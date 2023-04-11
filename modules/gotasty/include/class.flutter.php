<?php

    class Flutter {

        function __construct() {
            // $this->db = $db;
            // $this->setting = $setting;
            // $this->general = $general;
        }

        public function appLogin($msgpackData) {
            $db             = MysqliDb::getInstance();
            // $language       = General::$currentLanguage;
            // $translations   = General::$translations;

            $params         = $msgpackData['params'];
            $ip             = $msgpackData['ip'];

            // $id             = trim($params['id']);
            $username       = trim($params['username']);
            $password       = trim($params['password']);

            $dateTime       = date('Y-m-d H:i:s');

            /* Check username and password cannot be empty */
            if (empty($username)) return array('status' => "error", 'code' => 1, 'msg' => "Username is required", 'statusMsg' => "Your Username cannot be empty", 'data' => "");
            if (empty($password)) return array('status' => "error", 'code' => 1, 'msg' => "Passsword is required", 'statusMsg' => "Your Password cannot be empty", 'data' => "");

            $encryptedPassword = $db->encrypt($password);

            /* Verify user using mysql encryption */
            $db->where('username', $username);
            $db->where('password', $encryptedPassword);
            $result = $db->getOne('client');

            if (empty($result)) {
                return array('status' => "error", 'code' => 1, 'msg' => "Login Failed", 'statusMsg' => "Your username or password is incorrect", 'data' => "");
            }

            /* Use to check mutiple data */
            $clientId = $result["id"];

            /* If member is registered under this country, prevent login */
            $db->where('registered_block_login','1');
            $disabledLoginCountries = $db->map('id')->arrayBuilder()->get('country', null, 'id');

            if (in_array($result['country_id'], $disabledLoginCountries)){
                return array('status' => 'error', 'code' => 1, 'msg' => "Login Failed", 'statusMsg' => "Your region doesn't support this app", 'data' => "");
            }

            // $turnOffPopUpMemo = $result['turnOffPopUpMemo'];

            if ($result['disabled'] == 1) {
                $msg = "Your account is disabled";
                $statusErrMsg = "Please contract our admin for more information";
            }

            if ($result['activated'] == 0) {
                $msg = "Your account is not activated";
                $statusErrMsg = "Please contract our admin to re-activate";
            }

            if ($result['suspended'] == 1) {
                $msg = "Your account is suspended";
                $statusErrMsg = "Please contract our admin for more information";
            }

            if ($result['freezed'] == 1) {
                $msg = "Your account is freezed";
                $statusErrMsg = "Please contract our admin for more information";
            }

            if ($result['terminated'] == 1) {
                $msg = "Your account is terminated";
                $statusErrMsg = "Please contract our admin for more information";
            }

            if ($statusErrMsg || $msg) {
                if ($marcaje && $marcajeTK) {
                    return array('status' => 'error', 'code' => 5, 'msg' => "", 'statusMsg' => "", 'data' => "");
                }
                else {
                    return array('status' => 'error', 'code' => 1, 'msg' => $msg, 'statusMsg' => $statusErrMsg, 'data' => $data);
                }
            }

            /* Checking if client's countryIP is allowed to login */
            $returnData = Client::countryIPBlock($ip, $clientId);
            if ($returnData['status'] == "error" || $returnData['code'] == 1) {
                return array('status' => 'error', 'code' => 1, 'msg' => "Login Failed", 'statusMsg' => "Your country IP was blocked", 'data' => "");
            }

            $sessionID = md5($result['username'] . time());

            $fields = array('session_id', 'last_login', 'last_login_ip');
            $values = array($sessionID, date("Y-m-d H:i:s"), $ip);
            $db->where('id', $clientId);
            $db->update('client', array_combine($fields, $values));

            /* Insert Session ID */
            $sessionData = User::insertSessionData($clientId, $sessionID, $dateTime, $timeOut, $isAutoLogin);

            // $content = '*Login Message* '."\n\n".'Member ID: '.$member_id."\n".'Type: '.$type."\n".'Phone Number: '.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            // Client::sendTelegramNotification($content);
            // $memo = Bulletin::getPopUpMemo($id, $turnOffPopUpMemo);

            // $member['memo'] = $memo;
            $member['timeOutFlag']      = Setting::$systemSetting["appTimeout"];
            $member['userID']           = $clientId;
            $member['memberID']         = $result['member_id'];
            $member['name']             = $result['name'];
            $member['username']         = $result['username'];
            // $member['userRoleID']       = $result['role_id'];
            $member["countryID"]        = $result["country_id"];
            $member['sessionID']        = $sessionID;
            // $member['decimalPlaces'] = Setting::getInternalDecimalFormat();
            $data['userDetails']        = $member;
            if ($sessionData['token']) {
                $data['token']          = $sessionData['token'];
            }
            // $db->where("id", $clientId);
            // $db->update('client', array("fail_login" => "0"));

            // $data['marcaje']            = $sessionData['marcaje'];
            // $data['marcajeTK']          = $sessionData['marcajeTK'];
            // $data['expiredTS']          = $sessionData['expiredTS']?($sessionData['expiredTS'] + $defTimeOut):"";

            return array('status' => 'ok', 'code' => 0, 'msg' => "Login", 'statusMsg' => "Login successfully", 'data' => $data);
        }

        // public function appLogin($params) {
        //     $db = MysqliDb::getInstance();

        //     // $db = $this->db;
        //     // $setting = $this->setting;

        //     // Get the stored password type.
        //     // $passwordEncryption = $setting->getSuperAdminPasswordEncryption();

        //     $username = trim($params['username']);
        //     $password = trim($params['password']);

        //     /* Check username and password cannot be empty */
        //     if (empty($username)) return array('status' => "error", 'code' => 1, 'msg' => "Username is required", 'statusMsg' => "Your Username cannot be empty", 'data' => "");
        //     if (empty($password)) return array('status' => "error", 'code' => 1, 'msg' => "Passsword is required", 'statusMsg' => "Your Password cannot be empty", 'data' => "");


        //     $encryptedPassword = $db->encrypt($password);

        //     /* Verify user using mysql encryption */
        //     $db->where('username', $username);
        //     $db->where('password', $encryptedPassword);
        //     $result = $db->getOne('client');

        //     /* Valid User */
        //     if (!empty($result)) {

        //         /* Check account disabled */
        //         if ($result['disabled'] == 1) {
        //             /* Return error if account is disabled */
        //             return array('status' => "error", 'code' => 1, 'msg' => "Account Not Found", 'statusMsg' => "Your account is disabled", 'data' => "");
        //         }

        //         $client_id = $result['id'];
        //         $session_id = md5($result['username'] . time());

        //         // $fields = array('session_id', 'last_login', 'updated_at');
        //         // $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
        //         $userLogin = array(
        //             'session_id'    => $session_id,
        //             'last_login'    => date("Y-m-d H:i:s"),
        //             'updated_at'    => date("Y-m-d H:i:s")
        //         );
        //         $db->where('id', $client_id);
        //         $db->update('client', $userLogin);

        //         $client['userID'] = $client_id;
        //         $client['username'] = $result['username'];
        //         $client['sessionID'] = $session_id;

        //         $data['userDetails'] = $client;

        //         return array('status' => "ok", 'code' => 0, 'msg' => "Login", 'statusMsg' => "Login successfully", 'data' => $data);
        //     }
        //     else {
        //         return array('status' => "error", 'code' => 1, 'msg' => "Login Failed", 'statusMsg' => "Your username or password is incorrect", 'data' => "");
        //     }
        // }

        public function changePassword($params) {
            $db                 = MysqliDb::getInstance();

            $username           = trim($params['username']);
            $currentPassword    = trim($params['currentPassword']);
            $newPassword        = trim($params['newPassword']);
            $confirmPassword    = trim($params['confirmPassword']);

            /* Check username, current password, new password, and confirm password cannot be empty */
            if (!$username) return array('status' => "error", 'code' => 1, 'msg' => "Username is required", 'statusMsg' => "Your username is null", 'data' => "");
            if (!$currentPassword) return array('status' => "error", 'code' => 1, 'msg' => "Current password is required", 'statusMsg' => "Please enter your current password", 'data' => "");
            if (!$newPassword) return array('status' => "error", 'code' => 1, 'msg' => "New password is required", 'statusMsg' => "Please enter your new password", 'data' => "");
            if (!$confirmPassword) return array('status' => "error", 'code' => 1, 'msg' => "Confirm password is required", 'statusMsg' => "Please enter your confirm password", 'data' => "");

            /* Verify user using mysql encryption */
            $db->where('username', $username);
            $db->where('password', $db->encrypt($currentPassword));
            $result = $db->getOne('client');

            /* Hash and insert new password (database) */
            if (!empty($result)) {
                /* Confirm password must same as new passowrd */
                if ($newPassword == $confirmPassword) {
                    $hash_password = $db->encrypt($newPassword);
                    $changePassword = array(
                        'password'      => $hash_password,
                        'updated_at'    => date("Y-m-d H:i:s")
                    );

                    $db->where('username', $username);
                    $result = $db->update('client', $changePassword);
                }
                else {
                    return array('status' => "error", 'code' => 1, 'msg' => "Confirm Password Doesn't Match", 'statusMsg' => "Your New password and confirm password must be matched", 'data' => "");
                }
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "Incorrect Password", 'statusMsg' => "Your current password is incorrect", 'data' => "");
            }

            /* Change password successfully */
            if ($result) {
                return array('status' => "ok", 'code' => 0, 'msg' => "Change Password", 'statusMsg' => "Change password successfully", 'data' => "");
            }
            else{
                return array('status' => "error", 'code' => 1, 'msg' => "Change Password Failed", 'statusMsg' => "Failed to change password", 'data' => "");
            }
        }

        public function getShopListing($params, $client_id) {
            $db = MysqliDb::getInstance();

            $db->where('client_id', $client_id);
            $db->orderBy('deleted', "ASC");
            $result = $db->get('shop', null, 'id, name, address');

            if (!empty($result)) {
                $data = $result;
                // foreach($result as $key => $value) {
                // }
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "", 'statusMsg' => "No result found", 'data' => "");
            }
        }

        public function getShopDetails($params) {
            $db         = MysqliDb::getInstance();

            $shop_id    = trim($params['shop_id']);

            $db->where('d.shop_id', $shop_id);
            $db->orderBy('d.disabled', "ASC");
            // $db->join('shop s', 'd.shop_id = s.id', "LEFT");
            // $db->joinWhere('shop s', 's.id', $shop_id);
            $result = $db->get('device d', null, 'd.device_ref, d.name, d.disabled');

            if (!empty($result)) {
                $data = $result;
                // foreach($result as $key => $value) {
                // }
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "", 'statusMsg' => "No result found", 'data' => "");
            }
        }

        public function getWorkerListing($params, $client_id) {
            $db = MysqliDb::getInstance();

            $db->where('client.type', "Worker");

            $db->join('assign_shop', 'assign_shop.client_id = client.id', 'INNER');
            $db->join('shop', 'shop.id = assign_shop.shop_id', 'INNER');
            $db->joinWhere('shop', 'shop.client_id', $client_id);
            $result = $db->get('client', null, 'client.id, client.name, client.dial_code, client.phone, assign_shop.id as assign_id, assign_shop.assigned_at, shop.id as shop_id');

            if (!empty($result)) {
                $data = $result;
                // foreach($result as $key => $value) {
                // }
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "", 'statusMsg' => "No result found", 'data' => "");
            }
        }

        public function addWorker($params, $client_id) {
            $db             = MysqliDb::getInstance();

            $workername     = trim($params['workername']);
            $username       = trim($params['username']);
            $password       = trim($params['password']);
            $type           = "Worker"; // user type => worker
            $phone          = trim($params['phone']);
            $shop_id        = trim($params['shopId']);

            if (!$workername) {
                return array('status' => "error", 'code' => 1, 'msg' => "Worker's name is required", 'statusMsg' => "Worker name cannot be empty", 'data' => "");
            }
            if (!$username) {
                return array('status' => "error", 'code' => 1, 'msg' => "Username is required", 'statusMsg' => "Username cannot be empty", 'data' => "");
            }
            if (!$password) {
                return array('status' => "error", 'code' => 1, 'msg' => "Password is required", 'statusMsg' =>  "Password cannot be empty", 'data' => "");
            }
            if (!preg_match('/^[a-zA-Z](?:_?[a-zA-Z0-9]+)*$/', $username, $matches)) {
                return array('status' => "error", 'code' => 1, 'msg' => "Username invalid format", 'statusMsg' => "Username must be alphanumeric only", 'data' => "");
            }
            if (strlen($password) < 8) {
                return array('status' => "error", 'code' => 1, 'msg' => "Password invalid format", 'statusMsg' => "Password must be at least 8 characters", 'data' => "");
            }
            if (!preg_match('/^[a-z0-9@$!%*#?&]*$/', $password, $matches)) {
                return array('status' => "error", 'code' => 1, 'msg' => "Password invalid format", 'statusMsg' => "Password must be alphanumeric and (@!%*#?&]) only", 'data' => "");
            }

            $db->where('username', $username);
            $checkUsername = $db->getOne('client', 'username');

            if ($checkUsername && $checkUsername['username']) {
                return array('status' => "error", 'code' => 1, 'msg' => "Username duplication", 'statusMsg' => "Username already exists", 'data' => ""); 
            }

            /* Encrypt password */
            $encryptedPassword = $db->encrypt($password);

            /* Create new shop owner */
            $insertWorkerData = array(
                'name'          => $workername,
                'username'      => $username,
                'password'      => $encryptedPassword,
                'phone'         => $phone,
                'type'          => $type,
                'activated'     => 1,
                'disabled'      => 0,
                'created_at'    => date('Y-m-d H:i:s')
            );
            $insertWorkerResult = $db->insert('client', $insertWorkerData);

            if ($insertWorkerResult) {
                $insertAssignData = array(
                    'client_id'     => $insertWorkerResult,
                    'shop_id'       => $shop_id,
                    'assigned_at'   => date('Y-m-d H:i:s'),
                    'created_at'    => date('Y-m-d H:i:s')
                );
                $insertAssignResult = $db->insert('assign_shop', $insertAssignData);

                if ($insertAssignResult) {
                    $db->where('id', $shop_id);
                    $shopname = $db->getValue('shop', 'name') ? : "the shop";

                    return array('status' => "ok", 'code' => 0, 'msg' => "Add New Worker", 'statusMsg' => "Add worker successfully and assigned to ".$shopname, 'data' => "");
                }
                else {
                    return array('status' => "ok", 'code' => 0, 'msg' => "Add New Worker", 'statusMsg' => "Add worker successfully", 'data' => "");
                }
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "Add New Worker Failed", 'statusMsg' => "Failed to add worker", 'data' => "");
            }
        }

        public function editWorker($params, $client_id) {
            $db             = MysqliDb::getInstance();

            $worker_id      = trim($params['workerId']);
            $assign_id      = trim($params['assignId']);
            $workername     = trim($params['workername']);
            $username       = trim($params['username']);
            $password       = trim($params['password']);
            $phone          = trim($params['phone']);
            $shop_id        = trim($params['shopId']);
            $disabled       = trim($params['disabled']) ? : 0;

            if ($disabled == 0) {
                $activated = 1;
            }
            else {
                $activated = 0;
            }

            if (!$worker_id) {
                return array('status' => "error", 'code' => 1, 'msg' => "Worker's ID is required", 'statusMsg' => "Worker ID not found", 'data' => "");
            }
            if (!$workername) {
                return array('status' => "error", 'code' => 1, 'msg' => "Worker's name is required", 'statusMsg' => "Worker name cannot be empty", 'data' => "");
            }
            if (!$username) {
                return array('status' => "error", 'code' => 1, 'msg' => "Username is required", 'statusMsg' => "Username cannot be empty", 'data' => "");
            }
            if (!preg_match('/^[a-zA-Z](?:_?[a-zA-Z0-9]+)*$/', $username, $matches)) {
                return array('status' => "error", 'code' => 1, 'msg' => "Username invalid format", 'statusMsg' => "Username must be alphanumeric only", 'data' => "");
            }

            /* Check username exists or not */
            $db->where('id', $worker_id);
            $checkUser = $db->getOne('client');

            if (empty($checkUser)) {
                return array('status' => "error", 'code' => 1, 'msg' => "", 'statusMsg' => "Worker does not exist", 'data' => ""); 
            };

            /* Check duplicate username */
            $db->where('username', $username);
            $db->where('id', $checkUser['id'], '!=');
            $checkUsername = $db->getOne('client', 'username');

            if ($checkUsername && $checkUsername['username']) {
                return array('status' => "error", 'code' => 1, 'msg' => "Username duplication", 'statusMsg' => "Username already exists", 'data' => "");
            }

            /* Update with password */
            if (strlen($password) >= 8) {
                if (strlen($password) < 8) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01156"][$language] /* Password must be at least 8 characters. */, 'data' => "");
                }
                if (!preg_match('/^[a-z0-9@$!%*#?&]*$/', $password, $matches)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01157"][$language] /* Password must be alphanumeric and (@!%*#?&]) only. */, 'data' => "");
                }

                /* Encrypt password */
                $encryptedPassword = $db->encrypt($password);

                /* Update shop worker (password) */
                $updateWorkerData = array(
                    'name'          => $workername,
                    'username'      => $username,
                    'password'      => $encryptedPassword,
                    'disabled'      => $disabled,
                    'activated'     => $activated,
                    'updated_at'    => date('Y-m-d H:i:s')
                );
            }
            else {
                /* Update shop worker */
                $updateWorkerData = array(
                    'name'          => $workername,
                    'username'      => $username,
                    'activated'     => $activated,
                    'disabled'      => $disabled,
                    'updated_at'    => date('Y-m-d H:i:s')
                );
            }
            $db->where('id', $worker_id);
            $updateWorkerResult = $db->update('client', $updateWorkerData);

            if ($updateWorkerResult) {
                if ($shop_id) {
                    $db->where('id', $shop_id);
                    $checkShop = $db->getOne('shop');

                    if ($checkShop['id'] && $assign_id) {
                        $updateAssignData = array(
                            'client_id'     => $worker_id,
                            'shop_id'       => $checkShop['id'],
                            'assigned_at'   => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s')
                        );
                        $db->where('id', $assign_id);
                        $updateAssignResult = $db->update('assign_shop', $updateAssignData);
                    }
                    else {
                        $insertAssignData = array(
                            'client_id'     => $worker_id,
                            'shop_id'       => $checkShop['id'],
                            'assigned_at'   => date('Y-m-d H:i:s'),
                            'created_at'    => date('Y-m-d H:i:s')
                        );
                        $insertAssignResult = $db->insert('assign_shop', $insertAssignData);
                    }
                }
                else {
                    return array('status' => "ok", 'code' => 0, 'msg' => "Edit Worker", 'statusMsg' => "Update worker successfully", 'data' => "");
                }
                return array('status' => "ok", 'code' => 0, 'msg' => "Edit Worker", 'statusMsg' => "Update worker successfully and assigned to ".$shopname, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "Edit Worker Failed", 'statusMsg' => "Failed to update worker details", 'data' => "");
            }
        }

        public function getWorkerDetails($params, $client_id) {
            $db = MysqliDb::getInstance();

            $worker_id      = trim($params['workerId']);
            $assign_id      = trim($params['assignId']);

            if (!$worker_id) {
                return array('status' => "error", 'code' => 1, 'msg' => "", 'statusMsg' => "Worker doesn't exists", 'data' => "");
            }

            $db->where('client_id', $client_id);
            $result = $db->get('shop', null, 'id, name');
            $data['shopList'] = $result;

            if ($worker_id) {
                $db->where('client.id', $worker_id);
                $db->join('assign_shop', 'assign_shop.client_id = client.id', 'INNER');
                if ($assign_id) {
                    $db->joinWhere('assign_shop.id', $assign_id);
                    $db->join('shop', 'shop.id = assign_shop.shop_id', 'INNER');
                    $db->joinWhere('shop', 'shop.client_id', $client_id);
                }
                $result = $db->getOne('client', 'client.id as worker_id, client.name, client.dial_code, client.phone, assign_shop.id as assign_id, assign_shop.assigned_at, shop.id as shop_id, shop.name as shop_name');
            }

            if (!empty($result)) {
                $data += $result;
                // foreach($result as $key => $value) {
                // }
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'msg' => "", 'statusMsg' => "Worker doesn't exists", 'data' => "");
            }
        }
    }

?>