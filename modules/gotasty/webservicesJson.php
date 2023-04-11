<?php

    if($_SERVER['REQUEST_METHOD'] != 'POST') die("Invalid Method!");

    include_once('include/classlib.php');
    include_once('language/lang_all.php');

    // $msgpackData = msgpack::msgpack_unpack(file_get_contents('php://input'));
    $msgpackData    = file_get_contents('php://input');
    $msgpackData    = json_decode($msgpackData, 1);

    $timeStart      = time();
    $tblDate        = date("Ymd");
    $createTime     = date("Y-m-d H:i:s");

    $command            = $msgpackData['command'];
    $sessionID          = $msgpackData['sessionID'];
    $userID             = $msgpackData['userID'];
    $sessionTimeOut     = $msgpackData['sessionTimeOut'];
    $source             = $msgpackData['source'];
    $sourceVersionName  = $msgpackData['sourceVersionName'];
    $sourceVersionNo    = $msgpackData['sourceVersionNo'];
    $platform           = $msgpackData['platformVersion'];
    $site               = $msgpackData['site'];
    $systemLanguage     = trim($msgpackData['language'])? trim($msgpackData['language']) : "english"; // default to english
    $ip                 = $msgpackData['ip'];
    $userAgent          = $msgpackData['userAgent'];
    $marcaje            = $msgpackData['marcaje'];
    $marcajeTK          = $msgpackData['marcajeTK'];

    // Get Device Detial
    if ($source == "Apps") {
        $deviceDetail['deviceModel']        = $msgpackData['deviceModel'];
        $deviceDetail['deviceManufacturer'] = $msgpackData['deviceManufacturer'];
        $deviceDetail['deviceName']         = $msgpackData['deviceName'];
        $deviceDetail['sourceVersionNo']    = $msgpackData['sourceVersionNo'];

        General::$deviceDetail  = $deviceDetail;
    }

    General::$currentLanguage   = $systemLanguage;
    General::$translations      = $translations;
    General::$userAgent         = $userAgent;
    General::$ip                = $ip;

    Setting::$accessUser['userID']  = $userID;
    Setting::$accessUser['site']    = $site;
    Setting::$accessUser['source']  = $source;

    $filterBase64Arr = array("");
    $filterKeyAry = array("");

    if ($command != "getWebservices") {
        $filterMsgpackData = $msgpackData;
        $webserviceID = Webservice::insertWebserviceData($filterMsgpackData, $tblDate, $createTime);
    }

    // General::insertDailyTable("acc_credit");
    // General::insertDailyTable("sent_history");

    $filterCommands = array("appLogin");
    $filterSpecialCharCommands = array("");

    if ($source == "Apps" || $source == "Api") {

        $msgpackData['params']['appsBypass'] = true;

    }
    else if (!in_array($command, $filterCommands)) {

        if ($command == "testAPI") {
            // If it's test API, no need to validate session
            $userData = User::getTestUserData($msgpackData['params']['userID'], $site);

            // Replace the command with the command that we are going to test
            $command = trim($msgpackData['params']['testCommand']);
            unset($msgpackData['params']['testCommand']);

            // Remove from params object, so that checkApiParams will not block it
            // Assign to another variable, just in case need to use it again
            $testApiUserID = trim($msgpackData['params']['userID']);
            unset($msgpackData['params']['userID']);
        }
        else {
            $userDataRes = User::checkSession($userID, $sessionID, $site, $source, $marcaje, $marcajeTK);
            $userData   = $userDataRes['userData'];
            $timeOut    = $userDataRes['timeOut'];
            $newSessionID = $userDataRes['newSessionID'];
            $userID     = $userData['id'];
            $username   = $userData['username'];
            Setting::$accessUser['userID'] = $userID;
            $isSessionExpired = $userDataRes['isSessionExpired'];
            $isSessionExpired = 1;
        }

        if (!$userData) {
            $errCode = 3;
            $errMsg = "Session expired.";
            if (!$isSessionExpired) {
                $errCode = 5;
                $errMsg = "";
            }

            // If sessionID is invalid, we return as session timeout
            $outputArray = array('status' => "error", 'code' => $errCode, 'statusMsg' => $errMsg, 'data' => $returnData);

            Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            echo json_encode($outputArray);
            exit;
        }
    }

    if ($source == "Apps") {
        $mobileOSAry = array("Android", "iOS");
        if ($command != "getAllLanguage") {
            if (!in_array($sourceVersionNo, Setting::$configArray['appCurrentVersion']) ) {
                foreach (Setting::$configArray['appCurrentVersion'] as $key => $value) {
                    $latestVersion = $value;
                }

                $outputArray = array('status' => "error", 'code' => 999, 'statusMsg' => "Require update.", 'appUpdateData' => array("currentVersion" => $latestVersion, "actionPerform" => "update apps", "appsLink" => Setting::$configArray["downloadUrl"], "appsLinkGoogle" => Setting::$configArray["appsLinkGoogle"]));
                
                Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

                echo json_encode($outputArray);
                exit;
            }
        }
    }

    if ($source == "Apps") {

         if(!in_array($command, $filterCommands)) {

            $userDataRes = User::checkSession($userID, $sessionID, $site, $source, $marcaje, $marcajeTK);
            $userData   = $userDataRes['userData'];
            $timeOut    = $userDataRes['timeOut'];
            $newSessionID = $userDataRes['newSessionID'];
            $userID     = $userData['id'];
            $username   = $userData['username'];
            Setting::$accessUser['userID'] = $userID;
            $isSessionExpired = $userDataRes['isSessionExpired'];

            if (!$userData) {
                $errCode = 3;
                $errMsg = "Session expired.";
                if(!$isSessionExpired){
                    $errCode = 5;
                    $errMsg = "";
                }
                // If sessionID is invalid, we return as session timeout
                $outputArray = array('status' => "error", 'code' => 3, 'statusMsg' => "Session expired.", 'data' => '');

                Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

                echo json_encode($outputArray);
                exit;
            }
        }
    }

    $db->userID      = $userID;
    $db->userType    = $site;

    $getApiResult = Api::getOneApi($command);
    // Temporary comment till all APIs are added into API table
    // if($getApiResult['code'] == 1) {
    //     $updateWebservice = Webservice::updateWebserviceData($webserviceID, $getApiResult, $getApiResult["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

    //     echo msgpack::msgpack_pack($outputArray);
    //     exit;
    // }
    $apiSetting = $getApiResult['data'];

    $apiID = $apiSetting['id'];
    $apiDuplicate = $apiSetting['check_duplicate'];
    $duplicateInterval = $apiSetting['check_duplicate_interval'];
    $isSample = $apiSetting['sample'];

    // Check api parameters type
    $checker = Api::checkApiParams($apiID, $msgpackData['params']);

    if($checker['code'] == 1) {
        $updateWebservice = Webservice::updateWebserviceData($webserviceID, $checker, $checker["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

        echo json_encode($checker);
        exit;
    }

    if(!in_array($command, $filterSpecialCharCommands)){
        //check special character
        $check = true;
        Api::setSpecialCharacters();
        $specialChars = Api::getSpecialCharacters();
        // Check if there are restrictions on special characters
        if(empty($specialChars)) {
            $check = false;
        }
        $filterParamsArr = array("invProductName");
        if($check == true){
           foreach($msgpackData['params'] as $key => $value){
                if(!in_array($key,$filterParamsArr)){
                    foreach($specialChars as $array) {
                        $char = $array['value'];
                        $pregCheck = preg_match('/[\\'.$char.']/', $value);
                        $check = ($pregCheck == 0)?true:false;
                        
                        if(!$check){
                            $data["field"][] = array(
                                                        'id'  => $key."Error",
                                                        'msg' => $translations["E00834"][$systemLanguage]
                                                    );

                            $outputArray = array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00834"][$systemLanguage], 'data' => $data);

                            //Add Session Data
                            $outputArray['sessionData'] = array(
                                "newSessionID" => $newSessionID,
                                "userID" => $userID,
                                "username" => $username,
                                "timeOut" => $timeOut
                            );   

                            Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());
                            echo json_encode($outputArray);
                            exit;
                        }
                    }
                }
            } 
        }
        
        $checkTag = true;
        //check html tag
        $checkParams = Api::apiParamsTagCheck($msgpackData['params'], $checkTag);
        if(!$checkParams){
            $outputArray = array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00835"][$systemLanguage], 'data' => 'ERROR 126');
            Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());
            echo json_encode($outputArray);
            exit;
        }  
    }

    // Check duplicate parameters of api
    if($apiDuplicate == 1) {
        $duplicate = Api::checkApiDuplicate($tblDate, $createTime, $userID, $sessionID, $site, $command, $duplicateInterval, $webserviceID);
        
        if($duplicate['code'] == 1) {
            $updateWebservice = Webservice::updateWebserviceData($webserviceID, $duplicate, $duplicate["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            //Add Session Data
            $duplicate['sessionData'] = array(
                "newSessionID" => $newSessionID,
                "userID" => $userID,
                "username" => $username,
                "timeOut" => $timeOut
            );
            
            echo json_encode($duplicate);
            exit;
        }
    }

    // Check whether to use sample output for this api
    if($isSample == 1) {
        $outputArray = Api::getSampleOutput($apiID);

        Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

        echo json_encode($outputArray);
        exit;
    }
    
    // Set creator id and type
    Cash::setCreator($userID, $site);
    // $activity->setCreator($userID, $site);
    // $general->setCreator($userID, $site);

    $db->queryNumber = 0;

    //check whether client is being locked out from api
    $params = $msgpackData['params'];

    $result = 0;

    if (Cash::$creatorType == "Member") {
        $sq  = $db->subQuery();
        $sq2 = $db->subQuery();

        $sq2->where("name", $params['creditType']);
        $sq2->getOne("credit", "id");

        $sq->where("command", $command);
        $sq->where("credit_id", $sq2);
        $sq->getOne("mlm_client_rights", "id");

        $db->where("client_id", Cash::$creatorID);
        $db->where("rights_id", $sq);
        $result = $db->getValue("mlm_client_blocked_rights", "count(*)");
    }

    //Add Session Data
    $sessionData = array(
        "newSessionID" => $newSessionID,
        "userID" => $userID,
        "username" => $username,
        "timeOut" => $timeOut
    );

    $db->sessionID = $newSessionID;

    // API Type
    // 1.ignore
    // 2.get
    // 3.verify
    // 4.callback
    // 5.insert

    //result equals to 0 means the client is not blocked from using the api
    if ($result == 0) {

        switch ($command) {

            case "appLogin":
                $apiType = "verify";
                $outputArray = Flutter::appLogin($msgpackData);
                break;

            case "changePassword":
                $apiType = "insert";
                $outputArray = Flutter::changePassword($msgpackData['params']);
                break;

            case "getShopListing":
                $apiType = "get";
                $outputArray = Flutter::getShopListing($msgpackData['params'], $userID);
                break;

            case "getShopDetails":
                $apiType = "get";
                $outputArray = Flutter::getShopDetails($msgpackData['params']);
                break;

            case "getWorkerListing":
                $apiType = "get";
                $outputArray = Flutter::getWorkerListing($msgpackData['params'], $userID);
                break;

            case "addWorker":
                $apiType = "insert";
                $outputArray = Flutter::addWorker($msgpackData['params'], $userID);
                break;

            case "editWorker":
                $apiType = "insert";
                $outputArray = Flutter::editWorker($msgpackData['params'], $userID);
                break;

            case "getWorkerDetails":
                $apiType = "get";
                $outputArray = Flutter::getWorkerDetails($msgpackData['params'], $userID);
                break;

            default:
                $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Command not found.", 'data' => '');
                $find = array("%%apiName%%");
                $replace = array($command);
                Message::createMessageOut('10003', NULL, NULL, $find, $replace); //Send notification if Invalid Command.
                break;
        }
    } else {
        $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "You have been blocked from using this transaction", 'data' => "");
    }

    //Update Duplicate Checking
    if((!$apiDuplicate) && ($apiType == 'insert')){
        $db->where("command", $command);
        $db->where("site",$site);
        $db->update('api',array("check_duplicate"=>1, "check_duplicate_interval"=>1, "updated_at"=>$createTime));
    }

    //Add Session Data
    $outputArray['sessionData'] = $sessionData;

    $outputArrayFiltered = $outputArray;
    //skip base:64 data insert into db
    if(in_array($command, $filterBase64Arr)) Api::loopReplace($outputArrayFiltered,$filterKeyAry);

    /***** For sending the Notifications. *****/
    $queries = $db->getQueryNumber(); // Need to add the Executed queries count.
    //For sending the Notification - API executes the no of queries.
    if($queries > $apiSetting['no_of_queries']) {
        $find = array("%%apiName%%", "%%apiAllowed%%", "%%apiCurrent%%");
        $replace = array($command, $apiSetting['no_of_queries'], $queries);
        Message::createMessageOut('10002', NULL, NULL, $find, $replace);
    }
    /***** For sending the Notification. *****/

    $completedTime = date("Y-m-d H:i:s");
    $processedTime = time() - $timeStart;

    $dataOut = $outputArray;
    $status = $dataOut['status'];

    //For sending the Notification - API takes longer time.
    if($processedTime > $apiSetting['duration']){
        $find = array("%%apiName%%", "%%apiTime%%", "%%seconds%%");
        $replace = array($command, $apiSetting['duration'], $processedTime);
        Message::createMessageOut('10001', NULL, NULL, $find, $replace);
    }

    if($command != "getWebservices") {
        $updateWebservice = Webservice::updateWebserviceData($webserviceID, $outputArrayFiltered, $status, $completedTime, $processedTime, $tblDate, $queries);
    }

    echo json_encode($outputArray);

?>
