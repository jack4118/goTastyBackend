<?php
    
    /**
     * Script to clean up unnecessary tables or tables that have passed their lifetime.
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.log.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $log = new Log($logPath, $logBaseName);
    
    $dbHost = $config['dBHost'];
    $databaseName = $config['dB'];
    $user = $config['dBUser'];
    $password = $config['dBPassword'];
    $todayDate = date('Ymd');

    //get backup path
    $backupPath = $setting->systemSetting['backupPath'];

    //get daily table name
    $db->where('disabled', 0);
    $result = $db->get('cleanup_table', null , "table_name, table_type, days, backup");


    foreach ($result as $row) {
        $day = $row["days"];
        $tableType = $row["table_type"];

        if(!$day) {
            echo date("Y-m-d H:i:s")." Days is not set, continue to next result.\n";
            continue;
        }
        if(!$tableType) {
            echo date("Y-m-d H:i:s")." Table type is not set, continue to next result.\n";
            continue;
        }

        $dropTables = array();
        $tblName = $row["table_name"];

        if($tableType == "daily table"){
            $tblName = $tblName."_";
            $lastDate = date("Ymd", strtotime("-".$day." day"));

            $result = $db->rawQuery('SHOW TABLES LIKE "'.$db->escape($tblName).'%"');
            // Get daily table_name
            foreach ($result as $array) {
                foreach ($array as $key => $val) {

                    $tblDate = str_replace($tblName, "", $val);

                    if(strtotime($tblDate) <= strtotime($lastDate)){
                        $dropTables[] = $val;
                    }
                }
            }

            if($dropTables){
                foreach ($dropTables as $key => $backupTable) {

                    if($row["backup"] == 1){
                        // If backup flag is turned on, we backup the table before dropping it
                        echo date("Y-m-d H:i:s")." Backing up $backupTable before DROP.\n\n";
                        $backupName = "$databaseName.$backupTable.sql";
                        $command = "/usr/bin/mysqldump --skip-lock-tables -u$user -p$password $databaseName ".$backupTable." > $backupPath$backupName";
                        exec($command);
                    }
                    
                    echo date("Y-m-d H:i:s")." DROPPING $backupTable now.\n";

                    $result = $db->rawQuery('DROP TABLE IF EXISTS '.$db->escape($backupTable).' ');
            
                }
            }
        }
        else if ($tableType == "single table"){

            $stEndDate = date("Y-m-d 23:59:59", strtotime("-".$day." day"));

            echo date("Y-m-d H:i:s")." Deleting records before $stEndDate\n";

            $db->where('created_at', $stEndDate, '<=');
            $db->delete($tblName);
            
        }
       
    }


?>
