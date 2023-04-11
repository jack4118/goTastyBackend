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


    echo "Start Setup Matching Bonus...\n";
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
        "name" => "bonusMatching",
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
        "adminDisplay" => array("english" => "Bonus Wallet - Matching",
                           "chineseSimplified" => "Bonus Wallet - Matching",
                           "chineseTraditional" => "Bonus Wallet - Matching",
                           "vietnam" => "Bonus Wallet - Matching",
                           "malay" => "Bonus Wallet - Matching",
                           "japanese" => "Bonus Wallet - Matching",
                           "korean" => "Bonus Wallet - Matching"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );
    unset($creditSettingAry);

    $creditSettingAry["isAdjustable"]           = array("value" => "1", "admin" => "1");
    $creditSettingAry["isDisplayOnTransaction"] = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isTransferable"]         = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isWallet"]               = array("value" => "1", "admin" => "1", "member" => "1");
    $creditSettingAry["isWithdrawable"]         = array("value" => "0", "admin" => "0", "member" => "0");
    $creditSettingAry["isWithholdingWallet"]    = array("value" => "1", "admin" => "0", "member" => "0", "reference" => "bonusMatching");
    $creditSettingAry["showTransactionHistory"] = array("value" => "1", "admin" => "1", "member" => "1");

    $creditDataAry[] = array(
        "name" => "withholdingMatching",
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
        "adminDisplay" => array("english" => "Withholding Wallet - Matching",
                           "chineseSimplified" => "Withholding Wallet - Matching",
                           "chineseTraditional" => "Withholding Wallet - Matching",
                           "vietnam" => "Withholding Wallet - Matching",
                           "malay" => "Withholding Wallet - Matching",
                           "japanese" => "Withholding Wallet - Matching",
                           "korean" => "Withholding Wallet - Matching"),
        "setting" => $creditSettingAry,
        "decimal" => 2
    );

    unset($creditSettingAry);

    foreach($creditDataAry AS $creditData){
        // exist credit will be skipped
        $return = $function->addNewWallet($creditData);
        if($return) echo date("Y-m-d H:i:s")." ".$creditData['name']." (success)\n";
    }

    echo "Get bonus permissions ID...\n";
    $db->where("name", "Bonus Report");
    $bonusID = $db->getValue("permissions", "id");

    $db->where("parent_id", $bonusID);
    $maxPriority = $db->getValue("permissions", "MAX(priority)");

    $bonusArray[] = array(
        "name" => "matchingBonus",
        "bonus_source" => "bonusValue",
        "calculation" => "Instant",
        "payment" => "Instant",
        "priority" => $maxPriority+1,
        "table_name" => "mlm_bonus_matching",
        "allow_rank_maintain" => "0",
        "disabled" => "0",
        'display' => array(
                            'english' => 'Matching Bonus',
                            'chineseSimplified' => 'Matching Bonus',
                            'chineseTraditional' => 'Matching Bonus',
                            "vietnam" => "Matching Bonus",
                            "malay" => "Matching Bonus",
                            "japanese" => "Matching Bonus",
                            "korean" => "Matching Bonus"
                        ),
        "type" => "mlm",
        "paymentMethod" => array(
                                array("percentage" => "100","credit_type" => "bonusMatching", "description" => "Matching Bonus Payout"),
                            ),

        "setting" => array(
                                array("name" => "Bonus Percentage", "value" => "50", "type" => "Matching Bonus Payout", "reference" => "percentage"),
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

    $db->rawquery("ALTER TABLE mlm_bonus_matching DROP COLUMN from_pairing_id");
    $db->rawquery("ALTER TABLE mlm_bonus_matching CHANGE from_pairing_amount from_amount decimal(20,8) NOT NULL");
    $db->rawquery("ALTER TABLE mlm_bonus_matching CHANGE rank_id game_id bigint(20) NOT NULL");
    
    echo "Setup Matching Bonus End...\n";
    
echo "\nEnd\n";?>