<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the API Related Database code.
     * Date  29/06/2017.
    **/
    class Api {
        
        function __construct($db, $general) {
            $this->db = $db;
            $this->general = $general;
        }
        
        public function getAPIParams($params)
        {
            $db = $this->db;
            
            $apiID = $params['apiID'];
            
            $db->where("api_id", $apiID);
            $result = $db->get("api_params", null, "id, params_name, params_type, web_input_type, compulsory");
            foreach ($result as $row)
            {
                $apiParams['id'] = $row["id"];
                $apiParams['paramsName'] = $row["params_name"];
                $apiParams['paramsType'] = $row["params_type"];
                $apiParams['webInputType'] = $row["web_input_type"];
                $apiParams['compulsory'] = $row["compulsory"];
                $data['apiParams'][] = $apiParams;
            }
            
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        
        /**
         * Function for getting the Api List.
         * @param NULLL.
         * @author Rakesh.
        **/
        public function apiList($apiParams) {
            
            $db = $this->db;
            $general = $this->general;
            $pageNumber = $apiParams['pageNumber'] ? $apiParams['pageNumber'] : 1;

            $searchData = $apiParams['searchData'];

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'command':
                            $db->where('command', $dataValue);
                            break;

                        case 'status':
                            $db->where('disabled', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $copyDb = $db->copy();

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            $db->orderBy('id', 'DESC');
            $result = $db->get('api', $limit, 'id, command, description, duration, no_of_queries, disabled');

            // $result = $db->rawQuery("SELECT id, command, description, duration, no_of_queries, disabled FROM                    api order by id desc LIMIT ". $db->escape($limit[0]).", ". $db->escape($limit[1])." ");

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("api", "count(id)");
                foreach($result as $value) {

                    $api['id'] = $value['id'];
                    $api['command'] = $value['command'];
                    $api['description'] = $value['description'];
                    $api['duration'] = $value['duration'];
                    $api['noOfQueries'] = $value['no_of_queries'];
                    $api['apiStatus'] = ($value['disabled'] == 0) ? 'Active' : 'Disabled';

                    $apiList[] = $api;
                }
                
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['apiData']      = $apiList;
                $data['totalRecord']  = $totalRecord;
                $data['numRecord']    = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for saving the New Api.
         * @param $apiParams.
         * @author Rakesh.
        **/
        public function newApi($apiParams, $userID) {
            $db = $this->db;
            $command        = $db->escape($apiParams['commandName']);
            $description    = $db->escape($apiParams['description']);
            $duration       = $db->escape($apiParams['duration']);
            $no_of_queries  = $db->escape($apiParams['queries']);
            $status         = $db->escape($apiParams['apiStatus']);

            if(strlen($command) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Command", 'data'=>"commandError");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Description", 'data'=>"descriptionError");

            if(strlen($duration) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Duration", 'data'=>"DurationError");

            if(strlen($no_of_queries) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter No of queries", 'data'=>"No_of_queriesError");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Status", 'data'=>"statusError");

            $fields    = array("command", "description", "duration", "no_of_queries", "created_at", "disabled");
            $values    = array($command, $description, $duration, $no_of_queries, date("Y-m-d H:i:s"), $status);
            $arrayData = array_combine($fields, $values);

            $result = $db->insert("api", $arrayData);
            if (is_numeric($result)) {
                $result = true;
            } else {
                $result = false;
            }

            $api['result'] = $result;

            $data['apiSave'] = $api;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        /**
         * Function for Delete the Api.
         * @param $apiId.
         * @author Rakesh.
        **/
        public function deleteApi($apiId){
            $db = $this->db;
            $id = trim($apiId['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Api.", 'data'=> '');
            $id = $db->escape($id);

            $fields    = array("deleted", "updated_at");
            $values    = array("1", date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $db->where ('id', $id);
            $result = $db->update ('api', $arrayData);

            if (!empty($result)) {
                if($result) {
                    return $this->apiList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete.', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Api.", 'data'=>"");
            }
        }

        /**
         * Function for the Edit Api.
         * @param $apiParams.
         * @author Rakesh.
        **/
        public function editApi($apiParams, $userID) {
            $db = $this->db;
            $command       = $db->escape($apiParams['commandName']);
            $description   = $db->escape($apiParams['description']);
            $duration      = $db->escape($apiParams['duration']);
            $no_of_queries = $db->escape($apiParams['queries']);
            $status        = $db->escape($apiParams['apiStatus']);

            if(strlen($command) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Command", 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Description", 'data'=>"");

            if(strlen($duration) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Duration", 'data'=>"");

            if(strlen($no_of_queries) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter No of queries", 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Status", 'data'=>"");

            $fields    = array("command", "description", "duration", "no_of_queries", "updated_at", "disabled");
            $values    = array($command, $description, $duration, $no_of_queries, date("Y-m-d H:i:s"), $status);
            $arrayData = array_combine($fields, $values);

            //Get the Old data from API
            $db->where("id", $apiParams["id"]);
            $oldData = $db->getOne("api");

            $db->where ('id', $apiParams['id']);
            $result = $db->update ('api', $arrayData);

            //Get the Old data from API
            $db->where("id", $apiParams["id"]);
            $newData = $db->getOne("api");

            if($result) {
                $api['result'] = $result;
                
                $data['apiEdit'] = $api;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "no", 'code' => 1, 'statusMsg' => '', 'data' => '');
            }
        }

        /**
         * Function for the load the Api data in Edit.
         * @param $apiId.
         * @author Rakesh.
        **/
        public function getEditApiData($apiId){
            $db = $this->db;
            $db->where ("id", $apiId['id']);
            $result = $db->getOne ("api");

            if (!empty($result)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
            }else{
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No Result", 'data'=>"");
            }
        }

        /**
         * Function 
         * @param 
         * @author Alan Low
        **/
        public function getApiSampleData($params){
            $db = $this->db;
            $db->where ("api_id", $params['apiID']);
            $result = $db->getOne ("api_sample");
            
            $data = (array) json_decode($result['output'],true);

            if ($data) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data'=>"");
            }
        }

        /**
         * Function 
         * @param 
         * @author Alan Low
        **/
        public function editApiSampleData($params) {

            $db = $this->db;

            $apiID     = $params['apiID'];
            $status    = $params['status'];
            $code      = $params['code'];
            $statusMsg = $params['statusMsg'];
            $userData  = $params['data'];

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Status", 'data'=>'');
            
            if(strlen($code) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Code", 'data'=>'');
            
            if(empty($userData))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Data", 'data'=>'');

            $output = array('status'    => $status,
                            'code'      => $code,
                            'statusMsg' => $statusMsg,
                            'data'      => $userData);
            $output = json_encode($output);
            $fields    = array("api_id", "output");
            $values    = array($apiID, $output);
            $arrayData = array_combine($fields, $values);
            
            $db->where('api_id', $apiID);
            $getResult = $db->getOne('api_sample');
            if($getResult) {
                $db->where('api_id', $apiID);
                $result = $db->update ('api_sample', $arrayData);
            }
            else {
                $result = $db->insert ('api_sample', $arrayData);
            }

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Sample output saved successfully.', 'data' => $data);
            }else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to save sample output.', 'data' => '');
            }
        }

        /**
         * Used Searching the API.
         * @param $param Search Parameters.
        **/
        function getApiSearchData($params){
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];

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
            $copyDb = $db->copy();
            $result       = $db->get('api', $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("api", "count(id)");
                $timeStamp = array();
                $description = array();
                $patchBy = array();
                $patchOn = array();
                foreach($result as $value) {
                    $id[]            = $value['id'];
                    $command[]       = $value['command'];
                    $description[]   = $value['description'];
                    $duration[]      = $value['duration'];
                    $no_of_queries[] = $value['no_of_queries'];
                    $apiStatus[]     = ($value['disabled'] == 1) ? 'On' : 'Off';
                }

                $api['ID']            = $id;
                $api['Command']       = $command;
                $api['Description']   = $description;
                $api['Duration']      = $duration;
                $api['No_of_queries'] = $no_of_queries;
                $api['Status']        = $apiStatus;
                
                $data['apiData']      = $api;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord']  = $totalRecord;
                $data['numRecord']    = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for getting the Api Params List.
         * @param NULLL.
         * @author Rakesh.
        **/
        public function getApiParameterData($apiParams) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $apiParams['pageNumber'] ? $apiParams['pageNumber'] : 1;
            $searchData = $apiParams['searchData'];
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
            if ($apiParams['apiId']) {
                $db->where("api_id", $apiParams["apiId"]);
            }

            //Get the limit.
            $limit = $general->getLimit($pageNumber);
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            //$db->where("deleted", 0);
            $result = $db->get("api_params", $limit);
            
            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("api_params", "count(id)");
                $tmp = array();
                foreach($result as $value) {

                    $api['id']    = $value['id'];
                    $api['name']  = $value['params_name'];
                    $api['type']  = $value['params_type'];

                    $apiParam[] = $api;
                }

                $data['apiParam']    = $apiParam;
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
         * Function for saving the New Api Param.
         * @param $apiParams.
         * @author Rakesh.
        **/
        public function newApiParam($apiParams, $userID) {
            $db = $this->db;
            $paramName = $db->escape($apiParams['apiParamName']);
            $paramType = $db->escape($apiParams['apiParamVal']);
            $apiId     = $db->escape($apiParams['apiId']);

            if(strlen($paramName) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Parameter Name.", 'data'=>"commandError");
            
            if(strlen($paramType) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Parameter Type.", 'data'=>"descriptionError");

            $checkDuplicate = $db->rawQuery("SELECT * FROM api_params WHERE api_id ='". $db->escape($apiId). "' AND params_name = '". $db->escape($paramName)."' ");

            if(!empty($checkDuplicate)) {
                $api['result'] = "duplicateData";
                $data['apiSave'] = $api;
                return array('status' => "ok", 'code' => 1, 'statusMsg' => 'Duplidate Data', 'data' => $data);
            }else if(empty($checkDuplicate)){
                $fields    = array("api_id", "params_name", "params_type", "created_at");
                $values    = array($apiId, $paramName, $paramType, date("Y-m-d H:i:s"));
                $arrayData = array_combine($fields, $values);
                $result    = $db->insert("api_params", $arrayData);
                if (is_numeric($result)) {
                    // ######### Activity Log -- Journal Log. #########
                    // $db->where("id", $result);
                    // $activityData = $db->getOne("api_params");
                    // $log = $db->activityLog("api_params", "add", $fields, $activityData, $userID, "");
                    // ######### Activity Log -- Journal Log. #########

                    $result = true;
                }else {
                    $result = false;
                }

                $api['result'] = $result;
                $api['saved']  = "saved";
                
                $data['apiSave'] = $api;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
        }

        /**
         * Function for the getting the Api name.
         * @param NULL.
         * @author Rakesh.
         * @return Array.
        **/
        public function getApiName() {
            $db = $this->db;
            $apiNames = $db->rawQuery('SELECT id, command, site FROM api order by command asc');
            if (!empty($apiNames)) {
                foreach($apiNames as $value){
                    $id[] = $value['id'];
                    $command[] = $value['command'];
                    $site[] = $value['site'];
                }
                
                $api['id'] = $id;
                $api['command'] = $command;
                $api['site'] = $site;
                
                $data['apiName'] = $api;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for the load the Api Param data in Edit.
         * @param $apiParamId.
         * @author Rakesh.
        **/
        public function getEditParamData($apiParam){
            $db = $this->db;
            $db->where ("id", $apiParam['id']);
            $result = $db->getOne ("api_params");

            if (!empty($result)) {
                // $api['ID']        = $result['id'];
                // $api['apiId']     = $result['api_id'];
                // $api['paramName'] = $result['params_name'];
                // $api['paramType'] = $result['params_type'];
                
                // $data['apiParamData'] = $api;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
            }else{
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No Result", 'data'=>"");
            }
        }

        /**
         * Function for the Edit Api Parameter.
         * @param $apiParams.
         * @author Rakesh.
        **/
        public function editParam($apiParams) {
            $db = $this->db;
            $apiParamName   = $apiParams['apiParamName'];
            $apiParamVal    = $apiParams['apiParamVal'];
            $apiId          = $apiParams['apiId'];

            if(empty($apiParamName) || !isset($apiParamName)) {
                $api['result'] = "apiParamName";
                $data['apiParamEdit'] = $api;
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please enter Api parameter.", 'data'=>$data);
            }

            if(empty($apiParamVal) || !isset($apiParamVal)) {
                $api['result'] = "apiParamVal";
                $data['apiParamEdit'] = $api;
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select Parameter type.", 'data'=> $data);
            }

            if(empty($apiId) || !isset($apiId)) {
                $api['result'] = "apiName";
                $data['apiParamEdit'] = $api;
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select Api.", 'data'=>$data);
            }

            $checkDuplicate = $db->rawQuery("SELECT * FROM api_params WHERE api_id ='". $apiId. "' AND id ='". $apiParamName. "' AND id !='". $apiParams['id']. "' AND params_type = '". $apiParamVal."' ");

            if(!empty($checkDuplicate)) {
                $api['result'] = $checkDuplicate;
                $data['apiParamEdit'] = $api;
                return array('status' => "ok", 'code' => 1, 'statusMsg' => 'Duplidate Data', 'data' => $data);
            }else if(empty($checkDuplicate)){ 
                $dbFields    = array("api_id", "params_name", "params_type", "updated_at");
                $dbValues = array($apiId, $apiParamName, $apiParamVal, date("Y-m-d H:i:s"));
                $apiParamData = array_combine($dbFields, $dbValues);

                $db->where ('id', $apiParams['id']);
                $result = $db->update ('api_params', $apiParamData);
            }

            if($result) {
                $api['result']  = $result;
                $api['message'] = "updated";
                $data['apiParamEdit'] = $api;
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "no", 'code' => 1, 'statusMsg' => '', 'data' => '');
            }
        }

        /**
         * Function for Delete the Api Param.
         * @param $apiId.
         * @author Rakesh.
        **/
        function deleteApiParam($apiParamId) {
            $db = $this->db;
            $id = trim($apiParamId['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Api parameter", 'data'=> '');

            $fields    = array("deleted", "updated_at");
            $values    = array("1", date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $db->where ('id', $id);
            $result = $db->update ('api_params', $arrayData);

            if (!empty($result)) {
                if($result) {
                    return $this->getApiParameterData();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Api Parameter", 'data'=>"");
            }
        }

        /**
         * Function for seacrhing the Api Param.
         * @param $apiSearchParams.
         * @author Rakesh.
        **/
        public function searchParamHistory($params) {
            $db = $this->db;
            $general = $this->general;
            
            $searchData = $params['searchData'];
            $pageNumber = $apiParams['pageNumber'] ? $apiParams['pageNumber'] : 1;
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
            
            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            $copyDb = $db->copy();
            $result       = $db->get("api_params", $limit);
            
            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("api_params", "count(id)");
                $timeStamp   = array();
                $description = array();
                $patchBy     = array();
                $patchOn     = array();
                foreach($result as $value) {
                    $id[]            = $value['id'];
                    $paramName[]     = $value['params_name'];
                    $paramType[]     = $value['params_type'];
                }

                $api['ID']                = $id;
                $api['Parameter_Name']    = $paramName;
                $api['Parameter_Type']    = $paramType;

                $data['apiParam']     = $api;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for getting the Api details.
         * @param $command.
         * @author Rakesh.
         * @return Array.
        **/
        public function getOneApi($command) {
            $db = $this->db;
            
            $db->where("command", $command);
            $result = $db->getOne ("api");

            // API does not exist in api table
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid API.', 'data' => '');

            // API is disabled
            if($result['disabled'] == 1)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid API.', 'data' => '');

            // API is deleted
            if($result['deleted'] == 1)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid API.', 'data' => '');

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
        }

        function apiParamsScriptCheck($array, $check) {
            $db = $this->db;
            if($check) {
                foreach($array as $key => $val) {
                    if(is_array($val)) {
                        if($check)
                            $this->apiParamsScriptCheck($val,$check);
                    } else {
                        if($val != strip_tags($val)){
                            $check = false;
                            break;
                        }else{
                            continue;
                        }
                    }
                }
            }
            return $check;
        }
        
        function checkApiParams($apiID, $params) {
            $db = $this->db;

            /*$check = TRUE;
            $check = $this->apiParamsScriptCheck($params, $check);
            if(!$check) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid value detected.', 'data'=>'');
            }*/
            
            $cols = array('params_name', 'params_type', 'compulsory', 'web_input_type');
            $db->where('api_id', $apiID);
            $db->where('deleted', 0);
            $result = $db->get('api_params', null, $cols);
            
            if(empty($result))
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>'');
            
            $db->where('disabled', 0);
            $specialChars = $db->get('special_characters', null, 'value');
            
            // $tmpA contains the key from the forms
            // $tmpB contains the key from db
            $tmpA = array_keys($params);

            $db->where('command', 'addTicket');
            $addTicketID = $db->getValue('api', 'id');

            if($apiID == 149){
            	unset($tmpA);
            	$tmpA = array("senderId", "ticketId", "message");
            } else if($apiID == $addTicketID) {
                unset($tmpA);
                $tmpA = array("clientID", "subject", "message", "type");
            }

            foreach($result as $array) {
                $tmpB[] = $array['params_name'];
            }
            
            $tmpdiff = array_diff($tmpA, $tmpB);
            if(count($tmpdiff) != 0)
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid value detected.', 'data'=>'');
                //Send notification stating there is a difference in api parameters

            // Set special characters here
            $this->setSpecialCharacters();
            
            foreach($result as $array) {
                $value = $params[$array['params_name']];
                $compulsory = $array['compulsory'];
                $paramsType = $array['params_type'];
                $webType = $array['web_input_type'];
                
                if(is_array($value)) {
                    $check = TRUE;
                    $this->apiParamsArrayCheck($value, $paramsType, $compulsory, $check);
                    if(!$check) {
                        $data['type'] = $webType;
                        $data['field'] = $array['params_name'];
//                            $data['compulsory'] = $compulsory;
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid value detected.', 'data'=>$data);
                    }
                }
                else {
                    $check = $this->paramsChecker($value, $paramsType, $compulsory);
                    if(!$check) {
                        $data['type'] = $webType;
                        $data['field'] = $array['params_name'];
//                        $data['compulsory'] = $compulsory;
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid value detected.', 'data'=>$data);
                    }
                }
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>'');
        }
        
        function paramsChecker($value, $paramsType, $compulsory) {
            // If the data from the forms are empty
            if(strlen($value) == 0) {
                // Check compulsory field in db
                $check = ($compulsory == 0)?true:false;
            }
            else {
                $pregCheck = 1;
                switch($paramsType) {
                    //Check for whole number
                    case "integer":
                        $check = is_numeric($value);
                        if($check) {
                            $pregCheck = preg_match('/[.]/', $value);
                            $check = ($pregCheck == 0)?true:false;
                        }
                        break;
                    //Check for whole number or floating point number
                    case "numeric":
                        $check = is_numeric($value);
                        break;
                    //Check for alphabet,number,space,punctuation,comma
                    case "alphanumeric":
                        $pregCheck = preg_match('/[^a-zA-Z0-9 .,]/', $value);
                        $check = ($pregCheck == 0)?true:false;
                        break;
                    case "general":
                        $specialChars = $this->getSpecialCharacters();
                        // Check if there are restrictions on special characters
                        if(empty($specialChars)) {
                            $check = true;
                            break;
                        }
                        foreach($specialChars as $array) {
                            $char = $array['value'];
                            $pregCheck = preg_match('/['.$char.']/', $value);
                            $check = ($pregCheck == 0)?true:false;
                            
                            if(!$check)
                                break;
                        }
                        break;
                    default:
                        $check = false;
                }
            }
            return $check;
        }
        
        function checkApiDuplicate($tblDate, $createTime, $userID, $sessionID, $site, $command, $duplicateInterval, $webserviceID) {
            $db = $this->db;
            
            $result = $db->rawQuery('SHOW TABLES LIKE "web_services_%"');
            
            $totalRows = count($result);
            
            // Get the latest webservices table date
            $wsDateArr = $result[$totalRows-1];
            $wsDateLatestArr = array_values($wsDateArr);
            $wsDateLatestArr = explode('_', $wsDateLatestArr[0]);
            $wsDateCurr = $wsDateLatestArr[2];
            
            // Get the previous webservices table
            $wsDateArr = $result[$totalRows-2];
            $wsDatePrevArr = array_values($wsDateArr);
            $wsDatePrevArr = explode('_', $wsDatePrevArr[0]);
            $wsDatePrev = $wsDatePrevArr[2];
            
            // Check whether the input data is on the same date as our latest webservices table
            $check = 0;
            $tsTo = strtotime($createTime);
            $tsFrom = $tsTo - $duplicateInterval;
            $tsFrom = date('Y-m-d H:i:s', $tsFrom);
            
            unset($clientCount, $wsServiceIDArr);

            if($tblDate == $wsDateCurr) {
                $wsResult = $this->wsDataIn($userID, $site, $command, $tsFrom, $createTime, $wsDateCurr);
                foreach ($wsResult as $wsResultRow) {
                    $wsServiceIDArr[$wsResultRow['id']] = $wsResultRow['id'];
                    $clientCount[$wsResultRow['client_id']]+=1;
                }
            }
            
            $wsResRows = count($wsResult);
            if($wsResRows <= 1) {
                if($dateFrom == $wsDateCurr)
                    return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>''); // return here no need to perform further checking
            }
            
            $currDataIn = $wsResult[$wsResRows-1]['data_in'];
            $currDataIn = str_replace('&nbsp', '', $currDataIn);
            $currDataIn = explode('params : <br>', $currDataIn);
            $currParams = $currDataIn[1];
            
            // First comparison with current daily webservices table
            $iter = 0;
            while($iter < $wsResRows - 1) {
                $clientID   = $wsResult[$iter]['client_id'];
                $prevDataIn = $wsResult[$iter]['data_in'];
                $prevDataIn = str_replace('&nbsp', '', $prevDataIn);
                $prevDataIn = explode('params : <br>', $prevDataIn);
                if((strcmp($currParams,$prevDataIn[1]) == 0) && ($clientCount[$clientID]>1)){
                    if(MIN($wsServiceIDArr) == $webserviceID){
                        return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>''); // 
                    }else{
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Duplicate values detected.', 'data'=>''); // return here no need to perform further checking
                    }
                }
                $iter++;
            }
            
            $dateFrom = date('Ymd', $tsTo - $duplicateInterval);
            // Check whether the duplicate interval includes the previous day webservices table
            if($dateFrom != $wsDatePrev)
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>''); // return here no need to perform further checking
            
            // Get the previous day webservices table
            $wsResult = $this->wsDataIn($userID, $site, $command, $tsFrom, $createTime, $wsDatePrev);
            $wsResRows = count($wsResult);
            if($wsResRows <= 1) {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>''); // return here no need to perform further checking
            }
            
            // Second comparison with previous daily webservices table
            $iter = 0;
            while($iter < $wsResRows) {
                $prevDataIn = $wsResult[$iter]['data_in'];
                $prevDataIn = str_replace('&nbsp', '', $prevDataIn);
                $prevDataIn = explode('params : <br>', $prevDataIn);
                if(strcmp($currParams,$prevDataIn[1]) == 0)
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Duplicate values detected.', 'data'=>''); // return here no need to perform further checking
                $iter++;
            }
            
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data'=>'');      
        }
        
        function wsDataIn($userID, $site, $command, $timeFrom, $timeTo, $wsDate) {
            $db = $this->db;
            
            $db->where('client_id', $userID);
            $db->where('site', $site);
            $db->where('command', $command);
            $db->where('created_at', array($timeFrom, $timeTo), 'BETWEEN');
            
            $wsResult = $db->get('web_services_'.$wsDate, null, 'id, client_id, data_in, status');
            return $wsResult;
        }
        
        function getSampleOutput($apiID) {
            $db = $this->db;
            
            $db->where('api_id', $apiID);
            $result = $db->getValue('api_sample', 'output');
            
            if($result) {
                $output = (array) json_decode($result,true);
            
                $status = $output['status'];
                $code = $output['code'];
                $statusMsg = $output['statusMsg'];
                $outputData = $output['data'];
                return array('status' => $status, 'code' => $code, 'statusMsg' => $statusMsg, 'data'=>$outputData);   
            }
            else {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Sample data output does not exist.', 'data'=>'');   
            }
        }

        function setSpecialCharacters() {
            $db = $this->db;

            $db->where('disabled', 0);
            $this->specialCharacters = $db->get('special_characters', null, 'value');
        }

        function getSpecialCharacters() {
            return $this->specialCharacters;
        }

        // Function to loop nested arrays to check
        function apiParamsArrayCheck($array, $paramsType, $compulsory, &$check) {
            $db = $this->db;

            if($check) {
                foreach($array as $key => $val) {
                    if(is_array($val)) {
                        if($check)
                            $this->apiParamsArrayCheck($val, $paramsType, $compulsory, $check);
                    } else {
                        $check = $this->paramsChecker($val, $paramsType, $compulsory);
                        if(!$check)
                            continue;
                    }
                }
            }
        }
    }

    ?>
