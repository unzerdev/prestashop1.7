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

require_once 'Customweb/Payment/Authorization/OrderContext/AbstractDeprecated.php';
require_once 'Customweb/Core/Util/Rand.php';
require_once 'Customweb/Date/DateTime.php';
require_once 'Customweb/Core/Util/Language.php';
require_once 'Customweb/Payment/Authorization/IOrderContext.php';
require_once 'Customweb/Core/Language.php';

require_once 'UnzerCw/Util.php';


class UnzerCw_OrderContext extends Customweb_Payment_Authorization_OrderContext_AbstractDeprecated implements Customweb_Payment_Authorization_IOrderContext
{
	private $cartId;
	private $customerId;
	private $carrierName;
	private $languageCode;
	private $customerEmailAddress;
	private $customerGenderType;
	private $customerBirthdate;
	private $customerFirstname;
	private $customerLastname;
	private $lineItems;
	
	private $billingAddress;
	private $shippingAddress;
	private $paymentMethod;
	private $orderTotal;
	private $currency;
	private $checkoutId = null;
	private $employeeId = null;
	
	public function __construct($cart, Customweb_Payment_Authorization_IPaymentMethod $paymentMethod) {
		if (!($cart instanceof Cart)) {
			throw new Exception("The given cart object is NULL or is not of instance 'Cart'.");
		}
		$this->paymentMethod = $paymentMethod;
		
		$customer = new Customer(intval($cart->id_customer));
		$gender = new Gender($customer->id_gender);
		
		$this->cartId = $cart->id;
		$this->customerId = $cart->id_customer;
		
		$summary = $cart->getSummaryDetails();
		$this->carrierName = $summary['carrier']->name;
		
		
		$lang = Language::getLanguage($cart->id_lang);
		$this->languageCode = $lang['iso_code'];
		$this->customerEmailAddress = $customer->email;
		$this->customerGenderType = $gender->type;
		$this->customerBirthdate = $customer->birthday;
		$this->customerFirstname = $customer->firstname;
		$this->customerLastname = $customer->lastname;
		
		
		
		$this->billingAddress = new Address(intval($cart->id_address_invoice));
		$this->shippingAddress = new Address(intval($cart->id_address_delivery));
		
		$this->orderTotal = $cart->getOrderTotal(true, Cart::BOTH, null, null, false);
		
		// Add payment fees if BVK payment fee module is active.
		if (method_exists($cart, 'getFee') && isset($cart->feeamount) && $cart->feeamount <= 0) {
			$cart->getFee('unzercw_' . $paymentMethod->getPaymentMethodName());
			$feeamount = $cart->feeamount;
			if ($feeamount != 0) {
				$this->orderTotal += $cart->feeamount;
			}
		}
		
		$this->lineItems = $this->buildLineItems($cart, $this->orderTotal);
		
		$currency = Currency::getCurrency($cart->id_currency);
		$this->currency = $currency['iso_code'];
		
		$key = UnzerCw_Util::getCheckoutCookieKey($paymentMethod);
		$cookie = Context::getContext()->cookie;
		if (!isset($cookie->{$key}) || $cookie->{$key} === null || $cookie->{$key} == '') {
			$cookie->{$key} = Customweb_Core_Util_Rand::getUuid();
		}
		$this->checkoutId = $cookie->{$key};
		$this->employeeId = UnzerCw_Util::getEmployeeIdFromCookie();
	}
	
	public function __wakeup() {
		if (isset($this->cart)) {
			$cart = $this->cart;
			$this->cartId = $cart->id;
			$this->customerId = $cart->id_customer;
			$summary = $cart->getSummaryDetails();
			$this->carrierName = $summary['carrier']->name;
			$this->lineItems = $this->buildLineItems($cart, $this->orderTotal);
			
			$lang = Language::getLanguage($cart->id_lang);
			$this->languageCode = $lang['iso_code'];
			unset($this->cart);
		}
		if (isset($this->customer)) {
			$customer = $this->customer;
			$gender = new Gender($customer->id_gender);
			$this->customerEmailAddress = $customer->email;
			$this->customerGenderType = $gender->type;
			$this->customerBirthdate = $customer->birthday;
			$this->customerFirstname = $customer->firstname;
			$this->customerLastname = $customer->lastname;
			unset($this->customer);
		}
	}
	
	private function buildLineItems(Cart $cart, $orderTotal) {
		return UnzerCw_Util::createLineItemsFromCart($cart, $orderTotal, $this->getPaymentMethod());
	}
	
	
	public function getEmployeeId() {
		return $this->employeeId;
	}
	
	public function getCartId() {
		return $this->cartId;
	}
	
	public function getCheckoutId() {
		return $this->checkoutId;
	}
	
	public function getCustomerId() {
		return $this->customerId;
	}
	
	public function isNewCustomer() {
		return 'unknown';
	}
	
	public function getCustomerRegistrationDate() {
		return null;
	}
	
	public function getOrderAmountInDecimals() {
		return $this->orderTotal;
	}
	
	public function getCurrencyCode() {
		return $this->currency;
	}
	
	public function getInvoiceItems() {
		return $this->lineItems;
	}
	
	public function getShippingMethod() {
		return $this->carrierName;
	}
	
	public function getPaymentMethod() {
		return $this->paymentMethod;
	}
	
	public function getLanguage() {
		try{
			//Check if it is valid lanugage code
			$ietf = Customweb_Core_Util_Language::getIetfCode($this->languageCode);
			return new Customweb_Core_Language($ietf);
		}
		catch(Exception $e){
			$ietf = Language::getLanguageCodeByIso($this->languageCode);
			if($ietf == false){
				throw $e;
			}
			return new Customweb_Core_Language($ietf);
		}
	}
	
	public function getCustomerEMailAddress() {
		return $this->customerEmailAddress;
	}
	
	public function getBillingEMailAddress() {
		return $this->customerEmailAddress;
	}
	
	public function getBillingGender() {
		if ($this->getBillingCompanyName() !== null) {
			return 'company';
		}
	
		if ($this->isCustomerEqualBilling()) {
			if ($this->customerGenderType == '0') {
				return 'male';
			}
			else if ($this->customerGenderType == '1') {
				return 'female';
			}
		}
	
		return null;
	}
	
	public function getBillingSalutation() {
		return null;
	}
	
	
	public function getBillingFirstName() {
		return $this->billingAddress->firstname;
	}
	
	public function getBillingLastName() {
		return $this->billingAddress->lastname;
	}
	
	public function getBillingStreet() {
		$addressLine2 = $this->billingAddress->address2;
		if (!empty($addressLine2)) {
			return $this->billingAddress->address1 . ' ' . $addressLine2;
		}
		else {
			return $this->billingAddress->address1;
		}
	}
	
	public function getBillingCity() {
		return $this->billingAddress->city;
	}
	
	public function getBillingPostCode() {
		return $this->billingAddress->postcode;
	}
	
	public function getBillingState() {
		if (isset($this->billingAddress->id_state) && !empty($this->billingAddress->id_state)) {
			$state = new State($this->billingAddress->id_state);
			$code = $state->iso_code;
			if (!empty($code)) {
				return $code;
			}
		}
		
		return null;
	}
	
	public function getBillingCountryIsoCode() {
		$country = new Country(intval($this->billingAddress->id_country));
		return $country->iso_code;
	}
	
	public function getBillingPhoneNumber() {
		return $this->billingAddress->phone;
	}
	
	public function getBillingMobilePhoneNumber() {
		return $this->billingAddress->phone_mobile;
	}
	
	public function getBillingDateOfBirth() {
		if ($this->isCustomerEqualBilling()) {
			$birthday = $this->customerBirthdate;
			if (!empty($birthday) && $birthday != '0000-00-00') {
				return new Customweb_Date_DateTime($birthday);
			}
		}
		return null;
	}
	
	public function getBillingCompanyName() {
		$company = $this->billingAddress->company;
		if (empty($company)) {
			return null;
		}
		else {
			return $company;
		}
	}
	
	public function getBillingCommercialRegisterNumber() {
		return null;
	}
	
	public function getBillingSalesTaxNumber() {
		return null;
	}
	
	public function getShippingEMailAddress() {
		return $this->getCustomerEMailAddress();
	}
	
	public function getShippingGender() {
		if ($this->getShippingCompanyName() !== null) {
			return 'company';
		}
		
		if ($this->isCustomerEqualShipping()) {
			if ($this->customerGenderType == '0') {
				return 'male';
			}
			else if ($this->customerGenderType == '1') {
				return 'female';
			}
		}
		
		return null;
	}
	
	public function getShippingSalutation() {
		return null;
	}
	
	public function getBillingSocialSecurityNumber() {
		return null;
	}
	
	public function getShippingFirstName() {
		return $this->shippingAddress->firstname;
	}
	
	public function getShippingLastName() {
		return $this->shippingAddress->lastname;
	}
	
	public function getShippingStreet() {
		$addressLine2 = $this->shippingAddress->address2;
		if (!empty($addressLine2)) {
			return $this->shippingAddress->address1 . ' ' . $addressLine2;
		}
		else {
			return $this->shippingAddress->address1;
		}
	}
	
	public function getShippingCity() {
		return $this->shippingAddress->city;
	}
	
	public function getShippingPostCode() {
		return $this->shippingAddress->postcode;
	}
	
	public function getShippingState() {
		if (isset($this->shippingAddress->id_state) && !empty($this->shippingAddress->id_state)) {
			$state = new State($this->shippingAddress->id_state);
			$code = $state->iso_code;
			if (!empty($code)) {
				return $code;
			}
		}
		
		return null;
	}
	
	public function getShippingCountryIsoCode() {
		$country = new Country(intval($this->shippingAddress->id_country));
		return $country->iso_code;
	}
	
	public function getShippingPhoneNumber() {
		return $this->shippingAddress->phone;
	}
	
	public function getShippingMobilePhoneNumber() {
		return $this->shippingAddress->phone_mobile;
	}
	
	public function getShippingDateOfBirth() {
		if ($this->isCustomerEqualShipping()) {
			$birthday = $this->customerBirthdate;
			if (!empty($birthday) && $birthday != '0000-00-00') {
				return new Customweb_Date_DateTime($birthday);
			}
		}
		
		return null;
	}
	
	public function getShippingCompanyName() {
		$company = $this->shippingAddress->company;
		if (empty($company)) {
			return null;
		}
		else {
			return $company;
		}
	}
	
	public function getShippingCommercialRegisterNumber() {
		return null;
	}
	
	public function getShippingSalesTaxNumber() {
		return null;
	}

	public function getShippingSocialSecurityNumber() {
		return null;
	}
	
	public function getOrderParameters() {
		return null;
	}
	
	private function isCustomerEqualBilling() {
		if ($this->customerFirstname == $this->billingAddress->firstname && $this->customerLastname == $this->billingAddress->lastname) {
			return true;
		}
		else {
			return false;
		}
	}
	
	private function isCustomerEqualShipping() {
		if ($this->customerFirstname == $this->shippingAddress->firstname && $this->customerLastname == $this->shippingAddress->lastname) {
			return true;
		}
		else {
			return false;
		}
	}
	
}