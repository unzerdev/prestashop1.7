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

require_once 'Customweb/Grid/RequestHandler.php';

class Customweb_Grid_Loader {
	
	/**
	 * @var Customweb_Grid_Column[]
	 */
	private $columns = array();
	
	/**
	 * @var Customweb_Grid_DataAdapter_IAdapter
	 */
	private $dataAdpater = null;
	
	private $request = array();
	
	private $rows = array();
	
	private $numberOfRows = -1;

	/**
	 * @var Customweb_Grid_RequestHandler
	 */
	private $requestHandler = null;
	
	private $defaultSortingsSet = false;
	
	/**
	 * @return multitype:
	 */
	public function getRequestData() {
		return $this->request;
	}
	
	/**
	 * @param array $requestData
	 * @return Customweb_Grid_Loader
	 */
	public function setRequestData(array $requestData) {
		$this->request = $requestData;
		$this->setRequestHandler(new Customweb_Grid_RequestHandler($requestData));
		return $this;
	}
	
	/**
	 * 
	 * @param Customweb_Grid_Column $column
	 * @return Customweb_Grid_Loader
	 */
	public function addColumn(Customweb_Grid_Column $column) {
		$this->columns[] = $column;
		return $this;
	}

	/**
	 * @param array $columns
	 * @return Customweb_Grid_Loader
	 */
	public function setColumns(array $columns) {
		$this->columns = $columns;
		return $this;
	}
	
	/**
	 * @return Customweb_Grid_Column[]
	 */
	public function getColumns() {
		return $this->columns;
	}
	
	/**
	 * @param Customweb_Grid_DataAdapter_IAdapter $adapter
	 * @return Customweb_Grid_Loader
	 */
	public function setDataAdapter(Customweb_Grid_DataAdapter_IAdapter $adapter) {
		$this->dataAdpater = $adapter;
		return $this;
	}
	
	/**
	 * @return Customweb_Grid_DataAdapter_IAdapter
	 */
	public function getDataAdapter() {
		return $this->dataAdpater;
	}
	
	/**
	 * @param Customweb_Grid_RequestHandler $handler
	 * @return Customweb_Grid_Loader
	 */
	public function setRequestHandler(Customweb_Grid_RequestHandler $handler) {
		$this->requestHandler = $handler;
		return $this;
	}
	
	/**
	 * @return Customweb_Grid_RequestHandler
	 */
	public function getRequestHandler() {
		return $this->requestHandler;
	}
	
	/**
	 * @throws Exception
	 * @return array
	 */
	public function getRows() {
		
		if (count($this->getColumns()) <= 0) {
			throw new Exception("No column set. For loading at least one column must be set.");
		}
		
		if ($this->getDataAdapter() === null) {
			throw new Exception("No data adapter is set. Before loading the gird data, a data adapter must be set.");
		}
		
		if (count($this->rows) <= 0) {
			$this->setDefaultSortings();
			$rowCount = $this->getNumberOfRows();
			$total = $this->getRequestHandler()->getPageIndex() * $this->getRequestHandler()->getNumberOfItems();
			if ($total > $rowCount) {
				$this->setRequestHandler($this->getRequestHandler()->setPageIndex(0));
			}
			
			$this->getDataAdapter()->setRequestHandler($this->getRequestHandler());
			$this->rows = $this->getDataAdapter()->fetchResults();
			
			
		}
		
		return $this->rows;
	}
	
	public function getNumberOfRows() {
		
		if ($this->getDataAdapter() === null) {
			throw new Exception("No data adapter is set. Before loading the gird data, a data adapter must be set.");
		}
		
		if ($this->numberOfRows < 0) {
			$this->setDefaultSortings();
			$this->getDataAdapter()->setRequestHandler($this->getRequestHandler());
			$this->numberOfRows = $this->getDataAdapter()->getTotalNumberOfRows();
		}
		
		return $this->numberOfRows;
	}
	
	/**
	 * This method updates the default sortings in the request handler
	 */
	protected function setDefaultSortings() {
		if (!$this->defaultSortingsSet) {
			$requestHandler = $this->getRequestHandler();
			$sortings = $requestHandler->getOrderBys();
			
			// Set the default sorting
			foreach ($this->getColumns() as $column) {
				/* @var $column Customweb_Grid_Column */
				$sorting = $column->getDefaultSorting();
				if ($sorting !== null && count($sortings) <= 0) {
					$sortings[$column->getKey()] = $sorting;
				}
			}
			$requestHandler = $requestHandler->setOrderBys($sortings);
			$this->setRequestHandler($requestHandler);
			$this->defaultSortingsSet = true;
		}
	}
	
}