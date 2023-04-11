<?php

    /**
     * Cash Class:
     * Used for retrieving and calculating client's credit balance in the system
     */
    
    class Cash
    {
        
        //Commented on 15/11/2017 - removed last param
        //function __construct($db, $setting, $message, $provider, $log) {
        function __construct($db, $setting, $message, $provider, $log, $general, $client) {
            $this->db = $db;
            $this->setting = $setting;
            $this->message = $message;
            $this->provider = $provider;
            $this->log = $log;
            $this->general = $general;
            $this->client = $client;
            
            $this->creatorID = "";
            $this->creatorType = "";
        }
        
        public function setCreator($creatorID, $creatorType) {
            $this->creatorID = $creatorID;
            $this->creatorType = $creatorType;
        }
        
        public function insertTAccount($accountID, $receiverID, $creditType, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark="",$portfolioID=0, $data , $transactionID,$rate) {
            $db = $this->db;
            $setting = $this->setting;

            // Check for negative amount
            if ($amount < 0) {
                return false;
            }

            if(!trim($transactionDate)) {
                $transactionDate = date("Y-m-d H:i:s");
            }
            $tblDate = date('Ymd', strtotime($transactionDate));

            if(!$transactionID) $transactionID = $db->getTransactionID();
            $credit = $this->getPaymentCredit(); // get all credit
            $paymentCreditType = $credit[$creditType]; // if paymentCreditType is subwallet will get subwallet array else get array of single array of that credit

            foreach($paymentCreditType AS $type){
                if($amount <= 0) continue; //if amount less than 0 continue
                
                if($accountID > 50){
                    $creditBalance = $this->getBalance($accountID,$type); // get balance for checking 
                    if($creditBalance <= 0) continue; //if no balance continue 
                    $balanceCheck = $amount - $creditBalance; // check balance 
                    $calculatedAmount = ($balanceCheck > 0 ? $creditBalance : $amount); //if got balance use creditBalance else amount
                }else{
                    $calculatedAmount = $amount;
                }

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

                $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".$db->escape($tblDate)." LIKE acc_credit");
                
                
                $decimalPlaces = $setting->getSystemDecimalPlaces();
                
                // Format amount to according to decimal places
                $calculatedAmount = number_format($calculatedAmount, $decimalPlaces, ".", "");

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
                $accountBalance = $this->getBalance($accountID, $type, "", false);
                
                if($allowNegativeBalanceFlag)
                {
                    $accountBalance -= $calculatedAmount; // Debit - minus
                }else{
                    if ($accountData['type'] == "Internal" && in_array($accountData['description'], array("Expenses"))) {
                        // Do nothing here
                    }
                    else {
                        // Check if balance is negative after deducting amount
                        $accountBalance -= $calculatedAmount; // Debit - minus
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
                
                if ($accountData['type'] == "Internal" && in_array($accountData['description'], array("Expenses"))) {
                    // Update cache balance for the account that debit
                    $this->updateClientCacheBalance($accountID, $type, $accountBalance);
                }
                else {
                    // 2nd checking on balance > 0 after insert debit, pass the flag as false so that it won't update the cache balance first
                    $accountBalance = $this->getBalance($accountID, $type, "", false);
                    
                    // Check if balance is negative after deducting amount
                    if($accountBalance < 0 && !$allowNegativeBalanceFlag) {
                        $data = array('deleted' => 1);
                        $db->where('id', $debitID);
                        $result = $db->update('acc_credit_'.$tblDate, $data);
                        return false; // Stop here after updating the debit row in acc_credit
                    }
                    else {
                        // Update cache balance for the account that debit
                        $this->updateClientCacheBalance($accountID, $type, $accountBalance);
                    }
                }

                // Get latest balance and update cache balance
                $receiverBalance = $this->getBalance($receiverID, $type, "", false);

                $receiverBalance += $calculatedAmount; // Credit - plus
               
                // 1st checking on balance > 0 before insert credit
                //$receiverBalance = $this->getBalance($receiverID, $type);
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

                // Update cache balance for the account that debit
                $this->updateClientCacheBalance($receiverID, $type, $receiverBalance);

                $creditTransactionRes = $this->insertCreditTransaction($accountID, $receiverID, $type, $calculatedAmount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark,$portfolioID, $data, $transactionID,$rate);
                if(!$creditTransactionRes)
                    return false;
                
                $amount -=  $calculatedAmount;
            }

                // Update main credit balance
            if($accountID > 50) $this->getBalance($accountID, $creditType);
            else $this->getBalance($receiverID, $creditType);

            if($creditType == 'mt4Credit'){

                if($accountID > 50) {
                    $this->getBalance($accountID, $creditType);
                    $dataABC = array("amount"=>$calculatedAmount,"comment" => $subject.", ".$belongID);
                    $testABC = json_encode($dataABC);
                    $b = array($testABC, $accountID,"0","withdrawalMT4", date('Y-m-d H:i:s'));
                }
                else {
                    $this->getBalance($receiverID, $creditType);
                    $dataABC = array("amount"=>$calculatedAmount,"comment" => $subject.", ".$belongID);
                    $testABC = json_encode($dataABC);
                    $b = array($testABC, $receiverID,"0","fundInMT4", date('Y-m-d H:i:s'));
                }
                $a = array("data","client_id","processed","queue_type", "created_at");
                $c = array_combine($a, $b);
                $db->Insert("mlm_queue", $c);
            }
            
            return true;
        }

        // Get balance from acc_closing & acc_credit_%
        public function getBalance($clientID, $type, $date='', $updateCache=true,$portfolioID=0) {
            $db = $this->db;
            $setting = $this->setting;

            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            $creditArr = $this->getPaymentCredit();
            $creditType = $creditArr[$type];

            if($type == 'mt4Credit'){
                $balance = $MT4->getBalance($clientID);
                if($balance['status'] == 'error'){
                    $balance = 0;
                }
                $balance = number_format(($balance), $decimalPlaces, '.', '');
            }
            else{

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
            }
            
            if ($updateCache && !$date) {
                // Update cache balance for the clientID
                $this->updateClientCacheBalance($clientID, $type, $balance);
            }
            
            return $balance;
        }
        
        // Get balance from acc_closing & acc_credit_%
        public function getAllClientBalance($clientIDAry, $typeAry, $date) {
            $db = $this->db;
            $setting = $this->setting;
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
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
                    $balanceRes = $db->get('acc_credit_'.$dateCredit, null,'SUM(credit - debit) AS balance, account_id, type');
                    foreach($balanceRes AS $balanceRow){
                    	$clientBalanceAry[$balanceRow['account_id']][$balanceRow['type']] = bcadd($clientBalanceAry[$balanceRow['account_id']][$balanceRow['type']], $balanceRow['balance'], 8);
                    }
                }
            }
            return $clientBalanceAry;
        }

        public function getClientCacheBalance($clientID, $creditType) {
            $db = $this->db;
            $setting = $this->setting;
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            $db->where('client_id', $clientID);
            $db->where('name', $creditType);
            $db->where('type', 'Credit Balance');
            $result = $db->getOne('client_setting', 'value');
            
            return $result['value']? number_format($result['value'], $decimalPlaces, '.', '') : 0;
        }
        
         public function updateClientCacheBalance($clientID, $creditType, $balance, $closingDate, $isClosing) {
            $db = $this->db;

            /*if($isClosing){
                $db->where("client_id",$clientID);
                $db->where("name",$creditType);
                $db->where("type","Credit Balance");
                $db->update("client_setting",array("reference" => $closingDate));
            }else{*/
                $db->where('client_id', $clientID);
                $db->where('name', $creditType);
                $db->where('type', 'Credit Balance');
                $count = $db->getValue("client_setting", "count(client_id)");

                if($count == 0){
                    // Insert new record
                    $balance = $this->getBalance($clientID,$creditType,"",false);
                    $fields = array("name","value","type","reference","client_id");
                    $values = array($creditType,$balance,"Credit Balance",$closingDate,$clientID);
                    //$values = array($rowID, $creditType, $balance, "Credit Balance", "", $clientID);
                    $arrayData = array_combine($fields,$values);
                    $db->insert("client_setting",$arrayData);
                }else{
                    $data = array("reference" => $closingDate);
                    $db->where("client_id",$clientID);
                    $db->where("name",$creditType);
                    $db->where("type","Credit Balance");
                    $db->update("client_setting",$data);
                }
            /*}*/

            return true;
        }
        
        /**
         * Accountings closing function
         * Used for calculating the total day's balance for each client and carry forward to the next date
         */
        public function closingOld($closingDate) {
            $db = $this->db;
            $log = $this->log;
            $message = $this->message;
            
            $log->write(date("Y-m-d H:i:s")." Deleting closing date $closingDate onwards.\n");
            
            if ($this->deleteClosing($closingDate)) {
                $log->write(date("Y-m-d H:i:s")." Successfully deleted closing from $closingDate onwards.\n");
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
            
            // Select all existing currencies
            $creditRes = $db->get('credit', null, array('name'));

            foreach ($creditRes as $creditRow) {
                
                $creditType = $creditRow["name"];
                
                $log->write(date("Y-m-d H:i:s")." Closing $creditType now.\n");
                
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
                    
                    $log->write(date("Y-m-d H:i:s")." Last closing date for client ".$clientRow["username"]." is $lastClosingDate.\n");
                    
                    // Convert to timestamp for comparison
                    $lastClosingTimestamp = strtotime($lastClosingDate);
                    
                    while ($lastClosingTimestamp <= $closingTimestamp) {
                        
                        $lastBalance = $this->closeClientAccount($clientRow["id"], $clientRow["username"], $lastClosingDate, $lastBalance, $creditType);
                        // Increment by 1 day for next iteration
                        $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
                        $lastClosingTimestamp = strtotime($lastClosingDate);
                        
                    }
                    
                    // Update client's latest cache balance for current currency
                    $balance = $this->getBalance($clientRow["id"], $creditType);
                    
                    $log->write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$clientRow["username"]."[".$clientRow["id"]."]. Balance: ".$balance."\n");
                    
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
                
                $log->write(date("Y-m-d H:i:s")." Finish closing for $creditType. Total issued: $incomeBalance + Total spending: $expensesBalance = $companyBalance\n");
                
                if ($companyBalance != 0) {
                    // If company balance is less than 0, means there might be a problem
                    $notTallyArray[] = $creditType." balance is not tally. Amount: $companyBalance\n";
                }
                
            }
            
            if (count($notTallyArray) > 0) {
                $content = "Closing result on $closingDate\n\n";
                $content .= implode("\n\n", $notTallyArray);
                // 10005 => balance not tally
                $message->createMessageOut(10005, $content);
            }
            
        }

        public function closing($closingDate) {
            $db             = $this->db;
            $log            = $this->log;
            $message        = $this->message;
            $setting        = $this->setting;
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $this->insertAccClosingBatch($closingDate);
            
            $log->write(date("Y-m-d H:i:s")." Deleting closing date $closingDate onwards.\n");
            
            if($this->deleteClosing($closingDate)){
                $log->write(date("Y-m-d H:i:s")." Successfully deleted closing from $closingDate onwards.\n");
            }
            
            // Convert to timestamp for comparison
            $closingTimestamp = strtotime($closingDate);

            // Get the latest acc closing date
            $db->where("completed","1");
            $db->orderBy("id","DESC");
            $latestAccClosingDate = $db->getValue("acc_closing_batch","closing_date");

            // Select all existing credits
            $creditRes = $db->map("name")->get("credit",null,"name,created_at");

            $db->where("name","isAccClosingPortfolio");
            $db->where("value",1);
            $isAccClosingPortfolio = $db->getValue("credit_setting","type",null);

            //Get acc credit records for closing date
            $db->where("deleted",0);
            $db->orderBy("account_id","ASC");
            $db->groupBy("account_id");
            $db->groupBy("type");
            $db->groupBy("portfolio_id");
            $closingDateAccRes = $db->get("acc_credit_".date("Ymd",strtotime($closingDate)),null,"type,account_id,portfolio_id");

            unset($clientIDAry);

            foreach($closingDateAccRes as $closingDateAccRow){
                $clientIDAry[$closingDateAccRow["account_id"]] = $closingDateAccRow["account_id"];
            }

            if($clientIDAry){
                $db->where("id",$clientIDAry,"IN");
                $clientRes = $db->map("id")->get("client",null,"id,DATE(created_at) as created_at");
            }

            unset($clientPortfolioCreditAry);

            foreach($closingDateAccRes as $closingDateAccRow){
                /*if($closingDateAccRow["type"] == "capitalDef"){
                    continue;
                }*/

                $skipClose = 1;
                $isPortfolioCredit = 0;
                $creditType = $closingDateAccRow["type"];
                $accountID = $closingDateAccRow["account_id"];

                if(!in_array($closingDateAccRow["type"],$isAccClosingPortfolio)){
                    $portfolioID = 0;
                }else{
                    $portfolioID = $closingDateAccRow["portfolio_id"];
                    $isPortfolioCredit = 1;
                    $clientPortfolioCreditAry[$accountID] = $creditType;
                }
                $creditCreatedAt = $creditRes[$closingDateAccRow["type"]];
                $creditCreatedAt = date("Y-m-d",strtotime($creditCreatedAt));

                $log->write(date("Y-m-d H:i:s")." Closing $creditType for $accountID.\n");

                $db->where("client_id",$accountID);
                $db->where("name",$creditType);
                $lastClosingDate = $db->getValue("client_setting","reference");

                // Prevent duplicate closing for other credits
                if((strtotime($lastClosingDate) == strtotime($closingDate)) && (!$isPortfolioCredit)){
                    $log->write(date("Y-m-d H:i:s")." Continue $creditType for $accountID - Already closed.\n");
                    continue;
                }

                if($lastClosingDate){
                    $db->where("client_id",$accountID);
                    $db->where("`type`",$creditType);
                    $db->where("`date`",$lastClosingDate,"<=");
                    if($portfolioID){
                        $db->where("portfolio_id",$portfolioID);
                    }
                    $db->orderBy("`date`","DESC");
                    $lastBalance = $db->getValue("acc_closing","balance");
                    $lastBalance = $lastBalance ? $lastBalance : 0;
                }else{
                    $db->where("client_id",$accountID);
                    $db->where("`type`",$creditType);
                    if($portfolioID){
                        $db->where("portfolio_id",$portfolioID);
                    }
                    $db->orderBy("`date`","DESC");
                    $accClosingResults = $db->getOne("acc_closing");

                    $lastClosingDate = $accClosingResults["date"];
                    $lastBalance = $accClosingResults["balance"] ? $accClosingResults["balance"] : 0;
                }

                if(!$lastClosingDate){
                    // Set to client joined date if did not perform closing previously
                    $lastClosingDate = $clientRes[$accountID];
                    // Based on credit created date
                    if(strtotime($lastClosingDate) <= strtotime($creditCreatedAt)) {
                        $lastClosingDate = $creditCreatedAt;
                    }
                    $skipClose = 0;
                }

                // Check latest acc closing date
                if($skipClose){
                    $lastClosingDate = $latestAccClosingDate;
                    $lastClosingDate = date("Y-m-d",strtotime("+1 day",strtotime($lastClosingDate)));
                }

                $log->write(date("Y-m-d H:i:s")." Last closing date for client ".$accountID." is $lastClosingDate.\n");

                // Convert to timestamp for comparison
                $lastClosingTimestamp = strtotime($lastClosingDate);

                while($lastClosingTimestamp <= $closingTimestamp){
                    $lastBalance = $this->closeClientAccount($accountID,$accountID,$lastClosingDate,$lastBalance,$creditType,$skipClose,$portfolioID);
                    // Increment by 1 day for next iteration
                    $lastClosingDate = date("Y-m-d",strtotime("+1 day",strtotime($lastClosingDate)));
                    $lastClosingTimestamp = strtotime($lastClosingDate);
                    // Reset the skipClose flag
                    $skipClose = 1;
                }

                if(!$isPortfolioCredit){
                    // Update client's latest cache balance for current currency
                    $balance = $this->updateClientCacheBalance($accountID,$creditType,"",$closingDate,true);
                }
                
                $log->write(date("Y-m-d H:i:s")." Finish closing $creditType for ".$accountID."\n");
            }

            // Special handle for acc closing portfolio
            foreach($clientPortfolioCreditAry as $clientID => $creditType){
                $this->updateClientCacheBalance($clientID,$creditType,"",$closingDate,true);
            }

            //Audit the credit type
            $this->checkDailyAccountTally($closingDate);

            $this->insertAccClosingBatch($closingDate,true);

            return true;
        }
        
        private function closeClientAccount($clientID, $clientUsername, $closingDate, $previousBalance=0, $creditType, $skipClose, $portfolioID) {
            $db = $this->db;
            $setting = $this->setting;
            $log = $this->log;
            
            $decimalPlaces = $setting->getSystemDecimalPlaces();
            
            $db->where('account_id', $clientID);
            $db->where('type', $creditType);
            $db->where('deleted', 0);
            if($portfolioID){
                $db->where("portfolio_id",$portfolioID);
            }
            $accRes = $db->getOne('acc_credit_'.date("Ymd", strtotime($closingDate)), 'SUM(debit) AS debit, SUM(credit) AS credit');

            // No need insert acc closing if there is no transaction
            // Need insert for client joined date or last month of the day or the latest transaction
            if($skipClose && !$accRes["debit"] && !$accRes["credit"]) {
                return $previousBalance;
            }
            
            $log->write(date("Y-m-d H:i:s")." Last query: ".$db->getLastQuery()."\n");
            
            $credit = $accRes["credit"]? $accRes["credit"] : 0;
            $debit = $accRes["debit"]? $accRes["debit"] : 0;
            $total = number_format(($credit - $debit), $decimalPlaces, ".", "");
            $balance = number_format(($previousBalance + $total), $decimalPlaces, ".", "");
            
            $log->write(date("Y-m-d H:i:s")." PreviousBalance: $previousBalance, Debit: $debit, Credit: $credit, Total: $total, Balance: $balance\n");
            
            // Insert client's closing record into acc_closing
            $fields = array("client_id", "type", "date", "total", "balance", "created_at", "portfolio_id");
            $values = array($clientID, $creditType, $closingDate, $total, $balance, date("Y-m-d H:i:s"), $portfolioID);
            $arrayData = array_combine($fields, $values);
            $db->insert('acc_closing', $arrayData);
            
            return $balance; // Return the latest balance
        }

        public function deleteClosing($closingDate) {
            $db = $this->db;
            $log = $this->log;
            
            $db->where('date', $closingDate, " >= ");
            $db->delete('acc_closing');
            // Optmize the table after deletion
            $db->optimize('acc_closing');
        }


        function memberPaymentTransaction($params,$clientID)
        {
            $db = $this->db;
            $setting = $this->setting;

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
                // $this->insertTAccount($clientID,$payoutID,$creditType,$amount,"Payout to downline",$belong,"",date("Y-m-d H:i:s"),$belong,$clientID);
                // insert downline receive payment from upline
                $this->insertTAccount($payoutID,$downlineID,$creditType,$amount,"Receive payment from upline",$belong,"",date("Y-m-d H:i:s"),$belong,$downlineID);
            }
            else if($paymentType == "receive")
            {
                $belong = $db->getNewID();
                // receive payment from downline
                $this->insertTAccount($downlineID,$payoutID,$creditType,$amount,"Payout to upline",$belong,"",date("Y-m-d H:i:s"),$belong,$downlineID);
                // downline pay to upline
                // $this->insertTAccount($payoutID,$clientID,$creditType,$amount,"Receive payment from downline",$belong,"",date("Y-m-d H:i:s"),$belong,$clientID);
            }

            //get client balance
            $balance = $this->getClientCacheBalance($downlineID,$creditType);

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

        private function insertCreditTransaction($accountID, $receiverID, $type, $amount, $subject, $belongID, $referenceID, $transactionDate, $batchID, $clientID, $remark,$portfolioID=0, $data, $transactionID,$rate)
        {
            $db = $this->db;
            $general = $this->general;
            
            $creatorID = $this->creatorID ? $this->creatorID:0;
            $creatorType = $this->creatorType ? $this->creatorType:'System';

            $fields = array("id", "subject", "type", "from_id", "to_id", "client_id", "amount", "remark", "belong_id", "reference_id", "batch_id", "deleted", "creator_id", "creator_type", "created_at","portfolio_id", "data", "transaction_id","coin_rate");

            $values = array($db->getNewID(), $subject, $type, $accountID, $receiverID, $clientID, $amount, $remark, $belongID, $referenceID, $batchID, "0", $creatorID, $creatorType, $transactionDate,$portfolioID, $data, $transactionID,$rate);

            $arrayData = array_combine($fields,$values);
            
            $result = $db->insert("credit_transaction",$arrayData);
            if($result)
                return true;
            
            return false;
        }

        public function lockLiveRate($params) {
            
            $db             = $this->db;
            $setting        = $this->setting;
            $client         = $this->client;
            $cash           = $this->cash;
            $admin          = $this->admin;
            $general        = $this->general;
            $coindata       = $this->coindata;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            $acceptCoinType = json_decode($setting->systemSetting['acceptCoinType']);
            $clientID = $params['clientID'];

            if(!$params['coinType'] || $params['coinType'] == '')
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00534"][$language], 'data' => "coin type"); // Coin Type Cannot be empty

            foreach($params['coinType'] as $coinType){
                if(!in_array($coinType , $acceptCoinType ))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00505"][$language], 'data' => "coin type"); // Invalid Value
            }

            if(!$params['txnType'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00505"][$language], 'data' => "transaction type"); // Invalid Value

            if(!$clientID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language], 'data' => "username"); // Member not found.
            }
            
            $db->where("id", $clientID);
            $db->where("type", "Client");
            $res = $db->getValue("client", "id");
            if(!$res){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language], 'data' => "username"); // Member not found.
            }

            $db->where('type', "liveCoinRate");
            $res = $db->get("system_settings", NULL, array("name", "value"));
            foreach ($res as $key => $value) {
                $coinName = str_replace(' Latest Rate', '', $value['name']);
                if( in_array($coinName, $params['coinType']) ){
                    $rate[$coinName] = bcdiv((string)$value['value'],"1",$setting->systemSetting['systemDecimalFormat']);
                }
            }
            $data['rate'] = $rate;
            unset($result);unset($key);unset($value);

            $data['expirePeriod'] = $setting->systemSetting['lockCoinRateExpireTime'];
            $data['expireTime'] = date("Y-m-d H:i:s", strtotime("+".$data['expirePeriod']));

            foreach($params['coinType'] as $coinType){
                $insertData = array(
                    'client_id' => $clientID,
                    'coin_type' => $coinType,
                    'type' => (string)$params['txnType'],
                    'rate' => $rate[$coinType],
                    'created_on' => date('Y-m-d H:i:s'),
                    'expired_on' => $data['expireTime'],
                );
                $success = $db->insert("mlm_lock_coin_rate", $insertData);

                if(!$success)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language], 'data' => ""); // Failed to insert activity.
            }


            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        
        }

        public function getLockRate($params) {
            
            $db             = $this->db;
            $setting        = $this->setting;
            $client         = $this->client;
            $cash           = $this->cash;
            $admin          = $this->admin;
            $general        = $this->general;
            $coindata       = $this->coindata;
            $language       = $this->general->getCurrentLanguage();
            $translations   = $this->general->getTranslations();

            $acceptCoinType = json_decode($setting->systemSetting['acceptCoinType']);
            $clientID = $params['clientID'];

            if(!$params['txnType'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00505"][$language], 'data' => "transaction type"); // Invalid Value

            if(!$params['amount'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00341"][$language], 'data' => $coinType." amount : ".$amount); // Amount is required or invalid

            foreach ($params['amount'] as $coinType => $amountV) {
                $amount = (string) $amountV;

                if(!in_array($coinType , $acceptCoinType ))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00505"][$language], 'data' => "coin type : ".$coinType); // Invalid Value

                if( !is_numeric($amount) )
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00505"][$language], 'data' => "amount : ".$amount); // Invalid Value

                if(!$amount || $amount <= 0 || $amount == '')
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00341"][$language], 'data' => $coinType." amount : ".$amount); // Amount is required or invalid

                $coinTypeAry[$coinType] = $coinType;
            }

            if(!$clientID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language], 'data' => "username"); // Member not found.
            }

            $db->where("id", $clientID);
            $db->where("type", "Client");
            $res = $db->getValue("client", "id");
            if(!$res){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language], 'data' => "username"); // Member not found.
            }

            if(!$coinTypeAry){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00125"][$language] /* Invalid Value. */, 'data' => "amount");       
            }

            $db->where("client_id", $clientID);
            $db->where("coin_type", $coinTypeAry, "IN");
            $db->where("type", (string)$params['txnType']);
            $db->where("created_on", date('Y-m-d H:i:s'), "<=");
            $db->where("expired_on", date('Y-m-d H:i:s'), ">=");
            $db->orderBy("created_on", "ASC");
            $res = $db->get("mlm_lock_coin_rate", null, "rate, coin_type");
            if(!$res){
                return array('status' => "error", 'code' => 9,  'statusMsg' => $translations["E00745"][$language], 'data' => ""); // Your Transaction Have Expired.
            }

            foreach ($res as $key => $value) {
                $rate[$value['coin_type']] = $value['rate'];
            }

            foreach ($params['amount'] as $coinType => $amount) {
                $amountAry[$coinType] = bcdiv((string)$amount,"1",$setting->systemSetting['systemDecimalFormat']);
                $coinAmountAry[$coinType] = bcdiv((string)$amount,$rate[$coinType],$setting->systemSetting['systemDecimalFormat']);
            }

            $data['rate'] = $rate;
            $data['amount'] = $amountAry;
            $data['coinAmount'] = $coinAmountAry;


            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        
        }

        function insertRedeemPoints($params) {
            
            $db                 = $this->db;

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
            $this->insertTAccount($redeemPointsID, $clientID, $creditType, $bonusValue, $subject, $belongID, "", $db->now(), $batchID, $clientID, "Investment",$portfolioID);
            // $clientBalance=$this->getBalance($clientID,$creditType);

            // $convertedPoints=floor($clientBalance/$creditSettingAry[$creditType]);

            // if ($convertedPoints){
            //     $this->insertTAccount($clientID, $redeemPointsID, $creditType, $convertedPoints*$creditSettingAry[$creditType], $convertSubject, $belongID, "", $db->now(), $batchID, $clientID, "Investment");
            //     $this->insertTAccount($redeemPointsID, $clientID, $prizeCredit, $convertedPoints, $convertSubject, $belongID, "", $db->now(), $batchID, $clientID, "Investment");
            // }

            // if($uplineID){
                
            //     $creditType='redeem2Credit';

            //     $this->insertTAccount($redeemPointsID, $uplineID, $creditType, $bonusValue, $subject, $belongID, "", $db->now(), $batchID, $clientID, "Sponsor");
            //     // $uplineBalance=$this->getBalance($uplineID,$creditType);

            //     // $convertedPoints=floor($uplineBalance/$creditSettingAry[$creditType]);

            //     // if ($convertedPoints){
            //     //     $this->insertTAccount($uplineID, $redeemPointsID, $creditType, $convertedPoints*$creditSettingAry[$creditType], $convertSubject, $belongID, "", $db->now(), $batchID, $uplineID,"Sponsor");
            //     //     $this->insertTAccount($redeemPointsID, $uplineID, $prizeCredit, $convertedPoints, $convertSubject, $belongID, "", $db->now(), $batchID, $uplineID,"Sponsor");
                    
            //     // }
            // }

        }

        public function getPaymentCredit(){
            $db = $this->db;
            
            $db->orderBy('priority', 'ASC');
            $result = $db->get("credit",NULL,"name,type");
            foreach($result AS $key => $value){
                $creditArr[$value['name']][] = $value['name']; 
                if($value['name'] != $value['type']){
                    $creditArr[$value['type']][] = $value['name']; 
                }
            }
            if(empty($result)) return false;

            return $creditArr;
        }

        public function insertAccClosingBatch($closingDate,$isCompleted){
            $db = $this->db;

            if(!$closingDate){
                die(date("Y-m-d H:i:s")." Batch Argv not valid!");
            }

            $db->where("closing_date",$closingDate);
            $batchID = $db->getValue("acc_closing_batch","id");
            if(!$batchID && !$isCompleted){
                unset($insert);
                $insert = array(
                    "closing_date"  => $closingDate,
                    "completed"     => "0",
                    "created_at"    => date("Y-m-d H:i:s"),
                );
                $db->insert("acc_closing_batch",$insert);
            }else if($isCompleted){
                unset($update);
                $update = array(
                    "completed"     => 1,
                    "completed_at"  => date("Y-m-d H:i:s"),
                );
                $db->where("id",$batchID);
                $db->update("acc_closing_batch",$update);
            }

            return true;
        }

        public function checkAccountTally($closingDate){
            $db             = $this->db;
            $log            = $this->log;
            $message        = $this->message;
            $setting        = $this->setting;
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $log->write(date("Y-m-d H:i:s")." Start running check account tally\n");

            $db->where("main_id",0,"<=");
            $clientRes = $db->getValue("client","id",null);

            $db->orderBy("type","ASC");
            $creditRes = $db->getValue("credit","name",null);

            unset($checkTallyBal);
            foreach($clientRes as $clientID){
                foreach($creditRes as $creditType){
                    $lastBalance = $this->getBalance($clientID,$creditType,$closingDate,false);
                    if($lastBalance == 0) continue;
                    $checkTallyBal[$creditType] = number_format(($checkTallyBal[$creditType]+$lastBalance),$decimalPlaces,".","");
                }
            }

            foreach($checkTallyBal as $creditType => $balance){
                if($balance != 0){
                    $log->write(date("Y-m-d H:i:s")." [$creditType] balance is not tally. Balance: $balance\n");
                    $notTallyArray[] = $creditType." balance is not tally.\n";
                }
            }

            if(count($notTallyArray) > 0){
                $content = "Closing result on $closingDate\n\n";
                $content .= implode("\n\n", $notTallyArray);
                // 10005 => balance not tally
                $message->createMessageOut(10005, $content);
            }

            $log->write(date("Y-m-d H:i:s")." Finish running check account tally\n");

            return true;
        }

        public function checkDailyAccountTally($closingDate){
            $db             = $this->db;
            $log            = $this->log;
            $message        = $this->message;
            $setting        = $this->setting;
            $decimalPlaces  = $setting->getSystemDecimalPlaces();

            $log->write(date("Y-m-d H:i:s")." Start checking daily account tally\n");

            $db->where("name","isAccClosingPortfolio");
            $db->where("value",1);
            $isAccClosingPortfolio = $db->getValue("credit_setting","type",null);

            $db->where("deleted",0);
            $db->orderBy("account_id","ASC");
            $db->groupBy("account_id");
            $db->groupBy("type");
            $db->groupBy("portfolio_id");
            $accCreditRes = $db->get("acc_credit_".date("Ymd",strtotime($closingDate)),null,"account_id,type,SUM(credit) as credit,SUM(debit) as debit,portfolio_id");

            unset($checkTallyBal,$clientIDAry);

            foreach($accCreditRes as $accCreditRow){
                /*if($accCreditRow["type"] == "capitalDef"){
                    continue;
                }*/

                $clientID   = $accCreditRow["account_id"];
                $creditType = $accCreditRow["type"];
                $credit     = $accCreditRow["credit"];
                $debit      = $accCreditRow["debit"];
                $portfolioID= $accCreditRow["portfolio_id"];

                if(!in_array($creditType,$isAccClosingPortfolio) && $portfolioID){
                    $portfolioID = 0;
                }

                if($credit > 0){
                    $checkTallyBal[$creditType][$clientID][$portfolioID] = number_format(($checkTallyBal[$creditType][$clientID][$portfolioID]+$credit),$decimalPlaces,".","");
                }

                if($debit > 0){
                    $checkTallyBal[$creditType][$clientID][$portfolioID] = number_format(($checkTallyBal[$creditType][$clientID][$portfolioID]-$debit),$decimalPlaces,".","");
                }
                
                $clientIDAry[$accCreditRow["account_id"]] = $accCreditRow["account_id"];
            }

            if($clientIDAry){
                $db->where("client_id",$clientIDAry,"IN");
                $db->where("`date`",$closingDate);
                $db->groupBy("client_id");
                $db->groupBy("type");
                $db->groupBy("portfolio_id");
                $accClosingRes = $db->get("acc_closing",null,"client_id,type,total,portfolio_id");
            }

            unset($notTallyArray);

            foreach($accClosingRes as $accClosingRow){
                /*if($accClosingRow["type"] == "capitalDef"){
                    continue;
                }*/
                
                $clientID   = $accClosingRow["client_id"];
                $creditType = $accClosingRow["type"];
                $total      = $accClosingRow["total"];
                $portfolioID= $accClosingRow["portfolio_id"];
                $accTotal   = $checkTallyBal[$creditType][$clientID][$portfolioID];

                if($total != $accTotal){
                    $log->write(date("Y-m-d H:i:s")." [$clientID][$creditType] Balance is not tally. Acc Closing Total: $total. Acc Credit Total: $accTotal\n");
                    $notTallyArray[] = "[".$creditType."] Balance is not tally.\n";
                }
            }

            if(count($notTallyArray) > 0){
                $content = "Closing result on $closingDate\n\n";
                $content .= implode("\n\n",$notTallyArray);
                // 10005 => balance not tally
                $message->createMessageOut(10005,$content);
            }

            $log->write(date("Y-m-d H:i:s")." Finished checking daily account tally\n");

            return true;
        }

    }

?>
