<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/config.php');
    include_once($currentPath.'/../include/class.database.php');
    include_once($currentPath.'/../include/class.setting.php');
    include_once($currentPath.'/../include/class.general.php');
    include_once($currentPath.'/../include/class.country.php');
    include_once($currentPath.'/../include/class.tree.php');
    include_once($currentPath.'/../include/class.provider.php');
    include_once($currentPath.'/../include/class.message.php');
    include_once($currentPath.'/../include/class.cash.php');
    include_once($currentPath.'/../include/class.activity.php');
    include_once($currentPath.'/../include/class.product.php');
    include_once($currentPath.'/../include/class.invoice.php');
    include_once($currentPath.'/../include/class.client.php');
    include_once($currentPath.'/../include/class.log.php');
    include_once($currentPath.'/../include/class.bonus.php');
    include_once($currentPath.'/../include/class.subscription.php');

    $db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting         = new Setting($db);
    $general         = new General($db, $setting);
    $country         = new Country($db, $general);
    $tree            = new Tree($db, $setting, $general);
    $provider        = new Provider($db);
    $message         = new Message($db, $general, $provider);
    $cash            = new Cash($db, $setting, $message, $provider);
    $activity        = new Activity($db, $general);
    $product         = new Product($db, $setting);
    $invoice         = new Invoice($db, $setting);
    $logBaseName     = basename(__FILE__, '.php');
    $logPath         = $currentPath.'/log/';
    $log             = new Log($logPath, $logBaseName);
    $bonus           = new Bonus($db, $general, $setting, $cash, $log);
    $client          = new Client($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus);
    $subscription    = new Subscription($db, $setting, $message, $provider, $log, $general, $client, $cash);


    // $switch = "on";

    // while($switch  == "on"){

        // $switchRes = $db->dbSelect("mlmSetting", "value", "name = 'Address Process'");

        // while ($switchRow = mysql_fetch_assoc($switchRes)) {
        //     $switch = $switchRow['value'];
        // }

        $db->where("status", "Active");
        $db->where("processed", 0);
        $db->where("queue_type", "generateWalletAddress");
        $db->orderBy("id", "ASC");

        $fields = array('client_id', 'id');
        $queueInfo = $db->get("mlm_queue", null, $fields);

        foreach($queueInfo as $queueData){
            $clientID = $queueData["client_id"];
            $queueID = $queueData["id"];
            $memberCoinAddresses = $subscription->generateWalletAddress($clientID);

            $fields = array("processed", "status");
            $values = array(1, "Processed");
            $updateData = array_combine($fields, $values);
            $db->where('id', $queueID);
            $db->update("mlm_queue", $updateData);

            echo "Queue ".$queueID." is updated. Coin addresses are assigned to clientID ".$clientID.".\n";

        }

        // sleep(2);
    // }



    // echo '<pre>';
    // print_r($result);
    // echo '</pre>';


?>
