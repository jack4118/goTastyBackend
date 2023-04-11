<?php

    /**
     * Wallet Class:
     * Used for retrieving and calculating client's credit data in the system
     */
    
    class Wallet
    {
        
        function __construct() {
        	
        }

        public function getCreditData($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $db->userID;
            $site           = $db->userType;

            $creditType     = trim($params['creditType']);
            $type           = trim($params['type']); //transfer/convert/withdrawal/fundIn/purchase

            if(!$type){
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Type.', 'data'=> "");
            }

            if($site == "Member"){
                $db->where("id", $clientID);
                if(!$db->has("client")) {
                    return array("status" => "error", "code" => 2, "statusMsg" => "Invalid Member.", "data"=> "");

                }
            } else {
                if(!$clientID){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Member.', 'data'=> "");
                }
            }

            if(!$creditType){
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Credit Type.', 'data'=> "");
            }else{
                $db->where("name", $creditType);
                $db->orWhere("type", $creditType);
                $creditRes = $db->get("credit", null, "id, name, type, translation_code");
                if(empty($creditRes)){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Credit Type.', 'data'=> "");
                }

                foreach ($creditRes as $creditRow) {
                    $creditIDAry[$creditRow["id"]] = $creditRow["id"];
                }
            }

            //check valid checking
            switch ($type) {
                case 'transfer':
                    $checkName = "isTransferable";
                    break;
                
                case 'withdrawal': 
                    $checkName = "isWithdrawable";
                    break;

                case 'convert': 
                    $checkName = "isConvertible";
                    break;

                case 'fundIn':
                    $checkName = "isFundinable";
                    break;

                case 'purchase': 
                    $checkName = "isPurchaseCredit";
                    break;
            }

            $db->where("name",$checkName);
            $db->where("member","1");
            $db->where("credit_id",$creditIDAry,"IN");
            $validCreditType = $db->getValue("credit_setting","count(id)");

            if(!$validCreditType){
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Credit Type.', 'data'=> "");
            }
            
            //take all creditLang 
            $allCreditRes = $db->map("id")->get("credit",null, "id, name, type, translation_code");
            foreach ($allCreditRes as $allCreditRow) {
                $allCreditAry[$allCreditRow["name"]] = $allCreditRow["translation_code"];
                $allCreditAry[$allCreditRow["type"]] = $allCreditRow["translation_code"];
            }

            $data["creditType"] = $creditType;
            $data["creditTypeDisplay"] = $allCreditAry[$creditType];

            switch ($type) {
                case 'withdrawal':
                    $db->where("name", $creditType);
                    $db->orWhere("type", $creditType);
                    $creditResult = $db->getOne("credit", "id, name, type, translation_code");

                    $creditType = $creditResult['name'];

	                // $db->where("name","1stWithdrawalFree");
	                // $db->where("credit_id",$creditIDAry,"IN");
	                // $freeWtihdrawal = $db->getValue("credit_setting","value");

         //        	if($freeWtihdrawal == 1){
         //                //1st withdrawal
					    // $db->where("client_id",$clientID);
					    // $firstWithdrawal =$db->getValue("mlm_withdrawal","id");
         //        	}

                	// if(!$params['withdrawalType']) $params["withdrawalType"] = "manual";
                	// $adminChargesSett = $params["withdrawalType"] == "manual" ? "manualWithdrawAdminFee" : "autoWithdrawAdminFee";
                 //    $db->where("credit_id", $creditIDAry,"IN");
                 //    $db->where("name",$adminChargesSett);
                 //    $creditAdminChargesRes = $db->get("credit_setting", NULL, "value, reference");
                 //    // if(!$firstWithdrawal && $freeWtihdrawal == 1) $creditAdminCharges[0]['value'] = 0;
                 //    if($db->count > 1){
                 //    	// unset($creditAdminCharges);
                 //    	// $db->where("credit_id", $creditIDAry,"IN");
	                //     // $db->where("name","withdrawalAdminFee");
	                //     // $db->groupBy("value");
	                //     // $db->groupBy("reference");
	                //     // $db->orderBy("convert(`value`, decimal)","DESC");
	                //     // $res = $db->get("credit_setting", NULL, "value, reference,type");
	                //     // foreach($res AS $val){
	                //     // 	$row["value"] = (!$firstWithdrawal && $freeWtihdrawal == 1 ?  0 : $val["value"]);
	                //     // 	$row["type"] = $val["type"];
	                //     // 	$row["range"] = $val["reference"];
	                //     // 	$creditAdminCharges[] = $row;
	                //     // }

                 //    	$creditAdminCharges = $creditAdminChargesRes[0]["value"];
                 //    	$adminChargesType = $creditAdminChargesRes[0]["reference"];

                 //    }else{
                 //    	$creditAdminCharges = 0;
                 //    }
                    
                    $db->where("id",$clientID);
                    $countryID = $db->getValue("client","country_id");        

                    // $db->where("credit_id", $creditIDAry,"IN");
                    // $db->where("name","isWithdrawable");
                    // $withdrawalByRes = $db->get("credit_setting", NULL, "value");
                    // foreach ($withdrawalByRes as $withdrawalByRow) {
                    //     $withdrawalBy = $withdrawalByRow["value"];
                    // }

                    if(!$params['withdrawalType']) $params["withdrawalType"] = "manual";

                    //1-- bank
                    //2 -- crypto
                    // $db->where("name","isAutoWithdrawal");
                    // $db->where("client_id",$clientID);
                    // // $db->where("value",'1');
                    // $withdrawalBy = $db->getOne("client_setting","type,reference");
                    // if($withdrawalBy['type'] == 'bank'){
                        /* get bank data */
                        $db->where("client_id",$clientID);
                        $db->where("status", "Active");
                        $db->orderBy("id","DESC");
                        $bankData = $db->get("mlm_client_bank",null, "
                            bank_id,
                            account_no,
                            account_holder,
                            province,
                            branch,
                            bank_city,
                            (SELECT country_id FROM mlm_bank WHERE id = bank_id) AS country_id,
                            (SELECT name FROM mlm_bank WHERE id = bank_id) AS bank_name,
                            (SELECT translation_code FROM mlm_bank WHERE id = bank_id) AS bank_display
                            ");
                        if(!empty($bankData)) {
                            foreach ($bankData as $key => &$bankDataRow) {
                                $bankDataRow["bank_display"] = $translations[$bankDataRow["bank_display"]][$language];
                            }
                            $data['bankData'] = $bankData; 
                        }

                        /* exchange rate */
                        $exchangeCountryRateRes = $db->get("mlm_currency_exchange_rate");
                        foreach($exchangeCountryRateRes as $exchangeCountryRateRow){
                            $tempExchangeRate[$exchangeCountryRateRow['country_id']] = $exchangeCountryRateRow['exchange_rate'];
                        }

                        foreach ($data['bankData'] as $dataRow) {
                            $exchangeRateDisplay[$dataRow['bank_id']] = $tempExchangeRate[$dataRow['country_id']];
                        }

                        $countryID = $data['bankData'][0]['country_id'];
						$db->where("reference", $countryID);
		            	$db->where("type", $params["withdrawalType"]);
		            	$db->where("name","bankWithdrawalFee");
		            	$res = $db->getOne("system_settings","value,type"); 
		            	$creditAdminCharges = $res['value'];

                        //only 1 bank 
                        $coinRate = $tempExchangeRate[$dataRow['country_id']];

                        /* END get bank data */
                    // }else if($withdrawalBy['type'] == 'crypto'){
                        /* get wallet data */
                        $cryptoCreditListDisplay = Self::getCryptoCredit(true, true);
                        
                        $db->where("client_id",$clientID);
                        $db->where("status", "Active");
                        $walletDataRes = $db->get("mlm_client_wallet_address",NULL, "credit_type, info");
                        // if($walletDataRes){
                        //     foreach ($walletDataRes as $key => $walletDataRow) {
                        //         $tempWalletData[$walletDataRow["credit_type"]] = $walletDataRow["info"];
                        //     }
                        // }

                        foreach ($cryptoCreditListDisplay as $key => $value) {
                        	// if($key != $withdrawalBy['reference']) continue;
                            // $tempWallet["credit_type"] = $key;
                            // $tempWallet["info"] = $tempWalletData[$key];
                            // $tempWallet["creditTypeDisplay"] = $value;

                            // $walletData[] = $tempWallet;

                            foreach($walletDataRes as $walletDataRow) {
                                $tempWallet["credit_type"] = $key;
                                $tempWallet["info"] = $walletDataRow['info'];
                                $tempWallet["creditTypeDisplay"] = $value;

                                $walletData[] = $tempWallet;
                            }
                        }
                        /* END get wallet data */

                        // $acceptCoinType = json_decode(Setting::$systemSetting['cryptoCoinType'], true);
                        // $acceptCoinType = Client::getCryptoCredit(true, true);
                        // foreach ($acceptCoinType as $key => $value) {
                            // $cryptoCreditAry[$key] = $translations[$value][$language];
                            $type = Self::cryptoMapping($withdrawalBy['reference']);
                            $db->where("type", $type);
                            $db->orderBy("created_on", "DESC");
                            $coinRate = $db->getValue("mlm_coin_rate", "rate");

                            // $exchangeRateDisplay[$key] = $coinRateRes?:1;
                        // }
                        //system setting withdrawal Rate
		            	// $db->where("reference", $type);
		            	// $db->where("type", $params["withdrawalType"]);
		            	// $db->where("name","cryptoWithdrawalFee");
		            	// $res = $db->getOne("system_settings","value,type");
		            	// $creditAdminCharges = $res['value'] ? $res['value'] : 0;
		            	// $adminFeeType == "percentage";
                        $creditArr = Cash::$paymentCredit;
                        $creditTypes = $creditArr[$creditType];

                        $db->where("name",$creditTypes, "IN");
                        $db->orWhere("type",$creditTypes, "IN");
                        $creditIDAry = $db->map('id')->get("credit",NULL,"id"); 
                        if($creditIDAry){
                            // if($creditType == "rewardCredit"){
                            //     $db->where("credit_id", $creditIDAry,"IN");
                            //     $db->where("name", "isWithdrawable");
                            //     $db->where("value", "0", ">");
                            //     $isWithdrawableRes = $db->map("credit_id")->get("credit_setting", null, "credit_id");
                            //     foreach ($isWithdrawableRes as $isWithdrawableRow) {
                            //         unset($creditType);
                            //         $creditType = $allCreditRes[$isWithdrawableRow]['name'];
                            //     }
                            // }

                            $db->where("credit_id", $creditIDAry,"IN");
                            $db->where("name", "isWithdrawable");
                            $db->where("value", "0", ">");
                            $isWithdrawableRes = $db->map("credit_id")->get("credit_setting", null, "credit_id");
                            foreach ($isWithdrawableRes as $isWithdrawableRow) {
                                // unset($creditType);
                                $creditTypeBalanceGet[$allCreditRes[$isWithdrawableRow]['name']] = $allCreditRes[$isWithdrawableRow]['name'];
                            }

                            $balance = 0;
                            if($creditTypeBalanceGet) {
                                $balance = Cash::getBalance($clientID, $creditTypeBalanceGet[$creditType], "", false);
                                $data["balance"] = $balance;
                            }
                            $calculatedBalance = 1;

                            /*$db->where("credit_id", $creditIDAry,"IN");
                            $db->where("name","withdrawalAdminFee");
                            $db->groupBy("value");
                            $db->groupBy("reference");
                            // $db->orderBy("convert(`value`, decimal)","DESC");
                            $creditAdminCharges = $db->get("credit_setting", NULL, "value, reference,type");
                            foreach ($creditAdminCharges as $creditAdminChargesRow) {
                                $adminFeeAry[$creditAdminChargesRow['reference']]['adminFee'] = $creditAdminChargesRow['value'];
                                $adminFeeAry[$creditAdminChargesRow['reference']]['type'] = $creditAdminChargesRow['type'];
                            }*/

                            $db->where("credit_id", $creditIDAry,"IN");
                            $db->where("name","withdrawalAdminFee");
                            $adminFeePercentage = $db->getOne("credit_setting","reference")['reference'];


                            $db->where('credit_id', $creditIDAry, 'IN');
                            $db->where('name', 'minWithdrawal');
                            $minWithdrawal = $db->getValue('credit_setting', 'value');

                            $db->where('credit_id', $creditIDAry, 'IN');
                            $db->where('name', 'isWithdrawAll');
                            $isWithdrawAll = $db->getValue('credit_setting', 'value');
                        }

                    // }
                    // $data['withdrawalBy'] = $withdrawalBy['type'];
                    /* END exchange rate */
                    $data['exchangeRateDisplay'] = $exchangeRateDisplay;
                    $data['bankData'] = $bankData ? : '-';
                    $data['hasBankFlag'] = $bankData ? 1 : 0;
                    $data['walletData'] = $walletData;
                    $data['minWithdrawal'] = $minWithdrawal ? $minWithdrawal : 1;
                    $data['isWithdrawAll'] = $isWithdrawAll ? $isWithdrawAll : 0;
                    $data['adminFeePercentage'] = $adminFeePercentage?:0;
                    // $data['minCreditAdminCharges'] = $adminFeeAry["min"]["adminFee"];
                    // $data['minCreditAdminChargesType'] = $adminFeeAry["min"]["type"];
                    // $data['maxCreditAdminCharges'] = $adminFeeAry["max"]["adminFee"];
                    // $data['maxCreditAdminChargesType'] = $adminFeeAry["max"]["type"];
                    // $data['creditAdminCharges'] = $creditAdminCharges;
                    // $data['adminChargesType'] = $adminChargesType;
                    $data['coinRate'] = $coinRate ? $coinRate : 0;
                    
                    break;

                case 'convert':
                    $db->where("name","convertTo");
                    $db->where("member","1");
                    $db->where("credit_id",$creditIDAry, 'IN');
                    $csRes = $db->get("credit_setting", NULL, "credit_id,value,reference");
                    foreach($csRes AS $csRow){

                        $row['rate'] = $csRow['reference'];
                        $row['fromCredit'] = $creditType;
                        $row['fromCreditDisplay'] = $translations[$allCreditAry[$creditType]][$language];
                        $row['fromCreditLangCode'] = $allCreditAry[$creditType];
                        $row['toCredit'] = $csRow['value'];
                        $row['toCreditDisplay'] = $translations[$allCreditAry[$csRow['value']]][$language];
                        $row['toCreditLangCode'] = $allCreditAry[$csRow['value']];

                        if(empty($convertDataAry[$csRow['value']])){
                            $creditData[] = $row;
                        }
                        $convertDataAry[$csRow['value']] = $csRow['value'];
                    }
                    $data["convertData"] = $creditData;
                    
                    break;

                case 'fundIn':
                    $db->where("name",$checkName);
                    $db->where("value","1");
                    $db->where("credit_id",$creditIDAry,"IN");
                    // $validCoinAry = $db->map("reference")->get("credit_setting", null, "reference");
                    $validCoin = $db->getValue("credit_setting", "reference");

                    $validCoinAry = explode(',', $validCoin);                    

                    $db->where('type', $validCoinAry, "IN");
                    $db->orderBy("created_on","ASC");
                    $coinRate = $db->map("type")->get("mlm_coin_rate", NULL, "type, rate");

                    foreach ($coinRate as $coinType => $value) {
                        // $creditAry[$coinType]['adminCharge'] = $adminCharge;
                        // $creditAry[$coinType]['rate']= $value;
                        $coinData = CryptoPG::getCryptoConverter($coinType);
                        $coin['coin_type'] = $coinType;
                        $coin['coin_display'] = $coinData['shortForm'];
                        $coin['coin_value'] = strtolower($coinData['shortForm']);
                        if(strtolower($coinType) == 'usdt'){
                            $coin['rate'] = number_format(1, 8);
                        } else {
                            $coin['rate'] = $value;
                        }
                        $coinAry[] = $coin; 
                    }
                    $data['coin'] = $coinAry;
                    break;

                case 'purchase':
                    $db->where("name","isPurchaseCredit");
                    $db->where("member","1");
                    $db->where("credit_id",$creditIDAry, 'IN');
                    $csRes = $db->get("credit_setting", NULL, "credit_id,value,reference");
                    foreach($csRes AS $csRow) {
                        $row['rate']                = $csRow['reference'];
                        $row['toCredit']          = $creditType;
                        $row['toCreditDisplay']   = $translations[$allCreditAry[$creditType]][$language];
                        $row['toCreditLangCode']  = $allCreditAry[$creditType];
                        $row['fromCredit']            = $csRow['value'];
                        $row['fromCreditDisplay']     = $translations[$allCreditAry[$csRow['value']]][$language];
                        $row['fromCreditLangCode']    = $allCreditAry[$csRow['value']];
                        $row["fromCreditBalance"]     = Cash::getBalance($clientID, $csRow['value']);

                        if(empty($purchaseDataAry[$csRow['value']])) {
                            $creditData[] = $row;
                        }
                        $purchaseDataAry[$csRow['value']] = $csRow['value'];
                    }
                    $data["purchaseData"] = $creditData;
                    break;
            }

            if($creditType){
                if(!$calculatedBalance)
                    $data["balance"] = Cash::getBalance($clientID, $creditType);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        function getCryptoCredit($getArray = false, $showAll = true){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            /* prepare language */
            $creditLangArr['bitcoin']   = $translations['M01018'][$language];/* BTC */
            $creditLangArr['tether']    = $translations['M01781'][$language];/* USDT (erc20) */
            $creditLangArr['tronUSDT']  = $translations['B00398'][$language];/* TRC20-USDT */
            $creditLangArr['ethereum']  = $translations['M01987'][$language];/* ETH */
            /* END prepare language */

            if(!$showAll)
                $db->where("reference", "1");/* show Active only */

            $db->where("name", "cryptoCoinType");
            $acceptCoinType = $db->get("system_settings", null, "value");

            if($getArray) {
                foreach ($acceptCoinType as $acceptCoinTypeRow) {
                    $acceptCoinTypeRow;
                    $cryptoCreditAry[$acceptCoinTypeRow['value']] = $creditLangArr[$acceptCoinTypeRow['value']];
                }
                return $cryptoCreditAry;
            }
            else
            {
                foreach ($acceptCoinType as $acceptCoinTypeRow) {
                     $resultCredit[] = array(
                        'value'     => $acceptCoinTypeRow['value'],
                        'display'   => $creditLangArr[$acceptCoinTypeRow['value']],
                    );
                }
                return $resultCredit;
            }           
        }

        public function getAdminWithdrawalList($params,$userID) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $seeAll         = $params['seeAll'];
            $offsetSecs     = trim($params['offsetSecs']);
            $tableName      = "mlm_withdrawal";
            $searchData     = $params['searchData'];
        	$getCreditFilter = $params["getCreditFilter"];

            //credit filter 
            $db->where("value",0,">");
            $db->where("name","isWithdrawable");
            $idArr = $db->map('credit_id')->get("credit_setting",NULL,"credit_id");

            if($idArr){
            	$withdrawalMethod = array("All"=>"","Manual"=>"Manual","Auto"=>"Auto");
            	$data['withdrawalMethod'] = $withdrawalMethod;

 	            $db->where("id",$idArr,"IN");
	            $res = $db->get("credit",NULL,"type,translation_code");
	            foreach($res AS $row){
	            	$row2['value'] = $row['type'];
	            	$row2['display'] = $translations[$row['translation_code']][$language];
	            	$creditData[$row['type']] = $row2;
	            }
	            if($getCreditFilter == 1 ){
	            	$data['creditData'] = $creditData;
	            	return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
	            }
            }

            // $sq = $db->subQuery();
            // $sq->groupBy('withdraw_id');
            // $sq->get('mlm_crypto_queue', NULL, 'MAX(id)');
            // $db->where('id', $sq, 'IN');
            // $withdrawalHashMap = $db->map('withdraw_id')->get('mlm_crypto_queue', NULL, 'withdraw_id, txn_hash, error_msg');

            $column         = array(

                $tableName . ".id AS withdrawal_id",
                "(SELECT name FROM country WHERE id = (SELECT country_id FROM client WHERE id = client_id)) AS country",
                "client.id AS client_id",
                "client.username AS client_username",
                "client.member_id AS client_memberID",
                // "CONCAT(client.dial_code, '', client.phone) AS client_phone",
                "client.name AS client_name",
                // "client.sponsor_id AS sponsorID",
                // "(SELECT username FROM client WHERE id = sponsorID) AS sponsorUsername",
                // "(SELECT name FROM country WHERE id = (SELECT country_id FROM client WHERE id = client_id)) AS country",
                $tableName . ".amount",
                // "mlm_bank.name AS bank_name",
                "mlm_bank.translation_code AS translation_code",
                $tableName . ".walletAddress",
                $tableName . ".status",
                $tableName . ".charges",
                $tableName . ".receivable_amount",
                $tableName . ".converted_amount",
                $tableName . ".currency_rate",
                $tableName . ".crypto_type",
                $tableName . ".credit_type",
                $tableName . ".created_at",
                $tableName . ".estimated_date",
                $tableName . ".approved_at",
                $tableName . ".remark",
                $tableName . ".updated_currency_rate",
                $tableName . ".account_no",
                $tableName . ".branch",
                $tableName . ".bank_city",
                $tableName . ".withdrawal_type",
                $tableName . ".ref_id",
                $tableName . ".method"
            );

            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

            $withdrawalList         = array();
            $withdrawalListDetails  = array();
            $clientIdListDetails    = array();
            $clientIdList           = array();


            $creditLangArr = $db->map('type')->get("credit",NULL,"type,translation_code");

            // SELECT *  FROM `client_setting` WHERE `name` LIKE 'mainLeader' AND value != ''
            $db->where('name', 'mainLeader');
            $db->where('value', '', '!=');
            $leaderUsernameAry = $db->map('client_id')->get('client_setting', null, 'client_id, (SELECT username FROM client WHERE `id` = `value`) as leaderUsername');

            $adminLeaderAry = Setting::getAdminLeaderAry();

            $cpDb = $db->copy();

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

                            $db->where('client.id', $mainDownlines, "IN");

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
                            $db->where('client.id', $downlines, "IN");
                            break;
                    }
                }
            }


            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        /* client table */
                        case 'username':
                            if ($usernameSearchType == "like") {
                                $db->where('client.username', $dataValue."%", 'like');
                            }else {
                                $db->where('client.username', $dataValue);
                            }
                            break;
                        case 'name':
                            if($dataType == "like"){
                                $db->where('client.name', "%".$dataValue."%", 'like');
                            }else{
                                $db->where('client.name', $dataValue);
                            }
                            break;
                        case 'countryName':
                            $db->where('client.country_id', $dataValue);
                            break;
                        case 'memberID':
                            $db->where('client.member_id', $dataValue);
                            break;
                        /* END client table */

                        /* mlm_withdrawal table */
                        case 'status':
                            $db->where('mlm_withdrawal.status', $dataValue);
                            break;
                        case 'accountNo':
                            $db->where('mlm_withdrawal.account_no', $dataValue);
                            break;
                        case 'withdrawalType':
                            $db->where('mlm_withdrawal.withdrawal_type', $dataValue);

                            if($dataValue == "crypto") $isCryptoType = 1;

                            break;
                        case 'cryptoType':
                            $db->where('mlm_withdrawal.crypto_type', $dataValue);
                            break;
                        case 'creditType':
                            $db->where('mlm_withdrawal.credit_type', $dataValue);
                            break;
                        case 'method':
	                        if($dataValue){
	                        	$db->where('mlm_withdrawal.method', $dataValue);
	                        }
                            break;
                        case 'date':
                            // Set db column here
                            $columnName = 'date(mlm_withdrawal.created_at)';
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 1) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }

                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        case 'approvedAt':
                            // Set db column here
                            $columnName = 'date(mlm_withdrawal.approved_at)';
                                    
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }

                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        /* END mlm_withdrawal table */
                    }
                }
            }

            $db->join("mlm_bank", "mlm_bank.id = " . $tableName . ".bank_id", "LEFT");
            $db->join("client", "client.id = " . $tableName . ".client_id", "LEFT");
            if($adminLeaderAry) $db->where('client.id', $adminLeaderAry, 'IN');
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");
            $db->orderBy("withdrawal_id", "DESC");
            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 

            $totalWithdrawal = $copyDb->get($tableName, NULL,"sum(amount) as amount, sum(receivable_amount) as receivableAmount, sum(charges) charges");

            $withdrawalListResult = $db->get($tableName, $limit, $column);

            if (empty($withdrawalListResult))
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["B00126"][$language] /* No Results Found */, 'data' => "");

            if(!$isCryptoType){
                $clientBankAry = $db->map('id')->get("mlm_client_bank", NULL, "id, account_holder");
            }

            $totalGrossWithdrawal = array(
                "Waiting Approval" => '0',
                "Pending" => '0',
                "Cancel" => '0', 
                "Reject" => '0',
                "Approve" => '0',
            );

            $totalNetWithdrawal = array(
                "Waiting Approval" => '0',
                "Pending" => '0',
                "Cancel" => '0', 
                "Reject" => '0',
                "Approve" => '0',
            );

            $totalWithdrawalAmount = 0;
            $totalWithdrawalCharges = 0;
            $totalWithdrawalReceivableAmount = 0;
            $totalWithdrawalConvertedAmount = 0;
            $cryptoCreditListDisplay = self::getCryptoCredit(true);
            foreach ($withdrawalListResult as $withdrawalResult){
                $withdrawalListDetails = $withdrawalResult;
                $withdrawalListDetails["credit_type"] = $translations[$creditLangArr[$withdrawalResult['credit_type']]][$language];
                $withdrawalAmount = $withdrawalResult['amount'];
                $totalWithdrawalAmount += $withdrawalAmount;
                $withdrawalListDetails['amount'] = number_format($withdrawalResult['amount'], $decimalPlaces, '.', ',');
                $withdrawalCharges = $withdrawalResult['charges'];
                $totalWithdrawalCharges += $withdrawalCharges;
                $withdrawalListDetails['charges'] = number_format($withdrawalResult['charges'], $decimalPlaces, '.', ',');
                $withdrawalReceivableAmount = $withdrawalResult['receivable_amount'];
                $totalWithdrawalReceivableAmount += $withdrawalReceivableAmount;
                $withdrawalListDetails['receivable_amount'] = number_format($withdrawalResult['receivable_amount'], $decimalPlaces, '.', ',');
                $withdrawalConvertedAmount = $withdrawalListDetails['converted_amount'];
                $totalWithdrawalConvertedAmount += $withdrawalConvertedAmount;
                $withdrawalListDetails['converted_amount'] = number_format($withdrawalResult['converted_amount'], $decimalPlaces, '.', ',');
                $withdrawalListDetails['currency_rate'] = number_format($withdrawalResult['currency_rate'], $decimalPlaces, '.', ',');
                $withdrawalListDetails['bank_display'] = $translations[$withdrawalListDetails['translation_code']][$language];
                $withdrawalListDetails['crypto_type'] = $cryptoCreditListDisplay[$withdrawalListDetails['crypto_type']];
                $withdrawalListDetails['leaderUsername'] = $leaderUsernameAry[$withdrawalResult['client_id']];
                $withdrawalListDetails['accountHolder'] = $clientBankAry[$withdrawalResult['ref_id']] ? : "-";
                $withdrawalListDetails['branch'] = $withdrawalResult['branch'] ? : "-";
                $withdrawalListDetails['bank_city'] = $withdrawalResult['bank_city'] ? : "-";
                $withdrawalListDetails['method'] = $withdrawalResult['method'] ? $withdrawalResult['method'] : "-";

                $withdrawalListDetails['txHash'] = $withdrawalHashMap[$withdrawalResult['withdrawal_id']]['txn_hash'] ?: "-";
                $withdrawalListDetails['error_msg'] = $withdrawalHashMap[$withdrawalResult['withdrawal_id']]['error_msg'] ?: "-";

                foreach($withdrawalListDetails as $key => $value) {
                    $withdrawalListDetails[$key] = $value ? $value : "-";
                }

                if ($withdrawalResult['approved_at'] == '0000-00-00 00:00:00') $withdrawalListDetails['approved_at'] = "-";
                else $withdrawalListDetails['approved_at'] = date("d/m/Y H:i:s", strtotime($withdrawalResult['approved_at']));

                $withdrawalListDetails['created_at'] = date("d/m/Y H:i:s", strtotime($withdrawalResult['created_at']));

                switch($withdrawalResult['withdrawal_type']){
                    case "bank":
                        $withdrawalListDetails['withdrawal_type'] = General::getTranslationByName($withdrawalResult['withdrawal_type']);

                        $withdrawalListDetails['accountHolder'] = $clientBankAry[$withdrawalResult['ref_id']];
                    break;
                    case "crypto":
                        $withdrawalListDetails['withdrawal_type'] = General::getTranslationByName($withdrawalResult['withdrawal_type']);
                    break;
                    default:
                    break;
                }

                switch($withdrawalResult['status']){
                        case "Waiting Approval":
                            $withdrawalListDetails['status'] = General::getTranslationByName($withdrawalResult['status']);
                            $withdrawalListDetails['statusValue'] = $withdrawalResult['status'];
                        break;
                        case "Approve":
                            $withdrawalListDetails['status'] = General::getTranslationByName($withdrawalResult['status']);
                            $withdrawalListDetails['statusValue'] = $withdrawalResult['status'];
                        break;
                        case "Reject":
                            $withdrawalListDetails['status'] = General::getTranslationByName($withdrawalResult['status']);
                            $withdrawalListDetails['statusValue'] = $withdrawalResult['status'];
                        break;
                        case "Cancel":
                            $withdrawalListDetails['status'] = General::getTranslationByName($withdrawalResult['status']);
                            $withdrawalListDetails['statusValue'] = $withdrawalResult['status'];
                        break;
                        case "Pending":
                            $withdrawalListDetails['status'] = General::getTranslationByName($withdrawalResult['status']);
                            $withdrawalListDetails['statusValue'] = $withdrawalResult['status'];
                        break;
                        default:
                            $withdrawalListDetails['status'] = $withdrawalResult['status'];
                            $withdrawalListDetails['statusValue'] = $withdrawalResult['status'];
                        break;
                    }
                $totalGrossWithdrawal[$withdrawalResult['status']] += $withdrawalAmount;
                $totalNetWithdrawal[$withdrawalResult['status']] += $withdrawalReceivableAmount;

                $withdrawalList[] = $withdrawalListDetails;
            }

            if (empty($withdrawalList))
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["B00126"][$language] /* No Results Found */, 'data' => "");

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $totalGrossAmount = array_sum($totalGrossWithdrawal);
            $totalGrossWithdrawal['totalGrossAmount'] = $totalGrossAmount ? $totalGrossAmount : 0;
            $totalNetAmount = array_sum($totalNetWithdrawal);
            $totalNetWithdrawal['totalNetAmount'] = $totalNetAmount ? $totalNetAmount : 0; 


            foreach ($totalGrossWithdrawal as $key => $value) {
                $totalGrossWithdrawal[$key] = number_format($value, $decimalPlaces, '.', ',');
            }

            foreach ($totalNetWithdrawal as $key => $value) {
                $totalNetWithdrawal[$key] = number_format($value, $decimalPlaces, '.', ',');
            }

            $totalTableAmount = array(
                'amount' => number_format($totalWithdrawalAmount, $decimalPlaces, '.', ','),
                'charges' => number_format($totalWithdrawalCharges, $decimalPlaces, '.', ','),
                'receivable_amount' => number_format($totalWithdrawalReceivableAmount, $decimalPlaces, '.', ','),
                'converted_amount' => number_format($totalWithdrawalConvertedAmount, $decimalPlaces, '.', ','),
            );

            foreach ($totalWithdrawal as $value) {
                $grandTotalAmount = array(
                    'amount' => number_format($value['amount'], $decimalPlaces, '.', ','),
                    'charges' => number_format($value['charges'], $decimalPlaces, '.', ','),
                    'receivable_amount' => number_format($value['receivableAmount'], $decimalPlaces, '.', ','),
                );
            }

            $data['grandTotalAmount']   = $grandTotalAmount;
            $data['withdrawalList']     = $withdrawalList;
            $data['pageNumber']         = $pageNumber;
            $data['totalRecord']        = $totalRecord;
            $data['totalGrossWithdrawal'] = $totalGrossWithdrawal;
            $data['totalNetWithdrawal'] = $totalNetWithdrawal;
            $data['totalTableAmount']   = $totalTableAmount;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];
            }

            $db->where('admin_id',$userID);
            $db->where('type',"withdrawal");
            $db->update('admin_notification',array("notification_count"=>0));

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getWithdrawalDetailByID($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $tableName      = "mlm_withdrawal";
            $column         = array(

                $tableName . ".amount",
                $tableName . ".status",
                $tableName . ".account_no",
                $tableName . ".branch",
                $tableName . ".created_at",
                $tableName . ".remark",
                $tableName . ".charges",
                $tableName . ".credit_type",
                $tableName . ".walletAddress",
                "mlm_bank" . ".name"
            );

            $withdrawalId = $params['withdrawalId'];

            if (empty($withdrawalId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data' => "");

            $db->join("mlm_bank", "mlm_bank.id = " . $tableName . ".bank_id", "LEFT");
            $db->where($tableName . ".id", $withdrawalId);
            $result = $db->getOne($tableName, $column);

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00128"][$language] /* No results found */, 'data' => "");


            $data['amount']         = $result['amount'];
            $data['status']         = $result['status'];
            $data['creditType']     = $result['credit_type'];
            $data['accountNumber']  = $result['account_no'];
            $data['walletAddress']  = $result['walletAddress'];
            $data['branch']         = $result['branch'];
            $data['remark']         = $result['remark'];
            $data['charges']        = number_format($result['charges'], $decimalPlaces, '.', '');
            $data['withdrawalDate'] = $result['created_at'];
            $data['bankName']       = $result['name'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00129"][$language] /* Withdrawal list detail successfully retrieved */, 'data' => $data);
        }

        public function getTransactionHistory($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $adminRoleID    = Cash::$creatorID;

            $memberID = $db->userID;
            $site = $db->userType;

            $creditType     = $params['creditType'];
            $searchData     = $params['searchData'];
            $portfolio      = $params['portfolio'];
            $pageType       = $params['pageType'];
            $source         = $site;
            $pageLimit      = $params["pageLimit"];
            //Get the limit.
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $limit = General::getLimit($pageNumber, $pageLimit);

            if($site == "Admin"){
                $memberID       = $params['id'];
            }
            if($site == 'Admin' && $pageType == 'customerService'){
                if(empty($creditType)){
                    $validCreditType    = Client::getValidCreditType();
                    $creditType         = $validCreditType[0];
                }

                $memberDetails      = Client::getCustomerServiceMemberDetails($memberID);

                $creditRes = $db->get('credit', null, 'name, admin_translation_code');
                foreach($creditRes AS $creditValue){
                    if(!in_array($creditValue['name'], $validCreditType)) continue;

                    $creditDisplay[$creditValue['name']] = $translations[$creditValue["admin_translation_code"]][$language];
                }

                $data['memberDetails'] = $memberDetails['data']['memberDetails'];
                $data['creditTypes'] = $creditDisplay;
            }

            if(empty($creditType))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00197"][$language] /* Credit type no found */, 'data' => "");
            if(empty($memberID) && $site == 'Member')
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00198"][$language] /* ID no found */, 'data' => "");

            $bonusPayoutIDAry = $db->map("bonus_id")->get("mlm_bonus_payment_method", null, "bonus_id, description");
            $bonusRes = $db->get("mlm_bonus", null, "id, language_code");
            foreach ($bonusRes as $bonusRow) {
                $bonusPayoutSubjectAry[$bonusPayoutIDAry[$bonusRow["id"]]] = $translations[$bonusRow["language_code"]][$language];
            }

            $creditArr = Cash::$paymentCredit;
            $credit = $creditArr[$creditType];
            //Get admin's roleID
            $db->where("id", $adminRoleID);
            $roleID = $db->getValue("admin","role_id");

            $permissionsID = $db->subQuery();
            $permissionsID->where("role_id",$roleID);
            $permissionsID->where("disabled",0);
            $permissionsID->get('roles_permission',null,'permission_id');
            $db->where('id',$permissionsID,'IN');
            $permissionsRes = $db->get('permissions',null,'name');
            foreach ($permissionsRes as $key => $value) {
                $permissionsArray[] = $value['name'];

            }

            // Checking whether credit type is wallet
            if($source == "API") {

                $db->where("name", $credit, 'IN');
                $creditID = $db->get("credit",NULL ,"id");

                $db->where("credit_id", $creditID, "IN");
                $result = $db->get("credit_setting", null, "name, value, member AS permission");

            }else {

                $creditID = $db->subQuery();
                $creditID->where("name", $credit, 'IN');
                $creditID->get("credit", null, "id");
                $db->where("credit_id", $creditID, "in");
                $result = $db->get("credit_setting", null, "name, value, member, admin");
            
            }

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type. */, 'data' => "");

            foreach($result as $value) {
                if($value['name'] == "isWallet" && (($value['value'] == 0 && $site != 'Admin') || ($value['admin'] == 0 && $site == 'Admin'))) {
                    // if($value['value'] == 0)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type. */, 'data' => "");
                }

                $permissions[$value['name']] = $source == "Member" ? $value['member'] : $value['admin'];

            }

            $data['permissions'] = $permissions;

            if($site == "Member"){
                
                $db->where("disabled", "0");
                $db->where("type", "Transaction Type");
                $db->orderBy("priority", "ASC");
                $trnxTypeRes = $db->get("type_mapping", null, "name, translation_code as langCode");
                foreach ($trnxTypeRes as $trnxTypeRow) {
                    $trnxTypeRow["display"] = $translations[$trnxTypeRow["langCode"]][$language];
                    $trnxTypeAry[] = $trnxTypeRow;
                }

                $data["transactionType"] = $trnxTypeAry;
            }
           
            $balance         = Cash::getBalance($memberID, $creditType);
            $data['balance'] = $balance; //latest balance not based on date filter

            unset($result);

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'creditType':
                                $db->where('type', $dataValue);
                                break;

                        case 'transactionType':
                            $db->where('subject', $dataValue);
                            break;

                        case 'dateRange':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('DATE(created_at)', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00202"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                $db->where('DATE(created_at)', date('Y-m-d', $dateTo), '<=');

                                $dateTo = strtotime(date("Y-m-d 23:59:59",$dateTo));
                                $date = $dateTo; //for getBalance
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

            $db->where('client_id', $memberID);
            $db->where('type', $credit,"IN");
            $db->groupBy("group_id");
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $copyDb2 = $db->copy();

            $result = $db->get("credit_transaction", $limit, "client_id, subject, from_id, to_id, SUM(amount) AS amount, remark, batch_id, creator_id, creator_type, created_at, portfolio_id, belong_id, type, coin_rate, data");

            if(empty($result)){
                $data['balance']         = $balance;
                $data['decimal']         = $decimal;
                $data['transactionList'] = $transactionList;
                $data['totalPage']       = ceil($totalRecord/$limit[1]);
                $data['pageNumber']      = $pageNumber;
                $data['totalRecord']     = $totalRecord;
                $data['numRecord']       = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00132"][$language] /* No Results Found */, 'data'=> $data);
            }

            foreach($result as $value) {
                if(!$startingDate) $startingDate = $value['created_at'];
                if($value['creator_type'] == 'SuperAdmin')
                    $superAdminID[] = $value['creator_id'];
                else if($value['creator_type'] == 'Admin')
                    $adminID[] = $value['creator_id'];
                else if ($value['creator_type'] == 'Member')
                    $clientID[] = $value['creator_id'];
            }

            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    // $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                    $usernameList['SuperAdmin'][$value['id']] = 'SuperAdmin';
                }
            }

            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                    if($site == 'Admin'){
                        $usernameList['Admin'][$value['id']] = $value['username'];
                    }else{
                        $usernameList['Admin'][$value['id']] = General::getTranslationByName('Admin');
                    }
                }
            }

            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            foreach($result as $value) {
                if($value['subject'] == "Transfer In" || $value['subject'] == "Transfer Out");
                    $batch[] = $value['batch_id'];

                if($value['subject'] == "Convert Credit")
                    $belong_id[] = $value['belong_id'];

                if($value['subject'] == "Bonus Package Reentry")
                    $bonusPortfolioAry[$value['portfolio_id']] = $value['portfolio_id'];
            }
            if(!empty($batch)) {
                $db->where('batch_id', $batch, 'IN');
                $db->where('subject', array("Transfer In", "Transfer Out"), 'IN');
                $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
                $batchDetail = $db->get('credit_transaction', null, 'subject, batch_id, '.$getUsername);
            }
            if(!empty($batchDetail)) {
                foreach($batchDetail as $value) {
                    $batchUsername[$value['batch_id']][$value['subject']] = $value['username'];
                }
            }
            if(!empty($belong_id)) {
                $db->where('belong_id', $belong_id, 'IN');
                $db->where('subject', array("Convert Credit"), 'IN');
                $getCreditLangCode = "(SELECT translation_code FROM credit WHERE name = creditType AND type != '".$creditType."') AS creditLangCode";
                $belongDetail = $db->get('credit_transaction', null, 'subject, belong_id, type as creditType,'.$getCreditLangCode);

            }
            if(!empty($belongDetail)) {
                foreach($belongDetail as $belongDetailValue) {
                    if(!$belongDetailValue['creditLangCode']) continue;
                    $belongCreditType[$belongDetailValue['belong_id']][$belongDetailValue['subject']] = $translations[$belongDetailValue['creditLangCode']][$language];
                }
            }

            if(!empty($bonusPortfolioAry)){
                $db->where("id", $bonusPortfolioAry, "IN");
                $portfolioIDRes = $db->get("mlm_client_portfolio", null, "id, client_id");
                foreach($portfolioIDRes as $portfolioIDRow){
                    $portfolioClientAry[$portfolioIDRow["client_id"]] = $portfolioIDRow["client_id"];
                }

                if($portfolioClientAry){
                    $db->where('id', $portfolioClientAry, 'IN');
                    $clientUsernameRes = $db->map("id")->get('client', null, 'id, username');
                    foreach($portfolioIDRes as $portfolioIDRow){
                        $bonusReentryPortfolio[$portfolioIDRow["id"]] = $clientUsernameRes[$portfolioIDRow["client_id"]];
                    }
                }
            }

            $currentBalance = Cash::getBalance($memberID, $creditType, date("Y-m-d H:i:s", $date));

            if($pageNumber != 1) {
                $res2 = $copyDb2->get('credit_transaction', array(0, $limit[0]), "client_id, subject, type, from_id, to_id, SUM(amount) AS amount, data, remark, batch_id, creator_id, creator_type, created_at, portfolio_id, belong_id, reference_id");
                foreach($res2 as $value) {
                    if($value['from_id'] >= "1000000") {
                        $currentBalance += $value['amount'];
                    }
                    else {
                        $currentBalance -= $value['amount'];
                    }
                }
            }
            $clientUsernameMap = $db->map('id')->get('client', NULL, 'id, username');

            foreach($result as $value) {
                $transactionSubject             = $bonusPayoutSubjectAry[$value['subject']] ? $bonusPayoutSubjectAry[$value['subject']] : General::getTranslationByName($value['subject']);
                $transaction['created_at']      = General::formatDateTimeToString($value['created_at'], "d/m/Y H:i:s");

                $transacDay = date('d', strtotime($value['created_at']));

                if($transacDay > 1 && $transacDay <= 16){
                    $transaction['fromToDate'] = date('16/m/Y', strtotime($value['created_at']));
                }else if($transacDay == 1){
                    $transaction['fromToDate'] = date('01/m/Y', strtotime($value['created_at']));
                }else{
                    $transaction['fromToDate'] = date('01/m/Y', strtotime($value['created_at']." +1 month"));
                }

                $transaction['subject'] = $transactionSubject ? $transactionSubject : $value['subject'] ;   

                if($value['subject'] == "Transfer Out") {
                    $transaction['to_from']     = $batchUsername[$value['batch_id']]["Transfer In"] ? $batchUsername[$value['batch_id']]["Transfer In"] : "-";
                }
                else if($value['subject'] == "Transfer In") {
                    $transaction['to_from']     = $batchUsername[$value['batch_id']]["Transfer Out"] ? $batchUsername[$value['batch_id']]["Transfer Out"] : "-";
                }
                else if($value['subject'] == "Convert Credit") {
                    $transaction['to_from']     = $belongCreditType[$value['belong_id']][$value['subject']] ? $belongCreditType[$value['belong_id']][$value['subject']] : "-";
                }else if($value['subject'] == "Bonus Package Reentry") {
                    $transaction['to_from'] = $bonusReentryPortfolio[$value["portfolio_id"]] ? $bonusReentryPortfolio[$value["portfolio_id"]] : "-";
                }
                else if($value['from_id'] == "9")
                    $transaction['to_from']     = $translations["B00224"][$language];
                else
                    $transaction['to_from']     = "-";

                $dateTimeStr = $value['created_at'];
                $dateTimeAry = explode(' ', $dateTimeStr);
                $dateAry = explode('-', $dateTimeAry[0]);
                $timeAry = explode(':', $dateTimeAry[1]);

                $startTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':00';
                $endTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':59';

                $db->where("name", $creditType);
                $db->orWhere("type", $creditType);
                $decimal = $db->getValue("credit", "dcm");

                $db->where('created_on', $startTimeStr, '>=');
                $db->where('created_on', $endTimeStr, '<=');
                $db->where('type', $creditType);
                $currentRate = $db->getValue('mlm_coin_rate', 'rate');
                if(!$currentRate) $currentRate = '-';
                if($value['from_id'] >= "1000000") {
                    $transaction['credit_in'] = "-";
                    $transaction['credit_out'] = Setting::setDecimal($value['amount'], $creditType);
                    $transaction['coin_rate'] = $value['coin_rate']==0 ? "-" : $value['coin_rate'];
                    $transaction['balance'] = Setting::setDecimal($currentBalance, $creditType);
                    $currentBalance += Setting::setDecimal($value['amount'],$creditType);
                }
                else {
                    $transaction['credit_in'] = Setting::setDecimal($value['amount'], $creditType);
                    $transaction['credit_out'] = "-";
                    $transaction['coin_rate'] = $value['coin_rate']==0 ? "-" : $value['coin_rate'];
                    $transaction['balance'] = Setting::setDecimal($currentBalance, $creditType);
                    $currentBalance -= Setting::setDecimal($value['amount'],$creditType);
                }

                if($value['from_id'] == "9" || $value['subject'] == "Package Cashback" || $value['creator_type'] == "System"){
                    $transaction['creator_id']  = General::getTranslationByName("System");
                }else{
                    $transaction['creator_id']  = $usernameList[$value['creator_type']][$value['creator_id']];
                }

                $transaction['remark']      = $value['remark'] ? $value['remark'] : "-";

                if($portfolio == 1) $transaction["portfolio_id"] = $value["portfolio_id"];

                $transaction['maxCapMultiplier'] = ($value["data"] ? $value["data"] : 0.00);

                $transactionList[] = $transaction;
                unset($transaction);
            }

            $totalRecordRes          = $copyDb->get("credit_transaction", null, "group_id");
            $totalRecord             = count($totalRecordRes);
            $data['decimal']         = $decimal;
            $data['transactionList'] = $transactionList;
            $data['totalPage']       = ceil($totalRecord/$limit[1]);
            $data['pageNumber']      = $pageNumber;
            $data['totalRecord']     = $totalRecord;
            $data['numRecord']       = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function creditAdjustment($params,$type) {
            $db = MysqliDb::getInstance();

            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            $clientId           = $params['id'];
            $creditType         = $params['creditType'];
            $adjustmentType     = $params['adjustmentType'];
            $adjustmentAmount   = $params['adjustmentAmount'];
            $remark             = $params['remark'];
            $maxAdjustment      = Setting::$systemSetting['maxAdjustment'];
            $dateTime           = date("Y-m-d H:i:s");
            $validAdjustType    = array('Adjustment In','Adjustment Out');

            if($type == 'deposit'){
                $validAdjustType    = array('Adjustment In');
            }
            
            if (empty($clientId)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00209"][$language] /* Client does not exist. */, 'data'=> "");
            }
            // checking client ID 
            $db->where('id', $clientId);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00210"][$language] /* Sender no found */, 'data' => "");

            $clientName    = $clientDetails;

            if (empty($creditType)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00211"][$language] /* Credit type is required */, 'data'=> "");
            }

            if (empty($adjustmentType)){
                $errorFieldArr[] = array(
                                            'id'  => 'adjustmentTypeError',
                                            'msg' => $translations["E00212"][$language] /* Adjustment type is required */
                                        );
            }elseif(!in_array($adjustmentType, $validAdjustType)){
                $errorFieldArr[] = array(
                                            'id'  => 'adjustmentTypeError',
                                            'msg' => $translations["E00890"][$language] /* Adjustment type is required */
                                        );
            }

            if (empty($adjustmentAmount) || !is_numeric($adjustmentAmount)){
                $errorFieldArr[] = array(
                                            'id'  => 'adjustmentAmountError',
                                            'msg' => $translations["E00213"][$language] /* Adjustment amount is required or invalid. */
                                        );
            }elseif($adjustmentAmount > $maxAdjustment){
                $errorFieldArr[] = array(
                                            'id'  => 'adjustmentAmountError',
                                            'msg' => str_replace("%%adjustmentAmount%%", $maxAdjustment, $translations["E00985"][$language]) /* Maximum Adjustment Amount is %%adjustmentAmount%%. */
                                        );
            }

            if (empty($remark)) {
                if (strlen($remark) > 255) {
                    $errorFieldArr[] = array(
                                                'id'    => 'remarkError',
                                                'msg'   => $translations["E00214"][$language] /* Text length is over limit. */
                                            );
                }
            }
            
            if($adjustmentType == "Adjustment In" ){
            	$adjustableAmount = Cash::checkCreditLimit($clientId,$creditType,$adjustmentAmount);
            	$adjustmentAmount = $adjustableAmount;
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);
            }

            if ($adjustmentType == "Adjustment Out") {

                $db->where("name", "creditAdjustment");
                $result     = $db->getValue ("client", "id");
                $accountID  = $clientId;
                $receiverID = $result;
                
                $activityCode = 'L00005';
                $titleCode = 'T00005';
                
            } else if ($adjustmentType == "Adjustment In") {
                $db->where("name", "creditRefund");
                $result     = $db->getValue ("client", "id");
                $accountID  = $result;
                $receiverID = $clientId;
                
                $activityCode = 'L00004';
                $titleCode = 'T00004';
            }

            $batchID        = $db->getNewID();
            $belongID       = $db->getNewID();
            $data = Cash::insertTAccount($accountID, $receiverID, $creditType, $adjustmentAmount, $adjustmentType, $belongID, "", $db->now(), $batchID, $clientId, $remark);
            if (!$data) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00216"][$language] /* Adjustment failed. */, 'data' => "");
            } else {
                // Update Cache
                Cash::getBalance($clientId,$creditType);
                
                $activityData = array('user'   => $clientName,
                                      'credit' => $creditType
                                     );
                $activityRes = Activity::insertActivity($adjustmentType, $titleCode, $activityCode, $activityData, $clientId);
                // Failed to insert activity
                if(!$activityRes)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

                if($adjustmentType == "Adjustment In" && $type == 'deposit'){
                    $db->where('name',$creditType);
                    $creditID = $db->getValue('credit','id');

                    //Change Sponsor bonus, Hide This sponsor bonus
                    /*$db->where('credit_id',$creditID);
                    $db->where('name',"isFundinable");
                    $isFundinable = $db->getValue('credit_setting','value');
                    if($isFundinable){
                        $queueData['bonusValue'] = $adjustmentAmount;
                        $queueData['bonusTime'] = $dateTime;
                        $queueData['belongID'] = $belongID;
                        $insertQueue = array(
                            "queue_type"    => "sponsorBonus",
                            "client_id"     => $receiverID,
                            "data"          => json_encode($queueData),
                            "status"        => "Active",
                            "created_at"    => $dateTime,
                        );
                        $db->insert('queue',$insertQueue);
                    }*/
                }

                // Custom::updateMemberActiveStatus($clientId, $dateTime);

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00135"][$language] /* Adjustment success */, 'data' => "");
            }

           
        }

        public function transferCredit($params, $site) {
            $db = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $creditType = trim($params['creditType']);
            $transferAmount = trim($params['amount']);
            $remark = trim($params['remark']);
            $transactionPassword = trim($params['transactionPassword']);
            $receiverUsername = trim($params['receiverUsername']);
            $type = 1; //value is 1 / 2; 1 = phone/username, 2 = walletAddress

            $site = $db->userType;
            $transferId = $db->userID;

            if($site == "Member") {
                if(empty($transactionPassword)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'transactionPasswordError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );
                }
                else {
                    $result = Client::verifyTransactionPassword($transferId, $transactionPassword);
                    if($result['status'] != "ok") {
                        $errorFieldArr[] = array(
                                                    'id'  => 'transactionPasswordError',
                                                    'msg' => $translations["E00219"][$language] /* Invalid password */
                                                );
                    }
                }
            }else if($site == 'Admin'){
                $transferId = trim($params['clientId']); //userID = clientID
            }

            if (empty($creditType))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            //check credit isTransferable
            $db->where("type", $creditType);
            $checkID = $db->getValue("credit", "id");
            if (!$checkID) {
                $db->where("name", $creditType);
            } else {
                $db->where("type", $creditType);
            }

            $creditRow = $db->getOne("credit", "id, translation_code");
            $creditID = $creditRow["id"];
            $creditDisplay = $translations[$creditRow["translation_code"]][$language];
            if ($site == 'Admin') {
                $db->where("admin", 1);
            } else {
                $db->where("member", 1);
            }
            $db->where("credit_id", $creditID);
            $db->where("name", "isTransferable");
            $isTransferable = $db->getValue("credit_setting", "value");
            if ($isTransferable != 1) return array('status' => "error", 'code' => 1, 'statusMsg' => "This credit cannot transfer", 'data' => "");

            if (empty($transferId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => "");

            if (empty($receiverUsername)) {
                $errorFieldArr[] = array(
                    'id' => 'receiverUsernameError',
                    'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                );
            }

            $db->where('id', $transferId);
            $transferID = $db->getValue('client', 'id');
            if (empty($transferID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00223"][$language] /* No result found */, 'data' => "");

            if (empty($transferAmount) || !is_numeric($transferAmount)) {
                $errorFieldArr[] = array(
                    'id' => 'transferAmountError',
                    'msg' => $translations["E00224"][$language] /* Invalid amount */
                );
            } else {
                $balance = Cash::getClientCacheBalance($transferID, $creditType);
                if ($transferAmount > $balance) {
                    $errorFieldArr[] = array(
                        'id' => 'transferAmountError',
                        'msg' => $translations["E00266"][$language] /* Insufficient credit */
                    );
                }
            }


            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }


            if ($type == "1") {
                // $db->where('concat(dial_code,phone)', $receiverUsername);
                // $db->orWhere('username', $receiverUsername);
                $db->where('username', $receiverUsername);
                $receiverID = $db->getValue('client', 'id');
            
            }else if($type == "2") {

                $db->where('username', $receiverUsername);
                $addressRow = $db->getOne('client', 'id');
                $receiverID = $addressRow['id'];
                if (empty($addressRow['id'])) {
                    $errorFieldArr[] = array(
                        'id' => 'receiverUsernameError',
                        'msg' => $translations["E00227"][$language] /* Invalid username */
                    );
                }
            }else if($type == "3"){
                $db->where('username', $receiverUsername);
                $clientRow = $db->getOne('client', 'id, main_id');

                $receiverID = $clientRow["id"];
                $receiverMainID = $clientRow["main_id"];
            }

            if (empty($receiverID)) {
                $errorFieldArr[] = array(
                                            'id' => 'receiverUsernameError',
                                            'msg' => $translations["E00227"][$language] /* Invalid username */
                                        );
            } else if ($transferID == $receiverID) {
                $errorFieldArr[] = array(
                                            'id' => 'receiverUsernameError',
                                            'msg' => $translations["E00228"][$language] /* Receiver cannot be yourself */
                                        );
            }

            if($type == "3" && $receiverMainID){
                $errorFieldArr[] = array(
                                            'id' => 'receiverUsernameError',
                                            'msg' => str_replace("%%credit%%", $creditDisplay, $translations["E00845"][$language]) /* Receiver cannot be yourself */
                                        );
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // check member transfer to its uplines and downlines
            $checkUpDownLines = Tree::sponsorTreeUpDownLinesChecking($transferID, $receiverID);
            if (!$checkUpDownLines) {
                $errorFieldArr[] = array(
                                            'id' => 'receiverUsernameError',
                                            'msg' => $translations["E00846"][$language]
                                        );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            $dataOut["receiverID"] = $receiverID;
            $dataOut["transferID"] = $transferID;
            $dataOut["creditDisplay"] = $creditDisplay;
            $dataOut["receiverUsername"] = $receiverUsername;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $dataOut);
        }

        public function transferCreditConfirmation($params,$site) {
            $db = MysqliDb::getInstance();

            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            $type                = trim($params['type']);  //value is 1 / 2; 1 = phone/username, 2 = walletAddress
            $creditType          = trim($params['creditType']);
            $receiverUsername    = trim($params['receiverUsername']);
            $transferAmount      = trim($params['amount']);
            $remark              = trim($params['remark']);
            // $transferId          = trim($params['clientId']); //userID = clientID
            $transactionPassword = trim($params['transactionPassword']);
            $callSource          = trim($params['callSource']);

            $returnData = Self::transferCredit($params,$site);
            if($returnData["status"] != "ok"){
                return $returnData;
            }
            
            $transferID = $returnData["data"]["transferID"];
            $receiverID = $returnData["data"]["receiverID"];
            $creditDisplay = $returnData["data"]["creditDisplay"];
            $receiverName = $returnData["data"]["receiverUsername"];
            $db->where('id',$transferID);
            $senderName = $db->getValue('client','username');

            $batchID  = $db->getNewID();
            $belongID = $db->getNewID();

            $db->where('username', "transfer");
            $db->where('type', "Internal");
            $internalID = $db->getValue('client', 'id');
            if(empty($internalID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00253"][$language] /* No result found */, 'data' => "");

            // Sender to internal
            $result = Cash::insertTAccount($transferID, $internalID, $creditType, $transferAmount, "Transfer Out", $belongID, "", $db->now(), $batchID, $transferID, $remark, '');
            if(!$result)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $transferID.$translations["E00254"][$language]."1" /* Credit transfer failed */, 'data' => "");
            
            // Internal to receiver
            $db->where("belong_id",$belongID);  
            $returnCredit = $db->get("credit_transaction",NULL,"type,amount");
            $transactionID = $db->getTransactionID();
            foreach($returnCredit AS $data){
                $result = Cash::insertTAccount($internalID, $receiverID, $data['type'], $data['amount'], "Transfer In", $belongID, "", $db->now(), $batchID, $receiverID, $remark,"","",$transactionID);
                if(!$result)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $receiverID.$translations["E00255"][$language]."2" /* Credit transfer failed */, 'data' => "");
            }


            // insert activity log
            $titleCode    = 'T00002';
            $activityCode = 'L00002';
            $transferType = 'Transfer';
            $activityData = array('sender'   => $senderName,
                                  'credit'   => $creditType,
                                  'receiver' => $receiverName
                                 );

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $transferID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            $performanceParams["eventSection"] = "Transfer Credit";
            $performanceParams["fromUser"] = $senderName;
            $performanceParams["toUser"] = $receiverName;
            $performanceParams["creditType"] = $creditType;
            $performanceParams["amount"] = $transferAmount;

            Message::recordPerformance($performanceParams);
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => str_replace("%%credit%%", $creditDisplay, $translations["B00136"][$language]) /* Credit has been successfully transferred. */, 'data' => "");
        }

        public function getWithdrawalDetail($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "client";
            $joinTableName  = "client_setting";
            $creditType     = $params['creditType'];
            $column         = array(
                                    "client_setting.value",
                                    "client.name",
                                    "client.username"
                                   );
            $userId         = $params['clientId'];
            if (empty($userId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00257"][$language] /* User id is invalid */, 'data' => "");

            if(empty($creditType))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            $adminRoleID        = Cash::$creatorID;
            //Get admin's roleID
            $db->where("id", $adminRoleID);
            $roleID=$db->getValue("admin","role_id");

            $permissionsID = $db->subQuery();
            $permissionsID->where("role_id",$roleID);
            $permissionsID->where("disabled",0);
            $permissionsID->get('roles_permission',null,'permission_id');
            $db->where('id',$permissionsID,'IN');
            $permissionsRes=$db->get('permissions',null,'name');
            foreach ($permissionsRes as $key => $value) {
                $permissionsArray[]=$value['name'];

            }
                    
            $creditID = $db->subQuery();
            $creditID->where("name", $creditType);
            $creditID->get("credit", null, "id");
            $db->where("credit_id", $creditID, "in");
            $result = $db->get("credit_setting", null, "name,".strtolower($db->userType)." AS permission");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            foreach($result as $value) {
                $permissions[$value['name']] = $value['permission'];
            }

            // If role has no access
            if (!in_array($creditType.' Withdrawal', $permissionsArray)){
                $permissions['isWithdrawable']=0;
            }
            if (!in_array($creditType.' Adjustment', $permissionsArray)) {
                $permissions['isAdjustable']=0;
            }
            if (!in_array($creditType.' Transfer', $permissionsArray)) {
                $permissions['isTransferable']=0;
            }
            $data['permissions'] = $permissions;
            unset($result);

            $db->join($joinTableName, $joinTableName . ".client_id = " . $tableName . ".id", "LEFT");
            $db->where($tableName . ".id", $userId);
            $db->where($joinTableName . ".name", $creditType);
            $result = $db->get($tableName, NULL, $column);

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00137"][$language] /* No results found */, 'data' => $data);

            $data['fullname']   = $result[0]['name'];
            $data['username']   = $result[0]['username'];
            $data['balance']    = $result[0]['value'];
            $countryParam       = array('pagination' => "No");
            $countryList        = Country::getCountriesList($countryParam);
            $bankParam          = array();
            $bankList           = Country::getWithdrawalBankList($bankParam);

            if (!empty($countryList))
                $data['countryList'] = $countryList['data']['countriesList'];

            if (!empty($bankList))
                $data['bankList'] = $bankList['data'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addNewWithdrawal($params) {
            $db = MysqliDb::getInstance();

            $language               = General::$currentLanguage;
            $translations           = General::$translations;

            $typeArr = array('bank','crypto');
            $type                   = trim($params['type']); // bank or crypto
            $amount                 = trim($params['amount']);
            $walletAddress          = trim($params['walletAddress']);
            $creditType             = trim($params['creditType']);
            $cryptoType             = trim($params['cryptoType']);
            $transactionPassword    = $params['transactionPassword'];
            // $countryID              = $params['countryID'];
            $bankID                 = $params['bankID'];
            $accountNumber          = $params['accountNumber'];
            $branch                 = $params['branch'];
            $bankCity               = $params['bank_city'];
            $date                   = $params['date'];
            $autoWithdrawal         = $params['autoWithdrawal'];
            $otpType                = trim($params['otpType']);
            $otpCode                = trim($params['otpCode']);
            // $tag                    = $params['tag'];

            $site = $db->userType;
            $clientID = $db->userID;

            $passwordEncryption  = Setting::getMemberPasswordEncryption();

            /* check type array */
            if(!in_array($type, $typeArr)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Type", 'data' => $data);
            }
            /* END check type array */

            /* Check empty param */
            if(empty($clientID) || empty($creditType))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => '');

            // if($creditType == "rewardCredit"){
            //     $db->where("type", $creditType);
            //     $allCreditRes = $db->map("id")->get("credit",null, "id, name, type, translation_code");
            //     foreach ($allCreditRes as $allCreditRow) {
            //         $creditIDAry[$allCreditRow['id']] = $allCreditRow['id'];
            //     }

            //     $db->where("credit_id", $creditIDAry,"IN");
            //     $db->where("name", "isWithdrawable");
            //     $db->where("value", "0", ">");
            //     $isWithdrawableRes = $db->map("credit_id")->get("credit_setting", null, "credit_id");
            //     foreach ($isWithdrawableRes as $isWithdrawableRow) {
            //         unset($creditType);
            //         $creditType = $allCreditRes[$isWithdrawableRow]['name'];
            //     }
            // }
            $db->where("name", $creditType);
            $db->orWhere("type", $creditType);
            $creditResult = $db->getOne("credit", "id, name, type, translation_code");

            $creditType = $creditResult['name'];

            $allCreditRes = $db->map("id")->get("credit",null, "id, name");

            $creditArr = Cash::$paymentCredit;
            $tmpCreditType = $creditArr[$creditType];

            $db->where("name",$tmpCreditType, "IN");
            $db->orWhere("type",$tmpCreditType, "IN");
            $creditIDAry = $db->map('id')->get("credit",NULL,"id"); 

            $db->where("credit_id", $creditIDAry,"IN");
            $db->where("name", "isWithdrawable");
            $db->where("value", "0", ">");
            $isWithdrawableRes = $db->map("credit_id")->get("credit_setting", null, "credit_id");
            foreach ($isWithdrawableRes as $isWithdrawableRow) {
                // unset($creditType);
                $creditTypeBalanceGet[$allCreditRes[$isWithdrawableRow]] = $allCreditRes[$isWithdrawableRow];
            }
            $balance = Cash::getBalance($clientID, $creditTypeBalanceGet[$creditType], $date, false);

            /* withdraw must take all balance */
            // $balance = Cash::getBalance($clientID, $creditType, $date);
            if($autoWithdrawal) $amount = $balance;



            $db->where('name', $creditType);
            $db->orWhere('type', $creditType);
            $creditID = $db->getValue('credit', 'id');

            $db->where('credit_id', $creditID);
            $db->where('name', 'isWithdrawAll');
            $isWithdrawAll = $db->getValue('credit_setting', 'reference');

            if($isWithdrawAll) {
                $db->where('client_id', $clientID);
                $db->where('credit_type', $creditType);
                $db->where('status', 'Failed', '!=');
                $withdrawalCount = $db->getValue('mlm_withdrawal', 'count(id)');

                if($withdrawalCount >= $isWithdrawAll) {
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00952'][$language] /* Witdrawal Limit Reached */, 'data' => "");
                }

                $amount = $balance;
            }

            if(empty($amount)) {
                $errorFieldArr[] = array(
                                            'id'  => 'amountError',
                                            'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                        );
            }
            else if($amount <= 0 || !is_numeric($amount)) {
                $errorFieldArr[] = array(
                                            'id'  => 'amountError',
                                            'msg' => $translations["E00262"][$language] /* Invalid amount */
                                        );
            }

            if($type == 'bank'){

                if(empty($bankID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'bankError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );
                }

                if(empty($accountNumber)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'accountNoError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );
                }

                if(empty($branch)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'branchError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );
                }

                if(empty($bankCity)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'cityError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );

                }
            }else{

                if(empty($walletAddress)){
                    $errorFieldArr[] = array(
                                                'id'  => 'walletAddressError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );
                }

                if(empty($cryptoType)){
                    $errorFieldArr[] = array(
                                                'id'  => 'selectCryptoCreditTypeError',
                                                'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                            );
                }
            }
            /* END Check empty param */

            /* check if user exist */
            $db->where('id', $clientID);
            $clientDetails = $db->getOne('client', 'username,phone,email,dial_code');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client no found */, 'data' => "");
            $username = $clientDetails['username'];
            $phoneNumber = $clientDetails['dial_code'].$clientDetails['phone'];
            $email = $clientDetails['email'];


            if($site == "Member"){
                $db->where('id', $clientID);
                $result = $db->getOne('client', 'password');

                if (empty($result)) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

                $encryptedPassword = Setting::getEncryptedPassword($transactionPassword);

                if (empty($transactionPassword)) {
                    $errorFieldArr[] = array(
                                                'id'  => "transactionPasswordError",
                                                'msg' => $result['statusMsg'] /* Invalid value */
                                            );
                } else {
                    // Check password encryption
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(!password_verify($transactionPassword, $result['password'])) {
                            $errorFieldArr[] = array(
                                                        'id'  => "transactionPasswordError",
                                                        'msg' => $translations["E00282"][$language] /* Invalid transaction password */
                                                    );
                        }
                    } else {
                        if ($encryptedPassword != $result['password']) {
                            $errorFieldArr[] = array(
                                                        'id'  => "transactionPasswordError",
                                                        'msg' => $translations["E00282"][$language] /* Invalid transaction password */
                                                    );
                        }
                    }
                }

                /*$verifyCode = Otp::verifyOTPCode($clientID,$otpType,"withdrawal",$otpCode);

                if($verifyCode["status"] != "ok"){
                    $errorFieldArr[] = array(
                                                'id'  => 'otpCodeError',
                                                'msg' => $verifyCode['statusMsg']
                                            );
                }else{
                    $otpID = $verifyCode['data'];
                }*/
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            /* check credit setting */
            $creditSettingArr = Setting::getCreditSetting($creditType);
            $creditSetting = array_values($creditSettingArr);

            if($creditSetting[0]['isWithdrawable']['value'] != "1" && $creditSetting[0]['isWithdrawable']['value'] != "2" && $creditSetting[0]['isWithdrawable']['value'] != "3")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00265"][$language] /* Withdrawal failed */, 'data' => "");

            if($type == 'bank') {
                if (!in_array($creditSetting[0]["isWithdrawable"]["value"], array("1", "3"))) {
                    return array('status'=>'error','code'=>1,'statusMsg'=>'Invalid withdrawal type.','data'=>array('field'=>'withdrawalType'));
                }
            } else if ($type == 'crypto') {
                if (!in_array($creditSetting[0]["isWithdrawable"]["value"], array("2", "3"))) {
                    return array('status'=>'error','code'=>1,'statusMsg'=>'Invalid withdrawal type.','data'=>array('field'=>'withdrawalType'));
                }
            }

            // get admin fee
            $db->where("name",$creditType);
            $db->orWhere("type",$creditType);
            $creditIDAry = $db->map('id')->get("credit",NULL,"id"); 
            if($creditIDAry){
	            /*$db->where("credit_id", $creditIDAry,"IN");
	            $db->where("name","withdrawalAdminFee");
	            $db->groupBy("value");
	            $db->groupBy("reference");
	            // $db->orderBy("convert(`value`, decimal)","DESC");
	            $creditAdminCharges = $db->get("credit_setting", NULL, "value, reference,type");
                foreach ($creditAdminCharges as $creditAdminChargesRow) {
                    $adminFeeAry[$creditAdminChargesRow['reference']]['adminFee'] = $creditAdminChargesRow['value'];
                    $adminFeeAry[$creditAdminChargesRow['reference']]['type'] = $creditAdminChargesRow['type'];
                }*/

                $db->where("credit_id", $creditIDAry,"IN");
                $db->where("name","withdrawalAdminFee");
                $adminFeePercentage = $db->getOne("credit_setting","reference")['reference'];

                $db->where('credit_id', $creditIDAry, 'IN');
                $db->where('name', 'minWithdrawal');
                $minWithdrawal = $db->getValue('credit_setting', 'value');
            }

            if(!$params['withdrawalType']) $params["withdrawalType"] = "manual";
        	// $adminChargesSett = $params["withdrawalType"] == "manual" ? "manualWithdrawAdminFee" : "autoWithdrawAdminFee";

         //    $adminFee = $creditSetting[0][$adminChargesSett]['value'];
         //    $adminFeeType = $creditSetting[0][$adminChargesSett]['type'];

            if($type == 'bank'){
            	$db->where("id", $bankID);
                $countryID = $db->getValue("mlm_bank", "country_id");

            	//system setting withdrawal Rate base on country
            	/*$db->where("reference", $countryID);
            	$db->where("type", $params["withdrawalType"]);
            	$db->where("name","bankWithdrawalFee");
            	$res = $db->getOne("system_settings","value,type"); 
            	$adminFee = $res['value'];
            	$adminFeeType = "percentage";*/
                // $adminFee = $adminFeeAry['max']['adminFee'];
                // $adminFeeType = $adminFeeAry['max']['type'];

                $adminFee = $adminFeePercentage;
            }else{
            	//system setting withdrawal Rate
            	// $cryptoTypeMap = Self::cryptoMapping($cryptoType);
            	// $db->where("reference", $cryptoTypeMap);
            	// $db->where("type", $params["withdrawalType"]);
            	// $db->where("name","cryptoWithdrawalFee");
            	// $res = $db->getOne("system_settings","value,type");
            	// $adminFee = $res['value'];
            	// $adminFeeType = "percentage";
                // $adminFee = $adminFeeAry['max']['adminFee'];
                // $adminFeeType = $adminFeeAry['max']['type'];

                $adminFee = $adminFeePercentage;
            }

            //if($adminFeeType == "percentage") $adminChargeInPercentage = $adminFee;
            /* END check credit setting */

            /* check balance */
            // $balance = Cash::getBalance($clientID, $creditType);

            // if($amount > $balance)
            if($amount > $balance || $amount == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00266"][$language] /* Insufficient balance */, 'data' => "");
            /* END check balance */

            // $minWithdrawal = $creditSetting[0]["isWithdrawable"]["reference"];

            if($minWithdrawal && $minWithdrawal != 0 && $autoWithdrawal != 1){
	            // $withdrawalTypeFrom = $creditSetting[0]['withdrawalTypeFrom']['value'];
            	// $withdrawalTypeTo = $creditSetting[0]['withdrawalTypeTo']['value'];
            	// if($withdrawalTypeFrom != $withdrawalTypeTo){
            	// 	$db->where("type",$withdrawalTypeFrom);
            	// 	$db->orderBy("created_at","DESC");
            	// 	$fromRate = $db->getValue("mlm_coin_rate","rate");
            	// 	$db->where("type",$withdrawalTypeTo);
            	// 	$db->orderBy("created_at","DESC");
            	// 	$toRate = $db->getValue("mlm_coin_rate","rate");
            	// 	$rate = $fromRate / $toRate;
            	//  $amount = $amount * $rate;
            	// }
            	if($amount < $minWithdrawal){
            		return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace(array("%%amount%%"), array($minWithdrawal), $translations["E00832"][$language]), 'data' => "");
            	}
            }

            $withdrawalMultiplier = $creditSetting[0]['withdrawalMultiplier']['value'];

            if($withdrawalMultiplier){
            	if($amount%$withdrawalMultiplier != 0){
            		$translations["E00833"][$language] = "Withdrawal amount need to be multiplier of %%amount%%.";
            		return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace(array("%%amount%%"), array($withdrawalMultiplier), $translations["E00833"][$language]), 'data' => "");
            	}
            } 

            $freeWtihdrawal = $creditSetting[0]['1stWithdrawalFree']['value'];

            //1st withdrawal
            // $db->where("client_id",$clientID);
            // $firstWithdrawal =$db->getValue("mlm_withdrawal","id");
            // if(!$firstWithdrawal && $freeWtihdrawal == 1){
            // 	$adminFee = 0;
            // 	$adminChargeInPercentage = 0;
            // }

            /* check system ID */
            $db->where('type', "Internal");
            $db->where("username", "withdrawal");
            $internalID = $db->getValue("client", "id");
            if(empty($internalID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00267"][$language] /* No result found */, 'data' => "");
            /* END check system ID */

            $totalAmount = Setting::setDecimal(($amount), 8);
            // if($adminChargeInPercentage){
            //     $chargePercentage = $adminChargeInPercentage;
            //     $charges = Setting::setDecimal(($amount * $chargePercentage / 100 ),2);

            //     //Take the highest fee
            //     if($charges < $adminFeeAry['min']['adminFee']){
            //         $charges = $adminFeeAry['min']['adminFee'];
            //         $adminFeeType = $adminFeeAry['min']['type'];
            //         $chargePercentage = 0;
            //     }

            // }else{
            // 	if($adminFee) $charges = $adminFee;
            // }
            if($adminFee) $charges = Setting::setDecimal(($amount * $adminFee / 100 ),2);

            /* check currency exchange rate */
            if($type == 'bank'){

                $db->where("country_id", $countryID);
                $db->orderBy("created_at","DESC");
                $liveRateResult = $db->getValue("mlm_currency_exchange_rate", "exchange_rate");

                // $receivableAmount = ($totalAmount - $charges) * $liveRateResult;
                $receivableAmount = ($totalAmount - $charges);
                $receivableAmount = Setting::setDecimal(($receivableAmount), 8);
                $convertedAmount = $receivableAmount * $liveRateResult;
            }else{

            	$cryptoTypeMap = Self::cryptoMapping($cryptoType);
                $db->where("type", $cryptoTypeMap);
                $db->orderBy("created_on",'DESC');
                $liveRateResult = $db->getValue("mlm_coin_rate","rate");
                if(!$liveRateResult) $liveRateResult = 1;

                $receivableAmount = ($totalAmount - $charges);
                $receivableAmount = Setting::setDecimal(($receivableAmount), 8);
                $convertedAmount = $receivableAmount * $liveRateResult;
            }
            /* END check currency exchange rate */

            // $receivableAmount = ($totalAmount - $charges) * $liveRateResult;

            /* prepare data out */
            $data['totalAmount'] = Setting::setDecimal($totalAmount, 8);
            $data['balance'] = $balance;
            $data['receivableAmount'] = Setting::setDecimal(($receivableAmount), 8);
            $data['convertedAmount'] = Setting::setDecimal(($convertedAmount), 8);

            $data['adminFeePercent'] = $adminFee ? $adminFee : 0 ;
            // $data['adminFeeType'] = $adminFeeType;
            $data['adminCharges'] = number_format($charges, 8, '.', '');
            $data['username'] = $username;

            $data['liveRateResult'] = $liveRateResult;
            $data['internalID'] = $internalID;
            $data['otpID'] = $otpID;
            $data['creditTypeBalanceGet'] = $creditTypeBalanceGet;
            /* END prepare data out */

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00138"][$language] /* Successfully submitted withdrawal request */, 'data' => $data);
        }

        public function addNewWithdrawalConfirmation($params) {
            $db = MysqliDb::getInstance();

            $language               = General::$currentLanguage;
            $translations           = General::$translations;

            $type                   = $params['type']; // bank or crypto
            // $amount                 = $params['amount'];
            $walletAddress          = $params['walletAddress'];
            // $clientID               = $params['clientID'];
            $creditType             = $params['creditType'];
            $cryptoType             = $params['cryptoType'];
            $transactionPassword    = $params['transactionPassword'];
            $bankID                 = $params['bankID'];
            $accountNumber          = $params['accountNumber'];
            $branch                 = $params['branch'];
            $bankCity               = $params['bank_city'];
            $transactionDate        = $params['date'];
            $batchID                = $params['batchID'];
            $clientBankID           = $params['client_bank_id'];
            $autoWithdrawal         = $params['autoWithdrawal'];
            $withdrawalType = $autoWithdrawal ? "Auto" : "Manual";

            $site = $db->userType;
            $clientID = $db->userID;
            $db->where("id", $clientID);
            $user = array('user' => $db->getValue("client", "username"));

            if(!$transactionDate) $transactionDate = date("Y-m-d H:i:s");

            /* withdraw must take all balance */
            // $balance = Cash::getBalance($clientID, $creditType, $transactionDate);
            // if($autoWithdrawal) $amount = $balance;
            
            // "countryID"              => $_POST['countryID'],
            // "tag"                    => $_POST['tag'],

            /* verification */
            $verifyInputData = Self::addNewWithdrawal($params,$site);
            if($verifyInputData['status'] != 'ok') return $verifyInputData;

            if($type == 'bank'){

                $walletAddress = '-';
            }else{

                $countryID              = "0";
                $bankID                 = "0";
                $accountNumber          = "-";
                $branch                 = "-";
                $clientBankID           = "0";
            }

            $chargePercentage = $verifyInputData["data"]["adminFeePercent"];
            $username = $verifyInputData["data"]["username"];
            $internalID = $verifyInputData["data"]["internalID"];


            $liveRateResult = $verifyInputData["data"]["liveRateResult"];
            $receivableAmount = $verifyInputData["data"]["receivableAmount"];
            $convertedAmount = $verifyInputData["data"]["convertedAmount"];
            $charges = $verifyInputData["data"]["adminCharges"];
            $totalAmount = $verifyInputData["data"]["totalAmount"];
            $otpID                  = $verifyInputData['data']['otpID'];
            $creditTypeBalanceGet   = $verifyInputData['data']['creditTypeBalanceGet'];

            if($totalAmount <= 0){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00266"][$language] /* Insufficient balance */, 'data' => "");
            }
            
            $data['adminCharges'] = $charges;
            $data['totalAmount'] = $totalAmount;
            $data['receivableAmount'] = $convertedAmount;

            /* get random serial number */
            while(1) {
                $serial_number  = rand(100000, 999999);
                $db->where('serial_number', $serial_number);
                $serialRes      = $db->getOne("mlm_withdrawal", "id");

                if(!empty($serialRes)) continue;
                else break;
            }

            $createdOn = $db->now();
            $belongID = $db->getNewID();
            if(!$batchID) $batchID  = $db->getNewID();

            $insertData = array (
                                    "client_id"         => $clientID,
                                    "amount"            => $totalAmount,
                                    "status"            => "Waiting Approval",
                                    "created_at"        => $transactionDate,
                                    "bank_id"           => $bankID,
                                    "account_no"        => $accountNumber,
                                    "credit_type"       => $creditType,
                                    "crypto_type"       => $cryptoType,
                                    "receivable_amount" => $receivableAmount,
                                    "charges"           => $charges,
                                    "currency_rate"     => $liveRateResult,//$currencyRate,
                                    "withdrawal_type"   => $type,
                                    "converted_amount"  => $convertedAmount,//$currencyRate * ($amount * (1 - $chargePercentage)),
                                    "branch"            => $branch,
                                    "bank_city"         => $bankCity,
                                    "belong_id"         => $belongID,
                                    "batch_id"          => $batchID,
                                    "walletAddress"     => $walletAddress,
                                    "serial_number"     => $serial_number,
                                    "ref_id"            => $clientBankID,
                                    "method"           	=> $withdrawalType
                                );

            // Insert transaction into mlm_withdrawal table
            $id = $db->insert('mlm_withdrawal', $insertData);
            if(empty($id)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00265"][$language] /* Withdrawal failed */, 'data' => "");
            }

            // prevent duplicate
            $db->where('client_id', $clientID);
            $db->where("converted_amount", $convertedAmount);
            $db->where('created_at', $createdOn);
            $db->where('id', $id , '<');
            $checkingDuplicate = $db->getValue("mlm_withdrawal", "id");

            if(!empty($checkingDuplicate)){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00265"][$language] /* Withdrawal failed */, 'data' => "");
            }


            // insert activity log before insert into acc
            $titleCode    = 'T00003';
            $activityCode = 'L00003';
            $transferType = 'Withdraw';
            $activityData = array('user'   => $username,
                                  'credit' => $creditType
                                 );
            // $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // // Failed to insert activity
            // if(!$activityRes)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            $performanceParams["eventSection"] = "Withdrawal";
            $performanceParams["creditType"] = $creditType;
            $performanceParams["amount"] = $totalAmount;
            $performanceParams["adminCharges"] = $charges;
            $performanceParams["receivableAmount"] = $receivableAmount;
            $performanceParams["walletAddress"] = $walletAddress;
            $performanceParams["chargePercentage"] = $chargePercentage;

            Message::recordPerformance($performanceParams);

            // include admin charges
            if($charges){
                $result = Cash::insertTAccount($clientID, $internalID, $creditType, $charges, "Admin Charge", $belongID, "", $transactionDate, $batchID, $clientID, "", "", "", "", "", "", $creditTypeBalanceGet);
            }

            // Insert transaction into acc_credit table
            $result = Cash::insertTAccount($clientID, $internalID, $creditType, $receivableAmount, "Withdrawal", $belongID, "", $transactionDate, $batchID, $clientID, "", "", "", "", $coinRate, "", $creditTypeBalanceGet);

            if(!$result) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00265"][$language] /* Withdrawal failed */, 'data' => "");
            }

            if($otpID){
                $db->where('id',$otpID,'IN');
                $db->update('sms_integration',array('expired_at'=>$db->now()));
            }

            General::insertNotification("withdrawal");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00138"][$language] /* Successfully submitted withdrawal request */, 'data' => "");
        }

        public function convertCreditVerification($params,$userID,$site){
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $fromCredit = $params['fromCredit'];
            $toCredit = $params['toCredit'];
            $amount = $params['amount'];
            $transactionPassword = $params['transactionPassword'];
            $clientID = $userID;

            if(!$fromCredit){
                return array('status' => 'error', 'code' => 2, 'statusMsg' => "Invalid Credit Type." /* Required fields cannot be empty */, 'data' => '');
            }
            $paymentCredit = Cash::$paymentCredit;
            $paymentCreditType = $paymentCredit[$fromCredit];
            $db->where('name',$paymentCreditType,'IN');
            $creditIDRes = $db->get('credit',null,'id');
            foreach ($creditIDRes as $creditIDKey => $creditIDValue) {
                $fromCreditIDAry[] = $creditIDValue['id'];
            }

            $db->where('name',$fromCredit);
            $db->orWhere('type',$fromCredit);
            $checkValidFromCredit = $db->getValue('credit','id');
            if(empty($checkValidFromCredit)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Required fields cannot be empty */, 'data' => '');
            }

            if($checkValidFromCredit){
                $db->where("credit_id",$fromCreditIDAry,"IN");
                $db->where("name","isConvertible");
                $checkConvertCredit = $db->get("credit_setting",null, "value, reference");
                foreach($checkConvertCredit as $creditRow){
                    if($creditRow["value"] == 0){
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Required fields cannot be empty */, 'data' => '');
                    }

                    $minConvertAmount = $creditRow["reference"];
                }
            }

            $db->where('name',$toCredit);
            $db->orWhere('type',$toCredit);
            $checkValidToCredit = $db->get('credit', null, 'id, name');
            if(empty($checkValidToCredit)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Required fields cannot be empty */, 'data' => '');
            }

            if(empty($clientID) || empty($fromCredit) || empty($toCredit))
            return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => '');

            if($site == "Member"){
                if(empty($transactionPassword))
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => '');
            }

            $db->where('id', $clientID);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client no found */, 'data' => "");

            $username = $clientDetails;

            if(empty($amount)) {
                $errorFieldArr[] = array(
                                            'id'  => 'amountError',
                                            'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                        );
            }
            else if($amount <= 0 || !is_numeric($amount)) {
                $errorFieldArr[] = array(
                                            'id'  => 'amountError',
                                            'msg' => $translations["E00262"][$language] /* Invalid amount */
                                        );
            }else if($minConvertAmount > 0 && ($amount < $minConvertAmount)){
                $errorMsg = str_replace("%%amount%%", $minConvertAmount, $translations["E00859"][$language]);
                $errorFieldArr[] = array(
                                            'id'  => 'amountError',
                                            'msg' => $errorMsg /* Minimum convert amount is %%amount%%. */
                                        );
            }

            if($site == "Member"){
                $result = Client::verifyTransactionPassword($clientID, $transactionPassword);

                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                    'id'  => 'transactionPasswordError',
                                    'msg' => $result['statusMsg'] /* Invalid value */
                                );
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            // Validation if the amount entered is greater than the balance user has
            $balance = Cash::getBalance($clientID, $fromCredit);

            if($amount > $balance)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00266"][$language] /* Insufficient balance */, 'data' => "");

            // check convert limit
            $convertibleAmount = Cash::checkCreditLimit($clientID,$toCredit,$amount);
            if($convertibleAmount != $amount) {
                // $sq = $db->subQuery();
	        	// $sq->where("name",$toCredit);
	        	// $sq->getOne("credit","id");
	        	// $db->where("credit_id",$sq);
	        	// $db->where("name","convertCap");
	        	// $limit = $db->getValue("credit_setting","value");

            	$errMsg = str_replace("%%limit%%", $convertibleAmount, $translations["E00879"][$language]) /* Receiver cannot be yourself */;
            	return array('status' => "error", 'code' => 1, 'statusMsg' => $errMsg /*  */, 'data' => "");
            }

            $result = $db->get("credit" , NULL , "id,name,type,translation_code");
            foreach($result AS $key => $value){
                $creditDataByID[$value['id']] = $value;
                $creditDataByName[$value['name']] = $value;
                $creditDataByType[$value['type']] = $value;
            }

            $db->where("name","convertTo");
            $db->where("member","1");
            $db->where("value",$toCredit);
            $db->where("credit_id",$fromCreditIDAry, 'IN');
            $creditRate = $db->getValue("credit_setting","reference");

            $data["balance"] = $balance;
            $data["fromCredit"] = $translations[$creditDataByType[$fromCredit]['translation_code']][$language];
            $data["toCredit"] = $translations[$creditDataByType[$toCredit]['translation_code']][$language];
            $data["amount"] =  Setting::setDecimal($amount);
            // $data["convertibleAmount"] =  Setting::setDecimal($convertibleAmount);

            $data["coinRate"] = Setting::setDecimal($creditRate);
            $data["convertAmount"] = Setting::setDecimal($data["amount"] * $data["coinRate"]);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function convertCreditConfirmation($params,$userID,$site){
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $fromCredit = $params['fromCredit'];
            $toCredit = $params['toCredit'];
            $amount = $params['amount'];
            $transactionPassword    = $params['transactionPassword'];
            // $walletAddress = $params['walletAddress'];
            $clientID = $userID;

            $verifyResult = Self::convertCreditVerification($params,$userID,$site);
            if($verifyResult['status']!='ok'){
                return $verifyResult;
            }

            $db->where("type",$toCredit);
            $firstSubCredit = $db->getValue("credit","name");
            if($firstSubCredit) $toCredit = $firstSubCredit;            

            // $toCoinRate = $creditDataByName[$toCredit]['rate'];
            $toCoinRate = $verifyResult['data']['coinRate'];
            $fromCoinRate = $verifyResult['data']['coinRate'];
            // $convertibleAmount = $verifyResult['data']['convertibleAmount'];
            // $convertAmount = $getLiveRate['data']['coinAmount'][$toCredit];
            // $convertAmount = $amount;
            // $amount = $convertibleAmount;
            // $convertAmount = $convertibleAmount;
            $convertAmount = $verifyResult['data']['convertAmount'];
            $amount = $convertAmount;
            $convertAmount = $convertAmount;
            
            $belongID = $db->getNewID();
            $adminPercent = 0;

            // Get the default account id from the client table
            $db->where("username", "creditSales");
            $receiverId = $db->getValue("client", "id");

            Cash::insertTAccount($clientID, $receiverId, $fromCredit, $amount, "Convert Credit", $belongID, "", $db->now(), "", $clientID,"","",$fromCoinRate);
            Cash::insertTAccount($receiverId, $clientID, $toCredit, $convertAmount, "Convert Credit", $belongID, "", $db->now(), "", $clientID, "","",$toCoinRate, "", $toCoinRate); 

            // $db->where("name", "isDailyLimitWallet");
            // $db->where("value", 1);
            // $limitWalletRow = $db->getOne("credit_setting", "credit_id, reference");
            // if($limitWalletRow){
            //     $db->where("id", $limitWalletRow["credit_id"]);
            //     $limitWalletType = $db->getValue("credit", "name");
            //     if($fromCredit == $limitWalletType){
            //         Trading::updateBuySellLimit("reduce", $clientID, $amount, "", "", $belongID);
            //     }else if($toCredit == $limitWalletType){
            //         Trading::updateBuySellLimit("add", $clientID, $convertAmount, "", "", $belongID);
            //     }
            // }
            
            $performanceParams["eventSection"] = "Convert Credit";
            $performanceParams["amount"] = $amount;
            $performanceParams["convertAmount"] = $convertAmount;
            $performanceParams["fromCredit"] = $fromCredit;
            $performanceParams["toCredit"] = $toCredit;            

            Message::recordPerformance($performanceParams);

            $db->where("type", $toCredit);
            $creditLangCode = $db->getValue("credit", "translation_code");

            $creditDisplay = $translations[$creditLangCode][$language];

            $statusMsg = str_replace(array("%%credit%%"), array($creditDisplay), $translations['B00395'][$language]);
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $statusMsg /* Successfully Converted to %%credit%%. */, 'data' => '');
        }

        public function updateWithdrawalStatus($params,$userID,$site){
        	$db = MysqliDb::getInstance();

            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $tableName          = "mlm_withdrawal";
            $withdrawalId       = $params['withdrawalId'];
            $status             = trim($params['status']);
            $remark             = trim($params['remark']);
            $statusArr          = array("Approve","Reject","Cancel","Pending");
            $userType           = $db->userType;

            // no id
            if(!$withdrawalId) return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data' => "");
            // no status
            if (!$status) return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00189"][$language] /* Data is invalid */, 'data' => "");
            // invalid status
            if (!in_array($status, $statusArr)) return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00189"][$language] /* Data is invalid */, 'data' => "");
            //	member can cancel only
            if ($status != "Cancel" && $site == "Member")return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00189"][$language] /* Data is invalid */, 'data' => "");

            $db->where("id",$withdrawalId);
            $withdrawalData = $db->getOne($tableName,"id, client_id, amount, converted_amount, status, credit_type, batch_id, belong_id, withdrawal_type, crypto_type, walletAddress");
            // withdrawal not found
            if(!$withdrawalData)return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00189"][$language] /* Data is invalid */, 'data' => "");
            // cannout update if status not Waiting Approval Or pending
            if(!in_array($withdrawalData['status'], array("Waiting Approval", "Pending", "Failed"))){
                return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00396"][$language] /* Data is invalid */, 'data' => "");
            }
            if($withdrawalData['status'] == "Pending" && $site == "Member")return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00834"][$language] /* Data is invalid */, 'data' => "");

            $creditType  = $withdrawalData['credit_type'];
            $cryptoType  = $withdrawalData['crypto_type'];
            $clientID  = $withdrawalData['client_id'];
            $amount  = $withdrawalData['amount'];
            $convertedAmount = $withdrawalData['converted_amount'];
            $belongID  = $withdrawalData['belong_id'];
            $withdrawalType  = $withdrawalData['withdrawal_type'];
            $walletAddress = $withdrawalData['walletAddress'];

            $db->where('username', "withdrawal");
            $db->where('type', "Internal");
            $internalID = $db->getValue('client', 'id');

            switch($status){
            	case "Approve":
            		/*if($withdrawalType == 'crypto'){    
                        $db->where('withdraw_id', $withdrawalId);
                        $db->where('status', 'failed', '!=');
                        $checkQueue = $db->getValue('mlm_crypto_queue', 'COUNT(id)');
                        if($checkQueue > 0){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Withdrawal has been processed', 'data' => "");
                        }

                        $queueType = "cryptoWithdrawalPayout";  
                        $insertQueueData = array(   
                            "queue_type" => $queueType, 
                            "processed" => 0,   
                            "status" => "waiting",  
                            "amount" => $convertedAmount,    
                            "credit_type" => $cryptoType,   
                            "wallet_address" => $walletAddress, 
                            "withdraw_id" => $withdrawalData['id'], 
                            "batch_id" => $batchID, 
                            "belong" => $belongID,  
                            "created_at" => $currentTime    
                        );  
                        $db->insert("mlm_crypto_queue", $insertQueueData); 
                    }*/
        			$statusMsg = $translations["B00130"][$language];
            		break;	
            	case "Reject":
            	case "Cancel":
            		$groupID = $db->getGroupID();

                    $db->where('belong_id',$belongID);
                    $db->groupBy('type');
                    $returnCredit = $db->get('credit_transaction',null,'type,Sum(amount) AS amount');
                    foreach ($returnCredit as $returnCreditRow) {
                		$data = Cash::insertTAccount($internalID, $clientID, $returnCreditRow['type'], $returnCreditRow['amount'], "Withdrawal Return", $belongID, "", $db->now(), $batchID, $clientID, $remark,"","",$groupID);
                		if(!$data) return array('status' => "error", 'code' => 0, 'statusMsg' => "Withdrawal Failed" /* Data is invalid */, 'data' => "");
                    }
            		$statusMsg = $translations["B00127"][$language];
            		break;
            	default : 
            		break;
            }

            $tblName = ($site == "Member" ? "client" : "admin");
        	$db->where("id",$userID);
            $username = $db->getValue($tblName,"username");
            $updaterName = array('user' => $username);

            // update table
            $updateData = array(
            					"status" => $status,
            					"approved_at" => date("Y-m-d H:i:s"),
            					"updater_id" => $userID,
            					"updater_username" => $username,
            					"remark" => $remark,
            					);

            // merge user name, update data record
            $activityData = array_merge($updaterName, $updateData);

            //insert activity log
            $activityRes = Activity::insertActivity('Update Withdrawal Status', 'A01508', 'L00035', $activityData, $clientID, $userID, $userType);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");

            $db->where("id",$withdrawalId);
            $res = $db->update($tableName , $updateData);
            if(!$res) return array('status' => "error", 'code' => 0, 'statusMsg' => "Update Failed", 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>  $statusMsg, 'data' => "");
        }

    	public function batchUpdateWithdrawalStatus($params,$userID,$site) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $withdrawIDAry     = $params['checkedIDs'];
            $status          = trim($params['status']);
            $remark          = $params['remark'] ? $params['remark'] : "";

            if(empty($withdrawIDAry))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00395"][$language] /* No check box selected. */, 'data' => "");

            $statusArr = array("Approve","Reject","Waiting Approval","Cancel","Pending");

            if(empty($status) || (!in_array($status, $statusArr)))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid status. */, 'data' => "");

            if($status == "Approve"){
                /*$db->where("id", $withdrawIDAry,"IN");
                $db->groupBy("credit_type");
                $totalWithdrawalAmount = $db->map('credit_type')->get("mlm_withdrawal", NULL, 'credit_type, SUM(converted_amount)');

                foreach ($totalWithdrawalAmount as $key => $value) {
                    $getCryptoBalanceParams['creditType'] = $key;
                    $cryptoBalance = CryptoPG::getFundOutWalletBalance($getCryptoBalanceParams);

                    $db->where('credit_type', $key);
                    $db->where('status', array('waiting', 'pending'), 'IN');
                    $db->groupBy('credit_type');
                    $queueRecord = $db->getOne('mlm_crypto_queue', 'SUM(amount) as amount, COUNT(id) as num_record');

                    $availabeBalance = $cryptoBalance['balance'] - ($queueRecord['amount'] * 0.005 + ($queueRecord['num_record'] * 2));
                    if($value > $availabeBalance){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "Insufficient Balance for".$key, 'data' => "");
                    }
                }*/
            }
            
            $dataIn['remark'] = $remark;
            $dataIn['status'] = $status;
            foreach ($withdrawIDAry as $key => $value) {
            	if(!$value)continue;
                $dataIn['withdrawalId'] = $value;
                $result = Self::updateWithdrawalStatus($dataIn,$userID,$site);
            }

            if($result['status'] == 'error'){
                return $result;
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $value, 'convertRate'=> $value['convertRate']);
            }
        }

        function autoWithdrawal($clientID, $creditType, $site, $date, $batchID){
            
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $db->where("client_id", $clientID);
            $db->where("name", "isAutoWithdrawal");
            $clientWithdrawalRes = $db->getOne("client_setting", null, "value,name,reference,type");

            if(empty($clientWithdrawalRes) || $clientWithdrawalRes["value"] != "1"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "This Client Did Not Turn On Auto Withdrawal", 'data'=> "");
            }

            $db->where("name", "Auto Withdrawal");
            $rightsID = $db->getValue("mlm_client_rights", "id");

            if($rightsID){
                $db->where('rights_id', $rightsID);
                $db->where('client_id', $clientID);
                $check = $db->getValue('mlm_client_blocked_rights', 'id');

                if($check){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "This Client Auto Withdrawal is Locked By Admin", 'data'=> "");
                }    
            }

            $params["type"]                   = $clientWithdrawalRes["type"];
            $params["clientID"]               = $clientID;
            $params["creditType"]             = $creditType;

            $db->where("client_id", $clientID);
            $db->where("status", "Active");

            $reference = $clientWithdrawalRes["reference"];
            if($clientWithdrawalRes["type"] == "bank")
            {
                $db->where("bank_id", $reference);
                $bankRes = $db->getOne("mlm_client_bank",null,"id, bank_id, account_no, branch");

                if(empty($bankRes)) return array('status' => "error", 'code' => 1, 'statusMsg' => "Bank Not Found", 'data'=> "");

                $params['client_bank_id'] = $bankRes["id"];
                $params['bankID']         = $bankRes["bank_id"];
                $params['accountNumber']  = $bankRes["account_no"];
                $params['branch']         = $bankRes["branch"];
            }
            else if($clientWithdrawalRes["type"]=="crypto")
            {
                $db->where("credit_type", $reference);
                $walletRes = $db->getOne("mlm_client_wallet_address",null,"id, credit_type, info");

                if(empty($walletRes)) return array('status' => "error", 'code' => 1, 'statusMsg' => "Wallet Address Not Found", 'data'=> "");

                $params['cryptoType']             = $walletRes["credit_type"];
                $params['walletAddress']          = $walletRes["info"];
            }
            else
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Wallet Address Not Found", 'data'=> "");
            }

            if($date) $params['date'] = $date;
            $params['batchID'] = $batchID;
            $params['autoWithdrawal'] = 1;
            $withdrawalResult = self::addNewWithdrawalConfirmation($params,$site);

            return $withdrawalResult;
        }

        function cryptoMapping($crypto){
        	switch ($crypto) {
        		case 'tether':
        			$code = "USDT";
        			break;
        		case 'bitcoin':
        			$code = "BTC";
        			break;
    			case 'ethereum':
        			$code = "ETH";
        			break;
        		default:
        			$code = $crypto;
        			break;
        	}
        	return $code;
        }

        public function purchasePC($params,$site){
        	$db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $creditType = trim($params['creditType']);
            $amount = trim($params['amount']);
            $username = trim($params['username']);
            $remark = trim($params["remark"]);

            // credit checking
            $db->where("name",$creditType);
            $res = $db->getOne("credit","id,translation_code");
            $creditID = $res['id'];
            $creditName = $translations[$res['translation_code']]['english'];
            if(!$creditType) return array('status' => "error", 'code' => 1, 'statusMsg' => "creditType cannot empty", 'data'=> "");
            if(!$creditID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00195'][$language], 'data'=> "");
            $db->where("credit_id",$creditID);
            $db->where("name","isPurchasable");
            $db->where("value","1");
            $isPurchasable = $db->getValue("credit_setting","value");
            if(!$isPurchasable) return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit not Allow to purchase", 'data'=> "");
            // amount checking
            if(!$amount) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00224'][$language]/* Inavalid Amount*/, 'data'=> "");
            if(!is_numeric($amount)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00224'][$language]/* Inavalid Amount*/, 'data'=> "");
            // username checking
            if(!$username)return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00848'][$language]/*Please insert username*/, 'data'=> "");
            $db->where("username",$username);
            $clientID = $db->getValue("client","id");
            if(!$clientID)return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00819'][$language]/* Inavalid username*/, 'data'=> "");

            $db->where("username","creditSales");
            $internalID = $db->getValue("client","id");

            $subject = "Purchase ".$creditName;
            $belongID = $db->getNewID();
            $batchID = $db->getNewID();
            $data = Cash::insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $belongID, "", $db->now(), $batchID, $clientID, $remark);

            if(!$data) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00847'][$language] /* Purchase failed. */, 'data' => "");

            $titleCode = "T00019"; //Purchase Credit
            $activityCode = "L00007";
			$activityData = array('user'   => $username,
                                  'credit' => $creditType
                                 );
            $activityRes = Activity::insertActivity($subject, $titleCode, $activityCode, $activityData, $creditID);

            // Failed to insert activity
            if(!$activityRes) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00277"][$language]/*Purchase successfully*/, 'data'=> "");
        }

    	public function getAvailablePurchaseCredit($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $companyAccount = Setting::$systemSetting["companyAccount"];
            $internalDecimalFormat = Setting::$systemSetting["internalDecimalFormat"];

            $db->where("username",$companyAccount);
            $companyAccountData = $db->getOne("client","id, member_id, name");
            $companyAccountID = $companyAccountData['id'];
            if(!$companyAccountID) return array('status' => "error", 'code' => 1, 'statusMsg' => "$companyAccount account not found", 'data'=> "");

            $db->where("name","isPurchasable");
            $db->where("value","1");
            $validCreditID = $db->map('credit_id')->get("credit_setting",null,"credit_id");

            $db->where("id",$validCreditID,"IN");
            $res = $db->get("credit",NULL,"name,translation_code,admin_translation_code");
            foreach($res AS $row){
                unset($temp);
                $temp['memberDisplay'] = $translations[$row['translation_code']][$language];
                $temp["adminDisplay"] = $translations[$row['admin_translation_code']][$language];
                $temp["value"] = $row['name'];

                $balance = Cash::getBalance($companyAccountID, $row['name']);
                $temp["balance"] = $balance < 0 ? 0 :  number_format($balance,$internalDecimalFormat,".","");
                $temp["fromUsername"] = $companyAccountData['member_id'];
                $temp["fromName"] = $companyAccountData['name'];
                $creditData[$row['name']] = $temp;
            }

            $data["credit"] = $creditData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => ""/*Purchase successfully*/, 'data'=> $data);
        }

        public function purchaseCredit($params,$site){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $companyAccount = Setting::$systemSetting["companyAccount"];

            $creditType = trim($params['creditType']);
            $amount = trim($params['amount']);
            $username = trim($params['username']);
            $remark = trim($params["remark"]);
            // $companyAccount = "company08";
            // credit checking
            $db->where("name",$creditType);
            $res = $db->getOne("credit","id,translation_code");
            $creditID = $res['id'];
            $creditName = $translations[$res['translation_code']]['english'];
            if(!$creditType) return array('status' => "error", 'code' => 1, 'statusMsg' => "creditType cannot empty", 'data'=> "");
            if(!$creditID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00195'][$language], 'data'=> "");
            $db->where("credit_id",$creditID);
            $db->where("name","isPurchasable");
            $db->where("value","1");
            $isPurchasable = $db->getValue("credit_setting","value");
            if(!$isPurchasable) return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit not Allow to purchase", 'data'=> "");
            // amount checking
            if(!$amount) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00224'][$language]/* Inavalid Amount*/, 'data'=> "");
            if(!is_numeric($amount)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00224'][$language]/* Inavalid Amount*/, 'data'=> "");
            // username checking
            // if(!$username)return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['M01149'][$language]/*Please insert username*/, 'data'=> "");

            // member id into username
            if(!$username)return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['B00459'][$language]/*Please insert member ID.*/, 'data'=> "");

            // $db->where("username",$username);
            $db->where("member_id",$username);
            $clientID = $db->getValue("client","id");
            if(!$clientID)return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00819'][$language]/* Inavalid username*/, 'data'=> "");

            $db->where("username","creditSales");
            $internalID = $db->getValue("client","id");

            $db->where("username",$companyAccount);
            $companyAccountID = $db->getValue("client","id");
            if(!$companyAccountID) return array('status' => "error", 'code' => 1, 'statusMsg' => "company account not found", 'data'=> "");
            $balance = Cash::getBalance($companyAccountID, $creditType);
            if($balance <  $amount) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00266'][$language]/* Insufficient balance*/, 'data'=> "");


            $subject = "Purchase ".$creditName;
            $belongID = $db->getNewID();
            $batchID = $db->getNewID();
            Cash::insertTAccount($companyAccountID, $internalID, $creditType, $amount, $subject, $belongID, "", $db->now(), $batchID, $companyAccountID, $remark,"",$username);
            $data = Cash::insertTAccount($internalID, $clientID, $creditType, $amount, $subject, $belongID, "", $db->now(), $batchID, $clientID, $remark,"",$companyAccount);

            if(!$data) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00989'][$language] /* Purchase failed. */, 'data' => "");

            $titleCode = "T00019"; //Purchase Credit
            $activityCode = "L00007";
            $activityData = array('user'   => $username,
                                  'credit' => $creditType
                                 );
            $activityRes = Activity::insertActivity($subject, $titleCode, $activityCode, $activityData, $db->userID);

            // Failed to insert activity
            if(!$activityRes) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00277"][$language]/*Purchase successfully*/, 'data'=> "");
        }

		public function updateAutowitdrawalStatus($params,$clientID){
			$db = MysqliDb::getInstance();
	        $language = General::$currentLanguage;
	        $translations = General::$translations;

	        $transactionPassword  = $params['transactionPassword'];
	        $status  = $params['status'] == 1 ? 1 : 0;

	        $result = Client::verifyTransactionPassword($clientID, $transactionPassword);
            if($result['status'] != "ok") {
                $errorFieldArr[] = array(
                                            'id'  => 'transactionPasswordError',
                                            'msg' => $translations["E00219"][$language] /* Invalid password */
                                        );
            }
            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);
            }

			$db->where("client_id", $clientID);
            $db->where("name","isAutoWithdrawal");
            $id = $db->getValue("client_setting","id");

            if($id){

            	$updateData = array("value"=>$status);
	            $db->where("id",$id);
            	$db->update("client_setting",$updateData);

            }else{
            	$insertData = array(
            							"name"=>"isAutoWithdrawal",
            							"client_id"=>$clientID,
            							"value"=>$status
            						);
            	$db->insert("client_setting",$insertData);
            }

	        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['A00684'][$language]/*Purchase successfully*/, 'data'=> $data);
		}

        public function purchaseCreditVerification($params,$userID,$site){
            $db                  = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;
            $fromCredit          = $params['fromCredit'];
            $toCredit            = $params['toCredit'];
            $amount              = Setting::setDecimal($params['amount']);
            $transactionPassword = $params['transactionPassword'];
            $clientID            = $db->userID;
            $site                = $db->userType;

            //invalid input
            if (empty($amount)) {
                $errorFieldArr[] = array(
                    'id'  => 'amountError',
                    'msg' => $translations["E00221"][$language] /* Required fields cannot be empty */
                );
            }
            
            if ($amount <= 0 || !is_numeric($amount)) {
                $errorFieldArr[] = array(
                    'id'  => 'amountError',
                    'msg' => $translations["E00262"][$language] /* Invalid amount */
                );
            }

            if (empty($fromCredit)) {
                $errorFieldArr[] = array(
                    'id'  => 'fromCreditError',
                    'msg' => $translations["E00221"][$language] /* Required fields cannot be empty */
                );
            }

            if (empty($toCredit)) {
                $errorFieldArr[] = array(
                    'id'  => 'toCreditError',
                    'msg' => $translations["E00221"][$language] /* Required fields cannot be empty */
                );
            }

            if ($site == "Member") {
                if(empty($transactionPassword)) {
                    $errorFieldArr[] = array(
                        'id'  => 'transactionPasswordError',
                        'msg' => $translations["E00221"][$language] /* Required fields cannot be empty */
                    );
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            //check valid client
            $db->where('id', $clientID);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client no found */, 'data' => "");

            $username = $clientDetails;

            $db->where('name',$fromCredit);
            $fromCreditID = $db->getValue('credit','id');
           
            //check fromCredit
            if(empty($fromCreditID)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid Wallet */, 'data' => '');
            }

            //check toCredit
            $db->where('name',$toCredit);
            $toCreditID = $db->getValue('credit', 'id');
            if(empty($toCreditID)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid Wallet */, 'data' => '');
            }

            if($site == "Member"){
                $result = Client::verifyTransactionPassword($clientID, $transactionPassword);

                if($result['status'] != "ok") {
                    $errorFieldArr[] = array(
                                    'id'  => 'transactionPasswordError',
                                    'msg' => $result['statusMsg'] /* Invalid value */
                                );
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            // Validation if the amount entered is greater than the balance user has
            $balance = Cash::getBalance($clientID, $fromCredit);

            $db->where("name","isPurchaseCredit");
            $db->where("member","1");
            $db->where("value",$fromCredit);
            $db->where("credit_id",$toCreditID);
            $creditRate = $db->getValue("credit_setting","reference");

            $coinRate = Setting::setDecimal($creditRate);
            $payableAmount = Setting::setDecimal($amount * $coinRate);

            if($balance < $payableAmount) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00266"][$language] /* Insufficient balance */, 'data' => "");

            $creditRes = $db->get("credit" , NULL , "id,name,type,translation_code");
            foreach($creditRes AS $key => $value){
                $creditDataByID[$value['id']] = $value;
                $creditDataByName[$value['name']] = $value;
                $creditDataByType[$value['type']] = $value;
            }

            $data["balance"]        = $balance;
            $data["fromCredit"]     = $translations[$creditDataByType[$fromCredit]['translation_code']][$language];
            $data["toCredit"]       = $translations[$creditDataByType[$toCredit]['translation_code']][$language];
            $data["amount"]         = $amount;
            $data["coinRate"]       = $coinRate;
            $data["payableAmount"]  = $payableAmount;
            $data["purchaseAmount"] = $amount;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function purchaseCreditConfirmation($params,$userID,$site){
            $db                  = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;
            $fromCredit          = $params['fromCredit'];
            $toCredit            = $params['toCredit'];
            $amount              = $params['amount'];
            $transactionPassword = $params['transactionPassword'];
            // $walletAddress    = $params['walletAddress'];
            $clientID            = $db->userID;
            $site                = $db->userType;

            $verifyResult = Self::purchaseCreditVerification($params,$userID,$site);
            if($verifyResult['status']!='ok'){
                return $verifyResult;
            }           

            $belongID = $db->getNewID();
            $coinRate = $verifyResult['data']['coinRate'];
            $calculatedAmount = $verifyResult['data']['payableAmount'];
            $purchaseAmount = $verifyResult['data']['purchaseAmount'];

            // Get the default account id from the client table
            $db->where("username", "creditSales");
            $receiverId = $db->getValue("client", "id");

            Cash::insertTAccount($clientID, $receiverId, $fromCredit, $calculatedAmount, "Purchase Credit", $belongID, "", $db->now(), "", $clientID,"","",$coinRate);
            Cash::insertTAccount($receiverId, $clientID, $toCredit, $purchaseAmount, "Purchase Credit", $belongID, "", $db->now(), "", $clientID, "","",$coinRate, "", $coinRate);
            
            // $performanceParams["eventSection"]    = "Purchase Credit";
            // $performanceParams["amount"]          = $amount;
            // $performanceParams["purchaseAmount"]  = $purchaseAmount;
            // $performanceParams["fromCredit"]      = $fromCredit;
            // $performanceParams["toCredit"]        = $toCredit;

            // Message::recordPerformance($performanceParams);

            // $data['fromWallet : '.$fromCredit] = Cash::getBalance($clientID, $fromCredit);
            // $data['toWallet' : '.$toCredit] = Cash::getBalance($clientID, $toCredit);

            // return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00277"][$language]/* Purchase successfully */, 'data' => $data);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00277"][$language]/* Purchase successfully */, 'data' => '');
        }
	}

?>
