<?php
    class Upgrade
    {
        function __construct($db, $general)
        {
            $this->db = $db;
            $this->general = $general;
        }

        function getNewUpgrades(){
            $db = $this->db;
            
            include($_SERVER["DOCUMENT_ROOT"]."/upgrades/xImport.php");
            //tmpA is xImport file
            //tmpB is from database
            //tmpC is xImport file in modules folder
            $tmpA = array();
            foreach($repository as $key => $value) {
                $tmpA[$key] = $value;
            }
            
            $result = $db->get('log_upgrade');
            $tmpB = array();
            foreach($result as $value) {
                $tmpB[(int)$value['id']] = $value['description'];
            }
            
            include($_SERVER["DOCUMENT_ROOT"]."/modules/upgrades/xImport.php");
            $tmpC = array();
            foreach($repository as $key => $value) {
                $tmpC[$key] = $value;
            }
            
            $timeStamp = array();
            $description = array();
            $fileStatus = array();
            $tmpABDiff = array_diff_key($tmpA, $tmpB);
            $tmpCBDiff = array_diff_key($tmpC, $tmpB);
            if((count($tmpABDiff) == 0) && (count($tmpCBDiff) == 0))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'No new upgrades found.', 'data' => '');
            
            if(count($tmpABDiff) != 0) {
                foreach($tmpABDiff as $key => $value){
                    $upgrade['timeStamp'] = $key;
                    $upgrade['description'] = $value;
                    if (file_exists("upgrades/$key.xml")){
                        $upgrade['status'] = "Ready";
                    }
                    else {
                        $upgrade['status'] = "File not found.";
                    }

                    $upgradeList[] = $upgrade;
                }
            }
            
            if(count($tmpCBDiff) != 0) {
                foreach($tmpCBDiff as $key => $value){
                    $upgrade['timeStamp'] = $key;
                    $upgrade['description'] = $value;
                    if (file_exists("modules/upgrades/$key.xml")){
                        $upgrade['status'] = "Ready";
                    }
                    else {
                        $upgrade['status'] = "File not found.";
                    }

                    $upgradeList[] = $upgrade;
                }
            }
            
            $data['upgradeList'] = $upgradeList;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        
        function getUpgradesHistory($params){
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
                        case 'id':
                            $db->where('id', $dataValue);
                            break;

                        case 'searchDate':
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>""); // Invalid date

                            $dataValue = date('Y-m-d', $dataValue);
                            $db->where('created_at', $dataValue.'%', 'LIKE');

                            break;
                                
                        case 'searchTime':
                            // Set db column here
                            $columnName = 'created_at';

                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date.", 'data'=>"");

                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date.", 'data'=>"");

                            $dataValue = date('Y-m-d', $dataValue);

                            $dateFrom = trim($v['timeFrom']);
                            $dateTo = trim($v['timeTo']);
                            if(strlen($dateFrom) > 0) {
                                $dateFrom = strtotime($dataValue.' '.$dateFrom);
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date.", 'data'=>"");

                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                $dateTo = strtotime($dataValue.' '.$dateTo);
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date.", 'data'=>"");

                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Time from cannot be later than time to", 'data'=>$data);

                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                            
                        case 'created_by':
                            $db->where('created_by', $dataValue); 
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy('id', 'DESC');
            $result = $db->get('log_upgrade', $limit);
            
            if(!empty($result)) {
                $totalRecord = $copyDb->getValue ("log_upgrade", "count(id)");
                $timeStamp = array();
                $description = array();
                $patchBy = array();
                $patchOn = array();
                foreach($result as $value) {

                    $upgrade['timeStamp'] = $value['id'];
                    $upgrade['description'] = $value['description'];
                    $upgrade['patchedOn'] = $value['created_at'];
                    $upgrade['patchedBy'] = $value['created_by'];

                    $upgradeList[] = $upgrade;
                }
                
                $data['upgradeList'] = $upgradeList;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else
                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'No Results Found', 'data' => '');
        }
        
        function updateAllUpgrades($params) {
            $db = $this->db;
            
            $username = trim($params['username']);
            
            include($_SERVER["DOCUMENT_ROOT"]."/upgrades/xImport.php");
            //tmpA is xImport file
            //tmpB is from database
            //tmpC is xImport file in modules folder
            $tmpA = array();
            foreach($repository as $key => $value) {
                $tmpA[$key] = $value;
            }
            
            $result = $db->get('log_upgrade');
            $tmpB = array();
            foreach($result as $value) {
                $tmpB[(int)$value['id']] = $value['description'];
            }
            
            include($_SERVER["DOCUMENT_ROOT"]."/modules/upgrades/xImport.php");
            $tmpC = array();
            foreach($repository as $key => $value) {
                $tmpC[$key] = $value;
            }
            
            $timeStamp = array();
            $description = array();
            $fileStatus = array();
            $error = array();
            $errorFile = array();
            
            $tmpABDiff = array_diff_key($tmpA, $tmpB);
            $tmpCBDiff = array_diff_key($tmpC, $tmpB);
            
            if((count($tmpABDiff) == 0) && (count($tmpCBDiff) == 0))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'No new upgrades found.', 'data' => '');
            
            if(count($tmpABDiff) != 0) {
                foreach($tmpABDiff as $key => $value){
                    if (file_exists("upgrades/$key.xml")){
                        // Load xml file
                        $xml = simplexml_load_file("upgrades/$key.xml");
                        // Run all queries in this file
                        foreach($xml->query as $v) {
                            try{
                                $result = $db->rawQuery($v);
                            }
                            catch (Exception $e) {

                                $errorMsg['timeStamp'] = $key;
                                $errorMsg['message']   = "Query: ".$v."</br><strong>".$e->getMessage()."</strong>";

                                $errorMsgList[] = $errorMsg;
                            }
                        }
                        $values = array('id' => $key,
                                      'description' => $value,
                                      'created_at' => date("Y-m-d H:i:s"),
                                      'created_by' => $username);
                        $db->insert('log_upgrade', $values);
                    }
                    else {

                        $upgrade['timeStamp']   = $key;
                        $upgrade['description'] = $value;
                        $upgrade['status']      = "File not found.";

                        $upgradeList[] = $upgrade;
                    }
                }
            }
            
            if(count($tmpCBDiff) != 0) {
                foreach($tmpCBDiff as $key => $value){
                    if (file_exists("modules/upgrades/$key.xml")){
                        // Load xml file
                        $xml = simplexml_load_file("modules/upgrades/$key.xml");
                        // Run all queries in this file
                        foreach($xml->query as $v) {
                            try{
                                $result = $db->rawQuery($v);
                            }
                            catch (Exception $e) {
                                
                                $errorMsg['timeStamp'] = $key;
                                $errorMsg['message']   = "Query: ".$v."</br><strong>".$e->getMessage()."</strong>";

                                $errorMsgList[] = $errorMsg;
                            }
                        }
                        $values = array('id' => $key,
                                      'description' => $value,
                                      'created_at' => date("Y-m-d H:i:s"),
                                      'created_by' => $username);
                        $db->insert('log_upgrade', $values);
                    }
                    else {
                        // $timeStamp[] = $key;
                        // $description[] = $value;
                        // $fileStatus[] = "File not found.";

                        $upgrade['timeStamp']   = $key;
                        $upgrade['description'] = $value;
                        $upgrade['status']      = "File not found.";

                        $upgradeList = $upgrade;
                    }
                }
            }
            
            if(count($errorMsgList) != 0) {
                // $errorMsg['timeStamp'] = $errorFile;
                // $errorMsg['message'] = $error;
                
                $data['errorMsg'] = $errorMsgList;
            }
            
            if(count($upgradeList) != 0) {
                // $upgrade['timeStamp'] = $timeStamp;
                // $upgrade['description'] = $description;
                // $upgrade['status'] = $fileStatus;
                
                $data['upgradeList'] = $upgradeList;
            }
            
            if((count($upgrade['timeStamp']) != 0) || (count($errorMsg['timeStamp']) != 0))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Successfully upgraded.', 'data' => $data);
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Successfully upgraded.', 'data' => '');  
        }

    }

    ?>
