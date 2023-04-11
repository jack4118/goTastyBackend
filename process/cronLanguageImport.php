<?php
    
    /**
     * Script to update/insert language_translations table
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.language.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.general.php');
    include_once($currentPath.'/../include/PHPExcel.php');
    include_once($currentPath.'/../include/PHPExcel/IOFactory.php');

    $db       = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting         = new Setting($db);
    $general         = new General($db, $setting);
    $language = new Language($db, $general, $setting);

    // ######## Cron Function for Language Translations Import. ########
    // $function = $_GET['function'];
    // if($function == "importLanguageTranslations") {
    $msg = $language->importLanguageTranslations();
    
    if($msg != "noFile"){
        echo $msg."\n";
    }
    //}
    // ######## End Cron Job Call. ########
?>
