<?php
    include_once('../include/classlib.php');
    include_once('../language/lang_all.php');
	log::setupLogPath(__DIR__, __FILE__);

	$language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;

    echo "Start patch\n";

    $date = date('Y-m-d H:i:s');

    // Create inv_product_supplier Table
    $db->rawQuery("CREATE TABLE `inv_product_supplier` ( `id` BIGINT(20) NOT NULL AUTO_INCREMENT , `supplier_id` BIGINT(20) NOT NULL , `inv_product_id` BIGINT(20) NOT NULL , `cost` DECIMAL(20,8) NOT NULL , `status` VARCHAR(255) NOT NULL , `created_at` DATETIME NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_unicode_ci;");
    $db->rawQuery("ALTER TABLE `inv_product_supplier` ADD KEY `inv_product_id` (`inv_product_id`), ADD KEY `status` (`status`);");

    // Create inv_purchase_order Table
    $db->rawQuery("CREATE TABLE `inv_purchase_order` ( `id` BIGINT(20) NOT NULL AUTO_INCREMENT , `inv_order_id` BIGINT(20) NOT NULL , `supplier_id` BIGINT(20) NOT NULL, `reference_number` VARCHAR(255) NOT NULL , `status` VARCHAR(255) NOT NULL , `created_at` DATETIME NOT NULL , `updated_at` DATETIME NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_unicode_ci;");
    $db->rawQuery("ALTER TABLE `inv_purchase_order` ADD KEY `supplier_id` (`supplier_id`), ADD KEY `status` (`status`);");

    // Create inv_purchase_order_detail Table
    $db->rawQuery("CREATE TABLE `inv_purchase_order_detail` ( `id` BIGINT(20) NOT NULL AUTO_INCREMENT , `purchase_order_id` BIGINT(20) NOT NULL , `inv_product_id` BIGINT(20) NOT NULL , `quantity` DECIMAL(20,8) NOT NULL , `real_quantity` DECIMAL(20,8) NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_unicode_ci;");
    $db->rawQuery("ALTER TABLE `inv_purchase_order_detail` ADD KEY `purchase_order_id` (`purchase_order_id`);");

    // Add Supplier_id column for inv_product_transaction Table
    $db->rawQuery("ALTER TABLE `inv_product_transaction` ADD `supplier_id` BIGINT(20) NOT NULL AFTER `id`;");

    // Add Index for inv_product_transaction
    $db->rawQuery("ALTER TABLE `inv_product_transaction` ADD KEY `getBalance` (`created_at`,`inv_product_id`), ADD KEY `supplier_id` (`supplier_id`);");

    // Add Purchase Order ID for inv_delivery_order
    $db->rawQuery("ALTER TABLE `inv_delivery_order` ADD `purchase_order_id` BIGINT(20) NOT NULL AFTER `inv_order_id`;");

    // Add Permission For Purchase Listing
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) SELECT NULL, 'Purchase Order Listing', 'Purchase Order Listing', 'Sub Menu', b.id, 'purchaseOrderList.php', '1', (SELECT (c.priority)+1 FROM permissions c WHERE c.name = 'Redemption Listing' AND c.parent_id = b.id), '', 'A01568', '0', '0', 'Admin', '0000-00-00 00:00:00', '2021-01-14 14:32:49', '', '', '0' FROM permissions b WHERE b.name = 'Inventory' AND b.type = 'Menu';");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) SELECT NULL, 'Purchase Order Detail', 'Purchase Order Detail', 'Page', b.id, 'purchaseOrderDetail.php', '2', '1', '', 'A01569', '0', '0', 'Admin', '0000-00-00 00:00:00', '2021-01-14 14:32:49', '', '', '0' FROM permissions b WHERE b.name = 'Purchase Order Listing' AND b.type = 'Sub Menu';");
    $db->rawQuery("UPDATE `permissions` SET `priority` = `priority`+1 WHERE `permissions`.`name` = 'Delivery Order Listing';");

    $roleParams['roleName'] = 'Supplier';
    $roleParams['description'] = 'This role will be only have Purchase Order and Delivery Order Permission.';
    $roleParams['status'] = 0;
    $roleParams['site'] = 'Admin';

    $res = User::addRole($roleParams);
    if($res['status'] != 'ok'){
        echo "Failed to Add Roles.\n";
    }

    $db->where('name','Supplier');
    $roleID = $db->getValue('roles','id');

    $adminParams['email'] = "supplier01@mail.com";
    $adminParams['fullName'] = "supplier01";
    $adminParams['username'] = "supplier01";
    $adminParams['password'] = "qwe123";
    $adminParams['roleID']   = $roleID;
    $adminParams['status']   = "Active";
    $res = User::addAdmin($adminParams);
    if($res['status'] != 'ok'){
        echo "Failed to Add Admin.\n";
    }

    echo "Start Patch Existing Product Supplier.\n";

    $db->where('name','supplier01');
    $supplierID = $db->getValue('admin','id');

    $invProductArr = $db->map('id')->get('inv_product',null,'id,created_at');
    foreach ($invProductArr as $invProductID => $createdAt) {
        unset($insertData);
        $insertData = array(
            "supplier_id"    => $supplierID,
            "inv_product_id" => $invProductID,
            "cost"           => "1",
            "status"         => "Active",
            "created_at"     => $createdAt
        );
        $db->insert('inv_product_supplier',$insertData);
    }

    $db->update('inv_product_transaction',array("supplier_id"=>$supplierID));

    echo "End Patch Existing Product Supplier.\n";

	echo "Done patch\n";

?>