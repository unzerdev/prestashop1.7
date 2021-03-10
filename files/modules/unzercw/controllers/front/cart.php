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
class UnzerCwCartModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 * 
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
		
		// opc
		$errorTransactionId = Tools::getValue('cw_transaction_id', null);
		if(!empty($errorTransactionId)){
			$errorTransaction = UnzerCw_Entity_Transaction::loadById($errorTransactionId);
			if ($errorTransaction !== null && $errorTransaction->getTransactionObject() != null) {
				$errorMessages = $errorTransaction->getTransactionObject()->getErrorMessages();
				$this->redirectWithMessage(end($errorMessages), $errorTransaction->getModuleId());
				return;
			}
		}
		
		// external checkout
		$errorContextId = Tools::getValue('unzercw-context-id', null);
		if (!empty($errorContextId)) {
			$errorContext = UnzerCw_Util::getEntityManager()->fetch('UnzerCw_Entity_ExternalCheckoutContext', $errorContextId);
			/* @var $errorContext UnzerCw_Entity_ExternalCheckoutContext */
			$this->redirectWithMessage($errorContext->getFailedErrorMessage(), $errorContext->getModuleId());
			return;
		}
	}

	private function redirectWithMessage($message, $moduleId){
		$link = new Link();
		
		$url = $link->getPageLink('cart', true, NULL, array(
			'id_module' => $moduleId,
			'action' => 'show' 
		));
		
		$this->errors[] = (string)$message;
		
		$this->redirectWithNotifications($url);
	}
}
