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

    while(true){
    
        Setting::setupSysSetting($config);
        $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

        foreach ($walletType as $coinType) {
            unset($latestRow);
            unset($allRow);
            unset($summaryRow);
            unset($buyRow);
            unset($buyAry);
            unset($sellRow);
            unset($sellAry);
            unset($matchRow);
            unset($matchAry);
            unset($trdData);
            
            //send latest data to socket
            $result = Trading::getStocks($interval, $tableName, $coinType);
            $latestRow = $result["latestRow"];
            $allRow = $result["allRow"];

            if($latestRow){
                $latestRow["category"] = "trading";
                $latestRow["type"] = "graph";
                $latestRow["time"] = $type;
                Trading::sendTrdDataToSocket($latestRow);
            }
            
            //scp json to frontend
            $graphDataPath = realpath(dirname(__FILE__))."/../json/";
            file_put_contents($graphDataPath."trdGraph".$type.".json", json_encode($allRow));

            if ($config['isLocalhost']) {
                $cmd = "cp ".$graphDataPath."trdGraph".$type.".json ".$config['memberJsonFilePath'];
                exec($cmd, $output, $result);
            } elseif ($config['frontendServerIP']) {
                $cmd = "scp ".$graphDataPath."trdGraph".$type.".json root@".$config['frontendServerIP'].":".$config['memberJsonFilePath'];
                exec($cmd, $output, $result);
            }

            if($type == "1m"){

                $trdData["category"] = "trading";
                $trdData["type"] = "trdData";
                //trading summary
                /*$db->where("coin_type", $coinType);
                $summaryRow = $db->getOne("trd_trading_summary", "high, low, open, close, volume, `change`, change_percentage as changePercentage");*/

                $currentDateTime = date("Y-m-d H:i:s");
                $past24HourDateTime = date("Y-m-d H:i:s",strtotime("-1 day ".$currentDateTime));
                $db->where('created_at',$past24HourDateTime,'>=');
                $db->where('created_at',$currentDateTime,'<=');
                $db->where("coin_type", $coinType);
                $db->orderBy('created_at','DESC');
                $db->orderBy('id','DESC');
                $latestTradeRow = $db->getOne('trd_latest_trade','MAX(price) AS highest_price,MIN(price) AS lowest_price,SUM(quantity) AS quantity');
                $summaryRow["high"] = $latestTradeRow["highest_price"] ? Setting::setDecimal($latestTradeRow["highest_price"]) : 0;;
                $summaryRow["low"] = $latestTradeRow["lowest_price"] ? Setting::setDecimal($latestTradeRow["lowest_price"]) : 0;
                $summaryRow["quantity"] = $latestTradeRow["quantity"] ? Setting::setDecimal($latestTradeRow["quantity"]) : 0;

                $db->where('coin_type',$coinType);
                $db->where('created_at',$past24HourDateTime,'<=');
                $db->orderBy('created_at','DESC');
                $db->orderBy('id','DESC');
                $past24HourLastPrice = $db->getValue('trd_latest_trade','price');

                $db->where('coin_type',$coinType);
                $db->orderBy('created_at','DESC');
                $db->orderBy('id','DESC');
                $lastPrice = $db->getValue('trd_latest_trade','price');
                $summaryRow["lastPrice"] = $lastPrice ? Setting::setDecimal($lastPrice) : General::getLatestUnitPrice($coinType);

                $summaryRow["changePrice"] = $past24HourLastPrice ? Setting::setDecimal((($lastPrice-$past24HourLastPrice)/$past24HourLastPrice)) : 0;
                $summaryRow["changePricePercentage"] = $past24HourLastPrice ? Setting::setDecimal(($summaryRow["changePrice"]*100), 2) : 0;

                if($summaryRow["changePrice"] >= 0){
                    $summaryRow["isIncFlag"] = 1;
                }else{
                    $summaryRow["isIncFlag"] = 0;
                }
                $trdData["trdSummary"] = $summaryRow;

                $limit = Setting::$systemSetting["displayOrderLimit"] ? Setting::$systemSetting["displayOrderLimit"] : 5;

                //orderbook - buy
                $db->where("coin_type", $coinType);
                // $db->groupBy("price");
                $db->orderBy("price", "DESC");
                // $buyRes = $db->get("trd_buy_queue", $limit, "price, SUM(quantity) as quantity");
                $buyRes = $db->get("trd_buy_queue", $limit, "price, quantity");
                foreach ($buyRes as $buyRow) {
                    $buyRow["price"] = Setting::setDecimal($buyRow["price"]);
                    $buyRow["quantity"] = Setting::setDecimal($buyRow["quantity"]);
                    $buyAry[] = $buyRow;
                }
                $trdData["trdBuy"] = $buyAry;

                //orderbook - sell
                $db->where("coin_type", $coinType);
                // $db->groupBy("price");
                $db->orderBy("price", "ASC");
                // $sellRes = $db->get("trd_sell_queue", $limit, "price, SUM(quantity) as quantity");
                $sellRes = $db->get("trd_sell_queue", $limit, "price, quantity");
                foreach ($sellRes as $sellRow) {
                    $sellRow["price"] = Setting::setDecimal($sellRow["price"]);
                    $sellRow["quantity"] = Setting::setDecimal($sellRow["quantity"]);
                    $sellAry[] = $sellRow;
                }
                $trdData["trdSell"] = $sellAry;

                //market trade - match
                $previousPrice = 0;
                $incFlag = '-';
                $db->where("coin_type", $coinType);
                $db->orderBy("created_at", "DESC");
                $db->orderBy("id", "DESC");
                $matchRes = $db->get("trd_match_transaction", $limit, "price, quantity, created_at as date");
                foreach ($matchRes as $matchRow) {

                    if ($matchRow['price'] > $previousPrice) {
                        $incFlag = 1;
                    }elseif ($matchRow['price'] < $previousPrice) {
                        $incFlag = 0;
                    }
                    $matchRow["incFlag"] = $incFlag;
                    $matchRow["price"] = Setting::setDecimal($matchRow["price"]);
                    $matchRow["quantity"] = Setting::setDecimal($matchRow["quantity"]);
                    $matchRow["date"] = date($dateTimeFormat, strtotime($matchRow["date"]));
                    $matchAry[] = $matchRow;

                    $previousPrice = $matchRow['price'];

                }
                $trdData["trdMarket"] = $matchAry;
                Trading::sendTrdDataToSocket($trdData);
            }
        }

        usleep(1000);

    }


?>
