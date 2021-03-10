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

require_once 'Customweb/Payment/Entity/AbstractTransaction.php';
require_once 'Customweb/Payment/Authorization/ITransaction.php';
require_once 'Customweb/Core/Logger/Factory.php';

require_once 'UnzerCw/Util.php';


/**
 * 
 * @Entity(tableName = 'unzercw_transactions')
 * @Filter(name = 'loadByCartId', where = 'cartId = >cartId', orderBy = 'cartId')
 * @Filter(name = 'loadByCartOrOrder', where = '(cartId = >cartId or orderId = >orderId)', orderBy = 'orderId')
 * @Filter(name = 'loadByOriginalCartId', where = 'originalCartId = >cartId', orderBy = 'originalCartId')
 *
 */
class UnzerCw_Entity_Transaction extends Customweb_Payment_Entity_AbstractTransaction
{
	private $moduleId = null;
	private $cartId = null;
	private $mailMessages = null;
	private $originalCartId = null;
	
	public function onBeforeSave(Customweb_Database_Entity_IManager $entityManager) {
		if($this->isSkipOnSafeMethods()){
			return;
		}
		$transactionObject = $this->getTransactionObject();
		$orderId = $this->getOrderId();
		
		// In case a order is associated with this transaction and the authorization failed, we have to 
		// cancel the order to restock the products.
		if ($transactionObject !== null && $transactionObject instanceof Customweb_Payment_Authorization_ITransaction && $transactionObject->isAuthorizationFailed() && !empty($orderId)) {
			$this->forceTransactionFailing();
		}
		return parent::onBeforeSave($entityManager);
	}
	
	
	protected function updateOrderStatus(Customweb_Database_Entity_IManager $entityManager, $currentStatus, $orderStatusSettingKey) {
		$this->updateOrderStatusInner($currentStatus);
	}
	
	private function updateOrderStatusInner($currentStatus) {
		$orderId = $this->getOrderId();
		if ($currentStatus != 'none' && $currentStatus !== null && !empty($orderId)) {
			$order = new Order($orderId);
			if ($order->current_state != $currentStatus) {
				$order_history = new OrderHistory();
				$order_history->id_order = $this->getOrderId();
				$order_history->changeIdOrderState($currentStatus, $this->getOrderId());
				$order_history->addWithemail();
				
				// We reset the mail messages to reduce the memory consumption. We do not need them anymore
				// because we will never send them for failed transactions.
				$this->setMailMessages(array());
				
				// We use a temporary order. Since PrestaShop is not capable to restore vouchers, when the order
				// is cancelled, we need to restore the vouchers manually. 
				if (!empty($orderId) && $currentStatus == Configuration::get('PS_OS_CANCELED')) {
					$this->resetCartRules();
				}
				
			}
		}
	}
	
	private function resetCartRules() {
		// Re-increase the number of usages per cart rule.
		$cart = new Cart($this->getCartId());
		foreach ($cart->getCartRules() as $rule) {
			$rule['obj']->quantity = $rule['obj']->quantity + 1;
			$rule['obj']->save();
		}
		$order = new Order($this->getOrderId());
		
		// Fix the number of usages of the cart rule per customer
		foreach ($order->getCartRules() as $rule) {
			$order_cart_rule = new OrderCartRule($rule['id_order_cart_rule']);
			if (Validate::isLoadedObject($order_cart_rule) && $order_cart_rule->id_order == $order->id)
			{
				// Delete Order Cart Rule and update Order
				$order_cart_rule->delete();
				$order->update();
			}
		}
	}
	
	protected function authorize(Customweb_Database_Entity_IManager $entityManager) {
		
		$orderId = $this->getOrderId();
		if (empty($orderId)) {
			$cart = new Cart($this->getCartId());
			$transactionObject = $this->getTransactionObject();
			$paymentMethod = $this->getPaymentMethod();
			$stateId = $paymentMethod->getPaymentMethodConfigurationValue($transactionObject->getOrderStatusSettingKey()); // must be a logable order state for payment id to be set on prestashop payment object
			$customer = new Customer(intval($cart->id_customer));

			// Make sure that the notification can be processed, even if the payment
			// module is deactivated in this store.
			$this->active = true;

			$GLOBALS['unzercw_successful_transaction_object'] = $transactionObject;
			$employeeId = null;
			$orderContext = $transactionObject->getTransactionContext()->getOrderContext();
			if (method_exists($orderContext, 'getEmployeeId')) {
				$employeeId = $transactionObject->getTransactionContext()->getOrderContext()->getEmployeeId();
			}
			$message = UnzerCw_Util::getOrderCreationMessage($employeeId);
			//Set country on context
			$context = Context::getContext();
			$psCountryId = Country::getByIso($transactionObject->getTransactionContext()->getOrderContext()->getBillingAddress()->getCountryIsoCode());
			if($psCountryId){
				$context->country= new Country($psCountryId);
			}
			Customweb_Core_Logger_Factory::getLogger(get_class($this))->logInfo("Start creating the order during authorization for transaction ".$this->getTransactionId());
			$paymentMethod->validateOrder(
					(int)$cart->id,
					$stateId,
					$this->getAuthorizationAmount(),
					$paymentMethod->getPaymentMethodDisplayName(),
					$message,
					UnzerCw_Util::extractMailVariables($transactionObject),
					null,
					false,
					$customer->secure_key
			);
			Customweb_Core_Logger_Factory::getLogger(get_class($this))->logInfo("Finished creating the order during authorization for transaction ".$this->getTransactionId());
			$orderId = $paymentMethod->currentOrder;
			$this->setOrderId($orderId);
		}
		else {
			
			// To clean up the database and make sure no abadonned carts wrongly remaining 
			// in the database we remove the original cart, when we successfully authorize the
			// duplicated cart / order.
			$originalCartId = $this->getOriginalCartId();
			if (!empty($originalCartId)) {
				$cart = new Cart($originalCartId);
				$cart->delete();
			}
			
			$messages = $this->getMailMessages();
			if (count($messages) > 0 && method_exists('Mail', 'sendMailMessageWithoutHook')) {
				Customweb_Core_Logger_Factory::getLogger(get_class($this))->logInfo("Start sending email during authorization for transaction ".$this->getTransactionId());
				foreach ($messages as $message) {
					UnzerCw_Util::attachTransactionToMailMessage($this->getTransactionObject(), $message);
					Mail::sendMailMessageWithoutHook($message, false);
				}
				Customweb_Core_Logger_Factory::getLogger(get_class($this))->logInfo("Finished sending email during authorization for transaction ".$this->getTransactionId());
			}
			
			// We reset the mail messages to reduce the memory consumption, after we have send them we 
			// do not need them anymore.
			$this->setMailMessages(array());
		}
	}

	protected function generateExternalTransactionId(Customweb_Database_Entity_IManager $entityManager) {
		return $this->generateExternalTransactionIdAlwaysAppend($entityManager);
	}
	
	/**
	 * Forcing that the transaction fails.
	 * 
	 * @return void
	 */
	public function forceTransactionFailing() {
		// We do not force the transaction object to fail, because a late successful
		// transaction will lead to refuse the payment. On the other the order is created
		// even if there are no items in the stock. The status will be changed to 'out of stock'.
		$this->updateOrderStatusInner(Configuration::get('PS_OS_CANCELED'));
	}
	
	/**
	 * @return PaymentModule
	 */
	public function getPaymentMethod() {
		return Module::getInstanceById($this->getModuleId());
	}
	
	/**
	 * @Column(type = 'integer')
	 * @return int
	 */
	public function getModuleId(){
		return $this->moduleId;
	}
	
	/**
	 * @param int $moduleId
	 * @return UnzerCw_Entity_Transaction
	 */
	public function setModuleId($moduleId){
		$this->moduleId = $moduleId;
		return $this;
	}
	
	/**
	 * Returns the original cart id. We have to duplicate the cart
	 * when we create pending orders to make sure, when the customer
	 * press the back button in the browser that always a cart
	 * exists.
	 * 
	 * @Column(type = 'integer')
	 * @return int
	 */
	public function getOriginalCartId(){
		return $this->originalCartId;
	}
	
	public function setOriginalCartId($originalCartId){
		$this->originalCartId = $originalCartId;
		return $this;
	}
	
	/**
	 * @Column(type = 'integer')
	 * @return int
	 */
	public function getCartId(){
		return $this->cartId;
	}
	
	/**
	 * @param int $cartId
	 * @return UnzerCw_Entity_Transaction
	 */
	public function setCartId($cartId){
		$this->cartId = $cartId;
		return $this;
	}
	
	/**
	 * Returns true when a pending order is created. 
	 * 
	 * @return boolean
	 */
	public function isPendingOrderCreated() {
		$orderId = $this->getOrderId();
		if ($this->getAuthorizationStatus() == Customweb_Payment_Authorization_ITransaction::AUTHORIZATION_STATUS_PENDING && !empty($orderId)) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * @Column(type = 'object')
	 */
	public function getMailMessages(){
		return $this->mailMessages;
	}
	
	public function setMailMessages($mailMessages){
		if (empty($mailMessages)) {
			$this->mailMessages = array();
		}
		$this->mailMessages = $mailMessages;
		return $this;
	}
	
	
	public static function getGridQuery() {
		return 'SELECT * FROM ' . _DB_PREFIX_ . 'unzercw_transactions WHERE ${WHERE} ${ORDER_BY} ${LIMIT}';
	}
	
	/**
	 *
	 * @return UnzerCw_Entity_Transaction
	 */
	public static function loadById($id, $cache = true) {
		return UnzerCw_Util::getEntityManager()->fetch('UnzerCw_Entity_Transaction', $id, $cache);
	}

	/**
	 *
	 * @param string $orderId
	 * @param boolean $loadFromCache
	 * @return UnzerCw_Entity_Transaction[]
	 */
	public static function getTransactionsByOrderId($orderId, $loadFromCache = true) {
		return UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_Transaction', 'loadByOrderId', array('>orderId' => $orderId), $loadFromCache);
	}

	/**
	 *
	 * @param string $cartId
	 * @param boolean $loadFromCache
	 * @return UnzerCw_Entity_Transaction[]
	 */
	public static function getTransactionsByCartId($cartId, $loadFromCache = true) {
		return UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_Transaction', 'loadByCartId', array('>cartId' => $cartId), $loadFromCache);
	}

	/**
	 *
	 * @param string $cartId
	 * @param boolean $loadFromCache
	 * @return UnzerCw_Entity_Transaction[]
	 */
	public static function getTransactionsByOriginalCartId($cartId, $loadFromCache = true) {
		return UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_Transaction', 'loadByOriginalCartId', array('>cartId' => $cartId), $loadFromCache);
	}
	
	/**
	 *
	 * @param string $orderId
	 * @param boolean $loadFromCache
	 * @return UnzerCw_Entity_Transaction[]
	 */
	public static function getTransactionsByCartOrOrder($cartId, $orderId, $loadFromCache = true) {
		return UnzerCw_Util::getEntityManager()->searchByFilterName('UnzerCw_Entity_Transaction', 'loadByCartOrOrder', array('>cartId' => $cartId, '>orderId' => $orderId), $loadFromCache);
	}
	
	
}
