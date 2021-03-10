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

require_once 'Customweb/Payment/IConfigurationAdapter.php';

require_once 'UnzerCw/Util.php';


/**
 * @Bean
 */
class UnzerCw_ConfigurationAdapter implements Customweb_Payment_IConfigurationAdapter{

	public function getConfigurationValue($key, $languageCode = null) {

		$langId = null;
		if ($languageCode !== null) {
			$languageCode = (string)$languageCode;
			$langId = UnzerCw_Util::getLanguageIdByIETFTag($languageCode);
		}

		$multiSelectKeys = array(
		);
		$rs = $this->getMainModule()->getConfigurationValue($key, $langId);
		if (isset($multiSelectKeys[$key])) {
			if (empty($rs)) {
				return array();
			}
			else {
				return explode(',', $rs);
			}
		}
		else {
			return $rs;
		}
	}

	public function existsConfiguration($key, $language = null) {
		$langId = null;
		if ($language !== null) {
			$language = (string)$language;
			$langId = UnzerCw_Util::getLanguageIdByIETFTag($language);
		}

		return $this->getMainModule()->hasConfigurationKey($key, $langId);
	}

	public function getLanguages($currentStore = false) {
		$languages = array();
		foreach (Language::getLanguages() as $language) {
			$languages[$language['iso_code']] = $language['name'];
		}
		return $languages;
	}

	public function getStoreHierarchy() {
		// Default >> Shop Group >> Shop

		// When the multi shop feature is not active, we return null.
		if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') != '1') {
			return null;
		}

		$currentStoreId = null;
		if (!empty(Context::getContext()->cookie->shopContext)) {
			if (strpos(Context::getContext()->cookie->shopContext, 'g-') === 0) {
				$groupId = substr(Context::getContext()->cookie->shopContext, 2);
				$currentStoreGroup = new ShopGroup($groupId);
				$currentStoreGroupName = $currentStoreGroup->name;
				return array(
					'default' => UnzerCw::translate('Default'),
					'g-' . $currentStoreGroup->id => $currentStoreGroupName,
				);
			}
			else if (strpos(Context::getContext()->cookie->shopContext, 's-') === 0) {
				$currentStoreId = substr(Context::getContext()->cookie->shopContext, 2);
			}
		}
		else if(defined('_PS_ADMIN_DIR_')) {
			return array(
				'default' => UnzerCw::translate('Default'),
			);
		}

		if ($currentStoreId === null) {
			$currentStoreId = Context::getContext()->shop->id;
		}

		$currentStore = new Shop($currentStoreId);
		$currentStoreName = $currentStore->name;

		$currentStoreGroupId = $currentStore->id_shop_group;
		$currentStoreGroup = new ShopGroup($currentStoreGroupId);
		$currentStoreGroupName = $currentStoreGroup->name;
		return array(
			'default' => UnzerCw::translate('Default'),
			'g-' . $currentStoreGroupId => $currentStoreGroupName,
			's-' . $currentStoreId => $currentStoreName,
		);
	}

	public function useDefaultValue(Customweb_Form_IElement $element, array $formData) {
		$controlName = implode('_', $element->getControl()->getControlNameAsArray());
		return (isset($formData['default'][$controlName]) && $formData['default'][$controlName] == 'default');
	}

	public function getOrderStatus() {
		$orderStates = array();
		foreach (OrderState::getOrderStates(Context::getContext()->language->id) as $state) {
			$orderStates[$state['id_order_state']] = $state['name'];
		}
		return $orderState;
	}

	/**
	 * @return UnzerCw
	 */
	private function getMainModule() {
		return UnzerCw::getInstance();
	}
}