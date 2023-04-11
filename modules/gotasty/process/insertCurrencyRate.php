<?php

    include_once(__DIR__.'/../include/config.php');
    include_once(__DIR__.'/../include/class.database.php');
    include_once(__DIR__.'/../include/class.CreateXML.php');
    include_once(__DIR__.'/../include/class.setting.php');
    // include_once(__DIR__.'/../include/class.CreateXML.php');

    $db             = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting        = new Setting($db);
    $createXML      = new CreateXML();


    // $json = file_get_contents("https://api.exchangeratesapi.io/latest?base=USD");
    $apiID = $config['apiID'];
	$url = "http://openexchangerates.org/api/latest.json?app_id=".$apiID."&base=USD";
    $json = file_get_contents($url);
    $decode = json_decode($json,true);

    $acceptedCurrency = array("CNY","THB","MYR","IDR","INR","VND");
    $db->where("currency_code",$acceptedCurrency,"IN");
    $db->groupBy("currency_code");
    $res = $db->map("currency_code")->get("mlm_currency_exchange_rate",NULL,"currency_code,country_id,priority");

	foreach ($decode['rates'] as $currencyCode => $rate) {
		if(!in_array($currencyCode, $acceptedCurrency)) continue;
		$insertData = array(
							"country_id" => $res[$currencyCode]['country_id'],
							"currency_code" => $currencyCode,
							"exchange_rate" => $rate,
							"buy_rate" => $rate,
							"created_at" => $db->now(),
							"status" => "Active",
							"priority" => $res[$currencyCode]['priority']

						);
		$db->insert("mlm_currency_exchange_rate",$insertData);
	}	
?>