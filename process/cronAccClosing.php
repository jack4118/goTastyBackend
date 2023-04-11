<?php
    
    /**
     * Script to sum each and every client's balance at the given date and store the balance to be brought forward to the next day
     */

    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.cash.php');
    include($currentPath.'/../include/class.provider.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.log.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $log = new Log($logPath, $logBaseName);
    $provider = new Provider($db);
    $message = new Message($db,"",$provider);
    $cash = new Cash($db, $setting, $message, $provider, $log);
    
    
    // Get the closing period in days (Default 1 day)
    $closingPeriod = $setting->systemSetting['closingPeriod']? $setting->systemSetting['closingPeriod'] : 1;
    $closingDate = date("Y-m-d", strtotime("-$closingPeriod day"));
    
    if ($argv[1]) {
        // If a closing date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $closingDate = $argv[1];
        }
    }

    $db->where("completed","1");
    $db->orderBy("id","DESC");
    $latestAccClosingDate = $db->getValue("acc_closing_batch","closing_date");

    if($latestAccClosingDate){
        $latestAccClosingDate = date("Y-m-d",strtotime("+1 day",strtotime($latestAccClosingDate)));
    }else{
        //Start from the first transaction date if no account closing before
        $db->orderBy("created_at","ASC");
        $latestAccClosingDate = $db->getValue("credit_transaction","DATE(created_at)");
    }

    if(strtotime($closingDate) < strtotime($latestAccClosingDate)){
        $closingDate = $latestAccClosingDate;
    }

    while(strtotime($latestAccClosingDate) <= strtotime($closingDate)){
        //Call the closing function
        $cash->closing($latestAccClosingDate);
        $latestAccClosingDate = date("Y-m-d",strtotime("+1 day",strtotime($latestAccClosingDate)));
        sleep(0.1);
    }  
?>
