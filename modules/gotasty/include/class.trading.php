<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 13/07/2020.
    **/

    class Trading {

        function __construct() {

        }

        public function sendTrdDataToSocket($dataIn){
            $context = new ZMQContext();
            $portNumber = Setting::$configArray["socketPort"];
            $socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'my pusher');
            $socket->connect("tcp://localhost:".$portNumber);
            $socket->send(json_encode($dataIn));

            return true;
        }

        private function getTrdPaymentMethod($type, $coinType){
        	$db = MysqliDb::getInstance();

        	if(!$coinType){
        		return false;
        	}

        	if($type) $db->where("type", $type);
        	$db->where("coin_type", $coinType);
        	$paymentRes = $db->get("trd_payment_method", null, "type, pay_type AS payCreditType, receive_type AS receiveCreditType, admin_charge AS adminCharge, charge_type as chargeType");
        	foreach ($paymentRes as $paymentRow) {
        		$paymentAry[$paymentRow["type"]] = $paymentRow;
        	}

        	return $paymentAry;
        }

        public function getTrdCoin($coinType){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if($coinType) $db->where("name", $coinType);
            $db->where("disabled", "0");
            $db->orderBy("priority", "ASC");
            $coinRes = $db->get("trd_coin", null, "id, name, translation_code, dcm, priority");
            foreach ($coinRes as $coinRow) {
                $coinIDAry[$coinRow["id"]] = $coinRow["id"];
            }

            if($coinIDAry){
                $db->where("coin_id", $coinIDAry, "IN");
                $coinSettingRes = $db->get("trd_coin_setting", null, "coin_id, name, value");
                foreach ($coinSettingRes as $coinSettingRow) {
                    if($coinSettingRow["name"] == "timeBuyLimit"){
                        unset($limitSettingRow);
                        $limitAry = explode("#", $coinSettingRow["value"]);
                        $limitSettingRow["time"] = $limitAry[0];
                        $limitSettingRow["amount"] = $limitAry[1];
                        $coinSettingAry[$coinSettingRow["coin_id"]][$coinSettingRow["name"]][$limitSettingRow["time"]] = $limitSettingRow["amount"]; 
                    }else{
                        $coinSettingAry[$coinSettingRow["coin_id"]][$coinSettingRow["name"]] = $coinSettingRow["value"]; 
                    }
                }
            }

            foreach ($coinRes as $coinRow) {
                unset($coinDataRow);

                $coinDataRow = $coinSettingAry[$coinRow["id"]];
                $coinDataRow["name"] = $coinRow["name"];
                $coinDataRow["langCode"] = $coinRow["translation_code"];
                $coinDataRow["display"] = $translations[$coinRow["translation_code"]][$language];
                $coinDataRow["coinPrice"] = General::getLatestUnitPrice($coinRow["name"]);

                $coinAry[] = $coinDataRow;
            }
            return $coinAry;
        }

        public function insertGraphData($tableName, $coinType){
            $db = MysqliDb::getInstance();
            
            if(!$coinType){
                Log::write(date("Y-m-d H:i:s")." Invalid Coin Type.\n");
                return false;
            }

            $dateTime = date('Y-m-d H:i:s');

            $db->where("coin_type", $coinType);
            $db->orderBy("created_at", "DESC");
            $dataRow = $db->getOne($tableName, "coin_type, high, low, open, close, volume, updated_at");

            $baseRate = General::getLatestUnitPrice($coinType);

            if(empty($dataRow)){
                $insertData = array(    
                                        "coin_type" => $coinType,
                                        "high" => $baseRate,
                                        "low" => $baseRate,
                                        "open" => $baseRate,
                                        "close" => $baseRate,
                                        "volume" => 0,
                                        "created_at" => $dateTime,
                                        "updated_at" => $dateTime,
                                    );
            }else{
                $insertData = array(    
                                        "coin_type" => $coinType,
                                        "high" => $dataRow["close"],
                                        "low" => $dataRow["close"],
                                        "open" => $dataRow["close"],
                                        "close" => $dataRow["close"],
                                        "volume" => 0,
                                        "created_at" => $dateTime,
                                        "updated_at" => $dateTime,
                                    );
            }
            $db->insert($tableName, $insertData);

            return true;
        }

        public function getStocks($interval, $tableName, $coinType){
            $db = MysqliDb::getInstance();

            $maxCandlestick = Setting::$systemSetting["maxCandlestick"] ? Setting::$systemSetting["maxCandlestick"] : 1440;

            $startTime = (floor(time()/(int)$interval)*(int)$interval)-((int)$interval*$maxCandlestick);
            $startDate = date("Y-m-d H:i:s", $startTime);

            $queryRes = $db->rawQuery("SELECT created_at, 
                                      SUBSTRING_INDEX(MIN(CONCAT(UNIX_TIMESTAMP(created_at), '_', open)), '_', -1) AS 'open', 
                                      MAX(high) as 'high', 
                                      MIN(low) as 'low', 
                                      SUBSTRING_INDEX(MAX(CONCAT(UNIX_TIMESTAMP(created_at), '_', close)), '_', -1) AS 'close', 
                                      AVG(volume) as 'volume' 
                                      FROM ".$tableName."
                                      WHERE created_at > '".$startDate."' 
                                      AND coin_type = '".$coinType."'
                                      GROUP BY FLOOR(UNIX_TIMESTAMP(created_at)/".$interval.")
                                      ORDER BY created_at ASC");


            $graphData["latestRow"] = $queryRes[($db->count) - 1];
            $graphData["allRow"] = $queryRes;
            return $graphData;
        }

        public function graphDataCache($quantity, $price, $coinType){
            $db = MysqliDb::getInstance();

            $low = 0;
            $high = 0;
            $open = 0;
            $volume = 0;

            $insertData = array(
                'coin_type' => $coinType,
                'price' => $price,
                'quantity' => $quantity,
                'created_at' => date('Y-m-d H:i:s')
            );

            $db->insert('trd_latest_trade',$insertData);

            unset($insertData);

            $db->where("table_name", "trd_graph_data%", "LIKE");
            $db->where("table_schema", Setting::$configArray['dB']);
            $tableRes = $db->getValue("information_schema.tables", "table_name", null);
            foreach ($tableRes as $tableName) {
                unset($dataRow);
                unset($updatedData);
                $rowID = 0;
                
                $db->where("coin_type", $coinType);
                $db->orderBy("created_at", "DESC");
                $dataRow = $db->getOne($tableName, "id, high, low, open, volume");

                $low = $dataRow["low"];
                $high = $dataRow["high"];
                $open = $dataRow["open"];
                $volume = $dataRow["volume"];
                $rowID = $dataRow["id"];

                // low
                if($price < $low || $low == 0) $low = $price;
                
                // highest
                if($price > $high) $high = $price;
                
                // get change
                // FOMULA : (Last done price - yesterday closing price ) / yesterday closing price  *  100
                $change = $open == 0 ? 0 : (($price - $open) / $open) * 100;

                // volume
                $volume += $quantity;
                
                $updatedData = array(
                                        "high" => $high,
                                        "low" => $low,
                                        "close" => $price,
                                        "volume" => $volume,
                                        "change" => $change,
                                        "updated_at" => date("Y-m-d H:i:s"),
                                    );
                $db->where("id", $rowID);
                $db->update($tableName, $updatedData);
            }

            unset($updateData);
            $db->where("coin_type", $coinType);
            $summaryRow = $db->getOne("trd_trading_summary", "id, high, low, open, volume");
            if($summaryRow){
                $low = $summaryRow["low"];
                $high = $summaryRow["high"];
                $open = $summaryRow["open"];
                $volume = $summaryRow["volume"];
                $summaryID = $summaryRow["id"];

                // low
                if($price < $low || $low == 0) $low = $price;

                // highest
                if($price > $high) $high = $price;

                // get change
                // FOMULA : (Last done price - yesterday closing price ) / yesterday closing price  *  100
                $change = $open == 0 ? 0 : (($price - $open) / $open) * 100;
                $changePercentage = $change * 100;

                // volume
                $volume += $quantity;

                $updatedData = array(
                                        "high" => $high,
                                        "low" => $low,
                                        "close" => $price,
                                        "volume" => $volume,
                                        "change" => $change,
                                        "change_percentage" => $changePercentage,
                                        "updated_at" => date("Y-m-d H:i:s"),
                                    );
                $db->where("id", $summaryID);
                $db->update("trd_trading_summary", $updatedData);
                
            }else{

                $open = General::getLatestUnitPrice($coinType);
                
                // low
                if($price < $open || $open == 0) $low = $price;
                else $low = $open;

                // highest
                if($price > $high) $high = $price;
                else $high = $open;

                // get change
                // FOMULA : (Last done price - yesterday closing price ) / yesterday closing price  *  100
                $change = $open == 0 ? 0 : (($price - $open) / $open) * 100;

                // volume
                $volume = $quantity;

                $insertData = array(    
                                        "coin_type" => $coinType,
                                        "high" => $high,
                                        "low" => $low,
                                        "open" => $open, 
                                        "close" => $price,
                                        "volume" => $volume,
                                        "change" => $change,
                                        "updated_at" => date("Y-m-d H:i:s"),
                                    );

                $db->insert("trd_trading_summary", $insertData);
            }


            return true;
        }

        public function checkDailyLimit($clientID, $coinType, $dateTime){
            $db = MysqliDb::getInstance();

            if(!$coinType) return false;

            if(!$dateTime) $dateTime = date("Y-m-d H:i:s");

            $coinAry = self::getTrdCoin($coinType);
            foreach ($coinAry as $coinRow) {
                $coinTypeAry[$coinRow["name"]] = $coinRow;
            }

            $db->where("client_id", $clientID);
            $db->where("type", "buy");
            $db->where("DATE(created_at)", date("Y-m-d", strtotime($dateTime)));
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $balance = $db->getValue("trd_client_limit", "balance");
            $dailyBuyLimit = $coinTypeAry[$coinType]["dailyBuyLimit"];
            $timeBuyLimitAry = $coinTypeAry[$coinType]["timeBuyLimit"];
            krsort($timeBuyLimitAry);
            
            $nowHrs = date("H", strtotime($dateTime));
            $nowHrs = 21;

            foreach ($timeBuyLimitAry as $hrs => $value) {
                if($hrs <= $nowHrs){
                    $timeLimit = $value <= 0 ? $balance : $value;
                    $hitHrs = $hrs;
                    break;
                }
            }
            $db->where("client_id", $clientID);
            $db->where("type", "buy");
            $db->where("created_at", date("Y-m-d ".$hitHrs.":00:00", strtotime($dateTime)),">=");
            $buyValue = $db->getValue("trd_client_limit", "SUM(debit)");
            if($balance > $timeLimit){
                $remainBuyLimit = $timeLimit - $buyValue;
            }else{
                $remainBuyLimit = $balance - $buyValue;
            }

            $remainBuyLimit = $remainBuyLimit > 0 ? $remainBuyLimit : 0;
            return $remainBuyLimit;
        }

        public function updateBuySellLimit($type, $clientID, $amount,$dateTime, $coinType, $belongID){
            $db = MysqliDb::getInstance();
            
            if(!$type){
                return false;
            }

            if(!$dateTime){
                $dateTime = date("Y-m-d H:i:s");
            }

            if(!$coinType){
                $coinType = "trdBBIT";
            }

            $db->where("client_id", $clientID);
            $db->where("type", "buy");
            $db->where("DATE(created_at)", date("Y-m-d", strtotime($dateTime)));
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $balance = $db->getValue("trd_client_limit", "balance");

            $db->where("name", "isDailyLimitWallet");
            $db->where("value", 1);
            $checkTypeRow = $db->getOne("credit_setting", "credit_id, reference");
            if(empty($checkTypeRow)){
                return false;
            }

            $db->where("id", $checkTypeRow["credit_id"]);
            $creditType = $db->getValue("credit", "name");

            $limitPercentage = $checkTypeRow["reference"];
            $realLimit = Setting::setDecimal(($amount * $limitPercentage/100),"");
            
            switch ($type) {
                case 'add': 
                    $insertData = array(    
                                            "type" => "buy",
                                            "client_id" => $clientID,
                                            "credit" => $realLimit,
                                            "balance" => Setting::setDecimal(($balance + $realLimit)),
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;

                case 'adjustIn': 
                    $insertData = array(    
                                            "type" => "buy",
                                            "client_id" => $clientID,
                                            "credit" => $amount,
                                            "balance" => Setting::setDecimal(($balance + $amount)),
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;

                case 'adjustOut': 
                    $insertData = array(    
                                            "type" => "buy",
                                            "client_id" => $clientID,
                                            "debit" => $amount,
                                            "balance" => Setting::setDecimal(($balance - $amount)),
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;

                case 'reduce': 
                    $insertData = array(    
                                            "type" => "buy",
                                            "client_id" => $clientID,
                                            "debit" => $realLimit,
                                            "balance" => Setting::setDecimal(($balance - $realLimit)),
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;

                case 'buy': 
                    $insertData = array(    
                                            "type" => "buy",
                                            "client_id" => $clientID,
                                            "debit" => $amount,
                                            "balance" => Setting::setDecimal(($balance - $amount)),
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;

                case 'daily':
                    //reset
                    $insertData = array(    
                                            "type" => "buy",
                                            "client_id" => $clientID,
                                            "credit" => $realLimit,
                                            "balance" => $realLimit,
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;
            }
            $remainBuyLimit = self::checkDailyLimit($clientID, $coinType, $dateTime);
            if($remainBuyLimit > 0){
                //update queue
                $db->where("client_id", $clientID);
                $db->where("status", 1);
                $queueRes = $db->get("trd_sell_queue", null, "id, client_id, credit_type, quantity, price, trd_transaction_id");
                if($queueRes){
                    $db->where("client_id", $clientID);
                    $db->where("status", 1);
                    $db->update("trd_sell_queue", array("status" => 0));
                }
            }else{
                $db->where("client_id", $clientID);
                $db->where("status", 0);
                $queueRes = $db->get("trd_sell_queue", null, "id, client_id, credit_type, quantity, price, trd_transaction_id");
                if($queueRes){
                    $db->where("client_id", $clientID);
                    $db->where("status", 0);
                    $db->update("trd_sell_queue", array("status" => 1));
                }
            }

            return true;
        }

        public function updateBuySellVolume($type, $clientID, $amount,$dateTime, $coinType, $belongID){
            $db = MysqliDb::getInstance();

            if(!$type){
                return false;
            }

            if(!$dateTime){
                $dateTime = date("Y-m-d H:i:s");
            }

            if(!$coinType){
                $coinType = "trdBBIT";
            }

            $db->where("client_id", $clientID);
            $db->where("type", "vol");
            $db->where("DATE(created_at)", date("Y-m-d", strtotime($dateTime)));
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $balance = $db->getValue("trd_client_limit", "balance");

            switch ($type) {
                case 'match':
                    $insertData = array(    
                                            "type" => "vol",
                                            "client_id" => $clientID,
                                            "credit" => $amount,
                                            "balance" => Setting::setDecimal(($balance + $amount)),
                                            "belong_id" => $belongID,
                                            "created_at" => $dateTime,
                                        );
                    $db->insert("trd_client_limit", $insertData);
                    break;
                
            }
            return true;
        }

        public function getMemberBuySellVolume($clientID, $dateTime){
            $db = MysqliDb::getInstance();

            if($clientID) $db->where("client_id", $clientID);
            $db->where("type", "vol");
            $db->where("DATE(created_at)", $dateTime);
            $db->groupBy("client_id");
            $clientBuyAry = $db->map("client_id")->get("trd_client_limit", null, "client_id, SUM(credit - debit) as buyVolume");

            return $clientBuyAry;
        }

        public function getTradeDetail($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlaces = Setting::$systemSetting["internalDecimalFormat"];

            $clientID = $db->userID;
            $site = $db->userType;

            $type = trim($params["type"]);

            if(!$type){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Type.", 'data'=>"");
            }

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            if(!$coinType){
                $coinType = "trdBBIT";
            }

            $paymentAry = self::getTrdPaymentMethod($type, $coinType);
            foreach ($paymentAry as $payType => $value) {
                $creditTypeAry[$value["payCreditType"]] = $value["payCreditType"];
            }

            if($creditTypeAry){
                $db->where("type", $creditTypeAry, "IN");
                $creditDataAry = $db->map("type")->get("credit", null, "id, name, type, translation_code");
                foreach ($paymentAry as $payType => $value) {
                    unset($payRow);

                    $payRow["creditType"] = $value["payCreditType"];
                    $payRow["creditLangCode"] = $creditDataAry[$value["payCreditType"]]["translation_code"];
                    $payRow["creditDisplay"] = $translations[$creditDataAry[$value["payCreditType"]]["translation_code"]][$language];
                    $payRow["adminCharge"] = number_format($value["adminCharge"], $decimalPlaces, ".", "");
                    $payRow["balance"] = Cash::getBalance($clientID, $value["payCreditType"]);

                    $paymentDataAry[] = $payRow;
                }
            }

            $coinAry = self::getTrdCoin($coinType);
            foreach ($coinAry as &$coinValue) {
                $coinValue["lastPrice"] = "1.2";
            }

            $dataOut["paymentAry"] = $paymentDataAry;
            $dataOut["coinAry"] = $coinAry;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully Retrieved", 'data'=> $dataOut);
        }

        public function resetTrdBuySellLimit($bonusDate){
            $db = MysqliDb::getInstance();
            $type = "sell";
            $bonusName = "resetTrdBuySellLimit";

            $db->where("bonus_name",$bonusName);
            $db->where("DATE(bonus_date)",$bonusDate);
            $db->where("completed","1");
            $batchID = $db->getValue("mlm_bonus_calculation_batch","count(id)");
            if($batchID){
                Log::write(date("Y-m-d H:i:s")." $bonusDate ".$bonusName." has been Calculate. Failed to calculate.\n");
                return false;
            }

            $batchID = Bonus::insertBonusCalculationBatch($bonusName, $bonusDate);

            $checkDate = $bonusDate." 23:59:59";
            $dateTime = date("Y-m-d", strtotime("+1 day ".$bonusDate));
            $batchID = $db->getNewID();
            $db->where("name", "isDailyLimitWallet");
            $db->where("value", 1);
            $checkTypeRow = $db->getOne("credit_setting", "credit_id, reference");
            if(empty($checkTypeRow)){
                return false;
            }

            $db->where("id", $checkTypeRow["credit_id"]);
            $creditType = $db->getValue("credit", "name");

            $balanceAry = Cash::getAllClientBalance('', array($creditType), $checkDate);
            $paymentAry = self::getTrdPaymentMethod($type, "trdBBIT");
            $deductCreditType = $paymentAry[$type]["payCreditType"];
            $adminCharges = $paymentAry[$type]["adminCharge"];
            $db->where("status", array("Queue", "Scheduled"), "IN");
            $db->where("created_at", $checkDate, "<=");
            $db->where("type", $type);
            $db->groupBy("client_id");
            $clientTrdQtyAry = $db->map("client_id")->get("trd_transaction", null, "client_id, SUM(total_amount - actual_amount) as amount");
            if(!$clientTrdQtyAry){
                Log::write(date("Y-m-d H:i:s")." No member.\n");
                return false;
            }

            foreach ($clientTrdQtyAry as $clientID => &$amount) {
                $amount -= Setting::setDecimal(($amount * $adminCharges/100),"");
            }

            foreach ($balanceAry as $clientID => $amountRow) {
                unset($amount);

                $amount = $amountRow[$creditType] + $clientTrdQtyAry[$clientID];
                Log::write(date("Y-m-d H:i:s") . " clientID: ".$clientID." Balance: ".$amount.".\n");
                self::updateBuySellLimit("daily", $clientID, $amount, $dateTime, "trdBBIT", $batchID);
            }
            
            Bonus::insertBonusCalculationBatch($bonusName, $bonusDate, 1);

            return true;
        }

        public function buySellConfirmation($params, $type){
        	$db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

        	$creditType = $params["creditType"];
            $quantity = trim($params["quantity"]);
            $price = trim($params["price"]);
            $dateTime = date("Y-m-d H:i:s");

            if(!$coinType){
            	$coinType = "trdBBIT";
            }

            if(!$type){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => "System Error" /* Failed to rate advertisement */, 'data'=>"");
	        }

	        if($site != "Member"){
	        	$clientID = trim($params["clientID"]);
	        }

	        if(!$clientID){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00361"][$language], 'data'=>"");
	        }

	        if(!$coinType){
	        	$errorFieldArr[] = array(
                                            'id' => 'coinTypeError',
                                            'msg' => $translations["E00862"][$language],
                                        );
	        }

	        $coinRate = General::getLatestUnitPrice($coinType);

	        // if($price <= 0){
            if($price != $coinRate){
	        	$errorFieldArr[] = array(
                                            'id' => 'priceError',
                                            'msg' => $translations["E00860"][$language],
                                        );
	        }

	        if($quantity <= 0){
	        	$errorFieldArr[] = array(
                                            'id' => 'quantityError',
                                            'msg' => $translations["E00861"][$language],
                                        );
	        }

	        $totalPayAmount = Setting::setDecimal(($price * $quantity), "");
	        $paymentAry = self::getTrdPaymentMethod($type, $coinType);
	        $deductCreditType = $paymentAry[$type]["payCreditType"];

	        if($paymentAry[$type]["chargeType"] == $deductCreditType){
	        	$adminCharges = $paymentAry[$type]["adminCharge"];
	        	$adminChargesFee = Setting::setDecimal(($totalPayAmount * $adminCharges/100),"");
	        }

	        if($type == "buy"){
                
                //check close time
                $checkDateTime = date("H:i:s");
                $closeTradeTime = Setting::$systemSetting["closeTradeTime"];
                $closeTradeTimeAry = json_decode($closeTradeTime, true);
                if(strtotime($checkDateTime) >= strtotime($closeTradeTime["startTime"]) && strtotime($checkDateTime) <= strtotime($closeTradeTime["endTime"])){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00874"][$language], 'data'=>"");
                }

                //add checking for vesting credit is no balance
	        	$db->where("name", "isSellTrdCheckWallet");
	        	$db->where("value", 1);
	        	$checkCreditID = $db->getValue("credit_setting", "credit_id");
	        	if($checkCreditID){
	        		$db->where("id", $checkCreditID);
	        		$checkTypeRow = $db->getOne("credit", "name, translation_code");

	        		$vestBalance = Cash::getBalance($clientID, $checkTypeRow["name"]);

	        		if($vestBalance <= 0){
	        			$errorMsg = str_replace("%%credit%%", $translations[$checkTypeRow["translation_code"]][$language], $translations["E00863"][$language]);
	        			$errorFieldArr[] = array(
                                            'id' => 'quantityError',
                                            'msg' => $errorMsg,
                                        );
	        		}
	        	}

                $validBuyLimit = self::checkDailyLimit($clientID, $coinType, $dateTime);
                if($quantity > $validBuyLimit){
                    $errorMsg = str_replace("%%amount%%", $validBuyLimit, $translations["E00873"][$language]);
                    $errorFieldArr[] = array(
                                        'id' => 'quantityError',
                                        'msg' => $errorMsg,
                                    );
                }

	        	$balance = Cash::getBalance($clientID, $deductCreditType);

	        	if($balance < ($totalPayAmount + $adminChargesFee)){
	        		$errorFieldArr[] = array(
                                            'id' => 'priceError',
                                            'msg' => $translations["E00266"][$language],
                                        );
	        	}
	        	$subject = "Buy Stock";
	        	$deductAmount = $totalPayAmount;
	        }else{
	        	$quantityBalance = Cash::getBalance($clientID, $deductCreditType);
	        	if($quantityBalance < $quantity){
	        		$errorFieldArr[] = array(
                                            'id' => 'quantityError',
                                            'msg' => $translations["E00266"][$language],
                                        );
	        	}

	        	$subject = "Sell Stock";
	        	$deductAmount = $quantity;
	        }

	        if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            $db->where("name", "escrowTrd");
            $db->where("type", "Internal");
            $internalID = $db->getValue("client", "id");

            $db->startTransaction();

            try{
            	//insertTA
            	$belongID = $db->getNewID();
            	if($deductAmount > 0){
            		Cash::insertTAccount($clientID, $internalID, $deductCreditType, $deductAmount, $subject, $belongID, "", $dateTime, $belongID, $clientID, "", "", "", "",$price);
            	}

            	if($adminChargesFee > 0){
            		Cash::insertTAccount($clientID, $internalID, $deductCreditType, $adminChargesFee, $subject." Transaction Fees", $belongID, "", $dateTime, $belongID, $clientID, "", "", "", "",$price);
            	}

            	//insert trd_transaction
            	$insertData = array(
            							"client_id" => $clientID,
            							"type" => $type, 
            							"coin_type" => $coinType,
            							"credit_type" => $deductCreditType,
            							"price" => $price,
            							"quantity" => $quantity,
            							"left_quantity" => $quantity,
            							"total_amount" => $totalPayAmount,
            							"admin_charge" => $adminChargesFee,
            							"status" => "Scheduled",
            							"created_at" => $dateTime,
            							"belong_id" => $belongID,
            						);
            	$db->insert("trd_transaction", $insertData);

            }catch(Exception $e){
                $db->rollback();
	            return array('status' => "error", 'code' => 2, 'statusMsg' => "System Error", 'data'=>"");
            }
            $db->commit();

            if($type == "buy"){
                self::updateBuySellLimit("buy", $clientID, $quantity, $dateTime, $coinType, $belongID);
            }

	        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00295"][$language] /* Failed to rate advertisement */, 'data'=>"");
        }

        public function matchQueue($params, $processType){
        	$db = MysqliDb::getInstance();

            $trnxID = trim($params["trnxID"]);
            $dateTime = trim($params["dateTime"]);

            if(!$trnxID){
            	Log::write(date("Y-m-d H:i:s")." - Empty transaction ID.\n");
            	return false;
            }

            if(!$dateTime){
            	$dateTime = date("Y-m-d H:i:s");
            }

            if($processType == "queue"){
                $db->where("id", $trnxID);
                $trnxRow = $db->getOne("trd_transaction", "client_id, type, coin_type, credit_type, price, quantity, left_quantity, status, belong_id");
                if($trnxRow["status"] != "Queue" ){
                    Log::write(date("Y-m-d H:i:s")." - (Queue) TrnxID: ".$trnxID." Status: ".$trnxRow["status"]." Invalid Status.\n");
                    return false;
                }
            }else{
                $db->where("id", $trnxID);
                $trnxRow = $db->getOne("trd_transaction", "client_id, type, coin_type, credit_type, price, quantity, left_quantity, status, belong_id");

                if($trnxRow["status"] != "Scheduled" ){
                    Log::write(date("Y-m-d H:i:s")." - TrnxID: ".$trnxID." Status: ".$trnxRow["status"]." Invalid Status.\n");
                    return false;
                }
            }
            
            $clientID = $trnxRow["client_id"];
        	$type = $trnxRow["type"];
        	$coinType = $trnxRow["coin_type"];
        	$price = $trnxRow["price"];
        	$leftQuantity = $trnxRow["left_quantity"];

    		$db->startTransaction();

            $processTrnxData = array(
                                    "status" => "Processing",
                                    "updated_at" => $dateTime,
                                );
            $db->where("id",$trnxID);
            $db->update("trd_transaction",$processTrnxData);

            Log::write(date("Y-m-d H:i:s")." Processing Type: ".$type." ID: ".$trnxID." clientID: ".$clientID." quantity: ".$leftQuantity."\n");
            $remainBuyLimit = self::checkDailyLimit($clientID, $coinType, $dateTime);

            if($type == "buy"){
                $queueTable = "trd_sell_queue";
                $insertTable = "trd_buy_queue";

                //find direct upline
                $db->where("client_id", $clientID);
                $networkIDAry = $db->map("upline_id")->get("tree_sponsor", null, "upline_id");

            }else{
                $queueTable = "trd_buy_queue";
                $insertTable = "trd_sell_queue";

                //find direct downline
                $db->where("upline_id", $clientID);
                $networkIDAry = $db->map("client_id")->get("tree_sponsor", null, "client_id");
            }

            //direct network 1st
            if($networkIDAry){
                if($type == "buy" || $remainBuyLimit > 0){
                    $db->where("client_id", $networkIDAry, "IN");
                    if($type == "buy"){
                        $db->where("coin_type", $coinType);
                        $db->where("status", 0);
                        $db->where("price", $price, "<=");
                        $db->orderBy("price", "ASC");
                    }else{
                        $db->where("coin_type",$coinType);
                        $db->where("status", 0);
                        $db->where("price",$price,">=");
                        $db->orderBy("price","DESC");
                    }
                    $db->orderBy("id", "ASC");
                    $queueRes = $db->setQueryOption("FOR UPDATE")->get($queueTable." FORCE INDEX(getQueue)", null, "id, client_id, credit_type, quantity, price, trd_transaction_id");
                }

                try{
                    foreach ($queueRes as $queueRow) {
                        unset($matchParams);
                        unset($queueInsertData);

                        $matchQuantity = 0;
                        $matchPrice = 0;
                        $sellerTrnxID = 0;
                        $buyerTrnxID = 0;
                        $queueQuantity = 0;

                        if($leftQuantity <= 0) continue;

                        $matchPrice = $queueRow["price"];
                        $queueQuantity = $queueRow["quantity"];

                        if($queueQuantity < $leftQuantity){
                            $matchQuantity = $queueQuantity;
                        }else{
                            $matchQuantity = $leftQuantity;
                        }
                        $leftQuantity -= $matchQuantity;
                        $queueQuantity -= $matchQuantity;

                        if($matchQuantity > 0){
                            if($type == "buy"){
                                $sellerID = $queueRow["client_id"];
                                $sellerTrnxID = $queueRow["trd_transaction_id"];
                                $buyerID = $clientID;
                                $buyerTrnxID = $trnxID;
                            }else{
                                $sellerID = $clientID;
                                $sellerTrnxID = $trnxID;
                                $buyerID = $queueRow["client_id"];
                                $buyerTrnxID = $queueRow["trd_transaction_id"];

                            }
                            self::matchQueuePayout($sellerID, $buyerID, $sellerTrnxID, $buyerTrnxID, $coinType, $matchPrice, $matchQuantity, $dateTime);
                        }

                        if($queueQuantity > 0){
                            //update queue
                            $db->where("id", $queueRow["id"]);
                            $db->update($queueTable, array("quantity" => $queueQuantity));
                        }else{
                            $db->where("id", $queueRow["id"]);
                            $db->delete($queueTable);

                            $queueInsertData["status"] = "Completed";
                        }

                        $queueInsertData["left_quantity"] = $queueQuantity;
                        $queueInsertData["updated_at"] = $dateTime;
                        $db->where("id", $queueRow["trd_transaction_id"]);
                        $db->update("trd_transaction", $queueInsertData);

                        if($type == "sell" && $queueInsertData["status"] == "Completed"){
                            self::refundTradeAmount($queueRow["trd_transaction_id"], $dateTime,"","refund");
                        }
                    }
                }catch(Exception $e){
                    Log::write(date("Y-m-d H:i:s")." Rollback: ".$type." ID: ".$trnxID." clientID: ".$clientID." quantity: ".$leftQuantity."\n");
                    echo $db->getLastQuery()."\n";
                    echo $db->getLastError()."\n";

                    $db->rollback();
                    return false;
                }
            }

            //public
            if($leftQuantity > 0){
                if($type == "buy" || $remainBuyLimit > 0){
                    $db->where("client_id",$clientID,"!=");
                    if($type == "buy"){
                        $db->where("coin_type", $coinType);
                        $db->where("status", 0);
                        $db->where("price", $price, "<=");
                        $db->orderBy("price", "ASC");
                    }else{
                        $db->where("coin_type",$coinType);
                        $db->where("status", 0);
                        $db->where("price",$price,">=");
                        $db->orderBy("price","DESC");
                    }

                    $db->orderBy("id", "ASC");
                    $queueRes = $db->setQueryOption("FOR UPDATE")->get($queueTable." FORCE INDEX(getQueue)", null, "id, client_id, credit_type, quantity, price, trd_transaction_id");
                }
            }
            
    		try{

    			foreach ($queueRes as $queueRow) {
    				unset($matchParams);
    				unset($queueInsertData);

    				$matchQuantity = 0;
    				$matchPrice = 0;
    				$sellerTrnxID = 0;
    				$buyerTrnxID = 0;
    				$queueQuantity = 0;

                    if($networkIDAry[$queueRow["client_id"]]) continue;

    				if($leftQuantity <= 0) continue;

    				$matchPrice = $queueRow["price"];
    				$queueQuantity = $queueRow["quantity"];

    				if($queueQuantity < $leftQuantity){
    					$matchQuantity = $queueQuantity;
    				}else{
    					$matchQuantity = $leftQuantity;
    				}
    				$leftQuantity -= $matchQuantity;
    				$queueQuantity -= $matchQuantity;

    				if($matchQuantity > 0){
    					if($type == "buy"){
    						$sellerID = $queueRow["client_id"];
    						$sellerTrnxID = $queueRow["trd_transaction_id"];
    						$buyerID = $clientID;
    						$buyerTrnxID = $trnxID;
    					}else{
    						$sellerID = $clientID;
    						$sellerTrnxID = $trnxID;
    						$buyerID = $queueRow["client_id"];
    						$buyerTrnxID = $queueRow["trd_transaction_id"];

    					}
    					self::matchQueuePayout($sellerID, $buyerID, $sellerTrnxID, $buyerTrnxID, $coinType, $matchPrice, $matchQuantity, $dateTime);
    				}

    				if($queueQuantity > 0){
    					//update queue
    					$db->where("id", $queueRow["id"]);
    					$db->update($queueTable, array("quantity" => $queueQuantity));
    				}else{
    					$db->where("id", $queueRow["id"]);
    					$db->delete($queueTable);

    					$queueInsertData["status"] = "Completed";
    				}

    				$queueInsertData["left_quantity"] = $queueQuantity;
    				$queueInsertData["updated_at"] = $dateTime;
    				$db->where("id", $queueRow["trd_transaction_id"]);
    				$db->update("trd_transaction", $queueInsertData);

    				if($type == "sell" && $queueInsertData["status"] == "Completed"){
    					self::refundTradeAmount($queueRow["trd_transaction_id"], $dateTime,"","refund");
    				}
    			}

                if($processType == "queue"){
                    if($leftQuantity > 0){
                        //insertQueue
                        unset($insertQueueData);
                        unset($queueInsertData);
                        $updateQueueData = array(
                                                "status" => ($remainBuyLimit > 0 ? 0 : 1),
                                                "quantity" => $leftQuantity,
                                            );
                        $db->where("trd_transaction_id", $trnxID);
                        $db->update("trd_sell_queue", $updateQueueData);
                    }else{
                        $db->where("trd_transaction_id", $trnxID);
                        $db->delete("trd_sell_queue");
                    }

                }else{

                    if($leftQuantity > 0){
                        //insertQueue

                        $insertQueueData = array(
                                                "client_id" => $clientID,
                                                "coin_type" => $coinType,
                                                "credit_type" => $trnxRow["credit_type"],
                                                "quantity" => $leftQuantity,
                                                "price" => $price,
                                                "trd_transaction_id" => $trnxID,
                                                "status" => ($remainBuyLimit > 0 ? 0 : 1),
                                            );
                        $db->insert($insertTable, $insertQueueData);
                    }
                }

                if($leftQuantity != $trnxRow["left_quantity"]){
                    $processTrnxData = array(
                                                "left_quantity" => $leftQuantity,
                                                "status" => ($leftQuantity > 0 ? "Queue" : "Completed"),
                                                "updated_at" => $dateTime,
                                            );
                }else{
                    $processTrnxData = array(
                                                "status" => "Queue",
                                                "updated_at" => $dateTime,
                                            );
                }
                $db->where("id",$trnxID);
                $db->update("trd_transaction",$processTrnxData);

                if($type == "buy" && $leftQuantity <= 0){
                    self::refundTradeAmount($trnxID, $dateTime, "", "refund");
                }
	    		
    		}catch(Exception $e){
                Log::write(date("Y-m-d H:i:s")." Rollback: ".$type." ID: ".$trnxID." clientID: ".$clientID." quantity: ".$leftQuantity."\n");
                echo $db->getLastQuery()."\n";
                echo $db->getLastError()."\n";

    			$db->rollback();
    			return false;
    		}

    		$db->commit();
        	
        	return true;
        }

        function matchQueuePayout($sellerID, $buyerID, $sellerTrnxID, $buyerTrnxID, $coinType, $price, $quantity, $dateTime){
        	$db = MysqliDb::getInstance();
        	if($price <= 0) return false;
        	if($quantity <= 0) return false;
        	if(!$dateTime) $dateTime = date("Y-m-d H:i:s");

        	$db->where("name", "escrowTrd");
            $db->where("type", "Internal");
            $internalID = $db->getValue("client", "id");
            $belongID = $db->getNewID();
        	$buyerPayAmount = Setting::setDecimal($quantity, "");
        	$sellerPayAmount = Setting::setDecimal(($quantity * $price), "");

        	$db->where("id", array($sellerTrnxID, $buyerTrnxID), "IN");
        	$batchIDAry = $db->map("id")->get("trd_transaction", null, "id, belong_id");


	        $paymentAry = self::getTrdPaymentMethod("", $coinType);
        	if($buyerPayAmount > 0){
        		//insertTA
        		$buyCreditType = $paymentAry["buy"]["receiveCreditType"];
            	Cash::insertTAccount($internalID, $buyerID, $buyCreditType, $buyerPayAmount, "Matched Buy Stock", $belongID, $batchIDAry[$sellerTrnxID], $dateTime, $batchIDAry[$buyerTrnxID], $buyerID, "", "", "", "",$price);

        		if($paymentAry["buy"]["chargeType"] == $buyCreditType){
		        	$buyCharges = $paymentAry["buy"]["adminCharge"];
        			$buyChargesFee = Setting::setDecimal(($buyerPayAmount * $buyCharges/100), "");

        			if($buyChargesFee > 0){
            			Cash::insertTAccount($buyerID, $internalID, $buyCreditType, $buyChargesFee, "Matched Buy Stock Transaction Fee", $belongID, $batchIDAry[$sellerTrnxID], $dateTime, $batchIDAry[$buyerTrnxID], $buyerID, "", "", "", "",$price);
        			}

    				$updateTrnxData["admin_charge"] = $db->inc($buyChargesFee);
        			
		        }
		        $updateTrnxData["actual_amount"] = $db->inc($sellerPayAmount);
                $updateTrnxData["updated_at"] = $dateTime;
		        $db->where("id", $buyerTrnxID);
				$db->update("trd_transaction", $updateTrnxData);

        	}

        	unset($updateTrnxData);
        	if($sellerPayAmount > 0){
        		$sellCreditType = $paymentAry["sell"]["receiveCreditType"];
            	Cash::insertTAccount($internalID, $sellerID, $sellCreditType, $sellerPayAmount, "Matched Sell Stock", $belongID, $batchIDAry[$buyerTrnxID], $dateTime, $batchIDAry[$sellerTrnxID], $sellerID, "", "", "", "",$price);
        		
        		if($paymentAry["sell"]["chargeType"] == $sellCreditType){
		        	$sellCharges = $paymentAry["sell"]["adminCharge"];
        			$sellChargesFee = Setting::setDecimal(($sellerPayAmount * $sellCharges/100), "");
        			if($sellChargesFee > 0){

            			Cash::insertTAccount($sellerID, $internalID, $sellCreditType, $sellChargesFee, "Matched Sell Stock Transaction Fee", $belongID, $batchIDAry[$buyerTrnxID], $dateTime, $batchIDAry[$sellerTrnxID], $sellerID, "", "", "", "",$price);
        				
        				$updateTrnxData["admin_charge"] = $db->inc($sellChargesFee);
        			}

        			$sellerBonusValue = $sellChargesFee;
        			$buyerBonusValue = $sellChargesFee;
		        }

		        $updateTrnxData["actual_amount"] = $db->inc($sellerPayAmount);
                $updateTrnxData["updated_at"] = $dateTime;

		        $db->where("id", $sellerTrnxID);
        		$db->update("trd_transaction", $updateTrnxData);
        	}

        	$insertData = array(
        							"buy_transaction_id" => $buyerTrnxID,
        							"sell_transaction_id" => $sellerTrnxID,
        							"coin_type" => $coinType,
        							"price" => $price,
        							"quantity" => $quantity,
        							"belong_id" => $belongID,
        							"created_at" => $dateTime,
        						);

        	$db->insert("trd_match_transaction", $insertData);

        	if($sellerBonusValue > 0){
        		$bonusInData['clientID']    = $sellerID;
	            $bonusInData['type']        = "sell";
	            $bonusInData['belongID']    = $belongID;
	            $bonusInData['batchID']     = $batchIDAry[$sellerTrnxID];
	            $bonusInData['bonusValue']  = $sellerBonusValue;
	            $bonusInData['dateTime']    = $dateTime;
	            $bonusInData['processed']   = 0;
            	$insertBonusResult = Bonus::insertTrdBonusValue($bonusInData);
        	}

        	if($buyerBonusValue > 0){
        		$bonusInData['clientID']    = $buyerID;
	            $bonusInData['type']        = "buy";
	            $bonusInData['belongID']    = $belongID;
	            $bonusInData['batchID']     = $batchIDAry[$buyerTrnxID];
	            $bonusInData['bonusValue']  = $buyerBonusValue;
	            $bonusInData['dateTime']    = $dateTime;
	            $bonusInData['processed']   = 0;
            	$insertBonusResult = Bonus::insertTrdBonusValue($bonusInData);
        	}

        	Custom::autoBuySellStock($buyerID, $quantity, $dateTime);
            // self::updateBuySellLimit("match", $buyerID, $dateTime, $coinType, $belongID);
            Custom::upgradeGoldmineRank($buyerID, $dateTime);
            $db->where("id", $buyerID);
            $sponsorID = $db->getValue("client", "sponsor_id");
            Custom::upgradeGoldmineRank($sponsorID, $dateTime);

            self::updateBuySellVolume("match", $buyerID, $quantity, $dateTime, $coinType, $belongID);
            self::graphDataCache($quantity, $price, $coinType);
        	return true;
        }

        function refundTradeAmount($trnxID, $dateTime, $quantityReduce, $actionType){
        	$db = MysqliDb::getInstance();

        	if(!$trnxID){
        		return false;
        	}

        	if(!$dateTime) $dateTime = date("Y-m-d H:i:s");

        	$db->where("id", $trnxID);
        	$trdTrnxRow = $db->getOne("trd_transaction", "id, client_id, type, coin_type, credit_type, price, left_quantity, total_amount, actual_amount, admin_charge, belong_id");
        	if(empty($trdTrnxRow)){
        		return false;
        	}

        	$db->where("name", "escrowTrd");
            $db->where("type", "Internal");
            $internalID = $db->getValue("client", "id");


        	$clientID = $trdTrnxRow["client_id"];
        	$type = $trdTrnxRow["type"];
        	$adminCharge = $trdTrnxRow["admin_charge"];
        	$totalAmount = $trdTrnxRow["total_amount"];
        	$actualAmount = $trdTrnxRow["actual_amount"];
        	$coinType = $trdTrnxRow["coin_type"];
        	$batchID = $trdTrnxRow["belong_id"];
        	$price = $trdTrnxRow["price"];
        	$leftQuantity = $trdTrnxRow["left_quantity"];

	        $paymentAry = self::getTrdPaymentMethod($type, $coinType);

	        if($quantityReduce > 0){
	        	if($type == "buy"){
	        		$payCreditType = $paymentAry[$type]["payCreditType"];
					$refundAmount = Setting::setDecimal(($quantityReduce * $price), "");
	        	}else{
	        		$payCreditType = $paymentAry[$type]["payCreditType"];
					$refundAmount = $quantityReduce;
	        	}
	        }else{
	        	if($type == "buy"){
	        		$payCreditType = $paymentAry[$type]["payCreditType"];
	        		if($totalAmount > $actualAmount){
	        			$refundAmount = Setting::setDecimal(($totalAmount - $actualAmount), "");
	        		}
	        	}else{
	        		$payCreditType = $paymentAry[$type]["payCreditType"];
        			$refundAmount = Setting::setDecimal(($leftQuantity), "");
	        	}
	        }

	        if($refundAmount > 0 && ($paymentAry[$type]["chargeType"] == $payCreditType)){
				$refundCharges = $paymentAry[$type]["adminCharge"];
				$refundChargesFee = Setting::setDecimal(($refundAmount * $refundCharges/100), "");
			}

	        $belongID = $db->getNewID();

    		if($refundAmount > 0){
    			$action = ($actionType == "cancel" ? "Cancel" : "Refund");
    			$subjectType = ($type == "buy" ? " Stock Buy" : " Stock Sell");
    			$subject = $action.$subjectType;
        		Cash::insertTAccount($internalID, $clientID, $payCreditType, $refundAmount, $subject, $belongID, "", $dateTime, $batchID, $clientID, "", "", "", "",$price);
    		}

    		if($refundChargesFee > 0){
    			$action2 = ($actionType == "cancel" ? "Cancel" : "Refund");
    			$subjectType2 = ($type == "buy" ? " Stock Transaction Fee Buy" : " Stock Transaction Fee Sell");
    			$subject2 = $action2.$subjectType2;
        		Cash::insertTAccount($internalID, $clientID, $payCreditType, $refundChargesFee, $subject2, $belongID, "", $dateTime, $batchID, $clientID, "", "", "", "",$price);
    		}

            if($type == "buy" && $leftQuantity > 0){
                self::updateBuySellLimit("add", $clientID, $leftQuantity, $dateTime, $coinType, $belongID);
            }

        	return true;
        }

        public function reduceBuySell($params){
        	$db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            $quantityReduce = $params["quantityReduce"];
            $trnxID = $params["trnxID"];

            $dateTime = date("Y-m-d H:i:s");

            if($site != "Member"){
            	$clientID = $params["clientID"];
            }

            if(!$clientID){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00361"][$language], 'data'=>"");
            }

            if(!$trnxID){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00865"][$language], 'data'=>"");
            }

            $db->where("id", $trnxID);
            $trnxRow = $db->getOne("trd_transaction", "id, coin_type, type, price, quantity, left_quantity, status, updated_at");

            if(empty($trnxRow)){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00865"][$language], 'data'=>"");
            }

            if(!in_array($trnxRow["status"], array("Queue"))){
            	$errorMsg = str_replace("%%status%%", General::getTranslationByName($trnxRow["status"]), $translations["E00866"][$language]);
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $errorMsg, 'data'=>"");
            }

            if($trnxRow["left_quantity"] <= 0){
	            $errorMsg = str_replace("%%status%%", General::getTranslationByName("Completed"), $translations["E00866"][$language]);
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $errorMsg, 'data'=>"");
	        }

	        if($quantityReduce <= 0){
            	$errorFieldArr[] = array(
                                            'id' => 'quantityReduceError',
                                            'msg' => $translations["E00861"][$language],
                                        );
            }else if($quantityReduce > $trnxRow["left_quantity"]){
            	$errorFieldArr[] = array(
                                            'id' => 'quantityReduceError',
                                            'msg' => $translations["E00867"][$language],
                                        );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

	        $type = $trnxRow["type"];
	        if($type == "buy"){
	        	$queueTable = "trd_buy_queue";
	        }else{
	        	$queueTable = "trd_sell_queue";
	        }

	        $db->startTransaction();
	        $db->where("trd_transaction_id", $trnxID);
    		$queueRow = $db->setQueryOption("FOR UPDATE")->getOne($queueTable, "id, client_id, credit_type, quantity, price, trd_transaction_id");

    		if(empty($queueRow)){
    			$db->rollback();
            	$db->commit();
    			$errorMsg = str_replace("%%status%%", General::getTranslationByName("Completed"), $translations["E00866"][$language]);
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $errorMsg, 'data'=>"");
    		}

            try{
            	$newLeftQuantity = Setting::setDecimal(($trnxRow["left_quantity"] - $quantityReduce), "");

            	if($newLeftQuantity <= 0){
            		$updateData["status"] = "Completed";
            		//delete queue
            		$db->where("trd_transaction_id", $trnxID);
            		$db->delete($queueTable);
            	}else{
            		$db->where("trd_transaction_id", $trnxID);
            		$db->update($queueTable, array("quantity" => $newLeftQuantity));
            	}

            	self::refundTradeAmount($trnxID, $dateTime, $quantityReduce, "reduce");

        		$refundAmount = Setting::setDecimal(($quantityReduce * $trnxRow["price"]), "");

            	$updateData["reduce_quantity"] = $db->inc($quantityReduce);
            	$updateData["left_quantity"] = $newLeftQuantity;
            	$updateData["actual_amount"] = $db->inc($refundAmount);
            	$updateData["updated_at"] = $dateTime;
            	$db->where("id", $trnxID);
            	$db->update("trd_transaction", $updateData);
            }catch(Exception $e){
            	$db->rollback();
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00868"][$language], 'data'=>"");
            }

            $db->commit();


	        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00289"][$language], 'data'=>"");
        }

        public function cancelBuySell($params){
        	$db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            $trnxID = $params["trnxID"];

            $dateTime = date("Y-m-d H:i:s");

            if($site != "Member"){
            	$clientID = $params["clientID"];
            }

            if(!$clientID){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00361"][$language], 'data'=>"");
            }

            if(!$trnxID){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00865"][$language], 'data'=>"");
            }

            $db->where("id", $trnxID);
            $trnxRow = $db->getOne("trd_transaction", "id, type, price, quantity, left_quantity, status, updated_at");

            if(empty($trnxRow)){
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00865"][$language], 'data'=>"");
            }

            if(!in_array($trnxRow["status"], array("Queue"))){
            	$errorMsg = str_replace("%%status%%", General::getTranslationByName($trnxRow["status"]), $translations["E00866"][$language]);
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $errorMsg, 'data'=>"");
            }

            if($trnxRow["left_quantity"] <= 0){
	            $errorMsg = str_replace("%%status%%", General::getTranslationByName("Completed"), $translations["E00866"][$language]);
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $errorMsg, 'data'=>"");
	        }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

	        $type = $trnxRow["type"];
	        if($type == "buy"){
	        	$queueTable = "trd_buy_queue";
	        }else{
	        	$queueTable = "trd_sell_queue";
	        }

	        $db->startTransaction();
	        $db->where("trd_transaction_id", $trnxID);
    		$queueRow = $db->setQueryOption("FOR UPDATE")->getOne($queueTable, "id, client_id, credit_type, quantity, price, trd_transaction_id");

    		if(empty($queueRow)){
    			$db->rollback();
            	$db->commit();
    			$errorMsg = str_replace("%%status%%", General::getTranslationByName("Completed"), $translations["E00866"][$language]);
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $errorMsg, 'data'=>"");
    		}

            try{
        		$updateData["status"] = "Removed";
                $updateData["updated_at"] = $dateTime;
                $db->where("id", $trnxID);
                $db->update("trd_transaction", $updateData);
                
        		//delete queue
        		$db->where("trd_transaction_id", $trnxID);
        		$db->delete($queueTable);

            	self::refundTradeAmount($trnxID, $dateTime, "", "cancel");

        		// $refundAmount = Setting::setDecimal(($trnxRow["left_quantity"] * $trnxRow["price"]), "");
            	// $updateData["reduce_quantity"] = $db->inc($quantityReduce);
            	// $updateData["left_quantity"] = $newLeftQuantity;
            	
            }catch(Exception $e){
            	$db->rollback();
	            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00868"][$language], 'data'=>"");
            }

            $db->commit();

	        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00290"][$language], 'data'=>"");
        }

        public function getOrderHistory($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $db->userID;
            $site = $db->userType;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $usernameSearchType = $params["usernameSearchType"];
            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $coinAry = self::getTrdCoin();
            foreach ($coinAry as $coinRow) {
                $coinDataAry[$coinRow["name"]] = $coinRow;
            }

            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);

                switch($dataName) {

                    case 'dateRange':
                            // Set db column here
                            $columnName = 'DATE(created_at)';

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

                    case 'updatedDate':
                            // Set db column here
                            $columnName = 'DATE(updated_at)';

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

                    case 'type':
                        // Set db column here
                        if($dataValue){
                            $db->where("type", $dataValue);
                            $searchTrdType = $dataValue;
                        }
                        break;

                    case 'status':
                        // Set db column here
                        if($dataValue){
                            if($dataValue == "Inactive"){
                                if($searchTrdType == "buy"){
                                    $db->resetState();
                                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
                                }else{
                                    $searchTrdIDAry = $db->subQuery();
                                    $searchTrdIDAry->where("status","1");
                                    $searchTrdIDAry->get("trd_sell_queue",null, "trd_transaction_id");
                                    if(empty($searchTrdIDAry)){
                                        $db->resetState();
                                        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
                                    }
                                    $db->where("id", $searchTrdIDAry, "IN");
                                }
                            }else if($dataValue == "Queue"){
                                if($searchTrdType != "buy"){
                                    $searchTrdIDAry = $db->subQuery();
                                    $searchTrdIDAry->where("status","1");
                                    $searchTrdIDAry->get("trd_sell_queue", null, "trd_transaction_id");
                                    if(empty($searchTrdIDAry)){
                                        $db->resetState();
                                        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
                                    }
                                    $db->where("id", $searchTrdIDAry, "NOT IN");
                                }
                                $db->where("status", $dataValue);
                            }else{
                                $db->where("status", $dataValue);
                            }
                            $isStatusSearchFilter = 1;
                        }
                        break;

                    case 'leaderUsername':
                        $clientID = $db->subQuery();
                        $clientID->where('username', $dataValue);
                        $clientID->getOne('client', "id");

                        $downlines = Tree::getSponsorTreeDownlines($clientID);

                        if (empty($downlines))
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

                        $db->where('client_id', $downlines, "IN");
                        break;
                }
            }

            if($site == "Member"){
                $db->where("client_id", $clientID);
                if(!$isStatusSearchFilter) $db->where("status", array("Completed", "Removed"), "IN");
            }

            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $trnxRes = $db->get("trd_transaction", $limit, "id, client_id, type, coin_type, price, quantity, left_quantity, reduce_quantity, total_amount, actual_amount, admin_charge, status, created_at, updated_at");
            if (empty($trnxRes))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

            foreach ($trnxRes as $trnxRow) {
                if($trnxRow["type"] == "sell" && $trnxRow["status"] == "Queue") $trdTrnxIDAry[$trnxRow["id"]] = $trnxRow["id"];
                if($site != "Member") $clientIDAry[$trnxRow["client_id"]] = $trnxRow["client_id"];
            }

            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, member_id");
            }

            if($trdTrnxIDAry){
                $db->where("trd_transaction_id", $trdTrnxIDAry, "IN");
                $db->where("status", "1");
                $inactiveQueueAry = $db->map("trd_transaction_id")->get("trd_sell_queue", null, "trd_transaction_id");
            }

            foreach ($trnxRes as $trnxRow) {
                unset($dataRow);
                $dataRow["trnxID"] = $trnxRow["id"];
                if($site != "Member"){
                    $dataRow["memberID"] = $clientDataAry[$trnxRow["client_id"]]["member_id"];
                    $dataRow["username"] = $clientDataAry[$trnxRow["client_id"]]["username"];
                }

                $dataRow["createdAt"] = date($dateTimeFormat, strtotime($trnxRow['created_at']));
                $dataRow["updatedAt"] = date($dateTimeFormat, strtotime($trnxRow['updated_at']));
                $dataRow["type"] = $trnxRow["type"];
                $dataRow["typeDisplay"] = General::getTranslationByName($trnxRow["type"]);
                $dataRow["coinType"] = $trnxRow["coin_type"];
                $dataRow["coinTypeDisplay"] = $coinDataAry[$trnxRow["coin_type"]]["display"];
                $dataRow["price"] = Setting::setDecimal($trnxRow["price"], "");
                $dataRow["quantity"] = Setting::setDecimal($trnxRow["quantity"], "");
                $dataRow["leftQuantity"] = Setting::setDecimal($trnxRow["left_quantity"], "");
                $dataRow["reduceQuantity"] = Setting::setDecimal($trnxRow["reduce_quantity"], "");
                $dataRow["matchQuantity"] = Setting::setDecimal(($trnxRow["quantity"] - $trnxRow["left_quantity"]), "");
                $dataRow["totalAmount"] = Setting::setDecimal($trnxRow["total_amount"], "");
                $dataRow["actualAmount"] = Setting::setDecimal($trnxRow["actual_amount"], "");
                $dataRow["actualAmountPerUnit"] = Setting::setDecimal(($trnxRow["actual_amount"] / $dataRow["matchQuantity"]), "");
                $dataRow["adminCharge"] = Setting::setDecimal($trnxRow["admin_charge"], "");
                $dataRow["status"] = $trnxRow["status"];
                $dataRow["statusDisplay"] = General::getTranslationByName($trnxRow["status"]);

                $dataRow["isInactive"] = $inactiveQueueAry[$trnxRow["id"]];
                if($dataRow["isInactive"]){
                    $dataRow["status"] = "Inactive";
                    $dataRow["statusDisplay"] = General::getTranslationByName($dataRow["status"]);
                }

                if($trnxRow["status"] == "Queue"){
                    switch ($trnxRow["type"]) {
                        case 'buy':
                            $dataRow["displayReduceBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isBuyReduce"];
                            $dataRow["displayCancelBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isBuyCancel"];
                            break;
                        
                        case 'sell':
                            $dataRow["displayReduceBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isSellReduce"];
                            $dataRow["displayCancelBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isSellCancel"];
                            break;
                    } 
                }
                
                $trnxList[] = $dataRow;

                $tblQty += $dataRow['quantity'] ? $dataRow['quantity'] : '0';
                $tblActualAmt += $dataRow['actualAmount'] ? $dataRow['actualAmount'] : '0';
                $tblMatchQty += $dataRow['matchQuantity'] ? $dataRow['matchQuantity'] : '0';
                $tblTrxFees += $dataRow['adminCharge'] ? $dataRow['adminCharge'] : '0';
            }

            $tblTotalList['quantity'] = Setting::setDecimal($tblQty);
            $tblTotalList['actualAmount'] = Setting::setDecimal($tblActualAmt);
            $tblTotalList['matchQuantity'] = Setting::setDecimal($tblMatchQty);
            $tblTotalList['adminCharge'] = Setting::setDecimal($tblTrxFees);

            unset($belongAry);
            unset($matchAry);

            $copyDb->groupBy("type");
            $grandTotalData = $copyDb->get("trd_transaction", null, "count(id) as record, type, SUM(quantity) as quantity, SUM(quantity - left_quantity) as matchQuantity, SUM(actual_amount) as actualAmount, SUM(admin_charge) as adminCharge");
            foreach ($grandTotalData as $grandTotal) {
                $totalRecord += $grandTotal["record"];
                if($grandTotal["type"] == "buy") {
                    $buyQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
                    $buyMatchQty += ($grandTotal["matchQuantity"] ? $grandTotal["matchQuantity"] : '0');
                    $buyActualAmt += ($grandTotal["actualAmount"] ? $grandTotal["actualAmount"] : '0');
                    $buyTrxFees += ($grandTotal['adminCharge'] ? $grandTotal['adminCharge'] : '0');
                } else {
                    $sellQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
                    $sellMatchQty += ($grandTotal["matchQuantity"] ? $grandTotal["matchQuantity"] : '0');
                    $sellActualAmt += ($grandTotal["actualAmount"] ? $grandTotal["actualAmount"] : '0');
                    $sellTrxFees += ($grandTotal['adminCharge'] ? $grandTotal['adminCharge'] : '0');
                }
            }

            $grandTotalList['buy']['quantity'] = Setting::setDecimal($buyQty);
            $grandTotalList['buy']['matchQuantity'] = Setting::setDecimal($buyMatchQty);
            $grandTotalList['buy']['actualAmount'] = Setting::setDecimal($buyActualAmt);
            $grandTotalList['buy']['adminCharge'] = Setting::setDecimal($buyTrxFees);
            $grandTotalList['sell']['quantity'] = Setting::setDecimal($sellQty);
            $grandTotalList['sell']['matchQuantity'] = Setting::setDecimal($sellMatchQty);
            $grandTotalList['sell']['actualAmount'] = Setting::setDecimal($sellActualAmt);
            $grandTotalList['sell']['adminCharge'] = Setting::setDecimal($sellTrxFees);

            $data["trnxList"] = $trnxList;
            $data['tblTotalList'] = $tblTotalList;
            $data['grandTotalList'] = $grandTotalList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00666"][$language], 'data'=> $data);
        }

        public function getOpenOrdersListing($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $usernameSearchType = $params["usernameSearchType"];
            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $coinAry = self::getTrdCoin();
            foreach ($coinAry as $coinRow) {
                $coinDataAry[$coinRow["name"]] = $coinRow;
            }

            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);

                switch($dataName) {

                    case 'dateRange':
                            // Set db column here
                            $columnName = 'DATE(created_at)';

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

                    case 'type':
                        // Set db column here
                        $db->where("type", $dataValue);
                        break;
                }
            }

            if($site == "Member"){
                $db->where("client_id", $clientID);
            }
            $db->where("status", "Queue");
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $trnxRes = $db->get("trd_transaction", $limit, "id, client_id, type, coin_type, price, quantity, left_quantity, reduce_quantity, total_amount, actual_amount, admin_charge, status, created_at");
            foreach ($trnxRes as $trnxRow) {
                if($site != "Member") $clientIDAry[$bonusInRow["client_id"]] = $bonusInRow["client_id"];
                if($trnxRow["type"]) $trnxIDAry[$trnxRow["id"]] = $trnxRow["id"];
            }
            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, member_id");
            }

            if($trnxIDAry){
                $db->where("trd_transaction_id", $trnxIDAry, "IN");
                $queueDataAry = $db->map("trd_transaction_id")->get("trd_sell_queue", null, "trd_transaction_id, status");
            }

            foreach ($trnxRes as $trnxRow) {
                unset($dataRow);

                if($site != "Member"){
                    $dataRow["memberID"] = $clientDataAry[$trnxRow["client_id"]]["member_id"];
                    $dataRow["username"] = $clientDataAry[$trnxRow["client_id"]]["username"];
                }

                $dataRow["trnxID"] = $trnxRow["id"];
                $dataRow["createdAt"] = date($dateTimeFormat, strtotime($trnxRow['created_at']));
                $dataRow["type"] = $trnxRow["type"];
                $dataRow["typeDisplay"] = General::getTranslationByName($trnxRow["type"]);
                $dataRow["coinType"] = $trnxRow["coin_type"];
                $dataRow["coinTypeDisplay"] = $coinDataAry[$trnxRow["coin_type"]]["display"];
                $dataRow["price"] = Setting::setDecimal($trnxRow["price"], "");
                $dataRow["quantity"] = Setting::setDecimal($trnxRow["quantity"], "");
                $dataRow["leftQuantity"] = Setting::setDecimal($trnxRow["left_quantity"], "");
                $dataRow["matchQuantity"] = Setting::setDecimal(($trnxRow["quantity"] - $trnxRow["left_quantity"]), "");
                $dataRow["reduceQuantity"] = Setting::setDecimal($trnxRow["reduce_quantity"], "");
                $dataRow["totalAmount"] = Setting::setDecimal($trnxRow["total_amount"], "");
                $dataRow["actualAmount"] = Setting::setDecimal($trnxRow["actual_amount"], "");
                $dataRow["adminCharge"] = Setting::setDecimal($trnxRow["admin_charge"], "");
                $dataRow["status"] = $trnxRow["status"];
                $dataRow["statusDisplay"] = General::getTranslationByName($trnxRow["status"]);
                $dataRow["isInactive"] = $queueDataAry[$trnxRow["id"]];

                if($dataRow["isInactive"] == 1){
                    $dataRow["status"] = "Inactive";
                    $dataRow["statusDisplay"] = General::getTranslationByName($dataRow["status"]);
                }

                switch ($trnxRow["type"]) {
                    case 'buy':
                        $dataRow["displayReduceBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isBuyReduce"];
                        $dataRow["displayCancelBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isBuyCancel"];
                        break;
                    
                    case 'sell':
                        $dataRow["displayReduceBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isSellReduce"];
                        $dataRow["displayCancelBtn"] = $coinDataAry[$trnxRow["coin_type"]]["isSellCancel"];
                        break;
                }

                $trnxList[] = $dataRow;
            }

            $data["trnxList"] = $trnxList;
            $totalRecord = $copyDb->getValue("trd_transaction", "count(*)");
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00666"][$language], 'data'=> $data);
        }

        public function getTradeHistory($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $usernameSearchType = $params["usernameSearchType"];
            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $decimalPlaces = Setting::$systemSetting['internalDecimalFormat'];

            $coinAry = self::getTrdCoin();
            foreach ($coinAry as $coinRow) {
                $coinDataAry[$coinRow["name"]] = $coinRow;
            }

            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);

                switch($dataName) {

                    case 'dateRange':
                            // Set db column here
                            $columnName = 'DATE(created_at)';

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

                    case 'memberID':
                        $sq = $db->subQuery();
                        $sq->where("member_id", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);
                        break;

                    case 'type':
                        // Set db column here
                        $db->where("type", $dataValue);
                        break;

                    case 'leaderUsername':
                        $clientID = $db->subQuery();
                        $clientID->where('username', $dataValue);
                        $clientID->getOne('client', "id");

                        $downlines = Tree::getSponsorTreeDownlines($clientID);

                        if (empty($downlines))
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

                        $db->where('client_id', $downlines, "IN");
                        break;           
                }
            }

            if($site == "Member"){
                $db->where("client_id", $clientID);
            }

            $db->orderBy("created_at", "DESC");
            $copyDb = $db->copy();
            $bonusInRes = $db->get("trd_bonus_in", $limit, "client_id, type, belong_id, batch_id, bonus_value as adminCharge, created_at");

            if (empty($bonusInRes))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

            foreach ($bonusInRes as $bonusInRow) {
                if($site != "Member") $clientIDAry[$bonusInRow["client_id"]] = $bonusInRow["client_id"];

                $belongAry[$bonusInRow["belong_id"]] = $bonusInRow["belong_id"];
            }

            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, member_id");
            }

            if($belongAry){
                $db->where("belong_id", $belongAry, "IN");
                $matchAry = $db->map("belong_id")->get("trd_match_transaction", null, "belong_id, coin_type, price, quantity");
            }

            foreach ($bonusInRes as $bonusInRow) {
                unset($dataRow);
                if($site != "Member"){
                    $dataRow["memberID"] = $clientDataAry[$bonusInRow["client_id"]]["member_id"];
                    $dataRow["username"] = $clientDataAry[$bonusInRow["client_id"]]["username"];
                }
                $dataRow["type"] = $bonusInRow["type"];
                $dataRow["typeDisplay"] = General::getTranslationByName($bonusInRow["type"]);
                $dataRow["createdAt"] = date($dateTimeFormat, strtotime($bonusInRow['created_at']));
                $dataRow["coinType"] = $matchAry[$bonusInRow["belong_id"]]["coin_type"];
                $dataRow["coinTypeDisplay"] = $coinDataAry[$matchAry[$bonusInRow["belong_id"]]["coin_type"]]["display"];
                $dataRow["price"] = Setting::setDecimal($matchAry[$bonusInRow["belong_id"]]["price"]);
                $dataRow["quantity"] = Setting::setDecimal($matchAry[$bonusInRow["belong_id"]]["quantity"]);
                $dataRow["adminCharge"] = Setting::setDecimal($bonusInRow["adminCharge"]);
                $dataRow["totalPrice"] = Setting::setDecimal(($dataRow["price"] * $dataRow["quantity"]));

                $trnxList[] = $dataRow;

                $tblQty += $dataRow['quantity'] ? $dataRow['quantity'] : '0';
                $tblAmt += $dataRow['totalPrice'] ? $dataRow['totalPrice'] : '0';
                $tblTrxFees += $dataRow['adminCharge'] ? $dataRow['adminCharge'] : '0';
            }

            $tblTotalList['quantity'] = Setting::setDecimal($tblQty);
            $tblTotalList['totalPrice'] = Setting::setDecimal($tblAmt);
            $tblTotalList['adminCharge'] = Setting::setDecimal($tblTrxFees);

            unset($belongAry);
            unset($matchAry);

            $grandTotalData = $copyDb->get("trd_bonus_in", null, "type, belong_id, bonus_value as adminCharge");
            foreach ($grandTotalData as $grandTotal) {
                $belongAry[$grandTotal["belong_id"]] = $grandTotal["belong_id"];
            }

            if($belongAry){
                $db->where("belong_id", $belongAry, "IN");
                $matchAry = $db->map("belong_id")->get("trd_match_transaction", null, "belong_id, coin_type, price, quantity");
            }

            foreach ($grandTotalData as $grandTotal) {
                $grandTotal["price"] = Setting::setDecimal($matchAry[$grandTotal["belong_id"]]["price"]);
                $grandTotal["quantity"] = Setting::setDecimal($matchAry[$grandTotal["belong_id"]]["quantity"]);
                $grandTotal["totalPrice"] = Setting::setDecimal(($grandTotal["price"] * $grandTotal["quantity"]));

                if($grandTotal["type"] == "buy") {
                    $buyQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
                    $buyAmt += ($grandTotal["totalPrice"] ? $grandTotal["totalPrice"] : '0');
                    $buyTrxFees += ($grandTotal['adminCharge'] ? $grandTotal['adminCharge'] : '0');
                } else {
                    $sellQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
                    $sellAmt += ($grandTotal["totalPrice"] ? $grandTotal["totalPrice"] : '0');
                    $sellTrxFees += ($grandTotal['adminCharge'] ? $grandTotal['adminCharge'] : '0');
                }
            }

            $grandTotalList['buy']['quantity'] = Setting::setDecimal($buyQty);
            $grandTotalList['buy']['totalPrice'] = Setting::setDecimal($buyAmt);
            $grandTotalList['buy']['adminCharge'] = Setting::setDecimal($buyTrxFees);
            $grandTotalList['sell']['quantity'] = Setting::setDecimal($sellQty);
            $grandTotalList['sell']['totalPrice'] = Setting::setDecimal($sellAmt);
            $grandTotalList['sell']['adminCharge'] = Setting::setDecimal($sellTrxFees);

            $totalRecord = count($grandTotalData);

            $data["trnxList"] = $trnxList;
            $data['tblTotalList'] = $tblTotalList;
            $data['grandTotalList'] = $grandTotalList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00666"][$language], 'data'=> $data);
        }

        public function getTradingSummary($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $usernameSearchType = $params["usernameSearchType"];
            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $coinAry = self::getTrdCoin();
            foreach ($coinAry as $coinRow) {
                $coinDataAry[$coinRow["name"]] = $coinRow;
            }

            $db->where("name", "isDailyLimitWallet");
            $db->where("value", 1);
            $limitPercentage = $db->getValue("credit_setting", "reference");

            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);

                switch($dataName) {

                    case 'dateRange':
                            // Set db column here
                            $columnName = 'DATE(created_at)';

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

                    case 'memberID':
                        $sq = $db->subQuery();
                        $sq->where("member_id", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);
                        break;
                }
            }

            if($site == "Member"){
                $db->where("client_id", $clientID);
            }

            $db->where("type", "buy");
            $db->groupBy("DATE(created_at)");
            $db->groupBy("client_id");
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $limitRes = $db->get("trd_client_limit", $limit, "client_id, DATE(created_at) as createdAt, SUM(debit) as usedLimit, SUM(credit) as inLimit, MAX(credit) AS balance");
            foreach ($limitRes as $limitRow) {
                if(!$endDate){
                    $endDate = $limitRow["createdAt"];
                }
                $startDate = $limitRow["createdAt"];
                if($site != "Member") $clientIDAry[$limitRow["client_id"]] = $limitRow["client_id"];
            }

            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, member_id");
            }

            if($startDate && $endDate){
                $db->where("type", "vol");
                $db->where("DATE(created_at)", $startDate, ">=");
                $db->where("DATE(created_at)", $endDate, "<=");
                $db->groupBy("DATE(created_at)");
                $db->groupBy("client_id");
                $buyVolumeRes = $db->get("trd_client_limit", null, "client_id, DATE(created_at) as createdAt, SUM(credit - debit) AS balance");
                foreach ($buyVolumeRes as $buyVolumeRow) {
                    $buyVolumeAry[$buyVolumeRow["client_id"]][$buyVolumeRow["createdAt"]] = $buyVolumeRow["balance"];
                }
            }

            foreach ($limitRes as $key => $limitRow) {
                unset($dataRow);

                if($site != "Member"){
                    $dataRow["memberID"] = $clientDataAry[$limitRow["client_id"]]["member_id"];
                    $dataRow["username"] = $clientDataAry[$limitRow["client_id"]]["username"];
                }

                $dataRow["date"] = date("Y-m-d", strtotime($limitRow["createdAt"]));
                $dataRow["buyVolume"] = $buyVolumeAry[$limitRow["client_id"]][$dataRow["date"]] ? Setting::setDecimal($buyVolumeAry[$limitRow["client_id"]][$dataRow["date"]]) : 0;
                $dataRow["release"] = Setting::setDecimal(($dataRow["buyVolume"] * 1 / 100), ""); //temp hardcode release %
                $dataRow["usedLimit"] = $limitRow["usedLimit"] >= $limitRow["balance"] ? $limitRow["balance"] : Setting::setDecimal($limitRow["usedLimit"], ""); //temp hardcode release %
                $dataRow["dailyLimit"] = $limitRow["balance"] > 0 ? Setting::setDecimal($limitRow["balance"], "") : 0;
                $dataRow["percentage"] = $limitPercentage;
                $dataRow["balance"] = Setting::setDecimal(($dataRow["dailyLimit"] * 100 / 500), "");

                $trnxList[] = $dataRow;
            }
            // $data['buyVolumeAry'] = $buyVolumeAry;

            $totalRecord = $copyDb->getValue("trd_client_limit", "count(*)");
            $data['trnxList'] = $trnxList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00666"][$language], 'data'=> $data);
        }

        public function getTradingLimitSummary($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $usernameSearchType = $params["usernameSearchType"];
            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $todayDate = date("Y-m-d H:i:s");

            $coinAry = self::getTrdCoin();
            foreach ($coinAry as $coinRow) {
                $coinDataAry[$coinRow["name"]] = $coinRow;
            }

            $db->where("name", "isDailyLimitWallet");
            $db->where("value", 1);
            $limitPercentage = $db->getValue("credit_setting", "reference");

            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);

                switch($dataName) {

                    case 'regDate':
                            // Set db column here
                            $columnName = 'DATE(created_at)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            $sq = $db->subQuery();

                            if(strlen($dateFrom) > 0) {
                                $sq->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                $sq->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            $sq->get("client", null, "id");
                            $db->where("client_id", $sq, "IN");

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
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

                    case 'memberID':
                        $sq = $db->subQuery();
                        $sq->where("member_id", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);
                        break;

                    case 'name':
                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("client_id", $sq);
                        break;
                }
            }

            if($site == "Member"){
                $db->where("client_id", $clientID);
            }

            $db->where("type", "buy");
            $db->groupBy("DATE(created_at)");
            $db->groupBy("client_id");
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $limitRes = $db->get("trd_client_limit", $limit, "client_id, DATE(created_at) as createdAt, SUM(debit) as usedLimit, SUM(credit) as inLimit, MAX(credit) AS balance");
            if(!$limitRes){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
            foreach ($limitRes as $limitRow) {
                if(!$endDate){
                    $endDate = $limitRow["createdAt"];
                }
                $startDate = $limitRow["createdAt"];
                if($site != "Member") $clientIDAry[$limitRow["client_id"]] = $limitRow["client_id"];
            }

            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, member_id, name, created_at");

                $db->where("client_id", $clientIDAry, "IN");
                $db->where("type", "vol");
                $db->where("DATE(created_at)", $startDate, ">=");
                $db->where("DATE(created_at)", $endDate, "<=");
                $db->groupBy("DATE(created_at)");
                $db->groupBy("client_id");
                $buyVolumeAry = $db->map("client_id")->get("trd_client_limit", null, "client_id, SUM(credit - debit) AS balance");
            }

            foreach ($limitRes as $key => $limitRow) {
                unset($dataRow);

                if($site != "Member"){
                    $dataRow["clientID"] = $limitRow["client_id"];
                    $dataRow["memberID"] = $clientDataAry[$limitRow["client_id"]]["member_id"];
                    $dataRow["username"] = $clientDataAry[$limitRow["client_id"]]["username"];
                    $dataRow["name"] = $clientDataAry[$limitRow["client_id"]]["name"];
                    $dataRow["regDate"] = date($dateTimeFormat, strtotime($clientDataAry[$limitRow["client_id"]]["created_at"]));
                }

                $dataRow["buyVolume"] = $buyVolumeAry[$limitRow["client_id"]] ? Setting::setDecimal($buyVolumeAry[$limitRow["client_id"]]) : 0;
                $dataRow["release"] = Setting::setDecimal(($dataRow["buyVolume"] * 1 / 100), ""); //temp hardcode release %
                $dataRow["usedLimit"] = $limitRow["usedLimit"] >= $limitRow["balance"] ? $limitRow["balance"] : Setting::setDecimal($limitRow["usedLimit"], ""); //temp hardcode release %
                $dataRow["dailyLimit"] = $limitRow["balance"] > 0 ? Setting::setDecimal($limitRow["balance"], "") : 0;
                $dataRow["percentage"] = $limitPercentage;
                $dataRow["balance"] = Setting::setDecimal($limitRow["balance"], "");

                $trnxList[] = $dataRow;
            }
            $totalRecord = $copyDb->getValue("trd_client_limit", "count(*)");
            $data['trnxList'] = $trnxList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00666"][$language], 'data'=> $data);
        }

        public function getMemberTradingLimit($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $usernameSearchType = $params["usernameSearchType"];
            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            if(!$clientID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Client ID.", 'data'=> "");
            }

            $coinAry = self::getTrdCoin();
            foreach ($coinAry as $coinRow) {
                $coinDataAry[$coinRow["name"]] = $coinRow;
            }

            $db->where("name", "isDailyLimitWallet");
            $db->where("value", 1);
            $limitPercentage = $db->getValue("credit_setting", "reference");

            foreach ($searchData as $k => $v) {
                $dataName       = trim($v['dataName']);
                $dataValue      = trim($v['dataValue']);

                switch($dataName) {

                    case 'dateRange':
                            // Set db column here
                            $columnName = 'DATE(created_at)';

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
                }
            }

            $db->where("client_id", $clientID);
            $db->where("type", "buy");
            $db->groupBy("DATE(created_at)");
            $db->groupBy("client_id");
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $limitRes = $db->get("trd_client_limit", $limit, "client_id, DATE(created_at) as createdAt, SUM(debit) as usedLimit, SUM(credit) as inLimit, MAX(credit) AS balance");
            if(!$limitRes){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
            foreach ($limitRes as $limitRow) {
                if(!$endDate){
                    $endDate = $limitRow["createdAt"];
                }
                $startDate = $limitRow["createdAt"];
            }

            if($startDate && $endDate){
                $db->where("type", "vol");
                $db->where("DATE(created_at)", $startDate, ">=");
                $db->where("DATE(created_at)", $endDate, "<=");
                $db->groupBy("DATE(created_at)");
                $db->groupBy("client_id");
                $buyVolumeRes = $db->get("trd_client_limit", null, "client_id, DATE(created_at) as createdAt, SUM(credit - debit) AS balance");
                foreach ($buyVolumeRes as $buyVolumeRow) {
                    $buyVolumeAry[$buyVolumeRow["client_id"]][$buyVolumeRow["createdAt"]] = $buyVolumeRow["balance"];
                }
            }

            foreach ($limitRes as $key => $limitRow) {
                unset($dataRow);

                $dataRow["date"] = date("Y-m-d", strtotime($limitRow["createdAt"]));
                $dataRow["buyVolume"] = $buyVolumeAry[$limitRow["client_id"]][$dataRow["date"]] ? Setting::setDecimal($buyVolumeAry[$limitRow["client_id"]][$dataRow["date"]]) : 0;
                $dataRow["release"] = Setting::setDecimal(($dataRow["buyVolume"] * 1 / 100), ""); //temp hardcode release %
                $dataRow["usedLimit"] = $limitRow["usedLimit"] >= $limitRow["balance"] ? $limitRow["balance"] : Setting::setDecimal($limitRow["usedLimit"], ""); //temp hardcode release %
                $dataRow["dailyLimit"] = $limitRow["balance"] > 0 ? Setting::setDecimal($limitRow["balance"], "") : 0;
                $dataRow["percentage"] = $limitPercentage;
                $dataRow["balance"] = Setting::setDecimal($limitRow["balance"], "");

                $trnxList[] = $dataRow;
            }
            // $data['buyVolumeAry'] = $buyVolumeAry;

            $totalRecord = $copyDb->getValue("trd_client_limit", "count(*)");
            $data['trnxList'] = $trnxList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M00666"][$language], 'data'=> $data);
        }
    }
 ?>