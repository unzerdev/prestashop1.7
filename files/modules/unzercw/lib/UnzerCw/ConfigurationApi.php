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

require_once 'Customweb/Core/Stream/Input/File.php';
require_once 'Customweb/I18n/Translation.php';

require_once 'UnzerCw/Util.php';


class UnzerCw_ConfigurationApi {
	
	const ASSET_IDENTIFIER = 'asset://';
	const FILE_IDENTIFIER = 'file://';
	
	private $moduleId = null;
	
	public function __construct($moduleId) {
		$this->moduleId = $moduleId;
	}
	
	public function getConfigurationValue($key, $languageId = null) {
		
		//load setting
		$configKey = $this->getConfigurationKey($key, $languageId);
		$rs = Configuration::get($configKey);
		
		//check if legacy setting exists, load it
		$legacyKey = $this->getConfigurationKeyLegacy($key, $languageId);
		if(Configuration::hasKey($legacyKey)){
			$rs =  Configuration::get($legacyKey);
		}
		
		
		if (strpos($rs, self::ASSET_IDENTIFIER) === 0) {
			$asset = substr($rs, strlen(self::ASSET_IDENTIFIER));
			$rs = UnzerCw_Util::getAssetResolver()->resolveAssetStream($asset);
		}
		else if (strpos($rs, self::FILE_IDENTIFIER) === 0) {
			$file = _PS_UPLOAD_DIR_ . substr($rs, strlen(self::FILE_IDENTIFIER));
			if (file_exists($file)) {
				$rs = new Customweb_Core_Stream_Input_File($file);
			}
			else {
				$rs = null;
			}
		}
		
		return $rs;
	}
	
	public function hasConfigurationKey($key, $languageId = null) {
		
		//Check if setting stored with the legacy key
		if (Configuration::hasKey($this->getConfigurationKeyLegacy($key, $languageId))) {
			return true;
		}
		
		return Configuration::hasKey($this->getConfigurationKey($key, $languageId));
	}
	
	//Determine configuration key
	public function getConfigurationKey($key, $languageId = null) {
		$key = 'UNZ_' . $this->moduleId . '_' . $key;
		if ($languageId !== null) {
			$key .= '_' . $languageId;
		}
		if(strlen($key) > 32){
			$key = strtoupper($key);
			$prefix = $this->moduleId.'_'.substr($key, -10).'_';
			$key = $prefix.$raw = hash('crc32', $key).hash('adler32', $key) ;
			
		}
		
		return strtoupper($key);
	}
	
	//Old method to determine settings key, had issue with long key ending in the same chars
	private function getConfigurationKeyLegacy($key, $languageId = null) {
		$key = 'UNZERCW_' . $this->moduleId . '_' . $key;
		if ($languageId !== null) {
			$key .= '_' . $languageId;
		}
		return strtoupper(substr($key, max(strlen($key) - 32, 0), strlen($key)));
	}
	
	public function updateConfigurationValue($key, $value, $languageId = null) {
		if (is_array($value)) {
			$value = implode(',', $value);
		}
		//delete legacy setting
		Configuration::deleteByName($this->getConfigurationKeyLegacy($key, $languageId));
		
		return Configuration::updateValue($this->getConfigurationKey($key, $languageId), $value, true);
	}
	
	public function removeConfigurationValue($key, $languageId = null) {
		return Configuration::deleteByName($this->getConfigurationKey($key, $languageId));
	}
	
	public function processConfigurationSaveAction($fields) {
		foreach ($fields as $field) {
			$key = $field['name'];
			
			if ($field['type'] == 'select' && isset($field['multiple'])) {
				$key = trim($key, '[]');
			}
			
			if ($field['type'] == 'textarea' && isset($field['lang']) && $field['lang'] == 'true') {
				foreach ($_POST as $keyName => $value) {
					if (strpos($keyName, $key . "_") === 0) {
						$languageId = substr($keyName, strlen($key . "_"));
						$this->updateConfigurationValue($key, $value, $languageId);
					}
				}
			}
			else if ($field['type'] == 'file') {
				$resetKey = $key.'_RESET';
				if (isset($_POST[$resetKey]) && $_POST[$resetKey] == 'RESET') {
					$this->updateConfigurationValue($key, $field['default']);
				}
				else if (isset($_FILES[$key]) && !empty($_FILES[$key]['name'])) {
					$fileExtension = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
					$fileName = $this->getConfigurationKey($key) . '.' . $fileExtension;
					if (!is_writable(_PS_UPLOAD_DIR_)) {
						throw new Exception(Customweb_I18n_Translation::__("For uploading files the folder '@path' must be writable.", array('@path' => _PS_UPLOAD_DIR_)));
					}
					$rs = move_uploaded_file($_FILES[$key]['tmp_name'], _PS_UPLOAD_DIR_ . '/' . $fileName);
					if ($rs === false) {
						throw new Exception(Customweb_I18n_Translation::__("Failed to move file to upload direcotry."));
					}
					$value = self::FILE_IDENTIFIER . $fileName;
					$this->updateConfigurationValue($key, $value);
				}
			}
			else {
				if (isset($_POST[$key])) {
					$value = $_POST[$key];
					if (is_array($value)) {
						$value = implode(',', $value);
					}
					
					if (!empty($value) || $field['type'] != 'password') {
						$this->updateConfigurationValue($key, $value);
					}
				}
				else if ($field['type'] == 'select' && isset($field['multiple'])) {
					$this->updateConfigurationValue($key, '');
				}
			}
		}
	}
	
	public function convertFieldTypes($fields) {
		
		$orderStates = array();
		foreach (OrderState::getOrderStates(Context::getContext()->language->id) as $state) {
			$orderStates[] = array(
				'name' => $state['name'],
				'id' => $state['id_order_state'],
			);
		}
		
		$newFields = array();
		foreach ($fields as $fieldId => $field) {
			if ($field['type'] == 'orderstatus') {
				$field['type'] = 'select';
				$field['options'] = array(
					'query' => array_merge($field['order_status'], $orderStates),
					'name' => 'name',
					'id' => 'id',
				);
				$newFields[$fieldId] = $field;
			}
			else if ($field['type'] == 'select' && isset($field['multiple'])) {
				$newFields[$fieldId] = $field;
				$newFields[$fieldId]['name'] = $field['name'] . '[]';
			}
			else if ($field['type'] == 'file') {
				$value = $this->getConfigurationValue($field['name']);
				if ($value instanceof Customweb_Core_Stream_Input_File) {
					$field['desc'] .= '<br />' . UnzerCw::translate("Current: ") . $value->getFilePath();
				}
				$newFields[$fieldId] = $field;
				$newField = array(
					'type' => 'checkbox',
					'name' => $field['name'],
					'values' => array(
						'name' => 'name',
						'id' => 'id',
						'query' => array(
							'reset' => array(
								'val' => 'RESET',
								'name' => UnzerCw::translate("Reset '@label' to default", array('@label' => $field['label'])),
								'id' => 'RESET',
							),
						),
					),
				);
				$newFields[$fieldId . 'RESET'] = $newField;
			}
			else {
				$newFields[$fieldId] = $field;
			}
		}
		
		return $newFields;
	}
	
	public function getConfigurationValues($fields) {
		$values = array();
		$languages = Language::getLanguages(false);
		foreach ($fields as $field) {
			$key = $field['name'];

			if ($field['type'] == 'select' && isset($field['multiple'])) {
				$value = explode(',', $this->getConfigurationValue(trim($key, '[]')));
			}
			else if ($field['type'] == 'textarea' && isset($field['lang']) && $field['lang'] == 'true') {
				$value = array();
				foreach ($languages as $language) {
					$value[$language['id_lang']] = $this->getConfigurationValue($key, $language['id_lang']);
				}
			}
			else {
				$value = $this->getConfigurationValue($key);
			}
			$values[$key] = $value;
		}
		
		return $values;
	}
	
	public function l($string, $specific = false) {
		return UnzerCw::translate($string, $specific);
	}
	
	
}
