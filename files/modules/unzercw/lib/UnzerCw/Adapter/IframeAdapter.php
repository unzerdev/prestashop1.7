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


require_once 'UnzerCw/Adapter/AbstractAdapter.php';


/**
 * @author Thomas Hunziker
 * @Bean
 *
 */
class UnzerCw_Adapter_IframeAdapter extends UnzerCw_Adapter_AbstractAdapter {
	
	private $visibleFormFields = array();
	private $formActionUrl = null;
	private $iframeHeight = 500;
	private $iframeUrl = null;
	private $errorMessage = '';
	
	public function getPaymentAdapterInterfaceName() {
		return 'Customweb_Payment_Authorization_Iframe_IAdapter';
	}
	
	/**
	 * @return Customweb_Payment_Authorization_Iframe_IAdapter
	 */
	public function getInterfaceAdapter() {
		return parent::getInterfaceAdapter();
	}
		
	public function prepareWithFormData(array $formData, UnzerCw_Entity_Transaction $transaction) {
		$this->iframeUrl = $this->getInterfaceAdapter()->getIframeUrl($transaction->getTransactionObject(), $formData);
		$this->iframeHeight = $this->getInterfaceAdapter()->getIframeHeight($transaction->getTransactionObject(), $formData);
		if ($transaction->getTransactionObject()->isAuthorizationFailed()) {
			$this->iframeUrl = null;
			$errorMessage = current($transaction->getTransactionObject()->getErrorMessages());
			/* @var $errorMessage Customweb_Payment_Authorization_IErrorMessage */
			if (is_object($errorMessage)) {
				$this->errorMessage = $errorMessage->getUserMessage();
			}
			else {
				$this->errorMessage = UnzerCw::translate("Failed to initialize transaction with an unknown error");
			}
		}
	}
	
	public function getIframe() {
		if ($this->iframeUrl !== null) {
			$this->smarty->assign(array(
				'iframeUrl' => $this->iframeUrl,
				'iframeHeight' => $this->iframeHeight,
			));
			return $this->renderTemplate('form/iframe.tpl');
		}
		else {
			return $this->renderErrorMessage($this->errorMessage);
		}
	}
	
	
	protected function getTransactionAjaxResponseCallback() {
		throw new Exception("For iframe authorization this method is not used. Hence it should never be called.");
	}
	
	protected function preparePaymentFormPane() {
		$this->visibleFormFields = $this->getInterfaceAdapter()->getVisibleFormFields(
				$this->getOrderContext(),
				$this->getAliasTransactionObject(),
				$this->getFailedTransactionObject(),
				$this->getPaymentCustomerContext()
				);
		$this->formActionUrl = $this->createFormUrl();
		$this->persistTransaction();
	}
	
	private function createFormUrl() {
		$link = new Link();
		
		$parameters = array(
			'id_module' => $this->paymentMethod->id,
		);
		if ($this->getTransaction() !== null) {
			$parameters['cw_transaction_id'] = $this->getTransaction()->getTransactionId();
		}
		if ($this->aliasTransactionId !== null) {
			$parameters['cw_alias_id'] = $this->aliasTransactionId;
		}
		return $link->getModuleLink('unzercw', 'iframe', $parameters, true);
	}
	
	protected function getBaseVariables() {
		$vars = parent::getBaseVariables();
		$vars['createTransaction'] = false;
		$vars['isServerAuthorization'] = true;
		return $vars;
	}
	
	protected function getVisibleFormFields() {
		return $this->visibleFormFields;
	}
	
	protected function getFormActionUrl() {
		return $this->formActionUrl;
	}
	
}