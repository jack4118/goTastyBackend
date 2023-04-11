<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');

    log::setupLogPath(__DIR__, __FILE__);

    ## ----------------------------- New Structure ----------------------------- ## 
    $bonusDate = date("Y-m-d", strtotime("-1 day"));

    // $db->where('status','Active');
    // $validProductID = $db->map('id')->get('mlm_product',null,'id');

    if ($argv[1]) {
        // If a bonus date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            // $date = $argv[1];
            $bonusDate = $argv[1];
        }
    }

    // $productID = $argv[1];
    // $date = $argv[1];
    // $time = $argv[2];

    // if(!$time){
    //     Log::write(date("Y-m-d H:i:s")." Invalid Time.\n");
    //     exit;
    // }else{
    //     if(preg_match("#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#", $time)){
    //         $bonusDate = $date." ".$time;
    //     } else {
    //         Log::write(date("Y-m-d H:i:s")." Wrong Time Format.\n");
    //         exit;
    //     }
    // }

    // if(!$productID || !in_array($productID, $validProductID)){
    //     Log::write(date("Y-m-d H:i:s")." Invalid Product ID.\n");
    //     exit;
    // }

    $db->where('name','processBonusSwitch');
    $stgRes = $db->getOne('system_settings','id,value');
    $processBonusSwitch = $stgRes['value'];
    $stgID = $stgRes['id'];
    while ($processBonusSwitch) {
        Log::write(date('Y-m-d H:i:s')." Waiting for Bonus Process...\n");

        $db->where('id',$stgID);
        $processBonusSwitch = $db->getValue('system_settings','value');

        sleep(1);
    }

    $db->where('name','resetBonusSwitch');
    $stgRes = $db->getOne('system_settings','id,value');
    $resetBonusSwitch = $stgRes['value'];
    $stgID = $stgRes['id'];
    while ($resetBonusSwitch) {
        Log::write(date('Y-m-d H:i:s')." Waiting for Reset Bonus Process...\n");

        $db->where('id',$stgID);
        $resetBonusSwitch = $db->getValue('system_settings','value');

        sleep(1);
    }

    $db->where('name','resetBonusSwitch');
    $db->update('system_settings',array("value" => 1));

    unset($isAdminSet);
    $queueID    = $argv[2];
    $creatorID  = $argv[3];
    $creatorAt  = $argv[4]." ".$argv[5];
    if($queueID && $creatorID && $creatorAt){
        $isAdminSet = 1;
        $logBaseName = basename(__FILE__, '.php');
        Log::setupLogPath(__DIR__, $logBaseName."-Admin");
    }

    $bonusArr = $db->subQuery();
    $bonusArr->where('disabled',0);
    $bonusArr->get('mlm_bonus',null,'name');
    $db->where('bonus_name',$bonusArr,"IN");
    $db->groupBy('bonus_date');
    $db->orderBy('bonus_date','DESC');
    $latestBonusDate = $db->getValue('mlm_bonus_calculation_batch','bonus_date');
    if(strtotime($bonusDate) > strtotime($latestBonusDate)){
        Log::write(date("Y-m-d H:i:s")." Bonus haven't run, failed to reset. \n");
        $db->where('name','resetBonusSwitch');
        $db->update('system_settings',array("value" => 0));
        if($isAdminSet){
            return false;
        }else{
            exit();
        }
    }
    $tgtBonusDate = $bonusDate;
    $bonusDate = $latestBonusDate;

    ## ----------------------------- End of New Structure ----------------------------- ##
    while (strtotime($tgtBonusDate) <= strtotime($bonusDate)) {
        Log::write(date("Y-m-d H:i:s")." Start to run Reset Bonus - ".$bonusDate." isAdminRun : ".$isAdminSet."\n");

        // select all batchID from mlm_bonus_calculation_batch
        /*$db->where("DATE(created_at)", $bonusDate);
        $db->update("mlm_bonus_in", array("paid" => 0));*/

        $db->where("disabled", "0");
        $bonusAry = $db->map("name")->get("mlm_bonus", null, "name, table_name");

        foreach($bonusAry as $bonusNames => $tableNames){
            $batchAry[$bonusNames] = $bonusNames;
        }

        $batchAry[] = "calculateRank#couple";
        $batchAry[] = "calculateRank#fizMemberUpgrade";
        $batchAry[] = "calculateRank#starterKit";

        $db->where("bonus_name", $batchAry, "IN");
        $db->where('bonus_date', $bonusDate);
        // $db->where('product_id', $productID);
        $result = $db->get('mlm_bonus_calculation_batch',null, 'id');
        foreach ($result as $key => $value) {
            $bonusBatchIDArray[$value['id']] = $value['id'];
        }

        if(!$bonusBatchIDArray){
            Log::write(date("Y-m-d H:i:s")." Bonus not found. \n");
            exit();
        }

        $db->where('type','Client');
        $clientData = $db->map('id')->get('client',null,'id,username,name');

        //delete Bonus Report
        $db->where('bonus_date', date("Y-m-d", strtotime($bonusDate)));
        $db->delete('mlm_bonus_report');
        $db->optimize('mlm_bonus_report');

        $bonusTableAry = $bonusAry;
        // delete auto withdrawal
        // $bonusTableAry[]  = "mlm_withdrawal";

        foreach ($bonusTableAry as $bonusType => $tableName) {
            if($tableName != "mlm_withdrawal") $db->where("paid", "1", "<=");
            $db->where('batch_id', $bonusBatchIDArray, "in");
            $db->delete($tableName);
            $db->optimize($tableName);

            $db->where('DATE(bonus_date)',date("Y-m-d", strtotime($bonusDate)));
            $db->groupBy('client_id');
            if($tableName == "mlm_bonus_enrollment"){
                $paidAmtArr = $db->map('client_id')->get($tableName,null,'client_id,SUM(amount) as paidAmount');
            }else{
                $paidAmtArr = $db->map('client_id')->get($tableName,null,'client_id,SUM(payable_amount) as paidAmount');
            }
            foreach ($paidAmtArr as $clientID => $paidAmount) {
                unset($insertData);
                $insertData = array(
                    "client_id"     => $clientID,
                    "bonus_date"    => date("Y-m-d", strtotime($bonusDate)),
                    "bonus_type"    => $bonusType,
                    "bonus_amount"  => $paidAmount,
                    // "username"      => $clientData[$clientID]['username'],
                    // "name"          => $clientData[$clientID]['name'],
                );
                $db->insert('mlm_bonus_report',$insertData);
            }
        }

        //delete Acc Record
        $accDate = date("Ymd", strtotime($bonusDate."  + 1 day"));
        $result = $db->rawQuery('SHOW TABLES LIKE "acc_credit_'.$accDate.'"');
        foreach ($result as $array) {
            $db->where('batch_id', $bonusBatchIDArray, "in");
            $db->delete('acc_credit_'.$accDate);
            $db->optimize('acc_credit_'.$accDate);

        }

        $db->where('batch_id', $bonusBatchIDArray, "in");
        $db->delete('credit_transaction');
        $db->optimize('credit_transaction');

        //delete BonusCalculation Batch
        $db->where('id', $bonusBatchIDArray, "in");
        $db->delete('mlm_bonus_calculation_batch');
        $db->optimize('mlm_bonus_calculation_batch');

        $rankDate = date("Y-m-d 23:59:59", strtotime($bonusDate));
        $db->where('created_at',$rankDate);
        $db->delete('client_rank');
        $db->optimize('client_rank');

        // Revert Inactive Member
        $db->where('name', 'stayActivePVP');
        $activeSettings = $db->getOne('system_settings', 'value, reference');
        $activePeriod = $activeSettings['reference'];

        $currentDateTime = date("Y-m-d 00:00:00", strtotime($bonusDate." +1 day"));
        $expiredActivedDate = date("Y-m-d", strtotime($currentDateTime." -".$activePeriod));

        $db->join('client_sales b','b.client_id = a.id');
        $db->where('b.activated', '0');
        $db->where('DATE(a.active_date)', $expiredActivedDate);
        $db->orderBy('a.id', 'ASC');
        $inactiveMemberAry = $db->get('client a', null, 'a.id as client_id, b.activated');
        foreach ($inactiveMemberAry as $memberData) {
            $clientID = $memberData['client_id'];
            // Inactive client status
            $db->where('client_id', $clientID);
            $db->update('client_sales', array('activated' => 1));

            // Update client Sales data
            Custom::updateClientSales($clientID, "", "", "activate", $currentDateTime);

            // // Insert Update Rank Queue
            // unset($insertData, $jsonData);
            // $jsonData['dateTime'] = $currentDateTime;
            // $insertData = array(
            //     "queue_type" => "calculateRank",
            //     "client_id"  => $clientID,
            //     "data"       => json_encode($jsonData),
            //     "created_at" => date('Y-m-d H:i:s'),
            // );
            // $db->insert('queue',$insertData);
        }

        $bonusDate = date('Y-m-d',strtotime($bonusDate." -1 days"));
    }

    $db->groupBy('date');
    $db->orderBy('date','DESC');
    $closingDate = $db->getValue('acc_closing','date');

    // Remove ACC Closing
    if($closingDate){
        Log::write(date("Y-m-d H:i:s")." Clear ACC Closing from ".$tgtBonusDate." to ".$closingDate.". \n");

        $db->where('date',$tgtBonusDate,">=");
        $db->where('date',$closingDate,"<=");
        $db->delete('acc_closing');
        $db->optimize('acc_closing');

        $db->where('closing_date',$tgtBonusDate,">=");
        $db->where('closing_date',$closingDate,"<=");
        $db->delete('acc_closing_batch');

        $sq = $db->subQuery();
        $sq->get('credit', NULL, 'name');
        $db->where('name',$sq,"IN");
        $db->update('client_setting', array('reference' => ''));
    }

    if($isAdminSet){
        $db->where('id',$queueID);
        $db->where('processed',2);
        $db->update('queue',array('processed'=>1));
    }

    $db->where('name','resetBonusSwitch');
    $db->update('system_settings',array("value" => 0));

    Log::write(date("Y-m-d H:i:s")."Done Reset Bonus. \n");

?>