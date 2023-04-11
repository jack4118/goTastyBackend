<?php
    use libphonenumber\PhoneNumberUtil;
	
	class Otp {

		public static $sms;
		
		function __construct() {
        }

        //请求数据到短信接口，检查环境是否 开启 curl init。
        function Post($curlPost,$url){
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_NOBODY, true);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
                $return_str = curl_exec($curl);
                curl_close($curl);
                return $return_str;
        }

        //将 xml数据转换为数组格式。
        function xml_to_array($xml){
            $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
            if(preg_match_all($reg, $xml, $matches)){
                $count = count($matches[0]);
                for($i = 0; $i < $count; $i++){
                $subxml= $matches[2][$i];
                $key = $matches[1][$i];
                    if(preg_match( $reg, $subxml )){
                        $arr[$key] = self::xml_to_array( $subxml );
                    }else{
                        $arr[$key] = $subxml;
                    }
                }
            }
            return $arr;
        }

        public function sendOTPCodeDouble($params, $clientID){
            $db = MysqliDb::getInstance();
            // $message = self::message;
            $sms     = self::$sms;
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $username = trim((string)$params['username']);
            $phoneNumberIn = trim((string)$params['phoneNumber'])?trim((string)$params['phoneNumber']):trim((string)$params['phone']);
            $emailIn = trim((string)$params['email']);
            $type = trim((string)$params['type']);
            $dialCodeIn = trim((string)$params['dialCode']);

            $client_id = $clientID ? $clientID:0;

            if( ($client_id == '0' || !$client_id) && !$username) {
                $errorFieldArr[] = array(
                                            'id'  => 'phoneError',
                                            'msg' => $translations['E00656'][$language] // Please Enter User Name.
                                        );
            }

            if(!$phoneNumberIn){
                $errorFieldArr[] = array(
                                            'id'  => 'phoneError',
                                            'msg' => $translations['E00664'][$language] // Please Enter Mobile Number.
                                        );
            }
            if ( !$emailIn ){
                $errorFieldArr[] = array(
                                            'id'  => 'emailError',
                                            'msg' => $translations['E00663'][$language] // Please Enter Email.
                                        );
            }
            if ( !$type ){
                $errorFieldArr[] = array(
                                            'id'  => 'phoneError',
                                            'msg' => $translations['E00661'][$language] // Please Enter Type.
                                        );
            }
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            if($client_id){
                $db->where('id', $client_id);
            }elseif($username){
                $db->where('username', $username);
            }else{
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00656'][$language] /* Please Enter User Name. */, 'data' => array('field'=> 'phoneError'));
            }
            $row = $db->getone('client', 'phone, dial_code, email, id, username, language');
            if(empty($row))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => array('field'=> 'phoneError'));
            $dialCode       = $row['dial_code'];
            $phoneNumber    = $row['phone'];
            $email          = $row['email'];
            $client_id      = $row['id'];
            $username       = $row['username'];

            if(!$params['username']) $params['username'] = $username;

            if( ( ($phoneNumberIn && ($phoneNumber != $phoneNumberIn)) || ($dialCodeIn && ($dialCode != $dialCodeIn)) ) && ($phoneNumberIn != ($dialCode.$phoneNumber)) ){
                $errorFieldArr[] = array(
                                            'id'  => 'phoneError',
                                            'msg' => $translations["B00217"][$language]
                                        );
            }
            if ($email!=$emailIn){
                $errorFieldArr[] = array(
                                            'id'  => 'emailError',
                                            'msg' => $translations["B00217"][$language]
                                        );
            }
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            $params['sendType'] = 'phone';
            $result = self::sendOTPCode($params, $clientID);
            $statusMsg = $result['statusMsg'];
            if($result['status'] == 'error') return $result;
            unset($result);

            $params['sendType'] = 'email';
            $result = self::sendOTPCode($params, $clientID);
            $statusMsg .= " ".$result['statusMsg'];
            if($result['status'] == 'error') return $result;

            return array("status"=> "ok", "code"=> 0, "statusMsg"=> $statusMsg, "data"=>'');
        }

        public function sendOTPCode($params){
            $db                 = MysqliDb::getInstance();
            // $message = self::message;
            $sms                = self::$sms;
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $username           = trim((string)$params['username']);
            $phoneNumberIn      = trim((string)$params['phoneNumber'])?trim((string)$params['phoneNumber']):trim((string)$params['phone']);
            $email              = trim((string)$params['email']);
            $type               = trim((string)$params['type']);
            $sendType           = trim((string)$params['sendType']);
            $dialCodeIn         = trim((string)$params['dialCode']);
            $created_on         = date("Y-m-d H:i:s");
            $otpValidTime       = Setting::$systemSetting["otpValidTime"];
            $resendOTPCountDown = Setting::$systemSetting["resendOTPCountDown"];

            $client_id          = $db->userID;

            $internalAccountSendOTPAry  =   array('withdrawal','accountSharing');
            $registerByOTP = array('register', 'verifyPhone');
            $resetPasswordByOTP = array('resetPassword');

            switch ($sendType) {
                case 'phone':
                    if(in_array($type, $internalAccountSendOTPAry)){

                        $db->where('id', $client_id);
                        $row = $db->getone('client','dial_code,phone');
                        $phoneNumberIn = $row['phone'];
                        $dialCodeIn = $row['dial_code'];

                    }else if(in_array($type, $registerByOTP)){
                        $db->where('country_code',$dialCodeIn);
                        $regionCode = $db->getValue('country','iso_code2');

                        $result =self::checkPhoneNumber($dialCodeIn.$phoneNumberIn,$regionCode);

                        if(!$result){
                            $errorFieldArr[] = array(
                                                            'id'  => 'phoneError',
                                                            'msg' => $translations["E00773"][$language]
                                                        );
                        }

                        if (!$dialCodeIn || !$phoneNumberIn) {
                            $errorFieldArr[] = array(
                                'id'  => 'phoneError',
                                'msg' => $translations["E00773"][$language]
                            );
                        }

                        $db->where('dial_code',$dialCodeIn);
                        $db->where('phone',$phoneNumberIn);
                        $isOccupied = $db->has('client');
                        if ($isOccupied) {
                            $errorFieldArr[] = array(
                                'id'  => 'phoneError',
                                'msg' => $translations["E00749"][$language]
                            );
                        }
                        $dialCode = $dialCodeIn;
                        $phoneNumber = $phoneNumberIn;

                    }else if(in_array($type, $resetPasswordByOTP)) {
                        // if (!$dialCodeIn || !$phoneNumberIn) {
                        //     $errorFieldArr[] = array(
                        //         'id'  => 'phoneError',
                        //         'msg' => $translations["E00773"][$language] /* Invalid phone number. */
                        //     );
                        // } else {
                        //     $db->where('dial_code', $dialCodeIn);
                        //     $db->where('phone', $phoneNumberIn);
                        //     $row = $db->getValue('client', 'id');
                        //     $client_id = $row;
                        //     if(!$row) {
                        //         $errorFieldArr[] = array(
                        //             'id'  => 'phoneError',
                        //             'msg' => $translations['E00679'][$language] /* Invalid User. */
                        //         );
                        //     }
                        // }

                        // if(!$username){
                        //     $errorFieldArr[] = array(
                        //                                 'id'  => 'usernameError',
                        //                                 'msg' => $translations["E00656"][$language]
                        //                             );
                        // }


                        $db->where('concat(dial_code, phone)', $phoneNumberIn);                

                        $row = $db->getone('client', 'phone, dial_code, ID, language');
                        //return array('status' => "error", 'code' => 2, 'statusMsg' => 'debug'/* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
                        $result=Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType);
                        if(empty($row)){
                            $errorFieldArr[] = array(
                                                            'id'  => 'phoneError',
                                                            'msg' => $translations["E00227"][$language]/* Invalid Username */
                                                        );

                        }

                        $dialCode = $row['dial_code'];
                        $phoneNumber = $row['phone'];
                        $client_id = $row['ID'];
                        $userLanguage = $row['language'];

                    }else{

                        if($client_id){
                            $db->where('id', $client_id);
                        }elseif($username){
                            $db->where('username', $username);
                        }else{
                            $errorFieldArr[] = array(
                                                            'id'  => 'usernameError',
                                                            'msg' => $translations["E00656"][$language]
                                                        );
                        }                    

                        $row = $db->getone('client', 'phone, dial_code, ID, language');
                        if(empty($row)){
                            $errorFieldArr[] = array(
                                                            'id'  => 'phoneError',
                                                            'msg' => $translations["E00227"][$language]/* Invalid Username */
                                                        );
                        }

                        $dialCode = $row['dial_code'];
                        $phoneNumber = $row['phone'];
                        $client_id = $row['ID'];
                        $userLanguage = $row['language'];

                        if( $phoneNumberIn || $dialCodeIn ){

                            if( ( ($phoneNumberIn && ($phoneNumber != $phoneNumberIn)) || ($dialCodeIn && ($dialCode != $dialCodeIn)) ) && ($phoneNumberIn != ($dialCode.$phoneNumber)) ){
                                $errorFieldArr[] = array(
                                                            'id'  => 'phoneError',
                                                            'msg' => $translations["E00773"][$language]
                                                        );
                            }
                        }
                    }
                    break;

                case 'email':
                    if (in_array($type, $internalAccountSendOTPAry) ){
                        $db->where('id', $client_id);
                        $row = $db->getone('client', 'email');
                        $email = $row['email'];

                    }else if(in_array($type, $registerByOTP)){
                        if (!$email) {
                            $errorFieldArr[] = array(
                                'id'  => 'emailError',
                                'msg' => $translations['E00319'][$language]
                            );
                        }

                        $db->where('email',$email);
                        $isOccupied = $db->has('client');
                        if ($isOccupied) {
                            $errorFieldArr[] = array(
                                'id'  => 'emailError',
                                'msg' => $translations['E00748'][$language]
                            );
                        }
                    }else if(in_array($type, $resetPasswordByOTP)) {
                        if (!$email) {
                            $errorFieldArr[] = array(
                                'id'  => 'emailError',
                                'msg' => $translations["E00318"][$language] /* Please fill in email */
                            );
                        } else {
                            $db->where('email', $email);
                            $row = $db->getValue('client', 'id');
                            $client_id = $row;
                            if(!$row) {
                                $errorFieldArr[] = array(
                                    'id'  => 'emailError',
                                    'msg' => $translations['E00679'][$language] /* Invalid User. */
                                );
                            }
                        }
                    }else{
                        $db->where('username', $username);
                        $row = $db->getone('client', 'ID,email');
                        $client_id = $row['ID'];
                        if ($email!=$row['email']){
                            $errorFieldArr[] = array(
                                                        'id'  => 'emailError',
                                                        'msg' => 'Invalid Email' 
                                                    );
                        }
                    }
                    break;
            }

            if(!$sendType){
                $errorFieldArr[] = array(
                                            'id'  => 'sendTypeError',
                                            'msg' => 'Invalid Send Type'
                                        );
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            $checkResult = self::checkSendOTPAvailable($created_on, $client_id, $type, $sendType,$dialCode.$phoneNumber);
 
            if($checkResult != "true"){
                return array("status"=> "error", "code"=> 1, "statusMsg"=> str_replace("%%timeRange%%", $checkResult, $translations["E00869"][$language]), "data"=>"");
            }

            if(!$userLanguage)
                $userLanguage = $language;

            $otpCode = mt_rand(10000, 99999);
            $expire_at = date("Y-m-d H:i:s", strtotime(($created_on)." + ".$otpValidTime));//8 hours
            
            $db->where('id', $client_id);
            $countryRow = $db->getone('client', 'country_id');
            $countryID = $countryRow['country_id'];

            /*if($dialCode == 86 || $countryID =='44') $language = "chineseSimplified";
            
            if($dialCode == 886 || $countryID =='44' ) $language = "chineseTraditional";

            switch ($countryID) {
                ## japan
                case '107':
                    $language = "japanese";
                    break;
                 ## korea
                case '113':
                    $language = "korean";
                    break;
                case '44':
                    $language = "chineseSimplified";
                    break;
                
                case '96':
                    $language = "chineseTraditional";
                    break;
                default:
                    $language = "english";

                    break;
                
                
            }*/
            $companyName = "[".Setting::$configArray["companyName"]."] ";

            if($dialCodeIn == 886 || $dialCodeIn == 86 || $countryID == 44){
                $companyName = "";
            }
            switch($type){
                case "tradeToSeller":
                    $ad_no = (string)$params['ad_no'];
                    $content = str_replace("%%adNo%%", $ad_no, $translations["B00229"][$language]);
                break;

                case "tradeToBuyer":
                    $ad_no = (string)$params['ad_no'];
                    $content = str_replace("%%adNo%%", $ad_no, $translations["B00240"][$language]);
                break;

                case "tradeConfirmation":
                    $ad_no = (string)$params['ad_no'];
                    $content = str_replace("%%adNo%%", $ad_no, $translations["B00237"][$language]);
                break;

                case "tradePaid":
                    $content = $translations["B00238"][$language];
                break;

                case "tradeClear":
                    $content = $translations["B00239"][$language];
                break;

                case "dailyIncomeUpdate":
                    $bonusEarned = (string)$params['bonusEarned'];
                    $daysDeducted = (string)$params['daysDeducted'];
                    $daysLeft = (string)$params['daysLeft'];
                    $dateEarned = (string)$params['dateEarned'];
                    $content = str_replace(array("%%date%%", "%%bonusEarned%%", "%%daysDeducted%%", "%%daysLeft%%"), array($dateEarned, $bonusEarned, $daysDeducted, $daysLeft), $translations["B00231"][$language]);
                    break;

                case "adminUpdateWithdrawal":
                    $creditArray = $db->get('credit', NULL, 'name, translation_code');
                    foreach($creditArray as $creditItem){
                        $creditLangCode[$creditItem['name']] = $creditItem['translation_code'];
                    }

                    $creditDisplay = $translations[$creditLangCode[$params['creditType']]][$language];
                    $amount = (string)$params['amount'];

                    $content = str_replace(array("%%creditDisplay%%", "%%amount%%"), array($creditDisplay, $amount), $translations["B00232"][$language]);

                    break;

                case "withdrawal":
                    $text='A00888';//Withdrawal
                    $content = str_replace(array("%%company%%", "%%OTP%%"), array($companyName, $otpCode), $translations["B00288"][$language]);
                    $subject = 'Withdrawal';
                    break;

                case "transfer":
                    $text='M01539';//Transfer
                    $content = str_replace(array("%%company%%", "%%OTP%%", "%%module%%",'%%Expire_time%%'), array($companyName, $otpCode, $translations[$text][$language],$expire_time), $translations["B00180"][$language]);
                    $subject = $translations[$text][$language];
                    break;

                case "viewSponsorTree":
                    $text='M00134';//Diagram
                    $content = str_replace(array("%%company%%", "%%OTP%%", "%%module%%",'%%Expire_time%%'), array($companyName, $otpCode, $translations[$text][$language],$expire_time), $translations["B00180"][$language]);
                    $subject = $translations[$text][$language];
                    break;
                    
                case "accountSharing":
                    $content = str_replace(array("%%company%%", "%%OTP%%", "%%module%%",'%%Expire_time%%'), array($companyName, $otpCode, $translations[$text][$language],$expire_time), $translations["B00180"][$language]);
                    $subject = 'accountSharing';
                    break;

                case "resetPassword":
                case "resetTransactionPassword":
                    if($type == 'resetTransactionPassword') {
                        $text = "T00014";
                        $subject = 'Reset Transaction Password';
                    }
                    else{
                        $text = 'T00013';
                        $subject = 'Reset Password';
                    }
                    // $content = str_replace(array("%%company%%", "%%OTP%%"), array($companyName, $otpCode), $translations["B00288"][$language]);
                    //TODO : 
                    $content = "RM0 GoTasty.net : code ".$otpCode;
                    // $content = str_replace(array("%%company%%", "%%module%%", "%%OTP%%"), array($companyName, $translations[$text][$language], $otpCode), $translations["B00366"][$language]);
                    //return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $content);
                    $return = $phoneNumberIn;
                    break;

                case "register":
                    $subject = $translations['M02678'][$language];
                    //TODO : add translation
                    $content = "RM0 GoTasty.net : code ".$otpCode;
                    //$content = str_replace(array("%%company%%", "%%OTP%%"), array($companyName, $otpCode), $translations["B00288"][$language]);
                    $return = $email;
                    break;

                case "editProfile":
                    $subject = 'Edit Profile';
                    $content = str_replace(array("%%company%%", "%%OTP%%"), array($companyName, $otpCode), $translations["B00288"][$language]);
                    break;

                case "verifyPhone":
                    $subject = 'Verify Phone';
                    $content = str_replace(array("%%company%%", "%%OTP%%"), array($companyName, $otpCode), $translations["B00288"][$language]);
                    break;

                case "verifyKYC":
                    $subject = 'Verify KYC';
                    $content = Custom::resendAuthenticationContent($username,$otpCode);
                    $return = $email;
                    break;

                default:
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Type", 'data' => $data);
                    break;
            }

            if ($sendType=='email'){
                $recipient = $email;//recipient is email destination
                $result=Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType,'','','','',1);
            }
            else if ($sendType=='phone'){
                $recipient=$dialCode.$phoneNumber;
                $result=Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType);

            }

            if($result){
                $status = "Sent";

                $insertData = array(
                    "receiver_id"   => $client_id,
                    "msg"           => $content,
                    "data"          => $return,
                    "created_on"    => $created_on,
                    "msg_type"      => "OTP Code",
                    "status"        => $status,
                    "error_msg"     => "",
                    "phone_number"  => $dialCode.$phoneNumber,
                    "code"          => $otpCode,
                    "expired_at"    => $expire_at,
                    "verification_type"          => $type.'##'.$sendType,
                );

                $db->insert("sms_integration", $insertData);
            }else{
                return array("status"=> "error", "code"=> 1, "statusMsg"=> $translations['E00872'][$language], "data"=>'');
            }
            $data['resendOTPCountDown'] = strtotime($resendOTPCountDown,0);
            $data['otpCode'] = $otpCode;

            if($sendType=='phone'){
               return array("status"=> "ok", "code"=> 0, "statusMsg"=> str_replace("%%phone%%", $phoneNumber, $translations["B00218"][$language]), "data"=>$data);
            }else{
               return array("status"=> "ok", "code"=> 0, "statusMsg"=> $translations["B00247"][$language], "data"=>$data);
            }
        }

        function encodeHexStr($dataCoding, $realStr) {

            if ($dataCoding == 15)
            {
                return strtoupper(bin2hex(iconv('UTF-8', 'GBK', $realStr)));               
            }
            else if ($dataCoding == 3)
            {
                return strtoupper(bin2hex(iconv('UTF-8', 'ISO-8859-1', $realStr)));               
            }
            else if ($dataCoding == 8)
            {
                return strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $realStr)));   
            }
            else
            {
                return strtoupper(bin2hex(iconv('UTF-8', 'ASCII', $realStr)));
            }
        }

        public function postRestackNotification($dateTime){
            
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            // example:-
            // twelve_notifyer = 0 && 
            // day_left = 0 &&
            // expire_at >= 2019-05-28 03:31:47 && expire_at <= 2019-05-28 03:36:47
            // -12
            $between = "expire_at >= " . date('Y-m-d H:i:s', strtotime($dateTime . ' -36 hours -10 minutes')) . " && expire_at <= " . date('Y-m-d H:i:s', strtotime($dateTime . ' -12 hours'));
            // -18
            $between2 = "expire_at >= " . date('Y-m-d H:i:s', strtotime($dateTime . ' -42 hours -10 minutes')) . " && expire_at <= " . date('Y-m-d H:i:s', strtotime($dateTime . ' -18 hours'));

            // 12 hours remaining notification
            $db->where('twelve_notifyer', '0');
            $db->where('day_left', '0');
            $db->where('expire_at', date('Y-m-d H:i:s', strtotime($dateTime . ' -36 hours -10 minutes')), '>=');
            $db->where('expire_at', date('Y-m-d H:i:s', strtotime($dateTime . ' -36 hours')), '<=');
            $data1 = $db->get('mlm_client_portfolio', NULL, 'id, client_id');

            $otpParams['type'] = 'restakeAlert';

            if($data1) {
                $otpParams['hoursRemain'] = '12';
                foreach ($data1 as $value1) {
                    $state = 'not send';
                    $db->where('name', 'autoRestake');
                    $db->where('value', '0');
                    $db->where('client_id', $value1['client_id']);
                    $autoRestake = $db->getValue('client_setting', 'value');
                    if($autoRestake == '0') {
                        $updateAry1[] = $value1['id'];
                        self::sendOTPCode($otpParams, $value1['client_id']);
                        $state = 'send';
                    }
                    $trace[] = array('autoRestake' => $autoRestake, 'hoursRemain' => $otpParams['hoursRemain'], 'clientID' => $value1['id'], 'status' => $state);
                }
            }

            if($updateAry1) {
                $ary1 = array('twelve_notifyer' => '1');
                $db->where('id', $updateAry1, 'IN');
                $db->update('mlm_client_portfolio', $ary1);
            }

            // 6 hours remaining notification
            $db->where('six_notifyer', '0');
            $db->where('day_left', '0');
            $db->where('expire_at', date('Y-m-d H:i:s', strtotime($dateTime . ' -42 hours -10 minutes')), '>=');
            $db->where('expire_at', date('Y-m-d H:i:s', strtotime($dateTime . ' -42 hours')), '<=');
            $data2 = $db->get('mlm_client_portfolio', NULL, 'id, client_id');

            if($data2) {
                $otpParams['hoursRemain'] = '6';
                foreach ($data2 as $value2) {
                    $state = 'not send';
                    $db->where('name', 'autoRestake');
                    $db->where('value', '0');
                    $db->where('client_id', $value2['client_id']);
                    $autoRestake = $db->getValue('client_setting', 'value');
                    if($autoRestake == '0') {
                        $updateAry2[] = $value2['id'];
                        self::sendOTPCode($otpParams, $value2['client_id']);
                        $state = 'send';
                    }
                    $trace[] = array('autoRestake' => $autoRestake, 'hoursRemain' => $otpParams['hoursRemain'], 'clientID' => $value2['id'], 'status' => $state);
                }
            }

            if($updateAry2) {
                $ary2 = array('six_notifyer' => '1');
                $db->where('id', $updateAry2, 'IN');
                $db->update('mlm_client_portfolio', $ary2);
            }

            return array("status"=> "ok", "code"=> 0, "time" => $dateTime, "condition"=> array($between, $between2), "trancer"=> $trace);

        }

        function sendTwilioSMS($params, $clientID){

            $db = MysqliDb::getInstance();
            
            /* Purchased Phone Number */
            $twilio_number = (string)Setting::$systemSetting["twilio_number"];

            /* Receiver Phone Number */
            $receiver = (string)$params["receiver"];

            /*.Twilio Account Sid & Auth Token */
            $account_sid = (string)Setting::$systemSetting["smsAccountSID"];
            $auth_token = (string)Setting::$systemSetting["smsAuthToken"];


            if(!empty($twilio_number) && !empty($receiver)) {

                $msgContent = (string)$params["msgContent"];     

                /* Build Message Content */
                $body = $msgContent;

                /* Send SMS */
                $client = new Client($account_sid, $auth_token);
                
                try {
                    $message = $client->messages->create(
                        // Where to send a text message (your cell phone?)
                        '+'.$receiver,
                        array(
                            'from' => $twilio_number,
                            'body' => $body
                        )
                    );

                } catch (Twilio\Exceptions\RestException $e) {
                    $statusErrorCode = $e->getCode();
                    $statusReturnCode = $e->getStatusCode();
                    $statusErrorMessage = $e->getMessage();
                }

                /* Check Error Code */
                if(!empty($statusErrorCode)){

                    /* Return Call Back Error */
                    $result["errorCode"] = $statusErrorCode;
                    $result["returnCode"] = $statusReturnCode;
                    $result["errorMessage"] = $statusErrorMessage;

                    /* Failed to send SMS! Please try again later. */
                    return array("status"=> 'error', "code"=> 1, "statusMsg"=> "Failed to send SMS! Please try again later.", "data" => $result, "field" => "phone");
                }
                else{

                    /* Return Completed Result */
                     return array("status"=> 'ok', "code"=> 1, "statusMsg"=> "Sent Successfully!", "data" => "");
                }
            }
            else{
                /* Return Error */
                return array("status"=> 'error', "code"=> 1, "statusMsg"=> "Failed to send SMS! Please fill in the receiver.", "data"=>'', "field" => "phone");
            }
        }

        public function verifyOTPCode($clientID,$otpType,$module,$otpCode,$phoneNumber){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $superOTP = Setting::$configArray['superOTP'];
            $superOTP = "12345";

            //super otp can by pass checking
            if($otpCode == $superOTP && $superOTP != "") return array('status' => "ok", 'code' => 1, 'statusMsg' => "", 'data' => "");

            if($module == 'register'){
                $db->where('phone_number', $phoneNumber);
            }else{
                // $db->where('data', $clientID['phone']);
                $db->where('data', $phoneNumber);
                //$db->where('receiver_id', $clientID['phone']);
            }
            $db->where('expired_at', date('Y-m-d H:i:s'),'>=');
            $db->where('verification_type', $module.'##'.$otpType);
            $db->where('status','Sent');
            $db->orderBy('ID', 'DESC');
            $row = $db->get("sms_integration", null,"id,code");

            foreach ($row as $checkRow) {
                $code = $checkRow['code'];
                $smsID[] = $checkRow['id'];

                if ($code == $otpCode){
                    $checkedOTP = 1;
                } 
            }
            if($checkedOTP){

                // make OTP become invalid
                $db->where('expired_at', date('Y-m-d H:i:s'),'>=');
                $db->where('verification_type', $module.'##'.$otpType);
                $db->where('status','Sent');
                $db->where('phone_number', $phoneNumber);
                $fields = array("expired_at");
                $values = array(date('Y-m-d H:i:s.u'));
                $arrayData = array_combine($fields, $values);
                $row = $db->update("sms_integration", $arrayData);
                // return array('status' => "ok", 'code' => 1, 'statusMsg' => "", 'data' => $smsID);
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00864"][$language] /*Invalid OTP code*/, 'data' => "");
            }              
        }

        function checkSendOTPAvailable($currentTime, $clientID,$module,$sendType,$phoneNumber) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $resendOTPCountDown = Setting::$systemSetting["resendOTPCountDown"];
            $verification_type = $module."##".$sendType;
            $validTime = date('Y-m-d H:i:s',strtotime($currentTime." - ".$resendOTPCountDown));

            if($module == 'register'){
                $db->where('phone_number', $phoneNumber);
            }else{
                $db->where('receiver_id', $clientID);
            }
            $db->where('created_on', $validTime,'>=');
            $db->where('verification_type',$verification_type);
            $db->where('status','Success');
            $db->orderBy('ID', 'DESC');
            $created_on = $db->getValue('sms_integration','created_on');

            if($created_on){
                $interval = strtotime($created_on) - strtotime($validTime);
                $timeRemain = (floor($interval/60))." ".$translations['E00870'][$language]." ".($interval%60)." ".$translations['E00871'][$language];
                return $timeRemain;
            }else{
                return true;
            }

        }

        function checkPhoneNumber($phoneNumber,$regionCode){

            require dirname(__DIR__) . '/vendor/autoload.php';
            $phoneUtil = libphonenumber\PhoneNumberUtil::getInstance();

            try {
                $swissNumberProto = $phoneUtil->parse($phoneNumber, $regionCode);
                $isValid = $phoneUtil->isValidNumber($swissNumberProto);

                if($isValid){
                    return true;
                }else{
                    return false;
                }

            } catch (\libphonenumber\NumberParseException $e) {

                return false;
            }
        }

        public function validateKYCOTP($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientId = $db->userID;
            $otpType = 'email';
            $module = 'verifyKYC';
            $otpCode = $params['otpCode'];

            $checkOTP = self::verifyOTPCode($clientId,$otpType,$module,$otpCode,$phoneNumber);

            if($checkOTP['status'] == 'ok'){
                $statusAry = array('status'=>'Success');
                $db->where('code',$otpCode);
                $db->where('verification_type', $module.'##'.$otpType);
                $db->where('receiver_id',$clientId);
                $updateStatus = $db->update('sms_integration',$statusAry);

                $verifyEmail = array('email_verified' => '1');
                $db->where('client_id', $clientId);
                $updateClientDetail = $db->update('client_detail', $verifyEmail);
            }else{
                return $checkOTP;
            }

            if($updateStatus && $updateClientDetail){
                return array('status' => "ok", 'code' => 1, 'statusMsg' => $translations['B00509'][$language] /*Verify Successful*/, 'data' => "");
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['B00510'][$language] /*Verify Failed*/, 'data' => "");
            }
        }

	}
?>
