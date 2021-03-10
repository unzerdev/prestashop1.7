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

class Customweb_Grid_Column {
	
	private $key = null;
	private $header = null;
	private $filterable = true;
	private $sortable = true;
	private $options = array();
	private $defaultSorting = null;
	
	/**
	 * @param string $key
	 * @param string $header
	 * @param string $defaultSorting Either DESC, ASC or NULL
	 */
	public function __construct($key, $header = null, $defaultSorting = null) {
		$this->key = $key;
		$this->header = $header;
		if ($defaultSorting !== null) {
			$this->defaultSorting = strtoupper($defaultSorting);
		}
	}
	
	public function setKey($key) {
		$this->key = $key;
		return $this;
	}
	
	public function getKey() {
		return $this->key;
	}
	
	public function setHeader($header) {
		$this->header = $header;
		return $this;
	}
	
	public function getHeader() {
		return $this->header;
	}
		
	public function isSortable() {
		return $this->sortable;
	}

	public function setSortable($sortable) {
		$this->sortable = $sortable;
		return $this;
	}
	
	public function isFilterable() {
		return $this->filterable;
	}
	
	public function setFilterable($filterable) {
		$this->filterable = $filterable;
		return $this;
	}
	
	public function isDropdownActive() {
		if (count($this->options) > 0) {
			return true;
		}
		else {
			return false;
		}
	}
	
	public function getOptions() {
		return $this->options;
	}
	
	public function setOptions(array $options) {
		$this->options = $options;
		return $this;
	}
	
	public function getContent($rowData) {
		if (isset($rowData[$this->getKey()])) {
			return $rowData[$this->getKey()];
		}
		else {
			return '';
		}
	}
	
	public function getDefaultSorting() {
		return $this->defaultSorting;
	}
	
	/**
	 * This method sets the default sorting. This sorting is applied if no sorting is provided by the user.
	 * 
	 * @param mixed $sorting Either DESC, ASC or null. Null resets the sorting for this column.
	 * @return Customweb_Grid_Column
	 */
	public function setDefaultSorting($sorting) {
		$this->defaultSorting = $sorting;
		return $this;
	}
	
}