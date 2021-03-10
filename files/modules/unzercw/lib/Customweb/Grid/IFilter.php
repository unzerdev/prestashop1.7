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

interface Customweb_Grid_IFilter {
	
	/**
	 * This method returns a unique identifier of the filter. If a filter with the same
	 * key is added to the request, the existing filter is overriden by the actual filter.
	 * 
	 * @return string A key, which identifies this filter.
	 */
	public function getKey();
	
	
}