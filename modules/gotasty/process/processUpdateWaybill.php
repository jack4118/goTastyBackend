<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');

    log::setupLogPath(__DIR__, __FILE__);

    $language = "english";
    $db = MysqliDb::getInstance();
    General::$translations = $translations;
    General::$currentLanguage = $language;
    $dateTime = date('Y-m-d H:i:s');
    // ONDELIVERY
    $onDeliveryPath = Setting::$systemSetting["onTrackingWaybill"];
    $onDeliveryAuthKey = Setting::$configArray["onDeliveryAuthKey"];
    $onDeliveryURL = Setting::$configArray["onDeliveryURL"].$onDeliveryPath;

    $validStatus = array('Pending', 'PICKED UP', 'PACKED', 'DEPARTURE', 'ARRIVAL PROCESS', 'ARRIVAL', 'DELIVERY DEPEND', 'DELIVERY');

    Log::write(date("Y-m-d H:i:s")." Start checking waybill needed for update.\n");

    $db->where('courier_company', 'ONDELIVERY');
    $invOrderIDAry = $db->map('id')->get('inv_order', null, 'id');

    if($invOrderIDAry){
        $db->where('inv_order_id', $invOrderIDAry, 'IN');
        $db->where('status', $validStatus, 'IN');
        $invDeliveryOrderRes = $db->map('id')->get('inv_delivery_order', null, 'id, tracking_number, status');
    }

    if(!$invDeliveryOrderRes){
        Log::write(date("Y-m-d H:i:s")." No waybill needed for update.\n");
        exit;
    }

    foreach ($invDeliveryOrderRes as $invDeliveryOrderRow) {
        $trackingNoAry[] = $invDeliveryOrderRow['tracking_number'];
    }

    // ONDELIVERY Get Result
    $onParams = array(
        "waybill_numbers" => $trackingNoAry,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $onDeliveryURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($onParams)); // $response->setBody()
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: '.$onDeliveryAuthKey));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $onDeliveryJsonResponse = curl_exec($ch);
    curl_close($ch);
    $onDeliveryResult = json_decode($onDeliveryJsonResponse, 1);

    foreach ($onDeliveryResult as $onDeliveryDetail) {
        $trackingNo = $onDeliveryDetail['waybill_number'];
        $trackingDetail = $onDeliveryDetail['tracking_details'];
        krsort($trackingDetail);

        foreach ($trackingDetail as $detailRow) {
            $updateData = array(
                'status' => $detailRow['category'],
            );
            $db->where('tracking_number', $trackingNo);
            $db->update('inv_delivery_order', $updateData);

            Log::write(date("Y-m-d H:i:s")." Updating ".$trackingNo." waybill status.\n");
            break;
        }
    }

    Log::write(date("Y-m-d H:i:s")." Done update.\n");
?>