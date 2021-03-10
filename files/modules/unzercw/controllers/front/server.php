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

class UnzerCwServerModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent(){
		
		require_once 'Customweb/Core/Exception/CastException.php';
require_once 'Customweb/Payment/Authorization/Server/IAdapter.php';
require_once 'Customweb/Core/Http/Response.php';

		require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';

		
		if (Module::isInstalled('mailhook')) {
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessage.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageAttachment.php';
			require_once rtrim(_PS_MODULE_DIR_, '/') . '/mailhook/MailMessageEvent.php';
		}
		
		/* @var $module UnzerCw_IPaymentMethod */
		$module = PaymentModule::getInstanceById(Tools::getValue('id_module', null));
		
		try {
			$adapter = $module->getAuthorizationAdapter($module->getOrderContext());
			$customerContext = UnzerCw_Util::getPaymentCustomerContext($module->getOrderContext()->getCustomerId());
			$adapter->validate($module->getOrderContext(), $customerContext, $_REQUEST);
		}
		catch (Exception $e) {
			parent::initContent();
			$this->errors[] = $e->getMessage();
			$link = new Link();
			
			$url = $link->getPageLink('order', true, NULL);
			
			$this->redirectWithNotifications($url);
			return;
		}
		
		$transactionId = Tools::getValue('cw_transaction_id', null);
		$aliasTransactionId = null;
		$tempAliasId = Tools::getValue('cw_alias_id', null);
		if ($tempAliasId !== null) {
			if ($tempAliasId == 'new') {
				$aliasTransactionId = 'new';
			}
			else {
				$aliasTransaction = UnzerCw_Entity_Transaction::loadById($tempAliasId);
				if ($aliasTransaction !== null && $aliasTransaction->getTransactionObject() != null && $aliasTransaction->getTransactionObject()->getTransactionContext()->getOrderContext()->getCustomerId() ===
						 $module->getOrderContext()->getCustomerId()) {
					$aliasTransactionId = $tempAliasId;
				}
			}
		}
		
		if (empty($transactionId)) {
			$transaction = $module->createTransaction($module->getOrderContext(), $aliasTransactionId);
		}
		else {
			$transaction = UnzerCw_Entity_Transaction::loadById($transactionId);
		}
		
		$adapter = UnzerCw_Util::getAuthorizationAdapter($transaction->getAuthorizationType());
		
		if (!($adapter instanceof Customweb_Payment_Authorization_Server_IAdapter)) {
			throw new Customweb_Core_Exception_CastException('Customweb_Payment_Authorization_Server_IAdapter');
		}
		$transactionObject = $transaction->getTransactionObject();
		
		$response = $adapter->processAuthorization($transactionObject, $_REQUEST);
		UnzerCw_Util::getTransactionHandler()->persistTransactionObject($transactionObject);
		
		$response = new Customweb_Core_Http_Response($response);
		$response->send();
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
