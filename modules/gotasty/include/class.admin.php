<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for Admins.
     * Date  11/07/2017.
     **/

    class Admin {

        function __construct() {
            // $this->cash    = Client::validation->bonus->cash;
            // $this->invoice = Client::validation->invoice;
            // $this->product = Client::validation->product;
            // $this->country = Client::validation->country;
            // $this->client  = $client;
            // $this->otp     = Client::validation->bonus->otp;
            // $this->tree    = Client::validation->bonus->tree;
            // $this->bonusReport = $bonusReport;
            // $this->wallet  = Client::validation->bonus->cash->wallet;
            // $this->message = Client::validation->bonus->otp->message;
        }

        public function adminLogin($params) {

            $db = MysqliDb::getInstance();

            //Language Translations.
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTime        = date('Y-m-d H:i:s');

            // Get the stored password type.
            $passwordEncryption = Setting::getAdminPasswordEncryption();

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
            $result = $db->get('admin');

            if (!empty($result)) {
                if($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(!password_verify($password, $result[0]['password']))
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => $data);
                }

                if($result[0]['disabled'] == 1) {
                    // Return error if account is disabled
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00102"][$language] /* Your account is disabled. */, 'data' => '');
                }
                //get Master Admin
                $db->where('name','Master Admin');
                $masterAdminRoleId = $db->getValue('roles','id');

                $id = $result[0]['id'];
                $role_id = $result[0]["role_id"];
                $admin['withdrawalRecordNotification'] = $result[0]['withdrawal_record_notification'];
                // Join the permissions table
                $db->where('a.site', 'Admin');
                if($role_id == $masterAdminRoleId){
                    $db->where('a.master_disabled', 0);
                }else{
                    $db->where('a.disabled', 0);
                }
                $db->where('a.type', 'Page', '!=');
                if ($result[0]["role_id"] != 1 && $result[0]["role_id"] != $masterAdminRoleId) {
                    $db->where('b.disabled', 0);
                    $db->where('b.role_id', $result[0]['role_id']);
                    $db->join('roles_permission b', 'b.permission_id=a.id', 'LEFT');
                }

                $db->orderBy('level', "asc");
                $res = $db->get('permissions a', null, 'a.id, a.name, a.type, a.parent_id, a.file_path, a.priority, a.icon_class_name, a.translation_code, a.reference_id,a.reference_table, a.last_line');
                foreach ($res as $array) {

                    // For frontend, if permission has lastLine, add a line below as separator
                    if ($array['last_line']){
                        $array['lastLine']=true;
                    }

                    if($array["reference_table"]){
                        switch($array["reference_table"]){
                            case "mlm_bonus":
                                $db->where('disabled',0);
                                $db->where('id',$array["reference_id"]);
                                $res2 = $db->getONE('mlm_bonus',"id");
                                // $data['test'] = $res2;
                                if($res2){
                                    if (!empty($array["translation_code"])){
                                        $array["name"] = $translations[$array["translation_code"]][$language];
                                    }
                                    $data['permissions'][] = $array;
                                }
                                break;
                            case "credit":
                                $db->where('id', $array["reference_id"]);
                                $res2 = $db->getONE('credit',"admin_translation_code");
                                if($res2){
                                    
                                    $array["name"] = $translations[$res2["admin_translation_code"]][$language];
                                    $data['permissions'][] = $array;
                                }

                                break;
                            default:
                                $data['permissions'][] = $array;
                                
                                break;
                        }
                    }else{
                        if (!empty($array["translation_code"])){
                            $array["name"] = $translations[$array["translation_code"]][$language];
                        }
                        $data['permissions'][] = $array;
                    }
                    unset($array['lastLine']);
                }

                unset($array);

                $sessionID = md5($result[0]['username'] . time());

                $fields = array('session_id', 'last_login', 'updated_at');
                $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

                $db->where('id', $id);
                $db->update('admin', array_combine($fields, $values));

                //Insert Session ID
                User::insertSessionData($id,$sessionID,$dateTime);

                if($role_id != $masterAdminRoleId){
                    // This is to get the Pages from the permissions table
                    $ids = $db->subQuery();
                    $ids->where('disabled', 0);
                    $ids->get('roles_permission', null, 'permission_id');

                    $db->where('id', $ids, 'in');
                    $db->where('disabled', 0);
                }else{
                    $db->where('master_disabled', 0);
                }
                $db->where('type', 'Page');
                $db->where('site', 'Admin');
                $pageResults = $db->get('permissions');
                foreach ($pageResults as $array) {
                    $data['pages'][] = $array;
                }

                // This is to get the hidden submenu from the permissions table
                $db->where('type', 'Hidden');
                $db->where('site', 'Admin');
                if($role_id == $masterAdminRoleId){
                    $db->where('master_disabled', 0);
                }else{
                    $db->where('disabled', 0);
                }
                $hiddenResults = $db->get('permissions');
                foreach ($hiddenResults as $array){
                    $data['hidden'][] = $array;
                }

                $admin['userID']                = $id;
                $admin['username']              = $result[0]['name'];
                $admin['userEmail']             = $result[0]['email'];
                $admin['userRoleID']            = $result[0]['role_id'];
                
                /* handle agent login - START */ 
                // get role name
                $db->where('id', $result[0]['role_id']);
                $admin['userRoleName'] = $db->getValue('roles', 'name');
                // if role is agent, get agent clientID
                if($admin['userRoleName'] == "Agent"){
                    $db->where("admin_id", $id);
                    $agentID = $db->getValue("admin_agent", "leader_id");

                }else{
                    // set director as agent
                    $db->where("username", "director");
                    $agentID = $db->getValue("client", "id");
                }
                /* handle agent login - END (can improve in future :D )*/ 

                $admin['userAgentID']           = $agentID;
                $admin['sessionID']             = $sessionID;
                $admin['timeOutFlag']           = Setting::getAdminTimeOut();
                $admin['pagingCount']           = Setting::getAdminPageLimit();
                $admin['decimalPlaces']         = Setting::getSystemDecimalPlaces();

                $data['userDetails'] = $admin;

                // Get product list
                /*$productList = Product::getProductList();
                $data['productList'] = $productList['data'];*/

                // Get member status for filter
                $db->where('name','memberStatus');
                $res = $db->getValue('system_settings','value');

                $statusList = json_decode($res);
                $memberStatusList = array();
                foreach ($statusList as $status => $translationCode) {
                    $memberStatusList[$status] = $translations[$translationCode][$language];
                }

                $data['memberStatusList'] = $memberStatusList;

                $db->where('name', 'treasureRobotTypes');
                $robotType = $db->getOne('system_settings', 'value');
                $robotTypes = explode('#', $robotType['value']);
                $data['robotTypes'] = $robotTypes;

                // Get Pin Type
                $db->where('name','pinType');
                $res = $db->getValue('system_settings','value');

                $pinTypeList = array();
                foreach (json_decode($res) as $pinType => $translationCode) {
                    $pinTypeList[$pinType] = $translations[$translationCode][$language];
                }

                $data['pinTypeList'] = $pinTypeList;

                /* get user's inbox message */
                // $inboxSubQuery = $db->subQuery();
                // $inboxSubQuery->where("`type`", "support");
                // $inboxSubQuery->where("`status`", "Closed", "!=");
                // $inboxSubQuery->get("`mlm_ticket`", null, "`id`");
                // $db->where("`ticket_id`", $inboxSubQuery, "IN");
                // $db->where("`read`", 0);
                // $db->where("`sender_id`", $id, "!=");
                // $inboxUnreadMessage = $db->getValue("`mlm_ticket_details`", "COUNT(*)");
                // $data['inboxUnreadMessage'] = $inboxUnreadMessage;

				$inbox = Client::getInboxUnreadMessage($id,"Admin");
                $data['inboxUnreadMessage'] = $inbox["data"]["inboxUnreadMessage"];
                // $unread = Self::getWithdrawalUnreadCount($id);
                // $data['inboxUnreadMessage'] = $unread['data']['inboxUnreadMessage'];

                $countryParams = array("pagination" => "No");
                $resultCountryList = Country::getCountriesList($countryParams);
                $data['countryList'] = $resultCountryList['data']['countriesList'];

                // Custom Country List
                $deliveryCountryList = Country::getCustomCountryList(array('type' => 'delivery'));
                $data['deliveryCountryList'] = $deliveryCountryList['data']['countryList'];

                $db->where('disabled', 0);
                $availableCategory = $db->get('inv_category', null, 'id, name');

                foreach ($availableCategory as $value) {
                    $categoryIDAry[$value['id']] = $value['id'];
                }

                if($categoryIDAry) {
                    $db->where('module_id', $categoryIDAry, 'IN');
                    $db->where('language', $language);
                    $db->where('module', 'inv_category');
                    $db->where('type', 'name');
                    $categoryLang = $db->map('module_id')->get('inv_language', null, 'module_id, content');
                }

                foreach ($availableCategory as $value) {
                    $value['categoryDisplay'] = $categoryLang[$value['id']];

                    $categoryList[] = $value;
                }

                $data['categoryList'] = $categoryList;

                $db->where('type', 'Client');
                $db->where('activated', array(1,2),"IN");
                $db->where('disabled', 0);
                $memberCount = $db->getValue('client', 'count(*)');

                $data['memberCount'] = $memberCount;

                $db->where('name','Supplier');
                $db->where('site','Admin');
                $supplierRoleID = $db->getValue('roles','id');

                $db->where('role_id',$supplierRoleID);
                $data['supplierIDArr'] = $db->get('admin',null,'id,username');

                $db->groupBy('subject');
                $invTrxnSubRes = $db->get('inv_product_transaction',null,'subject');
                foreach ($invTrxnSubRes as $invTrxnSubRow) {
                    $invTrxnSubArr['value'] = $invTrxnSubRow['subject'];
                    $invTrxnSubArr['display'] = General::getTranslationByName($invTrxnSubRow['subject']);
                    $data['invTrxnSubArr'][] = $invTrxnSubArr;
                }

                //Get Language
                $db->where("disabled", 0);
                $availableLanguages = $db->get("languages", NULL, "id, language, language_code");

                foreach ($availableLanguages as $value) {
                    $row = array(
                        "languageType" => $value['language'],
                        "languageDisplay" => $translations[$value['language_code']][$language]
                    );

                    $languageList[] = $row;
                }

                $data['languageList'] = $languageList;

                $categoryTypeAry = array('package', 'product');
                foreach ($categoryTypeAry as $categoryRow) {
                    $category['type'] = $categoryRow;
                    $category['display'] = General::getTranslationByName($categoryRow);

                    $categoryType[] = $category;
                }

                $data['categoryType'] = $categoryType;
                
                $db->where('status', 'Active');
                $supplier = $db->get('inv_supplier',null,'id, name, code');

                foreach($supplier as $value){
                    $supplierDetail['id'] = $value['id'];
                    $supplierDetail['name'] = $value['name'];
                    $supplierDetail['code'] = $value['code'];
                    $supplierDetailAry[] = $supplierDetail;
                }

                $data['supplier'] = $supplierDetailAry;
                
                //Get Product Category List
                /*$db->groupBy('category');
                $productCategoryRes = $db-> get('mlm_product',null,'category, id, name');
               

                foreach ($productCategoryRes as $productCategoryRow) {
                    $productCategoryArr['value'] = $productCategoryRow['category'];
                    $productCategoryArr['display'] = General::getTranslationByName($productCategoryRow['category']);
                    $data['productCategoryArr'][] = $productCategoryArr;
                }*/

                $data['creditList'] = $db->get('credit', null, 'name, type, code, admin_translation_code');

                //Get Rank List
                $db->orderBy('priority','ASC');
                $rankRes = $db->get('rank',null,'id,type,translation_code');
                foreach ($rankRes as $rankRow) {
                    $rankData['id'] = $rankRow['id'];
                    $rankData['display'] = $translations[$rankRow['translation_code']][$language];
                    $rankList[$rankRow['type']][] = $rankData;
                }
                $data['rankList'] = $rankList;

                // GET Activity Log Monthly Filter
                $activityTableRes = $db->rawQuery('SHOW TABLES LIKE "activity_log_%"');
                foreach ($activityTableRes as $tableDetail) {
                    foreach ($tableDetail as $activityTableRow) {
                        $logDate = str_replace("activity_log_", "", $activityTableRow);

                        $activityLogDateArr[$logDate]['value'] = $logDate;
                        $activityLogDateArr[$logDate]['Display'] = date("Y F",strtotime($logDate."01"));
                    }
                }
                krsort($activityLogDateArr);
                $data['activityLogDateArr'] = $activityLogDateArr;

                $adminRoleList = Setting::$systemSetting['InvEditableRoles'];
                $adminRolesListAry = explode("#", $adminRoleList);

                $db->where('id', $result[0]['id']);
                $db->where('role_id', $adminRolesListAry, 'IN');
                $getUserID = $db->getValue('admin','id');

                $data['invEditable'] = $getUserID ? '1':'0';

                $db->where("type", "Transaction Type");
                $db->where("disabled", "0");
                $typeRes = $db->get("type_mapping", null, "name, translation_code");

                foreach($typeRes as &$typeRow) {
                    $typeRow["display"] = $translations[$typeRow["translation_code"]][$language]; 
                    $typeList[] = $typeRow;
                }
                $data["transactionTypeList"] = $typeList;

                $db->where('name', 'nicepaySetting');
                $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
                $nicepayBankAry = json_decode($nicepaySetting['description'], true);
                foreach ($nicepayBankAry as $npBankCode => $npBankName) {
                    $tempBank['code'] = $npBankCode;
                    $tempBank['name'] = $npBankName;
                    $data["paymentGatewayBankAry"][] = $tempBank;
                }

                $db->groupBy('status');
                $paymentGatewayStatusAry = $db->get('mlm_pending_payment', NULL, 'status');
                foreach ($paymentGatewayStatusAry as $statusValue) {
                    $tempStatus['value'] = $statusValue['status'];
                    $tempStatus['display'] = General::getTranslationByName("PG ".$statusValue['status']);
                    $data["paymentGatewayStatusAry"][] = $tempStatus;
                }                

                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00103"][$language] /* Invalid Login */, 'data' => "");
        }

        public function getAdminList($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData      = $params['inputData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $roleID = $db->subQuery();
            $roleID->where('name', 'Master Admin');
            $roleID->getOne('roles', "id");
            $db->where('role_id',$roleID);
            $masterAdminID = $db->get('admin',null,'id');
            foreach ($masterAdminID as $masterAdminIDKey => $masterAdminIDValue) {
                $masterAdminIDAry[] = $masterAdminIDValue['id'];
            }

            //Get the limit.
            $limit           = General::getLimit($pageNumber);
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'name':
                            if ($dataType == "like") {
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            }else{
                                $db->where('name', $dataValue);
                            }
                            break;
                                
                            break;
                            
                        case 'username':
                            $db->where('username', $dataValue);
                                
                            break;
                            
                        case 'email':
                            $db->where('email', $dataValue);
                                
                            break;
                            
                        case 'disabled':
                            $db->where('disabled', $dataValue);
                                
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");

            $getRoleName  = '(SELECT name FROM roles WHERE admin.role_id = roles.id) as roleName';

            //Meaning a = admin table
            if($masterAdminIDAry) $db->where("id", $masterAdminIDAry, "NOT IN");
            $result = $db->get("admin", $limit, $getRoleName. ", id, username, name as Name, email, disabled, created_at, last_login");
            // print_r($result);
            $totalRecord = $copyDb->getValue ("admin", "count(*)");

            if (!empty($result)) {
                foreach($result as $value) {
                    $admin['id']           = $value['id'];
                    $admin['username']     = $value['username'];
                    $admin['name']         = $value['Name'];
                    $admin['email']        = $value['email'];
                    $admin['roleName']     = $value['roleName'];
                    $admin['disabled']     = ($value['disabled'] == 1)? 'Yes':'No';
                    $admin['createdAt']    = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";
                    $admin['price']    = $value['last_login'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['last_login'])) : "-";
                    // $admin['price']        = $value['last_login'];

                    $adminList[] = $admin;
                }

                $data['adminList']   = $adminList;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }

            else
            {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function getAdminDetails($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Admin */, 'data'=> '');

            $db->where('id', $id);
            $result = $db->getOne("admin", "id, username, name, email, disabled as status"); //, role_id as roleID

            if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");

            foreach ($result as $key => $value) {
                $adminDetail[$key] = $value;
            }

            $data['adminDetail'] = $adminDetail;

            $db->where('admin_id', $id);
            $leaderID = $db->getValue('admin_agent', 'leader_id');

            if($leaderID){
            	$db->where('id', $leaderID);
            	$leaderUsername = $db->getValue('client', 'username');
            	$data['adminDetail']['leaderUsername'] = $leaderUsername;
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addAdmins($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            //Check the stored password type.
            $passwordFlag = Setting::$systemSetting['passwordVerification'];

            $email        = trim($params['email']);
            $fullName     = trim($params['fullName']);
            $username     = trim($params['username']);
            $password     = trim($params['password']);
            $leaderUsername = trim($params['leaderUsername']);
            $roleID       = trim($params['roleID']);
            $status       = trim($params['status']);

            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00106"][$language] /* Please Enter Full Name */, 'data'=>"");

            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00107"][$language] /* Please Enter Username */, 'data'=>"");

            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00108"][$language] /* Please Enter Email */, 'data'=>"");

            if(strlen($roleID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Please Select a Role */, 'data'=>"");

            $db->where("id", $roleID);
            $roleName = $db->getValue("roles", "name");

            // if($roleName == "Agent"){
            //     if(strlen($leaderUsername) == 0){
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00732"][$language] /* Please Enter Leader Username */, 'data' => "");
            //     }

            if($leaderUsername){
            //     // check if leader exist
                $db->where('username', $leaderUsername);
                // $db->orWhere('concat(dial_code,phone)', $leaderUsername);
                $leaderData = $db->getOne("client", "id, password");
                if(empty($leaderData)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00594"][$language] /* Leader Not Found */, 'data'=>"");
                }
            }
            // }else{

            if(strlen($password) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00109"][$language] /* Please Enter Password */, 'data'=>"");
            // }

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Please Choose a Status */, 'data'=>"");

            $db->where('email', $email);
            $result = $db->get('admin');
            if (!empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00111"][$language] /* Email Already Used */, 'data'=>"");

            $db->where('username', $username);
            $result = $db->get('admin');
            if (!empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00692"][$language] /* Username Already Used */, 'data'=>"");

            // Retrieve the encrypted password based on settings
            // if($roleName == "Agent"){
            //     $password = $leaderData["password"];
            // }else{
                $password = Setting::getEncryptedPassword($password);
            // }

            $adminID = $db->getNewID();
            $fields = array("id", "email", "password", "username","name", "created_at", "role_id", "disabled", "updated_at");
            $values = array($adminID, $email, $password, $username, $fullName, date("Y-m-d H:i:s"), $roleID, $status, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            try{
                $result = $db->insert("admin", $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00112"][$language] /* Failed to add new user */, 'data'=>"");
            }

            if($leaderUsername){
                $insert = array(
                	"leader_id" => $leaderData['id'], 
                	"leader_username" => $leaderUsername, 
                	"admin_id" => $adminID, 
                	"created_at" => date("Y-m-d H:i:s")
                );
                $result2 = $db->insert("admin_agent", $insert);
            }

            if($roleName == 'Supplier'){
                $db->where('role_id',$roleID);
                $dataOut['supplierIDArr'] = $db->get('admin',null,'id,username');
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */, 'data'=>$dataOut);
        }

        public function editAdmins($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id       = trim($params['id']);
            $email    = trim($params['email']);
            $fullName = trim($params['fullName']);
            $username = trim($params['username']);
            $leaderUsername = trim($params['leaderUsername']);
            $roleID   = trim($params['roleID']);
            $status   = trim($params['status']);
            $password = trim($params['password']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00113"][$language] /* Admin ID does not exist */, 'data'=>"");

            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00114"][$language] /* Please Enter Email */, 'data'=>"");

            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00115"][$language] /* Please Enter Full Name */, 'data'=>"");

            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00116"][$language] /* Please Enter Username */, 'data'=>"");

            // if(strlen($roleID) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Please Select a Role */, 'data'=>"");

            // $db->where('id', $roleID);
            // $result = $db->getOne('roles');
            // if (empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Invalid Admin Role */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00117"][$language] /* Please Select a Status */, 'data'=>"");

	    $db->where('username', $username);
	    $db->where('id',$id,'!=');
            $usernameResult = $db->get('admin');
            if (!empty($usernameResult)) return array('status' => "error", 'code' => 1, 'statusMsg' => "username already exists", 'data'=>"");
            
	    $db->where('id', $id);
            $result = $db->getOne('admin');

            if (!empty($result)) {
                $fields    = array("email", "username", "name", "role_id", "disabled", "updated_at");
                $values    = array($email, $username, $fullName, $roleID, $status, date("Y-m-d H:i:s"));

                if (strlen($password) != 0) {
                    array_push($fields, "password");
                    array_push($values, Setting::getEncryptedPassword($password));
                }

                if($leaderUsername){
                	$db->where('admin_id', $id);
                	$dbLeaderUsername = $db->getValue('admin_agent', 'id');

                	$db->where('username', $leaderUsername);
                	$leaderID = $db->getValue('client', 'id');

                	if(empty($dbLeaderUsername)){
                		$insert = array(
    		            	"leader_id" => $leaderID, 
    		            	"leader_username" => $leaderUsername, 
    		            	"admin_id" => $id, 
    		            	"created_at" => date("Y-m-d H:i:s")
    		            );
    		            $result2 = $db->insert("admin_agent", $insert);
    		            
                	}else{
                		$update = array(
    		            	"leader_id" => $leaderID, 
    		            	"leader_username" => $leaderUsername, 
    		            	"created_at" => date("Y-m-d H:i:s")
                		);
                		$db->where('admin_id', $id);
                		$result2 = $db->update("admin_agent", $update);
                	}
                    
    	        }

                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("admin", $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => "");

            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data'=>"");
            }
        }

        public function getMemberList($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $seeAll         = $params['seeAll'];

            $dateTime = date('Y-m-d H:i:s');

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit              = General::getLimit($pageNumber);
            $searchData         = $params['searchData'];
            
    		$adminLeaderAry = Setting::getAdminLeaderAry();

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            // Means the search params is there
            $cpDb = $db->copy();
            $tempCopy = $db->copy();
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

                            $db->where('id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);
                    switch($dataName) {

                        case 'memberID':
                            $db->where('member_id', $dataValue);
                            break;

                        case 'email':
                             switch ($dataType) {
                                case 'like':
                                    $db->where("email", "%" . $dataValue . "%", "LIKE");
                                    break;
                                
                                case 'match':
                                    $db->where("email", $dataValue);
                                    break;
                            }
                            break;

                        case 'name':
                            if($dataType == "like"){
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            }else{
                                $db->where('name', $dataValue);
                            }
                            break;

                        case 'countryID':
                            $db->where('country_id', $dataValue); 
                            break;

                        case 'sponsor':
                            $sponsorID = $db->subQuery();
                            $sponsorID->where('username', $dataValue);
                            $sponsorID->getOne('client', "id");
                            $db->where('sponsor_id', $sponsorID);
                            break;

                        case 'status':
                            if ($dataValue == "suspended") {
                                $db->where("suspended", 1);
                            } elseif ($dataValue == "terminated") {
                                $db->where("`terminated`", 1);
                            } elseif ($dataValue == "disabled") {
                                $db->where("`disabled`", 1);
                            } else{
                                $db->where("activated",1);
                                $db->where("suspended",0);
                                $db->where("`terminated`",0);
                                $db->where("disabled",0);
                            }

                            break;
                        
                        case 'phone':
                            $db->where('phone', $dataValue);
                            break;

                        case 'sponsorID':
                            $sq = $db->subQuery();
                            $sq->where('member_id', $dataValue);
                            $sq->get('client', null, 'id');
                            $db->where('sponsor_id', $sq);
                            break;

                        case 'username':
                            $db->where('username', $dataValue);
                            break;
                        case 'rank':
                            $sq = $db->subQuery();
                            $tempCopy->where('name', 'rankDisplay');
                            $tempCopy->orderBy('created_at', 'DESC');
                            $tempCopy->groupBy("client_id");
                            $tempCopy->groupBy("type");
                            $tempRes = $tempCopy->get('client_rank', NULL,'client_id, MAX(id) as id, created_at');

                            foreach ($tempRes as $row) {
                                $maxID[] = $row['id'];
                            }

                            $sq->where("id", $maxID, "IN");
                            $sq->where("rank_id", $dataValue);
                            $sq->getValue('client_rank', 'client_id',null);

                            $db->where('id', $sq, "IN");
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            if($adminLeaderAry){
            	$db->where('id', $adminLeaderAry, 'IN');
            }
            //try this if face query performance issues
            //$sub = $db->subQuery();
            //$sub->where('ag.admin_id', $userID);
            //$sub->join('admin_agent ag' , "ts.trace_key like CONCAT('%', ag.leader_id, '%')", 'INNER' );
            //$sub->get('tree_sponsor ts',null,'ts.client_id');

            //if($sub) $db->where('id',$sub,'IN');
            $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
            $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
            $getSponsorMemberID = "(SELECT member_id FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_id";
            $getSponsorName = "(SELECT name FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_name";
            $db->where('type', "Client");
            if ($params['pageType'] == "lockAccount"){
                $db->where('disabled', "0");
            }
            $copyDb = $db->copy();
            $totalRecords = $copyDb->getValue("client", "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecords);
            }
            $db->orderBy("created_at","DESC");
            $result = $db->get('client', $limit, 'id, member_id, name, username, '.$getCountryName.','.$getSponsorUsername.','.$getSponsorMemberID.','.$getSponsorName.',activated, disabled, suspended, freezed, 
                `terminated`, last_login, last_login_ip, created_at, email');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00105'][$language] /* No Results Found. */, 'data' => "");

            // first day of this month
            $firstDayOfTheMonth = date('Y-m-d H:i:s', strtotime('-1 second', strtotime(date('Y-m-01'))));

            $rankData = $db->map('id')->get('rank', null, 'id, name, translation_code');

            foreach ($result as $clientDetailsRow) {
                $clientIDAry[] = $clientDetailsRow['id'];
            }

            $db->where('client_id',$clientIDAry,"IN");
            $traceKeyArr = $db->map('client_id')->get('tree_sponsor',null,'client_id,trace_key');

            //$clientRankArr = Bonus::getClientRank("Bonus Tier", "", $dateTime, 'rankDisplay', "");

            $db->where('client_id', $clientIDAry, 'IN');
            $db->where('name','rankDisplay');
            $db->groupBy('client_id');
            $clientRankDetail = $db->get('client_rank', null, 'client_id, max(id) as maxid');

            $clientRankArr = array();
            foreach($clientRankDetail as $key => $value) {
                 $db->where('id', $value['maxid']);
                 $thisRankId = $db->getValue("client_rank", "rank_id");
                 $clientRankArr[$value['client_id']]['rank_id'] = $thisRankId;
            }

            $directorRankID = 4;
            foreach ($result as $row) {
                $sponsorTreeTrace = explode("/", $traceKeyArr[$row['id']]);
                krsort($sponsorTreeTrace);

                foreach ($sponsorTreeTrace as $uplineID) {
                    if($clientRankArr[$uplineID]['rank_id'] >= $directorRankID && ($uplineID != $row['id'])){
                        $nearDirector[$row['id']] = $uplineID;
                        break;
                    }
                }
            }

            if($nearDirector){
                $db->where('id',$nearDirector,"IN");
                $nearDirectorData = $db->map('id')->get('client',null,'id,name');
            }

            $db->where('client_id', $clientIDAry, "IN");
            $clientSalesRes = $db->map('client_id')->get('client_sales', null, 'id, client_id, activated, own_sales, group_sales, pgp_sales, active_leg');

            if($clientIDAry){
                $db->where('client_id', $clientIDAry, "IN");
                $db->where('address_type', 'billing');
                $city = $db->map('client_id')->get('address', null, 'client_id, city_id');

                foreach($clientIDAry as $clientIDRow){
                    $db->where('client_id', $clientIDRow);
                    $pvp[$clientIDRow] = $db->getValue('mlm_bonus_in', 'SUM(bonus_value)');

                    $db->where('trace_key','%'.$clientIDRow.'%','LIKE');
                    $db->where('client_id',$clientIDRow, '!=');
                    $downlineIDArr[$clientIDRow] = $db->map('client_id')->get('tree_placement',null,'client_id');

                    if ($downlineIDArr[$clientIDRow]) {
                        $db->where('client_id',$downlineIDArr[$clientIDRow],'IN');
                        $dvp[$clientIDRow] = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
                    }
                }
            }

            if($city){
                $db->where('id', $city, "IN");
                $cityName = $db->map('id')->get('city',  NULL, 'id, name');
            }

            foreach($result as $value) {
                $client['clientID'] = $value['id'];
                $mainLeaderUsername = Tree::getMainLeaderUsername($client);
                $client['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                unset($rankID);    
                $rankID = $clientRankArr[$client['clientID']]['rank_id'];
                $client['rank'] = $translations[$rankData[$rankID]['translation_code']][$language]?:$rankData[$rankID]['name']?:'-';
               





 
                // if($clientSalesRes[$value['id']]['activated']){
                //     $client['status'] = General::getTranslationByName('Active');
                // }else{
                //     $client['status'] = General::getTranslationByName('Disable');
                // }

                if ($value['activated'] == 1) {
                    $client['status'] = $translations['A00372'][$language];
                } else {
                    $client['status'] = $translations['A00373'][$language];
                }

                if ($value['disabled'] == 1) {
                    $client['status'] = $translations['A00104'][$language];
                } elseif ($value['suspended'] == 1) {
                    $client['status'] = $translations['A00156'][$language];
                } elseif ($value['freezed'] == 1) {
                    $client['status'] = $translations['A00176'][$language];
                } elseif ($value['terminated'] == 1) {
                    $client['status'] = $translations['A01131'][$language];
                }

                $client['memberID'] = $value['member_id'];
                $client['name'] = $value['name'];
                $client['username'] = $value['username'];
                $client['email'] = $value['email'];
                $client['sponsorName'] = $value['sponsor_name'] ?: "-";
                $client['sponsorUsername'] = $value['sponsor_username'] ?: "-";
                $client['sponsorMemberID'] = $value['sponsor_id'] ?:"-";
                $client['country'] = $value['country_name'] ? $value['country_name'] : "-";
                $client['city']    = $cityName[$city[$value['id']]]? $cityName[$city[$value['id']]] : "-";

                $client['lastLogin'] = $value['last_login'] == "0000-00-00 00:00:00" ? "-" : date($dateTimeFormat,strtotime($value['last_login']));
                $client['lastLoginIp'] = $value['last_login_ip'] ?: "-";
                $client['createdAt'] = date($dateTimeFormat,strtotime($value['created_at']));
                // $client['pvp']  = Setting::setDecimal($clientSalesRes[$value['id']]['own_sales']);
                $client['pvp'] = $pvp[$value['id']] ? Setting::setDecimal($pvp[$value['id']]) : Setting::setDecimal(0);
                $client['pgp']  = Setting::setDecimal(($clientSalesRes[$value['id']]['own_sales'] + $clientSalesRes[$value['id']]['pgp_sales']));
                // $client['dvp']  = Setting::setDecimal(($clientSalesRes[$value['id']]['own_sales'] + $clientSalesRes[$value['id']]['group_sales']));
                $client['dvp'] = $dvp[$value['id']] ? Setting::setDecimal($dvp[$value['id']]) : Setting::setDecimal(0);
                $client['activeLeg']  = $clientSalesRes[$value['id']]['active_leg'];
                $client['nearDirector'] = $nearDirectorData[$nearDirector[$value['id']]]?:"-";

                $clientList[] = $client;
            }

            $data['memberList']  = $clientList;
            $data['totalPage']   = ceil($totalRecords/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $limit[1];
            $data['countryList'] = $db->get('country', null, 'id, name');

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getMainLeaderList() {

            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $db->where('name', "isLeader");
            $db->where('value', "mainLeader");
            $mainLeaderID = $db->get("client_setting", NULL, "client_id");

            foreach ($mainLeaderID as $key => $value) {

                $db->where('id', $value["client_id"]);
                $mainLeaderUsername = $db->get('client', NULL,'username');

                foreach ($mainLeaderUsername as $key1 => $value1) {
                    // $mainLeaderArray[$value["client_id"]] = $value1["username"];
                    $mainLeaderIDArray[] = $value["client_id"];
                }
            }

            return $mainLeaderIDArray;
        }

        public function getMemberDetails($params) {
            $db = MysqliDb::getInstance();

            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            
            $userID = $db->userID;
            $site = $db->userType;

            if($site == 'Member'){
                $clientID = $userID;
            }else{
                $clientID = trim($params['clientID']);
            }

            $db->where('id', $clientID);
            $member = $db->getOne("client", 'name, username, member_id, email, dial_code, phone, address, country_id, dob, weChat, whatsApp, state_id, activated, disabled, suspended, freezed, turnOffPopUpMemo, passport, identity_number, main_id, sponsor_id,`terminated`, `created_at`');

            $db->where('client_id', $clientID);
            $detailInfo = $db->getOne('client_detail', 'gender, martial_status, num_of_child, child_age, tax_number');

            $db->where('id', $member['sponsor_id']);
            $sponsorMemberID = $db->getValue('client', 'member_id');

            $db->where('id', $member['country_id']);
            $memberCountryTranslation = $db->getOne('country', 'country_code,translation_code');

            $member['fullname'] = $member['name'];
            $member['username'] = $member['username'];
            $member['email'] = $member['email'];
            $member["dialingArea"] = $memberCountryTranslation["country_code"];
            $member['phoneNumber'] = $member['phone'];
            $member['dob'] = $member['dob'];
            $member['gender'] = General::getTranslationByName($detailInfo['gender']);
            $member["countryID"] = $member["country_id"];
            $member['country'] = $translations[$memberCountryTranslation["translation_code"]][$language];
            $member['sponsorID'] = $sponsorMemberID;
            $member['joinedAt'] = $member['created_at'];

            $newestID = $db->subQuery();
            $newestID->where('client_id', $clientID);
            $newestID->groupBy('doc_type');
            $newestID->get('mlm_kyc', null, 'MAX(id)');
            $db->where('id', $newestID, 'IN');
            $db->where('client_id',$clientID);
            $checkKYCDetails = $db->map('doc_type')->get('mlm_kyc',null,'doc_type, id, status');

            foreach($checkKYCDetails as $value){
                if($value['status'] == 'Waiting Approval'){
                    $waitingID[] = $value['id'];
                }
            }

            if($waitingID){
                $db->where('kyc_id', $waitingID, 'IN');
                $db->where('name', 'Image Name 1');
                $getKYCImageName = $db->map('kyc_id')->get('mlm_kyc_detail', null, 'kyc_id, value');
            }

            $db->where('email_verified','1');
            $db->where('client_id',$clientID);
            $checkEmailValidate = $db->has('client_detail');

            $member['emailVerify'] = $checkEmailValidate ? 1 : 0;

            $kyc = array('IDVerification','BankAccountCover','NPWPVerification');

            if(!empty($checkEmailValidate)){
                foreach($checkKYCDetails as $key=>$value){
                    $key = str_replace(" ","",$key);

                    $member[$key] = $value['status'];
                    if($value['status'] == 'Waiting Approval'){
                        $member[$key.'ImageName'] = $getKYCImageName[$value['id']];
                    }
                    unset($kyc[array_search($key, $kyc)]);
                }

                if(!empty($kyc)){
                    foreach($kyc as $remaining){
                        $member[$remaining] = 0;
                    }
                }
            }else{
                foreach($kyc as $key){
                    $member[$key] = 0;
                }
            }

            /*$countryParams = array('pagination' => 'No');
            $countryList = Country::getCountriesList($countryParams);
            if($countryList['status'] == 'ok')
                $data['countryList'] = $countryList['data']['countriesList'];

            $resultStateList = Country::getState();
            $data['stateList'] = $resultStateList;

            foreach ($data['countryList'] as $key => $countryValue) {
                 
                    if($countryValue["id"]==$member["country_id"]){
                        $member["countryDisplay"] = $countryValue["display"];
                        break;
                    }

            }*/   

            $data['member'] = $member;
            if(empty($member))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['B00106'][$language] /* No Results Found. */, 'data' => "");
            $memberDetails = Client::getCustomerServiceMemberDetails($clientID);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];

            // $db->where('client_id', $clientID);
            // $detailInfo = $db->getOne('client_detail', 'gender, martial_status, num_of_child, tax_number');

            $db->where('client_id', $clientID);
            $db->where('status', 'Active');
            $db->orderBy('created_at', 'DESC');
            $bankRes = $db->getOne('mlm_client_bank', 'bank_id, account_no, branch, account_holder, bank_city');

            // credentials
            $credentials['fullName'] = $member['name'];
            $credentials['gender'] = General::getTranslationByName($detailInfo['gender']);
            $credentials['email'] = $member['email'];
            $credentials['phone'] = $member['phone'];
            $credentials['countryID'] = $member['country_id'];
            $credentials['passport'] = $member['passport'];
            $credentials['identityNumber'] = $member['identity_number'];
            $credentials['dob'] = $member['dob'];

            // additionalInfo
            $additionalInfo['martialStatus'] = $detailInfo['martial_status'];
            $additionalInfo['childNumber'] = $detailInfo['num_of_child'];
            
            $additionalInfo['childAge'] = $detailInfo['child_age'];
            if(empty($detailInfo['child_age']) && $detailInfo['child_age'] !="0")
                $additionalInfo['childAge'] = '-';
            
            $additionalInfo['taxNumber'] = $detailInfo['tax_number'];            

            $childAgeOption = explode('#', Setting::$systemSetting['childAgeOption']);
            foreach ($childAgeOption as $childAgeValue) {
                $childAgeData['value'] = $childAgeValue;

                if(is_numeric($childAgeValue)){
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00481'][$language])/*%%childAgeValue%% years old and above*/;
                }else{
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00482'][$language])/*%%childAgeValue%% years old*/;
                }
                $childAgeOptionArr[] = $childAgeData;
            }

             $additionalInfo['childAgeOption'] = $childAgeOptionArr;

            if($member['activated']){
                $member['status'] = 'active';
            }
            if($member['suspended']){
                $member['status'] = 'suspended';
            }else if($member['freezed']){
                $member['status'] = 'freezed';
            }else if($member['terminated']){
                $member['status'] = 'terminated';
            }

            $additionalInfo['status'] = $member['status'];
            $additionalInfo['memberID'] = $member['member_id'];
            $additionalInfo['sponsorID'] = $sponsorMemberID;

            // bankInfo
            $bankInfo['bankID'] = $bankRes['bank_id'];
            $bankInfo['accountNo'] = $bankRes['account_no'];
            $bankInfo['branch'] = $bankRes['branch'];
            $bankInfo['accountHolder'] = $bankRes['account_holder'];
            $bankInfo['bankCity'] = $bankRes['bank_city'];

            $db->where('disabled', '0');
            $db->where('address_type', 'billing');
            $db->where('client_id', $clientID);
            $billingRes = $db->getOne('address', 'name, email, phone, address, state_id, district_id, sub_district_id, post_code_id, city_id, remarks, country_id');

            $billingInfo['name'] = $billingRes['name'];
            $billingInfo['email'] = $billingRes['email'];
            $billingInfo['phone'] = $billingRes['phone'];
            $billingInfo['address'] = $billingRes['address'];
            $billingInfo['remarks'] = $billingRes['remarks'];
            $billingInfo['countryId'] = $billingRes['country_id'];

            $db->where('disabled', '0');
            $db->where('address_type', 'delivery');
            $db->where('client_id', $clientID);
            $db->orderBy('created_at', 'DESC');
            $deliveryRes = $db->getOne('address', 'client_id, name, email, phone, address, state_id, district_id, sub_district_id, post_code_id, city_id, remarks, country_id');

            $deliveryInfo['name'] = $deliveryRes['name'];
            $deliveryInfo['email'] = $deliveryRes['email'];
            $deliveryInfo['phone'] = $deliveryRes['phone'];
            $deliveryInfo['address'] = $deliveryRes['address'];
            $deliveryInfo['remarks'] = $deliveryRes['remarks'];
            $deliveryInfo['country_id'] = $deliveryRes['country_id'];

            $db->where("id", array($billingInfo["countryId"]?:"", $deliveryInfo["country_id"]?:"", $credentials["countryID"]?:""), "IN");
            $countryCode = $db->map("id")->get("country", null, "id, country_code");

            $db->where("id", array($billingRes["district_id"]?:"", $deliveryRes["district_id"]?:"",), "IN");
            $countyDisplay = $db->map("id")->get("county", null, "id,name,translation_code");

            $db->where("id", array($billingRes["sub_district_id"]?:"", $deliveryRes["sub_district_id"]?:""), "IN");
            $subCountyDisplay = $db->map("id")->get("sub_county", null, "id,name,translation_code");

            $db->where("id", array($billingRes["post_code_id"]?:"", $deliveryRes["post_code_id"]?:""), "IN");
            $zipCodeDisplay = $db->map("id")->get("zip_code", null, "id,name,translation_code");

            $db->where("id", array($billingRes["city_id"]?:"", $deliveryRes["city_id"]?:""), "IN");
            $cityDisplay = $db->map("id")->get("city", null, "id,name,translation_code");

            $db->where("id", array($billingRes["state_id"]?:"", $deliveryRes["state_id"]?:""), "IN");
            $stateDisplay = $db->map("id")->get("state", null, "id,name,translation_code");

            $billingInfo['dialingArea'] = $countryCode[$billingRes['country_id']];
            $deliveryInfo['dialingArea'] = $countryCode[$deliveryRes['country_id']];
            $credentials['dialingArea'] = $countryCode[$member['country_id']];

            $billingInfo["districtID"] = $billingRes["district_id"];
            $billingInfo["district"] = $translations[$countyDisplay[$billingRes["district_id"]]["translation_code"]][$language] ? $translations[$countyDisplay[$billingRes["district_id"]]["translation_code"]][$language] : $countyDisplay[$billingRes["district_id"]]["name"];

            $billingInfo["subDistrictID"] = $billingRes["sub_district_id"];
            $billingInfo["subDistrict"] = $translations[$subCountyDisplay[$billingRes["sub_district_id"]]["translation_code"]][$language] ? $translations[$subCountyDisplay[$billingRes["sub_district_id"]]["translation_code"]][$language] : $subCountyDisplay[$billingRes["sub_district_id"]]["name"];

            $billingInfo["postCodeID"] = $billingRes["post_code_id"];
            $billingInfo["postCode"] = $translations[$zipCodeDisplay[$billingRes["post_code_id"]]["translation_code"]][$language] ? $translations[$zipCodeDisplay[$billingRes["post_code_id"]]["translation_code"]][$language] : $zipCodeDisplay[$billingRes["post_code_id"]]["name"];

            $billingInfo["cityID"] = $billingRes["city_id"];
            $billingInfo["city"] = $translations[$cityDisplay[$billingRes["city_id"]]["translation_code"]][$language] ? $translations[$cityDisplay[$billingRes["city_id"]]["translation_code"]][$language] : $cityDisplay[$billingRes["city_id"]]["name"];

            $billingInfo["stateID"] = $billingRes["state_id"];
            $billingInfo["state"] = $translations[$stateDisplay[$billingRes["state_id"]]["translation_code"]][$language] ? $translations[$stateDisplay[$billingRes["state_id"]]["translation_code"]][$language] : $stateDisplay[$billingRes["state_id"]]["name"];

            $deliveryInfo["districtID"] = $deliveryRes["district_id"];
            $deliveryInfo["district"] = $translations[$countyDisplay[$deliveryRes["district_id"]]["translation_code"]][$language] ? $translations[$countyDisplay[$deliveryRes["district_id"]]["translation_code"]][$language] : $countyDisplay[$deliveryRes["district_id"]]["name"];

            $deliveryInfo["subDistrictID"] = $deliveryRes["sub_district_id"];
            $deliveryInfo["sub_district"] = $translations[$subCountyDisplay[$deliveryRes["sub_district_id"]]["translation_code"]][$language] ? $translations[$subCountyDisplay[$deliveryRes["sub_district_id"]]["translation_code"]][$language] : $subCountyDisplay[$deliveryRes["sub_district_id"]]["name"];

            $deliveryInfo["post_code_id"] = $deliveryRes["post_code_id"];
            $deliveryInfo["post_code"] = $translations[$zipCodeDisplay[$deliveryRes["post_code_id"]]["translation_code"]][$language] ? $translations[$zipCodeDisplay[$deliveryRes["post_code_id"]]["translation_code"]][$language] : $zipCodeDisplay[$deliveryRes["post_code_id"]]["name"];

            $deliveryInfo["cityID"] = $deliveryRes["city_id"];
            $deliveryInfo["city"] = $translations[$cityDisplay[$deliveryRes["city_id"]]["translation_code"]][$language] ? $translations[$cityDisplay[$deliveryRes["city_id"]]["translation_code"]][$language] : $cityDisplay[$deliveryRes["city_id"]]["name"];

            $deliveryInfo["stateID"] = $deliveryRes["state_id"];
            $deliveryInfo["state"] = $translations[$stateDisplay[$deliveryRes["state_id"]]["translation_code"]][$language] ? $translations[$stateDisplay[$deliveryRes["state_id"]]["translation_code"]][$language] : $stateDisplay[$deliveryRes["state_id"]]["name"];

            $data['credentials'] = $credentials;
            $data['additionalInfo'] = $additionalInfo;
            $data['bankInfo'] = $bankInfo;
            $data['billingInfo'] = $billingInfo;
            $data['deliveryInfo'] = $deliveryInfo;

            $data['identityType'] = $member['identity_number']?'nric':'passport';

            $db->where('status', 'Active');
            $bankListRes = $db->get('mlm_bank', null, 'country_id, (SELECT name FROM country where id = country_id) as countryName, id, name, translation_code');
            foreach ($bankListRes as $bankListRow) {
                $bankList[$bankListRow['countryName']][] = $bankListRow;
            }
            $data['bankList'] = $bankList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function editMemberDetails($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            //get max and min full name length
            $maxFName       = Setting::$systemSetting['maxFullnameLength'];
            $minFName       = Setting::$systemSetting['minFullnameLength'];
            $maxUName       = Setting::$systemSetting['maxUsernameLength'];
            $minUName       = Setting::$systemSetting['minUsernameLength'];
            $maxPass        = Setting::$systemSetting['maxPasswordLength'];
            $minPass        = Setting::$systemSetting['minPasswordLength'];
            $maxTPass       = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass       = Setting::$systemSetting['minTransactionPasswordLength'];

            $martialStatusArr = array("single","married","widowed","divorced","separated");
            $genderArr = array("male", "female");

            $status         = trim($params['status']);
            $clientID       = trim($params['clientID']);
            $fullName       = trim($params['name']);
            $gender         = trim($params['gender']);
            $email          = trim($params['email']);
            $country        = trim($params['countryID']);
            $phone          = trim($params['phone']);
            $dateOfBirth    = trim($params['dob']);
            $identityType   = trim($params['identityType']);
            $identityNumber = trim($params['identityNumber']);
            $passport       = trim($params['passport']);

            // additional param
            $martialStatus = trim($params['martialStatus']);
            $childNumber   = trim($params['childNumber']);
            $childAge      = $params['childAge'];
            $taxNumber     = trim($params['taxNumber']);

            // bank param
            $bankInfo['clientID']     = trim($params['clientID']);
            $bankInfo['bankID']     = trim($params['bankID']);
            $bankInfo['accountNo']  = trim($params['accountNo']);
            $bankInfo['branch']     = trim($params['branch']);
            $bankInfo['bankCity']   = trim($params['bankCity']);
            $bankInfo['accountHolder'] = trim($params['accountHolder']);

            // billing addr param
            $billingInfo['addressType'] = "billing";
            $billingInfo['clientID']    = trim($params['clientID']);
            $billingInfo['fullname']  = trim($params['billingName']);
            // $billingInfo['first_name']  = trim($params['billingFirstName']);
            // $billingInfo['last_name']   = trim($params['billingLastName']);
            $billingInfo['dialingArea'] = trim($params['billingDialingArea']);
            $billingInfo['phone']       = trim($params['billingPhone']);
            $billingInfo['email']       = trim($params['billingEmail']);
            $billingInfo['address']     = trim($params['billingAddress']);
            $billingInfo['districtID'] = trim($params['billingDistrict']);
            $billingInfo['subDistrictID'] = trim($params['billingSubDistrict']);
            $billingInfo['cityID'] = trim($params['billingCity']);
            $billingInfo['postalCodeID'] = trim($params['billingPostalCode']);
            $billingInfo['stateID'] = trim($params['billingState']);
            $billingInfo['countryID']   = trim($params['billingCountryID']);

            $deliveryInfo['addressType'] = "delivery";
            $deliveryInfo['clientID']    = trim($params['clientID']);
            $deliveryInfo['fullname']  = trim($params['deliveryName']);
            // $deliveryInfo['first_name']  = trim($params['deliveryFirstName']);
            // $deliveryInfo['last_name']   = trim($params['deliveryLastName']);
            $deliveryInfo['dialingArea'] = trim($params['deliveryDialingArea']);
            $deliveryInfo['phone']       = trim($params['deliveryPhone']);
            $deliveryInfo['email']       = trim($params['deliveryEmail']);
            $deliveryInfo['address']     = trim($params['deliveryAddress']);
            $deliveryInfo['districtID'] = trim($params['deliveryDistrict']);
            $deliveryInfo['subDistrictID'] = trim($params['deliverySubDistrict']);
            $deliveryInfo['cityID'] = trim($params['deliveryCity']);
            $deliveryInfo['postalCodeID'] = trim($params['deliveryPostalCode']);
            $deliveryInfo['stateID'] = trim($params['deliveryState']);
            $deliveryInfo['countryID']   = trim($params['deliveryCountryID']);

            //checking client ID
            if(empty($clientID))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Client not found', 'data' => '');

            // //checking KYC validate
            // // Checking for email verification
            // $db->where('email_verified','1');
            // $db->where('client_id',$clientID);
            // $checkEmailValidate = $db->has('client_detail');

            // Checking for Success verification
            $newestID = $db->subQuery();
            $newestID->where('client_id', $clientID);
            $newestID->groupBy('doc_type');
            $newestID->get('mlm_kyc', null, 'MAX(id)');
            $db->where('id', $newestID, 'IN');
            $db->where('status', 'Approved');
            $getSuccessKYCRes = $db->map('doc_type')->get('mlm_kyc',null,'doc_type');

            // Get member original details
            $db->where('id', $clientID);
            $oriDetailRes = $db->getOne('client', 'name, email, country_id, identity_number, passport');
            // $db->where('client_id',$clientID);
            // $oriNPWPRes = $db->getOne('client_detail','tax_number');

            // ===== CREDENTIALS START =====
            // Validate fullName
            if(empty($fullName)) {
                $errorFieldArr[] = array(
                    'id'    => 'nameError',
                    'msg'   => $translations["E00296"][$language] /* Please insert full name */
                );
            } else {
                // if(in_array('ID Verification',$getSuccessKYCRes) && $fullName != $oriDetailRes['name']){
                //     $errorFieldArr[] = array(
                //         'id'    => 'nameError',
                //         'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
                //     );
                // } else 
                if (strlen($fullName) < $minFName || strlen($fullName) > $maxFName) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                    );
                }
            }

            // Validate Gender
            if(empty($gender) || (!in_array($gender, $genderArr))){
                $errorFieldArr[] = array(
                    'id' => 'genderError',
                    'msg' => $translations["E00766"][$language] /* Invalid gender */
                );
            } 

            // Valid email
            if (empty($email)) {
                $errorFieldArr[] = array(
                    'id' => 'emailError',
                    'msg' => $translations["E00318"][$language] /* Please fill in email */
                );
            } else {
                if ($email) {
                    // if($checkEmailValidate && $email != $oriDetailRes['email']){
                    //     $errorFieldArr[] = array(
                    //         'id' => 'emailError',
                    //         'msg' => $translations["E01107"][$language] /* Email had been validated. Unable to edit. */
                    //     );
                    // }else 
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorFieldArr[] = array(
                            'id' => 'emailError',
                            'msg' => $translations["E00319"][$language] /* Invalid email format. */
                        );
                    }else{
                        $db->where('email', $email);
                        $isOccupied = $db->getOne('client', 'id');
                        if ($isOccupied && $isOccupied['id'] != $clientID) {
                            $errorFieldArr[] = array(
                                'id'  => 'emailError',
                                'msg' => $translations['E00748'][$language] /* Email Already Used */
                            );
                        }
                    }
                }
            }

            // Validate country
            if(in_array('ID Verification',$getSuccessKYCRes) && $country != $oriDetailRes['country_id']){
                    $errorFieldArr[] = array(
                        'id'    => 'countryIDError',
                        'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
                    );
            }else if(!is_numeric($country) || empty($country)) {
                $errorFieldArr[] = array(
                    'id'  => "countryIDError",
                    'msg' => $translations['E00947'][$language]
                );
            }else{
                $db->where('id', $country);
                $dialingArea = $db->getValue('country', 'country_code');
                if(!$dialingArea){
                    $errorFieldArr[] = array(
                        'id'  => "countryIDError",
                        'msg' => $translations['E00947'][$language]
                    );
                }
            }

            // Validate phone
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
            }

            // Validate Date of Birth
            if (!is_numeric($dateOfBirth)){
                $errorFieldArr[] = array(
                    'id' => 'dateOfBirthError',
                    'msg' => $translations["E00156"][$language] /* Invalid date. */
                );
            }

            if($dateOfBirth){
                // check Date of Birth, min 18 years old
                $ts1 = date("Y-m-d", $dateOfBirth); 
                $tempDob = date("Y-m-d", strtotime('-18 year', strtotime("now")));
                $ts2 = $tempDob;
                if($ts1 > $ts2){
                    $errorFieldArr[] = array(
                        'id' => 'dateOfBirthError',
                        'msg' => $translations["E01053"][$language] /* You must be 18 and above to register. */
                    );
                }    
            }

            // Validate identity
            // if(in_array('ID Verification',$getSuccessKYCRes) && ($passport != $oriDetailRes['passport'] || $identityNumber != $oriDetailRes['identity_number'])){
            //     if($oriDetailRes['passport'] && $identityType != 'passport'){
            //         $errorFieldArr[] = array(
            //             'id'    => 'identityTypeError',
            //             'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //         );
            //     }else if($oriDetailRes['identity_number'] && $identityType != 'nric'){
            //         $errorFieldArr[] = array(
            //             'id'    => 'identityTypeError',
            //             'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //         );
            //     }
            //     $errorFieldArr[] = array(
            //         'id'    => 'identityNumberError',
            //         'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //     );
            // } else {
            if($identityType == "nric"){
                if(empty($identityNumber)){
                    $errorFieldArr[] = array(
                        'id' => 'identityNumberError',
                        'msg' => $translations["E01040"][$language] /* Please Insert Identity Number */
                    );
                }
            } else if ($identityType == "passport"){
                if(empty($passport)){
                    $errorFieldArr[] = array(
                        'id' => 'identityNumberError',
                        'msg' => $translations["E01042"][$language] /* Please Insert Passport Number */
                    );
                }
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00218"][$language], 'data' => "");
            }
            
            // ===== CREDENTIALS END =====

            // ===== ADDITIONAL INFO START =====

            // Validate Marital status
            if(empty($martialStatus) || (!in_array($martialStatus, $martialStatusArr))){
                $errorFieldArr[] = array(
                    'id' => 'martialStatusError',
                    'msg' => $translations["E01037"][$language] /* Please Select Marital Status */
                );
            }

            if(!is_numeric($childNumber) || $childNumber < 0){
                $errorFieldArr[] = array(
                    'id' => 'childNumberError',
                    'msg' => $translations["E01038"][$language] /* Please Insert Child Number */
                );
            }

            if($childNumber > 0){
                $childAgeOption = explode('#', Setting::$systemSetting['childAgeOption']);
                // childAge
                if(!is_array($childAge)){
                    $errorFieldArr[] = array(
                        'id' => 'childAgeError',
                        'msg' => $translations["E01111"][$language] /* Invalid Age. */
                    );
                }else if(count($childAge) != $childNumber){
                    $errorFieldArr[] = array(
                        'id' => 'childAgeError',
                        'msg' => $translations["E01112"][$language] /* Total count of age not match. */
                    );
                }else{
                    foreach ($childAge as $childAgeRow) {
                        if(!$childAgeOption[$childAgeRow]){
                            $errorFieldArr[] = array(
                                'id' => 'childAgeError',
                                'msg' => $translations["E01111"][$language] /* Invalid Age. */
                            );
                            break;
                        }
                    }
                }
            }

            // if(empty($taxNumber)){
            //     // $errorFieldArr[] = array(
            //     //     'id' => 'taxNumberError',
            //     //     'msg' => $translations["E01039"][$language] /* Please Insert Tax Number */
            //     // );
            // }else if(in_array('NPWP Verification', $getSuccessKYCRes) && $taxNumber != $oriNPWPRes['tax_number']){
            //     $errorFieldArr[] = array(
            //         'id'    => 'taxNumberError',
            //         'msg'   => $translations["E01109"][$language] /* NPWP had been validated. Unable to edit */
            //     );
            // }
            // ===== ADDITIONAL INFO END =====

            // ===== BANK INFO START =====
            $db->where('status', 'Active');
            $db->where('client_id', $clientID);
            $db->orderBy('created_at', 'DESC');
            $curBankRes = $db->getOne('mlm_client_bank', 'bank_id, account_no, branch, bank_city, account_holder');

            // if(in_array('Bank Account Cover', $getSuccessKYCRes)){
            //     if($bankInfo['bankID'] != $curBankRes['bank_id']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankIDError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['accountNo'] != $curBankRes['account_no']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankAccError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['branch'] != $curBankRes['branch']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankBranchError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['bankCity'] != $curBankRes['bank_city']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankCityError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['accountHolder'] != $curBankRes['account_holder']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankAccHolderError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            // }else 
            if($curBankRes &&
                ( $bankInfo['bankID'] != $curBankRes['bank_id']
                    || $bankInfo['accountNo'] != $curBankRes['account_no']
                    || $bankInfo['branch'] != $curBankRes['branch']
                    || $bankInfo['bankCity'] != $curBankRes['bank_city']
                    || $bankInfo['accountHolder'] != $curBankRes['account_holder'] )
            ){
                // add bank flag
                $addBankFlag = true;
            }else if(!$curBankRes && $bankInfo['bankID']){
                $addBankFlag = true;
            }

            if($addBankFlag){
                $bankValidation = Client::addBankAccountDetailVerification($bankInfo);
                if(strtolower($bankValidation['status']) != 'ok'){
                    return $bankValidation;
                }
            }
            // ===== BANK INFO END =====

            // ===== BILLING INFO START =====
            $db->where('disabled', '0');
            $db->where('address_type', 'billing');
            $db->where('client_id', $clientID);
            $curBillingRes = $db->getOne('address', 'id, name, email, phone, address, state_id, district_id, sub_district_id, remarks, city_id, post_code_id, country_id');
            // $billingInfo['dialingArea'] != $curBillingRes['']

            if($curBillingRes &&
                ( $billingInfo['fullname'] != $curBillingRes['name']
                    || $billingInfo['phone'] != $curBillingRes['phone']
                    || $billingInfo['email'] != $curBillingRes['email']
                    || $billingInfo['address'] != $curBillingRes['address']
                    || $billingInfo['districtID'] != $curBillingRes['district_id']
                    || $billingInfo['subDistrictID'] != $curBillingRes['sub_district_id']
                    || $billingInfo['cityID'] != $curBillingRes['city_id']
                    || $billingInfo['postalCodeID'] != $curBillingRes['post_code_id']
                    || $billingInfo['stateID'] != $curBillingRes['state_id']
                    || $billingInfo['countryID'] != $curBillingRes['country_id'] )
            ){
                // add billing address flag
                $addBillingAddrFlag = true;

            }else if(!$curBillingRes && $billingInfo['fullname']){
                $addBillingAddrFlag = true;
            }

            if($addBillingAddrFlag){
                $errorFieldArrBilling = Inventory::verifyAddress($billingInfo);
                if($errorFieldArrBilling){
                    foreach ($errorFieldArrBilling as $key => &$value) {
                        $value['id'] = $value['id']."Billing";
                    }
                    $data['field'] = $errorFieldArrBilling;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
                }
            }
            // ===== BILLING INFO END =====

            // ===== DELIVERY INFO START =====
            $db->where('disabled', '0');
            $db->where('address_type', 'delivery');
            $db->where('client_id', $clientID);
            $db->orderBy('created_at', 'DESC');
            $curDeliveryRes = $db->getOne('address', 'id, name, email, phone, address, state_id, district_id, sub_district_id, remarks, city_id, post_code_id, country_id');
            // $deliveryInfo['dialingArea'] != $curDeliveryRes['']

            if($curDeliveryRes &&
                ( $deliveryInfo['fullname'] != $curDeliveryRes['name']
                    || $deliveryInfo['phone'] != $curDeliveryRes['phone']
                    || $deliveryInfo['email'] != $curDeliveryRes['email']
                    || $deliveryInfo['address'] != $curDeliveryRes['address']
                    || $deliveryInfo['districtID'] != $curDeliveryRes['district_id']
                    || $deliveryInfo['subDistrictID'] != $curDeliveryRes['sub_district_id']
                    || $deliveryInfo['cityID'] != $curDeliveryRes['city_id']
                    || $deliveryInfo['postalCodeID'] != $curDeliveryRes['post_code_id']
                    || $deliveryInfo['stateID'] != $curDeliveryRes['state_id']
                    || $deliveryInfo['countryID'] != $curDeliveryRes['country_id'] )
            ){
                // add billing address flag
                $addDeliveryAddrFlag = true;

            }else if(!$curDeliveryRes && $deliveryInfo['fullname']){
                $addDeliveryAddrFlag = true;
            }

            if($addDeliveryAddrFlag){
                $errorFieldArrDelivery = Inventory::verifyAddress($deliveryInfo);
                if($errorFieldArrDelivery){
                    foreach ($errorFieldArrDelivery as $key => &$value) {
                        $value['id'] = $value['id']."Delivery";
                    }
                    $data['field'] = $errorFieldArrDelivery;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
                }
            }
            // ===== DELIVERY INFO END =====

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            
            // ===== VERIFICATION END =====

            // get old data
            $db->where("id", $clientID);
            $clientOldData = $db->getOne("client", "name, email, phone, address, country_id, disabled, suspended, freezed, `terminated`,turnOffPopUpMemo");

            if (Cash::$creatorType == "Admin") {

                $updateData["name"] = $fullName;
                $updateData["email"] = $email;
                $updateData["passport"] = $passport;
                $updateData["identity_number"] = $identityNumber;
                $updateData["dial_code"] = $dialingArea;
                $updateData["phone"] = $phone;
                $updateData["country_id"] = $country;

               if($status == "active"){
                    $updateData["activated"] = 1;
                    $updateData["suspended"] = 0;
                    $updateData["freezed"] = 0;
                    $updateData["terminated"]  = 0;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;

                }elseif($status == "suspended"){
                    $updateData["suspended"] = 1;
                    $updateData["freezed"] = 0;
                    $updateData["terminated"]  = 0;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;

                }elseif($status == "freezed"){
                    $updateData["suspended"] = 0;
                    $updateData["freezed"] = 1;
                    $updateData["terminated"]  = 0;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;

                }elseif($status == "terminated"){
                    $updateData["suspended"] = 0;
                    $updateData["freezed"] = 0;
                    $updateData["terminated"]  = 1;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;
                    $isTerminated = 1;
                }

                $db->where('id', $clientID);
                $updateResult = $db->update('client', $updateData);
            }

            //Insert Terminate time for Rerun bonus module
            $db->where('client_id',$clientID);
            $db->where('name','terminatedAt');
            $terminateStgID = $db->getValue('client_setting','id');
            switch ($isTerminated) {
                case '1':
                    if(!$terminateStgID){
                        unset($insertData);
                        $insertData = array(
                            "name" => 'terminatedAt',
                            "value"=> $dateTime,
                            "client_id"=>$clientID
                        );
                        $db->insert('client_setting',$insertData);
                    }
                    break;
                
                case '0':
                    if($terminateStgID){
                        $db->where('id',$terminateStgID);
                        $db->delete('client_setting');
                    }
                    break;
            }

            $db->where('id', $clientID);
            $clientUsername = $db->getValue("client", "username");
            $userData = array("user" => $clientUsername);

            $db->where("client_id", $clientID);
            $clientOldData2 = $db->getOne("client_detail", "gender, martial_status, num_of_child, tax_number");

            if (Cash::$creatorType == "Admin") {

                unset($updateData2);
                $updateData2["gender"] = $gender;
                $updateData2["martial_status"] = $martialStatus;
                $updateData2["num_of_child"] = $childNumber;
                $updateData2["child_age"] = $childNumber>0?implode("#", $childAge):'';
                $updateData2["tax_number"] = $taxNumber;

                $db->where('client_id', $clientID);
                $updateResult = $db->update('client_detail', $updateData2);
            }

            // insert activity log
            $changedDataArray = array_diff_assoc($updateData, $clientOldData); // get what is changed
            $changedDataArray2 = array_diff_assoc($updateData2, $clientOldData2); // get what is changed
            if(count($changedDataArray) > 0 || count($changedDataArray2) > 0){
                $activityData = array_merge($userData, $changedDataArray, $changedDataArray2);
                $activityRes = Activity::insertActivity('Edit Member Details', 'T00015', 'L00015', $activityData, $clientID);
                // Failed to insert activity
                if(!$activityRes)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => "");
            }

            if($addBankFlag){
                $bankValidation = Client::addBankAccountDetail($bankInfo);
            }

            if($addBillingAddrFlag){
                if($curBillingRes) {
                    $billingInfo['id'] = $curBillingRes['id'];
                    $errorFieldArrBilling = Inventory::manageAddress($billingInfo, 'edit');
                }
                else {
                    $errorFieldArrBilling = Inventory::manageAddress($billingInfo, 'add');
                }
            }

            if($addDeliveryAddrFlag){
                if($curDeliveryRes) {
                    $deliveryInfo['id'] = $curDeliveryRes['id'];
                    $errorFieldArrBilling = Inventory::manageAddress($deliveryInfo, 'edit');
                }
                else {
                    $errorFieldArrBilling = Inventory::manageAddress($deliveryInfo, 'add');
                }
            }

            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M02477"][$language] /* Successfully Updated" */, 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => "Update failed" /*  $translations["E00131"][$language]*/, 'data' =>"");
        }

        public function changeMemberPassword($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $memberID     = $params['clientID'];
            $newPassword  = $params['newPassword'];
            $confirmNewPassword  = $params['confirmNewPassword'];
            $passwordCode = $params['passwordType'];

            $minPass      = Setting::$systemSetting['minPasswordLength'];
            // Get password encryption type
            $passwordEncryption  = Setting::getMemberPasswordEncryption();

            if (empty($memberID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00132"][$language] /* Member not found */, 'data'=> "");

            // checking client
            $db->where('id', $memberID);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language] /* Member not found */, 'data' => "");

            $memberId      = $memberID;
            $username    = $clientDetails;

            if (empty($passwordCode)) {
                $errorFieldArr[] = array(
                                            'id'  => 'passwordTypeError',
                                            'msg' => $translations["E00134"][$language] /* Please select a password type */
                                        );
            } else {
                if ($passwordCode == 1) {
                    $passwordType  = "password";
                } else if ($passwordCode == 2) {
                    $passwordType  = "transaction_password";
                } else {
                    $errorFieldArr[] = array(
                                                'id'  => 'passwordTypeError',
                                                'msg' => $translations["E00135"][$language] /* Invalid password type */
                                            );
                }
            }
            // get error msg type
            if ($passwordType == "password") {
                $idName        = 'Password';
                $msgFieldB     = 'Password';
                $msgFieldS     = 'password';
                $titleCode     = 'T00013';
                $activityCode  = 'L00013';
                $transferType  = 'Reset Password';
                $maxLength     = $maxPass;
                $minLength     = $minPass;
            } else if ($passwordType == "transaction_password") {
                $idName        = 'TPassword';
                $msgFieldB     = 'Transaction password';
                $msgFieldS     = 'transaction password';
                $titleCode     = 'T00014';
                $activityCode  = 'L00014';
                $transferType  = 'Reset Transaction Password';
                $maxLength     = $maxTPass;
                $minLength     = $minTPass;
            }
            if (empty($newPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' =>  $translations["E00136"][$language] /* Please enter new */ . " " . $msgFieldS . "."
                                        );
            } elseif (strlen($newPassword)<$minPass) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $msgFieldB . " " . $translations["E00137"][$language] /* cannot be less than */ . " " . $minLength . " " . $translations["E00138"][$language] /* or more than */ . " " . $maxLength . "."
                                        );
            }
            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = Setting::getEncryptedPassword($newPassword);
            $db->where('id', $memberId);
            $result = $db->getOne('client', $passwordType);
            // if (empty($result[$passwordType])) 
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00139"][$language] /* Member not found */, 'data'=> "");

            if($result){
                if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(password_verify($newPassword, $result[$passwordType])) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => $translations["E00140"][$language] /* Please enter different */ . " $msgFieldS."
                                                );
                    }
                } else {
                    if ($newEncryptedPassword == $result[$passwordType]) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => $translations["E00140"][$language] /* Please enter different */ . " $msgFieldS."
                                                );
                    }
                }
            }
            
            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data'=>$data);

            $updateData = array($passwordType => $newEncryptedPassword);
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
            if(!$updateResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00143"][$language] /* Update failed */, 'data' => "");

            // insert activity log
            $activityData = array('user' => $username);

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $memberId);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRankMaintain($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;


            $tableName      = "mlm_bonus";
            $column         = array(

                "mlm_bonus.name AS mlm_bonus_name",
                "mlm_bonus.language_code AS languageCode"
            );

            $db->where("mlm_bonus.allow_rank_maintain", "1");
            $db->where("mlm_bonus.disabled", "0");
            $result = $db->get($tableName, NULL, $column);

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00107"][$language] /* No Results Found. */, 'data'=>"");

            foreach ($result as $value) {
                $bonusSelection[$value['mlm_bonus_name']] = $translations[$value['languageCode']][$language];
                $bonusRankAry[] = $value['mlm_bonus_name']."Percentage";
            }
            $data["bonusRankAry"] = $bonusRankAry;
            
            $clientID = $params['clientID'];
            if ($clientID) {
                // Get member details
                $db->where('id',$clientID);
                $memberDetails = $db->getOne('client','id AS clientID,member_id,username,name, email');

                //get Rank setting
                $column = array(
                    'rank_id',
                    'name',
                    'value',
                    '(SELECT name From rank where id = rank_id) AS rank_name',
                    '(SELECT translation_code From rank where id = rank_id) AS rank_lang_code',
                );
                $bonusRankSettingRes = $db->get('rank_setting',null,$column);
                foreach ($bonusRankSettingRes as $bonusRankSettingRes => $bonusRankSettingValue) {

                    unset($rankData);
                    $rankData['rank_id'] = $bonusRankSettingValue['rank_id'];
                    $rankData['rank_name'] = $bonusRankSettingValue['rank_name'];
                    $rankData['value'] = $bonusRankSettingValue['value'];
                    $rankData['rank_display'] = $translations[$bonusRankSettingValue['rank_lang_code']][$language];

                    $rankDisplay[$bonusRankSettingValue['rank_id']] = $translations[$bonusRankSettingValue['rank_lang_code']][$language];

                    $rankSettingData[$bonusRankSettingValue['name']][$bonusRankSettingValue['rank_id']] = $rankData;
                }

                $bonusNameRes = $db->get('mlm_bonus',null,'name'); 
                foreach ($bonusNameRes as $bonusNameKey => $bonusNameValue) {

                    $settingName = $bonusNameValue['name'].'Percentage';
                    if($rankSettingData[$settingName]){
                        $rankSettingAry[$settingName] = $rankSettingData[$settingName];

                    }
                }

                foreach ($bonusRankAry as $bonusName) {
                    $memberDetails["System"][$bonusName] = 0;
                    $memberDetails["Admin"][$bonusName] = 0;
                }

                $db->where('client_id',$clientID);
                $db->where('name',$bonusRankAry,'IN');
                $db->orderBy('created_at','ASC');
                $bonusPercentage = $db->get('client_rank',null,'name,value,type,updated_at,rank_id');

                foreach ($bonusPercentage as $bonusPercentageKey => $bonusPercentageValue) {

                    $memberDetails[$bonusPercentageValue['type']][$bonusPercentageValue['name']] = $rankDisplay[$bonusPercentageValue['rank_id']];//$bonusPercentageValue['value'];
                    $memberDetails['updated_at'] = $bonusPercentageValue['updated_at'] > 0 ? $bonusPercentageValue['updated_at'] : "-";
                }

                foreach ($bonusSelection as $key => $value) {
                    $clientBonusPercentage[$key]["display"] = $value;
                    $clientBonusPercentage[$key]["percentage"] = $bonusPercentage[$key] ? $bonusPercentage[$key] : 0;
                }
            }

            $data['memberDetails'] = $memberDetails;
            $data['clientBonusPercentage'] = $clientBonusPercentage;
            $data['rankSettingAry'] = $rankSettingAry;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function updateRankMaintain($params,$userID,$site) {

            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $bonusName   = $params['bonusName'];
            $rank_id    = $params['rank_id'];
            $clientID    = trim($params['clientID']);


            if (empty($clientID) || !is_numeric($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data'=>"");
            
            // Check client
            $db->where('id', $clientID);
            $username = $db->getValue('client', 'username');
            if(!$username)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00146"][$language] /* Client not found. */, 'data'=>"");

            if (empty($bonusName) || empty($rank_id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00147"][$language] /* Invalid rank. */, 'data'=> "");

            if($bonusName != 'maxCap'){
                $db->where('name',$bonusName);
                $bonusNameLangCode = $db->getValue('mlm_bonus','language_code');
                $bonusNameDisplay = $translations[$bonusNameLangCode][$language];


                $bonusName = $bonusName."Percentage";
            }else{

                $bonusNameDisplay = $translations['A00549'][$language];
            }
            

            $column = array(
                "rank_id",
                "value",
                "(SELECT type FROM rank where id = rank_id) AS rank_type"
            );
            $db->where('name', $bonusName);
            $db->where('rank_id', $rank_id);
            $rank_setting_res = $db->get("rank_setting", 1,$column);
            foreach ($rank_setting_res as $rank_setting_key => $rank_setting_value) {
                $percentage = $rank_setting_value['value'];
                $rank_type = $rank_setting_value['rank_type'];
            }

            $isSet = '';
            $db->where('client_id',$clientID);
            $db->where('name',$bonusName);
            $db->where('type',$site);
            $db->orderBy('created_at', 'DESC');
            $copyDb = $db->copy();
            $isSetBeforePercentage = $db->getValue('client_rank','value');
/*
            if ($isSetBeforePercentage >= $percentage) {
                
                return array('status'=>'error','code'=>2,'statusMsg'=>'Failed to update rank.','data'=>'');

            } else {*/
                $insertData = array(
                    'client_id' => $clientID,
                    'name' => $bonusName,
                    'value' => $percentage,
                    'rank_type'  => $rank_type,
                    'rank_id'    => $rank_id,
                    'created_at' => $db->now(),
                    'updated_at' => $db->now(),
                    'updated_by' => $userID,
                    'type' => $site
                );

                $isSet = $db->insert('client_rank',$insertData);
            /*}*/

            if (!$isSet) {
                return array('status'=>'error','code'=>2,'statusMsg'=>'Failed to update rank.','data'=>'');
            }

            // insert activity log
            $titleCode      = 'T00008';
            $activityCode   = 'L00025';
            $transferType   = 'Change Rank';
            $activityData   = array(
                'user' => $username,
                'bonusName'  => $bonusNameDisplay,
                'old'  => $isSetBeforePercentage?$isSetBeforePercentage:0,
                'new'  => $percentage
            );

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00109"][$language] /* Successfully update clienk rank. */, 'data'=> '');
        }

        public function getPortfolioList($params, $site, $userID, $specialFilterArray = 0) {
            $db            = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData    = $params['searchData'];
            $pageNumber    = trim($params['pageNumber']) ? trim($params['pageNumber']) : 1;
            $seeAll        = trim($params['seeAll']);
            $currentTime   = time();
            $dateTime      = date("Y-m-d H:i:s");
            $limit         = $seeAll == 1 ? NULL : General::getLimit($pageNumber);

            $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

            $decimalPlaces  = Setting::getInternalDecimalFormat();
            $adminLeaderAry = Setting::getAdminLeaderAry();

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

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

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
                        case 'portfolioType':
                            $db->where("portfolio_type", $dataValue);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" .  $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;
                            
                        case 'username':
                            $sq = $db->subQuery();
                            if ($usernameSearchType == "like") $sq->where("username", '%'.$dataValue.'%', 'LIKE');
                            else $sq->where("username", $dataValue);

                            $sq->get("client", NULL, "id");
                            $db->where('client_id', $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;
                        
                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;
                            
                        case 'entryDate':
                            // Set db column here
                            $columnName = 'date(created_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'refNo':
                            $db->where('reference_no', $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            if($site == 'Member') $db->where("client_id", $userID);

            $copyDB = $db->copy();
            $db->orderBy('id', 'DESC');
            $portfolioRes = $db->get('mlm_client_portfolio', $limit, 'id, client_id, product_price, status, product_id, bonus_value, batch_id');

            if(!$portfolioRes){
                if(!$data) $data = "";
                return array("status" => 'ok', "code" => 0, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => $data);
            }

            foreach ($portfolioRes as $portfolioResult) {
                $clientIDAry[$portfolioResult['client_id']]   = $portfolioResult['client_id'];
                $productIDAry[$portfolioResult['product_id']] = $portfolioResult['product_id'];
                $batchIDAry[$portfolioResult['batch_id']] = $portfolioResult['batch_id'];
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry, "IN");
                $clientData = $db->map('id')-> get('client', NULL, 'id, username, member_id, email');
            }

            if($productIDAry){
                $db->where('module_id', $productIDAry, "IN");
                $db->where('module', 'mlm_product');
                $db->where('type', 'name');
                $db->where('language', $language);
                $prodData = $db->map('module_id')-> get('inv_language', NULL, 'module_id, content');
            }

            if($batchIDAry){
                $db->where('batch_id', $batchIDAry, "IN");
                $batchIDData = $db->map('batch_id')-> get('inv_order', NULL, 'batch_id, reference_number');
            }

            foreach ($portfolioRes as $portfolioResult) {

                $portfolio['id']            = $portfolioResult['id'];
                $portfolio['username']      = $clientData[$portfolioResult['client_id']]['username'];
                $portfolio['memberID']      = $clientData[$portfolioResult['client_id']]['member_id'];
                $portfolio['email']         = $clientData[$portfolioResult['client_id']]['email'];
                $portfolio['packageName']   = $prodData[$portfolioResult['product_id']];
                $portfolio['referenceNo']   = $batchIDData[$portfolioResult['batch_id']];
                $portfolio['productPrice']  = $portfolioResult['product_price'];
                $portfolio['bonusValue']    = $portfolioResult['bonus_value'];
                $portfolio['status']        = General::getTranslationByName($portfolioResult['status']);

                $portfolioList[] = $portfolio;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully exported", 'data' => $data);
            }

            $totalRecordRes = $copyDB->getValue('mlm_client_portfolio','count(*)', null);
            $totalRecord    = $copyDB->count;
            $data['portfolioList']  = $portfolioList;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }
            $data['seeAll']         = $seeAll;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /* Successfully retrieved */, 'data' => $data);
        }

        public function getPortfolioListOld($params, $site, $userID, $specialFilterArray = 0) {
            $db            = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;

            $productID     = trim($params['productID']);
            $searchData    = $params['searchData'];
            $pageNumber    = trim($params['pageNumber']) ? trim($params['pageNumber']) : 1;
            $seeAll        = trim($params['seeAll']);
            $currentTime   = time();

            $limit         = $seeAll == 1 ? NULL : General::getLimit($pageNumber);

            $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

            $decimalPlaces  = Setting::getInternalDecimalFormat();
            $adminLeaderAry = Setting::getAdminLeaderAry();

            $firstGameTime   = Setting::$systemSetting['firstGameTime'];  /* 11:30:00 */
            $secondGameTime  = Setting::$systemSetting['secondGameTime']; /* 16:30:00 */

            $firstGameDateTime  = date('Y-m-d H:i:s', strtotime($firstGameTime));  /*Y-m-d 11:30:00*/
            $secondGameDateTime = date('Y-m-d H:i:s', strtotime($secondGameTime)); /*Y-m-d 16:30:00*/

            if($site == 'Member'){
                $db->where('name', array('firstGameTime', 'secondGameTime'), 'IN');
                $gameTimeSetting = $db->get('system_settings');

                foreach ($gameTimeSetting as $timeSetting) {
                    if ($timeSetting['name'] == 'firstGameTime') {
                        $gameOneStartTime = date('Y-m-d H:i:s', strtotime($timeSetting['value']));
                        $gameOneEndTime   = date('Y-m-d H:i:s', strtotime($timeSetting['reference']));
                    }

                    if ($timeSetting['name'] == 'secondGameTime') {
                        $gameTwoStartTime = date('Y-m-d H:i:s', strtotime($timeSetting['value']));
                        $gameTwoEndTime   = date('Y-m-d H:i:s', strtotime($timeSetting['reference']));
                    }
                }

                if((strtotime($gameOneStartTime) <= $currentTime && strtotime($gameOneEndTime) > $currentTime) || (strtotime($gameTwoStartTime) <= $currentTime && strtotime($gameTwoEndTime) > $currentTime)) {

                    // In Game
                    if(strtotime($gameOneStartTime) <= $currentTime && strtotime($gameOneEndTime) > $currentTime){
                        $remaining['startTime'] = 0;
                        $remaining['drawTime']  = strtotime($gameOneEndTime) - $currentTime;
                    } else if(strtotime($gameTwoStartTime) <= $currentTime && strtotime($gameTwoEndTime) > $currentTime){
                        $remaining['startTime'] = 0;
                        $remaining['drawTime']  = strtotime($gameTwoEndTime) - $currentTime;
                    } 
                } else{
                    // Not In Game
                    if(strtotime($gameOneStartTime) >= $currentTime){
                        $remaining['startTime'] = strtotime($gameOneStartTime) - $currentTime;
                        $remaining['drawTime']  = 0;
                    } elseif(strtotime($gameTwoStartTime) >= $currentTime) {
                        $remaining['startTime'] = strtotime($gameTwoStartTime) - $currentTime;
                        $remaining['drawTime']  = 0;
                    } else {
                        $remaining['startTime'] = strtotime($gameOneStartTime. "+ 1 day") - $currentTime;
                        $remaining['drawTime']  = 0;
                    }
                }
                $data['remaining'] = $remaining;
            }

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

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }


                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = strtolower(trim($v['dataType']));
                        
                    switch($dataName) {
                        case 'portfolioType':
                            $db->where("portfolio_type", $dataValue);
                            break;

                        case 'type':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->getOne("mlm_product", "id");
                            $db->where("product_id", $sq);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" .  $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;
                            
                        case 'username':
                            $sq = $db->subQuery();
                            if ($usernameSearchType == "like") $sq->where("username", '%'.$dataValue.'%', 'LIKE');
                            else $sq->where("username", $dataValue);

                            $sq->get("client", NULL, "id");
                            $db->where('client_id', $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;
                        
                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;
                            
                        case 'entryDate':
                            // Set db column here
                            $columnName = 'date(created_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                            
                        case 'maturityDate':
                            // Set db column here
                            $columnName = 'date(expire_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00162"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00163"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00164"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'refNo':
                            $db->where('reference_no', $dataValue);
                            break;

                        case 'clientId':
                            $db->where('client_id', $$dataValue);
                            break;

                        case 'productType':
                            if ($dataValue) $db->where('product_id',$dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            if ($specialFilterArray){
                foreach ($specialFilterArray as $columnName => $columnField) {
                    switch ($columnName) {
                        case 'productName':
                            $sq = $db->subQuery();
                            $sq->where("name", $columnField);
                            $sq->getOne("mlm_product", "id");
                            $db->where("product_id", $sq);
                            break;
                        
                        default:
                            $db->where($columnName,$columnField);
                            break;
                    }
                    
                }
            }

            if($productID) $db->where("product_id", $productID);

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            if($site == 'Member') $db->where("client_id", $userID);

            $column = array(
                "id", "client_id", "created_at", "product_price", "expire_at",
                "status", "product_id", "bonus_value as amount", "belong_id", "creator_id",
                "(SELECT `sponsor_id` FROM `client` WHERE `client`.`id` = `mlm_client_portfolio`.`client_id`) AS sponsor_id",
            );

            $copyDB = $db->copy();
            $db->orderBy('id', 'DESC');
            $portfolioRes = $db->get('mlm_client_portfolio', $limit, $column);

            if(!$portfolioRes)
                return array("status" => 'ok', "code" => 0, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => '');
            
            foreach ($portfolioRes as $value) {
                $clientIDAry[$value['client_id']]   = $value['client_id'];
                $clientIDAry[$value['sponsor_id']]  = $value['sponsor_id'];
                $clientIDAry[$value['creator_id']]  = $value['creator_id'];
                $productIDAry[$value['product_id']] = $value['product_id'];
                $belongIDAry[$value['belong_id']]   = $value['belong_id'];

                if($site == 'Member') $portfolioIDAry[$value['id']]   = $value['id'];
            }

            if($clientIDAry){
                $db->where('type', 'Client');
                $db->where('id', $clientIDAry, 'IN');
                $clientData = $db->map('id')->get('client', NULL, 'id, username');

                $db->where('name', 'enabledAutoJoin');
                $db->where('type', 'joinGameSetting');
                $db->where('client_id', $clientIDAry, 'IN');
                $autoJoinGameData = $db->map('client_id')->get('client_setting', NULL, 'client_id, value');
            }

            if($currentTime >= strtotime($firstGameDateTime)){
                $previousGameTime = $firstGameDateTime;
            }else if($currentTime >= strtotime($secondGameDateTime)){
                $previousGameTime = $secondGameDateTime;
            }else{
                $pastDayGameDateTime = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($secondGameTime))); /*Y-m-d(-1) 16:30:00*/
                $previousGameTime = $pastDayGameDateTime;
            }

            if($previousGameTime){
                $db->where('start_date', $previousGameTime);
                $previousGameIDAry = $db->getValue('game', 'id', NULL);
            }

            if($portfolioIDAry){
                if($previousGameIDAry) $db->where('game_id', $previousGameIDAry, 'IN');
                $db->where('portfolio_id', $portfolioIDAry, 'IN');
                $getGameDetail = $db->map('portfolio_id')->get('game_detail', NULL, 'portfolio_id, id, game_id');

                if($getGameDetail){
                    foreach ($getGameDetail as $gameRow) {
                        $gameIDAry[$gameRow['game_id']] = $gameRow['game_id'];
                    }
                }

                if($gameIDAry){
                    $db->where('id', $gameIDAry, 'IN');
                    // $db->where('status', array('await', 'closed'), 'IN');
                    $db->orderBy('end_date', 'DESC');
                    $db->orderBy('id', 'DESC');
                    $db->groupBy('product_id');
                    $gameData = $db->map('id')->get('game', NULL, 'id, product_id, created_at as startTime, end_date as endTime, status');
                }
            }

            if($productIDAry){
                $db->where('id', $productIDAry, 'IN');
                $productData = $db->map('id')->get('mlm_product', NULL, 'id, translation_code');
            }

            unset($value);
            foreach ($portfolioRes as $value) {
                $value['bonusEarned']     = 0;
                $value['entryDate']       = $value['created_at'];
                $value['portfolioID']     = $value['id'];
                $value['username']        = $clientData[$value['client_id']]  ? $clientData[$value['client_id']]  : '-';
                $value['sponsorUsername'] = $clientData[$value['sponsor_id']] ? $clientData[$value['sponsor_id']] : '-';
                $value['creatorUsername'] = $clientData[$value['creator_id']] ? $clientData[$value['creator_id']] : '-';

                $productDisplay          = $translations[$productData[$value['product_id']]][$language];
                $value['packageDisplay'] = $productDisplay ? $productDisplay : '-';

                $value['statusDisplay'] = General::getTranslationByName($value['status']);

                $value['maturityDate'] = $value["expire_at"] > 0 ? date("Y-m-d",strtotime($value["expire_at"])) : "-";

                if($site == 'Member'){
                    $value['hasJoined']         = '0';
                    $value['gameStartTime']     = '-';
                    $value['gameCloseTime']     = '-';
                    $value['gameStatus']        = '-';
                    $value['gameStatusDisplay'] = '-';
                    $value['isMatured']         = '0';
                    $value['currentGameID']     = '-';
                    $value['disabledAutoJoin']  = $autoJoinGameData[$value['client_id']] == '1' ? '0' : '1';

                    /*Initially the common varable for both portfolio status*/
                    switch ($value['status']) {
                        case 'Active':
                            if($getGameDetail[$value['id']]){
                                $value['hasJoined']     = '1';
                                $value['currentGameID'] = $getGameDetail[$value['id']]['game_id'];
                            }

                            $value['gameStatus']    = $gameData[$getGameDetail[$value['id']]['game_id']]['status'];
                            $value['gameStatus']    = $value['gameStatus'] ? $value['gameStatus'] : '-';

                            $gameStatusDisplay          = General::getTranslationByName($value['gameStatus']);
                            $gameStatusDisplay          = $gameStatusDisplay ? $gameStatusDisplay : '-';
                            $value['gameStatusDisplay'] = $gameStatusDisplay ? $gameStatusDisplay : $value['gameStatus'];

                            $value['gameStartTime'] = $remaining['startTime'] ? $remaining['startTime'] : '-';
                            $value['gameCloseTime'] = $remaining['drawTime'] ? $remaining['drawTime'] : '-';

                            // return;
                            // if(in_array($value['gameStatus'], array('await', 'closed'))){
                            //     $gameEndTime = $gameData[$getGameDetail[$value['id']]['game_id']]['endTime'];

                            //     $value['gameCloseTime'] = strtotime($gameEndTime) - $currentTime;
                            //     $value['gameCloseTime'] = $value['gameCloseTime'] <= 0 ? '-' : $value['gameCloseTime'];
                            // }else{
                            //     if($currentTime < strtotime($firstGameDateTime)){
                            //         $value['gameStartTime'] = strtotime($firstGameDateTime);
                            //     }else if($currentTime < strtotime($secondGameDateTime)){
                            //         $value['gameStartTime'] = strtotime($secondGameDateTime);
                            //     }else{
                            //         $value['gameStartTime'] = strtotime('+1 day', strtotime($firstGameDateTime));
                            //     }

                            //     if($value['gameStartTime'] != '-'){
                            //         $value['gameStartTime'] = $value['gameStartTime'] - $currentTime;
                            //     }
                            // }
                            break;

                        case 'Matured':
                            $value['hasJoined'] = '1';
                            $value['isMatured'] = '1';
                            break;
                    }
                }

                unset($value['id']);
                unset($value['client_id']);
                unset($value['sponsor_id']);
                unset($value['creator_id']);
                unset($value['product_id']);
                unset($value['expire_at']);

                $portfolioList[] = $value;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully exported", 'data' => $data);
            }

            $totalRecord            = $copyDB->getValue('mlm_client_portfolio', 'COUNT(id)');
            $data['portfolioList']  = $portfolioList;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }
            $data['grandTotal']     = $grandTotal;
            $data['seeAll']         = $seeAll;

            if($site != 'Member'){
                $data['countryList'] = $db->get('country', null, 'id, name');
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /* Successfully retrieved */, 'data' => $data);
        }

       //  public function getPortfolioList1($params, $site, $userID,$specialFilterArray=0) {

       //      $db = MysqliDb::getInstance();

       //      $language       = General::$currentLanguage;
       //      $translations   = General::$translations;
            
       //      $productID     = $params['productID'];
       //      $searchData     = $params['searchData'];
       //      $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
       //      $seeAll         = $params['seeAll'];
       //      $limit          = General::getLimit($pageNumber);
       //      $decimalPlaces  = Setting::getInternalDecimalFormat();

       //      $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

       //      // Get member leader username
       //      $leaderUsernameArray = $db->map('client_id')->get('tree_sponsor',null,'client_id,(SELECT client.username FROM client WHERE client.id = tree_sponsor.upline_id) AS username');

       //      $tableName      = "mlm_client_portfolio";
       //      $column         = array(

       //          "id",
       //          "client_id",
       //          "reference_no",
       //          "created_at",
       //          "(SELECT username FROM client WHERE id = client_id) AS username",
       //          "(SELECT name FROM client WHERE id = client_id) AS fullname",
       //          "(SELECT identity_number FROM client WHERE id = client_id) AS identity_number",
       //          "(SELECT email FROM client WHERE id = client_id) AS email",
       //          "(SELECT member_id FROM client WHERE id = client_id) AS memberID",
       //          "(SELECT name FROM country WHERE id = (SELECT country_id FROM client WHERE id = client_id)) AS country",
       //          "(SELECT username FROM client WHERE id = (SELECT sponsor_id FROM client WHERE id = client_id)) AS sponsorUsername",
       //          "status",
       //          // "day_left",
       //          "(product_price) AS product_price",
       //          "expire_at",
       //          "product_id",
       //          "portfolio_type",
       //          "bonus_value AS amount",
       //          "max_cap",
       //          "(SELECT username FROM client WHERE id = creator_id) AS creatorUsername",
       //          "(SELECT mlm_pin.code FROM mlm_pin WHERE mlm_pin.belong_id = mlm_client_portfolio.belong_id) AS pinCode",
       //          "pairing_cap",
       //          // "rebateLock",
       //          // "rebateWithholdingCredit",
       //      );

       //      // Get user name


       //      $adminLeaderAry = Setting::getAdminLeaderAry();

       //      // Means the search params is there
       //      $cpDb = $db->copy();
       //      if (count($searchData) > 0) {
       //          foreach ($searchData as $k => $v) {
       //              $dataName = trim($v['dataName']);
       //              $dataValue = trim($v['dataValue']);

       //              switch($dataName) {
       //                  case 'leaderUsername':

       //                      $clientID = $db->subQuery();
       //                      $clientID->where('username', $dataValue);
       //                      $clientID->getOne('client', "id");

       //                      $downlines = Tree::getSponsorTreeDownlines($clientID);
       //                      // $downlines[] = $clientID;

       //                      if (empty($downlines))
       //                          return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

       //                      $db->where('client_id', $downlines, "IN");

       //                      break;

       //                  case 'mainLeaderUsername':

       //                      $cpDb->where('username', $dataValue);
                            // $mainLeaderID  = $cpDb->getValue('client', 'id');
                            // $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            // if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            // $db->where('client_id', $mainDownlines, "IN");

       //                      break;
       //              }
       //              unset($dataName);
       //              unset($dataValue);
       //          }


       //          foreach ($searchData as $k => $v) {
       //              $dataName = trim($v['dataName']);
       //              $dataValue = trim($v['dataValue']);
       //              // $dataType = strtolower(trim($v['dataType']));
                        
       //              switch($dataName) {
       //                  case 'portfolioType':
                         
       //                      $db->where("portfolio_type", $dataValue);
                                
       //                      break;

       //                  case 'type':
       //                      $sq = $db->subQuery();
       //                      $sq->where("name", $dataValue);
       //                      $sq->getOne("mlm_product", "id");
       //                      $db->where("product_id", $sq);
                                
       //                      break;

       //                  case 'memberID':
       //                      $sq = $db->subQuery();
       //                      $sq->where("member_id", $dataValue);
       //                      $sq->get("client", NULL, "id");
       //                      $db->where("client_id", $sq, "in");
                                
       //                      break;

       //                  case 'fullName':
       //                      $sq = $db->subQuery();
       //                      $sq->where("name", $dataValue);
       //                      $sq->get("client", NULL, "id");
       //                      $db->where("client_id", $sq, "in");
                                
       //                      break;
                            
       //                  case 'username':
       //                      //If like, else defaults to '='
       //                      if ($usernameSearchType == "like") {
       //                          $dataValue="%$dataValue%";
       //                          $sq = $db->subQuery();
       //                          $sq->where("username", $dataValue,'like');
       //                          $sq->get("client", NULL, "id");
       //                          $db->where('client_id', $sq,'IN');
       //                      }else {
       //                          $sq = $db->subQuery();
       //                          $sq->where("username", $dataValue);
       //                          $sq->get("client", NULL, "id");
       //                          $db->where('client_id', $sq);
       //                      }
       //                      break;

       //                  case 'phone':
       //                      $sq = $db->subQuery();
       //                      $sq->where("phone", $dataValue);
       //                      $sq->getOne("client", "id");
       //                      $db->where("client_id", $sq);
       //                      break;
                        
       //                  case 'countryName':
       //                      $sq = $db->subQuery();
       //                      $sq->where("country_id", $dataValue);
       //                      $sq->get("client", NULL, "id");
       //                      $db->where("client_id", $sq, "in");
       //                      break;
                            
       //                  case 'entryDate':
       //                      // Set db column here
       //                      $columnName = 'date(created_at)';
                                
       //                      $dateFrom = trim($v['tsFrom']);
       //                      $dateTo = trim($v['tsTo']);
       //                      if(strlen($dateFrom) > 0) {
       //                          if($dateFrom < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
       //                      }
       //                      if(strlen($dateTo) > 0) {
       //                          if($dateTo < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          if($dateTo < $dateFrom)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
       //                          // $dateTo += 86399;
       //                          $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
       //                      }
                                
       //                      unset($dateFrom);
       //                      unset($dateTo);
       //                      unset($columnName);
       //                      break;
                            
       //                  case 'maturityDate':
       //                      // Set db column here
       //                      $columnName = 'date(expire_at)';
                                
       //                      $dateFrom = trim($v['tsFrom']);
       //                      $dateTo = trim($v['tsTo']);
       //                      if(strlen($dateFrom) > 0) {
       //                          if($dateFrom < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00162"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
       //                      }
       //                      if(strlen($dateTo) > 0) {
       //                          if($dateTo < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00163"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          if($dateTo < $dateFrom)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00164"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
       //                          // $dateTo += 86399;
       //                          $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
       //                      }
                                
       //                      unset($dateFrom);
       //                      unset($dateTo);
       //                      unset($columnName);
       //                      break;

       //                  case 'leaderUsername':
       //                      // do nothing =D

       //                      break;

       //                  case 'mainLeaderUsername':
       //                      // do nothing =D

       //                      break;

       //                  case 'status':
       //                      $db->where('status', $dataValue);
       //                      break;

       //                  case 'refNo':
       //                      $db->where('reference_no', $dataValue);
       //                      break;

       //                  case 'clientId':
       //                      $clientId = $dataValue;
       //                      $db->where('client_id', $clientId);
       //                      break;

       //                  case 'productType':
       //                      if ($dataValue) {
       //                          $db->where('product_id',$dataValue);
       //                      }
       //                      break;

       //                  default:
       //                      $db->where($dataName, $dataValue);

       //              }
       //              unset($dataName);
       //              unset($dataValue);
       //              unset($dataType);
       //          }
       //      }

       //      if($site == 'Member'){
       //          $clientID = $userID; 
       //          $db->where("client_id", $clientID);
       //      }

       //      if ($specialFilterArray){
       //          foreach ($specialFilterArray as $columnName => $columnField) {
       //              switch ($columnName) {
       //                  case 'productName':
       //                      $sq = $db->subQuery();
       //                      $sq->where("name", $columnField);
       //                      $sq->getOne("mlm_product", "id");
       //                      $db->where("product_id", $sq);
       //                      break;
                        
       //                  default:
       //                      $db->where($columnName,$columnField);
       //                      break;
       //              }
                    
       //          }
       //      }

       //      $copyDb = $db->copy();
       //      $totalRecord = $copyDb->getValue($tableName, "count(*)");

       //      if($seeAll == "1"){
       //          $limit = array(0, $totalRecord);
       //      } 
       //      if (!empty($productID)){
       //          $db->where("product_id", $productID);
       //      }

       //      if($adminLeaderAry){
    			// $db->where('client_id', $adminLeaderAry, 'IN');
       //      }

       //      $db->orderBy("id", "DESC");
       //      $portfolioList = $db->get($tableName, $limit, $column);
            
       //      if (empty($portfolioList))
       //          return array('status' => "ok", 'code' => 0, 'statusMsg' => 'No Results Found', 'data' => "");

       //      $productList = Product::getProductList();
       //      $date1 = new DateTime(date("Y-m-d"));

       //      // $countRecord = 0;
       //      $grandTotal = 0;

       //      foreach ($portfolioList as $portfolio) {
             
       //          $portfolioListing['clientID']               = $portfolio['client_id'];
       //          $portfolioListing['memberID']               = $portfolio['memberID'];
       //          $portfolioListing['identity_number']        = $portfolio['identity_number'] ? $portfolio['identity_number'] : '-';
       //          $portfolioListing['email']                  = $portfolio['email'] ? $portfolio['email'] : '-';

       //          $mainLeaderUsername = Tree::getMainLeaderUsername($portfolioListing);
       //          $portfolioListing['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

       //          $portfolioListing['portfolioID']            = $portfolio['id']?:'-';
       //          $portfolioListing['reference_no']           = $portfolio['reference_no']?:'-';
       //          $portfolioListing['createdAt']              = General::formatDateTimeToString($portfolio['created_at'])?:'-';
       //          $portfolioListing['username']               = $portfolio['username']?:'-';
       //          $portfolioListing['fullname']               = $portfolio['fullname']?:'-';
       //          $portfolioListing['country']                = $portfolio['country']?:'-';
       //          $portfolioListing['sponsorUsername']        = $portfolio['sponsorUsername']?:'-';
       //          $portfolioListing['creatorUsername']        = $portfolio['creatorUsername']?:'-';
       //          $portfolioListing['pinCode']                = $portfolio['pinCode']?:'-';
       //          $portfolioListing['bonusValue']             = Setting::setDecimal($portfolio['amount']);
       //          $portfolioListing['leaderUsername']         = $leaderUsernameArray[$portfolio['client_id']]?:'-';
       //          $portfolioListing['pairingCap']             = Setting::setDecimal($portfolio['pairing_cap']);

       //          $portfolioListing['rebateLock']             = $portfolio['rebateLock'] ?  $translations["A01273"][$language]:$translations["A01274"][$language];
       //          $portfolioListing['rebateWithholdingCredit']= $portfolio['rebateWithholdingCredit']? $translations["C00014"][$language]:$translations["C00009"][$language];//

       //          if($portfolio['status'] == "Purchased"){
       //              $portfolio['status'] = "Active";
       //          } 

       //          $portfolioListing['status']                 = $portfolio['status'] ? $portfolio['status'] : '-';

       //          $portfolioListing['packageConvertible']     = '0';
       //          if($portfolioListing['status'] == 'Active'){
       //          $portfolioListing['statusDisplay']          = $translations["M00329"][$language];
       //          }
       //          if($portfolioListing['status'] == 'Inactive'){
       //              $productName=$productList['data'][$portfolio['product_id']]['name'];
       //              if($productName=='hedging'||$productName=='newHedging'){
       //                  $portfolioListing['packageConvertible']= '1';
       //              }


       //          $portfolioListing['statusDisplay']          = $translations["M00330"][$language];
       //          }
       //          if($portfolioListing['status'] == 'Terminated'){
       //          $portfolioListing['statusDisplay']          = $translations["M01655"][$language];
       //          }
       //          if($portfolioListing['status'] == 'Redeemed'){
       //          $portfolioListing['statusDisplay']          = $translations["M02051"][$language];
       //          }
       //          // $portfolioListing['expireAt']               = General::formatDateTimeToString($portfolio['expire_at'])?:'-';
       //          $portfolioListing['product_translate_code'] = $productList['data'][$portfolio['product_id']]['translation_code'];
       //          $portfolioListing['product_name']          = $translations[$productList['data'][$portfolio['product_id']]['translation_code']][$language];
       //          $portfolioListing['VRPercentage']           = $productList['data'][$portfolio['product_id']]['vestingReceivable']['value'];
       //          $portfolioListing['vestingReceivableValue'] = $portfolioListing['VRPercentage'] * $portfolio['product_price'] / 100;
       //          $portfolioListing['productPrice']           = number_format($portfolio['product_price'], $decimalPlaces, '.', '');
       //          $portfolioListing['portfolioType']          = $portfolio['portfolio_type'];

       //          $portfolioListing['amount']          = number_format($portfolio['amount'], $decimalPlaces, '.', '');
       //          $portfolioListing['amountDisplay'] = 0;
       //          $portfolioListing['amountNBVDisplay'] = 0;
       //          $portfolioListing['max_cap']          = number_format($portfolio['max_cap'], $decimalPlaces, '.', '');

       //          if($portfolio['portfolio_type'] == 'Package Re-entry'){
       //             // $portfolioListing['portfolioTypeDisplay'] = $translations["T00012"][$language];
       //             $portfolioListing['portfolioTypeDisplay'] = 'Investment';
       //             $portfolioListing['amountDisplay'] = $portfolio['amount'];
       //          }elseif($portfolio['portfolio_type'] == 'freeWithRebate'){
       //             $portfolioListing['portfolioTypeDisplay'] = 'Non-BV Rebate';
       //             $portfolioListing['amountNBVDisplay'] = $portfolio['amount'];
       //          }elseif($portfolio['portfolio_type'] == 'noRebate'){
       //             $portfolioListing['portfolioTypeDisplay'] = 'Non-BV';
       //             $portfolioListing['amountNBVDisplay'] = $portfolio['amount'];
       //          }

       //          switch ($portfolio['portfolio_type']) {
       //              case 'Credit Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01421'][$language];
       //                  break;

       //              case 'Diamond Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01422'][$language];
       //                  break;

       //              case 'Credit Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01425'][$language];
       //                  break;

       //              case 'Diamond Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01426'][$language];
       //                  break;

       //              case 'NBV Credit Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01423'][$language];
       //                  break;

       //              case 'NBVR Credit Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01424'][$language];
       //                  break;

       //              case 'NBV Credit Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01427'][$language];
       //                  break;

       //              case 'NBVR Credit Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01428'][$language];
       //                  break;
                    
       //              default:
       //                  $portfolioListing['portfolioTypeDisplay'] = $portfolio['portfolio_type'];
       //                  break;
       //          }

       //          $portfolioListing['maturityDate']           = $portfolio["expire_at"]>0?date("Y-m-d",strtotime($portfolio["expire_at"])):"-";

       //          $portfolioMaturityDate = date("Y-m-d",strtotime($portfolio["expire_at"]));

       //          $date2 = new DateTime($portfolioMaturityDate);
       //          if( $date2 < $date1){
       //              $portfolioListing['countDownDays']  = "0";
       //          } else {

       //          $interval = $date1->diff($date2);
              
       //          $portfolioListing['countDownDays']  = $interval->days;

       //          } 

       //          if ($site == 'Admin') {
       //              // unset($portfolioListing['portfolioID']);
       //              // unset($portfolioListing['productPrice']);
       //              unset($portfolioListing['product_translate_code']);
       //          }
                
       //          $portfolioPageListing[] = $portfolioListing;
       //          $grandTotal += ($portfolio['amount']?number_format($portfolio['amount'], $decimalPlaces, '.', ''):'0');
       //          // $countRecord++;
       //      }

       //      $memberDetails = Client::getCustomerServiceMemberDetails($clientId);
       //      $data['memberDetails'] = $memberDetails['data']['memberDetails'];

       //      $data['portfolioPageListing']       = $portfolioPageListing;

       //      if($params['type'] == "export"){
       //          $params['command'] = __FUNCTION__;
       //          $data = Excel::insertExportData($params);
       //          return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
       //      }

       //      $data['pageNumber']                 = $pageNumber;
       //      $data['totalRecord']                = $totalRecord;
       //      if($seeAll == "1"){
       //          $data['totalPage']              = 1;
       //          $data['numRecord']              = $totalRecord;
       //      }else{
       //          $data['totalPage']              = ceil($totalRecord/$limit[1]);
       //          $data['numRecord']              = $limit[1];
       //      }
       //      $data['grandTotal'] = $grandTotal;
       //      $data['seeAll'] = $seeAll;
       //      if($site != 'Member'){
       //          $data['countryList'] = $db->get('country', null, 'id, name');
       //      }

       //      return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Portfolio List successfully retrieved', 'data' => $data);
       //  }

        public function getProductDetail($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_product";
            $searchData     = $params['searchData'];
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $column         = array(

                "mlm_product.name AS product_name",
                "mlm_product.code",
                "mlm_product.category",
                "mlm_product.price",
                "mlm_product.status",
                "mlm_product.translation_code",
                "mlm_product.active_at",
                "mlm_product.expire_at",
                "mlm_product_setting.name AS setting_name",
                "mlm_product_setting.value"

            );

            if (count($searchData) > 0) {
                foreach ($searchData as $array) {
                    foreach ($array as $key => $value) {
                        if ($key == 'dataName') {
                            $dbColumn = $tableName . "." .$value;
                        } else if ($key == 'dataValue') {
                            foreach ($value as $innerVal) {
                                $db->where($dbColumn, $innerVal);
                            }
                        }
                    }
                }
            }

            $copyDb = $db->copy();
            $db->join("mlm_product_setting", "mlm_product_setting.product_id = mlm_product.id", "LEFT");
            $totalRecord = $copyDb->getValue($tableName, "count(*)");
            $productDetail = $db->get($tableName, null, $column);

            $newProductDetail       = array();
            $productArray           = array();
            $newKey                 = -1;
            foreach($productDetail as $productDetailKey => $product){

                if (!in_array($product["product_name"], $productArray)) {

                    ++$newKey;
                    $newProductDetail[$newKey]["product_name"]       = $product["product_name"];
                    $newProductDetail[$newKey]["code"]               = $product["code"];
                    $newProductDetail[$newKey]["category"]           = $product["category"];
                    $newProductDetail[$newKey]["price"]              = $product["price"];
                    $newProductDetail[$newKey]["status"]             = $product["status"];
                    $newProductDetail[$newKey]["translation_code"]   = $product["translation_code"];
                    $newProductDetail[$newKey]["active_at"]          = $product["active_at"];
                    $newProductDetail[$newKey]["expire_at"]          = $product["expire_at"];
                }
                $newProductDetail[$newKey][$product["setting_name"]] = $product["value"];
                $productArray [] = $product["product_name"];

            }

            $data['productDetail']          = $newProductDetail;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00116"][$language] /* Successfully retrieved product detail */, 'data' => $data);
        }

        public function getActivityLogList($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $searchData     = $params['searchData'];
            $memberId       = $params['memberId'] ? $params['memberId'] : "";
            $dateToday      = date("Ym");

            $usernameSearchType = $params["usernameSearchType"];

            //Get the limit.
            $limit = General::getLimit($pageNumber);

    		$adminLeaderAry = Setting::getAdminLeaderAry();

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach($searchData as $k => $v){
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch ($dataName) {
                        case 'creatorUsername':
                            if ($dataValue == "Public Registration") {
                                $db->where('creator_id', "0");
                            } else{
                                $db->where('username', $dataValue);
                                $searchID = $db->getValue('admin',"id");

                                if (empty($searchID)){
                                    $db->where('username', $dataValue);
                                    $searchID = $db->getValue('client', "id");
                                }

                                $db->where('creator_id', $searchID);
                            }
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
                            if ($usernameSearchType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'clientId':
                            // $db->where('client_id', $dataValue);
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);  
                            break;
                                
                        case 'activityType':
                            $db->where('title', $dataValue);
                            break;

                        case 'searchDate':
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00166"][$language] /* Please specify a date */, 'data'=>"");
                                
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where("DATE(created_at)",date('Y-m-d',$dataValue));
                            break;
                                
                        case 'searchTime':
                            // Set db column here
                            $columnName = 'created_at';

                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00168"][$language] /* Please specify a date */, 'data'=>"");

                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00169"][$language] /* Invalid date. */, 'data'=>"");

                            $dataValue = date('Y-m-d', $dataValue);

                            $dateFrom = trim($v['timeFrom']);
                            $dateTo = trim($v['timeTo']);
                            if(strlen($dateFrom) > 0) {
                                $dateFrom = strtotime($dataValue.' '.$dateFrom);
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00170"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                $dateTo = strtotime($dataValue.' '.$dateTo);
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00171"][$language] /* Invalid date. */, 'data'=>"");

                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00172"][$language] /* Time from cannot be later than time to */, 'data'=>$data);

                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'searchMonth':
                            $dateToday = $dataValue;
                            break;

                        case 'fullname':
                            if($dataType == "like"){
                                $fullname = $db->subQuery();
                                $fullname->where('name',  "%" .  $dataValue . "%", "LIKE");
                                $fullname->get('client', NULL, "id");
                                $db->where("client_id", $fullname,"IN");
                            }else{
                                $fullname = $db->subQuery();
                                $fullname->where('name', $dataValue);
                                $fullname->getOne('client', "id");
                                $db->where("client_id", $fullname);

                            }
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if (!empty($memberId))
                $db->where("a.client_id", $memberId);

    		if($adminLeaderAry)$db->where('a.client_id', $adminLeaderAry, 'IN');

            $db->where("title", "noRebate", "!=");
            $db->orderBy("created_at", "DESC");
            $copyDb = $db->copy();

            $getAdminId        = '(SELECT id FROM admin WHERE a.creator_id = admin.id) as adminId';
            $getMemberId       = '(SELECT member_id FROM client WHERE a.client_id = client.id) as memberId';
            $getAdminUsername  = '(SELECT username FROM admin WHERE a.creator_id = admin.id) as adminUsername';
            $getMemberUsername = '(SELECT username FROM client WHERE a.creator_id = client.id) as clientUsername';
            // specially for public registration
            $getClientUsername = '(SELECT username FROM client WHERE a.client_id = client.id) as getClientUsername';
            $getClientName     = '(SELECT name FROM client WHERE a.client_id = client.id) as getClientName';

            try {
                $result = $db->get('activity_log_'.$dateToday." a", $limit, $getMemberUsername. "," .$getAdminUsername. "," .$getClientUsername. "," .$getMemberId. "," .$getAdminId. ", " .$getClientName. ", client_id, title, translation_code, data, creator_id, creator_type, created_at");
            }
            catch (Exception $e) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00117"][$language] /* No Results Found. */, 'data' => "");
            }

            $creditRes = $db->get('credit', null, 'name, admin_translation_code');
            foreach($creditRes AS $data){
                $creditAry[$data['name']] =  $translations[$data['admin_translation_code']][$language];
            }

            if ($result) {
                foreach($result as $value) {

                    $activity['activityType'] = $value['title'];
                    $translationCode          = $value['translation_code'];
                    $activityData             = (array) json_decode($value['data'], true);

                    $db->where('code', $translationCode);
                    $db->where('language',$language);
                    $content     = $db->getValue('language_translation', 'content');

                    foreach($activityData as $key => $val) {
                        if($key=="client_id"){
                            $db->where("id", $val);
                            $val = $db->getValue("client", "username");
                            $key = "user";
                        }

                        $oriKeyWord = '%%'.$key.'%%';
                        if($key == 'credit'){
                            $val = $creditAry[$val];
                        }
                        $content = str_replace($oriKeyWord, $val, $content);                       
                    }
                    //pieces chop content where ' %%' is at.
                    //pieces2 chop pieces from array position [1] onwards where '%%' is at.
                    //pieces3 chop pieces at array position [0] only where '%%' is at.
                    //pieces3 is using to detect if %% is the first word.
                    $pieces = explode(" %%", $content);

                    if(isset($pieces[1])) {
                        $pieces3 = explode("%%", $pieces[0]);
                        if(isset($pieces3[1]))
                            $piecesList[] = $pieces3[1];

                        foreach(array_slice($pieces, 1) as $val) {
                            $pieces2 = explode("%%", $val);
                            $piecesList[] = $pieces2[0];
                        }
                                
                        foreach($piecesList as $key) {
                            $oriKeyWord = '%%'.$key.'%%';
                            $content = str_replace($oriKeyWord, '', $content);                       
                        }
                    }

                    $activity['description'] = $content;
                    $activity['created_at']  = General::formatDateTimeToString($value['created_at'], "d/m/Y h:i:s A");

                    if ($value['creator_type'] == "Admin")
                        $activity['doneBy']  = $value['adminUsername']?:"-";
                    else if ($value['creator_type'] == "Member")
                        $activity['doneBy']  = $value['clientUsername']?:"-";
                    else
                        $activity['doneBy']  = "-";

                    $activity['memberID']    = $value['memberId']?:"-";
                    $activity['fullname']    = $value['getClientName']?:"-";
                    $activity['username']    = $value['getClientUsername']?:"-";


                    if ( $value['creator_type'] == "Member" && empty($value['clientUsername'])){
                         $activity['doneBy']  = "Public Registration";
                         $activity['username'] = $value['getClientUsername']?:"-";
                    }

                    $activityList[]          = $activity;
                }

                // This is to get the title for the search select option
                $db->where("title", "noRebate", "!=");
                $dropDownResult = $db->get('activity_log_'.$dateToday, null, "title");
                if(empty($dropDownResult))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00173"][$language] /* Failed to get title for search option */, 'data' => '');
                    
                foreach($dropDownResult as $value) {
                    $searchBarData['activityType'] = $value['title'];
                    $searchBarDataList[]           = $searchBarData;
                }

                $totalRecord = $copyDb->getValue ('activity_log_'.$dateToday . " a", "count(id)");

                // remove duplicate command. Then sort it alphabetically
                $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
                sort($searchBarDataList);

                $data['activityLogList']  = $activityList;
                $data['activityTypeList'] = $searchBarDataList;
                $data['totalPage']        = ceil($totalRecord/$limit[1]);
                $data['pageNumber']       = $pageNumber;
                $data['totalRecord']      = $totalRecord;
                $data['numRecord']        = $limit[1];
                        
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00117"][$language] /* No Results Found. */, 'data'=> "");
            }
        }

        public function getLanguageTranslationList($params) {
            $db = MysqliDb::getInstance();
                
            $pageNumber  = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit       = General::getLimit($pageNumber);

            $searchData  = json_decode($languageCodeParams['searchData']);
            if (count($searchData) > 0) {
                foreach ($searchData as $array) {                  
                    foreach ($array as $key => $value) {
                        if ($key == 'dataName') {
                            $dbColumn = $value;
                        } else if ($key == 'dataValue') {
                            foreach ($value as $innerVal) {
                                $db->where($dbColumn, $innerVal);
                            }
                        }
                    }
                }
            }
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("language_translation", $limit);

            $totalRecord = $copyDb->getValue ("language_translation", "count(id)");
                foreach($result as $value) {
                    $language['id']           = $value['id'];
                    $language['contentCode']  = $value['code'];
                    $language['language']     = $value['language'];
                    $language['module']       = $value['module'];
                    $language['site']         = $value['site'];
                    $language['category']     = $value['type'];
                    $language['content']      = $value['content'];

                    $languageList[] = $language;
                        
                }


                    $data['languageCodeList'] = $languageList;
                    $data['totalPage']        = ceil($totalRecord/$limit[1]);
                    $data['pageNumber']       = $pageNumber;
                    $data['totalRecord']      = $totalRecord;
                    $data['numRecord']        = $limit[1];
                    
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getLanguageTranslationData($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00174"][$language] /* Please Select A Language Code */, 'data'=> '');
                
            $db->where('id', $id);
            $result = $db->getOne("language_translation");

            if (!empty($result)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00175"][$language] /* Invalid Language */, 'data'=>"");
            }
        }

        public function editLanguageTranslationData($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $id           = trim($params['id']);
            $contentCode  = trim($params['contentCode']);
            $module       = trim($params['module']);
            $language     = trim($params['language']);
            $site         = trim($params['site']);
            $category     = trim($params['category']);
            $content      = trim($params['content']);

            if(strlen($contentCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00176"][$language] /* Please Enter Language Name. */, 'data' => "");

            if(strlen($language) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00177"][$language] /* Please Enter Language Code. */, 'data' => "");

            $updatedAt = $db->now();

            $fields    = array("code", "module", "language", "site", "type", "content", "updated_at");
            $values    = array($contentCode, $module, $language, $site, $category, $content, $updatedAt);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result    = $db->update("language_translation", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00119"][$language] /* Permission Successfully Updated. */);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00178"][$language] /* Invalid Permission. */, 'data' => "");
            }
        }

        public function getExchangeRateList($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_currency_exchange_rate";
            $joinTable      = "country";
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();


            $db->where("status", "Active");
            $countryRes = $db->get("country", null, "id, name, translation_code, currency_code");

            foreach ($countryRes as $countryRow) {
                if($countryRow['currency_code']){
                    $countryIDArr[$countryRow['id']] = $countryRow['id'];
                    $countryData[$countryRow['id']] = $countryRow;
                }
            }

            $data['activeCountry'] = $countryData;

            $column = array(

                $tableName . ".id",
                $tableName . ".currency_code",
                $tableName . ".exchange_rate",
                $tableName . ".buy_rate",
                $tableName . ".country_id",
            );

            $db->where("country_id", $countryIDArr, "IN");
            $db->orderBy('priority','ASC');
            
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");
            $exchangeRateList = $db->get($tableName, $limit, $column);

            if (empty($exchangeRateList))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00120"][$language] /* No Result Found. */, 'data'=> $data);

            foreach ($exchangeRateList as $exchangeRate) {

                if (Cash::$creatorType == "Admin") {
                    if (!empty($exchangeRate['id']))
                        $exchangeRateListing['id']              = $exchangeRate['id'];
                    else
                        $exchangeRateListing['id']              = "-";
                }

                // if (!empty($exchangeRate['name']))
                //     $exchangeRateListing['name']                = $exchangeRate['name'];
                // else
                //     $exchangeRateListing['name']                = "-";
                $translateCode = $countryData[$exchangeRate['country_id']]['translation_code'];
                $exchangeRateListing['display_name'] = $translations[$translateCode][$language];
                $exchangeRateListing['countryID'] = $exchangeRate['country_id'];

                if (!empty($exchangeRate['currency_code']))
                    $exchangeRateListing['currencyCode']        = $exchangeRate['currency_code'];
                else
                    $exchangeRateListing['currencyCode']        = "-";

                if (!empty($exchangeRate['exchange_rate']))
                    $exchangeRateListing['exchangeRate']        = number_format($exchangeRate['exchange_rate'], $decimalPlaces, '.', '');
                else
                    $exchangeRateListing['exchangeRate']        = "-";

                if (!empty($exchangeRate['buy_rate']))
                    $exchangeRateListing['buyRate']        = number_format($exchangeRate['buy_rate'], $decimalPlaces, '.', '');
                else
                    $exchangeRateListing['buyRate']        = "-";

                $exchangeRatePageListing[] = $exchangeRateListing;
            }

            $data['exchangeRatePageListing']    = $exchangeRatePageListing;
            $data['totalPage']                  = ceil($totalRecord/$limit[1]);
            $data['pageNumber']                 = $pageNumber;
            $data['totalRecord']                = $totalRecord;
            $data['numRecord']                  = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00121"][$language] /* Exchange rate list successfully retrieved. */, 'data'=> $data);
        }

        public function editExchangeRate($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_currency_exchange_rate";
            $exchangeRate   = trim($params['exchangeRate']);
            $buyRate        = trim($params['buyRate']);
            $userID         = $db->userID;
            $site           = $db->userType;
            $now            = date("Y-m-d H:i:s");
            $countryID      = trim($params['countryID']);

            if (empty($exchangeRate) || !is_numeric($exchangeRate) || $exchangeRate < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            if (empty($buyRate) || !is_numeric($buyRate) || $buyRate < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            if (empty($countryID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            if($site != "Admin"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            }

            $db->where('id', $userID);
            $adminUsername = $db->getValue("admin", "username");

            $db->where("id", $countryID);
            $db->where("status", "Active");
            $validCountry = $db->getOne("country", "id, currency_code, translation_code");
            if (empty($validCountry) || !$validCountry['currency_code'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $db->where("country_id", $validCountry['id']);
            $rateData = $db->getOne("mlm_currency_exchange_rate");

            if($rateData){
                unset($updateData);
                $updateData = array(
                    "exchange_rate" => $exchangeRate,
                    "buy_rate" => $buyRate,
                    "updated_at" => $now,
                );

                $db->where("country_id", $validCountry['id']);
                if (!$db->update($tableName, $updateData))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");    
            }else{
                $maxPriority = $db->getValue("mlm_currency_exchange_rate", 'MAX(priority)', NULL);
                $maxPriority = $maxPriority[0];
                // insert
                unset($insertData);
                $insertData = array(
                    "country_id"    => $validCountry['id'],
                    "currency_code" => $validCountry['currency_code'],
                    "exchange_rate" => $exchangeRate,
                    "buy_rate"      => $buyRate,
                    "created_at"    => $now,
                    "updated_at"    => $now,
                    "status"        => "Active",
                    "priority"      => $maxPriority + 1,
                );
                if (!$db->insert("mlm_currency_exchange_rate", $insertData))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");    
            }
            
            $countryID = $validCountry['id'];
            $currencyCode = $validCountry['currency_code'];
            $countryDisplay = $translations[$validCountry['translation_code']][$language];

            unset($insertData);
            $insertData = array(
                "country_id"    => $rateData['country_id'],
                "currency_code" => $rateData['currency_code'],
                "exchange_rate" => $exchangeRate,
                "buy_rate"      => $buyRate,
                "creator_id"    => $userID,
                "created_at"    => $now,
            );
            $db->insert("mlm_currency_exchange_rate_history", $insertData);

            // insert activity log
            $title   = 'Update Exchange Rate';
            $titleCode      = 'T00061'; // Update Exchange Rate
            $activityCode   = 'L00083'; // %%admin%% updated country: %%country%%, currency code : %%currencyCode%%, exchange rate to : %%exRate%%, buy rate to : %%buyRate%%.
            $activityData   = array(
                'admin'     => $adminUsername,
                'country'   => $countryDisplay,
                'currencyCode' => $currencyCode,
                'exRate'    => $exchangeRate,
                'buyRate'   => $buyRate,
            );

            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00122"][$language] /* Successfully update exchange rate. */, 'data'=> "");
        }

        public function getUnitPriceList($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_unit_price";
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $column         = array(

                "id",
                "unit_price",
                "(SELECT name FROM admin WHERE id = creator_id) AS creator_name",
                "created_at"
            );

            $db->orderBy("created_at", "DESC");
            $copyDb = $db->copy();
            $unitPriceList = $db->get($tableName, null, $column);
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if (empty($unitPriceList))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00123"][$language] /* No Result Found. */, 'data'=> "");

            foreach ($unitPriceList as $unitPrice) {

                if (!empty($unitPrice['id']))
                    $unitPriceListing['id']                     = $unitPrice['id'];
                else
                    $unitPriceListing['id']                     = "-";

                if (!empty($unitPrice['unit_price']))
                    $unitPriceListing['unitPrice']              = number_format($unitPrice['unit_price'], $decimalPlaces, '.', '');
                else
                    $unitPriceListing['unitPrice']              = "-";

                if (!empty($unitPrice['created_at']))
                    $unitPriceListing['createdAt']              = General::formatDateTimeString($offsetSecs, $unitPrice['created_at'], $format = "d/m/Y h:i:s A");
                else
                    $unitPriceListing['createdAt']              = "-";

                if (!empty($unitPrice['creator_name']))
                    $unitPriceListing['creatorName']            = $unitPrice['creator_name'];
                else
                    $unitPriceListing['creatorName']            = "-";

                $unitPricePageListing[] = $unitPriceListing;
            }


            $data['unitPricePageListing']           = $unitPricePageListing;
            $data['totalPage']                      = ceil($totalRecord/$limit[1]);
            $data['pageNumber']                     = $pageNumber;
            $data['totalRecord']                    = $totalRecord;
            $data['numRecord']                      = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00124"][$language] /* Unit price list successfully retrieved */, 'data'=> $data);
        }

        public function addUnitPrice($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_unit_price";
            $unitPrice      = trim($params['unitPrice']);
            $creatorId      = trim($params['creatorId']);
            $type           = $params['type'] ? trim($params['type']) : "purchase";
            $activedDate    = $params['actived_date'] ? trim($params['actived_date']) : $db->now();

            if (empty($unitPrice) || !is_numeric($unitPrice) || $unitPrice < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Successfully insert unit price */, 'data'=> "");

            if (empty($creatorId) || !is_numeric($creatorId) || $creatorId < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00182"][$language] /* Successfully insert unit price */, 'data'=> "");

            $insertData     = array(

                "unit_price"        => $unitPrice,
                "type"              => $type,
                "creator_id"        => $creatorId,
                "creator_type"      => "Admin",
                "created_at"        => $db->now(),
                "actived_date"      => $activedDate 
            );

            $id = $db->insert($tableName, $insertData);

            if(empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00183"][$language] /* Failed to insert unit price */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00125"][$language] /* Successfully insert unit price */, 'data'=> "");
        }

        public function getMemberAccList($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            if(empty($params['creditType']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            $creditType = $params['creditType'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $usernameSearchType = $params["usernameSearchType"];

            $creditID = $db->subQuery();
            $creditID->where("name", $creditType);
            $creditID->get("credit", null, "id");
            $db->where("credit_id", $creditID, "in");
            $db->where("name", "isWallet");
            $result = $db->getOne("credit_setting", "value, admin");

            if(!$result['value'] && !$result['admin'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00196"][$language] /* Invalid credit type */, 'data' => "");
            unset($result);

            //Get the limit.
            $limit      = General::getLimit($pageNumber);
            $searchData = $params['searchData'];
            
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

                            $db->where('id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

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
                        case 'name':
                            if ($dataType == "like") {
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            } else {
                                $db->where('name', $dataValue);
                            }
                                
                            break;
                            
                        case 'username':
                            if ($usernameSearchType == "like") {
                                $db->where("username", $dataValue . "%", "LIKE");
                            } else {
                                $db->where("username", $dataValue);
                            }
                                
                            break;

                        case 'memberID':
                            $db->where('member_id', $dataValue);
                            break;

                        case 'email':
                            $db->where('email', $dataValue);
                            break;
                            
                        case 'countryID':
                            $db->where('country_id', $dataValue); 
                            break;
                            
                        case 'disabled':
                            $db->where('disabled', $dataValue);
                                
                            break;

                        case 'phone':
                            $db->where('phone', $dataValue);
                            break;

                        case 'leaderUsername':

                            break;

                        case 'mainLeaderUsername':

                            break; 
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
            $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
            
            if($adminLeaderAry) $db->where('id', $adminLeaderAry, 'IN');

            $db->where("type", "Client");
            $copyDb = $db->copy();

            $db->orderBy("id", "DESC");

            $result = $db->get("client", $limit, 'id, member_id, username, name, '.$getCountryName.','.$getSponsorUsername.', email, disabled');

            $totalRecords = $copyDb->getValue("client", "count(*)");

            if (!empty($result)) {
                foreach($result as $value) {
                    $client['clientID']           = $value['id'];
                    $client['member_id']    = $value['member_id'];
                    $client['username']     = $value['username'];
                    $client['name']         = $value['name'];

                    $client['sponsorUsername'] = $value['sponsor_username'] ? $value['sponsor_username'] : "-";

                    $mainLeaderUsername = Tree::getMainLeaderUsername($client);
                    $client['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                    $client['country']         = $value['country_name'] ? $value['country_name'] : "-";

                    $client['email']        = $value['email'];
                    $client['disabled']     = ($value['disabled'] == 1)? "Disabled":"Active";

                    $clientList[] = $client;
                }

                $data['memberList']  = $clientList;
                $data['totalPage']   = ceil($totalRecords/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord']   = $limit[1];
                $data['countryList'] = $db->get('country', null, 'id, name');

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00131"][$language] /* No Results Found */, 'data'=>"");
            }
        }

        public function getMemberBalance($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $params['id'];
            $creditType     = $params['creditType'];

            if(empty($creditType))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type. */, 'data' => "");

            $adminID=Cash::$creatorID;
            //Get admin's roleID
            $db->where("id", $adminID);
            $roleID = $db->getValue("admin","role_id");

            $db->where('id',$roleID);
            $roleName = $db->getValue('roles','name');


            $permissionsID = $db->subQuery();
            $permissionsID->where("role_id",$roleID);
            $permissionsID->where("disabled",0);
            $permissionsID->get('roles_permission',null,'permission_id');
            $db->where('id',$permissionsID,'IN');
            $permissionsRes=$db->get('permissions',null,'name');
            foreach ($permissionsRes as $key => $value) {
                $permissionsArray[]=$value['name'];
            }

            $creditID = $db->subQuery();
            $creditID->where("name", $creditType);
            $creditID->get("credit", null, "id");
            $db->where("credit_id", $creditID, "in");
            $result = $db->get("credit_setting", null, "name,".strtolower($db->userType)." AS permission");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00208"][$language] /* Invalid credit type. */, 'data' => "");

            foreach($result as $value) {
                $permissions[$value['name']] = $value['permission'];

                if($roleName != 'Master Admin'){
                    if (!in_array($creditType.' Withdrawal', $permissionsArray)){
                        $permissions['isWithdrawable']=0;
                    }
                    if (!in_array($creditType.' Adjustment', $permissionsArray)) {
                        $permissions['isAdjustable']=0;
                    }
                    if (!in_array($creditType.' Transfer', $permissionsArray)) {
                    // if (!in_array($creditType.' Transfer', $permissionsRes)) {
                        $permissions['isTransferable']=0;
                    }
                }
            }
            $data['permissions'] = $permissions;
            unset($result);

            $data['balance']        = Cash::getClientCacheBalance($clientID, $creditType);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00134"][$language] /* Successfully get detail */, 'data'=>$data);
        }

        public function getMemberLoginDetail($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $id             = $params['memberId'];
            $url            = $params['loginToMemberURL'];
            $tableName      = "client";
            $column         = Array(
                "id",
                "username",
                "dial_code",
                "phone"
            );

            $db->where("id", $id);

            $result = $db->getOne($tableName, $column);

            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00275"][$language] /* User is invalid */, 'data'=> "");


            $dataOut['id'] = $result['id'];
            // $dataOut['username'] = $result['dial_code'].$result['phone'];
            $dataOut['username'] = $result['username'];
            $dataOut['url'] = Setting::$configArray["loginToMemberURL"];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $dataOut);
        }

        public function getWhoIsOnlineList($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $adminTimeOut   = Setting::getAdminTimeOut();
            $memberTimeOut  = Setting::getMemberTimeout();
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $data           = array();
            $currentTime    = time();
            $tableName      = "admin";
            $column         = array(
                "username",
                "name",
                "last_login",
                "last_activity"
            );

            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            $adminLastActivity  = $currentTime - $memberTimeOut;
            $memberLastActivity = $currentTime - $adminTimeOut;
            $adminLeaderAry = Setting::getAdminLeaderAry();
            $condition = $adminLeaderAry ? "and id in (".implode(",", $adminLeaderAry).") " : "";

            $result = $db->rawQuery("select username, name, last_login, last_activity, 'client' as type from client where last_activity >= '".date("Y-m-d H:i:s", $memberLastActivity)."'".$condition." and type = 'Client' union all select username,name,last_login,last_activity, 'admin' as type from admin where last_activity >= '".date("Y-m-d H:i:s" , $adminLastActivity)."' and role_id not in (select id from roles where name = 'Master Admin') limit ".$limit[0].",".$limit[1]);
            if(!$result){
                $data['totalUserOnline'] = 0;
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00717"][$language] /* No User Is Online. */, 'data'=> $data);
            }

            unset($count);
            foreach($result as $row){
                    $client['username']     = $row['username'];
                    $client['fullname']     = $row['name'];
                    $client['last_login']   = strtotime($row['last_login']) >0 ? date($dateTimeFormat, strtotime($row['last_login'])) : "-";

                    $count++;
                    $onlineUserList[]       = $client;
            }

            //total client online
            $db->where('last_activity', date("Y-m-d H:i:s", $memberLastActivity),'>=');
            if($adminLeaderAry){
                $db->where('id', $adminLeaderAry, 'IN');
            }
            $db->where('type', 'Client');
            $totalClientOnline = $db->getValue('client','count(*)');

            //total admin online
            $sq = $db->subQuery();
            $sq->where('name','Master Admin');
            $sq->get('roles', null, 'id');
            $db->where('role_id', $sq, 'NOT IN');
            $db->where('last_activity', date("Y-m-d H:i:s", $adminLastActivity),'>=');
            $totalAdminOnline = $db->getValue('admin','count(*)');

            $totalRecord = $totalClientOnline + $totalAdminOnline;

            $data['onlineUserList']     = $onlineUserList;
            $data['totalUserOnline']    = $totalRecord ?  $totalRecord : 0;

            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            $data['totalPage']      = ceil($totalRecord/$limit[1]);
            $data['numRecord']      = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getClientRightsList($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName  = "mlm_client_rights";

            if(!$params['pageType']){
                $clientId   = trim($params['clientId']);
                $column     = array(
                    "id",
                    "name",
                    "parent_id",
                    "translation_code",
                    "(SELECT count(*) FROM mlm_client_blocked_rights WHERE client_id = " . $clientId . " AND rights_id = " . $tableName . ".id) AS blocked"
                );

                if (empty($clientId))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data'=> "");
            }else{
                $column     = array(
                    "id",
                    "name",
                    "parent_id",
                    "translation_code",
                );
            }
            
            if($params['pageType']!="batchUnlock"){
                $db->where('status','on');
                $db->orderBy("priority",'ASC');
                $result = $db->get($tableName, NULL, $column);
            }else{
                $db->where('parent_id','0','!=');
                $db->groupBy('parent_id');
                $parentId = $db->get($tableName, NULL, 'parent_id');

                foreach($parentId as $key => $id){
                    $parentIdU[] = $id['parent_id'];
                }

                if($parentIdU) $db->where('id', $parentIdU, 'NOT IN');
                $db->where('status','on');
                $db->orderBy('parent_id','ASC');
                $db->orderBy('priority','ASC');
                $result = $db->get($tableName, NULL, $column);

                array_push($column,'credit_id');

                $credit = $db->subQuery();
                $credit->where('credit_id','0','!=');
                $credit->get($tableName, NULL, 'credit_id');
                $db->where('id',$credit,'IN');
                $creditMenu = $db->map('id')->get('credit', NULL, 'id, translation_code');

                if($parentIdU){
                    $db->where('id',$parentIdU,'IN');
                    $db->where('parent_id','0');
                    $parentMainMenu = $db->get($tableName, NULL, $column);
                    foreach($parentMainMenu as $key=>$value){
                        $parentMainMenuU[] = $value['id'];
                        $parentName[$value['id']] = $value['credit_id'] != 0 ? $translations[$creditMenu[$value['credit_id']]][$language] : $value['name'];
                    }

                    $db->where('id',$parentIdU,'IN');
                    $db->where('parent_id',$parentIdU,'IN');
                    $parentSubMenu = $db->get($tableName, NULL, $column);
                    foreach($parentSubMenu as $key=>$value){
                        $parentSubId[] = $value['id'];
                        $parentSubMenuU[$value['id']] = $value['parent_id'];
                        $parentSubName[$value['id']] = $value['credit_id'] != 0 ? $translations[$creditMenu[$value['credit_id']]][$language] : $value['name'];
                    }
                }
            }

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00275"][$language], 'data'=> "");

            foreach ($result as &$resultRow) {
                if($params['pageType'] == "batchUnlock" && $parentIdU){
                    if(in_array($resultRow['parent_id'], $parentMainMenuU)){

                        $resultRow['display'] = $translations[$resultRow["translation_code"]][$language]?$parentName[$resultRow['parent_id']]." - ".$translations[$resultRow["translation_code"]][$language]:$resultRow['name'];

                    }elseif(in_array($resultRow['parent_id'], $parentSubId)){

                        $resultRow['display'] = $translations[$resultRow["translation_code"]][$language]?$parentName[$parentSubMenuU[$resultRow['parent_id']]]." - ".$parentSubName[$resultRow['parent_id']]." - ".$translations[$resultRow["translation_code"]][$language]:$resultRow['name']; 

                    }else{

                        $resultRow["display"] = $translations[$resultRow["translation_code"]][$language]?$translations[$resultRow["translation_code"]][$language]:$resultRow['name'];   
                    
                    }
                }else{
                    $resultRow["display"] = $translations[$resultRow["translation_code"]][$language]?$translations[$resultRow["translation_code"]][$language]:$resultRow['name'];
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $result);
        }

        public function lockAccount($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_client_blocked_rights";
            $clientID       = trim($params['clientId']);
            $blockedList    = $params['blockedList'];

            foreach($blockedList as $rights){

                if ($rights['blocked'] == "1"){

                    $db->where("id", $rights['rightsId']);
                    $rightName = $db->getValue("mlm_client_rights","name");

                    $db->where('rights_id', $rights['rightsId']);
                    $db->where('client_id', $clientID);
                    $check = $db->getOne('mlm_client_blocked_rights', 'id');

                    if(!$check){

                        $insertData = array(
                                "client_id" => $clientID,
                                "rights_id" => $rights['rightsId'],
                                "rights_name" => $rightName,
                                "created_at" => $db->now()
                            );
                            if (!$db->insert($tableName, $insertData))
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to update account rights", 'data' => "");

                    }
                    // $db->rawQuery("INSERT INTO " . $tableName . " (client_id, rights_id, created_at)
                    //                SELECT * FROM (SELECT " . $clientId . ", " . $rights['rightsId'] . ", NOW()) AS tmp
                    //                WHERE NOT EXISTS (
                    //                SELECT client_id FROM " . $tableName . " WHERE client_id = " . $clientId . " AND rights_id = " . $rights['rightsId'] . ")
                    //                LIMIT 1");
                }
                else if ($rights['blocked'] == "0"){

                    $db->where("client_id", $clientID);
                    $db->where("rights_id", $rights['rightsId']);

                    if (!$db->delete($tableName))
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00720"][$language], 'data'=> "");

                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> "");
        }

        public function leaderLockAccount($params) {

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $seeAll = $params['seeAll'];
            $lineType = $params['lineType'];
            $type = $params['type'];
            $filterOutLeaderUsername = $params['filterOutLeaderUsername'];

            // get rank display
            $rankRes = $db->get("rank", null, "id,translation_code");
            foreach ($rankRes as $rankRow) {
                $rankDisplay[$rankRow['id']] = $translations[$rankRow['translation_code']][$language];
            }

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            if($params['step'] == 1){
                $limit = General::getLimit($pageNumber);
            }
            $searchData = $params['searchData'];

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

                            $leaderUsernameSearch=$downlines;
                            // $db->where('id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                            case 'country':

                                $clientIDAry = $db->subQuery();
                                $clientIDAry->where('country_id',$dataValue);
                                $clientIDAry->get('client', null, 'id');
                                $db->where('id', $clientIDAry, "IN");

                                break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if ($leaderUsernameSearch){
                $db->where('id', $leaderUsernameSearch, "IN");
            }            
            if ($mainLeaderSearch){
                $db->where('id', $mainLeaderSearch, "IN");
            }
                $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
                $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
                $getRankID = "(SELECT value FROM client_setting WHERE client_setting.client_id=client.id AND name='leadershipRank') AS rank_id";
                $db->where('type', "Client");
                $db->where('disabled', "0");
                $copyDb = $db->copy();
                $totalRecords = $copyDb->getValue("client", "count(*)");

                if ($seeAll == "1") {
                    $limit = array(0, $totalRecords);
                }
                $db->orderBy("created_at", "DESC");
                $result = $db->get('client', $limit, 'id, member_id, username, name, ' . $getCountryName . ',' . $getSponsorUsername . ',' . $getRankID . ', disabled, suspended, freezed, last_login, last_login_ip, created_at');                

                if (empty($result))
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00105'][$language] /* No Results Found. */, 'data' => "");

                foreach ($result as $value) {

                    // if(in_array($filterDownlines,$value['id'])){

                    //     continue;
                    // }
                    $test[] = in_array($value['id'], $filterDownlines);



                    $client['clientID'] = $value['id'];
                    $client['member_id'] = $value['member_id'];
                    $client['username'] = $value['username'];
                    $client['name'] = $value['name'];
                    $client['sponsorUsername'] = $value['sponsor_username'] ? $value['sponsor_username'] : "-";
                    $mainLeaderUsername = Tree::getMainLeaderUsername($client);
                    $client['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                    $client['rankDisplay'] = $rankDisplay[$value['rank_id']] ? $rankDisplay[$value['rank_id']] : $rankDisplay[1];
                    $client['country'] = $value['country_name'] ? $value['country_name'] : "-";
                    $client['disabled'] = $value['disabled'] == 1 ? "Yes" : "No";
                    $client['suspended'] = $value['suspended'] == 1 ? "Yes" : "No";
                    $client['freezed'] = $value['freezed'] == 1 ? "Yes" : "No";
                    $client['lastLogin'] = $value['last_login'] == "0000-00-00 00:00:00" ? "-" : $value['last_login'];
                    $client['lastLoginIp'] = $value['last_login_ip'];
                    $client['createdAt'] = $value['created_at'];

                    $clientList[] = $client;
                }

            if($params['step'] == 1){
                $data['memberList'] = $clientList;
                $data['totalPage'] = ceil($totalRecords / $limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord'] = $limit[1];
                // $data['countryList'] = $db->get('country', null, 'id, name');

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);

            }else if($params['step'] == 2){


                foreach ($clientList as $key => $value) {
                    $client_id = $value['clientID'];

                    $insertParams = array('clientId' => $client_id,
                                          'blockedList' => $params['blockedList']);

                    $returnResult = Self::lockAccount($insertParams);
                    if($returnResult['status'] != 'ok'){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $returnResult['statusMsg'], 'data' => '');
                    }
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => '');
            }
        }

        public function getPaymentMethodList($params){
            
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $tableName      = "mlm_payment_method";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            // Get the limit.
            $limit              = General::getLimit($pageNumber);
            $searchData         = $params['inputData'];
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'paymentType':
                            if($dataValue != "all"){
                                $db->where('payment_type', $dataValue);
                            }
                            break;
                            
                        case 'status':
                            if($dataValue != ""){
                                $db->where('status', $dataValue);
                            }   
                            break;
                            
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("ID", "ASC");
            $result = $db->get($tableName, $limit, "ID, credit_type, status, min_percentage, max_percentage, payment_type");

            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if (!empty($result)) {
                foreach($result as $value) {
                    $temp['ID']             = $value['ID'];
                    $temp['paymentType']    = $value['payment_type'];
                    $temp['creditType']     = $value['credit_type'];
                    $temp['minPercentage']  = $value['min_percentage'];
                    $temp['maxPercentage']  = $value['max_percentage'];
                    $temp['status']         = $value['status'];
                    // $temp['createdAt']      = $value['created_at'];

                    $paymentSetting[] = $temp;
                }

                // $totalRecords = $copyDb->getValue($tableName, "count(*)");
                $data['settingList']  = $paymentSetting;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }

            else
            {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function getPaymentMethodDetails($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Payment Setting */, 'data'=> '');

            $db->where('ID', $id);
            $result = $db->getOne("mlm_payment_method", "ID, status, credit_type, min_percentage, max_percentage, payment_type"); //, role_id as roleID

            if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid Setting. */, 'data'=>"");

            foreach ($result as $key => $value) {
                $settingDetail[$key] = $value;
            }

            $data['settingDetail'] = $settingDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editPaymentMethod($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id       = trim($params['id']);
            $credit_type    = trim($params['credit_type']);
            $payment_type = trim($params['payment_type']);
            $min_percentage = trim($params['min_percentage']);
            $max_percentage   = trim($params['max_percentage']);
            $status   = trim($params['status']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00514"][$language] /* method ID does not exist */, 'data'=>"");

            if(strlen($credit_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00510"][$language] /* Credit cannot be empty */, 'data'=>"");

            if(strlen($payment_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00509"][$language] /* Payment Type cannot be empty */, 'data'=>"");

            if(strlen($min_percentage) == 0 || !is_numeric($min_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00511"][$language]/* Please Enter Min Percentage */, 'data'=>"");

            if(strlen($max_percentage) == 0 || !is_numeric($max_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00512"][$language] /* Please Enter Max Percentage */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00117"][$language] /* Please Select a Status */, 'data'=>"");

            $db->where('id', $id);
            $result = $db->getOne('mlm_payment_method');

            if (!empty($result)) {
                $fields    = array("credit_type", "status", "min_percentage", "max_percentage", "payment_type");
                $values    = array($credit_type, $status, $min_percentage, $max_percentage, $payment_type);

                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("mlm_payment_method", $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00179"][$language] /* Admin Profile Successfully Updated */, 'data' => "");

            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data'=>"");
            }
        }

        public function deletePaymentMethod($params){
            $db = MysqliDb::getInstance();

            $id = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => $translations["E00721"][$language], 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('mlm_payment_method', 1);
            
            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('mlm_payment_method');
                
                if($result) {
                    return Self::getPaymentMethodList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00723"][$language], 'data'=>"");
            }
        }

        public function getPaymentSettingDetails() {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $creditID = $db->subQuery();
            $creditID->where('name', 'isWallet');
            $creditID->where('value', 1);
            $creditID->getValue('credit_setting', 'credit_id', null);

            $db->where('id', $creditID, 'IN');
            $creditResult = $db->getValue("credit", "name", null);

            if(empty($creditResult)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");
            }

            $data["creditData"] = $creditResult;

            $db->where('payment', 1);
            $paymentTypeResult = $db->getValue("mlm_modules", "name", null);

            if(empty($paymentTypeResult)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00724"][$language] /* Invalid modules */, 'data' => "");
            }

            $data["paymentType"] = $paymentTypeResult;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addPaymentMethod($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $payment_type   = trim($params['paymentType']);
            $credit_type    = trim($params['creditType']);
            $min_percentage = trim($params['minPercentage']);
            $max_percentage = trim($params['maxPercentage']);
            $status         = trim($params['status']);

            if(strlen($payment_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00509"][$language] /* Payment type cannot be empty */, 'data'=>"");

            if(strlen($credit_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00510"][$language] /* Credit type cannot be empty */, 'data'=>"");

            if(strlen($min_percentage) == 0 || !is_numeric($min_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00511"][$language]/* Please Enter Min Percentage */, 'data'=>"");

            if(strlen($max_percentage) == 0 || !is_numeric($max_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00512"][$language] /* Please Enter Max Percentage */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Please Choose a Status */, 'data'=>"");

            $db->where('payment_type', $payment_type);
            $db->where('credit_type', $credit_type);
            
            $result = $db->get('mlm_payment_method');
            if (!empty($result)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Setting already exist */, 'data'=>"");
            }else{

                $fields = array("credit_type", "status", "min_percentage","max_percentage", "payment_type", "created_at");
                $values = array($credit_type, $status, $min_percentage, $max_percentage, $payment_type, date("Y-m-d H:i:s"));
                $arrayData = array_combine($fields, $values);
                try{
                    $result = $db->insert("mlm_payment_method", $arrayData);
                }
                catch (Exception $e) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00513"][$language] /* Failed to add new payment method */, 'data'=>"");
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00178"][$language] /* Successfully Added */, 'data'=>"");
            }
        }

        public function getWithdrawalUnreadCount($userID){
                $db = MysqliDb::getInstance();
                $language       = General::$currentLanguage;
                $translations   = General::$translations;


                $db->where("id", $userID);
                $withdrawalUnreadCount =  $db->getValue("admin", "withdrawal_record_notification");
                $data["withdrawalUnreadCount"] = $withdrawalUnreadCount;

                $inbox = Client::getInboxUnreadMessage();
                $data['inboxUnreadMessage'] = $inbox["data"]["inboxUnreadMessage"];

                $db->where("admin_id", $userID);
                $db->where("type", 'kyc');
                $kycUnreadCount =  $db->getValue("admin_notification", "notification_count");
                $data["kycUnreadCount"] = $kycUnreadCount > 0 ? $kycUnreadCount : 0;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
  
        public function adminChangePassword($params,$userID) {
            $db = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $currentPassword     = $params['currentPassword'];
            $newPassword         = $params['newPassword'];
            $newPasswordConfirm  = $params['newPasswordConfirm'];

            // get password length
            $maxPass  = Setting::$systemSetting['maxPasswordLength'];
            $minPass  = Setting::$systemSetting['minPasswordLength'];
            // Get password encryption type

            if (empty($userID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            $idName        = 'Password';
            $msgFieldB     = $translations["A00120"][$language];
            $msgFieldS     = $translations["A00120"][$language];
            $maxLength     = $maxPass;
            $minLenght     = $minPass;

            if (empty($newPasswordConfirm)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."ConfirmError",
                                            'msg' => $translations["E00445"][$language] /* Please re-type */.  $msgFieldS
                                        );

            } else {
                if ($newPasswordConfirm != $newPassword) 
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."ConfirmError",
                                                'msg' => $translations["E00446"][$language] /* Re-type new  */ . " " . $msgFieldS . " no match."
                                            );
            }            

            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = Setting::getEncryptedPassword($newPassword);
            // Retrieve the encrypted currentPassword based on settings
            $encryptedCurrentPassword = Setting::getEncryptedPassword($currentPassword);            

            $db->where('id', $userID);
            $result = $db->getOne('admin', 'password');
            if (empty($result)) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if (empty($currentPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "current".$idName."Error",
                                            'msg' => $translations["E00448"][$language] /* Please enter old  */ . " " . $msgFieldS
                                        );

            } else {

                // Check password encryption
                // We need to verify hash password by using this function
                if(!password_verify($currentPassword, $result['password'])) {
                    $errorFieldArr[] = array(
                                                'id'  => "current".$idName."Error",
                                                'msg' => $translations["E00449"][$language] /* Invalid  */ . " " . $msgFieldS
                                            );
                } 
            }

            if (empty($newPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $translations["E00450"][$language] /* Please enter new  */ . " " . $msgFieldS
                                        );
            } else {
                if (strlen($newPassword) < $minLenght || strlen($newPassword) > $maxLength) {
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."Error",
                                                'msg' => $msgFieldB . " " . $translations["E00451"][$language] /*  cannot be less than  */ . " " . $minLenght . " " . $translations["E00452"][$language] /*  or more than  */ . " " . $maxLength
                                            );

                }else if(!ctype_alnum($newPassword) || !preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $newPassword)){

                    $errorFieldArr[] = array(
                        'id'  => "new".$idName."Error",
                        'msg' => $translations["M00190"][$language]
                    );

                }else {

                    //checking new password no match with current password
                    // We need to verify hash password by using this function
                    if(password_verify($newPassword, $result['password'])) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => $translations["E00453"][$language] /* Please enter different  */ . " " . $msgFieldS
                                                );
                    }
                }
            }

            $data['field'] = $errorFieldArr;
            
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);

            $updateData = array('password' => $newEncryptedPassword);
            $db->where('id', $userID);
            $updateResult = $db->update('admin', $updateData);
            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00455"][$language] /* Update failed. */, 'data' => "");
        }

        public function getBlockMemberLoginByCountryIP($params,$columnName){
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $countryList = $db->get('country', null, "id,$columnName, name");

            foreach ($countryList as $key => $value) {
                if ($value[$columnName]==1){
                    $value['availabilityDisplay']='Blocked';    
                }else{
                    $value['availabilityDisplay']='Enabled';
                }
                $countryListOutput[]=$value;
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>'', 'data' => $countryListOutput);
        }

        public function setBlockMemberLoginByCountryIP($params,$columnName=''){
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $countryIDAry=$params['checkedIDs'];
            $status=$params['status'];

            $updateData = array(
                                    "$columnName" => $status
                                );
            $db->where('id',$countryIDAry,'IN');
            $res=$db->update('country',$updateData);

            if($res){
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["M01131"][$language], 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' =>$translations["M01441"][$language], 'data' => '');
            }
        }

        public function getBlockMemberLoginByCountryIPandTree($params){
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            
            $username=$params['username'];
            $db->where('username',$username);
            $clientID=$db->getValue('client','ID');
            if(!$clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00227"][$language], 'data' =>'' );
            }

            $countryList = $db->map('iso_code2')->ArrayBuilder()->get('country', null, "id,iso_code2,name,'Enabled' AS availabilityDisplay");
            $db->where('client_id',$clientID);
            $countryCodeList = $db->map('country_code')->ArrayBuilder()->get('client_country_ip_block', null, "country_code,blocked, (SELECT name FROM country WHERE country.iso_code2=client_country_ip_block.country_code ) AS name");

            foreach ($countryCodeList as $countryCode => $value) {
                if ($value['blocked']==1){
                    $countryList[$countryCode]['availabilityDisplay']='Blocked';    
                }else{
                    $countryList[$countryCode]['availabilityDisplay']='Enabled';
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>'', 'data' => $countryList);
        }
        
        public function setBlockMemberLoginByCountryIPandTree($params,$columnName=''){
            $db = MysqliDb::getInstance();

            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $username=$params['username'];
            $countryIDAry=$params['checkedIDs'];
            $status=$params['status'];

            //blocks country IP bases on username's downline

            $db->where('username',$username);
            $clientID=$db->getValue('client','ID');

            if(!$clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00227"][$language], 'data' =>'' );
            }
            $downlines = Tree::getSponsorTreeDownlines($clientID);
            

            $db->where('id',$countryIDAry,'IN');
            $countryCodeList = $db->get('country', null, "id,iso_code2, name");

            foreach ($countryCodeList as $key => $value) {


                $country_code=$value['iso_code2'];

                // return $downlines;
                foreach ($downlines as $key => $downlineID) {
                    //Should only work when both columns are a unique key
                    $db->rawQuery("INSERT INTO client_country_ip_block (client_id, country_code, blocked) VALUES($downlineID,'$country_code',$status) ON DUPLICATE KEY UPDATE blocked = VALUES(blocked)");
                }

            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["M01131"][$language], 'data' => '');
        }

        public function getCreditType($params,$setting) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $isShowMainWallet = $params['isShowMainWallet'];

            if($setting){
	            $db->where('name',$setting);
	            $db->where('value',0,"!=");
	            $res = $db->get('credit_setting',null,'credit_id');
	            $creditIDArray = array();
	            foreach ($res as $row) {
	                $creditIDArr[] = $row['credit_id'];
	            }
            }

            $db->where('name','isWallet');
            $db->where('value',1);
            $res = $db->get('credit_setting',null,'credit_id');
            $creditIDArray = array();
            foreach ($res as $row) {
            	if($creditIDArr && in_array($row["credit_id"], $creditIDArr)){
        			$creditIDArray[] = $row['credit_id'];
            	}else if(!$creditIDArr){
            		$creditIDArray[] = $row['credit_id'];
            	}
                
            }

            $creditArray = array();

            if ($isShowMainWallet) {
                $creditArray['cashCredit'] = $translations['M01496'][$language]; // Add RMB wallet
            }

            if (count($creditIDArray) > 0) {
                $db->where('id',$creditIDArray,'IN');
                $res = $db->get('credit',null,'name,admin_translation_code');

                foreach ($res as $row) {
                    $creditArray[$row['name']] = $translations[$row['admin_translation_code']][$language];
                }
            }

            $data = array(
                'creditArray' => $creditArray
            );

            return array('status'=>'ok','code'=>0,'statusMsg'=>'','data'=>$data);
        }

        public function getPagePermission($params,$userID){
        	$db = MysqliDb::getInstance();

        	$filePath = $params['filePath'];

            $db->where('name','Master Admin');
            $masterAdminRoleId = $db->getValue('roles','id');

        	$db->where("id",$userID);
        	$roleID = $db->getValue("admin","role_id");

        	$db->where("file_path",$filePath);
            $copyDb = $db->copy();
        	$premissionIDArr = $db->map('id')->get("permissions",NULL,"id");
            $masterAdminPremissionIDArr = $copyDb->map('id')->get("permissions",NULL,"id,name");

            if($roleID != $masterAdminRoleId){
            	$db->where("role_id",$roleID);
            	$db->where("permission_id",$premissionIDArr,"IN");
            	$db->where("disabled",0);
            	$res = $db->map('id')->get("roles_permission",NULL,"id,(SELECT name FROM permissions where id = permission_id) AS name");
            }else{
                $res = $masterAdminPremissionIDArr;
            }

        	$data['permission'] = $res;
    		return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);        	
        }

        public function getAgentID($adminID){
            $db = MysqliDb::getInstance();

            $db->where("admin_id", $adminID);
            $agentID = $db->getValue("admin_agent", "leader_id");

            return $agentID;
        }

        public function updateRebatePercentage($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $rebatePercentage = $params['rebatePercentage'];
            $monthPeriod     = $params['monthPeriod'];
            $activeDate      = trim($params['activeDate']);
            $currentDate     = date("Y-m-01");

            if(empty($monthPeriod)){
                $errorFieldArr[] = array(
                                                    'id'  => "activeDateError",
                                                    'msg' => $translations['E00156'][$language]
                                                );
            }

            if(empty($activeDate)){
                $errorFieldArr[] = array(
                                                    'id'  => "activeDateError",
                                                    'msg' => $translations['E00156'][$language]
                                                );
            }

            $activeDate = strtotime($monthPeriod."-".$activeDate);
            $activeDate = date('Y-m-d',$activeDate);

            $bonusID = $db->subQuery();
            $bonusID->where('name', 'rebateBonus');
            $bonusID->get('mlm_bonus', null, 'id');
            $db->where('bonus_id', $bonusID, 'in');
            $db->where('name', 'setRebatePercentage');
            $db->where('type', 'Rebate Type');
            $percentageRes = $db->getOne('mlm_bonus_setting','value,reference');
            $minPercentage = $percentageRes['value'];
            $maxPercentage = $percentageRes['reference'];

            if(!is_numeric($rebatePercentage) || empty($rebatePercentage)){
                $errorFieldArr[] = array(
                                                    'id'  => "rebatePercentageError",
                                                    'msg' => $translations['E00125'][$language]
                                                );
            }

            if($rebatePercentage < $minPercentage || $rebatePercentage > $maxPercentage){

                $errorMsg = str_replace(array('%%min%%','%%max%%'), array($minPercentage,$maxPercentage), 'Rebate Percentage cannot less than %%min%% and cannot more than %%max%%.');

                $errorFieldArr[] = array(
                                                    'id'  => "rebatePercentageError",
                                                    'msg' => $errorMsg
                                                );
            }

            if(strtotime($activeDate) < strtotime($currentDate)){
                $errorFieldArr[] = array(
                                                    'id'  => "activeDateError",
                                                    'msg' => $translations['E00156'][$language]
                                                );
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);

            $insertData = array(
                "unit_price"    => Setting::setDecimal($rebatePercentage,2),
                "type"          => "Rebate Percentage",
                "creator_id"    => $db->userID,
                "creator_type"  => $db->userType,
                "created_at"    => $db->now(),
                "actived_date"  => $activeDate,
            );

            $db->insert('mlm_unit_price',$insertData);

            return array('status'=>'ok','code'=>0,'statusMsg'=>'','data'=>$data);
        }

        public function getRebatePercentageList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            $searchData      = $params['inputData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            //Get the limit.
            $limit           = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];


            $column         = array(
                "id",
                "unit_price as percentage",
                "creator_id",
                "(SELECT username From admin where `admin`.`id` = creator_id) AS creator_username",
                "created_at",
                "actived_date",
            );
            $db->orderBy('created_at','DESC');
            $copyDb = $db->copy();
            $rebatePercentageList = $db->get("mlm_unit_price",$limit,$column);
            foreach ($rebatePercentageList as &$row) {

                $row['created_at']    = $row['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($row['created_at'])) : "-";
                $row['actived_date']    = $row['actived_date'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($row['actived_date'])) : "-";
                $row['percentage'] = Setting::setDecimal($row['percentage'],2);
            }

            $totalRecord                    = $copyDb->getValue("mlm_unit_price", "count(*)");
            $data['rebatePercentageList']   = $rebatePercentageList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00616"][$language], 'data' => $data);
        }

        public function adminSetAutoWithdrawal($params,$site){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientID      = trim($params['clientID']);
            $withdrawalType= trim($params['withdrawalType']);
            $creditType    = trim($params['creditType']);
            $walletAddress = trim($params['walletAddress']);
            $accountHolder = trim($params['accountHolderName']);
            $bankID        = trim($params['bankID']);
            $accountNo     = trim($params['accountNo']);
            $province      = trim($params['province']);
            $branch        = trim($params['branch']);
            $status        = trim($params['status']);
            $removeSetting  = trim($params['removeSetting']);

            if (empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00461"][$language] /* Member not found. */, 'data'=> "");

            if ($status != 0 && $status != 1)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid Status */, 'data'=> "");


            // if($status == 1 || $removeSetting){
            	// set previous active to inactive
                $db->where('client_id',$clientID);
                $db->where('status','Active');
                $check=$db->get('mlm_client_wallet_address',null,'id');
                if ($check){
                    foreach ($check as $checkKey => $checkValue) {
                        $updateData = array(
                            "status" => "Inactive",
                        );
                        $db->where('id',$checkValue['id']);
                        $db->update('mlm_client_wallet_address', $updateData);
                    }
                }
                // set previous active to inactive
                $db->where('client_id',$clientID);
                $db->where('status','Active');
                $check=$db->get('mlm_client_bank',null,'id');
                if ($check){
                    foreach ($check as $checkKey => $checkValue) {
                        $updateData = array(
                            "status" => "Inactive",
                        );
                        $db->where('id',$checkValue['id']);
                        $db->update('mlm_client_bank', $updateData);
                    }
                }

            // }

            if($removeSetting){
            	$db->where('client_id',$clientID);
            	$db->where("name","isAutoWithdrawal");
            	$id = $db->getValue("client_setting","id");

            	if(!$id) return array('status' => "error", 'code' => 1, 'statusMsg' => "No record remove" , 'data' => "");

            	$db->where("id",$id);
            	$db->delete("client_setting");

            	return array('status' => "ok", 'code' => 0, 'statusMsg' => "Withdrawal Type is remove", 'data' => "");
            }

            if($withdrawalType == 'crypto'){

                // $acceptCoinType=json_decode(Setting::$systemSetting['cryptoCoinType']);
                $acceptCoinType = Client::getCryptoCredit(true, false);
                
                if(!trim($creditType) || !array_key_exists($creditType, $acceptCoinType)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'creditTypeError',
                                                'msg' => $translations["E00747"][$language]
                                            );
                }

                if(!$walletAddress || $walletAddress == ""){
                    $errorFieldArr[] = array(
                                                'id'  => 'walletAddressError',
                                                'msg' => $translations["M01941"][$language]/* Wallet Address cannot be empty. */
                                            );
                }
                else if(strlen($walletAddress)<30){
                    $errorFieldArr[] = array(
                                                'id'  => 'walletAddressError',
                                                'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                                            );
                }

                if($errorFieldArr){
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
                }

                $insertData = array(
                    "client_id"      => $clientID,
                    "credit_type"      => $creditType,
                    "info"      => $walletAddress,
                    "created_at"      => date("Y-m-d H:i:s"),
                    "status" => 'Active'
                );
                $insertClientWalletResult = $db->insert("mlm_client_wallet_address", $insertData);

                $reference = $creditType;
                
                // Failed to insert client bank account
                if (!$insertClientWalletResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00817"][$language] /* Failed to add wallet address. */, 'data' => "");

            }else if($withdrawalType == 'bank' ){

                if (empty($accountHolder))
                    $errorFieldArr[] = array(
                                                'id'  => "accHolderNameError",
                                                'msg' => $translations["E00462"][$language] /* Please enter account holder name. */
                                            );
                if (empty($bankID))
                    $errorFieldArr[] = array(
                                                'id'  => "bankTypeError",
                                                'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                            );
                if (empty($accountNo))
                    $errorFieldArr[] = array(
                                                'id'  => "accountNoError",
                                                'msg' => $translations["E00464"][$language] /* Please enter account number. */
                                            );
                if (empty($province))
                    $errorFieldArr[] = array(
                                                'id'  => "provinceError",
                                                'msg' => $translations["E00465"][$language] /* Please enter province. */
                                            );
                if (empty($branch))
                    $errorFieldArr[] = array(
                                                'id'  => "branchError",
                                                'msg' => $translations["E00466"][$language] /* Please enter branch. */
                                            );

                $data['field'] = $errorFieldArr;
                if($errorFieldArr)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00469"][$language] /* Data does not meet requirements. */, 'data' => $data);

                $insertClientBankData = array(
                                            "client_id"      => $clientID,
                                            "bank_id"        => $bankID,
                                            "account_no"     => $accountNo,
                                            "account_holder" => $accountHolder,
                                            "created_at"     => $db->now(),
                                            "status"         => 'Active',
                                            "province"       => $province,
                                            "branch"         => $branch

                                         );

                $insertClientBankResult  = $db->insert('mlm_client_bank', $insertClientBankData);

                    // Failed to insert client bank account
                    if (!$insertClientBankResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00470"][$language] /* Failed to add bank account. */, 'data' => "");

                
                $reference = $bankID;
            }

            // $isFirstTimeAddWithdrawalOption = $this->checkIsFirstTimeAddWithdrawalOption($clientID);

            $rebuildParams["onOffOption"] = $status;
            $rebuildParams["withdrawalOption"] = $withdrawalType;
            $rebuildParams["reference"] = $reference;
            Self::setAutoWithdrawal($rebuildParams,$site,$clientID);


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00599"][$language], 'data' => "");

        }

        function setAutoWithdrawal($params,$site,$userID){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $onOffValue = $params["onOffOption"]; // (0/1)
            $type       = $params["withdrawalOption"]; // (bank/crypto)
            $reference  = $params["reference"];

            if($site=="Member"){

                if($onOffValue == "0"){
                    $errorFieldArr[] = array(
                                        'id'  => 'onOffOptionError',
                                        'msg' => 'Cannot Off Auto Withdrawal.' /* Invalid value. */
                                    );
                }
            }

            $clientID = $userID;
                
            /* check user input */
            if($onOffValue != "0" && $onOffValue != "1")
            {
                $errorFieldArr[] = array(
                                        'id'  => 'onOffOptionError',
                                        'msg' => $translations["E00125"][$language] /* Invalid value. */
                                    );       
            }
            if($onOffValue == "1"){
                if(empty($type)) {
                $errorFieldArr[] = array(
                                        'id'  => 'withdrawalOptionError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
                }
                if(empty($reference)) {
                    $errorFieldArr[] = array(
                                            'id'  => 'referenceError',
                                            'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                        );
                }
            }
            // else {
            //     $type = "";
            //     $reference = "";
            // }
            
            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00264"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            if($onOffValue == "1"){
                /* deeper input checking */
                $db->where("client_id", $clientID);
                $db->where("status", "Active");

                if($type=="crypto"){
                    $tableName = "mlm_client_wallet_address";
                    $columnName = "credit_type";
                }
                else { //if bank
                    $tableName = "mlm_client_bank";
                    $columnName = "bank_id";
                }

                $db->where($columnName, $reference);
                $deepCheckRes = $db->getValue($tableName, "id");

                if(!$deepCheckRes){
                    $errorFieldArr[] = array(
                                                'id'  => 'referenceError',
                                                'msg' => $translations["E00130"][$language] /* Data does not meet requirements */
                                            );
                }
                if($errorFieldArr) {
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00264"][$language] /* Data does not meet the requirements */, 'data' => $data);
                }
            }
            

            $clientSettingName = "isAutoWithdrawal";
            $clientSettingData = array(
                "name"      => $clientSettingName, 
                "value"     => $onOffValue, 
                "type"      => $type, 
                "reference" => $reference,
                "client_id" => $clientID
            );

            // insert activity log before insert into client_setting
            $titleCode    = 'T00003';
            $activityCode = 'L00026';
            $activityType = 'Set Auto Withdrawal';

            $activityRes = Activity::insertActivity($activityType, $titleCode, $activityCode, $clientSettingData, $clientID);

            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00269"][$language] /* Failed to insert activity */, 'data'=> "");

            $db->where('client_id', $clientID);
            $db->where('name', $clientSettingName);
            $copyDb = $db->copy();
            $check = $db->getValue("client_setting", "id");

            if($check){
                /* if have record */
                if($onOffValue == 0 && !$type) $clientSettingData = array("value"     => $onOffValue);
                $copyDb->where("id", $check);
                $result = $copyDb->update('client_setting', $clientSettingData);
            }
            else{
                /* if no record */
                $db->insert("client_setting", $clientSettingData);
            }

            $data['setDefaultWithdrawal'] = $type;

            return array("status" => "ok", "code" => 0, "statusMsg" => $translations["E00708"][$language], "data" => $data);
        }

        function getAutoWithdrawalData($params,$userID,$site){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $userID;
            if($site == 'Admin') $clientID = $params['clientID'];

            $db->where("client_id",$clientID);
            $db->where("status", "Active");
            $copyDb = $db->copy();

            /* get bank data */
            $bankData = $db->get("mlm_client_bank",NULL, "
                bank_id,
                account_no,
                account_holder,
                province,
                branch,
                (SELECT country_id FROM mlm_bank WHERE id = bank_id) AS country_id,
                (SELECT name FROM mlm_bank WHERE id = bank_id) AS bank_name,
                (SELECT translation_code FROM mlm_bank WHERE id = bank_id) AS bank_display
                ");
            if(!empty($bankData)) {
                foreach ($bankData as $key => &$bankDataRow) {
                    $bankDataRow["bank_display"] = $translations[$bankDataRow["bank_display"]][$language];
                    $infoDetails[$bankDataRow['bank_id']] = $bankDataRow["bank_display"];
                }
                $data['bankData'] = $bankData;
            }
            /* END get bank data */

            /* get wallet data */
            $cryptoCreditListDisplay = Client::getCryptoCredit(true);
            $walletData = $copyDb->get("mlm_client_wallet_address",NULL, "
                credit_type,
                info
                ");
            if(!empty($walletData)){
                foreach ($walletData as $key => &$walletDataRow) {
                    $infoDetails[$walletDataRow['credit_type']] = $walletDataRow['info'];

                    $walletDataRow["creditTypeDisplay"] = $cryptoCreditListDisplay[$walletDataRow['credit_type']];
                }
            }

            $db->where('name','isAutoWithdrawal');
            $db->where('client_id',$clientID);
            $autoWithdrawalSetting = $db->getOne('client_setting','value,type,reference');

            if($autoWithdrawalSetting['value'] == 1){
                $onOffSetting = $translations['M01160'][$language];
                $onOffValue = $autoWithdrawalSetting['value'];
            }else{
                $onOffSetting = $translations['M01161'][$language];
                $onOffValue = $autoWithdrawalSetting['value'];
            }

            if($autoWithdrawalSetting['type'] == 'bank'){
                $withdrawalTypeDisplay = $translations['M00467'][$language];
                $withdrawalType = $autoWithdrawalSetting['type'];

            }else if($autoWithdrawalSetting['type'] == 'crypto'){
                $withdrawalTypeDisplay = $translations['M02069'][$language];
                $withdrawalType = $autoWithdrawalSetting['type'];
            }

            $withdrawalInfo = $infoDetails[$autoWithdrawalSetting['reference']];

            $withdrawalSetting['onOffSetting'] = $onOffSetting;
            $withdrawalSetting['withdrawalType'] = $withdrawalType?$withdrawalType:"-";
            $withdrawalSetting['withdrawalTypeDisplay'] = $withdrawalTypeDisplay?$withdrawalTypeDisplay:"-";
            $withdrawalSetting['withdrawalInfo'] = $withdrawalInfo?$withdrawalInfo:"-";
            $withdrawalSetting['settingReference'] = $autoWithdrawalSetting['reference']?$autoWithdrawalSetting['reference']:"-";
            $withdrawalSetting['onOffValue'] = $onOffValue?$onOffValue:0;

            // $getAutoWithdrawalData['']
            /* END get wallet data */

            $data['withdrawalSetting'] = $withdrawalSetting;
            $data['bankData'] = $bankData;
            $data['walletData'] = $walletData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getSenderAddressListing($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $walletProviderSearchType  = $params["walletProviderSearchType"];
            if (!$seeAll) $limit = General::getLimit($pageNumber);

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'walletProvider':
                            if ($walletProviderSearchType == "match") {
                                $db->where('wallet_provider', $dataValue);
                            } elseif ($walletProviderSearchType == "like") {
                                $db->where('wallet_provider', $dataValue . "%", "LIKE");
                            }
                            break;
                            
                        case 'senderAddress':
                            $db->where('info', $dataValue);
                            break;

                        default :
                            break;    
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('type', 'deposit');
            $copyDb = $db->copy();
            $db->orderBy('setOn', 'DESC');
            $res = $db->get('mlm_client_wallet_address', $limit, 'client_id, info, wallet_provider, created_at AS setOn');

            if (!$res) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach ($res as $row) {
                $clientIDAry[$row['client_id']] = $row['client_id'];
            }

            if ($clientIDAry) {
                $db->where('id', $clientIDAry, 'IN');
                $clientAry = $db->map('id')->get('client', NULL, 'id, username, member_id, phone, email, last_login_ip, created_at');
            }

            foreach ($res as $value) {
                unset($senderAddress);
                $senderAddress['signUpDate'] = $clientAry[$value['client_id']]['created_at'] ? date($dateTimeFormat, strtotime($clientAry[$value['client_id']]['created_at'])) : '-';
                $senderAddress['memberID']   = $clientAry[$value['client_id']]['member_id'] ? : '-';
                $senderAddress['username']   = $clientAry[$value['client_id']]['username'] ? : '-';
                $senderAddress['phone']      = $clientAry[$value['client_id']]['phone'] ? : '-';
                $senderAddress['email']      = $clientAry[$value['client_id']]['email'] ? : '-';
                $senderAddress['lastLoginIp']    = $clientAry[$value['client_id']]['last_login_ip'] ? : '-';
                $senderAddress['senderAddress']  = $value['info'] ? : '-';
                $senderAddress['walletProvider'] = $value['wallet_provider'] ? : '-';
                $senderAddress['setOn'] = $value['setOn'] ? date($dateTimeFormat, strtotime($value['setOn'])) : '-';

                $senderAddressList[] = $senderAddress;
            }

            $totalRecord = $copyDb->getValue("mlm_client_wallet_address", "count(id)");

            $data['senderAddressList'] = $senderAddressList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        public function addPrivateGameStg($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $sellingQty = trim($params['sellingQty']);
            $sellingPrice = trim($params['sellingPrice']);
            $sellingStartTS = trim($params['sellingStartTS']);
            $sellingEndTS = trim($params['sellingEndTS']);
            $voucherExpiredTS = trim($params['voucherExpiredTS']);
            $voucherID = trim($params['voucherID']);
            $packageName = trim($params['packageName']);
            $gameRoundArr = $params['gameRoundArr'];
            $category = "private";
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Self::privateGmStgVerification($params,"add");
            if($result['status'] == 'error'){
                return $result;
            }
            $gameArr = $result['data']['gameArr'];
            $fristGameTS = $result['data']['fristGameTS'];

            //Default Code
            $newCode = 1000;

            $db->orderBy('code','DESC');
            $lastCode = $db->getValue('private_game','code');
            if($lastCode) $newCode = $lastCode+1;

            foreach ($gameArr as $gameTimeTS => $insertRow) {
                unset($insertData);
                $batchID = $db->getNewID();
                $insertData = array(
                    "product_category" => $category,
                    "code"       => $newCode,
                    "name"       => $packageName,
                    "winner" => $insertRow['winner'],
                    "status"     => "await",
                    "start_time" => date("Y-m-d H:i:s",$gameTimeTS),
                    "batch_id"  => $batchID,
                    "created_at" => $dateTime,
                );
                $db->insert('private_game',$insertData);

                $roundData[date("Y-m-d H:i:s",$gameTimeTS)]['winner'] = $insertRow['winner'];
            }

            $gameData['sellingTime']['value'] = date("Y-m-d H:i:s",$sellingStartTS);
            $gameData['sellingTime']['reference'] = date("Y-m-d H:i:s",$sellingEndTS);
            $gameData['sellingPrice'] = $sellingPrice;
            $gameData['sellingQty'] = $sellingQty;
            $gameData['voucherStg']['value'] = $voucherID;
            $gameData['voucherStg']['reference'] = date("Y-m-d H:i:s",$voucherExpiredTS);

            foreach ($gameData as $settingName => $settingValue) {
                unset($insertData);
                switch ($settingName) {
                    case 'voucherStg':
                    case 'sellingTime':
                        $insertData = array(
                            "private_game_code" => $newCode,
                            "name"              => $settingName,
                            "value"             => $settingValue['value'],
                            "reference"         => $settingValue['reference'],
                            "created_at"        => $dateTime
                        );
                        break;
                    
                    default:
                        $insertData = array(
                            "private_game_code" => $newCode,
                            "name"              => $settingName,
                            "value"             => $settingValue,
                            "created_at"        => $dateTime
                        );
                        break;
                }
                $db->insert('private_game_setting',$insertData);
            }
            $gameData['roundData'] = $roundData;

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }

            // insert activity log
            $activityData = array('admin' => $adminName,"gameStartDate"=>date('Y-m-d',$fristGameTS),"gameData"=>$gameData);

            $activityRes = Activity::insertActivity('Set Private Game Setting', 'T00050', 'L00070', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function getPrivateGameList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            
            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if (!$seeAll) $limit = General::getLimit($pageNumber);

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {

                        default :
                            break;    
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('status','disabled',"!=");
            $db->groupBy('code');
            $copyDb = $db->copy();
            $db->orderBy('created_at', 'DESC');
            $res = $db->map('code')->get('private_game', $limit, 'code');
            if (!$res) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $db->where('status','disabled',"!=");
            $db->where('code',$res,"IN");
            $gameDetialRes = $db->get('private_game',null,'id,name,winner,code,status,start_time AS gameDate,updated_at,updater_id,created_at');
            foreach ($gameDetialRes as $gameDetialRow) {
                $updateIDArr[$gameDetialRow['updater_id']] = $gameDetialRow['updater_id'];
            }

            //Get Updater username
            if($updateIDArr){
                $db->where('id',$updateIDArr,"IN");
                $updaterData = $db->map('id')->get('admin',null,'id,username');
            }

            foreach ($gameDetialRes as $gameDetialRow) {
                $code = $gameDetialRow['code'];
                $gameData[$code]['totalWinner'] += $gameDetialRow['winner'];
                $gameData[$code]['name'] = $gameDetialRow['name'];
                $gameData[$code]['code'] = $code;

                $gameDetialRow['statusDisplay'] = General::getTranslationByName($gameDetialRow['status']);
                $gameDetialRow['updater'] = $updaterData[$gameDetialRow['updater_id']]?$updaterData[$gameDetialRow['updater_id']]:"-";
                unset($gameDetialRow['updater_id'],$gameDetialRow['code']);

                $gameData[$code]['gameArr'][] = $gameDetialRow;

                if($gameData[$code]['status'] == 'await') continue;
                $gameData[$code]['status'] = $gameDetialRow['status'];
                $gameData[$code]['statusDisplay'] = General::getTranslationByName($gameDetialRow['status']);
            }

            foreach ($res as $gameDate) {
                $gameDataArr[] = $gameData[$gameDate];
            }

            $totalRecord = $copyDb->get("private_game", null,"id");
            $totalRecord = COUNT($totalRecord);

            $data['gameDataArr'] = $gameDataArr;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        public function getPrivateGameDetail($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;

            $code = trim($params['code']);
            

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $db->where('status',"disabled","!=");
            $db->where('code',$code);
            $res = $db->get('private_game', null,'id as packageID,name,start_time,winner');
            if (!$res) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $db->where('private_game_code',$code);
            $pvtStgRes = $db->get('private_game_setting',null,'private_game_code,name,value,reference');
            foreach ($pvtStgRes as $pvtStgRow) {
                switch ($pvtStgRow['name']) {
                    case 'sellingTime':
                        $pvtGameDetail["sellingStart"] = date("d/m/Y",strtotime($pvtStgRow['value']));
                        $pvtGameDetail["sellingEnd"] = date("d/m/Y",strtotime($pvtStgRow['reference']));
                        break;

                    case 'voucherStg':
                        $pvtGameDetail["voucherID"] = $pvtStgRow['value'];
                        $pvtGameDetail["voucherExpired"] = date("d/m/Y",strtotime($pvtStgRow['reference']));
                        break;
                    
                    default:
                        $pvtGameDetail[$pvtStgRow['name']] = $pvtStgRow['value'];
                        break;
                }
            }

            foreach ($res as &$row) {
                $row['start_time'] = date("d/m/Y",strtotime($row['start_time']));
            }

            $pvtGameDetail['packageName'] = $res[0]['name'];
            $pvtGameDetail['gameRoundArr'] = $res;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $pvtGameDetail);
        }

        public function editPrivateGameStg($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $sellingQty = trim($params['sellingQty']);
            $sellingPrice = trim($params['sellingPrice']);
            $sellingStartTS = trim($params['sellingStartTS']);
            $sellingEndTS = trim($params['sellingEndTS']);
            $voucherExpiredTS = trim($params['voucherExpiredTS']);
            $voucherID = trim($params['voucherID']);
            $packageName = trim($params['packageName']);
            $gameCode   = trim($params['gameCode']);
            $gameRoundArr = $params['gameRoundArr'];
            $category = "private";
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Self::privateGmStgVerification($params,"edit");
            if($result['status'] == 'error'){
                return $result;
            }
            $productID = $result['data']['productID'];
            $gameArr = $result['data']['gameArr'];

            $db->where('code',$gameCode);
            $db->where('status','await');
            $gameRes = $db->map('id')->get('private_game',null,'id,winner,status,name,start_time');
            foreach ($gameArr as $gameTimeTS => $insertRow) {
                $packageID = $insertRow['packageID'];
                if($packageID){
                    if($gameRes[$packageID]['status'] != 'await' || !$gameRes[$packageID]){
                        unset($gameRes[$packageID]);
                        unset($gameArr[$gameTimeTS]);
                        continue;
                    }

                    if($insertRow['winner'] != $gameRes[$packageID]['winner']){
                        $editData[$packageID]['winner']['old'] = $gameRes[$packageID]['winner'];
                        $editData[$packageID]['winner']['new'] = $insertRow['winner'];
                    }

                    if($packageName != $gameRes[$packageID]['name']){
                        $editData[$packageID]['name']['old'] = $gameRes[$packageID]['name'];
                        $editData[$packageID]['name']['new'] = $packageName;
                    }

                    if($gameTimeTS != strtotime($gameRes[$packageID]['start_time'])){
                        $editData[$packageID]['start_time']['old'] = $gameRes[$packageID]['start_time'];
                        $editData[$packageID]['start_time']['new'] = date('Y-m-d H:i:s',$gameTimeTS);
                    }

                    unset($gameRes[$packageID]);
                    unset($gameArr[$gameTimeTS]);
                }
            }

            // Update Edit Data
            foreach ($editData as $editID => $editRow) {
                unset($updateData);
                foreach ($editRow as $column => $columnVal) {
                    $updateData[$column] = $columnVal['new'];
                }
                $updateData['updater_id'] = $userID;
                $updateData['updated_at'] = $dateTime;
                $db->where('id',$editID);
                $db->update('private_game',$updateData);
            }

            if($gameRes){
                $db->where('id',array_keys($gameRes),"IN");
                $db->update('private_game',array("status"=>"disabled","updater_id"=>$userID,"updated_at"=>$dateTime));
            }

            //Insert New Game Data
            foreach ($gameArr as $gameTimeTS => $insertRow) {
                unset($insertData);
                $batchID = $db->getNewID();
                $insertData = array(
                    "product_category" => $category,
                    "code"       => $gameCode,
                    "name"       => $packageName,
                    "winner" => $insertRow['winner'],
                    "status"     => "await",
                    "start_time" => date("Y-m-d H:i:s",$gameTimeTS),
                    "batch_id"  => $batchID,
                    "created_at" => $dateTime,
                    "updated_at" => $dateTime,
                    "updater_id" => $userID,
                );
                $db->insert('private_game',$insertData);

                $roundData[date("Y-m-d H:i:s",$gameTimeTS)]['winner'] = $insertRow['winner'];
            }

            $db->where('private_game_code',$gameCode);
            $privateGameStg = $db->map('name')->get('private_game_setting',null,'name,private_game_code,value,reference');

            $gameData['sellingTime']['value'] = date("Y-m-d H:i:s",$sellingStartTS);
            $gameData['sellingTime']['reference'] = date("Y-m-d H:i:s",$sellingEndTS);
            $gameData['sellingPrice'] = $sellingPrice;
            $gameData['sellingQty'] = $sellingQty;
            $gameData['voucherStg']['value'] = $voucherID;
            $gameData['voucherStg']['reference'] = date("Y-m-d H:i:s",$voucherExpiredTS);

            //Update Game Setting
            foreach ($gameData as $settingName => $settingRow) {
                unset($updateData);
                switch ($settingName) {
                    case 'voucherStg':
                    case 'sellingTime':
                        if($settingRow['value'] != $privateGameStg[$settingName]['value']){
                            $editStgData[$settingName]['value']['old'] = $privateGameStg[$settingName]['value'];
                            $editStgData[$settingName]['value']['new'] = $settingRow['value'];
                            $updateData['value'] = $settingRow['value'];
                        }

                        if($settingRow['reference'] != $privateGameStg[$settingName]['reference']){
                            $editStgData[$settingName]['reference']['old'] = $privateGameStg[$settingName]['reference'];
                            $editStgData[$settingName]['reference']['new'] = $settingRow['reference'];
                            $updateData['reference'] = $settingRow['reference'];
                        }
                        break;
                    
                    default:
                        if($settingRow != $privateGameStg[$settingName]['value']){
                            $editStgData[$settingName]['value']['old'] = $privateGameStg[$settingName]['value'];
                            $editStgData[$settingName]['value']['new'] = $settingRow;
                            $updateData['value'] = $settingRow;
                        }
                        break;
                }
                if($updateData){
                    $db->where('private_game_code',$gameCode);
                    $db->where('name',$settingName);
                    $db->update('private_game_setting',$updateData);
                }
            }
            $gameDataLog['editData'] = $editData;
            $gameDataLog['rmData'] = $gameRes;
            $gameDataLog['newData'] = $gameArr;
            $gameDataLog['editStgData'] = $editStgData;

            $gameData['roundData'] = $roundData;

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }

            // insert activity log
            $activityData = array('admin' => $adminName,"gameCode"=>$gameCode,"gameData"=>$gameData,"gameDataLog"=>$gameDataLog);

            $activityRes = Activity::insertActivity('Edit Private Game Setting', 'T00051', 'L00071', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function privateGmStgVerification($params,$type = "add"){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $sellingQty = trim($params['sellingQty']);
            $sellingPrice = trim($params['sellingPrice']);
            $sellingStartTS = trim($params['sellingStartTS']);
            $sellingEndTS = trim($params['sellingEndTS']);
            $voucherExpiredTS = trim($params['voucherExpiredTS']);
            $voucherID = trim($params['voucherID']);
            $packageName = trim($params['packageName']);
            $gameCode   = trim($params['gameCode']);
            $gameRoundArr = $params['gameRoundArr'];
            $dateTime = date('Y-m-d H:i:s');
            $category = "private";

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $db->where('active_at',$dateTime,"<=");
            $db->where('status','Active');
            $db->where('ref_id',$category);
            $db->orderBy('active_at','ASC');
            $db->orderBy('id','ASC');
            $settingRes = $db->map('name')->get("system_settings_admin",NULL,"name,value");
            $timeInterval = $settingRes['gameTimeInterval'];

            if(!$packageName){
                $errorFieldArr[] = array(
                    'id'  => 'packageNameError',
                    'msg' => $translations['E00998'][$language] /*Invalid Package Name.*/
                );
            }

            //Check Selling Qty
            if(!$sellingQty || $sellingQty <= 0 || !is_numeric($sellingQty) || (int)$sellingQty != $sellingQty){
                $errorFieldArr[] = array(
                    'id'  => 'sellingQtyError',
                    'msg' => $translations['E00999'][$language] /*Invalid Quantity.*/
                );
            }

            //Check Selling Price
            if(!$sellingPrice || $sellingPrice <= 0 || !is_numeric($sellingPrice) || (int)$sellingPrice != $sellingPrice){
                $errorFieldArr[] = array(
                    'id'  => 'sellingPriceError',
                    'msg' => $translations['E00910'][$language]
                );
            }

            //Check Game Round
            if(!$gameRoundArr){
                $errorFieldArr[] = array(
                    'id'  => 'gameRoundArrError',
                    'msg' => $translations['E01000'][$language] /*Invalid Game Details.*/
                );
            }else{
                foreach ($gameRoundArr as $gameRoundKey => $gameRoundRow) {
                    if($gameArr[$gameRoundRow['gameTimeTS']]){
                        $errorFieldArr[] = array(
                            'id'  => 'gameTimeTS'.($gameRoundKey+1).'Error',
                            'msg' => $translations['E01001'][$language] /*Invalid Game Time.*/
                        );
                    }
                    $gameArr[$gameRoundRow['gameTimeTS']]['winner'] = $gameRoundRow['winner'];
                    $gameArr[$gameRoundRow['gameTimeTS']]['columnKey'] = $gameRoundKey;
                    $gameArr[$gameRoundRow['gameTimeTS']]['packageID'] = $gameRoundRow['packageID'];
                    if($gameRoundRow['packageID']){
                        $packageIDArr[$gameRoundRow['packageID']] = $gameRoundRow;
                    }
                }
                ksort($gameArr);
            }

            $totalWinner = 0;
            foreach ($gameArr as $gameTimeTS => &$gameRow) {

                //Check Game Date
                if(($lastTimeTs) && ($gameTimeTS < ($lastTimeTs+strtotime($timeInterval,0)))){
                    $errorFieldArr[] = array(
                        'id'  => 'gameTimeTS'.($gameRow['columnKey']+1).'Error',
                        'msg' => str_replace("%%timeInterval%%", $timeInterval, $translations['E01002'][$language]) /*Game Time Interval cannot less than %%timeInterval%%.*/
                    );
                }

                //Check Winner setting
                if(($gameRow['winner'] <= 0) || ((int)$gameRow['winner'] != $gameRow['winner']) || (!is_numeric($gameRow['winner']))){
                    $errorFieldArr[] = array(
                        'id'  => 'winner'.($gameRow['columnKey']+1).'Error',
                        'msg' => $translations['E01003'][$language] /*Invalid Winner Setting.*/
                    );
                }


                $totalWinner += $gameRow['winner'];
                $lastTimeTs = $gameTimeTS;
            }

            if($totalWinner > $sellingQty){
                $errorFieldArr[] = array(
                    'id'  => 'winner'.($gameRow['columnKey']+1).'Error',
                    'msg' => $translations['E01004'][$language] /*Winner cannot more than package quantity.*/
                );
            }

            $fristGameTS = MIN(array_keys($gameArr));
            $lastGameTS = MAX(array_keys($gameArr));

            //Check Selling Time Start
            if(!$sellingStartTS || !is_numeric($sellingStartTS)){
                $errorFieldArr[] = array(
                    'id'  => 'sellingStartTSError',
                    'msg' => $translations['E00505'][$language] /*Invalid Value.*/
                );
            }elseif($sellingStartTS > $sellingEndTS){
                $errorFieldArr[] = array(
                    'id'  => 'sellingStartTSError',
                    'msg' => "Selling Start time cannot late than End time."
                );
            }

            //Check Selling End Start
            if(!$sellingEndTS){
                $errorFieldArr[] = array(
                    'id'  => 'sellingEndTSError',
                    'msg' => $translations['E00505'][$language] /*Invalid Value.*/
                );
            }elseif($sellingEndTS >= $fristGameTS){
                $errorFieldArr[] = array(
                    'id'  => 'sellingEndTSError',
                    'msg' => $translations['E01006'][$language] /*Selling End time cannot late than first game time.*/
                );
            }

            if(!$voucherID){
                $errorFieldArr[] = array(
                    'id'  => 'voucherError',
                    'msg' => "Invalid Voucher"
                );
            }else{
                $db->where('id',$voucherID);
                $db->where('status','Active');
                if(!$db->has('voucher')){
                    $errorFieldArr[] = array(
                        'id'  => 'voucherError',
                        'msg' => "Invalid Voucher"
                    );
                }
            }

            // Check Voucher Expired Time
            if($voucherExpiredTS <= $lastGameTS){
                $errorFieldArr[] = array(
                    'id'  => 'voucherExpiredTSError',
                    'msg' => $translations['E01007'][$language] /*Voucher expired time cannot early than last game time.*/
                );
            }

            if($type == 'edit'){
                if(!$gameCode){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01008'][$language] /*Invalid Game ID.*/, 'data' => "");
                }

                //Check Package ID
                $db->where('code',$gameCode);
                $gameCodeRes = $db->map('id')->get('private_game',null,'id,status,winner,start_time');
                if(!$gameCodeRes){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01009'][$language] /*Invalid Package ID.*/, 'data' => "");
                }else{
                    foreach ($packageIDArr as $packageID => $packageIDRow) {
                        if(!$gameCodeRes[$packageID]){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01009'][$language] /*Invalid Package ID.*/, 'data' => "");
                        }
                    }
                    foreach ($gameCodeRes as $gameCodeRow) {
                        if(in_array($gameCodeRow['status'], array("pending","completed"))){
                            $ranGameArr[$gameCodeRow['id']] = $gameCodeRow;
                        }
                    }
                }

                foreach ($ranGameArr as $ranGameID => $ranGameRow) {
                    if(!$packageIDArr[$ranGameID]){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Game already ran, cannot edit", 'data' => "");
                    }else{
                        if($ranGameRow['winner'] != $packageIDArr[$ranGameID]['winner']){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => "Game already ran, cannot edit", 'data' => "");
                        }

                        if($ranGameRow['start_time'] != date("Y-m-d H:i:s",$packageIDArr[$ranGameID]['gameTimeTS'])){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => "Game already ran, cannot edit", 'data' => "");
                        }
                    }
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            $data['gameArr'] = $gameArr;
            $data['fristGameTS'] = $fristGameTS;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function addEVoucher($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $name = trim($params['name']);
            $nameLanguages = $params['nameLanguages'];
            $descrLanguages = $params['descrLanguages'];
            $uploadImage = $params['uploadImage'];
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Admin::eVoucherVerification($params,"add");
            if($result['status'] == 'error'){
                return $result;
            }

            $translationCode = General::generateDynamicCode("W");

            // Get System Languages
            $db->where("disabled", 0);
            $languages = $db->map("language_code")->get("languages", NULL, "language_code, language");

            // Insert language_translation
            foreach($nameLanguages as $nameRow) {
                $defaultName = $name;
                $nameLanguagesList[$nameRow['languageType']] = $nameRow;
            }

            foreach($languages as $languagesRow) {
                if($nameLanguagesList[$languagesRow]['languageType'] == $languagesRow) {
                    $insertProductNameTrans = array(
                        "code" => $translationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $nameLanguagesList[$languagesRow]['content'],
                        "created_at" => $dateTime
                    );
                } else {
                    $insertProductNameTrans = array(
                        "code" => $translationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $defaultName,
                        "created_at" => $dateTime
                    );
                }
                $db->insert('language_translation',$insertProductNameTrans);
            }

            $descrTranslationCode = General::generateDynamicCode("W");

            foreach($descrLanguages as $descriptionRow) {
                if($descriptionRow['content'] && !$defaultDescription) $defaultDescription = $descriptionRow['content'];
                $descLanguagesList[$descriptionRow['languageType']] = $descriptionRow;
            }

            foreach($languages as $languagesRow) {
                if($descLanguagesList[$languagesRow]['languageType'] == $languagesRow) {
                    $insertProductDescrTrans = array(
                        "code" => $descrTranslationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $descLanguagesList[$languagesRow]['content'],
                        "created_at" => $dateTime
                    );
                } else {
                    $insertProductDescrTrans = array(
                        "code" => $descrTranslationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $defaultDescription,
                        "created_at" => $dateTime
                    );
                }
                $db->insert('language_translation',$insertProductDescrTrans);                        
            }

            $imageGroupUniqueChar   = General::generateUniqueChar("voucher","image_name");
            $imageUniqueChar = General::generateUniqueChar("voucher","image_name");
            $imageAry = explode(".",$uploadImage['imgName']);
            $imageExt = end($imageAry);
            $storedImage = time()."_".$imageUniqueChar."_".$imageGroupUniqueChar.".".$imageExt;

            //Insert Voucher
            $insertData = array(
                "name" => $name,
                "translation_code" => $translationCode,
                "description" => $descrTranslationCode,
                "image_name" => $storedImage,
                "status" => "Active",
                "created_at" => $dateTime,
            );
            $db->insert('voucher',$insertData);

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }

            $data['imgName'] = $storedImage;

            // insert activity log
            $activityData = array('admin' => $adminName,"name"=>$name);

            $activityRes = Activity::insertActivity('Add Voucher', 'T00052', 'L00072', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function eVoucherVerification($params,$type = "add"){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $name = trim($params['name']);
            $voucherID = trim($params['voucherID']);
            $status = trim($params['status']);
            $nameLanguages = $params['nameLanguages'];
            $descrLanguages = $params['descrLanguages'];
            $uploadImage = $params['uploadImage'];
            $validStatus = array("Active","Inactive");
            $dateTime = date('Y-m-d H:i:s');

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            if(empty($name)){
                $errorFieldArr[] = array(
                    'id'  => "nameError",
                    'msg' => $translations['E00628'][$language] /* This field value is invalid. */
                );
            }

            // Check Name Language Field
            if(empty($nameLanguages)) {
                $errorFieldArr[] = array(
                    'id'  => "nameLanguagesError",
                    'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                );
            }else{
                // Check Name Language Field
                foreach($nameLanguages as $nameRow) {
                    if(!$nameRow["languageType"]) {
                        $errorFieldArr[] = array(
                            'id'  => "nameLanguagesError",
                            'msg' => $translations['E00602'][$language] /* Please Select Language. */
                        );
                    }

                    if(empty($nameRow["content"])) {
                        $errorFieldArr[] = array(
                            'id'  => "nameLanguagesError",
                            'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                        );
                    }
                }
            }

            // Check Description Language Field
            if(empty($descrLanguages)) {
                $errorFieldArr[] = array(
                    'id'  => "descrLanguagesError",
                    'msg' => $translations['E00662'][$language] /* Please Enter Description. */
                );
            }else{
                // check description language field
                foreach($descrLanguages as $descriptionRow) {
                    if(empty($descriptionRow["content"])) {
                        $errorFieldArr[] = array(
                            'id'  => "descrLanguagesError",
                            'msg' => $translations['E00662'][$language] /* Please Enter Description. */
                        );
                    }
                }
            }

            if(!$uploadImage){
                $errorFieldArr[] = array(
                    'id'  => "imgError",
                    'msg' => $translations['E00556'][$language] /*Image fields cannot be empty.*/
                );
            }else{
                $validImageSet  = $uploadSetting['validImageType'];
                $validImageType = explode("#", $validImageSet['value']);
                $validImageSize = $validImageSet['reference'];
                $sizeMB         = $validImageSize / 1024 / 1024;

                if(empty($uploadImage["imgName"]) || empty($uploadImage["imgType"])) {
                    $errorFieldArr[] = array(
                        'id'  => "imgError",
                        'msg' => $translations["E00925"][$language] /* No file chosen */
                    );
                }

                if(empty($uploadImage['uploadType']) || !in_array($uploadImage['uploadType'], array('image'))) {
                    $errorFieldArr[] = array(
                        'id'  => "uploadTypeError",
                        'msg' => $translations["E00741"][$language] /* Invalid Type */
                    );
                }

                if($uploadImageRow["imgFlag"] && $type != 'add'){
                    if(!in_array($uploadImage["imgType"], $validImageType)) {
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                        );
                    }

                    if(!$uploadImage['imgSize'] || $uploadImage['imgSize'] > $validImageSize){
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                        );
                    }
                }
            }

            if($type == 'edit'){
                $db->where('id',$voucherID);
                $voucherRes = $db->getOne('voucher','status,translation_code,description,image_name');
                $voucherStatus = $voucherRes['status'];
                if(!$voucherStatus){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Voucher", 'data' => "");
                }

                if(!in_array($status, $validStatus)){
                    $errorFieldArr[] = array(
                        'id'  => "statusError",
                        'msg' => $translations['E00396'][$language]
                    );
                }
                if($voucherStatus != $status){
                    $db->where('voucher_id',$voucherID);
                    $db->where('redeem_status','pending');
                    $checkVoucher = $db->getValue('private_game_detail','id');
                    if($checkVoucher){
                        $errorFieldArr[] = array(
                            'id'  => "statusError",
                            'msg' => "Still got available voucher, failed to inactive voucher."
                        );
                    }
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }
            $data['voucherRes'] = $voucherRes;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function editEVoucher($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $voucherID = trim($params['voucherID']);
            $status = trim($params['status']);
            $name = trim($params['name']);
            $nameLanguages = $params['nameLanguages'];
            $descrLanguages = $params['descrLanguages'];
            $uploadImage = $params['uploadImage'];
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Admin::eVoucherVerification($params,"edit");
            if($result['status'] == 'error'){
                return $result;
            }
            $translationsCode = $result['data']['voucherRes']['translation_code'];
            $descriptionCode = $result['data']['voucherRes']['description'];
            $imageName = $result['data']['voucherRes']['image_name'];
            $oldStatus = $result['data']['voucherRes']['status'];

            //Update Name Language
            $db->where("code", $translationsCode);
            $translationList = $db->get("language_translation", NULL, "id, code, language, content");

            // Update language_translation
            foreach ($nameLanguages as $nameRow) {
                $defaultName = $name;
                $nameLanguagesList[$nameRow['languageType']] = $nameRow;
            }

            foreach ($translationList as $translationRow) {
                if($nameLanguagesList[$translationRow['language']]['languageType'] == $translationRow['language']) {
                    $updateTranslation = array(
                        "language" => $translationRow["language"],
                        "content" => $nameLanguagesList[$translationRow['language']]['content'],
                        "updated_at" => $dateTime
                    );
                } else {
                    $updateTranslation = array(
                        "language" => $translationRow["language"],
                        "content" => $defaultName,
                        "updated_at" => $dateTime
                    );                   
                }
                $db->where("id", $translationRow['id']);
                $db->update("language_translation", $updateTranslation);
            }

            //Update Description Language
            $db->where("code", $descriptionCode);
            $descrTranslationList = $db->get("language_translation", NULL, "id, code, language, content");

            // Update language_translation
            foreach ($descrLanguages as $descrLanguagesRow) {
                if($descrLanguagesRow['content'] && !$defaultDescrName) $defaultDescrName = $descrLanguagesRow['content'];
                $descrLanguagesList[$descrLanguagesRow['languageType']] = $descrLanguagesRow;
            }

            foreach ($descrTranslationList as $descrTranslationListRow) {
                if ($descrLanguagesList[$descrTranslationListRow['language']]['languageType'] == $descrTranslationListRow["language"]) {
                    $updateDescrTranslation = array(
                        "language" => $descrTranslationListRow["language"],
                        "content" => $descrLanguagesList[$descrTranslationListRow['language']]['content'],
                        "updated_at" => $dateTime
                    );
                } else {
                    $updateDescrTranslation = array(
                        "language" => $descrTranslationListRow["language"],
                        "content" => $defaultDescrName,
                        "updated_at" => $dateTime
                    );       
                }
                $db->where('id',$descrTranslationListRow['id']);
                $db->update("language_translation", $updateDescrTranslation);
            }

            if($imageName != $uploadImage['imgName']){
                $imageGroupUniqueChar   = General::generateUniqueChar("voucher","image_name");
                $imageUniqueChar = General::generateUniqueChar("voucher","image_name");
                $imageAry = explode(".",$uploadImage['imgName']);
                $imageExt = end($imageAry);
                $storedImage = time()."_".$imageUniqueChar."_".$imageGroupUniqueChar.".".$imageExt;

                //Insert Media Trash
                unset($insertTrash);
                $insertTrash = array(
                    'file_name' => $imageName,
                    'created_at'=> $dateTime,
                    'deleted'   => '0'
                );
                $db->insert('media_trash', $insertTrash);

                $editData['image']['old'] = $imageName;
                $editData['image']['new'] = $storedImage;
            }

            if($oldStatus != $status){
                $editData['image']['old'] = $oldStatus;
                $editData['image']['new'] = $status;
            }

            //Insert Voucher
            $updateData = array(
                "name" => $name,
                "status" => $status,
                "updated_at" => $dateTime,
                "updater_id" => $userID,
            );
            if($storedImage) $updateData["image_name"] = $storedImage;
            $db->where('id',$voucherID);
            $db->update('voucher',$updateData);

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }
            $data['imgName'] = $storedImage;
            // insert activity log
            $activityData = array('admin' => $adminName,"name"=>$name, "editData"=>$editData);

            $activityRes = Activity::insertActivity('Edit Voucher', 'T00053', 'L00073', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function getVoucherList($params,$onlyList) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            
            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if (!$seeAll && !$onlyList) $limit = General::getLimit($pageNumber);

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {

                        default :
                            break;    
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            if($onlyList) $db->where('status','Active');
            $db->orderBy('created_at', 'DESC');
            $voucherRes = $db->get('voucher', $limit, 'id,name,translation_code,description,created_at,status,updated_at,updater_id');
            if (!$voucherRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach ($voucherRes as $voucherRow) {
                $updaterIDArr[$voucherRow['updater_id']] = $voucherRow['updater_id'];
            }

            //Get Updater username
            if($updaterIDArr){
                $db->where('id',$updaterIDArr,"IN");
                $updaterData = $db->map('id')->get('admin',null,'id,username');
            }

            foreach ($voucherRes as $voucherRow) {
                $voucherData['voucherID']   = $voucherRow['id'];
                $voucherData['name']        = $voucherRow['name'];
                $voucherData['display']     = $translations[$voucherRow['translation_code']][$language];

                if(!$onlyList){
                    $voucherData['description'] = $translations[$voucherRow['description']][$language];
                    $voucherData['status']      = $voucherRow['status'];
                    $voucherData['statusDisplay'] = General::getTranslationByName($voucherRow['status']);
                    $voucherData['createdAt']   = $voucherRow['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($voucherRow['created_at'])) : "-";
                    $voucherData['updateAt']    = $voucherRow['updated_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($voucherRow['updated_at'])) : "-";
                    $voucherData['updater']     = $voucherRow['updater_id']?$updaterData[$voucherRow['updater_id']]:"-";
                }

                $voucherList[] = $voucherData;
            }

            if($onlyList){
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $voucherList);
            }

            $totalRecord = $copyDb->get("voucher", null,"id");
            $totalRecord = COUNT($totalRecord);

            $data['voucherList'] = $voucherList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        public function getVoucherDetail($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $voucherID  = trim($params['voucherID']);

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $db->where('id',$voucherID);
            $voucherRes = $db->getOne('voucher', 'id,name,translation_code,description,status,image_name');
            if (!$voucherRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $languageCodeArr[] = $voucherRes['translation_code'];
            $languageCodeArr[] = $voucherRes['description'];

            $db->where('code',$languageCodeArr,"IN");
            $langRes = $db->get('language_translation',null,'code,language,content');
            foreach ($langRes as $langRow) {
                $languageArr[$langRow['code']][$langRow['language']] = $langRow['content'];
            }
            $voucherRes['nameLang'] = $languageArr[$voucherRes['translation_code']];
            $voucherRes['descriptionLang'] = $languageArr[$voucherRes['description']];

            unset($voucherRes['translation_code'],$voucherRes['description']);
            $voucherData = $voucherRes;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $voucherData);
        }

        public function getCVRateList($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $now            = date("Y-m-d H:i:s");
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $db->where("status", "Active");
            $db->where("currency_code", "", "!=");
            $db->orderBy("priority", "ASC");
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("country", "count(*)");

            $countryRes = $db->get("country", $limit, "id, name, translation_code, currency_code");

            foreach ($countryRes as $countryRow) {
                $countryIDArr[$countryRow['id']] = $countryRow['id'];
            }

            // $db->where("country_id", $countryIDArr, "IN");
            // $db->where("actived_at", $now, "<=");
            // $db->groupBy("country_id");
            // $maxIDListRes = $db->get('cv_rate', null, 'max(id) as id');
            foreach ($maxIDListRes as $maxIDListRow) {
                $maxIDList[$maxIDListRow['id']] = $maxIDListRow['id'];
            }

            $db->where("actived_at", $now, "<=");
            $db->orderBy('id','ASC');
            $fullListRes = $db->get('cv_rate', null, 'id, country_id, actived_at');
            foreach ($fullListRes as $fullListRow) {
                if(strtotime($fullListRow['actived_at']) >= strtotime($maxActive[$fullListRow['country_id']]['actived_at'])){
                    $maxActive[$fullListRow['country_id']]['actived_at'] = $fullListRow['actived_at'];
                    $maxActive[$fullListRow['country_id']]['id'] = $fullListRow['id'];
                }
            }

            foreach ($maxActive as $countryID => $maxActive) {
                $maxIDList[$maxActive['id']] = $maxActive['id'];
            }

            if($maxIDList){
                $db->where("id", $maxIDList, "IN");
                $cvListRes = $db->map('country_id')->get('cv_rate', null, 'country_id, rate, actived_at');
            }

            foreach ($countryRes as $countryRow) {

                if (Cash::$creatorType == "Admin") {
                    $rateData['id'] = $countryRow['id'];
                }

                $translateCode = $countryRow['translation_code'];
                $rateData['displayName'] = $translations[$translateCode][$language];
                $rateData['currencyCode'] = $countryRow['currency_code'];
                $rateData['rate'] = Setting::setDecimal($cvListRes[$countryRow['id']]["rate"], $decimalPlaces);

                $rateData['activedAt'] = "-";
                if($cvListRes[$countryRow['id']]["actived_at"] != '0000-00-00 00:00:00' && $cvListRes[$countryRow['id']]["actived_at"]){
                    $rateData['activedAt'] = date($dateTimeFormat, strtotime($cvListRes[$countryRow['id']]["actived_at"]));
                }

                $cvRateList[] = $rateData;
            }

            $data['cvRateList']  = $cvRateList;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getCVRateHistory($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $userID         = $db->userID;
            $site           = $db->userType;

            $id             = trim($params['id']);

            // check admin
            if($site != "Admin"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            }

            if (empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $db->where("country_id", $id);
            $cvHistoryRes = $db->get('cv_rate', null, 'rate, actived_at, created_at, creator_id');

            if (!$cvHistoryRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach ($cvHistoryRes as $cvHistoryRow) {
                $adminIDList[$cvHistoryRow['creator_id']] = $cvHistoryRow['creator_id'];
            }

            $db->where('id', $adminIDList, 'IN');
            $adminUsername = $db->map('id')->get('admin', null, 'id, username');

            foreach ($cvHistoryRes as $cvHistoryRow) {
                $rateData['rate'] = Setting::setDecimal($cvHistoryRow['rate']);

                $rateData['createdAt'] = $cvHistoryRow['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($cvHistoryRow['created_at'])) : "-";

                $rateData['activedAt'] = $cvHistoryRow['actived_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($cvHistoryRow['actived_at'])) : "-";

                $rateData['admin'] = $adminUsername[$cvHistoryRow['creator_id']];
                $cvRateHistory[] = $rateData;
            }

            $data['cvRateHistory']  = $cvRateHistory;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function editCVRate($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;
            
            $countryID      = trim($params['countryID']);
            $cvRate         = trim($params['cvRate']);
            $activeDate     = trim($params['activeDate']);
            $now            = date("Y-m-d H:i:s");

            if (empty($cvRate) || !is_numeric($cvRate) || $cvRate < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $tsActive = strtotime($activeDate);
            $tsNow = strtotime($now);
            if (empty($activeDate) || $tsActive < $tsNow)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Active Date should be more than now.", 'data'=> "");

            if (empty($countryID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            // check admin
            if($site != "Admin"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            }

            $db->where('id', $userID);
            $adminUsername = $db->getValue("admin", "username");

            $db->where("id", $countryID);
            $db->where("status", "Active");
            $validCountry = $db->getOne("country", "id, currency_code, translation_code");
            if (empty($validCountry) || !$validCountry['currency_code'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            unset($insertData);
            $insertData = array(
                "country_id"    => $validCountry['id'],
                "currency_code" => $validCountry['currency_code'],
                "rate"          => $cvRate,
                "actived_at"    => $activeDate,
                "created_at"    => $now,
                "creator_id"    => $userID,
            );

            if (!$db->insert("cv_rate", $insertData))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");    

            // insert activity log
            $title   = 'Add CV Rate';
            $titleCode      = 'T00058'; // Add CV Rate
            $activityCode   = 'L00078'; // %%admin%% added CV rate: %%cvRate%%, active date: %%activeDate%%.
            $activityData   = array(
                'admin'     => $adminUsername,
                'activeDate'=> $activeDate,
                'cvRate'    => $cvRate,
            );

            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00419"][$language] /* Successfully add PV rate. */, 'data'=> "");
        }

        public function getMemberName($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;

            $memberID             = trim($params['memberID']);

            // check admin
            // if($site != "Admin"){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            // }

            if (empty($memberID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $db->where("member_id", $memberID);
            $memberName = $db->getValue("client", "name");

            $data['memberName'] = $memberName?:'-';

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function setTaxPercentage($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;
            $bonusSetting    = $params['bonusSetting'];
            $dateTime       = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
            }else{
                $db->where('id',$userID);
                $username = $db->getValue('admin','username');
                if(!$username){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
                }
            }

            foreach ($bonusSetting as $settingRow) {
                if(is_null($settingRow['minBonusAmt']) || !is_numeric($settingRow['minBonusAmt']) || ($settingRow['minBonusAmt'] < 0)){
                    $errorFieldArr[] = array(
                        'id' => 'minBonusAmt'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01146'][$language]/*Invalid Minimum Bonus Amount*/
                    );
                }

                if(($bonusStgArr[$settingRow['tier']]) || (!$settingRow['tier']) || (!is_numeric($settingRow['tier'])) || ($settingRow['tier'] <= 0)){
                    $errorFieldArr[] = array(
                        'id' => 'tier'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01147'][$language]/*Invalid Tier*/
                    );
                }

                if($lastTier+1 != $settingRow['tier']){
                    $errorFieldArr[] = array(
                        'id' => 'tier'.$settingRow['tier'].'Error',
                        'msg' => str_replace("%%lastTier%%", $lastTier+1, $translations['E01150'][$language]/*Tier %%lastTier%% is Missing.*/)
                    );
                }

                if(is_null($settingRow['npwpTax']) || !is_numeric($settingRow['npwpTax']) || ($settingRow['npwpTax'] < 0)){
                    $errorFieldArr[] = array(
                        'id' => 'npwpTax'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01148'][$language]/*Invalid Tax Percentage*/
                    );
                }

                if(is_null($settingRow['nonNpwpTax']) || !is_numeric($settingRow['nonNpwpTax']) || ($settingRow['nonNpwpTax'] < 0)){
                    $errorFieldArr[] = array(
                        'id' => 'nonNpwpTax'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01148'][$language]/*Invalid Tax Percentage*/
                    );
                }

                $bonusStgArr[$settingRow['tier']] = $settingRow;

                $lastTier = $settingRow['tier'];
            }

            ksort($bonusStgArr);
            unset($lastBonusAmt);
            foreach ($bonusStgArr as $tier => $bonusStgRow) {
                if(($lastBonusAmt) && ($lastBonusAmt >= $bonusStgRow['minBonusAmt'])){
                    $errorFieldArr[] = array(
                        'id' => 'nonNpwpminBonusAmtTax'.$tier.'Error',
                        'msg' => $translations['E01149'][$language]/*Bonus Amount cannot less than last tier.*/
                    );
                }
                $lastBonusAmt = $bonusStgRow['minBonusAmt'];
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            if(!$bonusStgArr){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00743"][$language] /*Failed to Update*/, 'data' => "");
            }

            foreach ($bonusStgArr as $bonusTier => $bonusRow) {
                unset($updateData);

                $db->where('name','bonusTaxPercentage');
                $db->where('ref_id',$bonusTier);
                $stgID = $db->getValue('system_settings_admin','id');

                $updateData['value']    = $bonusRow['minBonusAmt'];
                $updateData['type']     = $bonusRow['npwpTax'];
                $updateData['reference']= $bonusRow['nonNpwpTax'];
                $updateData['status']   = "Active";
                $updateData['creator_id']= $userID;

                if($stgID){
                    $db->where('id',$stgID);
                    $db->update('system_settings_admin',$updateData);
                }else{
                    $updateData['name'] = 'bonusTaxPercentage';
                    $updateData['ref_id'] = $bonusTier;
                    $updateData['creator_id']= $userID;
                    $updateData['status']    = "Active";
                    $updateData['active_at'] = $dateTime;
                    $updateData['created_at']= $dateTime;
                    $db->insert('system_settings_admin',$updateData);

                }
            }

            //Inactive others setting
            $db->where('name','bonusTaxPercentage');
            $db->where('ref_id',array_keys($bonusStgArr),"NOT IN");
            $db->update('system_settings_admin',array("status"=>"Inactive"));


            // insert activity log
            $titleCode      = 'T00072';
            $activityCode   = 'L00098';
            $transferType   = 'Set Bonus Tax Percentage';
            $activityData   = array(
                'adminName' => $username,
                'data'  => json_encode($bonusStgArr),
            );
            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00744'][$language] /*Successfully Updated*/, 'data'=> $data);
        }

        public function getTaxPercentage($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;
            $dateTime       = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
            }else{
                $db->where('id',$userID);
                $username = $db->getValue('admin','username');
                if(!$username){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
                }
            }

            $db->where('name','bonusTaxPercentage');
            $db->where('status','Active');
            $db->orderBy('CAST(ref_id AS Integer)','ASC');
            $bonusTaxStgArr = $db->get('system_settings_admin',null,'value AS minBonusAmt,type AS npwpTax, reference AS nonNpwpTax,ref_id AS tier');
            $data['bonusTaxStgArr'] = $bonusTaxStgArr;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /*Successfully retrieved.*/, 'data'=> $data);
        }
    }
?>
