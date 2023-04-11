<?php

    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');

    log::setupLogPath(__DIR__, __FILE__);

    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;

    $db = MysqliDb::getInstance();

    $db->where('name','generateLiveSearchJSON');
    $isProcessing = $db->getValue('system_settings','value');

    if (!$isProcessing) {
        $updateData = array('value'=>1);
        $db->where('name','generateLiveSearchJSON');
        $db->update('system_settings',$updateData);

        $jsonFileArray = array();

        /* Username */
        // Get all member username
        $db->where('type','Client');
        $db->where('username','director','!=');
        $db->where('activated',1);
        $usernameArray = $db->getValue('client','username',null);

        $usernameJson = json_encode(array('usernameArray'=>$usernameArray));

        if (file_put_contents(__DIR__."/../json/username.json", $usernameJson))
            Log::write(date("Y-m-d H:i:s")." Username JSON file successfully created.\n");
        else
            Log::write(date("Y-m-d H:i:s")." Failed to create username JSON file.\n");

        $jsonFileArray[] = 'username.json';
        /* Username END */

        /* Copy file to frontend */
        foreach ($jsonFileArray as $jsonFile) {
            if ($config['frontendServerIP'] && $config['frontendServerIP'] != '127.0.0.1' && $config['frontendPath']) {
                $cmd = "scp " . __DIR__ . "/../json/$jsonFile root@" . $config['frontendServerIP'] . ":" . $config['frontendPath'] . "/admin/json/";
            } elseif ($config['frontendPath']) {
                $cmd = "cp " . __DIR__ . "/../json/$jsonFile " . $config['frontendPath'] . "/admin/json/";
            }

            $result = exec($cmd);
            if (is_null($result)) {
                echo "failed to copy: " . $cmd . "\n";
            } else {
                // Excel::updateExcelReqStatus(array("status" => "Success", "progress" => 100), $row['id']);
                Excel::updateExcelReqStatus(array("status" => "Success", "progress" => 100, "end_time" => date("Y-m-d H:i:s")), $row['id']);
            }
        }
        /* Copy file to frontend END */

        $updateData = array('value'=>0);
        $db->where('name','generateLiveSearchJSON');
        $db->update('system_settings',$updateData);
    }