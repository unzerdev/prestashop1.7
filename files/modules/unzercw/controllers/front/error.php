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
class UnzerCwErrorModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 * 
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent(){
		
		
		require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';

		UnzerCw_Util::redirectSetCookieIfRequired('error');
		
		if (Module::isInstalled('mailhook')) {
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
		}
		
		$errorTransactionId = Tools::getValue('cw_transaction_id', null);
		$errorTransaction = UnzerCw_Entity_Transaction::loadById($errorTransactionId);
		if ($errorTransaction !== null) {
			$link = new Link();

			$url = $link->getPageLink('order', true, NULL,
					array(
						'id_module' => $errorTransaction->getModuleId(),
						'error_transaction_id' => $errorTransactionId,
					));
			Tools::redirect($url);

		}
		else {
			die(Tools::displayError("Not all required parameters are passed back from the payment process."));
		}
	}
}
