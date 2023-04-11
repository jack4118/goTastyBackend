<?php

	class Credit
    {
        function __construct($db, $general, $setting)
        {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }
        
        function getCredits($params) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            
            $searchData = $params['searchData'];
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'translation_code':
                            $db->where('translation_code', $dataValue);
                            break;
                            
                        case 'priority':
                            $db->where('priority', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get('credit', $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("credit", "count(id)");  
                foreach($result as $value) {

                    $credit['id'] = $value['id'];
                    $credit['name'] = $value['name'];
                    $credit['description'] = $value['description'];
                    $credit['translationCode'] = $value['translation_code'];
                    $credit['priority'] = $value['priority'];
                    $credit['createdAt'] = $value['created_at'];
                    $credit['updatedAt'] = $value['updated_at'];

                    $creditList[] = $credit;
                }
                
                $data['creditList'] = $creditList;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            } 
        }
        
        function addCredit($params){
            $db = $this->db;
            $setting = $this->setting;

            $creditName = trim($params['creditName']);
            $description = trim($params['description']);
            $creditDisplay = $params['creditDisplay'];
            // $translationCode = trim($params['translationCode']);
            $priority = trim($params['priority']);

            $languageResult = $setting->getActiveLanguages();
            $languageAry = $languageResult["data"];

            if(strlen($creditName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Credit Name", 'data'=>"");
            
            // if(strlen($description) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a Description", 'data'=>"");
            
            if(!$creditDisplay){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a Credit Display", 'data'=>"");
            }

            // check if required language is empty
            foreach($languageAry as $language){
                if(strlen($creditDisplay[$language]) == 0){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter $language Language", 'data'=>"");
                }
            }

            // if(strlen($translationCode) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a translation code", 'data'=>"");
            
            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a priority", 'data'=>"");
            
            $db->where('name', $creditName);
            $result = $db->get('credit');

            if (!empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Duplicate Credit Name", 'data'=>"");
            

            // insert credit language
            $creditCode = $this->generateLanguageCode("C", "Credit Display");
            foreach($languageAry as $lang){
                $languageData = array("code" => $creditCode,
                                  "module" => "Credit Display",
                                  "language" => $lang,
                                  "site" => "System",
                                  "type" => "Dynamic",
                                  "content" => $creditDisplay[$lang],
                                  "created_at" => $db->now(),
                                  "updated_at" => $db->now());
            
                $db->insert("language_translation", $languageData);
            }

            $fields = array('name', 'description', 'translation_code', 'priority', 'created_at', 'updated_at');
            $values = array($creditName, $description, $creditCode, $priority, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
            $arrayData = array_combine($fields, $values);
            
            try{
                $creditID = $db->insert('credit', $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to add new credit', 'data'=>'');
            }
            
            $creditPreset = $db->tableExists('credit_setting_preset');
            if (!$creditPreset)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Credit Setting Preset table not found', 'data' => ''); 
            
            $cols = Array('name', 'value', 'type', 'reference', 'description');
            $result = $db->get('credit_setting_preset', null, $cols);
            
            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Credit Setting Preset table is empty.', 'data' => ''); 
             
            foreach($result as $array) {
                $tmpName[] = $array['name'];
                $tmpValue[] = $array['value'];
                $tmpType[] = $array['type'];
                $tmpRef[] = $array['reference'];
                $tmpDesc[] = $array['description'];
            }
            
            $creditSettingArr = array();
            $i = 0;
            foreach ($result as $array) {
                $creditSettingArr[$i]['name'] = $array['name'];
                $creditSettingArr[$i]['value'] = $array['value'];
                $creditSettingArr[$i]['type'] = $array['type'];
                $creditSettingArr[$i]['reference'] = $array['reference'];
                $creditSettingArr[$i]['description'] = $array['description'];
                $creditSettingArr[$i]['credit_id'] = $creditID;
                $i++;
            }
            
            try{
                $result = $db->insertMulti("credit_setting", $creditSettingArr);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to assign setting for this credit", 'data'=>"");
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> 'Successfully Added', 'data'=>'');
        }
        
        function getCreditDetails($params){
            $db = $this->db;
            $setting = $this->setting;

            $languageResult = $setting->getActiveLanguages();
            $languageAry = $languageResult["data"];

            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Credit", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get("credit", 1);
            
            if (!empty($result)) {
                
                $credit['id'] = $result[0]["id"];
                $credit['creditName'] = $result[0]["name"];
                $credit['description'] = $result[0]["description"];

                foreach($languageAry as $language){
                    $db->where("language", $language);
                    $db->where("code", $result[0]["translation_code"]);

                    $display = $db->getValue("language_translation", "content");

                    $credit["creditDisplay"][$language] = ($display != "" ? $display : "");
                }

                $credit['translationCode'] = $result[0]["translation_code"];
                $credit['priority'] = $result[0]["priority"];
                
                $data['creditDetails'] = $credit;
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);  
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Credit", 'data'=>"");
            }
        }
        
        function editCredit($params){
            $db = $this->db;
            $setting = $this->setting;

            $id = trim($params['id']);
            $creditName = trim($params['creditName']);
            $creditDisplay = $params['creditDisplay'];
            $description = trim($params['description']);
            $translationCode = trim($params['translationCode']);
            $priority = trim($params['priority']);

            $languageResult = $setting->getActiveLanguages();
            $languageAry = $languageResult["data"];

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit ID does not exist", 'data'=>"");

            if(strlen($creditName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Credit Name", 'data'=>"");
            
            // if(strlen($description) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter description", 'data'=>"");
            
            if(strlen($translationCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Translation Code", 'data'=>"");
            
            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter priority", 'data'=>"");
            
            // check if required language is empty
            foreach($languageAry as $language){
                if(strlen($creditDisplay[$language]) == 0){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter $language Language", 'data'=>"");
                }
            }

            // handle credit language
            foreach($creditDisplay as $lang => $display){
                $db->where("module", "Credit Display");
                $db->where("language", $lang);
                $db->where("code", $translationCode);

                $result = $db->getOne("language_translation");
                if(empty($result)){
                    $languageData = array("code" => $translationCode,
                                  "module" => "Credit Display",
                                  "language" => $lang,
                                  "site" => "System",
                                  "type" => "Dynamic",
                                  "content" => $creditDisplay[$lang],
                                  "created_at" => $db->now(),
                                  "updated_at" => $db->now());
            
                    $db->insert("language_translation", $languageData);

                }else{
                    $languageData = array("content" => $creditDisplay[$lang],
                                        "updated_at" => $db->now());
                    $db->where("module", "Credit Display");
                    $db->where("language", $lang);
                    $db->where("code", $translationCode);
                    $db->update("language_translation", $languageData);
                }
            }

            $db->where('id', $id);
            $result = $db->get('credit', 1);

            if (!empty($result)) {
                $db->where('name', $creditName);
                $db->where('id !='.$id);
                $result = $db->get('credit');
                if (!empty($result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Duplicate Credit Name", 'data'=>"");
                
                $fields = array('name', 'description', 'translation_code', 'priority', 'updated_at');
                $values = array($creditName, $description, $translationCode, $priority, date('Y-m-d H:i:s'));
                
                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update('credit', $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully Updated");
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No Result", 'data'=>"");
            }
        }
        
        function deleteCredit($params){
            $db = $this->db;

            $id = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Credit", 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('credit', 1);
            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit not found", 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->delete('credit');
            if(!$result)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete credit', 'data' => '');
             
            $db->where('credit_id', $id);
            $result = $db->delete('credit_setting');
            if(!$result)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete credit setting', 'data' => '');
            
            return $this->getCredits();
        }
        
        function getCreditSettingDetails($params){
            $db = $this->db;
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Credit", 'data'=> '');
            
            $db->where('credit_id', $id);
            
            $cols = Array ('id', 'name', 'value', 'admin', 'member');
            $result = $db->get('credit_setting', null, $cols);
            
            if (!empty($result)) {
                foreach($result as $array) {
                    $creditSettingID[] = $array['id'];
                    $name[] = $array['name'];
                    $value[] = $array['value'];
                    $admin[] = $array['admin'];
                    $member[] = $array['member'];
                }
                
                $credit['creditSettingID'] = $creditSettingID;
                $credit['name'] = $name;
                $credit['value'] = $value;
                $credit['admin'] = $admin;
                $credit['member'] = $member;
                
                $data['creditSetting'] = $credit;
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);  
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Credit Settings Found", 'data'=>'');
            }
        }
        
        function editCreditSetting($params){
            $db = $this->db;
            
            $creditID = $params['creditID'];
            $id = $params['id'];
            $values = $params['values'];
            $admin = $params['admin'];
            $member = $params['member'];

            $fields = array('value', 'admin', 'member');
            foreach($id as $key=>$val) {
                $data = array($values[$key], $admin[$key], $member[$key]);
                $arrayData = array_combine($fields, $data);
                
                $db->where('credit_id', $creditID);
                $db->where('id', $val);
                try {
                    $db->update('credit_setting', $arrayData);
                }
                catch (Exception $e) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Fail to update completely.", 'data'=>"");
                }
            }

            // handle for permission table and client rights
            $result = $db->rawQuery("SELECT a.name AS creditName, a.priority AS creditPriority, b.credit_id, b.name, b.value, b.admin, b.member FROM credit AS a, credit_setting AS b WHERE b.credit_id = '".$creditID."' AND a.id = b.credit_id");
            foreach($result as $value){
                if($value["name"] == "isWallet" && $value["value"] == "1"){
                    $mainPermissions[] = array("name" => $value["creditName"],
                                       "description" => "Credit Type Page",
                                       "type" => "Sub Menu",
                                       "parent_id" => "(SELECT a.id FROM permissions a WHERE a.name = 'Accounts' and a.parent_id = 0)",
                                       "file_path" => "memberDetailsList.php?type=".$value["creditName"],
                                       "priority" => $value["creditPriority"],
                                       "site" => "Admin",
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s"),
                                       "reference_table" => "credit",
                                       "reference_id" => $value["creditPriority"]
                                      );
            
                    $mainPermissions[] = array("name" => $value["creditName"],
                                       "description" => $value["creditName"]." Transaction Listing Page",
                                       "type" => "Sub Menu",
                                       "parent_id" => "(SELECT a.id FROM permissions a WHERE a.name = 'Credit Transaction' and a.parent_id = 0)",
                                       "file_path" => "creditTransactionList.php?type=".$value["creditName"],
                                       "priority" => $value["creditPriority"],
                                       "site" => "Admin",
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s"),
                                       "reference_table" => "credit",
                                       "reference_id" => $value["creditPriority"]
                                      );
                }

                if($value["name"] == "isWithdrawable" && $value["value"] == "1"){
                    $permissions[] = array("name" => $value["creditName"]." Withdrawal",
                                       "description" => "Credit Withdrawal Page",
                                       "type" => "Page",
                                       "parent_id" => "(SELECT a.id FROM permissions a WHERE a.name = '".$value["creditName"]."' AND a.description = 'Credit Type Page')",
                                       "file_path" => "memberWithdrawal.php?type=".$value["creditName"],
                                       "priority" => 1,
                                       "site" => "Admin",
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s"),
                                       );

                    $clientRights[] = array("name" => $value["creditName"]." Withdrawal",
                                        "description" => "Block Member to view this",
                                        "command" => "memberAddNewWithdrawal",
                                        "credit_id" => $value["credit_id"],
                                        "status" => "on");
                }

                if ($value["name"] == "isTransferable" && $value["value"] == "1") {
                
                    $permissions[] = array("name" => $value["creditName"]." Transfer",
                                           "description" => "Credit Transfer Page",
                                           "type" => "Page",
                                           "parent_id" => "(SELECT a.id FROM permissions a WHERE a.name = '".$value["creditName"]."' AND a.description = 'Credit Type Page')",
                                           "file_path" => "memberWithdrawal.php?type=".$value["creditName"],
                                           "priority" => 1,
                                           "site" => "Admin",
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s"),
                                           );
                    
                    $permissions[] = array("name" => $value["creditName"]." Transfer Confirmation",
                                           "description" => "Credit Transfer Confirmation Page",
                                           "type" => "Page",
                                           "parent_id" => "(SELECT a.id FROM permissions a WHERE a.name = '".$value["creditName"]." Transfer' AND a.description = 'Credit Transfer Page')",
                                           "file_path" => "memberTransferConfirmation.php?type=".$value["creditName"],
                                           "priority" => 1,
                                           "site" => "Admin",
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s"),
                                           );
                    
                    $clientRights[] = array("name" => $value["creditName"]." Transfer",
                                            "description" => "Block Member to view this",
                                            "command" => "memberTransferCreditConfirmation",
                                            "credit_id" => $value["credit_id"],
                                            "status" => "on");
                    
                }
                
                if ($value["name"] == "isAdjustable" && $value["value"] == "1") {
                    
                    $permissions[] = array("name" => $value["creditName"]." Adjustment",
                                           "description" => "Credit Adjustment Page",
                                           "type" => "Page",
                                           "parent_id" => "(SELECT a.id FROM permissions a WHERE a.name = '".$value["creditName"]."' AND a.description = 'Credit Type Page')",
                                           "file_path" => "memberAdjustment.php?type=".$value["creditName"],
                                           "priority" => 1,
                                           "site" => "Admin",
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s"),
                                           );
                    
                }
            }

            foreach($mainPermissions as $permission){
                unset($check);
                $db->where("name", $permission["name"]);
                $db->where("type", $permission["type"]);
                $db->where("description", $permission["description"]);
                $check = $db->getOne("permissions");

                if(empty($check)){
                    $db->rawQuery("INSERT INTO permissions (`name`, `description`, `type`, `parent_id`, `file_path`, `priority`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`) VALUES ('".$permission["name"]."', '".$permission["description"]."', '".$permission["type"]."', ".$permission["parent_id"].", '".$permission["file_path"]."', '".$permission["priority"]."', '".$permission["site"]."', '".$permission["created_at"]."', '".$permission["updated_at"]."', '".$permission["reference_table"]."', '".$permission["reference_id"]."')");
                }


                // check role_permission table
                unset($permissionID);
                $db->where('name', $permission["name"]);
                $permissionID = $db->getValue('permissions', 'id');
                
                if($permissionID){
                    $db->where('permission_id', $permissionID);
                    $checkRole = $db->getOne('roles_permission');

                    if(!$checkRole){
                        $db->where('name', 'Admin');
                        $roleID = $db->getValue('roles', 'id');
                        $db->rawQuery("INSERT INTO `roles_permission` (`role_id`, `permission_id`, `disabled`, `created_at`, `updated_at`) VALUES ('".$roleID."', '".$permissionID."', '0', '".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."');");
                    }
                }
            }

            foreach($permissions as $permission){
                $db->where("name", $permission["name"]);
                $db->where("type", $permission["type"]);
                $check = $db->getOne("permissions");

                if(empty($check)){
                    $db->rawQuery("INSERT INTO permissions (`name`, `description`, `type`, `parent_id`, `file_path`, `priority`, `site`, `created_at`, `updated_at`, `reference_table`, `reference_id`) VALUES ('".$permission["name"]."', '".$permission["description"]."', '".$permission["type"]."', ".$permission["parent_id"].", '".$permission["file_path"]."', '".$permission["priority"]."', '".$permission["site"]."', '".$permission["created_at"]."', '".$permission["updated_at"]."', '".$permission["reference_table"]."', '".$permission["reference_id"]."')");
                }

                // check role_permission table
                unset($permissionID);
                $db->where('name', $permission["name"]);
                $permissionID = $db->getValue('permissions', 'id');
                
                if($permissionID){
                    $db->where('permission_id', $permissionID);
                    $checkRole = $db->getOne('roles_permission');

                    if(!$checkRole){
                        $db->where('name', 'Admin');
                        $roleID = $db->getValue('roles', 'id');
                        $db->rawQuery("INSERT INTO `roles_permission` (`role_id`, `permission_id`, `disabled`, `created_at`, `updated_at`) VALUES ('".$roleID."', '".$permissionID."', '0', '".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."');");
                    }
                }
            }

            foreach($clientRights as $rights){
                $db->where("name", $rights["name"]);
                $check = $db->getOne("mlm_client_rights");

                if(empty($check)){
                    $db->rawQuery("INSERT INTO `mlm_client_rights` (`id`, `name`, `description`, `parent_id`, `level`, `priority`, `module`, `category`, `command`, `credit_id`, `translation_code`, `status`) VALUES (NULL, '".$rights["name"]."', '".$rights["description"]."', '0', '0', '0', '', '', '".$rights["command"]."', '".$rights["credit_id"]."', '', '".$rights["status"]."');");
                }
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Successfully updated.', 'data' => '');
        }

        function generateLanguageCode($code, $module) {
            $db = $this->db;
            
            $result = $db->rawQuery("SELECT code FROM language_translation WHERE code LIKE '".$code."%' AND module = '".$module."' ORDER BY code DESC LIMIT 1");
            if(!empty($result)){
                foreach($result as $row){
                    $row["code"] = (int)substr($row["code"], 1, strlen($row["code"]));
                    for ($i=4; $i>=strlen($row["code"]); $i--) {
                        $leadingZero .= "0";
                    }
                    $newCode = $leadingZero.($row["code"] + 1);
                    if (strlen($newCode) > 5) {
                        $newCode = substr($newCode, 1, strlen($newCode));
                    }
                    $newCode = $code.$newCode;
                }

            }else{
                $newCode = $code."00001";
            }

            return $newCode;
        }
	}

?>
