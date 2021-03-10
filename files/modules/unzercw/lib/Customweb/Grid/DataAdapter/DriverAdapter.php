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

require_once 'Customweb/Grid/DataAdapter/AbstractAdapter.php';
require_once 'Customweb/Grid/DataAdapter/IAdapter.php';

class Customweb_Grid_DataAdapter_DriverAdapter extends Customweb_Grid_DataAdapter_AbstractAdapter implements Customweb_Grid_DataAdapter_IAdapter{
	
	/**
	 * @var Customweb_Database_IDriver
	 */
	private $driver;
	
	public function __construct($query, Customweb_Database_IDriver $driver) {
		parent::__construct($query);
		$this->driver = $driver;
	}
	
	protected function executeQuery($query) {
		return $this->getDriver()->query($query);
	}
	
	protected function fetchRow($result) {
		if (!($result instanceof Customweb_Database_IStatement)) {
			throw new Customweb_Core_Exception_CastException('Customweb_Database_IStatement');
		}
		return $result->fetch();
	}
	
	protected function fetchNumberOfRows($result) {
		if (!($result instanceof Customweb_Database_IStatement)) {
			throw new Customweb_Core_Exception_CastException('Customweb_Database_IStatement');
		}
		return $result->getRowCount();
	}
	
	protected function quote($value) {
		return $this->getDriver()->quote($value);
	}
	
	/**
	 * @return Customweb_Database_IDriver
	 */
	protected function getDriver() {
		return $this->driver;
	}
	
}