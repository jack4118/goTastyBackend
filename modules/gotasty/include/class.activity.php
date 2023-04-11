<?php 

    class Activity {
        
        // function __construct(){
        // }
        
    	function insertActivity($title, $titleTranslationCode, $translationCode, $activityData, $clientID, $creatorID = "", $creatorType = "") {
            $db = MysqliDb::getInstance();

    		$tblDate = date("Ym");
            $tableName = "activity_log_".$tblDate;

    		$createResult = $db->rawQuery("CREATE TABLE IF NOT EXISTS ".$tableName." LIKE activity_log");

            $activityData    = json_encode($activityData);
            $createdAt       = date("Y-m-d H:i:s");
            if(!$creatorID)
                $creatorID = Cash::$creatorID;
            
            if(!$creatorType)
                $creatorType = Cash::$creatorType;

            $fields    = array("title", "title_translation_code", "translation_code", "data", "client_id", "creator_id", "creator_type", "created_at");
            $values    = array($title, $titleTranslationCode, $translationCode, $activityData, $clientID, $creatorID, $creatorType, $createdAt);
            $arrayData = array_combine($fields, $values);

            $result = $db->insert($tableName, $arrayData);

            if($result)
                return true;
            
            return false;
    	}

        function getActivity($params) {
            $db = MysqliDb::getInstance();
            
            $offsetSecs = trim($params['offsetSecs']);
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            
            // Set default to get from today
            $activityDate = date("Ymd");
            
            $searchData = $params['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                        case 'activityDate':
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                            
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $activityDate = date('Ymd', $dataValue);
                    
                            break;
                            
                        case 'activityTime':
                            // Set db column here
                            $columnName = 'created_at';
                            
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                                
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $dataValue = date('Y-m-d', $dataValue);
                            
                            $dateFrom = trim($v['timeFrom']);
                            $dateTo = trim($v['timeTo']);
                            if(strlen($dateFrom) > 0) {
                                $dateFrom = strtotime($dataValue.' '.$dateFrom);
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                                
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                $dateTo = strtotime($dataValue.' '.$dateTo);
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                                
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Time from cannot be later than time to", 'data'=>$data);
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                            
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                            
                        case 'title':
                            $db->where('title', $dataValue);
                            
                            break;
                            
                        case 'creatorType':
                            $db->where('creator_type', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            //Get the limit.
            $limit = General::getLimit($pageNumber);

            if($paramsSite == 'Admin')
                $db->where('site', 'SuperAdmin', '!=');
            
            $db->orderBy("created_at", "Desc");
            $copyDb = $db->copy();

            try{
                $result = $db->get('activity_log_'.$activityDate, $limit);
            }
            catch (Exception $e) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
            
            if ($result) {
                foreach($result as $value) {
                    if($value['creator_type'] == 'SuperAdmin')
                        $superAdminID[] = $value['creator_id'];
                    else if($value['creator_type'] == 'Admin')
                        $adminID[] = $value['creator_id'];
                    else if ($value['creator_type'] == 'Client')
                        $clientID[] = $value['creator_id'];
                }
                if(!empty($superAdminID)) {
                    $db->where('id', $superAdminID, 'IN');
                    $dbResult = $db->get('users', null, 'id, username');
                    foreach($dbResult as $key => $value) {
                        $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                    }
                }
                if(!empty($adminID)) {
                    $db->where('id', $adminID, 'IN');
                    $dbResult = $db->get('admin', null, 'id, username');
                    foreach($dbResult as $key => $value) {
                        $usernameList['Admin'][$value['id']] = $value['username'];
                    }
                }
                if(!empty($clientID)) {
                    $db->where('id', $clientID, 'IN');
                    $dbResult = $db->get('client', null, 'id, username');
                    foreach($dbResult as $key => $value) {
                        $usernameList['Member'][$value['id']] = $value['username'];
                    }
                }
                    
                foreach($result as $value) {

                    $activity['id']               = $value['id'];
                    $activity['title']            = $value['title'];
                    $translationCode              = $value['translation_code'];
                    $activityData                 = (array) json_decode($value['data'], true);

                    $db->where('code', $translationCode);
                    $content     = $db->getValue('language_translation', 'content');

                    foreach($activityData as $key => $val) {
                        $oriKeyWord = '%%'.$key.'%%';
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

                    $activity['description']      = $content;  
                    $activity['username']         = $usernameList[$value['creator_type']][$value['creator_id']];
                    $activity['creator_type']     = $value['creator_type'];
                    $activity['created_at']       = General::formatDateTimeToString($value['created_at'], "d/m/Y h:i:s A");

                    $activityList[]   = $activity;
                }

                $totalRecord = $copyDb->getValue ('activity_log_'.$activityDate, "count(id)");

                $data['activityList'] = $activityList;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord']  = $totalRecord;
                $data['numRecord']    = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>'');
            }
        }
    }
?>
