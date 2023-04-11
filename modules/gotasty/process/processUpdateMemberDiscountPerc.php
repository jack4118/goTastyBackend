<?php

    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');
    log::setupLogPath(__DIR__, __FILE__);

    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;

    $searchMonth = date('n',strtotime("-1 days"));
    $searchYear = date('Y',strtotime("-1 days"));
    $searchDateTime = date('Y-m-d H:i:s',strtotime("-1 days"));
    $dateTime = date('Y-m-d H:i:s');
    $date = date('Y-m-d');

    Log::write(date("Y-m-d H:i:s")." Start update discount percentage.\n");

    $clientRankArr = Bonus::getClientRank("Bonus Tier", "", "", "discount");

    foreach ($clientRankArr as $key => $value) {
        $clientIDAry[$key] = $key;
    }

    $db->where('MONTH(created_at)', $searchMonth);
    $db->where('YEAR(created_at)', $searchYear);
    $db->groupBy('client_id');
    $bonusThisMonth = $db->map('client_id')->get('mlm_client_portfolio', null, 'client_id, sum(bonus_value) as bonus_value');

    foreach ($clientRankArr as $key => $value) {
        // only check fizEntreprenuer, fizExecutive, fizDirector, fizUnicorn
        if($value['rank_id'] >= 2){
            unset($supposePerc);
            // check 300 PVP
            $pvp = $bonusThisMonth[$key];
            if($pvp >= 300){
                $supposePerc = 30;
            } else {
                $supposePerc = 25;
            }

            if($supposePerc){
                if($value['percentage'] != $supposePerc){
                    //insert new
                    $insertClientRank = array(
                        'client_id'  => $key,
                        'name'       => "discountPercentage", // rank_setting (name) 
                        'rank_id'    => $value['rank_id'],
                        'value'      => $supposePerc, // rank_setting (value)  
                        'rank_type'  => "Bonus Tier",
                        'type'       => 'System', // rank_setting (type) 
                        'created_at' => $dateTime,
                    );
                    $db->insert('client_rank', $insertClientRank); 

                    Log::write(date("Y-m-d H:i:s")." Update ".$key." disc perc from ".$value['percentage']." to ".$supposePerc.".\n");
                }
            }
        }
    }

    Log::write(date("Y-m-d H:i:s")." Finish update discount percentage.\n");
?>