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

class UnzerCw_Migration_2_3_0 implements Customweb_Database_Migration_IScript{

	public function execute(Customweb_Database_IDriver $driver) {
		
		$result = $driver->query("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "unzercw_transactions` LIKE 'transactionObjectBinary'");
		if ($result->getRowCount() <= 0) {
			$driver->query("ALTER TABLE  `" . _DB_PREFIX_ . "unzercw_transactions` ADD  `transactionObjectBinary` MEDIUMBLOB NULL")->execute();
		}
		
		
		$driver->query("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "unzercw_documents` (
			documentId bigint(20) NOT NULL AUTO_INCREMENT,
			transactionId int (11) ,
			machineName varchar (100) ,
			name LONGTEXT ,
			fileExtension varchar (255) ,
			updatedOn datetime ,
			createdOn datetime ,
			versionNumber int NOT NULL,
			KEY transactionId (transactionId),
			PRIMARY KEY (documentId)
		) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB;")->execute();

	}

}
