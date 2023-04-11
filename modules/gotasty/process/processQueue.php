<?php

	$currentPath = __DIR__;
    include_once($currentPath . '/../include/classlib.php');
    include_once($currentPath . '/../language/lang_all.php');

    
    if (!$argv[1]) {
        Log::write(date("Y-m-d H:i:s")." - Invalid Argv type.\n");
        exit;
    }
    $logBaseName     = basename(__FILE__, '.php');

    log::setupLogPath(__DIR__, $logBaseName."-".$argv[1]);

    $db->where('name','processQueueSwitch');
    $swtichStg = $db->getOne('system_settings','id,value');
    $switchStgID = $swtichStg['id'];
    $switchValue = $swtichStg['value'];

    while($switchValue){
        $curDate = date('Y-m-d');

        switch ($argv[1]) {
            case 'calculateRank':
                $db->where('name','processBonusSwitch');
                $stgRes = $db->getOne('system_settings','id,value');
                $processBonusSwitch = $stgRes['value'];
                $stgID = $stgRes['id'];
                while ($processBonusSwitch) {
                    Log::write(date('Y-m-d H:i:s')." Waiting for Bonus Process...\n");

                    $db->where('name','waitQueueFlag');
                    $waitQueueRes = $db->getOne('system_settings','value,reference');
                    $waitQueueFlag = $waitQueueRes['value'];
                    $curDate = $waitQueueRes['reference'];
                    if($waitQueueFlag) break;

                    $db->where('id',$stgID);
                    $processBonusSwitch = $db->getValue('system_settings','value');

                    sleep(1);
                }

                $db->where("queue_type","calculateRank");
                $db->where('DATE(created_at)',$curDate,"<=");
                $limit = null;
                $sleepTime = 1;
                break;

            case 'autoWhitelistWalletAddress':
                $db->where("queue_type","autoWhitelistWalletAddress");
                $limit = null;
                $sleepTime = 1;
                break;
            
            default:
                Log::write(date("Y-m-d H:i:s")." - Invalid Argv type.\n");
                exit;
                
                break;
        }

    	$db->where("processed", "0");
    	$db->orderBy("created_at", "ASC");
    	$queueRes = $db->get("queue", $limit, "id, queue_type, client_id, data, created_at");

    	foreach ($queueRes as $queueRow) {
    		$queueData = json_decode($queueRow["data"], true);
            $db->where("id", $queueRow["id"]);
            $db->update("queue", array("processed" => 2));
			Log::write(date("Y-m-d H:i:s")." Processing Queue ID - ".$queueRow["id"]."\n");
    		
    		switch ($queueRow["queue_type"]) {
                case 'calculateRank':
                    $dateTime   = $queueData["dateTime"];
                    $moduleType = $queueData["moduleType"];

                    $result = Custom::calculateClientRank($queueRow['client_id'],$dateTime,$moduleType);

                    $db->where("id", $queueRow["id"]);
                    $db->update("queue", array("processed" => 1));
                    break;

                case 'autoWhitelistWalletAddress':
                    $db->where("id", $queueRow["id"]);
                    $db->update("queue", array("processed" => 2));

                    $witelistParams["checkedIDs"] = $queueData['checkedIDs'];
                    $witelistParams["status"] = $queueData['status'];

                    $whitlistRes = Client::updateWalletAddressStatus($witelistParams);

                    $updateQueue = array(
                        "data_in"        => json_encode($witelistParams),
                        "data_out"       => json_encode($whitlistRes),
                        "status"         => $whitlistRes['status'],
                        "error_message"  => $whitlistRes['statusMsg'],
                        "processed" => 1
                    );

                    $db->where("id", $queueRow["id"]);
                    $db->update("queue", $updateQueue);
                    break;

    			default:
    				# code...
    				break;
    		}
			Log::write(date("Y-m-d H:i:s")." Done Queue Type - ".$queueRow["queue_type"]."\n");
    	}

        $db->where('id',$switchStgID);
        $switchValue = $db->getValue('system_settings','value');

    	sleep($sleepTime);
    }
?>