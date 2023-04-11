<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * Date 20/04/2018.
 **/

class P2P
{

    public function __construct()
    {

    }

    private function insertAdvertisement($clientID, $quantity, $price, $type, $creator_payment_id, $dateTime, $belong, $receiver_payment_id, $processingFee)
    {
        $db = MysqliDb::getInstance();

        $referenceNoLength = Setting::$systemSetting['referenceNumberLength'] ?: 8;

        $min = "1";
        $max = "9";
        for ($i = 1; $i < $referenceNoLength; $i++) {
            $max .= "9";
        }

        while (1) {
            $referenceNo = sprintf("%0" . $referenceNoLength . "s", mt_rand((int) $min, (int) $max));
            $db->where('ads_no', $referenceNo);
            $count = $db->getValue('p2p_ads', 'count(*)');
            if ($count == 0) {
                break;
            }

        }

        $insertData = array(
            'ads_no'              => $referenceNo,
            'client_id'           => $clientID,
            'quantity'            => $quantity,
            'quantity_left'       => $quantity,
            'price'               => $price,
            'type'                => $type,
            'creator_payment_id'  => $creator_payment_id,
            'status'              => 'Pending',
            'created_at'          => $dateTime,
            'belong'              => $belong,
            'receiver_payment_id' => $receiver_payment_id,
            'fee'                 => $processingFee
        );

        $adsID = $db->insert("p2p_ads", $insertData);

        return $adsID;
    }

    public function validateCreateAdvertisement($params, $userID)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $clientID             = $userID;
        $quantity             = trim($params['quantity']);
        $adsCreateMethodID    = trim($params['adsCreateMethodID']); // credit will pay for the deal
        $price                = trim($params['price']);
        $type                 = trim($params['type']);
        $adsReceivedMethodIDs = trim($params['adsReceivedMethodIDs']);
        $currentTime          = date('Y-m-d H:i:s');

        if (empty($quantity) || $quantity <= 0) {
            $errorFieldArr[] = array(
                'id'  => 'quantityError',
                'msg' => $translations['E00877'][$language],
            );
        }

        if (empty($price) || $price <= 0) {
            $errorFieldArr[] = array(
                'id'  => 'priceError',
                'msg' => 'Please Enter Valid Price',
            );
        }

        if (empty($type)) {
            $errorFieldArr[] = array(
                'id'  => 'typeError',
                'msg' => 'Please Enter a Type',
            );
        }

        if ($adsCreateMethodID) {
            $db->where('type', $type . 'Creator');
            $db->where('status', 'Active');
            $adsCreateMethodIDAry = $db->getValue('p2p_payment_method', 'id', null);

            if (!in_array($adsCreateMethodID, $adsCreateMethodIDAry)) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Create Advertisement Method.", 'data' => '');
            }

        } else {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Create Advertisement Method Not Found.", 'data' => '');
        }

        if ($adsReceivedMethodIDs) {
            $db->where('type', $type . 'Receiver');
            $db->where('status', 'Active');
            $adsReceivedMethodIDAry = $db->getValue('p2p_payment_method', 'id', null);
            if (!in_array($adsReceivedMethodIDs, $adsReceivedMethodIDAry)) {
                $errorFieldArr[] = array(
                    'id'  => 'paymentMethodError',
                    'msg' => 'Invalid payment method',
                );
            }
        } else {
            $errorFieldArr[] = array(
                'id'  => 'paymentMethodError',
                'msg' => 'Please choose a payment method',
            );
        }

        $db->where('id', $adsCreateMethodID);
        $db->where('type', $type . 'Creator');
        $creditType    = $db->getValue('p2p_payment_method', 'value');
        $creditBalance = Cash::getBalance($clientID, $creditType);

        /*Processing Fee*/
        $db->where('name', 'processP2PFee');
        $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
        foreach ($processingFeeAry as $feeData) {
            $creditFeeData[$feeData['credit_type']] = $feeData['value'];
        }

        // $sq = $db->subQuery();
        // $sq->getOne('credit', 'id');
        // $db->where('credit_id', $sq);
        // $db->where('name', 'setPriceRange');
        // $creditRateSetting = $db->map('credit_type')->getOne('credit_setting', '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value, type, reference, description');
        // if ($creditRateSetting[$creditType] && $creditRateSetting[$creditType]['value'] == '1') {
        //     $rateSetting = $creditRateSetting[$creditType];
        // } else {
        //     $db->where('id', $adsReceivedMethodIDs);
        //     $receivedType = $db->getValue('p2p_payment_method', 'value');
        //     if ($creditRateSetting[$receivedType] && $creditRateSetting[$receivedType]['value'] == '1') {
        //         $rateSetting = $creditRateSetting[$receivedType];
        //     }
        // }

        // $sq->where('type', $rateSetting['reference']);
        // $sq->where('actived_date', $currentTime, '<=');
        // $sq->getOne('mlm_unit_price', 'MAX(id)');
        // $db->where('id', $sq);
        // $creditRate = $db->getValue('mlm_unit_price', 'unit_price');

        // if ($rateSetting) {
        //     $rateRange = explode('#', $rateSetting['description']);
        //     $minPrice  = $creditRate - ($creditRate * $rateRange[0] / 100);
        //     $maxPrice  = $creditRate + ($creditRate * $rateRange[1] / 100);
        // }

        // $purchaseRate = $price;
        // if ($purchaseRate < $minPrice || $purchaseRate > $maxPrice) {
        //     $errorMsg        = str_replace(array("%%minPrice%%", "%%maxPrice%%"), array($rateRange[0],$rateRange[1]) , $translations["M02467"][$language]);
        //     $errorFieldArr[] = array(
        //         'id'  => 'priceError',
        //         'msg' => $errorMsg,
        //     );
        // }

        $sq = $db->subQuery();
        $sq->where('type', 'p2pBBIT');
        $sq->where('actived_date', $currentTime, '<=');
        $sq->getOne('mlm_unit_price', 'MAX(id)');
        $db->where('id', $sq);
        $creditRate = $db->getValue('mlm_unit_price', 'unit_price');

        if($price < $creditRate){
            $errorFieldArr[] = array(
                'id'  => 'priceError',
                'msg' => $translations["M02607"][$language],
            );
        }

        if (in_array($creditType, array_keys($creditFeeData))) {
            $processingFeePercentage = $creditFeeData[$creditType];
        } else {
            $processingFeePercentage = 0;
        }

        switch ($type) {
            case 'buy':
                $totalCreditPay = $price * $quantity;
                if (($totalCreditPay + $data['processingFeeAmount']) > $creditBalance) {
                    $errorFieldArr[] = array(
                        'id'  => 'priceError',
                        'msg' => $translations['E00876'][$language],
                    );
                }
                $creditAmount = $totalCreditPay;
                break;

            case 'sell':
                if (($quantity + $data['processingFeeAmount']) > $creditBalance) {
                    $errorFieldArr[] = array(
                        'id'  => 'quantityError',
                        'msg' => $translations['M02347'][$language],
                    );
                }
                $creditAmount = $quantity;
                break;

            default:
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Input Type.", 'data' => '');
                break;
        }

        if ($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Data does not meet requirements.', 'data' => $data);
        }

        $processingFeeAmount             = $creditAmount * $creditFeeData[$creditType] / 100;
        $data['rate']                    = $creditRate;
        $data['processingFeePercentage'] = $processingFeePercentage;
        $data['processingFeeAmount']     = $processingFeeAmount;
        $data['paymentMethodIDAry']      = $adsReceivedMethodIDs;
        $data['creditAmount']            = $creditAmount;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }

    public function createAdvertisement($params, $userID)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $clientID             = $userID;
        $quantity             = trim($params['quantity']);
        $adsCreateMethodID    = trim($params['adsCreateMethodID']); // credit will pay for the deal
        $price                = trim($params['price']);
        $type                 = trim($params['type']);
        $adsReceivedMethodIDs = trim($params['adsReceivedMethodIDs']);
        $currentTime          = date('Y-m-d H:i:s');
        $belongID             = $db->getNewID();

        $validateRes = self::validateCreateAdvertisement($params, $userID);
        if ($validateRes['status'] != 'ok') {
            return $validateRes;
        }

        $creditAmount       = $validateRes['data']['creditAmount'];
        $paymentMethodIDAry = $validateRes['data']['paymentMethodIDAry'];
        $rate               = $validateRes['data']['rate'];
        $processingFee      = $validateRes['data']['processingFeeAmount'];

        if (is_array($adsReceivedMethodIDs)) {
            $adsReceivedMethodIDs = implode("#", $adsReceivedMethodIDs);
        }

        $db->where('id', $adsCreateMethodID);
        $db->where('type', $type . 'Creator');
        $creditType = $db->getValue('p2p_payment_method', 'value');

        $sq = $db->subQuery();
        $sq->where('name', 'isP2PWallet');
        $sq->where('value', '1');
        $sq->get('credit_setting', null, 'credit_id');
        $db->where('id', $sq, 'IN');
        $p2pCreditType = $db->getValue('credit', 'type', null);

        if (in_array($creditType, $p2pCreditType)) {
            $db->where("type", "Internal");
            $db->where("username", "escrowP2P");
            $internalID     = $db->getValue("client", "id");
            // $subject        = "Add Ads";
            if($type == 'buy'){
                $subject = 'P2P Buy Ads';
            } elseif($type == 'sell'){
                $subject = 'P2P Sell Ads';
            }

            $transactionRes = Cash::insertTAccount($clientID, $internalID, $creditType, $creditAmount, $subject, $belongID, '', $currentTime, $belongID, $clientID, '', '', '', '', $rate);
            if (!$transactionRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to do transaction. Please contact customer service for support', 'data' => '');
            }
        }

        if ($processingFee > 0) {
            $db->where("type", "Internal");
            $db->where("username", "escrowP2P");
            $internalID          = $db->getValue("client", "id");
            // $feeSubject          = "P2P Processing Fee";
            if($type == 'buy'){
                $feeSubject = 'P2P Buy Ads Fee';
            } elseif($type == 'sell'){
                $feeSubject = 'P2P Sell Ads Fee';
            }

            $payProcessingFeeRes = Cash::insertTAccount($clientID, $internalID, $creditType, $processingFee, $feeSubject, $belongID, '', $currentTime, $belongID, $clientID, '', '', '', '', $rate);
            if (!$payProcessingFeeRes) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to Pay Processing Fee', 'data' => '');
            }
        }

        $addAdsRes = self::insertAdvertisement($clientID, $quantity, $price, $type, $adsCreateMethodID, $currentTime, $belongID, $adsReceivedMethodIDs, $processingFee);

        if (empty($addAdsRes)) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to created advertisement. Please contact customer service for support', 'data' => '');
        }

        return array('status' => 'ok', 'code' => 0, 'statusMsg' => 'Successful add create advertisement', 'data' => '');
    }

    public function getCreateAdvertisementData($params, $userID)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $type         = $params['type'];
        $creditType   = $params['credit_type'];
        $clientID     = $userID;
        $acceptedType = array('buy', 'sell');
        $currentTime  = date("Y-m-d H:i:s");

        if (!in_array($type, $acceptedType)) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Input Type.", 'data' => '');
        }

        $db->where('type', $type . 'Creator');
        $db->where('status', 'Active');
        $creatorPaymentType = $db->map('value')->getOne('p2p_payment_method', 'value, id, language_code');
        if (!in_array($creditType, array_keys($creatorPaymentType))) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Credit.", 'data' => '');
        }

        $db->where('name', 'processP2PFee');
        $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
        foreach ($processingFeeAry as $feeData) {
            $creditFeeData[$feeData['credit_type']] = $feeData['value'];
        }

        if (in_array($creditType, array_keys($creditFeeData))) {
            $data['processingFee'] = $creditFeeData[$creditType];
        } else {
            $data['processingFee'] = 0;
        }

        $db->where('type', $type . 'Receiver');
        $db->where('status', 'Active');
        $receiverPaymentType = $db->get('p2p_payment_method', null, 'id, type, value, language_code');
        foreach ($receiverPaymentType as &$payment) {
            $payment['display_credit'] = $translations[$payment['language_code']][$language];
            if (in_array($payment['value'], array_keys($creditFeeData))) {
                $data['processingFee'] = $creditFeeData[$payment['value']];
            }
        }

        $sq = $db->subQuery();
        $sq->where('type', 'p2pBBIT');
        $sq->where('actived_date', $currentTime, '<=');
        $sq->getOne('mlm_unit_price', 'MAX(id)');
        $db->where('id', $sq);
        $creditRate = $db->getValue('mlm_unit_price', 'unit_price');

        $creditBalance = Cash::getBalance($clientID, $creditType);

        $data['createAdstMethod'] = $creatorPaymentType;
        $data['BBITRate']         = $creditRate ? $creditRate : "1";
        $data['balance']          = $creditBalance;
        $data['payment_method']   = $receiverPaymentType;
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function getAdvertisementListing($params, $userID, $site)
    {
        $db             = MysqliDb::getInstance();
        $language       = General::$currentLanguage;
        $translations   = General::$translations;
        $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

        $myAds      = $params['myAdvertisement'];
        $searchData = $params['searchData'];
        $seeAll     = $params['seeAll'];
        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

        $limit = General::getLimit($pageNumber, '', '');

        $column = array(
            "id",
            "ads_no",
            "quantity",
            "quantity_left",
            "(SELECT username FROM client where id = client_id) AS username",
            "fee AS ads_fee",
            "(SELECT SUM(fee) FROM p2p_ads_order WHERE ads_id = p2p_ads.id GROUP BY ads_id) AS order_fee",
            "type",
            "price",
            "status",
            "created_at",
            "(SELECT value FROM p2p_payment_method WHERE id = creator_payment_id) AS created_method",
            "(SELECT value FROM p2p_payment_method WHERE id = receiver_payment_id) AS received_method",
        );

        $usernameSearchType = $params["usernameSearchType"];
        $adminLeaderAry     = Setting::getAdminLeaderAry();
        $cpDb               = $db->copy();
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName  = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                $dataType  = trim($v['dataType']);

                switch ($dataName) {

                    case 'adsNo':
                        $sq = $db->subQuery();
                        $sq->where("ads_no", $dataValue);
                        $sq->get("p2p_ads", null, "id");
                        $db->where("id", $sq, "IN");
                        break;

                    case 'userName':
                        if ($usernameSearchType == "like") {
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue . "%", "LIKE");
                            $sq->get("client", null, "id");
                            $db->where("client_id", $sq, "IN");
                        } else {
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                        }
                        break;

                    case 'type':
                        $db->where('type', $dataValue);
                        if ($dataValue == 'buy') {
                            $db->orderBy('price', 'DESC');
                        } elseif ($dataValue == 'sell') {
                            $db->orderBy('price', 'ASC');
                        }
                        break;

                    case 'status':
                        $db->where('status', $dataValue);
                        break;

                    case 'dateRange':
                        // Set db column here
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo   = trim($v['tsTo']);

                        $db->where('DATE(created_at)', date('Y-m-d', $dateFrom), '>=');
                        $db->where('DATE(created_at)', date('Y-m-d', $dateTo), '<='); //86400=24hours

                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'leaderUsername':
                        $clientID = $db->subQuery();
                        $clientID->where('username', $dataValue);
                        $clientID->getOne('client', "id");

                        $downlines = Tree::getSponsorTreeDownlines($clientID);

                        if (empty($downlines))
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

                        $db->where('client_id', $downlines, "IN");
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }
        }

        if ($adminLeaderAry) {
            $db->where('client_id', $adminLeaderAry, 'IN');
        }

        if ($site == "Member") {
            if ($myAds == '1') {
                $db->where('client_id', $userID);
                $copyDb = $db->copy();
                $db->orderBy('created_at', 'DESC');
            } else {
                $db->where('client_id', $userID, '!=');
                $db->where('quantity_left', '0', '!=');
                $db->where('status', 'Complete', '!=');
                $db->where('status', 'Cancelled', '!=');
                $copyDb = $db->copy();
                $db->orderBy('created_at', 'ASC');
            }

        } else {
            $copyDb = $db->copy();
            $db->orderBy('created_at', 'DESC');
        }

        $advertisementList = $db->get('p2p_ads', $limit, $column);

        if (empty($advertisementList)) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language]/* No Results Found. */, 'data' => "");
        }

        /*Processing Fee*/
        $db->where('name', 'processP2PFee');
        $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
        foreach ($processingFeeAry as $feeData) {
            $creditFeeData[$feeData['credit_type']] = $feeData['value'];
        }

        $totalPerPage = 0;
        foreach ($advertisementList as &$advertisement) {
            $advertisement["typeDisplay"] = General::getTranslationByName($advertisement["type"]);
            $advertisement["created_at"]         = date($dateTimeFormat, strtotime($advertisement["created_at"]));
            $advertisement["purchased_quantity"] = $advertisement["quantity"] - $advertisement["quantity_left"];

            if (in_array($advertisement['created_method'], array_keys($creditFeeData))) {
                $advertisement['processingFee']       = $creditFeeData[$advertisement['created_method']] ? $creditFeeData[$advertisement['created_method']] : 0;
                $advertisement['processingFeeAmount'] = ($advertisement['quantity'] * $advertisement['price']) * $creditFeeData[$advertisement['created_method']] / 100;
            } elseif (in_array($advertisement['received_method'], array_keys($creditFeeData))) {
                $advertisement['processingFee']       = $creditFeeData[$advertisement['created_method']] ? $creditFeeData[$advertisement['created_method']] : 0;
                $advertisement['processingFeeAmount'] = $advertisement['quantity'] * $creditFeeData[$advertisement['created_method']] / 100;
            } else {
                $advertisement['processingFee']       = 0;
                $advertisement['processingFeeAmount'] = 0;
            }
            
            $advertisement['ads_status'] = $advertisement['status'];
            switch ($advertisement['status']) {
                case 'Pending':
                    $advertisement['status'] = $translations['M02458'][$language];
                    break;

                case 'Complete':
                    $advertisement['status'] = $translations['M02459'][$language];
                    break;

                case 'Cancelled':
                    $advertisement['status'] = $translations['M02460'][$language];
                    break;

                default:
                    # code...
                    break;
            }

            $tblQty += $advertisement['quantity'] ? $advertisement['quantity'] : '0';
            $tblPurchaseQty += $advertisement['purchased_quantity'] ? $advertisement['purchased_quantity'] : '0';
            $tblTrxFees += $advertisement['ads_fee'] ? $advertisement['ads_fee'] : '0';
        }

        $tblTotalList['quantity'] = Setting::setDecimal($tblQty);
        $tblTotalList['purchased_quantity'] = Setting::setDecimal($tblPurchaseQty);
        $tblTotalList['ads_fee'] = Setting::setDecimal($tblTrxFees);    

        $copyDb->groupBy("type");
        $grandTotalData = $copyDb->get("p2p_ads", null, "type, count(id) as record, SUM(quantity) as quantity, SUM(quantity - quantity_left) as matchQuantity, SUM(fee) as fee, AVG(price) as avgPrice");
        foreach ($grandTotalData as $grandTotal) {
            $totalRecord += $grandTotal["record"];
            if($grandTotal["type"] == "buy") {
                $buyQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
                $buyMatchQty += ($grandTotal["matchQuantity"] ? $grandTotal["matchQuantity"] : '0');
                $buyTrxFees += ($grandTotal["fee"] ? $grandTotal["fee"] : '0');
                $buyAvgPrice += ($grandTotal["avgPrice"] ? $grandTotal["avgPrice"] : '0');
            } else {
                $sellQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
                $sellMatchQty += ($grandTotal["matchQuantity"] ? $grandTotal["matchQuantity"] : '0');
                $sellTrxFees += ($grandTotal["fee"] ? $grandTotal["fee"] : '0');
                $sellAvgPrice += ($grandTotal["avgPrice"] ? $grandTotal["avgPrice"] : '0');
            }
        }

        $grandTotalList['buy']['quantity'] = Setting::setDecimal($buyQty);
        $grandTotalList['buy']['purchased_quantity'] = Setting::setDecimal($buyMatchQty);
        $grandTotalList['buy']['ads_fee'] = Setting::setDecimal($buyTrxFees);
        $grandTotalList['buy']['avgPrice'] = Setting::setDecimal($buyAvgPrice);
        $grandTotalList['sell']['quantity'] = Setting::setDecimal($sellQty);
        $grandTotalList['sell']['purchased_quantity'] = Setting::setDecimal($sellMatchQty);
        $grandTotalList['sell']['ads_fee'] = Setting::setDecimal($sellTrxFees);
        $grandTotalList['sell']['avgPrice'] = Setting::setDecimal($sellAvgPrice);

        $data['advertisementList'] = $advertisementList;
        $data['tblTotalList'] = $tblTotalList;
        $data['grandTotalList'] = $grandTotalList;
        $data['pageNumber']        = $pageNumber;
        $data['totalRecord']       = $totalRecord;
        if ($seeAll == "1") {
            $data['totalPage'] = 1;
            $data['numRecord'] = $totalRecord;
        } else {
            $data['totalPage'] = ceil($totalRecord / $limit[1]);
            $data['numRecord'] = $limit[1];
        }
        $data['seeAll'] = $seeAll;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);

    }

    public function getAdvertisementDetail($params)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $adsID = trim($params['id']);
        if (empty($adsID)) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Select a advertisement to continue", 'data' => '');
        }

        $column = array(
            "id",
            "client_id",
            "(SELECT username FROM client where id = client_id) AS username",
            "ads_no",
            "type",
            "quantity",
            "quantity_left",
            "price",
            "(SELECT value FROM p2p_payment_method WHERE id = creator_payment_id) AS credit_type",
            "(SELECT language_code FROM p2p_payment_method WHERE id = creator_payment_id) AS credit_type_tc",
            "created_at",
            "status",
            "belong",
            "receiver_payment_id",
        );

        $db->where('id', $adsID);
        $advertisementData = $db->getOne('p2p_ads', $column);
        if (empty($advertisementData)) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Advertisement Not Found", 'data' => '');
        }

        $db->where('name', 'processP2PFee');
        $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
        foreach ($processingFeeAry as $feeData) {
            $creditFeeData[$feeData['credit_type']] = $feeData['value'];
        }

        $paymentMethodIDs = explode('#', $advertisementData['receiver_payment_id']);
        $db->where('id', $paymentMethodIDs, 'IN');
        $paymentMethodAry = $db->map('id')->get('p2p_payment_method', null, 'id, value, language_code');
        foreach ($paymentMethodAry as $method) {
            $advertisementData['paymentMethodDisplay'] = array(
                'methodID' => $method['id'],
                'type'     => $advertisementData['type'],
                'display'  => $translations[$method['language_code']][$language],
            );
            if (in_array($method['value'], array_keys($creditFeeData))) {
                $advertisementData['paymentMethodDisplay']['processingFee'] = $creditFeeData[$method['value']];
            } else {
                $advertisementData['paymentMethodDisplay']['processingFee'] = 0;
            }
        }

        if (in_array($advertisementData['credit_type'], array_keys($creditFeeData))) {
            $advertisementData['paymentMethodDisplay']['processingFee'] = $creditFeeData[$advertisementData['credit_type']];
        }

        $advertisementData['adsStatus'] = $advertisementData['status'];
        switch ($advertisementData['status']) {
            case 'Pending':
                $advertisementData['status'] = $translations['M02458'][$language];
                break;

            case 'Complete':
                $advertisementData['status'] = $translations['M02459'][$language];
                break;

            case 'Cancelled':
                $advertisementData['status'] = $translations['M02460'][$language];
                break;

            default:
                # code...
                break;
        }

        if (!$params['validate']) {
            $orderColumn = array(
                "(SELECT username FROM client where id = client_id) AS username",
                'quantity',
                'price',
                'created_at',
                '(SELECT amount FROM credit_transaction WHERE belong_id = belong AND subject LIKE "%Ads Fee" AND credit_transaction.client_id = p2p_ads_order.client_id) AS processingFeeAmount',
                '(SELECT language_code FROM p2p_payment_method WHERE id = payment_id) AS payment_tc',
                '(SELECT value FROM p2p_payment_method WHERE id = payment_id) AS payment_type',
            );

            $db->where('ads_id', $adsID);
            $orderList = $db->get('p2p_ads_order', null, $orderColumn);
            foreach ($orderList as &$order) {
                $order['paymentMethodDisplay'] = $translations[$order['payment_tc']][$language];
                $order['processingFee']        = $creditFeeData[$order['payment_type']] ? $creditFeeData[$order['payment_type']] : 0;
                $order['processingFeeAmount']  = $order['processingFeeAmount'] ? $order['processingFeeAmount'] : 0;
            }
        }

        unset($advertisementData['payment_method_id']);
        $data['advertisement'] = $advertisementData;
        $data['orderListing']  = $orderList;
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function validateAdvertisementOrder($params, $userID)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $clientID = $userID;
        if (empty($clientID)) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "User Not Found.", 'data' => '');
        }

        $adsID           = $params['id'];
        $quantity        = $params['quantity'];
        $paymentMethodID = $params['payment_method'];

        if (empty($adsID)) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Select a advertisement to continue", 'data' => '');
        }

        if (empty($quantity) || !is_numeric($quantity)) {
            $errorFieldArr[] = array(
                'id'  => 'quantityError',
                'msg' => 'Invalid Quantity Value',
            );
        }

        if (empty($paymentMethodID)) {
            $errorFieldArr[] = array(
                'id'  => 'paymentMethodError',
                'msg' => 'Payment Method Not Found',
            );
        }

        $params['validate'] = '1';
        $adsDetail          = self::getAdvertisementDetail($params);
        if ($adsDetail['status'] != 'ok') {
            return $adsDetail;
        }

        $adsData      = $adsDetail['data']['advertisement'];
        $quantityLeft = $adsData['quantity_left'];
        $creditType   = $adsData['credit_type'];
        $price        = $adsData['price'];
        $adsType      = $adsData['type'];
        $adsStatus    = $adsData['adsStatus'];

        if ($adsStatus != 'Pending') {
            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00878'][$language], 'data' => '');
        }

        if ($quantity > $quantityLeft) {
            $errorFieldArr[] = array(
                'id'  => 'quantityError',
                'msg' => $translations['E00877'][$language],
            );
        }

        $db->where('id', $paymentMethodID);
        $db->where('type', $adsType . "Receiver");
        $db->where('status', 'Active');
        $payMethod = $db->getOne('p2p_payment_method', 'value, language_code');

        if (empty($payMethod)) {
            $errorFieldArr[] = array(
                'id'  => 'paymentMethodError',
                'msg' => 'Invalid Payment Method',
            );
        }

        $sq = $db->subQuery();
        $sq->where('name', 'isP2PWallet');
        $sq->where('value', '1');
        $sq->get('credit_setting', null, 'credit_id');
        $db->where('id', $sq, 'IN');
        $p2pCreditType = $db->getValue('credit', 'type', null);

        if (in_array($payMethod['value'], $p2pCreditType)) {
            $payCredit = $payMethod['value'];

            $db->where('name', 'processP2PFee');
            $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
            foreach ($processingFeeAry as $feeData) {
                $creditFeeData[$feeData['credit_type']] = $feeData['value'];
            }

            $payCreditBalance = Cash::getBalance($clientID, $payCredit);

            switch ($adsType) {
                case 'buy':
                    $creditAmount        = $quantity;
                    $processingFeeAmount = $creditAmount * $creditFeeData[$payCredit] / 100;
                    if (($creditAmount + $processingFeeAmount) > $payCreditBalance) {
                        $errorFieldArr[] = array(
                            'id'  => 'quantityError',
                            'msg' => $translations['M02347'][$language],
                        );
                    }

                    break;

                case 'sell':
                    $totalCreditPay      = $price * $quantity;
                    $creditAmount        = $totalCreditPay;
                    $processingFeeAmount = $creditAmount * $creditFeeData[$payCredit] / 100;
                    if (($creditAmount + $processingFeeAmount) > $payCreditBalance) {
                        $errorFieldArr[] = array(
                            'id'  => 'priceError',
                            'msg' => $translations['M02348'][$language],
                        );
                    }
                    break;

                default:
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Input Type.", 'data' => '');
                    break;
            }
        }

        if ($errorFieldArr) {
            $data['field'] = $errorFieldArr;
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Data does not meet requirements.', 'data' => $data);
        }

        $data['creditAmount']  = $creditAmount;
        $data['creditPay']     = $payCredit;
        $data['advertisement'] = $adsData;
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function addAdvertisementOrder($params, $userID)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $clientID        = $userID;
        $adsID           = $params['id'];
        $quantity        = $params['quantity'];
        $paymentMethodID = $params['payment_method'];
        $currentTime     = date("Y-m-d H:i:s");
        $belongID        = $db->getNewID();

        $validateRes = self::validateAdvertisementOrder($params, $userID);
        if ($validateRes['status'] != 'ok') {
            return $validateRes;
        }

        $adsData        = $validateRes['data']['advertisement'];
        $creditType     = $adsData['credit_type'];
        $price          = $adsData['price'];
        $adsType        = $adsData['type'];
        $adsUserID      = $adsData['client_id'];
        $adsID          = $adsData['id'];
        $adsBelongID    = $adsData['belong'];


        $creditAmount = $validateRes['data']['creditAmount'];
        $creditPay    = $validateRes['data']['creditPay'];

        $db->where("type", "Internal");
        $db->where("username", "escrowP2P");
        $internalID = $db->getValue("client", "id");

        if ($adsType == 'buy') {
            $adsPaymentSubject  = 'P2P Sell Order'; // 'Sell From Exchange';
            $adsPaymentFeeSubject  = 'P2P Sell Order Fee'; // 'Sell From Exchange Fee';
            $adsReceivedSubject = 'P2P Received from Buy Ads'; // 'Buy From Ads';
            $adsFeeSubject = 'P2P Sell Ads Fee'; // 'Buy From Ads Fee';
        } elseif ($adsType == 'sell') {
            $adsPaymentSubject  = 'P2P Buy Order';
            $adsPaymentFeeSubject  = 'P2P Buy Order Fee';
            $adsReceivedSubject = 'P2P Received from Sell Ads';
            $adsFeeSubject = 'P2P Buy Ads Fee';
        }

        $db->startTransaction();

        $db->where('id', $adsID);
        $adsDataAry = $db->setQueryOption("FOR UPDATE")->get('p2p_ads', NULL, "id, quantity, quantity_left, fee");
        foreach ($adsDataAry as $key => $value) {
            $adsData['quantity_left'] = $value['quantity_left'];
            $adsData['fee'] = $value['fee'];
            $adsQuantity = $value['quantity'];
        }

        $quantityLeft = $adsData['quantity_left'];

        if (empty($adsData)) {
            $db->rollback();
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Data Not Found.", 'data' => '');
        }

        if ($quantity > $quantityLeft) {
            $db->rollback();
            return array('status' => "error", 'code' => 2, 'statusMsg' => ".", 'data' => '');
        }

        try {
             $insertOrder = array(
                'ads_id'     => $adsID,
                'client_id'  => $userID,
                'quantity'   => $quantity,
                'price'      => $price,
                'created_at' => $currentTime,
                'belong'     => $belongID,
                'batch_id'   => $adsBelongID,
                'payment_id' => $paymentMethodID,
            );

            $orderID = $db->insert('p2p_ads_order', $insertOrder);

            $db->where('ads_id', $adsID);
            $db->groupBy('ads_id');
            $purchasedQuantity = $db->getValue('p2p_ads_order', 'SUM(quantity)');
            if ($purchasedQuantity > $adsQuantity) {
                $db->rollback();
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Exceed Quantity Number.", 'data' => '');
            }

            $qty                        = $adsQuantity - $purchasedQuantity;
            $updateAds['quantity_left'] = $qty;
            if ($qty <= 0) {
                $updateAds['status'] = 'Complete';
            }

            $db->where('id', $adsID);
            $updatesAdsRes = $db->update('p2p_ads', $updateAds);
            if (!$updatesAdsRes) {
                $db->rollback();
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Update Ads Failed.", 'data' => '');
            }

            /* transaction pay purchase user */
            Cash::insertTAccount($clientID, $internalID, $creditPay, $creditAmount, $adsPaymentSubject, $belongID, '', $currentTime, $adsBelongID, $clientID, '', '', '', '', $rate);

            switch ($adsType) {
                case 'buy':
                    $paymentValue      = $price * $quantity;
                    Cash::insertTAccount($internalID, $clientID, $creditType, $paymentValue, $adsPaymentSubject, $belongID, '', $currentTime, $adsBelongID, $clientID, '', '', '', '', $rate);
                    break;

                case 'sell':
                    $paymentValue      = $quantity;
                    Cash::insertTAccount($internalID, $clientID, $creditType, $paymentValue, $adsPaymentSubject, $belongID, '', $currentTime, $adsBelongID, $clientID, '', '', '', '', $rate);
                    break;

                default:
                    $db->rollback();
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Input Type.", 'data' => '');
                    break;
            }

            $db->where('name', 'processP2PFee');
            $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
            foreach ($processingFeeAry as $feeData) {
                $creditFeeData[$feeData['credit_type']] = $feeData['value'];
            }

            Cash::insertTAccount($internalID, $adsUserID, $creditPay, $creditAmount, $adsReceivedSubject, $belongID, '', $currentTime, $adsBelongID, $adsUserID, '', '', '', '', $rate);

            if (in_array($creditType, array_keys($creditFeeData))) {
                $dealProcessingFeeAmount = $paymentValue * $creditFeeData[$creditType] / 100;
                $dealCreditType          = $creditType;
            } else if (in_array($creditPay, array_keys($creditFeeData))) {
                $dealProcessingFeeAmount = $creditAmount * $creditFeeData[$creditPay] / 100;
                $dealCreditType          = $creditPay;

                $processingFeeAmount = $creditAmount * $creditFeeData[$creditPay] / 100;
            } else {
                $dealProcessingFeeAmount = 0;
            }

            if ($dealProcessingFeeAmount > 0) {
                Cash::insertTAccount($clientID, $internalID, $dealCreditType, $dealProcessingFeeAmount, $adsPaymentFeeSubject, $belongID, '', $currentTime, $adsBelongID, $clientID, '', '', '', '', $rate);

                $db->where('id', $orderID);
                $db->update('p2p_ads_order', array('fee' => $dealProcessingFeeAmount));
            }

            if ($processingFeeAmount > 0) {
                Cash::insertTAccount($adsUserID, $internalID, $creditPay, $processingFeeAmount, $adsFeeSubject, $belongID, '', $currentTime, $adsBelongID, $adsUserID, '', '', '', '', $rate);
                $accFee = $adsData['fee'] + $dealProcessingFeeAmount;
                $db->where('id', $adsID);
                $db->update('p2p_ads', array('fee' => $accFee));
            }

        } catch (Exception $e) {
            $db->rollback();
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Failed Place Order.", 'data' => '');
        }

        $db->commit();
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
    }

    public function getOrderListing($params, $userID, $site)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;

        $searchData     = $params['searchData'];
        $seeAll         = $params['seeAll'];
        $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit          = General::getLimit($pageNumber, '', '');
        $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

        $column = array(
            "created_at",
            "(SELECT ads_no FROM p2p_ads WHERE id = ads_id) AS ads_no",
            "(SELECT value FROM p2p_payment_method WHERE p2p_payment_method.id = (SELECT creator_payment_id FROM p2p_ads WHERE p2p_ads.id = p2p_ads_order.ads_id)) AS credit_type",
            "quantity",
            "price",
            "(SELECT type FROM p2p_ads WHERE id = ads_id) AS ads_type",
            "(SELECT client_id FROM p2p_ads WHERE id = ads_id) AS ads_user_id",
            "(SELECT username FROM client WHERE id = (SELECT client_id FROM p2p_ads WHERE id = ads_id)) AS ads_username",
            "client_id AS order_user_id",
            "(SELECT username FROM client WHERE id = client_id) AS order_username",
            "(SELECT value FROM p2p_payment_method WHERE id = payment_id) AS payment_method",
            "(SELECT language_code FROM p2p_payment_method WHERE id = payment_id) AS language_code",
            "fee",
        );

        $usernameSearchType = $params["usernameSearchType"];
        $adminLeaderAry     = Setting::getAdminLeaderAry();
        $cpDb               = $db->copy();
        if (count($searchData) > 0) {
            foreach ($searchData as $k => $v) {
                $dataName  = trim($v['dataName']);
                $dataValue = trim($v['dataValue']);
                $dataType  = trim($v['dataType']);

                switch ($dataName) {
                    case 'userName':
                        if ($usernameSearchType == "like") {
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue . "%", "LIKE");
                            $sq->get("client", null, "id");
                            $db->where("client_id", $sq, "IN");
                        } else {
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                        }
                        break;

                    case 'adsNo':
                        $sq = $db->subQuery();
                        $sq->where("ads_no", $dataValue);
                        $sq->get("p2p_ads", null, "id");
                        $db->where("ads_id", $sq, "IN");
                        break;

                    case 'adsOwner':
                        $sq = $db->subQuery();
                        $sq->where("username", $dataValue);
                        $sq->getOne("client", "id");
                        $sq1 = $db->subQuery();
                        $sq1->where("client_id", $sq);
                        $sq1->get("p2p_ads", null, "id");
                        $db->where("ads_id", $sq1, "IN");
                        break;

                    case 'type':
                        if ($dataValue == 'buy') {
                            $filterType = 'sell';
                        } elseif ($dataValue == 'sell') {
                            $filterType = 'buy';
                        }
                        $sq = $db->subQuery();
                        $sq->where('type', $filterType);
                        $sq->get('p2p_ads', null, 'id');
                        $db->where('ads_id', $sq, 'IN');
                        break;

                    case 'dateRange':
                        // Set db column here
                        $dateFrom = trim($v['tsFrom']);
                        $dateTo   = trim($v['tsTo']);

                        $db->where('DATE(created_at)', date('Y-m-d', $dateFrom), '>=');
                        $db->where('DATE(created_at)', date('Y-m-d', $dateTo), '<='); //86400=24hours

                        unset($dateFrom);
                        unset($dateTo);
                        unset($columnName);
                        break;

                    case 'leaderUsername':
                        $clientID = $db->subQuery();
                        $clientID->where('username', $dataValue);
                        $clientID->getOne('client', "id");

                        $downlines = Tree::getSponsorTreeDownlines($clientID);

                        if (empty($downlines))
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

                        $db->where('client_id', $downlines, "IN");
                        break;
                }
                unset($dataName);
                unset($dataValue);
            }
        }

        if ($adminLeaderAry) {
            $db->where('client_id', $adminLeaderAry, 'IN');
        }

        if ($site == 'Member') {
            $db->where('client_id', $userID);
        }

        $copyDb = $db->copy();
        $db->orderBy('created_at', 'DESC');

        $orderList = $db->get('p2p_ads_order', $limit, $column);

        if (empty($orderList)) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language]/* No Results Found. */, 'data' => "");
        }

        $creditTC = $db->map('type')->get('credit', null, 'type, translation_code');

        /*Processing Fee*/
        $db->where('name', 'processP2PFee');
        $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
        foreach ($processingFeeAry as $feeData) {
            $creditFeeData[$feeData['credit_type']] = $feeData['value'];
        }

        $totalPerPage = 0;
        foreach ($orderList as $order) {
            unset($orderData);

            $orderData['created_at']  = date($dateTimeFormat, strtotime($order['created_at']));
            $orderData['ads_no']      = $order['ads_no'];
            $orderData['credit_type'] = $translations[$creditTC[$order['credit_type']]][$language];
            $orderData['quantity']    = $order['quantity'];
            $orderData['price']       = $order['price'];
            $orderData['ads_type']    = $order['ads_type'];
            if ($order['ads_type'] == 'buy') {
                $orderData['orderType'] = 'sell';
            } elseif ($order['ads_type'] == 'sell') {
                $orderData['orderType'] = 'buy';
            }
            $orderData["orderTypeDisplay"] = General::getTranslationByName($orderData["orderType"]);

            $orderData["processingFeeAmount"] = $order["fee"];
            if ($site == 'Member') {
                switch ($order['ads_type']) {
                    case 'buy':
                        if ($order['ads_user_id'] == $userID) {
                            $orderData['buyer']       = $order['ads_username'];
                            $orderData['seller']      = $order['order_username'];
                            $orderData['paid_amount'] = $order['quantity'] * $order['price'];
                            $tempAmount               = $orderData['paid_amount'];
                        } elseif ($order['order_user_id'] == $userID) {
                            $orderData['buyer']           = $order['order_username'];
                            $orderData['seller']          = $order['ads_username'];
                            $orderData['received_amount'] = $order['quantity'] * $order['price'];
                            $tempAmount                   = $orderData['received_amount'];
                        }
                        break;

                    case 'sell':
                        if ($order['ads_user_id'] == $userID) {
                            $orderData['buyer']           = $order['order_username'];
                            $orderData['seller']          = $order['ads_username'];
                            $orderData['received_amount'] = $order['quantity'] * $order['price'];
                            $tempAmount                   = $orderData['received_amount'];
                        } elseif ($order['order_user_id'] == $userID) {
                            $orderData['buyer']       = $order['ads_username'];
                            $orderData['seller']      = $order['order_username'];
                            $orderData['paid_amount'] = $order['quantity'] * $order['price'];
                            $tempAmount               = $orderData['paid_amount'];
                        }
                        break;
                }
            } else {
                $orderData['adsBy']   = $order['ads_username'];
                $orderData['orderBy'] = $order['order_username'];

                $tempAmount = $order['quantity'] * $order['price'];
            }

            $orderData['pay_with'] = $translations[$order['language_code']][$language];

            $orderDataList[] = $orderData;
            $tblQty += $orderData['quantity'] ? $orderData['quantity'] : '0';
            $tblTrxFees += $orderData['processingFeeAmount'] ? $orderData['processingFeeAmount'] : '0';

        }

        $tblTotalList['quantity'] = Setting::setDecimal($tblQty);
        $tblTotalList['processingFeeAmount'] = Setting::setDecimal($tblTrxFees);    
        
        $grandTotalData = $copyDb->get("p2p_ads_order", null, "count(id) as record, SUM(quantity) as quantity, SUM(fee) as fee, AVG(price) as avgPrice");
        foreach ($grandTotalData as $grandTotal) {
            $totalRecord += $grandTotal["record"];
            $buyQty += ($grandTotal["quantity"] ? $grandTotal["quantity"] : '0');
            $buyTrxFees += ($grandTotal["fee"] ? $grandTotal["fee"] : '0');
            $buyAvgPrice += ($grandTotal["avgPrice"] ? $grandTotal["avgPrice"] : '0');
        }

        $grandTotalList['quantity'] = Setting::setDecimal($buyQty);
        $grandTotalList['processingFeeAmount'] = Setting::setDecimal($buyTrxFees);
        $grandTotalList['avgPrice'] = Setting::setDecimal($buyAvgPrice);
        
        $data['orderList']   = $orderDataList;
        $data['tblTotalList'] = $tblTotalList;
        $data['grandTotalList'] = $grandTotalList;
        $data['pageNumber']  = $pageNumber;
        $data['totalRecord'] = $totalRecord;
        if ($seeAll == "1") {
            $data['totalPage'] = 1;
            $data['numRecord'] = $totalRecord;
        } else {
            $data['totalPage'] = ceil($totalRecord / $limit[1]);
            $data['numRecord'] = $limit[1];
        }
        $data['seeAll'] = $seeAll;

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }

    public function cancelAdvertisement($params)
    {
        $db           = MysqliDb::getInstance();
        $language     = General::$currentLanguage;
        $translations = General::$translations;
        $belongID     = $db->getNewID();

        $currentTime = date("Y-m-d H:i:s");
        $adsID       = trim($params['id']);

        $db->startTransaction();
        $db->where('id', $adsID);
        $db->where('status', 'Pending');

        $adsData = $db->setQueryOption("FOR UPDATE")->getOne('p2p_ads', 'client_id, quantity, quantity_left, price, type, (SELECT value FROM p2p_payment_method WHERE id = creator_payment_id) AS credit_type, status');

        if (!$adsData) {
            $db->rollback();
            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Advertisment Not Found', 'data' => "");
        }

        try {
            $db->where("type", "Internal");
            $db->where("username", "escrowP2P");
            $internalID = $db->getValue("client", "id");

            $remainQty  = $adsData['quantity_left'];
            $price      = $adsData['price'];
            $creditType = $adsData['credit_type'];
            $clientID   = $adsData['client_id'];
            $adsType    = $adsData['type'];

            switch ($adsType) {
                case 'buy':
                    $totoalReturn  = $remainQty * $price;
                    $subject       = "Cancel Buy Ads";
                    $returnSubject = 'Return Buy Ads Fee';
                    break;

                case 'sell':
                    $totoalReturn   = $remainQty;
                    $subject        = "Cancel Sell Ads";
                    $returnSubject  = 'Return Sell Ads Fee';
                    break;

                default:
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Input Type.", 'data' => '');
                    break;
            }

            // $subject        = "Cancel Ads";
            $transactionRes = Cash::insertTAccount($internalID, $clientID, $creditType, $totoalReturn, $subject, $belongID, '', $currentTime, $belongID, $clientID, '', '', '', '', '');

            /*Processing Fee*/
            $db->where('name', 'processP2PFee');
            $processingFeeAry = $db->get('credit_setting', null, '(SELECT type FROM credit WHERE id = credit_id) AS credit_type, value');
            foreach ($processingFeeAry as $feeData) {
                $creditFeeData[$feeData['credit_type']] = $feeData['value'];
            }

            if (in_array($creditType, array_keys($creditFeeData))) {
                $processingFeeAmount = $totoalReturn * $creditFeeData[$creditType] / 100;
                $transactionRes      = Cash::insertTAccount($internalID, $clientID, $creditType, $processingFeeAmount, $returnSubject, $belongID, '', $currentTime, $belongID, $clientID, '', '', '', '', '');
            }

            $updateAdsData = array(
                'status'     => 'Cancelled',
                'updated_at' => $currentTime,
            );
            $db->where('id', $adsID);
            $db->where('status', 'Pending');
            $updateRes = $db->update('p2p_ads', $updateAdsData);
            if (!$updateRes) {
                $db->rollback();
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed update status', 'data' => $removeRes);
            }
        } catch (Exception $e) {
            $db->rollback();
            return array('status' => "error", 'code' => 2, 'statusMsg' => "Failed Cancel Advertisement.", 'data' => '');
        }

        $db->commit();

        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M02476"][$language], 'data' => "");
    }
}
