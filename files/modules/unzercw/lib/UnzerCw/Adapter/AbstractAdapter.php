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

require_once 'Customweb/Util/Url.php';
require_once 'Customweb/Util/Html.php';

require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/Adapter/IAdapter.php';
require_once 'UnzerCw/FormRenderer.php';

abstract class UnzerCw_Adapter_AbstractAdapter implements UnzerCw_Adapter_IAdapter {
	
	/**
	 *
	 * @var Customweb_Payment_Authorization_IAdapter
	 */
	private $interfaceAdapter;
	
	/**
	 *
	 * @var Customweb_Payment_Authorization_IOrderContext
	 */
	private $orderContext;
	
	/**
	 *
	 * @var UnzerCw_IPaymentMethod
	 */
	protected $paymentMethod;
	
	/**
	 *
	 * @var UnzerCw_Entity_Transaction
	 */
	protected $failedTransaction = null;
	
	/**
	 *
	 * @var UnzerCw_Entity_Transaction
	 */
	protected $aliasTransaction = null;
	
	/**
	 *
	 * @var int
	 */
	protected $aliasTransactionId = null;
	
	/**
	 *
	 * @var UnzerCw_Entity_Transaction
	 */
	private $transaction = null;
	
	/**
	 *
	 * @var string
	 */
	private $redirectUrl = null;
	protected $context = null;
	protected $smarty = null;
	private static $frontendJSOutputted = false;

	public function __construct(){
		// Load context and smarty
		$this->context = Context::getContext();
		if (is_object($this->context->smarty)) {
			$this->smarty = $this->context->smarty->createData($this->context->smarty);
			$this->smarty->escape_html = false;
		}
	}

	/**
	 * This method returns a AJAX response, when the transaction is created with
	 * an AJAX call.
	 *
	 * @throws Exception
	 * @return string JavaScript which is executed on.
	 */
	abstract protected function getTransactionAjaxResponseCallback();

	public function setInterfaceAdapter(Customweb_Payment_Authorization_IAdapter $interface){
		$this->interfaceAdapter = $interface;
	}

	public function getInterfaceAdapter(){
		return $this->interfaceAdapter;
	}
	
	
	public function isHeaderRedirectionSupported(){
		if (false) {
			return false;
		}
		
		if ($this->getRedirectionUrl() === null) {
			return false;
		}
		else {
			return true;
		}
	}
	
	protected function setRedirectUrl($redirectUrl){
		$this->redirectUrl = $redirectUrl;
		return $this;
	}

	public function getRedirectionUrl(){
		return $this->redirectUrl;
	}

	public function handleAliasTransaction(UnzerCw_IPaymentMethod $paymentMethod, Customweb_Payment_Authorization_IOrderContext $orderContext){
		$this->aliasTransaction = null;
		$this->aliasTransactionId = null;
		$this->paymentMethod = $paymentMethod;
		$this->orderContext = $orderContext;
		
		
	}

	public function prepareCheckout(UnzerCw_IPaymentMethod $paymentMethod, Customweb_Payment_Authorization_IOrderContext $orderContext, $failedTransaction, $createTransaction){
		if ($failedTransaction !== null & !($failedTransaction instanceof UnzerCw_Entity_Transaction)) {
			throw new Exception("The failed transaction is not of instance UnzerCw_Entity_Transaction.");
		}
		
		$this->paymentMethod = $paymentMethod;
		$this->failedTransaction = $failedTransaction;
		$this->orderContext = $orderContext;
		
		$this->transaction = null;
		
		$this->handleAliasTransaction($paymentMethod, $orderContext);
		
		if ($createTransaction === true) {
			$this->createNewTransaction();
		}
		
		$transaction = $this->getTransaction();
		$this->preparePaymentFormPane();
		if ($transaction !== null && $transaction->getTransactionObject()->isAuthorizationFailed()) {
			$this->setRedirectUrl(
					Customweb_Util_Url::appendParameters($transaction->getTransactionObject()->getTransactionContext()->getFailedUrl(), 
							$transaction->getTransactionObject()->getTransactionContext()->getCustomParameters()));
		}
	}

	public function processTransactionCreationAjaxCall(){
		try {
			$this->executeValidation();
			$transaction = $this->createNewTransaction();
			$js = $this->getTransactionAjaxResponseCallback();
			$rs = array(
				'status' => 'success',
				'callback' => $js 
			);
		}
		catch (Exception $e) {
			$rs = array(
				'status' => 'error',
				'message' => $e->getMessage() 
			);
		}
		$this->persistTransaction();
		return $rs;
	}

	protected function getPaymentCustomerContext(){
		return UnzerCw_Util::getPaymentCustomerContext($this->getOrderContext()->getCustomerId());
	}

	protected function executeValidation(){
		$this->getInterfaceAdapter()->validate($this->getOrderContext(), 
				UnzerCw_Util::getPaymentCustomerContext($this->getOrderContext()->getCustomerId()), $this->getFormData());
	}

	protected function getFormData(){
		return $_REQUEST;
	}

	public function getCheckoutPageHtml($renderOnLoadJS){
		return $this->getPaymentFormPane($renderOnLoadJS);
	}

	public function getCheckoutPageForm(){
		return $this->getCheckoutPageHtml(false);
	}
	
	
	protected function getOrderContext(){
		return $this->orderContext;
	}

	/**
	 *
	 * @return UnzerCw_Entity_Transaction
	 */
	protected final function createNewTransaction(){
		$orderContext = $this->getOrderContext();
		$this->transaction = $this->paymentMethod->createTransaction($this->getOrderContext(), $this->aliasTransactionId, 
				$this->getFailedTransactionObject());
		return $this->transaction;
	}

	/**
	 *
	 * @return UnzerCw_Entity_Transaction
	 */
	public function getTransaction(){
		return $this->transaction;
	}

	protected function getAliasTransactionObject(){
		$aliasTransactionObject = null;
		$orderContext = $this->getOrderContext();
		
		if ($this->aliasTransactionId === 'new') {
			$aliasTransactionObject = 'new';
		}
		
		if ($this->aliasTransaction !== null && $this->aliasTransaction->getCustomerId() !== null &&
				 $this->aliasTransaction->getCustomerId() == $orderContext->getCustomerId()) {
			$aliasTransactionObject = $this->aliasTransaction->getTransactionObject();
		}
		
		return $aliasTransactionObject;
	}

	protected function getFailedTransactionObject(){
		$failedTransactionObject = null;
		$orderContext = $this->getOrderContext();
		if ($this->failedTransaction !== null && $this->failedTransaction->getCustomerId() !== null &&
				 $this->failedTransaction->getCustomerId() == $orderContext->getCustomerId()) {
			$failedTransactionObject = $this->failedTransaction->getTransactionObject();
		}
		return $failedTransactionObject;
	}

	protected function getPaymentFormPaneVariables($renderOnLoadJS){
		$templateVars = $this->getBaseVariables();
		
		$actionUrl = $this->getFormActionUrl();
		if ($actionUrl !== null && !empty($actionUrl)) {
			$templateVars['formActionUrl'] = $actionUrl;
		}
		
		
		

		$visibleFormFields = $this->getVisibleFormFields();
		$isAnyFieldMandatory = false;
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
		
		$hiddenFormFields = $this->getHiddenFormFields();
		if ($hiddenFormFields !== null && count($hiddenFormFields) > 0) {
			$templateVars['hiddenFields'] = Customweb_Util_Html::buildHiddenInputFields($hiddenFormFields);
		}
		else {
			$templateVars['hiddenFields'] = null;
		}
		
		$templateVars['additionalOutput'] = $this->getAdditionalFormHtml();
		$templateVars['buttons'] = $this->getOrderConfirmationButton();
		$templateVars['error_message'] = null;
		
		if ($this->failedTransaction != null) {
			$errors = $this->failedTransaction->getTransactionObject()->getErrorMessages();
			$templateVars['error_message'] = end($errors);
		}
		
		return $templateVars;
	}

	protected function getPaymentFormPane($renderOnLoadJS){
		$this->smarty->assign($this->getPaymentFormPaneVariables($renderOnLoadJS));
		return $this->renderTemplate('form/pane.tpl');
	}

	protected function persistTransaction(){
		if ($this->getTransaction() !== null) {
			UnzerCw_Util::getEntityManager()->persist($this->getTransaction());
		}
	}
	
	
	protected function getBaseVariables(){
		$vars = array(
			'paymentMethodName' => $this->getPaymentMethod()->getPaymentMethodDisplayName(),
			'paymentMethodDescription' => $this->getPaymentMethod()->getPaymentMethodDescription(),
			'paymentLogo' => $this->getPaymentMethod()->getPaymentMethodLogo(),
			'paymentMachineName' => $this->getPaymentMethod()->getPaymentMethodName() 
		);
		
		if ($this->getTransaction() === null) {
			$vars['createTransaction'] = true;
			$link = new Link();
			$vars['ajaxUrl'] = $link->getModuleLink('unzercw', 'ajax', array(
				'id_module' => $this->getPaymentMethod()->id 
			), true);
			$vars['sendFromDataBack'] = true;
		}
		else {
			$vars['createTransaction'] = false;
		}
		
		if (false) {
			$vars['paymentMethodDescription'] = '<div style="border: 1px solid #ff0000; background: #ffcccc; font-weight: bold; display:block;">' .
					 UnzerCw::translate(
							'We experienced a problem with your sellxed payment extension. For more information, please visit the configuration page of the Unzer plugin.') .
					 '</div>';
		}
		
		// Make sure we output the frontend js only once. However we need to put it here to make sure,
		// it is loaded always. (Some one step checkouts do not load it otherwise)
		if ($this->getFrontendJsOutput() === false && Tools::getValue('ajax', 'false') == 'true') {
			$vars['jsFileUrl'] = Media::getJSPath(_MODULE_DIR_ . 'unzercw/js/frontend.js');
			$this->setFrontendJsOutput(true);
		}
		else {
			$vars['jsFileUrl'] = null;
		}
		
		return $vars;
	}
	
	private function setFrontendJsOutput($js){
		self::$frontendJSOutputted = $js;
	}

	private function getFrontendJsOutput(){
		return self::$frontendJSOutputted;
	}

	protected function getPaymentMethod(){
		return $this->paymentMethod;
	}

	protected function renderErrorMessage($message){
		$this->smarty->assign(array(
			'errorMessage' => $message 
		));
		return $this->renderTemplate('form/error.tpl');
	}

	protected function getAdditionalFormHtml(){
		return '';
	}

	/**
	 * Method to load some data before the payment pane is rendered.
	 */
	protected function preparePaymentFormPane(){}

	protected function getVisibleFormFields(){
		return array();
	}

	protected function getFormActionUrl(){
		return null;
	}

	protected function getHiddenFormFields(){
		return array();
	}

	protected function getOrderConfirmationButton(){
		return $this->renderTemplate('form/buttons.tpl');
	}

	protected function renderTemplate($template){
		$overloaded = false;
		$moduleName = 'unzercw';
		
		$templatePath = $this->getTemplatePath($template);
		$overloaded = false;
		if (strpos($templatePath, _PS_THEME_DIR_)) {
			$overloaded = true;
		}
		
		$this->smarty->assign(
				array(
					'module_dir' => __PS_BASE_URI__ . 'modules/' . $moduleName . '/',
					'module_template_dir' => ($overloaded ? _THEME_DIR_ : __PS_BASE_URI__) . 'modules/' . $moduleName . '/' 
				));
		$result = $this->context->smarty->createTemplate($this->getTemplatePath($template), null, null, $this->smarty)->fetch();
		
		return $result;
	}

	private function getTemplatePath($template){
		$moduleName = 'unzercw';
		$pathsToCheck = array(
			_PS_THEME_DIR_ . 'modules/' . $moduleName . '/' . $template,
			_PS_THEME_DIR_ . 'modules/' . $moduleName . '/views/templates/front/' . $template,
			_PS_MODULE_DIR_ . $moduleName . '/views/templates/front/' . $template 
		);
		
		foreach ($pathsToCheck as $path) {
			if (Tools::file_exists_cache($path)) {
				return $path;
			}
		}
		
		return null;
	}

	public function l($string, $specific = false){
		return UnzerCw::translate($string);
	}
}