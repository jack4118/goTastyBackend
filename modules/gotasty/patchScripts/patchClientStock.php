<?php
    $currentPath = __DIR__;

    include_once($currentPath."/../include/classlib.php");
    include_once($currentPath."/../include/class.lang_all.php");

    General::$currentLanguage = "english";
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    echo "start patch client stock.\n";

    $dateTime = date("Y-m-d H:i:s");

    $referenceNumber = array("INV22/FIZ/03/0013");
    $doNumber = array("22/DO/FIZ/03/0030");

    $db->where("reference_number",$referenceNumber,"IN");
    $orderIDAry = $db->getValue("inv_order","id",null);

    if(empty($orderIDAry)){
        echo "no records found.\n";
        exit;
    }

    if($orderIDAry){
        $db->where("id",$orderIDAry,"IN");
        $orderDataAry = $db->map("id")->get("inv_order",null,"id,client_id,created_at,batch_id,delivery_option");

        $db->where("inv_order_id",$orderIDAry,"IN");
        $orderDetAry = $db->get("inv_order_detail",null,"inv_order_id,mlm_product_id,inv_product_id,price,pv_price,weight,quantity,stock_quantity,left_stock_quantity");

        unset($orderPackAry);
        unset($packageIDAry);
        unset($packgeDetARy);

        foreach($orderDetAry as $orderDetRow){
            $orderPackAry[$orderDetRow["inv_order_id"]][$orderDetRow["mlm_product_id"]][$orderDetRow["inv_product_id"]] = $orderDetRow["inv_product_id"];
            $packageIDAry[$orderDetRow["mlm_product_id"]] = $orderDetRow["mlm_product_id"];
            $packgeDetARy[$orderDetRow["inv_order_id"]][$orderDetRow["mlm_product_id"]] = $orderDetRow;
        }

        if($packageIDAry){
            $db->where("value",$packageIDAry,"IN");
            $productDetAry = $db->get("inv_product_detail",null,"inv_product_id,value");

            unset($productDataAry);

            foreach($productDetAry as $productDetRow){
                $productDataAry[$productDetRow["value"]][$productDetRow["inv_product_id"]] = $productDetRow["inv_product_id"];
            }
        }

        $db->where("inv_order_id",$orderIDAry,"IN");
        $db->where("reference_number",$doNumber,"IN");
        $deliveryOrderAry = $db->get("inv_delivery_order",null,"inv_order_id,id");

        unset($deliveryOrderDataAry);
        unset($deliveryOrderIDAry);
        unset($orderDeliveryIDAry);

        foreach($deliveryOrderAry as $deliveryOrderRow){
            $deliveryOrderDataAry[$deliveryOrderRow["inv_order_id"]][$deliveryOrderRow["id"]] = $deliveryOrderRow["id"];
            $deliveryOrderIDAry[$deliveryOrderRow["id"]] = $deliveryOrderRow["id"];
            $orderDeliveryIDAry[$deliveryOrderRow["id"]] = $deliveryOrderRow["inv_order_id"];
        }

        if($deliveryOrderIDAry){
            $db->where("inv_delivery_order_id",$deliveryOrderIDAry,"IN");
            $deliveryOrderDetAry = $db->get("inv_delivery_order_detail",null,"inv_delivery_order_id,mlm_product_id,inv_product_id,inv_stock_id,quantity");

            foreach($deliveryOrderDetAry as $deliveryOrderDetRow){
                $deliveryOrderDetDataAry[$orderDeliveryIDAry[$deliveryOrderDetRow["inv_delivery_order_id"]]][$deliveryOrderDetRow["mlm_product_id"]][$deliveryOrderDetRow["inv_product_id"]] = $deliveryOrderDetRow["inv_product_id"];
            }
        }

        foreach($orderPackAry as $orderID => $orderPackRow){
            $clientID = $orderDataAry[$orderID]["client_id"];
            $batchID = $orderDataAry[$orderID]["batch_id"];
            $deliveryOption = $orderDataAry[$orderID]["delivery_option"];
            $deliveryBatchID = $db->getNewID();

            foreach($orderPackRow as $packageID => $productIDAry){
                $packagePrice = $packgeDetARy[$orderID][$packageID]["price"];
                $packagePvPrice = $packgeDetARy[$orderID][$packageID]["pv_price"];
                $packageWeight = $packgeDetARy[$orderID][$packageID]["weight"];
                $packageQty = $packgeDetARy[$orderID][$packageID]["quantity"];
                $productStockQty = $packgeDetARy[$orderID][$packageID]["stock_quantity"];
                $productLeftStockQty = $packgeDetARy[$orderID][$packageID]["left_stock_quantity"];
                $checkProductAry = $productDataAry[$packageID];

                foreach($checkProductAry as $checkProductID){
                    if(!$productIDAry[$checkProductID]){
                        echo "insert order detail.\n";
                        unset($insertData);
                        $insertData = array(
                            "inv_order_id" => $orderID,
                            "mlm_product_id" => $packageID,
                            "inv_product_id" => $checkProductID,
                            "price" => $packagePrice,
                            "pv_price" => $packagePvPrice,
                            "weight" => $packageWeight,
                            "quantity" => $packageQty,
                            "stock_quantity" => $productStockQty,
                            "left_stock_quantity" => $productLeftStockQty,
                        );
                        $db->insert("inv_order_detail",$insertData);
                        print_r($insertData);
                        echo "done insert order detail.\n";

                        echo "insert product transaction.\n";
                        unset($productInvAry);
                        $productInvAry[$checkProductID] = $productStockQty;
                        Inventory::insertInvProductTransaction($productInvAry,$clientID,"Buy Product",$packageQty,$data,$batchID);
                        echo "done insert product transaction.\n";

                        echo "insert delivery order.\n";

                        if(!($deliveryOrderDetDataAry[$orderID][$packgeID][$checkProductID])){
                            $db->where("inv_product_id",$checkProductID);
                            $db->having("SUM(stock_in - stock_out)",0,">");
                            $db->orderBy("id","ASC");
                            $invStockID = $db->getValue("inv_stock","id");

                            if(!$invStockID){
                                echo "no stock to deduct.\n";
                                continue;
                            }

                            unset($insertData);
                            $insertData = array(
                                "inv_delivery_order_id" => array_values($deliveryOrderDataAry[$orderID])[0],
                                "mlm_product_id" => $packageID,
                                "inv_product_id" => $checkProductID,
                                "inv_stock_id" => $invStockID,
                                "quantity" => $productStockQty,
                            );
                            $db->insert("inv_delivery_order_detail",$insertData);
                            print_r($insertData);
                            echo "done insert delivery order.\n";

                            echo "insert delivery order transaction.\n";
                            unset($doProductAry);
                            $doProductAry[$packageID][$checkProductID] = $productStockQty;
                            Inventory::insertInvStockTransaction($doProductAry,$clientID,"Issue DO","",$deliveryBatchID,"",$deliveryOption);
                            echo "done insert delivery order transaction.\n";
                        }
                    }
                }
            }
        }
    }

    echo "done patched.\n";
?>