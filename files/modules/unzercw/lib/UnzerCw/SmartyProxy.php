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




class UnzerCw_SmartyProxy extends Smarty_Internal_Data {
	
	/**
	 * @var stdClass
	 */
	private $___object = null;
	
	public function __construct($object) {
		$this->___object = $object;
	}

	public function __isset($name) {
		if (isset($this->___object->{$name})) {
			return true;
		}
		else {
			return false;
		}
	}
	
	public function assign($tpl_var, $value = null, $nocache = false) {
		if (is_array($tpl_var) && isset($tpl_var['modules'])) {
			$modules = $tpl_var['modules'];
			if(is_array($modules)){
				foreach ($modules as $module) {
					if(isset($GLOBALS['cwrmUnTrustedMs']) && is_array($GLOBALS['cwrmUnTrustedMs'])){
						foreach ($GLOBALS['cwrmUnTrustedMs'] as $moduleName) {
							if (strpos($module->name, $moduleName) === 0 || $module->name == 'mailhook') {
								$module->trusted = 3;
							}
						}
					}
				}
			}
		}
		return $this->___object->assign($tpl_var, $value, $nocache);
	}
	
	public function __unset($name) {
		unset($this->___object->{$name});
	}
	
	public function __set($name, $value) {
		$this->___object->{$name} = $value;
	}
	
	public function __get($name) {
		return $this->___object->{$name};
	}
	
	public function __call($method, $args) {
		return call_user_func_array(array($this->___object, $method), $args);
	}

	public function __wakeup() {
		return $this->___object->__wakeup();
	}

	public function __sleep() {
		return $this->___object->__sleep();
	}
	
}