<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 20/04/2018.
    **/

    class Custom {

        function __construct(){

        }

        function resendAuthenticationContent($username,$otpCode){
            $config = Setting::$configArray;
            $sysSetting = Setting::$systemSetting;

            $html ='<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Email Verification</title>
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
                                text-align: center;
                                padding: 1rem 20%;
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
                            }
                        </style>
                    </head>
                    <body>
                        <div class="loginBlock">
                            <div class="companyMsgBox">
                                <img class="companyEmailIcon" src="'. $config['memberSite'] .'images/project/companyLogo2.png" width="70px" alt="">
                                <h3 class="companyTxt1">Verify Your Email Address</h3> 
                                <div class="longLine"></div>
                                <h3 class="companyTxt2">Hello '.$username.',</h3> 
                                <p class="companyTxt3">You are required to enter the follow OTP verification code to verify your registered Email with '.$config['companyName'].'. Please enter your OTP code in <b>'.$sysSetting['otpValidTime'].'</b>.</p>
                                <p class="companyTxt3">Your OTP verification code:</p>
                                <p class="companyTxt4">'.$otpCode.'</p>
                                <div class="shortLine"></div>
                                <small class="companySmallTxt">Disclaimer: If this wasn’t you, please ignore this email.</small>
                            </div>
                             
                        </div>
                    </body>
                    </html>';

            return $html;
        }

        public function calculateClientRank($clientID, $dateTime, $moduleType){
            $db = MysqliDb::getInstance();
            $rankType   = "Bonus Tier";
            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');
            $date = date('Y-m-d',strtotime($dateTime));
            $realClientID = $clientID;

            if(!$clientID){
                return false;
            }

            //Get Special Calculate Sales Setting
            $db->where('name','spCalSalesDate');
            $spCalSalesRes = $db->getOne('system_settings','value,reference');
            $spSalesFrom = $spCalSalesRes['value'];
            $spSalesPeriod = explode("#", $spCalSalesRes['reference']);

            $startDate = date('Y-m-01',strtotime($dateTime));
            $endDate = $date;

            if((strtotime($date) >= strtotime($spSalesPeriod[0])) && (strtotime($date) <= strtotime($spSalesPeriod[1]))){
                $startDate = $spSalesFrom;
                Log::write(date("Y-m-d H:i:s") . " Special Calculate Sales Period. From ".$startDate." to End ".$endDate."\n");
            }

            if($moduleType){
                $db->where('client_id',$clientID);
                $isActivated = $db->getValue('client_sales','activated');
            }

            //Get Sponsor Group
            $sponsorTreeData = $db->map('client_id')->get('tree_sponsor',null,'client_id,trace_key');

            $db->where('DATE(created_at)',$startDate,">=");
            $db->where('DATE(created_at)',$endDate,"<=");
            $db->where('status','Active');
            $db->groupBy('client_id');
            $clientSalesArr = $db->map('client_id')->get('mlm_client_portfolio',null,'client_id,SUM(bonus_value) as totalSales');

            $db->where('type',$rankType);
            $db->orderBy('priority','DESC');
            $rankIDArr = $db->map('id')->get('rank',null,'id,priority');

            /*Get Rank Setting*/
            if($rankIDArr){
                $db->where('rank_id',array_keys($rankIDArr),"IN");
                $rankRes = $db->get('rank_setting',null,'rank_id,name,value,reference,type');
                foreach ($rankRes as $rankRow) {
                    switch ($rankRow['type']) {
                        case 'purchase':
                            $minRankQualification[$rankRow["rank_id"]][$rankRow["name"]]['value'] = $rankRow["value"];
                            $minRankQualification[$rankRow["rank_id"]][$rankRow["name"]]['reference'] = $rankRow["reference"];

                            if($rankRow['name'] == 'minPGPSales'){
                                $breakOutRank = $rankRow["reference"]; //3
                            }

                            if($rankRow['name'] == 'minActiveLeg'){
                                $maxLevelLimit = $rankRow["reference"]; //10
                            }
                            break;
                        case 'percentage':
                        case 'level':
                            $rankPercentArr[$rankRow['rank_id']][$rankRow['name']] = $rankRow['value'];
                            break;
                    }
                }
            }

            $rankData = Bonus::getClientRank($rankType,"",$dateTime,"goldmineBonus","System");

            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write(date("Y-m-d H:i:s") . " Start Calculate Client Rank. Memory Used: ".$alloc_mem."MB .\n");

            while ($clientID) {
                $secDownlineArr = array();
                $activeDownlineCount = 0;
                $directSales = 0;
                unset($secDownlineArr,$downlineRes,$pgpSalesArr,$groupSales,$extraOptionRank);

                $db->where('sponsor_id',$clientID);
                $downlineRes = $db->map('id')->get('client',null,'id,DATE(created_at) AS createdAt');

                $db->where('trace_key',"%".$clientID."/%","LIKE");
                $db->orderBy('level','ASC');
                $allDownlineArr = $db->map('client_id')->get('tree_sponsor',null,'client_id,upline_id,trace_key,level');
                foreach ($allDownlineArr as $allDownlineRow) {
                    if(!$maxLevel) $maxLevel = $allDownlineRow['level'] + $maxLevelLimit;

                    if($allDownlineRow['level'] >= $maxLevel) break;
                    $tempUplineArr[$allDownlineRow['client_id']] = $allDownlineRow['upline_id'];
                }

                foreach ($clientSalesArr as $clientIDSales => $clientSales) {
                    if(((strtotime($downlineRes[$clientIDSales]) >= strtotime($startDate)) && (strtotime($downlineRes[$clientIDSales]) <= strtotime($endDate))) && ($clientSales > $directSales)){
                        $directSales = $clientSales;
                    }

                    $uplineIDArr = explode("/", $sponsorTreeData[$clientIDSales]);
                    $breakOutFlag = 0;
                    krsort($uplineIDArr);
                    foreach ($uplineIDArr as $uplineID) {
                        $groupSales[$uplineID] += $clientSales;
                        $uplineRankID = $rankData[$uplineID]['rank_id'];

                        if($breakOutFlag) continue;

                        if($rankIDArr[$uplineRankID]>=$breakOutRank){
                            $pgpSalesArr[$uplineID] += $clientSales;
                            $breakOutFlag = 1;
                            continue;
                        }

                        $pgpSalesArr[$uplineID] += $clientSales;
                    }
                }

                if($tempUplineArr){
                    $db->where('client_id',array_keys($tempUplineArr),"IN");
                    $db->where('activated',"1");
                    $activeDownlineArr = $db->getValue('client_sales',"client_id",null);
                }

                foreach ($activeDownlineArr as $downlineID) {
                    if(in_array($downlineID, array_keys($downlineRes))){
                        $activeUplineAry[$downlineID] += 1;
                    }
                    while ($tempUplineArr[$downlineID]) {
                        if(in_array($tempUplineArr[$downlineID], array_keys($downlineRes))){
                            $activeUplineAry[$tempUplineArr[$downlineID]] += 1;
                        }
                        $downlineID = $tempUplineArr[$downlineID];
                    }
                }

                //Get Extra Option for PVP
                $db->where('client_id',$clientID);
                $db->where('name','extraPVPOption');
                $db->where('CAST(reference AS DATE)',$date,">=");
                $extraOptionRank = $db->getValue('client_setting','value');

                $activeDownlineCount = COUNT($activeUplineAry);
                unset($activeUplineAry,$tempUplineArr,$activeDownlineArr);

                foreach ($rankIDArr as $rankID => $rankPriority) {
                    $minOwnSales = $minRankQualification[$rankID]['minOwnSales']['value'];
                    $minPortfolio = $minRankQualification[$rankID]['minOwnSales']['reference']; // Check for Started Pack
                    $minPGPSales = $minRankQualification[$rankID]['minPGPSales']['value'];
                    $minPGPPriority = $minRankQualification[$rankID]['minPGPSales']['reference'];
                    $minGroupSales = $minRankQualification[$rankID]['minGroupSales']['value'];
                    $minActiveLeg = $minRankQualification[$rankID]['minActiveLeg']['value'];
                    $fristDownlinePriority = $minRankQualification[$rankID]['minFirstDownlineRank']['value'];
                    $minFristDownline = $minRankQualification[$rankID]['minFirstDownlineRank']['reference'];
                    $secDownlinePriority = $minRankQualification[$rankID]['minSecDownlineRank']['value'];
                    $minSecDownline = $minRankQualification[$rankID]['minSecDownlineRank']['reference'];

                    $ownSales = 0;
                    $pgpSales = 0;
                    $activeLeg = $activeDownlineCount;
                    $totalGroupSales = 0;
                    $fristDownlineEntitle = 0;
                    $secDownlineEntitle = 0;
                    $countPortfolio = 0;
                    unset($fristEntitleArr,$secEntitleArr);

                    if($fristDownlinePriority > 0 || $secDownlinePriority > 0){
                        foreach ($allDownlineArr as $downlineID => $downlineRow) {
                            $downlineTraceKey = $downlineRow['trace_key'];
                            $downlineRankID = $rankData[$downlineID]['rank_id'];
                            $uplineArr = explode("/", $downlineTraceKey);

                            if(array_intersect($uplineArr, $fristEntitleArr)){
                                if(!array_intersect($uplineArr, $secEntitleArr)){
                                    if($rankIDArr[$downlineRankID] < $secDownlinePriority) continue;

                                    Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Downline ID : ".$downlineID." Rank : ".$downlineRankID." Last Gen User : ".json_encode(array_intersect($uplineArr, $fristEntitleArr))." Entile Second Gen.\n");
                                    $secEntitleArr[$downlineID] = $downlineID;
                                }
                            }elseif(!$fristEntitleArr[$downlineID]){
                                if($rankIDArr[$downlineRankID] < $fristDownlinePriority) continue;

                                Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Downline ID : ".$downlineID." Rank : ".$downlineRankID." Entile Frist Gen.\n");
                                $fristEntitleArr[$downlineID] = $downlineID;
                            }

                            if((COUNT($fristEntitleArr) >= $minFristDownline) && (COUNT($secEntitleArr) >= $minSecDownline)){
                                Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Hit Requirement. Break.\n");
                                break;
                            }
                        }
                    }

                    if($clientSalesArr[$clientID]) $ownSales = $clientSalesArr[$clientID];
                    if($pgpSalesArr[$clientID]) $pgpSales = $pgpSalesArr[$clientID];
                    if($groupSales[$clientID]) $totalGroupSales = $groupSales[$clientID];

                    $fristDownlineEntitle = COUNT($fristEntitleArr);
                    $secDownlineEntitle = COUNT($secEntitleArr);
                    $checkOwnSales = $ownSales;

                    //If extra option sales more than original option will replace it.
                    if(($rankIDArr[$extraOptionRank] >= $rankPriority) && ($directSales > $checkOwnSales)){
                        $checkOwnSales = $directSales;
                        Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Extra Option Rank : ".$extraOptionRank." Rank ID : ".$rankID." ownSales : ".$ownSales." directSales : ".$directSales." : ".$checkOwnSales.".\n");
                    }

                    if($minPortfolio>0){
                        $db->where('client_id',$clientID);
                        $countPortfolio = $db->getValue('mlm_client_portfolio','COUNT(id)');
                        Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Process Rank: ".$rankID." Portfolio Count : ".$countPortfolio." Min Portfolio Count : ".$minPortfolio.".\n");
                    }

                    if(($checkOwnSales >= $minOwnSales) && ($pgpSales >= $minPGPSales) && ($totalGroupSales >= $minGroupSales) && ($activeLeg >= $minActiveLeg) && ($fristDownlineEntitle >= $minFristDownline) && ($secDownlineEntitle >= $minSecDownline) && ($countPortfolio >= $minPortfolio)){
                        Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." entitle rank: ".$rankID." OS : ".$checkOwnSales." PGPSales : ".$pgpSales." TotalGS : ".$totalGroupSales." ActiveLeg : ".$activeLeg." FDEntitle : ".$fristDownlineEntitle." SDEntitle : ".$secDownlineEntitle." .\n");
                        $clientRankArr[$clientID] = $rankID;
                        $rankData[$clientID]['rank_id'] = $rankID;
                        break;
                    }
                }

                $updatePGPSales = $pgpSales - $ownSales;
                $updateGroupSales = $totalGroupSales - $ownSales;
                $updateSponsorSales = 0;
                foreach ($downlineRes as $directDownlineID => $createdAt) {
                    $updateSponsorSales += $clientSalesArr[$directDownlineID];
                }

                $updateSales = array(
                    "own_sales"     => $ownSales,
                    "group_sales"   => $updateGroupSales,
                    "sponsor_sales" => $updateSponsorSales,
                    "pgp_sales"     => $updatePGPSales,
                    "active_leg"    => $activeLeg,
                    "updated_at"    => $dateTime,
                );

                switch ($moduleType) {
                    case 'activate':
                        if(($isActivated) && ($clientID != $realClientID)){
                            $updateSales['active_downline_count'] = $db->inc(1);
                        }
                        break;

                    case 'expired':
                        if((!$isActivated) && ($clientID != $realClientID)){
                            $updateSales['active_downline_count'] = $db->dec(1);
                        }
                        break;
                }

                if($updateSales){
                    $db->where('client_id',$clientID);
                    $db->update("client_sales",$updateSales);
                }

                $db->where('id',$clientID);
                $clientID = $db->getValue('client','sponsor_id');

                unset($allDownlineArr,$fristEntitleArr,$secEntitleArr,$updateSales);
            }

            unset($secDownlineArr,$activeDownlineCount,$downlineRes);
            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write(date("Y-m-d H:i:s") . " Finish Calculate Client Rank. Memory Used: ".$alloc_mem."MB .\n");

            if($clientRankArr){
                $db->where("client_id",array_keys($clientRankArr),"IN");
                $db->where("type", "System");
                $db->where("name", "discountPercentage","!=");
                $db->where("rank_type",$rankType);
                $db->groupBy("client_id");
                $res = $db->get("client_rank",NULL,"client_id, MAX(id) as id");
                foreach($res AS $row){
                    $maxID[$row['id']] = $row['id'];
                }

                if($maxID){
                    $db->where("id", $maxID,"IN");
                    $prevRankAry = $db->map('client_id')->get("client_rank", null, "client_id,rank_id");
                }

                $alloc_mem = round(memory_get_usage() / 1024/1024);
                Log::write(date("Y-m-d H:i:s") . " Start Insert Client Rank. Memory Used: ".$alloc_mem."MB .\n");

                foreach ($clientRankArr as $clientID => $insertRankID) {
                    $lastRank = $prevRankAry[$clientID]; 
                    if($insertRankID == $lastRank) continue;

                    foreach($rankPercentArr[$insertRankID] as $dataName => $dataPercentage){
                            // Skip Discount Percentage, if last rank is more than 0
                            if(($rankPercentArr[$lastRank][$dataName] > 0) && ($dataName == "discountPercentage")) continue;

                            //insert new
                            $insertClientRank = array(
                                'client_id'  => $clientID,
                                'name'       => $dataName, // rank_setting (name) 
                                'rank_id'    => $insertRankID,
                                'value'      => $dataPercentage, // rank_setting (value)  
                                'rank_type'  => $rankType,
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $dateTime,
                            );
                            $db->insert('client_rank', $insertClientRank); 
                    }
                }

                $alloc_mem = round(memory_get_usage() / 1024/1024);
                Log::write(date("Y-m-d H:i:s") . " Finish Insert Client Rank. Memory Used: ".$alloc_mem."MB .\n");
            }

            Log::write(date("Y-m-d H:i:s") . " Completed Client Rank.\n");

            return true;
        }

        public function calculateClientRankOld($clientID, $dateTime, $moduleType){
            $db = MysqliDb::getInstance();
            $rankType   = "Bonus Tier";
            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');
            $date = date('Y-m-d',strtotime($dateTime));
            $realClientID = $clientID;

            if(!$clientID){
                return false;
            }

            //Get Special Calculate Sales Setting
            $db->where('name','spCalSalesDate');
            $spCalSalesRes = $db->getOne('system_settings','value,reference');
            $spSalesFrom = $spCalSalesRes['value'];
            $spSalesPeriod = explode("#", $spCalSalesRes['reference']);

            $startDate = date('Y-m-01',strtotime($dateTime));
            $endDate = $date;

            if((strtotime($date) >= strtotime($spSalesPeriod[0])) && (strtotime($date) <= strtotime($spSalesPeriod[1]))){
                $startDate = $spSalesFrom;
                Log::write(date("Y-m-d H:i:s") . " Special Calculate Sales Period. From ".$startDate." to End ".$endDate."\n");
            }

            if($moduleType){
                $db->where('client_id',$clientID);
                $isActivated = $db->getValue('client_sales','activated');
            }

            //Get Sponsor Group
            $sponsorTreeData = $db->map('client_id')->get('tree_sponsor',null,'client_id,trace_key');

            $db->where('DATE(created_at)',$startDate,">=");
            $db->where('DATE(created_at)',$endDate,"<=");
            $db->where('status','Active');
            $db->groupBy('client_id');
            $clientSalesArr = $db->map('client_id')->get('mlm_client_portfolio',null,'client_id,SUM(bonus_value) as totalSales');

            $db->where('type',$rankType);
            $db->orderBy('priority','DESC');
            $rankIDArr = $db->map('id')->get('rank',null,'id,priority');

            /*Get Rank Setting*/
            if($rankIDArr){
                $db->where('rank_id',array_keys($rankIDArr),"IN");
                $rankRes = $db->get('rank_setting',null,'rank_id,name,value,reference,type');
                foreach ($rankRes as $rankRow) {
                    switch ($rankRow['type']) {
                        case 'purchase':
                            $minRankQualification[$rankRow["rank_id"]][$rankRow["name"]]['value'] = $rankRow["value"];
                            $minRankQualification[$rankRow["rank_id"]][$rankRow["name"]]['reference'] = $rankRow["reference"];

                            if($rankRow['name'] == 'minPGPSales'){
                                $breakOutRank = $rankRow["reference"];
                            }
                            break;
                        case 'percentage':
                        case 'level':
                            $rankPercentArr[$rankRow['rank_id']][$rankRow['name']] = $rankRow['value'];
                            break;
                    }
                }
            }

            $rankData = Bonus::getClientRank($rankType,"",$dateTime,"goldmineBonus","System");

            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write(date("Y-m-d H:i:s") . " Start Calculate Client Rank. Memory Used: ".$alloc_mem."MB .\n");

            while ($clientID) {
                $secDownlineArr = array();
                $activeDownlineCount = 0;
                unset($secDownlineArr,$downlineRes,$pgpSalesArr,$groupSales);

                $db->where('sponsor_id',$clientID);
                $downlineRes = $db->map('id')->get('client',null,'id,active_date');

                if($downlineRes){
                    $db->where('client_id',array_keys($downlineRes),"IN");
                    $db->where('activated',1);
                    $activeDownlineCount = $db->getValue('client_sales',"COUNT(id)");
                }

                $db->where('trace_key',"%".$clientID."/%","LIKE");
                $db->orderBy('level','ASC');
                $allDownlineArr = $db->map('client_id')->get('tree_sponsor',null,'client_id,trace_key');

                foreach ($clientSalesArr as $clientIDSales => $clientSales) {
                    $uplineIDArr = explode("/", $sponsorTreeData[$clientIDSales]);
                    $breakOutFlag = 0;
                    krsort($uplineIDArr);
                    foreach ($uplineIDArr as $uplineID) {
                        $groupSales[$uplineID] += $clientSales;
                        $uplineRankID = $rankData[$uplineID]['rank_id'];

                        if($breakOutFlag) continue;

                        if($rankIDArr[$uplineRankID]>=$breakOutRank){
                            $pgpSalesArr[$uplineID] += $clientSales;
                            $breakOutFlag = 1;
                            continue;
                        }

                        $pgpSalesArr[$uplineID] += $clientSales;
                    }
                }

                foreach ($rankIDArr as $rankID => $rankPriority) {
                    $minOwnSales = $minRankQualification[$rankID]['minOwnSales']['value'];
                    $minPortfolio = $minRankQualification[$rankID]['minOwnSales']['reference']; // Check for Started Pack
                    $minPGPSales = $minRankQualification[$rankID]['minPGPSales']['value'];
                    $minPGPPriority = $minRankQualification[$rankID]['minPGPSales']['reference'];
                    $minGroupSales = $minRankQualification[$rankID]['minGroupSales']['value'];
                    $minActiveLeg = $minRankQualification[$rankID]['minActiveLeg']['value'];
                    $fristDownlinePriority = $minRankQualification[$rankID]['minFirstDownlineRank']['value'];
                    $minFristDownline = $minRankQualification[$rankID]['minFirstDownlineRank']['reference'];
                    $secDownlinePriority = $minRankQualification[$rankID]['minSecDownlineRank']['value'];
                    $minSecDownline = $minRankQualification[$rankID]['minSecDownlineRank']['reference'];

                    $ownSales = 0;
                    $pgpSales = 0;
                    $activeLeg = $activeDownlineCount;
                    $totalGroupSales = 0;
                    $fristDownlineEntitle = 0;
                    $secDownlineEntitle = 0;
                    $countPortfolio = 0;
                    unset($fristEntitleArr,$secEntitleArr);

                    if($fristDownlinePriority > 0 || $secDownlinePriority > 0){
                        foreach ($allDownlineArr as $downlineID => $downlineTraceKey) {
                            $downlineRankID = $rankData[$downlineID]['rank_id'];
                            $uplineArr = explode("/", $downlineTraceKey);

                            if(array_intersect($uplineArr, $fristEntitleArr)){
                                if(!array_intersect($uplineArr, $secEntitleArr)){
                                    if($rankIDArr[$downlineRankID] < $secDownlinePriority) continue;

                                    Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Downline ID : ".$downlineID." Rank : ".$downlineRankID." Last Gen User : ".json_encode(array_intersect($uplineArr, $fristEntitleArr))." Entile Second Gen.\n");
                                    $secEntitleArr[$downlineID] = $downlineID;
                                }
                            }elseif(!$fristEntitleArr[$downlineID]){
                                if($rankIDArr[$downlineRankID] < $fristDownlinePriority) continue;

                                Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Downline ID : ".$downlineID." Rank : ".$downlineRankID." Entile Frist Gen.\n");
                                $fristEntitleArr[$downlineID] = $downlineID;
                            }

                            if((COUNT($fristEntitleArr) >= $minFristDownline) && (COUNT($secEntitleArr) >= $minSecDownline)){
                                Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Hit Requirement. Break.\n");
                                break;
                            }
                        }
                    }

                    if($clientSalesArr[$clientID]) $ownSales = $clientSalesArr[$clientID];
                    if($pgpSalesArr[$clientID]) $pgpSales = $pgpSalesArr[$clientID];
                    if($groupSales[$clientID]) $totalGroupSales = $groupSales[$clientID];

                    $fristDownlineEntitle = COUNT($fristEntitleArr);
                    $secDownlineEntitle = COUNT($secEntitleArr);

                    if($minPortfolio>0){
                        $db->where('client_id',$clientID);
                        $countPortfolio = $db->getValue('mlm_client_portfolio','COUNT(id)');
                        Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." Process Rank: ".$rankID." Portfolio Count : ".$countPortfolio." Min Portfolio Count : ".$minPortfolio.".\n");
                    }

                    if(($ownSales >= $minOwnSales) && ($pgpSales >= $minPGPSales) && ($totalGroupSales >= $minGroupSales) && ($activeLeg >= $minActiveLeg) && ($fristDownlineEntitle >= $minFristDownline) && ($secDownlineEntitle >= $minSecDownline) && ($countPortfolio >= $minPortfolio)){
                        Log::write(date("Y-m-d H:i:s") . " Client ".$clientID." entitle rank: ".$rankID." OS : ".$ownSales." PGPSales : ".$pgpSales." TotalGS : ".$totalGroupSales." ActiveLeg : ".$activeLeg." FDEntitle : ".$fristDownlineEntitle." SDEntitle : ".$secDownlineEntitle." .\n");
                        $clientRankArr[$clientID] = $rankID;
                        $rankData[$clientID]['rank_id'] = $rankID;
                        break;
                    }
                }

                $updatePGPSales = $pgpSales - $ownSales;
                $updateGroupSales = $totalGroupSales - $ownSales;
                $updateSponsorSales = 0;
                foreach ($downlineRes as $directDownlineID => $activateDate) {
                    $updateSponsorSales += $clientSalesArr[$directDownlineID];
                }

                $updateSales = array(
                    "own_sales"     => $ownSales,
                    "group_sales"   => $updateGroupSales,
                    "sponsor_sales" => $updateSponsorSales,
                    "pgp_sales"     => $updatePGPSales,
                );

                switch ($moduleType) {
                    case 'activate':
                        if(($isActivated) && ($clientID != $realClientID)){
                            $updateSales['active_downline_count'] = $db->inc(1);
                        }
                        break;

                    case 'expired':
                        if((!$isActivated) && ($clientID != $realClientID)){
                            $updateSales['active_downline_count'] = $db->dec(1);
                        }
                        break;
                }

                if($updateSales){
                    $db->where('client_id',$clientID);
                    $db->update("client_sales",$updateSales);
                }

                $db->where('id',$clientID);
                $clientID = $db->getValue('client','sponsor_id');

                unset($allDownlineArr,$fristEntitleArr,$secEntitleArr,$updateSales);
            }

            unset($secDownlineArr,$activeDownlineCount,$downlineRes);
            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write(date("Y-m-d H:i:s") . " Finish Calculate Client Rank. Memory Used: ".$alloc_mem."MB .\n");

            if($clientRankArr){
                $db->where("client_id",array_keys($clientRankArr),"IN");
                $db->where("type", "System");
                $db->where("rank_type",$rankType);
                $db->groupBy("client_id");
                $res = $db->get("client_rank",NULL,"client_id, MAX(id) as id");
                foreach($res AS $row){
                    $maxID[$row['id']] = $row['id'];
                }

                if($maxID){
                    $db->where("id", $maxID,"IN");
                    $prevRankAry = $db->map('client_id')->get("client_rank", null, "client_id,rank_id");
                }

                $alloc_mem = round(memory_get_usage() / 1024/1024);
                Log::write(date("Y-m-d H:i:s") . " Start Insert Client Rank. Memory Used: ".$alloc_mem."MB .\n");

                foreach ($clientRankArr as $clientID => $insertRankID) {
                    $lastRank = $prevRankAry[$clientID]; 
                    if($insertRankID == $lastRank) continue;

                    foreach($rankPercentArr[$insertRankID] as $dataName => $dataPercentage){
                            // Skip Discount Percentage, if last rank is more than 0
                            if(($rankPercentArr[$lastRank][$dataName] > 0) && ($dataName == "discountPercentage")) continue;

                            //insert new
                            $insertClientRank = array(
                                'client_id'  => $clientID,
                                'name'       => $dataName, // rank_setting (name) 
                                'rank_id'    => $insertRankID,
                                'value'      => $dataPercentage, // rank_setting (value)  
                                'rank_type'  => $rankType,
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $dateTime,
                            );
                            $db->insert('client_rank', $insertClientRank); 
                    }
                }

                $alloc_mem = round(memory_get_usage() / 1024/1024);
                Log::write(date("Y-m-d H:i:s") . " Finish Insert Client Rank. Memory Used: ".$alloc_mem."MB .\n");
            }

            Log::write(date("Y-m-d H:i:s") . " Completed Client Rank.\n");

            return true;
        }

        public function updateClientSales($clientID,$sponsorID,$bonusValue,$moduleType,$dateTime){
            $db = MysqliDb::getInstance();
            $rankType   = "Bonus Tier";
            // Control this function only for Registration use for insert new rows
            unset($bonusValue);
            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');

            if(!$clientID){
                return false;
            }

            unset($insertSalesData,$notDirectUpline,$isActiveFlag);
            //Check Client is Active Leg

            if(!$sponsorID){
                $db->where('id',$clientID);
                $clientData = $db->getOne('client','sponsor_id');
                $sponsorID  = $clientData['sponsor_id'];
                if(!$sponsorID) return false;
            }

            $db->where('client_id',$clientID);
            $clientSalesData = $db->getOne('client_sales','id,activated');
            $salesID = $clientSalesData['id'];
            $isActiveFlag = $clientSalesData['activated'];
            if(!$salesID){
                $insertSalesData = array(
                    "client_id" => $clientID,
                    "sponsor_id"=> $sponsorID,
                    "own_sales" => $bonusValue,
                    "updated_at"=> $dateTime,
                );
                $db->insert('client_sales',$insertSalesData);
            }else{
                if($bonusValue > 0){
                    $db->where('id',$salesID);
                    $db->update('client_sales',array("own_sales"=>$db->inc($bonusValue),"updated_at"=>$dateTime));
                }
            }

            while ($sponsorID) {
                unset($updateData);

                $db->where('client_id',$sponsorID);
                $uplineSalesRes = $db->getOne('client_sales','id,sponsor_id');
                $uplineSalesID = $uplineSalesRes['id'];
                $sponsorID = $uplineSalesRes['sponsor_id'];

                switch ($moduleType) {
                    case 'register':
                        $updateData['downline_count'] = $db->inc(1);
                        break;

                    case 'activate':
                        if($isActiveFlag){
                            $updateData['active_downline_count'] = $db->inc(1);
                        }
                        break;

                    case 'expired':
                        if(!$isActiveFlag){
                            $updateData['active_downline_count'] = $db->dec(1);
                        }
                        break;
                }

                if($bonusValue > 0){
                    $updateData['group_sales'] = $db->inc($bonusValue);

                    if(!$notDirectUpline){
                        $updateData['sponsor_sales'] = $db->inc($bonusValue);
                    }
                }

                $updateData['updated_at'] = $dateTime;

                $db->where('id',$uplineSalesID);
                $db->update('client_sales',$updateData);

                $notDirectUpline = 1;
            }
            
            return true;
        }

        public function moveClientSales($clientID,$moveType,$dateTime){
            $db = MysqliDb::getInstance();
            $rankType   = "Bonus Tier";
            $validType = array('decrease','increase');
            unset($isBreakOutFlag);
            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');

            if(!$clientID){
                return false;
            }

            if(!in_array($moveType, $validType)){
                return false;
            }

            $db->where('client_id',$clientID);
            $clientSalesRes = $db->getOne('client_sales','activated,downline_count,active_downline_count,own_sales,group_sales,pgp_sales');

            $uplineIDArray = Tree::getSponsorUplineByClientID($clientID,false);

            if($uplineIDArray){
                $rankData = Bonus::getClientRank($rankType,$uplineIDArray,$dateTime,"goldmineBonus","System");
            }

            $db->where('type',$rankType);
            $rankIDArr = $db->map('id')->get('rank',null,'id,priority');

            $db->where('rank_id',$rankIDArr,"IN");
            $db->where('name','minPGPSales');
            $db->where('type','purchase');
            $breakOutRank = $db->getValue('rank_setting','reference');

            foreach ($uplineIDArray as $uplineID) {
                $rankID = $rankData[$uplineID]['rank_id'];

                unset($updateData);
                switch ($moveType) {
                    case 'decrease':
                        $gs = $clientSalesRes['group_sales'] + $clientSalesRes['own_sales'];
                        $pgps = $clientSalesRes['pgp_sales'] + $clientSalesRes['own_sales'];
                        $actDownline = $clientSalesRes['active_downline_count'];
                        $downline = $clientSalesRes['downline_count'] + 1;
                        if($clientSalesRes['activated'] == 1){
                            $actDownline  = $actDownline + 1;
                        }

                        $updateData['group_sales'] = $db->dec($gs);
                        if(!$notDirectFlag){
                            $updateData['sponsor_sales'] = $db->dec($clientSalesRes['own_sales']);
                        }
                        if(!$isBreakOutFlag){
                            $updateData['pgp_sales'] = $db->dec($pgps);
                        }
                        $updateData['active_downline_count'] = $db->dec($actDownline);
                        $updateData['downline_count'] = $db->dec($downline);
                        break;

                    case 'increase':
                        $gs = $clientSalesRes['group_sales'] + $clientSalesRes['own_sales'];
                        $pgps = $clientSalesRes['pgp_sales'] + $clientSalesRes['own_sales'];
                        $actDownline = $clientSalesRes['active_downline_count'];
                        $downline = $clientSalesRes['downline_count'] + 1;
                        if($clientSalesRes['activated'] == 1){
                            $actDownline  = $actDownline + 1;
                        }

                        $updateData['group_sales'] = $db->inc($gs);
                        if(!$notDirectFlag){
                            $updateData['sponsor_sales'] = $db->inc($clientSalesRes['own_sales']);
                        }
                        if(!$isBreakOutFlag){
                            $updateData['pgp_sales'] = $db->inc($pgps);
                        }
                        $updateData['active_downline_count'] = $db->inc($actDownline);
                        $updateData['downline_count'] = $db->inc($downline);
                        break;
                }

                if($updateData){
                    $updateData['updated_at'] = $dateTime;
                    $db->where('client_id',$uplineID);
                    $db->update('client_sales',$updateData);
                }

                if(!$isBreakOutFlag && ($rankIDArr[$rankID] >= $breakOutRank)){
                    $isBreakOutFlag = 1;
                }

                $notDirectFlag = 1;
            }            
            return true;
        }

        public function resetAwardCycle($bonusDate){
            $db = MysqliDb::getInstance();

            $db->where('name','awardCycleDuration');
            $awardCycleDuration = $db->getValue('system_settings','value');

            $resetDate = date('Y-m-d',strtotime($bonusDate." +1 days"));
            $expiredDate = date('Y-m-d',strtotime($resetDate." - ".$awardCycleDuration));
            $dateTime = date('Y-m-d H:i:s');

            Log::write(date("Y-m-d H:i:s")." Reset Award Cycle Date before ".$expiredDate." to ".$resetDate."\n");

            $db->where('name','awardCycleDate');
            $db->where('DATE(value)',$expiredDate);
            $db->update('client_setting',array("value"=>$resetDate,"type"=>"0","reference"=>"0"));
            return true;
        }

        public function maintainActiveMember($clientID, $bonusValue, $dateTime){
            $db = MysqliDb::getInstance();

            if(!$clientID) return false;
            if($bonusValue <= 0) return false;

            $db->where('name', 'stayActivePVP');
            $maintainPVPRes = $db->getOne('system_settings', 'value, reference');
            $maintainPVP = $maintainPVPRes['value'];
            $activePeriod = $maintainPVPRes['reference'];

            if($bonusValue < $maintainPVP){
                return false;
            }

            $isActivated = false;
            $db->where('client_id', $clientID);
            $clientActivatedStatus = $db->getValue('client_sales', 'activated');
            if($clientActivatedStatus == 0){
                $db->where('client_id', $clientID);
                $db->update("client_sales", array('activated' => 1));    
                $isActivated = true;
            }

            $db->where('id', $clientID);
            $db->update('client', array('active_date' => $dateTime));

            return $isActivated;
        }

        public function expiredMemberActiveStatus($bonusDate){
            $db = MysqliDb::getInstance();
            $bonusName = "ExpiredActiveStatus";
            
            Log::write(date("Y-m-d H:i:s") . " Start Expired Member Status\n");
            if (!$bonusDate) {
                Log::write(date("Y-m-d H:i:s") . " Invalid Date: " . $bonusDate . "Please check your date and run again.\n");
                return false;
            }

            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $bonusDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($count > 0){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been calculate.\n");
                return false;
            }

            $batchID = Bonus::insertBonusCalculationBatch($bonusName, $bonusDate);

            $db->where('name', 'stayActivePVP');
            $activeSettings = $db->getOne('system_settings', 'value, reference');
            $activePeriod = $activeSettings['reference'];

            $currentDateTime = date("Y-m-d 00:00:00", strtotime($bonusDate." +1 day"));
            $expiredActivedDate = date("Y-m-d", strtotime($currentDateTime." -".$activePeriod));

            $db->join('client_sales b','b.client_id = a.id');
            $db->where('b.activated', '1');
            $db->where('DATE(a.active_date)', $expiredActivedDate, '<=');
            $db->orderBy('a.id', 'ASC');
            $inactiveMemberAry = $db->get('client a', null, 'a.id as client_id, b.activated');
            if(!$inactiveMemberAry){
                Bonus::insertBonusCalculationBatch($bonusName, $bonusDate, 1);
                Bonus::insertBonusCalculationBatch($bonusName, $bonusDate, "" , "",1);
                Log::write(date("Y-m-d H:i:s") . " No inactive member found. Complete Inactive Member " . $bonusDate . " Process\n");
                return true;
            }

            foreach ($inactiveMemberAry as $memberData) {
                $clientID = $memberData['client_id'];
                Log::write(date("Y-m-d H:i:s") . " Inactive cID: $clientID\n");

                // Inactive client status
                $db->where('client_id', $clientID);
                $db->update('client_sales', array('activated' => 0));

                // Insert Update Rank Queue
                unset($insertData, $jsonData);
                $jsonData['dateTime'] = $currentDateTime;
                $jsonData['moduleType'] = "expired";
                $insertData = array(
                    "queue_type" => "calculateRank",
                    "client_id"  => $clientID,
                    "data"       => json_encode($jsonData),
                    "created_at" => date('Y-m-d H:i:s'),
                );
                $db->insert('queue',$insertData);
            }

            Bonus::insertBonusCalculationBatch($bonusName, $bonusDate, 1);
            Bonus::insertBonusCalculationBatch($bonusName, $bonusDate, "" , "",1);
            Log::write(date("Y-m-d H:i:s") . " Complete Inactive Member " . $bonusDate . " Process\n");
            return true;
        }

        public function getCVRate($dateTime,$countryID){
            $db = MysqliDb::getInstance();

            if($countryID)$db->where('country_id',$countryID);
            $db->where('actived_at',$dateTime,"<=");
            $db->orderBy('created_at','ASC');
            $cvRateArr = $db->map('country_id')->get('cv_rate',null,'country_id,rate');

            if($countryID){
                $cvRate = $cvRateArr[$countryID]?$cvRateArr[$countryID]:1;
            }else{
                $cvRate = $cvRateArr;
            }
            
            return $cvRate;
        }

        public function updateMemberDiscountPerc($date) {
            $db = MysqliDb::getInstance();
            
            $searchMonth = date('n',strtotime("-1 days", strtotime($date)));
            $searchYear = date('Y',strtotime("-1 days", strtotime($date)));
            $clientRankArr = Bonus::getClientRank("Bonus Tier", "", $date, "discount");

            foreach ($clientRankArr as $key => $value) {
                $clientIDAry[$key] = $key;
            }

            $db->where('MONTH(created_at)', $searchMonth);
            $db->where('YEAR(created_at)', $searchYear);
            $db->groupBy('client_id');
            $bonusThisMonth = $db->map('client_id')->get('mlm_client_portfolio', null, 'client_id, sum(bonus_value) as bonus_value');

            foreach ($clientRankArr as $key => $value) {
                // only check fizEntreprenuer, fizExecutive, fizDirector, fizUnicorn
                if($value['rank_id'] >= 2){
                    unset($supposePerc);
                    // check 300 PVP
                    $pvp = $bonusThisMonth[$key];
                    if($pvp >= 300){
                        $supposePerc = 30;
                    } else {
                        $supposePerc = 25;
                    }

                    if($supposePerc){
                        if($value['percentage'] != $supposePerc){
                            //insert new
                            $insertClientRank = array(
                                'client_id'  => $key,
                                'name'       => "discountPercentage", // rank_setting (name) 
                                'rank_id'    => $value['rank_id'],
                                'value'      => $supposePerc, // rank_setting (value)  
                                'rank_type'  => "Bonus Tier",
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $date,
                            );
                            $db->insert('client_rank', $insertClientRank); 

                            Log::write(date("Y-m-d H:i:s")." Update ".$key." disc perc from ".$value['percentage']." to ".$supposePerc.".\n");
                        }
                    }
                }
            }
            return true;
        }

        public function calculateRecruitPromo($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = "Recruit Promo";
            $insertDate = date('Y-m-d 23:59:59',strtotime($bonusDate));
            $payDate    = date('Y-m-d',strtotime($bonusDate." +1 days"));
            $monthFirstDate = date('Y-m-01',strtotime($bonusDate));

            if(!$bonusDate){
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " Invalid Bonus Date. Failed to calculate.\n");
                return false;
            }

            $db->where("bonus_name",$bonusName);
            $db->where("bonus_date",$bonusDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(ID)");
            if($count > 0) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " has been calculate for ".$bonusDate.". Failed to calculate.\n");
                return false;
            }

            $db->where('type','Recruit Promo Setting');
            $promoStgRes = $db->map('name')->get('system_settings',null,'name,value,reference');
            $promoStart = $promoStgRes['promoPeriod']['value'];
            $promoEnd   = $promoStgRes['promoPeriod']['reference'];

            if((strtotime($bonusDate) < strtotime($promoStart)) || (strtotime($bonusDate) > strtotime($promoEnd))){
                Log::write(date("Y-m-d H:i:s")." ".$bonusDate . " not under promo date. Failed to calculate.\n");
                return false;
            }

            $clientDataAry = Bonus::$clientDataAry;
            $directorID = Bonus::$directorID;

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = Bonus::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            if(strtotime(date('Y-m-t',strtotime($bonusDate))) != strtotime($bonusDate)){
                Log::write(date("Y-m-d H:i:s") . " ".$bonusDate." not last day of month. Failed to Proceed.\n");
                Bonus::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                return false;
            }

            $promoReward        = $promoStgRes['promoReward']['value'];
            $PromoRewardDivide  = $promoStgRes['promoReward']['reference'];
            $minDownlineSales = $promoStgRes['promoConditions']['reference'];
            $minOwnSales      = $promoStgRes['promoConditions']['value'];

            $db->where('DATE(created_at)',$monthFirstDate,">=");
            $db->where('DATE(created_at)',$bonusDate,"<=");
            $clientIDArr = $db->map('id')->get('client',null,'id');

            unset($validDownlineArr,$totalSalesArr);
            //Get Client Sales
            $db->where('DATE(updated_at)',$monthFirstDate,">=");
            $db->where('DATE(updated_at)',$bonusDate,"<=");
            $clientSalesArr = $db->map('client_id')->get('client_sales_cache',null,'client_id,own_sales');
            foreach ($clientSalesArr as $clientID => $clientSales) {
                $uplineID = $clientDataAry[$clientID]['sponsor_id'];
                if($clientSales >= $minDownlineSales && $clientIDArr[$clientID]){
                    $validDownlineArr[$uplineID][$clientID] = $clientID;
                    $totalSalesArr[$uplineID] += $clientSales;
                }
            }

            foreach ($validDownlineArr as $clientID => $downlineArr) {
                $validDownline = COUNT($downlineArr);
                $ownSales = $clientSalesArr[$clientID]?$clientSalesArr[$clientID]:0;
                $totalSales = $totalSalesArr[$clientID];

                if($clientID == $directorID) continue;

                if($ownSales < $minOwnSales){
                    Log::write(date("Y-m-d H:i:s") . " Client ID : ".$clientID." OS : ".$ownSales." Min OS : ".$minOwnSales.". Skip..\n");
                    continue;
                }

                $multiplierReward = floor($validDownline/$PromoRewardDivide);
                $payableAmt = Setting::setDecimal(($promoReward * $multiplierReward));
                Log::write(date("Y-m-d H:i:s") . " Client ID : ".$clientID." Multiplier Reward : floor(".$validDownline." / ".$PromoRewardDivide.") x ".$promoReward." = ".$payableAmt.".\n");

                if($payableAmt <= 0) continue;
                
                unset($insertData);
                $insertData = array(
                    "type"          => "Recruit Promo",
                    "client_id"     => $clientID,
                    "from_amount"   => $totalSales,
                    "amount"        => $payableAmt,
                    "batch_id"      => $batchID,
                    "data"          => json_encode($downlineArr),
                    "created_at"    => $payDate
                );
                $db->insert('mlm_promo',$insertData);

                $clientBonusArray[$clientID] += $payableAmt;
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                    "client_id"         => $clientID,
                    "country_id"        => $clientDataAry[$clientID]["country_id"],
                    "bonus_date"        => $bonusDate,
                    "bonus_type"        => "recruitPromo",
                    "bonus_amount"      => $totalAmount,
                );
                $db->insert("mlm_bonus_report", $insertData);
            }

            if (Bonus::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

        public function getRecruitPromoReport($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];

            $dateFormat     = Setting::$systemSetting["systemDateTimeFormat"];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            $userID = $db->userID;
            $site = $db->userType;

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                    
                                $db->where('Date(created_at)', date('Y-m-d', $dateFrom), '>=');
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

                                $db->where('Date(created_at)', date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'fullName':
                            if($dataType == "like"){
                                $sq = $db->subQuery();
                                $sq->where("name","%".$dataValue."%","LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }else{
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where('client_id', $userID);
            }
            $copyDb = $db->copy();
            $db->orderBy('created_at', 'DESC');
            $recruitRes = $db->get('mlm_promo', $limit, 'id, created_at, client_id, data, from_amount, amount');

            if(empty($recruitRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            $totalRecord = $copyDb->getValue('mlm_promo', 'COUNT(*)');

            foreach($recruitRes as $getID){
                $totalID[$getID['client_id']] = $getID['client_id'];
            }

            if($totalID){
                $db->where('id', $totalID, 'IN');
                $getName = $db->map('id')->get('client', null, 'id, member_id, name');

                $db->groupBy('client_id');
                $db->where('client_id', $totalID, 'IN');
                $getCityID = $db->map('client_id')->get('address',null,'client_id,city_id');

                foreach($getCityID as $getCityIDRow){
                    $cityIDAry[$getCityIDRow] = $getCityIDRow;
                }
            }

            if($cityIDAry){
                $db->where('id',$cityIDAry,'IN');
                $getCityName = $db->map('id')->get('city',null,'id, name');
            }

            foreach($recruitRes as $getRow){
                $recruitRecord['id'] = $getRow['id'];
                $recruitRecord['date'] = date($dateFormat, strtotime($getRow['created_at']));
                if($site == 'Admin'){
                    $recruitRecord['memberID'] = $getName[$getRow['client_id']]['member_id'];
                    $recruitRecord['fullName'] = $getName[$getRow['client_id']]['name'];
                    $recruitRecord['cityName'] = $getCityName[$getCityID[$getRow['client_id']]]?:'-';
                }
                $recruitRecord['totalNumberOfDirectDownline'] = count(json_decode($getRow['data'], true));
                $recruitRecord['totalPVP'] = $getRow['from_amount'];
                $recruitRecord['bonusPayout'] = $getRow['amount'];

                $totalRecruitRecord[] = $recruitRecord;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }
            
            $data['recruitPromoReport'] = $totalRecruitRecord;
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

        public function getRecruitPromoDetails($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateFormat     = Setting::$systemSetting["systemDateFormat"];

            $id = $params['id'];

            if(empty($id)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => '');
            }

            $db->where('id', $id);
            $recruitRes = $db->getOne('mlm_promo', 'data, DATE(created_at) as createdAt');

            if(empty($recruitRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }
            
            $getID = json_decode($recruitRes['data'],true);

            if($getID){ 
                $db->where('id', $getID, 'IN');
                $getName = $db->map('id')->get('client', null, 'id, member_id, name');
            }

            $previousDate = date('Y-m-d', strtotime($recruitRes['createdAt'].'- 1 days'));

            $db->where('client_id', $getID, 'IN');
            $db->where('DATE(updated_at)', $previousDate);
            $getPVP = $db->map('client_id')->get('client_monthly_sales', null, 'client_id, own_sales');

            foreach($getID as $value){
                $detail['fromMemberID'] = $getName[$value]['member_id'];
                $detail['fullname'] = $getName[$value]['name'];
                $detail['PVP'] = $getPVP[$value]?:'-';

                $allRecord[] = $detail;
            }

            $data['allRecord']      = $allRecord;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function callNicepayPaymentGateway($params, $ip, $sessionID){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $merchantKey    = Setting::$configArray['nicepayMerchantKey'];
            $iMid           = Setting::$configArray['nicepayIMID'];
            $nicepayDomain  = Setting::$configArray['nicepayURL'];
            $memberSiteURL  = Setting::$configArray['memberSite'];

            $userAgent      = General::$userAgent;
            $userIP         = General::$ip;
            $clientID       = $db->userID;
            $site           = $db->userType;

            $dateTime = date("Y-m-d H:i:s");
            $tsTrxn   = date("YmdHis", strtotime($dateTime));

            $makePaymentMethod      = trim($params["makePaymentMethod"]);

            switch ($makePaymentMethod) {
                case 'nicepay':
                    $payMethod = '02';
                    $pendingPaymentType = 'nicepayVirtualAccount';
                    $refNo = "ORD".$tsTrxn;
                    $url = $nicepayDomain."nicepay/direct/v2/registration";
                    break;

                case 'creditCard':
                    $payMethod = '00';
                    $pendingPaymentType = 'nicepayCreditCard';
                    $refNo = $iMid.$tsTrxn;
                    $url = $nicepayDomain."nicepay/redirect/v2/registration";
                    break;
                
                default:
                    return array("status" => "error", "code" => 2, "statusMsg" => "Invalid Payment Method", "data" => $makePaymentMethod);
                    break;
            }

            $submitCreditCard = $params["submitCreditCard"];
            $nicepayTxId = $params["nicepayTxId"];
            if($submitCreditCard == true && !$nicepayTxId){
                return array("status" => "error", "code" => 2, "statusMsg" => "Invalid Payment Data", "data" => "");
            }


            $params["step"]         = 5;
            $verificationReturn     = Inventory::purchasePackageVerification($params);
            if($verificationReturn["status"] != "ok"){
                return $verificationReturn;
            }

            $packageAry             = $verificationReturn["data"]["packageAry"];
            $packageData            = $verificationReturn["data"]["packageData"];
            $totalPrice             = $verificationReturn["data"]["totalPrice"];
            $deliveryAddressID      = $verificationReturn["data"]["deliveryAddressID"];
            $billingAddressID       = $verificationReturn["data"]["billingAddressID"];
            $nicepayBankCode        = $verificationReturn["data"]["nicepayBankCode"];
            $clientMemberID         = $verificationReturn["data"]["clientMemberID"];
            $clientEmail            = $verificationReturn["data"]["clientEmail"];
            $taxPercentage          = $verificationReturn["data"]["taxPercentage"];
            $taxes                  = $verificationReturn["data"]["taxes"];
            $shippingFee            = $verificationReturn["data"]["shippingFee"];
            $discountAmount         = $verificationReturn["data"]["discountAmount"];
            $insuranceTaxes           = $verificationReturn["data"]["insuranceTaxes"];


            $db->startTransaction();

            foreach($packageAry as $packageRow) {
                $packageIDRes[$packageRow['packageID']] = $packageRow['packageID'];
            }
            $db->where('id',$packageIDRes,"IN");
            $db->where('status',"Active");
            $packageRes = $db->setQueryOption("FOR UPDATE")->get("mlm_product", null,"total_sold, total_balance, total_holding, is_unlimited, status");
            if(COUNT($packageRes) != COUNT($packageIDRes)){
                $db->rollback();
                $db->commit();
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01088'][$language] /* Insufficient Package. */, 'data'=>"");
            }

            foreach ($packageRes as $packageBal) {
                if($packageBal['is_unlimited']) continue;
                
                if($packageBal['total_balance'] <= ($packageBal['total_sold'] + $packageBal['total_holding'])){
                    $db->rollback();
                    $db->commit();
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01088'][$language] /* Insufficient Package. */, 'data'=>"");
                }
            } 

            $totalAmount = ceil($totalPrice);

            $db->where('id', $billingAddressID);
            $billingData = $db->getOne('address', 'name, email, phone, address, (SELECT name FROM city WHERE city.id = city_id) AS city_name, (SELECT name FROM state WHERE state.id = state_id) state_name, (SELECT name FROM country WHERE country.id = country_id) AS country_name, (SELECT name FROM zip_code WHERE zip_code.id = post_code_id) AS post_code');

            $tmpPackageName = "";
            $packageTotalPrice = 0;
            foreach ($packageAry as $packageInfo) {

                unset($tempItem);
                // $tempItem["img_url"]        = $packageData[$packageInfo['packageID']]['Image'];
                $tempItem["goods_detail"]   = (String) $packageData[$packageInfo['packageID']]['bonusValue'];
                $tempItem["goods_name"]     = $packageData[$packageInfo['packageID']]['name'];
                
                $packagePrice               = $packageInfo['packagePrice'];
                $pricePerUnit = bcdiv($packagePrice, $packageInfo['quantity'], 2);

                $tempItem["goods_amt"]      = (String) ceil($packagePrice);
                $tempItem["goods_quantity"] = "1";//(String) $packageInfo['quantity'];
                // $cartData["item"][]         = $tempItem;

                $packageTotalPrice += $packagePrice;

                if($tmpPackageName!="") $tmpPackageName .= ", ";
                $tmpPackageName .= $packageData[$packageInfo['packageID']]['name']." (Rp.".$pricePerUnit." x ".$packageInfo['quantity'].") ";
            }

            if($taxes > 0){
                $tempTax["goods_detail"]   = (String) "Tax With $taxPercentage%";
                $tempTax["goods_name"]     = "Taxes Amount";
                $tempTax["goods_amt"]      = (String) ceil($taxes);
                $tempTax["goods_quantity"] = "1";
                // $cartData["item"][]        = $tempTax;

                $packageTotalPrice += $taxes;

                if($tmpPackageName!="") $tmpPackageName .= ", ";
                $tmpPackageName .= "Tax With $taxPercentage%" ." (Rp.".$taxes." x 1)";

            }

            if($shippingFee > 0){
                $tempShipping["goods_detail"]   = (String) "Shipping Fee";
                $tempShipping["goods_name"]     = "Shipping Amount";
                $tempShipping["goods_amt"]      = (String) ceil($shippingFee);
                $tempShipping["goods_quantity"] = "1";
                // $cartData["item"][]             = $tempShipping;

                $packageTotalPrice += $shippingFee;

                if($tmpPackageName!="") $tmpPackageName .= ", ";
                $tmpPackageName .= "Shipping Fee" ." (Rp.".$shippingFee." x 1)";
            }

            //insuranceTax
            if($insuranceTaxes > 0){
                $tempInsurance["goods_detail"]   = (String) "Insurance Tax";
                $tempInsurance["goods_name"]     = "Insurance Tax Amount";
                $tempInsurance["goods_amt"]      = "-".(String) ceil($insuranceTaxes);
                $tempInsurance["goods_quantity"] = "1";
                // $cartData["item"][]             = $tempShipping;

                $packageTotalPrice += $insuranceTaxes;

                if($tmpPackageName!="") $tmpPackageName .= ", ";
                $tmpPackageName .= "Insurance Charge With $insuranceTaxes" ." (Rp.".$insuranceTaxes." x 1)";
            }

            //voucherCode
            if($discountAmount > 0){
                $tempShipping["goods_detail"]   = (String) "Discount";
                $tempShipping["goods_name"]     = "Discount Amount";
                $tempShipping["goods_amt"]      = "-".(String) ceil($discountAmount);
                $tempShipping["goods_quantity"] = "1";
                // $cartData["item"][]             = $tempShipping;

                $packageTotalPrice -= $discountAmount;

                if($tmpPackageName!="") $tmpPackageName .= ", ";
                $tmpPackageName .= "Discount" ." (-Rp.".$discountAmount." x 1)";
            }

            //summary
            $summaryCart["goods_detail"]   = (String) $tmpPackageName;
            $summaryCart["goods_name"]     = "Purchase Package";
            $summaryCart["goods_amt"]      = $packageTotalPrice;
            $summaryCart["goods_quantity"] = "1";
            $cartData["item"][]             = $summaryCart;


            if($totalAmount < $packageTotalPrice){
                $totalAmount = ceil($packageTotalPrice);
            }
            
            $merchantData = $tsTrxn.$iMid.$refNo.$totalAmount.$merchantKey;
            $merTok = hash("sha256", $merchantData);

            $cartData["count"] = (String) count($cartData["item"]);

            $db->where('name', 'nicepaySetting');
            $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
            $nicepayExpired = explode('#', $nicepaySetting['type']);
            $nicepayExpiredPeriod = $nicepayExpired[0];
            $systemExpiredPeriod  = $nicepayExpired[1];

            $trxnValidDateTime = date("Y-m-d H:i:s", strtotime($dateTime." +".$nicepayExpiredPeriod));
            $trxnValidDate = date("Ymd", strtotime($trxnValidDateTime));
            $trxnValidTime = date("His", strtotime($trxnValidDateTime));
            $trxnSystemExpiredAt = date("Y-m-d H:i:s", strtotime($dateTime." +".$systemExpiredPeriod));

            $postParams["timeStamp"]        = $tsTrxn;
            $postParams["iMid"]             = $iMid;
            $postParams["payMethod"]        = $payMethod;
            $postParams["currency"]         = "IDR";
            $postParams["amt"]              = $totalAmount;
            $postParams["referenceNo"]      = $refNo;
            $postParams["goodsNm"]          = "Meta-Fiz";
            $postParams["billingNm"]        = $billingData['name'] ?: '-';
            $postParams["billingPhone"]     = $billingData['phone'] ?: '-';
            $postParams["billingEmail"]     = $billingData['email'] ?: $clientEmail;
            $postParams["billingAddr"]      = $billingData['address'] ?: '-';
            $postParams["billingCity"]      = $billingData['city_name'] ?: '-';
            $postParams["billingState"]     = $billingData['state_name'] ?: '-';
            $postParams["billingPostCd"]    = $billingData['post_code'] ?: '-';
            $postParams["billingCountry"]   = $billingData['country_name'] ?: '-';
            $postParams["dbProcessUrl"]     = $memberSiteURL."nicepayCallback";
            $postParams["merchantToken"]    = $merTok;
            $postParams["cartData"]         = json_encode($cartData);
            $postParams["recurrOpt"]        = "0";

            switch ($makePaymentMethod) {
                case 'nicepay':
                    $postParams["vacctValidDt"]     = $trxnValidDate;
                    $postParams["vacctValidTm"]     = $trxnValidTime;
                    $postParams["merFixAcctId"]     = $clientMemberID;
                    $postParams["bankCd"]           = $nicepayBankCode;
                    break;

                case 'creditCard':
                    $postParams["userIP"]        = $ip ?: "127.0.0.1";
                    $postParams["userSessionID"] = $sessionID;
                    $postParams["userAgent"]     = $userAgent;
                    $postParams["instmntType"]   = "2";
                    $postParams["instmntMon"]    = "1";
                    $postParams["callBackUrl"]   = $memberSiteURL."paymentComplete";
                    break;
                
                default:
                    break;
            }

            try{

                $postParams = json_encode($postParams);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams); // $response->setBody()
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $jsonResponse = curl_exec($ch);

                $result = json_decode($jsonResponse, true);

                if($result['resultCd'] != "0000" && strtolower($result['resultMsg']) != "success"){
                    $db->rollback();
                    $db->commit();

                    $insertPendingPayment = array(
                        'type'          => $pendingPaymentType,
                        'client_id'     => $clientID,
                        'merchant_token'=> $merTok,
                        'data'          => json_encode($params),
                        'data_in'       => $postParams,
                        'data_out'      => $jsonResponse,
                        'amount'        => $totalPrice,
                        'reference_no'  => $refNo,
                        'status'        => 'Failed',
                        'created_at'    => $dateTime,
                        'expired_at'    => $trxnSystemExpiredAt,
                        'error_msg'     => $result['resultMsg'],
                    );

                    $db->insert('mlm_pending_payment', $insertPendingPayment);

                    return array('status' => "error", 'code' => 2, 'statusMsg' => 'Failed Make Payment via Virtual Account', 'data'=>"");
                }

                $insertPendingPayment = array(
                    'type'              => $pendingPaymentType,
                    'client_id'         => $clientID,
                    'data'              => json_encode($params),
                    'data_in'           => $postParams,
                    'data_out'          => $jsonResponse,
                    'tx_id'             => $result['tXid'],
                    'merchant_token'    => $merTok,
                    'amount'            => $totalPrice,
                    'discount_amount'   => $discountAmount,
                    'currency'          => 'IDR',
                    'bank_code'         => $nicepayBankCode,
                    'vacct_no'          => $result['vacctNo'],
                    'reference_no'      => $refNo,
                    'status'            => 'Pending',
                    'created_at'        => $dateTime,
                    'expired_at'        => $trxnSystemExpiredAt,
                    'error_msg'         => '',
                    'updated_at'        => date("Y-m-d H:i:s"),
                );
                $recordID = $db->insert('mlm_pending_payment', $insertPendingPayment);

                foreach ($packageAry as $packageInfo) {
                    $updateData = array(
                        'total_holding' => $db->inc($packageInfo['quantity'])
                    );
                    $db->where('id', $packageInfo['packageID']);
                    $db->update('mlm_product', $updateData);

                    $packageIDAry[$packageInfo['packageID']] = $packageInfo['packageID'];
                }

                $db->where('mlm_product_id', $packageIDAry, 'IN');
                $db->where('client_id', $clientID);
                $db->delete('shopping_cart');

                $db->where('client_id', $clientID);
                $db->delete('session_data');

            } catch(Exception $e){
                $db->rollback();
                $db->commit();
                return array("status" => "error", "code" => 2, "statusMsg" => "System Error", "data" => $e);
            }

            $db->commit();

            switch ($makePaymentMethod) {
                case 'nicepay':
                    $db->where('name', 'nicepaySetting');
                    $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
                    $nicepayBankAry = json_decode($nicepaySetting['description'], true);

                    $data['vacctNo'] = $result['vacctNo'];
                    $data['bank'] = $nicepayBankAry[$nicepayBankCode];
                    $data['txid'] = $result['tXid'];
                    $data['amount'] = $totalPrice;
                    $data['vacctvalidtm'] = $trxnValidTime;
                    break;

                case 'creditCard':
                    $merTok = hash("sha256", $merchantData);
                    $encodeData = array(
                        "tXid"            =>   $result['tXid'],
                    );
                    foreach ($encodeData as $postKey => $postValue) {
                        $encodeRow[] = $postKey."=".$postValue;
                    }

                    $encodeData = implode("&", $encodeRow);
                    $redirectURL = $nicepayDomain."nicepay/redirect/v2/payment?".$encodeData;

                    $data['redirectURL'] = $redirectURL;
                    $data['record_id'] = $recordID;

                    break;
                
                default:
                    break;
            }


            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function nicepayCallback($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $txID           = trim($params["txID"]);
            $referenceNo    = trim($params["referenceNo"]);
            $paymentAmount  = trim($params["amount"]);
            $token          = trim($params["token"]);
            $returnStatus   = trim($params["returnStatus"]);
            $bankCode       = trim($params["bankCode"]);
            $bankValidNo    = trim($params["bankValidNo"]);
            $depositDate    = trim($params["depositDate"]);
            $depositTime    = trim($params["depositTime"]);
            $mitraCd        = trim($params["mitraCd"]);
            $paymentResult  = trim($params["paymentResult"]);
            $callbackData   = trim($params["callbackData"]);

            $dateTime       = date("Y-m-d H:i:s");

            $db->startTransaction();

            $db->where('tx_id', $txID);
            // $db->where('merchant_token', $token);
            $db->where('status', array("Pending", "Matured"), "IN");
            $pendingPayment = $db->setQueryOption("FOR UPDATE")->getOne("mlm_pending_payment", null, "id, client_id, data, amount, expired_at");
            if(!$pendingPayment){
                $db->rollback();
                $db->commit();
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Pending Payment Not Found', 'data'=> "");
            }

            $expiredDateTime = date('YmdHis', strtotime($pendingPayment['expired_at']));
            $compareDateTime = date('YmdHis', strtotime($dateTime));// $depositDate.$depositTime;
            if($expiredDateTime < $compareDateTime){
                $db->rollback();
                $db->commit();

                $db->where('name', 'creditSales');
                $db->where('type', 'Internal');
                $internalID = $db->getValue('client', 'id');

                $batchID = $db->getNewID();
                $belongID = $batchID;

                // Cash::$creatorID = $pendingPayment['client_id'];
                Cash::$creatorType = "System";
                Cash::insertTAccount($internalID, $pendingPayment['client_id'], 'mfizDef', $paymentAmount, 'Nicepay Expired Trxn', $belongID, "", $dateTime, $batchID, $pendingPayment['client_id'], $remark);
                 
                $updatePendingPayment = array(
                    'status'            => 'Expired',
                    'error_msg'         => 'Deposit Time Exceed Valid Time',
                    'call_back_amount'  => $paymentAmount,
                    'call_back_data'    => $callbackData,
                    'updated_at'        => $dateTime,
                    'batch_id'          => $batchID,
                );

                $db->where('id', $pendingPayment['id']);
                $db->update('mlm_pending_payment', $updatePendingPayment);
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Deposit Time Exceed Valid Time', 'data'=> "");
            }


            if(!in_array($paymentResult, array('Over', 'Match')) || $paymentAmount < $pendingPayment['amount']){
                $db->rollback();
                $db->commit();
                $updatePendingPayment = array(
                    'status'            => 'Under Paid',
                    'error_msg'         => 'Payment is Under Paid',
                    'call_back_amount'  => $paymentAmount,
                    'call_back_data'    => $callbackData,
                    'updated_at'        => $dateTime,
                );

                $db->where('id', $pendingPayment['id']);
                $db->update('mlm_pending_payment', $updatePendingPayment);
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Payment is Under Paid', 'data'=> "");
            }

            $db->where('id', $pendingPayment['id']);
            $db->update('mlm_pending_payment', array('status' => 'Processing'));

            $db->commit();

            $batchID = $db->getNewID(); 

            $purchaseParams = json_decode($pendingPayment['data'], true);
            $db->userID = $pendingPayment['client_id'];
            $purchaseParams['batch_id'] = $batchID;
            $purchaseParams['isNicepayCallback'] = true;
            $purchaseParams['tx_id'] = $pendingPayment["tx_id"];
            $purchaseRes = Inventory::purchasePackageConfirmation($purchaseParams);
            if($purchaseRes['status'] != 'ok'){
                return $purchaseRes;
            }

            $updatePendingPayment = array(
                'status'            => 'Paid',
                'call_back_amount'  => $paymentAmount,
                'call_back_data'    => $callbackData,
                'updated_at'        => $dateTime,
                'batch_id'          => $batchID,
            );

            $db->where('id', $pendingPayment['id']);
            $db->update('mlm_pending_payment', $updatePendingPayment);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPaymentGatewayRequestListing($params){
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $decimalPlaces  = Setting::$systemSetting["internalDecimalFormat"];

            $site = $db->userType;
            $clientID = $db->userID;

            $tableName      = "mlm_pending_payment";
            $column         = array(
                "type", 
                "client_id",
                "amount",
                "tx_id",
                "merchant_token",
                "reference_no",
                "status",
                "created_at",
                "expired_at",
                "error_msg",
                "call_back_amount",
                "updated_at",
                "currency",
                "bank_code",
                "vacct_no",
            );
            
            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

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

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            break;

                        case 'mainLeaderUsername':
                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataType  = trim($v['dataType']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'username':
                            $sq = $db->subQuery();

                            if ($dataType == "like") {
                                $sq->where("username", "%".$dataValue."%", "LIKE");
                            }else{
                                $sq->where("username", $dataValue);
                            }
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN"); 
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN"); 
                            break;
                            
                        case 'reference_no':
                            $db->where("reference_no", $dataValue);
                            break;

                        case 'status':
                            $db->where("status", $dataValue);
                            break;

                        case 'bank_code':
                            $db->where("bank_code", $dataValue);
                            break;

                        case 'vacct_no':
                            $db->where("vacct_no", $dataValue);
                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
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

                                // if($dateTo == $dateFrom)

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        default:
                            // $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataType);
                    unset($dataValue);
                }

                if (!empty($mainDownlines)) $db->where('client_id', $mainDownlines, "IN");
                if (!empty($downlines)) $db->where('client_id', $downlines, "IN");
                if (!empty($searchArr)) $db->where('to_id', $searchArr, "IN");
            }

            if($site == "Member"){
                $db->where("client_id",$clientID);
            }

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 
            $db->orderBy("created_at","DESC");
            $results = $db->get($tableName, $limit, $column);

            if (empty($results))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach($results as $result) {
                $clientIDAry[$result['client_id']] = $result['client_id'];
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry, 'IN');
                $clientData = $db->map('id')->get('client', null, 'id, username, name, member_id, email');
            }

            $db->where('name', 'nicepaySetting');
            $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
            $nicepayBankAry = json_decode($nicepaySetting['description'], true);

            foreach($results as &$result) {
                if($result['type'] == 'nicepayVirtualAccount'){
                    $result['type']        = 'Virtual Account';
                } elseif ($result['type'] == 'nicepayCreditCard'){
                    $result['type']        = 'Credit Card';
                }
                $result['username']        = $clientData[$result['client_id']]['username'];
                $result['member_id']       = $clientData[$result['client_id']]['member_id'];
                $result['created_at']      = $result['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($result['created_at'])) : "-";
                $result['expired_at']      = $result['expired_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($result['expired_at'])) : "-";
                $result['updated_at']      = $result['expired_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($result['expired_at'])) : "-";
                $result['bank_name']       = $nicepayBankAry[$result['bank_code']];
                $result['statusDisplay']   = $result['status'] ? General::getTranslationByName("PG ".$result['status']) : '-';
                $result['error_msg']       = $result['error_msg'] ?: "-";
            }

            $data['result']   = $results;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);
            
        }

        public function getPendingPaymentStatus($params){
            $db = MysqliDb::getInstance();
            $recordID = $params['id'];

            $db->where('id', $recordID);
            $record = $db->getOne('mlm_pending_payment', 'status');

            $data['status'] = $record['status'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
    }
?>
