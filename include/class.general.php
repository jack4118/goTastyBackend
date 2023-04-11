<?php
	
	class General
    {
        
        private $currentLanguage = 'english';
        
        function __construct($db, $setting) {
            $this->db = $db;
            $this->setting = $setting;
            
            $this->creatorID = "";
            $this->creatorType = "";
        }
        
        public function setCreator($creatorID, $creatorType) {
            $this->creatorID = $creatorID;
            $this->creatorType = $creatorType;
        }
        
        public function validatePassword($password)
        {
            global $setting;

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

            if (!preg_match('$\S*(?=\S{' . $setting->sysSetting["minPasswordLength"] . ',})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $password))
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
                    $phoneNumber[$i] = trim($this->onlyNumber($phoneNumber[$i]));
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

            $phone = $this->numberOnly($phone);
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
                    "phone" => $this->numberOnly($phone),
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
                "phone" => $this->numberOnly($phone),
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
        public function getLimit($pageNumber = NULL)
        {
            global $setting;

            $pagingLimit = $setting->systemSetting["superAdminPageLimit"];
            $startLimit  = ($pageNumber-1) * $pagingLimit;
            $limit       = array($startLimit, $pagingLimit);

            return $limit;
        }
        
        public function setCurrentLanguage($currentLanguage)
        {
            $this->currentLanguage = $currentLanguage;
        }
        
        public function getCurrentLanguage()
        {
            return $this->currentLanguage;
        }
        
        public function setTranslations($translations)
        {
            $this->translations = $translations;
        }
        
        public function getTranslations()
        {
            return $this->translations;
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
            
            return date($this->setting->getDateFormat(), $timestamp - $timeDiff);
        }
        
        public function formatDateTime($offsetSecs, $timestamp) {
            $serverTime = date('Z');
            $timeDiff = $serverTime + $offsetSecs;
            
            return date($this->setting->getDateTimeFormat(), $timestamp - $timeDiff);
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
            if ($this->setting->getTimezoneSetting()) {
                $timeDiff = $serverTime + $offsetSecs;
                $newTs = $dateTs - $timeDiff;
                return date($format, $newTs);
            }
            else {
                return date($format, $dateTs);
            }
        }

        public function formatDateTimeToString($dateTimeString, $format="") {
            $setting = $this->setting;
            
            $dateTs = strtotime($dateTimeString);
            if($dateTs < 0)
                return false;

            if($format == "")
                $format = strlen($setting->systemSetting['systemDateTimeFormat']) > 0 ? $setting->systemSetting['systemDateTimeFormat'] : "d/m/Y h:i:s A";
            
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
            $db = $this->db;

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


    }
?>
