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

require_once 'Customweb/Payment/Authorization/IPaymentMethod.php';


/**
 * This interface serves as the connection between the single generated payment methods, and the library of the plugin.
 *
 * @author customweb GmbH
 */
interface UnzerCw_IPaymentMethod extends Customweb_Payment_Authorization_IPaymentMethod {

	public function hookPaymentOptions($params);

	public function hookPaymentReturn($params);

	public function hookDisplayHeader();

	public function getEmbeddedPaymentOption();

	public function getFormFields();

	public function getPaymentMethodLogo();

	/**
	 * Creates the HTML required for displaying the payment pane.
	 * 
	 * @return string
	 */
	public function getPaymentPane();

	public function getPaymentMethodConfigurationValue($key, $languageCode = null);

	/**
	 *
	 * @return UnzerCw_ConfigurationApi
	 */
	public function getConfigApi();

	public function getPaymentMethodName();

	public function getPaymentMethodDisplayName();

	public function getPaymentMethodDescription();

	/**
	 * This method checks if for the current cart, the payment can be accepted by this
	 * payment method.
	 *
	 * @throws Exception In case it is not valid
	 * @return boolean
	 */
	public function validate();

	/**
	 * The main method for the configuration page.
	 *
	 * @return string html output
	 */
	public function getContent();

	public function existsPaymentMethodConfigurationValue($key, $languageCode = null);

	/**
	 *
	 * @return UnzerCw_OrderContext
	 */
	public function getOrderContext();

	public function getShopAdapter();

	/**
	 *
	 * @return Customweb_Payment_Authorization_IAdapter
	 */
	public function getAuthorizationAdapter(Customweb_Payment_Authorization_IOrderContext $orderContext);

	public function l($string, $sprintf = false, $id_lang = null);

	public function setCart($cart);

	/**
	 *
	 * @return UnzerCw_Entity_Transaction
	 */
	public function createTransaction(UnzerCw_OrderContext $orderContext, $aliasTransactionId = null, $failedTransactionObject = null);

	public function createTransactionWithAdapter(UnzerCw_OrderContext $orderContext, Customweb_Payment_Authorization_IAdapter $adapter, $aliasTransactionId, $failedTransactionObject);

	public function createTransactionContext(UnzerCw_OrderContext $orderContext, $aliasTransactionId, $failedTransactionObject);

}

