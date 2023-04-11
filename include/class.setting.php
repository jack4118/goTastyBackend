<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for System Settings..
     * Date  11/07/2017.
    **/

    class Setting {

        /**
         * Constructor for storing the system setting into class variables for usage in other class functions.
         * Usage: $variable = $setting->systemSetting[name] = value;
         **/
        function __construct($db) {
            $this->db = $db;
            
            $results = $db->get('system_settings');
            foreach ($results as $row) {
                if ($row["type"] == "Language Setting") {
                    if($row["reference"] != "off")
                       $this->languageSetting[$row['value']] = $row["reference"];
                }
                else{
                    $this->systemSetting[$row['name']] = $row['value'];
                }
            }
            
        }
        
        public function getAuditHistoryLimit() {
            return $this->systemSetting['auditHistoryLimit']? $this->systemSetting['auditHistoryLimit'] : 100;
        }
        
        public function getSuperAdminPasswordEncryption() {
            return $this->systemSetting['superAdminPasswordEncryption']? $this->systemSetting['superAdminPasswordEncryption'] : "bcrypt";
        }
        
        public function getAdminPasswordEncryption() {
            return $this->systemSetting['adminPasswordEncryption']? $this->systemSetting['adminPasswordEncryption'] : "bcrypt";
        }
        
        public function getMemberPasswordEncryption() {
            return $this->systemSetting['memberPasswordEncryption']? $this->systemSetting['memberPasswordEncryption'] : "";
        }
        
        public function getSuperAdminPageLimit() {
            return $this->systemSetting['superAdminPageLimit']? $this->systemSetting['superAdminPageLimit'] : 25;
        }
        
        public function getAdminPageLimit() {
            return $this->systemSetting['adminPageLimit']? $this->systemSetting['adminPageLimit'] : 25;
        }
        
        public function getMemberPageLimit() {
            return $this->systemSetting['memberPageLimit']? $this->systemSetting['memberPageLimit'] : 25;
        }
        
        public function getSuperAdminTimeOut() {
            return $this->systemSetting['superAdminTimeout']? $this->systemSetting['superAdminTimeout'] : 900;
        }
        
        public function getAdminTimeOut() {
            return $this->systemSetting['adminTimeout']? $this->systemSetting['adminTimeout'] : 900;
        }
        
        public function getMemberTimeout() {
            return $this->systemSetting['memberTimeout']? $this->systemSetting['memberTimeout'] : 900;
        }
        
        public function getSystemDecimalPlaces() {
            return $this->systemSetting['systemDecimalFormat']? $this->systemSetting['systemDecimalFormat'] : 8;
        }
        
        public function getSystemCreditSetting() {
            return $this->systemSetting['creditSetting']? $this->systemSetting['creditSetting'] : "prepaid";
        }
        
        public function getDateFormat() {
            return $this->systemSetting['systemDateFormat']? $this->systemSetting['systemDateFormat'] : "Y-m-d";
        }
        
        public function getDateTimeFormat() {
            return $this->systemSetting['systemDateTimeFormat']? $this->systemSetting['systemDateTimeFormat'] : "Y-m-d h:i:s";
        }
        
        public function getTimezoneSetting() {
            return $this->systemSetting['timezoneUsage']? $this->systemSetting['timezoneUsage'] : 0;
        }

        public function getInvoiceNumberLength(){
            return $this->systemSetting['invoiceNumberLength'] ? $this->systemSetting['invoiceNumberLength'] : 10;
        }

        public function getPinNumberLength(){
            return $this->systemSetting['pinNumberLength'] ? $this->systemSetting['pinNumberLength'] : 10;
        }

    	public function getInternalDecimalFormat(){
            return $this->systemSetting['internalDecimalFormat'] ? $this->systemSetting['internalDecimalFormat'] : 8;
        }
        /**
         * Function for getting the Settings List.
         * @param $settingParams.
         * @author Rakesh.
        **/
        public function getSettingsList($settingParams) {
            global $db, $general;
            $pageNumber = $settingParams['pageNumber'] ? $settingParams['pageNumber'] : 1;
            $searchData = $settingParams['searchData'];
            
            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'type':
                            $db->where('type', $dataValue);
                            break;
                            
                        case 'module':
                            $db->where('module', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("system_settings", $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("system_settings", "count(id)");
                foreach($result as $value) {

                    $setting['id']         = $value['id'];
                    $setting['name']       = $value['name'];
                    $setting['type']       = $value['type'];
                    $setting['reference']  = $value['reference'];
                    $setting['module']     = $value['module'];
                    $setting['value']      = $value['value'];

                    $settingList[] = $setting;
                }
                
                $data['settingList'] = $settingList;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Settings.
         * @param $settingParams.
         * @author Rakesh.
        **/
        function newSetting($settingParams) {
            global $db;

            $name      = trim($settingParams['name']);
            $type      = trim($settingParams['type']);
            $reference = trim($settingParams['reference']);
            $value     = trim($settingParams['value']);
            $module    = trim($settingParams['module']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($reference) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Reference.", 'data'=>"");

            if(strlen($value) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter the Value.", 'data'=>"");

            if(strlen($module) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter the Module.", 'data'=>"");

            $fields     = array("name", "type", "reference", "value", "module");
            $values     = array($name, $type, $reference, $value, $module);
            $arrayData  = array_combine($fields, $values);

            $result = $db->insert("system_settings", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Setting Successfully Saved"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for adding the Updating the Setting.
         * @param $settingParams.
         * @author Rakesh.
        **/
        public function editSettingData($settingParams) {
            global $db;

            $id          = trim($settingParams['id']);
            $name        = trim($settingParams['name']);
            $type        = trim($settingParams['type']);
            $value       = trim($settingParams['value']);
            $reference   = trim($settingParams['reference']);
            $module      = trim($settingParams['module']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($reference) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Reference.", 'data'=>"");

            if(strlen($value) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Value.", 'data'=>"");

            if(strlen($module) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Module.", 'data'=>"");

            $fields = array("name", "type", "reference", "value", "module");
            $values = array($name, $type, $reference, $value, $module);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("system_settings", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Setting Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Setting.
         * @param $settingParams.
         * @author Rakesh.
        **/
        function deleteSettings($settingParams) {
            global $db;

            $id = trim($settingParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Setting", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('system_settings', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('system_settings');
                if($result) {
                    return $this->getSettingsList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for getting the Setting data in the Edit.
         * @param $settingParams.
         * @author Rakesh.
        **/
        public function getSettingData($settingParams) {
            global $db;
            $id = trim($settingParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Setting", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("system_settings");
            
            if (!empty($result)) {
                $setting['id']            = $result["id"];
                $setting['name']          = $result["name"];
                $setting['type']          = $result["type"];;
                $setting['reference']     = $result["reference"];
                $setting['value']         = $result["value"];
                $setting['module']        = $result['module'];
                
                $data['settingData'] = $setting;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for getting the Modules Settings List.
         * @param $settingParams.
         * @author TaiSheng.
        **/
        public function getModuleSettingList($settingParams) {
            global $db, $general;
            $pageNumber = $settingParams['pageNumber'] ? $settingParams['pageNumber'] : 1;
            $searchData = $settingParams['searchData'];
            
            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            // Get super admin name
            $superAdminResult = $db->get("users", null, "id, username, name");
            if(!empty($superAdminResult)){
                foreach($superAdminResult as $saValue){
                    $superAdminData[$saValue["id"]] = $saValue["username"];
                }
            }

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'moduleName':
                            $db->where('name', $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("id", "ASC");
            $result = $db->get("mlm_modules", $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue("mlm_modules", "count(id)");
                foreach($result as $value) {

                    $setting['id']         = $value['id'];
                    $setting['name']       = $value['name'];
                    $setting['disabled']   = $value['disabled'];
                    $setting['payment']    = $value['payment'];
                    $setting['created_at'] = $value['created_at'];
                    $setting['updated_at'] = $value['updated_at'];
                    $setting['creator_id'] = ($value['creator_id'] > 0 ? $superAdminData[$value["creator_id"]] : "System");
                    $setting['updater_id'] = ($value['updater_id'] > 0 ? $superAdminData[$value["updater_id"]] : "System");

                    $settingList[] = $setting;
                }
                
                $data['settingList'] = $settingList;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for getting the Module Setting data in the Edit.
         * @param $settingParams.
         * @author TaiSheng.
        **/
        public function getModuleSettingData($settingParams) {
            global $db;
            $id = trim($settingParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Setting", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("mlm_modules");
            
            if (!empty($result)) {
                $setting['id']            = $result["id"];
                $setting['moduleName']    = $result["name"];
                $setting['disabled']      = $result["disabled"];
                $setting['payment']       = $result["payment"];
                
                $data['moduleSettingData'] = $setting;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for Edit the Module Setting.
         * @param $settingParams.
         * @author TaiSheng.
        **/
        public function editModuleSettingData($settingParams) {
            global $db, $cash;

            $id          = trim($settingParams['id']);
            $moduleName  = trim($settingParams['moduleName']);
            $disabled    = trim($settingParams['disabled']);
            $payment     = trim($settingParams['payment']);

            $creatorID = $cash->creatorID ? $cash->creatorID:0;

            if(strlen($moduleName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Module Name.", 'data'=>"");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Disabled Status.", 'data'=>"");

            if(strlen($payment) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Payment Status.", 'data'=>"");

            $fields = array("name", "disabled", "payment", "updated_at", "updater_id");
            $values = array($moduleName, $disabled, $payment, date("Y-m-d H:i:s"), $creatorID);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("mlm_modules", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Module Setting Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Module Setting", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Module Setting.
         * @param $settingParams.
         * @author TaiSheng.
        **/
        function deleteModuleSetting($settingParams) {
            global $db;

            $id = trim($settingParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Setting", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('mlm_modules', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('mlm_modules');
                if($result) {
                    return $this->getModuleSettingList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Module Setting", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Module Settings.
         * @param $settingParams.
         * @author TaiSheng.
        **/
        function addModuleSetting($settingParams) {
            global $db, $cash;

            $moduleName = trim($settingParams['moduleName']);
            $disabled   = trim($settingParams['disabled']);
            $payment    = trim($settingParams['payment']);

            $creatorID = $cash->creatorID ? $cash->creatorID:0;

            if(strlen($moduleName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Module Name.", 'data'=>"");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Disabled Status.", 'data'=>"");

            if(strlen($payment) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Payment Status.", 'data'=>"");

            $fields     = array("name", "disabled", "payment", "created_at", "updated_at", "creator_id", "updater_id");
            $values     = array($moduleName, $disabled, $payment, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $creatorID, $creatorID);
            $arrayData  = array_combine($fields, $values);

            $result = $db->insert("mlm_modules", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Module Setting Successfully Saved"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Module Setting", 'data'=>"");
            }
        }

        public function setDecimal($amount, $creditType=""){

            $decimal = $this->systemSetting[$creditType."DecimalPlaces"];
            if(!$decimal) $decimal = $this->systemSetting['decimalPlaces'];
            if(!$decimal) $decimal = 8; // default 8
            if(is_int($creditType) && $creditType < 9) $decimal = $creditType;
      
            $floor = pow(10, $decimal); // floor for extra decimal
            $convertedAmount = number_format( (floor(strval($amount*$floor))/$floor) , $decimal , '.', '');

            return $convertedAmount;
        }

        public function getSwappableCoins() {
            $db = $this->db;

            $db->where('name', "isSwappableCoin");
            $db->where('value', "1");

            $getRes = $db->get("credit_setting", null, "credit_id");
            
            foreach ($getRes as $creditID) {
                $db->where('id', $creditID["credit_id"]);
                $coinType[] = $db->getValue('credit', "name");
            }
            
            return $coinType;
        }

        public function getActiveLanguages(){
            $db = $this->db;

            $db->where('disabled', 0);
            $result = $db->get('languages', null, 'language');
            
            foreach($result as $value){
                $languages[] = $value['language'];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> "", 'data' => $languages); 
            // return $languages;
        }

        public function getSpecialClientData($client_id){
            $db = $this->db;

            $db->where('client_id',$client_id);
            $db->where('name', 'portfolioType');
            $result = $db->getOne('client_setting','value,type');

            return $result;
        }

        public function getLeaderDownlineSetting($clientID,$type){
            $db = $this->db;

            $db->where("client_id",$clientID);
            $db->where("type",$type);
            $db->where("disable",'0');
            $result = $db->getOne("leader_downline_setting","value,disable");

            return $result[0];
        }

        public function getCreditTranslationCode() {
            $db = $this->db;

            $result = $db->get("credit", null, "name, translation_code");
            foreach ($result as $value) {
                $creditTranslationsCode[$value["name"]] = $value["translation_code"];
            }

            return $creditTranslationsCode;
        }

        public function getPaymentSettingByCredit() {
            $db = $this->db;

            $result = $db->get("mlm_payment_method", null, "credit_type, status, min_percentage, max_percentage, payment_type");
            foreach($result as $value) {
                $temp['paymentType']    = $value['payment_type'];
                $temp['creditType']     = $value['credit_type'];
                $temp['minPercentage']  = $value['min_percentage'];
                $temp['maxPercentage']  = $value['max_percentage'];
                $temp['status']         = $value['status'];

                $paymentSetting[$temp['creditType']] = $temp;
            }

            return $paymentSetting;
        }

        public function getPinProductId($pinCode) {
            $db = $this->db;

            $db->where("code", trim($pinCode));
            $result = $db->getValue("mlm_pin", "product_id");

            return $result;
        }

        public function getProductDetail($productId, $registerType) {
            $db = $this->db;

            $result['bonusValue'] = 0;
            $result['tierValue'] = 0;

            $db->where('id', $productId);
            $result = $db->getOne("mlm_product", "name, code, category, price, status, translation_code, image_id, image_name, active_at, expire_at, created_at");

            $db->where("product_id", $productId);
            $db->where("name", array('bonusValue', 'tierValue'), 'IN');
            $settingResult = $db->get("mlm_product_setting", null, "name, value");
            foreach ($settingResult as $value) {
                if ($value['name'] == 'bonusValue') {
                    if($registerType != 'free') {
                        $result['bonusValue'] = $value['value'];
                    }
                }
                if ($value['name'] == 'tierValue') {
                    $result['tierValue'] = $value['value'];
                }
            }

            $db->where("product_id", $productId);
            $db->where("name", "contractLength");
            $contractResult = $db->getValue("mlm_product_setting", "value");

            

            return $result;
        }

        public function getCreditSeting($creditType ='', $creditName = ''){
            $db = $this->db;    

            $column = "(SELECT name FROM credit WHERE id = credit_id) AS creditType, 
                        value,
                        name,
                        admin,
                        member";

            if($creditType){
                $db->where("type",$creditType); 
                $creditIDArray = $db->get("credit",null,'id');
                foreach ($creditIDArray as $key => $creditValue) {

                    $creditID[] = $creditValue['id'];
                    # code...
                }
                 $db->where("credit_id",$creditID,"IN");
            }

            if($creditName){
                $db->where("name",$creditName); 
                $creditIDArray = $db->get("credit",null,'id');
                foreach ($creditIDArray as $key => $creditValue) {

                    $creditID[] = $creditValue['id'];
                    # code...
                }
                 $db->where("credit_id",$creditID,"IN");
            }
            
            $result = $db->get("credit_setting",NULL,$column);

            if(empty($result)) return false;

            foreach($result AS $value){
                $creditData[$value['creditType']][$value['name']]['value'] = $value['value'];
                $creditData[$value['creditType']][$value['name']]['member'] = $value['member'];
                $creditData[$value['creditType']][$value['name']]['admin'] = $value['admin'];
            }

            return $creditData;
        }

        function getMainLeaderList() {
            $db = $this->db;

            $db->where('name', "isLeader");
            $db->where('value', "mainLeader");
            $mainLeaderID = $db->get("client_setting", NULL, "client_id");

            foreach ($mainLeaderID as $key => $value) {

                $db->where('id', $value["client_id"]);
                $mainLeaderUsername = $db->get('client', NULL,'username');

                foreach ($mainLeaderUsername as $key1 => $value1) {
                    $mainLeaderIDArray[] = $value["client_id"];
                }
            }

            return $mainLeaderIDArray;
        }

    }

?>
