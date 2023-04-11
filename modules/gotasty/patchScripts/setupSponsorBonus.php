<?php echo "Start\n";
	$currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');
    include_once($currentPath.'/../include/class.scriptFunction.php');

	log::setupLogPath(__DIR__, __FILE__);
	$language = "english";
    General::$translations = $translations;
    General::$currentLanguage = $language;

    $function = new scriptFunction();
    $dateTime = date("Y-m-d H:i:s");

    echo "Start Setup Sponsor Bonus...\n";

    $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    $creditSettingAry["isDisplayOnTransaction"] = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isTransferable"]         = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    $creditSettingAry["isWithdrawable"]         = array("value" => "3", "admin" => "0", "member" => "1", "description" => "bank=1,crypto=2,both=3");
    $creditSettingAry["minWithdrawal"]          = array("value" => "50");
    $creditSettingAry["showTransactionHistory"] = array("value" => "1", "admin" => "1", "member" => "1");
    $creditSettingAry["withdrawalAdminFee"][]   = array("value" => "0", "admin" => "0", "member" => "0", "type" => "amount", "reference" => "min","description" => "");
    $creditSettingAry["withdrawalAdminFee"][]   = array("value" => "5", "admin" => "0", "member" => "0", "type" => "percentage", "reference" => "max","description" => "");

    $creditDataAry[] = array(
        "name" => "bonusSponsor",
        // "type" => "bonusCredit",
        "type" => "gotastyCredit",
        "code" => "BC",
        "description" => "Bonus Credit",
        "image_name" => "bonusCredit.jpg",
        // "display" => array(
        //     "english" => "Bonus Wallet",
        //     "chineseSimplified" => "Bonus Wallet",
        //     "chineseTraditional" => "Bonus Wallet",
        //     "vietnam" => "Bonus Wallet",
        //     "malay" => "Bonus Wallet",
        //     "japanese" => "Bonus Wallet",
        //     "korean" => "Bonus Wallet"),
        // "adminDisplay" => array(
        //     "english" => "Bonus Wallet - Sponsor",
        //     "chineseSimplified" => "Bonus Wallet - Sponsor",
        //     "chineseTraditional" => "Bonus Wallet - Sponsor",
        //     "vietnam" => "Bonus Wallet - Sponsor",
        //     "malay" => "Bonus Wallet - Sponsor",
        //     "japanese" => "Bonus Wallet - Sponsor",
        //     "korean" => "Bonus Wallet - Sponsor"),

        "display" => array(
            "english" => "Gotasty Wallet",
            "chineseSimplified" => "Gotasty Wallet",
            "chineseTraditional" => "Gotasty Wallet",
            "vietnam" => "Gotasty Wallet",
            "malay" => "Gotasty Wallet",
            "japanese" => "Gotasty Wallet",
            "korean" => "Gotasty Wallet"),
        "adminDisplay" => array(
            "english" => "Gotasty Wallet - Sponsor",
            "chineseSimplified" => "Gotasty Wallet - Sponsor",
            "chineseTraditional" => "Gotasty Wallet - Sponsor",
            "vietnam" => "Gotasty Wallet - Sponsor",
            "malay" => "Gotasty Wallet - Sponsor",
            "japanese" => "Gotasty Wallet - Sponsor",
            "korean" => "Gotasty Wallet - Sponsor"),

        "setting" => $creditSettingAry,
        "decimal" => 6
    );

    unset($creditSettingAry);

    /*
    $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    $creditSettingAry["isDisplayOnTransaction"] = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isTransferable"]         = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    $creditSettingAry["isWithdrawable"]         = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isWithholdingWallet"]    = array("value" => "1", "admin" => "0", "member" => "0", "reference" => "bonusMatching");
    $creditSettingAry["showTransactionHistory"] = array("value" => "1", "admin" => "1", "member" => "1");

    $creditDataAry[] = array(
        "name" => "withholdingSponsor",
        "type" => "withholding",
        "code" => "WH",
        "description" => "Withholding",
        "image_name" => "withholding.jpg",
        "display" => array(
            "english" => "Withholding Wallet",
            "chineseSimplified" => "Withholding Wallet",
            "chineseTraditional" => "Withholding Wallet",
            "vietnam" => "Withholding Wallet",
            "malay" => "Withholding Wallet",
            "japanese" => "Withholding Wallet",
            "korean" => "Withholding Wallet"),
        "adminDisplay" => array(
            "english" => "Withholding Wallet - Sponsor",
            "chineseSimplified" => "Withholding Wallet - Sponsor",
            "chineseTraditional" => "Withholding Wallet - Sponsor",
            "vietnam" => "Withholding Wallet - Sponsor",
            "malay" => "Withholding Wallet - Sponsor",
            "japanese" => "Withholding Wallet - Sponsor",
            "korean" => "Withholding Wallet - Sponsor"),
        "setting" => $creditSettingAry,
        "decimal" => 6
    );

    unset($creditSettingAry);
    */

    foreach($creditDataAry AS $creditData) {
        // Exist credit will be skipped
        $return = $function->addNewWallet($creditData);
        if($return) echo date("Y-m-d H:i:s")." ".$creditData['name']." (Success)\n";
    }

    echo "Get bonus permissions ID...\n";
    $db->where("name", "Bonus Report");
    $bonusID = $db->getValue("permissions", "id");

    $db->where("parent_id", $bonusID);
    $maxPriority = $db->getValue("permissions", "MAX(priority)");

    $bonusArray[] = array(
        "name" => "sponsorBonus",
        "bonus_source" => "bonusValue",
        "calculation" => "Instant",
        "payment" => "Instant",
        "priority" => $maxPriority+1,
        "table_name" => "mlm_bonus_sponsor",
        "allow_rank_maintain" => "0",
        "disabled" => "0",
        'display' => array(
            'english' => 'Sponsor Bonus',
            'chineseSimplified' => 'Sponsor Bonus',
            'chineseTraditional' => 'Sponsor Bonus',
            "vietnam" => "Sponsor Bonus",
            "malay" => "Sponsor Bonus",
            "japanese" => "Sponsor Bonus",
            "korean" => "Sponsor Bonus"
        ),
        "type" => "mlm",
        "paymentMethod" => array(
            array("percentage" => "100","credit_type" => "bonusSponsor", "description" => "Sponsor Bonus Payout"),
        ),
        "setting" => array(
            array("name" => "Bonus Percentage", "value" => "5", "type" => "Sponsor Bonus Payout", "reference" => "percentage"),
        ),
    );

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
            "priority" => $bonus["priority"],
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

    $db->rawQuery("DROP TABLE IF EXISTS `mlm_bonus_sponsor`");
    $db->rawQuery("CREATE TABLE `mlm_bonus_sponsor` (`id` bigint(20) NOT NULL PRIMARY KEY AUTO_INCREMENT,`bonus_id` bigint(20) NOT NULL,`client_id` bigint(20) NOT NULL,`bonus_date` date NOT NULL,`game_id` bigint(20) NOT NULL,`product_id` bigint(20) NOT NULL,`from_client_id` bigint(20) NOT NULL,`from_amount` decimal(20,8) NOT NULL,`percentage` decimal(20,8) NOT NULL,`calculated_amount` decimal(20,8) NOT NULL,`unit_price` decimal(20,8) NOT NULL,`payable_amount` decimal(20,8) NOT NULL,`paid` tinyint(1) NOT NULL,`batch_id` bigint(20) NOT NULL,`created_at` datetime NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    
    echo "Setup Sponsor Bonus End...\n";
    
echo "\nEnd\n";?>