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
class UnzerCwBreakOutModuleFrontController extends ModuleFrontController {
	public $ssl = true;


	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent(){
		require_once 'Customweb/Util/Url.php';

		require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';

		UnzerCw_Util::redirectSetCookieIfRequired('breakout');
		
		if (Module::isInstalled('mailhook')) {
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
		}
		
		$this->addCSS($this->module->getPath() . 'css/style.css', 'all');
		$this->display_column_left = false;
		$this->display_column_right = false;

		parent::initContent();

		/**
		 *
		 * @var Customweb_Payment_Authorization_PaymentPage_IAdapter
		 */
		$id_transaction = Tools::getValue('cw_transaction_id', NULL);
		if ($id_transaction === NULL) {
			die("The given transaction cannot be loaded.");
		}

		$dbTransaction = UnzerCw_Entity_Transaction::loadById($id_transaction);

		$redirectionUrl = '';
		if ($dbTransaction->getTransactionObject()->isAuthorizationFailed()) {
			$redirectionUrl = Customweb_Util_Url::appendParameters($dbTransaction->getTransactionObject()->getTransactionContext()->getFailedUrl(),
					$dbTransaction->getTransactionObject()->getTransactionContext()->getCustomParameters());
		}
		else {
			$redirectionUrl = Customweb_Util_Url::appendParameters($dbTransaction->getTransactionObject()->getTransactionContext()->getSuccessUrl(),
					$dbTransaction->getTransactionObject()->getTransactionContext()->getCustomParameters());
		}

		$this->context->smarty->assign(array(
			'url' => $redirectionUrl
		));
		$this->setTemplate('module:unzercw/views/templates/front/breakout.tpl');
	}
}
