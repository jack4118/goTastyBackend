<?php
	include_once('../include/classlib.php');
    include_once('../language/lang_all.php');
	log::setupLogPath(__DIR__, __FILE__);

	$language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;

    echo "Start patch\n";

    $db->where("type", "Client");
    $db->where("username", "director", "!=");
    $clientIDRes = $db->getValue("client", "id", NULL);

    foreach($clientIDRes as $clientID){
        $sponsorCode = General::generateSponsorCode();

        $db->where('id', $clientID);
    	$db->update('client', array('sponsor_code' => $sponsorCode));
    }

	echo "Done patch\n";
?>