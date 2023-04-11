<?php

    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');

    Log::setupLogPath(__DIR__, __FILE__);

    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;
    $processBuySell = Setting::$systemSetting["processBuySell"];

    while($processBuySell){
        General::insertDailyTable("acc_credit","process");

        Setting::setupSysSetting($config);
        $processBuySell = Setting::$systemSetting["processBuySell"];
        $closeTradeTime = Setting::$systemSetting["closeTradeTime"];
        $closeTradeTimeAry = json_decode($closeTradeTime, true);

        $dateTime = date("H:i:s");
        if(strtotime($dateTime) >= strtotime($closeTradeTime["startTime"]) && strtotime($dateTime) <= strtotime($closeTradeTime["endTime"])){
            $db->where("name", "processBuySell");
            $db->update("system_settings", array("value" => "0"));
            $processBuySell = 0;
        }else if(strtotime($dateTime) > strtotime($closeTradeTime["endTime"]) && $processBuySell == 0){
            $db->where("name", "processBuySell");
            $db->update("system_settings", array("value" => "1"));
            $processBuySell = 1;
            Log::write(date("Y-m-d H:i:s")." Process is started back.\n");
        }

        if(!$processBuySell){
            Log::write(date("Y-m-d H:i:s")." Process is stopped.\n");
            break;
        }

        $coinDataAry = Trading::getTrdCoin();
        foreach ($coinDataAry as $coinDataRow) {
            $timeBuyLimitAry = $coinDataRow["timeBuyLimit"];
        }
        
        krsort($timeBuyLimitAry);
        foreach ($timeBuyLimitAry as $hrs => $value) {
            $hitHrsAry[] = $hrs;
        }

        if(in_array(date("H"), $hitHrsAry)){
            //update to 0
            $db->where("name", "processResetQueue");
            $processResetQueue = $db->getValue("system_settings", "value");

            if($processResetQueue == 0){
                $updatedData = array(
                                        "value" => 1,
                                        "type" => $hitHrs,
                                        "reference" => date("Y-m-d H:i:s"),
                                    );
                $db->where("name", "processResetQueue");
                $db->update("system_settings", $updatedData);

                $processResetQueue = 2; //2 mean run sell queue, 1 means done run, 0 means pre-set
                Log::write(date("Y-m-d H:i:s")." Process Reset Queue is running.\n");
            }
            

        }else{
            //update to 0
            $updatedData = array(
                                    "value" => 0,
                                    "reference" => date("Y-m-d H:i:s"),
                                );
            $db->where("name", "processResetQueue");
            $db->where("value", "1");
            $db->update("system_settings", $updatedData);
            $processResetQueue = 0;
        }

        if($processResetQueue == 2){
            //run sell queue
            $db->where("status", 1); //take inactive sell queue
            $queueRes = $db->get("trd_sell_queue", null, "id, client_id, trd_transaction_id");
            
            foreach ($queueRes as $queueRow) {
                $dateTime = date("Y-m-d H:i:s");

                Log::write(date("Y-m-d H:i:s")." trnxID: ".$queueRow["trd_transaction_id"]." is matching.\n");

                $matchParams = array(
                                        "trnxID" => $queueRow["trd_transaction_id"],
                                        "dateTime" => $dateTime,
                                    );
                $returnData = Trading::matchQueue($matchParams, "queue");
            }
        }

        $db->where("status", "Scheduled");
        $db->orderBy("created_at", "ASC");
        $trdTrnxRes = $db->get("trd_transaction", null, "id, client_id, coin_type, price, left_quantity, updated_at");
        if($db->count <= 0){
            Log::write(date("Y-m-d H:i:s")." no transaction found.\n");
        }

        foreach ($trdTrnxRes as $trdTrnxRow) {
            $dateTime = date("Y-m-d H:i:s");

            if(strtotime($dateTime) >= strtotime(date("Y-m-d ".$closeTradeTime["startTime"])) && strtotime($dateTime) <= strtotime(date("Y-m-d ".$closeTradeTime["endTime"]))){
                $db->where("name", "processBuySell");
                $db->update("system_settings", array("value" => "0"));
                Log::write(date("Y-m-d H:i:s")." Process is stopped.\n");
                break;
            }

            if(in_array(date("H", strtotime($dateTime)), $hitHrsAry) && $processResetQueue == 0){
                Log::write(date("Y-m-d H:i:s")." Process Reset Queue is start.\n");
                break;
            }

            Log::write(date("Y-m-d H:i:s")." trnxID: ".$trdTrnxRow["id"]." is matching.\n");

            $matchParams = array(
                                    "trnxID" => $trdTrnxRow["id"],
                                    "dateTime" => $dateTime,
                                );
            $returnData = Trading::matchQueue($matchParams, "trnx");
        }
    }

?>
