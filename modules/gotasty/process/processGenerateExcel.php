<?php

    $currentPath = __DIR__;
    include_once($currentPath . '/../include/classlib.php');
    include_once($currentPath . '/../language/lang_all.php');

    General::$currentLanguage = "english";
    General::$translations = $translations;
    
    $processEnable = Setting::$systemSetting['processExport'];
    if ($processEnable == 1) {
        die("Process is Running!\n");
    }

    $db->where('status', 'Success');
    $db->where('end_time', '0000-00-00 00:00:00', '!=');
    $db->where('end_time', date("Y-m-d 00:00:00"), '<');
    $update = array("status" => "Deleted");
    $db->update('mlm_export', $update);

    $db->where('status', 'Pending');
    $requestAry = $db->get("mlm_export", null, 'command, params, id, type, file_name, key_ary, header_ary, title_key, total_ary,creator_id,creator_type');
    if (empty($requestAry)) {
        $return = Excel::updateSystemStatus("processExport", "0");
        die(date("Y-m-d H:i:s") . " No request at the moment!\n");
    }

    // $return = Excel::updateSystemStatus("processExport", "1");
    // if($return['status'] != 'ok') die(date("Y-m-d H:i:s")." ".$return['statusMsg']."\n");
    echo date("Y-m-d H:i:s") . " processExport: " . $return['statusMsg'] . "\n";

    $maxRows = 200000;//number of rows a generated Excel can have before splitting off a new file

    foreach ($requestAry AS $row) {
        $command = $row['command'];

        unset($update, $return, $classReturn);
        $update = array("status" => "Processing", "start_time" => date("Y-m-d H:i:s"), "progress" => 1, "error_msg" => "");
        Excel::updateExcelReqStatus($update, $row['id']);

        $classReturn = Excel::checkFunctionClass($row['command']);
        if (empty($classReturn)) {
            Excel::excelReqFailed("class not found", $row['id']);
            continue;
        }
        Excel::updateExcelReqStatus(array("progress" => 10), $row['id']);
        $params = json_decode($row['params'], true);
        ## execute command base on search data
        $params["fromExport"] = 1;
        $params["seeAll"] = 1;
        
        $return = $classReturn->$command($params);
        if ($return['status'] != 'ok') {
            Excel::excelReqFailed($return['statusMsg'], $row['id']);
            continue;
        }

        echo $return['statusMsg'] . "\n";

        $allDataAry = $return['data'][$row['title_key']];

        $headerAry = json_decode($row['header_ary'], true);
        $keyAry = json_decode($row['key_ary'], true);
        $totalAry = json_decode($row['total_ary'], true);
        $titleAry = $return['data']['title_ary'];
        $grandTotalList = $return['data']['grandTotalList'];

        if($return['data']['newCommand']){
            $command = $return['data']['newCommand'];
        }

        // print_r($return['data']);
        unset($cmd, $cmd2);
        if ($config['frontendServerIP'] && $config['frontendServerIP'] != '127.0.0.1' && $config['frontendPath']) {
            $cmd = "scp " . __DIR__ . "/../xlsx/" . $row['file_name'] . " root@" . $config['frontendServerIP'] . ":" . $config['frontendPath'] . "/admin/xlsx/";
            if ($command == 'getLanguageCodeList') {
                $cmd2 = "scp " . __DIR__ . "/../xlsx/" . $row['file_name'] . " root@" . $config['frontendServerIP'] . ":" . $config['frontendPath'] . "/superAdmin/xlsx/";
            }
        } else if ($config['frontendPath']) {
            $cmd = "cp " . __DIR__ . "/../xlsx/" . $row['file_name'] . " " . $config['frontendPath'] . "/admin/xlsx/";
            if ($command == 'getLanguageCodeList') {
                $cmd2 = "cp " . __DIR__ . "/../xlsx/" . $row['file_name'] . " " . $config['frontendPath'] . "/superAdmin/xlsx/";
            }
        }

        if($command == "getGroupPairingBonusReport"){
            $specialFlag = $return['data']["groupLimit"];
        }

        if($command == "getBonusPayoutSummary" || $command == "getBonusPayoutSummaryMonetary"){
            $totalAry = $return['data']["totalBonusReport"];
        }

        if ($command == "getSalesPurchaseReport") {
            $specialFlag = $return['data']['creditArray'];
            $totalAry = $return['data']['totalArray'];
        }

        if(!in_array($command,array('getSalesPurchaseReport','getFundInSalesReport','getBonusPayoutSummary','getBonusPayoutSummaryMonetary','getFirstPairingBonusReport','getGroupPairingBonusReport', 'getAdminOrderListing'))) {
            Excel::exportExcelNew($allDataAry,$headerAry,$keyAry,$totalAry,$row['file_name'],$row['id'],$titleAry,$grandTotalList,$command);
        } else {
            Excel::exportExcel($allDataAry, $headerAry, $keyAry, $totalAry, $row['file_name'], $row['id'], $titleAry, $grandTotalList, $command, $specialFlag);
        }

        $result = exec($cmd);
        if (is_null($result)) {
            echo "failed to copy: " . $cmd . "\n";
        } else {
            // Excel::updateExcelReqStatus(array("status" => "Success", "progress" => 100), $row['id']);
            Excel::updateExcelReqStatus(array("status" => "Success", "progress" => 100, "end_time" => date("Y-m-d H:i:s")), $row['id']);
        }
        if ($cmd2) $result = exec($cmd2);

        gc_collect_cycles();
    }

    Excel::updateSystemStatus("processExport", "0");

    echo date("Y-m-d H:i:s") . " Process End!\n";