<?php echo "Start\n";
    // ini_set('display_errors', 1);
    // ini_set('display_startup_errors', 1);
    // error_reporting(E_ALL);
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

    echo "Start create unilevel bonus table\n";

    $db->rawQuery("DROP TABLE IF EXISTS `mlm_bonus_unilevel`;");
    $db->rawQuery("CREATE TABLE `mlm_bonus_unilevel` (
         `id` bigint(20) NOT NULL,
         `bonus_date` date NOT NULL,
         `client_id` bigint(20) NOT NULL,
         `rank_id` bigint(20) NOT NULL,
         `couple_flush` decimal(20,8) NOT NULL,
         `flush_dvp` decimal(20,8) NOT NULL,
         `calculated_dvp` decimal(20,8) NOT NULL,
         `amount` decimal(20,8) NOT NULL,
         `payable_amount` decimal(20,8) NOT NULL,
         `batch_id` bigint(20) NOT NULL,
         `paid` tinyint(1) NOT NULL,
         `paid_batch_id` bigint(20) NOT NULL,
         `created_at` datetime NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->rawQuery("ALTER TABLE `mlm_bonus_unilevel` ADD PRIMARY KEY (`id`);");
    $db->rawQuery("ALTER TABLE `mlm_bonus_unilevel` MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;");

    echo "Finish create unilevel bonus table\n";

    $bonusArray[] = array(
                        "name" => "unilevelBonus",
                        'type' => "mlm",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Daily",
                        "payment" => "Daily",
                        "priority" => "7",
                        "table_name" => "mlm_bonus_unilevel",
                        "disabled" => "0",
                        "allow_rank_maintain" => 1,
                        'display' => array(
                                                'english' => 'Unilevel Bonus',
                                                'chineseSimplified' => 'Unilevel Bonus',
                                                'chineseTraditional' => 'Unilevel Bonus',
                                            ),
                        "setting" => array(   
                                            array("name" => "unilevelDVP","value" => "200", "type" => "Bonus Setting", "reference" => "", "description" => "For every couple flush"),
                                            array("name" => "unilevelPercentage","value" => "0.5", "type" => "Bonus Setting", "reference" => "10000", "description" => "Unilevel = value * amount * reference"),
                                            array("name" => "unilevelEntitled","value" => "fizExecutive#fizManager#fizDirector", "type" => "Bonus Setting", "reference" => "8000#20000#0", "description" => "Maximum earn by respective rank"),
                                            ),
                        "paymentMethod" => array(
                                                array("percentage" => "100","credit_type" => "bonusDef", "description" => "Unilevel Bonus Payout"),
                                                // array("percentage" => "100","credit_type" => "gotastyDef", "description" => "Unilevel Bonus Payout"),
                                            ),
                        "rank_setting" => array(
                                                array("name" => "unilevelBonusPercentage","type" => "percentage", "description" => "Unilevel Bonus Percentage"),
                                            ),
    );

    echo "Get bonus permissions ID...\n";
    $db->where("name", "Bonus Report");
    $bonusID = $db->getValue("permissions", "id");

    echo "Get rank id... \n";
    $rankSettingAry = $db->get('rank');

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
        $bonusRank    = $bonus["rank_setting"];

        unset($bonus['display']);
        unset($bonus["setting"]);
        unset($bonus["paymentMethod"]);
        unset($bonus["rank_setting"]);
        $bonus["id"] = $db->insert("mlm_bonus", $bonus);
        
        $mainPermissions = array(
            "name" => $bonus["name"],
            "description" => $bonus["name"]." Bonus Report Listing",
            "type" => "Sub Menu",
            "parent_id" => $bonusID,
            "file_path" => $bonus["name"]."Report.php",
            "level" => 2,
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

        foreach($rankSettingAry as $rankSettingRow){
            foreach($bonusRank as $bonusRankValue){
                $bonusRankValue["rank_id"] = $rankSettingRow['id'];
                if($rankSettingRow['name'] == 'member' || $rankSettingRow['name'] == 'fizEntreprenuer') {
                    $bonusRankValue["value"] = 0;
                }else{
                    $bonusRankValue["value"] = 100;
                }
                $db->insert("rank_setting", $bonusRankValue);
            }
        }
    }
?>