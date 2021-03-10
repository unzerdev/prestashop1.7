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



abstract class UnzerCw_DatabaseLinkAccessor extends Db {
	
	/**
	 * The link variable is protected. Over reflection the 
	 * access is only possible with 5.3 and above. This accessor
	 * allows the access on the protected value wihtout needing this
	 * requirement (over inheritance).
	 * 
	 * @param Db $database
	 */
	public static function getUnzerCwLink(Db $database) {
		
		if (version_compare(_PS_VERSION_, '1.6.1') >= 0) {
			return $database->getLink();			
		}
		else {
			return $database->link;
		}
	}
	
}
