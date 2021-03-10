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


require_once 'UnzerCw/FormRenderer.php';
require_once 'UnzerCw/Adapter/AbstractAdapter.php';



/**
 *
 * @author Thomas Hunziker
 * @Bean
 *
 */
class UnzerCw_Adapter_AjaxAdapter extends UnzerCw_Adapter_AbstractAdapter {
	private $visibleFormFields = array();
	private $ajaxScriptUrl = null;
	private $javaScriptCallbackFunction = null;

	public function getPaymentAdapterInterfaceName(){
		return 'Customweb_Payment_Authorization_Ajax_IAdapter';
	}

	/**
	 *
	 * @return Customweb_Payment_Authorization_Ajax_IAdapter
	 */
	public function getInterfaceAdapter(){
		return parent::getInterfaceAdapter();
	}

	protected function preparePaymentFormPane(){
		$this->visibleFormFields = $this->getInterfaceAdapter()->getVisibleFormFields($this->getOrderContext(), $this->getAliasTransactionObject(), 
				$this->getFailedTransactionObject(), $this->getPaymentCustomerContext());
		
		if ($this->getTransaction() !== null) {
			$this->ajaxScriptUrl = $this->getInterfaceAdapter()->getAjaxFileUrl($this->getTransaction()->getTransactionObject());
			$this->javaScriptCallbackFunction = $this->getInterfaceAdapter()->getJavaScriptCallbackFunction(
					$this->getTransaction()->getTransactionObject());
		}
		$this->persistTransaction();
	}

	protected function getTransactionAjaxResponseCallback(){
		$transactionObject = $this->getTransaction()->getTransactionObject();
		$ajaxScriptUrl = $this->getInterfaceAdapter()->getAjaxFileUrl($transactionObject);
		$callback = $this->getInterfaceAdapter()->getJavaScriptCallbackFunction($transactionObject);
		$this->persistTransaction();
		return 'function() { jQuery.getScript("' . $ajaxScriptUrl . '").done(function(){(' . $callback .
				 '(fields))}).fail(function(){alert("unable to load the AJAX remote script.")});}';
	}

	protected function getPaymentFormPane($renderOnLoadJS){
		$this->preparePaymentFormPane();
		$templateVars = $this->getBaseVariables();
		
		
		

		$templateVars['ajaxScriptUrl'] = $this->ajaxScriptUrl;
		$templateVars['ajaxSubmitCallback'] = $this->javaScriptCallbackFunction;
		$templateVars['sendFromDataBack'] = false;
		
		if ($this->getTransaction() === null) {
			$templateVars['ajaxPendingOrderSubmit'] = true;
		}
		
		$isAnyFieldMandatory = false;
		$visibleFormFields = $this->getVisibleFormFields();
		if ($visibleFormFields !== null && count($visibleFormFields) > 0) {
			$renderer = new UnzerCw_FormRenderer($this->paymentMethod->getPaymentMethodName());
			$renderer->setRenderOnLoadJs($renderOnLoadJS);
			$templateVars['visibleFormFields'] = $renderer->renderElements($visibleFormFields);
			foreach ($visibleFormFields as $field) {
				if ($field->isRequired() || $field->getControl()->isRequired()) {
					$isAnyFieldMandatory = true;
				}
			}
		}
		
		else {
			$templateVars['visibleFormFields'] = null;
		}
		$templateVars['isAnyFieldMandatory'] = $isAnyFieldMandatory;
		
		$templateVars['buttons'] = $this->getOrderConfirmationButton();
		
		if ($this->failedTransaction != null) {
			$errors = $this->failedTransaction->getTransactionObject()->getErrorMessages();
			$templateVars['error_message'] = end($errors);
// 			$this->error = end($errors);
		}
		
		$this->smarty->assign($templateVars);
		return $this->renderTemplate('form/pane.tpl');
	}

	protected function getVisibleFormFields(){
		return $this->visibleFormFields;
	}
}