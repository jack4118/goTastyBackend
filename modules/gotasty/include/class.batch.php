<?php

class Batch{

    function __construct(){

        // $this->cash    = Client::validation->bonus->cash;
        // $this->invoice = Client::validation->invoice;
        // $this->product = Client::validation->product;
        // $this->country = Client::validation->country;
        // $this->client  = $client;
        // $this->bonus   = Client::validation->bonus;
        // $this->subscribe = $subscribe;

    }

    public function adminBatchUpdateWithdrawal($params, $site, $userID) {
        $db = MysqliDb::getInstance();

        $language = General::$currentLanguage;
        $translations = General::$translations;

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchUpdateWithdrawal');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if ($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if ($worksheet->getCell('B1')->getValue() != "SerialNo")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if ($worksheet->getCell('C1')->getValue() != "Status")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if ($worksheet->getCell('D1')->getValue() != "Remarks")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");


        $dataInsert = array(
            'data' => $params['base64'],
            'type' => $params['type'],
            'created_at' => $db->now()
        );
        $uploadID = $db->insert('uploads', $dataInsert);
        

        if (empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language], 'data' => "");

        $batchID = $db->getNewID();
        
        $dataInsert = array(
            'type' => 'batchUpdateWithdrawal',
            'attachment_id' => $uploadID,
            'attachment_name' => $params['name'],
            'creator_id' => $userID,
            'creator_type' => $site,
            'created_at' => $db->now(),
            'batch_id' => $batchID
        );
        $importID = $db->insert('mlm_import_data', $dataInsert);
        
        if (empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");
       
        $recordCount = 0;
        $processedCount = 0;
        $failedCount = 0;
        for ($row = 2; $row <= $lastRow; $row++) {

            unset($estimatedDate);
            // $recordCount++;

            $serialNo = $worksheet->getCell('B' . $row)->getValue(); //SerialNo
            $statusWD = $worksheet->getCell('C' . $row)->getValue(); //Status
            $remark = $worksheet->getCell('D' . $row)->getValue(); //Remark
            $estimatedDate2 = $worksheet->getCell('E' . $row)->getValue(); //Estimate Date
            $errorMessage = $status = $withdrawalID = '';
            if($estimatedDate2){ //If estimatedDate2 is not null 
                $estimatedDate1 = strtr($estimatedDate2, '/', '-');
                $estimatedDate = date('Y-m-d', strtotime($estimatedDate1));
            }
            $currentDate = date('Y-m-d');

            if(empty($statusWD)){
                $errorMessage = $errorMessage."Status cannot be left empty.\n";
                $status = "Failed";
            }

           

            if($statusWD != 'Approve' && $statusWD != 'Reject' && $statusWD != 'Cancel' && $statusWD != 'Pending'){
                $errorMessage = $errorMessage."Insert invalid status.\n";
                $status = "Failed";
            }

            if(!isset($serialNo) || $serialNo == "" || !isset($statusWD) || $statusWD == ""){
                $errorMessage = $errorMessage."Serial No is Empty.\n";
                $status = "Failed";
            }else{
                $db->where("serial_number", $serialNo);
                $withdrawalID = $db->getValue("mlm_withdrawal", "id");
                if (empty($withdrawalID)) {
                    $errorMessage = $errorMessage."Serial No. is invalid.\n";
                    $status = "Failed";
                }
            }


            $recordCount++;
            /* Refund if status is Reject or Cancel*/
            if($withdrawalID){
                $db->where("id", $withdrawalID);
                $result = $db->getOne('mlm_withdrawal', "*");

                $amount = $result['amount'];

                // Validate Checking
                if (in_array($result['status'], array('Approve', 'Reject', 'Cancel','Pending'))){
                    $errorMessage = $errorMessage."Status ('Approve', 'Reject', 'Cancel' , 'Pending') is not allow to change status.\n";
                    $status = "Failed";
                }
            }

            if ($estimatedDate && $estimatedDate < $currentDate) {
                $errorMessage = $errorMessage."Estimated date cannot smaller than current date.\n";
                $status = "Failed";
            } 
            
            if ($status != 'Failed') {

                $fields = array('serial_number', 'approved_at', 'updater_id', 'updater_username','estimated_date','status', 'remark');
                $values = array($serialNo, $db->now(), $userID, $site, $estimatedDate, $statusWD, $remark);

                $db->where('id', $withdrawalID);
                $db->update('mlm_withdrawal', array_combine($fields, $values));

                if ($statusWD == "Reject" || $statusWD == "Cancel") {
                    $db->where('username', "withdrawal");
                    $db->where('type', "Internal");
                    $internalID = $db->getValue('client', 'id');

                    $groupID = $db->getGroupID();
                    $db->where("belong_id", $result['belong_id']);
                    $returnCredit = $db->get("credit_transaction", NULL, "type,amount");
                    foreach ($returnCredit AS $data) {
                        Cash::insertTAccount($internalID, $result['client_id'], $data['type'], $data['amount'], 'Withdrawal Return', $result['belong_id'], "", $db->now(), $result['batch_id'], $result['client_id'], $remark, "", "", $groupID);
                    }

                } 


                $status = "Success";
                $processedCount++;
            }else{
                $failedCount++;
            }

            $json = array(
                'SerialNo' => $serialNo,
                // 'AdjustmentType' => $adjustType,
                'Status' => $statusWD,
                // 'Credit Type' => $creditType,
                'Remark' => $remark,
                'Estimated Date' => $estimatedDate
            );
            $json = json_encode($json);

            $dataInsert = array(
                'mlm_import_data_id' => $importID,
                'data' => $json,
                'processed' => "1",
                'status' => $status,
                'error_message' => $errorMessage
            );
            $db->insert('mlm_import_data_details', $dataInsert);
        }

        $dataUpdate = array(
            'total_records' => $recordCount,
            'total_processed' => $processedCount,
            'total_failed' => $failedCount
        );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }
    
    public function adminBatchAddWaterBucket($params, $site, $userID) {
        $db = MysqliDb::getInstance();

        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchAddWaterBucket');


        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;


        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "Water Bucket Instant %")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('D1')->getValue() != "Water Bucket Daily %")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" && $worksheet->getCell('C2')->getValue() == "" && $worksheet->getCell('D2')->getValue() == ""){
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");
        }



        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] , 'data' => "");

        
        $dataInsert = array (
                                'type' => 'adminBatchAddWaterBucket',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = $worksheet->getCell('B'.$row)->getValue();
            $instantPercentage = $worksheet->getCell('C'.$row)->getValue();
            $dailyPercentage = $worksheet->getCell('D'.$row)->getValue();

            if($username == ""){
                $rowFailed = "Username cannot be Empty. ";
            }

            if( !is_numeric($instantPercentage) || !is_numeric($dailyPercentage)){
                $rowFailed = $rowFailed."Invalid Percentage. ";
            }

            if($instantPercentage == ""){
                $instantPercentage = 0;
                // $rowFailed = $rowFailed."Instant Percentage cannot be Empty. ";
            }

            if($dailyPercentage == ""){
                $dailyPercentage = 0;
                // $rowFailed = $rowFailed."Daily Percentage cannot be Empty. ";
            }

            $db->where("username", $username);
            $clientID = $db->getValue("client", "id");
            if(empty($clientID)){
                // $failedCount++;
                $rowFailed = $rowFailed."Member not found. ";
                // $status = "Failed";
            }

            $db->where("name", "waterBucketBonus");
            $waterID = $db->getValue("mlm_bonus", "id");

            $db->where("bonus_id", $waterID);
            $db->where("name", 'maxPercentage');
            $waterMaxPercentage = (float)$db->getValue("mlm_bonus_setting", "value");

            if($instantPercentage < 0 || $instantPercentage > $waterMaxPercentage || $dailyPercentage < 0 || $dailyPercentage > $waterMaxPercentage){
                $rowFailed = $rowFailed.$translations['M00755']['english'] /* Percentage */ ." ".$translations["E00137"][$language] /* cannot be less than  */ . " 0 " . $translations["E00138"][$language] /*  or more than  */ ." ". $waterMaxPercentage . '. ';
            }

            if(empty($rowFailed)){

                $params['username'] = $username;
                $params['percentage']['instant'] = $instantPercentage;
                $params['percentage']['daily'] = $dailyPercentage;

                $result = Bonus::setWaterBucketPercentage($params,$site,$userID);

                if($result['status'] == 'ok'){
                    $status = "Success";
                    $processedCount++;
                }else{
                    $status = "Failed";
                    $rowFailed = $result['statusMsg'];
                    $failedCount++;
                } 

            }else{
                $status = "Failed";
                $failedCount++;
            }

            $json = array   (
                                'Username' => $username,
                                'instantPercentage' => $instantPercentage,
                                'dailyPercentage' => $dailyPercentage,
                            );
            $json = json_encode($json);


            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $rowFailed
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            unset($rowFailed);

        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function adminSpecialBatchRegistration($params, $site) {
        $db = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $package = $params['package'];
        $portfolioType = $params['portfolioType'];

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminSpecialBatchRegistration');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "FullName")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('D1')->getValue() != "Password")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('E1')->getValue() != "TransactionPassword")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('F1')->getValue() != "Email")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('G1')->getValue() != "DialingArea")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('H1')->getValue() != "PhoneNumber")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('I1')->getValue() != "SponsorUsername")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('J1')->getValue() != "Amount")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" || $worksheet->getCell('C2')->getValue() == "" || $worksheet->getCell('D2')->getValue() == "" || $worksheet->getCell('E2')->getValue() == "" || $worksheet->getCell('F2')->getValue() == "" || $worksheet->getCell('G2')->getValue() == "" || $worksheet->getCell('H2')->getValue() == "" || $worksheet->getCell('I2')->getValue() == "" || $worksheet->getCell('J2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");


        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] , 'data' => "");


        if ($package == "quantum"){

                $dataInsertType = 'adminSpecialBatchRegistrationQuantum';

                $db->where('name', "quantum");
                $productID = $db->getValue("mlm_product", 'id');

                $db->where("product_id", $productID);
                $db->where("name", "minValue");
                $minValue = $db->getValue("mlm_product_setting", 'value');

                $db->where("product_id", $productID);
                $db->where("name", "multiplesOf");
                $multiplesOf = $db->getValue("mlm_product_setting", 'value');

        }elseif ($package == "hedging") {

                $dataInsertType = 'adminSpecialBatchRegistrationHedging';

                $db->where('name', "hedging");
                $productID = $db->getValue("mlm_product", 'id');

                $db->where("product_id", $productID);
                $db->where("name", "multiplesOf");
                $multiplesOf = $db->getValue("mlm_product_setting", 'value');

        }else{
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* The selected file cannot be empty */, 'data' => "");
        }


        $dataInsert = array (
                                'type' => $dataInsertType,
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");


        $recordCount = 0; $processedCount = 0; $failedCount = 0;


        for($row=2; $row<=$lastRow; $row++) {

            

            $fullName = $worksheet->getCell('B'.$row)->getValue();
            $username = $worksheet->getCell('C'.$row)->getValue();
            $password = $worksheet->getCell('D'.$row)->getValue();
            $transactionPassword = $worksheet->getCell('E'.$row)->getValue();
            // $country = $worksheet->getCell('F'.$row)->getValue();
            $email = $worksheet->getCell('F'.$row)->getValue();
            $dialingArea = $worksheet->getCell('G'.$row)->getValue();
            $phoneNumber = $worksheet->getCell('H'.$row)->getValue();
            $sponsorUsername = $worksheet->getCell('I'.$row)->getValue();
            $amount = $worksheet->getCell('J'.$row)->getValue();

            if (!$username){
                $emptyRowCount++;
                if ($emptyRowCount>=5){
                    break;//IF too many consecutive empty rows, break out of the loop
                }
                continue;
            }
            $emptyRowCount=0;

            $recordCount++;
            $db->where("country_code", $dialingArea);
            $countryID = $db->getValue("country", "id");

            $title = array("username","fullName","country","dialingArea","phone","email","password","checkPassword","tPassword","checkTPassword","sponsorName");
            $value = array($username,$fullName,$countryID,$dialingArea,$phoneNumber,$email,$password,$password,$transactionPassword,$transactionPassword,$sponsorUsername);

            $registerParams = array_combine($title, $value);

            $verifyResult = Subscribe::memberRegistration($registerParams);

                

            $amountChecking = 0;

            if ($package == "quantum"){

                // if($amount % $multiplesOf != '0'){
                    // if($amount != $minValue){
                    if($amount < $minValue){

                        $amountChecking = 1;              
                    }
                // }
            }elseif ($package == "hedging") {

                // if($amount % $multiplesOf != '0'){                     
                        // $amountChecking = 1;              
                // }
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

            }

            if($verifyResult["status"] == "ok" && $amountChecking == "0"){

                $registerResult = Subscribe::memberRegistrationConfirmation($registerParams);

                $db->where('username', $username);
                $clientID = $db->getValue('client', 'id');

                // $db->where('name', "quantum");
                // $productID = $db->getValue("mlm_product", 'id');

                $db->where("product_id", $productID);
                $db->where("name", "maturityDays");
                $days = $db->getValue("mlm_product_setting", 'value');
                $maturityDays = "+".$days."day";

                $startDate = date("Y-m-d H:i:s");
                $expireAt = date("Y-m-d H:i:s", strtotime($startDate.$maturityDays));


                $portfolioParams = array (
                                'clientID' => $clientID,
                                'productID' => $productID,
                                'price' => '0',
                                'bonusValue' => $amount,
                                // 'tierValue' => 0,
                                'type' => $portfolioType,
                                'belongID' => $db->getNewID(),
                                'referenceID' => "",
                                'batchID' => $db->getNewID(),
                                'status' => "Active",
                                'expireAt' => $expireAt,
                                'unitPrice' => $unitPrice,
                                'isCompounding' => '1'
                            );
                //need handle - function moved to subscribe class
                $portfolioID = Subscribe::insertClientPortfolio($portfolioParams);

                if($portfolioType == "freeWithRebate" && $package == "hedging"){

                $creditTypeArray = array('flexiCredit','ibgCredit');

                $db->where('name', $creditTypeArray,'IN');
                $result = $db->get("credit", NULL, "id");

                foreach ($result as $value) {
                    $creditID[] = $value['id'];
                }

                $column = array(
                    "id",
                    "name"
                );
                $db->where('credit_id', $creditID, 'IN');
                $clientRightsArray = $db->get("mlm_client_rights", NULL, $column);

                $db->where('name', 'reentry Block');
                $reentryBlockRight = $db->get("mlm_client_rights", NULL, $column);

                $blockRightArray = array_merge($clientRightsArray, $reentryBlockRight);

                foreach ($blockRightArray as $value) {
                    $rightsID = $value["id"];
                    $rightsName = $value["name"];

                    $insertData = array(
                        "client_id" => $clientID,
                        "rights_id" => $rightsID,
                        "rights_name" => $rightsName,
                        "created_at" => $db->now()
                    );
                    $db->insert('mlm_client_blocked_rights', $insertData);
                }

                }                    

            }


            if($registerResult["status"] == "ok" && $portfolioID){
                $status = "Success";
                $processedCount++;
                $errorMessage = "";
                
            }else{
                $status = "Failed";
                $failedCount++;
                $errorMessage = $verifyResult["data"]["field"][0]["msg"];
                if($amountChecking == "1"){
                    $errorMessage = "Amount less than minimum amount";
                }
            }

            unset($portfolioID);

            $title1 = array("username","fullName","country","dialingArea","phone","email","password","checkPassword","tPassword","checkTPassword","sponsorName","amount");
            $value1 = array($username,$fullName,$countryID,$dialingArea,$phoneNumber,$email,$password,$password,$transactionPassword,$transactionPassword,$sponsorUsername,$amount);

            $saveParams = array_combine($title1, $value1);

            $json = json_encode($saveParams);

            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $errorMessage
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function adminBatchCreditAdjustment($params, $site) {
        $db = MysqliDb::getInstance();

        $language     = General::$currentLanguage;
        $translations = General::$translations;
        $dateTime = date("Y-m-d H:i:s");

        $creditType = $params['creditType'];
        $adjustType = $params['adjustType'];

        $adjustTypeAccepted =  array('In','Out');

        if(!in_array($adjustType, $adjustTypeAccepted)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Adjust Type", 'data' => "");
        }

        if(empty($creditType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");
        }

        $db->where("name", "isDailyLimitWallet");
        $db->where("value", 1);
        $limitWalletRow = $db->getOne("credit_setting", "credit_id, reference");
        if($limitWalletRow){
            $db->where("id", $limitWalletRow["credit_id"]);
            $limitWalletType = $db->getValue("credit", "name");
        }

        $db->where("name", "isAutoSellTrdWallet");
        $db->where("value", 1);
        $checkTypeRow = $db->getOne("credit_setting", "credit_id, reference");
        if($checkTypeRow){
            $db->where("id", $checkTypeRow["credit_id"]);
            $checkType = $db->getValue("credit", "name");
        }
        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchCreditAdjustment');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "Amount")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('D1')->getValue() != "Remark")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" || $worksheet->getCell('C2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");


        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] , 'data' => "");

        
        $dataInsert = array (
                                'type' => 'adminBatchCreditAdjustment',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        if ($adjustType == "Out") {

            $db->where("name", "creditAdjustment");
            $accountID     = $db->getValue ("client", "id");

        } else if ($adjustType == "In") {

            $db->where("name", "creditRefund");
            $accountID     = $db->getValue ("client", "id");

        }

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = $worksheet->getCell('B'.$row)->getValue();
            $amount = $worksheet->getCell('C'.$row)->getValue();
            $remark = $worksheet->getCell('D'.$row)->getValue();


            if(($username == "") || ($amount == "")){
                $status = "Failed";
                $failedCount++;
                continue;
            }

            if(($username != "") && ($amount != "")){

                $db->where("username", (string) $username);
                $clientID = $db->getValue("client", "id");
                if(empty($clientID)){
                    $failedCount++;
                    $rowFailed = "Member not found";
                    $status = "Failed";
                }


                if($amount <= 0){
                    $failedCount++;
                    $rowFailed = "Adjustment Amount Is Zero Or Negative";
                    $status = "Failed";
                }
                else if(strpos((string)$amount, ",") !== false) {
                    $failedCount++;
                    $rowFailed = "Adjustment Amount Cannot Have Comma";
                    $status = "Failed";
                }
                else if($adjustType == "Out" && $clientID) {
                    $currentBalance = Cash::getBalance($clientID, $creditType);
                    $newBalance = $currentBalance - $amount;

                    if($newBalance < 0){
                        $failedCount++;
                        $rowFailed = "Adjustment Out Amount Cannot Exceed Credit Balance";
                        $status = "Failed";
                    }
                }

                if(empty($rowFailed)){
                    $rowFailed = "";
                    switch($adjustType) {
                        case "In":
                            Cash::insertTAccount($accountID, $clientID, $creditType, $amount, "Adjustment In", $importID, "", $db->now(), $batchID, $clientID, $remark);
                            // Custom::updateMemberActiveStatus($clientID, $dateTime);
                            break;
                        case "Out":
                            Cash::insertTAccount($clientID, $accountID, $creditType, $amount, "Adjustment Out", $importID, "", $db->now(), $batchID, $clientID, $remark);
                            break;

                    }

                    $status = "Success";
                    $processedCount++;
                }

            }else{
                $status = "Failed";
                $failedCount++;
            }

            $json = array   (
                                'Username' => $username,
                                'AdjustmentType' => $adjustType,
                                'Adjustment Amount' => $amount,
                                'Credit Type' => $creditType
                            );
            $json = json_encode($json);


            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $rowFailed
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);
            unset($rowFailed);
            unset($remark);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function adminBatchStatusAdjustment($params, $site) {
        $db = MysqliDb::getInstance();

        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchStatusAdjustment');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "Status")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" || $worksheet->getCell('C2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");


        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] , 'data' => "");

        
        $dataInsert = array (
                                'type' => 'adminBatchStatusAdjustment',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = $worksheet->getCell('B'.$row)->getValue();
            $adjustStatus = $worksheet->getCell('C'.$row)->getValue();


            if(($username == "") || ($adjustStatus == "")){
                $status = "Failed";
                $failedCount++;
                continue;
            }

            if(($username != "") && ($adjustStatus != "")){

                $db->where("username", (string)$username);
                $clientID = $db->getValue("client", "id");
                if(empty($clientID)){
                    $failedCount++;
                    $rowFailed = "Member not found";
                    $status = "Failed";
                }

                $statusAccepted =  array('active','disabled');

                if(!in_array(strtolower($adjustStatus), $statusAccepted)){
                    $failedCount++;
                    $rowFailed = "Status must write in Active or Disabled";
                    $status = "Failed";
                }

                if(empty($rowFailed)){
                    unset($updateData);
                    if (strtolower($adjustStatus) == "active"){
                        $statusColumnValue = '0';
                        $updateData = array ('disabled' => $statusColumnValue,'freezed' => $statusColumnValue);
                    } elseif (strtolower($adjustStatus) == "disabled") {
                        $statusColumnValue = '1';
                        $updateData = array ('disabled' => $statusColumnValue);
                    }

                    $db->where('id', $clientID);
                    $db->update('client', $updateData);

                    $status = "Success";
                    $processedCount++;
                }

            }else{
                $status = "Failed";
                $failedCount++;
            }

            $json = array   (
                                'Username' => $username,
                                'newStatus' => $adjustStatus
                            );
            $json = json_encode($json);


            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $rowFailed
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            unset($rowFailed);

        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function massChangePassword($params, $site) {
        $db = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminMassChangePassword');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "New Login Password")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('D1')->getValue() != "New Transaction Password")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" || $worksheet->getCell('C2')->getValue() == "" || $worksheet->getCell('D2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");

        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $dataInsert = array (
                                'type' => 'adminMassChangePassword',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = $worksheet->getCell('B'.$row)->getValue();
            $loginPassword = $worksheet->getCell('C'.$row)->getValue();
            $transactionPassword = $worksheet->getCell('D'.$row)->getValue();

            $db->where('disabled', 1);
            $specialCharacters = $db->getValue('special_characters', 'value', null);
            $pattern = "/[";
            foreach($specialCharacters as $value) {
                $pattern = $pattern.$value;
            }
            $pattern = $pattern."]/";

            $errorMessage = "";

            if(empty($username))
                $errorMessage = $errorMessage."Username cannot be left empty.\n";
            if(empty($loginPassword))
                $errorMessage = $errorMessage."Login password cannot be left empty.\n";
            else if(preg_match_all($pattern, $loginPassword))
                $errorMessage = $errorMessage."Login password cannot contain special characters.\n";
            if(empty($transactionPassword))
                $errorMessage = $errorMessage."Transaction password cannot be left empty.\n";
            else if(preg_match_all($pattern, $transactionPassword))
                $errorMessage = $errorMessage."Transaction password cannot contain special characters.\n";

            $db->where('username', $username);
            $checkUser = $db->getValue('client', 'username');
            if(empty($checkUser))
                $errorMessage = $errorMessage."Member not found.\n";

            if(empty($errorMessage)) {
                $status = "Success";
                $processedCount++;
                $dataUpdate = array (
                                        'password' => Setting::getEncryptedPassword($loginPassword),
                                        'transaction_password' => Setting::getEncryptedPassword($transactionPassword)
                                    );
                $db->where('username', $username);
                $db->update('client', $dataUpdate);
            }
            else {
                $status = "Failed";
                $failedCount++;
            }

            $json = array   (
                                'Username' => $username,
                                'Login Password' => $loginPassword,
                                'Transaction Password' => $transactionPassword
                            );
            $json = json_encode($json);

            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $errorMessage
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function batchAdjustTradingLimit($params) {
        $db = MysqliDb::getInstance();

        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $userID = $db->userID;
        $site = $db->userType;

        $base64 = $params['base64'];
        $type = $params['type'];
        $name = $params['name'];
        $adjustType = $params['adjustType'];

        $adjustTypeAccepted = array('In','Out');

        if(!in_array($adjustType, $adjustTypeAccepted)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Adjust Type", 'data' => "");
        }

        $fileDataBase64 = base64_decode((string)$base64);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'batchAdjustTradingLimit');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "Amount")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" || $worksheet->getCell('C2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");

        $dataInsert = array (
                                'data' => $base64,
                                'type' => $type,
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $dataInsert = array (
                                'type' => 'batchAdjustTradingLimit',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $name,
                                'creator_id' => $userID,
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = trim($worksheet->getCell('B'.$row)->getValue());
            $amount = trim($worksheet->getCell('C'.$row)->getValue());

            if(!$username || !$amount){
                $status = "Failed";
                $failedCount++;
                continue;
            }

            if($username && $amount){

                $db->where("username", (string) $username);
                $clientID = $db->getValue("client", "id");
                if(empty($clientID)){
                    $failedCount++;
                    $rowFailed = "Member not found";
                    $status = "Failed";
                }

                if($amount <= 0){
                    $failedCount++;
                    $rowFailed = "Adjustment Amount Is Zero Or Negative";
                    $status = "Failed";
                }
                else if(strpos((string)$amount, ",") !== false) {
                    $failedCount++;
                    $rowFailed = "Adjustment Amount Cannot Have Comma";
                    $status = "Failed";
                }

                if(empty($rowFailed)){
                    $rowFailed = "";
                    switch($adjustType) {
                        case "In":
                            Trading::updateBuySellLimit("adjustIn", $clientID, $amount, "", "", $importID);
                            break;
                        case "Out":
                            Trading::updateBuySellLimit("adjustOut", $clientID, $amount, "", "",$importID);
                            break;
                    }

                    $status = "Success";
                    $processedCount++;
                }

            } else {
                $status = "Failed";
                $failedCount++;
            }

            $json = array   (
                                'username' => $username,
                                'adjustment_type' => $adjustType,
                                'adjustment_amount' => $amount,
                            );
            $json = json_encode($json);

            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $rowFailed
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);

            $activityRes = Activity::insertActivity('Batch Trading Limit Adjustment', 'T00021', 'L00033', json_decode($json), $clientID, $userID, $site);

            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => '');

            unset($rowFailed);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    public function getImportData($params) {
        $db = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit = General::getLimit($pageNumber);
        $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

        $searchData = $params['searchData'];
        $type = $params['type'];

        if(count($searchData) > 0) {
            foreach($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'type':
                        $db->where('type', $dataValue);
                        break;

                    case 'createdAt':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                            $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
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

        if($type == "adminBatchRegistration"){
            $searchType = array('adminBatchRegistration');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminSpecialBatchRegistration") {
            $searchType = array('adminSpecialBatchRegistrationHedging','adminSpecialBatchRegistrationQuantum');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminBatchCreditAdjustment") {
            $searchType = array('adminBatchCreditAdjustment');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminBatchAdjustCreditLimit") {
            $searchType = array('adminBatchAdjustCreditLimit');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminBatchStatusAdjustment") {
            $searchType = array('adminBatchStatusAdjustment');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminBatchAddWaterBucket") {
            $searchType = array('adminBatchAddWaterBucket');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminBatchAdjustCoupon") {
            $searchType = array('adminBatchAdjustCoupon');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "batchAdjustTradingLimit") {
            $searchType = array('batchAdjustTradingLimit');
            $db->where('type', $searchType, 'IN');
        } elseif ($type == "adminBatchRightsAdjustment"){
            $searchType = array('adminBatchRightsAdjustment');
            $db->where('type', $searchType, 'IN');
        } else {
            $searchType = array('adminBatchRegistration','adminSpecialBatchRegistrationHedging','adminSpecialBatchRegistrationQuantum','adminBatchCreditAdjustment','adminBatchStatusAdjustment','adminBatchAddWaterBucket','adminBatchAdjustCoupon','batchAdjustTradingLimit','adminBatchRightsAdjustment');
            $db->where('type', $searchType, 'NOT IN');
        }

        $db->orderBy('id', 'Desc');
        $copyDb = $db->copy();
        $result = $db->get('mlm_import_data', $limit);

        if(empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00147"][$language] /* No result found. */, 'data' => "");

        foreach($result as $value) {
            if($value['creator_type'] == 'SuperAdmin')
                $superAdminID[] = $value['creator_id'];
            else if($value['creator_type'] == 'Admin')
                $adminID[] = $value['creator_id'];
            else if ($value['creator_type'] == 'Member')
                $clientID[] = $value['creator_id'];
        }
        if(!empty($superAdminID)) {
            $db->where('id', $superAdminID, 'IN');
            $dbResult = $db->get('users', null, 'id, username');
            foreach($dbResult as $value) {
                $usernameList['SuperAdmin'][$value['id']] = $value['username'];
            }
        }
        if(!empty($adminID)) {
            $db->where('id', $adminID, 'IN');
            $dbResult = $db->get('admin', null, 'id, username');
            foreach($dbResult as $value) {
                $usernameList['Admin'][$value['id']] = $value['username'];
            }
        }
        if(!empty($clientID)) {
            $db->where('id', $clientID, 'IN');
            $dbResult = $db->get('client', null, 'id, username');
            foreach($dbResult as $value) {
                $usernameList['Member'][$value['id']] = $value['username'];
            }
        }

        foreach($result as $value) {
            $import['id'] = $value['id'];
            $import['type'] = $value['type'];
            $import['attachment_name'] = $value['attachment_name'];
            $import['username'] = $usernameList[$value['creator_type']][$value['creator_id']];
            $import['total_records'] = $value['total_records'];
            $import['total_processed'] = $value['total_processed'];
            $import['total_failed'] = $value['total_failed'];
            $import['created_at'] = date($dateTimeFormat, strtotime($value['created_at']));

            $importList[] = $import;
        }

        $totalRecords = $copyDb->getValue('mlm_import_data', 'count(id)');
        $data['importList'] = $importList;
        $data['totalPage'] = ceil($totalRecords/$limit[1]);
        $data['pageNumber'] = $pageNumber;
        $data['totalRecord'] = $totalRecords;
        $data['numRecord'] = $limit[1];
            
        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function getImportDataDetails($params) {
        $db = MysqliDb::getInstance();

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit = General::getLimit($pageNumber);

        $db->where('mlm_import_data_id', $params['id']);
        $copyDb = $db->copy();
        $result = $db->get('mlm_import_data_details', $limit, 'data, status, error_message');

        if(empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00148"][$language] /* No result found. */, 'data' => "");

        foreach($result as $value) {
            foreach(json_decode($value['data']) as $key => $val) {
                $details[$key] = $val;
            }
            $details['status'] = $value['status'];
            $details['error_message'] = $value['error_message'];

            $importDetailsList[] = $details;
        }

        $totalRecords = $copyDb->getValue('mlm_import_data_details', 'count(id)');
        $data['importDetailsList'] = $importDetailsList;
        $data['totalPage'] = ceil($totalRecords/$limit[1]);
        $data['pageNumber'] = $pageNumber;
        $data['totalRecord'] = $totalRecords;
        $data['numRecord'] = $limit[1];
            
        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function adminBatchAdjustCreditLimit($params, $site) {
        $db = MysqliDb::getInstance();

        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $creditType = $params['creditType'];
        if(!$params['clientID']) $params['clientID'] = $db->userID;
        if(!$params['clientID']) $params['clientID'] = $db->userID;

        if(empty($creditType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");
        }

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchAdjustCreditLimit');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language].'1qw' /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('C1')->getValue() != "Amount")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language].'asdasd' /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "" || $worksheet->getCell('C2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");


        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] , 'data' => "");

        
        $dataInsert = array (
                                'type' => 'adminBatchAdjustCreditLimit',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = $worksheet->getCell('B'.$row)->getValue();
            $amount = $worksheet->getCell('C'.$row)->getValue();

            if(($username == "") || ($amount == "")){
                $status = "Failed";
                $failedCount++;
                continue;
            }

            if(($username != "") && ($amount != "")){

                $db->where("username", (string) $username);
                $clientID = $db->getValue("client", "id");
                if(empty($clientID)){
                    $failedCount++;
                    $rowFailed = "Member not found";
                    $status = "Failed";
                }


                if($amount <= 0){
                    $failedCount++;
                    $rowFailed = "Adjustment Amount Is Zero Or Negative";
                    $status = "Failed";
                }
                else if(strpos((string)$amount, ",") !== false) {
                    $failedCount++;
                    $rowFailed = "Adjustment Amount Cannot Have Comma";
                    $status = "Failed";
                }



            }else{
                $status = "Failed";
                $failedCount++;
            }

			if($status != "Failed"){
				$db->where("name","convertCap");
				$db->where("client_id",$clientID);
				$db->where("type",$creditType);
				$id = $db->getValue("client_setting","id");
				if($id){
					$db->where("id",$id);
					$db->update("client_setting",array("value"=>$amount));
				}else{
					$insertData = array(
										"name" => "convertCap",
										"client_id" => $clientID,
										"type" => $creditType,
										"value" => $amount,
									);
					$db->insert("client_setting",$insertData);
				}
				$status = "Success";
				$processedCount++;
			}else{
				$status = "Failed";
				$failedCount++;
			}

            $json = array   (
                                'Username' => $username,
                                'AdjustmentType' => $adjustType,
                                'Adjustment Amount' => $amount,
                                'Credit Type' => $creditType
                            );
            $json = json_encode($json);


            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $rowFailed
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);
            unset($rowFailed);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

        }

        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00324"][$language], 'data' => "");
    }

    public function adminBatchUnlock($params, $site){
        $db = MysqliDb::getInstance();

        $language = General::$currentLanguage;
        $translations = General::$translations;   

        $rightsType = $params['rightsType'];
        $adjustType = $params['adjustType'];

        $adjustTypeAccepted = array('Lock','Unlock');

        if(!in_array($adjustType, $adjustTypeAccepted)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Adjust Type", 'data' => "");
        }

        if(empty($rightsType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Rights Type", 'data' => "");
        }

        if(empty($params['base64'])){
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00925"][$language] /* No file chosen */, 'data' => "");
        }                              

        $fileDataBase64 = base64_decode((string)$params['base64']);
        $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchRightsAdjustment');

        $handle = fopen($tmp_handle, 'r+');
        fwrite($handle, $fileDataBase64);
        rewind($handle);

        $fileType = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($fileType);
        
        $excelObj = $objReader->load($tmp_handle);
        $worksheet = $excelObj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();
        $lastCol++;

        if($lastRow <= 1)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

        if($worksheet->getCell('B1')->getValue() != "Username")
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

        if($worksheet->getCell('B2')->getValue() == "")
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Empty Row Detected", 'data' => "");

        //check exact row
        $exactRow = 0;
        for($iteration=2; $iteration<=$lastRow; $iteration++) {
            $username = $worksheet->getCell('B'.$iteration)->getValue();
            if($username)
                $exactRow++;

            if($exactRow > 300)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Adjustment data cannot more than 300 rows", 'data' => "");
        }

        $dataInsert = array (
                                'data' => $params['base64'],
                                'type' => $params['type'],
                                'created_at' => $db->now()
                            );
        $uploadID = $db->insert('uploads', $dataInsert);

        if(empty($uploadID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */ , 'data' => "");

        
        $dataInsert = array (
                                'type' => 'adminBatchRightsAdjustment',
                                'attachment_id' => $uploadID,
                                'attachment_name' => $params['name'],
                                'creator_id' => $params['clientID'],
                                'creator_type' => $site,
                                'created_at' => $db->now()
                            );
        $importID = $db->insert('mlm_import_data', $dataInsert);

        if(empty($importID))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

        $recordCount = 0; $processedCount = 0; $failedCount = 0;

        for($row=2; $row<=$lastRow; $row++) {

            $recordCount++;

            $username = $worksheet->getCell('B'.$row)->getValue();

            if($username == ""){
                $status = "Failed";
                $failedCount++;
                continue;
            }

            if($username != ""){

                $db->where("username", (string) $username);
                $clientID = $db->getValue("client", "id");

                if(empty($clientID)){
                    $failedCount++;
                    $rowFailed = "Member not found";
                    $status = "Failed";
                }

                if(empty($rowFailed)){
                    $rowFailed = "";
                    $db->where("rights_name",$rightsType);
                    $db->where('client_id',$clientID);
                    $copyDb = $db->copy();
                    $blockExists = $db->has('mlm_client_blocked_rights');
                    switch($adjustType) {
                        case "Lock":        
                            if ($blockExists) {
                                $updateData = array(
                                    "created_at" => date('Y-m-d H:i:s')
                                );
                                $copyDb->update('mlm_client_blocked_rights', $updateData);
                            }else{
                                $db->where('name',$rightsType);
                                $rightsId = $db->getValue('mlm_client_rights','id');
                                
                                $insertData = array(
                                    "client_id"   => $clientID,
                                    "rights_id"   => $rightsId,
                                    "rights_name" => $rightsType,
                                    "created_at"  => date('Y-m-d H:i:s')
                                );

                                $db->insert('mlm_client_blocked_rights',$insertData);
                            }
                            $status = "Success";
                            $processedCount++;
                            break;

                        case "Unlock":
                            if(!$blockExists){
                                $failedCount++;
                                $rowFailed = $clientID." does not have ".$rightsType." record previously";
                                $status = "Failed";
                            }else{
                                $copyDb->delete('mlm_client_blocked_rights');
                                $status = "Success";
                            }
                            $processedCount++;
                            break;

                        // $status = "Success";
                        // $processedCount++;
                    }
                }
            }

            $json = array   (
                                'Username' => $username,
                                'AdjustmentType' => $adjustType,
                                'RightsType' => $rightsType
                            );
            $json = json_encode($json);


            $dataInsert = array (
                                    'mlm_import_data_id' => $importID,
                                    'data' => $json,
                                    'processed' => "1",
                                    'status' => $status,
                                    'error_message' => $rowFailed
                                );
            $ID = $db->insert('mlm_import_data_details', $dataInsert);
            unset($rowFailed);
            unset($remark);

            if(empty($ID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

        }
        $dataUpdate = array (
                                'total_records' => $recordCount,
                                'total_processed' => $processedCount,
                                'total_failed' => $failedCount
                            );
        $db->where('id', $importID);
        $db->update('mlm_import_data', $dataUpdate);

        $handle = fclose($handle);
        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }
}

?>
