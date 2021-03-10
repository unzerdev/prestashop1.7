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

require_once 'Customweb/Core/Util/Rand.php';
require_once 'Customweb/Util/Html.php';

require_once 'UnzerCw/Adapter/AbstractAdapter.php';


/**
 * @author Thomas Hunziker
 * @Bean
 *
 */
class UnzerCw_Adapter_HiddenAdapter extends UnzerCw_Adapter_AbstractAdapter {

	private $visibleFormFields = array();
	private $formActionUrl = null;
	private $hiddenFields = array();
	
	public function getPaymentAdapterInterfaceName() {
		return 'Customweb_Payment_Authorization_Hidden_IAdapter';
	}
	
	/**
	 * @return Customweb_Payment_Authorization_Hidden_IAdapter
	 */
	public function getInterfaceAdapter() {
		return parent::getInterfaceAdapter();
	}
	
	protected function preparePaymentFormPane() {
		$this->visibleFormFields = $this->getInterfaceAdapter()->getVisibleFormFields(
			$this->getOrderContext(),
			$this->getAliasTransactionObject(),
			$this->getFailedTransactionObject(),
			$this->getPaymentCustomerContext()
		);
		if ($this->getTransaction() !== null) {
			$this->formActionUrl = $this->getInterfaceAdapter()->getFormActionUrl($this->getTransaction()->getTransactionObject());
			$this->hiddenFields = $this->getInterfaceAdapter()->getHiddenFormFields($this->getTransaction()->getTransactionObject());
		}
		$this->persistTransaction();
	}

	protected function getTransactionAjaxResponseCallback() {
		$transactionObject = $this->getTransaction()->getTransactionObject();
		$hiddenFields = Customweb_Util_Html::buildHiddenInputFields($this->getInterfaceAdapter()->getHiddenFormFields($transactionObject));
		$formUrl = $this->getInterfaceAdapter()->getFormActionUrl($transactionObject);
		$id = Customweb_Core_Util_Rand::getUuid();
		$html = '<form action="' . $formUrl . '" id="' . $id .'" method="POST" accept-charset="UTF-8">' . $hiddenFields . '</form>';
		return 'function() {
				var html = "' . urlencode($html) . '";
				html = decodeURIComponent(html.replace(/\+/g, \' \'));
				jQuery("body").append(html);
				$("#' . $id . '").append(unzercwBuildHiddenFormFields(fields));
				$("#' . $id . '").submit();
		}';
	}
	
	protected function getBaseVariables() {
		$vars = parent::getBaseVariables();
		$vars['sendFromDataBack'] = false;
		$vars['formActionUrl'] = '#';
		return $vars;
	}
	
	protected function getVisibleFormFields() {
		return $this->visibleFormFields;
	}
	
	protected function getFormActionUrl() {
		return $this->formActionUrl;
	}
	
	protected function getHiddenFormFields() {
		return $this->hiddenFields;
	}
		
}