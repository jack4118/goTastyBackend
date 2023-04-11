<?php

    if(!isset($_SESSION)){
        session_start();
    }

    class Queue{
        
        function __construct() {
            // $this->db          = $db;
            // $this->setting     = $setting;
            // $this->general     = $general;
        }

        function addMlmQueue($params, $clientID){
            $db = MysqliDb::getInstance();

            $queueType   = trim($params['queueType']);

            if(!$queueType){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter queue type.", 'data' => $dataOut);
            }
            
            $updateData = array("queue_type" => $queueType,
                            "created_at" => date("Y-m-d H:i:s"),
                            "processed" => '0',
                            "client_id" => $clientID,);
            $db->Insert("mlm_queue", $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $queueType);
        }

        function updateMlmQueueProcess($params){
            $db = MysqliDb::getInstance();

            $queueID   = trim($params['queueID']);

            if($queueID == ""){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter queue ID.", 'data' => "");
            }

            $db->where("id", $queueID);
            $db->Update("mlm_queue",  array("processed" => '1'));

            // return success
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully update USDT transaction.", 'data' => "");
        }

        // Call from setMt4Password
        function userRecordNew($params, $clientID){
            $db = MysqliDb::getInstance();

            $queueType  = "UserRecordNew";

            $password   = trim($params['password']);
            $fullname   = trim($params["fullName"]);
            $phone      = trim($params["phone"]);
            $countryID  = trim($params["country"]);
            $email  = trim($params["email"]);
            $address            = empty(trim($params['address'])) ? "" : trim($params['address']);
            $state              = empty(trim($params['state'])) ? "" : trim($params['state']);
            // $gender     = trim($params["gender"]);
            // $nric       = trim($params["nric"]);

            if(!$clientID){
                return;
            }

            $db->where('client_id', $clientID);
            $res = $db->getValue('client_setting', 'value');
            if($res) return;

            //find country
            if($countryID){
                $db->where('id', $countryID);
                $country = $db->getValue('country', 'name');
            }

            /*$clientQuery = "SELECT username, name, memberID, email, country, phone, postcode FROM mlmClient WHERE ID = '".mysql_escape_string($clientID)."'";
            $clientRes = $db->dbSql($clientQuery);
            if($clientRow = mysql_fetch_assoc($clientRes)){
                $username = $clientRow['username'];
                $fullname = $clientRow['name'];
                $memberID = $clientRow['memberID'];
                $email = $clientRow['email'];
                $country = $clientRow['country'];
                $phone = $clientRow['phone'];
                $postcode = $clientRow['postcode'];
            }*/

            // $latestLoginCount = Setting::$internalSetting["MT4 Latest Login ID"];

            $db->where('name', array('MT4 Latest Group', 'MT4 Latest Login ID','MT4 Group Name'), "IN");
            $res = $db->getValue('system_settings', 'name, value, reference');
            foreach ($res as $key => $value) {
                if($value['name'] == "MT4 Latest Login ID"){
                    $latestLoginCount = $value['value'];
                }else if($value['name'] == "MT4 Group Name"){
                    $groupName = $value["value"];
                    $groupSize = $value["reference"] > 0 ? $value["reference"] : "400";
                }else{
                    $groupCount = $value['reference'];
                    $latestGroupNo = $value['value'];
                }
            }

            $latestGroup = $groupName.$latestGroupNo;
            if($groupCount >= $groupSize){
                $latestGroupNo = $latestGroupNo+1;
                $latestGroup = $groupName.$latestGroupNo;
                $groupCount = 0;
            }
            
            $groupCount += 1;

            $accID = $latestLoginCount+1;

            $excludedIDSet = Setting::$internalSetting["MT4 Excluded ID"];
            $excludedIDAry = explode(",", $excludedIDSet);

            if(in_array($accID, $excludedIDAry)){
                $accID += 1;
            }

            $leverage = Setting::$internalSetting["MT4 Leverage"] ? Setting::$internalSetting["MT4 Leverage"] : "25";

            $dataAry = array('name' => $fullname,
                            // 'password' => 'PH97123743',
                            'password' => $password,
                            'group' => $latestGroup,
                            'investor' => 'PH97123743',
                            'email' => $email,
                            'country' => $country,
                            'state' => $state,
                            'address' => $address,
                            // 'comment' => 'M-'.$latestGroupNo,
                            'comment' => '',
                            'phone' => $phone,
                            'password_phone' => 'secret_code_passage',
                            'status' => 'status',
                            'zipcode' => '',
                            'id' => $accID,
                            'leverage' => $leverage,
                            'login' => $accID,
                            'agent' => '0',
                            'enable_read_only' => 1,
                            'enable' => '1'
                            );  

            $data = json_encode($dataAry);
            
            $insertData = array(
                "queue_type" => $queueType, 
                "data" => $data, 
                "created_at" => date("Y-m-d H:i:s"), 
                "processed" => "0", 
                "url" => "", 
                "client_id" => $clientID
            );
            $db->insert("mlm_queue", $insertData);

            $db->where("name", 'MT4 Latest Login ID');
            $db->Update("system_settings", array("value" => $accID) );

            $db->where("name", 'MT4 Latest Group');
            $db->Update("system_settings", array("value" => $latestGroupNo, "reference" => $groupCount) );

            // Self::userChangeBalance($clientID,$totalSumBV);

            return true;
        }

        // Call from adminMemberRegistrationConfirmation & memberRegistrationConfirmation & adminMemberReEntryConfirmation
        function userChangeBalance($clientID, $amount, $unitPrice = 0, $portfolioID = ''){
            $db = MysqliDb::getInstance();

            // $queueType = "Mt4FundIn";

            // $db->where("client_id", $clientID);
            // $db->where("queue_type", 'UserRecordNew');
            // $res = $db->get("mlm_queue", NULL, "id");
            // $queueCount = $db->count;
            // if($queueCount > 0){
            //     $queueType = "InstantMt4FundIn";
            // }
    
            // if($amount > 0){
            //     $amount = bcmul((string)$amount, (string)Setting::$systemSetting['MT4ReentryRate'], Setting::$systemSetting['systemDecimalFormat'] );

            //     $dataAry = array('login' => 0,
            //                     'amount' => $amount,
            //                     'comment' => "Fund In",
            //                     'unitPrice' => $unitPrice,
            //                     'portfolioID' => $portfolioID);

            //     $data = json_encode($dataAry);

            //     $insertData = array(
            //         "queue_type" => $queueType,
            //         "data" => $data,
            //         "created_at" => date("Y-m-d H:i:s"),
            //         "processed" => '0',
            //         "client_id" => $clientID
            //     );
            //     $db->Insert("mlm_queue", $insertData);                 
            // }

            // open display only mt4 account.
            $db->where('client_id',$clientID);
            $db->where('name','quantumAccDisplay');
            $check = $db->getOne('client_setting','COUNT(id)');
            if($check['COUNT(id)'] == 0){
                $quantumID = Self::generateQuantumID();

                $insertData = array(
                    "name" => 'quantumAccDisplay',
                    "value" => $quantumID,
                    "client_id" => $clientID
                );
                $db->Insert("client_setting", $insertData); 
            }

            return true;
        }

        function userRecordUpdate($fullname, $clientID, $password = 0){
            $db = MysqliDb::getInstance();

            $queueType = "UserRecordUpdate";

            $clientSetQuery = "SELECT value, reference FROM mlmClientSetting WHERE name = 'MT4LoginID' AND clientID = '".mysql_escape_string($clientID)."'";
            $clientSetRes = $db->dbSql($clientSetQuery);
            if($clientSetRow = mysql_fetch_assoc($clientSetRes)){

                $MT4LoginID = $clientSetRow['value'];
                $comment = $clientSetRow['reference'];
            }

            $dataAry = array('login' => $MT4LoginID,
                            'name' => $fullname,
                            'group' => $comment
                            );  

            $data = json_encode($dataAry);
            
            $fields = array("queueType", "data", "createdOn", "processed", "url", "clientID");
            $values = array(mysql_escape_string($queueType), mysql_escape_string($data), mysql_escape_string(date("Y-m-d H:i:s")), '0', "", mysql_escape_string($clientID));
            $db->dbInsert("mlm_queue", $fields, $values);

            if($password){

                $queueType = "UserPasswordSet";

                $dataAry = array('login' => $MT4LoginID,
                                'password' => $password,
                                'change_investor' => 1,
                                'group' => $comment
                                );  

                $data = json_encode($dataAry);
                
                $fields = array("queueType", "data", "createdOn", "processed", "url", "clientID");
                $values = array(mysql_escape_string($queueType), mysql_escape_string($data), mysql_escape_string(date("Y-m-d H:i:s")), '0', "", mysql_escape_string($clientID));
                $db->dbInsert("mlm_queue", $fields, $values);

            }
        }

        function userPasswordSet($clientID, $password){
            $db = MysqliDb::getInstance();

            $clientSetQuery = "SELECT value, reference FROM mlmClientSetting WHERE name = 'MT4LoginID' AND clientID = '".mysql_escape_string($clientID)."'";
            $clientSetRes = $db->dbSql($clientSetQuery);
            if($clientSetRow = mysql_fetch_assoc($clientSetRes)){

                $MT4LoginID = $clientSetRow['value'];
                $comment = $clientSetRow['reference'];
            }

            if($password == "reset"){
                // $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                // $length = 6;
                // $password = substr(str_shuffle($chars), 0, $length);
                $password = "lga618";
            }

            if($MT4LoginID && $password){

                $queueType = "UserPasswordSet";

                $dataAry = array('login' => $MT4LoginID,
                                'password' => $password,
                                'change_investor' => 1,
                                'group' => $comment
                                );  

                $data = json_encode($dataAry);
                
                $fields = array("queueType", "data", "createdOn", "processed", "url", "clientID");
                $values = array(mysql_escape_string($queueType), mysql_escape_string($data), mysql_escape_string(date("Y-m-d H:i:s")), '0', "", mysql_escape_string($clientID));
                $db->dbInsert("mlm_queue", $fields, $values);

            }
        }

        function portfolioWithdrawal($clientID, $portfolioID){
            $db = MysqliDb::getInstance();

            if(!$clientID || !$portfolioID){
                return false;
            }

            $clientSetQuery = "SELECT value, reference FROM mlmClientSetting WHERE name = 'MT4LoginID' AND clientID = '".mysql_escape_string($clientID)."'";
            $clientSetRes = $db->dbSql($clientSetQuery);
            if($clientSetRow = mysql_fetch_assoc($clientSetRes)){

                $MT4LoginID = $clientSetRow['value'];
                $comment = $clientSetRow['reference'];
            }

            $portfolioQuery = "SELECT packagePrice, bitCoinRate FROM mlmClientPortfolio WHERE ID = '".mysql_escape_string($portfolioID)."'";
            $portfolioRes = $db->dbSql($portfolioQuery);
            if($portfolioRow = mysql_fetch_assoc($portfolioRes)){
                $bitCoinRate = $portfolioRow['bitCoinRate'];

                $usdPrice = Setting::setDecimal($portfolioRow['packagePrice']*$bitCoinRate);
            }

            $queueType = "PortfolioWithdrawal";

            $dataAry = array('login' => $MT4LoginID,
                            'group' => $comment,
                            'amount' => "-".$usdPrice,
                            'comment' => "Package Termination",
                            'bitCoinRate' => $bitCoinRate,
                            'portfolioID' => $portfolioID
                            );  

            $data = json_encode($dataAry);
            
            $fields = array("queueType", "data", "createdOn", "processed", "url", "clientID");
            $values = array(mysql_escape_string($queueType), mysql_escape_string($data), mysql_escape_string(date("Y-m-d H:i:s")), '0', "", mysql_escape_string($clientID));
            $db->dbInsert("mlm_queue", $fields, $values);

        }

        //AUTO-RUN function
        function checkUserRecordNew($data, $queueRow, $queueID, $queueType){
            $db = MysqliDb::getInstance();

            $type = "type=live";
            $cmd = "cmd=UserRecordNew";
            $master = "master=GW_PARKPASS87";

            foreach($data as $clientID => $queueRow) {
                $host = "";

                foreach($queueRow as $queueKey => $queueItem) {
                    $host .= $queueKey."=".$queueItem."&";

                    if($queueKey == "group"){
                        $comment = $queueItem;
                    }
                }

                $queryString = $host.$type.'&'.$cmd.'&'.$master;

                $execute = Self::execute($queryString);

                $result = json_decode($execute["data"], true);
                $responseStatus = $result["result"];
                $responseData = $result["data"];
                $responseMsg = $result["message"];

                // IF MT4 return result = 1
                if($responseStatus=="1"){
                    $responseStatus = "ok";
                    if(!$responseData == "" || "null"){

                        // Check the client whether already insert to mlmClientSetting or not
                        $query2 = "SELECT ID FROM mlmClientSetting WHERE clientID='".mysql_escape_string($clientID)."' AND name='MT4LoginID' AND reference = '".mysql_escape_string($comment)."'";

                        $res2 = $db->dbSql($query2);

                        //if yes update, else insert
                        if($row2 = mysql_fetch_assoc($res2)){
                            $fields = array("name", "value", "type", "reference", "clientID");
                            $values = array("MT4LoginID", mysql_escape_string($responseData), "MT4 Integration", mysql_escape_string($comment), mysql_escape_string($clientID));
                            $db->dbUpdate("mlmClientSetting", $fields, $values, "ID='".mysql_escape_string($row2["ID"])."'");
                        }else{
                            
                            $fields = array("ID", "name", "value", "type", "reference", "clientID");
                            $values = array($db->getNewID(), "MT4LoginID", mysql_escape_string($responseData), "MT4 Integration", mysql_escape_string($comment), mysql_escape_string($clientID));
                            $db->dbInsert("mlmClientSetting", $fields, $values);
                        }

                        $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = '".$responseStatus."' WHERE ID ='".$queueID."' AND queueType='".$queueType."'");
                    }
                }else if($responseStatus=="0"){
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($responseMsg)."', status = 'error' WHERE ID ='".$queueID."' AND queueType='".$queueType."'");
                }else{
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($responseMsg)."', status = 'error' WHERE ID ='".$queueID."' AND queueType='".$queueType."'");
                }

            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => "", 'data' => $responseData);
        }

        function checkUserChangeBalance($data, $queueRow, $queueType){
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $clientID = $queueRow['clientID'];

            if($queueType == "Mt4FundIn"){
                //company account
                $MT4LoginID = Setting::$internalSetting["MT4 Company Account"] ? Setting::$internalSetting["MT4 Company Account"] : "288001";
            
            }else{

                $clientSetQuery = "SELECT value FROM mlmClientSetting WHERE name = 'MT4LoginID' AND clientID = '".mysql_escape_string($clientID)."'";
                $clientSetRes = $db->dbSql($clientSetQuery);
                if($clientSetRow = mysql_fetch_assoc($clientSetRes)){

                    $MT4LoginID = $clientSetRow['value'];
                }
                
            }

            $proceed = 1;
            $master = "master=GW_PARKPASS87";
            $loginField = "login=";
            $type = "type=live";

            $cmd = "cmd=UserChangeBalance";
            $amountField = "amount=";

            foreach($data as $ID => $queue) {

                $amount = $queue['amount'];
                $comment = "comment=".$queue['comment'];

                $queryString = $cmd.'&'.$loginField.$MT4LoginID.'&'.$amountField.$amount.'&'.$comment.'&'.$type.'&'.$master;

                $execute = Self::execute($queryString);

                $result = json_decode($execute["data"], true);
                $responseStatus = $result["result"];
                $responseData = $result["data"];
                $responseMsg = $result["message"];
            
                if($responseStatus == "1"){
                    $responseStatus = "ok";
                    if(!$responseData == "" || "null"){
                        
                        $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = '".$responseStatus."' WHERE ID ='".$ID."' AND queueType='".$queueType."'");

                        switch ($queueType) {
                            case "Mt4Withdrawal" :
                                $fields = array("ID", "clientID", "amount", "createdOn", "type", "comment", "mt4LoginID");
                                $values = array($db->getNewID(), mysql_escape_string($clientID), mysql_escape_string(abs($amount)), date("Y-m-d H:i:s"), mysql_escape_string($queueType), mysql_escape_string($queue['comment']), mysql_escape_string($MT4LoginID));
                                $db->dbInsert("mlmMt4Withdrawal", $fields, $values);
                                break;

                            case "InstantMt4FundIn":
                                $message = str_replace('%%loginid%%',$MT4LoginID,$translations['M01984'][$language]) . " " . str_replace('%%amount%%',$amount,$translations['M01985'][$language]);

                                $fields = array('queueType','data','createdOn','processed','clientID');
                                $values = array(mysql_escape_string('smsMT4'),mysql_escape_string($message), mysql_escape_string(date('Y-m-d H:i:s')), mysql_escape_string(0),mysql_escape_string($clientID));
                                $db->dbInsert('mlm_queue',$fields,$values);
                                break;

                            case "Mt4FundIn":
                                /*$message = str_replace('%%amount%%',$amount,$lang['M01985'][$language]);

                                $fields = array('queueType','data','createdOn','processed','clientID');
                                $values = array(mysql_escape_string('smsMT4'),mysql_escape_string($message), mysql_escape_string(date('Y-m-d H:i:s')), mysql_escape_string(0),mysql_escape_string($clientID));
                                $db->dbInsert('mlm_queue',$fields,$values);*/
                                break;

                            default:
                                break;
                        }
                    }
                }else if($responseStatus == "0"){
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = 'error' WHERE ID ='".$ID."' AND queueType='".$queueType."'");
                }else{

                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["errorMsg"])."', status = 'error' WHERE ID ='".$ID."' AND queueType='".$queueType."'");
                }
            }
        }

        //AUTO-RUN function
        function checkUserPasswordSet($data, $queueRow, $queueID, $queueType){
            $db = MysqliDb::getInstance();

            $type = "type=live";
            $cmd = "cmd=UserPasswordSet";
            $master = "master=GW_PARKPASS87";
            $investor = "&change_investor=0";

            foreach($data as $clientID => $queueRow) {
                $host = "";

                foreach($queueRow as $queueKey => $queueItem) {
                    $host .= $queueKey."=".urlencode($queueItem)."&";

                }

                // $queryString = $host.$type.'&'.$cmd.'&'.$master;
                $queryString = $cmd.'&'.$host.$master.$investor;
               // echo $queryString."\n";

                $execute = Self::execute($queryString);

                $result = json_decode($execute["data"], true);
                $responseStatus = $result["result"];
                $responseData = $result["data"];
                $responseMsg = $result["message"];

                // IF MT4 return result = 1
                if($responseStatus=="1"){
                    $responseStatus = "ok";
                    if(!$responseData == "" || "null"){

                        $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = '".$responseStatus."' WHERE ID ='".$queueID."' AND queueType='".$queueType."'");
                    }
                }else if($responseStatus=="0"){
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = 'error' WHERE ID ='".$queueID."' AND queueType='".$queueType."'");
                }else{
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["errorMsg"])."', status = 'error' WHERE ID ='".$queueID."' AND queueType='".$queueType."'");
                }

            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => "", 'data' => $responseData);
        }

        //AUTO-RUN function
        function checkUserRecordUpdate($data, $queueRow, $queueID, $queueType){
            $db = MysqliDb::getInstance();

            $type = "type=live";
            $cmd = "cmd=UserRecordUpdate";
            $master = "master=GW_PARKPASS87";

            foreach($data as $clientID => $queueRow) {
                $host = "";

                foreach($queueRow as $queueKey => $queueItem) {
                    $host .= $queueKey."=".$queueItem."&";
                }

                $queryString = $host.$type.'&'.$cmd.'&'.$master;

                $execute = Self::execute($queryString);

                $result = json_decode($execute["data"], true);
                $responseStatus = $result["result"];
                $responseData = $result["data"];
                $responseMsg = $result["message"];

                // IF MT4 return result = 1
                if($responseStatus=="1"){
                    $responseStatus = "ok";
                    if(!$responseData == "" || "null"){

                        $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = '".$responseStatus."' WHERE ID ='".$queueID."'");
                    }
                }else if($responseStatus=="0"){
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["data"])."', status = 'error' WHERE ID ='".$queueID."'");
                }else{
                    
                    $db->dbSql("UPDATE mlm_queue SET processed = '1', dataIn = '".mysql_escape_string($queryString)."', dataOut = '".mysql_escape_string($execute["errorMsg"])."', status = 'error' WHERE ID ='".$queueID."'");
                }
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => "", 'data' => $responseData);
        }

        function memberChangeMt4Password($params, $clientID){
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            $password = (string)$params->password;
            $confirmPassword = (string)$params->confirmPassword;

            if (strlen($password) == 0) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00003"][$language], 'data' => array('field' => "mt4Password"));

            if (strlen($confirmPassword) == 0) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00004"][$language], 'data' => array('field' => "mt4ConfirmPassword"));

            if ($confirmPassword != $password) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00021"][$language], 'data' => array('field' => "mt4ConfirmPassword"));

             // Check valid password
            if (Setting::$generalSetting['Min Password Length']) 
                $minPasswordLength = (int)Setting::$generalSetting['Min Password Length'];
            else 
                $minPasswordLength = 6;

            if (Setting::$generalSetting['Max Password Length']) 
                $maxPasswordLength = (int)Setting::$generalSetting['Max Password Length'];
            else 
                $maxPasswordLength = 20;

            if (strlen($password) < $minPasswordLength || strlen($password) > $maxPasswordLength || !preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $password)) { //(?=\S*[A-Z]) checking for capital
                $find = array("%%min%%", "%%max%%");
                $replace = array($minPasswordLength, $maxPasswordLength);
                $statusMsg = str_replace($find, $replace, $lang["E00097"][$language]);
                return array('status' => "error", 'code' => 1, 'statusMsg' => $statusMsg, 'data' => array('field' => "mt4Password"));
            }

            $clientSetQuery = "SELECT value, reference FROM mlmClientSetting WHERE name = 'MT4LoginID' AND clientID = '".mysql_escape_string($clientID)."'";
            $clientSetRes = $db->dbSql($clientSetQuery);
            if($clientSetRow = mysql_fetch_assoc($clientSetRes)){
                //normal change password
                $MT4LoginID = $clientSetRow['value'];
                $comment = $clientSetRow['reference'];

                $queueType = "UserPasswordSet";

                $dataAry = array('login' => $MT4LoginID,
                                'password' => $password,
                                'group' => $comment
                                );  

                $data = json_encode($dataAry);
                
                $fields = array("queueType", "data", "createdOn", "processed", "url", "clientID");
                $values = array(mysql_escape_string($queueType), mysql_escape_string($data), mysql_escape_string(date("Y-m-d H:i:s")), '0', "", mysql_escape_string($clientID));
                $db->dbInsert("mlm_queue", $fields, $values);
            }
            else{
                $queueCount = $db->dbCountRecords("mlm_queue","clientID = '".mysql_escape_string($clientID)."' AND queueType = 'UserRecordNew'");
                if($queueCount <= 0){
                    Self::userRecordNew($params,$clientID);
                }else{
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $lang["E00303"][$language], 'data' => "");
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $lang['M00298'][$language],"data" => "");
        }

        // Socket Connection between MT4
        function execute($queryString){

            $fp = fsockopen("52.221.159.220", 20452, $errno, $errstr, 30);
                if (!$fp) {
                    echo "$errstr ($errno)<br />\n";
                } else {
                    $out = $queryString;
                    fwrite($fp, $out);
                    $result = '';
                    while (!feof($fp)) {
                        $result .= fgets($fp, 4096);
                        //echo fgets($fp);
                    }
                    fclose($fp);
                } 
                echo "\n\n".date("Y-m-d H:i:s")."\n".$queryString."\n".$result;
                return array('status' => "ok", 'code' => 0, 'errorMsg' => $errstr, 'data' => $result);
        }  

        // Socket Connection between MT4  
        // *** for transferFromMT4BalanceFullAmount frotnend API use. (without echo) ***
        function executeFrontEnd($queryString){

            $fp = fsockopen("52.221.159.220", 20452, $errno, $errstr, 30);
                if (!$fp) {
                } else {
                    $out = $queryString;
                    fwrite($fp, $out);
                    $result = '';
                    while (!feof($fp)) {
                        $result .= fgets($fp, 4096);
                    }
                    fclose($fp);
                } 
                return array('status' => "ok", 'code' => 0, 'errorMsg' => $errstr, 'data' => $result);
        }  

        function generateQuantumID(){
            $db = MysqliDb::getInstance();
            // $db->where('name','quantumIDLength');
            // $quantumIDLength= $db->getOne('system_settings','value');
            $quantumIDLength= 6;
            $min = 1; $max = 9; 
            for($i=1;$i<(int)$quantumIDLength;$i++) $max .= "9";
            while(1){ 
                $quantumID = sprintf("%0".$quantumIDLength."s", mt_rand((int)$min, (int)$max));
                $db->where('value',$quantumID);
                $db->where('name','quantumAccDisplay');
                $check = $db->getOne('client_setting','COUNT(id)');
                if($check['COUNT(id)'] == 0) break;
            }
            return $quantumID;
        }
    }
?>