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

require_once 'Customweb/Grid/Renderer.php';



class UnzerCw_Grid_Renderer extends Customweb_Grid_Renderer {
	
	public function getTableCssClass() {
		return 'table unzercw-transaction-table';
	}
	
	protected function renderFilters() {
		$html = '';
	
		$html .= '<tr class="nodrag nodrop filter row_hover" style="height: 35px;">';
		foreach ($this->getLoader()->getColumns() as $column) {
			$html .= $this->renderFilter($column);
		}
		$html .= '</tr>';
	
		return $html;
	}
	
	protected function renderResultSelector() {
		$html = '';
	
		$html .= '<div style="display: inline-block;">  <input type="submit" value="change" style="visibility: hidden;" />';
	
		$html .= '<select name="numberOfItems" class="' . $this->getFilterControlCssClass() . '" onchange="this.form.submit()">';
		$numberOfItems = $this->getRequestHandler()->getNumberOfItems();
		foreach ($this->getNumberOfItemsPerPageOptions() as $option) {
				
			$html .= '<option value="' . $option . '"';
			if ($numberOfItems == $option) {
				$html .= ' selected="selected" ';
			}
			$html .= '>' . $option . '</option>';
				
		}
		$html .= '</select>';
		//$html .= $this->renderResultSelectorButton();
	
		$html .= '</div>';
	
		return $html;
	}
	
	
	
}