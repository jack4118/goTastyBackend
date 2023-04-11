<?php

    class Bonus{

        function __construct($db, $setting, $cash, $log){

            $this->db       = $db;
            $this->setting  = $setting;
            $this->log      = $log;
            $this->cash     = $cash;

        }


        function calculateSponsorBonus($startDate) {

            $db             = $this->db;
            $log            = $this->log;
            $setting        = $this->setting;
            $bonusName      = "sponsorBonus";
            $bonusSetting   = $this->getBonusSetting($bonusName);

            $log->write(date("Y-m-d H:i:s") . " Calculating Sponsor Bonus for " . $startDate . "\n");

            if (!$startDate) {
                $log->write(date("Y-m-d H:i:s") . " Date problem: " . $startDate . "Please check your date and run again.\n");
                return false;
            }

            $start = $startDate." 00:00:00";
            $end = $startDate." 23:59:59";

            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $startDate);
            $db->where("completed", 1);
            $db->where("paid", 1);
            $count = $db->getValue("mlm_bonus_calculation_batch", "count(*)");

            //avoid run twice or more cause the payment made twice or more
            if ($count > 0) {
                $log->write(date("Y-m-d H:i:s") . $startDate . " " . $bonusName . " has been paid. Failed to calculate.\n");
                return false;
            }

            //get bonus source
            $db->where("name", $bonusName);
            $bonusSource = $db->getValue("mlm_bonus", "bonus_source");

            if (!$bonusSource) {
                $log->write(date("Y-m-d H:i:s")." Bonus source is not set, do not continue.\n");
                return false;
            }

            $db->where("created_at", $start, ">=");
            $db->where("created_at", $end, "<=");
            $db->orderBy("created_at", "ASC");
            $result = $db->get("mlm_bonus_in", NULL, array(

                "id",
                "client_id",
                "(SELECT value from mlm_product_setting WHERE mlm_product_setting.product_id = mlm_bonus_in.product_id AND name = 'rankID') as rank_id",
                "belong_id",
                "bonus_value"

            ));

            if (!$result)
                $log->write(date("Y-m-d H:i:s") . " Today's mlm_bonus_in table record is empty\n");

            foreach($result as $row){

                $bonusInArray[] = $row;
            }

            /** mlm_bonus_in_details table and mlm_bonus_in table are actually one to one relationship
                changes are made (merge them together) **/
//            if (count($bonusInIDArray) > 0) {
//                // Load all the values
//                $db->where("bonus_in_id", $bonusInIDArray, "IN");
//                //**table not yet created**//
//                $result = $db->get("mlm_bonus_in_details", NULL, array(
//
//                    "bonus_in_id",
//                    "name",
//                    "value",
//
//                ));
//
//                foreach($result as $row){
//                    $bonusInDetails[$row["bonus_in_id"]][$row['name']] = $row['value'];
//                }
//            }

            foreach ($bonusInArray as $row) {
                // Do validation on all the bonus values before inserting
                $bonusValue = $row['bonus_value'];
                if (!$bonusValue || empty($bonusValue)) {
                    $log->write(date("Y-m-d H:i:s") . " Bonus value is empty, do not continue.\n");
                    return false;
                }
            }

            //get unit price
            $unitPrice = $this->getLatestUnitPrice();

            if(!$unitPrice) {
                $log->write(date("Y-m-d H:i:s") . " Unit value is empty, do not continue.\n");
                return false;
            }

            $log->write(date("Y-m-d H:i:s") . " Bonus_overriding for " . $bonusName . " is " . $bonusSetting['bonus_overriding'] . "\n");

            if ($bonusSetting['bonus_overriding'] == 1) {

                $maxSponsorLevel = 0;

                // Get the maximum level that we can pay up
                $db->where('name', "sponsorLevel");
                $productResult = $db->get("rank_setting", NULL, array(

                    "rank_id",
                    "value"
                ));

                foreach ($productResult as $product){
                    $sponsorLevel[$product['rank_id']] = $product['value'];

                    if ($product["value"] > $maxSponsorLevel)
                        $maxSponsorLevel = $product["value"];
                }
            }

            // Get all the members
            $db->where("type", "Client");
            $clients = $db->get("client", NULL, array(

                "id",
                "sponsor_id",

            ));

            foreach ($clients as $client){
                $clientIDArray[]   = $client["id"];
            }

            //get client setting

            $db->where("client_id", $clientIDArray, "IN");
            $db->where("name", $bonusName);
            $db->where("type", "Bonus Percentage");
            $db->orWhere("type", "Bonus Rank");
            $clientResult = $db->get("client_setting", NULL, array(
                "client_id",
                "name",
                "value",
                "reference",
                "type"
            ));

            foreach ($clientResult as $client){

                if ($client['type'] == "Bonus Percentage")
                    $sponsorPercentage[$client['client_id']]    = $client['value'];
                else if ($client['type'] == "Bonus Rank"){
                    $sponsorRankID[$client['client_id']]        = $client['value'];
                }
            }

            unset($clientIDArray);

            // Generate new batchID if no problem
            $batchID    = $db->getNewID();
            $insertData = array(

                "id"            => $batchID,
                "bonus_name"    => $bonusName,
                "bonus_date"    => $startDate,
                "created_at"    => $db->now()
            );
            $bonusCalculationBatchID = $db->insert("mlm_bonus_calculation_batch", $insertData);

            if ($bonusCalculationBatchID)
                $log->write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            else
                $log->write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            foreach ($bonusInArray as $row) {

                $bonusInID          = $row["id"];
                $clientID           = $row["client_id"];
                $clientRankID       = (int)$row["rank_id"];
                $bonusValue         = $row['bonus_value'];

                $log->write(date("Y-m-d H:i:s") . " BonusIn ID: " . $row["ID"] . ", client: " . $clientID . ", BV: " . $bonusValue . "\n");

                unset($uplineIDArray);

                if ($bonusSetting['bonus_overriding'] == 0) {
                    // No Overriding
                    $uplineIDArray = $this->getSponsorTreeUplines($clientID, 1, false);
                }
                else {
                    // Overriding
                    $uplineIDArray = $this->getSponsorTreeUplines($clientID, 0, false);
                }

                //unset($sponsorPercentage);

                // Loop through the upline to distribute the bonus
                $downlinePercentage = 0;

                $level = 1;

                foreach ($uplineIDArray as $uplineID) {
                    $curPercentage      = $sponsorPercentage[$uplineID];
                    $curRankID          = $sponsorRankID[$uplineID];

                    $log->write(date("Y-m-d H:i:s") . " " . $uplineID . " current sponsor percentage is  " . $curPercentage . ", Current: " . $level . "\n");

                    if ($bonusSetting['bonus_overriding'] == 0) {

                        if ($curPercentage < $downlinePercentage) {
                            // If percentage is lower than previous downline, do not generate any bonus, and set same level for comparison later
                            $curPercentage = $downlinePercentage;
                        }

                    }
                    else if ($bonusSetting['bonus_overriding'] == 1) {
                        // Type A, check for overriding pass up and whether the upline is eligible to receive or not
                        if ($level > $maxSponsorLevel)
                            break;

                        $curEligibleLevel = $sponsorLevel[$curRankID];

                        if ($curEligibleLevel < $level) {

                            $log->write(date("Y-m-d H:i:s") . " " . $uplineID . " is not eligible to receive. Eligible: " . $curEligibleLevel . ", Current: " . $level . "\n");

                            $level++;
                            continue;
                        }

                    }


                    $realPercentage = ($curPercentage - $downlinePercentage) / 100;
                    $portion[$uplineID] = number_format(($bonusValue * $realPercentage), 2, ".", "");
                    //echo date("Y-m-d H:i:s")." ".number_format(($portion[$uplineID]), 2, ".", "")." = $bonusValue * $realPercentage.\n";

                    // Store the previous percentage for comparison
                    $downlinePercentage = $curPercentage;

                    if ($portion[$uplineID] > 0) {

                        $insertData = array(

                            "id"                => $db->getNewID(),
                            "bonus_id"          => $bonusInID,
                            "client_id"         => $uplineID,
                            "rank_id"           => $curRankID,
                            "from_id"           => $clientID,
                            "from_rank_id"      => $clientRankID,
                            "percentage"        => $curPercentage,
                            "bonus_date"        => $startDate,
                            "batch_id"          => $batchID,
                            "amount"            => $portion[$uplineID],
                            "payable_amount"    => $portion[$uplineID] * $unitPrice,
                            "unit_price"        => $unitPrice,
                            "paid"              => 0,
                            "created_at"        => $db->now()

                        );
//                        $fields[] = "ID";
//                        $fields[] = "bonusInID";
//                        $fields[] = "clientID";
//                        $fields[] = "username";
//                        $fields[] = "name";
//                        $fields[] = "packageID";
//                        $fields[] = "fromID";
//                        $fields[] = "fromUsername";
//                        $fields[] = "fromName";
//                        $fields[] = "fromPackageID";
//                        $fields[] = "fromLevel";
//                        $fields[] = "percentage";
//                        $fields[] = "createdOn";
//                        $fields[] = "bonusDate";
//                        $fields[] = "batchID";
//                        $fields[] = "amount";
//                        $fields[] = "unitPrice";
//                        $fields[] = "payableAmount";
//
//                        $values[] = $db->getNewID();
//                        $values[] = mysql_escape_string($bonusInID);
//                        $values[] = mysql_escape_string($uplineID);
//                        $values[] = mysql_escape_string($clientUsername[$uplineID]);
//                        $values[] = mysql_escape_string($clientName[$uplineID]);
//                        $values[] = mysql_escape_string($curPackageID);
//                        $values[] = mysql_escape_string($clientID);
//                        $values[] = mysql_escape_string($clientUsername[$clientID]);
//                        $values[] = mysql_escape_string($clientName[$clientID]);
//                        $values[] = mysql_escape_string($clientPackageID);
//                        $values[] = mysql_escape_string($level);
//                        $values[] = mysql_escape_string($curPercentage);
//                        $values[] = date("Y-m-d H:i:s");
//                        $values[] = mysql_escape_string($startDate);
//                        $values[] = mysql_escape_string($batchID);
//                        $values[] = mysql_escape_string($portion[$uplineID]);
//                        $values[] = mysql_escape_string($unitPrice);
//
//                        $payableAmount = $portion[$uplineID] * $unitPrice;
//                        $values[] = mysql_escape_string($payableAmount);

                        $id = $db->insert("mlm_bonus_sponsor", $insertData);

                        if ($id)
                            $log->write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_sponsor\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed insert into mlm_bonus_sponsor. Error : " . $db->getLastErrno() . "\n");


                        // Keep all bonus in array first
                        /***  not creating mlm_bonus_report table thus this one no longer use in code ***/
//                        $clientBonusArray[$uplineID] += $portion[$uplineID];
                    }
                    else{
                        $log->write(date("Y-m-d H:i:s") . " The portion for client " . $clientID . " is " . $portion[$uplineID] ."\n");

                    }

                    $level++;
                }
            }

            /***  not creating mlm_bonus_report table thus this one no longer use in code ***/
//            foreach ($clientBonusArray as $clientID => $clientBonus) {
//                // Loop and insert total sponsor bonus into bonus report
//                $fields = array("ID", "clientID", "username", "name", "bonusType", "bonusAmount", "bonusDate");
//                $values = array($db->getNewID(), mysql_escape_string($clientID), mysql_escape_string($clientUsername[$clientID]), mysql_escape_string($clientName[$clientID]), mysql_escape_string(BONUS_SPONSOR), mysql_escape_string($clientBonus), mysql_escape_string($startDate));
//                $db->dbInsert("mlmBonusReport", $fields, $values);
//            }

            // Update the batch table to completed
            $updateData = array(
                "completed"     => 1
            );
            $db->where("id", $batchID);
            if ($db->update("mlm_bonus_calculation_batch", $updateData))
                $log->write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            $log->write(date("Y-m-d H:i:s") . " Done Calculating Sponsor Bonus for " . $startDate . "\n");

            return true;
        }


        function calculatePairingBonus($startDate) {

            $db             = $this->db;
            $setting        = $this->setting;
            $log            = $this->log;
            $bonusName      = "pairingBonus";
            $bonusSetting   = $this->getBonusSetting($bonusName);

            $log->write(date("Y-m-d H:i:s") . " Calculating " . $bonusName . " for " . $startDate . "\n");

            if (!$startDate) {

                $log->write(date("Y-m-d H:i:s") . " Date problem: " . $startDate . "Please check your date and run again.\n");
                return false;
            }

            $start = $startDate." 00:00:00";
            $end = $startDate." 23:59:59";

            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $startDate);
            $db->where("completed", 1);
            $db->where("paid", 1);
            $count = $db->getValue("mlm_bonus_calculation_batch", "count(*)");

            //avoid run twice or more cause the payment made twice or more
            if ($count > 0) {
                $log->write(date("Y-m-d H:i:s") . $startDate . " " . $bonusName . " has been paid. Failed to calculate.\n");
                return false;
            }


            //get bonus source
            $db->where("name", $bonusName);
            $bonusSource = $db->getValue("mlm_bonus", "bonus_source");

            if (!$bonusSource) {
                $log->write(date("Y-m-d H:i:s")." Bonus source is not set, do not continue.\n");
                return false;
            }

            $db->where("created_at", $start, ">=");
            $db->where("created_at", $end, "<=");
            $db->orderBy("created_at", "ASC");
            $result = $db->get("mlm_bonus_in", NULL, array(

                "id",
                "client_id",
                "(SELECT value from mlm_product_setting WHERE mlm_product_setting.product_id = mlm_bonus_in.product_id AND name = 'rankID') as rank_id",
                "belong_id",
                "bonus_value"

            ));

            if (!$result)
                $log->write(date("Y-m-d H:i:s") . " Today's mlm_bonus_in table record is empty\n");

            foreach($result as $row){

                $clientIDArray[$row['client_id']] = $row['client_id'];
                $bonusInArray[] = $row;
            }

            foreach ($bonusInArray as $row) {
                // Do validation on all the bonus values before inserting
                $bonusValue = $row['bonus_value'];

                if (!$bonusValue || empty($bonusValue)) {
                    $log->write(date("Y-m-d H:i:s") . " Bonus value is empty, do not continue.\n");
                    return false;
                }
            }


            //get product setting
            $db->where("name", array("pairingBonusMaxCap", "businessCenter"), "IN");
            $productResult = $db->get("rank_setting", NULL, array(

                "rank_id",
                "name",
                "value"
            ));

            foreach($productResult as $product){
                $productSetting[$product["rank_id"]][$product["name"]] = $product["value"];
            }


            //get unit price
            $unitPrice = $this->getLatestUnitPrice();
            if(!$unitPrice) {
                $log->write(date("Y-m-d H:i:s") . " Unit value is " . $unitPrice . ", do not continue.\n");
                return false;
            }

            // Get all the members
            $db->where("type", "Client");
            $clients = $db->get("client", NULL, array(

                "id",
                "name",
                "username",
                "sponsor_id",

            ));

            foreach ($clients as $client){
                $clientIDArray[$client["id"]]   = $client["id"];
                $clientIDArrayForSetting[]      = $client["id"];
                $clientName[$client["id"]]      = $client["name"];
                $clientUsername[$client["id"]]  = $client["username"];
            }

            //get client setting

            $db->where("client_id", $clientIDArrayForSetting, "IN");
            $db->where("name", $bonusName);
            $db->where("type", "Bonus Percentage");
            $db->orWhere("type", "Bonus Rank");
            $clientResult = $db->get("client_setting", NULL, array(
                "client_id",
                "name",
                "value",
                "reference",
                "type"
            ));

            foreach ($clientResult as $client){
                if ($client['type'] == "Bonus Percentage")
                    $pairingPercentage[$client['client_id']]    = $client['value'];
                else if ($client['type'] == "Bonus Rank"){
                    $pairingPackageID[$client['client_id']]     = $client['value'];
                }
            }

//            $positionArray[1] = "left";
//            $positionArray[2] = "right";
//            $positionArray[3] = "center";
//
//            // Get the placement positions in the system
//            $maxPlacementPositions = (int)$setting->internalSetting['Placement Positions'];

            $maxPlacementPositions = (int)$this->getSystemSetting("maxPlacementPositions");

//            if ($maxPlacementPositions == 2){
//                $positionArray[1] = "left";
//                $positionArray[2] = "right";
//            }
//            else if ($maxPlacementPositions == 3){
//                $positionArray[1] = "left";
//                $positionArray[2] = "center";
//                $positionArray[3] = "right";
//            }

            for ($i=1; $i<=$maxPlacementPositions; $i++) {

                $clientSettingName[] = "'Placement CF Total $i'";
            }

            $pairingBonusData = array();

            foreach ($bonusInArray as $row) {

                $clientID = $row["client_id"];
                $bonusValue = $row['bonus_value'];

                // Get all the uplines
                $uplines = $this->getPlacementTreeUplines($clientID);

                foreach ($uplines as $upline) {

                    if ($upline["client_id"] != $clientID) {

                        // Store the client's total bv in array
                        $pairingBonusData[$upline["client_id"]][$downlinePosition] += $bonusValue;
                    }

                    $downlinePosition = $upline["client_position"];

                }
            }

            // Generate new batchID if no problem
            $batchID = $db->getNewID();
            $insertData = array(

                "id"            => $batchID,
                "bonus_name"    => $bonusName,
                "bonus_date"    => $startDate,
                "created_at"    => $db->now()
            );
            $bonusCalculationBatchID = $db->insert("mlm_bonus_calculation_batch", $insertData);

            if ($bonusCalculationBatchID)
                $log->write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            else
                $log->write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            foreach ($clientIDArray as $clientID) {

                for ($i=1; $i<=$maxPlacementPositions; $i++) {

                    $clientBonus[$i] = $pairingBonusData[$clientID][$i] ? $pairingBonusData[$clientID][$i] : 0;

                    // Build the selects fields
                    $selects[$i] = "rm_position_" . $i;
                    $carryFoward[$i] = 0; // Set default 0 first
                }

                // Get remaining from previous day to insert as carry forward
                $previousDate = date("Y-m-d", strtotime($startDate." -1 day"));
                $db->where("client_id", $clientID);
                $db->where("bonus_date", $previousDate);
                $result = $db->get("mlm_bonus_pairing", NULL, $selects);

                foreach($result as $row){
                    for ($i = 1; $i <= $maxPlacementPositions; $i++) {
                        $carryFoward[$i] = $row["rm_position_" . $i];
                    }
                }

                $curPercentage      = $pairingPercentage[$clientID]? $pairingPercentage[$clientID] : 0;
                $curRankID          = $pairingPackageID[$clientID]? $pairingPackageID[$clientID] : 0;
                $curMaxCap          = $productSetting[$curRankID]['pairingBonusMaxCap']? $productSetting[$curRankID]['pairingBonusMaxCap'] : 0;
                $curBusinessCenter  = $productSetting[$curRankID]['businessCenter']? $productSetting[$curRankID]['businessCenter'] : 0;

                unset($placementCarryForward);

                if (count($clientSettingName) > 0) {

                    $db->where("client_id", $clientID);
                    $db->where("name", $clientSettingName, "IN");
                    $clientSetRes = $db->get("client_setting", NULL, array(

                        "name",
                        "value"
                    ));

                    foreach ($clientSetRes as $clientSet){
                        $placementCarryForward[$clientSet["name"]] = $clientSet["value"];
                    }
                }

                // Check for system placement position
                if ($maxPlacementPositions == 2) {

                    $combinedBonus[1] = $clientBonus[1] + $carryFoward[1];
                    $combinedBonus[2] = $clientBonus[2] + $carryFoward[2];

                    if ($combinedBonus[1] < $combinedBonus[2]) {
                        $remaining[1] = 0;
                        $remaining[2] = $combinedBonus[2] - $combinedBonus[1];
                        $pairingAmount = $combinedBonus[1];
                    }
                    else if ($combinedBonus[1] > $combinedBonus[2]) {
                        $remaining[1] = $combinedBonus[1] - $combinedBonus[2];
                        $remaining[2] = 0;
                        $pairingAmount = $combinedBonus[2];
                    }
                    else {
                        // If is equal value
                        $remaining[1] = 0;
                        $remaining[2] = 0;
                        $pairingAmount = $combinedBonus[1];
                    }

                    $calculatedAmount = $pairingAmount * ($curPercentage / 100);

                    if($bonusSetting['calculation'] == "D") {

                    }
                    else {
//                        if ($calculatedAmount > $curMaxCap) {
//                            // Cannot be more than max cap
//                            $calculatedAmount = $curMaxCap;
//                            //echo " ***** ";
//                        }
                    }

                    $calculatedAmount = number_format($calculatedAmount, 2, ".", "");


                    if (!$carryFoward[1] && !$carryFoward[2] && !$clientBonus[1] && !$clientBonus[2] && !$remaining[1] && !$remaining[2]) {
                        // If all is 0 then do not insert
                    }
                    else {
                        unset($insertData);

                        $payableAmount = $calculatedAmount * $unitPrice;

                        $insertData = array(
                            "id"                => $db->getNewID(),
                            "client_id"         => $clientID,
                            "cf_position_1"     => $carryFoward[1],
                            "cf_position_2"     => $carryFoward[2],
                            "position_1"        => $clientBonus[1],
                            "position_2"        => $clientBonus[2],
                            "rm_position_1"     => $remaining[1],
                            "rm_position_2"     => $remaining[2],
                            "percentage"        => $curPercentage,
                            "bonus_date"        => $startDate,
                            "batch_id"          => $batchID,
                            "pairing_amount"    => $pairingAmount,
                            "amount"            => $calculatedAmount,
                            "payable_amount"    => $payableAmount,
                            "unit_price"        => $unitPrice,
                            "business_center"   => $curBusinessCenter,
                            "paid"              => 0,
                            "created_at"        => $db->now()
                        );

                        $id = $db->insert("mlm_bonus_pairing", $insertData);

                        if ($id)
                            $log->write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_pairing\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed insert into mlm_bonus_pairing. Error : " . $db->getLastErrno() . "\n");
                    }
                }

                // Update the carry forward for each client
                for ($i=1; $i<=$maxPlacementPositions; $i++) {
                    if (isset($placementCarryForward["Placement CF Total $i"])) {

                        $db->where("client_id", $clientID);
                        $db->where("name", "Placement CF Total " . $i);
                        if ($db->update("client_setting", array("value" => $remaining[$i])))
                            $log->write(date("Y-m-d H:i:s") . " Successfully update client_setting\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed update client_setting. Error : " . $db->getLastErrno() . "\n");

                    }
                    else {
                        // Insert if not exist
                        $insertData = array(

                            "ID"        => $db->getNewID(),
                            "name"      => "Placement CF Total " . $i,
                            "type"      => "Placement Position CF Total",
                            "client_id" => $clientID
                        );

                        $id = $db->insert("client_setting", $insertData);

                        if ($id)
                            $log->write(date("Y-m-d H:i:s") . " Successfully insert into client_setting\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed insert into client_setting. Error : " . $db->getLastErrno() . "\n");
                    }

                }

                /**  commented because mlm_bonus_report is no longer needed **/
//                if ($payableAmount > 0) {
//                    // Insert total sponsor bonus into bonus report
//                    $fields = array("ID", "clientID", "username", "name", "bonusType", "bonusAmount", "bonusDate");
//                    $values = array($db->getNewID(), mysql_escape_string($clientID), mysql_escape_string($clientUsername[$clientID]), mysql_escape_string($clientName[$clientID]), BONUS_PAIRING, mysql_escape_string($payableAmount), mysql_escape_string($startDate));
//                    $db->dbInsert("mlmBonusReport", $fields, $values);
//                }

                //echo date("Y-m-d H:i:s")." clientID: $clientID, remainLeft: ".$remaining[1].", remainRight: ".$remaining[2].", pairedAmount: $pairingAmount, $curPercentage, $curMaxCap, cAmount: $calculatedAmount\n";

                unset($clientBonus);
                unset($combinedBonus);
                unset($calculatedAmount);
                unset($carryFoward);
                unset($payableAmount);

            }

            // Update the batch table to completed
            $updateData = array(
                "completed"     => 1
            );
            $db->where("id", $batchID);

            if ($db->update("mlm_bonus_calculation_batch", $updateData))
                $log->write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            return true;
        }


        function calculateMatchingBonus($startDate) {

            $db             = $this->db;
            $setting        = $this->setting;
            $log            = $this->log;
            $bonusName      = "matchingBonus";
            $bonusSetting   = $this->getBonusSetting($bonusName);

            $log->write(date("Y-m-d H:i:s") . " Calculating " . $bonusName . " for " . $startDate . "\n");

            if (!$startDate) {

                $log->write(date("Y-m-d H:i:s") . " Date problem: " . $startDate . "Please check your date and run again.\n");
                return false;
            }

            $start = $startDate." 00:00:00";
            $end = $startDate." 23:59:59";

            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $startDate);
            $db->where("completed", 1);
            $db->where("paid", 1);
            $count = $db->getValue("mlm_bonus_calculation_batch", "count(*)");

            //avoid run twice or more cause the payment made twice or more
            if ($count > 0) {
                $log->write(date("Y-m-d H:i:s") . $startDate . " " . $bonusName . " has been paid. Failed to calculate.\n");
                return false;
            }

            // Get all the pairing amount from bonus pairing table
            $db->where("bonus_date", $startDate);
            $db->where("amount", 0, ">");
            $result = $db->get("mlm_bonus_pairing", NULL, array(
                "id",
                "client_id",
                "amount"
            ));

            foreach ($result as $row){
                $clientPairingAmount[$row["client_id"]] = $row["amount"];
                $clientPairingID[$row["client_id"]] = $row["id"];
            }

            //get unit price
            $unitPrice = $this->getLatestUnitPrice();
            if(!$unitPrice) {
                $log->write(date("Y-m-d H:i:s") . " Unit value is $unitPrice, do not continue.\n");
                return false;
            }

            $maxMatchingLevel = 0;

            // Get the maximum level that we can pay up
            $db->where("name", "maxMatchingLevel");
            $productResult = $db->get("rank_setting", NULL, array(

                "rank_id",
                "name",
                "value"
            ));

            foreach ($productResult as $productRow){

                $matchingLevel[$productRow["rank_id"]] = $productRow["value"];

                if ($productRow["value"] > $maxMatchingLevel)
                    $maxMatchingLevel = (int)$productRow["value"];
            }


            // Get all the members
            $db->where("type", "Client");
            $clients = $db->get("client", NULL, array(

                "id",
                "name",
                "username",
                "sponsor_id",

            ));

            foreach ($clients as $client){
                $clientIDArray[]                = $client["id"];
                $clientName[$client["id"]]      = $client["name"];
                $clientUsername[$client["id"]]  = $client["username"];
            }

            //get client setting

            $db->where("client_id", $clientIDArray, "IN");
            $db->where("name", $bonusName);
            $db->where("type", "Bonus Percentage");
            $db->orWhere("type", "Bonus Rank");
            $clientResult = $db->get("client_setting", NULL, array(
                "client_id",
                "name",
                "value",
                "reference",
                "type"
            ));

            foreach ($clientResult as $client){
                if ($client['type'] == "Bonus Percentage")
                    $matchingPercentage[$client['client_id']]    = $client['value'];
                else if ($client['type'] == "Bonus Rank"){
                    $matchingPackageID[$client['client_id']]     = $client['value'];
                }
            }

            unset($clientIDArray);

            // Generate new batchID if no problem
            $batchID = $db->getNewID();
            $insertData = array(

                "id"            => $batchID,
                "bonus_name"    => $bonusName,
                "bonus_date"    => $startDate,
                "created_at"    => $db->now()
            );
            $bonusCalculationBatchID = $db->insert("mlm_bonus_calculation_batch", $insertData);

            if ($bonusCalculationBatchID)
                $log->write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            else
                $log->write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            foreach ($clientPairingAmount as $clientID => $bonusValue) {

                $pairingID = $clientPairingID[$clientID];

//                $clientProductID = $matchingProductID[$clientID];

                //echo date("Y-m-d H:i:s")." BonusIn ID: ".$row["ID"].", client: $clientID, BV: $bonusValue\n";

                unset($uplineIDArray);

                if ($bonusSetting['bonus_overriding'] == 0) {
                    // No Overriding
                    $uplineIDArray = $this->getSponsorTreeUplines($clientID, 1, false);
                }
                else {
                    // Overriding
                    $uplineIDArray = $this->getSponsorTreeUplines($clientID, 0, false);
                }

                $level = 1;

                foreach ($uplineIDArray as $uplineID) {
                    $curPercentage      = $matchingPercentage[$uplineID];
                    $curRankID          = $matchingPackageID[$uplineID];

                    //echo date("Y-m-d H:i:s")." $uplineID % = $percentage\n";
                    if ($level > $maxMatchingLevel)
                        break;

                    $curEligibleLevel   = $matchingLevel[$curRankID];

                    if ($curEligibleLevel < $level) {
                        $log->write(date("Y-m-d H:i:s") . $uplineID . " is not eligible to receive. Eligible: " . $curEligibleLevel . " , Current: " . $level . "\n");
                        $level++;
                        continue;
                    }

                    $realPercentage = $curPercentage / 100;
                    $calculatedAmount = number_format(($bonusValue * $realPercentage), 2, ".", "");
                    //echo date("Y-m-d H:i:s")." ".number_format(($portion[$uplineID]), 2, ".", "")." = $bonusValue * $realPercentage.\n";

                    if ($calculatedAmount > 0) {
                        unset($insertData);

                        $insertData = array(

                            "id"                    => $db->getNewID(),
                            "client_id"             => $uplineID,
                            "rank_id"               => $curRankID,
                            "from_id"               => $clientID,
                            "from_pairing_id"       => $pairingID,
                            "from_pairing_amount"   => $bonusValue,
                            "from_level"            => $level,
                            "percentage"            => $curPercentage,
                            "bonus_date"            => $startDate,
                            "batch_id"              => $batchID,
                            "amount"                => $calculatedAmount,
                            "payable_amount"        => ($calculatedAmount * $unitPrice),
                            "unit_price"            => $unitPrice,
                            "paid"                  => 0,
                            "created_at"            => $db->now()

                        );

                        $id = $db->insert("mlm_bonus_matching", $insertData);

                        if ($id)
                            $log->write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_matching\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed insert into mlm_bonus_matching. Error : " . $db->getLastErrno() . "\n");

                        // Keep all bonus in array first
                        /***  not creating mlm_bonus_report table thus this one no longer use in code ***/
//                        $clientBonusArray[$uplineID] += $calculatedAmount;
                    }

                    $level++;
                }
            }

            /***  not creating mlm_bonus_report table thus this one no longer use in code ***/
//            foreach ($clientBonusArray as $clientID => $clientBonus) {
//                // Loop and insert total matching bonus into bonus report
//                $fields = array("ID", "clientID", "username", "name", "bonusType", "bonusAmount", "bonusDate");
//                $values = array($db->getNewID(), mysql_escape_string($clientID), mysql_escape_string($clientUsername[$clientID]), mysql_escape_string($clientName[$clientID]), mysql_escape_string(BONUS_MATCHING), mysql_escape_string($clientBonus), mysql_escape_string($startDate));
//                $db->dbInsert("mlmBonusReport", $fields, $values);
//            }

            // Update the batch table to completed
            $updateData = array(
                "completed"     => 1
            );
            $db->where("id", $batchID);
            if ($db->update("mlm_bonus_calculation_batch", $updateData))
                $log->write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            return true;
        }


        function paySponsorBonus($bonusDate,$bonusPayoutTime="00:00:00") {

            $db             = $this->db;
            $cash           = $this->cash;
            $log            = $this->log;
            $bonusName      = "sponsorBonus";
            $bonusSetting   = $this->getBonusSetting($bonusName);

            $log->write(date("Y-m-d H:i:s") . " Paying " . $bonusName . " for $bonusDate\n");

            if ($bonusSetting['payment'] == "Weekly") {
                // Type D for weekly payout, so we get the date range here
                $startDate = date("Y-m-d", strtotime("-6 day", strtotime($bonusDate)));
                $endDate = $bonusDate;

                $db->where("bonus_name", $bonusName);
                $db->where("bonus_date", $startDate, ">=");
                $db->where("bonus_date", $endDate, "<=");
                $db->where("completed", 1);
                $db->orderBy("id", "ASC");

                $result = $db->get("mlm_bonus_calculation_batch", NULL, array(

                    "id",
                    "paid",
                ));

                foreach($result as $row){
                    $batchID = $row['id'];
                    if ($row['paid'] == 1){
                        $log->write(date("Y-m-d H:i:s") . " $bonusDate " . $bonusName . " has been paid. Failed to payout.\n");
                        return false;
                    }
                }

                $condition = true;
            }
            else {

                $db->where("bonus_name", $bonusName);
                $db->where("bonus_date", $bonusDate);
                $db->where("completed", 1);
                $db->orderBy("id", "ASC");

                $result = $db->get("mlm_bonus_calculation_batch", NULL, array(

                    "id",
                    "paid",
                ));

                foreach($result as $row){
                    $batchID = $row['id'];
                    if ($row['paid'] == 1){
                        $log->write(date("Y-m-d H:i:s") . " $bonusDate " . $bonusName . " has been paid. Failed to payout.\n");
                        return false;
                    }
                }

                $condition = false;
            }

            if (!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                $log->write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $percentageTotal = 0;

            $paymentMethod = $this->getPaymentMethod($bonusName);
            foreach ($paymentMethod as $creditType => $percentage) {
                $percentageTotal += (float)$percentage;
                $creditPercentage[$creditType] = $percentage;
            }

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                $log->write(date("Y-m-d H:i:s") . " " . $bonusName . " payment total is not 100%. Failed to payout.\n");
                return false;
            }

            //get bonuspayout internal client id
            $db->where("name", "bonusPayout");
            $db->where("type", "Internal");
            $internalID = $db->getValue("client", "id");

            // Only select those members who are not terminated
            $db->where("mlm_bonus_sponsor.client_id = client.id");
            if ($condition == true)
                $db->where("mlm_bonus_sponsor.bonus_date", array($startDate, $endDate), "between");
            else
                $db->where("mlm_bonus_sponsor.batch_id", $batchID);
            $db->where("mlm_bonus_sponsor.paid", 0);
            $db->where("client.terminated", 0);
            $db->orderBy("mlm_bonus_sponsor.id", "ASC");
            $result = $db->get("mlm_bonus_sponsor, client", NULL, array(

                "mlm_bonus_sponsor.client_id",
                "mlm_bonus_sponsor.payable_amount",
                "mlm_bonus_sponsor.bonus_date",
                "mlm_bonus_sponsor.unit_price",
                "mlm_bonus_sponsor.batch_id",
            ));

            $batchIDArray = array();

            foreach ($result as $row){
                $clientBonusArray[$row["client_id"]]["calculationDate"] = $row["bonus_date"];
                $clientBonusArray[$row["client_id"]]["bonusDate"] = date("Y-m-d", strtotime($row["bonus_date"]." +1 day"));
                $clientBonusArray[$row["client_id"]]["amount"] += $row["payable_amount"];
                $clientBonusArray[$row["client_id"]]["unitPrice"] = $row["unit_price"];
                if (!in_array($row["batch_id"], $batchIDArray))
                    $batchIDArray[] = $row["batch_id"];
            }

            $subject        = "Sponsor Bonus Payout";
            $payDate        = date("Y-m-d", strtotime($bonusDate." +1 day"));

            foreach ($clientBonusArray as $clientID => $row) {

                $log->write(date("Y-m-d H:i:s") . " " . $clientID . " " . $row["amount"]."\n");

                // Reset the total amount
                unset($totalAmount);

//                if ($this->getSystemSetting("sponsorBonusPaymentType") == "D") {
//                    // Check whether reached weekly maxcap
//                    $row["amount"] = $this->checkWeeklyMaxCap($clientID, $row["amount"], $payDate, $bonusPayoutTime);
//                }
//
//                // Deduct maxcap and check whether to credit to another wallet
//                $row["amount"] = $this->checkBonusMaxCap($clientID, $internalID, $creditPercentage, $row["amount"], $subject, $batchID, $payDate, $bonusPayoutTime);

                foreach ($creditPercentage as $creditType => $value) {

                    $percentage     = $value / 100;
                    $amount         = $row["amount"] * $percentage;
                    $totalAmount    = 0;

                    //echo date("Y-m-d H:i:s")." $creditType: $value% * ".$row["amount"]." = $amount\n";

                    if ($totalAmount + $amount > $row["amount"]) {
                        // SUM should not be more than total
                        $log->write(date("Y-m-d H:i:s")." ***** Amount exceeded ($totalAmount + $amount > ".$row["amount"].")\n");
                        $amount = $row["amount"] - $totalAmount;
                    }

                    if ($amount > 0) {
                        // Payout to client
                        if($cash->insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $db->getNewID(), "", $db->now(), $batchID, $clientID))
                            $log->write(date("Y-m-d H:i:s") . " Successfully process insertTAccount for client : " . $clientID ."\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed to process insertTAccount for client : " . $clientID ."\n");

                        // Update the cache balance
                        $cash->getBalance($clientID, $creditType);

                    }

                    $totalAmount += $amount;
                }

                // Update the paid status for this client
                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDArray, "IN");
                if ($db->update("mlm_bonus_sponsor", array("paid" => 1)))
                    $log->write(date("Y-m-d H:i:s") . " Successfully update mlm_bonus_sponsor\n");
                else
                    $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_sponsor. Error : " . $db->getLastError() . "\n");
            }

            if (count($batchIDArray) > 0) {
                $db->where("id", $batchIDArray, "IN");
                if ($db->update("mlm_bonus_calculation_batch", array("paid" => 1)))
                    $log->write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
                else
                    $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            return true;

        }

        function payPairingBonus($bonusDate,$bonusPayoutTime="00:00:00") {

            $db             = $this->db;
            $cash           = $this->cash;
            $log            = $this->log;
            $bonusName      = "pairingBonus";
            $bonusSetting   = $this->getBonusSetting($bonusName);

            $log->write(date("Y-m-d H:i:s") . " Paying " . $bonusName . " for $bonusDate\n");

            if ($bonusSetting['payment'] == "Weekly") {
                // Type D for weekly payout, so we get the date range here
                $startDate = date("Y-m-d", strtotime("-6 day", strtotime($bonusDate)));
                $endDate = $bonusDate;

                $db->where("bonus_name", $bonusName);
                $db->where("bonus_date", $startDate, ">=");
                $db->where("bonus_date", $endDate, "<=");
                $db->where("completed", 1);
                $db->orderBy("id", "ASC");

                $result = $db->get("mlm_bonus_calculation_batch", NULL, array(

                    "id",
                    "paid",
                ));

                foreach($result as $row){
                    $batchID = $row['id'];
                    if ($row['paid'] == 1){
                        $log->write(date("Y-m-d H:i:s") . " $bonusDate " . $bonusName . " has been paid. Failed to payout.\n");
                        return false;
                    }
                }

                $condition = true;
            }
            else {

                $db->where("bonus_name", $bonusName);
                $db->where("bonus_date", $bonusDate);
                $db->where("completed", 1);
                $db->orderBy("id", "ASC");

                $result = $db->get("mlm_bonus_calculation_batch", NULL, array(

                    "id",
                    "paid",
                ));

                foreach($result as $row){
                    $batchID = $row['id'];
                    if ($row['paid'] == 1){
                        $log->write(date("Y-m-d H:i:s") . " $bonusDate " . $bonusName . " has been paid. Failed to payout.\n");
                        return false;
                    }
                }

                $condition = false;
            }

            if (!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                $log->write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $percentageTotal = 0;

            $paymentMethod = $this->getPaymentMethod($bonusName);
            foreach ($paymentMethod as $creditType => $percentage) {
                $percentageTotal += (float)$percentage;
                $creditPercentage[$creditType] = $percentage;
            }

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                $log->write(date("Y-m-d H:i:s") . " " . $bonusName . " payment total is not 100%. Failed to payout.\n");
                return false;
            }

            //get bonuspayout internal client id
            $db->where("name", "bonusPayout");
            $db->where("type", "Internal");
            $internalID = $db->getValue("client", "id");


            // Only select those members who are not terminated
            $db->where("mlm_bonus_pairing.client_id = client.id");
            if ($condition == true)
                $db->where("mlm_bonus_pairing.bonus_date", array($startDate, $endDate), "between");
            else
                $db->where("mlm_bonus_pairing.batch_id", $batchID);
            $db->where("mlm_bonus_pairing.paid", 0);
            $db->where("client.terminated", 0);
            $db->orderBy("mlm_bonus_pairing.id", "ASC");
            $result = $db->get("mlm_bonus_pairing, client", NULL, array(

                "mlm_bonus_pairing.client_id",
                "mlm_bonus_pairing.payable_amount",
                "mlm_bonus_pairing.bonus_date",
                "mlm_bonus_pairing.unit_price",
                "mlm_bonus_pairing.batch_id",
            ));

            $batchIDArray = array();

            foreach ($result as $row){
                $clientBonusArray[$row["client_id"]]["calculationDate"] = $row["bonus_date"];
                $clientBonusArray[$row["client_id"]]["bonusDate"] = date("Y-m-d", strtotime($row["bonus_date"]." +1 day"));
                $clientBonusArray[$row["client_id"]]["amount"] += $row["payable_amount"];
                $clientBonusArray[$row["client_id"]]["unitPrice"] = $row["unit_price"];
                if (!in_array($row["batch_id"], $batchIDArray))
                    $batchIDArray[] = $row["batch_id"];
            }

            $subject        = "Pairing Bonus Payout";
            $payDate        = date("Y-m-d", strtotime($bonusDate." +1 day"));

            foreach ($clientBonusArray as $clientID => $row) {

                $log->write(date("Y-m-d H:i:s") . " " . $clientID . " " . $row["amount"]."\n");

                // Reset the total amount
                unset($totalAmount);

//                if ($this->getSystemSetting("sponsorBonusPaymentType") == "D") {
//                    // Check whether reached weekly maxcap
//                    $row["amount"] = $this->checkWeeklyMaxCap($clientID, $row["amount"], $payDate, $bonusPayoutTime);
//                }
//
//                // Deduct maxcap and check whether to credit to another wallet
//                $row["amount"] = $this->checkBonusMaxCap($clientID, $internalID, $creditPercentage, $row["amount"], $subject, $batchID, $payDate, $bonusPayoutTime);

                foreach ($creditPercentage as $creditType => $value) {

                    $percentage = $value / 100;
                    $amount = $row["amount"] * $percentage;
                    $totalAmount    = 0;

                    //echo date("Y-m-d H:i:s")." $creditType: $value% * ".$row["amount"]." = $amount\n";

                    if ($totalAmount + $amount > $row["amount"]) {
                        // SUM should not be more than total
                        $log->write(date("Y-m-d H:i:s")." ***** Amount exceeded ($totalAmount + $amount > ".$row["amount"].")\n");
                        $amount = $row["amount"] - $totalAmount;
                    }

                    if ($amount > 0) {

                        // Payout to client
                        if($cash->insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $db->getNewID(), "", $db->now(), $batchID, $clientID))
                            $log->write(date("Y-m-d H:i:s") . " Successfully process insertTAccount for client : " . $clientID ."\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed to process insertTAccount for client : " . $clientID ."\n");

                        // Update the cache balance
                        $cash->getBalance($clientID, $creditType);

                    }

                    $totalAmount += $amount;
                }

                // Update the paid status for this client
                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDArray, "IN");
                if ($db->update("mlm_bonus_pairing", array("paid" => 1)))
                    $log->write(date("Y-m-d H:i:s") . " Successfully update mlm_bonus_pairing\n");
                else
                    $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_pairing. Error : " . $db->getLastError() . "\n");
            }

            if (count($batchIDArray) > 0) {
                $db->where("id", $batchIDArray, "IN");
                if ($db->update("mlm_bonus_calculation_batch", array("paid" => 1)))
                    $log->write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
                else
                    $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            return true;
        }

        function payMatchingBonus($bonusDate,$bonusPayoutTime="00:00:00") {

            $db             = $this->db;
            $cash           = $this->cash;
            $log            = $this->log;
            $bonusName      = "matchingBonus";
            $bonusSetting   = $this->getBonusSetting($bonusName);

            $log->write(date("Y-m-d H:i:s") . " Paying " . $bonusName . " for $bonusDate\n");

            if ($bonusSetting['payment'] == "Weekly") {
                // Type D for weekly payout, so we get the date range here
                $startDate = date("Y-m-d", strtotime("-6 day", strtotime($bonusDate)));
                $endDate = $bonusDate;

                $db->where("bonus_name", $bonusName);
                $db->where("bonus_date", $startDate, ">=");
                $db->where("bonus_date", $endDate, "<=");
                $db->where("completed", 1);
                $db->orderBy("id", "ASC");

                $result = $db->get("mlm_bonus_calculation_batch", NULL, array(

                    "id",
                    "paid",
                ));

                foreach($result as $row){
                    $batchID = $row['id'];
                    if ($row['paid'] == 1){
                        $log->write(date("Y-m-d H:i:s") . " $bonusDate " . $bonusName . " has been paid. Failed to payout.\n");
                        return false;
                    }
                }

                $condition = true;
            }
            else {

                $db->where("bonus_name", $bonusName);
                $db->where("bonus_date", $bonusDate);
                $db->where("completed", 1);
                $db->orderBy("id", "ASC");

                $result = $db->get("mlm_bonus_calculation_batch", NULL, array(

                    "id",
                    "paid",
                ));

                foreach($result as $row){
                    $batchID = $row['id'];
                    if ($row['paid'] == 1){
                        $log->write(date("Y-m-d H:i:s") . " $bonusDate " . $bonusName . " has been paid. Failed to payout.\n");
                        return false;
                    }
                }

                $condition = false;
            }

            if (!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                $log->write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $percentageTotal = 0;

            $paymentMethod = $this->getPaymentMethod($bonusName);
            foreach ($paymentMethod as $creditType => $percentage) {
                $percentageTotal += (float)$percentage;
                $creditPercentage[$creditType] = $percentage;
            }

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                $log->write(date("Y-m-d H:i:s") . " " . $bonusName . " payment total is not 100%. Failed to payout.\n");
                return false;
            }

            //get bonuspayout internal client id
            $db->where("name", "bonusPayout");
            $db->where("type", "Internal");
            $internalID = $db->getValue("client", "id");


            // Only select those members who are not terminated
            $db->where("mlm_bonus_matching.client_id = client.id");
            if ($condition == true)
                $db->where("mlm_bonus_matching.bonus_date", array($startDate, $endDate), "between");
            else
                $db->where("mlm_bonus_matching.batch_id", $batchID);
            $db->where("mlm_bonus_matching.paid", 0);
            $db->where("client.terminated", 0);
            $db->orderBy("mlm_bonus_matching.id", "ASC");
            $result = $db->get("mlm_bonus_matching, client", NULL, array(

                "mlm_bonus_matching.client_id",
                "mlm_bonus_matching.payable_amount",
                "mlm_bonus_matching.bonus_date",
                "mlm_bonus_matching.unit_price",
                "mlm_bonus_matching.batch_id",
            ));

            $batchIDArray = array();

            foreach ($result as $row){
                $clientBonusArray[$row["client_id"]]["calculationDate"] = $row["bonus_date"];
                $clientBonusArray[$row["client_id"]]["bonusDate"] = date("Y-m-d", strtotime($row["bonus_date"]." +1 day"));
                $clientBonusArray[$row["client_id"]]["amount"] += $row["payable_amount"];
                $clientBonusArray[$row["client_id"]]["unitPrice"] = $row["unit_price"];
                if (!in_array($row["batch_id"], $batchIDArray))
                    $batchIDArray[] = $row["batch_id"];
            }



            $subject        = "Matching Bonus Payout";
            $payDate        = date("Y-m-d", strtotime($bonusDate." +1 day"));

            foreach ($clientBonusArray as $clientID => $row) {

                $log->write(date("Y-m-d H:i:s") . " " . $clientID . " " . $row["amount"]."\n");

                // Reset the total amount
                unset($totalAmount);

//                if ($this->getSystemSetting("sponsorBonusPaymentType") == "D") {
//                    // Check whether reached weekly maxcap
//                    $row["amount"] = $this->checkWeeklyMaxCap($clientID, $row["amount"], $payDate, $bonusPayoutTime);
//                }
//
//                // Deduct maxcap and check whether to credit to another wallet
//                $row["amount"] = $this->checkBonusMaxCap($clientID, $internalID, $creditPercentage, $row["amount"], $subject, $batchID, $payDate, $bonusPayoutTime);

                foreach ($creditPercentage as $creditType => $value) {

                    $percentage = $value / 100;
                    $amount = $row["amount"] * $percentage;

                    //echo date("Y-m-d H:i:s")." $creditType: $value% * ".$row["amount"]." = $amount\n";

                    if ($totalAmount + $amount > $row["amount"]) {
                        // SUM should not be more than total
                        $log->write(date("Y-m-d H:i:s")." ***** Amount exceeded ($totalAmount + $amount > ".$row["amount"].")\n");
                        $amount = $row["amount"] - $totalAmount;
                    }

                    if ($amount > 0) {

                        // Payout to client
                        if($cash->insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $db->getNewID(), "", $db->now(), $batchID, $clientID))
                            $log->write(date("Y-m-d H:i:s") . " Successfully process insertTAccount for client : " . $clientID ."\n");
                        else
                            $log->write(date("Y-m-d H:i:s") . " Failed to process insertTAccount for client : " . $clientID ."\n");

                        // Update the cache balance
                        $cash->getBalance($clientID, $creditType);

                    }

                    $totalAmount += $amount;
                }

                // Update the paid status for this client
                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDArray, "IN");
                if ($db->update("mlm_bonus_matching", array("paid" => 1)))
                    $log->write(date("Y-m-d H:i:s") . " Successfully update mlm_bonus_sponsor\n");
                else
                    $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_sponsor. Error : " . $db->getLastError() . "\n");
            }

            if (count($batchIDArray) > 0) {
                $db->where("id", $batchIDArray, "IN");
                if ($db->update("mlm_bonus_calculation_batch", array("paid" => 1)))
                    $log->write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
                else
                    $log->write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            return true;
        }

        function clearTodayPlacementBonus() {

            $db         = $this->db;
            $setting    = $this->setting;

            $db->where("name", "maxPlacementPositions");
            $maxPlacementPositions = $db->getValue("system_settings", "value");
            for ($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "'Placement Total $i'";
                $clientSettingName[] = "'Placement CF Total $i'";
            }

            if (count($clientSettingName) > 0) {
                // Update all to 0, reset for the new day

                $db->where('name', $clientSettingName, "IN");
                if ($db->update("client_setting", array("value" => 0)))
                    return true;
                else
                    return false;

            }

        }

        function getLatestUnitPrice(){
            $db         = $this->db;
            $tableName  = 'mlm_unit_price';

            $db->where('type', 'purchase');
            $db->orderBy('created_at', 'DESC');
            $unitPrice = $db->getValue($tableName, 'unit_price');
            if($unitPrice)
                return $unitPrice;

            return 1.00;
        }

        function getSystemSetting($settingName){

            $db         = $this->db;
            $tableName = "system_settings";

            $db->where("name", $settingName);
            $result = $db->getValue($tableName, "value");

            return $result;
        }

        function getBonusSetting($bonusName){

            $db         = $this->db;
            $tableName  = "mlm_bonus";
            $column     = array(

                "calculation",
                "payment",
                "(SELECT value FROM mlm_bonus_setting WHERE mlm_bonus_setting.bonus_id = mlm_bonus.id AND mlm_bonus_setting.name = 'bonusOverriding') AS bonus_overriding"
            );

            $db->where("name", $bonusName);
            $result = $db->getOne($tableName, $column);

            return $result;
        }

        public function getPlacementTreeUplines($clientID, $director = true) {

            $db         = $this->db;
            $tableName  = "tree_placement";
            $data       = array();
            $column     = array(

                "client_id",
                "client_position",
            );

            if ($director != true)
                $db->where("level", "0", ">");

            $db->where("client_id", $clientID);
            $traceKey = $db->getValue($tableName, "trace_key");

            $changed = str_replace(array('-1<', '-1>', '-1|'), ',', $traceKey);
            $uplineIDArray = explode(',', $changed, -1);
            //reverse make it's order descending
            $uplineIDArray = array_reverse($uplineIDArray);

            $db->where("client_id", $clientID);
            $uplineIDDetails = $db->getOne($tableName, $column);

            $data[] = $uplineIDDetails;

            foreach($uplineIDArray as $uplineID){

                $db->where("client_id", $uplineID);
                $uplineIDDetails = $db->getOne($tableName, $column);

                $data[] = $uplineIDDetails;
            }

            return $data;

        }

        function getSponsorTreeUplines($clientID, $limit, $includeSelf) {

            $db         = $this->db;
            $tableName  = "tree_sponsor";
            $data       = array();

            $db->where("client_id", $clientID);
            $traceKey = $db->getValue($tableName, "trace_key");

            $uplineIDArray = explode("/", $traceKey);

            if ($includeSelf != true )
                unset($uplineIDArray[count($uplineIDArray) - 1]);

            if (!empty($limit)){
                for($count = 1; $count <= $limit; $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }
            else{
                for($count = 1; $count <= count($uplineIDArray); $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }

            return $data;
        }

        function getPaymentMethod($paymentReference) {

            $db             = $this->db;
            $tableName      = "mlm_bonus_payment_method";
            $paymentMethod  = array();
            $column         = array(

                "id",
                "(SELECT name FROM mlm_bonus WHERE id = bonus_id) AS bonus_name",
                "credit_type",
                "percentage"

            );

            //payment reference is the id of the bonus
            if ($paymentReference) {
                $sq = $db->subQuery();
                $sq->where("name", $paymentReference);
                $sq->getOne("mlm_bonus", "id");
                $db->where("bonus_id", $sq);
            }

            $result = $db->get($tableName, NULL, $column);

            foreach ($result as $row){
                $paymentMethod[$row['credit_type']] = $row['percentage'];
            }

            return $paymentMethod;
        }

        function getBonusSettingAll(){

            $db         = $this->db;
            $tableName = "mlm_bonus";

            $result = $db->get($tableName);
            $data["bonusSettingList"] = $result;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>$statusMsg, 'data' => $data);

        }

        function getBonusSettingDetails($params){

            $id = trim($params['id']);
            $db         = $this->db;
            $tableName = "mlm_bonus";
            $db->where("id", $id);
            // $db->where("name", $settingName);
            $result = $db->getOne($tableName);
            $data["bonusSettingDetails"] = $result;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>$statusMsg, 'data' => $data);
        }

        function addBonusSetting($params)
        {
            $db = $this->db;
            
            $name = trim($params['name']);
            $bonus_source = trim($params['bonus_source']);
            $calculation = trim($params['calculation']);
            $calculation_start = trim($params['calculation_start']);
            $payment = trim($params['payment']);
            $payment_start = trim($params['payment_start']);
            $priority = trim($params['priority']);
            $allow_rank_maintain = trim($params['allow_rank_maintain']);
            $disabled = trim($params['disabled']);
            $languageCode = trim($params['languageCode']);
            
            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Bonus Name", 'data'=>"");
            
            if(strlen($calculation_start) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Calculation Start Time", 'data'=>"");
            
            if(strlen($payment_start) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Payment Start Time", 'data'=>"");
            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Priority", 'data'=>"");

            if(strlen($allow_rank_maintain) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Allow Rank Maintain", 'data'=>"");
            
            $fields = array("name", "bonus_source", "calculation", "calculation_start", "payment", "payment_start","priority", "allow_rank_maintain", "disabled","languageCode");
            $values = array($name, $bonus_source, $calculation, $calculation_start, $payment, $payment_start, $priority, $allow_rank_maintain,$disabled,$languageCode);

            $arrayData = array_combine($fields, $values);
            try{
                $result = $db->insert("mlm_bonus", $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to add new bonus", 'data'=>"");
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully Added", 'data'=>"");
        }

        function editBonusSetting($params){

            $db         = $this->db;

            $id = trim($params['id']);
            $name = trim($params['name']);
            $bonus_source = trim($params['bonus_source']);
            $calculation = trim($params['calculation']);
            $calculation_start = trim($params['calculation_start']);
            $payment = trim($params['payment']);
            $payment_start = trim($params['payment_start']);
            $priority = trim($params['priority']);
            $allow_rank_maintain = trim($params['allow_rank_maintain']);
            $disabled = trim($params['disabled']);
            $languageCode = trim($params['languageCode']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Bonus ID does not exist", 'data'=>"");
            
            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Bonus Name", 'data'=>"");
            
            if(strlen($calculation_start) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Calculation Start Time", 'data'=>"");
            
            if(strlen($payment_start) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Payment Start Time", 'data'=>"");
            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Priority", 'data'=>"");

            if(strlen($allow_rank_maintain) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Allow Rank Maintain", 'data'=>"");
            $db->where('id', $id);
            $result = $db->getOne('mlm_bonus');

            if (!empty($result)) {
                $fields = array("name", "bonus_source", "calculation", "calculation_start", "payment", "payment_start","priority", "allow_rank_maintain", "disabled","languageCode");
                $values = array($name, $bonus_source, $calculation, $calculation_start, $payment, $payment_start, $priority, $allow_rank_maintain,$disabled,$languageCode);
                
                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("mlm_bonus", $arrayData);
                
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Bonus Successfully Updated", 'data' => "");
                
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Bonus ID", 'data'=>"");
            }
        }
    }




?>