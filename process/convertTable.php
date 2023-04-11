<?php
    
    $currentPath = __DIR__;
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    echo "Converting tables to use InnoDB Engine...\n";

    $result = $db->rawQuery("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".$config['dB']."' AND ENGINE = 'MyISAM'");

    foreach($result as $array) {
        foreach($array as $v) {
            echo "Converting $v table.\n";
            try {
                $db->rawQuery("ALTER TABLE `".$v."` ENGINE=InnoDB");
            }
            catch (Exception $e) {
                echo "Failed to convert $v table.\n";
                echo "Exception message: ".$e->getMessage()."\n";
            }
        }
    }

    echo "Done.\n";
?>