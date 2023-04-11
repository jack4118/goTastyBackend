<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date  25/04/2018.
    **/

    class Dashboard {
        
        function __construct() {
            // $this->db = $db;
        }

        public function getDashboard($params) {
            //api for dashboard display
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $db->userID;
            $clientName = $db->username;
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            $dateTime = date("Y-m-d H:i:s");
            $poolSwitch = Setting::$systemSetting['memberPoolSwitch'];
            $kingPoolSwitch = Setting::$systemSetting['memberKingPoolSwitch'];
            $dateFormat = Setting::$systemSetting["systemDateFormat"];

            if(empty($clientID))
                $clientID = trim((string)$params['clientID']);

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00592"][$language], 'data' => "Invalid user.");

            $db->where('id',$clientID);
            $memberDetail = $db->getOne('client','member_id, username,name,created_at, transaction_password, email, concat(dial_code, phone) as phone, country_id, placement_id,main_id, avatar, sponsor_code');
            if (empty($memberDetail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            unset($memberDetail["transaction_password"]);

            $db->where("id",$memberDetail["country_id"]);
            $countryRow = $db->getOne("country", "id,name, translation_code");
            $memberDetail["countryName"] = $countryRow["name"];
            $memberDetail["countryLangCode"] = $countryRow["translation_code"];

            // Get client blocked rights - must tune it later (change to block for wallet)
            $column = array(
                "client_id",
                "(SELECT name FROM mlm_client_rights WHERE id = rights_id) AS right_name",
                "(SELECT credit_id FROM mlm_client_rights WHERE id = rights_id) AS credit_id"
            );
            $db->where("client_id", $clientID);
            $db->where("(SELECT credit_id FROM mlm_client_rights WHERE id = rights_id)", "", "!=");
            $result = $db->get("mlm_client_blocked_rights", NULL, $column);

            // Client blocked rights
            $data['blockedRights'] = $result;

            //Set BlockedRights name into array
            foreach ($result as $key => $value) {
                if ($value['credit_id'] > 0) {
                    $creditBlockedRights[]=$value['right_name'];
                }
                // $creditBlockedRights[]=$value['right_name'];
            }
            // Wallet

            $walletList = Cash::walletDisplaySetting($clientID);
            foreach ($walletList as &$walletData) {
                $balance = Cash::getBalance($clientID, $walletData["type"]);
                $walletList[$walletData['type']]['balance'] = Setting::setDecimal($balance, $walletData["type"]);
                $totalBalance += $balance;
                if($blockWallet[$walletData['type']]){
                    $walletList[$walletData['type']]['isWallet'] = 0;
                }
                $walletData['showWithdrawalHistory'] = $walletData['isWithdrawable'];
                // hide withdraw when autowithdrawal is on

                if(in_array($walletData['type'],$creditBlockedRights)){
                    $walletList[$walletData['type']]['isWallet'] = 0;
                }

                if (in_array($walletData['type']." Withdrawal",$creditBlockedRights)) {
                    $walletData['isWithdrawable'] = 0;
                }

                if (in_array($walletData['type']." Withdrawal Listing",$creditBlockedRights)) {
                    $walletData['showWithdrawalHistory'] = 0;
                }

                if (in_array($walletData['type']." Fund In by crypto",$creditBlockedRights)) {
                    $walletData['isFundinable'] = 0;
                }

                if (in_array($walletData['type']." Fund In Listing",$creditBlockedRights)) {
                    $walletData['showFundInListing'] = 0;
                }

                if (in_array($walletData['type']." Transaction History",$creditBlockedRights)) {
                    $walletData['showTransactionHistory'] = 0;
                }

                if (in_array($walletData['type']." Convert",$creditBlockedRights)) {
                    $walletData['isConvertible'] = 0;
                }

                if (in_array($walletData['type']." Transfer",$creditBlockedRights)) {
                    $walletData['isTransferable'] = 0;
                }
            }

            //Rank
            $rankData = $db->map("id")->get("rank",null,"id, translation_code");

            $db->where('client_id', $clientID);
            $db->where('name','rankDisplay');
            $db->orderBy('created_at','DESC');
            $rank_ID = $db->getOne('client_rank','rank_id')['rank_id'];

            // $clientIDAry[$clientID]   = $clientID;
            // $rankDisplay = Bonus::getClientRank("Bonus Tier",$clientIDAry,"", "goldmineBonus");

            $data['rank'] = $translations[$rankData[$rank_ID]][$language]?:"-";  

            // $db->where("client_id",$clientID);
            // $clientSales = $db->map("client_id")->get("client_sales",null,"client_id, sponsor_id, activated, downline_count, active_downline_count, own_sales, group_sales, sponsor_sales, pgp_sales, updated_at"); //temporary not using

            $db->where('client_id',$clientID);
            $ownSales = $db->getValue('mlm_bonus_in','SUM(bonus_value)');

            //New Member
            $db->where('trace_key','%'.$clientID.'%','LIKE');
            $db->where('client_id',$clientID, '!=');
            $downlineIDArr = $db->map('client_id')->get('tree_placement',null,'client_id');

            if ($downlineIDArr) {
                $activeDownlineCount = 0;
                foreach($downlineIDArr as $downlineIDRow){
                    $db->where('id',$downlineIDRow);
                    $db->where('`terminated`',0);
                    $checkStatus = $db->getOne('client');
                    if($checkStatus){
                        $activeDownlineCount += 1;
                    }
                }    

                $db->where('client_id',$downlineIDArr,'IN');
                $groupSales = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
            }

            /*Get Total Downlines Left & Right*/
            $db->where('client_id', $clientID);
            $clientPlacementLevel = $db->getOne('tree_placement', 'level');
            if ($clientPlacementLevel['level'] == "0") {
                $db->where('trace_key', $clientID."<%", "LIKE");
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineLeft = $db->getValue('tree_placement', 'client_id', null);
                if ($downlineLeft) {
                    $db->where('id', $downlineLeft, "IN");
                    $db->where('`terminated`', 0);
                    $downlineLeftCount  = $db->getValue('client', 'count(id)');    
                }

                $db->where('trace_key', $clientID.">%", "LIKE");
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineRight = $db->getValue('tree_placement', 'client_id', null);  

                if ($downlineRight) {
                    $db->where('id', $downlineRight, "IN");
                    $db->where('`terminated`', 0);
                    $downlineRightCount  = $db->getValue('client', 'count(id)');
                }

            } else {
                $db->where('trace_key','%'.$clientID.'-1<%','LIKE');
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineLeft = $db->getValue('tree_placement', 'client_id', null);
                if ($downlineLeft) {
                    $db->where('id', $downlineLeft, "IN");
                    $db->where('`terminated`', 0);
                    $downlineLeftCount  = $db->getValue('client', 'count(id)');
                }

                $db->where('trace_key','%'.$clientID.'-1>%','LIKE');
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineRight = $db->getValue('tree_placement', 'client_id', null);

                if ($downlineRight) {
                    $db->where('id', $downlineRight, "IN");
                    $db->where('`terminated`', 0);
                    $downlineRightCount  = $db->getValue('client', 'count(id)');
                }
            }

            $currentDate = date('Y-m-d');
            $db->where('bonus_date', $currentDate, "<");
            $db->where('client_id', $clientID);
            $totalCouple = $db->getValue('mlm_bonus_couple', 'SUM(total_couple)');

            // $pgpSales = $clientSales[$clientID]["pgp_sales"];
            // $ownSales = $clientSales[$clientID]["own_sales"];
            // $groupSales = $clientSales[$clientID]["group_sales"];

            //PGP Personal Group Point
            // $data['pgp'] = $pgpSales + $ownSales;
            //PVP Personal Volume Point
            $data['pvp'] = $ownSales?:0;

            //DVP Downline Volume Point
            // $data['dvp'] = $groupSales + $ownSales;
            // $data['dvp'] = $groupSales?:0;

            //Couple Number of couples
            $data['couple'] = $totalCouple?:0;

            //Total Active Downline
            $data['totalActiveDownline'] = $activeDownlineCount?:"0";
            //Total Downline
            // $data['totalDownline'] = $clientSales[$clientID]["downline_count"];
            $data['totalDownline']['left'] = $downlineLeftCount;
            $data['totalDownline']['right'] = $downlineRightCount;

            if (!empty($downlineIDArr)){
                $db->where('id',$downlineIDArr,'IN');
                $db->where('DATE(created_at)',date('Y-m-d') , '>=');
                $newMember = $db->getValue('client','count(id)');
            }

            $data['newMember'] = $newMember ?:"0";

            //newsDisplay
            $newsRes = Bulletin::newsDisplay($params, $clientID);
            $data["news"] = $newsRes["data"];
            
            //client kyc status
            $kycStatus = "New";
            $db->where("client_id",$clientID);
            $db->orderBy("created_at","DESC");
            $kycRes = $db->get("mlm_kyc",1,"status");
            foreach($kycRes as $kycRow){
                $kycStatus = $kycRow["status"];
            }
            $data["memberKycStatus"] = $kycStatus;
            $data["checkKYCFlag"] = Setting::$systemSetting['checkKYCFlag'];

            // $productPriorityAry = $db->map("id")->get("mlm_product", null, "id, priority, translation_code as langCode");

            $db->where("client_id", $clientID);
            $db->where("status", "Active");
            $db->orderBy("created_at", "ASC");
            $highestPortfolioRes = $db->get("mlm_client_portfolio", null, "client_id, product_id");
            foreach ($highestPortfolioRes as $highestPortfolioRow) {
                if($productPriorityAry[$highestPortfolioRow["product_id"]]["priority"] > $productPriorityAry[$highestPortfolioAry[$$highestProductID]]["priority"]){
                    $highestProductID = $highestPortfolioRow["product_id"];
                }
            }

            // getPV
            $db->where('client_id', $clientID);
            $db->where('updated_at', date('Y-m-01',strtotime("-5 month")), '>=');
            $db->where('updated_at',date('Y-m-01'),'<');
            $pvPrevious = $db->get('client_monthly_sales', null, 'own_sales, updated_at');

            $db->where('client_id', $clientID);
            $db->where('name', 'discountPercentage');
            $db->orderBy('id', 'ASC');
            $instantBonus = $db->get('client_rank', null, 'created_at, value');

            foreach($instantBonus as $instant){
                $bonus[date('m/Y', strtotime($instant['created_at']))] = $instant['value'];

                if(strtotime($instant['created_at'])<=strtotime("-6 month")){
                    $lastBonus = $instant['value'];
                }
            }

            for($i = 5; $i>0; $i--){
                $compareLastFiveMonth[date('m/Y',strtotime("-$i month"))] = date('m/Y',strtotime("-$i month"));
            }

            foreach($pvPrevious as $pvMonth){
                $getPrevious[date('m/Y', strtotime($pvMonth['updated_at']))] = $pvMonth['own_sales'];
            }

            foreach($compareLastFiveMonth as $value){
                $pv['month'] = $value;
                $pv['pv'] = $getPrevious[$value] ? : '-';
                $pv['instantBonus'] = $bonus[$pv['month']] ? : $lastBonus? : '-';
                $lastBonus = $bonus[$pv['month']]? : $lastBonus;
                $pvMonthly[] = $pv;
            }
            krsort($pvMonthly);
            $data['previousPVRecord'] = $pvMonthly;

            $db->where('client_id', $clientID);
            $pvTotal = $db->getValue('client_sales', 'own_sales');
            $pvPercentage = Setting::setDecimal(((floatval($pvTotal)/300) * 100));
            $data['pvPercentage'] = $pvPercentage < 100 ? floatval($pvPercentage) : 100;

            $getMonthlySales = Dashboard::getMonthlySales();

            $curRankProgress = Dashboard::getCurRankProgress($clientID,$dateTime);

            $getMonthlyDVP = Dashboard::getMonthlyDVP($downlineLeft, $downlineRight);
            
            $getMonthlyCouple = Dashboard::getMonthlyCouple();

            $getLeadershipCashReward = Dashboard::getLeadershipCashReward();

            /*$packageRes = $db->get('mlm_product', null, 'id, name, category, price, translation_code, image_name');
            foreach ($packageRes as $packageRow) {
                $packageRow['display'] = $translations[$packageRow['translation_code']][$language];
                $packageRow['price'] = Setting::setDecimal($packageRow['price']);

                $package[] = $packageRow;
            }
            $data['package'] = $package;*/

            $awardCycleDuration = Setting::$systemSetting['awardCycleDuration'];

            $db->where('client_id', $clientID);
            $db->where('name', 'awardCycleDate');
            $cashAwardRes = $db->getOne('client_setting', 'value, type, reference');
            if($cashAwardRes){
                $cashAward['endDate'] = date($dateFormat,strtotime('+'.$awardCycleDuration, strtotime($cashAwardRes['value'])));

                $dCount = $cashAwardRes['type']*25;
                $uCount = $cashAwardRes['reference']*25;
                $cashAward['director'] = $dCount>100?100:$dCount;
                $cashAward['unicorn'] = $uCount>100?100:$uCount;
            }else{
                $cashAward['endDate'] = '-';
                $cashAward['director'] = 0;
                $cashAward['unicorn'] = 0;
            }

            /* START OF COUNTDOWN FOR ACCOUNT VALIDITY */

            $db->where('id', $clientID);
            $userDetails = $db->getOne("client", null);

            $createdAt = $userDetails['created_at'];

            $db->where("client_id", $clientID);
            $db->where("name", "yearlyStartDate");
            $yearlyStartDate = $db->getValue("client_setting","value");


            $date = new DateTime($createdAt); // For today/now, don't pass an arg.
            $createdAtCompare = $date->format("Y-m-d");

            $currentTime = time();
            $date = new DateTime($createdAt);
            $date->modify("+2 day");
            $newAccountValidDate = $date->format("Y-m-d H:i:s");
            $newAccountValidDate = strtotime($newAccountValidDate);

            // handling old accounts created before new logic of purchasing starter kits
            if ($currentTime > $newAccountValidDate){
                $date = new DateTime($yearlyStartDate); // For today/now, don't pass an arg.
                $date->modify("+1 year");
                $validDate = $date->format("Y-m-d");

                $date = new DateTime($yearlyStartDate); // For today/now, don't pass an arg.
                $date->modify("-1 day");
                $date->modify("+1 year");
                $finalDate = $date->format("Y-m-d");

                $data['validPeriod'] = array(

                    "isNew" => 0,
                    "validTill" => strtotime($finalDate),
                    "purchaseBefore" =>  strtotime($validDate),

                );
            }

            else{
                // countdown for 1 year valid period
                if ($yearlyStartDate != $createdAtCompare){
                    $date = new DateTime($yearlyStartDate);
                    $date->modify("+1year");
                    $validDate = $date->format("Y-m-d");

                    $date = new DateTime($yearlyStartDate); // For today/now, don't pass an arg.
                    $date->modify("-1 day");
                    $date->modify("+1 year");
                    $finalDate = $date->format("Y-m-d");

                    $data['validPeriod'] = array(

                        "isNew" => 0,
                        "validTill" => strtotime($finalDate),
                        "purchaseBefore" =>  strtotime($validDate),

                    );
                }

                // countdown for 2 days valid period
                else{

                    $db->where("client_id", $clientID);
                    $starterKitPurchase = $db->getOne("mlm_client_portfolio");
                    if ($starterKitPurchase){
                        $date = new DateTime($yearlyStartDate);
                        $date->modify("+1year");
                        $validDate = $date->format("Y-m-d");

                        $date = new DateTime($yearlyStartDate); // For today/now, don't pass an arg.
                        $date->modify("-1 day");
                        $date->modify("+1 year");
                        $finalDate = $date->format("Y-m-d");

                        $data['validPeriod'] = array(

                            "isNew" => 0,
                            "validTill" => strtotime($finalDate),
                            "purchaseBefore" =>  strtotime($validDate),

                        );
                    }

                    else{
                        $date = new DateTime($createdAt); // For today/now, don't pass an arg.
                        $date->modify("+2 days");
                        $finalDate = $date->format("Y-m-d 00:00:00");

                        $purchaseBefore = strtotime($finalDate);

                        // $createdAt = strtotime($createdAt);
                        // $timeLeft = $purchaseBefore - $createdAt;
                        // $hours = $timeLeft/3600;

                        $data['validPeriod'] = array(

                            "isNew" => 1,
                            "validTill" => $purchaseBefore,
                            "purchaseBefore" => $purchaseBefore,

                        );
                    }
                }
            }

            
            $data['cashAward'] = $cashAward;

            $walletList = array_values($walletList);
            $data['memberDetail'] = $memberDetail;
            $data['wallet'] = $walletList;
            $data['totalBalance'] = Setting::setDecimal($totalBalance, $walletData["type"]);
            $data['highestProductID'] = $highestProductID ? $highestProductID : 0;
            $data['highestProductDisplay'] = $highestProductID ? $translations[$productPriorityAry[$highestProductID]["langCode"]][$language] : "-";
            $data['monthlySales'] = $getMonthlySales;
            // $data['monthlyDVP'] = $getMonthlyDVP;
            // $data['monthlyCouple'] = $getMonthlyCouple;
            $data['monthlyDVP'] = $getMonthlyDVP;
            $data['monthlyCouple'] = $getMonthlyCouple;
            $data['leadershipCashReward'] = $getLeadershipCashReward;
            $data['curRankProgress'] = $curRankProgress;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // for mobile apps
        public function getWallets($params){

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00592"][$language], 'data' => "");

            // Get credit types setting
            $db->where('name', 'isMember%', 'LIKE');
            $creditTypesSetting = $db->get('credit_setting', null, 'credit_id, name, value');
            if(empty($creditTypesSetting))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00593"][$language], 'data' => "");

            // Get is wallet credit types
            $isWallet = $db->subQuery();
            $isWallet->where('name', "isWallet");
            $isWallet->where('value', 1);
            $isWallet->get('credit_setting', null, 'credit_id');
            $db->where('id', $isWallet, 'IN');
            $result = $db->get('credit', null, 'id, name');
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00593"][$language], 'data' => "");

            foreach($result as $value) {
                $wallet['name'] = $value['name'];
                $wallet['balance'] = Cash::getBalance($clientID, $value["name"]);
                $walllet['balance'] = Setting::setDecimal($wallet['balance'],$wallet['name']);
                $wallet['id'] = $value['id'];
                $walletList[] = $wallet;
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $walletList);
        }

        public function getNavBarDetails($params){
            //api for navBar display
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00592"][$language], 'data' => "");

            //ald authorized = 0;
            $isFreezed = 1;
            $db->where("id",$clientID);
            $isFreezed = $db->getValue("client","freezed");
            
            $data["isAuthorized"] = $isFreezed == 1 ? 0 : 1;

            $data["memberKycStatus"] = 0;
            $checkMemberKYC = Client::checkMemberKYCStatus();
            if(empty($checkMemberKYC)){
                $data["memberKycStatus"] = 1;
            }

            $openRegister = 0;
            $db->where("main_id",$clientID);
            $subAccountCount = $db->getValue("client","count(id)");
            
            $db->where("client_id",$clientID);
            $portfolioCount = $db->getValue("mlm_client_portfolio","count(id)");

            if($subAccountCount >= 1 || $portfolioCount >= 1){
                $openRegister = 1;
            }

            $openPackageSubscription = 0;
            if($portfolioCount <= 0){
                $openPackageSubscription = 1;
            }

            if($clientID == "1000000"){
                $openRegister = 1;
                $openPackageSubscription = 0;
            }


            $data["openRegister"] = $openRegister; 
            $data["openPackageSubscription"] = $openPackageSubscription; 

            // isAutoWithdrawal
            $db->where("client_id", $clientID);
            $db->where("name", "isAutoWithdrawal");
            $isAutoWithdrawalRes = $db->getOne("client_setting", "value, type");
            if ($isAutoWithdrawalRes['value'] && $isAutoWithdrawalRes['value']!=0) {
                $isAutoWithdrawal = $isAutoWithdrawalRes['value'];
                // $hideAutoWithdrawal = $isAutoWithdrawalRes['value'];
                $withdrawalMethod = $isAutoWithdrawalRes['type'];
                // $adminSetWithdrawalMethod = $isAutoWithdrawalRes['type'];
                $autowithdrawalStatus = $isAutoWithdrawalRes['value'];
            } else {
                $isAutoWithdrawal = "0";
                $hideAutoWithdrawal = "0";
                $autowithdrawalStatus = "0";                
            } 
            // if withdrawal is added cannot add again
            if($isAutoWithdrawalRes['type']) $isAutoWithdrawal = 1;
            // $db->where("id",$clientID);
            // $country = $db->getValue("client","(SELECT name FROM country WHERE id = country_id)");
            // $withdrawalMethod = "crypto"; // default crypto
            // if($country=="China") $withdrawalMethod = "bank"; // only china bank
            // Get client blocked rights - must tune it later (change to block for report and display)

            $db->where("id",$clientID);
            $isLoginByMainAcc = $db->getValue("client","main_login");

            $clientRank = Bonus::getClientRank('Bonus Tier', array($clientID), '', 'discount');
            $discountPercentage = $clientRank[$clientID]['percentage'];

            $starterKitPurchased = 0;
            $db->where('is_starter_kit', 1);
            $starterKitPackage = $db->getValue('mlm_product', 'id', null);
            if($starterKitPackage){
                $db->where('client_id', $clientID);
                $db->where('product_id', $starterKitPackage, 'IN');
                $starterKit = $db->getValue('mlm_client_portfolio', 'count(id)');

                if($starterKit >= 1){
                    $starterKitPurchased = 1;
                }
            }

            $walletList = Cash::walletDisplaySetting($clientID);
            foreach ($walletList as &$walletData) {
                if($walletData["isPurchaseCredit"] == 1){
                    $balance = Cash::getBalance($clientID, $walletData["type"]);
                    $totalBalance += $balance;
                }
            }

            //Get Shopping Cart Count
            $db->where('client_id', $clientID);
            $db->groupBy('client_id');
            $shpCartCount = $db->getValue('shopping_cart','SUM(quantity)');

            $db->where('client_id',$clientID);
            $placementExist = $db->getOne('tree_placement');

            $data['placementExist'] = $placementExist ? 1 : 0;
            $data['autowithdrawalStatus'] = $autowithdrawalStatus;
            $data['isAutoWithdrawal'] = $isAutoWithdrawal;
            $data['withdrawalMethod'] = $withdrawalMethod;
            $data['isLoginByMainAcc'] = $isLoginByMainAcc;
            $data['discountPercentage'] = $discountPercentage ? : 0;
            $data['starterKitPurchased'] = $starterKitPurchased;
            $data['shpCartCount'] = $shpCartCount;
            $data['totalBalance'] = $totalBalance ? : 0;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getMLMDashboard($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];
            $clientID = $db->userID;
            $dateTime = date("Y-m-d H:i:s");

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00592"][$language], 'data' => "Invalid user.");

            $maxPackagePriority = $db->getValue('mlm_product', 'MAX(priority)');

            $portfolioColumn = array(
                "created_at",
                "status",
                "product_price AS productPrice",
                "(SELECT priority FROM mlm_product WHERE id = product_id) AS product_priority",
                "(SELECT translation_code FROM mlm_product WHERE id = product_id) AS product_TS",
                "expire_at"
            );
            $db->where("created_at", date("Y-01-01 00:00:00"), ">=");
            $db->where("created_at", date("Y-12-31 23:59:59"), "<=");
            $db->where("client_id", $clientID);
            $db->orderBy("id", "ASC");
            $portfolioList = $db->get('mlm_client_portfolio', NULL, $portfolioColumn);
            foreach ($portfolioList as &$portfolioListValue) {
                $portfolioListValue['package_Display'] = $translations[$portfolioListValue['product_TS']][$language];
                $portfolioListValue['status_Display'] = $portfolioListValue['status'] == 'Active' ? $translations['M00329'][$language] : $translations['M00330'][$language];
                $portfolioListValue['created_at'] = $portfolioListValue['created_at'] == "0000-00-00 00:00:00" ? "-" : date($dateTimeFormat, strtotime($portfolioListValue['created_at']));
                $portfolioListValue['expire_at'] = $portfolioListValue['expire_at'] == "0000-00-00 00:00:00" ? "-" : date($dateTimeFormat, strtotime($portfolioListValue['expire_at']));
                if($portfolioListValue['status'] == 'Active') $expiredDate = $portfolioListValue['expire_at'];
                if($portfolioListValue['product_priority'] == $maxPackagePriority) $maxPackage = 1;
            }

            $sponsorRankRes = Bonus::getClientRank('Sponsor Rank', array($clientID), $dateTime, "sponsorBonus");
            $sponsorRankPercentage = $sponsorRankRes[$clientID]['percentage'] ? $sponsorRankRes[$clientID]['percentage'] : "-";

            $waterBucketRankRes = Bonus::getClientRank("Bonus Tier", array($clientID), $dateTime, "waterBucketBonus");
            $waterBucketRankPercentage = $waterBucketRankRes[$clientID]['percentage'] ? $waterBucketRankRes[$clientID]['percentage'] : "-";

            if(empty($portfolioList)){
                $data["canUpgrade"]   = 0;
                $data["canPurchase"]  = 1;
            } else {
                if($maxPackage){
                    $data["canUpgrade"]   = 0;
                } else {
                    $data["canUpgrade"]   = 1;
                }
                $data["canPurchase"]  = 0;
            }
            
            $data["rate"]  = 1;
            $data["sponsorRankPercentage"]  = $sponsorRankPercentage;
            $data["waterBucketRankPercentage"]  = $waterBucketRankPercentage;
            $data["portfolioList"]  = $portfolioList;
            $data["expired_date"]   = $expiredDate;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getMonthlySales(){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            if(empty($clientID))
                return false;

            // $db->where('client_id', $clientID);
            // $db->where('updated_at', date('Y-m-d',strtotime("-6 month")), '>=');
            // $db->orderBy('updated_at', 'DESC');
            // $db->groupBy(("MONTH(updated_at)"));
            // $getMonthSales = $db->get('client_monthly_sales',NULL, ' SUM(own_sales) AS totalSales, updated_at');

            $db->where('created_at', date('Y-m-d',strtotime("-6 month")), '>=');
            // $db->orderBy('created_at', 'DESC');
            $db->orderBy('id', 'DESC');
            $getDateRange = $db->get("mlm_bonus_in", NULL,"DISTINCT CONCAT(YEAR(created_at),'-',MONTH(created_at),'-01')AS created_at");

            $db->where('client_id', $clientID);
            $db->where('created_at', date('Y-m-d',strtotime("-6 month")), '>=');
            $db->orderBy('created_at', 'DESC');
            $db->groupBy(("MONTH(created_at)"));
            $getMonthPVPRes = $db->get('mlm_bonus_in',NULL, ' SUM(bonus_value) AS bonus_value, created_at');

            foreach($getMonthPVPRes as $monthPVP){
                $monthPVPArr[date('m/Y', strtotime($monthPVP['created_at']))] = Setting::setDecimal($monthPVP['bonus_value']);
            }

            $db->where('client_id', $clientID);
            $db->where('created_at', date('Y-m-d',strtotime("-6 month")), '>=');
            $db->groupBy(("MONTH(created_at)"));
            $getProdPrice = $db->get('mlm_client_portfolio',NULL, ' SUM(product_price) AS totalProdPrice, created_at');
            
            foreach($getProdPrice as $getProdPrices){
                $prodPriceArr[date('m/Y', strtotime($getProdPrices['created_at']))] = Setting::setDecimal($getProdPrices['totalProdPrice']);
            }

            // foreach ($getMonthPVPRes as $getMonthPVP){

            //     $monthlySales['months'] =  date('m/Y', strtotime($getMonthPVP['created_at']));
            //     $monthlySales['sales'] = $prodPriceArr[date('m/Y', strtotime($getMonthPVP['created_at']))] ? $prodPriceArr[date('m/Y', strtotime($getMonthPVP['created_at']))] : Setting::setDecimal(0);
            //     $monthlySales['pv'] = Setting::setDecimal($getMonthPVP['bonus_value']); 
            //     $monthlySalesRecord[] = $monthlySales;
            // }

            foreach ($getDateRange as $dateRange){
                $monthlySales['months'] =  date('m/Y', strtotime($dateRange['created_at']));
                $monthlySales['sales'] = $prodPriceArr[date('m/Y', strtotime($dateRange['created_at']))] ? $prodPriceArr[date('m/Y', strtotime($dateRange['created_at']))] : Setting::setDecimal(0);
                $monthlySales['pv'] = $monthPVPArr[date('m/Y', strtotime($dateRange['created_at']))] ? $monthPVPArr[date('m/Y', strtotime($dateRange['created_at']))] : Setting::setDecimal(0);
                $monthlySalesRecord[] = $monthlySales;
            }

            return $monthlySalesRecord;

        }

        public function getMonthlyDVP($downlineLeft, $downlineRight){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");

            $db->where('created_at', date('Y-m-d',strtotime("-6 month")), '>=');
            // $db->orderBy('created_at', 'DESC');
            $db->orderBy('id', 'DESC');
            $getDateRange = $db->get("mlm_bonus_in", NULL,"DISTINCT CONCAT(YEAR(created_at),'-',MONTH(created_at),'-01')AS created_at");

            if (!$downlineLeft) {
                $downlineLeft = array();
            }

            if (!$downlineRight) {
                $downlineRight = array();
            }

            $downlines = array_merge($downlineLeft, $downlineRight);

            if (!empty($downlines)) {
                $db->where('client_id', $downlines, "IN");
                $db->where('created_at', date('Y-m-d',strtotime("-6 month")), '>=');
                $db->groupBy(("MONTH(created_at)"));
                $getProdPrice = $db->get('mlm_client_portfolio',NULL, ' SUM(product_price) AS totalProdPrice, created_at');    
            }

            $downlineLeftSq = $db->subQuery();
            if ($downlineLeft) {
                $downlineLeftSq->where('id', $downlineLeft, "IN");
                $downlineLeftSq->where('`terminated`', "0");
                $downlineLeftSq->getValue('client', 'id', null);
                $db->where('client_id', $downlineLeftSq, "IN");
                $db->where('created_at', date('Y-m-d', strtotime("-6 month")), ">=");
                $db->groupBy('MONTH(created_at)');
                $monthlyDVPLeft = $db->get('mlm_bonus_in', NULL,'SUM(bonus_value) AS bonus_value, created_at');    
            }

            $downlineRightSq = $db->subQuery();
            if ($downlineRight) {
                $downlineRightSq->where('id', $downlineRight, "IN");
                $downlineRightSq->where('`terminated`', "0");
                $downlineRightSq->getValue('client', 'id', null);
                $db->where('client_id', $downlineRightSq, "IN");
                $db->where('created_at', date('Y-m-d', strtotime("-6 month")), ">=");
                $db->groupBy('MONTH(created_at)');
                $monthlyDVPRight = $db->get('mlm_bonus_in', NULL,'SUM(bonus_value) AS bonus_value, created_at');
            }
            
            foreach($monthlyDVPLeft as $dvpLeft){
                $dvpLeftArr[date('m/Y', strtotime($dvpLeft['created_at']))] = Setting::setDecimal($dvpLeft['bonus_value']);
            }

            foreach($monthlyDVPRight as $dvpRight) {
                $dvpRightArr[date('m/Y', strtotime($dvpRight['created_at']))] = Setting::setDecimal($dvpRight['bonus_value']);
            }

            foreach($getProdPrice as $prodPrice) {
                $prodPriceArr[date('m/Y', strtotime($prodPrice['created_at']))] = Setting::setDecimal($prodPrice['totalProdPrice']);
            }

            foreach ($getDateRange as $dateRange){
                $dateMonth = date('m/Y', strtotime($dateRange['created_at']));
                $monthlyDVP['months'] =  $dateMonth;
                $monthlyDVP['dvpLeft'] = $dvpLeftArr[$dateMonth] ? $dvpLeftArr[$dateMonth] : Setting::setDecimal(0);
                $monthlyDVP['dvpRight'] = $dvpRightArr[$dateMonth] ? $dvpRightArr[$dateMonth] : Setting::setDecimal(0);
                $monthlyDVP['sales'] = $prodPriceArr[$dateMonth] ? $prodPriceArr[$dateMonth] : Setting::setDecimal(0);
                $monthlyDVPRecord[] = $monthlyDVP;
            }

            return $monthlyDVPRecord;
        }

         public function getMonthlyCouple(){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            if(empty($clientID))
                return false;

            $db->where('created_at', date('Y-m-d',strtotime("-6 month")), '>=');
            // $db->orderBy('created_at', 'DESC');
            $db->orderBy('id', 'DESC');
            $getDateRange = $db->get("mlm_bonus_couple", NULL,"DISTINCT CONCAT(YEAR(created_at),'-',MONTH(created_at),'-01')AS created_at");

            $db->where('client_id', $clientID);
            $db->orderBy('created_at', "DESC");
            $db->groupBy('MONTH(created_at)');
            $getCoupleRes = $db->get('mlm_bonus_couple', NULL, "SUM(total_couple) AS total_couple, created_at");

            foreach($getCoupleRes as $couple) {
                $coupleArr[date('m/Y', strtotime($couple['created_at']))] = $couple['total_couple'];
            }

            foreach ($getDateRange as $dateRange){
                $monthlyCouple['months'] =  date('m/Y', strtotime($dateRange['created_at']));
                $monthlyCouple['couples'] = $coupleArr[date('m/Y', strtotime($dateRange['created_at']))] ? $coupleArr[date('m/Y', strtotime($dateRange['created_at']))] : 0;
                $monthlyCoupleRecord[] = $monthlyCouple;
            }

            return $monthlyCoupleRecord;
        }

    public function getLeadershipCashReward(){
        $db             = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $clientID       = $db->userID;
        $dateTime       = date("Y-m-d H:i:s");
        $currentDate    = date("Y-m-d");

        if(empty($clientID))
            return false;

        $db->where('name', 'leadershipRewardRank');
        $leadershipRewardSetting = $db->getValue('mlm_bonus_setting', 'value');

        $leadershipRewardSettingValues = explode('#', $leadershipRewardSetting);

        $rankData = $db->map('id')->get('rank', null, 'id, name, translation_code');

        /*Get client's join date and 1 year range*/
        $db->where('id', $clientID);
        $clientJoinDate = $db->getOne('client', 'created_at');
        $joinTS = strtotime($clientJoinDate['created_at']);
        $currentTS = strtotime($currentDate);

        if($currentDate<="2023-08-31" && date("Y-m-d", strtotime($clientJoinDate['created_at']))<="2022-08-31") {

       		$db->where("client_id", $clientID);
            $db->where("name", "yearlyStartDate");
            $joinTS2 = $db->getValue("client_setting", "value");
            $remainingTS = strtotime("+ 1 year", strtotime($joinTS2)) - $currentTS;
            $remainingDays = floor($remainingTS / 86400);

			$db->where('client_id', $clientID);
	        $db->where('bonus_date', $currentDate, "<");
	        $db->where('bonus_date', date('Y-m-d', $joinTS), ">=");
	        $coupleDataRes = $db->getOne('mlm_bonus_couple', 'SUM(total_couple) as total_couple');

        } else {
            $date1 = date("Y-m-d",$joinTS);
            $date2 = date("Y-m-d", $currentTS);
            $d1=new DateTime($date2);
            $d2=new DateTime($date1);
            $Months = $d2->diff($d1); 
            $howeverManyMonths = (($Months->y) * 12) + ($Months->m);

        	$joinYear = date("Y", $joinTS);
	        $currentYear = date("Y", $currentTS);
	        $yearPassed = $currentYear - $joinYear;

	        if ($yearPassed > 0) {
	            $newJoinDateTS = strtotime("+".$yearPassed." year", $joinTS);
	            $tsRemained = $newJoinDateTS - $currentTS;
	            $years = $yearPassed;

	            if ($tsRemained <= 0) {
	                $years += 1;
	                $remainingTS = strtotime("+".$years." year", $joinTS) - $currentTS;
	            } else {
	                $remainingTS = strtotime("+".$years." year", $joinTS) - $currentTS;
	            }
	        } else {
	            $remainingTS = strtotime("+1 year", $joinTS) - $currentTS;
	        }

	        $remainingDays = floor($remainingTS / 86400);

	        $db->where('client_id', $clientID);
	        $db->where('bonus_date', $currentDate, "<");
	        if (($yearPassed > 0) && ($howeverManyMonths > 12)) {
	            if ($tsRemained <= 0) {
	                $latestOneYearRange = date('Y-m-d', strtotime("+".$years." year", $joinTS));
	                $db->where('bonus_date', $latestOneYearRange, ">=");
	            } else {
	                $latestOneYearRange = date('Y-m-d', strtotime("+".$yearPassed." year", $joinTS));
	                $db->where('bonus_date', $latestOneYearRange, ">=");
	            }

	        } else {
	            $db->where('bonus_date', date('Y-m-d', $joinTS), ">=");
	        }
	        $coupleDataRes = $db->getOne('mlm_bonus_couple', 'SUM(total_couple) as total_couple');


        }

        $coupleData = $coupleDataRes['total_couple']?:0;

        /*Get client's next target value*/
        foreach($leadershipRewardSettingValues as $rankKey => $rankValue) {
            if ($coupleData >= $rankValue) {
                $nextTarget = $leadershipRewardSettingValues[$rankKey + 1];
                $previousTarget = $rankValue;
                if ($leadershipRewardSettingValues[$rankKey + 1] == null) {
                    $nextTarget = "-";
                }
            }
        }

        $nextTarget = $nextTarget?:$leadershipRewardSettingValues[0];
        $previousTarget = $previousTarget?:0;

        /*Calculate progress percentage*/
        if ($nextTarget != "-") {
            $progressPercentage = $coupleData / $nextTarget * 100;
        } else {
            $progressPercentage = 100;
        }

        $data['nextTargetValue'] = $nextTarget;
        $data['totalCouple'] = $coupleData;
        $data['remainingDays'] = $remainingDays;
        $data['progressPercent'] = Setting::setDecimal($progressPercentage);

        return $data;
    }

        public function getCurRankProgress($clientID,$dateTime){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $rankType       = "Bonus Tier";
            $checkDownlineRank = 0;
            if(!$dateTime) $dateTime = date("Y-m-d H:i:s");
            $date = date('Y-m-d',strtotime($dateTime));
            $resetDate = date('Y-m-t',strtotime($dateTime));

            if(empty($clientID)) return false;

            // Get Current Rank
            $rankData = Bonus::getClientRank($rankType,array($clientID),$dateTime,"goldmineBonus","System");

            // Get Rank Data
            $rankRes = $db->get('rank',null,'id,priority,translation_code');
            foreach ($rankRes as $rankRow) {
                $rankPriorityArr[$rankRow['id']] = $rankRow['priority'];
                $rankIDArr[$rankRow['priority']] = $rankRow['id'];
                $rankLangCode[$rankRow['id']] = $rankRow['translation_code'];
            }

            $curRankPriority = $rankData[$clientID]['rank_id']?$rankPriorityArr[$rankData[$clientID]['rank_id']]:1;
            $nextRankPriority = $curRankPriority+1;
            if($nextRankPriority >= MAX($rankPriorityArr)) $nextRankPriority = MAX($rankPriorityArr);
            $nextRankID = $rankIDArr[$nextRankPriority];

            // Get Rank Setting
            $db->where('rank_id',$nextRankID);
            $db->where('type','purchase');
            $rankRes = $db->get('rank_setting',null,'name,value,reference');
            foreach ($rankRes as $rankRow) {
                if(($rankRow['name'] == 'minFirstDownlineRank') && ($rankRow['value'] > 0)){
                    $checkDownlineRank = 1;
                }
            }

            if($checkDownlineRank){
                $downlineRes = array();
                $secDownlineArr = array();

                $db->where('sponsor_id',$clientID);
                $downlineRes = $db->map('id')->get('client',null,'id');

                $db->where('sponsor_id',array_keys($downlineRes),"IN");
                $secDownlineArr = $db->map('id')->get('client',null,'id');

                $downlineArr = array_merge($downlineRes,$secDownlineArr);

                $rankData = Bonus::getClientRank($rankType,$downlineArr,$dateTime,"goldmineBonus","System");
            }

            $db->where('client_id',$clientID);
            $clientSalesData = $db->getOne('client_sales',null,'active_downline_count,own_sales,group_sales,sponsor_sales,pgp_sales');
            $totalPercent = 0;
            $totalEntitlePercent = 0;
            foreach ($rankRes as $rankRow) {
                $entitlePercent = 0;
                $entitleFirstDownline = 0;
                $entitleSecDownline = 0;

                switch ($rankRow['name']) {
                    case 'minActiveLeg':
                        if($rankRow['value'] <= 0) continue;

                        $totalPercent += 100;
                        $entitlePercent = ($clientSalesData['active_downline_count'] / $rankRow['value']) * 100;
                        $entitlePercent = Setting::setDecimal($entitlePercent,2);
                        break;

                    case 'minGroupSales':
                        if($rankRow['value'] <= 0) continue;

                        $totalPercent += 100;
                        $entitlePercent = ($clientSalesData['group_sales']+$clientSalesData['own_sales'] / $rankRow['value']) * 100;
                        $entitlePercent = Setting::setDecimal($entitlePercent,2);
                        break;

                    case 'minOwnSales':
                        if($rankRow['value'] <= 0) continue;

                        $totalPercent += 100;
                        $entitlePercent = ($clientSalesData['own_sales'] / $rankRow['value']) * 100;
                        $entitlePercent = Setting::setDecimal($entitlePercent,2);
                        break;

                    case 'minPGPSales':
                        if($rankRow['value'] <= 0) continue;

                        $totalPercent += 100;
                        $entitlePercent = ($clientSalesData['pgp_sales']+$clientSalesData['own_sales'] / $rankRow['value']) * 100;
                        $entitlePercent = Setting::setDecimal($entitlePercent,2);
                        break;

                    case 'minFirstDownlineRank':
                        if($rankRow['value'] <= 0) continue;

                        foreach ($downlineRes as $downlineID) {
                            $downlineRankID = $rankData[$downlineID]['rank_id'];
                            if($rankPriorityArr[$downlineRankID] >= $rankRow['value']){
                                $entitleFirstDownline += 1;
                            }
                        }

                        $totalPercent += 100;
                        $entitlePercent = ($entitleFirstDownline / $rankRow['reference']) * 100;
                        $entitlePercent = Setting::setDecimal($entitlePercent,2);
                        break;

                    case 'minSecDownlineRank':
                        if($rankRow['value'] <= 0) continue;

                        foreach ($secDownlineArr as $secDownlineID) {
                            $secDownlineRankID = $rankData[$secDownlineID]['rank_id'];
                            if($rankPriorityArr[$secDownlineRankID] >= $rankRow['value']){
                                $entitleSecDownline += 1;
                            }
                        }

                        $totalPercent += 100;
                        $entitlePercent = ($entitleSecDownline / $rankRow['reference']) * 100;
                        $entitlePercent = Setting::setDecimal($entitlePercent,2);
                        break;
                }

                if($rankRow['value'] > 0){
                    if($entitlePercent>100) $entitlePercent = Setting::setDecimal(100);
                    $entitlePercentArr[$rankRow['name']] = $entitlePercent;
                    $totalEntitlePercent += $entitlePercent;
                }
            }

            $data['entitlePercentArr']      = $entitlePercentArr;
            $data['totalPercent']           = $totalPercent;
            $data['totalEntitlePercent']    = Setting::setDecimal(($totalEntitlePercent/$totalPercent)*100);
            $data['nextRankDisplay']        = $rankLangCode[$nextRankID]?$translations[$rankLangCode[$nextRankID]][$language]:"-";
            $data['remainingDays']          = abs(strtotime($resetDate) - strtotime($date)) / 86400;
            
            return $data;
        }

        public function getCashAward($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            $year = trim($params['year']);
            if(!$year) $year = date("Y");

            $rankDisplayCode = $db->map("id")->get("rank", null, "id, name, translation_code");

            $db->where("client_id", $clientID);
            $currentSale = $db->getOne("client_sales", "pgp_sales, own_sales, group_sales");

            $currentRankRes = Bonus::getClientRank("Bonus Tier", array($clientID), "", "goldmineBonus", "System");
            $currentRank = $currentRankRes[$clientID]['rank_id'];

            $db->where("client_id", $clientID);
            $db->where("YEAR(updated_at)", $year);
            $salesRes = $db->map("MONTH(updated_at)")->get("client_monthly_sales", null, "MONTH(updated_at), pgp_sales, own_sales, group_sales");

            $db->where("client_id", $clientID);
            $db->where("YEAR(created_at)", $year);
            $rankRes = $db->map("MONTH(created_at)")->get("client_rank_monthly", null, "MONTH(created_at), rank_id");

            $db->where("client_id", $clientID);
            $db->where("YEAR(bonus_date)", $year);
            $db->groupBy("MONTH(bonus_date)");
            $awardRes = $db->map("MONTH(bonus_date)")->get("mlm_bonus_award", null, "MONTH(bonus_date), id");

            // 12 months
            for($i = 1; $i <= 12; $i++){
                $tmp['rankName'] = "-";
                $tmp['rankCode'] = "-";
                $tmp['rankDisplay'] = "-";
                $tmp['PGP'] = "-";
                $tmp['DVP'] = "-";

                $tmp['fancy'] = 0;
                if($awardRes[$i]){
                    $tmp['fancy'] = 1;
                }

                if($rankRes[$i]){
                    $tmp['rankName'] = $rankDisplayCode[$rankRes[$i]]['name'];
                    $tmp['rankCode'] = $rankDisplayCode[$rankRes[$i]]['translation_code'];
                    $tmp['rankDisplay'] = $translations[$rankDisplayCode[$rankRes[$i]]['translation_code']][$language];
                }

                if($salesRes[$i]){

                    $tmp['PGP'] = Setting::setDecimal($salesRes[$i]['own_sales'] + $salesRes[$i]['pgp_sales']);
                    $tmp['DVP'] = Setting::setDecimal($salesRes[$i]['own_sales'] + $salesRes[$i]['group_sales']);
                }

                $monthName = date("F", strtotime("2000-".$i."-01"));
                $dataList[$monthName] = $tmp;
            }

            $data['current']['rankName'] = $rankDisplayCode[$currentRank]['name']?:'-';
            $data['current']['rankCode'] = $rankDisplayCode[$currentRank]['translation_code']?:'-';
            $data['current']['rankDisplay'] = $translations[$rankDisplayCode[$currentRank]['translation_code']][$language]?:'-';

            $data['current']['PGP'] = Setting::setDecimal($currentSale['pgp_sales']);
            $data['current']['DVP'] = Setting::setDecimal($currentSale['own_sales'] + $currentSale['group_sales']);

            $data["dataList"]   = $dataList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getStarAward($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            $year = trim($params['year']);
            if(!$year) $year = date("Y");

            $rankDisplayCode = $db->map("id")->get("rank", null, "id, name, translation_code");

            $db->where("client_id", $clientID);
            $currentSale = $db->getOne("client_sales", "pgp_sales, own_sales, group_sales");

            $db->where("client_id", $clientID);
            $db->where("YEAR(updated_at)", $year);
            $salesRes = $db->map("MONTH(updated_at)")->get("client_monthly_sales", null, "MONTH(updated_at), pgp_sales, own_sales, group_sales");

            $db->where("client_id", $clientID);
            $db->where("YEAR(created_at)", $year);
            $db->groupBy("MONTH(created_at)");
            $totalSalesRes = $db->map("MONTH(created_at)")->get("mlm_client_portfolio", null, "MONTH(created_at), SUM(product_price) AS totalSales");

            $curPercRes = Bonus::getClientRank("Bonus Tier", array($clientID), '', "discount", "System");
            $curPerc = $curPercRes[$clientID]['percentage']?:'-';

            $db->where('client_id', $clientID);
            $db->where('name', 'discountPercentage');
            $db->orderBy('id', 'ASC');
            $instantBonus = $db->get('client_rank', null, 'created_at, value, MONTH(created_at) AS month, YEAR(created_at) AS year');

            $currentRankRes = Bonus::getClientRank("Bonus Tier", array($clientID), "", "goldmineBonus", "System");
            $currentRank = $currentRankRes[$clientID]['rank_id'];

            $lastBonus = '-';
            foreach($instantBonus as $instant){
                if($instant['year']==$year)
                    $bonus[$instant['month']] = $instant['value'];

                if(strtotime($instant['created_at'])<=strtotime($year."-01-01 23:59:59 -1 days")){
                    $lastBonus = $instant['value'];
                }
            }

            // 12 months
            for($i = 1; $i <= 12; $i++){
                $tmp['perc'] = '-';
                $tmp['totalSales'] = "-";
                $tmp['totalPV'] = "-";

                if($bonus[$i]){
                    $tmp['perc'] = $bonus[$i];
                    $lastBonus = $bonus[$i];
                }else{
                    $tmp['perc'] = $lastBonus;
                    if($i>date('m')){
                        $tmp['perc'] = '-';
                    }
                }

                if($totalSalesRes[$i]){
                    $tmp['totalSales'] = Setting::setDecimal($totalSalesRes[$i]);
                }

                if($salesRes[$i]){
                    $tmp['totalPV'] = Setting::setDecimal($salesRes[$i]['own_sales']);
                }

                $monthName = date("F", strtotime("2000-".$i."-01"));
                $dataList[$monthName] = $tmp;
            }

            $data['current']['pvLimit'] = 300;

            $data['current']['rankName'] = $rankDisplayCode[$currentRank]['name']?:'-';
            $data['current']['rankCode'] = $rankDisplayCode[$currentRank]['translation_code']?:'-';
            $data['current']['rankDisplay'] = $translations[$rankDisplayCode[$currentRank]['translation_code']][$language]?:'-';

            $data['current']['perc'] = $curPerc;
            $data['current']['totalSales'] = $currentSale['group_sales']?Setting::setDecimal($currentSale['group_sales']):'-';
            if($currentSale['group_sales']>300) $currentSale['group_sales'] = 300;
            $data['current']['totalPV'] = $currentSale['group_sales']?Setting::setDecimal($currentSale['own_sales']):'-';

            $data["dataList"]   = $dataList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getProductStockDetail($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            
            $dateFormat     = Setting::$systemSetting["systemDateFormat"];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            $getQuantity = General::getSystemSettingAdmin('lowStockQuantity');
            $quantity = $getQuantity['lowStockQuantity']['value'] ? : 0;

            $db->where('status', 'Active');
            $activeProductIDRes = $db->getValue('inv_product', 'id', null);

            if(!$activeProductIDRes){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }
            
            $db->where('inv_product_id', $activeProductIDRes, 'IN');
            $db->groupBy('inv_product_id');
            $db->orderBy('stock_out', 'DESC');
            $copyDb = $db->copy();
            $copyDb2= $db->copy();
            // $copyDb3= $db->copy();
            $db->where('subject', array('Stock Out'), 'NOT IN');
            $allProductRes = $db->get('inv_stock_transaction', 5, 'inv_product_id, SUM(amount_out) as stock_out');

            $copyDb->having('SUM(stock_in)-SUM(stock_out)','0','<=');
            $copyDb4= $copyDb->copy();
            $outOfStockRes = $copyDb->get('inv_stock', 5, 'inv_product_id, SUM(stock_in) as stock_in, SUM(stock_out) as stock_out');

            $copyDb2->having('SUM(stock_in)-SUM(stock_out)', '0', '>');
            $copyDb2->having('SUM(stock_in)-SUM(stock_out)', $quantity, '<=');
            $copyDb5= $copyDb2->copy();            
            $lowInStockRes = $copyDb2->get('inv_stock', 5, 'inv_product_id, SUM(stock_in) as stock_in, SUM(stock_out) as stock_out');

            if(empty($allProductRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            // $productSellingRes = $copyDb3->getValue('inv_stock', 'COUNT(*)', null);
            // $productSelling = $copyDb3->count;   
            $db->where('status', 'Active');
            $productSelling = $db->getValue('inv_product', 'COUNT(*)');

            $outOfStockResult = $copyDb4->getValue('inv_stock', 'COUNT(*)', null);
            $outOfStock = $copyDb4->count;

            $lowInStockResult = $copyDb5->getValue('inv_stock', 'COUNT(*)', null);
            $lowInStock = $copyDb5->count;

            $sq = $db->subQuery();
            $sq->groupBy('inv_product_id');
            $sq->get('inv_stock', null, 'inv_product_id');
            $db->where('id', $sq, 'NOT IN');
            $db->where('status', 'Active');
            $db->orderBy('created_at', 'DESC');
            $copyDb3 = $db->copy();
            $outOfStockWithoutTransactionRes = $db->getValue('inv_product', 'COUNT(*)');

            if($outOfStock < 5){
                $outOfStockNoTransactionRes = $copyDb3->get('inv_product', (5-$outOfStock), 'id, code, name, alert_at');
            }

            foreach($allProductRes as $allIDRow){
                $allIDAry[$allIDRow['inv_product_id']] = $allIDRow['inv_product_id'];
                $getAllProductOnlyRes[$allIDRow['inv_product_id']] = $allIDRow['inv_product_id'];
            }
            foreach($outOfStockRes as $outOfStockIDRow){
                $allIDAry[$outOfStockIDRow['inv_product_id']] = $outOfStockIDRow['inv_product_id'];
            }
            if($outOfStockNoTransactionRes){
                foreach($outOfStockNoTransactionRes as $getName){
                    $allIDAry[$getName['id']] = $getName['id'];
                }
            }
            foreach($lowInStockRes as $lowInStockIDRow){
                $allIDAry[$lowInStockIDRow['inv_product_id']] = $lowInStockIDRow['inv_product_id'];
            }
            if($allIDAry){
                $db->where('id', $allIDAry, 'IN');
                $productNameRes = $db->map('id')->get('inv_product', null, 'id, name, code, alert_at');
            }

            foreach($activeProductIDRes as $key=>$value){
                if(in_array($value, $getAllProductOnlyRes)){
                    unset($activeProductIDRes[$key]);                    
                }
            }

            if($activeProductIDRes){
                $db->where('id', $activeProductIDRes, 'IN');
                $prodNameRes = $db->map('id')->get('inv_product', null, 'id, name, code, alert_at');
            }

            foreach($allProductRes as $value){
                $productList['productID']= $value['inv_product_id'];
                $productList['productCode']= $productNameRes[$value['inv_product_id']]['code'];
                $productList['productName']= $productNameRes[$value['inv_product_id']]['name'];
                $productList['quantitySold']= $value['stock_out'];

                $allProductList[] = $productList;
            }
            $allProdCount = count($allProductList);
            if(!empty($activeProductIDRes) && $allProdCount<5){
                foreach($activeProductIDRes as $getID){
                    $productList['productID']= $getID;
                    $productList['productCode']= $prodNameRes[$getID]['code'];
                    $productList['productName']= $prodNameRes[$getID]['name'];
                    $productList['quantitySold']= '0';

                    $allProductList[] = $productList;
                    $allProdCount += 1;
                    if($allProdCount == 5) break;
                }
            }

            array_multisort(array_column($allProductList, 'quantitySold'), SORT_DESC, $allProductList);

            foreach($outOfStockRes as $outOfStockRow){
                $outOfStockList['productID']= $outOfStockRow['inv_product_id'];
                $outOfStockList['productCode'] = $productNameRes[$outOfStockRow['inv_product_id']]['code'];
                $outOfStockList['productName'] = $productNameRes[$outOfStockRow['inv_product_id']]['name'];
                $outOfStockList['outOfStockDate'] = $productNameRes[$outOfStockRow['inv_product_id']]['alert_at'] > 0 ? date($dateFormat, strtotime($productNameRes[$outOfStockRow['inv_product_id']]['alert_at'])) : '-';

                $allOutOfStockList[$outOfStockRow['inv_product_id']] = $outOfStockList;
            }

            if($outOfStockNoTransactionRes){
                foreach($outOfStockNoTransactionRes as $outOfStockRow2){
                    $outOfStockList['productID']= $outOfStockRow2['id'];
                    $outOfStockList['productCode'] = $outOfStockRow2['code'];
                    $outOfStockList['productName'] = $productNameRes[$outOfStockRow2['id']]['name'];
                    $outOfStockList['outOfStockDate'] = $outOfStockRow2['alert_at'] > 0 ? date($dateFormat, strtotime($outOfStockRow2['alert_at'])) : '-';

                    $allOutOfStockList[$outOfStockRow2['id']] = $outOfStockList;
                }
            }

            foreach($lowInStockRes as $lowInStockRow){
                $stockLeft = $lowInStockRow['stock_in']-$lowInStockRow['stock_out'];

                $lowInStockList['lowInStockDate'] = $productNameRes[$lowInStockRow['inv_product_id']]['alert_at'] > 0 ? date($dateFormat, strtotime($productNameRes[$lowInStockRow['inv_product_id']]['alert_at'])) : '-';
                $lowInStockList['productID'] = $lowInStockRow['inv_product_id'];
                $lowInStockList['productCode'] = $productNameRes[$lowInStockRow['inv_product_id']]['code'];
                $lowInStockList['productName'] = $productNameRes[$lowInStockRow['inv_product_id']]['name'];
                $lowInStockList['productQuantity'] = $stockLeft;

                $allLowInStockList[] = $lowInStockList;
            }

            $data['allProduct'] = $allProductList;
            $data['totalProductSelling'] = $productSelling;
            $data['outOfStock'] = $allOutOfStockList ? : '-';  
            $data['totalOutOfStock'] = $outOfStock+$outOfStockWithoutTransactionRes;
            $data['lowInStock'] = $allLowInStockList ? : '-';
            $data['totalLowInStock'] = $lowInStock;
                       
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
    }
?>
