<?php 

	class BonusReport{

        function __construct()
        {
            // Self::db       = $db;
            // Self::general  = $general;
            // Self::setting  = $setting;
            // Self::cash     = $cash;
            // Self::tree     = $tree;
        }

        public function getBonusListing($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $userType       = $db->userType;
            $decimal        = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $onloaded   = $params['onloaded'] ? $params['onloaded'] : 0;
            $seeAll     = $params['seeAll'] ? $params['seeAll'] : 0;
            $type       = $params['type'];

            $usernameSearchType = $params["usernameSearchType"];

            $getSetting = 1;

            $adminLeaderAry = Setting::getAdminLeaderAry();

            if($onloaded){
                $pageLimit = 300;
                if($pageNumber > 1) $getSetting = 0;
                $limit = General::getLimit($pageNumber, $pageLimit);
            }else{
                if(!$seeAll) $limit = General::getLimit($pageNumber);
            }
            // $limit=array('0'=>'0','1'=>'10000');
            /*if($userType == "Admin") {
                //Get leader ID
                $leaderID     = General::getLeaderID($db->userID);
                if(!empty($leaderID))
                    $downlineIDArray = Tree::getSponsorDownlineByClientID($leaderID, true);
            }*/

            // $bonusSetting = Bonus::getBonusSetting();
            $db->where("disabled","0");
            $bonusSetting = $db->get("mlm_bonus",null,"name, language_code");
            $cpDb = $db->copy();

            //Get an array of the bonusName that need to be multiplied using the cvRate.
            $disabledBonus = array('goldmineBonus', 'leadershipBonus', 'teamBonus', 'awardBonus');
            //Get latest cv_rate
            $cvRate = $db->getValue('bonus_payout_summary', 'MAX(id) as id, cv_rate');

            if(count($searchData) > 0){
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);


                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");

                        //     break;
                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where("client_id = leader_id");
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }
                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $mainDownlines, "IN");
                            break;

                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                               $db->resetState();
                               return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $db->where('client_id', $downlines, "IN");

                            break;
                    }
                }
            }

            unset($searchTs);   
            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);
                $dataType       = trim($v['dataType']);
                // $startTs        = trim($v['tsFrom']);
                // $endTs          = trim($v['tsTo']);

                switch($dataName) {

                    case 'bonusDate':
                        $startTs   = $v['tsFrom'] ? trim($v['tsFrom']) : 0;
                        $endTs     = $v['tsTo']   ? trim($v['tsTo']) : 0;

                        if($startTs > 0) $searchTs['startTs'] = date('Y-m-d 00:00:00', $startTs);
                        if($endTs > 0) $searchTs['endTs'] = date('Y-m-d 23:59:59', $endTs);

                        if($startTs < 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00202"][$language] /* Invalid date. */, 'data'=>"");
                            
                        if($endTs < $startTs && $endTs > 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                        break;

                    case 'leaderUsername':

                        $db->where("username", $dataValue);
                        $mainID = $db->getValue("client", "id");
                        if(!$mainID){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => "User Name Not Found.", 'data' => "");
                        }

                        $db->where("main_id", $mainID);
                        $clientUserRes = $db->get("client",null, "id");
                        foreach ($clientUserRes as $clientUserRow) {
                            $mainUsernameIDAry[$clientUserRow["id"]] = $clientUserRow["id"];
                        }

                        $mainUsernameIDAry[$mainID] = $mainID;

                        break;

                    case 'username':
                        if ($usernameSearchType == "match") {
                            $db->where("username", $dataValue);
                            $clientUserID = $db->getValue("client","id");
                            if($downlineIDArray && !in_array($clientUserID, $downlineIDArray)){
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "User Name Not Found.", 'data' => "");
                            }
                            unset($downlineIDArray);
                            $downlineIDArray[] = $clientUserID;
                        } else{
                            if ($downlineIDArray) {
                                $db->where("id", $downlineIDArray, "IN");
                            }
                            $db->where("username", $dataValue . "%", "LIKE");
                            $clientUserID = $db->map('id')->ObjectBuilder()->get("client", NULL, "id");

                            $downlineIDArray = $clientUserID;

                        }

                        break;

                    case 'name':
                        if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%".$dataValue."%", "LIKE");
                                $fullNameResult = $sq->get("client", NULL, "id");
                               
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $fullNameResult = $sq->get("client", NULL, "id");
                            }
                        $db->where("client_id", $sq, 'IN');

                        break;

                    case 'memberID':
                        $sq = $db->subQuery();
                        $sq->where("member_id", $dataValue);
                        $memberIDResult = $sq->get("client", null, "id");
			$db->where("client_id", $sq, 'IN');

                        break;

                    case 'sponsorID':
                        $sq = $db->subQuery();  
                        $ssq = $sq->subQuery();
                        $ssq->where('member_id', $dataValue);
                        $ssq->get('client', null, 'id');
                        $sq->where('sponsor_id',$ssq);
                        $sponId = $sq->get('client',null,'id');
			$db->where('client_id', $sq, 'IN');

                        break;

                    // case 'mainLeaderID':
                    //     $sq = $db->subQuery();  
                    //     $sq->where('leader_id', $dataValue);
                    //     $mainLeadID = $sq->get('mlm_leader', NULL, 'client_id');

                    //     break;

                    case 'mainLeaderUsername':

                        $cpDb->where('username', $dataValue);
                        $mainLeaderID  = $cpDb->getValue('client', 'id');
                        $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                        if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        $db->where('client_id', $mainDownlines, "IN");

                        break;


                    default:
                        break;

                }
                unset($dataName);
                unset($dataValue);
            }

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            if($getSetting){
                if($downlineIDArray) $db->where('client_id', $downlineIDArray, 'IN');

                if($searchTs){
                    if($searchTs['startTs']) $db->where('bonus_date', $searchTs['startTs'], '>=');
                    if($searchTs['endTs']) $db->where('bonus_date', $searchTs['endTs'], '<=');
                }

                $db2 = $db->copy();
                $db->groupBy('bonus_type');
                $grandTotalBonus = $db->map('bonus_type')->ObjectBuilder()->get('mlm_bonus_report', null, 'bonus_type, sum(bonus_amount) as amount');
                $dataOut['query'] = $db->getLastQuery();
            }

            

            foreach ($bonusSetting as $row) {
                $bonusNameList[$row['name']] = $row['language_code'];
                $bonusNameSQLColumns.=", SUM(if( bonus_type = '".$row['name']."', bonus_amount, 0 )) AS '".$row['name']."'"; 
            }


            unset($bonusReport);
            unset($clientBonusType);
            unset($totalBonusList);
            unset($grandTotalList);
            unset($headerList);
            unset($headerBonus);
            
            if($getSetting){
                foreach ($bonusNameList as $bonusName => $bonusCode) {
                    if (in_array($bonusName, $disabledBonus)) {
                        $headerBonus[] = $translations[$bonusCode][$language];
                        $rowBonus[$bonusName] = 0;
                        $grandTotalList["grandTotal"]["bonusName"] = "Grand Total Bonus Amount (IDR)";
                        $grandTotalList["grandTotal"]["totalBonus"] = bcadd($grandTotalList["grandTotal"]["totalBonus"],$grandTotalBonus[$bonusName] * $cvRate,$decimal);
                        $grandTotalList[$bonusName]["totalBonus"] = $grandTotalBonus[$bonusName] ? number_format($grandTotalBonus[$bonusName] * $cvRate,$decimal,".","") : 0;
                        $grandTotalList[$bonusName]["bonusName"] = 'Grand Total '.$translations[$bonusCode][$language].' (IDR)';
                    } else {
                        $headerBonus[] = $translations[$bonusCode][$language];
                        $rowBonus[$bonusName] = 0;
                        $grandTotalList["grandTotal"]["bonusName"] = "Grand Total Bonus Amount (IDR)";
                        $grandTotalList["grandTotal"]["totalBonus"] = bcadd($grandTotalList["grandTotal"]["totalBonus"],$grandTotalBonus[$bonusName],$decimal);
                        $grandTotalList[$bonusName]["totalBonus"] = $grandTotalBonus[$bonusName] ? number_format($grandTotalBonus[$bonusName],$decimal,".","") : 0;
                        $grandTotalList[$bonusName]["bonusName"] = 'Grand Total '.$translations[$bonusCode][$language].' (IDR)';
                    }
                }

                $headerList[] = $translations["A00969"][$language];
                $headerList[] = $translations["A00148"][$language];
                $headerList[] = $translations["A00117"][$language];
                $headerList[] = $translations["A00194"][$language];
                $headerList[] = $translations["A01616"][$language];
                // $headerList[] = $translations["A01617"][$language];
                $headerList[] = $translations["B00442"][$language];
                $headerList[] = $translations["B00443"][$language];
                $headerList[] = $translations["A00597"][$language];
                $headerList = array_merge($headerList, $headerBonus);
                $headerList[] = 'Total (IDR)';

                $totalBonusList[] = "";
                $totalBonusList[] = "";
                $totalBonusList[] = "";
                $totalBonusList[] = "";
                $totalBonusList[] = "";
                $totalBonusList[] = "";
                $totalBonusList[] = "";
                $totalBonusList[] = 'Grand Total';
                $totalBonusList += $rowBonus;
                $totalBonusList["totalBonus"] = 0;

            }

            if($onloaded){
                unset($totalBonusList);
            }
            if($mainUsernameIDAry) $db2->where('client_id', $mainUsernameIDAry, 'IN');
            if($downlineIDArray) $db2->where('client_id', $downlineIDArray, 'IN');

            if($searchTs){
                if($searchTs['startTs']) $db2->where('bonus_date', $searchTs['startTs'], '>=');
                if($searchTs['endTs']) $db2->where('bonus_date', $searchTs['endTs'], '<=');
            }

/*            if($fullNameResult){
                if($dataType == "like"){
                    $db->where('client_id', $fullNameResult, 'IN');

                } else{
                    $db->where('client_id', $fullNameResult);
                }
            }
*/
            //if($memberIDResult) $db->where('client_id', $memberIDResult);
/*
            if($newSearchArray) $db2->where('client_id', $newSearchArray, 'IN');

            if($searchArray) $db2->where('client_id', $searchArray, 'IN');

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            //if($sponId) $db->where('client_id', $sponId, 'IN');

            //if($mainLeadID) $db2->where('client_id', $mainLeadID, 'IN');
*/
            if($downlines) $db2->where('client_id', $downlines, "IN");

            if($mainDownlines) $db2->where('client_id', $mainDownlines, "IN");
            
            $db2->groupBy('client_id');
            $db2->groupBy('bonus_date');
            $db2->orderBy('bonus_date', 'DESC');
            $db2->orderBy('id', 'DESC');
            // if(!$seeAll){
                $copyDb = $db2->copy();
            // }

            if($type == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            if($downlines) $db2->where('client_id',$downlines,"IN");
            $clientBonusResult = $db2->get('mlm_bonus_report', $limit, "id,client_id,bonus_date AS bonusDate $bonusNameSQLColumns ");

            unset($clientIDArray);

            if(empty($clientBonusResult)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach($clientBonusResult as $row){
                $clientIDAry[$row['client_id']] = $row['client_id'];
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry,'IN');
                $getSponID = $db->map('id')->get('client', null,'id, sponsor_id');

                $db->where('client_id', $clientIDAry,'IN');
                $getMainLeaderID = $db->map('client_id')->get('mlm_leader', null,'client_id, leader_id');

                $db->groupBy('client_id');
                $db->where('client_id', $clientIDAry, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            // if ($seeAll&&!$onloaded){//IF see all grab all client Data
                $db->where('type','Client');
                $clientData=$db->map('id')->arrayBuilder()->get('client',null,'id, main_id, username, name, member_id');
            // }    

                foreach ($clientData as $client) {
                    $clientInfo[] = $client['id'];
                }

                $db->join("mlm_bank b", "b.id = c.bank_id", "LEFT");
                $db->where('c.client_id', $clientInfo, "IN");
                $db->where('c.status','Active');
                $bankData=$db->map('client_id')->arrayBuilder()->get('mlm_client_bank c', null, "c.id, c.client_id, c.account_no, b.id, b.name AS bank_name, c.branch AS branch_name");

            foreach ($clientBonusResult as $row) {
                $clientID = $row['client_id'];
                // $bonusDate = $row['bonus_date'];

                /*if(!$clientData[$row['client_id']]){
                    $db->where('id', $row['client_id']);
                    $clientData[$clientID] = $db->getOne('client', 'id, main_id, username, name, member_id');
                }*/

                $orderRow['id']=$row['id'];
                $orderRow['bonusDate']= date($dateTimeFormat, strtotime($row['bonusDate']));
                $orderRow['memberID'] = $clientData[$clientID]['member_id'];
                $orderRow['fullname'] = $clientData[$clientID]['name'];
                $orderRow['cityName'] = $getCityName[$getCityID[$clientID]]?:'-';
                $orderRow['sponsorID'] = $clientData[$getSponID[$clientID]]['member_id']?:'-';
                // $orderRow['mainLeaderID'] = $clientData[$getMainLeaderID[$clientID]]['member_id']?:'-';
                $orderRow['bankName'] = $bankData[$clientID]['bank_name']?:'-';
                $orderRow['bankAccountNo'] = $bankData[$clientID]['account_no']?:'-';
                $orderRow['bankBranch'] = $bankData[$clientID]['branch_name']?:'-';
                //$orderRow['mainUsername'] = $clientData[$clientID]['main_id'] > 0 ? $clientData[$clientData[$clientID]['main_id']]["username"] : "-";

                $eachClientID['clientID'] = $clientID;

                /*if(!$clientData[$clientID]['mainLeaderUsername']){//Saving to Array, Client's mainLeaderUsername so not to go searching again for each loop
                    $clientData[$clientID]['mainLeaderUsername'] = Tree::getMainLeaderUsername($eachClientID)? :'-' ;
                }

                $mainLeaderUsername = $clientData[$clientID]['mainLeaderUsername'];
                $orderRow['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";*/

                // foreach ($bonusNameList as $bonusName => $bonusCode) {
                //     $orderRow[$bonusName]=$row[$bonusName];
                //     $row['totalBonus']+=$row[$bonusName];
                //     $orderRow[$bonusName] =  number_format($row[$bonusName], $decimal, '.', ',');
                // }

                foreach ($bonusNameList as $bonusName => $bonusCode) {

                    if (in_array($bonusName, $disabledBonus)) {
                        $orderRow[$bonusName]=$row[$bonusName] * $cvRate;
                        $row['totalBonus']+=($row[$bonusName] * $cvRate);
                        $orderRow[$bonusName] =  number_format($row[$bonusName] * $cvRate, $decimal, '.', ',');
                    } else {
                        $orderRow[$bonusName]=$row[$bonusName];
                        $row['totalBonus']+=$row[$bonusName];
                        $orderRow[$bonusName] =  number_format($row[$bonusName], $decimal, '.', ',');
                    }
                }

                // if($totalBonusList){
                //     foreach ($bonusNameList as $bonusName => $bonusCode) {
                //         $totalBonusList[$bonusName] += $row[$bonusName];
                //         $totalBonusList["totalBonus"] += $row[$bonusName];
                //     }
                // }

                if($totalBonusList){
                    foreach ($bonusNameList as $bonusName => $bonusCode) {
                        if (in_array($bonusName, $disabledBonus)) {
                            $totalBonusList[$bonusName] += ($row[$bonusName] * $cvRate);
                            $totalBonusList["totalBonus"] += ($row[$bonusName] * $cvRate);    
                        } else {
                            $totalBonusList[$bonusName] += $row[$bonusName];
                            $totalBonusList["totalBonus"] += $row[$bonusName];     
                        }
                       
                    }
                }


                $orderRow['totalBonus']=number_format($row['totalBonus'], $decimal, '.', ',');//$row['totalBonus'];
                $bonusList[]=$orderRow;
                unset($row['totalBonus']);
                unset($orderRow);
            }   

            foreach($totalBonusList AS $type=>$total){
            	$totalBonusList[$type] = number_format($total, $decimal, '.', ',');
            }
            
            $totalPage = 1;

            $copyDb->get('mlm_bonus_report', null, 'id');
            $totalRecord = $copyDb->count;
            $pagingLimit = $limit[1];
            $totalPage = ceil($totalRecord/$pagingLimit);
    
            
            $dataOut['bonusNameList']       = $bonusNameList;
            $dataOut['headerList']          = $headerList;
            $dataOut['grandTotalList']      = $grandTotalList;
            $dataOut['bonusList']           = $bonusList;
            $dataOut['totalBonusList']      = $totalBonusList;
            $dataOut['numRecord']           = $pagingLimit;
            $dataOut['totalRecord']         = $totalRecord;
            $dataOut['pageNumber']          = $pageNumber;
            $dataOut['totalPage']           = $totalPage;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $dataOut);
        }

        public function getBonusPayoutSummary($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            // $bonus          = $this->bonus;
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['systemDateFormat'];
            $floor = pow(10, $decimalPlaces); // floor for extra decimal

            $db->where('name','awardBonus',"!=");
            $db->where("disabled", "0");
            $db->orderBy("priority", "ASC");
            $bonusRes = $db->get('mlm_bonus', null, "name, payment, language_code, table_name");
            foreach($bonusRes as &$bonusRow ){
                $bonusNameArray[] = $bonusRow['name'];
                $bonusRow['display'] = ($translations[$bonusRow['language_code']][$language] != ""? $translations[$bonusRow['language_code']][$language] :  $bonusRow['name']);
                $bonusArray[$bonusRow['name']] = $bonusRow;
            }

            //define params
            $searchBonusIn = $db->copy();
            $getCVRate = $db->copy();
            $cpDb = $db->copy();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':
                            $db->where('username', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            break;

                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            // $db->where('client_id', $mainDownlines, "IN");

                            break;

                        case 'leaderID':
                            $db->where('member_id', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            // $db->where('client_id', $downlines, "IN");
                            $searchBonusIn ->where('client_id', $downlines, 'IN');

                            break;
                            
                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     // $db->where('client_id', $mainDownlines, "IN");


                        //     break;
                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where('client_id = leader_id');
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null,'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }
                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'bonusDate':
                            // Set db column here
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom<0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                /*Calculate Monthly*/
                                // $db->where('LAST_DAY('.$columnName.')', date('Y-m-d', $dateFrom), '>=');
                                // $searchBonusIn->where("LAST_DAY(created_at)", date('Y-m-d', $dateFrom), '>=');

                                /*Calculate Daily*/
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                                $searchBonusIn->where("DATE(created_at)", date('Y-m-d', $dateFrom), '>=');
                            }

                            if(strlen($dateTo) > 0) {
                                if($dateTo<0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                if($dateTo < $dateFrom) {
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                }

                                /*Calculate Monthly*/
                                // $db->where('LAST_DAY('.$columnName.')', date('Y-m-d', $dateTo), '<=');
                                // $searchBonusIn->where("LAST_DAY(created_at)", date('Y-m-d', $dateTo), '<=');

                                /*Calculate Daily*/
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                                $searchBonusIn->where("DATE(created_at)", date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            } 
            
            if ($downlines) $searchBonusIn->where('client_id', $downlines, "IN");
            if ($mainDownlines) $searchBonusIn->where('client_id', $mainDownlines, "IN");

            $bonusInRes = $searchBonusIn->get('mlm_client_portfolio', null, "created_at, product_price, client_id");
            // return $bonusInRes;

            foreach( $bonusInRes as $row ){
                /*Calculate Monthly*/
                // $bonusDate = date($dateTimeFormat, strtotime(date('Y-m-t',strtotime($row["created_at"]))));

                /*Calculate Daily*/
                $bonusDate = date($dateTimeFormat, strtotime($row["created_at"]));
                // $bonusReport[$bonusDate]['totalBonusValue'] += $row['bonus_value'];
                $bonusReport[$bonusDate]['totalSales'] += $row['product_price'];
            }

            $copyDbA = $db->copy();

            //To ensure copyDbA is able to get the "WHERE" clause same as searchBonusIn, as copyDbA uses bonus_date instead of created_at for DATE
            if ($downlines) $copyDbA->where('client_id', $downlines, "IN");
            if ($mainDownlines) $copyDbA->where('client_id', $mainDownlines, "IN");

            $copyDbB = $db->copy();
            $copyDbA->groupBy("bonus_date");
            $copyDbA->groupBy("bonus_type");
            $copyDbA->orderBy("bonus_date", "DESC"); 

            $bonusSponsorTableResultTemp = $copyDbA->get("mlm_bonus_report", null, "sum(bonus_amount) AS bonus_amount, bonus_date, bonus_type");
            
            foreach ($bonusSponsorTableResultTemp as $key => $value) {
                /*Calculate Monthly*/
                // $value["bonus_date"] = date($dateTimeFormat, strtotime(date('Y-m-t',strtotime($value["bonus_date"]))));

                /*Calculate Daily*/
                $value["bonus_date"] = date($dateTimeFormat, strtotime($value["bonus_date"]));

                if($bonusArray[$value["bonus_type"]]["payment"] == "Bimonthly"){
                    if(date("d",strtotime($value["bonus_date"])) <= "15"){
                        $reportDate = date("Y-m-15",strtotime($value["bonus_date"]));
                        $bonusPayoutData[$reportDate][$value['bonus_type']] += $value["bonus_amount"];
                        $totalPayout[$reportDate] += $value["bonus_amount"];

                    }else{
                        $reportDate = date("Y-m-t",strtotime($value["bonus_date"]));
                        $bonusPayoutData[$reportDate][$value['bonus_type']] += $value["bonus_amount"];
                        $totalPayout[$reportDate] += $value["bonus_amount"];

                    }
                }else{
                    $bonusPayoutData[$value["bonus_date"]][$value['bonus_type']] = $value["bonus_amount"];
                    $totalPayout[$value["bonus_date"]] += $value["bonus_amount"];
                }
            }

            /*Calculate Monthly*/
            // $totalRecord = $copyDbB->getValue("mlm_bonus_calculation_batch", "count(DISTINCT CONCAT(YEAR(bonus_date),'-',MONTH(bonus_date),'-01'))");

            /*Calculate Daily*/
            $totalRecord = $copyDbB->getValue("mlm_bonus_calculation_batch", "count(DISTINCT bonus_date)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
                $totalPage = 1;
            }
            else{
                $totalPage = ceil($totalRecord/$limit[1]);
            }

            $db->orderBy('bonus_date', 'DESC');
            /*Calculate Monthly*/
            // $getBonusDateRange = $db->get("mlm_bonus_calculation_batch",$limit, "DISTINCT CONCAT(YEAR(bonus_date),'-',MONTH(bonus_date),'-01')AS bonus_date");

            /*Calculate Daily*/
            $getBonusDateRange = $db->get("mlm_bonus_calculation_batch",$limit, "DISTINCT bonus_date");

             if (empty($bonusInRes)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            if (!empty($getBonusDateRange)){

                foreach($getBonusDateRange as $key => $bonusDateValue) {
                    /*Calculate Monthly*/
                    // $bonusDateValue["bonus_date"] = date($dateTimeFormat, strtotime(date('Y-m-t',strtotime($bonusDateValue["bonus_date"]))));

                    /*Calculate Daily*/
                    $bonusDateValue["bonus_date"] = date($dateTimeFormat, strtotime($bonusDateValue["bonus_date"]));
                    
                    foreach ($bonusNameArray as $key => $bonusNameArrayValue) {
                        unset($reportDate);
                        $payoutAmount = $bonusPayoutData[$bonusDateValue["bonus_date"]][$bonusNameArrayValue]?:0;
                        // $percentage = $bonusReport[$bonusDateValue["bonus_date"]]['totalBonusValue']> 0? ($payoutAmount / $bonusReport[$bonusDateValue["bonus_date"]]['totalBonusValue'] * 100):0;
                        $percentage = $bonusReport[$bonusDateValue["bonus_date"]]['totalSales']> 0? ($payoutAmount / $bonusReport[$bonusDateValue["bonus_date"]]['totalSales'] * 100):0;

                        $tempValue["payout"] = number_format($payoutAmount, $decimalPlaces, '.', '' );
                        $tempValue["percentage"] = number_format( (floor(strval($percentage*$floor))/$floor) , $decimalPlaces , '.', '');
                        $bonusReportList[$bonusDateValue["bonus_date"]][$bonusNameArrayValue]= $tempValue;

                        $totalPayoutAmount += $payoutAmount;
                        $totalBonusPayout[$bonusNameArrayValue] += $payoutAmount;
                        $subTotalPayoutAmount += $payoutAmount;
                    }

                    // $totalBV = $bonusReport[$bonusDateValue['bonus_date']]['totalBonusValue'];
                    $totalS = $bonusReport[$bonusDateValue['bonus_date']]['totalSales'];

                    // $bonusReportList[$bonusDateValue["bonus_date"]]['totalBonusValue'] = number_format($totalBV?$totalBV:0, $decimalPlaces, '.', '' );
                    $bonusReportList[$bonusDateValue["bonus_date"]]['totalBonusValue'] = number_format($totalS?$totalS:0, $decimalPlaces, '.', '' );

                    // $subTotalPayoutAmountPercentage = $totalBV > 0 ? number_format( (floor(strval(($subTotalPayoutAmount / $totalBV * 100)*$floor))/$floor) , $decimalPlaces , '.', '') : 0.00;
                    $subTotalPayoutAmountPercentage = $totalS > 0 ? number_format( (floor(strval(($subTotalPayoutAmount / $totalS * 100)*$floor))/$floor) , $decimalPlaces , '.', '') : 0.00;

                    $bonusReportList[$bonusDateValue["bonus_date"]]['subTotalPayoutAmount'] = number_format($subTotalPayoutAmount?$subTotalPayoutAmount:0, $decimalPlaces, '.', '' );

                    $bonusReportList[$bonusDateValue["bonus_date"]]['subTotalPayoutAmountPercentage'] = $subTotalPayoutAmountPercentage > 0 ? number_format( (floor(strval($subTotalPayoutAmountPercentage*$floor))/$floor) , $decimalPlaces , '.', '') : 0;

                    // $totalBonusValue += $bonusReport[$bonusDateValue['bonus_date']]['totalBonusValue']?:0;
                    $totalSales += $bonusReport[$bonusDateValue['bonus_date']]['totalSales']?:0;

                    unset($subTotalPayoutAmount);
                }

                $totalBonusPayout['totalPayoutAmount'] = number_format($totalPayoutAmount, $decimalPlaces, '.', '');
                // $totalBonusPayout['totalBonusValue'] = number_format($totalBonusValue, $decimalPlaces, '.', '');
                $totalBonusPayout['totalBonusValue'] = number_format($totalSales, $decimalPlaces, '.', '');

                // $totalBonusPayout['grandSubTotalPayoutAmountPercentage'] = ($totalPayoutAmount/$totalBonusValue * 100) > 0 ? number_format( (floor(strval(($totalPayoutAmount/$totalBonusValue * 100)*$floor))/$floor) , $decimalPlaces , '.', '') : 0;
                $totalBonusPayout['grandSubTotalPayoutAmountPercentage'] = ($totalPayoutAmount/$totalSales * 100) > 0 ? number_format( (floor(strval(($totalPayoutAmount/$totalSales * 100)*$floor))/$floor) , $decimalPlaces , '.', '') : 0;

                krsort($bonusReport);

                foreach ($totalBonusPayout as $key => $totalBonusPayoutValue) {
                    $totalBonusPayout[$key] = number_format($totalBonusPayoutValue, $decimalPlaces, '.', '');
                    // $totalBonusPayout[$key.'Percentage'] = number_format(($totalBonusPayoutValue/$totalBonusValue*100), $decimalPlaces , '.', '');
                    $totalBonusPayout[$key.'Percentage'] = number_format(($totalBonusPayoutValue/$totalSales*100), $decimalPlaces , '.', '');

                    // $totalBonusPayout[$key.'Percentage'] = number_format( (floor(strval(($totalBonusPayoutValue/$totalBonusValue * 100)*$floor))/$floor) , $decimalPlaces , '.', '');
                    $totalBonusPayout[$key.'Percentage'] = number_format( (floor(strval(($totalBonusPayoutValue/$totalSales * 100)*$floor))/$floor) , $decimalPlaces , '.', '');
                }

                if($params['type'] == "export"){
                    $header = $params['header'];
                    $dataKeyArr = $params['key'];
                    $data["base64"] = Self::exportExcelBase64($bonusReport,$header,$dataKeyArr,"bonusPayoutSummary",$totalBonusReport);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                $data['bonusName'] = $bonusArray;
                $data['totalBonusReport'] = $totalBonusPayout;
                $data['report']   = $bonusReportList;
                $data['totalPage']   = $totalPage;
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

        }
        
        public function getBonusPayoutListing($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $db->userID;
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            $dateFormat = Setting::$systemSetting['systemDateFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $isCountryFilter= 0;

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            } 

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'country':
                            $db->where("country_id",$dataValue);
                            $isCountryFilter = $dataValue;
                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                $db->where('bonus_date', date('Y-m-d', $dateFrom), '>=');

                                
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);
                                }

                                if($dateTo == $dateFrom) {
                                    $db->where('bonus_date', date('Y-m-d', $dateTo), '<=');
                                }


                                $db->where('bonus_date', date('Y-m-d', $dateTo), '<=');
                                
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $db->where("status",$dataValue);

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

            }

            if(!$isCountryFilter){
              $db->where('country_id',100); // Default country Indonesia  
              $isCountryFilter = 100;
            } 
            $copyDb = $db->copy();
            $db->orderBy('bonus_date','DESC');
            $bonusPayoutRes = $db->get("bonus_payout_summary",$limit, "id, bonus_date, country_id, total_member, cv_rate, gm_cv, team_cv, leader_cv, award_payout, status, updated_at");

            $db->where('id',$isCountryFilter);
            $countryData = $db->getOne('country','currency_code,name');
            $currencyCode = $countryData['currency_code'];
            $countryName = $countryData['name'];

            unset($header);
            $header[] = $translations['B00428'][$language]/*Bonus Date*/;
            $header[] = $translations['B00429'][$language]/*Status*/;
            $header[] = $translations['B00485'][$language]/*Paid At*/;
            $header[] = $translations['B00430'][$language]/*Total Member*/;
            $header[] = $translations['B00431'][$language]/*Total Goldmine CV*/;
            $header[] = $translations['B00432'][$language]." ($currencyCode)"/*Total Goldmine Bonus*/;
            $header[] = $translations['B00433'][$language]/*Total Team Bonus CV*/;
            $header[] = $translations['B00434'][$language]." ($currencyCode)"/*Total Team Bonus*/;
            $header[] = $translations['B00435'][$language]/*Total Leadership Bonus CV*/;
            $header[] = $translations['B00436'][$language]." ($currencyCode)"/*Total Leadership Bonus*/;
            $header[] = $translations['B00437'][$language]." ($currencyCode)"/*Total Cash Award Bonus*/;
            $header[] = $translations['B00504'][$language]/* Recruit & Active Programme (IDR) */;
            $header[] = $translations['B00438'][$language]." ($currencyCode)"/*Total Total Bonus Amount*/;
            $data['header'] = $header;
            if(empty($bonusPayoutRes))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => $data);

            $db->where('bonus_type', 'recruitPromo');
            $db->groupBy('bonus_date');
            $rapRes = $db->map('bonus_date')->get('mlm_bonus_report', null, 'bonus_date, SUM(bonus_amount) as bonus_amount');

            foreach($bonusPayoutRes as $bonusPayoutRow){
                $bonusPayoutRow['bonusDate'] = date($dateFormat, strtotime($bonusPayoutRow["bonus_date"]));
                $bonusPayoutRow['status'] = $bonusPayoutRow["status"] == 'partial' ? "Partially Paid" :General::getTranslationByName($bonusPayoutRow["status"])?:"-";
                $bonusPayoutRow['paidAt'] = $bonusPayoutRow["updated_at"] > 0 ? date($dateTimeFormat, strtotime($bonusPayoutRow["updated_at"])) : "-";
                $bonusPayoutRow['totalMember'] = $bonusPayoutRow["total_member"]?:"0";
                $bonusPayoutRow['totalGoldMineCV'] = Setting::setDecimal($bonusPayoutRow["gm_cv"]?:"0");
                $totalGoldMineBonus = $bonusPayoutRow["cv_rate"] * $bonusPayoutRow['gm_cv'];
                $bonusPayoutRow['totalGoldMineBonus'] = Setting::setDecimal($totalGoldMineBonus)?:"0";
                $bonusPayoutRow['totalTeamCV'] = Setting::setDecimal($bonusPayoutRow["team_cv"])?:"0";
                $totalTeamBonus = $bonusPayoutRow["cv_rate"] * $bonusPayoutRow['team_cv'];                    
                $bonusPayoutRow['totalTeamBonus'] = Setting::setDecimal($totalTeamBonus)?:"0";
                $bonusPayoutRow['totalLeadershipCV'] = Setting::setDecimal($bonusPayoutRow["leader_cv"])?:"0";
                $totalLeadershipBonus = $bonusPayoutRow["cv_rate"] * $bonusPayoutRow["leader_cv"];
                $bonusPayoutRow['totalLeadershipBonus'] = Setting::setDecimal($totalLeadershipBonus)?:"0";
                $bonusPayoutRow['totalCashAwardBonus'] = Setting::setDecimal($bonusPayoutRow["award_payout"])?:"0";
                $bonusPayoutRow['recruitPromo'] = Setting::setDecimal($rapRes[$bonusPayoutRow["bonus_date"]])?:"0";
                $totalBonusAmount = $totalGoldMineBonus + $totalTeamBonus + $totalLeadershipBonus + $bonusPayoutRow["award_payout"] + $bonusPayoutRow['recruitPromo'];
                $bonusPayoutRow['totalBonusAmount'] = Setting::setDecimal($totalBonusAmount)?:"0";
                

                unset($bonusPayoutRow['bonus_date']);
                unset($bonusPayoutRow['country_id']);
                unset($bonusPayoutRow['total_member']);
                unset($bonusPayoutRow['cv_rate']);
                unset($bonusPayoutRow['gm_cv']);
                unset($bonusPayoutRow['team_cv']);
                unset($bonusPayoutRow['leader_cv']);
                unset($bonusPayoutRow['award_payout']);
                unset($bonusPayoutRow['updated_at']);

                $bonusPayoutArrType[] = $bonusPayoutRow;

            }
            $data['bonusPayoutList']= $bonusPayoutArrType;

            $totalRecord = $copyDb->getValue("bonus_payout_summary", "count(id)");
            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $params['file_name'] = $params['file_name']."_".$countryName;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);

        }
        
        public function getBonusPayoutSummaryOld($params,$site,$userID){

            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::$systemSetting["internalDecimalFormat"];

            $numberTotal = '0';
            $startCount = 1;
            $count = $startCount;

            $db->where("disabled", "0");
            $db->orderBy("priority", "ASC");
            $bonusRes = $db->get('mlm_bonus', NULL, 'name, language_code');
            foreach( $bonusRes as &$bonusRow ){
                $bonusNameArray[] = $bonusRow['name'];
                $bonusRow['display'] = ($translations[$bonusRow['language_code']][$language] != ""? $translations[$bonusRow['language_code']][$language] :  $bonusRow['name']);
                $bonusArray[$bonusRow['name']] = $bonusRow;
                $columnAmount[$count] = ' SUM(IF(bonus_type = "'.$bonusRow['name'].'",bonus_amount, 0)) amount'.$count;
                // $columnFromAmount[$count] = ' SUM(IF(bonus_type = "'.$bonusRow['name'].'",from_amount, 0)) fromAmount'.$count;
                $bonusName[$count]= $bonusRow['name'];
                $count++;
            }
            $adminLeaderAry = Setting::getAdminLeaderAry();

            $db->groupBy("created_at","CAST(created_at AS DATE)");
            $totalBV = $db->map('created_at')->get("mlm_bonus_in",NULL,"DATE(created_at) AS created_at, SUM(bonus_value) AS totalBv");

            if (count($searchData) > 0) {
                $cpDb = $db->copy();
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':

                            // $clientID = $db->subQuery();
                            $db->where('username', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($leaderID);
                            // $downlines[] = $clientID;

                            if (empty($downlines) || !$adminLeaderAry[$leaderID])
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

                    switch($dataName) {

                    case 'bonusDate':
                        // Set db column here
                        $columnName = 'bonus_date';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 1) {
                            
                            $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
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

            if($adminLeaderAry)$db->where('client_id', $adminLeaderAry, 'IN');
            if($site == "Member") $db->where("client_id",$userID);
            $db->groupBy("bonus_date");
            $db->orderBy("bonus_date", "DESC");

            if($seeAll == "1"){
                $limit = null;
            }
            $copyDbA = $db->copy();

            $res = $db->get('mlm_bonus_report', $limit, "bonus_date, SUM(bonus_amount) amount, ".implode(", ", $columnAmount) );
            foreach( $res as $row ){
                $count = $startCount;
                $bonusDate = date('Y-m-d', strtotime($row['bonus_date']));
                $bonusReport[$bonusDate][] = $totalBV[$bonusDate];
                $bonusReport[$bonusDate][] = $row['amount'];
                unset($percentage);
                $bonusReport[$bonusDate][] = $percentage;
                foreach ($columnAmount as $key => $value) {
                    $bonusReport[$bonusDate][] = bcmul((string)$row['amount'.$count], "1",  Setting::$systemSetting['internalDecimalFormat']);  //bonus payout
                    unset($percentage);
                    unset($bonusPercentage);
                    $bonusPercentage= $row['amount'.$count] == 0 ? 0 : ($row['amount'.$count] / $row['amount']) * 100; 
                    $bonusReport[$bonusDate][] = $bonusPercentage;
					$bonusReport[$bonusDate][2] += $bonusPercentage;
                    $count++;
                }
            }

            foreach ($bonusReport as &$eachRecord) {
                foreach ($eachRecord as $key => &$valueNumber) {
                    $totalArray[$key] += $valueNumber;
                    $valueNumber = number_format($valueNumber,$decimalPlaces,".","");
                }                
            }

            foreach ($totalArray as $key => &$value) {
                $value = number_format($value,$decimalPlaces,".","");
            }

            $getBonusDateRange = $copyDbA->get("mlm_bonus_report",null, "distinct bonus_date");
            $totalRecord = $copyDbA->count;
     
            if (!empty($getBonusDateRange)){

                if($params['type'] == "export"){
                    $header = $params['header'];
                    $dataKeyArr = $params['key'];
                    $data["base64"] = Self::exportExcelBase64($bonusReport,$header,$dataKeyArr,"bonusPayoutSummary",$totalArray);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                $data['bonusName'] = $bonusArray;
                $data['bonusList'] = $bonusReport;
                $data['bonusTotalArray'] = $totalArray;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                if($seeAll == "1"){
                    $data['totalPage']   = 1;
                    $data['numRecord']   = $totalRecord;
                }


                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
        }

        public function getOldSponsorBonusReport($params,$userID,$site){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::$systemSetting["internalDecimalFormat"];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $usernameSearchType = $params["usernameSearchType"];
            $db->where('type','Client');
            $clientData = $db->map('id')->get('client',null,'id,username,name');


            $tableName      = "mlm_bonus_sponsor";
            $bonusType      = "sponsorBonus";
            $column         = array(
                "(SELECT name FROM rank where id = rank_id) AS rank_id",
                "(SELECT username FROM client where id = from_id) AS from_id",
                "(SELECT name FROM client where id = from_id) AS from_name",
                "(SELECT name FROM rank where id = from_rank_id) AS from_rank_id",
                "percentage",
                "(SELECT id FROM client where id = client_id) AS clientID",
                "(SELECT member_id FROM client where id = client_id) AS member_id",
                "(SELECT username FROM client where id = client_id) AS username",
                "(SELECT name FROM client where id = client_id) AS name",
                // "(SELECT sponsor_id FROM client where id = client_id) AS sponsorID",
                // "(SELECT username FROM client where id = sponsorID) AS sponsorUsername",
                "payable_amount AS bonus_amount",
                "DATE(bonus_date) AS bonus_date",
                "from_amount", // bonus value
                "created_at",
                "bonus_id",
                "(SELECT client_id FROM mlm_bonus_in where id = bonus_id) AS real_from_id",
                // "(SELECT product_price FROM mlm_client_portfolio WHERE id = portfolio_id) AS stakeAmount", // stake amount
                // "(SELECT product_price FROM mlm_client_portfolio WHERE id = bonus_id) AS memberStake", // user stake
                // "(SELECT product_price FROM mlm_client_portfolio WHERE id = portfolio_id) AS memberStake", // member stake
            );

	        if($site == "Member"){
	            if(!$userID) 
	            return array('status' => "error", 'code' => 1, 'statusMsg' => "No Client ID" /* No results found */, 'data' => "");

	            $clientID = $userID;
	            $db->where("id",$clientID);
	            $clientIDCheck = $db->getValue("client","id");
	            if(!$clientIDCheck) 
	            return array('status' => "error", 'code' => 1, 'statusMsg' => "Client does not exist" /* No results found */, 'data' => "");
	        }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

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
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'name':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            // $db->where('client_id', $dataValue);
                            break;

                        case 'username':
                            if ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN");
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'bonusDate':
                            // Set db column here
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'fromUsername':
                            $sq = $db->subQuery();
                            $sq->where('username',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('from_id',$sq);
                            break;


                        case 'leaderUsername':

                            // Left Blank

                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // $copyDb = $db->copy();
            $db->orderBy("bonus_date", "DESC");
            $db->orderBy("id", "DESC");
            if($site == "Member"){
            	$db->where("client_id",$clientID);
            }
			if($adminLeaderAry)$db->where('client_id', $adminLeaderAry, 'IN');

            // $totalRecord = $copyDb->getValue ($tableName, "count(*)");
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 
            $result = $db->get($tableName, $limit, $column);

            if (!empty($result)){

                foreach($result as $value) {
                    // $bonus['bonus_date']    = $value['bonus_date'];
                    $bonus['bonus_date']    = $value['bonus_date'] != '0000-00-00' ? date('d/m/Y', strtotime($value['bonus_date'])) : "-";

                    $bonus['username']      = $value['username'];
                    $bonus['member_id']      = $value['member_id'];
                    // $bonus['leaderUsername']      = $value['sponsorUsername'];
                    $bonus['name']          = $value['name'];
                    // $bonus['from_id']       = $value['from_id'];
                    // $bonus['from_name']       = $value['from_name'];

                    // if($site == "Member"){
                    $bonus['from_id'] = $clientData[$value['real_from_id']]['username'];
                    $bonus['from_name'] = $clientData[$value['real_from_id']]['name'];
                    // }

                    $bonus['amount']        = number_format($value['from_amount'],$decimalPlaces,'.',',');
                    $bonus['percentage']    = number_format($value['percentage'],'2','.',',');
                    $bonus['bonus_amount']  = number_format($value['bonus_amount'],$decimalPlaces,'.',',');
                    $bonusList[] = $bonus;
                    $grandTotal += $value['bonus_amount'];
                }

                $data['bonusList']   = $bonusList;
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;

                if($seeAll == "1"){
                    $data['totalPage'] = 1;
                    $data['numRecord'] = $totalRecord;
                }else{
                    $data['totalPage'] = ceil($totalRecord/$limit[1]);
                    $data['numRecord'] = $limit[1];
                }
                // $data['totalPage']   = ceil($totalRecord/$limit[1]);
                // $data['numRecord']   = $limit[1];
                $data['grandTotal'] = number_format($grandTotal,$decimalPlaces,'.',',');

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
        }

        public function getSponsorBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting["systemDateTimeFormat"];
            // $dateFormat = Setting::$systemSetting["systemDateFormat"];
            // manual set date setting for this report
            $dateFormat = 'd/m/Y h:i';
            
            $seeAll = $params["seeAll"];
            $pageNumber = $params["pageNumber"] ? $params["pageNumber"] : 1;
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params["searchData"];
            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case "leaderUsername":
                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if(empty($downlines)) {
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $downlines, "IN");

                            break;

                        case "mainLeaderUsername":
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
                    $dataType  = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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

                        case 'fromName':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("from_client_id", $sq, "in");
                            break;

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

                        case 'fromUsername':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'bonusDate':
                            $columnName = "bonus_date";

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }

                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
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

            $db->orderBy("bonus_date", "DESC");
            $db->orderBy("created_at", "DESC");

            if($site == "Member") {
                $db->where("client_id", $userID);
            }

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("mlm_bonus_sponsor", "count(*)");
          
            if($seeAll == "1") {
                $limit = array(0, $totalRecord);
            }

            $result = $db->get("mlm_bonus_sponsor", $limit, "id, bonus_id, client_id, bonus_date, game_id, product_id, from_client_id, from_amount, percentage, payable_amount,created_at");

            if(!$result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
            }

            foreach($result as $value) {
                $clientIDAry[$value["client_id"]] = $value["client_id"];
                $clientIDAry[$value["from_client_id"]] = $value["from_client_id"];
            }

            if($clientIDAry) {
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, name, member_id");
            }

            foreach($result as $value) {
                $bonus['bonus_date'] = date($dateFormat, strtotime($value['created_at']));

                unset($clientData);
                $clientData = $clientDataAry[$value['client_id']];
                $bonus['username'] = $clientData['username'];
                $bonus['name'] = $clientData['name'];
                $bonus['memberID'] = $clientData['member_id'];

                unset($fromData);
                $fromData = $clientDataAry[$value['from_client_id']];
                $bonus['fromUsername'] = $fromData['username'];
                $bonus['fromName'] = $fromData['name'];
                $bonus['fromMemberID'] = $fromData['member_id'];

                $bonus['fromAmount'] = Setting::setDecimal($value['from_amount']);
                $bonus['percentage'] = Setting::setDecimal($value['percentage']);
                $bonus['payableAmount'] = Setting::setDecimal($value['payable_amount']);
                $grandTotal += $value['payable_amount'];

                $bonusList[] = $bonus;
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $bonusList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['grandTotal'] = Setting::setDecimal($grandTotal);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);  
        }

        public function getGoldmineBonusReport($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $bonusDateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];

            $userID = $db->userID;
            $site = $params['site']?:$db->userType;

            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            $column         = array(
            	"client_id",
            	"from_client_id",
                "compress_level as compressLvl",
                "from_level as fromLvl",
                "percentage",
                "bonus_date",
                "from_amount as bonusValue", // bonus value
                "payable_amount as payableAmount",
                "created_at",
                "rank_id",
                "from_rank_id",
            );

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

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            break;

                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
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
                        case 'fullname':
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

                        case 'fromName':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("from_client_id", $sq, "in");
                            break;

                        case 'username':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'fromUsername':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("from_client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("from_client_id", $sq, "IN"); 
                            }
                            break;

                        case 'createdAt':
                        case 'bonusDate':
                            // Set db column here
                            $columnName = 'date(mlm_bonus_goldmine.bonus_date)';
                                    
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('from_client_id',$sq);
                            break;

                        case 'email':
                            $sq = $db->subQuery();
                            $sq->where('email',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('client_id',$sq,"IN");
                            break;

                        case 'fromEmail':
                            $sq = $db->subQuery();
                            $sq->where('email',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('from_client_id',$sq,"IN");
                            break;

                        case 'rank':
                            $db->where('rank_id',$dataValue);
                            break;

                        case 'fromRank':
                            $db->where('from_rank_id',$dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
             

            $db->orderBy("created_at", "DESC");
            if($site == 'Member'){
                $db->where("client_id", $userID);
            }
            $copyDb = $db->copy();
            $copyGrandTotalDB1 = $db->copy();
            $copyGrandTotalDB2 = $db->copy();
            $totalRecord = $copyDb->getValue ("mlm_bonus_goldmine", "count(*)");
          
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 

            if($mainDownlines) $db->where('client_id', $mainDownlines, "IN");
            if($downlines) $db->where('client_id', $downlines, "IN");
            $result = $db->get("mlm_bonus_goldmine", $limit, $column);
            if (empty($result))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach ($result as $value) {
                $clientIDAry[$value['client_id']]       = $value['client_id'];
                $clientIDAry[$value['from_client_id']]  = $value['from_client_id'];
                $rankIDAry[$value['rank_id']]           = $value['rank_id'];
                $rankIDAry[$value['from_rank_id']]      = $value['from_rank_id'];
            }

            if($clientIDAry){
                $db->where("id",$clientIDAry,"IN");
                $db->where('type', 'Client');
                $clientDataAry = $db->map('id')->get('client', NULL, 'id, username, name, member_id, email, country_id');

                $db->groupBy('client_id');
                $db->where('client_id', $clientIDAry, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            if($rankIDAry){
                $db->where("id",$rankIDAry,"IN");
                $rankTranslationCode = $db->map('id')->get('rank', NULL, 'id, translation_code');   
            }

            //Get CV Rate
            $cvRateRes = $db->get('bonus_payout_summary',null,'bonus_date,country_id,cv_rate');
            foreach ($cvRateRes as $cvRateRow) {
                $cvRateArr[$cvRateRow['bonus_date']][$cvRateRow['country_id']] = $cvRateRow['cv_rate'];
            }

            foreach($result as $value) {
                $clientData = $clientDataAry[$value['client_id']];
                $cvRate = $cvRateArr[$value['bonus_date']][$clientData['country_id']];

                $bonus['bonus_date']    = date($bonusDateTimeFormat, strtotime($value['bonus_date']));

                if($site == 'Admin'){
                    $bonus['username']      = $clientData['username']?$clientData['username']:"-";
                    $bonus['memberID']      = $clientData['member_id']?$clientData['member_id']:"-";
                    $bonus['fullname']      = $clientData['name']?$clientData['name']:"-";
                    $bonus['cityName']      = $getCityName[$getCityID[$value['client_id']]]?:'-';
                    $bonus['email']         = $clientData['email']?$clientData['email']:"-";
                    $bonus['rankDisplay']   = $rankTranslationCode[$value['rank_id']]?$translations[$rankTranslationCode[$value['rank_id']]][$language]:"-";
                }
                
                $fromData = $clientDataAry[$value['from_client_id']];
                $bonus['fromUsername']  = $fromData['username']?$fromData['username']:"-";
                $bonus['fromMemberID']  = $fromData['member_id']?$fromData['member_id']:"-";
                $bonus['fromFullname']  = $fromData['name']?$fromData['name']:"-";
                $bonus['fromCityName']  = $getCityName[$getCityID[$value['from_client_id']]]?:'-';
                $bonus['fromEmail']     = $fromData['email']?$fromData['email']:"-";
                $bonus['fromRankDisplay'] = $rankTranslationCode[$value['from_rank_id']]?$translations[$rankTranslationCode[$value['from_rank_id']]][$language]:"-";


                $bonus['fromLvl']       = $value['fromLvl']>0?$value['fromLvl']:"-";
                $bonus['compressLvl']   = $value['compressLvl']>0?$value['compressLvl']:"-";
                $bonus['bonusValue']    = Setting::setDecimal($value['bonusValue']);
                $bonus['percentage']    = Setting::setDecimal($value['percentage']);
                $bonus['payableAmount'] = Setting::setDecimal($value['payableAmount']);
                $bonus['cvRate']        = Setting::setDecimal($cvRate);
                $bonus['bonusPayout'] = Setting::setDecimal(($bonus['payableAmount']* $cvRate));
                $totalGoldmineBonusPerpage += Setting::setDecimal($value['payableAmount']);
                $bonus['created_at']        = date($dateTimeFormat, strtotime($value['created_at']));
                $bonusList[] = $bonus;
            }

            $grandTotal['bonusValue'] = $copyGrandTotalDB1->getValue('mlm_bonus_goldmine', 'SUM(from_amount)');
            $grandTotal['bonusValue'] = Setting::setDecimal($grandTotal['bonusValue']);
            $grandTotal['payableAmount'] = $copyGrandTotalDB2->getValue('mlm_bonus_goldmine', 'SUM(payable_amount)');
            $grandTotal['payableAmount'] = Setting::setDecimal($grandTotal['payableAmount']);

             if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $params['site'] = $site;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
             }

            $data['bonusList'] = $bonusList;
            $data['totalGoldmineBonusPerpage'] = Setting::setDecimal($totalGoldmineBonusPerpage); 
            $data['grandTotal'] = $grandTotal;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);  
        }

        public function getTeamBonusReport($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $bonusDateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];

            $userID = $db->userID;
            $site = $params['site']?:$db->userType;

            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            $column         = array(
                "client_id",
                "bonus_date",
                "rank_id",
                "from_client_id",
                "from_level as fromLvl",
                "from_amount as bonusValue",
                "percentage",
                "payable_amount as payableAmount",
                "created_at",
                "from_rank_id",
            );

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

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            break;

                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
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
                        case 'fullname':
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

                        case 'fromName':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("from_client_id", $sq, "in");
                            break;

                        case 'username':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'fromUsername':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("from_client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("from_client_id", $sq, "IN"); 
                            }
                            break;

                        case 'createdAt':
                        case 'bonusDate':
                            // Set db column here
                            $columnName = 'date(bonus_date)';
                                    
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('from_client_id',$sq);
                            break;

                        case 'email':
                            $sq = $db->subQuery();
                            $sq->where('email',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('client_id',$sq,"IN");
                            break;

                        case 'fromEmail':
                            $sq = $db->subQuery();
                            $sq->where('email',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('from_client_id',$sq,"IN");
                            break;

                        case 'rank':
                            $db->where('rank_id',$dataValue);
                            break;

                        case 'fromRank':
                            $db->where('from_rank_id',$dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
             

            $db->orderBy("created_at", "DESC");
            if($site == 'Member'){
                $db->where("client_id", $userID);
            }
            $copyDb = $db->copy();
            $copyGrandTotalDB1 = $db->copy();
            $copyGrandTotalDB2 = $db->copy();
            $totalRecord = $copyDb->getValue ("mlm_bonus_team", "count(*)");
          
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 

            if($mainDownlines) $db->where('client_id', $mainDownlines, "IN");
            if($downlines) $db->where('client_id', $downlines, "IN");
            $result = $db->get("mlm_bonus_team", $limit, $column);
            if (empty($result))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach ($result as $value) {
                $clientIDAry[$value['client_id']]   = $value['client_id'];
                $clientIDAry[$value['from_client_id']] = $value['from_client_id'];
                $rankIDAry[$value['rank_id']]      = $value['rank_id'];
                $rankIDAry[$value['from_rank_id']]      = $value['from_rank_id'];
            }

            if($clientIDAry){
                $db->where("id",$clientIDAry,"IN");
                $db->where('type', 'Client');
                $clientDataAry = $db->map('id')->get('client', NULL, 'id, username, name, member_id, email, country_id');

                $db->groupBy('client_id');
                $db->where('client_id', $clientIDAry, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            if($rankIDAry){
                $db->where("id",$rankIDAry,"IN");
                $rankTranslationCode = $db->map('id')->get('rank', NULL, 'id, translation_code');   
            }

            //Get CV Rate
            $cvRateRes = $db->get('bonus_payout_summary',null,'bonus_date,country_id,cv_rate');
            foreach ($cvRateRes as $cvRateRow) {
                $cvRateArr[$cvRateRow['bonus_date']][$cvRateRow['country_id']] = $cvRateRow['cv_rate'];
            }

            foreach($result as $value) {
                $clientData = $clientDataAry[$value['client_id']];
                $cvRate = $cvRateArr[$value['bonus_date']][$clientData['country_id']];

                $bonus['bonus_date']    = date($bonusDateTimeFormat, strtotime($value['bonus_date']));
                if($site == 'Admin'){
                    $bonus['username']      = $clientData['username']?$clientData['username']:"-";
                    $bonus['memberID']      = $clientData['member_id']?$clientData['member_id']:"-";
                    $bonus['fullname']      = $clientData['name']?$clientData['name']:"-";
                    $bonus['cityName']      = $getCityName[$getCityID[$value['client_id']]]?:'-';
                    $bonus['email']         = $clientData['email']?$clientData['email']:"-";
                    $bonus['rankDisplay']   = $rankTranslationCode[$value['rank_id']]?$translations[$rankTranslationCode[$value['rank_id']]][$language]:"-";
                }
                $fromData = $clientDataAry[$value['from_client_id']];
                $bonus['fromUsername']  = $fromData['username']?$fromData['username']:"-";
                $bonus['fromMemberID']  = $fromData['member_id']?$fromData['member_id']:"-";
                $bonus['fromFullname']  = $fromData['name']?$fromData['name']:"-";
                $bonus['fromCityName']  = $getCityName[$getCityID[$value['from_client_id']]]?:'-';
                $bonus['fromEmail']     = $fromData['email']?$fromData['email']:"-";
                $bonus['fromRankDisplay']= $rankTranslationCode[$value['from_rank_id']]?$translations[$rankTranslationCode[$value['from_rank_id']]][$language]:"-";
                
                $bonus['fromLvl']       = $value['fromLvl']>0?$value['fromLvl']:"-";
                $bonus['bonusValue']    = Setting::setDecimal($value['bonusValue']);
                $bonus['percentage']    = Setting::setDecimal($value['percentage']);
                $bonus['payableAmount'] = Setting::setDecimal($value['payableAmount']);
                $bonus['cvRate']        = Setting::setDecimal($cvRate);
                $bonus['bonusPayout']  = Setting::setDecimal(($bonus['payableAmount']* $cvRate));


                $totalTeamBonusPerpage += Setting::setDecimal($value['payableAmount']);
                $bonus['created_at']        = date($dateTimeFormat, strtotime($value['created_at']));
                $bonusList[] = $bonus;
            }

            $grandTotal['bonusValue'] = $copyGrandTotalDB1->getValue('mlm_bonus_team', 'SUM(from_amount)');
            $grandTotal['bonusValue'] = Setting::setDecimal($grandTotal['bonusValue']);
            $grandTotal['payableAmount'] = $copyGrandTotalDB2->getValue('mlm_bonus_team', 'SUM(payable_amount)');
            $grandTotal['payableAmount'] = Setting::setDecimal($grandTotal['payableAmount']);

             if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $params['site'] = $site;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
             }

            $data['bonusList'] = $bonusList;
            $data['totalTeamBonusPerpage'] = Setting::setDecimal($totalTeamBonusPerpage); 
            $data['grandTotal'] = $grandTotal;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);  
        }

        public function getAwardBonusReport($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $bonusDateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            $column         = array(
                "client_id",
                "bonus_date",
                "bonus_type",
                "from_amount as bonusValue",
                "percentage",
                "payable_amount as payableAmount",
                "created_at",
            );

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

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

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
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'createdAt':
                        case 'bonusDate':
                            // Set db column here
                            $columnName = 'date(bonus_date)';
                                    
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'bonusType':
                            $db->where('bonus_type',$dataValue);
                            break;

                        case 'email':
                            $sq = $db->subQuery();
                            $sq->where('email',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('client_id',$sq,"IN");
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
             

            $db->orderBy("created_at", "DESC");
            if($site == 'Member'){
                $db->where("client_id", $userID);
            }
            $copyDb = $db->copy();
            $copyGrandTotalDB1 = $db->copy();
            $copyGrandTotalDB2 = $db->copy();
            $totalRecord = $copyDb->getValue ("mlm_bonus_award", "count(*)");
          
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 

            if($mainDownlines) $db->where('client_id', $mainDownlines, "IN");
            if($downlines) $db->where('client_id', $downlines, "IN");
            $result = $db->get("mlm_bonus_award", $limit, $column);
            if (empty($result))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach ($result as $value) {
                $clientIDAry[$value['client_id']]   = $value['client_id'];
                $rankIDAry[$value['rank_id']]      = $value['rank_id'];
            }

            if($clientIDAry){
                $db->where("id IN ('".implode("','", $clientIDAry)."')");
                $db->where('type', 'Client');
                $clientDataAry = $db->map('id')->get('client', NULL, 'id, username, name, member_id, email');

                $db->groupBy('client_id');
                $db->where('client_id', $clientIDAry, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            foreach($result as $value) {
                $bonus['bonus_date']    = date($bonusDateTimeFormat, strtotime($value['bonus_date']));
                $clientData = $clientDataAry[$value['client_id']];
                $bonus['username']      = $clientData['username']?$clientData['username']:"-";
                $bonus['cityName']      = $getCityName[$getCityID[$value['client_id']]]?:'-';
                $bonus['memberID']      = $clientData['member_id']?$clientData['member_id']:"-";
                $bonus['email']         = $clientData['email']?$clientData['email']:"-";
                $bonus['bonusType']     = General::getTranslationByName($value['bonus_type']);
                $bonus['bonusValue']    = Setting::setDecimal($value['bonusValue']);
                $bonus['percentage']    = Setting::setDecimal($value['percentage']);
                $bonus['payableAmount'] = Setting::setDecimal($value['payableAmount']);
                $totalTeamBonusPerpage += Setting::setDecimal($value['payableAmount']);
                $bonus['created_at']        = date($dateTimeFormat, strtotime($value['created_at']));
                $bonusList[] = $bonus;
            }

            $grandTotal['bonusValue'] = $copyGrandTotalDB1->getValue('mlm_bonus_award', 'SUM(from_amount)');
            $grandTotal['bonusValue'] = Setting::setDecimal($grandTotal['bonusValue']);
            $grandTotal['payableAmount'] = $copyGrandTotalDB2->getValue('mlm_bonus_award', 'SUM(payable_amount)');
            $grandTotal['payableAmount'] = Setting::setDecimal($grandTotal['payableAmount']);

             if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
             }

            $data['bonusList'] = $bonusList;
            $data['totalAwardBonusPerpage'] = Setting::setDecimal($totalTeamBonusPerpage); 
            $data['grandTotal'] = $grandTotal;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);  
        }

        public function getCommunityBonusReport($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            $tableName      = "mlm_bonus_community";

            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];
            
            $column         = array(
                "client_id",
                "from_client_id",
                "from_level",
                "percentage",
                "bonus_date",
                "from_amount", // bonus value
                "payable_amount",
            );

            $db->where('type', 'Client');
            $clientDataAry = $db->map('id')->get('client', null, 'id, username, name');

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
                    case 'name':
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

                    case 'fromName':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->get("client", NULL, "id");
                        $db->where("from_client_id", $sq, "in");
                        break;

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

                    case 'fromUsername':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("from_client_id", $sq);
                        break;

                    case 'phone':
                        $sq = $db->subQuery();
                        $sq->where("phone", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);
                        break;

                    case 'bonusDate':
                        // Set db column here
                        $columnName = 'bonus_date';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'leaderUsername':

                        // Left Blank

                        break;

                    case 'mainLeaderUsername':


                        break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
             

            $db->orderBy("bonus_date", "DESC");
            if($site == 'Member'){
                $db->where("client_id", $userID);
            }
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue ($tableName, "count(*)");
          
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 
            $result = $db->get($tableName, $limit, $column);

            if (empty($result))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach($result as $value) {

                $bonus['bonus_date'] = date($dateTimeFormat,strtotime($value['bonus_date']));

                $clientData = $clientDataAry[$value['client_id']];
                $bonus['username'] = $clientData['username'];
                $bonus['name']        = $clientData['name'];

                $fromData = $clientDataAry[$value['from_client_id']];
                $bonus['fromUsername']      = $fromData['username'];
                $bonus['fromName']      = $fromData['name'];

                $bonus['level']      = $value['from_level'];

                $clientID=$value['clientID'];
                $eachClientID['clientID'] = $clientID;

                // if(!$clientData[$clientID]['mainLeaderUsername']){//Saving to Array, Client's mainLeaderUsername so not to go searching again for each loop
                //     $clientData[$clientID]['mainLeaderUsername'] = Tree::getMainLeaderUsername($eachClientID)? :'-' ;
                // }

                // $mainLeaderUsername = $clientData[$clientID]['mainLeaderUsername'];

                // $bonus['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                $bonus['fromAmount']          = $value['from_amount'];
                $bonus['percentage']          = $value['percentage'];
                $bonus['payableAmount']          = $value['payable_amount'];
                $grandTotal += $value['payable_amount'];

                $bonusList[] = $bonus;
            }

             if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
             }

            $data['bonusList']   = $bonusList;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['grandTotal']   = $grandTotal;


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);
            
        }

        public function getMatchingBonusReport($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            // $dateFormat = Setting::$systemSetting['systemDateFormat'];
            // manual set date setting for this report
            $dateFormat = 'd/m/Y h:i';
            $bonusType      = "matchingBonus";

            $userID = $db->userID;
            $site = $db->userType;

            $column         = array(
                "client_id",
                "from_id",
                "game_id",
                "from_amount",
                "from_level",
                "percentage",
                "payable_amount AS bonus_amount",
                "bonus_date",
                "created_at",
                "batch_id"
            );

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

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $db->where('client_id', $downlines, "IN");

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
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);

                        break;

                    case 'fromUsername':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("from_id", $sq);

                        break;

                    case 'bonusDate':
                        // Set db column here
                        $columnName = 'date(bonus_date)';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            
                            $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 1) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                
                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                            // $dateTo += 86399;
                            $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'memberID':
                        $sq = $db->subQuery();
                        $sq->where('member_id',$dataValue);
                        $sq->getOne('client','id');
                        $db->where('client_id',$sq);
                        break;

                    case 'fromMemberID':
                        $sq = $db->subQuery();
                        $sq->where('member_id',$dataValue);
                        $sq->getOne('client','id');
                        $db->where('from_id',$sq);
                        break;

                    case 'leaderUsername':
                        break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where("client_id", $userID);
            }
            $copyDb = $db->copy();
            $grandTotal = $db->copy();
            $db->orderBy("bonus_date", "DESC");
            $db->orderBy("created_at", "DESC");
            $totalRecord = $copyDb->getValue ("mlm_bonus_matching", "count(*)");

            $result = $db->get("mlm_bonus_matching", $limit, $column);
            if (!empty($result)){
                foreach ($result as $res) {
                    $clientIDAry[] = $res['client_id'];
                    $clientIDAry[] = $res['from_id'];
                    $fromIDAry[] = $res['from_id'];
                    $gameIDAry[] = $res['game_id'];
                    $batchIDAry[] = $res['batch_id'];
                }

                if($gameIDAry){
                    $db->where('id', $gameIDAry, 'IN');
                    $gameDataAry = $db->map('id')->get('game', NULL, 'id, product_id');

                    $db->where('id', array_values($gameDataAry), 'IN');
                    $productDataAry = $db->map('id')->get('mlm_product', NULL, 'id, translation_code');

                    if($fromIDAry){                        
                        $db->where('batch_id', $batchIDAry, 'IN');
                        $db->where('game_id', $gameIDAry, 'IN');
                        $db->where('client_id', $fromIDAry, 'IN');
                        $goldmineReceiverRes = $db->get('mlm_bonus_goldmine', NULL, 'game_id, client_id, batch_id, from_client_id');

                        foreach ($goldmineReceiverRes as $res) {
                            $goldmineReceiverAry[$res['game_id']][$res['client_id']][$res['batch_id']] = $res['from_client_id'];
                            $clientIDAry[] = $res['from_client_id'];
                        }
                    }
                }

                if($clientIDAry){
                    $db->where('id', $clientIDAry, 'IN');
                    $clientDataAry = $db->map('id')->get('client', NULL, 'id, username, name, member_id');
                }

                foreach($result as &$value) {
                    $value['bonus_amount'] = Setting::setDecimal($value['bonus_amount']);
                    $value['from_amount'] = Setting::setDecimal($value['from_amount']);
                    $value['percentage'] = Setting::setDecimal($value['percentage']);
                    $value['username'] = $clientDataAry[$value['client_id']]['username'];
                    $value['name'] = $clientDataAry[$value['client_id']]['name'];
                    $value['memberID'] = $clientDataAry[$value['client_id']]['member_id'];
                    $value['from_username'] = $clientDataAry[$value['from_id']]['username'];
                    $value['fromMemberID'] = $clientDataAry[$value['from_id']]['member_id'];
                    $value['created_at'] = date($dateTimeFormat, strtotime($value['created_at']));
                    $value['bonus_date'] = date($dateFormat, strtotime($value['bonus_date']));
                    $packageTranslate = $translations[$productDataAry[$gameDataAry[$value['game_id']]]][$language];
                    $value['package_name_display'] = $packageTranslate ? $packageTranslate : '-';
                    $receivedFrom = $goldmineReceiverAry[$value['game_id']][$value['from_id']][$value['batch_id']];
                    $value['receivedFrom'] = $clientDataAry[$receivedFrom]['username'];
                }

                $grandTotalList = $grandTotal->getOne('mlm_bonus_matching', 'SUM(from_amount) AS total_from_amount, SUM(payable_amount) AS total_payable_amount');
                $grandTotalAry['total_from_amount'] = Setting::setDecimal($grandTotalList['total_from_amount']);
                $grandTotalAry['total_bonus_amount'] = Setting::setDecimal($grandTotalList['total_payable_amount']);

                 if($params['type'] == "export") {
                     $params['command'] = __FUNCTION__;
                     $data = Excel::insertExportData($params);
                     return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                 }

                $data['grandTotal']  = $grandTotalAry;
                $data['bonusList']   = $result;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];


                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
        }

        public function getRebateBonusReport($params,$userID,$site){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'];
            $limit = General::getLimit($pageNumber);

            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $usernameSearchType = $params["usernameSearchType"];

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
                        case 'name':
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
                            if ($usernameSearchType == 'match') {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            } else if ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN'); 
                            }

                            break;

                        case 'bonusType':
                            $db->where('bonus_type', $dataValue);
                            break;

                        case 'refNo':
                            $sq = $db->subQuery();
                            $sq->where('reference_no', $dataValue);
                            $sq->getOne('mlm_client_portfolio', 'client_id');
                            $db->where('client_id', $sq);

                            break;

                        case 'bonusDate':
                            $columnName = 'date(bonus_date)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                                $db->where($columnName, date('Y-m-d',$dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d',$dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'package':
                            $db->where('product_id', $dataValue);
                            break;

                        case 'portfolioID':
                            $sq = $db->subQuery();
                            $sq->where('portfolio_id', $dataValue);
                            $sq->get('game_detail', null, 'game_id');
                            $db->where('game_id', $sq, 'IN');
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

            if($site == 'Member'){
                $db->where('mlm_bonus_rebate.client_id', $userID);
            }

			if($adminLeaderAry) $db->where('mlm_bonus_rebate.client_id', $adminLeaderAry, 'IN');

            $db->orderBy('bonus_date', 'DESC');
           
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue('mlm_bonus_rebate', "count(*)");

            if($seeAll == '1') {
                $limit = array(0, $totalRecord);
            }

            if($params['type'] == "export"){
                $limit = null;
            }

            $result = $db->get('mlm_bonus_rebate', $limit, 'client_id, product_id, percentage, payable_amount as amount, game_id, created_at');

            if (!empty($result)) {
                foreach($result as $value) {
                    $clientIDAry[$value['client_id']] = $value['client_id'];
                    $productIDAry[$value['product_id']] = $value['product_id'];
                    $gameIDAry[$value['game_id']] = $value['game_id'];
                }

                if($clientIDAry) {
                    $db->where('id', $clientIDAry, 'IN');
                    $clientUsernameAry = $db->map('id')->get('client', null, 'id, username, member_id');
                }

                if($productIDAry) {
                    $db->where('id', $productIDAry, 'IN');
                    $productNameAry = $db->map('id')->get('mlm_product', null, 'id, translation_code, price');
                }

                if($gameIDAry) {
                    $db->where('game_id', $gameIDAry, 'IN');
                    $portfolioIDAry = $db->map('game_id')->get('game_detail', null, 'game_id, portfolio_id');
                }

                foreach($result as $value) {
                    $bonus['bonusDate'] = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";
                    $bonus['username'] = $clientUsernameAry[$value['client_id']]['username'];
                    $bonus['memberID'] = $clientUsernameAry[$value['client_id']]['member_id'];
                    $bonus['packageDisplay'] = $translations[$productNameAry[$value['product_id']]['translation_code']][$language];
                    $bonus['packagePrice'] = number_format($productNameAry[$value['product_id']]['price'], $decimalPlaces, ".", "");
                    $bonus['portfolioID'] = $portfolioIDAry[$value['game_id']];
                    $bonus['percentage'] = number_format($value['percentage'], $decimalPlaces, ".", "");
                    $bonus['amount'] = number_format($value['amount'], $decimalPlaces, ".", "");

                    $totalAmount += number_format($value['amount'],$decimalPlaces,".","");

                    $bonusList[] = $bonus;
                }

                if($site == 'Member'){
                    $db->where('client_id', $userID);
                    $sumTotalBonus = $db->getValue('mlm_bonus_rebate','sum(amount)');
                    $data['sumTotalBonus'] = number_format($sumTotalBonus, $decimalPlaces, ".", "");
                }

                if($params['type'] == "export"){
                    $params['command'] = __FUNCTION__;
                    $data = Excel::insertExportData($params);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                $data['bonusList'] = $bonusList;
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                if($seeAll == "1"){
                    $data['totalPage'] = 1;
                    $data['numRecord'] = $totalRecord;
                }else{
                    $data['totalPage'] = ceil($totalRecord/$limit[1]);
                    $data['numRecord'] = $limit[1];
                }

                $data['grandTotal'] = number_format($totalAmount, $decimalPlaces, ".", "");

                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language] /* No Results Found */, 'data' => "");
        }

        public function getRebateBonusReportOld($params,$userID,$site){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $tableName      = "mlm_bonus_rebate";
            $decimalPlaces  = Setting::$systemSetting["internalDecimalFormat"];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $usernameSearchType = $params["usernameSearchType"];

            $column         = array(
                "(SELECT username FROM client where id = client_id) AS username",
                "(SELECT name FROM client where id = client_id) AS name",
                "(SELECT member_id FROM client where id = client_id) AS member_id",
                // "'" . $bonusType . "' AS bonus_type",
                "(SELECT translation_code FROM mlm_product WHERE id = product_id) AS packageNameCode",
                "product_id",
                "percentage",
                "trade_limit as tradeLimit",
                "trade_sales as tradeSales",
                "payable_amount AS amount",
                "bonus_date AS bonusDate",
                "bonus_type",
                // "created_at AS date",
                // "portfolio_id",
                "from_amount",
                "client_id AS clientID",
            );

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
                    case 'name':
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

                    case 'bonusType':
                        $db->where('bonus_type', $dataValue);
                        break;

                    case 'refNo':
                        $sq = $db->subQuery();
                        $sq->where('reference_no', $dataValue);
                        $sq->getOne("mlm_client_portfolio", "client_id");
                        $db->where("client_id", $sq);

                        break;

                    case 'bonusDate':
                        // Set db column here
                        $columnName = 'bonus_date';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where($columnName, date('Y-m-d',$dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where($columnName, date('Y-m-d',$dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'memberID':
                        $sq = $db->subQuery();
                        $sq->where('member_id',$dataValue);
                        $sq->getOne('client','id');
                        $db->where('client_id',$sq);
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

            if($site=='Member'){
                $db->where($tableName.".client_id",$userID);
            }

			if($adminLeaderAry)$db->where($tableName.'.client_id', $adminLeaderAry, 'IN');

            $db->orderBy("bonus_date", "DESC");
            // $db->orderBy("(SELECT hour FROM mlm_bonus_calculation_batch WHERE id = batch_id)", "DESC");
           
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue ($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }

            $result = $db->get($tableName, $limit, $column);

            if($site=='Member'){
                $db->where('client_id', $userID);
                $clientPortfolioList = $db->get('mlm_client_portfolio', null, 'id,reference_no');
                 foreach ($clientPortfolioList as $portfolio) {

                    $referenceAry[$portfolio['id']] = $portfolio['reference_no'];
                }
            }else{
                 $clientPortfolioList = $db->get('mlm_client_portfolio', null, 'id,reference_no');
                 foreach ($clientPortfolioList as $portfolio) {

                    $referenceAry[$portfolio['id']] = $portfolio['reference_no'];
                }
            }

            /*Get Product Setting*/
            $db->where('name','maintainanceFee');
            $productRes = $db->get('mlm_product_setting',null,'product_id,value');
            foreach ($productRes as $productRow) {
                $maintainanceFeeAry[$productRow['product_id']] = $productRow['value'];
            }


            if (!empty($result)){
                foreach($result as $value) {
                    // $bonus['bonusDate']    = date($dateTimeFormat,strtotime($value['bonusDate']));
                    // $bonus['bonusDate']    = date("Y-m-d", strtotime($value['bonusDate']));
                    $bonus['bonusDate']             = $value['bonusDate'] != '0000-00-00' ? date('d/m/Y', strtotime($value['bonusDate'])) : "-";
                    $bonus['username']              = $value['username'];
                    $bonus['name']                  = $value['name'];
                    $bonus['member_id']             = $value['member_id'];
                    $bonus['tradeLimit']            = number_format($value['tradeLimit'],$decimalPlaces,".","");
                    $bonus['tradeSales']            = number_format($value['tradeSales'],$decimalPlaces,".","");
                    $bonus['percentage']            = number_format($value['percentage'],$decimalPlaces,".","");
                    $bonus['bonusAmount']           = number_format($value['amount'],$decimalPlaces,".","");
                    $bonus['packageTypeDisplay']    = $translations[$value['packageNameCode']][$language];
                    $bonus['maintainanceFee']       = number_format($maintainanceFeeAry[$value['product_id']],$decimalPlaces,".","");

                    $totalAmount                    += number_format($value['amount'],$decimalPlaces,".","");

                    $bonusList[] = $bonus;
                }

                if($site=='Member'){

                    $db->where("client_id", $userID);
                    $sumTotalBonus = $db->getValue('mlm_bonus_rebate','sum(amount)');
                    $data['sumTotalBonus'] = number_format($sumTotalBonus,$decimalPlaces,".","");

                }

                if($params['type'] == "export"){
                    $params['command'] = __FUNCTION__;
                    $data = Excel::insertExportData($params);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                $data['bonusList']   = $bonusList;
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                if($seeAll == "1"){
                    $data['totalPage'] = 1;
                    $data['numRecord'] = $totalRecord;
                }else{
                    $data['totalPage'] = ceil($totalRecord/$limit[1]);
                    $data['numRecord'] = $limit[1];
                }

                $data['grandTotal'] = number_format($totalAmount,$decimalPlaces,".","");

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
        }

        public function getWaterBucketBonusReport($params, $userID, $site){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $tableName      = "mlm_bonus_water_bucket";

            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            // Get client data
            $db->where('type','Client');
            $clientData = $db->map('id')->get('client',null,'id,username,name');

            if($site == 'Member'){
               $db->where("client_id", $userID);
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
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                    case 'name':
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

                    case 'fromName':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->get("client", NULL, "id");
                        $db->where("from_id", $sq, "in");
                        break;

                    case 'from_id_username':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("from_id", $sq);

                        break;

                    case 'bonusDate':
                        // Set db column here
                        $columnName = 'bonus_date';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
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

            $copyDb = $db->copy();
            $copySumDb = $db->copy();
            $copySumBonusDb = $db->copy();
            $db->orderBy("bonus_date", "DESC");
            $totalRecord = $copyDb->getValue ($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }

            $result = $db->get($tableName, $limit, "client_id,from_id,from_level,compress_level,percentage,user_percentage,payable_amount,bonus_date,from_amount,batch_id,type,client_id AS clientID");

            if (empty($result)){
            	return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
            }

            $pagingTotal = 0;
            foreach($result as &$value) {
                $value['username'] = $clientData[$value['client_id']]['username'];
                $value['name'] = $clientData[$value['client_id']]['name'];
                $value['from_username'] = $clientData[$value['from_id']]['username'];
                $value['from_name'] = $clientData[$value['from_id']]['name'];

            	$pagingTotal += $value['payable_amount'];
                $bonusList[] = $value;
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList']   = $bonusList;
            $data['page_total_bonus_received'] = number_format($pagingTotal, 8, '.', '');
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }


            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);   
        }
        
        public function getReleaseBonusReport($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $tableName      = "mlm_bonus_release";

            $userID = $db->userID;
            $site = $db->userType;

            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            $column         = array(
                "(SELECT username FROM client where id = client_id) AS username",
                "(SELECT name FROM client where id = client_id) AS name",
                "client_id AS clientID",
                "from_amount as fromAmount",
                "percentage",
                "bonus_date AS bonusDate",
                "bonus_amount AS bonusAmount",
                "payable_amount AS payableAmount",
                "batch_id as batchID",
            );

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
                    case 'name':
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

                    case 'fromName':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->get("client", NULL, "id");
                        $db->where("from_id", $sq, "in");
                        break;

                    case 'from_id_username':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("from_id", $sq);

                        break;

                    case 'bonusDate':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where('bonus_date', date('Y-m-d', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");

                            // if($dateTo == $dateFrom)
                            $db->where('bonus_date', date('Y-m-d', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
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

            $copyDb = $db->copy();
            $copySumDb = $db->copy();
            $copySumBonusDb = $db->copy();
            $db->orderBy("bonus_date", "DESC");
            $totalRecord = $copyDb->getValue ("mlm_bonus_release", "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }

            if($site == 'Member'){
               $db->where("client_id", $userID);
            }

            $result = $db->get("mlm_bonus_release", $limit, $column);

            if (empty($result)){
            	return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
            }
            $pagingTotal = 0;
            foreach($result as &$value) {
                $value["bonusDate"] = date($dateTimeFormat, strtotime($value["bonusDate"]));
            	$pagingTotal += $value['bonus_received'];
                $bonusList[] = $value;
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['pagingTotal'] = $pagingTotal;
            $data['bonusList']   = $bonusList;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }


            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);   
        }

        public function getGlobalPoolShareReport($params,$site,$userID){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['inputData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = 'd/m/Y 00:00:00';//Setting::$systemSetting['systemDateTimeFormat'];

            $usernameSearchType = $params["usernameSearchType"];

            $tableName      = "client_rank";
            $column         = array(
            						"created_at AS createdAt",
					                "(SELECT username FROM client where id = client_id) AS username",
					                "value AS numberOfShare",
					            );

	        if($site == "Member"){
	            if(!$userID) 
	            return array('status' => "error", 'code' => 1, 'statusMsg' => "No Client ID" /* No results found */, 'data' => "");

	            $clientID = $userID;
	            $db->where("id",$clientID);
	            $clientIDCheck = $db->getValue("client","id");
	            if(!$clientIDCheck) 
	            return array('status' => "error", 'code' => 1, 'statusMsg' => "Client does not exist" /* No results found */, 'data' => "");
	        }

			$adminLeaderAry = Setting::getAdminLeaderAry();

	        $copyDb = $db->copy();
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $dbLeader->where('username', $dataValue);
                            $leaderID = $dbLeader->getValue('client', 'id');

                            $dbLeader->where("client_id", $leaderID);
                            $clientTraceKey = $dbLeader->getValue("tree_sponsor", "trace_key");

                            // Find the downline with the trace key
                            $dbLeader->where("trace_key", $clientTraceKey."/%", "LIKE");
                            $dbLeader->orderby("level", "asc");
                            $dbLeader->orderby("id", "asc");
                            $result = $dbLeader->get("tree_sponsor", null, "client_id");
                            foreach ($result as $row)
                            {
                                $downlines[] = $row["client_id"];
                            }

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

                            $db->where('client_id', $downlines, "IN");

                            break;
                        case 'bonusDate':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d 00:00:00', strtotime('-1 day',$dateFrom)), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");

                                if($dateTo == $dateFrom)
                                    $dateTo += 86399;
                                $db->where('created_at', date('Y-m-d 23:59:59', strtotime('-1 day',$dateTo)), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        case 'username':
                            $clientUsernameID = $db->subQuery();
                            if(strtolower($usernameSearchType) == "like"){
                                $clientUsernameID->where('username', "%".$dataValue."%", 'LIKE');
                            }
                            else{
                                $clientUsernameID->where('username', $dataValue);
                            }
                            $clientUsernameID->get('client', null, "id");
                            $db->where("client_id", $clientUsernameID, 'IN');
                            break;
                        case 'name':
                            if($dataType == "like"){
                                $copyDb->where("name", "%".$dataValue."%",'LIKE');
                            }else{
                                $copyDb->where("name",$dataValue);
                            } 
                            $copyDbResult = $copyDb->get("client",NULL,"id");
                            if(empty($copyDbResult)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language], 'data' => '');
                            foreach($copyDbResult AS $value){
                                $clientIDArr[] = $value['id'];
                            }
                            if(!empty($clientIDArr))$db->where("client_id",$clientIDArr,"IN");
                            break;   

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // $copyDb = $db->copy();
            if($site == "Member")$db->where("client_id",$clientID);
			if($adminLeaderAry)$db->where('client_id', $adminLeaderAry, 'IN');
            
            $db->orderBy("created_at", "DESC");
            $db->groupBy("client_id");
            $db->where("name","globlaPoolShare");
            // if($site == "Member"){
            // 	$db->where("client_id",$clientID);
            // }
            // $totalRecord = $copyDb->getValue ($tableName, "count(*)");
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }
            $result = $db->get($tableName, $limit, $column);

            if(!$result) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach($result as $value) {
                $value['createdAt'] = date('d/m/Y 00:00:00',strtotime($value['createdAt']));
                $bonusList[] = $value;
            }

            $data['bonusList']   = $bonusList;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getWaterBucketPercentage($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $tableName      = "client_rank";

            $usernameSearchType = $params["usernameSearchType"];

            $column         = array(
                "client_id",
                "(SELECT username FROM client where id = client_id) AS username",
                "(SELECT name FROM client where id = client_id) AS name",
                "(SELECT member_id FROM client where id = client_id) AS memberID",
                "type",
                "value",
                "IF(updated_at='0000-00-00 00:00:00',created_at, updated_at) created_at",
                "updated_by",
                "(SELECT username FROM admin where id = updated_by) AS adminUsername",
            );
            
            // get water bucket bonus ID.
            $db->where("name", Bonus::BONUS_WATERBUCKET);
            $waterID = $db->getValue("mlm_bonus", "id");

            // $db->where("bonus_id", $waterID);
            $db->where("name", 'waterBucketBonusPercentage');

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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
                            // $sq = $db->subQuery();
                            // $sq->where("username", $dataValue);
                            // $sq->getOne("client", "id");
                            // $db->where("client_id", $sq);
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

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'type':
                            $db->where('type', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("created_at", "DESC");

            $totalRecord = $copyDb->getValue ($tableName, "count(*)");
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 
            $result = $db->get($tableName, $limit, $column);

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            if (!empty($result)){

                $data['waterBucketPercentageListing']   = $result;
                if($seeAll == "1"){
                    $data['totalPage'] = 1;
                    $data['numRecord'] = $totalRecord;
                }
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];


                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
        }

        public function exportExcelBase64($data,$headerArr,$dataKeyArr,$command,$grandTotal){
            include_once 'PHPExcel.php';
            include_once 'PHPExcel/Writer/Excel2007.php';
            // Create new PHPExcel object
            $objPHPExcel = new PHPExcel();

            $objPHPExcel->setActiveSheetIndex(0);
            $excelRow = 0;

            $excelRow += 1;
            $alphaRow = A;

            if($command == "getSalesPurchaseReport"){

                $excelRow += 1;
                $alphaRow = A;

                $objPHPExcel->getActiveSheet()->SetCellValue(C1, "Pre-order scheme");
                $objPHPExcel->getActiveSheet()->SetCellValue(G1, "Retail Scheme");

                foreach ($headerArr as $headerRow) {
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $headerRow);
                }

            } elseif ($command == "getFundInSalesReport") {

                $excelRow += 1;
                $alphaRow = A;

                $objPHPExcel->getActiveSheet()->SetCellValue(D1, "BTC");
                $objPHPExcel->getActiveSheet()->SetCellValue(F1, "ETH");
                $objPHPExcel->getActiveSheet()->SetCellValue(H1, "USDT");

                foreach ($headerArr as $headerRow) {
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $headerRow);
                }
                
            }  elseif ($command == "getBonusListing") {

                $excelRow += 0;
                $alphaRow = A;

                foreach ($grandTotal as $bonusName => $bonusRow) {

                    $alphaRow = A;
                    $excelRow++;
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $bonusRow['bonusName']);
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $bonusRow['totalBonus']);

                }

                $alphaRow = A;

                foreach ($headerArr as $headerRow) {
                    $excelRow = 11;
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $headerRow);
                }
                
            } elseif ($command == "getFundInWithdrawConvertListing") {

                $objPHPExcel->getActiveSheet()->SetCellValue(A1, "Date");
                $objPHPExcel->getActiveSheet()->SetCellValue(B1, "Withdrawal");
                $objPHPExcel->getActiveSheet()->SetCellValue(N1, "Fund In");
                $objPHPExcel->getActiveSheet()->SetCellValue(Z1, "Convert");
                
            } elseif ($command == "bonusPayoutSummary") {

                $objPHPExcel->getActiveSheet()->SetCellValue(E1, "Daily Rebate");
                $objPHPExcel->getActiveSheet()->SetCellValue(G1, "Sponsor Rewards");
                $objPHPExcel->getActiveSheet()->SetCellValue(I1, "Sharing Rewards");
                $objPHPExcel->getActiveSheet()->SetCellValue(K1, "Community Rewards");
                $objPHPExcel->getActiveSheet()->SetCellValue(M1, "Internation Dividend");

                foreach ($headerArr as $headerRow) {
                    $excelRow = 2;
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $headerRow);
                }
                
            }

            else{

                /* header bonus list */
                foreach ($headerArr as $headerRow) {
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $headerRow);
                }

            }

            if ($command == "getBonusListing"){

                foreach ($data as $bonusDate => $bonusArray) {
                    foreach ($bonusArray as $clientID => $dataArray) {
                        $excelRow++;
                        $alphaRow = A;

                        foreach ($dataArray as $key => $dataValue) {
                            if($key == "id") continue;
                            $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $dataValue);
                        }


                    }
                    // unset($bonusArray);
                }

            }elseif ($command == "getFundInWithdrawConvertListing") {
                foreach ($data as $key => $data) {
                    $excelRow++;
                    $alphaRow = A;

                    foreach ($dataKeyArr as $dataKey) {
                        if ($dataKey == 'date'){
                        $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $data[$dataKey]);
                        }

                        if (is_array($data[$dataKey])){
                            foreach ($data[$dataKey] as $key => $blockRight) {
                                $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $key);
                                $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $blockRight);                              
                            }
                        } else if ($data[$dataKey] == "-"){
                            for ($i=0; $i < 12 ; $i++) { 
                                $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, "-");
                            }
                        }
                    }
                    unset($data[$key]);
                }
            }

            elseif ($command == "bonusPayoutSummary") {
                foreach ($data as $key => $value) {
                    $excelRow++;
                    $alphaRow = A;

                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $key);

                        foreach ($value as $value1) {
                            $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $value1); 
                        }
                }

                $excelRow++;
                $objPHPExcel->getActiveSheet()->SetCellValue("A".$excelRow, "Total");

                $alphaRow = B;
                foreach ($grandTotal as $key => $value) {
                    $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $value);
                }
            }

            else {
                foreach ($data as $key => $data) {
                    $excelRow++;
                    $alphaRow = A;

                    foreach ($dataKeyArr as $dataKey) {
                        if(strlen($data[$dataKey]) > 13){
                            if(is_numeric($data[$dataKey]) && $dataKey != 'account_no' && $dataKey != 'walletAddress'){

                                $objPHPExcel->getActiveSheet()->setCellValueExplicit($alphaRow++ . $excelRow, $data[$dataKey], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                            }
                            else{
                                $objPHPExcel->getActiveSheet()->setCellValueExplicit($alphaRow++.$excelRow, $data[$dataKey], PHPExcel_Cell_DataType::TYPE_STRING);
                            }

                        } 
                        else{

                            $objPHPExcel->getActiveSheet()->SetCellValue($alphaRow++.$excelRow, $data[$dataKey]);
                        } 
                    }
                    unset($data[$key]);
                }
            }

            if($command == "getBalanceReport"){
                $excelRow++;
                
                $columnAlphabet = "A";

                foreach ($dataKeyArr as $creditType) {

                    if(strlen($grandTotal[$creditType]) > 13) (float)$objPHPExcel->getActiveSheet()->setCellValueExplicit($columnAlphabet.$excelRow, $grandTotal[$creditType], PHPExcel_Cell_DataType::TYPE_STRING);
                    else (float)$objPHPExcel->getActiveSheet()->SetCellValue($columnAlphabet.$excelRow, $grandTotal[$creditType]);

                    $columnAlphabet++;
                }

                $objPHPExcel->getActiveSheet()->SetCellValue("C".$excelRow, "Total");
            }

            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            ob_start();
            $objWriter->save('php://output');
            $excelOutput = ob_get_clean();
            $rawFile = base64_encode($excelOutput);
            return $rawFile;
        }

        public function getDirectSponsorRewardReport($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');
            }

            $searchData = $params['searchData'];

            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'bonusDate':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('bonus_date',date('Y-m-d',$dateFrom),">=");
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where('bonus_date',date('Y-m-d',$dateTo),"<=");
                        }
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }

            $db->where('client_id',$userID);
            $db->groupBy('bonus_date');
            $db->orderBy('bonus_date');
            $report = $db->get('mlm_bonus_goldmine',null,'bonus_date,SUM(payable_amount) as total_bonus,COUNT(id) as no_referral,MAX(from_level) as daily_entitlement');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['total_bonus'] = Setting::setDecimal($row['total_bonus'],$decimal);
            }

            // Get number of direct sponsor downline
            $db->where('upline_id',$userID);
            $currentNoReferral = $db->getValue('tree_sponsor','COUNT(id)');

            $db->where('client_id',$userID);
            $db->where('name','goldmineBonusPercentage');
            $db->orderBy('ID');
            $currentEntitlement = $db->getValue('client_rank','value');

            $data = array();
            $data['report'] = $report;
            $data['currentNoReferral'] = $currentNoReferral;
            $data['currentEntitlement'] = $currentEntitlement;

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getDirectSponsorRewardDetails($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');
            }

            $bonusDate = $params['bonusDate'];
            if (!$bonusDate) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid bonus date.','data'=>'');
            }

            $db->where('client_id',$userID);
            $db->where('bonus_date',date('Y-m-d',strtotime($bonusDate)));
            $report = $db->get('mlm_bonus_goldmine',null,'bonus_date,from_client_id,from_level,percentage,from_amount,payable_amount as bonus_amount');

            // Get member username
            $db->where('type','Client');
            $usernameArray = $db->map('id')->get('client',null,'id,username');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['from_username'] = $usernameArray[$row['from_client_id']];
                $row['bonus_amount'] = Setting::setDecimal($row['bonus_amount'],$decimal);
            }

            $db->where('client_id',$userID);
            $db->where('bonus_date',date('Y-m-d',strtotime($bonusDate)));
            $summaryData = $db->get('mlm_bonus_goldmine',null,'bonus_date,SUM(payable_amount) as total_bonus,COUNT(id) as no_referral,MAX(from_level) as daily_entitlement');

            $data = array();
            $data['report'] = $report;
            $data['summaryData'] = $summaryData;

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getNodeRewardReport($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');
            }

            $searchData = $params['searchData'];

            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'bonusDate':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('bonus_date',date('Y-m-d',$dateFrom),">=");
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where('bonus_date',date('Y-m-d',$dateTo),"<=");
                        }
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }

            $db->where('client_id',$userID);
            $db->groupBy('bonus_date');
            $db->orderBy('bonus_date');
            $report = $db->get('mlm_bonus_community',null,'bonus_date,SUM(payable_amount) as total_bonus,COUNT(id) as no_community,percentage');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['total_bonus'] = Setting::setDecimal($row['total_bonus'],$decimal);
            }

            $db->where('trace_key',"%$userID%",'LIKE');
            $currentCommunitySize = $db->getValue('tree_sponsor','COUNT(id)');

            $db->where('client_id',$userID);
            $db->where('name','goldmineBonusPercentage');
            $db->orderBy('ID');
            $rankData = $db->getOne('client_rank','value,(SELECT rank.translation_code FROM rank WHERE rank.id = rank_id) as translation_code');

            $data = array();
            $data['report'] = $report;
            $data['currentCommunitySize'] = $currentCommunitySize;
            $data['nodeProgram'] = $translations[$rankData['translation_code']][$language];
            $data['percentage'] = $rankData['value'];

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getNodeRewardDetails($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');
            }

            $bonusDate = $params['bonusDate'];
            if (!$bonusDate) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid bonus date.','data'=>'');
            }

            $db->where('client_id',$userID);
            $db->where('bonus_date',date('Y-m-d',strtotime($bonusDate)));
            $report = $db->get('mlm_bonus_community',null,'bonus_date,from_client_id,from_level,from_amount,percentage,payable_amount as bonus_amount');

            // Get member username
            $db->where('type','Client');
            $usernameArray = $db->map('id')->get('client',null,'id,username');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['from_username'] = $usernameArray[$row['from_client_id']];
                $row['bonus_amount'] = Setting::setDecimal($row['bonus_amount'],$decimal);
            }

            $db->where('client_id',$userID);
            $db->where('bonus_date',date('Y-m-d',strtotime($bonusDate)));
            $summaryData = $db->get('mlm_bonus_community',null,'bonus_date,SUM(payable_amount) as total_bonus,COUNT(id) as no_community,percentage');

            $db->where('client_id',$userID);
            $db->where('name','goldmineBonusPercentage');
            $db->orderBy('ID');
            $rankData = $db->getOne('client_rank','value,(SELECT rank.translation_code FROM rank WHERE rank.id = rank_id) as translation_code');

            $data = array();
            $data['report'] = $report;
            $data['summaryData'] = $summaryData;
            $data['nodeProgram'] = $translations[$rankData['translation_code']][$language];
            $data['percentage'] = $rankData['value'];

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getCloudMiningReport($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');

            $searchData = $params['searchData'];

            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'bonusDate':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('bonus_date',date('Y-m-d',$dateFrom),">=");
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where('bonus_date',date('Y-m-d',$dateTo),"<=");
                        }
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }

            $db->where('client_id',$userID);
            $db->groupBy('bonus_date');
            $db->orderBy('bonus_date');
            $report = $db->get('mlm_bonus_rebate',null,'bonus_date,product_id,percentage,SUM(payable_amount) as total_bonus');

            // Get product name
            $db->where('status','Active');
            $productTranslations = $db->map('id')->get('mlm_product',null,'id,translation_code');

            $db->where('name','minRelease');
            $productSetting = $db->map('product_id')->get('mlm_product_setting',null,'product_id,value');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['machine_level'] = $productTranslations[$row['product_id']]?$translations[$productTranslations[$row['product_id']]][$language]:'-';
                $row['mined_bbit'] = $productSetting[$row['product_id']]?:'-';
                $row['total_bonus'] = Setting::setDecimal($row['total_bonus'],$decimal);
            }

            $db->where('client_id',$userID);
            $db->where('status','Active');
            $portfolioProduct = $db->getValue('mlm_client_portfolio','product_id');

            $db->where('client_id',$userID);
            $db->where('type',array('vol','buy'),'IN');
            $db->where('created_at',date('Y-m-d 00:00:00'));
            $limitData = $db->map('type')->get('trd_client_limit',null,'type,balance');

            $todayTrdLimit = 0;
            if ($limitData['vol'] && $limitData['buy']) {
                $todayTrdLimit = $limitData['vol']/$limitData['buy'];
            }

            $data = array();
            $data['report'] = $report;
            $data['currentMachineLevel'] = $productTranslations[$portfolioProduct]?$translations[$productTranslations[$portfolioProduct]][$language]:'-';
            $data['trdLimit'] = $todayTrdLimit;

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getMiningSponsorBonusReport($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');

            $searchData = $params['searchData'];

            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'bonusDate':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('bonus_date',date('Y-m-d',$dateFrom),">=");
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where('bonus_date',date('Y-m-d',$dateTo),"<=");
                        }
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }

            $db->where('client_id',$userID);
            $db->groupBy('bonus_date');
            $db->orderBy('bonus_date');
            $report = $db->get('mlm_bonus_sponsor',null,'bonus_date,SUM(payable_amount) as total_bonus');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['total_bonus'] = Setting::setDecimal($row['total_bonus'],$decimal);
            }

            $db->where('upline_id',$userID);
            $directSponsorCount = $db->getValue('tree_sponsor','COUNT(id)');

            $db->where('client_id',$userID);
            $db->where('name','sponsorBonusPercentage');
            $db->orderBy('created_at');
            $entitledLevel = $db->getValue('client_rank','value');

            $data = array();
            $data['report'] = $report;
            $data['directSponsorCount'] = $directSponsorCount;
            $data['entitledLevel'] = $entitledLevel;

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getMiningSponsorBonusDetails($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');

            $bonusDate = $params['bonusDate'];
            if (!$bonusDate) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid bonus date.','data'=>'');
            }

            $db->where('client_id',$userID);
            $db->where('bonus_date',date('Y-m-d',strtotime($bonusDate)));
            $dbCopy = $db->copy();
            $report = $db->get('mlm_bonus_sponsor',null,'bonus_date,from_client_id,from_product_id,to_product_id,compress_level as from_level,from_amount,payable_amount as bonus_amount,percentage');

            $totalBonus = $dbCopy->getValue('mlm_bonus_sponsor','SUM(payable_amount)');

            // Get member username
            $db->where('type','Client');
            $usernameArray = $db->map('id')->get('client',null,'id,username');

            $db->where('status','Active');
            $productTranslations = $db->map('id')->get('mlm_product',null,'id,translation_code');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['from_username'] = $usernameArray[$row['from_client_id']];
                $row['bonus_amount'] = Setting::setDecimal($row['bonus_amount'],$decimal);
                $row['from_machine'] = $row['from_product_id'] > 0?$translations[$productTranslations[$row['from_product_id']]][$language]:'-';
                $row['action'] = $row['from_product_id'] > 0?$translations['M02500'][$language]:$translations['M02499'][$language];
                $row['to_machine'] = $row['to_product_id'] > 0?$translations[$productTranslations[$row['to_product_id']]][$language]:'-';

                unset($row['from_product_id']);
                unset($row['to_product_id']);
            }

            $db->where('upline_id',$userID);
            $directSponsorCount = $db->getValue('tree_sponsor','COUNT(id)');

            $db->where('client_id',$userID);
            $db->where('name','sponsorBonusPercentage');
            $db->orderBy('created_at');
            $entitledLevel = $db->getValue('client_rank','value');

            $data = array();
            $data['report'] = $report;
            $data['directSponsorCount'] = $directSponsorCount;
            $data['entitledLevel'] = $entitledLevel;
            $data['totalBonus'] = $totalBonus;
            $data["bonusDate"] = date($dateTimeFormat, strtotime($bonusDate));

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getMiningWaterBucketReport($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');

            $searchData = $params['searchData'];

            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'bonusDate':
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            $db->where('bonus_date',date('Y-m-d',$dateFrom),">=");
                        }
                        if(strlen($dateTo) > 1) {
                            $db->where('bonus_date',date('Y-m-d',$dateTo),"<=");
                        }
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }

            $db->where('client_id',$userID);
            $db->groupBy('bonus_date');
            $db->orderBy('bonus_date');
            $report = $db->get('mlm_bonus_water_bucket',null,'bonus_date,SUM(payable_amount) as total_bonus');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['total_bonus'] = Setting::setDecimal($row['total_bonus'],$decimal);
            }

            $db->where('client_id',$userID);
            $traceKey = $db->getValue('tree_sponsor','trace_key');

            $db->where('trace_key',"$traceKey/%",'LIKE');
            $sponsorCount = $db->getValue('tree_sponsor','COUNT(id)');

            $db->where('client_id',$userID);
            $db->where('name','waterBucketBonusPercentage');
            $db->orderBy('created_at');
            $waterBucketPercentage = $db->getValue('client_rank','value');

            $data = array();
            $data['report'] = $report;
            $data['sponsorCount'] = $sponsorCount;
            $data['waterBucketPercentage'] = $waterBucketPercentage?:'-';

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getMiningWaterBucketDetails($params,$userID) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimal        = Setting::getSystemDecimalPlaces();

            $db->where('id',$userID);
            $isValidUser = $db->getValue('client','COUNT(id)');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>'');

            $bonusDate = $params['bonusDate'];
            if (!$bonusDate) {
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid bonus date.','data'=>'');
            }

            $db->where('client_id',$userID);
            $db->where('bonus_date',date('Y-m-d',strtotime($bonusDate)));
            $db->where('paid','1');
            $dbCopy = $db->copy();
            $report = $db->get('mlm_bonus_water_bucket',null,'bonus_date,compress_level as from_level,payable_amount as bonus_amount,from_amount,from_product_id,to_product_id,percentage,from_id');

            $totalBonus = $dbCopy->getValue('mlm_bonus_water_bucket','SUM(payable_amount)');

            // Get member username
            $db->where('type','Client');
            $usernameArray = $db->map('id')->get('client',null,'id,username');

            $db->where('status','Active');
            $productTranslations = $db->map('id')->get('mlm_product',null,'id,translation_code');

            foreach ($report as &$row) {
                $row["bonus_date"] = date($dateTimeFormat, strtotime($row["bonus_date"]));
                $row['from_username'] = $usernameArray[$row['from_id']];
                $row['bonus_amount'] = Setting::setDecimal($row['bonus_amount'],$decimal);
                $row['from_machine'] = $row['from_product_id'] > 0?$translations[$productTranslations[$row['from_product_id']]][$language]:'-';
                $row['action'] = $row['from_product_id'] > 0?$translations['M02500'][$language]:$translations['M02499'][$language];
                $row['to_machine'] = $row['to_product_id'] > 0?$translations[$productTranslations[$row['to_product_id']]][$language]:'-';

                unset($row['from_product_id']);
                unset($row['to_product_id']);
            }

            $db->where('client_id',$userID);
            $traceKey = $db->getValue('tree_sponsor','trace_key');

            $db->where('trace_key',"$traceKey/%",'LIKE');
            $sponsorCount = $db->getValue('tree_sponsor','COUNT(id)');

            $db->where('client_id',$userID);
            $db->where('name','waterBucketBonusPercentage');
            $db->orderBy('created_at');
            $waterBucketPercentage = $db->getValue('client_rank','value');

            $data = array();
            $data['report'] = $report;
            $data['sponsorCount'] = $sponsorCount;
            $data['waterBucketPercentage'] = $waterBucketPercentage?:'-';
            $data['totalBonus'] = $totalBonus;
            $data["bonusDate"] = date($dateTimeFormat, strtotime($bonusDate));

            if (count($report) <= 0)
                return array('status'=>'ok','code'=>'0','statusMsg'=>$translations['B00101'][$language],'data'=>$data); // No Result Found

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }

        public function getBonanzaBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'];
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $usernameSearchType = $params["usernameSearchType"];

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
                        case 'name':
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
                            if ($usernameSearchType == 'match') {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            } else if ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN'); 
                            }

                            break;

                        case 'bonusType':
                            $db->where('bonus_type', $dataValue);
                            break;

                        case 'refNo':
                            $sq = $db->subQuery();
                            $sq->where('reference_no', $dataValue);
                            $sq->getOne('mlm_client_portfolio', 'client_id');
                            $db->where('client_id', $sq);

                            break;

                        case 'bonusDate':
                            $columnName = 'date(bonus_date)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d',$dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d',$dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'fromPackage':
                            $db->where('product_id', $dataValue);
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

            if($site == 'Member'){
                $db->where('mlm_bonus_bonanza.client_id', $userID);
            }

            if($adminLeaderAry) $db->where('mlm_bonus_bonanza.client_id', $adminLeaderAry, 'IN');

            $db->orderBy('created_at', 'DESC');
           
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue('mlm_bonus_bonanza', "count(*)");

            if($seeAll == '1') {
                $limit = array(0, $totalRecord);
            }

            $result = $db->get('mlm_bonus_bonanza', $limit, 'client_id, product_id, bonus_date, percentage, payable_amount as amount, batch_id');

            if (!empty($result)) {
                foreach($result as $value) {
                    $clientIDAry[$value['client_id']] = $value['client_id'];
                    $productIDAry[$value['product_id']] = $value['product_id'];
                }

                if($clientIDAry) {
                    $db->where('id', $clientIDAry, 'IN');
                    $clientUsernameAry = $db->map('id')->get('client', null, 'id, username');
                }

                if($productIDAry) {
                    $db->where('id', $productIDAry, 'IN');
                    $productNameAry = $db->map('id')->get('mlm_product', null, 'id, translation_code');
                }

                foreach($result as $value) {
                    // $bonus['bonusDate'] = $value['bonus_date'] != '0000-00-00' ? date('d/m/Y', strtotime($value['bonus_date'])) : "-";
                    $bonus['bonusDate'] = $value['bonus_date'] != '0000-00-00' ? date($dateTimeFormat, strtotime($value['bonus_date'])) : "-";

                    $bonus['username'] = $clientUsernameAry[$value['client_id']];
                    $bonus['packageDisplay'] = $translations[$productNameAry[$value['product_id']]][$language];
                    // $bonus['percentage'] = number_format($value['percentage'], $decimalPlaces, ".", "");
                    $bonus['amount'] = number_format($value['amount'], $decimalPlaces, ".", "");

                    $totalAmount += number_format($value['amount'],$decimalPlaces,".","");

                    $bonusList[] = $bonus;
                }

                if($site == 'Member'){
                    $db->where('client_id', $userID);
                    $sumTotalBonus = $db->getValue('mlm_bonus_bonanza','sum(amount)');
                    $data['sumTotalBonus'] = number_format($sumTotalBonus, $decimalPlaces, ".", "");
                }

                if($params['type'] == "export"){
                    $params['command'] = __FUNCTION__;
                    $data = Excel::insertExportData($params);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                $data['bonusList'] = $bonusList;
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                if($seeAll == "1"){
                    $data['totalPage'] = 1;
                    $data['numRecord'] = $totalRecord;
                }else{
                    $data['totalPage'] = ceil($totalRecord/$limit[1]);
                    $data['numRecord'] = $limit[1];
                }

                $data['grandTotal'] = number_format($totalAmount, $decimalPlaces, ".", "");

                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language] /* No Results Found */, 'data' => "");
        }

        public function getJackpotBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'];
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];
            // $dateFormat = Setting::$systemSetting['systemDateFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            // manual set date setting for this report
            $dateFormat = 'd/m/Y h:i';

            $adminLeaderAry = Setting::getAdminLeaderAry();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName   = trim($v['dataName']);
                    $dataValue  = trim($v['dataValue']);
                    $dataType   = trim($v['dataType']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

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
                    $dataType   = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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
                            if ($dataType == 'like') {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN'); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            }
                            break;

                        case 'bonusDate':
                            $columnName = 'date(bonus_date)';
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d',$dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d',$dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where('client_id', $userID);
            }

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            $db->orderBy('created_at', 'DESC');
           
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue('mlm_bonus_jackpot', "count(*)");

            if($seeAll == '1') {
                $limit = array(0, $totalRecord);
            }

            $results = $db->get('mlm_bonus_jackpot', $limit, 'client_id, win_count, bonus_date, from_amount, percentage, actual_percentage, payable_amount, total_shares, created_at, game_id');

            if (empty($results)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language] /* No Results Found */, 'data' => "");
            }

            foreach($results as $result) {
                $clientIDAry[$result['client_id']] = $result['client_id'];
                $gameIDAry[$result['game_id']] = $result['game_id'];
            }

            if($gameIDAry){
                $db->where('id', $gameIDAry, 'IN');
                $gameDataAry = $db->map('id')->get('game', NULL, 'id, product_id');

                if($gameDataAry){
                    $db->where('id', array_values($gameDataAry), 'IN');
                    $productDataAry = $db->map('id')->get('mlm_product', NULL, 'id, translation_code');
                }
            }

            if($clientIDAry) {
                $db->where('id', $clientIDAry, 'IN');
                $clientDataAry = $db->map('id')->get('client', null, 'id, username, member_id');
            }

            foreach($results as &$result) {
                $result['bonus_date'] = $result['bonus_date'] != '0000-00-00' ? date($dateFormat, strtotime($result['bonus_date'])) : "-";
                $result['username'] = $clientDataAry[$result['client_id']]['username'];
                $result['member_id'] = $clientDataAry[$result['client_id']]['member_id'] ?: "-";
                $result['from_amount'] = number_format($result['from_amount'], $decimalPlaces, ".", "");
                $result['payable_amount'] = number_format($result['payable_amount'], $decimalPlaces, ".", "");
                $result['created_at'] = $result['created_at'] != '0000-00-00' ? date($dateTimeFormat, strtotime($result['created_at'])) : "-";
                $packageTranslate = $translations[$productDataAry[$gameDataAry[$result['game_id']]]][$language];
                $result['package_name_display'] = $packageTranslate ? $packageTranslate : '-';

                $totalAmount += number_format($result['payable_amount'],$decimalPlaces,".","");
                unset($result['client_id']);
                unset($result['game_id']);
            }

            if($site == 'Member'){
                $db->where('client_id', $userID);
                $sumTotalBonus = $db->getValue('mlm_bonus_jackpot','sum(payable_amount)');
                $data['sumTotalBonus'] = number_format($sumTotalBonus, $decimalPlaces, ".", "");
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $results;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            $data['grandTotal'] = number_format($totalAmount, $decimalPlaces, ".", "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getKSponsorBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'];
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];
            $dateFormat = Setting::$systemSetting['systemDateFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $adminLeaderAry = Setting::getAdminLeaderAry();

            //This action only perfrom for member
            if($site == "Member"){

                $leaderBoardSettingDate = Setting::$systemSetting['resetKingPool'];

                //perform operation if user is a member
                if(strtotime($leaderBoardSettingDate) == strtotime(date("Y-m-d"))){
                    //get the bonus date from `mlm_bonus_calculation_batch`
                    $db->where("bonus_name","kSponsorBonus");
                    $db->orderby("bonus_date","DESC");
                    $bonusDate = $db->getValue("mlm_bonus_calculation_batch","bonus_date");
                    //remove the time from the datetime
                    //get the latest reslt from `mlm_bonus_ksponsor`
                    $db->where("bonus_date",date("Y-m-d", strtotime($bonusDate)));
                    // $db->where("bonus_date",$bonusDate->format('Y-m-d'));
                    $db->orderby("rank","ASC");
                    $resultSet = $db->get("mlm_bonus_ksponsor",12,"client_id,total_point");
               }else{
                    //get from mlm_ksponsor_point table
                    $db->where("is_flush","0");
                    $db->groupBy("client_id");
                    $db->orderby("SUM(point)","DESC");
                    $db->orderBy('MAX(created_at)','ASC');
                    $resultSet = $db->get("mlm_ksponsor_point",12,"client_id, SUM(point) AS total_point");
                }

                //create a indexes array for client id
                foreach ($resultSet as $result) {
                    $clientArr[$result['client_id']] = $result['client_id'];
                }
                //map the indexes(id) to username
                if($clientArr){
                    $db->where("id", $clientArr, "IN");
                    $clientList = $db->map("id")->get("client", null, "id, username");

                    $db->where('client_id',$clientArr,"IN");
                    $fakeUserArr = $db->map('client_id')->get('mlm_ksponsor_point',null,'client_id,username');
                }

                //assign value to a new array
                foreach ($resultSet as $result) {
                    $resultList['client_username'] = $clientList[$result['client_id']]?$clientList[$result['client_id']]:$fakeUserArr[$result['client_id']];
                    $resultList['client_username'] = General::getHashUsername($resultList['client_username']);
                    $resultList['total_point'] = Setting::setDecimal($result['total_point']);
                    $resultDataList[] = $resultList;
                }

                $db->where('client_id',$userID);
                $db->where("is_flush","0");
                $ownPoint = $db->getValue("mlm_ksponsor_point","SUM(point)");

                $data['leaderboard'] = $resultDataList; 
                $data['ownPoint']    = Setting::setDecimal($ownPoint);
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName   = trim($v['dataName']);
                    $dataValue  = trim($v['dataValue']);
                    $dataType   = trim($v['dataType']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

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
                    $dataType   = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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
                            if ($dataType == 'like') {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN'); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            }
                            break;

                        case 'bonusDate':
                            $columnName = 'date(bonus_date)';
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d',$dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d',$dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where('client_id', $userID);
            }

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            $db->orderBy('created_at', 'DESC');
           
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue('mlm_bonus_ksponsor', "count(*)");

            if($seeAll == '1') {
                $limit = array(0, $totalRecord);
            }

            $results = $db->get('mlm_bonus_ksponsor', $limit, 'client_id, bonus_date, rank, total_point, from_amount, percentage, payable_amount, created_at');

            if (empty($results)) {
                if(!$data) $data = "";
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language] /* No Results Found */, 'data' => $data);
            }

            foreach($results as $result) {
                $clientIDAry[$result['client_id']] = $result['client_id'];
            }

            if($clientIDAry) {
                $db->where('id', $clientIDAry, 'IN');
                $clientDataAry = $db->map('id')->get('client', null, 'id, username, member_id');

                $db->where('client_id',$clientIDAry,"IN");
                $fakeUserArr = $db->map('client_id')->get('mlm_ksponsor_point',null,'client_id,username');
            }

            foreach($results as &$result) {
                $result['bonus_date'] = $result['bonus_date'] != '0000-00-00' ? date($dateFormat, strtotime($result['bonus_date'])) : "-";
                $result['username'] = $clientDataAry[$result['client_id']]['username']?$clientDataAry[$result['client_id']]['username']:$fakeUserArr[$result['client_id']];
                $result['member_id'] = $clientDataAry[$result['client_id']]['member_id'] ?: "-";
                $result['from_amount'] = number_format($result['from_amount'], $decimalPlaces, ".", "");
                $result['payable_amount'] = number_format($result['payable_amount'], $decimalPlaces, ".", "");
                $result['created_at'] = $result['created_at'] != '0000-00-00' ? date($dateTimeFormat, strtotime($result['created_at'])) : "-";

                $totalAmount += number_format($result['payable_amount'],$decimalPlaces,".","");
                unset($result['client_id']);
            }

            if($site == 'Member'){
                $db->where('client_id', $userID);
                $sumTotalBonus = $db->getValue('mlm_bonus_ksponsor','sum(payable_amount)');
                $data['sumTotalBonus'] = number_format($sumTotalBonus, $decimalPlaces, ".", "");
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $results;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            $data['grandTotal'] = number_format($totalAmount, $decimalPlaces, ".", "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getLeadershipBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'];
            $limit = General::getLimit($pageNumber);

            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateFormat     = Setting::$systemSetting['systemDateFormat'];

            $site = $db->userType;
            $userID = $db->userID;

            $usernameSearchType = $params["usernameSearchType"];

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

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            break;

                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
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
                        case 'fullname':
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
                            if ($dataType == 'match') {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            } else if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN'); 
                            }

                            break;

                        case 'email':
                            $sq = $db->subQuery();
                            $sq->where('email', $dataValue);
                            $sq->get('client', NULL, 'id');
                            $db->where('client_id', $sq, 'IN');
                            break;

                        case 'bonusDate':
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d',$dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d',$dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'fromUsername':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_id", $sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_id", $sq);
                            break;

                        case 'fromEmail':
                            $sq = $db->subQuery();
                            $sq->where('email', $dataValue);
                            $sq->get('client', NULL, 'id');
                            $db->where('from_id', $sq, 'IN');
                            break;

                        case 'rankID':
                            $db->where("rank_id", $dataValue);
                            break;

                        case 'generation':
                            $db->where("compress_level", $dataValue);
                            break;

                        case 'fromRankID':
                            $db->where('from_rank_id',$dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where('mlm_bonus_leadership.client_id', $userID);
            }

            if($adminLeaderAry) $db->where('mlm_bonus_leadership.client_id', $adminLeaderAry, 'IN');
            if($mainDownlines) $db->where('client_id', $mainDownlines, "IN");
            if($downlines) $db->where('client_id', $downlines, "IN");

            $db->orderBy('bonus_date', 'DESC');
            $db->orderBy('id', 'DESC');
           
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue('mlm_bonus_leadership', "count(*)");

            if($seeAll == '1') {
                $limit = array(0, $totalRecord);
            }

            if($params['type'] == "export"){
                $limit = null;
            }

            $result = $db->get('mlm_bonus_leadership', $limit, 'client_id, rank_id, from_amount, percentage, amount, payable_amount, bonus_date, from_id, from_level, compress_level, unit_price, from_rank_id');
            if(empty($result)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language] /* No Results Found */, 'data' => "");
            }
            
            foreach($result as $value) {
                $clientIDAry[$value['client_id']] = $value['client_id'];
                $clientIDAry[$value['from_id']] = $value['from_id'];
                $rankIDAry[$value['rank_id']] = $value['rank_id'];
                $rankIDAry[$value['from_rank_id']] = $value['from_rank_id'];
                $bonusDateAry[$value['bonus_date']] = $value['bonus_date'];
            }

            if($clientIDAry) {
                $db->where('id', $clientIDAry, 'IN');
                $clientUsernameAry = $db->map('id')->get('client', null, 'id, username, member_id, name, email, country_id');

                $db->groupBy('client_id');
                $db->where('client_id', $clientIDAry, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            if($rankIDAry) {
                $db->where('id', $rankIDAry, 'IN');
                $rankLangArr = $db->map('id')->get('rank', null, 'id, translation_code');
            }

            if($rankIDAry) {
            $db->where('bonus_date', $bonusDateAry, 'IN');
                $rpDetails = $db->get('bonus_payout_summary', null, 'bonus_date, country_id, cv_rate');

                foreach($rpDetails as $rp){
                    $mulRate[$rp['bonus_date']][$rp['country_id']] = $rp['cv_rate'];
                }
            }


            foreach($result as $value) {
                $clientData                 = $clientUsernameAry[$value['client_id']];
                $cvRate                     = $mulRate[$value['bonus_date']][$clientData['country_id']];
                $bonus['bonusDate']         = $value['bonus_date'] != '0000-00-00' ? date($dateFormat, strtotime($value['bonus_date'])) : "-";

                $bonus['username']          = $clientUsernameAry[$value['client_id']]['username'];
                $bonus['memberID']          = $clientUsernameAry[$value['client_id']]['member_id'];
                $bonus['fullname']          = $clientUsernameAry[$value['client_id']]['name'];
                $bonus['cityName']          = $getCityName[$getCityID[$value['client_id']]]?:'-';
                $bonus['email']             = $clientUsernameAry[$value['client_id']]['email'];
                $bonus['fromUsername']      = $clientUsernameAry[$value['from_id']]['username'];
                $bonus['fromMemberID']      = $clientUsernameAry[$value['from_id']]['member_id'];
                $bonus['fromFullname']      = $clientUsernameAry[$value['from_id']]['name'];
                $bonus['fromCityName']      = $getCityName[$getCityID[$value['from_id']]]?:'-';
                $bonus['fromEmail']         = $clientUsernameAry[$value['from_id']]['email'];
                $bonus['fromLevel']         = $value['compress_level'];
                $bonus['fromRankDisplay']   = $value['from_rank_id'] ? $translations[$rankLangArr[$value['from_rank_id']]][$language] : "-";

                $bonus['rankDisplay']       = $translations[$rankLangArr[$value['rank_id']]][$language];
                
                $bonus['fromAmount']        = Setting::setDecimal($value['from_amount'], $decimalPlaces);
                $bonus["unitPrice"]         = Setting::setDecimal($value["unit_price"],$decimalPlaces);
                $bonus['percentage']        = Setting::setDecimal($value['percentage'], $decimalPlaces);
                $bonus['payableAmount']     = Setting::setDecimal($value['payable_amount'], $decimalPlaces);
                $bonus['cv_rate']           = Setting::setDecimal($cvRate);
                $bonus['payableAmountRP']   = Setting::setDecimal($bonus['payableAmount'] * $cvRate);

                $totalAmount += $value['payable_amount'];

                $bonusList[] = $bonus;
            }

            if($site == 'Member'){
                $db->where('client_id', $userID);
                $sumTotalBonus = $db->getValue('mlm_bonus_leadership','sum(payable_amount)');
                $data['sumTotalBonus'] = number_format($sumTotalBonus, $decimalPlaces, ".", "");
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $bonusList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            $data['grandTotal'] = Setting::setDecimal($totalAmount, $decimalPlaces);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getBonusAmountListing($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $userID = $db->userID;

            $db->where('client_id', $userID);
            $db->groupBy('bonus_date');
            $db->groupBy('bonus_type');
            $bonusDate = $db->get('mlm_bonus_report', null, 'client_id, country_id, bonus_date, paid, bonus_type, SUM(bonus_amount) as bonus_amount');

            foreach($bonusDate as $bonus){
                $bonusResult[$bonus['bonus_date']]['client_id'] = $bonus['client_id'];
                $bonusResult[$bonus['bonus_date']][$bonus['bonus_type']] = $bonus['bonus_amount'];
                $bonusResult[$bonus['bonus_date']]['country_id'] = $bonus['country_id'];
                $bonusResult[$bonus['bonus_date']]['paid'] = $bonus['paid'];
                $bonusValueDate[] = $bonus['bonus_date'];
                $countryID = $bonus['country_id'];
            }

            $db->where('id',$countryID);
            $currencyCode = $db->getValue('country','currency_code');

            unset($header);
            $header[] = $translations['B00428'][$language]/*Bonus Date*/;
            $header[] = $translations['B00439'][$language]/*Member ID*/;
            $header[] = $translations['B00440'][$language]/*Email*/;
            $header[] = $translations['B00441'][$language]/*Name*/;
            $header[] = (!empty($translations['B00442'][$language]))?$translations['B00442'][$language]:'-';/*Bank Name*/;
            $header[] = (!empty($translations['B00443'][$language]))?$translations['B00443'][$language]:'-';/*Bank Account No*/;
            $header[] = $translations['B00444'][$language]/*Rank*/;
            $header[] = $translations['B00445'][$language]/*Goldmine CV*/;
            $header[] = $translations['B00446'][$language]." ($currencyCode)"/*Goldmine Bonus*/;
            $header[] = $translations['B00447'][$language]/*Team Bonus CV*/;
            $header[] = $translations['B00448'][$language]." ($currencyCode)"/*Team Bonus*/;
            $header[] = $translations['B00449'][$language]/*Leadership Bonus CV*/;
            $header[] = $translations['B00450'][$language]." ($currencyCode)"/*Leadership Bonus*/;
            $header[] = $translations['B00451'][$language]." ($currencyCode)"/*Cash Award Bonus*/;
            $header[] = $translations['B00504'][$language]/* Recruit & Active Programme (IDR) */;
            $header[] = $translations['B00452'][$language]." ($currencyCode)"/*Total Bonus Amount*/;
            $data['header'] = $header;

            if(empty($bonusDate)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $db->where('id', $userID);
            $clientDetails = $db->map('id')->getOne('client', 'id, member_id, name, email');

            $db->where('client_id', $userID);
            $db->where('status', 'Active');
            $clientBankDetails = $db->map('client_id')->getOne('mlm_client_bank', 'client_id, bank_id, account_no');

            $db->where('status', 'Active');
            $bankName = $db->map('id')->get('mlm_bank', null, 'id, translation_code');

            $db->where('bonus_date', $bonusValueDate, 'IN');
            $rpDetails = $db->get('bonus_payout_summary', null, 'bonus_date, country_id, cv_rate');

            foreach($rpDetails as $rp){
                $mulRate[$rp['bonus_date']][$rp['country_id']] = $rp['cv_rate'];
            }

            $rank = $db->map('id')->get('rank', null,'id, translation_code'); 

            foreach($bonusResult as $key=>$value){
                $clientRankArr = Bonus::getClientRank("Bonus Tier", array($userID), date('Y-m-d 23:59:59', strtotime($key)), "goldmineBonus", "System");
                $rankName = empty($clientRankArr)?'-':$translations[$rank[$clientRankArr[$value['client_id']]['rank_id']]][$language];
                $bankNaming = $bankName[$clientBankDetails[$value['client_id']]['bank_id']];
                $statusNaming = $value['paid'] ? 'B00426' /* Paid */:'B00427' /* Unpaid */;
                
                $bonusDetails['bonusDate']              = $key;
                $bonusDetails['memberID']               = $clientDetails[$value['client_id']]['member_id'];
                $bonusDetails['email']                  = $clientDetails[$value['client_id']]['email'];
                $bonusDetails['status']                 = $translations[$statusNaming][$language];
                $bonusDetails['name']                   = $clientDetails[$value['client_id']]['name'];
                $bonusDetails['bankName']               = (!empty($translations[$bankNaming][$language]))?$translations[$bankNaming][$language]:'-';
                $bonusDetails['bankAccNo']              = (!empty($clientBankDetails[$value['client_id']]['account_no']))?$clientBankDetails[$value['client_id']]['account_no']:'-';
                $bonusDetails['rank']                   = $rankName;
                $bonusDetails['goldmineCV']             = $value['goldmineBonus']?:'0';
                $bonusDetails['goldmineBonusRP']        = Setting::setDecimal($value['goldmineBonus']*$mulRate[$key][$value['country_id']])?:'0';
                $bonusDetails['teamBonusCV']            = $value['teamBonus']?:'0';
                $bonusDetails['teamBonusRP']            = Setting::setDecimal($value['teamBonus']*$mulRate[$key][$value['country_id']])?:'0';
                $bonusDetails['leadershipBonusCV']      = $value['leadershipBonus']?:'0';
                $bonusDetails['leadershipBonusRP']      = Setting::setDecimal($value['leadershipBonus']*$mulRate[$key][$value['country_id']])?:'0';
                $bonusDetails['cashAwardBonusRP']       = $value['awardBonus']?:'0';
                $bonusDetails['recruitPromo']           = Setting::setDecimal($value['recruitPromo'])?:'0';
                $bonusDetails['totalBonusAmountRP']     = Setting::setDecimal($bonusDetails['goldmineBonusRP']+$bonusDetails['teamBonusRP']+$bonusDetails['leadershipBonusRP']+$bonusDetails['cashAwardBonusRP']+$bonusDetails['recruitPromo']);

                $bonusDetailsAry[] = $bonusDetails;
            }

            $data['bonusDetails'] = $bonusDetailsAry;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getBonusPayoutDetailListing($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            $bonusID = trim($params['bonusPayoutSummaryID']);
            $type = $params['type'];

            if(!$bonusID) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
            }

            $db->where('id', $bonusID);
            $getBonusDateNCountry = $db->getOne('bonus_payout_summary', 'bonus_date, country_id, cv_rate');

            if(empty($getBonusDateNCountry)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':
                            $db->where('username', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            $downlinesUser = Tree::getSponsorTreeDownlines($leaderID);

                            if (empty($downlinesUser))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            break;

                        case 'leaderMemberID':
                            $db->where('member_id', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            $downlinesID = Tree::getSponsorTreeDownlines($leaderID);

                            if (empty($downlinesID))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            break;

                        case 'leaderEmail':
                            $db->where('email', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            $downlinesEmail = Tree::getSponsorTreeDownlines($leaderID);

                            if (empty($downlinesEmail))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            break;

                        case "mainLeaderUsername":
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
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
                        case 'username':
                            if($dataType == 'like'){
                                $sq = $db->subQuery();
                                $sq->where('username', '%'.$dataValue.'%');
                                $sq->get('client', null, 'id');
                                $db->where('client_id', $sq, 'IN');    
                            }else if($dataType == 'match'){
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->get('client', null, 'id');
                                $db->where('client_id', $sq); 
                            }
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id', $dataValue);
                            $sq->get('client', null, 'id');
                            $db->where('client_id', $sq, 'IN');
                            break;
                        
                        case 'email':
                            $sq = $db->subQuery();
                            $sq->where('email', $dataValue);
                            $sq->get('client', null, 'id');
                            $db->where('client_id', $sq, 'IN');
                            break; 

                        case 'status':
                            $db->where('paid', $dataValue);
                            break;

                        case 'bankAccNo':
                            $sq = $db->subQuery();
                            $sq->where('account_no', $dataValue);
                            $sq->get('mlm_client_bank', null, 'client_id');
                            $db->where('client_id', $sq, 'IN');
                            break;

                        case 'rank':
                            $rankFilter = $db->subQuery();
                            $rankFilter->where('DATE(created_at)',$getBonusDateNCountry['bonus_date']);
                            $rankFilter->where('rank_id', $dataValue);
                            $rankFilter->get('client_rank_monthly', null, 'client_id');
                            $db->where('client_id', $rankFilter, 'IN');
                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                    
                                $db->where('bonus_date', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                    
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }

                                $db->where('bonus_date', date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($downlinesUser)  $db->where('client_id', $downlinesUser, 'IN');
            if($downlinesID)    $db->where('client_id', $downlinesID, 'IN');
            if($downlinesEmail) $db->where('client_id', $downlinesEmail, 'IN');
            $db->where('country_id', $getBonusDateNCountry['country_id']);
            $db->where('bonus_date', $getBonusDateNCountry['bonus_date']);
            $db->groupBy('client_id');
            $copyDb = $db->copy();
            $checkRecord = $db->get('mlm_bonus_report', $limit, 'client_id');

            $totalRecord = count($checkRecord);

            $db->where('id',$getBonusDateNCountry['country_id']);
            $countryData = $db->getOne('country','currency_code,name');
            $currencyCode = $countryData['currency_code'];
            $countryName = $countryData['name'];

            unset($header);
            $header[] = $translations['B00428'][$language]/*Bonus Date*/;
            $header[] = $translations['B00439'][$language]/*Member ID*/;
            $header[] = $translations['B00440'][$language]/*Email*/;
            $header[] = $translations['B00441'][$language]/*Name*/;
            $header[] = $translations['B00506'][$language]/*City*/;
            $header[] = $translations['B00442'][$language]/*Bank Name*/;
            $header[] = $translations['B00443'][$language]/*Bank Account No*/;
            $header[] = $translations['B00444'][$language]/*Rank*/;
            $header[] = $translations['B00445'][$language]/*Goldmine CV*/;
            $header[] = $translations['B00446'][$language]." ($currencyCode)"/*Goldmine Bonus*/;
            $header[] = $translations['B00447'][$language]/*Team Bonus CV*/;
            $header[] = $translations['B00448'][$language]." ($currencyCode)"/*Team Bonus*/;
            $header[] = $translations['B00449'][$language]/*Leadership Bonus CV*/;
            $header[] = $translations['B00450'][$language]." ($currencyCode)"/*Leadership Bonus*/;
            $header[] = $translations['B00451'][$language]." ($currencyCode)"/*Cash Award Bonus*/;
            $header[] = $translations['B00504'][$language]/* Recruit & Active Programme (IDR) */;
            $header[] = $translations['B00452'][$language]." ($currencyCode)"/*Total Bonus Amount*/;
            $data['header'] = $header;

            if(empty($checkRecord)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);
            }

            foreach($checkRecord as $getID){
                $clientIDAry[] = $getID['client_id'];
            }

            $db->where('client_id',$clientIDAry,'IN');
            $db->where('country_id', $getBonusDateNCountry['country_id']);
            $db->where('bonus_date', $getBonusDateNCountry['bonus_date']);
            $db->groupBy('client_id');
            $db->groupBy('bonus_type');
            $getBonusDetails = $db->get('mlm_bonus_report', null, 'id, client_id, country_id, bonus_date, bonus_type, SUM(bonus_amount) as bonus_amount, tax_percentage, paid');

            foreach($getBonusDetails as $bonus){
                $bonusResult[$bonus['client_id']]['client_id'] = $bonus['client_id'];
                $bonusResult[$bonus['client_id']]['paid'] = $bonus['paid'];
                $bonusResult[$bonus['client_id']]['taxPercentage'] = $bonus['tax_percentage'];
                $bonusResult[$bonus['client_id']][$bonus['bonus_type']] = $bonus['bonus_amount'];
                $bonusResult[$bonus['client_id']]['bonusID'][] = $bonus['id'];
            }

            $db->where('id', $clientIDAry, 'IN');
            $getMemberDetail = $db->map('id')->get('client', null,'id, member_id, name, email');

            $db->where('client_id', $clientIDAry, 'IN');
            $db->where('status', 'Active');
            $clientBankDetails = $db->map('client_id')->get('mlm_client_bank', null, 'client_id, bank_id, account_no');

            $db->where('status', 'Active');
            $bankName = $db->map('id')->get('mlm_bank', null, 'id, translation_code');

            $clientRankArr = Bonus::getClientRank("Bonus Tier", $clientIDAry, date('Y-m-d 23:59:59', strtotime($getBonusDateNCountry['bonus_date'])), "goldmineBonus", "System");

            $rank = $db->map('id')->get('rank', null,'id, translation_code'); 

            if($clientIDAry){
                $db->groupBy('client_id');
                $db->where('client_id', $clientIDAry, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }

                $db->where('client_id',$clientIDAry,"IN");
                $db->where('doc_type','NPWP Verification');
                $db->where('status','Approved');
                $validClientIDArr = $db->map('client_id')->get('mlm_kyc',null,'client_id');
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            foreach($bonusResult as $getDetails){
                $bankNaming = $bankName[$clientBankDetails[$getDetails['client_id']]['bank_id']];
                $rankName   = empty($clientRankArr[$getDetails['client_id']])?'-':$translations[$rank[$clientRankArr[$getDetails['client_id']]['rank_id']]][$language];
                $status     = $getDetails['paid'] ? 'paid' : 'unpaid';

                $bonusPayout['bonusDate']           = $getBonusDateNCountry['bonus_date'];
                $bonusPayout['memberID']            = $getMemberDetail[$getDetails['client_id']]['member_id'];
                $bonusPayout['email']               = $getMemberDetail[$getDetails['client_id']]['email'];
                $bonusPayout['name']                = $getMemberDetail[$getDetails['client_id']]['name'];
                $bonusPayout['cityName']            = $getCityName[$getCityID[$getDetails['client_id']]]?:'-';
                $bonusPayout['bankName']            = (!empty($translations[$bankNaming][$language]))?$translations[$bankNaming][$language]:'-';
                $bonusPayout['bankAccNo']           = (!empty($clientBankDetails[$getDetails['client_id']]['account_no']))?$clientBankDetails[$getDetails['client_id']]['account_no']:'-';
                $bonusPayout['rank']                = $rankName;
                $bonusPayout['goldmineCV']          = $bonusResult[$getDetails['client_id']]['goldmineBonus']?:'0';
                $bonusPayout['goldmineBonusRP']     = Setting::setDecimal($bonusResult[$getDetails['client_id']]['goldmineBonus']*$getBonusDateNCountry['cv_rate'])?:'0';
                $bonusPayout['teamBonusCV']         = $bonusResult[$getDetails['client_id']]['teamBonus']?:'0';
                $bonusPayout['teamBonusRP']         = Setting::setDecimal($bonusResult[$getDetails['client_id']]['teamBonus']*$getBonusDateNCountry['cv_rate'])?:'0';
                $bonusPayout['leadershipBonusCV']   = $bonusResult[$getDetails['client_id']]['leadershipBonus']?:'0';
                $bonusPayout['leadershipBonusRP']   = Setting::setDecimal($bonusResult[$getDetails['client_id']]['leadershipBonus']*$getBonusDateNCountry['cv_rate'])?:'0';
                $bonusPayout['cashAwardBonusRP']    = $bonusResult[$getDetails['client_id']]['awardBonus']?:'0';
                $bonusPayout['recruitPromo']        = Setting::setDecimal($bonusResult[$getDetails['client_id']]['recruitPromo'])?:'0';
                $bonusPayout['totalBonusAmountRP']  = Setting::setDecimal($bonusPayout['goldmineBonusRP']+$bonusPayout['teamBonusRP']+$bonusPayout['leadershipBonusRP']+$bonusPayout['cashAwardBonusRP']+$bonusPayout['recruitPromo']);
                $bonusPayout['taxPercentage']       = $getDetails['taxPercentage'];
                $bonusPayout['taxAmount']           = Setting::setDecimal($bonusPayout['totalBonusAmountRP']*($getDetails['taxPercentage']/100))?:'0';
                $bonusPayout['totalNetBonusRP']     = Setting::setDecimal($bonusPayout['totalBonusAmountRP']-$bonusPayout['taxAmount'])?:'0';
                $bonusPayout['status']              = General::getTranslationByName($status)?:"-";
                $bonusPayout['npwpStatus']          = $validClientIDArr[$getDetails['client_id']]?"Y":"N";
                $bonusPayout['bonusIDAry']          = $getDetails['bonusID'];

                $bonusPayoutAry[] = $bonusPayout;
            }

            if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $params['file_name'] = $params['file_name']."_".$countryName;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
             }

            $data['bonusPayoutDetail'] = $bonusPayoutAry;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']   = 1;
                $data['numRecord']   = $totalRecord;
            }else{
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['numRecord']   = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function updatePayoutStatus($params, $bonusDetailFlag){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $bonusPayoutID = $params['bonusPayoutID'];
            $bonusID       = $params['bonusID'];

            $userID = $db->userID;
            $site = $db->userType;

            if(!$bonusPayoutID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
            }

            if($bonusDetailFlag){
                if(!$bonusID){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
                }
            }
            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User */, 'data' => "");
            }else{
                $db->where('id', $userID);
                $adminUsername = $db->getValue('admin','username');
                if(empty($adminUsername)){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User */, 'data' => "");
                }
            }

            $db->where('id', $bonusPayoutID);
            $getDateNID = $db->getOne('bonus_payout_summary', 'bonus_date, country_id, status, cv_rate');

            if(empty($getDateNID)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            if($bonusDetailFlag){
                $db->where('id', $bonusID, 'IN');
            }else{
                $db->where('country_id', $getDateNID['country_id']);
                $db->where('bonus_date', $getDateNID['bonus_date']);
            }
            $db->where('paid', '0');
            $copyDb = $db->copy();
            $checkValidRes = $db->get('mlm_bonus_report', null, 'id,client_id, bonus_type, bonus_amount');
            if(!$checkValidRes){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach($checkValidRes as $checkValidRow){
                $clientBonusArr[$checkValidRow['client_id']]['bonusID'][$checkValidRow['id']] = $checkValidRow['id'];

                if(in_array($checkValidRow['bonus_type'], array('awardBonus','recruitPromo'))){
                    $clientBonusArr[$checkValidRow['client_id']]['awardBonusTotal'] +=  $checkValidRow['bonus_amount'];
                }else{
                    $clientBonusArr[$checkValidRow['client_id']]['otherBonusTotal'] +=  $checkValidRow['bonus_amount'];
                }
            }

            //Get Tax Setting
            $db->where('name','bonusTaxPercentage');
            $db->where('status','Active');
            $db->orderBy('CAST(ref_id AS Integer)','DESC');
            $bonusTaxStgArr = $db->map('value')->get('system_settings_admin',null,'value,type,reference');

            if($clientBonusArr){
                $db->where('client_id',array_keys($clientBonusArr),"IN");
                $db->where('doc_type','NPWP Verification');
                $db->where('status','Approved');
                $validClientIDArr = $db->map('client_id')->get('mlm_kyc',null,'client_id');
            }

            foreach ($clientBonusArr as $clientID => &$bonusPayoutRow) {
                $convertedBonus = Setting::setDecimal(($bonusPayoutRow['otherBonusTotal']*$getDateNID['cv_rate']));
                $totalIDRBonus = $bonusPayoutRow['awardBonusTotal'] + $convertedBonus;

                foreach ($bonusTaxStgArr as $minBonus => $texSetting) {
                    $tempTaxPercentage = $texSetting['reference'];
                    if($validClientIDArr[$clientID]) $tempTaxPercentage = $texSetting['type'];

                    if($totalIDRBonus >= $minBonus){
                        $taxPercentage = $tempTaxPercentage;
                        break;
                    }
                }
                $bonusPayoutRow['taxPercentage'] = $taxPercentage;

                $updateData = array(
                    "tax_percentage" => $taxPercentage,
                    "paid"=>'1',
                    "updated_at" => $dateTime,
                    "updater_id" => $userID,
                );
                $db->where('id',$bonusPayoutRow['bonusID'],"IN");
                $updateDB = $db->update('mlm_bonus_report',$updateData);

                if(!$updateDB){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed. */, 'data' => "");
                }
            }

            $db->where('country_id', $getDateNID['country_id']);
            $db->where('bonus_date', $getDateNID['bonus_date']);
            $db->where('paid', '0');
            $checkPending = $db->has('mlm_bonus_report');

            if($checkPending){
                $updateStatus = array(
                    "status" => 'partial',
                    "updated_at" => $dateTime,
                );
            }else{
                $updateStatus = array(
                    "status" => 'paid',
                    "updated_at" => $dateTime,
                );
            }

            $db->where('id', $bonusPayoutID);
            $updateStatus = $db->update('bonus_payout_summary', $updateStatus);

            if(!$updateStatus){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed. */, 'data' => "");
            }

            //get client details 
            $clientIDAry = array_keys($clientBonusArr);


            if($clientIDAry){
                $db->where('id', $clientIDAry,'IN');
                $clientDetails = $db->map('id')->get('client',null, 'id, member_id, name, email, type');
            }

            //Call function to send email
            foreach($clientBonusArr as $clientID => $bonusRow){
                $sendEmail = self::sendPayoutNotificationEmail($clientDetails[$clientID]['member_id'], $clientDetails[$clientID]['name'], $clientDetails[$clientID]['email'], $bonusRow['awardBonusTotal'], $bonusRow['otherBonusTotal'], $getDateNID['cv_rate'], $bonusRow['taxPercentage']);
            }

            $activityData = array('admin' => $adminUsername, 'dateTime' => $dateTime);

            if($bonusDetailFlag){
                $activityData['bonusID'] = $bonusID;
            }else{
                $activityData['bonusID'] = $bonusPayoutID;
            }


            $activityRes = Activity::insertActivity('Update bonus payout', 'T00071', 'L00097', $activityData, $userID);

            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => '');
        }

        public function sendPayoutNotificationEmail($clientMemberID,$clientName,$recipient,$awardBonusTotal,$otherBonusTotal,$cvRate,$taxPercentage){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $memberSite = Setting::$configArray['memberSite'];
            $companyInfo = Setting::$systemSetting['companyInfo'];

            $socialDetail = json_decode($companyInfo, true);

            $sendType = 'email';

            if(empty($awardBonusTotal)) $awardBonusTotal = 0;
            if(empty($otherBonusTotal)) $otherBonusTotal = 0;
            $amount = ($otherBonusTotal * $cvRate) + $awardBonusTotal;
            $taxAmt = $amount * ($taxPercentage/100);
            $amount = $amount - $taxAmt;

            $subject = $translations['B00490'][$language]; //Pernyataan Bonus
            $content = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>'.$subject.'</title>
                    <style>
                        .loginBlock {
                            display: block;
                            width: 600px;
                            padding: 3rem 3rem;
                            max-width: 100%;
                            background-color: #f4f7fa;
                            background-size: cover;
                            background-repeat: no-repeat;
                            background-position: right 15%;
                            color: #141414;
                            font-family: Arial, Helvetica, sans-serif;
                        }

                        img.companyLogo {
                            display: block;
                            margin: 0 auto;
                        }

                        .companyMsgBox {
                            background-color: #fff;
                            border-radius: 8px;
                            margin-top: 2rem;
                            padding: 2rem;
                            box-shadow: 0 0 20px -10px #ccc;
                        }

                        .companyEmailIcon {
                            display: block;
                            margin: 1.5rem auto;
                        }

                        .longLine {
                            display: block;
                            width: 100%;
                            height: 2px;
                            background-color: #e7e7e7;
                            clear: both;
                            margin: 2rem auto;
                        }

                        .companyTxt1 {
                            font-size: 18px;
                            color: #48545c;
                            text-align: center;
                        }

                        .companyTxt2 {
                            font-size: 17px;
                        }

                        .companyTxt3 {
                            font-size: 14px;
                            padding: 0 1rem;
                            margin: 20px 0 15px 0;
                        }

                        .companyTxt4 {
                            font-size: 23px;
                            font-weight: 600;
                            padding: 0 1rem;
                            margin: 20px 0 15px 0;
                        }

                        a.companyLinkBtn {
                            display: block;
                            width: 100%;
                            background-color: #29abe2;
                            color: #fff;
                            text-decoration: none;
                            padding: 8px;
                            border-radius: 4px;
                            text-transform: uppercase;
                        }

                        a.companyLinkBtn:hover {
                            text-decoration: underline;
                        }

                        .shortLine {
                            display: block;
                            width: 40px;
                            height: 2px;
                            background-color: #e7e7e7;
                            clear: both;
                            margin: 1.5rem auto;
                        }

                        .companySmallTxt {
                            font-size: 12px;
                            font-style: italic;
                            color: #929191;
                            text-align: center;
                        }
                    </style>
                </head>
                <body>
                ';

                $content .= '

                    <div class="loginBlock">
                        <div class="companyMsgBox">
                            <img class="companyEmailIcon" src="'.$memberSite.'/images/project/companyLogo2.png" width="70px" alt="">
                            <h3 class="companyTxt1">'.$translations['B00490'][$language].'</h3> 
                            <div class="longLine"></div>
                            <p class="companyTxt3">'.str_replace(array("%%name%%","%%id%%"), array($clientName,$clientMemberID), $translations["B00491"][$language]).'</p>
                            <p class="companyTxt3">'.$translations["B00492"][$language].'</p>
                            <p class="companyTxt3">'.str_replace("%%amount%%", number_format($amount,"2"), $translations["B00493"][$language]).'</p>
                            <p class="companyTxt3">'.$translations['B00494'][$language].'</p>
                            <p class="companyTxt3">'.$translations['B00495'][$language].'</p>
                            <p class="companyTxt3">'.$translations['B00474'][$language].'</p>
                            <p class="companyTxt3">'.$translations['B00496'][$language].'</p>
                            <p class="companyTxt3">
                                '.$translations['B00497'][$language].'<br>
                                '.$translations['B00498'][$language].'
                            </p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $result = Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType,'','','','',1);

            return array("status" => "ok", "code" => 0, "statusMsg" => "Email Sent Successfully", "data" => "");
        }

        public function getPGPMonthlySalesSummary($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $site = $db->userType;
            if($site == 'Member'){
                $userID = $db->userID;
            }
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "mainLeaderUsername":
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
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
                    switch($dataName){
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                    
                                $db->where('bonus_date', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                    
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }

                                $db->where('bonus_date', date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'memberID':
                            $getMemberID = $db->subQuery();
                            $getMemberID->where('member_id', $dataValue);
                            $getMemberID->getValue('client', 'id', null);
                            $db->where('client_id', $getMemberID);
                            break;

                        case 'fullname':
                            if ($dataType == "like") {
                                $getMemberName = $db->subQuery();
                                $getMemberName->where('name', "%" .  $dataValue . "%", "LIKE");
                                $getMemberName->getValue('client', 'id', null);
                                $db->where('client_id', $getMemberName, 'IN');
                            }else{
                                $getMemberName = $db->subQuery();
                                $getMemberName->where('name', $dataValue);
                                $getMemberName->getOne('client', 'id');
                                $db->where('client_id', $getMemberName);
                            }
                            break;
                        
                        case 'memberRank':
                            $db->where('rank_id', $dataValue);
                            break; 

                        case 'fromMemberID':
                            $getMember = $db->subQuery();
                            $getMember->where('member_id', $dataValue);
                            $getMember->getvalue('client', 'id', null);
                            $db->where('direct_client_id', $getMember);
                            break;

                        case 'fromMemberRank':
                            $db->where('direct_rank_id', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member') $db->where('client_id', $userID);

            $db->groupBy('client_id');
            $db->groupBy('direct_client_id');
            $copyDb = $db->copy();
            $getRecord = $db->get('mlm_bonus_goldmine', $limit, 'id, client_id, bonus_date, rank_id, direct_client_id, direct_rank_id, SUM(from_amount) as from_amount');

            if(empty($getRecord)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $totalRecord = count($copyDb->getValue('mlm_bonus_goldmine', 'count(*)', null));

            foreach($getRecord AS $idValue){
                $memberIDRecord[$idValue['client_id']] = $idValue['client_id'];
                $memberIDRecord[$idValue['direct_client_id']] = $idValue['direct_client_id'];
            }

            $db->where('id', $memberIDRecord, 'IN');
            $getMemberIDRecord = $db->map('id')->get('client', null, 'id, member_id, name');
            
            $rankName = $db->map('id')->get('rank', null, 'id, translation_code');

            foreach($getRecord AS $value){
                $getBonus['date'] = $value['bonus_date'];
                if($site != 'Member'){
                    $getBonus['memberID'] = $getMemberIDRecord[$value['client_id']]['member_id'];
                    $getBonus['rank'] = $translations[$rankName[$value['rank_id']]][$language];
                }
                $getBonus['fullname'] = $getMemberIDRecord[$value['client_id']]['name'];
                $getBonus['fromMemberID'] = $getMemberIDRecord[$value['direct_client_id']]['member_id'];
                $getBonus['memberRank'] = $translations[$rankName[$value['direct_rank_id']]][$language];
                $getBonus['totalPGP'] = Setting::setDecimal($value['from_amount']?:"0");
                
                $getBonusAry[] = $getBonus;
            }

            if($params['type'] == "export" && $site != 'Member') {
                $params['command'] = __FUNCTION__;
                $params['file_name'] = $params['file_name'];
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['displayRecord']  = $getBonusAry;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getBonusPayoutSummaryMonetary($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            // $bonus          = $this->bonus;
            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['systemDateFormat'];
            $floor = pow(10, $decimalPlaces); // floor for extra decimal

            $db->where("disabled", "0");
            $db->orderBy("priority", "ASC");
            $bonusRes = $db->get('mlm_bonus', null, "name, payment, language_code, table_name");
            foreach($bonusRes as &$bonusRow ){
                $bonusNameArray[] = $bonusRow['name'];
                $bonusRow['display'] = ($translations[$bonusRow['language_code']][$language] != ""? $translations[$bonusRow['language_code']][$language] :  $bonusRow['name']);
                $bonusArray[$bonusRow['name']] = $bonusRow;
            }

            //define params
            $searchBonusIn = $db->copy();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':
                            $db->where('username', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            break;

                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
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

                    switch($dataName) {

                    case 'bonusDate':
                        // Set db column here
                        $columnName = 'bonus_date';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom<0){
                                $db->resetState();
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }
                            $db->where('LAST_DAY('.$columnName.')', date('Y-m-d', $dateFrom), '>=');
                            $searchBonusIn->where("LAST_DAY(created_at)", date('Y-m-d', $dateFrom), '>=');
                        }

                        if(strlen($dateTo) > 0) {
                            if($dateTo<0){
                                $db->resetState();
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }
                            if($dateTo < $dateFrom) {
                                $db->resetState();
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                            }

                            $db->where('LAST_DAY('.$columnName.')', date('Y-m-d', $dateTo), '<=');
                            $searchBonusIn->where("LAST_DAY(created_at)", date('Y-m-d', $dateTo), '<=');
                        }
                            
                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            if ($downlines) $searchBonusIn->where('client_id', $downlines, "IN");
            if ($mainDownlines) $searchBonusIn->where('client_id', $mainDownlines, "IN");

            $bonusInRes = $searchBonusIn->get('mlm_client_portfolio', null, "created_at, product_price");
            foreach( $bonusInRes as $row ){
                $bonusDate = date($dateTimeFormat, strtotime(date('Y-m-t',strtotime($row["created_at"]))));
                $bonusReport[$bonusDate]['totalBonusValue'] += $row['product_price'];
            }

            $copyDbA = $db->copy();
            $copyDbB = $db->copy();
            $copyDbC = $db->copy();
            $copyDbA->groupBy("bonus_date");
            $copyDbA->groupBy("bonus_type");
            $copyDbA->orderBy("bonus_date", "DESC");            

            $bonusSponsorTableResultTemp = $copyDbA->get("mlm_bonus_report", null, "sum(bonus_amount) AS bonus_amount, bonus_date, bonus_type");
            foreach ($bonusSponsorTableResultTemp as $key => $value) {
                $value["bonus_date"] = date($dateTimeFormat, strtotime(date('Y-m-t',strtotime($value["bonus_date"]))));
                if($bonusArray[$value["bonus_type"]]["payment"] == "Bimonthly"){
                    if(date("d",strtotime($value["bonus_date"])) <= "15"){
                        $reportDate = date("Y-m-15",strtotime($value["bonus_date"]));
                        $bonusPayoutData[$reportDate][$value['bonus_type']] += $value["bonus_amount"];
                        $totalPayout[$reportDate] += $value["bonus_amount"];
                    }else{
                        $reportDate = date("Y-m-t",strtotime($value["bonus_date"]));
                        $bonusPayoutData[$reportDate][$value['bonus_type']] += $value["bonus_amount"];
                        $totalPayout[$reportDate] += $value["bonus_amount"];
                    }
                }else{
                    $bonusPayoutData[$value["bonus_date"]][$value['bonus_type']] = $value["bonus_amount"];
                    $totalPayout[$value["bonus_date"]] += $value["bonus_amount"];
                }
            }

            $copyDbC->join("mlm_bonus_calculation_batch", "mlm_bonus_calculation_batch.id = mlm_promo.batch_id", "LEFT");
            $copyDbC->groupBy('bonus_date');
            $promoRes = $copyDbC->get("mlm_promo", null, "bonus_date,SUM(amount) as bonus_amount");

            $bonusNameArray[] = "recruitPromo";

            foreach ($promoRes as $promoRow) {
                $promoRow["bonus_date"] = date($dateTimeFormat, strtotime(date('Y-m-t',strtotime($promoRow["bonus_date"]))));
                $bonusArray["recruitPromo"]['name'] = 'recruitPromo';
                $bonusArray["recruitPromo"]['display'] = $translations['B00483'][$language]; /*Recruit & Active Program*/
                $bonusPayoutData[$promoRow["bonus_date"]]["recruitPromo"] = $promoRow["bonus_amount"];
                $totalPayout[$promoRow["bonus_date"]] += $promoRow["bonus_amount"];
            }

            $totalRecord = $copyDbB->getValue("mlm_bonus_calculation_batch", "count(DISTINCT CONCAT(YEAR(bonus_date),'-',MONTH(bonus_date),'-01'))");
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
                $totalPage = 1;
            }
            else{
                $totalPage = ceil($totalRecord/$limit[1]);
            }

            $db->orderBy('bonus_date', 'DESC');
            $getBonusDateRange = $db->get("mlm_bonus_calculation_batch",$limit, "DISTINCT LAST_DAY(bonus_date) AS bonus_date");

            // get CV rate
            $db->where('country_id', '100');
            $getCVRate = $db->map('bonus_date')->get('bonus_payout_summary', null, 'bonus_date, cv_rate');

            $neededToBeMultiply = array('goldmineBonus', 'teamBonus', 'leadershipBonus');

            if (!empty($getBonusDateRange)){

                foreach($getBonusDateRange as $key => $bonusDateValue) {
                    $rate = $getCVRate[$bonusDateValue['bonus_date']];
                    $bonusDateValue["bonus_date"] = date($dateTimeFormat, strtotime($bonusDateValue["bonus_date"]));

                    $totalBV = $bonusReport[$bonusDateValue['bonus_date']]['totalBonusValue'];

                    foreach ($bonusNameArray as $key => $bonusNameArrayValue) {
                        unset($reportDate);
                        $payoutAmount = $bonusPayoutData[$bonusDateValue["bonus_date"]][$bonusNameArrayValue]?:0;
                        if(in_array($bonusNameArrayValue, $neededToBeMultiply)){
                            $payoutAmount = $payoutAmount * $rate;
                        }
                        $percentage = $totalBV> 0? ($payoutAmount / $totalBV * 100):0;

                        $tempValue["payout"] = number_format($payoutAmount, $decimalPlaces, '.', '' );
                        $tempValue["percentage"] = number_format( (floor(strval($percentage*$floor))/$floor) , $decimalPlaces , '.', '');
                        $bonusReportList[$bonusDateValue["bonus_date"]][$bonusNameArrayValue]= $tempValue;

                        $totalPayoutAmount += $payoutAmount;
                        $totalBonusPayout[$bonusNameArrayValue] += $payoutAmount;
                        $subTotalPayoutAmount += $payoutAmount;
                    }

                    $bonusReportList[$bonusDateValue["bonus_date"]]['totalBonusValue'] = number_format($totalBV?$totalBV:0, $decimalPlaces, '.', '' );

                    $subTotalPayoutAmountPercentage = $totalBV > 0 ? number_format( (floor(strval(($subTotalPayoutAmount / $totalBV * 100)*$floor))/$floor) , $decimalPlaces , '.', '') : 0.00;
                    $bonusReportList[$bonusDateValue["bonus_date"]]['subTotalPayoutAmount'] = number_format($subTotalPayoutAmount?$subTotalPayoutAmount:0, $decimalPlaces, '.', '' );

                    $bonusReportList[$bonusDateValue["bonus_date"]]['subTotalPayoutAmountPercentage'] = $subTotalPayoutAmountPercentage > 0 ? number_format( (floor(strval($subTotalPayoutAmountPercentage*$floor))/$floor) , $decimalPlaces , '.', '') : 0;

                    $totalBonusValue += $totalBV?:0;

                    unset($subTotalPayoutAmount);
                }
                $totalBonusPayout['totalPayoutAmount'] = number_format($totalPayoutAmount, $decimalPlaces, '.', '');
                $totalBonusPayout['totalBonusValue'] = number_format($totalBonusValue, $decimalPlaces, '.', '');
                $totalBonusPayout['grandSubTotalPayoutAmountPercentage'] = ($totalPayoutAmount/$totalBonusValue * 100) > 0 ? number_format( (floor(strval(($totalPayoutAmount/$totalBonusValue * 100)*$floor))/$floor) , $decimalPlaces , '.', '') : 0;

                krsort($bonusReport);

                foreach ($totalBonusPayout as $key => $totalBonusPayoutValue) {
                    $totalBonusPayout[$key] = number_format($totalBonusPayoutValue, $decimalPlaces, '.', '');
                    $totalBonusPayout[$key.'Percentage'] = number_format(($totalBonusPayoutValue/$totalBonusValue*100), $decimalPlaces , '.', '');
                    $totalBonusPayout[$key.'Percentage'] = number_format( (floor(strval(($totalBonusPayoutValue/$totalBonusValue * 100)*$floor))/$floor) , $decimalPlaces , '.', '');
                }

                if($params['type'] == "export"){
                    $header = $params['header'];
                    $dataKeyArr = $params['key'];
                    $data["base64"] = Self::exportExcelBase64($bonusReport,$header,$dataKeyArr,"bonusPayoutSummary",$totalArray);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                $data['bonusName'] = $bonusArray;
                $data['totalBonusReport'] = $totalBonusPayout;
                $data['report']   = $bonusReportList;
                $data['totalPage']   = $totalPage;
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

        }

        public function getDVPMonthlySalesSummary($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'];

            $clientID = $db->userID;
            $site = $db->userType;
            if ($site == 'Member') {
                $clientID = trim($params['clientID']);
            }

            if(!$seeAll) {
                $limit = General::getLimit($pageNumber);
            }

            $db->where('trace_key','%'.$clientID.'%','LIKE');
            $db->where('client_id',$clientID, '!=');
            $downlines = $db->map('client_id')->get('tree_placement',null,'client_id');

            if (empty($downlines)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $copyDbA = $db->copy();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'memberID': 
                            $sq = $db->subQuery();
                            $sq->where('member_id', $dataValue);
                            $sq->getValue('client', 'id', null);
                            
                            $db->where('client_id', $sq, "IN");
                            
                            break;

                        case 'memberRank':
                            $copyDbA->where('name', 'rankDisplay');
                            $copyDbA->groupBy('client_id');
                            $copyDbA->groupBy('type');
                            $tempRes = $copyDbA->get('client_rank', null, 'client_id, MAX(id) as id');

                            foreach ($tempRes as $row) {
                                $maxID[] = $row['id'];
                            }

                            $sq = $db->subQuery();
                            $sq->where('id', $maxID, "IN");
                            $sq->where('rank_id', $dataValue);
                            $sq->getValue('client_rank', 'client_id', null);

                            $db->where('client_id', $sq, "IN");

                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            $columnName = "created_at";

                            if(strlen($dateFrom) > 0) {
                                if ($dateFrom<0) {
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                $db->where('LAST_DAY('.$columnName.')', date('Y-m-t', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if ($dateTo < 0) {
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language]/* Invalid date. */, 'data' => "");
                                }

                                if ($dateTo < $dateFrom) {
                                    $db->resetState();
                                    return array('status'=>"error", 'code' => 1, 'statusMsg' =>$translations["E00158"][$language]/* Date from cannot be later than date to. */, 'data'=>$data);
                                }
                                $db->where('LAST_DAY('.$columnName.')', date('Y-m-t', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('client_id', $downlines, "IN");

            $memberIDQuery = "(SELECT member_id FROM client WHERE client.id = client_id) AS fromMemberID";
            $memberUsernameQuery = "(SELECT name FROM client WHERE client.id = client_id) AS fromName";

            $db->groupBy('client_id');
            $db->groupBy('LAST_DAY(created_at)');
            $db->orderBy('id', "DESC");
            $copyDbB = $db->copy();

            $res = $db->get("mlm_bonus_in", $limit, $memberIDQuery.",".$memberUsernameQuery.", client_id, SUM(bonus_value) as totalDVP, LAST_DAY(created_at) as created_at");

            $totalRecord = count($copyDbB->getValue('mlm_bonus_in', 'count(id)', null));

            if (empty($res)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
                $totalPage = 1;
            }
            else{
                $totalPage = ceil($totalRecord/$limit[1]);
            }

            foreach($res as $resClient){
                $clientIDs[] = $resClient['client_id'];
            };

            $tempRank = Bonus::getClientRank("Bonus Tier", $clientIDs, "", "rankDisplay", "");
            $rankData = $db->map('id')->get('rank', null, 'id, name, translation_code');

            /*Arrange the rank data into the respective data*/
            foreach ($res as &$resClient) {
                $resClient['memberRank'] = $rankData[$tempRank[$resClient['client_id']]['rank_id']]['name']?$translations[$rankData[$tempRank[$resClient['client_id']]['rank_id']]['translation_code']][$language]:'-';
                $resClient['date'] = date('m/Y', strtotime($resClient['created_at']));
                unset($resClient['created_at']);
                unset($resClient['client_id']);
                $reportList[] = $resClient;
            }

            $data['reportList'] = $reportList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['totalPage'] = $totalPage;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getEnrollmentBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting["systemDateTimeFormat"];
            // $dateFormat = Setting::$systemSetting["systemDateFormat"];
            // manual set date setting for this report
            $dateFormat = 'd/m/Y h:i';
            
            $seeAll = $params["seeAll"];
            $pageNumber = $params["pageNumber"] ? $params["pageNumber"] : 1;
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params["searchData"];
            $usernameSearchType = $params["usernameSearchType"];
            $fromUsernameSearchType = $params["fromUsernameSearchType"];

            $cpDb = $db->copy();

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case "leaderUsername":
                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if(empty($downlines)) {
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $downlines, "IN");

                            break;

                        case "mainLeaderUsername":
                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);


                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");

                        //     break;
                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where('client_id = leader_id');
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null,'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }
                                $tempDownlines = array_unique($tempDownlines);
                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $mainDownlines, "IN");
                            break;

                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $db->where('client_id', $downlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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

                        case 'fromName':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("from_client_id", $sq, "in");
                            break;

                        case 'username':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", "%" . $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }

                            break;

                        case 'fromUsername':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'bonusDate':
                            $columnName = "bonus_date";

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }

                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
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

            $db->orderBy("bonus_date", "DESC");
            $db->orderBy("created_at", "DESC");

            if($site == "Member") {
                $db->where("client_id", $userID);
            }

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("mlm_bonus_enrollment", "count(*)");
          
            if($seeAll == "1") {
                $limit = array(0, $totalRecord);
            }

            $result = $db->get("mlm_bonus_enrollment", $limit, "id, client_id, bonus_date, from_id, amount, created_at");

            if(!$result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
            }

            foreach($result as $value) {
                $clientIDAry[$value["client_id"]] = $value["client_id"];
                $clientIDAry[$value["from_id"]] = $value["from_id"];
            }

            if($clientIDAry) {
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, name, member_id");
            }

            foreach($result as $value) {
                $bonus['bonusDate'] = date($dateFormat, strtotime($value['bonus_date']));

                unset($clientData);
                $clientData = $clientDataAry[$value['client_id']];
                $bonus['username'] = $clientData['username'];
                $bonus['name'] = $clientData['name'];
                $bonus['memberID'] = $clientData['member_id'];

                unset($fromData);
                $fromData = $clientDataAry[$value['from_id']];
                $bonus['fromUsername'] = $fromData['username'];
                $bonus['fromName'] = $fromData['name'];
                $bonus['fromMemberID'] = $fromData['member_id'];
                $bonus['bonusAmount'] = Setting::setDecimal($value['amount']);
                $grandTotal += $value['amount'];

                $bonusList[] = $bonus;
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $bonusList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['grandTotal'] = Setting::setDecimal($grandTotal);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);  
        }

        public function getUnilevelBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting["systemDateTimeFormat"];
            // $dateFormat = Setting::$systemSetting["systemDateFormat"];
            // manual set date setting for this report
            $dateFormat = 'd/m/Y h:i';
            
            $seeAll = $params["seeAll"];
            $pageNumber = $params["pageNumber"] ? $params["pageNumber"] : 1;
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params["searchData"];

            $cpDb = $db->copy();

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case "leaderUsername":
                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if(empty($downlines)) {
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $downlines, "IN");

                            break;

                        case "mainLeaderUsername":
                            $db ->where('username', $dataValue);
                            $mainLeaderID = $db ->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                            
                        // case "mainLeaderID":
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");

                        //     break;
                        case "mainLeaderID":
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where('client_id = leader_id');
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }

                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $mainDownlines, "IN");
                            break;

                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $db->where('client_id', $downlines, "IN");
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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

                        case 'fromName':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("from_client_id", $sq, "in");
                            break;

                        case 'username':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }

                            break;

                        case 'fromUsername':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'bonusDate':
                            $columnName = "bonus_date";

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }

                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
                            break;

                        case 'rankID':
                            $db->where('rank_id',$dataValue);
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

            $db->orderBy("bonus_date", "DESC");
            $db->orderBy("created_at", "DESC");

            if($site == "Member") {
                $db->where("client_id", $userID);
            }

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("mlm_bonus_unilevel", "count(*)");
          
            if($seeAll == "1") {
                $limit = array(0, $totalRecord);
            }

            $result = $db->get("mlm_bonus_unilevel", $limit, "id, client_id, bonus_date, rank_id, couple_flush, flush_dvp, calculated_dvp, amount, payable_amount, created_at");

            if(!$result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
            }

            foreach($result as $value) {
                $clientIDAry[$value["client_id"]] = $value["client_id"];
                $rankIDAry[$value['rank_id']] = $value['rank_id'];
            }

            if($clientIDAry) {
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, name, member_id");
            }

            if($rankIDAry){
                $db->where("id",$rankIDAry,"IN");
                $rankTranslationCode = $db->map('id')->get('rank', NULL, 'id, translation_code');   
            }

            foreach($result as $value) {
                $bonus['bonusDate'] = date($dateFormat, strtotime($value['bonus_date']));

                unset($clientData);
                $clientData = $clientDataAry[$value['client_id']];
                $bonus['username'] = $clientData['username'];
                $bonus['name'] = $clientData['name'];
                $bonus['memberID'] = $clientData['member_id'];
                $bonus['rank'] = $translations[$rankTranslationCode[$value['rank_id']]][$language];
                $bonus['flushNum'] = Setting::setDecimal($value['couple_flush']);
                $bonus['flushDVP'] = Setting::setDecimal($value['flush_dvp']);
                $bonus['calDVP'] = Setting::setDecimal($value['calculated_dvp']);
                $bonus['bonusAmount'] = Setting::setDecimal($value['payable_amount']);

                $grandTotal += $value['payable_amount']; 

                $bonusList[] = $bonus;
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $bonusList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['grandTotal'] = Setting::setDecimal($grandTotal);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);  
        }

        public function getCoupleBonusReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting["systemDateTimeFormat"];
            // $dateFormat = Setting::$systemSetting["systemDateFormat"];
            // manual set date setting for this report
            $dateFormat = 'd/m/Y h:i';
            
            $seeAll = $params["seeAll"];
            $pageNumber = $params["pageNumber"] ? $params["pageNumber"] : 1;
            $limit = General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params["searchData"];

            $cpDb = $db->copy();

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case "leaderUsername":
                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if(empty($downlines)) {
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $downlines, "IN");

                            break;

                        case "mainLeaderUsername":
                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");

                        //     break;
                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where('client_id = leader_id');
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null,'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }
                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $db->where('client_id', $downlines, "IN");
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch($dataName) {
                        case 'name':
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

                        case 'fromName':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("from_client_id", $sq, "in");
                            break;

                        case 'username':
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }

                            break;

                        case 'fromUsername':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'fromMemberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("from_client_id", $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'bonusDate':
                            $columnName = "bonus_date";

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }

                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $db->where('client_id',$sq);
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

            $db->orderBy("bonus_date", "DESC");
            $db->orderBy("created_at", "DESC");
            // $db->orderBy("id", "DESC");

            if($site == "Member") {
                $db->where("client_id", $userID);
            }

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("mlm_bonus_couple", "count(*)");
          
            if($seeAll == "1") {
                $limit = array(0, $totalRecord);
            }

            $result = $db->get("mlm_bonus_couple", $limit, "id, client_id, bonus_date, cf_dvp_1, new_dvp_1, remaining_dvp_1, cf_dvp_2, new_dvp_2, remaining_dvp_2, total_couple, calculated_couple, unit_bv, payable_amount, created_at");

            if(!$result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");
            }

            foreach($result as $value) {
                $clientIDAry[$value["client_id"]] = $value["client_id"];
            }

            if($clientIDAry) {
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, name, member_id");
            }

            foreach($result as $value) {
                $bonus['bonusDate'] = date($dateFormat, strtotime($value['bonus_date']));

                unset($clientData);
                $clientData = $clientDataAry[$value['client_id']];
                $bonus['username'] = $clientData['username'];
                $bonus['name'] = $clientData['name'];
                $bonus['memberID'] = $clientData['member_id'];
                $bonus['cfLeftDVP'] = Setting::setDecimal($value['cf_dvp_1']);
                $bonus['newLeftDVP'] = Setting::setDecimal($value['new_dvp_1']);
                $bonus['remLeftDVP'] = Setting::setDecimal($value['remaining_dvp_1']);
                $bonus['cfRightDVP'] = Setting::setDecimal($value['cf_dvp_2']);
                $bonus['newRightDVP'] = Setting::setDecimal($value['new_dvp_2']);
                $bonus['remRightDVP'] = Setting::setDecimal($value['remaining_dvp_2']);
                $bonus['coupleNum'] =  $value['total_couple'];
                $bonus['calCouple'] = $value['calculated_couple'];
                $bonus['bonusAmount'] = Setting::setDecimal($value['payable_amount']);

                $grandTotal += $value['payable_amount'];

                $bonusList[] = $bonus;
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['bonusList'] = $bonusList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['grandTotal'] = Setting::setDecimal($grandTotal);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);  
        }

        public function getLeadershipCashRewardReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $searchData = $params['searchData'];
            $seeAll = $params['seeAll'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateFormat = Setting::$systemSetting['systemDateFormat'];
            $bonusDateTimeFormat = Setting::$systemSetting['bonusDateTimeFormat'];
            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];

            $userID = $db->userID;
            $site = $db->userType;

            $usernameSearchType = $params["usernameSearchType"];

            $cpDb = $db->copy();

            $adminLeaderAry = Setting::getAdminLeaderAry();
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUserName':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clinetID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if (empty($downlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /*No Result Found.*/, 'data'=> "");

                            break;
                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID = $db->getValue('client', "id");
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found.*/, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");

                        //     break;

                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where('client_id = leader_id');
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, 'IN');
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }

                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $mainDownlines, "IN");

                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $db->where('client_id', $downlines, "IN");
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
                        case 'bonusDate': 
                            $columnName = 'bonus_date';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id', $dataValue);
                            $sq->getOne("client", "id");
                            $db->where('client_id', $sq);
                            break;

                        case 'username':
                            if ($dataType == 'match') {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            } else if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where('username', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN');
                            }

                            break;

                        case 'rankID':
                            $db->where("rank_id", $dataValue);
                            break;

                        case 'name':
                            if ($dataType == 'match') {
                                $sq = $db->subQuery();
                                $sq->where('name', $dataValue);
                                $sq->getOne('client', 'id');
                                $db->where('client_id', $sq);
                            } else if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where('name', $dataValue . '%', "LIKE");
                                $sq->get('client', NULL, 'id');
                                $db->where('client_id', $sq, 'IN');
                            }

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where('mlm_bonus_leadership_reward.client_id', $userID);
            }

            if($adminLeaderAry) $db->where('mlm_bonus_leadership_reward.client_id', $adminLeaderAry, 'IN');
            if($mainDownlines) $db->where('client_id', $mainDownlines, "IN");
            if($downlines) $db->where('client_id', $downlines, "IN");

            $db->orderBy('bonus_date', 'DESC');
            $db->orderBy('id', 'DESC');

            $copyDb = $db -> copy();
            $totalRecord = $copyDb->getValue('mlm_bonus_leadership_reward', "count(*)");
            if($seeAll == '1') {
                $limit == array(0, $totalRecord);
            }

            if($params['type'] == "export"){
                $limit = null;
            }

            $result = $db->get('mlm_bonus_leadership_reward', $limit, 'bonus_date, client_id, rank_id, acc_couple, bonus_amount');

            if(empty($result)){
                return array('status'=>"ok", 'code' => 0, 'statusMsg' => $translations['E00714'][$language] /* No Results Found */, 'data' => "");
            }

            foreach($result as $value) {
                $clientIDAry[$value['client_id']] = $value['client_id'];
                $rankIDAry[$value['rank_id']] = $value['rank_id'];
            }

            if($clientIDAry) {
                $db->where('id', $clientIDAry, 'IN');
                $clientUsernameAry = $db->map('id')->get('client', null, 'id, username, member_id, name, email, country_id');
            }
            
            if($rankIDAry){
                $db->where("id",$rankIDAry,"IN");
                $rankTranslationCode = $db->map('id')->get('rank', NULL, 'id, translation_code');   
            }

            foreach($result as $value) {
                $retrievedData['bonusDate']         = $value['bonus_date'] != '0000-00-00' ? date($dateFormat, strtotime($value['bonus_date'])) : "-";

                $retrievedData['username']          = $clientUsernameAry[$value['client_id']]['username'];
                $retrievedData['name']              = $clientUsernameAry[$value['client_id']]['name'];
                $retrievedData['memberID']          = $clientUsernameAry[$value['client_id']]['member_id'];
                $retrievedData['rank']              = $translations[$rankTranslationCode[$value['rank_id']]][$language] ? : "-";
                $retrievedData['accCouple']         = $value['acc_couple'];
                $retrievedData['bonusAmount']       = Setting::setDecimal($value['bonus_amount'], $decimalPlaces);
                

                $dataList[] = $retrievedData;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status'=>"ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['data'] = $dataList;
            $data['pageNumber']        = $pageNumber;
            $data['totalRecord']       = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);

        }
	}
?>
