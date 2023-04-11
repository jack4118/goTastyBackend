<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');
    log::setupLogPath(__DIR__, __FILE__);

    $db->where('name','processMaturedPendingPayment');
    $switchStg = $db->getOne('system_settings', 'id, value, reference');
    $switchValue = $switchStg['value'];
    $sleepTime   = $switchStg['reference'];
    $switchID    = $switchStg['id'];
    if($switchValue){
        Log::write($currentDT." Process Start.....\n");
    }

    while($switchValue){
        $currentDT = date("Y-m-d H:i:s"); // prevent jump second.

        unset($idAry);

        $db->startTransaction();

        $db->where('status', 'pending');
        $db->where('expired_at', $currentDT, '<=');
        $maturedpayment = $db->setQueryOption("FOR UPDATE")->get("mlm_pending_payment", null,"id, data, expired_at");

        try{
            foreach ($maturedpayment as $payment) {
                unset($updateStockBalance, $reentryData, $packageAry);
                Log::write("id: ".$payment['id']." expired at: ".$payment['expired_at']."\n");

                $reentryData = json_decode($payment['data'], true);
                $packageAry = $reentryData['packageAry'];
                foreach ($packageAry as $package) {
                    $updateStockBalance = array(
                        'total_holding' => $db->dec($package["quantity"]),
                    );
                    $db->where('id', $package["packageID"]);
                    $db->update('mlm_product', $updateStockBalance);   
                }

                $idAry[$payment['id']] = $payment['id'];
            }

            if($idAry){
                $db->where('id', $idAry, 'IN');
                $db->update("mlm_pending_payment", array("status" => "Matured", 'updated_at' => $currentDT));
            }

        } catch (Exception $e){
            $db->rollback();
            $db->commit();
            Log::write($e);
        }

        $db->commit();

        $db->where('id', $switchID);
        $switchStg = $db->getOne('system_settings','value,reference');
        $switchValue = $switchStg['value'];
        $sleepTime   = $switchStg['reference'];
        if(!$switchValue){
            Log::write($currentDT." Manual Stop Process.\n");
        }else{
            sleep($sleepTime);
        }
    }