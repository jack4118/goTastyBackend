<?php

class Product{

    function __construct(){
    	
    }

    public function generatePinNumber(){

        $pinNumberLength      = Setting::getPinNumberLength();

        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $max        = strlen($characters) - 1;

        $pinNumber = "";

        for ($i = 0; $i < $pinNumberLength; $i++) {

            $pinNumber .= $characters[mt_rand(0, $max)];
        }

        return $pinNumber;
    }

    public function getMinMaxPaymentMethod($amount, $creditType, $paymentType) {
        $db = MysqliDb::getInstance();

        if(empty($paymentType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify payment type", 'data' => "");
        }

        if(!empty($creditType)){
            $db->where('credit_type', $creditType);
        }

        $db->where('payment_type', $paymentType);
        $db->where('status', 'Active');
        
        // Get payment method
        $result = $db->get("mlm_payment_method", null, "id, credit_type, min_percentage, max_percentage, payment_type");

        if(empty($result)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Payment Method Not Found.", 'data' => "");
        }else{
            foreach($result as $value){
                $temp["min_percentage"] = $value["min_percentage"];
                $temp["max_percentage"] = $value["max_percentage"];
                $temp["min_usage"] = $amount * ($value["min_percentage"]/100);
                $temp["max_usage"] = $amount * ($value["max_percentage"]/100);

                $paymentMethod[$value["credit_type"]] = $temp;
            }

            return $paymentMethod;
        }
    }

    public function checkMinMaxPayment($totalAmount, $payAmount, $creditType, $paymentType, $type){
        $db = MysqliDb::getInstance();
        $language = General::$currentLanguage;
        $translations = General::$translations;

        if(empty($paymentType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify payment type", 'data' => "");
        }

        // if(empty($type)){
        //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify type", 'data' => "");
        // }
        
        if(empty($creditType)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify credit type", 'data' => "");
        }

        if(empty($totalAmount)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify total amount", 'data' => "");
        }

        if($payAmount == ""){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify pay amount", 'data' => "");
        }

        $creditList=$db->get('credit',null,'name,translation_code');
        foreach ($creditList as $key => $value) {
            $creditListDisplay[$value['name']]=$translations[$value['translation_code']][$language];
        }

        $db->where('credit_type', $creditType);
        $db->where('payment_type', $paymentType);
        // $db->where('type', $type);
        $db->where('status', 'Active');
        
        // Get payment method
        $result = $db->getOne("mlm_payment_method", "credit_type, min_percentage, max_percentage, payment_type");

        if(empty($result)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => "Payment Method Not Found.", 'data' => "");
        }else{
            $min_usage = $totalAmount * ($result["min_percentage"]/100);
            $max_usage = $totalAmount * ($result["max_percentage"]/100);

            // check min and max
            if($min_usage && ($payAmount < $min_usage)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%wallet%%", $creditListDisplay[$creditType], $translations["E00507"][$language]), 'data' => array("field" => $creditType));
            }

            if($max_usage && ($payAmount > $max_usage)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%wallet%%", $creditListDisplay[$creditType], $translations["E00508"][$language]), 'data' => array("field" => $creditType));
            }
           
        }

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    }

    function getProductData($productID, $category) {
        $db = MysqliDb::getInstance();

        if(!$productID) return false;

        $db->where('id', $productID);
        $db->where("status", "Active");
        if($category) $db->where("category",$category);
        $productData = $db->getOne('mlm_product', 'id, name, code, category, price, status, translation_code, image_id, image_name, priority');

        if(empty($productData)) return false;

        $db->where('product_id', $productID);
        $productSetting = $db->get('mlm_product_setting', NULL, 'name, value, type, reference, description');

        foreach ($productSetting as $setting) {
            if($setting["type"] == $setting["name"]){
                $productData[$setting['name']] =  $setting["value"];
            }else if($setting["type"] == "Product Setting"){
                $productData[$setting['name']] =  $setting["value"];
            }else{
                $productData['setting'][$setting['type']][] =  $setting;
            }
        }

        return $productData;
    }

    public function getProductList($productType = null, $category) {
        $db = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;

        $db->where("name", array("Hot Deals", "Flash Deals"),"IN");
        $spCategoryAry = $db->get("inv_category", NULL, "id");

        if ($productType) {
            $sq = $db->subQuery();
            $sq->where('name',$productType);
            $sq->where('value',1);
            $sq->get('mlm_product_setting',null,'product_id');

            $db->where('id',$sq,'IN');
        }
        $db->orderBy("code", "ASC");
        $db->where('status', 'Active');
        if($category) $db->where("category", $category);
        $products = $db->get('mlm_product', NULL, 'id, name, category, price, translation_code, image_id, image_name, priority');

        $productIDArray = array();
        foreach ($products as $product) {
            $checkPromo = 'bundle promo';
            if (strpos($product['name'], $checkPromo) !== false) {
                $product['isPromo'] = 1;
            } else {
                $product['isPromo'] = 0;
            }
            $product["price"] = Setting::setDecimal($product["price"], "");
            $productList[$product['id']] = $product;
            $productList[$product['id']]['display'] = $translations[$product['translation_code']][$language];
            
            if(in_array($product["category"], $spCategoryAry)){
                $productList[$product['id']]["deal"] = "1";
            }else{
                $productList[$product['id']]["deal"] = "0";
            }

            $productIDArray[$product['id']] = $product['id'];
        }
        
        if($productIDArray){
            $db->where('product_id',$productIDArray,'IN');
            $productSetting = $db->get('mlm_product_setting', NULL, 'product_id, name, value, type, reference');

            foreach ($productSetting as $setting) {
                $productList[$setting['product_id']][$setting['name']] = $setting; 
            } 
        }
        

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $productList);
    }

    //----  Pin Reentry Start ---// -- need tune
    public function transferPin($params){

        $db = MysqliDb::getInstance();
        $tableName      = "mlm_pin";
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $pin            = trim($params['pinCode']);
        $username       = trim($params['username']);

        if (empty($pin) || empty($username))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00456"][$language] /* Failed to transfer pin */, 'data' => "");

        $db->where('username', $username);
        $clientId   = $db->getValue("client", "id");

        if (empty($clientId))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00457"][$language] /* User doesn't exist */, 'data' => "");

        $updateData = array(
            "owner_id" => $clientId
        );
        $db->where("code", $pin);
        if ($db->update($tableName, $updateData))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00166"][$language] /* Successfully transferred pin */, 'data' => "");
        else
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00456"][$language] /* Failed to transfer pin */, 'data' => "");
    }

    public function getPinList($params) {

        $db = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $tableName      = "mlm_pin";
        $searchData     = $params['searchData'];
        $offsetSecs     = trim($params['offsetSecs']);
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = General::getLimit($pageNumber);
        $decimalPlaces  = Setting::getSystemDecimalPlaces();

        $db->where("category", "package");
        $productDataAry = $db->get("mlm_product", null, "id, name, translation_code as langCode");
        $data["productList"] = $productDataAry;

        // Get Pin Type
        $db->where('name','pinType');
        $res = $db->getValue('system_settings','value');

        $pinTypeList = array();
        foreach (json_decode($res) as $pinType => $translationCode) {
            $pinTypeList[$pinType] = $translations[$translationCode][$language];
        }

        $data['pinTypeList'] = $pinTypeList;

        $column     = array(

            "id",
            "code",
            "created_at",
            "unit_price",
            "bonus_value",
            "price",
            "buyer_id",
            "(SELECT member_id FROM client WHERE id = buyer_id) AS buyer_member_id",
            "(SELECT username FROM client WHERE id = buyer_id) AS buyer_username",
            "(SELECT name FROM mlm_product WHERE id = product_id) AS package_name",
            "(SELECT translation_code FROM mlm_product WHERE id = product_id) AS languageCode",
            "pin_type",
            "client_id",
            "(SELECT member_id FROM client WHERE id = client_id) AS placement_member_id",
            "(SELECT username FROM client WHERE id = client_id) AS placement_username",
            "owner_id",
            "(SELECT member_id FROM client WHERE id = owner_id) AS holder_member_id",
            "(SELECT username FROM client WHERE id = owner_id) AS holder_username",
            "used_at",
            "status"

        );

        // Means the search params is there
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);

                switch($dataName) {
                    case 'code':
                        $db->where('code', $dataValue);

                        break;

                    case 'purchaseDate':
                        if($dataValue < 0)
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>""); // Invalid date

                        $dataValue = date('Y-m-d', $dataValue);
                        $db->where('created_at', $dataValue.'%', 'LIKE');

                        break;

                    case 'transactionDate':
                        $columnName = 'created_at';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
//                            $dateTo += 86399;
                            $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                        }

                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'package':
                        if ($dataValue)
                            $db->where("product_id", $dataValue);
                        break;

                    case 'placementDate':
                        $columnName = 'used_at';

                        $dateFrom = trim($v['tsFrom']);
                        $dateTo = trim($v['tsTo']);
                        if(strlen($dateFrom) > 0) {
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                        }
                        if(strlen($dateTo) > 0) {
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
//                            $dateTo += 86399;
                            $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                        }

                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'buyerName':

                        $sq = $db->subQuery();
                        $sq->where("name", $dataValue);
                        $sq->getOne("client", "id");
                        $db->where("buyer_id", $sq);

                        break;

                    case 'status':
                        $db->where('status', $dataValue);

                        break;

                    case 'buyerUsername':
                        if ($dataValue) {
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("buyer_id", $sq);
                        }
                        break;

                    case 'placementUsername':
                        if ($dataValue) {
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                        }
                        break;

                    case 'pinType':
                        if ($dataValue) {
                            $db->where('pin_type',$dataValue);
                        }
                        break;

                    case 'buyerID':
                        if ($dataValue) {
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('buyer_id',$sq);
                        }
                        break;

                    case 'placeID':
                        if ($dataValue) {
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->get('client',null,'id');
                            $db->where('client_id',$sq);
                        }
                        break;

                    default:
                        // $db->where($dataName, $dataValue);
                        break;

                }
                unset($dataName);
                unset($dataValue);
            }
        }

        if (Cash::$creatorType == "Member")
            $db->where("owner_id", Cash::$creatorID);
        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();
        $pinList = $db->get($tableName, $limit, $column);

        if (empty($pinList))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00152"][$language] /* No results found */, 'data' => $data);

        foreach ($pinList as $pin) {

            if (Cash::$creatorType == "Admin")
                $pinListing['id']               = $pin['id'];
            $pinListing['pinNumber']            = $pin['code'];
            $pinListing['createdAt']            = General::formatDateTimeToString($pin['created_at'])?:'-';

            if (Cash::$creatorType == "Admin")
                $pinListing['entryPrice']       = $pin['unit_price']?number_format($pin['unit_price'], $decimalPlaces, '.', ''):'-';
            $pinListing['purchasePrice']        = $pin['price']?number_format($pin['price'], $decimalPlaces, '.', ''):'-';

            if (Cash::$creatorType == "Member") {
                $pinListing['bonusValue']       = $pin['bonus_value'] ?: '-';
                $pinListing['contract_length']  = '-'; //TODO not sure how is the contract get from need wait for reply
            }

            $pinListing['buyer_member_id']      = $pin['buyer_member_id']?:'-';
            $pinListing['buyerId']              = $pin['buyer_id']?:'-';
            $pinListing['buyerUsername']        = $pin['buyer_username']?:'-';
            $pinListing['packageName']          = $pin['package_name']?:'-';
            $pinListing['packageDisplay']       = $translations[$pin['languageCode']][$language]?:'-';

            // if (Cash::$creatorType == "Admin")
                $pinListing['BvType']           = $pin['pin_type']? $pinTypeList[$pin['pin_type']]:'-';
            $pinListing['placeId']              = $pin['client_id']?:'-';

            $pinListing['placementMemberID']    = $pin['placement_member_id']?:'-';
            $pinListing['placementUsername']    = $pin['placement_username']?:'-';
            $pinListing['holderMemberID']       = $pin['holder_member_id']?:'-';
            $pinListing['holderId']             = $pin['owner_id']?:'-';
            $pinListing['holderUsername']       = $pin['holder_username']?:'-';
            $pinListing['placeDate']            = General::formatDateTimeToString($pin['used_at'])?:'-';
            $pinListing['status']               = $pin['status']?:'-';

            $pinPageListing[] = $pinListing;
        }

        $totalRecord                    = $copyDb->getValue($tableName, "count(*)");
        $data['pinPageListing']         = $pinPageListing;
        $data['totalPage']              = ceil($totalRecord/$limit[1]);
        $data['pageNumber']             = $pageNumber;
        $data['totalRecord']            = $totalRecord;
        $data['numRecord']              = $limit[1];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00153"][$language] /* Pin list successfully retrieved */, 'data' => $data);
    }

    public function getPinDetail($params) {

        $db = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $pinId          = trim($params['pinId']);
        $tableName      = "mlm_pin";
        $decimalPlaces  = Setting::getSystemDecimalPlaces();

        $column     = array(
            "id",
            "code",
            "(SELECT name FROM mlm_product WHERE id = product_id) AS package_name",
            "bonus_value",
            "(SELECT member_id FROM client WHERE id = buyer_id) AS buyer_id",
            "(SELECT username FROM client WHERE id = buyer_id) AS buyer_username",
            "(SELECT member_id FROM client WHERE id = receiver_id) AS receiver_id",
            "(SELECT username FROM client WHERE id = receiver_id) AS receiver_username",
            "status"
        );

        $db->where("id", $pinId);
        $pinDetail = $db->getOne($tableName, $column);

        if (empty($pinDetail))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00154"][$language] /* No results found */, 'data' => "");

        $pinDetail['bonus_value'] = $pinDetail['bonus_value']?number_format($pinDetail['bonus_value'], $decimalPlaces, '.', ''):'-';


        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00155"][$language] /* Pin detail successfully retrieved */, 'data' => $pinDetail);
    }

    public function updatePinDetail($params) {

        $db = MysqliDb::getInstance();

        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $status         = trim($params['status']);
        $tableName      = "mlm_pin";
        $pinIdList      = $params['pinId'];
        $column         = array(

            "buyer_id",
            "batch_id",
            "unit_price",
            "(SELECT id FROM client WHERE name = 'creditRefund' AND type = 'Internal') AS account_id"
        );

        if (empty($status))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00399"][$language] /* Status is invalid */, 'data' => "");

        if (!$pinIdList)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00400"][$language] /* Pin id is invalid */, 'data' => "");

        foreach($pinIdList as $pinId) {

            $db->where("id", $pinId);
            $currentStatus = $db->getValue($tableName, "status");

            if ($currentStatus == "New" || $currentStatus == "Transferred") {

                //only perform this when user select refund
                //refund to the buyer of the id
                if ($status == "Refund"){

                    $db->where("id", $pinId);
                    $pinDetail = $db->getOne($tableName, $column);

                    $db->where("pin_id", $pinId);
                    $pinPayments = $db->get("mlm_pin_payment", NULL, "credit_type, amount");
                    $belongId = $db->getNewID();

                    if (empty($pinDetail) || empty($pinPayments))
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00401"][$language] /* Invalid pin */, 'data' => "");

                    foreach($pinPayments as $pinPayment) {
                        Cash::insertTAccount($pinDetail['account_id'], $pinDetail['buyer_id'], $pinPayment['credit_type'], $pinPayment['amount'], "Pin Refund", $belongId, "", $db->now(), $pinDetail['batch_id'], $pinDetail['buyer_id']);
                    }

                }

                $updateData = array(

                    'status' => $status
                );

                $db->where("id", $pinId);

                if (!$db->update($tableName, $updateData))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00402"][$language] /* Pin failed to update */, 'data' => "");

            }
        }

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00156"][$language] /* Pin successfully updated */, 'data' => "");
    }

    public function getPinPurchaseFormDetail($params) {

        $db = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $tableName      = "enumerators";
        $decimalPlaces  = Setting::getSystemDecimalPlaces();
        $column         = array(

            "name",
            "translation_code"
        );

        $db->where("type", "pinType");
        $pinTypeResult = $db->get($tableName, NULL, $column);

        if (empty($pinTypeResult))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00403"][$language] /* No result found. */, 'data' => "");

        $tableName  = "mlm_product";
        $column     = array(

            "id",
            "(SELECT name FROM rank WHERE id = (SELECT value FROM mlm_product_setting WHERE name = 'rankID' AND product_id = " . $tableName . ".id)) AS product_name",
            "(SELECT value FROM mlm_product_setting WHERE name = 'pinType' AND product_id = ". $tableName . ".id) AS pin_type",
            "(SELECT value FROM mlm_product_setting WHERE name = 'bonusValue' AND product_id = ". $tableName . ".id) AS bonus_value",
            "(price * (SELECT unit_price FROM mlm_unit_price ORDER BY created_at DESC LIMIT 1)) AS unit_price",

        );

        $db->where('category', 'Pin');
        $pinResult = $db->get($tableName, NULL, $column);

        if (empty($pinResult))
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00403"][$language] /* No result found. */, 'data' => "");

        foreach($pinResult as $pin){

            $pinProduct['id']           = $pin['id'];
            $pinProduct['product_name'] = $pin['product_name'];

            if (Cash::$creatorType == "Admin")
                $pinProduct['pin_type']     = $pin['pin_type'];

            $pinProduct['bonus_value']  = $pin['bonus_value'] ? number_format($pin['bonus_value'], $decimalPlaces, '.', '') : '-';
            $pinProduct['unit_price']   = $pin['unit_price'] ? number_format($pin['unit_price'], $decimalPlaces, '.', '') : '-';

            $pinProductList[] = $pinProduct;

        }

        $data['pinType']    = $pinTypeResult;
        $data['pinProduct'] = $pinProductList;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00157"][$language] /* Pin purchase form detail retrieved  */, 'data' => $data);
    }

    public function checkProductAndGetClientCreditType($params) {

        $db = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $tableName      = "mlm_product";

        $productIdList  = $params['productIdList'];
        $clientId       = trim($params['clientId']);

        // Get valid credit type 
        $creditTypeList = Self::getValidCreditType();

        if (Cash::$creatorType == "Member")
            $clientId = Cash::$creatorID;

        $totalPrice = 0;
        foreach($productIdList as $productId){

            $db->where("id", $productId['productId']);
            $db->where("status", "Active");
            $result = $db->get($tableName, null, "name, price");

            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00158"][$language] /* Product is invalid */, 'data' => "");

            foreach($result as $value){
                $totalPrice += $value["price"] * $productId["quantity"];
            }
        }

        foreach($creditTypeList as $creditType){
            // Get min/max payment method
            $paymentMethod = Product::getMinMaxPaymentMethod($totalPrice, $creditType, "Purchase Pin");

            if($paymentMethod[$creditType]){
                $balance = Cash::getClientCacheBalance($clientId, $creditType);
                $data[$creditType] = array("balance" => $balance, "payment" => $paymentMethod[$creditType]);
            }

        }

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }

    public function getClientRepurchasePinDetail($params){

        $db = MysqliDb::getInstance();

        $language           = General::$currentLanguage;
        $translations       = General::$translations;
        $clientId           = $params['clientId'];
        $tableName          = "client";
        $column             = array(

            "name",
            "username",
            "(SELECT username FROM client sponsorUsername WHERE sponsorUsername.id = client.sponsor_id) AS sponsor_username",
            "sponsor_id",
            "(SELECT username FROM client placementUsername WHERE placementUsername.id = client.placement_id) AS placement_username",
            "placement_id",
            "(SELECT client_position FROM tree_placement WHERE client_id = ". $clientId.") AS client_position",
            "(SELECT value FROM system_settings WHERE name = 'maxPlacementPositions') AS max_placement_position"

        );

        $db->where("id", $clientId);
        $result = $db->getOne($tableName, $column);

        if (empty($result))
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00403"][$language], 'data' => "");

        if ($result['max_placement_position'] == 2){

            if ($result['client_position'] == 1)
                $result['client_position'] = "Left";
            else if ($result['client_position'] == 2)
                $result['client_position'] = "Right";
        }
        else if ($result['max_placement_position'] == 3){

            if ($result['client_position'] == 1)
                $result['client_position'] = "Left";
            else if ($result['client_position'] == 2)
                $result['client_position'] = "Center";
            else if ($result['client_position'] == 3)
                $result['client_position'] = "Right";
        }

        foreach($result as $key => &$value){

            if (empty($value))
                $value = "-";
        }

        unset($value);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00161"][$language] /* Successfully retrieved client detail */, 'data' => $result);
    }

    public function getPinPurchaseData($params){
        $db = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $productReturn = self::getProductList("", "package");
        $productData = $productReturn["data"];
        foreach ($productData as $productID => $productRow) {
            unset($validClientAry);

            // if($productRow["isBundlePackage"]["value"] == 1) continue;

            $productRow["bonusValue"] = $productRow["bonusValue"]["value"];

            $validProductList[] = $productRow;
        }

        $dataOut["productList"] = $validProductList;
        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $dataOut);
    }

    public function purchasePinVerification($params, $payType){
        $db = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $clientID = $db->userID;
        $site = $db->userType;

        $purchasePackageAry = $params["purchasePackage"]; //packageID, quantity, unit Price
        $pinType = $params["pinType"];
        $tPassword = $params["tPassword"];
        $spendCredit = $params["spendCredit"];
        $step = $params["step"];

        $validPinTypeAry = array("Normal", "NBV", "NBVR");

        if(!$step){
            $step = 1;
        }

        if($site != "Member"){
            $clientID = $params["clientID"];
        }

        if(!$clientID){
            return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Member.', 'data' => "");
        }

        $db->where("id",$clientID);
        $clientRow = $db->getOne("client","id, username, octopus_id, sponsor_id");
        if($clientRow["octopus_id"]){
            $db->where("id",$clientRow["octopus_id"]);
            $octopusName = $db->getValue("client","id, username");
        }
        if($clientRow["sponsor_id"]){
            $db->where("id",$clientRow["sponsor_id"]);
            $sponsorName = $db->getValue("client","id, username");
        }

        $clientData["username"] = $clientRow["username"];
        $clientData["sponsorName"] = $sponsorName;
        $clientData["octopusName"] = $octopusName;

        if(!in_array($pinType, $validPinTypeAry)){
            return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Pin Type.', 'data' => "");
        }

        if(empty($purchasePackageAry)){
            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00841"][$language], 'data' => "");
        }
        $registerType = "Purchase Pin";

        foreach ($purchasePackageAry as $purchaseRow) {
            unset($productData);

            $productID = $purchaseRow["productID"];
            $quantity = $purchaseRow["quantity"];

            if($quantity < 1){
                continue;
            }

            if(!is_numeric($quantity)){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00505"][$language], 'data' => "");
            }

            $productData = self::getProductData($productID);
            if(empty($productData)){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Package", 'data' => "");
            }
            $purchaseRow["name"] = $productData["name"];
            $purchaseRow["bonusValue"] = $pinType == "Normal" ? $productData["bonusValue"] : 0;
            $purchaseRow["tierValue"] = $pinType == "Normal" ? 0 : $productData["bonusValue"];
            $purchaseRow["display"] = $productData["translation_code"];
            $purchaseRow["price"] = $pinType == "Normal" ? Setting::setDecimal($productData["price"],"") : 0;
            $purchaseRow["totalPrice"] = Setting::setDecimal(($quantity * $purchaseRow["price"]),"");
            $purchaseRow["totalBV"] = Setting::setDecimal(($quantity * $purchaseRow["bonusValue"]),"");

            $totalPrice += $purchaseRow["totalPrice"];

            $productPurchaseAry[] = $purchaseRow;
        }

        $paymentSetting = Cash::getPaymentDetail($clientID, $registerType, $totalPrice, $productID, "");
        $paymentMethod = $paymentSetting['data']["paymentData"];

        // for skip payment page
        if($pinType == "Normal" && count($paymentMethod) == 1){
            foreach ($paymentMethod as $creditType => $rowValue) {
                $spendCredit[$creditType]["amount"] = $totalPrice;
            }

            $dataOut["spendCredit"] = $spendCredit;
        }

        if($step == 2 && !in_array($payType, array("special", "games"))){
            //check credit payment
            $validateCredit  = Cash::paymentVerification($clientID, $registerType, $spendCredit, $productID, $totalPrice);
            if(strtolower($validateCredit["status"]) != "ok"){
                return $validateCredit;
            }

            $invoiceSpendData = $validateCredit["data"]["invoiceSpendData"];

            if($site == "Member"){
                 $tPasswordReturn = Client::verifyTransactionPassword($clientID, $tPassword);
                if($tPasswordReturn["status"] != "ok"){
                    return $tPasswordReturn;
                }
            }
        }

        $dataOut["clientData"] = $clientData;
        $dataOut["pinType"] = $pinType;
        $dataOut["registerType"] = $registerType;
        $dataOut["productData"] = $productPurchaseAry;
        $dataOut["paymentCredit"] = $paymentMethod;
        $dataOut["invoiceSpendData"] = $invoiceSpendData;
        $dataOut["totalPrice"] = Setting::setDecimal($totalPrice, "");

        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $dataOut);
    }

    public function purchasePinConfirmation($params, $payType){
        $db = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $clientID = $db->userID;
        $site = $db->userType;

        $purchasePackageAry = $params["purchasePackage"]; //packageID, quantity, unit Price
        $pinType = $params["pinType"];
        $tPassword = $params["tPassword"];
        $spendCredit = $params["spendCredit"];
        $dateTime = date("Y-m-d H:i:s");
        $step = 2;

        if($site != "Member"){
            $clientID = $params["clientID"];
        }
        $validationReturn = self::purchasePinVerification($params, $payType);
        if($validationReturn["status"] != "ok"){
            return $validationReturn;
        }
        $validationReturnData = $validationReturn["data"];
        $invoiceSpendData = $validationReturnData["invoiceSpendData"];
        $registerType = $validationReturnData["registerType"];
        $productData = $validationReturnData["productData"];
        $pinType = $validationReturnData["pinType"];
        $clientData = $validationReturnData["clientData"];
        $paymentCredit = $validationReturnData["paymentCredit"];
        $totalPrice = $validationReturnData["totalPrice"];
        $clientData = $validationReturnData["clientData"];
        
        if($params['belong']){
            $batchID = $params["belong"];
        }else{
            $batchID = $db->getNewID();
        }

        if(!in_array($payType, array("special", "games")) && $pinType == "Normal"){
            $paymentResult = Cash::paymentConfirmation($clientID, $registerType, $invoiceSpendData, $productID, $portfolioID, $totalPrice, $dateTime, $batchID);
        }
        foreach ($productData as $productRow) {
            unset($temp);
            unset($pinCodeAry);

            $productID = $productRow["productID"];
            $quantity = $productRow["quantity"];
            $bonusValue = $productRow["bonusValue"];
            $tierValue = $productRow["tierValue"];
            $price = $productRow["price"];
            $packageTotalPrice = $productRow["totalPrice"];
            for ($i=0; $i < $quantity ; $i++) { 
                unset($insertPinData);
                unset($insertPinPaymentData);

                $pinNumber = self::generatePinNumber();
                $pinCodeAry[] = $pinNumber;
                $allPinCodeAry[] = $pinNumber;
                $pinID = $db->getNewID();
                $belongID = $db->getNewID();

                //mlm_pin
                $insertPinData = array(
                    "id"                => $pinID,
                    'product_id'        => $productID,
                    'code'              => $pinNumber,
                    'status'            => "New",
                    'created_at'        => $dateTime,
                    'buyer_id'          => $clientID,
                    'price'             => !in_array($payType, array("special", "games")) ? $price : 0,
                    'bonus_value'       => $bonusValue,
                    'belong_id'         => $belongID,
                    'batch_id'          => $batchID,
                    'pin_type'          => $pinType,
                    'owner_id'          => $clientID,
                    'unit_price'        => $unitPrice,
                    'is_rewards'        => ($payType == "games" ? 1 : 0),
                );
                $db->insert("mlm_pin", $insertPinData);

                //mlm_pin_payment
                foreach ($invoiceSpendData as $creditType => $spendRow) {
                    unset($insertPinPaymentData);

                    $creditRatio  = Setting::setDecimal(($spendRow["paymentAmount"] / $totalPrice), "");
                    $creditAmount = Setting::setDecimal(($price * $creditRatio),"");

                    $insertPinPaymentData = array(
                        'pin_id'        => $pinID,
                        'credit_type'   => $creditType,
                        'amount'        => $creditAmount
                    );

                    $pinPaymentID   = $db->insert("mlm_pin_payment", $insertPinPaymentData);
                }

            }

            $temp["productId"] = $productID;
            $temp["productPrice"] = $price;
            $temp["quantity"] = $quantity;
            $temp["data"] = implode(",",$pinCodeAry);
            $temp['bonusValue'] = $bonusValue;
            $temp['belongId'] = $batchID;

            $invoiceDataArr[] = $temp;

            $pinCodeData["packageName"] = $productRow["name"];
            $pinCodeData["packageDisplay"] = $productRow["display"];
            $pinCodeData["pinCode"] = $pinCodeAry;
            $pinCodeData["productPrice"] = $price;
            $pinCodeData["quantity"] = $quantity;

            $pinCodeDataAry[] = $pinCodeData;
        }

        //invoice
        if(!in_array($payType, array("special", "games")) && $pinType == "Normal"){
        // if($payType != "special" && $pinType == "Normal"){
            // insert invoice
            $invoiceResult = Invoice::insertFullInvoice($clientID, $totalPrice, $invoiceDataArr, $invoiceSpendData, 'mlm', $batchID);
        }

        // insert activity log
        $titleCode    = 'T00011';
        $activityCode = 'L00011';
        $transferType = 'Purchase Pin';
        $activityData = array('user' => $username);

        $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
        // Failed to insert activity
        if(!$activityRes)
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");
        
        $dataOut["clientData"] = $clientData;
        $dataOut["pinCodeAry"] = $pinCodeDataAry;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M02074"][$language], 'data' => $dataOut);
    }

}

?>