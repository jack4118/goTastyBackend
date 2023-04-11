<?php

    class Subscribe {
    	function __construct($client, $mall) {
            // Self::validation = $client->validation;
            // Self::client = $client;
            // Self::cash = $client->validation->bonus->cash;
            // Self::tree = $client->validation->bonus->tree;
            // Self::invoice = $client->validation->invoice;
            // Self::bonus = $client->validation->bonus;
            // Self::mall = $mall;
        }

        public function reentryVerification($params, $upgradeType){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $checkKYCFlag = Setting::$systemSetting['checkKYCFlag'];
            // $client = Self::client;
            // $cash = Self::cash;

            $clientID = $db->userID;
            $site = $db->userType;

            $type = $params["type"];
            $step = $params["step"];
            $packageID = $params["packageID"];
            $creditUnit = $params["creditUnit"];
            $tPassword = $params["tPassword"];
            $spendCredit = $params["spendCredit"];
            $productID = $params["productID"];
            $pinCode = $params["pinCode"];

            $isSet = 1;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            if(!$clientID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Member.', 'data' => "");
            }

            $db->where("id", $clientID);
            $clientRow = $db->getOne("client", "id, main_id, username, sponsor_id");
            if (empty($clientRow)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
            }

            if($checkKYCFlag == 1 && $site == "Member"){
            	$db->where("client_id",$clientID);
	            $db->where("status","Approved");
	            $db->orderBy("created_at","DESC");
	            $kycRes = $db->getValue("mlm_kyc","status");
	            if(!$kycRes) return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00778"][$language] /* Your Kyc Is not ready. */, 'data' => '');
            }

            $clientData['clientID'] = $clientRow['id'];
            $clientData['username'] = $clientRow['username'];
            $sponsorID = $clientRow["sponsor_id"];
            $sponsorUsername = $clientRow['username'];
            
            if(!$type){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00427"][$language], 'data' => "");
            }

            if(!$step){
                $step = 2;
            }
            
            switch ($type) {
                case 'credit':
                    $registerType = "Credit Reentry";

                    if (strpos($creditUnit, ".") !== false) {
                        $errorFieldArr[] = array(
                            'id' => 'creditUnitError',
                            'msg' => $translations["E00861"][$language],
                        );
                    }

                    if ($creditUnit <= 0 || !preg_match("/^[1-9][0-9]*$/",$creditUnit)) {
                        $errorFieldArr[] = array(
                            'id' => 'creditUnitError',
                            'msg' => $translations["E00262"][$language],
                        );
                    }
                    
                    $db->where("category",$type);
                    $productID = $db->getValue("mlm_product","id");

                    if($productID){
                        $db->where("product_id",$productID);
                        $db->where("type","Purchase Setting");
                        $productSettingRes = $db->get("mlm_product_setting", null, "name, value");
                        foreach($productSettingRes as $productSettingRow){
                            $productSettingAry[$productSettingRow["name"]] = $productSettingRow["value"];
                        }

                    }else{
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00427"][$language], 'data' => "");
                    }

                    $firstMinPrice = $productSettingAry["1stMinPrice"];
                    $reentryMultiplier = $productSettingAry['reentryMultiplier'] ? $productSettingAry['reentryMultiplier'] : 1;

                    if($firstMinPrice > 0){
                        $db->where("client_id",$clientID);
                        $portfolioCount = $db->getValue("mlm_client_portfolio","count(id)");

                        if($portfolioCount <= 0){
                            // check for 1st time reentry
                            if($creditUnit < $firstMinPrice){
                                $errorMessage = str_replace("%%min1stTimePurchase%%", $firstMinPrice, $translations["E00813"][$language]);
                                $errorFieldArr[] = array(
                                                            'id' => 'creditUnitError',
                                                            'msg' => $errorMessage,
                                                        );
                            }
                        }

                    }

                    if (fmod($creditUnit,$reentryMultiplier) != 0){
                        $errorMessage = str_replace("%%number%%", $reentryMultiplier, $translations["E00823"][$language]);
                        $errorFieldArr[] = array(
                                                    'id' => 'creditUnitError',
                                                    'msg' => $errorMessage,
                                                );
                    }

                    if ($errorFieldArr) {
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    if($step == 2){
                        $isSet = 0;
                    }

                    $bonusValue = $creditUnit;

                    $paymentSetting = Cash::getPaymentDetail($clientID, $registerType, $creditUnit, $productID, "", $isSet);
                    $paymentMethod = $paymentSetting['data']["paymentData"];

                    if($step == 2){
                        //check credit payment
                        $validateCredit  = Cash::paymentVerification($clientID, $registerType, $spendCredit, $productID, $creditUnit);
                        if(strtolower($validateCredit["status"]) != "ok"){
                            return $validateCredit;
                        }

                        $invoiceSpendData = $validateCredit["data"]["invoiceSpendData"];

                        if($site == "Member"){
                             $tPasswordReturn = Client::verifyTransactionPassword($clientID, $tPassword);
                            if($tPasswordReturn["status"] != "ok"){
                                return $tPasswordReturn;
                            }
                        }
                    }
                    $dataOut["creditUnit"] = $creditUnit;
                    $dataOut["paymentCredit"] = $paymentMethod;
                    $dataOut["invoiceSpendData"] = $invoiceSpendData;

                    break;

                case 'package': 
                    $registerType = "Package Reentry";

                    if(!$productID){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }

                    $productData = Product::getProductData($productID);    
                    if(empty($productData)){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }

                    // if($productData["setting"]["Daily Bonus Cap Setting"]){
                    //     foreach ($productData["setting"]["Daily Bonus Cap Setting"] as $purchaseRow) {
                    //         if($purchaseRow["value"] <= 0) continue;
                    //         if($purchaseRow["name"] == "pairingDailyCap"){
                    //             $productRow["pairingDailyCap"] = $purchaseRow["value"];
                    //         }
                    //     }
                    // }

                    $highestProductID = 0;
                    // if($upgradeType == "upgrade"){
                    	$db->where("status","Active");
		            	// $db->where("id",$params['portfolioID']);
		            // }else{
		            	$db->where("client_id", $clientID);
		            	$db->orderBy("id","DESC");
		            // }

                    $res = $db->getOne("mlm_client_portfolio", "id, product_id,product_price");
                    $lastPortfolioID = $res["id"];
		            $highestProductID = $res["product_id"];
		            $priceDeduct = $res["product_price"];

		            if($upgradeType == "upgrade" && !$highestProductID) return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid portfolio.', 'data' => "");
                    else if($upgradeType != "upgrade"){
                        if($lastPortfolioID){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00880"][$language], 'data' => "");
                        }
                    }
                    
                    $highestPriority = 0;
                    if($highestProductID){
                        $db->where("id", $highestProductID);
                        $highestPriority = $db->getValue("mlm_product", "priority");
                    }

                    if($productData["priority"] <= $highestPriority && $upgradeType == "upgrade"){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }
                    if($upgradeType == "upgrade"){
                    	$productData["price"] -= $priceDeduct;
                    }
                    $productRow["price"] = Setting::setDecimal($productData["price"],"");
                    $productRow["languageCode"] = $productData["translation_code"];
                    $productRow["code"] = $productData["code"];
                    $productRow["bonusValue"] = Setting::setDecimal($productData["bonusValue"],"");
                    $bonusValue = $productRow["bonusValue"];
                    $price = $productData["price"];
                    /*if($productData["setting"]["Purchase Setting"]){
                        foreach ($productData["setting"]["Purchase Setting"] as $purchaseRow) {
                            if($purchaseRow["value"] <= 0) continue;

                            $payAmount = 0;

                            if($purchaseRow["reference"]){
                                $db->where("client_id",$clientID);
                                $payAmount = $db->getValue($purchaseRow["reference"],"SUM(payable_amount)");

                                if($payAmount <= $purchaseRow["value"]){
                                    return array('status' => "error", 'code' => 2, 'statusMsg' => "You are not valid to buy this product.", 'data'=> "");
                                }
                            }
                        }
                    }*/

                    $paymentSetting = Cash::getPaymentDetail($clientID, $registerType, $price, $productID, "");
                    $paymentMethod = $paymentSetting['data']["paymentData"];

                    // for skip payment page
                    if($type != "free" && count($paymentMethod) == 1){
                        foreach ($paymentMethod as $creditType => $rowValue) {
                            $spendCredit[$creditType]["amount"] = $price;
                        }

                        $dataOut["spendCredit"] = $spendCredit;
                    }

                    if($step == 2){
                        //check credit payment
                        $validateCredit  = Cash::paymentVerification($clientID, $registerType, $spendCredit, $productID, $price);
                        if(strtolower($validateCredit["status"]) != "ok"){
                            return $validateCredit;
                        }

                        $invoiceSpendData = $validateCredit["data"]["invoiceSpendData"];

                        if($site == "Member"){
                             $tPasswordReturn = Client::verifyTransactionPassword($clientID, $tPassword);
                            if($tPasswordReturn["status"] != "ok"){
                                return $tPasswordReturn;
                            }
                        }
                    }
                    $dataOut["productData"] = $productRow;
                    $dataOut["paymentCredit"] = $paymentMethod;
                    $dataOut["invoiceSpendData"] = $invoiceSpendData;
                    $dataOut["totalPrice"] = $price;
                    break;

                case 'pin': 

                    $registerType = "Pin Reentry";

                    if(!$pinCode){
                        $errorFieldArr[] = array(
                                                    'id' => 'pinCodeError',
                                                    'msg' => $translations["E00842"][$language],
                                                );
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    $db->where("code",$pinCode);
                    $db->where("status", "New");
                    $pinRow = $db->getOne("mlm_pin", "id, product_id, buyer_id, bonus_value, price, belong_id, batch_id, pin_type, owner_id");

                    if(empty($pinRow)){
                        $errorFieldArr[] = array(
                                                    'id' => 'pinCodeError',
                                                    'msg' => $translations["E00401"][$language],
                                                );
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    $productID = $pinRow["product_id"];
                    $buyerID = $pinRow["buyer_id"];
                    $pinType = $pinRow["pin_type"];
                    $pinID = $pinRow["id"];
                    $belongID = $pinRow["belong_id"];

                    $registerType = $pinType == "Normal" ? $registerType : $pinType." ".$registerType;

                    //check is downline or not
                    $db->where("client_id", $clientID);
                    $db->where("trace_key", "%".$buyerID."%","LIKE");
                    $isDownlines = $db->getValue("tree_sponsor", "count(id)");
                    if(!$isDownlines){
                        $errorFieldArr[] = array(
                                                    'id' => 'pinCodeError',
                                                    'msg' => $translations["E00401"][$language],
                                                );
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    $productData = Product::getProductData($productID);
                    if(empty($productData)){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }

                    if($productData["setting"]["Daily Bonus Cap Setting"]){
                        foreach ($productData["setting"]["Daily Bonus Cap Setting"] as $purchaseRow) {
                            if($purchaseRow["value"] <= 0) continue;
                            if($purchaseRow["name"] == "pairingDailyCap"){
                                $productRow["pairingDailyCap"] = $purchaseRow["value"];
                            }
                        }
                    }

                    $highestProductID = 0;

                    $db->where("client_id", $clientID);
                    $db->orderBy("id","DESC");
                    $highestProductID = $db->getValue("mlm_client_portfolio", "product_id");
                    
                    $highestPriority = 0;
                    if($highestProductID){
                        $db->where("id", $highestProductID);
                        $highestPriority = $db->getValue("mlm_product", "priority");
                    }

                    if($productData["priority"] <= $highestPriority){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }

                    $productRow["price"] = Setting::setDecimal($pinRow["price"],"");
                    $productRow["languageCode"] = $productData["translation_code"];
                    $productRow["code"] = $productData["code"];
                    $productRow["bonusValue"] = Setting::setDecimal($pinRow["bonus_value"],"");
                    $tierValue = $pinType == "Normal" ? 0 : Setting::setDecimal($productData["bonusValue"],"");
                    $bonusValue = $productRow["bonusValue"];
                    $price = $productRow["price"];
                    /*if($productData["setting"]["Purchase Setting"]){
                        foreach ($productData["setting"]["Purchase Setting"] as $purchaseRow) {
                            if($purchaseRow["value"] <= 0) continue;

                            $payAmount = 0;

                            if($purchaseRow["reference"]){
                                $db->where("client_id",$clientID);
                                $payAmount = $db->getValue($purchaseRow["reference"],"SUM(payable_amount)");

                                if($payAmount <= $purchaseRow["value"]){
                                    return array('status' => "error", 'code' => 2, 'statusMsg' => "You are not valid to buy this product.", 'data'=> "");
                                }
                            }
                        }
                    }*/

                    if($step == 2){
                        //check credit payment
                        if($site == "Member"){
                             $tPasswordReturn = Client::verifyTransactionPassword($clientID, $tPassword);
                            if($tPasswordReturn["status"] != "ok"){
                                return $tPasswordReturn;
                            }
                        }
                    }

                    $dataOut["productData"] = $productRow;
                    $dataOut["totalPrice"] = $price;
                    $dataOut["pinBelong"] = $belongID;
                    $dataOut["pinID"] = $pinID;

                    break;

                default: 
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00427"][$language], 'data' => "");
                    break;
               
            }
            $dataOut["lastPortfolioID"] = $lastPortfolioID;
            $dataOut["sponsorName"] = $sponsorUsername;
            $dataOut["sponsorID"] = $sponsorID;
            $dataOut["tierValue"] = $tierValue;
            $dataOut["bonusValue"] = $bonusValue;
            $dataOut["productID"] = $productID;
            $dataOut["client"] = $clientData;
            $dataOut["registerType"] = $registerType;
            $dataOut["priceDeduct"] = $priceDeduct;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $dataOut);
        }

        public function reentryConfirmation($params, $upgradeType){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;
            $dateTime = date("Y-m-d H:i:s");
            if($upgradeType == "bonusCreate"){
                $dateTime = $params["dateTime"];
            }

            $type = $params["type"];
            $packageID = $params["packageID"];
            $creditUnit = $params["creditUnit"];
            $tPassword = $params["tPassword"];
            $spendCredit = $params["spendCredit"];
            $upgradeClientID = $params["upgradeClientID"];
            $params["step"] = 2;

            if($site != "Member"){
                $clientID = $params["clientID"];
            }

            $verificationReturn = self::reentryVerification($params, $upgradeType);
            if($verificationReturn["status"] != "ok"){
                return $verificationReturn;
            }

            $productID = $verificationReturn["data"]["productID"];
            $bonusValue = $verificationReturn["data"]["bonusValue"];
            $tierValue = $verificationReturn["data"]["tierValue"];
            $registerType = $verificationReturn["data"]["registerType"];
            $creditUnit = $verificationReturn["data"]["creditUnit"];
            $price = $verificationReturn["data"]["totalPrice"];
            $productData = $verificationReturn["data"]["productData"];
            $clientData = $verificationReturn["data"]["clientData"];
            $paymentCredit = $verificationReturn["data"]["paymentCredit"];
            $invoiceSpendData = $verificationReturn["data"]["invoiceSpendData"];
            $pinBelong = $verificationReturn["data"]["pinBelong"];
            $pinID = $verificationReturn["data"]["pinID"];
            $sponsorID = $verificationReturn["data"]["sponsorID"];
            $priceDeduct = $verificationReturn["data"]["priceDeduct"];
            $lastPortfolioID = $verificationReturn["data"]["lastPortfolioID"];

            if($upgradeType == "upgrade") $price += $priceDeduct;

            $payerID = $clientID;

            $unitPrice = General::getLatestUnitPrice();

            $batchID = $db->getNewID();
            $portfolioID = $db->getNewID();
            switch ($type) {
                case 'credit':
                    //deduct payment
                    $paymentResult = Cash::paymentConfirmation($payerID, $registerType, $invoiceSpendData, $productID, $portfolioID, $creditUnit, $dateTime, $batchID);

                    // insert invoice
                    $invoiceData['productId']          = $productID;
                    $invoiceData['bonusValue']         = $bonusValue;
                    $invoiceData['productPrice']       = $creditUnit;
                    $invoiceData['unitPrice']          = $unitPrice;
                    $invoiceData['belongId']           = $batchID;
                    $invoiceData['portfolioId']        = $portfolioID;

                    $invoiceDataArr[] = $invoiceData;
                    $invoiceResult = Invoice::insertFullInvoice($payerID, $creditUnit, $invoiceDataArr, $invoiceSpendData, 'mlm', $batchID);

                    // Failed to insert invoice
                    if (!$invoiceResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

                    break;

                case 'package':
                    //deduct payment
                    $paymentResult = Cash::paymentConfirmation($payerID, $registerType, $invoiceSpendData, $productID, $portfolioID, $price, $dateTime, $batchID);

                    // insert invoice
                    $invoiceData['productId']          = $productID;
                    $invoiceData['bonusValue']         = $bonusValue;
                    $invoiceData['productPrice']       = $price;
                    $invoiceData['unitPrice']          = $unitPrice;
                    $invoiceData['belongId']           = $batchID;
                    $invoiceData['portfolioId']        = $portfolioID;

                    $invoiceDataArr[] = $invoiceData;
                    $invoiceResult = Invoice::insertFullInvoice($payerID, $price, $invoiceDataArr, $invoiceSpendData, 'mlm', $batchID);

                    // Failed to insert invoice
                    if (!$invoiceResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

                    break;

                case 'pin':
                    //update pin 

                    $belongID = $pinBelong;
                    $reference = $pinID;

                    $updatePinData = array(
                                                "client_id" => $clientID,
                                                "status" => "Used",
                                                "used_at" => $dateTime,
                                            );

                    $db->where("id", $pinID);
                    $db->update("mlm_pin", $updatePinData);
                    break;

                default: 
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00427"][$language], 'data' => "");
                    break;

            }

            if($upgradeType == "upgrade"){
            	$upgradeParams["lastPortfolioID"] = $lastPortfolioID;
            	// getLastportfolio belong
            	$db->where("id",$lastPortfolioID);
            	$res = $db->getOne("mlm_client_portfolio","belong_id,bonus_value");
            	$belongID = $res["belong_id"];
            	$lastBv = $res["bonus_value"];
            }

            $belongID = $belongID ? $belongID : $batchID;
            //insert client portfolio
            $db->where("name","maturityDays");
            $db->where("product_id",$productID);
            $maturityDays = $db->getValue("mlm_product_setting","value");
            $expiredDate = $maturityDays ? date("Y-m-d H:i:s", strtotime($maturityDays)) : "";
            $insertData = array(    
                                    "portfolioID"  => $portfolioID,
                                    "clientID"     => $clientID,
                                    "productID"    => $productID,
                                    "price"        => $price,
                                    "bonusValue"   => $bonusValue,
                                    "tierValue"    => $tierValue,
                                    "type"         => $registerType,
                                    "belongID"     => $belongID,
                                    "referenceID"  => $reference,
                                    "batchID"      => $batchID,
                                    "status"       => "Active",
                                    "purchaseAt"   => $dateTime,
                                    "expire_at"    => $expiredDate, 
                                    "pairingCap"   => $productData["pairingDailyCap"],
            );
            $portfolioId = self::insertClientPortfolio($insertData,$upgradeParams);

            // remove previous bv
            if($upgradeType == "upgrade") $bonusValue -= $lastBv;
            //insert bonus value
            $bonusInData['clientID']    = $clientID;
            $bonusInData['mainID']      = $payerID;
            $bonusInData['type']        = $registerType;
            $bonusInData['productID']   = $productID;
            $bonusInData['belongID']    = $belongID;
            $bonusInData['batchID']     = $batchID;
            $bonusInData['bonusValue']  = $bonusValue;
            $bonusInData['dateTime']    = $dateTime;
            $bonusInData['processed']   = 0;
            $insertBonusResult = Bonus::insertBonusValue($bonusInData);

            //insert Credit Sources
            $db->where("name", "isMaxCapWallet");
            $db->where("value", "1");
            $maxCapIDAry = $db->getValue("credit_setting", "credit_id", null);
            if($maxCapIDAry){
                $db->where("id", $maxCapIDAry, "IN");
                $maxCapCreditAry = $db->map("name")->get("credit", null, "name");
            }
            $db->where('username', "creditSales");
            $db->where('type', "Internal");
            $internalID = $db->getValue('client', 'id');
            $db->where("product_id", $productID);
            $db->where("type","Credit Sources");
            $creditSourcesRes = $db->get("mlm_product_setting", null, "name, value");
            foreach($creditSourcesRes as $creditSourcesRow){
                $creditAmount = Setting::setDecimal($creditSourcesRow["value"], $creditSourcesRow["name"]);
                if($creditAmount > 0){
                    Cash::insertTAccount($internalID, $payerID, $creditSourcesRow["name"], $creditAmount, $registerType, $db->getNewID(), "", $dateTime, $batchID,  $payerID, "", $portfolioID);
                }
                if($maxCapCreditAry[$creditSourcesRow["name"]]){
                    $maxCapAmount += $creditAmount;
                }
            }

            if($maxCapAmount > 0){
                $db->where("id", $portfolioID);
                $db->update("mlm_client_portfolio", array("max_cap" => $maxCapAmount));
            }
            //calculate rank and insert maxCap
            // $clientRankData = self::upgradeClientRank($clientID, $bonusValue, $dateTime, $portfolioID, $batchID, $registerType);
            self::updateMemberSalesData($clientID, "reentry", $bonusValue);
            Custom::upgradeSponsorRank($clientID,$dateTime);
            Custom::upgradeSponsorRank($sponsorID,$dateTime);

            //get Username
            $db->where('id',$clientID);
            $clientUsername = $db->getValue('client','username');

            //insert activity
            $activityData = array('user' => $clientUsername,'portfolioID' => $portfolioID, "bonusValue" => $bonusValue);
            $activityRes = Activity::insertActivity('Reentry', 'T00012', 'L00012', $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M02074"][$language] /* Purchased Successfully. */, 'data' => $dataOut);

        }

        function insertClientPortfolio($params,$upgradeParams) {
            $db = MysqliDb::getInstance();
            $tableName  = "mlm_client_portfolio";
            $referenceNo = Self::generateReferenceNo();

            $db->where('username', "payout");
            $db->where('type', "Internal");
            $internalID = $db->getValue('client', 'id');

            if($upgradeParams){
            	
            	// terminate last portfolio
            	$db->where("id",$upgradeParams["lastPortfolioID"]);
            	$db->update($tableName,array("status"=>"Terminated","expire_at"=>date("Y-m-d H:i:s")));
            }

            $insertData = array(
                "id"                    => $params["portfolioID"],
                "client_id"             => $params['clientID'],
                "product_id"            => $params['productID'],
                "product_price"         => $params['price'],
                "reference_no"          => $referenceNo,
                "bonus_value"           => $params['bonusValue'],
                "tier_value"            => $params['tierValue'],
                "portfolio_type"        => $params['type'],
                "belong_id"             => $params['belongID'],
                "reference_id"          => $params['referenceID'],
                "batch_id"              => $params['batchID'],
                "status"                => $params['status'],
                "expire_at"             => $params['expire_at'],
                "unit_price"            => $params['unitPrice'],
                "creator_id"            => Cash::$creatorID,
                "creator_type"          => Cash::$creatorType,
                "created_at"            => $params['purchaseAt'] ? $params['purchaseAt'] : $db->now(),
                "pairing_cap"           => $params["pairingCap"],
            );

            $portfolioID = $db->insert($tableName, $insertData);
            if (!$portfolioID)
                return false;

            return $portfolioID;
        }

        function generateReferenceNo() {
            $db = MysqliDb::getInstance();
            $tableName  = 'mlm_client_portfolio';
            
            // Get the length setting
            $referenceNoLength = Setting::$systemSetting['referenceNumberLength']?:8;

            $min = "1"; $max = "9";
            for($i=1;$i<$referenceNoLength;$i++) $max .= "9";

            while (1) {
                $referenceNo = sprintf("%0".$referenceNoLength."s", mt_rand((int)$min, (int)$max));
                
                $db->where('reference_no', $referenceNo);
                $count = $db->getValue($tableName, 'count(*)');
                if ($count == 0) break;
                // If exists, continue to generate again
            }

            return $referenceNo;
        }

        public function generateMemberID(){
            $db = MysqliDb::getInstance();
            $db->where('name','memberIDLength');
            $memberIDLength= $db->getOne('system_settings','value');
            $min = 1; $max = 9; 
            $memberIDLength['value'] -= 1;
            for($i=1;$i<(int)$memberIDLength['value'];$i++) $max .= "9";
            while(1){ 
            	$firstDigit = mt_rand(1, 9);
                $memberID = $firstDigit.sprintf("%0".$memberIDLength['value']."s", mt_rand((int)$min, (int)$max));
                $db->where('member_id',$memberID);
                $check = $db->getOne('client','COUNT(id)');
                if($check['COUNT(id)'] == 0) break;
            }
            return $memberID;
        }

        function insertClientSettingByProductSetting($params) {
            
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            
            $productID          = $params['productID'];
            $productBelongID    = $params['productBelongID'];
            $productBatchID     = $params['productBatchID'];
            $remark             = $params['remark'];
            $subject            = $params['subject'];
            $clientID           = $params['clientID'];
            
            // get internal accounts
            $db->where("username", "creditSales");
            $accountID = $db->getValue("client", "id");
            
            // select bonus from mlm_bonus table
            $db->where("allow_rank_maintain", "1");
            $db->where("disabled", "0");
            $bonuses = $db->get("mlm_bonus",null, "name");

            foreach ($bonuses as $bonus)
                $bonusList[] = $bonus['name'];
            
            // Overall rankID
            $bonusList[] = 'rankID';
            
            // select credit from credit table
            $credits = $db->get("credit", null, "name");

            foreach ($credits as $credit)
                $creditList[] = $credit['name'];

            $mergedArray = array_merge($bonusList,$creditList);

            // get product bonuses
            $db->where("product_id", $productID);
            $db->where("name", $mergedArray, "IN");
            $bonusRankList = $db->get("mlm_product_setting", null, "name, value, type");
            
            //check client setting table, update if exists else insert, cant use mysql on duplicate update because table doesn't have any unique column
            foreach($bonusRankList as $newRank){

                $db->where("name", $newRank['name']);
                $db->where("client_id", $clientID);
                $previousRank = $db->get("client_setting", null, "value");

                if (in_array($newRank['name'], $bonusList)){

                    if (empty($previousRank)) {

                        $insertData = array(

                            "name"      => $newRank['name'],
                            "value"     => $newRank['value'],
                            "type"      => $newRank['type'],
                            "client_id" => $clientID
                        );
                        // Insert bonus rank
                        $insertRankResult = $db->insert("client_setting", $insertData);

                        if (empty($insertRankResult))
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");
                        
                        $db->where('name', $newRank['name']);
                        $db->where('rank_id', $newRank['value']);
                        $rankSetting = $db->getOne('rank_setting', null, 'value, type');
                        if($rankSetting) {
                            $rankValue['type'] = $rankSetting['type'];
                            $rankValue['value'] = $rankSetting['value'];
                        }
                        
                        $insertData = array(

                            "name"      => $newRank['name'],
                            "value"     => $rankValue['value']?:'',
                            "type"      => $rankValue['type']?:'',
                            "client_id" => $clientID
                        );
                        // Insert bonus percentage
                        $insertRankValueResult = $db->insert("client_setting", $insertData);
                        
                        unset($rankValue);
                        
                        if (empty($insertRankValueResult))
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");

                    } else {

                        //check previous value whether it is greater than the new one if so remain same value
                        if ($previousRank['value'] < $newRank['value']) {
                            
                            $updateData = array(
                                "value" => $newRank['value']
                            );
                            // Update bonus rank
                            $db->where('type', $newRank['type']);
                            $db->where("name", $newRank['name']);
                            $db->where("client_id", $clientID);
                            $updateRankResult = $db->update("client_setting", $updateData);
                            if (!$updateRankResult)
                                return array('status' => "error", 'code' => 1, 'statusMsg' =>$translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");

                            $db->where('name', $newRank['name']);
                            $db->where('rank_id', $newRank['value']);
                            $rankSetting = $db->getOne('rank_setting', null, 'value, type');
                            if($rankSetting) {
                                $rankValue['type'] = $rankSetting['type'];
                                $rankValue['value'] = $rankSetting['value'];
                            }
                            
                            $updateData = array(
                                "value"     => $rankValue['value']?:''
                            );
                            // Update bonus percentage
                            $db->where('type', $rankValue['type']);
                            $db->where("name", $newRank['name']);
                            $db->where("client_id", $clientID);
                            $updateRankValueResult = $db->update("client_setting", $updateData);
                            
                            unset($rankValue);
                            if (empty($updateRankValueResult))
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00439"][$language] /* Failed to insert client rank. */, 'data' => "");

                        }
                    }
                }
                else if (in_array($newRank['name'], $creditList)){
                    $insertTAccountResult = Cash::insertTAccount($accountID, $clientID, $newRank['name'], $newRank['value'], $subject, $productBelongID, "", $db->now(), $productBatchID, $clientID, $remark);
                    if(!$insertTAccountResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00440"][$language] /* Failed to insert data */, 'data' => "");
                }
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function memberRegistration($params) {
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            $batchRegister      = trim($params['batchRegister']); 
            // personal information
            $fullName           = trim($params['fullName']); 
            // $username           = trim($params['username']); 
            $email              = trim($params['email']);
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']); 
            $dateOfBirth        = trim($params["dateOfBirth"]); 
            $gender             = trim($params["gender"]); 
            $password           = trim($params['password']);
            $checkPassword      = trim($params['checkPassword']);
            $sponsorName        = trim($params['sponsorName']);
            // billing address and delivery address
            $address            = trim($params['address']);
            $addressType        = trim($params['addressType']); // billing or delivery
            $district           = trim($params['district']);
            $subDistrict        = trim($params['subDistrict']);
            $postalCode         = trim($params['postalCode']);
            $city               = trim($params['city']);
            $state              = trim($params['state']);
            $country            = trim($params['country']);
            $remarks            = trim($params['remarks']);

            // bank info
            $bankOptional       = trim($params['bankOptional']); // 1 need check, 0 no need
            $bankID             = trim($params['bankID']);
            $branch             = trim($params['branch']);
            $bankCity           = trim($params['bankCity']);
            $accountHolder      = trim($params['accountHolder']);
            $accountNo          = trim($params['accountNo']);
            // $uploadData         = $params['uploadData']; // imageSize, imageType, imageName, imageFlag
            // additional info
            $martialStatus      = trim($params['martialStatus']); // single, married, widowed, divorced, separated
            $childNumber        = trim($params['childNumber']);
            $childAge           = $params['childAge'];
            $taxNumber          = trim($params['taxNumber']);
            $identityType       = trim($params['identityType']); // nric or passport
            $identityNumber     = trim($params['identityNumber']); // ktp number
            $passport           = trim($params['passport']); // passport
            // $ktpImage           = $params['ktpImage']; // imageSize, imageType, imageName, imageFlag

            $step               = trim($params['step']);
            $type               = trim($params['registerType']);
            $registerMethod     = trim($params['registerMethod']); // default username  

            //Placement Option
            // $placementPosition    = trim($params["placementPosition"]);  //moved to purchase starter kit verification    

            $site = $db->userType;
            $payerID = $db->userID;

            if ($site == "Admin") {
                $payerID = $params['clientID'];
            }

            $passwordEncryption  = Setting::getMemberPasswordEncryption();

			$maxFName = Setting::$systemSetting['maxFullnameLength'];
			$minFName = Setting::$systemSetting['minFullnameLength'];
			$maxUName = Setting::$systemSetting['maxUsernameLength'];
			$minUName = Setting::$systemSetting['minUsernameLength'];
			$maxPass  = Setting::$systemSetting['maxPasswordLength'];
			$minPass  = Setting::$systemSetting['minPasswordLength'];
			$maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
			$minTPass = Setting::$systemSetting['minTransactionPasswordLength'];
            $maxAccPP = Setting::$systemSetting['maxAccPerPhone'];
			$otpCodeVerify         = Setting::$systemSetting["otpCodeVerify"];
			$isSponsorCodeRegister = Setting::$systemSetting["isSponsorCodeRegister"];
            $martialStatusArr = array("single","married","widowed","divorced","separated");
            $genderArr = array("male", "female");

            if(!$step){
                $step = 1;
            }

            if (empty($type)) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01024"][$language], 'data' => '');
            }

            if(!$registerMethod) $registerMethod = "username";
                $registerMethodArray = array('phone','email','username');
                if (!in_array($registerMethod,$registerMethodArray)) {
                    return array('status'=>'error','code'=>'1','statusMsg'=>$translations["E01025"][$language],'data'=>array('field'=>'registerMethod'));
            }

            if($step >= 1){
                // Validate fullName
                if(empty($fullName)) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00296"][$language] /* Please insert full name */
                    );
                } else {
                    if (strlen($fullName) < $minFName || strlen($fullName) > $maxFName) {
                        $errorFieldArr[] = array(
                            'id'    => 'nameError',
                            'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                        );
                    }
                } 

                // Validate username
                // if (empty($username)) {
                //     $errorFieldArr[] = array(
                //         'id' => 'usernameError',
                //         'msg' => $translations["E00299"][$language] /* Please fill in username */
                //     );
                // } else {
                //     if (!(ctype_alnum($username) && !ctype_alpha($username) && !ctype_digit($username))) {
                //     // if (!preg_match("/^[a-zA-Z]+[a-zA-Z0-9]+$/",$username)) {
                //         $errorFieldArr[] = array(
                //             'id' => 'usernameError',
                //             'msg' => $translations["E00844"][$language], /* Username unavailable */
                //         );
                //     } else if (strlen($username) < $minUName || strlen($username) > $maxUName) {
                //         $errorFieldArr[] = array(
                //             'id' => 'usernameError',
                //             'msg' => str_replace(array("%%minUName%%", "%%maxUName%%"), array($minUName, $maxUName), $translations["E00806"][$language]),
                //             // 'msg' => $translations["E00806"][$language] /* Username cannot be less than */ ." ". $ ." ". $translations["E00301"][$language] /*  or more than  */ . $ . '.'
                //         );
                //     } else {
                //         $db->where("username", $username);
                //         $result = $db->getOne("client");
                //         if (!empty($result)) {
                //             $errorFieldArr[] = array(
                //                 'id' => 'usernameError',
                //                 'msg' => $translations["E00302"][$language] /* Username unavailable */
                //             );
                //         }
                //     }
                // }       

                // Valid email
                if (empty($email)) {
                    $errorFieldArr[] = array(
                        'id' => 'emailError',
                        'msg' => $translations["E00318"][$language] /* Please fill in email */
                    );
                } else {
                    if ($email) {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $errorFieldArr[] = array(
                                'id' => 'emailError',
                                'msg' => $translations["E00319"][$language] /* Invalid email format. */
                            );
                        }else{
                            $db->where('email',$email);
                            $isOccupied = $db->has('client');
                            if ($isOccupied) {
                                $errorFieldArr[] = array(
                                    'id'  => 'emailError',
                                    'msg' => $translations['E00748'][$language] /* Email Already Used */
                                );
                            }
                        }
                    }
                }

                // Validate phone
                if (empty($dialingArea) || empty($phone)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                    );
                } else {
                    if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                        $errorFieldArr[] = array(
                            'id' => 'phoneError',
                            'msg' => $translations["E00858"][$language] /* Only number is allowed */
                        );
                    }

                    // check max account per phone
                    $db->where("dial_code", $dialingArea);
                    $db->where("phone", $phone);
                    $totalAccThisPhone = $db->getValue("client", "COUNT(*)");
                    /*if (!empty($totalAccThisPhone)) {
                        if($totalAccThisPhone>=$maxAccPP){
                            $errorFieldArr[] = array(
                                'id' => 'phoneError',
                                'msg' => $translations["E00994"][$language] 
                            );
                        }
                    }*/
                }

                // Validate Date of Birth
                if (!is_numeric($dateOfBirth)){
                    $errorFieldArr[] = array(
                        'id' => 'dateOfBirthError',
                        'msg' => $translations["E00156"][$language] /* Invalid date. */
                    );
                }

                // check Date of Birth, min 18 years old
                $ts1 = date("Y-m-d", $dateOfBirth); 
                $tempDob = date("Y-m-d", strtotime('-18 year', strtotime("now")));
                $ts2 = $tempDob;
                if($ts1 > $ts2){
                    $errorFieldArr[] = array(
                        'id' => 'dateOfBirthError',
                        'msg' => $translations["E01053"][$language] /* You must be 18 and above to register. */
                    );
                }

                // Validate Gender
                if(empty($gender) || (!in_array($gender, $genderArr))){
                    $errorFieldArr[] = array(
                        'id' => 'genderError',
                        'msg' => $translations["E00766"][$language] /* Invalid gender */
                    );
                }

                 // Validate password
                if (empty($password)) {
                    $errorFieldArr[] = array(
                        'id' => 'passwordError',
                        'msg' => $translations["E00306"][$language] /* Please fill in password */
                    );
                } elseif (!preg_match("#[0-9]+#", $password)) {
                    $errorFieldArr[] = array(
                        'id' => 'passwordError',
                        'msg' => $translations["E00810"][$language] /* Login Password must set at least 6 and not more than 20 alphanumeric */
                    );

                } elseif (!preg_match("#[a-zA-z]+#", $password)) {
                    $errorFieldArr[] = array(
                        'id' => 'passwordError',
                        'msg' => $translations["E00810"][$language] /* Login Password must set at least 6 and not more than 20 alphanumeric */
                    );

                } else {
                    if (strlen($password) < $minPass || strlen($password) > $maxPass) {
                        $errorFieldArr[] = array(
                            'id' => 'passwordError',
                            'msg' => str_replace(array("%%minPass%%", "%%maxPass%%"), array($minPass, $maxPass), $translations["E00808"][$language]),
                        );
                    }
                }

                //checking re-type password
                if (empty($checkPassword)) {
                    $errorFieldArr[] = array(
                        'id' => 'checkPasswordError',
                        'msg' => $translations["E00306"][$language] /* Please fill in password */
                    );
                } else {
                    if ($checkPassword != $password) {
                        $errorFieldArr[] = array(
                            'id' => 'checkPasswordError',
                            'msg' => $translations["E00309"][$language] /* Password not match */
                        );
                    }
                }

                // Validate sponsorName
                if (empty($sponsorName)) {
                    $errorFieldArr[] = array(
                        'id' => 'sponsorUsernameError',
                        'msg' => $translations["E00320"][$language] /* Please fill in sponsor */
                    );
                } else {
                    $db->where("member_id", $sponsorName);
                    $db->where('`terminated`','0');
                    $sponsorID = $db->getValue("client", "id");
                    $sponsorDownlineAry = Tree::getSponsorTreeDownlines($payerID);

                    if($sponsorID){
                        $db->where('client_id',$sponsorID);
                        $placementChecking = $db->getOne("tree_placement");
                    }
                  
                    if (empty($sponsorID)) {
                        $errorFieldArr[] = array(
                            'id' => 'sponsorUsernameError',
                            'msg' => $translations["E00321"][$language] /* Invalid sponsor */
                        );
                    } else if(empty($placementChecking)) {
                        $errorFieldArr[] = array(
                            'id' => 'sponsorUsernameError',
                            'msg' => $translations["E00321"][$language] /* Invalid sponsor */
                        );
                    }else if (!in_array($sponsorID, $sponsorDownlineAry) && $payerID) {
                        $errorFieldArr[] = array(
                            'id' => 'sponsorUsernameError',
                            'msg' => $translations["E00820"][$language] /* Invalid sponsor */
                        );
                    }
                }


                //Placement checking moved to starter kit purchase verification
                /*$placementUsername = $sponsorName;

                if($placementUsername){
                    $db->where("member_id", $placementUsername);
                    $placementID = $db->getValue("client", "id");

                    if($site == "Admin") {
                        $payerID = $placementID;
                    }

                    if($placementUsername || $payerID) {
                        if (empty($placementPosition)) {
                            $errorFieldArr[] = array(
                                "id"  => "placementPositionError",
                                "msg" => $translations["E00325"][$language]
                            );
                        } else if (!in_array($placementPosition,[1,2])) {
                            $errorFieldArr[] = array(
                                "id"  => "placementPositionError",
                                "msg" => $translations["E00325"][$language]
                            );
                        }

                        // valid octopus username
                        $db->where("client_id", $placementID);
                        $db->where("trace_key", "%".$placementID."%", "LIKE");
                        $isUnderPlacementID = $db->getOne("tree_placement", "id");
                        if(!$isUnderPlacementID) {
                            $errorFieldArr[] = array(
                                "id"  => "placementUsernameError",
                                "msg" => $translations["E00579"][$language]
                            );
                        }

                        // if placement 2 leg full, loop until downline leg empty
                        $placementDownlineID = $placementID;

                        do {
                            if($placementValid) $placementDownlineID = $placementValid;
                            $db->where("upline_id",$placementDownlineID);
                            $db->where("client_position",$placementPosition);
                            $placementValid = $db->getOne("tree_placement","client_id")['client_id'];
                        } while (!empty($placementValid));

                        $db->where('id',$placementDownlineID);
                        $placementDownline = $db->getOne('client','id, username');
    
                    }
                }*/
            } 

            if ($step >= 2) {
                // Validate Address type
                if(empty($addressType)) {
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01026"][$language], 'data' => '');
                }

                // Validate address
                if(empty($address)) {
                    $errorFieldArr[] = array(
                        'id'  => "addressError",
                        'msg' => $translations['E00943'][$language]
                    );
                }

                // Validate country
                if(!is_numeric($country) || empty($country)) {
                    $errorFieldArr[] = array(
                        'id'  => "countryIDError",
                        'msg' => $translations['E00947'][$language]
                    );
                }else{
                    $db->where("id",$country);
                    $countryRes = $db->getOne("country","name,translation_code");
                    if(!$countryRes){
                        $errorFieldArr[] = array(
                            "id"  => "countryIDError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }

                // Validate state
                if(!is_numeric($state) || empty($state)) {
                    $errorFieldArr[] = array(
                        'id'  => "stateError",
                        'msg' => $translations['E00667'][$language]
                    );
                }else{
                    $db->where("id",$state);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $stateRes = $db->getOne("state","name,translation_code");
                    if(!$stateRes){
                        $errorFieldArr[] = array(
                            "id"  => "stateError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }

                // Validate city
                if(!is_numeric($city) || empty($city)) {
                    $errorFieldArr[] = array(
                        'id'  => "cityError",
                        'msg' => $translations['E01029'][$language]
                    );
                }else{
                    $db->where("id",$city);
                    $db->where("state_id",$state);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $cityRes = $db->getOne("city","name,translation_code");
                    if(!$cityRes){
                        $errorFieldArr[] = array(
                            "id"  => "cityError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }

                // Validate district
                if(!is_numeric($district) || empty($district)) {
                    $errorFieldArr[] = array(
                        'id'  => "districtErrror",
                        'msg' => $translations['E01113'][$language]
                    );
                }else{
                    $db->where("id",$district);
                    $db->where("city_id",$city);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $districtRes = $db->getOne("county","name,translation_code");
                    if(!$districtRes){
                        $errorFieldArr[] = array(
                            "id"  => "districtErrror",
                            "msg" => $translations["E01113"][$language]
                        );
                    }
                }

                // Validate sub district
                if(!is_numeric($subDistrict) || empty($subDistrict)) {
                    $errorFieldArr[] = array(
                        'id'  => "subDistrictError",
                        'msg' => $translations['E01028'][$language]
                    );
                }else{
                    $db->where("id",$subDistrict);
                    $db->where("county_id",$district);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $subDistrictRes = $db->getOne("sub_county","name,translation_code");
                    if(!$subDistrictRes){
                        $errorFieldArr[] = array(
                            "id"  => "subDistrictError",
                            "msg" => $translations["E01028"][$language]
                        );
                    }
                }

                // Validate postal code
                if(!is_numeric($postalCode) || empty($postalCode)) {
                     $errorFieldArr[] = array(
                        'id'  => "postalCodeError",
                        'msg' => $translations['E01030'][$language]
                    );
                }else{
                    $db->where("id",$postalCode);
                    $db->where("sub_county_id",$subDistrict);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $postalCodeRes = $db->getOne("zip_code","name,translation_code");
                    if(!$postalCodeRes){
                        $errorFieldArr[] = array(
                            "id"  => "postalCodeError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }
            }

            if ($step >= 3 && $bankOptional) { 

                if (empty($bankID)) {
                    $errorFieldArr[] = array(
                        'id'  => "bankTypeError",
                        'msg' => $translations["E01031"][$language] /* Please Select A Bank. */
                    );
                }

                if (empty($branch)) {
                    $errorFieldArr[] = array(
                        'id'  => "branchError",
                        'msg' => $translations["E01032"][$language] /* Please Insert Branch */
                    );
                }

                if (empty($bankCity)) {
                    $errorFieldArr[] = array(
                        'id'  => "bankCityError",
                        'msg' => $translations["E01033"][$language] /* Please Insert Bank City */
                    );
                }

                if (empty($accountHolder)) {
                    $errorFieldArr[] = array(
                        'id'  => "accountHolderError",
                        'msg' => $translations["E01034"][$language] /* Please Insert Account Holder's Name */
                    );

                }else{
                    if($accountHolder != $fullName){
                        $errorFieldArr[] = array(
                            "id" => "accountHolderError",
                            "msg" => $translations["E01106"][$language]
                        );
                    }
                }

                if (empty($accountNo)) {
                    $errorFieldArr[] = array(
                        'id'  => "accountNoError",
                        'msg' => $translations["E01035"][$language] /* Please Insert Account Number */
                    );
                }

                // $db->where("type", "Upload Setting");
                // $validMediaRes  = $db->map('name')->get("system_settings",null,"name, value ,reference");

                // $validImageType = explode("#", $validMediaRes['validImageType']['value']);
                // $maxImageSize   = $validMediaRes['validImageType']['reference'];

                // if(!empty($uploadData)) {
                //     if($uploadData['imageFlag'] == 1) {
                //         if(!$uploadData['imageName']){
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00556"][$language], 'data' => "");
                //         }
                //         if(!in_array($uploadData['imageType'], $validImageType)){
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language], 'data' => $data);
                //         }
                //         if(!$uploadData['imageSize']){
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Image)", 'data' => $data);
                //         }
                //         if($uploadData['imageSize']>$maxImageSize){
                //             $sizeMB  = $maxImageSize / 1024 / 1024;
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Image)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                //         } 
                //     }
                // }else{
                //     $errorFieldArr[] = array(
                //         'id'  => "uploadDataError",
                //         'msg' => $translations["E01036"][$language] /* Please Upload Bank Account Front Page*/
                //     );
                // }
            }

            if ($step >= 4) {

                // Validate Gender
                if(empty($martialStatus) || (!in_array($martialStatus, $martialStatusArr))){
                    $errorFieldArr[] = array(
                        'id' => 'martialStatusError',
                        'msg' => $translations["E01037"][$language] /* Please Select Marital Status */
                    );
                }

                if(!is_numeric($childNumber) || $childNumber < 0){
                    $errorFieldArr[] = array(
                        'id' => 'childNumberError',
                        'msg' => $translations["E01038"][$language] /* Please Insert Child Number */
                    );
                }

                if($childNumber > 0 && !$batchRegister){
                    $childAgeOption = explode('#', Setting::$systemSetting['childAgeOption']);
                    // childAge
                    if(!is_array($childAge)){
                        $errorFieldArr[] = array(
                            'id' => 'childAgeError',
                            'msg' => $translations["E01111"][$language] /* Invalid Age. */
                        );
                    }else if(count($childAge) != $childNumber){
                        $errorFieldArr[] = array(
                            'id' => 'childAgeError',
                            'msg' => $translations["E01112"][$language] /* Total count of age not match. */
                        );
                    }else{
                        foreach ($childAge as $childAgeRow) {
                            if(!$childAgeOption[$childAgeRow]){
                                $errorFieldArr[] = array(
                                    'id' => 'childAgeError',
                                    'msg' => $translations["E01111"][$language] /* Invalid Age. */
                                );
                                break;
                            }
                        }
                    }
                }

                // if(empty($taxNumber)){
                //     $errorFieldArr[] = array(
                //         'id' => 'taxNumberError',
                //         'msg' => $translations["E01039"][$language] /* Please Insert Tax Number */
                //     );
                // }

                if($identityType == "nric"){
                    if(empty($identityNumber)){
                        $errorFieldArr[] = array(
                            'id' => 'identityNumberError',
                            'msg' => $translations["E01040"][$language] /* Please Insert Identity Number */
                        );
                    }else if(!is_numeric($identityNumber)){
                        $errorFieldArr[] = array(
                            'id' => 'identityNumberError',
                            'msg' => $translations["E00858"][$language] /* Only number is allowed */
                        );
                    }
                } else if ($identityType == "passport"){
                    if(empty($passport)){
                        $errorFieldArr[] = array(
                            'id' => 'passportNumberError',
                            'msg' => $translations["E01042"][$language] /* Please Insert Passport Number */
                        );
                    }
                }else{
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00218"][$language], 'data' => "");
                }

                // $db->where("type", "Upload Setting");
                // $validMediaRes  = $db->map('name')->get("system_settings",null,"name, value ,reference");

                // $validImageType = explode("#", $validMediaRes['validImageType']['value']);
                // $maxImageSize   = $validMediaRes['validImageType']['reference'];

                // if(!empty($ktpImage)){
                //     if($ktpImage['imageFlag'] == 1) {
                //         if(!$ktpImage['imageName']){
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00556"][$language], 'data' => "");
                //         }
                //         if(!in_array($ktpImage['imageType'], $validImageType)){
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language], 'data' => $data);
                //         }
                //         if(!$ktpImage['imageSize']){
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Image)", 'data' => $data);
                //         }
                //         if($ktpImage['imageSize']>$maxImageSize){
                //             $sizeMB  = $maxImageSize / 1024 / 1024;
                //             return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Image)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                //         } 
                //     }                    
                // }else{
                //     $errorFieldArr[] = array(
                //         'id' => 'ktpImageError',
                //         'msg' => $translations["E01041"][$language] /* Please Upload KTP image */
                //     );
                // }
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            switch ($type) {
                case 'free':
                    $registerType = 'Free Register';

                    if($site != 'Admin' && $otpCodeVerify){
                        $verifyCode = Otp::verifyOTPCode($clientID,$otpType,"register",$otpCode,$dialingArea.$phone);
                    
                        if($verifyCode["status"] != "ok"){
                            $errorFieldArr[] = array(
                                                        'id'  => 'otpCodeError',
                                                        'msg' => $verifyCode['statusMsg']
                                                    );
                        }else{
                            $otpID = $verifyCode['data'];
                        }
                    }
                    break;

                case 'credit':
                    $registerType = "Credit Register";

                    if(!$payerID){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Member.', 'data'=>'');
                    }
                    if(!is_numeric($creditUnit) || $creditUnit <= 0){
                        $errorFieldArr[] = array(
                                                    'id' => 'creditUnitError',
                                                    'msg' => $translations["E00428"][$language],
                                                );
                    }

                    $db->where("category",$type);
                    $productID = $db->getValue("mlm_product","id");
                    if($productID){
                        $db->where("product_id",$productID);
                        $db->where("type","Purchase Setting");
                        $productSettingRes = $db->get("mlm_product_setting", null, "name, value");
                        foreach($productSettingRes as $productSettingRow){
                            $productSettingAry[$productSettingRow["name"]] = $productSettingRow["value"];
                        }
                    }else{
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00427"][$language], 'data' => "");
                    }
                    
                    $firstMinPrice = $productSettingAry["1stMinPrice"];
                    $reentryMultiplier = $productSettingAry['reentryMultiplier'] ? $productSettingAry['reentryMultiplier'] : 1;

                    //register must be 1000
                    if($creditUnit < $firstMinPrice){
                        $errorMessage = str_replace("%%min1stTimePurchase%%", $firstMinPrice, $translations["E00813"][$language]);
                        $errorFieldArr[] = array(
                                                    'id' => 'creditUnitError',
                                                    'msg' => $errorMessage,
                                                );
                    }

                    if (fmod($creditUnit,$reentryMultiplier) != 0){
                        $errorMessage = str_replace("%%number%%", $reentryMultiplier, $translations["E00823"][$language]);
                        $errorFieldArr[] = array(
                                                    'id' => 'creditUnitError',
                                                    'msg' => $errorMessage,
                                                );
                    }

                    if ($errorFieldArr) {
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }
                    $isSet = 1;
                    if($step == 2){
                        $isSet = 0;
                    }

                    $paymentSetting = Cash::getPaymentDetail($payerID, $registerType, $creditUnit, $productID, "", $isSet, "register");
                    $paymentMethod = $paymentSetting['data']["paymentData"];
                    if($step == 2){
                        //check credit payment
                        $validateCredit  = Cash::paymentVerification($payerID, $registerType, $spendCredit, $productID, $creditUnit,"", "register");
                        if(strtolower($validateCredit["status"]) != "ok"){
                            return $validateCredit;
                        }

                        $invoiceSpendData = $validateCredit["data"]["invoiceSpendData"];

                        if($site == "Member"){
                             $tPasswordReturn = client::verifyTransactionPassword($payerID, $payerTPassword);
                            if($tPasswordReturn["status"] != "ok"){
                                return $tPasswordReturn;
                            }
                        }
                    }
                    $dataOut["creditUnit"] = $creditUnit;
                    $dataOut["paymentCredit"] = $paymentMethod;
                    $dataOut["invoiceSpendData"] = $invoiceSpendData;

                    break;

                case 'package': 

                    $registerType = "Package Register";

                    if(!$productID){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }

                    $productData = Product::getProductData($productID);                    
                    if(empty($productData)){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }
                    
                    if(!$productData["isRegisterPackage"] && !$productData["isBundlePackage"]){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }

                    $productRow["price"] = Setting::setDecimal($productData["price"],"");
                    $productRow["languageCode"] = $productData["translation_code"];
                    $productRow["code"] = $productData["code"];
                    $productRow["bonusValue"] = Setting::setDecimal($productData["bonusValue"],"");
                    $bonusValue = $productRow["bonusValue"];
                    $price = $productData["price"];
                    if($productData["setting"]["Daily Bonus Cap Setting"]){
                        foreach ($productData["setting"]["Daily Bonus Cap Setting"] as $purchaseRow) {
                            if($purchaseRow["value"] <= 0) continue;
                            if($purchaseRow["name"] == "pairingDailyCap"){
                                $productRow["pairingDailyCap"] = $purchaseRow["value"];
                            }
                        }
                    }
                    /*if($productData["setting"]["Purchase Setting"]){
                        foreach ($productData["setting"]["Purchase Setting"] as $purchaseRow) {
                            if($purchaseRow["value"] <= 0) continue;

                            $payAmount = 0;

                            if($purchaseRow["reference"]){
                                $db->where("client_id",$clientID);
                                $payAmount = $db->getValue($purchaseRow["reference"],"SUM(payable_amount)");

                                if($payAmount <= $purchaseRow["value"]){
                                    return array('status' => "error", 'code' => 2, 'statusMsg' => "You are not valid to buy this product.", 'data'=> "");
                                }
                            }
                        }
                    }*/

                    $paymentSetting = Cash::getPaymentDetail($payerID, $registerType, $price, $productID, "");
                    $paymentMethod = $paymentSetting['data']["paymentData"];

                    // for skip payment page
                    if($type != "free" && count($paymentMethod) == 1){
                        foreach ($paymentMethod as $creditType => $rowValue) {
                            $spendCredit[$creditType]["amount"] = $price;
                        }

                        $dataOut["spendCredit"] = $spendCredit;
                    }

                    if($step == 2){
                        //check credit payment
                        $validateCredit  = Cash::paymentVerification($payerID, $registerType, $spendCredit, $productID, $price);
                        if(strtolower($validateCredit["status"]) != "ok"){
                            return $validateCredit;
                        }

                        $invoiceSpendData = $validateCredit["data"]["invoiceSpendData"];

                        if($site == "Member"){
                             $tPasswordReturn = Client::verifyTransactionPassword($payerID, $payerTPassword);
                            if($tPasswordReturn["status"] != "ok"){
                                return $tPasswordReturn;
                            }
                        }
                    }
                    $dataOut["productData"] = $productRow;
                    $dataOut["paymentCredit"] = $paymentMethod;
                    $dataOut["invoiceSpendData"] = $invoiceSpendData;
                    $dataOut["totalPrice"] = $price;
                    $dataOut["isBundlePackage"] = $productData["isBundlePackage"] ? $productData["isBundlePackage"] : 0;
                    break;

                case 'pin': 

                    $registerType = "Pin Register";

                    if(!$pinCode){
                        $errorFieldArr[] = array(
                                                    'id' => 'pinCodeError',
                                                    'msg' => $translations["E00842"][$language],
                                                );
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    $db->where("code",$pinCode);
                    $db->where("status", "New");
                    $pinRow = $db->getOne("mlm_pin", "id, product_id, buyer_id, bonus_value, price, belong_id, batch_id, pin_type, owner_id");

                    if(empty($pinRow)){
                        $errorFieldArr[] = array(
                                                    'id' => 'pinCodeError',
                                                    'msg' => $translations["E00401"][$language],
                                                );
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    $productID = $pinRow["product_id"];
                    $buyerID = $pinRow["buyer_id"];
                    $pinType = $pinRow["pin_type"];
                    $pinID = $pinRow["id"];
                    $belongID = $pinRow["belong_id"];
                    $packageType = $pinType;
                    $registerType = $pinType == "Normal" ? $registerType : $pinType." ".$registerType;

                    //check is downline or not
                    $db->where("client_id", $payerID);
                    $db->where("trace_key", "%".$buyerID."%","LIKE");
                    $isDownlines = $db->getValue("tree_sponsor", "count(id)");
                    if(!$isDownlines){
                        $errorFieldArr[] = array(
                                                    'id' => 'pinCodeError',
                                                    'msg' => $translations["E00401"][$language],
                                                );
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }

                    $productData = Product::getProductData($productID);                    
                    if(empty($productData)){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package.", 'data'=> "");
                    }
                    
                    if(!$productData["isRegisterPackage"] && !$productData["isBundlePackage"]){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00843"][$language], 'data'=> "");
                    }

                    $productRow["price"] = Setting::setDecimal($pinRow["price"],"");
                    $productRow["languageCode"] = $productData["translation_code"];
                    $productRow["code"] = $productData["code"];
                    $productRow["bonusValue"] = Setting::setDecimal($pinRow["bonus_value"],"");
                    $bonusValue = $productRow["bonusValue"];

                    if($productData["setting"]["Daily Bonus Cap Setting"]){
                        foreach ($productData["setting"]["Daily Bonus Cap Setting"] as $purchaseRow) {
                            if($purchaseRow["value"] <= 0) continue;
                            if($purchaseRow["name"] == "pairingDailyCap"){
                                $productRow["pairingDailyCap"] = $purchaseRow["value"];
                            }
                        }
                    }

                    if($step == 2){
                        if($site == "Member"){
                             $tPasswordReturn = client::verifyTransactionPassword($payerID, $payerTPassword);
                            if($tPasswordReturn["status"] != "ok"){
                                return $tPasswordReturn;
                            }
                        }
                    }

                    $price = $productData["price"];
                    $tierValue = $pinType == "Normal" ? 0 : Setting::setDecimal($productData["bonusValue"],"");
                    $dataOut["productData"] = $productRow;
                    $dataOut["totalPrice"] = $price;
                    $dataOut["isBundlePackage"] = $productData["isBundlePackage"] ? $productData["isBundlePackage"] : 0;
                    $dataOut["pinBelong"] = $belongID;
                    $dataOut["pinID"] = $pinID;

                    break;

                default: 
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Register Type.", 'data' => '');
                    break;
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            $dateOfBirth = date("d/m/Y", $dateOfBirth);

            $dataOut['fullName'] = $fullName;
            $dataOut['sponsorID'] = $sponsorID;
            // $dataOut['placementID'] = $placementDownlineID; //moved to purchase starter kit verification
            // $dataOut['placementPosition'] = $placementPosition;
            // $dataOut['placementDownline'] = $placementDownline;
            //$dataOut['username']  = $username;
            $dataOut["email"] = $email;
            $dataOut["dialingArea"] = $dialingArea;
            $dataOut["phone"] = $phone;
            $dataOut["dateOfBirth"] = $dateOfBirth;
            $dataOut["gender"] = General::getTranslationByName($gender);
            $dataOut["sponsorName"] = $sponsorName;
            $dataOut["address"] = $address;
            $dataOut["addressType"] = $addressType;
            $dataOut["district"] = $districtRes["translation_code"] ? $translations[$districtRes["translation_code"]][$language] : $districtRes["name"];
            $dataOut["subDistrict"] = $subDistrictRes["translation_code"] ? $translations[$subDistrictRes["translation_code"]][$language] : $subDistrictRes["name"];
            $dataOut["postalCode"] = $postalCodeRes["translation_code"] ? $translations[$postalCodeRes["translation_code"]][$language] : $postalCodeRes["name"];
            $dataOut["city"] = $cityRes["translation_code"] ? $translations[$cityRes["translation_code"]][$language] : $cityRes["name"];
            $dataOut["state"] = $stateRes["translation_code"] ? $translations[$stateRes["translation_code"]][$language] : $stateRes["name"];
            $dataOut["country"] = $countryRes["translation_code"] ? $translations[$countryRes["translation_code"]][$language] : $countryRes["name"];
            $dataOut["remarks"] = $remarks;
            $dataOut["bankID"] = $bankID;
            $dataOut["branch"] = $branch;
            $dataOut["bankCity"] = $bankCity;
            $dataOut["accountHolder"] = (!empty($accountHolder))?$accountHolder:'-';
            $dataOut["accountNo"] = (!empty($accountNo))?$accountNo:'-';
            // $dataOut["uploadData"] = $uploadData;
            $dataOut["martialStatus"] = General::getTranslationByName($martialStatus);
            $dataOut["childNumber"] = $childNumber;
            $dataOut["childAge"] = implode("#", $childAge);
            $dataOut["taxNumber"] = $taxNumber;
            $dataOut["identityType"] = General::getTranslationByName($identityType);
            $dataOut["identityNumber"] = $identityNumber;
            $dataOut["passport"] = $passport;
            // $dataOut["ktpImage"] = $ktpImage;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $dataOut);
        }

        public function memberRegistrationConfirmation($params) {
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            // personal information
            $fullName           = trim($params['fullName']); 
            // $username           = trim($params['username']); 
            $email              = trim($params['email']);
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']); 
            $dateOfBirth        = trim($params["dateOfBirth"]); 
            $gender             = trim($params["gender"]); 
            $password           = trim($params['password']);
            $checkPassword      = trim($params['checkPassword']);
            $sponsorName        = trim($params['sponsorName']); 

            //Placement Option
            // $placementPosition    = trim($params["placementPosition"]);  

            // billing address and delivery address
            $address            = trim($params['address']);
            $addressType        = trim($params['addressType']); // billing or delivery
            $district           = trim($params['district']);
            $subDistrict        = trim($params['subDistrict']);
            $postalCode         = trim($params['postalCode']);
            $city               = trim($params['city']);
            $state              = trim($params['state']);
            $country            = trim($params['country']);
            $remarks            = trim($params['remarks']);

            // bank info
            $bankOptional       = trim($params['bankOptional']); // 1 need check, 0 no need
            $bankID             = trim($params['bankID']);
            $branch             = trim($params['branch']);
            $bankCity           = trim($params['bankCity']);
            $accountHolder      = trim($params['accountHolder']);
            $accountNo          = trim($params['accountNo']);
            // $uploadData         = $params['uploadData']; // imageSize, imageType, imageName, imageFlag
            // additional info
            $martialStatus      = trim($params['martialStatus']); // single, married, widowed, divorced, separated
            $childNumber        = trim($params['childNumber']);
            $taxNumber          = trim($params['taxNumber']);

            $identityType       = trim($params['identityType']); // nric or passport
            $identityNumber     = trim($params['identityNumber']); // ktp number
            $passport           = trim($params['passport']); // passport
            // $ktpImage           = $params['ktpImage']; // imageSize, imageType, imageName, imageFlag

            $type               = trim($params['registerType']);
            $registerMethod     = trim($params['registerMethod']); // default username    
            $dateTime           = date("Y-m-d H:i:s");
            $date               = date("Y-m-d");
            $params["step"]     = 5;

            $site = $db->userType;
            $payerID = $db->userID;

            $validationResult = self::memberRegistration($params);

            if(strtolower($validationResult['status']) != 'ok'){
                return $validationResult;
            }

            $sponsorID = $validationResult['data']['sponsorID'];
            // $placementID = $validationResult['data']['placementID'];
            // $placementPosition = $validationResult['data']['placementPosition'];
            $productID = $validationResult['data']['productID'];
            $registerType = $validationResult["data"]["registerType"];
            $creditUnit = $validationResult["data"]["creditUnit"];
            $paymentCredit = $validationResult["data"]["paymentCredit"];
            $invoiceSpendData = $validationResult["data"]["invoiceSpendData"];
            $bonusValue = $validationResult["data"]["bonusValue"];
            $tierValue = $validationResult["data"]["tierValue"];
            $price = $validationResult["data"]["totalPrice"];
            $isBundlePackage = $validationResult["data"]["isBundlePackage"];
            $pinBelong = $validationResult["data"]["pinBelong"];
            $pinID = $validationResult["data"]["pinID"];
            $productData = $validationResult["data"]["productData"];
            $otpID = $validationResult["data"]["otpID"];
            $introducerID = $validationResult["data"]["introducerID"];

            $childAge = $validationResult["data"]["childAge"];

            if($site == "Admin"){
                $payerID = $sponsorID;
            }
            
            $unitPrice = General::getLatestUnitPrice();

            $dialingArea = str_replace("+", "", $dialingArea);
            $db->where("country_code", $dialingArea);
            $countryID = $db->getValue("country", "id");


            $clientID     = $db->getNewID();
            $batchID      = $db->getNewID();
            $belongID     = $db->getNewID();

            if($type != "free") $portfolioID  = $db->getNewID();

            switch ($type) {
                case 'credit':

                    //deduct payment
                    $paymentResult = Cash::paymentConfirmation($payerID, $registerType, $invoiceSpendData, $productID, $portfolioID, $creditUnit, $dateTime, $batchID);

                    $belongID = $batchID;
                    // $bonusValue = $creditUnit;
                    // $tierValue = 0;
                    
                    // insert invoice
                    $invoiceData['productId']          = $productID;
                    $invoiceData['bonusValue']         = $bonusValue;
                    $invoiceData['productPrice']       = $creditUnit;
                    $invoiceData['unitPrice']          = $unitPrice;
                    $invoiceData['belongId']           = $batchID;
                    $invoiceData['portfolioId']        = $portfolioID;

                    $invoiceDataArr[] = $invoiceData;
                    $invoiceResult = Invoice::insertFullInvoice($payerID, $creditUnit, $invoiceDataArr, $invoiceSpendData, 'mlm', $batchID);

                    // Failed to insert invoice
                    if (!$invoiceResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

                    $price = $creditUnit;
                    break;

                case 'package':
                    //deduct payment
                    $paymentResult = Cash::paymentConfirmation($payerID, $registerType, $invoiceSpendData, $productID, $portfolioID, $price, $dateTime, $batchID);

                    $belongID = $batchID;
                    
                    // insert invoice
                    $invoiceData['productId']          = $productID;
                    $invoiceData['bonusValue']         = $bonusValue;
                    $invoiceData['productPrice']       = $price;
                    $invoiceData['unitPrice']          = $unitPrice;
                    $invoiceData['belongId']           = $batchID;
                    $invoiceData['portfolioId']        = $portfolioID;
                    $invoiceDataArr[] = $invoiceData;
                    $invoiceResult = Invoice::insertFullInvoice($payerID, $price, $invoiceDataArr, $invoiceSpendData, 'mlm', $batchID);
                    
                    // Failed to insert invoice
                    if (!$invoiceResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00435"][$language] /* Failed to re-entry package. */, 'data' => "");

                    break;

                case 'pin':
                    //update pin 

                    $belongID = $pinBelong;
                    $reference = $pinID;
                    
                    $updatePinData = array(
                                                "client_id" => $clientID,
                                                "status" => "Used",
                                                "used_at" => $dateTime,
                                            );

                    $db->where("id", $pinID);
                    $db->update("mlm_pin", $updatePinData);
                    break;

                case 'free':
                    break;

                default: 
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Register Type.", 'data' => '');
                    break;
            }

            $password = Setting::getEncryptedPassword($password);
            // $tPassword = Setting::getEncryptedPassword($tPassword);
            $sponsorCode = General::generateSponsorCode();

            $memberID = self::generateMemberID();

            $dateOfBirth = date("Y-m-d H:i:s", $dateOfBirth);
            // insert into client table -----------
            $insertClientData = array(
                "id" => $clientID,
                "member_id" => $memberID,
                "name" => $fullName,
                "username" => $memberID, 
                "email" => $email,
                "dial_code" => $dialingArea,
                "phone" => $phone,
                "dob" => $dateOfBirth,
                "password" => $password,
                "sponsor_code" => $sponsorName,
                "address" => $address,
                "state_id" => $state,
                "country_id" => $country,
                "type" => "Client",
                "sponsor_id" => $sponsorID,
                "placement_id" => $placementID,
                "placement_position" => $placementPosition,
                "created_at" => $dateTime,
                "identity_number" => $identityNumber,
                "passport" => $passport,
                "register_method" => $registerMethod,
            );
    
            $insertClientResult  = $db->insert('client', $insertClientData);

            // Failed to insert client account
            if (!$insertClientResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");

            // insert into client_detail table -----------
            $insertClientDetailData = array(
                "client_id" => $clientID,
                'member_id' => $memberID,
                "gender" => $gender,
                "martial_status" => $martialStatus,
                "num_of_child" => $childNumber,
                "child_age" => $childNumber>0?$childAge:'',
                "tax_number" => $taxNumber,
                // "image_upload_name" => $ktpImage[0]['imageName'],
                // "image_upload_type" => $ktpImage[0]['imageType'],
            );

            $insertClientDetailResult  = $db->insert('client_detail', $insertClientDetailData);

            // Failed to insert client detail table
            if (!$insertClientResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member. */, 'data' => "");

            if($bankOptional){
                // insert into mlm_client_bank table -----------
                $insertClientBankDetail = array(
                    "client_id" => $clientID,
                    "bank_id" => $bankID,
                    "account_no" => $accountNo,
                    "account_holder" => $accountHolder,
                    "created_at" => $dateTime,
                    "status" => "Active",
                    "branch" => $branch,
                    "bank_city" => $bankCity,
                    // "upload_name" => $uploadData[0]['imageName'],
                    // "upload_type" => $uploadData[0]['imageType'],
                );

                $insertClientBankResult  = $db->insert('mlm_client_bank', $insertClientBankDetail);

                // Failed to insert mlm_client_bank table
                if (!$insertClientBankResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member. */, 'data' => "");
            }

            // insert into address table -----------
            $insertClientAddressDetail = array(
                "client_id" => $clientID,
                "name" => $fullName,
                "phone" => $phone,
                "address" => $address,
                "district_id" => $district,
                "sub_district_id" => $subDistrict,
                "post_code_id" => $postalCode,
                "city_id" => $city,
                "state_id" => $state,
                "country_id" => $country,
                "address_type" => "billing",
                // "remarks" => $remarks,
                "created_at" => $dateTime,
            );

            $insertClientAddressResult  = $db->insert('address', $insertClientAddressDetail);

            // Failed to insert address table
            if (!$insertClientAddressResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member. */, 'data' => "");


            if($addressType == 'delivery'){
                 // insert into address table for delivery row
                $insertClientDeliveryAddress = array(
                    "client_id" => $clientID,
                    "name" => $fullName,
                    "phone" => $phone,
                    "address" => $address,
                    "district_id" => $district,
                    "sub_district_id" => $subDistrict,
                    "post_code_id" => $postalCode,
                    "city_id" => $city,
                    "state_id" => $state,
                    "country_id" => $country,
                    "address_type" => "delivery",
                    // "remarks" => $remarks,
                    "created_at" => $dateTime,
                );

                $insertClientDeliveryAddressResult  = $db->insert('address', $insertClientDeliveryAddress);

                // Failed to insert address table
                if (!$insertClientDeliveryAddressResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member. */, 'data' => "");
            }

            $sponsorTree = Tree::insertSponsorTree($clientID, $sponsorID);

            // Failed to insert sponsorTree
            if (!$sponsorTree)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member. */, 'data' => "");

            // if($placementID){
            //     $placementTree = Tree::insertPlacementTree($clientID, $placementID,$placementPosition);
            //     if (!$placementTree) {
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member.  */, 'data' => "");
            //     }
            // }

            Leader::insertMainLeaderSetting($clientID, $sponsorID);
            
            //Copy sponsor's blocked country IP settings
//            $db->where('client_ID', $sponsorID);
//            $db->where('blocked','1');
//            $res=$db->get('client_country_ip_block',null,'country_code,blocked');
//            foreach ($res as $key => $value) {
//
//                $insertData = array(
//                    "client_ID"       => $clientID,
//                    "country_code"    => $value['country_code'],
//                    "blocked"         => $value['blocked']
//
//                );
//
//                $db->insert("client_country_ip_block", $insertData);
//            }

            if($type != "free"){
                //insert client portfolio
            
                $db->where("name","maturityDays");
                $db->where("product_id",$productID);
                $maturityDays = $db->getValue("mlm_product_setting","value");
                $expiredDate = $maturityDays ? date("Y-m-d H:i:s", strtotime($maturityDays)) : "";
                $insertData = array(    
                                        "portfolioID"  => $portfolioID,
                                        "clientID"     => $clientID,
                                        "productID"    => $productID,
                                        "price"        => $price,
                                        "bonusValue"   => $bonusValue,
                                        "tierValue"    => $tierValue,
                                        "type"         => $registerType,
                                        "belongID"     => $batchID,
                                        "referenceID"  => $reference,
                                        "batchID"      => $batchID,
                                        "status"       => "Active",
                                        "purchaseAt"   => $dateTime,
                                        "expire_at"    => $expiredDate,
                                        "pairingCap"   => $productData["pairingDailyCap"],
                );
                $portfolioId = self::insertClientPortfolio($insertData);

                //insert bonus value
                $bonusInData['clientID']    = $clientID;
                $bonusInData['mainID']      = $clientID;
                $bonusInData['type']        = $registerType;
                $bonusInData['productID']   = $productID;
                $bonusInData['belongID']    = $batchID;
                $bonusInData['batchID']     = $batchID;
                $bonusInData['bonusValue']  = $bonusValue;
                $bonusInData['dateTime']    = $dateTime;
                $bonusInData['processed']   = 0;
                $insertBonusResult = Bonus::insertBonusValue($bonusInData);
                
                //insert Credit Sources
                $db->where("name", "isMaxCapWallet");
                $db->where("value", "1");
                $maxCapIDAry = $db->getValue("credit_setting", "credit_id", null);
                if($maxCapIDAry){
                    $db->where("id", $maxCapIDAry, "IN");
                    $maxCapCreditAry = $db->map("name")->get("credit", null, "name");
                }

                $db->where('username', "creditSales");
                $db->where('type', "Internal");
                $internalID = $db->getValue('client', 'id');
                
                $db->where("product_id", $productID);
                $db->where("type","Credit Sources");
                $creditSourcesRes = $db->get("mlm_product_setting", null, "name, value");
                foreach($creditSourcesRes as $creditSourcesRow){
                    $creditAmount = Setting::setDecimal($creditSourcesRow["value"], $creditSourcesRow["name"]);
                    if($creditAmount > 0){
                        Cash::insertTAccount($internalID, $clientID, $creditSourcesRow["name"], $creditAmount, $registerType, $db->getNewID(), "", $dateTime, $batchID,  $clientID, "", $portfolioID);
                    }
                    if($maxCapCreditAry[$creditSourcesRow["name"]]){
                        $maxCapAmount += $creditAmount;
                    }
                }
                if($maxCapAmount > 0){
                    $db->where("id", $portfolioID);
                    $db->update("mlm_client_portfolio", array("max_cap" => $maxCapAmount));
                }
                //calculate rank and insert maxCap
                // $clientRankData = self::upgradeClientRank($clientID, $bonusValue, $dateTime, $portfolioID, $batchID, $registerType);
                self::updateMemberSalesData($clientID,"register", $bonusValue);
            }
            
            //email verified On/Off
            if(Setting::$systemSetting['disabledEmailVerified'] || $isBatchRegister == 1){
                $db->where("id",$clientID);
                $db->update("client",array("activated" => 1));
            
            } else {

                //insert verify code - extra work for email
//                $verifiedCode = Client::generateVerifiedCode();
//
//                $verifiedData = array(
//                                        "name" => "verifiedCode",
//                                        "value" => $verifiedCode,
//                                        "client_id" => $clientID,
//                                        "reference" => date("Y-m-d H:i:s"),
//                                    );
//                $db->insert("client_setting",$verifiedData);
//
//                $sendEmail = Client::sendVerifiedEmail($email, $username, $verifiedCode);
                
            }

            if($otpID){
                $db->where('id',$otpID,'IN');
                $db->update('sms_integration',array('expired_at'=>$db->now()));
            }
            // insert/update total downline
            Client::updateTotalDownline($clientID);
            // Client::updateTotalIntroducee($clientID);

            Custom::updateClientSales($clientID,$sponsorID,"","register",$dateTime);
            self::downlineRegistrationSendNotice($clientID, $sponsorID);

            // Insert 
            unset($insertData);
            $insertData = array(
                "client_id" => $clientID,
                "name" => "awardCycleDate",
                "value" => $date,// Cycle Start Date
                "type" => 0, // Director Rank Entitle count
                "reference" => 0, // Unicorn Rank Entitle count
            );
            $db->insert('client_setting',$insertData);

            // Insert for Leadership Cash Rewrad & Yearly acc status checking
            unset($insertData);
            $insertData = array(
                "client_id" => $clientID,
                "name" => "yearlyStartDate",
                "value" => $date,
                "type" => 0, 
                "reference" => 0, 
            );
            $db->insert('client_setting',$insertData);


            $activityData = array('user' => $fullName);
            $activityRes = Activity::insertActivity('Registration', 'T00001', 'L00001', $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");

            // Custom::upgradeGoldmineRank($clientID, $datetime);
            // Custom::upgradeGoldmineRank($sponsorID, $datetime);

            // Custom::upgradeSponsorRank($clientID, $datetime);
            // Custom::upgradeSponsorRank($sponsorID, $datetime);

            //insert mainleader (client_setting)
            // leader::insertMainLeaderSetting($clientID, $sponsorID);

            // Custom::updateMemberActiveStatus($clientID, $dateTime);

            // upload data to DO
            // $count = 1;
            // $updateImgData = array();
            // $groupCode = General::generateUniqueChar("mlm_client_bank","upload_name");

            // foreach ($uploadData as $element) {
            //     if ($element['imageFlag'] == 1) {

            //         $fileType = end(explode(".", $element['imageName']));
            //         $upload_name = time()."_".General::generateUniqueChar("mlm_client_bank","upload_name")."_".$groupCode.".".$fileType;

            //         if ($count == 1) {
            //             $updateImgData['image_name'] = $upload_name;
            //         } else {
            //             $updateImgData['image_name_'.$count] = $upload_name;
            //         }
            //     }

            //     $count++;
            // }

            // $count_ktp = 1;
            // $updateImgDataKtp = array();
            // $groupCodeKtp = General::generateUniqueChar("client_detail","image_upload_name");

            // foreach ($ktpImage as $element) {
            //     if ($element['imageFlag'] == 1) {

            //         $fileType = end(explode(".", $element['imageName']));
            //         $upload_name = time()."_".General::generateUniqueChar("client_detail","image_upload_name")."_".$groupCodeKtp.".".$fileType;

            //         if ($count_ktp == 1) {
            //             $updateImgDataKtp['image_name'] = $upload_name;
            //         } else {
            //             $updateImgDataKtp['image_name_'.$count_ktp] = $upload_name;
            //         }
            //     }

            //     $count_ktp++;
            // }

            // if ($updateImgData) {
            //     $data['uploadImageData'] = $updateImgData;

            // }

            // if ($updateImgDataKtp){
            //     $data['uploadImageDataKtp'] = $updateImgDataKtp;
            // }

            // if($updateImgDataKtp || $updateImgData){
            //     $data["doRegion"] = Setting::$configArray["doRegion"];
            //     $data["doEndpoint"] = Setting::$configArray["doEndpoint"];
            //     $data["doAccessKey"] = Setting::$configArray["doApiKey"];
            //     $data["doSecretKey"] = Setting::$configArray["doSecretKey"];
            //     $data["doBucketName"] = Setting::$configArray["doBucketName"];
            //     $data["doProjectName"] = Setting::$configArray["doProjectName"];
            //     $data["doFolderName"] = Setting::$configArray["doFolderName"];
            // }

            // $dataOut["uploadData"] = $data;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00145"][$language] /* Registration successful. */, 'data' => "");
        }

        public function insertProfileDetails($params,$userID = 0) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $username = trim($params['username']);
            $countryID = trim($params['countryID']);
            $transactionPassword = trim($params['transactionPassword']);
            $confirmTransactionPassword = trim($params['confirmTransactionPassword']);
            $sponsorUsername = trim($params['sponsorUsername']);
            $avatar = trim($params['avatar']);

            $maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass = Setting::$systemSetting['minTransactionPasswordLength'];

            $db->where('id',$userID);
            $isValidUser = $db->has('client');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>array('field'=>'user'));

            $db->where('id',$userID);
            $isCompleted = $db->getValue('client','username');
            if ($isCompleted) {
                $errorFieldArr[] = array(
                    'id' => 'userError',
                    'msg' => 'User profile already completed.'
                );
            }

            if (!$username) {
                $errorFieldArr[] = array(
                    'id' => 'usernameError',
                    'msg' => $translations['E00323'][$language]
                );
            }

            if (!$countryID) {
                $errorFieldArr[] = array(
                    'id' => 'countryError',
                    'msg' => $translations['E00568'][$language]
                );
            } else {
                $db->where('id',$countryID);
                $db->where('status','Active');
                $isValidCountry = $db->has('country');
                if (!$isValidCountry) {
                    $errorFieldArr[] = array(
                        'id' => 'countryError',
                        'msg' => $translations['E00568'][$language]
                    );
                }
            }

            if (empty($sponsorUsername)) {
                $errorFieldArr[] = array(
                    'id' => 'sponsorUsernameError',
                    'msg' => $translations['E00320'][$language]
                );
            } else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");

                $sponsorDownlineAry = Tree::getSponsorTreeDownlines($sponsorID);
                if (!$sponsorID) {
                    $errorFieldArr[] = array(
                        'id' => 'sponsorUsernameError',
                        'msg' => $translations['E00321'][$language]
                    );
                } else if (!in_array($sponsorID, $sponsorDownlineAry)) {
                    $errorFieldArr[] = array(
                        'id' => 'sponsorUsernameError',
                        'msg' => $translations['E00820'][$language]
                    );
                }
            }

            if (!$transactionPassword) {
                $errorFieldArr[] = array(
                    'id' => 'transactionPasswordError',
                    'msg' => $translations['E00919'][$language]
                );
            } elseif (!preg_match("#[0-9]+#", $transactionPassword)) {
                $errorFieldArr[] = array(
                    'id' => 'transactionPasswordError',
                    'msg' => $translations['E00919'][$language]
                );
            } elseif (!preg_match("#[a-zA-z]+#", $transactionPassword)) {
                $errorFieldArr[] = array(
                    'id' => 'transactionPasswordError',
                    'msg' => $translations['E00919'][$language]
                );
            } else {
                if (strlen($transactionPassword) < $minTPass || strlen($transactionPassword) > $maxTPass) {
                    $errorFieldArr[] = array(
                        'id' => 'transactionPasswordError',
                        'msg' => $translations['E00919'][$language]
                    );
                }
            }

            if (!$confirmTransactionPassword) {
                $errorFieldArr[] = array(
                    'id' => 'confirmTransactionPasswordError',
                    'msg' => $translations['E00919'][$language]
                );
            } elseif ($transactionPassword != $confirmTransactionPassword) {
                $errorFieldArr[] = array(
                    'id' => 'confirmTransactionPasswordError',
                    'msg' => $translations['E00313'][$language]
                );
            }

            if (!$avatar) {
                $errorFieldArr[] = array(
                    'id' => 'avatarError',
                    'msg' => $translations['E00920'][$language]
                );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            $transactionPassword = Setting::getEncryptedPassword($transactionPassword);

            $updateData = array(
                'username' => $username,
                'transaction_password' => $transactionPassword,
                'country_id' => $countryID,
                'avatar' => $avatar,
                'sponsor_id' => $sponsorID
            );
            $db->where('id',$userID);
            $db->update('client',$updateData);

            $sponsorTree = Tree::insertSponsorTree($userID, $sponsorID);
            if (!$sponsorTree)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "");

            return array('status'=>'ok','code'=>'1','statusMsg'=>$translations['E00696'][$language],'data'=>'');
        }

        public function upgradeClientRank($clientID, $currentBonusValue, $dateTime, $portfolioID, $batchID, $registerType){
            $db = MysqliDb::getInstance();      
            $language = General::$currentLanguage;
            $translations = General::$translations;   
            $rankType = "Bonus Tier";
            
            if(!$clientID){
                return false;
            }

            $db->where("client_id", $clientID);
            $db->where("status","Active");
            $totalBV = $db->getValue("mlm_client_portfolio", "sum(bonus_value + tier_value)");

            //get all rank setting
            $db->where("type", $rankType);
            $db->orderBy("priority","DESC");
            $rankIDAry = $db->map("id")->get("rank",null, "id, name");

            $rankSettingRes = $db->get("rank_setting", null, "rank_id, name, value, type, reference");
            foreach($rankSettingRes as $rankSettingRow){
                if($rankSettingRow["type"] == "percentage"){
                    if($rankSettingRow["name"] == "goldmineBonusPercentage"){
                        $rankSettingRow["value"] = "";
                    }
                    $rankSettingAry[$rankSettingRow["rank_id"]][$rankSettingRow["name"]] = $rankSettingRow["value"];
                }else if($rankSettingRow["type"] == "purchase"){
                    $minRankQualification[$rankSettingRow["rank_id"]][$rankSettingRow["name"]] = $rankSettingRow["value"];
                }

                if($rankSettingRow["reference"] == "Income Cap"){
                    $rankMaxCapAry[$rankSettingRow["rank_id"]][$rankSettingRow["name"]] = $rankSettingRow["value"];
                }
            }

            foreach ($rankIDAry as $rankID => $rankName) {
                $minTotalBV = $minRankQualification[$rankID]["minRankQualification"];
                if($totalBV >= $minTotalBV){
                    $clientRankID = $rankID;
                    $clientRankData = $rankSettingAry[$rankID];
                    $clientRankIncomeCap = $rankMaxCapAry[$rankID];
                    break;
                }
            }
            
            $db->where("client_id", $clientID);
            $db->where("rank_type",$rankType);
            $db->where("type","System");
            $db->orderBy("created_at","ASC");
            $clientRankRes = $db->get("client_rank", null, "name, value, rank_id");
            foreach($clientRankRes as $clientRankRow){
                $prevRankAry[$clientRankRow["name"]] = $clientRankRow;
            }
            if(empty($prevRankAry)){
                //insert
                $insertClientRank = array(
                    'client_id'  => $clientID,
                    'name'       => "rankDisplay", // rank_setting (name) 
                    'rank_id'    => $clientRankID,
                    'value'      => "", // rank_setting (value)  
                    'rank_type'  => $rankType,
                    'type'       => 'System', // rank_setting (type) 
                    'created_at' => $db->now(),
                );
                $db->insert('client_rank', $insertClientRank);

                foreach($clientRankData as $dataName => $dataPercentage){
                    $insertClientRank = array(
                        'client_id'  => $clientID,
                        'name'       => $dataName, // rank_setting (name) 
                        'rank_id'    => $clientRankID,
                        'value'      => $dataPercentage, // rank_setting (value)  
                        'rank_type'  => $rankType,
                        'type'       => 'System', // rank_setting (type) 
                        'created_at' => $db->now(),
                    );
                    $db->insert('client_rank', $insertClientRank); 
                }
            }else{

                if($prevRankAry["rankDisplay"]["rank_id"] != $clientRankID){
                    $insertClientRank = array(
                        'client_id'  => $clientID,
                        'name'       => "rankDisplay", // rank_setting (name) 
                        'rank_id'    => $clientRankID,
                        'value'      => "", // rank_setting (value)  
                        'rank_type'  => $rankType,
                        'type'       => 'System', // rank_setting (type) 
                        'created_at' => $db->now(),
                    );
                    $db->insert('client_rank', $insertClientRank);
                }

                foreach($clientRankData as $dataName => $dataPercentage){

                    if($prevRankAry[$dataName]["rank_id"] != $clientRankID){
                        //insert new
                        $insertClientRank = array(
                            'client_id'  => $clientID,
                            'name'       => $dataName, // rank_setting (name) 
                            'rank_id'    => $clientRankID,
                            'value'      => $dataPercentage, // rank_setting (value)  
                            'rank_type'  => $rankType,
                            'type'       => 'System', // rank_setting (type) 
                            'created_at' => $db->now(),
                        );
                        $db->insert('client_rank', $insertClientRank); 
                    }
                }

            }


            if($currentBonusValue > 0){
                //maxCap
                $db->where("client_id",$clientID);
                $db->where("rank_type",$rankType);
                $db->where("name","maxCap");
                $db->where("type","Admin");
                $db->orderBy("created_at","ASC");
                $adminSetMaxCapRes = $db->get("client_rank",null, "id, name, value");
                foreach ($adminSetMaxCapRes as $adminSetMaxCapRow) {
                    $adminSetIncomeCap[$adminSetMaxCapRow["name"]] = $adminSetMaxCapRow["value"];
                }

                if($adminSetIncomeCap["maxCap"] > $clientRankIncomeCap["maxCap"]){
                    $clientRankIncomeCap = $adminSetIncomeCap;
                }

                //insert income cap
                $db->where('username', "creditSales");
                $db->where('type', "Internal");
                $internalID = $db->getValue('client', 'id');

                foreach ($clientRankIncomeCap as $creditType => $percentage) {
                    $maxCapAmount = Setting::setDecimal(($currentBonusValue * $percentage/100), $creditType);
                    if($maxCapAmount > 0){
                        Cash::insertTAccount($internalID, $clientID, $creditType, $maxCapAmount, $registerType, $db->getNewID(), "", $dateTime, $batchID,  $clientID, "", $portfolioID);
                    }
                    if($maxCapType == 'maxCap'){
                        $maxCapValue = $maxCapAmount;
                    }
                }

                $updateMaxCap = array('max_cap' => $maxCapValue);
                $db->where('id', $portfolioID);
                $db->update('mlm_client_portfolio', $updateMaxCap);
            }
            

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function adminBatchRegistration($params, $site) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $fileDataBase64 = base64_decode((string)$params['base64']);
            $tmp_handle = tempnam(sys_get_temp_dir(), 'adminBatchRegistration');

            $handle = fopen($tmp_handle, 'r+');
            fwrite($handle, $fileDataBase64);
            rewind($handle);

            $fileType = PHPExcel_IOFactory::identify($tmp_handle);
            $objReader = PHPExcel_IOFactory::createReader($fileType);
            
            $excelObj = $objReader->load($tmp_handle);
            $worksheet = $excelObj->getSheet(0);
            $lastRow = $worksheet->getHighestRow();
            $lastCol = $worksheet->getHighestColumn();
            $lastCol++;

            if($lastRow <= 1)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00355"][$language] /* The selected file cannot be empty */, 'data' => "");

            if($worksheet->getCell('B1')->getValue() != "Full Name")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");
    
            if($worksheet->getCell('C1')->getValue() != "Email Address")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('D1')->getValue() != "Mobile Number")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");
            
            if($worksheet->getCell('E1')->getValue() != "Date Of Birth")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('F1')->getValue() != "Gender")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('G1')->getValue() != "Login Password")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");
            // check password

            if($worksheet->getCell('H1')->getValue() != "Sponsor Name")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('I1')->getValue() != "Address")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('J1')->getValue() != "District")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('K1')->getValue() != "Sub District")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('L1')->getValue() != "City")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('M1')->getValue() != "Postal Code")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('N1')->getValue() != "State")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('O1')->getValue() != "Country")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('P1')->getValue() != "Billing Address")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('Q1')->getValue() != "Bank")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('R1')->getValue() != "Branch")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('S1')->getValue() != "Bank City")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('T1')->getValue() != "Account Holder")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('U1')->getValue() != "Account Number")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");
            
            if($worksheet->getCell('V1')->getValue() != "Marital Status")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('W1')->getValue() != "Child Number")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('X1')->getValue() != "Tax Number")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('Y1')->getValue() != "Identity Type")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('Z1')->getValue() != "Identity Number")
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if($worksheet->getCell('AA1')->getValue() != "Placement Position") 
                return array('status' => "error", 'code' => 1, 'statusMSg' => $translations["E00356"][$language] /* The selected file is in the wrong format */, 'data' => "");

            if(
                $worksheet->getCell('B2')->getValue() == "" 
                || $worksheet->getCell('C2')->getValue() == "" 
                || $worksheet->getCell('D2')->getValue() == "" 
                || $worksheet->getCell('E2')->getValue() == "" 
                || $worksheet->getCell('F2')->getValue() == "" 
                || $worksheet->getCell('G2')->getValue() == "" 
                || $worksheet->getCell('H2')->getValue() == "" 
                || $worksheet->getCell('I2')->getValue() == "" 
                || $worksheet->getCell('J2')->getValue() == ""
                || $worksheet->getCell('K2')->getValue() == ""
                || $worksheet->getCell('L2')->getValue() == ""
                || $worksheet->getCell('M2')->getValue() == ""
                || $worksheet->getCell('N2')->getValue() == ""
                || $worksheet->getCell('O2')->getValue() == ""
                || $worksheet->getCell('P2')->getValue() == ""
                || $worksheet->getCell('Q2')->getValue() == ""
                || $worksheet->getCell('R2')->getValue() == ""
                || $worksheet->getCell('S2')->getValue() == ""
                || $worksheet->getCell('T2')->getValue() == ""
                || $worksheet->getCell('U2')->getValue() == ""
                || $worksheet->getCell('V2')->getValue() == ""
                // || $worksheet->getCell('W2')->getValue() == "" //child number
                || $worksheet->getCell('X2')->getValue() == ""
                || $worksheet->getCell('Y2')->getValue() == ""
                || $worksheet->getCell('Z2')->getValue() == ""
                || $worksheet->getCell('AA2')->getValue() == ""
            )
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Empty row detected', 'data' => "");            

            $dataInsert = array (
                                    'data'       => $params['base64'],
                                    'type'       => $params['type'],
                                    'created_at' => $db->now()
                                );
            $uploadID = $db->insert('uploads', $dataInsert);

            if(empty($uploadID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] , 'data' => "");


            $dataInsert = array (
                                    'type'              => 'adminBatchRegistration',
                                    'attachment_id'     => $uploadID,
                                    'attachment_name'   => $params['name'],
                                    'creator_id'        => $params['clientID'],
                                    'creator_type'      => $site,
                                    'created_at'        => $db->now()
                                );
            $importID = $db->insert('mlm_import_data', $dataInsert);

            if(empty($importID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00357"][$language] /* Failed to mass change password */, 'data' => "");

            $recordCount = 0; $processedCount = 0; $failedCount = 0;


            for($row=2; $row<=$lastRow; $row++) {

                // $recordCount++;

                $fullName = $worksheet->getCell('B'.$row)->getValue();
                $email = $worksheet->getCell('C'.$row)->getValue();
                $phone = $worksheet->getCell('D'.$row)->getValue();
                $dateOfBirth = $worksheet->getCell('E'.$row)->getValue();
                $gender = strtolower($worksheet->getCell('F'.$row)->getValue());
                $password = $worksheet->getCell('G'.$row)->getValue();

                $sponsorName = $worksheet->getCell('H'.$row)->getValue();
                $address = $worksheet->getCell('I'.$row)->getValue();
                $district = $worksheet->getCell('J'.$row)->getValue();
                $subDistrict = $worksheet->getCell('K'.$row)->getValue();
                $city = $worksheet->getCell('L'.$row)->getValue();
                $postalCode = $worksheet->getCell('M'.$row)->getValue();
                $state = $worksheet->getCell('N'.$row)->getValue();
                $country = $worksheet->getCell('O'.$row)->getValue();
                $addressType = $worksheet->getCell('P'.$row)->getValue();
                $bankName = $worksheet->getCell('Q'.$row)->getValue();
                $branch = $worksheet->getCell('R'.$row)->getValue();
                $bankCity = $worksheet->getCell('S'.$row)->getValue();
                $accountHolder = $worksheet->getCell('T'.$row)->getValue();
                $accountNo = $worksheet->getCell('U'.$row)->getValue();
                $martialStatus = strtolower($worksheet->getCell('V'.$row)->getValue());
                $childNumber = $worksheet->getCell('W'.$row)->getValue() ? : 0;
                $taxNumber = $worksheet->getCell('X'.$row)->getValue();
                $identityType = $worksheet->getCell('Y'.$row)->getValue();
                $identityNumber = $worksheet->getCell('Z'.$row)->getValue();
                $placementPosition = $worksheet->getCell('AA'.$row)->getValue();
                
                unset($checkPassword);
                unset($icNumber);
                unset($passport);

                $checkPassword = $password;

                if($identityType == 'KTP') $identityType = 'nric';
                // nric or passport
                if($identityType == 'nric') {
                    $icNumber = $identityNumber;
                }else{
                    $passport = $identityNumber;
                }

                $errorMessage = "";

                if (!$fullName){
                    $emptyRowCount++;
                    if ($emptyRowCount>=5){
                        break;//IF too many consecutive empty rows, break out of the loop
                    }
                    continue;
                }
                $emptyRowCount=0;

                $recordCount++;

                $db->where("name", $country);
                $copyDb = $db->copy();
                $countryID = $db->getValue("country", "id");
                $dialingArea = $copyDb->getValue("country","country_code");

                if(!$countryID || !$dialingArea){
                    $errorMessage = "Insert Wrong Country Name.";
                }

                $db->where("name", $state);
                $stateID = $db->getValue("state", "id");

                if(!$stateID){
                    $errorMessage = "Insert Wrong State Name.";
                }

                $db->where("name",$city);
                $db->where("state_id",$stateID);
                $cityID = $db->getValue("city","id");

                if(!$cityID){
                    $errorMessage = "Insert Wrong City Name.";
                }

                $db->where("name",$district);
                $db->where("city_id",$cityID);
                $districtID = $db->getValue("county","id");

                if(!$districtID){
                    $errorMessage = "Insert Wrong District Name.";
                }

                $db->where("name",$subDistrict);
                $db->where("county_id",$districtID);
                $subDistrictID = $db->getValue("sub_county","id");

                if(!$subDistrictID){
                    $errorMessage = "Insert Wrong Sub District Name.";
                }

                $db->where("name",$postalCode);
                $db->where("sub_county_id",$subDistrictID);
                $postalCodeID = $db->getValue("zip_code","id");

                if(!$postalCodeID){
                    $errorMessage = "Insert Wrong Postal Code.";
                }

                $db->where('status', 'Active');
                $db->where("name", $bankName);
                $bankID = $db->getValue("mlm_bank", "id");

                if(!$bankID){
                    $errorMessage = "Insert Wrong Bank Name.";
                }

                $db->where("name", $sponsorName);
                $sponsorMemberID = $db->getValue("client", "member_id");

                if(!$sponsorMemberID){
                    $errorMessage = "Insert Wrong Sponsor Name.";
                }

                $dateOfBirth = strtotime($dateOfBirth);

                if (strtolower($placementPosition) != "left" && strtolower($placementPosition) != "right" || $placementPosition == "" ){
                    $errorMessage = "Insert Wrong Placement Position";
                }

                if(strtolower($placementPosition) == "left"){
                    $placementPosition = 1;
                } else{
                    $placementPosition = 2;
                }

                $registerParams = array(
                    "batchRegister" => '1',
                    // personal information
                    "fullName" => $fullName,
                    "email" => $email,
                    "dialingArea" => $dialingArea,
                    "phone" => $phone,
                    "dateOfBirth" => $dateOfBirth,
                    "gender" => $gender,
                    "password" => $password,
                    "checkPassword" => $checkPassword,
                    "sponsorName" => $sponsorMemberID,
                    "placementPosition" => $placementPosition,

                    // billing address and delivery address
                    "address" => $address,
                    "addressType" => $addressType,
                    "district" => $districtID,
                    "subDistrict" => $subDistrictID,
                    "city" => $cityID,
                    "postalCode" => $postalCodeID,
                    "state" => $stateID,
                    "country" => $countryID,
                    "remarks" => $remarks,

                    // bank info
                    "bankID" => $bankID,
                    "branch" => $branch,
                    "bankCity" => $bankCity,
                    "accountHolder" => $accountHolder,
                    "accountNo" => $accountNo,

                    // additional info
                    "martialStatus" => $martialStatus,
                    "childNumber" => $childNumber,
                    "taxNumber" => $taxNumber,
                    "identityType" => $identityType,
                    "identityNumber" => $icNumber,
                    "passport" => $passport,

                    "registerType" => "free",
                );

                if(empty($errorMessage)){

                    $result = Self::memberRegistrationConfirmation($registerParams);

                    if($result["status"] == "ok"){
                        $status = "Success";
                        $processedCount++;
                        $errorMessage = "";
                    }else{
                        $status = "Failed";
                        $failedCount++;
                        $errorMessage = $result["data"]["field"][0]["msg"];
                    }
                }else{
                    $status = "Failed";
                    $failedCount++;
                }

                $json = json_encode($registerParams);

                $dataInsert = array (
                                        'mlm_import_data_id' => $importID,
                                        'data'               => $json,
                                        'processed'          => "1",
                                        'status'             => $status,
                                        'error_message'      => $errorMessage
                                    );
                $ID = $db->insert('mlm_import_data_details', $dataInsert);

                if(empty($ID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            }

            $dataUpdate = array (
                                    'total_records'     => $recordCount,
                                    'total_processed'   => $processedCount,
                                    'total_failed'      => $failedCount
                                );
            $db->where('id', $importID);
            $db->update('mlm_import_data', $dataUpdate);

            $handle = fclose($handle);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function updateMemberSalesData($clientID, $type, $bonusValue){
            $db = MysqliDb::getInstance();

            if(!$clientID){
                return false;
            }

            if(!$type){
                return false;
            }

            if($bonusValue <= 0){
                return false;
            }
            $db->where("name", "ownSales");
            $db->where("client_id",$clientID);
            $bonusValueID = $db->getValue("client_setting","id");
            if(!$bonusValueID){
                $insertData = array(    
                                        "client_id" => $clientID,
                                        "name" => "ownSales",
                                        "value" => $bonusValue,
                                    );
                $db->insert("client_setting", $insertData);
            }else{
                $db->where("name", "ownSales");
                $db->where("client_id",$clientID);
                switch ($type) {
                    case 'terminated':
                        $db->update("client_setting",array("value" => $db->dec($bonusValue)));
                        break;
                    
                    default:
                        $db->update("client_setting",array("value" => $db->inc($bonusValue)));
                        break;
                }
            }

            unset($insertData);

            $uplineIDAry = Tree::getSponsorUplineByClientID($clientID);
            foreach ($uplineIDAry as $uplineID) {
                unset($groupSalesID);

                $db->where("name", "groupSales");
                $db->where("client_id",$uplineID);
                $groupSalesID = $db->getValue("client_setting","id");

                if(!$groupSalesID){
                    $insertData = array(
                                            "client_id" => $uplineID,
                                            "name" => "groupSales",
                                            "value" => $bonusValue,
                                        );
                    $db->insert("client_setting", $insertData);

                }else{
                    $db->where("name", "groupSales");
                    $db->where("client_id",$uplineID);
                    switch ($type) {
                        case 'terminated':
                            $db->update("client_setting",array("value" => $db->dec($bonusValue)));
                            break;
                        
                        default:
                            $db->update("client_setting",array("value" => $db->inc($bonusValue)));
                            break;
                    }
                }
            }

            return true;
        }

        public function getReentryData($params, $type){
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $site = $db->userType;
            $clientID = $params["clientID"];
            $registerType = $params["registerType"] ? $params["registerType"] : "Package Reentry";
            if(!$clientID){
                $clientID = $db->userID;
            }

            if($site == "Admin"){
                $clientID = $params["clientID"];
            }

            if(!$clientID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Member.', 'data' => "");
            }

            if($type == "upgrade" && !$params['portfolioID']) return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid portfolioID.', 'data' => "");

            $db->where("id",$clientID);
            $clientRow = $db->getOne("client", "id, username, main_id, sponsor_id");
            $mainID = $clientRow["main_id"];
            $sponsorID = $clientRow["sponsor_id"];
            if(!$sponsorID){
                $db->where("id",$mainID);
                $clientRow = $db->getOne("client", "id, username, main_id, sponsor_id");
                $sponsorID = $clientRow["sponsor_id"];
            }
            
            $db->where("id",$sponsorID);
            $sponsorRow = $db->getOne("client","id, username");

            $dataOut["sponsorUsername"] = $sponsorRow["username"];

            $highestProductID = 0;
            if($type == "upgrade"){
            	$db->where("id",$params['portfolioID']);
            }else{
            	$db->where("client_id", $clientID);
            	$db->orderBy("id","DESC");
            }
            
            $res = $db->getOne("mlm_client_portfolio", "product_id,product_price");
            $highestProductID = $res["product_id"];
            $currPrice = $res["product_price"];

            if($type == "upgrade" && !$highestProductID) return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid portfolio.', 'data' => "");

            $highestPriority = 0;
            if($highestProductID){
                $db->where("id", $highestProductID);
                $highestPriority = $db->getValue("mlm_product", "priority");
            }
            $dataOut["highestPriority"] = $highestPriority;
            $productReturn = Product::getProductList("", "package");
            $productData = $productReturn["data"];
            foreach ($productData as $productID => $productRow) {
                unset($validClientAry);

                $productRow["bonusValue"] = $productRow["bonusValue"]["value"];

                if($productRow["priority"] <= $highestPriority){
                    $productRow["isDisabled"] = 1;
                }
                if($type == "upgrade"){
                	$productRow["price"] -= $currPrice;
                }
                $price = $productRow["price"];
                $paymentSetting = Cash::getPaymentDetail($clientID, $registerType, $price, $productID, "");
                $productRow["paymentMethod"] = $paymentSetting['data']["paymentData"];
                $validProductList[] = $productRow;
            }
            $dataOut["pairingAmount"] = $pairingAmount;
            $dataOut["productList"] = $validProductList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $dataOut);
        }

        public function updateSponsorGroupSales($clientID,$type,$downlineIDArray = array()) {
            $db = MysqliDb::getInstance();

            if (!$clientID)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user failed to update sponsor group sales.','data'=>'');

            $typeArray = array('increase','decrease');
            if (!$type || !in_array($type,$typeArray))
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid update type failed to update sponsor group sales.','data'=>'');

            $downlineIDArray[$clientID] = $clientID;
            $db->where('client_id',$downlineIDArray,'IN');
            // $db->where('status','Active');
            $totalBV = $db->getValue('mlm_client_portfolio','SUM(bonus_value)');

            $uplineIDArray = Tree::getSponsorUplineByClientID($clientID,false);
            
            if ($totalBV > 0) {
                foreach ($uplineIDArray as $uplineID) {
                    $db->where('client_id',$uplineID);
                    $db->where("name", "groupSales");
                    $hasRecord = $db->getValue('client_setting','COUNT(*)');

                    if ($hasRecord) {
                        $db->where('client_id',$uplineID);
                        $db->where("name", "groupSales");
                        if ($type == 'increase')
                            $db->update("client_setting",array("value" => $db->inc($totalBV)));
                        elseif ($type == 'decrease')
                            $db->update("client_setting",array("value" => $db->dec($totalBV)));
                    } else {
                        if ($type == 'increase') {
                            $insertData = array(
                                'name' => 'groupSales',
                                'value' => $db->inc($totalBV),
                                'client_id' => $uplineID,
                            );
                            $db->insert('client_setting',$insertData);
                        }
                    }
                }
            }

            return array('status'=>'ok','code'=>'1','statusMsg'=>'','data'=>'');
        }

        public function downlineRegistrationSendNotice($clientID, $sponsorID){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $memberSite = Setting::$configArray['memberSite'];
            $companyInfo = Setting::$systemSetting['companyInfo'];

            $socialDetail = json_decode($companyInfo, true);

            $db->where('id', $clientID);
            $sendDetails = $db->getOne('client',null,'member_id, name');

            $newMemberID = $sendDetails['member_id'];
            $newMemberName = $sendDetails['name'];

            $db->where('id',$sponsorID);
            $senderEmail = $db->getOne('client','email');

            $recipient = $senderEmail['email'];;//recipient is email destination
            $sendType = 'email';

            if($sendDetails){
                $subject = $translations['B00486'][$language]; //Rekrutmen Tim
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
                                <h3 class="companyTxt1">'.$translations['B00486'][$language].'</h3> 
                                <div class="longLine"></div>
                                <p class="companyTxt3">'.$translations['B00487'][$language].' '.$newMemberID.' - '.$newMemberName.'</p>
                                <p class="companyTxt3">'.$translations['B00488'][$language].'</p>
                                <p class="companyTxt3">'.$translations['B00474'][$language].'</p>
                                <div class="shortLine"></div>
                                <p class="companySmallTxt">'.$translations['B00489'][$language].'</p>
                            </div>
         
                            </div>
                        </body>
                        </html>
                ';

                $result = Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType,'','','','',1, $attachmentFlag);
            }
            return array("status" => "ok", "code" => 0, "statusMsg" => "Email Sent Successfully", "data" => "");
        }
    }
?>
