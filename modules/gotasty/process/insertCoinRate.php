<?php

    include_once(__DIR__.'/../include/config.php');
    include_once(__DIR__.'/../include/class.database.php');
    include_once(__DIR__.'/../include/class.CreateXML.php');
    include_once(__DIR__.'/../include/class.setting.php');
    include_once(__DIR__.'/../include/class.CreateXML.php');

    $db             = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting        = new Setting($db);
    $createXML      = new CreateXML();
    // $createXML = new CreateXML();

    // $json = file_get_contents("https://api.bithumb.com/public/recent_ticker");
    // $coinDetail = json_decode ($json);
    // $finalRate = ($coinDetail->data->last*0.00093536637470626);
    // if($finalRate != ""){
    //     $fields = array("ID","type", "rate", "createdOn");
    //     $values = array("", "bitcoin", number_format($finalRate, 2, ".", ""), date("Y-m-d H:i:s"));
    //     $db->dbInsert("mlmCoinRate", $fields, $values);

    //     $res = $db->dbSql("SELECT ID FROM mlmSetting Where name = 'bitcoin Latest Rate'");
    //      if($db->dbFetchRow($res)){
    //         $row = $db->dbRow["mlmSetting"];

    //         $fields = array("value");
    //         $values = array(number_format($finalRate, 2, ".", ""));
    //         $db->dbUpdate("mlmSetting", $fields, $values,"ID='".mysql_escape_string($row["ID"])."'");
    //     }else{
    //         $fields = array("ID","name", "value", "type");
    //         $values = array("", "bitcoin Latest Rate",  number_format($finalRate, 2, ".", ""), "liveCoinRate");
    //         $db->dbInsert("mlmSetting", $fields, $values);
    //     }
    // }
while(1){
    $acceptCoinType = json_decode($setting->systemSetting['acceptCoinType']);
    $json = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
    $coinDetail = json_decode ($json);
    $currentDT = date("Y-m-d H:i:s"); // prevent jump second.

    foreach ($coinDetail as $value) {

        if (in_array ((string)$value->id,$acceptCoinType)){
            unset($fields);
            unset($values);
            if($value->price_usd){

                $price = str_replace(',', '', $value->price_usd);
                
                // if((string)$value->id == "tether") $price = 1;
                $coinRateData = array(
                    "type"          => $value->id,
                    "rate"          => number_format($price, $setting->systemSetting['systemDecimalFormat'], ".", ""),
                    "created_on"    => $currentDT
                );


                $db->insert("mlm_coin_rate", $coinRateData);


                $db->where("name", $value->id.' Latest Rate');
             
                $result = $db->getOne("system_settings", "id");

                 if($result){
     
                    $data = array('value' => number_format($price, $setting->systemSetting['systemDecimalFormat'], ".", ""));
                    $db->where('id', $result["id"]);
                    $db->update("system_settings", $data);

                }else{
                    $data = array(
                        'name' => $value->id." Latest Rate",
                        'value' => number_format($price, $setting->systemSetting['systemDecimalFormat'], ".", ""),
                        'type' => "liveCoinRate",
                        'description' => "Insert live coin rate from coinmarketcap",
                        'module' => "MLM Platform"
                    );
                    $db->insert("system_settings", $data);
                }

            }

        }
      
    }


    ## get from coinceckgo if coinmarket array is empty

    if(empty($coinDetail)){
        $json = file_get_contents("https://api.coingecko.com/api/v3/simple/price?ids=tether,bitcoin,ethereum,ripple,eos,cardano&vs_currencies=usd");

        $coinDetail = json_decode($json, true);
        // $acceptCoinType = json_decode($setting->systemSetting['acceptCoinType']);
     
  
        foreach ($coinDetail as $crytoName => $crytoData) {

        if (in_array ((string)$crytoName,$acceptCoinType)){
                    
                if($crytoData["usd"] && $crytoData["usd"] > 0){

                        $usdPrice = number_format($crytoData["usd"], $setting->systemSetting['systemDecimalFormat'], ".", "");

                        
                        $coinRateData = array(
                            "type"          => $crytoName,
                            "rate"          => number_format($usdPrice, $setting->systemSetting['systemDecimalFormat'], ".", ""),
                            "created_on"    => date('Y-m-d H:i:s'),
                        );

                        $db->insert("mlm_coin_rate", $coinRateData);


                        $db->where("name", $crytoName.' Latest Rate');
                
                        $result = $db->getOne("system_settings", "id");

                        if($result){
             
                            $data = array('value' => number_format($usdPrice, $setting->systemSetting['systemDecimalFormat'], ".", ""));
                            $db->where('id', $result["id"]);
                            $db->update("system_settings", $data);
       
                         }else{
                            $data = array(
                                'name' => $crytoName." Latest Rate",
                                'value' => number_format($usdPrice, $setting->systemSetting['systemDecimalFormat'], ".", ""),
                                'type' => "liveCoinRate",
                                'description' => "Insert live coin rate from coinmarketcap",
                                'module' => "MLM Platform"
                        );
                        $db->insert("system_settings", $data);

                  
                }

                }
            }
        }
    }

    ## get from coincap if coinmarket and coingecko array is empty

    if(empty($coinDetail)){
        $json = file_get_contents("https://api.coincap.io/v2/assets?ids=tether,bitcoin,ethereum,ripple,eos,cardano");

        $jsonData = json_decode($json, true);
        // $acceptCoinType = json_decode($setting->systemSetting['acceptCoinType']);
   
        foreach ($jsonData as $crytoKey => $crytoDataAry) {

            foreach ($crytoDataAry as $key => $crytoData) {
                    $crytoName = $crytoData["id"];
                    $priceUsd =  $crytoData["priceUsd"];

             if (in_array ((string)$crytoName,$acceptCoinType)){
                    
                if($priceUsd && $priceUsd > 0){

                        $usdPrice = number_format($priceUsd, $setting->systemSetting['systemDecimalFormat'], ".", "");

                        
                        $coinRateData = array(
                            "type"          => $crytoName,
                            "rate"          => number_format($usdPrice, $setting->systemSetting['systemDecimalFormat'], ".", ""),
                            "created_on"    => date('Y-m-d H:i:s'),
                        );

                        $db->insert("mlm_coin_rate", $coinRateData);


                        $db->where("name", $crytoName.' Latest Rate');
                
                        $result = $db->getOne("system_settings", "id");

                        if($result){
             
                            $data = array('value' => number_format($usdPrice, $setting->systemSetting['systemDecimalFormat'], ".", ""));
                            $db->where('id', $result["id"]);
                            $db->update("system_settings", $data);
       
                         }else{
                            $data = array(
                                'name' => $crytoName." Latest Rate",
                                'value' => number_format($usdPrice, $setting->systemSetting['systemDecimalFormat'], ".", ""),
                                'type' => "liveCoinRate",
                                'description' => "Insert live coin rate from coinmarketcap",
                                'module' => "MLM Platform"
                        );
                        $db->insert("system_settings", $data);

                  
                }

                 }
                }

            }
        }
    }

    unset($coinDetail);

    ## Insert czo credit price like coin rate
    // $db->where('name', 'czoBasePrice');
    // $czoRate = $db->getValue('system_settings','value');
    // $coinRateData = array(
    //     "type"          => "czoCredit",
    //     "rate"    => $czoRate,
    //     "created_on"     => date("Y-m-d H:i:s")
    // );
    // $db->insert("mlm_coin_rate", $coinRateData);

    ## Get Latest Coin Rate (coinRate.xml)
    $db->where('type', "liveCoinRate");
    $res = $db->get("system_settings", NULL, array("name", "value"));
   	// print_r($res);

    // $czoData = array('name' => "czoCredit latest Rate", "value" => number_format($czoRate, 8, '.', ''));
    // $res[] = $czoData;

    $createXML->generateXML($res, "coinRate");

    ## Generate czo graph data
    // $db->where('type', "czoCredit");
    // $db->orderBy('created_on', "ASC");
    // $res = $db->get("mlm_coin_rate", null, "date_format(created_on, '%d/%m/%Y %H:%i:%s') as rateDate, rate");
    // $res = $db->get("mlm_coin_rate", null, "created_on as rateDate, rate");
	// $createXML->generateXML($res, "czoGraph");

    // $cmd = "scp /var/www/barlingsCapitalBackend/coinRateData/coinRate.xml root@101.100.201.196:/var/www/barlingsCapitalMember/coinRateData/";
    $db->where('name', array('isLocalhost','memberLanguagePath','frontendServerIP'), "IN");
    $res = $db->get("system_settings", null, "value, name");
    foreach ($res as $key => $value) {
        if($value['name'] == 'isLocalhost')
            $isLocalhost = $value['value'];
        if($value['name'] == 'memberLanguagePath')
            $memberPlace = str_replace('language', 'coinRateData/.', $value['value']);
        if($value['name'] == 'frontendServerIP')
            $frontendServerIP = $value['value'];
    }

    if($isLocalhost){
       $cmd = "cp -r ".__DIR__."/../coinRateData/* ".$memberPlace;
    }else{
	   $cmd = "scp -r ".__DIR__."/../coinRateData/* root@".$frontendServerIP.":".$memberPlace;
    }

	// $cmd = "scp -r /var/www/cryptzoProjectTEST/backend/modules/mlmPlatform/coinRateData/* root@testfront:/var/www/cryptzoProjectTEST/member/coinRateData/";

    // $cmd = "scp -r coinRateData/ ../../../member/coinRateData";

    $result = exec($cmd);
    if(is_null($result)) echo "havent copy";

    sleep(30);
}


?>