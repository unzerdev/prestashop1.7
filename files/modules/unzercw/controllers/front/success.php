<?php
/**
  * You are allowed to use this API in your web application.
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

class UnzerCwSuccessModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	/**
	 * @see FrontController::initContent() 
	 */
	public function initContent()
	{
		require_once 'Customweb/Core/Util/System.php';

		require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';

		UnzerCw_Util::redirectSetCookieIfRequired('success');
		
		if (Module::isInstalled('mailhook')) {
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
		}

		$transactionId = Tools::getValue('cw_transaction_id', null);
		
		
		
		$dbTransaction = UnzerCw_Entity_Transaction::loadById(intval($transactionId));

		if ($dbTransaction->getTransactionId() === null) {
			die("No transaction found for the given id.");
		}

		$id_cart = $dbTransaction->getCartId();
		$cart = new Cart($id_cart);
		$customer = new Customer($cart->id_customer);
		$key = $customer->secure_key;

		$link = new Link();
		$successUrl = $link->getPageLink('order-confirmation', true, null, array(
			'id_cart' => $id_cart,
			'id_module' => $dbTransaction->getModuleId(),
			'key' => $key,
		));

		$timeoutUrl = $link->getModuleLink('unzercw', 'timeout', array('cw_transaction_id' => $dbTransaction->getTransactionId()), true);

		$failedUrl = $link->getModuleLink('unzercw', 'error', array(
			'cw_transaction_id' => $dbTransaction->getTransactionId(),
			'id_cart' => $id_cart,
			'key' => $key,
		), true);

		

		// We have to close the session here otherwise the transaction may not be updated by the notification
		// callback.
		$this->context->cookie->write();

		$start = time();
		$maxExecutionTime = Customweb_Core_Util_System::getMaxExecutionTime() - 5;

		if ($maxExecutionTime > 60) {
			$maxExecutionTime = 60;
		}

		while (true) {

			$dbTransaction = UnzerCw_Entity_Transaction::loadById(intval($transactionId), false);
			$id_order = Order::getOrderByCartId((int)$id_cart);
			$transactionObject = $dbTransaction->getTransactionObject();

			if ($transactionObject->isAuthorizationFailed()) {
				header('Location: ' . $failedUrl);
				die();
			}
			else if ($transactionObject->isAuthorized()) {
				// Make sure we delete the cart.
				unset($this->context->cookie->id_cart);
				header('Location: ' . $successUrl);
				die();
			}

			if (time() - $start > $maxExecutionTime) {
				header('Location: ' . $timeoutUrl);
				die();
			}
			else {
				// Wait 2 seconds for the next try.
				sleep(2);
			}
		}
	}
}
