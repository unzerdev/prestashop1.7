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


require_once 'UnzerCw/OrderStatus.php';


class UnzerCw_OrderStatus {
	
	const LEGACY_SETTING_KEY = 'PENDING_ORDER_STATUS_ID';
	
	const SETTING_KEY = 'UNZERCW_PENDING_STATE';
	
	private static $pendingOrderState = null;
	
	public static function getPendingOrderStatusId() {
		$result = UnzerCw::getInstance()->getConfigurationValue(self::LEGACY_SETTING_KEY);
		if (!empty($result)) {
			return $result;
		}
		else {
			$result = Configuration::get(self::getSettingKey());
			if (!empty($result)) {
				return $result;
			}
			return self::createPendingOrderState();
		}
	}
	
	public static function setPendingOrderStatusId($id) {
		return Configuration::updateGlobalValue(self::getSettingKey(), $id);
	}
	
	/**
	 * @return OrderState
	 */
	public static function getPendingOrderStatus() {
		if (self::$pendingOrderState === null) {
			self::$pendingOrderState = new OrderState(self::getPendingOrderStatusId());
		}
		return self::$pendingOrderState;
	}
	
	public static function createPendingOrderState() {
		$pendingOrderState = new OrderState();
		$pendingOrderState->color = 'RoyalBlue';
		$pendingOrderState->deleted = 0;
		$pendingOrderState->delivery = 0;
		$pendingOrderState->hidden = 0;
		$pendingOrderState->invoice = 0;
		$pendingOrderState->logable = 0;
		$pendingOrderState->module_name = 'unzercw';
		
		foreach (Language::getLanguages() as $language) {
			$pendingOrderState->name[$language['id_lang']] = 'Awaiting Payment (Unzer)';
		}
		
		$pendingOrderState->paid = 0;
		$pendingOrderState->send_email = 0;
		$pendingOrderState->template = '';
		$pendingOrderState->unremovable = 1;
		$pendingOrderState->add();
		UnzerCw_OrderStatus::setPendingOrderStatusId($pendingOrderState->id);
		return $pendingOrderState->id;
	}
	
	
	private static function getSettingKey() {
		return substr(self::SETTING_KEY, 0, 32);
	}
	
}