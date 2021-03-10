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

require_once 'Customweb/Database/Driver/MySQL/Driver.php';
require_once 'Customweb/Payment/Authorization/DefaultInvoiceItem.php';
require_once 'Customweb/DependencyInjection/Container/Default.php';
require_once 'Customweb/Asset/Resolver/Composite.php';
require_once 'Customweb/Mvc/Template/Smarty/ContainerBean.php';
require_once 'Customweb/Util/Invoice.php';
require_once 'Customweb/Core/Http/ContextRequest.php';
require_once 'Customweb/Payment/Endpoint/Dispatcher.php';
require_once 'Customweb/Cache/Backend/Memory.php';
require_once 'Customweb/Asset/Resolver/Simple.php';
require_once 'Customweb/Database/Driver/PDO/Driver.php';
require_once 'Customweb/Payment/Authorization/DefaultPaymentCustomerContext.php';
require_once 'Customweb/Core/Util/Html.php';
require_once 'Customweb/Database/Driver/MySQLi/Driver.php';
require_once 'Customweb/DependencyInjection/Bean/Provider/Annotation.php';
require_once 'Customweb/DependencyInjection/Bean/Provider/Editable.php';
require_once 'Customweb/Storage/Backend/Database.php';
require_once 'Customweb/Core/Util/Class.php';
require_once 'Customweb/Payment/Authorization/IAdapterFactory.php';

require_once 'UnzerCw/EntityManager.php';
require_once 'UnzerCw/DatabaseLinkAccessor.php';
require_once 'UnzerCw/Adapter/IAdapter.php';
require_once 'UnzerCw/Entity/PaymentCustomerContext.php';

require_once 'Customweb/Cron/Annotation/Cron.php';

final class UnzerCw_Util {
	private static $driver;
	private static $entityManager;
	private static $container = null;
	private static $cache = null;
	private static $paymentCustomerContexts = array();
	private static $resolvers = array();

	private static $tagToLanguage = array();

	private function __construct(){}

	public static function getCheckoutCookieKey(Customweb_Payment_Authorization_IPaymentMethod $paymentMethod){
		return 'unzercw_checkout_id' . $paymentMethod->getPaymentMethodName();
	}
	
	public static function redirectSetCookieIfRequired($action) {
		if($_SERVER['REQUEST_METHOD'] !== 'GET') { // ensure is only called when required, and no loop (Location: => GET)
			Context::getContext()->cookie->disallowWriting();
			$link = new Link();
			header('Location: ' . $link->getModuleLink('unzercw', $action, array('cw_transaction_id' => Tools::getValue('cw_transaction_id')), true));
			die();
		}
	}
	
	public static function isAliasManagerActive(Customweb_Payment_Authorization_IOrderContext $orderContext){
		$paymentMethod = $orderContext->getPaymentMethod();
		if ($paymentMethod->existsPaymentMethodConfigurationValue('alias_manager') &&
				 strtolower($paymentMethod->getPaymentMethodConfigurationValue('alias_manager')) == 'active') {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 *
	 * @param int $customerId
	 * @return Customweb_Payment_Authorization_IPaymentCustomerContext
	 */
	public static function getPaymentCustomerContext($customerId){
		// Handle guest context. This context is not stored.
		if ($customerId === null || $customerId === 0) {
			if (!isset(self::$paymentCustomerContexts['guestContext'])) {
				self::$paymentCustomerContexts['guestContext'] = new Customweb_Payment_Authorization_DefaultPaymentCustomerContext(array());
			}

			return self::$paymentCustomerContexts['guestContext'];
		}

		if (!isset(self::$paymentCustomerContexts[$customerId])) {
			$entities = self::getEntityManager()->searchByFilterName('UnzerCw_Entity_PaymentCustomerContext', 'loadByCustomerId',
					array(
						'>customerId' => $customerId
					));
			if (count($entities) > 0) {
				self::$paymentCustomerContexts[$customerId] = current($entities);
			}
			else {
				$context = new UnzerCw_Entity_PaymentCustomerContext();
				$context->setCustomerId($customerId);
				self::$paymentCustomerContexts[$customerId] = $context;
			}
		}
		return self::$paymentCustomerContexts[$customerId];
	}

	public static function persistPaymentCustomerContext(Customweb_Payment_Authorization_IPaymentCustomerContext $context){
		if ($context instanceof UnzerCw_Entity_PaymentCustomerContext) {
			$storedContext = self::getEntityManager()->persist($context);
			self::$paymentCustomerContexts[$storedContext->getCustomerId()] = $storedContext;
		}
	}

	public static function getGenderId($gender){
		$gender = strtolower($gender);
		if ($gender == 'female') {
			return 2;
		}
		else if ($gender == 'male') {
			return 1;
		}
		else {
			return null;
		}
	}

	public static function getLanguageIdByIETFTag($tag){

		if(!isset(self::$tagToLanguage[$tag])){
			$result = Db::getInstance()->getValue(
					'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `language_code` = \'' . pSQL(strtolower($tag)) . '\'');
			if($result === false){
				$result = Db::getInstance()->getValue(
						'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `language_code` = \'' . pSQL(strtolower(str_replace('_', '-', $tag))) . '\'');
			}
			if($result === false){
				$result = Db::getInstance()->getValue('SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `iso_code` = \'' . pSQL(strtolower(substr($tag, 0, 2))) . '\'');
			}
			if($result === false){
				$result = Db::getInstance()->getValue('SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `iso_code` = \'' . pSQL(strtolower($tag)) . '\'');
			}
			if($result === false){
				return null;
			}
			self::$tagToLanguage[$tag] = $result;
		}

		return self::$tagToLanguage[$tag];
	}

	/**
	 *
	 * @return Customweb_DependencyInjection_Container_Default
	 */
	public static function createContainer(){
		if (self::$container === null) {
			$packages = array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		);
			$packages[] = 'UnzerCw';
			$packages[] = 'Customweb_Payment_Alias';
			$packages[] = 'Customweb_Payment_Update';
			$packages[] = 'Customweb_Payment_TransactionHandler';
			$packages[] = 'Customweb_Mvc_Template_Smarty_Renderer';
			$packages[] = 'UnzerCw_LayoutRenderer';
			$packages[] = 'UnzerCw_ExternalCheckoutService';
			$packages[] = 'UnzerCw_EndpointAdapter';
			$packages[] = 'Customweb_Payment_SettingHandler';

			$provider = new Customweb_DependencyInjection_Bean_Provider_Editable(new Customweb_DependencyInjection_Bean_Provider_Annotation($packages));

			$storageBackend = new Customweb_Storage_Backend_Database(self::getEntityManager(), self::getDriver(), 'UnzerCw_Entity_Storage');
			$provider->addObject(self::getEntityManager())->addObject(Customweb_Core_Http_ContextRequest::getInstance())->addObject($storageBackend)->addObject(
					self::getDriver())->addObject(self::getCacheBackend())->add('databaseTransactionClassName',
					'UnzerCw_Entity_Transaction');

			if(version_compare(_PS_VERSION_, '1.7') >= 0){
				$smarty = clone Context::getContext()->smarty;
				$smarty->escape_html = false;
				$templateRenderer = new Customweb_Mvc_Template_Smarty_ContainerBean($smarty);
				$provider->addObject($templateRenderer);
			}
			else{
				$templateRenderer = new Customweb_Mvc_Template_Smarty_ContainerBean(Context::getContext()->smarty);
				$provider->addObject($templateRenderer);
			}

			$provider->addObject(self::getAssetResolver());

			self::$container = new Customweb_DependencyInjection_Container_Default($provider);
		}

		return self::$container;
	}

	/**
	 *
	 * @return Customweb_Mvc_Template_Smarty_ContainerBean
	 */
	public static function getTemplateSmartyContainer(){
		self::createContainer();
		return self::$container->getBean('Customweb_Mvc_Template_Smarty_ContainerBean');
	}

	/**
	 *
	 * @return Customweb_Mvc_Template_IRenderer
	 */
	public static function getTemplateRenderer(){
		return self::createContainer()->getBean('Customweb_Mvc_Template_IRenderer');
	}

	/**
	 *
	 * @return Customweb_Storage_IBackend
	 */
	public static function getStorageBackend(){
		return self::createContainer()->getBean('Customweb_Storage_IBackend');
	}

	/**
	 *
	 * @return Customweb_Payment_Alias_Handler
	 */
	public static function getAliasHandler(){
		return self::createContainer()->getBean('Customweb_Payment_Alias_Handler');
	}

	public static function getEndpointDispatcher(){
		return new Customweb_Payment_Endpoint_Dispatcher(self::createContainer()->getBean('UnzerCw_EndpointAdapter'),
				self::createContainer(), array(
			0 => 'Customweb_Unzer',
 			1 => 'Customweb_Payment_Authorization',
 		));
	}

	/**
	 *
	 * @return Customweb_Payment_BackendOperation_Form_IAdapter
	 */
	public static function getBackendFormAdapter(){
		return self::createContainer()->getBean('Customweb_Payment_BackendOperation_Form_IAdapter');
	}

	/**
	 *
	 * @return Customweb_Database_Entity_Manager
	 */
	public static function getEntityManager(){
		if (self::$entityManager === null) {
			$cache = self::getCacheBackend();
			self::$entityManager = new UnzerCw_EntityManager(self::getDriver(), $cache);
		}
		return self::$entityManager;
	}

	/**
	 *
	 * @return Customweb_Payment_ITransactionHandler
	 */
	public static function getTransactionHandler(){
		$container = self::createContainer();
		return $container->getBean('Customweb_Payment_ITransactionHandler');
	}

	/**
	 *
	 * @return Customweb_Cache_Backend_Memory
	 */
	private static function getCacheBackend(){
		if (self::$cache === null) {
			self::$cache = new Customweb_Cache_Backend_Memory();
		}
		return self::$cache;
	}

	/**
	 *
	 * @return Customweb_Database_Driver_PDO_Driver
	 */
	public static function getDriver(){
		if (self::$driver === null) {
			$databaseInstance = Db::getInstance();
			$link = UnzerCw_DatabaseLinkAccessor::getUnzerCwLink($databaseInstance);

			if ($databaseInstance instanceof DbMySQLiCore) {
				self::$driver = new Customweb_Database_Driver_MySQLi_Driver($link);
			}
			else if ($databaseInstance instanceof MySQLCore) {
				self::$driver = new Customweb_Database_Driver_MySQL_Driver($link);
			}
			else if ($databaseInstance instanceof DbPDOCore) {
				self::$driver = new Customweb_Database_Driver_PDO_Driver($link);
			}
			else {
				throw new Exception("The database is not supported.");
			}
		}
		return self::$driver;
	}

	protected static function getAuthorizationAdapterFactory(){
		$container = self::createContainer();
		$factory = $container->getBean('Customweb_Payment_Authorization_IAdapterFactory');

		if (!($factory instanceof Customweb_Payment_Authorization_IAdapterFactory)) {
			throw new Exception("The payment api has to provide a class which implements 'Customweb_Payment_Authorization_IAdapterFactory' as a bean.");
		}

		return $factory;
	}

	public static function getAuthorizationAdapter($authorizationMethodName){
		return self::getAuthorizationAdapterFactory()->getAuthorizationAdapterByName($authorizationMethodName);
	}

	public static function getAuthorizationAdapterByContext(Customweb_Payment_Authorization_IOrderContext $orderContext){
		return self::getAuthorizationAdapterFactory()->getAuthorizationAdapterByContext($orderContext);
	}

	/**
	 *
	 * @param Customweb_Payment_Authorization_IAdapter $paymentAdapter
	 * @throws Exception
	 * @return UnzerCw_Adapter_IAdapter
	 */
	public static function getShopAdapterByPaymentAdapter(Customweb_Payment_Authorization_IAdapter $paymentAdapter){
		$reflection = new ReflectionClass($paymentAdapter);
		$adapters = self::createContainer()->getBeansByType('UnzerCw_Adapter_IAdapter');
		foreach ($adapters as $adapter) {
			if ($adapter instanceof UnzerCw_Adapter_IAdapter) {
				$inferfaceName = $adapter->getPaymentAdapterInterfaceName();
				try {
					Customweb_Core_Util_Class::loadLibraryClassByName($inferfaceName);
					if ($reflection->implementsInterface($inferfaceName)) {
						$adapter->setInterfaceAdapter($paymentAdapter);
						return $adapter;
					}
				}
				catch (Customweb_Core_Exception_ClassNotFoundException $e) {
					// Ignore
				}
			}
		}

		throw new Exception("Could not resolve to shop adapter.");
	}


	/**
	 *
	 * @return Customweb_Asset_IResolver
	 */
	public static function getAssetResolver(){
		$context = Context::getContext();
		$shopId = $context->shop->id;
		if (!isset(self::$resolvers[$shopId])) {
			$baseUrl = $context->shop->getBaseURL();
			if (self::isHttps()) {
				$baseUrl = str_replace("http://", "https://", $baseUrl);
			}

			$modulePath = dirname(dirname(dirname(__FILE__)));
			$moduleUrl = $baseUrl . 'modules/unzercw';

			$currentTemplatePath = _PS_ALL_THEMES_DIR_ . $context->shop->getTheme() . '/modules/unzercw';
			$currentTemplateUrl = $baseUrl . '/themes/' . $context->shop->getTheme() . '/modules/unzercw';

			self::$resolvers[$shopId] = new Customweb_Asset_Resolver_Composite(
					array(
						new Customweb_Asset_Resolver_Simple($currentTemplatePath . '/snippets/', $currentTemplateUrl . '/snippets/',
								array(
									'application/x-smarty'
								)),
						new Customweb_Asset_Resolver_Simple($currentTemplatePath . '/css/assets/', $currentTemplateUrl . '/css/assets/',
								array(
									'text/css'
								)),
						new Customweb_Asset_Resolver_Simple($currentTemplatePath . '/js/assets/', $currentTemplateUrl . '/js/assets/',
								array(
									'application/javascript'
								)),
						new Customweb_Asset_Resolver_Simple($currentTemplatePath . '/images/assets/', $currentTemplateUrl . '/images/assets/',
								array(
									'image/png',
									'image/jpg',
									'image/gif',
									'image/svg'
								)),
						new Customweb_Asset_Resolver_Simple($modulePath . '/views/templates/snippets/', $moduleUrl . '/views/templates/snippets/',
								array(
									'application/x-smarty'
								)),
						new Customweb_Asset_Resolver_Simple($modulePath . '/css/assets/', $moduleUrl . '/css/assets/', array(
							'text/css'
						)),
						new Customweb_Asset_Resolver_Simple($modulePath . '/js/assets/', $moduleUrl . '/js/assets/', array(
							'application/javascript'
						)),
						new Customweb_Asset_Resolver_Simple($modulePath . '/images/assets/', $moduleUrl . '/images/assets/',
								array(
									'image/png',
									'image/jpg',
									'image/gif',
									'image/svg'
								)),
						new Customweb_Asset_Resolver_Simple($modulePath . '/assets/', $moduleUrl . '/assets/')
					));
		}

		return self::$resolvers[$shopId];
	}

	private static function isHttps() {
		if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
			return true;
		}
		else if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * This method attaches the given transaction to the mail message.
	 * The method adds multiple mail template
	 * variables. Hence the mail template can be customized to fit specific needs.
	 *
	 * @param Customweb_Payment_Authorization_ITransaction $transaction
	 * @param MailMessage $message
	 */
	public static function attachTransactionToMailMessage(Customweb_Payment_Authorization_ITransaction $transaction, MailMessage $message){
		$variables = array_merge($message->getTemplateVariables(), self::extractMailVariables($transaction));
		$message->setTemplateVariables($variables);
	}

	public static function extractMailVariables(Customweb_Payment_Authorization_ITransaction $transaction){
		$variables = array();

		$variables['{unzercw_transaction_id}'] = $transaction->getTransactionId();
		$variables['{unzercw_payment_id}'] = $transaction->getPaymentId();

		$variables['transaction_id'] = $transaction->getPaymentId();

		foreach ($transaction->getTransactionLabels() as $labelKey => $label) {
			$variables['{unzercw_label_' . $labelKey . '}'] = $label['value'];
		}

		if ($transaction->getPaymentInformation() !== null) {
			$pre = "--><div>";
			$post = "</div><!--";
			$paymentInformation = $pre . $transaction->getPaymentInformation() . $post; // no conditionals possible in default mail templates, so var is commented per default.
			$variables['{unzercwpayment_information}'] = $paymentInformation;
			$variables['{unzercw_payment_information}'] = $paymentInformation;
			$variables['{unzercw_payment_information_txt}'] = Customweb_Core_Util_Html::toText($paymentInformation);
		}
		else {
			// set to empty, no conditionals possible in template
			$variables['{unzercwpayment_information}'] = $variables['{unzercw_payment_information}'] = $variables['{unzercw_payment_information_txt}'] = "";
		}

		return $variables;
	}

	public static function getOrderCreationMessage($id_employee){
		$message = null;
		if (isset($id_employee) && $id_employee > 0) {
			$employee = new Employee((int) $id_employee);
			$message = UnzerCw::translate('Manual order -- Employee:');
			$message .= ' ' . substr($employee->firstname, 0, 1) . '. ' . $employee->lastname;
		}

		return $message;
	}

	public static function getEmployeeIdFromCookie(){
		$cookie = Context::getContext()->cookie;
		if (isset($cookie->id_employee) && $cookie->id_employee > 0) {
			return (int) $cookie->id_employee;
		}

		return null;
	}

	/**
	 * Creates line items based on the given cart.
	 *
	 * @param Cart $cart
	 * @return Customweb_Payment_Authorization_IInvoiceItem[]
	 */
	public static function createLineItemsFromCart(Cart $cart, $orderTotal, Customweb_Payment_Authorization_IPaymentMethod $paymentMethod = null){
		$items = array();

		$summary = $cart->getSummaryDetails();
		foreach ($summary['products'] as $productItem) {
			$type = Customweb_Payment_Authorization_DefaultInvoiceItem::TYPE_PRODUCT;
			$sku = $productItem['reference'];
			if(empty($sku)){
				$sku = $productItem['name'];
			}
			$items[] = new Customweb_Payment_Authorization_DefaultInvoiceItem(strip_tags($sku), strip_tags($productItem['name']), $productItem['rate'],
					$productItem['total_wt'], $productItem['quantity'], $type);
		}

		// Add shipping costs
		$shippingCosts = floatval($summary['total_shipping']);
		$shippingCostExcl = floatval($summary['total_shipping_tax_exc']);
		if ($shippingCosts > 0) {
			if (isset($summary['carrier']) && $summary['carrier'] instanceof Carrier && isset($summary['delivery']) &&
					 $summary['delivery'] instanceof Address) {
				$taxRate = $summary['carrier']->getTaxesRate($summary['delivery']);
			}
			else {
				$taxRate = 0;
				if ($shippingCostExcl > 0) {
					$taxRate = ($shippingCosts - $shippingCostExcl) / $shippingCostExcl * 100;
				}
			}
			$items[] = new Customweb_Payment_Authorization_DefaultInvoiceItem('shipping', $summary['carrier']->name, $taxRate, $shippingCosts, 1,
					Customweb_Payment_Authorization_DefaultInvoiceItem::TYPE_SHIPPING);
		}

		// Add wrapping costs
		$wrappingCosts = floatval($summary['total_wrapping']);
		$wrappingCostExcl = floatval($summary['total_wrapping_tax_exc']);
		if ($wrappingCosts > 0) {
			$taxRate = 0;
			if ($wrappingCostExcl > 0) {
				$taxRate = ($wrappingCosts - $wrappingCostExcl) / $wrappingCostExcl * 100;
			}
			$items[] = new Customweb_Payment_Authorization_DefaultInvoiceItem('wrapping', UnzerCw::translate("Wrapping Fee"), $taxRate,
					$wrappingCosts, 1, Customweb_Payment_Authorization_DefaultInvoiceItem::TYPE_PRODUCT);
		}

		// Add discounts
		if (count($summary['discounts']) > 0) {
			foreach ($summary['discounts'] as $discount) {
				$taxRate = 0;
				if ($discount['value_tax_exc'] > 0) {
					$taxRate = ($discount['value_real'] - $discount['value_tax_exc']) / $discount['value_tax_exc'] * 100;
				}

				$items[] = new Customweb_Payment_Authorization_DefaultInvoiceItem('discount', $discount['description'], $taxRate,
						$discount['value_real'], 1, Customweb_Payment_Authorization_DefaultInvoiceItem::TYPE_DISCOUNT);
			}
		}

		// Add payment fees if BVK payment fee module is active.
		if (method_exists($cart, 'getFee') && $paymentMethod != null) {
			$cart->getFee('unzercw_' . $paymentMethod->getPaymentMethodName());
			$feeamount = $cart->feeamount;
			if ($feeamount != 0) {
				$items[] = new Customweb_Payment_Authorization_DefaultInvoiceItem('payment-fee', UnzerCw::translate("Payment Fee"), 0,
						$feeamount, 1, Customweb_Payment_Authorization_DefaultInvoiceItem::TYPE_FEE);
			}
		}

		$currency = Currency::getCurrency($cart->id_currency);
		return Customweb_Util_Invoice::cleanupLineItems($items, $orderTotal, $currency['iso_code']);
	}
}