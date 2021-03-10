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
class UnzerCwPaymentModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent(){
		
		require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';

		
		if (Module::isInstalled('mailhook')) {
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
		}

		$variables = array();
		$this->addCSS($this->module->getPath() . 'css/style.css', 'all');
		$this->addJS(_MODULE_DIR_ . 'unzercw/js/frontend.js');

		$this->display_column_left = false;

		parent::initContent();
		$cart = $this->context->cart;

		/* @var $module UnzerCw_IPaymentMethod */
		$module = PaymentModule::getInstanceById(Tools::getValue('id_module', null));

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $m) {
			if ($m['name'] == $module->name) {
				$authorized = true;
				break;
			}
		}
		if (!$authorized) {
			die(Tools::displayError('This payment method is not available.'));
		}

		$variables['paymentMethodName'] = $module->getPaymentMethodDisplayName();

		$errorTransactionId = Tools::getValue('error_transaction_id', null);
		$errorTransaction = null;
		$errorDbTransaction = null;
		if ($errorTransactionId !== null) {
			$errorDbTransaction = UnzerCw_Entity_Transaction::loadById(intval($errorTransactionId));
			$errorTransaction = $errorDbTransaction->getTransactionObject();

			if ($errorTransaction->getTransactionContext()->getOrderContext()->getCustomerId() == $this->context->customer->id &&
					 $this->context->customer->id != 0) {
				$errorMessage = current($errorTransaction->getErrorMessages());
			}
			else {
				$errorMessage = current($errorTransaction->getErrorMessages());
				$errorDbTransaction = null;
			}
			$variables['error_message'] = $errorMessage;
		}

		try {
			$adapter = UnzerCw_Util::getShopAdapterByPaymentAdapter($module->getAuthorizationAdapter($module->getOrderContext()));

			$createTransaction = true;
			$renderOnloadJs = true;
			if (isset($_REQUEST['ajaxAliasForm']) || UnzerCw::getInstance()->isCreationOfPendingOrderActive()) {
				$createTransaction = false;
				$renderOnloadJs = false;
			}

			$adapter->prepareCheckout($module, $module->getOrderContext(), $errorDbTransaction, $createTransaction);
			$variables['paymentPane'] = $adapter->getCheckoutPageHtml($renderOnloadJs);
		}
		catch (Exception $e) {
			$variables['error_message'] = $e->getMessage();
		}

		$this->context->smarty->assign($variables);
		$this->setTemplate('payment_confirmation.tpl');
	}
}
