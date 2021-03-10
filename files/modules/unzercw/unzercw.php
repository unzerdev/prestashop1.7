<?php
/**
 *  * You are allowed to use this API in your web application.
 *
 * Copyright (C) 2018 by customweb GmbH
 *
 * This program is licenced under the customweb software licence. With the
 * purchase or the installation of the software in your application you
 * accept the licence agreement. The allowed usage is outlined in the
 * customweb software licence which can be found under
 * http://www.sellxed.com/en/software-license-agreement
 *
 * Any modification or distribution is strictly forbidden. The license
 * grants you the installation in one application. For multiuse you will need
 * to purchase further licences at http://www.sellxed.com/shop.
 *
 * See the customweb software licence agreement for more details.
 *
 */
require_once _PS_MODULE_DIR_ . 'unzercw/lib/loader.php';

require_once _PS_MODULE_DIR_ . 'unzercw/lib/UnzerCw/TranslationResolver.php';

if (!defined('_PS_VERSION_'))
	exit();

/**
 * UnzerCw
 *
 * This class defines all central vars for the UnzerCw modules.
 * 
 *
 * @author customweb GmbH
 */
class UnzerCw extends Module {
	/**
	 *
	 * @var UnzerCw_ConfigurationApi
	 */
	private $configurationApi = null;
	public $trusted = true;
	const CREATE_PENDING_ORDER_KEY = 'CREATE_PENDING_ORDER';
	private static $recordMailMessages = false;
	private static $recordedMailMessages = array();
	private static $instance = null;
	private static $cancellingCheckIsRunning = false;
	private static $logListenerRegistered = false;
	private $initialized = false;
	private static $requiresExecuted = false;
	
	
	/**
	 * This method init the module.
	 */
	public function __construct(){
		
		// We have to make sure we can reuse the instance later.
		if (self::$instance === null) {
			self::$instance = $this;
		}
		
		$this->name = 'unzercw';
		$this->tab = 'checkout';
		$this->version = preg_replace('([^0-9\.a-zA-Z]+)', '', '1.0.55');
		$this->author = 'customweb ltd';
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		$this->bootstrap = true;
		
		parent::__construct();
		
		// The parent construct is required for translations 
		$this->displayName = UnzerCw::translate('DISPLAY NAME');
		$this->description = UnzerCw::translate('ACCEPTS PAYMENTS MAIN');
		$this->confirmUninstall = UnzerCw::translate('DELETE CONFIRMATION');
		
		if (Module::isInstalled($this->name) && !empty($this->id)) {
			$this->checkForCancellingRunningTransaction();
		}
		
		if (!isset($_GET['configure']) && $this->context->controller instanceof AdminModulesController && method_exists('Module', 'isModuleTrusted') &&
				 (!Module::isInstalled($this->name) || !Module::isInstalled('mailhook'))) {
		 	require_once 'UnzerCw/SmartyProxy.php';
			$this->context->smarty = new UnzerCw_SmartyProxy($this->context->smarty);
			if (!isset($GLOBALS['cwrmUnTrustedMs'])) {
				$GLOBALS['cwrmUnTrustedMs'] = array();
			}
			$GLOBALS['cwrmUnTrustedMs'][] = 'unzercw';
		}
		
		
		$this->handleChangesForAuthController();
	}
	
	
	/**
	 * This method loads the additional required classes and initializes all the things required to run the module.
	 */
	private function initialize() {
		if ($this->initialized === false) {
			$this->initialized = true;
			self::loadClasses();
			
			if (Module::isInstalled($this->name)) {
				$migration = new Customweb_Database_Migration_Manager(UnzerCw_Util::getDriver(), dirname(__FILE__) . '/updates/',
						_DB_PREFIX_ . 'unzercw_schema_version');
				$migration->migrate();
			}
			
			if (Module::isInstalled($this->name)) {
				$this->registerLogListener();
			}
		}
	}	
	
	
	private function checkLicense(){
		if (Module::isInstalled($this->getName())) {
			if (false) {
				$reason = Customweb_Licensing_UnzerCw_License::getValidationErrorMessage();
				if ($reason === null) {
					$reason = 'Unknown error.';
				}
				$token = Customweb_Licensing_UnzerCw_License::getCurrentToken();
				if (!isset($token)) {
					$token = 'Unknown';
				}
				return $this->displayError(
						UnzerCw::translate(
								'There is a problem with your license of your Unzer module. Please contact us (www.sellxed.com/support). Reason: !reason Current Token: !token',
								array(
									'!reason' => $reason,
									'!token' => $token
								)));
			}
		}
		return '';
	}
	
	
	private static function loadClasses() {
		if (self::$requiresExecuted === false) {
			self::$requiresExecuted = true;
			
			require_once 'Customweb/Payment/ExternalCheckout/IContext.php';
require_once 'Customweb/Util/Invoice.php';
require_once 'Customweb/Core/Exception/CastException.php';
require_once 'Customweb/Licensing/UnzerCw/License.php';
require_once 'Customweb/Payment/ExternalCheckout/IProviderService.php';
require_once 'Customweb/Core/Logger/Factory.php';
require_once 'Customweb/Core/Url.php';
require_once 'Customweb/Core/DateTime.php';
require_once 'Customweb/Core/String.php';
require_once 'Customweb/Database/Migration/Manager.php';
require_once 'Customweb/Payment/Authorization/ITransaction.php';

			require_once 'UnzerCw/ConfigurationApi.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/Entity/ExternalCheckoutContext.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/LoggingListener.php';
require_once 'UnzerCw/SmartyProxy.php';

			
			if (Module::isInstalled('mailhook')) {
				require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
				require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
				require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
			}
			
		}
	}
	

	private function getName(){
		return $this->name;
	}

	/**
	 * When pending orders are created, the stock may be reduced during the checkout.
	 * When
	 * the customer returns during the payment in the browser to the store, the stock is
	 * reserved for the customer, however he will never complete the payment. Hence we have to give
	 * the customer the option to cancel the running transaction.
	 */
	private function checkForCancellingRunningTransaction(){
		$controller = strtolower(Tools::getValue('controller'));
		if (($controller == 'order' || $controller == 'orderopc') && isset($this->context->cart) && !Configuration::get('PS_CATALOG_MODE') &&
				!$this->context->cart->checkQuantities()) {
			if ($this->isCreationOfPendingOrderActive() && self::$cancellingCheckIsRunning === false) {
				self::$cancellingCheckIsRunning = true;
				$originalCartId = $this->context->cart->id;
				UnzerCw_Util::getDriver()->beginTransaction();
				$cancelledTransactions = 0;
				try {
					$transactions = UnzerCw_Entity_Transaction::getTransactionsByOriginalCartId($originalCartId, false);
					foreach ($transactions as $transaction) {
						if ($transaction->getAuthorizationStatus() == Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_PENDING) {
							$transaction->forceTransactionFailing();
							$cancelledTransactions++;
						}
					}
					UnzerCw_Util::getDriver()->commit();
				}
				catch (Exception $e) {
					$this->context->controller->errors[] = $e->getMessage();
					UnzerCw_Util::getDriver()->rollBack();
				}
				if ($cancelledTransactions > 0) {
					$this->context->controller->errors[] = UnzerCw::translate(
							"It seems as you have not finished the payment. We have cancelled the running payment.");
				}
			}
			self::$cancellingCheckIsRunning = false;
		}
	}

	public static function getInstance(){
		if (self::$instance === null) {
			self::$instance = new UnzerCw();
		}
		
		return self::$instance;
	}

	/**
	 *
	 * @return UnzerCw_ConfigurationApi
	 */
	public function getConfigApi(){
		$this->initialize();
		if (empty($this->id)) {
			throw new Exception("Cannot initiate the config api wihtout the module id.");
		}
		
		if ($this->configurationApi == null) {
			$this->configurationApi = new UnzerCw_ConfigurationApi($this->id);
		}
		return $this->configurationApi;
	}

	/**
	 * This method installs the module.
	 *
	 * @return boolean if it was successful
	 */
	public function install(){
		$this->initialize();
		$this->installController('AdminUnzerCwRefund', 'Unzer Refund');
		$this->installController('AdminUnzerCwMoto', 'Unzer Moto');
		$this->installController('AdminUnzerCwForm', 'Unzer', 1, 
				Tab::getIdFromClassName('AdminParentModulesSf'));
		$this->installController('AdminUnzerCwTransaction', 'Unzer Transactions', 1);
		
		if (parent::install() && $this->installConfigurationValues() && $this->registerHook('adminOrder') && $this->registerHook('backOfficeHeader') &&
				 $this->registerHook('displayHeader') && $this->registerHook('displayCustomerAccountForm') && $this->registerHook('displayPDFInvoice')) {
			
			

			return true;
		}
		else {
			return false;
		}
	}

	public function installController($controllerName, $name, $active = 0, $parentId = null){
		$this->initialize();
		if ($parentId === null) {
			$parentId = Tab::getIdFromClassName('AdminParentOrders');
		}
		
		$tab_controller_main = new Tab();
		$tab_controller_main->active = $active;
		$tab_controller_main->class_name = $controllerName;
		foreach (Language::getLanguages() as $language) {
			//in Presta 1.5 the name length is limited to 32
			if (version_compare(_PS_VERSION_, '1.6') >= 0) {
				$tab_controller_main->name[$language['id_lang']] = substr($name, 0, 64);
			}
			else {
				//we have to cut the psp name otherwise, otherwise there could be an issue
				//where we can not distinguish the different controllers as all there visible names are identical
				if (strlen($name) > 32) {
					if (strpos($name, 'Unzer') !== false) {
						$name = str_replace('Unzer', '', $name);
						$length = strlen($name);
						if ($length < 32) {
							$pspName = substr('Unzer', 0, 32 - $length);
							$name = $pspName . $name;
						}
					}
				}
				$tab_controller_main->name[$language['id_lang']] = substr($name, 0, 32);
			}
		}
		$tab_controller_main->id_parent = $parentId;
		$tab_controller_main->module = $this->name;
		$tab_controller_main->add();
		$tab_controller_main->move(Tab::getNewLastPosition(0));
	}

	public function uninstall(){
		$this->initialize();
		$this->uninstallController('AdminUnzerCwRefund');
		$this->uninstallController('AdminUnzerCwMoto');
		$this->uninstallController('AdminUnzerCwForm');
		$this->uninstallController('AdminUnzerCwTransaction');
		
		return parent::uninstall() && $this->uninstallConfigurationValues();
	}

	public function uninstallController($controllerName){
		$this->initialize();
		$tab_controller_main_id = TabCore::getIdFromClassName($controllerName);
		$tab_controller_main = new Tab($tab_controller_main_id);
		$tab_controller_main->delete();
	}

	private function installConfigurationValues(){
		$this->getConfigApi()->updateConfigurationValue('CREATE_PENDING_ORDER', 'inactive');
		$this->getConfigApi()->updateConfigurationValue('OPERATING_MODE', 'test');
		$this->getConfigApi()->updateConfigurationValue('PUBLIC_KEY_LIVE', '');
		$this->getConfigApi()->updateConfigurationValue('PRIVATE_KEY_LIVE', '');
		$this->getConfigApi()->updateConfigurationValue('PUBLIC_KEY_TEST', '');
		$this->getConfigApi()->updateConfigurationValue('PRIVATE_KEY_TEST', '');
		$this->getConfigApi()->updateConfigurationValue('ORDER_ID_SCHEMA', '{id}');
		$this->getConfigApi()->updateConfigurationValue('PAYMENT_REFERENCE_SCHEMA', '{id}');
		$this->getConfigApi()->updateConfigurationValue('INVOICE_ID_SCHEMA', '{id}');
		$this->getConfigApi()->updateConfigurationValue('LOG_LEVEL', 'off');
		
		return true;
	}

	private function uninstallConfigurationValues(){
		$this->getConfigApi()->removeConfigurationValue('CREATE_PENDING_ORDER');
		$this->getConfigApi()->removeConfigurationValue('OPERATING_MODE');
		$this->getConfigApi()->removeConfigurationValue('PUBLIC_KEY_LIVE');
		$this->getConfigApi()->removeConfigurationValue('PRIVATE_KEY_LIVE');
		$this->getConfigApi()->removeConfigurationValue('PUBLIC_KEY_TEST');
		$this->getConfigApi()->removeConfigurationValue('PRIVATE_KEY_TEST');
		$this->getConfigApi()->removeConfigurationValue('ORDER_ID_SCHEMA');
		$this->getConfigApi()->removeConfigurationValue('PAYMENT_REFERENCE_SCHEMA');
		$this->getConfigApi()->removeConfigurationValue('INVOICE_ID_SCHEMA');
		$this->getConfigApi()->removeConfigurationValue('LOG_LEVEL');
		
		return true;
	}


	/**
	 * The main method for the configuration page.
	 *
	 * @return string html output
	 */
	public function getContent(){
		$this->initialize();
		$this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/css/admin.css');
		
		$html = '';
		$html .= $this->checkLicense();
		
		if (isset($_POST['submit_unzercw'])) {
			
			if (isset($_POST[self::CREATE_PENDING_ORDER_KEY]) && $_POST[self::CREATE_PENDING_ORDER_KEY] == 'active') {
				$this->registerHook('actionMailSend');
				if (!self::isInstalled('mailhook')) {
					$html .= $this->displayError(
							UnzerCw::translate(
									"The module 'Mail Hook' must be activated, when using the option 'create pending order', otherwise the mail sending behavior may be inappropriate."));
				}
			}
			
			$fields = $this->getConfigApi()->convertFieldTypes($this->getFormFields());
			$this->getConfigApi()->processConfigurationSaveAction($fields);
			$html .= $this->displayConfirmation(UnzerCw::translate('Settings updated'));
		}
		
		$html .= $this->getConfigurationForm();
		
		return $html;
	}

	private function getConfigurationForm(){
		$link = new Link();
		$fields = $this->getConfigApi()->convertFieldTypes($this->getFormFields());
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get(
				'PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int) Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submit_unzercw';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab .
				 '&module_name=' . $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigApi()->getConfigurationValues($fields),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id 
		);
		
		$forms = array(
			array(
				'form' => array(
					'legend' => array(
						'title' => 'Unzer: ' . UnzerCw::translate('Settings'),
						'icon' => 'icon-envelope' 
					),
					'input' => $fields,
					'submit' => array(
						'title' => UnzerCw::translate('Save') 
					) 
				) 
			) 
		);
		
		return $helper->generateForm($forms);
	}

	protected function getFormFields(){
		$this->initialize();
		$fields = array(
			0 => array(
				'name' => 'CREATE_PENDING_ORDER',
 				'label' => $this->l("Create Pending Order"),
 				'desc' => $this->l("By creating pending orders the module will create a order before the payment is authorized. This not PrestaShop standard and may introduce some issues. However the module can send the order number to , which can reduce the overhead for the reconsilation. To use this feature the 'Mail Hook' module must be activated."),
 				'default' => 'inactive',
 				'type' => 'select',
 				'options' => array(
					'query' => array(
						0 => array(
							'id' => 'inactive',
 							'name' => $this->l("Inactive"),
 						),
 						1 => array(
							'id' => 'active',
 							'name' => $this->l("Active"),
 						),
 					),
 					'name' => 'name',
 					'id' => 'id',
 				),
 			),
 			1 => array(
				'name' => 'OPERATING_MODE',
 				'label' => $this->l("Operation Mode"),
 				'desc' => $this->l("Operation mode of the shop.
			"),
 				'default' => 'test',
 				'type' => 'select',
 				'options' => array(
					'query' => array(
						0 => array(
							'id' => 'test',
 							'name' => $this->l("Test"),
 						),
 						1 => array(
							'id' => 'live',
 							'name' => $this->l("Live"),
 						),
 					),
 					'name' => 'name',
 					'id' => 'id',
 				),
 			),
 			2 => array(
				'name' => 'PUBLIC_KEY_LIVE',
 				'label' => $this->l("Public Key (Live)"),
 				'desc' => $this->l("Public Key for live requests, provided by
				.
			"),
 				'default' => '',
 				'type' => 'text',
 			),
 			3 => array(
				'name' => 'PRIVATE_KEY_LIVE',
 				'label' => $this->l("Private Key (Live)"),
 				'desc' => $this->l("Private Key for live requests, provided by
				.
			"),
 				'default' => '',
 				'type' => 'text',
 			),
 			4 => array(
				'name' => 'PUBLIC_KEY_TEST',
 				'label' => $this->l("Public Key (Test)"),
 				'desc' => $this->l("Public Key for test requests, provided by
				.
			"),
 				'default' => '',
 				'type' => 'text',
 			),
 			5 => array(
				'name' => 'PRIVATE_KEY_TEST',
 				'label' => $this->l("Private Key (Test)"),
 				'desc' => $this->l("Private Key for test requests, provided by
				.
			"),
 				'default' => '',
 				'type' => 'text',
 			),
 			6 => array(
				'name' => 'ORDER_ID_SCHEMA',
 				'label' => $this->l("OrderId Schema"),
 				'desc' => $this->l("Here you can set a schema for the orderId parameter
				transmitted to identify the payment. If left empty it is
				not
				transmitted. The following placeholders can be used: {oid} for
				the
				order id, which may not be unique or set; {tid} for the sellxed
				transaction
				id which is a unique number, or {id} which contains the
				order id and
				is guaranteed to be unique.
			"),
 				'default' => '{id}',
 				'type' => 'text',
 			),
 			7 => array(
				'name' => 'PAYMENT_REFERENCE_SCHEMA',
 				'label' => $this->l("PaymentReference Schema"),
 				'desc' => $this->l("Here you can set a schema for the paymentReference
				parameter
				transmitted to identify the payment. If left empty it is
				not transmitted. The following placeholders can be used: {oid} for
				the order id, which may not be unique or set; {tid} for the sellxed
				transaction id which is a unique number, or {id} which contains the
				order id and is guaranteed to be unique.
			"),
 				'default' => '{id}',
 				'type' => 'text',
 			),
 			8 => array(
				'name' => 'INVOICE_ID_SCHEMA',
 				'label' => $this->l("InvoiceID Schema"),
 				'desc' => $this->l("Here you can set a schema for the invoiceId parameter
				transmitted to identify the payment. If left empty it is
				not
				transmitted. The following placeholders can be used: {oid} for
				the
				order id, which may not be unique or set; {tid} for the sellxed
				transaction
				id which is a unique number, or {id} which contains the
				order id and
				is guaranteed to be unique.
			"),
 				'default' => '{id}',
 				'type' => 'text',
 			),
 			9 => array(
				'name' => 'LOG_LEVEL',
 				'label' => $this->l("Log Level"),
 				'desc' => $this->l("Messages of this or a higher level will be logged."),
 				'default' => 'off',
 				'type' => 'select',
 				'options' => array(
					'query' => array(
						0 => array(
							'id' => 'off',
 							'name' => $this->l("Off"),
 						),
 						1 => array(
							'id' => 'error',
 							'name' => $this->l("Error"),
 						),
 						2 => array(
							'id' => 'info',
 							'name' => $this->l("Info"),
 						),
 						3 => array(
							'id' => 'debug',
 							'name' => $this->l("Debug"),
 						),
 					),
 					'name' => 'name',
 					'id' => 'id',
 				),
 			),
 		);
		
		return $fields;
	}

	public function getPath(){
		return $this->_path;
	}

	public function hookDisplayHeader(){
		// In the one page checkout the CSS files are not loaded. This method adds therefore the missing CSS files on
		// this page. 
		if ($this->context->controller instanceof OrderOpcController) {
			$this->context->controller->addCSS(_MODULE_DIR_ . 'unzercw/css/style.css');
		}
	}

	public function hookDisplayBeforeShoppingCartBlock(){
		
		return '';
	}

	public function hookDisplayPDFInvoice($object){
		if (!isset($object['object'])) {
			return;
		}
		$orderInvoice = $object['object'];
		if (!($orderInvoice instanceof OrderInvoice)) {
			return;
		}
		$this->initialize();
		$transactions = UnzerCw_Entity_Transaction::getTransactionsByOrderId($orderInvoice->id_order);
		$transactionObject = null;
		foreach ($transactions as $transaction) {
			if ($transaction->getTransactionObject() !== null && $transaction->getTransactionObject()->isAuthorized()) {
				$transactionObject = $transaction->getTransactionObject();
				break;
			}
		}
		if ($transactionObject === null) {
			return;
		}
		$paymentInformation = $transactionObject->getPaymentInformation();
		$result = '';
		if (!empty($paymentInformation)) {
			$result .= '<div class="unzercw-invoice-payment-information" id="unzercw-invoice-payment-information">';
			$result .= $paymentInformation;
			$result .= '</div>';
		}
		return $result;
	}
	
	
	private function handleChangesForAuthController(){
		
	}
	
	
	public function sortCheckouts($a, $b){
		if (isset($a['sortOrder']) && isset($b['sortOrder'])) {
			if ($a['sortOrder'] < $b['sortOrder']) {
				return -1;
			}
			else if ($a['sortOrder'] > $b['sortOrder']) {
				return 1;
			}
			else {
				return 0;
			}
		}
		else {
			return 0;
		}
	}

	public function hookBackOfficeHeader(){
		$id_order = Tools::getValue('id_order');
		
		// Check if we need to ask the customer to refund the amount 
		if ((isset($_POST['partialRefund']) || isset($_POST['cancelProduct'])) && !isset($_GET['confirmed']) && !(isset($_POST['generateDiscountRefund']) && $_POST['generateDiscountRefund']== 'on')) {
			$this->initialize();
			$transaction = current(UnzerCw_Entity_Transaction::getTransactionsByOrderId($id_order));
			if (is_object($transaction) && $transaction->getTransactionObject() !== null &&
					 $transaction->getTransactionObject()->isPartialRefundPossible()) {
				$order = new Order($id_order);
				if ($order->module == ('unzercw_' . $transaction->getPaymentMachineName())) {
					$url = '?controller=AdminUnzerCwRefund&token=' . Tools::getAdminTokenLite('AdminUnzerCwRefund');
					$url .= '&' . Customweb_Core_Url::parseArrayToString($_POST);
					header('Location: ' . $url);
					die();
				}
			}
		}
		
		if (isset($_POST['submitUnzerCwRefundAuto'])) {
			$this->initialize();
			try {
				$transaction = current(UnzerCw_Entity_Transaction::getTransactionsByOrderId($id_order));
				$this->refundTransaction($transaction->getTransactionId(), self::getRefundAmount($_POST));
			}
			catch (Exception $e) {
				$this->context->controller->errors[] = UnzerCw::translate("Could not refund the transaction: ") . $e->getMessage();
				unset($_POST['partialRefund']);
				unset($_POST['cancelProduct']);
			}
		}
		
		

		
	}

	public function hookActionMailSend($data){
		$this->initialize();
		if ($this->isCreationOfPendingOrderActive()) {
			if (!isset($data['event'])) {
				throw new Exception("No item 'event' provided in the mail action function.");
			}
			$event = $data['event'];
			if (!($event instanceof MailMessageEvent)) {
				throw new Exception("Invalid type provided by the mail send action.");
			}
			
			if (self::isRecordingMailMessages()) {
				foreach ($event->getMessages() as $message) {
					self::$recordedMailMessages[] = $message;
				}
				$event->setMessages(array());
			}
		}
	}

	public static function isRecordingMailMessages(){
		return self::$recordMailMessages;
	}

	public static function startRecordingMailMessages(){
		self::$recordMailMessages = true;
		self::$recordedMailMessages = array();
	}

	/**
	 *
	 * @return MailMessage[]
	 */
	public static function stopRecordingMailMessages(){
		self::$recordMailMessages = false;
		
		return self::$recordedMailMessages;
	}

	public function isCreationOfPendingOrderActive(){
		$this->initialize();
		$createPendingOrder = $this->getConfigApi()->getConfigurationValue(self::CREATE_PENDING_ORDER_KEY);
		
		if ($createPendingOrder == 'active') {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * This method extracts the refund amount from the POST data.
	 *
	 * @param array $data
	 * @return float amount
	 */
	public static function getRefundAmount($data){
		$amount = 0;
		$order_detail_list = array();
		$isPartial = false;
		if (isset($data['partialRefund'])){
			$isPartial = true;
		}
		
		if($isPartial){
			if(isset($data['refund_voucher_off']) && $data['refund_voucher_off'] == "2" && isset($data['refund_voucher_choose'])){
				return $data['refund_voucher_choose'];
			}
			
		}else{
			if(isset($data['refund_total_voucher_off']) && $data['refund_total_voucher_off'] == "2" && isset($data['refund_total_voucher_choose'])){
				return $data['refund_total_voucher_choose'];
			}
		}
		
		if (isset($data['partialRefundProduct'])) {
			foreach ($data['partialRefundProduct'] as $id_order_detail => $amount_detail) {
				$order_detail_list[$id_order_detail]['quantity'] = (int) $data['partialRefundProductQuantity'][$id_order_detail];
				
				if (empty($amount_detail)) {
					$order_detail = new OrderDetail((int) $id_order_detail);
					$order_detail_list[$id_order_detail]['amount'] = $order_detail->unit_price_tax_incl *
							 $order_detail_list[$id_order_detail]['quantity'];
				}
				else {
					$order_detail_list[$id_order_detail]['amount'] = (float) str_replace(',', '.', $amount_detail);
				}
				$amount += $order_detail_list[$id_order_detail]['amount'];
			}
			
			$shipping_cost_amount = (float) str_replace(',', '.', $data['partialRefundShippingCost']);
			if ($shipping_cost_amount > 0) {
				$amount += $shipping_cost_amount;
			}
		}
		
		// When the amount is not zero, we should consider also cancelQuantity. Otherwise the partialRefundProduct contains already the relevant stufff and
		// we do not need to take a look on cancelQuantity.
		if (isset($data['cancelQuantity']) && $amount == 0) {
			foreach ($data['cancelQuantity'] as $id_order_detail => $quantity) {
				$q = (int) $quantity;
				if ($q > 0) {
					$order_detail = new OrderDetail((int) $id_order_detail);
					$line_amount = $order_detail->unit_price_tax_incl * $q;
					$amount += $line_amount;
				}
			}
		}
		
		if($isPartial){
			if(isset($data['refund_voucher_off']) && $data['refund_voucher_off'] == "1"){
				$amount -= trim($data['order_discount_price']);
			}
		}else{
			if(isset($data['refund_total_voucher_off']) && $data['refund_total_voucher_off'] == "1"){
				$amount -= trim($data['order_discount_price']);
			}
		}
		
		
		return $amount;
	}

	/**
	 * This method is used to add a special info field in the order
	 * Tab.
	 *
	 * @param array $params Hook parameters
	 * @return string the html output
	 */
	public function hookAdminOrder($params){
		$html = '';
		
		$order = new Order((int) $params['id_order']);
		if (!strstr($order->module, 'unzercw')) {
			return '';
		}
		$this->initialize();
		$errorMessage = '';
		try {
			$this->processAdminAction();
		}
		catch (Exception $e) {
			$errorMessage = $e->getMessage();
		}
		
		$transactions = UnzerCw_Entity_Transaction::getTransactionsByCartOrOrder($order->id_cart, $order->id);
		
		if (is_array($transactions) && count($transactions) > 0) {
			
			$activeTransactionId = false;
			if (isset($_POST['id_transaction'])) {
				$activeTransactionId = $_POST['id_transaction'];
			}
			
			$this->context->smarty->assign(
					array(
						'order_id' => $params['id_order'],
						'base_url' => _PS_BASE_URL_SSL_ . __PS_BASE_URI__,
						'transactions' => $transactions,
						'date_format' => $this->context->language->date_format_full,
						'errorMessage' => $errorMessage,
						'activeTransactionId' => $activeTransactionId 
					));
// 			$this->error = $errorMessage;
			
			$this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/css/admin.css');
			$this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/js/admin.js');
			$html .= $this->evaluateTemplate('/views/templates/back/admin_order.tpl');
		}
		
		return $html;
	}

	public function getConfigurationValue($key, $langId = null){
		return $this->getConfigApi()->getConfigurationValue($key, $langId);
	}

	public function hasConfigurationKey($key, $langId = null){
		return $this->getConfigApi()->hasConfigurationKey($key, $langId);
	}

	private function processAdminAction(){
		if (isset($_POST['id_transaction'])) {
			
			
			if (isset($_POST['submitUnzerCwRefund'])) {
				$amount = null;
				if (isset($_POST['refund_amount'])) {
					$amount = $_POST['refund_amount'];
				}
				
				$close = false;
				if (isset($_POST['close']) && $_POST['close'] == '1') {
					$close = true;
				}
				$this->refundTransaction($_POST['id_transaction'], $amount, $close);
			}
			
			

			
			if (isset($_POST['submitUnzerCwCancel'])) {
				$this->cancelTransaction($_POST['id_transaction']);
			}
			
			

			
			if (isset($_POST['submitUnzerCwCapture'])) {
				$amount = null;
				if (isset($_POST['capture_amount'])) {
					$amount = $_POST['capture_amount'];
				}
				
				$close = false;
				if (isset($_POST['close']) && $_POST['close'] == '1') {
					$close = true;
				}
				$this->captureTransaction($_POST['id_transaction'], $amount, $close);
			}
			
		}
	}
	
	
	public function refundTransaction($transactionId, $amount = null, $close = false){
		$this->initialize();
		$dbTransaction = UnzerCw_Entity_Transaction::loadById($transactionId);
		$adapter = UnzerCw_Util::createContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_IRefund');
		if ($dbTransaction->getTransactionObject() != null && $dbTransaction->getTransactionObject()->isRefundPossible()) {
			if ($amount !== null) {
				$items = Customweb_Util_Invoice::getItemsByReductionAmount(
						$dbTransaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getInvoiceItems(), $amount, 
						$dbTransaction->getTransactionObject()->getCurrencyCode());
				$adapter->partialRefund($dbTransaction->getTransactionObject(), $items, $close);
			}
			else {
				$adapter->refund($dbTransaction->getTransactionObject());
			}
			UnzerCw_Util::getEntityManager()->persist($dbTransaction);
		}
		else {
			throw new Exception("The given transaction is not refundable.");
		}
	}
	
	

	
	public function captureTransaction($transactionId, $amount = null, $close = false){
		$this->initialize();
		$dbTransaction = UnzerCw_Entity_Transaction::loadById($transactionId);
		$adapter = UnzerCw_Util::createContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_ICapture');
		if ($dbTransaction->getTransactionObject() != null && $dbTransaction->getTransactionObject()->isCapturePossible()) {
			if ($amount !== null) {
				$items = Customweb_Util_Invoice::getItemsByReductionAmount(
						$dbTransaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getInvoiceItems(), $amount, 
						$dbTransaction->getTransactionObject()->getCurrencyCode());
				$adapter->partialCapture($dbTransaction->getTransactionObject(), $items, $close);
			}
			else {
				$adapter->capture($dbTransaction->getTransactionObject());
			}
			UnzerCw_Util::getEntityManager()->persist($dbTransaction);
		}
		else {
			throw new Exception("The given transaction is not capturable.");
		}
	}
	
	

	
	public function cancelTransaction($transactionId){
		$this->initialize();
		$dbTransaction = UnzerCw_Entity_Transaction::loadById($transactionId);
		$adapter = self::createContainer()->getBean('Customweb_Payment_BackendOperation_Adapter_Service_ICancel');
		if ($dbTransaction->getTransactionObject() != null && $dbTransaction->getTransactionObject()->isCancelPossible()) {
			$adapter->cancel($dbTransaction->getTransactionObject());
			UnzerCw_Util::getEntityManager()->persist($dbTransaction);
		}
		else {
			throw new Exception("The given transaction cannot be cancelled.");
		}
	}
	
	private function evaluateTemplate($file){
		return $this->display(__FILE__, $file);
	}

	public function l($string, $specific = null, $id_lang = null){
		return self::translate($string, $specific);
	}

	public static function translate($string, $sprintf = null, $module = 'unzercw'){
		$stringOriginal = $string;
		$string = str_replace("\n", " ", $string);
		$string = preg_replace("/\t++/", " ", $string);
		$string = preg_replace("/( +)/", " ", $string);
		$string = preg_replace("/[^a-zA-Z0-9]*/", "", $string);
		
		$rs = Translate::getModuleTranslation($module, $string, $module, $sprintf);
		if ($string == $rs) {
			$rs = $stringOriginal;
		}
		
		if ($sprintf !== null && is_array($sprintf)) {
			$rs = Customweb_Core_String::_($rs)->format($sprintf);
		}
		
		if (version_compare(_PS_VERSION_, '1.6') > 0) {
			return htmlspecialchars_decode($rs);
		}
		else {
			return $rs;
		}
	}

	public static function getAdminUrl($controller, array $params, $token = true){
		if ($token) {
			$params['token'] = Tools::getAdminTokenLite($controller);
		}
		$id_lang = Context::getContext()->language->id;
		$path = Dispatcher::getInstance()->createUrl($controller, $id_lang, $params, false);
		$protocol = 'http://';
		$sslEnabled = Configuration::get('PS_SSL_ENABLED');
		$sslEverywhere = Configuration::get('PS_SSL_ENABLED_EVERYWHERE');
		if ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') || $sslEnabled == '1' || $sslEverywhere == '1'){
			$protocol = 'https://';
		}
		
		return $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER["SCRIPT_NAME"]) . '/' . ltrim($path, '/');
	}

	private static function getShopIds(){
		$shops = array();
		$rs = Db::getInstance()->query('
				SELECT
					id_shop
				FROM
					`' . _DB_PREFIX_ . 'shop`');
		foreach ($rs as $data) {
			$shops[] = $data['id_shop'];
		}
		return $shops;
	}
	
	private function registerLogListener(){
		if (!self::$logListenerRegistered) {
			self::$logListenerRegistered = true;
			$level = UnzerCw::getInstance()->getConfigurationValue('log_level');
			if(strtolower($level) != 'off'){
				Customweb_Core_Logger_Factory::addListener(new UnzerCw_LoggingListener());
			}
		}
	}
}

// Register own translation function in smarty 
if (!function_exists('cwSmartyTranslate')) {
	global $smarty;

	function cwSmartyTranslate($params, $smarty){
		$sprintf = isset($params['sprintf']) ? $params['sprintf'] : null;
		if (empty($params['mod'])) {
			throw new Exception(sprintf("Could not translate string '%s' because no module was provided.", $params['s']));
		}
		
		return UnzerCw::translate($params['s'], $sprintf, $params['mod']);
	}
	smartyRegisterFunction($smarty, 'function', 'lcw', 'cwSmartyTranslate', false);
}



