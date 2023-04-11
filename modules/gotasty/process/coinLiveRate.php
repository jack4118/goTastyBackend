<?php

    include_once(__DIR__.'/../include/config.php');
    include_once(__DIR__.'/../include/class.database.php');
    include_once(__DIR__.'/../include/class.setting.php');
    include_once(__DIR__.'/../include/class.cryptoPG.php');

    $db             = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    

    $acceptCoinType = array('bitcoin', 'tether', 'ethereum');

//  while(1){
        $currentDT = date("Y-m-d H:i:s"); // prevent jump second.

        // $acceptCoinType = json_decode($setting->systemSetting['acceptCoinType']);

        /* Coinbase */
        foreach ($acceptCoinType as $coinType => $shortCoinType) {
            $json = file_get_contents("https://api.coinbase.com/v2/prices/$shortCoinType-USD/buy");
            $coinDetail = json_decode($json);

            foreach ($coinDetail as $value) {
                if ((string)$value->base == $shortCoinType) {
                    $price = str_replace(',', '', $value->amount);
                    $coinbaseAry[$coinType] = $price;
                }
            }
            unset($json);
            unset($coinDetail);
        }

        ## get from coinceckgo if coinmarket array is empty
        $json = file_get_contents("https://api.coingecko.com/api/v3/simple/price?ids=".implode(",", $acceptCoinType)."&vs_currencies=usd");
        $result = json_decode($json, true);
           
        foreach ($result as $crytoName => $crytoData) {
               
            if (in_array ((string)$crytoName,$acceptCoinType)){
                $coinCeckgoAry[$crytoName] =  $crytoData["usd"];
            }
        } unset($json, $result);

        ## get from coincap if coinmarket and coingecko array is empty

        $json = file_get_contents("https://api.coincap.io/v2/assets?ids=".implode(",", $acceptCoinType));
        $result = json_decode($json, true);
       
        foreach ($result as $crytoKey => $crytoDataAry) {

            foreach ($crytoDataAry as $key => $crytoData) {
                $crytoCapName = $crytoData["id"];
                $priceUsd =  $crytoData["priceUsd"];

                if (in_array ((string)$crytoCapName,$acceptCoinType)){

                     $coinCapAry[$crytoCapName] =  $priceUsd;
                }
            }
        } unset($json, $result);

        foreach ($acceptCoinType as $coinType) {
            $a = $coinMarketAry[$coinType];
            $b = $coinCeckgoAry[$coinType];
            $c = $coinCapAry[$coinType];
            
            echo $currentDT." ".$coinType." mkt: ".$a." kgo:".$b." cap:".$c."\n";
            ## compare 3 value , get the middle one
            ## https://stackoverflow.com/questions/1582356/fastest-way-of-finding-the-middle-value-of-a-triple
            $finalCoinRate[$coinType] = max(min($a,$b), min(max($a,$b),$c));
        }

        unset($convertData);
        foreach ($finalCoinRate as $coinKey => $coinPrice) {

                $price = number_format($coinPrice, 8, ".", "");

                if($price && $price > 0){
                    $coinRateData = array(
                        "type"          => CryptoPG::getCryptoConverter($coinKey)['coinRatePrefix'],
                        "rate"          => $price,
                        "created_on"    => $currentDT
                    );
                   
                    $db->insert("mlm_coin_rate", $coinRateData);

                    $temp['name'] = $coinKey;
                    $temp['rate'] = $price;
                    $convertData["coinRate"][] = $temp;
                }
        }

        $latestCoinRateArray = array();
        $tempTable = "(SELECT b.type,MAX(b.created_on) AS created_on FROM mlm_coin_rate b WHERE b.type IN ('".implode("','",$acceptCoinType)."') GROUP BY b.type) AS latest_coin_rate";
        $db->where('a.type = latest_coin_rate.type');
        $db->where('a.created_on = latest_coin_rate.created_on');
        $db->groupBy('a.type');
        $res = $db->get("mlm_coin_rate a,$tempTable",null,'a.type,a.rate');
        foreach ($res as $row) {
            $latestCoinRateArray['coinRate'][] = array(
                'name' => $row['type'],
                'rate' => $row['rate']
            );
        }

        $fileLocation = __DIR__.'/../coinRateData/coinRate.json';
        file_put_contents($fileLocation, json_encode($latestCoinRateArray));

        if($config['frontendServerIP'] && $config['frontendServerIP'] != '127.0.0.1'){
            $cmd = "scp ".$fileLocation." root@".$config['frontendServerIP'].":".$config['frontendPath']."/member/coinRateData";
            // test/live
        }else{
            $cmd = "cp ".$fileLocation." ".$config['frontendPath']."/member/coinRateData";
        } // local
        
        exec($cmd, $output, $result);

//      sleep(60);
//  }