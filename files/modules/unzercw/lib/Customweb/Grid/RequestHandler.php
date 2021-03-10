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

require_once 'Customweb/Grid/GenericFilter.php';

/**
 * This class handles the parsing of the request data and the encoding of the
 * request data back into a set of query parameters.
 * 
 * This class is imutable. Hence the set method returns always a clone of the
 * current object. This allows the modification of the object, without touching
 * the original RequestHandler.
 * 
 * @author Thomas Hunziker
 *
 */
class Customweb_Grid_RequestHandler {
	
	/**
	 * @var array
	 */
	private $parameters = array();
	
	/**
	 * @var array
	 */
	private $orderBys = array();
	
	/**
	 * @var Customweb_Grid_IFilter[]
	 */
	private $filters = array();
	
	/**
	 * @var integer
	 */
	private $pageIndex = 0;
	
	/**
	 * @var integer
	 */
	private $numberOfItems = 30;
	
	final public function __construct(array $parameters) {
		$this->parameters = $parameters;
		$this->parseRequest();
	}
	
	private function parseRequest() {
		
		if (isset($this->parameters['filters'])) {
			$filters = array();
			foreach($this->parameters['filters'] as $key => $filterData) {
				$filter = new Customweb_Grid_GenericFilter($key, $filterData['value']);
				$filters[$key] = $filter;
			}
			$this->filters = $filters;
		}
		
		if (isset($this->parameters['orderBys']) && !empty($this->parameters['orderBys'])) {
			$orderByStrings = explode(';', $this->parameters['orderBys']);
			$orderBys = array();
			foreach ($orderByStrings as $orderByString) {
				if (!empty($orderByString)) {
					$parts = explode(',', $orderByString);
					if (count($parts) != 2) {
						throw new Exception("Invalid group by format.");
					}
					$orderBys[$parts[0]] = $parts[1];
				}
			}
			$this->orderBys = $orderBys;
		}
		
		if (isset($this->parameters['numberOfItems'])) {
			$this->numberOfItems = $this->parameters['numberOfItems'];
		}
		
		if (isset($this->parameters['pageIndex'])) {
			$this->pageIndex = $this->parameters['pageIndex'];
		}
		
	}
	
	private function updateParameters() {
		
		$parameters = array();
		foreach ($this->getFilters() as $filter) {
			$parameters['filters'][$filter->getKey()]['value'] = $filter->getValue();
		}
		
		$orderBys = '';
		foreach ($this->getOrderBys() as $key => $order) {
			$orderBys .= $key . ',' . $order . ';';
		}
		if (!empty($orderBys)) {
			$parameters['orderBys'] = substr($orderBys, 0, -1);
		}
		
		$parameters['pageIndex'] = $this->getPageIndex();
		$parameters['numberOfItems'] = $this->getNumberOfItems();
		$this->parameters = $parameters;
	}
	
	public function getParameters() {
		$this->updateParameters();
		return $this->parameters;
	}
	
	public function getOrderBys() {
		return $this->orderBys;
	}
	
	public function isColumnSorted($columnKey) {
		if (isset($this->orderBys[$columnKey])) {
			return true;
		}
		else {
			return false;
		}
	}

	public function getColumnSorting($columnKey) {
		if ($this->isColumnSorted($columnKey)) {
			return $this->orderBys[$columnKey];
		}
		else {
			return null;
		}
	}
	
	/**
	 * @return Customweb_Grid_IFilter[]
	 */
	public function getFilters() {
		return $this->filters;
	}
	
	/**
	 * @return integer
	 */
	public function getPageIndex() {
		return $this->pageIndex;
	}
	
	/**
	 * @return integer
	 */
	public function getNumberOfItems() {
		return $this->numberOfItems;
	}
	
	/**
	 * @param Customweb_Grid_IFilter $filter
	 * @return Customweb_Grid_RequestHandler
	 */
	public function setFilter(Customweb_Grid_IFilter $filter) {
		$object = clone $this;
		$object->filters[$filter->getKey()] = $filter;
		return $object;
	}
	
	/**
	 * 
	 * @param array $filters
	 * @return Customweb_Grid_RequestHandler
	 */
	public function setFilters(array $filters) {
		$object = clone $this;
		$object->filters = $filters;
		return $object;
	}
	
	/**
	 * 
	 * @param string $fieldKey The identifier of the field.
	 * @param string $order Either DESC or ASC
	 * @return Customweb_Grid_RequestHandler
	 */
	public function setOrderBy($fieldKey, $order) {
		$object = clone $this;
		$object->orderBys[$fieldKey] = $order;
		return $object;
	}
	
	public function removeOrderBy($fieldKey) {
		$object = clone $this;
		unset($object->orderBys[$fieldKey]);
		return $object;
	}
	
	public function removeAllFilters() {
		$object = clone $this;
		$object->filters = array();
		return $object;
	}
	
	/**
	 * @param string $columnKey
	 * @return Customweb_Grid_IFilter
	 */
	public function getFilterByColumnKey($columnKey) {
		if (isset($this->filters[$columnKey])) {
			return $this->filters[$columnKey];
		}
		else {
			return null;
		}
	}
	
	public function getFilterFieldName($columnKey) {
		return 'filters[' . $columnKey . '][value]';
	}
	
	public function getFilterFieldValue($columnKey) {
		$filter = $this->getFilterByColumnKey($columnKey);
		if ($filter !== null) {
			return $filter->getValue();
		}
		else {
			return '';
		}
	}
	
	/**
	 * 
	 * @param array $orderBys
	 * @return Customweb_Grid_RequestHandler
	 */
	public function setOrderBys(array $orderBys) {
		$object = clone $this;
		$object->orderBys = $orderBys;
		return $object;
	}
	
	public function setPageIndex($pageIndex) {
		$object = clone $this;
		$object->pageIndex = $pageIndex;
		return $object;
	}
	
	public function setNumberOfItems($numberOfItemsPerPage) {
		$object = clone $this;
		$object->numberOfItems = $numberOfItemsPerPage;
		return $object;
	}
}