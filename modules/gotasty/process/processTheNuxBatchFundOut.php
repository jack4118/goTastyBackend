<?php 
    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');
    include_once($currentPath.'/../include/config.php');

    log::setupLogPath(__DIR__, __FILE__);

    // check process is it running.
    $db->where("name",'processAutoWithdrawalPayout');
    $statusAutoWithdrawalPayout = $db->getValue("system_settings", "value");
    if($statusAutoWithdrawalPayout=='completed'){
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "processing"));
    }
    else{
        Log::write(date('Y-m-d H:i:s')." Auto Withdrawal Payout is running.\nStop now.\n");
        exit;
    }

    $db->where('name', 'externalFundOutCryptoType');
    $db->where('value', '1');
    $validCoinTypeAry = $db->getValue('mlm_crypto_setting', 'coin_type', NULL);
    foreach ($validCoinTypeAry as $coin) {
        $coinConvert = CryptoPG::getCryptoConverter($coin);
        $validCoinType[$coinConvert['theNuxPrefix']] = $coinConvert['theNuxPrefix'];
    }

    // $currntTS = time();
    // $currentMinute = date("i");
    // if($currentMinute % 10 == 0){
    //     $halfHourAgo = date("Y-m-d, H:i:s", ($currntTS  - 1800));
    //     $db->where('processed', '1');
    //     $db->where('created_at', $halfHourAgo, ">=");
    //     $db->where('status', 'pending');

    //     $delayedQueue = $db->map('id')->get("mlm_crypto_queue", NULL, "id, (SELECT username FROM client WHERE client.id = (SELECT client_id FROM mlm_withdrawal WHERE id = withdraw_id)) AS client_username, (SELECT serial_number FROM mlm_withdrawal WHERE id = withdraw_id) AS serial_number, credit_type, amount, wallet_address, withdraw_id, txn_hash, processed_at");

    //     foreach ($delayedQueue as $id => $delay) { 
    //         $delayCoinType = CryptoPG::getCryptoConverter($delay['credit_type']);
    //         $cType = $delayCoinType['shortForm'];
            
    //         $performanceParams["eventSection"] = "Auto Fund Out (30min)";
    //         $performanceParams["creditType"] = $cType;
    //         $performanceParams["amount"] = $delay['amount'];
    //         $performanceParams["walletAddress"] = $delay['wallet_address'];
    //         $performanceParams["transactionHash"] = $delay['txn_hash'];
    //         $performanceParams["toUser"] = $delay['client_username'];
    //         $performanceParams["withdrawal_id"] = $delay['withdraw_id'];
    //         $performanceParams["serial_number"] = $delay['serial_number'];
    //         $performanceParams["createdOn"] = $delay['processed_at'];

    //         Message::recordPerformance($performanceParams);
    //     }
    // }

    // get TheNux credentials
    $addressBusinessID = $config['theNuxWalletBusinessID'];
    $apiKey = $config['theNuxWalletApiKey'];
    $providerDomain = $config['nuxPayAPIDomain'];

    if(empty($apiKey) || empty($addressBusinessID) || empty($providerDomain)){
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "completed"));
        Log::write(date("Y-m-d H:i:s") . " Provider domian, business ID or api key is not set. addressBusinessID: $addressBusinessID apiKey: $apiKey providerDomain: $providerDomain\n");
        exit();
    }

    // get queue to ready process.
    $db->where("queue_type",'cryptoWithdrawalPayout');
    $db->where("processed",'0');
    $db->where("status",'waiting');
    $db->orderBy("id",'ASC');
    $queueRes = $db->map('id')->get("mlm_crypto_queue", array(0, 50), "id, credit_type, (SELECT username FROM client WHERE client.id = (SELECT client_id FROM mlm_withdrawal WHERE id = withdraw_id)) AS client_username, (SELECT serial_number FROM mlm_withdrawal WHERE id = withdraw_id) AS serial_number, amount, wallet_address, withdraw_id, belong, batch_id, status");

    if(!$queueRes) {
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "completed"));
        Log::write(date("Y-m-d H:i:s") . " No Waiting Queue...\n");
        exit();
    }

    $db->where('id', array_keys($queueRes), 'IN');
    $db->update('mlm_crypto_queue', array('processed' => '2'));

    // Validate Records
    foreach ($queueRes as $queueID => $queueData) {
        unset($errorMsg);
        $withdrawAmount = $queueData["amount"];
        $withdrawAddress = $queueData["wallet_address"];
        $withdrawCreditType = $queueData["credit_type"];

        if($queueStatus == 'confirmed'){
            echo date('Y-m-d H:i:s')." Invalid Status\n";             
            $errorMsg[] = 'Invalid Status.';
        }

        if(!$withdrawCreditType){
            echo date('Y-m-d H:i:s')." Credit Type not found.\n";             
            $errorMsg[] = 'Credit Type not found.';
        }

        if(!$withdrawAmount){
            echo date('Y-m-d H:i:s')." Amount not found.\n"; 
            $errorMsg[] = 'Amount not found.';
        }

        if(!$withdrawAddress){
            echo date('Y-m-d H:i:s')." Wallet Address not found.\n"; 
            $errorMsg[] = 'Wallet Address not found.';
        }

        if(!is_numeric($withdrawAmount) ){
            echo date('Y-m-d H:i:s')." Amount not valid.'".$withdrawAmount."'\n"; 
            $errorMsg[] = "Amount not valid.'".$withdrawAmount."'\n";
        }

        $withdrawCoinTypeAry = CryptoPG::getCryptoConverter($withdrawCreditType);
        $withdrawCoinType = $withdrawCoinTypeAry['theNuxPrefix'];

        if(!in_array($withdrawCoinType, $validCoinType)){
            echo date('Y-m-d H:i:s')." creditType not supported.".$withdrawCoinType."\n"; 
            $errorMsg[] = "creditType not supported.".$withdrawCoinType."\n";
        }

        if($errorMsg){
            unset($updateFailedAry);
            $updateFailedAry = array(
                'processed_at'  => date('Y-m-d H:i:s'),
                'processed' => '1',
                'error_msg' => implode(", ", $errorMsg),
                'status' => 'failed'
            );
            $db->where('id', $queueID);
            $db->update('mlm_crypto_queue', $updateFailedAry);

            continue; // skip to process this error queue
        }

        $transferQueue['recipient_address'] = $withdrawAddress;
        $transferQueue['amount'] = $withdrawAmount;
        $transferQueue['queue_id'] = $queueID;

        $validQueueIDAry[$queueID] = $queueID;
        $transferQueueAry[$withdrawCoinType][] = $transferQueue;
    }

    if(!$transferQueueAry || !$validQueueIDAry){
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "completed"));
        Log::write(date("Y-m-d H:i:s") . " Do not have valid process queue\n");
        exit();
    }

    foreach($transferQueueAry as $tranferCoin => $tranferCoinData){
        unset($transactionDetails);
        unset($processQueueIDAry);
        
        $batchReferenceID = $db->getNewID();
        Log::write(date('Y-m-d H:i:s')." Sending '".$tranferCoin."' transactionDetails ".json_encode($tranferCoinData)."\n");

        foreach ($tranferCoinData as $tranfer) {
            $transactionDetail['recipient_address'] = $tranfer['recipient_address'];
            $transactionDetail['amount'] = $tranfer['amount'];

            $transactionDetails[] = $transactionDetail;
            $processQueueIDAry[] = $tranfer['queue_id'];
        }

        $updateQueueReferenceAry = array(
            'reference_id'  => $batchReferenceID
        );  

        $db->where('id', $processQueueIDAry, 'IN');
        $db->update('mlm_crypto_queue', $updateQueueReferenceAry);

        $url = $providerDomain.'/crypto/external/transfer/by/batch';
        $postParams = array(
            "account_id" => $addressBusinessID,
            "api_key" => $apiKey,
            "wallet_type" => strtolower($tranferCoin),
            "reference_id" => $batchReferenceID,
            "transaction_details" => $transactionDetails
        );

        $postParams = json_encode($postParams);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams); // $response->setBody()
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $returnWalletData = curl_exec($ch);
        Log::write(date('Y-m-d H:i:s')." walletData '".$returnWalletData."'\n");
        echo $walletData."\n";

        $walletData = json_decode($returnWalletData, true);

        switch (strtolower($walletData['message'])) {
            case 'failed':
            case 'error':
                $returnStatus = 'failed';
                $returnMsg = $walletData['message_d'] ?: $walletData['statusMsg'];
                $withdrawalStatus = 'Failed';

                $updateQueueAry = array(
                    'processed_at'  => date('Y-m-d H:i:s'),
                    'processed' => '1',
                    'status'        => $returnStatus,
                    'error_msg'     => $returnMsg,
                    'data_in'       => $postParams,
                    'url'           => $url,
                    'data_out'      => $returnWalletData
                );  

                $db->where('id', $processQueueIDAry, 'IN');
                $db->update('mlm_crypto_queue', $updateQueueAry);

                $sq = $db->subQuery();
                $sq->where('id', $processQueueIDAry, 'IN');
                $sq->get('mlm_crypto_queue', NULL, 'withdraw_id');
                $db->where('id', $sq, 'IN');
                $db->update('mlm_withdrawal', array('status' => $withdrawalStatus));
                break;

            case 'success':
                $returnStatus = $walletData['data']['status'];
                $returnReferenceID = $walletData['data']['reference_id'];
                $returnTransactionDetails = $walletData['data']['transactionDetails'];
                $returnErrorDetails = $walletData['data']['errorDetails'];

                foreach ($returnTransactionDetails as $returnTransaction) {
                    $returnReceiverAddress = $returnTransaction['receiverAddress'];
                    $returnTrxToken = $returnTransaction['transactionToken'];
                    $returnTrxHash = $returnTransaction['transaction_hash'];
                    $returnFeeAmount = $returnTransaction['serviceChargeAmount']; 
                    $returnAmount = $returnTransaction['amount'];
                    $withdrawalStatus = 'Processing';

                    $returnFeeAmount = bcdiv($returnFeeAmount, 1000000, 8);
                    $returnAmount = bcdiv($returnAmount, 1000000, 8);

                    $updateQueueAry = array(
                        'processed_at'  => date('Y-m-d H:i:s'),
                        'processed' => '1',
                        'txn_token' => $returnTrxToken,
                        'txn_hash'  => $returnTrxHash,
                        'fee_amount' => $returnFeeAmount,
                        'status' => $returnStatus,
                        'data_in' => $postParams,
                        'url' => $url,
                        'data_out' => $returnWalletData
                    );

                    $sq = $db->subQuery();
                    $sq->where('wallet_address', $returnReceiverAddress);
                    $sq->where('amount', $returnAmount);
                    $sq->where('reference_id', $batchReferenceID);
                    $sq->where('status', 'waiting');
                    $sq->orderBy('id', 'ASC');
                    $sq->getOne('mlm_crypto_queue', 'id');
                    $db->where('id', $sq);
                    $db->update('mlm_crypto_queue', $updateQueueAry);

                    $sq = $db->subQuery();
                    $sq->where('wallet_address', $returnReceiverAddress);
                    $sq->where('reference_id', $batchReferenceID);
                    $sq->get('mlm_crypto_queue', NULL, 'withdraw_id');
                    $db->where('id', $sq, 'IN');
                    $db->update('mlm_withdrawal', array('status' => $withdrawalStatus));
                }

                foreach ($returnErrorDetails as $returnErrorDetails) {
                    $returnErrorAddress = $returnErrorDetails['receiverAddress'];
                    $returnErrorReason = $returnErrorDetails['reason'];
                    $withdrawalErrorStatus = 'Failed';

                    $updateErrorQueueAry = array(
                        'processed_at'  => date('Y-m-d H:i:s'),
                        'status' => 'failed',
                        'processed' => '1',
                        'error_msg' => $returnErrorReason,
                        'data_in'   => $postParams,
                        'url'       => $url,
                        'data_out'  => $returnWalletData
                    );

                    $db->where('wallet_address', $returnErrorAddress);
                    $db->where('reference_id', $batchReferenceID);
                    $db->update('mlm_crypto_queue', $updateErrorQueueAry);

                    $sq = $db->subQuery();
                    $sq->where('wallet_address', $returnErrorAddress);
                    $sq->where('reference_id', $batchReferenceID);
                    $sq->get('mlm_crypto_queue', NULL, 'withdraw_id');
                    $db->where('id', $sq, 'IN');
                    $db->update('mlm_withdrawal', array('status' => $withdrawalErrorStatus));
                }

                break;
            
            default:
                $returnStatus = 'suspened';
                $returnMsg = $walletData['message_d'] ?: $walletData['statusMsg'];
                $withdrawalStatus = 'Processing';

                $updateQueueAry = array(
                    'processed_at'  => date('Y-m-d H:i:s'),
                    'processed' => '1',
                    'status'    => $returnStatus,
                    'data_in'   => $postParams,
                    'url'       => $url,
                    'data_out'  => $returnWalletData
                );  

                $db->where('id', $processQueueIDAry, 'IN');
                $db->update('mlm_crypto_queue', $updateQueueAry);

                $sq = $db->subQuery();
                $sq->where('id', $processQueueIDAry, 'IN');
                $sq->get('mlm_crypto_queue', NULL, 'withdraw_id');
                $db->where('id', $sq, 'IN');
                $db->update('mlm_withdrawal', array('status' => $withdrawalStatus));
                break;
        }
    }

    $db->where("name",'processAutoWithdrawalPayout');
    $db->Update("system_settings", array("value" => "completed"));

?>