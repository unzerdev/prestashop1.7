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

require_once 'Customweb/Mvc/Template/RenderContext.php';
require_once 'Customweb/Core/Util/Rand.php';
require_once 'Customweb/Payment/Authorization/OrderContext/Address/Default.php';
require_once 'Customweb/Core/Http/Response.php';
require_once 'Customweb/Core/Exception/CastException.php';
require_once 'Customweb/Payment/ExternalCheckout/AbstractCheckoutService.php';
require_once 'Customweb/Mvc/Template/SecurityPolicy.php';



/**
 *
 * @author Thomas Hunziker
 * @Bean
 */
class UnzerCw_ExternalCheckoutService extends Customweb_Payment_ExternalCheckout_AbstractCheckoutService {

	public function loadContext($contextId, $cache = true){
		return $this->getEntityManager()->fetch('UnzerCw_Entity_ExternalCheckoutContext', $contextId, $cache);
	}

	public function renderShippingMethodSelectionPane(Customweb_Payment_ExternalCheckout_IContext $context, $errorMessages){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$this->refreshContext($context);
		$this->getEntityManager()->persist($context);
		$cart = new Cart($context->getCartId());
		
		$this->registerSmartyFunctions();
		$templateContext = new Customweb_Mvc_Template_RenderContext();
		$templateContext->setSecurityPolicy(new Customweb_Mvc_Template_SecurityPolicy());
		$templateContext->setTemplate('checkout/carrier');
		$templateContext->addVariables($this->getVariablesForRenderingShippingMethodSelection($cart));
		$templateContext->addVariable('shippingMethodSelectionError', $errorMessages);
		return UnzerCw_Util::getTemplateRenderer()->render($templateContext);
	}

	private function registerSmartyFunctions(){
		$smarty = UnzerCw_Util::getTemplateSmartyContainer()->getSmartyInstance();
		smartyRegisterFunction($smarty, 'function', 'convertPrice', array(
			'Product',
			'convertPrice' 
		));
		smartyRegisterFunction($smarty, 'function', 'displayPrice', array(
			'Tools',
			'displayPriceSmarty' 
		));
	}

	protected function getVariablesForRenderingShippingMethodSelection(Cart $cart){
		$carriers = $cart->simulateCarriersOutput();
		$checked = $cart->simulateCarrierSelectedOutput();
		$delivery_option_list = $cart->getDeliveryOptionList(null, true);
		
		$cart->setDeliveryOption($cart->getDeliveryOption(null, false, false));
		
		// Wrapping fees
		$wrapping_fees = $cart->getGiftWrappingPrice(false);
		$wrapping_fees_tax_inc = $wrapping_fees = $cart->getGiftWrappingPrice();
		
		$variabels = array(
			'address_collection' => $cart->getAddressCollection(),
			'delivery_option_list' => $delivery_option_list,
			'carriers' => $carriers,
			'checked' => $checked,
			'virtual_cart' => $cart->isVirtualCart(),
			'delivery_option' => $cart->getDeliveryOption(null, false, false),
			'recyclablePackAllowed' => (int) (Configuration::get('PS_RECYCLABLE_PACK')),
			'giftAllowed' => (int) (Configuration::get('PS_GIFT_WRAPPING')),
			'total_wrapping_cost' => Tools::convertPrice($wrapping_fees_tax_inc, $cart->id_currency),
			'total_wrapping_tax_exc_cost' => Tools::convertPrice($wrapping_fees, $cart->id_currency),
			'cookie' => Context::getContext()->cookie,
			'use_taxes' => (int) Configuration::get('PS_TAX'),
			'priceDisplay' => Product::getTaxCalculationMethod((int) Context::getContext()->cookie->id_customer),
			'display_tax_label' => Context::getContext()->country->display_tax_label 
		);
		
		$vars = array(
			'HOOK_BEFORECARRIER' => Hook::exec('displayBeforeCarrier', 
					array(
						'carriers' => $carriers,
						'checked' => $checked,
						'delivery_option_list' => $delivery_option_list,
						'delivery_option' => $cart->getDeliveryOption(null, false, false) 
					)) 
		);
		Cart::addExtraCarriers($vars);
		
		return array_merge($variabels, $vars);
	}

	protected function validateDeliveryOption($delivery_option){
		if (!is_array($delivery_option))
			return false;
		
		foreach ($delivery_option as $option)
			if (!preg_match('/(\d+,)?\d+/', $option))
				return false;
		
		return true;
	}

	protected function updateShippingMethodOnContext(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$cart = new Cart($context->getCartId());
		$parameters = $request->getParameters();
		
		if (!empty($parameters['recyclable'])) {
			$cart->recyclable = (int) $parameters['recyclable'];
		}
		if (!empty($parameters['gift'])) {
			$cart->gift = (int) $parameters['gift'];
			if (!Validate::isMessage($parameters['gift_message'])) {
				throw new Exception('Invalid gift message.');
			}
			else {
				$cart->gift_message = strip_tags($parameters['gift_message']);
			}
		}
		
		if (isset($parameters['delivery_option'])) {
			if ($this->validateDeliveryOption($parameters['delivery_option'])) {
				$cart->setDeliveryOption($parameters['delivery_option']);
				$cart->getTotalShippingCost(); // ensure correct value is cached
			}
		}
		
		Hook::exec('actionCarrierProcess', array(
			'cart' => $cart 
		));
		
		if (!$cart->update()) {
			throw new Exception("Unable to store cart object.");
		}
		
		$context->setCarrierId($cart->id_carrier);
		
		// Carrier has changed, so we check if the cart rules still apply
		CartRule::autoRemoveFromCart(Context::getContext());
		CartRule::autoAddToCart(Context::getContext());
	}

	protected function extractShippingName(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		return $this->getShippingMethodNameFromContext($context);
	}

	private function getShippingMethodNameFromContext(UnzerCw_Entity_ExternalCheckoutContext $context){
		if ($context->getCarrierId() !== null) {
			$carrier = new Carrier($context->getCarrierId());
			return $carrier->name;
		}
		else {
			return null;
		}
	}

	protected function refreshContext(Customweb_Payment_ExternalCheckout_AbstractContext $context){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		$cart = new Cart($context->getCartId());
		
		if ($context->getShippingAddress() !== null) {
			$cart->id_address_delivery = $this->updateAddress($cart->id_address_delivery, $context->getShippingAddress());
		}
		
		if ($context->getBillingAddress() !== null) {
			$invoiceAddressId = $cart->id_address_invoice;
			
			// We need to make sure that the address id for invoice is different than the one
			// of the delivery. Otherwise we are unable to use different addresses.
			if (!empty($invoiceAddressId) && $invoiceAddressId == $cart->id_address_delivery) {
				$invoiceAddressId = null;
			}
			
			$cart->id_address_invoice = $this->updateAddress($invoiceAddressId, $context->getBillingAddress());
		}
		
		$options = $cart->getDeliveryOptionList(null, true);
		if (empty($options)) {
			$cart->setDeliveryOption();
			$context->setCarrierId(null);
		}
		else {
			$cart->setDeliveryOption($cart->getDeliveryOption(null, false, false));
		}
		if (!empty($cart->id_carrier)) {
			$context->setCarrierId($cart->id_carrier);
		}
		
		$context->setShippingMethodName($this->getShippingMethodNameFromContext($context));
		
		$cart->update();
		
		$context->updateFromCart($cart, $context->getPaymentMethod());
		$this->getEntityManager()->persist($context);
	}

	private function updateAddress($targetAddressId, Customweb_Payment_Authorization_OrderContext_IAddress $source){
		if (empty($targetAddressId)) {
			$address = new Address();
			$address->alias = 'default';
		}
		else {
			$address = new Address($targetAddressId);
		}
		
		$address->firstname = $source->getFirstName();
		$address->lastname = $source->getLastName();
		$address->address1 = $source->getStreet();
		$address->city = $source->getCity();
		$address->postcode = $source->getPostCode();
		$address->company = $source->getCompanyName();
		$address->address2 = '';
		
		$phone = $source->getPhoneNumber();
		$phone = preg_replace('/[^0-9. ()-+]/', '', $phone);
		$address->phone = $phone;
		
		$mobile = $source->getMobilePhoneNumber();
		$mobile = preg_replace('/[^0-9. ()-+]/', '', $mobile);
		$address->phone_mobile = $mobile;
		
		$code = $source->getCountryIsoCode();
		if (!empty($code)) {
			$address->id_country = Country::getByIso($source->getCountryIsoCode());
		}
		
		$state = $source->getState();
		if (!empty($state) && !empty($address->id_country)) {
			$address->id_state = State::getIdByIso($state, $address->id_country);
		}
		$address->save();
		
		return $address->id;
	}

	protected function updateUserSessionWithCurrentUser(Customweb_Payment_ExternalCheckout_AbstractContext $context){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		$sessionContext = Context::getContext();
		if ($sessionContext->cookie->logged) {
			return;
		}
		$email = $context->getCustomerEmailAddress();
		if (!empty($email) && $context->getBillingAddress() !== null) {
			$sessionContext = Context::getContext();
			$cart = new Cart($context->getCartId());
			if (!Customer::customerExists($context->getCustomerEmailAddress())) {
				$customer = new Customer();
				$customer->is_guest = 1;
				$customer->firstname = Tools::ucwords($context->getBillingAddress()->getFirstName());
				$customer->lastname = $context->getBillingAddress()->getLastName();
				$customer->email = $email;
				$customer->passwd = md5(Customweb_Core_Util_Rand::getRandomString(32));
				$result = $customer->add();
				if ($result == false) {
					throw new Exception("Unable to create the guest account. You may need to activate the guest checkout feature.");
				}
				$customer->addGroups(array(
					(int) Configuration::get('PS_CUSTOMER_GROUP') 
				));
			}
			else {
				$data = current(Customer::getCustomersByEmail($email));
				$customer = new Customer($data['id_customer']);
			}
			$customer->update();
			$cart->secure_key = $customer->secure_key;
			$cart->id_customer = $customer->id;
			$cart->id_address_delivery = (int) Address::getFirstCustomerAddressId((int) ($customer->id));
			$cart->id_address_invoice = (int) Address::getFirstCustomerAddressId((int) ($customer->id));
			$cart->update();
			$sessionContext->customer = $customer;
			$sessionContext->cookie->id_customer = (int) $customer->id;
			$sessionContext->cookie->customer_lastname = $customer->lastname;
			$sessionContext->cookie->customer_firstname = $customer->firstname;
			$sessionContext->cookie->passwd = $customer->passwd;
			$sessionContext->cookie->logged = 1;
			$customer->logged = 1;
			$sessionContext->cookie->email = $customer->email;
			$sessionContext->cookie->is_guest = $customer->is_guest;
		}
	}

	public function authenticate(Customweb_Payment_ExternalCheckout_IContext $context, $emailAddress, $successUrl){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		if ($context->getBillingAddress() === null) {
			$billingAddress = new Customweb_Payment_Authorization_OrderContext_Address_Default();
			$billingAddress->setFirstName('First')->setLastName('Last')->setCity('unknown')->setStreet('unknown 1')->setCountryIsoCode('DE')->setPostCode(
					'10000');
			$context->setBillingAddress($billingAddress);
		}
		
		$sessionContext = Context::getContext();
		if ($sessionContext->cookie->logged) {
			return Customweb_Core_Http_Response::redirect($successUrl);
		}
		
		$externalCheckout = UnzerCw::getInstance()->getConfigurationValue('external_checkout_account_creation');
		$display_guest_checkout = 1;
		if ($externalCheckout === 'skip_selection' && Configuration::get('PS_GUEST_CHECKOUT_ENABLED')) {
			$display_guest_checkout = 0;
			if (!empty($emailAddress) && !Customer::customerExists($emailAddress)) {
				$this->updateCustomerEmailAddress($context, $emailAddress);
				return Customweb_Core_Http_Response::redirect($successUrl);
			}
		}
		
		$context->setAuthenticationEmailAddress($emailAddress);
		$context->setAuthenticationSuccessUrl($successUrl);
		$this->getEntityManager()->persist($context);
		
		$link = new Link();
		$url = $link->getPageLink('authentication', true, null, 
				array(
					'unzercw-context-id' => $context->getContextId(),
					'token' => $context->getSecurityToken(),
					'display_guest_checkout' => $display_guest_checkout 
				));
		return Customweb_Core_Http_Response::redirect($url);
	}

	protected function createTransactionContextFromContext(Customweb_Payment_ExternalCheckout_IContext $context){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$paymentMethod = $context->getPaymentMethod();
		if (!($paymentMethod instanceof UnzerCw_IPaymentMethod)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_IPaymentMethod');
		}
		$orderContext = new UnzerCw_OrderContext(new Cart($context->getCartId()), 
				new UnzerCw_PaymentMethodWrapper($paymentMethod));
		return $paymentMethod->createTransactionContext($orderContext, null, null);
	}

	public function getPossiblePaymentMethods(Customweb_Payment_ExternalCheckout_IContext $context){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$paymentMethods = array();
		foreach (PaymentModule::getInstalledPaymentModules() as $module) {
			if (strpos($module['name'], 'unzercw_') === 0) {
				$paymentMethods[] = Module::getInstanceByName($module['name']);
			}
		}
		
		return $paymentMethods;
	}

	public function renderAdditionalFormElements(Customweb_Payment_ExternalCheckout_IContext $context, $errorMessage){
		$templateContext = new Customweb_Mvc_Template_RenderContext();
		$templateContext->setSecurityPolicy(new Customweb_Mvc_Template_SecurityPolicy());
		$templateContext->setTemplate('checkout/additional-form-fields');
		// 			$this->error = $errorMessage;
		$templateContext->addVariable('errorMessage', $errorMessage);
		
		$templateContext->addVariable('customerMessage', Tools::getValue('customerMessage', ''));
		return UnzerCw_Util::getTemplateRenderer()->render($templateContext);
	}

	public function processAdditionalFormElements(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$parameters = $request->getParameters();
		
		if (isset($parameters['customerMessage'])) {
			$this->updateMessage(strip_tags($parameters['customerMessage']), new Cart($context->getCartId()));
		}
	}

	public function renderReviewPane(Customweb_Payment_ExternalCheckout_IContext $context, $renderConfirmationFormElements, $errorMessage){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$this->refreshContext($context);
		$this->getEntityManager()->persist($context);
		
		$cart = new Cart($context->getCartId());
		
		$sessionContext = Context::getContext();
		$sessionContext->cookie->cart_total_amount = $cart->getOrderTotal();
		
		$sessionContext->smarty->escape_html = false;
		smartyRegisterFunction($sessionContext->smarty, 'function', 'displayPrice', array(
			'Tools',
			'displayPriceSmarty' 
		));
		$templateContext = new Customweb_Mvc_Template_RenderContext();
		$templateContext->setSecurityPolicy(new Customweb_Mvc_Template_SecurityPolicy());
		$templateContext->setTemplate('checkout/order-review');
		$templateContext->addVariables($this->getReviewPaneVariables($cart, $renderConfirmationFormElements));
		// 			$this->error = $errorMessage;
		$templateContext->addVariable('confirmationError', $errorMessage);
		$templateContext->addVariable('PS_STOCK_MANAGEMENT', Configuration::get('PS_STOCK_MANAGEMENT'));
		$templateContext->addVariable('show_taxes', (int) (Configuration::get('PS_TAX_DISPLAY') == 1 && (int) Configuration::get('PS_TAX')));
		$templateContext->addVariable('tpl_dir', _PS_THEME_DIR_);
		
		if ($context->getBillingAddress() != null) {
			$address = new Address($cart->id_address_invoice);
			$fields = AddressFormat::getOrderedAddressFields($address->id_country);
			$templateContext->addVariable('address_invoice', $address);
			$templateContext->addVariable('inv_adr_fields', $fields);
			$deliveryAddressFormatedValues = AddressFormat::getFormattedAddressFieldsValues($address, $fields);
			$templateContext->addVariable('invoiceAddressFormatedValues', $deliveryAddressFormatedValues);
		}
		
		if ($context->getShippingAddress() != null) {
			$address = new Address($cart->id_address_delivery);
			$fields = AddressFormat::getOrderedAddressFields($address->id_country);
			$templateContext->addVariable('address_delivery', $address);
			$templateContext->addVariable('dlv_adr_fields', $fields);
			$deliveryAddressFormatedValues = AddressFormat::getFormattedAddressFieldsValues($address, $fields);
			$templateContext->addVariable('deliveryAddressFormatedValues', $deliveryAddressFormatedValues);
		}
		
		return UnzerCw_Util::getTemplateRenderer()->render($templateContext);
	}

	public function validateReviewForm(Customweb_Payment_ExternalCheckout_IContext $context, Customweb_Core_Http_IRequest $request){
		if (!($context instanceof UnzerCw_Entity_ExternalCheckoutContext)) {
			throw new Customweb_Core_Exception_CastException('UnzerCw_Entity_ExternalCheckoutContext');
		}
		
		$cart = new Cart($context->getCartId());
		$sessionContext = Context::getContext();
		if ($sessionContext->cookie->cart_total_amount != $cart->getOrderTotal()) {
			throw new Exception(UnzerCw::translate("Cart content was modified."));
		}
		
		$parameters = $request->getParameters();
		if ((int) (Configuration::get('PS_CONDITIONS')) && (!isset($parameters['cgv']) || $parameters['cgv'] !== '1')) {
			throw new Exception(UnzerCw::translate("You need to accept the general terms and conditions."));
		}
	}

	private function updateMessage($messageContent, Cart $cart){
		if (!empty($messageContent)) {
			if (!Validate::isMessage($messageContent)) {
				throw new Exception(UnzerCw::translate('Invalid message'));
			}
			else if ($oldMessage = Message::getMessageByCartId((int) ($cart->id))) {
				$message = new Message((int) ($oldMessage['id_message']));
				$message->message = $messageContent;
				$message->update();
			}
			else {
				$message = new Message();
				$message->message = $messageContent;
				$message->id_cart = (int) ($cart->id);
				$message->id_customer = (int) ($cart->id_customer);
				$message->add();
			}
		}
		else {
			if ($oldMessage = Message::getMessageByCartId($cart->id)) {
				$message = new Message($oldMessage['id_message']);
				$message->delete();
			}
		}
	}

	protected function getReviewPaneVariables(Cart $cart, $renderGtc){
		$summary = $cart->getSummaryDetails();
		$customizedDatas = Product::getAllCustomizedDatas($cart->id);
		
		// override customization tax rate with real tax (tax rules)
		if ($customizedDatas) {
			foreach ($summary['products'] as &$productUpdate) {
				$productId = (int) (isset($productUpdate['id_product']) ? $productUpdate['id_product'] : $productUpdate['product_id']);
				$productAttributeId = (int) (isset($productUpdate['id_product_attribute']) ? $productUpdate['id_product_attribute'] : $productUpdate['product_attribute_id']);
				
				if (isset($customizedDatas[$productId][$productAttributeId])) {
					$productUpdate['tax_rate'] = Tax::getProductTaxRate($productId, $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
				}
			}
			Product::addCustomizationPrice($summary['products'], $customizedDatas);
		}
		
		$presented_cart = Context::getContext()->controller->cart_presenter->present($cart);
		$presented_cart['vouchers']['allowed'] = false;
				
		// Get available cart rules and unset the cart rules already in the cart
		$available_cart_rules = CartRule::getCustomerCartRules($cart->id_lang, (isset($cart->id_customer) ? $cart->id_customer : 0), true, true, true, 
				$cart);
		$cart_cart_rules = $cart->getCartRules();
		foreach ($available_cart_rules as $key => $available_cart_rule) {
			if (!$available_cart_rule['highlight'] || strpos($available_cart_rule['code'], 'BO_ORDER_') === 0) {
				unset($available_cart_rules[$key]);
				continue;
			}
			foreach ($cart_cart_rules as $cart_cart_rule)
				if ($available_cart_rule['id_cart_rule'] == $cart_cart_rule['id_cart_rule']) {
					unset($available_cart_rules[$key]);
					continue 2;
				}
		}
		
		$show_option_allow_separate_package = (!$cart->isAllProductsInStock(true) && Configuration::get('PS_SHIP_WHEN_AVAILABLE'));
		
		$currency = new Currency($cart->id_currency);
		
		$cms = new CMS(Configuration::get('PS_CONDITIONS_CMS_ID'), $cart->id_lang);
		$link_conditions = Context::getContext()->link->getCMSLink($cms, $cms->link_rewrite, (bool) Configuration::get('PS_SSL_ENABLED'));
		if (!strpos($link_conditions, '?'))
			$link_conditions .= '?content_only=1';
		else
			$link_conditions .= '&content_only=1';
		
		$vars = array(
			'token_cart' => Tools::getToken(false),
			'static_token' => Tools::getToken(false),
			'isLogged' => 0,
			'checkedTOS' => 0,
			'isVirtualCart' => $cart->isVirtualCart(),
			'productNumber' => $cart->nbProducts(),
			'voucherAllowed' => 0,
			'shippingCost' => $cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
			'shippingCostTaxExc' => $cart->getOrderTotal(false, Cart::ONLY_SHIPPING),
			'customizedDatas' => $customizedDatas,
			'CUSTOMIZE_FILE' => Product::CUSTOMIZE_FILE,
			'CUSTOMIZE_TEXTFIELD' => Product::CUSTOMIZE_TEXTFIELD,
			'lastProductAdded' => $cart->getLastProduct(),
			'displayVouchers' => $available_cart_rules,
			'currencySign' => $currency->sign,
			'currencyRate' => $currency->conversion_rate,
			'currencyFormat' => $currency->format,
			'currencyBlank' => $currency->blank,
			'show_option_allow_separate_package' => $show_option_allow_separate_package,
			'smallSize' => Image::getSize(ImageType::getFormattedName('small')),
			'cms_id' => (int) (Configuration::get('PS_CONDITIONS_CMS_ID')),
			'conditions' => (int) (Configuration::get('PS_CONDITIONS')) && $renderGtc,
			'urls' => Context::getContext()->controller->getTemplateVarUrls(),
			'link_conditions' => $link_conditions ,
			'cart' => $presented_cart
		);
		
		return array_merge($summary, $vars);
	}

	private function addCustomizedData(array $products, Cart $cart, $link, $imageRetriever){
		return array_map(
				function (array $product) use($cart){
					$product['customizations'] = array();
					
					$data = Product::getAllCustomizedDatas($cart->id, null, true, null, (int) $product['id_customization']);
					
					if (!$data) {
						$data = array();
					}
					$id_product = (int) $product['id_product'];
					$id_product_attribute = (int) $product['id_product_attribute'];
					if (array_key_exists($id_product, $data)) {
						if (array_key_exists($id_product_attribute, $data[$id_product])) {
							foreach ($data[$id_product] as $byAddress) {
								foreach ($byAddress as $customizations) {
									foreach ($customizations as $customization) {
										$presentedCustomization = array(
											'quantity' => $customization['quantity'],
											'fields' => array(),
											'id_customization' => null 
										);
										
										foreach ($customization['datas'] as $byType) {
											foreach ($byType as $data) {
												$field = array();
												switch ($data['type']) {
													case Product::CUSTOMIZE_FILE:
														$field['type'] = 'image';
														$field['image'] = $imageRetriever->getCustomizationImage($data['value']);
														break;
													case Product::CUSTOMIZE_TEXTFIELD:
														$field['type'] = 'text';
														$field['text'] = $data['value'];
														break;
													default:
														$field['type'] = null;
												}
												$field['label'] = $data['name'];
												$field['id_module'] = $data['id_module'];
												$presentedCustomization['id_customization'] = $data['id_customization'];
												$presentedCustomization['fields'][] = $field;
											}
										}
										
										$product['up_quantity_url'] = $link->getUpQuantityCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										$product['down_quantity_url'] = $link->getDownQuantityCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										$product['remove_from_cart_url'] = $link->getRemoveFromCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										$product['update_quantity_url'] = $link->getUpdateQuantityCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										
										$presentedCustomization['up_quantity_url'] = $link->getUpQuantityCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										
										$presentedCustomization['down_quantity_url'] = $link->getDownQuantityCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										
										$presentedCustomization['remove_from_cart_url'] = $link->getRemoveFromCartURL($product['id_product'], 
												$product['id_product_attribute'], $presentedCustomization['id_customization']);
										
										$presentedCustomization['update_quantity_url'] = $product['update_quantity_url'];
										
										$product['customizations'][] = $presentedCustomization;
									}
								}
							}
						}
					}
					
					usort($product['customizations'], 
							function (array $a, array $b){
								if ($a['quantity'] > $b['quantity'] || count($a['fields']) > count($b['fields']) ||
										 $a['id_customization'] > $b['id_customization']) {
									return -1;
								}
								else {
									return 1;
								}
							});
					
					return $product;
				}, $products);
	}

	private function presentProduct(array $rawProduct){
		$context = Context::getContext();
		$priceFormatter = new PriceFormatter();
		$link = $context->link;
		$translator = $context->getTranslator();
		$imageRetriever = new ImageRetriever($link);
		$taxConfiguration = new TaxConfiguration();
		$includeTaxes = $taxConfiguration->includeTaxes();
		$settings = new ProductPresentationSettings();
		
		$settings->catalog_mode = Configuration::isCatalogMode();
		$settings->include_taxes = $includeTaxes;
		$settings->allow_add_variant_to_cart_from_listing = (int) Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY');
		$settings->stock_management_enabled = Configuration::get('PS_STOCK_MANAGEMENT');
		
		if (isset($rawProduct['attributes']) && is_string($rawProduct['attributes'])) {
			// return an array of attributes
			$rawProduct['attributes'] = explode(',', $rawProduct['attributes']);
			$attributesArray = array();
			
			foreach ($rawProduct['attributes'] as $attribute) {
				list($key, $value) = explode(':', $attribute);
				$attributesArray[trim($key)] = ltrim($value);
			}
			
			$rawProduct['attributes'] = $attributesArray;
		}
		$rawProduct['remove_from_cart_url'] = $link->getRemoveFromCartURL($rawProduct['id_product'], $rawProduct['id_product_attribute']);
		
		$rawProduct['up_quantity_url'] = $link->getUpQuantityCartURL($rawProduct['id_product'], $rawProduct['id_product_attribute']);
		
		$rawProduct['down_quantity_url'] = $link->getDownQuantityCartURL($rawProduct['id_product'], $rawProduct['id_product_attribute']);
		
		$rawProduct['update_quantity_url'] = $link->getUpdateQuantityCartURL($rawProduct['id_product'], $rawProduct['id_product_attribute']);
		
		$rawProduct['ecotax_rate'] = '';
		$rawProduct['specific_prices'] = '';
		$rawProduct['customizable'] = '';
		$rawProduct['online_only'] = '';
		$rawProduct['reduction'] = '';
		$rawProduct['new'] = '';
		$rawProduct['condition'] = '';
		$rawProduct['pack'] = '';
		
		if ($includeTaxes) {
			$rawProduct['price_amount'] = $rawProduct['price_wt'];
			$rawProduct['price'] = $priceFormatter->format($rawProduct['price_wt']);
		}
		else {
			$rawProduct['price_amount'] = $rawProduct['price'];
			$rawProduct['price'] = $rawProduct['price_tax_exc'] = $priceFormatter->format($rawProduct['price']);
		}
		
		if ($rawProduct['price_amount'] && $rawProduct['unit_price_ratio'] > 0) {
			$rawProduct['unit_price'] = $rawProduct['price_amount'] / $rawProduct['unit_price_ratio'];
		}
		
		$rawProduct['total'] = $priceFormatter->format($includeTaxes ? $rawProduct['total_wt'] : $rawProduct['total']);
		
		$rawProduct['quantity_wanted'] = $rawProduct['cart_quantity'];
		
		$presenter = new ProductListingPresenter($imageRetriever, $link, $priceFormatter, new ProductColorsRetriever(), $translator);
		
		return $presenter->present($settings, $rawProduct, Context::getContext()->language);
	}
}