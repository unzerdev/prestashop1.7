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

require_once 'Customweb/Form/Control/MultiControl.php';
require_once 'Customweb/Form/Renderer.php';
require_once 'Customweb/Form/WideElement.php';



class UnzerCw_FormRenderer extends Customweb_Form_Renderer
{
	private $paymentMethodName;
	private $isRenderingWideElement = false;
	
	public function __construct($paymentMethodName) {
		$this->paymentMethodName = $paymentMethodName;
	}
	
	
	public function getElementCssClass() {
		return 'form-group clearfix';
	}
	
	public function getElementLabelCssClass() {
		return 'control-label col-sm-4';
	}
	
	public function getControlCssClass() {
		if($this->isRenderingWideElement) {
			return 'controls col-sm';
		}
		return 'controls col-sm-8';
	}
	
	public function getControlCss(Customweb_Form_Control_IControl $control) {
		return 'form-control ' . $control->getCssClass();
	}
	
	public function getDescriptionCssClass() {
		return 'help-block col-sm-8 col-sm-offset-4';
	}
	
	public function renderControl(Customweb_Form_Control_IControl $control) {
		if (!($control instanceof Customweb_Form_Control_MultiControl)) {
			$control->setCssClass($this->getControlCss($control));
		}
		return $control->render($this);
	}
	
	public function renderElementsJavaScript(array $elements, $jsFunctionPostfix = '') {
		$namespace = $this->getNamespacePrefix();
		$this->setNamespacePrefix('unzercw_' . $this->paymentMethodName . '_');
		$js = parent::renderElementsJavaScript($elements, $jsFunctionPostfix);
		$this->setNamespacePrefix($namespace);
		return $js;
	}
	
	public function renderElementPrefix(Customweb_Form_IElement $element){
		$this->isRenderingWideElement = $element instanceof Customweb_Form_WideElement;
		return parent::renderElementPrefix($element);
	}
	
	public function renderElementPostfix(Customweb_Form_IElement $element) {
		$this->isRenderingWideElement = false;
		return parent::renderElementPostfix($element);
	}
	
	protected function renderStopEventJavaScript()
	{
		return '		function stopEvent(e) {
			if ( e.stopPropagation ) { e.stopPropagation(); }
			e.cancelBubble = true;
			if ( e.preventDefault ) { e.preventDefault(); } else { e.returnValue = false; }
				
				jQuery(".unzercw-payment-form").find(".cwProcessPayment").each(function() {
						jQuery(this).show();
				});
				
			return false;
		}
	';
	}
	
}
