<?php
    
    class leader {
        function __construct() {
        }

        public function setLeader($params){
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            $leaderUsername = $params['memberID'];
            // $leaderUsername = $params['username']; 
            // $leaderCountryID = $params['countryID'];

            $todayDate = date("Y-m-d H:i:s");
            if(!$leaderUsername) {
                // $errorFieldArr[] = array(
                //             // 'id' => 'usernameError',
                //             // 'msg' => $translations["E00323"][$language]  /* Invalid Username */ 
                //         );
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["A01637"][$language] /* Invalid Member ID */, 'data' => "");
            }else{
                $db->where("member_id",$leaderUsername);
                // $db->where("username",$leaderUsername);
                // $leaderID = $db->getValue("client","id");
                $leader = $db->getOne("client","id, country_id");
                $leaderID = $leader['id'];
                $leaderCountryID = $leader['country_id'];
                $countryID = $leader['country_id'];
                if(!$leaderID) { 
                        // $errorFieldArr[] = array( 
                        //             // 'id' => 'usernameError',
                        //             // 'msg' => $translations["E00323"][$language] /* Invalid Username */
                        //         );
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["A01637"][$language] /* Invalid Member ID */, 'data' => "");
                };
            }

            /*Original setLeader data check*/
            // if(!$leaderCountryID) {
            //     $errorFieldArr[] = array(
            //                 'id' => 'countryIDError',
            //                 'msg' => $translations["E00304"][$language] /*Country not found*/
            //             );
            // }
            // else{
            //     $db->where("id",$leaderCountryID); 
            //     $countryID = $db->getValue("country","id"); 
            //         if(!$countryID) { 
            //             $errorFieldArr[] = array( 
            //                         'id' => 'countryIDError',
            //                         'msg' => $translations["E00304"][$language] /*Country not found*/
            //                     );
            //         };
            // }
            // if ($errorFieldArr) {
            //     $data['field'] = $errorFieldArr;
            //     return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>"");
            // }

            // set leader
            $db->where("client_id", $leaderID);
            $res = $db->getOne("mlm_leader","id,client_id,leader_id,leader_country_id,updated_at,updater_id");

            if($res){
                $updateData = array(
                    "leader_id"         => $leaderID, 
                    "updated_at"        => $todayDate, 
                    "leader_country_id" => $countryID, 
                    "updater_id"        => $userID,
                );
                $db->where("id",$res['id']);
                $setMainLeaderRes = $db->update("mlm_leader",$updateData);
            }else{
                $insertData = array(
                                        "client_id"         => $leaderID,
                                        "leader_id"         => $leaderID,
                                        "leader_country_id" => $countryID,
                                        "updated_at"        => $todayDate, 
                                        "updater_id"        => $userID, 
                                    );
                $setMainLeaderRes = $db->insert("mlm_leader",$insertData);  
            }
            // get sponsor tree downline 
            $db->where("trace_key", "%".$leaderID."%", "LIKE");
            $treeRes = $db->get("tree_placement", null, "client_id,level"); 

            foreach ($treeRes as $treeRow) {
                $downlineIDArray[$treeRow["client_id"]] = $treeRow["client_id"];
                $clientLevelArr[$treeRow["client_id"]] = $treeRow["level"];
            }

            $db->where("client_id",$downlineIDArray,"IN");
            $clientSetting = $db->map('client_id')->get('mlm_leader', null,'client_id, id,leader_id,leader_country_id,updated_at,updater_id');

            $leaderLevel = $clientLevelArr[$leaderID];
            foreach($downlineIDArray AS $clientID){
                if($clientID  == $leaderID ) continue;

                if($clientSetting[$clientID]){
                    $oriLeaderID = $clientSetting[$clientID]['leader_id'];
                    $oriLeaderLevel = $clientLevelArr[$oriLeaderID];
                    $clientLevel = $clientLevelArr[$clientID];
                    $oriLevelDiff  = $clientLevel - $oriLeaderLevel;
                    $newLevelDiff  = $clientLevel - $leaderLevel;
                    // original leader is nearer skip
                    if($newLevelDiff > $oriLevelDiff) continue;

                    $updateData = array(
                        "leader_id" => $leaderID, 
                        "leader_country_id" => $countryID,
                        "updated_at" => $todayDate,
                        "updater_id" => $userID,
                    );
                    $db->where("id",$clientSetting[$clientID]['id']);
                    $db->update("mlm_leader",$updateData);
                }else{
                    $insertData = array(
                                            "client_id"         => $clientID,
                                            "leader_id"         => $leaderID,
                                            "leader_country_id" => $countryID,
                                            "updated_at"        => $todayDate,
                                            "updater_id"        => $userID,
                                        );
                    $db->insert("mlm_leader",$insertData);
                }
            }

            if($setMainLeaderRes){
                // L00050 = %%admin%% has set %%member%% as main leader
                $db->where("id",$userID);
                $adminUsername = $db->getValue("admin","username");
                $activityData = array("admin" => $adminUsername , "member" => $leaderUsername);
                $activityRes = Activity::insertActivity("Set Main Leader", 'T00030', 'L00050', $activityData, $setByUsername);
                if(!$activityRes)
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00144"][$language] /*Failed to insert activity*/, 'data'=> "");
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Leader added', 'data' => "");  
        }

        public function getLeaderList($params){

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData = $params['searchData'];
            $currentDate = date($dateTimeFormat);


            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                if ($dateFrom < 0) {
                                    $db->resetState();
                                    return array('status' => "error", "code" => 1, 'statusMsg' => $translations["E00156"][$language], 'data' => "");
                                }
                                $db->where('updated_at', date('Y-m-d 00:00:00', $dateFrom), ">=");
                            }
                            if (strlen($dateTo) > 0) {
                                if ($dateTo < 0) {
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data' => "");
                                }

                                if ($dateTo < $dateFrom) {
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data' => $data);
                                }

                                $db->where('updated_at', date('Y-m-d 23.59.59', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getValue('client', 'id', null);

                            $db->where('client_id', $sq, "IN");
                            break;
                    }
                }
            }

            $db->where('leader_id = client_id');
            $leaderIDAry = $db->map('leader_id')->get("mlm_leader",  NULL, "leader_id, client_id, leader_country_id, updated_at, updater_id");
            if(!$leaderIDAry) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00110"][$language], 'data' => "");
            }

            foreach ($leaderIDAry as $leaderID => $leader) {
                $adminAry[$leader['updater_id']] = $leader['updater_id'];
                $countryAry[$leader['leader_country_id']] = $leader['leader_country_id'];
            }

            $clientIDAry = array_keys($leaderIDAry);

            /*Get client info*/
            $db->where('id', $clientIDAry, 'IN');
            $clientDataAry = $db->map('id')->get('client', NULL, 'id, member_id, name, email');

            /*Get client rank*/
            $clientRankDataAry = Bonus::getClientRank("Bonus Tier", $clientIDAry, '', 'rankDisplay', '');

            $rankData = $db->map('id')->get('rank', null, 'id, translation_code'); 

            if($adminAry){
                $db->where('id', $adminAry, 'IN');
                $adminDataAry = $db->map('id')->get('admin', NULL, 'id, name');
            }

            /*Get country data and city data*/
            if($countryAry){
                $db->where('id', $countryAry, 'IN');
                $countryDataAry = $db->map('id')->get('country', NULL, 'id, name, translation_code');
            }

            if ($cityAry) {
                $db->where('id', $cityAry, "IN");
                $cityDataAry = $db->map('id')->get('city', NULL,'id, name, translation_code');
            }

            foreach ($leaderIDAry as $leaderID => $leader) {
                // $leaderList['authorisedBy']     = $adminDataAry[$leader['updater_id']];
                $leaderList['clientID']     = $leaderID;
                $leaderList['memberID']     = $clientDataAry[$leaderID]['member_id'];
                $leaderList['fullName']         = $clientDataAry[$leaderID]['name'];
                $leaderList['email']        = $clientDataAry[$leaderID]['email'];
                $leaderList['rank']         = $clientRankDataAry[$leaderID]['rank_id']?$translations[$rankData[$clientRankDataAry[$leaderID]['rank_id']]][$language]:"-";
                $leaderList['country'] = $leader['leader_country_id']?$translations[$countryDataAry[$leader['leader_country_id']]['translation_code']][$language]:"-";
                $leaderList['createdAt']     = date($dateTimeFormat, strtotime($leader['updated_at']));
                $leaderList['addedBy']      = $adminDataAry[$leader['updater_id']]?:"-";
                // $leaderList['updated_at']           = $leader['updated_at'] != "0000-00-00 00:00:00" ? date($dateTimeFormat, strtotime($leader['updated_at'])) : "-";

                $leaderDetails[] = $leaderList;
            }

            $data["leaderList"] = $leaderList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $leaderDetails);
        }

        public function getLeaderDownlines($clientID, $includeSelf = true){

            $db = MysqliDb::getInstance();

            $db->where('client_id = leader_id');
            $mainLeaderIDAry = $db->getvalue('mlm_leader','client_id', null);

            if(in_array($clientID, $mainLeaderIDAry)){
                $mainLeaderID = $clientID;
            } else {
                return false;
            }

            $db->where('leader_id', $mainLeaderID);
            $mainLeaderDownLineID = $db->map('client_id')->get('mlm_leader', null,'client_id');
            if($includeSelf==false){
                unset($mainLeaderDownLineID[$mainLeaderID]);
            }

            return $mainLeaderDownLineID;
        }

        public function insertMainLeaderSetting($clientID, $sponsorID){
            
            $db = MysqliDb::getInstance();

            $todayDate = date('Y-m-d H:i:s');

            $userID = $db->userID;
            $site = $db->userType;

            if(!$clientID || !$sponsorID){
                return false;
            }

            $db->where('client_id', array($clientID, $sponsorID), "IN");
            $clientSettings = $db->map('client_id')->get('mlm_leader', NULL, 'client_id, leader_id, leader_country_id, updated_at');

            //get value from sponsorID whether he is mainLeader or not
            unset($mainLeaderID); 
            $mainLeaderID        =  $clientSettings[$sponsorID]['leader_id'] ? : 0;
            $clientSettingID     =  $clientSettings[$clientID]['client_id']; 
            $clientLeaderCountry =  $clientSettings[$sponsorID]['leader_country_id'] ? : 0; 

            if($clientSettingID){
                $updateData = array(
                    "leader_id"         => $mainLeaderID, 
                    "leader_country_id" => $clientLeaderCountry, 
                    "updated_at"        => $todayDate,
                    "updater_id"        => $userID,
                );
                $db->where('client_id', $clientSettingID);
                $db->update('mlm_leader', $updateData);
            }else{
                $insertData = array(
                    "client_id"         => $clientID,
                    "leader_id"         => $mainLeaderID,
                    "leader_country_id" => $clientLeaderCountry,
                    "updated_at"        => $todayDate,
                    "updater_id"        => $userID,
                );
                $db->insert('mlm_leader', $insertData);
            } 
            return true;
        }

        public function removeMainLeader($params){
          $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $todayDate = date('Y-m-d H:i:s');
            $userID = $db->userID;

            $leaderID = trim($params['leaderID']); 
            
            if(!$leaderID) return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00116"][$language] /* Please Enter Username */, 'data' => "");
            
            $db->where("id",$leaderID); 
            $leaderID = $db->getValue("client","id");
            if(!$leaderID) return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid user */, 'data' => ""); 

            $db->where('client_id', $leaderID);
            $db->where('leader_id', $leaderID);
            $isMainLeader = $db->getValue('mlm_leader', 'leader_id');
            if(!$isMainLeader) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00594'][$language] /* Leader not found */, 'data' => "");
            }

            // leaderID's Sponsor upline - mainleader  
            $db->where('id', $leaderID);
            $sponsorID = $db->getValue('client', 'sponsor_id');

            $db->where('client_id', $sponsorID);
            $newMLInfo = $db->getOne('mlm_leader', 'leader_id, leader_country_id');   
            $newMainLeader  = $newMLInfo['leader_id']; 
            $newMLCountryID = $newMLInfo['leader_country_id'];
            
            $newMainLeader = $newMainLeader ? : 0;
            $newMLCountryID = $newMLCountryID ? : 0;

            /*Remove Main Leader*/
            $updateData = array(
                "leader_id"         =>  $newMainLeader, 
                "leader_country_id" =>  $newMLCountryID,
                "updated_at"        =>  $todayDate,
                "updater_id"        =>  $userID,
            );
            $db->where('leader_id',$leaderID);
            $db->update('mlm_leader',$updateData);

            // L00051 = %%admin%% has removed %%member%% as main leader
            $db->where("id", $userID);
            $adminUsername = $db->getValue('admin', 'username');
            $db->where("id", $leaderID);
            $leaderUsername = $db->getValue('client', 'username');
            $activityData = array("admin" => $adminUsername, "member" => $leaderUsername);
            $activityRes = Activity::insertActivity("Remove Main Leader", 'T00031', 'L00051', $activityData, $adminUsername['username']);
            if(!$activityRes)
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00144"][$language] /*Faield to insert activity*/, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00480'][$language], 'data' => "");  
        }
    }
    
?>