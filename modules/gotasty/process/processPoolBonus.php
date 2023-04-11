<?php

    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');


    log::setupLogPath(__DIR__, __FILE__);

    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;
    $bonusDate  = date("Y-m-d", strtotime("-1 day"));
    $currentDate = date("Y-m-d");

    if ($argv[1]) {
        // If a bonus date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $bonusDate = $argv[1];
            $currentDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
        }
    }
    General::insertDailyTable("acc_credit",null,$currentDate);

    Bonus::cacheTable('tree_sponsor', $bonusDate);
    Bonus::bonusPreset($bonusDate);

    Log::write(date("Y-m-d H:i:s")." Start Running Monthly Pool Bonus.\n");
    Bonus::payMthPoolBonus($bonusDate);
    Log::write(date("Y-m-d H:i:s")." Finish Monthly Pool Bonus.\n");
?>
