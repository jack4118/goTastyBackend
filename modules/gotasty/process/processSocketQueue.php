<?php

    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');


    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;
    unset($category);
    unset($socketEnd);
    $category = $argv[1];
    $endTime = $argv[2]." ".$argv[3];
    $socketSwitch = 1;

    $logBaseName = basename(__FILE__, '.php');
    Log::setupLogPath(__DIR__, $logBaseName."-".$category);

    $db->where('category',$category);
    $productIDArr = $db->map('id')->get('mlm_product',null,'id');
    if(!$productIDArr){
        Log::write(date("Y-m-d H:i:s")." Invalid Category.\n");
        exit;
    }

    while($socketSwitch){
        $dateTime = date('Y-m-d H:i:s');
        
        $db->where('queue_type','sendSocket');
        $db->where("processed", "0");
        $db->where('created_at',$endTime,"<=");
        $db->where('product_id',$productIDArr,"IN");
        $db->orderBy("id", "ASC");
        $socketRes = $db->get("queue", null, "id, queue_type, data");
        if(!$socketRes && !$socketEnd){
            $socketEnd = date('Y-m-d H:i:s',strtotime($dateTime." +20 minutes"));
            Log::write(date("Y-m-d H:i:s")." No more vaild queue to process. Add 20 minutes to End process. Socket End Time - ".$socketEnd."\n");
        }

        foreach ($socketRes as $socketRow) {
            Log::write(date("Y-m-d H:i:s")." Start to Send Socket Queue ID - ".$socketRow["id"].".\n");
            $db->where("id", $socketRow["id"]);
            $db->update("queue", array("processed" => "2"));

            General::sendSocketData(json_decode($socketRow["data"]));

            $db->where("id", $socketRow["id"]);
            $db->update("queue", array("processed" => "1"));

            Log::write(date("Y-m-d H:i:s")." Send Socket Queue ID - ".$socketRow["id"]." End.\n");
        }

        if(($socketEnd) && (strtotime($dateTime) >= strtotime($socketEnd))){
            Log::write(date("Y-m-d H:i:s")." End Time : ".$socketEnd." Socket Process End.\n");
            $socketSwitch = 0;
        }
        // usleep(1000);
    }
?>