<?php
    
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for Users.
     * Date  11/07/2017.
    **/

    class User
    {
        
        function __construct($db, $setting, $general)
        {
            $this->db = $db;
            $this->setting = $setting;
            $this->general = $general;
        }
        
        public function superAdminLogin($params)
        {
            $db = $this->db;
            $setting = $this->setting;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            // Get the stored password type.
            $passwordEncryption = $setting->getSuperAdminPasswordEncryption();
            
            $username = trim($params['username']);
            $password = trim($params['password']);
            
            $db->where('username', $username);
            if($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            }
            else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('password', $db->encrypt($password));
            }
            else {
                // No encryption
                $db->where('password', $password);
            }
            $result = $db->get('users');
            
            if (!empty($result)) {
                if($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(!password_verify($password, $result[0]['password']))
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00653"][$language], 'data' => '');
                }
                
                if($result[0]['disabled'] == 1) {
                    // Return error if account is disabled
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00654"][$language], 'data' => '');
                }
                
                $id = $result[0]['id'];
                
                // Join the permissions table
                $db->where('a.site', 'SuperAdmin');
                $db->where('a.disabled', 0);
                $db->where('a.type', 'Page', '!=');
                if ($result[0]["role_id"] != 1) {
                    $db->where('b.disabled', 0);
                    $db->where('b.role_id', $result[0]['role_id']);
                    $db->join('roles_permission b', 'b.permission_id=a.id', 'LEFT');
                }
                
                $db->orderBy("type, parent_id, priority","asc");
                $res = $db->get('permissions a', null, 'a.id, a.name, a.type, a.parent_id, a.file_path, a.priority, a.icon_class_name');
                
                foreach ($res as $array) {
                    $data['permissions'][] = $array;
                }
                
                $sessionID = md5($result[0]['username'] . time());
                
                $fields = array('session_id', 'last_login', 'updated_at');
                $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
                
                $db->where('id', $id);
                $db->update('users', array_combine($fields, $values));
                
                // This is to get the Pages from the permissions table
                $db->where('type', 'Page');
                $db->where('site', 'SuperAdmin');
                $db->where('disabled', 0);
                $pageResults = $db->get('permissions');
                foreach ($pageResults as $array) {
                    $data['pages'][] = $array;
                }

                //This is to get the hidden submenu from the permissions table
                $db->where('type', 'Hidden');
                $db->where('site', 'SuperAdmin');
                $db->where('disabled', 0);
                $hiddenResults = $db->get('permissions');
                foreach ($hiddenResults as $array){
                    $data['hidden'][] = $array;
                }
                
                $client['userID'] = $id;
                $client['username'] = $result[0]['username'];
                $client['userEmail'] = $result[0]['email'];
                $client['userRoleID'] = $result[0]['role_id'];
                $client['sessionID'] = $sessionID;
                $client['timeOutFlag'] = $setting->getSuperAdminTimeOut();
                $client['pagingCount'] = $setting->getSuperAdminPageLimit();
                
                $data['userDetails'] = $client;
                
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00653"][$language], 'data' => '');
        }
        
        /**
         * Function for getting the Interal Accounts List.
         * @param $internalAccountParams.
         * @author Rakesh.
         **/
        public function getInternalAccountsList($params)
        {
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            
            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            $searchData   = $params['searchData'];

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        default:
                            $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $db->orderBy("id", "DESC");
            $db->where("type", "Internal");
            $copyDb = $db->copy();
            $result = $db->get("client", $limit, "id, username, name, description");
            
            if (!empty($result)) {
                foreach($result as $value) {
                    $client['id']           = $value['id'];
                    $client['username']     = $value['username'];
                    $client['name']         = $value['name'];
                    $client['remark']       = $value['description'];

                    $clientList[] = $client;
                }

                $totalRecords = $copyDb->getValue("client", "count(id)");
                
                $data['internalAccList'] = $clientList;
                $data['totalPage']       = ceil($totalRecords/$limit[1]);
                $data['pageNumber']      = $pageNumber;
                $data['totalRecord']     = $totalRecords;
                $data['numRecord']       = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language], 'data'=>"");
            }
        }
        
        /**
         * Function for adding the New InternalAccounts.
         * @param $internalAccountParams.
         * @author Rakesh.
         **/
        public function newInternalAccount($internalAccountParams)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $username               = trim($internalAccountParams['username']);
            $name                   = trim($internalAccountParams['name']);
            $description            = trim($internalAccountParams['description']);
            $type                   = "Internal";
            // $password               = trim($internalAccountParams['password']);
            // $transaction_password   = trim($internalAccountParams['transaction_password']);
            // $type                   = trim($internalAccountParams['type']);
            // $description            = trim($internalAccountParams['description']);
            // $email                  = trim($internalAccountParams['email']);
            // $phone                  = trim($internalAccountParams['phone']);
            // $address                = trim($internalAccountParams['address']);
            // $country_id             = trim($internalAccountParams['country_id']);
            // $state_id               = trim($internalAccountParams['state_id']);
            // $county_id              = trim($internalAccountParams['county_id']);
            // $city_id                = trim($internalAccountParams['city_id']);
            // $sponsor_id             = trim($internalAccountParams['sponsor_id']);
            // $placement_id           = trim($internalAccountParams['placement_id']);
            // $disabled               = trim($internalAccountParams['disabled']);
            
            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00656"][$language], 'data'=>"");
            
            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00657"][$language], 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00658"][$language], 'data'=>"");
            
            // if(strlen($password) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00659"][$language], 'data'=>"");
            
            // if(strlen($transaction_password) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00128"][$language], 'data'=>"");
            
            // if(strlen($type) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00661"][$language], 'data'=>"");
            
            // if(strlen($description) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00662"][$language], 'data'=>"");
            
            // if(strlen($email) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00663"][$language], 'data'=>"");
            
            // if(strlen($phone) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00664"][$language], 'data'=>"");
            
            // if(strlen($address) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00665"][$language], 'data'=>"");
            
            // if(strlen($country_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00303"][$language], 'data'=>"");
            
            // if(strlen($state_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00667"][$language], 'data'=>"");
            
            // if(strlen($county_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00668"][$language], 'data'=>"");
            
            // if(strlen($city_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00669"][$language], 'data'=>"");
            
            // if(strlen($sponsor_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00677"][$language], 'data'=>"");
            
            // if(strlen($placement_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00670"][$language], 'data'=>"");
            
            // if(strlen($disabled) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $fields = array("username",
                            "name",
                            "description",
                            "type",
                            // "password",
                            // "transaction_password",
                            // "type",
                            // "description",
                            // "email",
                            // "phone",
                            // "address",
                            // "country_id",
                            // "state_id",
                            // "county_id",
                            // "city_id",
                            // "sponsor_id",
                            // "placement_id",
                            // "disabled",
                            "last_login",
                            "last_activity",
                            "created_at");
            $values = array($username,
                            $name,
                            $description,
                            $type,
                            // $password,
                            // $transaction_password,
                            // $type,
                            // $description,
                            // $email,
                            // $phone,
                            // $address,
                            // $country_id,
                            // $state_id,
                            // $county_id,
                            // $city_id,
                            // $sponsor_id,
                            // $placement_id,
                            // $disabled,
                            date("Y-m-d H:i:s"),
                            date("Y-m-d H:i:s"),
                            date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            
            $result = $db->insert("client", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00672"][$language]);
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00673"][$language], 'data'=>"");
            }
        }
        
        /**
         * Function for adding the Updating the InternalAccount.
         * @param $internalAccountParams.
         * @author Rakesh.
         **/
        public function editInternalAccountData($internalAccountParams)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id                     = trim($internalAccountParams['id']);
            $username               = trim($internalAccountParams['username']);
            $name                   = trim($internalAccountParams['name']);
            $description            = trim($internalAccountParams['description']);
            $type                   = "Internal";
            // $password               = trim($internalAccountParams['password']);
            // $transaction_password   = trim($internalAccountParams['transaction_password']);
            // $type                   = trim($internalAccountParams['type']);
            // $description            = trim($internalAccountParams['description']);
            // $email                  = trim($internalAccountParams['email']);
            // $phone                  = trim($internalAccountParams['phone']);
            // $address                = trim($internalAccountParams['address']);
            // $country_id             = trim($internalAccountParams['country_id']);
            // $state_id               = trim($internalAccountParams['state_id']);
            // $county_id              = trim($internalAccountParams['county_id']);
            // $city_id                = trim($internalAccountParams['city_id']);
            // $sponsor_id             = trim($internalAccountParams['sponsor_id']);
            // $placement_id           = trim($internalAccountParams['placement_id']);
            // $disabled               = trim($internalAccountParams['disabled']);
            
            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00656"][$language], 'data'=>"");
            
            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00657"][$language], 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00658"][$language], 'data'=>"");
            
            // if(strlen($password) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00659"][$language], 'data'=>"");
            
            // if(strlen($transaction_password) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00128"][$language], 'data'=>"");
            
            // if(strlen($type) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00661"][$language], 'data'=>"");
            
            // if(strlen($description) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00662"][$language], 'data'=>"");
            
            // if(strlen($email) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00663"][$language], 'data'=>"");
            
            // if(strlen($phone) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00664"][$language], 'data'=>"");
            
            // if(strlen($address) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00665"][$language], 'data'=>"");
            
            // if(strlen($country_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00303"][$language], 'data'=>"");
            
            // if(strlen($state_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00667"][$language], 'data'=>"");
            
            // if(strlen($county_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00668"][$language], 'data'=>"");
            
            // if(strlen($city_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00669"][$language], 'data'=>"");
            
            // if(strlen($sponsor_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00677"][$language], 'data'=>"");
            
            // if(strlen($placement_id) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00670"][$language], 'data'=>"");
            
            // if(strlen($disabled) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $fields     = array("username",
                                "name",
                                "description",
                                "type",
                                // "password",
                                // "transaction_password",
                                // "type",
                                // "description",
                                // "email",
                                // "phone",
                                // "address",
                                // "country_id",
                                // "state_id",
                                // "county_id",
                                // "city_id",
                                // "sponsor_id",
                                // "placement_id",
                                // "disabled",
                                "last_login",
                                "last_activity",
                                "updated_at");
            $values     = array($username,
                                $name,
                                $description,
                                $type,
                                // $password,
                                // $transaction_password,
                                // $type,
                                // $description,
                                // $email,
                                // $phone,
                                // $address,
                                // $country_id,
                                // $state_id,
                                // $county_id,
                                // $city_id,
                                // $sponsor_id,
                                // $placement_id,
                                // $disabled,
                                date("Y-m-d H:i:s"),
                                date("Y-m-d H:i:s"),
                                date("Y-m-d H:i:s"));
            $arrayData  = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("client", $arrayData);
            
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00674"][$language]);
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00673"][$language], 'data'=>"");
            }
        }
        
        /**
         * Function for deleting the InternalAccount.
         * @param $internalAccountParams.
         * @author Rakesh.
         **/
        public function deleteInternalAccount($internalAccountParams)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($internalAccountParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00675"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get("client", 1);
            
            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete("client");
                if($result) {
                    return $this->getInternalAccountsList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00673"][$language], 'data'=>"");
            }
        }
        
        /**
         * Function for getting the InternalAccount data in the Edit.
         * @param $internalAccountParams.
         * @author Rakesh.
         **/
        public function getInternalAccountData($internalAccountParams)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($internalAccountParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00675"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("client");
            
            if (!empty($result)) {
                $client['id']                    = $result['id'];
                $client['username']              = $result['username'];
                $client['name']                  = $result['name'];
                $client['description']           = $result['description'];
                // $myObj->data->password              = $result['password'];
                // $myObj->data->transaction_password  = $result['transaction_password'];
                // $myObj->data->type                  = $result['type'];
                // $myObj->data->description           = $result['description'];
                // $myObj->data->address               = $result['address'];
                // $myObj->data->email                 = $result['email'];
                // $myObj->data->phone                 = $result['phone'];
                // $myObj->data->state_id              = $result['state_id'];
                // $myObj->data->city_id               = $result['city_id'];
                // $myObj->data->country_id            = $result['country_id'];
                // $myObj->data->county_id             = $result['county_id'];
                // $myObj->data->sponsor_id            = $result['sponsor_id'];
                // $myObj->data->placement_id          = $result['placement_id'];
                // $myObj->data->phone                 = $result['phone'];
                // $myObj->data->disabled              = $result['disabled'];
                
                $data['internalAccData'] = $client;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00673"][$language], 'data'=>"");
            }
        }
        
        public function getAdmins($params)
        {
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $searchData = $params['searchData'];
            $searchDate = $params['searchDate'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $statusMsg = "";
            
            //Get the limit.
            $limit = $general->getLimit($pageNumber);
            
            // $db->where("name", "Admin");
            $result = $db->get("roles", null, "id, name");
            
            foreach ($result as $key => $val) {
                $rolesName[$val['id']] = $val['name'];
            }
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        default:
                            $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
//            if (count($searchDate) > 0) {
//                foreach ($searchDate as $array) {
//                    foreach ($array as $key => $val) {
//                        $db->where($key, date("Y-m-d H:i:s", $val['startTs']), ">=");
//                        $db->where($key,  date("Y-m-d H:i:s", $val['endTs']), "<=");
//                    }
//                }
//            }
            
            $db->orderBy("id", "DESC");
            
            $copyDb = $db->copy();
            $result = $db->get("admin", $limit, "id AS ID, username, name, email, role_id as roleName, disabled, created_at as createdAt, last_login as lastLogin");//, role_id as role_name
            
            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language], 'data' => "");
            
            
            
            foreach($result as $array) {
                // $adminData["role_name"] = $rolesName[$array["role_name"]];
                $array["disabled"] = ($array["disabled"] == 1) ? "Yes" : "No";
                $array["roleName"] = $rolesName[$array["roleName"]];
                
                // foreach ($array as $key => $value) {
                //     if($adminData[$key]) $value = $adminData[$key];
                    
                //     $adminList[$key][] = $value;
                // }

                $adminList[] = $array;
                
                
            }
            
            $totalRecord = $copyDb->getValue ("admin", "count(id)");
            
            $data['adminList'] = $adminList;
            $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>$statusMsg, 'data' => $data);
            
        }
        
        public function getAdminDetails($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00678"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("admin", "id, username, name, email, disabled as status"); //, role_id as roleID
            
            if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00679"][$language], 'data'=>"");
            
            foreach ($result as $key => $value) {
                $adminDetail[$key] = $value;
            }
            
            $data['adminDetail'] = $adminDetail;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            
        }
        
        public function addAdmin($params)
        {
            $db = $this->db;
            $setting = $this->setting;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            //Check the stored password type.
            $passwordFlag = $setting->systemSetting['passwordVerification'];
            
            $email    = trim($params['email']);
            $fullName = trim($params['fullName']);
            $username = trim($params['username']);
            $password = trim($params['password']);
            $roleID   = trim($params['roleID']);
            $status   = trim($params['status']);
            
            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00657"][$language], 'data'=>"");
            
            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00656"][$language], 'data'=>"");
            
            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00663"][$language], 'data'=>"");
            
            if(strlen($password) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00659"][$language], 'data'=>"");
            
            // if(strlen($roleID) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00681"][$language], 'data'=>"");
            
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $db->where('email', $email);
            $result = $db->get('admin');
            if (!empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00680"][$language], 'data'=>"");
            
            // Retrieve the encrypted password based on settings
            $password = $this->getEncryptedPassword($password);
            
            $fields = array("email", "password", "username","name", "created_at", "role_id", "disabled", "updated_at");
            $values = array($email, $password, $username, $fullName, date("Y-m-d H:i:s"), $roleID, $status, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            try{
                $result = $db->insert("admin", $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00682"][$language], 'data'=>"");
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00683"][$language], 'data'=>"");
        }
        
        public function editAdmin($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            $email = trim($params['email']);
            $fullName = trim($params['fullName']);
            $username = trim($params['username']);
            $roleID = trim($params['roleID']);
            $status = trim($params['status']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00713"][$language], 'data'=>"");
            
            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00663"][$language], 'data'=>"");
            
            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00657"][$language], 'data'=>"");
            
            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00656"][$language], 'data'=>"");
            
            // if(strlen($roleID) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00681"][$language], 'data'=>"");
            
            // $db->where('id', $roleID);
            // $result = $db->getOne('roles');
            // if (empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00684"][$language], 'data'=>"");
            
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->getOne('admin');
            
            if (!empty($result)) {
                $fields = array("email", "username", "name", "role_id", "disabled", "updated_at");
                $values = array($email, $username, $fullName, $roleID, $status, date("Y-m-d H:i:s"));
                
                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("admin", $arrayData);
                
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00685"][$language], 'data' => "");
                
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00686"][$language], 'data'=>"");
            }
        }
        
        public function deleteAdmin($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            $statusMsg = "";
            
            if(strlen($id) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00687"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne('admin');
            
            if (!empty($result)) $statusMsg = $translations["E00688"][$language];
            
            $db->where('id', $id);
            $result = $db->delete('admin');
            
            if($result) return $this->getAdmins();
            else  $statusMsg = $translations["E00571"][$language];
            
            return array('status' => "error", 'code' => 1, 'statusMsg' => $statusMsg, 'data' => '');
            
            
        }
        
        
        public function getClients($params)
        {
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            
            //Get the limit.
            $limit      = $general->getLimit($pageNumber);
            $searchData = $params['searchData'];
            
            $searchText = '';
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'sponsor':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("a.sponsor_id", $sq, "in");

                            break;

                        default:
                            $db->where("a." . $dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            /*
             if(strlen($params['tsLoginFrom']) != 0) {
             $timeFrom = date("Y-m-d H:i:s", $params['tsLoginFrom']);
             $searchText = $searchText.' AND last_login >= "'.$timeFrom.'"';
             }
             $tmpA = $timeFrom;
             if(strlen($params['tsLoginTo']) != 0) {
             $timeTo = date("Y-m-d H:i:s", $params['tsLoginTo']);
             $searchText = $searchText.' AND last_login <= "'.$timeTo.'"';
             }
             $tmpB = $timeTo;
             if(strlen($params['tsActivityFrom']) != 0) {
             $timeFrom = date("Y-m-d H:i:s", $params['tsActivityFrom']);
             $searchText = $searchText.' AND last_activity >= "'.$timeFrom.'"';
             }
             $tmpC = $timeFrom;
             if(strlen($params['tsActivityTo']) != 0) {
             $timeTo = date("Y-m-d H:i:s", $params['tsActivityTo']);
             $searchText = $searchText.' AND last_activity <= "'.$timeTo.'"';
             }*/
            $tmpD = $timeTo;
            //if ($searchText != '')
            //    $searchText = preg_replace('/AND/', 'WHERE', $searchText, 1);
            
            $db->join("country c", "a.country_id=c.id", "LEFT");
            $db->join("client u", "a.sponsor_id=u.id", "LEFT");
            $db->where("a.type", "Client");
            $copyDb = $db->copy();
            
            $db->orderBy("a.id", "DESC");
            
            $result = $db->get("client a", $limit, "a.id, a.username, a.name, c.name AS country, u.username AS sponsor_username, a.disabled, a.suspended, a.freezed, a.last_login, a.created_at");
            
            $totalRecords = $copyDb->getValue("client a", "count(a.id)");
            
            //$totalRecords = count($countResult);
            if (!empty($result)) {
                foreach($result as $value) {
                    $client['id'] = $value['id'];
                    $client['username'] = $value['username'];
                    $client['name'] = $value['name'];
                    $client['sponsorUsername'] = $value['sponsor_username']? $value['sponsor_username'] : "-";
                    $client['country'] = $value['country'];
                    $client['disabled'] = ($value['disabled'] == 1)? 'Yes':'No';
                    $client['suspended'] = ($value['suspended'] == 1)? 'Yes':'No';
                    $client['freezed'] = ($value['freezed'] == 1)? 'Yes':'No';
                    $client['lastLogin'] = ($value['last_login'] == "0000-00-00 00:00:00")? "-" : $value['last_login'];
                    $client['createdAt'] = $value['created_at'];

                    $clientList[] = $client;
                }
                
                $data['clientList'] = $clientList;
                $data['totalPage']  = ceil($totalRecords/$limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord'] = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language], 'data'=>"");
            }
        }
        
        public function getClientSettings($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00690"][$language], 'data'=> '');
            
            $db->where('client_id', $id);
            
            $cols = Array ('id', 'name', 'value', 'type');
            $result = $db->get('client_setting', null, $cols);
            
            if (!empty($result)) {
                foreach($result as $array) {
                    //                    $clientSettingID[] = $array['id'];
                    $name[] = $array['name'];
                    $value[] = $array['value'];
                    $type[] = $array['type'];
                }
                
                //                $myObj->data->clientSettingID = $clientSettingID;
                $clientSetting['name'] = $name;
                $clientSetting['value'] = $value;
                $clientSetting['type'] = $type;
                
                $data['clientSetting'] = $clientSetting;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00691"][$language], 'data'=>'');
            }
        }
        
        public function getClientDetails($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Client", 'data'=> '');
            
            $result = $db->rawQuery('SELECT id, username, name, type, email, phone, address, (SELECT name FROM country WHERE id=client.country_id) as country, (SELECT name FROM state WHERE id=client.state_id) as state, (SELECT name FROM city WHERE id=client.city_id) as city, (SELECT name FROM county WHERE id=client.county_id) as county, (SELECT username FROM client a WHERE a.id=client.sponsor_id) as sponsor_username, (SELECT username FROM client b WHERE b.id=client.placement_id) as placement_username, disabled, suspended, deleted, last_login, last_activity, created_at, updated_at FROM client WHERE id="'.$db->escape($id).'" LIMIT 1');
            
            if (!empty($result)) {
                $client['ID']            = $result[0]['id'];
                $client['username']      = $result[0]['username'];
                $client['name']          = $result[0]['name'];
                $client['type']          = $result[0]['type'];
                $client['email']         = $result[0]['email'];
                $client['phone']         = $result[0]['phone'];
                $client['address']       = $result[0]['address'];
                $client['country']       = $result[0]['country'];
                $client['state']         = $result[0]['state'];
                $client['city']          = $result[0]['city'];
                $client['county']        = $result[0]['county'];
                //                $myObj->data->sponsorUsername   = $result[0]['sponsor_username'];
                //                $myObj->data->placementUsername = $result[0]['placement_username'];
                //                $myObj->data->disabled   = $result[0]['disabled'];
                //                $myObj->data->suspended   = $result[0]['suspended'];
                //                $myObj->data->deleted   = $result[0]['deleted'];
                //                $myObj->data->lastLogin   = $result[0]['last_login'];
                //                $myObj->data->lastActivity   = $result[0]['last_activity'];
                //                $myObj->data->createdAt   = $result[0]['created_at'];
                //                $myObj->data->updatedAt   = $result[0]['updated_at'];
                
                $data['clientDetail'] = $client;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language], 'data'=>"");
            }
        }

        public function addUser($params)
        {
            $db = $this->db;
            $setting = $this->setting;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $email    = trim($params['email']);
            $fullName = trim($params['fullName']);
            $username = trim($params['username']);
            $password = trim($params['password']);
            $roleID   = trim($params['roleID']);
            $status   = trim($params['status']);

            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00657"][$language], 'data'=>"");
            
            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00663"][$language], 'data'=>"");

            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00656"][$language], 'data'=>"");

            if(strlen($password) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00659"][$language], 'data'=>"");

            if(strlen($roleID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00681"][$language], 'data'=>"");
            
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $db->where('email', $email);
            $result = $db->get('users');
            if (!empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00680"][$language], 'data'=>"");

            $db->where('username', $username);
            $result = $db->get('users');
            if (!empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00692"][$language], 'data'=>"");

            // Retrieve the encrypted password based on settings
            $password = $this->getEncryptedPassword($password);
            
            $fields = array("email", "username", "password", "name", "created_at", "role_id", "disabled", "updated_at");
            $values = array($email, $username, $password, $fullName, date("Y-m-d H:i:s"), $roleID, $status, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            try{
                $result = $db->insert("users", $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00693"][$language], 'data'=>"");
            }
//            $result = $db->insert("users", $arrayData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00683"][$language], 'data'=>"");
        }
        
        public function getEncryptedPassword($password)
        {
            $db = $this->db;
            $setting = $this->setting;
            
            // Get the stored password type.
            $passwordEncryption = $setting->getSuperAdminPasswordEncryption();
            if($passwordEncryption == "bcrypt") {
                return password_hash($password, PASSWORD_BCRYPT);
            }
            else if ($passwordEncryption == "mysql") {
                return $db->encrypt($password);
            }
            else return $password;
        }

        public function editUser($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id       = trim($params['id']);
            $email    = trim($params['email']);
            $fullName = trim($params['fullName']);
            $roleID   = trim($params['roleID']);
            $status   = trim($params['status']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00694"][$language], 'data'=>"");

            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00663"][$language], 'data'=>"");

            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00657"][$language], 'data'=>"");
            
            if(strlen($roleID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00681"][$language], 'data'=>"");
            
            $db->where('id', $roleID);
            $result = $db->get('roles', 1);
            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00695"][$language], 'data'=>"");
            
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('users', 1);

            if (!empty($result)) {
                $fields = array("email", "name", "role_id", "disabled", "updated_at");
                $values = array($email, $fullName, $roleID, $status, date("Y-m-d H:i:s"));
                
                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("users", $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00696"][$language], data=> ''); 

            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00679"][$language], 'data'=>"");
            }
        }

        public function deleteUser($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00687"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get('users', 1);
            
            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('users');
                if($result) {
                    return $this->getUsers();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00698"][$language], 'data'=>"");
            }
        }
        
        public function getUserDetails($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00687"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get("users", 1);
            
            if (!empty($result)) {
                $user['id'] = $result[0]['id'];
                $user['email'] = $result[0]['email'];
                $user['fullName'] = $result[0]['name'];
                $user['roleID'] = $result[0]['role_id'];
                $user['status'] = $result[0]['disabled'];

                $data['userDetails']  = $user;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00679"][$language], 'data'=>"");
            }

        }

        public function getUsers($params)
        {
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            $result = $db->get("roles", null, "id, name");

            foreach ($result as $key => $val) {
                $rolesName[$val['id']] = $val['name'];
            }
            
            $searchData = $params['searchData'];
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "role":
                            $db->where("role_id", $dataValue);
                            break;

                        default:
                            $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $result = $db->get("users", $limit, "id, username, name, email, role_id as roleName, disabled, created_at as createdAt");
            
            if (!empty($result)) {

                foreach($result as $array) {
                    $array["roleName"] = $rolesName[$array["roleName"]];
                    $array["disabled"] = ($array["disabled"] == 1) ? "Yes" : "No";
                    // foreach ($array as $key => $value) {
                    //     if($userData[$key]) $value = $userData[$key];

                    //     $user[$key][] = $value;
                    // }

                    $user[] = $array;
                }

                $totalRecords = $copyDb->getValue("users", "count(id)");
                $data['userList']     = $user;
                $data['totalPage']    = ceil($totalRecords/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord']  = $totalRecords;
                $data['numRecord']    = $limit[1];
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language], 'data'=>"");
            }
        }

        public function addRole($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $roleName = trim($params['roleName']);
            $description = trim($params['description']);
            $status = trim($params['status']);
            $site = trim($params['site']);

            if(strlen($roleName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00699"][$language], 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00700"][$language], 'data'=>"");
            
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00701"][$language], 'data'=>"");

            if(strlen($site) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00702"][$language], 'data'=>"");
            
            $db->where('name', $roleName);
            $result = $db->get('roles');

            if (!empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00703"][$language], 'data'=>"");
            
            $fields = array("name", "disabled", "created_at", "description", "site");
            $values = array($roleName, $status, date("Y-m-d H:i:s"), $description, $site);
            $arrayData = array_combine($fields, $values);
            
            try{
                $roleID = $db->insert('roles', $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00704"][$language], 'data'=>"");
            }
            
            $db->where('type',  'Page', '!=');
            $result = $db->get('permissions', null, "id, disabled");
            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00705"][$language], 'data'=>"");
            
            $rolesPermissionsArr = array();
            $i = 0;
            foreach ($result as $value) {
                $rolesPermissionsArr[$i]['role_id'] = $roleID;
                $rolesPermissionsArr[$i]['permission_id'] = $value['id'];
                $rolesPermissionsArr[$i]['disabled'] = 1;
//                $rolesPermissionsArr[$i]['disabled'] = $value['disabled'];
                $rolesPermissionsArr[$i]['created_at'] = date("Y-m-d H:i:s");
                $rolesPermissionsArr[$i]['updated_at'] = date("Y-m-d H:i:s");
                $i++;
            }
            
            try{
                $result = $db->insertMulti("roles_permission", $rolesPermissionsArr);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00706"][$language], 'data'=>"");
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00683"][$language], 'data'=>'');
        }

        public function editRole($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            $roleName = trim($params['roleName']);
            $description = trim($params['description']);
            $status = trim($params['status']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00707"][$language], 'data'=>"");

            if(strlen($roleName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00699"][$language], 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00700"][$language], 'data'=>"");
            
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00671"][$language], 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('roles', 1);

            if (!empty($result)) {
                $db->where('name', $roleName);
                $db->where('id !='.$id);
                $result = $db->get('roles');
                if (!empty($result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00703"][$language], 'data'=>"");
                
                $fields = array("name", "description", "disabled");
                $values = array($roleName, $description, $status);
                
                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("roles", $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00708"][$language]);
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00655"][$language], 'data'=>"");
            }
        }

        public function deleteRole($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => $translations["E00681"][$language], 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('roles', 1);
            
            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('roles');
                
                if($result) {
                    $db->where('role_id', $id);
                    $result = $db->delete('roles_permission');
                    if($result) {
                        return $this->getRoles($params);
                    }
                    else
                       return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => ''); 
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00711"][$language], 'data'=>"");
            }
        }
        
        public function getRoleDetails($params)
        {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00681"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get("roles", 1);
            
            if (!empty($result)) {
                $role['id'] = $result[0]["id"];
                $role['roleName'] = $result[0]["name"];
                $role['description'] = $result[0]["description"];
                $role['status'] = $result[0]["disabled"];
                $data['roleDetails'] = $role;
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);  
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00712"][$language], 'data'=>"");
            }
        }

        public function getRoles($params)
        {
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            if ($params['pagination'] == "No") {
                // This is for getting all the countries without pagination
                $limit = null;
            }
            else {
                $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
                //Get the limit.
                $limit      = $general->getLimit($pageNumber);
            }
            
            $searchData = $params['searchData'];
            
            // Add new users will pass this here
            $getActiveRoles = trim($params['getActiveRoles']);
            if (strlen($getActiveRoles) > 0) {
                $db->where('disabled', '0');
            }
            
            $site = trim($params['site']);
            if (strlen($site) > 0) {
                $db->where('site', $site);
            }
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $array) {                    
                    foreach ($array as $key => $value) {
                        if ($key == 'dataName') {
                            $dbColumn = $value;
                        }
                        else if ($key == 'dataValue') {
                            foreach ($value as $innerVal) {
                                $db->where($dbColumn, $innerVal);
                            }
                        }
                            
                    }
                }
            }
            
            $db->where("Site", "Admin");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $result = $db->get('roles', $limit, "id, name, description, site, disabled");
            $totalRecords = $copyDb->getValue ("roles", "count(id)");

            if (!empty($result)) {
               
                foreach($result as $value) {
                    $role['id']            = $value['id'];
                    $role['name']          = $value['name'];
                    $role['description']   = $value['description'];
                    $role['site']          = $value['site'];
                    $role['status']        = ($value['disabled'] == 0) ? 'Active' : 'Disabled';

                    $roleList[] = $role;
                }

                $data['roleList']   = $roleList;
                $data['totalPage']  = ceil($totalRecords/$limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord'] = $limit[1];
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language], 'data'=>"");
            }
        }
        
        public function checkSession($userID, $sessionID, $site)
        {
            $db = $this->db;
            
            if(strlen($sessionID) == 0)
                return false;
            
            $sessionID = trim($sessionID);
            
            $db->where('id', $userID);
            $db->where('session_id', $sessionID);
            
            if($site == 'SuperAdmin')
                $result = $db->getOne('users');
            else if($site == 'Admin')
                $result = $db->getOne('admin');
            else if($site == 'Member')
                $result = $db->getOne('client');
            else
                return false;
            
            if (empty($result)) {
                return false;
            }
            
            if($site == 'Admin') {
                try{
                    $db->where('id', $userID)->update('admin', ['last_activity' => date("Y-m-d H:i:s")]);
                }
                catch (Exception $e) {
                    return false;
                }
            }
            else if($site == 'Member') {
                try{
                    $db->where('id', $userID)->update('client', ['last_activity' => date("Y-m-d H:i:s")]);
                }
                catch (Exception $e) {
                    return false;
                }
            }
            
            return $result;
        }
        
        public function checkSessionTimeOut($sessionTimeOut, $site)
        {
            $db = $this->db;
            $setting = $this->setting;
            
            if(strlen($sessionTimeOut) == 0)
                return false;
            
            $sessionTimeOut = trim($sessionTimeOut);
            $currentTime = time();
            
            if($site == 'SuperAdmin')
                $name = 'superAdminTimeout';
            else if($site == 'Admin')
                $name = 'adminTimeout';
            else if($site == 'Member')
                $name = 'memberTimeout';
            else
                $name = '-';
            
            //Call db to get timeOut from system settings table
            if ($setting->systemSetting[$name])
            {
                $timeOut = $setting->systemSetting[$name];
            }
            else
            {
                // Set a default value if setting does not exist
                $timeOut = 900;
            }
            
            if(($currentTime - $sessionTimeOut) > $timeOut)
                return false;
            
            return true;
        }
        
        public function getTestUserData($userID, $site)
        {
            $db = $this->db;
            
            $db->where('id', $userID);
            if ($site == 'SuperAdmin') {
                $result = $db->getOne('users');
            }
            else if ($site == 'Admin') {
                $result = $db->getOne('admin');
            }
            else if ($site == 'Member') {
                $result = $db->getOne('client');
            }
            
            return $result;
        }
    }

?>
