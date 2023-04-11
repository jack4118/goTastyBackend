<?php

    if($_SERVER['REQUEST_METHOD'] != 'POST') die("Invalid Method!");

    include_once('include/classlib.php');
    include_once('language/lang_all.php');
    
    $msgpackData = msgpack::msgpack_unpack(file_get_contents('php://input'));
    $timeStart   = time();
    $tblDate     = date("Ymd");
    $createTime  = date("Y-m-d H:i:s");

    $command        = $msgpackData['command'];
    $sessionID      = $msgpackData['sessionID'];
    $userID         = $msgpackData['userID'];
    $sessionTimeOut = $msgpackData['sessionTimeOut'];
    $source         = $msgpackData['source'];
    $sourceVersionName  = $msgpackData['sourceVersionName'];
    $sourceVersionNo  = $msgpackData['sourceVersionNo'];
    $platform  = $msgpackData['platformVersion'];
    $site           = $msgpackData['site'];
    $systemLanguage = trim($msgpackData['language'])? trim($msgpackData['language']) : "english"; // default to english
    $ip             = $msgpackData['ip'];
    $userAgent      = $msgpackData['userAgent'];
    $marcaje        = $msgpackData['marcaje'];
    $marcajeTK      = $msgpackData['marcajeTK'];

    // Get Device Detial
    if($source == "Apps"){
        $deviceDetail['deviceModel']         = $msgpackData['deviceModel'];
        $deviceDetail['deviceManufacturer']  = $msgpackData['deviceManufacturer'];
        $deviceDetail['deviceName']          = $msgpackData['deviceName'];
        $deviceDetail['sourceVersionNo']     = $msgpackData['sourceVersionNo'];
        General::$deviceDetail = $deviceDetail;
    }

    General::$currentLanguage = $systemLanguage;
    General::$translations = $translations;
    General::$userAgent = $userAgent;
    General::$ip = $ip;

    Setting::$accessUser['userID']  = $userID;
    Setting::$accessUser['site']    = $site;
    Setting::$accessUser['source']  = $source;
    
    $filterBase64Arr = array("addAnnouncement","editAnnouncement","addDocument","editDocument","addMemo","editMemo","addTicket","addKYC","replyTicket","uploadReceipt");
    $filterKeyAry = array("imageData","attachmentData","base_64","imgData","fileData","imageBase64","imageData1","imageData2","imageBased64","image_data","attachment_data");
    if($command != "getWebservices") {
        // skip base64 data into db
        $filterMsgpackData = $msgpackData;
		// if(in_array($command, $filterBase64Arr)) Api::loopReplace($filterMsgpackData,$filterKeyAry);
        $webserviceID = Webservice::insertWebserviceData($filterMsgpackData, $tblDate, $createTime);
    }

	General::insertDailyTable("acc_credit");
    General::insertDailyTable("sent_history");
	
    $filterCommands = array("adminLogin", "memberLogin", "getRegistrationDetailMember", "publicRegistration", "publicRegistrationConfirmation", "memberRegistrationAdmin", "memberRegistrationConfirmationAdmin", "getRegistrationPaymentDetailMember", "getRegistrationPackageDetailMember", "sendOTPCode", "sendOTPCodeDouble", "memberResetPassword", "theNuxFundInCallBack", "apiRegistration", "apiLogin", "requestOTP", "apiInboxUnreadMessage", "apiDashboardDetails", "apiNewsDisplay", "apiWalletDetails", "apiWalletTransfer", "apiWalletWithdrawal", "apiDocumentDownloadList", "apiDocumentDownload", "apiEditMemberDetailMember", "apiGetCoinRate", "apiBuildRegistration", "apiGetTreeSponsor", "apiGetSponsorTreeVerticalView", "apiGetAffiliateBonusList", "apiChangeTransactionPassword", "apiForgotLoginPassword", "apiForgotTransactionPassword", "apiGetProfileDetails", "apiGetWalletHistory", "apiGetBtcData","getAllLanguage","getLanguageVersion","getLanguageList","recordPerformance","addKYC","theNuxFundOutCallback","getDeliveryOrderDetail", "getBuyProductDetails", "getProductIDForSearch", "addTicket", "addPublicTicket", "getTicketList","getECatalogueList","getECatalogue", "accountOwnerVerification", "memberResetPasswordVerification","getDashboardBanner", "memberGetMemberName", "memberGetMemoList", "nicepayCallback", "getInvoiceDetail", "accountSignUpVerification", "guestOwnerVerification", "getState", "getCategoryInventoryMember", "getBankDetails", "addNewPayment", "getProviderSettingFPX", "getProductInventoryList", "getProductDetails", "getProductListMember", "updateSaleOrder", "getPaymentDeliveryOptions", "FPXBackendVerify", "uploadReceipt", "getDeliveryMethod", "CheckOutCalculation");

    $filterSpecialCharCommands = array("theNuxFundInCallBack","theNuxFundOutCallback", "nicepayCallback", "memberLogin", "publicRegistrationConfirmation", "memberResetPassword", "FPXBackendVerify", "addPurchaseRequest", "purchaseOrderEdit", "assignSerial", "confirmSerial", "getPurchaseOrderList", "getPurchaseRequestList", "purchaseRequestEdit", "getProductDetails" );
    
    if ($source == "Apps" || $source == "Api"){

        $msgpackData['params']['appsBypass'] = true;

    }
    else if(!in_array($command, $filterCommands)) {

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
            if(!$isSessionExpired){
                $errCode = 5;
                $errMsg = "";
            }

            // If sessionID is invalid, we return as session timeout
            $outputArray = array('status' => "error", 'code' => $errCode, 'statusMsg' => $errMsg, 'data' => $returnData);

            Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            echo msgpack::msgpack_pack($outputArray);
            exit;
        }
    }

    if($source == "Apps") {
        $mobileOSAry = array("Android", "iOS");
        if($command != "getAllLanguage") {
            if(!in_array($sourceVersionNo, Setting::$configArray['appCurrentVersion']) ) {
                foreach (Setting::$configArray['appCurrentVersion'] as $key => $value) {
                    $latestVersion = $value;
                }

                $outputArray = array('status' => "error", 'code' => 999, 'statusMsg' => "Require update.", 'appUpdateData' => array("currentVersion" => $latestVersion, "actionPerform" => "update apps", "appsLink" => Setting::$configArray["downloadUrl"], "appsLinkGoogle" => Setting::$configArray["appsLinkGoogle"]));
                
                Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

                echo msgpack::msgpack_pack($outputArray);
                exit;
            }
        }
    }

    if($source=="Apps"){

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

                echo msgpack::msgpack_pack($outputArray);
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

        echo msgpack::msgpack_pack($checker);
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
                            echo msgpack::msgpack_pack($outputArray);
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
            echo msgpack::msgpack_pack($outputArray);
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
            
            echo msgpack::msgpack_pack($duplicate);
            exit;
        }
    }

    // Check whether to use sample output for this api
    if($isSample == 1) {
        $outputArray = Api::getSampleOutput($apiID);

        Webservice::updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

        echo msgpack::msgpack_pack($outputArray);
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

            case "memberRegistration":
                $apiType = "verify";
                $outputArray = Subscribe::memberRegistration($msgpackData['params']);
                break;

            case "memberRegistrationConfirmation":
                $apiType = "insert";
                $outputArray = Subscribe::memberRegistrationConfirmation($msgpackData['params']);
                break;

            case "publicRegistration":
                $apiType = "verify";
                $outputArray = Subscribe::memberRegistration($msgpackData['params']);
                break;

            case "publicRegistrationConfirmation":
                $apiType = "insert";
                $outputArray = Subscribe::memberRegistrationConfirmation($msgpackData);
                break;  

            case "adminLogin":
                $apiType = "verify";
                $outputArray = Admin::adminLogin($msgpackData['params']);
                break;

            case "getRoles":
                $apiType = "get";
                $outputArray = User::getRoles($msgpackData['params'],$userID);
                break;

            case "getAdminList":
                $apiType = "get";
                $outputArray = Admin::getAdminList($msgpackData['params']);
                break;

            case "getPurchaseRequestList":
                $apiType = "get";
                $outputArray = Admin::getPurchaseRequestList($msgpackData['params']);
                break;

            case "getVendorList":
                $apiType = "get";
                $outputArray = Admin::getVendorList($msgpackData['params']);
                break;

            case "getProductList":
                $apiType = "get";
                $outputArray = Admin::getProductList($msgpackData['params']);
                break;

            case "getAdminDetails":
                $apiType = "get";
                $outputArray = Admin::getAdminDetails($msgpackData['params']);
                break;

            case "addAdmins":
                $apiType = "insert";
                $outputArray = Admin::addAdmins($msgpackData['params']);
                break;

            case "addPurchaseRequest":
                $apiType = "insert";
                $outputArray = Admin::addPurchaseRequest($msgpackData['params']);
                break;
            
            case "addPurchaseProduct":
                $apiType = "insert";
                $outputArray = Admin::addPurchaseProduct($msgpackData['params']);
                break;

            case "editAdmins":
                $apiType = "insert";
                $outputArray = Admin::editAdmins($msgpackData['params']);
                break;

            case "purchaseRequestEdit":
                $apiType = "insert";
                $outputArray = Admin::purchaseRequestEdit($msgpackData['params'], $msgpackData['username']);
                break;

            case "getPortfolioList":
                $apiType = "get";
                $outputArray = Admin::getPortfolioList($msgpackData['params'], $site, $userID);
                break;

            case "getMemberList":
                $apiType = "get";
                $outputArray = Admin::getMemberList($msgpackData['params']);
                break;

            case "getMemberDetails":
                $apiType = "get";
                $outputArray = Admin::getMemberDetails($msgpackData['params']);
                break;

            case "editMemberDetails":
                $apiType = "insert";
                $outputArray = Admin::editMemberDetails($msgpackData['params']);
                break;

            case "changeMemberPassword":
                $apiType = "insert";
                $outputArray = Admin::changeMemberPassword($msgpackData['params']);
                break;

            case "getRankMaintain":
                $apiType = "get";
                $outputArray = Admin::getRankMaintain($msgpackData['params']);
                break;

            case "updateRankMaintain":
                $apiType = "insert";
                $outputArray = Admin::updateRankMaintain($msgpackData['params'],$userID,$site);
                break;

            case "verifyPaymentAdmin":
                $apiType = "verify";
                $outputArray = Client::verifyPayment($msgpackData['params']);
                break;

            case "getCreditTransactionList":
                $apiType = "get";
                $outputArray = Client::getCreditTransactionList($msgpackData['params']);
                break;

            case "getPinList":
                $apiType = "get";
                $outputArray = Product::getPinList($msgpackData['params']);
                break;

            case "getPinDetail":
                $apiType = "get";
                $outputArray = Product::getPinDetail($msgpackData['params']);
                break;

            case "updatePinDetail":
                $apiType = "insert";
                $outputArray = Product::updatePinDetail($msgpackData['params']);
                break;

            case "getPinPurchaseFormDetail":
                $apiType = "get";
                $outputArray = Product::getPinPurchaseFormDetail($msgpackData['params']);
                break;

            case "getProductDetail":
                $apiType = "get";
                $outputArray = Admin::getProductDetail($msgpackData['params']);
                break;

            case "getActivityLogList":
                $apiType = "get";
                $outputArray = Admin::getActivityLogList($msgpackData['params']);
                break;

            case "getLanguageTranslationList":
                $apiType = "get";
                $outputArray = Admin::getLanguageTranslationList($msgpackData['params']);
                break;

            case "getLanguageTranslationData":
                $apiType = "get";
                $outputArray = Admin::getLanguageTranslationData($msgpackData['params']);
                break;

            case "editLanguageTranslationData":
                $apiType = "insert";
                $outputArray = Admin::editLanguageTranslationData($msgpackData['params']);
                break;

            case "getExchangeRateList":
                $apiType = "get";
                $outputArray = Admin::getExchangeRateList($msgpackData['params']);
                break;

            case "editExchangeRate":
                $apiType = "insert";
                $outputArray = Admin::editExchangeRate($msgpackData['params']);
                break;

            case "getCVRateList":
                $apiType = "get";
                $outputArray = Admin::getCVRateList($msgpackData['params']);
                break;

            case "editCVRate":
                $apiType = "insert";
                $outputArray = Admin::editCVRate($msgpackData['params']);
                break;

            case "getCVRateHistory":
                $apiType = "get";
                $outputArray = Admin::getCVRateHistory($msgpackData['params']);
                break;

            case "getUnitPriceList":
                $apiType = "get";
                $outputArray = Admin::getUnitPriceList($msgpackData['params']);
                break;

            case "addUnitPrice":
                $apiType = "insert";
                $outputArray = Admin::addUnitPrice($msgpackData['params']);
                break;

            case "getAdminWithdrawalList":
                $apiType = "get";
                $outputArray = Wallet::getAdminWithdrawalList($msgpackData['params'],$userID);
                break;

            case "getAdminWithdrawalListBankDetails":
                $apiType = "get";
                $outputArray = Admin::getAdminWithdrawalListBankDetails($msgpackData['params']);
                break;

            case "getAdminClientWithdrawalDetail":
                $apiType = "get";
                $outputArray = Wallet::getWithdrawalDetailByID($msgpackData['params']);
                break;

            case "editAdjustmentDetailAdmin":
                $apiType = "insert";
                $outputArray = Wallet::creditAdjustment($msgpackData['params']);
                break;

            case "checkProductAndGetClientCreditType":
                $apiType = "get";
                $outputArray = Product::checkProductAndGetClientCreditType($msgpackData['params']);
                break;

            case "getTreeSponsor":
                $apiType = "get";
                $outputArray = Client::getTreeSponsor($msgpackData['params']);
                break;

            case "getTreePlacement":
                $apiType = "get";
                $outputArray = Client::getTreePlacement($msgpackData['params']);
                break;

            case "getSponsorTreeTextView":
                $apiType = "get";
                $outputArray = Client::getSponsorTreeTextView($msgpackData['params']);
                break;

            case "getSponsorTreeVerticalView":
                $apiType = "get";
                $outputArray = Tree::getSponsorTree($msgpackData['params']);
                break;

            case "getPlacementTreeVerticalView":
                $apiType = "get";
                $outputArray = Client::getPlacementTreeVerticalView($msgpackData['params']);
                break;

            case "getUpline":
                $apiType = "get";
                $outputArray = Client::getUpline($msgpackData['params']);
                break;

            case "getSponsor":
                $apiType = "get";
                $outputArray = Client::getSponsor($msgpackData['params']);
                break;

            case "getPlacement":
                $apiType = "get";
                $outputArray = Client::getPlacement($msgpackData['params']);
                break;

            case "getCreditData":
                $apiType = "get";
                $outputArray = Wallet::getCreditData($msgpackData['params']);
                break;

            case "changeSponsor":
                $apiType = "insert";
                $outputArray = Client::changeSponsor($msgpackData['params']);
                break;

            case "changePlacement":
                $apiType = "insert";
                $outputArray = Client::changePlacement($msgpackData['params']);
                break;

            case "getAnnouncementList":
                $apiType = "get";
                $outputArray = Bulletin::getAnnouncementList($msgpackData['params']);
                break;

            case "addAnnouncement":
                $apiType = "insert";
                $outputArray = Bulletin::addAnnouncement($msgpackData['params'], $site);
                break;

            case "getAnnouncement":
                $apiType = "get";
                $outputArray = Bulletin::getAnnouncement($msgpackData['params']);
                break;

            case "editAnnouncement":
                $apiType = "insert";
                $outputArray = Bulletin::editAnnouncement($msgpackData['params'], $site);
                break;

            case "removeAnnouncement":
                $apiType = "insert";
                $outputArray = Bulletin::removeAnnouncement($msgpackData['params']);
                break;

            case "getMemoList":
                $apiType = "get";
                $outputArray = Bulletin::getMemoList($msgpackData['params']);
                break;

            case "memberGetMemoList":
                $apiType = "get";
                $outputArray = Client::memberGetMemoList();
                break;

            case "addMemo":
                $apiType = "insert";
                $outputArray = Bulletin::addMemo($msgpackData['params'], $site);
                break;

            case "getMemo":
                $apiType = "get";
                $outputArray = Bulletin::getMemo($msgpackData['params']);
                break;

            case "editMemo":
                $apiType = "insert";
                $outputArray = Bulletin::editMemo($msgpackData['params'], $site);
                break;

            case "removeMemo":
                $apiType = "insert";
                $outputArray = Bulletin::removeMemo($msgpackData['params'], $site);
                break;

            // start of eCatalogue
            case "addECatalogue":
                $apiType = "insert";
                $outputArray = Bulletin::addDocument($msgpackData['params'], "eCatalogue", "add");
                break;

            case "getECatalogueList":
                $apiType = "get";
                $outputArray = Bulletin::getDocumentList($msgpackData['params'], "eCatalogue");
                break;

            case "getECatalogue":
                $apiType = "get";
                $outputArray = Bulletin::getDocument($msgpackData['params']);
                break;

            case "editECatalogue":
                $apiType = "insert";
                $outputArray = Bulletin::addDocument($msgpackData['params'], "eCatalogue", "edit");
                break;

            case "removeECatalogue":
                $apiType = "insert";
                $outputArray = Bulletin::removeDocument($msgpackData['params'], "eCatalogue");
                break;
            // start of eCatalogue

            case "getDocumentList":
                $apiType = "get";
                $outputArray = Bulletin::getDocumentList($msgpackData['params']);
                break;

            case "addDocument":
                $apiType = "insert";
                $outputArray = Bulletin::addDocument($msgpackData['params'], "normal", "add");
                break;

            case "getDocument":
                $apiType = "get";
                $outputArray = Bulletin::getDocument($msgpackData['params']);
                break;

            case "editDocument":
                $apiType = "insert";
                $outputArray = Bulletin::editDocument($msgpackData['params']);
                break;

            case "removeDocument":
                $apiType = "insert";
                $outputArray = Bulletin::removeDocument($msgpackData['params'], "normal");
                break;

            case "getTicketList":
                $apiType = "get";
                $outputArray = Ticket::getTicketList($msgpackData['params'], $userID, $site);
                break;

            case "getTicketDetail":
                $apiType = "get";
                $outputArray = Ticket::getTicketDetail($msgpackData['params'], $userID);
                break;

            case "replyTicket":
                $apiType = "ignore";
                $outputArray = Ticket::replyTicket($msgpackData['params'], $site);
                break;

            case "updateTicketStatus":
                $apiType = "insert";
                $outputArray = Ticket::updateTicketStatus($msgpackData['params']);
                break;

            case "massChangePassword":
                $apiType = "insert";
                $outputArray = Batch::massChangePassword($msgpackData['params'], $site);
                break;

            case "adminBatchRegistration":
                $apiType = "insert";
                $outputArray = Subscribe::adminBatchRegistration($msgpackData['params'], $site);
                break;

            case "adminSpecialBatchRegistration":
                $apiType = "insert";
                $outputArray = Batch::adminSpecialBatchRegistration($msgpackData['params'], $site);
                break;

            case "adminBatchCreditAdjustment":
                $apiType = "insert";
                $outputArray = Batch::adminBatchCreditAdjustment($msgpackData['params'], $site);
                break;

            case "adminBatchStatusAdjustment":
                $apiType = "insert";
                $outputArray = Batch::adminBatchStatusAdjustment($msgpackData['params'], $site);
                break;

            case "adminBatchAddWaterBucket":
                $apiType = "insert";
                $outputArray = Batch::adminBatchAddWaterBucket($msgpackData['params'], $site, $userID);
                break;

            case "batchAdjustTradingLimit":
                $apiType = "insert";
                $outputArray = Batch::batchAdjustTradingLimit($msgpackData['params']);
                break;

            case "getImportData":
                $apiType = "get";
                $outputArray = Batch::getImportData($msgpackData['params']);
                break;

            case "getImportDataDetails":
                $apiType = "get";
                $outputArray = Batch::getImportDataDetails($msgpackData['params']);
                break;

            case "getCountriesList":
                $apiType = "get";
                $outputArray = Country::getCountriesList($msgpackData['params']);
                break;

            case "getRegistrationDetailAdmin":
                $apiType = "get";
                $outputArray = Client::getRegistrationDetails($msgpackData['params']);
                break;

            case "getRegistrationPackageDetailAdmin":
                $apiType = "get";
                $outputArray = Client::getRegistrationPackageDetails($msgpackData['params']);
                break;

            case "getRegistrationPaymentDetailAdmin":
                $apiType = "get";
                $outputArray = Client::getRegistrationPaymentDetails($msgpackData['params']);
                break;

            case "getViewMemberDetails":
                $apiType = "get";
                $outputArray = Client::getViewMemberDetails($msgpackData['params']);
                break;

            case "getClientRepurchasePinDetail":
                $apiType = "get";
                $outputArray = Product::getClientRepurchasePinDetail($msgpackData['params']);
                break;

            case "getClientRepurchasePackageDetailAdmin":
                $apiType = "get";
                $outputArray = Client::getClientRepurchasePackageDetail($msgpackData['params']);
                break;

            case "getMemberAccList":
                $apiType = "get";
                $outputArray = Admin::getMemberAccList($msgpackData['params']);
                break;

            case "getMemberBalanceAdmin":
                $apiType = "get";
                $outputArray = Admin::getMemberBalance($msgpackData['params']);
                break;

            case "transferCreditAdmin":
                $apiType = "verify";
                $outputArray = Wallet::transferCredit($msgpackData['params'], $site);
                break;

            case "transferCreditConfirmationAdmin":
                $apiType = "insert";
                $outputArray = Wallet::transferCreditConfirmation($msgpackData['params'], $site);
                break;

            case "getWithdrawalBankList":
                $apiType = "get";
                $outputArray = Country::getBankListByCountryID($msgpackData['params']);
                break;

            case "getWithdrawalDetailAdmin":
                $apiType = "get";
                $outputArray = Wallet::getWithdrawalDetail($msgpackData['params']);
                break;

            case "addNewWithdrawalAdmin":
                $apiType = "insert";
                $outputArray = Wallet::addNewWithdrawal($msgpackData['params']);
                break;

            case "getBankAccountListAdmin":
                $apiType = "get";
                $outputArray = Client::getBankAccountList($msgpackData['params']);
                break;

            case "updateBankAccStatusAdmin":
                $apiType = "insert";
                $outputArray = Client::updateBankAccStatus($msgpackData['params']);
                break;

            case "getLeaderGroupSalesReport":
                $apiType = "get";
                $outputArray = Report::getLeaderGroupSalesReport($msgpackData['params']);
                break;

            case "getSalesPlacementReport":
                $apiType = "get";
                $outputArray = Report::getSalesPlacementReport($msgpackData['params']);
                break;

            case "getSalesPurchaseReport":
                $apiType = "get";
                $outputArray = Report::getSalesPurchaseReport($msgpackData['params']);
                break;

            case "getOwnMonthlySalesSummary";
                $apiType = "get";
                $outputArray = Report::getOwnMonthlySalesSummary($msgpackData['params']);
                break;

            case "getOwnMonthlyPerformanceReport";
                $apiType = "get";
                $outputArray = Report::getOwnMonthlyPerformanceReport($msgpackData['params']);
                break;

            case "getCustomerServiceMemberDetails":
                $apiType = "get";
                $outputArray = Client::getCustomerServiceMemberDetails("", $msgpackData['params']);
                break;

            case "getLanguageCodeList":
                $apiType = "get";
                $outputArray = Language::getLanguageCodeList($msgpackData['params'],$site);
                break;

            case "getLanguageCodeData":
                $apiType = "get";
                $outputArray = Language::getLanguageCodeData($msgpackData['params']);
                break;

            case "editLanguageCodeData":
                $apiType = "insert";
                $outputArray = Language::editLanguageCodeData($msgpackData['params']);
                break;

            // Member Site
            case "memberLogin":
                $apiType = "verify";
                $outputArray = Client::memberLogin($msgpackData);
                break;

            case "getDashboard":
                $apiType = "get";
                $outputArray = Dashboard::getDashboard($msgpackData['params']);
                break;

            case "getTransactionHistory":
                $apiType = "get";
                $outputArray = Wallet::getTransactionHistory($msgpackData['params']);
                break;

            case "memberTransferCredit":
                $apiType = "verify";
                $outputArray = Wallet::transferCredit($msgpackData['params'],$site);
                break;

            case "memberTransferCreditConfirmation":
                $apiType = "insert";
                $outputArray = Wallet::transferCreditConfirmation($msgpackData['params']);
                break;

            case "getMemberBankList":
                $apiType = "get";
                $outputArray = Client::getMemberBankList($msgpackData['params']);
                break;

            case "memberAddNewWithdrawal":
                $apiType = "verify";
                $outputArray = Wallet::addNewWithdrawal($msgpackData['params'],$site);
                break;

            case "memberAddNewWithdrawalConfirmation":
                $apiType = "insert";
                $outputArray = Wallet::addNewWithdrawalConfirmation($msgpackData['params'],$site);
                break;

            case "getWithdrawalListing":
                $apiType = "get";
                $outputArray = Client::getWithdrawalListing($msgpackData['params']);
                break;

            case "addTicket":
                $apiType = "insert";
                $outputArray = Ticket::addTicket($msgpackData['params'], $site, "ticket");
                break;

            case "addFiatTicket":
                $apiType = "insert";
                $outputArray = Ticket::addTicket($msgpackData['params'], $site, "fiatTicket");
                break;

            case "addPublicTicket":
                $apiType = "insert";
                $outputArray = Ticket::addTicket($msgpackData['params'], $site, "publicTicket");
                break;

            case "documentDownloadList":
                $apiType = "get";
                $outputArray = Bulletin::documentDownloadList($msgpackData['params']);
                break;

            case "documentDownload":
                $apiType = "get";
                $outputArray = Bulletin::documentDownload($msgpackData['params']);
                break;

            case "newsDisplay":
                $apiType = "get";
                $outputArray = Bulletin::newsDisplay($msgpackData['params'], $userID);
                break;

            case "newsDownload":
                $apiType = "get";
                $outputArray = Bulletin::newsDownload($msgpackData['params']);
                break;

            case "getInboxListing":
                $apiType = "get";
                $outputArray = Ticket::getInboxListing($msgpackData['params']);
                break;

            case "getInboxMessages":
                $apiType = "get";
                $outputArray = Ticket::getInboxMessages($msgpackData['params']);
                break;

            case "addInboxMessages":
                $apiType = "insert";
                $outputArray = Ticket::addInboxMessages($msgpackData['params'], $site);
                break;

            case "transferPin":
                $apiType = "insert";
                $outputArray = Product::transferPin($msgpackData['params']);
                break;

            case "getRegistrationDetailMember":
                $apiType = "get";
                $outputArray = Client::getRegistrationDetails($msgpackData['params']);
                break;

            case "getRegistrationPaymentDetailMember":
                $apiType = "get";
                $outputArray = Client::getRegistrationPaymentDetails($msgpackData['params']);
                break;

            case "verifyPaymentMember":
                $apiType = "verify";
                $outputArray = Client::verifyPayment($msgpackData['params']);
                break;

            case "getRegistrationPackageDetailMember":
                $apiType = "get";
                $outputArray = Client::getRegistrationPackageDetails($msgpackData['params']);
                break;

            case "memberChangePassword":
                $apiType = "insert";
                $outputArray = Client::memberChangePassword($msgpackData['params']);
                break;

            case "memberChangeTransactionPassword":
                $apiType = "insert";
                $outputArray = Client::memberChangePassword($msgpackData['params']);
                break;

            case "getBankAccountListMember":
                $apiType = "get";
                $outputArray = Client::getBankAccountList($msgpackData['params']);
                break;

            case "getBankAccountDetail":
                $apiType = "get";
                $outputArray = Client::getBankAccountDetail($msgpackData['params']);
                break;

            case "addBankAccountDetail":
                $apiType = "insert";
                $outputArray = Client::addBankAccountDetail($msgpackData['params']);
                break;

            case "updateBankAccStatusMember":
                $apiType = "insert";
                $outputArray = Client::updateBankAccStatus($msgpackData['params']);
                break;

            case "getMemberDetailMember":
                $apiType = "get";
                $outputArray = Admin::getMemberDetails($msgpackData['params']);
                break;

            case "editMemberDetailMember":
                $apiType = "insert";
                $outputArray = Admin::editMemberDetails($msgpackData['params']);
                break;

            case "getMemberLoginDetail":
                $apiType = "get";
                $outputArray = Admin::getMemberLoginDetail($msgpackData['params']);
                break;

            case "getWhoIsOnlineList":
                $apiType = "get";
                $outputArray = Admin::getWhoIsOnlineList($msgpackData['params']);
                break;

            case "getClientRightsList":
                $apiType = "get";
                $outputArray = Admin::getClientRightsList($msgpackData['params']);
                break;

            case "lockAccount":
                $apiType = "insert";
                $outputArray = Admin::lockAccount($msgpackData['params']);
                break;

            case "leaderLockAccount":
                $apiType = "insert";
                $outputArray = Admin::leaderLockAccount($msgpackData['params'], $site, $userID);
                break;

            case "getWallets":
                $apiType = "get";
                $outputArray = Dashboard::getWallets($msgpackData['params']);
                break;

            case "addRole":
                $apiType = "insert";
                $outputArray = User::addRole($msgpackData['params']);
                break;

            case "getRoleDetails":
                $apiType = "get";
                $outputArray = User::getRoleDetails($msgpackData['params']);
                break;

            case "getPermissions":
                $apiType = "get";
                $outputArray = Permission::getPermissions($msgpackData['params'],$userID,$site);
                break;

            case "getRoleNames":
                $apiType = "get";
                $outputArray = Permission::getRoleNames();
                break;

            case "getPermissionNames":
                $apiType = "get";
                $outputArray = Permission::getPermissionNames($msgpackData['params']);
                break;

            case "getRolePermissionData":
                $apiType = "get";
                $outputArray = Permission::getRolePermissionData($msgpackData['params']);
                break;

            case "deleteRole":
                $apiType = "insert";
                $outputArray = User::deleteRole($msgpackData['params']);
                break;

            case "editRole":
                $apiType = "insert";
                $outputArray = User::editRole($msgpackData['params']);
                break;
                
            case "editRolePermission":
                $apiType = "insert";
                $outputArray = Permission::editRolePermission($msgpackData['params'],$userID,$site);
                break;

            case "getPaymentMethodList":
                $apiType = "get";
                $outputArray = Admin::getPaymentMethodList($msgpackData['params']);
                break;

            case "getPaymentMethodDetails":
                $apiType = "get";
                $outputArray = Admin::getPaymentMethodDetails($msgpackData['params']);
                break;

            case "editPaymentMethod":
                $apiType = "insert";
                $outputArray = Admin::editPaymentMethod($msgpackData['params']);
                break;

            case "deletePaymentMethod":
                $apiType = "insert";
                $outputArray = Admin::deletePaymentMethod($msgpackData['params']);
                break;

            case "getPaymentSettingDetails":
                $apiType = "get";
                $outputArray = Admin::getPaymentSettingDetails();
                break;

            case "addPaymentMethod":
                $apiType = "insert";
                $outputArray = Admin::addPaymentMethod($msgpackData['params']);
                break;

            case "getSponsorBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getSponsorBonusReport($msgpackData['params'],$userID,$site);
                break;
                
            case "getGoldmineBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getGoldmineBonusReport($msgpackData['params'], $userID, $site);
                break;

            case "getTeamBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getTeamBonusReport($msgpackData['params'], $userID, $site);
                break;

            case "getAwardBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getAwardBonusReport($msgpackData['params'], $userID, $site);
                break;

            case "getMatchingBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getMatchingBonusReport($msgpackData['params']);
                break;

            case "getRebateBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getRebateBonusReport($msgpackData['params'], $userID, $site);
                break;

            case "getWaterBucketBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getWaterBucketBonusReport($msgpackData['params'], $userID, $site);
                break;
                
            case "getReleaseBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getReleaseBonusReport($msgpackData['params']);
                break;

            case "getLeadershipBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getLeadershipBonusReport($msgpackData['params']);
                break;

            case "getLeadershipCashRewardReport":
                $apiType = "get";
                $outputArray = BonusReport::getLeadershipCashRewardReport($msgpackData['params']);
                break;

            case "sendOTPCode":
                $apiType = "insert";
                $outputArray = Otp::sendOTPCode($msgpackData['params'], $userID);
                break;

            case "sendOTPCodeDouble":
                $apiType = "insert";
                $outputArray = Otp::sendOTPCodeDouble($msgpackData['params'], $userID);
                break;

            case "memberResetPassword":
                $apiType = "insert";
                $outputArray = Client::memberResetPassword($msgpackData['params']);
                break;

            case "memberResetTransactionPassword":
                $apiType = "insert";
                $outputArray = Client::memberResetTransactionPassword($msgpackData['params']);
                break;

            case "getInboxUnreadMessage":
                $apiType = "get";
                $outputArray = Client::getInboxUnreadMessage($userID, $site);
                break;
                
            case "memberAddTransactionPassword":
                $apiType = "insert";
                $outputArray = Client::addTransactionPassword($msgpackData['params'], $userID);
                break;

            case "theNuxFundInCallBack":
                $apiType = "callback";
                $outputArray = CryptoPG::theNuxFundInCallBack($msgpackData['params']);
                break;

            case "theNuxFundOutCallback":
                $apiType = "callback";
                $outputArray = CryptoPG::theNuxFundOutCallback($msgpackData['params']);
                break;

            case "getBalanceReport":
                $apiType = "get";
                $outputArray = Report::getBalanceReport($msgpackData['params'], $userID, $site);
                break;

            case "verifyTransactionPassword":
                $apiType = "verify";
                $outputArray = Client::verifyTransactionPassword($msgpackData['params']['clientID'], $msgpackData['params']['tPassword']);
                break;

            case "getWithdrawalUnreadCount":
                $apiType = "get";
                $outputArray = Admin::getWithdrawalUnreadCount($userID);
                break;

            case "getBonusPayoutSummary":
                $apiType = "get";
                $outputArray = BonusReport::getBonusPayoutSummary($params,$site,$userID);
                break;

            case "getBonusPayoutListing":
                $apiType = "get";
                $outputArray = BonusReport::getBonusPayoutListing($params);
                break;

            case "addKYC":
                $apiType = "insert";
                $outputArray = Client::addKYC($params);
                break;

            case "adminEditKYC":
                $apiType = "insert";
                $outputArray = Client::adminEditKYC($params);
                break;

            case "getKYCDetails": 
                $apiType = "get";
                $outputArray = Client::getKYCDetails($params);
                break;

            case "updateKYC":
                $apiType = "insert";
                $outputArray = Client::updateKYC($params);
                break;

            case "getKYCDataByID":
                $apiType = "get";
                $outputArray = Client::getKYCDataByID($params);
                break;

            case "getKYCListing":
                $apiType = "get";
                $outputArray = Client::getKYCListing($params);
                break;

            case "getImageByID":
                $apiType = "get";
                $outputArray = Client::getImageByID($params);
                break;
            
            case "setLanguage":
                $apiType = "insert";
                $outputArray = Language::setLanguage($params);
                break;

            case "setLeader":
                $apiType = "insert";
                $outputArray = leader::setLeader($params, $userID);
                break;

            case "getLeaderList":
                $apiType = "get";
                $outputArray = leader::getLeaderList();
                break;

            case "getLeaderSettingListing":
                $apiType = "get";
                $outputArray = leader::getLeaderSettingListing($params);
                break;

            case "setLeaderSetting":
                $apiType = "insert";
                $outputArray = leader::setLeaderSetting($params);
                break;

            case "updateLeaderSetting":
                $apiType = "insert";
                $outputArray = leader::updateLeaderSetting($params);
                break;

            case "getProductList":
                $apiType = "get";
                $outputArray = Product::getProductList($params);
                break;
         
            case "convertCreditVerification":
                $apiType = "verify";
                $outputArray = Wallet::convertCreditVerification($params,$userID,$site);
                break;

            case "convertCreditConfirmation":
                $apiType = "insert";
                $outputArray = Wallet::convertCreditConfirmation($params,$userID,$site);
                break;  

            case "getFundInListing":
                $apiType = "get";
                $outputArray = CryptoPG::getFundInListing($msgpackData['params'], $site,$userID);
                break;

            case "getLanguageList":
                $apiType = "get";
                $outputArray = Language::getLanguageList();
                break;

            case "getAllLanguage":
                $apiType = "get";
                $outputArray = Language::getAllLanguage();
                break;

            case "getLanguageVersion":
                $apiType = "get";
                $outputArray = Language::getLanguageVersion();
                break;

            case "addWalletAddress":
                $apiType = "insert";
                $outputArray = Client::addWalletAddress($msgpackData['params']);
                break;

            case "getWalletAddressListing":
                $apiType = "get";
                $outputArray = Client::getWalletAddressListing($msgpackData['params'],$userID);
                break; 

            case "inactiveWalletAddress":
                $apiType = "insert";
                $outputArray = Client::inactiveWalletAddress($msgpackData['params'],$userID);
                break;

            case "inactiveBankAccount":
                $apiType = "insert";
                $outputArray = Client::inactiveBankAccount($msgpackData['params'],$userID);
                break;

            case "setWaterBucketPercentage":
                $apiType = "insert";
                $outputArray = Bonus::setWaterBucketPercentage($msgpackData['params'], $site, $userID);
                break;

            case "getWaterBucketPercentage":
                $apiType = "get";
                $outputArray = BonusReport::getWaterBucketPercentage($msgpackData['params']);
                break;

            case "getAvailableCreditWalletAddress":
                $apiType = "get";
                $outputArray = Client::getAvailableCreditWalletAddress($site);
                break;

            case "recordPerformance":
                $apiType = "ignore";
                $outputArray = Message::recordPerformance($params,$source);
                break;

            case "getBonusListing":
                $apiType = "get";
                $outputArray = BonusReport::getBonusListing($params);
                break;

            case "updateWithdrawalStatus":
                $apiType = "insert";
                $outputArray = Wallet::updateWithdrawalStatus($params,$userID,$site);
                break;

             case "batchUpdateWithdrawalStatus":
                $apiType = "insert";
                $outputArray = Wallet::batchUpdateWithdrawalStatus($params,$userID,$site);
                break;

            case "adminChangePassword":
                $apiType = "insert";
                $outputArray = Admin::adminChangePassword($msgpackData['params'],$userID);
                break;

            case "addMlmQueue":
                $apiType = "insert";
                $outputArray = Queue::addMlmQueue($msgpackData['params'],$userID);
                break;

            case "adminSearchDownline":
                $apiType = "get";
                $outputArray = Client::adminSearchDownline($msgpackData['params']);
                break;

            case "adminGetWalletAddressListing":
                $apiType = "get";
                $outputArray = Client::getWalletAddressListing($params);
                break;

            case "updateWalletAddressStatusAdmin":
                $apiType = "insert";
                $outputArray = Client::updateWalletAddressStatus($msgpackData['params']);
                break;

            case "adminUpdateEstimatedDate":
                $apiType = "insert";
                $outputArray = Admin::adminUpdateEstimatedDate($params);
                break;

            case "adminBatchUpdateWithdrawal":
                $apiType = "insert";
                $outputArray = Batch::adminBatchUpdateWithdrawal($params,$site,$userID);
                break;

            case "getPortfolioListRebateLock":
                $apiType = "get";
                $specialFilterArray=$arrayName = array(
                    'portfolio_type' =>'freeWithRebate',
                    'status'=>'Active'
                );
                $outputArray = Admin::getPortfolioList($msgpackData['params'], $site, $userID,$specialFilterArray);
                break;

            case "getPortfolioListRebateWithholding":
                $apiType = "get";
                $specialFilterArray=$arrayName = array(
                    'portfolio_type' =>'freeWithRebate',
                    'status'=>'Active',
                    'productName'=>'quantum'
                );
                $outputArray = Admin::getPortfolioList($msgpackData['params'], $site, $userID,$specialFilterArray);
                break;

            case "getBlockMemberLoginByRegisteredCountry":
                $apiType = "get";
                $outputArray = Admin::getBlockMemberLoginByCountryIP($params,'registered_block_login');
                break;

            case "setBlockMemberLoginByRegisteredCountry":
                $apiType = "insert";
                $outputArray = Admin::setBlockMemberLoginByCountryIP($params,'registered_block_login');
                break;

            case "getBlockMemberLoginByCountryIPandTree":
                $apiType = "get";
                $outputArray = Admin::getBlockMemberLoginByCountryIPandTree($params);
                break;

            case "setBlockMemberLoginByCountryIPandTree":
                $apiType = "insert";
                $outputArray = Admin::setBlockMemberLoginByCountryIPandTree($params);
                break;

            case "addExcelReq":
                $apiType = "insert";
                $outputArray = Excel::addExcelReq($params);
                break;

            case "getExcelReqList":
                $apiType = "get";
                $outputArray = Excel::getExcelReqList($params);
                break;

            case "getLanguageUploadFileList":
                $apiType = "get";
                $outputArray = Language::getLanguageUploadFileList($params);
                break;
            
            case "uploadFile":
                $apiType = "insert";
                $outputArray = Language::uploadFile($params,$userID,$site);
                break;

            case "getPaymentDetail":
                $apiType = "get";
                $outputArray = Cash::getPaymentDetail($params,$userID);
                break;
            
            case 'getNavBarDetails':
                $apiType = "get";
                $outputArray = Dashboard::getNavBarDetails($params);
                break;

            case 'updateAuthorizedAgreement': 
                $apiType = "insert";
                $outputArray = Client::updateClientData($params,"client","freezed");
                break;

            case 'assignMemberWalletAddress': 
                $apiType = "insert";
                $outputArray = CryptoPG::assignMemberWalletAddress($params, $userID);
                break;

            case 'getTeamBonusReport':
                $apiType = "get";
                $outputArray = BonusReport::getTeamBonusReport($params, $userID, $site);
                break;
            
            case 'getCryotoData':
                $apiType = "get";
                $outputArray = CryptoPG::getCryotoData($params);
                break;

            case 'getGlobalPoolShareReport':
                $apiType = "get";
                $outputArray = BonusReport::getGlobalPoolShareReport($params,$site,$userID);
                break;

            case "terminatePortfolio":
                $apiType = "insert";
                $outputArray = Client::terminatePortfolio($userID,$params);
                break;

            case "getCreditType":
                $apiType = "get";
                $outputArray = Admin::getCreditType($params);
                break;

            case "getAdjustLimitCreditType":
                $apiType = "get";
                $outputArray = Admin::getCreditType($params,"convertCap");
                break;

			case "getPagePermission":
                $apiType = "get";
                $outputArray = Admin::getPagePermission($params,$userID);
                break;

            case "getAllBankAccountDetail":
                $apiType = "get";
                $outputArray = Client::getAllBankAccountDetail($params);
                break;

            case "readAnnouncement":
                $apiType = "insert";
                $outputArray = Bulletin::readAnnouncement($params,$userID);
                break;

			case "getDocumentAnnouncementUnreadMessage":
                $apiType = "get";
                $outputArray = Bulletin::getDocumentAnnouncementUnreadMessage($params,$userID);
                break;

            case "updateMemberUpline":
                $apiType = "insert";
                $outputArray = Tree::updateMemberUpline($msgpackData['params'],$userID);
                break;

            case 'reentryVerification':
                $apiType = "verify";
                $outputArray = Subscribe::reentryVerification($params);
                break;

            case 'reentryConfirmation':
                $apiType = "insert";
                $outputArray = Subscribe::reentryConfirmation($params);
                break;

            case 'upgradeReentryVerification':
                $apiType = "verify";
                $outputArray = Subscribe::reentryVerification($params, "upgrade");
                break;

            case 'upgradeReentryConfirmation':
                $apiType = "insert";
                $outputArray = Subscribe::reentryConfirmation($params, "upgrade");
                break;

            case 'createReentryVerification':
                $apiType = "verify";
                $outputArray = Subscribe::reentryVerification($params, "create");
                break;

            case 'createReentryConfirmation':
                $apiType = "insert";
                $outputArray = Subscribe::reentryConfirmation($params, "create");
                break;

            case 'getTheNuxTransactionToken':
                $apiType = "get";
                $outputArray = CryptoPG::getTheNuxTransactionToken($params, $userID);
                break;

            case 'insertTheNuxFundInTransactionID':
                $apiType = "insert";
                $outputArray = CryptoPG::insertTheNuxFundInTransactionID($params);
                break;

            case 'getPackageReentryData':
                $apiType = "get";
                $outputArray = Subscribe::getReentryData($params);
                break;

            case 'getCreateReentryData':
                $apiType = "get";
                $outputArray = Subscribe::getReentryData($params, "create");
                break;

            case 'getUpgradeReentryData':
                $apiType = "get";
                $outputArray = Subscribe::getReentryData($params, "upgrade");
                break;

            case 'getPinPurchaseData':
                $apiType = "get";
                $outputArray = Product::getPinPurchaseData($params);
                break;  

            case 'getTreeOctopus':
                $apiType = "get";
                $outputArray = Tree::getTreeOctopus($params);
                break;

            case "getTreeOctopusByVertical":
                $apiType = "get";
                $outputArray = Tree::getTreeOctopusByViewType($params, "vertical");
                break;

            case 'purchasePinVerification':
                $apiType = "verify";
                $outputArray = Product::purchasePinVerification($params);
                break;

            case 'purchasePinConfirmation':
                $apiType = "insert";
                $outputArray = Product::purchasePinConfirmation($params);
                break;

            case "getRebatePercentageList":
                $apiType = "get";
                $outputArray = Admin::getRebatePercentageList($params);
                break;

            case "updateRebatePercentage":
                $apiType = "insert";
                $outputArray = Admin::updateRebatePercentage($params);
                break;

            case "getAutoWithdrawalData":
                $apiType = "get";
                $outputArray = Admin::getAutoWithdrawalData($params,$userID,$site);
                break;

            case "adminSetAutoWithdrawal":
                $apiType = "insert";
                $outputArray = Admin::adminSetAutoWithdrawal($params,$userID,$site);
                break; 
            
            case "purchasePC":
                $apiType = "insert";
                $outputArray = Wallet::purchasePC($params,$site);
                break; 

            case "getAvailablePurchaseCredit":
                $apiType = "get";
                $outputArray = Wallet::getAvailablePurchaseCredit($params,$site);
                break; 

            case "purchaseCredit":
                $apiType = "insert";
                $outputArray = Wallet::purchaseCredit($params);
                break;

            case "getPurchaseCreditListing":
                $apiType = "get";
                $outputArray = Report::getPurchaseCreditListing($params,$site);
                break;

            case 'setMainLeader':
                $apiType = "insert";
                $outputArray = Leader::setLeader($params);
                break;

            case 'getMainLeaderList':
                $apiType = "get";
                $outputArray = Leader::getLeaderList($params);
                break;

            case 'removeLeader':
                $apiType = "insert";
                $outputArray = Leader::removeMainLeader($params);
                break;

            case "updateAutowitdrawalStatus":
                $apiType = "insert";
                $outputArray = Wallet::updateAutowitdrawalStatus($params,$userID);
                break; 
                
            case 'validateClient':
                $apiType = "verify";
                $outputArray = Validation::validateClient($params);
                break;

            case 'validateCreateAdvertisement':
                $apiType = "verity";
                $outputArray = P2P::validateCreateAdvertisement($params, $userID);
                break;

            case 'createAdvertisement':
                $apiType = "insert";
                $outputArray = P2P::createAdvertisement($params, $userID);
                break;

            case 'getCreateAdvertisementData':
                $apiType = "get";
                $outputArray = P2P::getCreateAdvertisementData($params, $userID);
                break;

            case 'getAdvertisementListing':
                $apiType = "get";
                $outputArray = P2P::getAdvertisementListing($params, $userID, $site);
                break;

            case 'getAdvertisementDetail':
                $apiType = "get";
                $outputArray = P2P::getAdvertisementDetail($params, $userID);
                break;

            case 'validateAdvertisementOrder':
                $apiType = "verify";
                $outputArray = P2P::validateAdvertisementOrder($params, $userID);
                break;

            case 'addAdvertisementOrder':
                $apiType = "insert";
                $outputArray = P2P::addAdvertisementOrder($params, $userID);
                break;

            case 'getOrderListing':
                $apiType = "get";
                $outputArray = P2P::getOrderListing($params, $userID, $site);
                break;

            case "cancelAdvertisement":
                $apiType = "insert";
                $outputArray = P2P::cancelAdvertisement($params);
                break;

            case "insertUnitPrice":
                $apiType = "insert";
                $outputArray = Admin::addUnitPrice($msgpackData['params']);
                break;

            case "buyStockConfirmation": 
                $apiType = "insert";
                $outputArray = Trading::buySellConfirmation($params, "buy");
                break;

            case "sellStockConfirmation": 
                $apiType = "insert";
                $outputArray = Trading::buySellConfirmation($params, "sell");
                break;

            case 'reduceBuySell':
                $apiType = "insert";
                $outputArray = Trading::reduceBuySell($params);
                break;

            case 'cancelBuySell':
                $apiType = "insert";
                $outputArray = Trading::cancelBuySell($params);
                break;

            case 'getTradeDetail':
                $apiType = "get";
                $outputArray = Trading::getTradeDetail($params);
                break;

            case 'getOrderHistory':
                $apiType = "get";
                $outputArray = Trading::getOrderHistory($params);
                break;

            case 'getOpenOrdersListing':
                $apiType = "get";
                $outputArray = Trading::getOpenOrdersListing($params);
                break;

            case 'getTradeHistory':
                $apiType = "get";
                $outputArray = Trading::getTradeHistory($params);
                break;

            case 'getTradingSummary':
                $apiType = "get";
                $outputArray = Trading::getTradingSummary($params);
                break;

            case 'getFundInStatus':
                $apiType = "get";
                $outputArray = CryptoPG::getFundInStatus($params);
                break;

            case 'getCommunityBonusReport':
                $apiType = "get";
                $outputArray = BonusReport::getCommunityBonusReport($params);
                break;

            case 'getDirectSponsorRewardReport':
                $apiType = "get";
                $outputArray = BonusReport::getDirectSponsorRewardReport($params,$userID);
                break;

            case 'getDirectSponsorRewardDetails':
                $apiType = "get";
                $outputArray = BonusReport::getDirectSponsorRewardDetails($params,$userID);
                break;

            case 'getNodeRewardReport':
                $apiType = "get";
                $outputArray = BonusReport::getNodeRewardReport($params,$userID);
                break;

            case 'getNodeRewardDetails':
                $apiType = "get";
                $outputArray = BonusReport::getNodeRewardDetails($params,$userID);
                break;

            case 'getMLMDashboard':
                $apiType = "get";
                $outputArray = Dashboard::getMLMDashboard($params);
                break;

            case 'getCloudMiningReport':
                $apiType = "get";
                $outputArray = BonusReport::getCloudMiningReport($params,$userID);
                break;

            case 'getMiningSponsorBonusReport':
                $apiType = "get";
                $outputArray = BonusReport::getMiningSponsorBonusReport($params,$userID);
                break;

            case 'getMiningSponsorBonusDetails':
                $apiType = "get";
                $outputArray = BonusReport::getMiningSponsorBonusDetails($params,$userID);
                break;

            case 'getMiningWaterBucketReport':
                $apiType = "get";
                $outputArray = BonusReport::getMiningWaterBucketReport($params,$userID);
                break;

            case 'getMiningWaterBucketDetails':
                $apiType = "get";
                $outputArray = BonusReport::getMiningWaterBucketDetails($params,$userID);
                break;
 
            case 'adminBatchAdjustCreditLimit':
                $apiType = "insert";
                $outputArray = Batch::adminBatchAdjustCreditLimit($params,$site);
                break;

            case 'getTradingLimitSummary':
                $apiType = "get";
                $outputArray = Trading::getTradingLimitSummary($params);
                break;

            case 'getMemberTradingLimit':
                $apiType = "get";
                $outputArray = Trading::getMemberTradingLimit($params);
                break;

            case "purchaseCreditVerification":
                $apiType = "verify";
                $outputArray = Wallet::purchaseCreditVerification($params,$userID,$site);
                break;

            case "purchaseCreditConfirmation":
                $apiType = "insert";
                $outputArray = Wallet::purchaseCreditConfirmation($params,$userID,$site);
                break;

            case 'insertProfileDetails':
                $apiType = "insert";
                $outputArray = Subscribe::insertProfileDetails($params,$userID);
                break;

            case 'getFundInData':
                $apiType = "get";
                $outputArray = CryptoPG::getFundInData($params,$userID);
                break;

            case 'getSenderAddressListing':
                $apiType = "get";
                $outputArray = Admin::getSenderAddressListing($params);
                break;

            case 'getDeliveryChargesListing':
                $apiType = "get";
                $outputArray = Inventory::getDeliveryChargesListing($params);
                break;

            case 'getDeliveryCharges':
                $apiType = "get";
                $outputArray = Inventory::getDeliveryCharges($params);
                break;

            case 'updateDeliveryCharges':
                $apiType = "insert";
                $outputArray = Inventory::updateDeliveryCharges($params);
                break;

            case 'getProductInventory':
                $apiType = "get";
                $outputArray = Inventory::getProductInventory($params);
                break;

            case 'getProductInventoryDetails':
                $apiType = "get";
                $outputArray = Inventory::getProductInventoryDetails($params);
                break;

            case "verifyAddProductInventory":
                $apiType = "verify";
                $outputArray = Inventory::verifyProductInventory($params,"add",true);
                break;

            case 'addProductInventory':
                $apiType = "insert";
                $outputArray = Inventory::addProductInventory($params);
                break;

            case 'editProductInventory':
                $apiType = "insert";
                $outputArray = Inventory::editProductInventory($params);
                break;

            case 'getPackageListing':
                $apiType = "get";
                $outputArray = Inventory::getPackageListing($params);
                break;

            case 'getPackageDetail':
                $apiType = "get";
                $outputArray = Inventory::getPackageDetail($params);
                break;

            case 'addPackageDetail':
                $apiType = "insert";
                $outputArray = Inventory::addPackageDetail($params);
                break;

            case 'editPackageDetail':
                $apiType = "insert";
                $outputArray = Inventory::editPackageDetail($params);
                break;

            case 'getStarterPackageListing':
                $apiType = "get";
                $outputArray = Inventory::getPackageListing($params, true);
                break;

            case 'getStarterPackageDetail':
                $apiType = "get";
                $outputArray = Inventory::getPackageDetail($params, true);
                break;

            case 'addStarterPackageDetail':
                $apiType = "insert";
                $outputArray = Inventory::addPackageDetail($params, true);
                break;

            case 'editStarterPackageDetail':
                $apiType = "insert";
                $outputArray = Inventory::editPackageDetail($params, true);
                break;

            case 'getBuyProductList';
                $apiType = "get";
                $outputArray = Inventory::getBuyProductList($params);
                break;    

            case 'getDashboardProductList';
                $apiType = "get";
                $outputArray = Inventory::getBuyProductList($params, true);
                break;    

            case 'getBuyProductDetails';
                $apiType = "get";
                $outputArray = Inventory::getBuyProductDetails($params);
                break; 
                
            case 'manualJoinGame':
                $apiType = "insert";
                $outputArray = Game::manualJoinGame($params);
                break;

            case 'getGameParticipantData':
                $apiType = "get";
                $outputArray = Game::getGameJoinerData($params);
                break;

            case 'getGameRoomListing':
                $apiType = "get";
                $outputArray = Game::getGameRoomListing($params);
                break;

            case 'getGameRoomDetail':
                $apiType = "get";
                $outputArray = Game::getGameRoomDetail($params);
                break;

            case 'getBonanzaBonusReport':
                $apiType = "get";
                $outputArray = BonusReport::getBonanzaBonusReport($params);
                break;

            case 'getTreeIntroducer':
                $apiType = "get";
                $outputArray = Tree::getTreeIntroducer($params);
                break;

            case 'changeIntroducer':
                $apiType = "insert";
                $outputArray = Tree::changeIntroducer($params);
                break;

            case 'getIntroducer':
                $apiType = "get";
                $outputArray = Tree::getIntroducer($params);
                break;

            case "getCategoryInventoryMember":
                $apiType = "get";
                $outputArray = Client::getCategoryInventoryMember($params);
                break;
            
            case "getProductListMember":
                $apiType = "get";
                $outputArray = Client::getProductListMember($params);
                break;

            case "getCategoryInventory":
                $apiType = "get";
                $outputArray = Inventory::getCategoryInventory($params);
                break;

            case "getCategoryInventoryDetail":
                $apiType = "get";
                $outputArray = Inventory::getCategoryInventoryDetail($params);
                break;
            
            case "getNewRecuitAndActiveProgram":
                $apiType = "post";
                $outputArray = Bonus::getNewRecuitAndActiveProgram($params);
                break;


            case "addCategoryInventory":
                $apiType = "insert";
                $outputArray = Inventory::addCategoryInventory($params);
                break;

            case "editCategoryInventory":
                $apiType = "insert";
                $outputArray = Inventory::editCategoryInventory($params);
                break;

            case "getBannerList":
                $apiType = "get";
                $outputArray = Bulletin::getBannerList($msgpackData['params']);
                break;

            case "getBanner":
                $apiType = "get";
                $outputArray = Bulletin::getBanner($msgpackData['params']);
                break;

            case "addBanner":
                $apiType = "insert";
                $outputArray = Bulletin::addBanner($msgpackData['params'], $site);
                break;

            case "editBanner":
                $apiType = "insert";
                $outputArray = Bulletin::editBanner($msgpackData['params'], $site);
                break;

            case "removeBanner":
                $apiType = "insert";
                $outputArray = Bulletin::removeBanner($msgpackData['params']);
                break;

            case "getDashboardBanner":
                $apiType = "get";
                $outputArray = Bulletin::getDashboardBanner();
                break;

            case "getJackpotBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getJackpotBonusReport($params);
                break;

            case "getKSponsorBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getKSponsorBonusReport($params);
                break;

            case "adminSetPool":
                $apiType = "insert";
                $outputArray = Game::setPoolSetting($params);
                break;

            case "getPoolDetail":
                $apiType = "get";
                $outputArray = Game::getPoolData();
                break;

            case "setRoomPrioritize":
                $apiType = "insert";
                $outputArray = Game::setPrioritizeSwitch($params);
                break;

            case "getDepositDataAdmin":
                $apiType = "get";
                $outputArray = Custom::getDepositData($params);
                break;

            case "depositAdjustmentAdmin":
                $apiType = "insert";
                $outputArray = Wallet::creditAdjustment($msgpackData['params'],"deposit");
                break;

            case 'getAdminNotification':
                $apiType = "get";
                $outputArray = General::getAdminNotification($params);
                break;

            case 'setPrivateGameStg':
                $apiType = "insert";
                $outputArray = Admin::addPrivateGameStg($params);
                break;

            case 'getPrivateGameList':
                $apiType = "get";
                $outputArray = Admin::getPrivateGameList($params);
                break;

            case 'getPrivateGameDetail':
                $apiType = "get";
                $outputArray = Admin::getPrivateGameDetail($params);
                break;

            case 'editPrivateGameStg':
                $apiType = "insert";
                $outputArray = Admin::editPrivateGameStg($params);
                break;

            case 'addEVoucher':
                $apiType = "insert";
                $outputArray = Admin::addEVoucher($params);
                break;

            case 'getVoucherList':
                $apiType = "get";
                $outputArray = Admin::getVoucherList($params,true);
                break;

            case 'getVoucherReport':
                $apiType = "get";
                $outputArray = Admin::getVoucherList($params);
                break;

            case 'getVoucherDetail':
                $apiType = "get";
                $outputArray = Admin::getVoucherDetail($params);
                break;

            case 'editEVoucher':
                $apiType = "insert";
                $outputArray = Admin::editEVoucher($params);
                break;

            case 'setTaxes';
                $apiType = "insert";
                $outputArray = Inventory::setTaxes($params);
                break;

            case 'getTaxes';
                $apiType = "get";
                $outputArray = Inventory::getTaxes($params);
                break;

            case 'addAddress';
                $apiType = "insert";
                $outputArray = Inventory::manageAddress($params,"add");
                break;

            case 'editAddress';
                $apiType = "insert";
                $outputArray = Inventory::manageAddress($params,"edit");
                break;

            case 'deleteAddress';
                $apiType = "insert";
                $outputArray = Inventory::manageAddress($params,"delete");
                break;

            case 'getAddress';
                $apiType = "get";
                $outputArray = Inventory::getAddress($params);
                break;

            case 'getAddressList';
                $apiType = "get";
                $outputArray = Inventory::getAddressList($params);
                break; 

            case 'getPVPListing';
                $apiType = "get";
                $outputArray = Inventory::getPVPListing($params);
                break; 

            case 'getPVTGameJoinerData':
                $apiType = "get";
                $outputArray = Game::getPVTGameJoinerData($params);
                break;

            case "getPurchaseReport":
                $apiType = "get";
                $outputArray = Custom::getPurchaseReport($params);
                break;

            case "adminBatchUnlock":
                $apiType = "insert";
                $outputArray = Batch::adminBatchUnlock($params, $site);
                break;

            case "validateKYCOTP":
                $apiType = "verify";
                $outputArray = OTP::validateKYCOTP($params);
                break;

            case "getKYCDetailsNew": 
                $apiType = "get";
                $outputArray = Client::getKYCDetailsNew($params);
                break;

            case "addKYCValidation":
                $apiType = "verify";
                $outputArray = Client::addKYCValidation($params);
                break;

            case "addKYCConfirmation":
                $apiType = "insert";
                $outputArray = Client::addKYCConfirmation($params);
                break;

            case "adjustInvProduct":
                $apiType = "insert";
                $outputArray = Inventory::adjustInvProduct($params);
                break;

            case "adjustInvStock":
                $apiType = "insert";
                $outputArray = Inventory::adjustInvStock($params);
                break;

            case "getStockDetails":
                $apiType = "get";
                $outputArray = Inventory::getStockDetails($params);
                break;

            case "getStockTransactionHistory":
                $apiType = "get";
                $outputArray = Inventory::getStockTransactionHistory($params);
                break;

            case "getProductTransactionHistory":
                $apiType = "get";
                $outputArray = Inventory::getProductTransactionHistory($params);
                break;

            case "getProductIDForSearch":
                $apiType = "get";
                $outputArray = Inventory::getProductIDForSearch($params);
                break;

            case "packageAdjustment":
                $apiType = "insert";
                $outputArray = Inventory::packageAdjustment($params);
                break;

            case "getPackageAdjustment":
                $apiType = "get";
                $outputArray = Inventory::getPackageAdjustment($params);
                break;
                
            case "purchasePackageVerification":
                $apiType = "verify";
                $outputArray = Inventory::purchasePackageVerification($params);
                break;

            case "purchasePackageConfirmation":
                $apiType = "insert";
                $outputArray = Inventory::purchasePackageConfirmation($params);
                break;
            
            case "getActiveSupplier":
                $apiType = "get";
                $outputArray = Inventory::getSupplierListing($params,true);
                break;

            case "getSupplierListing":
                $apiType = "get";
                $outputArray = Inventory::getSupplierListing($params);
                break;
            
            case "getSupplierDetail":
                $apiType = "get";
                $outputArray = Inventory::getSupplierDetail($params);
                break;

            case "addSupplier":
                $apiType = "insert";
                $outputArray = Inventory::addSupplier($params);
                break;

            case "editSupplier":
                $apiType = "insert";
                $outputArray = Inventory::editSupplier($params);
                break;

            case "getInvoiceListing":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceListing($params,"invoice");
                break;

            case "getPOListing":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceListing($params,"po");
                break;

            case "getMemberOrderListing":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceListing($params,"po");
                break;

            case "getAdminOrderListing":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceListing($params,"apo");
                break;
                
            case "issueDO":
                $apiType = "insert";
                $outputArray = Inventory::issueDO($params);
                break;

            case "cancelDO":
                $apiType = 'insert';
                $outputArray = Inventory::cancelDO($params);
                break;

            case "updateDeliveryOrder":
                $apiType = "insert";
                $outputArray = Inventory::updateDeliveryOrder($params);
                break;

            case "getInvoiceDetail":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceDetail($params);
                break;

            case "getPODetail":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceDetail($params);
                break;

            case "getIssueDOPage":
                $apiType = "get";
                $outputArray = Inventory::getInvoiceDetail($params);
                break;
                
            case "getBonusAmountListing":
                $apiType = "get";
                $outputArray = BonusReport::getBonusAmountListing($params);
                break;

            case "getBonusPayoutDetailListing":
                $apiType = "get";
                $outputArray = BonusReport::getBonusPayoutDetailListing($params);
                break;

            case "getCashAward":
                $apiType = "get";
                $outputArray = Dashboard::getCashAward($params);
                break;
                
            case "getStarAward":
                $apiType = "get";
                $outputArray = Dashboard::getStarAward($params);
                break;

            case "updatePayoutStatus":
                $apiType = "insert";
                $outputArray = BonusReport::updatePayoutStatus($params);
                break;

            case "updatePayoutDetailsStatus":
                $apiType = "insert";
                $outputArray = BonusReport::updatePayoutStatus($params, true);
                break;

            case 'getDeliveryOrderListing':
                $apiType = "get";
                $outputArray = Inventory::getDeliveryOrderListing($params);
                break;
                
            case 'getDeliveryOrderDetail':
                $apiType = "get";
                $outputArray = Inventory::getDeliveryOrderDetail($params);
                break;

            case "getPGPMonthlySalesSummary":
                $apiType = "get";
                $outputArray = BonusReport::getPGPMonthlySalesSummary($params);
                break;

            case "getDVPMonthlySalesSummary":
                $apiType = "get";
                $outputArray = BonusReport::getDVPMonthlySalesSummary($params);
                break;
                
            case 'addShoppingCart':
                $apiType = "insert";
                $outputArray = Inventory::addShoppingCart($params);
                break;

            case 'updateShoppingCart':
                $apiType = "insert";
                $outputArray = Inventory::updateShoppingCart($params);
                break;

            case 'removeShoppingCart':
                $apiType = "insert";
                $outputArray = Inventory::removeShoppingCart($params);
                break;

            case 'getShoppingCart':
                $apiType = "get";
                $outputArray = Inventory::getShoppingCart($params);
                break;

            case "getLowStockQuantity":
                $apiType = "get";
                $outputArray = Inventory::getLowStockQuantity($params);
                break;

            case "setLowStockQuantity":
                $apiType = "insert";
                $outputArray = Inventory::setLowStockQuantity($params);
                break;

            case "getProductStockDetail":
                $apiType = "get";
                $outputArray = Dashboard::getProductStockDetail($params);
                break;
            case "accountSignUpVerification":
                $apiType = "verify";
                $outputArray = Client::accountOwnerVerification($params);
                break;
            case "accountOwnerVerification":
                $apiType = "verify";
                $outputArray = Client::accountOwnerVerification($params);
                break;
            case "getState":
                $apiType = "verify";
                $outputArray = Client::getState($params);
                break;
            case "guestOwnerVerification":
                $apiType = "verify";
                $outputArray = Client::guestOwnerVerification($params);
                break;
            case "getPurchaseHistory":
                $apiType = "get";
                $outputArray = Client::clientPurchaseHistory($params);
                break;
            case "memberResetPasswordVerification":
                $apiType = "verify";
                $outputArray = Client::accountOwnerVerification($params, "resetPassword");
                break;
                
            case "getLowInStockListing":
                $apiType = "get";
                $outputArray = Inventory::productAlertListing($params);
                break;

            case "getOutOfStockListing":
                $apiType = "get";
                $outputArray = Inventory::productAlertListing($params, true);
                break;

            case "adminGetRecruitPromoReport":
                $apiType = "get";
                $outputArray = Custom::getRecruitPromoReport($params);
                break;

            case "getRecruitPromoDetails":
                $apiType = "get";
                $outputArray = Custom::getRecruitPromoDetails($params);
                break;

            case "memberGetRecruitPromoReport":
                $apiType = "get";
                $outputArray = Custom::getRecruitPromoReport($params);
                break;

            case 'getDiscountVoucherSetting';
                $apiType = "get";
                $outputArray = Inventory::getDiscountVoucherSetting($params);
                break; 

            case 'getCurrentDiscountVoucherSetting';
                $apiType = "get";
                $outputArray = Inventory::getCurrentDiscountVoucherSetting($params);
                break;

            case 'addDiscountVoucherSetting';
                $apiType = "insert";
                $outputArray = Inventory::addDiscountVoucherSetting($params, "add");
                break;

            case 'editDiscountVoucherSetting';
                $apiType = "insert";
                $outputArray = Inventory::editDiscountVoucherSetting($params, "edit");
                break;

            // small function
            case "adminGetMemberName":
            case "memberGetMemberName":
                $apiType = "get";
                $outputArray = Admin::getMemberName($params);
                break;

            case "checkValidVoucher":
                $apiType = "verify";
                $outputArray = Inventory::checkValidVoucher($params);
                break;

            case "getDiscountVoucherRedemptionListing":
                $apiType = "get";
                $outputArray = Inventory::getDiscountVoucherRedemptionListing($params);
                break;

            case "getBonusPayoutSummaryMonetary":
                $apiType = "get";
                $outputArray = BonusReport::getBonusPayoutSummaryMonetary($params);
                break;

            case "callNicepayPaymentGateway":
                $apiType = "callback";
                $outputArray = Custom::callNicepayPaymentGateway($params, $ip, $sessionID);
                break;                

            case "nicepayCallback":
                $apiType = "callback";
                $outputArray = Custom::nicepayCallback($params);
                break;

            case "getPaymentGatewayRequestListing":
                $apiType = "get";
                $outputArray = Custom::getPaymentGatewayRequestListing($params);
                break;

            case "getMonthlyPerformanceRpt":
                $apiType = "get";
                $outputArray = Report::getMonthlyPerformanceRpt($params);
                break;

            case "getMonthlyPerformanceDetail":
                $apiType = "get";
                $outputArray = Report::getMonthlyPerformanceDetail($params);
                break;

            case "getDownlinePerformanceReport":
                $apiType = "get";
                $outputArray = Report::getDownlinePerformanceReport($params);
                break;

            case "getPendingPaymentStatus":
                $apiType = "get";
                $outputArray = Custom::getPendingPaymentStatus($params);
                break;

            case "addTaxPercentage":
                $apiType = "insert";
                $outputArray = Admin::setTaxPercentage($params);
                break;

            case "getTaxPercentage":
                $apiType = "get";
                $outputArray = Admin::getTaxPercentage($params);
                break;
                
            case "getEnrollmentBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getEnrollmentBonusReport($params);
                break;

            case "getUnilevelBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getUnilevelBonusReport($params);
                break;

            case "getCoupleBonusReport":
                $apiType = "get";
                $outputArray = BonusReport::getCoupleBonusReport($params);
                break;

            case 'checkStarterpackEmailAttachment':
                $apiType = "get";
                $outputArray = Inventory::checkStarterpackEmailAttachment($params);
                    // $outputArray = $params;
                break;

            case 'updateStarterpackEmailAttachment':
                $apiType = "update";
                $outputArray = Inventory::updateStarterpackEmailAttachment($params);
                    // $outputArray = $params;
                break;

            case 'addStarterpackEmailAttachment':
                $apiType = "update";
                $outputArray = Bulletin::addStarterpackEmailAttachment($params);
                    // $outputArray = $params;
                break;

            case 'instantMemberExcelExport':
                $apiType = "insert";
                $outputArray = Excel::instantMemberExcelExport($params, $config);
                break;

            case "getPurchaseRequestDetails":
                $apiType = "get";
                $outputArray = Admin::getPurchaseRequestDetails($msgpackData['params']);
                break;

            case "getShopOwnerList":
                $apiType = "get";
                $outputArray = Admin::getShopOwnerList($msgpackData['params']);
                break;

            case "getShopOwnerDetail":
                $apiType = "get";
                $outputArray = Admin::getShopOwnerDetail($msgpackData['params']);
                break;

            case "addShopOwner":
                $apiType = "insert";
                $outputArray = Admin::addShopOwner($msgpackData['params']);
                break;

            case "editShopOwner":
                $apiType = "insert";
                $outputArray = Admin::editShopOwner($msgpackData['params']);
                break;

            case "getShopList":
                $apiType = "get";
                $outputArray = Admin::getShopList($msgpackData['params']);
                break;

            case "getShopDetail":
                $apiType = "get";
                $outputArray = Admin::getShopDetail($msgpackData['params']);
                break;

            case "addShop":
                $apiType = "insert";
                $outputArray = Admin::addShop($msgpackData['params']);
                break;

            case "editShop":
                $apiType = "insert";
                $outputArray = Admin::editShop($msgpackData['params']);
                break;

            case "purchaseRequestApprove":
                $apiType = "get";
                $outputArray = Admin::purchaseRequestApprove($msgpackData['params'], $msgpackData['username']);
                break;

            case "getProductInventoryList":
                $apiType = "get";
                $outputArray = Inventory::getProductInventoryList($params);
                break;

            case "getProductDetails":
                $apiType = "get";
                $outputArray = Inventory::getProductDetails($params);
                break;

            case 'getBankDetails':
                $apiType = "get";
                $outputArray = Cash::getBankDetails($params,$userID);
                break;

            case 'addNewPayment':
                $apiType = "insert";
                $outputArray = Cash::addNewPayment($params,$userID);
                break;

            case 'CheckOutCalculation':
                $apiType = "get";
                $outputArray = Cash::CheckOutCalculation($params,$userID);
                break;

            case 'getDeliveryMethod':
                $apiType = "get";
                $outputArray = Cash::getDeliveryMethod($params);
                break;

            case "addAttribute":
                $apiType = "insert";
                $outputArray = Inventory::addAttribute($params);
                break;

            case "editAttribute":
                $apiType = "insert";
                $outputArray = Inventory::editAttribute($params);
                break;

            case "getAttributeList":
                $apiType = "get";
                $outputArray = Inventory::getAttributeList($params);
                break;

            case "getAttributeDetail":
                $apiType = "get";
                $outputArray = Inventory::getAttributeDetail($params);
                break;

            case "generateProductSKU":
                $apiType = "get";
                $outputArray = Inventory::generateProductSKU($params);
                break;

            case "getPackageProductList":
                $apiType = "get";
                $outputArray = Inventory::getPackageProductList($params);
                break;

            case "getShopDeviceList":
                $apiType = "get";
                $outputArray = Admin::getShopDeviceList($msgpackData['params']);
                break;

            case "addShopDevice":
                $apiType = "insert";
                $outputArray = Admin::addShopDevice($msgpackData['params']);
                break;

            case "getShopDeviceDetail":
                $apiType = "get";
                $outputArray = Admin::getShopDeviceDetail($msgpackData['params']);
                break;

            case "editShopDevice":
                $apiType = "insert";
                $outputArray = Admin::editShopDevice($msgpackData['params']);
                break;

            case "getShopWorkerList":
                $apiType = "get";
                $outputArray = Admin::getShopWorkerList($msgpackData['params']);
                break;
            
            case "getProviderSettingFPX":
                $apiType = "get";
                $outputArray = Cash::getProviderSettingFPX();
                break;

            case "updateSaleOrder":
                $apiType = "update";
                $outputArray = Cash::updateSaleOrder($params);
                break;
            
            case "getPaymentDeliveryOptions":
                $apiType = "get";
                $outputArray = Cash::getPaymentDeliveryOptions();
                break;

            case "uploadReceipt":
                $apiType = "get";
                $outputArray = Cash::uploadReceipt($params);
                break;

            case "getReceipt":
                $apiType = "get";
                $outputArray = Cash::getReceipt($params);
                break;

            case "FPXBackendVerify":
                $apiType = "get";
                $outputArray = Cash::FPXBackendVerify($params);
                break;

            case "getPurchaseOrderList":
                $apiType = "get";
                $outputArray = Admin::getPurchaseOrderList($msgpackData['params']);
                break;
    
            case "getPurchaseOrderDetails":
                $apiType = "get";
                $outputArray = Admin::getPurchaseOrderDetails($msgpackData['params']);
                break;

            case "purchaseOrderEdit":
                $apiType = "insert";
                $outputArray = Admin::purchaseOrderEdit($msgpackData['params']);
                break;

            case "assignSerial":
                $apiType = "insert";
                $outputArray = Admin::assignSerial($msgpackData['params']);
                break;

            case "confirmSerial":
                $apiType = "insert";
                $outputArray = Admin::confirmSerial($msgpackData['params']);
                break;

            case "getVendor":
                $apiType = "get";
                $outputArray = Admin::getVendor($msgpackData['params']);
                break;

            case "getWarehouse":
                $apiType = "get";
                $outputArray = Admin::getWarehouse($msgpackData['params']);
                break;

            case "approvePurchaseOrder":
                $apiType = "insert";
                $outputArray = Admin::approvePurchaseOrder($msgpackData['params'], $msgpackData['username']);
                break;

            case "getStockList":
                $apiType = "get";
                $outputArray = Admin::getStockList($msgpackData['params']);
                break;

            case "getSaleDetail":
                $apiType = "get";
                $outputArray = Inventory::getSODetail($params);
                break;

            case "getProduct":
                $apiType = "get";
                $outputArray = Admin::getProduct($msgpackData['params']);
                break;

            case "editOrderDetails":
                $apiType = "update";
                $outputArray = Inventory::editOrderDetails($params);
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

    echo msgpack::msgpack_pack($outputArray);

?>
