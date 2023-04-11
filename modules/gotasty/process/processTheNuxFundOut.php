<?php 

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');
    include_once($currentPath.'/../include/config.php');

    log::setupLogPath(__DIR__, __FILE__);

    // $validCreditType = array('usdtWallet');
    // $validWalletType = array('tetherusd');

    $db->where('name', 'fundOutCreditType');
    $validCoinType = $db->getValue('mlm_crypto_setting', 'coin_type', NULL);
    // check process is it running.
    $db->where("name",'processAutoWithdrawalPayout');
    $statusAutoWithdrawalPayout = $db->getValue("system_settings", "value");
    if($statusAutoWithdrawalPayout=='completed'){
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "processing"));
    }
    else{
        echo date('Y-m-d H:i:s')." Auto Withdrawal Payout is running.\nStop now.\n";
        exit;
    }

    $currntTS = time();
    $currentMinute = date("i");
    if($currentMinute % 10 == 0){
        $halfHourAgo = date("Y-m-d, H:i:s", ($currntTS  - 1800));
        $db->where('processed', '1');
        $db->where('created_at', $halfHourAgo, ">=");
        $db->where('status', 'pending');

        $delayedQueue = $db->get("mlm_crypto_queue", NULL, "id, (SELECT username FROM client WHERE client.id = (SELECT client_id FROM mlm_withdrawal WHERE id = withdraw_id)) AS client_username, (SELECT serial_number FROM mlm_withdrawal WHERE id = withdraw_id) AS serial_number, credit_type, amount, wallet_address, withdraw_id, txn_hash, processed_at");

        foreach ($delayedQueue as $delay) { 
            $delayCoinType = CryptoPG::getCryptoConverter($delay['credit_type']);
            $cType = $delayCoinType['shortForm'];
            
            $performanceParams["eventSection"] = "Auto Fund Out (30min)";
            $performanceParams["creditType"] = $cType;
            $performanceParams["amount"] = $delay['amount'];
            $performanceParams["walletAddress"] = $delay['wallet_address'];
            $performanceParams["transactionHash"] = $delay['txn_hash'];
            $performanceParams["toUser"] = $delay['client_username'];
            $performanceParams["withdrawal_id"] = $delay['withdraw_id'];
            $performanceParams["serial_number"] = $delay['serial_number'];
            $performanceParams["createdOn"] = $delay['processed_at'];

            Message::recordPerformance($performanceParams);
        }
    }

    // get queue to ready process.
    $db->where("queue_type",'cryptoWithdrawalPayout');
    $db->where("processed",'0');
    $db->orderBy("credit_type",'ASC');
    $db->orderBy("created_at",'ASC');
    $res = $db->get("mlm_crypto_queue", NULL, "id, credit_type,(SELECT username FROM client WHERE client.id = (SELECT client_id FROM mlm_withdrawal WHERE id = withdraw_id)) AS client_username, (SELECT serial_number FROM mlm_withdrawal WHERE id = withdraw_id) AS serial_number, amount, wallet_address, withdraw_id, belong, batch_id, status");

    if(!$res) {
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "completed"));
        exit("\n".date('Y-m-d H:i:s')." No queue.\n");
    }
    foreach ($res as $key => $row) {
        // if($row['credit_type'] == 'bitcoin'){
        //     $btcData[$row['id']] = (array)json_decode($row['data']);
        // }else{
        //     $data[$row['id']] = (array)json_decode($row['data']);
        // }
        $updateQueue[$row['id']]['processed'] = "1";
        // $noMoreLimit[$row['credit_type']] = 0;
    }
    //  get TheNux credentials
    $addressBusinessID = $config['theNuxWalletBusinessID'];
    $apiKey = $config['theNuxWalletApiKey'];
    $providerDomain = $config['theNuxWalletFundOutUrl'];

    echo $addressBusinessID."\n";
    echo $apiKey."\n";
    echo $providerDomain."\n";

    if(empty($apiKey) || empty($addressBusinessID) || empty($providerDomain)){
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "completed"));
        Log::write(date("Y-m-d H:i:s") . " Provider domian, business ID or api key is not set.\n");
        exit("\n".date('Y-m-d H:i:s')." Provider business ID or api key is not set.\n");
    }
    // $db->where("name", "walletType");
    // $creditWalletTypeArr = $db->map("type")->ArrayBuilder()->get("credit_setting", null, "credit_id, name, value, type");


    $db->where("username",'payout');
    $db->where("type",'Internal');
    $creditSalesID = $db->getValue("client", 'id');

    //  get current wallet balance
    $creditType = 'ETH';
    $getBalanceParams = array(
        'creditType' => $creditType
    );

    $walletBalanceData = cryptoPG::getFundOutWalletBalance($getBalanceParams);
    $walletBalance = $walletBalanceData['balance'];

    //  get balance threshold
    /*$usdtThreshold = $setting->systemSetting['usdtWalletLowThreshold'];
    
    if($walletBalance < $usdtThreshold){
        $content = "Wallet: " . $walletBalanceData['unit'] ."\n";
        $content .= "Remaining Balance: ".$walletBalance ."\n\n";
        $content .= "Time: ".date('Y-m-d H:i:s');
        $message->createMessageOut("10008", $content, "Low Balance");
    }*/

    foreach ($res as $k => $value) {
        // unset($res);
        $mlmQueueID = $value["id"];
        $withdrawAmount = $value["amount"];
        $withdrawAddress = $value["wallet_address"];
        $withdrawCreditType = $value["credit_type"];
        $queueBatchId = $value["batch_id"];
        $queueBelong = $value["belong"];
        $queueStatus = $value["status"];
        // $walletTypeData = $creditWalletTypeArr[$withdrawCreditType];
        $walletType = $value["credit_type"];
        $username = $value["client_username"];
        $withdrawID = $value["withdraw_id"];
        $serialNumber = $value["serial_number"];

        if($queueStatus == 'confirmed'){
            continue;
        }

        echo "\n".date('Y-m-d H:i:s')." Processing mlmQueue.ID : '$mlmQueueID'.\n"; 

        if(!$withdrawCreditType){
            echo date('Y-m-d H:i:s')." Credit Type not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Credit Type not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$withdrawAmount){
            echo date('Y-m-d H:i:s')." Amount not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Amount not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$withdrawAddress){
            echo date('Y-m-d H:i:s')." Wallet Address not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Wallet Address not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!is_numeric($withdrawAmount) ){
            echo date('Y-m-d H:i:s')." Amount not valid.'".$withdrawAmount."'\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Amount not valid. ".$withdrawAmount;
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$value['withdraw_id']){
            echo date('Y-m-d H:i:s')." Withdraw_id not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Withdraw_id not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if($noMoreLimit[$value['credit_type']]){           
            echo date('Y-m-d H:i:s')." ".$value['credit_type']." Insufficient balance.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Insufficient balance.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }

        $withdrawCoinTypeAry = CryptoPG::getCryptoConverter($withdrawCreditType);
        $withdrawCoinType = $withdrawCoinTypeAry['shortForm'];

        if(in_array($withdrawCoinType, $validCoinType)){
            $fullCreditType = $withdrawCoinTypeAry['theNuxPrefix'];
            $insertTACreditType = $withdrawCoinTypeAry['shortForm'];

            // send to theNux - start
            echo date('Y-m-d H:i:s')." Sending '".$fullCreditType."' : '".$withdrawAmount."' to '".$withdrawAddress."'.\n"; 

            $url = $providerDomain.'/crypto/external/transfer';

            $postParams = array(
                "business_id" => $addressBusinessID,
                "api_key" => $apiKey,
                "wallet_type" => $fullCreditType,
                "reference_id" => $mlmQueueID,
                "recipient_address" => $withdrawAddress,
                "amount" => $withdrawAmount
            );


            $postParams = json_encode($postParams);

            $updateQueue[$mlmQueueID]['data_in'] = $postParams;
            $updateQueue[$mlmQueueID]['status'] = "Send";
            $updateQueue[$mlmQueueID]['url'] = $url;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams); // $response->setBody()
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Timeout in seconds
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);

            $walletData = curl_exec($ch);
            echo $walletData."\n";
            // send to theNux - end

            //debug mode
            // success case
            // $walletData = '{"code":1,"message":"SUCCESS","message_d":"Success","data":{"referenceID":"983","transactionToken":"28b523c855ea193d56529984ba060fbc","transactionHash":"0x21d5c34a6cc1f43c271c881e00bc686c2584d1c03e7febd1a4a33ce53d733eea","transactionDetails":[{"receiverAddress":"0xfaf4d58e4bf24cca4239e5b27c1bb5c4a193c98e","amount":"100","unit":"ETH","conversionRate":"1000000","exchangeRate":{"USD":"1.00334018"}}],"amountDetails":{"amount":"100","unit":"ETH","conversionRate":"1000000","exchangeRate":{"USD":"1.00334018"}},"feeDetails":{"amount":"90000000000000","unit":"ETH","conversionRate":"1000000000000000000","exchangeRate":{"USD":"133.48961195"}},"confirmation":0,"status":"pending","time":"2019-12-24 00:10:06","successTime":""}}';
            // fail case
            // $walletData = '{"code":0,"message":"FAILED","message_d":"Insufficient balance","data":{"errorCode":"E10000","errorMessage":"Insufficient balance","referenceID":"2815"}}';
            // $walletData = '{"code":0,"status":"ERROR","statusMsg":"statusMsg error."}';
            //debug mode

            // print_r($walletData);
            $updateQueue[$mlmQueueID]['data_out'] = print_r($walletData,1); // record raw data first.

            $walletData = json_decode($walletData);

            if(strtolower($walletData->message) == 'failed' || strtolower($walletData->status) == 'error' ){
                $updateQueue[$mlmQueueID]['status'] = 'failed';
                $updateQueue[$mlmQueueID]['error_msg'] = (string)$walletData->message_d?:(string)$walletData->statusMsg;
                echo date('Y-m-d H:i:s')." status : '".($walletData->message?:$walletData->status)."', reason : '".($walletData->message_d?:$walletData->statusMsg)."'.\n"; 

                // if ($walletData->data->errorCode == 'E10000'){
                //     // Insufficient balance 
                //     $noMoreLimit[$value['credit_type']] = 1;
                // } 
                $withdrawalStatus = 'Failed';
            }

            // success send out.
            if( $updateQueue[$mlmQueueID]['status'] != 'failed' && $updateQueue[$mlmQueueID]['status'] != 'waiting'){
                $updateQueue[$mlmQueueID]['status'] = (string)$walletData->data->status;
                $referenceID = (string)$walletData->data->referenceID?:(string)$walletData->data->reference_id;
                $transactionToken = (string)$walletData->data->transactionToken?:(string)$walletData->data->transaction_token;
                $transaction_hash = (string)$walletData->data->transaction_hash?:(string)$walletData->data->transactionHash;

                $feeAmount = (string)$walletData->data->feeDetails->amount;
                $feeConversionRate = (string)$walletData->data->feeDetails->conversionRate;
                $feeAmount = bcdiv($feeAmount,$feeConversionRate,8);
                // $updateQueue[$mlmQueueID]['reference_id'] = $referenceID;
                $updateQueue[$mlmQueueID]['txn_token'] = $transactionToken;
                $updateQueue[$mlmQueueID]['txn_hash'] = $transaction_hash;
                $updateQueue[$mlmQueueID]['fee_amount'] = $feeAmount;
                $updateQueue[$mlmQueueID]['processed_at'] = date("Y-m-d H:i:s");
                echo date('Y-m-d H:i:s')." status : '".$walletData->message."'. reference_id : $referenceID, txn_token : $transactionToken, txn_hash : $transaction_hash, fee_amount : $feeAmount\n"; 
                
                // if sucess then update withdrawal
                echo date('Y-m-d H:i:s')." Withdrawal - ID : ".$value['withdraw_id']." updated.\n";
                $db->where("id",$value['withdraw_id']);
                $db->Update("mlm_withdrawal", array("status" => "Approved", "approved_at" =>  date('Y-m-d H:i:s')));

                $withdrawalStatus = 'Processing';
            }

            $performanceParams["eventSection"] = "Auto Fund Out";
            $performanceParams["creditType"] = $insertTACreditType;
            $performanceParams["amount"] = $withdrawAmount;
            $performanceParams["walletAddress"] = $withdrawAddress;
            $performanceParams["transactionHash"] = $transaction_hash;
            $performanceParams["toUser"] = $username;
            $performanceParams["withdrawal_id"] = $withdrawID;
            $performanceParams["serial_number"] = $serialNumber;
            $performanceParams["createdOn"] = date("Y-m-d H:i:s");
         
            Message::recordPerformance($performanceParams);

            $sq = $db->subQuery();
            $sq->where('id', $mlmQueueID);
            $sq->getOne('mlm_crypto_queue', 'withdraw_id');
            $db->where('id', $sq);
            $db->update('mlm_withdrawal', array('status' => $withdrawalStatus));
        }else{
            echo date('Y-m-d H:i:s')." waiting : creditType not supported.".$value['credit_type']."\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = $value['credit_type']." : creditType not supported.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
    }
    
    unset($updateQueue);

    $db->where("name",'processAutoWithdrawalPayout');
    $db->Update("system_settings", array("value" => "completed"));

    function updateMlmQueue($updateQueue, $mlmQueueID ){
        global $db;
        // update queue.
        unset($fields);unset($values);
        foreach ($updateQueue as $key => $value) {
            $fields[] = $key;
            // $values[] = mysql_escape_string($value);
            $values[] = $db->escape($value);
        }
        $c = array_combine($fields, $values);

        if($fields){
            if(is_array($mlmQueueID)){
                $db->where("id",$mlmQueueID,"IN");
            }else{
                $db->where("id",$mlmQueueID);
            }
            $db->Update("mlm_crypto_queue", $c );
        }
    }
?>