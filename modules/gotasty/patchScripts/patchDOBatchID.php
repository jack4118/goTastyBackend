<?php  
    echo "Start\n";
    $currentPath = __DIR__;

    include_once($currentPath."/../include/classlib.php");
    include_once($currentPath."/../include/class.language.php");
    include_once($currentPath."/../include/class.scriptFunction.php");

    General::$currentLanguage = 'english';
    General::$translations    = $translations;

    $language = new Language();
    $function = new scriptFunction();

    $dateTime = date("Y-m-d H:i:s");

    $db->groupBy('batch_id');
    $db->where('subject', 'Issue DO');
    $stockTransaction = $db->map('created_at')->get('inv_stock_transaction', null, 'created_at, batch_id');

    $deliveryOrder = $db->get('inv_delivery_order', null, 'id, created_at');

    foreach ($deliveryOrder as $deliveryOrderRow) {
        $updateData = array(
            'batch_id' => $stockTransaction[$deliveryOrderRow['created_at']],
        );

        $db->where('id', $deliveryOrderRow['id']);
        $db->update('inv_delivery_order', $updateData);
    }

    echo "Done patch back batch ID.";
    echo "\nEnd\n"; 
?>