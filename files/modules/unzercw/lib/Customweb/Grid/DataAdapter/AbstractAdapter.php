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

require_once 'Customweb/Grid/DataAdapter/IAdapter.php';

/**
 * Abstract implementation of a grid data adapter. This class may 
 * be used by subclasses to provide an adapter.  
 * 
 * @author Thomas Hunziker
 *
 */
abstract class Customweb_Grid_DataAdapter_AbstractAdapter implements Customweb_Grid_DataAdapter_IAdapter{
	
	private $query;
	
	/**
	 * @var Customweb_Grid_RequestHandler
	 */
	private $requestHandler = null;
	
	public function __construct($query) {
		$this->query = $query;
	}
	
	public function setRequestHandler(Customweb_Grid_RequestHandler $request) {
		$this->requestHandler = $request;
		return $this;
	}
	
	public function getBaseQuery() {
		return $this->query;
	}
	
	public function fetchResults() {
	
		if ($this->requestHandler === null) {
			throw new Exception("Before fetching any results, the request handler must be set.");
		}
		$query = $this->buildQuery();
	
		$resultSet = array();
		$result = $this->executeQuery($query);
		while (($row = $this->fetchRow($result)) !== false) {
			$resultSet[] = $row;
		}
	
		return $resultSet;
	}
	
	public function getTotalNumberOfRows() {
		if ($this->requestHandler === null) {
			throw new Exception("Before fetching any results, the request handler must be set.");
		}
		$query = $this->buildCountQuery();
		$result = $this->executeQuery($query);
		
		$countRow = $this->fetchRow($result);
		if (isset($countRow['numberOfEntries'])) {
			return $countRow['numberOfEntries'];
		}
		else {
			return 0;
		}
	}
	
	/**
	 * Executes the given query.
	 * 
	 * @param string $query
	 * @return resource
	 */
	abstract protected function executeQuery($query);
	
	/**
	 * Returns either the result or false in case no more rows exists.
	 * 
	 * @param resource $result
	 * @return boolean|array
	 */
	abstract protected function fetchRow($result);
	
	/**
	 * Returns the number of results in the result set.
	 * 
	 * @param resource $result
	 * @return int
	 */
	abstract protected function fetchNumberOfRows($result);
	
	/**
	 * Quotes the given value (escape and adds quotes).
	 * 
	 * @param string $value
	 * @return string
	 */
	abstract protected function quote($value);
	
	protected function buildQuery() {
		$query = $this->getBaseQuery();
	
		$query = $this->injectWhere($query);
		$query = $this->injectOrderBy($query);
		$query = $this->injectLimit($query);
	
		return $query;
	}
	
	protected function buildCountQuery() {
		$query = $this->getBaseQuery();
		
		if (strpos($query, 'SELECT *') === false) {
			throw new Exception("The query does not contain a SELECT command.");
		}
		
		$query = str_replace("SELECT *", "SELECT count(*) AS numberOfEntries ", $query);
	
		$query = $this->injectWhere($query);
		$query = $this->injectOrderBy($query);
		$query = str_replace('${LIMIT}', '', $query);
	
		return $query;
	}
	
	protected function injectOrderBy($query) {
		$orderBy = '';
		foreach ($this->requestHandler->getOrderBys() as $key => $order) {
			$orderBy .= $key . ' ' . $order . ', ';
		}
		if (!empty($orderBy)) {
			$orderBy = substr($orderBy, 0, -2);
			$orderBy = 'ORDER BY ' . $orderBy;
		}
	
		if (strstr($query, '${ORDER_BY}') !== false) {
			$query = str_replace('${ORDER_BY}', $orderBy, $query);
		}
		else {
			throw new Exception("Could not find variable '\${ORDER_BY}' in query.");
		}
	
		return $query;
	}
	
	protected function injectLimit($query) {
	
		$startItem = $this->requestHandler->getPageIndex() * $this->requestHandler->getNumberOfItems();
		$endItem = $this->requestHandler->getNumberOfItems();
		$limit = $startItem . ',' . $endItem;
	
		if (!strstr($query, '${LIMIT}') !== false) {
			throw new Exception("Could not find variable '\${LIMIT}' in query.");
		}
	
		$limit = ' LIMIT ' . $limit;
		return str_replace('${LIMIT}', $limit, $query);
	}
	
	
	protected function injectWhere($query) {
		$where = '';
		foreach ($this->requestHandler->getFilters() as $filter) {
			if ($filter instanceof Customweb_Grid_GenericFilter) {
				$where .= $this->getSqlFromFilter($filter) . ' AND ';
			}
			else {
				throw new Exception("Currently only the Customweb_Grid_GenericFilter is supported.");
			}
				
		}
		if (!empty($where)) {
			$where = substr($where, 0, -5);
		}
		else {
			$where = '1 = 1';
		}
	
		if (strstr($query, '${WHERE}') !== false) {
			$query = str_replace('${WHERE}', $where, $query);
		}
		else {
			throw new Exception("Could not find variable '\${WHERE}' in query.");
		}
	
		return $query;
	}
	
	protected function getSqlFromFilter(Customweb_Grid_GenericFilter $filter) {
		if ($filter->getComparison() == 'LIKE') {
			return $filter->getKey() . ' ' . $filter->getComparison() . ' ' . $this->quote('%' . $filter->getValue() . '%') . '';
		}
		else {
			return $filter->getKey() . ' ' . $filter->getComparison() . ' ' . $this->quote($filter->getValue()) . '';
		}
	}
	
}