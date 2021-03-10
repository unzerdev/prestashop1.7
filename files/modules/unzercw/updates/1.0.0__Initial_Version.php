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

require_once 'Customweb/Database/Migration/IScript.php';

class UnzerCw_Migration_1_0_0 implements Customweb_Database_Migration_IScript{
	
	public function execute(Customweb_Database_IDriver $driver) {
		
		$driver->query("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "unzercw_customer_contexts` (
			`id_customer_context` int(11) NOT NULL auto_increment,
			`id_customer` int(11) NOT NULL,
			`payment_customer_context` mediumtext,
			`updated_on` datetime NOT NULL,
			`created_on` datetime NOT NULL,
			PRIMARY KEY  (`id_customer_context`),
			UNIQUE KEY `id_customer` (`id_customer`)
			) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;")->execute();
		

		$driver->query("CREATE TABLE IF NOT EXISTS `unzercw_storage` (
			`keyId` bigint(20) NOT NULL AUTO_INCREMENT,
			`keyName` varchar(165) DEFAULT NULL,
			`keySpace` varchar(165) DEFAULT NULL,
			`keyValue` longtext,
			PRIMARY KEY (`keyId`),
			UNIQUE KEY `keyName_keySpace` (`keyName`,`keySpace`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1")->execute();

		$driver->query("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "unzercw_transactions` (
			`id_transaction` int(11) NOT NULL auto_increment,
			`id_cart` int(11) NOT NULL,
			`id_customer` int(11) NOT NULL,
			`id_order` int(11) default NULL,
			`id_module` int(11) NOT NULL,
			`alias_for_display` varchar(255) default NULL,
			`alias_active` char(1) default 'y',
			`payment_method` varchar(255) NOT NULL,
			`transaction_object` MEDIUMTEXT,
			`authorization_type` varchar(255) NOT NULL,
			`updated_on` datetime NOT NULL,
			`created_on` datetime NOT NULL,
			`payment_id` varchar(255) NOT NULL,
			`updatable` char(1) default 'n',
			PRIMARY KEY  (`id_transaction`)
			) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;")->execute();
		
	}

}