<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/config.php');
    include_once($currentPath.'/../include/class.database.php');
    include_once($currentPath.'/../include/class.setting.php');

    $db        = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting         = new Setting($db);

    $isLocalhost = $setting->systemSetting['isLocalhost'];

    while(1){
        $db->where('type', "liveCoinRate");
        $res = $db->get("system_settings", NULL, array("name", "value"));

        foreach ($res as $key => $value) {
            if($value["name"] == "bitcoin Latest Rate"){
                $coinRateInfo["BTC"]  = $value["value"];
                $mobile["BTC"]["displayName"] = "BTC";
                $mobile["BTC"]["rate"] =  $value["value"];

            }
            else if($value["name"] == "ethereum Latest Rate"){
                $coinRateInfo["ETH"]  = $value["value"];
                $mobile["ETH"]["displayName"] = "ETH";
                $mobile["ETH"]["rate"] =  $value["value"];

            }
            else if($value["name"] == "ripple Latest Rate"){
                $coinRateInfo["XRP"]  = $value["value"];
                $mobile["XRP"]["displayName"] = "XRP";
                $mobile["XRP"]["rate"] =  $value["value"];

            }else if($value["name"] == "eos Latest Rate"){
                $coinRateInfo["EOS"]  = $value["value"];
                $mobile["EOS"]["displayName"] = "EOS";
                $mobile["EOS"]["rate"] =  $value["value"];

            }else if($value["name"] == "tether Latest Rate"){
                $coinRateInfo["USDT"]  = $value["value"];
                $mobile["USDT"]["displayName"] = "USDT";
                $mobile["USDT"]["rate"] =  $value["value"];

            }else if($value["name"] == "cardano Latest Rate"){
                $coinRateInfo["Cardano"]  = $value["value"];
                $mobile["Cardano"]["displayName"] = "Cardano";
                $mobile["Cardano"]["rate"] =  $value["value"];
            }
            
        }
        $mobileData = array_values($mobile);

        $jsonData = json_encode($coinRateInfo);
        file_put_contents(__DIR__.'/../coinRateData/coinRate.json', $jsonData);

        $mobileJsonData = json_encode($mobileData);
        file_put_contents(__DIR__.'/../coinRateData/mobileCoinRate.json', $mobileJsonData);

        if($isLocalhost=='1'){
            $cmd = "scp ".__DIR__."/../coinRateData/coinRate.json root@testfront:/var/www/ibgProjectTEST/ibgfrontend/member/coinRateData/";
            exec($cmd, $output, $result);

            $cmd = "scp ".__DIR__."/../coinRateData/mobileCoinRate.json root@testfront:/var/www/ibgProjectTEST/ibgfrontend/member/coinRateData/";
            exec($cmd, $output, $result);
            sleep(1);
            
        }else{

            $cmd = "scp ".__DIR__."/../coinRateData/coinRate.json root@".$setting->systemSetting['frontendServerIP'].":/var/www/ibgProjectTEST/ibgfrontend/member/coinRateData/";
            exec($cmd, $output, $result);

            $cmd = "scp ".__DIR__."/../coinRateData/mobileCoinRate.json root@".$setting->systemSetting['frontendServerIP'].":/var/www/ibgProjectTEST/ibgfrontend/member/coinRateData/";
            exec($cmd, $output, $result);
            sleep(1);


        }
    }

?>
