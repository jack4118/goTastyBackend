<?php
   	$currentPath = __DIR__;

    include_once("../include/classlib.php");
    include_once("../language/lang_all.php");

    echo "Retrieving all accounts\n\n";

    $clientIDAry = $db->getValue("client","id",null);

    echo "Retrieving credits\n\n";

    $db->orderBy("type","ASC");
    $creditRes = $db->getValue("credit","name",null);

    echo "Start\n\n";

    if(empty($clientIDAry)){
        echo "No clientIDAry\n\n";
        echo "End\n\n";
        exit;
    }

    foreach($clientIDAry as $clientID){
        foreach($creditRes as $credit){
            $balance = Cash::getBalance($clientID,$credit);
            print_r("clientID: $clientID, credit: $credit balance: $balance\n");
        }
    }

    echo "End\n\n";
?>