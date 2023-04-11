<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the API Related Database code.
     * Date  29/06/2017.
    **/

    class Provider {
        
        function __construct($db)
        {
            $this->db = $db;
        }
        
        public function getProvider()
        {
            $db = $this->db;
        
            $providerRes = $db->get('provider');
            foreach($providerRes as $providerRow)
            {
                $providerArray[$providerRow['name']] = $providerRow;
            }
            
            return $providerArray;
        }
        
        /**
         * Function for getting the Provider List.
         * @param  $providerParams.
         * @author Rakesh.
        **/
        public function getProviderData($params) {
            global $db, $general;
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $searchData = $params['searchData'];

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where("name", $dataValue); 
                            break;

                        case 'username':
                            $db->where('username', $dataValue);
                            break;
                            
                        case 'company':
                            $db->where('company', $dataValue); 
                            break;
                            
                        case 'disabled':
                            $db->where('disabled', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->orderBy("id", "asc");
            $copyDb = $db->copy();
            $result = $db->get("provider", $limit, "id, name, username, company, api_key as apiKey, type, priority, url1, url2, balance, disabled as status");

            if (!empty($result)) {

                foreach($result as $array) {
                    $array["status"] = ($array["status"] == 0) ? "Active" : "Disabled";

                    $provider[] = $array;
                }

                $totalRecord = $copyDb->getValue ("provider", "count(id)");
                $data['providerList'] = $provider;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord']  = $totalRecord;
                $data['numRecord']    = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for saving the New Provider.
         * @param $providerParams.
         * @author Rakesh.
        **/
        public function newProvider($providerParams) {
            global $db;

            $command        = $providerParams['commandName'];
            $username       = $providerParams['username'];
            $password       = $providerParams['password'];
            $company        = $providerParams['company'];
            $apiKey        = $providerParams['apiKey'];
            $type           = $providerParams['type'];
            $priority       = $providerParams['priority'];
            $disabled       = $providerParams['disabled'];
            $defaultSender = $providerParams['defaultSender'];
            $url1           = $providerParams['url1'];
            $url2           = $providerParams['url2'];
            $remark         = $providerParams['remark'];
            $name  = $providerParams['name'];
            $currency       = $providerParams['currency'];
            $balance        = $providerParams['balance'];

            if(strlen($command) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Invalid command.", 'data'=>"command");
            
            if(strlen($username) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Username.", 'data'=>"username");

            if(strlen($company) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Company.", 'data'=>"company");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Type.", 'data'=>"type");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Status.", 'data'=>"disabled");

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Name.", 'data'=>"name");

            $fields    = array("company", "username", "password", "api_key", "type", "priority", "disabled", "default_sender", "url1", "url2", "remark", "name", "currency", "balance", "created_at");
            $values    = array($company, $username, $password, $apiKey, $type, $priority, $disabled, $defaultSender, $url1, $url2, $remark, $name, $currency, $balance, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $result = $db->insert("provider", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Setting Successfully Saved"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Setting", 'data'=>"");
            }
        }

        /**
         * Function for Delete the Provider.
         * @param $providerId.
         * @author Rakesh.
        **/
        public function deleteProvider($params){
            global $db;
            $id = trim($params['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Provider.", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne('provider');

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('provider');
                if($result) {
                    return $this->getProviderData($params);
                } else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete.', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Provider.", 'data'=>"");
            }
        }

        /**
         * Function for the Edit Provider.
         * @param $providerParams.
         * @author Rakesh.
        **/
        public function editProvider($providerParams) {
            global $db;
            $command        = $providerParams['commandName'];
            $username       = $providerParams['username'];
            $password       = $providerParams['password'];
            $company        = $providerParams['company'];
            $apiKey        = $providerParams['apiKey'];
            $type           = $providerParams['type'];
            $priority       = $providerParams['priority'];
            $disabled       = $providerParams['disabled'];
            $defaultSender = $providerParams['defaultSender'];
            $url1           = $providerParams['url1'];
            $url2           = $providerParams['url2'];
            $remark         = $providerParams['remark'];
            $name  = $providerParams['name'];
            $currency       = $providerParams['currency'];
            $balance        = $providerParams['balance'];

            if(strlen($command) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Invalid command.", 'data'=>"commandError");
            
            if(strlen($name) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Provider Name.", 'data'=>"statusError");

            if(strlen($username) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Username.", 'data'=>"descriptionError");

            if(strlen($company) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Company.", 'data'=>"No_of_queriesError");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Type.", 'data'=>"statusError");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Status.", 'data'=>"statusError");

            $fields    = array("company", "username", "password", "api_key", "type", "priority", "disabled", "default_sender", "url1", "url2", "remark", "name", "currency", "balance", "created_at", "updated_at");
            $values    = array($company, $username, $password, $apiKey, $type, $priority, $disabled, $defaultSender, $url1, $url2, $remark, $name, $currency, $balance, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $db->where ('id', $providerParams['id']);
            $result = $db->update ('provider', $arrayData);

            if($result) {
                $provider['result'] = $result;
                
                $data['providerRes'] = $provider;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "no", 'code' => 1, 'statusMsg' => '', 'data' => '');
            }
        }

        /**
         * Function for the load the Provider data in Edit.
         * @param $providerId.
         * @author Rakesh.
        **/
        public function getEditProviderData($providerId){
            global $db;
            $db->where ("id", $providerId['id']);
            $result = $db->getOne("provider");
            if (!empty($result)) {
                $provider['id']             = $result['id'];
                $provider['company']        = $result['company'];
                $provider['name']  = $result['name'];
                $provider['username']       = $result['username'];
                $provider['password']       = $result['password'];
                $provider['api_key']        = $result['api_key'];
                $provider['type']           = $result['type'];
                $provider['priority']       = $result['priority'];
                $provider['disabled']       = $result['disabled'];
                $provider['default_sender'] = $result['default_sender'];
                $provider['url1']           = $result['url1'];
                $provider['url2']           = $result['url2'];
                $provider['remark']         = $result['remark'];
                $provider['currency']       = $result['currency'];
                $provider['balance']        = $result['balance'];
                $provider['currency']       = $result['currency'];
                
                $data['providerData'] = $provider;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No Result", 'data'=>"");
            }
        }

        /**
         * Function for the getting the Message Type.
         * @param NULL.
         * @author Rakesh.
         * @return Array.
        **/
        public function getMessageType() {
            global $db;

            $messageType = $db->rawQuery('SELECT name FROM provider where type = "notification" order by name asc');

            if (!empty($messageType)) {
               foreach($messageType as $messageTypeValue) {
                   $msgType[] = $messageTypeValue['name'];
               }
                $message['messageType'] = $msgType;
                $data['messageData'] = $message;
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }
        
        public function getProviderIDByName($name) {
            $db = $this->db;
            
            $name = trim($name);
            if(strlen($name) == 0)
                return false;
            
            $db->where('name', $name);
            $result = $db->getValue('provider', 'id');
            
            return $result;
        }
    }

    ?>
