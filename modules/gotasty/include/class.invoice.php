<?php

class Invoice{

    function __construct(){
    	
    }

    public function generateInvoiceNumber(){

        $invoiceLength      = Setting::getInvoiceNumberLength();

        $invoiceNumber = "";

        for ($i = 0; $i < $invoiceLength; $i++) {

            $invoiceNumber .= mt_rand(0, 9);
        }

        return $invoiceNumber;
    }

    /**
     * @param $clientId
     * @param $totalAmount
     * @param array $products
     * @param array $wallets
     * @return bool
     * @internal param $portfolioId
     * @internal param $amount
     */

    public function insertFullInvoice($clientId, $portfolioId, $productId, $bonusValue, $amount, $deliveryFee, $taxes, $totalAmount, $products, $wallets, $belongID) {
        $db = MysqliDb::getInstance();
        $tableName          = "mlm_invoice";
        $invoiceNo          = "";

        if (empty($clientId) || !is_numeric($clientId)) {
            return false;
        }

        if ($totalAmount < 0 || !is_numeric($totalAmount)) {
            return false;
        }

        /*if (empty($products) || !is_array($products)){
            return false;
        }*/
        //keep generate invoice number until it is unique in database
        while(true){

            $invoiceNo = self::generateInvoiceNumber();
            $db->where("invoice_no", $invoiceNo);
            $count = $db->getValue($tableName, "count(*)");

            if ($count == 0)
                break;
        }

        if (empty($invoiceNo)){
            // echo "invoice number is not generated";
            return false;
        }

        $productsCount = $category == "fundIn" ? 1 : count($products);

        $invoiceId = self::insertInvoice($clientId, $invoiceNo, $portfolioId, $productId, $bonusValue, $productsCount, $amount, $deliveryFee, $taxes, $totalAmount, $belongID);

        if (!$invoiceId)
            return false;

        if($category == "fundIn"){
            //no nid insert payment and items
            return true;
        }

        foreach($products as $product) {

            $invProductId    = $product['invProductId'];
            $invProductPrice = $product['invProductPrice'];
            $unitPrice       = $product['unitPrice'];
            $quantity        = $product['quantity'];
            $data            = $product['data']?:'';

            $invoiceItemId = self::insertInvoiceItem($invoiceId, $invProductId, $invProductPrice, $unitPrice, $quantity, $data);

            if (!$invoiceItemId)
                return false;
        }

        foreach ($wallets as $walletType => $wallet) {
            $amount = $wallet['amount'];

            if($amount > 0) {
                $invoiceItemPaymentId = self::insertInvoiceItemPayment($invoiceId, $walletType, $amount);
                if (!$invoiceItemPaymentId)
                    return false;
            }
        }

        return $invoiceId;
    }

    /**
     * @param $clientId
     * @param $invoiceNo
     * @param $amount
     * @param $productsCount
     * @return  $invoiceId   ****** not invoice number ******
     * @internal param $portfolioId
     */

    public function insertInvoice($clientId, $invoiceNo, $portfolioId, $productId, $bonusValue, $totalItem, $amount, $deliveryFee, $taxes, $totalAmount, $belongID) {

        $db = MysqliDb::getInstance();
        $tableName          = "mlm_invoice";

        $insertData         = array(

            "invoice_no"    => $invoiceNo,
            "client_id"     => $clientId,
            "portfolio_id"  => $portfolioId,
            "product_id"    => $productId,
            "bonus_value"   => $bonusValue,
            "total_amount"  => $amount,
            "total_item"    => $totalItem,
            "amount"        => $amount,
            "delivery_fee"  => $deliveryFee,
            "taxes"         => $taxes,
            "total_amount"  => $totalAmount,
            "belong_id"     => $belongID,
            "created_at"    => $db->now()
        );

        $invoiceId = $db->insert($tableName, $insertData);

        return $invoiceId;
    }

    /**
     * @param $invoiceId
     * @param $productId
     * @param $bonusValue
     * @param $productPrice
     * @param $portfolioId
     * @param $belongId
     * @return  $invoiceItemId
     */

    public function insertInvoiceItem($invoiceId, $invProductId, $invProductPrice, $unitPrice, $quantity, $data){

        $db = MysqliDb::getInstance();
        $tableName          = "mlm_invoice_item";

        $insertData = array(

            "invoice_id"        => $invoiceId,
            "inv_product_id"    => $invProductId,
            "inv_product_price" => $invProductPrice,
            "unit_price"        => $unitPrice,
            "quantity"          => $quantity,
            "data"              => $data
        );

        $invoiceItemId = $db->insert($tableName, $insertData);

        return $invoiceItemId;
    }

    /**
     * @param $invoiceId
     * @param $invoiceItemId
     * @param $productId
     * @param $wallet
     * @param $amount
     * @return $invoiceItemPaymentId
     */

    public function insertInvoiceItemPayment($invoiceId, $wallet, $amount){

        $db = MysqliDb::getInstance();
        $tableName                  = "mlm_invoice_item_payment";

        $insertData = array(

            "invoice_id"        => $invoiceId,
            "credit_type"       => $wallet,
            "amount"            => $amount,
        );

        $invoiceItemPaymentId = $db->insert($tableName, $insertData);

        return $invoiceItemPaymentId;
    }

    /**
     * @param $searchData
     * @param $limit
     * @param $column
     * @return $invoiceList
     */

    public function getInvoiceList($searchData, $limit, $column){

        $db = MysqliDb::getInstance();
        $tableName                  = "mlm_invoice";

        foreach ($searchData as $array) {
            foreach ($array as $key => $value) {
                if ($key == 'dataName') {
                    $dbColumn = $value;
                }
                else if ($key == 'dataValue') {
                    foreach ($value as $innerVal) {
                        $db->where($dbColumn, $innerVal);
                    }
                }

            }
        }

        $invoiceList = $db->get($tableName, $limit, $column);

        return $invoiceList;

    }


}


?>