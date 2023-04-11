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
    include_once($currentPath.'/../include/class.apirequest.php');
    include_once($currentPath.'/../include/class.graph.php');
    include_once($currentPath.'/../include/class.leader.php');
    include_once($currentPath.'/../include/class.validation.php');
    include_once($currentPath.'/../include/class.wallet.php');
    include_once($currentPath.'/../include/class.queue.php');
    include_once($currentPath.'/../include/class.sms.php');
    include_once($currentPath.'/../include/class.excel.php');
    include_once($currentPath.'/../include/thirdPartyDBConfig.php');

    $db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting         = new Setting($db);
    $general         = new General($db, $setting);
    $log             = new Log();

    $msgpack         = new msgpack();
    
    $user            = new User($db, $setting, $general);
    $graph           = new graph($db, $setting, $general);
    $queue           = new Queue($db, $setting, $general);
    $sms             = new SMS($db, $setting, $general);
    $MT4             = new MT4($db, $setting, $general);
    $api             = new Api($db, $general);
    $provider        = new Provider($db);
    $message         = new Message($db, $general, $provider);
    $webservice      = new Webservice($db, $general, $message);
    $permission      = new Permission($db, $general);
    $wallet          = new Wallet($db, $setting, $general);

    $cash            = new Cash($db, $setting, $message, $provider, $log, $general, $client, $wallet, $MT4);
    $language        = new Language($db, $general, $setting);
    $activity        = new Activity($db, $general);
    
    $country         = new Country($db, $general);
    $tree            = new Tree($db, $setting, $general);
    $invoice         = new Invoice($db, $setting);
    $product         = new Product($db, $setting, $general);
    $otp             = new Otp($db, $setting, $general,$message,$sms);
    $bonus           = new Bonus($db, $general, $setting, $cash, $log, $otp, $tree);
    $validation      = new validation($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus, $otp, $config);
    $client          = new Client($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus, $otp, $config, $wallet, $validation, $queue, $sms);
    $admin           = new Admin($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $otp, $tree,$bonus,$wallet,$message);
    $memo            = new Memo($db, $general, $setting);
    $announcement    = new Announcement($db, $general, $setting);
    $document        = new Document($db, $general, $setting);
    $report          = new Report($db, $general, $setting,$bonus,$tree, $admin);

    $dashboard       = new Dashboard($db, $announcement, $cash, $admin, $setting, $general, $wallet, $product,$tree);
    $ticket          = new Ticket($db, $setting, $general,$otp);
    $stake           = new Stake($db, $setting, $general, $cash, $log, $admin, $bonus, $client, $otp);
    $coinswap        = new Coinswap($db, $setting, $message, $provider, $log, $general, $client, $cash, $admin);
    $coindata        = new Coindata($db, $setting, $message, $provider, $log, $general, $client, $cash);
    $trade           = new Trade($db, $setting, $general, $cash, $client, $ticket, $otp, $coindata);
    $subscription    = new Subscription($db, $setting, $message, $provider, $log, $general, $client, $cash, $bonus,$sms);
    $agent           = new Agent($db, $setting, $general, $tree, $cash, $bonus, $admin);
    $apirequest      = new Apirequest($db, $setting, $message, $provider, $log, $general, $client, $cash, $otp, $announcement, $admin, $dashboard, $stake, $document, $activity, $trade, $coinswap, $tree, $coindata);
    $leader          = new Leader($db, $setting, $general, $cash, $bonus);
    $excel           = new Excel($db, $setting, $message, $provider, $log, $general, $client, $cash, $otp, $announcement, $admin, $dashboard, $stake, $document, $activity, $trade, $coinswap, $tree, $coindata, $report, $agent, $bonus);

    $dbName = $config['dB'];
    $curDateTime = date("Y-m-d H:i:s");
    $curHour = date("H");

    $webserviceTable = "web_services_" . date('Ymd');
    if($curHour == "00") {
        $webserviceTable = "web_services_" . date('Ymd', strtotime($curDateTime . " -1 days"));
    }

    $tableResult = $db->rawQuery("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '" . $webserviceTable . "' AND TABLE_SCHEMA = '" . $dbName . "'");

    if(!empty($tableResult)) {
        $db->where("created_at", date("Y-m-d H:00:00", strtotime($startDate . " -1 hour")), ">=");
        $db->where("created_at", date("Y-m-d H:59:59", strtotime($startDate . " -1 hour")), "<=");
        $webserviceResult = $db->get($webserviceTable, NULL, "id, client_id, client_username, command, data_in, data_out, created_at");

        foreach ($webserviceResult as $webserviceValue) {
            $webserviceContentTemp = "";
            $webserviceContentTemp .= "ID\t: " . $webserviceValue['id'] . "\n";
            $webserviceContentTemp .= "Command\t: " . $webserviceValue['command'] . "\n";
            $webserviceContentTemp .= "Username\t: " . $webserviceValue['client_username'] . "\n";
            $webserviceContentTemp .= "Date/Time\t: " . $webserviceValue['created_at'] . "\n";

            if(empty($webserviceValue['data_in'])) {
                $webserviceContent .= $webserviceContentTemp;
                $webserviceContent .= "Error\t: DataIn" . "\n\n";
            } elseif(empty($webserviceValue['data_out'])) {
                $webserviceContent .= $webserviceContentTemp;
                $webserviceContent .= "Error\t: DataOut" . "\n\n";
            }
            
        }

        if(!empty($webserviceContent)) {
            $content .= $webserviceContent;
            Message::createMessageOut('10006', $content, "Webservices Empty");
        }

    }

    ## Sample contab
    ## run check webservices in out (every hour)
    /*0 * * * * root /usr/bin/php /var/www/qtnBackend/modules/mlmPlatform/process/processCheckWebservices.php >> /var/www/qtnBackend/modules/mlmPlatform/process/log/processCheckWebservices.log 2>&1*/

?>
