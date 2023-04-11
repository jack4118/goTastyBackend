<?php 

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/class.msgpack.php');
    include_once($currentPath.'/../include/config.php');
    include_once($currentPath.'/../include/class.admin.php');
    include_once($currentPath.'/../include/class.database.php');
    include_once($currentPath.'/../include/class.cash.php');
    include_once($currentPath.'/../include/class.webservice.php');
    include_once($currentPath.'/../include/class.user.php');
    include_once($currentPath.'/../include/class.api.php');
    include_once($currentPath.'/../include/class.message.php');
    include_once($currentPath.'/../include/class.permission.php');
    include_once($currentPath.'/../include/class.setting.php');
    include_once($currentPath.'/../include/class.language.php');
    include_once($currentPath.'/../include/class.provider.php');
    include_once($currentPath.'/../include/class.journals.php');
    include_once($currentPath.'/../include/class.country.php');
    include_once($currentPath.'/../include/class.general.php');
    include_once($currentPath.'/../include/class.tree.php');
    include_once($currentPath.'/../include/class.activity.php');
    include_once($currentPath.'/../include/class.invoice.php');
    include_once($currentPath.'/../include/class.product.php');
    include_once($currentPath.'/../include/class.client.php');
    include_once($currentPath.'/../include/class.memo.php');
    include_once($currentPath.'/../include/class.announcement.php');
    include_once($currentPath.'/../include/class.document.php');
    include_once($currentPath.'/../include/class.bonus.php');
    include_once($currentPath.'/../include/PHPExcel.php');
    include_once($currentPath.'/../include/class.log.php');
    include_once($currentPath.'/../include/class.report.php');
    include_once($currentPath.'/../include/class.dashboard.php');
    include_once($currentPath.'/../include/class.ticket.php');
    include_once($currentPath.'/../include/class.trade.php');
    include_once($currentPath.'/../include/class.stake.php');
    include_once($currentPath.'/../include/class.coinswap.php');
    include_once($currentPath.'/../include/class.coindata.php');
    include_once($currentPath.'/../include/class.otp.php');
    include_once($currentPath.'/../include/class.subscription.php');
    include_once($currentPath.'/../include/class.agent.php');
    include_once($currentPath.'/../include/class.graph.php');
    include_once($currentPath.'/../include/class.leader.php');
    include_once($currentPath.'/../include/class.validation.php');
    include_once($currentPath.'/../include/class.wallet.php');
    include_once($currentPath.'/../include/class.queue.php');

    // include_once($currentPath.'/../include/class.exportExcel.php');
    $db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting         = new Setting($db);
    $general         = new General($db, $setting);
    $logBaseName     = basename(__FILE__, '.php');
    $logPath         = $currentPath.'/log/';
    $log             = new Log($logPath, $logBaseName);

    $msgpack         = new msgpack();
    
    $user            = new User($db, $setting, $general);
    $graph           = new graph($db, $setting, $general);
    $queue           = new Queue($db, $setting, $general);
    $api             = new Api($db, $general);
    $provider        = new Provider($db);
    $message         = new Message($db, $general, $provider);
    $webservice      = new Webservice($db, $general, $message);
    $permission      = new Permission($db, $general);
    $wallet          = new Wallet($db, $setting, $general);

    $cash            = new Cash($db, $setting, $message, $provider, $log, $general, $client, $wallet);
    $language        = new Language($db, $general, $setting);
    $activity        = new Activity($db, $general);
    
    // $journals        = new Journals($db, $general);
    $country         = new Country($db, $general);
    $tree            = new Tree($db, $setting, $general);
    $invoice         = new Invoice($db, $setting);
    $product         = new Product($db, $setting, $general);
    $otp             = new Otp($db, $setting, $general,$message);
    $bonus           = new Bonus($db, $general, $setting, $cash, $log, $otp, $tree);
    $validation      = new validation($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus, $otp, $config);
    $client          = new Client($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus, $otp, $config, $wallet, $validation, $queue);
    $admin           = new Admin($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $otp, $tree,$bonus,$wallet);
    $memo            = new Memo($db, $general, $setting);
    $announcement    = new Announcement($db, $general, $setting);
    $document        = new Document($db, $general, $setting);
    $report          = new Report($db, $general, $setting);

    $dashboard       = new Dashboard($db, $announcement, $cash, $admin, $setting, $general, $wallet, $product);
    $ticket          = new Ticket($db, $setting, $general,$otp);
    $stake           = new Stake($db, $setting, $general, $cash, $log, $admin, $bonus, $client, $otp);
    $coinswap        = new Coinswap($db, $setting, $message, $provider, $log, $general, $client, $cash, $admin);
    $coindata        = new Coindata($db, $setting, $message, $provider, $log, $general, $client, $cash);
    $trade           = new Trade($db, $setting, $general, $cash, $client, $ticket, $otp, $coindata);
    $subscription    = new Subscription($db, $setting, $message, $provider, $log, $general, $client, $cash);
    $agent           = new Agent($db, $setting, $general, $tree, $cash, $bonus);
    $leader          = new Leader($db, $setting, $general, $cash, $bonus);

    $validCreditType = array('bitcoin','ethereum','tether');
    $btcSendOut = 0;

    // theNux setting.
    // test site
    $apiKey = 'xFetdrpXGSDvo30awl8Ic5bmT9Ls2KqZ';
    $addressBusinessID ='15675';
    $domain ='dev.xun.global:5281';

// live site
// $apiKey = '';
// $addressBusinessID = '11521';
// $domain ='prod.xun.global:5281';
// 'https://'.$domain.':'.$port.'/crypto/external/transfer?api_key='.$apiKey.'&business_id='.$addressBusinessID;
// https://'.$domain.':'.$port.'/crypto/external/transfer/by/batch

    // debug mode.
    // $db->where("name",'processAutoWithdrawalPayout');
    // $db->Update("system_settings", array("value" => "completed"));
    // min amount : bitcoin 0.0001, ethereum 0.001
    // $dataABC = array("credit_type"=>"bitcoin","amount"=>"0.00001", "wallet_address" => "2NC47H4hWEr7A9iyy717niZjFyQKbUxex9Z", "withdraw_id" => "9527", "belong" => "9527", "batch_id" => "9528");
    // $dataABC = array("credit_type"=>"ethereum","amount"=>"99.0010", "wallet_address" => "0x87eB6b7deD87Eb9f635d9a247F39fd9b111F2Ae3", "withdraw_id" => "1014328269", "belong" => "9527", "batch_id" => "9528" );

    // $testABC = json_encode($dataABC);
    // $a = array("data","client_id","processed","queue_type", "created_at", "url");
    // $b = array($testABC, "1000033","0","autoWithdrawalPayout", date('Y-m-d H:i:s'), "backdoor");
    // $c = array_combine($a, $b);
    // $c = array_merge($c, $dataABC);
    // $db->Insert("mlm_queue", $c);
    // // debug mode.

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

    // get queue to ready process.
    $db->where("queue_type",'autoWithdrawalPayout');
    $db->where("processed",'0');
    $db->orderBy("credit_type",'ASC');
    $db->orderBy("amount",'ASC');
    $db->orderBy("created_at",'ASC');
    $res = $db->get("mlm_queue", NULL, "id, data, client_id, credit_type, amount, wallet_address, withdraw_id, belong, batch_id");
    foreach ($res as $key => $row) {
        $dataClientID[$row['id']] = $row['client_id'];
        if($row['credit_type'] == 'bitcoin'){
            $btcData[$row['id']] = (array)json_decode($row['data']);
        }else{
            $data[$row['id']] = (array)json_decode($row['data']);
        }
        $updateQueue[$row['id']]['processed'] = "1";
        $noMoreLimit[$row['credit_type']] = 0;
    }
    // print_r($btcData);echo "\n";print_r($data);echo "\n";print_r($res);echo "\n";exit;
    if(!$data && !$btcData) {
        $db->where("name",'processAutoWithdrawalPayout');
        $db->Update("system_settings", array("value" => "completed"));
        exit("\n".date('Y-m-d H:i:s')." No queue.\n");
    }

    foreach ($data as $mlmQueueID => $value) {
        unset($res);
        echo "\n".date('Y-m-d H:i:s')." Processing mlmQueue.ID : '$mlmQueueID'.\n"; 
        if(!$value['credit_type']){
            echo date('Y-m-d H:i:s')." Credit Type not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Credit Type not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$value['amount']){
            echo date('Y-m-d H:i:s')." Amount not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Amount not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$value['wallet_address']){
            echo date('Y-m-d H:i:s')." Wallet Address not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Wallet Address not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!is_numeric($value['amount']) ){
            echo date('Y-m-d H:i:s')." Amount not valid.'".$value['amount']."'\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Amount not valid. ".$value['amount'];
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

        if(in_array($value['credit_type'],$validCreditType)){
            switch ($value['credit_type']) {
                case 'bitcoin':
                    $fullCreditType = 'bitcoin';
                    $insertTACreditType = 'myCryptoBTC';
                    $insertTACreditType = 'bitcoin';
                    break;
                case 'ethereum':
                    $fullCreditType = 'ethereum';
                    $insertTACreditType = 'myCryptoETH';
                    $insertTACreditType = 'ethereum';
                    break;
                case 'tether':
                    $fullCreditType = 'tetherUSD';
                    $insertTACreditType = 'myCryptoUSDT';
                    $insertTACreditType = 'tetherUSD';
                    break;
                
                default:
                    $updateQueue[$mlmQueueID]['error_msg'] = "Credit Type not support.";
                    $updateQueue[$mlmQueueID]['status'] = "failed";
                    updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
                    continue;
                    break;
            }

            // send to theNux - start
            echo date('Y-m-d H:i:s')." Sending '".$fullCreditType."' : '".$value['amount']."' to '".$value['wallet_address']."'.\n"; 
            $url = 'https://'.$domain.'/crypto/external/transfer';
            $url .= "?api_key=".$apiKey;
            $url .= "&wallet_type=".$fullCreditType;
            $url .= "&business_id=".$addressBusinessID;
            $url .= "&reference_id=".$mlmQueueID;
            $url .= "&recipient_address=".$value['wallet_address'];
            $url .= "&amount=".$value['amount'];

            $updateQueue[$mlmQueueID]['data_in'] = $url;
            $updateQueue[$mlmQueueID]['status'] = "Send";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Timeout in seconds
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $walletData = curl_exec($ch);
            // send to theNux - end

            //debug mode
            // success case
            // $walletData = '{"code":1,"message":"SUCCESS","message_d":"Success","data":{"referenceID":"983","transactionToken":"28b523c855ea193d56529984ba060fbc","transactionHash":"0x21d5c34a6cc1f43c271c881e00bc686c2584d1c03e7febd1a4a33ce53d733eea","transactionDetails":[{"receiverAddress":"0xfaf4d58e4bf24cca4239e5b27c1bb5c4a193c98e","amount":"2139800","unit":"USDT","conversionRate":"1000000","exchangeRate":{"USD":"1.00334018"}}],"amountDetails":{"amount":"2139800","unit":"USDT","conversionRate":"1000000","exchangeRate":{"USD":"1.00334018"}},"feeDetails":{"amount":"90000000000000","unit":"ETH","conversionRate":"1000000000000000000","exchangeRate":{"USD":"133.48961195"}},"confirmation":0,"status":"pending","time":"2019-12-24 00:10:06","successTime":""}}';
            // fail case
            // $walletData = '{"code":0,"message":"FAILED","message_d":"Insufficient balance","data":{"errorCode":"E10000","errorMessage":"Insufficient balance","referenceID":"2815"}}';
            // $walletData = '{"code":0,"status":"ERROR","statusMsg":"statusMsg error."}';
            //debug mode

            print_r($walletData);
            $updateQueue[$mlmQueueID]['data_out'] = print_r($walletData,1); // record raw data first.

            $walletData = json_decode($walletData);

            if(strtolower($walletData->message) == 'failed' || strtolower($walletData->status) == 'error' ){
                $updateQueue[$mlmQueueID]['status'] = 'failed';
                $updateQueue[$mlmQueueID]['error_msg'] = (string)$walletData->message_d?:(string)$walletData->statusMsg;
                echo date('Y-m-d H:i:s')." status : '".($walletData->message?:$walletData->status)."', reason : '".($walletData->message_d?:$walletData->statusMsg)."'.\n"; 

                if ($updateQueue[$mlmQueueID]['error_msg'] == 'Insufficient balance.') $noMoreLimit[$value['credit_type']] = 1;
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
                $updateQueue[$mlmQueueID]['reference_id'] = $referenceID;
                $updateQueue[$mlmQueueID]['txn_token'] = $transactionToken;
                $updateQueue[$mlmQueueID]['txn_hash'] = $transaction_hash;
                $updateQueue[$mlmQueueID]['fee_amount'] = $feeAmount;
                echo date('Y-m-d H:i:s')." status : '".$walletData->message."'. reference_id : $referenceID, txn_token : $transactionToken, txn_hash : $transaction_hash, fee_amount : $feeAmount\n"; 
                
                // if sucess then update withdrawal
                echo date('Y-m-d H:i:s')." Withdrawal - ID : ".$value['withdraw_id']." updated.\n";
                $db->where("id",$value['withdraw_id']);
                $db->Update("mlm_withdrawal", array("status" => "Approved", "approved_at" =>  date('Y-m-d H:i:s')));

                $db->where("username",'payout');
                $db->where("type",'Internal');
                $creditSalesID = $db->getValue("client", 'id');
                $cash->insertTAccount($creditSalesID, $dataClientID[$mlmQueueID], $insertTACreditType, $value['amount'], "Bonus Withdrawal", $value['belong'], $mlmQueueID, date('Y-m-d H:i:s'), $value['batch_id'], $dataClientID[$mlmQueueID]);
            }

        }else{
            echo date('Y-m-d H:i:s')." waiting : creditType not supported.".$value['credit_type']."\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = $value['credit_type']." : creditType not supported.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
    }


    foreach ($btcData as $mlmQueueID => $value) {
        unset($res);
        echo "\n".date('Y-m-d H:i:s')." Processing mlmQueue.ID : '$mlmQueueID'.\n"; 
        if(!$value['credit_type']){
            echo date('Y-m-d H:i:s')." Credit Type not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Credit Type not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$value['amount']){
            echo date('Y-m-d H:i:s')." Amount not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Amount not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!$value['wallet_address']){
            echo date('Y-m-d H:i:s')." wallet_address not found.\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "wallet_address not found.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        if(!is_numeric($value['amount']) ){
            echo date('Y-m-d H:i:s')." Amount not valid.'".$value['amount']."'\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = "Amount not valid. ".$value['amount'];
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

        if(in_array($value['credit_type'],$validCreditType)){
            $fullCreditType = 'bitcoin';
            $insertTACreditType = 'myCryptoBTC';
            $insertTACreditType = 'bitcoin';

            // record in batch.
            unset($recipient_address);unset($recipient_amount);
            if(in_array( $value['wallet_address'], $addressIncluding)){
                foreach ($transaction_details as $Rkey => &$Rvalue) {
                    echo $Rvalue['recipient_address']." == ".$value['wallet_address']."\n";
                    echo $Rvalue['amount']." += ".$value['amount']."\n";
                    if($Rvalue['recipient_address'] == $value['wallet_address']){
                        $transaction_details[$Rkey]['amount'] = bcadd($Rvalue['amount'], $value['amount'],8);
                    }
                    echo $Rvalue['amount']."\n";
                }
            }else{
                $transaction_details[] = array("recipient_address" => $value['wallet_address'], "amount" => $value['amount']);
                $addressIncluding[] = $value['wallet_address'];
            }
            $multiMlmQueueID[] = $mlmQueueID;
            $updateQueue[$mlmQueueID]['status'] = "preparing";
            $amountAry[$mlmQueueID] = $value['amount'];
            $totalAmount = bcadd($totalAmount, $value['amount'], 8) ;

            $btcSendOut = 1;

        }else{
            echo date('Y-m-d H:i:s')." waiting : credit_type not supported.".$value['credit_type']."\n"; 
            $updateQueue[$mlmQueueID]['error_msg'] = $value['credit_type']." : creditType not supported.";
            $updateQueue[$mlmQueueID]['status'] = "failed";
            updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            continue;
        }
        updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
    }
    unset($updateQueue);

    if($btcSendOut){

        // send bitcoin in batch
        echo date('Y-m-d H:i:s')." Sending '".$fullCreditType."' : '".print_r($transaction_details,1)."'.\n"; 
        // https://dev.xun.global:5281/crypto/external/transfer/by/batch
        $url = 'https://'.$domain.'/crypto/external/transfer/by/batch';
        
        // echo $url."\n";
        $params['api_key'] = $apiKey;
        $params['wallet_type'] = $fullCreditType;
        $params['business_id'] = $addressBusinessID;
        $params['reference_id'] = $db->getNewID();
        $params['transaction_details'] = $transaction_details;
        $params = json_encode($params);

        $updateMultiQueue['data_in'] = $url;
        $updateMultiQueue['status'] = "Send";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params); // $response->setBody()
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 

        // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $walletData = curl_exec($ch);

        //debug mode
        // success case
        // $walletData = '{"code":1,"message":"SUCCESS","message_d":"Success","data":{"referenceID":"1014971622","transactionToken":"28d9c91b58bc14546bfff08f83f1f616","transactionHash":"f3c0c9d4e1dfc7ee8275c2a6b150cecdee67cf8ac97578e1e16d223f7e37a476","transactionDetails":[{"receiverAddress":"1ZQ1SHa7tuoEgrTUwG7Jzqo9oCbNHDcom","amount":"12797.00000000","unit":"BTC","conversionRate":"100000000","exchangeRate":{"USD":"7362.35891599"}}],"amountDetails":{"amount":"12797","unit":"BTC","conversionRate":"100000000","exchangeRate":{"USD":"7362.35891599"}},"feeDetails":{"amount":655,"unit":"BTC","conversionRate":"100000000","exchangeRate":{"USD":"7362.35891599"}},"confirmation":0,"status":"pending","time":"2019-12-24 17:12:02","successTime":""}}';
        // fail case
        // $walletData = '{"code":0,"message":"FAILED","message_d":"Failed to validate receiver address.","data":{"errorCode":"E10003","errorMessage":"Misc. error","referenceID":"1014955086"}}';
        //debug mode

        print_r($walletData);
        $updateMultiQueue['data_out'] = print_r($walletData,1); // record raw data first.

        $walletData = json_decode($walletData);

        if(strtolower($walletData->message) == 'failed' || strtolower($walletData->status) == 'error' ){
            $updateMultiQueue['status'] = 'failed';
            $updateMultiQueue['error_msg'] = (string)$walletData->message_d?:(string)$walletData->statusMsg;
            echo date('Y-m-d H:i:s')." status : '".($walletData->message?:$walletData->status)."', reason : '".($walletData->message_d?:$walletData->statusMsg)."'.\n"; 

            if ($updateMultiQueue['error_msg'] == 'Insufficient balance.') $noMoreLimit[$value['credit_type']] = 1;
        }

        // success send out.
        if( $updateMultiQueue['status'] != 'failed' && $updateMultiQueue['status'] != 'waiting'){
            $updateMultiQueue['status'] = (string)$walletData->data->status;
            $referenceID = (string)$walletData->data->referenceID?:(string)$walletData->data->reference_id;
            $transactionToken = (string)$walletData->data->transactionToken?:(string)$walletData->data->transaction_token;
            $transaction_hash = (string)$walletData->data->transaction_hash?:(string)$walletData->data->transactionHash;


            $feeAmount = (string)$walletData->data->feeDetails->amount;
            $feeConversionRate = (string)$walletData->data->feeDetails->conversionRate;
            $feeAmount = bcdiv($feeAmount,$feeConversionRate,8);

            foreach ($amountAry as $mlmQueueID => $vAmount) {
                $txFee = bcdiv($vAmount, $totalAmount,8);
                $txFee = bcmul($txFee , $feeAmount, 8);
                $updateQueue[$mlmQueueID]['fee_amount'] = $txFee;
                updateMlmQueue($updateQueue[$mlmQueueID], $mlmQueueID );
            }

            $updateMultiQueue['reference_id'] = $referenceID;
            $updateMultiQueue['txn_token'] = $transactionToken;
            $updateMultiQueue['txn_hash'] = $transaction_hash;
            // $updateMultiQueue['fee_amount'] = $feeAmount;
            echo date('Y-m-d H:i:s')." status : '".$walletData->message."'. reference_id : $referenceID, txn_token : $transactionToken, txn_hash : $transaction_hash, fee_amount : $feeAmount\n"; 
            
            // if sucess then update withdrawal
            echo date('Y-m-d H:i:s')." Withdrawal - ID : ".$value['withdraw_id']." updated.\n";
            $db->where("id",$value['withdraw_id']);
            $db->Update("mlm_withdrawal", array("status" => "Approved", "approved_at" =>  date('Y-m-d H:i:s')));

            $db->where("username",'payout');
            $db->where("type",'Internal');
            $creditSalesID = $db->getValue("client", 'id');
            $cash->insertTAccount($creditSalesID, $dataClientID[$mlmQueueID], $insertTACreditType, $value['amount'], "Bonus Withdrawal", $value['belong'], $mlmQueueID, date('Y-m-d H:i:s'), $value['batch_id'], $dataClientID[$mlmQueueID]);
        }

        updateMlmQueue($updateMultiQueue, $multiMlmQueueID );

    }

    $db->where("name",'processAutoWithdrawalPayout');
    $db->Update("system_settings", array("value" => "completed"));

    function updateMlmQueue($updateQueue, $mlmQueueID ){
        global $db;
        // update queue.
        unset($fields);unset($values);
        foreach ($updateQueue as $key => $value) {
            $fields[] = $key;
            $values[] = mysql_escape_string($value);
        }
        $c = array_combine($fields, $values);

        if($fields){
            if(is_array($mlmQueueID)){
                $db->where("id",$mlmQueueID,"IN");
            }else{
                $db->where("id",$mlmQueueID);
            }
            $db->Update("mlm_queue", $c );
        }
    }
?>