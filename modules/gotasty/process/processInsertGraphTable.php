<?php

    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');

    log::setupLogPath(__DIR__, __FILE__);

    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;

    $type = $argv[1];

    $walletType = array("trdBBIT");

    switch($type){
        case "1m": //1 minute
            $interval = 60;
            $tableName = "trd_graph_data_1m";
        break;

        case "15m": //15 minute
            $interval = 900;
            $tableName = "trd_graph_data_15m";
        break;

        case "30m": //30 minute
            $interval = 1800;
            $tableName = "trd_graph_data_30m";
        break;

        case "1h": //60 minute
            $interval = 3600;
            $tableName = "trd_graph_data_1h";
        break;

        case "6h": //60 minute
            $interval = 6*3600;
            $tableName = "trd_graph_data_6h";
        break;

        case "12h": //60 minute
            $interval = 12*3600;
            $tableName = "trd_graph_data_12h";
        break;

        case "1d": //1 day
            $interval = 86400;
            $tableName = "trd_graph_data_1d";
        break;

        case "1w": //1 week
            $interval = 7*86400;
            $tableName = "trd_graph_data_1w";
        break;
        
        case "1mon" ://1 month
            $interval = date("t")*86400;
            $tableName = "trd_graph_data_1mon";
        break;
        
        default:
            Log::write("invalid argument, valid: 1m, 15m, 30m, 1h, 6h, 12h, 1d, 1w, 1mon");
            exit;
        break;
    }

    function getSleepTime(){
        global $interval, $type;
        
        if($type == "1mon"){
            $interval = date("t")*86400; //recheck interval for different month
        }
        
        $timestamp = time();
        $ceilTimestamp = ceil($timestamp/$interval)*$interval;
        $timediff = (int)$ceilTimestamp - (int)$timestamp;
        
        return $timediff;
    }

    while(true){
        
        Setting::setupSysSetting($config);
        General::insertDailyTable("acc_credit","process");
        
        foreach ($walletType as $coinType) {
            Trading::insertGraphData($tableName, $coinType);
        }
        
        if(getSleepTime() == "0"){
            sleep($interval);
        }else{
            sleep(getSleepTime());
        }   

    }


?>
