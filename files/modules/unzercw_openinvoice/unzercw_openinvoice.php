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
require_once _PS_MODULE_DIR_ . '/unzercw/unzercw.php';
require_once 'UnzerCw/IPaymentMethod.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

/**
 * UnzerCw_OpenInvoice
 *
 * This class defines is the module class for the
 * payment method "OpenInvoice".
 *
 * @author customweb GmbH
 */
class UnzerCw_OpenInvoice extends PaymentModule implements UnzerCw_IPaymentMethod {
	/**
	 *
	 * @var UnzerCw_ConfigurationApi
	 */
	private $configurationApi = null;
	public $currencies = true;
	public $currencies_mode = 'checkbox';
	public $version = '1.0.55';
	public $author = 'customweb ltd';
	public $is_eu_compatible = 1;
	public $name = 'unzercw_openinvoice';
	public $paymentMethodName = 'openinvoice';
	public $paymentMethodDisplayName = 'Invoice';
	private $transactionContext = null;
	private static $requiresExecuted = false;

	/**
	 * This method init the module.
	 *
	 * 
	 */
	public function __construct(){
		Context::getContext()->smarty->addTemplateDir($this->getModuleFrontendTemplateDirectory());
		parent::__construct();
		
		// The parent construct is required for translations
		if (defined('_PS_ADMIN_DIR_') || empty($this->id)) {
			$this->displayName = 'Unzer: ' . $this->paymentMethodDisplayName;
		}
		else {
			$this->displayName = $this->getPaymentMethodDisplayName();
		}
		
		$this->description = str_replace('!PaymentMethodName', $this->paymentMethodDisplayName, UnzerCw::translate('ACCEPTS PAYMENTS'));
		$this->confirmUninstall = UnzerCw::translate('DELETE CONFIRMATION');
		$this->tab = 'payments_gateways';
		$this->bootstrap = true;
		if (!isset($_GET['configure']) && $this->context->controller instanceof AdminModulesController && method_exists('Module', 'isModuleTrusted') &&
				 (!Module::isInstalled($this->name) || !Module::isInstalled('mailhook'))) {
			require_once 'UnzerCw/SmartyProxy.php';
			$this->context->smarty = new UnzerCw_SmartyProxy($this->context->smarty);
		}
		$this->ps_versions_compliancy = array(
			'min' => '1.7',
			'max' => _PS_VERSION_ 
		);
	}

	/**
	 * Loads the required classes.
	 */
	private static function loadClasses(){
		if (self::$requiresExecuted === false) {
			self::$requiresExecuted = true;
			
			require_once 'Customweb/Payment/Authorization/IAdapter.php';

			require_once 'UnzerCw/ConfigurationApi.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/TransactionContext.php';
require_once 'UnzerCw/OrderContext.php';
require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/IPaymentMethod.php';
require_once 'UnzerCw/OrderStatus.php';
require_once 'UnzerCw/PaymentMethodWrapper.php';
require_once 'UnzerCw/SmartyProxy.php';
require_once 'UnzerCw/Adapter/AbstractAdapter.php';

		}
	}

	private function getModuleFrontendTemplateDirectory(){
		return _PS_MODULE_DIR_ . 'unzercw/views/templates/front/';
	}

	public function hookPaymentOptions($params){
		self::loadClasses();
		try {
			if (!$this->isPaymentMethodVisible()) {
				return [];
			}
			
			$payment_options = [
				$this->getEmbeddedPaymentOption() 
			];
			
			return $payment_options;
		}
		catch (Customweb_Payment_Authorization_Method_PaymentMethodResolutionException $exc) {
			return [];
		}
	}

	/**
	 * This method hooks into the return payment hook.
	 * It use allways the order_confirmation.tpl!
	 *
	 * @param array $params the params of the hook point
	 * @return string the html output
	 */
	public function hookPaymentReturn($params){
		self::loadClasses();
		$this->context->controller->addCSS(_MODULE_DIR_ . 'unzercw/css/style.css');
		$paymentMethodMessage = $this->getPaymentMethodConfigurationValue('MESSAGE_AFTER_ORDER', $this->context->language->language_code);
		
		$id_cart = (int) (Tools::getValue('id_cart', 0));
		$order = new Order(Order::getOrderByCartId($id_cart));
		$orderId = $order->id;
		$transactions = UnzerCw_Entity_Transaction::getTransactionsByOrderId($orderId);
		$transaction = current($transactions);
		
		$nameBackup = $this->name;
		$this->name = 'unzercw';
		
		$paymentInformation = null;
		$paymentInformationTitle = UnzerCw::translate("Payment Information");
		if ($transaction->getTransactionObject() !== null && $transaction->getTransactionObject()->isAuthorized()) {
			$paymentInformation = $transaction->getTransactionObject()->getPaymentInformation();
		}
		$this->name = $nameBackup;
		$this->context->smarty->assign(
				[
					'paymentMethodMessage' => $paymentMethodMessage,
					'paymentInformationTitle' => $paymentInformationTitle,
					'paymentInformation' => $paymentInformation 
				]);
		
		return $this->context->smarty->fetch('module:unzercw/views/templates/hook/payment_return.tpl');
	}

	public function getPaymentPane(){
		self::loadClasses();
		try {
			$orderContext = $this->getOrderContext();
			$adapter = UnzerCw_Util::getShopAdapterByPaymentAdapter($this->getAuthorizationAdapter($orderContext));
			
			$errorTransaction = null;
			/* @var $request Customweb_Core_Http_IRequest */
			$errorId = Tools::getValue('error_transaction_id', false);
			$moduleId = Tools::getValue('id_module', false);
			if ($moduleId == $this->id && !empty($errorId)) {
				$errorTransaction = UnzerCw_Entity_Transaction::loadById($errorId);
			}
			
			$adapter->prepareCheckout($this, $orderContext, $errorTransaction, false);
			$form = $adapter->getCheckoutPageForm();
			
			// In a default PrestaShop everything is UTF-8 and as such decoding at this point should
			// not be required.
			
			return $form;
		}
		catch (Exception $e) {
			return $this->createErrorForm($e->getMessage());
		}
	}

	private function createErrorForm($errorMessage){
		return $errorMessage;
	}

	public function getEmbeddedPaymentOption(){
		self::loadClasses();
		$embeddedOption = new PaymentOption();
		// @formatter:off
		$embeddedOption
				->setCallToActionText($this->getPaymentMethodDisplayName())
				->setAction($this->getShopAdapter()->getRedirectionUrl())
				->setForm($this->getPaymentPane())
				->setBinary(false)
				->setLogo($this->getPaymentMethodLogo());
		//->setAdditionalInformation($this->context->smarty->fetch('module:unzercw/views/templates/front/payment_infos.tpl'))
		// @formatter:on
		return $embeddedOption;
	}

	public function getFormFields(){
		self::loadClasses();
		$fields = array(
			0 => array(
				'name' => 'SEND_CUSTOMER',
 				'label' => $this->l("Send Customer"),
 				'desc' => $this->l("Should customer data be transmitted to
						? This slightly increases the
						processing time due to an additional request, but may allow e.g.
						saving the payment method to the customer.
					"),
 				'default' => 'no',
 				'type' => 'select',
 				'options' => array(
					'query' => array(
						0 => array(
							'id' => 'no',
 							'name' => $this->l("Do not send"),
 						),
 						1 => array(
							'id' => 'yes',
 							'name' => $this->l("Send Customer"),
 						),
 					),
 					'name' => 'name',
 					'id' => 'id',
 				),
 			),
 			1 => array(
				'name' => 'STATUS_AUTHORIZED',
 				'label' => $this->l("Authorized Status"),
 				'desc' => $this->l("This status is set, when the payment was successfull
						and it is authorized.
					"),
 				'default' => 'authorized',
 				'order_status' => array(
				),
 				'type' => 'orderstatus',
 			),
 			2 => array(
				'name' => 'STATUS_UNCERTAIN',
 				'label' => $this->l("Uncertain Status"),
 				'desc' => $this->l("You can specify the order status for new orders that
						have an uncertain authorisation status.
					"),
 				'default' => 'uncertain',
 				'order_status' => array(
				),
 				'type' => 'orderstatus',
 			),
 			3 => array(
				'name' => 'STATUS_CANCELLED',
 				'label' => $this->l("Cancelled Status"),
 				'desc' => $this->l("You can specify the order status when an order is
						cancelled.
					"),
 				'default' => 'cancelled',
 				'order_status' => array(
					0 => array(
						'id' => 'no_status_change',
 						'name' => $this->l("Don't change order status"),
 					),
 				),
 				'type' => 'orderstatus',
 			),
 			4 => array(
				'name' => 'STATUS_CAPTURED',
 				'label' => $this->l("Captured Status"),
 				'desc' => $this->l("You can specify the order status for orders that are
						captured either directly after the order or manually in the
						backend.
					"),
 				'default' => 'no_status_change',
 				'order_status' => array(
					0 => array(
						'id' => 'no_status_change',
 						'name' => $this->l("Don't change order status"),
 					),
 				),
 				'type' => 'orderstatus',
 			),
 			5 => array(
				'name' => 'SEND_BASKET',
 				'label' => $this->l("Send Basket"),
 				'desc' => $this->l("Should the invoice items be transmitted to
						? This slightly increases the
						processing time due to an additional request, and may cause issues
						for certain quantity / price combinations.
					"),
 				'default' => 'no',
 				'type' => 'select',
 				'options' => array(
					'query' => array(
						0 => array(
							'id' => 'no',
 							'name' => $this->l("Do not send"),
 						),
 						1 => array(
							'id' => 'yes',
 							'name' => $this->l("Send Basket"),
 						),
 					),
 					'name' => 'name',
 					'id' => 'id',
 				),
 			),
 			6 => array(
				'name' => 'AUTHORIZATIONMETHOD',
 				'label' => $this->l("Authorization Method"),
 				'desc' => $this->l("Select the authorization method to use for processing this payment method."),
 				'default' => 'AjaxAuthorization',
 				'type' => 'select',
 				'options' => array(
					'query' => array(
						0 => array(
							'id' => 'AjaxAuthorization',
 							'name' => $this->l("Ajax Authorization"),
 						),
 					),
 					'name' => 'name',
 					'id' => 'id',
 				),
 			),
 		);
$fields = array_merge($this->getFormFieldsInner(), $fields);
		return $fields;
	}

	protected function getTemplateBasePath(){
		$filePath = str_replace('lib/UnzerCw/PaymentMethod', 'unzercw', __FILE__);
		$filePath = str_replace('lib\\UnzerCw\\PaymentMethod', 'unzercw', $filePath);
		return $filePath;
	}

	public function getPaymentMethodLogo(){
		return $this->_path . '/logo.png';
	}

	/**
	 * This method installs the module.
	 *
	 * @return boolean if it was successful
	 */
	public function install(){
		self::loadClasses();
		return parent::install() && $this->installPaymentConfigurations() && $this->registerHook('paymentOptions') &&
				 $this->registerHook('paymentReturn') && $this->registerHook('header') && $this->installMethodSpecificConfigurations();
	}

	public function uninstall(){
		self::loadClasses();
		return parent::uninstall() && $this->uninstallMethodSpecificConfigurations() && $this->uninstallPaymentConfigurations();
	}

	public function installMethodSpecificConfigurations(){
		self::loadClasses();
		$this->getConfigApi()->updateConfigurationValue('SEND_CUSTOMER', 'no');
		$this->getConfigApi()->updateConfigurationValue('STATUS_AUTHORIZED', Configuration::get('PS_OS_PAYMENT'));
		$this->getConfigApi()->updateConfigurationValue('STATUS_UNCERTAIN', Configuration::get('PS_OS_PREPARATION'));
		$this->getConfigApi()->updateConfigurationValue('STATUS_CANCELLED', Configuration::get('PS_OS_CANCELED'));
		$this->getConfigApi()->updateConfigurationValue('STATUS_CAPTURED', 'no_status_change');
		$this->getConfigApi()->updateConfigurationValue('SEND_BASKET', 'no');
		$this->getConfigApi()->updateConfigurationValue('AUTHORIZATIONMETHOD', 'AjaxAuthorization');
		
		return true;
	}

	public function uninstallMethodSpecificConfigurations(){
		self::loadClasses();
		$this->getConfigApi()->removeConfigurationValue('SEND_CUSTOMER');
		$this->getConfigApi()->removeConfigurationValue('STATUS_AUTHORIZED');
		$this->getConfigApi()->removeConfigurationValue('STATUS_UNCERTAIN');
		$this->getConfigApi()->removeConfigurationValue('STATUS_CANCELLED');
		$this->getConfigApi()->removeConfigurationValue('STATUS_CAPTURED');
		$this->getConfigApi()->removeConfigurationValue('SEND_BASKET');
		$this->getConfigApi()->removeConfigurationValue('AUTHORIZATIONMETHOD');
		;
		return true;
	}

	public function getPaymentMethodConfigurationValue($key, $languageCode = null){
		self::loadClasses();
		$multiSelectKeys = array(
		);
		$rs = $this->getPaymentMethodConfigurationValueInner($key, $languageCode);
		if (isset($multiSelectKeys[$key])) {
			if (empty($rs)) {
				return array();
			}
			else {
				return explode(',', $rs);
			}
		}
		else {
			return $rs;
		}
	}

	/**
	 *
	 * @return UnzerCw_ConfigurationApi
	 */
	public function getConfigApi(){
		if (empty($this->id)) {
			throw new Exception("Cannot initiate the config api wihtout the module id.");
		}
		
		if ($this->configurationApi == null) {
			require_once 'UnzerCw/ConfigurationApi.php';
			$this->configurationApi = new UnzerCw_ConfigurationApi($this->id);
		}
		return $this->configurationApi;
	}

	public function getPaymentMethodName(){
		return $this->paymentMethodName;
	}

	public function getPaymentMethodDisplayName(){
		$configuredName = $this->getConfigApi()->getConfigurationValue('METHOD_NAME', $this->context->language->id);
		if (!empty($configuredName)) {
			return $configuredName;
		}
		else {
			return $this->paymentMethodDisplayName;
		}
	}

	public function getPaymentMethodDescription(){
		$configuredDescription = $this->getConfigApi()->getConfigurationValue('METHOD_DESCRIPTION', $this->context->language->id);
		if (!empty($configuredDescription)) {
			return $configuredDescription;
		}
		else {
			return '';
		}
	}

	public function hookDisplayHeader(){
		$this->context->controller->addCSS(_MODULE_DIR_ . 'unzercw/css/style.css');
		$this->context->controller->addJS(_MODULE_DIR_ . 'unzercw/js/frontend.js');
		// 		if(isset($_REQUEST['cw_error'])) {
		// 			$controller = $this->context->controller;
		// 			if($controller instanceof FrontController) {
		// 				$controller->errors
		// 			}
		// 		}
	}

	public function installPaymentConfigurations(){
		$this->getConfigApi()->updateConfigurationValue('MESSAGE_AFTER_ORDER', '');
		
		$languages = Language::getLanguages(false);
		foreach ($languages as $language) {
			if (isset($language['lang_id'])) {
				$this->getConfigApi()->updateConfigurationValue('METHOD_NAME', $this->getPaymentMethodDisplayName(), $language['lang_id']);
			}
		}
		
		return true;
	}

	public function uninstallPaymentConfigurations(){
		$this->getConfigApi()->removeConfigurationValue('MESSAGE_AFTER_ORDER');
		
		$languages = Language::getLanguages(false);
		foreach ($languages as $language) {
			if (isset($language['lang_id'])) {
				$this->getConfigApi()->removeConfigurationValue('METHOD_NAME', $language['lang_id']);
				$this->getConfigApi()->removeConfigurationValue('METHOD_DESCRIPTION', $language['lang_id']);
			}
		}
		$this->getConfigApi()->removeConfigurationValue('MIN_TOTAL');
		$this->getConfigApi()->removeConfigurationValue('MAX_TOTAL');
		
		return true;
	}

	/**
	 * This method checks if for the current cart, the payment can be accepted by this
	 * payment method.
	 *
	 * @throws Exception In case it is not valid
	 * @return boolean
	 */
	public function validate(){
		self::loadClasses();
		$orderContext = $this->getOrderContext();
		$adapter = $this->getAuthorizationAdapter($orderContext);
		
		$paymentContext = UnzerCw_Util::getPaymentCustomerContext($this->context->cart->id_customer);
		try {
			$adapter->validate($orderContext, $paymentContext, array());
			UnzerCw_Util::persistPaymentCustomerContext($paymentContext);
			return NULL;
		}
		catch (Exception $e) {
			UnzerCw_Util::persistPaymentCustomerContext($paymentContext);
			return $e->getMessage();
		}
	}

	/**
	 * The main method for the configuration page.
	 *
	 * @return string html output
	 */
	public function getContent(){
		self::loadClasses();
		$this->context->controller->addCSS(_MODULE_DIR_ . 'unzercw/css/admin.css');
		
		$html = '<p><a class="button btn btn-default" href="?controller=adminmodules&configure=unzercw&module_name=unzercw&token=' .
				 Tools::getAdminTokenLite('AdminModules') . '">' . UnzerCw::translate('CONFIGURE_BASIC_SETTINGS') . '</a></p>';
		if (isset($_POST['submit_unzercw'])) {
			$fields = $this->getConfigApi()->convertFieldTypes($this->getFormFields());
			$this->getConfigApi()->processConfigurationSaveAction($fields);
			$this->displayConfirmation(UnzerCw::translate('Settings updated'));
		}
		$html .= $this->getConfigurationForm();
		
		return $html;
	}

	private function getConfigurationForm(){
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
						'title' => $this->paymentMethodDisplayName,
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

	private function getFormFieldsInner(){
		$fields = array(
			array(
				'name' => 'METHOD_NAME',
				'label' => UnzerCw::translate('METHOD_NAME_LABEL'),
				'desc' => UnzerCw::translate('METHOD_NAME_DESCRIPTION'),
				'type' => 'textarea',
				'lang' => 'true' 
			),
			array(
				'name' => 'METHOD_DESCRIPTION',
				'label' => UnzerCw::translate('METHOD_DESCRIPTION_LABEL'),
				'desc' => UnzerCw::translate('METHOD_DESCRIPTION_DESCRIPTION'),
				'type' => 'textarea',
				'lang' => 'true' 
			),
			array(
				'name' => 'MIN_TOTAL',
				'label' => UnzerCw::translate('MIN_TOTAL_LABEL'),
				'desc' => UnzerCw::translate('MIN_TOTAL_DESCRIPTION'),
				'type' => 'text' 
			),
			array(
				'name' => 'MAX_TOTAL',
				'label' => UnzerCw::translate('MAX_TOTAL_LABEL'),
				'desc' => UnzerCw::translate('MAX_TOTAL_DESCRIPTION'),
				'type' => 'text' 
			),
			array(
				'name' => 'MESSAGE_AFTER_ORDER',
				'label' => UnzerCw::translate('MESSAGE_AFTER_ORDER_LABEL'),
				'desc' => UnzerCw::translate('MESSAGE_AFTER_ORDER_DESCRIPTION'),
				'type' => 'textarea',
				'lang' => 'true' 
			) 
		);
		
		return $fields;
	}

	private function getPaymentMethodConfigurationValueInner($key, $languageCode = null){
		$langId = null;
		if ($languageCode !== null) {
			$languageCode = (string) $languageCode;
			$langId = UnzerCw_Util::getLanguageIdByIETFTag($languageCode);
		}
		
		return $this->getConfigApi()->getConfigurationValue($key, $langId);
	}

	public function existsPaymentMethodConfigurationValue($key, $languageCode = null){
		self::loadClasses();
		$langId = null;
		if ($languageCode !== null) {
			$languageCode = (string) $languageCode;
			$langId = UnzerCw_Util::getLanguageIdByIETFTag($languageCode);
		}
		
		return $this->getConfigApi()->hasConfigurationKey($key, $langId);
	}

	/**
	 *
	 * @return UnzerCw_OrderContext
	 */
	public function getOrderContext(){
		self::loadClasses();
		$cart = $this->context->cart;
		return new UnzerCw_OrderContext($cart, new UnzerCw_PaymentMethodWrapper($this));
	}

	public function getShopAdapter(){
		self::loadClasses();
		$adapter = UnzerCw_Util::getShopAdapterByPaymentAdapter(
				UnzerCw_Util::getAuthorizationAdapterByContext($this->getOrderContext()));
		if (!$adapter instanceof UnzerCw_Adapter_AbstractAdapter) {
			throw new Exception("Adapter must be instance of UnzerCw_Adapter_AbstractAdapter.");
		}
		return $adapter;
	}

	/**
	 *
	 * @return Customweb_Payment_Authorization_IAdapter
	 */
	public function getAuthorizationAdapter(Customweb_Payment_Authorization_IOrderContext $orderContext){
		self::loadClasses();
		return UnzerCw_Util::getAuthorizationAdapterByContext($orderContext);
	}

	public function l($string, $sprintf = null, $id_lang = null){
		return UnzerCw::translate($string, $sprintf);
	}

	public function setCart($cart){
		$this->context->cart = $cart;
	}

	/**
	 *
	 * @return UnzerCw_Entity_Transaction
	 */
	public function createTransaction(UnzerCw_OrderContext $orderContext, $aliasTransactionId = null, $failedTransactionObject = null){
		self::loadClasses();
		$adapter = UnzerCw_Util::getAuthorizationAdapterByContext($orderContext);
		if (!($adapter instanceof Customweb_Payment_Authorization_IAdapter)) {
			throw new Exception("The adapter has to implement Customweb_Payment_Authorization_IAdapter.");
		}
		
		return $this->createTransactionWithAdapter($orderContext, $adapter, $aliasTransactionId, $failedTransactionObject);
	}

	public function createTransactionWithAdapter(UnzerCw_OrderContext $orderContext, Customweb_Payment_Authorization_IAdapter $adapter, $aliasTransactionId, $failedTransactionObject){
		self::loadClasses();
		$transactionContext = $this->createTransactionContext($orderContext, $aliasTransactionId, $failedTransactionObject);
		$transactionObject = $adapter->createTransaction($transactionContext, $failedTransactionObject);
		
		$transaction = $transactionContext->getInternalTransaction();
		$transaction->setTransactionObject($transactionObject);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		
		return $transaction;
	}

	public function createTransactionContext(UnzerCw_OrderContext $orderContext, $aliasTransactionId, $failedTransactionObject){
		self::loadClasses();
		$mainModule = UnzerCw::getInstance();
		if ($mainModule->isCreationOfPendingOrderActive()) {
			return $this->createTransactionContextWithPendingOrder($orderContext, $aliasTransactionId, $failedTransactionObject);
		}
		else {
			return $this->createTransactionContextWithoutPendingOrder($orderContext, $aliasTransactionId, $failedTransactionObject);
		}
	}

	private function createTransactionContextWithPendingOrder(UnzerCw_OrderContext $orderContext, $aliasTransactionId, $failedTransactionObject){
		$originalCart = new Cart($orderContext->getCartId());
		
		$rs = $originalCart->duplicate();
		if (!isset($rs['success']) || !isset($rs['cart'])) {
			throw new Exception(
					"The cart duplication failed. May be some module prevents it. To fix this you may deactivate the creation of pending orders.");
		}
		$cart = $rs['cart'];
		if (!($cart instanceof Cart)) {
			throw new Exception("The duplicated cart is not of type 'Cart'.");
		}
		
		// Those values are not currently set when cloneing
		// 		$cart->id_address_delivery = $originalCart->id_address_delivery;
		// 		$cart->id_address_invoice = $originalCart->id_address_invoice;
		// 		$cart->getPackageList(true);
		// 		$cart->save();
		
		foreach ($originalCart->getCartRules() as $rule) {
			$ruleObject = $rule['obj'];
			//Because free gift cart rules adds a product to the order, the product is already in the duplicated order,
			//before we can add the cart rule to the new cart we have to remove the existing gift.
			if ((int) $ruleObject->gift_product) { //We use the same check as the shop, to get the gift product
				$cart->updateQty(1, $ruleObject->gift_product, $ruleObject->gift_product_attribute, false, 'down', 0, null, false);
			}
			$cart->addCartRule($ruleObject->id);
		}
		
		$collection = new PrestaShopCollection('Message');
		$collection->where('id_cart', '=', $originalCart->id);
		foreach($collection->getResults() as $message){
			$duplicateMessage = $message->duplicateObject();
			$duplicateMessage->id_cart = $cart->id;
			$duplicateMessage->save();
		}
		
		// Since we have duplicate the cart we have also to recreate the order context.
		$orderContext = new UnzerCw_OrderContext($cart, new UnzerCw_PaymentMethodWrapper($this));
		
		$pendingState = UnzerCw_OrderStatus::getPendingOrderStatusId();
		$customer = new Customer(intval($cart->id_customer));
		
		// Make sure that the notification can be processed, even if the payment
		// module is deactivated in this store.
		$this->active = true;
		
		$message = UnzerCw_Util::getOrderCreationMessage(UnzerCw_Util::getEmployeeIdFromCookie());
		
		UnzerCw::startRecordingMailMessages();
		$this->validateOrder((int) $cart->id, $pendingState, $orderContext->getOrderAmountInDecimals(), $this->getPaymentMethodDisplayName(), $message,
				$extra_vars = array(), $currency_special = null, $dont_touch_amount = false, $customer->secure_key);
		$orderId = $this->currentOrder;
		$messages = UnzerCw::stopRecordingMailMessages();
		
		$transaction = new UnzerCw_Entity_Transaction();
		$transaction->setOrderId($orderId)->setCustomerId($customer->id)->setModuleId($this->id)->setCartId($cart->id)->setMailMessages($messages)->setOriginalCartId(
				$originalCart->id);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		
		return $this->createTransactionContextInner($transaction, $orderContext, $aliasTransactionId);
	}

	private function createTransactionContextWithoutPendingOrder(UnzerCw_OrderContext $orderContext, $aliasTransactionId){
		$cart = new Cart($orderContext->getCartId());
		$transaction = new UnzerCw_Entity_Transaction();
		$transaction->setModuleId($this->id)->setCartId($cart->id);
		$transaction->setCustomerId($cart->id_customer);
		UnzerCw_Util::getEntityManager()->persist($transaction);
		
		return $this->createTransactionContextInner($transaction, $orderContext, $aliasTransactionId);
	}

	private function createTransactionContextInner(UnzerCw_Entity_Transaction $transaction, UnzerCw_OrderContext $orderContext, $aliasTransactionId){
		// Reset the checkout id.
		$key = UnzerCw_Util::getCheckoutCookieKey($this);
		$this->context->cookie->{$key} = null;
		return new UnzerCw_TransactionContext($transaction, $orderContext, $aliasTransactionId);
	}

	private function isPaymentMethodVisible(){
		if (!$this->active) {
			return false;
		}
		try{
			$orderContext = $this->getOrderContext();
		}
		catch(Exception $e){
			return false;
		}
		$adapter = $this->getAuthorizationAdapter($orderContext);
		$paymentContext = UnzerCw_Util::getPaymentCustomerContext($orderContext->getCustomerId());
		try {
			$adapter->preValidate($orderContext, $paymentContext);
			UnzerCw_Util::persistPaymentCustomerContext($paymentContext);
		}
		catch (Exception $e) {
			UnzerCw_Util::persistPaymentCustomerContext($paymentContext);
			return false;
		}
		
		// Check the minimal order total
		$minTotal = floatval($this->getConfigApi()->getConfigurationValue('MIN_TOTAL'));
		if (!empty($minTotal) && $minTotal > 0 && $minTotal > $this->context->cart->getOrderTotal(true, Cart::BOTH)) {
			return false;
		}
		
		// Check the maximal order total
		$maxTotal = floatval($this->getConfigApi()->getConfigurationValue('MAX_TOTAL'));
		if (!empty($maxTotal) && $maxTotal > 0 && $maxTotal < $this->context->cart->getOrderTotal(true, Cart::BOTH)) {
			return false;
		}
		
		return true;
	}
}

