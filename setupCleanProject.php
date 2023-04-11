<?php

    ##############################
    # Setup clean project script #
    ##############################

    include_once('include/config.php');
    include_once('include/class.database.php');
    include_once('include/class.setting.php');
    include_once('include/class.language.php');
    include_once('include/class.general.php');
    include_once('include/class.user.php');
    include_once('modules/mlmPlatform/include/class.scriptFunction.php');
    
    $databaseName = $config["dB"];
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General();

    $user = new user($db, $setting, $general);
    $language = new Language();
    // $client = new Client();
    $function = new scriptFunction();
    
    echo "Setup new project\n\n";

    echo "Dropping daily tables..\n";
    $function->deleteDailytable($databaseName, 'web_services_');
    $function->deleteDailytable($databaseName, 'sent_history_');
    $function->deleteDailytable($databaseName, 'acc_credit_');
    $function->deleteDailytable($databaseName, 'activity_log_');
    //truncate tables
    echo "truncating tables..\n";
    $db->rawQuery("TRUNCATE TABLE acc_closing");
    $db->rawQuery("TRUNCATE TABLE activity_log");
    $db->rawQuery("TRUNCATE TABLE admin");
    $db->rawQuery("TRUNCATE TABLE admin_agent");
    $db->rawQuery("TRUNCATE TABLE client");
    $db->rawQuery("TRUNCATE TABLE client_audit");
    $db->rawQuery("TRUNCATE TABLE client_rank");
    $db->rawQuery("TRUNCATE TABLE client_setting");
    $db->rawQuery("TRUNCATE TABLE credit");
    $db->rawQuery("TRUNCATE TABLE credit_setting");
    $db->rawQuery("TRUNCATE TABLE credit_transaction");
    $db->rawQuery("TRUNCATE TABLE transaction_id");
    $db->rawQuery("TRUNCATE TABLE language_import");
    $db->rawQuery("TRUNCATE TABLE log_upgrade");
    $db->rawQuery("TRUNCATE TABLE message_assigned");
    $db->rawQuery("TRUNCATE TABLE message_error");
    $db->rawQuery("TRUNCATE TABLE message_in");
    $db->rawQuery("TRUNCATE TABLE message_out");
    $db->rawQuery("TRUNCATE TABLE mlm_announcement");
    $db->rawQuery("TRUNCATE TABLE mlm_announcement_image_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_announcement_permissions");
    $db->rawQuery("TRUNCATE TABLE mlm_announcement_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_bonanza");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_calculation_batch");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_in");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_community");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_matching");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_sponsor");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_pairing");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_payment_method");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_placement");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_rebate");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_water_bucket");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_team");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_goldmine");
    $db->rawQuery("TRUNCATE TABLE mlm_client_bank");
    $db->rawQuery("TRUNCATE TABLE mlm_client_blocked_rights");
    $db->rawQuery("TRUNCATE TABLE mlm_client_portfolio");
    $db->rawQuery("TRUNCATE TABLE mlm_client_wallet_address");
    $db->rawQuery("TRUNCATE TABLE mlm_coin_rate");
    $db->rawQuery("TRUNCATE TABLE mlm_document");
    $db->rawQuery("TRUNCATE TABLE mlm_document_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_import_data");
    $db->rawQuery("TRUNCATE TABLE mlm_import_data_details");
    $db->rawQuery("TRUNCATE TABLE mlm_invoice");
    $db->rawQuery("TRUNCATE TABLE mlm_invoice_item");
    $db->rawQuery("TRUNCATE TABLE mlm_invoice_item_payment");
    $db->rawQuery("TRUNCATE TABLE mlm_memo");
    $db->rawQuery("TRUNCATE TABLE mlm_memo_image_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_memo_permissions");
    $db->rawQuery("TRUNCATE TABLE mlm_memo_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_payment_method");
    $db->rawQuery("TRUNCATE TABLE mlm_pin");
    $db->rawQuery("TRUNCATE TABLE mlm_pin_payment");
    $db->rawQuery("TRUNCATE TABLE mlm_product");
    $db->rawQuery("TRUNCATE TABLE mlm_product_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_queue");
    $db->rawQuery("TRUNCATE TABLE queue");
    $db->rawQuery("TRUNCATE TABLE mlm_ticket");
    $db->rawQuery("TRUNCATE TABLE mlm_ticket_details");
    $db->rawQuery("TRUNCATE TABLE mlm_unit_price");
    $db->rawQuery("TRUNCATE TABLE mlm_wallet_address");
    $db->rawQuery("TRUNCATE TABLE mlm_withdrawal");
    $db->rawQuery("TRUNCATE TABLE new_id");
    $db->rawQuery("TRUNCATE TABLE provider");
    $db->rawQuery("TRUNCATE TABLE rank");
    $db->rawQuery("TRUNCATE TABLE rank_setting");
    $db->rawQuery("TRUNCATE TABLE roles");
    $db->rawQuery("TRUNCATE TABLE roles_permission");
    $db->rawQuery("TRUNCATE TABLE server_status_data");
    $db->rawQuery("TRUNCATE TABLE server_status_summary");
    $db->rawQuery("TRUNCATE TABLE sms_integration");
    $db->rawQuery("TRUNCATE TABLE system_status");
    $db->rawQuery("TRUNCATE TABLE system_settings_admin");
    $db->rawQuery("TRUNCATE TABLE tree_placement");
    $db->rawQuery("TRUNCATE TABLE tree_sponsor");
    $db->rawQuery("TRUNCATE TABLE tree_placement_cache");
    $db->rawQuery("TRUNCATE TABLE tree_sponsor_cache");
    $db->rawQuery("TRUNCATE TABLE uploads");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_release");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_leadership");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_report");
    $db->rawQuery("TRUNCATE TABLE mlm_bonus_client_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_crypto_PG");
    $db->rawQuery("TRUNCATE TABLE mlm_crypto_setting");
    $db->rawQuery("TRUNCATE TABLE mlm_kyc");
    $db->rawQuery("TRUNCATE TABLE mlm_client_pairing");
    $db->rawQuery("TRUNCATE TABLE mlm_group_pairing");
    $db->rawQuery("TRUNCATE TABLE mlm_hash_log");
    $db->rawQuery("TRUNCATE TABLE group_id");
    $db->rawQuery("TRUNCATE TABLE mlm_export");
    $db->rawQuery("TRUNCATE TABLE p2p_payment_method");
    $db->rawQuery("TRUNCATE TABLE p2p_ads");
    $db->rawQuery("TRUNCATE TABLE p2p_ads_order");
    $db->rawQuery("TRUNCATE TABLE trd_transaction");
    $db->rawQuery("TRUNCATE TABLE trd_buy_queue");
    $db->rawQuery("TRUNCATE TABLE trd_match_transaction");
    $db->rawQuery("TRUNCATE TABLE trd_payment_method");
    $db->rawQuery("TRUNCATE TABLE trd_sell_queue");
    $db->rawQuery("TRUNCATE TABLE trd_coin");
    $db->rawQuery("TRUNCATE TABLE trd_coin_setting");
    $db->rawQuery("TRUNCATE TABLE trd_client_limit");
    $db->rawQuery("TRUNCATE TABLE trd_bonus_in");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_1m");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_15m");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_30m");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_1h");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_6h");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_12h");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_1d");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_1w");
    $db->rawQuery("TRUNCATE TABLE trd_graph_data_1mon");
    $db->rawQuery("TRUNCATE TABLE trd_trading_summary");
    $db->rawQuery("TRUNCATE TABLE trd_latest_trade");
    $db->rawQuery("TRUNCATE TABLE inv_delivery");
    $db->rawQuery("TRUNCATE TABLE inv_order");
    $db->rawQuery("TRUNCATE TABLE inv_order_detail");
    $db->rawQuery("TRUNCATE TABLE inv_order_payment");
    $db->rawQuery("TRUNCATE TABLE inv_product");
    $db->rawQuery("TRUNCATE TABLE inv_product_detail");
    $db->rawQuery("TRUNCATE TABLE inv_sst_charges");
    $db->rawQuery("TRUNCATE TABLE inv_category");
    $db->rawQuery("TRUNCATE TABLE system_settings_admin");

    echo "insert super admin and admin roles..\n";

    $db->rawQuery("INSERT INTO roles (`id`, `name`, `description`, `disabled`, `site`, `deleted`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', 'This role will be granted full access into the system', '0', 'SuperAdmin', '0', NOW(), NOW())");
    $db->rawQuery("INSERT INTO roles (`id`, `name`, `description`, `disabled`, `site`, `deleted`, `created_at`, `updated_at`) VALUES ('2', 'Master Admin', 'This role will be granted full access for the admin site.', '0', 'Admin', '0', NOW(), NOW())");
    $db->rawQuery("INSERT INTO roles (`id`, `name`, `description`, `disabled`, `site`, `deleted`, `created_at`, `updated_at`) VALUES ('3', 'Admin', 'This role will be granted full access for the admin site.', '0', 'Admin', '0', NOW(), NOW())");

    echo "inserting admin..\n";

    $adminPw = $user->getEncryptedPassword("t2Asia831");
    $directorPw = $user->getEncryptedPassword("tll901weB");

    $db->rawQuery("INSERT INTO admin (`id`, `username`, `name`, `password`, `email`, `role_id`, `created_at`, `updated_at`) VALUES ('1', 'solana', 'solana', '".$adminPw."', 'admin@email.com', '2', NOW(), NOW())");

    echo "inserting internal accounts..\n";

    $internalAccounts[] = array("id" => "1", "username" => "creditSales", "name" => "creditSales", "type" => "Internal", "description" => "Expenses", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "2", "username" => "withdrawal", "name" => "withdrawal", "type" => "Internal", "description" => "Suspense", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "3", "username" => "transfer", "name" => "transfer", "type" => "Internal", "description" => "Suspense", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "4", "username" => "convert", "name" => "convert", "type" => "Internal", "description" => "Suspense", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "5", "username" => "payout", "name" => "payout", "type" => "Internal", "description" => "Expenses", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "6", "username" => "creditAdjustment", "name" => "creditAdjustment", "type" => "Internal", "description" => "Earnings", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "7", "username" => "creditRefund", "name" => "creditRefund", "type" => "Internal", "description" => "Expenses", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "8", "username" => "creditSpending", "name" => "creditSpending", "type" => "Internal", "description" => "Earnings", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "9", "username" => "bonusPayout", "name" => "bonusPayout", "type" => "Internal", "description" => "Expenses", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "10", "username" => "System", "name" => "System", "type" => "Internal", "description" => "Earnings", "created_at" => date("Y-m-d H:i:s"));
    /*$internalAccounts[] = array("id" => "11", "username" => "escrowP2P", "name" => "escrowP2P", "type" => "Internal", "description" => "Suspense", "created_at" => date("Y-m-d H:i:s"));
    $internalAccounts[] = array("id" => "12", "username" => "escrowTrd", "name" => "escrowTrd", "type" => "Internal", "description" => "Suspense", "created_at" => date("Y-m-d H:i:s"));*/

    foreach($internalAccounts as $account){
        $db->insert("client", $account);
    }

    echo "inserting director..\n";
    $db->rawQuery("INSERT INTO client (`id`, `member_id`, `username`, `name`, `password`, `transaction_password`, `type`, `description`, `email`, `dial_code`, `phone`, `created_at`, `updated_at`,`activated`) VALUES ('1000000', '1000000', 'director', 'director', '".$directorPw."', '".$directorPw."', 'Client', 'First account in the company', 'director@gmail.com', '60', '123456789', NOW(), NOW(),'1')");

    // director placement
    // $placementTreeData = array("id" => "1", "client_id" => "1000000", "client_unit" => "1", "client_position" => "0", "upline_id" => "0", "upline_unit" => "0", "upline_position" => "0", "level" => "0", "trace_key" => "1000000-1");
    // $db->insert("tree_placement", $placementTreeData);

    // director sponsor
    $sponsorTreeData = array("id" => "1", "client_id" => "1000000", "upline_id" => "0", "level" => "0", "trace_key" => "1000000");
    $db->insert("tree_sponsor", $sponsorTreeData);

    // insert director client sales
    $clientSalesData = array("client_id" => "1000000","updated_at"=>$db->now());
    $db->insert("client_sales", $clientSalesData);

    echo "inserting new ID..\n";
    $db->rawQuery("ALTER TABLE credit AUTO_INCREMENT=1000;");
    $db->rawQuery("ALTER TABLE mlm_product AUTO_INCREMENT=2000;");
    $db->rawQuery("ALTER TABLE credit_transaction AUTO_INCREMENT=10000;");
    $db->rawQuery("ALTER TABLE trd_transaction AUTO_INCREMENT=20000;");
    $db->rawQuery("ALTER TABLE p2p_ads AUTO_INCREMENT=100000;");
    $db->rawQuery("ALTER TABLE p2p_ads_order AUTO_INCREMENT=10000;");
    $db->rawQuery("ALTER TABLE p2p_payment_method AUTO_INCREMENT=100;");
    $db->rawQuery("ALTER TABLE mlm_import_data AUTO_INCREMENT=30000;");
    $db->rawQuery("INSERT INTO new_id SELECT '1000000', NOW()");
    $db->rawQuery("INSERT INTO transaction_id SELECT '1000000', NOW()");
    $db->rawQuery("INSERT INTO group_id SELECT '2000000', NOW()");
    $db->rawQuery("ALTER TABLE address AUTO_INCREMENT=10000;");


    // **************************
    // ********  CREDITS ********
    // **************************

    //isP2PWallet, isTrdWallet, isMLMWallet

    unset($creditSettingAry);

    $creditSettingAry["isWallet"] = array("value" => "1", "admin" => "1", "member" => "1");
    $creditSettingAry["isAdjustable"] = array("value" => "1", "admin" => "1");
    $creditSettingAry["isPurchasable"] = array("value" => "1", "admin" => "1");

    $creditDataAry[] = array(
        "name" => "mfizDef",
        "type" => "mfizCredit",
        "code" => "MC",
        "description" => "mfizCredit",
        "image_name" => "MC.jpg",
        "display" => array(
            "english" => "Metafiz Wallet",
            "chineseSimplified" => "Metafiz Wallet",
            "chineseTraditional" => "Metafiz Wallet",
            "vietnam" => "Metafiz Wallet",
            "malay" => "Metafiz Wallet",
            "japanese" => "Metafiz Wallet",
            "korean" => "Metafiz Wallet"
        ),
        "adminDisplay" => array(
            "english" => "Metafiz Wallet",
            "chineseSimplified" => "Metafiz Wallet",
            "chineseTraditional" => "Metafiz Wallet",
            "vietnam" => "Metafiz Wallet",
            "malay" => "Metafiz Wallet",
            "japanese" => "Metafiz Wallet",
            "korean" => "Metafiz Wallet"
        ),
        "setting" => $creditSettingAry,
        "decimal" => 2,
    );

    /*P2P Wallet*/
    // $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isFundinable"]           = array("value" => "1", "admin" => "0", "member" => "1","reference" => "USDT");
    // $creditSettingAry["isTransferable"]         = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isWithdrawable"]         = array("value" => "3", "admin" => "0", "member" => "1", "reference" => "" ,"description" => "bank=1,crypto=2,both=3");
    // $creditSettingAry["withdrawalAdminFee"][]     = array("value" => "0","admin" => "0", "member" => "0", "type" => "amount","reference" => "min","description" => ""); //type amount/percentage
    // $creditSettingAry["withdrawalAdminFee"][]     = array("value" => "10","admin" => "0", "member" => "0", "type" => "percentage","reference" => "max","description" => ""); //type crypto/percentage
    // $creditSettingAry["isWithdrawAll"]          = array("value" => "1","admin" => "0", "member" => "0", "type" => "times","reference" => "3","description" => "Withdrawal All balance, only 3 times"); //type crypto/percentage
    // $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    // $creditSettingAry["isConvertible"]          = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["convertTo"]              = array("value" => "usdtTrd","admin" => "1", "member" => "1","reference"=> "1");
    // $creditSettingAry["transferByTree"]          = array("value" => "sponsorTree");
    // $creditSettingAry["isBonusValueWallet"]     = array("value" => "1");
    // $creditSettingAry["minWithdrawal"]          = array("value" => "0.001");
    // $creditSettingAry["withdrawalTypeFrom"]     = array("value" => "BTC");
    // $creditSettingAry["withdrawalTypeTo"]       = array("value" => "USDT");
    // $creditSettingAry["isP2PWallet"]           = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["processP2PFee"]         = array("value" => "0.2", "type" => "percentage");

    // $creditDataAry[] = array(
    //     "name" => "cashCredit",
    //     "type" => "cashCredit",
    //     "code" => "CC",
    //     "description" => "cashCredit",
    //     "image_name" => "CC.jpg",
    //     "display" => array("english" => "Cash Wallet",
    //                        "chineseSimplified" => "Cash Wallet",
    //                        "chineseTraditional" => "Cash Wallet",
    //                        "vietnam" => "Cash Wallet",
    //                        "malay" => "Cash Wallet",
    //                        "japanese" => "Cash Wallet",
    //                        "korean" => "Cash Wallet"),
    //     "adminDisplay" => array("english" => "Cash Wallet",
    //                        "chineseSimplified" => "Cash Wallet",
    //                        "chineseTraditional" => "Cash Wallet",
    //                        "vietnam" => "Cash Wallet",
    //                        "malay" => "Cash Wallet",
    //                        "japanese" => "Cash Wallet",
    //                        "korean" => "Cash Wallet"),
    //     "setting" => $creditSettingAry,
    //     "decimal" => 2
    // );
    // unset($creditSettingAry);

    // $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isWithdrawable"]         = array("value" => "3", "admin" => "0", "member" => "1", "description" => "bank=1,crypto=2,both=3");
    // $creditSettingAry["withdrawalAdminFee"][]     = array("value" => "0","admin" => "0", "member" => "0", "type" => "amount","reference" => "min","description" => ""); //type amount/percentage
    // $creditSettingAry["withdrawalAdminFee"][]     = array("value" => "5","admin" => "0", "member" => "0", "type" => "percentage","reference" => "max","description" => ""); //type crypto/percentage
    // $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    // $creditSettingAry["isTransferable"]         = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isConvertible"]          = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "100");
    // $creditSettingAry["convertTo"]              = array("value" => "vestCredit","admin" => "1", "member" => "1","reference"=> "1");
    // $creditSettingAry["isDisplayOnTransaction"] = array("value" => "1", "member" => "1");
    // $creditSettingAry["isHoldingWallet"]        = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "usdtWallet");
    // $creditSettingAry["transferByTree"]          = array("value" => "sponsorTree");
    // $creditSettingAry["isBonusValueWallet"]     = array("value" => "1");
    // $creditSettingAry["withdrawalAdminFee"]     = array("value" => "0.001","admin" => "1", "member" => "1", "type" => "amount"); //type amount/percentage
    // $creditSettingAry["minWithdrawal"]          = array("value" => "50");
    // $creditSettingAry["isHotDealFreshDeal"]     = array("value" => "1", "description" => "this is only for payment method purposes");
    // $creditSettingAry["isFundinable"]           = array("value" => "1", "admin" => "0", "member" => "1","reference" => "BTC");
    // $creditSettingAry["withdrawalTypeFrom"]     = array("value" => "BTC");
    // $creditSettingAry["withdrawalTypeTo"]       = array("value" => "USDT");
    // $creditSettingAry["isP2PWallet"]           = array("value" => "1", "admin" => "1", "member" => "1");

    /*$creditDataAry[] = array(
        "name" => "rewardRebate",
        "type" => "rewardCredit",
        "code" => "RC",
        "description" => "Reward Credit",
        "image_name" => "rewardCredit.jpg",
        "display" => array("english" => "Reward Wallet",
                           "chineseSimplified" => "Reward Wallet",
                           "chineseTraditional" => "Reward Wallet",
                           "vietnam" => "Reward Wallet",
                           "malay" => "Reward Wallet",
                           "japanese" => "Reward Wallet",
                           "korean" => "Reward Wallet"),
        "adminDisplay" => array("english" => "Reward Wallet",
                           "chineseSimplified" => "Reward Wallet",
                           "chineseTraditional" => "Reward Wallet",
                           "vietnam" => "Reward Wallet",
                           "malay" => "Reward Wallet",
                           "japanese" => "Reward Wallet",
                           "korean" => "Reward Wallet"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );
    unset($creditSettingAry);*/

    // $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isWithdrawable"]         = array("value" => "3", "admin" => "0", "member" => "1", "description" => "bank=1,crypto=2,both=3");
    // $creditSettingAry["withdrawalAdminFee"][]     = array("value" => "0","admin" => "0", "member" => "0", "type" => "amount","reference" => "min","description" => ""); //type amount/percentage
    // $creditSettingAry["withdrawalAdminFee"][]     = array("value" => "5","admin" => "0", "member" => "0", "type" => "percentage","reference" => "max","description" => ""); //type crypto/percentage
    // $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");

    // $creditSettingAry["isTransferable"]         = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isConvertible"]          = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "100");
    // $creditSettingAry["convertTo"]              = array("value" => "vestCredit","admin" => "1", "member" => "1","reference"=> "1");
    // $creditSettingAry["isDisplayOnTransaction"] = array("value" => "1", "member" => "1");
    // $creditSettingAry["isHoldingWallet"]        = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "usdtWallet");
    // $creditSettingAry["transferByTree"]          = array("value" => "sponsorTree");
    // $creditSettingAry["isBonusValueWallet"]     = array("value" => "1");
    // $creditSettingAry["withdrawalAdminFee"]     = array("value" => "0.001","admin" => "1", "member" => "1", "type" => "amount"); //type amount/percentage
    // $creditSettingAry["minWithdrawal"]          = array("value" => "50");
    // $creditSettingAry["isFundinable"]           = array("value" => "1", "admin" => "0", "member" => "1","reference" => "BTC");
    // $creditSettingAry["withdrawalTypeFrom"]     = array("value" => "BTC");
    // $creditSettingAry["withdrawalTypeTo"]       = array("value" => "USDT");
    // $creditSettingAry["isP2PWallet"]           = array("value" => "1", "admin" => "1", "member" => "1");

    /*$creditDataAry[] = array(
        "name" => "bonusGoldmine",
        "type" => "bonusCredit",
        "code" => "BC",
        "description" => "Bonus Credit",
        "image_name" => "bonusCredit.jpg",
        "display" => array("english" => "Bonus Wallet",
                           "chineseSimplified" => "Bonus Wallet",
                           "chineseTraditional" => "Bonus Wallet",
                           "vietnam" => "Bonus Wallet",
                           "malay" => "Bonus Wallet",
                           "japanese" => "Bonus Wallet",
                           "korean" => "Bonus Wallet"),
        "adminDisplay" => array("english" => "Bonus Wallet - Goldmine",
                           "chineseSimplified" => "Bonus Wallet - Goldmine",
                           "chineseTraditional" => "Bonus Wallet - Goldmine",
                           "vietnam" => "Bonus Wallet - Goldmine",
                           "malay" => "Bonus Wallet - Goldmine",
                           "japanese" => "Bonus Wallet - Goldmine",
                           "korean" => "Bonus Wallet - Goldmine"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );*/

    /*$creditDataAry[] = array(
        "name" => "bonusBonanza",
        "type" => "bonusCredit",
        "code" => "BC",
        "description" => "Bonus Credit",
        "image_name" => "bonusCredit.jpg",
        "display" => array("english" => "Bonus Wallet",
                           "chineseSimplified" => "Bonus Wallet",
                           "chineseTraditional" => "Bonus Wallet",
                           "vietnam" => "Bonus Wallet",
                           "malay" => "Bonus Wallet",
                           "japanese" => "Bonus Wallet",
                           "korean" => "Bonus Wallet"),
        "adminDisplay" => array("english" => "Bonus Wallet - Bonanza",
                           "chineseSimplified" => "Bonus Wallet - Bonanza",
                           "chineseTraditional" => "Bonus Wallet - Bonanza",
                           "vietnam" => "Bonus Wallet - Bonanza",
                           "malay" => "Bonus Wallet - Bonanza",
                           "japanese" => "Bonus Wallet - Bonanza",
                           "korean" => "Bonus Wallet - Bonanza"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );
    unset($creditSettingAry);*/

    /*$creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    $creditSettingAry["isWithdrawable"]         = array("value" => "2", "admin" => "0", "member" => "1", "description" => "bank=1,crypto=2,both=3");
    // $creditSettingAry["isTransferable"]         = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isConvertible"]          = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "100");
    // $creditSettingAry["convertTo"]              = array("value" => "vestCredit","admin" => "1", "member" => "1","reference"=> "1");
    $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    // $creditSettingAry["isDisplayOnTransaction"] = array("value" => "1", "member" => "1");
    // $creditSettingAry["isHoldingWallet"]        = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "usdtWallet");
    // $creditSettingAry["transferByTree"]          = array("value" => "sponsorTree");
    // $creditSettingAry["isBonusValueWallet"]     = array("value" => "1");
    $creditSettingAry["withdrawalAdminFee"]     = array("value" => "0.001","admin" => "1", "member" => "1", "type" => "amount"); //type amount/percentage
    // $creditSettingAry["minWithdrawal"]          = array("value" => "0.001");
    // $creditSettingAry["isFundinable"]           = array("value" => "1", "admin" => "0", "member" => "1","reference" => "BTC");
    // $creditSettingAry["withdrawalTypeFrom"]     = array("value" => "BTC");
    // $creditSettingAry["withdrawalTypeTo"]       = array("value" => "USDT");
    // $creditSettingAry["isP2PWallet"]           = array("value" => "1", "admin" => "1", "member" => "1");

    $creditDataAry[] = array(
        "name" => "productCredit",
        "type" => "productCredit",
        "code" => "PC",
        "description" => "Product Credit",
        "image_name" => "productCredit.jpg",
        "display" => array("english" => "Product Wallet",
                           "chineseSimplified" => "Product Wallet",
                           "chineseTraditional" => "Product Wallet",
                           "vietnam" => "Product Wallet",
                           "malay" => "Product Wallet",
                           "japanese" => "Product Wallet",
                           "korean" => "Product Wallet"),
        "adminDisplay" => array("english" => "Product Wallet",
                           "chineseSimplified" => "Product Wallet",
                           "chineseTraditional" => "Product Wallet",
                           "vietnam" => "Product Wallet",
                           "malay" => "Product Wallet",
                           "japanese" => "Product Wallet",
                           "korean" => "Product Wallet"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );
    unset($creditSettingAry);*/

    // $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    // $creditSettingAry["isWithdrawable"]         = array("value" => "2", "admin" => "0", "member" => "1", "description" => "bank=1,crypto=2,both=3");
    // $creditSettingAry["isTransferable"]         = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isConvertible"]          = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "100");
    // $creditSettingAry["convertTo"]              = array("value" => "vestCredit","admin" => "1", "member" => "1","reference"=> "1");
    // $creditSettingAry["isDisplayOnTransaction"] = array("value" => "1", "member" => "1");
    // $creditSettingAry["isHoldingWallet"]        = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "usdtWallet");
    // $creditSettingAry["transferByTree"]          = array("value" => "sponsorTree");
    // $creditSettingAry["isBonusValueWallet"]     = array("value" => "1");
    // $creditSettingAry["withdrawalAdminFee"]     = array("value" => "0.001","admin" => "1", "member" => "1", "type" => "amount"); //type amount/percentage
    // $creditSettingAry["minWithdrawal"]          = array("value" => "0.001");
    // $creditSettingAry["isFundinable"]           = array("value" => "1", "admin" => "0", "member" => "1","reference" => "BTC");
    // $creditSettingAry["withdrawalTypeFrom"]     = array("value" => "BTC");
    // $creditSettingAry["withdrawalTypeTo"]       = array("value" => "USDT");
    // $creditSettingAry["isP2PWallet"]           = array("value" => "1", "admin" => "1", "member" => "1");

    /*$creditDataAry[] = array(
        "name" => "creditWallet",
        "type" => "creditWallet",
        "code" => "CW",
        "description" => "Credit Wallet",
        "image_name" => "creditWallet.jpg",
        "display" => array("english" => "Credit Wallet",
                           "chineseSimplified" => "Credit Wallet",
                           "chineseTraditional" => "Credit Wallet",
                           "vietnam" => "Credit Wallet",
                           "malay" => "Credit Wallet",
                           "japanese" => "Credit Wallet",
                           "korean" => "Credit Wallet"),
        "adminDisplay" => array("english" => "Credit Wallet",
                           "chineseSimplified" => "Credit Wallet",
                           "chineseTraditional" => "Credit Wallet",
                           "vietnam" => "Credit Wallet",
                           "malay" => "Credit Wallet",
                           "japanese" => "Credit Wallet",
                           "korean" => "Credit Wallet"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );
    unset($creditSettingAry);*/

    // $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    // $creditSettingAry["isWithdrawable"]         = array("value" => "2", "admin" => "0", "member" => "1", "description" => "bank=1,crypto=2,both=3");
    // $creditSettingAry["isTransferable"]         = array("value" => "1", "admin" => "1", "member" => "1");
    // $creditSettingAry["isConvertible"]          = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "100");
    // $creditSettingAry["convertTo"]              = array("value" => "vestCredit","admin" => "1", "member" => "1","reference"=> "1");
    // $creditSettingAry["isDisplayOnTransaction"] = array("value" => "1", "member" => "1");
    // $creditSettingAry["isHoldingWallet"]        = array("value" => "1", "admin" => "1", "member" => "1", "reference" => "usdtWallet");
    // $creditSettingAry["transferByTree"]          = array("value" => "sponsorTree");
    // $creditSettingAry["isBonusValueWallet"]     = array("value" => "1");
    // $creditSettingAry["withdrawalAdminFee"]     = array("value" => "0.001","admin" => "1", "member" => "1", "type" => "amount"); //type amount/percentage
    // $creditSettingAry["minWithdrawal"]          = array("value" => "0.001");
    // $creditSettingAry["isFundinable"]           = array("value" => "1", "admin" => "0", "member" => "1","reference" => "BTC");
    // $creditSettingAry["withdrawalTypeFrom"]     = array("value" => "BTC");
    // $creditSettingAry["withdrawalTypeTo"]       = array("value" => "USDT");
    // $creditSettingAry["isP2PWallet"]           = array("value" => "1", "admin" => "1", "member" => "1");

    /*$creditDataAry[] = array(
        "name" => "withholdingGoldmine",
        "type" => "withholding",
        "code" => "WH",
        "description" => "Withholding",
        "image_name" => "withholding.jpg",
        "display" => array("english" => "Withholding Wallet",
                           "chineseSimplified" => "Withholding Wallet",
                           "chineseTraditional" => "Withholding Wallet",
                           "vietnam" => "Withholding Wallet",
                           "malay" => "Withholding Wallet",
                           "japanese" => "Withholding Wallet",
                           "korean" => "Withholding Wallet"),
        "adminDisplay" => array("english" => "Withholding Wallet - Goldmine",
                           "chineseSimplified" => "Withholding Wallet - Goldmine",
                           "chineseTraditional" => "Withholding Wallet - Goldmine",
                           "vietnam" => "Withholding Wallet - Goldmine",
                           "malay" => "Withholding Wallet - Goldmine",
                           "japanese" => "Withholding Wallet - Goldmine",
                           "korean" => "Withholding Wallet - Goldmine"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );

    $creditDataAry[] = array(
        "name" => "withholdingBonanza",
        "type" => "withholding",
        "code" => "WH",
        "description" => "Withholding",
        "image_name" => "withholding.jpg",
        "display" => array("english" => "Withholding Wallet",
                           "chineseSimplified" => "Withholding Wallet",
                           "chineseTraditional" => "Withholding Wallet",
                           "vietnam" => "Withholding Wallet",
                           "malay" => "Withholding Wallet",
                           "japanese" => "Withholding Wallet",
                           "korean" => "Withholding Wallet"),
        "adminDisplay" => array("english" => "Withholding Wallet - Bonanza",
                           "chineseSimplified" => "Withholding Wallet - Bonanza",
                           "chineseTraditional" => "Withholding Wallet - Bonanza",
                           "vietnam" => "Withholding Wallet - Bonanza",
                           "malay" => "Withholding Wallet - Bonanza",
                           "japanese" => "Withholding Wallet - Bonanza",
                           "korean" => "Withholding Wallet - Bonanza"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );
    unset($creditSettingAry);*/

    // **************************
    // ********  COIN ********
    // **************************

    /*$coinAry[] = array(
                            "name" => "trdBBIT",
                            "display" => array(
                                                    "english" => "BBIT",
                                                    "chineseSimplified" => "BBIT",
                                                    "chineseTraditional" => "BBIT",
                                                ),
                            "dcm" => 2,
                            "priority" => 1,
                            "setting" => array(
                                                    array("name" => "minPricePercentage", "value" => "5"),
                                                    array("name" => "maxPricePercentage", "value" => "20"),
                                                    array("name" => "dailyBuyLimit", "value" => "500"),
                                                    array("name" => "isBuyReduce", "value" => "0"),
                                                    array("name" => "isBuyCancel", "value" => "1"),
                                                    array("name" => "isSellReduce", "value" => "0"),
                                                    array("name" => "isSellCancel", "value" => "0"),
                                                    array("name" => "timeBuyLimit", "value" => "1#1000"),
                                                    array("name" => "timeBuyLimit", "value" => "7#2000"),
                                                    array("name" => "timeBuyLimit", "value" => "14#3000"),
                                                    array("name" => "timeBuyLimit", "value" => "21#0"),
                                                ),
                            "payment" => array(
                                                   array('type' => 'buy', 'coin_type' => 'trdBBIT', 'pay_type'  => 'usdtTrd', 'receive_type' => 'quantCredit', 'admin_charge' => '0.2', 'charge_type' => "usdtTrd"),
                                                   array('type' => 'sell', 'coin_type' => 'trdBBIT', 'pay_type'  => 'quantCredit', 'receive_type' => 'usdtTrd', 'admin_charge' => '0.2', 'charge_type' => "usdtTrd"), 
                                                ),
                        );*/

    
    // **************************
    // ********  PRODUCT ********
    // **************************

    /*$productDataAry[] = array(
        "name" => "package 1",
        "code" => "001",
        "display" => array(
                            "english" => "VIG",
                            "chineseSimplified" => "VIG",
                            "chineseTraditional" => "VIG"
                        ),
        "priority" => "1",
        "category" => "package",
        "price" => "300",
        "status" => "Active",
        "image_name" => "P001.jpg",
        "setting" => array(
                "isProduct" => array("value"=>1,"type"=>"Product Type","description"=>"Setting for filters"),
                "maxParticipateLimit" => array("value"=>6,"type"=>"Product Limit Setting","description"=>"Maximum for participate"),
                "minAmount" => array("value"=>280,"type"=>"Product Setting","description"=>"Minimum amount"),
                "maxAmount" => array("value"=>600,"type"=>"Product Setting","description"=>"Maximum amount"),
        )
    );*/

    /*$productDataAry[] = array(
        "name" => "package 2",
        "code" => "002",
        "display" => array(
                            "english" => "VIP",
                            "chineseSimplified" => "VIP",
                            "chineseTraditional" => "VIP"
                        ),
        "priority" => "2",
        "category" => "package",
        "price" => "500",
        "status" => "Active",
        "image_name" => "P002.jpg",
        "setting" => array(
                "isProduct" => array("value"=>1,"type"=>"Product Type","description"=>"Setting for filters"),
                "maxParticipateLimit" => array("value"=>6,"type"=>"Product Limit Setting","description"=>"Maximum for participate"),
                "minAmount" => array("value"=>480,"type"=>"Product Setting","description"=>"Minimum amount"),
                "maxAmount" => array("value"=>1000,"type"=>"Product Setting","description"=>"Maximum amount"),
        )
    );*/

    /*$productDataAry[] = array(
        "name" => "package 3",
        "code" => "003",
        "display" => array(
                            "english" => "VVIP",
                            "chineseSimplified" => "VVIP",
                            "chineseTraditional" => "VVIP"
                        ),
        "priority" => "3",
        "category" => "package",
        "price" => "1000",
        "status" => "Active",
        "image_name" => "P003.jpg",
        "setting" => array(
                "isProduct" => array("value"=>1,"type"=>"Product Type","description"=>"Setting for filters"),
                "maxParticipateLimit" => array("value"=>6,"type"=>"Product Limit Setting","description"=>"Maximum for participate"),
                "minAmount" => array("value"=>980,"type"=>"Product Setting","description"=>"Minimum amount"),
                "maxAmount" => array("value"=>2000,"type"=>"Product Setting","description"=>"Maximum amount"),
        )
    );*/

    $payArray[] = array(
        "credit_type" => "mfizCredit",
        "status" => "Active",
        "payment_type" => "Purchase Package",
        "min_percentage" => "0",
        "max_percentage" => "100",
    );

    /*$payArray[] = array(
        "credit_type" => "creditWallet",
        "status" => "Active",
        "payment_type" => "Package Reentry",
        "min_percentage" => "0",
        "max_percentage" => "100",
    );*/

    /*$bonusArray[] = array(
                        "name" => "rebateBonus",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Instant",
                        "payment" => "Instant",
                        "priority" => "1",
                        "table_name" => "mlm_bonus_rebate",
                        "allow_rank_maintain" => "0",
                        "disabled" => "0",
                        'display' => array(
                                            'english' => 'Rebate Bonus',
                                            'chineseSimplified' => '每日分红',
                                            'chineseTraditional' => '每日分紅',
                                            "vietnam" => "Cổ tức hàng ngày",
                                            "malay" => "Bonus Rebate",
                                            "japanese" => "毎日の配当",
                                            "korean" => "일일 배당"
                                        ),
                        "type" => "mlm",
                        "paymentMethod" => array(
                                                array("percentage" => "100","credit_type" => "rewardRebate", "description" => "Rebate Bonus Payout"),
                                            ),
                    );*/

    $bonusArray[] = array(
                        "name" => "goldmineBonus",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Monthly",
                        "payment" => "Monthly",
                        "priority" => "1",
                        "table_name" => "mlm_bonus_goldmine",
                        "disabled" => "0",
                        "allow_rank_maintain" => "1",
                        'display' => array(
                                            'english' => 'Goldmine Bonus',
                                            'chineseSimplified' => 'Goldmine Bonus',
                                            'chineseTraditional' => 'Goldmine Bonus',
                                            "vietnam" => "Goldmine Bonus",
                                            "malay" => "Goldmine Bonus",
                                            "japanese" => "Goldmine Bonus",
                                            "korean" => "Goldmine Bonus"
                                        ),
                        "type" => "mlm",
                        "setting" => array(
                                            array("name" => "breakOutRank","value" => "3", "type" => "Bonus Setting", "reference" => "", "description" => "Break Out Rank Priority."),
                                            array("name" => "includeOwnSalesRank","value" => "4", "type" => "Bonus Setting", "reference" => "", "description" => "Value = Rank Priority, above this Rank Priority will include own sales."),
                                        ),
                        /*"paymentMethod" => array(
                                                array("percentage" => "100","credit_type" => "bonusGoldmine", "description" => "Goldmine Bonus Payout"),
                                            ),*/
                    );

    $bonusArray[] = array(
                        "name" => "teamBonus",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Monthly",
                        "payment" => "Monthly",
                        "priority" => "2",
                        "table_name" => "mlm_bonus_team",
                        "disabled" => "0",
                        "allow_rank_maintain" => "1",
                        'display' => array(
                                            'english' => 'Team Bonus',
                                            'chineseSimplified' => 'Team Bonus',
                                            'chineseTraditional' => 'Team Bonus',
                                            "vietnam" => "Team Bonus",
                                            "malay" => "Team Bonus",
                                            "japanese" => "Team Bonus",
                                            "korean" => "Team Bonus"
                                        ),
                        "type" => "mlm",
                        "setting" => array(
                                            array("name" => "skipFirstGen","value" => "2", "type" => "Bonus Setting", "reference" => "", "description" => "Skip first generation valid upline for certain rank priority(will pass to next valid upline)."),
                                            array("name" => "spPercentage","value" => "2", "type" => "Bonus Setting", "reference" => "4", "description" => "Special Percentage Pass up based on From User Rank Priority(Reference)."),
                                        ),
                        /*"paymentMethod" => array(
                                                array("percentage" => "100","credit_type" => "bonusGoldmine", "description" => "Goldmine Bonus Payout"),
                                            ),*/
                    );

    $bonusArray[] = array(
                        "name" => "leadershipBonus",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Monthly",
                        "payment" => "Monthly",
                        "priority" => "3",
                        "table_name" => "mlm_bonus_leadership",
                        "disabled" => "0",
                        "allow_rank_maintain" => "1",
                        'display' => array(
                                            'english' => 'Leadership Bonus',
                                            'chineseSimplified' => 'Leadership Bonus',
                                            'chineseTraditional' => 'Leadership Bonus',
                                            "vietnam" => "Leadership Bonus",
                                            "malay" => "Leadership Bonus",
                                            "japanese" => "Leadership Bonus",
                                            "korean" => "Leadership Bonus"
                                        ),
                        "type" => "mlm",
                        "setting" => array(
                                            array("name" => "leadershipBonusPercentage","value" => "12", "type" => "Level", "reference" => "1", "description" => "Bonus Percentage Base on Level."),
                                            array("name" => "leadershipBonusPercentage","value" => "5", "type" => "Level", "reference" => "2", "description" => "Bonus Percentage Base on Level."),
                                            array("name" => "leadershipBonusPercentage","value" => "2", "type" => "Level", "reference" => "3", "description" => "Bonus Percentage Base on Level."),
                                            array("name" => "leadershipBonusPercentage","value" => "1", "type" => "Level", "reference" => "4", "description" => "Bonus Percentage Base on Level."),
                                            array("name" => "leadershipBonusPercentage","value" => "0.25", "type" => "Level", "reference" => "5", "description" => "Bonus Percentage Base on Level."),
                                            array("name" => "isNoLevelLimit","value" => "0.25", "type" => "Bonus Setting", "reference" => "fizUnicorn", "description" => "No Level Limit for this rank"),
                                            array("name" => "levelBreak", "value" => "fizUnicorn", "type" => "Bonus Setting", "reference" => "fizUnicorn", "description" => "Stop array loop after reach the level"),
                                        ),
                        // "paymentMethod" => array(
                        //                         array("percentage" => "100","credit_type" => "bonusBonanza", "description" => "Bonanza Bonus Payout"),
                        //                     ),
                    );

    $bonusArray[] = array(
                        "name" => "awardBonus",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Monthly",
                        "payment" => "Monthly",
                        "priority" => "4",
                        "table_name" => "mlm_bonus_award",
                        "disabled" => "0",
                        "allow_rank_maintain" => "0",
                        'display' => array(
                                            'english' => 'Cash Award Bonus',
                                            'chineseSimplified' => 'Cash Award Bonus',
                                            'chineseTraditional' => 'Cash Award Bonus',
                                            "vietnam" => "Cash Award Bonus",
                                            "malay" => "Cash Award Bonus",
                                            "japanese" => "Cash Award Bonus",
                                            "korean" => "Cash Award Bonus"
                                        ),
                        "type" => "mlm",
                        "setting" => array(
                                            array("name" => "directorAward","value" => "12000000", "type" => "Bonus Setting", "reference" => "4#4#3000", "description" => "Value = Payout Amount, Reference = (Min Rank Priority) # (Entitle Count)"),
                                            array("name" => "unicornAward","value" => "30000000", "type" => "Bonus Setting", "reference" => "5#4#3000", "description" => "Value = Payout Amount, Reference = (Min Rank Priority) # (Entitle Count)"),
                                        ),
                        /*"paymentMethod" => array(
                                                array("percentage" => "100","credit_type" => "bonusGoldmine", "description" => "Goldmine Bonus Payout"),
                                            ),*/
                    );

    // START GENERATE BY THE SETUP SETTING - STOP EDITING

    ##### Bonus Rank Calcution setting #####
    $rankArray[] = array(
        "name" => "member",
        "priority" => "1",
        "type" => "Bonus Tier",
        "display" => array(
                            "english" => "Member",
                            "chineseSimplified" => "会员",
                            "chineseTraditional" => "會員",
                        ),
        "setting" => array(
                            "minOwnSales" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Own Sales"),
                            "minPGPSales" => array("value" => "0", "type" => "purchase", "reference" => "3", "description" => "Minimum Group Sales (include own sales, Exclude executive and Above rank.)"),
                            "minGroupSales" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Group Sales (include own sales)"),
                            "minActiveLeg" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Active Direct Downline"),
                            "minFirstDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum First Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "minSecDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum Second Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "discountPercentage" => array("value" => "0", "type" => "percentage", "reference" => "0", "description" => "Discount Percentage."),
                            "goldmineBonusPercentage" => array("value" => "0", "type" => "percentage", "reference" => "3", "description" => "Goldmine bonus percentage. Reference = Level Limit(0 = no limit)."),
                            "teamBonusPercentage" => array("value" => "0", "type" => "percentage", "reference" => "", "description" => "Team bonus percentage."),
                            "leadershipBonusPercentage" => array("value" => "0", "type" => "level", "reference" => "0", "description" => "Leadership bonus percentage. Reference = isNoLevelLimit"),
                        )
    );

    $rankArray[] = array(
        "name" => "fizEntreprenuer",
        "priority" => "2",
        "type" => "Bonus Tier",
        "display" => array(
                            "english" => "Fiz Entreprenuer",
                            "chineseSimplified" => "Fiz Entreprenuer",
                            "chineseTraditional" => "Fiz Entreprenuer",
                        ),
        "setting" => array(
                            "minOwnSales" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Own Sales"),
                            "minPGPSales" => array("value" => "0", "type" => "purchase", "reference" => "3", "description" => "Minimum Group Sales (include own sales, Exclude executive and Above rank.)"),
                            "minGroupSales" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Group Sales (include own sales)"),
                            "minActiveLeg" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Active Direct Downline"),
                            "minFirstDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum First Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "minSecDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum Second Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "discountPercentage" => array("value" => "25", "type" => "percentage", "reference" => "0", "description" => "Discount Percentage."),
                            "goldmineBonusPercentage" => array("value" => "0", "type" => "percentage", "reference" => "3", "description" => "Goldmine bonus percentage. Reference = Level Limit(0 = no limit)."),
                            "teamBonusPercentage" => array("value" => "0", "type" => "percentage", "reference" => "", "description" => "Team bonus percentage."),
                            "leadershipBonusPercentage" => array("value" => "0", "type" => "level", "reference" => "0", "description" => "Leadership bonus percentage. Reference = isNoLevelLimit"),
                        )
    );

    $rankArray[] = array(
        "name" => "fizExecutive",
        "priority" => "3",
        "type" => "Bonus Tier",
        "display" => array(
                            "english" => "Fiz Executive",
                            "chineseSimplified" => "Fiz Executive",
                            "chineseTraditional" => "Fiz Executive",
                        ),
        "setting" => array(
                            "minOwnSales" => array("value" => "100", "type" => "purchase", "reference" => "", "description" => "Minimum Own Sales"),
                            "minPGPSales" => array("value" => "1000", "type" => "purchase", "reference" => "3", "description" => "Minimum Group Sales (include own sales, Exclude executive and Above rank.)"),
                            "minGroupSales" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Group Sales (include own sales)"),
                            "minActiveLeg" => array("value" => "2", "type" => "purchase", "reference" => "", "description" => "Minimum Active Direct Downline"),
                            "minFirstDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum First Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "minSecDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum Second Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "discountPercentage" => array("value" => "25", "type" => "percentage", "reference" => "0", "description" => "Discount Percentage."),
                            "goldmineBonusPercentage" => array("value" => "7", "type" => "percentage", "reference" => "3", "description" => "Goldmine bonus percentage. Reference = Level Limit(0 = no limit)."),
                            "teamBonusPercentage" => array("value" => "8", "type" => "percentage", "reference" => "", "description" => "Team bonus percentage."),
                            "leadershipBonusPercentage" => array("value" => "0", "type" => "level", "reference" => "0", "description" => "Leadership bonus percentage. Reference = isNoLevelLimit"),
                        )
    );

    $rankArray[] = array(
        "name" => "fizDirector",
        "priority" => "4",
        "type" => "Bonus Tier",
        "display" => array(
                            "english" => "Fiz Director",
                            "chineseSimplified" => "Fiz Director",
                            "chineseTraditional" => "Fiz Director",
                        ),
        "setting" => array(
                            "minOwnSales" => array("value" => "200", "type" => "purchase", "reference" => "", "description" => "Minimum Own Sales"),
                            "minPGPSales" => array("value" => "2000", "type" => "purchase", "reference" => "3", "description" => "Minimum Group Sales (include own sales, Exclude executive and Above rank.)"),
                            "minGroupSales" => array("value" => "0", "type" => "purchase", "reference" => "", "description" => "Minimum Group Sales (include own sales)"),
                            "minActiveLeg" => array("value" => "6", "type" => "purchase", "reference" => "", "description" => "Minimum Active Direct Downline"),
                            "minFirstDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum First Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "minSecDownlineRank" => array("value" => "0", "type" => "purchase", "reference" => "0", "description" => "Minimum Second Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "discountPercentage" => array("value" => "25", "type" => "percentage", "reference" => "0", "description" => "Discount Percentage."),
                            "goldmineBonusPercentage" => array("value" => "10", "type" => "percentage", "reference" => "0", "description" => "Goldmine bonus percentage. Reference = Level Limit(0 = no limit)."),
                            "teamBonusPercentage" => array("value" => "8", "type" => "percentage", "reference" => "", "description" => "Team bonus percentage."),
                            "leadershipBonusPercentage" => array("value" => "2", "type" => "level", "reference" => "0", "description" => "Leadership bonus percentage. Reference = isNoLevelLimit"),
                        )
    );

    $rankArray[] = array(
        "name" => "fizUnicorn",
        "priority" => "5",
        "type" => "Bonus Tier",
        "display" => array(
                            "english" => "Fiz Unicorn",
                            "chineseSimplified" => "Fiz Unicorn",
                            "chineseTraditional" => "Fiz Unicorn",
                        ),
        "setting" => array(
                            "minOwnSales" => array("value" => "200", "type" => "purchase", "reference" => "", "description" => "Minimum Own Sales"),
                            "minPGPSales" => array("value" => "2000", "type" => "purchase", "reference" => "3", "description" => "Minimum Group Sales (include own sales, Exclude executive and Above rank.)"),
                            "minGroupSales" => array("value" => "50000", "type" => "purchase", "reference" => "", "description" => "Minimum Group Sales (include own sales)"),
                            "minActiveLeg" => array("value" => "6", "type" => "purchase", "reference" => "", "description" => "Minimum Active Direct Downline"),
                            "minFirstDownlineRank" => array("value" => "4", "type" => "purchase", "reference" => "6", "description" => "Minimum First Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "minSecDownlineRank" => array("value" => "4", "type" => "purchase", "reference" => "4", "description" => "Minimum Second Generation Downline Rank, Value = Rank Priority Reference = Min Downline"),
                            "discountPercentage" => array("value" => "25", "type" => "percentage", "reference" => "0", "description" => "Discount Percentage."),
                            "goldmineBonusPercentage" => array("value" => "10", "type" => "percentage", "reference" => "0", "description" => "Goldmine bonus percentage. Reference = Level Limit(0 = no limit)."),
                            "teamBonusPercentage" => array("value" => "8", "type" => "percentage", "reference" => "", "description" => "Team bonus percentage."),
                            "leadershipBonusPercentage" => array("value" => "5", "type" => "level", "reference" => "1", "description" => "Leadership bonus percentage. Reference = isNoLevelLimit"),
                        )
    );

    echo "Deleting old Project credits related permissions..\n";
    $db->rawQuery("DELETE FROM permissions WHERE (description IN ('Credit Type Page', 'Credit Transfer Page', 'Credit Adjustment Page', 'Credit Withdrawal Page', 'Credit Transfer Confirmation Page') OR description LIKE '% Transaction Listing Page') AND type != 'hidden'");

    echo "Deleting  old Project bonus report related permissions..\n";
    $db->rawQuery("DELETE FROM permissions WHERE description LIKE '%Bonus Report Listing' AND type NOT IN ('hidden', 'menu')");

    if(count($permissionIDArray) > 0){
        foreach($permissionIDArray as $pItem){
            $pIDArray[] = $pItem['id'];
        }

        $db->where('id', $pIDArray, 'IN');
        $db->delete('permissions');
        $db->where('parent_id', $pIDArray, 'IN');
        $db->delete('permissions');
    }

    echo "Unable old Project language and enable default language..\n";
    $db->rawQuery("UPDATE `languages` SET `disabled` = '1' WHERE `language` NOT IN ('english', 'chineseSimplified')");
    $db->rawQuery("UPDATE `languages` SET `disabled` = '0' WHERE `language` = 'indonesian'");

    echo "Deleting  old Project Credit/Product Languages..\n";
    $db->rawQuery("DELETE FROM language_translation WHERE code LIKE 'C%'");
    $db->rawQuery("DELETE FROM language_translation WHERE code LIKE 'P%'");
    $db->rawQuery("DELETE FROM language_translation WHERE code LIKE 'S%'");
    $db->rawQuery("DELETE FROM language_translation WHERE code LIKE 'N%'");
    $db->rawQuery("DELETE FROM language_translation WHERE code LIKE 'R%'");
    $db->rawQuery("DELETE FROM language_translation WHERE code LIKE 'W%'");

    foreach($creditDataAry AS $creditData){
    	// echo date("Y-m-d H:i:s")." inserting credit: ".$creditData['name']."\n";
    	// exist credit will be skipped
    	$return = $function->addNewWallet($creditData);
    	if($return) echo date("Y-m-d H:i:s")." ".$creditData['name']." (success)\n";
    }

    echo "--------------------------\n";

    echo "Inserting coin...\n";
    foreach ($coinAry as &$coinData) {
        $coinData['name'] = $coinData['name'];
        $coinSetting = $coinData['setting'];
        $coinPayment = $coinData['payment'];
        unset($coinData['setting']);
        unset($coinData['payment']);

        $coinData['translation_code'] = $function->generateDynamicCode("C");
        foreach($coinData['display'] AS $language=>$msg){
            $insertDisplay = array(
                "code" => $coinData["translation_code"],
                "module" => "Coin",
                "language" => $language,
                "site" => "System",
                "type" => "Dynamic",
                "content" => $msg,
                "created_at" => $db->now()
            );
            $db->insert("language_translation", $insertDisplay);
        } 
        unset($coinData['display']);
        $coinID = $db->insert("trd_coin", $coinData);

        foreach($coinSetting as $settingRow){
            $insert = $settingRow;
            $insert['coin_id'] = $coinID;
            $db->insert("trd_coin_setting", $insert);
        }

        foreach($coinPayment as $key => $coinPaymentRow){
            $db->insert("trd_payment_method", $coinPaymentRow);
        }
    }

    echo "--------------------------\n";
    
    /*echo "Inserting product..\n";
    foreach($productDataAry AS &$productData){
    	$productData['name'] = strtolower($productData['name']);
    	$productData['created_at'] = $db->now();
    	$productSetting = $productData['setting'];
    	unset($productData['setting']);

    	$productData['translation_code'] = $function->generateDynamicCode("P");
    	foreach($productData['display'] AS $language=>$msg){
        	$insertDisplay = array(
        		"code" => $productData["translation_code"],
        		"module" => "Product",
        		"language" => $language,
        		"site" => "System",
        		"type" => "Dynamic",
        		"content" => $msg,
        		"created_at" => $db->now()
        	);
        	$db->insert("language_translation", $insertDisplay);
        } unset($productData['display']);

    	$productID = $db->insert("mlm_product", $productData);

    	foreach($productSetting AS $name => $setting){
            unset($insert);
            foreach($setting as $settingRow){
                if(is_array($settingRow)){
                    $insert = $settingRow;
                    $insert['name'] = $name;
                    $insert['product_id'] = $productID;
                    $db->insert("mlm_product_setting", $insert);
                }else{
                    $insert = $setting;
                    $insert['name'] = $name;
                    $insert['product_id'] = $productID;
                    $db->insert("mlm_product_setting", $insert);
                    break;
                }
            }
    	}
    }*/

    // mlm_payment_method
	// id	credit_type	status	min_percentage	max_percentage	payment_type	created_at

    foreach($payArray AS $insertPayment){
    	$insertPayment['created_at'] = $db->now();
    	$db->insert("mlm_payment_method", $insertPayment);
    }
    
    echo "Get bonus permissions ID...\n";
    $db->where("name", "Bonus Report");
    $bonusID = $db->getValue("permissions", "id");

    echo "Inserting bonuses...\n";
    foreach($bonusArray as $bonus){
        $bonus['language_code'] = $function->generateDynamicCode("S");
        foreach($bonus['display'] AS $language=>$msg){
            $insertDisplay = array(
                "code" => $bonus["language_code"],
                "module" => "Bonus",
                "language" => $language,
                "site" => "System",
                "type" => "Dynamic",
                "content" => $msg,
                "created_at" => $db->now()
            );
            $db->insert("language_translation", $insertDisplay);
        }

        $bonusSetting = $bonus["setting"];
        $bonusPayment = $bonus["paymentMethod"];

        unset($bonus['display']);
        unset($bonus["setting"]);
        unset($bonus["paymentMethod"]);
        $bonus["id"] = $db->insert("mlm_bonus", $bonus);
        
		$mainPermissions = array(
			"name" => $bonus["name"],
			"description" => $bonus["name"]." Bonus Report Listing",
			"type" => "Sub Menu",
			"parent_id" => $bonusID,
			"file_path" => $bonus["name"]."Report.php",
			"priority" => ($bonus["priority"]+1),
            "translation_code" => $bonus['language_code'],
			"site" => "Admin",
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
			"reference_table" => "mlm_bonus",
			"reference_id" => $bonus["id"],
		);

        $db->insert("permissions", $mainPermissions);

        foreach ($bonusSetting as $bonusSettingValue) {
            $bonusSettingValue["bonus_id"] = $bonus["id"];
            $db->insert("mlm_bonus_setting", $bonusSettingValue);
        }
        foreach($bonusPayment as $key => $bonusPaymentRow){
            $bonusPaymentRow["bonus_id"] = $bonus["id"];
            $db->insert("mlm_bonus_payment_method", $bonusPaymentRow);
        }
    }

    echo "Inserting rank..\n";
    foreach($rankArray AS $rankData){
        $rankData['name'] = $rankData['name'];
        $rankData['created_at'] = $db->now();
        $rankSetting = $rankData['setting'];
        unset($rankData['setting']);

        $rankData['translation_code'] = $function->generateDynamicCode("R");
        foreach($rankData['display'] AS $language=>$msg){
            $insertDisplay = array(
                "code" => $rankData["translation_code"],
                "module" => "Rank",
                "language" => $language,
                "site" => "System",
                "type" => "Dynamic",
                "content" => $msg,
                "created_at" => $db->now()
            );
            $db->insert("language_translation", $insertDisplay);
        }

        unset($rankData['display']);
        $rankID = $db->insert("rank", $rankData);

        foreach($rankSetting AS $name=>$setting){
            $insert = $setting;
            $insert['name'] = $name;
            $insert['rank_id'] = $rankID;
            $db->insert("rank_setting", $insert);
        }
    }
    // echo "Inserting rank setting...\n";
    // foreach ($rankMethod as $rankSettingValue) {
    //         $db->insert("rank_setting", $rankSettingValue);
    //  }

    /*echo "Inserting category...\n";
    foreach ($categoryAry as $categoryRow) {
        unset($insertData);
        $translationCode = $function->generateDynamicCode("W");
        foreach($categoryRow['display'] AS $language=>$msg){
            $insertDisplay = array(
                "code" => $translationCode,
                "module" => "Product Category",
                "language" => $language,
                "site" => "Inventory",
                "type" => "Dynamic",
                "content" => $msg,
                "created_at" => $db->now()
            );
            $db->insert("language_translation", $insertDisplay);
        }
        $insertData = array(
                                "name" => $categoryRow["name"],
                                "translation_code" => $translationCode,
                                "created_at" => $db->now(),
                            );
        $db->insert("inv_category", $insertData);
     }*/

    // update delivery country
    echo "Update Delivery Country";

    $db->rawQuery("UPDATE `country` SET `delivery_country` = '0'");
    $db->rawQuery("UPDATE `country` SET `delivery_country` = '1' WHERE `name` IN ('Indonesia')");

    // **************************
    // *****  EXTRA INSERT ******
    // **************************

     // Set default system decimal place
    $db->rawQuery("UPDATE `system_settings` SET `value` = '2' WHERE name = 'systemDecimalFormat'");
    $db->rawQuery("UPDATE `system_settings` SET `value` = '2' WHERE name = 'internalDecimalFormat'");
    $db->rawQuery("UPDATE `system_settings` SET `value` = '1' WHERE name = 'isFloatExtraDecimal'");

    //Set Maximun Place Position
    $db->rawQuery("UPDATE `system_settings` SET `value` = '2' WHERE name = 'maxPlacementPositions'");
    $db->rawQuery("UPDATE `system_settings` SET `value` = '8' WHERE name = 'maxOctopusPositions'");
    $db->rawQuery("UPDATE `system_settings` SET `value` = 'd/m/Y H:i:s' WHERE name = 'systemDateTimeFormat'");

    //Set check_duplicate_interval to 1 if more than 0
    $db->rawQuery("UPDATE `api` SET `check_duplicate_interval` = '1' WHERE check_duplicate_interval > 0 AND site = 'Admin'");

    //Set Crypto Coin Type
    $db->rawQuery("UPDATE `system_settings` SET `reference` = '1', `value` = 'tether' WHERE name = 'cryptoCoinType'");

    // $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'selfCollectAddress', '上海市金山区廊下镇景乐路 228号 7栋 D592室', '', '', '', 'Standard Platform');");

    // $db->rawQuery("DELETE FROM `permissions` WHERE name =  'Coin Wallet'");

    // **********************************
    // *****  INSERT Cryto Setting ******
    // **********************************
   $cryptoSetting[] = array(
                                    'coin_type' => 'USDT',
                                    'name'      => 'payCreditType',
                                    'value'     => 'usdtWallet',
                                ); 

    foreach ($cryptoSetting as $setting) {
        $db->insert('mlm_crypto_setting', $setting);        
    }


    // ***************************************
    // **********  INSERT Provider ***********
    // ***************************************

    // $providerAry[] = array(
    //                         'company'           => 'BITBUY',
    //                         'name'              => 'phone',
    //                         'username'          => '6586485817',
    //                         'password'          => 'BBIT1357',
    //                         'api_key'           => '65c19d9db324e9fda44ff3b32aad3c8f',
    //                         'type'              => 'phone',
    //                         'priority'          => '0',
    //                         'disabled'          => '0',
    //                         'deleted'           => '0',
    //                         'default_sender'    => '',
    //                         'url1'              => 'https://www.smss360.com/api/sendsms.php?',
    //                         'url2'              => '',
    //                         'remark'            => '',
    //                         'balance'           => '0.00000000',
    //                         'created_at'        => $db->now(),
    //                         'updated_at'        => '',
    //                     );

    // foreach ($providerAry as $provider) {
    //     $db->insert('provider', $provider);        
    // }

    // *************************************
    // *******  Add Admin Permission *******
    // *************************************
    $db->rawQuery("UPDATE permissions SET disabled = '0', master_disabled = '0' WHERE name = 'Set Main Leader' AND file_path = 'setLeader.php'");

    // Inventory
    // Add Category, Edit Category
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Category', 'Category', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') t), 'getCategoryList.php', '',  '1', '', 'A01575', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Category', 'Add Category', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Category' AND type = 'Sub Menu') t), 'addCategoryDetails.php', '',  '', '', 'A01576', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit Category', 'Edit Category', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Category' AND type = 'Sub Menu') t), 'editCategoryDetails.php', '',  '', '', 'A01577', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Add Package, Edit Package
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Package', 'Package', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') t), 'getPackageList.php', '',  '2', '', 'A01578', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Package', 'Add Package', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Package' AND type = 'Sub Menu') t), 'addPackageDetails.php', '',  '', '', 'A01579', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit Package', 'Edit Package', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Package' AND type = 'Sub Menu') t), 'editPackageDetails.php', '',  '', '', 'A01580', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Package Adjustment', 'Package Adjustment', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Package') AS t), 'packageAdjustment.php', '2', (SELECT MAX(priority)+1 FROM permissions AS b), '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");

    // Add, Edit Starter Package
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Starter Package', 'Starter Package', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') t), 'getStarterPackageList.php', '',  '2', '', 'A01611', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Starter Package', 'Add Starter Package', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Starter Package' AND type = 'Sub Menu') t), 'addStarterPackageDetails.php', '',  '', '', 'A01612', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit Starter Package', 'Edit Starter Package', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Starter Package' AND type = 'Sub Menu') t), 'editStarterPackageDetails.php', '',  '', '', 'A01613', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Add Product, Edit Product
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Product', 'Product', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'getProductInventory.php', '',  '3', '', 'A01581', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Product', 'Add Product', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Product' AND type = 'Sub Menu') AS t), 'addProductInventory.php', '',  '', '', 'A01582', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit Product', 'Edit Product', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Product' AND type = 'Sub Menu') AS t), 'editProductInventory.php', '',  '', '', 'A01583', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    //Memo/News/Upload 
    //Banner
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Banner', 'Banner', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Memo/News/Upload' AND description = 'Pop up memo, announcement and document upload menu') AS t), 'getBannerList.php', '1',  '4', '', 'A01593', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Banner', 'Add Banner', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Banner' AND type = 'Sub Menu') AS t), 'addBanner.php', '',  '', '', 'A01594', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit Banner', 'Edit Banner', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Banner' AND type = 'Sub Menu') AS t), 'editBanner.php', '',  '', '', 'A01595', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Remove Banner', 'Remove Banner', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Banner' AND type = 'Sub Menu') AS t), 'removeBanner.php', '',  '', '', 'A01596', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Get Banner', 'Get Banner', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Banner' AND type = 'Sub Menu') AS t), 'getBanner.php', '',  '', '', 'A01597', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

     //E-Catalogue
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'E-Catalogue', 'E-Catalogue', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Memo/News/Upload' AND type = 'Menu') AS t), 'getECatalogueList.php', '1', (SELECT MAX(priority)+1 FROM permissions AS b), '', 'A01598', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add E-Catalogue', 'Add E-Catalogue', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'E-Catalogue' AND type = 'Sub Menu') AS t), 'addECatalogue.php', '2',  '', '', 'A01599', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit E-Catalogue', 'Edit E-Catalogue', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'E-Catalogue' AND type = 'Sub Menu') AS t), 'editECatalogue.php', '2',  '', '', 'A01600', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Set Taxes
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Set Taxes', 'Set Taxes', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'setTaxes.php', '0', '4', 'zmdi-collection-text', 'A01532', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Delivery Charges
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Delivery Charges Setting', 'Delivery Charges Setting', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'updateDeliveryCharges.php', '0', '5', 'zmdi-collection-text', 'A01533', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Delivery Charges Listing', 'Delivery Charges Listing', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'getDeliveryChargesListing.php', '0',  '6', 'zmdi-collection-text', 'A01534', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Delivery Order
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Delivery Order Listing', 'Delivery Order Listing', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'getDeliveryOrderListing.php', '0', '7', 'zmdi-collection-text', 'A01535', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Delivery Order Details', 'Delivery Order Details', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'getDeliveryOrderDetails.php', '0', '', 'zmdi-collection-text', 'A01536', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Admin Order Listing
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Admin Order Listing', 'Admin Order Listing', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') AS t), 'getAdminOrderListing.php', '1', (SELECT MAX(b.`priority`)+1 FROM `permissions` AS b WHERE b.`parent_id` = (SELECT e.`id` FROM `permissions` AS e WHERE e.`name` = 'Inventory')), 'zmdi-collection-text', 'A01608', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Admin Order Detail', 'Admin Order Detail', 'Page', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'Admin Order Listing'), 'getAdminOrderDetail.php', '2', '1', 'zmdi-collection-text', 'A01609', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    //Address Listing
    $db->rawQuery("INSERT INTO `permissions`(`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('Address Listing', 'Address Listing', 'Menu', '', 'getAddressList.php', '0', '36', '', 'A01588', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    //PVP Listing
    $db->rawQuery("INSERT INTO `permissions`(`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('PVP Listing', 'PVP Listing', 'Sub Menu', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Inventory') t) , 'getPVPList.php', '1', '11', '', 'A01589', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");

    // Product Adjustment
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Stock', 'Product Adjustment', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Product') AS t), 'invProductAdjustment.php', '0', (SELECT MAX(priority)+1 FROM permissions AS b), '', 'A01538', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Product Transaction', 'Product Transaction', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Product') AS t), 'invProductTransaction.php', '0', (SELECT MAX(priority)+1 FROM permissions AS b), '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Stock Detail', 'Stock Detail', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Product') AS t), 'invStockDetail.php', '0', (SELECT MAX(priority)+1 FROM permissions AS b), '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Stock Transaction', 'Stock Transaction', 'Page', (SELECT * FROM (SELECT id FROM `permissions` WHERE name = 'Stock Detail') AS t), 'invStockTransaction.php', '0', '1', '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Stock Adjustment', 'Stock Adjustment', 'Page', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'Stock Detail'), 'invStockAdjustment.php', '3', (SELECT MAX(b.`priority`)+1 FROM `permissions` AS b WHERE b.`parent_id` = (SELECT e.`id` FROM `permissions` AS e WHERE e.`name` = 'Stock Detail')), '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    
    // Supplier
    $db->rawQuery("INSERT INTO `permissions`(`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Supplier', 'Supplier', 'Sub Menu', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'Inventory') , 'supplier.php', '1', (SELECT MAX(b.`priority`)+1 FROM `permissions` AS b WHERE b.`parent_id` = (SELECT e.`id` FROM `permissions` AS e WHERE e.`name` = 'Inventory')), '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Add Supplier', 'Add Supplier', 'Page', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'supplier'), 'addSupplier.php', '2',  '1', '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit Supplier', 'Edit Supplier', 'Page', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'supplier'), 'editSupplier.php', '2',  '2', '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Purchase Order
    $db->rawQuery("INSERT INTO `permissions`(`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Purchase Order Listing', 'Purchase Order Listing', 'Sub Menu', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'Inventory') , 'purchaseOrder.php', '1', (SELECT MAX(b.`priority`)+1 FROM `permissions` AS b WHERE b.`parent_id` = (SELECT e.`id` FROM `permissions` AS e WHERE e.`name` = 'Inventory')), '', 'A01568', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Purchase Order Detail', 'Purchase Order Detail', 'Page', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'Purchase Order Listing'), 'purchaseOrderDetail.php', '2',  '1', '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Issue DO', 'Issue DO', 'Page', (SELECT t.`id` FROM `permissions` AS t WHERE t.`name` = 'Purchase Order Listing'), 'issueDO.php', '2', '', '', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    // Bonus Payout
    $db->rawQuery("INSERT INTO `permissions`(`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('Bonus Payout Listing', 'Bonus Payout Listing', 'Menu', '0' , 'bonusPayout.php', '0', (SELECT MAX(b.`priority`)+1 FROM `permissions` AS b WHERE b.`type` = 'Menu' AND b.`site` = 'Admin'), '', 'A01586', '0', '0', 'Admin', 'now()', 'now()', '', '', ''), ('Bonus Payout Detail', 'Bonus Payout Detail', 'Page', (SELECT c.`id` FROM `permissions` AS c WHERE c.`name`='Bonus Payout Listing' AND c.`site` = 'Admin') , 'bonusPayoutDetail.php', '1', '1', '', 'A01587', '0', '0', 'Admin', 'now()', 'now()', '', '', '');");
    
    // Custom Query
    $db->rawQuery("INSERT INTO `provider`(`id`, `company`, `name`, `username`, `password`, `api_key`, `type`, `priority`, `disabled`, `deleted`, `default_sender`, `url1`, `url2`, `remark`, `currency`, `balance`, `created_at`, `updated_at`) VALUES (NULL, 'metafiz', 'email', 'cs@meta-fiz.com', 'metaversecs18', '', 'email', 0, 0, 0, '', '', '', '', '', '0.00000000', '0000-00-00 00:00:00', '0000-00-00 00:00:00')");

    $db->rawQuery("UPDATE `permissions` SET `disabled` = 0, `master_disabled` = 0 WHERE `name` = 'Products Settings' AND `type` = 'Menu'");

    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Game Room Setting', 'Admin set game room setting page', 'Page', (SELECT `b`.`id` FROM `permissions` `b` WHERE `b`.`name` = 'Products Settings'), 'setGameRoomSetting.php', '0', '', 'zmdi-collection-text', '', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->where('name', 'Batch Function');
    $batchFunctinID = $db->getValue('permissions', 'id');
    $db->rawQuery("UPDATE permissions SET disabled = '0', master_disabled = '0', parent_id = ".$batchFunctinID.", priority = '5' WHERE name = 'Batch Lock Account' AND description = 'Batch Lock Account' AND site = 'Admin';");
    $db->rawQuery("UPDATE permissions SET disabled = '0', master_disabled = '0' WHERE name = 'Add Batch Lock Account' AND type = 'Page' AND site = 'Admin';");
    $db->rawQuery("UPDATE permissions SET disabled = '0', master_disabled = '0' WHERE name = 'Batch Lock Account Detail' AND type = 'Page' AND site = 'Admin';");
    $db->rawQuery("UPDATE permissions SET disabled = '0', master_disabled = '0' WHERE name = 'KYC Listing' AND type = 'Menu' AND site = 'Admin';");
    $db->rawQuery("INSERT INTO `permissions` (`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('Dashboard', 'Dashboard', 'Menu', '', 'productStockDetail.php', '0', (SELECT MAX(d.`priority`)+1 FROM `permissions` as d), '', 'A01606', '0', '0', 'Admin', 'now()', 'now()', '', '', '0'), ('Low Stock Quantity', 'Low Stock Quantity', 'Menu', '', 'lowStockQuantity.php', '0', (SELECT MAX(d.`priority`)+2 FROM `permissions` as d), '', 'A01607', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");
    $db->rawQuery("INSERT INTO `permissions` (`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('PGP Monthly Sales', 'PGP Monthly Sales', 'Menu', '0', 'pgpMonthlySales.php', '0', (SELECT MAX(c.`priority`)+1 FROM permissions AS c WHERE c.`site` = 'Admin') ,'','A01603','0','0','Admin', 'now()','now()','','','0')");
    $db->rawQuery("INSERT INTO `permissions` (`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('Low Stock Listing', 'Low Stock Listing', 'Page', (SELECT a.`id` FROM permissions AS a WHERE a.`name` = 'Dashboard'), 'lowStockListing.php', '1', '1','','A01604','0','0','Admin', 'now()','now()','','','0'),('Out of Stock Listing', 'Out of Stock Listing', 'Page', (SELECT b.`id` FROM permissions AS b WHERE b.`name` = 'Dashboard'), 'outOfStockListing.php', '1', (SELECT MAX(c.`priority`)+1 FROM permissions AS c WHERE c.`parent_id` = (SELECT d.`id` FROM permissions AS d WHERE d.`name` = 'Dashboard')),'','A01605','0','0','Admin', 'now()','now()','','','0')");
    $db->rawQuery("INSERT INTO `permissions` (`name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES ('Recruit & Active Program Report', 'Recruit & Active Program Report', 'Menu', '0', 'recruitPromoReport.php', '0', (SELECT MAX(c.`priority`)+1 FROM permissions AS c WHERE c.`site` = 'Admin'), '', 'A01614','0','0','Admin','now()','now()', '', '', '0')");

    // *************************************
    // *******   Country Available   *******
    // *************************************
    $db->rawQuery("UPDATE `country` SET `status` = 'Inactive'");
    $db->rawQuery("UPDATE `country` SET `status` = 'Active' WHERE `name` in ('Indonesia')");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'maxAccPerPhone', '3', '', '', 'Maximum Account that can be created for one phone number', 'Standard Platform')");




    //Update permissions to hide some unwanted menu
    // *****************************
    // *******  Custom Query *******
    // *****************************

    //Process
    $db->rawQuery("INSERT INTO `processes` (`id`, `name`, `file_path`, `output_path`, `process_id`, `disabled`, `arg1`, `arg2`, `arg3`, `arg4`, `arg5`, `created_at`, `updated_at`) VALUES (NULL, 'pushServerSocket', '../modules/mlmPlatform/socket/bin/pushServer.php', '../modules/mlmPlatform/socket/bin/pushServer.log', '0', '0', '', '', '', '', '', NOW(), NOW());");

    $db->rawQuery("INSERT INTO `processes` (`id`, `name`, `file_path`, `output_path`, `process_id`, `disabled`, `arg1`, `arg2`, `arg3`, `arg4`, `arg5`, `created_at`, `updated_at`) VALUES (NULL, 'processGame', '../modules/mlmPlatform/process/processGame.php', '../modules/mlmPlatform/process/log/processGame.log', '0', '0', '', '', '', '', '', NOW(), NOW());");

    $db->rawQuery("INSERT INTO `processes` (`id`, `name`, `file_path`, `output_path`, `process_id`, `disabled`, `arg1`, `arg2`, `arg3`, `arg4`, `arg5`, `created_at`, `updated_at`) VALUES (NULL, 'processQueue-calculateRank', '../modules/mlmPlatform/process/processQueue.php', '../modules/mlmPlatform/process/log/processQueue-calculateRank.log', '0', '0', 'calculateRank', '', '', '', '', NOW(), NOW());");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'processGetProduct', '0', '', '', 'Process that generate product json.', 'Standard Platform')");

    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'Edit CV Rate', 'Admin set cv rate', 'Menu', '0', 'editCVRate.php', '0', (SELECT MAX(priority)+1 FROM permissions AS b), 'zmdi-collection-text', 'A01584', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) VALUES (NULL, 'CV Rate History', 'CV Rate History', 'Page', (SELECT id FROM permissions c WHERE c.name = 'Edit CV Rate'), 'cvRateHistory.php', '0', (SELECT MAX(priority)+1 FROM permissions AS b), 'zmdi-collection-text', 'A01585', '0', '0', 'Admin', 'now()', 'now()', '', '', '0');");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'companyAccount', 'director', '', '', 'Purchase Credit Account', 'Standard Platform')");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'HQ', 'Street 121 Kemang Utara No 17, Jakarta 14420 Indonesia', 'companyAddress', '10000', 'Company Address', 'Standard Platform')");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'Cabinet 2', 'Street 121 Kemang Utara No 17, Jakarta 14420 Indonesia', 'companyAddress', '10001', 'Company Address', 'Standard Platform')");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'stayActivePVP', '50', 'bonusValue', '12 months', 'PVP reuqired to Maintain Active .', 'Standard Platform')");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'Company Contact', '{".'"contactNo"'.":".'"625842377"'.",".'"email"'.":".'"mfiz@test.com"'.",".'"fax"'.":".'"621234567"'."}', 'companyContact', '', 'Company Contact', 'Standard Platform')");
    
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'companyInfo', '{".'"socialAcc"'.":".'"@metafiz.id"'.",".'"socialMedia"'.":".'"@MetaFiz ID"'.",".'"phone"'.":".'"021 75685326"'."}', 'companyInfo', '', 'Company Info', 'Standard Platform')");
        
	// **************************
    // *******  RUN LAST ********
    // **************************
    echo "--------------------------\n";


    //socket script

    $function->removeDuplicateRecord("processes", array("name", "file_path", "output_path", "arg1", "arg2"));
    $function->removeDuplicateRecord("permissions", array("name", "description", "type", "file_path", "site", "parent_id"));

    $function->regeneratePermission();
    $function->regenerateRolePermission(); // THIS FUNCTION WILL REASSIGN ROLE_PERMISSION
    $db->rawQuery("UPDATE `permissions` SET `master_disabled` = `disabled`");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'isSponsorCodeRegister', '1', '', '', 'sponsor code register if 1 yes else 0 ', 'MLM Platform'), (NULL, 'sponsorCodeLength', '6', '', '', 'sponsor code length', 'MLM Platform'), (NULL, 'otpCodeVerify', '0', '', '', '1 on 0 off', 'MLM Platform');");
    $db->rawQuery("UPDATE `client` SET `sponsor_code` = 't2pass' WHERE `id` = 1000000;");

    
    //Add Rerun Bonus Module Setting
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'processBonusSwitch', '0', '', '', 'Bonus Process Running Switch, Value 1 = running, Value 0 = finish', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'resetBonusSwitch', '0', '', '', 'Reset Bonus Process Running Switch, Value 1 = running, Value 0 = finish', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'waitQueueFlag', '0', '', '', 'Flag for Waiting queue to let queue process run during bonus is running, Value 1 = open, Value 0 = close', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'resetBonusLimit', '5 days', '', '', 'Reset Bonus Limit Days', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'processQueueSwitch', '1', '', '', 'Switch to handle processQueue. Value 1 = On, Value 0 = Off', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) SELECT NULL, 'Bonus Process Listing', 'Bonus Process Listing', 'Menu', '0', 'bonusProcessListing.php', '0', MAX(b.priority)+1, 'zmdi-money-box', '', '1', '1', 'Admin', '2020-01-20 18:35:28', '2021-07-01 18:13:12', '', '', '0' FROM permissions b WHERE b.type = 'Menu' AND b.site = 'Admin';");
    $db->rawQuery("INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `level`, `priority`, `icon_class_name`, `translation_code`, `disabled`, `master_disabled`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`, `last_line`) SELECT NULL, 'Bonus Operation', 'Bonus Operation', 'Function', b.id, '', '1', '1', '', '', '1', '1', 'Admin', '2018-04-14 13:51:32', '2021-07-06 17:01:17', '', '', '0' FROM permissions b WHERE b.type = 'Menu' AND b.site = 'Admin' AND b.name = 'Bonus Process Listing';");
    $db->rawQuery("INSERT INTO `processes` (`id`, `name`, `file_path`, `output_path`,`process_id`, `disabled`, `arg1`, `arg2`, `arg3`, `arg4`, `arg5`, `created_at`, `updated_at`) VALUES (NULL, 'bonusOperation', '../modules/mlmPlatform/process/processQueue.php', '../modules/mlmPlatform/process/log/processQueue-bonusOperation.log', '', '1', 'bonusOperation', '', '', '', '', '2021-05-12 17:38:18', '2021-05-12 17:38:18');");

    //Cash Award Cycle Duration
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'awardCycleDuration', '1 years', '', '', 'Cash Award Cycle Duration.', 'Standard Platform');");

    // add ValidMediaRes
     $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'validImageType', 'image/jpg#image/jpeg#image/png#image/gif#image/bmp#image/tiff#image/webp', 'Upload Setting', '3145728', 'Value = Valid Image Type, reference = Image Size Limit', 'MLM Platform')");
     $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'validVideoType', 'video/mp4#video/avi#video/quicktime#video/mpeg#video/x-ms-wmv#video/webm#video/ogg', 'Upload Setting', '15728640', 'Value = Valid Video Type, reference = Video Size Limit', 'MLM Platform')");

     // add ValidDocumentType
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'validDocumentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet#application/vnd.ms-excel#application/msword#application/vnd.ms-powerpoint#text/plain#application/pdf', 'Upload Setting', '5242880', 'Value = Valid Document Type, reference = Document Size Limit', 'MLM Platform')");

    // Add MetFiz Recruit & Active Program Setting
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'promoPeriod', '2022-01-01', 'Recruit Promo Setting', '2022-04-30', 'Promo Period for MetFiz Recruit & Active Program Event', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'promoReward', '500000', 'Recruit Promo Setting', '5', 'Promo Reward for MetFiz Recruit & Active Program Event. Each 5 downline achieved, will get 500,000 as Reward.', 'Standard Platform');");
    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'promoConditions', '50', 'Recruit Promo Setting', '200', 'Promo Conditions for MetFiz Recruit & Active Program Event. Value = min Own Sales, Reference. = Min Downline Own Sales', 'Standard Platform');");


    // Add Default System Setting Admin
     $db->rawQuery("INSERT INTO `system_settings_admin` (`id`, `name`, `type`, `value`, `reference`, `ref_id`, `created_at`, `creator_id`, `status`, `active_at`) VALUES (NULL, 'roomSetting', 'percentage', '100', 'memberPercentage', (SELECT id FROM mlm_product WHERE name = 'package 1'), NOW(), 0, 'Active', NOW())");
     $db->rawQuery("INSERT INTO `system_settings_admin` (`id`, `name`, `type`, `value`, `reference`, `ref_id`, `created_at`, `creator_id`, `status`, `active_at`) VALUES (NULL, 'roomSetting', 'percentage', '100', 'memberPercentage', (SELECT id FROM mlm_product WHERE name = 'package 2'), NOW(), 0, 'Active', NOW())");
     $db->rawQuery("INSERT INTO `system_settings_admin` (`id`, `name`, `type`, `value`, `reference`, `ref_id`, `created_at`, `creator_id`, `status`, `active_at`) VALUES (NULL, 'roomSetting', 'percentage', '100', 'memberPercentage', (SELECT id FROM mlm_product WHERE name = 'package 3'), NOW(), 0, 'Active', NOW())");

     $db->rawQuery("INSERT INTO `system_settings_admin` (`id`, `name`, `type`, `value`, `reference`, `ref_id`, `created_at`, `creator_id`, `status`, `active_at`) VALUES (NULL, 'roiPercentage', 'Game Setting', '3', 'rewardRebate', (SELECT id FROM mlm_product WHERE name = 'package 1'), NOW(), 0, 'Active', NOW())");
     $db->rawQuery("INSERT INTO `system_settings_admin` (`id`, `name`, `type`, `value`, `reference`, `ref_id`, `created_at`, `creator_id`, `status`, `active_at`) VALUES (NULL, 'roiPercentage', 'Game Setting', '3', 'rewardRebate', (SELECT id FROM mlm_product WHERE name = 'package 2'), NOW(), 0, 'Active', NOW())");
     $db->rawQuery("INSERT INTO `system_settings_admin` (`id`, `name`, `type`, `value`, `reference`, `ref_id`, `created_at`, `creator_id`, `status`, `active_at`) VALUES (NULL, 'roiPercentage', 'Game Setting', '5', 'rewardRebate', (SELECT id FROM mlm_product WHERE name = 'package 3'), NOW(), 0, 'Active', NOW())");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'deliveryOption', 'delivery#pickup', '', '', 'Delivery Option.', 'Standard Platform');");

    $db->rawQuery("INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES (NULL, 'childAgeOption', '1-5#6-10#11', '', '', 'Child age option for registration', ' Standard Platform');");

    echo "Setup Clean Project End...\n";
?>
