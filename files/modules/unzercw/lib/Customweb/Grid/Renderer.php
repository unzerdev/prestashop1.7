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


require_once 'Customweb/Core/Url.php';

class Customweb_Grid_Renderer {

	/**
	 * @var Customweb_Grid_Loader
	 */
	private $loader = null;

	/**
	 * @var string
	 */
	private $gridCssClass = 'grid';

	private $headerCssContentClass = '';

	private $baseUrl = null;
	
	private $tableCssClass = 'grid-table';
	
	private $filterControlCssClass = 'grid-filter';
	
	private $numberOfItemsPerPageOptions = array('10', '30', '100', '500');
	
	private $pageCssClass = 'pagination';
	
	private $infoBoxCssClass = 'info-box-grid';
	
	private $resultSelectorButtonCssClass;
	
	private $resultSelctorWrapperCssClass;
	
	private $hrefCssClass;
	
	private $gridId = 'grid';

	public function __construct(Customweb_Grid_Loader $loader, $baseUrl) {
		$this->loader = $loader;
		
		// Load the data
		$this->loader->getRows();
		$this->baseUrl = str_replace('&amp;', '&', $baseUrl);
	}

	public function getBaseUrl() {
		return $this->baseUrl;
	}
	
	public function getGridId() {
		return $this->gridId;
	}
	
	public function setGridId($id) {
		$this->gridId = $id;
		return $this;
	}
	
	/**
	 * Set here the base URL, you want to use for all links.
	 *
	 * @param unknown_type $url
	 */
	public function setBaseUrl($url) {
		$this->baseUrl = $url;
		return $this;
	}

	/**
	 * @return Customweb_Grid_Loader
	 */
	public function getLoader() {
		return $this->loader;
	}

	public function setNumberOfItemsPerPageOptions(array $options) {
		$this->numberOfItemsPerPageOptions = $options;
		return $this;
	}
	
	public function getNumberOfItemsPerPageOptions() {
		return $this->numberOfItemsPerPageOptions;
	}
	
	/**
	 * @return Customweb_Grid_RequestHandler
	 */
	public function getRequestHandler() {
		return $this->getLoader()->getRequestHandler();
	}
	
	public function setFilterControlCssClass($class) {
		$this->filterControlCssClass = $class;
		return $this;
	}
	
	public function getFilterControlCssClass() {
		return $this->filterControlCssClass;
	}
	
	public function getResultSelctorWrapperCssClass() {
		return $this->resultSelctorWrapperCssClass;
	}
	
	public function setResultSelectorWrapperCssClass($class) {
		$this->resultSelctorWrapperCssClass = $class;
		return $this;
	}
	
	public function getHrefCssClass() {
		return $this->hrefCssClass;
	}
	
	public function setHrefCssClass($class) {
		$this->hrefCssClass = $class;
		return $this;
	}
	
	public function getResultSelectorButtonCssClass() {
		return $this->resultSelectorButtonCssClass;
	}
	
	public function setResultSelectorButtonCssClass($class) {
		$this->resultSelectorButtonCssClass = $class;
		return $this;
	}
	
	public function setTableCssClass($class) {
		$this->tableCssClass = $class;
		return $this;
	}
	
	public function getTableCssClass() {
		return $this->tableCssClass;
	}

	public function getPageCssClass() {
		return $this->pageCssClass;
	}
	
	public function setPageCssClass($class) {
		$this->pageCssClass = $class;
		return $this;
	}
	
	public function getInfoBoxCssClass() {
		return $this->infoBoxCssClass;
	}
	
	public function setInfoBoxCssClass($class) {
		$this->infoBoxCssClass = $class;
		return $this;
	}
	
	/**
	 * @param string $class
	 * @return Customweb_Grid_Renderer
	 */
	public function setGridCssClass($class) {
		$this->gridCssClass = $class;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getGridCssClass() {
		return $this->gridCssClass;
	}

	/**
	 * @param string $class
	 * @return Customweb_Grid_Renderer
	 */
	public function setHeaderCssContentClass($class) {
		$this->headerCssContentClass = $class;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHeaderCssContentClass() {
		return $this->headerCssContentClass;
	}

	/**
	 * @return string
	 */
	public function render() {

		$html = '';
		$html .= $this->renderGridPrefix();
		$html .= $this->renderFormPrefix();

		$html .= $this->renderTable();

		$html .= $this->renderFooter();

		$html .= $this->renderFormPostfix();
		$html .= $this->renderGridPostfix();

		return $html;
	}

	protected function renderTable() {
		$html = '';

		$html .= '<table class="' . $this->getTableCssClass() . '">';

		$html .= $this->renderTableHeader();

		if ($this->hasFilterableColumns()) {
			$html .= $this->renderFilters();
		}

		$html .= $this->renderTableBody();

		$html .= '</table>';
		return $html;
	}

	public function hasFilterableColumns() {
		foreach ($this->getLoader()->getColumns() as $column) {
			if ($column->isFilterable()) {
				return true;
			}
		}
		return false;
	}

	protected function renderFilters() {
		$html = '';

		$html .= '<tr class="grid-filters">';
		foreach ($this->getLoader()->getColumns() as $column) {
			$html .= $this->renderFilter($column);
		}
		$html .= '</tr>';

		return $html;
	}

	protected function renderFilter(Customweb_Grid_Column $column) {
		$html = '';

		$html .= '<td class="grid-filter-row">';

		if ($column->isFilterable()) {
			$options = $column->getOptions();

			$value = $this->getRequestHandler()->getFilterFieldValue($column->getKey());
			$fieldName = $this->getRequestHandler()->getFilterFieldName($column->getKey());
			if (count($options) <= 0) {
				$html .= '<input type="text" class="' . $this->getFilterControlCssClass() . '"
				name="' . $fieldName . '"
				value="' . $value . '" />';
			}
			else {
				$html .= '<select name="' . $fieldName . '" class="' . $this->getFilterControlCssClass() . '">';
				foreach ($options as $key => $value) {
					$html .= '<option value="' . $key . '"';
					if ($key == $value) {
						$html .= ' selected="selected" ';
					}
					$html .= '>' . $value . '</option>';
				}
				$html .= '</select>';
			}
		}

		$html .= '</td>';

		return $html;
	}

	protected function renderTableBody() {

		$html = '';
		foreach ($this->getLoader()->getRows() as $rowData) {
			$html .= $this->renderTableRow($rowData);
		}
		return $html;
	}

	protected function renderTableRow($rowData) {
		$html = '';

		$html .= '<tr class="grid-row">';
		foreach ($this->getLoader()->getColumns() as $column) {
			$html .= $this->renderTableCell($column, $rowData);
		}
		$html .= '</tr>';

		return $html;
	}

	protected function renderTableCell(Customweb_Grid_Column $column, $rowData) {
		return '<td>' . $column->getContent($rowData) . '</td>';
	}

	protected function renderFooter() {
		$html = '';

		$html .= '<div class="grid-footer">';
		$html .= $this->renderInfo();
		$html .= $this->renderPager();
		$html .= $this->renderResultSelector();
		$html .= '</div>';

		return $html;
	}

	protected function getCurrentStartingItemNumber() {
		return $this->getRequestHandler()->getPageIndex() * $this->getRequestHandler()->getNumberOfItems() + 1;
	}

	protected function getCurrentEndingItemNumber() {
		$effectiveNumberOfitems = $this->getRequestHandler()->getNumberOfItems();
		$calc = ($this->getRequestHandler()->getPageIndex() + 1) * $this->getRequestHandler()->getNumberOfItems();
		if ($effectiveNumberOfitems > $calc) {
			return $calc;
		}
		else {
			return $effectiveNumberOfitems;
		}
	}

	protected function getTotalNumberOfItems() {
		return $this->getLoader()->getNumberOfRows();
	}

	protected function renderInfo() {
		$text = $this->getInfoPattern();
		$text = str_replace('!startingItem', $this->getCurrentStartingItemNumber(), $text);
		$text = str_replace('!endingItem', $this->getCurrentEndingItemNumber(), $text);
		$text = str_replace('!totalItems', $this->getTotalNumberOfItems(), $text);
		
		return '<div class="' . $this->getInfoBoxCssClass() .  '">' . $text . '</div>';
	}

	protected function getInfoPattern() {
		return 'Showing !startingItem to !endingItem of !totalItems items.';
	}

	protected function renderPager() {
		$html = '';
		
		$html .= '<ul class="' . $this->getPageCssClass() . '">';
		
		$html .= $this->renderLeftEndPager();
		$html .= $this->renderGoOneLeftPager();
		
		$numberOfPages = $this->getNumberOfPages();
		for ($i = 0; $i < $numberOfPages; $i++) {
			$html .= $this->renderPagerLink($i);
		}
		
		$html .= $this->renderGoOneRightPager();
		$html .= $this->renderRightEndPager();
		
		$html .= '</ul>';
		
		return $html;
	}
	
	protected function getNumberOfPages() {
		return ceil($this->getTotalNumberOfItems() / $this->getRequestHandler()->getNumberOfItems());
	}

	protected function renderLeftEndPager() {
		$url = $this->createUrl($this->getRequestHandler()->setPageIndex(0)->getParameters());
		$class = '';
		if ($this->getRequestHandler()->getPageIndex() == 0) {
			$class = 'disabled';
		}
		return '<li class="' . $class . '"><a class="' . $this->getHrefCssClass() . '" href="' . $url . '">&laquo;</a></li>';
	}
	
	protected function renderGoOneLeftPager() {
		$pageIndex = $this->getRequestHandler()->getPageIndex() - 1;
		if ($pageIndex < 0) {
			$pageIndex = 0;
		}
		$url = $this->createUrl($this->getRequestHandler()->setPageIndex($pageIndex)->getParameters());
		$class = '';
		if ($this->getRequestHandler()->getPageIndex() == 0) {
			$class = 'disabled';
		}
		return '<li class="' . $class . '"><a class="' . $this->getHrefCssClass() . '" href="' . $url . '">&lsaquo;</a></li>';
	}
	
	protected function renderGoOneRightPager() {
		$numberOfPages = $this->getNumberOfPages() - 1;
		$pageIndex = $this->getRequestHandler()->getPageIndex() + 1;
		if ($pageIndex > $numberOfPages) {
			$pageIndex = $numberOfPages;
		}
		$url = $this->createUrl($this->getRequestHandler()->setPageIndex($pageIndex)->getParameters());
		$class = '';
		if ($this->getRequestHandler()->getPageIndex() == $numberOfPages) {
			$class = 'disabled';
		}
		return '<li class="' . $class . '"><a class="' . $this->getHrefCssClass() . '" href="' . $url . '">&rsaquo;</a></li>';
	}
	
	protected function renderRightEndPager() {
		$pageIndex = $this->getNumberOfPages() - 1;
		$url = $this->createUrl($this->getRequestHandler()->setPageIndex($pageIndex)->getParameters());
		$class = '';
		if ($this->getRequestHandler()->getPageIndex() == $pageIndex) {
			$class = 'disabled';
		}
		return '<li class="' . $class . '"><a class="' . $this->getHrefCssClass() . '" href="' . $url . '">&raquo;</a></li>';
	}
	
	protected function renderPagerLink($pageIndex) {
		$url = $this->createUrl($this->getRequestHandler()->setPageIndex($pageIndex)->getParameters());
		
		$class = '';
		if ($pageIndex == $this->getRequestHandler()->getPageIndex()) {
			$class = 'active';
		}
		
		return '<li class="' . $class . '"><a class="' . $this->getHrefCssClass() . '" href="' . $url . '">' . ($pageIndex + 1) . '</a></li>';
	}
	
	protected function renderResultSelector() {
		$html = '';
		
		$html .= '<div class="' . $this->getResultSelctorWrapperCssClass() . '">';
		
		$html .= '<select name="numberOfItems" class="' . $this->getFilterControlCssClass() . '">';
		$numberOfItems = $this->getRequestHandler()->getNumberOfItems();
		foreach ($this->getNumberOfItemsPerPageOptions() as $option) {
			
			$html .= '<option value="' . $option . '"';
			if ($numberOfItems == $option) {
				$html .= ' selected="selected" ';
			}
			$html .= '>' . $option . '</option>';
			
		}
		$html .= '</select>';
		$html .= $this->renderResultSelectorButton();
		
		$html .= '</div>';
		
		return $html;
	}
	
	protected function renderResultSelectorButton() {
		return '<input type="submit" value="' . $this->getSubmitButtonLabel() . '" class="' . $this->getResultSelectorButtonCssClass() . '" />';
	}
	
	protected function getSubmitButtonLabel() {
		return 'Apply';
	}

	protected function renderGridPrefix() {
		return '<div class="' . $this->getGridCssClass() . '" id="' . $this->getGridId() . '">';
	}

	protected function renderGridPostfix() {
		return '</div>';
	}

	protected function renderFormPrefix() {
		$url = new Customweb_Core_Url($this->getBaseUrl());
		$form = '<form class="' . $this->getGridCssClass() . '" action="' . $url->toString() . '">';
		$params = $this->getRequestHandler()->removeAllFilters()->getParameters();
		$params = array_merge($url->getQueryAsArray(), $params);
		foreach ($params as $key => $value) {
			if ($key != 'numberOfItems') {
				$form .= '<input type="hidden" name="' . $key . '" value="'. $value . '" />';
			}
		}

		return $form;
	}

	protected function renderFormPostfix() {
		return '<noscript> <input type="submit" value="Apply" /></noscript> </form>';
	}

	protected function renderTableHeader() {
		$html = '';
		$html .= $this->renderHeaderPrefix();

		foreach ($this->getLoader()->getColumns() as $column) {
			$html .= $this->renderHeaderContent($column);
		}

		$html .= $this->renderHeaderPostfix();
		return $html;
	}

	protected function renderHeaderContent(Customweb_Grid_Column $column) {
		$html = '';
		$html .= '<th class="' . $this->getHeaderCssContentClass() . '">';
		$html .= $column->getHeader();

		if ($column->isSortable()) {
			$html .= $this->renderSortControls($column);
		}

		$html .= '</th>';
		return $html;
	}

	protected function renderSortControls(Customweb_Grid_Column $column) {
		if (!$column->isSortable()) {
			return '';
		}

		$html = '';

		$class = 'no-sorting';
		$sorting = $this->getRequestHandler()->getColumnSorting($column->getKey());
		if ($sorting == 'ASC') {
			$class = 'ascending-sorting';
		}
		else if ($sorting == 'DESC') {
			$class = 'descending-sorting';
		}

		$html .= '<div class="sorting ' . $class . '">';
		$html .= '<a class="' . $this->getHrefCssClass() . '" href="' . $this->createUrl($this->getRequestHandler()->setOrderBy($column->getKey(), 'ASC')->getParameters()) . '"><span class="ascending">&nbsp;</span></a>';
		$html .= '<a class="' . $this->getHrefCssClass() . '" href="' . $this->createUrl($this->getRequestHandler()->setOrderBy($column->getKey(), 'DESC')->getParameters()) . '"><span class="descending">&nbsp;</span></a>';
		$html .= '<a class="' . $this->getHrefCssClass() . '" href="' . $this->createUrl($this->getRequestHandler()->removeOrderBy($column->getKey())->getParameters()) . '"><span class="reset-sorting">&nbsp;</span></a>';
		$html .= '</div>';

		return $html;
	}

	protected function createUrl(array $params) {
		$url = new Customweb_Core_Url($this->getBaseUrl());

		foreach ($params as $key => $value) {
			$url->appendQueryParameter($key, $value);
		}

		return $url->toString();
	}


	protected function renderHeaderPrefix() {
		return '<tr>';
	}

	protected function renderHeaderPostfix() {
		return '</tr>';
	}

}