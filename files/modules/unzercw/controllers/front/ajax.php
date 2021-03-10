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

class UnzerCwAjaxModuleFrontController extends ModuleFrontController {
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
		
		parent::initContent();
		$cart = $this->context->cart;
		
		/* @var $module UnzerCw_IPaymentMethod */
		$module = PaymentModule::getInstanceById(Tools::getValue('id_module', null));
		
		$orderContext = $module->getOrderContext();
		$adapter = UnzerCw_Util::getShopAdapterByPaymentAdapter(UnzerCw_Util::getAuthorizationAdapterByContext($orderContext));
		
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
				$errorTransaction = null;
			}
			
			$this->context->smarty->assign(array(
				'error_message' => $errorMessage 
			));
		}
		
		$adapter->prepareCheckout($module, $orderContext, $errorTransaction, false);
		$rs = $adapter->processTransactionCreationAjaxCall();
		
		echo json_encode($rs);
		die();
	}

	protected function displayMaintenancePage(){
		// We want never to see here the maintenance page.
	}

	protected function displayRestrictedCountryPage(){
		// We do not want to restrict the content by any country.
	}

	protected function canonicalRedirection($canonical_url = ''){
		// We do not need any canonical redirect
	}
}
