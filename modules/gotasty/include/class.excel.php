<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for System..
 * Date  02/01/2020.
**/

class Excel
{

    function __construct(){
     //    $classAry = get_defined_vars();
        // foreach($classAry AS $className=>$class){
     //        if(in_array($className, array("db", "setting", "general", "activity"))){
     //                continue;
     //        }
        //  $this->$className = $class;
        // }
    }

    public function updateSystemStatus($name, $value){
    	$db = MysqliDb::getInstance();

        $db->where("name", $name);
        $oldValue=$db->getValue('system_settings','value');

    	$db->where("name", $name);
    	$db->update('system_settings', array("value" => $value));

        $db->where("name", $name);
        $updatedValue=$db->getValue('system_settings','value');

    	// if($db->count > 0){
    	// 	return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
    	// }
        if($oldValue!=$updatedValue){
         return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
        }

    	return array('status' => "error", 'code' => 0, 'statusMsg' => "Nothing to Update", 'data' => "");
    }

    public function getExcelReqList($params){
    	$db = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

        $adminID    = $db->userID;
        if(!$adminID) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Admin ID not found.', 'data' => '');

        $db->where("id", $adminID);
        $adminUsername = $db->getValue("admin", "username");

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit      = General::getLimit($pageNumber);

        $searchData = $params['searchData'];

        if(count($searchData) > 0) {
            foreach($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {

                    case 'date':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            $dateTo += 86399;
                            $db->where('created_at', date('Y-m-d H:i:s', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }
        }

        $db->where("creator_id", $adminID);
        $copyDb = $db->copy();
        $db->orderBy("created_at", "DESC");

        $exportList = $db->get('mlm_export', $limit, 'id, file_name, type, progress, created_at, start_time, end_time, status, error_msg');

        if(empty($exportList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00629"][$language], 'data' => "");

        foreach($exportList as &$value) {
        	$value['start_time'] = $value['start_time'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, 
                strtotime($value['start_time'])) : "-";
            $value['created_at'] = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, 
                strtotime($value['created_at'])) : "-";
            $value['end_time'] = $value['end_time'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['end_time'])) : "-";
        	$value['error_msg'] = $value['error_msg']? $value['error_msg']: "-";
        	$value['admin_username'] = $adminUsername;
        }

        $totalRecords         = $copyDb->getValue('mlm_export', 'count(id)');
        $data['exportList'] = $exportList;
        $data['totalPage']    = ceil($totalRecords/$limit[1]);
        $data['pageNumber']   = $pageNumber;
        $data['totalRecord']  = $totalRecords;
        $data['numRecord']    = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function addExcelReq($params){
    	$db = MysqliDb::getInstance();

    	if(!$params['command'])
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Command", 'data' => "");

    	if(!$params['params'] || !is_array($params['params']))
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Filter", 'data' => "");

    	if(!$params['type'])
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid file Type", 'data' => "");

    	if(!$params['titleKey'])
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid title", 'data' => "");

    	if(!$params['headerAry'] || !is_array($params['headerAry']))
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid header", 'data' => "");

    	if(!$params['fileName'])
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid fileName", 'data' => "");

    	// incase API != function name
    	// $replaceAPI = array("getFundInListing" => "getFundInListing2");

    	$command = $params['command'];
    	if($replaceAPI[$params['command']]){
    		$command = $replaceAPI[$params['command']];
    	}

    	$db->where('command', $command);
    	$db->where('status', array('Pending', 'Processing'), 'IN');
    	if($db->getValue('mlm_export', 'id')){
    		return array('status' => "error", 'code' => 1, 'statusMsg' => "Last Request Processing, try again later", 'data' => "");
    	}

    	$fileName = $params['fileName']."_".date("Ymd_His").".xlsx";
    	if($command == 'getLanguageCodeList') $fileName = $params['fileName'].".xlsx";
    	
    	$insert = array(
    		"command" => $command,
    		"params" => json_encode($params['params']), // filter search
    		"type" => $params['type'], // excel
    		"file_name" => $fileName,
    		"title_key" => $params['titleKey'], // data[transactionList]
    		"header_ary" => json_encode($params['headerAry']), // headerDisplay
    		"key_ary" => ($params['keyAry']? json_encode($params['keyAry']):""), // keyToRearrange
    		"total_ary" => ($params['totalAry']? json_encode($params['totalAry']):""), //keyToSumUp
    		"creator_id" => Cash::$creatorID,
    		"creator_type" => Cash::$creatorType,
    		"status" => "Pending",
    		"created_at" => date("Y-m-d H:i:s"),
    	);

    	$db->insert("mlm_export", $insert);
    	return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
    }

    public function updateExcelReqStatus($update, $id){
    	$db = MysqliDb::getInstance();

    	$db->where("id", $id);
    	$db->update("mlm_export", $update);

    	if($db->count > 0){
    		return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
    	}
    	return array('status' => "error", 'code' => 0, 'statusMsg' => "Nothing to Update", 'data' => "");
    }

    public function excelReqFailed($msg, $id){
    	$db = MysqliDb::getInstance();

    	$update = array(
			"status" => "Failed", 
			"end_time" => date("Y-m-d H:i:s"), 
			"error_msg" => $msg
		);

    	$db->where("id", $id);
    	$db->update("mlm_export", $update);
    }

    public function exportExcelNew($exportAry, $headerAry, $keyAry, $totalAry, $fileName, $id, $titleAry){
        include_once("PHP_XLSXWriter/xlsxwriter.class.php");
        $writer = new XLSXWriter();

        $sheetName="Sheet1";

        //Built to support 2D array
        foreach($headerAry AS $headerKeys){
            if (is_array($headerKeys)){
                $writer->writeSheetRow($sheetName, $headerKeys );
            }else{
                $buildHeaderArray[]=$headerKeys;
                $builtHeader=1;
            }
        }

        if ($builtHeader){
            $writer->writeSheetRow($sheetName, $buildHeaderArray );
        }

        $count = 0;
        $total = count($exportAry);
        foreach($exportAry AS $keyOne => $row){
            foreach($keyAry AS $key){
                $builtRowArray[]=$row[$key];
            }
            
            $writer->writeSheetRow($sheetName, $builtRowArray);
            unset($builtRowArray);
            $count++;

            if (($count%1000)==0){
                $percentage = 10 + (($count/$total)*80);
                self::updateExcelReqStatus(array("progress" => $percentage), $id);
            }
        }
        self::updateExcelReqStatus(array("progress" => 90), $id);

        $currentDirectory = __DIR__;
        $writer->writeToFile($currentDirectory."/../xlsx/".$fileName);
    }

    public function instantMemberExcelExport($params, $config) {
        $db = MysqliDb::getInstance();
        $language = General::$currentLanguage;
        $translations = General::$translations;
        $objPHPExcel = new PHPExcel();

        $sheetName="Sheet1";

        $clientID = $db->userID;
        $site = $db->userType;

        if (!$clientID) {
            $clientID = $params['clientID'];
        }

        $errorList = array();

        if (!$params['api']) {
            $errorList[] = array(
                'id' => "apiError",
                'msg' => $translations['E00125'][$language] /* Invalid value */,
            );
        }

        if (!$params['headerAry'] || !is_array($params['headerAry'])){
            $errorList[] = array(
                'id' => "headerError",
                'msg' => $translations['E00125'][$language] /* Invalid value */,
            );
        }

        if (!$params['fileName']) {
            $errorList[] = array(
                'id' => "fileNameError",
                'msg' => $translations['E00125'][$language] /* Invalid value */,
            );
        }

        if (!$params['titleKey']) {
            $errorList[] = array(
                'id' => "titleKeyError",
                'msg' => $translations['E00125'][$language] /* Invalid value */,
            );
        }

        if (!empty($errorList)) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00560"][$language] /* Failed to download */, 'data' => $errorList);
        }

        $command = $params['api'];
        $headerAry = $params['headerAry'];
        $fileName = $params['fileName']."_".date("Ymd_His").".xlsx";
        $keyAry = $params['keyAry'];
        $searchData = $params['params'];
        $titleKey = $params['titleKey'];

        /* Check if the API exist */
        $classReturn = Excel::checkFunctionClass($command);

        if (empty($classReturn)) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00733"][$language] /* No Results Found */, "data" => "");
        }

        /* Add export record */
        // $insert = array(
        //     "command" => $command,
        //     "params" => json_encode($searchData), // filter search
        //     "type" => $params['type'], // excel
        //     "file_name" => $fileName,
        //     "title_key" => $titleKey, // data[transactionList]
        //     "header_ary" => json_encode($headerAry), // headerDisplay
        //     "key_ary" => ($keyAry? json_encode($keyAry):""), // keyToRearrange
        //     "total_ary" => ($params['totalAry']? json_encode($params['totalAry']):""), //keyToSumUp
        //     "creator_id" => $clientID,
        //     "creator_type" => $site,
        //     "status" => "Pending",
        //     "created_at" => date("Y-m-d H:i:s"),
        //     "progress" => 0,
        // );
        // $db->insert("mlm_export", $insert);

        // $db->where('file_name', $fileName);
        // $excelFileID = $db->getValue('mlm_export', 'id');

        // $update = array("start_time" => date("Y-m-d H:i:s"), "status" => "Processing", "progress" => 1, "error_msg" => "");
        // self::updateExcelReqStatus($update, $excelFileID);

        /* Retrieve data from the requested API with the params provided */
        $respond = $classReturn->$command($searchData);

        $result  = $respond['data'][$titleKey];

        /* Build and write the header into the excel sheet */
        $char = "A";
        $rows = 1;
        foreach($headerAry as $headerKeys) {
            if (is_array($headerKeys)){
                $char = "A";
                foreach ($headerKeys as $key => $header) {
                    if (!$header || $header == "" || empty($header)){
                        $char++;
                        continue;
                    }
                    $objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $header);
                    $char++;
                }
                $rows++;
            } else {
                $objPHPExcel->getActiveSheet()->SetCellValue($char.'1', $headerKeys);
                $char++;
                $addRow = 1;
            }
        }

        if ($addRow) {
            $rows++;
        }

        /* Build and write the data into the excel sheet */
        // $count = 0;
        // $total = count($result);
        foreach($result AS $keyOne => $row){
            $char = "A";
            foreach ($keyAry AS $key) {
                $objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $row[$key], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
            }
            $rows++;
            // $count++;
            // if ($count % 1000 == 0) {
            //     $percentage = 10 + (($count/$total)*80);
            //     self::updateExcelReqStatus(array("progress" => $percentage), $excelFileID);
            // }
        }
        // self::updateExcelReqStatus(array("progress" => 100, "status" => 'Success', 'end_time' => date("Y-m-d H:i:s")), $excelFileID);

        /* Convert the generated excel file into base 64 */
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        ob_start();
        $objWriter->save('php://output');
        $excelOutput = ob_get_clean();
        $rawFile = base64_encode($excelOutput);

        $data['fileName'] = $fileName;
        $data['baseFile'] = $rawFile;
        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }


    public function exportExcel($exportAry, $headerAry, $keyAry, $totalAry, $fileName, $id, $titleAry,$grandTotal,$command, $specialFlag){
    	$objPHPExcel 	 = new PHPExcel();

    	$char = "A"; $rows = 1;
    	if($titleAry){
    		$style = array(
		        'alignment' => array(
		            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
		        )
		    );

    		$objPHPExcel->getActiveSheet()->getStyle($rows)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    		foreach($titleAry AS $title){

    			$from = $char.$rows;
    			$count = 0;
    			while($count != $title['colspan']){
    				$char++; $count++;
    			}
    			$to = $char.$rows;

    			$objPHPExcel->getActiveSheet()->mergeCells($from.':'.$to);
    			$objPHPExcel->getActiveSheet()->getCell($from)->setValue($title['value']);

    			$char++;
    		} // foreach($titleAry

    		$rows ++;
    	} // if($titleAry){

		// $char = "A";
        foreach ($grandTotal as $bonusName => $bonusRow) {
            $char = A;
            $rows++;
            $objPHPExcel->getActiveSheet()->SetCellValue($char++.$rows, $bonusRow['bonusName']);
            $objPHPExcel->getActiveSheet()->SetCellValue($char++.$rows, $bonusRow['totalBonus']);
        }

        if (!empty($grandTotal)){// To separate from table data
            $rows++;
        }

        /*
        For multi row Header
        */
        $char = "A";

		foreach($headerAry as $headerKeys){
            if(is_array($headerKeys)){
                $char = "A";
                foreach ($headerKeys as $key => $header) {
                    if(!$header || $header == "" || empty($header)){
                    // skip empty header for tickBox & button
                        $char++;
                        continue;
                    }
                    $objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $header);
                    $char ++;
                }
                $rows ++;        
            }
            else{
                $objPHPExcel->getActiveSheet()->SetCellValue($char.'1', $headerKeys);
                $char ++;
                $addRow = 1;
            }
		}

        if($addRow){
            $rows ++;
        }

		// if($headerAry){
		// 	$rows ++;
		// }
    	
    	$count = 0;
    	$total = count($exportAry);
	    foreach($exportAry AS $keyOne => $row){
			$char = "A";
            if (in_array($command,array('getBonusPayoutSummary', 'getBonusPayoutSummaryMonetary'))){
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $keyOne, PHPExcel_Cell_DataType::TYPE_STRING);
                $keyTotal[$char]='total';
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["totalBonusValue"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["subTotalPayoutAmount"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["subTotalPayoutAmountPercentage"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $char++;
                unset($row["totalBonusValue"],$row["subTotalPayoutAmount"], $row["subTotalPayoutAmountPercentage"]);
                foreach ($row as $payoutData) {
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $payoutData["payout"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                    $char++;
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $payoutData["percentage"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                    $char++;
                }
            }else if (in_array($command,array('getFirstPairingBonusReport'))){
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["bonusDate"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char ++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["username"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char ++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["name"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char ++;
                $group = 1;
                foreach ($row["position"] as $position => $positionRow) {
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["cf"], PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["newSales"], PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;
                    $totalChar = $char;
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["rm"], PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;

                    if($position%2 == 0){
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["groupPair".$group], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char ++;
                        $totalChar = $char;
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["groupPayable".$group], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char ++;
                        $group++;
                    }
                }
                $keyTotal[$totalChar]='total';
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["pairingAmount"], PHPExcel_Cell_DataType::TYPE_STRING);
                $keyTotal[$char] += $row["pairingAmount"];
                $char ++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["payableAmount"], PHPExcel_Cell_DataType::TYPE_STRING);
                $keyTotal[$char] += $row["payableAmount"];
                $char ++;

            } else if (in_array($command,array('getGroupPairingBonusReport'))){
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["bonusDate"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char ++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["username"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char ++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["name"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char ++;
                $group = 1;
                foreach ($row["position"] as $position => $positionRow) {
                    for ($i=1; $i <= $specialFlag; $i++) { 
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["fromUsername".$i]."(".$positionRow["fromSales".$i].")", PHPExcel_Cell_DataType::TYPE_STRING);
                        $char ++;
                    }
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["newSales"], PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;
                    
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["cf"], PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;
                    
                    $totalChar = $char;
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["rm"], PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;

                    if($specialFlag == 2 && $position%2 == 0){
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["groupPair".$group], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char ++;
                        $totalChar = $char;
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["groupPayable".$group], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char ++;
                        $group++;
                    }
                }

                if($specialFlag == 3){
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["leftOver"]["fromUsername1"]."(".$row["leftOver"]["fromSales1"].")", PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;
                    $totalChar = $char;

                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["leftOver"]["fromUsername2"]."(".$row["leftOver"]["fromSales2"].")", PHPExcel_Cell_DataType::TYPE_STRING);
                    $char ++;
                }
                $keyTotal[$totalChar]='total';
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["pairingAmount"], PHPExcel_Cell_DataType::TYPE_STRING);
                $keyTotal[$char] += $row["pairingAmount"];
                $char ++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["payableAmount"], PHPExcel_Cell_DataType::TYPE_STRING);
                $keyTotal[$char] += $row["payableAmount"];
                $char ++;

            } else if (in_array($command,array('getAdminOrderListing'))){
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $keyOne, PHPExcel_Cell_DataType::TYPE_STRING);
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["created_at"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["memberID"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["fullname"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;

                $rowsCount = 0;
                if(count($row['packageList']>1)){
                    foreach($row["packageList"] as $position => $positionRow){
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["amount"], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char++;
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["packageDisplay"], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char = chr(ord($char)-1);
                        $rows++;
                        $rowsCount++;
                    }
                    $char++;
                    $char++;
                    $rows = $rows-$rowsCount;
                }else{
                    foreach($row["packageList"] as $position => $positionRow){
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["packageDisplay"], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char++;
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $positionRow["amount"], PHPExcel_Cell_DataType::TYPE_STRING);
                        $char++;
                    }
                }
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["totalPV"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["paymentMethod"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["deliveryOption"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row["statusDisplay"], PHPExcel_Cell_DataType::TYPE_STRING);
                $char++;
                if($rowsCount){
                    $rows = $rows + $rowsCount - 1;
                }

            }elseif(in_array($command, array('getSalesPurchaseReport'))){
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $keyOne, PHPExcel_Cell_DataType::TYPE_STRING);
                // $keyTotal[$char]='total';
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row['totalUnit'], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $keyTotal[$char]='grandTotalUnit';
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row['totalAmount'], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $keyTotal[$char]='grandTotal';
                $char++;
                unset($row['totalAmount']);
                unset($row['totalUnit']);

                foreach ($specialFlag as $dataName) {
                    $rowDataKey[] = $dataName['name'];
                }

                // foreach($row as $rowTwo){
                //     $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $rowTwo['quantity'], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                //     $char++;
                //     $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $rowTwo['mfizDef'], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                //     $char++;
                //     $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $rowTwo['virtualAccount'], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                //     $char++;
                // }

                foreach($row as $rowTwo){
                    foreach ($rowDataKey as $dataKey) {
                        $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $rowTwo[$dataKey], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                        $keyTotal[$char] = $dataKey."Total";
                        $char++;  
                    }
                }
                unset($rowDataKey);

            }elseif(empty($keyAry)){
				foreach($row as $key=>$column){

					if(strlen($column) > 12){
						$objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $column, PHPExcel_Cell_DataType::TYPE_STRING);
					}else{
						$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $column);
					}

					if(in_array($key, $totalAry))
						$keyTotal[$char] += str_replace(",", "", $column);

					$char ++;
				} // auto arrange 0 - last
			}else{
				foreach($keyAry AS $key){

					if(!is_numeric($row[$key])){
                        if(strlen($row[$key]) > 12){
    						$objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $row[$key], PHPExcel_Cell_DataType::TYPE_STRING);
    					}else{
    						$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $row[$key]);
    					}
                    }else{
                        $objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $row[$key]);
                    }

					if(in_array($key, $totalAry)) 
						$keyTotal[$char] += str_replace(",", "", $row[$key]);

					$char ++;
				} // arrange by key
			}

			$count++;
			if($count % 1000 == 0){
				$percentage = 10 + (($count/$total)*80);
            	echo date('Y-m-d H:i:s'). " $percentage%\n";
				self::updateExcelReqStatus(array("progress" => $percentage), $id);
			}
			
			$rows ++;
		}

		if($keyTotal){
			$objPHPExcel->getActiveSheet()->getStyle($rows)->getFont()->setBold( true );
		}

		foreach($keyTotal AS $char=>$total){
            if (!is_numeric($total) && $total == 'total'){
                $objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, 'Total');
                $char++;
                continue;
            }

			$total = number_format($total, 2, '.', ',');
			if(strlen($total) > 12){
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $total, PHPExcel_Cell_DataType::TYPE_STRING);
			}else{
				$objPHPExcel->getActiveSheet()->SetCellValue($char.$rows, $total);
			}
		}

        if($command == "getBonusPayoutSummary" || $command == "getBonusPayoutSummaryMonetary"){
            $char = A;
            $objPHPExcel->getActiveSheet()->getStyle($rows)->getFont()->setBold( true );
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, "Total", PHPExcel_Cell_DataType::TYPE_STRING);
            $char++;
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["totalBonusValue"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
            $char++;
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["totalPayoutAmount"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
            $char++;
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["totalPayoutAmountPercentage"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
            $char++;

            $dataKeys = array_keys($row);

            foreach ($dataKeys as $totalKey) {
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry[$totalKey], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $char++;
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry[$totalKey."Percentage"], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $char++;
            }

            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["goldmineBonus"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["goldmineBonusPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["teamBonus"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["teamBonusPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["leadershipBonus"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["leadershipBonusPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["enrollmentBonus"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["enrollmentBonusPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["leadershipRewardBonus"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["leadershipRewardBonusPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["awardBonus"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["awardBonusPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["recruitPromo"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
            // $objPHPExcel->getActiveSheet()->setCellValueExplicit($char.$rows, $totalAry["recruitPromoPercentage"], PHPExcel_Cell_DataType::TYPE_STRING);
            // $char++;
        }

		// Save Excel 2007 file
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$currentDirectory = __DIR__;
		$objWriter->save($currentDirectory."/../xlsx/".$fileName);
    }

    public function checkFunctionClass($function){

    	$ignore = array('stdClass', 'Exception', 'ErrorException', 'Closure', 'Generator', 'DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'DatePeriod', 'LibXMLError', 'SQLite3', 'SQLite3Stmt', 'SQLite3Result', 'CURLFile', 'DOMException', 'DOMStringList', 'DOMNameList', 'DOMImplementationList', 'DOMImplementationSource', 'DOMImplementation', 'DOMNode', 'DOMNameSpaceNode', 'DOMDocumentFragment', 'DOMDocument', 'DOMNodeList', 'DOMNamedNodeMap', 'DOMCharacterData', 'DOMAttr', 'DOMElement', 'DOMText', 'DOMComment', 'DOMTypeinfo', 'DOMUserDataHandler', 'DOMDomError', 'DOMErrorHandler', 'DOMLocator', 'DOMConfiguration', 'DOMCdataSection', 'DOMDocumentType', 'DOMNotation', 'DOMEntity', 'DOMEntityReference', 'DOMProcessingInstruction', 'DOMStringExtend', 'DOMXPath', 'finfo', 'GMP', 'LogicException', 'BadFunctionCallException', 'BadMethodCallException', 'DomainException', 'InvalidArgumentException', 'LengthException', 'OutOfRangeException', 'RuntimeException', 'OutOfBoundsException', 'OverflowException', 'RangeException', 'UnderflowException', 'UnexpectedValueException', 'RecursiveIteratorIterator', 'IteratorIterator', 'FilterIterator', 'RecursiveFilterIterator', 'CallbackFilterIterator', 'RecursiveCallbackFilterIterator', 'ParentIterator', 'LimitIterator', 'CachingIterator', 'RecursiveCachingIterator', 'NoRewindIterator', 'AppendIterator', 'InfiniteIterator', 'RegexIterator', 'RecursiveRegexIterator', 'EmptyIterator', 'RecursiveTreeIterator', 'ArrayObject', 'ArrayIterator', 'RecursiveArrayIterator', 'SplFileInfo', 'DirectoryIterator', 'FilesystemIterator', 'RecursiveDirectoryIterator', 'GlobIterator', 'SplFileObject', 'SplTempFileObject', 'SplDoublyLinkedList', 'SplQueue', 'SplStack', 'SplHeap', 'SplMinHeap', 'SplMaxHeap', 'SplPriorityQueue', 'SplFixedArray', 'SplObjectStorage', 'MultipleIterator', 'Collator', 'NumberFormatter', 'Normalizer', 'Locale', 'MessageFormatter', 'IntlDateFormatter', 'ResourceBundle', 'Transliterator', 'IntlTimeZone', 'IntlCalendar', 'IntlGregorianCalendar', 'Spoofchecker', 'IntlException', 'IntlIterator', 'IntlBreakIterator', 'IntlRuleBasedBreakIterator', 'IntlCodePointBreakIterator', 'IntlPartsIterator', 'UConverter', 'SessionHandler', '__PHP_Incomplete_Class', 'php_user_filter', 'Directory', 'mysqli_sql_exception', 'mysqli_driver', 'mysqli', 'mysqli_warning', 'mysqli_result', 'mysqli_stmt', 'PDOException', 'PDO', 'PDOStatement', 'PDORow', 'PharException', 'Phar', 'PharData', 'PharFileInfo', 'ReflectionException', 'Reflection', 'ReflectionFunctionAbstract', 'ReflectionFunction', 'ReflectionParameter', 'ReflectionMethod', 'ReflectionClass', 'ReflectionObject', 'ReflectionProperty', 'ReflectionExtension', 'ReflectionZendExtension', 'SimpleXMLElement', 'SimpleXMLIterator', 'SoapClient', 'SoapVar', 'SoapServer', 'SoapFault', 'SoapParam', 'SoapHeader', 'XMLReader', 'XMLWriter', 'XSLTProcessor', 'ZipArchive', 'msgpack', 'MysqliDb', 'Setting', 'Provider', 'PHPExcel', 'PHPExcel_Autoloader', 'PHPExcel_Shared_String', 'Log', 'validation');

    	$allClasses = get_declared_classes();
    	foreach($allClasses AS $key=>$classes){
			if(in_array($classes, $ignore)) continue;

			if(method_exists($classes, $function)){
				$declareClass = strtolower($classes);
                return new $classes;
			}
		}

    } // checkFunctionClass

    public function insertExportData($params) {
        $language = General::$currentLanguage;
        $translations = General::$translations;

        unset($params['type']); //Otherwise, infinite loop back into this for processGenerateExcel
        unset($params['onloaded']);

        // Insert export listing
        $exportParams = array(
            'command' => $params['command'],
            'params' => $params,
            'type' => 'excel',
            'titleKey' => $params['DataKey'],
            'headerAry' => $params['header'],
            'keyAry' => $params['key'],
            'fileName' => $params['filename']
        );

        $data = Excel::addExcelReq($exportParams);
        return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
    }
}
?>