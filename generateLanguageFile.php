<?php

    include_once('include/config.php');
    include_once('include/class.database.php');
    include_once('include/class.setting.php');
    include_once('include/class.language.php');
    include_once('include/class.general.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General();
    $language = new Language($db, $general, $setting);
    
    //check flag to run
    $db->where("name","autoRunLangCron");
    $autoRun  = $db->getValue("system_settings","value");
    if(!$autoRun && $argv[1]) return false;

    $language->generateLanguageFile();

    $updateData = array("value"=>"0");
    $db->where("name","autoRunLangCron");
    $db->update("system_settings",$updateData);
    
    echo date("Y-m-d H:i:s")." Done generating language file.\n";
?>
