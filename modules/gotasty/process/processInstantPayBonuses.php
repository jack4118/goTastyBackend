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

    $db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    // $setting         = new Setting($db);
    Setting::setupSysSetting($config);
    // $general         = new General($db, $setting);
    $logBaseName = basename(__FILE__, '.php');
    $logPath = $currentPath . '/log/';
    $log            = new Log($logPath, $logBaseName);

    $msgpack         = new msgpack();
    
    $user            = new User();
    $graph           = new graph();
    $queue           = new Queue();

    $api             = new Api();

    $provider        = new Provider();

    $message         = new Message($provider);

    // $webservice      = new Webservice();
    $permission      = new Permission();

    $wallet          = new Wallet();

    $cash            = new Cash($message, $provider, $log, $client, $wallet, $MT4);

    $language        = new Language();
    // $activity        = new Activity();
    
    // $journals        = new Journals($db, $general);
    $country         = new Country();
    $tree            = new Tree();
    $invoice         = new Invoice();
    $product         = new Product();
    $otp             = new Otp($message,$sms);

    $bonus           = new Bonus($cash, $otp, $tree);

    $validation      = new validation($country, $product, $invoice, $bonus);
    $client          = new Client($validation, $queue);
    $admin           = new Admin($client);
    $memo            = new Memo();
    $announcement    = new Announcement();
    $document        = new Document();
    $report          = new Report($admin);

    $dashboard       = new Dashboard($announcement, $admin);
    $ticket          = new Ticket($otp);
    $stake           = new Stake($admin);
    $coinswap        = new Coinswap($admin);
    $coindata        = new Coindata($client);
    $trade           = new Trade($ticket, $coindata, $admin);
    $subscription    = new Subscription($client);
    $agent           = new Agent($admin);

    $apirequest      = new Apirequest($admin, $dashboard, $stake, $document, $trade, $coinswap);
    $leader          = new Leader($bonus);

    // $general->setCurrentLanguage("english");
    // $general->setTranslations($translations);
        General::$currentLanguage = $systemLanguage;
        // Include the language file for mapping usage
        include_once('language/lang_all.php');
        // Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
        General::$translations = $translations;

    $bonusDate  = date("Y-m-d", strtotime("-3 mins")); // to avoid 2359 - 0000 missout payment

    if ($argv[1]) {
        // If a bonus date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $bonusDate = $argv[1];
        }
    }

    $db->where('name', 'processInstantPayBonuses');
    $isProcess = $db->getValue('system_settings', 'value'); 
    if($isProcess){
    	$log->write(date("Y-m-d H:i:s")." Last process still executing... process stopped!\n");
    	exit();
    }

    $update = array("value" => 1);
    $db->where('name', 'processInstantPayBonuses');
    $db->update("system_settings", $update);

    // $processIP = time();
    // $log->write(date("Y-m-d H:i:s")." ".$processIP." Start payout bonus!\n");
    $bonusPayoutTime = "23:59:59";
    $bonus->calculateSponsorBonus($bonusDate, $bonusPayoutTime);
    $bonus->paySponsorBonus($bonusDate, $bonusPayoutTime);

    $update = array("value" => 0);
    $db->where('name', 'processInstantPayBonuses');
    $db->update("system_settings", $update);


?>
