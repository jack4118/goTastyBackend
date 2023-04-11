<?php

    class Client {
        
        function __construct() {
            
        }

        function updateClientData($params,$tableName,$updatedColumn){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $updatedValue = trim($params["updatedValue"]);

            if(!$clientID){
                $clientID = trim($params["clientID"]);
            }

            if(!$clientID){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
            }

            if(!$updatedValue){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
            }

            switch ($updatedColumn) {
                case 'freezed':
                    if($updatedValue > 1){
                        return array("status" => "error", "code" => 2, "statusMsg" => "Invalid Value.", "data" => "");
                    }else{
                        //authorized - 1 
                        $updatedData[$updatedColumn] = $updatedValue == 1 ? 0 : 1;
                    }

                    break;
                
                default:
                    return array("status" => "error", "code" => 2, "statusMsg" => "Invalid column", "data" => "");
                    break;
            }

            switch ($tableName) {
                case 'client':
                    $db->where("id",$clientID);
                    break;
                
                default:
                    return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
                    break;
            }
            
            $copyDb = $db->copy();
            $dataRow = $db->getOne($tableName, "id, ".$updatedColumn);
            if(empty($dataRow)){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
            }

            $copyDb->update($tableName,$updatedData);

            return array("status" => "ok", "code" => 0, "statusMsg" => "Updated successfully.", "data" => "");
        }

        public function sendTelegramNotification($content){
            global $config;
            $db = MysqliDb::getInstance();

            // retrieve bot api
            $db->where('company', $config['companyName']);
            $db->where('name','telegram');
            $db->where('type', 'notification');
            $URL1 = $db->getOne('provider',null,'url1');

            $URL = $URL1['url1'] . '/sendMessage';

            $chat_id = $config['telegramGroup'];
            
            $data = [
                'chat_id'   => $chat_id,
                'text'      => $content,
            ];
            // error_log(print_r($content, true));
            $URL .= "?".http_build_query($data)."&parse_mode=Markdown";
    
            // ##### GET METHOD #####
            $curl=curl_init($URL);
    
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_VERBOSE, 0);  // for debug
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,120);
            curl_setopt($curl, CURLOPT_TIMEOUT, 120); //timeout in seconds
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, 0);
    
            $response = curl_exec($curl);
    
            /* get http status code*/
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
            if(curl_errno($curl)){
                return array('code' => 1, 'status' => "error", 'statusMsg' => '', 'http_code' => $httpCode, 'curl_error_no' => curl_errno($curl), 'curl_error' => curl_error($curl));
            }
    
            curl_close($curl);
    
            // return $response;
        }

        function getCreditDisplay(){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $creditList=$db->get('credit',null,'name,translation_code');
            foreach ($creditList as $key => $value) {
                $creditListReturn[$value['name']]=$translations[$value['translation_code']][$language];
            }
            return $creditListReturn;
        }

        function create_passlib_pbkdf2($algo, $password, $salt, $iterations){
          $hash = hash_pbkdf2($algo, $password, base64_decode(str_replace(".", "+", $salt)), $iterations, 64, true);
          return sprintf("\$pbkdf2-%s\$%d\$%s\$%s", $algo, $iterations, $salt, str_replace("+", ".", rtrim(base64_encode($hash), '=')));
        }



        function verify_passlib_pbkdf2($password, $passlib_hash){
            if (empty($password) || empty($passlib_hash)) return false;

            $parts = explode('$', $passlib_hash);
            if (!array_key_exists(4, $parts)) return false;
            $t = explode('-', $parts[1]);
            if (!array_key_exists(1, $t)) return false;

            $algo = $t[1];
            $iterations = (int) $parts[2];
            $salt = $parts[3];
            $orghash = $parts[4];

            $hash = Self::create_passlib_pbkdf2($algo, $password, $salt, $iterations);
            return $passlib_hash === $hash;
        }

        
        public function getPortfolioDetail($portfolioId) {
            $db = MysqliDb::getInstance();

            $db->where("id", $portfolioId);
            $portfolioDetail = $db->getOne("mlm_client_portfolio", "product_id, product_price, bonus_value, portfolio_type, belong_id, batch_id");

            return $portfolioDetail;
        }

        public function memberLogin($msgpackData) {
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $passwordEncryption = Setting::getMemberPasswordEncryption();
            $autoLoginExpiryDay    = Setting::$systemSetting["autoLoginExpiryDay"];
            $defTimeOut = Setting::$systemSetting["memberTimeout"];

            $params = $msgpackData['params'];
            $ip = $msgpackData['ip'];

            $id = trim($params['id']);
            $username = trim($params['username']);
            $password = trim($params['password']);
            $loginFromID = trim($params['login_user_id']);
            // $params['loginBy'] = 'email';
            $isAutoLogin = trim($params['isAutoLogin']);
            $marcaje = trim($params['marcaje']);
            $marcajeTK = $params['marcajeTK'];
            $dateTime = date('Y-m-d H:i:s');

            if($marcaje && $marcajeTK){
                $checkRes = User::checkAutoLogin($marcaje,$marcajeTK,$id,$dateTime);
                if($checkRes){
                    if(!$id){
                        $isAutoLogin = 1;
                    }
                    $id = $checkRes['id'];
                    $username = $checkRes['username'];
                    $timeOut  = $checkRes['timeOut'];
                }else{
                    return array('status' => 'error', 'code' => 5, 'statusMsg' => "", 'data' => "");
                }
            }

            if(empty($username))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => "");
            
            switch ($params['loginBy']) {
                case 'phone':
                    $db->where('concat(dial_code, phone)', str_replace('+', '', $username));
                    $fieldName = "Phone Number ";
                    break;

                case 'username':
                    // $db->where("main_id","0");
                    $db->where('username', $username);
                    $fieldName = "Username ";
                    break;

                case 'mainAcc':
                    $db->where('username', $username);
                    $db->where("main_id", "0", "!=");

                    $copyDb = $db->copy();
                    $mainID = $copyDb->getValue('client', 'main_id');
                    if($mainID != $loginFromID){
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => "");
                    }
                    $fieldName = "Username ";
                    $loginFromMainAcc = 1;
                    break;

                case 'backAcc':
                    $copyDb = $db->copy();

                    $db->where('username', $username);
                    $db->where("main_id", "0");

                    $copyDb->where("main_id", $id);                    
                    $subIDAry = $copyDb->getValue('client', 'id', null);
                    if(!in_array($loginFromID, $subIDAry)){
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => "");
                    }
                    $fieldName = "Username ";
                    break;

                case 'email':
                    $db->where('email', $username);
                    $db->orwhere('username', $username);
                    $fieldName = "Email ";
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01178"][$language] /* Invalid Login Type. */,'data' => "");
                    break;
            }

            //for admin login from admin site to member site
            if (!empty($id)) {
                $db->where("id", $id);
                $loginFromAdmin = 1;
            }
            // $db->where('register_method',$params['loginBy']);
            $result = $db->get('client');
            //return array("status" => "error", "code" => 1, "statusMsg" => "", "data" => $db->getLastQuery());

            if(empty($result)){
                $returnData['field'][] = array('id' => 'usernameError', 'msg' => $translations["E01093"][$language]);
                return array("status" => "error", "code" => 1, "statusMsg" => $translations["E01093"][$language], "data" => $returnData);
            }

            if (!$result[0]['username']) {
                $data['incompleteProfile'] = 1;
            }
            
            $clientId = $result[0]["id"];

            //if doesn't have id means it is not login from admin site

            // return array("status" => "error", "code" => 21, "statusMsg" => "testing", "data" => Self::verify_passlib_pbkdf2($password,$result[0]['password']));
            if (!$loginFromAdmin) {
                // this is verification method using pbkdf2_sha512

                //return array("status" => "error", "code" => 21, "statusMsg" => $passwordEncryption, "data" => Self::verify_passlib_pbkdf2($password,$result[0]['password']));
                if ($result[0]['encryption_method'] == "pbkdf2_sha512"){

                    // We need to verify hash password by using this function
                    if (!Self::verify_passlib_pbkdf2($password,$result[0]['password'])){


                        // return array("status" => "error", "code" => 21, "statusMsg" => $errMsg, "data" => Client::verify_passlib_pbkdf2($result[0]['password'] , $password));

                        // $db->where('name', 'memberFailLogin');
                        // $failLimit = $db->getValue('system_settings', 'value');

                        // $failTime = $result[0]['fail_login'] + 1;
                        // $updateData["fail_login"] = $failTime;
                        // $remainTime = $failLimit - $failTime;

                        // if($failTime >= $failLimit || $remainTime == 0) {
                        //     if($result[0]['terminated'] != 1){
                        //         $updateData["suspended"] = 1;
                        //         $errMsg = $translations["E00471"][$language];
                        //     }else{
                        //         $errMsg = $translations["E00473"][$language];
                        //     }
                        // }else{
                        //      // $errMsg = $translations["E01093"][$language];
                        //     $errMsg = $translations["E00818"][$language];
                        //     $errMsg = str_replace("%%count%%", $remainTime, $errMsg);
                        // }
                        
                        // $db->where("id", $clientId);
                        // $db->update('client', $updateData);

                        $errMsg = $translations['E00468'][$language];
                        $returnData['field'][] = array('id' => 'passwordError', 'msg' => $errMsg);
                        return array("status" => "error", "code" => 1, "statusMsg" => $errMsg, "data" => $returnData);
                    }

                }else {
              //  if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if (!password_verify($password, $result[0]['password'])){
                        // $db->where('name', 'memberFailLogin');
                        // $failLimit = $db->getValue('system_settings', 'value');

                        // $failTime = $result[0]['fail_login'] + 1;
                        // $updateData["fail_login"] = $failTime;
                        // $remainTime = $failLimit - $failTime;

                        // if($failTime >= $failLimit || $remainTime == 0) {
                        //     if($result[0]['terminated'] != 1){
                        //         $updateData["suspended"] = 1;
                        //         $errMsg = $translations["E00471"][$language];
                        //     }else{
                        //         $errMsg = $translations["E00473"][$language];
                        //     }
                        // }else{
                        //      // $errMsg = $translations["E01093"][$language];
                        //     $errMsg = $translations["E00818"][$language];
                        //     $errMsg = str_replace("%%count%%", $remainTime, $errMsg);
                        // }
                        
                        // $db->where("id", $clientId);
                        // $db->update('client', $updateData);

                        $errMsg = $translations['E00468'][$language];
                        $returnData['field'][] = array('id' => 'passwordError', 'msg' => $errMsg);
                        return array("status" => "error", "code" => 1, "statusMsg" => $errMsg, "data" => $returnData);
                    }
                }
            }

            //IF Member is registered under this country, prevent login
            $db->where('registered_block_login','1');
            $disabledLoginCountries=$db->map('id')->arrayBuilder()->get('country',null,'id');

            if (in_array($result[0]['country_id'], $disabledLoginCountries)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00754"][$language] /* Invalid Login */, 'data' => '');
            }


            $id = $result[0]['id'];
            $turnOffPopUpMemo = $result[0]['turnOffPopUpMemo'];
            if($result[0]['disabled'] == 1) {
                // Return error if account is disabled
                $statusErrMsg = $translations["E00754"][$language]; /*Your account is disabled.*/
            }

            if($result[0]['activated'] == 0) {
                // Return error if account is not activated
                $data["resendVerifiedEmail"] = 1;
                $statusErrMsg = $translations["E00783"][$language]; /*Your email is not verified!<br/>  Please verify your email address.*/
            }

            if($result[0]['suspended'] == 1) {
                // Return error if account is suspended
                $statusErrMsg = $translations["E00471"][$language]; /*Your account is suspended.*/
            }

            if($result[0]['freezed'] == 1) {
                // Return error if account is freezed
                $statusErrMsg = $translations["E00472"][$language]; /*Your account is freezed.*/
            }

            if($result[0]['terminated'] == 1) {
               // Return error if account is terminated
                $statusErrMsg = $translations["E00473"][$language]; /*Your account is terminated.*/
            }

            if($statusErrMsg){
                if($marcaje && $marcajeTK){
                    return array('status' => 'error', 'code' => 5, 'statusMsg' => "", 'data' => "");
                }else{
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $statusErrMsg, 'data' => $data);
                }
            }

            // Checking if client's countryIP is allowed to login
            $returnData=Self::countryIPBlock($ip,$id);
            if($returnData['status']='error'){
                return $returnData;
            }

            $sessionID = md5($result[0]['username'] . time());
            
            $fields = array('session_id', 'last_login', 'updated_at', 'last_login_ip', 'main_login');
            $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $ip, $loginFromMainAcc);
            $db->where('id', $id);
            $db->update('client', array_combine($fields, $values));

            //Insert Session ID
            $sessionData = User::insertSessionData($clientId,$sessionID,$dateTime,$timeOut,$isAutoLogin);

            //get client blocked rights
            $column = array(
                "(SELECT name FROM mlm_client_rights WHERE id = mlm_client_blocked_rights.rights_id) AS blocked_rights"
            );
            $db->where('client_id', $id);
            $result2 = $db->get("mlm_client_blocked_rights", NULL, $column);

            $blockedRights = array();
            foreach ($result2 as $row){
                $blockedRights[] = $row['blocked_rights'];
            }
            $db->where('id', $id);
            $clientDetail = $db->get('client', null, 'member_id, type, concat(dial_code, phone) as phone');
            foreach ($clientDetail as $row) {
                $member_id = $row['member_id'];
                $type = $row['type'];
                $phone = $row['phone'];
            }
            $content = '*Login Message* '."\n\n".'Member ID: '.$member_id."\n".'Type: '.$type."\n".'Phone Number: '.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            Client::sendTelegramNotification($content);
            $memo = Bulletin::getPopUpMemo($id, $turnOffPopUpMemo);
            
            $member['memo'] = $memo;
            $member['timeOutFlag'] = Setting::getMemberTimeout();
            $member['userID'] = $id;
            $member['memberID'] = $result[0]['member_id'];
            $member['name'] = $result[0]['name'];
            $member['username'] = $result[0]['username'];
            $member['userEmail'] = $result[0]['email'];
            $member['userRoleID'] = $result[0]['role_id'];
            $member["countryID"] = $result[0]["country_id"];
            $member['sessionID'] = $sessionID;
            $member['pagingCount'] = Setting::getMemberPageLimit();
            $member['decimalPlaces'] = Setting::getInternalDecimalFormat();
            $member['blockedRights'] = $blockedRights;

            $data['userDetails'] = $member;

            //check for authorized and kyc
            //ald authorized = 0;
            $isFreezed = 1;
            $db->where("id",$id);
            $isFreezed = $db->getValue("client","freezed");
            
            $data["isAuthorized"] = $isFreezed == 1 ? 0 : 1;

            //check phone edited
            $data['isEditMobile'] = $result[0]['phone'] ? 1 : 0;

            $kycStatus = "New";
            //client kyc status
            $db->where("client_id",$id);
            $db->orderBy("created_at","DESC");
            $kycRes = $db->get("mlm_kyc",1,"status");
            foreach($kycRes as $kycRow){
                $kycStatus = $kycRow["status"];
            }
            $data["memberKycStatus"] = $kycStatus;

            $db->where("disabled","0");
            $db->orderBy("priority","ASC");
            $bonusReportAry = $db->get("mlm_bonus",null, "name, language_code as languageCode");
            $data["bonusReport"] = $bonusReportAry;

            /* get user's inbox message */
            $inboxSubQuery = $db->subQuery();
            $inboxSubQuery->where("`creator_id`", $id);
            $inboxSubQuery->orWhere("`receiver_id`", $id);
            $inboxSubQuery->get("`mlm_ticket`", null, "`id`");
            $db->where("`ticket_id`", $inboxSubQuery, "IN");
            $db->where("`read`", 0);
            $db->where("`sender_id`", $id, "!=");
            $inboxUnreadMessage = $db->getValue("`mlm_ticket_details`", "COUNT(*)");
            $data['inboxUnreadMessage'] = $inboxUnreadMessage;

            // $db->where('status','Active');
            // $packageArr = $db->get('mlm_product',null,'id,name,translation_code');
            // foreach ($packageArr as &$packageRow) {
            //     $packageRow['display'] = $translations[$packageRow['translation_code']][$language];
            // }
            // $data['packageArr'] = $packageArr;

            $db->orderBy('priority','ASC');
            $rankRes = $db->get('rank',null,'id,type,translation_code');
            foreach ($rankRes as $rankRow) {
                $rankData['id'] = $rankRow['id'];
                $rankData['display'] = $translations[$rankRow['translation_code']][$language];
                $rankList[$rankRow['type']][] = $rankData;
            }
            $data['rankList'] = $rankList;

            $db->where("id", $clientId);
            $db->update('client', array("fail_login" => "0"));

            $data['marcaje']            = $sessionData['marcaje'];
            $data['marcajeTK']          = $sessionData['marcajeTK'];
            $data['expiredTS']          = $sessionData['expiredTS']?($sessionData['expiredTS'] + $defTimeOut):"";

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        
        public function getValidCreditType() {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $creditID = $db->subQuery();
            $creditID->where('name', 'isWallet');
            $creditID->where('value', 1);
            $creditID->getValue('credit_setting', 'credit_id', null);

            $db->where('id', $creditID, 'IN');
            $creditName = $db->getValue("credit", "name", null);

            if(empty($creditName))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            return $creditName;
        }

        public function getViewMemberDetails($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $clientID = $params['clientID'];

            $db->join("country c", "m.country_id=c.id", "LEFT");
            $db->join("client s", "m.sponsor_id=s.id", "LEFT");
            $db->where("m.id", $clientID);
            // $member = $db->getOne("client m", "m.name, m.email, m.phone, m.dial_code, m.address, c.name AS country, m.disabled, m.suspended, m.freezed, s.username as sponsorUsername, s.name as sponsorName, s.dial_code as sponsorDialCode, s.phone AS sponsorPhone");
            $member = $db->getOne("client m", "m.name, m.email, m.phone, m.dial_code, m.address, c.name AS country, m.disabled, m.suspended, m.freezed, m.sponsor_id as sponsorId");
            $db->where("disabled",0);
            $db->where("address_type","billing");
            $db->where("client_id",$clientID);
            $billingRes = $db->getOne("address","name,email,phone,address,state_id,district_id,sub_district_id,post_code,city,country_id,remarks");

            unset($districtIDAry,$subDistrictIDAry,$postCodeIDAry,$cityIDAry,$stateIDAry,$countryIDAry);

            $districtIDAry[$billingRes["district_id"]] = $billingRes["district_id"];
            $subDistrictIDAry[$billingRes["sub_district_id"]] = $billingRes["sub_district_id"];
            $postCodeIDAry[$billingRes["post_code"]] = $billingRes["post_code"];
            $cityIDAry[$billingRes["city"]] = $billingRes["city"];
            $stateIDAry[$billingRes["state_id"]] = $billingRes["state_id"];
            $countryIDAry[$billingRes["country_id"]] = $billingRes["country_id"];

            if($districtIDAry){
                $db->where("id",$districtIDAry,"IN");
                $districtRes = $db->map("id")->get("county",null,"id,name");
            }

            if($subDistrictIDAry){
                $db->where("id",$subDistrictIDAry,"IN");
                $subDistrictRes = $db->map("id")->get("sub_county",null,"id,name");
            }

            if($postCodeIDAry){
                $db->where("id",$postCodeIDAry,"IN");
                $postCodeRes = $db->map("id")->get("zip_code",null,"id,name");
            }

            if($cityIDAry){
                $db->where("id",$cityIDAry,"IN");
                $cityRes = $db->map("id")->get("city",null,"id,name");
            }

            if($stateIDAry){
                $db->where("id",$stateIDAry,"IN");
                $stateRes = $db->map("id")->get("state",null,"id,name");
            }

            if($countryIDAry){
                $db->where("id",$countryIDAry,"IN");
                $countryRes = $db->map("id")->get("country",null,"id,name,translation_code,country_code");
            }

            unset($billingInfo);
            $billingInfo["name"] = $billingRes["name"];
            $billingInfo["email"] = $billingRes["email"];
            // $billingInfo["dialingArea"] = $countryRes[$billingRes["country_id"]]["country_code"];
            $billingInfo['dialingArea'] = $member['dial_code'];
            $billingInfo["phone"] = $billingRes["phone"];
            $billingInfo["address"] = $billingRes["address"];
            $billingInfo["remarks"] = $billingRes["remarks"];
            $billingInfo["country"] = $translations[$countryRes[$billingRes["country_id"]]["translation_code"]][$language] ? $translations[$countryRes[$billingRes["country_id"]]["translation_code"]][$language] : $countryRes[$billingRes["country_id"]]["name"];
            $billingInfo["state"] = $stateRes[$billingRes["state_id"]];
            // $billingInfo["city"] = $cityRes[$billingRes["city"]];
            $billingInfo["city"] = $billingRes["city"];
            $billingInfo["district"] = $districtRes[$billingRes["district_id"]];
            $billingInfo["subDistrict"] = $subDistrictRes[$billingRes["sub_district_id"]];
            // $billingInfo["postalCode"] = $postCodeRes[$billingRes["post_code"]];
            $billingInfo["postalCode"] = $billingRes["post_code"];

            unset($sponsorInfo);
            // get sponsor user detail
            if($member['sponsorId'] != '0')
            {   
                $db->where('concat(dial_code, phone)', $member['sponsorId']);
                $db->where('type', 'Client');
                $sponsorDetail = $db->getOne('client');
            }
            
            if($sponsorDetail)
            {
                $sponsorInfo["sponsorName"] = $sponsorDetail['name'];
                $sponsorInfo["sponsorDialCode"] = $sponsorDetail['dial_code'];
                $sponsorInfo["sponsorPhone"] = $sponsorDetail['phone'];
            }
            
            if(!$sponsorDetail)
            {
                $sponsorInfo["sponsorName"] = '-';
                $sponsorInfo["sponsorDialCode"] = '-';
                $sponsorInfo["sponsorPhone"] = '-';
            }

            $data['member'] = $member;
            $data["billingInfo"] = $billingInfo;
            $data["sponsorInfo"] = $sponsorInfo;
            if(empty($member))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00279"][$language] /* No result found */, 'data' => "");
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // -- Registration Start -- // -- need tune
        public function updateClientRank($clientId, $productId) {
            $db = MysqliDb::getInstance();

            $productAry = Product::getProductList();
            foreach ($productAry['data'] as $productKey => $productDetail) {
                $bonusValueAry[$productKey] = $productDetail['bonusValue'];
            }

            $db->where('client_id', $clientId);
            $db->where('name', "package");
            $result = $db->getOne('client_setting', 'id, name, value, reference');

            if(empty($result)) {
                $insertData = array(
                                        "name" => "package",
                                        "value" => $productAry['data'][$productId]['name'],
                                        "reference" => $productId,
                                        "client_id" => $clientId
                                    );
                $db->insert("client_setting", $insertData);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
            } 

            $previousBonusValue = $result['value'];
            if($bonusValueAry[$productId]['value'] > $bonusValueAry[$result["reference"]]['value']) {
                $updateData = array(
                                        'value' => $productAry[$productId]['name'], 
                                        'reference' => $productAry[$productId]['id']
                                    );
                $db->where('id', $result['id']);
                $db->update('client_setting', $updateData);
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function insertMaxCap($clientId, $productId, $belongId, $batchId, $bonusValue, $portfolioId) {
            $db = MysqliDb::getInstance();

            $db->where("product_id", $productId);
            $db->where("name", "maxCapMultiplier");
            $maxCapMultiplier = $db->getValue("mlm_product_setting", "value");

            $db->where("client_id", $clientId);
            $db->where("name", "maxCapMultiplier");
            $savedMaxCapMultiplier = $db->getValue("client_setting", "value");

            if(empty($savedMaxCapMultiplier)) {
                $insertData = array("name" => "maxCapMultiplier", 
                                    "value" => $maxCapMultiplier, 
                                    "client_id" => $clientId);
                $db->insert("client_setting", $insertData);
                $savedMaxCapMultiplier = $maxCapMultiplier;
            } elseif($maxCapMultiplier > $savedMaxCapMultiplier) {
                $updateData = array("value" => $maxCapMultiplier);
                $db->where("client_id", $clientId);
                $db->where("name", "maxCapMultiplier");
                $db->update("client_setting", $updateData);
                $savedMaxCapMultiplier = $maxCapMultiplier;
            }

            $maxCapRecievable = $bonusValue * ($savedMaxCapMultiplier / 100);

            $db->where("username", "creditSales");
            $internalId = $db->getValue("client", "id");
            Cash::insertTAccount($internalId, $clientId, "maxCap", $maxCapRecievable, "maxCap", $belongId, "", $db->now(), $batchId, $clientId, "", $portfolioId, $savedMaxCapMultiplier);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRegistrationDetails($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $childAgeOption = Setting::$systemSetting['childAgeOption'];

            $clientID = $db->userID;
            $site = $db->userType;

            if($site == "Admin"){
                $clientID = $params["clientID"];
            }

            // if($clientID || $site == "Admin"){
            //     $productReturn = Product::getProductList();
            //     $productData = $productReturn["data"];
            //     foreach ($productData as $productID => $productRow) {

            //         $productRow["bonusValue"] = $productRow["bonusValue"]["value"];

            //         if($productRow["isRegisterPackage"]["value"] == "1" || $productRow["isBundlePackage"]["value"] == "1"){
            //             $validProductList[] = $productRow;
            //         }
            //     }

            //     $productData = $validProductList;

            //     $walletList = Cash::walletDisplaySetting($clientID);
            //     foreach ($walletList as $creditType => $walletData) {
            //         $validCreditType[$creditType] = $creditType;
            //         $creditDisplay[$creditType] = $walletData["translation_code"];
            //     }

            //     $registerType = "Package Register";
            //     $db->where("status","Active");
            //     $db->where("payment_type",$registerType);
            //     $res = $db->get("mlm_payment_method", null, "credit_type AS creditType,min_percentage AS minPercentage,max_percentage AS maxPercentage, group_type AS groupType");
            //     foreach($res AS $row){

            //         if($validCreditType[$row["creditType"]]){

            //             $row['creditDisplay'] = $creditDisplay[$row['creditType']];
            //             $row['balance'] = Cash::getBalance($clientID,$row['creditType']);
                        
            //             if(!$row['groupType']){
            //                 $paymentData[$row['creditType']] = $row;
            //             }else{
            //                 $paymentData[$row['groupType']][$row['creditType']] = $row;
            //             }
            //         }
            //     }

            //     $data['credit'] = $paymentData;
            // }

            // return bank account based on country
            // get bank list
            $db->where('status', "Active");
            $db->orderBy('name', "ASC");
            $bankDetail  = $db->get("mlm_bank ", null, "id, country_id, name, translation_code");
            if (empty($bankDetail))
                $bankDetail = '';

            foreach($bankDetail AS &$bankData){
                $bankData['display'] = $translations[$bankData['translation_code']][$language] ? $translations[$bankData['translation_code']][$language] : $bankData["name"];
            }

            foreach($bankDetail as $bankValue) {
                $bankListData[$bankValue['country_id']][] = $bankValue;

            }
            // end of return bank account based on country

            $countryParams = array("pagination" => "No");
            $resultCountryList = Country::getCountriesList($countryParams);
            if (!$resultCountryList) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00281"][$language] /* No result found */, 'data' => "");
            }
            
            // $countryList    = $resultCountryList['data']['countriesList'];
            // $cityList       = Country::getCity();
            // $countyList     = Country::getCounty();
            // $subCountyList     = Country::getSubCounty();
            // $postalCodeList = Country::getPostalCode();
            // $resultStateList = Country::getState();
            // $stateList[]     = $resultStateList;

            $childAgeOption = explode('#', $childAgeOption);
            foreach ($childAgeOption as $childAgeValue) {
                $childAgeData['value'] = $childAgeValue;

                if(is_numeric($childAgeValue)){
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00481'][$language])/*%%childAgeValue%% years old and above*/;
                }else{
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00482'][$language])/*%%childAgeValue%% years old*/;
                }
                $childAgeOptionArr[] = $childAgeData;
            }

            $data["childAgeOption"] = $childAgeOptionArr;
            // foreach ($resultStateList as $stateRow) {
            //     if($stateRow['country_id'] == 129){
            //         $stateList[] = $stateRow;
            //     }
            // }
            

            $data["productData"]       = $productData;
            // $data['countriesList']     = $countryList;
            // $data['stateList']         = $stateList;
            // $data['cityList']          = $cityList;
            // $data['countyList']        = $countyList;
            // $data['subCountyList']     = $subCountyList;
            // $data['postalCode']        = $postalCodeList; 
            $data['placementPosition'] = $position;
            $data['pacDetails']        = $pacDetail;
            $data['bankDetails']      = $bankListData; 

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function verifyTransactionPassword($clientID, $transactionPassword){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            //get the stored password type.
            $passwordEncryption = Setting::getMemberPasswordEncryption();

            $db->where('id', $clientID);
            if($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            }
            else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('transaction_password', $db->encrypt($transactionPassword));
            }
            else {
                // No encryption
                $db->where('transaction_password', $transactionPassword);
            }
            $result = $db->getValue('client', 'transaction_password');

            if(empty($result)){
                 $returnData['field'][] = array('id' => 'transactionPasswordError', 'msg' => $translations["E00282"][$language]);
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00282"][$language] /* Invalid transaction password */, 'data' => $returnData);
            }
            
            if($passwordEncryption == "bcrypt") {
                // We need to verify hash password by using this function
                if(!password_verify($transactionPassword, $result)){
                    $returnData['field'][] = array('id' => 'transactionPasswordError', 'msg' => $translations["E00282"][$language]);
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00282"][$language] /* Invalid transaction password */, 'data' => $returnData);
                }
                    
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRegistrationPaymentDetails($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $sponsorUsername  = $params['sponsorUsername'];
            $codeNum          = $params['codeNum'];

            // Get latest unit price
            $unitPrice = General::getLatestUnitPrice();
            // Get valid credit type 
            $creditName = General::getValidCreditType();
            // Get decimal Placse
            $decimalPlaces = Setting::getSystemDecimalPlaces();

            if (empty($sponsorUsername)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00283"][$language] /* Sponsor no found */, 'data'=> "");
            } else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");
            }
            if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
            } else {
                // p is mlm_product table, s is mlm_product_setting table
                $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                $db->where("s.name","bonusValue");
                $db->where("p.status", "Active");
                $db->where("p.category","Package");
                $db->where("p.id", $codeNum);
                $copyDb        = $db->copy();
                $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                if (empty($resultPackage)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Sponsor no found */, 'data'=> "");
                }
            }

            foreach ($creditName as $value) {
                // Get min/max payment method
                $paymentMethod = Product::getMinMaxPaymentMethod(number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", ""), $value, "Registration");

                if($paymentMethod[$value]){
                    $balance[] = array("name" => $value, "value" => Cash::getClientCacheBalance($sponsorID, $value), "payment" => $paymentMethod[$value]);
                }
            }
            
            $data['sponsorID']              = $sponsorID;
            $data['balance']                = $balance;
            $data['resultPackage']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", "");
            $data['resultPackage']['name']  = $resultPackage['name'];
            $data['resultPackage']['value'] = $resultPackage['value'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getRegistrationPackageDetails($params) {
            $db = MysqliDb::getInstance();

            $language         = General::$currentLanguage;
            $translations     = General::$translations;

            $type             = $params['type'];
            $codeNum          = $params['codeNum'];
            $status           = $params['status'];
            $sponsorUsername  = $params['sponsorUsername'];
            
            // Get latest unit price
            $unitPrice = General::getLatestUnitPrice();
            // Get valid credit type 
            $creditName = Self::getValidCreditType();
            // Get decimal place
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            
            if(empty($sponsorUsername))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00283"][$language] /* Sponsor no found */, 'data'=> "");
            else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");
            }
            
            foreach($creditName as $value) {
                $credit[] = array("name" => $value, "value" => Cash::getClientCacheBalance($sponsorID, $value));
            }

            $data['credit'] = $credit;

            if ($type == 'package') {
                if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                } else {
                    // p is mlm_product table, s is mlm_product_setting table
                    $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                    $db->where("s.name","bonusValue");
                    $db->where("p.status", "Active");
                    $db->where("p.category","Package");
                    $db->where("p.id", $codeNum);
                    $copyDb        = $db->copy();
                    $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                    if (empty($resultPackage)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                    }
                    $data['result']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, '.', '');
                    $data['result']['name']  = $resultPackage['name'];
                    $data['result']['value'] = $resultPackage['value'];
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            } elseif ($type == 'pin') {
                if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00287"][$language] /* Pin no found */, 'data'=> "");
                } else {
                    // a is mlm_product table, c is mlm_pin table
                    $db->join("mlm_product a", "a.id = c.product_id", "LEFT");
                    $db->where("c.code", $codeNum);
                    $db->where('c.status', $status);
                    $copyDb     = $db->copy();
                    $resultPin  = $db->get("mlm_pin c", 1,  "c.code, a.name, c.bonus_value as bonusValue");
                    if (empty($resultPin)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00287"][$language] /* Pin no found */, 'data'=> "");
                    }
                    $data['result'] = $resultPin;
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            } elseif ($type == 'free') {
                if (empty($codeNum)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                } else {
                    // p is mlm_product table, s is mlm_product_setting table
                    $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                    $db->where("s.name","bonusValue");
                    $db->where("p.status", "Active");
                    $db->where("p.category","Package");
                    $db->where("p.id", $codeNum);
                    $copyDb        = $db->copy();
                    $resultPackage = $db->get("mlm_product p", NULL, "p.name");
                    if (empty($resultPackage)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                    }
                    $data['result'] = $resultPackage;
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            }
        }
        // -- Registration End -- //

        public function verifyPayment($params) {
            $db = MysqliDb::getInstance();

            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $clientId            = $params['clientId'];
            $packageId           = $params['packageId'];
            $tPassword           = trim($params['tPassword']);
            $creditData          = $params['creditData'];

            // Get password encryption type
            $passwordEncryption  = Setting::getMemberPasswordEncryption();
            // Get latest unit price
            $unitPrice = General::getLatestUnitPrice();
            
            //checking client ID
            if (empty($clientId)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
            } else {
                $db->where("id", $clientId);
                $id = $db->getValue("client", "id");

                if (empty($id)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
                }
            }
            //checking package ID
            if (empty($packageId)) {
                $errorFieldArr[] = array(
                                            'id'    => 'packageIdError',
                                            'msg'   => $translations["E00339"][$language] /* Invalid package */
                                        );
            }else {
                $db->where("id", $packageId);
                $db->where("category", 'Package');
                $checkingPackageId = $db->getOne("mlm_product", "price, status");
                $price             = $checkingPackageId['price'] * $unitPrice;
                $status            = $checkingPackageId['status'];

                if (empty($checkingPackageId)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'packageIdError',
                                                'msg'   => $translations["E00339"][$language] /* Invalid package */
                                            );
                } else {
                    if ($status != 'Active') {
                        $errorFieldArr[] = array(
                                                    'id'    => 'packageIdError',
                                                    'msg'   => $translations["E00339"][$language] /* Invalid package */
                                                );
                    }
                }
            }
            // checking credit type and amount
            if (empty($creditData)) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00340"][$language] /* Please enter an amount. */
                                        );
            }
            $totalAmount = 0;
            foreach ($creditData as $value) {
                $balance = Cash::getClientCacheBalance($id, $value['creditType']);
                if (!is_numeric($value['paymentAmount']) || $value['paymentAmount'] < 0) {
                    $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $translations["E00330"][$language] /* Amount is required or invalid */
                                            );
                } else {
                    if ($value['paymentAmount'] > $balance){
                        $errorFieldArr[] = array(
                                                    'id'    => $value['creditType'].'Error',
                                                    'msg'   => $translations["E00266"][$language] /* Insufficient credit. */
                                                );
                    }

                    $minMaxResult = Product::checkMinMaxPayment($price, $value['paymentAmount'], $value['creditType'], "Registration");
                    if($minMaxResult["status"] != "ok"){
                        $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $minMaxResult["statusMsg"]
                                            );
                    }

                    $totalAmount = $totalAmount + $value['paymentAmount'];
                    //matching amount with price 
                    
                }
            }

            if ($totalAmount == 0) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00343"][$language] /* Please enter an amount. */
                                        );
            }

            if ($totalAmount < $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00266"][$language] /* Insufficient credit. */
                                        );
            }
            if ($totalAmount > $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00344"][$language] /* Credit total does not match with total cost. */
                                        );
            }      
            //checking transaction password
            if (Cash::$creatorType){
                if (empty($tPassword)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'tPasswordError',
                                                'msg'   => $translations["E00128"][$language] /* Please enter transaction password. */
                                            );
                } else {
                    $result = Self::verifyTransactionPassword($clientId, $tPassword);
                    if($result['status'] != "ok") {
                        $errorFieldArr[] = array(
                                                    'id'  => 'tPasswordError',
                                                    'msg' => $translations["E00346"][$language] /* Invalid password. */
                                                );
                    }
                }
            }
            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        } 

        public function getCreditTransactionList($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlaces  = Setting::getSystemDecimalPlaces();

            $creditType   = $params['creditType'];
            $searchData   = $params['searchData'];
            $seeAll         = $params['seeAll'];

            $usernameSearchType = $params["usernameSearchType"];

            $db->where('name', $creditType);
            $creditCode = $db->getValue('credit', 'translation_code');

            // $data['creditHeader'] = $translations[$creditCode][$language];
            $data['creditType'] = $creditType;

            $db->where('type', $creditType);
            $creditCode = $db->getOne('credit', 'translation_code, code');
            $data['creditHeader'] = $translations[$creditCode['translation_code']][$language];
            
            if(!$creditCode){
                $db->where('name', $creditType);
                $creditCode = $db->getOne('credit', 'admin_translation_code, code');
                $data['creditHeader'] = $translations[$creditCode['admin_translation_code']][$language];
            }

            if($db->userType == 'Admin'){
                $data['creditHeader'] = $data['creditHeader']." - ";
            }

            $creditArr    = Cash::$paymentCredit;
            $creditsType   = $creditArr[$creditType];

            //Get the limit.
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

    		$adminLeaderAry = Setting::getAdminLeaderAry();

            // Means the search params is there
            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);
                            // $downlines[] = $clientID;

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $db->where('client_id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $db->where('username',$dataValue);
                            $mainLeaderID  = $db->getValue('client','id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, 'IN');

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%".$dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;

                        case 'userName':
                            if ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;
                            
                        case 'memberId':
                            $clientMemberID = $db->subQuery();
                            $clientMemberID->where('member_id', $dataValue);
                            $clientMemberID->getOne('client','id');
                            $db->where('client_id', $clientMemberID);  
                            break;
                            
                        case 'transactionType':
                            $db->where('subject', $dataValue);
                            break;
                            
                        case 'toFromId':
                            $fromUsernameID = $db->subQuery();
                            $fromUsernameID->where('username', $dataValue);
                            $fromUsernameID->getOne('client', "id");
                            $db->where('from_id', $fromUsernameID);
                            $db->orwhere('to_id', $toUsernameID);
                            break;

                        case 'phone':
                            $clientPhoneID = $db->subQuery();
                            $clientPhoneID->where('phone', $dataValue);
                            $clientPhoneID->getOne('client', "id");
                            $db->where('client_id', $clientPhoneID);
                            break;
                            
                        case 'searchDate':
                            $columnName = 'created_at';
                            $dateFrom   = trim($v['tsFrom']);
                            $dateTo     = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'leaderUsername':

                            break;

                        case 'mainLeaderUsername':

                            break;

                        case 'sponsorID':
                            $sq = $db->subQuery();  
                            $ssq = $sq->subQuery();
                            $ssq->where('member_id', $dataValue);
                            $ssq->get('client', null, 'id');
                            $sq->where('sponsor_id',$ssq);
                            $sq->get('client',null,'id');
                            $db->where('client_id',$sq,'IN');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if (empty($creditType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00350"][$language] /* Please select a credit type */, 'data' => "");
            } else {
                    $db->where('type', $creditsType ,"IN");
            }

            if($adminLeaderAry){
            	$db->where('client_id', $adminLeaderAry, 'IN');
            }
            $copyDb = $db->copy();
            $db->orderBy('created_at', "DESC");

            $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
            $getName     = "(SELECT name FROM client WHERE client.id=client_id) AS name";
            $getMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS memberID";
            $getSponsorID = "(SELECT member_id FROM client WHERE id = (SELECT sponsor_id FROM client R WHERE R.id = credit_transaction.client_id)) AS sponsorID";

            $totalRecord = $copyDb->getValue("credit_transaction", "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }

            // $db->groupBy("transaction_id");
            $db->groupBy("group_id");
            $db->groupBy("subject");
            $result = $db->get("credit_transaction", $limit, $getUsername.','.$getMemberID.','.$getName.", client_id, subject, from_id, to_id, SUM(amount) as amount, remark, batch_id, creator_id, creator_type, created_at, type, ".$getSponsorID);

            if (empty($result)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00146"][$language] /* No result found. */, 'data'=> $data);

            unset($clientID);

            $clientUsernameMap = $db->map('id')->get('client', NULL, 'id, username');

            foreach($result as $value) {

            	if(!$startingDate) $startingDate = $value['created_at'];

                if($value['creator_type'] == 'SuperAdmin'){

                    $superAdminID[] = $value['creator_id'];
                }
                else if($value['creator_type'] == 'Admin'){
                    $adminID[] = $value['creator_id'];

                }
                else if ($value['creator_type'] == 'Member'){
                    $clientID[] = $value['creator_id'];

                }

                unset($eachBal);
                if(!$balance[$value['client_id']]){
                	$eachBal = Cash::getBalance($value['client_id'], $creditType, $startingDate);
                	$balance[$value['client_id']] = $eachBal;
                }

                $clientIDs[] = $value['client_id'];

                if($value['subject'] == "Transfer In" || $value['subject'] == "Transfer Out")
                    $batch[] = $value['batch_id'];
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
            $usernameList['System']['0'] = "System";
            
            if(!empty($batch)) {
                $db->where('batch_id', $batch, 'IN');
                $db->where('subject', array("Transfer In", "Transfer Out"), 'IN');
                $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
                $batchDetail = $db->get('credit_transaction', null, 'subject, batch_id, '.$getUsername);
            }
            if(!empty($batchDetail)) {
                foreach($batchDetail as $value) {
                    $batchUsername[$value['batch_id']][$value['subject']] = $value['username'];
                }
            }

            foreach($result as $value) {
                $transactionSubject             = $value['subject'];
                $transaction['created_at']  = General::formatDateTimeToString($value['created_at'], "d/m/Y H:i:s");

                $transaction['username']    = $value['username'];
                $transaction['memberID']    = $value['memberID'];
                $transaction['clientID']    = $value['client_id'];
                $transaction['sponsorID']   = $value['sponsorID'];
                $clientID=$transaction['clientID'];
                if(!$clientData[$clientID]['mainLeaderUsername']){//Saving to Array, Client's mainLeaderUsername so not to go searching again for each loop
                    $clientData[$clientID]['mainLeaderUsername'] = Tree::getMainLeaderUsername($transaction)? : '-' ;
                }
                $transaction['mainLeaderUsername']=$clientData[$clientID]['mainLeaderUsername'];
                // $mainLeaderUsername = Tree::getMainLeaderUsername($transaction);
                // $transaction['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";
                $transaction['name']        = $value['name'];
                $transaction['subject']     = $value['subject'];
                if($value['subject'] == "Transfer Out") {
                    $transaction['to_from'] = $batchUsername[$value['batch_id']]["Transfer In"] ? $batchUsername[$value['batch_id']]["Transfer In"] : "-";
                }
                else if($value['subject'] == "Transfer In") {
                    $transaction['to_from'] = $batchUsername[$value['batch_id']]["Transfer Out"] ? $batchUsername[$value['batch_id']]["Transfer Out"] : "-";
                }
                else if($value['from_id'] == "9")
                    $transaction['to_from'] = "bonusPayout";
                else
                    $transaction['to_from'] = "-";

                if($value['from_id'] >= 1000000) {
                    $transaction['credit_in'] = "-";
                    $transaction['credit_out'] = number_format($value['amount'], $decimalPlaces, '.', '');
                    $transaction['balance'] = number_format($balance[$value['client_id']], $decimalPlaces, '.', '');
                    $balance[$value['client_id']] += $value['amount'];
                }
                else {
                    $transaction['credit_in'] = number_format($value['amount'], $decimalPlaces, '.', '');
                    $transaction['credit_out'] = "-";
                    $transaction['balance'] = number_format($balance[$value['client_id']], $decimalPlaces, '.', '');
                    $balance[$value['client_id']] -= $value['amount'];
                }

                $dateTimeStr = $value['created_at'];
                $dateTimeAry = explode(' ', $dateTimeStr);
                $dateAry = explode('-', $dateTimeAry[0]);
                $timeAry = explode(':', $dateTimeAry[1]);

                $startTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':00';
                $endTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':59';

                // $db->where('created_on', $startTimeStr, '>=');
                // $db->where('created_on', $endTimeStr, '<=');
                // $db->where('type', $creditType);
                // $currentRate = $db->getValue('mlm_coin_rate', 'rate');

                // $transaction['coinRate'] = $currentRate;

                $transaction['creator_id'] = $usernameList[$value['creator_type']][$value['creator_id']];
                $transaction['remark'] = $value['remark'] ? $value['remark'] : "-";
                $transaction['type'] = $value['type'] ? $translations[$creditLanguageCodeArray[$value['type']]][$language] : "-";

                if($creditType == 'maxCap'){
                    $db->where('batch_id', $value['batch_id']);
                    $tempClientID = $db->getValue('mlm_client_portfolio', 'client_id');
                    $transaction['to_from'] = $clientUsernameMap[$tempClientID] ? $clientUsernameMap[$tempClientID] : '-';
                } elseif (in_array($transactionSubject, array('Credit Reentry', 'Credit Register', 'Diamond Reentry', 'Diamond Register'))) {
                    $db->where('batch_id', $value['batch_id']);
                    $tempClientID = $db->getValue('mlm_client_portfolio', 'client_id');
                    $transaction['to_from'] = $clientUsernameMap[$tempClientID] ? $clientUsernameMap[$tempClientID] : '-';
                }

                $transactionList[] = $transaction;
                unset($transaction);
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            // This is to get the transaction type(as subject) for the search select option
            $db->groupBy('subject');
            $resultType = $db->get('credit_transaction', null, 'subject');
            if (empty($resultType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00352"][$language] /* Failed to get commands for search option */, 'data' => '');
            }
            foreach($resultType as $value) {
                $searchBarData['type'] = $value['subject'];

                $searchBarDataList[] = $searchBarData;
            }

            // $totalRecord = $copyDb->getValue("credit_transaction", "count(*)");

            // remove duplicate transaction type. Then sort it alphabetically
            $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
            sort($searchBarDataList);

            $data['transactionList'] = $transactionList;
            $data['transactionType'] = $searchBarDataList;
            $data['totalPage']       = ceil($totalRecord/$limit[1]);
            $data['pageNumber']      = $pageNumber;
            $data['totalRecord']     = $totalRecord;
            $data['numRecord']       = $limit[1];
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);  
        }

        public function getTreePlacementPositionAvailability($uplineID, $position) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($uplineID) || empty($position))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => "");

            $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];

            if($position < 1 || $position > $maxPlacementPositions)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00359"][$language] /* Invalid placement. */, 'data' => "");

            $db->where('upline_id', $uplineID);
            $db->where('client_position', $position);
            $result = $db->getOne('tree_placement', 'id');

            if($db->count > 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00360"][$language] /* Position has been taken. */, 'data' => "");
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getTreePlacementPositionValidity($sponsorID, $uplineID, $clientID="") {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($sponsorID) || empty($uplineID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $db->where('client_id', $uplineID);
            $result = $db->getValue('tree_placement', 'trace_key');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language], 'data' => "");

            $traceKey = str_replace(array("-1>","-1<","-1|","-1"), "/", $result);
            $traceKey = array_filter(explode("/", $traceKey), 'strlen');

            $uplineLevel = array_search($uplineID, $traceKey);
            $sponsorLevel = array_search($sponsorID, $traceKey);

            if(!empty($clientID)) {
                if(strlen(array_search($clientID, $traceKey)) > 0)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            }

            if(strlen($sponsorLevel) <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            else if($sponsorLevel > $uplineLevel)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            else
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getUpline($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $getUsername = '(SELECT username FROM client WHERE client.id=upline_id) AS username';
            $getID = '(SELECT id FROM client WHERE client.id=upline_id) AS id';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=upline_id) AS created_at';
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_sponsor', $getUsername.','.$getID.','.$getCreatedAt);

            // if(empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client */, 'data' => "");

            foreach($result as $key => $value) {
                if($key == 'created_at'){
                    $sponsorUpline[$key] = $value ? date($dateTimeFormat, strtotime($value)) : "-";
                }else{
                    $sponsorUpline[$key] = $value ? $value : "-";
                }
            }

            unset($result);

            $getUsername = '(SELECT username FROM client WHERE client.id=upline_id) AS username';
            $getID = '(SELECT id FROM client WHERE client.id=upline_id) AS id';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=upline_id) AS created_at';
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_placement', $getUsername.','.$getID.','.$getCreatedAt);

            // if(empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid client.", 'data' => "");

            foreach($result as $key => $value) {
                $placementUpline[$key] = $value ? $value : "-";
            }

            $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data['placementUpline'] = $placementUpline;
            $data['sponsorUpline'] = $sponsorUpline;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getTreeSponsor($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $clientID = $params['clientID'];
            $site = $db->userType;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00363"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00364"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_sponsor', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00365"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->getOne('client', 'id, username, name AS fullName, created_at, activated, disabled, suspended, freezed,`terminated`');

            if(empty($sponsor))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");
            
            $getUsername = '(SELECT username FROM client WHERE client.id=client_id) AS username';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=client_id) AS created_at';
            $getName = '(SELECT name FROM client WHERE client.id=client_id) AS fullName';
            $getActivated = '(SELECT activated FROM client WHERE client.id=client_id) AS activated';
            $getDisabled = '(SELECT disabled FROM client WHERE client.id=client_id) AS disabled';
            $getSuspended = '(SELECT suspended FROM client WHERE client.id=client_id) AS suspended';
            $getFreezed = '(SELECT freezed FROM client WHERE client.id=client_id) AS freezed';
            $getTerminated = '(SELECT `terminated` FROM client WHERE client.id=client_id) AS `terminated`';

            $db->where('upline_id', $sponsor['id']);
            $downlines = $db->get('tree_sponsor', null, 'client_id,'.$getUsername.','.$getCreatedAt.','.$getName.','.$getActivated.','.$getDisabled.','.$getSuspended.','.$getFreezed.','.$getTerminated);

            $db->where('client_id', $params['realClientID']);
            $searchLevel = $db->getValue('tree_sponsor', 'level');

            $db->where('client_id', $params['clientID']);
            $searchSponsorLevel = $db->getValue('tree_sponsor', 'level');
            $finalSponsorLevel = $searchSponsorLevel - $searchLevel;
            
            $allDownlines = Tree::getSponsorTreeDownlines($sponsor['id'],false);
            foreach ($allDownlines as $value) {
               $allDownlinesArray[] = $value;
            }

            //find the level of all downlines_id
            $db->where('upline_id', $sponsor['id']);
            $sponsorLevel = $db->map("client_id")->get("tree_sponsor", null, "client_id, level");

            $db->where('client_id', $params['realClientID']);
            $targetLevel = $db->getValue('tree_sponsor', 'level');

            foreach ($downlines as $value1) {
                $downlineArry[] = $value1["client_id"];
            }
            $clientIDArr = $downlineArry;
            $clientIDArr[] = $clientID;

            foreach($clientIDArr as $clientIDRow){
                $downline[$clientIDRow] = Tree::getSponsorTreeDownlines($clientIDRow, false);

                if($downline[$clientIDRow]){
                    $db->where('client_id',$downline[$clientIDRow],"IN");
                    $downlineSales[$clientIDRow] = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
                }
            }

            //Get Client Sales
            $db->where('client_id',$clientIDArr,"IN");
            $db->groupBy('client_id');
            $clientSalesData = $db->map('client_id')->get('mlm_bonus_in',null,'client_id, SUM(bonus_value) as bonus_value');

            $clientRankArr = Bonus::getClientRank("Bonus Tier",$clientIDArr,"","rankDisplay");
            $rankIDAry = $db->map("id")->get("rank", null, "id, translation_code as langCode");

            foreach ($downlineArry as $value2) {
                $allDownlinesResult = Tree::getSponsorTreeDownlines($value2,false);
                if (empty($allDownlinesResult)) continue;
                foreach ($allDownlinesResult as $value3) {
                    $allDownlinesResultArray[$value2][] = $value3;
                }
            }
            if(empty($downlines))
                $downlines = array();
  
            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = array_filter(explode("/", $clientTraceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            if($sponsor['terminated'] == 1) {
                $sponsor["rankDisplay"] = "Terminated";
            }else{
                $sponsor["rankDisplay"] = $clientRankArr[$clientID]["rank_id"] ? $translations[$rankIDAry[$clientRankArr[$clientID]["rank_id"]]][$language] : "-";
            }

            $db->where("client_id",$clientID);
            $traceKey = $db->getValue("tree_sponsor","trace_key");

            // $sponsor['community'] = ($clientSalesData[$clientID]['downline'] ? $clientSalesData[$clientID]['downline'] : "0");
            $sponsor["ownSales"] = ($clientSalesData[$clientID] ? Setting::setDecimal($clientSalesData[$clientID]): "0");
            $sponsor["pgpSales"] = $downlineSales[$clientID] ? : 0;

            if($sponsor['created_at'] != '0000-00-00 00:00:00'){
                $sponsor['created_at'] = (date("Y-m-d H:i:s", strtotime($sponsor['created_at'])) < "2022-09-01 00:00:00") ? date('Y-m-d', strtotime('2022-09-01 00:00:00')) : date('Y-m-d', strtotime($sponsor['created_at']));
            }else{
                $sponsor['created_at'] = '-';
            }


            unset($salesRes);
            unset($communityRankID);
            unset($rankID);
            unset($totalDownline);
            
            if($downlines){
                foreach ($downlines as $k => $v) {
                    $downlineIDAry[$v["client_id"]] = $v["client_id"];
                }

                $db->where("client_id", $downlineIDAry, "IN");
                $db->where("status", "Active");
                $downlineProductIDAry = $db->map("client_id")->get("mlm_client_portfolio", null, "client_id, product_id");
               
                $db->where("name","totalDownline");
            	$db->where("client_id", $downlineIDAry, "IN");
            	$totalDownlineArr = $db->map("client_id")->get("client_setting",NULL,"client_id,value");

                foreach ($downlines as $k => &$v) {
                	$donwlineID = $v['client_id'];
                    if($v['terminated'] == 1) {
                        $v["rankDisplay"] = "Terminated";
                    }else{
                        $v["rankDisplay"] = $clientRankArr[$donwlineID]["rank_id"] ? $translations[$rankIDAry[$clientRankArr[$donwlineID]["rank_id"]]][$language] : "-";
                    }
                    
    	            // $totalDownline = $totalDownlineArr[$donwlineID];
    	            // $v['community'] = ($clientSalesData[$donwlineID]['downline'] ? $clientSalesData[$donwlineID]['downline'] : "0");
                    $v["ownSales"] = ($clientSalesData[$donwlineID] ? $clientSalesData[$donwlineID] : "0");
                    $v["pgpSales"] = $downlineSales[$donwlineID] ? : 0;

                    if($v['created_at'] != '0000-00-00 00:00:00'){
                        $v['created_at'] = (date("Y-m-d H:i:s", strtotime($v['created_at'])) < "2022-09-01 00:00:00") ? date('Y-m-d', strtotime('2022-09-01 00:00:00')) : date('Y-m-d', strtotime($v['created_at']));
                    }else{
                        $v['created_at'] = '-';
                    }

    	            unset($downlineIDArr);
                    $v['downlines'] = $sponsorLevel[$value1["client_id"]] - $targetLevel;
                }
            }
            if($site == "Admin"){
                $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
                $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            }
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsor;
            $data['downlinesLevel'] = $downlines;
            $data['uplineLevel'] = $finalSponsorLevel;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getTreePlacement($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $currentDate = date('Y-m-d');

            $site = $db->userType;

            $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];
            for ($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "Placement Total $i";
                $clientSettingName[] = "Placement CF Total $i";
            }

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00367"][$language] /* Failed to load view. */, 'data' => "");

            $db->where('client_id', $params['clientID']);
            $clientTraceKey = $db->getValue('tree_placement', 'trace_key');
            if(empty($clientTraceKey))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00367"][$language] /* Failed to load view. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00368"][$language] /* Invalid username. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_placement', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00369"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->get('client', 1, 'id AS client_id, username, name, member_id, created_at');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            $db->where('trace_key','%'.$params['clientID'].'%','LIKE');
            $placementDownlines = $db->get('tree_placement',null,'client_id');
            foreach($placementDownlines as $placementDownlinesRow){
                $placementDownlineIDAry[$placementDownlinesRow['client_id']] = $placementDownlinesRow['client_id'];
            }

            unset($bonusInAry, $salesAmountData);
            $db->where("DATE(created_at)", $currentDate);
            $db->where('client_id',$placementDownlineIDAry,'IN');
            $db->groupBy("client_id");
            $bonusInAry = $db->map("client_id")->get("mlm_bonus_in", null, "client_id, sum(bonus_value) as bonus_value");

            if($bonusInAry){
                foreach($bonusInAry as $clientID => $bonusValue){
                    unset($uplineData);
                    $uplineData = Bonus::getPlacementTreeUplines($clientID,false);
                    if(empty($uplineData)) continue;
                    $downlinePosition = 0;
                    $downlineID = 0;

                    foreach ($uplineData as $uplineRow) {
                        $uplineID = $uplineRow["client_id"];
                        if($uplineID != $clientID){
                            $salesAmountData[$uplineID][$downlinePosition] += $bonusValue;
                        }
                        $downlineID = $uplineID;
                        $downlinePosition = $uplineRow["client_position"];
                    }
                }
            }

            $depthLevel = $params['depthLevel'] ? $params['depthLevel'] : 3;
            $upline = $sponsor;
            $sponsorDownlines = array();
            for($i = 0; $i < $depthLevel; $i++) {
                $nextGenUpline = array();
                foreach($upline as $value) {
                    $colUsername = '(SELECT c.username FROM client c WHERE c.id=t.client_id) AS username';
                    $colCreatedAt = '(SELECT c.created_at FROM client c WHERE c.id=t.client_id) AS created_at';
                    $colMemberID = '(SELECT c.member_id FROM client c WHERE c.id=t.client_id) AS member_id';
                    $colName = '(SELECT c.name FROM client c WHERE c.id=t.client_id) AS name';
                    $db->where('upline_id', $value['client_id']);
                    $downlines = $db->get('tree_placement t', null, 't.client_id, '.$colUsername.', '.$colMemberID.', '.$colName.', upline_id, client_position,'.$colCreatedAt);

                    if($db->count <= 0)
                        continue;

                    $nextGenUpline = array_merge($nextGenUpline, $downlines);
                    $sponsorDownlines = array_merge($sponsorDownlines, $downlines);
                }
                $upline = $nextGenUpline;
                unset($nextGenUpline);
            }

            foreach($sponsor as $sponsors) {
                // Get the placement total
                if (count($clientSettingName) > 0) {
                    $db->where("name",$clientSettingName,"IN");
                    $db->where("client_id",$sponsors["client_id"]);
                    $bvRes = $db->get("client_setting", null, "name, value");
                    foreach ($bvRes as $bvRow) {
                        $clientSetting[$bvRow["name"]] = $bvRow["value"];
                    }

                    $sponsors['created_at'] = (date("Y-m-d H:i:s", strtotime($sponsors['created_at'])) < "2022-09-01 00:00:00") ? "2022-09-01" : date("Y-m-d", strtotime($sponsors['created_at']));

                    unset($cfAmount);
                    $db->where('client_id', $sponsors["client_id"]);
                    $db->orderBy('bonus_date','DESC');
                    $cfAmount = $db->getOne('mlm_bonus_couple','remaining_dvp_1, remaining_dvp_2');


                    for ($i=1; $i<=$maxPlacementPositions; $i++) {
                        // $sponsors['placementTotal_'.$i] = $clientSetting["Placement Total $i"]? $clientSetting["Placement Total $i"] : 0;
                        // $sponsors['placementCFTotal_'.$i] = $clientSetting["Placement CF Total $i"]? $clientSetting["Placement CF Total $i"] : 0;
                        // $sponsors['placementRRTotal_'.$i] = $clientSetting["Placement RR Total $i"]? $clientSetting["Placement RR Total $i"] : 0;
                        $sponsors['DVP_'.$i] = $salesAmountData[$sponsors["client_id"]][$i] ? $salesAmountData[$sponsors["client_id"]][$i] : 0;
                        $sponsors['remainingDVP_'.$i] = $cfAmount["remaining_dvp_".$i.""]? $cfAmount["remaining_dvp_".$i.""] : 0;
                    }
                    unset($clientSetting);

                    $sponsorRow[] = $sponsors;
                }
            }

            unset($downlines);
            foreach ($sponsorDownlines as $sponsorDownlinesRow) {

                if($sponsorDownlinesRow["client_position"]) {
                    if($maxPlacementPositions == 2)
                        $sponsorDownlinesRow['placement'] = $sponsorDownlinesRow["client_position"] == 1 ? "Left" : "Right";
                    else if($maxPlacementPositions == 3) {
                        if($sponsorDownlinesRow["client_position"] == 1)
                            $sponsorDownlinesRow['placement'] = "Left";
                        else if($sponsorDownlinesRow["client_position"] == 2)
                            $sponsorDownlinesRow['placement'] = "Middle";
                        else if($sponsorDownlinesRow["client_position"] == 3)
                            $sponsorDownlinesRow['placement'] = "Right";
                    }
                }

                $db->where("name",$clientSettingName,"IN");
                $db->where("client_id",$sponsorDownlinesRow["client_id"]);
                $bvRes = $db->get("client_setting", null, "name, value");
                foreach ($bvRes as $bvRow) {
                    $clientSetting[$bvRow["name"]] = $bvRow["value"];
                }

                $sponsorDownlinesRow['created_at'] = (date("Y-m-d H:i:s", strtotime($sponsorDownlinesRow['created_at'])) < "2022-09-01 00:00:00") ? "2022-09-01" : date("Y-m-d", strtotime($sponsorDownlinesRow['created_at']));

                unset($cfAmountPlacement);
                $db->where('client_id', $sponsorDownlinesRow["client_id"]);
                $db->orderBy('bonus_date','DESC');
                $cfAmountPlacement = $db->getOne('mlm_bonus_couple','remaining_dvp_1, remaining_dvp_2');

                for ($i=1; $i<=$maxPlacementPositions; $i++) {
                    // $sponsorDownlinesRow['placementTotal_'.$i] = $clientSetting["Placement Total $i"]? $clientSetting["Placement Total $i"] : 0;
                    // $sponsorDownlinesRow['placementCFTotal_'.$i] = $clientSetting["Placement CF Total $i"]? $clientSetting["Placement CF Total $i"] : 0;
                    // $sponsorDownlinesRow['placementRRTotal_'.$i] = $clientSetting["Placement RR Total $i"]? $clientSetting["Placement RR Total $i"] : 0;
                    $sponsorDownlinesRow['DVP_'.$i] = $salesAmountData[$sponsorDownlinesRow["client_id"]][$i] ? $salesAmountData[$sponsorDownlinesRow["client_id"]][$i] : 0;
                    $sponsorDownlinesRow['remainingDVP_'.$i] = $cfAmountPlacement["remaining_dvp_".$i.""] ? $cfAmountPlacement["remaining_dvp_".$i.""] : 0;
                }
                unset($clientSetting);

                $downlines[] = $sponsorDownlinesRow;

            }
            // foreach($sponsorDownlines as $array) {
            //     foreach($array as $k => $v) {
            //         if($k == "client_position") {
            //             if($maxPlacementPositions == 2)
            //                 $col['placement'] = $v == 1 ? "Left" : "Right";
            //             else if($maxPlacementPositions == 3) {
            //                 if($v == 1)
            //                     $col['placement'] = "Left";
            //                 else if($v == 2)
            //                     $col['placement'] = "Middle";
            //                 else if($v == 3)
            //                     $col['placement'] = "Right";
            //             }
            //         }


            //         $col[$k] = $v;
            //     }
            //     $downlines[] = $col;
            // }

            if(empty($downlines))
                $downlines = array();

            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = str_replace(array("-1>","-1<","-1|","-1","<",">"), "/", $clientTraceKey);
                    $traceKey = array_filter(explode("/", $traceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            if($site == "Admin"){
                $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
                $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            }
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsorRow;
            $data['downlines'] = $downlines;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPlacementTreeVerticalView($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = trim($params["clientID"]);
            $targetID = trim($params["targetID"]);
            $viewType = trim($params["viewType"]);

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00371"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $clientID);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00372"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_placement', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00373"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $clientID = trim($childID);
                    $targetID = trim($childID);
                }
            }

            $maxPlacementPositions = Setting::$systemSetting["maxPlacementPositions"];

            if(strlen($clientID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "clientID");
            if(!$viewType)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00375"][$language] /* Select view type */, 'data' => array('field' => "targetID"));


            for($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "'Placement Total $i'";
                $clientSettingName[] = "'Placement CF Total $i'";
            }

            $db->where("id", $targetID);
            // $db->where("type", "Member");
            $result = $db->getOne("client", "id");
            if(!$result)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00149"][$language] /* Client not found */, 'data' => array('field' => "targetID"));


            $db->where("client_id", $targetID);
            $targetClient = $db->getOne("tree_placement", "level, trace_key");            

            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);

            $targetTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $filterTraceKey);


            foreach ($targetTraceKey as $key => $val) {
                if(!is_numeric($val[0])){
                    $targetUplinesID[] = explode("-", substr($val, 1))[0];

                }else{
                    $targetUplinesID[] = explode("-", $val)[0];
                }
            }

            $db->where("client_id" , $targetUplinesID, "IN");
            $targetUplinesAry = $db->get("tree_placement", null, "client_id,client_position,level,trace_key");
            
            $db->where("id" , $targetUplinesID, "IN");
            $targetUplinesClient = $db->map ('id')->ObjectBuilder()->get("client", null, "id, username, name, created_at");
            
            foreach ($targetUplinesAry as $key => $upline) {
                $uplineID = $upline['client_id'];
                $username = $targetUplinesClient[$uplineID]->username;
                $name = $targetUplinesClient[$uplineID]->name;
                $createdAt = $targetUplinesClient[$uplineID]->created_at;

                $tree['attr']['ID'] = $uplineID;
                $tree['attr']['name'] = $name;
                $tree['attr']['username'] = $username;
                // Build the level from clientID to targetID
                $data['treeLink'][] = $tree;

                if($uplineID == $targetID) {

                    $data['target']['attr']['id'] = $uplineID;
                    $data['target']['attr']['username'] = $username;
                    $data['target']['attr']['name'] = $name;
                    $data['target']['attr']['createdAt'] = date("d/m/Y", strtotime($createdAt));

                    $targetLevel = $upline["level"];
                }
            }

            $depthRule = "1";
            if($viewType == "Horizontal") $depthRule = "3";

            $db->where("level", $targetClient["level"], ">");
            $db->where("level", $targetClient["level"]+$depthRule, "<=");
            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
            $targetDownlinesAry = $db->get("tree_placement", null," client_id,client_unit,client_position,level,trace_key");

            if(count($targetDownlinesAry) == 0) return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);

            foreach ($targetDownlinesAry as $key => $val) $targetDownlinesIDAry[] = $val["client_id"];
            $db->where("id", $targetDownlinesIDAry, "in");
            $targetDownlinesClient = $db->map('id')->ObjectBuilder()->get("client",null,"id,username,name,created_at,disabled,suspended,freezed");
            
            foreach ($targetDownlinesAry as $key => $targetDownline) {
                $depth = $targetDownline["level"] - $targetLevel;
                $downlineID = $targetDownline['client_id'];
                $username = $targetDownlinesClient[$downlineID]->username;
                $name = $targetDownlinesClient[$downlineID]->name;
                $createdAt = $targetDownlinesClient[$downlineID]->created_at;
                $disabled = $targetDownlinesClient[$downlineID]->disabled;
                $suspended = $targetDownlinesClient[$downlineID]->suspended;
                $freezed = $targetDownlinesClient[$downlineID]->freezed;

                $downline['attr']['id'] = $downlineID;
                $downline['attr']['username'] = $username;
                $downline['attr']['name'] = $name;
                $downline['attr']['position'] = $targetDownline["client_position"];

                $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];
                if($maxPlacementPositions == 2)
                    $downline['attr']['position'] = $downline['attr']['position'] == 1 ? "Left" : "Right";
                else if($maxPlacementPositions == 3) {
                    if($downline['attr']['position'] == 1)
                        $downline['attr']['position'] = "Left";
                    else if($downline['attr']['position'] == 2)
                        $downline['attr']['position'] = "Middle";
                    else if($downline['attr']['position'] == 3)
                        $downline['attr']['position'] = "Right";
                }
                $downline['attr']['depth'] = $depth;
                $downline['attr']['createdAt'] = date("d/m/Y", strtotime($createdAt));
                $downline['attr']['downlineCount'] = count(Self::getPlacementTreeDownlines($downlineID, false));
                $downline['attr']['disabled'] = $disabled==0 ? "No" : "Yes";
                $downline['attr']['suspended'] = $suspended==0 ? "No" : "Yes";
                $downline['attr']['freezed'] = $freezed==0 ? "No" : "Yes";

                $data['downline'][] = $downline;
                unset($downline);

                //get placement total in client setting                
            }

            $data['targetID'] = ($clientID == $targetID) ? "" : $targetID;
            
            // $data['generatePlacementBonusType'] = Setting::$internalSetting['generatePlacementBonusType'];
            // $data['placementLRDecimalType'] = Setting::$internalSetting['placementLRDecimalType'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPlacementTreeDownlines($clientID, $includeSelf = true) {
            $db = MysqliDb::getInstance();   

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_placement", "trace_key");

            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
            $result = $db->get("tree_placement", null, "client_id");

            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];

            if(!$includeSelf) unset($downlineIDArray[$clientID]);

            return $downlineIDArray;
        }

        public function getSponsorTreeTextView($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00376"][$language] /* Failed to load view */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00377"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00376"][$language] /* Failed to load view */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_sponsor', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00378"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->get('client', 1, 'id AS client_id, username,member_id, created_at');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00379"][$language] /* Invalid client */, 'data' => "");

            $depthLevel = 30;
            $upline = $sponsor;
            $sponsorDownlines = array();

            for($i = 0; $i < $depthLevel; $i++) {
                $nextGenUpline = array();
                foreach($upline as $value) {
                    $colUsername = '(SELECT c.username FROM client c WHERE c.id=t.client_id) AS username';
                    $colCreatedAt = '(SELECT c.created_at FROM client c WHERE c.id=t.client_id) AS created_at';
                    $colMemberID = '(SELECT c.member_id FROM client c WHERE c.id=t.client_id) AS member_id';
                    $uplineUsername = '(SELECT c.username FROM client c WHERE c.id=t.upline_id) AS uplineUsername';
                    $db->where('upline_id', $value['client_id']);
                    $downlines = $db->get('tree_sponsor t', null, 't.client_id, '.$colUsername.', upline_id,'.$colCreatedAt.', '.$colMemberID.', '.$uplineUsername);

                    if($db->count <= 0)
                        continue;
                    $nextGenUpline = array_merge($nextGenUpline, $downlines);

                    // $sponsorDownlines = array_merge($sponsorDownlines, $downlines);
                }
                $upline = $nextGenUpline;

                unset($nextGenUpline);
                if(!$upline) continue;
                $downlinesLevel[$i] = $upline;
            }

            foreach ($downlinesLevel as $key => $downData) {
                foreach ($downData as $downValue ) {
                       $downlineDataAry[$downValue["client_id"]] = $downValue["client_id"];
                
                }
            }

            $db->where('status','Active');
            $productRes = $db->get('mlm_product',null,'id,name');
            foreach ($productRes as $pKey => $productRow) {
                $productIDAry[$productRow['id']] = $productRow['id'];
                $productNameAry[$productRow['id']] = $productRow['name'];
            }

            if($productIDAry){
                $db->where('module_id',$productIDAry,"IN");
                $db->where('module','mlm_product');
                $db->where('language',$language);
                $productNameDisplayAry = $db->map('module_id')->get('inv_language',null,'module_id,content');
            }
            
            $allDownlines = Tree::getSponsorTreeDownlines($params['clientID'],false);
            foreach ($allDownlines as $value) {
               $allDownlinesArray[] = $value;
            }

            if(!empty($allDownlinesArray)){
                $db->where("product_id",$productIDAry,'IN');
                $db->where("client_id",$allDownlinesArray,'IN');
                $db->groupBy("product_id");
                $portfolioRes = $db->get("mlm_client_portfolio",null,'client_id,sum(product_price) as total, product_id');

                foreach ($portfolioRes as $portfolioValue) {
                    $pData["name"]  = $productNameAry[$portfolioValue['product_id']];
                    $pData["total"]  = $portfolioValue['total'];

                    $portfolioData[] = $pData;

                }
            }

            //Logic not same, hide it.
            /*$db->where("status","Active");
            $db->orderBy("created_at","ASC");
            $allPortfolioRes = $db->get("mlm_client_portfolio",null,'client_id, product_id');
            foreach ($allPortfolioRes as $allPortfolioData) {
                $clientPortfolioDataAry[$allPortfolioData['client_id']] = $allPortfolioData['product_id'];
            }*/

            foreach ($downlinesLevel as $level => &$firstRow) {
                foreach ($firstRow as &$downlinesLevelData) {
                    $insideClientID = $downlinesLevelData['client_id'];
                    /*$packageDisplay = $productNameDisplayAry[$clientPortfolioDataAry[$insideClientID]];
                    $downlinesLevelData['packageDisplay'] = $packageDisplay?$packageDisplay:"-";*/
                    $downlinesLevelData['created_at'] = $downlinesLevelData['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($downlinesLevelData['created_at'])) : "-";;

                }
            }


            $data['downlines'] = $downlinesLevel;
            $data['portfolio'] = $portfolioData;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function changeSponsor($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            if(empty($params['clientID']) || empty($params['uplineUsername'])) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");
            }

            $clientID       = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];

            // Get sponsor by username
            $db->where('username',$uplineUsername);
            $uplineID = $db->getValue('client','id');

			if(empty($uplineID)) {
				return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid new sponsor id.", 'data' => "");
			}

            $db->where("client_id",$clientID);
            $uplineSponsorID = $db->getOne("tree_sponsor","upline_id");


            $db->where("client_id",$uplineSponsorID['upline_id']);
            $uplineSponsorIDTraceKey = $db->getOne("tree_sponsor","trace_key,upline_id");

            $db->where("trace_key","%".$uplineID."%","LIKE");
            $isUnderSponsorID = $db->get("tree_sponsor",null,"client_id");

            foreach ($isUnderSponsorID as $key => $value) {
                $isUnderSponsorIDAry[] = $value['client_id'];
            }

            if(!in_array($uplineID, $isUnderSponsorIDAry)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "The Upline Username Should be Same Tree with The username", 'data' => "");
            }

            $db->where("client_id",$clientID);
            $uplineOctID = $db->getOne("tree_placement","upline_id,trace_key");

            $db->where("client_id", $uplineOctID['upline_id']);
            $traceKey = $db->getValue("tree_placement", "trace_key");
            $traceKeys = str_replace(array("<", "|", ">", "-1"), array("/", "/", "/", ""), $traceKey);

            $uplineOctIDArray = array_filter(explode("/", $traceKeys), 'strlen');

            $uplineOctIDArray = array_slice($uplineOctIDArray,0);

            if(!in_array($uplineID, $uplineOctIDArray)) {
                 return array('status' => "error", 'code' => 1, 'statusMsg' => "The Upline Username is in different  Placement Tree ", 'data' => "");
            }

            $targetSponsor = Tree::getSponsorByUsername($uplineUsername);
            if(!$targetSponsor) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00573"][$language], 'data' => "");

            } else {
                $targetSponsorTraceKey = explode("/", $targetSponsor["trace_key"]);
                foreach ($targetSponsorTraceKey as $val) {
                    $targetSponsorUplinesID[$val] = $val;
                }

            }

            //get current client's sponsor ID
            $db->where("id", $clientID);
            $client = $db->getOne("client", "sponsor_id, username");
            $oldSponsorID = $client["sponsor_id"];
            $username = $client["username"];

            // If is the same sponsor, skip it
            if($targetSponsor["id"] == $oldSponsorID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00574"][$language], 'data' => "");

            }

            // If is ownself, skip it
            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');
            if($uplineID == $clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00575"][$language], 'data' => "");

            }

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_sponsor", "trace_key");
            $clientTraceKey = $result["trace_key"];

            if(!$clientTraceKey) {
                // Skip if encounter error
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data' => "");

            }

            // Compare level, cannot change to a lower level sponsor in the same tree
            if($targetSponsorUplinesID[$clientID]) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00577"][$language], 'data' => "");

            }

            // Remove sales from old sponsor upline
            $db->where('trace_key', $clientTraceKey.'/%', 'like');
            $downlineIDArray = $db->map('client_id')->get('tree_sponsor',null,'client_id');

            $removeGroupSalesRes = Subscribe::updateSponsorGroupSales($clientID,'decrease',$downlineIDArray);
            if ($removeGroupSalesRes['status'] != 'ok') {
                return $removeGroupSalesRes;
            }


            //lock the table prevent others access this table while running function
        //  $db->setLockMethod("WRITE")->lock("tree_sponsor");
            $db->where('client_id', $uplineID);
            $upline = $db->getOne('tree_sponsor', 'level, trace_key', 1);

            if($db->count <= 0) {
               // $db->unlock();
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $uplineLevel = $upline['level'];
            $traceKey = $upline['trace_key'];

            $db->where('client_id', $clientID);
            $client = $db->getOne('tree_sponsor', 'id, level, trace_key');

            if($db->count <= 0) {
              // $db->unlock();
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $db->rawQuery("UPDATE tree_sponsor SET upline_id = '".$uplineID."', level = '".($uplineLevel + 1)."', trace_key = '".($traceKey.'/'.$clientID)."' WHERE id = '".$client['id']."' ");

            $db->where('trace_key', $client['trace_key'].'/%', 'like');
            $downlines = $db->get('tree_sponsor', null, 'id, client_id, level, trace_key');

            $levelDiscrepancy = (($uplineLevel - $client['level']) + 1);

            foreach($downlines as $value) {
                $array = explode($clientID.'/', $value['trace_key']);

                $result = $db->rawQuery("UPDATE tree_sponsor SET level = '".($levelDiscrepancy + $value['level'])."', trace_key = '".($traceKey.'/'.$clientID.'/'.$array[1])."' WHERE id = '".$value['id']."' ");

            }

            $db->where('id', $clientID);
            $db->update('client', array('sponsor_id' => $uplineID));

            Leader::insertMainLeaderSetting($clientID, $uplineID);

            // insert activity log
            $titleCode    = 'T00009';
            $activityCode = 'L00009';
            $transferType = 'Change Sponsor';
            $activityData = array('user' => $username,'oldSponsorID' => $oldSponsorID,'newSponsorID' => $uplineID);

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes) {
              // $db->unlock();
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            $db->where("client_id",$oldSponsorID);
            $traceKey = $db->getValue("tree_sponsor", "trace_key");
            $currentSponsorUpline = explode("/", $traceKey);
            foreach($currentSponsorUpline AS $id){
                $idArr[] = $id;
            }

            $totalDownline = 1;
            $totalDownline += COUNT($downlines);
            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalDownline");
                $db->update("client_setting",array("value"=>$db->dec($totalDownline)));
            }
            unset($idArr);

            $db->where("client_id",$targetSponsor["id"]);
            $traceKey = $db->getValue("tree_sponsor","trace_key");
            $currentSponsorUpline = explode("/", $traceKey);
            foreach($currentSponsorUpline AS $id){
                $idArr[] = $id;
            }

            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalDownline");
                $db->update("client_setting",array("value"=>$db->inc($totalDownline)));
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00386"][$language], 'data' => "");
        
        }

        public function changePlacement($params) {

            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']) || empty($params['uplineUsername']) || empty($params['position']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientID = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];
            $position = $params['position'];

            $db->where('id', $clientID);
            $clientUsername = $db->getOne('client', 'username');

            if($uplineUsername == $clientUsername['username']){
                $errorFieldArr[] = array(
                    'id'  => 'uplineUsernameError',
                    'msg' => "Upline username cannot same with Client Username"
                );
            }
            $data['field'] = $errorFieldArr;
             if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
            }

            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');

             if(empty($uplineID)) {
				return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid new placement id.", 'data' => "");
                //$errorFieldArr[] = array(
                //                            'id'  => 'uplineUsernameError',
                //                            'msg' => $translations["E00382"][$language] /* Username does not exist. */
                //                        );
            }

            $downlineAry=Tree::getPlacementTreeDownlines($clientID,false);

            $db->where("client_id",$clientID);
            $uplineSponsorID = $db->getValue("tree_sponsor","upline_id");

            $db->where("client_id",$uplineSponsorID);
            $uplineOctTraceKey = $db->getValue("tree_placement", "trace_key");

            $db->where('trace_key', $uplineOctTraceKey.'%', 'like');
            $availableChangeOctClient = $db->get("tree_placement",null,"client_id");

            foreach ($availableChangeOctClient as $client) {
                $availableChangeOctClientAry[] = $client['client_id'];
            }
          
            if(!in_array($uplineID, $availableChangeOctClientAry)) {
                 return array('status' => "error", 'code' => 1, 'statusMsg' => "The Upline Username cannot High than the Sponsor Username", 'data' => "");
            }

            if(in_array($uplineID, $downlineAry)){
                $errorFieldArr[] = array(
                    'id'  => 'uplineUsernameError',
                    'msg' => "upline  username  exists  in Client downlines" /* Username does not exist. */
                );

            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
            }

            $db->where("upline_id", $uplineID);
            $db->where("client_position", $position);
            $checkUplineAvailabilePosition = $db->getOne('tree_placement', "client_position, level, trace_key");
            
            if(!empty($checkUplineAvailabilePosition)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $db->where("client_id", $clientID);
            $clientOldData= $db->getOne('tree_placement', "client_position, level, trace_key");


            $db->where("client_id", $uplineID);
            $uplineData= $db->getOne('tree_placement', "client_position, level, trace_key");

            $currentlevel = $uplineData['level'] +1;
            $uplinePosition = $uplineData['client_position'];

            if($position==1) {
                $traceKey = $uplineData["trace_key"]."<".$clientID."-1";
            } else {
                $traceKey = $uplineData["trace_key"].">".$clientID."-1"; 
            }
            
            if($currentlevel==0) {
                $clientUnit = 0;
                $uplineUnit = 0;
            } else if($currentlevel==1) {
                $clientUnit = 1;
                $uplineUnit = 0;
            } else {
                $clientUnit = 1;
                $uplineUnit = 1;
            }


            // update treeoctopus table columns
            $updateData = array (
                'client_unit'       => $clientUnit,
                'client_position'   => $position,
                'upline_id'         => $uplineID,
                'level'             => $currentlevel,
                'trace_key'         => $traceKey
            );
            $db->where('client_id', $clientID);
            $db->update('tree_placement', $updateData);


            $updateUplineData = array(
                'upline_unit'       => $uplineUnit,
                'upline_position'   => $uplinePosition
            );
            $db->where("upline_id", $uplineID);
            $db->update("tree_placement", $updateUplineData);


            $updateClientTable = array (
                'placement_id'         => $uplineID,
                'placement_position'   => $position,
            );
            $db->where('id', $clientID);
            $db->update('client', $updateClientTable);


            $db->where('upline_id', $clientID);
            $db->orderBy('id','ASC');
            $downlines = $db->get('tree_placement', null,"client_position, level, trace_key");

            if(!empty($downlines)){

                foreach ($downlines as $row) {
                    $db->where('trace_key', $row['trace_key'].'%', 'like');
                    $db->orderBy('id','ASC');
                    $getalldownlines= $db->get('tree_placement', null);

                       foreach($getalldownlines as $value) {

                        $downlineTraceKeys=str_replace($clientOldData['trace_key'],"", $value['trace_key']);
                        $downlineRealTraceKey =$traceKey.$downlineTraceKeys;
                        $downlinesLevels = count(explode(",", str_replace(array("<",">","|"), ",", $downlineRealTraceKey)))-1;

                        $updateDataDownline = array (
                            'trace_key'         => $downlineRealTraceKey,
                            'level'             => $downlinesLevels,
                        );

                        $db->where('client_id', $value['client_id']);
                        $db->update('tree_placement', $updateDataDownline);
                    }
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $updateData);

        }

        public function getSponsor($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getClientMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS member_id";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $getUplineMemberID = "(SELECT member_id FROM client WHERE client.id=upline_id) AS upline_member_id";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_sponsor', 'client_id, upline_id,'.$getClientName.','.$getClientUsername.','.$getClientMemberID.','.$getUplineName.','.$getUplineUsername.','.$getUplineMemberID);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            foreach($result as $key => $value) {
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }

            $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPlacement($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getClientMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS member_id";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $getUplineMemberID = "(SELECT member_id FROM client WHERE client.id=upline_id) AS upline_member_id";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_placement', 'client_id, upline_id, client_position, '.$getClientName.','.$getClientUsername.','.$getUplineName.','.$getUplineUsername.','.$getUplineMemberID.','.$getClientMemberID);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];

            foreach($result as $key => $value) {
                if($key == "client_position"){
                    if($maxPlacementPositions == 2)
                        $value = $value == 1 ? "Left" : "Right";
                    else if($maxPlacementPositions == 3) {
                        if($value == 1)
                            $value = "Left";
                        else if($value == 2)
                            $value = "Middle";
                        else if($value == 3)
                            $value = "Right";
                    }
                }
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }

            $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getCustomerServiceMemberDetails($clientID="", $params="") {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            if(empty($clientID) && empty($params))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientID = $clientID ? $clientID : $params['clientID'];
            $db->where('id', $clientID);
            $getClientUnitTier = "(SELECT value FROM client_setting WHERE name='tierValue' AND client_id=client.id) AS unit_tier";
            $getClientSponsorBonusPercentage = "(SELECT value FROM client_setting WHERE type='Bonus Percentage' AND name='sponsorBonus' AND client_id=client.id) AS sponsor_bonus_percentage";
            $getClientPairingBonusPercentage = "(SELECT value FROM client_setting WHERE type='Bonus Percentage' AND name='pairingBonus' AND client_id=client.id) AS pairing_bonus_percentage";
            $result = $db->getOne('client', 'id, username, member_id, name, '.$getClientUnitTier.','.$getClientSponsorBonusPercentage.','.$getClientPairingBonusPercentage);
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            foreach($result as $key => $value) {
                $memberDetails[$key] = $value ? $value : "0";
            }

            // get MT4 account no.
            $db->where('client_id',$clientID);
            $db->where('name','quantumAccDisplay');
            $quantumAcc = $db->getValue('client_setting','value');
            $memberDetails["quantumAcc"] = $quantumAcc?:'-'; 

            $data['memberDetails'] = $memberDetails;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getInboxUnreadMessage($userID,$site){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientID = $userID ? $userID : $db->userID;
            $site = $site ? $site : $db->userType;

            /* get user's inbox message */
            if($site == "Member"){
                /*$inboxSubQuery = $db->subQuery();
                $inboxSubQuery->where("`creator_id`", $clientID);
                $inboxSubQuery->orWhere("`receiver_id`", $clientID);
                $inboxSubQuery->get("`mlm_ticket`", null, "`id`");*/

                $db->where("creator_id", $clientID);
                $db->orWhere("receiver_id", $clientID);
                $ticketRes = $db->get("mlm_ticket", null, "id, type");
                foreach ($ticketRes as $ticketRow) {
                    $unreadTicketAry[$ticketRow["id"]] = $ticketRow["type"];
                    $ticketIDAry[] = $ticketRow["id"];
                }
            }else{
                /*$inboxSubQuery = $db->subQuery();
                $inboxSubQuery->where("`type`", "support");
                $inboxSubQuery->where("`status`", "Closed", "!=");
                $inboxSubQuery->get("`mlm_ticket`", null, "`id`");*/

                $db->where("status",  "Closed", "!=");
                $ticketRes = $db->get("mlm_ticket", null, "id, type");
                foreach ($ticketRes as $ticketRow) {
                    $unreadTicketAry[$ticketRow["id"]] = $ticketRow["type"];
                    $ticketIDAry[] = $ticketRow["id"];
                }

            }

            if($ticketIDAry){
                $db->where("`ticket_id`", $ticketIDAry, "IN");
                $db->where("`sender_id`", $clientID, "!=");
                $db->where("`read`", 0);
                $db->groupBy("ticket_id");
                $inboxUnreadMessageRes = $db->get("`mlm_ticket_details`", null, "ticket_id, COUNT(*) as unreadCount");
                foreach($inboxUnreadMessageRes as $inboxUnreadMessageRow){
                    $inboxUnreadMessage[$unreadTicketAry[$inboxUnreadMessageRow["ticket_id"]]] += $inboxUnreadMessageRow["unreadCount"];
                    // $inboxUnreadMessage[$inboxUnreadMessageRow["type"]] = $inboxUnreadMessageRow["unreadCount"];
                }
            }

            

            $data['inboxUnreadMessage'] = $inboxUnreadMessage;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function memberChangePassword($params) {
            $db = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $memberId = $db->userID;

            // $memberId            = $params['memberId'];
            $passwordCode        = $params['passwordCode'];
            $currentPassword     = $params['currentPassword'];
            $verificationCode    = $params['verificationCode'];
            $newPassword         = $params['newPassword'];
            $newPasswordConfirm  = $params['newPasswordConfirm'];

            // get password length
            $maxPass  = Setting::$systemSetting['maxPasswordLength'];
            $minPass  = Setting::$systemSetting['minPasswordLength'];
            $maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass = Setting::$systemSetting['minTransactionPasswordLength'];
            // Get password encryption type
            $passwordEncryption  = Setting::getMemberPasswordEncryption();

            if (empty($memberId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if (empty($passwordCode)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00444"][$language] /* Password type not found. */, 'data'=> "");

            } else {
                if ($passwordCode == 1) {
                    $passwordType = "password";

                } else if ($passwordCode == 2) {
                    $passwordType = "transaction_password";

                } else {
                   return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00444"][$language] /* Password type not found. */, 'data'=> "");
                }
            }
            // get error msg type
            if ($passwordType == "password") {
                $idName        = 'Password';
                $msgFieldB     = $translations["A00120"][$language];
                $msgFieldS     = $translations["A00120"][$language];
                $maxLength     = $maxPass;
                $minLenght     = $minPass;

            } else if ($passwordType == "transaction_password") {
                $idName        = 'TPassword';
                $msgFieldB     = $translations["A01190"][$language];
                $msgFieldS     = $translations["A01190"][$language];
                $maxLength     = $maxTPass;
                $minLenght     = $minTPass;

            }
            if (empty($newPasswordConfirm)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."ConfirmError",
                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00445"][$language])
                                        );

            } else {
                if ($newPasswordConfirm != $newPassword) 
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."ConfirmError",
                                                'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00446"][$language]) 
                                            );
            }

            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = Setting::getEncryptedPassword($newPassword);
            // Retrieve the encrypted currentPassword based on settings
            $encryptedCurrentPassword = Setting::getEncryptedPassword($currentPassword);

            $db->where('id', $memberId);
            $result = $db->getOne('client', $passwordType);
            if (empty($result)) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if ($passwordType == "password"){
                if (empty($currentPassword)) {
                    $errorFieldArr[] = array(
                                                'id'  => "current".$idName."Error",
                                                'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00448"][$language]) 
                                            );
                } else {
                    // Check password encryption
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(!password_verify($currentPassword, $result[$passwordType])) {
                            $errorFieldArr[] = array(
                                                        'id'  => "current".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                    );
                        }
                    } else {
                        if ($encryptedCurrentPassword != $result[$passwordType]) {
                            $errorFieldArr[] = array(
                                                        'id'  => "current".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                    );
                        }
                    }
                }
            }elseif ($passwordType == "transaction_password"){
                if (empty($currentPassword)) {
                    // if(empty($verificationCode)){
                        $errorFieldArr[] = array(
                                                    'id'  => "current".$idName."Error",
                                                    'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00448"][$language]) 
                                                );
                } else {
                    // Check password encryption
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(!password_verify($currentPassword, $result[$passwordType])) {
                            // if(empty($verificationCode)){

                                $errorFieldArr[] = array(
                                                            'id'  => "current".$idName."Error",
                                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                        );
                        }
                    } else {
                        if ($encryptedCurrentPassword != $result[$passwordType]) {
                            // if(empty($verificationCode)){
                                $errorFieldArr[] = array(
                                                            'id'  => "current".$idName."Error",
                                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                        );
                        }
                    }
                }
            }
            if (empty($newPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                        );
            } else {
                if (strlen($newPassword) < $minLenght || strlen($newPassword) > $maxLength) {
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."Error",
                                                'msg' => str_replace(array("%%min%%","%%max%%","%%password%%"), array($minLenght,$maxLength,$msgFieldB), $translations["E00451"][$language])  /*  cannot be less than  */  /*  or more than  */ 
                                            );
                }else if(!ctype_alnum($newPassword) || !preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $newPassword)){

                    $errorFieldArr[] = array(
                        'id'  => "new".$idName."Error",
                        'msg' => $translations["M00190"][$language]
                    );

                }else {
                    //checking new password no match with current password
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(password_verify($newPassword, $result[$passwordType])) {
                            $errorFieldArr[] = array(
                                                        'id'  => "new".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00453"][$language]) 
                                                    );
                        }
                    } else {
                        if ($newEncryptedPassword == $result[$passwordType]) {
                            $errorFieldArr[] = array(
                                                        'id'  => "new".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00453"][$language]) 
                                                    );
                        }  
                    }
                }
            }
            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);

            $updateData = array($passwordType => $newEncryptedPassword);
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00455"][$language] /* Update failed. */, 'data' => "");
        }

        public function memberResetPassword($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            // $username           = $params['username'];
            $password           = $params['password'];
            $retypePassword     = $params['retypePassword'];
            $verificationCode   = $params['verificationCode'];
            $otpType            = $params['otpType'];
            // $step               = $params['step'];
            // $phoneNumber        = $params['phoneNumber'];
            // $dialCode           = $params['dialCode'];
            //$email = trim($params['email']);
            $phone = trim($params['phone']);

            $maxPass  = Setting::$systemSetting['maxPasswordLength'];
            $minPass  = Setting::$systemSetting['minPasswordLength'];

            // if($otpType == 'email') {
            //     if(!$email){
            //         $errorFieldArr[] = array(
            //             'id'  => 'emailError',
            //             'msg' => $translations["E00318"][$language] /* Please fill in email */
            //         );
            //     } else {
            //         $db->where('email', $email);
            //         $client = $db->getOne('client', 'id, register_method');

            //         if(!$client) {
            //             $errorFieldArr[] = array(
            //                 'id'  => 'emailError',
            //                 'msg' => $translations["E00679"][$language] /* Invalid User. */
            //             );
            //         }
            //     }
            // } else if($otpType == 'phone') {
            //     if(!$dialCode || !$phoneNumber){
            //         $errorFieldArr[] = array(
            //             'id'  => 'phoneError',
            //             'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
            //         );
            //     } else {
            //         $db->where('dial_code',$dialCode);
            //         $db->where('phone',$phoneNumber);

            //         $client = $db->getOne('client', 'id, register_method');

            //         if(!$client) {
            //             $errorFieldArr[] = array(
            //                 'id'  => 'phoneError',
            //                 'msg' => $translations["E00679"][$language] /* Invalid User. */
            //             );
            //         }
            //     }
            // }

            // Check Register Method
            // if($client) {
            //     if($client['register_method'] != $otpType) {
            //         return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00922"][$language] /* Invalid Reset Password Method. */, 'data' => '');
            //     }
            // }

            // if(!$username){
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'usernameError',
            //                                 'msg' => $translations["E00227"][$language] /* Invalid username */
            //                             );
            // }else{
            //     // $db->where('id', $clientID);
            //     $db->where('username', $username);
            //     $clientData = $db->getOne('client', 'id, email');
            //     if(!$clientData || !$clientData['id'] || !$clientData['email']){
            //         return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /* Invalid User */, 'data' => '');
            //     }
            //     $clientID    = $clientData['id'];
            //     $clientEmail = $clientData['email'];
            // }

            if($phone == '60')
            {
                $errorFieldArr[] = array(
                    'id'  => 'phoneError',
                    'msg' => $translations["E00773"][$language] /* Invalid phone number */
                );
            }

            if(!$verificationCode)
            {
                $errorFieldArr[] = array(
                    'id'  => 'otpError',
                    'msg' => $translations["E00864"][$language] /* Invalid OTP code */
                );
            }

            if(!$password){
                $errorFieldArr[] = array(
                    'id'  => 'passwordError',
                    'msg' => $translations["E00306"][$language] /* Please fill in password */
                );
            }
            else if(strlen($password) < $minPass || strlen($password) > $maxPass){
                $errorFieldArr[] = array(
                    'id'  => 'passwordError',
                    'msg' => $translations["M00190"][$language] /* Password must contain 6-20 characters, which consists of letters and numbers. */
                );
            }

            else if(!ctype_alnum($password)){
                $errorFieldArr[] = array(
                    'id'  => 'passwordError',
                    'msg' => $translations["M00190"][$language] /* Password must contain 6-20 characters, which consists of letters and numbers. */
                );
            }

            else if(!preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $password)){
                $errorFieldArr[] = array(
                    'id'  => 'passwordError',
                    'msg' => $translations["M00190"][$language] /* Password must contain 6-20 characters, which consists of letters and numbers. */
                );
            }
            if(!$retypePassword){
                $errorFieldArr[] = array(
                    'id'  => 'checkPasswordError',
                    'msg' => $translations["E00306"][$language] /* Please fill in password */
                );
            }
            else if($password != $retypePassword){
                $errorFieldArr[] = array(
                    'id'  => 'checkPasswordError',
                    'msg' => $translations["M01051"][$language] /* The passwords you entered do not match. Please retype your password. */
                );
            }

            // if(!in_array($otpType, array('email', 'phone'))){
            //     $errorFieldArr[] = array(
            //         'id'  => 'otpTypeError',
            //         'msg' => 'Invalid OTP type'
            //     );   
            // }

            // if(!$verificationCode){
            //     $errorFieldArr[] = array(
            //         'id'  => 'verificationCodeError',
            //         'msg' => $verifyCode['statusMsg'] /* Invalid OTP code. */
            //     );      
            // }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // $verifyCode = Otp::verifyOTPCode($clientID, $otpType, "resetPassword", $verificationCode);
            // if($verifyCode["status"] != "ok") {
            //     $errorFieldArr[] = array(
            //         'id'  => 'verificationCodeError',
            //         'msg' => $verifyCode['statusMsg'] /* Invalid OTP code. */
            //     );
            // } else {
            //     $otpID = $verifyCode['data'];
            // }

            // if($errorFieldArr) {
            //     $data['field'] = $errorFieldArr;
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            // }
            $verifyCode = Otp::verifyOTPCode($clientID,$otpType,"resetPassword",$verificationCode,$phone);
                    
            if($verifyCode["status"] != "error")
            {
                $db->where('phone_number',$phone);
                $db->where('status','Sent');
                $db->where('msg_type','OTP Code');
                $db->where('verification_type','resetPassword##phone');
                $db->where('code',$verificationCode);
                $fields = array("status");
                $values = array("Verified");
                $arrayData = array_combine($fields, $values);
                $row = $db->update("sms_integration", $arrayData);
            }
            else
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Wrong OTP code', 'data' => $verifyCode);
            }

            $params['step'] = 3;
            
            if(empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
                );
            }
            
            if(!$verificationCode){
                $errorFieldArr[] = array(
                    'id'  => 'verificationCodeError',
                    'msg' => $translations["M01050"][$language] /* Please insert otp code */
                );      
            }
            
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            
            
            $db->where('concat(dial_code, phone)', $phone);
            $db->orWhere('member_id', $phone);
            $clientID = $db->getOne('client', 'id, email, username, concat(dial_code, phone) as phone');
            
            if(!$clientID)
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
            
            if($clientID)
            {
                $verificationRes['status'] = 'ok';
            }
            // $verificationRes = Client::accountOwnerVerification($params, 'resetPassword');
            if($verificationRes['status'] != 'ok') return $verificationRes;

            $db->where('phone_number',$phone);
            $db->where('status','Verified');
            $db->where('msg_type','OTP Code');
            $db->where('verification_type','resetPassword##phone');
            $db->where('code',$verificationCode);
            $fields = array("status");
            $values = array("Success");
            $arrayData = array_combine($fields, $values);
            $row = $db->update("sms_integration", $arrayData);

            $otpID = $verificationRes['data'];

            $db->where('concat(dial_code, phone)', $phone);
            $db->orWhere('member_id', $phone);
            $clientID = $db->getValue('client', 'id');

            $db->where('client_id', $clientID);
            $db->where('name', 'hasChangedPassword');
            $dbChangedPassword = $db->copy();
            $hasChangedPassword = $db->get('client_setting');

            if ($hasChangedPassword) {
                $dbChangedPassword->update('client_setting', array('value'=>'1'));
            } else {
                $insertData = array(
                    "name"           => 'hasChangedPassword',
                    'value'          => '1',                                            
                    'client_id'      => $clientID,                                            
                 );

                $db->insert('client_setting', $insertData);
            }

            $db->where('ID', $clientID);
            $updateData = array(
                'password'          => Setting::getEncryptedPassword($password),
                'encryption_method' => 'bcrypt'
            );
            $db->update('client', $updateData);

            if($otpID){
                $db->where('id', $otpID, 'IN');
                $db->update('sms_integration', array('expired_at' => $db->now()));
            }
            $content = '*Reset Password Message* '."\n\n".'Client ID: '.$clientID."\n".'Phone Number: '.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            Client::sendTelegramNotification($content);
            $db->where('phone_number',$phone);
            $db->where('status','Verified');
            $db->where('msg_type','OTP Code');
            $db->where('verification_type','resetPassword##phone');
            $db->where('code',$verificationCode);
            $fields = array("status");
            $values = array("Success");
            $arrayData = array_combine($fields, $values);
            $row = $db->update("sms_integration", $arrayData);
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00168"][$language] /* Update successful */, 'data' => "");
        }

        public function addTransactionPassword($params, $userID) {
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            // $registerType       = trim($params['registerType']);
            $tPassword          = trim($params['tPassword']);
            $checkTPassword     = trim($params['checkTPassword']);

            $maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass = Setting::$systemSetting['minTransactionPasswordLength'];
            
            //checking transaction password
            if (empty($tPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'addTPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if (strlen($tPassword)<$minTPass || strlen($tPassword)>$maxTPass) {
                    $errorFieldArr[] = array(
                                                'id'  => 'addTPasswordError',
                                                'msg' => $translations["E00311"][$language] /* Transaction password cannot be less than */ . $minTPass . $translations["E00312"][$language] /*  or more than  */ . $maxTPass . '.'
                                            );
                }
            }
            //checking re-type transaction password
            if (empty($checkTPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'checkAddTPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if ($checkTPassword != $tPassword) {
                    $errorFieldArr[] = array(
                                                'id'  => 'checkAddTPasswordError',
                                                'msg' => $translations["E00313"][$language] /* Transaction password not match */
                                            );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            $tPassword = Setting::getEncryptedPassword($tPassword);

            $updateData = array('transaction_password' => $tPassword);
            $db->where('id', $userID);
            $db->update('client', $updateData);
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function memberResetTransactionPassword($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $memberId           = $params['memberId'];
            $username           = $params['username'];
            $tPassword           = $params['tPassword'];
            $retypeTPassword     = $params['retypeTPassword'];
            $verificationCode   = $params['verificationCode'];
            // $phoneNumber     = $params['phoneNumber'];
            // $dialCode            = $params['dialCode'];

            // if(!$username)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language], 'data' => array('field'=> 'username'));
            if(!$tPassword)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00310"][$language], 'data' => array('field'=> 'tPassword'));
            if(!$retypeTPassword)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00310"][$language], 'data' => array('field'=> 'retypeTPassword'));
            if($tPassword != $retypeTPassword)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00313"][$language], 'data' => array('field'=> 'retypeTPassword'));

            $maxPass  = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minPass  = Setting::$systemSetting['minTransactionPasswordLength'];
            
            if(strlen($tPassword) < $minPass || strlen($tPassword) > $maxPass)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00193"][$language], 'data' => array('field'=> 'tPassword'));

            if(!ctype_alnum($tPassword)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00193"][$language], 'data' => array('field'=> 'tPassword'));
            }

            if(!preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $tPassword))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00193"][$language], 'data' => array('field'=> 'tPassword'));

            $db->where('concat(dial_code,phone)', $username);
            $db->orWhere('id', $memberId);

            $clientID = $db->getvalue("client","ID");
            if(!$clientID)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language], 'data' => "");

            $db->where('receiver_id', $clientID);
            $db->orderBy('ID', 'DESC');
            $row = $db->getone("sms_integration", "msg");
            $msg = $row['msg'];

            $msg = explode(' ', $msg);

            $db->where('id', $clientID);
            $dialCode = $db->getvalue("client", "dial_code");

            // preg_match_all('!\d+!', $verificationCode, $matches);
            if($dialCode != 212){
                if(!in_array($verificationCode, $msg) || empty($verificationCode)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00997"][$language], 'data' => "");
                }
            }else{
            	if($verificationCode != 12345){
            		return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00997"][$language], 'data' => "");
            	}
            }

            $db->where('ID', $clientID);
            $updateData = array('transaction_password' => Setting::getEncryptedPassword($tPassword));
            $db->update('client', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00168"][$language], 'data' => "");
        } 

        public function addKYC($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $maxAccPP = Setting::$systemSetting['maxAccPerPhone'];

            $name = trim($params["name"]);
            $nric = trim($params["nric"]);
            $documentType = trim($params["documentType"]);
            $country = trim($params["country"]);
            $address = trim($params["address"]);
            $phone   = trim($params["phone"]);

            // $step = trim($params["step"]);
            // $imageData = $params['imageData'];
            // $selfImageData = $params['selfImageData'];

            $clientID = $db->userID;

            if(empty($clientID)) {
                $clientID = $params['clientID'];
            }

            $db->where("id", $country);
            $dialCode = $db->getValue("country", "country_code");
            if (empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                );
            } else {
                if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00858"][$language] /* Only number is allowed */
                    );
                }

                // check max account per phone
                $db->where("dial_code", $dialCode);
                $db->where("phone", $phone);
                $totalAccThisPhone = $db->getValue("mlm_kyc", "COUNT(*)");
                if (!empty($totalAccThisPhone)) {
                    if($totalAccThisPhone>=$maxAccPP){
                        $errorFieldArr[] = array(
                            'id' => 'phoneError',
                            'msg' => $translations["E00994"][$language] /* Maximum account for this phone has reached. */
                        );
                    }
                }
            }

            

            $status = "Waiting Approval";

            $todayDate = date("Y-m-d H:i:s");

            $maxFName = Setting::$systemSetting['maxFullnameLength'];
            $minFName = Setting::$systemSetting['minFullnameLength'];
            $minNricLength = Setting::$systemSetting['Min NRIC Length'];
            $maxNricLength = Setting::$systemSetting['Max NRIC Length'];

            if(empty($clientID)) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Member.", 'data' => "");
            }

            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id'    => 'nameError',
                    'msg'   => $translations["E00296"][$language] /* Please insert full name */
                );
            } else {
                if (strlen($name) < $minFName || strlen($name) > $maxFName) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                    );
                }
            }

            if (empty($country) || !ctype_digit($country)) {
                $errorFieldArr[] = array(
                    'id' => 'countryError',
                    'msg' => $translations["E00303"][$language] /* Please select country */
                );
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id'    => 'addressError',
                    'msg'   => $translations["E00943"][$language] /* Please Insert Address */
                );
            }

            $validDocumentType = array(
                "nric" => 2,
                "passport" => 1
            );

            if(empty($documentType)) {
                $errorFieldArr[] = array(
                    'id'    => 'documentTypeError',
                    'msg'   => $translations["E00769"][$language] /* Please select document type. */
                );
            }else{
                if(!$validDocumentType[$documentType]) {
                    $errorFieldArr[] = array(
                        'id'    => 'documentTypeError',
                        'msg'   => $translations["E00770"][$language]
                    );
                }
            }

            $documentTypeDisplayAry = array(
                "nric" => $translations["B00252"][$language],
                "passport" => $translations["B00253"][$language],
            );

            if($validDocumentType[$documentType]) {
                if(empty($nric)) {
                    $errorMsg = str_replace("%%documentType%%", $documentTypeDisplayAry[$documentType], $translations["E00767"][$language]);
                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errorMsg /* Please insert %%documentType%% number. */
                    );
                } else {
                    if(strlen($nric) < $minNricLength || strlen($nric) > $maxNricLength){
                        $errorMsg = str_replace(array("%%min%%","%%max%%","%%documentType%%"), array($minNricLength,$maxNricLength,$documentTypeDisplayAry[$documentType]), $translations["E00771"][$language]);
                        $errorFieldArr[] = array(
                            'id'    => 'nricError',
                            'msg'   => $errorMsg,
                        );
                    }else{
                        //check image
                        // $imageCount = 0;
                        // if(count($imageData) != $validDocumentType[$documentType]){
                        //     $errorFieldArr[] = array(
                        //                             'id'    => 'imageFile1Error',
                        //                             'msg'   => $translations["E00775"][$language]
                        //                         );
                        // }
                    }
                }
            }

            // if(empty($selfImageData)) {
            //     $errorFieldArr[] = array(
            //         'id'    => 'selfImageError',
            //         'msg'   => $translations["E00775"][$language]
            //     );
            // } else {
            //     if (empty($selfImageData['imageType']) || empty($selfImageData['imageName'])) {
            //         $errorFieldArr[] = array(
            //             'id'    => 'selfImageError',
            //             'msg'   => $translations["E00775"][$language]
            //         );
            //     } else {
            //         $explodeMime = explode("/", $selfImageData['imageType']);
            //         $fileType    = $explodeMime[0];

            //         if($fileType != "image"){
            //             $errorFieldArr[] = array(
            //                 'id'    => 'selfImageError',
            //                 'msg'   => $translations["E00777"][$language]
            //             );
            //         }
            //     }
            // }
            
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // check nric, kyc not allow multiple same nric
            $db->where("type", $documentType);
            $db->where('status', array('Approved','Waiting Approval'), 'IN');
            $db->where("nric", $nric);
            $nricCountRes = $db->get("mlm_kyc", null, "dial_code, phone");
            if($nricCountRes) {
                foreach ($nricCountRes as $key => $value) {
                    if($value['dial_code'] == $dialCode && $value['phone'] == $phone){
                        continue;
                    }
                    if($documentType == 'passport') {
                        $errMsg = $translations["E00881"][$language];
                    } else if($documentType == 'nric') {
                        $errMsg = $translations["E00772"][$language];
                    }

                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errMsg
                    );
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                }
            }
            
            $db->where("client_id", $clientID);
            $db->where("status", array("Waiting Approval","Approved"), "IN");
            $approvedKycCount = $db->getValue("mlm_kyc", "count(id)");
            if($approvedKycCount > 0){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00779"][$language] /* Your KYC is already approved. */, 'data'=> $data);
            }

            $insertData = array(
                "client_id" => $clientID,
                "name" => $name,
                "nric" => $nric,
                "type" => $documentType,
                "dial_code" => $dialCode,
                "phone" => $phone,
                "address" => $address,
                "country_id" => $country,
                "status" => $status,
                "created_at" => $todayDate,
                "updated_at" => $todayDate,
            );

            $db->insert("mlm_kyc",$insertData);

            // if($step == 2){
            //     $groupCode = General::generateUniqueChar("mlm_kyc",array("image_1","image_2","self_image"));

            //     //insertData
            //     foreach ($imageData as $key => $uploadData) {
            //         $key += 1;
            //         unset($insertData);
            //         $fileType = end(explode(".", $uploadData["imageName"]));
            //         $uploadFileName = time()."_".General::generateUniqueChar("mlm_kyc",array("image_1","image_2"))."_".$groupCode.".".$fileType;

            //         $imageAry['image_'.$key] = $uploadFileName;
            //         $returnData['imageName'][] = $uploadFileName;
            //     }

            //     unset($fileType);
            //     unset($uploadFileName);
            //     //Self Image Data
            //     $fileType = end(explode(".", $selfImageData["imageName"]));
            //     $uploadFileName = time()."_".General::generateUniqueChar("mlm_kyc",array("image_1","image_2","self_image"))."_".$groupCode.".".$fileType;

            //     $selfImage = $uploadFileName;

            //     if($selfImage){
            //         $returnData['imageName'][] = $selfImage;
            //     }

            //     $insertData = array(
            //                             "client_id" => $clientID,
            //                             "name" => $name,
            //                             "nric" => $nric,
            //                             "type" => $documentType,
            //                             "image_1" => $imageAry["image_1"],
            //                             "image_2" => $imageAry["image_2"],
            //                             "self_image" => $selfImage,
            //                             "status" => $status,
            //                             "created_at" => $todayDate,
            //                             "updated_at" => $todayDate,
            //                         );

            //     $db->insert("mlm_kyc",$insertData);

            //     $returnData["doRegion"]     = Setting::$configArray["doRegion"];
            //     $returnData["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            //     $returnData["doAccessKey"]  = Setting::$configArray["doApiKey"];
            //     $returnData["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            //     $returnData["doBucketName"] = Setting::$configArray["doBucketName"]."/kyc";
            //     $returnData["doProjectName"]= Setting::$configArray["doProjectName"];
            //     $returnData["doFolderName"] = Setting::$configArray["doFolderName"];

            //     return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00251"][$language], 'data'=> $returnData);
            // }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00251"][$language] /* Successfully Submitted KYC. */, 'data'=> "");
        }

        public function adminEditKYC($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $maxAccPP = Setting::$systemSetting['maxAccPerPhone'];

            $kycID  = trim($params["kycID"]);
            $name   = trim($params["name"]);
            $nric   = trim($params["nric"]);
            $documentType   = trim($params["documentType"]);
            $country        = trim($params["country"]);
            $address        = trim($params["address"]);
            $phone          = trim($params["phone"]);

            // $step = trim($params["step"]);
            // $imageData = $params['imageData'];
            // $selfImageData = $params['selfImageData'];

            $userID = $db->userID;
            $site = $db->userType;
            if($site!="Admin"){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Action.", 'data' => "");
            }

            $db->where("id", $country);
            $dialCode = $db->getValue("country", "country_code");

            if (empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                );
            } else {
                if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00858"][$language] /* Only number is allowed */
                    );
                }

                // check max account per phone
                $db->where("dial_code", $dialCode);
                $db->where("phone", $phone);
                $totalAccThisPhone = $db->getValue("mlm_kyc", "COUNT(*)");
                if (!empty($totalAccThisPhone)) {
                    if($totalAccThisPhone>=$maxAccPP){
                        $errorFieldArr[] = array(
                            'id' => 'phoneError',
                            'msg' => $translations["E00994"][$language] /* Maximum account for this phone has reached. */
                        );
                    }
                }
            }

            $status = "Approved";

            $todayDate = date("Y-m-d H:i:s");

            $maxFName = Setting::$systemSetting['maxFullnameLength'];
            $minFName = Setting::$systemSetting['minFullnameLength'];
            $minNricLength = Setting::$systemSetting['Min NRIC Length'];
            $maxNricLength = Setting::$systemSetting['Max NRIC Length'];

            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id'    => 'nameError',
                    'msg'   => $translations["E00296"][$language] /* Please insert full name */
                );
            } else {
                if (strlen($name) < $minFName || strlen($name) > $maxFName) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                    );
                }
            }

            if (empty($country) || !ctype_digit($country)) {
                $errorFieldArr[] = array(
                    'id' => 'countryError',
                    'msg' => $translations["E00303"][$language] /* Please select country */
                );
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id'    => 'addressError',
                    'msg'   => $translations["E00943"][$language] /* Please Insert Address */
                );
            }

            $validDocumentType = array(
                "nric" => 2,
                "passport" => 1
            );

            if(empty($documentType)) {
                $errorFieldArr[] = array(
                    'id'    => 'documentTypeError',
                    'msg'   => $translations["E00769"][$language] /* Please select document type. */
                );
            }else{
                if(!$validDocumentType[$documentType]) {
                    $errorFieldArr[] = array(
                        'id'    => 'documentTypeError',
                        'msg'   => $translations["E00770"][$language]
                    );
                }
            }

            $documentTypeDisplayAry = array(
                "nric" => $translations["B00252"][$language],
                "passport" => $translations["B00253"][$language],
            );

            if($validDocumentType[$documentType]) {
                if(empty($nric)) {
                    $errorMsg = str_replace("%%documentType%%", $documentTypeDisplayAry[$documentType], $translations["E00767"][$language]);
                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errorMsg /* Please insert %%documentType%% number. */
                    );
                } else {
                    if(strlen($nric) < $minNricLength || strlen($nric) > $maxNricLength){
                        $errorMsg = str_replace(array("%%min%%","%%max%%","%%documentType%%"), array($minNricLength,$maxNricLength,$documentTypeDisplayAry[$documentType]), $translations["E00771"][$language]);
                        $errorFieldArr[] = array(
                            'id'    => 'nricError',
                            'msg'   => $errorMsg,
                        );
                    }else{
                    }
                }
            }
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // check nric, kyc not allow multiple same nric
            $db->where("type", $documentType);
            $db->where('status', array('Approved','Waiting Approval'), 'IN');
            $db->where("nric", $nric);
            $nricCountRes = $db->get("mlm_kyc", null, "dial_code, phone");
            if($nricCountRes) {
                foreach ($nricCountRes as $key => $value) {
                    if($value['dial_code'] == $dialCode && $value['phone'] == $phone){
                        continue;
                    }
                    if($documentType == 'passport') {
                        $errMsg = $translations["E00881"][$language];
                    } else if($documentType == 'nric') {
                        $errMsg = $translations["E00772"][$language];
                    }

                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errMsg
                    );
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                }
            }

            $db->where("id", $kycID);
            $kycCount = $db->getValue("mlm_kyc", "client_id");
            if(!$kycCount){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00996"][$language] /* KYC not found. */, 'data'=> $data);
            }

            $updateData = array(
                // "client_id" => $clientID,
                "name" => $name,
                "nric" => $nric,
                "type" => $documentType,
                "dial_code" => $dialCode,
                "phone" => $phone,
                "address" => $address,
                "country_id" => $country,
                "status" => $status,
                "created_at" => $todayDate,
                "updated_at" => $todayDate,
                "approved_at" => $todayDate,
                "updater_id" => $userID,
            );

            $db->where("id", $kycID);
            $db->update("mlm_kyc",$updateData);

            $db->where("id", $kycCount);
            $db->update("client", array("name" => $name, "dial_code" => $dialCode,"phone" => $phone));
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00251"][$language] /* Successfully Submitted KYC. */, 'data'=> "");
        }

        public function getKYCDetails($params){
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];

            $db->where('id', $clientID);
            $member = $db->getOne('client', 'member_id, passport, identity_number');
            if(!$member){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Member", 'data' => "");
            }

            $memberID = $member['member_id'];
            $passport = $member['passport'];
            $identityNumber = $member['identity_number'];
            $idType = $identityNumber > 0 ? "nric" : "passport";

            $temp['Bank Account Cover']['record'] = 0;
            $temp['NPWP Verification']['record'] = 0;
            $temp['ID Verification']['record'] = 0;


            $db->where("client_id", $clientID);
            $db->orderBy("id", 'ASC');
            $kycAry = $db->get("mlm_kyc", NULL, "id, doc_type, remark, status");

            if (empty($kycAry)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
            }


            foreach ($kycAry as $kyc) {
                $kycIDAry[$kyc['doc_type']] = $kyc['id'];
                $kycIDStatus[$kyc['id']]['status'] = $kyc['status'];
                $kycIDStatus[$kyc['id']]['remark'] = $kyc['remark'];
            }

            if($kycIDAry){
                $db->where('kyc_id', array_values($kycIDAry), 'IN');
                $kycDetails = $db->get('mlm_kyc_detail', null, "id, kyc_id, name, value, description");
            }

            foreach ($kycIDAry as $docType => $kycID) {
                if($docType == 'ID Verification'){
                    $temp[$docType]['idType']  = General::getTranslationByName($idType);
                }
                foreach($kycDetails as $value){     
                    if($value['kyc_id'] == $kycID){ 
                        $temp[$docType][$value['name']] = $value['value'];
                        if($value['name'] == 'Address'){   
                           $temp[$docType][$value['name']] = $value['description'];
                        }
                    }
                }
                $temp[$docType]['kycID']  = $kycID;
                $temp[$docType]['status'] = $kycIDStatus[$kycID]['status'];
                $temp[$docType]['remark'] = $kycIDStatus[$kycID]['remark'];
                $temp[$docType]['memberID'] = $memberID;
                $temp[$docType]['record'] = 1;
            }

            $db->where('kyc_id', array_values($kycIDAry), 'IN');
            $db->where('name', 'notificationCount');
            $db->update('mlm_kyc_detail', array('value' => '0'));

            $data = $temp;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getKYCDataByID($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $kycID = trim($params['kycID']);
            $statusDisplayAry = array(
                "Waiting Approval" => $translations["B00254"][$language],
                "New" => $translations["B00255"][$language],
                "Approved" => $translations["B00256"][$language],
                "Rejected" => $translations["B00259"][$language],
            );

            $genderDisplayAry = array(
                "male" => $translations["B00257"][$language],
                "female" => $translations["B00258"][$language],
            );

            $documentTypeDisplayAry = array(
                "nric" => $translations["B00252"][$language],
                "passport" => $translations["B00253"][$language],
            );

            if(empty($kycID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid KYC", 'data' => "");

            $db->where("id",$kycID);
            $kycRow = $db->getOne('mlm_kyc', 'id, client_id, name, phone, address, nric, country_id, type, image_1 as image1, image_2 as image2, self_image as selfImage, status, created_at, updated_at, approved_at, updated_at, updater_id, remark');

            if(empty($kycRow)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid KYC", 'data' => "");
            }

            if($kycRow["client_id"]){
                $db->where("id",$kycRow["client_id"]);
                $clientRow = $db->getOne("client","id, member_id, username, name");
                $kycData["memberID"] = $clientRow["member_id"];
                $kycData["username"] = $clientRow["username"];
                $kycData["fullName"] = $clientRow["name"];
            }

            if($kycRow["updater_id"]){
                $db->where("id",$kycRow["updater_id"]);
                $adminRow = $db->getOne("admin","id, username");
                $kycData["updaterUsername"] = $adminRow["username"];
            }

            if($kycRow["image1"]){
                $kycData["imageData1"] = $kycRow["image1"];
            }

            if($kycRow["image2"]){
                $kycData["imageData2"] = $kycRow["image2"];
            }

            if($kycRow["selfImage"]){
                $kycData["selfImage"] = $kycRow["selfImage"];
            }

            if($kycRow["country_id"]){
                $db->where("id",$kycRow["country_id"]);
                $countryRow = $db->getOne("country","id,name,translation_code");
                $kycData["countryDisplay"] = $translations[$countryRow["translation_code"]][$language];
            }

            $kycData["accountHolderName"] = $kycRow["name"];
            $kycData["address"] = $kycRow["address"];
            $kycData["nric"] = $kycRow["nric"];
            $kycData["documentTypeDisplay"] = $documentTypeDisplayAry[$kycRow["type"]];
            $kycData["status"] = $statusDisplayAry[$kycRow["status"]];
            $kycData["createdAt"] = $kycRow["created_at"];
            $kycData["updatedAt"] = $kycRow["updated_at"];
            $kycData["approvedAt"] = strtotime($kycRow["approved_at"]) > 0 ? $kycRow["approved_at"] : "-";
            $kycData["remark"] = $kycRow["remark"];

            $data["kycData"] = $kycData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function updateKYC($params) { 
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $adminID = $db->userID;

            $kycIDAry = $params['kycIDAry'];
            $remark = trim($params['remark']);
            $status = $params['status'];

            $validStatusAry = array("Waiting Approval", "Approved", "Rejected");

            $todayDate = date("Y-m-d H:i:s");

            if(empty($kycIDAry))
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Nothing was updated', 'data' => "");

            if(!in_array($status, $validStatusAry))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language], 'data' => "");

            foreach($kycIDAry as $kycID){
                $db->where("id",$kycID);
                $kycRow = $db->getOne("mlm_kyc","id, status, client_id, doc_type");

                if(empty($kycRow)){
                    continue;
                }

                if($kycRow["status"] == "Rejected" || $kycRow["status"] == "Approved"){
                    continue;
                }

                if($kycRow["status"] == $status){
                    continue;
                }

                $updateData["status"] = $status;
                $updateData["updated_at"] = $todayDate;
                $updateData["updater_id"] = $adminID;
                $updateData["remark"] = $remark;

                $db->where("id",$kycID);
                $db->update("mlm_kyc",$updateData);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'KYC Status Updated', 'data' => "");
        }

        public function getKYCListing($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? $params['seeAll'] : 0;
            $limit = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "mainLeaderUsername":
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataType = trim($v['dataType']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'username': 
                            if ($dataType == "like") {
                                $db->where('username', '%'.$dataValue.'%', 'LIKE');
                            } else {
                                $db->where('username', $dataValue);
                            }
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $db->where('name', '%'.$dataValue.'%', 'LIKE');
                            } else {
                                $db->where('name', $dataValue);
                            }
                            break;

                        case 'memberID':
                            $db->where("member_id", $dataValue);
                            break;

                        case 'createdAt':
                            // Set db column here
                            $columnName = 'date(created_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                            }
                            $sq = $db->subQuery();
                            $sq->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            $sq->where($columnName, date('Y-m-d', $dateTo), '<=');
                            $sq->get('mlm_kyc', NULL, 'client_id');
                            $db->where('id', $sq, 'IN');
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            // Set db column here
                            $columnName = 'date(updated_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                            }

                            $maxSQ = $db->subQuery();
                            $maxSQ->groupBy('client_id');
                            $maxSQ->groupBy('doc_type');
                            $maxSQ->get('mlm_kyc', NULL, 'MAX(id)');

                            $sq = $db->subQuery();
                            $sq->where('id', $maxSQ, 'IN');
                            $sq->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            $sq->where($columnName, date('Y-m-d', $dateTo), '<=');
                            $sq->get('mlm_kyc', NULL, 'client_id');
                            $db->where('id', $sq, 'IN');
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $statusValue = $dataValue;
                            break;

                        case 'docType':
                            $docTypeValue = $dataValue;
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($statusValue || $docTypeValue){
                $maxSQ = $db->subQuery();
                $maxSQ->groupBy('client_id');
                $maxSQ->groupBy('doc_type');
                $maxSQ->get('mlm_kyc', NULL, 'MAX(id)');

                if($statusValue && $docTypeValue){
                    if($docTypeValue == 'Email Verification'){
                        $nameSq = $db->subQuery();
                        $nameSq->where('id', $maxSQ, 'IN');
                        if($statusValue == 'Approved'){
                            $nameSq->where("email_verified", '1');
                        }else if($statusValue == 'Waiting Approval'){
                            $nameSq->where("email_verified", '0');
                        }else{
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No results found */, 'data' => "");
                        }
                        $nameSq->get('client_detail', NULL, 'client_id');
                        $db->where('id', $nameSq, 'IN');
                    }else{
                        $sq = $db->subQuery();
                        $sq->where('id', $maxSQ, 'IN');
                        $sq->where("doc_type", $docTypeValue);
                        $sq->where("status", $statusValue);
                        $sq->get('mlm_kyc', NULL, 'client_id'); 
                        $db->where('id', $sq, 'IN');
                    }
                }elseif($statusValue){
                    $sq = $db->subQuery();
                    $sq->where('id', $maxSQ, 'IN');
                    $sq->where("status", $statusValue);
                    $sq->get('mlm_kyc', NULL, 'client_id');
                    $db->where('id', $sq, 'IN');
                }elseif($docTypeValue){
                    $sq = $db->subQuery();
                    $sq->where('id', $maxSQ, 'IN');
                    if($docTypeValue == 'Email Verification'){
                        $sq->where("email_verified", '1');
                        $sq->get('client_detail', NULL, 'client_id');                                
                    } else {
                        $sq->where("doc_type", $docTypeValue);
                        $sq->get('mlm_kyc', NULL, 'client_id');
                    }
                    $db->where('id', $sq, 'IN');
                }
            }
            $db->where('type', 'Client');
            $copyDb = $db->copy();
            $db->orderBy('kyc_status', 'DESC');
            $kycClientRes = $db->get('client', $limit, 'id, (SELECT status FROM mlm_kyc WHERE client.id = mlm_kyc.client_id ORDER BY FIELD(status, "Waiting Approval", "Rejected", "Approved") LIMIT 1) AS kyc_status');
            if(!$kycClientRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No results found */, 'data' => "");
            }
            foreach($kycClientRes as $kyc){
                $clientIDAry[$kyc["id"]] = $kyc["id"];
            }

            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, name, member_id");

                $db->where("client_id", $clientIDAry, "IN");
                $clientEmailVerified = $db->map("client_id")->get("client_detail", null, "client_id, email_verified");

                $db->where("client_id", $clientIDAry, "IN");
                $db->orderBy("created_at","ASC");
                $kycDocAry = $db->get("mlm_kyc", NULL, "id, client_id, doc_type, status, updated_at, updater_id, created_at");
                foreach ($kycDocAry as $kycDoc) {
                    $allClientKYCIDs[$kycDoc['id']] = $kycDoc['client_id'];
                    $updaterIDAry[$kyc["updater_id"]] = $kyc["updater_id"];
                    $clientKYCDoc[$kycDoc['client_id']][$kycDoc['doc_type']] = $kycDoc['status'];
                    $kycUpdateDetails[$kycDoc['client_id']]['updated_at'] = $kycDoc['updated_at'];
                    $kycUpdateDetails[$kycDoc['client_id']]['updater_id'] = $kycDoc['updater_id'];
                    if(!$kycUpdateDetails[$kycDoc['client_id']]['created_at']){
                        $kycUpdateDetails[$kycDoc['client_id']]['created_at'] = $kycDoc['created_at'];
                    }
                }

                if($allClientKYCIDs){
                    $db->where('kyc_id', array_keys($allClientKYCIDs), 'IN');
                    $db->where('name', 'notificationCount');
                    $notifyCount = $db->get('mlm_kyc_detail', NULL, 'kyc_id, value');
                    foreach ($notifyCount as $kycNofity) {
                        $clientIDNotify[$allClientKYCIDs[$kycNofity['kyc_id']]] += $kycNofity['value'];
                    }
                }
            }

            if($updaterIDAry) {
                $db->where("id", $updaterIDAry, "IN");
                $adminDataAry = $db->map("id")->get("admin", null, "id, username");
            }

            foreach($kycClientRes as $kycRow) {
                unset($temp);

                $temp["ID Verification"]     = '-';
                $temp["Bank Account Cover"]  = '-';
                $temp["NPWP Verification"]   = '-';
                $memberKYCDoc = $clientKYCDoc[$kycRow["id"]];
                foreach ($memberKYCDoc as $docType => $verifyStatus) {
                    $temp[$docType] = $verifyStatus;
                }

                $temp["clientID"] = $kycRow["id"];
                $temp["memberID"] = $clientDataAry[$kycRow["id"]]["member_id"];
                $temp["username"] = $clientDataAry[$kycRow["id"]]["username"];
                $temp["name"]     = $clientDataAry[$kycRow["id"]]["name"];
                $temp["emailVerified"] = $clientEmailVerified[$kycRow["id"]] ? 'Approved' : '-';

                $updateAt       = $kycUpdateDetails[$kycRow["id"]]['updated_at'];
                $updatedID      = $kycUpdateDetails[$kycRow["id"]]['updater_id'];
                $createdAt      = $kycUpdateDetails[$kycRow["id"]]['created_at'];
                if($updateAt){
                    $temp["updatedAt"] = $updateAt != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($updateAt)) : "-";    
                } else {
                    $temp["updatedAt"] = '-';
                }

                if($createdAt){
                    $temp["createdAt"] = $createdAt != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($createdAt)) : "-";
                } else {
                    $temp["createdAt"] = '-';
                }
                
                $temp["updaterID"] = $adminDataAry[$updatedID] ?: '-';
                $temp["unreadCount"] = $clientIDNotify[$kycRow['id']] > 0 ? $clientIDNotify[$kycRow['id']] : '0';
                
                $kycList[] = $temp;
            }
            
            $totalRecord = $copyDb->getValue('client', 'count(id)');
            $data['kycList'] = $kycList;
            $data['pageNumber'] = $pageNumber;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['totalRecord'] = $totalRecord;

            $db->where('admin_id',$userID);
            $db->where('type', "kyc");
            $db->update('admin_notification',array("notification_count" => 0));

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getImageByID($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $imageID = $params['imageID'];

            $db->where('id', $imageID);
            $imageBase64 = $db->getValue('uploads', 'data');
            $data['imageBase64'] = $imageBase64;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        // --- Bank Start --// -- need tune

        public function getAvailableCreditWalletAddress($site){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $cryptoCreditList = Wallet::getCryptoCredit(false, false);
            // $acceptCoinType = json_decode(Setting::$systemSetting['acceptCoinType']);
            $data['creditList'] = $cryptoCreditList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function addBankAccountDetailVerification($params) {
            $db = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;
            $clientID = $db->userID;
            $site = $db->userType;
            $dateTime = date("Y-m-d H:i:s");

            if(!$clientID || $site == 'Admin'){
                $clientID      = $params['clientID'];
            }

            $accountHolder = $params['accountHolder'];
            $bankID        = $params['bankID'];
            $accountNo     = $params['accountNo'];
            $province      = $params['province'];
            $bankCity      = $params['bankCity'];
            $branch        = $params['branch'];
            $tPassword     = $params['tPassword'];

            if (empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00461"][$language] /* Member not found. */, 'data'=> "");

            if (empty($accountHolder))
                $errorFieldArr[] = array(
                                            'id'  => "accHolderNameError",
                                            'msg' => $translations["E00462"][$language] /* Please enter account holder name. */
                                        );
            if (empty($bankID)){
                $errorFieldArr[] = array(
                                            'id'  => "bankTypeError",
                                            'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                        );
            }else{
                $db->where('id',$bankID);
                $db->where('status','Active');
                $checkBank = $db->getValue('mlm_bank','id');
                if (empty($checkBank))
                    $errorFieldArr[] = array(
                                            'id'  => "bankTypeError",
                                            'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                        );
            }

            if (empty($accountNo))
                $errorFieldArr[] = array(
                                            'id'  => "accountNoError",
                                            'msg' => $translations["E00464"][$language] /* Please enter account number. */
                                        );
            // if (empty($province))
            //     $errorFieldArr[] = array(
            //                                 'id'  => "provinceError",
            //                                 'msg' => $translations["E00465"][$language] /* Please enter province. */
            //                             );

            if (empty($bankCity))
                $errorFieldArr[] = array(
                                            'id'  => "bankCityError",
                                            'msg' => $translations["E01033"][$language] /* Please enter province. */
                                        );
            if (empty($branch))
                $errorFieldArr[] = array(
                                            'id'  => "branchError",
                                            'msg' => $translations["E00466"][$language] /* Please enter branch. */
                                        );
            /*This project did not check Transaction Password*/
            // if($site != 'Admin'){
            //     /* check transaction password */
            //     if (empty($tPassword)){
            //         $errorFieldArr[] = array(
            //                                     'id'  => "tPasswordError",
            //                                     'msg' => $translations["E00128"][$language] /* Please enter transaction password. */
            //                                 );

            //     } else {
            //         $tPasswordResult = Self::verifyTransactionPassword($clientID, $tPassword);
            //         if($tPasswordResult['status'] != "ok") {
            //             $errorFieldArr[] = array(
            //                                         'id'  => 'tPasswordError',
            //                                         'msg' => $translations["E00468"][$language] /* Invalid password. */
            //                                     );
            //         }
            //     }
            //     /* END check transaction password */
            // }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => '');
        }

        public function addBankAccountDetail($params) {
            $db = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;
            $clientID = $db->userID;
            $site = $db->userType;
            $dateTime = date("Y-m-d H:i:s");

            if(!$clientID || $site == 'Admin'){
                $clientID      = $params['clientID'];
            }

            $accountHolder = $params['accountHolder'];
            $bankID        = $params['bankID'];
            $accountNo     = $params['accountNo'];
            $province      = $params['province'];
            $bankCity      = $params['bankCity'];
            $branch        = $params['branch'];
            $tPassword     = $params['tPassword'];

            $validationResult = self::addBankAccountDetailVerification($params);

            if(strtolower($validationResult['status']) != 'ok'){
                return $validationResult;
            }

            //one bank one active account
            $db->where('bank_id',$bankID);
            $db->where('client_id',$clientID);
            $db->where('status','Active');
            $checkID = $db->getValue('mlm_client_bank','id');
            if ($checkID){
                $db->where('id', $checkID);
                $db->update('mlm_client_bank', array('status' => 'Inactive'));
            }

            $insertClientBankData = array(
                                        "client_id"      => $clientID,
                                        "bank_id"        => $bankID,
                                        "account_no"     => $accountNo,
                                        "account_holder" => $accountHolder,
                                        "created_at"     => $dateTime,
                                        "status"         => 'Active',
                                        "province"       => $province,
                                        "bank_city"      => $bankCity,
                                        "branch"         => $branch

                                     );

            $insertClientBankResult  = $db->insert('mlm_client_bank', $insertClientBankData);
            // Failed to insert client bank account
            if (!$insertClientBankResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00470"][$language] /* Failed to add bank account. */, 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00168"][$language] /* Update successful */, 'data' => $data);
        }

        public function addWalletAddress($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            $userID = $db->userID;
            $site = $db->userType;

            $creditType          = trim($params['creditType']);
            $walletAddress       = trim($params['walletAddress']);
            $transactionPassword = trim($params['transactionPassword']);

            //check transaction password
            if(!$transactionPassword)
                return array("status" => "error", "code" => 1, "statusMsg" => $translations['E00128'][$language] /* Please enter transaction password */ , "data" => "");

            $result = self::verifyTransactionPassword($userID,$transactionPassword);
            if($result['status'] != 'ok'){
                $errorFieldArr= $result['data']['field'];
            }
            
            //check coin types
            $acceptCoinType = Wallet::getCryptoCredit(true, false);
            if(!trim($creditType) || !array_key_exists($creditType, $acceptCoinType)) {
                $errorFieldArr[] = array(
                    'id' => 'creditTypeError',
                    'msg' => $translations['E00747'][$language] /* Please choose a crypto currency */,
                );
            }

            $coinsWithTagAry = array('ripple','eos');
            if (in_array($creditType, $coinsWithTagAry)){
                // new coins that require tag
                $tag = trim($params['tag']);
                if ($tag) {
                    $walletAddress = $walletAddress . ":::ucl:::" . $tag;
                } else {
                    $errorFieldArr[] = array(
                        'id' => 'tagError',
                        'msg' => $translations["E00218"][$language]
                    );
                }
            }

            //check wallet address
            if(!$walletAddress || $walletAddress == "") {
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01941"][$language]/* Wallet Address cannot be empty. */
                );
            } else if (strlen($walletAddress) < 30) {
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                );
            } elseif ($creditType == "tether" && $walletAddress[0] != "0") {
                /* if tether address must start from 0 */
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                );
            } elseif ($creditType == "tronUSDT" && $walletAddress[0] != "T") {
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                );
            }

            // $db->where('info',$walletAddress);
            // $db->where('credit_type',$creditType);
            // $db->where('status','Active');
            // $isExist = $db->has('mlm_client_wallet_address');
            // if ($isExist) {
            //     $errorFieldArr[] = array(
            //         'id' => 'walletAddressError',
            //         'msg' => $translations["E00912"][$language] /* This wallet address is already occupied. */
            //     );
            // }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // $db->where('client_id', $userID);
            // $db->where('credit_type', $creditType);
            // $db->where('status', 'Active');
            // $getExistingWalletAddress = $db->get('mlm_client_wallet_address', NULL, 'id');
            // if($getExistingWalletAddress){
            //     $db->where('client_id', $userID);
            //     $db->where('credit_type', $creditType);
            //     $db->where('status', 'Active');
            //     $db->update('mlm_client_wallet_address', array('status' => 'Inactive'));
            // }

            $db->where('id', $userID);
            $username = $db->getValue('client', 'username');
            $insertData = array(
                "id"                => $db->getNewID(),
                "client_id"         => $userID,
                "credit_type"       => $creditType,
                "info"              => $walletAddress,
                // "type"              => $walletType,
                // "wallet_provider"   => $walletProvider,
                "created_at"        => date("Y-m-d H:i:s"),
                "status"            => 'Active',
                "updater_id"        => $userID,
                "updater_username"  => $username,
            );
            $recordID = $db->insert("mlm_client_wallet_address", $insertData);
            
            // Failed to insert client bank account
            if (!$recordID)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00817"][$language] /* Failed to add wallet address. */, 'data' => "");

            $queueData['checkedIDs'] = array($recordID);
            $queueData['status']       = 'WhiteList';
            $queueData['address']    = $walletAddress;

            $insertQueue = array(
                "queue_type" => "autoWhitelistWalletAddress",
                "client_id"  => $userID,
                "data"       => json_encode($queueData),
                "created_at" => date('Y-m-d H:i:s'),
                "processed"  => 0,
            );
            $db->insert('queue',$insertQueue);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00599"][$language], 'data' => $data);
        }

        public function getAllBankAccountDetail($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            // get bank list
            $db->where('status', "Active");
            $bankDetail   = $db->get("mlm_bank ", $limit, "id, name, translation_code");
            if (empty($bankDetail))
                $bankDetail = '';

            foreach($bankDetail AS &$bankData){
                $bankData['display'] = $translations[$bankData['translation_code']][$language];
            }

            $data['bankDetails']      = $bankDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getMemberBankList($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];
            $creditType = $params['creditType'];

            if(empty($clientID) || empty($creditType))
                return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00391"][$language] /* Failed to load bank list */, 'data' => "");

            $countryID = $db->subQuery();
            $countryID->where('id', $clientID);
            $countryID->get('client', null, 'country_id');

            $db->where('id', $countryID);
            $country = $db->getOne('country', 'id, name');

            $bankIDs = $db->subQuery();
            $bankIDs->where('country_id', $country['id']);
            $bankIDs->get('mlm_bank', null, 'id');

            $db->where('client_id', $clientID);
            $db->where('bank_id', $bankIDs, 'IN');
            $db->where('status', "Active");
            $getBankName = "(SELECT name FROM mlm_bank WHERE mlm_bank.id=bank_id) AS bank_name";
            $banks = $db->get('mlm_client_bank', null, 'bank_id, '.$getBankName.', account_no, account_holder, province, branch');

            $balance = Cash::getBalance($clientID, $creditType);

            $data['balance'] = $balance;
            $data['clientBankList'] = $banks;
            $data['country'] = $country;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getWalletAddressListing($params,$clientID){

            $db = MysqliDb::getInstance();
            
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit           = General::getLimit($pageNumber);

            $site = $db->userType;

            // Means the search params is there
            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);
                            // $downlines[] = $clientID;

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $db->where('client_id', $downlines, "IN");

                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'username':
                            $sq = $db->subQuery();
                            if ($dataType == "like") {
                                $sq->where("username", "%".$dataValue."%", "LIKE");
                            }else{
                                $sq->where("username", $dataValue);
                            }
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN"); 
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN");
                            break;

                        case 'creditType':
                            $db->where('credit_type', $dataValue);
                            break;

                        case 'walletType':
                            $db->where('type', $dataValue); //deposit / withdrawal
                            break;

                        case 'whiteList':

                            if (strtolower($dataValue) =="yes"){
                                $dataValue = 1;
                                $db->where("isWhitelisted",$dataValue);
                            }else if(strtolower($dataValue) == "no"){
                                $dataValue = 0;
                                $db->where("isWhitelisted",$dataValue);
                            }
                
                             break;

                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }
            else{
                //If no searchData, show only Active
                // $db->where('status','Active');
            }
            
            // $db->where('status','Active');
            if($site == 'Member') $db->where('client_id',$clientID);
            $dbSearch=$db->copy();
            $db->orderBy('status', 'ASC');
            $db->orderBy('created_at', 'DESC');
            $result=$db->get('mlm_client_wallet_address',$limit,'id,client_id,credit_type,info,type,wallet_provider,created_at,status, isWhitelisted, error_msg');
            // $creditListDisplay=Self::getCreditDisplay();
            $cryptoCreditListDisplay = Wallet::getCryptoCredit(true);
            foreach ($result as $res) {
                $clientIDAry[$res['client_id']] = $res['client_id'];
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry, 'IN');
                $clientDataAry = $db->map('id')->get('client', NULL, 'id, member_id, username, name');
            }

            foreach ($result as $key => $row) {

                if($row["status"] == "Active") $row["statusDisplay"] = $translations["A00372"][$language];
                else if($row["status"] == "Inactive") $row["statusDisplay"] = $translations["M00330"][$language];
                else $row["statusDisplay"] = "-";

                // $row["creditTypeDisplay"] = $creditListDisplay[$row['credit_type']];
                $row["creditTypeDisplay"] = $cryptoCreditListDisplay[$row['credit_type']];

                // if($row["creditType"] == "bitcoin") $row["creditTypeDisplay"] = $translations["M01898"][$language];
                // else if($row["creditType"] == "ETH") $row["creditTypeDisplay"] = $translations["M01899"][$language];
                // else if($row["creditType"] == "USDT") $row["creditTypeDisplay"] = $translations["M01900"][$language];
                // else $row["statusDisplay"] = "-"; 
                if(!$row['wallet_provider'])
                    $row['wallet_provider'] = '-';

                $row['created_at'] = date("d/m/Y H:i:s", strtotime($row['created_at']));

                if($site == 'Admin'){
                    $row['fullname'] = $clientDataAry[$row['client_id']]['name'] ?: "-";
                    $row['username'] = $clientDataAry[$row['client_id']]['username'] ?: "-";
                    $row['memberID'] = $clientDataAry[$row['client_id']]['member_id'] ?: "-";
                    $row['isWhitelisted'] = $row['isWhitelisted'] ? "Yes" : "No";

                    if($row['isWhitelisted'] == "Yes"){
                        unset($row['error_msg']);
                    }

                } else {
                    unset($row['isWhitelisted']);
                    unset($row['error_msg']);
                }

                $data["dataList"][] = $row;      
            }

            $totalRecord=$dbSearch->getValue('mlm_client_wallet_address','COUNT(id)');
            if ($totalRecord==0){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00733'][$language], 'data' => '');
            }
            $data["totalRecord"] = $totalRecord;
            $data["pageNumber"] = $pageNumber;
            $data['numRecord']   = $limit[1];
            $data["totalPage"] = ceil($totalRecord/$limit[1]);
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function inactiveWalletAddress($params,$clientID){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $addressID = (string)$params['addressID'];
            // $type = (string)$params[''];

            // Set the person who record the changes
            // Client::creatorID = $clientID;
            // Client::creatorUsername = Client::clientData['username'];
            // Client::creatorType = $source;

            $db->where('client_id',$clientID);
            $db->where('id',$addressID);
            $db->where('status','Active');
            $updatedb=$db->copy();
            $verify=$db->getOne('mlm_client_wallet_address',null,'info, credit_type');
            if ($verify){
                $status = "Inactive";
                $updateData = array('status' => "$status");
                $updatedb->update("mlm_client_wallet_address",$updateData);

                $updateData = array("value" => "0", "type"=>"crypto", "reference"=>$creditType);
                // $res = self::updateAutoWithdrawalStatus($updateData,$clientID);

                // Insert activity log
                Activity::insertActivity("Update Wallet Address Status", "Wallet Address Listing",$status,$verify, $clientID);

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00271'][$language]/* Canceled Successfully */, 'data' => $resData);
            }
            else{
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['B00272'][$language]/* Cannot cancel this wallet address */, 'data' => "");
            }
        }

        public function inactiveBankAccount($params,$clientID){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $ID = (string)$params['checkedIDs'];

            $db->where('client_id',$clientID);
            $db->where('id',$ID);
            $db->where('status','Active');
            $updatedb=$db->copy();
            $verify=$db->getOne('mlm_client_bank',null,'account_no, bank_id');
            if ($verify){
                $status = "Inactive";
                $updateData = array('status' => "$status");
                $updatedb->update("mlm_client_bank",$updateData);

                $updateData = array("value" => "0", "type"=>"bank", "reference"=>"0");
                // $res = self::updateAutoWithdrawalStatus($updateData,$clientID);

                // Insert activity log
                Activity::insertActivity("Update Bank Account Status", "Bank Account",$status,$verify, $clientID);

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00414'][$language]/* You successfully deactivate a Bank Account. */, 'data' => $resData);
            }
            else{
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['B00415'][$language]/* Fail to cancel bank account */, 'data' => "");
            }
        }

        public function getWithdrawalListing($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $db->userID;

            // $creditType = $params['creditType'];
            // $clientID = $params['clientID'];
            $searchData = $params['searchData'];

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            
            $res = $db->get("credit",NULL,"type,translation_code");
            foreach($res AS $row){
            	$creditDisplayList[$row['type']] = $translations[$row['translation_code']][$language];
            }
            // $creditDisplayList=Self::getCreditDisplay();
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'creditType':
                            $db->where('credit_type', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'withdrawalType':
                            if($dataValue != "all"){
                                $db->where('withdrawal_type', $dataValue);
                                break;
                            }

                        case 'cryptoType':
                            if($dataValue != "all"){
                                $db->where('crypto_type', $dataValue);
                                break;
                            }

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                if($dateTo == $dateFrom)
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

            // $db->where('credit_type', $creditType);
            $db->where('client_id', $clientID);
            // $getCountryName = "(SELECT name FROM country WHERE country.id=(SELECT country_id FROM mlm_bank WHERE mlm_bank.id=bank_id)) AS country_name";
            // $getAccountHolderName = "(SELECT account_holder FROM mlm_client_bank WHERE mlm_client_bank.client_id=mlm_withdrawal.client_id AND mlm_client_bank.bank_id=mlm_withdrawal.bank_id AND mlm_client_bank.account_no=mlm_withdrawal.account_no) AS account_holder";
            // $getBankName = "(SELECT name FROM mlm_bank WHERE mlm_bank.id=bank_id) AS bank_name";
            // $getProvince = "(SELECT province FROM mlm_client_bank WHERE mlm_client_bank.client_id=mlm_withdrawal.client_id AND mlm_client_bank.bank_id=mlm_withdrawal.bank_id AND mlm_client_bank.account_no=mlm_withdrawal.account_no) AS province";
            // $getCredit = "(SELECT content FROM language_translation WHERE code = (SELECT translation_code FROM credit WHERE name = credit_type) AND language = '".$language."') AS creditDisplay";
            $copyDb = $db->copy();
            $db->orderBy("created_at", "DESC");
            /*
               $column = 'id,
               status,
               (SELECT name FROM country WHERE id = (SELECT country_id FROM client WHERE id = client_id)) AS country,
               (SELECT username FROM client WHERE id = client_id) AS username ,
               (SELECT CONCAT(dial_code, "", phone) FROM client WHERE id = client_id) AS phone,
               (SELECT name FROM client WHERE id = client_id) AS name,
               receivable_amount, 
               charges,  
               amount, 
               walletAddress,
               created_at,
               credit_type'; 
            */
            $column = '
                id,
                amount,
                status,
                remark,
                created_at,
                estimated_date,
                bank_id,
                (SELECT translation_code FROM mlm_bank WHERE id = bank_id) AS bank_name,
                branch,
                bank_city,
                account_no,
                approved_at,
                credit_type,
                crypto_type,
                receivable_amount,
                charges,
                currency_rate,
                converted_amount,
                withdrawal_type,
                walletAddress,
                ref_id
                ';
            $result = $db->get('mlm_withdrawal', $limit, $column);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00150"][$language] /* No results found */, 'data' => "");
            $totalWithdrawal = 0;
            $totalFee = 0;
            $totalDeductible = 0;

            $cryptoCreditListDisplay = Wallet::getCryptoCredit(true);

            $db->where('client_id', $clientID);
            $clientBankAry = $db->map('id')->get("mlm_client_bank", NULL, "id, account_holder");

            foreach($result as $row) {
                foreach($row as $key => $value) {
                    $withdrawal[$key] = $value ? $value : "-";
                }

                $withdrawal['amount'] = Setting::setDecimal($row['amount'], $row['credit_type']);
                $withdrawal['charges'] = Setting::setDecimal($row['charges'], $row['credit_type']);
                $withdrawal['receivable_amount'] = Setting::setDecimal($row['receivable_amount'], $row['credit_type']);
                $withdrawal['currency_rate'] = Setting::setDecimal($row['currency_rate'], $row['credit_type']);
                $withdrawal['converted_amount'] = Setting::setDecimal($row['converted_amount'], $row['credit_type']);

                if($withdrawal['receivable_amount']) $totalWithdrawal += $row['receivable_amount'];
                if($withdrawal['charges']) $totalFee += $row['charges'];
                if($withdrawal['amount']) $totalDeductible += $row['amount'];

                if ($row['approved_at'] == '0000-00-00 00:00:00') $withdrawal['approved_at'] = "-";
                else $withdrawal['approved_at'] = date("d/m/Y H:i:s", strtotime($row['approved_at']));

                $withdrawal['created_at'] = date("d/m/Y H:i:s", strtotime($row['created_at']));

                $withdrawal['crypto_type'] = $row['crypto_type']?$cryptoCreditListDisplay[$row['crypto_type']]:"-";
                $withdrawal['bank_name'] = $row['bank_name']?$translations[$row['bank_name']][$language]:"-";
                $withdrawal['branch'] = $row['branch']? : "-";
                $withdrawal['bank_city'] = $row['bank_city']? :"-";

                $withdrawal['accountHolder'] = $clientBankAry[$row['ref_id']] ? $clientBankAry[$row['ref_id']] : "-";

                $withdrawal['creditDisplay']=$creditDisplayList[$withdrawal['credit_type']];
                switch($row['withdrawal_type']){
                    case "bank":
                        $withdrawal['withdrawal_type'] = General::getTranslationByName($row['withdrawal_type']);
                    break;
                    case "crypto":
                        $withdrawal['withdrawal_type'] = General::getTranslationByName($row['withdrawal_type']);
                    break;
                    default:
                    break;
                }

                switch($row['status']){
                    case "Waiting Approval":
                        $withdrawal['status'] = $translations["M00652"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Approve":
                        $withdrawal['status'] = $translations["M00498"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Reject":
                        $withdrawal['status'] = $translations["A01187"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Cancel":
                        $withdrawal['status'] = $translations["A00660"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Pending":
                        $withdrawal['status'] = $translations["M00500"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    default:
                        $withdrawal['status'] = $row['status'];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                }

                $withdrawalListing[] = $withdrawal;
            }

            $totalRecord = $copyDb->getValue("mlm_withdrawal", "COUNT(*)");
            $data['withdrawalListing'] = $withdrawalListing;
            $data['totalWithdrawal'] = $totalWithdrawal;
            $data['totalFee'] = $totalFee;
            $data['totalDeductible'] = $totalDeductible;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getBankAccountList($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            
            $searchData   = $params['searchData'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $usernameSearchType = $params["usernameSearchType"];
            
            //Get the limit.
            $limit        = General::getLimit($pageNumber);
            $adminLeaderAry = Setting::getAdminLeaderAry();
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $db->where('name','isAutoWithdrawal');
            $db->where('value','1');
            $db->where('type','bank');
            $autoBankIdRes = $db->get('client_setting',null,'reference,client_id');
            foreach ($autoBankIdRes as $autoBankIdKey => $autoBankIdValue) {
                $autoBankIdAry[$autoBankIdValue['client_id']] = $autoBankIdValue['reference'];
            }

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'username':
                            // $clientID = $db->subQuery();
                            // $clientID->where('username', $dataValue);
                            // $clientID->getOne("client", "id");
                            // $db->where("client_id", $clientID); 
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'name':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" . $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;

                        case 'accHolderName':
                            $db->where('account_holder', $dataValue);
                            break;
                            
                        case 'typeBank':
                            $langCode = $db->subQuery();
                            $langCode->where('content', $dataValue);
                            $langCode->where('site', "System");
                            $langCode->getOne('language_translation', "code");
                            $bankID = $db->subQuery();
                            $bankID->where('translation_code', $langCode);
                            $bankID->getOne('mlm_bank', "id");
                            $db->where('bank_id', $bankID);  
                            break;
                            
                        case 'status':
                            if ($dataValue == 0) {
                                $db->where('status', "Active");
                            } elseif ($dataValue == 1) {
                               $db->where('status', "Inactive");
                            }
                            break;
                            
                        case 'branch':
                            $db->where('branch', $dataValue);
                            break;
                            
                        case 'province':
                            $db->where('province', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            if (Cash::$creatorType == "Member"){
                // $memberID = $params['memberId'];
                $clientID=Cash::$creatorID;

                $db->where('id', $clientID);
                $memberDetail = $db->getOne('client', "name, username");
                $clientDetail['id'] = $clientID;
                $clientDetail['name'] = $memberDetail['name'];
                $clientDetail['username'] = $memberDetail['username'];
                $data['clientDetails'] = $clientDetail;

                $db->where("id", $clientID);
                $countryID = $db->getValue("client", "country_id");
                $db->where("id",$countryID);
                $countryName = $db->getValue("country", "name");
                if ($countryName!="China" && $countryName!="Thailand") {
                    $data['invalidAddBank'] = 1;
                }

                $db->where('client_id', $clientID);

            }
            
            if($adminLeaderAry){
                $db->where('client_id', $adminLeaderAry, 'IN');
            }

            $db->where("status", array('Deleted'), "NOT IN");
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");

            $getUsername  = '(SELECT username FROM client WHERE mlm_client_bank.client_id = client.id) as username';
            $getMemberID  = '(SELECT member_id FROM client WHERE mlm_client_bank.client_id = client.id) as member_id';
            $getFullName  = '(SELECT name FROM client WHERE mlm_client_bank.client_id = client.id) as fullName';
            $getBankName  = '(SELECT name FROM mlm_bank WHERE mlm_client_bank.bank_id = mlm_bank.id) as bank_name';
            $getBankName  = '(SELECT translation_code FROM mlm_bank WHERE mlm_client_bank.bank_id = mlm_bank.id) as langCode';

            $result = $db->get("mlm_client_bank ", $limit, $getUsername. "," .$getMemberID. "," .$getFullName. "," .$getBankName. ", id, client_id, account_no, account_holder as accountHolder, province, branch, status, bank_id, created_at");

            $totalRecord = $copyDb->getValue ("mlm_client_bank", "count(*)");

            if(!empty($result)) {
                foreach($result as $value) {
                    $bankAcc['id']            = $value['id'];

                    if($autoBankIdAry[$value['client_id']] == $value['bank_id'] && $value['status'] == 'Active'){
                        $bankAcc['isAutoWithdrawalBank'] = $translations['A00768'][$language];
                    }else{
                        $bankAcc['isAutoWithdrawalBank'] = $translations['A00605'][$language];
                    }

                    $bankAcc['createdAt']     = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";
                    $bankAcc['bankName']      = $translations[$value['langCode']][$language];
                    $bankAcc['memberID']      = $value['member_id'];
                    $bankAcc['fullName']      = $value['fullName'];
                    if(Cash::$creatorType){
                        $bankAcc['username']      = $value['username'];
                    }
                    $bankAcc['accountHolder'] = $value['accountHolder'];
                    $bankAcc['accountNo']     = $value['account_no'];
                    $bankAcc['province']      = $value['province'];
                    $bankAcc['branch']        = $value['branch'];
                    $bankAcc['status']        = $value['status'] == "Active" ? $translations["A00372"][$language] : $translations["A00373"][$language];
                    $bankAcc['statusDisplay'] = General::getTranslationByName($value['status']);

                    $bankAccList[] = $bankAcc;
                }

            $data['bankAccList'] = $bankAccList ? $bankAccList : "";
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00151"][$language] /* No results found */, 'data' => $data);
            }
        }

        public function updateBankAccStatus($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            if(empty($params['checkedIDs']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00395"][$language] /* No check box selected. */, 'data' => "");

            if(empty($params['status']) || ($params['status'] != "Inactive" && $params['status'] != "Deleted"))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid status. */, 'data' => "");

            $form = array(
                'status' => $params['status']
            );
            $db->where('id', $params['checkedIDs'], 'in');
            $db->update('mlm_client_bank', $form);

            // get admin username
            $adminID = Cash::$creatorID;
            $db->where("id", $adminID);
            $adminUsername = $db->getValue("admin", "username");

            // get member username
            $db->where('id', $params['checkedIDs'], 'in');
            $allClientUsernameRes = $db->get('mlm_client_bank', null, "(SELECT username FROM client WHERE id = client_id) AS username");

            foreach ($allClientUsernameRes as $key => $value) {
                $tempClientUsernameList[] = $value['username'];
            }

            $clientUsername = implode(",", $tempClientUsernameList);
            $activityData = array('admin' => $adminUsername,'client'=>$clientUsername);
            $activityRes = Activity::insertActivity('Update Bank Account Status', 'T00017', 'L00028', $activityData);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => "");
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function updateWalletAddressStatus($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $checkedIDs = $params['checkedIDs'];

            if(empty($params['checkedIDs']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00395"][$language] /* No check box selected. */, 'data' => "");

            switch ($params['status']) {
                case 'Inactive':
                case 'Deleted':
                    $form = array(
                        'status' => $params['status']
                    );
                    $db->where('id', $params['checkedIDs'], 'in');
                    $db->update('mlm_client_wallet_address', $form);

                    $activityTitle = 'Update Wallet Address Status';
                    $activityDescriptionLang = 'L00029';
                    $activityTitleLang = 'T00018';
                    break;

                case 'WhiteList':
                    $db->where('id', $checkedIDs, "IN");
                    $walletAddressAry = $db->get('mlm_client_wallet_address',null, 'id, status, credit_type, info, isWhitelisted');

                    foreach ($walletAddressAry as $walletAddres) {  
                        if($walletAddres['status'] == "Inactive"){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Inactive Wallet Address Cannot Select for Whitelist', 'data' => "");
                        }

                        if($walletAddres['isWhitelisted'] == 1){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Selected Addresses have Whiteliested Address ', 'data' => "");
                        }

                        if($walletAddres['status'] == "Active" && $walletAddres['isWhitelisted'] == 0) {
                            $address['address']     = $walletAddres['info'];
                            $address['wallet_type'] = CryptoPG::getCryptoConverter($walletAddres['credit_type'])['theNuxPrefix'];
                        }

                        
                        $addressInfo[$walletAddres['info']] = $address;
                    }

                    //send request to the Nux Pay
                    $postParams = array(
                                        "account_id"=> Setting::$configArray['theNuxWalletBusinessID'],
                                        "api_key"    => Setting::$configArray['nuxPayWhiteListApiKey'],
                                        "address"    => array_values($addressInfo),
                                    );

                    $postParams = json_encode($postParams);
                    $url = Setting::$configArray['nuxPayAPIDomain']."/whitelist/address/multi";

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams); // $response->setBody()
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    $jsonResponse = curl_exec($ch);
                    $whitelistResponse = json_decode($jsonResponse, 1);

                    if($whitelistResponse['message'] == "Success"){
                        $whitelistData = $whitelistResponse['data'];

                        foreach ($whitelistData as $whitelist) {                            
                            if($whitelist['status'] == "Success") {
                                $successWhitelist[$whitelist['address']] = $whitelist['address'];
                            } else {
                                if($whitelist['reason'] == 'Duplicate address detected'){
                                    $updateAddress['isWhitelisted'] = 1;
                                }
                                $updateAddress['error_msg'] = $whitelist['reason'];

                                $db->where('info', $whitelist['address']);
                                $db->where('status', 'Active');
                                $db->update('mlm_client_wallet_address', array('error_msg' => $whitelist['reason']));
                            }
                        }

                        if($successWhitelist){
                            $db->where('info', $successWhitelist, 'IN');
                            $db->where('status', 'Active');
                            $db->update('mlm_client_wallet_address', array('isWhitelisted' => '1'));    
                        }
                    } else {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to WhiteList." , 'data' => $whitelistResponse);
                    }

                    $activityTitle = 'WhiteList Wallet Address';
                    $activityDescriptionLang = 'L00068';
                    $activityTitleLang = 'T00048';
                    break;

                default:
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid status. */, 'data' => "");
                    break;
            }

            // get admin username
            $adminID = Cash::$creatorID;
            if($adminID){
                $db->where("id", $adminID);
                $adminUsername = $db->getValue("admin", "username");

                // get member username
                $db->where('id', $params['checkedIDs'], 'in');
                $allClientUsernameRes = $db->get('mlm_client_wallet_address', null, "(SELECT username FROM client WHERE id = client_id) AS username");

                foreach ($allClientUsernameRes as $key => $value) {
                    $tempClientUsernameList[] = $value['username'];
                }

                $clientUsername = implode(",", $tempClientUsernameList);
                $activityData = array('admin' => $adminUsername,'client'=>$clientUsername);
                $activityRes = Activity::insertActivity($activityTitle, $activityTitleLang, $activityDescriptionLang, $activityData, $adminID);
                // Failed to insert activity
                if(!$activityRes)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => "");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getBankAccountDetail($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $site = $db->userType;
            $clientID = $db->userID;

            if($site == 'Admin'){
                $clientID     = $params['clientID'];
            }

            if (empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            // get member name, username, country_id
            $db->where('id', $clientID);
            $memberDetail = $db->getOne('client', "name, username, country_id");
            if (empty($memberDetail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            $countryCode  = $memberDetail['country_id'];

            $db->where('client_id',$clientID);
            $db->where('status','Active');
            $userBankDetail = $db->getOne('mlm_client_bank','bank_id,account_no,account_holder,branch,bank_city');

            // get bank list
            $db->where('country_id', $countryCode);
            $db->where('status', "Active");
            $db->orderBy('name', "ASC");
            $bankDetail   = $db->get("mlm_bank ", $limit, "id, name, translation_code");
            if (empty($bankDetail))
                $bankDetail = '';

            foreach($bankDetail AS &$bankData){
                $bankData['display'] = $translations[$bankData['translation_code']][$language] ? $translations[$bankData['translation_code']][$language] : $bankData["name"];
            }

            $clientDetail['id']       = $clientID;
            $clientDetail['name']     = $memberDetail['name'];
            $clientDetail['username'] = $memberDetail['username'];
            $clientDetail['hasBank']  = $userBankDetail?1:0;
            $clientDetail['bankID']   = $userBankDetail['bank_id'];
            $clientDetail['accountNo']= $userBankDetail['account_no'];
            $clientDetail['accountHolder']= $userBankDetail['account_holder'];
            $clientDetail['branch']   = $userBankDetail['branch'];
            $clientDetail['bank_city']= $userBankDetail['bank_city'];
            $data['clientDetails']    = $clientDetail;
            $data['bankDetails']      = $bankDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getWithdrawalOptionOfMember($clientID){
            $db = MysqliDb::getInstance();

            $db->where("client_id", $clientID);
            $db->where("name","isAutoWithdrawal");
            $db->where("value",1);
            $isAutoWithdrawalType = $db->getValue('client_setting','type');

            $db->where("id", $clientID);
            $countryID = $db->getValue("client", "country_id");
            $db->where("id",$countryID);
            $countryName = $db->getValue("country", "name");

            if($isAutoWithdrawalType == 'bank'){
                return array("bank");

            }else if($isAutoWithdrawalType == 'crypto'){

                return array("crypto");

            }

            // if($countryName!="China") return array("crypto");

            return array("bank","crypto");
        }

        public function updateAutoWithdrawalStatus($params,$clientID){
            $db = MysqliDb::getInstance();

            $type = $params["type"];
            $reference = $params["reference"];
            $value = $params["value"];

            if(!$type && $type != 0) return false;
            if(!$reference && $reference != 0) return false;
            if(!$value && $value != 0) return false;

            $db->where("client_id", $clientID);
            $db->where("name","isAutoWithdrawal");
            $id = $db->getValue("client_setting","id");

            if($id){

                $updateData = array("value"=>$value,"type"=>$type,"reference"=>$reference);
                $db->where("id",$id);
                $db->update("client_setting",$updateData);

            }else{
                $insertData = array(
                                        "name"=>"isAutoWithdrawal",
                                        "client_id"=>$clientID,
                                        "value"=>$value,
                                        "type"=>$type,
                                        "reference"=>$reference,
                                    );
                $db->insert("client_setting",$insertData);
            }

            return true;
        }
        
        // --- Bank End --//
 
        function adminSearchDownline($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = trim($params["clientID"]);
            $targetUsername = trim($params["targetUsername"]);

            $db->where("username", $targetUsername);
            $targetClientID = $db->getValue("client", "id");

            if (empty($targetClientID)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
            }

            $db->where("client_id", $clientID);
            $clientOwnLevel = $db->getValue("tree_sponsor", "level");

            $downline = Tree::getSponsorDownlineByClientID($clientID);

            if(!in_array($targetClientID, $downline)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00378"][$language] /* Client not found. */, 'data' => '');
            }

            $db->where("client_id", $targetClientID);
            $result = $db->get("tree_sponsor", null, "level,trace_key");

            foreach ($result as $key => $value) {
                $targetClientTraceKey = $value["trace_key"];
                $targetClientOwnLevel = $value["level"];
            }

            $clientArray = explode("/", $targetClientTraceKey);

            foreach ($clientArray as $value1) {
                $db->where("client_id", $value1);
                $level = $db->getValue("tree_sponsor", "level");
                $clientIDwithLevel[$value1] = $level;

            }

            foreach ($clientIDwithLevel as $cID => $kLvl) {
               // if($clientIDwithLevel[$cID] < $clientOwnLevel) continue;
               // if($clientIDwithLevel[$cID] > $targetClientOwnLevel) continue;
               $downlinesData['client_id'] = $cID;
               $downlineClientIDArray[] = $downlinesData;
               $allDownlinesArray[] = $cID;
            }

            $db->where("type", 'Client');
            $allClient = $db->get("client", NULL, "username, id,member_id");

            foreach ($allClient as $key => $value) {
                $allClientArray[$value['id']] = $value['username'];
                $allClientMemberIDArray[$value['id']] = $value['member_id'];

            }

            foreach ($allDownlinesArray as $value) {
                $downlineLineData['username'] = $allClientArray[$value];
                $downlineLineData['level'] = $clientIDwithLevel[$value];
                $downlineLineData['clientID'] = $value;
                $downlineLineData['memberID'] = $allClientMemberIDArray[$value];

                $displayClientArray[] = $downlineLineData;

            }
             // foreach ($allDownlinesArray as $value2) {
                    $allDownlinesResult = Tree::getSponsorTreeDownlines($targetClientID,false);
                    // if (empty($allDownlinesResult)) continue;
                    foreach ($allDownlinesResult as $value3) {
                        $allDownlinesResultArray[$targetClientID][] = $value3;
                    }

            // }
            if (!empty($allDownlinesResultArray)) {

                foreach ($allDownlinesResultArray as $key1 => $value4) {
                            
                    $db->where("portfolio_type",'Package Re-entry');
                    $db->where("client_id" , $allDownlinesResultArray[$key1], "IN");
                    $tsAResult = $db->getValue("mlm_client_portfolio","SUM(bonus_value)");
                    $tsA[$key1] = $tsAResult > 0 ? number_format($tsAResult,2,".","") : number_format(0,2,".","");
                }

                foreach ($allDownlinesResultArray as $key1 => $value4) {

                    $dateToday = date("Y-m-d");
                            
                    $db->where("portfolio_type",'Package Re-entry');
                    $db->where("client_id" , $allDownlinesResultArray[$key1], "IN");
                    $db->where("created_at", $dateToday."%", "LIKE");
                    $dsAResult = $db->getValue("mlm_client_portfolio","SUM(bonus_value)");
                    $dsA[$key1] = $dsAResult > 0 ? number_format($dsAResult,2,".","") : number_format(0,2,".","");
                }
            }

            $db->where("portfolio_type",'Package Re-entry');
            $db->groupBy('client_id');
            $result = $db->get('mlm_client_portfolio portfolio', NULL, 'client_id AS clientID, SUM(bonus_value) AS amount');
            foreach ($result as $value) {
                $totalArray[$value["clientID"]] = $value["amount"];
            }

            if($downlineClientIDArray){

                foreach ($downlineClientIDArray as $k => &$v) {
                    $v['username'] = $allClientArray[$v['client_id']];
                    $v['ownSalesAmount'] = number_format($totalArray[$v['client_id']]?:0,2,".","");
                    $v['totalSalesAmount'] = number_format($tsA[$v['client_id']]?:0,2,".","");
                    $v['dailySalesAmount'] = number_format($dsA[$v['client_id']]?:0,2,".","");
                }
            }


            // foreach ($displayArray as $key => $value) {
            //     $displayClientArray[$allClientArray[$key]] = $value;
            // }
            $memberDetails = Self::getCustomerServiceMemberDetails($targetClientID);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data["treeLink"] = $displayClientArray;
            $data["downlines"] = $downlineClientIDArray;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function countryIPBlock($ip,$clientID){
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $excludedCheckIP=array('127.0.0.1');

            if (!$clientID){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid clientID', 'data' => '');
            }

            if (!$ip){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid IP address', 'data' => '');
            }
            // Grab recorded IP address's country info
            if (!in_array($ip, $excludedCheckIP)){
                $db->where('ip',$ip);
                $IPRecord=$db->getOne('mlm_Ip_Country_Code','ip,countryCode');
                if (!$IPRecord){
                    $returnData=General::ip_info($ip);
                    $country_code=$returnData['country_code'];
                    $db->rawQuery("INSERT INTO `mlm_Ip_Country_Code` (`ip`, `countryCode`, `source`, `createdOn`) SELECT '$ip', '$country_code', '', '".date('Y-m-d H:i:s')."' ");

                    $db->where('ip',$ip);
                    $IPRecord=$db->getOne('mlm_Ip_Country_Code','ip,countryCode');
                }

                //IF country is disabled then stop from login
                // $disabledLoginCountries=json_decode(Setting::$systemSetting['blockMemberLoginByCountryIP']);

                // $db->where('IP_block_login','1');
                // $disabledLoginCountries=$db->map('iso_code2')->arrayBuilder()->get('country',null,'iso_code2');


                $db->where('blocked','1');
                $db->where('client_id',$clientID);
                $disabledLoginCountries=$db->map('country_code')->arrayBuilder()->get('client_country_ip_block',null,'country_code');

                if (in_array($IPRecord['countryCode'], $disabledLoginCountries)){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00754"][$language] /* Invalid Login */, 'data' => '');
                }

            }
            return true;
        }

        function terminatePortfolio($clientID, $params){
            $db = MysqliDb::getInstance();
            $cash = $this->cash;
            $currentTime = date('Y-m-d H:i:s');
            $portfolioId = $params['portfolioIDAry'];

            $totalPortfolioBV = 0;
            $totalPayableAmount = 0;

            $db->where('username','payout');
            $internalID = $db->getValue('client','id');

            $db->where('status','Active');
            $db->where('client_id',$clientID);
            $db->where('id',$portfolioId,'IN');
            $db->where('portfolio_type',array('Credit Reentry','Credit Register'),'IN');
            $portfolioRes = $db->get('mlm_client_portfolio',null,'bonus_value,id,promoBV');
            foreach ($portfolioRes as $portfolioKey => $portfolioValue) {
                $portfolioDetail[] = $portfolioValue;
            }

            if(empty($portfolioDetail)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No portfolio able to redeem.", 'data'=> "");
            }

            foreach ($portfolioDetail as $portfolioKey => $portfolioValue) {

                $db->where('portfolio_id',$portfolioValue['id']);
                // $db->where('paid','1');
                $rebateBonusRes = $db->get('mlm_bonus_rebate',null,'payable_amount');
                foreach ($rebateBonusRes as $rebateBonusKey => $rebateBonusValue) {
                    $totalPayableAmount += $rebateBonusValue['payable_amount'];
                }

                $totalRedeemAmount = $portfolioValue['bonus_value'] - $totalPayableAmount;

                if($totalRedeemAmount < 0){

                    $totalRedeemAmount = 0;

                }

                $batchID = $db->getNewID();

                $cash->insertTAccount($internalID, $clientID, 'voucherRedeem', $totalRedeemAmount, 'Early Redemption', $batchID, '', $currentTime, $batchID, $clientID, "",$portfolioValue['id']);

                $updateData = array(
                    "status" => "Redeemed",
                    "redeemed_at" => $currentTime,
                );

                $db->where('id',$portfolioValue['id']);
                $db->update('mlm_client_portfolio',$updateData);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Redeem Successfully.', 'data'=> '');
        }

        function updateTotalDownline($clientID){
        	$db = MysqliDb::getInstance();

        	$db->where("client_id",$clientID);
        	$traceKey = $db->getValue("tree_sponsor","trace_key");

        	$uplineArray = explode("/", $traceKey);
        	foreach($uplineArray AS $id){
        		if($id == $clientID) continue;
        		$idArr[] = $id;
        	}

        	$insertArr = array(
									"client_id" => $clientID,
									"name" => "totalDownline",
									"value" => 0,
								);
			$db->insert("client_setting",$insertArr);

        	if($idArr){
        		$db->where("client_id",$idArr,"IN");
        		$db->where("name","totalDownline");
        		$db->update("client_setting",array("value"=>$db->inc(1)));

        		$db->where("client_id",$idArr,"IN");
        		$db->where("name","totalDownline");
        		$clientIDArr = $db->getValue("client_setting","client_id",NULL);

        		$diffArr = array_diff($idArr, $clientIDArr);
        		if(!$clientIDArr && !$diffArr) $diffArr = $idArr;

        		if(COUNT($diffArr) > 0){
        			foreach($diffArr AS $client){
        				$insertArr = array(
        										"client_id" => $client,
        										"name" => "totalDownline",
        										"value" => 1,
        									);
        				$db->insert("client_setting",$insertArr);
        			}
        		}
        	}
        	return true;
        }

        function updateTotalIntroducee($clientID){
            $db = MysqliDb::getInstance();

            $db->where("client_id",$clientID);
            $traceKey = $db->getValue("tree_introducer","trace_key");

            $uplineArray = explode("/", $traceKey);
            foreach($uplineArray AS $id){
                if($id == $clientID) continue;
                $idArr[] = $id;
            }

            $insertArr = array(
                                    "client_id" => $clientID,
                                    "name" => "totalIntroducee",
                                    "value" => 0,
                                );
            $db->insert("client_setting",$insertArr);

            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalIntroducee");
                $db->update("client_setting",array("value"=>$db->inc(1)));

                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalIntroducee");
                $clientIDArr = $db->getValue("client_setting","client_id",NULL);

                $diffArr = array_diff($idArr, $clientIDArr);
                if(!$clientIDArr && !$diffArr) $diffArr = $idArr;

                if(COUNT($diffArr) > 0){
                    foreach($diffArr AS $client){
                        $insertArr = array(
                                                "client_id" => $client,
                                                "name" => "totalIntroducee",
                                                "value" => 1,
                                            );
                        $db->insert("client_setting",$insertArr);
                    }
                }
            }
            return true;
        }

        public function memberGetMemoList() {
            $db = MysqliDb::getInstance();

            $id = $db->userID;

            if($id){
                $db->where("id", $id);
                $turnOffPopUpMemo = $db->getValue('client', 'turnOffPopUpMemo');
            }
            $memo = Bulletin::getPopUpMemo($id, $turnOffPopUpMemo);
            $data['memo'] = $memo;

            //get client blocked rights
            $blockedRights = array();
            if($id){
                $column = array(
                    "(SELECT name FROM mlm_client_rights WHERE id = mlm_client_blocked_rights.rights_id) AS blocked_rights"
                );
                $db->where('client_id', $id);
                $result2 = $db->get("mlm_client_blocked_rights", NULL, $column);

                foreach ($result2 as $row){
                    $blockedRights[] = $row['blocked_rights'];
                }
            }
            $data['blockedRights'] = $blockedRights;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getKYCDetailsNew($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientId   = $db->userID;
            $verifyType = $params['verifyType'];
            $db->where('id',$clientId);
            $clientDetails = $db->getOne('client','name, identity_number, passport');

            if(empty($clientDetails)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00105'][$language] /* Invalid User */, 'data' => '');
            }

            $fullName   = $clientDetails['name'];
            if(!empty($clientDetails['identity_number'])){
                $idNumber   = $clientDetails['identity_number'];
                $idType     = 'KTP';
            }else if(!empty($clientDetails['passport'])){
                $idNumber   = $clientDetails['passport'];
                $idType     = 'Passport';
            }

            $db->where('address_type', 'billing');
            $db->where('client_id',$clientId);
            $clientAddress = $db->getOne('address','address, (SELECT name FROM county WHERE id = district_id) AS district, (SELECT name FROM sub_county WHERE id = sub_district_id) AS subDistrict, (SELECT name FROM zip_code WHERE id = post_code) AS zipCode, (SELECT name FROM city WHERE id = city) AS city, (SELECT name FROM state WHERE id = state_id) AS province, (SELECT translation_code FROM country WHERE id = address.country_id) AS countryName');

            if($clientAddress['countryName']){
                $clientAddress['countryName'] = $translations[$clientAddress['countryName']]['english'];;    
            }
            $address = join(', ',$clientAddress);

            $db->where('status','Active');
            $db->where('client_id',$clientId);
            $bankDetail = $db->getOne('mlm_client_bank','bank_id, account_no, account_holder');

            if(!$bankDetail){
                $bankName = "";
            }else{
                $db->where('id', $bankDetail['bank_id']);
                $bankName = $db->getValue('mlm_bank','name');
            }
            $bankAccNo  = $bankDetail['account_no'];
            $bankAccHold= $bankDetail['account_holder'];

            $db->where('client_id',$clientId);
            $npwp = $db->getValue('client_detail','tax_number');

            if($verifyType == 'idVerify'){
                $db->where('doc_type', 'ID Verification');
            }else if($verifyType == 'bankAccVerify'){
                $db->where('doc_type', 'Bank Account Cover');
            }else if($verifyType == 'NPWPVerify'){
                $db->where('doc_type', 'NPWP Verification');
            }

            $db->where('client_id',$clientId);
            $db->orderBy("created_at","DESC");
            $remarkRes = $db->getOne('mlm_kyc','remark, status');

            switch($verifyType){
                case 'idVerify':
                    $data['fullName'] = $fullName;
                    $data['idNum']    = $idNumber;
                    $data['idType']   = $idType;
                    $data['address']  = $address;
                    $data['remarkDetail']  = $remarkRes;
                    break;

                case 'bankAccVerify':
                    $data['bankName']       = $bankName;
                    $data['bankAccNo']      = $bankAccNo;
                    $data['bankAccHolder']  = $bankAccHold;
                    $data['remarkDetail']  = $remarkRes;
                    break;

                case 'NPWPVerify':
                    $data['fullName'] = $fullName;
                    $data['npwpNum']  = $npwp;
                    $data['remarkDetail']  = $remarkRes;
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01061'][$language]/* Invalid verify type */, 'data' => '');
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addKYCValidation($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientId   = $db->userID;
            $verifyType = $params['verifyType'];
            $imgName    = $params['imgName'];

            $db->where('id',$clientId);
            $clientDetails = $db->getOne('client','name, identity_number, passport');
            
            $db->where('address_type', 'billing');
            $db->where('client_id',$clientId);
            $clientAddress = $db->getOne('address','address, (SELECT name FROM county WHERE id = district_id) AS district, (SELECT name FROM sub_county WHERE id = sub_district_id) AS subDistrict, (SELECT name FROM city WHERE id = city) AS city, (SELECT name FROM zip_code WHERE id = post_code) AS zipCode, (SELECT name FROM state WHERE id = state_id) AS province, (SELECT translation_code FROM country WHERE id = address.country_id) AS countryName');

            if($clientAddress['countryName']){
                $clientAddress['countryName'] = $translations[$clientAddress['countryName']]['english'];;    
            }
            
            $fullName   = $clientDetails['name'];
            if(!empty($clientDetails['identity_number'])){
                $idNumber   = $clientDetails['identity_number'];
            }else if(!empty($clientDetails['passport'])){
                $idNumber   = $clientDetails['passport'];
            }
            $address = join(', ',$clientAddress);

            $db->where('status','Active');
            $db->where('client_id',$clientId);
            $bankDetail = $db->getOne('mlm_client_bank','bank_id, account_no, account_holder');
            if($bankDetail){
                $db->where('id', $bankDetail['bank_id']);
                $bankName = $db->getValue('mlm_bank','name');
            }else{
                $bankName = "";
            }
            $bankAccNo  = $bankDetail['account_no'];
            $bankAccHold= $bankDetail['account_holder'];

            $db->where('client_id',$clientId);
            $npwp = $db->getValue('client_detail','tax_number');

            switch($verifyType){
                case 'idVerify':
                    if(!$fullName){
                        $errorFieldArr[] = array(
                            'id'    => 'usernameError',
                            'msg'   => $translations['E01062'][$language] /* Username not found */
                        );
                    }elseif(!$idNumber){
                        $errorFieldArr[] = array(
                            'id'    => 'idError',
                            'msg'   => $translations['E01063'][$language] /* Id not found */
                        );
                    }elseif(!$address){
                        $errorFieldArr[] = array(
                            'id'    => 'addressError',
                            'msg'   => $translations['E01064'][$language] /* Address not found */
                        );
                    }
                    $docType = 'ID Verification';
                    break;

                case 'bankAccVerify':
                    if(!$bankName){
                        $errorFieldArr[] = array(
                            'id'    => 'bankError',
                            'msg'   => $translations['E01065'][$language] /* Bank not found */
                        );
                    }elseif(!$bankAccNo){
                        $errorFieldArr[] = array(
                            'id'    => 'bankAccError',
                            'msg'   => $translations['E01066'][$language] /* Bank account not found */
                        );
                    }elseif(!$bankAccHold){
                        $errorFieldArr[] = array(
                            'id'    => 'bankAccHolderError',
                            'msg'   => $translations['E01067'][$language] /* Bank account holder not found */
                        );
                    }
                    $docType = 'Bank Account Cover';
                    break;

                case 'NPWPVerify':
                    if(!$fullName){
                        $errorFieldArr[] = array(
                            'id'    => 'usernameError',
                            'msg'   => $translations['E01062'][$language] /* Username not found */
                        );
                    }elseif(!$npwp){
                        $errorFieldArr[] = array(
                            'id'    => 'npwpError',
                            'msg'   => $translations['E01068'][$language] /* NPWP not found */
                        );
                    }
                    $docType = 'NPWP Verification';
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01060'][$language]/* Invalid verify type */, 'data' => '');
            }

            if(!$imgName){
                $errorFieldArr[] = array(
                    'id'    => 'uploadError',
                    'msg'   => $translations['E01069'][$language] /* Please upload image */
                );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E01070'][$language] /* Required place cannot be empty */, 'data'=>$data);
            }

            $db->where('doc_type', $docType);
            $db->where('client_id',$clientId);
            $db->where('status', array('Waiting Approval','Success'),'IN');
            $checkValid = $db->getValue('mlm_kyc','status');

            if($checkValid){
                switch($checkValid){
                    case 'Success':
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01071'][$language] /* You have done verified your KYC */, 'data' => '');
                        break;

                    default:
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01071'][$language] /*Your verification is waiting to be approved */, 'data' => '');
                        break;
                }
            }


            $data['imgName']    = $imgName;
            $data['fullName']   = $fullName;
            $data['idNumber']   = $idNumber;
            $data['address']    = $address;
            $data['bankName']   = $bankName;
            $data['bankAccNo']  = $bankAccNo;
            $data['bankAccHold']= $bankAccHold;
            $data['npwp']       = $npwp;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addKYCConfirmation($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientId   = $db->userID;
            $verifyType = $params['verifyType'];
            $created_at = date('Y-m-d H:i:s');

            $db->where('client_id',$clientId);
            $getCountryId = $db->getValue('address','country_id');

            $result = self::addKYCValidation($params);
            if($result['status'] != 'ok'){
                return $result;
            }
            
            $imgName    = $result['data']['imgName'];
            $fullName   = $result['data']['fullName'];
            $idNumber   = $result['data']['idNumber'];
            $address    = $result['data']['address'];
            $bankName   = $result['data']['bankName'];
            $bankAccNo  = $result['data']['bankAccNo'];
            $bankAccHold= $result['data']['bankAccHold'];
            $npwp       = $result['data']['npwp'];
            
            $groupCode = General::generateUniqueChar("mlm_kyc_detail",'value');
            $fileType = end(explode(".",$imgName));
            $imgName = time()."_".General::generateUniqueChar("mlm_kyc_detail","value")."_".$groupCode.".".$fileType;

            switch($verifyType){
                case 'idVerify':
                    $docType = 'ID Verification';
                    $data = array(  
                                    "Full Name"         => $fullName,
                                    "Identity Number"   => $idNumber,
                                    "Address"           => $address,
                                    "Image Name 1"      => $imgName
                                );
                    break;

                case 'bankAccVerify':
                    $docType = 'Bank Account Cover';
                    $data = array(  
                                    "Bank Name"             => $bankName,
                                    "Bank Account Number"   => $bankAccNo,
                                    "Bank Account Holder"   => $bankAccHold,
                                    "Image Name 1"          => $imgName
                                );
                    break;

                case 'NPWPVerify':
                    $docType = 'NPWP Verification';
                    $data = array(  
                                    "Full Name"         => $fullName,
                                    "NPWP Number"       => $npwp,
                                    "Image Name 1"      => $imgName
                                );
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01060'][$language]/* Invalid verify type */, 'data' => '');
            }

            $insertKYCData = array(
                                    "client_id" => $clientId,
                                    "country_id"=> $getCountryId,
                                    "doc_type"  => $docType,
                                    "status"    => "Waiting Approval",
                                    "created_at"=> $created_at
                                  );
            $kyc = $db->insert('mlm_kyc',$insertKYCData);

            foreach($data as $name=>$value){
                if($name == 'Address'){
                    $insertKYCDetail = array(
                                             "kyc_id"       => $kyc,
                                             "name"         => $name,
                                             "type"         => "basic",
                                             "description"  => $value
                                            );
                }else{
                    $insertKYCDetail = array(
                                             "kyc_id"       => $kyc,
                                             "name"         => $name,
                                             "type"         => "basic",
                                             "value"        => $value
                                            );
                }
                $kycDetail = $db->insert('mlm_kyc_detail',$insertKYCDetail);
            }

            /* Notification Part */
            General::insertNotification("kyc");
            $insertNotification = array(
                "kyc_id"       => $kyc,
                "name"         => 'notificationCount',
                "type"         => 'basic',
                "value"        => 1
            );
            $db->insert('mlm_kyc_detail', $insertNotification);
            
            $returnData['imgName']      = $imgName;
            $returnData["doRegion"]     = Setting::$configArray["doRegion"];
            $returnData["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            $returnData["doAccessKey"]  = Setting::$configArray["doApiKey"];
            $returnData["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            $returnData["doBucketName"] = Setting::$configArray["doBucketName"]."/kyc";
            $returnData["doProjectName"]= Setting::$configArray["doProjectName"];
            $returnData["doFolderName"] = Setting::$configArray["doFolderName"];

            if(!$kyc || !$kycDetail){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01073'][$language] /* Please try again later */, 'data' => '');
            }else{
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $returnData);
            }
        }

        public function checkMemberKYCStatus($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $checkKYCFlag = Setting::$systemSetting['checkKYCFlag'];
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;

            if($checkKYCFlag != 1) return false;

            // $kycDocAry = array("ID Verification","Bank Account Cover","NPWP Verification");
            $kycDocAry = array("ID Verification");
            if($params['type'] == 'purchase') $kycDocAry = array("ID Verification");

            $db->where("client_id",$clientID);
            $db->where("doc_type",$kycDocAry,"IN");
            $db->where("status",array("Approved"),"IN");
            $memberKYC = $db->map("doc_type")->get("mlm_kyc",null,"doc_type");

            unset($nonApprovedKYC);

            foreach($kycDocAry as $kycDoc){
                if(!$memberKYC[$kycDoc]) $nonApprovedKYC[$kycDoc] = $kycDoc;
            }

            return $nonApprovedKYC;
        }

        public function accountOwnerVerification($params, $type) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $identityType = trim($params['identityType']);
            $identityNumber = trim($params['identityNumber']);
            $name = trim($params['name']);
            $dob = trim($params['dob']);
            $step = trim($params['step']);
            $phone = trim($params['phone']);
            $verificationCode = $params['verificationCode'];
            $dialCode = $params['dialCode'];
            $number = $params['number'];
            $type = $type ? $type : $params['type'];
            if(!$step) $step = 1;

            if($type == 'resetPassword'){
                if(empty($phone)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
                    );
                }
            }

            if($type == 'resetPassword' && $step > 1){
                if(!$verificationCode){
                    $errorFieldArr[] = array(
                        'id'  => 'verificationCodeError',
                        'msg' => $translations["M01050"][$language] /* Please insert otp code */
                    );      
                }
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            if($type == 'resetPassword'){
                $db->where('concat(dial_code, phone)', $phone);
                $db->orWhere('member_id', $phone);
                $clientID = $db->getOne('client', 'id, email, username, concat(dial_code, phone) as phone');

                if(!$clientID)
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
            }

            $data['loginID'] = $clientID['username'];
            $data['phone'] = $clientID['phone'];

            if($type == 'resetPassword'){

                // if($step == 1){
                    $otpParams['phone'] = $clientID['phone'];
                    $otpParams['sendType'] = 'phone';
                    $otpParams['type'] = $type;

                    //return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $clientID['phone']);

                    $otpRes = Otp::sendOTPCode($otpParams);
                    $content = '*OTP Request* '."\n\n".'Phone : '.$otpParams['phone']."\n".'Send Type: phone'."\n".'OTP Code: '.$otpRes['data']['otpCode']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    Client::sendTelegramNotification($content);

                    return $otpRes;
                // }

            }else {

                // if ($step == 1){
                    $otpParams['phone'] = $number;
                    $otpParams['sendType'] = 'phone';
                    $otpParams['type'] = $type;
                    $otpParams['dialCode'] = $dialCode;

                    $db->orderby('created_on');
                    $db->where('phone_number',$dialCode.$number);
                    $db->where('status','Sent');
                    $db->where('verification_type','register##phone');
                    $availableOTP = $db->getOne('sms_integration','created_on');
                    $availableOTP = $availableOTP['created_on'];
                    $currentDateTime = time();

                    $availableOTP = strtotime($availableOTP);

                    $diff_minutes = ($currentDateTime - $availableOTP) / 60;
                    if ($diff_minutes >= 3) {
                        $otpRes = Otp::sendOTPCode($otpParams);
                    } else 
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "Please wait the resend OTP colddown", 'data' => "");
                    }
                    if($otpRes)
                    {
                        $db->where('phone_number', $dialCode.$number);
                        $getOtpCode = $db->getOne('sms_integration','code');
                        foreach ($getOtpCode as $row) {
                            $OtpCode = $row['code'];
                        }
                        $content = '*OTP Request* '."\n\n".'Phone : '.$otpParams['phone']."\n".'Send Type: phone'."\n".'OTP Code: '.$otpRes['data']['otpCode']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                        Client::sendTelegramNotification($content);
                    }
                    return $otpRes;
                // }

            }

            // verify OTP code
            $verifyCode = Otp::verifyOTPCode($clientID,'phone',$type,$verificationCode,$phone);
            if($verifyCode['status'] == 'error')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Wrong OTP code", 'data' => "");
            }
            else
            {
                $db->where('phone_number',$phone);
                $db->where('status','Sent');
                $db->where('msg_type','OTP Code');
                $db->where('verification_type','resetPassword##phone');
                $db->where('code',$verificationCode);
                $fields = array("status");
                $values = array("Verified");
                $arrayData = array_combine($fields, $values);
                $row = $db->update("sms_integration", $arrayData);
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function guestOwnerVerification($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $name = trim($params['name']);
            $emailAddress = trim($params['emailAddress']);
            $dialingArea = trim($params['dialingArea']);
            $phone = trim($params['phone']);
            $companyName = trim($params['companyName']);
            $address = $params['streetNo'];
            $city = $params['city'];
            $zipCode = $params['zipCode'];
            $state = $params['state'];
            $country = $params['country'];
            $package    = $params['package'];
            $purchaseAmount = $params['purchaseAmount'];
            $ShipToSameAddress = $params['guestShipToSameAddress'];

            $name2 = trim($params['name2']);
            $emailAddress2 = trim($params['emailAddress2']);
            $dialingArea2 = trim($params['dialingArea2']);
            $phone2 = trim($params['phone2']);
            $companyName2 = trim($params['companyName2']);
            $address2 = $params['streetNo2'];
            $city2 = $params['city2'];
            $zipCode2 = $params['zipCode2'];
            $state2 = $params['state2'];
            $country2 = $params['country2'];
            $deliveryMethod = trim($params['deliveryMethod']);

            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id' => 'nameError',
                    'msg' => 'Please fill in name' 
                );
            }


            if(empty($dialingArea)) {
                $errorFieldArr[] = array(
                    'id' => 'dialCodeError',
                    'msg' => $translations["E01084"][$language] /* Please fill in valid dial code */
                );
            }

            if(empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["M02436"][$language] /* Enter your phone number */
                );
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id' => 'addressError',
                    'msg' => $translations["M03152"][$language] /* Enter your address */
                );
            }

            if(empty($city)) {
                $errorFieldArr[] = array(
                    'id' => 'cityError',
                    'msg' => $translations["M03157"][$language] /* Enter your city */
                );
            }

            if(empty($zipCode)) {
                $errorFieldArr[] = array(
                    'id' => 'zipCodeError',
                    'msg' => $translations["E01030"][$language] /* Please Insert Zip Code */
                );
            }


            if($ShipToSameAddress != 1)
            {
                if(empty($address2)) {
                    $errorFieldArr[] = array(
                        'id' => 'addressError',
                        'msg' => $translations["M03152"][$language] /* Enter your address */
                    );
                }
    
                if(empty($city2)) {
                    $errorFieldArr[] = array(
                        'id' => 'cityError',
                        'msg' => $translations["M03157"][$language] /* Enter your city */
                    );
                }
    
                if(empty($zipCode2)) {
                    $errorFieldArr[] = array(
                        'id' => 'zipCodeError',
                        'msg' => $translations["E01030"][$language] /* Please Insert Zip Code */
                    );
                }
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // check is the user exist or not
            $db->where('concat(dial_code,phone)',$dialingArea.$phone);
            $userExist = $db->get('client',null,'id, username, name, email, dial_code, phone, address, country_id, state_id, city_id');

            if($userExist)
            {
                foreach($userExist as $userRow)
                {
                    $clientID = $userRow['id'];
                }

                $db->where('name',$country);
                $countryID = $db->get('country',null,'id');
                $countryID = $countryID[0]['id'];
                $db->where('name',$state);
                $stateID = $db->get('state',null,'id');
                $stateID = $stateID[0]['id'];
                $db->where('name',$city);
                $db->where('country_id',$countryID);
                $cityID = $db->get('city',null,'id');
                $cityID = $cityID[0]['id'];

                $data = array(
                    "email"         => $emailAddress,
                    "address"       => $address,
                    "country_id"    => $countryID,
                    "state_id"      => $stateID,
                    "city_id"       => $cityID,
                    "updated_at"    => date("Y-m-d H:i:s"),
                );
                $db->where('concat(dial_code,phone)',$dialingArea.$phone);
                // update client table details
                $updateUser = $db->update('client',$data);
                if(!$updateUser)
                {
                    $content = '*Failed to Update User Existing profile* '."\n\n"."Client Phone No: ".$dialingArea.$phone."\n"."Address: ".$address."\n"."Country ID: ".$countryID."\n"."State ID: ".$stateID."\n"."City ID: ".$cityID."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    Client::sendTelegramNotification($content);
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01180"][$language] /* Failed to update user existing profile. */, 'data' => '');
                }
            }
            else
            {
                // $clientID = $db->getNewID();
                $memberID = Subscribe::generateMemberID();
                $dateTime = $db->now();
                $db->where('name',$country);
                $countryID = $db->get('country',null,'id');
                $countryID = $countryID[0]['id'];
                $db->where('name',$state);
                $stateID = $db->get('state',null,'id');
                $stateID = $stateID[0]['id'];
                $db->where('name',$city);
                $db->where('country_id',$countryID);
                $cityID = $db->get('city',null,'id');
                $cityID = $cityID[0]['id'];

                $insertClientData = array(
                    // "id" => $clientID,
                    "member_id" => $memberID,
                    "email" => $emailAddress,
                    "name" => $name,
                    "username" => $dialingArea.$phone, 
                    "dial_code" => $dialingArea,
                    "phone" => $phone,
                    "address" => $address,
                    "state_id" => $stateID,
                    "city_id" => $cityID,
                    "country_id" => $countryID,
                    "type" => "Guest",
                    "created_at" => $dateTime,
                );
                $createGuest = $db->insert('client',$insertClientData);
                if(!$createGuest)
                {
                    $content = '*Failed to register new guest Message* '."\n\n".'Member ID: '.$memberID."\n"."Name: ".$name."\n".'Type: Guest'."\n".'Phone Number: +'.$dialingArea.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    Client::sendTelegramNotification($content);
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01182"][$language] /* Failed to create new client. */ , 'data' => '');
                }
                // get User Client Id (Guest)
                $db->where('concat(dial_code,phone)', $dialingArea.$phone);
                $db->where('type', 'Guest');
                $clientID = $db->getOne('client');
                $clientID = $clientID['id']; 
            }
            // convert country name and state name into id
            $db->where('name', $country);
            $countryId = $db->getOne('country');

            $db->where('name', $country2);
            $countryId2 = $db->getOne('country');

            $db->where('name', $state);
            $stateId = $db->getOne('state');

            $db->where('name', $state2);
            $stateId2 = $db->getOne('state');

            if($ShipToSameAddress == '1')
                {
                    $db->where('id',$clientID);
                    $clientDetails = $db->getOne('client');
                    // Billing address
                    $data = array(
                        "client_id" => $clientID,
                        "name" => $clientDetails['name'],
                        "email" => $emailAddress,
                        "phone" => $clientDetails['phone'],
                        "address" => $address,
                        "post_code" => $zipCode,
                        "city" => $city,
                        "state_id" => $stateId['id'],
                        "country_id" => $countryId['id'],
                        "address_type" => 'shipping',
                        "remarks" => $companyName,
                        "created_at" => $db->now(),
                    );
                    // Shipping address
                    $data2 = array(
                        "client_id" => $clientID,
                        "name" => $name2,
                        "email" => $emailAddress2,
                        "phone" => $phone2,
                        "address" => $address,
                        "post_code" => $zipCode,
                        "city" => $city,
                        "state_id" => $stateId['id'],
                        "country_id" => $countryId['id'],
                        "address_type" => 'shipping',
                        "remarks" => $companyName,
                        "created_at" => $db->now(),
                    );
                    $insertAddress1 = $db->insert('address',$data);
                    if(!$insertAddress1)
                    {
                        $content = '*Failed to insert new company address Message* '."\n\n".'client ID: '.$clientID."\n"."Name: ".$clientDetails['name']."\n"."Email: ".$clientDetails['email']."\n".'Type: Guest'."\n".'Phone Number: +'.$clientDetails['phone']."\n"."Address: ".$clientDetails['address']."\n"."Post Code: ".$zipCode."\n"."City: ".$city."\n"."State id: ".$clientDetails['state_id']."\n"."Country id: ".$clientDetails['country_id']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                        Client::sendTelegramNotification($content);
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01181"][$language] /* Failed to insert company address. */, 'data' => '');
                    }
                    $insertAddress2 = $db->insert('address',$data2);
                    if(!$insertAddress2)
                    {
                        $content = '*Failed to insert new company address Message* '."\n\n".'client ID: '.$clientID."\n"."Name: ".$clientDetails['name']."\n"."Email: ".$clientDetails['email']."\n".'Type: Guest'."\n".'Phone Number: +'.$clientDetails['phone']."\n"."Address: ".$clientDetails['address']."\n"."Post Code: ".$zipCode."\n"."City: ".$city."\n"."State id: ".$clientDetails['state_id']."\n"."Country id: ".$clientDetails['country_id']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                        Client::sendTelegramNotification($content);
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01181"][$language] /* Failed to insert company address. */, 'data' => '');
                    }
                }
                else
                {
                    $db->where('id',$clientID);
                    $clientDetails = $db->getOne('client');
                    // insert address Table
                    // Billing address
                    $data = array(
                        "client_id" => $clientID,
                        "name" => $clientDetails['name'],
                        "email" => $emailAddress,
                        "phone" => $clientDetails['phone'],
                        "address" => $address,
                        "post_code" => $zipCode,
                        "city" => $city,
                        "state_id" => $stateId['id'],
                        "country_id" => $countryId['id'],
                        "address_type" => 'silling',
                        "remarks" => $companyName,
                        "created_at" => $db->now(),
                    );
                    // Shipping address
                    $data2 = array(
                        "client_id" => $clientID,
                        "name" => $name2,
                        "email" => $emailAddress2,
                        "phone" => $phone2,
                        "address" => $address2,
                        "post_code" => $zipCode2,
                        "city" => $city2,
                        "state_id" => $stateId2['id'],
                        "country_id" => $countryId2['id'],
                        "address_type" => 'shipping',
                        "remarks" => $companyName,
                        "created_at" => $db->now(),
                    );
                    $insertAddress1 = $db->insert('address',$data);
                    if(!$insertAddress1)
                    {
                        $content = '*Failed to insert new company address Message* '."\n\n".'client ID: '.$clientID."\n"."Name: ".$clientDetails['name']."\n"."Email: ".$clientDetails['email']."\n".'Type: Guest'."\n".'Phone Number: +'.$clientDetails['phone']."\n"."Address: ".$clientDetails['address']."\n"."Post Code: ".$zipCode."\n"."City: ".$city."\n"."State id: ".$clientDetails['state_id']."\n"."Country id: ".$clientDetails['country_id']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                        Client::sendTelegramNotification($content);
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01181"][$language] /* Failed to insert company address. */, 'data' => '');
                    }
                    $insertAddress2 = $db->insert('address',$data2);
                    if(!$insertAddress2)
                    {
                        $content = '*Failed to insert new company address Message* '."\n\n".'client ID: '.$clientID."\n"."Name: ".$clientDetails['name']."\n"."Email: ".$clientDetails['email']."\n".'Type: Guest'."\n".'Phone Number: +'.$clientDetails['phone']."\n"."Address: ".$clientDetails['address']."\n"."Post Code: ".$zipCode."\n"."City: ".$city."\n"."State id: ".$clientDetails['state_id']."\n"."Country id: ".$clientDetails['country_id']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                        Client::sendTelegramNotification($content);
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01181"][$language] /* Failed to insert company address. */, 'data' => '');
                    }
                }
            // add to shopping cart
            foreach ($package as $value) {
                $cartParams['packageID'] = $value['packageID'];
                $cartParams['quantity'] = $value['quantity'];
                $cartParams['product_template'] = $value['product_template'];
                $cartParams['clientID'] = $clientID;
                $cartParams['type'] = 'inc';
                $addCartResult = Inventory::addShoppingCart($cartParams);

                if(!$addCartResult)
                {
                    return array("code" => 1, "status" => "error", "statusMsg" => 'failure' , 'data' => $addCartResult['statusMsg']);
                }
            }

            // get the shipping address id
            $db->where('client_id', $clientID);
            $ShippingAddressList = $db->get('address', null,'id');
            
            $ShippingId = $ShippingAddressList[0]['id'];
            $BillingId = $ShippingAddressList[1]['id'];

            unset($params);
            $params['clientID'] = $clientID;
            $params['package'] = $package;
            
            $updateInventory = Inventory::updateShoppingCart($params);
            if($updateInventory['status'] == 'error')
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01183"][$language] /* Failed to update shopping card. */, 'data' => $updateInventory['statusMsg']);
            }
            unset($params);
            $params['quantityOfReward'] = '0';
            $params['isRedeemReward'] = '0';
            $params['redeemAmount'] = '0';
            $params['memberPointDeduct'] = '0';
            $params['billing_address'] = $BillingId;
            $params['shipping_address'] = $ShippingId;
            $params['purchase_amount'] = $purchaseAmount;
            $params['clientID'] = $clientID;
            $params['delivery_method'] = $deliveryMethod;
            $addNewPayment = Cash::addNewPayment($params, $clientID); // addNewPayment
            if($addNewPayment['status'] == 'error')
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01184"][$language] /* Failed to add new payment. */, 'data' => $addNewPayment);
            }
            
            $content = '*Register New guest Message* '."\n\n".'Member ID: '.$memberID."\n"."Name: ".$name."\n".'Type: Guest'."\n".'Phone Number: +'.$dialingArea.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            Client::sendTelegramNotification($content);
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $addNewPayment['statusMsg'], 'data' => $addNewPayment['data']);
        }

        public function getState($params)
        {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $country_name = trim($params['countryName']);
            $country_id   = trim($params['countryId']);
            $state_id     = trim($params['stateId']);
            $state_name   = trim($params['stateName']);

            if(empty($country_id))
            {
                // get all country
                $countryList = $db->get('country',null,'id, name');

                // get all state
                $stateList = $db->get('state',null,'id, name');
            }  
            if(!empty($country_id))
            {
                if(empty($country_name))
                {
                    // get the country name
                    $db->where('id',$country_id);
                    $country_name = $db->getOne('country','name');
                    $country_name = $country_name['name'];
                }
                if(empty($state_id))
                {
                    // $stateList = $db->get('state',null,'id, name');
                    $db->where('country_id',$country_id);
                    $state_id = $db->getOne('state','id');
                    $state_id = $state_id['id'];
                }
                else if (!empty($state_id))
                {
                    if(empty($state_name));
                    {
                        // get the state name
                        $db->where('id',$state_id);
                        $db->where('country_id',$country_id);
                        $state_name = $db->getOne('state','name');
                        $state_name = $state_name['name'];
                    }
                    
                }
                // get the country id and name
                $db->where('id',$country_id);
                $countryList = $db->getOne('country','id, name');

                // get the state id and name
                $db->where('country_id',$country_id);
                $stateList = $db->get('state',null,'id, name');
            }
            
            $data['state'] = $stateList;
            $data['country'] = $countryList;
            return array("code" => 0, "status" => "ok", "statusMsg" => '', "data" => $data);
        }

        public function getProductListMember($params)
        {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            
            $userID = $db->userID;
            $site = $db->userType;

            $categories = trim($params['categories']);

            if(empty($categories))
            {
                $productList = $db->get('product',null, 'id, sale_price, name, product_type, barcode as skuCode');
                $db->where('name', 'percentage');
                $db->where('type', 'marginPercen');
                $margin_percen = $db->getOne('system_settings','value');
                $margin_percen = $margin_percen['value'];
                foreach($productList as $productInvRow)
                {
                    $productDetail['id']                = $productInvRow['id'];
                    $productDetail['skuCode']           = $productInvRow['skuCode'];
                    $productDetail['name']              = $productInvRow['name'];
                    $productDetail['productType']       = $productInvRow['product_type'];
                    $productDetail['marginPercen']      = $margin_percen;
                    $productDetail['salePrice']         = $productInvRow['sale_price'];

                    $db->where('reference_id', $productInvRow['id']);
                    $db->where('type', 'Image');
                    $productImage = $db->getOne('product_media', 'url');

                    if($productImage) {
                        $productDetail['image']             = $productImage['url'];
                    } else {
                        $productDetail['image']             = '';
                    }

                    $productInvList[] = $productDetail;
                }
                $data['productInventory'] = $productInvList;
                return array("code" => 0, "status" => "ok", "statusMsg" => '', "data" => $data);
            }
            $db->where('name',$categories);
            $productID = $db->get('product_category',null,'id');

            if(!$productID)
            {
                // probably not searching category, try search product name
                $searchTerm = '%'.$categories.'%';
                $productList = $db->rawQuery("SELECT * FROM product WHERE name LIKE '".$searchTerm."'");
                if(!$productList)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00279"][$language] /* No result found */, 'data' => "");
                }
                $productID = $productList;
                // return array("code" => 333, "status" => "ok", "productID" => $productID);
            }

            // $productID = '["'.$productID[0]['id'].'"]';

            // $db->where('categ_id',$productID);
            // $productList = $db->get('product',null, 'id, cost, margin_percen, name, product_type, barcode as skuCode');
            foreach($productList as $productInvRow)
            {
                $productDetail['id']                = $productInvRow['id'];
                $productDetail['skuCode']           = $productInvRow['skuCode'];
                $productDetail['name']              = $productInvRow['name'];
                $productDetail['productType']       = $productInvRow['product_type'];
                $productDetail['marginPercen']      = $productInvRow['margin_percen'];
                $productDetail['salePrice']         = $productInvRow['sale_price'];

                $productInvList[] = $productDetail;
            }
            $data['productInventory'] = $productInvList;
            return array("code" => 0, "status" => "ok", "statusMsg" => '', "data" => $data);
        }

        public function getCategoryInventoryMember($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            $searchForm = $params['searchData'];
            $categories = trim($params['categories']);

            $searchData[1]['dataName'] = 'name';
            $searchData[1]['dataType'] = 'text';
            $searchData[1]['dataValue'] = $categories;
            
            // $params['categories'] = $searchForm;
            $productList = Client::getProductListMember($params);
            // foreach($productList as $row)
            // {
            //     $productDetail['productInventory']  = $row['productInventory'];

            //     $productInvList[] = $productDetail;
            // }
            // return array("code" => 110, "status" => "ok", "productList" => $productList);

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? : 0;
            $limit = General::getLimit($pageNumber);

            if($seeAll) {
                $limit = NULL;
            }

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch($dataName) {
                        case "status":
                            if($dataValue == "Active") {
                                $db->where("deleted", 0);
                            } else if($dataValue == "Inactive") {
                                $db->where("deleted", 1);
                            }
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where('DATE(created_at)', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);

                                $db->where('DATE(created_at)', date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            $copyDB = $db->copy();
            $db->orderBy("created_at", "DESC");
            $db->where('deleted','0');
            $categoryRes = $db->get("product_category", null, "id, name, deleted, created_at");

            if(!$categoryRes) {
                if(!$productList)
                {
                    return array("status" => "ok", "code" => 0, "statusMsg" => $translations["B00101"][$language] /* No results found */, "data" => "");
                }
                $db->where('deleted','0');
                $categoryRes = $db->get("product_category", null, "id, name, deleted, created_at");
            }

            foreach ($categoryRes as $categoryRow) {
                $categoryID[$categoryRow['id']] = $categoryRow['id'];
            }

            if($categoryID){
                $db->where('module_id', $categoryID, 'IN');
                $db->where('module', 'category');
                $db->where('type', 'name');
                $langRes = $db->get('inv_language', null, 'module_id, language, content');

                foreach ($langRes as $langRow) {
                    $lang[$langRow['module_id']][$langRow['language']] = $langRow['content'];
                }
            }

            foreach($categoryRes as $categoryRow) {
                $category["id"] = $categoryRow["id"];
                $category["name"] = $categoryRow["name"];
                $category["display"] = $lang[$categoryRow['id']][$language];

                switch($categoryRow["deleted"]) {
                    case "0":
                        $category["status"] = "Active";
                        $category["statusDisplay"] = General::getTranslationByName($category["status"]);
                        break;

                    case "1":
                        $category["status"] = "Inactive";
                        $category["statusDisplay"] = General::getTranslationByName($category["status"]);
                        break;

                    default:
                        break;
                }

                $category["createdAt"] = date($dateTimeFormat, strtotime($categoryRow['created_at']));

                $categoryList[] = $category;
            }
            // check productInventory
            if($productList['data']['productInventory'] == null)
            {

                $db->where('name', $categories);
                $categoryId = $db->getOne('product_category');
                $categoryId = $categoryId['id'];

                $categ_id = '"'.$categoryId.'"';
                $db->where('categ_id', '%'.$categ_id.'%', 'LIKE');
                $productList = $db->get('product');
            }
            else
            {
                $productList = $productList['data']['productInventory'];
            }


            // do another filter
            if(empty($categories))
            {
                $db->where('deleted', '0');
                $categoryList = $db->get('product_category');
            }

            if(!empty($categories))
            {
                if(empty($searchForm))
                {
                    $db->where('name', $categories);
                    $db->where('deleted', '0');
                    $categoryId = $db->getOne('product_category');
                    $categoryId = $categoryId['id'];
                    $categ_id = '"'.$categoryId.'"';
                    $db->where('categ_id', '%'.$categ_id.'%', 'LIKE');
                    if(!empty($categories))
                    {
                        $db->where('name', "%".$searchForm."%", 'LIKE');
                    }
                    $productList = $db->get('product');
                    foreach($productList as $productInvRow)
                    {
                        $productDetail['id']                = $productInvRow['id'];
                        $productDetail['skuCode']           = $productInvRow['skuCode'];
                        $productDetail['name']              = $productInvRow['name'];
                        $productDetail['productType']       = $productInvRow['product_type'];
                        $productDetail['marginPercen']      = $productInvRow['margin_percen'];
                        $productDetail['salePrice']         = $productInvRow['sale_price'];
                        $db->where('reference_id', $productInvRow['id']);
                        $db->where('type', 'Image');
                        $productImage = $db->getOne('product_media', 'url');
    
                        if($productImage) {
                            $productDetail['image']             = $productImage['url'];
                        } else {
                            $productDetail['image']             = '';
                        }
                        $productInvList[] = $productDetail;
                    }
                    $productList = $productInvList;
                }
                else
                {
                    $db->where('name', "%".$searchForm."%", 'LIKE');
                    $db->where('deleted', '0');
                    $productList = $db->get('product');
                    foreach($productList as $productInvRow)
                    {
                        $productDetail['id']                = $productInvRow['id'];
                        $productDetail['skuCode']           = $productInvRow['skuCode'];
                        $productDetail['name']              = $productInvRow['name'];
                        $productDetail['productType']       = $productInvRow['product_type'];
                        $productDetail['marginPercen']      = $productInvRow['margin_percen'];
                        $productDetail['salePrice']         = $productInvRow['sale_price'];
                        $db->where('reference_id', $productInvRow['id']);
                        $db->where('type', 'Image');
                        $productImage = $db->getOne('product_media', 'url');
    
                        if($productImage) {
                            $productDetail['image']             = $productImage['url'];
                        } else {
                            $productDetail['image']             = '';
                        }
                        $productInvList[] = $productDetail;
                    }
                    $productList = $productInvList;
                }   
            }
            else
            {
                if(!empty($searchForm))
                {
                    $db->where('name', "%".$searchForm."%", 'LIKE');
                    $db->where('deleted', '0');
                    $productList = $db->get('product');
                    foreach($productList as $productInvRow)
                    {
                        $productDetail['id']                = $productInvRow['id'];
                        $productDetail['skuCode']           = $productInvRow['skuCode'];
                        $productDetail['name']              = $productInvRow['name'];
                        $productDetail['productType']       = $productInvRow['product_type'];
                        $productDetail['marginPercen']      = $productInvRow['margin_percen'];
                        $productDetail['salePrice']         = $productInvRow['sale_price'];
                        $db->where('reference_id', $productInvRow['id']);
                        $db->where('type', 'Image');
                        $productImage = $db->getOne('product_media', 'url');
    
                        if($productImage) {
                            $productDetail['image']             = $productImage['url'];
                        } else {
                            $productDetail['image']             = '';
                        }
                        $productInvList[] = $productDetail;
                    }
                    $productList = $productInvList;
                }
                // return array("code" => 110, "status" => "ok", "productList" => $productList);
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            // $db->where('name', "%".$searchForm."%", 'LIKE');
            // $productList = $db->get('product');
            if($categories == 'All Categories')
            {
                if(empty($searchForm))
                {
                    $db->where('deleted', '0');
                    $productList = $db->get('product');
                    foreach($productList as $productInvRow)
                    {
                        $productDetail['id']                = $productInvRow['id'];
                        $productDetail['skuCode']           = $productInvRow['skuCode'];
                        $productDetail['name']              = $productInvRow['name'];
                        $productDetail['productType']       = $productInvRow['product_type'];
                        $productDetail['marginPercen']      = $productInvRow['margin_percen'];
                        $productDetail['salePrice']         = $productInvRow['sale_price'];
                        $db->where('reference_id', $productInvRow['id']);
                        $db->where('type', 'Image');
                        $productImage = $db->getOne('product_media', 'url');
    
                        if($productImage) {
                            $productDetail['image']             = $productImage['url'];
                        } else {
                            $productDetail['image']             = '';
                        }
                        $productInvList[] = $productDetail;
                    }
                    $productList = $productInvList;
                    // return array("code" => 110, "status" => "ok", "productList" => $productList);
                }
            }

            $totalRecord = $copyDB->getValue('product_category', "count(*)");
            $data["categoryList"] = $categoryList;
            if($productList == '')
            {
                $data['productInventory'] = $productList['data']['productInventory'];
            }
            else
            {
                $data['productInventory'] = $productList;
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00114"][$language] /* Search Sucecssful */, 'data'=> $data);
        }
        
        public function clientPurchaseHistory($params)
        {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $ClientID = $db->userID;
            $site = $db->userType;

            if(empty($ClientID))
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00257"][$language] /* User id is invalid */, 'data' => '');
            }
            $db->orderBy('s.id','Desc');
            $db->where('s.client_id',$ClientID);
            $db->join('payment_gateway_details pg','s.id = pg.purchase_id','LEFT');
            try{
            $result = $db->get('sale_order s',null,'s.id, pg.created_at as payment_date, pg.purchase_amount, s.status');
            } catch(Exception $e)
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01185"][$language] /* Failed to execute query */, 'data' => '');
            }
            if(!$result)
            {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["M03624"][$language] /* No Result Found */, 'data' => '');
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $result);
        }
    }
?>
