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

class UnzerCwFormModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent(){
		
		
		
		
		parent::initContent();
		/* @var $module UnzerCw_IPaymentMethod */
		$module = PaymentModule::getInstanceById(Tools::getValue('id_module', null));
		
		// wrap content in div so jquery can query it.
		echo "<div id='unzercw-form-wrapper'>";
		echo $module->getPaymentPane();
		echo "</div>";
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
