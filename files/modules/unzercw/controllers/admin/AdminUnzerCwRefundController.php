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
class AdminUnzerCwRefundController extends AdminController
{
	public function __construct() {
		$this->className = 'AdminUnzerCwRefundController';
		parent::__construct();
		$this->context->smarty->addTemplateDir($this->getTemplatePath());
		$this->tpl_folder = 'unzercw_refund/';
		$this->bootstrap = true;
	}


	public function initContent()
	{
		$this->display = 'confirm';

		require_once 'UnzerCw/Entity/Transaction.php';
		$data = $_GET;
		$transaction = current(UnzerCw_Entity_Transaction::getTransactionsByOrderId((int)$data['id_order']));
		$id_order = $data['id_order'];

		$targetUrl = '?controller=AdminOrders&id_order=' . $id_order . '&vieworder&token=' . Tools::getAdminTokenLite('AdminOrders') . '&confirmed=true';
		$backUrl = '?controller=AdminOrders&id_order=' . $id_order . '&vieworder&token=' . Tools::getAdminTokenLite('AdminOrders');

		$vars = array();
		$vars['orderId'] = $id_order;
		$vars['refundAmount'] = UnzerCw::getRefundAmount($data);
		$vars['transaction'] = $transaction->getTransactionObject();
		$vars['targetUrl'] = $targetUrl;
		$vars['hiddenFields'] = $this->getHiddenFields($data);
		$vars['backUrl'] = $backUrl;

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