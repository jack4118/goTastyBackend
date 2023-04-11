<?php
    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../include/class.lang_all.php');

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    echo "Start patch\n";
    $db->where('type','Recruit Promo');
    $promoRes = $db->get('mlm_promo',null,'client_id,amount,created_at');
    foreach ($promoRes as $promoRow) {
        $cliendIDArr[$promoRow['client_id']] = $promoRow['client_id'];
    }

    if($cliendIDArr){
        $db->where('id',$cliendIDArr,"IN");
        $countryIDArr = $db->map('id')->get('client',null,'id,country_id');
    }

    foreach ($promoRes as $promoRow) {
        echo date('Y-m-d H:i:s')." Patch Recruit Promo Bonus Report : ".$promoRow['client_id']." Bonus Date : ".date('Y-m-d',strtotime($promoRow['created_at']."- 1 days"))." Amount : ".$promoRow['amount']." \n";

        unset($insertData);
        $insertData = array(
            "client_id" => $promoRow['client_id'],
            "country_id" => $countryIDArr[$promoRow['client_id']],
            "bonus_date" => date('Y-m-d',strtotime($promoRow['created_at']."- 1 days")),
            "bonus_type" => "recruitPromo",
            "bonus_amount" => $promoRow['amount'],
            "paid" => 1,
            "updater_id" => 1000004,
            "updated_at" => "2022-03-08 12:43:49"
        );
        $db->insert('mlm_bonus_report',$insertData);
    }

    echo "Done patch\n";
?>