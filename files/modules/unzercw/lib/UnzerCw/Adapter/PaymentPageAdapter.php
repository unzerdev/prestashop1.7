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
class UnzerCw_Adapter_PaymentPageAdapter extends UnzerCw_Adapter_AbstractAdapter {
	
	private $visibleFormFields = array();
	private $formActionUrl = null;
	private $hiddenFields = array();
	
	public function getPaymentAdapterInterfaceName() {
		return 'Customweb_Payment_Authorization_PaymentPage_IAdapter';
	}
	
	/**
	 * @return Customweb_Payment_Authorization_PaymentPage_IAdapter
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
		$parameters = array('id_module' => $this->paymentMethod->id);
		$link = new Link();
		if ($this->visibleFormFields !== null && count($this->visibleFormFields) > 0) {
			if ($this->getTransaction() !== null) {
				$parameters['cw_transaction_id'] = $this->getTransaction()->getTransactionId();
			}
			$this->formActionUrl = $link->getModuleLink('unzercw', 'redirection', $parameters, true);
		}
		else {
			if ($this->getTransaction() !== null) {
				$this->formActionUrl = $this->getInterfaceAdapter()->getFormActionUrl($this->getTransaction()->getTransactionObject(), array());
				$this->hiddenFields = $this->getInterfaceAdapter()->getParameters($this->getTransaction()->getTransactionObject(), array());
				if ($this->getInterfaceAdapter()->isHeaderRedirectionSupported($this->getTransaction()->getTransactionObject(), array())) {
					$this->setRedirectUrl($this->getInterfaceAdapter()->getRedirectionUrl($this->getTransaction()->getTransactionObject(), array()));
				
				}
			}
			else {
				$this->formActionUrl = '#';
			}
		}
		$this->persistTransaction();
	}
	
	protected function getTransactionAjaxResponseCallback() {
		
		$formData = $this->getFormData();
		$transactionObject = $this->getTransaction()->getTransactionObject();
		if ($this->getInterfaceAdapter()->isHeaderRedirectionSupported($transactionObject, $formData)) {
			$url = $this->getInterfaceAdapter()->getRedirectionUrl($transactionObject, $formData);
			return 'function(){ window.location = "' . $url . '"; }';
		}
		else {
			$hiddenFields = Customweb_Util_Html::buildHiddenInputFields($this->getInterfaceAdapter()->getParameters($transactionObject, $formData));
			$formUrl = $this->getInterfaceAdapter()->getFormActionUrl($transactionObject, $formData);
			$id = Customweb_Core_Util_Rand::getUuid();
			$html = '<form action="' . $formUrl . '" id="' . $id .'" method="POST" accept-charset="UTF-8">' . $hiddenFields . '</form>';
			return 'function() { var html = "' . urlencode($html) . '"; html = decodeURIComponent(html.replace(/\+/g, \' \')); jQuery("body").append(html); $("#' . $id . '").submit();}';
		}
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