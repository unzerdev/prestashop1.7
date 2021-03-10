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
class UnzerCwTimeoutModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent(){
		
		
		require_once 'UnzerCw/Entity/Transaction.php';

		
		if (Module::isInstalled('mailhook')) {
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
		}
		
		$link = new Link();

		
		require_once 'UnzerCw/Entity/Transaction.php';


		$url = $link->getPageLink('order', true, NULL);
		$this->errors[] = $this->getTimeoutErrorMessage();
		$this->redirectWithNotifications($url);
	}

	private function getTimeoutErrorMessage(){
		$error = $this->getTranslator()->trans(
				'It seems as your order was successful. However we do not get any feedback from the payment processor. Please contact us to find out what is going on with your order.',
				array(), 'Module.unzercw');

		$transactionId = Tools::getValue('cw_transaction_id');
		if ($transactionId !== null) {
			$transaction = UnzerCw_Entity_Transaction::loadById($transactionId);
			if ($transaction !== null) {
				$error .= sprintf($this->getTranslator()->trans(' Please mention the following transaction id: %s.', array(), 'Module.unzercw'),
						$transaction->getTransactionExternalId());
			}
		}

		return $error;
	}
}
