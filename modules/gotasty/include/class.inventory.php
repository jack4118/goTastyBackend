<?php
	/**
     * @author TtwoWeb Sdn Bhd.
     * Date 10/12/2020.
    **/

    class Inventory {

    	function __construct(){

        }

        function generateUniqueChar(){
            $db = MysqliDb::getInstance();

            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            while (1) {
                $uniqueChar =  substr(str_shuffle($permitted_chars), 0, 16);
                $db->where('name', array('Image', 'Video'), 'IN');
                $db->where('value', '%'.$uniqueChar.'%', 'LIKE');
                $count = $db->has("mlm_product_setting");

                if ($count == 0) break;
            }
            return $uniqueChar;
        }

        // -------- Delivery Charges -------- //
        public function getDeliveryChargesListing($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $db->where("delivery_country", "1");
            $resultCountryList = $db->map("id")->get("country", NULL, "id, name, translation_code");

            // Add in country => Others
            // foreach ($resultCountryList as $key => $value) {
            //     $resultCountryList["999"] = array(
            //         "id"    => "999",
            //         "name"  => "Others",
            //         "translation_code"  => "D003520",
            //     );
            // }

            // Filter
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {

                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'countryID':
                            $db->where("country_id", $dataValue);

                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if($dateFrom <= 0 || $dateTo <= 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            if ($dateFrom) $db->where("Date(created_at)", date('Y-m-d', $dateFrom), '>=');

                            if ($dateTo) {
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                $db->where("Date(created_at)", date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // Set Limit
            if ($seeAll != "1") {
                $limit = General::getLimit($pageNumber);
            }

            // Get List
            $db->orderBy("country_id", "ASC");
            $db->orderBy("state_id", "ASC");
            $db->orderBy("priority", "ASC");
            $db->where('disabled', '0');
            $copyDb = $db->copy();
            $deliveryChargesRes = $db->get("inv_delivery", $limit, "id, country_id, state_id, weight, charges, creator_id, created_at");

            // Get Creator Username
            foreach ($deliveryChargesRes as $key => $value) {
                $creatorIDAry[$value["creator_id"]] = $value["creator_id"];
                $stateIDAry[$value["state_id"]] = $value["state_id"];
            }

            if($creatorIDAry){
                $db->where("id", $creatorIDAry, "IN");
                $creatorDataAry = $db->map("id")->get("admin", NULL, "id, username");
            }

            if($stateIDAry){
                $db->where("id", $stateIDAry, "IN");
                $stateDataAry = $db->map("id")->get("state", NULL, "id, translation_code");
            }

            //rearrange and add countryDisplay
            foreach ($deliveryChargesRes as $key => $row) {
                unset($deliveryRow);
                $deliveryRow = array(
                    "date"          => date($dateTimeFormat, strtotime($row["created_at"])),
                    "countryID"     => $row["country_id"],
                    "countryDisplay"=> $translations[$resultCountryList[$row["country_id"]]["translation_code"]][$language],
                    "stateID"       => $row["state_id"],
                    "stateDisplay"  => $translations[$stateDataAry[$row["state_id"]]][$language],
                    "charges"       => Setting::setDecimal($row["charges"]),
                    "weight"        => Setting::setDecimal($row["weight"], 2),
                    "doneBy"        => $creatorDataAry[$row["creator_id"]] ? : "-",
                );

                $countryDeliveryAry[] = $deliveryRow;
            }

            $totalRecord                 = $copyDb->getValue("inv_delivery", "COUNT(id)");
            $data["countryList"]         = $resultCountryList;
            $data['deliveryChargesList'] = $countryDeliveryAry;
            $data['pageNumber']          = $pageNumber;
            $data['totalRecord']         = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            if(!$deliveryChargesRes){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getDeliveryCharges($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $db->where("delivery_country", "1");
            $resultCountryList = $db->map("id")->get("country", NULL, "id, name, translation_code");

            // Add in country => Others
            // foreach ($resultCountryList as $key => $value) {
            //     $resultCountryList["999"] = array(
            //         "id"    => "999",
            //         "name"  => "Others",
            //         "translation_code"  => "D003520",
            //     );
            // }

            foreach ($resultCountryList as $resultCountry) {
                $countryID[$resultCountry['id']] = $resultCountry['id'];
            }

            $db->orderBy("name", "ASC");
            $db->where("country_id", $countryID, "IN");
            $db->where("disabled", "0");
            $resultStateList = $db->map('id')->get("state", NULL, "id, name, translation_code, country_id");

            foreach ($resultStateList as $stateValue) {
                unset($countryID);
                $countryID = $stateValue['country_id'];
                $stateAry[$countryID][] = $stateValue;

            }

            // Get List
            $db->orderBy("country_id", "ASC");
            $db->orderBy("state_id", "ASC");
            $db->orderBy("priority", "ASC");
            $db->where("disabled", "0");
            $deliveryChargesAry = $db->get("inv_delivery");

            // Rearrange and add countryDisplay
            foreach ($deliveryChargesAry as $row) {
                $deliveryCountryAry[$row["country_id"]][] = $row;
            }

            if($deliveryCountryAry){
                foreach ($resultCountryList as $countryID => $value) {
                    foreach ($deliveryCountryAry[$countryID] as $deliveryRow) {
                        $row = array(
                            "countryID"     => $countryID,
                            "countryDisplay"=> $translations[$resultCountryList[$countryID]["translation_code"]][$language],
                            "stateID"       => $deliveryRow['state_id'],
                            "stateDisplay"  => $translations[$resultStateList[$deliveryRow['state_id']]["translation_code"]][$language],
                            "weight"        => Setting::setDecimal($deliveryRow["weight"], 2),
                            "charges"       => Setting::setDecimal($deliveryRow["charges"], 2),
                        );
                        $countryDeliveryAry[$countryID][$deliveryRow['state_id']][] = $row;
                    }
                }
            }else{
                foreach ($stateAry as $countryID => $stateDetails) {
                    foreach ($stateDetails as $details) {
                        $row = array(
                            "countryID"     => $countryID,
                            "countryDisplay"=> $translations[$resultCountryList[$countryID]["translation_code"]][$language],
                            "stateID"       => $details["id"],
                            "stateDisplay"  => $translations[$details["translation_code"]][$language],
                            "weight"        => 1,
                            "charges"       => 0,
                        );
                        $countryDeliveryAry[$countryID][$details['id']][] = $row;
                    }
                }
            }

            $data["countryList"] = $resultCountryList;
            $data["stateList"]   = $resultStateList;
            $data['deliveryChargesList'] = $countryDeliveryAry;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function updateDeliveryCharges($params) {
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $decimalPlaces   = Setting::$systemSetting["internalDecimalFormat"];
            $deliveryCharges = $params['deliveryCharges'];
            $clientID        = $db->userID;
            $dateTime        = date("Y-m-d H:i:s");

            foreach ($deliveryCharges as $chargeRow) {
                $updateList[$chargeRow["countryID"]][$chargeRow["stateID"]][] = $chargeRow;
            }

            // Get list from database
            $db->orderBy("state_id", "ASC");
            $db->orderBy("priority", "ASC");
            $db->where("disabled", "0");
            $deliveryChargesAry = $db->get("inv_delivery", NULL, "id, country_id, state_id, weight, charges, priority, creator_id");

            // Rearrange
            foreach ($deliveryChargesAry as $row) {
                $stateDeliveryRes[$row['country_id']][$row['state_id']][$row['priority']] = array(
                    "id"            => $row["id"],
                    "countryID"     => $row["country_id"],
                    "stateID"       => $row["state_id"],
                    "weight"        => $row["weight"],
                    "charges"       => $row["charges"],
                    "priority"      => $row["priority"],
                    "creatorID"     => $row["creator_id"],
                );
            }

            // Invalid input check
            foreach ($updateList as $countryID => $updateDetailRow) {
                unset($checkMax);

                // Check countryID
                // Get valid country id
                // if ($countryID != "999") {
                //     $db->where("id", $countryID);
                //     $db->where("delivery_country", "1");
                //     $validCountryID = $db->getValue("country", "id");
                //     if(!is_numeric($countryID) || empty($countryID) || !$validCountryID) {
                //         $errorFieldArr[] = array(
                //             'id'  => $countryID."countryIDError",
                //             'msg' => $translations['E00125'][$language]
                //         );
                //     }
                // }

                foreach ($updateDetailRow as $stateID => $updateDetail) {
                    foreach ($updateDetail as $updateKey => $updateRow) {
                        // Check min
                        if ($updateRow["weight"] <= 0 || strlen(substr(strrchr($updateRow["weight"], "."), 1)) > 2) {
                            $errorFieldArr[] = array(
                                'id'  => "c".$countryID."s".$stateID."minWeight".($updateKey+1)."Error",
                                'msg' => $translations['E00125'][$language]
                            );
                        }

                        if($updateKey > 0){
                            // Check charges
                            if ($updateRow["charges"] <= 0 || !is_numeric($updateRow["charges"])) {
                                $errorFieldArr[] = array(
                                    'id'  => "c".$countryID."s".$stateID."charges".($updateKey+1)."Error",
                                    'msg' => $translations['E00125'][$language]
                                );
                            }
                        }else{
                            // Check charges
                            if ($updateRow["charges"] < 0 || !is_numeric($updateRow["charges"])) {
                                $errorFieldArr[] = array(
                                    'id'  => "c".$countryID."s".$stateID."charges".($updateKey+1)."Error",
                                    'msg' => $translations['E00125'][$language]
                                );
                            }
                        }
                    }
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // Detect changes and insert new record
            foreach ($stateDeliveryRes as $oriCountryID => $stateDeliveryRow) {

                foreach ($stateDeliveryRow as $oriStateID => $stateDetailRow) {

                    foreach ($stateDetailRow as $detailKey => $stateDetail) {
                        $newDetail = $updateList[$oriCountryID][$oriStateID][$detailKey-1];
                        $oriDetail = $stateDetail;

                        $newWeight = Setting::setDecimal($newDetail['weight']);
                        $newCharges = Setting::setDecimal($newDetail['charges']);
                        $oriWeight = Setting::setDecimal($oriDetail['weight']);
                        $oriCharges = Setting::setDecimal($oriDetail['charges']);

                        if($newDetail){
                            if ($newWeight != $oriWeight || $newCharges != $oriCharges) {
                                $inactiveColumn = array(
                                    "disabled"   => "1",
                                    "created_at" => $dateTime,
                                );
                                $db->where("id", $oriDetail["id"]);
                                $db->where("country_id", $oriCountryID);
                                $db->where("state_id", $oriStateID);
                                $db->update("inv_delivery", $inactiveColumn);

                                $newRecord = array(
                                    "country_id"    =>  $newDetail['countryID'],
                                    "state_id"      =>  $newDetail['stateID'],
                                    "weight"        =>  $newDetail['weight'],
                                    "charges"       =>  $newDetail['charges'],
                                    "disabled"      =>  0,
                                    "priority"      =>  $detailKey,
                                    "created_at"    =>  $dateTime,
                                    "creator_id"    =>  $clientID,
                                );
                                $db->insert("inv_delivery", $newRecord);
                            }
                        } else {
                            if ($oriWeight != $newWeight || $oriCharges != $newCharges) {
                                $disableColumn = array(
                                    "disabled"   => "1",
                                    "created_at" => $dateTime,
                                );
                                $db->where("id", $oriDetail["id"]);
                                $db->where("country_id", $oriCountryID);
                                $db->where("state_id", $oriStateID);
                                $db->update("inv_delivery", $disableColumn);
                            }
                        }
                        unset($updateList[$oriCountryID][$oriStateID][$detailKey-1]);
                    }
                }
            }

            $newDetailList = $updateList[$countryID];

            foreach ($newDetailList as $newStateID => $newDetailRow) {
                foreach ($newDetailRow as $priority => $detailRow) {
                    unset($newRecord);

                    $newRecord = array(
                        "country_id"    =>  $detailRow['countryID'],
                        "state_id"      =>  $detailRow['stateID'],
                        "weight"        =>  $detailRow['weight'],
                        "charges"       =>  $detailRow['charges'],
                        "disabled"      =>  0,
                        "priority"      =>  $priority+1,
                        "created_at"    =>  $dateTime,
                        "creator_id"    =>  $clientID,
                    );
                    $db->insert("inv_delivery", $newRecord);
                }
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => "");
        }

        function calculateDeliveryFee($countryID, $stateID, $productWeight){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $db->where('country_id', $countryID);
            $db->where('state_id', $stateID);
            $db->where('disabled', '0');
            $db->orderBy('priority', 'ASC');
            $deliveryChargesRes = $db->map('priority')->get('inv_delivery', null, 'priority, weight, charges');

            $i = 1;
            $chargeAmount = 0;
            $lastPriority = count($deliveryChargesRes);
            foreach ($deliveryChargesRes as $priority => $deliveryCharges) {
                $weight = $deliveryCharges['weight'];
                $charges = $deliveryCharges['charges'];

                if($i == $lastPriority){
                    $multiplier = ceil($productWeight / $weight);
                    $chargesAmount += ($multiplier * $charges);
                }else{
                    $productWeight = $productWeight - $weight;
                    $chargesAmount += $charges;
                }

                if($productWeight <= 0) break;

                $i++;
            }

            return $chargesAmount;
        }

        public function get3rdPartyDeliveryFees($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            // JNE
            $jneUsername = Setting::$configArray["jneUsername"];
            $jneAPIKey = Setting::$configArray["jneAPIKey"];
            $jneURL = Setting::$configArray["jneURL"];
            $from = Setting::$systemSetting['pickUpOrigins'];

            // ONDELIVERY
            $onDeliveryPath = Setting::$systemSetting["onServiceList"];
            $onDeliveryAuthKey = Setting::$configArray["onDeliveryAuthKey"];
            $onDeliveryURL = Setting::$configArray["onDeliveryURL"].$onDeliveryPath;

            // params
            $thru   = $params['thru'];
            $weight = $params['weight']; // in kg
            $destID = $params['destID'];

            // JNE Get Result
            $jneParams = array(
                "username" => $jneUsername,
                "api_key" => $jneAPIKey,
                "from" => $from,
                "thru" => $thru,
                'weight' => $weight, // in kg,
            );

            $jneData = http_build_query($jneParams);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $jneURL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jneData); // $response->setBody()
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $jneJsonResponse = curl_exec($ch);
            curl_close($ch);
            $jneResult = json_decode($jneJsonResponse, 1);

            if(!$jneResult['price']){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to get Delivery Fee', 'data'=> $jneResult);
            }

            $acceptedCourrier = array('JTR', 'REG', 'CTC');

            foreach ($jneResult['price'] as $jneRow) {
                if(in_array($jneRow['service_display'], $acceptedCourrier)){
                    $jne['courier'] = $jneRow['service_display'];
                    if($jneRow['service_display'] == 'CTC'){
                        $jne['courier'] = 'REG';
                    }

                    $jne['price'] = $jneRow['price'];

                    $jneList[] = $jne;
                }
            }

            $data['JNE'] = $jneList;

            // ONDELIVERY Get Result

            /* COMMENTED AWAY TO HIDE ONDELIVERY, uncomment to open it back */

            // if($destID > 0){
            //     $onParams = array(
	        //         "origin_id" => $onDeliveryOriginId,
            //         "destination_id" => $destID,
            //         'weight' => ($weight >= 1) ? $weight : 1,
            //     );

            //     $ch = curl_init();
            //     curl_setopt($ch, CURLOPT_URL, $onDeliveryURL);
            //     curl_setopt($ch, CURLOPT_POST, 1);
            //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($onParams)); // $response->setBody()
            //     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: '.$onDeliveryAuthKey));
            //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //     curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            //     $onDeliveryJsonResponse = curl_exec($ch);
            //     curl_close($ch);
            //     $onDeliveryResult = json_decode($onDeliveryJsonResponse, 1);

            //     if(!$onDeliveryResult){
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to get Delivery Fee', 'data'=> $onDeliveryResult);
            //     }

            //     foreach ($onDeliveryResult as $onDeliveryRow) {
            //         if($onDeliveryRow['service_cost'] > 0){
            //             $onDelivery['courier'] = $onDeliveryRow['service_name'];
            //             $onDelivery['price'] = $onDeliveryRow['service_cost'];
            //             $onDelivery['serviceID'] = $onDeliveryRow['service_id'];

            //             $onDeliveryList[] = $onDelivery;
            //         }
            //     }

            //     $data['ONDELIVERY'] = $onDeliveryList;
            // }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function thirdPartyCreateJneWaybill($params){

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            // ONDELIVERY
            $onDeliveryAuthKey = Setting::$configArray["jneAuthKey"];
            $onDeliveryURL = Setting::$configArray["jneApiPath"];


            // params
            $serviceID = $params['invID'];
            $senderName = $params['senderName'];
            $senderPhone = $params['senderPhone'];
            $senderAddress = $params['senderAddress'];
            $receiverName = $params['receiverName'];
            $receiverPhone = $params['receiverPhone'];
            $receiverAddress = $params['receiverAddress'];
            $receiverSubDistrict = $params['receiverSubDistrict'];
            $receiverDistrict = $params['receiverDistrict'];
            $receiverCity = $params['city'];
            $receiverState = $params['state'];
            $receiverCountryCode = $params['countryCode'];
            $goodsID = $params['goodsID'];
            $goodsWeight = $params['goodsWeight'];
            $goodsQuantity = $params['productQuantity'];
            $totalAmount = $params['totalAmount'];
            $quantity = $params['quantity'];
            $price = $params['price'];

            // ONDELIVERY Get Result
            $onParams = array(
                'username' => Setting::$configArray["jneUsername1"],
                'api_key' => Setting::$configArray["jneAuthKey"],
                'OLSHOP_BRANCH' => Setting::$configArray["jneBranch"],
                'OLSHOP_CUST' => Setting::$configArray["jneCustNo"],
                'OLSHOP_ORDERID' => $serviceID,
                'OLSHOP_SHIPPER_NAME' => Setting::$configArray["jneSenderName"],
                'OLSHOP_SHIPPER_ADDR1'=> Setting::$configArray["jneSenderAdd1"],
                'OLSHOP_SHIPPER_ADDR2' => Setting::$configArray["jneSenderAdd2"],
                'OLSHOP_SHIPPER_CITY' => Setting::$configArray["jneSenderCity"],
                'OLSHOP_SHIPPER_REGION' => Setting::$configArray["jneSenderRegion"],
                'OLSHOP_SHIPPER_ZIP' => Setting::$configArray["jneSenderZip"],
                'OLSHOP_SHIPPER_PHONE' => $senderPhone,
                'OLSHOP_RECEIVER_NAME' => $receiverName,
                'OLSHOP_RECEIVER_ADDR1' => $receiverAddress,
                'OLSHOP_RECEIVER_ADDR2' => $receiverSubDistrict,
                'OLSHOP_RECEIVER_ADDR3' => $receiverDistrict,
                'OLSHOP_RECEIVER_CITY' => $receiverCity,
                'OLSHOP_RECEIVER_REGION' => $receiverState,
                'OLSHOP_RECEIVER_ZIP' => $receiverCountryCode,
                'OLSHOP_RECEIVER_PHONE' => $receiverPhone,
                'OLSHOP_QTY' => $quantity,
                'OLSHOP_WEIGHT' => $goodsWeight,
                'OLSHOP_GOODSDESC' => 'Noting',
                'OLSHOP_GOODSVALUE' => $price,
                'OLSHOP_GOODSTYPE' => 1,
                'OLSHOP_INST' => 'TEST',
                'OLSHOP_INS_FLAG' => 'N',
                'OLSHOP_ORIG' => 'CGK10000',
                'OLSHOP_DEST' => 'BDO10000',
                'OLSHOP_SERVICE' => 'REG',
                'OLSHOP_COD_FLAG' => 'N',
                'OLSHOP_COD_AMOUNT' => $totalAmount,
            );

            //change to application/x-www-form-urlencoded format

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $onDeliveryURL);
            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($onParams));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($onParams));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $onDeliveryJsonResponse = curl_exec($ch);
            curl_close($ch);
            $onDeliveryResult = json_decode($onDeliveryJsonResponse, 1);

            if(!$onDeliveryResult){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to create waybill_jne', 'data'=> $onDeliveryResult);
            }

            $trackingNo = $onDeliveryResult['detail'][0]['cnote_no'];

            if(!$trackingNo){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to create waybill', 'data'=> $onDeliveryResult);
            }

            $data['trackingNo'] = $trackingNo;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);

        }

        public function thirdPartyCreateWaybill($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            // ONDELIVERY
            $onDeliveryPath = Setting::$systemSetting["onCreateWaybill"];
            $onDeliveryAuthKey = Setting::$configArray["onDeliveryAuthKey"];
            $onDeliveryURL = Setting::$configArray["onDeliveryURL"].$onDeliveryPath;

            // params
            $destID = $params['destID'];
            $serviceID = $params['serviceID'];
            $senderName = $params['senderName'];
            $senderPhone = $params['senderPhone'];
            $senderAddress = $params['senderAddress'];
            $receiverName = $params['receiverName'];
            $receiverPhone = $params['receiverPhone'];
            $receiverAddress = $params['receiverAddress'];
            $goodsID = $params['goodsID'];
            $goodsWeight = $params['goodsWeight'];

            // ONDELIVERY Get Result
            $onParams = array(
                "destination_id" => $destID,
                "service_id" => $serviceID,
                "sender_name" => $senderName,
                "sender_phone" => $senderPhone,
                "sender_address" => $senderAddress,
                "receiver_name" => $receiverName,
                "receiver_phone" => $receiverPhone,
                "receiver_address" => $receiverAddress,
                "goods_id" => $goodsID,
                "goods_weight" => $goodsWeight,
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

            if(!$onDeliveryResult){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to create waybill', 'data'=> $onDeliveryResult);
            }

            $trackingNo = $onDeliveryResult['waybill_number'];

            if(!$trackingNo){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to create waybill', 'data'=> $onDeliveryResult);
            }

            $data['trackingNo'] = $trackingNo;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function thirdPartyCancelWaybill($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            // ONDELIVERY
            $onDeliveryPath = Setting::$systemSetting["onCancelWaybill"];
            $onDeliveryAuthKey = Setting::$configArray["onDeliveryAuthKey"];
            $onDeliveryURL = Setting::$configArray["onDeliveryURL"].$onDeliveryPath;

            // params
            $trackingNo = $params['trackingNo'];
            $notes = $params['notes'];

            // ONDELIVERY Get Result
            $onParams = array(
                "waybill_number" => $trackingNo,
                "notes" => $notes,
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

            if(!$onDeliveryResult){
                return "noResult";
            }

            $statusMsg = $onDeliveryResult['message'];

            if(in_array($statusMsg,array("success cancelling transaction","success canceling waybill"))){
                return "success";
            }

            return $statusMsg;
        }

        // -------- Product/Package Category --------//
        public function getCategoryInventory($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? : 0;
            $limit = General::getLimit($pageNumber);

            if($seeAll) {
                $limit = NULL;
            }

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch($dataName) {
                        // case "type":
                        //     $db->where("type", $dataValue);
                        //     break;

                        case "status":
                            if($dataValue == "Active") {
                                $db->where("deleted", 0);
                            } else if($dataValue == "Inactive") {
                                $db->where("deleted", 1);
                            }
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where('DATE(created_at)', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);

                                $db->where('DATE(created_at)', date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'name':
                            $db->where('name', "%". $dataValue . "%", "LIKE");
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            $copyDB = $db->copy();
            $db->orderBy("created_at", "DESC");
            $categoryRes = $db->get("product_category", null, "id, name, deleted, created_at");

            if(!$categoryRes) {
                return array("status" => "ok", "code" => 0, "statusMsg" => $translations["B00101"][$language] /* No results found */, "data" => "");
            }

            foreach ($categoryRes as $categoryRow) {
                $categoryID[$categoryRow['id']] = $categoryRow['id'];
            }

            if($categoryID){
                $db->where('module_id', $categoryID, 'IN');
                $db->where('module', 'category');
                $db->where('type', 'name');
                $langRes = $db->get('inv_language', null, 'module_id, language, content');

                foreach ($langRes as $langRow) {
                    $lang[$langRow['module_id']][$langRow['language']] = $langRow['content'];
                }
            }

            foreach($categoryRes as $categoryRow) {
                $category["id"] = $categoryRow["id"];
                $category["name"] = $categoryRow["name"];
                $category["display"] = $lang[$categoryRow['id']][$language];
                // $category["type"] = General::getTranslationByName($categoryRow["type"]);

                switch($categoryRow["deleted"]) {
                    case "0":
                        $category["status"] = "Active";
                        $category["statusDisplay"] = General::getTranslationByName($category["status"]);
                        break;

                    case "1":
                        $category["status"] = "Inactive";
                        $category["statusDisplay"] = General::getTranslationByName($category["status"]);
                        break;

                    default:
                        break;
                }

                $category["createdAt"] = date($dateTimeFormat, strtotime($categoryRow['created_at']));

                $categoryList[] = $category;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $totalRecord = $copyDB->getValue('product_category', "count(*)");
            $data["categoryList"] = $categoryList;
            $data["pageNumber"] = $pageNumber;
            $data["totalRecord"] = $totalRecord;
            if($seeAll == "1") {
                $data["totalPage"] = 1;
                $data["numRecord"] = $totalRecord;
            } else {
                $data["totalPage"] = ceil($totalRecord/$limit[1]);
                $data["numRecord"] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00114"][$language] /* Search Sucecssful */, 'data'=> $data);
        }

        public function getCategoryInventoryDetail($params){
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $categoryInvId   = $params['categoryInvId'];

            $userID = $db->userID;
            $site = $db->userType;

            $db->where("disabled", 0);
            $availableLanguages = $db->get("languages", NULL, "id, language, language_code");

            foreach ($availableLanguages as $value) {
                $row = array(
                    "languageType" => $value["language"],
                    "languageDisplay" => $translations[$value["language_code"]][$language]
                );

                $languageList[] = $row;
            }

            $data["languageList"] = $languageList;

            if($categoryInvId) {
                $db->where("id", $categoryInvId);
                $categoryRes = $db->getOne("product_category", "id, name, deleted");

                if(!$categoryRes) {
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01043'][$language] /* Invalid ID */, 'data' => $categoryRes);
                } else {
                    $db->where("module_id", $categoryRes["id"]);
                    $db->where('module', 'category');
                    $db->where('type', 'name');
                    $nameTranslationList = $db->get("inv_language", NULL, "id, language, content");

                    if($categoryRes["deleted"] == 0) {
                        $categoryRes["status"] = "Active";
                    } else {
                        $categoryRes["status"] = "Inactive";
                    }
                    // $categoryRes['type'] = General::getTranslationByName($categoryRes['type']);

                    $data["categoryRes"] = $categoryRes;
                    $data["nameTranslationList"] = $nameTranslationList;
                }
            }

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $data);
        }

        public function verifyCategoryInventory($params, $insertType){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $category       = $params['category'];
            $status         = trim($params['status']);

            $userID = $db->userID;

            if(!$userID) {
                return array('status'=>'error', 'code'=>2, 'statusMsg'=>$translations['A01078'][$language] /* Access denied. */, 'data'=>'');
            } else {
                $db->where("id", $userID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid Admin */,'data'=>'');
                }
            }

            if($insertType == "add"){
                foreach ($category as $v) {
                    $categoryName = trim($v['name']);
                    $languageType = $v['language'];

                    if(!$categoryName && $languageType == "english"){
                        $errorFieldArr[] = array(
                            'id' => "name".$languageType."Error",
                            'msg'=> $translations['E01043'][$language] /* Please Choose English as default. */
                        );

                        // ($categoryName && !preg_match("/^[a-zA-Z]+[a-zA-Z0-9]+$/",$categoryName))
                    }elseif($categoryName == "-"){
                        $errorFieldArr[] = array(
                            'id' => "name".$languageType."Error",
                            'msg'=> $translations['E01012'][$language] /* Invalid Category. */
                        );
                    }

                    // Check Language Type Field
                    if(!$languageType) {
                        $errorFieldArr[] = array(
                            'id'  => "langTypeError",
                            'msg' => $translations['E01045'][$language] /* Please Select Language Type. */
                        );
                    }
                }
            }else{
                foreach ($category as $v) {
                    $categoryName = trim($v['name']);
                    $languageType = $v['language'];

                    if(!$categoryName && $languageType == "english"){
                        $errorFieldArr[] = array(
                            'id' => "name".$languageType."Error",
                            'msg'=> $translations['E01043'][$language] /* Please Choose English as default. */
                        );

                    // ($categoryName && !preg_match("/^[a-zA-Z]+[a-zA-Z0-9]+$/",$categoryName))
                    }elseif($categoryName == "-"){
                        $errorFieldArr[] = array(
                            'id' => "name".$languageType."Error",
                            'msg'=> $translations['E01012'][$language] /* Invalid Category. */
                        );
                    }

                    // Check Language Type Field
                    if(!$languageType) {
                        $errorFieldArr[] = array(
                            'id'  => "langTypeError",
                            'msg' => $translations['E01045'][$language] /* Please Select Language Type. */
                        );
                    }
                }
            }

            // Check Status Field
            $statusAry = array('Active', 'Inactive');
            if(!$status || !in_array($status, $statusAry)) {
                $errorFieldArr[] = array(
                    'id'  => "statusError",
                    'msg' => $translations['E00671'][$language] /* Please Select Status. */
                );
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' =>"error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            return array('status'=>"ok", 'code'=>0, 'statusMsg'=>"", 'data'=>"");
        }

        public function addCategoryInventory($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");
            $category       = $params['category'];
            // $type           = trim($params['type']);
            $status         = trim($params['status']);

            $userID = $db->userID;

            $verify = self::verifyCategoryInventory($params, 'add');
            if ($verify["status"] != "ok") return $verify;

            foreach ($category as $v) {
                $v['name'] = trim($v['name']);
                $categoryName = $v['name'];
                $languageType = $v['language'];
                if($languageType == "english"){
                    $defaultName = $categoryName;
                }
                $languageTypeList[$languageType] = $v;
            }

            //Insert into inv_category
            if($status=='Active'){
                $status=0;
            }else{
                $status=1;
            }

            $insertCategory = array(
                'name'          => $defaultName,
                // 'type'          => $type,
                'deleted'       => $status,
                'created_at'    => $dateTime,
                // 'updater_id'    => $userID,
            );
            $categoryInvId = $db->insert('product_category', $insertCategory);

            // Get System Languages
            $db->where("disabled", 0);
            $languages = $db->map("language_code")->get("languages", NULL, "language_code, language");

            foreach($languages  as  $row){
                if($languageTypeList[$row]['language'] == $row && $languageTypeList[$row]['name']){
                    $insertCategoryNameTrans = array(
                        "module" => "category",
                        "module_id" => $categoryInvId,
                        "type" => "name",
                        "language" => $row,
                        "content" => $languageTypeList[$row]['name'],
                        "updated_at" => $dateTime,
                    );
                } else {
                    $insertCategoryNameTrans = array(
                        "module" => "category",
                        "module_id" => $categoryInvId,
                        "type" => "name",
                        "language" => $row,
                        "content" => $defaultName,
                        "updated_at" => $dateTime,
                    );
                }
                $db->insert("inv_language", $insertCategoryNameTrans);
            }

            // Update system setting for process get product
            $db->where('name', 'processGetProduct');
            $db->update('system_settings', array('value' => 0));

            if(!$categoryInvId) {
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations['E01046'][$language] /* Failed to Insert Category Name */,'data'=>'');
            }else{
                return array('status'=>'ok','code'=>0,'statusMsg'=> $translations['B00416'][$language] /* Successful Insert Category Name */,'data'=>'');
            }
        }

        public function editCategoryInventory($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");
            $categoryInvId  = trim($params['categoryInvId']);
            $category       = $params['category'];
            $status         = trim($params['status']);         
            
            $userID = $db->userID;
            $site = $db->userType;

            $verify = self::verifyCategoryInventory($params, 'edit');
            if ($verify["status"] != "ok") return $verify;

            foreach ($category as $v) {
                $categoryName = $v['name'];
                $languageType = $v['language'];
                if($languageType == "english"){
                    $defaultName = $categoryName;
                }
                $languageTypeList[$languageType] = $v;
            }

            if(!$categoryInvId) {
                return array('status' => 'error', 'code' => 2, 'statusMsg' => $translations['E01047'][$language] /* Category not found */, 'data' => '');
            }

            $db->where("id", $categoryInvId);
            $categoryInvRecord = $db->getOne("product_category", "*");

            if(!$categoryInvRecord) {
                return array('status' => 'error', 'code' => 2, 'statusMsg' => $translations['E01048'][$language] /* Record not found */, 'data' => '');
            }

            //determined the status to 0 or 1
            if($status=='Active'){
                $status=0;
            }else{
                $status=1;
            }

            $editCategory = array(
                'name'          => $defaultName,
                'deleted'       => $status,
                'updated_at'    => $dateTime,
            );

            $db->where("id", $categoryInvId);
            $editInvRes = $db->update('product_category', $editCategory);

            if(!$editInvRes) {
                return array('status' => 'error', 'code' => 2,'statusMsg' => $translations['E01049'][$language] /* Failed to Update Category */ ,'data' => '');
            }

                $db->where("module_id", $categoryInvRecord['id']);
                $db->where("module", "category");
                $db->where("type", "name");
                $translationList = $db->get("inv_language", NULL, "id, language, content");

            // Update language_translation
                foreach ($translationList as $row) {
                    if($languageTypeList[$row['language']]['language'] == $row['language']) {
                        $updateTranslation = array(
                            "language" => $row["language"],
                            "content" => $languageTypeList[$row['language']]['name'],
                            "updated_at" => $dateTime,
                        );
                    } else {
                        $updateTranslation = array(
                            "language" => $row["language"],
                            "content" => $defaultName,
                            "updated_at" => $dateTime,
                        );
                    }
                    $db->where("module_id", $categoryInvRecord['id']);
                    $db->where("language", $row["language"]);
                    $db->where("module", "category");
                    $db->where("type", "name");
                    $db->update("inv_language", $updateTranslation);
                    $updateTranslationList['updateList'][] = $updateTranslation;
                }

                $insertInvLog = array(
                        "module"                    =>  "inv_category",
                        "module_id"                 =>  $categoryInvId,
                        "title_transaction_code"    =>  "T00065",
                        "title"                     =>  "Edit Category",
                        "transaction_code"          =>  "L00088",
                        "data"                      =>  json_encode(array("admin"=>$checkAdmin)),
                        "creator_type"              =>  $site,
                        "creator_id"                =>  $userID,
                        "created_at"                =>  $dateTime,
                );

                $db->insert("inv_log", $insertInvLog);

            // Update system setting for process get product
            $db->where('name', 'processGetProduct');
            $db->update('system_settings', array('value' => 0));

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations['E00708'][$language] /* Successfully Edited */, 'data'=>"");
        }

        // -------- Package -------- //
        public function getPackageListing($params, $starterKit){
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $limit           = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];
            if(!$starterKit) $starterKit = $params['starterKitFlag'];

            $userID = $db->userID;
            $site = $db->userType;

            if($seeAll){
                $limit = null;
            }

            if($params['type'] == 'export') {
                $params['command'] = __FUNCTION__;
                $params['starterKitFlag'] = $starterKit;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'productName':
                            $db->where('name', "%" . $dataValue . "%", "LIKE");
                            break;

                        case 'code':
                            $db->where("code", $dataValue);
                            break;

                        case 'status':
                            if($dataValue == 'Sold Out'){
                                $db->where('status', 'Active');
                                $db->where('is_unlimited', '0');
                                $db->where('total_balance - total_sold', '0', '<=');
                            }else{
                                $db->where("status", $dataValue);
                            }
                            break;

                        case 'createdAt':
                            $columnName = 'DATE(created_at)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            $columnName = 'DATE(updated_at)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($starterKit){
                $db->where('is_starter_kit', 1);
            }else{
                $db->where('is_starter_kit', 0);
            }
            $db->orderBy('created_at', 'DESC');
            $copyDb = $db->copy();
            $packageRes = $db->get("mlm_product", $limit, "id, code, name, weight, pv_price, total_balance, total_sold, is_unlimited, status, active_at, created_at, updated_at, updater_id");

            if(empty($packageRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            foreach($packageRes as $packageRow){
                if($packageRow['updater_id']) $updaterAry[$packageRow['updater_id']] = $packageRow['updater_id'];
                $packageID[$packageRow['id']] = $packageRow['id'];
            }

            if($updaterAry){
                $db->where("id", $updaterAry, "IN");
                $adminIDAry = $db->map("id")->get("admin", null, "id, username");
            }

            if($packageID){
                $db->where('product_id', $packageID, 'IN');
                $db->where('type', array('packageCategory'), 'IN');
                $packageDetailRes = $db->get('mlm_product_setting', null, 'product_id, name, value');

                foreach ($packageDetailRes as $packageDetailRow) {
                    $packageDetail[$packageDetailRow['product_id']][$packageDetailRow['name']][] = $packageDetailRow['value'];
                }

                $db->where('module_id', $packageID, 'IN');
                $db->where('module', 'mlm_product');
                $db->where('language', $language);
                $langAry = $db->get('inv_language', null, 'module_id, type, content');

                foreach ($langAry as $langRow) {
                    $lang[$langRow['module_id']][$langRow['type']] = $langRow['content'];
                }

                $db->where('product_id', $packageID, 'IN');
                $db->where('country_id', 100);
                $priceDetailRes = $db->map('product_id')->get('mlm_product_price', null, 'product_id, price, promo_price, m_price, ms_price');

                $db->where('value', $packageID, 'IN');
                $db->where('name', 'validPackage');
                $validPackageAry = $db->get('inv_product_detail', null, 'name, value, inv_product_id, reference');

                $db->where('status', 'Active');
                $productAry = $db->map('id')->get('inv_product', null, 'id, weight');

                foreach ($validPackageAry as $validPackageRow) {
                    $validPackageRow['weight'] = Setting::setDecimal($productAry[$validPackageRow['inv_product_id']] * $validPackageRow['reference']);

                    unset($validPackageRow['reference']);
                    $packageDetailAry[$validPackageRow['value']][$validPackageRow['inv_product_id']] = $validPackageRow;
                }
            }

            $db->where('disabled', 0);
            $db->where('type', 'package');
            $categoryAry = $db->getValue('inv_category', 'id', null);

            if($categoryAry) {
                $db->where('module_id', $categoryAry, 'IN');
                $db->where('module', 'inv_category');
                $db->where('language', $language);
                $categoryLang = $db->map('module_id')->get('inv_language', null, 'module_id, content');
            }

            foreach($packageRes as $packageRow){
                $package['id'] = $packageRow['id'];
                $package['name'] = $packageRow['name'];
                $package['code'] = $packageRow['code'];
                $package['totalBalance'] = Setting::setDecimal($packageRow['total_balance'] - $packageRow['total_sold']);
                $package['totalSold'] = Setting::setDecimal($packageRow['total_sold']);

                if($package['totalBalance'] <= 0 && $packageRow['status'] == "Active" && $packageRow['is_unlimited'] == 0){
                    $package['status'] = 'Sold Out';
                    $package['statusDisplay'] = General::getTranslationByName($package['status']);
                }else{
                    $package['status'] = $packageRow['status'];
                    $package['statusDisplay'] = General::getTranslationByName($packageRow['status']);
                }

                $package['description'] = $lang[$packageRow['id']]['desc'] ? : '-';

                $categoryID = $packageDetail[$packageRow['id']]['packageCategory'];
                foreach ($categoryID as $category) {
                    $categoryDisplay[$category] = $categoryLang[$category];
                }
                $package['categoryDisplay'] = implode(', ', $categoryDisplay) ? : '-';

                $package['pvPrice'] = $packageRow['pv_price'];

                $retailPrice    = $priceDetailRes[$packageRow['id']]['price'];
                $promoPrice     = $priceDetailRes[$packageRow['id']]['promo_price'];
                $memberPrice    = $priceDetailRes[$packageRow['id']]['m_price'];
                $memberUpPrice  = $priceDetailRes[$packageRow['id']]['ms_price'];
                $package['retailPrice'] = Setting::setDecimal($retailPrice);
                $package['promoPrice']  = $promoPrice > 0 ? Setting::setDecimal($promoPrice) : '-';
                $package['memberPrice'] = Setting::setDecimal($memberPrice);
                $package['memberUpPrice'] = Setting::setDecimal($memberUpPrice);

                $productList = $packageDetailAry[$packageRow['id']];
                foreach ($productList as $productRow) {
                    $productWeight += $productRow['weight'];
                }
                $package['weight'] = Setting::setDecimal($productWeight);
                if($packageRow["weight"]) $package["weight"] = Setting::setDecimal($packageRow["weight"]);

                $package['activeDate'] = $packageRow['active_at'] > 0 ? date('d/m/Y', strtotime($packageRow['active_at'])) : '-';
                $package['createdAt'] = date($dateTimeFormat, strtotime($packageRow['created_at']));
                $package['updaterName'] = $adminIDAry[$packageRow['updater_id']] ? : '-';
                $package['updatedAt'] = $packageRow['updated_at'] > 0 ? date($dateTimeFormat, strtotime($packageRow['updated_at'])) : '-';
                $package['adjustRestricted'] = $packageRow['is_unlimited'];
                unset($productWeight);
                $packageList[] = $package;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $totalRecord              = $copyDb->getValue("mlm_product", "count(*)");
            $data['packageList']      = $packageList;
            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            if($seeAll) {
                $data['totalPage']    = 1;
                $data['numRecord']    = $totalRecord;
            } else {
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['numRecord']    = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPackageDetail($params, $starterKit){
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $packageID = $params['packageID'];

            $userID = $db->userID;
            $site = $db->userType;

            $db->where("disabled", 0);
            $availableLanguages = $db->get("languages", NULL, "id, language, language_code");

            foreach ($availableLanguages as $value) {
                $row = array(
                    "languageType" => $value['language'],
                    "languageDisplay" => $translations[$value['language_code']][$language]
                );

                $languageList[] = $row;
                $availableLanguageAry[] = $value['language'];
            }
            $data['languageList'] = $languageList;

            $db->where('disabled', 0);
            $availableCategory = $db->map('id')->get('inv_category', null, 'id, name, type');

            foreach ($availableCategory as $value) {
                $categoryIDAry[$value['id']] = $value['id'];
            }

            if($categoryIDAry) {
                $db->where('module_id', $categoryIDAry, 'IN');
                $db->where('module', 'inv_category');
                $db->where('language', $language);
                $db->where('type', 'name');
                $categoryLang = $db->map('module_id')->get('inv_language', null, 'module_id, content');
            }

            foreach ($availableCategory as $value) {
                $value['categoryDisplay'] = $categoryLang[$value['id']];

                if($value['type'] == 'package') {
                    unset($value['type']);
                    $categoryPackageList[] = $value;
                }else if($value['type'] == 'product'){
                    unset($value['type']);
                    $categoryProductList[] = $value;
                }
            }

            $data['packageCategoryList'] = $categoryPackageList;
            $data['productCategoryList'] = $categoryProductList;

            $availableProduct = $db->map('id')->get('inv_product', null, 'id, name, status, weight, status');

            foreach ($availableProduct as $value) {
                $productIDAry[$value['id']] = $value['id'];
            }

            if($productIDAry) {
                $db->where('inv_product_id', $productIDAry, 'IN');
                $db->where('name','productCategory');
                $getProductCategory = $db->map('inv_product_id')->get('inv_product_detail', null, 'inv_product_id, value');
            }

            foreach ($availableProduct as $value) {
                if(in_array($value["status"],array("Active"))){
                    $value['productDisplay'] = $value['name'];
                    $value['categoryID'] = $getProductCategory[$value['id']];

                    $productList[$getProductCategory[$value['id']]][$value['id']] = $value;
                }
            }

            $data['productList'] = $productList;

            unset($countryParams);
            $countryParams = array(
                "deliveryCountry" => "Yes"
            );
            $countryReturn = Country::getCountriesList($countryParams);
            $data["availableCountry"] = $countryReturn["data"]["countriesList"];

            if($packageID) {
                if($starterKit){
                    $db->where('is_starter_kit', 1);
                }
                $db->where('id', $packageID);
                $packageRes = $db->getOne("mlm_product", "id, code, name, weight, pv_price, total_balance, status, active_at");
                $packageRes['active_at'] = $packageRes['active_at'] > 0 ? date('d/m/Y', strtotime($packageRes['active_at'])) : '-';

                if(!$packageRes) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01051"][$language], 'data' => "");
                } else {
                    $db->where("module_id", $packageRes['id']);
                    $db->where("module", "mlm_product");
                    $db->where("type", "name");
                    $db->where("language",$availableLanguageAry,"IN");
                    $nameTranslationList = $db->get("inv_language", null, "id, language, content");

                    $db->where("module_id", $packageRes['id']);
                    $db->where("module", "mlm_product");
                    $db->where("type", "desc");
                    $db->where("language",$availableLanguageAry,"IN");
                    $descrTranslationList = $db->get("inv_language", null, "id, language, content");

                    $db->where('product_id', $packageID);
                    $db->where('type', array('Image', 'Video'), 'IN');
                    $packageDetails = $db->get("mlm_product_setting", null, 'value, type as uploadType');

                    foreach($packageDetails as $packageDetailsRow) {
                        if($packageDetailsRow['uploadType'] == 'Image'){
                            $imageList[] = $packageDetailsRow;
                        }elseif ($packageDetailsRow['uploadType'] == 'Video') {
                            $videoList[] = $packageDetailsRow;
                        }
                    }

                    $db->where('product_id', $packageID);
                    $db->where('type', array('packageCategory', 'bBasic', 'catalogue'), 'IN');
                    $packageDetailRes = $db->get("mlm_product_setting", null, "id, name, value");

                    $data['bBasic'] = 0;
                    $data['catalogue'] = 0;
                    if($packageDetailRes){
                        $countryName = $db->map("id")->get("country", null, "id, translation_code");

                        foreach ($packageDetailRes as $packageDetailRow) {
                            if($packageDetailRow['name'] == "packageCategory"){
                                $packageRes['category'][] = $categoryLang[$packageDetailRow['value']];
                            }
                            if($packageDetailRow['name'] == "bBasic" || $packageDetailRow['name'] == "catalogue"){
                                $data[$packageDetailRow['name']] = $packageDetailRow['value'];
                            }
                        }
                    }

                    $db->where('value', $packageID);
                    $db->where('type', array('validPackage'), 'IN');
                    $validProduct = $db->get("inv_product_detail", null, "inv_product_id, value, reference");

                    if($validProduct){
                        foreach ($validProduct as $productRow) {
                            $product['productName'] = $availableProduct[$productRow['inv_product_id']]['name'];
                            $product['productQuantity'] = $productRow['reference'];

                            $packageRes['product'][] = $product;
                        }
                    }

                    $db->where('p.disabled', 0);
                    $db->where('p.product_id', $packageID);
                    $priceDetails = $db->get('mlm_product_price p',null,'(SELECT c.name FROM country c WHERE c.id = p.country_id) AS countryName, p.price, p.promo_price AS promoPrice, p.m_price AS memberPrice, p.ms_price AS memberUpPrice');

                    $data['packageDetails'] = $packageRes;
                    $data['packagePriceDetails'] = $priceDetails;
                    $data['nameTranslationList'] = $nameTranslationList;
                    $data['descrTranslationList'] = $descrTranslationList;
                    $data['imageList'] = $imageList;
                    $data['videoList'] = $videoList;
                }
            }

            $data['discountPercentage'] = 25;
            $data['discountUpPercentage'] = 30;
            $data["doRegion"]     = Setting::$configArray["doRegion"];
            $data["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            $data["doAccessKey"]  = Setting::$configArray["doApiKey"];
            $data["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            $data["doBucketName"] = Setting::$configArray["doBucketName"]."inv";
            $data["doProjectName"]= Setting::$configArray["doProjectName"];
            $data["doFolderName"] = Setting::$configArray["doFolderName"]."inv";

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function verifyPackageDetail($params, $type = "") {
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $packageID          = $params['packageID'];
            $name            = trim($params['name']);
            $code            = trim($params['code']);
            $packageQuantity = trim($params['packageQuantity']);
            $product         = $params['product'];
            $category        = $params['category'];
            $priceSetting    = $params['priceSetting'];
            $pvPrice         = $params['pvPrice'];
            $activeDate      = $params['activeDate'];
            $nameLanguages   = $params['nameLanguages'];
            $descrLanguages  = $params['descrLanguages'];
            $uploadImage     = $params['uploadImage'];
            $uploadVideo     = $params['uploadVideo'];
            $status          = trim($params['status']);
            $statusAry       = array('Active', 'Inactive');
            $todayDate       = strtotime(date("Y-m-d"));
            $adminRoleList   = Setting::$systemSetting['InvEditableRoles'];
            $adminRoleListAry = explode("#", $adminRoleList);

            $clientID   = $db->userID;
            $site       = $db->userType;

            $createPackage   = $params['createPackage'];
            $productQuantity = $params['productQuantity'];
            $isStarterKit    = $params['isStarterKit'];
            $weight          = trim($params["weight"]);

            $db->where('role_id',$adminRoleListAry, 'IN');
            $availableAdminRes = $db->getValue('admin','id',null);

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            // Check Name Field
            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id'  => "nameError",
                    'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                );
            }

            // Check Code Field
            if(empty($code)) {
                $errorFieldArr[] = array(
                    'id'  => "codeError",
                    'msg' => $translations['E00928'][$language] /* Please Enter Code. */
                );
            } else if($type == 'add'){
                // check code avaibility
                $db->where('code', $code);
                $result = $db->has("mlm_product");

                if($result) {
                    $errorFieldArr[] = array(
                        'id'  => 'codeError',
                        'msg' => $translations["E00929"][$language] /* Code Existed. */
                    );
                }
            }

            // Check Category Field
            if(!$category) {
                $errorFieldArr[] = array(
                    'id'  => "categoryError",
                    'msg' => $translations['E00930'][$language] /* Please Enter Category. */
                );
            } else {
                $db->where('disabled', 0);
                $availableCategory = $db->getValue('inv_category', 'id', null);

                foreach ($category as $categoryData) {
                    if(!in_array($categoryData, $availableCategory)) {
                        $errorFieldArr[] = array(
                            'id'  => "categoryError",
                            'msg' => $translations['E01012'][$language]
                        );
                    }
                }
            }

            // Check Product Field
            if($createPackage == 1){
                /*if(!$productQuantity || !is_numeric($productQuantity) || $productQuantity < 0){
                    $errorFieldArr[] = array(
                        'id'  => "productQuantityError",
                        'msg' => $translations['E00999'][$language]
                    );
                }*/
            }else{
                if(!$product) {
                    $errorFieldArr[] = array(
                        'id'  => "productError",
                        'msg' => $translations['E00841'][$language] /* Please select product. */
                    );
                } else {
                    $db->where('status', 'Active');
                    $availableProduct = $db->getValue('inv_product', 'id', null);

                    foreach ($product as $key => $productData) {
                        $num = $key+1;

                        if(!in_array($productData['productID'], $availableProduct)) {
                            $errorFieldArr[] = array(
                                'id'  => "productError",
                                'msg' => str_replace("%%key%%", $num, $translations['E01059'][$language])
                            );
                        }

                        if(!$productData['quantity'] || !is_numeric($productData['quantity']) || $productData['quantity'] < 0){
                            $errorFieldArr[] = array(
                                'id'  => "quantityError",
                                'msg' => str_replace("%%key%%", $num, $translations['E01060'][$language])
                            );
                        }
                    }
                }
            }

            // Check Price Field
            //PV price && Price setting
            if($type == "edit"){
                if(!in_array($clientID, $availableAdminRes)){
                    if($isStarterKit == 1){
                        if($pvPrice){
                            if(!is_numeric($pvPrice) || $pvPrice < 0){
                                $errorFieldArr[] = array(
                                    'id'  => "pvPriceError",
                                    'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                );
                            }
                        }
                    }else{
                        if(!$pvPrice || !is_numeric($pvPrice) || $pvPrice < 0){
                            $errorFieldArr[] = array(
                                'id'  => "pvPriceError",
                                'msg' => $translations['E00910'][$language] /* Invalid Price. */
                            );
                        }
                    }

                    if(!$priceSetting){
                        $errorFieldArr[] = array(
                            'id'  => "priceSettingError",
                            'msg' => $translations['E01087'][$language] /* Please fill in price setting. */
                        );
                    }else{
                        $db->where('delivery_country', '1');
                        $db->where('status', 'Active');
                        $availableCountry = $db->getValue('country', 'id', null);

                        foreach($priceSetting as $priceSettingID => $priceDetail){
                            $priceID = $priceSettingID+1;
                            // Check Country Field
                            if(!in_array($priceDetail['country'], $availableCountry)) {
                                $errorFieldArr[] = array(
                                    'id'  => "country".$priceID."Error",
                                    'msg' => $translations['E00568'][$language] /* Invalid Country. */
                                );
                            }

                            // Check retail price
                            if($priceDetail['retailPrice']){
                                if(!is_numeric($priceDetail['retailPrice']) || $priceDetail['retailPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "retailPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }
                            }

                            // check promo price
                            if($priceDetail['promoPrice']){
                                if(!is_numeric($priceDetail['promoPrice']) || $priceDetail['promoPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "promoPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }
                            }

                            if(($priceSetting['retailPrice'] || $priceSetting['promoPrice']) && !$isStarterKit){
                                // check member price
                                if(!$priceDetail['memberPrice'] || !is_numeric($priceDetail['memberPrice']) || $priceDetail['memberPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "memberPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }

                                // check member s price
                                if(!$priceDetail['memberUpPrice'] || !is_numeric($priceDetail['memberUpPrice']) || $priceDetail['memberUpPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "memberUpPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }
                            }
                        }
                    }
                }else{
                    if($isStarterKit == 1){
                        if($pvPrice){
                            if(!is_numeric($pvPrice) || $pvPrice < 0){
                                $errorFieldArr[] = array(
                                    'id'  => "pvPriceError",
                                    'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                );
                            }
                        }
                    }else{
                        if(!$pvPrice || !is_numeric($pvPrice) || $pvPrice < 0){
                            $errorFieldArr[] = array(
                                'id'  => "pvPriceError",
                                'msg' => $translations['E00910'][$language] /* Invalid Price. */
                            );
                        }
                    }

                    if(!$priceSetting){
                        $errorFieldArr[] = array(
                            'id'  => "priceSettingError",
                            'msg' => $translations['E01087'][$language] /* Please fill in price setting. */
                        );
                    }else{
                        $db->where('delivery_country', '1');
                        $db->where('status', 'Active');
                        $availableCountry = $db->getValue('country', 'id', null);

                        foreach($priceSetting as $priceSettingID => $priceDetail){
                            $priceID = $priceSettingID+1;
                            // Check Country Field
                            if(!in_array($priceDetail['country'], $availableCountry)) {
                                $errorFieldArr[] = array(
                                    'id'  => "country".$priceID."Error",
                                    'msg' => $translations['E00568'][$language] /* Invalid Country. */
                                );
                            }

                            // Check retail price
                            if($priceDetail['retailPrice']){
                                if(!is_numeric($priceDetail['retailPrice']) || $priceDetail['retailPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "retailPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }
                            }

                            // check promo price
                            if($priceDetail['promoPrice']){
                                if(!is_numeric($priceDetail['promoPrice']) || $priceDetail['promoPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "promoPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }
                            }

                            if(($priceSetting['retailPrice'] || $priceSetting['promoPrice']) && !$isStarterKit){
                                // check member price
                                if(!$priceDetail['memberPrice'] || !is_numeric($priceDetail['memberPrice']) || $priceDetail['memberPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "memberPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }

                                // check member s price
                                if(!$priceDetail['memberUpPrice'] || !is_numeric($priceDetail['memberUpPrice']) || $priceDetail['memberUpPrice'] < 0){
                                    $errorFieldArr[] = array(
                                        'id'  => "memberUpPrice".$priceID."Error",
                                        'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                    );
                                }
                            }
                        }
                    }
                }
            }else{
                if($isStarterKit == 1){
                    if($pvPrice){
                        if(!is_numeric($pvPrice) || $pvPrice < 0){
                            $errorFieldArr[] = array(
                                'id'  => "pvPriceError",
                                'msg' => $translations['E00910'][$language] /* Invalid Price. */
                            );
                        }
                    }
                }else{
                    if(!$pvPrice || !is_numeric($pvPrice) || $pvPrice < 0){
                        $errorFieldArr[] = array(
                            'id'  => "pvPriceError",
                            'msg' => $translations['E00910'][$language] /* Invalid Price. */
                        );
                    }
                }

                if(!$priceSetting){
                    $errorFieldArr[] = array(
                        'id'  => "priceSettingError",
                        'msg' => $translations['E01087'][$language] /* Please fill in price setting. */
                    );
                }else{
                    $db->where('delivery_country', '1');
                    $db->where('status', 'Active');
                    $availableCountry = $db->getValue('country', 'id', null);

                    foreach($priceSetting as $priceSettingID => $priceDetail){
                        $priceID = $priceSettingID+1;
                        // Check Country Field
                        if(!in_array($priceDetail['country'], $availableCountry)) {
                            $errorFieldArr[] = array(
                                'id'  => "country".$priceID."Error",
                                'msg' => $translations['E00568'][$language] /* Invalid Country. */
                            );
                        }

                        // Check retail price
                        if($priceDetail['retailPrice']){
                            if(!is_numeric($priceDetail['retailPrice']) || $priceDetail['retailPrice'] < 0){
                                $errorFieldArr[] = array(
                                    'id'  => "retailPrice".$priceID."Error",
                                    'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                );
                            }
                        }

                        // check promo price
                        if($priceDetail['promoPrice']){
                            if(!is_numeric($priceDetail['promoPrice']) || $priceDetail['promoPrice'] < 0){
                                $errorFieldArr[] = array(
                                    'id'  => "promoPrice".$priceID."Error",
                                    'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                );
                            }
                        }

                        if(($priceSetting['retailPrice'] || $priceSetting['promoPrice']) && !$isStarterKit){
                            // check member price
                            if(!$priceDetail['memberPrice'] || !is_numeric($priceDetail['memberPrice']) || $priceDetail['memberPrice'] < 0){
                                $errorFieldArr[] = array(
                                    'id'  => "memberPrice".$priceID."Error",
                                    'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                );
                            }

                            // check member s price
                            if(!$priceDetail['memberUpPrice'] || !is_numeric($priceDetail['memberUpPrice']) || $priceDetail['memberUpPrice'] < 0){
                                $errorFieldArr[] = array(
                                    'id'  => "memberUpPrice".$priceID."Error",
                                    'msg' => $translations['E00910'][$language] /* Invalid Price. */
                                );
                            }
                        }
                    }
                }
            }

            $countPriceSetting = count($priceSetting);
            if($countPriceSetting < 0){
                $errorFieldArr[] = array(
                                'id'  => "retailPriceError",
                                'msg' => $translations['E01092'][$language] /* Please insert at least 1 retail price. */
                            );
            }

            // Check Package Quantity
            if($type == "add"){
                if($packageQuantity){
                    if(!is_numeric($packageQuantity) || $packageQuantity < 0){
                        $errorFieldArr[] = array(
                            'id'  => "packageQuantityError",
                            'msg' => $translations['E00999'][$language] /* Invalid Quantity. */
                        );
                    }
                }
            }else if($type == "edit"){
                $db->where('id',$packageID);
                $isUnlimited = $db->getValue('mlm_product','is_unlimited');

                if($isUnlimited == "1"){
                    if($packageQuantity){
                        $errorFieldArr[] = array(
                            'id'  => "packageQuantityError",
                            'msg' => $translations['E01079'][$language] /* Quantity cannot be edit. */
                        );
                    }
                }else{
                    if(in_array($clientID, $availableAdminRes)){
                        if(!$packageQuantity || !is_numeric($packageQuantity) || $packageQuantity < 0){
                            $errorFieldArr[] = array(
                                'id'  => "packageQuantityError",
                                'msg' => $translations['E00999'][$language] /* Invalid Quantity. */
                            );
                        }
                    }else{
                        if($packageQuantity){
                            $errorFieldArr[] = array(
                                'id'  => "packageQuantityError",
                                'msg' => $translations['E01079'][$language] /* Quantity cannot be edit. */
                            );
                        }
                    }
                }
            }

            // Check Status Field
            if(empty($status) || !in_array($status, $statusAry)) {
                $errorFieldArr[] = array(
                    'id'  => "statusError",
                    'msg' => $translations['E00671'][$language] /* Please Select Status. */
                );
            }

            if(!is_numeric($weight) || !$weight || $weight <= 0){
                $errorFieldArr[] = array(
                    "id" => "weightError",
                    "msg" => $translations["E01010"][$language]
                );
            }

            /*// Check actived date
            if(!$activeDate || $activeDate < $todayDate) {
                $errorFieldArr[] = array(
                    'id'  => "activeDateError",
                    'msg' => $translations['E00156'][$language]
                );
            }*/

            $db->where('disabled', 0);
            $countLanguage = $db->getValue('languages', 'count(*)');

            // Check Name Language Field
            if(empty($nameLanguages)) {
                $errorFieldArr[] = array(
                    'id'  => "nameLanguagesError",
                    'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                );
            }else{
                $nameLanguagesAry = count($nameLanguages);

                if($nameLanguagesAry > $countLanguage){
                    $errorFieldArr[] = array(
                        'id'  => "nameLanguagesError",
                        'msg' => str_replace("%%count%%", $countLanguage, $translations['E01077'][$language])
                    );
                }
            }

            // Check Name Language Field
            foreach($nameLanguages as $nameRow) {
                if(!$nameRow["languageType"]) {
                    $errorFieldArr[] = array(
                        'id'  => "nameLanguagesError",
                        'msg' => $translations['E00602'][$language] /* Please Select Language. */
                    );
                }

                if($nameRow["languageType"] == "english") {
                    if(empty($nameRow["content"])) {
                        $errorFieldArr[] = array(
                            'id'  => "nameLanguagesError",
                            'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                        );
                    }
                }
            }

            // Check Description Language Field
            if(empty($descrLanguages)) {
                $errorFieldArr[] = array(
                    'id'  => "descrLanguagesError",
                    'msg' => $translations['E00662'][$language] /* Please Enter Description. */
                );
            }else{
                $descrLanguagesAry = count($descrLanguages);

                if($descrLanguagesAry > $countLanguage){
                    $errorFieldArr[] = array(
                        'id'  => "descrLanguagesError",
                        'msg' => str_replace("%%count%%", $countLanguage, $translations['01077'][$language])
                    );
                }
            }

            // check description language field
            foreach($descrLanguages as $descriptionRow) {
                if($descriptionRow["languageType"] == "english") {
                    if(empty($descriptionRow["content"])) {
                        $errorFieldArr[] = array(
                            'id'  => "descrLanguagesError",
                            'msg' => $translations['E00662'][$language] /* Please Enter Description. */
                        );
                    }
                }
            }

            foreach($uploadImage as $uploadImageRow) {
                $validImageSet  = $uploadSetting['validImageType'];
                $validImageType = explode("#", $validImageSet['value']);
                $validImageSize = $validImageSet['reference'];
                $sizeMB         = $validImageSize / 1024 / 1024;

                if(empty($uploadImageRow["imgName"]) || empty($uploadImageRow["imgType"])) {
                    $errorFieldArr[] = array(
                        'id'  => "imgError",
                        'msg' => $translations["E00925"][$language] /* No file chosen */
                    );
                }

                if(empty($uploadImageRow['uploadType']) || !in_array($uploadImageRow['uploadType'], array('image'))) {
                    $errorFieldArr[] = array(
                        'id'  => "uploadTypeError",
                        'msg' => $translations["E00741"][$language] /* Invalid Type */
                    );
                }

                if($type != 'add') {
                    if($uploadImageRow["imgFlag"]) {
                        if(!in_array($uploadImageRow["imgType"], $validImageType)) {
                            $errorFieldArr[] = array(
                                'id'  => "imgTypeError",
                                'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                            );
                        }

                        if(!$uploadImageRow['imgSize'] || $uploadImageRow['imgSize'] > $validImageSize){
                            $errorFieldArr[] = array(
                                'id'  => "imgTypeError",
                                'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                            );
                        }
                    }
                } else {
                    if(!in_array($uploadImageRow["imgType"], $validImageType)) {
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                        );
                    }

                    if(!$uploadImageRow['imgSize'] || $uploadImageRow['imgSize'] > $validImageSize){
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                        );
                    }
                }
            }

            foreach($uploadVideo as $uploadVideoRow) {
                if(!$uploadVideoRow['videoName']) continue;

                $validVideoSet = $uploadSetting['validVideoType'];
                $validVideoType = explode("#", $validVideoSet['value']);
                $validVideoSize = $validVideoSet['reference'];
                $sizeMB         = $validVideoSize / 1024 / 1024;

                if(empty($uploadVideoRow["videoName"]) || empty($uploadVideoRow["videoType"])) {
                    $errorFieldArr[] = array(
                        'id'  => "videoError",
                        'msg' => $translations["E00925"][$language] /* No file chosen */
                    );
                }

                if(empty($uploadVideoRow['uploadType']) || !in_array($uploadVideoRow['uploadType'], array('video'))) {
                    $errorFieldArr[] = array(
                        'id'  => "uploadTypeError",
                        'msg' => $translations["E00741"][$language] /* Invalid Type */
                    );
                }

                if($type != 'add') {
                    if($uploadVideoRow["videoFlag"]) {
                        if(!in_array($uploadVideoRow["videoType"], $validVideoType)) {
                            $errorFieldArr[] = array(
                                'id'  => "videoError",
                                'msg' => $translations["E00972"][$language] /* Uploaded file is not a valid video. */
                            );
                        }

                        if(!$uploadVideoRow['videoSize'] || $uploadVideoRow['videoSize'] > $validVideoSize){
                            $errorFieldArr[] = array(
                                'id'  => "videoError",
                                'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 15MB) */
                            );
                        }
                    }
                } else {
                    if(!in_array($uploadVideoRow["videoType"], $validVideoType)) {
                        $errorFieldArr[] = array(
                            'id'  => "videoError",
                            'msg' => $translations["E00972"][$language] /* Uploaded file is not a valid video. */
                        );
                    }

                    if(!$uploadVideoRow['videoSize'] || $uploadVideoRow['videoSize'] > $validVideoSize){
                        $errorFieldArr[] = array(
                            'id'  => "videoError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 15MB) */
                        );
                    }
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> '', 'data'=>"");
        }

        public function addPackageDetail($params, $starterKit) {
            $db                     = MysqliDb::getInstance();
            $language               = General::$currentLanguage;
            $translations           = General::$translations;
            $imageGroupUniqueChar   = self::generateUniqueChar();
            $dateTime               = date("Y-m-d H:i:s");

            $name            = trim($params['name']);
            $code            = trim($params['code']);
            $packageQuantity = trim($params['packageQuantity']);
            $product         = $params['product'];
            $category        = $params['category'];
            $priceSetting    = $params['priceSetting'];
            $pvPrice         = $params['pvPrice'];
            $activeDate      = $params['activeDate'];
            $nameLanguages   = $params['nameLanguages'];
            $descrLanguages  = $params['descrLanguages'];
            $uploadImage     = $params['uploadImage'];
            $uploadVideo     = $params['uploadVideo'];
            $status          = trim($params['status']);
            $statusAry       = array('Active', 'Inactive');
            $todayDate       = strtotime(date("Y-m-d"));

            $createPackage   = $params['createPackage'];
            $productInvID    = $params['productInvID'];
            $productQuantity = $params['productQuantity'];

            $catalogue       = $params['catalogue'];
            $bBasic          = $params['bBasic'];
            $weight          = trim($params["weight"]);

            $userID = $db->userID;
            $site = $db->userType;

            if(!$userID) {
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["A01078"][$language] /* Access denied. */,'data'=>'');
            } else {
                $db->where("id", $userID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid Admin */,'data'=>'');
                }
            }

            if($starterKit){
                $params['isStarterKit'] = $starterKit;
            }
            $verify = self::verifyPackageDetail($params, 'add');
            if($verify["status"] != "ok") return $verify;

            $isUnlimited = 1;
            if($packageQuantity > 0){
                $isUnlimited = 0;
            }

            // Insert mlm_product
            $insertPackage = array(
                "code"          => $code,
                "name"          => $name,
                "weight"        => $weight,
                "pv_price"      => $pvPrice,
                "total_balance" => $packageQuantity,
                "is_starter_kit"=> $starterKit ? 1 : 0,
                "is_unlimited"  => $isUnlimited,
                "status"        => $status,
                "active_at"     => $dateTime,
                "created_at"    => $dateTime,
                "updated_at"    => $dateTime
            );

            $packageID = $db->insert('mlm_product', $insertPackage);

            if (!$packageID) {
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00932"][$language] /* Failed to add product. */,'data'=>'');
            }

            // insert mlm_product_setting
            foreach ($category as $categoryRow) {
                $insertPackageCategory = array(
                    "product_id" => $packageID,
                    "name"  => "packageCategory",
                    "value" => $categoryRow,
                    "type"  => "packageCategory"
                );
                $db->insert('mlm_product_setting', $insertPackageCategory);
            }
            // temporary close catalogue - 20220114

            // if($catalogue){
            //     unset($insertData);
            //     $insertData = array(
            //         "product_id" => $packageID,
            //         "name"  => "catalogue",
            //         "value" => 1,
            //         "type"  => "catalogue"
            //     );
            //     $db->insert('mlm_product_setting', $insertData);
            // }

            if($bBasic){
                unset($insertData);
                $insertData = array(
                    "product_id" => $packageID,
                    "name"  => "bBasic",
                    "value" => 1,
                    "type"  => "bBasic"
                );
                $db->insert('mlm_product_setting', $insertData);
            }

            // Price checking
            foreach($priceSetting as $price){
                if($price['retailPrice'] <= 0){
                    continue;
                }
                if(!$price['promoPrice']){
                    $price['promoPrice'] = 0;
                }

                // insert mlm_product_price
                $insertPrice = array(
                    "product_id"    =>  $packageID,
                    "country_id"    =>  $price['country'],
                    "price"         =>  $price['retailPrice'],
                    "promo_price"   =>  $price['promoPrice'],
                    "m_price"       =>  $price['memberPrice'],
                    "ms_price"      =>  $price['memberUpPrice'],
                    "disabled"      =>  0,
                    "updated_at"    =>  $dateTime,
                );
                $db->insert('mlm_product_price', $insertPrice);

               $priceAry[] = $price['country'];
            }

            $db->where('id', $userID);
            $adminName = $db->getValue('admin', 'name');
            $db->where('id', $priceAry, 'IN');
            $countryNameList = $db->get('country', null, 'translation_code');
            foreach($countryNameList as $translationCodeForCountry){
                $nameOfCountry[] = $translations[$translationCodeForCountry['translation_code']][$language];
            }
            $logInvData = array_merge(array('admin' => $adminName,'country' => $nameOfCountry, 'package' => $name));

            // insert inv_log
            $invLog = array(
                "module"                    =>  "mlm_product_price",
                "module_id"                 =>  $packageID,
                "title_transaction_code"    =>  "T00059",
                "title"                     =>  "Add product price",
                "transaction_code"          =>  "L00085",
                "data"                      =>  json_encode($logInvData),
                "creator_type"              =>  $site,
                "creator_id"                =>  $userID,
                "created_at"                =>  $dateTime
            );
            $db->insert("inv_log", $invLog);

            // insert inv_product_detail
            if($createPackage == 1){
                $insertValidPackage = array(
                    "inv_product_id"    => $productInvID,
                    "name"              => "validPackage",
                    "value"             => $packageID,
                    "type"              => "validPackage",
                    "reference"         => 1,
                );
                $db->insert('inv_product_detail', $insertValidPackage);
            }else{
                foreach ($product as $productRow) {
                    $insertValidPackage = array(
                        "inv_product_id"    => $productRow['productID'],
                        "name"              => "validPackage",
                        "value"             => $packageID,
                        "type"              => "validPackage",
                        "reference"         => $productRow['quantity'],
                    );
                    $db->insert('inv_product_detail', $insertValidPackage);
                }
            }


            // Get System Languages
            $db->where("disabled", 0);
            $languages = $db->map("language_code")->get("languages", NULL, "language_code, language");

            // Insert language_translation
            foreach($nameLanguages as $nameRow) {
                $defaultName = $name;
                $nameLanguagesList[$nameRow['languageType']] = $nameRow;
            }

            foreach($languages as $languagesRow) {
                if($nameLanguagesList[$languagesRow]['languageType'] == $languagesRow) {
                    unset($insertPackageNameTrans);
                    $insertPackageNameTrans = array(
                        "module" => "mlm_product",
                        "module_id" => $packageID,
                        "type" => "name",
                        "language" => $languagesRow,
                        "content" => $nameLanguagesList[$languagesRow]['content'],
                        "updated_at" => $dateTime,
                    );
                    $insertPackageNameTransList['name'][] = $insertPackageNameTrans;
                    $db->insert('inv_language',$insertPackageNameTrans);
                } /*else {
                    $insertPackageNameTrans = array(
                        "module" => "mlm_product",
                        "module_id" => $packageID,
                        "type" => "name",
                        "language" => $languagesRow,
                        "content" => $defaultName,
                        "updated_at" => $dateTime,
                    );
                }*/
                // $insertPackageNameTransList['name'][] = $insertPackageNameTrans;
                // $db->insert('inv_language',$insertPackageNameTrans);
            }

            foreach($descrLanguages as $descriptionRow) {
                if($descriptionRow['content'] && !$defaultDescription) $defaultDescription = $descriptionRow['content'];
                $descLanguagesList[$descriptionRow['languageType']] = $descriptionRow;
            }

            foreach($languages as $languagesRow) {
                if($descLanguagesList[$languagesRow]['languageType'] == $languagesRow) {
                    unset($insertProductDescrTrans);
                    $insertProductDescrTrans = array(
                        "module" => "mlm_product",
                        "module_id" => $packageID,
                        "type" => "desc",
                        "language" => $languagesRow,
                        "content" => $descLanguagesList[$languagesRow]['content'],
                        "updated_at" => $dateTime,
                    );
                    $insertProductDescrTranList['desc'][] = $insertProductDescrTrans;
                    $db->insert('inv_language',$insertProductDescrTrans);
                } /*else {
                    $insertProductDescrTrans = array(
                        "module" => "mlm_product",
                        "module_id" => $packageID,
                        "type" => "desc",
                        "language" => $languagesRow,
                        "content" => $defaultDescription,
                        "updated_at" => $dateTime,
                    );
                }*/
                // $insertProductDescrTranList['desc'][] = $insertProductDescrTrans;
                // $db->insert('inv_language',$insertProductDescrTrans);
            }

            foreach($uploadImage as $key => $uploadImageRow) {
                $imageUniqueChar = self::generateUniqueChar();
                $imageAry = explode(".",$uploadImageRow['imgName']);
                $imageExt = end($imageAry);
                $storedImage[] = time()."_".$imageUniqueChar."_".$imageGroupUniqueChar.".".$imageExt;
            }

            foreach($storedImage as $storedImageRow) {

                // insert mlm_product_setting
                $insertInvDetails = array(
                    "product_id" => $packageID,
                    "name" => "Image",
                    "value" => $storedImageRow,
                    "type" => "Image",
                );

                $insertInvDetailsRes = $db->insert('mlm_product_setting', $insertInvDetails);
                $insertInvImage[] = $insertInvDetails['value'];
            }

            foreach($uploadVideo as $key => $uploadVideoRow) {
                if(!$uploadVideoRow['videoName']) continue;
                $videoUniqueChar = self::generateUniqueChar();
                $videoAry = explode(".",$uploadVideoRow['videoName']);
                $videoExt = end($videoAry);
                $storedVideo[] = time()."_".$videoUniqueChar."_".$imageGroupUniqueChar.".".$videoExt;
            }

            foreach($storedVideo as $storedVideoRow) {

                // insert mlm_product_setting
                $insertInvDetails = array(
                    "product_id" => $packageID,
                    "name" => "Video",
                    "value" => $storedVideoRow,
                    "type" => "Video",
                );

                $insertInvDetailsRes = $db->insert('mlm_product_setting', $insertInvDetails);
                $insertInvVideo[] = $insertInvDetails['value'];
            }

            // Update system setting for process get product
            $db->where('name', 'processGetProduct');
            $db->update('system_settings', array('value' => 0));

            if($insertInvImage && $insertInvVideo){
                $data["imgName"]    = $insertInvImage;
                $data["videoName"]  = $insertInvVideo;
            }elseif($insertInvImage){
                $data["imgName"]    = $insertInvImage;
            }elseif($insertInvVideo){
                $data["imgName"]    = $insertInvVideo;
            }

            $data["doRegion"]     = Setting::$configArray["doRegion"];
            $data["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            $data["doAccessKey"]  = Setting::$configArray["doApiKey"];
            $data["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            $data["doBucketName"] = Setting::$configArray["doBucketName"]."inv";
            $data["doProjectName"]= Setting::$configArray["doProjectName"];
            $data["doFolderName"] = Setting::$configArray["doFolderName"]."inv";

            if(empty($insertPackage)) $insertPackage = array();
            if(empty($insertInvDetails)) $insertInvDetails = array();
            if(empty($insertPackageCategory)) $insertPackageCategory = array();
            if(empty($insertValidCountry)) $insertValidCountry = array();
            if(empty($insertValidPackage)) $insertValidPackage = array();
            if(empty($insertProductNameTransList)) $insertProductNameTransList = array();
            if(empty($insertProductDescrTranList)) $insertProductDescrTranList = array();
            if(empty($invLog)) $invLog = array();
            if(empty($insertPrice)) $insertPrice = array();
            $activityData = array_merge(array('admin' => $checkAdmin, 'catalogue' => $catalogue, 'bBasic' => $bBasic), $insertPackage, $insertInvDetails, $insertPackageCategory, $insertValidCountry, $insertValidPackage, $insertProductNameTransList, $insertProductDescrTranList, $invLog, $insertPrice);

            // Insert Activity Log
            $activityRes = Activity::insertActivity('Add Package', 'T00056', 'L00076', $activityData, $userID, $userID, $site);

            if(!$activityRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            if($insertInvImage && $insertInvVideo) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> $data);
            } else if($insertInvImage) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> $data);
            } else if($insertInvVideo) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> $data);
            } else {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> $data);
            }
        }

        public function editPackageDetail($params, $starterKit) {
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTime        = date("Y-m-d H:i:s");

            $packageID       = trim($params['packageID']);
            $name            = trim($params['name']);
            $code            = trim($params['code']);
            $product         = $params['product'];
            $category        = $params['category'];
            $priceSetting    = $params['priceSetting'];
            $pvPrice         = $params['pvPrice'];
            $activeDate      = $params['activeDate'];
            $nameLanguages   = $params['nameLanguages'];
            $descrLanguages  = $params['descrLanguages'];
            $uploadImage     = $params['uploadImage'];
            $uploadVideo     = $params['uploadVideo'];
            $status          = trim($params['status']);
            $statusAry       = array('Active', 'Inactive');
            $todayDate       = strtotime(date("Y-m-d"));
            $adminRoleList   = Setting::$systemSetting['InvEditableRoles'];
            $adminRoleListAry = explode("#", $adminRoleList);

            $createPackage   = $params['createPackage'];
            $productInvID    = $params['productInvID'];
            $productQuantity = $params['productQuantity'];

            $catalogue       = $params['catalogue'];
            $bBasic          = $params['bBasic'];
            $weight          = trim($params["weight"]);

            $imageGroupUniqueChar = self::generateUniqueChar();

            $userID = $db->userID;
            $site = $db->userType;

            if(!$userID) {
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["A01078"][$language] /* Access denied. */,'data'=>'');
            } else {
                $db->where("id", $userID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid admin */,'data'=>'');
                }
            }

            if($starterKit){
                $params['isStarterKit'] = $starterKit;
            }
            $verify = self::verifyPackageDetail($params, "edit");
            if($verify["status"] != "ok") return $verify;

            if(!$packageID) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00933"][$language] /* Product not found */, 'data' => '');
            }

            $db->where("id", $packageID);
            $packageRecord = $db->getValue("mlm_product", "id");

            if(!$packageRecord) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00933"][$language] /* Product not found */, 'data' => '');
            }

            $db->where('role_id',$adminRoleListAry, 'IN');
            $availableAdminRes = $db->getValue('admin','id',null);

            // Edit mlm_product
            if(in_array($userID, $availableAdminRes)){
                $editPackage = array(
                    "code"          => $code,
                    "name"          => $name,
                    "weight"        => $weight,
                    "pv_price"      => $pvPrice,
                    "total_balance" => $productQuantity,
                    "status"        => $status,
                    "active_at"     => $dateTime,
                    "updated_at"    => $dateTime
                );
            }else{
                $editPackage = array(
                    "code"          => $code,
                    "name"          => $name,
                    "weight"        => $weight,
                    "pv_price"      => $pvPrice,
                    "status"        => $status,
                    "active_at"     => $dateTime,
                    "updated_at"    => $dateTime
                );
            }
            $db->where("id", $packageID);
            $editPackageRes = $db->update("mlm_product", $editPackage);

            if (!$editPackageRes) {
                return array('status' => 'error', 'code' => 2,'statusMsg' => $translations["E00934"][$language] /* Failed to update product. */, 'data' => '');
            }

            // Get Original Product Category
            $db->where("product_id", $packageID);
            $db->where("type", "packageCategory");
            $oriCategory = $db->map("value")->get("mlm_product_setting", null, "value");

            // Update mlm_product_setting
            foreach ($category as $categoryRow) {
                $categoryAry[$categoryRow] = $categoryRow;
            }

            foreach ($oriCategory as $oriCategoryKey => $oriCategoryValue) {
                $newCategory = $categoryAry[$oriCategoryKey];

                if($oriCategoryValue != $newCategory){
                    $editDetail = array(
                        "type" => "invalidCategory",
                    );
                    $db->where("product_id", $packageID);
                    $db->where("value", $oriCategoryValue);
                    $db->where("type", "packageCategory");
                    $db->update("mlm_product_setting", $editDetail);
                }

                unset($categoryAry[$oriCategoryKey]);

                $newCategoryAry = $categoryAry;
            }

            foreach ($newCategoryAry as $newCategoryRow) {
                $insertNewCategory = array(
                    "product_id" => $packageID,
                    "name"  => "packageCategory",
                    "value" => $newCategoryRow,
                    "type"  => "packageCategory"
                );
                $db->insert('mlm_product_setting', $insertNewCategory);
            }

            // temporary close catalogue - 20220114

            // $db->where("product_id", $packageID);
            // $db->where("name", "catalogue");
            // $catalogueID = $db->getValue("mlm_product_setting", "id");
            // if($catalogueID){
            //     $db->where("id", $catalogueID);
            //     $db->update("mlm_product_setting", array('value' => $catalogue));
            // }else{
            //     if($catalogue){
            //         unset($insertData);
            //         $insertData = array(
            //             "product_id" => $packageID,
            //             "name"  => "catalogue",
            //             "value" => 1,
            //             "type"  => "catalogue",
            //         );
            //         $db->insert('mlm_product_setting', $insertData);
            //     }
            // }


            $db->where("product_id", $packageID);
            $db->where("name", "bBasic");
            $bBasicID = $db->getValue("mlm_product_setting", "id");
            if($bBasicID){
                $db->where("id", $bBasicID);
                $db->update("mlm_product_setting", array('value' => $bBasic));
            }else{
                if($bBasic){
                    unset($insertData);
                    $insertData = array(
                        "product_id" => $packageID,
                        "name"  => "bBasic",
                        "value" => 1,
                        "type"  => "bBasic"
                    );
                    $db->insert('mlm_product_setting', $insertData);
                }
            }

            // Country checking
            // if(in_array($userID, $availableAdminRes)){
                foreach($priceSetting as $countryAvailable){
                    $countryList['retailPrice'] = $countryAvailable['retailPrice'];
                    $countryList['promoPrice'] = $countryAvailable['promoPrice'];
                    $countryList['memberPrice'] = $countryAvailable['memberPrice'];
                    $countryList['memberUpPrice'] = $countryAvailable['memberUpPrice'];

                    $enabledCountry[$countryAvailable['country']] = $countryList;
                }

                $db->where('disabled', '0');
                $db->where('product_id', $packageID);
                $priceChecking = $db->map('country_id')->get('mlm_product_price', null, 'country_id, price, promo_price, m_price, ms_price');


                foreach($priceChecking as $priceCheckingKey => $priceCheckingValue){
                    $newCountryRetail   = $enabledCountry[$priceCheckingKey]['retailPrice'];
                    $newCountryPromo    = $enabledCountry[$priceCheckingKey]['promoPrice'];
                    $newCountryMember   = $enabledCountry[$priceCheckingKey]['memberPrice'];
                    $newCountryMemberUp = $enabledCountry[$priceCheckingKey]['memberUpPrice'];
                    $oriCountryRetail   = $priceCheckingValue['price'];
                    $oriCountryPromo    = $priceCheckingValue['promo_price'];
                    $oriCountryMember   = $priceCheckingValue['m_price'];
                    $oriCountryMemberUp = $priceCheckingValue['ms_price'];

                    if(empty($enabledCountry[$priceCheckingKey])){
                        continue;
                    }

                    if($newCountryRetail != $oriCountryRetail || $newCountryPromo != $oriCountryPromo || $newCountryMember != $oriCountryMember || $newCountryMemberUp != $oriCountryMemberUp){
                        $updatePrice = array(
                            "price"         =>  $newCountryRetail,
                            "promo_price"   =>  $newCountryPromo,
                            "m_price"       =>  $newCountryMember,
                            "ms_price"      =>  $newCountryMemberUp,
                            "updated_at"    =>  $dateTime
                        );
                        $db->where('country_id', $priceCheckingKey);
                        $db->where('product_id',$packageID);
                        $db->update('mlm_product_price', $updatePrice);
                    }
                    unset($enabledCountry[$priceCheckingKey]);
                    unset($priceChecking[$priceCheckingKey]);
                    $disabledCountry = $priceChecking;
                    $newEnabledCountry = $enabledCountry;
                    $priceAry[] = $priceCheckingKey;
                }

                foreach($disabledCountry as $key => $value){
                    $updatePrice = array(
                        "disabled" => 1,
                    );
                    $db->where('country_id', $key);
                    $db->update('mlm_product_price', $updatePrice);
                }

                foreach($newEnabledCountry as $newEnabledCountryKey => $newEnabledCountryValue){
                    $insertPrice = array(
                        "product_id"    =>  $packageID,
                        "country_id"    =>  $newEnabledCountryKey,
                        "price"         =>  $newEnabledCountryValue['retailPrice'],
                        "promo_price"   =>  $newEnabledCountryValue['promoPrice'],
                        "m_price"       =>  $newEnabledCountryValue['memberPrice'],
                        "ms_price"      =>  $newEnabledCountryValue['memberUpPrice'],
                        "disabled"      =>  0,
                        "updated_at"    =>  $dateTime,
                    );
                    $db->insert('mlm_product_price', $insertPrice);
                }

                if($priceAry){
                    $db->where('id', $priceAry, 'IN');
                    $countryNameList = $db->get('country', null, 'translation_code');
                    foreach($countryNameList as $translationCodeForCountry){
                        $nameOfCountry[] = $translations[$translationCodeForCountry['translation_code']][$language] ? : "-";
                    }
                }

                $logInvData = array_merge(array('admin' => $checkAdmin, 'country' => $nameOfCountry, 'package' => $name, 'pvPrice' => $pvPrice, 'productQuantity' => $productQuantity));

                // insert inv_log
                $invLog = array(
                    "module"                    =>  "mlm_product, mlm_product_price",
                    "module_id"                 =>  $packageID,
                    "title_transaction_code"    =>  "T00057",
                    "title"                     =>  "Edit Package",
                    "transaction_code"          =>  "L00089",
                    "data"                      =>  json_encode($logInvData),
                    "creator_type"              =>  $site,
                    "creator_id"                =>  $userID,
                    "created_at"                =>  $dateTime
                );
                $db->insert("inv_log", $invLog);
            // }

            // Get Original Product
            $db->where("value", $packageID);
            $db->where("type", "validPackage");
            $oriProductList = $db->map("inv_product_id")->get("inv_product_detail", null, "inv_product_id, value, reference");

            // Update inv_product_detail
            foreach ($product as $productRow) {
                $invProduct['productID'] = $productRow['productID'];
                $invProduct['quantity'] = $productRow['quantity'];

                $productAry[$productRow['productID']] = $invProduct;
            }

            foreach ($oriProductList as $oriProductKey => $oriProductValue) {
                $newProductID   = $productAry[$oriProductKey]['productID'];
                $newQuantity    = $productAry[$oriProductKey]['quantity'];
                $oriProductID   = $oriProductValue['inv_product_id'];
                $oriQuantity    = $oriProductValue['reference'];

                if($oriProductID != $newProductID){
                    $updateDetail = array(
                        "type" => "invalidPackage",
                    );
                    $db->where("inv_product_id", $oriProductID);
                    $db->where("value", $packageID);
                    $db->where("type", "validPackage");
                    $db->update("inv_product_detail", $updateDetail);
                }

                if($oriProductID == $newProductID && $oriQuantity != $newQuantity){
                    $editDetail = array(
                        "reference" => $newQuantity,
                    );
                    $db->where("inv_product_id", $oriProductID);
                    $db->where("value", $packageID);
                    $db->where("type", "validPackage");
                    $db->update("inv_product_detail", $editDetail);
                }

                unset($productAry[$oriProductKey]);

                $newProductAry = $productAry;
            }

            foreach ($newProductAry as $newProductRow) {
                $insertNewProduct = array(
                    "inv_product_id" => $newProductRow["productID"],
                    "name"  => "validPackage",
                    "value" => $packageID,
                    "type"  => "validPackage",
                    "reference" => $newProductRow["quantity"],
                );
                $db->insert('inv_product_detail', $insertNewProduct);
            }

            $editMediaCount = 0;

            foreach ($uploadImage as $uploadFileName) {
                $checkFileAry[$uploadFileName['imgName']] = $uploadFileName;
                $editMediaCount++;
            }

            $db->where('product_id', $packageID);
            $db->where("type", array('Image'), 'IN');
            $trashMediaNameAry = $db->get('mlm_product_setting', null, 'id, value');
            foreach ($trashMediaNameAry as $trashMediaRow) {
                if(!$checkFileAry[$trashMediaRow['value']]) {
                    // Insert media trash
                    $insertTrash = array(
                        'file_name' => $trashMediaRow["value"],
                        'created_at'=> $dateTime,
                        'deleted'   => '0'
                    );
                    $db->insert('media_trash', $insertTrash);

                    $oldImages['oldImages'][] = $insertTrash;

                    $db->where("id", $trashMediaRow["id"]);
                    $db->update("mlm_product_setting", array("type" => "Inactive Image"));
                } else {
                    unset($checkFileAry[$trashMediaRow["value"]]);
                }
            }

            if($checkFileAry) {
                foreach ($checkFileAry as $checkFileName) {
                    if($checkFileName['imgFlag'] == 0) {
                        unset($checkFileAry[$checkFileName["imgName"]]);
                    } else {
                        // Insert inv_product_detail
                        $imageUniqueChar = self::generateUniqueChar();
                        $imageAry = explode(".", $checkFileName['imgName']);
                        $imageExt = end($imageAry);
                        $newImage = time()."_".$imageUniqueChar."_".$imageGroupUniqueChar.".".$imageExt;

                        $newImageDetails = array(
                            "product_id" => $packageID,
                            "name" => "Image",
                            "value" => $newImage,
                            "type" => "Image"
                        );

                        $db->insert('mlm_product_setting', $newImageDetails);

                        $newImages[] = $newImageDetails["value"];
                    }
                }
            }

            unset($editMediaCount);
            unset($checkFileAry);
            $editMediaCount = 0;

            foreach ($uploadVideo as $uploadFileName) {
                if(!$uploadFileName['videoName']) continue;
                $checkFileAry[$uploadFileName['videoName']] = $uploadFileName;
                $editMediaCount++;
            }

            $db->where('product_id', $packageID);
            $db->where("type", array('Video'), 'IN');
            $trashMediaNameAry = $db->get('mlm_product_setting', null, 'id, value');

            foreach ($trashMediaNameAry as $trashMediaRow) {
                if(!$checkFileAry[$trashMediaRow['value']]) {
                    // Insert media trash
                    $insertTrash = array(
                        'file_name' => $trashMediaRow["value"],
                        'created_at'=> $dateTime,
                        'deleted'   => '0'
                    );
                    $db->insert('media_trash', $insertTrash);

                    $oldImages['oldImages'][] = $insertTrash;

                    $db->where("id", $trashMediaRow["id"]);
                    $db->update("mlm_product_setting", array("type" => "Inactive Video"));
                } else {
                    unset($checkFileAry[$trashMediaRow["value"]]);
                }
            }

            if($checkFileAry) {
                foreach ($checkFileAry as $checkFileName) {
                    if($checkFileName['videoFlag'] == 0) {
                        unset($checkFileAry[$checkFileName["videoName"]]);
                    } else {
                        // Insert inv_product_detail
                        $videoUniqueChar = self::generateUniqueChar();
                        $videoAry = explode(".", $checkFileName['videoName']);
                        $videoExt = end($videoAry);
                        $newVideo = time()."_".$videoUniqueChar."_".$imageGroupUniqueChar.".".$videoExt;

                        $newVideoDetails = array(
                            "product_id" => $packageID,
                            "name" => "Video",
                            "value" => $newVideo,
                            "type" => "Video"
                        );

                        $db->insert('mlm_product_setting', $newVideoDetails);

                        $newVideos[] = $newVideoDetails["value"];
                    }
                }
            }

            // Get System Languages
            $db->where("disabled", 0);
            $languages = $db->map("language_code")->get("languages", NULL, "language_code, language");

            $db->where("module_id", $packageRecord);
            $db->where("module", "mlm_product");
            $db->where("type", "name");
            $translationList = $db->get("inv_language", NULL, "id, language, content");

            // Update language_translation
            foreach ($nameLanguages as $nameRow) {
                $defaultName = $name;
                $nameLanguagesList[$nameRow['languageType']] = $nameRow;
            }

            foreach($translationList as $translationRow){
                $existingLangAry[] = $translationRow['language'];
            }

            foreach($languages as $languagesRow){
                if($nameLanguagesList[$languagesRow]['languageType'] == $languagesRow){
                    if(in_array($nameLanguagesList[$languagesRow]['languageType'], $existingLangAry)){
                        unset($updateTranslation);
                        $updateTranslation = array(
                            "content" => $nameLanguagesList[$languagesRow]['content'],
                            "updated_at" => $dateTime
                        );

                        $db->where("module_id", $packageRecord);
                        $db->where("module", "mlm_product");
                        $db->where("type", "name");
                        $db->where("language", $languagesRow);
                        $db->update("inv_language", $updateTranslation);

                        $updateTranslationList['updateList'][] = $updateTranslation;
                    } else{
                        unset($insertPackageNameTrans);
                        $insertPackageNameTrans = array(
                            "module" => "mlm_product",
                            "module_id" => $packageRecord,
                            "type" => "name",
                            "language" => $languagesRow,
                            "content" => $nameLanguagesList[$languagesRow]['content'],
                            "updated_at" => $dateTime,
                        );
                        $insertPackageNameTransList['name'][] = $insertPackageNameTrans;
                        $db->insert('inv_language',$insertPackageNameTrans);
                    }
                }
            }

            $db->where("module_id", $packageRecord);
            $db->where("module", "mlm_product");
            $db->where("type", "desc");
            $descrTranslationList = $db->get("inv_language", NULL, "id, language, content");
            // Update language_translation
            foreach ($descrLanguages as $descrLanguagesRow) {
                if($descrLanguagesRow['content'] && !$defaultDescrName) $defaultDescrName = $descrLanguagesRow['content'];
                $descrLanguagesList[$descrLanguagesRow['languageType']] = $descrLanguagesRow;
            }

            foreach($descrTranslationList as $descrTranslationRow){
                $existingDescrLangAry[] = $descrTranslationRow['language'];
            }

            foreach($languages as $languagesRow){
                if($descrLanguagesList[$languagesRow]['languageType'] == $languagesRow){
                    if(in_array($descrLanguagesList[$languagesRow]['languageType'], $existingDescrLangAry)){
                        $updateDescrTranslation = array(
                            "content" => $descrLanguagesList[$languagesRow]['content'],
                            "updated_at" => $dateTime
                        );
                        $db->where("module_id", $packageRecord);
                        $db->where("module", "mlm_product");
                        $db->where("type", "desc");
                        $db->where("language", $languagesRow);
                        $db->update("inv_language", $updateDescrTranslation);

                        $updateDescrTranslationList['updateDescList'][] = $updateDescrTranslation;
                    }else{
                        unset($insertProductDescrTrans);
                        $insertProductDescrTrans = array(
                            "module" => "mlm_product",
                            "module_id" => $packageRecord,
                            "type" => "desc",
                            "language" => $languagesRow,
                            "content" => $descrLanguagesList[$languagesRow]['content'],
                            "updated_at" => $dateTime,
                        );
                        $insertProductDescrTranList['desc'][] = $insertProductDescrTrans;
                        $db->insert('inv_language',$insertProductDescrTrans);
                    }
                }
            }

            // Update system setting for process get product
            $db->where('name', 'processGetProduct');
            $db->update('system_settings', array('value' => 0));

            if($newImages && $newVideos){
                $data["imgName"]    = $newImages;
                $data["videoName"]  = $newVideos;
            }elseif($newImages){
                $data["imgName"]    = $newImages;
            }elseif($newVideos){
                $data["videoName"]  = $newVideos;
            }

            $data["doRegion"]     = Setting::$configArray["doRegion"];
            $data["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            $data["doAccessKey"]  = Setting::$configArray["doApiKey"];
            $data["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            $data["doBucketName"] = Setting::$configArray["doBucketName"]."inv";
            $data["doProjectName"]= Setting::$configArray["doProjectName"];
            $data["doFolderName"] = Setting::$configArray["doFolderName"]."inv";

            if(empty($insertPackageNameTransList)) $insertPackageNameTransList = array();
            if(empty($insertProductDescrTranList)) $insertProductDescrTranList = array();

            if($oldImages && $newImages) {
                $activityData = array_merge(array('admin' => $checkAdmin), $editPackage, $editCategory, $editCountry, $editValidPackage, $oldImages, $newImages, $updateTranslationList, $updateDescrTranslationList, $insertProductDescrTranList, $insertProductDescrTranList, $disabledPrice, $updatePrice, $invLog);
            } else {
                $activityData = array_merge(array('admin' => $checkAdmin), $editPackage, $editCategory, $editCountry, $editValidPackage, $updateTranslationList, $updateDescrTranslationList, $insertProductDescrTranList, $insertProductDescrTranList, $disabledPrice, $updatePrice, $invLog);
            }

            // Insert Activity Log
            $activityRes = Activity::insertActivity('Edit Package', 'T00057', 'L00077', $activityData, $userID, $userID, $site);

            if(!$activityRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            if($newImages && $newVideos) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00370"][$language] /* Successfully Updated */ , 'data'=> $data);
            } else if($newImages) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00370"][$language] /* Successfully Updated */ , 'data'=> $data);
            } else if($newVideo) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00370"][$language] /* Successfully Updated */ , 'data'=> $data);
            } else {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00370"][$language] /* Successfully Updated */ , 'data'=> $data);
            }
        }

        // -------- Product --------//
        public function getProductInventory($params){
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $limit           = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'productName':
                            $db->where('p.name', "%" . $dataValue . "%", "LIKE");
                            break;

                        case 'code':
                            $db->where("p.barcode", "%". $dataValue . "%" , "LIKE");
                            break;

                        case 'status':
                            if($dataValue == 'active')
                            {
                                $dataValue = 0;
                            }
                            else
                            {
                                $dataValue = 1;
                            }
                            $db->where("p.deleted", $dataValue);
                            break;

                        case 'createdAt':
                            $columnName = 'DATE(p.created_at)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            $columnName = 'DATE(p.updated_at)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->orderBy('p.created_at', 'DESC');
            $db->join('vendor v', 'v.id = p.vendor_id', 'LEFT');
            $copyDb = $db->copy();
            $productInv = $db->get("product p", $limit, "p.id, p.barcode as skuCode, p.name, p.product_type, p.description, p.categ_id, p.cost, p.sale_price, p.cooking_time, p.deleted as status, p.expired_day, p.cooking_suggestion, p.full_instruction, p.full_instruction2, p.created_at, p.updated_at, v.name as vendorName");

            if(empty($productInv)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            // foreach($productInv as $productInvRow){
            //     if($productInvRow['updater_id']) $updaterAry[$productInvRow['updater_id']] = $productInvRow['updater_id'];
            //     $productInvID[$productInvRow['id']] = $productInvRow['id'];
            // }

            // if($updaterAry){
            //     $db->where("id", $updaterAry, "IN");
            //     $adminIDAry = $db->map("id")->get("admin", null, "id, username");
            // }

            // if($productInvID){
            //     $db->where('inv_product_id', $productInvID, 'IN');
            //     $db->where('type', 'productCategory');
            //     $categoryRes = $db->get('inv_product_detail', null, 'inv_product_id, value');

            //     foreach ($categoryRes as $categoryRow) {
            //         $categoryIDAry[$categoryRow['inv_product_id']][] = $categoryRow['value'];
            //     }

            //     $db->where('inv_product_id', $productInvID, 'IN');
            //     $db->groupBy('inv_product_id');
            //     $stockQuantity = $db->map('inv_product_id')->get('inv_stock',null,'inv_product_id, SUM(stock_in) as stock_in, SUM(stock_out) as stock_out');

            // }

            $db->where('deleted', 0);
            $categoryAry = $db->map('id')->get('product_category', null, 'id');

            if($categoryAry) {
                $db->where('module_id', $categoryAry, 'IN');
                $db->where('language', $language);
                $db->where('module', 'category');
                $categoryLang = $db->map('module_id')->get('inv_language', null, 'module_id, content');
            }

            foreach($productInv as $productInvRow){
                $productDetail['id'] = $productInvRow['id'];
                $productDetail['skuCode'] = $productInvRow['skuCode'];
                $productDetail['name'] = $productInvRow['name'];

                if($productInvRow['status'] == 1) {
                    $productDetail['status'] = 'Inactive';
                    $productDetail['statusDisplay'] = General::getTranslationByName($productDetail['status']);
                } else {
                    $productDetail['status'] = 'Active';
                    $productDetail['statusDisplay'] = General::getTranslationByName($productDetail['status']);
                }

                $productDetail['productType'] = $productInvRow['product_type'];
                $productDetail['description'] = $productInvRow['description'];
                $productDetail['cost'] = $productInvRow['cost'];
                $productDetail['salePrice'] = $productInvRow['sale_price'];
                $productDetail['expiredDay'] = $productInvRow['expired_day'];
                $productDetail['cookingTime'] = $productInvRow['cooking_time'];
                $productDetail['cookingSuggestion'] = $productInvRow['cooking_suggestion'];
                $productDetail['fullInstruction'] = $productInvRow['full_instruction'];
                $productDetail['fullInstruction2'] = $productInvRow['full_instruction2'];
                $productDetail['vendorName'] = $productInvRow['vendorName'];

                $productInvRow['categ_id'] = json_decode($productInvRow['categ_id'], true);
                
                unset($categoryDisplay);
                unset($productDetail['categoryDisplay']);
                foreach($productInvRow['categ_id'] as $val) {
                    $categoryDisplay[$category] = $categoryLang[$val];
                    $productDetail['categoryDisplay'][] = implode(', ', $categoryDisplay);
                }

                if(empty($productDetail['categoryDisplay'])) {
                    $productDetail['categoryDisplay'] = '';
                }

                // $productDetail['weight'] = Setting::setDecimal($productInvRow['weight'], 3) ? : '-';
                // $productDetail['totalStock'] = $stockQuantity[$productInvRow['id']]['stock_in'];
                // $productDetail['totalStockOut'] = $stockQuantity[$productInvRow['id']]['stock_out'];
                // $productDetail['quantity'] = $stockQuantity[$productInvRow['id']]['stock_in'] - $stockQuantity[$productInvRow['id']]['stock_out'];
                // $productDetail['isAdjustable'] = $productInvRow['status'] == "Active" ? 1 : 0;
                $productDetail['created_at'] = date($dateTimeFormat, strtotime($productInvRow['created_at']));
                // $productDetail['updater_id'] = $adminIDAry[$productInvRow['updater_id']] ? : '-';
                $productDetail['updated_at'] = $productInvRow['updated_at'] > 0 ? date($dateTimeFormat, strtotime($productInvRow['updated_at'])) : '-';
                $productInvList[] = $productDetail;
            }

            // if($params['type'] == "export"){
            //     $params['command'] = __FUNCTION__;
            //     $data = Excel::insertExportData($params);
            //     return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            // }

            $totalRecord              = $copyDb->getValue("product p", "count(p.id)");
            $data['productInventory'] = $productInvList;
            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            if($seeAll) {
                $data['totalPage']    = 1;
                $data['numRecord']    = $totalRecord;
            } else {
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['numRecord']    = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getProductInventoryDetails($params){
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            include('config.php');

            $productInvId = $params['productInvId'];

            $userID = $db->userID;
            $site = $db->userType;

            $db->where("disabled", 0);
            $availableLanguages = $db->get("languages", NULL, "id, language, language_code");

            foreach ($availableLanguages as $value) {
                $row = array(
                    "languageType" => $value['language'],
                    "languageDisplay" => $translations[$value['language_code']][$language]
                );

                $languageList[] = $row;
            }

            $data['languageList'] = $languageList;

            $db->where("deleted",0);
            $availableCategory = $db->get("product_category",null,"id, name");

            foreach($availableCategory as $value){
                $categoryIDAry[] = $value["id"];
            }

            if($categoryIDAry) {
                $db->where("module_id",$categoryIDAry,"IN");
                $db->where("module","category");
                $db->where("language",$language);
                $db->where("type","name");
                $categoryLang = $db->map('module_id')->get("inv_language",null,"module_id,content");
            }

            foreach($availableCategory as $value){
                $value["categoryDisplay"] = $categoryLang[$value["id"]];
                $categoryProductList[] = $value;
            }

            $data["categoryList"] = $categoryProductList;

            $db->where("deleted","0");
            $supplierRes = $db->get("vendor",null,"id,name,vendor_code");

            unset($supplierList);

            foreach($supplierRes as $supplierRow){
                unset($tempSupplier);

                $tempSupplier["supplierID"] = $supplierRow["id"];
                $tempSupplier["name"] = $supplierRow["name"];
                $tempSupplier["code"] = $supplierRow["vendor_code"];

                $supplierList[] = $tempSupplier;
            }

            $data["supplierList"] = $supplierList;

            unset($countryParams);
            $countryParams = array(
                "deliveryCountry" => "Yes"
            );
            $countryReturn = Country::getCountriesList($countryParams);
            $data["countryList"] = $countryReturn["data"]["countriesList"];

            $db->where('type', 'productType');
            $productType = $db->get('enumerators', null, 'name');

            $data['productType'] = $productType;

            $db->where("name","percentage");
            $marginPercen  = $db->getValue("system_settings","value");
            $data['discountPercentage'] = (int)$marginPercen;

            if($productInvId) {
                $db->where('id', $productInvId);
                $productInv = $db->getOne("product", "id, barcode as skuCode, name, product_type, description, cost, sale_price, cooking_time, expired_day, vendor_id, categ_id, deleted as status, cooking_suggestion, full_instruction, full_instruction2, created_at, updated_at");

                if(!$productInv) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01051"][$language], 'data' => "");
                } else {
                    if($productInv['status'] == 0) {
                        $productInv['status'] = 'Active';
                    } else {
                        $productInv['status'] = 'Inactive';
                    }

                    if(!empty($productInv['categ_id'])) {
                        $cateAry = json_decode($productInv['categ_id'], true);
                        $db->where('deleted', '0');
                        $db->where('id', $cateAry, 'IN');
                        $productCategory = $db->get('product_category', null, 'id, name');
                    } else {
                        $productCategory = '';
                    }

                    $db->where('deleted', 0);
                    $db->where('product_id', $productInvId);
                    $productVar = $db->get('product_template', null, 'product_attribute_value_id');

                    if(!empty($productVar)) {
                        // get all attribute value id in product template
                        $attrIdList = [];
                        foreach($productVar as $attr) {
                            foreach($attr as $val) {
                                foreach(json_decode($val, true) as $v) {
                                    if(empty($attrIdList)) {
                                        $attrIdList[] = $v;
                                    } else {
                                        if(!in_array($v, $attrIdList)) {
                                            $attrIdList[] = $v;
                                        }
                                    }
                                }
                            }
                        }

                        if(!empty($attrIdList)) {
                            // use the id in $attrIdList to find the product attribute and product attribute value
                            $db->where('pav.deleted', 0);
                            $db->where('pav.id', $attrIdList, 'IN');
                            $db->join('product_attribute pa', 'pa.id = pav.product_attribute_id', 'LEFT');
                            $productAttr = $db->get('product_attribute_value pav', null, 'pav.id, pav.name, pav.product_attribute_id as pa_id');

                            foreach($productAttr as $val) {
                                $attr[] = $val['pa_id'];

                                $attrVal[$val['pa_id']]['pa_id'] = $val['pa_id'];
                                $attrVal[$val['pa_id']]['id'][] = $val['id'];
                                $attrVal[$val['pa_id']]['name'][] = $val['name'];
                            }

                            $db->where('product_attribute_id', $attr, 'IN');
                            $attribute = $db->get('product_attribute_value', null, 'id, name, product_attribute_id');
                        }
                        $data['attrIdList'] = $attrVal;
                        $data['attribute'] = $attribute;
                        $data['productVar'] = $productVar;
                    } else {
                        $data['attrIdList'] = '';
                        $data['attribute'] = '';
                        $data['productVar'] = '';
                    }

                    if($productInv['product_type'] == 'Package') {
                        $db->where('deleted', 0);
                        $productList = $db->get('product', null, 'id, name');

                        $db->where('pi.deleted', 0);
                        $db->where('pi.package_id', $productInv['id']);
                        $db->join('product p', 'p.id = pi.product_id', 'LEFT');
                        $packageProduct = $db->get('package_item pi', null, 'pi.id, pi.package_id, pi.product_id, p.name');
                        
                        $data['packageProduct'] = $packageProduct;
                        $data['productList'] = $productList;
                    } else {
                        $data['packageProduct'] = '';
                    }

                    $db->where('deleted', 0);
                    $db->where('reference_id', $productInv['id']);
                    $productMedia = $db->get('product_media', null, 'id, type, url, reference_id');

                    if($productMedia) {
                        foreach($productMedia as $val) {
                            if($val['type'] == 'video') {
                                $media['id'] = $val['id'];
                                $media['url'] = $val['url'];
                                $media['type'] = $val['type'];
                                $media['reference_id'] = $val['reference_id'];
                            } else {
                                $media['id']   = $val['id'];
                                $media['url']  = $val['url'];
                                $media['name'] = str_replace($config['tempMediaUrl'], '', $val['url']);
                                $media['type'] = $val['type'];
                                $media['reference_id'] = $val['reference_id'];
                            }
                            $mediaList[] = $media;
                        }
                    } else {
                        $mediaList = '';
                    }
                    $productInv['media'] = $mediaList;

                    $data['productDetails'] = $productInv;
                    $data['productCategory'] = $productCategory;
                    $data['nameTranslationList'] = array();
                    $data['descTranslationList'] = array();
                }
            }

            $db->where('deleted', '0');
            $productAttr = $db->get('product_attribute', null, 'id, name');

            $data['productAttrList'] = $productAttr;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data, $productVar);
        }

        public function verifyProductInventory($params, $type = "", $verify = false) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $name           = trim($params['invProductName']);
            $skuCode        = trim($params['skuCode']);
            $status         = trim($params['status']);
            $category       = $params['category'];
            // $weight         = trim($params['weight']);
            $statusAry      = array('Active', 'Inactive');
            $productType    = trim($params['productType']);
            $description    = trim($params['description']);
            $expired_day    = trim($params['expired_day']);
            $cost           = trim($params['cost']);
            $salePrice      = trim($params['salePrice']);
            $vendorId       = trim($params['vendorId']);
            $category       = $params['category'];
            $cookingTime    = trim($params['cookingTime']);
            $cookingSuggest = trim($params['cookingSuggest']);
            $fullInstruc    = $params['fullInstruc'];
            
            $isPackage      = trim($params['isPackage']) ? : 0;
            $priceSetting   = $params["priceSetting"];
            $pvPrice        = $params["pvPrice"];
            $catalogue      = $params["catalogue"];
            $bBasic         = $params["bBasic"];
            $activeDate     = $params['activeDate'];
            $uploadImage    = $params['uploadImage'];
            $uploadVideo    = $params['uploadVideo'];
            $packageQuantity= $params['packageQuantity'];
            $productQuantity= $params['productQuantity'];
            $packageCategory= $params["packageCategory"];
            $packageNameLanguages  = $params['packageNameLanguages'];
            $packageDescrLanguages = $params['packageDescrLanguages'];

            // Stock
            $isStock = trim($params["isStock"]) ? 1 : 0;
            $stockDate = trim($params["stockDate"]);
            $stockSupplierID = trim($params["stockSupplierID"]);
            $stockSupplierDO = trim($params["stockSupplierDO"]); // Optional field
            $stockQty = trim($params["stockQty"]);
            $stockCost = trim($params["stockCost"]);
            $stockFee = trim($params["stockFee"]);

            // Check Name Field
            if(!$name) {
                $errorFieldArr[] = array(
                    'id'  => "invProductNameError",
                    'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                );
            }

            // Check Code Field
            if(!$skuCode && $type != 'edit') {
                $errorFieldArr[] = array(
                    'id'  => "skuCodeError",
                    'msg' => $translations['E00928'][$language] /* Please Enter Code. */
                );
            } else if($type === 'add'){
                // check code avaibility
                $db->where('code', $skuCode);
                $result = $db->getOne("inv_product", 'id');

                if($result) {
                    $errorFieldArr[] = array(
                        'id'  => 'skuCodeError',
                        'msg' => $translations["E00929"][$language] /* Code Existed. */
                    );
                }
            }

            // Check Category Field
            if(!$category) {
                $errorFieldArr[] = array(
                    'id'  => "categoryError",
                    'msg' => $translations['E00930'][$language] /* Please Enter Category. */
                );
            } else {
                $db->where('deleted', 0);
                $availableCategory = $db->getValue('product_category', 'id', null);

                foreach($category as $val) {
                    if(!in_array($val, $availableCategory)) {
                        $errorFieldArr[] = array(
                            'id'  => "categoryError",
                            'msg' => $translations['E01012'][$language]
                        );
                    }
                }
            }

            if(!$productType) {
                $errorFieldArr[] = array(
                    'id'  => "productTypeError",
                    'msg' => $translations['E01172'][$language]
                );
            }

            if(!$description) {
                $errorFieldArr[] = array(
                    'id'  => "descriptionError",
                    'msg' => $translations['E00637'][$language]
                );
            }

            if(!$expired_day && $type != 'edit') {
                $errorFieldArr[] = array(
                    'id'  => "expiredDayError",
                    'msg' => $translations['E01165'][$language]
                );
            }

            if(!$cost) {
                $errorFieldArr[] = array(
                    'id'  => "costError",
                    'msg' => $translations['E01166'][$language]
                );
            }

            if(!$salePrice) {
                $errorFieldArr[] = array(
                    'id'  => "salePriceError",
                    'msg' => $translations['E01167'][$language]
                );
            }

            if(!$vendorId) {
                $errorFieldArr[] = array(
                    'id'  => "vendorNameError",
                    'msg' => $translations['E01168'][$language]
                );
            } else {
                $db->where('deleted', 0);
                $availableVendor = $db->getValue('vendor', 'id', null);

                if(!in_array($vendorId, $availableVendor)) {
                    $errorFieldArr[] = array(
                        'id'  => "vendorNameError",
                        'msg' => $translations['E01168'][$language]
                    );
                }
            }

            if(!$cookingTime) {
                $errorFieldArr[] = array(
                    'id'  => "cookingTimeError",
                    'msg' => $translations['E01169'][$language]
                );
            }

            if(!$cookingSuggest) {
                $errorFieldArr[] = array(
                    'id'  => "cookingSuggestionError",
                    'msg' => $translations['E01170'][$language]
                );
            }

            if(!$fullInstruc) {
                if($fullInstruc[0]) {
                    $errorFieldArr[] = array(
                        'id'  => "fullInstructionError",
                        'msg' => $translations['E01171'][$language]
                    );

                } else if ($fullInstruc[1]) {
                    $errorFieldArr[] = array(
                        'id'  => "fullInstruction2Error",
                        'msg' => $translations['E01171'][$language]
                    );
                }
            }

            if(!$uploadImage) {
                $errorFieldArr[] = array(
                    'id'  => "imgError",
                    'msg' => $translations['E01069'][$language]
                );
            }

            // Check Weight Field
            // if(!is_numeric($weight) || !$weight || $weight <= 0 ){
            //     $errorFieldArr[] = array(
            //         'id'  => "weightError",
            //         'msg' => $translations['E01010'][$language] /* Weight must be greater than 0 */
            //     );
            // }

            // Check Status Field
            if(!$status || !in_array($status, $statusAry)) {
                $errorFieldArr[] = array(
                    'id'  => "statusError",
                    'msg' => $translations['E00671'][$language] /* Please Select Status. */
                );
            }

            $db->where('disabled', 0);
            $countLanguage = $db->getValue('languages', 'count(*)');

            if($isPackage == 1){
                $params['createPackage'] = $isPackage;
                $params['name'] = $name;
                $params['category'] = $packageCategory;
                $params['code'] = $skuCode;
                $params['nameLanguages'] = $packageNameLanguages;
                $params['descrLanguages'] = $packageDescrLanguages;
                $package = self::verifyPackageDetail($params, "add");
                if($package["status"] != "ok") return $package;
            }

            // Stock
            if($isStock == 1 && $type == "add"){
                unset($stockParams);
                $stockParams = array(
                    "fromAddProduct" => $isStock,
                    "stockInDate" => $stockDate,
                    "supplierID" => $stockSupplierID,
                    "doNum" => $stockSupplierDO,
                    "quantity" => $stockQty,
                    "cost" => $stockCost,
                    "feeNCharges" => $stockFee,
                    "type" => "in",
                );
                $verifyStock = Self::adjustInvProduct($stockParams,"add");
                if($verifyStock["status"] != "ok") return $verifyStock;
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            if($verify){
                $db->where("id",$category);
                $categoryData = $db->get("inv_category",null,"name");

                $db->where("id",$stockSupplierID);
                $supplierData = $db->get("inv_supplier",null,"name,code");

                unset($countryIDAry);

                foreach($priceSetting as $priceRow){
                    $countryIDAry[$priceRow["country"]] = $priceRow["country"];
                }

                if($countryIDAry){
                    $db->where("id",$countryIDAry,"IN");
                    $countryData = $db->map("id")->get("country",null,"id,name,translation_code,currency_code");
                }

                foreach($priceSetting as &$priceRow){
                    $priceRow["promoPrice"] = Setting::setDecimal($priceRow["promoPrice"]);
                    $priceRow["retailPrice"] = Setting::setDecimal($priceRow["retailPrice"]);
                    $priceRow["countryName"] = $countryData[$priceRow["country"]]["name"];
                    $priceRow["countryDisplay"] = $translations[$countryData[$priceRow["country"]]["translation_code"]][$language];
                    $priceRow["countryCurrency"] = $countryData[$priceRow["country"]]["currency_code"];

                    unset($priceRow["translation_code"]);
                }

                unset($dataOut);
                $dataOut = array(
                    "name" => $name,
                    "skuCode" => $skuCode,
                    "status" => $status,
                    "statusDisplay" => General::getTranslationByName($status),
                    "category" => $category,
                    "categoryDisplay" => $categoryData,
                    "weight" => Setting::setDecimal($weight),

                    "isPackage" => $isPackage,
                    "priceSetting" => $priceSetting,
                    "activeDate" => date("d/m/Y",$activeDate),
                    "packageQuantity" => Setting::setDecimal($packageQuantity),
                    "productQuantity" => Setting::setDecimal($productQuantity),

                    "isStock" => $isStock,
                    "stockDate" => date("d/m/Y",$stockDate),
                    "stockSupplierID" => $stockSupplierID,
                    "stockSupplierData" => $supplierData,
                    "stockSupplierDO" => $stockSupplierDO,
                    "stockQty" => Setting::setDecimal($stockQty),
                    "stockCost" => Setting::setDecimal($stockCost),
                    "stockFee" => Setting::setDecimal($stockFee),
                );
                return array("status" => "ok", "code" => 0, "statusMsg"=> "", "data" => $dataOut);
            }

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> '', 'data'=>"");
        }

        public function addProductInventory($params) {
            include('config.php');

            $db                     = MysqliDb::getInstance();
            $language               = General::$currentLanguage;
            $translations           = General::$translations;
            $imageGroupUniqueChar   = self::generateUniqueChar();
            $dateTime               = date("Y-m-d H:i:s");

			$name           = trim($params['invProductName']);
            $productType    = trim($params['productType']);
            $description    = trim($params['description']);
            $expired_day    = $params['expired_day'];
            $skuCode        = trim($params['skuCode']);
            $cost           = trim($params['cost']);
            $salePrice      = trim($params['salePrice']);
            $vendorId       = trim($params['vendorId']);
            $category       = $params['category'];
            $cookingTime    = trim($params['cookingTime']);
            $cookingSuggest = trim($params['cookingSuggest']);
            $fullInstruc    = $params['fullInstruc'];
            $videoList      = $params['videoList'];
            $uploadImage    = $params['uploadImage'];

            // Variant
            $isVariant      = trim($params["isVariant"]) ? 1 : 0;
            $variant        = $params["variants"];

            //Package
            $packageList    = $params['packageProduct'];
            // $status         = trim($params['status']);
            // $weight         = trim($params['weight']);
            // $statusAry      = array('Active', 'Inactive');

            // $isPackage      = trim($params['isPackage']) ? : 0;
            // $priceSetting   = $params["priceSetting"];
            // $pvPrice        = $params["pvPrice"];
            // $catalogue      = $params["catalogue"];
            // $bBasic         = $params["bBasic"];
            // $activeDate     = $params['activeDate'];
            // $uploadImage    = $params['uploadImage'];
            // $uploadVideo    = $params['uploadVideo'];
            // $packageQuantity= $params['packageQuantity'];
            // $productQuantity= $params['productQuantity'];
            // $packageCategory= $params["packageCategory"];
            // $packageNameLanguages  = $params['packageNameLanguages'];
            // $packageDescrLanguages = $params['packageDescrLanguages'];

            // Stock
            // $isStock = trim($params["isStock"]) ? 1 : 0;
            // $stockDate = trim($params["stockDate"]);
            // $stockSupplierID = trim($params["stockSupplierID"]);
            // $stockSupplierDO = trim($params["stockSupplierDO"]); // Optional field
            // $stockQty = trim($params["stockQty"]);
            // $stockCost = trim($params["stockCost"]);
            // $stockFee = trim($params["stockFee"]);

            $userID = $db->userID;
            $site = $db->userType;

            if(!$userID) {
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["A01078"][$language] /* Access denied. */,'data'=>'');
            } else {
                $db->where("id", $userID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid Admin */,'data'=>'');
                }
            }

            $db->where('id', $vendorId);
            $checkVendor = $db->getValue('vendor','id');

            if(!$checkVendor) {
                return array('status'=>'error','code'=>2,'statusMsg'=> "Invalid Vendor", 'data'=>'');
            }
            $verify = self::verifyProductInventory($params, 'add');
            if($verify["status"] != "ok") return $verify;

            // $db->startTransaction();

            // try{
                // Insert inv_product
                $insertInv = array(
                    "name"               => $name,
                    "product_type"       => $productType,
                    "description"        => $description,
                    "expired_day"        => $expired_day,
                    "barcode"            => $skuCode,
                    "cost"               => $cost,
                    "sale_price"         => $salePrice,
                    "vendor_id"          => $vendorId,
                    "categ_id"           => json_encode($category),
                    "cooking_time"       => $cookingTime,
                    "cooking_suggestion" => $cookingSuggest,
                    "full_instruction"   => $fullInstruc[0],
                    "full_instruction2"  => $fullInstruc[1],
                    "created_at"         => $dateTime
                );
                $productInvId = $db->insert('product', $insertInv);

                if (!$productInvId) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00932"][$language] /* Failed to add product. */,'data'=>'');
                }

                if(!empty($videoList)) {
                    $db->where('deleted', 0);
                    $db->where('reference_id', $productInvId);
                    $proVideo = $db->get('product_media', null, 'url');

                    if(empty($proVideo)) {
                        $proVideo = array();
                    }

                    foreach($videoList as $key => $val) {
                        if($val != '') {
                            if(!in_array($val, $proVideo)) {
                                $insertProMedia[] = array(
                                    "type"         => 'video',
                                    "url"          => $val, 
                                    "reference_id" => $productInvId,
                                    "created_at"   => $dateTime
                                );
                            }
                        }
                    }

                    if($insertProMedia) {
                        $proMedia = $db->insertMulti('product_media', $insertProMedia);
                    }
                }

                if(!empty($uploadImage)) {
                    foreach($uploadImage as $key => $val) {
                        $imgSrc = json_decode($val['imgData'], true);
                        $uploadParams['imgSrc'] = $imgSrc;
                        $uploadRes = aws::awsUploadImage($uploadParams);

                        if($uploadRes['status'] == 'ok') {
                            $imageUrl = $uploadRes['imageUrl'];

                            // insert product_media
                            $insertImage = array(
                                "type" => "Image",
                                "url" => $imageUrl,
                                "reference_id" => $productInvId,
                            );
                            $insertInvImage = $db->insert('product_media', $insertImage);

                            if(!$insertInvImage) {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                            }
                        } else {
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                        }
                    }
                }

                if($isVariant == 1) {
                    foreach($variant[0] as $attr1) {
                        if(!empty($variant[1])) {
                            foreach($variant[1] as $val) {
                                $variantList = array($attr1, $val);
    
                                $insertProVar[] = array(
                                    "product_id"                 => $productInvId,
                                    "product_attribute_value_id" => json_encode($variantList),
                                    "deleted"                    => 0,
                                    "created_at"                 => $dateTime
                                );
                            }
                        } else {
                            $variantList = array($attr1);

                            $insertProVar[] = array(
                                "product_id"                 => $productInvId,
                                "product_attribute_value_id" => json_encode($variantList),
                                "deleted"                    => 0,
                                "created_at"                 => $dateTime
                            );
                        }
                    }
                    $proVar = $db->insertMulti('product_template', $insertProVar);
                } else {
                    $db->where('deleted', 0);
                    $db->where('product_id', $productInvId);
                    $db->where('product_attribute_value_id', '');
                    $checkEmptyTemplate = $db->getOne('product_template', 'id');

                    if(!$checkEmptyTemplate) {
                        $insertProVar = array(
                            "product_id"                 => $productInvId,
                            "product_attribute_value_id" => '',
                            "deleted"                    => 0,
                            "created_at"                 => $dateTime
                        );
                        $proVar = $db->insert('product_template', $insertProVar);
                    }
                }

                if(!empty($packageList)) {
                    foreach($packageList as $val) {
                        $insertPackageItemData[] = array(
                            'package_id' => $productInvId,
                            'product_id' => $val,
                            'deleted'    => 0,
                            'created_at' => $dateTime
                        );
                    }

                    if($insertPackageItemData) {
                        $insertPackageItem = $db->insertMulti('package_item', $insertPackageItemData);
                    }
                }
                // Insert inv_product_detail
                // $insertInvDetails = array(
                //     "inv_product_id" => $productInvId,
                //     "name"  => "productCategory",
                //     "value" => $category,
                //     "type"  => "productCategory"
                // );
                // $insertInvDetailsRes = $db->insert('inv_product_detail', $insertInvDetails);

                // if($isPackage == 1){
                //     $params['createPackage'] = $isPackage;
                //     $params['productInvID']  = $productInvId;
                //     $params["name"]          = $name;
                //     $params['category']      = $packageCategory;
                //     $params['code']          = $skuCode;
                //     $params['nameLanguages'] = $packageNameLanguages;
                //     $params['descrLanguages']= $packageDescrLanguages;

                //     $package = self::addPackageDetail($params);
                //     if($package["status"] != "ok") {
                //         $db->rollback();
                //         $db->commit();
                //         return $package;
                //     }
                //     $dataOut["packageData"] = $package["data"];
                // }

                // Stock
            //     if($isStock == 1){
            //         unset($stockParams);
            //         $stockParams = array(
            //             "stockInDate" => $stockDate,
            //             "invProductID" => $productInvId,
            //             "supplierID" => $stockSupplierID,
            //             "supplierDO" => $stockSupplierDO,
            //             "quantity" => $stockQty,
            //             "cost" => $stockCost,
            //             "feeNCharges" => $stockFee,
            //             "type" => "in",
            //         );
            //         $verifyStock = Self::adjustInvProduct($stockParams);
            //         if($verifyStock["status"] != "ok"){
            //             $db->rollback();
            //             $db->commit();
            //             return $verifyStock;
            //         }
            //     }
            // }catch(Exception $e){
            //     $db->rollback();
            //     $db->commit();
            //     return array("status" => "error", "code" => 2, "statusMsg" => "System Error", "data" => "");
            // }

            // $db->commit();

            // $activityData = array_merge(array('admin' => $checkAdmin), $insertInv, $insertInvDetails, $insertProductNameTransList, $insertProductDescrTranList);

            // Insert Activity Log
            $activityRes = Activity::insertActivity('Add Product', 'T00032', 'L00052', $activityData, $userID, $userID, $site);

            if(!$activityRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> '');
        }

        public function editProductInventory($params) {
            $db              = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTime        = date("Y-m-d H:i:s");

            $productInvId    = trim($params['productInvId']);
            $name           = trim($params['invProductName']);
            $status         = trim($params['status']);
            $category       = $params['category'];
            $weight         = trim($params['weight']);
            $statusAry      = array('Active', 'Inactive');

            $productType    = trim($params['productType']);
            $description    = trim($params['description']);
            $expired_day    = trim($params['expired_day']);
            $skuCode        = trim($params['code']);
            $cost           = trim($params['cost']);
            $salePrice      = trim($params['salePrice']);
            $vendorId       = trim($params['vendorId']);
            $cookingTime    = trim($params['cookingTime']);
            $cookingSuggest = trim($params['cookingSuggest']);
            $fullInstruc    = $params['fullInstruc'];
            $videoList      = $params['videoList'];
            $videoId        = $params['videoId'];
            $uploadImage    = $params['uploadImage'];
            $imageId        = $params['imageId'];

            // Variant
            $isVariant      = trim($params["isVariant"]) ? 1 : 0;
            $variant        = $params["variants"];

            //Package
            $packageProduct = $params['packageProduct'];

            $imageGroupUniqueChar = self::generateUniqueChar();

            $userID = $db->userID;
            $site = $db->userType;

            if(!$userID) {
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["A01078"][$language] /* Access denied. */,'data'=>'');
            } else {
                $db->where("id", $userID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid admin */,'data'=>'');
                }
            }

            $db->where('id', $vendorId);
            $checkVendor = $db->getValue('vendor','id');

            if(!$checkVendor) {
                return array('status'=>'error','code'=>2,'statusMsg'=> "Invalid Vendor", 'data'=>'');
            }

            $db->where('id', $category);
            $checkCategory = $db->getValue('product_category','id');

            if(!$checkCategory) {
                return array('status'=>'error','code'=>2,'statusMsg'=> "Invalid Category", 'data'=>'');
            }

            $verify = self::verifyProductInventory($params, "edit");
            if($verify["status"] != "ok") return $verify;

            if(!$productInvId) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00933"][$language] /* Product not found */, 'data' => '');
            }

            $db->where("id", $productInvId);
            $productInvRecord = $db->getOne("product", "id");

            if($status == "Inactive"){
                $delete = '1';
            } else {
                $delete = '0';
            }

            // Edit product
            $editInv = array(
                "name"               => $name,
                "product_type"       => $productType,
                "description"        => $description,
                "expired_day"        => $expired_day,
                "barcode"            => $skuCode,
                "cost"               => $cost,
                "sale_price"         => $salePrice,
                "vendor_id"          => $vendorId,
                "categ_id"           => json_encode($category),
                "cooking_time"       => $cookingTime,
                "cooking_suggestion" => $cookingSuggest,
                "full_instruction"   => $fullInstruc[0],
                "full_instruction2"  => $fullInstruc[1],
                "deleted"            => $delete,
                "updated_at"         => $dateTime,
            );

            $db->where("id", $productInvId);
            $editInvRes = $db->update("product", $editInv);

            if (!$editInvRes) {
                return array('status' => 'error', 'code' => 2,'statusMsg' => $translations["E00934"][$language] /* Failed to update product. */, 'data' => '');
            }

            if(!empty($videoList)) {
                $db->where('deleted', 0);
                $db->where('reference_id', $productInvId);
                $proVideo = $db->get('product_media', null, 'url');

                if(empty($proVideo)) {
                    $proVideo = array();
                }

                foreach($videoList as $key => $val) {
                    if($videoId[$key] != '') {
                        if($val != '') {
                            $updateVideo = array(
                                "url"          => $val, 
                                "updated_at"   => $dateTime
                            );
                        } else {
                            $updateVideo = array(
                                "url"          => $val,
                                "reference_id" => 0,
                                "updated_at"   => $dateTime
                            );
                        }
                        $db->where("id", $videoId[$key]);
                        $editVideo = $db->update("product_media", $updateVideo);
                    } else {
                        if(!in_array($val, $proVideo)) {
                            if($val != '') {
                                $insertProMedia[] = array(
                                    "type"         => 'video',
                                    "url"          => $val, 
                                    "reference_id" => $productInvId,
                                    "created_at"   => $dateTime
                                );
                            }
                        }
                    }
                }

                if($insertProMedia) {
                    $proMedia = $db->insertMulti('product_media', $insertProMedia);
                }
            }

            if(!empty($uploadImage)) {
                foreach($uploadImage as $key => $val) {
                    if($val['imgID'] == '') {
                        $imgSrc = json_decode($val['imgData'], true);
                        $uploadParams['imgSrc'] = $imgSrc;
                        $uploadRes = aws::awsUploadImage($uploadParams);

                        if($uploadRes['status'] == 'ok') {
                            $imageUrl = $uploadRes['imageUrl'];
                            // insert product_media
                            $insertImage[] = array(
                                "type" => "Image",
                                "url" => $imageUrl,
                                "reference_id" => $productInvId,
                            );
                        } else {
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                        }
                    } else {
                        $imgSrc = json_decode($val['imgData'], true);

                        if (strpos($imgSrc, "data:") !== false) {
                            $db->where('id', $val['imgID']);
                            $db->where('type', 'Image');
                            $editImage = $db->update('product_media', array('deleted' => 1));

                            $uploadParams['imgSrc'] = $imgSrc;
                            $uploadRes = aws::awsUploadImage($uploadParams);

                            if($uploadRes['status'] == 'ok') {
                                $imageUrl = $uploadRes['imageUrl'];
                                // insert product_media
                                $insertImage[] = array(
                                    "type" => "Image",
                                    "url" => $imageUrl,
                                    "reference_id" => $productInvId,
                                );
                            } else {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                            }
                        }
                    }
                }

                if($imageId) {
                    $db->where('id', $imageId, 'IN');
                    $db->where('type', 'Image');
                    $db->where('reference_id', $productInvId);
                    $editImageList = $db->update('product_media', array('deleted' => 1));
                }

                if(!empty($insertImage)) {
                    $insertInvImage = $db->insertMulti('product_media', $insertImage);

                    if(!$insertInvImage) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                    }
                }

            }

            if($isVariant == 1) {
                if(!empty($variant)) {
                    foreach($variant[0] as $attr1) {
                        if(!empty($variant[1])) {
                            foreach($variant[1] as $val) {
                                $variantList = array($attr1, $val);

                                $db->where('product_id', $productInvId);
                                $db->where('product_attribute_value_id', '%'.json_encode($variantList).'%', 'LIKE');
                                $getVariantId = $db->getOne('product_template', 'id, deleted');

                                if(empty($getVariantId)) {
                                    $insertProVar[] = array(
                                        "product_id"                 => $productInvId,
                                        "product_attribute_value_id" => json_encode($variantList),
                                        "deleted"                    => 0,
                                        "created_at"                 => $dateTime
                                    );
                                } else {
                                    if($getVariantId['deleted'] == 0) {
                                        $varList[] = $getVariantId['id'];
                                    } else {
                                        $varList2[] = $getVariantId['id'];
                                    }
                                }
                            }
                        } else {
                            $variantList = array($attr1);

                            $db->where('product_id', $productInvId);
                            $db->where('product_attribute_value_id', '%'.json_encode($variantList).'%', 'LIKE');
                            $getVariantId = $db->getOne('product_template', 'id, deleted');

                            if(empty($getVariantId)) {
                                $insertProVar[] = array(
                                    "product_id"                 => $productInvId,
                                    "product_attribute_value_id" => json_encode($variantList),
                                    "deleted"                    => 0,
                                    "created_at"                 => $dateTime
                                );
                            } else {
                                if($getVariantId['deleted'] == 1) {
                                    $varList[] = $getVariantId['id'];
                                } else {
                                    $varList2[] = $getVariantId['id'];
                                }
                            }
                        }
                    }

                    $updateProVarData = array(
                        "deleted"    => 1,
                        "updated_at" => $dateTime
                    );
                    $db->where('product_attribute_value_id', '');
                    $db->where('product_id', $productInvId);
                    $updateProVar = $db->update('product_template', $updateProVarData);
                } else {
                    $updateProVarData = array(
                        "deleted"    => 0,
                        "updated_at" => $dateTime
                    );
                    $db->where('product_attribute_value_id', '');
                    $db->where('product_id', $productInvId);
                    $updateProVar = $db->update('product_template', $updateProVarData);

                    $updateEmptyVarData = array(
                        "deleted"    => 1,
                        "updated_at" => $dateTime
                    );
                    $db->where('product_attribute_value_id', '', '!=');
                    $db->where('product_id', $productInvId);
                    $updateEmptyVar = $db->update('product_template', $updateEmptyVarData);
                }

                if(!empty($varList)) {
                    $updateProVarData = array(
                        "deleted"    => 1,
                        "updated_at" => $dateTime
                    );
                    $db->where('id', $varList, 'NOT IN');
                    $db->where('product_attribute_value_id', '', '!=');
                    $db->where('product_id', $productInvId);
                    $updateProVar = $db->update('product_template', $updateProVarData);
                }

                if(!empty($varList2)) {
                    $updateProVarData = array(
                        "deleted"    => 0,
                        "updated_at" => $dateTime
                    );
                    $db->where('id', $varList2, 'IN');
                    $db->where('product_attribute_value_id', '', '!=');
                    $db->where('product_id', $productInvId);
                    $updateProVar = $db->update('product_template', $updateProVarData);
                }

                if($insertProVar) {
                    $proVar = $db->insertMulti('product_template', $insertProVar);
                }
            }

            if($productType == 'Package') {
                if(!empty($packageProduct)) {
                    $updatePackageData = array(
                        "deleted"    => 1,
                        "updated_at" => $dateTime
                    );
                    $db->where('product_id', $packageProduct, 'NOT IN');
                    $db->where('deleted', 0);
                    $db->where('package_id', $productInvId);
                    $updatePackage = $db->update('package_item', $updatePackageData);

                    foreach($packageProduct as $val) {
                        $db->where('product_id', $val);
                        $checkPackage = $db->getOne('package_item', 'id');

                        if(!$checkPackage) {
                            $insertPackageItemData[] = array(
                                'package_id' => $productInvId,
                                'product_id' => $val,
                                'deleted'    => 0,
                                'created_at' => $dateTime
                            );
                        } else {
                            $updatePackageData = array(
                                "deleted"    => 0,
                                "updated_at" => $dateTime
                            );
                            $db->where('product_id', $val);
                            $db->where('deleted', 1);
                            $db->where('package_id', $productInvId);
                            $updatePackage = $db->update('package_item', $updatePackageData);
                        }
                    }

                    if($insertPackageItemData) {
                        $insertPackageItem = $db->insertMulti('package_item', $insertPackageItemData);
                    }
                }
            }

            $activityData = array_merge(array('admin' => $checkAdmin), $editInv, $updateTranslationList, $updateDescrTranslationList);

            // Insert Activity Log
            $activityRes = Activity::insertActivity('Edit Product', 'T00033', 'L00053', $activityData, $userID, $userID, $site);

            if(!$activityRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00370"][$language] /* Successfully Updated */ , 'data'=>$updatePackage);
        }

        // -------- Stock Adjustment --------//
        public function packageAdjustment($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $packageID      = trim($params["packageID"]);
            $quantity       = trim($params["quantity"]);
            $type           = trim($params["type"]);
            $typeArr        = array("in","out");
            $subject        = $type == "in" ? "Adjust In" : "Adjust Out";
            $todayDate      = date("Y-m-d H:i:s");

            $userID = $db->userID;
            $site = $db->userType;

            if(!$packageID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00841"][$language] /* Please Select Product */, 'data' => "");
            }else{
                $db->where("id",$packageID);
                $validPackage = $db->getOne("mlm_product", "id, SUM(total_balance - total_sold - total_holding) AS quantity");
                if(!$validPackage) return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data' => "");
            }

            if(!$type || !in_array($type, $typeArr)){
                $errorFieldArr[] = array(
                        'id'  => "typeError",
                        'msg' => $translations["E00741"][$language]
                    );
            }

            if(!$quantity || $quantity <= 0 || !is_numeric($quantity)){
                $errorFieldArr[] = array(
                        'id'  => "quantityError",
                        'msg' => $translations['E00941'][$language] /* Quantity must be greater than 0 */
                    );
            }elseif($type == "out"){
                $currentBalance = $validPackage['quantity'];

                if($quantity > $currentBalance){
                    $errorFieldArr[] = array(
                            'id'  => "quantityError",
                            'msg' => $translations['E01080'][$language] /* Current package quantity less than quantity wanna be deduct.  */
                        );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // Update mlm product total balance
            if($type == 'in') {
                $data = array(
                    "admin"     =>  $userID,
                    "action"    =>  "add",
                    "quantity"  =>  $quantity,
                    "package"   =>  $packageID,
                );

                $updateData = array(
                    'total_balance' => $db->inc($quantity)
                );
            } else if($type == 'out') {
                $data = array(
                    "admin"     =>  $userID,
                    "action"    =>  "deduct",
                    "quantity"  =>  $quantity,
                    "package"   =>  $packageID,
                );

                $updateData = array(
                    'total_balance' => $db->dec($quantity)
                );
            }
            $db->where('id', $packageID);
            $db->update('mlm_product', $updateData);

            // insert inv log
            $invLog = array(
                    "module"                    =>  "mlm_product",
                    "module_id"                 =>  $packageID,
                    "title_transaction_code"    =>  "T00059",
                    "title"                     =>  $subject,
                    "transaction_code"          =>  "L00079",
                    "data"                      =>  json_encode($data),
                    "creator_type"              =>  $site,
                    "creator_id"                =>  $userID,
                    "created_at"                =>  $todayDate
            );
            $db->insert("inv_log", $invLog);

            // Update system setting for process get product
            $db->where('name', 'processGetProduct');
            $db->update('system_settings', array('value' => 0));

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00370"][$language], 'data' => "");
        }

        public function getPackageAdjustment($params){
            $db           = MysqliDb::getInstance();
            $translations = General::$translations;
            $language     = General::$currentLanguage;
            $searchData   = $params['searchData'];
            $seeAll       = $params['seeAll'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit        = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $packageID    = $params["packageID"];

            $userID       = $db->userID;
            $site         = $db->userType;

            if(!$packageID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01009"][$language] /* Invalid Package ID */, 'data' => "");
            }

            $db->where("id", $packageID);
            $packageData = $db->getOne("mlm_product", "id, SUM(total_balance - total_sold - total_holding) AS quantity");

            $db->where('module_id', $packageData['id']);
            $db->where('module', 'mlm_product');
            $db->where('language', $language);
            $db->where('type', 'name');
            $packageName = $db->getValue('inv_language', 'content');

            $data['packageName'] = $packageName;
            $data["quantity"] = $packageData['quantity'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function adjustInvProduct($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $dateTimeFormat = "Y-m-d H:i:s";
            $todayDate      = date($dateTimeFormat);

            $fromAddProduct = trim($params["fromAddProduct"]) ? 1 : 0;
            $invProductID   = trim($params["invProductID"]);
            $quantity       = trim($params["quantity"]);
            $cost           = trim($params["cost"]);
            $remark         = trim($params["remark"]);
            $stockInDate    = date($dateTimeFormat, $params["stockInDate"]) ? : $todayDate;
            $subject        = "Stock In";
            $doNum          = $params['doNum'];
            $supplierID     = $params['supplierID'];
            $feeNCharges    = $params['feeNCharges'];

            $userID = $db->userID;
            $site = $db->userType;

            if($site == "Member"){
                $clientID = $userID;
            }

            if(!$fromAddProduct){
                if(!$invProductID){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00841"][$language] /* Please Select Product */, 'data' => "");
                }else{
                    $db->where("id",$invProductID);
                    $validProduct = $db->has("inv_product");
                    if(!$validProduct) return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data' => "");
                }
            }

            if($doNum){
                $db->where("do_number",$doNum);
                $checkDoNum = $db->has("inv_stock");
                if($checkDoNum){
                    $errorFieldArr[] = array(
                        "id"  => "doNumError",
                        "msg" => $translations['E01085'][$language] /* DO Number duplicated */
                    );
                }
            }

            if(!$supplierID){
                $errorFieldArr[] = array(
                        'id'  => "supplierError",
                        'msg' => $translations['E00987'][$language] /* Invalid Supplier */
                    );
            }else{
                $db->where("id", $supplierID);
                $validSupplier = $db->has("inv_supplier");

                if(!$validSupplier){
                    $errorFieldArr[] = array(
                            'id'  => "supplierError",
                            'msg' => $translations['E00987'][$language] /* Invalid Supplier */
                        );
                }
            }

            if($feeNCharges){
                if(!is_numeric($feeNCharges) || $feeNCharges < 0){
                    $errorFieldArr[] = array(
                        'id'  => "quantityError",
                        'msg' => $translations['E00941'][$language] /* Quantity must be greater than 0 */
                    );
                }
            }

            if(!$quantity || $quantity <= 0 || !is_numeric($quantity)){
                $errorFieldArr[] = array(
                        'id'  => "quantityError",
                        'msg' => $translations['E00941'][$language] /* Quantity must be greater than 0 */
                    );
            }

            if(!$cost || $cost <= 0 || !is_numeric($cost)){
                $errorFieldArr[] = array(
                        'id'  => "costError",
                        'msg' => $translations['E01056'][$language] /* Cost must be greater than 0 */
                    );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            if($fromAddProduct){
                return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => "");
            }

            // Update inv product total balance
            $updateData = array(
                'total_balance' => $db->inc($quantity)
            );
            $db->where('id', $invProductID);
            $copyDB = $db->copy();
            $db->update('inv_product', $updateData);

            $batchID  = $db->getNewID();
            $amountIn = $quantity;

            $stockCode = General::generateDynamicCode("K", 7, "inv_stock", "stock_code", true);
            // insert inv stock
            $insertStock = array(
                    "inv_product_id"    => $invProductID,
                    "stock_code"        => $stockCode,
                    "do_number"         => $doNum,
                    "supplier_id"       => $supplierID,
                    "fee_charges"       => $feeNCharges,
                    "cost"              => $cost,
                    "stock_in"          => $quantity,
                    "stock_in_date"     => $todayDate,
                    "created_at"        => $todayDate,
                    "remark"            => $remark,
            );
            $invStockID = $db->insert("inv_stock", $insertStock);

            // insert inv product transactions
            $insertProductTransaction = array(
                    "inv_product_id"    => $invProductID,
                    "client_id"         => $clientID,
                    "subject"           => $subject,
                    "amount_in"         => $amountIn,
                    "amount_out"        => $amountOut,
                    "data"              => $data,
                    "batch_id"          => $batchID,
                    "created_at"        => $todayDate,
                    "creator_id"        => $userID,
                    "creator_type"      => $site,
                    "remark"            => $remark,
            );
            $productTransaction = $db->insert("inv_product_transaction",$insertProductTransaction);

            // insert inv stock transactions
            $insertStockTransaction = array(
                    "inv_product_id"    => $invProductID,
                    "inv_stock_id"      => $invStockID,
                    "client_id"         => $clientID,
                    "subject"           => $subject,
                    "amount_in"         => $amountIn,
                    "amount_out"        => $amountOut,
                    "data"              => $data,
                    "batch_id"          => $batchID,
                    "created_at"        => $todayDate,
                    "creator_id"        => $userID,
                    "creator_type"      => $site,
                    "remark"            => $remark,
            );
            $stockTransaction = $db->insert("inv_stock_transaction",$insertStockTransaction);

            $lowStockAlert      = General::getSystemSettingAdmin('lowStockQuantity');

            if($lowStockAlert){
                $newestStockBalance = $copyDB->getOne('inv_product', 'id, total_balance, alert_at');
                $lowStockAlertValue = $lowStockAlert['lowStockQuantity']['value'];

                if($newestStockBalance['total_balance'] >= $lowStockAlertValue && $newestStockBalance['alert_at'] > 0){
                    $updateTime = array(
                        'alert_at'  =>  '0000-00-00 00:00:00',
                    );
                    $db->where('id', $newestStockBalance['id']);
                    $db->update('inv_product', $updateTime);
                }
            }

            if(!$invStockID || !$productTransaction || !$stockTransaction){
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E01058"][$language] /* Failed to update stock. */,'data'=>'');
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00370"][$language], 'data' => "");
        }

        public function adjustInvStock($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $dateTimeFormat = "Y-m-d H:i:s";
            $todayDate      = date($dateTimeFormat);

            $stockID        = $params['stockID'];
            $quantity       = trim($params["quantity"]);
            $remark         = trim($params["remark"]);
            $type           = trim($params["type"]);
            $stockInDate    = date($dateTimeFormat, $params["stockInDate"]) ? : $todayDate;
            $typeArr        = array("in","out");
            $subject        = $type == "in" ? "Stock In" : "Stock Out";

            $userID = $db->userID;
            $site = $db->userType;

            if(!$stockID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00841"][$language] /* Please Select Product */, 'data' => "");
            }else{
                $db->where('id',$stockID);
                $stock = $db->getOne('inv_stock','inv_product_id, stock_code');

                if(empty($stock)){
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
                }

                $invProductID = $stock['inv_product_id'];
                $stockCode = $stock['stock_code'];
            }

            if($site == "Member"){
                $clientID = $userID;
            }

            if(!$type || !in_array($type, $typeArr)){
                $errorFieldArr[] = array(
                        'id'  => "typeError",
                        'msg' => $translations["E00741"][$language]
                    );
            }

            if(!$quantity || $quantity <= 0 || !is_numeric($quantity)){
                $errorFieldArr[] = array(
                        'id'  => "quantityError",
                        'msg' => $translations['E00941'][$language] /* Quantity must be greater than 0 */
                    );
            }elseif($type == "out") {
                $db->where("stock_code", $stockCode);
                $currentBalance = $db->getValue("inv_stock", "SUM(stock_in - stock_out)");
                $stockBalance = ($currentBalance - $quantity);

                if($stockBalance < 0){
                    $errorFieldArr[] = array(
                            'id'  => "quantityError",
                            'msg' => $translations['E00955'][$language] /* Invalid product */
                        );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // Update inv product total balance
            if($type == 'in') {
                $updateData = array(
                    'total_balance' => $db->inc($quantity)
                );
            } else if($type == 'out') {
                $updateData = array(
                    'total_balance' => $db->dec($quantity)
                );
            }
            $db->where('id', $invProductID);
            $copyDb = $db->copy();
            $db->update('inv_product', $updateData);

            if($type == 'in') {
                $amountIn = $quantity;

                $updateStock = array(
                        "stock_in"  => $db->inc($quantity),
                );
            } else if($type == 'out') {
                $amountOut = $quantity;

                $updateStock = array(
                        'stock_out' => $db->inc($quantity),
                );
            }
            $db->where('id',$stockID);
            $db->where('inv_product_id', $invProductID);
            $copyDB = $db->copy();
            $db->update('inv_stock', $updateStock);

            $invStockID = $copyDB->getValue("inv_stock", "id");

            $batchID    = $db->getNewID();

            // insert inv product transactions
            $insertProductTransaction = array(
                    "inv_product_id"    => $invProductID,
                    "client_id"         => $clientID,
                    "subject"           => $subject,
                    "amount_in"         => $amountIn,
                    "amount_out"        => $amountOut,
                    "data"              => $data,
                    "batch_id"          => $batchID,
                    "created_at"        => $todayDate,
                    "creator_id"        => $userID,
                    "creator_type"      => $site,
                    "remark"            => $remark,
            );
            $productTransaction = $db->insert("inv_product_transaction",$insertProductTransaction);

            // insert inv stock transactions
            $insertStockTransaction = array(
                    "inv_product_id"    => $invProductID,
                    "inv_stock_id"      => $invStockID,
                    "client_id"         => $clientID,
                    "subject"           => $subject,
                    "amount_in"         => $amountIn,
                    "amount_out"        => $amountOut,
                    "data"              => $data,
                    "batch_id"          => $batchID,
                    "created_at"        => $todayDate,
                    "creator_id"        => $userID,
                    "creator_type"      => $site,
                    "remark"            => $remark,
            );
            $stockTransaction = $db->insert("inv_stock_transaction",$insertStockTransaction);

            $lowStockAlert      = General::getSystemSettingAdmin('lowStockQuantity');

            if($lowStockAlert){
                $newestStockBalance = $copyDB->getOne('inv_product', 'id, total_balance, alert_at');
                $lowStockAlertValue = $lowStockAlert['lowStockQuantity']['value'];

                if($newestStockBalance['total_balance'] >= $lowStockAlertValue && $newestStockBalance['alert_at'] > 0){
                    $updateTime = array(
                        'alert_at'  =>  '0000-00-00 00:00:00',
                    );
                    $db->where('id', $newestStockBalance['id']);
                    $db->update('inv_product', $updateTime);
                }
            }

            if(!$invStockID || !$productTransaction || !$stockTransaction){
                return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E01058"][$language] /* Failed to update stock. */,'data'=>'');
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00370"][$language], 'data' => "");
        }

        public function getStockDetails($params){
            $db           = MysqliDb::getInstance();
            $translations = General::$translations;
            $language     = General::$currentLanguage;
            $searchData   = $params['searchData'];
            $seeAll       = $params['seeAll'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit        = General::getLimit($pageNumber);
            $systemDateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $invProductID = $params["invProductID"];

            $userID       = $db->userID;
            $site         = $db->userType;

            if(!$invProductID){
                return array('status' => "ok", 'code' => 2, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
            }

            if($seeAll){
                $limit = NULL;
            }

            $db->where("inv_product_id",$invProductID);
            $copyDB = $db->copy();
            $db->orderBy("created_at", "DESC");
            $invStockRes = $db->get('inv_stock', $limit, "id, inv_product_id, supplier_id, stock_code, do_number, cost, stock_in, stock_out, stock_in_date, created_at, remark");

            if(!$invStockRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
            }

            foreach($invStockRes as $invStockRow) {
                $supplierIDAry[$invStockRow["supplier_id"]] = $invStockRow["supplier_id"];
                $productID = $invStockRow["inv_product_id"];
            }

            if($supplierIDAry){
                $db->where("id", $supplierIDAry, "IN");
                $supplierNameAry = $db->map("id")->get("inv_supplier", null, "id, name, code");
            }

            foreach ($invStockRes as $invStockRow) {
                $value['id']            = $invStockRow['id'];
                $value['stockCode']       = $invStockRow['stock_code'];
                $value['doNumber']      = $invStockRow['do_number'] ? : '-';
                $value['cost']          = Setting::setDecimal($invStockRow['cost']);
                $value['quantity']      = Setting::setDecimal($invStockRow['stock_in'] - $invStockRow['stock_out']);
                $value['totalCost']     = Setting::setDecimal($invStockRow['cost'] * $value['quantity']);
                $value['stockInDate']   = $invStockRow['stock_in_date'] > 0 ? date($systemDateTimeFormat, strtotime($invStockRow['stock_in_date'])) : "-";
                $value['createdAt']     = date($systemDateTimeFormat, strtotime($invStockRow['created_at']));
                $value['remark']        = $invStockRow['remark'] ? : '-';
                $value['supplierName']  = $supplierNameAry[$invStockRow['supplier_id']]['name'];
                $value['supplierCode']  = $supplierNameAry[$invStockRow['supplier_id']]['code'];

                $productList[] = $value;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $totalRecord = $copyDB->getValue('inv_stock', 'COUNT(*)');
            $data['list']          = $productList;
            $data['pageNumber']    = $pageNumber;
            $data['totalRecord']   = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getStockTransactionHistory($params){
            $db           = MysqliDb::getInstance();
            $translations = General::$translations;
            $language     = General::$currentLanguage;
            $searchData   = $params['searchData'];
            $seeAll       = $params['seeAll'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit        = General::getLimit($pageNumber);
            $systemDateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $invStockID = $params["invStockID"];

            $userID     = $db->userID;
            $site       = $db->userType;

            if(!$invStockID){
                return array('status' => "ok", 'code' => 2, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
            }

            if($seeAll){
                $limit = NULL;
            }

            $db->where("inv_stock_id",$invStockID);
            $copyDB = $db->copy();
            $db->orderBy("created_at", "DESC");
            $stockTransactionRes = $db->get('inv_stock_transaction', $limit, "inv_product_id, inv_stock_id, client_id, subject, amount_in, amount_out, created_at, creator_id, creator_type, remark, batch_id");

            if(!$stockTransactionRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
            }

            foreach($stockTransactionRes as $stockTransactionRow) {
                $clientIDAry[$stockTransactionRow["client_id"]] = $stockTransactionRow["client_id"];
                $creatorIDAry[$stockTransactionRow["creator_id"]] = $stockTransactionRow["creator_id"];
                $stockIDAry[$stockTransactionRow["inv_stock_id"]] = $stockTransactionRow["inv_stock_id"];
                $batchIDAry[$stockTransactionRow["batch_id"]] = $stockTransactionRow['batch_id'];
            }

            if($clientIDAry) {
                $db->where("type", "Client");
                $db->where("id", $clientIDAry, "IN");
                $clientUsernameAry = $db->map("id")->get("client", null, "id, username");
            }

            if($creatorIDAry) {
                $db->where("id", $creatorIDAry, "IN");
                $creatorUsernameAry = $db->map("id")->get("admin", null, "id, username");
            }

            if($stockIDAry) {
                $db->where("id", $stockIDAry, "IN");
                $stockCodeAry = $db->map("id")->get("inv_stock", null, "id, stock_code, stock_in, stock_out");
            }

            if($batchIDAry) {
                $db->where("batch_id", $batchIDAry, "IN");
                $deliveryNoAry = $db->map("batch_id")->get("inv_delivery_order", null, "batch_id, reference_number");
            }

            foreach ($stockTransactionRes as $stockTransactionRow) {
                $value['stockCode']       = $stockCodeAry[$stockTransactionRow['inv_stock_id']]['stock_code'];
                $value['client']        = $stockTransactionRow['client_id'] > 0 ? $clientUsernameAry[$stockTransactionRow['client_id']] : '-';
                $value['subject']       = General::getTranslationByName($stockTransactionRow["subject"]);
                $value['amountIn']      = Setting::setDecimal($stockTransactionRow["amount_in"]);
                $value['amountOut']     = Setting::setDecimal($stockTransactionRow["amount_out"]);
                $value['createdBy']     = $stockTransactionRow['creator_id'] > 0 ? $creatorUsernameAry[$stockTransactionRow['creator_id']] : '-';
                $value['createrType']   = $stockTransactionRow['creator_type'] == "Admin" ? "Admin" : "System";
                $value['createdAt']     = date($systemDateTimeFormat, strtotime($stockTransactionRow['created_at']));

                $doNumber = $deliveryNoAry[$stockTransactionRow['batch_id']];
                $msg = str_replace(array("%%doNo%%", "%%name%%"), array($doNumber, $value['createdBy']), $translations['B00507'][$language]);
                if($stockTransactionRow['subject'] == 'Issue DO'){
                    $value['remark']    = $msg ? : '-';
                }else{
                    $value['remark']    = $stockTransactionRow['remark'] ? : '-';
                }

                $stockList[] = $value;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $totalRecord = $copyDB->getValue('inv_stock_transaction', 'COUNT(*)');
            $data['list']          = $stockList;
            $data['pageNumber']    = $pageNumber;
            $data['totalRecord']   = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getProductTransactionHistory($params){
            $db           = MysqliDb::getInstance();
            $translations = General::$translations;
            $language     = General::$currentLanguage;
            $searchData   = $params['searchData'];
            $seeAll       = $params['seeAll'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit        = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $invProductID = $params["invProductID"];

            $userID       = $db->userID;
            $site         = $db->userType;

            if(!$invProductID){
                return array('status' => "ok", 'code' => 2, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
            }

            if($seeAll){
                $limit = NULL;
            }

            $db->where("inv_product_id",$invProductID);
            $copyDB = $db->copy();
            $db->orderBy("created_at", "DESC");
            $productTransactionRes = $db->get('inv_product_transaction', $limit, "inv_product_id, client_id, subject, amount_in, amount_out, created_at, creator_id, creator_type, remark, batch_id");

            if(!$productTransactionRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => "");
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            foreach($productTransactionRes as $productTransactionRow) {
                $clientIDAry[$productTransactionRow["client_id"]] = $productTransactionRow["client_id"];
                $creatorIDAry[$productTransactionRow["creator_id"]] = $productTransactionRow["creator_id"];
                $batchIDAry[$productTransactionRow["batch_id"]] = $productTransactionRow['batch_id'];
                $productID = $productTransactionRow["inv_product_id"];
            }

            if($clientIDAry) {
                $db->where("type", "Client");
                $db->where("id", $clientIDAry, "IN");
                $clientUsernameAry = $db->map("id")->get("client", null, "id, username");
            }

            if($creatorIDAry) {
                $db->where("id", $creatorIDAry, "IN");
                $creatorUsernameAry = $db->map("id")->get("admin", null, "id, username");
            }

            if($batchIDAry) {
                $db->where("batch_id", $batchIDAry, "IN");
                $orderNoAry = $db->map("batch_id")->get("inv_order", null, "batch_id, reference_number");
            }

            foreach ($productTransactionRes as $productTransactionRow) {
                $value['client']        = $productTransactionRow['client_id'] > 0 ? $clientUsernameAry[$productTransactionRow['client_id']] : '-';
                $value['subject']       = General::getTranslationByName($productTransactionRow["subject"]);
                $value['amountIn']      = Setting::setDecimal($productTransactionRow["amount_in"]);
                $value['amountOut']     = Setting::setDecimal($productTransactionRow["amount_out"]);
                $value['createdBy']     = $productTransactionRow['creator_id'] > 0 ? $clientUsernameAry[$productTransactionRow['creator_id']] : '-';
                $value['createrType']   = $productTransactionRow['creator_type'] == "Admin" ? "Admin" : "System";
                $value['createdAt']     = date($dateTimeFormat, strtotime($productTransactionRow['created_at']));

                $poNumber = $orderNoAry[$productTransactionRow['batch_id']];
                $msg = str_replace(array("%%poNo%%", "%%name%%"), array($poNumber, $value['createdBy']), $translations['B00508'][$language]);
                if($productTransactionRow['subject'] == 'Buy Product'){
                    $value['remark']    = $msg ? : '-';
                }else{
                    $value['remark']    = $productTransactionRow['remark'] ? : '-';
                }

                $productList[] = $value;
            }

            $totalRecord = $copyDB->getValue('inv_product_transaction', 'COUNT(*)');
            $data['list']          = $productList;
            $data['pageNumber']    = $pageNumber;
            $data['totalRecord']   = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function insertInvProductTransaction($invProduct, $clientID, $subject, $packageQuantity, $data, $batchID, $remark){
            $db = MysqliDb::getInstance();

            $userID     = $db->userID;
            $site       = $db->userType;
            $todayDate  = date("Y-m-d H:i:s");

            $decProductSubject = array("Buy Product");
            $incProductSubject = array("Cancel Product");
            $acceptedSubject = array_merge($decProductSubject, $incProductSubject);

            if($site == "Member"){
                $creatorType = "System";
            }

            if(!$invProduct || !in_array($subject, $acceptedSubject)){
                return false;
            }

            foreach ($invProduct as $invProductID => $invProductValue) {
                // Update inv product
                $quantity = $invProductValue * $packageQuantity;

                if(in_array($subject, $incProductSubject)) {
                    $amountIn = $quantity;

                    $updateData = array(
                        'total_balance' => $db->inc($quantity),
                        'total_pending' => $db->dec($quantity)
                    );
                } else if(in_array($subject, $decProductSubject)) {
                    $amountOut = $quantity;

                    $updateData = array(
                        'total_balance' => $db->dec($quantity),
                        'total_pending' => $db->inc($quantity)
                    );
                }
                $db->where('id', $invProductID);
                $db->update('inv_product', $updateData);

                // Insert inv product transactions
                $insertData = array(
                    "inv_product_id"    => $invProductID,
                    "client_id"         => $clientID,
                    "subject"           => $subject,
                    "amount_in"         => $amountIn,
                    "amount_out"        => $amountOut,
                    "data"              => $data,
                    "batch_id"          => $batchID,
                    "created_at"        => $todayDate,
                    "creator_id"        => $userID,
                    "creator_type"      => $site,
                    "remark"            => $remark,
                );
                $db->insert("inv_product_transaction",$insertData);
            }

            return true;
        }

        function insertInvStockTransaction($invProduct, $clientID, $subject, $data, $batchID, $remark, $type){
            $db = MysqliDb::getInstance();

            $userID     = $db->userID;
            $site       = $db->userType;
            $todayDate  = date("Y-m-d H:i:s");

            $decProductSubject = array("Issue DO");
            $incProductSubject = array("Cancel DO");
            $acceptedSubject = array_merge($decProductSubject, $incProductSubject);

            if($site == "Member"){
                $creatorType = "System";
            }

            if(!$invProduct || !in_array($subject, $acceptedSubject)){
                return false;
            }

            // Special handle for cancel DO
            if(in_array($type,array("cancelDelivery"))){
                foreach ($invProduct as $packageID => $packageDetail) {
                    foreach ($packageDetail as $invProductID => $stockDetail) {
                        foreach($stockDetail as $stockID => $invProductQuantity){
                            $invStockRes[$packageID][$invProductID]["quantity"] = $invProductQuantity;

                            $db->where("id",$stockID);
                            $invStock = $db->map("id")->get("inv_stock",null,"id,inv_product_id,stock_code,stock_in,stock_out");

                            $invStockRes[$packageID][$invProductID]["invStock"] = $invStock;
                        }
                    }
                }
            }else{
                foreach ($invProduct as $packageID => $packageDetail) {
                    foreach ($packageDetail as $invProductID => $invProductQuantity) {
                        $invStockRes[$packageID][$invProductID]['quantity'] = $invProductQuantity;

                        $db->where('inv_product_id', $invProductID);
                        $invStock = $db->map('id')->get('inv_stock', null, 'id, inv_product_id, stock_code, stock_in, stock_out');
                        $invStockRes[$packageID][$invProductID]['invStock'] = $invStock;
                    }
                }
            }

            unset($packageID);
            unset($packageDetail);

            foreach ($invStockRes as $packageID => $packageDetail) {
                unset($quantity);
                unset($realQuantity);
                foreach ($packageDetail as $invProductID => $invStockRow) {
                    if(in_array($subject, $decProductSubject)){
                        if(!$quantity) $quantity = $invStockRow['quantity'];
                        if(!$realQuantity) $realQuantity = $invStockRow['quantity'];
                    }else{
                        $quantity = $invStockRow['quantity'];
                    }

                    foreach ($invStockRow['invStock'] as $invStockID => $invStock) {
                        if(in_array($subject, $incProductSubject)){
                            $amountIn = $quantity;

                            $updateStock = array(
                                'stock_out' => $db->dec($quantity)
                            );
                        }elseif(in_array($subject, $decProductSubject)){
                            $currentStockBalance = $invStock['stock_in'] - $invStock['stock_out'];

                            if($currentStockBalance <= 0){
                                continue;
                            }

                            if($currentStockBalance <= $quantity){
                                $quantity = $currentStockBalance;
                                $quantityLeft = $realQuantity - $quantity;
                            }else{
                                $quantityLeft = 0;
                            }

                            $amountOut = $quantity;

                            $updateStock = array(
                                'stock_out' => $db->inc($quantity)
                            );
                        }
                        $db->where('id', $invStockID);
                        $db->where('inv_product_id', $invProductID);
                        $db->update('inv_stock', $updateStock);

                        // Insert Inv Product Transactions
                        $insertData = array(
                            "inv_product_id"    => $invProductID,
                            "inv_stock_id"      => $invStockID,
                            "client_id"         => $clientID,
                            "subject"           => $subject,
                            "amount_in"         => $amountIn,
                            "amount_out"        => $amountOut,
                            "data"              => $data,
                            "batch_id"          => $batchID,
                            "created_at"        => $todayDate,
                            "creator_id"        => $userID,
                            "creator_type"      => $site,
                            "remark"            => $remark,
                        );
                        $stockTransaction = $db->insert("inv_stock_transaction",$insertData);

                        // Update inv product total balance
                        switch ($type) {
                            case 'delivery':
                                if(in_array($subject, $decProductSubject)){
                                    $updateData = array(
                                        'total_pending' => $db->dec($quantity),
                                        'total_shipped' => $db->inc($quantity)
                                    );
                                }
                                break;

                            case 'pickup':
                                if(in_array($subject, $decProductSubject)){
                                    $updateData = array(
                                        'total_pending' => $db->dec($quantity),
                                        'total_received' => $db->inc($quantity)
                                    );
                                }
                                break;

                            case 'cancelDelivery':
                                if(in_array($subject, $incProductSubject)){
                                    $updateData = array(
                                        'total_pending' => $db->inc($quantity),
                                        'total_shipped' => $db->dec($quantity)
                                    );
                                }
                                break;

                            case 'cancelPickup':
                                if(in_array($subject, $incProductSubject)){
                                    $updateData = array(
                                        'total_pending' => $db->inc($quantity),
                                        'total_received' => $db->dec($quantity)
                                    );
                                }
                                break;
                        }
                        $db->where('id', $invProductID);
                        $invProduct = $db->update('inv_product', $updateData);

                        $invStockDetail[$packageID][$invProductID][$invStockID] = $invStockRow['quantity'];

                        if(in_array($subject, $decProductSubject)){
                            if($quantityLeft || $quantityLeft > 0){
                                $quantity = $quantityLeft;
                            }else{
                                break;
                            }
                        }
                    }
                }
            }

            return $invStockDetail;
        }

        // -------- Member Product -------- //
        public function getProductIDForSearch($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $search = trim($params['searchWord']);

            if(!$search) {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => '');
            }

            $db->where('module', 'mlm_product');
            $db->where('content', '%'.$search.'%', 'LIKE');
            $db->where('type', 'name');
            $productID = $db->getValue('inv_language', 'module_id', null);
            if(!$productID) {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => '');
            }

            $data['productID'] = $productID;
            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getBuyProductList($params, $onlyProduct){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $searchData = $params['searchData'];
            $sortByData = $params['sortByData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber, 30);

            $userID = $db->userID;
            $site = $db->userType;

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {

                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'minPrice':
                            $db->where('price', $dataValue, ">=");
                            break;

                        case 'maxPrice':
                            $db->where('price', $dataValue, "<=");
                            break;

                        case 'category':

                            $subProductIDAry = $db->subQuery();
                            $subProductIDAry->where('value', '%'.$dataValue.'%', 'LIKE');
                            $subProductIDAry->where('name', 'packageCategory');
                            $subProductIDAry->get('mlm_product_setting', null, 'product_id');

                            $db->where('id', $subProductIDAry, 'IN');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('status', 'Active');
            $db->where('active_at', $dateTime, '<=');
            $db->orderBy('created_at', 'DESC');
            $copyDB = $db->copy();

            $column = array(
                'id',
                'name',
                'code',
                'price'
            );
            $productRes = $db->get('mlm_product', $limit, $column);

            // category
            $categoryRes = $db->get('inv_category', null, 'id, type');
            foreach($categoryRes as $categoryRow) {
                $categoryIDAry[$categoryRow['id']] = $categoryRow['id'];
                $categoryData[$categoryRow['id']]['type'] = $categoryRow['type'];
            }

            if($categoryIDAry){
                $db->where('module_id', $categoryIDAry, 'IN');
                $db->where('module', 'inv_category');
                $db->where('language', $language);
                $db->where('type', 'name');
                $categoryDisplayRes = $db->get('inv_language', null, 'module_id, content');
                foreach($categoryDisplayRes as $cDisplayRow) {
                    $categoryDisplay[$cDisplayRow['module_id']]['id'] = $cDisplayRow['module_id'];
                    $categoryDisplay[$cDisplayRow['module_id']]['display'] = $cDisplayRow['content'];
                }
            }

            $data['categoryList'] = $categoryDisplay;
            if(!$productRes) {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations['B00101'][$language] /* No Results Found */, 'data' => $data);
            }

            foreach($productRes as $productRow) {
                $productIDAry[$productRow['id']] = $productRow['id'];
                // $productIDLangCodeAry[$productRow['id']] = $productRow['translation_code'];
                // $productLangCodeAry[$productRow['translation_code']] = $productRow['translation_code'];
                // $descriptionLangCodeAry[$productRow['description']] = $productRow['description'];
            }

            if($productIDAry) {
                $db->where('product_id', $productIDAry, 'IN');
                $pSettingRes = $db->get('mlm_product_setting', null, 'product_id, name, value, type');

                $psetFilterAry = array('Inactive Image', 'Inactive Video');
                foreach($pSettingRes as $pSetRow) {
                    // do checking if is Image / Video
                    if(in_array($pSetRow['type'], $psetFilterAry)) continue;

                    $productDisplay[$pSetRow['product_id']][$pSetRow['name']] = $pSetRow['value'];

                    if($pSetRow['type'] == "packageCategory"){
                        $packageCategory[$pSetRow['product_id']][$pSetRow['name']][] = $pSetRow['value'];
                    }
                }

                $db->where('module_id', $productIDAry, 'IN');
                $db->where('module', 'mlm_product');
                $db->where('language', $language);
                $db->where('type', array('name', 'desc'), 'IN');
                $productDisplayRes = $db->get('inv_language', null, 'module_id, type, content');
                foreach($productDisplayRes as $pDisplayRow) {
                    $productDisplay[$pDisplayRow['module_id']][$pDisplayRow['type']] = $pDisplayRow['content'];
                }
            }

            foreach($productRes as $productRow) {
                $productRow['price'] = Setting::setDecimal($productRow['price']);

                $productRow['nameDisplay'] = $productDisplay[$productRow['id']]['name'];
                $productRow['description'] = $productDisplay[$productRow['id']]['desc'];

                $productRow['image'] = $productDisplay[$productRow['id']]['Image'];
                $productRow['video'] = $productDisplay[$productRow['id']]['Video'];

                unset($tmp);
                unset($category);

                $tmp = $packageCategory[$productRow['id']]['packageCategory'];
                foreach ($tmp as $value) {
                    $category[] = $categoryDisplay[$value]['display'];
                }
                $productRow['packageCategory'] = implode(', ', $category);

                $productList[] = $productRow;
            }

            $totalRecord = $copyDB->getValue("mlm_product", "count(*)");

            $data['productList'] = $productList;
            $data['categoryList'] = $categoryDisplay;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['totalPage']    = ceil($totalRecord/$limit[1]);
            $data['numRecord']    = $limit[1];

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getBuyProductDetails($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;

            $productCode = trim($params['productCode']);

            if (!$productCode) {
                return array('status' => 'error', 'code' => 2, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data' => '');
            }

            $db->where('code', $productCode);
            $db->where('status', 'Active');

            $productRes = $db->getOne('mlm_product', 'id, code, name, pv_price');
            if (!$productRes) {
                return array('status' => 'error', 'code' => 2, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data' => '');
            }

            if($productRes['id']){
                $db->where('module_id', $productRes['id']);
                $db->where('module', 'mlm_product');
                $db->where('language', $language);
                $db->where('type', array('name', 'desc'), 'IN');
                $packageDisplayRes = $db->get('inv_language', null, 'module_id, type, content');
                foreach($packageDisplayRes as $pDisplayRow) {
                    $packageDisplay[$pDisplayRow['type']] = $pDisplayRow['content'];
                }
            }

            if($clientID){
                $db->where('id', $clientID);
                $countryID = $db->getValue('client', 'country_id');
            }

            if(!$countryID) $countryID = 100; // default indonesia

            $db->where('product_id', $productRes['id']);
            $db->where('country_id', $countryID);
            $db->where('disabled', '0');
            $productPriceRes = $db->getOne('mlm_product_price', 'price, promo_price, m_price, ms_price');
            if(!$productPriceRes){
                $countryID = 100;
                $db->where('product_id', $productRes['id']);
                $db->where('country_id', $countryID);
                $db->where('disabled', '0');
                $productPriceRes = $db->getOne('mlm_product_price', 'price, promo_price, m_price, ms_price');
            }

            $categoryRes = $db->get('inv_category', null, 'id, type');
            foreach($categoryRes as $categoryRow) {
                $categoryIDAry[$categoryRow['id']] = $categoryRow['id'];
                $categoryData[$categoryRow['id']]['type'] = $categoryRow['type'];
            }

            if($categoryIDAry){
                $db->where('module_id', $categoryIDAry, 'IN');
                $db->where('module', 'inv_category');
                $db->where('language', $language);
                $db->where('type', 'name');
                $categoryDisplayRes = $db->get('inv_language', null, 'module_id, content');
                foreach($categoryDisplayRes as $cDisplayRow) {
                    $categoryDisplay[$cDisplayRow['module_id']]['id'] = $cDisplayRow['module_id'];
                    $categoryDisplay[$cDisplayRow['module_id']]['display'] = $cDisplayRow['content'];
                }
            }

            $productDetailID = $productRes['id'];
            $db->where('product_id', $productDetailID);
            $pSettingRes = $db->get('mlm_product_setting', null, 'name, value, type');

            $psetFilterAry = array('Inactive Image', 'Inactive Video');
            foreach($pSettingRes as $pSetRow) {
                // do checking if is Image / Video
                if(in_array($pSetRow['type'], $psetFilterAry)) continue;
                $productDisplay[$pSetRow['name']][] = $pSetRow['value'];

                if($pSetRow['type'] == "packageCategory"){
                    $packageCategory[$pSetRow['name']][] = $pSetRow['value'];
                }
            }

            $db->where('module_id', $productDetailID);
            $db->where('module', 'mlm_product');
            $db->where('language', $language);
            $db->where('type', array('name', 'desc'), 'IN');
            $productDisplayRes = $db->get('inv_language', null, 'module_id, type, content');
            foreach($productDisplayRes as $pDisplayRow) {
                $productDisplay[$pDisplayRow['module_id']][$pDisplayRow['type']] = $pDisplayRow['content'];
            }

            $db->where('name', 'validPackage');
            $db->where('value', '%'.$productDetailID.'%', 'LIKE');
            $allProductIDAry = $db->getValue('inv_product_detail', 'inv_product_id', null);

            $db->where('module_id', $allProductIDAry, 'IN');
            $db->where('module', 'inv_product');
            $db->where('language', $language);
            $db->where('type', array('name', 'desc'), 'IN');
            $invProductDisplayRes = $db->get('inv_language', null, 'module_id, type, content');
            foreach($invProductDisplayRes as $ipDisplayRow) {
                $invProductDisplay[$ipDisplayRow['module_id']][$ipDisplayRow['type']] = $ipDisplayRow['content'];
            }

            $db->where('id', $allProductIDAry, 'IN');
            $db->where('status', 'Active');
            $productData = $db->map('id')->get('inv_product', null, 'id, code, name, weight');

            // prepare output data
            $data['id'] = $productRes['id'];
            $data['code'] = $productRes['code'];
            $data['name'] = $productRes['name'];
            $data['pvPrice'] = $productRes['pv_price'];
            $data['price'] = $productPriceRes['price'];
            $data['promoPrice'] = $productPriceRes['promo_price'];
            $data['mPrice'] = $productPriceRes['m_price'];
            $data['msPrice'] = $productPriceRes['ms_price'];
            $data['img'] = $productDisplay['Image'];
            $data['vid'] = $productDisplay['Video'];
            $data['nameDisplay'] = $packageDisplay['name'];
            $data['description'] = $packageDisplay['desc'];

            $tmp = $packageCategory['packageCategory'];
            foreach ($tmp as $value) {
                $category[] = $categoryDisplay[$value]['display'];
            }
            $data['category'] = $category;

            foreach ($productData as $key => &$value) {
                $value['nameDisplay'] = $invProductDisplay[$value['id']]['name'];
                $value['description'] = $invProductDisplay[$value['id']]['desc'];

                unset($value['id']);
            }
            $data['productList'] = $productData;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        // -------- Member Address -------- //
        public function verifyAddress($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $isDefault          = trim($params["isDefault"]); // 1 or 0, pass into address table, column type
            $addressType        = trim($params["addressType"]); //  delivery or billing
            $fullname           = trim($params["fullname"]);
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']);
            $email              = trim($params['email']);
            $address            = trim($params["address"]);
            $district           = trim($params['districtID']);
            $subDistrict        = trim($params['subDistrictID']);
            $city               = trim($params['cityID']);
            $postalCode         = trim($params['postalCodeID']);
            $state              = trim($params['stateID']);
            $countryID          = trim($params["countryID"]);
            $disabled           = trim($params["disabled"]); // 1 or 0

            $isDefaultInput = array(1,0);
            if(!in_array($isDefault, $isDefaultInput)){
                $errorFieldArr[] = array(
                                    "id" => "isDefaultInput",
                                    "msg" => $translations["E00125"][$language],
                                );
            }

            $addressTypeInput = array("billing","delivery");
            if(empty($addressType) || !in_array($addressType, $addressTypeInput)) {
                $errorFieldArr[] = array(
                    'id'  => "addressTypeError",
                    'msg' => $translations["E01026"][$language]
                );
            }

            if(empty($fullname)) {
                $errorFieldArr[] = array(
                    'id'  => "fullnameError",
                    'msg' => $translations['E00296'][$language] // Please insert full name
                );
            }

            if(empty($dialingArea) || empty($phone)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                    );
            }else {
                if(!preg_match('/^[0-9]*$/', $phone, $matches)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00858"][$language] /* Only number is allowed */
                    );
                }
            }

            if(empty($email)) {
                $errorFieldArr[] = array(
                    'id' => 'emailError',
                    'msg' => $translations["E00318"][$language] /* Please fill in email */
                );
            }else {
                if ($email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorFieldArr[] = array(
                            'id' => 'emailError',
                            'msg' => $translations["E00319"][$language] /* Invalid email format. */
                            );
                    }
                }
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id'  => "addressError",
                    'msg' => $translations['E00943'][$language]
                );
            }

            // if(empty($streetName)) {
            //     $errorFieldArr[] = array(
            //         'id'  => "streetNameError",
            //         'msg' => $translations['E01027'][$language]
            //     );
            // }

            // if(empty($subDistrict)) {
            //     $errorFieldArr[] = array(
            //         'id'  => "subDistrictError",
            //         'msg' => $translations['E01028'][$language]
            //     );
            // }

             // Validate district
            // if(!is_numeric($district) || empty($district)) {
            //     $errorFieldArr[] = array(
            //         'id'  => "districtErrror",
            //         'msg' => $translations['E01113'][$language]
            //     );
            // }else {
            //     $db->where("id",$district);
            //     $db->where("country_id",$countryID);
            //     $db->where("disabled",0);
            //     $districtRes = $db->getOne("county","name,translation_code");
            //     if(!$districtRes){
            //         $errorFieldArr[] = array(
            //             "id"  => "districtErrror",
            //             "msg" => $translations["E01113"][$language]
            //         );
            //     }
            // }

            // Validate sub district
            // if(!is_numeric($subDistrict) || empty($subDistrict)) {
            //     $errorFieldArr[] = array(
            //         'id'  => "subDistrictError",
            //         'msg' => $translations['E01028'][$language]
            //         );
            // }else{
            //     $db->where("id",$subDistrict);
            //     $db->where("country_id",$countryID);
            //     $db->where("disabled",0);
            //     $subDistrictRes = $db->getOne("sub_county","name,translation_code");
            //     if(!$subDistrictRes){
            //         $errorFieldArr[] = array(
            //             "id"  => "subDistrictError",
            //             "msg" => $translations["E01028"][$language]
            //         );
            //     }
            // }

            // Validate postalCode
            // if(!is_numeric($postalCode) || empty($postalCode)) {
            //     $errorFieldArr[] = array(
            //         'id'  => "postalCodeError",
            //         'msg' => $translations['E01030'][$language]
            //     );
            // }else{
            //     $db->where("id",$postalCode);
            //     $db->where("country_id",$countryID);
            //     $db->where("disabled",0);
            //     $postalCodeRes = $db->getOne("zip_code","name,translation_code");
            //     if(!$postalCodeRes){
            //         $errorFieldArr[] = array(
            //             "id"  => "postalCodeError",
            //             "msg" => $translations["E01029"][$language]
            //         );
            //     }
            // }

            // Validate city
            // if(!is_numeric($city) || empty($city)) {
            // if($city != '' || $city != null) {
            //     $errorFieldArr[] = array(
            //         'id'  => "cityError",
            //         'msg' => $translations['E01029'][$language]
            //     );
            // }else{
            //     $db->where("id",$city);
            //     $db->where("country_id",$countryID);
            //     $db->where("disabled",0);
            //     $cityRes = $db->getOne("city","name,translation_code");
            //     if(!$cityRes){
            //         $errorFieldArr[] = array(
            //             "id"  => "cityError",
            //             "msg" => $translations["E01029"][$language]
            //         );
            //     }
            // }

            // if(!is_numeric($state) || empty($state)) {
            // if($state != '' || $state != null) {
            //     $errorFieldArr[] = array(
            //         'id'  => "stateError",
            //         'msg' => $translations['E00667'][$language]
            //       );
            // }else{
            //     $db->where("id",$state);
            //     $db->where("country_id",$countryID);
            //     $db->where("disabled",0);
            //     $stateRes = $db->getOne("state","name,translation_code");
            //     if(!$stateRes){
            //         $errorFieldArr[] = array(
            //             "id"  => "stateError",
            //             "msg" => $translations["E01029"][$language]
            //         );
            //     }
            // }

            if(!is_numeric($countryID) || empty($countryID)) {
                $errorFieldArr[] = array(
                    'id'  => "countryIDError",
                    'msg' => $translations['E00947'][$language]
                );
            }else{
                $db->where("id",$countryID);
                $countryRes = $db->getOne("country","name,translation_code");
                if(!$countryRes){
                    $errorFieldArr[] = array(
                        "id"  => "countryIDError",
                         "msg" => $translations["E01029"][$language]
                    );
                 }
            }

            $disableInput = array(1,0);
            if(!in_array($disabled, $disableInput)){
                $errorFieldArr[] = array(
                            "id" => "disableInputError",
                            "msg" => $translations["E00125"][$language],
                );
            }

            return $errorFieldArr;
        }

        public function manageAddress($params,$type){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $todayDate = date("Y-m-d H:i:s");
            $db2 = $db->copy();
            $clientID = $db->userID;
            $site = $db->userType;
        
            if($site == 'Admin') $clientID = trim($params["clientID"]);
        
            $isDefault          = trim($params["isDefault"]); // 1 or 0, pass into address table, column type
            $addressType        = trim($params["addressType"]); //  delivery or billing
            $fullname           = trim($params["fullName"]);
            // $dialingArea        = trim($params['dialingArea']);
            // $phone              = trim($params['phone']);
            // $email              = trim($params['email']);
            $address            = trim($params["address"]);
            $address2           = trim($params['address2']);
            // $subDistrict        = trim($params['subDistrictID']);
            $city               = trim($params['cityID']);
            $postalCode         = trim($params['postalCodeID']);
            $state              = trim($params['stateID']);
            // $countryID          = trim($params["countryID"]);
            $addressId          = trim($params["id"]);
            $countryID          = '129';
            $disabled           = trim($params["disabled"]); // 1 or 0
            if($clientID == null || $clientID == '')
            {
                $clientID              = trim($params['clientID']);
            }
        
            if($type == "edit" || $type == "delete"){
                $id = $params["clientID"];
                $db->where("client_id",$id);
                $db->where('address_type', $addressType);
                $db->where('id', $addressId);
                $id = $db->get("address",null,"id");
                foreach($id as $row)
                {
                    $addressId['id'] = $row['id'];
        
                    $addressIdList[] = $addressId['id'];
                }
                if(!$addressIdList) return array('status' => "error", 'code' => 2, 'statusMsg' => "Please add Address", 'data' => "");
            }
            // return array("code" => 110, "status" => "ok", "addressId" => $addressId, "addressIdList" => $addressIdList);
            if($type != "delete"){
                // $errorFieldArr = self::verifyAddress($params);
                if(empty($fullname)) {
                    $errorFieldArr[] = array(
                        'id'  => "fullNameError",
                        'msg' => $translations['E00296'][$language]/* Please insert full name */
                    );
                }
                if(empty($address)) {
                    $errorFieldArr[] = array(
                        'id'  => "addressError",
                        'msg' => $translations['E00943'][$language]
                    );
                }
                if(empty($city)) {
                    $errorFieldArr[] = array(
                        'id'  => "cityError",
                        'msg' => $translations['E01029'][$language] /* Please insert City */
                    );
                }
                if(empty($postalCode)) {
                    $errorFieldArr[] = array(
                        'id'  => "postalError",
                        'msg' => $translations['E00946'][$language]/* Please insert Post Code */
                    );
                }
                // $db->where('name',$state);
                // $state_id = $db->getOne('state','id');
                // if($state_id == '' || $state_id == null)
                // {
                //     // $state_id = 0;
                //     return array('status' => "error", 'code' => 1, 'statusMsg' => 'Cannot find the State', 'data' => $data);
                // }
                // else
                // {
                    // $state_id = intval($state_id);
                // }
            }
            if($errorFieldArr){
                 $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }
        
            // $dialingArea = str_replace("+", "", $dialingArea);
            if ($address2 != '' || $address2 != null)
            {
                $address = $address.', '.$address2;
            }
        
            // get client details
            $db->where('id',$clientID);
            $clientDetail = $db->getOne('client','member_id, name, email, dial_code, phone');
            // foreach($clientDetail as $detail)
            // {
            // $fullName = $clientDetail['name'];
            $email = $clientDetail['email'];
            $clientPhone = $clientDetail['phone'];
            // }
        
            // check got existing address or not
            $db->where('client_id',$clientID);
            $db->where('name',$fullname);
            $existAddress = $db->get('address');            
            // if($type == "add")
            // {
            //     if($existAddress)
            //     {
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00740"][$language] /* Address Already Used. */, 'data' => $data);
            //     }
            // }
        
            $data = array(
                "type" => $isDefault,
                "client_id" => $clientID,
                "name" => $fullname,
                "email" => $email,
                "phone" => $clientPhone,
                "address" => $address,
                "state_id" => $state,
                // "district_id" => $district,
                // "sub_district_id" => $subDistrict,
                "city" => $city,
                "post_code" => $postalCode,
                "country_id" => $countryID,
                "address_type" => $addressType,
                "disabled" =>$disabled,
                "updated_at" => $todayDate,
            );
        
            $data2 = array(
                "type" => $isDefault,
                "name" => $fullname,
                "address" => $address,
                "city" => $city,
                "state_id" => $state,
                "country_id" => $countryID,
                "updated_at" => $todayDate,
            );
        
            // check for default
            // only update all address to 0 if current address is set default
            if($isDefault){
                $db->where("client_id",$clientID);
                // $db->where("address_type",'billing','!=');
                $db->update("address",array("type"=>"0"));
            }
            if($type == "add"){
                $data["created_at"] = $todayDate;
                // check got existing billing address or not, if not , insert
                if($addressType == 'billing')
                {
                    $db->where("client_id",$clientID);
                    $db->where('address_type',$addressType);
                    // $db->where("id",$id);
                    $result = $db->get("address");
                    if($result)
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => 'You Cannot add more than one billing address!', 'data' => $data);
                    }
                    else
                    {
                        $db->insert("address",$data);
                    }
                }
                else
                {
                    $db->insert("address",$data);
                    // $db2->where("id",$clientID);
                    // $db2->update("client",$data2);
                }
                $msg = $translations["B00422"][$language]; // Add Successfully
            }else {
                // delete or edit
                // $db->where("id",$id);
                // disable previous address with id $id
                // $db->update("address",array("disabled"=>"1", "updated_at"=>$todayDate));
                if($type == "edit"){
                    // return array("code" => 110, "status" => "ok", "clientID" => $clientID, "addressId" => $addressId);
                    //  return array("code" => 110, "status" => "ok", "clientID" => $clientID, "addressType" => $addressType, "fullname" => $fullname, "address" => $address);
                    $data["created_at"] = $todayDate;
                    $db->where("client_id",$clientID);
                    $db->where('id',$addressId);
                    // $db->where('address_type',$addressType);
                    // $db->where('name', $fullname);
                    // $db->where('address',$address);
                    // $db->where("id",$id);
                    $db->update("address",$data);
                    // $db2->where("id",$clientID);
                    // $db2->update("client",$data2);
                }
                else if($type == "delete")
                {
                    $db->where('id',$addressId);
                    $db->where('client_id',$clientID);
                    $deleteStatus = $db->delete('address');
                    if($deleteStatus)
                    {
                        $msg = $translations["B00373"][$language]; // Update Successfully
                    }
                    else
                    {
                        $msg = $translations["E00743"][$language]; // Failed to Update
                    }
        
                }
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $msg, 'data' => "");
        }

        public function getAddress($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $addressID = $params['id'];

            $clientID = $db->userID;
            $site = $db->userType;

            $db->where("id", $addressID);
            $db->where("client_id",$clientID);

            $address = $db->getOne("address","id,name,email,phone,address,state_id AS stateID,district_id AS districtID ,sub_district_id AS subDistrictID,remarks,city AS cityID,post_code AS postCodeID,type,country_id AS countryID,address_type AS addressType");

            $resultStateList = Country::getState();
            foreach($resultStateList as $stateValue) {
                $stateList[$stateValue['country_id']][] = $stateValue;
            }

            $data["addressDetails"] = $address;
            $countryParams = array("pagination" => "No");
            $resultCountryList = Country::getCountriesList($countryParams);
            $data['countryList'] = $resultCountryList['data']['countriesList'];
            $data['stateList'] = $stateList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getAddressList($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $userID = $db->userID;
            $site = $db->userType;

            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "mainLeaderUsername":
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataType = trim($v['dataType']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'name':
                            if($dataType == "like"){
                                $db->where("name","%".$dataValue."%","LIKE");
                            }else{
                                $db->where("name", $dataValue);
                            }
                            break;
                        case 'username':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN");
                            } else{
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }

                            break;
                        case 'phone':
                            $db->where("phone", $dataValue);
                            break;

                        case 'address':
                            $db->where('address', '%'.$dataValue.'%', 'LIKE');
                            break;
                    }

                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == 'Member'){
                $db->where("client_id", $userID);
                $db->where("disabled", 0);
            }

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue ("address", "count(*)");

            $db->orderBy("type", "DESC");
            $db->orderBy("created_at", "DESC");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }

            $result = $db->get("address",$limit,"id,type,client_id,name,email,phone,address,district_id,sub_district_id,post_code,city,state_id,country_id,address_type,remarks,created_at,updated_at");

            if (empty($result))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            unset($clientIDAry,$districtIDAry,$subDistrictIDAry,$postCodeIDAry,$cityIDAry,$stateIDAry,$countryIDAry);

            foreach($result as $value){
                $clientIDAry[$value["client_id"]] = $value["client_id"];
                $districtIDAry[$value["district_id"]] = $value["district_id"];
                $subDistrictIDAry[$value["sub_district_id"]] = $value["sub_district_id"];
                $postCodeIDAry[$value["post_code"]] = $value["post_code"];
                $cityIDAry[$value["city"]] = $value["city"];
                $stateIDAry[$value["state_id"]] = $value["state_id"];
                $countryIDAry[$value["country_id"]] = $value["country_id"];
            }

            if($clientIDAry){
                $db->where("id",$clientIDAry,"IN");
                $clientRes = $db->map("id")->get("client",null,"id,member_id,username");
            }

            if($districtIDAry){
                $db->where("id",$districtIDAry,"IN");
                $districtRes = $db->map("id")->get("county",null,"id,name,translation_code");
            }

            if($subDistrictIDAry){
                $db->where("id",$subDistrictIDAry,"IN");
                $subDistrictRes = $db->map("id")->get("sub_county",null,"id,name,translation_code");
            }

            if($postCodeIDAry){
                $db->where("id",$postCodeIDAry,"IN");
                $postCodeRes = $db->map("id")->get("zip_code",null,"id,name,translation_code");
            }

            if($cityIDAry){
                $db->where("name",$cityIDAry,"IN");
                $cityRes = $db->map("id")->get("city",null,"id,name,translation_code");
            }

            if($stateIDAry){
                $db->where("id",$stateIDAry,"IN");
                $stateRes = $db->map("id")->get("state",null,"id,name,translation_code");
            }

            if($countryIDAry){
                $db->where("id",$countryIDAry,"IN");
                $countryRes = $db->map("id")->get("country",null,"id,name,translation_code,country_code");
            }

            foreach($result as $value) {
                unset($tempValue);
                if($site == "Admin"){
                    $tempValue['username'] = $clientRes[$value["client_id"]]['username']?:"-";
                }
                $tempValue['deliveryID'] = $value["id"];
                $tempValue['memberID'] = $clientRes[$value['client_id']]['member_id'];
                $tempValue['client_id'] = $value["client_id"];
                $tempValue['fullname'] = $value["name"];
                $tempValue['type'] = $value["type"];
                $tempValue['address_type'] = $value["address_type"];

                $district = $translations[$districtRes[$value["district_id"]]["translation_code"]][$language] ? $translations[$districtRes[$value["district_id"]]["translation_code"]][$language] : $districtRes[$value["district_id"]]["name"];
                $subDistrict = $translations[$subDistrictRes[$value["sub_district_id"]]["translation_code"]][$language] ? $translations[$subDistrictRes[$value["sub_district_id"]]["translation_code"]][$language] : $subDistrictRes[$value["sub_district_id"]]["name"];
                // $postCode = $translations[$postCodeRes[$value["post_code_id"]]["translation_code"]][$language] ? $translations[$postCodeRes[$value["post_code_id"]]["translation_code"]][$language] : $postCodeRes[$value["post_code_id"]]["name"];
                $postCode = $postCodeIDAry[$value['post_code']];
                // $city = $translations[$cityRes[$value["city_id"]]["translation_code"]][$language] ? $translations[$cityRes[$value["city_id"]]["translation_code"]][$language] : $cityRes[$value["city_id"]]["name"];
                $city = $cityIDAry[$value['city']];
                $state = $translations[$stateRes[$value["state_id"]]["translation_code"]][$language] ? $translations[$stateRes[$value["state_id"]]["translation_code"]][$language] : $stateRes[$value["state_id"]]["name"];
                $country = $translations[$countryRes[$value["country_id"]]["translation_code"]][$language] ? $translations[$countryRes[$value["country_id"]]["translation_code"]][$language] : $countryRes[$value["country_id"]]["name"];
                // $tempValue["address"] = $value["address"].", ".$district.", ".$subDistrict.", ".$postCode.", ".$city.", ".$state.", ".$country;
                $tempValue["address"] = $value["address"].", ".$postCode.", ".$city.", ".$state.", ".$country;
                $tempValue["phone"] = "+".$countryRes[$value["country_id"]]["country_code"]." ".$value["phone"];

                if($site == "Member"){
                    if($value["address_type"] == "billing"){
                        $billingList[] = $tempValue;
                    }else{
                        $list[] = $tempValue;
                    }
                }else{
                    $list[] = $tempValue;
                }
            }

            if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }
            if($billingList){
                $data['billingList']   = $billingList;
            }
            $data['list']   = $list;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // -------- Taxes -------- //
        public function setTaxes($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $type = $params['type'];
            $rate = $params['rate'];

            $userID = $db->userID;

            if (!$userID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data' => "");

            if (!$type) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00125"][$language] /* Invalid value. */, 'data' => "");

            if (!is_numeric($rate) || $rate < 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00125"][$language] /* Invalid value. */, 'data'=>"");

            $db->where('id', $userID);
            $creatorID = $db->getValue('admin', 'id');
            if (!$creatorID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid user. */, 'data'=>"");

            $insertData = array(
                'type' => $type,
                'rate' => $rate,
                'created_at' => date('Y-m-d H:i:s'),
                'creator_id' => $creatorID
            );
            $insertRes = $db->insert('inv_tax_charges', $insertData);

            if (!$insertRes) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed. */, 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => '');
        }

        public function getTaxes($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $type = $params['type'];

            if (!$type) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00125"][$language] /* Invalid value. */, 'data' => "");

            $userID = $db->userID;

            if (!$userID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data' => "");

            $db->where('type', $type);
            $db->orderBy('id', 'DESC');
            $res = $db->getOne('inv_tax_charges', 'type, rate');

            if (!$res) {
                $data['type'] = $type ? : '-';
                $data['rate'] = 0;
            } else {
                $data['type'] = $res['type'] ? : '-';
                $data['rate'] = $res['rate'] ? Setting::setDecimal($res['rate']) : '-';
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // -------- Purchase Package Module -------- //
        public function getShoppingCart($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $deliveryFee = Setting::$systemSetting['deliveryFee'];
            $amountDeliveryFee = 0;

            $todayDate  = date('Y-m-d H:i:s');

            $clientID = $db->userID;
            $site = $db->userType;

            if(!$clientID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data' => "");
            }else{
                $db->where('id', $clientID);
                $clientInfo = $db->getOne('client', 'id, country_id');

                if(!$clientInfo){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data' => "");
                }
            }

            $db->where('disabled', 0);
            $db->where('client_id', $clientID);
            $db->orderBy('disabled', 'ASC');
            $db->join('product_template b', 'b.id = a.product_template_id', 'LEFT');
            $db->join('product p', 'p.id = a.product_id', 'LEFT');
            $shoppingCart = $db->get('shopping_cart a', null, 'a.product_id, a.product_template_id, 
                                    b.product_attribute_value_id, a.quantity, a.disabled, p.cost, 
                                    p.sale_price, p.description, p.name');

            $totalSalePrice = 0;

            $product_attribute_value = $db->get('product_attribute_value a', null, 'id,name');
            
            foreach ($shoppingCart as &$cartRow) {
                $packageIDAry[$cartRow['product_id']] = $cartRow['product_id'];

                if (!empty($cartRow['product_attribute_value_id'])){
                    $string = $cartRow['product_attribute_value_id'];  
                    $array = json_decode($string);
                    $string = implode(",", $array);
                    $cartRow['product_attribute_value_id'] = $string;
                    // print_r("array:".json_encode($array)."\n");

                    $name_array = array();
                    foreach ($array as $id) {
                        foreach ($product_attribute_value as $value) {
                          if ($value['id'] == $id) {
                            $name_array[] = $value['name']; // Store name in array
                          }
                        }
                    }
                    $name_string = implode(", ", $name_array);
                    $cartRow['product_attribute_name'] = $name_string;
                }
                else{
                    $cartRow['product_attribute_value_id'] = '';
                    $cartRow['product_attribute_name'] = '';
                }
            }

            if($packageIDAry){
                $db->where("reference_id", array_keys($packageIDAry),"IN");
                $db->where("type",array("Image"),"IN");
                $imageData = $db->get("product_media",null,"reference_id,url");

                unset($imageDataAry);

                foreach($imageData as $imageRow){
                    $imageDataAry[$imageRow["reference_id"]][] = $imageRow["url"];
                }
                
                if ($totalSalePrice > 280){
                    $deliveryFee = 'Free';
                    $amountDeliveryFee = 0;
                }else{
                    $amountDeliveryFee = $deliveryFee;
                }
                
                $db->where("id", $packageIDAry,"IN");
                $pvPriceAry = $db->map("id")->get("product", null, "id, sale_price");
            }

            $redeemAmount = 0;
            $params['userID']  = $clientID;
            $clientDetail = Admin::getMemberDetails($params);
            $Point = $clientDetail['data']['userPointBalance'] ?: 0;

            foreach ($shoppingCart as &$cartRow) {
                unset($cart);

                // $retailPrice = Setting::setDecimal($productPrice[$cartRow['product_id']]['sale_price']);
                $Total = bcmul(number_format($pvPriceAry[$cartRow['product_id']],2),$cartRow['quantity'],2);

                $cart['productID']                     = $cartRow['product_id'];
                $cart['productName']                   = $cartRow['name'];
                $cart['product_template_id']           = $cartRow['product_template_id'];
                $cart['product_attribute_value_id']    = $cartRow['product_attribute_value_id'];
                $cart['product_attribute_name']        = $cartRow['product_attribute_name'];
                $cart['quantity']                      = $cartRow['quantity'];
                $cart['total']                         = $Total;
                $cart['disabled']                      = $cartRow['disabled'];
                $cart["img"]                           = $imageDataAry[$cartRow["product_id"]][0];
                $cart["stockCount"]                    = 100;
                $cart["price"]                         = number_format($pvPriceAry[$cartRow['product_id']],2);
                
                $totalSalePrice += $Total;
                // $promoPrice = Setting::setDecimal($productPrice[$cartRow['product_id']]['promo_price']);
                // $cart["retailPrice"] = $retailPrice;
                // if($discountPercentage == '25'){
                //     $cart["promoPrice"]  = Setting::setDecimal($productPrice[$cartRow['product_id']]['m_price']);
                // }elseif($discountPercentage == '30'){
                //     $cart["promoPrice"]  = Setting::setDecimal($productPrice[$cartRow['product_id']]['ms_price']);
                // }else{
                //     $cart["promoPrice"]  = ($promoPrice > 0) ? $promoPrice : $retailPrice;
                // }
                $cartList[] = $cart;
            }

            $subTotal = $totalSalePrice;
            $totalSalePrice = $totalSalePrice + $amountDeliveryFee;

            $data['cartList'] = $cartList;
            $data['redeemAmount'] = $redeemAmount;
            $data['subTotal'] = $subTotal;
            $data['totalSalePrice'] = $totalSalePrice;
            $data['Taxes'] = 0;
            $data['deliveryFee'] = $deliveryFee;
            $data['Point'] = $Point;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addShoppingCart($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $packageID                = $params['packageID'];
            $quantity                 = $params['quantity'];
            $type                     = $params['type'];
            $product_template         = $params['product_template'];
            $acceptedType             = array('add', 'inc', 'dec', 'num');
            $excludeType              = array('inc', 'dec');
            $todayDate                = date('Y-m-d H:i:s');

            $clientID = $db->userID;
            if($clientID == null)
            {
                $clientID = $params['clientID'];
            }
            $site = $db->userType;

            if(!empty($product_template)){
                $product_template = explode(',', $product_template);
                $product_template = array_map('trim', $product_template);

                $db->where('product_id', $packageID);
                $db->where('product_attribute_value_id',json_encode($product_template));
                $product_template_id = $db->getOne('product_template a');
                $product_template_id = $product_template_id['id'];
            }
            else{
                $db->where('product_id', $packageID);
                $db->where('product_attribute_value_id',$product_template);
                $product_template_id = $db->getOne('product_template a');
                $product_template_id = $product_template_id['id'];
            }
            
            if(!$clientID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00517"][$language] /* Invalid User. */, 'data' => "");
            }else{
                $db->where('id', $clientID);
                $clientInfo = $db->has('client');

                if(!$clientInfo){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00517"][$language] /* Invalid User. */, 'data' => "");
                }
            }

            if(!$packageID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00518"][$language] /* Invalid Stock. */, 'data' => "");
            }else{
                $db->where('id', $packageID);
                $packageInfo = $db->has('product');

                if(!$packageInfo){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00518"][$language] /* Invalid Stock. */, 'data' => "");
                }
            }
            
            if(!$product_template_id) {
                return array('status' => "error", 'code' => 22, 'statusMsg' => 'Invalid product template id', 'data' => "");
            }else{
                $db->where('id', $product_template_id);
                $packageInfo = $db->has('product_template');

                if(!$packageInfo){
                    return array('status' => "error", 'code' => 22, 'statusMsg' => 'Invalid product template id', 'data' => "");
                }
            }

            if(!$type || !in_array($type, $acceptedType)){
                return array('status' => "error", 'code' => 21, 'statusMsg' => $translations["B00519"][$language] /* Invalid Type. */, 'data'=>"");

            }elseif(!in_array($type, $excludeType)){
                if(!$quantity || !is_numeric($quantity) || $quantity <= 0) {
                    return array('status' => "error", 'code' => 21, 'statusMsg' => $translations["B00520"][$language] /* Quantity must be greater than 0. */, 'data'=>"");
                }
            }

            $saleParams['clientID'] = $clientID;
            $CheckSaleOrderDraftStatus = self::CheckSaleOrderDraftStatus($saleParams);

            // if (!$CheckSaleOrderDraftStatus['saleID'] == 0){
            //     unset($params);
            //     $params['quantityOfReward'] = '0';
            //     $params['isRedeemReward'] = '0';
            //     $params['redeemAmount'] = '0';
            //     $params['memberPointDeduct'] = '0';
            //     $params['billing_address'] = $BillingId;
            //     $params['shipping_address'] = $ShippingId;
            //     $params['purchase_amount'] = $purchaseAmount;
            //     $params['shipping_fee'] = $shippingFee;
            //     $params['clientID'] = $clientID;
            //     $addNewPayment = Cash::addNewPayment($params, $clientID); // addNewPayment
            //     if($addNewPayment['status'] == 'error')
            //     {
            //         return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01184"][$language] /* Failed to add new payment. */, 'data' => $addNewPayment);
            //     }
            // }else{
            //     $sale_id = $CheckSaleOrderDraftStatus['saleID'];
            // }

            switch ($type) {
                case 'add':
                    $db->where('disabled', 0);
                    $db->where('client_id', $clientID);
                    $db->where('product_id', $packageID);
                    $db->where('product_template_id', $product_template_id);
                    $copyDB = $db->copy();
                    $shoppingCart = $db->getOne('shopping_cart', 'id, quantity');

                    if($shoppingCart){
                        $updateData = array(
                            'quantity' => $db->inc($quantity),
                        );
                        $copyDB->update('shopping_cart', $updateData);

                    }else{
                        $insertData = array(
                            'client_id'                  => $clientID,
                            'sale_id'                    => $sale_id,
                            'product_id'                 => $packageID,
                            'product_template_id'        => $product_template_id,
                            'quantity'                   => $quantity,
                            'disabled'                   => 0,
                            'updated_at'                 => $todayDate,
                        );
                        $db->insert('shopping_cart', $insertData);
                    }
                    break;

                case 'inc':
                    $quantity = 1;

                    $updateData = array(
                        'quantity' => $db->inc($quantity),
                    );
                    $db->where('disabled', 0);
                    $db->where('client_id', $clientID);
                    $db->where('product_id', $packageID);
                    $db->where('product_template_id', $product_template_id);
                    $db->update('shopping_cart', $updateData);
                    break;

                case 'dec':
                    $quantity = 1;

                    $updateData = array(
                        'quantity' => $db->dec($quantity),
                    );
                    $db->where('disabled', 0);
                    $db->where('client_id', $clientID);
                    $db->where('product_id', $packageID);
                    $db->where('product_template_id', $product_template_id);
                    $db->update('shopping_cart', $updateData);
                    break;

                case 'num':
                    $updateData = array(
                        'quantity' => $quantity,
                    );
                    $db->where('disabled', 0);
                    $db->where('client_id', $clientID);
                    $db->where('product_id', $packageID);
                    $db->where('product_template_id', $product_template_id);
                    $db->update('shopping_cart', $updateData);
                    break;
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00516"][$language] /* Added To Cart */, 'data' => '');
        }

        public function updateShoppingCart($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $package    = $params['package'];
            $todayDate  = date('Y-m-d H:i:s');

            $clientID = $params['clientID'];
            $site = $db->userType;

            if(!$clientID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00517"][$language] /* Invalid User. */, 'data' => "");
            }else{
                $db->where('id', $clientID);
                $clientInfo = $db->has('client');

                if(!$clientInfo){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00517"][$language] /* Invalid User. */, 'data' => "");
                }
            }
               
            foreach ($package as $packageRow) {
                if(!$packageRow['packageID']) {
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00518"][$language] /* Invalid Stock. */, 'data' => "");
                }

                if(!$packageRow['quantity'] || !is_numeric($packageRow['quantity']) || $packageRow['quantity'] <= 0) {
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00520"][$language] /* Quantity must be greater than 0. */, 'data'=>"");
                }

                // if(!$packageRow['product_template']) {
                //     return array('status' => "error", 'code' => 2, 'statusMsg' => 'Invalid Product Template ID.' /* Invalid Product Template ID. */, 'data'=>"");
                // }
            }

            foreach ($package as $packageRow) {
                if(!empty($packageRow['product_template'])){
                    $product_template = explode(',', $packageRow['product_template']);
                    $product_template = array_map('trim', $product_template);
        
                    $db->where('product_id', $packageRow['packageID']);
                    $db->where('product_attribute_value_id',json_encode($product_template));
                    $product_template_id = $db->getOne('product_template a');
                    $product_template_id = $product_template_id['id'];
                }
                else{
                    $db->where('product_id', $packageRow['packageID']);
                    $db->where('product_attribute_value_id',$packageRow['product_template']);
                    $product_template_id = $db->getOne('product_template a');
                    $product_template_id = $product_template_id['id'];
                }
                    
                $db->where('disabled', 0);
                $db->where('client_id', $clientID);
                $db->where('product_id', $packageRow['packageID']);
                $db->where('product_template_id', $product_template_id);
                $copyDB = $db->copy();
                $shoppingCart = $db->getOne('shopping_cart', 'id, quantity');

                if($shoppingCart){
                    $updateData = array(
                        'quantity' => $db->inc($packageRow['quantity']),
                    );
                    $copyDB->update('shopping_cart', $updateData);
                }else{
                    if (is_numeric($product_template_id))
                    {
                        $insertData = array(
                            'client_id'         => $clientID,
                            'product_id'        => $packageRow['packageID'],
                            'product_template_id'          => $product_template_id,
                            'quantity'          => $packageRow['quantity'],
                            'disabled'          => 0,
                            'updated_at'        => $todayDate,
                        );
                        $db->insert('shopping_cart', $insertData);
                    }
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00521"][$language] /* Updated Cart */, 'data' => $data);
        }

        public function removeShoppingCart($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $packageID  = $params['packageID'];
            $product_template  = $params['product_template'];
            $todayDate  = date('Y-m-d H:i:s');

            $clientID = $db->userID;
            $site = $db->userType;

            if(!empty($product_template)){
                $product_template = explode(',', $product_template);
                $product_template = array_map('trim', $product_template);
                
                $db->where('product_id', $packageID);
                $db->where('product_attribute_value_id',json_encode($product_template));
                $product_template_id = $db->getOne('product_template a');
                $product_template_id = $product_template_id['id'];
            }
            else{
                $db->where('product_id', $packageID);
                $db->where('product_attribute_value_id', $product_template);
                $product_template_id = $db->getOne('product_template a');
                $product_template_id = $product_template_id['id'];
            }

            if(!$clientID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00517"][$language] /* Invalid User. */, 'data' => "");
            }else{
                $db->where('id', $clientID);
                $clientInfo = $db->has('client');

                if(!$clientInfo){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00517"][$language] /* Invalid User. */, 'data' => "");
                }
            }

            if(!$packageID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00518"][$language] /* Invalid Stock. */, 'data' => "");
            }else{
                $db->where('id', $packageID);
                $packageInfo = $db->has('product');

                if(!$packageInfo){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["B00518"][$language] /* Invalid Stock. */, 'data' => "");
                }
            }

            if(!$product_template_id) {
                return array('status' => "error", 'code' => 22, 'statusMsg' => 'Invalid product template id', 'data' => "");
            }else{
                $db->where('id', $product_template_id);
                $packageInfo = $db->has('product_template');

                if(!$packageInfo){
                    return array('status' => "error", 'code' => 22, 'statusMsg' => 'Invalid product template id', 'data' => "");
                }
            }

            $db->where('disabled', 0);
            $db->where('client_id', $clientID);
            $db->where('product_id', $packageID);
            $db->where('product_template_id', $product_template_id);
            $db->delete('shopping_cart');

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00454"][$language] /* Removed From Cart */, 'data' => '');
        }

        function CheckSaleOrderDraftStatus($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $clientID = $params['clientID'];

            $db->where("client_id",$clientID);
            $db->where("status",'pending');
            $resSaleOrder = $db->getOne("sale_order");

            if($resSaleOrder){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'saleID' => $resSaleOrder['id']);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'saleID' => 0);
            }
        }

        function validPackageVerification($countryID,$packageAry){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;

            if(!$clientID){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00361"][$language] /* Invalid Client */ , "data" => "");
            }

            if(!$packageAry){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01078"][$language] /* Invalid Package */, "data" => "");
            }

            $db->where('is_starter_kit', 1);
            $starterKitPackage = $db->getValue('mlm_product', 'id', null);

            if($starterKitPackage){
                $db->where('client_id', $clientID);
                $db->where('product_id', $starterKitPackage, 'IN');
                $starterPurchased = $db->getValue('mlm_client_portfolio', 'id');
            }

            unset($packageIDAry);

            foreach($packageAry as $packageRow){
                $packageIDAry[$packageRow["packageID"]] = $packageRow["quantity"];
            }

            $db->where("id",array_keys($packageIDAry),"IN");
            $db->where("status","Active");
            $db->where("active_at",$dateTime,"<=");
            $packageData = $db->map("id")->get("mlm_product",null,"id, name, weight, pv_price as bonusValue, total_balance, total_sold, is_unlimited, total_holding");

            foreach($packageIDAry as $packageID => $packageQty){
                if(!$packageData[$packageID]){
                    $errorFieldArr[] = array(
                        "id" => "package".$packageID."Error",
                        "msg" => $translations["E01090"][$language],
                    );
                }

                if(!$starterPurchased && (!in_array($packageID, $starterKitPackage))){
                    return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01114"][$language], "data" => "");
                }

                if($starterPurchased && (in_array($packageID, $starterKitPackage))){
                    return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01115"][$language], "data" => "");
                }

                if(in_array($packageID, $starterKitPackage) && $packageQty > 1){
                    return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01117"][$language], "data" => "");
                }

                if(in_array($packageID, $starterKitPackage)){
                    $startKitCount += 1;
                }
            }

            if($startKitCount > 1){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01117"][$language], "data" => "");
            }

            foreach($packageData as $packageID => $setting){
                $packageData[$packageID]["weight"] = Setting::setDecimal($setting["weight"]);
                $packageData[$packageID]["bonusValue"] = Setting::setDecimal($setting["bonusValue"]);
                $packageData[$packageID]["totalBalance"] = Setting::setDecimal($setting["total_balance"]);

                $totalBalance = $setting["total_balance"] - $setting["total_sold"] - $setting['total_holding'];
                $isUnlimited = $setting['is_unlimited'];

                if(($totalBalance < $packageIDAry[$packageID]) && $isUnlimited == 0){
                    $errorFieldArr[] = array(
                        "id" => "package".$packageID."Error",
                        "msg" => $translations["E01088"][$language],
                    );
                }

                if(in_array($packageID, $starterKitPackage)){
                    $packageData[$packageID]["isStarterKit"] = 1;
                }

                unset($packageData[$packageID]["total_balance"]);
                unset($packageData[$packageID]["total_sold"]);
            }

            $db->where("product_id",array_keys($packageIDAry),"IN");
            $db->where("type",array("Image"),"IN");
            $packageSetting = $db->get("mlm_product_setting",null,"product_id, type, value");

            if(!$packageSetting){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01078"][$language] /* Invalid Package */, "data" => "");
            }

            foreach($packageSetting as $setting){
                $packageData[$setting["product_id"]][$setting["type"]][] = $setting["value"];
            }

            $db->where("product_id",array_keys($packageIDAry),"IN");
            $db->where("country_id", $countryID);
            $db->where("disabled", 0);
            $packageSetting = $db->get("mlm_product_price",null,"product_id, price, promo_price as promoPrice, m_price as mPrice, ms_price as msPrice");

            unset($packagePriceAry);

            foreach($packageSetting as $setting){
                $packageData[$setting["product_id"]]['price'] = Setting::setDecimal($setting['price']);
                $packageData[$setting["product_id"]]['promoPrice'] = Setting::setDecimal($setting['promoPrice']);

                if(!in_array($setting["product_id"], $starterKitPackage)){
                    $packageData[$setting["product_id"]]['mPrice'] = Setting::setDecimal($setting['mPrice']);
                    $packageData[$setting["product_id"]]['msPrice'] = Setting::setDecimal($setting['msPrice']);
                }else{
                    $packageData[$setting["product_id"]]['mPrice'] = 0;
                    $packageData[$setting["product_id"]]['msPrice'] = 0;
                }

                $packagePriceAry[$setting["product_id"]] = $setting["product_id"];
            }

            foreach($packageIDAry as $packageID => $packageQty){
                if(!$packagePriceAry[$packageID]){
                    $errorFieldArr[] = array(
                        "id" => "package".$packageID."Error",
                        "msg" => $translations["E01091"][$language],
                    );
                }
            }

            if($errorFieldArr){
                $dataOut["errorFieldArr"] = $errorFieldArr;
            }

            $db->where("value",array_keys($packageIDAry),"IN");
            $db->where("type","validPackage");
            $productDetail = $db->get("inv_product_detail",null,"value as package_id, inv_product_id, reference as product_qty");

            unset($productIDAry);

            foreach($productDetail as $productRow){
                $productIDAry[$productRow["inv_product_id"]] = $productRow["inv_product_id"];
            }

            if($productIDAry){
                $db->where("id",$productIDAry,"IN");
                $db->where("status","Active");
                $productData = $db->map("id")->get("inv_product",null,"id,weight");

                foreach($productDetail as $productRow){
                    unset($tempProduct);

                    $tempProduct["productID"] = $productRow["inv_product_id"];
                    $tempProduct["quantity"] = Setting::setDecimal($productRow["product_qty"]);
                    $tempProduct["weight"] = Setting::setDecimal($productData[$productRow["inv_product_id"]], 3);

                    $packageData[$productRow["package_id"]]["product"][] = $tempProduct;
                }
            }

            $dataOut["packageData"] = $packageData;

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $dataOut);
        }

        function getClientActiveAddress(){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;

            if(!$clientID){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00361"][$language] /* Invalid Client */, "data" => "");
            }

            $db->where("name", "pickUpOrigins");
            $companyAddressRes = $db->get("system_settings", null, "name, value AS pickupID, reference as address");
            foreach ($companyAddressRes as &$companyAddressRow) {
                $companyAddressRow['name'] = 'HQ';
            }

            if(!$companyAddressRes){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01086"][$language] /* Invalid Address */, "data" => "");
            }

            /*foreach($companyAddressRes as $companyAddressRow){
                unset($tempAddress);

                $tempAddress["pickupID"] = $companyAddressRow["reference"];
                $tempAddress["name"] = $companyAddressRow["name"];
                $tempAddress["address"] = $companyAddressRow["value"];

                $pickupAddressData[] = $tempAddress;
            }*/

            $dataOut["pickupAddressData"] = $companyAddressRes;

            $db->where("client_id", $clientID);
            $db->where("disabled", "0");
            $db->orderBy("type", "DESC");
            $addressData = $db->get("address",null,"id, type, name, email, phone, address, state_id, district_id, sub_district_id, city, post_code, country_id, address_type");

            if(!$addressData){
                return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $dataOut);
            }

            unset($countryIDAry,$stateIDAry,$districtIDAry,$subDistrictIDAry,$cityIDAry,$postCodeIDAry);

            foreach($addressData as $addressRow){
                $countryIDAry[$addressRow["country_id"]] = $addressRow["country_id"];
                $stateIDAry[$addressRow["state_id"]] = $addressRow["state_id"];
                $districtIDAry[$addressRow["district_id"]] = $addressRow["district_id"];
                $subDistrictIDAry[$addressRow["sub_district_id"]] = $addressRow["sub_district_id"];
                $cityIDAry[$addressRow["city"]] = $addressRow["city"];
                $postCodeIDAry[$addressRow["post_code"]] = $addressRow["post_code"];
            }

            if($countryIDAry && $stateIDAry && $districtIDAry && $subDistrictIDAry && $cityIDAry && $postCodeIDAry){
                $db->where("id",$countryIDAry,"IN");
                $countryDisplay = $db->map("id")->get("country",null,"id,name,translation_code,country_code");

                $db->where("id",$stateIDAry,"IN");
                $stateDisplay = $db->map("id")->get("state",null,"id,name,translation_code");

                $db->where("id",$districtIDAry,"IN");
                $districtDisplay = $db->map("id")->get("county",null,"id,name");

                $db->where("id",$subDistrictIDAry,"IN");
                $subDistrictDisplay = $db->map("id")->get("sub_county",null,"id,name");

                $db->where("id",$cityIDAry,"IN");
                $cityDisplay = $db->map("id")->get("city",null,"id,name");

                $db->where("id",$postCodeIDAry,"IN");
                $postCodeDisplay = $db->map("id")->get("zip_code",null,"id,name");
            }

            unset($deliveryAddressData,$billingAddressData);

            foreach($addressData as $addressRow){
                unset($tempAddress);

                $tempAddress["addressID"] = $addressRow["id"];
                $tempAddress["isDefault"] = ($addressRow["type"] ? 1 : 0);
                $tempAddress["fullName"] = $addressRow["name"];
                $tempAddress["emailAddress"] = $addressRow["email"];
                $tempAddress["dialingArea"] = $countryDisplay[$addressRow["country_id"]]["country_code"];
                $tempAddress["phoneNumber"] = $addressRow["phone"];
                $tempAddress["address"] = $addressRow["address"];
                $tempAddress["stateID"] = $addressRow["state_id"];
                $tempAddress["stateName"] = $stateDisplay[$addressRow["state_id"]]["name"];
                $tempAddress["stateDisplay"] = $translations[$stateDisplay[$addressRow["state_id"]]["translation_code"]][$language];
                $tempAddress["districtID"] = $addressRow["district_id"];
                $tempAddress["district"] = $districtDisplay[$addressRow["district_id"]];
                $tempAddress["subDistrictID"] = $addressRow["sub_district_id"];
                $tempAddress["subDistrict"] = $subDistrictDisplay[$addressRow["sub_district_id"]];
                $tempAddress["cityID"] = $addressRow["city"];
                $tempAddress["city"] = $cityDisplay[$addressRow["city"]];
                $tempAddress["postalCodeID"] = $addressRow["post_code"];
                $tempAddress["postalCode"] = $postCodeDisplay[$addressRow["post_code"]];
                $tempAddress["countryID"] = $addressRow["country_id"];
                $tempAddress["countryName"] = $countryDisplay[$addressRow["country_id"]]["name"];
                $tempAddress["countryDisplay"] = $translations[$countryDisplay[$addressRow["country_id"]]["translation_code"]][$language];

                switch($addressRow["address_type"]){
                    case "delivery":
                        $deliveryAddressData[$addressRow["id"]] = $tempAddress;
                        break;

                    case "billing":
                        $billingAddressData[] = $tempAddress;
                        break;

                    default:
                        break;
                }
            }

            $dataOut["deliveryAddressData"] = $deliveryAddressData;
            $dataOut["billingAddressData"] = $billingAddressData;

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $dataOut);
        }

        public function checkValidVoucher($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $site = $db->userType;
            $sessionData = $db->sessionID;

            if(!$clientID){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00361"][$language], "data" => "");
            }

            $voucherCode = trim($params["voucherCode"]);
            $packageIDAry = $params["packageIDAry"];
            $courierService = trim($params["courierService"]);

            $db->where("code",$voucherCode);
            $db->where("status","Active");
            $voucherData = $db->getOne("inv_voucher","id,type,total_balance,total_used,is_unlimited");

            $voucherID = $voucherData["id"];
            $voucherType = $voucherData["type"];
            $voucherQuantity = $voucherData["total_balance"];
            $voucherSold = $voucherData["total_used"];
            $voucherBalance = ($voucherQuantity - $voucherSold);
            $unlimitedVoucher = $voucherData["is_unlimited"];

            if(empty($voucherData)){
                $errorFieldArr[] = array(
                    "id" => "voucherCodeError",
                    "msg" => $translations["E01126"][$language],
                );
            }else{
                $db->where("inv_voucher_id",$voucherID);
                $db->where("type","validPackage");
                $voucherPackageIDAry = $db->getValue("inv_voucher_detail","value",null);

                $db->where("inv_voucher_id",$voucherID);
                $db->where("type","tieUpType");
                $voucherTieUpType = $db->getValue("inv_voucher_detail","value");

                if(($unlimitedVoucher == 0) && ($voucherBalance <= 0)){
                    $errorFieldArr[] = array(
                        "id" => "voucherCodeError",
                        "msg" => $translations["E01127"][$language],
                    );
                }else{
                    if(($voucherTieUpType) && ($voucherPackageIDAry)){
                        switch($voucherTieUpType){
                            case "normal":
                                if(($voucherPackageIDAry) && (empty(array_intersect($voucherPackageIDAry,$packageIDAry)))){
                                    $errorFieldArr[] = array(
                                        "id" => "voucherCodeError",
                                        "msg" => $translations["E01128"][$language],
                                    );
                                }
                                break;

                            case "single":
                                if($voucherPackageIDAry){
                                    foreach($packageIDAry as $packageID){
                                        if((!in_array($packageID,$voucherPackageIDAry))){
                                            $errorFieldArr[] = array(
                                                "id" => "voucherCodeError",
                                                "msg" => $translations["E01128"][$language],
                                            );
                                            break;
                                        }
                                    }
                                }
                                break;

                            default:
                                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E01126"][$language], "data" => "");
                                break;
                        }
                    }else{
                        if(($voucherPackageIDAry) && (empty(array_intersect($voucherPackageIDAry,$packageIDAry)))){
                            $errorFieldArr[] = array(
                                "id" => "voucherCodeError",
                                "msg" => $translations["E01128"][$language],
                            );
                        }
                    }
                }
            }

            if($errorFieldArr){
                $data["field"] = $errorFieldArr;
                return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00130"][$language], "data" => $data);
            }

            $db->where("inv_voucher_id",$voucherID);
            $db->where("name","discountBy");
            $discountByData = $db->getOne("inv_voucher_detail","value,type,reference");

            $discountType = $discountByData["type"];

            $isPercentage = 0;
            $isAmount = 0;
            switch($discountType){
                case "percentage":
                    $discountPercentage = $discountByData["value"];
                    $maxDiscountAmount = $discountByData["reference"];
                    $isPercentage = 1;
                    break;

                case "amount":
                    $discountAmount = $discountByData["value"];
                    $isAmount = 1;
                    break;

                default:
                    break;
            }

            switch($voucherType){
                case "delivery":
                    $db->where("token",$sessionData);
                    $sessionID = $db->getValue("client_session","id");

                    if($sessionID){
                        $db->where("session_id",$sessionID);
                        $db->where("client_id",$clientID);
                        $db->where("type","shippingFee");
                        $shippingFeeData = $db->getValue("session_data","data");

                        if(empty($shippingFeeData)){
                            $errorFieldArr[] = array(
                                "id" => "courierServiceError",
                                "msg" => $translations["E01129"][$language],
                            );
                        }else{
                            $shippingFeeData = json_decode($shippingFeeData,true);

                            unset($courierData);
                            foreach ($shippingFeeData as $shippingFeeRow) {
                                foreach ($shippingFeeRow as $shippingFee) {
                                    $courierData[$shippingFee['courier']] = $shippingFee;
                                }
                            }

                            unset($courierDataAry);
                            foreach($courierData as $courierRow){
                                $courierDataAry[$courierRow["courier"]] = $courierRow["price"];
                            }

                            $amount = $courierDataAry[$courierService];

                            if(empty($amount) || empty($courierService)){
                                $errorFieldArr[] = array(
                                    "id" => "courierServiceError",
                                    "msg" => $translations["E01129"][$language],
                                );
                            }
                        }
                    }
                    break;

                default:
                    return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00741"][$language], "data" => "");
                    break;
            }

            if($errorFieldArr){
                $data["field"] = $errorFieldArr;
                return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00130"][$language], "data" => $data);
            }

            if($isPercentage){
                $discountAmount = Setting::setDecimal(($amount * ($discountPercentage/100)));
                if(($maxDiscountAmount > 0) && ($discountAmount >= $maxDiscountAmount)){
                    $discountAmount = $maxDiscountAmount;
                }
            }

            $data["voucherType"] = $voucherType;
            $data["discountAmount"] = $discountAmount;

            return array("status" => "ok", "code" => 0, "statusMsg" => $translations["B00484"][$language], "data" => $data);
        }

        public function insertOrderVoucher($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $invOrderID = trim($params["invOrderID"]);
            $voucherCode = trim($params["voucherCode"]);
            $realDiscountAmount = trim($params["realDiscountAmount"]);

            $db->where("code",$voucherCode);
            $invVoucherData = $db->getOne("inv_voucher","id,type");

            $invVoucherID = $invVoucherData["id"];
            $invVoucherType = $invVoucherData["type"];

            if($invVoucherID){
                $db->where("inv_voucher_id",$invVoucherID);
                $db->where("name","discountBy");
                $invVoucherDetail = $db->getOne("inv_voucher_detail","value,type,reference");

                $discountType = $invVoucherDetail["type"];

                switch($discountType){
                    case "percentage":
                        $discountPercentage = $invVoucherDetail["value"];
                        $discountAmount = $invVoucherDetail["reference"];
                        break;

                    case "amount":
                        $discountAmount = $invVoucherDetail["value"];
                        break;

                    default:
                        break;
                }
            }

            unset($insertData);

            $insertData = array(
                "inv_order_id" => $invOrderID,
                "inv_voucher_id" => $invVoucherID,
                "type" => $invVoucherType,
                "discount_type" => $discountType,
                "discount_percentage" => Setting::setDecimal($discountPercentage),
                "discount_amount" => Setting::setDecimal($discountAmount),
                "real_discount_amount" => Setting::setDecimal($realDiscountAmount),
            );

            $insertVoucher = $db->insert("inv_order_voucher",$insertData);

            if(!$insertVoucher){
                return false;
            }

            unset($updateData);

            $updateData = array(
                "total_used" => $db->inc(1),
            );

            $db->where("id",$invVoucherID);
            $updateVoucher = $db->update("inv_voucher",$updateData);

            if(!$updateVoucher){
                return false;
            }

            return true;
        }

        public function purchasePackageVerification($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $deliveryOptions = Setting::$systemSetting["deliveryOption"];
            $deliveryOptions = explode("#",$deliveryOptions);
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;
            $sessionData = $db->sessionID;

            $packageAry = $params["packageAry"];
            $deliveryOption = trim($params["deliveryOption"]);
            $courierCompany = $params['courierCompany'];
            $courierService = $params['courierService'];
            // $pickupID = trim($params["pickupID"]);

            $addressID = trim($params["addressID"]);
            $fullName = trim($params["fullName"]);
            $countryID = trim($params["countryID"]);
            $address = trim($params["address"]);
            $district = trim($params["district"]);
            $subDistrict = trim($params["subDistrict"]);
            $city = trim($params["city"]);
            $stateID = trim($params["stateID"]);
            $postalCode = trim($params["postalCode"]);
            $dialingArea = trim($params["dialingArea"]);
            $phoneNumber = trim($params["phoneNumber"]);
            $emailAddress = trim($params["emailAddress"]);
            $isBillingAddress = trim($params["isBillingAddress"]);
            $makePaymentMethod = trim($params["makePaymentMethod"]);
            $nicepayBankCode = trim($params["nicepayBankCode"]);
            $submitCreditCard = trim($params["submitCreditCard"]);

            $ccNo           = trim($params["ccNo"]);
            $ccExpiry       = trim($params["ccExpiry"]);
            $ccCvv          = trim($params["ccCvv"]);
            $ccHolderName   = trim($params["ccHolderName"]);

            //Placement Option
            $placementPosition    = trim($params["placementPosition"]);

            /*$billingFullName = trim($params["billingFullName"]);
            $billingCountryID = trim($params["billingCountryID"]);
            $billingAddress = trim($params["billingAddress"]);
            $billingDistrict = trim($params["billingDistrict"]);
            $billingSubDistrict = trim($params["billingSubDistrict"]);
            $billingCity = trim($params["billingCity"]);
            $billingStateID = trim($params["billingStateID"]);
            $billingPostalCode = trim($params["billingPostalCode"]);
            $billingDialingArea = trim($params["billingDialingArea"]);
            $billingPhoneNumber = trim($params["billingPhoneNumber"]);
            $billingEmailAddress = trim($params["billingEmailAddress"]);*/

            $voucherCode = trim($params["voucherCode"]); // Optional
            $spendCredit = $params["spendCredit"];
            $purchaseSpecialNote = trim($params["purchaseSpecialNote"]); // Optional
            $purchaseRemark = trim($params["purchaseRemark"]); // Optional
            $step = trim($params["step"]);

            if(!$step) $step = 1;

            if(!$clientID){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations['E00679'][$language] /* Invalid User */ , "data" => "");
            }

            $tmpParams['type'] = "purchase";
            $checkMemberKYC = Client::checkMemberKYCStatus($tmpParams);
            if(!(empty($checkMemberKYC))){
                foreach($checkMemberKYC as &$kycDoc){
                    $kycDoc = General::getTranslationByName($kycDoc);
                }
                $kycErr = implode(", ",$checkMemberKYC);
                return array("status" => "error", "code" => 2, "statusMsg" => str_replace("%%kyc%%",$kycErr,$translations["E01094"][$language]), "data" => "");
            }

            if($step == 1){
                foreach($deliveryOptions as $delOption){
                    unset($tempOption);

                    $tempOption["option"] = $delOption;
                    $tempOption["optionDisplay"] = General::getTranslationByName($delOption);

                    $deliveryOption[] = $tempOption;
                }

                /*unset($countryParams);
                $countryParams = array(
                    "deliveryCountry" => "Yes",
                );
                $countryReturn = Country::getCountriesList($countryParams);
                $data["countryList"] = $countryReturn["data"]["countriesList"];

                $stateList = Country::getState();
                $data["stateList"] = $stateList;*/
            }

            $isAddDeliveryAddress = 0;
            $subTotal = 0;
            $shippingFee = 0;
            $taxPercentage = 0;
            $taxes = 0;
            $totalPrice = 0;
            $totalBV = 0;
            $totalWeight = 0;

            $db->where("id", $clientID);
            $clientDetails      = $db->getOne("client", "country_id, member_id, email,sponsor_id");
            $clientMemberID     = $clientDetails['member_id'];
            $clientCountryID    = $clientDetails['country_id'];
            $clientEmail        = $clientDetails['email'];
            $clientSponsorID    = $clientDetails['sponsor_id'];

            $db->where("id",$clientSponsorID);
            $sponsorDetails     = $db->getOne("client", "member_id, name");
            $sponsorMemberID    = $sponsorDetails['member_id'];
            $sponsorName        = $sponsorDetails['name'];

            if(!$packageAry){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations['E01078'][$language] /* Invalid Package */ , "data" => "");
            }else{
                $validPackageReturn = Inventory::validPackageVerification($clientCountryID,$packageAry);

                if(strtolower($validPackageReturn["status"]) != "ok"){
                    return $validPackageReturn;
                }

                $packageData = $validPackageReturn["data"]["packageData"];
                $errorFieldArr = $validPackageReturn["data"]["errorFieldArr"];
            }

            $db->where('name', 'Indonesia');
            $payCurrenyCode = $db->getValue('country', 'currency_code');

            $activeAddressReturn = Inventory::getClientActiveAddress();
            $deliveryAddressData = $activeAddressReturn["data"]["deliveryAddressData"];
            $billingAddressData = $activeAddressReturn["data"]["billingAddressData"];
            $pickupAddressData = $activeAddressReturn["data"]["pickupAddressData"];

            $clientRank = Bonus::getClientRank('Bonus Tier', array($clientID), '', 'discount');
            $clientDiscountPercentage = $clientRank[$clientID]['percentage'];

            if($step >= 2){
                if((!in_array($deliveryOption,$deliveryOptions))){
                    $errorFieldArr[] = array(
                        "id" => "deliveryOptionError",
                        "msg" => $translations["E00125"][$language],
                    );

                }/*elseif($deliveryOption == "pickup"){

                    if(!$pickupID){
                        $errorFieldArr[] = array(
                            "id" => "pickupIDError",
                            "msg" => $translations["E01103"][$language],
                        );
                    }else{
                        foreach ($pickupAddressData as $pickupAddressRow) {
                            $pickupAddressIDAry[$pickupAddressRow['pickupID']] = $pickupAddressRow['pickupID'];
                        }

                        if(!in_array($pickupID, $pickupAddressIDAry)){
                            $errorFieldArr[] = array(
                                "id" => "pickupIDError",
                                "msg" => $translations["E01104"][$language],
                            );
                        }
                    }

                }*/elseif($deliveryOption == "delivery"){

                    if($addressID){
                        $db->where("id",$addressID);
                        $db->where("disabled",0);
                        $db->where("address_type","delivery");
                        $addressRes = $db->getOne("address","*");

                        $countryID = $addressRes["country_id"];
                        $stateID = $addressRes["state_id"];

                        if(!$addressRes){
                            $errorFieldArr[] = array(
                                "id" => "addressError",
                                "msg" => $translations["E00125"][$language],
                            );
                        } else {
                            $db->where("id", $addressRes["post_code_id"]);
                            $postalCodeRes = $db->getOne("zip_code","name, tariff_code, destination_id");
                        }

                        $deliveryAddressID = $addressID;

                    }else{

                        $isAddDeliveryAddress = 1;

                        if(!$fullName){
                            $errorFieldArr[] = array(
                                "id" => "fullNameError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }

                        if(!$address){
                            $errorFieldArr[] = array(
                                "id" => "addressError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }

                        if(!$countryID){
                            $errorFieldArr[] = array(
                                "id" => "countryIDError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }else{
                            $db->where("id",$countryID);
                            $db->where("status","Active");
                            $countryRes = $db->getOne("country","id,name,translation_code");
                            $countryID = $countryRes["id"];

                            if(!$countryID){
                                $errorFieldArr[] = array(
                                    "id" => "countryIDError",
                                    "msg" => $translations["E00125"][$language],
                                );
                            }
                        }

                        if(!$stateID){
                            $errorFieldArr[] = array(
                                "id" => "stateIDError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }else{
                            $db->where("id",$stateID);
                            $db->where("country_id",$countryID);
                            $db->where("disabled",0);
                            $stateRes = $db->getOne("state","id,name,translation_code");
                            $stateID = $stateRes["id"];

                            if(!$stateID){
                                $errorFieldArr[] = array(
                                    "id" => "stateIDError",
                                    "msg" => $translations["E00125"][$language],
                                );
                            }
                        }

                        if(!$city){
                            $errorFieldArr[] = array(
                                "id" => "cityError",
                                "msg" => $translations["E01029"][$language],
                            );
                        }else{
                            $db->where("id",$city);
                            $db->where("state_id",$stateID);
                            $db->where("country_id",$countryID);
                            $db->where("disabled",0);
                            $cityRes = $db->getOne("city","name");
                            if(!$cityRes){
                                $errorFieldArr[] = array(
                                    "id"  => "cityError",
                                    "msg" => $translations["E01029"][$language]
                                );
                            }
                        }

                        if(!$district){
                            $errorFieldArr[] = array(
                                "id" => "districtError",
                                "msg" => $translations["E01113"][$language],
                            );
                        }else{
                            $db->where("id",$district);
                            $db->where("city_id",$city);
                            $db->where("country_id",$countryID);
                            $db->where("disabled",0);
                            $districtRes = $db->getOne("county","name");
                            if(!$districtRes){
                                $errorFieldArr[] = array(
                                    "id"  => "districtErrror",
                                    "msg" => $translations["E01113"][$language]
                                );
                            }
                        }

                        if(!$subDistrict){
                            $errorFieldArr[] = array(
                                "id" => "subDistrictError",
                                "msg" => $translations["E01028"][$language]
                            );
                        }else{
                            $db->where("id",$subDistrict);
                            $db->where("county_id",$district);
                            $db->where("country_id",$countryID);
                            $db->where("disabled",0);
                            $subDistrictRes = $db->getOne("sub_county","name");
                            if(!$subDistrictRes){
                                $errorFieldArr[] = array(
                                    "id"  => "subDistrictError",
                                    "msg" => $translations["E01028"][$language]
                                );
                            }
                        }

                        if(!$postalCode){
                            $errorFieldArr[] = array(
                                "id" => "postalCodeError",
                                "msg" => $translations["E01029"][$language],
                            );
                        }else{
                            $db->where("id",$postalCode);
                            $db->where("sub_county_id",$subDistrict);
                            $db->where("country_id",$countryID);
                            $db->where("disabled",0);
                            $postalCodeRes = $db->getOne("zip_code","name, tariff_code, destination_id");
                            if(!$postalCodeRes){
                                $errorFieldArr[] = array(
                                    "id"  => "postalCodeError",
                                    "msg" => $translations["E01029"][$language]
                                );
                            }
                        }

                        if(!$dialingArea || !$phoneNumber){
                                $errorFieldArr[] = array(
                                    "id" => "phoneNumberError",
                                    "msg" => $translations["E00305"][$language],
                                );
                        }else{
                            if(!preg_match("/^[0-9]*$/",$phoneNumber,$matches)){
                                $errorFieldArr[] = array(
                                    "id" => "phoneNumberError",
                                    "msg" => $translations["E00858"][$language]
                                );
                            }
                        }

                        if(!$emailAddress){
                            $errorFieldArr[] = array(
                                "id" => "emailAddressError",
                                "msg" => $translations["E00318"][$language],
                            );
                        }else{
                            if($emailAddress){
                                if(!filter_var($emailAddress,FILTER_VALIDATE_EMAIL)){
                                    $errorFieldArr[] = array(
                                        "id" => "emailAddressError",
                                        "msg" => $translations["E00319"][$language],
                                    );
                                }
                            }
                        }
                    }

                    $isBillingAddressInput = array(1,0);
                    if((!in_array($isBillingAddress,$isBillingAddressInput))){
                        $errorFieldArr[] = array(
                            "id" => "isBillingAddressError",
                            "msg" => $translations["E00125"][$language],
                        );
                    }

                    /*if(!$isBillingAddress){
                        if(!$billingFullName){
                            $errorFieldArr[] = array(
                                "id" => "billingFullNameError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }

                        if(!$billingAddress){
                            $errorFieldArr[] = array(
                                "id" => "billingAddressError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }

                        if(!$billingCountryID){
                            $errorFieldArr[] = array(
                                "id" => "billingCountryIDError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }else{
                            $db->where("id",$billingCountryID);
                            $db->where("status","Active");
                            $billingCountryRes = $db->getOne("country","id,name,translation_code");
                            $billingCountryID = $billingCountryRes["id"];

                            if(!$billingCountryID){
                                $errorFieldArr[] = array(
                                    "id" => "billingCountryIDError",
                                    "msg" => $translations["E00125"][$language],
                                );
                            }
                        }

                        if(!$billingStateID){
                            $errorFieldArr[] = array(
                                "id" => "billingStateIDError",
                                "msg" => $translations["E00125"][$language],
                            );
                        }else{
                            $db->where("id",$billingStateID);
                            $db->where("disabled",0);
                            $billingStateRes = $db->getOne("state","id,name,translation_code");
                            $billingStateID = $billingStateRes["id"];

                            if(!$billingStateID){
                                $errorFieldArr[] = array(
                                    "id" => "billingStateIDError",
                                    "msg" => $translations["E00125"][$language],
                                );
                            }
                        }

                        if(!$billingCity){
                            $errorFieldArr[] = array(
                                "id" => "billingCityError",
                                "msg" => $translations["E01029"][$language],
                            );
                        }else{
                            $db->where("id",$billingCity);
                            $db->where("state_id",$billingStateID);
                            $db->where("country_id",$billingCountryID);
                            $db->where("disabled",0);
                            $billingCityRes = $db->getOne("city","name");
                            if(!$billingCityRes){
                                $errorFieldArr[] = array(
                                    "id"  => "billingCityError",
                                    "msg" => $translations["E01029"][$language]
                                );
                            }
                        }

                        if(!$billingDistrict){
                            $errorFieldArr[] = array(
                                "id" => "billingDistrictError",
                                "msg" => $translations["E01113"][$language],
                            );
                        }else{
                            $db->where("id",$billingDistrict);
                            $db->where("city_id",$billingCity);
                            $db->where("country_id",$billingCountryID);
                            $db->where("disabled",0);
                            $billingDistrictRes = $db->getOne("county","name");
                            if(!$billingDistrictRes){
                                $errorFieldArr[] = array(
                                    "id"  => "billingDistrictError",
                                    "msg" => $translations["E01113"][$language]
                                );
                            }
                        }

                        if(!$billingSubDistrict){
                            $errorFieldArr[] = array(
                                "id" => "billingSubDistrictError",
                                "msg" => $translations["E01028"][$language]
                            );
                        }else{
                            $db->where("id",$billingSubDistrict);
                            $db->where("county_id",$billingDistrict);
                            $db->where("country_id",$billingCountryID);
                            $db->where("disabled",0);
                            $billingSubDistrictRes = $db->getOne("sub_county","name");
                            if(!$billingSubDistrictRes){
                                $errorFieldArr[] = array(
                                    "id"  => "billingSubDistrictError",
                                    "msg" => $translations["E01028"][$language]
                                );
                            }
                        }

                        if(!$billingPostalCode){
                            $errorFieldArr[] = array(
                                "id" => "billingPostalCodeError",
                                "msg" => $translations["E01029"][$language],
                            );
                        }else{
                            $db->where("id",$billingPostalCode);
                            $db->where("sub_county_id",$billingSubDistrict);
                            $db->where("country_id",$billingCountryID);
                            $db->where("disabled",0);
                            $billingPostalCodeRes = $db->getOne("zip_code","name");
                            if(!$billingPostalCodeRes){
                                $errorFieldArr[] = array(
                                    "id"  => "billingPostalCodeError",
                                    "msg" => $translations["E01029"][$language]
                                );
                            }
                        }

                        if(!$billingDialingArea || !$billingPhoneNumber){
                                $errorFieldArr[] = array(
                                    "id" => "billingPhoneNumberError",
                                    "msg" => $translations["E00305"][$language],
                                );
                        }else{
                            if(!preg_match("/^[0-9]*$/",$billingPhoneNumber,$matches)){
                                $errorFieldArr[] = array(
                                    "id" => "billingPhoneNumberError",
                                    "msg" => $translations["E00858"][$language]
                                );
                            }
                        }

                        if(!$billingEmailAddress){
                            $errorFieldArr[] = array(
                                "id" => "billingEmailAddressError",
                                "msg" => $translations["E00318"][$language],
                            );
                        }else{
                            if($billingEmailAddress){
                                if(!filter_var($billingEmailAddress,FILTER_VALIDATE_EMAIL)){
                                    $errorFieldArr[] = array(
                                        "id" => "billingEmailAddressError",
                                        "msg" => $translations["E00319"][$language],
                                    );
                                }
                            }
                        }
                    }*/
                }

                foreach($packageAry as &$packageRow){
                    $packageID = $packageRow["packageID"];
                    $isStarterKit = $packageData[$packageID]["isStarterKit"];
                }

                if($isStarterKit){
                    $db->where('client_id',$clientID);
                    $placementExist = $db->getOne('tree_placement');

                    if(!$placementExist){
                        $db->where("id", $clientID);
                        $placementID = $db->getValue("client", "sponsor_id");

                        if($placementID){
                            if (empty($placementPosition)) {
                                $errorFieldArr[] = array(
                                    "id"  => "placementPositionError",
                                    "msg" => $translations["E00325"][$language]
                                );
                            } else if (!in_array($placementPosition,[1,2])) {
                                $errorFieldArr[] = array(
                                    "id"  => "placementPositionError",
                                    "msg" => $translations["E00325"][$language]
                                );
                            }

                            // valid octopus username
                            $db->where("client_id", $placementID);
                            $db->where("trace_key", "%".$placementID."%", "LIKE");
                            $isUnderPlacementID = $db->getOne("tree_placement", "id");
                            if(!$isUnderPlacementID) {
                                $errorFieldArr[] = array(
                                    "id"  => "placementUsernameError",
                                    "msg" => $translations["E00579"][$language]
                                );
                            }

                            // if placement 2 leg full, loop until downline leg empty
                            $placementDownlineID = $placementID;

                            do {
                                if($placementValid) $placementDownlineID = $placementValid;
                                $db->where("upline_id",$placementDownlineID);
                                $db->where("client_position",$placementPosition);
                                $placementValid = $db->getOne("tree_placement","client_id")['client_id'];
                            } while (!empty($placementValid));

                            $db->where('id',$placementDownlineID);
                            $placementDownline = $db->getOne('client','id, username');
                        }else{
                            $errorFieldArr[] = array(
                                "id"  => "placementUsernameError",
                                "msg" => $translations["E00579"][$language]
                            );
                        }
                    }
                }

                $billingAddressID = $billingAddressData[0]["addressID"];
            }

            if($errorFieldArr){
                $data["field"] = $errorFieldArr;
                return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00130"][$language], "data" => $data);
            }

            foreach($packageAry as &$packageRow){
                $packageID = $packageRow["packageID"];
                $packageQty = $packageRow["quantity"];
                $packageIDAry[] = $packageRow["packageID"];

                $packageWeight = $packageData[$packageID]["weight"];
                $bonusValue = $packageData[$packageID]["bonusValue"];
                $packageProductAry = $packageData[$packageID]["product"];
                $price = $packageData[$packageID]["promoPrice"] > 0 ? $packageData[$packageID]["promoPrice"] : $packageData[$packageID]["price"];
                $mPrice = $packageData[$packageID]["mPrice"];
                $msPrice = $packageData[$packageID]["msPrice"];

                if($clientDiscountPercentage){
                    if(($mPrice > 0 && $clientDiscountPercentage == 25)){
                        $packagePrice = Setting::setDecimal(($mPrice * $packageQty));
                        $pricePerUnit = $mPrice;
                    }else if(($msPrice > 0 && $clientDiscountPercentage == 30)){
                        $packagePrice = Setting::setDecimal(($msPrice * $packageQty));
                        $pricePerUnit = $msPrice;
                    }else{
                        $packagePrice = Setting::setDecimal(($price - (($price * $clientDiscountPercentage) / 100)) * $packageQty);
                        $pricePerUnit = Setting::setDecimal((($price * $clientDiscountPercentage) / 100));
                    }
                }else{
                    $packagePrice = Setting::setDecimal(($price * $packageQty));
                    $pricePerUnit = $price;
                }
                $packageRow['pricePerUnit'] = $pricePerUnit;
		        $packageRow['packagePrice'] = $packagePrice;

                $subTotal = Setting::setDecimal(($subTotal + $packagePrice));

                $packageBV = Setting::setDecimal(($bonusValue * $packageQty));
                $totalBV = Setting::setDecimal(($totalBV + $packageBV));

                if(($packageWeight > 0)){
                    $subWeight = Setting::setDecimal(($packageQty * $packageWeight));
                    $totalWeight = Setting::setDecimal(($totalWeight + $subWeight));
                }else{
                    foreach($packageProductAry as $packageProductRow){
                        $productQty = $packageProductRow["quantity"];
                        $productWeight = $packageProductRow["weight"];
                        $subWeight = Setting::setDecimal(($packageQty * $productQty * $productWeight));
                        $totalWeight = Setting::setDecimal(($totalWeight + $subWeight));
                    }
                }
            }

            $totalPrice = Setting::setDecimal(($subTotal));

            $db->where("type","SST");
            $db->orderBy("created_at","DESC");
            $taxPercentage = $db->getValue("inv_tax_charges","rate");
            if(!$taxPercentage) $taxPercentage = 0;

            $taxes = Setting::setDecimal(($totalPrice * ($taxPercentage / 100)));
            $totalPrice = Setting::setDecimal(($totalPrice + $taxes));

            if($step >= 3){
                if(!in_array($deliveryOption,array("pickup"))){
                    $db->where('token', $sessionData);
                    $sessionID = $db->getValue('client_session', 'id');

                    if($sessionID){
                        $db->where('session_id', $sessionID);
                        $db->where('client_id', $clientID);
                        $db->where('type', 'shippingFee');
                        $shippingFeeData = $db->getValue('session_data', 'data');
                    }

                    $addShippingFee = 0;
                    $updateShippingFee = 0;

                    if($shippingFeeData){
                        $shippingFeeData = json_decode($shippingFeeData, true);
                        $courierListRes = $shippingFeeData;

                        if($step == 3){
                            if($countryID && $stateID){
                                // $shippingFee = Inventory::calculateDeliveryFee($countryID,$stateID,$totalWeight);
                                $dfParams = array(
                                    'destID' => $postalCodeRes['destination_id'],
                                    'thru'   => $postalCodeRes['tariff_code'],
                                    'weight' => $totalWeight,
                                );
                                $shippingFeeRes = self::get3rdPartyDeliveryFees($dfParams);
                                if($shippingFeeRes['status'] != 'ok'){
                                    return $shippingFeeRes;
                                }

                                $courierListRes = $shippingFeeRes['data'];

                                $updateShippingFee = 1;
                            }
                        }

                    }else{
                        if($countryID && $stateID){
                            // $shippingFee = Inventory::calculateDeliveryFee($countryID,$stateID,$totalWeight);
                            $dfParams = array(
                                'destID' => $postalCodeRes['destination_id'],
                                'thru'   => $postalCodeRes['tariff_code'],
                                'weight' => $totalWeight,
                            );
                            $shippingFeeRes = self::get3rdPartyDeliveryFees($dfParams);
                            if($shippingFeeRes['status'] != 'ok'){
                                return $shippingFeeRes;
                            }

                            $courierListRes = $shippingFeeRes['data'];

                            $addShippingFee = 1;
                        }
                    }

                    foreach ($courierListRes as $companyName => $serviceList) {
                        $acceptedCompany[$companyName] = $companyName;

                        foreach ($serviceList as $serviceRow) {
                            $acceptedServiceAry[$companyName][] = $serviceRow['courier'];
                            $deliveryFeeRes[$companyName][$serviceRow['courier']] = $serviceRow['price'];

                            if($serviceRow['serviceID']){
                                $serviceIDAry[$companyName][$serviceRow['courier']] = $serviceRow['serviceID'];
                            }
                        }
                    }

                    if($addShippingFee){
                        $insertData = array(
                            'session_id'    => $sessionID,
                            'client_id'     => $clientID,
                            'type'          => 'shippingFee',
                            'data'          => json_encode($courierListRes),
                            'created_at'    => $dateTime
                        );
                        $db->insert('session_data', $insertData);
                    }

                    if($updateShippingFee){
                        unset($shippingFeeData);
                        unset($updateData);

                        $updateData = array(
                            "data" => json_encode($courierListRes),
                            "created_at" => $dateTime
                        );
                        $db->where("session_id",$sessionID);
                        $db->where("client_id",$clientID);
                        $db->where("type","shippingFee");
                        $db->update("session_data",$updateData);
                    }
                }

                unset($deliveryAddressDataAry);
                unset($billingAddressDataAry);

                if(($deliveryAddressID && !$isAddDeliveryAddress)){
                    $deliveryAddressDataAry[] = $deliveryAddressData[$deliveryAddressID];
                    $deliveryAddressData = $deliveryAddressDataAry;
                }else{
                    unset($deliveryAddressData);
                    unset($tempDelivery);

                    $tempDelivery["fullName"] = $fullName;
                    $tempDelivery["countryID"] = $countryID;
                    $tempDelivery["countryName"] = $countryRes["name"];
                    $tempDelivery["countryDisplay"] = $translations[$countryRes["translation_code"]][$language];
                    $tempDelivery["address"] = $address;
                    $tempDelivery["districtID"] = $district;
                    $tempDelivery["district"] = $districtRes["name"];
                    $tempDelivery["subDistrictID"] = $subDistrict;
                    $tempDelivery["subDistrict"] = $subDistrictRes["name"];
                    $tempDelivery["cityID"] = $city;
                    $tempDelivery["city"] = $cityRes["name"];
                    $tempDelivery["stateID"] = $stateID;
                    $tempDelivery["stateName"] = $stateRes["name"];
                    $tempDelivery["stateDisplay"] = $translations[$stateRes["translation_code"]][$language];
                    $tempDelivery["postalCodeID"] = $postalCode;
                    $tempDelivery["postalCode"] = $postalCodeRes["name"];
                    $tempDelivery["dialingArea"] = $dialingArea;
                    $tempDelivery["phoneNumber"] = $phoneNumber;
                    $tempDelivery["emailAddress"] = $emailAddress;

                    $deliveryAddressDataAry[] = $tempDelivery;
                    $deliveryAddressData = $deliveryAddressDataAry;
                }
                if($isBillingAddress){
                    unset($billingAddressData);
                    $billingAddressData = $deliveryAddressData;
                }/*else{
                    unset($billingAddressData);
                    unset($tempBilling);

                    $tempBilling["fullName"] = $fullName;
                    $tempBilling["countryID"] = $billingCountryID;
                    $tempBilling["countryName"] = $billingCountryRes["name"];
                    $tempBilling["countryDisplay"] = $translations[$billingCountryRes["translation_code"]][$language];
                    $tempBilling["address"] = $billingAddress;
                    $tempBilling["districtID"] = $billingDistrict;
                    $tempBilling["district"] = $billingDistrictRes["name"];
                    $tempBilling["subDistrictID"] = $billingSubDistrict;
                    $tempBilling["subDistrict"] = $billingSubDistrictRes["name"];
                    $tempBilling["cityID"] = $billingCity;
                    $tempBilling["city"] = $billingCityRes["name"];
                    $tempBilling["stateID"] = $billingStateID;
                    $tempBilling["stateName"] = $billingStateRes["name"];
                    $tempBilling["stateDisplay"] = $translations[$billingStateRes["translation_code"]][$language];
                    $tempBilling["postalCodeID"] = $billingPostalCode;
                    $tempBilling["postalCode"] = $billingPostalCodeRes["name"];
                    $tempBilling["dialingArea"] = $billingDialingArea;
                    $tempBilling["phoneNumber"] = $billingPhoneNumber;
                    $tempBilling["emailAddress"] = $billingEmailAddress;

                    $billingAddressDataAry[] = $tempBilling;
                    $billingAddressData = $billingAddressDataAry;
                }*/

                $db->where('name', 'nicepaySetting');
                $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
                $nicepayBankAry = json_decode($nicepaySetting['description'], true);
                foreach ($nicepayBankAry as $npBankCode => $npBankName) {
                    $tempBank['code'] = $npBankCode;
                    $tempBank['name'] = $npBankName;
                    $data["nicepayBankAry"][] = $tempBank;
                }
            }

            if($step >= 4){
                $acceptedService = $acceptedServiceAry[$courierCompany];

                if(!in_array($deliveryOption,array("pickup"))){
                    if(!$courierCompany || !in_array($courierCompany, $acceptedCompany)){
                        $errorFieldArr[] = array(
                                                    "id" => "courierCompanyError",
                                                    "msg" => $translations["E01125"][$language],
                                                );
                    }

                    if(!$courierService || !in_array($courierService, $acceptedService)){
                        $errorFieldArr[] = array(
                                                    "id" => "courierServiceError",
                                                    "msg" => $translations["E01125"][$language],
                                                );
                    }

                    $shippingFee = $deliveryFeeRes[$courierCompany][$courierService];
                    $serviceID = $serviceIDAry[$courierCompany][$courierService] ? : 0;

                    if(!$shippingFee){
                        $shippingFee = 0;
                    }

                    if($params['insuranceOption'] == 1){
                        $insuranceTax = 0.2;

                        $insuranceTaxes = Setting::setDecimal((($totalPrice * ($insuranceTax / 100))) + 5000);

                        $totalPrice = Setting::setDecimal(($totalPrice + $shippingFee + $insuranceTaxes));
                    }
                    else{
                        $totalPrice = Setting::setDecimal(($totalPrice + $shippingFee));
                    }
                }

                if($makePaymentMethod == 'nicepay'){
                     if(!$nicepayBankCode){
                        $errorFieldArr[] = array(
                            "id" => "bankCodeError",
                            "msg" => $translations["E01144"][$language],
                        );
                    } else {
                        $db->where('name', 'nicepaySetting');
                        $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
                        $nicepayBankAry = json_decode($nicepaySetting['description'], true);
                        if(!in_array($nicepayBankCode, array_keys($nicepayBankAry))){
                            $errorFieldArr[] = array(
                                "id" => "bankCodeError",
                                "msg" => $translations["E01145"][$language],
                            );
                        }
                    }
                }

                if($errorFieldArr){
                    $data["field"] = $errorFieldArr;
                    return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00130"][$language], "data" => $data);
                }

                $paymentType = "Purchase Package";
                $paymentSetting = Cash::getPaymentDetail($clientID,$paymentType,$totalPrice,$packageAry);
                $paymentMethod = $paymentSetting["data"]["paymentData"];

                if(($type != "free") && (count($paymentMethod) == 1)){
                    foreach($paymentMethod as $creditType => $paymentValue){
                        $spendCredit[$creditType]["amount"] = $totalPrice;
                    }

                    $data["spendCredit"] = $spendCredit;
                }
            }

            if($step >= 5){
                if($voucherCode){

                    if($params["isNicepayCallback"]==true && $params["tx_id"]!="" ) {

                        $db->where("tx_id", $params["tx_id"] );
                        $discountAmount = $db->getValue("mlm_pending_payment", "discount_amount");

                        $db->where("code", $voucherCode);
                        $voucherType = $db->getValue("inv_voucher", "type");

                    } else {

                        unset($voucherParams);
                        $voucherParams = array(
                            "voucherCode" => $voucherCode,
                            "packageIDAry" => $packageIDAry,
                            "courierService" => $courierService
                        );
                        $validVoucherReturn = Self::checkValidVoucher($voucherParams);

                        if(strtolower($validVoucherReturn["status"]) != "ok"){
                            return $validVoucherReturn;
                        }
                        $voucherType = $validVoucherReturn["data"]["voucherType"];
                        $discountAmount = $validVoucherReturn["data"]["discountAmount"];

                    }

                    switch($voucherType){
                        case "delivery":
                            $totalPrice = Setting::setDecimal(($totalPrice - $discountAmount));
                            break;

                        default:
                            break;
                    }

                    $paymentSetting = Cash::getPaymentDetail($clientID,$paymentType,$totalPrice,$packageAry);
                    $paymentMethod = $paymentSetting["data"]["paymentData"];

                    unset($spendCredit);

                    if(($type != "free") && (count($paymentMethod) == 1)){
                        foreach($paymentMethod as $creditType => $paymentValue){
                            $spendCredit[$creditType]["amount"] = $totalPrice;
                        }

                        $data["spendCredit"] = $spendCredit;
                    }
                }

                if(!in_array($makePaymentMethod, array('nicepay', 'creditCard'))){
                    $validateCreditReturn = Cash::paymentVerification($clientID,$paymentType,$spendCredit,$packageAry,$totalPrice);
                    if(strtolower($validateCreditReturn["status"]) != "ok"){
                        return $validateCreditReturn;
                    }
                } elseif($makePaymentMethod == 'creditCard' && $submitCreditCard == true){
                    // check Credit card detail
                    if(empty($ccHolderName)) {
                        $errorFieldArr[] = array(
                            'id'    => 'ccHolderNameError',
                            'msg'   => 'Credit Card Holder Name Not Found'
                        );
                    } else {
                        if (strlen($ccHolderName) > 50) {
                            $errorFieldArr[] = array(
                                'id'    => 'ccHolderNameError',
                                'msg'   => 'Invalid Credit Card Holder Name'
                            );
                        }
                    }

                    if (empty($ccNo)) {
                        $errorFieldArr[] = array(
                            'id' => 'ccNoError',
                            'msg' => 'Credit Card No Not Found'
                        );
                    } else {
                        if (!preg_match('/^[0-9]*$/', $ccNo, $matches) || strlen($ccNo) > 20) {
                            $errorFieldArr[] = array(
                                'id' => 'ccNoError',
                                'msg' => 'Invalid Credit Card No'
                            );
                        }
                    }

                    if (empty($ccExpiry)) {
                        $errorFieldArr[] = array(
                            'id' => 'ccExpiryError',
                            'msg' => 'Credit Card Expiry Not Found'
                        );
                    } else {
                        if (!preg_match('/^[0-9]*$/', $ccExpiry, $matches) || strlen($ccExpiry) > 4) {
                            $errorFieldArr[] = array(
                                'id' => 'ccNoError',
                                'msg' => 'Invalid Credit Card Expiry'
                            );
                        }
                    }

                    if (empty($ccCvv)) {
                        $errorFieldArr[] = array(
                            'id' => 'ccExpiryError',
                            'msg' => 'Credit Card Expiry Not Found'
                        );
                    } else {
                        if (!preg_match('/^[0-9]*$/', $ccCvv, $matches) || strlen($ccCvv) > 4) {
                            $errorFieldArr[] = array(
                                'id' => 'ccCvvError',
                                'msg' => 'Invalid Credit Card CVV'
                            );
                        }
                    }

                    if($errorFieldArr){
                        $data["field"] = $errorFieldArr;
                        return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00130"][$language], "data" => $data);
                    }
                }

                $invoiceSpendData = $validateCreditReturn["data"]["invoiceSpendData"];
            }

            $data["packageAry"] = $packageAry;
            $data["packageData"] = $packageData;
            $data["isStarterKit"] = $isStarterKit ? : 0;
            if($isStarterKit){
                $data['placementID'] = $placementDownlineID;
                $data['placementPosition'] = $placementPosition;
                $data['placementDownline'] = $placementDownline;
            }
            $data["deliveryOption"] = $deliveryOption;
            $data["paymentCredit"] = $paymentMethod;
            $data["subTotal"] = Setting::setDecimal($subTotal);
            $data["shippingFee"] = Setting::setDecimal($shippingFee);
            $data["serviceID"] =  $serviceID;
            $data["courierCompany"] = $acceptedCompany;
            $data["courierList"] = $courierListRes;
            $data["taxPercentage"] = Setting::setDecimal($taxPercentage);
            $data["taxes"] = Setting::setDecimal($taxes);
            $data["insuranceTaxes"] = Setting::setDecimal($insuranceTaxes);
            $data["totalPrice"] = Setting::setDecimal($totalPrice);
            $data["totalBV"] = Setting::setDecimal($totalBV);
            $data["totalWeight"] = Setting::setDecimal($totalWeight);
            $data["payCurrenyCode"] = $payCurrenyCode;
            $data["cvRate"] = Setting::setDecimal($cvRate);
            $data["paymentType"] = $paymentType;
            $data["invoiceSpendData"] = $invoiceSpendData;
            $data["isAddDeliveryAddress"] = $isAddDeliveryAddress;
            $data["deliveryAddressID"] = $deliveryAddressID;
            $data["billingAddressID"] = $billingAddressID;
            $data["deliveryAddressData"] = $deliveryAddressData;
            $data["billingAddressData"] = $billingAddressData;
            $data["pickupAddressData"] = $pickupAddressData;
            $data["purchaseSpecialNote"] = $purchaseSpecialNote;
            $data["purchaseRemark"] = $purchaseRemark;
            $data["discountAmount"] = $discountAmount;
            $data["nicepayBankCode"] = $nicepayBankCode;
            $data["clientMemberID"] = $clientMemberID;
            $data["clientEmail"] = $clientEmail;
            $data["sponsorMemberID"] = $sponsorMemberID;
            $data["sponsorName"] = $sponsorName;
            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $data);
        }

        public function purchasePackageConfirmation($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;
            $sessionData = $db->sessionID;

            $packageAry = $params["packageAry"];
            $deliveryOption = trim($params["deliveryOption"]);
            $courierCompany = $params['courierCompany'];
            $courierService = $params['courierService'];
            // $pickupID = trim($params["pickupID"]);

            $addressID = trim($params["addressID"]);
            $fullName = trim($params["fullName"]);
            $countryID = trim($params["countryID"]);
            $address = trim($params["address"]);
            $district = trim($params["district"]);
            $subDistrict = trim($params["subDistrict"]);
            $city = trim($params["city"]);
            $stateID = trim($params["stateID"]);
            $postalCode = trim($params["postalCode"]);
            $dialingArea = trim($params["dialingArea"]);
            $phoneNumber = trim($params["phoneNumber"]);
            $emailAddress = trim($params["emailAddress"]);
            $isBillingAddress = trim($params["isBillingAddress"]);
            $makePaymentMethod = trim($params["makePaymentMethod"]);

            //Placement Option
            $placementPosition    = trim($params["placementPosition"]);

            /*$billingFullName = trim($params["billingFullName"]);
            $billingCountryID = trim($params["billingCountryID"]);
            $billingAddress = trim($params["billingAddress"]);
            $billingDistrict = trim($params["billingDistrict"]);
            $billingSubDistrict = trim($params["billingSubDistrict"]);
            $billingCity = trim($params["billingCity"]);
            $billingStateID = trim($params["billingStateID"]);
            $billingPostalCode = trim($params["billingPostalCode"]);
            $billingDialingArea = trim($params["billingDialingArea"]);
            $billingPhoneNumber = trim($params["billingPhoneNumber"]);
            $billingEmailAddress = trim($params["billingEmailAddress"]);*/

            $voucherCode = trim($params["voucherCode"]); // Optional
            $spendCredit = $params["spendCredit"];
            $purchaseSpecialNote = trim($params["purchaseSpecialNote"]); // Optional
            $purchaseRemark = trim($params["purchaseRemark"]); // Optional
            $params["step"] = 5;


            $verificationReturn = self::purchasePackageVerification($params);

            if($verificationReturn["status"] != "ok"){
                return $verificationReturn;
            }

            $packageAry = $verificationReturn["data"]["packageAry"];
            $packageData = $verificationReturn["data"]["packageData"];
            $deliveryOption = $verificationReturn["data"]["deliveryOption"];
            $paymentCredit = $verificationReturn["data"]["paymentCredit"];
            $subTotal = $verificationReturn["data"]["subTotal"];
            $insuranceTaxes = $verificationReturn["data"]["insuranceTaxes"];
            $shippingFee = $verificationReturn["data"]["shippingFee"];
            $taxPercentage = $verificationReturn["data"]["taxPercentage"];
            $taxes = $verificationReturn["data"]["taxes"];
            $totalPrice = $verificationReturn["data"]["totalPrice"];
            $totalBV = $verificationReturn["data"]["totalBV"];
            $totalWeight = $verificationReturn["data"]["totalWeight"];
            $payCurrenyCode = $verificationReturn["data"]["payCurrenyCode"];
            $cvRate = $verificationReturn["data"]["cvRate"];
            $paymentType = $verificationReturn["data"]["paymentType"];
            $invoiceSpendData = $verificationReturn["data"]["invoiceSpendData"];
            $isAddDeliveryAddress = $verificationReturn["data"]["isAddDeliveryAddress"];
            $deliveryAddressID = $verificationReturn["data"]["deliveryAddressID"];
            $billingAddressID = $verificationReturn["data"]["billingAddressID"];
            $discountAmount = $verificationReturn["data"]["discountAmount"];
            $serviceID = $verificationReturn["data"]["serviceID"];
            $isStarterKit = $verificationReturn["data"]["isStarterKit"];

            if($isStarterKit){
                $placementID = $verificationReturn['data']['placementID'];
                $placementPosition = $verificationReturn['data']['placementPosition'];
            }
            // $billingAddressData = $verificationReturn["data"]["billingAddressData"];

            $unitPrice = General::getLatestUnitPrice();

            //Get Package ID Array
            foreach($packageAry as $packageRow) {
                $packageIDRes[$packageRow['packageID']] = $packageRow['packageID'];
            }
            if(!$packageIDRes){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00955'][$language], 'data'=>"");
            }

            $db->startTransaction();

            $db->where('id',$packageIDRes,"IN");
            $db->where('status',"Active");
            $packageRes = $db->setQueryOption("FOR UPDATE")->get("mlm_product", null,"total_sold, total_balance, total_holding, is_unlimited, status");
            if(COUNT($packageRes) != COUNT($packageIDRes)){
                $db->rollback();
                $db->commit();
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01088'][$language] /* Insufficient Package. */, 'data'=>"");
            }

            if(!in_array($makePaymentMethod, array('nicepay', 'creditCard'))){
                foreach ($packageRes as $packageBal) {
                    if($packageBal['is_unlimited']) continue;

                    if($packageBal['total_balance'] <= ($packageBal['total_sold'] + $packageBal['total_holding'])){
                        $db->rollback();
                        $db->commit();
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01088'][$language] /* Insufficient Package. */, 'data'=>"");
                    }
                }
            } else{
                if($params['isNicepayCallback'] != true){
                    $db->rollback();
                    $db->commit();
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01088'][$language] /* Insufficient Package. */, 'data'=>"");
                }
            }

            try{
                $batchID = $params['batch_id'] ?: $db->getNewID();
                $belongID = $params['belong_id'] ?: $db->getNewID();


                if(!in_array($makePaymentMethod, array('nicepay', 'creditCard'))){
                    $paymentResult = Cash::paymentConfirmation($clientID,$paymentType,$invoiceSpendData,$packageAry,$portfolioID,$totalPrice,$dateTime,$batchID,$belongID);

                    if(!$paymentResult){
                        $db->rollback();
                        $db->commit();
                        return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00948"][$language], "data" => "");
                    }
                }

                if($isAddDeliveryAddress){
                    unset($deliveryParams);

                    $deliveryParams = array(
                        "isDefault" => 0,
                        "addressType" => "delivery",
                        "fullname" => $fullName,
                        "dialingArea" => $dialingArea,
                        "phone" => $phoneNumber,
                        "email" => $emailAddress,
                        "address" => $address,
                        "districtID" => $district,
                        "subDistrictID" => $subDistrict,
                        "cityID" => $city,
                        "postalCodeID" => $postalCode,
                        "stateID" => $stateID,
                        "countryID" => $countryID,
                        "disabled" => 0,
                    );

                    $deliveryAddressReturn = Inventory::manageAddress($deliveryParams,"add");

                    if($deliveryAddressReturn["status"] != "ok"){
                        $db->rollback();
                        $db->commit();
                        return $deliveryAddressReturn;
                    }

                    $db->where("address_type","delivery");
                    $db->orderBy("id","DESC");
                    $deliveryAddressID = $db->getValue("address","id");

                    /*if(!$isBillingAddress){
                        unset($billingParams);

                        $billingParams = array(
                            "id" => $billingAddressID,
                            "isDefault" => 0,
                            "addressType" => "billing",
                            "fullname" => $billingFullName,
                            "dialingArea" => $billingDialingArea,
                            "phone" => $billingPhoneNumber,
                            "email" => $billingEmailAddress,
                            "address" => $billingAddress,
                            "districtID" => $billingDistrict,
                            "subDistrictID" => $billingSubDistrict,
                            "cityID" => $billingCity,
                            "postalCodeID" => $billingPostalCode,
                            "stateID" => $billingStateID,
                            "countryID" => $billingCountryID,
                            "disabled" => 0,
                        );
                    }else{
                        $deliveryParams["id"] = $billingAddressID;
                        $deliveryParams["addressType"] = "billing";
                        $billingParams = $deliveryParams;
                    }

                    $billingAddressReturn = Inventory::manageAddress($billingParams,"edit");

                    if($billingAddressReturn["status"] != "ok"){
                        $db->rollback();
                        $db->commit();
                        return $billingAddressReturn;
                    }

                    $db->where("address_type","billing");
                    $db->orderBy("id","DESC");
                    $billingAddressID = $db->getValue("address","id");*/
                }

                $totalPackage = 0;
                $totalBonusValue = 0;
                $clientRank = Bonus::getClientRank("Bonus Tier",array($clientID),"","discount");
                $clientDiscountPercentage = $clientRank[$clientID]["percentage"];

                foreach($packageAry as $packageRow){
                    $packageID = $packageRow["packageID"];
                    $packageQty = $packageRow["quantity"];

                    $totalPackage += $packageQty;

                    $bonusValue = $packageData[$packageID]["bonusValue"];
                    $price = ($packageData[$packageID]["promoPrice"] > 0) ? $packageData[$packageID]["promoPrice"] : $packageData[$packageID]["price"];
                    $mPrice = $packageData[$packageID]["mPrice"];
                    $msPrice = $packageData[$packageID]["msPrice"];

                    if($clientDiscountPercentage){
                        if(($mPrice > 0 && $clientDiscountPercentage == 25)){
                            $packagePrice = Setting::setDecimal(($mPrice * $packageQty));
                        }else if(($msPrice > 0 && $clientDiscountPercentage == 30)){
                            $packagePrice = Setting::setDecimal(($msPrice * $packageQty));
                        }else{
                            $packagePrice = Setting::setDecimal(($price - (($price * $clientDiscountPercentage) / 100)) * $packageQty);
                        }
                    }else{
                        $packagePrice = Setting::setDecimal(($price * $packageQty));
                    }

                    $totalBonusValue += (($packageData[$packageID]["bonusValue"]) * $packageQty);

                    $portfolioID = $db->getNewID();

                    unset($insertData);

                    $insertData = array(
                        "portfolioID" => $portfolioID,
                        "clientID" => $clientID,
                        "productID" => $packageID,
                        "price" => $packagePrice,
                        "bonusValue" => ($bonusValue * $packageQty),
                        "type" => $paymentType,
                        "belongID" => $belongID,
                        "batchID" => $batchID,
                        "status" => "Active",
                        "purchaseAt" => $dateTime,
                        "unitPrice" => $unitPrice,
                    );
                    $insertPortfolioRes = Subscribe::insertClientPortfolio($insertData);

                    if(!$insertPortfolioRes){
                        $db->rollback();
                        $db->commit();
                        return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00948"][$language], "data" => "");
                    }

                    unset($insertData);

                    $insertData = array(
                        "clientID" => $clientID,
                        "mainID" => $clientID,
                        "type" => $paymentType,
                        "productID" => $packageID,
                        "belongID" => $belongID,
                        "batchID" => $batchID,
                        "bonusValue" => ($bonusValue * $packageQty),
                        "dateTime" => $dateTime,
                    );

                    $insertBonusValue = Bonus::insertBonusValue($insertData);

                    if(!$insertBonusValue){
                        $db->rollback();
                        $db->commit();
                        return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00948"][$language], "data" => "");
                    }

                    $updateData = array(
                        'total_sold' => $db->inc($packageQty)
                    );
                    $db->where('id', $packageID);
                    $db->update('mlm_product', $updateData);

                    $packageIDAry[] = $packageID;
                }

                $db->where('id', $packageIDAry, 'IN');
                $checkQuantityRes = $db->get('mlm_product', null, 'id, (total_balance - total_sold) AS quantity');

                foreach ($checkQuantityRes as $checkQuantityRow) {
                    if($checkQuantityRow['quantity'] <= 0){
                        $soldOutPackage[$checkQuantityRow['id']] = $checkQuantityRow['id'];
                    }
                }

                // Update system setting for process get product
                if($soldOutPackage){
                    $db->where('name', 'processGetProduct');
                    $db->update('system_settings', array('value' => 0));
                }

                $isActivate = Custom::maintainActiveMember($clientID, $totalBonusValue, $dateTime);

                $getInvoiceNoFormat = 'INV'.DATE('y').'/FIZ/'.DATE('m').'/';
                $getPONumberFormat = DATE('y').'/SO/FIZ/'.DATE('m').'/';

                $db->where('po_number', $getPONumberFormat.'%', 'LIKE');
                $db->where('reference_number', $getInvoiceNoFormat.'%', 'LIKE');
                $maxID = $db->getValue('inv_order', 'MAX(id)');

                $db->where('id', $maxID);
                $getLatestNumber = $db->getValue('inv_order', "reference_number");

                if($getLatestNumber){
                    $getLatestNo = explode("/", $getLatestNumber);
                    $referenceNo = str_pad(end($getLatestNo)+1, 4, '0', STR_PAD_LEFT);
                }else{
                    $referenceNo = str_pad(1, 4, '0', STR_PAD_LEFT);
                }
                // $referenceNo = General::generateAllReferenceNo("inv_order","reference_number");

                unset($insertData);
                switch ($makePaymentMethod) {
                    case 'nicepay':
                        $invPaymentMethod = 'VirtualAccount';
                        break;

                    case 'creditCard':
                        $invPaymentMethod = 'CreditCard';
                        break;

                    default:
                        $invPaymentMethod = 'Credit';
                        break;
                }

                $insertData = array(
                    "client_id" => $clientID,
                    "reference_number" => $getInvoiceNoFormat.$referenceNo,
                    "po_number" => $getPONumberFormat.$referenceNo,
                    "delivery_option" => $deliveryOption,
                    "billing_add_id" => ($isBillingAddress == 1) ? $deliveryAddressID : $billingAddressID,
                    "delivery_add_id" => (!in_array($deliveryOption,array("pickup"))) ? $deliveryAddressID : '',
                    "courier_company" => (!in_array($deliveryOption,array("pickup"))) ? $courierCompany : '-',
                    "courier_service" => (!in_array($deliveryOption,array("pickup"))) ? $courierService : '-',
                    "service_id" => ($serviceID > 0) ? $serviceID : '0',
                    "pay_currency_code" => $payCurrenyCode,
                    "total_package" => $totalPackage,
                    "total_weight" => $totalWeight,
                    "total_pv" => $totalBonusValue,
                    "total_price" => $subTotal,
                    "delivery_fee" => $shippingFee,
                    "tax_charges" => $taxes,
                    "insurance_tax" => $insuranceTaxes,
                    "payment_type" => $invPaymentMethod,
                    "paid_amount" => $totalPrice,
                    "status" => "Paid",
                    "batch_id" => $batchID,
                    "special_note" => $purchaseSpecialNote,
                    "remark" => $purchaseRemark,
                    "created_at" => $dateTime,
                );
                $orderID = $db->insert("inv_order",$insertData);

                foreach($packageAry as $packageRow){
                    $packageID = $packageRow["packageID"];
                    $packageQty = $packageRow["quantity"];

                    $price = ($packageData[$packageID]["promoPrice"] > 0) ? $packageData[$packageID]["promoPrice"] : $packageData[$packageID]["price"];
                    $mPrice = $packageData[$packageID]["mPrice"];
                    $msPrice = $packageData[$packageID]["msPrice"];

                    if($clientDiscountPercentage){
                        if(($mPrice > 0 && $clientDiscountPercentage == 25)){
                            $packagePrice = Setting::setDecimal(($mPrice));
                        }else if(($msPrice > 0 && $clientDiscountPercentage == 30)){
                            $packagePrice = Setting::setDecimal(($msPrice));
                        }else{
                            $packagePrice = Setting::setDecimal(($price - (($price * $clientDiscountPercentage) / 100)));
                        }
                    }else{
                        $packagePrice = Setting::setDecimal(($price));
                    }

                    $packageProductAry = $packageData[$packageID]["product"];
                    $packageWeight = $packageData[$packageID]["weight"];

                    foreach($packageProductAry as $packageProductRow){
                        unset($productInvAry);

                        $productID = $packageProductRow["productID"];
                        $productQty = $packageProductRow["quantity"];
                        $productWeight = $packageProductRow["weight"];

                        $totalWeight = Setting::setDecimal(($packageQty * $productQty * $productWeight));

                        unset($insertData);

                        $insertData = array(
                            "inv_order_id" => $orderID,
                            "mlm_product_id" => $packageID,
                            "inv_product_id" => $productID,
                            "price" => $packagePrice,
                            "pv_price" => $packageData[$packageID]["bonusValue"],
                            "weight" => ($packageWeight > 0) ? $packageWeight : $productWeight,
                            "quantity" => $packageQty,
                            "stock_quantity" => ($packageQty * $productQty),
                            "left_stock_quantity" => ($packageQty * $productQty),
                        );
                        $db->insert("inv_order_detail",$insertData);

                        $productInvAry[$productID] = $productQty;

                        $insertProductTrxn = Inventory::insertInvProductTransaction($productInvAry,$clientID,"Buy Product",$packageQty,$data,$batchID);

                        if(!$insertProductTrxn){
                            $db->rollback();
                            $db->commit();
                            return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00948"][$language], "data" => "");
                        }
                    }
                }

                foreach($invoiceSpendData as $creditType => $creditAmount){
                    if($creditAmount["amount"] <= 0){
                        continue;
                    }

                    unset($insertData);

                    $insertData = array(
                        "inv_order_id" => $orderID,
                        "credit_type" => $creditType,
                        "amount" => $creditAmount["amount"],
                        "created_at" => $dateTime,
                    );
                    $db->insert("inv_order_payment",$insertData);
                }

                if($voucherCode && $discountAmount){
                    unset($voucherParams);
                    $voucherParams = array(
                        "invOrderID" => $orderID,
                        "voucherCode" => $voucherCode,
                        "realDiscountAmount" => $discountAmount,
                    );
                    $insertOrderVoucher = Self::insertOrderVoucher($voucherParams);

                    if(!$insertOrderVoucher){
                        $db->rollback();
                        $db->commit();
                        return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00948"][$language], "data" => "");
                    }
                }

                $db->where('client_id', $clientID);
                $emailVerified = $db->getValue('client_detail', 'email_verified');
                if($emailVerified == 1){
                    $db->where('id', $clientID);
                    $recipientEmail = $db->getValue('client', 'email');
                }
                $sendECatelog = self::buyPackageSendNotice($packageIDAry, $recipientEmail);

                if(in_array($makePaymentMethod, array('nicepay', 'creditCard'))){
                    foreach ($packageAry as $packageInfo) {
                        $updateData = array(
                            'total_holding' => $db->dec($packageInfo['quantity']),
                            'total_sold' => $db->inc($packageInfo['quantity'])
                        );
                        $db->where('id', $packageInfo['packageID']);
                        $db->update('mlm_product', $updateData);
                    }
                } else {
                    $db->where('mlm_product_id', $packageIDAry, 'IN');
                    $db->where('client_id', $clientID);
                    $updateShoppingCart = $db->delete('shopping_cart');

                    $db->where('client_id', $clientID);
                    $updateSessionData = $db->delete('session_data');
                }

                //Add to placement
                if($placementID){
                    $placementTree = Tree::insertPlacementTree($clientID, $placementID,$placementPosition);
                    if (!$placementTree) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language]  /* Failed to register member.  */, 'data' => "");
                    }
                }

                //Instant calculate rank if purchase starterkit / join pack
                $isStarterCheck = 0;
                foreach($packageAry as $packageRow){
                    $packageID = $packageRow["packageID"];
                    $db->where('id',$packageID);
                    $db->where('status','Active');
                    $db->where('is_starter_kit','1');
                    $isStarterKitChecking = $db->getOne('mlm_product');

                    if($isStarterKitChecking){
                        $isStarterCheck ++;
                    }
                }

                if($isStarterCheck >= 1){
                    $calClientRank = Bonus::calculateBonusTier($clientID,$dateTime, 'starterKit', 'Bonus Tier');

                    if(!$calClientRank){
                        return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00150"][$language], "data" => "");
                    }

                    $db->where('id',$clientID);
                    $sponsorID = $db->getValue('client','sponsor_id');

                    $calSponsorRank = Bonus::calculateBonusTier($sponsorID,$dateTime, 'fizMemberUpgrade', 'Bonus Tier');

                    if(!$calClientRank && !$calSponsorRank){
                        return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00150"][$language], "data" => "");
                    }
                } else {
                    $calClientRank = Bonus::calculateBonusTier($clientID,$dateTime, 'fizMemberUpgrade', 'Bonus Tier');
                }


                // //Insert Rank Queue
                // unset($insertData,$jsonData);
                // $jsonData['dateTime'] = $dateTime;
                // if($isActivate == true){
                //     $jsonData['moduleType'] = "activate";
                // }
                // $insertData = array(
                //     "queue_type" => "calculateRank",
                //     "client_id"  => $clientID,
                //     "data"       => json_encode($jsonData),
                //     "created_at" => $dateTime,
                // );
                // $db->insert('queue',$insertData);

            }catch(Exception $e){
                $db->rollback();
                $db->commit();
                return array("status" => "error", "code" => 2, "statusMsg" => "System Error", "data" => "");
            }

            $db->commit();

            $db->where("id",$clientID);
            $clientUsername = $db->getValue("client","username");

            unset($activityData);

            $activityData = array(
                "user" => $clientUsername,
            );



            $activityReturn = Activity::insertActivity("Purchase Package","T00034","L00054",$activityData,$clientID);

            if(!$activityReturn){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00144"][$language], "data" => "");
            }

            return array("status" => "ok", "code" => 0, "statusMsg" => "Purchase Package Successfully", "data" => "");
        }

        // public function buyPackageSendNotice($params){
        public function buyPackageSendNotice($packageIDAry, $email){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $memberSite = Setting::$configArray['memberSite'];
            $companyInfo = Setting::$systemSetting['companyInfo'];

            $socialDetail = json_decode($companyInfo, true);

            $recipient = $email;//recipient is email destination
            $sendType = 'email';

            $db->where("product_id", $packageIDAry, "IN");
            $db->where("name", "catalogue");
            $db->where("value", "1");
            $sendCatalogue = $db->getValue("mlm_product_setting", "id");

            if($sendCatalogue){
                $attachmentFlag = 1;
            }

            $db->where("product_id", $packageIDAry, "IN");
            $db->where("name", "bBasic");
            $db->where("value", "1");
            $sendBBasic = $db->getValue("mlm_product_setting", "id");

            if($sendBBasic){
                $attachmentFlag = 2;
            }

            if($sendCatalogue && $sendBBasic){
                $attachmentFlag = 3;
            }

            if($sendCatalogue || $sendBBasic){
                $subject = $translations['B00478'][$language];
                $content = '
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>'.$subject.'</title>
                        <style>
                            .loginBlock {
                                display: block;
                                width: 600px;
                                padding: 3rem 3rem;
                                max-width: 100%;
                                background-color: #f4f7fa;
                                background-size: cover;
                                background-repeat: no-repeat;
                                background-position: right 15%;
                                color: #141414;
                                font-family: Arial, Helvetica, sans-serif;
                            }

                            img.companyLogo {
                                display: block;
                                margin: 0 auto;
                            }

                            .companyMsgBox {
                                background-color: #fff;
                                border-radius: 8px;
                                margin-top: 2rem;
                                padding: 2rem;
                                box-shadow: 0 0 20px -10px #ccc;
                            }

                            .companyEmailIcon {
                                display: block;
                                margin: 1.5rem auto;
                                width: 70px;
                                height: auto;
                            }

                            .longLine {
                                display: block;
                                width: 100%;
                                height: 2px;
                                background-color: #e7e7e7;
                                clear: both;
                                margin: 2rem auto;
                            }

                            .companyTxt1 {
                                font-size: 18px;
                                color: #48545c;
                            }

                            .companyTxt2 {
                                font-size: 17px;
                            }

                            .companyTxt3 {
                                font-size: 14px;
                                padding: 0;
                                margin: 20px 0 15px 0;
                            }

                            .companyTxt4 {
                                font-size: 14px;
                                padding: 0;
                                margin: 0;
                            }

                            .companyTxt4Div {
                                list-style-type: circle;
                            }

                            .contentImage {
                                width: 100%;
                                height: auto;
                            }

                            .socialIcon {
                                margin: 5px;
                                width: 30px;
                                height: 30px;
                            }

                            a.companyLinkBtn {
                                display: block;
                                width: 100%;
                                background-color: #29abe2;
                                color: #fff;
                                text-decoration: none;
                                padding: 8px;
                                border-radius: 4px;
                                text-transform: uppercase;
                            }

                            a.companyLinkBtn:hover {
                                text-decoration: underline;
                            }

                            .alignMiddle {
                                display: -webkit-flex;
                                display: flex;
                                -webkit-align-items: center;
                                align-items: center;
                                height: auto;
                            }

                            .alignMiddle img {
                                margin-right: 10px;
                            }
                        </style>
                    </head>
                    <body>
                    ';

                    $content .= '
                        <div class="loginBlock">
                            <div class="companyMsgBox">
                                <img class="companyEmailIcon" src="'.$memberSite.'/images/project/favicon.png" alt="">
                                <h3 class="companyTxt1">'.$translations['B00460'][$language].'</h3>
                                <div class="longLine"></div>
                                <h3 class="companyTxt2">'.$translations['B00461'][$language].'</h3>
                                <p class="companyTxt3">'.$translations['B00462'][$language].'</p>
                                <p class="companyTxt3">'.$translations['B00463'][$language].'</p>
                                <p class="companyTxt3">'.$translations['B00464'][$language].'</p>
                                <p class="companyTxt3"><img class="contentImage" src="'.$memberSite.'/images/project/welcomeEmail.jpg" alt=""></p>
                                <p class="companyTxt3">'.$translations['B00465'][$language].'</p>
                                <ul class="companyTxt4Div">
                                    <li><p class="companyTxt4">'.$translations['B00466'][$language].'</p></li>
                                    <li><p class="companyTxt4">'.$translations['B00467'][$language].'</p></li>
                                    <li><p class="companyTxt4">'.$translations['B00468'][$language].'</p></li>
                                </ul>
                                <p class="companyTxt3">
                                    <p class="companyTxt4">'.$translations['B00469'][$language].'</p>
                                    <p class="companyTxt4"><a href="'.$memberSite.'">'.$memberSite.'</a></p>
                                    <p class="companyTxt4 alignMiddle"><img class="socialIcon" src="'.$memberSite.'/images/project/ins-icon.png" alt="Instagram">'.$socialDetail['socialAcc'].'</p>
                                    <p class="companyTxt4 alignMiddle"><img class="socialIcon" src="'.$memberSite.'/images/project/fb-icon.png" alt="Facebook">'.$socialDetail['socialAcc'].'</p>
                                    <p class="companyTxt4 alignMiddle"><img class="socialIcon" src="'.$memberSite.'/images/project/tiktok-icon.png" alt="TikTok">'.$socialDetail['socialAcc'].'</p>
                                    <p class="companyTxt4 alignMiddle"><img class="socialIcon" src="'.$memberSite.'/images/project/youtube-icon.png" alt="Youtube">'.$socialDetail['socialMedia'].'</p>
                                </p>
                                <p class="companyTxt3">
                                    <p class="companyTxt4"><i>'.$translations['B00470'][$language].'</i></p>
                                    <p class="companyTxt4">'.$translations['B00471'][$language].'</p>
                                    <p class="companyTxt4">'.$translations['B00472'][$language].'</p>
                                    <p class="companyTxt4">'.$socialDetail['phone'].'</p>
                                </p>
                                <p class="companyTxt3">
                                    <p class="companyTxt4">'.$translations['B00473'][$language].'</p>
                                    <p class="companyTxt4">'.$translations['B00474'][$language].'</p>
                                </p>
                                <p class="companyTxt3"><b>'.$translations['B00475'][$language].'</b></p>
                                <p class="companyTxt3">
                                    <p class="companyTxt4"><b>'.$translations['B00476'][$language].'</b></p>
                                    <p class="companyTxt4">'.$translations['B00477'][$language].'</p>
                                </p>
                            </div>
                        </div>
                    </body>
                    </html>
                ';

                $result = Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType,'','','','',1, $attachmentFlag);
            }

            return array("status" => "ok", "code" => 0, "statusMsg" => "Purchase Package Successfully", "data" => "");
        }

        // -------- Supplier Module -------- //
        public function getSupplierListing($params, $type = false){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? : 0;
            $limit = General::getLimit($pageNumber);

            if($seeAll) {
                $limit = NULL;
            }

            $clientID = $db->userID;
            $site = $db->userType;

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {

                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'status':
                            if($dataValue == 'active')
                            {
                                $dataValue = 0;
                            }
                            else
                            {
                                $dataValue = 1;
                            }
                            $db->where("v.deleted", $dataValue);
                            break;

                        case 'supplierID':
                            $db->where('v.vendor_code', "%".$dataValue."%","LIKE");
                            break;

                        case 'name':
                            $db->where('name', "%" .$dataValue. "%" ,"LIKE");
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if($dateFrom <= 0 || $dateTo <= 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            if ($dateFrom) $db->where("Date(v.created_at)", date('Y-m-d', $dateFrom), '>=');

                            if ($dateTo) {
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                $db->where("Date(v.created_at)", date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $copyDb = $db->copy();
            $db->orderBy('v.created_at', 'DESC');
            // $db->where('v.deleted','0');
            $db->join('vendor_address va', 'v.id = va.vendor_id', 'LEFT');
            $supplier = $db->get('vendor v', NULL, 'v.id as id, v.name as name, v.vendor_code as vendor_code, v.pic as pic, v.mobile as mobile, v.email as email, va.address as address, v.created_at as created_at, v.deleted as deleted');

            if(!$supplier){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }
            foreach($supplier as $key => $value){
                $supplierList[$key]['id'] = $value['id'];
                $supplierList[$key]['name'] = $value['name'];
                $supplierList[$key]['code'] = $value['vendor_code'];
                $supplierList[$key]['phone'] = $value['mobile'];
                $supplierList[$key]['email'] = $value['email'];
                $supplierList[$key]['pic'] = $value['pic'];
                $supplierList[$key]['address'] = $value['address'];
                if($value['deleted'] == 0)
                {
                    $supplierList[$key]['status'] = 'Active';
                }
                else
                {
                    $supplierList[$key]['status'] = 'Inactive';
                }
                $supplierList[$key]['createdAt'] = date('Y-m-d',strtotime($value['created_at']));
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['supplierDetails'] = $supplierList;

            $totalRecord = $copyDb->getValue('vendor v', 'COUNT(*)');
            $data['pageNumber']          = $pageNumber;
            $data['totalRecord']         = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getSupplierDetail($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;
            $type = $params['type'];

            $supplierID = $params['supplierID'];

            if($type == 'add'){
                $data['code'] = General::generateDynamicCode("GT",3,'vendor','vendor_code');
            }else{
                if(!$supplierID) {
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01051'][$language] /* Invalid ID */, 'data' => "");
                }

                $db->where('id', $supplierID);
                $supplier = $db->getOne('vendor','name, vendor_code, pic, country_id, mobile, email, deleted');

                $db->where('id', $supplier['country_id']);
                $dialCode = $db->getValue('country','country_code');

                $db->where('vendor_id', $supplierID);
                $getAddress = $db->get('vendor_address',null, 'id, address');

                if(empty($supplier)){
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $data);
                } else {
                    if($supplier['deleted'] == 0) {
                        $supplierStatus = "Active";
                    } else {
                        $supplierStatus = "Inactive";
                    }
                }

                if($getAddress) {
                    foreach($getAddress as $val) {
                        $address['addressID'] = $val['id'];
                        $address['address']   = $val['address'];

                        $addressList[] = $address;
                    }
                }

                $data['name']     = $supplier['name'];
                $data['code']     = $supplier['vendor_code'];
                $data['address']  = $addressList;
                $data['status']   = $supplierStatus;
                $data['dialCode'] = $dialCode;
                $data['phone']    = $supplier['mobile'];
                $data['email']    = $supplier['email'];
                $data['pic']      = $supplier['pic'];
            }

            $db->where('delivery_country', '1');
            $countryList = $db->get('country',null,'id, name, iso_code2, country_code');
            $data['validCountry'] = $countryList;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function verifySupplier($params, $type){
            $db         = MysqliDb::getInstance();
            $language   = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime   = date("Y-m-d H:i:s");
            $adminRoleList = Setting::$systemSetting['InvEditableRoles'];
            $adminRolesListAry = explode("#", $adminRoleList);

            $clientID   = $db->userID;
            $site       = $db->userType;

            $supplierID = trim($params['supplierID']);
            $name       = trim($params['name']);
            $code       = trim($params['code']);
            $address    = $params['address'];
            $dialCode   = trim($params['dialCode']);
            $contact    = trim($params['contact']);
            $status     = trim($params['status']);
            $email      = trim($params['email']);
            $pic        = trim($params['pic']);

            $db->where('role_id', $adminRolesListAry, 'IN');
            $availableAdminRes = $db->getValue('admin', 'id', null);

            if($type == 'add'){
                if(empty($name)){
                    $errorFieldArr[] = array(
                        'id'  => "nameError",
                        'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                    );
                }
            }else{
                $db->where('id',$supplierID);
                $checkName = $db->getValue('vendor','name');
                if(!in_array($clientID, $availableAdminRes)){
                    if(empty($name) || $name != $checkName){
                        $errorFieldArr[] = array(
                            'id'  => "nameError",
                            'msg' => $translations['E01081'][$language] /* Name cannot be changed */
                        );
                    }
                }else{
                    if(empty($name)){
                        $errorFieldArr[] = array(
                            'id'  => "nameError",
                            'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                        );
                    }

                    // $db->where('id', $supplierID, '!=');
                    // $db->where('code',$code);
                    // $checkDuplicateCodeRes = $db->getValue('vendor','code');
                    // if($checkDuplicateCodeRes){
                    //     $errorFieldArr[] = array(
                    //         'id'  => "codeError",
                    //         'msg' => $translations['E00929'][$language] /* Code Exists. */
                    //     );
                    // }
                }
            }

            if(empty($address)){
                $errorFieldArr[] = array(
                    'id'  => "addressError",
                    'msg' => $translations['E00943'][$language] /* Please Insert Address */
                );
            }

            if(empty($contact) || empty($dialCode)){
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                );
            }

            if(!preg_match("/^[0-9]*$/",$contact) || strlen($identityNumber) > 30){
                $errorFieldArr[] = array(
                    "id" => "billingPhoneNumberError",
                    "msg" => $translations["E00858"][$language] /* Only number is allowed */
                );
            }

            if(empty($email)) {
                $errorFieldArr[] = array(
                    'id' => 'emailError',
                    'msg' => $translations["E00318"][$language] /* Please fill in email */
                );
            }else {
                if ($email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorFieldArr[] = array(
                            'id' => 'emailError',
                            'msg' => $translations["E00319"][$language] /* Invalid email format. */
                            );
                    }
                }
            }

            if(empty($pic)) {
                $errorFieldArr[] = array(
                    'id' => 'picError',
                    'msg' => $translations["E01164"][$language] /* Please fill in person in charge name */
                );
            }

            $statusAry = array('Active', 'Inactive');
            if(!$status || !in_array($status, $statusAry)) {
                $errorFieldArr[] = array(
                    'id'  => "statusError",
                    'msg' => $translations['E00671'][$language] /* Please Select Status. */
                );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            return array('status'=>"ok", 'code'=>0, 'statusMsg'=>"", 'data'=>"");
        }

        public function addSupplier($params){
            $db         = MysqliDb::getInstance();
            $language   = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime   = date("Y-m-d H:i:s");

            $clientID   = $db->userID;
            $site       = $db->site;

            $name       = trim($params['name']);
            $code       = trim($params['code']);
            $address    = $params['address'];
            $contact    = trim($params['contact']);
            $email      = trim($params['email']);
            $dialCode   = trim($params['dialCode']);
            $pic        = trim($params['pic']);
            $address    = trim($params['address']);

            if(!$clientID) {
                return array('status'=>'error', 'code'=>2, 'statusMsg'=>$translations['A01078'][$language] /* Access denied. */, 'data'=>'');
            } else {
                $db->where("id", $clientID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid Admin */,'data'=>'');
                }
            }

            $verify     = self::verifySupplier($params,'add');
            if($verify['status'] != 'ok') return $verify;

            $db->where('country_code', $dialCode);
            $countryID = $db->getValue('country','id');

            $insertVendorData = array(
                'name'        => $name,
                'vendor_code' => $code,
                'country_id'  => $countryID,
                'mobile'      => $contact,
                'email'       => $email,
                'pic'         => $pic,
                'deleted'     => '0',
                'created_at'  => $dateTime
            );
            $insertVendor = $db->insert('vendor', $insertVendorData);

            if(is_array($address)) {
                foreach($address as $val) {
                    $insertAddressData = array(
                        'address'    => $val,
                        'vendor_id'  => $insertVendor,
                        'deleted'    => '0',
                        'created_at' => $dateTime
                    );
                    $insertAddressDataList[] = $insertAddressData;
                }
                $insertAddress = $db->insertMulti('vendor_address', $insertAddressDataList);
            } else {
                $insertAddressData = array(
                    'address'    => $address,
                    'vendor_id'  => $insertVendor,
                    'deleted'    => '0',
                    'created_at' => $dateTime
                );
                $insertAddress = $db->insert('vendor_address', $insertAddressData);
            }

            if(!$insertVendor) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01082"][$language] /* Failed to add supplier. */, 'data' => "");
            }

            $activityData = array_merge(array('admin' => $checkAdmin), $insertData);

            // Insert Activity Log
            $activityRes = Activity::insertActivity('Add Supplier', 'T00056', 'L00076', $activityData, $clientID, $clientID, $site);

            if(!$activityRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> "");
        }

        public function editSupplier($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");
            $adminRoleList = Setting::$systemSetting['InvEditableRoles'];
            $adminRolesListAry = explode("#", $adminRoleList);

            $clientID = $db->userID;
            $site = $db->userType;

            $supplierID = trim($params['supplierID']);
            $name       = trim($params['name']);
            $code       = trim($params['code']);
            $address    = $params['address'];
            // $contact    = trim($params['contact']);
            $status     = trim($params['status']);
            // $dialCode   = trim($params['dialCode']);
            $email      = trim($params['email']);
            $pic        = trim($params['pic']);

            if(!$clientID) {
                return array('status'=>'error', 'code'=>2, 'statusMsg'=>$translations['A01078'][$language] /* Access denied. */, 'data'=>'');
            } else {
                $db->where("id", $clientID);
                $checkAdmin = $db->getValue("admin", "username");

                if(!$checkAdmin) {
                    return array('status'=>'error','code'=>2,'statusMsg'=> $translations["E00118"][$language] /* Invalid Admin */,'data'=>'');
                }
            }

            if(!$supplierID){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }
            // else{
            //     $db->where('id',$supplierID);
            //     $checkSupplier = $db->has('inv_supplier');
            //     if(!$checkSupplier){
            //         return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            //     }
            // }
            $verify     = self::verifySupplier($params,'edit');
            if($verify['status'] != 'ok') return $verify;

            // if($status == "Inactive"){
            //     $db->where("supplier_id", $supplierID);
            //     $db->groupBy("supplier_id");
            //     $checkStatus = $db->getValue("inv_stock","SUM(stock_in - stock_out)");

            //     if($checkStatus > 0){
            //         $errorFieldArr[] = array(
            //             'id'  => "statusError",
            //             'msg' => $translations['E01105'][$language] /* This supplier cannot be Inactive due to existing stock. */
            //         );
            //     }
            // }

            // if ($errorFieldArr) {
            //     $data['field'] = $errorFieldArr;
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            // }

            if($status == "Inactive"){
                $delete = '1';
            } else {
                $delete = '0';
            }

            $db->where('country_code', $dialCode);
            $countryID = $db->getValue('country','id');

            $db->where('role_id', $adminRolesListAry, 'IN');
            $checkAdminRoleRes = $db->getValue('admin','id',null);

            // if(in_array($clientID, $checkAdminRoleRes)){
            //     $updateData = array(
            //                         "name"      => $name,
            //                         "code"      => $code,
            //                         "country_id"=> $countryID,
            //                         "address"   => $address,
            //                         "phone"     => $contact,
            //                         "status"    => $status,
            //                         );

            //     $invLog = array(
            //             "module"                    =>  "inv_supplier",
            //             "module_id"                 =>  $supplierID,
            //             "title_transaction_code"    =>  "T00063",
            //             "title"                     =>  "Edit Supplier",
            //             "transaction_code"          =>  "L00086",
            //             "data"                      =>  json_encode(array("admin"=>$checkAdmin)),
            //             "creator_type"              =>  $site,
            //             "creator_id"                =>  $clientID,
            //             "created_at"                =>  $dateTime,
            //     );
            //     $db->insert("inv_log", $invLog);
            // }else{
            $updateData = array(
                "country_id" => $countryID,
                "name"       => $name,
                "email"      => $email,
                "updated_at" => $dateTime,
                "pic"        => $pic,
                "deleted"    => $delete
            );
            // }
            $db->where('id', $supplierID);
            $update = $db->update('vendor', $updateData);

            foreach($address as $val) {
                $db->where('id', $val['id']);
                $updateAddress = $db->update('vendor_address', array('address' => $val['address']));
            }

            if(!$update) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed. */, 'data' => "");
            }

            $activityData = array_merge(array('admin' => $checkAdmin), $updateData);

            // Insert Activity Log
            $activityRes = Activity::insertActivity('Edit Supplier', 'T00063', 'L00086', $activityData, $clientID, $clientID, $site);

            if(!$activityRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => "");
        }

        // -------- Invoice / PO / DO -------- //
        public function getInvoiceListing($params, $type){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $tableName      = "inv_order";
            $column         = array("id","client_id","created_at","reference_number","po_number","delivery_option","status","total_pv","total_price", "courier_service", "payment_type");

            $searchData     = $params['searchData'];
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $seeAll         = $params['seeAll'];

            $clientID = $db->userID;
            $site = $db->userType;

            if($seeAll == 1){
                $limit = null;
            }

            if($limit) $limitCond = "LIMIT ".implode(",", $limit);

            $cpDb = $db->copy();

            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "mainLeaderUsername":
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            // $db->where('client_id', $mainDownlines, "IN");
                            $orderRules[] = "client_id IN ('".implode("', '", $mainDownlines)."')";
                            $pendingRules[] = "client_id IN ('".implode("', '", $mainDownlines)."')";

                            break;

                        case 'mainLeaderID':
                            // $db ->where('member_id', $dataValue);
                            // $mainLeaderID = $db ->getValue('client', 'id');
                            // $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where("client_id = leader_id");
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }
                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $orderRules[] = "client_id IN ('".implode("', '", $mainDownlines)."')";
                            $pendingRules[] = "client_id IN ('".implode("', '", $mainDownlines)."')";

                            break;

                        case 'leaderID':
                            $db->where('member_id', $dataValue);
                            $leaderID = $db->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $orderRules[] = "client_id IN ('".implode("', '", $downlines)."')";
                            $pendingRules[] = "client_id IN ('".implode("', '", $downlines)."')";

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {

                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'invoiceNo':
                            $orderRules[] = "reference_number = '".$dataValue."'";
                            break;

                        case 'transactionDate':

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }
                            }

                            $orderRules[] = "Date(created_at) >= '".date('Y-m-d', $dateFrom)."'";
                            $orderRules[] = "Date(created_at) <= '".date('Y-m-d', $dateTo)."'";

                            $pendingRules[] = "Date(created_at) >= '".date('Y-m-d', $dateFrom)."'";
                            $pendingRules[] = "Date(created_at) <= '".date('Y-m-d', $dateTo)."'";

                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'memberID':
                            $db->where('member_id', $dataValue);
                            $searchClientID = $db->getValue('client', "id");

                            $orderRules[] = "client_id = '".$searchClientID."'";
                            $pendingRules[] = "client_id = '".$searchClientID."'";

                            break;

                        case 'username':
                            $db->where('username', $dataValue);
                            $searchClientID = $db->getValue('client', "id");

                            $orderRules[] = "client_id = '".$searchClientID."'";
                            $pendingRules[] = "client_id = '".$searchClientID."'";

                            break;

                        case 'fullname':
                            if ($dataType == "like") {
                                $db->where('name', '%' . $dataValue . '%', "LIKE");
                                $fullnames = $db->getValue('client', "id", NULL);

                                $orderRules[] = "client_id IN ('".implode("', '", $fullnames)."')";
                                $pendingRules[] = "client_id IN ('".implode("', '", $fullnames)."')";
                            }else{
                                $db->where('name', $dataValue);
                                $fullname = $db->getOne('client', "id")['id'];

                                $orderRules[] = "client_id = '".$fullname."'";
                                $pendingRules[] = "client_id = '".$fullname."'";
                            }

                            break;

                        // filter for po listing
                        case 'deliveryOption':
                            $orderRules[] = "delivery_method = '".$dataValue."'";
                            break;

                        case 'status':
                            $orderRules[] = "status = '".$dataValue."'";
                            $pendingRules[] = "status = '".$dataValue."'";
                            break;

                        case 'poNumber':
                            $orderRules[] = "id = '".$dataValue."'";
                            break;

                        case 'doNumber':
                            $db->where('reference_number', $dataValue);
                            $invIDs = $db->getValue('inv_delivery_order', "inv_order_id", NULL);

                            $orderRules[] = "id IN ('".implode("', '", $invIDs)."')";
                            break;

                        case 'payment_method':
                            $orderRules[] = "payment_method = '".$dataValue."'";
                            break;
    
                    }

                    unset($dataName);
                    unset($dataValue);
                }
            }


            if($site == "Member"){
                // $db->where("client_id",$clientID);
                $orderRules[] = "client_id = '".$clientID."'";
                $pendingRules[] = "client_id = '".$clientID."'";
            } else {
                $pendingRules[] = "status = 'Paid'" ;
            }

            // $copyDB = $db->copy();
            // $db->orderBy("created_at", "DESC");
            $sort = "ORDER BY created_at DESC";
            if($orderRules) $orderRule = "WHERE ". implode(" AND ", $orderRules);

            $pendingRules[] = "status IN ('Draft', 'Pending', 'Expired', 'Waiting for Payment', 'Cancelled', 'Payment Verified')" ;
            if($pendingRules) $pendingRule = "WHERE ". implode(" AND ", $pendingRules);

            $query = "SELECT id, client_id, created_at, 'order' FROM sale_order ".$orderRule. $sort;

            $invoiceList = $db->rawQuery($query);

            if(empty($invoiceList)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            foreach ($invoiceList as $invoiceRow) {
                $invoiceIDAry[$invoiceRow['id']] = $invoiceRow['id'];

                if($invoiceRow["order"] == 'order'){
                    $invoiceOrderIDAry[$invoiceRow['id']] = $invoiceRow['id'];
                } else {
                    $invoicePendingIDAry[$invoiceRow['id']] = $invoiceRow['id'];
                }

                $clientIDAry[$invoiceRow['client_id']] = $invoiceRow['client_id'];
            }

            // print_r(json_encode($invoiceList));
            // exit;
            if($invoiceOrderIDAry){
                $db->where('id', $invoiceOrderIDAry, 'IN');
                $invData = $db->map('id')->get('sale_order', NULL, 'id, payment_amount, delivery_method, status, redeem_amount, payment_method');

                // print_r("invData:".json_encode($invClient)."\n\n");
                // exit;
                // $db->where("inv_order_id",$invoiceOrderIDAry, "IN");
                // $invOrderIDAry = $db->map("inv_order_id")->get("inv_order_payment", NULL, "inv_order_id, credit_type");
                // foreach ($invOrderIDAry as $creditRow) {
                //     $creditNameAry[$creditRow] = $creditRow;
                // }
                // if($creditNameAry){
                //     $db->where("type",$creditNameAry, "IN");
                //     $creditLang = $db->map("type")->get("credit", NULL, "type, translation_code");
                // }

                // $db->where("inv_order_id",$invoiceOrderIDAry, "IN");
                // $invOrderDetailRes = $db->get('inv_order_detail', NULL ,'inv_order_id, mlm_product_id, left_stock_quantity');

                // foreach ($invOrderDetailRes as $detailRow) {
                //     $invOrderDetailList[$detailRow['inv_order_id']][$detailRow['mlm_product_id']] += 1;
                //     $productList[$detailRow['mlm_product_id']] = $detailRow['mlm_product_id'];
                //     $leftStockQuantityAry[$detailRow['inv_order_id']] += $detailRow['left_stock_quantity'];
                // }

                // if($productList){
                //     $db->where('module_id', $productList, 'IN');
                //     $db->where('module', 'mlm_product');
                //     $db->where('language', $language);
                //     $db->where('type', 'name');
                //     $productData = $db->map('module_id')->get('inv_language', null, 'module_id, content');
                // }

                // $db->where("inv_order_id", $invoiceOrderIDAry,'IN');
                // $deliveryOrderRes = $db->get('inv_delivery_order', null, 'id, inv_order_id, tracking_number, reference_number, status');

                // foreach ($deliveryOrderRes as $deliveryOrderRow) {
                //     if($deliveryOrderRow['tracking_number']){
                //         $trackingNoAry[$deliveryOrderRow['inv_order_id']]['tracking_number'] = $deliveryOrderRow['tracking_number'];
                //         $trackingNoAry[$deliveryOrderRow['inv_order_id']]['status'] = $deliveryOrderRow['status'];
                //     }
                //     if($deliveryOrderRow['reference_number']){
                //         $trackingNoAry[$deliveryOrderRow['inv_order_id']]['reference_number'] = $deliveryOrderRow['reference_number'];
                //     }
                // }

                // $db->where("inv_order_id",$invoiceOrderIDAry, "IN");
                // $deliOrderNum = $db->map('inv_order_id')->get('inv_delivery_order', NULL ,'inv_order_id, reference_number');

                // $db->where("inv_order_id",$invoiceOrderIDAry,"IN");
                // $voucherDataAry = $db->map("inv_order_id")->get("inv_order_voucher",null,"inv_order_id,type,discount_type,discount_percentage,discount_amount,real_discount_amount");
            }
           
            if($clientIDAry){
                $db->where("id",$clientIDAry, "IN");
                $clientInfoAry = $db->map("id")->get("client", NULL, "id, member_id, username, name");
            }

            if($invoicePendingIDAry){
                $db->where('id', $invoicePendingIDAry, 'IN');
                $pendingData = $db->map('id')->get('mlm_pending_payment', NULL, 'id, client_id, amount, status');
            }
//  print_r("clientInfoAry:".json_encode($clientInfoAry)."\n\n");
//                 exit;
            foreach ($invoiceList as $invoiceRow) {
                unset($invoice);
                $invoice['id'] = $invoiceRow['id'];
                $invoice['memberID'] = $clientInfoAry[$invoiceRow['client_id']]['member_id'];
                $invoice['username'] = $clientInfoAry[$invoiceRow['client_id']]['username'];
                $invoice['created_at'] = date($dateTimeFormat, strtotime($invoiceRow['created_at']));
                // $invoice['invoiceNumber'] = $invData[$invoiceRow['id']]['reference_number'] ?: '-';
                $invoice['fullname'] = $clientInfoAry[$invoiceRow['client_id']]['name'];
                $paymentMethod = !empty($invData[$invoiceRow['id']]['payment_method']) ? $invData[$invoiceRow['id']]['payment_method'] : "-";
                $invoice['paymentMethod'] = $paymentMethod;
                
                if($invoiceRow['order'] == 'order'){
                    $invoice['poNumber'] = $invoiceRow['id'];
                    $deliveryOption = !empty($invData[$invoiceRow['id']]['delivery_method']) ? $invData[$invoiceRow['id']]['delivery_method'] : "-";
                    $invoice['deliveryOption'] = $deliveryOption;
                    // $invoice['DONumber'] = $deliOrderNum[$invoiceRow['id']] ?: "-";
                    // $invoice['courierService'] = $invData[$invoiceRow['id']]['courier_service'];
                    $invoice["payment_amount"] = Setting::setDecimal($invData[$invoiceRow['id']]['payment_amount']);
                    $invoice["redeem_amount"] = Setting::setDecimal($invData[$invoiceRow['id']]['redeem_amount']);

                    $invoice['status'] = $invData[$invoiceRow['id']]['status'];
                    $invoice["statusDisplay"] = $invData[$invoiceRow['id']]['status'];
                }
                    // $invoice["statusDisplay"] = General::getTranslationByName($invData[$invoiceRow['id']]['status']);
                // } else {
                //     $invoice['poNumber'] = '-';
                //     $invoice['deliveryOption'] = '-';
                //     $invoice['DONumber'] = '-';
                //     $invoice['courierService'] = '-';
                //     $invoice["orderAmount"] = Setting::setDecimal($pendingData[$invoiceRow['id']]['amount']);
                //     $invoice["totalPV"] = Setting::setDecimal(0);

                //     $invoice['paymentMethod'] = General::getTranslationByName('VirtualAccount');
                //     $invoice['status'] = $pendingData[$invoiceRow['id']]['status'];
                //     $invoice["statusDisplay"] = General::getTranslationByName("PG ".$pendingData[$invoiceRow['id']]['status']);
                // }

                // $invoice['trackingFlag'] = 0;
                // $invoice['trackingNo'] = "-";
                // $invoice['trackingStatus'] = "-";
                // $invoice['DONumber'] = "-";
                // if($trackingNoAry[$invoiceRow['id']] && $invoiceRow['order'] == 'order'){
                //     $invoice['trackingFlag'] = 1;
                //     $invoice['trackingNo'] = $trackingNoAry[$invoiceRow['id']]['tracking_number'] ? : '-';
                //     $invoice['trackingStatus'] = General::getTranslationByName($trackingNoAry[$invoiceRow['id']]['status']) ? : '-';
                //     $invoice['DONumber'] = $trackingNoAry[$invoiceRow['id']]['reference_number'] ? : '-';
                // }

                // $invoice['issueDOAllowed'] = 0;
                // if($leftStockQuantityAry[$invoiceRow['id']] > 0 && $invoiceRow['order'] == 'order'){
                //     $invoice['issueDOAllowed'] = 1;
                // }

                // unset($tmpPackageList);
                // if($invoiceRow['order'] == 'order'){
                //     foreach ($invOrderDetailList[$invoiceRow['id']] as $productID => $amount) {
                //         $tmp['packageDisplay'] = $productData[$productID];
                //         $tmp['amount'] = $amount;
                //         $tmpPackageList[] = $tmp;
                //     }
                //     $invoice["packageList"] = $tmpPackageList;
                // } else{
                //     $invoice["packageList"] = $tmpPackageList;
                // }

                // if($voucherDataAry[$invoiceRow["id"]] && $invoiceRow['order'] == 'order'){
                //     unset($voucherData);
                //     $voucherData["voucherType"] = $voucherDataAry[$invoiceRow["id"]]["type"];
                //     $voucherData["discountType"] = $voucherDataAry[$invoiceRow["id"]]["discount_type"];
                //     switch($voucherDataAry[$invoiceRow["id"]]["discount_type"]){
                //         case "percentage":
                //             $voucherData["discountPercentage"] = Setting::setDecimal($voucherDataAry[$invoiceRow["id"]]["discount_percentage"]);
                //             $voucherData["maxDiscountAmount"] = Setting::setDecimal($voucherDataAry[$invoiceRow["id"]]["discount_amount"]);
                //             break;

                //         case "amount":
                //             $voucherData["discountAmount"] = Setting::setDecimal($voucherDataAry[$invoiceRow["id"]]["discount_amount"]);
                //             break;
                //     }
                //     $voucherData["realDiscountAmount"] = Setting::setDecimal($voucherDataAry[$invoiceRow["id"]]["real_discount_amount"]);
                //     $invoice["voucherData"] = $voucherData;
                // }

                // $invoice["invoiceAmount"] = Setting::setDecimal($invData[$invoiceRow['id']]['paid_amount']);

                $invoiceListing[] = $invoice;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $params['exportType'] = $type;

                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            switch ($params['exportType']) {
                case 'invoice':
                    $data['newCommand'] = 'getInvoiceListing';
                    break;

                case 'apo':
                    $data['newCommand'] = 'getAdminOrderListing';
                    break;

                default:
                    break;
            }

            $data['invoiceList'] = $invoiceListing;

            $totalRecordRes = $db->rawQuery("SELECT COUNT(id) AS totalRecord FROM (SELECT id FROM sale_order ".$orderRule.") x");
            $totalRecord = $totalRecordRes[0]['totalRecord'];

            $data["pageNumber"] = $pageNumber;
            $data["totalRecord"] = $totalRecord;
            if($seeAll == "1") {
                $data["totalPage"] = 1;
                $data["numRecord"] = $totalRecord;
            } else {
                $data["totalPage"] = ceil($totalRecord/$limit[1]);
                $data["numRecord"] = $limit[1];
            }

            if($type == "invoice"){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00111"][$language] /* Invoice List successfully retrieved. */, 'data' => $data);
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00421"][$language] /* Purchase Order List successfully retrieved. */, 'data' => $data);
            }
        }

        public function getInvoiceDetailOld($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $invoiceID = trim($params['invOrderID']);

            if(!$invoiceID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language], 'data'=> "");
            }

            // Company address
            $db->where('name', 'HQ');
            $db->where('type', 'companyAddress');
            $companyAddress = $db->getValue('system_settings', 'value');

            // Company Contact No/ Email/ Fax
            $db->where('type', 'companyContact');
            $res = $db->getValue('system_settings', 'value');
            $companyContactList = json_decode($res, true);

            // Invoice No, Transaction Date, Member ID, Full Name, Delivery Method, Shipping Address, Contact No, Email Address, Billing Address

            $db->where("id", $invoiceID);
            $invoiceDetail = $db->getOne("inv_order","id, client_id, reference_number AS referenceNo, created_at AS createdAt, delivery_option AS deliveryOption, delivery_add_id, billing_add_id, total_price AS subTotal, tax_charges AS taxCharges, delivery_fee AS deliveryFee, paid_amount AS paidAmount, total_pv AS totalPV");

            $invoiceDetail['createdAt'] = date($dateTimeFormat, strtotime($invoiceDetail["createdAt"]));
            $invoiceDetail['subTotal'] = Setting::setDecimal($invoiceDetail['subTotal']);
            $invoiceDetail['taxCharges'] = Setting::setDecimal($invoiceDetail['taxCharges']);
            $invoiceDetail['deliveryFee'] = Setting::setDecimal($invoiceDetail['deliveryFee']);
            $invoiceDetail['paidAmount'] = Setting::setDecimal($invoiceDetail['paidAmount']);
            $invoiceDetail['totalPV'] = Setting::setDecimal($invoiceDetail['totalPV']);

            // get delivery address, email, phone, from address table
            $db->where("address_type", "delivery");
            $db->where("id", $invoiceDetail['delivery_add_id']);
            $addressDetail= $db->getOne("address","address, street_name AS streetName, sub_district AS subDistrict, post_code AS postCode, city, state, country_id, email, phone");

            // get state from state table
            $db->where("id", $addressDetail["state"]);
            $stateRes = $db->getOne("state","name, translation_code");

             // get country from state table
            $db->where("id", $addressDetail["country_id"]);
            $countryRes = $db->getOne("country","name, translation_code");

            unset($addressDetail['country_id']);
            $addressDetail['state'] = $stateRes['name'];
            $addressDetail['stateDisplay'] = $translations[$stateRes['translation_code']][$language];
            $addressDetail['country'] = $countryRes['name'];
            $addressDetail['countryDisplay'] = $translations[$countryRes['translation_code']][$language];

            // get billing address from address table
            $db->where("address_type", "billing");
            $db->where("id", $invoiceDetail['billing_add_id']);
            $billingAddressDetail = $db->getOne("address","address, street_name AS streetName, sub_district AS subDistrict, post_code AS postCode, city, state, country_id");

            // get state from state table
            $db->where("id", $billingAddressDetail["state"]);
            $billingStateRes = $db->getOne("state","name, translation_code");

             // get country from state table
            $db->where("id", $billingAddressDetail["country_id"]);
            $billingCountryRes = $db->getOne("country","name, translation_code");

            unset($billingAddressDetail['country_id']);
            $billingAddressDetail['state'] = $billingStateRes['name'];
            $billingAddressDetail['stateDisplay'] = $translations[$billingStateRes['translation_code']][$language];
            $billingAddressDetail['country'] = $billingCountryRes['name'];
            $billingAddressDetail['countryDisplay'] = $translations[$billingCountryRes['translation_code']][$language];

            // client table:  Member ID, Full Name
            $db->where("id", $invoiceDetail['client_id']);
            $clientDetail = $db->getOne("client","member_id AS memberID, name");

            unset($invoiceDetail['delivery_add_id']);
            unset($invoiceDetail['billing_add_id']);
            unset($invoiceDetail['client_id']);

            $db->where("inv_order_id", $invoiceDetail['id']);
            $invOrderList = $db->get('inv_order_detail', NULL ,'mlm_product_id AS packageDisplay, inv_product_id AS productDisplay, price, weight, quantity, stock_quantity, left_stock_quantity AS quantityLeft');

            foreach ($invOrderList as $invOrderRow) {

                $invOrderRow['price'] = Setting::setDecimal($invOrderRow['price']);
                $invOrderRow['weight'] = Setting::setDecimal($invOrderRow['weight']);
                $invOrderRow['totalWeight'] = Setting::setDecimal($invOrderRow['weight'] * $invOrderRow['quantity'] * $invOrderRow['stock_quantity'] );
                $invOrderRow['quantity'] = Setting::setDecimal($invOrderRow['quantity']);
                $invOrderRow['quantityLeft'] = Setting::setDecimal($invOrderRow['quantityLeft']);

                $invOrder[$invOrderRow['packageDisplay']][$invOrderRow['productDisplay']] = $invOrderRow;
                $invProductIDAry[$invOrderRow["productDisplay"]] = $invOrderRow["productDisplay"];
                $mlmProductIDAry[$invOrderRow["packageDisplay"]] = $invOrderRow["packageDisplay"];
            }

            if($invProductIDAry){
                // product
                $db->where("module", "inv_product");
                $db->where("module_id", $invProductIDAry, "IN");
                $db->where("type", "name");
                $db->where("language", $language);
                $langRes = $db->get('inv_language', NULL ,'module_id, language, content');

                foreach ($langRes as $langRow) {
                    $lang[$langRow['module_id']] = $langRow['content'];
                }
            }

            if($mlmProductIDAry){
                // package
                $db->where("module", "mlm_product");
                $db->where("module_id", $mlmProductIDAry, "IN");
                $db->where("type", "name");
                $db->where("language", $language);
                $packageLangRes = $db->get('inv_language', NULL ,'module_id, language, content');

                foreach ($packageLangRes as $langRow) {
                    $packageLang[$langRow['module_id']] = $langRow['content'];
                }
            }

            foreach ($invOrder as $packageID => $packageValue) {
                foreach ($packageValue as $productID => $productValue) {
                    $invOrder[$packageID][$productID]['productDisplay'] = $lang[$productValue['productDisplay']];
                    $invOrder[$packageID][$productID]['packageDisplay'] = $packageLang[$productValue['packageDisplay']];
                }
            }

            // assign data into data
            $data['companyAddress'] = $companyAddress;
            $data['companyContact'] = $companyContactList;
            $data['invoiceDetail'] = $invoiceDetail;
            $data['deliveryAddressDetail'] = $addressDetail;
            $data['billingAddressDetail'] = $billingAddressDetail;
            $data['clientDetail'] = $clientDetail;
            $data['packageList'] =  $invOrder;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language] /* Successfully retrieved */, 'data' => $data);
        }

        public function getInvoiceDetail($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $invoiceID = trim($params['invOrderID']);

            if(!$invoiceID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language], 'data'=> "");
            }

            // Company address
            $db->where('name', 'pickUpOrigins');
            $companyAddress = $db->getValue('system_settings', 'reference');

            // Company Contact No/ Email/ Fax
            $db->where('type', 'companyContact');
            $res = $db->getValue('system_settings', 'value');
            $companyContactList = json_decode($res, true);

            $db->where("id", $invoiceID);
            $invoiceDetail = $db->getOne("inv_order","id, po_number AS purchaseOrderNo , client_id, reference_number AS referenceNo, created_at AS createdAt, delivery_option AS deliveryOption, delivery_add_id, billing_add_id, total_price AS subTotal, tax_charges AS taxCharges, delivery_fee AS deliveryFee, paid_amount AS paidAmount, total_pv AS totalPV, total_weight AS totalWeight, special_note as specialNote, remark, courier_service AS courierService, insurance_tax AS insuranceTax");

            $db->where("inv_order_id", $invoiceDetail['id']);
            $db->where("status",array("Cancel"),"NOT IN");
            $deliveryOrderRes = $db->get('inv_delivery_order', null, 'inv_order_id, tracking_number');
            foreach ($deliveryOrderRes as $deliveryOrderRow) {
                if($deliveryOrderRow['tracking_number']){
                    $trackingNo[] = $deliveryOrderRow['tracking_number'];
                }
            }

            $data['showInsuranceTax'] = 0;
            $invoiceDetail['insuranceTax'] = Setting::setDecimal($invoiceDetail['insuranceTax']);
            if($invoiceDetail['insuranceTax'] > 0) $data['showInsuranceTax'] = 1;

            $invoiceDetail['createdAt'] = date($dateTimeFormat, strtotime($invoiceDetail["createdAt"]));
            $invoiceDetail['subTotal'] = Setting::setDecimal($invoiceDetail['subTotal']);
            $invoiceDetail['taxCharges'] = Setting::setDecimal($invoiceDetail['taxCharges']);
            $invoiceDetail['deliveryFee'] = Setting::setDecimal($invoiceDetail['deliveryFee']);
            $invoiceDetail['paidAmount'] = Setting::setDecimal($invoiceDetail['paidAmount']);
            $invoiceDetail['totalPV'] = Setting::setDecimal($invoiceDetail['totalPV']);
            $invoiceDetail['trackingNo'] = $trackingNo ? implode(', ', $trackingNo) : '-';

            $addressIDAry = array($invoiceDetail['delivery_add_id'], $invoiceDetail['billing_add_id']);

            if($addressIDAry){
                $db->where("id", $addressIDAry, "IN" );
                $addressDetailAry= $db->map('id')->get("address", NULL,"id, name, address, district_id AS districtID, sub_district_id AS subDistrictID, post_code AS postCodeID, city AS cityID, state_id AS stateID, country_id AS countryID, email, phone, address_type");
            }

            foreach ($addressDetailAry as $addressRow) {
                $stateAry[$addressRow['stateID']] = $addressRow['stateID'];
                $countryAry[$addressRow['countryID']] = $addressRow['countryID'];
                $countyAry[$addressRow['districtID']] = $addressRow['districtID'];
                $subCountyAry[$addressRow['subDistrictID']] = $addressRow['subDistrictID'];
                $postCodeAry[$addressRow['postCodeID']] = $addressRow['postCodeID'];
                $cityAry[$addressRow['cityID']] = $addressRow['cityID'];
            }

            if($stateAry){
                $db->where("id", $stateAry, "IN");
                $stateRes = $db->map('id')->get("state", NULL,"id, name, translation_code");
            }

            if($countryAry){
                $db->where("id", $countryAry, "IN");
                $countryRes = $db->map('id')->get("country", NULL,"id, name, translation_code, country_code");
            }

            if($countyAry){
                $db->where("id", $countyAry, "IN");
                $countyRes = $db->map('id')->get("county", NULL,"id, name, translation_code");
            }

            if($subCountyAry){
                $db->where("id", $subCountyAry, "IN");
                $subCountyRes = $db->map('id')->get("sub_county", NULL,"id, name, translation_code");
            }

            if($postCodeAry){
                $db->where("id", $postCodeAry, "IN");
                $postCodeRes = $db->map('id')->get("zip_code", NULL,"id, name, translation_code");
            }

            if($cityAry){
                $db->where("id", $cityAry, "IN");
                $cityRes = $db->map('id')->get("city", NULL,"id, name, translation_code");
            }

            foreach ($addressDetailAry as $addressRow) {
                unset($addressDetail);
                $addressDetail['fullname'] = $addressRow['name'];
                $addressDetail['address'] = $addressRow['address'];
                $addressDetail['district'] = $countyRes[$addressRow['districtID']]['name'];
                $addressDetail['subDistrict'] = $subCountyRes[$addressRow['subDistrictID']]['name'];
                $addressDetail['postCode'] = $postCodeRes[$addressRow['postCodeID']]['name'];
                $addressDetail['city'] = $cityRes[$addressRow['cityID']]['name'];
                $addressDetail['email'] = $addressRow['email'];
                $addressDetail['phone'] = $addressRow['phone'];
                $addressDetail['state'] = $stateRes[$addressRow['stateID']]['name'];
                $addressDetail['stateDisplay'] = $stateRes[$addressRow['stateID']]['name'];
                $addressDetail['country'] = $countryRes[$addressRow['countryID']]['name'];
                $addressDetail['dialingArea'] = $countryRes[$addressRow['countryID']]['country_code'];
                $addressDetail['countryDisplay'] = $translations[$countryRes[$addressRow['countryID']]['translation_code']][$language];

                $addressDetailRes[$addressRow['id']] = $addressDetail;
            }

            $deliveryAddressData = $addressDetailRes[$invoiceDetail['delivery_add_id']];
            $billingAddressData = $addressDetailRes[$invoiceDetail['billing_add_id']];

            // client table:  Member ID, Full Name
            $db->where("id", $invoiceDetail['client_id']);
            $clientDetail = $db->getOne("client","member_id AS memberID, name");

            unset($invoiceDetail['delivery_add_id']);
            unset($invoiceDetail['billing_add_id']);
            unset($invoiceDetail['client_id']);

            $db->where("inv_order_id", $invoiceDetail['id']);
            $db->orderBy("mlm_product_id", "ASC");
            $invOrderList = $db->get('inv_order_detail', NULL ,'mlm_product_id AS packageDisplay, inv_product_id AS productDisplay, price AS packagePrice, weight, quantity, stock_quantity AS stockQuantity, left_stock_quantity AS quantityLeft, pv_price as orderPvPrice');

            foreach ($invOrderList as $invOrderRow) {
                $invProductIDAry[$invOrderRow["productDisplay"]] = $invOrderRow["productDisplay"]; // product
                $mlmProductIDAry[$invOrderRow["packageDisplay"]] = $invOrderRow["packageDisplay"]; // package

                $mlmInvOrderPv[$invOrderRow["packageDisplay"]] = $invOrderRow["orderPvPrice"] * $invOrderRow["quantity"];
            }

            if($invProductIDAry){
                // product
                $db->where("id", $invProductIDAry, "IN");
                $productNameRes = $db->map('id')->get('inv_product', NULL ,'id, name');
            }

            if($mlmProductIDAry){
                // package
                $db->where("module_id", $mlmProductIDAry, "IN");
                $db->where("module", "mlm_product");
                $db->where("type", "name");
                $db->where("language", $language);
                $packageLangRes = $db->get('inv_language', NULL ,'module_id, language, content');

                foreach ($packageLangRes as $langRow) {
                    $packageLang[$langRow['module_id']] = $langRow['content'];
                }

                // $db->where("product_id", $mlmProductIDAry, "IN");
                // $packagePriceAry = $db->map('product_id')->get('mlm_product_price', NULL ,'product_id,price'); // package price

                $db->where("id", $mlmProductIDAry, "IN");
                $pvPriceAry = $db->map("id")->get('mlm_product', NULL ,'id, weight, pv_price AS pvPrice');
            }

            unset($currentPackageID);
            foreach ($invOrderList as $invOrderRow) {
                unset($invOrderData);
                unset($productListData);
                if($currentPackageID != $invOrderRow['packageDisplay']){
                    unset($productList);
                    unset($totalProductWeight);
                }
                $invOrderData['package'] = $mlmProductIDAry[$invOrderRow['packageDisplay']];
                $invOrderData['packageDisplay'] = $packageLang[$mlmProductIDAry[$invOrderRow['packageDisplay']]];
                $invOrderData['packagePrice'] = Setting::setDecimal($invOrderRow['packagePrice']);
                $invOrderData['totalPackagePrice'] = Setting::setDecimal($invOrderRow['packagePrice'] * $invOrderRow['quantity']);
                //$invOrderData['pvPrice'] = Setting::setDecimal($pvPriceAry[$invOrderRow['packageDisplay']]["pvPrice"]);
                $invOrderData['pvPrice'] = Setting::setDecimal($mlmInvOrderPv[$invOrderRow['packageDisplay']]);


                $invOrderData['packageQuantity'] = Setting::setDecimal($invOrderRow['quantity']);
                $productListData['productDisplay'] = $productNameRes[$invOrderRow['productDisplay']];
                $productListData['productID'] = $invOrderRow['productDisplay'];

                if(($pvPriceAry[$invOrderRow["packageDisplay"]]["weight"] > 0)){
                    $invOrderData["totalProductWeight"] = Setting::setDecimal($invOrderRow["weight"] * $invOrderRow["quantity"]);
                }else{
                    $productListData["weight"] = Setting::setDecimal($invOrderRow["weight"] * $invOrderRow["stockQuantity"]); // stock quantity * weight
                    $productListData["singleWeight"] = Setting::setDecimal($invOrderRow["weight"]); // single weight
                    $totalProductWeight += $productListData['weight'];
                    $invOrderData['totalProductWeight'] = $totalProductWeight;
                }

                $productListData['stockQuantity'] = Setting::setDecimal($invOrderRow['stockQuantity']);
                $productListData['quantityLeft'] = Setting::setDecimal($invOrderRow['quantityLeft']);
                $productList[] = $productListData;
                $invOrderData["productList"] = $productList;
                $currentPackageID = $invOrderRow['packageDisplay'];
                $invOrder[$invOrderRow['packageDisplay']] = $invOrderData;

                $leftStockQuantity += $invOrderRow['quantityLeft'];
            }

            $issueDOFlag = 0;
            if($leftStockQuantity > 0){
                $issueDOFlag = 1;
            }

            $db->where("type","SST");
            $db->orderBy("created_at","DESC");
            $taxPercentage = $db->getValue("inv_tax_charges","rate");
            if(!$taxPercentage) $taxPercentage = 0;

            // assign data into data
            if($invoiceDetail['deliveryOption'] == "pickup"){
                $data['deliveryAddressDetail']['pickUpAddress'] = $companyAddress;
            }else{
                $data['deliveryAddressDetail'] = $deliveryAddressData;
            }

            $db->where("inv_order_id",$invoiceDetail["id"]);
            $voucherDataAry = $db->getOne("inv_order_voucher","inv_voucher_id,type,discount_type,discount_percentage,discount_amount,real_discount_amount");

            if($voucherDataAry){
                unset($voucherData);
                $db->where("id",$voucherDataAry["inv_voucher_id"]);
                $voucherData["voucherCode"] = $db->getValue("inv_voucher","code");
                $voucherData["voucherType"] = $voucherDataAry["type"];
                $voucherData["discountType"] = $voucherDataAry["discount_type"];
                switch($voucherDataAry["discount_type"]){
                    case "percentage":
                        $voucherData["discountPercentage"] = Setting::setDecimal($voucherDataAry["discount_percentage"]);
                        $voucherData["maxDiscountAmount"] = Setting::setDecimal($voucherDataAry["discount_amount"]);
                        break;

                    case "amount":
                        $voucherData["discountAmount"] = Setting::setDecimal($voucherDataAry["discount_amount"]);
                        break;
                }
                $voucherData["realDiscountAmount"] = Setting::setDecimal($voucherDataAry["real_discount_amount"]);
            }

            $data['billingAddressDetail'] = $billingAddressData;
            $data['companyAddress'] = $companyAddress;
            $data['companyContact'] = $companyContactList;
            $data['taxPercentage'] = $taxPercentage;
            $data['invoiceDetail'] = $invoiceDetail;
            $data['clientDetail'] = $clientDetail;
            $data['packageList'] =  $invOrder;
            $data['issueDOAllowed'] = $issueDOFlag;
            if($voucherData) $data["voucherData"] = $voucherData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language] /* Successfully retrieved */, 'data' => $data);
        }

        public function getSODetail($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $SaleID = trim($params['SaleID']);

            if(!$SaleID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language], 'data'=> "");
            }

            // Company address
            $db->where('name', 'pickUpOrigins');
            $companyAddress = $db->getValue('system_settings', 'reference');
            
            // Company Contact No/ Email/ Fax
            $db->where('type', 'companyContact');
            $res = $db->getValue('system_settings', 'value');
            $companyContactList = json_decode($res, true);

            // Company Address
            $db->where('type', 'companyAddressActual');
            $res = $db->getValue('system_settings', 'value');
            $companyAddressList = json_decode($res, true);

            $db->where("id", $SaleID);
            $invoiceDetail = $db->getOne("sale_order","id, client_id, 
                                        created_at AS createdAt, payment_method AS paymentMethod, 
                                        delivery_method AS deliveryMethod, shipping_address, billing_address, 
                                        payment_amount AS paymentAmount, payment_tax AS paymentTax, 
                                        shipping_fee AS shippingfee, status");

            // $db->where("inv_order_id", $invoiceDetail['id']);
            // $db->where("status",array("Cancel"),"NOT IN");
            // $deliveryOrderRes = $db->get('inv_delivery_order', null, 'inv_order_id, tracking_number');
            // foreach ($deliveryOrderRes as $deliveryOrderRow) {
            //     if($deliveryOrderRow['tracking_number']){
            //         $trackingNo[] = $deliveryOrderRow['tracking_number'];
            //     }
            // }

            $data['showInsuranceTax'] = 0;
            // $invoiceDetail['insuranceTax'] = Setting::setDecimal($invoiceDetail['insuranceTax']);
            // if($invoiceDetail['insuranceTax'] > 0) $data['showInsuranceTax'] = 1;

            $invoiceDetail['createdAt'] = date($dateTimeFormat, strtotime($invoiceDetail["createdAt"]));
            $invoiceDetail['paymentAmount'] = Setting::setDecimal($invoiceDetail['paymentAmount']);
            $invoiceDetail['paymentTax'] = Setting::setDecimal($invoiceDetail['paymentTax']);
            $invoiceDetail['shippingfee'] = Setting::setDecimal($invoiceDetail['shippingfee']);
            $invoiceDetail['paymentMethod'] = $invoiceDetail['paymentMethod'];
            $invoiceDetail['deliveryMethod'] = $invoiceDetail['deliveryMethod'];
            $invoiceDetail['shipping_address'] = $invoiceDetail['shipping_address'];
            $invoiceDetail['billing_address'] = $invoiceDetail['billing_address'];

            // $invoiceDetail['trackingNo'] = $trackingNo ? implode(', ', $trackingNo) : '-';

            $addressIDAry = array($invoiceDetail['shipping_address'], $invoiceDetail['billing_address']);

            if($addressIDAry){
                $db->where("id", $addressIDAry, "IN" );
                $addressDetailAry= $db->map('id')->get("address", NULL,"id, name, address, district_id AS districtID, sub_district_id AS subDistrictID, post_code AS postCodeID, city AS cityID, state_id AS stateID, country_id AS countryID, email, phone, address_type");
            }

            foreach ($addressDetailAry as $addressRow) {
                $stateAry[$addressRow['stateID']] = $addressRow['stateID'];
                $countryAry[$addressRow['countryID']] = $addressRow['countryID'];
                // $countyAry[$addressRow['districtID']] = $addressRow['districtID'];
                // $subCountyAry[$addressRow['subDistrictID']] = $addressRow['subDistrictID'];
                $postCodeAry[$addressRow['postCodeID']] = $addressRow['postCodeID'];
                $cityAry[$addressRow['cityID']] = $addressRow['cityID'];
            }

            if($stateAry){
                $db->where("id", $stateAry, "IN");
                $stateRes = $db->map('id')->get("state", NULL,"id, name, translation_code");
            }

            if($countryAry){
                $db->where("id", $countryAry, "IN");
                $countryRes = $db->map('id')->get("country", NULL,"id, name, translation_code, country_code");
            }

            if($countyAry){
                $db->where("id", $countyAry, "IN");
                $countyRes = $db->map('id')->get("county", NULL,"id, name, translation_code");
            }

            if($subCountyAry){
                $db->where("id", $subCountyAry, "IN");
                $subCountyRes = $db->map('id')->get("sub_county", NULL,"id, name, translation_code");
            }

            if($postCodeAry){
                $db->where("id", $postCodeAry, "IN");
                $postCodeRes = $db->map('id')->get("zip_code", NULL,"id, name, translation_code");
            }

            // if($cityAry){
            //     $db->where("id", $cityAry, "IN");
            //     $cityRes = $db->map('id')->get("city", NULL,"id, name, translation_code");
            // }

            foreach ($addressDetailAry as $addressRow) {
                unset($addressDetail);
                $addressDetail['fullname'] = $addressRow['name'];
                $addressDetail['address'] = $addressRow['address'];
                // $addressDetail['district'] = $countyRes[$addressRow['districtID']]['name'];
                // $addressDetail['subDistrict'] = $subCountyRes[$addressRow['subDistrictID']]['name'];
                $addressDetail['postCode'] = $postCodeRes[$addressRow['postCodeID']]['name'];
                $addressDetail['city'] = $addressRow['cityID'];
                // $addressDetail['email'] = $addressRow['email'];
                $addressDetail['phone'] = $addressRow['phone'];
                $addressDetail['state'] = $stateRes[$addressRow['stateID']]['name'];
                $addressDetail['stateDisplay'] = $stateRes[$addressRow['stateID']]['name'];
                $addressDetail['country'] = $countryRes[$addressRow['countryID']]['name'];
                $addressDetail['dialingArea'] = $countryRes[$addressRow['countryID']]['country_code'];
                $addressDetail['countryDisplay'] = $translations[$countryRes[$addressRow['countryID']]['translation_code']][$language];

                $addressDetailRes[$addressRow['id']] = $addressDetail;
            }

            $deliveryAddressData = $addressDetailRes[$invoiceDetail['shipping_address']];
            $billingAddressData = $addressDetailRes[$invoiceDetail['billing_address']];

            // client table:  Member ID, Full Name
            $db->where("id", $invoiceDetail['client_id']);
            $clientDetail = $db->getOne("client","member_id AS memberID, name");
            
            unset($invoiceDetail['delivery_add_id']);
            unset($invoiceDetail['billing_add_id']);
            unset($invoiceDetail['client_id']);

            // $db->where("inv_order_id", $invoiceDetail['id']);
            // $db->orderBy("mlm_product_id", "ASC");
            // $invOrderList = $db->get('inv_order_detail', NULL ,'mlm_product_id AS packageDisplay, inv_product_id AS productDisplay, price AS packagePrice, weight, quantity, stock_quantity AS stockQuantity, left_stock_quantity AS quantityLeft, pv_price as orderPvPrice');

            // foreach ($invOrderList as $invOrderRow) {
            //     $invProductIDAry[$invOrderRow["productDisplay"]] = $invOrderRow["productDisplay"]; // product
            //     $mlmProductIDAry[$invOrderRow["packageDisplay"]] = $invOrderRow["packageDisplay"]; // package

            //     $mlmInvOrderPv[$invOrderRow["packageDisplay"]] = $invOrderRow["orderPvPrice"] * $invOrderRow["quantity"];
            // }

            $db->where("a.deleted", 0);
            $db->where("sale_id", $invoiceDetail['id']);
            $db->orderBy("product_template_id", "ASC");
            $db->join('product_template b', 'b.id = a.product_template_id', 'LEFT');
            $invOrderList = $db->get('sale_order_detail a', NULL ,'a.sale_id AS packageDisplay, a.product_template_id AS productDisplay, a.item_name, a.product_id, a.item_price AS packagePrice, a.quantity, a.subtotal as Total, product_attribute_value_id');

            $product_attribute_value = $db->get('product_attribute_value a', null, 'id,name');

            $subTotal =0;
            foreach ($invOrderList as $invOrderRow) {
                $invProductIDAry[$invOrderRow["productDisplay"]] = $invOrderRow["productDisplay"]; // product
                $mlmProductIDAry[$invOrderRow["packageDisplay"]] = $invOrderRow["packageDisplay"]; // package
                $subTotal = bcadd($subTotal, $invOrderRow["Total"],2);
                $mlmInvOrderPv[$invOrderRow["packageDisplay"]] = $subTotal;
            }

            $invoiceDetail['subtotal'] = $subTotal;

            if($invProductIDAry){
                // product_template
                $db->where("a.id", $invProductIDAry, "IN");
                $db->join('product b', 'a.product_id = b.id', 'LEFT');
                $productNameRes = $db->map('temp_id')->get('product_template a', NULL ,'a.id as temp_id, b.id as id, b.name');
            }

            if($mlmProductIDAry){
                // package
                $db->where("module_id", $mlmProductIDAry, "IN");
                $db->where("module", "mlm_product");
                $db->where("type", "name");
                $db->where("language", $language);
                $packageLangRes = $db->get('inv_language', NULL ,'module_id, language, content');

                foreach ($packageLangRes as $langRow) {
                    $packageLang[$langRow['module_id']] = $langRow['content'];
                }

                // $db->where("product_id", $mlmProductIDAry, "IN");
                // $packagePriceAry = $db->map('product_id')->get('mlm_product_price', NULL ,'product_id,price'); // package price

                $db->where("id", $mlmProductIDAry, "IN");
                $pvPriceAry = $db->map("id")->get('mlm_product', NULL ,'id, weight, pv_price AS pvPrice');
            }
            unset($currentPackageID);
            $index = 0;
            foreach ($invOrderList as $invOrderRow) {
                // unset($invOrderData);
                // unset($productListData);
                // if($currentPackageID != $invOrderRow['packageDisplay']){
                //     unset($productList);
                //     unset($totalProductWeight);
                // }
                // $invOrderData['package'] = $mlmProductIDAry[$invOrderRow['packageDisplay']];
                $invOrderData['packageDisplay'] = $invOrderRow['item_name'];
                $invOrderData['packagePrice'] = Setting::setDecimal($invOrderRow['packagePrice']);
                $invOrderData['totalPackagePrice'] = Setting::setDecimal($invOrderRow['Total']);
                //$invOrderData['pvPrice'] = Setting::setDecimal($pvPriceAry[$invOrderRow['packageDisplay']]["pvPrice"]);
                // $invOrderData['pvPrice'] = Setting::setDecimal($mlmInvOrderPv[$invOrderRow['packageDisplay']]);

                $invOrderData['packageQuantity'] = Setting::setDecimal($invOrderRow['quantity']);
                // $productListData['productDisplay'] = $productNameRes[$invOrderRow['productDisplay']];
                // $productListData['productID'] = $invOrderRow['productDisplay'];

                // $productListData['stockQuantity'] = Setting::setDecimal($invOrderRow['stockQuantity']);
                // $productListData['quantityLeft'] = Setting::setDecimal($invOrderRow['quantityLeft']);
                // $productList[] = $productListData;
                // $invOrderData["productList"] = $productList;
                // $currentPackageID = $invOrderRow['packageDisplay'];


                $string = $invOrderRow['product_attribute_value_id'];  
                $array = json_decode($string);
                $string = implode(",", $array);
                $invOrderRow['product_attribute_value_id'] = $string;

                $name_array = array();
                foreach ($array as $id) {
                    foreach ($product_attribute_value as $value) {
                        if ($value['id'] == $id) {
                        $name_array[] = $value['name']; // Store name in array
                        }
                    }
                }
                $name_string = implode(", ", $name_array);
                $invOrderData['product_attribute_name'] = $name_string;

                $invOrder[$index] = $invOrderData;
                $index++; 
            }

            $issueDOFlag = 0;
            if($leftStockQuantity > 0){
                $issueDOFlag = 1;
            }

            $db->where("type","SST");
            $db->orderBy("created_at","DESC");
            $taxPercentage = $db->getValue("inv_tax_charges","rate");
            if(!$taxPercentage) $taxPercentage = 0;

            // assign data into data
            if($invoiceDetail['deliveryMethod'] == "pickup"){
                $data['deliveryAddressDetail']['pickUpAddress'] = $companyAddress;
            }else{
                $data['deliveryAddressDetail'] = $deliveryAddressData;
            }

            $db->where("inv_order_id",$invoiceDetail["id"]);
            $voucherDataAry = $db->getOne("inv_order_voucher","inv_voucher_id,type,discount_type,discount_percentage,discount_amount,real_discount_amount");

            // if($voucherDataAry){
            //     unset($voucherData);
            //     $db->where("id",$voucherDataAry["inv_voucher_id"]);
            //     $voucherData["voucherCode"] = $db->getValue("inv_voucher","code");
            //     $voucherData["voucherType"] = $voucherDataAry["type"];
            //     $voucherData["discountType"] = $voucherDataAry["discount_type"];
            //     switch($voucherDataAry["discount_type"]){
            //         case "percentage":
            //             $voucherData["discountPercentage"] = Setting::setDecimal($voucherDataAry["discount_percentage"]);
            //             $voucherData["maxDiscountAmount"] = Setting::setDecimal($voucherDataAry["discount_amount"]);
            //             break;

            //         case "amount":
            //             $voucherData["discountAmount"] = Setting::setDecimal($voucherDataAry["discount_amount"]);
            //             break;
            //     }
            //     $voucherData["realDiscountAmount"] = Setting::setDecimal($voucherDataAry["real_discount_amount"]);
            // }

            $data['billingAddressDetail'] = $billingAddressData;
            $data['companyAddress'] = $companyAddress;
            $data['companyAddressAct'] = $companyAddressList;
            $data['companyContact'] = $companyContactList;
            $data['taxPercentage'] = $taxPercentage;
            $data['invoiceDetail'] = $invoiceDetail;
            $data['clientDetail'] = $clientDetail;
            $data['packageList'] =  $invOrder;
            $data['subtotal'] =  $mlmInvOrderPv;
            $data['issueDOAllowed'] = $issueDOFlag;
            // if($voucherData) $data["voucherData"] = $voucherData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language] /* Successfully retrieved */, 'data' => $data);
        }

        public function issueDO($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $invOrderID = $params["invOrderID"];
            $productAry = $params["productAry"];
            $remark     = $params["remark"];
            $todayDate  = date("Y-m-d H:i:s");
            $subject    = "Pending";

            $userID = $db->userID;
            $site = $db->userType;


            if(!$invOrderID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
            }else{
                $db->where('id', $invOrderID);
                $invOrder = $db->getOne('inv_order', 'id, client_id, delivery_option, delivery_add_id, courier_company, service_id, total_price,reference_number');

                if(!$invOrder){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
                }
            }

            $db->where('inv_order_id', $invOrderID);
            $invOrderDetail = $db->get('inv_order_detail', null, 'mlm_product_id, inv_product_id, left_stock_quantity, quantity, price');

            $priceDetail += 0;
            $quantityDetail += 0;

            foreach ($invOrderDetail as $detailRow) {
                $invProductAry[$detailRow['mlm_product_id']][$detailRow['inv_product_id']] = Setting::setDecimal($detailRow['left_stock_quantity']);

                $productIDAry[$detailRow['inv_product_id']] = $detailRow['inv_product_id'];

                $priceDetail += $detailRow['price'];
                $quantityDetail += $detailRow['quantity'];
            }

            if($productIDAry){
                $db->where('id', $productIDAry, 'IN');
                $productWeightAry = $db->map('id')->get('inv_product', null, 'id, weight');
            }

            foreach ($invProductAry as $packageID => $productDetail) {

                foreach ($productAry as $key => $paramsProduct) {
                    $key = $key+1;

                    if($paramsProduct['packageID'] == $packageID){

                        foreach ($productDetail as $productID => $detailRow) {

                            if(!$paramsProduct['productID']){
                                $errorFieldArr[] = array(
                                                'id'  => "product".$packageID.'_'.$productID."Error",
                                                'msg' => $translations['E00955'][$language] /* Invalid Product */
                                            );
                            }

                            if((!$paramsProduct['quantity'] || $paramsProduct['quantity'] <= 0 || !is_numeric($paramsProduct['quantity']) ) && ($paramsProduct['productID'] == $productID)) {
                                $errorFieldArr[] = array(
                                            'id'  => "quantity".$packageID.'_'.$productID."Error",
                                            'msg' => $translations['E00941'][$language] /* Quantity must be greater than 0 */
                                        );
                            }

                            if(($detailRow < $paramsProduct['quantity']) && ($paramsProduct['productID'] == $productID)){
                                $errorFieldArr[] = array(
                                            'id'  => "quantity".$packageID.'_'.$productID."Error",
                                            'msg' => $translations['E01120'][$language]
                                        );
                            }
                        }

                        $productWeight += $productWeightAry[$paramsProduct['productID']] * $paramsProduct['quantity'];

                    }
                }
            }

            foreach ($productAry as $productRow) {
                $product[$productRow['productID']] += $productRow['quantity'];

                $productRes[$productRow['packageID']][$productRow['productID']] = $productRow['quantity'];
            }

            $db->where('inv_product_id', array_keys($product), 'IN');
            $invStockRes = $db->get('inv_stock', null, '*');

            foreach ($invStockRes as $invStockRow) {
                $invStock[$invStockRow['inv_product_id']] += Setting::setDecimal($invStockRow['stock_in'] - $invStockRow['stock_out']);
            }

            unset($productID);
            foreach ($invStock as $invProductID => $stockBalance) {

                foreach ($productRes as $packageID => $productDetail) {

                    foreach ($productDetail as $productID => $quantity) {

                        if($invProductID == $productID && $stockBalance < $quantity){
                            $errorFieldArr[] = array(
                                                'id'  => "quantity".$packageID.'_'.$productID."Error",
                                                'msg' => $translations['E01089'][$language] /* Insufficient stock balance. */
                                            );
                        }
                    }
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            if($invOrder['courier_company'] == "ONDELIVERY" && $invOrder['delivery_option'] == 'delivery'){
                $companyName    = Setting::$configArray["companyName"];
                $companyInfo    = Setting::$systemSetting["Company Contact"];
                $companyInfo    = json_decode($companyInfo, true);
                $companyContact = $companyInfo['contactNo'];

                $db->where('name', 'pickUpOrigins');
                $companyAddress = $db->getValue('system_settings', 'reference');

                $db->where('id', $invOrder['delivery_add_id']);
                $address = $db->getOne('address', '*');

                $db->where('id', $address['country_id']);
                $country = $db->getOne('country', 'name, country_code');

                $db->where('id', $address['state_id']);
                $state = $db->getValue('state', 'name');

                $db->where('id', $address['city_id']);
                $city = $db->getValue('city', 'name');

                $db->where('id', $address['district_id']);
                $district = $db->getValue('county', 'name');

                $db->where('id', $address['sub_district_id']);
                $subDistrict = $db->getValue('sub_county', 'name');

                $db->where('id', $address['post_code_id']);
                $zipCode = $db->getOne('zip_code', 'name, destination_id');

                $receiverAddress = $address['address'].','.$subDistrict.','.$district.','.$city.','.$state.','.$zipCode['name'].','.$country['name'];

                $productWeight = number_format($productWeight);

                $waybillParams = array(
                    'destID' => $zipCode['destination_id'],
                    'serviceID' => $invOrder['service_id'],
                    'senderName' => $companyName,
                    'senderPhone' => $country['country_code'].$companyContact,
                    'senderAddress' => $companyAddress,
                    'receiverName' => $address['name'],
                    'receiverPhone' => $country['country_code'].$address['phone'],
                    'receiverAddress' => $receiverAddress,
                    'goodsID' => 28,
                    'goodsWeight' => ($productWeight < 1) ? 1 : $productWeight,
                );

                $trackingNoRes = self::thirdPartyCreateWaybill($waybillParams);
                if($trackingNoRes['status'] != 'ok'){
                    return $trackingNoRes;
                }
                $trackingNo = $trackingNoRes['data']['trackingNo'];

            }elseif($invOrder['courier_company'] == 'JNE' && $invOrder['delivery_option'] == 'delivery'){
                $companyName    = Setting::$configArray["companyName"];
                $companyInfo    = Setting::$systemSetting["Company Contact"];
                $companyInfo    = json_decode($companyInfo, true);
                $companyContact = $companyInfo['contactNo'];

                $db->where('name', 'pickUpOrigins');
                $companyAddress = $db->getValue('system_settings', 'reference');

                $db->where('id', $invOrder['delivery_add_id']);
                $address = $db->getOne('address', '*');

                $db->where('id', $address['country_id']);
                $country = $db->getOne('country', 'name, country_code');

                $db->where('id', $address['state_id']);
                $state = $db->getValue('state', 'name');

                $db->where('id', $address['city_id']);
                $city = $db->getValue('city', 'name');

                $db->where('id', $address['district_id']);
                $district = $db->getValue('county', 'name');

                $db->where('id', $address['sub_district_id']);
                $subDistrict = $db->getValue('sub_county', 'name');

                $db->where('id', $address['post_code_id']);
                $zipCode = $db->getOne('zip_code', 'name, destination_id');

                $productWeight = number_format($productWeight);

                $waybillParams = array(
                    'invID' => $invOrder['reference_number'],
                    'totalAmount' => $invOrder['total_price'],
                    'quantity' => $quantityDetail,
                    'price' => $priceDetail,
                    'senderName' => $companyName,
                    'senderPhone' => $country['country_code'].$companyContact,
                    'senderAddress' => $companyAddress,
                    'receiverName' => $address['name'],
                    'receiverPhone' => $country['country_code'].$address['phone'],
                    'receiverAddress' => $address['address'],
                    'receiverSubDistrict' => $subDistrict,
                    'receiverDistrict' => $district,
                    'city' => $city,
                    'countryCode' => $zipCode['name'],
                    'countryName' => $country['name'],
                    'state' => $state,
                    'goodsID' => 28,
                    'goodsWeight' => ($productWeight < 1) ? 1 : $productWeight,

                );

                $trackingNoRes = self::thirdPartyCreateJneWaybill($waybillParams);
                if($trackingNoRes['status'] != 'ok'){
                    return $trackingNoRes;
                }
                $trackingNo = $trackingNoRes['data']['trackingNo'];
            }

            $db->startTransaction();

            try{
                $getDONumberFormat = DATE('y').'/DO/FIZ/'.DATE('m').'/';

                $db->where('reference_number', $getDONumberFormat.'%', 'LIKE');
                $maxID = $db->getValue('inv_delivery_order', 'MAX(id)');

                $db->where('id', $maxID);
                $getLatestNumber = $db->getValue('inv_delivery_order', "reference_number");
                if($getLatestNumber){
                    $getDONumber = explode("/", $getLatestNumber);
                    $doNumber = $getDONumberFormat.str_pad(end($getDONumber)+1, 4, '0', STR_PAD_LEFT);
                }else{
                    $doNumber = $getDONumberFormat.'0001';
                }
                // $doNumber = General::generateDynamicCode('DO', 8, 'inv_delivery_order', 'reference_number', true);
                $batchID = $db->getNewID();

                $insertDeliveryOrder = array(
                    'inv_order_id'      =>  $invOrderID,
                    'reference_number'  =>  $doNumber,
                    'tracking_number'   =>  $trackingNo ? : '',
                    'status'            =>  $subject,
                    'batch_id'          =>  $batchID,
                    'remark'            =>  $remark,
                    'created_at'        =>  $todayDate,
                    'creator_id'        =>  $userID
                );
                $invDeliveryOrderID = $db->insert('inv_delivery_order', $insertDeliveryOrder);

                $result = self::insertInvStockTransaction($productRes, $invOrder['client_id'], 'Issue DO', '', $batchID, '', $invOrder['delivery_option']);

                if(!$result){
                    $db->rollback();
                    $db->commit();
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01116"][$language], 'data' => $data);
                }

                unset($productDetail);
                foreach ($result as $packageID => $packageDetail) {

                    foreach ($packageDetail as $productID => $productDetail) {

                        foreach ($productDetail as $stockID => $quantity) {
                            $insertDeliveryOrderDetail = array(
                                'inv_delivery_order_id' => $invDeliveryOrderID,
                                'mlm_product_id'        => $packageID,
                                'inv_product_id'        => $productID,
                                'inv_stock_id'          => $stockID,
                                'quantity'              => $quantity,
                            );
                            $db->insert('inv_delivery_order_detail', $insertDeliveryOrderDetail);

                            $updateBalance = array(
                                'left_stock_quantity' => $db->dec($quantity)
                            );
                            $db->where('inv_order_id', $invOrderID);
                            $db->where('mlm_product_id', $packageID);
                            $db->where('inv_product_id', $productID);
                            $db->update('inv_order_detail', $updateBalance);
                        }
                    }
                }

                unset($invStockRes);
                unset($invStock);

                $db->where('inv_product_id', array_keys($product), 'IN');
                $invStockRes = $db->get('inv_stock', null, 'inv_product_id, stock_in, stock_out');

                $getQuantity = General::getSystemSettingAdmin('lowStockQuantity');
                $getLowQuantity = $getQuantity['lowStockQuantity']['value'];

                foreach ($invStockRes as $invStockRow) {
                    $invStock[$invStockRow['inv_product_id']] += Setting::setDecimal($invStockRow['stock_in'] - $invStockRow['stock_out']);
                }

                foreach($invStock as $idKey => $rowStock){
                    $db->where('id', $idKey);
                    $checkAlertDate = $db->getValue('inv_product', 'alert_at');
                    if($checkAlertDate < 0){
                        if($rowStock < $getLowQuantity && $rowStock > 0){
                            $insertInvProduct = array(
                                "alert_at" => $todayDate,
                            );
                            $db->where('id', $idKey);
                            $db->update('inv_product', $insertInvProduct);
                        }
                    }else{
                        if ($rowStock <= 0){
                            $insertInvProduct = array(
                                "alert_at" => $todayDate,
                            );
                            $db->where('id', $idKey);
                            $db->update('inv_product', $insertInvProduct);
                        }
                    }
                }

            }catch(Exception $e){
                $db->rollback();
                $db->commit();
                return array("status" => "error", "code" => 2, "statusMsg" => "System Error", "data" => "");
            }

            $db->commit();

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00370"][$language], 'data' => "");
        }

        public function cancelDO($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $deliveryOrderID = $params["deliveryOrderID"];
            $remark         = $params["remark"];
            $todayDate      = date("Y-m-d H:i:s");

            $userID = $db->userID;
            $site = $db->userType;

            if(!$deliveryOrderID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
            }else{
                $db->where('id', $deliveryOrderID);
                $deliveryOrder = $db->getOne('inv_delivery_order', 'inv_order_id, tracking_number, status, batch_id');

                if(!$deliveryOrder){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
                }
            }

            $db->where('id', $deliveryOrder['inv_order_id']);
            $invOrder = $db->getOne('inv_order', 'client_id, delivery_option, courier_company');

            if($invOrder['courier_company'] == "ONDELIVERY" && in_array($deliveryOrder['status'], array('Pending'))){
                $waybillParams = array(
                    'trackingNo' => $deliveryOrder['tracking_number'],
                    'notes' => $remark,
                );

                $cancelDO = self::thirdPartyCancelWaybill($waybillParams);

                if($cancelDO != "success"){
                    $data["errorMsg"] = $cancelDO;
                    return array("status" => "error", "code" => 2, "statusMsg" => "Failed to cancel this DO", "data" => $data);
                }

                $db->where('inv_delivery_order_id', $deliveryOrderID);
                $deliveryDetailRes = $db->get('inv_delivery_order_detail', null, 'mlm_product_id, inv_product_id, inv_stock_id, quantity');

                foreach ($deliveryDetailRes as $deliveryDetailRow) {
                    $productRes[$deliveryDetailRow['mlm_product_id']][$deliveryDetailRow['inv_product_id']][$deliveryDetailRow["inv_stock_id"]] = $deliveryDetailRow['quantity'];
                }

                $batchID = $deliveryOrder['batch_id'];

                $updateDeliveryOrder = array(
                    'status' => 'Cancel',
                    'updated_at' => $todayDate,
                    'updater_id' => $userID,
                );
                $db->where('id', $deliveryOrderID);
                $invDeliveryOrderID = $db->update('inv_delivery_order', $updateDeliveryOrder);

                $result = self::insertInvStockTransaction($productRes, $invOrder['client_id'], 'Cancel DO', '', $batchID, $remark, 'cancelDelivery');

                if(!$result){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01116"][$language], 'data' => $data);
                }

                unset($productDetail);
                foreach ($result as $packageID => $packageDetail) {

                    foreach ($packageDetail as $productID => $productDetail) {

                        foreach ($productDetail as $stockID => $quantity) {
                            $updateBalance = array(
                                'left_stock_quantity' => $db->inc($quantity)
                            );
                            $db->where('inv_order_id', $deliveryOrder['inv_order_id']);
                            $db->where('mlm_product_id', $packageID);
                            $db->where('inv_product_id', $productID);
                            $db->update('inv_order_detail', $updateBalance);
                        }
                    }
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'DO successfully cancel.', 'data' => "");

            }else{

                return array('status' => "error", 'code' => 2, 'statusMsg' => "This DO not allow to cancel", 'data' => "");
            }
        }

        public function updateDeliveryOrder($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateTime = date('Y-m-d H:i:s');

            $userID = $db->userID;
            $site = $db->userType;

            $deliveryOrderID = trim($params['deliveryOrderID']);
            $trackingNo = trim($params['trackingNo']);

            if(!$deliveryOrderID){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
            }else{
                $db->where('id', $deliveryOrderID);
                $deliveryOrder = $db->getOne('inv_delivery_order', 'id, inv_order_id, tracking_number');

                if(!$deliveryOrder){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language] /* Invalid ID */, 'data' => "");
                }
            }

            $db->where('id', $deliveryOrder['inv_order_id']);
            $invOrder = $db->getOne('inv_order', 'courier_company, delivery_option');
            $courierCompany = $invOrder['courier_company'];
            $deliveryOption = $invOrder['delivery_option'];

            if($courierCompany == "ONDELIVERY"){
                $errorFieldArr[] = array(
                                            'id'  => "trackingNoError",
                                            'msg' => 'This order cannot be update'
                                        );
            }

            $currentTrackingNo = $deliveryOrder['tracking_number'];

            if(!$trackingNo){
                $errorFieldArr[] = array(
                                            'id'  => "trackingNoError",
                                            'msg' => $translations['E00953'][$language] /* Please fill in tracking number */
                                        );
            }elseif($currentTrackingNo){
                if(!$trackingNo){
                    $errorFieldArr[] = array(
                                            'id'  => "trackingNoError",
                                            'msg' => $translations['E00953'][$language] /* Please fill in tracking number */
                                        );
                }
            }else{
                $db->where('tracking_number', $trackingNo);
                $duplicatedTrackNo = $db->has('inv_delivery_order');

                if($duplicatedTrackNo){
                    $errorFieldArr[] = array(
                                                'id'  => "trackingNoError",
                                                'msg' => $translations['E01118'][$language]
                                            );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            $updateData = array(
                "tracking_number"   =>  $trackingNo,
            );
            $db->where('id', $deliveryOrder['id']);
            $db->update('inv_delivery_order', $updateData);

            if(in_array($userID,$checkAdminRoleRes)){
                $invLog = array(
                        "module"                    =>  "inv_delivery_order",
                        "module_id"                 =>  $deliveryOrder['id'],
                        "title_transaction_code"    =>  "T00064",
                        "title"                     =>  "Update Delivery Order",
                        "transaction_code"          =>  "L00087",
                        "data"                      =>  json_encode(array("admin"=>$checkAdmin)),
                        "creator_type"              =>  $site,
                        "creator_id"                =>  $userID,
                        "created_at"                =>  $dateTime,
                );
                $db->insert("inv_log", $invLog);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language] /* Update Successful. */, 'data' => "");
        }

        public function getDeliveryOrderListing($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();

            if($seeAll == 1){
                $limit = null;
            }

            //filter
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                $db->where('Date(created_at)', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }

                                $db->where('Date(created_at)', date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            break;
                        case 'invoiceNo':
                            $invoiceNoAry = $db->subQuery();
                            $invoiceNoAry->where("reference_number", $dataValue);
                            $invoiceNoAry->get("inv_order", null, "id");
                            $db->where("inv_order_id", $invoiceNoAry, "IN");

                            break;
                        case 'purchaseOrderNo':
                            $purchaseOrderAry = $db->subQuery();
                            $purchaseOrderAry->where("po_number", $dataValue);
                            $purchaseOrderAry->get("inv_order", null, "id");
                            $db->where("inv_order_id", $purchaseOrderAry, "IN");

                            break;
                        case 'memberID':
                            $sq1 = $db->subQuery();
                            $sq1->where('member_id', $dataValue);
                            $sq1->getOne('client', "id");

                            $sq2 = $db->subQuery();
                            $sq2->where('client_id', $sq1);
                            $sq2->get('inv_order', null, "id");
                            $db->where("inv_order_id", $sq2, "IN");

                            break;
                        case 'fullname':
                            if($dataType == "like"){
                                $sq = $db->subQuery();
                                $sq->where("name", "%".$dataValue."%","LIKE");
                                $sq->get("address", null, "client_id");
                            }else{
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->get("address", null, "client_id");
                            }

                            $sq2 = $db->subQuery();
                            $sq2->where("client_id", $sq, "IN");
                            $sq2->get("inv_order", null, "id");
                            $db->where("inv_order_id", $sq2, "IN");

                            break;
                        case 'deliveryOption':
                            $sq1 = $db->subQuery();
                            $sq1->where('delivery_option', $dataValue);
                            $sq1->get('inv_order', null, "id");
                            $db->where("inv_order_id", $sq1, "IN");

                            break;
                        case 'status':
                            $db->where("status", $dataValue);

                            break;

                        case 'deliveryOrderNo':
                            $db->where("reference_number", $dataValue);
                            break;
                    }

                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDB = $db->copy();

            $db->orderBy("created_at", "DESC");
            $deliveryOrderList = $db->get("inv_delivery_order", $limit, "id, created_at AS date, inv_order_id, status, creator_id, remark, reference_number");

            if(empty($deliveryOrderList)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            foreach ($deliveryOrderList as $deliveryOrderRow) {
                $invOrderIDAry[$deliveryOrderRow['inv_order_id']] = $deliveryOrderRow['inv_order_id'];
                $creatorIDAry[$deliveryOrderRow['creator_id']] = $deliveryOrderRow['creator_id'];
            }

            if($invOrderIDAry){
                $db->where("id",$invOrderIDAry, "IN");
                $invOrderIDRes = $db->map("id")->get("inv_order", NULL, "id, reference_number AS invoiceNo, po_number AS purchaseOrderNo, (SELECT member_id FROM client WHERE id = client_id) AS memberID, (SELECT name FROM client WHERE id = client_id) AS fullName, client_id, delivery_option, courier_company, courier_service");

                foreach ($invOrderIDRes as $invOrderIDRow) {
                    $memberIDAry[] = $invOrderIDRow['client_id'];
                }

                $db->where("client_id",$memberIDAry, "IN");
                $addressAry = $db->map("client_id")->get("address", NULL,"client_id, name");

                $db->where("inv_order_id",$invOrderIDAry, "IN");
                $paymentIDAry = $db->map("inv_order_id")->get("inv_order_payment", NULL, "inv_order_id, credit_type");
                foreach ($paymentIDAry as $creditRow) {
                    $creditNameAry[$creditRow] = $creditRow;
                }
                if($creditNameAry){
                    $db->where("type",$creditNameAry, "IN");
                    $creditLang = $db->map("type")->get("credit", NULL, "type, translation_code");
                }
            }

            if($creatorIDAry){
                $db->where("id",$creatorIDAry, "IN");
                $creatorRes = $db->map("id")->get("admin", NULL, "id, username");
            }

            foreach ($deliveryOrderList as $deliveryOrderRow) {
                unset($tempDeliveryOrder);
                $courierCompany = $invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['courier_company'];

                $tempDeliveryOrder["doID"] = $deliveryOrderRow["id"];
                $tempDeliveryOrder["date"] = date($dateTimeFormat, strtotime($deliveryOrderRow["date"]));
                $tempDeliveryOrder["invoiceNo"] = $invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['invoiceNo'];
                $tempDeliveryOrder["purchaseOrderNo"] = $invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['purchaseOrderNo'];
                $tempDeliveryOrder['deliveryOrderNo'] = $deliveryOrderRow['reference_number']?:'-';
                $tempDeliveryOrder["memberID"] = $invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['memberID'];
                $tempDeliveryOrder["fullname"] = $invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['fullName'];
                $tempDeliveryOrder["paymentMethod"] = $paymentIDAry[$deliveryOrderRow["inv_order_id"]] ? $translations[$creditLang[$paymentIDAry[$deliveryOrderRow["inv_order_id"]]]][$language] : '-';
                $tempDeliveryOrder["deliveryOption"] = General::getTranslationByName($invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['delivery_option']);
                $tempDeliveryOrder["courierService"] = $invOrderIDRes[$deliveryOrderRow["inv_order_id"]]['courier_service'];
                $tempDeliveryOrder["status"] = $deliveryOrderRow["status"];
                $tempDeliveryOrder["statusDisplay"] = General::getTranslationByName($deliveryOrderRow["status"]);
                $tempDeliveryOrder["remark"] = $deliveryOrderRow["remark"]? $deliveryOrderRow["remark"]: "-";
                $tempDeliveryOrder["issuedBy"] = $creatorRes[$deliveryOrderRow["creator_id"]];

                $tempDeliveryOrder["cancelAllowed"] = 0;
                if($courierCompany == "ONDELIVERY" && (in_array($deliveryOrderRow["status"],array("Pending")))){
                    $tempDeliveryOrder["cancelAllowed"] = 1;
                }

                $deliveryOrderListing[] = $tempDeliveryOrder;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['deliveryOrderListing'] =  $deliveryOrderListing;
            $totalRecord = $copyDB->getValue('inv_delivery_order', "count(*)");
            $data["pageNumber"] = $pageNumber;
            $data["totalRecord"] = $totalRecord;
            if($seeAll == "1") {
                $data["totalPage"] = 1;
                $data["numRecord"] = $totalRecord;
            } else {
                $data["totalPage"] = ceil($totalRecord/$limit[1]);
                $data["numRecord"] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language] /* Successfully retrieved. */, 'data' => $data);
        }

        public function getDeliveryOrderDetail($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $adminRoleList = Setting::$systemSetting['InvEditableRoles'];
            $adminRolesListAry = explode("#", $adminRoleList);

            $userID = $db->userID;
            $site = $db->userType;

            $db->where('role_id',$adminRolesListAry, 'IN');
            $checkAdminRoleRes = $db->getValue('admin', 'id', null);


            $availableToEdit = 0;
            if(in_array($userID,$checkAdminRoleRes)){
                $availableToEdit = 1;
            }

            $deliveryOrderID = trim($params['deliveryOrderID']);

            if(!$deliveryOrderID) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language], 'data'=> "");
            }

            $db->where("id", $deliveryOrderID);
            $deliveryOrderDetail = $db->getOne("inv_delivery_order", "inv_order_id, created_at AS deliveryOrderDate, reference_number AS deliveryOrderNo, tracking_number AS trackingNumber ");

            if(!$deliveryOrderDetail){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01051"][$language], 'data'=> "");
            }

            // Company address
            $db->where('name', 'pickUpOrigins');
            $companyAddress = $db->getValue('system_settings', 'reference');

            // Company Contact No/ Email/ Fax
            $db->where('type', 'companyContact');
            $res = $db->getValue('system_settings', 'value');
            $companyContactList = json_decode($res, true);

            $db->where("inv_delivery_order_id", $deliveryOrderID);
            $deliveryDetailAry = $db->get("inv_delivery_order_detail", null, "id, inv_delivery_order_id, mlm_product_id, inv_product_id, inv_stock_id, quantity, remark");

            foreach ($deliveryDetailAry as $deliveryDetailRow) {
                $packageIDAry[$deliveryDetailRow['mlm_product_id']] = $deliveryDetailRow['mlm_product_id']; // package
                $productIDAry[$deliveryDetailRow['inv_product_id']] = $deliveryDetailRow['inv_product_id']; // product
                $stockIDAry[$deliveryDetailRow['inv_stock_id']] = $deliveryDetailRow['inv_stock_id']; //stock
            }

            // get invoice date and invoice no,deliveryOrderDate and deliveryOrderNo
            $db->where("id", $deliveryOrderDetail["inv_order_id"]);
            $invoiceDetail = $db->getOne("inv_order", "client_id, reference_number AS invoiceNo, created_at AS createdAt, special_note, remark, delivery_option, billing_add_id, delivery_add_id, courier_company, courier_service");
            $invoiceDetail["createdAt"] = date($dateTimeFormat, strtotime($invoiceDetail["createdAt"]));

            // get memberID
            $db->where("id", $invoiceDetail['client_id']);
            $clientInfo = $db->getOne("client", "member_id, name");


            // get receiverName and phoneNumber, billing address, delivery address
            $addressIDAry = array($invoiceDetail['billing_add_id'],$invoiceDetail['delivery_add_id']);
            if($addressIDAry){
                $db->where("id", $addressIDAry, "IN");
                $db->where("client_id", $invoiceDetail['client_id']);
                $memberDetail= $db->map('id')->get("address", null, "id, name, phone, address, district_id, sub_district_id, post_code, city, state_id, country_id, email, phone, address_type");
            }

            foreach ($memberDetail as $memberDetailRow) {
                $stateAry[$memberDetailRow['state_id']] = $memberDetailRow['state_id'];
                $countryAry[$memberDetailRow['country_id']] = $memberDetailRow['country_id'];
                $countyAry[$memberDetailRow['district_id']] = $memberDetailRow['district_id'];
                $subCountyAry[$memberDetailRow['sub_district_id']] = $memberDetailRow['sub_district_id'];
                $postCodeAry[$memberDetailRow['post_code']] = $memberDetailRow['post_code'];
                $cityAry[$memberDetailRow['city']] = $memberDetailRow['city'];
            }

            if($stateAry){
                $db->where("id", $stateAry, "IN");
                $stateRes = $db->map('id')->get("state", NULL,"id, name");
            }

            if($countryAry){
                $db->where("id", $countryAry, "IN");
                $countryRes = $db->map('id')->get("country", NULL,"id, name, translation_code, country_code");
            }

            if($countyAry){
                $db->where("id", $countyAry, "IN");
                $countyRes = $db->map('id')->get("county", NULL,"id, name, translation_code");
            }

            if($subCountyAry){
                $db->where("id", $subCountyAry, "IN");
                $subCountyRes = $db->map('id')->get("sub_county", NULL,"id, name, translation_code");
            }

            if($postCodeAry){
                $db->where("id", $postCodeAry, "IN");
                $postCodeRes = $db->map('id')->get("zip_code", NULL,"id, name, translation_code");
            }

            if($cityAry){
                $db->where("id", $cityAry, "IN");
                $cityRes = $db->map('id')->get("city", NULL,"id, name, translation_code");
            }

            foreach ($memberDetail as $addressID => $memberDetailRow) {
                unset($addressValue);
                $addressValue["name"] = $memberDetailRow['name'];
                $addressValue["phone"] = $memberDetailRow['phone'];
                $addressValue["email"] = $memberDetailRow['email'];
                $addressValue["address"] = $memberDetailRow['address'];
                $addressValue["districtDisplay"] = $countyRes[$memberDetailRow['district_id']]['name'];
                $addressValue["subDistrictDisplay"] = $subCountyRes[$memberDetailRow['sub_district_id']]['name'];
                $addressValue["postCodeDisplay"] = $postCodeRes[$memberDetailRow['post_code']]['name'];
                $addressValue["cityDisplay"] = $cityRes[$memberDetailRow['city']]['name'];
                $addressValue["stateDisplay"] = $stateRes[$memberDetailRow['state_id']];
                $addressValue["countryDisplay"] = $translations[$countryRes[$memberDetailRow['country_id']]["translation_code"]][$language];
                $addressValue["dialCode"] = $countryRes[$memberDetailRow['country_id']]["country_code"];

                $addressAry[$memberDetailRow['id']] = $addressValue;
            }

            // DO summary
            $db->where("id", $packageIDAry, "IN");
            $mlmPackageRes = $db->map("id")->get("mlm_product", null,"id, name, code"); // map package id

            $db->where("id", $productIDAry, "IN");
            $mlmProductRes = $db->map("id")->get("inv_product", null,"id, name, code"); // map product id

            foreach ($deliveryDetailAry as $deliveryDetailRow) {
                unset($temp);
                $temp['stockCode'] = $mlmProductRes[$deliveryDetailRow['inv_product_id']]['code'];
                $temp['quantity'] = $deliveryDetailRow['quantity'];
                $deliveryOrderList[$deliveryDetailRow['mlm_product_id']][$deliveryDetailRow['inv_product_id']]['packageName'] = $mlmPackageRes[$deliveryDetailRow['mlm_product_id']]['name'];
                $deliveryOrderList[$deliveryDetailRow['mlm_product_id']][$deliveryDetailRow['inv_product_id']]['packageCode'] = $mlmPackageRes[$deliveryDetailRow['mlm_product_id']]['code'];
                $deliveryOrderList[$deliveryDetailRow['mlm_product_id']][$deliveryDetailRow['inv_product_id']]['product'] = $mlmProductRes[$deliveryDetailRow['inv_product_id']]['name'];
                  $deliveryOrderList[$deliveryDetailRow['mlm_product_id']][$deliveryDetailRow['inv_product_id']]['stockList'][$deliveryDetailRow['inv_stock_id']] = $temp;
            }

            if($invoiceDetail['courier_company'] == "ONDELIVERY" && (in_array($deliveryOrderRow["status"],array("Pending")))){
                $cancelAllowed = 1;
            }

            $data['companyAddress'] = $companyAddress;
            $data['companyContact'] = $companyContactList;
            $data['billingAddress'] = $addressAry[$invoiceDetail['billing_add_id']];
            $data['deliveryAddress'] = $addressAry[$invoiceDetail['delivery_add_id']];
            $data['invoiceNo'] = $invoiceDetail['invoiceNo'];
            $data['invoiceDate'] = $invoiceDetail['createdAt'];
            $data["specialNote"] = $invoiceDetail["special_note"];
            $data["remark"] = $invoiceDetail["remark"];
            $data["deliveryOption"] = $invoiceDetail["delivery_option"];
            $data["pickUpAddress"] = $companyAddress;
            $data['deliveryOrderDate'] = date($dateTimeFormat, strtotime($deliveryOrderDetail['deliveryOrderDate']));
            $data['deliveryOrderNo'] = $deliveryOrderDetail['deliveryOrderNo'];
            $data['courierCompany'] = $invoiceDetail['courier_company'];
            $data['courierService'] = $invoiceDetail['courier_service'];
            $data['memberID'] = $clientInfo['member_id'];
            $data['fullname'] = $deliveryAddressAry['name'] ? : $clientInfo['name'];
            $data['dialingArea'] = $deliveryAddressAry['dialCode'];
            $data['phoneNumber'] = $deliveryAddressAry['phone'];
            $data['trackingNumber'] = $deliveryOrderDetail['trackingNumber']? $deliveryOrderDetail['trackingNumber']: "-" ;
            $data['deliveryOrderList'] = $deliveryOrderList;
            $data['availableToEdit'] = $availableToEdit;
            $data['cancelAllowed'] = $cancelAllowed ? : 0;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language] /* Successfully retrieved */, 'data' => $data);
        }

        // -------- PVP Listing (Admin) //PVP Transaction History (Member)-------- //

        public function getPVPListing($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $searchData     = $params['searchData'];
            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $limits          = "LIMIT ".implode(",",General::getLimit($pageNumber))."";
            $decimalPlaces  = Setting::getSystemDecimalPlaces();

            $userID = $db->userID;
            $site = $db->userType;
            $cpDb = $db->copy();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);


                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");
                        //     $invOrderID = $db->getValue("inv_order", "id", null);
                        //     $db->where("inv_order_id", $invOrderID, "IN");

                        //     break;
                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID, true);

                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $cpDb->where('client_id', $downlines, "IN");
                            $invOrderID = $cpDb->getValue("inv_order", "id", null);
                            $db->where("inv_order_id", $invOrderID, "IN");
                            break;

                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where("client_id = leader_id");
                            $mainLeaderSq->getValue('mlm_leader', 'client_id', null);
                            $cpDb->where('id', $mainLeaderSq, "IN");
                            $cpDb->where('member_id', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');

                            if ($mainLeaderID) {
                                $cpDb->where('trace_key', "%".$mainLeaderID."%", "LIKE");
                                $mainDownlines = $cpDb->map('client_id')->get('tree_placement',  null, 'client_id');

                                $cpDb->where('client_id', $mainDownlines, "IN");
                                $cpDb->where('client_id = leader_id');
                                $cpDb->where('client_id', $mainLeaderID, "!=");
                                $mainLeaders = $cpDb->getValue('mlm_leader', 'client_id', null);
                            }

                            if (!empty($mainLeaders)) {
                                $tempDownlines = array();
                                foreach ($mainLeaders as $leader) {
                                    $cpDb->where('trace_key', "%".$leader."%", "LIKE");
                                    $temp = $cpDb->map('client_id')->get('tree_placement', null, 'client_id');
                                    $tempDownlines = array_merge($tempDownlines, $temp);
                                    unset($temp);
                                }
                                $tempDownlines = array_unique($tempDownlines);

                                foreach ($tempDownlines as $downline) {
                                    unset($mainDownlines[$downline]);
                                }
                                unset($tempDownlines);
                            }

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $mainLeaderSq2 = $db->subQuery();
                            $mainLeaderSq2->where('client_id', $mainDownlines, "IN");
                            $mainLeaderSq2->getValue('inv_order', "id", null);
                            $db->where("inv_order_id", $mainLeaderSq2, "IN");
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'dateRange':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                $sq = $db->subQuery();
                                $sq->where("DATE(created_at)", date('Y-m-d', $dateFrom), '>=');
                                $sq->get("inv_order",null,"id");
                                $db->where("inv_order_id",$sq,"IN");
                            }
                            if(strlen($dateTo) > 1) {
                                $sq = $db->subQuery();
                                $sq->where("DATE(created_at)", date('Y-m-d', $dateTo), '<=');
                                $sq->get("inv_order",null,"id");
                                $db->where("inv_order_id",$sq,"IN");
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where('member_id',$dataValue);
                            $sq->getOne('client','id');
                            $ioid = $db->subQuery();
                            $ioid->where('client_id',$sq);
                            $ioid->get("inv_order", null,"id");
                            $db->where("inv_order_id", $ioid, "IN");
                            break;

                        case 'fullname':
                            if($dataType == "like"){
                                $sq = $db->subQuery();
                                $sq->where('name', "%" .  $dataValue . "%", 'LIKE');
                                $sq->get('client',NULL,'id');
                                $name = $db->subQuery();
                                $name->where('client_id',$sq, "IN");
                                $name->get("inv_order", null,"id");
                                $db->where("inv_order_id", $name, "IN");
                            }else{
                                $sq = $db->subQuery();
                                $sq->where('name',$dataValue);
                                $sq->getOne('client','id');
                                $name = $db->subQuery();
                                $name->where('client_id',$sq);
                                $name->get("inv_order", null,"id");
                                $db->where("inv_order_id", $name, "IN");
                            }
                            break;

                        case 'package':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("content", "%".$dataValue . "%", "LIKE");
                                $sq->where("module","mlm_product");
                                $sq->where("type", "name");
                                $sq->get("inv_language", NULL, "module_id");
                                $db->where("mlm_product_id",$sq,"IN");
                            } else{
                                $sq = $db->subQuery();
                                $sq->where("content", $dataValue);
                                $sq->where("module","mlm_product");
                                $sq->getOne("inv_language", "module_id");
                                $db->where("mlm_product_id",$sq);
                            }

                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            if($site == 'Member'){
                $sq = $db->subQuery();
                $sq->where("client_id", $userID);
                $sq->get("inv_order",null,"id");
                $db->where("inv_order_id",$sq,"IN");
            }

            $db->groupBy("inv_order_id");
            $db->groupBy("mlm_product_id");

            $copyDb = $db->copy();

            if($seeAll == "1"){
                $limit = $limits = null;
            }

            $detailRes = $db->getValue('inv_order_detail','id',null);
            $pvpListingRes = $db->rawQuery("SELECT detail.id, detail.inv_order_id, detail.mlm_product_id, detail.inv_product_id, detail.price AS unitPrice, detail.pv_price AS pvPrice, detail.quantity, b.client_id, b.created_at AS date FROM inv_order_detail detail LEFT JOIN inv_order b ON detail.inv_order_id = b.id WHERE detail.id IN ('".implode("', '", $detailRes)."') ORDER BY b.created_at DESC ".$limits."");

            $totalRecord = count($copyDb->get('inv_order_detail', null, "id"));

            if (empty($pvpListingRes))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach($pvpListingRes as $pvpListingRow){
                $memberIDAry[$pvpListingRow['client_id']] = $pvpListingRow['client_id'];
                $mlmProductIDAry[$pvpListingRow['mlm_product_id']] = $pvpListingRow['mlm_product_id']; //mlm product
            }

            if($memberIDAry){
                $db->where('id', $memberIDAry,'IN');
                $memberIDArr = $db->map('id')->get('client',null,'id, member_id, name, sponsor_id, state_id');

                $db->where('client_id', $memberIDAry, "IN");
                $db->where('address_type', 'billing');
                $city = $db->map('client_id')->get('address', null, 'client_id, city');
            }

            if($city){
                $db->where('id', $city, "IN");
                $cityName = $db->map('id')->get('city',  NULL, 'id, name');
            }


            if($mlmProductIDAry){
                $db->where('id',$mlmProductIDAry,'IN');
                $packageNameRes = $db->map('id')->get('mlm_product',null,'id, name');
            }

            $mainLeaderAry = array();

            foreach($pvpListingRes as $pvpListingRow){
                $pvpListRes['date'] = date($dateTimeFormat, strtotime($pvpListingRow['date']));
                $pvpListRes['memberID'] = $memberIDArr[$pvpListingRow['client_id']]['member_id']?:"-";
                $pvpListRes['fullName'] = $memberIDArr[$pvpListingRow['client_id']]['name']?:"-";
                $pvpListRes['package'] = $packageNameRes[$pvpListingRow['mlm_product_id']];
                $pvpListRes['quantity'] = Setting::setDecimal($pvpListingRow['quantity']);
                $pvpListRes['unitPrice'] = Setting::setDecimal($pvpListingRow['unitPrice']);
                $pvpListRes['totalPrice'] = Setting::setDecimal($pvpListingRow["quantity"] * $pvpListingRow["unitPrice"]);
                $pvpListRes['pv'] = Setting::setDecimal($pvpListingRow["quantity"] * $pvpListingRow["pvPrice"]);
                $pvpListRes['city'] = $cityName[$city[$pvpListingRow['client_id']]] ? $cityName[$city[$pvpListingRow['client_id']]] : "-";

                $sponsorID = $memberIDArr[$pvpListingRow['client_id']]['sponsor_id'];
                $db->where('id', $sponsorID);
                $sponsorDetails = $db->getOne('client', 'name, member_id');
                $pvpListRes['sponsorID'] = $sponsorDetails['member_id'];
                $pvpListRes['sponsorName'] = $sponsorDetails['name'];

                $client['clientID'] = $memberIDArr[$pvpListingRow['client_id']]['id'];
                $mainLeaderMemberID = Tree::getMainLeaderUsername($client);

                if (!$mainLeaderAry[$mainLeaderMemberID]){
                    $db->where("member_id", $mainLeaderMemberID);
                    $currentMainLeader = $db->getOne("client", "name, member_id, id");
                    $mainLeaderAry[$currentMainLeader["member_id"]] = $currentMainLeader;
                }

                $mainLeaderAry[$mainLeaderMemberID]['member_id'] ? $pvpListRes['mainLeaderID'] = $mainLeaderAry[$mainLeaderMemberID]['member_id']
                                                                        : $pvpListRes['mainLeaderID'] = "-";

                $mainLeaderAry[$mainLeaderMemberID]['name'] ?  $pvpListRes['mainLeaderName'] = $mainLeaderAry[$mainLeaderMemberID]['name']
                                                                   :  $pvpListRes['mainLeaderName'] = "-";

                $pvpList[] = $pvpListRes;
                unset($pvpListRes);

            }

            $data['list']   = $pvpList;

            if($params['type'] == "export") {
                 $params['command'] = __FUNCTION__;
                 $data = Excel::insertExportData($params);
                 return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;

            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language] /* Successfully retrieved. */, 'data' => $data);
        }

        // -------- Low Stock Percentage -------- //
        public function getLowStockQuantity($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateFormat     = Setting::$systemSetting["systemDateFormat"];

            $db->where('name', 'lowStockQuantity');
            $db->orderBy('id', 'DESC');
            $getNewestQuantity = $db->get('system_settings_admin', null, 'value, status, created_at, creator_id');
            $currentQuantity = General::getSystemSettingAdmin('lowStockQuantity');

            if(empty($currentQuantity)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $data);

            foreach($getNewestQuantity as $getAdminID){
                $adminID[$getAdminID['creator_id']] = $getAdminID['creator_id'];
            }

            $db->where('id', $adminID, 'IN');
            $getName = $db->map('id')->get('admin', null, 'id, username');

            foreach($getNewestQuantity as $value){
                $getNewestQuantityList['date'] = date($dateFormat,strtotime($value['created_at']));
                $getNewestQuantityList['quantity'] = $value['value'];
                $getNewestQuantityList['status'] = $value['status'];
                $getNewestQuantityList['createdBy'] = $getName[$value['creator_id']];

                $getAllNewestQuantityList[] = $getNewestQuantityList;
            }
            $data['currentQuantity'] = $currentQuantity['lowStockQuantity']['value']?:0;
            $data['previousQuantity'] = $getAllNewestQuantityList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function setLowStockQuantity($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID = $db->userID;
            $quantity     = $params['quantity'];

            $checkExists    = General::getSystemSettingAdmin('lowStockQuantity');

            if($checkExists){
                $updateData = array(
                    "status" => 'Inactive',
                );
                $db->where('type', 'lowStockQuantity');
                $db->update('system_settings_admin', $updateData);
            }

            $insertData = array(
                "name"      => 'lowStockQuantity',
                "type"      => 'lowStockQuantity',
                "value"     => $quantity,
                "created_at"=> date('Y-m-d H:i:s'),
                "creator_id"=> $userID,
                "status"    => 'Active',
                "active_at" => date('Y-m-d'),
            );
            $db->insert('system_settings_admin', $insertData);

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => "");
        }

        // -------- Product Alert Module -------- //
        public function productAlertListing($params, $outOfStock = false){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateFormat     = Setting::$systemSetting["systemDateFormat"];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            $checkExists    = General::getSystemSettingAdmin('lowStockQuantity');

            if($checkExists){
                $lowInStock = $checkExists['lowStockQuantity']['value'] ? : 0;
            }

            $db->groupBy('inv_product_id');
            if($outOfStock){
                $db->having('SUM(stock_in)-SUM(stock_out)', 0,'<=');
            }else{
                $db->having('SUM(stock_in)-SUM(stock_out)', $lowInStock,'<');
                $db->having('SUM(stock_in)-SUM(stock_out)', 0,'>');
            }
            $allProductRes = $db->get('inv_stock', $limit, 'inv_product_id, SUM(stock_in) as stock_in, SUM(stock_out) as stock_out');

            if(empty($allProductRes) && !$outOfStock){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $data);
            }

            foreach($allProductRes as $getTotalRecord){
                $ttlRecord[] = $getTotalRecord['inv_product_id'];
            }

            if($outOfStock && count($ttlRecord) < $limit[1]){
                $sq = $db->subQuery();
                $sq->groupBy('inv_product_id');
                $sq->get('inv_stock', null, 'inv_product_id');
                $db->where('id', $sq, 'NOT IN');
                $db->where('status', 'Active');
                $db->orderBy('created_at', 'DESC');
                $getAllProductRes = $db->get('inv_product', $limit[1]-count($ttlRecord), 'id, code, name, alert_at');

                if(empty($getAllProductRes)){
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $data);
                }

                if($getAllProductRes){
                    foreach($getAllProductRes as $getRecordID){
                        $ttlRecord[] = $getRecordID['id'];
                    }
                }
            }

            if($ttlRecord) $db->where('id', $ttlRecord, 'IN');
            $totalRecord = $db->getValue('inv_product', 'COUNT(*)');

            $db->where('status', 'Active');
            $getListRes = $db->map('id')->get('inv_product', null, 'id, code, name, alert_at');

            if(empty($getListRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $data);
            }

            foreach($allProductRes as $getDataRow){
                $getDataList['productID'] = $getDataRow['inv_product_id'];
                $getDataList['productCode'] = $getListRes[$getDataRow['inv_product_id']]['code'];
                $getDataList['productName'] = $getListRes[$getDataRow['inv_product_id']]['name'];
                if(!$outOfStock){
                    $getDataList['lowInStockDate'] = $getListRes[$getDataRow['inv_product_id']]['alert_at'] > 0 ? date($dateFormat, strtotime($getListRes[$getDataRow['inv_product_id']]['alert_at'])):'-';
                }else{
                    $getDataList['outOfStockDate'] = $getListRes[$getDataRow['inv_product_id']]['alert_at'] > 0 ? date($dateFormat, strtotime($getListRes[$getDataRow['inv_product_id']]['alert_at'])):'-';
                }

                $allGetDataList[$getDataRow['inv_product_id']] = $getDataList;
            }
            if($outOfStock && $getAllProductRes){
                foreach($getAllProductRes as $getNewDataRow){
                    $getDataList['productID'] = $getNewDataRow['id'];
                    $getDataList['productCode'] = $getNewDataRow['code'];
                    $getDataList['productName'] = $getListRes[$getNewDataRow['id']]['name'];
                    $getDataList['outOfStockDate'] = $getNewDataRow['alert_at'] > 0 ? date($dateFormat, strtotime($getNewDataRow['alert_at'])):'-';

                    $allGetDataList[$getNewDataRow['id']] = $getDataList;
                }
            }

            $data['result'] = $allGetDataList;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }


            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // -------- New Module -------- //

        public function getDiscountVoucherSetting($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateFormat     = Setting::$systemSetting["systemDateFormat"];

            $site = $db->userType;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName){
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                $db->where('created_at', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }

                                $db->where('created_at', date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'voucherName':
                            $db->where('name', $dataValue);
                            break;

                        case 'voucherCode':
                            $db->where('code', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy('created_at', 'DESC');

            $getVoucher = $db->get('inv_voucher', $limit, 'id, name, code, total_balance, total_used, status, created_at, updated_at, updater_id');

            if(empty($getVoucher)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach($getVoucher as $getVouchers){
                $updaterID[$getVouchers['updater_id']] = $getVouchers['updater_id'];
            }

            if($updaterID){
                $db->where('id', $updaterID, "IN");
                $updaterName = $db->map('id')->get('admin',  NULL, 'id, username');
            }

            $totalRecord = $copyDb->getValue('inv_voucher', 'count(id)');

            foreach($getVoucher as $getVouchers){

                $getVouc['id']      = $getVouchers['id'];
                $getVouc['name']    = $getVouchers['name'];
                $getVouc['code']    = $getVouchers['code'];
                $getVouc['totalBalance'] = Setting::setDecimal($getVouchers['total_balance']);
                $getVouc['totalUsed']    = Setting::setDecimal($getVouchers['total_used']);
                $getVouc['status']       = General::getTranslationByName($getVouchers['status']);
                $getVouc['updaterName']  = $updaterName[$getVouchers['updater_id']];
                $getVouc['createdAt']    = date($dateFormat, strtotime($getVouchers['created_at']));
                $getVouc['updatedAt']    = $getVouchers['updated_at'] >0 ? date('d-m-y', strtotime($getVouchers['updated_at'])) : '-';

                $getVoucherAry[] = $getVouc;
            }

            $data['voucherList']    = $getVoucherAry;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getCurrentDiscountVoucherSetting($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $site = $db->userType;

            $id = $params['id'];

            $db->where('status', 'Active');
            $packageRes = $db->get('mlm_product', null, 'id, name, is_starter_kit');

            foreach ($packageRes as $packageRow) {
                $packageIDAry[$packageRow['id']] = $packageRow['id'];
            }

            if($packageIDAry){
                $db->where('module', 'mlm_product');
                $db->where('module_id', $packageIDAry, "IN");
                $db->where('type', 'name');
                $db->where('language', $language);
                $packageLang = $db->map('module_id')->get('inv_language', null, 'module_id, content');
            }

            foreach ($packageRes as $packageRow) {
                $packageType = ($packageRow['is_starter_kit'] == 1) ? 'starter' : 'normal';
                $packageDetail['packageID'] = $packageRow['id'];
                $packageDetail['packageName'] = $packageLang[$packageRow['id']];

                $packageList[$packageType][] = $packageDetail;
            }

            $data['packageLists'] = $packageList;

            if($id){
                $db->where('id', $id);
                $getVoucher = $db->getOne('inv_voucher', 'name, code, total_balance AS balance, status');
                $getVoucher['balance'] = Setting::setDecimal($getVoucher['balance']);

                $db->where('inv_voucher_id', $id);
                $getVoucherDetail = $db->get('inv_voucher_detail', null, 'name, value, type, reference');

                unset($packageArr);
                foreach ($getVoucherDetail as $value) {
                    if($value['name'] == "discountBy"){
                        if($value['type'] == "percentage"){
                            $values['type'] = "percentage";
                            $values["percentage"] = Setting::setDecimal($value['value']);
                            $values["maxAmount"] = Setting::setDecimal($value['reference']);
                        }else if($value['type'] == "amount"){
                            $values['type'] = "amount";
                            $values["amount"] = Setting::setDecimal($value['value']);
                        }
                        $voucherDet = $values;
                    }else if($value['type'] == "validPackage"){
                        $packageArr[$value['value']] = $value['value'];
                        continue;
                    }else if($value["type"] == "tieUpType"){
                        $tieUpType = $value["value"];
                    }
                }

                $data['voucher']['voucherLists'] = $getVoucher;
                $data['voucher']['discountBy'] = $voucherDet;
                $data['voucher']['isTieUpPackage'] = 0;
                if($packageArr){
                    $data['voucher']['packageList'] = $packageArr;
                    $data['voucher']['isTieUpPackage'] = 1;
                    $data["voucher"]["tieUpType"] = $tieUpType;
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function verifyDiscountVoucherSetting($params, $type = "") {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $voucherID      = trim($params['voucherID']);
            $voucherName    = trim($params['voucherName']);
            $voucherCode    = trim($params['voucherCode']);
            $voucherBalance = trim($params['balance']);
            $voucherStatus  = trim($params['voucherStatus']);
            $statusAry      = array('Active', 'Inactive');

            //verify for inv_voucher_detail
            $packageIDAry   = $params['packageIDAry'];
            $discountBy     = trim($params['discountBy']);
            $discountByArr  = array("amount", "percentage");
            $discountPercentage  = trim($params['discountPercentage']);
            $discountMaxAmount   = trim($params['discountMaxAmount']);
            $discountAmount = trim($params['discountAmount']);
            $isTieUpPackage = $params['isTieUpPackage'];
            $tieUpType = trim($params["tieUpType"]);
            $isTieUp = array(0,1);

            if(!$voucherID && $type == "edit") {
                $errorFieldArr[] = array(
                    'id'  => "voucherError",
                    'msg' => $translations['E01130'][$language] /* Please Enter ID. */
                );
            }else if($type =="edit"){

                $db->where('id', $voucherID);
                $idResult = $db->getOne("inv_voucher", 'id');

                if(!$idResult) {
                    $errorFieldArr[] = array(
                        'id'  => 'voucherError',
                        'msg' => $translations["E01136"][$language] /* ID is not exist. */
                    );
                }

            }

            if(!$voucherName) {
                $errorFieldArr[] = array(
                    'id'  => "voucherNameError",
                    'msg' => $translations['E01131'][$language] /* Please Enter Name. */
                );
            }

            // Check Code Field
            if(!$voucherCode && $type == 'add') {
                $errorFieldArr[] = array(
                    'id'  => "voucherCodeError",
                    'msg' => $translations['E01132'][$language] /* Please Enter Code. */
                );
            } else if($type == 'add'){
                // check code avaibility
                $db->where('code', $voucherCode);
                $result = $db->getOne("inv_voucher", 'code');

                if($result) {
                    $errorFieldArr[] = array(
                        'id'  => 'voucherCodeError',
                        'msg' => $translations["E01133"][$language] /* Code Existed. */
                    );
                }
            } else if($type == "edit"){
                if($voucherCode){
                    $errorFieldArr[] = array(
                        "id"  => "voucherCodeError",
                        "msg" => "Code cannot be edited.",
                    );
                }
            }

            // Check balance Field
            if($type == 'edit'){
                /*$db->where('id', $id);
                $getIsUnlimited = $db->getValue("inv_voucher", 'is_unlimited');

                if($getIsUnlimited == "1") {*/
                    if($voucherBalance){
                        $errorFieldArr[] = array(
                            'id'  => 'balanceError',
                            'msg' => $translations["E01135"][$language] /* Balance Cannot Be Edited. */
                        );
                    }
            }else if($type == 'add' && $voucherBalance){
                if(!is_numeric($voucherBalance) || $voucherBalance < 0){
                    $errorFieldArr[] = array(
                        'id'  => "balanceError",
                        'msg' => $translations['E01134'][$language] /* Please Enter Balance. */
                    );
                }
            }

            // Check Status Field
            if(!$voucherStatus || !in_array($voucherStatus, $statusAry)) {
                $errorFieldArr[] = array(
                    'id'  => "statusError",
                    'msg' => $translations['E00671'][$language] /* Please Select Status. */
                );
            }

            // verify for inv_voucher_detail
            if(!in_array($isTieUpPackage, $isTieUp)){
                $errorFieldArr[] = array(
                    'id'  => "tieUpPackageError",
                    'msg' => $translations['E01143'][$language] /* Please Enter 1 or 0 for tie up Package. */
                );
            }

            if($isTieUpPackage == 1){
                if((!$tieUpType) || (!in_array($tieUpType,array("single","normal")))){
                    $errorFieldArr[] = array(
                        "id" => "tieUpTypeError",
                        "msg" => $translations["E00741"][$language],
                    );
                }

                if(!$packageIDAry) {
                    $errorFieldArr[] = array(
                        'id'  => "voucherError",
                        'msg' => $translations['E01137'][$language] /* Please Enter Package ID. */
                    );
                }else{
                    $db->where('status', 'Active');
                    $db->where('id', $packageIDAry, "IN");
                    $getPackageID = $db->map('id')->get('mlm_product', NULL, 'id');

                    foreach($packageIDAry as $packageId){
                        if(!$getPackageID[$packageId]){
                            $errorFieldArr[] = array(
                                'id'  => "package". $packageId. "Error",
                                'msg' => $translations['E01138'][$language] /* Inactive package. */
                            );
                        }
                    }
                }
            }

            if(!$discountBy || !in_array($discountBy, $discountByArr)) {
                $errorFieldArr[] = array(
                    'id'  => "discountByError",
                    'msg' => $translations['E01139'][$language] /* discountBy only can be percentage or amount. */
                );
            }

            if($discountBy == 'percentage'){
                if(!is_numeric($discountPercentage) || $discountPercentage < 0) {
                    $errorFieldArr[] = array(
                        'id'  => "percentageError",
                        'msg' => $translations['E01140'][$language] /* Please Enter correct discount precentage. */
                    );
                }

                if($discountMaxAmount){
                    if(!is_numeric($discountMaxAmount) || $discountMaxAmount < 0) {
                        $errorFieldArr[] = array(
                            'id'  => "maxAmountError",
                            'msg' => $translations['E01141'][$language] /* Please Enter Correct Discount Max Amount. */
                        );
                    }
                }

            }else if($discountBy == 'amount'){
                if(!is_numeric($discountAmount) || $discountAmount < 0) {
                    $errorFieldArr[] = array(
                        'id'  => "amountError",
                        'msg' => $translations['E01142'][$language] /* Please Enter Correct Discount Amount. */
                    );
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "" , 'data'=> "");
        }

        public function addDiscountVoucherSetting($params, $type) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");
            $tableName      = "inv_voucher";
            $tableNames     = "inv_voucher_detail";
            $voucherName    = trim($params['voucherName']);
            $voucherCode    = trim($params['voucherCode']);
            $voucherBalance = trim($params['balance']);
            $voucherStatus  = trim($params['voucherStatus']);

            //insert into inv_voucher_detail
            $packageIDAry   = $params['packageIDAry'];
            $discountBy     = trim($params['discountBy']);
            $discountPercentage  = trim($params['discountPercentage']);
            $discountMaxAmount   = trim($params['discountMaxAmount']);
            $discountAmount = trim($params['discountAmount']);
            $isTieUpPackage = trim($params['isTieUpPackage']);
            $tieUpType = trim($params["tieUpType"]);
            $userID         = $db->userID;
            $site           = $db->userType;

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{

                $db->where('id',$userID);
                $adminNames = $db->getValue('admin','username');
                if(!$adminNames){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $verify = self::verifyDiscountVoucherSetting($params, "add");
            if($verify["status"] != "ok") return $verify;

            if($voucherBalance > 0){
                $isUnlimited = 0;
            }else{
                $isUnlimited = 1;
            }

            $insertVoucherData = array(

                "name" => $voucherName,
                "code" => $voucherCode,
                "total_balance" => $voucherBalance,
                "type" => "delivery",
                "status" => $voucherStatus,
                "is_unlimited" => $isUnlimited,
                "created_at" => $dateTime,
            );

            $insertNewVoucher = $db->insert($tableName, $insertVoucherData);

            if (!$insertNewVoucher)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to add discount voucher" /* Failed to update data. */, 'data'=> "");

            if($discountBy == 'percentage'){
                $insertVoucherDetail = array(

                    "inv_voucher_id" => $insertNewVoucher,
                    "name" => "discountBy",
                    "value" => $discountPercentage,
                    "type" => $discountBy,
                    "reference" => $discountMaxAmount

                );

            }else if($discountBy == 'amount'){
                $insertVoucherDetail = array(
                    "inv_voucher_id" => $insertNewVoucher,
                    "name" => "discountBy",
                    "value" => $discountAmount,
                    "type" => $discountBy,
                );
            }

            $insertNewVoucherDetail = $db->insert($tableNames, $insertVoucherDetail);

            if($isTieUpPackage == 1){
                unset($insertVoucherDetail);
                $insertVoucherDetail = array(
                    "inv_voucher_id" => $insertNewVoucher,
                    "name" => "tieUpType",
                    "value" => $tieUpType,
                    "type" => "tieUpType",
                );
                $insertNewVoucherDetail = $db->insert($tableNames,$insertVoucherDetail);

                foreach($packageIDAry as $packageID){
                    unset($insertVoucherDetail);
                    $insertVoucherDetail = array(
                        "inv_voucher_id" => $insertNewVoucher,
                        "name" => "validPackage",
                        "value" => $packageID,
                        "type" => "validPackage",
                    );

                    $insertNewVoucherDetail = $db->insert($tableNames, $insertVoucherDetail);
                }

                $db->where("id",$packageIDAry,"IN");
                $packageCodeAry = $db->getValue("mlm_product","code",null);
                $packageCodeAry = implode(", ",$packageCodeAry);
            }

            if (!$insertNewVoucherDetail)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to add voucher detail" /* Failed to update data. */, 'data'=> "");

            // insert activity log
            $titleCode      = 'T00070';
            $activityCode   = 'L00094';
            $title          = 'Add Discount Voucher';
            $activityData   = array(
                "admin"                     => $adminNames,
                "addedVoucherName"          => $voucherName,
                "voucherCode"               => $voucherCode,
                "voucherBalance"            => $voucherBalance ? $voucherBalance: '-',
                "voucherStatus"             => $voucherStatus,
                "packageName"               => $packageCodeAry ? $packageCodeAry : "-",
                "discountVoucherPercentage" => $discountPercentage ? $discountPercentage: '-',
                "discountVoucherType"       => $discountBy,
                "discountMaxAmounts"        => $discountMaxAmount ? $discountMaxAmount: '-',
                "discountAmounts"           => $discountAmount ? $discountAmount: '-'
            );

            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully added voucher data" , 'data'=> "");
        }

        public function editDiscountVoucherSetting($params, $type) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");
            $tableName      = "inv_voucher";
            $tableNames     = "inv_voucher_detail";
            $voucherID      = trim($params['voucherID']);
            $voucherName  = trim($params['voucherName']);
            $voucherBalance = trim($params['balance']);
            $voucherStatus  = trim($params['voucherStatus']);

            //insert into inv_voucher_detail
            $packageIDAry   = $params['packageIDAry'];
            $discountBy     = trim($params['discountBy']);
            $discountPercentage  = trim($params['discountPercentage']);
            $discountMaxAmount   = trim($params['discountMaxAmount']);
            $discountAmount = trim($params['discountAmount']);
            $isTieUpPackage = trim($params['isTieUpPackage']);
            $tieUpType = trim($params["tieUpType"]);
            $userID         = $db->userID;
            $site           = $db->userType;

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{

                $db->where('id',$userID);
                $adminNames = $db->getValue('admin','username');
                if(!$adminNames){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $verify = self::verifyDiscountVoucherSetting($params, "edit");
            if($verify["status"] != "ok") return $verify;

            if($voucherBalance > 0){
                $isUnlimited = 0;
            }else{
                $isUnlimited = 1;
            }

            $db->where("id",$voucherID);
            $getVoucherCode = $db->getOne('inv_voucher', 'code');

            $updatedVoucherData = array(
                "name" => $voucherName,
                "status" => $voucherStatus,
                "updater_id" => $userID,
                "updated_at" => $dateTime,
            );

            $db->where("id",$voucherID);
            if (!$db->update($tableName, $updatedVoucherData))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to update discount voucher" /* Failed to update data. */, 'data'=> "");

            unset($updatedVoucherDetail);
            if($discountBy == "percentage"){
                $updatedVoucherDetail = array(
                    "value" => $discountPercentage,
                    "type" => $discountBy,
                    "reference" => $discountMaxAmount,
                );
            }else if($discountBy == "amount"){
                $updatedVoucherDetail = array(
                    "value" => $discountAmount,
                    "type" => $discountBy,
                    "reference" => "",
                );
            }

            $db->where("inv_voucher_id", $voucherID);
            $db->where("name","discountBy");
            if (!$db->update($tableNames, $updatedVoucherDetail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to update voucher detail" /* Failed to update data. */, 'data'=> "");

            if($isTieUpPackage == 1){
                unset($updateDetail);
                $updateDetail = array(
                    "value" => $tieUpType,
                );
                $db->where("inv_voucher_id",$voucherID);
                $db->where("type","tieUpType");
                $db->update("inv_voucher_detail",$updateDetail);

                $db->where("inv_voucher_id",$voucherID);
                $db->where("type","validPackage");
                $oriPackageList = $db->map("value")->get("inv_voucher_detail",null,"value");

                unset($existingPackageIDAry);
                foreach($oriPackageList as $oriPackageID){
                    if((!in_array($oriPackageID,$packageIDAry))){
                        unset($updateDetail);
                        $updateDetail = array(
                            "type" => "invalidPackage",
                        );
                        $db->where("inv_voucher_id",$voucherID);
                        $db->where("type","validPackage");
                        $db->where("value",$oriPackageID);
                        $db->update("inv_voucher_detail",$updateDetail);
                    }else{
                        $existingPackageIDAry[$oriPackageID] = $oriPackageID;
                    }
                }

                foreach($packageIDAry as $packageID){
                    if(!$existingPackageIDAry[$packageID]){
                        unset($insertVoucherDetail);
                        $insertVoucherDetail = array(
                            "inv_voucher_id" => $voucherID,
                            "name" => "validPackage",
                            "value" => $packageID,
                            "type" => "validPackage",
                        );
                        $db->insert("inv_voucher_detail",$insertVoucherDetail);
                    }
                }

                $db->where("id",$packageIDAry,"IN");
                $packageCodeAry = $db->getValue("mlm_product","code",null);
                $packageCodeAry = implode(", ",$packageCodeAry);
            }else{
                $db->where("inv_voucher_id",$voucherID);
                $db->where("type","validPackage");
                $db->update("inv_voucher_detail",array("type" => "invalidPackage"));
            }

            // insert activity log
            $titleCode      = 'T00069';
            $activityCode   = 'L00093';
            $title          = 'Edit Discount Voucher';
            $activityData   = array(
                "admin"                         => $adminNames,
                "updatedVoucherName"            => $voucherName,
                "voucherCode"                   => $getVoucherCode['code'],
                "updatedVoucherStatus"          => $voucherStatus,
                "updatedPackageCode"            => $packageCodeAry ? $packageCodeAry : "-",
                "updatedDiscountVoucherPercentage" => $discountPercentage ? $discountPercentage: '-',
                "updatedDiscountVoucherType"    => $discountBy,
                "updatedDiscountMaxAmount"      => $discountMaxAmount ? $discountMaxAmount: '-',
                "updatedDiscountAmount"         => $discountAmount ? $discountAmount: '-'
            );

            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully update voucher data" , 'data'=> "");
        }

        public function getDiscountVoucherRedemptionListing($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateFormat     = Setting::$systemSetting["systemDateFormat"];

            $site = $db->userType;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName){

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(strlen($dateFrom) > 0) {
                                $sq = $db->subQuery();
                                $sq->where("DATE(created_at)", date('Y-m-d', $dateFrom), '>=');
                                $sq->get("inv_order",null,"id");
                                $db->where("inv_order_id",$sq,"IN");

                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                            }
                            if(strlen($dateTo) > 0) {
                                $sq = $db->subQuery();
                                $sq->where("DATE(created_at)", date('Y-m-d', $dateTo), '<=');
                                $sq->get("inv_order",null,"id");
                                $db->where("inv_order_id",$sq,"IN");

                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }

                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                }
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");

                            $ssq = $db->subQuery();
                            $ssq->where('client_id',$sq);
                            $ssq->get('inv_order',null,'id');
                            $db->where("inv_order_id", $ssq, "IN");
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" .  $dataValue . "%", "LIKE");
                                $sq->get("client",NULL, "id");

                                $ssq = $db->subQuery();
                                $ssq->where('client_id',$sq,'IN');
                                $ssq->get('inv_order',null,'id');
                                $db->where("inv_order_id", $ssq, "IN");
                            }else{
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");

                                $ssq = $db->subQuery();
                                $ssq->where('client_id',$sq);
                                $ssq->get('inv_order',null,'id');
                                $db->where("inv_order_id", $ssq, "IN");
                            }
                            break;

                        case 'voucherCode':
                            $sq = $db->subQuery();
                            $sq->where("code", $dataValue);
                            $sq->get("inv_voucher", NULL, "id");
                            $db->where("inv_voucher_id", $sq, "IN");
                            break;

                        case 'invoiceNo':
                            $sq = $db->subQuery();
                            $sq->where("reference_number", $dataValue);
                            $sq->get("inv_order", NULL, "id");
                            $db->where("inv_order_id", $sq, "IN");
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();

            $db->orderBy('inv_order_id', 'DESC');
            $getVoucherRedemList = $db->get('inv_order_voucher', $limit, 'inv_voucher_id, inv_order_id');

            if(empty($getVoucherRedemList)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach($getVoucherRedemList as $getVoucherRedemptionList){
                $voucherID[$getVoucherRedemptionList['inv_voucher_id']] = $getVoucherRedemptionList['inv_voucher_id'];
                $invOrderID[$getVoucherRedemptionList['inv_order_id']]  = $getVoucherRedemptionList['inv_order_id'];
            }

            if($voucherID){
                $db->where('id', $voucherID, "IN");
                $voucherData = $db->map('id')->get('inv_voucher',  NULL, 'id, code');
            }

            if($invOrderID){
                $db->where('id', $invOrderID, "IN");
                $invOrderData = $db->map('id')->get('inv_order',  NULL, 'id, client_id, reference_number, created_at');
            }

            foreach($invOrderData as $invOrderRow){
                $clientData[$invOrderRow['client_id']] = $invOrderRow['client_id'];
            }

            if($clientData){
                $db->where('id', $clientData, "IN");
                $clientDatas = $db->map('id')->get('client',  NULL, 'id, member_id, name');
            }

            $totalRecord = $copyDb->getValue('inv_order_voucher', 'count(id)');

            foreach($getVoucherRedemList as $getVoucherRedemptionList){
                $getVoucherRedempList['date'] = date('d/m/Y H:i:s', strtotime($invOrderData[$getVoucherRedemptionList['inv_order_id']]['created_at']));
                $getVoucherRedempList['memberID'] = $clientDatas[$invOrderData[$getVoucherRedemptionList['inv_order_id']]['client_id']]['member_id'];
                $getVoucherRedempList['fullName'] = $clientDatas[$invOrderData[$getVoucherRedemptionList['inv_order_id']]['client_id']]['name'];
                $getVoucherRedempList['invoiceNo'] = $invOrderData[$getVoucherRedemptionList['inv_order_id']]['reference_number'];
                $getVoucherRedempList['voucherCode'] = $voucherData[$getVoucherRedemptionList['inv_voucher_id']];

                $getVoucherRedemptionAry[] = $getVoucherRedempList;
            }

            $data['voucherRedemptionList']    = $getVoucherRedemptionAry;
            $data['pageNumber']               = $pageNumber;
            $data['totalRecord']              = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }



        public function updateStarterpackEmailAttachment($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("YmdHis");


            $name  = trim($params['name']);
            $pdfpath  = trim($params['path']);
            $contenttype  = trim($params['contenttype']);


            if(empty($name)){
                 return array('status' => "error", 'code' => 1, 'statusMsg' => "name is empty");

            }

            if(empty($pdfpath)){
                 return array('status' => "error", 'code' => 2, 'statusMsg' => "Path is empty");

            }
                $db->where("pdfname",$name);
                $checkName =$db->getOne("pdffile");
                if(empty($checkName)){
                return array('status' => "Error", 'code' => 3, 'statusMsg' =>"pdf name not found" ,"data"=>"");

                }
                $updateIsActive = array(
                "isActive"   => "0"
                );
                $db->where("isActive","1");
                $db->update("pdffile",$updateIsActive);
                $inserpdfparams = array(
                    'path'                   => $pdfpath,
                    'isActive'               => 1,
                    );
            $db->where("pdfname",$name);
            $insertpdf = $db->update('pdffile', $inserpdfparams);
             return array('status' => "ok", 'code' => 0, 'statusMsg' =>"" ,"data"=>"");

        }

        public function checkStarterpackEmailAttachment($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            // $dateTime       = date("YmdHis");

            $db->where("isActive","1");
            $data=$db->getOne("pdffile");
            if(empty($data)){
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Result Not Found" ,"data"=>"");
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "" ,"data"=>$data);

        }

        public function getProductInventoryList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $limit           = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            $db->where('deleted', '0');
            $db->orderBy('created_at', 'DESC');
            $copyDb = $db->copy();
            $productInv = $db->get("product", $limit, "id, barcode as skuCode, name, product_type, cost, sale_price");

            if(empty($productInv)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            foreach($productInv as $productInvRow){
                $productDetail['id']                = $productInvRow['id'];
                $productDetail['skuCode']           = $productInvRow['skuCode'];
                $productDetail['name']              = $productInvRow['name'];
                $productDetail['productType']       = $productInvRow['product_type'];
                $productDetail['cost']              = $productInvRow['cost'];
                $productDetail['salePrice']         = $productInvRow['sale_price'];
                $productDetail['image']             = '';

                $productInvList[] = $productDetail;
            }

            $totalRecord              = $copyDb->getValue("product p", "count(p.id)");
            $data['productInventory'] = $productInvList;
            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            if($seeAll) {
                $data['totalPage']    = 1;
                $data['numRecord']    = $totalRecord;
            } else {
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['numRecord']    = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getProductDetails($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $productInvId   = $params['productInvId'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            include('config.php');

            if(!$productInvId) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            $db->where('deleted', '0');
            $db->where('id', $productInvId);
            $productInv = $db->get("product", $limit, "id, barcode as skuCode, name, product_type, description, cost, sale_price, cooking_time, cooking_suggestion, full_instruction, full_instruction2");

            if(empty($productInv)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            foreach($productInv as $productInvRow){
                $productDetail['id']                = $productInvRow['id'];
                $productDetail['skuCode']           = $productInvRow['skuCode'];
                $productDetail['name']              = $productInvRow['name'];
                $productDetail['productType']       = $productInvRow['product_type'];
                $productDetail['description']       = $productInvRow['description'];
                $productDetail['cost']              = $productInvRow['cost'];
                $productDetail['salePrice']         = $productInvRow['sale_price'];
                $productDetail['cookingTime']       = $productInvRow['cooking_time'];
                $productDetail['cookingSuggestion'] = $productInvRow['cooking_suggestion'];
                $productDetail['fullInstruction']   = $productInvRow['full_instruction'];
                $productDetail['fullInstruction2']  = $productInvRow['full_instruction2'];

                $productInvList[] = $productDetail;
            }

            $db->where('deleted', 0);
            $db->where('reference_id', $productInvId);
            $productMedia = $db->get('product_media', null, 'id, type, url');

            if($productMedia) {
                foreach($productMedia as $val) {
                    if($val['type'] == 'video') {
                        if($val['url'] != '') {
                            $link = $val['url'];
                            $pattern = '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/';
                            preg_match($pattern, $link, $matches);
                            $video_id = $matches[7];

                            $video['url'] = $video_id;
                        }
                        $videoList[] = $video;
                    }

                    if($val['type'] == 'Image') {
                        $media['url']  = $val['url'];
                        $media['name'] = str_replace($config['tempMediaUrl'], '', $val['url']);
                        $mediaList[] = $media;
                    }
                }
            } else {
                $mediaList = '';
                $videoList = '';
            }
            $productInvList[0]['media'] = $mediaList;
            $productInvList[0]['video'] = $videoList;

            $db->where('product_id', $productInvId);
            $productVar = $db->get('product_template', null, 'id, product_attribute_value_id');

            if(!empty($productVar)) {
                // get all attribute value id in product template
                $attrIdList = [];
                foreach($productVar as $attr) {
                    foreach($attr as $val) {
                        foreach(json_decode($val, true) as $v) {
                            if(empty($attrIdList)) {
                                $attrIdList[] = $v;
                            } else {
                                if(!in_array($v, $attrIdList)) {
                                    $attrIdList[] = $v;
                                }
                            }
                        }
                    }
                }

                if(!empty($attrIdList)) {
                    // use the id in $attrIdList to find the product attribute and product attribute value
                    $db->where('pav.deleted', 0);
                    $db->where('pav.id', $attrIdList, 'IN');
                    $db->join('product_attribute pa', 'pa.id = pav.product_attribute_id', 'LEFT');
                    $productAttr = $db->get('product_attribute_value pav', null, 'pav.id, pav.name, pav.product_attribute_id as attribute_id, pa.name as attribute_name');

                    foreach($productAttr as $val) {
                        $attr[] = $val['attribute_id'];

                        $attrVal[$val['attribute_id']]['attribute_id'] = $val['attribute_id'];
                        $attrVal[$val['attribute_id']]['attribute_name'] = $val['attribute_name'];

                        $attribute['id'] = $val['id'];
                        $attribute['name'] = $val['name'];

                        $attrVal[$val['attribute_id']][$val['attribute_name']][] = $attribute;
                    }

                    $db->where('product_attribute_id', $attr, 'IN');
                    $attribute = $db->get('product_attribute_value', null, 'id, name, product_attribute_id');
                }
                $data['attribute'] = $attrVal;
                $data['productTemplate'] = $productVar;
            } else {
                $data['attribute'] = '';
                $data['productTemplate'] = '';
            }

            $data['productInventory'] = $productInvList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function generateProductSKU($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $vendorId = trim($params['vendorId']);

            if($vendorId) {
                $db->where('deleted', 0);
                $db->where('id', $vendorId);
                $vendorCode = $db->getValue('vendor', 'vendor_code');

                $productSku = General::generateDynamicCode($vendorCode.'-',3,'product','barcode');

                if($productSku) {
                    $data['productSku'] = $productSku;
                } else {
                    $data['productSku'] = $vendorCode.'-001';
                }
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPackageProductList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $type = trim($params['type']);

            if($type == 'Package') {
                $db->where('deleted', 0);
                $productList = $db->get('product', null, 'id, name');
    
                $data['productList'] = $productList;
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function addAttribute($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTime       = date("Y-m-d H:i:s");

            $attribute      = trim($params['attribute']);
            $attributeVal   = $params['attributeVal'];
            $status         = trim($params['status']);

            if($attribute) {
                $insetAttrData = array(
                    "name"       => $attribute,
                    "deleted"    => 0,
                    "created_at" => $dateTime,
                );
                $insertAttr = $db->insert('product_attribute', $insetAttrData);
            }

            if($attributeVal) {
                foreach ($attributeVal as $val) {
                    $insetAttrValData = array(
                        "name"                 => $val['name'],
                        "product_attribute_id" => $insertAttr,
                        "deleted"              => 0,
                        "created_at"           => $dateTime,
                    );
                    $insetAttrValDataList[] = $insetAttrValData;
                }
                $insertAttrVal = $db->insertMulti('product_attribute_value', $insetAttrValDataList);
            }

            if($insertAttr) {
                return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */ , 'data'=> '');
            }
        }

        public function getAttributeDetail($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTime       = date("Y-m-d H:i:s");
            $attrId         = $params['attrId'];
            $type           = trim($params['type']);
            $inputId        = trim($params['inputId']);
            
            if($attrId) {
                if($type != 'get') {
                    $db->where('pa.id', $attrId);
                    $db->join('product_attribute_value pav', 'pav.product_attribute_id = pa.id', 'LEFT');
                    $result = $db->get('product_attribute pa', null, 'pa.id as attribute_id, pa.name as attribute_name, pa.deleted as attr_delete, pav.id, pav.name, pav.deleted');

                    foreach ($result as $key => $val) {
                        $attributeID = $val['attribute_id'];

                        $attribute[$attributeID]['id']           = $val['attribute_id'];
                        $attribute[$attributeID]['name']         = $val['attribute_name'];

                        if ($val['attr_delete'] == 0) {
                            $attribute[$attributeID]['status']   = "Active";
                        } else {
                            $attribute[$attributeID]['status']   = "Inactive";
                        }
                        
                        $attribute[$attributeID]['value'][$key]['id']   = $val['id'];
                        $attribute[$attributeID]['value'][$key]['name'] = $val['name'];

                        if ($val['deleted'] == 0) {
                            $attribute[$attributeID]['value'][$key]['status'] = "Active";
                        } else {
                            $attribute[$attributeID]['value'][$key]['status'] = "Inactive";
                        }
                    }
                } else {
                    $db->where('product_attribute_id', $attrId);
                    $attribute = $db->get('product_attribute_value', null, 'id, name');

                    $data['inputId'] = $inputId;
                }
            }

            $data['attributeDetail'] = $attribute;

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> 'Get Attribute Successfully' , 'data'=> $data);
        }

        public function editAttribute($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTime       = date("Y-m-d H:i:s");

            $attributeID     = trim($params['attrId']);
            $attributeName   = trim($params['attribute']);
            $attributeStatus = trim($params['status']);
            $attributeValue  = $params['attributeVal'];

            if($attributeID) {
                if($attributeStatus == "Active") {
                    $attributeStatus = 0;
                } else {
                    $attributeStatus = 1;
                }

                $attrData = array(
                    "name"       => $attributeName,
                    "deleted"    => $attributeStatus,
                    "updated_at" => $dateTime,
                );

                $db->where('id', $attributeID);
                $updateAttr = $db->update("product_attribute", $attrData);
            }

            if($attributeValue) {
                foreach($attributeValue as $val) {
                    if($val['status'] == "Active") {
                        $attrStatus = 0;
                    } else {
                        $attrStatus = 1;
                    }

                    $attrValData = array(
                        "name"       => $val['name'],
                        "deleted"    => $attrStatus,
                        "updated_at" => $dateTime,
                    );
                    $db->where('id', $val['id']);
                    $db->update("product_attribute_value", $attrValData);
                }
            }

            if($updateAttr) {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => "");
            }
        }

        public function getAttributeList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $limit           = General::getLimit($pageNumber);        
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];      

            $userID = $db->userID;
            $site = $db->userType;

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                $db->where('DATE(pa.created_at)', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);

                                $db->where('DATE(pa.created_at)', date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'name':
                            $db->where('pa.name', "%".$dataValue."%", "LIKE");
                            break;
                        
                        case "status":
                            if($dataValue == "Active") {
                                $db->where("pa.deleted", 0);
                            } else if($dataValue == "Inactive") {
                                $db->where("pa.deleted", 1);
                            }
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->join('product_attribute_value pav', 'pav.product_attribute_id = pa.id', 'LEFT');
            $copyDb = $db->copy();
            $getAttributeList = $db->get('product_attribute pa', null, 'pa.id as attribute_id, pa.name as attribute_name, pav.id, pav.name');
            if(empty($getAttributeList)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            foreach ($getAttributeList as $val) {
                $attributeID = $val['attribute_id'];

                $attribute[$attributeID]['id']           = $val['attribute_id'];
                $attribute[$attributeID]['name']         = $val['attribute_name'];
                $attribute[$attributeID]['value_name'][] = $val['name'];
            }

            $totalRecord              = $copyDb->getValue("product_attribute pa", "count(pav.id)");
            $data['attributeList']    = $attribute;
            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            if($seeAll) {
                $data['totalPage']    = 1;
                $data['numRecord']    = $totalRecord;
            } else {
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['numRecord']    = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function editOrderDetails($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateTime = date('Y-m-d H:i:s');

            $SaleID = trim($params['saleID']);
            $SaleOrderDetail = $params['orderDetailArray'];
            $status = $params['status'];
            $payment_amount = $params['payment_amount'];
            $shipping_fee = $params['shipping_fee'];
            $payment_tax = $params['payment_tax'];
            $payment_method = $params['payment_method'];
            $delivery_method = $params['delivery_method'];

            if(!$SaleID) {
                return array('status' => "error", 'code' => 21, 'statusMsg' => $translations["E01051"][$language], 'data'=> "");
            }
            if(!$SaleOrderDetail) {
                return array('status' => "error", 'code' => 22, 'statusMsg' => $translations["E01186"][$language], 'data'=> "");
            }

            $db->where('id', $SaleID);
            $sale = $db->getOne('sale_order');

            if(!$sale) {
                return array('status' => "error", 'code' => 23, 'statusMsg' => $translations["E01187"][$language], 'data'=> "");
            }

            $status = (!empty($params['status'])) ? $params['status'] : $sale['status'];
            $payment_amount = (!empty($params['payment_amount'])) ? $params['payment_amount'] : $sale['payment_amount'];
            $shipping_fee = (!empty($params['shipping_fee'])) ? $params['shipping_fee'] : $sale['shipping_fee'];
            $payment_tax = (!empty($params['payment_tax'])) ? $params['payment_tax'] : $sale['payment_tax'];
            $payment_method = (!empty($params['payment_method'])) ? $params['payment_method'] : $sale['payment_method'];
            $delivery_method = (!empty($params['delivery_method'])) ? $params['delivery_method'] : $sale['delivery_method'];
     
            foreach ($SaleOrderDetail as $detailRow) {
                if(!$detailRow['product_id']) {
                    return array('status' => "error", 'code' => 24, 'statusMsg' => $translations["E01188"][$language] /* Invalid Stock. */, 'data' => "");
                }
                if(!$detailRow['product_template_id']) {
                    return array('status' => "error", 'code' => 25, 'statusMsg' => $translations["E01188"][$language] /* Invalid Stock. */, 'data' => "");
                }
                if(!$detailRow['quantity']) {
                    return array('status' => "error", 'code' => 26, 'statusMsg' => $translations["E01188"][$language] /* Invalid Stock. */, 'data' => "");
                }

                $product_idAry[$detailRow['product_id']] = $detailRow['product_id'];
                $product_template_idAry[$detailRow['product_template_id']] = $detailRow['product_template_id'];
            }

            if($product_idAry){
                $db->where("id", $product_idAry, "IN" );
                $productAry= $db->map('id')->get("product", NULL,"");
            }
            if($product_template_idAry){
                $db->where("id", $product_template_idAry, "IN" );
                $product_templateAry= $db->map('id')->get("product_template", NULL,"");
            }
            
            $updateData = array(
                "deleted"    => 1
            );
            $db->where('deleted', 0);
            $db->where('sale_id', $SaleID);
            $db->update("sale_order_detail", $updateData);
            
            $updateData = array(
                "status"    => $status,
                "payment_amount"    => $payment_amount,
                "shipping_fee"    => $shipping_fee,
                "payment_tax"    => $payment_tax,
                "payment_method"    => $payment_method,
                "delivery_method"    => $delivery_method,
            );
            $db->where('id', $SaleID);
            $db->update("sale_order", $updateData);

            foreach ($SaleOrderDetail as $detailRow) {
                unset($newRecord);
                $newRecord = array(
                    "client_id"             =>  $sale['client_id'],
                    "product_id"            =>  $detailRow['product_id'],
                    "product_template_id"   =>  $detailRow['product_template_id'],
                    "item_name"             =>  $productAry[$detailRow['product_id']]['name'],
                    "item_price"            =>  $productAry[$detailRow['product_id']]['cost'],
                    "quantity"              =>  $detailRow['quantity'],
                    "subtotal"              =>  Setting::setDecimal($productAry[$detailRow['product_id']]['cost']*$detailRow['quantity']),
                    "sale_id"               =>  $sale['id'],
                    "deleted"               =>  0,
                    "created_at"            =>  $dateTime
                );
                $db->insert("sale_order_detail", $newRecord);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => "");
        }
    }
?>
