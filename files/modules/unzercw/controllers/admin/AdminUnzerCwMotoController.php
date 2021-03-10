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
$modulePath = rtrim(_PS_MODULE_DIR_, '/');
require_once $modulePath . '/unzercw/unzercw.php';
/**
 * This calls intercepts the storage process of the refund executed in the backend of PrestaShop.
 *
 * @author Thomas Hunziker
 *
 */
class AdminUnzerCwMotoController extends AdminController
{
	public function __construct() {
		$this->className = 'AdminUnzerCwMotoController';
		parent::__construct();
		$this->context->smarty->addTemplateDir($this->getTemplatePath());
		$this->tpl_folder = 'unzercw_moto/';
		$this->bootstrap = true;
	}


	public function initContent()
	{
		
		library_load_class_by_name('Customweb_Payment_Authorization_Moto_IAdapter');
		
		require_once 'Customweb/Util/Html.php';
require_once 'Customweb/Payment/Authorization/Moto/IAdapter.php';

		require_once 'UnzerCw/Util.php';
require_once 'UnzerCw/Entity/Transaction.php';
require_once 'UnzerCw/FormRenderer.php';

		
		$this->display = 'confirm';
		$vars = array();
		$this->addCSS(_MODULE_DIR_ . 'unzercw/css/admin.css');

		$failedTransaction = null;
		if (isset($_GET['cw_transaction_id'])) {
			
			$dbTransaction = UnzerCw_Entity_Transaction::loadById($_GET['cw_transaction_id']);
			if ($dbTransaction->getTransactionObject()->isAuthorized()) {
				$id_cart = Tools::getValue('id_cart', null);
				$id_order = $dbTransaction->getOrderId();
				$params = array();
				$params['id_order'] = $id_order;
				$params['vieworder'] = '1';
				header('Location: ' . UnzerCw::getAdminUrl('AdminOrders', $params));
				die();
			}
			else {
				$failedTransaction = $dbTransaction->getTransactionObject();
				$messages = $failedTransaction->getErrorMessages();
				$vars['error_message'] = current($messages);
			}
		}

		if ($failedTransaction !== NULL) {
			$moduleInstance = PaymentModule::getInstanceById($dbTransaction->getModuleId());
		}
		else {
			$paymentMethodName = $_GET['payment_module_name'];
			$moduleInstance = Module::getInstanceByName($paymentMethodName);
		}
		
		/* @var $moduleInstance UnzerCw_IPaymentMethod */


		$cart = new Cart(intval($_GET['id_cart']));
		Context::getContext()->currency = new Currency((int)$cart->id_currency);
		Context::getContext()->customer = new Customer((int)$cart->id_customer);
		Context::getContext()->cart = $cart;
		$moduleInstance->setCart($cart);

		$orderContext = $moduleInstance->getOrderContext();
		$adapter = UnzerCw_Util::getAuthorizationAdapter(Customweb_Payment_Authorization_Moto_IAdapter::AUTHORIZATION_METHOD_NAME);
		if (!($adapter instanceof Customweb_Payment_Authorization_Moto_IAdapter)) {
			throw new Exception("Adapter must be of type 'Customweb_Payment_Authorization_Moto_IAdapter'.");
		}
		$isSupported = $adapter->isAuthorizationMethodSupported($orderContext);

		$vars['isMotoSupported'] = $isSupported;
		$vars['paymentMethodName'] = $moduleInstance->getPaymentMethodDisplayName();

		// Handle the debit case
		if ($isSupported) {

			$transaction = $moduleInstance->createTransactionWithAdapter($orderContext, $adapter, null, $failedTransaction);
			
			$vars['form_target_url'] = $adapter->getFormActionUrl($transaction->getTransactionObject());
			$vars['hidden_fields'] = Customweb_Util_Html::buildHiddenInputFields($adapter->getParameters($transaction->getTransactionObject()));
			$visibleFormFields = $adapter->getVisibleFormFields($orderContext, null, $failedTransaction, $transaction->getTransactionObject()->getPaymentCustomerContext());
			if ($visibleFormFields !== null && count($visibleFormFields) > 0) {
				$renderer = new UnzerCw_FormRenderer($moduleInstance->getPaymentMethodName());
				$vars['visible_fields'] = $renderer->renderElements($visibleFormFields);
			}
		}
		
		UnzerCw_Util::getEntityManager()->persist($transaction);

		// Handle the normal
		$data = $_GET;
		$targetUrl = '?controller=AdminOrders&submitAddorder=1&token=' . Tools::getAdminTokenLite('AdminOrders') . '&confirmed=true';
		$vars['normalFinishUrl'] = $targetUrl;
		$vars['normalFinishHiddenFields'] = $this->getHiddenFields($data);

		$this->context->smarty->assign($vars);
		parent::initContent();
	}


	public function getTemplatePath()
	{
		return _PS_MODULE_DIR_ . 'unzercw/views/templates/back/';
	}


	private function getHiddenFields($data) {
		$out = '';

		unset($data['controller']);
		unset($data['token']);
		unset($data['id_order']);

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $key2 => $value2) {
					$out .= '<input type="hidden" name="' . $key .'[' . $key2 . ']" value="' . $value2 . '" /> ';
				}
			}
			else {
				$out .= '<input type="hidden" name="' . $key .'" value="' . $value . '" /> ';
			}
		}

		return $out;
	}
}