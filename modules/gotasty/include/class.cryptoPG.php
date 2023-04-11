<?php

    class CryptoPG
    {
        function __construct($client) {
            // $this->db = $db;
            // $this->setting = $setting;
            // $this->message = Client::validation->bonus->cash->message;
            // $this->provider = Client::validation->bonus->cash->provider;
            // $this->log = Client::validation->bonus->cash->log;
            // $this->invoice = Client::validation->invoice;
            // $this->general = $general;
            // $this->client = $client;
            // $this->cash = Client::validation->bonus->cash;
            // $this->bonus = Client::validation->bonus;
        }

        public function theNuxFundInCallBack($params) {
            $db             = MysqliDb::getInstance();

            $originalData   = trim($params['originalData']);
            $walletAddress  = trim($params['walletAddress']);
            $transactionHash= trim($params['transactionHash']);
            $referenceID    = trim($params['referenceID']);
            $receivedTxID   = trim($params['receivedTxID']);
            $callBackAmount = trim($params['receivedAmount']);
            $returnCurrency = trim($params['returnCurrency']);
            $returnStatus   = trim($params['status']);
            $entryKey       = trim($params['entryKey']);
            $dateTime       = date("Y-m-d H:i:s");
            $marketRate     = trim($params['exchangeRate']);

            $tag = trim($params['tag']);
            if($tag) $walletAddress = $walletAddress.":::ucl:::".$tag;

            $insert = array(
                "transaction_hash" => $db->escape($transactionHash),
                "received_tx_id" => $db->escape($receivedTxID),
                "wallet_address" => $db->escape($walletAddress),
                "status" => $db->escape($returnStatus),
                "created_at" => date("Y-m-d H:i:s")
            );
            $hashID = $db->insert('mlm_hash_log', $insert);

            $db->where("id", $hashID, "<");
            $db->where("transaction_hash", $transactionHash);
            $db->where("received_tx_id", $receivedTxID);
            $db->where("wallet_address", $walletAddress);
            $db->where("status", $returnStatus);
            $returnID = $db->getValue("mlm_hash_log", "id");
            if(!empty($returnID)){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Already paid.", 'data' => $transactionHash);
            }

            $convert =  self::getCryptoConverter($returnCurrency);

            $db->where("received_tx_id", $receivedTxID);
            $db->where("tx_ref_id", $referenceID);
            // $db->where("transaction_hash", $transactionHash);
            $db->where("status", array("Success", "Failed"), "IN");
            if($db->has("mlm_crypto_PG")) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid", 'data' => $transactionHash);
            }

            $db->where("wallet_address", $walletAddress);
            if(!$db->has("mlm_crypto_PG")) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Wallet Address Not Found In the System", 'data' => '');
            }

            switch ($returnStatus) {
                case 'received':
                    $db->where('wallet_address', $walletAddress);
                    $db->where('status', 'Pending');
                    $recordData = $db->getOne('mlm_crypto_PG', 'id, received_tx_id, status');
                    if(empty($recordData)){
                        $db->where('wallet_address', $walletAddress);
                        $db->where('received_tx_id', $receivedTxID);
                        $db->where("tx_ref_id", $referenceID);
                        $db->where('status', array('Success', 'Processing', 'Received'), 'IN');
                        $tempRecordData = $db->getOne('mlm_crypto_PG', 'id, received_tx_id, status');
                        if($tempRecordData){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid", 'data' => $tempRecordData);
                        }

                        $db->where('wallet_address', $walletAddress);
                        $addressData = $db->getOne('mlm_crypto_PG', 'id, client_id, coin_type, pay_credit_type');
                        $rowID = self::insertCryptoPG($addressData['client_id'], $walletAddress, '0', $convert['sysName'], $addressData['pay_credit_type'], 'Received', '', '', $dateTime, '',$receivedTxID, $referenceID, $callBackAmount, $dateTime);
                        $creditPayment = 1;
                        $creditRecordID = $rowID;
                    } else {
                        $updateData = array(
                            "call_back_amount" => $callBackAmount,
                            "call_back_at" => $dateTime,
                            "status" => "Received",
                            "received_tx_id" => $receivedTxID,
                            "tx_ref_id" => $referenceID
                        );
                        $db->where('id', $recordData['id']);
                        $db->update('mlm_crypto_PG', $updateData);

                        $creditPayment = 1;
                        $creditRecordID = $recordData['id'];
                    }

                    break;

                case 'pending':
                    $db->where('wallet_address', $walletAddress);
                    $db->where('received_tx_id', $receivedTxID);
                    $db->where("tx_ref_id", $referenceID);
                    $db->where('status', 'Received');
                    $recordData = $db->getOne('mlm_crypto_PG', 'id, received_tx_id, status');
                    if(!$recordData){
                        $db->where('wallet_address', $walletAddress);
                        $db->where('received_tx_id', $receivedTxID);
                        $db->where("tx_ref_id", $referenceID);
                        $db->where('status', array('Success','Processing'), 'IN');
                        $tempRecordData = $db->getOne('mlm_crypto_PG', 'id, received_tx_id, status');
                        if($tempRecordData){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid", 'data' => $tempRecordData);
                        }

                        $db->where('wallet_address', $walletAddress);
                        $db->where('status', 'Pending');
                        $pendingRecordData = $db->getOne('mlm_crypto_PG', 'id, received_tx_id, status');
                        if($pendingRecordData){
                            $updateData = array(
                                "call_back_amount" => $callBackAmount,
                                // "call_back_at"     => $dateTime,
                                "status"           => "Processing",
                                "received_tx_id"   => $receivedTxID,
                                "tx_ref_id"         => $referenceID,
                                "transaction_hash"  => $transactionHash,
                            );
                            $db->where('id', $pendingRecordData['id']);
                            $db->update('mlm_crypto_PG', $updateData);

                        } else{
                            $db->where('wallet_address', $walletAddress);
                            $addressData = $db->getOne('mlm_crypto_PG', 'client_id, coin_type, pay_credit_type');
                            self::insertCryptoPG($addressData['client_id'], $walletAddress, '0', $convert['sysName'], $addressData['pay_credit_type'], 'Processing', '', '', $dateTime, $transactionHash, $receivedTxID, $referenceID, $callBackAmount, $dateTime);
                        }
                    } else {
                        $updateData = array(
                            "transaction_hash"  => $transactionHash,
                            "call_back_amount" => $callBackAmount,
                            // "call_back_at"     => $dateTime,
                            "status"           => "Processing",
                        );
                        $db->where('id', $recordData['id']);
                        $db->update('mlm_crypto_PG', $updateData);
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Updated Pending Status.", 'data' => '');
                    }
                    break;

                case 'success':
                    $db->where('wallet_address', $walletAddress);
                    $db->where("tx_ref_id", $referenceID);
                    $db->where("received_tx_id", $receivedTxID);
                    $db->where("status", "Success");
                    if($db->has("mlm_crypto_PG")) {
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid", 'data' => $transactionHash);
                    }

                    $db->where("transaction_hash", $transactionHash);
                    $db->where("status", "Success");
                    if($db->has("mlm_crypto_PG")) {
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid", 'data' => $transactionHash);
                    }

                    $db->where('wallet_address', $walletAddress);
                    $db->where("transaction_hash", $transactionHash);
                    $db->where("received_tx_id", $receivedTxID);
                    $db->where("tx_ref_id", $referenceID);
                    $recordData = $db->getOne('mlm_crypto_PG');
                    if($recordData['status'] == 'Success'){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid", 'data' => $recordData);
                    }

                    if(empty($recordData)){
                        $db->where('wallet_address', $walletAddress);
                        $db->where("received_tx_id", $receivedTxID);
                        $db->where("tx_ref_id", $referenceID);
                        $updateRecordData = $db->getOne('mlm_crypto_PG');
                        if($updateRecordData){
                            $updateData = array(
                                "call_back_amount" => $callBackAmount,
                                // "call_back_at"     => $dateTime,
                                "updated_at"        => $dateTime,
                                "status"           => "Success",
                                "transaction_hash"  => $transactionHash,
                            );
                            $db->where('id', $updateRecordData['id']);
                            $db->update('mlm_crypto_PG', $updateData);
                            $recordID = $updateRecordData['id'];
                        } else {
                            $db->where('wallet_address', $walletAddress);
                            $db->where('status', 'Pending');
                            $pendingRecordData = $db->getOne('mlm_crypto_PG', 'id, received_tx_id, status');
                            if($pendingRecordData){
                                $updateData = array(
                                    "call_back_amount" => $callBackAmount,
                                    // "call_back_at"     => $dateTime,
                                    "updated_at"        => $dateTime,
                                    "status"           => "Success",
                                    "received_tx_id"   => $receivedTxID,
                                    "tx_ref_id"         => $referenceID,
                                    "transaction_hash"  => $transactionHash,
                                );
                                $db->where('id', $pendingRecordData['id']);
                                $db->update('mlm_crypto_PG', $updateData);
                                $recordID = $pendingRecordData['id'];
                            } else{
                                $db->where('wallet_address', $walletAddress);
                                $addressData = $db->getOne('mlm_crypto_PG', 'client_id, coin_type, pay_credit_type');
                                $recordID = self::insertCryptoPG($addressData['client_id'], $walletAddress, '0', $convert['sysName'], $addressData['pay_credit_type'], 'Success', '', '', $dateTime, $transactionHash, $receivedTxID, $referenceID, $callBackAmount, $dateTime, $dateTime);
                            }
                        }

                        $creditPayment = 1;
                        $creditRecordID = $recordID;
                    } else {
                        $updateData = array(
                            // "call_back_at"     => $dateTime,
                            "updated_at"  => $dateTime,
                            "status"      => "Success",
                        );
                        $db->where('id', $recordData['id']);
                        $db->update('mlm_crypto_PG', $updateData);
                        $creditPayment = 1;
                        $creditRecordID = $recordData['id'];
                    }

                    break;

                case 'failed':
                    $db->where('wallet_address', $walletAddress);
                    $db->where("tx_ref_id", $referenceID);
                    $db->where("received_tx_id", $receivedTxID);
                    if(!$db->has("mlm_crypto_PG")) {
                        $notFoundData = array(
                            "wallet_address" => $walletAddress,
                            "tx_ref_id"      => $referenceID,
                            "received_tx_id" => $receivedTxID,
                        );
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Record Not Found", 'data' => $notFoundData);
                    }

                    $updateData = array(
                        "status"  => "Failed",
                    );

                    $db->where('wallet_address', $walletAddress);
                    $db->where("tx_ref_id", $referenceID);
                    $db->where("received_tx_id", $receivedTxID);
                    $db->update('mlm_crypto_PG', $updateData);

                    break;

                default:
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Return Status", 'data' => '');
                    break;
            }

            if($creditPayment == 1 && $creditRecordID){
                $db->where('name', 'minFundInAmount');
                $minFundIn = $db->getOne('system_settings', 'value');


                /* Coin Live Rate */
                // $sq = $db->subQuery();
                // $sq->where('type', $convert['coinRatePrefix']);
                // $sq->getOne('mlm_coin_rate', 'id');
                // $db->where('id', $sq);
                // $coinRate = $db->getValue('mlm_coin_rate', 'rate');
                $coinRate = 1;

                $adminCharge = self::calaulateFundInAdminCharge($convert['sysName'], $callBackAmount);
                $receivableAmount = $callBackAmount - $adminCharge;


                $convertedAmount = $receivableAmount * $coinRate;

                $db->where('wallet_address', $walletAddress);
                $db->where('id', $creditRecordID);
                $addressRecord = $db->getOne('mlm_crypto_PG', 'client_id, pay_credit_type, belong');
                $clientID = $addressRecord['client_id'];
                $payCreditType = $addressRecord['pay_credit_type'];
                $addressRecordBelong = $addressRecord['belong'];
                if(!$clientID || !$payCreditType)
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Address Data Not Found.", 'data' => '');

                if($addressRecordBelong != '0')
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Already Paid. Updated Status", 'data' => $addressRecord);

                if($convertedAmount < $minFundIn['value']){
                    $updateReceivedData = array(
                        'system_rate' => $coinRate,
                        'market_rate' => $marketRate,
                        'receivable_amount' => $receivableAmount,
                        'admin_charge' => $adminCharge,
                        'status' => 'Failed',
                        'raw_data' => $originalData
                    );
                    $db->where('wallet_address', $walletAddress);
                    $db->where('id', $creditRecordID);
                    $db->update('mlm_crypto_PG', $updateReceivedData);

                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Fund In Amount Less Than Min Amount", 'data' => '');
                }

                $belong = $db->getNewID();
                //Update Receiveable Amount and Rate
                $updateReceivedData = array(
                    'system_rate' => $coinRate,
                    'market_rate' => $marketRate,
                    'receivable_amount' => $receivableAmount,
                    'belong' => $belong,
                    'admin_charge' => $adminCharge,
                    'raw_data' => $originalData
                );
                $db->where('wallet_address', $walletAddress);
                $db->where('id', $creditRecordID);
                $db->update('mlm_crypto_PG', $updateReceivedData);

                // Get client_id
                if(!$clientID)
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "ClientID not found.", 'data' => '');

                $db->where('username', 'creditSales');
                $db->where('type', 'Internal');
                $internalID = $db->getValue("client", "id");

                Cash::$creatorID = $clientID;
                Cash::$creatorType = "Member";
                $fundInRes = Cash::insertTAccount($internalID, $clientID, $payCreditType, $convertedAmount, $convert['shortForm']." Fund In", $belong, "", $dateTime, $belong, $clientID);
                if(!$fundInRes){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Fund In failed', 'data' => '');
                }

                if($adminCharge > 0){
                    $convertedAdminCharge = ($adminCharge * $coinRate);
                    $adminChargeRes = Cash::insertTAccount($clientID, $internalID, $payCreditType, $convertedAdminCharge, "Fund In Admin Charge", $belong, "", $db->now(), $belong, $clientID);
                    if(!$adminChargeRes){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => 'Admin Charge failed', 'data' => '');
                    }
                }

                $invoiceResult = Invoice::insertFullInvoice($clientID, $receivableAmount, '', '', 'fundIn', $belong);

                //Change Sponsor bonus, Hide This sponsor bonus
                /*if($convertedAmount > 0){
                    $queueData['bonusValue'] = $convertedAmount;
                    $queueData['bonusTime'] = $dateTime;
                    $queueData['belongID'] = $belong;
                    $insertQueue = array(
                        "queue_type"    => "sponsorBonus",
                        "client_id"     => $clientID,
                        "data"          => json_encode($queueData),
                        "status"        => "Active",
                        "created_at"    => $dateTime,
                    );
                    $db->insert('queue',$insertQueue);
                }*/

                $db->where('id', $clientID);
                $clientEmail = $db->getValue('client','email');

                //send notification email to admin
                $adminEmail = Setting::$configArray["adminEmail"];

                foreach ($adminEmail as $email) {
                    $dataIn = array(
                        'clientID'      => $clientID,
                        'callBackAmount'=> $callBackAmount,
                        'cryptoType'    => $convert['shortForm'],
                        'callBackTime'  => $dateTime,
                        'defaultEmail'  => $email,
                        'clientEmail'   => $clientEmail,
                        'sendType'      => 'email',
                        'type'          => 'fundIn',
                    );

                    Otp::sendOTPCode($dataIn);
                }

            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Success Retrieve Call Back.", 'data' => '');
        }

        public function theNuxFundInCallBack1($params) {
            $db = MysqliDb::getInstance();

            $originalData = trim($params['originalData']);
            $walletAddress = trim($params['walletAddress']);
            $transactionHash = trim($params['transactionHash']);
            $receivedTxID = trim($params['receivedTxID']);
            $referenceID = trim($params['referenceID']);
            $callBackAmount = trim($params['receivedAmount']);
            $returnCurrency = trim($params['returnCurrency']);
            $returnStatus = strtolower(trim($params['status']));
            $entryKey = trim($params['entryKey']);
            $dateTime = date("Y-m-d H:i:s");
            $senderAddress = trim($params['senderAddress']);
            $transactionUrl = trim($params['transactionUrl']);

            $tag = trim($params['tag']);
            if($tag) $walletAddress = $walletAddress.":::ucl:::".$tag;

            $insert = array(
                "transaction_hash" => $db->escape($transactionHash),
                "received_tx_id" => $db->escape($receivedTxID),
                "ref_id" => $db->escape($referenceID),
                "wallet_address" => $db->escape($walletAddress),
                "status" => $db->escape($returnStatus),
                "created_at" => date("Y-m-d H:i:s")
            );
            $hashID = $db->insert('mlm_hash_log', $insert);

            $db->where("id", $hashID, "<");
            $db->where("received_tx_id", $receivedTxID);
            $db->where("ref_id", $referenceID);
            $db->where("status", $returnStatus);
            $db->where("transaction_hash", $transactionHash);
            $returnID = $db->getValue("mlm_hash_log", "id");
            if(!empty($returnID)){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Already paid.", 'data' => $transactionHash);
            }

            $convert =  self::getCryptoConverter($returnCurrency);

            $db->where("received_tx_id", $receivedTxID);
            $db->where("ref_id", $referenceID);
            $db->where("status", 'success');
            $db->where("transaction_hash", $transactionHash);
            if($db->has("mlm_crypto_PG")) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Transaction is already completed.", 'data' => $transactionHash);
            }

            $db->where('name','releaseFundInStatus');
            $releaseFundInStatus = $db->getValue('system_settings','value');

            $db->where('coin_type', $convert['sysName']);
            $payCreditType = $db->getValue('mlm_crypto_setting', 'value');

            $db->where('info',$walletAddress);
            $db->where('type','deposit');
            $db->where('status','Active');
            $senderID = $db->getValue('mlm_client_wallet_address','client_id');

            $creditPayment = $creditRecordID = 0;
            switch ($returnStatus) {
                case 'received':
                    $db->where("received_tx_id", $receivedTxID);
                    $db->where("ref_id", $referenceID);
                    $recordID = $db->getValue('mlm_crypto_PG','id');

                    if (!$recordID) {
                        $recordID = self::insertCryptoPG($senderID, $senderAddress, $walletAddress, '0', $convert['sysName'], $payCreditType, 'Received', $referenceID, '', $dateTime, '', $receivedTxID, $callBackAmount, $dateTime, $transactionUrl);

                        if ($releaseFundInStatus == 'received') {
                            $creditPayment = 1;
                            $creditRecordID = $recordID;
                        }
                    } else {
                        $updateData = array(
                            "call_back_amount" => $callBackAmount,
                            "receivable_amount" => $callBackAmount,
                            "call_back_at" => $dateTime,
                            "status" => "Received",
                            "received_tx_id" => $receivedTxID,
                            "transaction_hash" => $transactionHash,
                            "transaction_url" => $transactionUrl
                        );
                        $db->where('id', $recordID);
                        $db->update('mlm_crypto_PG', $updateData);
                    }
                    break;

                case 'pending':
                    $db->where("received_tx_id", $receivedTxID);
                    $db->where("ref_id", $referenceID);
                    $recordID = $db->getValue('mlm_crypto_PG','id');

                    if (!$recordID) {
                        $recordID = self::insertCryptoPG($senderID, $senderAddress, $walletAddress, '0', $convert['sysName'], $payCreditType, 'Pending', $referenceID, '', $dateTime, '', $receivedTxID, $callBackAmount, $dateTime, $transactionUrl);

                        if ($releaseFundInStatus == 'pending') {
                            $creditPayment = 1;
                            $creditRecordID = $recordID;
                        }
                    } else {
                        $updateData = array(
                            "call_back_amount" => $callBackAmount,
                            "receivable_amount" => $callBackAmount,
                            "call_back_at" => $dateTime,
                            "status" => "Pending",
                            "received_tx_id" => $receivedTxID,
                            "transaction_hash" => $transactionHash,
                            "transaction_url" => $transactionUrl
                        );
                        $db->where('id', $recordID);
                        $db->update('mlm_crypto_PG', $updateData);
                    }
                    break;
                    break;

                case 'success':
                    $db->where("received_tx_id", $receivedTxID);
                    $db->where("ref_id", $referenceID);
                    $recordID = $db->getValue('mlm_crypto_PG','id');

                    if (!$recordID) {
                        $recordID = self::insertCryptoPG($senderID, $senderAddress, $walletAddress, '0', $convert['sysName'], $payCreditType, 'Success', $referenceID, '', $dateTime, $transactionHash, $receivedTxID, $callBackAmount, $dateTime, $transactionUrl);
                    } else {
                        $updateData = array(
                            "call_back_amount" => $callBackAmount,
                            "receivable_amount" => $callBackAmount,
                            "call_back_at" => $dateTime,
                            "status" => "Success",
                            "received_tx_id" => $receivedTxID,
                            "transaction_hash" => $transactionHash,
                            "transaction_url" => $transactionUrl
                        );
                        $db->where('id', $recordID);
                        $db->update('mlm_crypto_PG', $updateData);
                    }

                    if ($releaseFundInStatus == 'success') {
                        $creditPayment = 1;
                        $creditRecordID = $recordID;
                    }
                    break;

                case 'failed':
                    $db->where("received_tx_id", $receivedTxID);
                    $db->where("ref_id", $referenceID);
                    $recordID = $db->getValue('mlm_crypto_PG', 'id');

                    if (!$recordID) {
                        $recordID = self::insertCryptoPG($senderID, $senderAddress, $walletAddress, '0', $convert['sysName'], $payCreditType, 'Failed', $referenceID, '', $dateTime, $transactionHash, $receivedTxID, $callBackAmount, $dateTime, $transactionUrl);
                    } else {
                        $updateData = array(
                            "call_back_amount" => $callBackAmount,
                            "receivable_amount" => $callBackAmount,
                            "call_back_at" => $dateTime,
                            "status" => "Failed",
                            "received_tx_id" => $receivedTxID,
                            "transaction_hash" => $transactionHash,
                            "transaction_url" => $transactionUrl
                        );
                        $db->where('id', $recordID);
                        $db->update('mlm_crypto_PG', $updateData);
                    }
                    break;

                default:
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Return Status", 'data' => '');
                    break;
            }

            if ($creditPayment && $creditRecordID) {
                $belongID = $db->getNewID();
                /* Coin Live Rate */
                // $sq = $db->subQuery();
                // $sq->where('type', $convert['coinRatePrefix']);
                // $sq->getOne('mlm_coin_rate', 'id');
                // $db->where('id', $sq);
                // $coinRate = $db->getValue('mlm_coin_rate', 'rate');
                $coinRate = 1;

                $db->where('name','minFundInAmount');
                $db->where('type',$returnCurrency);
                $minFundInAmount = $db->getValue('system_settings','value')?:1;

                if ($callBackAmount > $minFundInAmount) {
                    $updateData = array(
                        'remark' => 'E00913'
                    );
                    $db->where('id',$creditRecordID);
                    $db->update('mlm_crypto_PG',$updateData);

                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Fund in amount is less then minimum fund in amount.", 'data' => '');
                }

                $adminCharge = self::calaulateFundInAdminCharge($convert['sysName'], $callBackAmount);
                $receivableAmount = $callBackAmount - $adminCharge;

                $db->where('id', $creditRecordID);
                $fundInData = $db->getOne('mlm_crypto_PG', 'client_id, pay_credit_type');
                $clientID = $fundInData['client_id'];
                $payCreditType = $fundInData['pay_credit_type'];
                if(!$clientID)
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid client.", 'data' => '');

                if(!$payCreditType)
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid pay credit.", 'data' => '');

                // Update Receivable Amount and Rate
                $updateReceivedData = array(
                    'coin_rate' => $coinRate,
                    'receivable_amount' => $receivableAmount,
                    'belong_id' => $belongID,
                    'admin_charge' => $adminCharge,
                );
                $db->where('id', $creditRecordID);
                $db->update('mlm_crypto_PG', $updateReceivedData);

                $convertedAmount = $receivableAmount * $coinRate;

                $db->where('username', 'creditSales');
                $db->where('type', 'Internal');
                $internalID = $db->getValue("client", "id");

                Cash::$creatorID = $clientID;
                Cash::$creatorType = "Member";
                $fundInRes = Cash::insertTAccount($internalID, $clientID, $payCreditType, $convertedAmount, $convert['shortForm']." Fund In", $belongID, "", $dateTime, $belongID, $clientID);
                if(!$fundInRes){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Fund In failed', 'data' => '');
                }

                if($adminCharge > 0){
                    $convertedAdminCharge = ($adminCharge * $coinRate);
                    $adminChargeRes = Cash::insertTAccount($clientID, $internalID, $payCreditType, $convertedAdminCharge, "Fund In Admin Charge", $belongID, "", $db->now(), $belongID, $clientID);
                    if(!$adminChargeRes){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => 'Admin Charge failed', 'data' => '');
                    }
                }

                $invoiceResult = Invoice::insertFullInvoice($clientID, $receivableAmount, null, null, 'fundIn', $belongID);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Success Retrieve Call Back.", 'data' => '');
        }

        public function assignMemberWalletAddress($params, $clientID){
            $db             = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $topUpAmount = $params['topUpAmount'];
            $fundInCreditType = $params['fundInCreditType'];
            // $fundInType = $params['fundInType'];
            $tPassword = $params['tPassword'];

            if(empty($fundInCreditType)){
               return array('status' => "error", 'code' => 1, 'statusMsg' => 'Credit Not Found', 'data' => "");
            }

            if(empty($topUpAmount)){
               return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid fund in amount', 'data' => "");
            }

            if(empty($tPassword)){
               return array('status' => "error", 'code' => 1, 'statusMsg' => 'Transaction Password Not Found', 'data' => "");
            }

            switch ($fundInCreditType) {
                case 'tetherUSD':
                case 'tether':
                    $walletType = "tether";
                    break;

                case 'BTC':
                case 'bitcoin':
                    $walletType = "bitcoin";
                    break;

                case 'ETC':
                case 'ethereum':
                    $walletType = "ethereum";
                    break;

                default:
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Undefined Credit Type', 'data' => "");
                break;

            }

            $passwordEncryption  = Setting::getMemberPasswordEncryption();
            $db->where('id', $clientID);
            if ($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            } else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('transaction_password', $db->encrypt($tPassword));
            } else {
                // No encryption
                $db->where('transaction_password', $tPassword);
            }
            $result = $db->get('client');

            if (!empty($result)) {
                if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if (!password_verify($tPassword, $result[0]['transaction_password']))
                        $errorFieldArr[] = array(
                            'id' => 'tPasswordError',
                            'msg' => $translations["E00433"][$language] /* Invalid transaction password. */
                        );
                }
            } else {
                $errorFieldArr[] = array(
                    'id' => 'tPasswordError',
                    'msg' => $translations["E00433"][$language] /* Invalid transaction password. */
                );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // $db->where('client_id', $clientID);
            // $db->where('status', "Paired", "!=");
            // $db->where('credit_type', $theNuxCreditType);
            // $clientWalletAddress = $db->getOne('mlm_wallet_address', 'id, info');

            // if($clientWalletAddress){
            //     $data['walletAddress'] = $clientWalletAddress['info'];
            //     return array('status' => "ok", 'code' => 0, 'statusMsg' => "You already has one active wallet address", 'data' => $data);
            // }
            // if($fundInType == 'Premium'){
            //     $topUpAmount = $topUpAmount + ($topUpAmount * $adminCharge);
            // }

            $db->where('client_id', '0');
            $db->where('status', "Paired", "!=");
            $db->where('credit_type', $walletType);
            $db->orderBy('id', "ASC");
            $walletAddressRow = $db->getOne('mlm_wallet_address', "id, info");

            if(empty($walletAddressRow)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Wallet Address not found ', 'data' => "");
            }

            $walletAddressID = $walletAddressRow['id'];
            $walletAddress = $walletAddressRow['info'];

            $updateData = array(
                                    'client_id' => $clientID,
                                    'status' => "Paired",
                                    // 'type' => $fundInType,
                                    'paired_at' => date("Y-m-d H:i:s"),
                                );
            $db->where('id', $walletAddressID);
            $db->Update('mlm_wallet_address', $updateData);

            $insertParams["client_id"] = $clientID;
            $insertParams["walletAddress"] = $walletAddress;
            $insertParams['topUpAmount'] = $topUpAmount;
            $insertParams['walletType'] = $walletType;
            $fundInListID = self::insertFundInList($insertParams);

            if(!$fundInListID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'failed insert fund in list', 'data' => "");
            }

            $data['walletType'] = $walletType;
            $data['topUpAmount'] = $topUpAmount;
            $data['walletAddress'] = $walletAddress;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Assign wallet address successful", 'data' => $data);
        }

        public function insertCryptoPG($clientID, $walletAddress, $topUpAmount, $coinType, $payCreditType, $status, $referenceID, $transactionToken, $dateTime, $transactionHash, $receivedTxID, $refTxID, $callBackAmount, $callBackAt, $updatedAt) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTime       = $dateTime ? $dateTime : date("Y-m-d H:i:s") ;
            $coinType       = self::getCryptoConverter($coinType)['sysName'];
            $insertData = array(
                "client_id" => $clientID,
                "coin_type" => $coinType,
                "pay_credit_type" => $payCreditType,
                "top_up_amount" => $topUpAmount,
                "status" => $status,
                "created_at" => $dateTime,
                "wallet_address" => $walletAddress,
                "ref_id" => $referenceID,
                "tx_token" => $transactionToken,
                "transaction_hash" => $transactionHash,
                "received_tx_id" => $receivedTxID,
                "tx_ref_id" => $refTxID,
                "call_back_amount" => $callBackAmount,
                "call_back_at" => $callBackAt,
                "updated_at" => $updatedAt
            );

            $id = $db->insert("mlm_crypto_PG", $insertData);

            if($id){
                return $id;
            } else {
                return false;
            }
        }

        public function insertCryptoPG1($clientID, $senderAddress, $walletAddress, $topUpAmount, $coinType, $payCreditType, $status, $referenceID, $transactionToken, $dateTime, $transactionHash, $receivedTxID, $callBackAmount, $callBackAt, $transactionUrl) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTime       = $dateTime ? $dateTime : date("Y-m-d H:i:s") ;
            $coinType       = self::getCryptoConverter($coinType)['sysName'];
            $insertData = array(
                "client_id" => $clientID,
                "client_wallet_address" => $senderAddress,
                "coin_type" => $coinType,
                "pay_credit_type" => $payCreditType,
                "top_up_amount" => $topUpAmount,
                "status" => $status,
                "created_at" => $dateTime,
                "wallet_address" => $walletAddress,
                "ref_id" => $referenceID,
                "tx_token" => $transactionToken,
                "transaction_hash" => $transactionHash,
                "transaction_url" => $transactionUrl,
                "received_tx_id" => $receivedTxID,
                "call_back_amount" => $callBackAmount,
                "call_back_at" => $callBackAt,
            );

            $id = $db->insert("mlm_crypto_PG", $insertData);

            if($id){
                return $id;
            } else {
                return false;
            }
        }

        public function getFundInListing($params, $site, $userID){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $db->where('id',"$userID");
            if ($site == 'Member') {
                $isValidUser = $db->has('client');
            } else {
                $isValidUser = $db->has('admin');
            }

            if (!$isValidUser)
                return array('status'=>'error','code'=>'0','statusMsg'=>'Invalid user.','data'=>array('field'=>'user'));

            $searchData   = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            $column = array(
                    "client_id",
                    "(SELECT username FROM client where id = client_id) AS username",
                    "(SELECT member_id FROM client where id = client_id) AS member_id",
                    "coin_type",
                    "pay_credit_type",
                    "(SELECT translation_code FROM credit WHERE type = pay_credit_type GROUP BY type) AS payCreditTC",
                    "FORMAT(top_up_amount, 8) AS top_up_amount",
                    "FORMAT(call_back_amount, 8) AS call_back_amount",
                    "FORMAT(receivable_amount, 8) AS receivable_amount",
                    "system_rate as coin_rate",
                    "FORMAT((receivable_amount * system_rate), 8) as converted_receivable_amount",
                    "status",
                    "created_at",
                    "call_back_at",
                    "wallet_address",
                    "transaction_hash",
                );

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $usernameSearchType = $params["usernameSearchType"];

			$adminLeaderAry = Setting::getAdminLeaderAry();
            $cpDb = $db->copy();
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

                            $db->where('id', $downlines, "IN");

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

                        case 'creditType':
                            $db->where('crypto_type', $dataValue);
                            break;

                        case 'walletAddress':
                            $db->where('wallet_address', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'approvedDate':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            switch ($dataType) {
                                case 'singleDate':
                                    $db->where('call_back_at', date('Y-m-d 00:00:00', $dataValue), '>=');
                                    $db->where('call_back_at', date('Y-m-d 23:59:59', ($dataValue+86400) ),'<');//86400=24hours
                                    break;

                                case 'dateRange':
                                    if(strlen($dateFrom) > 0) {
                                        if($dateFrom < 0)
                                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    }
                                    if(strlen($dateTo) > 0) {
                                        if($dateTo < 0)
                                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"$dateTo");

                                        if($dateTo < $dateFrom)
                                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                    }

                                    if(strlen($dateFrom) > 0) {
                                        $db->where('call_back_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                                    }
                                    if(strlen($dateTo) > 0) {
                                        $db->where('call_back_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                                    }
                                    break;


                                default:
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"Invalid dataType");
                                    break;
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'searchDate':
                            $columnName = 'created_at';
                            $dateFrom   = trim($v['tsFrom']);
                            $dateTo     = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
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

            if($adminLeaderAry){
            	$db->where('client_id', $adminLeaderAry, 'IN');
            }

            $userSite = $db->userType;
            if($userSite == "Member") {
                $clientID = $userID;
                $db->where('client_id', $clientID);
            } else {
                $copyGrandTotalDb = $db->copy();
            }

            $copyDb = $db->copy();
            $db->orderBy('created_at', 'DESC');
            $fundInData = $db->get('mlm_crypto_PG', $limit, $column);

            if(empty($fundInData)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $totalPerPage = 0;
            foreach ($fundInData as $fundIn) {
                $fundIn['statusDisplay'] = General::getTranslationByName($fundIn['status']);
                $fundIn['payCreditDisplay'] = $translations[$fundIn['payCreditTC']][$language];
                $fundIn['created_at'] = date('d/m/Y h:i A', strtotime($fundIn['created_at']));
                if($fundIn['call_back_at'] != '0000-00-00 00:00:00'){
                    $fundIn['call_back_at'] = date('d/m/Y h:i A', strtotime($fundIn['call_back_at']));
                } else {
                    $fundIn['call_back_at'] = '-';
                }

                $fundIn['transaction_hash'] = $fundIn['transaction_hash'] ? $fundIn['transaction_hash'] : "-";
                $fundIn['username'] = $fundIn['username'] ?: '-';
                $fundIn['member_id'] = $fundIn['member_id'] ?: '-';

                // get mainLeaderUSername
                $subParams['clientID'] = $fundIn['client_id'];
                $mainLeaderUsername = Tree::getMainLeaderUsername($subParams);
                $fundIn['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                $tblCallBack += ($fundIn['call_back_amount'] ? $fundIn['call_back_amount'] : '0');
                $tblCoinRate += ($fundIn['coin_rate'] ? $fundIn['coin_rate'] : '0');
                $tblRecAmt += ($fundIn['converted_receivable_amount'] ? $fundIn['converted_receivable_amount'] : '0');

                $fundInList[] = $fundIn;
            }

            $tblTotalList['call_back'] = Setting::setDecimal($tblCallBack);
            $tblTotalList['coin_rate'] = Setting::setDecimal($tblCoinRate);
            $tblTotalList['converted_receivable_amount'] = Setting::setDecimal($tblRecAmt);

            $grandTotalData = $copyDb->get('mlm_crypto_PG', null, $column);

            foreach ($grandTotalData as $grandTotal) {
                $grandCallBack += ($grandTotal['call_back_amount'] ? $grandTotal['call_back_amount'] : '0');
                $grandCoinRate += ($grandTotal['coin_rate'] ? $grandTotal['coin_rate'] : '0');
                $grandlRecAmt += ($grandTotal['converted_receivable_amount'] ? $grandTotal['converted_receivable_amount'] : '0');
            }

            $grandTotalList['call_back'] = Setting::setDecimal($grandCallBack);
            $grandTotalList['coin_rate'] = Setting::setDecimal($grandCoinRate);
            $grandTotalList['converted_receivable_amount'] = Setting::setDecimal($grandlRecAmt);

            $totalRecord = count($grandTotalData);

            if($userSite == "Admin"){
                $totalFundIn = $copyGrandTotalDb->getValue('mlm_crypto_PG', "SUM(receivable_amount)");
                $data['grandTotalFundIn'] = $totalFundIn;
            }

            $data['fundInList'] = $fundInList;
            $data['grandTotalList'] = $grandTotalList;
            $data['tblTotalList'] = $tblTotalList;
            $data['totalFundInPerPage'] = $totalPerPage;
            $data['pageNumber']                 = $pageNumber;
            $data['totalRecord']                = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']              = 1;
                $data['numRecord']              = $totalRecord;
            }else{
                $data['totalPage']              = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];
            }
            $data['seeAll']                     = $seeAll;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getTheNuxTransactionToken($params, $clientID) {
            include('config.php');
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $amount         = $params['amount'] ? $params['amount'] : 0;
            $coinType       = $params['coin_type'];
            $payCreditType  = $params['pay_credit_type'];
            $referenceID    = $db->getNewID();
            $dateTime       = date("Y-m-d H:i:s");

            $convert =  self::getCryptoConverter($coinType);
            $currency = $convert['theNuxPrefix'];

            if(!$payCreditType){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00827"][$language], 'data' => "");
            }

            if(!$coinType){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00829"][$language], 'data' => "");
            }

            $url = $config['theNuxRequestUrl'];
            $url .="?business_id=".$config['theNuxWalletBusinessID'];
            $url .="&api_key=".$config['theNuxWalletApiKey'];
            $url .="&amount=".$amount;
            $url .="&currency=".$currency;
            $url .="&reference_id=".$referenceID;
            $url .="&redirect_url=".$config['theNuxRedirectUrl'];
            $url .="&address=".$address;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Timeout in seconds
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $res = curl_exec($ch);
            curl_close($ch);
            /* 
                Sample Output
            {"code":1,"message":"SUCCESS","message_d":"Success.","data":{"transaction_token":"gEW3ClFZQSUIGxOb","reference_id":"1001153","address":"0xdb3673133b6e81f15e5e95db2a1f02b756723163"}}

            */

            $output = json_decode($res, true);

            if(strtolower($output['message']) != 'success'){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00830"][$language], 'data' => array($url, $res));   
            }

            $outputData = $output['data'];
            $transactionToken = $outputData['transaction_token'];
            $walletAddress = $outputData['address'];

            $recordID = self::insertCryptoPG($clientID, $walletAddress, $amount, $coinType, $payCreditType, 'Pending', $referenceID, $transactionToken, $dateTime, '', '');

            if(!$recordID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00831"][$language], 'data' => '');
            }

            $db->where('name', 'minFundInAmount');
            $minFundIn = $db->getOne('system_settings', 'value');

            $data['minFundIn'] = $minFundIn['value'];
            $data['record_id'] = $recordID;
            $data['qrPayURL'] = $config['theNuxQRPayUrl'];
            $data['theNuxCoinPrefix'] = $currency;
            $data['walletAddress'] = $walletAddress;
            $data['transaction_token'] = $transactionToken;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getCryptoConverter($coinType) {
            switch (strtolower($coinType)) {
                case 'btc':
                case 'bitcoin':
                    $data['name'] = 'Bitcoin';
                    $data['shortForm'] = 'BTC';
                    $data['sysName'] = 'BTC';
                    $data['theNuxPrefix'] = 'bitcoin';
                    $data['coinRatePrefix'] = 'BTC';
                    break;

                case 'usdt':
                case 'tether':
                case 'tetherusd':
                case 'usdtp2p':
                    $data['name'] = 'TetherUSD';
                    $data['shortForm'] = 'USDT';
                    $data['sysName'] = 'USDT';
                    $data['theNuxPrefix'] = 'tetherUSD';
                    $data['coinRatePrefix'] = 'USDT';
                    break;

                case 'eth':
                case 'ethereum':
                    $data['name'] = 'Ethereum';
                    $data['shortForm'] = 'ETH';
                    $data['sysName'] = 'ETH';
                    $data['theNuxPrefix'] = 'ethereum';
                    $data['coinRatePrefix'] = 'ETH';
                    break;

                case 'tronusdt':
                    $data['name'] = 'TronUSDT';
                    $data['shortForm'] = 'tronUSDT';
                    $data['sysName'] = 'tronUSDT';
                    $data['theNuxPrefix'] = 'tronUSDT';
                    $data['coinRatePrefix'] = 'tronUSDT';
                    break;

                default:
                    return false;
                    break;
            }

            return $data;
        }

        public function insertTheNuxFundInTransactionID($params) {
            $db             = MysqliDb::getInstance();

            $status             = $params["status"];
            $transactionToken   = $params['transaction_token'];
            $transactionID      = $params["transaction_id"];

            if($status != 'success'){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Transaction Failed', 'data' => "");
            }

            $db->where("tx_token", $transactionToken);
            $db->update("mlm_crypto_PG", array('tx_id' => $transactionID));

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function calaulateFundInAdminCharge($coinType, $amount){
            $db = MysqliDb::getInstance();

            $convert =  self::getCryptoConverter($coinType);

            $db->where('coin_type', $convert['sysName']);
            $db->where('name', 'adminChargePercentage');
            $adminChargePercentage = $db->getValue('mlm_crypto_setting', 'value');

            if($adminChargePercentage){
                $totalAmountPercentage = $$adminChargePercentage + 100;
                $chargeAmount = $amount * ($adminChargePercentage / $totalAmountPercentage) / 100;
            } else {
                 $chargeAmount = 0;
            }

            return $chargeAmount;
        }

        public function getFundInStatus($params){
            $db           = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $id = $params['id'];
            if(empty($id)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid Record', 'data' => '');
            }

            $db->where('id', $id);
            $db->where('client_id', $db->userID);
            $fundInStatus = $db->getValue('mlm_crypto_PG', 'status');

            if(empty($fundInStatus)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Record Not Found', 'data' => '');
            }

            $data['fundInStatus'] = $fundInStatus;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getFundOutWalletBalance($params){
            $db = MysqliDb::getInstance();
            $coinType = trim($params['creditType']);

            $businessID = Setting::$configArray['theNuxWalletBusinessID'];
            $apiKey = Setting::$configArray['theNuxWalletApiKey'];
            $providerDomain = Setting::$configArray['nuxPayAPIDomain'];

            // $db->where('name', 'fundOutCreditType');
            // $acceptCoinType = $db->getValue('mlm_crypto_setting', 'coin_type', NULL);

            $convert =  self::getCryptoConverter($coinType);
            // if(!in_array($convert['shortForm'], $acceptCoinType)){
            //     return array('status' => "ok", 'code' => 0, 'statusMsg' => "Invalid Coin Type", 'data' => "");
            // }
            $walletType = $convert['theNuxPrefix'];

            $postParams = array(
                "business_id" => $businessID,
                "api_key" => $apiKey,
                "wallet_type" => $walletType
            );

            $postParams = json_encode($postParams);
            $url = $providerDomain . '/crypto/wallet/balance/get';

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
            $balanceResponse = json_decode($jsonResponse, 1);

            if($balanceResponse['code'] == 1){
                $balanceData = $balanceResponse['data'];
            }

            return $balanceData;
        }

        public function theNuxFundOutCallback($params){
            $db = MysqliDb::getInstance();

            $referenceID = $params['referenceID'];
            $transactionHash = $params['transactionHash'];
            $transactionDetails = $params['transactionDetails'];
            $status = $params['status'];

            if(!$transactionHash){
                foreach ($transactionDetails as $transaction) {
                    $address = $transaction['receiverAddress'];
                    $token = $transaction['transactionToken'];
                    $transactionHash = $transaction['transactionHash'];
                    $transactionAmount = $transaction['amount'];

                    $db->where('wallet_address', $address);
                    $db->where('txn_token', $token);
                    $db->where('reference_id', $referenceID);
                    $db->where('status', 'failed', '!=');
                    $cryptoQueue = $db->getOne('mlm_crypto_queue', 'id, status, withdraw_id');
                    if(!$cryptoQueue){
                        $db->where('wallet_address', $address);
                        $db->where('reference_id', $referenceID);
                        $db->where('amount', $transactionAmount);
                        $db->where('status', 'suspened');
                        $cryptoQueue = $db->getOne('mlm_crypto_queue', 'id, status, withdraw_id');                        
                    }

                    $updateData['status'] = $status;
                    $updateData['txn_hash'] = $transactionHash;
                    $updateData['call_back_msg'] = json_encode($params);
                    $updateData['call_back_at'] = date('Y-m-d H:i:s');

                    $db->where('id', $cryptoQueue['id']);
                    $db->update('mlm_crypto_queue', $updateData);

                    switch ($status) {
                        case 'confirmed':
                        case 'success':
                            $withdrawalStatus = 'Success';
                            break;

                        case 'failed':
                            $withdrawalStatus = 'Failed';
                            break;

                        default:

                            break;
                    }

                    $db->where('id', $cryptoQueue['withdraw_id']);
                    $db->update('mlm_withdrawal', array('status' => $withdrawalStatus));

                }
            } else {
                foreach ($transactionDetails as $transaction) {
                    unset($withdrawalStatus);
                    $address = $transaction['receiverAddress'];
                    $transactionAmount = $transaction['amount'];

                    $db->where('wallet_address', $address);
                    $db->where('reference_id', $referenceID);
                    $db->where('amount', $transactionAmount);
                    $db->where('status', array('confirmed', 'confirmedF'), 'NOT IN');
                    $cryptoQueue = $db->getOne('mlm_crypto_queue', 'id, status, withdraw_id');
                    if($cryptoQueue['status'] == 'failed'){
                        $status = 'confirmedF';
                    }

                    $updateData['status'] = $status;
                    $updateData['txn_hash'] = $transactionHash;
                    $updateData['call_back_msg'] = json_encode($params);
                    $updateData['call_back_at'] = date('Y-m-d H:i:s');                        
                    $db->where('id', $cryptoQueue['id']);
                    $db->update('mlm_crypto_queue', $updateData);

                    switch ($status) {
                        case 'confirmedF':
                        case 'confirmed':
                            $withdrawalStatus = 'Success';
                            break;

                        case 'failed':
                            $withdrawalStatus = 'Failed';
                            break;

                        default:
                            break;
                    }

                    $db->where('id', $cryptoQueue['withdraw_id']);
                    $db->update('mlm_withdrawal', array('status' => $withdrawalStatus));
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => '');
        }

        public function getFundInData($params,$userID = 0) {
            $db = MysqliDb::getInstance();

            $cryptoType = trim($params['cryptoType']);

            $db->where('id',$userID);
            $isValidUser = $db->has('client');
            if (!$isValidUser)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid user.','data'=>array('field'=>'user'));

            if (!$cryptoType)
                return array('status'=>'error','code'=>'1','statusMsg'=>'Invalid crypto type.','data'=>array('field'=>'cryptoType'));
            
            $db->where('name','fundInWalletAddress');
            $db->where('type',$cryptoType);
            $walletAddress = $db->getValue('system_settings','value');

            $data = array(
                'walletAddress' => $walletAddress
            );

            return array('status'=>'ok','code'=>'0','statusMsg'=>'','data'=>$data);
        }
	}
?>
