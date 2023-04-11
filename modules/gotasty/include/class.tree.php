<?php
    
    /**
     * Tree Stucture Class - Used for retrieving and setting hierachical structure in the system
     */
    
    class Tree {
        
        /**
         * Database instance
         */
        protected $db;
        protected $setting;
        
        function __construct() {
        	
        }
        
        public function getSponsorDownlineByClientID($clientID){
            $db = MysqliDb::getInstance();
            
            $db->where("client_id", $clientID);
            $clientTraceKey = $db->getValue("tree_sponsor", "trace_key");
            
            // Find the downline with the trace key
            $db->orderby("level", "asc");
            $db->orderby("id", "asc");
            $db->where("trace_key", $clientTraceKey."/%", "LIKE");
            $result = $db->get("tree_sponsor", null, "client_id");


            foreach ($result as $row)
            {
                $downlines[] = $row["client_id"];
            }
            return $downlines;
        }
        
        public function getSponsorUplineByClientID($clientID, $limit){
            $db = MysqliDb::getInstance();
            
            $db->where("client_id", $clientID);
            $clientTraceKey = $db->getValue("tree_sponsor", "trace_key");
            
            // Split the trace key to get the whole list of uplines
            $clientArray = explode("/", $clientTraceKey);
            if ($limit == 1)
            {
                $uplines[] = $clientArray[1];
            }
            else
            {
                for ($i=0; $i<count($clientArray)-1; $i++)
                {
                    $uplines[] = $clientArray[$i];
                }
            }
            
            // Sort the array by descending because we want to loop from bottom to top
            krsort($uplines);
            
            return $uplines;
        }
        
        function getSponsorByUsername($username){
            $db = MysqliDb::getInstance();

            $db->where("username", $username);
            $client = $db->getOne("client", "id,username,name,email,phone,country_id,sponsor_id");
            if(!$client) return false;

            $db->where("client_id", $client["id"]);
            $sponsor = $db->getOne("tree_sponsor","trace_key");
            if(!$sponsor) return false;

            $client["trace_key"] = $sponsor["trace_key"];


            return $client;
        }

        function getSponsorTreeUplines($clientID, $limit = null, $includeSelf = 1,$treeTable) {
            $db = MysqliDb::getInstance();

            $treeTableArr = array('tree_sponsor','tree_sponsor_cache');
            if(!in_array($treeTable, $treeTableArr));
            if(!$treeTable) $treeTable = "tree_sponsor";

            $db->where("client_id", $clientID);
            $result = $db->getOne($treeTable,"trace_key");

            $uplineIDArray = explode("/", $result["trace_key"]);
            krsort($uplineIDArray);
            if(!$includeSelf) unset($uplineIDArray[count($uplineIDArray)-1]);
            if($limit) $uplineIDArray = array_slice($uplineIDArray,0,$limit);

            return $uplineIDArray;
        }

        function getSponsorTreeDownlines($clientID, $includeSelf = true) {
            $db = MysqliDb::getInstance();   
            
            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_sponsor", "trace_key");

            if(!$result){
                return ;// If no result return nothing
            }
            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
            $result = $db->get("tree_sponsor", null, "client_id");

            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];

            if(!$includeSelf) unset($downlineIDArray[$clientID]);

            return $downlineIDArray;
        }

        function getIntroducerTreeDownlines($clientID, $includeSelf = true) {
            $db = MysqliDb::getInstance();   
            
            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_introducer", "trace_key");

            if(!$result){
                return ;// If no result return nothing
            }
            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
            $result = $db->get("tree_introducer", null, "client_id");

            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];

            if(!$includeSelf) unset($downlineIDArray[$clientID]);

            return $downlineIDArray;
        }

        public function sponsorTreeUpDownLinesChecking($clientID, $receiverID){
            $db = MysqliDb::getInstance();

           if (!$clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'No Client ID Found', 'data'=> '');               
            }

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_sponsor", "level,trace_key");
            $uplinesArr = explode('/', $result['trace_key'], -1);

            $downlinesArr = self::getSponsorTreeDownlines($clientID, false);
            
            $result = array_merge($uplinesArr, $downlinesArr);

            if(in_array($receiverID, $result)) return true;

            return false;
        }


        function rebuildSponsorTree($movingTree){
            
            foreach($movingTree as $moved) {
                // Loop to insert the tree under the new sponsor
                $bool = self::insertSponsorTree($moved["client_id"], $moved["upline_id"]);

                if(!$bool) $failedArray[] = $moved;
            }

            if(count($failedArray) > 0) self::rebuildSponsorTree($failedArray);
            else return true;
        }
         
        public function insertSponsorTree($clientID, $uplineID){
            $db = MysqliDb::getInstance();

            $db->where("client_id", $uplineID);
            $result = $db->getOne("tree_sponsor", "level, trace_key");
            if(!$result) return false;

            $traceKey = $result["trace_key"]."/".$clientID;
            $level = $result["level"] + 1;
            
            $fields = array("client_id", "upline_id", "level", "trace_key");
            $values = array($clientID, $uplineID, $level, $traceKey);
            $data = array_combine($fields, $values);

            $id = $db->insert("tree_sponsor", $data);

            if(!$id) return false;

            return true;
        }

        public function insertIntroducerTree($clientID, $uplineID){
            $db = MysqliDb::getInstance();

            $db->where("client_id", $uplineID);
            $result = $db->getOne("tree_introducer", "level, trace_key");
            if(!$result) return false;

            $traceKey = $result["trace_key"]."/".$clientID;
            $level = $result["level"] + 1;
            
            $fields = array("client_id", "upline_id", "level", "trace_key");
            $values = array($clientID, $uplineID, $level, $traceKey);
            $data = array_combine($fields, $values);

            $id = $db->insert("tree_introducer", $data);

            if(!$id) return false;

            return true;
        }

        function changeSponsor($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            //current client 
            $clientID = trim($params["clientID"]);
            //client that target to change
            $sponsorUsername = trim($params["sponsorUsername"]);

            // Check on required fields
            if(strlen($clientID) == 0) return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00361"][$language], 'data' => "");
            if(strlen($sponsorUsername) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00573"][$language], 'data' => array('field' => "sponsorUsername"));

            // Get sponsor by username
            $targetSponsor = self::getSponsorByUsername($sponsorUsername);

            if(!$targetSponsor) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00573"][$language], 'data' => array('field' => "sponsorUsername"));
            }
            else {
                $targetSponsorTraceKey = explode("/", $targetSponsor["trace_key"]);
                foreach ($targetSponsorTraceKey as $val) $targetSponsorUplinesID[$val] = $val;

            }

            //get current client's sponsor ID
            $db->where("id", $clientID);
            $client = $db->getOne("client", "sponsor_id");
            $currentSponsorID = $client["sponsor_id"];

            // If is the same sponsor, skip it
            if($targetSponsor["id"] == $currentSponsorID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00574"][$language], 'data' => array('field' => "sponsorUsername"));

            // If is ownself, skip it
            if($newSponsorData["id"] == $clientID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00575"][$language], 'data' => array('field' => "sponsorUsername"));

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_sponsor", "trace_key");
            $clientTraceKey = $result["trace_key"];

            if(!$clientTraceKey) {
                // Skip if encounter error
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data' => array('field' => "sponsorUsername"));
            }

            // Compare level, cannot change to a lower level sponsor in the same tree
            if($targetSponsorUplinesID[$clientID]) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00577"][$language], 'data' => array('field' => "sponsorUsername"));
            }

            $db->where("trace_key", $clientTraceKey."%", "LIKE");
            $db->orderby("level", "asc");
            $db->orderby("id", "asc");
            $movingTree = $db->get("tree_sponsor", null, "client_id,upline_id");

            //get client's data who going move to new tree
            foreach ($movingTree as $key => $val) {
                if($val["client_id"] == $clientID) $movingTree[$key]["upline_id"] = $targetSponsor['id'];
                $movingClientArray[] = $val["client_id"];
            }

            $db->where("id", $clientID);
            $db->update("client", array("sponsor_id" => $targetSponsor['id']));

            $db->where("client_id", $movingClientArray, 'IN');
            $db->delete("tree_sponsor");

            self::rebuildSponsorTree($movingTree);

            //insert mainleader (client_setting)
            leader::insertMainLeaderSetting($clientID, $targetSponsor["id"]);
            
            $data['newSponsorID'] = $targetSponsor["id"];
            $data['newSponsorUsername'] = $targetSponsor['username'];
            $data['newSponsorName'] = $targetSponsor['name'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00588"][$language], 'data' => $data);
        }

        function getSponsorTree($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = trim($params["clientID"]);
            $targetID = trim($params["targetID"]);
            $viewType = trim($params["viewType"]);
            $viewType = trim($params["viewType"]);
            $targetUsername = trim($params["targetUsername"]);
            
            $offsetSecs = trim($params['offsetSecs']);

            //get sponsor tree by username if exist
            if($targetUsername){
                $db->where("username", $targetUsername);
                $result = $db->getOne("client", "id");
                if(!$result) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00382"][$language], 'data' => array('field' => "targetUsername"));
                   
                $targetID = $result["id"];
            }

            $db->where("id", $targetID);
            $db->where("type", "Client");
            $targetClient = $db->getOne("client", "id,name,username");
            if(!$targetClient) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00119"][$language], 'data' => array('field' => "targetID"));

            $db->where("client_id", $targetID);
            $result = $db->getOne("tree_sponsor", "level,trace_key");
            $targetClient["trace_key"] = $result["trace_key"];
            $targetClient["level"] = $result["level"];

            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);

            $targetUplinesIDAry = explode("/", $filterTraceKey);
            $db->where("id", $targetUplinesIDAry, "in");
            $targetUplinesClientData = $db->map ('id')->ObjectBuilder()->get("client", null, "id,username,name,created_at,member_id");

            foreach ($targetUplinesIDAry as $key => $uplineID) {
                $username = $targetUplinesClientData[$uplineID]->username;
                $name = $targetUplinesClientData[$uplineID]->name;
                $createdAt = $targetUplinesClientData[$uplineID]->created_at;
                $memberID = $targetUplinesClientData[$uplineID]->member_id;

                $tree['attr']['id'] = $uplineID;
                $tree['attr']['name'] = $name;
                $tree['attr']['username'] = $username;
                $tree['attr']['memberID'] = $memberID;

                if($uplineID == $targetID){

                    $data['target']['attr']['id'] = $uplineID;
                    $data['target']['attr']['username'] = $username;
                    $data['target']['attr']['name'] = $name;
                    $data['target']['attr']['createdAt'] = General::formatDateTimeToString($createdAt, "d/m/Y");
                    $data['target']['attr']['memberID'] = $memberID;

                    $targetLevel = $targetClient["level"];
                }
            }

            $limit = null;

            $db->where('level', $targetClient["level"], '>');
            $db->where('level', $targetClient["level"]+1, '<=');
            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
            if($viewType == "Horizontal") {
                $pageNumber = trim($params["pageNumber"]);
                if(!$pageNumber) $pageNumber = 1;
                $pagingLimit = 5;
                $startLimit = ($pageNumber-1) * $pagingLimit;
                $limit = array($startLimit, $pagingLimit);

                $copyDb = $db->copy();
                $totalRecord = $copyDb->getValue("tree_sponsor", "count(id)");
                $totalPage = ceil($totalRecord/$pagingLimit);
                $data['totalPage'] = $totalPage;

            }

            $result = $db->get("tree_sponsor", $limit, "client_id,level,trace_key");
            
            foreach ($result as $key => $val) {
                $val["depth"] = $val["level"] - $targetLevel;
                $downlineData[] = $val;
                $downlineIDAry[] = $val["client_id"];
            }
            
            $data['downline'] = array();
            if(count($downlineIDAry) == 0) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00119"][$language], 'data' => $data);
            }

            $db->where("id", $downlineIDAry, "in");
            $targetDownlinesClientData = $db->map ('id')->ObjectBuilder()->get("client", null, "id, username, name, created_at, disabled, suspended,member_id");
            
            foreach($downlineData as $row) {
                $downlineID = $row["client_id"];

                $downline['attr']['id'] = $downlineID;
                $downline['attr']['memberID'] = $targetDownlinesClientData[$downlineID]->member_id;
                $downline['attr']['username'] = $targetDownlinesClientData[$downlineID]->username;
                $downline['attr']['name'] = $targetDownlinesClientData[$downlineID]->name;
                $createdAt = $targetDownlinesClientData[$downlineID]->created_at;
                $downline['attr']['createdAt'] = General::formatDateTimeToString($createdAt);
                $downline['attr']['downlineCount'] = count(self::getSponsorTreeDownlines($downlineID, false));
                $downline['attr']['disabled'] =($targetDownlinesClientData[$downlineID]->disabled == 0)?'No':'Yes';
                $downline['attr']['suspended'] = ($targetDownlinesClientData[$downlineID]->suspended == 0)?'No':'Yes';

                $data['downline'][] = $downline;
                unset($downline);

            }
            
            $data['targetID'] = (trim($params["clientID"]) == trim($params["targetID"]))?'':trim($params["targetID"]);
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getSponsorTreeByUsername($params, $clientID) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

           $clientID = trim($params["clientID"]);
           $targetUsername = trim($params->targetUsername);
           $viewType = trim($params->viewType);

           $db->where("username", $targetUsername);
           $result = $db->getOne("client", "id");
           if(!$result) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00382"][$language], 'data' => array('field' => "targetUsername"));
               
           $targetID = $result["id"];

           $db->where("client_id", $targetID);
           $result = $db->get("tree_sponsor", "client_id, trace_key");
           $targetTraceKey = $result["trace_key"];
           $targetTraceKeyAry = explode("/", $targetTraceKey);
           foreach ($targetTraceKeyAry as $val) $targetDownlineIDArray[$val] = $val;

           // Target user not Downline
           if(!$targetDownlineIDArray[$clientID]) {
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00591"][$language], 'data' => array("field"=>"targetUsername"));
           }

           $sponsorTree = self::getSponsorTree($params);

           return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $sponsorTree['data']);
        }

        function getPlacementByUsername($username) {
            $db = MysqliDb::getInstance();

            $db->where("username", $username);
            $client = $db->getOne("client", "id,username,name,email,phone,country_id,sponsor_id");
            if(!$client) return false;

            $db->where("client_id", $client["id"]);
            $placement = $db->getOne("tree_placement","client_position,trace_key");
            $client["trace_key"] = $placement["trace_key"];

            $db->where("upline_id", $client["id"]);
            $result = $db->get("tree_placement", null, "client_id, client_position");

            foreach ($result as $key => $val) {
                $client["position"][$val["client_position"]] = $val;           
            }

            if(!$client) return false;

            return $client;
        }

        public function insertPlacementTree($clientID, $uplineID, $position){
            $db = MysqliDb::getInstance();

            $maxPlacementPositions = Setting::$systemSetting["maxPlacementPositions"];
            
            $db->where("client_id", $uplineID);
            $result = $db->getOne('tree_placement', "client_unit as upline_unit, client_position as upline_position, level, trace_key");
            if(!$result) return false;

            if($maxPlacementPositions == 2){

                if($position == 1) $positionKey = "<";
                else $positionKey = ">";
                    
            }else if($maxPlacementPositions == 3){
                    
                if($position == 1) $positionKey = "<";
                else if($position == 2) $positionKey = "|";
                else $positionKey = ">";
            }

            $traceKey = $result["trace_key"].$positionKey.$clientID."-1";
            $level = $result["level"] + 1;

            $fields    = array("client_id", "client_unit", "client_position", "upline_id", "upline_unit", "upline_position", "level", "trace_key");
            $values    = array($clientID, "1", $position, $uplineID, $result["upline_unit"], $result["upline_position"], $level, $traceKey);
            $data = array_combine($fields, $values);

            $id = $db->insert ('tree_placement', $data);

            if(!$id) return false;

			$update = array('placement_id' => $uplineID);
			$db->where('placement_id', 0);
			$db->where('id', $clientID);
			$db->update('client', $update);

            return true;
        }

        function getPlacementTreeUplines($clientID, $director = true) {
            $db = MysqliDb::getInstance();

            $maxPlacementPositions = Setting::$systemSetting["maxPlacementPositions"];
            $db->where("client_id", $clientID);
            $result = $db->getOne('tree_placement',"trace_key");

            $uplinesID = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $result["trace_key"]);

            // add downlinePosition for sales
            $leftAry = explode("<", $result['trace_key']);
            foreach($leftAry AS $leftSplit){
            	$rightAry = explode('>', $leftSplit);
            	if(($total = count($rightAry)) > 1){
            		foreach($rightAry AS $key=>$rightSplit){
            			$uplineID = explode("-", $rightSplit)[0];
            			if(($total-1) == $key) $linesData[$uplineID]['downlinePosition'] = 1;
            			else $linesData[$uplineID]['downlinePosition'] = 2;
            		}
            	}else{
            		$uplineID = explode("-", $leftSplit)[0];
            		$linesData[$uplineID]['downlinePosition'] = 1;
            	}
            } // done add downlinePosition

            foreach ($uplinesID as $key => $upline) {

                if(!is_numeric($upline[0])){
                    $clientID = explode("-", substr($upline, 1))[0];

                    if($maxPlacementPositions == 2) $linesData[$clientID]["position"] = ($upline[0] == "<")? 1 : 2;
                    else if($maxPlacementPositions == 3) $linesData[$clientID]["position"] = ($upline[0] == "<") ? 1 : (($upline[0] == "|") ? 2 : 3);

                }else{
                    if(!$director) continue;

                    $clientID = explode("-", $upline)[0];
                    $linesData[$clientID]["position"] = 0;
                }

                $uplineIDArray[] = $clientID;
                $linesData[$clientID]["clientID"] = $clientID;

            }

            return array($linesData, $uplineIDArray);
        }        

        function getPlacementTreeDownlines($clientID, $includeSelf = true) {
            $db = MysqliDb::getInstance();   

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_placement", "trace_key");

            if (!$result) {
                return ;
            }

            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
            $result = $db->get("tree_placement", null, "client_id");

            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];

            if(!$includeSelf) unset($downlineIDArray[$clientID]);

            return $downlineIDArray;
        }

        function rebuildPlacementTree($movingTree){
            print_r($movingTree);
            foreach($movingTree as $moved) {
                // Loop to insert the tree under the new sponsor
                $bool = self::insertPlacementTree($moved["client_id"], $moved["upline_id"], $moved["position"]);

                if(!$bool) $failedArray[] = $moved;
            }
            echo "failed array :  \n\n";
            print_r($failedArray);
            echo "next nested loop :\n\n";
            if(count($failedArray) > 0) self::rebuildPlacementTree($failedArray);
            else return true;
        }

        function changePlacement($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $maxPlacementPositions = (int)Setting::$systemSetting["maxPlacementPositions"];

            $clientId = trim($params["clientID"]);
            $targetUsername = trim($params["targetUsername"]);
            $targetPosition = trim($params["targetPosition"]);

            // Check on required fields
            if(strlen($clientId) == 0) return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00361"][$language], 'data' => "");

            $db->where('id', $clientId);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data' => "");

            $clientID      = $clientId;
            $username      = $clientDetails;

            if(strlen($targetUsername) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00579"][$language], 'data' => array('field' => "placementUsername"));

            // Check whether placement position is out of range
            if($targetPosition > $maxPlacementPositions) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00580"][$language], 'data' => array('field' => "placementPosition"));
            }

            $db->where("id", $clientID);
            $client = $db->getOne("client","sponsor_id,placement_id, placement_position");
            $currentSponsorID = $client["sponsor_id"];
            $currentPlacementID = $client["placement_id"];
            $currentPlacementPosition = $client["placement_position"];

            $db->where("client_id", $currentPlacementID);
            $result = $db->getOne("tree_placement", "trace_key");
            $currentPlacementTraceKey = $result["trace_key"];

            // Get placement by username
            $targetPlacementData = self::getPlacementByUsername($targetUsername);

            if(!$targetPlacementData) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00579"][$language], 'data' => array('field' => "placementUsername"));
            
            $targetPlacementTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $targetPlacementData["trace_key"]);

            foreach ($targetPlacementTraceKey as $key => $val) {
                if(!is_numeric($val[0])) $uplineID = explode("-", substr($val, 1))[0];
                else $uplineID = explode("-", $val)[0];
                
                $targetPlacementUplinesID[$uplineID] = $uplineID;
            }

            // If is the same placement, skip it
            if($targetPlacementData["id"] == $currentPlacementID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00581"][$language], 'data' => array('field' => "placementUsername"));

            // If is ownself, skip it
            if($targetPlacementData["id"] == $clientID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00582"][$language], 'data' => array('field' => "placementUsername"));

            // Check whether placement positions are fully occupied
            if(count($targetPlacementData['position']) >= $maxPlacementPositions) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00583"][$language], 'data' => array('field' => "placementUsername"));
            }
            // Check whether placement position is occupied
            if($targetPlacementData['position'][$targetPosition]) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00584"][$language], 'data' => array('field' => "placementPosition"));
            }

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_placement", "trace_key");
            $clientTraceKey = $result["trace_key"];

            if(!$clientTraceKey) {
                // Skip if encounter error
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00585"][$language], 'data' => array('field' => "placementUsername"));
            }

            // Compare level, cannot change to a lower level placement in the same tree
            if($targetPlacementUplinesID[$clientID]) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00586"][$language], 'data' => array('field' => "placementUsername"));
            }

            $db->where("trace_key", $clientTraceKey."%", "LIKE");
            $db->orderby("level", "asc");
            $db->orderby("id", "asc");
            $movingTree = $db->get("tree_placement", null, "client_id,upline_id,client_position as position");

            //get client's data who going move to new tree
            foreach ($movingTree as $key => $val) {
                if($val["client_id"] == $clientID){
                    $movingTree[$key]["upline_id"] = $targetPlacementData['id']; 
                    $movingTree[$key]["position"] = $targetPosition; 
                } 
                $movingClientArray[] = $val["client_id"];
            }

            $db->where("id", $clientID);
            $insertClientData = array("placement_id" => $targetPlacementData['id'], "placement_position" => $targetPosition);
            $db->update("client", $insertClientData);

            $db->where("client_id", $movingClientArray, 'IN');
            $db->delete("tree_placement");

            self::rebuildPlacementTree($movingTree);

            // insert activity log
            $titleCode    = 'T00010';
            $activityCode = 'L00010';
            $transferType = 'Change Placement';
            $activityData = array('user' => $username);

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language], 'data'=> "");

            $data['newPlacementUsername'] = $targetPlacementData["username"];
            $data['newPlacementName'] = $targetPlacementData["name"];
            $data['newPlacementPosition'] = $targetPosition;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00588"][$language], 'data' => $data);
        }

        function getPlacementTree($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = trim($params["clientID"]);
            $targetID = trim($params["targetID"]);
            $viewType = trim($params["viewType"]);

            $maxPlacementPositions = Setting::$systemSetting["maxPlacementPositions"];

            if(strlen($clientID) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language], 'data' => "clientID");
            if(!$viewType) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00590"][$language], 'data' => array('field' => "targetID"));


            for($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "'Placement Total $i'";
                $clientSettingName[] = "'Placement CF Total $i'";
            }

            $db->where("id", $targetID);
            $db->where("type", "Member");
            $result = $db->getOne("client", "id");
            if(!$result) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00119"][$language], 'data' => array('field' => "targetID"));


            $db->where("client_id", $targetID);
            $targetClient = $db->getOne("tree_placement", "level,trace_key");            

            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);

            $targetTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $filterTraceKey);


            foreach ($targetTraceKey as $key => $val) {
                if(!is_numeric($val[0])){
                    $targetUplinesID[] = explode("-", substr($val, 1))[0];

                }else{
                    $targetUplinesID[] = explode("-", $val)[0];
                }
            }

            $db->where("client_id" , $targetUplinesID, "IN");
            $targetUplinesAry = $db->get("tree_placement", null, "client_id,client_position,level,trace_key");
            
            $db->where("id" , $targetUplinesID, "IN");
            $targetUplinesClient = $db->map ('id')->ObjectBuilder()->get("client", null, "id,username,name, created_at");
            
            foreach ($targetUplinesAry as $key => $upline) {
                $uplineID = $upline['client_id'];
                $username = $targetUplinesClient[$uplineID]->username;
                $name = $targetUplinesClient[$uplineID]->name;
                $createdAt = $targetUplinesClient[$uplineID]->created_at;

                $tree['attr']['ID'] = $uplineID;
                $tree['attr']['name'] = $name;
                $tree['attr']['username'] = $username;
                // Build the level from clientID to targetID
                $data['treeLink'][] = $tree;

                if($uplineID == $targetID) {

                    $data['target']['attr']['id'] = $uplineID;
                    $data['target']['attr']['username'] = $username;
                    $data['target']['attr']['name'] = $name;
                    $data['target']['attr']['createdAt'] = strtotime($createdAt);

                    $targetLevel = $upline["level"];
                }
            }

            $depthRule = "1";
            if($viewType == "Horizontal") $depthRule = "3";

            $db->where("level", $targetClient["level"], ">");
            $db->where("level", $targetClient["level"]+$depthRule, "<=");
            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
            $targetDownlinesAry = $db->get("tree_placement", null," client_id,client_unit,client_position,level,trace_key");

            foreach ($targetDownlinesAry as $key => $val) $targetDownlinesIDAry[] = $val["client_id"];
            $db->where("id", $targetDownlinesIDAry, "in");
            $targetDownlinesClient = $db->map('id')->ObjectBuilder()->get("client",null,"id,username,name,created_at");


            if(count($targetDownlinesAry) == 0) return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            
            foreach ($targetDownlinesAry as $key => $targetDownline) {
                $depth = $targetDownline["level"] - $targetLevel;
                $downlineID = $targetDownline['client_id'];
                $username = $targetDownlinesClient[$downlineID]->username;
                $name = $targetDownlinesClient[$downlineID]->name;
                $createdAt = $targetDownlinesClient[$downlineID]->created_at;

                $downline['attr']['id'] = $downlineID;
                $downline['attr']['username'] = $username;
                $downline['attr']['name'] = $name;
                $downline['attr']['position'] = $targetDownline["client_position"];
                $downline['attr']['depth'] = $depth;
                $downline['attr']['createdAt'] = strtotime($createdAt);

                $data['downline'][] = $downline;
                unset($downline);

                //get placement total in client setting                
            }
            
            // $data['generatePlacementBonusType'] = Setting::$internalSetting['generatePlacementBonusType'];
            // $data['placementLRDecimalType'] = Setting::$internalSetting['placementLRDecimalType'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        /**
         * Function for getting the Downline.
         * @param  $clientID Integer.
         * @author Rakesh.
        **/

        public function getMainLeaderUsername($params, $type) {

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];

            $allUsernameAry = $db->get('client', NULL, 'id, username, member_id');
            foreach ($allUsernameAry as $value) {
                if($type == 'memberID'){
                    $clientDet[$value['id']] = $value['member_id'];
                }else{
                    $clientDet[$value['id']] = $value['username'];
                }
            }

            if(is_array($clientID)){
                $db->where('client_id',$clientID,'IN');
                $mainLeaders = $db->map('client_id')->get('mlm_leader',null,'client_id, leader_id');
                foreach($mainLeaders as $clientId => $leaderId){
                    $mainLeaderUsername[$clientId]=$clientDet[$leaderId];
                }
            }else{
                $db->where('client_id',$clientID);
                $mainLeaders = $db->getValue('mlm_leader','leader_id');

                $mainLeaderUsername = $clientDet[$mainLeaders];
            }
            return $mainLeaderUsername;
        }

        public function updateMemberUpline($params, $clientID){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $uplineUsername     = trim($params['uplineUsername']);
            $downlineUsername   = trim($params['downlineUsername']);
            $placementPosition  = trim($params['placementPosition']);
            $tPassword          = trim($params['tPassword']);
            $genealogy          = trim($params['genealogy']);
            $sponsorID          = $clientID;

            $db->where('sponsor_id',$sponsorID);
            $db->where('username',$downlineUsername);
            $downlineID = $db->getValue('client','id');
            if(empty($downlineID)){
                $errorFieldArr[] = array(
                                            'id'    => 'downlineUsernameError',
                                            'msg'   => $translations["E00365"][$language]
                                        );
            }

            if(empty($genealogy)){
                $errorFieldArr[] = array(
                                            'id'    => 'genealogyError',
                                            'msg'   => $translations["E00741"][$language]
                                        );
            }

            if (empty($tPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'tPasswordError',
                                            'msg'   => $translations["E00128"][$language] 
                                        );
            } else {
                $result = Client::verifyTransactionPassword($clientID, $tPassword);
                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                                'id'  => 'tPasswordError',
                                                'msg' => $translations["E00129"][$language]
                                            );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            switch ($genealogy) {
                case 'placement':
                    if (empty($placementPosition)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'placementPositionError',
                                                    'msg' => $translations["E00325"][$language]
                                                );
                    }

                    $db->where("username",$uplineUsername);
                    $placementID = $db->getValue("client", "id");
                    if(empty($placementID)){
                        $errorFieldArr[] = array(
                                                            'id'  => 'placementUsernameError',
                                                            'msg' => $translations["E00579"][$language]
                                                        ); 
                    }else{

                        $db->where("client_id",$placementID);
                        $db->where("trace_key","%".$sponsorID."%","LIKE");
                        $isUnderSponsorID = $db->getOne("tree_placement","id");
                        if(!$isUnderSponsorID){
                            $errorFieldArr[] = array(
                                                            'id'  => 'placementUsernameError',
                                                            'msg' => $translations["E00579"][$language]
                                                        ); 
                        }
                        $db->where("upline_id",$placementID);
                        $db->where("client_position",$placementPosition);
                        $placementValid = $db->getOne("tree_placement","id");
                        if($placementValid){
                            $errorFieldArr[] = array(
                                                            'id'  => 'placementUsernameError',
                                                            'msg' => $translations["E00584"][$language]
                                                        ); 
                        }
                    }

                    $db->where('id',$downlineID);
                    $check = $db->getValue('client','placement_id');
                    if($check){
                        $errorFieldArr[] = array(
                                                            'id'  => 'placementUsernameError',
                                                            'msg' => $translations["E00359"][$language]
                                                        ); 
                    }

                    if ($errorFieldArr) {
                        $data['field'] = $errorFieldArr;

                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
                    }

                    $updateData = array(
                        "placement_id" => $placementID,
                        "placement_position" => $placementPosition,
                        "updated_at" => $db->now(),
                    );

                    $db->where('id',$downlineID);
                    $db->update("client", $updateData );


                    $result = self::insertPlacementTree($downlineID, $placementID,$placementPosition);
                    if($result != true)
                        return array('status' => "error", 'code' => 0, 'statusMsg' => "Failed to insert placement tree.", 'data' => '');

                    // insert activity log
                    $activityData = array('user' => $downlineUsername);
                    $activityRes = Activity::insertActivity('Change Placement', 'T00010', 'L00010', $activityData, $clientID);
                    // Failed to insert activity
                    if(!$activityRes)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language], 'data' => "");

                    break;
            }


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00508"][$language], 'data' => '');
        }

        public function getTreeIntroducer($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $clientID = $params['clientID'];
            $site = $db->userType;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00363"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_introducer', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00364"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_introducer', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00365"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->getOne('client', 'id, username, created_at');

            if(empty($sponsor))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");
            
            $getUsername = '(SELECT username FROM client WHERE client.id=client_id) AS username';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=client_id) AS created_at';
            $db->where('upline_id', $sponsor['id']);
            $downlines = $db->get('tree_introducer', null, 'client_id,'.$getUsername.','.$getCreatedAt);

            $db->where('client_id', $params['realClientID']);
            $searchLevel = $db->getValue('tree_introducer', 'level');

            $db->where('client_id', $params['clientID']);
            $searchSponsorLevel = $db->getValue('tree_introducer', 'level');
            $finalSponsorLevel = $searchSponsorLevel - $searchLevel;
            
            $allDownlines = Self::getIntroducerTreeDownlines($sponsor['id'],false);
            foreach ($allDownlines as $value) {
               $allDownlinesArray[] = $value;
            }

            //find the level of all downlines_id
            $db->where('upline_id', $sponsor['id']);
            $sponsorLevel = $db->map("client_id")->get("tree_introducer", null, "client_id, level");

            $db->where('client_id', $params['realClientID']);
            $targetLevel = $db->getValue('tree_introducer', 'level');

            foreach ($downlines as $value1) {
                $downlineArry[] = $value1["client_id"];
            }
            $clientIDArr = $downlineArry;
            $clientIDArr[] = $clientID;

            $clientRankArr = Bonus::getClientRank("Community Rank",$clientIDArr,"","communityBonus");
            $rankIDAry = $db->map("id")->get("rank", null, "id, translation_code as langCode");

            if(empty($downlines))
                $downlines = array();
  
            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_introducer', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = array_filter(explode("/", $clientTraceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            $sponsor["communityRankDisplay"] = $clientRankArr[$clientID]["rank_id"] ? $translations[$rankIDAry[$clientRankArr[$clientID]["rank_id"]]][$language] : "-";

            $productLangAry = $db->map("id")->get("mlm_product", null, "id, translation_code as langCode");

            $db->where("client_id", $clientID);
            $db->where("status", "Active");
            $sponsorProductID = $db->getValue("mlm_client_portfolio", "product_id");
            $sponsor['productDisplay'] = $sponsorProductID ? $translations[$productLangAry[$sponsorProductID]][$language] : "-";

            $db->where("client_id",$clientID);
            $traceKey = $db->getValue("tree_introducer","trace_key");

            // $db->where("name","totalIntroducee");
            // $db->where("client_id",$clientID);
            // $totalDownline = $db->getValue("client_setting","value");

            $sponsor['community'] = count($downlines) > 0 ? count($downlines) : "0";
            $sponsor['created_at']    = $sponsor['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($sponsor['created_at'])) : "-";

            unset($salesRes);
            unset($communityRankID);
            unset($rankID);
            unset($totalDownline);
            
            if($downlines){
                foreach ($downlines as $k => $v) {
                    $downlineIDAry[$v["client_id"]] = $v["client_id"];
                }

                $db->where("client_id", $downlineIDAry, "IN");
                $db->where("status", "Active");
                $downlineProductIDAry = $db->map("client_id")->get("mlm_client_portfolio", null, "client_id, product_id");
                
                // $db->where("client_id", $downlineIDAry, "IN");
                // $db->where("name","totalIntroducee");
                // $totalDownlineArr = $db->map("client_id")->get("client_setting",NULL,"client_id,value");

                foreach ($downlines as $k => &$v) {
                    unset($totalDownline);
                    $donwlineID = $v['client_id'];
                    $v["communityRankDisplay"] = $clientRankArr[$donwlineID]["rank_id"] ? $translations[$rankIDAry[$clientRankArr[$donwlineID]["rank_id"]]][$language] : "-";
                    $rankID  = $rankArr[$donwlineID]['rank_id'];
                    $rankLangCode2 = $rankLangCodeArr[$rankID];
                    $v['rankDisplay'] = ($rankLangCode2 ? $translations[$rankLangCode2][$language] : "-");
                    // $totalDownline = $totalDownlineArr[$donwlineID];
                    $totalDownline = Self::getIntroducerTreeDownlines($donwlineID,false);

                    $v['community'] = count($totalDownline) > 0 ? count($totalDownline) : "0";
                    $v["productDisplay"] = $downlineProductIDAry[$donwlineID] ? $translations[$productLangAry[$downlineProductIDAry[$donwlineID]]][$language] : "-";

                    $v["created_at"] = $v['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($v['created_at'])) : "-";
                    unset($downlineIDArr);
                    $v['downlines'] = $sponsorLevel[$value1["client_id"]] - $targetLevel;
                }
            }
            if($site == "Admin"){
                $memberDetails = Client::getCustomerServiceMemberDetails($params['clientID']);
                $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            }
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsor;
            $data['downlinesLevel'] = $downlines;
            $data['uplineLevel'] = $finalSponsorLevel;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getIntroducer($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getClientMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS member_id";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $getUplineMemberID = "(SELECT member_id FROM client WHERE client.id=upline_id) AS upline_member_id";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_introducer', 'client_id, upline_id,'.$getClientName.','.$getClientUsername.','.$getClientMemberID.','.$getUplineName.','.$getUplineUsername.','.$getUplineMemberID);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            foreach($result as $key => $value) {
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }


            $memberDetails = Client::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function changeIntroducer($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            
            if(empty($params['clientID']) || empty($params['uplineUsername']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientID       = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];

            // Get sponsor by username
            $targetSponsor = Self::getSponsorByUsername($uplineUsername);
            if(!$targetSponsor) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00573"][$language], 'data' => "");
            }
            else {
                $targetSponsorTraceKey = explode("/", $targetSponsor["trace_key"]);
                foreach ($targetSponsorTraceKey as $val) $targetSponsorUplinesID[$val] = $val;

            }

            //get current client's sponsor ID
            $db->where("id", $clientID);
            $client = $db->getOne("client", "introducer_id, username");
            $oldSponsorID = $client["introducer_id"];
            $username = $client["username"];

            // If is the same sponsor, skip it
            if($targetSponsor["id"] == $oldSponsorID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00574"][$language], 'data' => "");
            }

            // If is ownself, skip it
            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');
            if($uplineID == $clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00575"][$language], 'data' => "");
            }

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_introducer", "trace_key");
            $clientTraceKey = $result["trace_key"];

            if(!$clientTraceKey) {
                // Skip if encounter error
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data' => "");
            }

            // Compare level, cannot change to a lower level sponsor in the same tree
            if($targetSponsorUplinesID[$clientID]) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00577"][$language], 'data' => "");
            }

            // Remove sales from old sponsor upline
            $db->where('trace_key', $client['trace_key'].'/%', 'like');
            $downlineIDArray = $db->map('client_id')->get('tree_introducer',null,'client_id');

           /* $removeGroupSalesRes = Subscribe::updateSponsorGroupSales($clientID,'decrease',$downlineIDArray);
            if ($removeGroupSalesRes['status'] != 'ok') {
                return $removeGroupSalesRes;
            }
*/
            //lock the table prevent others access this table while running function
            $db->setLockMethod("WRITE")->lock("tree_introducer");

            $db->where('client_id', $uplineID);
            $upline = $db->getOne('tree_introducer', 'level, trace_key', 1);

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $uplineLevel = $upline['level'];
            $traceKey = $upline['trace_key'];

            $db->where('client_id', $clientID);
            $client = $db->getOne('tree_introducer', 'id, level, trace_key');

            if($db->count <= 0) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $db->rawQuery("UPDATE tree_introducer SET upline_id = '".$uplineID."', level = '".($uplineLevel + 1)."', trace_key = '".($traceKey.'/'.$clientID)."' WHERE id = '".$client['id']."' ");

            $db->where('trace_key', $client['trace_key'].'/%', 'like');
            $downlines = $db->get('tree_introducer', null, 'id, client_id, level, trace_key');

            $levelDiscrepancy = (($uplineLevel - $client['level']) + 1);

            foreach($downlines as $value) {
                $array = explode($clientID.'/', $value['trace_key']);

                $result = $db->rawQuery("UPDATE tree_introducer SET level = '".($levelDiscrepancy + $value['level'])."', trace_key = '".($traceKey.'/'.$clientID.'/'.$array[1])."' WHERE id = '".$value['id']."' ");
            }

            $db->unlock();

            // Add sales to new sponsors upline
            /*$addGroupSalesRes = Subscribe::updateSponsorGroupSales($clientID,'increase',$downlineIDArray);
            if ($addGroupSalesRes['status'] != 'ok') {
                return $addGroupSalesRes;
            }*/

            $db->where('id', $clientID);
            $db->update('client', array('introducer_id' => $uplineID));
            // Change Logic For BOM 6.0, Hide it
            // Game::refundPortfolio($uplineID);

            // Leader::insertMainLeaderSetting($clientID, $uplineID);

            // insert activity log
            $titleCode    = 'T00041';
            $activityCode = 'L00061';
            $transferType = 'Change Introducer';
            $activityData = array('user' => $username,'oldIntroducerID' => $oldSponsorID,'newIntroducerID' => $uplineID);

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes) {
                $db->unlock();
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            /*$db->where("client_id",$oldSponsorID);
            $traceKey = $db->getValue("tree_introducer","trace_key");
            $currentSponsorUpline = explode("/", $traceKey);
            foreach($currentSponsorUpline AS $id){
                $idArr[] = $id;
            }
            $totalDownline = 1;
            $totalDownline += COUNT($downlines);
            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalIntroducee");
                $db->update("client_setting",array("value"=>$db->dec($totalDownline)));
            }
            unset($idArr);
            $db->where("client_id",$targetSponsor["id"]);
            $traceKey = $db->getValue("tree_introducer","trace_key");
            $currentSponsorUpline = explode("/", $traceKey);
            foreach($currentSponsorUpline AS $id){
                $idArr[] = $id;
            }
            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalIntroducee");
                $db->update("client_setting",array("value"=>$db->inc($totalDownline)));
            }*/

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00386"][$language], 'data' => "");
        }
    }
    
?>