<?php
    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../include/class.lang_all.php');

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    echo "Start patch\n";
    $dateTime = date('Y-m-d H:i:s');

    $db->where('type','Client');
    $db->orderBy('id','DESC');
    $clientRes = $db->get('client',null,'id,username');
    foreach ($clientRes as $clientRow) {
        $clientID = $clientRow['id'];
        echo date('Y-m-d H:i:s')." Username : ".$clientRow['username']."\n";
        Custom::calculateClientRank($clientID,$dateTime);
    }

    echo "Done patch\n";
?>