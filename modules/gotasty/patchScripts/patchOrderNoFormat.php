<?php  echo "Start\n";

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once ($currentPath.'/../language/lang_all.php');
    General::$currentLanguage = 'english';
    General::$translations    = $translations;

    $getInvoiceNSONo = $db->get('inv_order', null, 'id, reference_number, po_number, YEAR(created_at) as year, MONTH(created_at) as month, created_at');
    $getDONo = $db->get('inv_delivery_order', null, 'id, reference_number, YEAR(created_at) as year, MONTH(created_at) as month, created_at');

    $successForInvoiceNSo   = 0;
    $failForInvoiceNSo      = 0;
    $successForDO           = 0;
    $failForDO              = 0;

    $number = 0001;
    foreach($getInvoiceNSONo as $value){
        $getYearMonth = "{$value['year']}{$value['month']}";
        if($record){
            if(!in_array($getYearMonth, $record)){
                $record[]   = $getYearMonth;
                $number     = '0001';
            }
        }else{
            $record[] = $getYearMonth;
        }

        $numberString   = str_pad($number, 4, '0', STR_PAD_LEFT);
        $monthRecord    = str_pad($value['month'], 2, '0', STR_PAD_LEFT);
        $yearRecord     = date('y', strtotime($value['created_at']));

        $updateData     = array(
            "reference_number" => 'INV'.$yearRecord.'/FIZ/'.$monthRecord.'/'.$numberString,
            "po_number" => $yearRecord.'/SO/FIZ/'.$monthRecord.'/'.$numberString,
        );
        $db->where('id', $value['id']);
        $checkUpdate    = $db->update('inv_order', $updateData);

        if($checkUpdate){
            $successForInvoiceNSo++;
            $number++;
        }else{
            $failForInvoiceNSo++;
        }
    }
    unset($getYearMonth);
    unset($record);
    unset($checkUpdate);

    $number = 0001;
    foreach($getDONo as $do){
        $getYearMonth = "{$do['year']}{$do['month']}";
        if($record){
            if(!in_array($getYearMonth, $record)){
                $record[]   = $getYearMonth;
                $number     = '0001';
            }
        }else{
            $record[] = $getYearMonth;
        }

        $numberString   = str_pad($number, 4, '0', STR_PAD_LEFT);   
        $monthRecord    = str_pad($do['month'], 2, '0', STR_PAD_LEFT);
        $yearRecord     = date('y', strtotime($value['created_at']));

        $updateData     = array(
            "reference_number" => $yearRecord.'/DO/FIZ/'.$monthRecord.'/'.$numberString,
        );
        $db->where('id', $do['id']);
        $checkUpdate    = $db->update('inv_delivery_order', $updateData);

        if($checkUpdate){
            $successForDO++;
            $number++;
        }else{
            $failForDO++;
        }        
    }
    unset($getYearMonth);
    unset($record);
    unset($checkUpdate);

    echo "\nSuccess for inv_order table: {$successForInvoiceNSo}\nFail for inv_order table: {$failForInvoiceNSo}\n";
    echo "\nSuccess for inv_delivery_order table: {$successForDO}\nFail for inv_order table: {$failForDO}\n";
    echo "\nChange succefully\n";
    echo "End";
    
?>