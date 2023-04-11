<?php

    /**
     * Cash Class:
     * Used for retrieving and calculating client's credit balance in the system
     */
    
    class Cash{
        
        //Commented on 15/11/2017 - removed last param
        public static $creatorID = 0;
        public static $creatorType = "System";
        public static $paymentCredit;
        public static $mainCredit;
        function __construct() {
            
            
        }
        
        public function setCreator($creatorID, $creatorType) {
            self::$creatorID = $creatorID;
            self::$creatorType = $creatorType;
        }

        public function setPaymentCredit(){
            $db = MysqliDb::getInstance();
            
            $db->orderBy('priority', 'ASC');
            $result = $db->get("credit", NULL , "name,type");
            foreach($result AS $key => $value){
                self::$paymentCredit[$value['name']][] = $value['name']; 
                if($value['name'] != $value['type']){
                    self::$paymentCredit[$value['type']][] = $value['name']; 
                    self::$mainCredit[$value['name']] = $value['type']; 
                }
            }

        }

        public function walletDisplaySetting($clientID, $displayMainWallet){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(!$clientID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Client Not Found", 'data' => "Client Error");
            }

            $isPurchaseCredit  = $db->subQuery();
            $isPurchaseCredit->where('name', "isPurchaseCredit");
            $isPurchaseCredit->where('value', 1);
            $isPurchaseCredit->where('member', 1);
            $isPurchaseCredit->get('credit_setting', null, 'credit_id');
            $db->where('id',$isPurchaseCredit,'IN');
            $isPurchaseCreditAry = $db->getValue('credit','id',null);

            $isWallet  = $db->subQuery();
            $isWallet->where('name', "isWallet");
            $isWallet->where('value', 1);
            $isWallet->where('member', 1);
            $isWallet->get('credit_setting', null, 'credit_id');
            $db->where('b.id', $isWallet, 'IN');
            $db->join('credit_setting a','a.credit_id = b.id');
            $db->orderBy("b.priority", "ASC");
            $result = $db->get('credit b', null, 'b.id, b.translation_code, b.admin_translation_code, b.type, b.code, b.dcm AS `decimal`, b.rate, a.name as setting,a.value,a.member');

            if(empty($result)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Error", 'data' => "");
            }

            $settArr = array("convertTo");
            foreach($result as $value) {
                // unset($row);
                $value[$value['setting']] = $value['member'];
                if(in_array($value["setting"], $settArr)) $value[$value['setting']] = $value['value'];

                if($walletList[$value['type']]){
                    $walletList[$value['type']][$value['setting']] = $value['member'];
                    if(in_array($value["setting"], $settArr)) $walletList[$value['type']][$value['setting']] = $value['value'];
                } else {
                    $walletList[$value['type']] = $value;
                }

                $walletList[$value['type']]["creditDisplay"] = $translations[$value["translation_code"]][$language];
                $walletList[$value['type']]["isPurchaseCredit"] = in_array($value['id'],$isPurchaseCreditAry) ? 1 : 0;
            }

            foreach ($walletList as $index => $row) {
                if($row['isDisplayOnTransaction'] == "1")
                {
                    $db->where('client_id', $clientID);
                    $db->where('type', $row['type']);
                    $transaction = $db->get('credit_transaction', NULL, 'to_id');
                    if(empty($transaction)){
                        unset($walletList[$row["type"]]);
                    }
                }
            }

            return $walletList;
        }

        // isSpecial - for hot deal fresh deal
        public function insertTAccount($accountID, $receiverID, $creditType, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark="",$portfolioID=0, $data , $transactionID,$rate, $isSpecial, $paymentCredit) {
            $db = MysqliDb::getInstance();

            // Check for negative amount
            if ($amount < 0) {
                return false;
            }

            if(!trim($transactionDate)) {
                $transactionDate = date("Y-m-d H:i:s");
            }
            $tblDate = date('Ymd', strtotime($transactionDate));

            $db->where("id", $accountID);
            $mainIDRow = $db->getOne("client", "type, main_id");
            if($mainIDRow["type"] == "Client" && $mainIDRow["main_id"] > 0){
                $accountID = $mainIDRow["main_id"];
            }

            unset($mainIDRow);
            $db->where("id", $receiverID);
            $mainIDRow = $db->getOne("client", "type, main_id");
            if($mainIDRow["type"] == "Client" && $mainIDRow["main_id"] > 0){
                $receiverID = $mainIDRow["main_id"];
            }
            
            unset($mainIDRow);
            $db->where("id", $clientID);
            $mainIDRow = $db->getOne("client", "type, main_id");
            if($mainIDRow["type"] == "Client" && $mainIDRow["main_id"] > 0){
                $clientID = $mainIDRow["main_id"];
            }

            if(!$transactionID) $transactionID = $db->getGroupID();

            if($paymentCredit){
                $paymentCreditType = $paymentCredit;
            }else{
                $credit = self::$paymentCredit; // get all credit
                $paymentCreditType = $credit[$creditType]; // if paymentCreditType is subwallet will get subwallet array else get array of single array of that credit    
            }
            
            $mainCredit = self::$mainCredit;

            if($isSpecial){
                $db->where("name", "isHotDealFreshDeal");
                $db->where("value", "1");
                $creditIDAry = $db->map("credit_id")->get("credit_setting", null, " credit_id");

                $db->where("id", $creditIDAry, "IN");
                $result = $db->get("credit", NULL , "name,type");
                foreach($result AS $key => $value){
                    $paymentCreditTypeTmp[$value['name']][] = $value['name']; 
                    if($value['name'] != $value['type']){
                        $paymentCreditTypeTmp[$value['type']][] = $value['name']; 
                    }
                }

                $paymentCreditType = $paymentCreditTypeTmp[$creditType];
            }

            $isExistSubWallet = 0;
            $subWalletCount = 0;
            $totalSubWallet = count($paymentCreditType);
            if($totalSubWallet > 1) {
                $isExistSubWallet = 1;
            }

            $db->where("name", $paymentCreditType, "IN");
            $creditIDAry = $db->getValue("credit","id", null);
            if($creditIDAry){
                $db->where("credit_id", $creditIDAry, "IN");
                $db->where("name","allowNegativeBalance");
                $db->where("value",1);
                $allowNegativeBalance = $db->getValue("credit_setting", "value");
            }

            if($allowNegativeBalance){
                $shiftedType = array_shift($paymentCreditType);
                $paymentCreditType[] = $shiftedType;
            }

            foreach($paymentCreditType AS $type){
                if($amount <= 0) continue; //if amount less than 0 continue
                
                $subWalletCount++;

                if($accountID > 50){
                    $creditBalance = Self::getBalance($accountID,$type,"",false); // get balance for checking 

                    if($creditBalance < $amount && $isExistSubWallet && ($subWalletCount != $totalSubWallet)) {
                        $calculatedAmount = $creditBalance;
                    }else{
                        $calculatedAmount = $amount;
                    }

                    /*
                    if($creditBalance <= 0) continue; //if no balance continue 
                    $balanceCheck = $amount - $creditBalance; // check balance 
                    $calculatedAmount = ($balanceCheck > 0 ? $creditBalance : $amount); //if got balance use creditBalance else amount
                    */
                }else{
                    $calculatedAmount = $amount;
                }

                $calculatedAmount = Setting::setDecimal($calculatedAmount);
                if($calculatedAmount <= 0) continue; //if no balance continue 

                // $accountID - From
                // $receiverID - To
                // $type - name of currency
                // $amount
                // $subject - transaction subject
                // $belongID - to link to another account besides the credit and debit ID
                // $referenceID - additional ID to keep track when needed.
                // $transactionDate - enter in this format --> date("Y-m-d H:i:s")
                // $batchID - an ID when perform a task so that we can remove or edit in a batch when needed
                // $remark - Remark for credit_transaction
                
                $db->where("type",$type);
                $db->where("name","allowNegativeBalance");
                $allowNegativeBalanceFlag = $db->getValue("credit_setting", "value");

                // $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".$db->escape($tblDate)." LIKE acc_credit");
                
                
                // $decimalPlaces = Setting::getSystemDecimalPlaces();
                
                // Format amount to according to decimal places
                // $calculatedAmount = number_format($calculatedAmount, $decimalPlaces, ".", "");
                // Check whether accountID is an internal account, and what type of account it is
                // Expenses (Allow negative balance)
                // Suspense (Intermediate accounts)
                // Earnings (Always positive balance)
                $db->where('id', $accountID);
                $accountData = $db->getOne("client", "type, description");
                
                // Generate debit/credit ID
                // $debitID = $db->getNewID();
                // $creditID = $db->getNewID();
                
                // Get balance from acc_closing & acc_credit_%
                $accountBalance = Self::getBalance($accountID, $type, "", false);
                
                if($allowNegativeBalanceFlag)
                {
                    $accountBalance = bcsub((string)$accountBalance,(string)$calculatedAmount,"8"); // Debit - minus
                }else{
                    if ($accountData['type'] == "Internal" && in_array($accountData['description'], array("Expenses"))) {
                        // Do nothing here
                    }
                    else {
                        // Check if balance is negative after deducting amount
                        $accountBalance = bcsub((string)$accountBalance,(string)$calculatedAmount,"8"); // Debit - minus
                        if($accountBalance < 0) {
                            return false;
                        }    
                    }
                }
                
                // Set fields for acc_credit table
                $arrayData = array( 
                                    "subject"      => $subject,
                                    "type"         => $type,
                                    "account_id"   => $accountID,
                                    "receiver_id"  => $receiverID,
                                    "credit"       =>  0,
                                    "debit"        => $calculatedAmount,
                                    "balance"      => $accountBalance,
                                    "belong_id"    => $belongID,
                                    "reference_id" => $referenceID,
                                    "batch_id"     => $batchID,
                                    "deleted"      => 0,
                                    "created_at"   => $transactionDate,
                                    "portfolio_id" => $portfolioID,
                                );
                $debitRes = $db->insert("acc_credit_".$tblDate, $arrayData);
                if(!$debitRes)
                    return false;
                
                if ($accountData['type'] == "Client") {
                    // 2nd checking on balance > 0 after insert debit, pass the flag as false so that it won't update the cache balance first
                    $accountBalance = Self::getBalance($accountID, $type, "", false);
                    
                    // Check if balance is negative after deducting amount
                    if($accountBalance < 0 && !$allowNegativeBalanceFlag) {
                        $data2 = array('deleted' => 1);
                        $db->where('id', $debitID);
                        $result = $db->update('acc_credit_'.$tblDate, $data2);
                        return false; // Stop here after updating the debit row in acc_credit
                    }else{
                        // Update cache balance
                        Self::updateClientCacheBalance($accountID,$type,$accountBalance);
                    }
                }

                // Get latest balance and update cache balance
                $receiverBalance = Self::getBalance($receiverID, $type, "", false);

                $receiverBalance = bcadd((string)$receiverBalance,(string)$calculatedAmount,"8"); // Credit - plus
               
                // 1st checking on balance > 0 before insert credit
                //$receiverBalance = Self::getBalance($receiverID, $type);
                //$receiverBalance = $db->escape($receiverBalance);
                //$receiverBalance += $amount; // Credit - plus
                //if($receiverBalance < 0)
                //    return false;
                $arrayData = array( 
                                    "subject"      => $subject,
                                    "type"         => $type,
                                    "account_id"   => $receiverID,
                                    "receiver_id"  => $accountID,
                                    "credit"       => $calculatedAmount,
                                    "debit"        => 0,
                                    "balance"      => $receiverBalance,
                                    "belong_id"    => $belongID,
                                    "reference_id" => $referenceID,
                                    "batch_id"     => $batchID,
                                    "deleted"      => 0,
                                    "created_at"   => $transactionDate,
                                    "portfolio_id" => $portfolioID,
                                );
                $creditRes = $db->insert("acc_credit_".$tblDate, $arrayData);
                if(!$creditRes)
                    return false;

                // Update cache balance
                Self::updateClientCacheBalance($receiverID,$type,$receiverBalance);

                $creditTransactionRes = Self::insertCreditTransaction($accountID, $receiverID, $type, $calculatedAmount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark,$portfolioID, $data, $transactionID,$rate);
                if(!$creditTransactionRes)
                    return false;

                // link transaction & acc_credit
                unset($updateAry);
                $updateAry = array('transaction_id' => $creditTransactionRes);
                $db->where('id', array($creditRes, $debitRes), 'IN');
                $db->update('acc_credit_'.$tblDate, $updateAry);

                $amount = bcsub((string)$amount, (string)$calculatedAmount,"8");
            }

            // Convert Sub Credit to Main Credit
            if($mainCredit[$creditType]) $creditType = $mainCredit[$creditType];

            // Update main credit balance
            if($accountID > 50) Self::getBalance($accountID, $creditType);
            else Self::getBalance($receiverID, $creditType);

            return true;
        }

        public function getBalance($clientID, $type, $date, $updateCache=true, $portfolioID=0) {
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            
            $creditArr = self::$paymentCredit;
            $creditType = $creditArr[$type];

            $db->where("id",$clientID);
            $db->where("type","Client");
            $mainID = $db->getValue("client","main_id");
            if($mainID > 0){
                $clientID = $mainID;
            }

            $realDate = $date;

            if(!strtotime($date)){
                $db->orderBy("created_at", "DESC");
                $date = $db->getValue("credit_transaction", "created_at");
                $realDate = "";
            }

            // Handle for no data in credit transaction
            if(!$date){
                return 0;
            }

            $balance = 0;
            $latestDate = "";
            $tsCondition = strtotime($date);

            $db->where("name","isAccClosingPortfolio");
            $db->where("value",1);
            $isAccClosingPortfolio = $db->getValue("credit_setting","type",null);

            // Get the latest acc closing date
            $db->where("completed","1");
            $db->orderBy("id","DESC");
            $latestAccClosingDate = $db->getValue("acc_closing_batch","closing_date");

            //Get latest closing date from client_setting
            $db->where("client_id",$clientID);
            $db->where("name",$creditType,"IN");
            $db->orderBy("name","ASC");
            $clientStgRes = $db->map("name")->get("client_setting",null,"name,reference");

            $checkSubWallet = 0;

            foreach($clientStgRes as $credit => $closingDate){
                $db->where("client_id",$clientID);
                $db->where("type",$credit);
                $db->orderBy("id","DESC");
                if($closingDate){
                    if($realDate){
                        $db->where("date",date("Y-m-d",strtotime($realDate)),"<");
                    }else{
                        $db->where("date",$closingDate,"<=");
                    }
                    if($portfolioID){
                        $db->where("portfolio_id", $portfolioID);
                    }
                    if(in_array($credit,$isAccClosingPortfolio) && !$portfolioID){
                        // Special handle for closing by portfolio id
                        $db->groupBy("portfolio_id");
                        $accClosingResult = $db->get("acc_closing",null,"MAX(id) as id,portfolio_id");
                        unset($accClosingMaxIDAry);
                        foreach($accClosingResult as $accClosingRow){
                            $accClosingMaxIDAry[$accClosingRow["id"]] = $accClosingRow["id"];
                        }
                        if($accClosingMaxIDAry){
                            $db->where("id",$accClosingMaxIDAry,"IN");
                            $accClosingResult2 = $db->get("acc_closing",null,"MAX(date) as date,SUM(balance) as balance");
                            unset($accClosingRes);
                            foreach($accClosingResult2 as $accClosingRow2){
                                $accClosingRes["date"] = $accClosingRow2["date"];
                                $accClosingRes["balance"] = $accClosingRow2["balance"];
                            }
                        }
                    }else{
                        $accClosingRes = $db->getOne("acc_closing","date,balance");
                    }
                    $balance += $accClosingRes["balance"];
                    if((strtotime($accClosingRes["date"]) > strtotime($realClosingDate))){
                        $checkSubWallet = 1;
                        $realClosingDate = $accClosingRes["date"];
                        $latestDate = date("Y-m-d",strtotime("+1 days ".$accClosingRes["date"]));
                    }
                }else{
                    if($realDate){
                        $db->where("date",date("Y-m-d",strtotime($realDate)),"<");
                    }else{
                        $db->where("date",$latestAccClosingDate,"<");
                    }
                    if($portfolioID){
                        $db->where("portfolio_id",$portfolioID);
                    }
                    $copyDB = $db->copy();
                    $balanceCount = $db->getValue("acc_closing","count(*)");
                    if($balanceCount > 0){
                        $balance += $copyDB->getValue("acc_closing","balance");
                    }else{
                        if(!$checkSubWallet){
                            $db->where("client_id",$clientID);
                            $db->where("type",$credit);
                            $db->orderBy("created_at","ASC");
                            $creditFirstDate = $db->getValue("credit_transaction","created_at");
                            if($creditFirstDate){
                                if(!$latestDate){
                                    $latestDate = $creditFirstDate;
                                }else{
                                    if((strtotime($creditFirstDate) <= strtotime($latestDate))){
                                        $latestDate = $creditFirstDate;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            unset($checkLatestDate);
            $checkLatestDate = date("Y-m-d",strtotime($date));
            if((strtotime($checkLatestDate) == strtotime($realClosingDate))){
                $balance = number_format($balance,$decimalPlaces,".","");
            }else{
                if(!$latestDate && !$realDate){
                    $latestDate = $latestAccClosingDate;
                }elseif(!$latestDate){
                    $latestDate = $realDate;
                }

                $tsLatest = strtotime($latestDate);
                $totalCredit = 0;
                $totalDebit = 0;
                
                $db->where("id",$clientID);
                $registerDate = $db->getValue("client","DATE_FORMAT(created_at,'%Y-%m-%d')");

                /*if($portfolioID){
                    $latestDate = $registerDate;
                }*/

                if(strtotime($latestDate) < strtotime($registerDate)){
                    $latestDate = $registerDate;
                }

                if(strtotime($latestDate) > strtotime($date)){
                    $loopDate = $date;
                    $endDate = $latestDate;
                }else{
                    $loopDate = $latestDate;
                    $endDate = $date;
                }

                // Get all acc_credit_% tables
                while(strtotime($loopDate) <= strtotime($endDate)){
                    $dateCredit = date("Ymd",strtotime($loopDate));

                    $db->where("account_id",$clientID);
                    $db->where("type",$creditType,"IN");
                    $db->where("deleted",0);
                    if($tsCondition){
                        $db->where("created_at", date("Y-m-d H:i:s", $tsCondition), "<=");
                    }
                    if($portfolioID){
                        $db->where("portfolio_id", $portfolioID);
                    }
                    $creditRes = $db->getOne("acc_credit_".$dateCredit, "SUM(credit) AS credit, SUM(debit) AS debit");
                    $totalCredit += $creditRes["credit"];
                    $totalDebit += $creditRes["debit"];

                    $loopDate = date("Y-m-d", strtotime("+1 days ".$loopDate));
                }

                $balance = number_format(($balance + $totalCredit - $totalDebit), $decimalPlaces, ".", "");
            }
   
            if($updateCache && !$realDate) {
                // Update cache balance for the clientID
                self::updateClientCacheBalance($clientID, $type, $balance);
            }
            
            return $balance;
        }

        // Get balance from acc_closing & acc_credit_%
        public function getBalanceOld($clientID, $type, $date='', $updateCache=true,$portfolioID=0) {
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getSystemDecimalPlaces();
                
            if(is_array($type)){
                $creditType = $type;
            }
            else{
                $creditArr = self::$paymentCredit;
                $creditType = $creditArr[$type];
            }

            $db->where("id", $clientID);
            $db->where("type","Client");
            $mainID = $db->getValue("client", "main_id");
            if($mainID > 0){
                $clientID = $mainID;
            }

            $db->where('client_id', $clientID);
            $db->where('type', $creditType , "IN");
            
            if ($date) {
                // If date is passed in as argument, we only want to select the range up till the given date
                $db->where('date', $date, '<=');
                $tsCondition = strtotime($date);
            }
            $count = $db->getValue('acc_closing', 'count(id)');
            
            // 0 means no rows exist in the acc_closing for this client
            if($count == 0) {
                $balance = 0;
                $latestDate = '';
            }
            else {
                
                // Get the latest acc_closing date for this client
                if($portfolioID) $db->where('portfolio_id',$portfolioID);

                $db->where('client_id', $clientID);
                $db->where('type', $creditType , "IN");

                $db->groupBy("date");
                // $accClosingResults = $db->getOne('acc_closing', null, 'balance, date');
                $accClosingResults = $db->getOne('acc_closing','SUM(balance) AS balance, date');

                $latestDate = $accClosingResults["date"];
                $balance = $accClosingResults["balance"];
                
            }
            
            if($date && $latestDate >= $date){
                $balance = 0;
                $latestDate = '';
            }

            $tsLatest = strtotime($latestDate);
            $totalCredit = 0;
            $totalDebit = 0;
            
            $db->where("id", $clientID);
            $registerDate = $db->getValue('client', "DATE_FORMAT(created_at, '%Y-%m-%d')");

            // Get all acc_credit_% tables
            $result = $db->rawQuery('SHOW TABLES LIKE "acc_credit_%"');
            foreach ($result as $array) {
                foreach ($array as $key=>$val) {
                    $val = explode('_', $val);
                    $dateCredit = $val[2];
                    $tsCredit = strtotime($dateCredit);
                    if($tsCredit < strtotime($registerDate)) continue;
                    // Compare the date with the latest acc_closing date
                    // For eg. there exist tables
                    // acc_credit_20170801, acc_credit_20170802, acc_credit_20170803, acc_credit_20170804
                    // Condition 1: If acc_closing on 20170802,
                    // This 'if' part will sum up acc_credit_20170803 and acc_credit_20170804 debit & credit
                    // Condition 2: If acc_closing on 20170804,
                    // This 'if' part won't run
                    if($tsCredit > $tsLatest) {
                        if ($tsCondition && $tsCredit > $tsCondition) {
                            // If it exceeds the time of the date argument, breka from the loop
                            break;
                        }
                        if($portfolioID) $db->where('portfolio_id',$portfolioID);

                        $db->where('account_id', $clientID);
                        if($tsCondition) $db->where('created_at', date("Y-m-d H:i:s", $tsCondition), '<=');
                        $db->where('type', $creditType , "IN");

                        $db->where('deleted', 0);
                        $creditRes = $db->getOne('acc_credit_'.$dateCredit, 'SUM(credit) AS credit, SUM(debit) AS debit');
                        $totalCredit += $creditRes['credit'];
                        $totalDebit += $creditRes['debit'];
                    }
                }
            }

            // this part to fix -0.00 error
            // $totalCredit = number_format($totalCredit, $decimalPlaces, '.', '');
            // $totalDebit = number_format($totalDebit, $decimalPlaces, '.', '');
            
            $balance = number_format(($balance + $totalCredit - $totalDebit), $decimalPlaces, '.', '');
   
            if ($updateCache && !$date) {
                // Update cache balance for the clientID
                self::updateClientCacheBalance($clientID, $type, $balance);
            }
            
            return $balance;
        }
        
        // Get balance from acc_closing & acc_credit_%
        public function getAllClientBalanceOld($clientIDAry, $typeAry, $date, $groupByPortfolio) {
            $db = MysqliDb::getInstance();
            
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            $totalCredit = $totalDebit =0;

            if($date) $tsCondition = strtotime($date);
            else $tsCondition = TIME();

            // Get all acc_credit_% tables
            $result = $db->rawQuery('SHOW TABLES LIKE "acc_credit_%"');
            foreach ($result as $array) {
                foreach ($array as $key=>$val) {
                    $val = explode('_', $val);
                    $dateCredit = $val[2];
                    $tsCredit = strtotime($dateCredit);

                    if ($tsCondition && $tsCredit > $tsCondition) {
                        // If it exceeds the time of the date argument, break from the loop
                        break;
                    }

                    if($clientIDAry) $db->where('account_id', $clientIDAry, "IN");
                    else $db->where('account_id', 50, ">");
                    if($tsCondition) $db->where('created_at', date("Y-m-d H:i:s", $tsCondition), '<=');
                    if($typeAry) $db->where('type', $typeAry, "IN");
                    $db->where('deleted', 0);
                    $db->groupBy('account_id');
                    $db->groupBy('type');
                    if($groupByPortfolio) $db->groupBy("portfolio_id");
                    $balanceRes = $db->get('acc_credit_'.$dateCredit, null,'SUM(credit - debit) AS balance, account_id, type, portfolio_id');
                    foreach($balanceRes AS $balanceRow){
                        if($balanceRow['type'] == 'maxCap' && $groupByPortfolio){
                            $clientBalanceAry[$balanceRow['account_id']][$balanceRow['type']][$balanceRow['portfolio_id']] = bcadd($clientBalanceAry[$balanceRow['account_id']][$balanceRow['type']][$balanceRow['portfolio_id']], $balanceRow['balance'], $decimalPlaces); 
                        }else{
                            $clientBalanceAry[$balanceRow['account_id']][$balanceRow['type']] = bcadd($clientBalanceAry[$balanceRow['account_id']][$balanceRow['type']], $balanceRow['balance'], $decimalPlaces);   
                        }
                    }
                }
            }
            return $clientBalanceAry;
        }

        public function getAllClientBalance($clientIDAry, $typeAry, $date, $groupByPortfolio) {
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getSystemDecimalPlaces();

            if(!$clientIDAry){
                $db->where("type","Client");
                $db->where("main_id","0");
                $clientIDAry = $db->getValue("client","id",null);
            }

            if(!strtotime($date)){
                $db->orderBy("created_at","DESC");
                $date = $db->getValue("credit_transaction","created_at");
                $tsCondition = strtotime($date);
            }else{
                $tsCondition = strtotime($date);
            }

            $db->where("completed","1");
            $db->orderBy("id","DESC");
            $latestAccClosingDate = $db->getValue("acc_closing_batch","closing_date");

            if($latestAccClosingDate){
                if($clientIDAry){
                    $db->where("client_id",$clientIDAry,"IN");
                }
                if($typeAry){
                    $db->where("type",$typeAry,"IN");
                }
                if($tsCondition){
                    $db->where("date",date("Y-m-d",$tsCondition),"<");
                }
                $db->where("date",$latestAccClosingDate,"<=");
                $db->groupBy("client_id");
                $db->groupBy("date");
                $db->groupBy("type");
                $db->orderBy("date","ASC");
                $accClosingResults = $db->get("acc_closing",null,"client_id,type,SUM(balance) as balance,date");
                foreach($accClosingResults as $accClosingRow){
                    $clientBalanceAry[$accClosingRow["client_id"]][$accClosingRow["type"]] = $accClosingRow["balance"];
                }
                $latestDate = date("Y-m-d", strtotime("+1 days ".$latestAccClosingDate));
                $tsLatest = strtotime($latestDate);
            }
            
            if(!$latestDate){
                $db->orderBy("created_at","ASC");
                $latestDate = $db->getValue("credit_transaction","created_at");
            }
            
            if(strtotime($latestDate) > strtotime($date)){
                $loopDate = $date;
                $endDate = $latestDate;
            }else{
                $loopDate = $latestDate;
                $endDate = $date;
            }

            while(strtotime($loopDate) <= strtotime($endDate)){
                $dateCredit = date("Ymd",strtotime($loopDate));

                if($clientIDAry){
                    $db->where("account_id",$clientIDAry,"IN");
                }
                if($typeAry){
                    $db->where("type",$typeAry,"IN");
                }
                if($tsCondition){
                    $db->where("created_at",date("Y-m-d H:i:s",$tsCondition),"<=");
                }
                $db->where("deleted",0);
                $db->groupBy("account_id");
                $db->groupBy("type");
                if($groupByPortfolio){
                    $db->groupBy("portfolio_id");
                }
                $balanceRes = $db->get("acc_credit_".$dateCredit,null,"SUM(credit - debit) AS balance,account_id,type,portfolio_id");
                foreach($balanceRes AS $balanceRow){
                    if(($balanceRow["type"] == "maxCap" && $groupByPortfolio) || ($balanceRow["type"] == "capitalDef" && $groupByPortfolio)){
                        $clientBalanceAry[$balanceRow["account_id"]][$balanceRow["type"]][$balanceRow["portfolio_id"]] = bcadd($clientBalanceAry[$balanceRow["account_id"]][$balanceRow["type"]][$balanceRow["portfolio_id"]],$balanceRow["balance"],$decimalPlaces); 
                    }else{
                        $clientBalanceAry[$balanceRow["account_id"]][$balanceRow["type"]] = bcadd($clientBalanceAry[$balanceRow["account_id"]][$balanceRow["type"]],$balanceRow["balance"],$decimalPlaces);   
                    }
                }

                $loopDate = date("Y-m-d",strtotime("+1 days ".$loopDate));
            }

            return $clientBalanceAry;
        }

        public function getClientCacheBalance($clientID, $creditType) {
            $db = MysqliDb::getInstance();
            
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            
            $db->where('client_id', $clientID);
            $db->where('name', $creditType);
            $db->where('type', 'Credit Balance');
            $result = $db->getOne('client_setting', 'value');
            
            return $result['value']? number_format($result['value'], $decimalPlaces, '.', '') : 0;
        }
        
        public function updateClientCacheBalance($clientID, $creditType, $balance) {
            $db = MysqliDb::getInstance();
            
            $db->where('client_id', $clientID);
            $db->where('name', $creditType);
            $db->where('type', 'Credit Balance');
            $count = $db->getValue("client_setting", "count(client_id)");
            
            if ($count == 0) {
                // Insert new record
                $fields = array('name', 'value', 'type', 'reference', 'client_id');
                $values = array($creditType, $balance, 'Credit Balance', '', $clientID);
                //$values = array($rowID, $creditType, $balance, 'Credit Balance', '', $clientID);
                $arrayData = array_combine($fields, $values);
                $db->insert("client_setting", $arrayData);
            }
            else {
                $data = array('value' => $balance);
                $db->where('client_id', $clientID);
                $db->where('name', $creditType);
                $db->where('type', 'Credit Balance');
                $db->update("client_setting", $data);
            }
        }
        
        /**
         * Accountings closing function
         * Used for calculating the total day's balance for each client and carry forward to the next date
         */
        public function closing($closingDate) {
            $db = MysqliDb::getInstance();
            // Message:: = Self::message;
            
            Log::write(date("Y-m-d H:i:s")." Deleting closing date $closingDate onwards.\n");
            
            if (Self::deleteClosing($closingDate)) {
                Log::write(date("Y-m-d H:i:s")." Successfully deleted closing from $closingDate onwards.\n");
            }
            
            // Convert to timestamp for comparison
            $closingTimestamp = strtotime($closingDate);
            
            // Select all client accounts and internal accounts
            $clientFields = array('id', 'username', 'DATE(created_at) AS created_at', 'description');
            $clientRes = $db->get('client', null, $clientFields);
            
            foreach ($clientRes as $clientRow) {
                if ($clientRow["description"] == "Expenses") {
                    // Expenses accounts means they will always be negative balance
                    $expensesArray[] = $clientRow["id"];
                }
                $clientArray[] = $clientRow;
            }
            unset($clientRes);
            //print_r($clientArray);
            
            // Select all existing currencies
            $creditRes = $db->get('credit', null, array('name'));
            foreach ($creditRes as $creditRow) {
                
                $creditType = $creditRow["name"];
                
                Log::write(date("Y-m-d H:i:s")." Closing $creditType now.\n");
                
                foreach ($clientArray as $clientRow) {
                    
                    $db->where('client_id', $clientRow["id"]);
                    $db->where('`type`', $creditType);
                    $db->orderBy('`date`', "DESC");
                    $accClosingResults = $db->getOne('acc_closing');
                    
                    $lastClosingDate = $accClosingResults["date"];
                    $lastBalance = $accClosingResults["balance"]? $accClosingResults["balance"] : 0;
                    
                    //echo "Last closing date from DB: $lastClosingDate [".$clientRow["id"]."]\n";
                    
                    if ($lastClosingDate) {
                        // Increment by 1 day from the last closing date
                        $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                    }
                    else {
                        // Set to client joined date if did not perform closing previously
                        $lastClosingDate = $clientRow["created_at"];
                    }
                    
                    Log::write(date("Y-m-d H:i:s")." Last closing date for client ".$clientRow["username"]." is $lastClosingDate.\n");
                    
                    // Convert to timestamp for comparison
                    $lastClosingTimestamp = strtotime($lastClosingDate);
                    
                    while ($lastClosingTimestamp <= $closingTimestamp) {
                        
                        $lastBalance = Self::closeClientAccount($clientRow["id"], $clientRow["username"], $lastClosingDate, $lastBalance, $creditType);
                        
                        // Increment by 1 day for next iteration
                        $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                        $lastClosingTimestamp = strtotime($lastClosingDate);
                        
                    }
                    
                    // Update client's latest cache balance for current currency
                    $balance = Self::getBalance($clientRow["id"], $creditType);
                    
                    Log::write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$clientRow["username"]."[".$clientRow["id"]."]. Balance: ".$balance."\n");
                    
                }
                
                // Audit the credit type (total issued - total spending = balance on all accounts)
                $db->where('client_id', $expensesArray, 'IN');
                $db->where('type', $creditType);
                $db->where('date', $closingDate);
                $expensesBalance = $db->getValue('acc_closing', 'SUM(balance)');
                
                $db->where('client_id', $expensesArray, 'NOT IN');
                $db->where('type', $creditType);
                $db->where('date', $closingDate);
                $incomeBalance = $db->getValue('acc_closing', 'SUM(balance)');
                
                $companyBalance = $incomeBalance + $expensesBalance;
                
                Log::write(date("Y-m-d H:i:s")." Finish closing for $creditType. Total issued: $incomeBalance + Total spending: $expensesBalance = $companyBalance\n");
                
                if ($companyBalance != 0) {
                    // If company balance is less than 0, means there might be a problem
                    $notTallyArray[] = $creditType." balance is not tally. Amount: $companyBalance\n";
                }
                
            }
            
            if (count($notTallyArray) > 0) {
                $content = "Closing result on $closingDate\n\n";
                $content .= implode("\n\n", $notTallyArray);
                // 10005 => balance not tally
                Message::createMessageOut(10005, $content);
            }
        }
        
        private function closeClientAccount($clientID, $clientUsername, $closingDate, $previousBalance=0, $creditType) {
            $db = MysqliDb::getInstance();
            
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            
            // Create the acc_credit daily table if not exists
            $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".date("Ymd", strtotime($closingDate))." LIKE acc_credit");
            
            $db->where('account_id', $clientID);
            $db->where('type', $creditType);
            $db->where('deleted', 0);
            $accRes = $db->getOne('acc_credit_'.date("Ymd", strtotime($closingDate)), 'SUM(debit) AS debit, SUM(credit) AS credit');
            
            Log::write(date("Y-m-d H:i:s")." Last query: ".$db->getLastQuery()."\n");
            
            $credit = $accRes["credit"]? $accRes["credit"] : 0;
            $debit = $accRes["debit"]? $accRes["debit"] : 0;
            $total = number_format(($credit - $debit), $decimalPlaces, ".", "");
            $balance = number_format(($previousBalance + $total), $decimalPlaces, ".", "");
            
            Log::write(date("Y-m-d H:i:s")." PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");
            
            // Insert client's closing record into acc_closing
            $fields = array("id", "client_id", "type", "date", "total", "balance", "created_at");
            $values = array($db->getNewID(), $clientID, $creditType, $closingDate, $total, $balance, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            $db->insert('acc_closing', $arrayData);
            
            return $balance; // Return the latest balance
        }

        public function deleteClosing($closingDate) {
            $db = MysqliDb::getInstance();
            
            $db->where('date', $closingDate, " >= ");
            $db->delete('acc_closing');
            // Optmize the table after deletion
            $db->optimize('acc_closing');
        }


        public function memberPaymentTransaction($params,$clientID){
            $db = MysqliDb::getInstance();

            $downlineID = trim($params["downlineID"]);
            $amount = trim($params["amount"]);
            $paymentType = trim($params["paymentType"]);
            $creditType = trim($params["creditType"]);

            if(strlen($creditType) == 0)
                $creditType = "cash";

            if(empty($downlineID))
                return array("status"=>"error","code"=>"1","statusMsg"=>"Downline id is empty.","data"=>"");

            if(empty($amount))
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Please enter amount.","data"=>"");
            
            if(!is_numeric($amount))
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Please enter a valid amount.","data"=>"");

            if(empty($paymentType))
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Please select your payment option.","data"=>"");

            if($paymentType != "pay" && $paymentType != "receive")
                return array("status"=>"ok","code"=>"1","statusMsg"=>"Invalid payment option.","data"=>"");

            //get downlineName
            $db->where("id",$downlineID);
            $downline = $db->get("client",1,"name");
            if(!empty($downline))
                $downlineName = $downline[0]["name"];

            $db->where("type","Internal");
            $db->where("name","payout");
            $payoutRes = $db->get("client",1,"id");
            if(!empty($payoutRes))
                $payoutID = $payoutRes[0]["id"];

            $fields = array("id","subject","type","from_id","to_id","client_id","amount","remark","belong_id","reference_id","batch_id","deleted","creator_id","creator_type","created_at");
            if($paymentType == "pay")
            {
                $belong = $db->getNewID();
                // insert upline pay to downline
                // Self::insertTAccount($clientID,$payoutID,$creditType,$amount,"Payout to downline",$belong,"",date("Y-m-d H:i:s"),$belong,$clientID);
                // insert downline receive payment from upline
                Self::insertTAccount($payoutID,$downlineID,$creditType,$amount,"Receive payment from upline",$belong,"",date("Y-m-d H:i:s"),$belong,$downlineID);
            }
            else if($paymentType == "receive")
            {
                $belong = $db->getNewID();
                // receive payment from downline
                Self::insertTAccount($downlineID,$payoutID,$creditType,$amount,"Payout to upline",$belong,"",date("Y-m-d H:i:s"),$belong,$downlineID);
                // downline pay to upline
                // Self::insertTAccount($payoutID,$clientID,$creditType,$amount,"Receive payment from downline",$belong,"",date("Y-m-d H:i:s"),$belong,$clientID);
            }

            //get client balance
            $balance = Self::getClientCacheBalance($downlineID,$creditType);

            $db->where("deleted","0");
            $db->where("client_id",$downlineID);
            // $db->where ("(from_id = ? or to_id = ?)", Array($clientID,$clientID));
            $getRes = $db->get("credit_transaction",null,"id,created_at,subject,amount");

            if(!empty($getRes))
            {
                foreach($getRes as $value)
                {
                   
                    if($value["subject"] == "Receive payment from upline" || $value["subject"] == "Payout to upline")
                    {
                        $id[] = $value["id"];
                        $transDate[] = $value["created_at"];

                        $tempSub = $value["subject"];
                        if($tempSub == "Payout to upline")
                        {
                            $subject[] = "Receive payment from $downlineName";
                        }
                        else{
                            $subject[] = "Payout to $downlineName";
                        }

                        $transAmount[] = $value["amount"];
                    }
                }
                $output["id"] = $id;
                $output["date"] = $transDate;
                $output["subject"] = $subject;
                $output["payout"] = $transAmount;
                $data["paymentList"] = $output;
                $data["balance"] = $balance;
            }
            else{
                return array("status"=>"error","code"=>1,"statusMsg"=>"No payment found.","data"=>"");
            }

            return array("status"=>"ok", "code"=>"0","statusMsg"=>"Add Payment successfull.","data"=>$data);
        }

        private function insertCreditTransaction($accountID, $receiverID, $type, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark,$portfolioID=0, $data, $transactionID,$rate){
            $db = MysqliDb::getInstance();
            
            $creatorID = self::$creatorID;
            $creatorType = self::$creatorType;

            $fields = array("subject", "type", "from_id", "to_id", "client_id", "amount", "remark", "belong_id", "reference_id", "batch_id", "deleted", "creator_id", "creator_type", "created_at","portfolio_id", "data", "group_id","coin_rate");

            // $id = $db->getNewID();
            $values = array($subject, $type, $accountID, $receiverID, $clientID, $amount, $remark, $belongID, $referenceID, $batchID, "0", $creatorID, $creatorType, $transactionDate,$portfolioID, $data, $transactionID,$rate);

            $arrayData = array_combine($fields,$values);
            
            $result = $db->insert("credit_transaction",$arrayData);
            if($result) return $result;

            return false;
        }

        function insertRedeemPoints($params) {
            
            $db = MysqliDb::getInstance();

            $clientID=$params['clientID'];
            $bonusValue=$params['bonusValue'];
            $belongID=$params['belongID'];
            $batchID=$params['batchID'];
            $portfolioID=$params['portfolioID'];

            $db->where('name', 'redeemPoints');
            $db->where('type', 'Internal');
            $redeemPointsID = $db->getValue('client', 'id');

            $promoCreditArray=array('redeem1Credit','redeem2Credit');
            $db->where('credit.name', $promoCreditArray,'IN');
            $db->join('credit_setting', "credit.id = credit_setting.credit_id");
            $db->where('credit_setting.name', 'credit2Points');

            //maps creditName to redeemPoints amount
            $creditSettingAry = $db->map('creditName')->arrayBuilder()->get("credit", null, "`credit`.`name` AS creditName,`credit_setting`.`value`");
            
            $subject="Redeem Point";
            $convertSubject="Convert Point";
            $prizeCredit="pointCredit";
            /*
            redeem1Credit for self
            redeem2Credit for direct upline
            */

            // $db->where('client_id',$clientID);
            // $uplineID=$db->getValue('tree_sponsor','upline_id');

            $creditType='redeem1Credit';
            Self::insertTAccount($redeemPointsID, $clientID, $creditType, $bonusValue, $subject, $belongID, "", $db->now(), $batchID, $clientID, "Investment",$portfolioID);
            // $clientBalance=Self::getBalance($clientID,$creditType);

            // $convertedPoints=floor($clientBalance/$creditSettingAry[$creditType]);

            // if ($convertedPoints){
            //     Self::insertTAccount($clientID, $redeemPointsID, $creditType, $convertedPoints*$creditSettingAry[$creditType], $convertSubject, $belongID, "", $db->now(), $batchID, $clientID, "Investment");
            //     Self::insertTAccount($redeemPointsID, $clientID, $prizeCredit, $convertedPoints, $convertSubject, $belongID, "", $db->now(), $batchID, $clientID, "Investment");
            // }

            // if($uplineID){
                
            //     $creditType='redeem2Credit';

            //     Self::insertTAccount($redeemPointsID, $uplineID, $creditType, $bonusValue, $subject, $belongID, "", $db->now(), $batchID, $clientID, "Sponsor");
            //     // $uplineBalance=Self::getBalance($uplineID,$creditType);

            //     // $convertedPoints=floor($uplineBalance/$creditSettingAry[$creditType]);

            //     // if ($convertedPoints){
            //     //     Self::insertTAccount($uplineID, $redeemPointsID, $creditType, $convertedPoints*$creditSettingAry[$creditType], $convertSubject, $belongID, "", $db->now(), $batchID, $uplineID,"Sponsor");
            //     //     Self::insertTAccount($redeemPointsID, $uplineID, $prizeCredit, $convertedPoints, $convertSubject, $belongID, "", $db->now(), $batchID, $uplineID,"Sponsor");
                    
            //     // }
            // }
        }

        // isSpecial - for hot deal freah deal
        function getPaymentDetail($clientID, $registerType, $price, $productID, $dateTime, $isSet, $type, $isSpecial){          
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getInternalDecimalFormat();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(!$dateTime) $dateTime = date("Y-m-d H:i:s");

            if(!$isSpecial){
                $walletList = Self::walletDisplaySetting($clientID);
                foreach ($walletList as $creditType => $walletData) {
                    $validCreditType[$creditType] = $creditType;
                    $creditDisplay[$creditType] = $walletData["translation_code"];
                }    
            } else {
                
                $db->where("name", "isHotDealFreshDeal");
                $db->where("value", "1");
                $creditIDAry = $db->map("credit_id")->get("credit_setting", null, " credit_id");

                $db->where("id", $creditIDAry, "IN");
                $walletList = $db->map("name")->get("credit", null, "name, type, translation_code");

                foreach ($walletList as $creditType => $walletData) {
                    $validCreditType[$walletData['type']][] = $creditType;
                    $creditDisplay[$walletData['type']] = $walletData["translation_code"];
                }
            }

            $db->where("product_id", $productID);
            $db->where("type","Credit Setting");
            $productRes = $db->get("mlm_product_setting", null, "name, value, reference");
            foreach($productRes as $productRow){
                $chargesRateAry[$productRow["name"]] = $productRow;
            }
            $db->where("status","Active");
            $db->where("payment_type",$registerType);
            $res = $db->get("mlm_payment_method", null, "credit_type AS creditType,min_percentage AS minPercentage,max_percentage AS maxPercentage, group_type AS groupType");
            foreach($res AS $row){

                if($validCreditType[$row["creditType"]] || $registerType == "Bonus Package Reentry"){

                    $row['creditDisplay'] = $translations[$creditDisplay[$row['creditType']]][$language];

                    if(is_array($validCreditType[$row["creditType"]])){
                        foreach ($validCreditType[$row["creditType"]] as $value) {
                            $row['balance'] += self::getBalance($clientID,$value);
                        }
                    }else{
                        $row['balance'] = self::getBalance($clientID,$row['creditType']);
                    }
                    $row['minPrice'] = number_format($row['minPercentage'] * $price / 100, $decimalPlaces, ".", "");
                    $row['maxPrice'] = number_format($row['maxPercentage'] * $price / 100, $decimalPlaces, ".", "");
                    
                    if($chargesRateAry[$row["creditType"]]["value"] && $chargesRateAry[$row["creditType"]]["value"] < 1){
                        //lock coin rate
                        if($isSet){
                            $coinRate = self::updateLockCoinRate($clientID, $row["creditType"],$type);
                        }else{
                            $coinRate = self::getLockCoinRate($clientID, $row["creditType"],$type);
                        }

                        $row["rate"] = Setting::setDecimal(($coinRate * $chargesRateAry[$row["creditType"]]["value"]),$row["creditType"]);

                    }else{
                        $row["rate"] = $chargesRateAry[$row["creditType"]]["value"] ? $chargesRateAry[$row["creditType"]]["value"] : 1;
                    }

                    $row["formula"] = $chargesRateAry[$row["creditType"]]["reference"] ? $chargesRateAry[$row["creditType"]]["reference"] : "multiply" ;
                    if(!$row['groupType']){
                        $paymentData[$row['creditType']] = $row;
                    }else{
                        $paymentData[$row['groupType']][$row['creditType']] = $row;
                    } 
                }
            }
            $data['paymentData'] = $paymentData;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function paymentVerification($clientID,$registerType,$paymentDetail,$productID,$price,$dateTime, $type, $isSpecial){
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getInternalDecimalFormat();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $check = self::getPaymentDetail($clientID, $registerType, $price, $productID, $dateTime,"", $type, $isSpecial);
            if($check['status'] != "ok") return $check;
            $paymentData = $check['data']["paymentData"];
            // if(!$paymentDetail) return array('status' => "error", 'code' => 1,  'statusMsg' => "paymentDetail not found", 'data' => "");

            $totalPrice = $price;
            $percentage = 0;
            $totalSpend = 0;

            foreach($paymentData as $creditType => $val){ 
                $amount = $paymentDetail[$creditType]['amount'];
                if(!is_numeric($amount) && $amount != 0 ){
                    $errorFieldArr[] = array(
                                                    // 'id' => $creditType."Error",
                                                    'id' => "totalAmountError",
                                                    'msg' => "Amount number only.",
                                                );
                }

                if($val['maxPrice'] > 0 && $amount > $val['maxPrice']){
                    $errorMsg = str_replace("%%wallet%%", $translations[$val["creditDisplay"]][$language], $translations["E00508"][$language]);
                    $errorFieldArr[] = array(
                                                    // 'id' => $creditType."Error",
                                                    'id' => "totalAmountError",
                                                    'msg' => $errorMsg,
                                                );
                }

                if($val['minPrice'] > 0 && $amount < $val['minPrice']){
                    $errorMsg = str_replace("%%wallet%%", $translations[$val["creditDisplay"]][$language], $translations["E00507"][$language]);
                    $errorFieldArr[] = array(
                                                    // 'id' => $creditType."Error",
                                                    'id' => "totalAmountError",
                                                    'msg' => $errorMsg,
                                                );
                }

                if($val["formula"] == "divide"){
                    $payableAmount = Setting::setDecimal(($amount / $val["rate"]),$creditType);
                }else if($val["formula"] == "multiply"){
                    $payableAmount = Setting::setDecimal(($amount * $val["rate"]),$creditType);
                }else{
                    $payableAmount = $amount;
                }

                if($payableAmount > $val['balance']){
                    $errorFieldArr[] = array(
                                                    // 'id' => $creditType."Error",
                                                    'id' => "totalAmountError",
                                                    'msg' => $translations["E00266"][$language],
                                                );
                }
                if($amount > 0){
                    $invoiceSpendData[$creditType]["display"] = $val["creditDisplay"];
                    $invoiceSpendData[$creditType]["amount"] = $amount;
                    $invoiceSpendData[$creditType]["rate"] = $val["rate"];
                    $invoiceSpendData[$creditType]["paymentAmount"] = $payableAmount;
                }

                $totalSpend += $amount;
            }
            if($totalPrice > $totalSpend){
                $errorFieldArr[] = array(
                                                    'id' => "totalAmountError",
                                                    'msg' => $translations["E00824"][$language],
                                                );
            }

            if($totalPrice != $totalSpend){
                $errorFieldArr[] = array(
                                                    'id' => "totalAmountError",
                                                    'msg' => $translations["E00410"][$language],
                                                );
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            $data["invoiceSpendData"] = $invoiceSpendData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);           
        }

        // isSpecial - for hot deal freah deal
        function paymentConfirmation($clientID,$paymentType,$paymentDetail,$productID,$portfolioID,$price,$dateTime, $batchID,$belongID, $isSpecial){
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getInternalDecimalFormat();

            if(!$dateTime) $dateTime = date("Y-m-d H:i:s");
            /*$check = Self::paymentVerification($clientID,$paymentType,$paymentDetail,$productID,$price,$dateTime);
            if($check['status'] != "ok") return $check;*/

            $db->where("username","creditSales");
            $internalID = $db->getValue("client","id");

            if(!$belongID) $belongID = $db->getNewID();
            $subject = $paymentType;

            foreach($paymentDetail AS $creditType => $val){ 
                $amount = $val['paymentAmount'];
                if($amount > 0){
                    Self::insertTAccount($clientID, $internalID, $creditType, $amount, $subject, $belongID, '', $dateTime, $batchID, $clientID, $remark,$portfolioID, '', $transactionID, $val["rate"], $isSpecial);
                }
            }

            return true;
        }

        function updateLockCoinRate($clientID, $creditType, $type){
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getInternalDecimalFormat();

            if(!$clientID || !$creditType || !$type){
                return false;
            }
            $db->where("client_id",$clientID);
            $db->where("coin_type",$creditType);
            $db->where("type",$type);
            $lockID = $db->getValue("mlm_lock_coin_rate","id");

            $db->where("type",$creditType);
            $db->orderBy("id","DESC");
            $coinRate = $db->getValue("mlm_coin_rate","rate");
            $coinRate = $coinRate > 0 ? $coinRate : 1;
            if(!$lockID){
                $insertData = array(
                                        "client_id" => $clientID,
                                        "coin_type" => $creditType,
                                        "type" => $type,
                                        "rate" => $coinRate,
                                        "created_on" => date("Y-m-d H:i:s"),
                                    );
                $db->insert("mlm_lock_coin_rate", $insertData);
            }else{
                $updateData = array(
                                        "rate" => $coinRate,
                                    );
                $db->where("id", $lockID);
                $db->update("mlm_lock_coin_rate", $updateData);
            }

            return $coinRate;
        }

        function getLockCoinRate($clientID, $creditType, $type){
            $db = MysqliDb::getInstance();
            $decimalPlaces = Setting::getInternalDecimalFormat();

            if(!$clientID || !$creditType || !$type){
                return false;
            }

            $db->where("client_id",$clientID);
            $db->where("coin_type",$creditType);
            $db->where("type",$type);
            $coinRate = $db->getValue("mlm_lock_coin_rate","rate");

            return $coinRate;
        }

        function checkCreditLimit($clientID,$creditType,$amount){
        	$db = MysqliDb::getInstance();
        		
        	$sq = $db->subQuery();
        	$sq->where("name",$creditType);
        	$sq->getOne("credit","id");
        	$db->where("credit_id",$sq);
        	$db->where("name","convertCap");
        	$maxCap = $db->getValue("credit_setting","value");

        	$db->where("name","convertCap");
			$db->where("client_id",$clientID);
			$db->where("type",$creditType);
			$personalCap = $db->getValue("client_setting","value");
			
			if($personalCap > 0) $maxCap = $personalCap;

        	if($maxCap <= 0) return $amount;

        	$balance = Cash::getBalance($clientID,$creditType);
        	$total += $balance;

        	$db->where("client_id",$clientID);
        	$db->where("credit_type",$creditType);
        	$db->where("status","Completed","!=");
        	$res = $db->get("trd_transaction",NULL,"type,total_amount,actual_amount,admin_charge");
        	foreach($res AS $row){
        		$total += ($row['total_amount'] - $row['actual_amount']);
        		if($row['type'] == "buy") $total += $row['admin_charge'];
        	}	

        	$remainAmount = $maxCap - $total;

        	if($remainAmount >= $amount ){
        		return $amount;
        	}else{
        		if($remainAmount <= 0 ) return 0;
        		return $remainAmount;
        	}
        }
	}

?>
