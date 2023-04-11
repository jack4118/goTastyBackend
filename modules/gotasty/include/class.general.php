<?php
	
	class General
    {
        
        // private $currentLanguage = 'english';
        public static $translations; // declare
        public static $currentLanguage = "english"; // declare
        public static $userAgent; // declare
        public static $ip; // declare
        public static $source; // declare
        public static $deviceDetail; // declare
        function __construct() {
            // Self::db = $db;
            // Self::setting = $setting;
            
            // Self::creatorID = "";
            // Self::creatorType = "";
        }
        
        public function validatePassword($password)
        {
            // global $setting;

            /*
             Explaining $\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])(?=\S*[\W])\S*$
             $ = beginning of string
             \S* = any set of characters
             (?=\S{8,}) = of at least length 8
             (?=\S*[a-z]) = containing at least one lowercase letter
             (?=\S*[A-Z]) = and at least one uppercase letter
             (?=\S*[\d]) = and at least one number
             (?=\S*[\W]) = and at least a special character (non-word characters)
             $ = end of the string
             */

            if (!preg_match('$\S*(?=\S{' . Setting::$sysSetting["minPasswordLength"] . ',})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $password))
                return false;
            return true;
        }

        // take out number from string
        public function onlyNumber($str)
        {
            $number = preg_replace("/[^0-9]/", '', $str);

            return $number;
        }

        public function validateEmail($email)
        {
            if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $email))
                return true;
            else
                return false;
        }

        public function validatePostCode($postcode)
        {
            if ((preg_match('/^\d{0,}$/', $postcode)))
                return true;
            else return false;
        }

        public function generateAlpaNumeric($length)
        {
            $str = str_shuffle('abcefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');

            return substr($str, 0, $length);
        }

        public function generateRandomNumber($length)
        {

            $numberAllow = '1234567890';
            $generate = '';
            $generateTmp = '';
            $strTmp = '';
            $str = '';
            $i = 0;

            while ($i < $length) {
                $generate = str_shuffle($numberAllow);
                $generateTmp = substr($generate, 0, 1);
                if ($strTmp != $generateTmp) {
                    unset($strTmp);
                    $strTmp = $generateTmp;
                    $str .= $generateTmp;
                    $i++;
                    unset($generateTmp);
                }
            }
            return $str;
        }

        public function phoneNumberWeKeep($phoneNumber)
        {

            if (is_array($phoneNumber)) {

                $phoneNumber = explode(";", $phoneNumber);
                for ($i = 0; $i < count($phoneNumber); $i++) {
                    $phoneNumber[$i] = trim(Self::onlyNumber($phoneNumber[$i]));
                    ######## for Malaysia only #########
                    // add 0
                    if (strlen($phoneNumber[$i]) == "9" && substr($phoneNumber[$i], 0, 1) == "1")
                        $phoneNumber[$i] = '0' . $phoneNumber[$i];
                    if (strlen($phoneNumber[$i]) == "10" && substr($phoneNumber[$i], 0, 2) == "11")
                        $phoneNumber[$i] = '0' . $phoneNumber[$i];
                    // add 6
                    (substr($phoneNumber[$i], 0, 2) == "01") ? $phoneNumber[$i] = "6" . $phoneNumber[$i] : '';
                    #####################################
                }
                $phoneNumber = implode(";", $phoneNumber);

            }

            return $phoneNumber;
        }

        ######################### VALID PHONE NUMBER ###############
        public function mobileNumberInfo($phone, $clientRegionCode)
        {

            $phone = Self::numberOnly($phone);
            $regionCode = "";
            $countryCode = "";
            $countryName = "";

            if (substr($phone, 0, 1) != 0) $phone = "+" . $phone;

            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            try {
                $mobileNumberDetails = $phoneUtil->parse($phone, $clientRegionCode);
                $countryCode = $mobileNumberDetails->getCountryCode();
                $regionCode = $phoneUtil->getRegionCodeForNumber($mobileNumberDetails);
                $phone = $phoneUtil->format($mobileNumberDetails, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);

                $xml = simplexml_load_file(__DIR__ . "/lang_world_country.xml");
                foreach ($xml->language->item as $items) {
                    if ($items['phone_code'] == $countryCode && $items['country_code'] == $regionCode) {
                        $countryName = (string)$items;
                        break;
                    }
                }

                //var_dump($swissNumberProto);
            } catch (\libphonenumber\NumberParseException $e) {
                // var_dump($e);
                return array(
                    "isValid" => 0,
                    "mobileNumberFormatted" => $phone,
                    "phone" => Self::numberOnly($phone),
                    "countryCode" => $countryCode,
                    "regionCode" => $regionCode,
                    "countryName" => $countryName,
                );
            }

            $isValid = 0;
            if ($phoneUtil->isValidNumber($mobileNumberDetails)) {
                // 0:FIXED_LINE
                // 1:MOBILE
                // 2:FIXED_LINE_OR_MOBILE
                // 10:UNKNOWN
                // 27:EMERGENCY
                if ($phoneUtil->getNumberType($mobileNumberDetails)) $isValid = 1;
            }

            return array(
//                          "asd" => $phoneUtil->isValidNumber($mobileNumberDetails),
                "isValid" => $isValid,
                "mobileNumberFormatted" => $phone,
                "phone" => Self::numberOnly($phone),
                "countryCode" => $countryCode,
                "regionCode" => $regionCode,
                "countryName" => $countryName,
            );
        }

        public function generateApiKey($clientID)
        {
            return md5($clientID.time());
        }

        /**
         *
         * Get the Limit value.
         * @param $pageNumber Integer.
         *
        **/
        public function getLimit($pageNumber = NULL, $pageLimit, $rowAmount = NULL)
        {
            global $setting;

            $pagingLimit = $pageLimit ? $pageLimit : Setting::$systemSetting["superAdminPageLimit"];

            if($rowAmount > 0) $pagingLimit = $pagingLimit * $rowAmount;
            $startLimit  = ($pageNumber-1) * $pagingLimit;
            $limit       = array($startLimit, $pagingLimit);

            return $limit;
        }

        //For getting the Language.
        public function getLanguage() {
            if (isset($_SESSION['language'])) {
                $language = $_SESSION['language'];
            } else {
                if(isset($_COOKIE["language"])) {
                    $_SESSION['language'] = $_COOKIE['language'];
                    $language = $_COOKIE['language'];
                } else {
                    $_SESSION['language'] = "english";
                    $language = "english";
                    setcookie("language", "english");
                }
            }
            return $language;
        }
        
        // Getting the timezone offset difference
        public function formatDate($offsetSecs, $timestamp) {
            $serverTime = date('Z');
            $timeDiff = $serverTime + $offsetSecs;
            
            return date(Setting::getDateFormat(), $timestamp - $timeDiff);
        }
        
        public function formatDateTime($offsetSecs, $timestamp) {
            $serverTime = date('Z');
            $timeDiff = $serverTime + $offsetSecs;
            
            return date(Setting::getDateTimeFormat(), $timestamp - $timeDiff);
        }
        
        
        // Convert from front to back, need to add
        // Convert from back to display at front, need to minus
        // Getting the timezone offset difference
        /*
         * $offsetSecs will be the UTC timezone from the front in seconds
         * 
         * $dateTimeString will be in this format
         * $from = 0 -->  Y-m-d H:i:s
         * $from = 1 -->  d/m/y H:i:s A
         * 
         * $from will be the conversion from where
         * 0 - To display from backend to frontend
         * 1 - To convert back from frontend to backend
         *
         * $format will be the date format for this output
         */
        public function formatDateTimeString($offsetSecs, $dateTimeString, $format="Y-m-d H:i:s") {
            $dateTs = strtotime($dateTimeString);
            if($dateTs < 0)
                return;
            $serverTime = date('Z');
            
            // Check for timezone setting
            if (Setting::getTimezoneSetting()) {
                $timeDiff = $serverTime + $offsetSecs;
                $newTs = $dateTs - $timeDiff;
                return date($format, $newTs);
            }
            else {
                return date($format, $dateTs);
            }
        }

        public function formatDateTimeToString($dateTimeString, $format="") {
            
            $dateTs = strtotime($dateTimeString);
            if($dateTs < 0)
                return false;

            if($format == "")
                $format = strlen(Setting::$systemSetting['systemDateTimeFormat']) > 0 ? Setting::$systemSetting['systemDateTimeFormat'] : "d/m/Y h:i:s A";
            
            return date($format, $dateTs);
        }

        public function getWalletValidity($creditType) {

            $creName = array(
                                "bitcoin"           => "BTC",
                                "bitcoinCash"       => "BCH",
                                "ethereum"          => "ETH",
                                "litecoin"          => "LTC",
                                "tether"            => "USDC",
                                "czoCredit"         => "CZO"
                            );

            if(array_key_exists($creditType, $creName)) {

                $isCryto = 1;

            }

            else {

                $isCryto = 0;

            }

            return array("creditType" => $creName[$creditType], "isCryto" => $isCryto);

        }


        function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
            $output = NULL;
            if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
                $ip = $_SERVER["REMOTE_ADDR"];
                if ($deep_detect) {
                    if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
                        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                    if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
                        $ip = $_SERVER['HTTP_CLIENT_IP'];
                }
            }
            $purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
            $support    = array("country", "countrycode", "state", "region", "city", "location", "address");
            $continents = array(
                "AF" => "Africa",
                "AN" => "Antarctica",
                "AS" => "Asia",
                "EU" => "Europe",
                "OC" => "Australia (Oceania)",
                "NA" => "North America",
                "SA" => "South America",
                "SG" => "Singapore",
                "CN" => "China",
                "MY" => "Malaysia",
            );
            if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
                $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
                if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
                    switch ($purpose) {
                        case "location":
                            $output = array(
                                "city"           => @$ipdat->geoplugin_city,
                                "state"          => @$ipdat->geoplugin_regionName,
                                "country"        => @$ipdat->geoplugin_countryName,
                                "country_code"   => @$ipdat->geoplugin_countryCode,
                                "continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                                "continent_code" => @$ipdat->geoplugin_continentCode
                            );
                            break;
                        case "address":
                            $address = array($ipdat->geoplugin_countryName);
                            if (@strlen($ipdat->geoplugin_regionName) >= 1)
                                $address[] = $ipdat->geoplugin_regionName;
                            if (@strlen($ipdat->geoplugin_city) >= 1)
                                $address[] = $ipdat->geoplugin_city;
                            $output = implode(", ", array_reverse($address));
                            break;
                        case "city":
                            $output = @$ipdat->geoplugin_city;
                            break;
                        case "state":
                            $output = @$ipdat->geoplugin_regionName;
                            break;
                        case "region":
                            $output = @$ipdat->geoplugin_regionName;
                            break;
                        case "country":
                            $output = @$ipdat->geoplugin_countryName;
                            break;
                        case "countrycode":
                            $output = @$ipdat->geoplugin_countryCode;
                            break;
                    }
                }
            }
            return $output;
        }


        public function addExcelReq($params){
            $db = MysqliDb::getInstance();

            if(!$params['command'])
                return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid Command", 'data' => "");

            if(!$params['params'] || !is_array($params['params']))
                return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid Filter", 'data' => "");

            if(!$params['type'])
                return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid file Type", 'data' => "");

            if(!$params['titleKey'])
                return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid title", 'data' => "");

            if(!$params['headerAry'] || !is_array($params['headerAry']))
                return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid header", 'data' => "");

            if(!$params['fileName'])
                return array('status' => "error", 'code' => 0, 'statusMsg' => "Invalid fileName", 'data' => "");

            // incase API != function name
            $replaceAPI = array("getFundInListing" => "getFundInListing2");

            $command = $params['command'];
            if($replaceAPI[$params['command']]){
                $command = $replaceAPI[$params['command']];
            }

            $fileName = $params['fileName']."_".date("Ymd_His").".xlsx";
            $insert = array(
                "command" => $command,
                "params" => json_encode($params['params']), // filter search
                "type" => $params['type'], // excel
                "file_name" => $fileName,
                "title_key" => $params['titleKey'], // data[transactionList]
                "header_ary" => json_encode($params['headerAry']), // headerDisplay
                "key_ary" => ($params['keyAry']? json_encode($params['keyAry']):""), // keyToRearrange
                "total_ary" => ($params['totalAry']? json_encode($params['totalAry']):""), //keyToSumUp
                "creator_id" => $this->creatorID,
                "creator_type" => $this->creatorType,
                "status" => "Pending",
                "created_at" => date("Y-m-d H:i:s"),
            );

            $db->insert("mlm_export", $insert);
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Update Successfully", 'data' => "");
        }

        function getLatestUnitPrice($type) {
            $db = MysqliDb::getInstance();
            $tableName  = 'mlm_unit_price';

            if($type){
                $db->where('type', $type);
            }else{
                $db->where('type', 'purchase');
            }
            
            $db->orderBy('created_at', 'DESC');
            $unitPrice = $db->getValue($tableName, 'unit_price');
            if($unitPrice)
                return $unitPrice;
            
            return 1.00;
        }

        function getTranslationByName($varText){
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            switch ($varText) {

                case 'Admin':
                    return $translations['B00273'][$language];//Admin
                    break;

                case 'System':
                    return $translations['B00274'][$language];//System
                    break;

                case 'Disable':
                    return $translations['A01610'][$language];//Disable
                    break;

                case 'Active':
                    return $translations['A00372'][$language];//Active
                    break;

                case 'Inactive':
                case 'inactive':
                    return $translations['A00373'][$language];//Inactive
                    break;

                case 'bank':
                    return $translations['M00467'][$language]; /* Bank */
                    break;

                case 'crypto':
                    return $translations['M00909'][$language]; /* Wallet Address */
                    break;

                case 'Waiting Approval':
                    return $translations["M00652"][$language]; /* Waiting Approval */
                    break;

                case 'Approved':
                case 'Approve':
                    return $translations["M00498"][$language]; /* Approve */
                    break;
                
                case 'Reject':
                case 'Rejected':
                    return $translations["A01187"][$language]; /* Reject */
                    break;
                
                case 'Cancel':
                    return $translations["A00660"][$language]; /* Cancel */
                    break;
                
                case 'Pending':
                    return $translations["M00500"][$language]; /* Pending */
                    break;

                case "Adjustment In":
                    return $translations["B00187"][$language]; /*Admin Adjust In*/
                    break;

                case "Adjustment Out":
                    return $translations["B00188"][$language]; /*Admin Adjust Out*/
                    break;

                case "Admin Fee":
                    return $translations["B00194"][$language]; /*Admin Fee*/
                    break;

                case "Transfer To Btc":
                    return $translations["B00200"][$language]; /*Stake Ended*/
                    break;

                case "Transfer From Stake":
                    return $translations["B00201"][$language]; /*Stake Ended*/
                    break;

                case "Withdrawal Return":
                    return $translations["B00221"][$language]; /*Withdrawal Return*/
                    break;

                case "Admin Charge":
                    return $translations["B00223"][$language]; /*Admin Charges*/
                    break;

                case "Withdrawal":
                    return $translations["B00222"][$language]; /*Withdrawal*/
                    break;

                case "Transfer In":
                    return $translations["B00225"][$language]; /*Transfer In*/
                    break;

                case "Transfer Out":
                    return $translations["B00226"][$language]; /*Transfer Out*/
                    break;

                case "Fund In Coin":
                    return $translations["B00236"][$language]; /*Fund In Coin*/
                    break;

                case 'Package Cashback':
                    return $translations["M01632"][$language]; /*Package Cashback*/
                    break;

                case 'Package Purchase':
                    return  $translations["B00246"][$language]; /*Package Purchase*/
                    break;

                case 'Convert Credit':
                    return  $translations["M00017"][$language]; /*Convert Coins*/
                    break;

                case 'Redeem Prize':
                    return  $translations["M01739"][$language]; /*Redeem Prize*/
                    break;

                case 'Convert Package':
                    return  $translations["M01767"][$language]; /*Convert Package*/
                    break;

                case 'Convert Payout':
                    return  $translations["M01769"][$language]; /*Convert Payout*/
                    break;

                case 'Package Reentry':
                    return  $translations["B00275"][$language]; /*Package Reentry*/
                    break;

                case 'Credit Reentry':
                    return  $translations["A01425"][$language]; /*Credit Reentry*/
                    break;

                case 'Purchase Pin':
                    return  $translations["B00276"][$language]; /*Package Reentry*/
                    break;

                case 'Paid':
                    return $translations["B00456"][$language]; /*Paid*/
                    break;

                case 'Refunded':
                    return $translations["B00280"][$language]; /*Refunded*/
                    break;

                case 'Processing':
                    return $translations["B00291"][$language]; /*Processing*/
                    break;

                case 'Scheduled':
                    return $translations["B00292"][$language]; /*Scheduled*/
                    break;

                // case 'Completed':
                //     return $translations["B00293"][$language]; /*Completed*/
                //     break;

                case 'Removed':
                    return $translations["B00294"][$language]; /*Removed*/
                    break;

                case 'Queue':
                    return $translations["B00310"][$language]; /*Queue*/
                    break;

                case 'Buy':
                case 'buy':
                    return $translations["B00296"][$language]; /*Buy*/
                    break;

                case 'Sell':
                case 'sell':
                    return $translations["B00297"][$language]; /*Sell*/
                    break;

                case 'Add Ads':
                    return $translations["B00298"][$language]; 
                    break;

                case 'Cancel Ads':
                    return $translations["B00299"][$language];
                    break;

                case 'Purchase Ads':
                    return $translations["B00300"][$language]; 
                    break;

                case 'Sell Stock':
                    return $translations["B00301"][$language]; 
                    break;

                case 'Buy Stock':
                    return $translations["B00302"][$language]; 
                    break;

                case 'Buy Stock Transaction Fees':
                    return $translations["B00303"][$language]; 
                    break;

                case 'Matched Buy Stock':
                    return $translations["B00304"][$language]; 
                    break;

                case 'Matched Sell Stock':
                    return $translations["B00305"][$language]; 
                    break;

                case 'Matched Sell Stock Transaction Fee':
                    return $translations["B00306"][$language]; 
                    break;

                case 'Refund Stock':
                    return $translations["B00307"][$language]; 
                    break;

                case 'Refund Stock Transaction Fee':
                    return $translations["B00307"][$language]; 
                    break;

                case 'P2P Sell Order':
                    return $translations["M02442"][$language]; 
                    break;

                case 'P2P Buy Order':
                    return $translations["M02443"][$language]; 
                    break;

                case 'P2P Received from Buy Ads':
                    return $translations["M02444"][$language]; 
                    break;

                case 'P2P Received from Sell Ads':
                    return $translations["M02445"][$language]; 
                    break;

                case 'P2P Processing Fee':
                    return $translations["M02446"][$language]; 
                    break;

                case 'Return P2P Processing Fee':
                    return $translations["M02447"][$language]; 
                    break;

                case 'Refund Stock Buy':
                    return $translations["B00311"][$language]; 
                    break;

                case 'Refund Stock Sell':
                    return $translations["B00312"][$language]; 
                    break;

                case 'Refund Stock Transaction Fee Buy':
                    return $translations["B00313"][$language]; 
                    break;

                case 'Refund Stock Transaction Fee Sell':
                    return $translations["B00314"][$language]; 
                    break;

                case 'Cancel Stock Buy':
                    return $translations["B00315"][$language]; 
                    break;

                case 'Cancel Stock Sell':
                    return $translations["B00316"][$language]; 
                    break;

                case 'Cancel Stock Transaction Fee Buy':
                    return $translations["B00317"][$language]; 
                    break;

                case 'Cancel Stock Transaction Fee Sell':
                    return $translations["B00318"][$language]; 
                    break;

                case 'P2P Sell Order Fee':
                    return $translations["B00319"][$language]; 
                    break;

                case 'P2P Buy Order Fee':
                    return $translations["B00320"][$language]; 
                    break;

                case 'P2P Sell Ads Fee':
                    return $translations["B00321"][$language]; 
                    break;

                case 'P2P Buy Ads Fee':
                    return $translations["B00322"][$language]; 
                    break;

                case 'Return Buy Ads Fee':
                    return $translations["B00323"][$language]; 
                    break;

                case 'Return Sell Ads Fee':
                    return $translations["B00324"][$language]; 
                    break;

                case 'Cancel Buy Ads':
                    return $translations["B00325"][$language]; 
                    break;

                case 'Cancel Sell Ads':
                    return $translations["B00326"][$language]; 
                    break;

                case 'P2P Buy Ads':
                    return $translations["B00327"][$language]; 
                    break;

                case 'P2P Sell Ads':
                    return $translations["B00328"][$language]; 
                    break;

                case 'P2P Buy Ads Fee':
                    return $translations["B00329"][$language]; 
                    break;

                case 'P2P Sell Ads Fee':
                    return $translations["B00330"][$language]; 
                    break;

                case 'Maintainance Fee':
                    return $translations["B00331"][$language]; 
                    break;

                case 'USDT Fund In':
                    return $translations["B00332"][$language]; 
                    break;

                case 'Purchase Credit':
                    return $translations["B00336"][$language]; 
                    break; 

                case 'await':
                    return $translations["B00345"][$language]; 
                    break;

                case 'market':
                    return $translations["B00346"][$language]; 
                    break;

                case 'split':
                    return $translations["B00347"][$language]; 
                    break;

                case 'drop':
                    return $translations["B00348"][$language]; 
                    break;

                case 'jackpot':
                    return $translations["B00349"][$language]; 
                    break; 

                case 'sold':
                    return $translations["B00350"][$language]; 
                    break; 

                case 'Treasure Wages':
                    return $translations["B00360"][$language]; 
                    break; 

                case 'Entry Ship':
                    return $translations["B00361"][$language]; 
                    break; 

                case 'await':
                    return $translations['A01523'][$language]; /* Await */
                    break;

                case 'departed':
                    return $translations['A01524'][$language]; /* Departed */
                    break;

                case 'process':
                    return $translations['A01420'][$language] /* Processing */;
                    break;

                case 'To Pick':
                    return $translations['B00382'][$language] /* To Pickup */;
                    break;

                case 'To Ship':
                    return $translations['B00383'][$language] /* To Ship */;
                    break;

                case 'To Receive':
                    return $translations['B00384'][$language] /* To Receive */;
                    break;

                case 'Completed':
                case 'completed':
                    return $translations['B00385'][$language] /* Completed */;
                    break;

                case 'Partial':
                case 'partial':
                    return $translations['B00420'][$language] /* Partial */;
                    break;

                case 'Matured':
                    return $translations["B00387"][$language]; /* Matured */
                    break;

                case 'delivery':
                    return $translations["B00391"][$language]; /* Delivery */
                    break;

                case 'pickup':
                    return $translations["B00392"][$language]; /* Pickup */
                    break;

                case 'Refund':
                    return $translations["B00393"][$language]; /* Refund */
                    break;

                case 'refund':
                    return $translations["A00661"][$language]; /* Refund */
                    break;

                case 'Bonus Backpay':
                    return $translations["B00394"][$language]; /*  Release Witholding */
                    break;

                case 'Jackpot Pool Adjustment In':
                    return $translations["A01558"][$language]; /*  Jackpot Pool Adjustment In */
                    break;

                case 'Jackpot Pool Adjustment Out':
                    return $translations["A01559"][$language]; /*  Jackpot Pool Adjustment Out */
                    break;

                case 'King Pool Adjustment In':
                    return $translations["A01560"][$language]; /*  Jackpot Pool Adjustment Out */
                    break;

                case 'King Pool Adjustment Out':
                    return $translations["A01561"][$language]; /*  Jackpot Pool Adjustment Out */
                    break;
                    
                case 'Refund Portfolio':
                    return $translations["M03042"][$language]; /*  Package Refund */
                    break;

                case 'ezPayment':
                    return $translations["B00405"][$language]; /*  Easy Payment */
                    break;

                case 'fullPayment':
                    return $translations["B00406"][$language]; /*  Full Payment */
                    break;

                case 'normal':
                    return $translations['B00413'][$language]; /* Normal */
                    break;

                case 'normal room':
                    return $translations['M03081'][$language]; /* Normal Room */
                    break;

                case 'private room':
                    return $translations['M03082'][$language]; /* Private Room */
                    break;

                case 'package':
                    return $translations['B00417'][$language]; /* Package */
                    break;

                case 'product':
                    return $translations['B00418'][$language]; /* Product */
                    break;

                case 'directorAward':
                    return $translations['B00423'][$language]; /* Director Award */
                    break;

                case 'unicornAward':
                    return $translations['B00424'][$language]; /* Unicorn Award */
                    break;

                case "male":
                    return $translations["B00257"][$language]; /* Male */
                    break;

                case "female":
                    return $translations["B00258"][$language]; /* Female */
                    break;

                case "nric":
                    // return $translations["B00252"][$language]; /* Identity Card */
                    return $translations["B00457"][$language]; /* KTP Number */
                    break;

                case "passport":
                    return $translations["B00253"][$language]; /* Passport */
                    break;     

                case "single":
                    return $translations["M03171"][$language]; /* Single */
                    break;                     

                case "married":
                    return $translations["M03172"][$language]; /* Married */
                    break;  

                case "widowed":
                    return $translations["M03173"][$language]; /* Widowed */
                    break;  

                case "divorced":
                    return $translations["M03174"][$language]; /* Divorced */
                    break; 

                case "separated":
                    return $translations["M03175"][$language]; /* Separated */
                    break;

                case 'paid':
                    return $translations['B00426'][$language]; /* Paid */
                    break;

                case 'unpaid':
                    return $translations['B00427'][$language]; /* Unpaid */
                    break;                     

                case "ID Verification":
                    return $translations["M03298"][$language]; /* ID Verification */
                    break;  

                case "Bank Account Cover":
                    return $translations["M03300"][$language]; /* Bank Account Cover */
                    break;  

                case "NPWP Verification":
                    return $translations["M03302"][$language]; /* NPWP Verification */
                    break;  

                case "Sold Out":
                    return $translations["B00479"][$language]; /* Sold Out */
                    break;

                case "PG Paid":
                    return $translations["B00500"][$language]; /* Success */
                    break;

                case "PG Expired":
                    return $translations["B00503"][$language]; /* Credit to Wallet */
                    break;

                case "PG Under Paid":
                case "PG Failed":
                    return $translations["B00502"][$language]; /* Failed */
                    break;

                case "PG Matured":
                    return $translations["B00501"][$language]; /* Expired */
                    break;

                case "PG Pending":
                    return $translations["B00499"][$language]; /* Pending for Payment */
                    break;

                case "VirtualAccount":
                    return $translations["B00505"][$language]; /* Virtual Account */
                    break;

                default:
                    return $varText;
                    break;
            }
        }

        function generateUniqueChar($tableName,$columnName){
            $db = MysqliDb::getInstance();

            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            while (1) {
                $uniqueChar =  substr(str_shuffle($permitted_chars), 0, 16);
                if(is_array($columnName)){
                    foreach ($columnName as $columnNameData) {
                        $db->where($columnNameData, '%'.$uniqueChar.'%', 'LIKE','OR');
                    }
                }else{
                    $db->where($columnName, '%'.$uniqueChar.'%', 'LIKE');
                }
                $count = $db->has($tableName);

                if ($count == 0) break;
            }
            return $uniqueChar;
        }

        function insertDailyTable($tableName, $type, $date){
            $db = MysqliDb::getInstance();

            if($type == "process"){
                if(strtotime(date("H:i")) < strtotime(date("23:58")) || strtotime(date("H:i")) > strtotime(date("23:59"))){
                    return false;
                }

                $date = date("Y-m-d");
                $date = date("Y-m-d",strtotime($date." + 1 days"));
            }

            if(!$date){
                $date = date("Y-m-d");
            }

            if(!$tableName){
                return false;
            }

            $tblDate = date("Ymd", strtotime($date));
            $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS ".$tableName."_".$db->escape($tblDate)." LIKE ".$tableName);

            return true;
        }

        function getSystemSettingAdmin($type,$dateTime,$refID = NULL){
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');

            if($refID) $db->where('ref_id',$refID);
            $db->where('active_at',$dateTime,"<=");
            $db->where('type',$type);
            $db->where('status','Active');
            $db->orderBy('active_at','ASC');
            $db->orderBy('id','ASC');
            $res = $db->map('name')->get("system_settings_admin",NULL,"name,value,reference,active_at");

            return $res;
        }

        function generateAllReferenceNo($tableName, $column) {
            $db = MysqliDb::getInstance();
            
            // Get the length setting
            $referenceNoLength = Setting::$systemSetting['referenceNumberLength']?:8;

            $min = "1"; $max = "9";

            for($i=1;$i<$referenceNoLength;$i++){
                $max .= "9";
                $min .= "0";
            }

            while (1) {
                $referenceNo = sprintf("%0".$referenceNoLength."s", mt_rand((int)$min, (int)$max));
                
                $db->where($column, $referenceNo);
                $count = $db->getValue($tableName, 'count(*)');
                if ($count == 0) break;
                // If exists, continue to generate again
            }

            return $referenceNo;
        }

        function getUsernameByID($clientID) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if (!$clientID) return false;

            if (is_array($clientID)) {
                $db->where("id", $clientID, "IN");
            } else {
                $db->where("id", $clientID);
            }
            
            $clientAry = $db->map("id")->get("client", NULL, "id, username");

            return $clientAry;
        }

        function generateSponsorCode(){
        	$db = MysqliDb::getInstance();
        	$codeLength = Setting::$systemSetting["sponsorCodeLength"];
        	if(!$codeLength) $codeLength = 6;
	        $characters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
	        $max        = strlen($characters) - 1;
	        
	        while(1){ 
	        	$code = "";
		        for ($i = 0; $i < $codeLength; $i++) {
		            $code .= $characters[mt_rand(0, $max)];
		        }
		        $db->where('sponsor_code',$code);
                $check = $db->getValue('client','COUNT(id)');
                if(!$check) break;
	    	}

	        return $code;
        }

        function generateDynamicCode($code,$codeLength,$table,$column,$randomCode){
            $db = MysqliDb::getInstance();
            
            if(!$codeLength) $codeLength = 5;
            if(!$table) $table = "language_translation";
            if(!$column) $column = "code";

            if($randomCode){
                $min = "1"; $max = "9";

                for($i=1;$i<$codeLength;$i++){
                    $max .= "9";
                    $min .= "0";
                }

                while (1) {
                    $referenceNo = sprintf("%0".$codeLength."s", mt_rand((int)$min, (int)$max));
                    
                    $db->where($column, $code.$referenceNo);
                    $count = $db->getValue($table, 'count(*)');
                    if ($count == 0) break;
                    // If exists, continue to generate again
                }

                $newCode = $code.$referenceNo;

            }else{
                $db->where($column,$code."%", "LIKE");
                $db->orderBy($column, 'DESC');
                $codeData = $db->getOne($table, $column);

                if (empty($codeData)) return $code.str_pad(1, $codeLength, "0", STR_PAD_LEFT);

                $existCode = $codeData[$column];
                $existCode = str_replace($code, "", $existCode);
                $newCode = $code.str_pad($existCode+1, $codeLength, "0", STR_PAD_LEFT);
            }

            return $newCode;
        }

        public function sendSocketData($dataIn) {
            $context = new ZMQContext();
            $portNumber = Setting::$configArray["socketPort"];
            $socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'my pusher');
            $socket->connect("tcp://localhost:".$portNumber);
            $socket->send(json_encode($dataIn));

            return true;
        }
        
        function getHashUsername($username) {
            //hashing part of username
            if (strlen($username) == 8) {
                $hashUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 5) . substr($username, -3);
            } else if (strlen($username) == 9) {
                $hashUsername = substr($username, 0, 3) . str_repeat('*', strlen($username) - 6) . substr($username, -3);
            } else if (strlen($username) == 10) {
                $hashUsername = substr($username, 0, 3) . str_repeat('*', strlen($username) - 6) . substr($username, -3);
            } else if (strlen($username) == 11) {
                $hashUsername = substr($username, 0, 3) . str_repeat('*', strlen($username) - 7) . substr($username, -4);
            } else if (strlen($username) == 12) {
                $hashUsername = substr($username, 0, 3) . str_repeat('*', strlen($username) - 8) . substr($username, -5);
            } else if (strlen($username) == 13) {
                $hashUsername = substr($username, 0, 4) . str_repeat('*', strlen($username) - 8) . substr($username, -4);
            } else if (strlen($username) == 14) {
                $hashUsername = substr($username, 0, 4) . str_repeat('*', strlen($username) - 9) . substr($username, -5);
            } else if (strlen($username) == 15) {
                $hashUsername = substr($username, 0, 5) . str_repeat('*', strlen($username) - 10) . substr($username, -5);
            } else if (strlen($username) == 16) {
                $hashUsername = substr($username, 0, 5) . str_repeat('*', strlen($username) - 10) . substr($username, -5);
            } else if (strlen($username) >= 17) {
                $hashUsername = substr($username, 0, 5) . str_repeat('*', strlen($username) - 11) . substr($username, -6);
            } else {
                // username length < 8
                $hashUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 4) . substr($username, -2);
            }

            return $hashUsername;
        }

        public function insertNotification($type) {
            $db = MysqliDb::getInstance();

            if(!$type) return false;

            $db->where('deleted',0);
            $adminIDArr = $db->map('id')->get('admin',null,'id');

            $db->where('type',$type);
            $notificationIDArr = $db->map('admin_id')->get('admin_notification',null,'admin_id,id');

            foreach ($adminIDArr as $adminID) {
                unset($insertData);
                if($notificationIDArr[$adminID]){
                    $db->where('id',$notificationIDArr[$adminID]);
                    $db->update('admin_notification',array("notification_count" => $db->inc(1)));
                }else{
                    $insertData = array(
                        "admin_id" => $adminID,
                        "type" => $type,
                        "notification_count" => 1
                    );
                    $db->insert('admin_notification',$insertData);
                }
            }

            return true;
        }

        public function getAdminNotification() {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID     = $db->userID;
            $site       = $db->userType;

            if($site != 'Admin'){
                return array('status'=>'error','code'=>2,'statusMsg'=>$translations['E00105'][$language],'data'=>"");
            }

            $db->where('admin_id',$userID);
            $db->where('notification_count',0,">");
            $data = $db->map('type')->get('admin_notification',null,'type,notification_count');

            return array('status'=>'ok','code'=>0,'statusMsg'=>"",'data'=>$data);
        }

        function generateAutoLoginToken($dateTime,$timeOut){
            $db = MysqliDb::getInstance();
            $length = 32;
            $autoLoginSecretKey    = Setting::$configArray["autoLoginSecretKey"];
            $autoLoginExpiryDay    = Setting::$systemSetting["autoLoginExpiryDay"];
            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');

            while (1) {
                $uniqueChar = uniqid();
                $db->where("wb_token", $uniqueChar);
                $count = $db->getValue("client_session", 'count(*)');
                if ($count == 0) break;
                // If exists, continue to generate again
            }

            switch (true) {
                case function_exists("mcrypt_create_iv") :
                    $encrytCode = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
                    break;
                case function_exists("openssl_random_pseudo_bytes") :
                    $encrytCode = openssl_random_pseudo_bytes($length);
                    break;
                case is_readable('/dev/urandom') : // deceze
                    $encrytCode = file_get_contents('/dev/urandom', false, null, 0, $length);
                    break;
                default :
                    $i = 0;
                    $encrytCode = "";
                    while($i ++ < $length) {
                        $encrytCode .= chr(mt_rand(0, 255));
                    }
                    break;
            }
            $expiredAt   = $timeOut?$timeOut:strtotime($dateTime." ".$autoLoginExpiryDay);
            $encrytCode .= $expiredAt;
            $encrytValue = base64_encode($encrytCode . bin2hex(hash_hmac('sha256', $uniqueChar . $encrytCode, $autoLoginSecretKey, true)));

            $data['wbToken']    = $uniqueChar;
            $data['bkendToken'] = $encrytValue;
            $data['expiredTS']  = $expiredAt;
            return $data;
        }

        function checkLoginToken($marcaje,$marcajeTK,$bkendToken){
            $db = MysqliDb::getInstance();
            $length = 32;
            $autoLoginSecretKey    = Setting::$configArray["autoLoginSecretKey"];

            $marcajeTK = base64_decode($marcajeTK);
            $encrytCode = substr($marcajeTK, 0, $length);
            $dateTime = substr($marcajeTK, $length, 10);
            $var = $marcaje . $encrytCode . $dateTime;

            $result = $encrytCode . $dateTime . bin2hex(hash_hmac('sha256', $var, $autoLoginSecretKey, true)) === base64_decode($bkendToken);
            if(!$result){
                return false;
            }

            $dataOut['dateTime'] = $dateTime;
            return $dataOut;
        }

        function getBrowserInfo(){
            $browserInfo = array('user_agent'=>'','browser'=>'','browser_version'=>'','os_platform'=>'','pattern'=>'', 'device'=>'',"ip"=>'');
            $u_agent = $_SERVER['HTTP_USER_AGENT'];
            $source  = Setting::$accessUser['source'];
            if(!$u_agent) $u_agent = General::$userAgent;
            $deviceDetail = General::$deviceDetail;

            $bname = 'Unknown';
            $ub = 'Unknown';
            $version = "";
            $platform = 'Unknown';
            $ip = 'Unknown';

            if(General::$ip) $ip = General::$ip;

            if($source == 'Apps'){
                if($deviceDetail['deviceManufacturer']){
                    switch ($deviceDetail['deviceManufacturer']) {
                        case 'Apple':
                            $osPlatform = "ios";
                            break;
                        
                        default:
                            $osPlatform = "android";
                            break;
                    }
                }

                return array(
                    'user_agent' => $u_agent,
                    'browser'      => $deviceDetail['deviceModel']."#".$deviceDetail['deviceName'],
                    'browser_version'   => $deviceDetail['sourceVersionNo'],
                    'os_platform'  => $osPlatform,
                    'device'    => $deviceDetail['deviceManufacturer'],
                    'ip'        => $ip
                );
            }

            $deviceType='Desktop';

            if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$u_agent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($u_agent,0,4))){

                $deviceType='Mobile';

            }

            if($_SERVER['HTTP_USER_AGENT'] == 'Mozilla/5.0(iPad; U; CPU iPhone OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B314 Safari/531.21.10') {
                $deviceType='Tablet';
            }

            if(stristr($_SERVER['HTTP_USER_AGENT'], 'Mozilla/5.0(iPad;')) {
                $deviceType='Tablet';
            }

            //$detect = new Mobile_Detect();

            //First get the platform?
            if (preg_match('/linux/i', $u_agent)) {
                $platform = 'linux';

            } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
                $platform = 'mac';

            } elseif (preg_match('/windows|win32/i', $u_agent)) {
                $platform = 'windows';
            }

            // Next get the name of the user agent yes seperately and for good reason
            if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
            {
                $bname = 'IE';
                $ub = "MSIE";

            } else if(preg_match('/Firefox/i',$u_agent))
            {
                $bname = 'Mozilla Firefox';
                $ub = "Firefox";

            } else if(preg_match('/Chrome/i',$u_agent) && (!preg_match('/Opera/i',$u_agent) && !preg_match('/OPR/i',$u_agent)))
            {
                $bname = 'Chrome';
                $ub = "Chrome";

            } else if(preg_match('/Safari/i',$u_agent) && (!preg_match('/Opera/i',$u_agent) && !preg_match('/OPR/i',$u_agent)))
            {
                $bname = 'Safari';
                $ub = "Safari";

            } else if(preg_match('/Opera/i',$u_agent) || preg_match('/OPR/i',$u_agent))
            {
                $bname = 'Opera';
                $ub = "Opera";

            } else if(preg_match('/Netscape/i',$u_agent))
            {
                $bname = 'Netscape';
                $ub = "Netscape";

                } else if((isset($u_agent) && (strpos($u_agent, 'Trident') !== false || strpos($u_agent, 'MSIE') !== false)))
            {
                $bname = 'Internet Explorer';
                $ub = 'Internet Explorer';
            }


            // finally get the correct version number
            $known = array('Version', $ub, 'other');
            $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

            if (!preg_match_all($pattern, $u_agent, $matches)) {
                // we have no matching number just continue
            }

            // see how many we have
            $i = count($matches['browser']);
            if ($i != 1) {
                //we will have two since we are not using 'other' argument yet
                //see if version is before or after the name
                if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
                    $version= $matches['version'][0];

                } else {
                    $version= @$matches['version'][1];
                }

            } else {
                $version= $matches['version'][0];
            }

            // check if we have a number
            if ($version==null || $version=="") {$version="?";}

            return array(
                'user_agent' => $u_agent,
                'browser'      => $bname,
                'browser_version'   => $version,
                'os_platform'  => $platform,
                'pattern'   => $pattern,
                'device'    => $deviceType,
                'ip'        => $ip
            );
        }
    }
?>
