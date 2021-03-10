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

require_once 'Customweb/Payment/ExternalCheckout/AbstractContext.php';

require_once 'UnzerCw/Util.php';



/**
 * @Entity(tableName = 'unzercw_external_checkout_contexts')
 * @Filter(name = 'loadContextByCartId', where = 'cartId = >cartId', orderBy = 'cartId')
 * @Filter(name = 'loadContextNotFinalByCartId', where = 'cartId = >cartId AND (state != "completed" AND state != "failed")', orderBy = 'updatedOn')
 */
class UnzerCw_Entity_ExternalCheckoutContext extends Customweb_Payment_ExternalCheckout_AbstractContext {
	private $moduleId = null;
	private $cartId = null;
	private $carrierId = null;

	/**
	 * Updates this context object with the cart object.
	 *
	 * <p>
	 * This method can be called when the context is already stored in the database.
	 *
	 * @param Cart $cart
	 * @return UnzerCw_Entity_ExternalCheckoutContext
	 */
	public function updateFromCart(Cart $cart, Customweb_Payment_Authorization_IPaymentMethod $paymentMethod = null){
		$id = $this->getContextId();
		if (empty($id)) {
			throw new Exception("Before the context can be updated with cart, the context must be stored in the database.");
		}
		
		$lang = Language::getLanguage($cart->id_lang);
		$this->setLanguageCode($lang['iso_code']);
		$this->setCartId($cart->id);
		$currency = Currency::getCurrency($cart->id_currency);
		$this->setCurrencyCode($currency['iso_code']);
		$link = new Link();
		
		$this->setCartUrl($link->getModuleLink('unzercw', 'cart', array(
			'unzercw-context-id' => $this->getContextId() 
		), true));
		
		$this->setDefaultCheckoutUrl($link->getPageLink('order', true, null, array(
			'step' => '1' 
		)));
		$this->setInvoiceItems(
				UnzerCw_Util::createLineItemsFromCart($cart, $cart->getOrderTotal(true, Cart::BOTH, null, null, false), $paymentMethod));
		
		return $this;
	}

	protected function loadPaymentMethodByMachineName($machineName){
		return UnzerCw::getInstanceByName('unzercw_' . $machineName);
	}

	/**
	 * @Column(type = 'integer')
	 *
	 * @return int
	 */
	public function getModuleId(){
		return $this->moduleId;
	}

	/**
	 *
	 * @param int $moduleId
	 * @return UnzerCw_Entity_Transaction
	 */
	public function setModuleId($moduleId){
		$this->moduleId = $moduleId;
		return $this;
	}

	/**
	 * @Column(type = 'integer')
	 *
	 * @return int
	 */
	public function getCartId(){
		return $this->cartId;
	}

	/**
	 *
	 * @param int $cartId
	 * @return UnzerCw_Entity_Transaction
	 */
	public function setCartId($cartId){
		$this->cartId = $cartId;
		return $this;
	}

	/**
	 *
	 * @param string $cartId
	 * @param boolean $loadFromCache
	 * @return UnzerCw_Entity_ExternalCheckoutContext[]
	 */
	public static function getContextsByCartId($cartId, $loadFromCache = true){
		return UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_ExternalCheckoutContext', 
				'loadContextByCartId', array(
					'>cartId' => $cartId 
				), $loadFromCache);
	}

	/**
	 *
	 * @param string $cartId
	 * @param boolean $loadFromCache
	 * @return UnzerCw_Entity_ExternalCheckoutContext[]
	 */
	public static function getReusableContextByCartId($cartId, $loadFromCache = true){
		$result = UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_ExternalCheckoutContext', 
				'loadContextNotFinalByCartId', array(
					'>cartId' => $cartId 
				), $loadFromCache);
		if (count($result) > 0) {
			return current($result);
		}
		else {
			return null;
		}
	}

	/**
	 * @Column(type = 'integer')
	 *
	 * @return int
	 */
	public function getCarrierId(){
		return $this->carrierId;
	}

	public function setCarrierId($carrierId){
		$this->carrierId = $carrierId;
		return $this;
	}
}
