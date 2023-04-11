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

    echo "Start create enrollment bonus table\n";

    $db->rawQuery("DROP TABLE IF EXISTS `mlm_bonus_enrollment`;");
    $db->rawQuery("CREATE TABLE `mlm_bonus_enrollment` (
         `id` bigint(20) NOT NULL,
         `bonus_id` bigint(20) NOT NULL,
         `bonus_date` date NOT NULL,
         `client_id` bigint(20) NOT NULL,
         `from_id` bigint(20) NOT NULL,
         `amount` decimal(20,8) NOT NULL,
         `batch_id` bigint(20) NOT NULL,
         `paid` tinyint(1) NOT NULL,
         `paid_batch_id` bigint(20) NOT NULL,
         `created_at` datetime NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->rawQuery("ALTER TABLE `mlm_bonus_enrollment` ADD PRIMARY KEY (`id`);");
    $db->rawQuery("ALTER TABLE `mlm_bonus_enrollment` MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;");

    echo "Finish create enrollment bonus table\n";

    $bonusArray[] = array(
                        "name" => "enrollmentBonus",
                        'type' => "mlm",
                        "bonus_source" => "bonusValue",
                        "calculation" => "Daily",
                        "payment" => "Daily",
                        "priority" => "5",
                        "table_name" => "mlm_bonus_enrollment",
                        "disabled" => "0",
                        "allow_rank_maintain" => 0,
                        'display' => array(
                                                'english' => 'Enrollment Bonus',
                                                'chineseSimplified' => 'Enrollment Bonus',
                                                'chineseTraditional' => 'Enrollment Bonus',
                                            ),
                        "setting" => array(   
                                            array("name" => "enrollmentProductCode","value" => "9999120229899#9999220229899#9999320229899#9999420229899#9999520229899#9999620229899#9999920229899#99991020229899#99992220229899#99991320229899#99991220229899", "type" => "Bonus Setting", "reference" => "", "description" => "Product Code for Enrollment Bonus Product"),
                                            array("name" => "enrollmentBV","value" => "200000", "type" => "Bonus Setting", "reference" => "", "description" => "Enrollment BV."),
                                            ),
                        "paymentMethod" => array(
                                                // array("percentage" => "100","credit_type" => "bonusDef", "description" => "Enrollment Bonus Payout"),
                                                array("percentage" => "100","credit_type" => "gotastyDef", "description" => "Enrollment Bonus Payout"),
                                            ),
    );

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
    }
?>