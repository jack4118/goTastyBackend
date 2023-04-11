<?php 
    interface BonusInterface {

        const BONUS_SPONSOR     = 'sponsorBonus';
        const BONUS_PAIRING     = 'pairingBonus';
        const BONUS_REBATE      = 'rebateBonus';
        const BONUS_GOLDMINE    = 'goldmineBonus';
        const BONUS_COMMUNITY   = 'communityBonus';
        const BONUS_TREASURE    = 'treasureBonus';
        const BONUS_LEADERSHIP  = 'leadershipBonus';
        const BONUS_TEAM        = 'teamBonus';
        const BONUS_MATCHING    = 'matchingBonus';
        const BONUS_BONANZA   = 'bonanzaBonus';
        const BONUS_WATERBUCKET = 'waterBucketBonus';
        const BONUS_RELEASE     = 'releaseBonus';
        const BONUS_JACKPOT     = 'jackpotBonus';
        const BONUS_KSPONSOR     = 'kSponsorBonus';
        const BONUS_MONTHLY_POOL = 'mthPoolBonus';
        const BONUS_TYPE_REGISTRATION   = 'Registration';
		const BONUS_TYPE_REENTRY        = 'Re-entry';
        const BONUS_TYPE_TOPUP          = 'Top Up';
        const BONUS_AWARD        = 'awardBonus';
        const BONUS_ACTIVE_PROGRAM      = 'active program bonus';
        const BONUS_ENROLLMENT     = 'enrollmentBonus';
        const BONUS_COUPLE     = 'coupleBonus';
        const BONUS_UNILEVEL     = 'unilevelBonus';
        const BONUS_LEADERSHIP_REWARD = 'leadershipRewardBonus';

    }

	class Bonus implements BonusInterface {

        public static $bonusPayoutID, $bonusSetting, $paymentMethod, $rankSetting, $clientDataAry, $sponsorTreeCache, $directorID, $clientSetting, $highestPortfolioAry, $unitPrice, $bonusCreator, $placementSelfData, $placementTreeCache, $placementDownlineAry;

        function __construct(){
         
        }

        public function insertBonusValue($params) {
            $db = MysqliDb::getInstance();

            $bonusValue = $params["bonusValue"];
            $clientID = $params["clientID"];

            if(($params["bonusValue"] > 0)){
                $insertData = array(
                    "client_id"     => $params["clientID"],
                    "main_id"       => $params["mainID"],
                    "type"          => $params["type"],
                    "product_id"    => $params["productID"],
                    "belong_id"     => $params["belongID"],
                    "batch_id"      => $params["batchID"],
                    "bonus_value"   => $params["bonusValue"],
                    "created_at"    => $params["dateTime"] ? $params["dateTime"] : $db->now()
                );
                $bonusInID = $db->insert("mlm_bonus_in",$insertData);
            }

            return true;
        }

        function getBonusData($type){
            $db = MysqliDb::getInstance();

            $result = $db->get("mlm_bonus",NULL,"id,name, calculation, payment");
            foreach($result AS $key => $row){
                $bonusIDAry[$row["id"]] = $row["id"];
                $bonusNameAry[$row['id']] = $row['name'];
                $bonusData['activeBonus'][$row['name']] = $row['name'];
                $bonusData[$row['name']]['calculate'] = $row['calculation'];
                $bonusData[$row['name']]['payment'] = $row['payment'];
            }

            if($bonusIDAry) $db->where("bonus_id", $bonusIDAry, "IN");
            $result = $db->get("mlm_bonus_setting",NULL,"type,name,value,reference,bonus_id, description");
            foreach($result AS $key => $row){
                $bonusName = $bonusNameAry[$row['bonus_id']];
                $name = $row['name'];
                unset($row['name'], $row['bonus_id']);
                
                if(in_array($row['type'], array("Level","Rank Setting"))){
                    $bonusData[$bonusName][$row['type']][$name][] = $row;
                }else if($row['type'] == "Product ID"){
                    $bonusData[$bonusName][$name][$row["reference"]] = $row;
                }else{
                    $bonusData[$bonusName][$row['type']][$name] = $row;
                }
            }

            return $bonusData;
        }

        function bonusPreset($bonusDate, $bonusCreatorID){
            $db = MysqliDb::getInstance();
           
            Log::write(date("Y-m-d H:i:s") . " Start Bonus Preset.\n");
            Cash::$creatorID = 0;
            Cash::$creatorType = "System";

            Self::$bonusSetting = Self::getBonusData($type);

            $return = Self::getPaymentMethod();
            Self::$paymentMethod = $return;

            Self::$bonusCreator = $bonusCreatorID;

            $db->where("username","bonusPayout");
            $db->where("type","Internal");
            $payoutID = $db->getValue("client", "id");
            Self::$bonusPayoutID = $payoutID; 

            $db->where("username","director");
            $directorID = $db->getValue("client","id");
            Self::$directorID = $directorID;

            $db->where('name',array('terminatedAt','awardCycleDate'),"IN");
            $clientStgRes = $db->get('client_setting',null,'name,client_id,DATE(value) AS dateValue');
            foreach ($clientStgRes as $clientStgRow) {
                switch ($clientStgRow['name']) {
                    case 'terminatedAt':
                        $clientTerminateArr[$clientStgRow['client_id']] = $clientStgRow['dateValue'];
                        break;

                    case 'awardCycleDate':
                        $awardCycleDateArr[$clientStgRow['client_id']] = $clientStgRow['dateValue'];
                        break;
                }
            }

            $db->where("type","Client");
            $clientRes = $db->get("client", null, "id, name, username, country_id, client.terminated, sponsor_id, active_date, client.freezed,client.activated");
            foreach ($clientRes as &$clientRow) {
                if(($clientRow['terminated'] == 1) && (strtotime($bonusDate) < strtotime($clientTerminateArr[$clientRow['id']]))){
                    $clientRow['terminated'] = 0;
                }
                $clientRow['awardCycleDate'] = $awardCycleDateArr[$clientRow["id"]];
                $clientDataAry[$clientRow["id"]] = $clientRow;
            }

            $tblDate = date("Ymd", strtotime($bonusDate));
            $db->where('table_schema', Setting::$configArray['dB']);
            $db->where('table_name', 'tree_sponsor_cache_'.$tblDate);
            $isTableExists = $db->getValue('information_schema.tables', 'COUNT(*)');
            if ($isTableExists > 0) {
                $result = $db->get("tree_sponsor_cache_".$tblDate, null, "client_id, trace_key, level");
                foreach($result AS $row){
                    if($row['client_id']){
                        $sponsorTreeCache[$row['client_id']] = $row['trace_key'];
                        $clientDataAry[$row['client_id']]['level'] = $row['level'];
                    }
                }
            }

            // $productPriorityAry = $db->map("id")->get("mlm_product", null, "id, priority");

            $db->where("created_at", $bonusDate, "<=");
            $db->where("status", "Active");
            $db->orderBy("created_at", "ASC");
            $highestPortfolioRes = $db->get("mlm_client_portfolio", null, "client_id, product_id, status");
            foreach ($highestPortfolioRes as $highestPortfolioRow) {
                $clientDataAry[$highestPortfolioRow["client_id"]]["portfolioCount"] += 1;
            }

            Self::$clientDataAry = $clientDataAry;
            Self::$sponsorTreeCache = $sponsorTreeCache;

            $tblDate = date("Ymd", strtotime($bonusDate));
            $db->where('table_schema', Setting::$configArray['dB']);
            $db->where('table_name', 'tree_placement_cache_'.$tblDate);
            $isTableExists = $db->getValue('information_schema.tables', 'COUNT(*)');
            if ($isTableExists > 0) {
                $db->orderBy("level", "DESC");
                $placementRes = $db->get("tree_placement_cache_".$tblDate, null, "client_id, upline_id, client_position, trace_key, level");
                foreach($placementRes AS $placementRow){
                    $placementSelfData[$placementRow['client_id']]['position'] = $placementRow['client_position'];
                    $placementSelfData[$placementRow['client_id']]['upline'] = $placementRow['upline_id'];
                    $placementTreeCache[$placementRow['client_id']] = $placementRow['trace_key'];
                    $placementDownlineAry[$placementRow["upline_id"]][$placementRow["client_position"]] = $placementRow["client_id"];
                }
            }
            
            Self::$placementSelfData = $placementSelfData;
            Self::$placementTreeCache = $placementTreeCache;
            Self::$placementDownlineAry = $placementDownlineAry;


            $latestUnitPrice = General::getLatestUnitPrice();
            Self::$unitPrice = $latestUnitPrice;

            Log::write(date("Y-m-d H:i:s") . " Done Bonus Preset.\n");

            return true;
        }

        function checkMemberStatus($clientID, $dateTime, $action){
            $db = MysqliDb::getInstance();

            if(!$clientID){
                return false;
            }

            if(!$action){
                return false;
            }

            if(!$dateTime){
                $dateTime = date("Y-m-d H:i:s");
            }

            $clientData = self::$clientDataAry[$clientID];
            $dateSetting = self::$dateSetting;
            $memberStatus = 1;

            if($clientData['activated'] != 2){
                if($action == "calculate"){
                    $flushBonusDuration = $dateSetting["flushBonusDuration"];
                    $flushBonusDay = date("Y-m-d H:i:s", strtotime("-".$flushBonusDuration["value"]." ".$flushBonusDuration["reference"]." ".$dateTime)); // 7day
                    if(strtotime($clientData["active_date"]) <= strtotime($flushBonusDay)){
                        $memberStatus = 0;
                    }
                }else if($action == "payout"){
                    $withholdingPayout = $dateSetting["withholdingPayout"];
                    $withholdingPayoutDay = date("Y-m-d H:i:s", strtotime("-".$withholdingPayout["value"]." ".$withholdingPayout["reference"]." ".$dateTime)); // 7day
                    if(strtotime($clientData["active_date"]) <= strtotime($withholdingPayoutDay)){
                        $memberStatus = 0;
                    }
                }
            }

            return $memberStatus;
        }

        function getPaymentMethod($paymentReference) {

            $db = MysqliDb::getInstance();
            $tableName      = "mlm_bonus_payment_method";
            $paymentMethod  = array();
            $column         = array(

                "id",
                "(SELECT name FROM mlm_bonus WHERE id = bonus_id) AS bonus_name",
                "credit_type",
                "percentage",
                "description",
                "is_special",
            );

            //payment reference is the id of the bonus
            if ($paymentReference) {
                $sq = $db->subQuery();
                $sq->where("name", $paymentReference);
                $sq->getOne("mlm_bonus", "id");
                $db->where("bonus_id", $sq);
            }

            $result = $db->get($tableName, NULL, $column);

            foreach ($result as $row){
                $paymentMethod[$row["bonus_name"]]["subject"] = $row["description"];
                if($paymentReference){
                    $paymentMethod[$row['credit_type']] = $row['percentage'];
                }else{
                    if($row["is_special"]){
                        $paymentMethod[$row["bonus_name"]]["specialPayment"][$row['credit_type']] = $row['percentage'];
                    }else{
                        $paymentMethod[$row["bonus_name"]]["payment"][$row['credit_type']] = $row['percentage'];
                    }
                }
            }
            return $paymentMethod;
        }

        function cacheTable($table, $bonusDate){
            $db = MysqliDb::getInstance();
            $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS `".$table."_cache` LIKE ".$table);
            Log::write(date("Y-m-d H:i:s") . " Clear table ".$table."_cache.\n");
            $res = $db->rawQuery("TRUNCATE TABLE `".$table."_cache`");
            Log::write(date("Y-m-d H:i:s") . " Inserting tree data into ".$table."_cache.\n");
            $res = $db->rawQuery("INSERT ".$table."_cache SELECT * FROM ".$table.";");
            Log::write(date("Y-m-d H:i:s") . " Done inserting tree data into ".$table."_cache.\n");
            return true;
        }

        function duplicateClientSales($bonusDate){
            $db = MysqliDb::getInstance();
            $dateTime = date('Y-m-d 23:59:59',strtotime($bonusDate));
            $bonusName = "resetClientSales";

            if ($bonusDate != date('Y-m-t',strtotime($bonusDate))){
                Log::write(date("Y-m-d H:i:s")." $date Not last day of the month.\n");
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

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = Self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            Log::write(date("Y-m-d H:i:s")." Start duplicate client monthly sales.\n");

            $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS `client_sales_cache` LIKE `client_sales`");
            Log::write(date("Y-m-d H:i:s") . " Clear table `client_sales_cache`.\n");

            $res = $db->rawQuery("TRUNCATE TABLE `client_sales_cache`");
            Log::write(date("Y-m-d H:i:s") . " Inserting data into `client_sales_cache`.\n");

            $res = $db->rawQuery("INSERT INTO `client_sales_cache` (`id`, `client_id`, `sponsor_id`, `activated`, `downline_count`, `active_downline_count`, `active_leg`, `own_sales`, `group_sales`, `sponsor_sales`, `pgp_sales`, `updated_at`) SELECT `id`, `client_id`, `sponsor_id`, `activated`, `downline_count`, `active_downline_count`, `active_leg`, `own_sales`, `group_sales`, `sponsor_sales`, `pgp_sales`, '$dateTime' FROM `client_sales`"); 
            Log::write(date("Y-m-d H:i:s") . " Done inserting data into `client_sales_cache`.\n");

            $res =$db->rawQuery("UPDATE `client_sales` SET `own_sales` = 0.00000000, `group_sales` = 0.00000000, `sponsor_sales` = 0.00000000, `pgp_sales` = 0.00000000");

            $res = $db->rawQuery("INSERT INTO `client_monthly_sales` (`client_id`, `sponsor_id`, `activated`, `downline_count`, `active_downline_count`, `active_leg`, `own_sales`, `group_sales`, `sponsor_sales`, `pgp_sales`, `updated_at`) SELECT `client_id`, `sponsor_id`, `activated`, `downline_count`, `active_downline_count`, `active_leg`, `own_sales`, `group_sales`, `sponsor_sales`, `pgp_sales`,`updated_at` FROM `client_sales_cache`"); 
            Log::write(date("Y-m-d H:i:s") . " Done inserting data into `client_monthly_sales`.\n");

            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
 
            Log::write(date("Y-m-d H:i:s")." Finish duplicate client monthly sales.\n");

            return true;
        }

        function cacheDailyTable($table, $bonusDate, $isUpdateSponsorCache){
            $db = MysqliDb::getInstance();
            $currentDate = date('Y-m-d');

            $tblDate = date("Ymd", strtotime($bonusDate));
            $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS `".$table."_cache_".$tblDate."` LIKE ".$table);

            $checkID = $db->getValue($table."_cache_".$tblDate,'id');
            if($checkID && $isUpdateSponsorCache != 1 && (strtotime($bonusDate) < strtotime($currentDate))){
                return true;
            }

            Log::write(date("Y-m-d H:i:s") . " Clear table ".$table."_cache_".$tblDate.".\n");
            $res = $db->rawQuery("TRUNCATE TABLE `".$table."_cache_".$tblDate."`");
            Log::write(date("Y-m-d H:i:s") . " Inserting tree data into ".$table."_cache_".$tblDate.".\n");

            $res = $db->rawQuery("INSERT ".$table."_cache_".$tblDate." SELECT * FROM ".$table.";");
            Log::write(date("Y-m-d H:i:s") . " Done inserting tree data into ".$table."_cache_".$tblDate.".\n");
            return true;
        }

        function getSponsorTreeUplinesCache($clientID, $limit, $includeSelf) {

            $db = MysqliDb::getInstance();
            $data = array();

            $sponsorTreeCache = Self::$sponsorTreeCache;
            $clientTraceKey = $sponsorTreeCache[$clientID];
            $uplineIDArray = explode("/", $clientTraceKey);

            if ($includeSelf != true )
                unset($uplineIDArray[count($uplineIDArray) - 1]);

            if (!empty($limit)){
                for($count = 1; $count <= $limit; $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }
            else{
                for($count = 1; $count <= count($uplineIDArray); $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }

            return $data;
        }

        function getSponsorDownlineCacheByClientID($clientID,$level){
            $db = MysqliDb::getInstance();
            
            $db->where("client_id", $clientID);
            $treeRow = $db->getOne("tree_sponsor", "level, trace_key");
            $clientTraceKey = $treeRow["trace_key"];
            $clientLevel = $treeRow["level"];

            // Find the downline with the trace key
            $db->orderby("level", "asc");
            $db->orderby("id", "asc");
            if($level > 0){
                $db->where("level",($clientLevel + $level),"<=");
            }
            $db->where("trace_key", $clientTraceKey."/%", "LIKE");
            $result = $db->get("tree_sponsor", null, "client_id");


            foreach ($result as $row)
            {
                $downlines[] = $row["client_id"];
            }
            return $downlines;
        }

        function clearBonusReport($bonusName, $bonusDate) {
            $db = MysqliDb::getInstance();

            $db->where("bonus_type",$bonusName);
            $db->where("bonus_date",$bonusDate);
            $db->delete("mlm_bonus_report");

            return true;
        }

        public function calculateBonusTier($clientID, $dateTime, $rankCalType, $rankType,$isReset){
            $db = MysqliDb::getInstance();      
            $language = General::$currentLanguage;
            $translations = General::$translations;   
            $rankType = "Bonus Tier";
            if($rankCalType == "starterKit"){
                $bonusName = "calculateRank#starterKit";
            } else if($rankCalType == "fizMemberUpgrade"){
                $bonusName = "calculateRank#fizMemberUpgrade";
            } else if($rankCalType == "couple"){
                $bonusName = "calculateRank#couple";
            }
            // $bonusName = "calculateRank";
            $isBonusCal = 0;

            if(!$clientID){
                $db->where('type','Client');
                $clientIDArr = $db->map('id')->get('client',null,'id');
                $isBonusCal = 1;
                //Bonus Date Change to Current Date
                $bonusDate = $dateTime;
                // if($isReset){
                    $dateTime = date("Y-m-d 23:59:59", strtotime($dateTime));
                // }else{
                //     $dateTime = date("Y-m-d", strtotime($dateTime." +1 day"));
                // }

                $batchID = Bonus::insertBonusCalculationBatch($bonusName, $bonusDate);
                if($batchID) {
                    Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
                } else {
                    Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
                }

                $date = date('Y-m-d',strtotime($dateTime));
            }else{
                $clientIDArr[$clientID] = $clientID;
                $receiverID = $clientID;

                $date = date('Y-m-d',strtotime($dateTime));
            }

            if(!$clientIDArr){
                $db->resetState();
                return false;
            }

            switch ($rankCalType) {
                case 'starterKit':

                    $bonusSetting      = Self::getBonusData();
                    $joinPackProduct = $bonusSetting['enrollmentBonus']['Bonus Setting']['enrollmentProductCode']['value'];
                    $joinPackProduct = explode("#",$joinPackProduct);

                    $db->where('code',$joinPackProduct,'IN');
                    $db->where('is_starter_kit','1');
                    $db->where('status','Active');
                    $joinPackIDAry = $db->getValue('mlm_product','id',null);

                    $db->where('code',$joinPackProduct,'NOT IN');
                    $db->where('is_starter_kit','1');
                    $db->where('status','Active');
                    $starterPackIDAry = $db->getValue('mlm_product','id',null);

                    if($joinPackIDAry){
                        if($clientID) $db->where('client_id',$clientID);
                        $db->where('DATE(created_at)',$date);
                        $db->where('product_id',$joinPackIDAry,'IN');
                        $joinPurchase = $db->get('mlm_client_portfolio',null,'client_id');
                    }

                    if($starterPackIDAry){
                        if($clientID) $db->where('client_id',$clientID);
                        $db->where('DATE(created_at)',$date);
                        $db->where('product_id',$starterPackIDAry,'IN');
                        $starterPurchase = $db->get('mlm_client_portfolio',null,'client_id');
                    }

                    unset($starterIDAry);
                    if($starterPurchase) {
                        foreach($starterPurchase as $starterPurchaseRow){
                            $starterIDAry[$starterPurchaseRow['client_id']] = $starterPurchaseRow['client_id'];
                            $starterRankName = "member";
                        }
                    }

                    unset($joinIDAry);
                    if($joinPurchase) {
                        foreach($joinPurchase as $joinPurchaseRow){
                            $joinIDAry[$joinPurchaseRow['client_id']]= $joinPurchaseRow['client_id'];
                            $joinRankName = "fizEntreprenuer";
                        }
                    }

                    if(!$starterPurchase && !$joinPurchase) {
                        if($isBonusCal) Log::write(date("Y-m-d H:i:s") . " No starter kit / join pack purchase for ".$date." skip...\n");
                        return false;
                    }

                    break;

                case 'fizMemberUpgrade':

                    $bonusSetting      = Self::getBonusData();
                    $joinPackProduct = $bonusSetting['enrollmentBonus']['Bonus Setting']['enrollmentProductCode']['value'];
                    $joinPackProduct = explode("#",$joinPackProduct);

                    $db->where('code',$joinPackProduct,'IN');
                    $db->where('is_starter_kit','1');
                    $db->where('status','Active');
                    $joinPackIDAry = $db->getValue('mlm_product','id',null);

                    unset($upgradeIDAry);
                    if($clientID){
                        $db->where('id',$clientID);
                        $sponsor = $db->getValue('client','sponsor_id');

                        $db->where('sponsor_id',$clientID);
                        $downline = $db->getValue('client','id',null);

                        $upgradeIDAry = array($clientID,$sponsor);

                        foreach($downline as $downlineIDs){
                            $upgradeIDAry[] = $downlineIDs;
                        }
                    }

                    break;

                case 'couple':
                    
                    $db->where('name','minCouple');
                    $getMinCouple = $db->map('rank_id')->get('rank_setting',null,'rank_id,value');

                    $db->where('name','minCouple');
                    $db->orderBy('rank_id','ASC');
                    $getMinCoupleAmt = $db->getOne('rank_setting','value')['value'];

                    $db->where('name','minCouple');
                    $getMinCouple = $db->map('rank_id')->get('rank_setting',null,'rank_id,value');

                    if($clientID) $db->where('client_id',$clientID);
                    $db->where('bonus_date',$date, '<=');
                    $db->groupBy('client_id');
                    $coupleBonus = $db->map('client_id')->get('mlm_bonus_couple',null,'client_id, SUM(total_couple) as total_couple');

                    unset($newRankID, $coupleIDAry);
                    foreach($coupleBonus as $coupleID => $totalCouple){
                        if($totalCouple < $getMinCoupleAmt) continue;
                        //add if no active PV for 12 month, continue
                        foreach($getMinCouple as $minRank => $minCouple){
                            if($totalCouple >= $minCouple){
                                $newRankID[$coupleID] = $minRank;
                                $coupleIDAry[$coupleID] = $coupleID;
                            }
                        }
                    }

                    break;

                default: 
                    if($isBonusCal)Log::write(date("Y-m-d H:i:s") . " Invalid Rank Type.\n");
                    return false;
                    break;
            }

            //get all rank setting
            $db->where("type", $rankType);
            $db->orderBy("priority","DESC");
            $rankIDAry = $db->map("name")->get("rank",null, "name, id");

            $db->where("type", $rankType);
            $rankPriorityAry = $db->map("id")->get("rank",null, "id, priority");

            $db->where("disabled","0");
            $db->where("allow_rank_maintain","1");
            $bonusRankNameAry = $db->get('mlm_bonus',null,'name');

            $rankSettingRes = $db->get("rank_setting", null, "rank_id, name, value, type, reference");
            unset($rankSettingAry);
            unset($minRankQualification);
            foreach($rankSettingRes as $rankSettingRow){
                switch ($rankSettingRow["type"]) {
                    case 'percentage':
                        $rankSettingAry[$rankSettingRow["rank_id"]][$rankSettingRow["name"]] = $rankSettingRow["value"];
                        break;

                    case 'purchase':
                        $minRankQualification[$rankSettingRow["rank_id"]][$rankSettingRow["name"]] = $rankSettingRow["value"];
                        if($rankSettingRow["name"] == 'minActiveLeg'){
                            $minRankQualification[$rankSettingRow["rank_id"]]["minDownlineRank"] = $rankSettingRow["reference"];
                        }
                        break;
                }
            }
            
            //calculate rank
            unset($clientLatestRankRes);
            if($clientID) {
                if($upgradeIDAry) $db->where('client_id',$upgradeIDAry,'IN');
                else $db->where('client_id',$clientID);
            }
            $db->where("rank_type",$rankType);
            $db->where("type","System");
            $db->where('DATE(created_at)',$date,"<=");
            $db->groupBy('client_id');
            $db->groupBy('name');
            $clientLatestRankRes = $db->get("client_rank", null, "client_id, MAX(id) as id");

            unset($maxID);
            foreach($clientLatestRankRes AS $clientLatestRankRow){
                    $maxID[] = $clientLatestRankRow['id'];
            }

            if($maxID){
                $db->where("id",$maxID,"IN");
                $clientRankRes = $db->get("client_rank",NULL,"client_id, name, type, value, rank_id, created_at");

                unset($prevRankAry, $memberRankIDAry);
                foreach($clientRankRes as $clientRankRow){
                    $prevRankAry[$clientRankRow['client_id']][$clientRankRow['name']] = $clientRankRow['rank_id'];

                    if($rankCalType == "fizMemberUpgrade"){
                        if($clientRankRow['rank_id'] == $rankIDAry['member'] && $clientRankRow['name'] == 'rankDisplay') {
                            $memberRankIDAry[$clientRankRow['client_id']] = $clientRankRow['client_id'];
                            $memberRankDateAry[$clientRankRow['client_id']] = date('Y-m-d', strtotime($clientRankRow['created_at']));
                        }
                    }
                }
            }

            //starter kit upgrade rank
            if($rankCalType == "starterKit"){
                //starter pack: no rank to fiz member
                if($starterIDAry){
                    foreach($starterIDAry as $starterID){
                        if(empty($prevRankAry[$starterID]['rankDisplay']) ||  $rankPriorityAry[$prevRankAry[$starterID]['rankDisplay']] < $rankPriorityAry[$rankIDAry['member']]){
                            unset($insertClientRank);
                            $insertClientRank = array(
                                'client_id'  => $starterID,
                                'name'       => "rankDisplay", // rank_setting (name) 
                                'rank_id'    => $rankIDAry['member'],
                                'value'      => "", // rank_setting (value)  
                                'rank_type'  => $rankType,
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $dateTime,
                            );
                            $db->insert('client_rank', $insertClientRank);

                        }

                        if($bonusRankNameAry){
                            foreach($bonusRankNameAry as $bonusRankNameRow){
                                unset($insertClientRank);
                                if(empty($prevRankAry[$starterID][$bonusRankNameRow['name']."Percentage"]) || $rankPriorityAry[$prevRankAry[$starterID][$bonusRankNameRow['name']."Percentage"]] < $rankPriorityAry[$rankIDAry['member']]){
                                    $insertClientRank = array(
                                        'client_id'  => $starterID,
                                        'name'       => $bonusRankNameRow['name']."Percentage", // rank_setting (name) 
                                        'rank_id'    => $rankIDAry['member'],
                                        'value'      => $rankSettingAry[$rankIDAry['member']][$bonusRankNameRow['name']."Percentage"], // rank_setting (value)  
                                        'rank_type'  => $rankType,
                                        'type'       => 'System', // rank_setting (type) 
                                        'created_at' => $dateTime,
                                    );
                                    $db->insert('client_rank', $insertClientRank); 
                                }
                            }
                        }

                        unset($insertClientRank);
                        if(empty($prevRankAry[$starterID]['discountPercentage']) ||  $rankPriorityAry[$prevRankAry[$starterID]["discountPercentage"]] < $rankPriorityAry[$rankIDAry['member']]) {
                            $insertClientRank = array(
                                'client_id'  => $starterID,
                                'name'       => "discountPercentage", // rank_setting (name) 
                                'rank_id'    => $rankIDAry['member'],
                                'value'      => $rankSettingAry[$rankIDAry['member']]["discountPercentage"], 
                                'rank_type'  => $rankType,
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $dateTime,
                            );
                            $db->insert('client_rank', $insertClientRank); 

                        }

                    }
                }

                //join pack: no rank to fiz preneur
                if($joinIDAry){
                    foreach($joinIDAry as $joinID){
                        if(empty($prevRankAry[$joinID]['rankDisplay']) ||  $rankPriorityAry[$prevRankAry[$joinID]['rankDisplay']] < $rankPriorityAry[$rankIDAry['member']]){

                            unset($insertClientRank);
                            $insertClientRank = array(
                                'client_id'  => $joinID,
                                'name'       => "rankDisplay", // rank_setting (name) 
                                'rank_id'    => $rankIDAry['fizEntreprenuer'],
                                'value'      => "", // rank_setting (value)  
                                'rank_type'  => $rankType,
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $dateTime,
                            );
                            $db->insert('client_rank', $insertClientRank);
                        }
                        

                        if($bonusRankNameAry){
                            foreach($bonusRankNameAry as $bonusRankNameRow){
                                unset($insertClientRank);
                                if(empty($prevRankAry[$joinID][$bonusRankNameRow['name']."Percentage"]) || $rankPriorityAry[$prevRankAry[$joinID][$bonusRankNameRow['name']."Percentage"]] < $rankPriorityAry[$rankIDAry['fizEntreprenuer']]){
                                    $insertClientRank = array(
                                        'client_id'  => $joinID,
                                        'name'       => $bonusRankNameRow['name']."Percentage", // rank_setting (name) 
                                        'rank_id'    => $rankIDAry['fizEntreprenuer'],
                                        'value'      => $rankSettingAry[$rankIDAry['fizEntreprenuer']][$bonusRankNameRow['name']."Percentage"], // rank_setting (value)  
                                        'rank_type'  => $rankType,
                                        'type'       => 'System', // rank_setting (type) 
                                        'created_at' => $dateTime,
                                    );
                                    $db->insert('client_rank', $insertClientRank); 
                                }
                            }
                        }

                        unset($insertClientRank);
                        if(empty($prevRankAry[$joinID]['discountPercentage']) ||  $rankPriorityAry[$prevRankAry[$joinID]["discountPercentage"]] < $rankPriorityAry[$rankIDAry['fizEntreprenuer']]) {
                            $insertClientRank = array(
                                'client_id'  => $joinID,
                                'name'       => "discountPercentage", // rank_setting (name) 
                                'rank_id'    => $rankIDAry['fizEntreprenuer'],
                                'value'      => $rankSettingAry[$rankIDAry['fizEntreprenuer']]["discountPercentage"], 
                                'rank_type'  => $rankType,
                                'type'       => 'System', // rank_setting (type) 
                                'created_at' => $dateTime,
                            );
                            $db->insert('client_rank', $insertClientRank); 

                        }

                    }

                }
            }

            //normal fiz member upgrade to fiz preneur
            if($rankCalType == "fizMemberUpgrade" && !$memberRankIDAry){
                if($isBonusCal) Log::write(date("Y-m-d H:i:s") . " No Fiz Member to calculate.\n");
                return false;
            }

            if($memberRankIDAry){
                foreach($memberRankIDAry as $memberRankID){
                    $db->where('client_id',$memberRankID);
                    $db->where('DATE(created_at)',$memberRankDateAry[$memberRankID],'>=');
                    $db->where('DATE(created_at)',$date,"<=");
                    $pvChecking = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
                    if($pvChecking < $minRankQualification[$rankIDAry['fizEntreprenuer']]['minOwnSales']) continue;

                    $db->where('sponsor_id',$memberRankID);
                    $directDownlines = $db->get('client',null,'id');

                    if(!$directDownlines) continue;
                    if(!$joinPackIDAry) continue;

                    $db->where('DATE(created_at)',$memberRankDateAry[$memberRankID],'>=');
                    $db->where('DATE(created_at)',$date,"<=");
                    $db->where('product_id',$joinPackIDAry,'IN');
                    $joinPurchase = $db->getValue('mlm_client_portfolio','client_id',null);

                    $activeLeg[$memberRankID] = 0;
                    foreach($directDownlines as $directDownlinesRow){
                        if(in_array($directDownlinesRow['id'],$joinPurchase)){
                            $activeLeg[$memberRankID] ++ ;
                        }
                    }

                    if($activeLeg[$memberRankID] < $minRankQualification[$rankIDAry['fizEntreprenuer']]['minActiveLeg']) continue;

                    //update rank to fiz preneur
                    if(empty($prevRankAry[$memberRankID]['rankDisplay']) ||  $rankPriorityAry[$prevRankAry[$memberRankID]['rankDisplay']] < $rankPriorityAry[$rankIDAry['fizEntreprenuer']]){
                        unset($insertClientRank);
                        $insertClientRank = array(
                            'client_id'  => $memberRankID,
                            'name'       => "rankDisplay", // rank_setting (name) 
                            'rank_id'    => $rankIDAry['fizEntreprenuer'],
                            'value'      => "", // rank_setting (value)  
                            'rank_type'  => $rankType,
                            'type'       => 'System', // rank_setting (type) 
                            'created_at' => $dateTime,
                        );
                        $db->insert('client_rank', $insertClientRank);
                    }

                    if($bonusRankNameAry){
                        foreach($bonusRankNameAry as $bonusRankNameRow){
                            unset($insertClientRank);
                            if(empty($prevRankAry[$memberRankID][$bonusRankNameRow['name']."Percentage"]) || $rankPriorityAry[$prevRankAry[$memberRankID][$bonusRankNameRow['name']."Percentage"]] < $rankPriorityAry[$rankIDAry['fizEntreprenuer']]){
                                $insertClientRank = array(
                                    'client_id'  => $memberRankID,
                                    'name'       => $bonusRankNameRow['name']."Percentage", // rank_setting (name) 
                                    'rank_id'    => $rankIDAry['fizEntreprenuer'],
                                    'value'      => $rankSettingAry[$rankIDAry['fizEntreprenuer']][$bonusRankNameRow['name']."Percentage"], // rank_setting (value)  
                                    'rank_type'  => $rankType,
                                    'type'       => 'System', // rank_setting (type) 
                                    'created_at' => $dateTime,
                                );
                                $db->insert('client_rank', $insertClientRank); 
                            }
                        }
                    }

                    unset($insertClientRank);
                    if(empty($prevRankAry[$memberRankID]['discountPercentage']) ||  $rankPriorityAry[$prevRankAry[$memberRankID]["discountPercentage"]] < $rankPriorityAry[$rankIDAry['fizEntreprenuer']]) {
                        $insertClientRank = array(
                            'client_id'  => $memberRankID,
                            'name'       => "discountPercentage", // rank_setting (name) 
                            'rank_id'    => $rankIDAry['fizEntreprenuer'],
                            'value'      => $rankSettingAry[$rankIDAry['fizEntreprenuer']]["discountPercentage"], 
                            'rank_type'  => $rankType,
                            'type'       => 'System', // rank_setting (type) 
                            'created_at' => $dateTime,
                        );
                        $db->insert('client_rank', $insertClientRank); 

                    }
                }
            }

            if($coupleIDAry){
                foreach($coupleIDAry as $coupleIDRow){
                    //update rank
                    if($rankPriorityAry[$prevRankAry[$coupleIDRow]['rankDisplay']] < $rankPriorityAry[$newRankID[$coupleIDRow]]){
                        unset($insertClientRank);
                        $insertClientRank = array(
                            'client_id'  => $coupleIDRow,
                            'name'       => "rankDisplay", // rank_setting (name) 
                            'rank_id'    => $newRankID[$coupleIDRow],
                            'value'      => "", // rank_setting (value)  
                            'rank_type'  => $rankType,
                            'type'       => 'System', // rank_setting (type) 
                            'created_at' => $dateTime,
                        );
                        $db->insert('client_rank', $insertClientRank);
                    }

                    if($bonusRankNameAry){
                        foreach($bonusRankNameAry as $bonusRankNameRow){
                            unset($insertClientRank);
                            if($rankPriorityAry[$prevRankAry[$coupleIDRow][$bonusRankNameRow['name']."Percentage"]] < $rankPriorityAry[$newRankID[$coupleIDRow]]){
                                $insertClientRank = array(
                                    'client_id'  => $coupleIDRow,
                                    'name'       => $bonusRankNameRow['name']."Percentage", // rank_setting (name) 
                                    'rank_id'    => $newRankID[$coupleIDRow],
                                    'value'      => $rankSettingAry[$newRankID[$coupleIDRow]][$bonusRankNameRow['name']."Percentage"], // rank_setting (value)  
                                    'rank_type'  => $rankType,
                                    'type'       => 'System', // rank_setting (type) 
                                    'created_at' => $dateTime,
                                );
                                $db->insert('client_rank', $insertClientRank); 
                            }
                        }
                    }

                    unset($insertClientRank);
                    if($rankPriorityAry[$prevRankAry[$coupleIDRow]["discountPercentage"]] < $rankPriorityAry[$newRankID[$coupleIDRow]]) {
                        $insertClientRank = array(
                            'client_id'  => $coupleIDRow,
                            'name'       => "discountPercentage", // rank_setting (name) 
                            'rank_id'    => $newRankID[$coupleIDRow],
                            'value'      => $rankSettingAry[$newRankID[$coupleIDRow]]["discountPercentage"], 
                            'rank_type'  => $rankType,
                            'type'       => 'System', // rank_setting (type) 
                            'created_at' => $dateTime,
                        );
                        $db->insert('client_rank', $insertClientRank); 

                    }

                }
            }

            if($isBonusCal){
                Bonus::insertBonusCalculationBatch($bonusName, $bonusDate, 1);
            }
            unset($clientIDArr, $memberRankIDAry, $bonusRankNameAry, $clientRankRes);

            if($isBonusCal) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
            } else {
                return true;
            }
        }

        function getClientRank($rankType,$clinetIDArr,$dateTime, $bonusName, $type){
            $db = MysqliDb::getInstance();
            if(!$dateTime) $dateTime = date("Y-m-d H:i:s");

            if($rankType) $db->where("rank_type",$rankType);
            if($clinetIDArr) $db->where("client_id",$clinetIDArr,"IN");
            if($bonusName == 'rankDisplay')  {
                $db->where("name", $bonusName);
            } else {
                $db->where("name", $bonusName."Percentage");
            }
            if($type) $db->where("type", $type);
            $db->where("created_at",$dateTime,"<=");
            $db->groupBy("client_id");
            $db->groupBy("type");
            $res = $db->get("client_rank",NULL,"client_id, MAX(id) as id");
            foreach($res AS $row){
                $maxID[] = $row['id'];
            }

            if($maxID){
                $db->where('type',$rankType);
                $priorityArr = $db->map('id')->get('rank',null,'id,priority');

                $db->where("id",$maxID,"IN");
                $res = $db->get("client_rank",NULL,"client_id, type, value, rank_id");
                foreach($res AS $row){
                	if($row["value"] == ""){
                		$clientRank[$row['client_id']]["rank_id"] = $row["rank_id"];
                        $clientRank[$row['client_id']]["adminSetRank"] = ($row['type'] == "Admin" ? 1 : 0);
                	}else if(($row["value"] >= $clientRank[$row['client_id']]["percentage"]) && ($priorityArr[$row["rank_id"]] > $priorityArr[$clientRank[$row['client_id']]["rank_id"]]) ){
                        // $clientRankID[$row['client_id']] = $row["rank_id"];
                        $clientRank[$row['client_id']]["rank_id"] = $row["rank_id"];
                        $clientRank[$row['client_id']]["percentage"] = $row["value"];
                        $clientRank[$row['client_id']]["adminSetRank"] = ($row['type'] == "Admin" ? 1 : 0);
                    }
                    // $clientRank[$row['type']][$row['client_id']] = $row['rank_id'];
                }   
            }


            return $clientRank;
        }

        function getRankSetting($name){
            $db = MysqliDb::getInstance();

            if($name) $db->where("name",$name);
            $res = $db->get("rank_setting",NULL,"name,rank_id,value");
            foreach($res AS $row){
                $rankSetting[$row['name']][$row['rank_id']] = $row['value'];
            }

            return $rankSetting;
        }

        function checkMaxCap($clientID, $bonusName, $subject, $amount, $paidDate, $batchID, $portfolioID){
            $db = MysqliDb::getInstance();
            if(!$clientID) return false;

            if($amount <= 0) return false;

            if(!$subject) return false;

            $internalID = Self::$bonusPayoutID ? Self::$bonusPayoutID : 9;
            switch ($bonusName) {
                case self::BONUS_GOLDMINE:
                    $withholdingWallet = 'withholdingGoldmine';
                    break;

                case self::BONUS_MATCHING:
                    $withholdingWallet = 'withholdingMatching';
                    break;

                case self::BONUS_JACKPOT:
                    $withholdingWallet = 'withholdingJackpot';
                    break;

                case self::BONUS_KSPONSOR:
                    $withholdingWallet = 'withholdingKSponsor';
                    break;

                case self::BONUS_SPONSOR:
                    $withholdingWallet = 'withholdingSponsor';
                    break;

                case self::BONUS_MONTHLY_POOL:
                    $withholdingWallet = 'withholdingPool';
                    break;

                default:
                    $dataOut['amount']  = $amount;
                    return $dataOut;
                    break;
            }

            /*if($maxCapType){
                $maxCap = Cash::getBalance($clientID, $maxCapType, $paidDate, "", $portfolioID);

                if($maxCap < $amount){
                    $payout = $maxCap;
                    $withholding = Setting::setDecimal(($amount - $maxCap), "");
                }else{
                    $payout = $amount;
                }

                if($payout > 0){
                    Cash::insertTAccount($clientID, $internalID, $maxCapType, $payout, $subject, $batchID, $referenceID, $paidDate, $batchID, $clientID);
                    Cash::getBalance($clientID, $maxCapType, $todayDate, "", $portfolioID);
                }
            }*/

            $validPayout = self::checkMemberStatus($clientID, $paidDate, "payout");
            if(!$validPayout){
                $withholding = $amount;
                $payout = 0;
            }else{
                $payout = $amount;
            }

            if($withholding > 0){
                Cash::insertTAccount($internalID, $clientID, $withholdingWallet, $withholding, $subject, $batchID, $referenceID, $paidDate, $batchID, $clientID);
            }

            $dataOut['amount']  = $payout;
            return $dataOut;
        }

        function getBonusSetting($bonusName){

            $db = MysqliDb::getInstance();
            $tableName  = "mlm_bonus";
            $column     = array(

                "calculation",
                "payment",
                "(SELECT value FROM mlm_bonus_setting WHERE mlm_bonus_setting.bonus_id = mlm_bonus.id AND mlm_bonus_setting.name = 'bonusOverriding') AS bonus_overriding"
            );

            $db->where("name", $bonusName);
            $result = $db->getOne($tableName, $column);

            return $result;
        }

        function getTotalCouple($clientIDAry, $bonusDate){
            $db = MysqliDb::getInstance();

            $db->where('name','member');
            $fizMember = $db->getValue('rank','id');

            $db->where('client_id',$clientIDAry,'IN');
            $db->where('rank_id',$fizMember);
            $db->where('DATE(created_at)',$bonusDate,'<=');
            $db->where('name','rankDisplay');
            $db->groupBy('client_id');
            $maxIDAry = $db->getValue('client_rank','MAX(id)',null);

            if($maxIDAry){
                $db->where('id',$maxIDAry,'IN');
                $fizMemberData = $db->map('client_id')->get('client_rank',null,'client_id, created_at');
            }

            if($fizMemberData){
                foreach($fizMemberData as $mapID => $mapDate){
                    $memberRankDate[$mapID] = $mapDate;
                }
            }

            foreach($clientIDAry as $clientIDRow){

                unset($totalCouple);
                if($memberRankDate[$clientIDRow]) $db->where('bonus_date',$memberRankDate[$clientIDRow],'>=');
                $db->where('client_id',$clientIDRow);
                $db->where('bonus_date',$bonusDate,'<=');
                $db->groupBy('client_id');
                $totalCouple = $db->getValue('mlm_bonus_couple','SUM(total_couple)');

                $clientCouple[$clientIDRow]['fizMemberDate'] = $memberRankDate[$clientIDRow];
                $clientCouple[$clientIDRow]['totalCouple'] = $totalCouple;

            }

            return $clientCouple;
        }

        public function accountStatusMaintain($bonusDate){
            $db = MysqliDb::getInstance();

            $date = date('Y-m-d',strtotime($bonusDate));
            $lastYearlyDate = date("Y-m-d", strtotime($date.' -1year  +1day'));

            $db->where('name','yearlyStartDate');
            $db->where('value',$lastYearlyDate); //change to + 1year - 1 day
            $yearlyClientIDAry = $db->getValue('client_setting','client_id',null);

            $bonusSetting      = Self::getBonusData();
            $joinPackProduct = $bonusSetting['enrollmentBonus']['Bonus Setting']['enrollmentProductCode']['value'];
            $joinPackProduct = explode("#",$joinPackProduct);

            $db->where('code',$joinPackProduct,'IN');
            $db->where('is_starter_kit','1');
            $db->where('status','Active');
            $joinPackIDAry = $db->getValue('mlm_product','id',null);

            if($yearlyClientIDAry){
                $db->where('name','yearlyStartDate');
                $db->where('client_id',$yearlyClientIDAry, 'IN');
                $getYearlyData = $db->get('client_setting',null,'client_id, value');

                foreach($getYearlyData as $getYearlyRow){
                    $clientID = $getYearlyRow['client_id'];
                    $currentYearlyDate = $getYearlyRow['value'];

                    //PV 50 checking
                    $db->where('b.client_id', $clientID);
                    $db->where('DATE(b.created_at)',$currentYearlyDate,'>=');
                    $db->where('DATE(b.created_at)',$date,'<');
                    $db->where("p.is_starter_kit", 0);
                    $db->join("mlm_product p", "p.id=b.product_id", "INNER");
                    $pvChecking[$clientID] = $db->getValue('mlm_bonus_in b','SUM(b.bonus_value)') ?:0;
                    if($pvChecking[$clientID] >= 50) $pvPass[$clientID] = 1;

                    //New recruit checking
                    $db->where('sponsor_id', $clientID);
                    $db->where('DATE(created_at)',$currentYearlyDate,'>=');
                    $db->where('DATE(created_at)',$date,'<');
                    $downlineData = $db->getValue('client','id',null);

                    if($downlineData){
                        $db->where('client_id',$downlineData,'IN');
                        $db->where('DATE(created_at)',$currentYearlyDate,'>=');
                        $db->where('DATE(created_at)',$date,'<');
                        $db->where('product_id',$joinPackIDAry,'IN');
                        $joinerRecruitChecking[$clientID] = $db->get('mlm_client_portfolio');
                        if($joinerRecruitChecking[$clientID]) $joinerPass[$clientID] = 1;
                    }

                    if($pvPass[$clientID] || $joinerPass[$clientID]){
                        //Renew Yearly Start Date
                        $db->where('name','yearlyStartDate');
                        $db->where('client_id',$clientID);
                        $updateNewYearlyStartDate = $db->update('client_setting', array("value" => $date));
                    }else{
                        //Terminate Account
                        $db->where("id", $clientID);
                        $updateStatus = $db->update('client', array("terminated" => 1));
                        if($updateStatus) {
                            $db->where('client_id',$clientID);
                            $db->where('name','terminatedAt');
                            $updateTerminated = $db->copy();
                            $recordChecking = $db->getOne('client_setting');
                            if($recordChecking){
                                $updateTerminated->update('client_setting',array("value" => $date));
                            }else{
                                $insertData = array(
                                    "name"      => "terminatedAt",
                                    "value"     => $date,
                                    "client_id" => $clientID,

                                );
                                $db->insert('client_setting',$insertData);
                            }
                        }
                    }
                }
            }

            return true;
        }

        public function getPlacementTreeUplines($clientID, $director = true) {

            $db = MysqliDb::getInstance();
            $tableName  = "tree_placement";
            $data       = array();
            $column     = array(

                "client_id",
                "client_position",
            );

            if ($director != true)
                $db->where("level", "0", ">");

            $db->where("client_id", $clientID);
            $traceKey = $db->getValue($tableName, "trace_key");

            $changed = str_replace(array('-1<', '-1>', '-1|'), ',', $traceKey);
            $uplineIDArray = explode(',', $changed, -1);
            //reverse make it's order descending
            $uplineIDArray = array_reverse($uplineIDArray);

            $db->where("client_id", $clientID);
            $uplineIDDetails = $db->getOne($tableName, $column);

            $data[] = $uplineIDDetails;

            foreach($uplineIDArray as $uplineID){

                $db->where("client_id", $uplineID);
                $uplineIDDetails = $db->getOne($tableName, $column);

                $data[] = $uplineIDDetails;
            }

            return $data;
        }

        public function getBonusPlacementTreeUplines($clientID, $bonusDate, $director = true) {

            $db = MysqliDb::getInstance();
            $tblDate = date("Ymd", strtotime($bonusDate));
            $tableName  = "tree_placement_cache_".$tblDate;
            $data       = array();
            $column     = array(

                "client_id",
                "client_position",
            );

            if ($director != true)
                $db->where("level", "0", ">");

            $db->where("client_id", $clientID);
            $traceKey = $db->getValue($tableName, "trace_key");

            $changed = str_replace(array('-1<', '-1>', '-1|'), ',', $traceKey);
            $uplineIDArray = explode(',', $changed, -1);
            //reverse make it's order descending
            $uplineIDArray = array_reverse($uplineIDArray);

            $db->where("client_id", $clientID);
            $uplineIDDetails = $db->getOne($tableName, $column);

            $data[] = $uplineIDDetails;

            foreach($uplineIDArray as $uplineID){

                $db->where("client_id", $uplineID);
                $uplineIDDetails = $db->getOne($tableName, $column);

                $data[] = $uplineIDDetails;
            }

            return $data;
        }

        function getSponsorTreeUplines($clientID, $limit, $includeSelf) {

            $db = MysqliDb::getInstance();
            $data       = array();

            if(!$sponsorTraceAry = Self::$sponsorTreeCache){
            	$db->where("client_id", $clientID);
            	$traceKey = $db->getValue("tree_sponsor_cache", "trace_key");
            }else{
            	$traceKey = $sponsorTraceAry[$clientID];
            }
            
            $uplineIDArray = explode("/", $traceKey);

            if ($includeSelf != true )
                unset($uplineIDArray[count($uplineIDArray) - 1]);

            if (!empty($limit)){
                for($count = 1; $count <= $limit; $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }
            else{
                for($count = 1; $count <= count($uplineIDArray); $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }

            return $data;
        }

        function getIntroducerTreeUplines($clientID, $limit, $includeSelf) {

            $db = MysqliDb::getInstance();
            $data       = array();

            if(!$introducerTraceAry = Self::$introducerTreeCache){
                $db->where("client_id", $clientID);
                $traceKey = $db->getValue("tree_introducer_cache", "trace_key");
            }else{
                $traceKey = $introducerTraceAry[$clientID];
            }
            
            $uplineIDArray = explode("/", $traceKey);

            if ($includeSelf != true )
                unset($uplineIDArray[count($uplineIDArray) - 1]);

            if (!empty($limit)){
                for($count = 1; $count <= $limit; $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }
            else{
                for($count = 1; $count <= count($uplineIDArray); $count++){
                    if (!empty($uplineIDArray[count($uplineIDArray) - $count]))
                        $data[] = $uplineIDArray[count($uplineIDArray) - $count];
                }
            }

            return $data;
        }

        public function insertBonusCalculationBatch($bonusName, $bonusDate, $isCompleted=0, $isPaid=0){
            $db = MysqliDb::getInstance();
            $bonusCreator = Bonus::$bonusCreator;

            if(!$bonusName || !$bonusDate) die(date("Y-m-d H:i:s")." Batch Argv not valid!");
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $bonusDate);

            $batchID = $db->getValue("mlm_bonus_calculation_batch", "ID");
            if(!$batchID){  

                $batchID = $db->getNewID();
                $insert = array(
                    "id" => $batchID,
                    "bonus_name" => $bonusName,
                    "bonus_date" => $bonusDate,
                    "completed" => $isCompleted,
                    "creator_id"=> $bonusCreator,
                    "created_at" => date("Y-m-d H:i:s")
                );

                $db->insert("mlm_bonus_calculation_batch", $insert);
            } elseif($isCompleted != 0){
                $update = array("completed" => 1);
                $db->where('id', $batchID);
                $db->update("mlm_bonus_calculation_batch", $update);
            } elseif($isPaid != 0){
                $update = array("paid" => 1);
                $db->where('id', $batchID);
                $db->update("mlm_bonus_calculation_batch", $update);
            }

            return $batchID;
        }

        public function calculateSponsorBonus($clientID,$params) {
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_SPONSOR;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            $bonusTime  = trim($params['bonusTime']);
            $bonusValue = trim($params['bonusValue']);
            $belongID   = trim($params['belongID']);

            if(!$bonusTime) $bonusTime = date('Y-m-d H:i:s');

            if(!$bonusValue){
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " Invalid Bonus Value. Failed to calculate.\n");
                return false;
            }

            $bonusDate = date('Y-m-d',strtotime($bonusTime));

            $db->where("bonus_name",$bonusName);
            $db->where("bonus_date",$bonusDate);
            $db->where("product_id",$productID);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(ID)");

            if($count > 0) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " has been paid for ".$bonusDate.". Failed to calculate.\n");
                return false;
            }

            $clientDataArr = Self::$clientDataAry;

            $internalID = Self::$bonusPayoutID;

            if(!$internalID) {
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $unitPrice = Self::$unitPrice;

            $paymentMethodAry = Self::$paymentMethod;
            $paymentMethod = $paymentMethodAry[$bonusName];

            $subject = $paymentMethod["subject"]; 
            foreach($paymentMethod['payment'] as $creditType => $percentage) {
                $percentageTotal += $percentage;
                $creditPercentage[$creditType] = $percentage;
            }

            if($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." payment total is not 100%. Failed to payout.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $bonusDate);
            $batchID = $db->getValue("mlm_bonus_calculation_batch", "ID");
            if(!$batchID){  
                $batchID = $db->getNewID();
                $insert = array(
                    "id" => $batchID,
                    "bonus_name" => $bonusName,
                    "bonus_date" => $bonusDate,
                    "created_at" => date("Y-m-d H:i:s")
                );
                $db->insert("mlm_bonus_calculation_batch", $insert);
            }
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            $directorID = Self::$directorID;

            $sponsorPercentage = $bonusSetting["Sponsor Bonus Payout"]["Bonus Percentage"]["value"];

            if(!$sponsorPercentage) {
                Log::write(date("Y-m-d H:i:s")." No Bonus Percentage.\n");
                return false;
            }

            $uplineID = $clientDataArr[$clientID]["introducer_id"];
            $calculatedAmount = Setting::setDecimal(($bonusValue * $sponsorPercentage / 100));
            $payableAmount = Setting::setDecimal(($calculatedAmount * $unitPrice), "");

            if($uplineID == $directorID || $uplineID == 0) return false;

            if(!$uplineID) {
                Log::write(date("Y-m-d H:i:s") . " Downline $clientID No Upline\n"); 
                return false;
            }

            if(!$sponsorPercentage) {
                Log::write(date("Y-m-d H:i:s") . " Upline $uplineID No percentage\n"); 
                return false;
            }

            if(!$payableAmount) {
                Log::write(date("Y-m-d H:i:s") . " Upline $uplineID No amount to pay\n");
                return false;
            }

            if($clientDataArr[$uplineID]["freezed"] == 1) {
                Log::write(date("Y-m-d H:i:s")." ClientID: ".$uplineID." : Freezed\n");
                return false;
            }

            if((!$clientDataArr[$uplineID]["portfolioCount"]) && ($clientDataArr[$uplineID]['activated'] != 2)) {
                Log::write(date("Y-m-d H:i:s")." ClientID: ".$uplineID." : No portfolio.\n");
                return false;
            }

            $validCalculate = self::checkMemberStatus($uplineID, $bonusTime, "calculate");

            if(!$validCalculate) {
                Log::write(date("Y-m-d H:i:s")." ClientID: ".$uplineID." : Inactive member.\n");
                return false;
            }

            if($payableAmount > 0) {
                unset($insertData);
                $insertData = array(
                    // "bonus_id" => $bonusID,
                    "client_id" => $uplineID,
                    "bonus_date" => $bonusDate,
                    // "game_id" => $gameID,
                    // "product_id" => $productID,
                    "from_client_id" => $clientID,
                    "from_amount" => $bonusValue,
                    "percentage" => $sponsorPercentage,
                    "calculated_amount" => $calculatedAmount,
                    "unit_price" => $unitPrice,
                    "payable_amount" => $payableAmount,
                    "batch_id" => $batchID,
                    "belong_id" => $belongID,
                    "created_at" => $bonusTime,
                );

                $bonusID = $db->insert("mlm_bonus_sponsor", $insertData);

                $db->where('bonus_date', date("Y-m-d", strtotime($bonusDate)));
                $db->where('client_id', $uplineID);
                $db->where('bonus_type', $bonusName);
                $bonusReportID = $db->getValue('mlm_bonus_report','id');

                if(!$bonusReportID) {
                    $insertData = array(
                        "client_id" => $uplineID,
                        "username" => $clientDataArr[$uplineID]["username"],
                        "name" => $clientDataArr[$uplineID]["name"],
                        "bonus_date" => date("Y-m-d", strtotime($bonusDate)),
                        "bonus_type" => $bonusName,
                        "bonus_amount" => $payableAmount,
                    );
                    $db->insert("mlm_bonus_report", $insertData);
                } else {
                    $db->where("id", $bonusReportID);
                    $db->update("mlm_bonus_report",array("bonus_amount" => $db->inc($payableAmount)));
                }

                $capData = self::checkMaxCap($uplineID, $bonusName, $subject, $payableAmount, $bonusTime, $batchID);
                $getAmount = $capData["amount"];

                Log::write("c:".$uplineID." payableAmount: ".$payableAmount." afterCap: ".$getAmount."\n");

                unset($totalAmount);
                foreach($creditPercentage as $creditType => $value) {
                    $percentage = $value / 100;
                    $amount = Setting::setDecimal(($getAmount * $percentage), ""); 

                    if (($totalAmount + $amount) > $getAmount && $totalAmount > 0) {
                        Log::write(date("Y-m-d H:i:s")." Amount not equal convert: ".$amount);
                        $amount = $payableAmount - $totalAmount;
                        Log::write(" > ".$amount."\n");
                    }

                    if($amount > 0) { 
                        Cash::insertTAccount($internalID, $uplineID, $creditType, $amount, $subject, $belongID, "", $bonusTime, $batchID, $uplineID, "", "", "", "", $unitPrice);
                        $totalAmount += $amount;
                    }
                }

                $db->where("id", $bonusID);
                $db->update("mlm_bonus_sponsor", array("paid" => 1));
            }

            return true;
        }

        public function insertInventory($bonusDate, $category){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $bonusName      = "insertInventory";

            if(!$bonusDate){
                Log::write(date("Y-m-d H:i:s")." Invalid Time.\n");
                return false;
            }

            if(!$category){
                Log::write(date("Y-m-d H:i:s")." Invalid Product.\n");
                return false;
            }

            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $bonusDate);
            $db->where("product_category", $category);
            $db->where("completed", 1);
            $count = $db->getValue("mlm_bonus_calculation_batch", "count(ID)");
            if ($count > 0) {
                Log::write(date("Y-m-d H:i:s")." ".$productID." ".$bonusName . " has been paid for ".$bonusDate.". Failed to calculate.\n");
                return false;
            }

            $batchID = Bonus::insertBonusCalculationBatch($category, $bonusName, $bonusDate);

            $clientDataAry = self::$clientDataAry;
            $gameRoomDetailAry = self::$gameRoomDetailAry;
            $gameRoomIDAry = self::$gameRoomIDAry;

            if(!$gameRoomIDAry){
                Log::write(date("Y-m-d H:i:s")." Completed Room is empty.\n");
                return false;
            }

            //Get Category Setting
            $db->where('name',"minWin");
            $db->where('ref_id',$category);
            $db->where('active_at',$bonusDate,"<=");
            $db->orderBy('active_at','DESC');
            $db->orderBy('id','DESC');
            $minWin = $db->getValue('system_settings_admin','value');

            $db->where("game_id", $gameRoomIDAry, "IN");
            $db->where("winner", "1");
            $gameDetailRes = $db->get("game_detail", null, "game_id, client_id, portfolio_id, winner");
            foreach ($gameDetailRes as $gameDetailRow) {
                $clientIDArr[$gameDetailRow['client_id']] = $gameDetailRow['client_id'];
                if($gameDetailRow['portfolio_id']>0){
                    $portfolioIDArr[$gameDetailRow['portfolio_id']] = $gameDetailRow['portfolio_id'];
                }
            }

            if($clientIDArr){
                $db->where('client_id',$clientIDArr,"IN");
                $db->where('name',array('thisMonthWon','bonusCountDate'),"IN");
                $clientWinRes = $db->get('client_setting',null,'client_id,name,value,reference,description');
                foreach ($clientWinRes as $clientWinRow) {
                    switch ($clientWinRow['name']) {
                        case 'thisMonthWon':
                            $clientWinData[$clientWinRow['client_id']][$clientWinRow['reference']] = $clientWinRow['value'];
                            break;
                        
                        case 'bonusCountDate':
                            $clientJoinGame[$clientWinRow['client_id']] = $clientWinRow['description'];
                            break;
                    }
                }
            }

            if($portfolioIDArr){
                $db->where('id',$portfolioIDArr,"IN");
                $portfolioData = $db->map('id')->get('mlm_client_portfolio',null,'id,product_id');
            }

            foreach ($gameDetailRes as $gameDetailRow) {
                $clientID    = $gameDetailRow["client_id"];
                $portfolioID = $gameDetailRow["portfolio_id"];
                $productID   = $portfolioData[$portfolioID];
                $winCount    = $clientWinData[$clientID][$productID];
                unset($updateData);

                if($clientID == 8888) continue;

                Log::write(date("Y-m-d H:i:s")." c: ".$clientID." p: ".$portfolioID." wc : ".$winCount."\n");

                Inventory::insertInventoryData($portfolioID, $bonusDate);
                $updateData['redeemed_at'] = $bonusDate;
                $updateData['status'] = 'Matured';

                $db->where('id',$portfolioID);
                $db->update('mlm_client_portfolio',$updateData);

                if(!$clientJoinGame[$clientID]){
                    if($winCount == $minWin){
                        //Set Member this cycle always Active
                        Game::setMemberCycleActive($clientID,$bonusDate);

                        Game::insertMthPoolWinner($clientID,$batchID,$bonusDate,"monthly");

                    }elseif($winCount < $minWin){
                        // Inactive member when Matured
                        Custom::updateMemberActiveStatus($clientID, $bonusDate,null,$category);
                    }
                }
            }

            $batchID = Bonus::insertBonusCalculationBatch($category, $bonusName, $bonusDate, 1);

            return true;
        }

        public function calculateGoldmineBonus($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_GOLDMINE;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if(!$bonusDate){
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " Invalid Bonus Date. Failed to calculate.\n");
                return false;
            }

            $db->where("bonus_name",$bonusName);
            $db->where("bonus_date",$bonusDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(ID)");
            if($count > 0) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " has been paid for ".$bonusDate.". Failed to calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            if($bonusSetting['calculate'] != 'Daily'){
                // if is not daily then nid to check if is valid calculation date
                if($bonusSetting['calculate'] == 'Monthly'){
                    // check if is last day of the month (t = number of days of given month)
                    if(date("Y-m-t", strtotime($bonusDate)) != $bonusDate){
                        Log::write(date("Y-m-d H:i:s")." ".$bonusName." ".$bonusDate." not last day of month. Failed to proceed. \n");
                        Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                        return false;
                    }

                    $fromDate = date('Y-m-01', strtotime($bonusDate));
                }else{
                    // if instant/hourly or others unstatement calculation will stopped here
                    Log::write(date("Y-m-d H:i:s")." ".$bonusName." haven't handled ".$bonusSetting['calculate']." case\n");
                    Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                    return false;
                }
            }

            //Get Special Calculate Sales Setting
            $db->where('name','spCalSalesDate');
            $spCalSalesRes = $db->getOne('system_settings','value,reference');
            $spSalesFrom = $spCalSalesRes['value'];
            $spSalesPeriod = explode("#", $spCalSalesRes['reference']);

            if((strtotime($bonusDate) == strtotime($spSalesPeriod[1]))){
                $fromDate = $spSalesFrom;
                Log::write(date("Y-m-d H:i:s") . " Special Calculate Sales Period. From ".$fromDate." to End ".$bonusDate."\n");
            }

            $clientDataAry = Self::$clientDataAry;

            $internalID = Self::$bonusPayoutID;

            if(!$internalID) {
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $unitPrice = Self::$unitPrice;
            if (!$unitPrice) {
                Log::write(date("Y-m-d H:i:s") . " Unit value is empty, do not continue.\n");
                return false;
            }

            $directorID = Self::$directorID;

            $filterDate    = date('Y-m-d 23:59:59',strtotime($bonusDate));
            $clientRankArr = Self::getClientRank("Bonus Tier", "", $filterDate, $bonusName);
            $breakOutRank  = $bonusSetting['Bonus Setting']['breakOutRank']['value'];
            $includeOwnSalesRank  = $bonusSetting['Bonus Setting']['includeOwnSalesRank']['value'];

            $db->where('type','Bonus Tier');
            $rankIDArr = $db->map('id')->get('rank',null,'id,priority');

            if($rankIDArr){
                $db->where('rank_id',array_keys($rankIDArr),"IN");
                $db->where('name','goldmineBonusPercentage');
                $rankData = $db->map('rank_id')->get('rank_setting',null,'rank_id,value AS percentage, reference AS levelLimit');
            }

            if($fromDate){
                $db->where('DATE(created_at)', $fromDate, ">=");
                $db->where('DATE(created_at)', $bonusDate,"<=");
            }else{
                $db->where('DATE(created_at)', $bonusDate);
            }
            $db->groupBy('client_id');
            $bonusInRes = $db->get('mlm_bonus_in', null,'id, client_id, SUM(bonus_value) AS bonusValue');
            foreach ($bonusInRes as $bonusInRow) {
                $clientID = $bonusInRow['client_id'];
                $bonusValue = $bonusInRow['bonusValue'];
                $ownRankID = $clientRankArr[$clientID]['rank_id'];
                $includeOwnSales = false;
                unset($lastClientID);

                Log::write(date("Y-m-d H:i:s")." Bonus ID : ".$bonusInRow['id']." Client ID : ".$clientID." BV : ".$bonusValue."\n");

                if($rankIDArr[$ownRankID]>=$includeOwnSalesRank) $includeOwnSales = true;

                // Executive Rank will not pass anything.
                if($rankIDArr[$ownRankID] == $breakOutRank) continue;

                $uplineIDArr = Self::getSponsorTreeUplinesCache($clientID,null,$includeOwnSales);
                $level = 0;
                foreach ($uplineIDArr as $uplineID) {
                    $rankID = $clientRankArr[$uplineID]['rank_id'];
                    $percentage = $clientRankArr[$uplineID]['percentage'];
                    $levelLimit = $rankData[$rankID]['levelLimit']; // 0 = No Limit
                    $level++;

                    // First loop will be from client as direct client id
                    if(!$lastClientID){
                        $lastClientID = $clientID;
                    }

                    $directClientID = $lastClientID;
                    // Rank Exec & above will be direct client id
                    if($rankIDArr[$ownRankID] >= $breakOutRank){
                        $directClientID = $clientID;
                    }

                    $lastClientID = $uplineID;

                    if($uplineID == $directorID) continue;

                    if(!$rankID){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No rank.\n");
                        continue;
                    }

                    if(!$percentage){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No percentage.\n");
                        continue;
                    }

                    if(($levelLimit>0) && ($levelLimit < $level)){
                        Log::write(date("Y-m-d H:i:s") . " u:".$uplineID." - no valid level.  UL: ".$levelLimit." CL: ".$level."\n");
                        continue;
                    }

                    $calcAmt = Setting::setDecimal($bonusValue * ($percentage/100), "");
                    $payAmt = Setting::setDecimal(($calcAmt * $unitPrice), "");

                    if($payAmt <= 0 ) continue;

                    Log::write(date("Y-m-d H:i:s") . " d: ".$clientID." u: ".$uplineID." lvl:".$level." calc:".$bonusValue."*(".$percentage."/100):".$calcAmt."\n");

                    $insertData = array(
                        'client_id'         => $uplineID,
                        'bonus_date'        => $bonusDate,
                        'rank_id'           => $rankID,
                        'direct_client_id'  => $directClientID,
                        'direct_rank_id'    => $clientRankArr[$directClientID]['rank_id'],
                        'from_client_id'    => $clientID,
                        'from_rank_id'      => $ownRankID,
                        'from_level'        => ($clientDataAry[$clientID]['level'] - $clientDataAry[$uplineID]['level']),
                        'compress_level'    => $level,
                        'from_amount'       => $bonusValue,
                        'percentage'        => $percentage,
                        'calculated_amount' => $calcAmt,
                        'unit_price'        => $unitPrice,
                        'payable_amount'    => $payAmt,
                        'batch_id'          => $batchID,                        
                        'created_at'        => date('Y-m-d H:i:s'),
                    );
                    $db->insert("mlm_bonus_goldmine", $insertData);
                    $clientBonusArray[$uplineID] += $payAmt;

                    // Stop, when pass to Exec & above Rank
                    if($rankIDArr[$rankID] >= $breakOutRank) break;
                }
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                    "client_id"         => $clientID,
                    "country_id"        => $clientDataAry[$clientID]["country_id"],
                    "bonus_date"        => $bonusDate,
                    "bonus_type"        => $bonusName,
                    "bonus_amount"      => $totalAmount,
                );
                $db->insert("mlm_bonus_report", $insertData);
            }

            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

        public function calculateTeamBonus($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_TEAM;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if(!$bonusDate){
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " Invalid Bonus Date. Failed to calculate.\n");
                return false;
            }

            $db->where("bonus_name",$bonusName);
            $db->where("bonus_date",$bonusDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(ID)");
            if($count > 0) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName . " has been paid for ".$bonusDate.". Failed to calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            if($bonusSetting['calculate'] != 'Daily'){
                // if is not daily then nid to check if is valid calculation date
                if($bonusSetting['calculate'] == 'Monthly'){
                    // check if is last day of the month (t = number of days of given month)
                    if(date("Y-m-t", strtotime($bonusDate)) != $bonusDate){
                        Log::write(date("Y-m-d H:i:s")." ".$bonusName." ".$bonusDate." not last day of month. Failed to proceed. \n");
                        Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                        return false;
                    }

                    $fromDate = date('Y-m-01', strtotime($bonusDate));
                }else{
                    // if instant/hourly or others unstatement calculation will stopped here
                    Log::write(date("Y-m-d H:i:s")." ".$bonusName." haven't handled ".$bonusSetting['calculate']." case\n");
                    Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                    return false;
                }
            }

            //Get Special Calculate Sales Setting
            $db->where('name','spCalSalesDate');
            $spCalSalesRes = $db->getOne('system_settings','value,reference');
            $spSalesFrom = $spCalSalesRes['value'];
            $spSalesPeriod = explode("#", $spCalSalesRes['reference']);

            if((strtotime($bonusDate) == strtotime($spSalesPeriod[1]))){
                $fromDate = $spSalesFrom;
                Log::write(date("Y-m-d H:i:s") . " Special Calculate Sales Period. From ".$fromDate." to End ".$bonusDate."\n");
            }

            $clientDataAry = Self::$clientDataAry;

            $internalID = Self::$bonusPayoutID;

            if(!$internalID) {
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $unitPrice = Self::$unitPrice;
            if (!$unitPrice) {
                Log::write(date("Y-m-d H:i:s") . " Unit value is empty, do not continue.\n");
                return false;
            }

            $directorID = Self::$directorID;

            $filterDate    = date('Y-m-d 23:59:59',strtotime($bonusDate));
            $clientRankArr = Self::getClientRank("Bonus Tier", "", $filterDate, $bonusName);
            $skipFirstGen  = $bonusSetting['Bonus Setting']['skipFirstGen']['value'];
            $spPercentage  = $bonusSetting['Bonus Setting']['spPercentage']['value'];
            $spRankPriority= $bonusSetting['Bonus Setting']['spPercentage']['reference'];
            $spReceiverRankData= $bonusSetting['Rank Setting']['spReceiverRank'];

            foreach ($spReceiverRankData as $spReceiverRankRow) {
                $spReceiverRankArr[$spReceiverRankRow['value']] = explode("#", $spReceiverRankRow['reference']);
            }

            $db->where('type','Bonus Tier');
            $rankIDArr = $db->map('id')->get('rank',null,'id,priority');

            if($fromDate){
                $db->where('DATE(created_at)', $fromDate, ">=");
                $db->where('DATE(created_at)', $bonusDate,"<=");
            }else{
                $db->where('DATE(created_at)', $bonusDate);
            }
            $db->groupBy('client_id');
            $bonusInRes = $db->get('mlm_bonus_in', null,'id, client_id, SUM(bonus_value) AS bonusValue');
            foreach ($bonusInRes as $bonusInRow) {
                $clientID = $bonusInRow['client_id'];
                $bonusValue = $bonusInRow['bonusValue'];
                $ownRankID = $clientRankArr[$clientID]['rank_id'];
                $lastUplineRankID = $ownRankID;
                $validUpline = 1;
                $isSpPercentage = 0;

                // Below skipFirstGen rank priority will skip for first generation valid upline
                if($rankIDArr[$ownRankID] <= $skipFirstGen) $validUpline = 0;

                if($rankIDArr[$ownRankID] >= $spRankPriority) $isSpPercentage = 1;

                Log::write(date("Y-m-d H:i:s")." Bonus ID : ".$bonusInRow['id']." Client ID : ".$clientID." BV : ".$bonusValue."\n");

                $uplineIDArr = Self::getSponsorTreeUplinesCache($clientID,null,false);
                foreach ($uplineIDArr as $uplineID) {
                    $rankID = $clientRankArr[$uplineID]['rank_id'];
                    $percentage = $clientRankArr[$uplineID]['percentage'];
                    $levelLimit = $rankData[$rankID]['levelLimit']; // 0 = No Limit
                    $spReceiverRank = $spReceiverRankArr[$rankID];

                    if($uplineID == $directorID) continue;

                    //This checking is if last loop user is Director & above, then percentage will become 2%
                    if(($percentage > 0) && ($isSpPercentage == 1)){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." Bonus Percentage : ".$percentage."% change to");
                        $percentage = $spPercentage;
                        Log::write(" ".$percentage."%\n");
                    }

                    if(!$rankID){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No rank.\n");
                        continue;
                    }

                    if(!$percentage){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No percentage.\n");
                        continue;
                    }

                    //This checking is for skip first generation Exec, next exec & above only can receive bonus
                    if($validUpline == 0){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." Rank ID : ".$rankID." Skip Frist Generation Upline.\n");
                        $validUpline = 1;
                        if($rankIDArr[$rankID] >= $spRankPriority){
                          $isSpPercentage = 1;
                          $lastUplineRankID = $rankID;
                        }
                        continue;
                    }

                    //This checking is for only Exec can receive Director & above Bonus(2%)
                    if(($isSpPercentage == 1) && (!in_array($lastUplineRankID, $spReceiverRank))){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." Rank ID : ".$rankID." Last Upline Rank ID : ".$lastUplineRankID.". cannot receive Certain Rank Bonus, Break Loop.\n");
                        break;
                    }

                    $calcAmt = Setting::setDecimal($bonusValue * ($percentage/100), "");
                    $payAmt = Setting::setDecimal(($calcAmt * $unitPrice), "");

                    if($payAmt <= 0 ) continue;

                    Log::write(date("Y-m-d H:i:s") . " d: ".$clientID." u: ".$uplineID." lvl:".$level." calc:".$bonusValue."*(".$percentage."/100):".$calcAmt."\n");

                    $insertData = array(
                        'client_id'         => $uplineID,
                        'bonus_date'        => $bonusDate,
                        'rank_id'           => $rankID,
                        'from_client_id'    => $clientID,
                        'from_rank_id'      => $ownRankID,
                        'from_level'        => ($clientDataAry[$clientID]['level'] - $clientDataAry[$uplineID]['level']),
                        'from_amount'       => $bonusValue,
                        'percentage'        => $percentage,
                        'calculated_amount' => $calcAmt,
                        'unit_price'        => $unitPrice,
                        'payable_amount'    => $payAmt,
                        'batch_id'          => $batchID,                        
                        'created_at'        => date('Y-m-d H:i:s'),
                    );
                    $db->insert("mlm_bonus_team", $insertData);
                    $clientBonusArray[$uplineID] += $payAmt;

                    // Pass 1 time only
                    break;
                }
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                    "client_id"         => $clientID,
                    "country_id"        => $clientDataAry[$clientID]["country_id"],
                    "bonus_date"        => $bonusDate,
                    "bonus_type"        => $bonusName,
                    "bonus_amount"      => $totalAmount,
                );
                $db->insert("mlm_bonus_report", $insertData);
            }

            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

        public function calculateLeadershipBonus($bonusDate){
            $db = MysqliDb::getInstance();

            $bonusName = Self::BONUS_LEADERSHIP;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if (!$bonusDate) {
                Log::write(date("Y-m-d H:i:s")." bonusDate not found!\n");
                return false;
            }

            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $insertDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($count > 0){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $insertDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            if($bonusSetting['calculate'] != 'Daily'){
                // if is not daily then nid to check if is valid calculation date
                if($bonusSetting['calculate'] == 'Monthly'){
                    // check if is last day of the month (t = number of days of given month)
                    if(date("Y-m-t", strtotime($bonusDate)) != $bonusDate){
                        Log::write(date("Y-m-d H:i:s")." ".$bonusName." ".$bonusDate." not last day of month. Failed to proceed. \n");
                        Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                        return false;
                    }

                    $fromDate = date('Y-m-01', strtotime($bonusDate));
                }else{
                    // if instant/hourly or others unstatement calculation will stopped here
                    Log::write(date("Y-m-d H:i:s")." ".$bonusName." haven't handled ".$bonusSetting['calculate']." case\n");
                    Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                    return false;
                }
            }

            //Get Special Calculate Sales Setting
            $db->where('name','spCalSalesDate');
            $spCalSalesRes = $db->getOne('system_settings','value,reference');
            $spSalesFrom = $spCalSalesRes['value'];
            $spSalesPeriod = explode("#", $spCalSalesRes['reference']);

            if((strtotime($bonusDate) == strtotime($spSalesPeriod[1]))){
                $fromDate = $spSalesFrom;
                Log::write(date("Y-m-d H:i:s") . " Special Calculate Sales Period. From ".$fromDate." to End ".$bonusDate."\n");
            }

            //get unit price
            $unitPrice = Self::$unitPrice;
            if (!$unitPrice) {
                Log::write(date("Y-m-d H:i:s") . " Unit value is empty, do not continue.\n");
                return false;
            }

            $db->where('type', 'Bonus Tier');
            $db->orderBy('priority', 'ASC');
            $rankDataArr = $db->map('id')->get('rank', NULL, 'id, priority');
            // print_r($rankNameID);

            $topLvl = 0;
            $bonusLevelPercentage = $bonusSetting['Level']['leadershipBonusPercentage'];
            foreach ($bonusLevelPercentage as $bonuslevelData) {
                $levelPercentage[$bonuslevelData['reference']] = $bonuslevelData['value'];

                if($topLvl < $bonuslevelData['reference']){
                    $topLvl = $bonuslevelData['reference'];
                }
            }
            krsort($levelPercentage);

            $noLevelLimitBonusPercentage = $bonusSetting['Bonus Setting']['isNoLevelLimit']['value'];
            $noLevelLimitRankPriority = $bonusSetting['Bonus Setting']['isNoLevelLimit']['reference'];
            $levelBreakPriority = $bonusSetting['Bonus Setting']['levelBreak']['value'];
            $skipFirstGen = $bonusSetting['Bonus Setting']['skipFirstGen']['value'];
            $excludeRankSales = $bonusSetting['Bonus Setting']['excludeRankSales']['value'];

            $clientDataArr = Self::$clientDataAry;
            $filterDate    = date('Y-m-d 23:59:59',strtotime($bonusDate));
            $clientRankArr = Self::getClientRank("Bonus Tier", "", $filterDate, $bonusName, null);

            if($fromDate){
                $db->where('DATE(created_at)', $fromDate, ">=");
                $db->where('DATE(created_at)', $bonusDate,"<=");
            }else{
                $db->where('DATE(created_at)', $bonusDate);
            }
            $db->groupBy('client_id');
            $bonusInRes = $db->get('mlm_bonus_in', null, 'client_id, SUM(bonus_value) AS bonusValue');
            $directorID      = Self::$directorID;

            //Note: this bonus no compress logic. But the level is based on Fiz Driector & above Group. Mean found an Director & above Rank only will count as 1 level
            foreach ($bonusInRes as $bonusInRow) {
                $clientID       = $bonusInRow['client_id'];
                $bonusValue     = $bonusInRow['bonusValue'];
                $latestPercent  = 0;
                $clientRankID   = $clientRankArr[$clientID]['rank_id'];
                $validUpline    = 1;

                Log::write(date("Y-m-d H:i:s")." Client ID : ".$clientID." BV : ".$bonusValue." Rank ID : ".$clientRankID."\n");

                //Below SkipFirstGen will rank priority will skip for next Valid Upline.
                if($rankDataArr[$clientRankID] <= $skipFirstGen) $validUpline = 0;

                // Own Rank Hit Breakout Priority.
                if($rankDataArr[$clientRankID] == $levelBreakPriority){
                    Log::write(date("Y-m-d H:i:s")." Client ID : ".$clientID." Rank ID : ".$rankDataArr[$clientRankID]." Own Rank Hit Breakout Priority. Skip..\n");
                    continue;
                }

                //Exclude Rank sales for this bonus
                if($clientRankID == $excludeRankSales){
                    Log::write(date("Y-m-d H:i:s")." Client ID : ".$clientID." Rank ID : ".$clientRankID." Exlude this rank sales for this bonus. Skip..\n");
                    continue;
                }

                $level = 1;
                $uplineIDArr = Self::getSponsorTreeUplinesCache($clientID, null, false);
                foreach ($uplineIDArr as $key => $uplineID) {
                    unset($percentage);
                    $rankID         = $clientRankArr[$uplineID]['rank_id'];
                    $uplineMaxlevel = $clientRankArr[$uplineID]['percentage'];

                    if($uplineID == $directorID) continue;

                    if(!$rankID){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No rank.\n");
                        continue;
                    }

                    if(!$uplineMaxlevel){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No percentage.\n");
                        continue;
                    }

                    //This checking is for skip first generation Director, next Director & above only can receive bonus
                    if($validUpline == 0){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." Rank ID : ".$rankID." Skip Frist Generation Upline.\n");
                        $validUpline = 1;
                        if($rankID == $levelBreakPriority){
                            Log::write(date("Y-m-d H:i:s")." uplineID: $uplineID rankID: $rankID level: $level. Hit highest Rank, Break it.\n"); 
                            break;
                        }else{
                            continue;
                        }
                    }

                    if(($level > $uplineMaxlevel) && ($rankDataArr[$rankID] != $noLevelLimitRankPriority)){
                        Log::write(date("Y-m-d H:i:s")." uplineID: $uplineID rankID: $rankID level: $level. Not qualify for this bonus\n"); 
                        $level++;
                        continue;
                    }

                    foreach ($levelPercentage as $bonusLevel => $bonusPercentage) {
                        if($level >= $bonusLevel){
                            $percentage = $bonusPercentage;
                            break;
                        }
                    }

                    if($level > $topLvl && $rankID == $noLevelLimitRankPriority){
                        $percentage = $noLevelLimitBonusPercentage;
                    }

                    if(!$percentage){
                        Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." No percentage.\n");
                        continue;
                    }

                    $fromAmount         = Setting::setDecimal($bonusValue);
                    $amount             = Setting::setDecimal(($bonusValue * ($percentage /100)), "");
                    $payableAmount      = Setting::setDecimal(($amount * $unitPrice),"");
                    Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." uplineID ".$uplineID." level : ".$level." percentage: $percentage * unit_price ".$unitPrice." =  payable_amount ".$payableAmount.".\n");

                    $diffLevel = $clientDataArr[$clientID]['level'] - $clientDataArr[$uplineID]['level'];
                    if($payableAmount > 0){
                        $insertData = array(
                                            "bonus_date"        => $bonusDate,
                                            "client_id"         => $uplineID,
                                            "rank_id"           => $rankID,
                                            "from_id"           => $clientID,
                                            "from_rank_id"      => $clientRankID,
                                            "from_level"        => $diffLevel,
                                            "compress_level"    => $level,
                                            "from_amount"       => $fromAmount,
                                            "percentage"        => $percentage,
                                            "amount"            => $amount,
                                            "unit_price"        => $unitPrice,
                                            "payable_amount"    => $payableAmount,
                                            "batch_id"          => $batchID,
                                            "created_at"        => date('Y-m-d H:i:s'),
                                        );
                        $db->insert("mlm_bonus_leadership",$insertData);
                    }

                    $clientBonusArray[$uplineID]['bonusAmt'] += $amount;
                    $level ++;
                    if($rankID == $levelBreakPriority) break;
                }
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                                        "client_id"         => $clientID,
                                        "country_id"        => $clientDataArr[$clientID]["country_id"],
                                        "bonus_date"        => $bonusDate,
                                        "bonus_type"        => $bonusName,
                                        "bonus_amount"      => $totalAmount['bonusAmt'],
                                    );
               $insertAll[] = $insertData;
            }            

            if($insertAll) $db->insertMulti("mlm_bonus_report", $insertAll);

            // Update the batch table to completed
            self::insertBonusCalculationBatch($bonusName, date("Y-m-d", strtotime($bonusDate)), 1);
            Log::write(date("Y-m-d H:i:s") . " Calculated Done $bonusName for " . $bonusDate . "\n");
        }

        public function insertRankMonthly($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = "Rank Monthly";
            $insertDate = date('Y-m-d 23:59:59',strtotime($bonusDate));
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

            $clientDataAry = Self::$clientDataAry;
            $directorID = Self::$directorID;

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            $clientRankArr = Self::getClientRank("Bonus Tier", "", $insertDate, "goldmineBonus","System");

            //Get Award Bonus Setting
            $db->where('name',array('directorAward','unicornAward'),"IN");
            $awardBonusStgRes = $db->get('mlm_bonus_setting',null,'name,reference');
            foreach ($awardBonusStgRes as $awardBonusStgRow) {
                $awardReqArr = explode("#", $awardBonusStgRow['reference']);
                $awardBonusStgArr[$awardBonusStgRow['name']]['minRankPriority'] = $awardReqArr[0];
                $awardBonusStgArr[$awardBonusStgRow['name']]['minPGP'] = $awardReqArr[2];
            }

            //Get System Setting
            $db->where('name','extendOptionDuration');
            $settingRes = $db->getOne('system_settings','value,reference');
            $extendOptionDuration = $settingRes['value'];
            $minOptionPriority = $settingRes['reference'];

            //Get Client Sales
            $db->where('DATE(updated_at)',$monthFirstDate,">=");
            $db->where('DATE(updated_at)',$bonusDate,"<=");
            $clientSalesRes = $db->get('client_sales_cache',null,'client_id,pgp_sales,own_sales');
            foreach ($clientSalesRes as $clientSalesRow) {
                $clientPGPSales[$clientSalesRow['client_id']] = ($clientSalesRow['pgp_sales'] + $clientSalesRow['own_sales']);
            }

            // Get Rank Data
            $rankData = $db->map('id')->get('rank',null,'id,priority');

            Log::write(date("Y-m-d H:i:s")." Insert Client Rank Monthly for ".$bonusDate.".\n");
            foreach ($clientRankArr as $clientID => $clientRankRow) {
                unset($insertData,$updateData,$isEntitle);
                //Director Rank = type column
                if(($rankData[$clientRankRow['rank_id']] == $awardBonusStgArr['directorAward']['minRankPriority']) && ($clientPGPSales[$clientID]>=$awardBonusStgArr['directorAward']['minPGP'])){
                    $updateData['type'] = $db->inc(1);
                    $isEntitle = 1;

                }elseif(($rankData[$clientRankRow['rank_id']] == $awardBonusStgArr['unicornAward']['minRankPriority']) && ($clientPGPSales[$clientID]>=$awardBonusStgArr['unicornAward']['minPGP'])){
                    $updateData['reference'] = $db->inc(1);
                    $isEntitle = 1;
                }

                Log::write(date("Y-m-d H:i:s") . " Client : ".$clientID." Rank : ".$clientRankRow['rank_id']." PGP Sales + Own Sales : ".$clientPGPSales[$clientID]."\n");

                $insertData = array(
                    "client_id" => $clientID,
                    "rank_id"   => $clientRankRow['rank_id'],
                    "batch_id"  => $batchID,
                    "entitle_award" => $isEntitle,
                    "created_at"=> $insertDate,
                );
                $db->insert('client_rank_monthly',$insertData);

                // Update Client Setting
                if($updateData){
                    $db->where('client_id',$clientID);
                    $db->where('name','awardCycleDate');
                    $db->update('client_setting',$updateData);
                }

                unset($extendData);
                if($rankData[$clientRankRow['rank_id']] >= $minOptionPriority){
                    $newExtendDate = date('Y-m-t',strtotime('first day of +'.$extendOptionDuration.$bonusDate));

                    $extendData['value'] = $clientRankRow['rank_id'];
                    $extendData['reference'] = $newExtendDate;
                    $extendData['description'] = $insertDate;

                    $db->where('client_id',$clientID);
                    $db->where('name','extraPVPOption');
                    $extraOptionRes = $db->getOne('client_setting','id,value,reference');
                    $extraOptionID = $extraOptionRes['id'];
                    $extraRankID = $extraOptionRes['value'];
                    $extendDate = $extraOptionRes['reference'];

                    if((strtotime($extendDate)<strtotime($bonusDate))) unset($extraRankID);
                    
                    if(($extraOptionID) && ($rankData[$clientRankRow['rank_id']]>=$rankData[$extraRankID])){
                        $db->where('id',$extraOptionID);
                        $db->update("client_setting",$extendData);
                    }elseif(!$extraOptionID){
                        $extendData['client_id'] = $clientID;
                        $extendData['name'] = "extraPVPOption";
                        $db->insert("client_setting",$extendData);
                    }
                }
            }
            Log::write(date("Y-m-d H:i:s")." Finish Insert Client Rank Monthly for ".$bonusDate.".\n");

            Log::write(date("Y-m-d H:i:s")." Recalculate All Client Rank for ".$bonusDate.".\n");

            foreach ($clientDataAry as $insertCleintID => $clientDataRow) {

                if($insertCleintID == $directorID) continue;

                unset($insertData,$jsonData);
                $jsonData['dateTime'] = date('Y-m-d 00:00:00',strtotime($bonusDate." +1 days"));
                $insertData = array(
                    "queue_type" => "calculateRank",
                    "client_id"  => $insertCleintID,
                    "data"       => json_encode($jsonData),
                    "created_at" => date('Y-m-d H:i:s'),
                );
                $db->insert('queue',$insertData);
            }

            Log::write(date("Y-m-d H:i:s")." Finish Recalculate All Client Rank for ".$bonusDate.".\n");

            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

        public function calculateAwardBonus($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_AWARD;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

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

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            if($bonusSetting['calculate'] != 'Daily'){
                // if is not daily then nid to check if is valid calculation date
                if($bonusSetting['calculate'] == 'Monthly'){
                    // check if is last day of the month (t = number of days of given month)
                    if(date("Y-m-t", strtotime($bonusDate)) != $bonusDate){
                        Log::write(date("Y-m-d H:i:s")." ".$bonusName." ".$bonusDate." not last day of month. Failed to proceed. \n");
                        Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                        return false;
                    }

                    $fromDate = date('Y-m-01', strtotime($bonusDate));
                }else{
                    // if instant/hourly or others unstatement calculation will stopped here
                    Log::write(date("Y-m-d H:i:s")." ".$bonusName." haven't handled ".$bonusSetting['calculate']." case\n");
                    Self::insertBonusCalculationBatch($bonusName, $bonusDate,1);
                    return false;
                }
            }

            $clientDataAry = Self::$clientDataAry;
            $directorID = Self::$directorID;

            $unitPrice = Self::$unitPrice;
            if (!$unitPrice) {
                Log::write(date("Y-m-d H:i:s") . " Unit value is empty, do not continue.\n");
                return false;
            }

            foreach ($bonusSetting['Bonus Setting'] as $bonusType => $bonusStgRow) {
                $bonusReqArr = explode("#", $bonusStgRow['reference']);
                $bonusStgArr[$bonusType]['bonusAmt'] = $bonusStgRow['value'];
                $bonusStgArr[$bonusType]['minRankPriority'] = $bonusReqArr[0];
                $bonusStgArr[$bonusType]['entitleCount'] = $bonusReqArr[1];

                if((!$minRankPriority) || ($minRankPriority > $bonusReqArr[0])) $minRankPriority = $bonusReqArr[0];
                if((!$minEntitleCount) || ($minEntitleCount > $bonusReqArr[1])) $minEntitleCount = $bonusReqArr[1];
            }
            $directorAwardArr = $bonusSetting['Bonus Setting']['directorAward'];
            $unicornAwardArr = $bonusSetting['Bonus Setting']['unicornAward'];

            $db->where('name','awardCycleDuration');
            $awardCycleDuration = $db->getValue('system_settings','value');

            // Get Rank Data
            $rankData = $db->map('id')->get('rank',null,'id,priority');

            $db->where('entitle_award',1);
            $db->groupBy('client_id');
            $clientDataRes = $db->map('client_id')->get('client_rank_monthly',null,'client_id,COUNT(id)');

            $db->where('bonus_type','unicornAward');
            $entitledUser = $db->map('client_id')->get('mlm_bonus_award',null,'client_id');

            foreach ($clientDataRes as $clientID => $totalEntileCount) {
                unset($entileCountArr);

                if($totalEntileCount < $minEntitleCount){
                    Log::write(date("Y-m-d H:i:s")." Client ID : ".$clientID." Total Entitle : ".$totalEntileCount." Min Entitle : ".$minEntitleCount.". Skip.\n");
                    continue;  
                }

                $startDate = $clientDataAry[$clientID]['awardCycleDate'];
                $endDate = date("Y-m-d",strtotime($startDate." + ".$awardCycleDuration));

                $db->where('client_id',$clientID);
                $db->where('entitle_award',1);
                $db->where('DATE(created_at)',$startDate,">=");
                $db->where('DATE(created_at)',$endDate,"<");
                $db->groupBy('rank_id');
                $rankEntitleRes = $db->map('rank_id')->get('client_rank_monthly',null,'rank_id,COUNT(id)');
                foreach ($rankEntitleRes as $rankID => $rankEntitleCount) {
                    foreach ($bonusStgArr as $bonusType => $bonusReq) {
                        if($rankData[$rankID] >= $bonusReq['minRankPriority']){
                            $entileCountArr[$bonusType] += $rankEntitleCount;
                        }
                    }
                }

                // Entitled Unicorn Before User, will only claim this bonus when hit Unicorn Conditions again.
                $unicronMinCount = $bonusStgArr['unicornAward']['entitleCount'];
                if($entitledUser[$clientID] && ($unicronMinCount > $entileCountArr['unicornAward'])){
                    Log::write(date("Y-m-d H:i:s") . " Entitled Unicorn User. Client ID : ".$clientID." Min Entitle Count: ".$unicronMinCount." Current Entitle Count : ".$entileCountArr['unicornAward']."\n");
                    continue;
                }

                foreach ($bonusStgArr as $bonusType => $bonusReq) {
                    $entileCount = $entileCountArr[$bonusType];
                    $bonusAmt = $bonusReq['bonusAmt'];
                    $percentage = 100;

                    if($entileCount >= $bonusReq['entitleCount']){

                        $db->where('client_id',$clientID);
                        $db->where('DATE(bonus_date)',$startDate,">=");
                        $db->where('DATE(bonus_date)',$endDate,"<");
                        $db->where('bonus_type',$bonusType);
                        $checkBonus = $db->getValue('mlm_bonus_award','id');
                        if($checkBonus){
                            Log::write(date("Y-m-d H:i:s")." Client ID : ".$clientID." Bonus Type : ".$bonusType." already paid for this cycle.\n");
                            continue;
                        }

                        $calcAmt = Setting::setDecimal($bonusAmt * ($percentage/100), "");
                        $payAmt = Setting::setDecimal(($calcAmt * $unitPrice), "");

                        if($payAmt <= 0 ) continue;

                        Log::write(date("Y-m-d H:i:s") . " bonusType: ".$bonusType." bonusAmt: ".$bonusAmt."\n");
                        Log::write(date("Y-m-d H:i:s") . " d: ".$clientID." u: calc:".$bonusAmt."*(".$percentage."/100):".$calcAmt."\n");

                        unset($insertData);
                        $insertData = array(
                            'client_id'         => $clientID,
                            'bonus_date'        => $bonusDate,
                            'bonus_type'        => $bonusType,
                            'from_amount'       => $bonusAmt,
                            'percentage'        => $percentage,
                            'calculated_amount' => $calcAmt,
                            'unit_price'        => $unitPrice,
                            'payable_amount'    => $payAmt,
                            'batch_id'          => $batchID,
                            'created_at'        => date('Y-m-d H:i:s'),
                        );
                        $db->insert('mlm_bonus_award',$insertData);

                        $clientBonusArray[$clientID] += $payAmt;
                    }
                }
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                    "client_id"         => $clientID,
                    "country_id"        => $clientDataAry[$clientID]["country_id"],
                    "bonus_date"        => $bonusDate,
                    "bonus_type"        => $bonusName,
                    "bonus_amount"      => $totalAmount,
                );
                $db->insert("mlm_bonus_report", $insertData);
            }

            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

        public function insertBonusPayoutSummary($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = "bonusPayoutSummary";
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];
            $filterDate = date('Y-m-d 23:59:59',strtotime($bonusDate));

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

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            $db->where('bonus_date',$bonusDate);
            $db->groupBy('bonus_type');
            $db->groupBy('client_id');
            $db->orderBy('country_id','ASC');
            $bonusReportRes = $db->get('mlm_bonus_report',null,'country_id,client_id,bonus_type,SUM(bonus_amount) AS totalPayout');
            foreach ($bonusReportRes as $bonusReportRow) {
                unset($updateData);
                $bonusPayoutSummary[$bonusReportRow['country_id']]['totalMember'][$bonusReportRow['client_id']] = $bonusReportRow['client_id'];
                switch ($bonusReportRow['bonus_type']) {
                    case 'goldmineBonus':
                        $bonusPayoutSummary[$bonusReportRow['country_id']]['gmCV'] += $bonusReportRow['totalPayout'];
                        break;

                    case 'teamBonus':
                        $bonusPayoutSummary[$bonusReportRow['country_id']]['teamCV'] += $bonusReportRow['totalPayout'];
                        break;

                    case 'leadershipBonus':
                        $bonusPayoutSummary[$bonusReportRow['country_id']]['leadershipCV'] += $bonusReportRow['totalPayout'];
                        break;

                    case 'awardBonus':
                        $bonusPayoutSummary[$bonusReportRow['country_id']]['awardCV'] += $bonusReportRow['totalPayout'];
                        break;
                }
            }

            foreach ($bonusPayoutSummary as $countryID => $bonusPayoutSummaryRow) {
                $cvRate = Custom::getCVRate($filterDate,$countryID);
                unset($insertData);
                $insertData = array(
                    "bonus_date"    => $bonusDate,
                    "country_id"    => $countryID,
                    "total_member"  => COUNT($bonusPayoutSummaryRow['totalMember']),
                    "cv_rate"       => $cvRate,
                    "gm_cv"         => $bonusPayoutSummaryRow['gmCV'],
                    "team_cv"       => $bonusPayoutSummaryRow['teamCV'],
                    "leader_cv"     => $bonusPayoutSummaryRow['leadershipCV'],
                    "award_payout"  => $bonusPayoutSummaryRow['awardCV'],
                    "status"        => "unpaid",
                );
                $db->insert('bonus_payout_summary',$insertData);
            }

            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

         public function getNewRecuitAndActiveProgram($params) {
          $db             = MysqliDb::getInstance();
          $language       = General::$currentLanguage;
          $translations   = General::$translations;
          $searchData     = $params['searchData'];

          $dateFormat     = Setting::$systemSetting["systemDateTimeFormat"];
          $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
          $seeAll         = $params['seeAll'];
          $limit          = $seeAll ? null : General::getLimit($pageNumber);

          $tableName      = 'mlm_bonus_payout';
          $site = $db->userType;
          if ($site == 'Admin') {
            $showField = 'bonus_date, upline_id, client_id, package_code, bonus_rebate';
          } else {
            $showField = 'bonus_date, client_id, package_code, bonus_rebate';
          }
          foreach ($searchData as $key => $val) {
            $dataName  = trim($val['dataName']);
            $dataType  = trim($val['dataType']);
            $dataValue = trim($val['dataValue']);

            switch($dataName) {
              // case 'bonus_date':
              case 'date':
                $dateFrom = trim($v['tsFrom']);
                $dateTo = trim($v['tsTo']);
                if(strlen($dateFrom) > 0) {
                    if($dateFrom < 0){
                        $db->resetState();
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                    }

                    $db->where('Date(bonus_date)', date('Y-m-d', $dateFrom), '>=');
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

                    $db->where('Date(bonus_date)', date('Y-m-d', $dateTo), '<=');
                }

                unset($dateFrom);
                unset($dateTo);
                break;
              // case 'upline_id':
              case 'memberID':
                $sq = $db->subQuery();

                if ($dataType == "like") {
                    $sq->where("member_id", "%".$dataValue."%", "LIKE");
                }else{
                    $sq->where("member_id", $dataValue);
                }
                $sq->get("client", NULL, "id");
                $db->where("upline_id", $sq, "IN");
                break;
              case 'memberFullName':
                $sq = $db->subQuery();

                if ($dataType == "like") {
                    $sq->where("name", "%".$dataValue."%", "LIKE");
                }else{
                    $sq->where("name", $dataValue);
                }
                $sq->get("client", NULL, "id");
                $db->where("upline_id", $sq, "IN");
                break;
              // case 'client_id':
              case 'fromMemberID':
                $sq = $db->subQuery();

                if ($dataType == "like") {
                    $sq->where("member_id", "%".$dataValue."%", "LIKE");
                }else{
                    $sq->where("member_id", $dataValue);
                }
                $sq->get("client", NULL, "id");
                $db->where("client_id", $sq, "IN");
                break;
              case 'fromMemberFullName':
                $sq = $db->subQuery();

                if ($dataType == "like") {
                    $sq->where("name", "%".$dataValue."%", "LIKE");
                }else{
                    $sq->where("name", $dataValue);
                }
                $sq->get("client", NULL, "id");
                // return array('data' => $dataValue);
                $db->where("client_id", $sq, "IN");
                break;
              default:
                //
            }
            unset($dataName);
            unset($dataType);
            unset($dataValue);
          }

          $copyDb = $db->copy();
          $db->orderBy('bonus_date', 'DESC');
          $bonuePayoutRes = $db->get($tableName, $limit, $showField);

          if(empty($bonuePayoutRes)){
              return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
          }

          $totalDB = $copyDb->getValue($tableName, 'COUNT(*)');

          foreach ($bonuePayoutRes as $bpr) {
            // get upline details
            if ($bpr['upline_id']) {
              $db->where('id', $bpr['upline_id'], '=');
              $uplineDetails = $db->getOne('client', 'member_id, name');
            }
            // get client details
            $db->where('id', $bpr['client_id'], '=');
            $clientDetails = $db->getOne('client', 'member_id, name');
            // get package details
            $db->where('code', $bpr['package_code'], '=');
            $packageDetails = $db->getOne('mlm_product', 'code, name');
            if ($site == 'Admin') {
              $record[] = [
                'bonus_date' => $bpr['bonus_date'],
                'member_id' => $uplineDetails['member_id'],
                'member_full_name' => $uplineDetails['name'],
                'from_member_id' => $clientDetails['member_id'],
                'from_member_full_name' => $clientDetails['name'],
                'package_name' => $packageDetails['name'],
                'bonus_rebate' => $bpr['bonus_rebate']
              ];
            } else {
              $record[] = [
                'bonus_date' => $bpr['bonus_date'],
                'member_id' => $clientDetails['member_id'],
                'member_full_name' => $clientDetails['name'],
                // 'package_name' => $packageDetails['name'],
                'bonus_rebate' => $bpr['bonus_rebate']
              ];
            }

            $allRecord = $record;
          }

          $data['data']      = $allRecord;
          $data['total']          = count($allRecord);

          $data['pageNumber']     = $pageNumber;
          if($seeAll == "1"){
              $data['totalPage']  = 1;
              $data['numRecord']  = $totalDB;
          }else{
              $data['totalPage']  = ceil($totalDB/$limit[1]);
              $data['numRecord']  = $limit[1];
          }

          return array('site' => $site, 'status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);
        }

        public function insertMonthlyDetail($bonusDate) {
            $db = MysqliDb::getInstance();
            $bonusName = "Monthly Details";
            $insertDate = date('Y-m-d 23:59:59',strtotime($bonusDate));
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

            $clientDataAry = Self::$clientDataAry;
            $directorID = Self::$directorID;

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $bonusDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            if($clientDataAry){
                $db->where('client_id',array_keys($clientDataAry),"IN");
                $db->where('address_type','billing');
                $clientDetailArr = $db->map('client_id')->get('address',null,'client_id,city,state_id');

                $db->where('DATE(created_at)',$monthFirstDate,">=");
                $db->where('DATE(created_at)',$bonusDate,"<=");
                $newClientArr = $db->map('id')->get('client',null,'id');
                if($newClientArr){
                    $db->where('client_id',$newClientArr,"IN");
                    $db->where('activated',1);
                    $db->groupBy('sponsor_id');
                    $newRecruitArr = $db->map('sponsor_id')->get('client_sales_cache',null,'sponsor_id,COUNT(id)');
                }
            }

            Log::write(date("Y-m-d H:i:s")." Insert Client Monthly Details for ".$bonusDate.".\n");
            foreach ($clientDataAry as $clientID => $clientData) {
                unset($insertData);
                $insertData = array(
                    "client_id" => $clientID,
                    "level"     => $clientData['level'],
                    "new_recruit"=> $newRecruitArr[$clientID],
                    "bonus_date"=> $bonusDate,
                    "city_id"   => $clientDetailArr[$clientID]['city'],
                    "state_id"  => $clientDetailArr[$clientID]['state_id'],
                );
                $db->insert('client_monthly_detail',$insertData);
            }
           
            Log::write(date("Y-m-d H:i:s")." Finish Insert Client Monthly Details for ".$bonusDate.".\n");


            if (Self::insertBonusCalculationBatch($bonusName, $bonusDate,1))
                Log::write(date("Y-m-d H:i:s") . " Successfully updated mlm_bonus_calculation_batch\n");
            else
                Log::write(date("Y-m-d H:i:s") . " Failed to update mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");

            Log::write(date("Y-m-d H:i:s") . " Calculated Done ".$bonusName." for " . $bonusDate . "\n");
            return true;
        }

        public function calculateActiveProgramBonusTEST($bonusDate){
            //bonus_for_starter_kit
            $db->where('is_payout','0');
            $payout = $db->get('mlm_bonus_payout');

            $db->where('type','active program bonus');
            $product_ids = $db->get('mlm_product_setting');

            $packageCodes = [];

            foreach ($product_ids as $product_id){
                $db->where('id',$product_id['product_id']);
                $code = $db->getValue('mlm_product','code');
                $packageCodes[] = strval($code);
            }

            $batchID = Bonus::insertBonusCalculationBatch('active program bonus', $bonusDate, 1, 0);

            foreach ($payout as $pay){
                $pay_datetime = $pay['payout_at'];
                $pay_month = date("m",strtotime($pay['created_at']));
                $lastMonth_ts = strtotime($bonusDate.'-1 month');
                $lastMonth = date('m',$lastMonth_ts);

                $month_start = new DateTime("first day of last month");
                $month_end = new DateTime("last day of last month");

                if($pay['payout_at'] <= $currentDate && $pay_month <= $lastMonth){
                    if(in_array($pay['package_code'],$packageCodes)){
                        $db->where("name", "bonusPayout");
                        $system_id = $db->getValue ("client", "id");
                        Cash::insertTAccount($system_id, $pay['upline_id'], 'mfizDef', $pay['bonus_rebate'], 'Active Program Bonus', $batchID, $pay['id'], $db->now(), $batchID,$pay['upline_id'],'',$pay['portfolio_id']);
                        $params = [
                            'is_payout' => '1',
                            'batch_id'=>$batchID,
                            'actual_bonus_rebate' => $pay['bonus_rebate'],
                            'bonus_date' => $bonusDate,
                        ];

                        $db->where ('id', $pay['id']);
                        $db->update ('mlm_bonus_payout',$params);
                    }
                }
            }

            Bonus::insertBonusCalculationBatch('active program bonus', $bonusDate, 0, 1);

        }

        public function calculateActiveProgramBonus($bonusDate){
            $db = MysqliDb::getInstance();
            $bonusMonth = date("m",strtotime("-1 months",strtotime($bonusDate)));

            //package code
            $db->where('name','active program bonus rebate');
            $product_ids = $db->get('mlm_product_setting');
            $packageIDS = [];
            foreach ($product_ids as $product_id){
                $packageIDS[] = $product_id['product_id'];
            }
            //all payout
            $db->where("type","active program bonus");
            $payouts = $db->get("mlm_bonus_payout");
            $payoutIDS = [];
            foreach($payouts as $payout){
                $payoutIDS[] = $payout['portfolio_id'];
            }

            $db->where("month(created_at)",$bonusMonth);
            $db->where("product_id",$packageIDS,"IN");
            $portfolios = $db->get("mlm_client_portfolio");

            $batchID = Bonus::insertBonusCalculationBatch('active program bonus', $bonusDate, 1, 0);

            foreach ($portfolios as $portfolio){
                if(!in_array($portfolio['id'],$payoutIDS)){
                    $db->where('product_id',$portfolio['product_id']);
                    $db->where('name','active program bonus rebate');
                    $value = $db->getValue('mlm_product_setting','value');

                    $db->where("id",$portfolio['product_id']);
                    $product_code = $db->getValue('mlm_product','code');

                    $db->where("id",$portfolio['client_id']);
                    $client = $db->getOne("client");
                    $clientCreatedMonth = date("m",strtotime($client['created_at']));
                    $portfolioCreatedMonth = date("m",strtotime($portfolio['created_at']));

                    if($clientCreatedMonth != $portfolioCreatedMonth){
                        continue;
                    }

                    $params = [
                        'client_id' => $portfolio['client_id'],
                        'upline_id' => $client['sponsor_id'],
                        'portfolio_id' => $portfolio['id'],
                        'product_id' => $portfolio['product_id'],
                        'package_code' => $product_code,
                        'type' => 'active program bonus',
                        'bonus_rebate' => $value,
                        'actual_bonus_rebate' => 0,
                        'is_payout' => '0',
                        'batch_id' => $batchID,
                        'bonus_date' => $bonusDate,
                    ];

                    $db->insert('mlm_bonus_payout', $params);
                }
            }


        }

        public function payActiveProgramBonus($bonusDate){
            $db = MysqliDb::getInstance();
            $db->where("is_payout",0);
            $db->where("type","active program bonus");
            $payouts = $db->get("mlm_bonus_payout");
            $db->where("name", "bonusPayout");
            $system_id = $db->getValue ("client", "id");
            $batchID = Bonus::insertBonusCalculationBatch('active program bonus', $bonusDate, 0, 1);

            foreach ($payouts as $pay){
                Cash::insertTAccount($system_id, $pay['upline_id'], 'mfizDef', $pay['bonus_rebate'], 'Active Program Bonus', $batchID, $pay['id'], $db->now(), $batchID,$pay['upline_id'],'',$pay['portfolio_id']);
                $params = [
                    'is_payout' => '1',
                    'payout_at' => $bonusDate,
                    'batch_id'=>$batchID,
                    'actual_bonus_rebate' => $pay['bonus_rebate'],
                    'bonus_date' => $bonusDate,
                ];

                $db->where ('id', $pay['id']);
                $db->update ('mlm_bonus_payout',$params);
            }
        }

        public function calculateEnrollmentBonus($bonusDate){
            $db = MysqliDb::getInstance();

            $bonusName = Self::BONUS_ENROLLMENT;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if (!$bonusDate) {
                Log::write(date("Y-m-d H:i:s")." bonusDate not found!\n");
                return false;
            }

            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $insertDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($count > 0){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $insertDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            $clientDataArr     = Self::$clientDataAry;
            $bonusSetting      = Self::getBonusData();
            $enrollmentProduct = $bonusSetting[$bonusName]['Bonus Setting']['enrollmentProductCode']['value'];
            $enrollmentProduct = explode("#",$enrollmentProduct);
            $enrollmentBV      = $bonusSetting[$bonusName]['Bonus Setting']['enrollmentBV']['value'];

            $db->where('code',$enrollmentProduct,'IN');
            $productIDAry = $db->getValue('mlm_product','id',null);
            
            $db->where('DATE(created_at)', $bonusDate);
            $db->where('portfolio_type','Purchase Package');
            $db->where('product_id',$productIDAry,'IN');
            $bonusInRes = $db->get('mlm_client_portfolio', null,'id, client_id');

            $directorID      = Self::$directorID;

            $db->where('name','member','!=');
            $db->orderBy('priority','ASC');
            $minRankID = $db->getValue('rank','id');

            foreach ($bonusInRes as $bonusInRow) {
                $clientID       = $bonusInRow['client_id'];
                $bonusID        = $bonusInRow['id'];

                if($clientDataArr[$clientID]['terminated']==1) {
                    Log::write(date("Y-m-d H:i:s")." [calculateEnrollmentBonus] Client ID ".$clientID." itself is terminated, skip...\n");
                    continue;
                }

                $db->where('id',$clientID);
                $sponsorID = $db->getValue('client','sponsor_id');

                if($clientDataArr[$sponsorID]['terminated']==1) {
                    Log::write(date("Y-m-d H:i:s")." [calculateEnrollmentBonus] Client ID ".$clientID." sponsor ".$sponsorID." is terminated, skip...\n");
                    continue;
                }

                $db->where('client_id',$sponsorID);
                $db->where('DATE(created_at)', $bonusDate, '<=');
                $db->where('name','rankDisplay');
                $db->orderBy('created_at','DESC');
                $db->orderBy('id','DESC');
                $latestID = $db->getValue('client_rank','id');

                $db->where('id',$latestID);
                $latestRank = $db->getValue('client_rank','rank_id');

                if($latestRank < $minRankID) continue;

                Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." sponsorID ".$sponsorID." amount ".$enrollmentBV.".\n");

                $insertData = array(
                                    "bonus_id"          => $bonusID,
                                    "bonus_date"        => $bonusDate,
                                    "client_id"         => $sponsorID,
                                    "from_id"           => $clientID,
                                    "amount"            => $enrollmentBV,
                                    "batch_id"          => $batchID,
                                    "created_at"        => date('Y-m-d H:i:s'),
                                );
                $db->insert("mlm_bonus_enrollment",$insertData);

                $clientBonusArray[$sponsorID]['bonusAmt'] += $enrollmentBV;
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                                        "client_id"         => $clientID,
                                        "country_id"        => $clientDataAry[$clientID]["country_id"],
                                        "bonus_date"        => $bonusDate,
                                        "bonus_type"        => $bonusName,
                                        "bonus_amount"      => $totalAmount['bonusAmt'],
                                    );
               $insertAll[] = $insertData;
            }            

            if($insertAll) $db->insertMulti("mlm_bonus_report", $insertAll);

            // Update the batch table to completed
            unset($clientBonusArray);
            self::insertBonusCalculationBatch($bonusName, date("Y-m-d", strtotime($bonusDate)), 1);
            Log::write(date("Y-m-d H:i:s") . " Calculated Done $bonusName for " . $bonusDate . "\n");
        }

        public function payEnrollmentBonus($bonusDate){
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_ENROLLMENT;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName]; 
            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $endDate = $bonusDate;


            $db->where("bonus_name",$bonusName);
            $db->where("DATE(bonus_date)",$bonusDate);
            $db->where("completed", '1');
            $db->orderBy("bonus_date","ASC");
            $batchRes = $db->get("mlm_bonus_calculation_batch", NULL, "id, paid");
            foreach ($batchRes as $batchRow) {
                $batchIDAry[$batchRow["id"]] = $batchRow["id"];
                if($batchRow["paid"] == 1){
                    Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been paid. Failed to payout.\n");
                    return false;
                }

                $batchID = $batchRow["id"];
            }

            if(empty($batchIDAry)){
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Array calculation is not ready. Failed to payout.\n");
                return false;
            }

            if(!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $paymentMethodAry = Self::$paymentMethod;
            $paymentMethod = $paymentMethodAry[$bonusName];
            $subject = $paymentMethod["subject"];        

            $percentageTotal = 0;
            foreach ($paymentMethod["payment"] as $creditType => $percentage) {
                $percentageTotal += $percentage;
                $creditPercentage[$creditType] = $percentage;
            }            

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." payment total is not 100%. Failed to payout.\n");
                return false;
            }            

            $internalID = self::$bonusPayoutID;
            if(!$internalID){
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $payDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
            $clientDataAry = self::$clientDataAry;

            $db->where('paid', 0);
            $db->where('batch_id',$batchIDAry, 'IN');
            $db->groupBy('client_id');
            $clientBonusArray = $db->map('client_id')->get("mlm_bonus_enrollment", null, 'client_id, SUM(amount) AS payableAmt');
            foreach($clientBonusArray AS $clientID => $payableAmount){
                unset($updateData);

                $totalAmount = $getAmount = $amount = 0;

                // $belongID = $db->getNewID();

                if($payableAmount <= 0) continue;

                if($clientDataAry[$clientID]["terminated"] == 1){
                    Log::write(date("Y-m-d H:i:s")." Terminated ".$clientID." - payableAmount: ".$payableAmount."\n");
                    continue;
                }

                // // max cap deduct during calculation
                // $capData = self::checkMaxCap($clientID, $bonusName, $subject, $payableAmount, $payDate, $batchID);
                // $getAmount = $capData["amount"];

                // Log::write("c:".$clientID." payableAmount: ".$payableAmount." afterCap: ".$getAmount."\n");
                // $getAmount = $payableAmount;

                Log::write(date("Y-m-d H:i:s")." c:".$clientID." payableAmount: ".$payableAmount."\n");
                foreach ($creditPercentage as $creditType => $value) {
                    $percentage     = $value / 100;
                    $amount         = Setting::setDecimal(($payableAmount * $percentage));

                    if (($totalAmount + $amount) > $payableAmount && $totalAmount > 0) {
                        // SUM should be equal to total

                        Log::write(date("Y-m-d H:i:s")." Amount not equal convert: ".$amount);
                        $amount = $payableAmount - $totalAmount;
                        Log::write(" > ".$amount."\n");
                    }

                    if ($amount > 0) { // Payout to client
                        Cash::insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $batchID, "", $payDate, $batchID, $clientID,"");
                        $totalAmount += $amount;
                    }

                }

                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDAry, 'IN');
                $db->where("paid", 0);
                $db->update("mlm_bonus_enrollment", array("paid" => 1, "paid_batch_id" => $batchID)); 
            }

            $db->where("id", $batchID);
            $db->update("mlm_bonus_calculation_batch", array("paid" => 1, 'completed_at' => date('Y-m-d H:i:s')));

            Log::write(date("Y-m-d H:i:s")." Done Paid $bonusName for ".$bonusDate."\n");
        }

        public function calculateCoupleBonus($bonusDate){
            $db = MysqliDb::getInstance();

            $bonusName = Self::BONUS_COUPLE;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if (!$bonusDate) {
                Log::write(date("Y-m-d H:i:s")." bonusDate not found!\n");
                return false;
            }

            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $insertDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($count > 0){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $insertDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }

            $clientDataArr         = Self::$clientDataAry;
            $bonusSetting          = Self::getBonusData();
            $couplePairingBV       = $bonusSetting[$bonusName]['Bonus Setting']['coupleParingAmount']['reference'];
            $couplePairingDVP      = $bonusSetting[$bonusName]['Bonus Setting']['coupleParingAmount']['value'];
            $coupleMaximum         = $bonusSetting[$bonusName]['Bonus Setting']['coupleMaximum']['value'];
            $maxPlacementPositions = Setting::$systemSetting["maxPlacementPositions"];

            $placementTreeCache    = Self::$placementTreeCache;
            $placementDownlineAry  = Self::$placementDownlineAry;
            $placementSelfData     = Self::$placementSelfData;

            $start  = date("Y-m-d 00:00:00", strtotime($bonusDate));
            $end    = date("Y-m-d 23:59:59", strtotime($bonusDate));

            $db->where("created_at", $start, ">=");
            $db->where("created_at", $end, "<=");
            $db->groupBy("client_id");
            $bonusInAry = $db->map("client_id")->get("mlm_bonus_in", null, "client_id, sum(bonus_value) as bonus_value");

            $db->where('name','member','!=');
            $db->orderBy('priority','ASC');
            $minRankID = $db->getValue('rank','id');

            unset($clientLatestRankRes);
            $db->where("rank_type",'Bonus Tier');
            $db->where("type","System");
            $db->where('name','rankDisplay');
            $db->where('DATE(created_at)',$bonusDate,"<=");
            $db->groupBy('client_id');
            $clientLatestRankRes = $db->get("client_rank", null, "client_id, MAX(id) as id");

            unset($maxID);
            foreach($clientLatestRankRes AS $clientLatestRankRow){
                    $maxID[] = $clientLatestRankRow['id'];
            }

            if($maxID){
                $db->where("id",$maxID,"IN");
                $clientRankRes = $db->get("client_rank",NULL,"client_id, rank_id");

                unset($rankIDAry);
                foreach($clientRankRes as $clientRankRow){
                    $rankIDAry[$clientRankRow['client_id']] = $clientRankRow['rank_id'];
                }
            }

            foreach($bonusInAry as $clientID => $bonusValue){
                unset($uplineData);

                if($clientDataArr[$clientID]['terminated']==1) {
                    Log::write(date("Y-m-d H:i:s")." [calculateCoupleBonus] Client ID ".$clientID." itself is terminated, skip...\n");
                    continue;
                }

                $uplineData = self::getBonusPlacementTreeUplines($clientID,$bonusDate,false);
                if(empty($uplineData)) continue;
                $downlinePosition = 0;
                $downlineID = 0;

                foreach ($uplineData as $uplineRow) {
                    $uplineID = $uplineRow["client_id"];

                    // if($uplineID != $clientID){
                         $salesAmountData[$uplineID] += $bonusValue;
                        // $salesAmountData[$uplineID][$downlinePosition] += $bonusValue;
                    // }
                    $downlineID = $uplineID;
                    $downlinePosition = $uplineRow["client_position"];
                }
            }

            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write("\n--------------------------------------------------------------\n");
            Log::write(date("Y-m-d H:i:s")." Started calculated ".$bonusName." 1 for $bonusDate, Memory Used: ".$alloc_mem."MB .\n");
            Log::write("--------------------------------------------------------------\n");

            $tmpLegDVP = "temp_".General::generateRandomNumber(20);

            $db->rawQuery("CREATE TEMPORARY TABLE ".$tmpLegDVP." (
                 `client_id` bigint(20) NOT NULL,
                 `upline_id` bigint(20) NOT NULL,
                 `personal_position` tinyint(1) NOT NULL,
                 `amount` decimal(20,8) NOT NULL
              );");


            foreach ($clientDataArr as $clientID => $clientRow) {
                if(!$salesAmountData[$clientID]) continue;

                // if($clientDataArr[$clientID]['terminated']==1) continue;

                $insertData = array(
                                        "client_id"         => $clientID,
                                        "personal_position" => $placementSelfData[$clientID]['position'],
                                        "upline_id"         => $placementSelfData[$clientID]['upline'],
                                        "amount"            => $salesAmountData[$clientID],
                                    );
                $db->insert($tmpLegDVP, $insertData);
                
                unset($salesAmountData[$clientID]);
                unset($placementSelfData[$clientID]);
            }

            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write("\n--------------------------------------------------------------\n");
            Log::write(date("Y-m-d H:i:s")." Started calculated ".$bonusName." 2 for $bonusDate, Memory Used: ".$alloc_mem."MB .\n");
            Log::write("--------------------------------------------------------------\n");

            /*Start on couple pairing*/

            foreach ($clientDataArr as $clientID => $clientRow) {

                if($rankIDAry[$clientID] < $minRankID) {
                    Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." Rank not at least Fiz Preneur, skip...\n");
                    continue;
                } 

                if($clientDataArr[$clientID]['terminated']==1) {
                    Log::write(date("Y-m-d H:i:s")." [calculateCoupleBonus] Client ID ".$clientID." itself is terminated, skip...\n");
                    continue;
                }

                $db->where('upline_id',$clientID);
                $downlineData = $db->get($tmpLegDVP,null,'personal_position,amount');
                unset($dvpLeft, $dvpRight);
                foreach($downlineData as $downlineDataRow){
                    if($downlineDataRow['personal_position'] == 1){
                        $dvpLeft = $downlineDataRow['amount'];
                    } else if($downlineDataRow['personal_position'] == 2){
                        $dvpRight = $downlineDataRow['amount'];
                    }
                }

                unset($cfDVPLeft, $cfDVPRight, $checkCFDate);
                $db->where('client_id',$clientID);
                $db->orderBy('created_at','DESC');
                $cfDVP = $db->getOne('mlm_bonus_couple','created_at, remaining_dvp_1, remaining_dvp_2');

                if($cfDVP){
                    $checkCFDate = date('Y-m-d',strtotime($cfDVP['created_at']." + 6 months"));

                    if($bonusDate <= $checkCFDate){
                        $cfDVPLeft  = $cfDVP['remaining_dvp_1'];
                        $cfDVPRight = $cfDVP['remaining_dvp_2'];
                    }
                }

                $dvpLeftTotal   = $dvpLeft + $cfDVPLeft;
                $dvpRightTotal  = $dvpRight + $cfDVPRight;

                if($dvpLeftTotal == 0 && $dvpRightTotal == 0) {
                    Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." Left & Right Leg no DVP, skip... | dvpLeft: ".$dvpLeft.", cfDVPLeft: ".$cfDVPLeft.", dvpLeftTotal: ".$dvpLeftTotal." | dvpRight: ".$dvpRight.", cfDVPRight: ".$cfDVPRight.", dvpRightTotal: ".$dvpRightTotal."  \n");

                    continue;
                } 

                $lowerDVP       = ($dvpLeftTotal < $dvpRightTotal) ? $dvpLeftTotal : $dvpRightTotal;
                $totalCouple    = floor($lowerDVP / $couplePairingDVP);
                $remainingLeft  = $dvpLeftTotal - ($totalCouple * $couplePairingDVP);
                $remainingRight = $dvpRightTotal - ($totalCouple * $couplePairingDVP);
                $calCouple      = ($totalCouple > $coupleMaximum) ? $coupleMaximum : $totalCouple;
                $calAmount      = $calCouple * $couplePairingBV;

                Log::write(date("Y-m-d H:i:s")." Client ID : ".$clientID." Total Couple : ".$totalCouple." Amount : ".$calAmount." Remaining Left : ".$remainingLeft." Remaining Right : ".$remainingRight."\n");

                unset($insertData);
                $insertData = array(
                                    "client_id"          => $clientID,
                                    "bonus_date"         => $bonusDate,
                                    "cf_dvp_1"           => $cfDVPLeft ? : 0,
                                    "new_dvp_1"          => $dvpLeft ? : 0,
                                    "remaining_dvp_1"    => $remainingLeft,
                                    "cf_dvp_2"           => $cfDVPRight ? : 0,
                                    "new_dvp_2"          => $dvpRight ? : 0,
                                    "remaining_dvp_2"    => $remainingRight,
                                    "total_couple"       => $totalCouple,
                                    "calculated_couple"  => $calCouple,
                                    "unit_bv"            => $couplePairingBV,
                                    "amount"             => $calAmount,
                                    "payable_amount"     => $calAmount,
                                    "batch_id"           => $batchID,
                                    "created_at"         => date('Y-m-d H:i:s'),
                                );
                $db->insert("mlm_bonus_couple",$insertData);

                $clientBonusArray[$clientID]['bonusAmt'] += $calAmount;
            }

            $alloc_mem = round(memory_get_usage() / 1024/1024);
            Log::write("\n--------------------------------------------------------------\n");
            Log::write(date("Y-m-d H:i:s")." Started calculated ".$bonusName." 3 for $bonusDate, Memory Used: ".$alloc_mem."MB .\n");
            Log::write("--------------------------------------------------------------\n");

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                                        "client_id"         => $clientID,
                                        "country_id"        => $clientDataAry[$clientID]["country_id"],
                                        "bonus_date"        => $bonusDate,
                                        "bonus_type"        => $bonusName,
                                        "bonus_amount"      => $totalAmount['bonusAmt'],
                                    );
               $insertAll[] = $insertData;
            }            

            if($insertAll) $db->insertMulti("mlm_bonus_report", $insertAll);
            unset($clientBonusArray, $clientDataAry, $bonusInAry, $cfDVP, $dvpLeft, $dvpRight, $rankIDAry, $maxID);
            $db->rawQuery("DROP TEMPORARY TABLE ".$tmpLegDVP."");

            // Update the batch table to completed
            self::insertBonusCalculationBatch($bonusName, date("Y-m-d", strtotime($bonusDate)), 1);
            Log::write(date("Y-m-d H:i:s") . " Calculated Done $bonusName for " . $bonusDate . "\n");
        }

        public function payCoupleBonus($bonusDate){
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_COUPLE;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName]; 
            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $endDate = $bonusDate;


            $db->where("bonus_name",$bonusName);
            $db->where("DATE(bonus_date)",$bonusDate);
            $db->where("completed", '1');
            $db->orderBy("bonus_date","ASC");
            $batchRes = $db->get("mlm_bonus_calculation_batch", NULL, "id, paid");
            foreach ($batchRes as $batchRow) {
                $batchIDAry[$batchRow["id"]] = $batchRow["id"];
                if($batchRow["paid"] == 1){
                    Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been paid. Failed to payout.\n");
                    return false;
                }

                $batchID = $batchRow["id"];
            }

            if(empty($batchIDAry)){
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Array calculation is not ready. Failed to payout.\n");
                return false;
            }

            if(!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $paymentMethodAry = Self::$paymentMethod;
            $paymentMethod = $paymentMethodAry[$bonusName];
            $subject = $paymentMethod["subject"];        

            $percentageTotal = 0;
            foreach ($paymentMethod["payment"] as $creditType => $percentage) {
                $percentageTotal += $percentage;
                $creditPercentage[$creditType] = $percentage;
            }            

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." payment total is not 100%. Failed to payout.\n");
                return false;
            }            

            $internalID = self::$bonusPayoutID;
            if(!$internalID){
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $payDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
            $clientDataAry = self::$clientDataAry;

            $db->where('paid', 0);
            $db->where('batch_id',$batchIDAry, 'IN');
            $db->groupBy('client_id');
            $clientBonusArray = $db->map('client_id')->get("mlm_bonus_couple", null, 'client_id, SUM(amount) AS payableAmt');
            foreach($clientBonusArray AS $clientID => $payableAmount){
                unset($updateData);

                $totalAmount = $getAmount = $amount = 0;

                // $belongID = $db->getNewID();

                if($payableAmount <= 0) continue;

                if($clientDataAry[$clientID]["terminated"] == 1){
                    Log::write(date("Y-m-d H:i:s")." Terminated ".$clientID." - payableAmount: ".$payableAmount."\n");
                    continue;
                }

                // // max cap deduct during calculation
                // $capData = self::checkMaxCap($clientID, $bonusName, $subject, $payableAmount, $payDate, $batchID);
                // $getAmount = $capData["amount"];

                // Log::write("c:".$clientID." payableAmount: ".$payableAmount." afterCap: ".$getAmount."\n");
                // $getAmount = $payableAmount;

                Log::write(date("Y-m-d H:i:s")." c:".$clientID." payableAmount: ".$payableAmount."\n");
                foreach ($creditPercentage as $creditType => $value) {
                    $percentage     = $value / 100;
                    $amount         = Setting::setDecimal(($payableAmount * $percentage));

                    if (($totalAmount + $amount) > $payableAmount && $totalAmount > 0) {
                        // SUM should be equal to total

                        Log::write(date("Y-m-d H:i:s")." Amount not equal convert: ".$amount);
                        $amount = $payableAmount - $totalAmount;
                        Log::write(" > ".$amount."\n");
                    }

                    if ($amount > 0) { // Payout to client
                        Cash::insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $batchID, "", $payDate, $batchID, $clientID,"");
                        $totalAmount += $amount;
                    }

                }

                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDAry, 'IN');
                $db->where("paid", 0);
                $db->update("mlm_bonus_couple", array("paid" => 1, "paid_batch_id" => $batchID)); 
            }

            $db->where("id", $batchID);
            $db->update("mlm_bonus_calculation_batch", array("paid" => 1, 'completed_at' => date('Y-m-d H:i:s')));

            Log::write(date("Y-m-d H:i:s")." Done Paid $bonusName for ".$bonusDate."\n");
        }

        public function calculateUnilevelBonus($bonusDate){
            $db = MysqliDb::getInstance();

            $bonusName = Self::BONUS_UNILEVEL;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if (!$bonusDate) {
                Log::write(date("Y-m-d H:i:s")." bonusDate not found!\n");
                return false;
            }

            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $insertDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($count > 0){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $insertDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }
            $dateTime = date("Y-m-d 23:59:59", strtotime($bonusDate));

            $clientDataArr      = Self::$clientDataAry;
            $clientRankArr      = Self::getClientRank("Bonus Tier", "", $dateTime, $bonusName, null);
            $bonusSetting       = Self::getBonusData();
            $unilevelDVP        = $bonusSetting[$bonusName]['Bonus Setting']['unilevelDVP']['value']; //200
            $unilevelPercentage = $bonusSetting[$bonusName]['Bonus Setting']['unilevelPercentage']['value']; //0.5
            $unilevelAmount     = $bonusSetting[$bonusName]['Bonus Setting']['unilevelPercentage']['reference']; //10000

            $unilevelEntitledRank = $bonusSetting[$bonusName]['Bonus Setting']['unilevelEntitled']['value'];
            $unilevelEntitledRank = explode("#",$unilevelEntitledRank);
            $unilevelEntitledAmt  = $bonusSetting[$bonusName]['Bonus Setting']['unilevelEntitled']['reference'];
            $unilevelEntitledAmt  = explode("#",$unilevelEntitledAmt);

            $unilevelEntitledArr  = array_combine($unilevelEntitledRank, $unilevelEntitledAmt);

            
            $db->where('bonus_date', $bonusDate);
            $bonusCouple = $db->get('mlm_bonus_couple', null,'client_id, total_couple, calculated_couple');

            $directorID      = Self::$directorID;

            foreach ($bonusCouple as $bonusCoupleRow) {
                $clientID       = $bonusCoupleRow['client_id'];
                $clientRankID   = $clientRankArr[$clientID]['rank_id'];
                $flushedCouple  = $bonusCoupleRow['total_couple'] - $bonusCoupleRow['calculated_couple'];

                if($clientDataArr[$clientID]['terminated']==1) {
                    Log::write(date("Y-m-d H:i:s")." [calculateUnilevelBonus] Client ID ".$clientID." itself is terminated, skip...\n");
                    continue;
                }

                if($flushedCouple == 0){
                    Log::write(date("Y-m-d H:i:s")." Client: ".$clientID." no flushed couple, skip.... \n");
                    continue;
                }

                $db->where('id',$clientRankID);
                $rankName = $db->getvalue('rank','name');

                if(!in_array($rankName,array_keys($unilevelEntitledArr))){
                    Log::write(date("Y-m-d H:i:s")." Client: ".$clientID." not qualified. rank: ".$rankName." on ".$dateTime." skip.... \n");
                    continue;
                }

                foreach($unilevelEntitledArr as  $rank => $entitled){
                    if($rankName == $rank){
                        $maxAmount = $entitled;
                    }
                }

                $flushedDVP = $flushedCouple * $unilevelDVP;
                $maxCalculated = $flushedDVP;
                if($rankName != "fizDirector"){
                    $maxCalculated = ($flushedDVP < $maxAmount) ? $flushedDVP : $maxAmount;
                }

                $flushedDVPCal     = ($unilevelPercentage/100) * $maxCalculated * $unilevelAmount;

                Log::write(date("Y-m-d H:i:s")." Client ID ".$clientID." rank ".$rankName." flushedCouple ".$flushedCouple." flushedDVP ".$flushedDVP." calFlushed ".$flushedDVPCal.".\n");

                $insertData = array(
                                    "bonus_date"        => $bonusDate,
                                    "client_id"         => $clientID,
                                    "rank_id"           => $clientRankID,
                                    "couple_flush"      => $flushedCouple,
                                    "calculated_dvp"    => $maxCalculated,
                                    "flush_dvp"         => $flushedDVP,
                                    "amount"            => $flushedDVPCal,
                                    "payable_amount"    => $flushedDVPCal,
                                    "batch_id"          => $batchID,
                                    "created_at"        => date('Y-m-d H:i:s'),
                                );
                $db->insert("mlm_bonus_unilevel",$insertData);

                $clientBonusArray[$clientID]['bonusAmt'] += $flushedDVPCal;
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                                        "client_id"         => $clientID,
                                        "country_id"        => $clientDataAry[$clientID]["country_id"],
                                        "bonus_date"        => $bonusDate,
                                        "bonus_type"        => $bonusName,
                                        "bonus_amount"      => $totalAmount['bonusAmt'],
                                    );
               $insertAll[] = $insertData;
            }            

            if($insertAll) $db->insertMulti("mlm_bonus_report", $insertAll);

            // Update the batch table to completed
            self::insertBonusCalculationBatch($bonusName, date("Y-m-d", strtotime($bonusDate)), 1);
            Log::write(date("Y-m-d H:i:s") . " Calculated Done $bonusName for " . $bonusDate . "\n");
        }

        public function payUnilevelBonus($bonusDate){
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_UNILEVEL;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName]; 
            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $endDate = $bonusDate;


            $db->where("bonus_name",$bonusName);
            $db->where("DATE(bonus_date)",$bonusDate);
            $db->where("completed", '1');
            $db->orderBy("bonus_date","ASC");
            $batchRes = $db->get("mlm_bonus_calculation_batch", NULL, "id, paid");
            foreach ($batchRes as $batchRow) {
                $batchIDAry[$batchRow["id"]] = $batchRow["id"];
                if($batchRow["paid"] == 1){
                    Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been paid. Failed to payout.\n");
                    return false;
                }

                $batchID = $batchRow["id"];
            }

            if(empty($batchIDAry)){
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Array calculation is not ready. Failed to payout.\n");
                return false;
            }

            if(!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $paymentMethodAry = Self::$paymentMethod;
            $paymentMethod = $paymentMethodAry[$bonusName];
            $subject = $paymentMethod["subject"];        

            $percentageTotal = 0;
            foreach ($paymentMethod["payment"] as $creditType => $percentage) {
                $percentageTotal += $percentage;
                $creditPercentage[$creditType] = $percentage;
            }            

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." payment total is not 100%. Failed to payout.\n");
                return false;
            }            

            $internalID = self::$bonusPayoutID;
            if(!$internalID){
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $payDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
            $clientDataAry = self::$clientDataAry;

            $db->where('paid', 0);
            $db->where('batch_id',$batchIDAry, 'IN');
            $db->groupBy('client_id');
            $clientBonusArray = $db->map('client_id')->get("mlm_bonus_unilevel", null, 'client_id, SUM(payable_amount) AS payableAmt');
            foreach($clientBonusArray AS $clientID => $payableAmount){
                unset($updateData);

                $totalAmount = $getAmount = $amount = 0;

                // $belongID = $db->getNewID();

                if($payableAmount <= 0) continue;

                if($clientDataAry[$clientID]["terminated"] == 1){
                    Log::write(date("Y-m-d H:i:s")." Terminated ".$clientID." - payableAmount: ".$payableAmount."\n");
                    continue;
                }

                // // max cap deduct during calculation
                // $capData = self::checkMaxCap($clientID, $bonusName, $subject, $payableAmount, $payDate, $batchID);
                // $getAmount = $capData["amount"];

                // Log::write("c:".$clientID." payableAmount: ".$payableAmount." afterCap: ".$getAmount."\n");
                // $getAmount = $payableAmount;

                Log::write(date("Y-m-d H:i:s")." c:".$clientID." payableAmount: ".$payableAmount."\n");
                foreach ($creditPercentage as $creditType => $value) {
                    $percentage     = $value / 100;
                    $amount         = Setting::setDecimal(($payableAmount * $percentage));

                    if (($totalAmount + $amount) > $payableAmount && $totalAmount > 0) {
                        // SUM should be equal to total

                        Log::write(date("Y-m-d H:i:s")." Amount not equal convert: ".$amount);
                        $amount = $payableAmount - $totalAmount;
                        Log::write(" > ".$amount."\n");
                    }

                    if ($amount > 0) { // Payout to client
                        Cash::insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $batchID, "", $payDate, $batchID, $clientID,"");
                        $totalAmount += $amount;
                    }

                }

                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDAry, 'IN');
                $db->where("paid", 0);
                $db->update("mlm_bonus_unilevel", array("paid" => 1, "paid_batch_id" => $batchID)); 
            }

            $db->where("id", $batchID);
            $db->update("mlm_bonus_calculation_batch", array("paid" => 1, 'completed_at' => date('Y-m-d H:i:s')));

            Log::write(date("Y-m-d H:i:s")." Done Paid $bonusName for ".$bonusDate."\n");
        }

        public function calculateLeadershipRewardBonus($bonusDate){
            $db = MysqliDb::getInstance();

            $bonusName = Self::BONUS_LEADERSHIP_REWARD;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName];

            if (!$bonusSettingAry['activeBonus'][$bonusName]) {
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Not active in mlm_bonus\n");
                return false;
            }

            if (!$bonusDate) {
                Log::write(date("Y-m-d H:i:s")." bonusDate not found!\n");
                return false;
            }

            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $db->where("bonus_name", $bonusName);
            $db->where("bonus_date", $insertDate);
            $db->where("completed","1");
            $count = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($count > 0){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been calculate.\n");
                return false;
            }

            Log::write(date("Y-m-d H:i:s")." Calculating ".$bonusName." for ".$bonusDate.".\n");
            $batchID = self::insertBonusCalculationBatch($bonusName, $insertDate);
            if ($batchID) {
                Log::write(date("Y-m-d H:i:s") . " Successfully insert into mlm_bonus_calculation_batch\n");
            } else {
                Log::write(date("Y-m-d H:i:s") . " Failed to insert into mlm_bonus_calculation_batch. Error : " . $db->getLastError() . "\n");
            }


            $db->where('bonus_date',$bonusDate,'<=');
            $db->where('total_couple',0,'>');
            $db->groupBy('client_id');
            $clientIDAry = $db->getValue('mlm_bonus_couple','client_id',null);

            $db->where('type','Bonus Tier');
            $db->where('name','member','!=');
            $rankNameAry = $db->map('id')->get('rank',null,'id, name');

            if(!$clientIDAry){
                Log::write(date("Y-m-d H:i:s")." $bonusDate no couple data found. \n");
                return false;
            }

            unset($clientLatestRankRes);
            $db->where('client_id',$clientIDAry,'IN');
            $db->where("rank_type",'Bonus Tier');
            $db->where("type","System");
            $db->where('name','rankDisplay');
            $db->where('DATE(created_at)',$bonusDate,"<=");
            $db->groupBy('client_id');
            $clientLatestRankRes = $db->get("client_rank", null, "client_id, MAX(id) as id");

            unset($maxID);
            foreach($clientLatestRankRes AS $clientLatestRankRow){
                    $maxID[] = $clientLatestRankRow['id'];
            }

            if($maxID){
                $db->where("id",$maxID,"IN");
                $clientRankRes = $db->get("client_rank",NULL,"client_id, rank_id");

                unset($rankIDAry);
                foreach($clientRankRes as $clientRankRow){
                    $rankIDAry[$clientRankRow['client_id']] = $clientRankRow['rank_id'];
                }
            }

            $clientDataArr      = Self::$clientDataAry;
            $bonusSetting       = Self::getBonusData();

            $leadershipAccCouple = $bonusSetting[$bonusName]['Bonus Setting']['leadershipRewardBV']['value'];
            $leadershipAccCouple = explode("#",$leadershipAccCouple);
            $leadershipRewardAmount  = $bonusSetting[$bonusName]['Bonus Setting']['leadershipRewardBV']['reference'];
            $leadershipRewardAmount  = explode("#",$leadershipRewardAmount);

            $leadershipAccCouples = $bonusSetting[$bonusName]['Bonus Setting']['leadershipRewardRank']['value'];
            $leadershipAccCouples = explode("#",$leadershipAccCouples);
            $leadershipRewardRank  = $bonusSetting[$bonusName]['Bonus Setting']['leadershipRewardRank']['reference'];
            $leadershipRewardRank  = explode("#",$leadershipRewardRank);

            // $coupleReward = Self::getTotalCouple($clientIDAry, $bonusDate);
            foreach($clientIDAry as $clientIDRow){
                $db->where('client_id',$clientIDRow);
                $db->where('name','yearlyStartDate') ;
                $yearlyStartDate[$clientIDRow] = $db->getValue('client_setting','value');

                unset($totalCouple);
                if($yearlyStartDate[$clientIDRow]){
                    $db->where('bonus_date',$yearlyStartDate[$clientIDRow],'>=');
                    $db->where('client_id',$clientIDRow);
                    $db->where('bonus_date',$bonusDate,'<=');
                    $db->groupBy('client_id');
                    $totalCouple = $db->getValue('mlm_bonus_couple','SUM(total_couple)');

                    $coupleReward[$clientIDRow]['totalCouple'] = $totalCouple;
                }
            }

            $rewardEntitledArr  = array_combine($leadershipAccCouple, $leadershipRewardAmount);
            $rewardRankArr  = array_combine($leadershipAccCouples, $leadershipRewardRank);

            $db->where('type','Bonus Tier');
            $rankIDChecking = $db->map('name')->get('rank',null,'name, id');

            $db->where("type", 'Bonus Tier');
            $rankPriorityAry = $db->map("id")->get("rank",null, "id, priority");

            unset($bvRewarded, $accCouple);
            if(!$coupleReward) {
                Log::write(date("Y-m-d H:i:s")." $bonusDate No".$bonusName." found.\n");
                return false;
            }

            foreach($coupleReward as $rewardID => $coupleRewardRow) {
                foreach($rewardEntitledArr as $minCouple => $bvEntitled){
                    if($coupleRewardRow['totalCouple'] >= $minCouple){
                        $bvRewarded[$rewardID][$minCouple] = $bvEntitled;
                        $bvRank[$rewardID][$minCouple] = $rewardRankArr[$minCouple];
                    }
                }

                if(!$bvRewarded[$rewardID]) continue;

                if($clientDataArr[$rewardID]['terminated']==1) {
                    Log::write(date("Y-m-d H:i:s")." [calculateLeadershipRewardBonus] Client ID ".$rewardID." itself is terminated, skip...\n");
                    continue;
                }

                foreach($bvRewarded[$rewardID] as $minCout => $bvEntitle){

                    // if($rankPriorityAry[$bvRank[$rewardID][$minCout]] > $rankPriorityAry[$rankIDAry[$rewardID]]) continue;
                    $db->where('client_id',$rewardID);
                    $db->where('bonus_date',$yearlyStartDate[$rewardID],'>=');
                    $db->where('bonus_date',$bonusDate,'<=');
                    $db->where('payable_amount',$bvRewarded[$rewardID][$minCout]);
                    $receivedChecking = $db->getOne('mlm_bonus_leadership_reward');

                    if($receivedChecking) continue;

                    if(!$receivedChecking){
                        unset($insertData);
                        $insertData = array(
                                            "bonus_date"        => $bonusDate,
                                            "client_id"         => $rewardID,
                                            "rank_id"           => $rankIDChecking[$bvRank[$rewardID][$minCout]],
                                            "acc_couple"        => $coupleRewardRow['totalCouple'],
                                            "bonus_amount"      => $bvRewarded[$rewardID][$minCout],
                                            "payable_amount"    => $bvRewarded[$rewardID][$minCout],
                                            "batch_id"          => $batchID,
                                            "created_at"        => date('Y-m-d H:i:s'),
                                        );
                        $db->insert("mlm_bonus_leadership_reward",$insertData);

                        $clientBonusArray[$rewardID]['bonusAmt'] += $bvRewarded[$rewardID][$minCout];

                    }
                }
                
            }

            foreach ($clientBonusArray as $clientID => $totalAmount) {
                $insertData = array(
                                        "client_id"         => $clientID,
                                        "country_id"        => $clientDataAry[$clientID]["country_id"],
                                        "bonus_date"        => $bonusDate,
                                        "bonus_type"        => $bonusName,
                                        "bonus_amount"      => $totalAmount['bonusAmt'],
                                    );
               $insertAll[] = $insertData;
            }            

            if($insertAll) $db->insertMulti("mlm_bonus_report", $insertAll);

            // Update the batch table to completed
            self::insertBonusCalculationBatch($bonusName, date("Y-m-d", strtotime($bonusDate)), 1);
            Log::write(date("Y-m-d H:i:s") . " Calculated Done $bonusName for " . $bonusDate . "\n");
        }

        public function payLeadershipRewardBonus($bonusDate){
            $db = MysqliDb::getInstance();
            $bonusName = Self::BONUS_LEADERSHIP_REWARD;
            $bonusSettingAry = Self::$bonusSetting;
            $bonusSetting = $bonusSettingAry[$bonusName]; 
            $insertDate = date("Y-m-d", strtotime($bonusDate));
            $endDate = $bonusDate;


            $db->where("bonus_name",$bonusName);
            $db->where("DATE(bonus_date)",$bonusDate);
            $db->where("completed", '1');
            $db->orderBy("bonus_date","ASC");
            $batchRes = $db->get("mlm_bonus_calculation_batch", NULL, "id, paid");
            foreach ($batchRes as $batchRow) {
                $batchIDAry[$batchRow["id"]] = $batchRow["id"];
                if($batchRow["paid"] == 1){
                    Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been paid. Failed to payout.\n");
                    return false;
                }

                $batchID = $batchRow["id"];
            }

            if(empty($batchIDAry)){
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." Array calculation is not ready. Failed to payout.\n");
                return false;
            }

            if(!$batchID) {
                // Batch ID is not found, bonus calculation isn't completed
                Log::write(date("Y-m-d H:i:s") . " " . $bonusName . " calculation is not ready. Failed to payout.\n");
                return false;
            }

            $paymentMethodAry = Self::$paymentMethod;
            $paymentMethod = $paymentMethodAry[$bonusName];
            $subject = $paymentMethod["subject"];        

            $percentageTotal = 0;
            foreach ($paymentMethod["payment"] as $creditType => $percentage) {
                $percentageTotal += $percentage;
                $creditPercentage[$creditType] = $percentage;
            }            

            if ($percentageTotal != 100) {
                // Percentage is not 100%, do not continue
                Log::write(date("Y-m-d H:i:s")." ".$bonusName." payment total is not 100%. Failed to payout.\n");
                return false;
            }            

            $internalID = self::$bonusPayoutID;
            if(!$internalID){
                Log::write(date("Y-m-d H:i:s")." Internal ID is not ready. Failed to payout.\n");
                return false;
            }

            $payDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
            $clientDataAry = self::$clientDataAry;

            $db->where('paid', 0);
            $db->where('batch_id',$batchIDAry, 'IN');
            $db->groupBy('client_id');
            $clientBonusArray = $db->map('client_id')->get("mlm_bonus_leadership_reward", null, 'client_id, SUM(payable_amount) AS payableAmt');
            foreach($clientBonusArray AS $clientID => $payableAmount){
                unset($updateData);

                $totalAmount = $getAmount = $amount = 0;

                // $belongID = $db->getNewID();

                if($payableAmount <= 0) continue;

                if($clientDataAry[$clientID]["terminated"] == 1){
                    Log::write(date("Y-m-d H:i:s")." Terminated ".$clientID." - payableAmount: ".$payableAmount."\n");
                    continue;
                }

                // // max cap deduct during calculation
                // $capData = self::checkMaxCap($clientID, $bonusName, $subject, $payableAmount, $payDate, $batchID);
                // $getAmount = $capData["amount"];

                // Log::write("c:".$clientID." payableAmount: ".$payableAmount." afterCap: ".$getAmount."\n");
                // $getAmount = $payableAmount;

                Log::write(date("Y-m-d H:i:s")." c:".$clientID." payableAmount: ".$payableAmount."\n");
                foreach ($creditPercentage as $creditType => $value) {
                    $percentage     = $value / 100;
                    $amount         = Setting::setDecimal(($payableAmount * $percentage));

                    if (($totalAmount + $amount) > $payableAmount && $totalAmount > 0) {
                        // SUM should be equal to total

                        Log::write(date("Y-m-d H:i:s")." Amount not equal convert: ".$amount);
                        $amount = $payableAmount - $totalAmount;
                        Log::write(" > ".$amount."\n");
                    }

                    if ($amount > 0) { // Payout to client
                        Cash::insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $batchID, "", $payDate, $batchID, $clientID,"");
                        $totalAmount += $amount;
                    }

                }

                $db->where("client_id", $clientID);
                $db->where("batch_id", $batchIDAry, 'IN');
                $db->where("paid", 0);
                $db->update("mlm_bonus_leadership_reward", array("paid" => 1, "paid_batch_id" => $batchID)); 
            }

            $db->where("id", $batchID);
            $db->update("mlm_bonus_calculation_batch", array("paid" => 1, 'completed_at' => date('Y-m-d H:i:s')));

            Log::write(date("Y-m-d H:i:s")." Done Paid $bonusName for ".$bonusDate."\n");
        }

	}
?>
