<?php
    
    /**
     * Script to update frontend and backend server health and notify when it reaches certain threshold
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.webservice.php');
    include($currentPath.'/../include/class.message.php');

    $db         = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $webservice = new Webservice();
    $message    = new Message();

    Webservice::generateWebServiceSummary();
?>
