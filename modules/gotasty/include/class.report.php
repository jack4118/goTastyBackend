<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 20/04/2018.
    **/

    class Report {
        
        function __construct($admin, $bonusReport) {
            // $this->db = $db;
            // $this->general = $general;
            // $this->setting = $setting;
            // $this->bonusReport = $bonusReport;
            // $this->tree = Admin::client->validation->bonus->tree;
            // $this->cash = Admin::client->validation->bonus->cash;
            // $this->admin = $admin;
        }

        function getFilterData($table, $getColumn, $filterAry, $limit=null){
	    	$db = MysqliDb::getFilterInstance();

    		foreach($filterAry AS $data){
    			if(empty($data['column']) || empty($data['filter'])) continue;

    			if(is_array($data['filter'])){
    				$db->where($data['column'], $data['filter'], 'IN'); // IN
    			}else if(strtolower($data['type']) == 'like'){ 
    				$db->where($data['column'], "%".$data['filter']."%", $data['type']); // LIKE
    			}else{
    				$db->where($data['column'], $data['filter']);  // MATCH
    			}
    		} // filterAry

    		// print_r($db);
			return $db->getValue($table, $getColumn, $limit);
	    }

        // function getLeaderGroupSalesReport($params, $clientID, $adminID, $site) {
        function getLeaderGroupSalesReport($params) {
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $genealogy = $params['genealogy'] ? $params['genealogy'] : "tree_sponsor";
            
            $seeAll         = (!trim($params['seeAll'])) ? "0" : $params['seeAll'];

            $usernameSearchType = $params["usernameSearchType"];

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            if($seeAll == "1")$limit = null;
            
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $adminLeaderAry = Setting::getAdminLeaderAry();
            $genealogyArray = array("tree_sponsor", "tree_placement");

            if(!in_array($genealogy, $genealogyArray)) $genealogy = "tree_sponsor";

            // UI option's data
            $db->where('status','Active');
            $db->orderBy('id', 'asc');
            $productNameList = $db->getValue('mlm_product', 'name', null);

            if(empty($productNameList))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case 'leaderUsername':

                            $filterAry[] = array('column' => 'username', 'filter' => $dataValue);
                            $leaderID = Self::getFilterData('client', "id", $filterAry, 1);
                            unset($filterAry);

                            if(empty($leaderID)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $filterAry[] = array('column'=>'trace_key', 'filter'=>$leaderID, 'type'=>'like');
                            $downlines = Self::getFilterData('tree_sponsor', "client_id", $filterAry);
                            unset($filterAry);

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            if(strtolower($dataType) == 'exclude'){
                                $db->where('client_id', $downlines, "NOT IN");
                            }else{
                                $db->where('client_id', $downlines, "IN");
                            }

                            break;

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

                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {

                        case 'username':
                            if ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                        break;

                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'product':

                            $filterAry[] = array('column' => 'name', 'filter' => $dataValue);
                            $productIDs = Self::getFilterData('mlm_product', "id", $filterAry, 1);
                            unset($filterAry);

    			            if(empty($productIDs))
    			                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

                            break;
                    }

                    unset($dataName, $dataValue, $dataType);
                }
            }

            if($adminLeaderAry){
                $db->where('client_id', $adminLeaderAry, 'IN');
            }

            $db->where('portfolio_type', array('NBVR Credit Reentry','NBVR Credit Register', 'NBV Credit Register', 'NBV Credit Reentry', 'NBV Pin Register', 'NBV Pin Reentry', 'NBVR Pin Register', 'NBVR Pin Reentry', 'NBV Package Register', 'NBV Package Reentry', 'NBVR Package Register', 'NBVR Package Reentry'), 'NOT IN');

            $db->orderBy('created_at', 'desc');
            $db->orderBy('id', 'desc');

            $copyDb = $db->copy();
            $getCountryName = "(SELECT country.name FROM country WHERE country.id=(SELECT country_id FROM client WHERE client.id=client_id)) AS country_name";
            $getUsername = "(SELECT client.username FROM client WHERE id=client_id) AS username";
            $getMemberID = "(SELECT client.member_id FROM client WHERE id=client_id) AS memberID";

            $result = $db->get('mlm_client_portfolio', $limit, 'created_at, client_id, portfolio_type, product_id, bonus_value, product_price, unit_price,'.$getCountryName.', '.$getUsername.', '.$getMemberID);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            foreach($result as $value){
                $productIDAry[$value['product_id']] = $value['product_id'];
            }

            if($productIDAry){
                $db->where('module_id', $productIDAry, "IN");
                $db->where('type', 'name');
                $db->where('language', $language);
                $prodData = $db->map('module_id')-> get('inv_language', NULL, 'module_id, content');
            }

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            $totalBV = 0;
            // $totalAmount = 0;
            $clientIDArray = array();
            foreach($result as $value) {
                $portfolio['created_at']= date($dateTimeFormat, strtotime($value['created_at']));
                
                $portfolio['client_id'] = $value['client_id'];
                $portfolio['memberID'] = $value['memberID'];
                $portfolio['username'] = $value['username'];
                $portfolio['country_name'] = $value['country_name'];
                $portfolio['product_name'] = $prodData[$value['product_id']];
                $portfolio['portfolio_type'] = $value['portfolio_type'];
                $portfolio['bonus_value'] = number_format($value['bonus_value'], $decimalPlaces, '.', '');
                // $portfolio['amount'] = $value['product_price'] * $value['unit_price'];
                // $portfolio['amount'] = number_format($portfolio['amount'], $decimalPlaces, '.', '');

                $portfolioList[] = $portfolio;
                $clientIDArray[$value['client_id']] = $value['client_id'];

                $totalBV += $value["bonus_value"];
                // $totalAmount += $portfolio['amount'];
            }
            unset($result);

            // Get user direct upline
            $uplineIDArray = array();
            if (count($clientIDArray) > 0) {
                $db->where('client_id',$clientIDArray,'IN');
                $res = $db->get('tree_sponsor',null,'client_id,upline_id');
                if (count($res) > 0) {
                    foreach ($res as $row) {
                        $uplineIDArray[$row['client_id']] = $row['upline_id'];
                    }
                }
            }

            // Get upline username
            $uplineUsernameArray = array();
            $db->where('id',$uplineIDArray,'IN');
            $res = $db->get('client',null,'id,username');
            if (count($res) > 0) {
                foreach ($res as $row) {
                    $uplineUsernameArray[$row['id']] = $row['username'];
                }
            }


            foreach ($portfolioList as &$portfolio) {
                $portfolio['upline_username'] = $uplineUsernameArray[$uplineIDArray[$portfolio['client_id']]];
            }

            // $total = '<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><th class="text-right">Total :</th><th>'.number_format($totalBV, $decimalPlaces, '.', '').'</th></tr>';

            $total = '<tr><th colspan="6" class="text-right" style="text-align: right!important;">Total :</th><th style="text-align: right!important;">'.number_format($totalBV, $decimalPlaces, '.', '').'</th></tr>';

            $totalRecord = $copyDb->getValue('mlm_client_portfolio', 'count(id)');

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return $data;
            }

            $data['portfolioList'] = $portfolioList;
            $data['total'] = $total;
            $data['productNameList'] = $productNameList;
            // $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage']              = 1;
                $data['numRecord']              = $totalRecord;
            }else{
                $data['totalPage']              = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];
            }
            $data['totalRecord'] = $totalRecord;
            // $data['numRecord'] = $limit[1];

            $db->where('status', 'Active');
            $countryList = $db->get('country', null, 'id, name');
            $data['countryList'] = $countryList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // function getSalesPlacementReport($params,$adminID) {
        function getSalesPlacementReport($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $genealogy = $params['genealogy']?:"sponsor";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();

            // *********************************************************************************
            // $db->where('name', 'leaderUsername');
            // $db->where('admin_id', $adminID);
            // $reference = $db->getValue('mlm_admin_setting', 'reference', null);
            // if(!empty($reference)) {
            //     $db->where('client_id', $reference)
            //     $clientTraceKey = $db->getValue($genealogy, 'trace_key');
            //     if(empty($clientTraceKey))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            //     $db->where('trace_key', $clientTraceKey."%", 'like');
            //     $clientDownlines = $db->get($genealogy, null, 'client_id');
            //     if(empty($clientDownlines))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            //     $db->where('client_id', $clientDownlines, 'in');
            // }
            // *********************************************************************************

            /* Get Product Data */
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'product':
                            $db->where('name', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('category',array('package'), "IN");
            $db->orderBy("mlm_product.status","ASC");
            $db->orderBy("mlm_product.priority", "ASC");
            $res = $db->get('mlm_product',null,"id, name, translation_code, status, price as bonus_value");

            if(empty($res))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            $productArray = $productIDArray = array();
            foreach($res as $row) {
                $statusDisplay = "";
                if($row["status"] != "Active") $statusDisplay = "(".$translations["A01504"][$language].")";
                $productArray[$row['id']] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'bonusValue' => $row['bonus_value'],
                    'display' => $translations[$row['translation_code']][$language].$statusDisplay
                );

                $productIDArray[] = $row['id'];
            }
            /* Get Product Data END */

            $data['productArray'] = $productArray;

            /* Get Package Sales Data */
            if(count($searchData) > 0) {

                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>$data);

                                $db->where("mlm_invoice.created_at >= '".date('Y-m-d 00:00:00', $dateFrom)."'");
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>$data);
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);


                                $db->where("mlm_invoice.created_at <= '".date('Y-m-d 23:59:59', $dateTo)."'");
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        case 'mainLeaderUsername':
                        	$cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('mlm_invoice.client_id', $mainDownlines, "IN");

                            break;
                        case 'leaderUsername':
                        	if (!$dataValue) break;

	                    	$filterAry[] = array('column' => 'username', 'filter' => $dataValue);
	                        $leaderID = Self::getFilterData('client', "id", $filterAry, 1);
	                        unset($filterAry);

	                        if(empty($leaderID)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

	                        $filterAry[] = array('column'=>'trace_key', 'filter'=>$leaderID, 'type'=>'like');
	                        if ($genealogy == 'sponsor')
                        		$downlines = Self::getFilterData('tree_sponsor', "client_id", $filterAry);
                            elseif ($genealogy == 'placement')
                        		$downlines = Self::getFilterData('tree_placement', "client_id", $filterAry);
	                        unset($filterAry);

	                        if (empty($downlines))
	                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

	                        if(strtolower($dataType) == 'exclude'){
	                        	$db->where('mlm_invoice.client_id', $downlines, "NOT IN");
	                        }else{
	                        	$db->where('mlm_invoice.client_id', $downlines, "IN");
	                        }

	                        break;

                    }
                    unset($dataName, $dataValue, $dataType, $mainLeaderID, $leaderID, $downlines);
                }
            }

            $reportData = $portfolioIDArray = array();
            // Get sales placement by package
            $db->where("mlm_invoice.product_id IN ('".implode("','",$productIDArray)."')");
            $db->where('mlm_invoice.portfolio_id',"0",'>');
            $db->where('mlm_invoice.id = mlm_invoice_item.invoice_id');
            $db->orderBy('mlm_invoice_item.id');
            $db->groupBy("mlm_invoice.portfolio_id");
            $res = $db->get('mlm_invoice_item,mlm_invoice',null,'mlm_invoice.product_id,mlm_invoice.total_amount as price,mlm_invoice.portfolio_id,DATE(mlm_invoice.created_at) as date');

            foreach ($res as $row) {
                $reportData[date('d/m/Y',strtotime($row['date']))][$row['product_id']]['bonusValue'] = $productArray[$row['product_id']]['bonusValue'];
                $reportData[date('d/m/Y',strtotime($row['date']))][$row['product_id']]['quantity'] += 1;
                $reportData[date('d/m/Y',strtotime($row['date']))][$row['product_id']]['amount'] += Setting::setDecimal($row['price']);
                $reportData[date('d/m/Y',strtotime($row['date']))]['totalAmount'] += Setting::setDecimal($row['price']);

                $portfolioIDArray[] = $row['portfolio_id'];
            }

            // Verify if portfolio exist, return error if no
            $portfolioCount = 0;
            if (count($portfolioIDArray)) {
                $db->where("id IN ('".implode("','",$portfolioIDArray)."')");
                $portfolioCount = $db->getValue('mlm_client_portfolio','COUNT(id)');
            }

            if (count($portfolioIDArray) != $portfolioCount)
                return array('status'=>'error','code'=>1,'statusMsg'=>'Missing portfolio detected, please contact support.','data'=>array('field'=>'portfolio'));
            /* Get Package Sales Data END */

            // Insert 0 as amount if there is no data
            $totalArray = array();
            foreach ($reportData as &$reportRow) {
                foreach ($productArray as $productID => $productData){
                    if (!$reportRow[$productID]) {
                        $reportRow[$productID] = array(
                            'productID' => $productID,
                            'bonusValue' => $productData['bonusValue'],
                            'quantity' => 0,
                            'amount' => 0,
                        );
                    }
                    $totalArray['grandTotal'] += Setting::setDecimal($reportRow[$productID]['amount']);

                    $totalArray[$productID]['totalQuantity'] += Setting::setDecimal($reportRow[$productID]['quantity']);
                    $totalArray[$productID]['bonusValue'] = $productData['bonusValue'];
                    $totalArray[$productID]['totalAmount'] += Setting::setDecimal($reportRow[$productID]['amount']);
                }
            }

            $totalRecord = count($reportData);
            $data['totalArray'] = $totalArray;
            $data['report'] = $reportData;
            // $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['totalPage'] = 1;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            // $data['numRecord'] = $limit[1];
            $data['numRecord'] = $totalRecord;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getSalesPurchaseReport($params) {
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;

            $seeAll         = (!trim($params['seeAll'])) ? "0" : $params['seeAll'];

            $searchData = $params['searchData'];
            $genealogy = $params['genealogy']?:"sponsor";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

            if ($seeAll == "1") {
                $limit = null;
            }
            
            $decimalPlaces  = Setting::getSystemDecimalPlaces();

            // $genealogyArray = array("tree_sponsor", "tree_placement");

            // if(!in_array($genealogy, $genealogyArray))
            //     $genealogy = "tree_sponsor";

            // *********************************************************************************
            // $db->where('name', 'leaderUsername');
            // $db->where('admin_id', $adminID);
            // $reference = $db->getValue('mlm_admin_setting', 'reference', null);
            // if(!empty($reference)) {
            //     $db->where('client_id', $reference)
            //     $clientTraceKey = $db->getValue($genealogy, 'trace_key');
            //     if(empty($clientTraceKey))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            //     $db->where('trace_key', $clientTraceKey."%", 'like');
            //     $clientDownlines = $db->get($genealogy, null, 'client_id');
            //     if(empty($clientDownlines))
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            //     $db->where('client_id', $clientDownlines, 'in');
            // }
            // *********************************************************************************

            /* Get Product Data */
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'product':
                            $db->where('name', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // $db->where('status','Active');
            $db->orderBy("status","ASC");
            $res = $db->get('mlm_product',null,'id, name, status');

            if(empty($res))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00616"][$language], 'data' => "");

            $productArray = $productIDArray = array();
            foreach($res as $row) {
                $productIDArray[$row['id']] = $row['id'];
            }

            $db->where("language", $language);
            $db->where("module", "mlm_product");
            $db->where("type", "name");
            $db->where("module_id", $productIDArray, "IN");
            $productLang = $db->map("module_id")->get("inv_language", null, "module_id, content");
            
            foreach($res as $row) {
                $statusDisplay = "";
                if($row["status"] != "Active") $statusDisplay = "(".$translations["A01504"][$language].")";
                $productArray[$row['id']] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    // 'display' => $translations[$row['translation_code']][$language].$statusDisplay
                    'display' => $productLang[$row['id']].$statusDisplay
                );

                
            }
            /* Get Product Data END */

            /* Get Credit Data */
            // $db->where('name', 'isWallet');
            $db->where('name', 'isPurchaseCredit'); 
            $db->where('value', 1); 
            $creditID = $db->get('credit_setting', null, 'id, credit_id, name'); 
            foreach ($creditID as $cData) {
                $creditIDAry[] = $cData['credit_id'];
            }

            $db->where('id', $creditIDAry, 'IN'); 
            $res = $db->get('credit', null, 'id,name,dcm,admin_translation_code, type');

            $creditArray = array();
            $creditArray[] = array('name' => "quantity", 'display' => $translations['A00627'][$language]);
            foreach ($res as $row) {
                $creditArray[$row['id']] = array(
                    'name' => $row['name'],
                    'display' => $translations[$row['admin_translation_code']][$language]
                );
                $credits[$row['type']] = $row['name'];
            }
            $creditArray[] = array('name' => "virtualAccount", 'display' => $translations['B00505'][$language]);

            /* Get Credit Data END */
            ksort($productArray);
            $data['productArray'] = $productArray;
            $data['creditArray']  = $creditArray;

            $adminLeaderAry = Setting::getAdminLeaderAry();
            if($adminLeaderAry)$db->where('client_id', $adminLeaderAry, 'IN');

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v){
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'mainLeaderUsername':
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                    }
                }

                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'username':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);


                            if(strlen($dateFrom) > 0) {
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);
                                }

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }



                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'leaderUsername':
                            if (!$dataValue) break;

                            $filterAry[] = array('column' => 'username', 'filter' => $dataValue);
                            $leaderID = Self::getFilterData('client', "id", $filterAry, 1);
                            unset($filterAry);

                            if(empty($leaderID)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

                            $filterAry[] = array('column'=>'trace_key', 'filter'=>$leaderID, 'type'=>'like');
                            if ($genealogy == 'sponsor')
                                $downlines = Self::getFilterData('tree_sponsor', "client_id", $filterAry);
                            elseif ($genealogy == 'placement')
                                $downlines = Self::getFilterData('tree_placement', "client_id", $filterAry);
                            unset($filterAry);

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

                            if(strtolower($dataType) == 'exclude'){
                                $db->where('client_id', $downlines, "NOT IN");
                            }else{
                                $db->where('client_id', $downlines, "IN");
                            }

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->groupBy("DATE(created_at)");
            $db->orderBy("created_at","DESC");
            $copyDb = $db->copy();
            $res = $copyDb->get("inv_order",NULL,"id");
            $totalRecord = $copyDb->count;

            $dateRange = $db->get("inv_order",$limit,"MAX(DATE(created_at)) AS maxDate,MIN(DATE(created_at)) AS minDate");
            //return $db ->getLastQuery();

            $minDate = end($dateRange)["minDate"];
            $maxDate = $dateRange[0]["maxDate"];

            /* A copy of db without any 'where' clause for filter use */
            $cpDb = $db->copy();
            
            // Get Invoice Data
            if($minDate) $db->where("DATE(created_at)",$minDate,">=");
            if($maxDate) $db->where("DATE(created_at)",$maxDate,"<=");
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v){
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'mainLeaderUsername':
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', 'id');

                            if ($leaderID) {
                                $cpDb->where('trace_key', "%".$leaderID."%", "LIKE");
                                $downlines = $cpDb->getValue('tree_placement', 'client_id', null);
                            }

                            if (empty($downlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $downlines, "IN");

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
                                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);
                            }

                            $db->where('client_id', $mainDownlines, "IN");
                            break;
                    }
                }

                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'username':
                            $sq = $db->subQuery();
                            $sq->where("username", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);


                            if(strlen($dateFrom) > 0) {
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);
                                }

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }



                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'leaderUsername':
                        	if (!$dataValue) break;

	                    	$filterAry[] = array('column' => 'username', 'filter' => $dataValue);
	                        $leaderID = Self::getFilterData('client', "id", $filterAry, 1);
	                        unset($filterAry);

	                        if(empty($leaderID)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

	                        $filterAry[] = array('column'=>'trace_key', 'filter'=>$leaderID, 'type'=>'like');
	                        if ($genealogy == 'sponsor')
                        		$downlines = Self::getFilterData('tree_sponsor', "client_id", $filterAry);
                            elseif ($genealogy == 'placement')
                        		$downlines = Self::getFilterData('tree_placement', "client_id", $filterAry);
	                        unset($filterAry);

	                        if (empty($downlines))
	                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

	                        if(strtolower($dataType) == 'exclude'){
	                        	$db->where('client_id', $downlines, "NOT IN");
	                        }else{
	                        	$db->where('client_id', $downlines, "IN");
	                        }

	                        break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // // Get Invoice Data
            // $res = $db->get('mlm_invoice', null, "id, product_id, DATE(created_at) as date");
            // if(empty($res)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

            // $invoiceDateArray = $invoiceIDArray = array();
            // foreach ($res as $row) {
            //     $invoiceDateArray[$row['id']] = $row['date'];
            //     $invoiceIDArray[] = $row['id'];
            //     $invoiceProductIDAry[$row["id"]] = $row["product_id"];
            // }

            // $db->where("invoice_id IN ('".implode("','",$invoiceIDArray)."')");
            // $db->orderBy('id');
            // $res = $db->get('mlm_invoice_item_payment', null, 'invoice_id, credit_type, amount');

            // if(empty($res)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);

            // $reportData = array();
            // foreach ($res as $row) {
            //     $reportData[date('d/m/Y',strtotime($invoiceDateArray[$row['invoice_id']]))]['totalAmount'] += Setting::setDecimal($row['amount']);
            //     $reportData[date('d/m/Y',strtotime($invoiceDateArray[$row['invoice_id']]))][$invoiceProductIDAry[$row['invoice_id']]]['totalAmount'] += Setting::setDecimal($row['amount']);
            //     $reportData[date('d/m/Y',strtotime($invoiceDateArray[$row['invoice_id']]))][$invoiceProductIDAry[$row['invoice_id']]][$row['credit_type']] += Setting::setDecimal($row['amount'],$row['credit_type']);
            // }

            $db->orderBy('created_at', 'DESC');
            $res = $db->get('inv_order', null, "id, DATE(created_at) as date, payment_type");

            if(empty($res)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $data);
            $invoiceDateArray = $invoiceIDArray = array();
            foreach ($res as $row) {
                $invoiceIDArray[$row['id']] = $row['id'];
                if (!in_array($row['payment_type'], array('Credit','VirtualAccount'))){
                    $row['payment_type'] = "VirtualAccount";
                }
            }

            $db->where("inv_order_id", $invoiceIDArray, "IN");
            $db->groupBy('inv_order_id');
            $db->groupBy('mlm_product_id');
            $detailRes = $db->get('inv_order_detail', null, 'inv_order_id, mlm_product_id, price, quantity');

            $db->where("inv_order_id", $invoiceIDArray, "IN");
            $invOrderPayment = $db->map('inv_order_id')->get('inv_order_payment',null, 'inv_order_id, credit_type');

            foreach($detailRes as $detailRow){
                $detailRec[$detailRow['inv_order_id']][$detailRow['mlm_product_id']]['price'] = ($detailRow['price'] * $detailRow['quantity']);
                $detailRec[$detailRow['inv_order_id']][$detailRow['mlm_product_id']]['quantity'] = $detailRow['quantity'];
            }

            $reportData = array();
            foreach($res as &$row) {
                $row['credit_type'] = $invOrderPayment[$row['id']];

                foreach($detailRec[$row['id']] as $prodID => $detail){
                    $reportDataNew[date('d/m/Y',strtotime($row['date']))]['totalAmount'] += Setting::setDecimal($detail['price']);
                    $reportDataNew[date('d/m/Y',strtotime($row['date']))]['totalUnit'] += $detail['quantity'];
                    $reportData[date('d/m/Y',strtotime($row['date']))][$prodID]['quantity'] += $detail['quantity'];

                    if($row['payment_type'] == "Credit"){
                        $reportData[date('d/m/Y',strtotime($row['date']))][$prodID][$credits[$row['credit_type']]] += Setting::setDecimal($detail['price']);
                    }else{
                        $reportData[date('d/m/Y',strtotime($row['date']))][$prodID]['virtualAccount'] += Setting::setDecimal($detail['price']);
                    }
                }
            }
                                              
            // Insert 0 as amount if there is no data
            $totalArray = array();
            foreach ($reportData as &$reportRow) {
                foreach ($productArray as $productID => $productData) {
                    if (!$reportRow[$productID]) $reportRow[$productID] = array();

                    $totalArray[$productID]['productTotal'] += Setting::setDecimal($reportRow[$productID]['totalAmount']);

                    foreach ($creditArray as $creditData) {
                        $reportRow[$productID]['quantity'] = $reportRow[$productID]['quantity']?:0;
                        $reportRow[$productID][$creditData['name']] = ($creditData['name'] == 'quantity' ? $reportRow[$productID][$creditData['name']] : Setting::setDecimal($reportRow[$productID][$creditData['name']]));

                        $totalArray[$productID][$creditData['name'].'Total'] += ($creditData['name'] == 'quantity' ? $reportRow[$productID][$creditData['name']] : Setting::setDecimal($reportRow[$productID][$creditData['name']],$creditData['name']));
                    }
                }
                ksort($reportRow);
            }

            foreach($reportData as $getDate => &$addTotalAmount){
                $addTotalAmount['totalAmount'] = Setting::setDecimal($reportDataNew[$getDate]['totalAmount']);
                $addTotalAmount['totalUnit'] = $reportDataNew[$getDate]['totalUnit'];
                $totalArray['grandTotal'] += Setting::setDecimal($reportDataNew[$getDate]['totalAmount']);
                $totalArray['grandTotalUnit'] += $reportDataNew[$getDate]['totalUnit'];
            }
            foreach($productArray as $productID => $productData){
                $totalArray[$productID]['productTotal'] = Setting::setDecimal($totalArray[$productID]['productTotal']); 
                foreach($creditArray as $creditNaming){
                    $totalArray[$productID]['quantityTotal'] = $totalArray[$productID]['quantityTotal'] ? : 0;
                    $totalArray[$productID][$creditNaming['name'].'Total'] = ($creditNaming['name'] == 'quantity' ? $totalArray[$productID][$creditNaming['name'].'Total'] : Setting::setDecimal($totalArray[$productID][$creditNaming['name'].'Total']));
                }                
            }
            $totalArray['grandTotal'] = Setting::setDecimal($totalArray['grandTotal']);

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            // $totalRecord = count($reportData);
            $data['report'] = $reportData;
            $data['totalArray'] = $totalArray;
            // $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['totalPage'] = 1;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            // $data['numRecord'] = $limit[1];
            $data['numRecord'] = $totalRecord;
            
            if($seeAll == "1"){
                $data['totalPage']   = 1;
                $data['numRecord']   = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);       
        }

        public function getOwnMonthlySalesSummary($params){
        
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlace = Setting::$systemSetting['systemDecimalFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            
            $clientID = $db->userID;
            $site = $db->userType;

            if($site != 'Member'){
                $clientID = trim($params['clientID']);
            }

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            } 

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'selectYear':
                            $year = trim($v['year']);
                            $db->where("year(created_at)",$year);

                            break;

                        case 'selectMonth':
                            $month = trim($v['month']);
                            $db->where("month(created_at)",$month);

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

            } 

            $db->groupBy("month(created_at)"); 
            $db->where('client_id', $clientID);
            $copyDb = $db->copy();

            $pvpSalesRes = $db->get("inv_order", $limit, 'created_at as date, sum(total_price) as totalSales, sum(total_pv) as totalPV');

            if(empty($pvpSalesRes))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => '');

            foreach($pvpSalesRes as $pvpSalesRow){
                $pvpSalesRow['date'] = date('m/Y', strtotime($pvpSalesRow["date"]));
                $pvpSalesRow['totalSales'] = Setting::setDecimal($pvpSalesRow["totalSales"]);
                $pvpSalesRow['totalPV'] = Setting::setDecimal($pvpSalesRow["totalPV"]);
                
                $pvpSaleArrType[] = $pvpSalesRow;
            }
            $data['pvpReport']= $pvpSaleArrType;

            $totalRecordRes = $copyDb->getValue('inv_order','count(id)',null);
            $totalRecord = $copyDb->count;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);
        }

        public function getBalanceReport($params, $userID, $site){
        
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlace = Setting::$systemSetting['systemDecimalFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);

            $usernameSearchType = $params["usernameSearchType"];

            // check if admin is agent
//            $agentID = self::getAgentID($userID);
//            if($agentID){
//                $downlineIDAry = Tree::getSponsorTreeDownlines($agentID);
//            }


            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        // case 'leaderUsername':

                        //     $clientID = $db->subQuery();
                        //     $clientID->where('username', $dataValue);
                        //     $clientID->getOne('client', "id");

                        //     $downlines = Tree::getSponsorTreeDownlines($clientID);
                        //     // $downlines[] = $clientID;

                        //     if (empty($downlines))
                        //         return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                        //     $db->where('id', $downlines, "IN");

                        //     break;

                        case 'leaderUsername':

                                $clientID = $db->subQuery();
                                $clientID->where('username', $dataValue);
                                $clientID->getOne('client', "id");

                                $downlines = Tree::getSponsorTreeDownlines($clientID);
                                // $downlines[] = $clientID;

                                if (empty($downlines))
                                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                                $db->where('id', $downlines, "IN");

                                break;

                        case 'mainLeaderUsername':

                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('id', $mainDownlines, "IN");
                            
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
                        case 'username':
                            // $db->where("username", $dataValue);

                            // $notAllCredit = TRUE;
                            if ($usernameSearchType == "match") {
                                $db->where("username", $dataValue);
                            } elseif ($usernameSearchType == "like") {
                                $db->where("username", $dataValue . "%", "LIKE");
                            }
                            break;

                        case 'name':
                            if($dataType == "like"){
                                $db->where("name", "%". $dataValue . "%", "LIKE");
                            }else{
                                $db->where("name", $dataValue);
                            }
                            break;

                        case 'memberID':
                            $db->where('member_id', $dataValue);
                            break;

                        case 'transactionDateRange':
                            // $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['dataValue']);
                            // if(strlen($dateFrom) > 0){
                            //     $dateFrom = date('Y-m-d 00:00:00', $dateFrom);
                            //     // $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            // }
                            if(strlen($dateTo) > 1) {
                                $dateTo = date('Y-m-d 23:59:59', $dateTo);
                                $db->where("created_at",$dateTo, '<=');
                            }
                            break;

                        case 'leaderUsername':

                                break;

                        case 'mainLeaderUsername':

                                break;

                        case 'sponsorID':
                            $sq = $db->subQuery();
                            $sq->where('member_id', $dataValue);
                            $sq->get('client', null, 'id');
                            $db->where('sponsor_id', $sq);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }  // if (count($searchData) > 0)

            if($downlineIDAry){
                $db->where("id", $downlineIDAry, "IN");
            }
            $db->where("type", "Client");

            if($seeAll == "1"){
                $limit = null;
            } 
            $copyDB = $db->copy();
            $grandTotalDB = $db->copy();
            $getSponsorMemberID = "(SELECT member_id FROM client sponsor WHERE sponsor.id = client.sponsor_id) as sponsor_id";
            $clientDataAry = $db->get("client", $limit, 'id, username, concat(dial_code,phone) as phone, member_id, name, '.$getSponsorMemberID);
            if(empty($clientDataAry))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => '');
            
            $creditID = $db->subQuery();
            // $creditID->where('name', 'isAgentSummary');
            // $creditID->where('value', 1);
            // $creditID->where('admin', 1);
            $creditID->get('credit_setting', null, 'credit_id');

            $db->where('id', $creditID, 'IN');
            $creditResult = $db->get("credit", null, "name, admin_translation_code");
            if(!empty($creditResult)){
                foreach($creditResult as $result){
                    $creditTypeAry[] = $result["name"];
                    $temp['display'] = $translations[$result["admin_translation_code"]][$language];
                    $temp['name'] = $result["name"];
                    $creditReturnAry[$result["name"]] = $temp;
                }
            }
            
            foreach($clientDataAry AS $key=>$clientData){
                if(!$clientData['mainLeaderUsername']) {
                    $cliID['clientID'] = $clientData['id'];
                    $mainLeaderUsername = Tree::getMainLeaderUsername($cliID);
                    $clientData['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";
                }
                if(!$clientData['sponsor_id']){
                    $clientData['sponsor_id'] = '-';
                }
                $clientDataAry[$clientData['id']] = $clientData;
                foreach($creditTypeAry AS $creditType){
                    $clientDataAry[$clientData['id']][$creditType] = 0;
                    $totalBalance[$creditType] = 0;
                } 
                $clientIDAry[$clientData['id']] = $clientData['id'];
                unset($clientDataAry[$key], $clientDataAry[$clientData['id']]['id']);
            }

            $clientBalanceAry = Cash::getAllClientBalance($clientIDAry, $creditTypeAry, $dateTo);

            $totalRecord = $copyDB->getValue("client", "count(*)");
            $data['pageNumber']  = $pageNumber;
            $data['creditType']  = $creditReturnAry;
            $data['totalRecord'] = $totalRecord;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['numRecord']   = $limit[1];

            if($seeAll == "1"){
                $data['totalPage']   = 1;
                $data['numRecord']   = $totalRecord;
            }

            foreach($clientBalanceAry AS $clientID=>$creditBalance){
                foreach($creditBalance AS $k=>$v){
                    if(!$totalBalance[$k]) $totalBalance[$k] = 0;
                    $clientDataAry[$clientID][$k] = number_format($v,$decimalPlace,".",",");
                    $totalBalance[$k] = bcadd((string)$totalBalance[$k],(string)$v,$decimalPlace);
                }
            }

            foreach ($totalBalance as &$balanceAmount) {
                $balanceAmount = number_format($balanceAmount,$decimalPlace,".",",");
            }

            $data['balanceReport']   = array_values($clientDataAry);
            $data['totalBalance'] = $totalBalance;

            // Get grand total
            unset($clientDataAry);
            $clientDataAry = $grandTotalDB->get("client", null, 'id, username, name, concat(dial_code,phone) as phone,member_id');


            // $creditTypeAry = array('bitcoin', 'ethereum', 'ripple', 'cardano', 'tether', 'eos', 'ibgCredit', 'witholdingBonus' );

            $grandTotalBalance = array();
            foreach($clientDataAry AS $key=>$clientData){
                if(!$clientData['mainLeaderUsername']) {
                    $cliID['clientID'] = $clientData['id'];
                    $mainLeaderUsername = Tree::getMainLeaderUsername($cliID);
                    $clientData['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";
                }
                $clientDataAry[$clientData['id']] = $clientData;

                foreach($creditTypeAry AS $creditType) {
                    $grandTotalBalance[$creditType] = 0;
                    $clientDataAry[$clientData['id']][$creditType] = 0;
                }
                $clientIDAry[$clientData['id']] = $clientData['id'];
                unset($clientDataAry[$key], $clientDataAry[$clientData['id']]['id']);
            }

            $clientBalanceAry = Cash::getAllClientBalance($clientIDAry, $creditTypeAry, $dateTo);
            foreach($clientBalanceAry AS $clientID=>$creditBalance){
                foreach($creditBalance AS $k=>$v){
                    if(!$grandTotalBalance[$k]) $grandTotalBalance[$k] = 0;
                    $clientDataAry[$clientID][$k] = number_format($v,$decimalPlace,".",",");
                    $grandTotalBalance[$k] = bcadd((string)$grandTotalBalance[$k],(string)$v,$decimalPlace);

                }
            }

            foreach ($grandTotalBalance as &$balanceAmount) {
                $balanceAmount = number_format($balanceAmount,$decimalPlace,".",",");
            }

            $data['allCreditTotalBalance'] = $grandTotalBalance;

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $params['header'] = array($translations['A00148'][$language],$translations['A01616'][$language],$translations['A00117'][$language],$translations['A01617'][$language]);
                $params['key'] = array('member_id','sponsor_id','name','mainLeaderUsername');

                foreach ($creditReturnAry as $creditData) {
                    $params['header'][] = $creditData['display'];
                    $params['key'][] = $creditData['name'];
                }
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);

        }

        public function getPurchaseCreditListing($params,$site){
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $decimalPlaces  = Setting::$systemSetting["internalDecimalFormat"];

            $tableName      = "credit_transaction";
            $column         = array(
                "created_at",
                "(SELECT username FROM client WHERE id = to_id) AS username",
                "(SELECT name FROM client WHERE id = to_id) AS name",
                "(SELECT member_id FROM client WHERE id = to_id) AS member_id",
                "amount",
                "type",
                "(SELECT username FROM admin WHERE id = creator_id) AS purchaseBy",
                "remark",
                "to_id"
            );
            
            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $db->where("name","isPurchasable");
            $db->where("value","1");
            $res = $db->get("credit_setting",NULL,"(SELECT translation_code FROM credit WHERE id = credit_id) AS translation_code");
            foreach($res AS $row){
                $subject = "Purchase ".$translations[$row["translation_code"]]["english"];
                $subjectArr[] = $subject;
            }

            // Means the search params is there
            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':
                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            break;

                        case 'mainLeaderUsername':
                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataType  = trim($v['dataType']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'username':
                            $sq = $db->subQuery();

                            if ($dataType == "like") {
                                $sq->where("username", "%".$dataValue."%", "LIKE");
                            }else{
                                $sq->where("username", $dataValue);
                            }
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN"); 
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN"); 
                            break;
                            
                        case 'creditType':
                            $db->where("type", $dataValue);
                                
                            break;
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                if($dateTo < $dateFrom){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);
                                }

                                if($dateTo == $dateFrom){
                                    $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                                }

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        default:
                            // $db->where($dataName, $dataValue);

                    }
                    unset($dataName);
                    unset($dataType);
                    unset($dataValue);
                }

                if (!empty($downlines)) $db->where('client_id', $downlines, "IN");
                if (!empty($searchArr)) $db->where('to_id', $searchArr, "IN");
            }

            if($site == "Member"){
                $db->where("to_id",$clientID);
            }
            // filter subject
            $db->where("subject",$subjectArr,"IN");
            $db->where("to_id","50",">");
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 
            $db->orderBy("created_at","DESC");
            $result = $db->get($tableName, $limit, $column);

            if (empty($result))return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00714"][$language], 'data' => "");

            foreach($result as $value) {
                $creditName[$value['type']] = $value['type'];
            }

            if($creditName){
                $db->where('name', $creditName, 'IN');
                $creditTranslation = $db->map('name')->get('credit', null, 'name, translation_code');
            }

            unset($mainLeader);
            foreach($result as $value) {

                if(empty($mainLeader[$value['to_id']]))
                    $mainLeader[$value['to_id']] = Tree::getMainLeaderUsername(array('clientID' => $value['to_id'] ), "memberID");

                $temp['mainLeader']      = $mainLeader[$value['to_id']] ? : "-";
                $temp['createdAt']      = date($dateTimeFormat, strtotime($value['created_at']));
                $temp['debug']          = strtotime($value['created_at']);
                $temp['type']           = $value['type'];
                $temp['creditTypeDisplay'] = $translations[$creditTranslation[$value['type']]][$language];
                $temp['username']      = $value['username'];
                $temp['name']          = $value['name'];
                $temp['memberID']      = $value['member_id'];
                $temp['amount']        = number_format($value['amount'],$decimalPlaces,'.','');
                $temp['doneBy']    = $value['purchaseBy'];
                $temp['remark']    = $value['remark'] ? : "-";
                $total += $temp['amount'];
                $list[] = $temp;
            }

            $data['grandTotal']   = $total;
            $data['purchaseListing']   = $list;
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00715"][$language], 'data' => $data);
            
        }

        public function getMonthlyPerformanceRpt($params){
        
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlace = Setting::$systemSetting['systemDecimalFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateFormat = Setting::$systemSetting['systemDateFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            
            $clientID = $db->userID;
            $site = $db->userType;

            if($site != 'Member'){
                $clientID = trim($params['clientID']);
            }

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            } 

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $bonusDate = date('Y-m-t',strtotime('first day of Last Month'));
            $currentDate = date('Y-m-d');

            $cpDb = $db->copy();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'reportMonth':
                            $bonusDate = date('Y-m-t',$dataValue);
                            break;

                        case 'mainLeaderUsername':
                            $cpDb->where('name', $dataValue);
                            $mainLeaderID = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);
                            if (empty($mainDownlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found */, 'data' => "");

                            $db->where('client_id', $mainDownlines, "IN");
                            break;

                        // case 'mainLeaderID':
                        //     $db ->where('member_id', $dataValue);
                        //     $mainLeaderID = $db ->getValue('client', 'id');
                        //     $mainDownlines = Leader::getLeaderDownlines($mainLeaderID);

                        //     if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                        //     $db->where('client_id', $mainDownlines, "IN");

                        //     break;

                        case 'mainLeaderID':
                            $mainLeaderSq = $cpDb->subQuery();
                            $mainLeaderSq->where('client_id = leader_id');
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

                            if (empty($mainDownlines)) {
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }

                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                        case 'leaderID':
                            $cpDb->where('member_id', $dataValue);
                            $leaderID = $cpDb->getValue('client', "id");

                            // $downlines = Tree::getSponsorTreeDownlines($leaderID,true);
                            $downlines = Tree::getPlacementTreeDownlines($leaderID,true);
                            if (empty($downlines)){
                                $db->resetState();
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            $db->where('client_id', $downlines, "IN");
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
                        case 'joinDate':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            $sq = $db->subQuery();
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $sq->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                $sq->where('DATE(created_at)', date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0){
                                    $sq->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                if($dateTo < $dateFrom){
                                    $sq->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>"");
                                }

                                $sq->where('DATE(created_at)', date('Y-m-d', $dateTo), '<=');
                                $sq->get('client',null,'id');
                                $db->where('client_id',$sq,"IN");
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'memberID':
                            $memberIDSq = $db->subQuery();
                            $memberIDSq->where('member_id',$dataValue);
                            $memberIDSq->get('client',null,'id');
                            $db->where('client_id',$memberIDSq,"IN");
                            break;

                        case 'name':
                            $nameSq = $db->subQuery();
                            if ($dataType == 'like') {
                                $nameSq->where('name', '%'.$dataValue. '%', 'LIKE');
                            }
                            if ($dataType == 'match') {
                                $nameSq->where('name',$dataValue);    
                            }
                            
                            $nameSq->get('client',null,'id');
                            $db->where('client_id',$nameSq,"IN");
                            break;

                        case 'rank':
                            $cpDb->where('rank_type', "Bonus Tier");
                            $cpDb->where('name', "rankDisplay");
                            $cpDb->where('LAST_DAY(created_at)', $bonusDate, "<=");
                            $cpDb->groupBy('client_id');
                            $filterMaxRank = $cpDb->get('client_rank', null, 'MAX(id) AS id');

                            foreach ($filterMaxRank as $maxRank) {
                                $maxRankID[] = $maxRank['id'];
                            }

                            $rankSq = $db->subQuery();
                            $rankSq->where('id', $maxRankID, "IN");
                            $rankSq->where('LAST_DAY(created_at)', $bonusDate, "<=");
                            $rankSq->where('rank_id', $dataValue);
                            $rankSq->getValue('client_rank', 'client_id',null);

                            $db->where('client_id', $rankSq, "IN");
                            break;

                        case 'status':
                            // $activated  = 0;
                            // if($dataValue == 'Active'){
                            //     $activated  = 1;
                            // }
                            // $db->where('b.activated',$activated);
                            $statusSq = $db->subQuery();   
                            if ($dataValue == 'Active') {
                                $statusSq->where('activated', "1");
                                $statusSq->where('disabled', "0");
                                $statusSq->where('suspended', "0");
                                $statusSq->where('`terminated`', "0");
                            }
                            if ($dataValue == 'Suspended') {
                                $statusSq->where('activated', "1");
                                $statusSq->where('disabled', "0");
                                $statusSq->where('suspended', "1");
                                $statusSq->where('`terminated`', "0");
                            }

                            if ($dataValue == 'Terminated') {
                                $statusSq->where('disabled', "0");
                                $statusSq->where('suspended', "0");
                                $statusSq->where('`terminated`', "1");
                            }
                            $statusSq->getValue('client', 'id', null);

                            $db->where('client_id', $statusSq, "IN");

                            break;

                        case 'sponsorID':
                            // $sponsorIDSq = $db->subQuery();
                            // $sponsorIDSq->where('member_id',$dataValue);
                            // $sponsorIDSq->get('client',null,'id');

                            // $db->where('b.sponsor_id',$sponsorIDSq,"IN");

                            $sponsorIDSq1 = $db->subQuery();
                            $sponsorIDSq2 = $sponsorIDSq1->subQuery();
                            $sponsorIDSq2->where('member_id', $dataValue);
                            $sponsorIDSq2->getValue('client','id',null);
                            $sponsorIDSq1->where('sponsor_id', $sponsorIDSq2, "IN");
                            $sponsorIDSq1->getValue('client','id',null);

                            $db->where('client_id', $sponsorIDSq1, "IN");
                            break;

                        case 'sponsorName':
                            // $sponsorNameDSq = $db->subQuery();
                            // $sponsorNameDSq->where('name',$dataValue);
                            // $sponsorNameDSq->get('client',null,'id');
                            // $db->where('b.sponsor_id',$sponsorNameDSq,"IN");

                            $sponsorNameSq1 = $db->subQuery();
                            $sponsorNameSq2 = $sponsorNameSq1->subQuery();
                            $sponsorNameSq2->where('name', $dataValue);
                            $sponsorNameSq2->getValue('client','id',null);
                            $sponsorNameSq1->where('sponsor_id', $sponsorNameSq2, "IN");
                            $sponsorNameSq1->getValue('client','id',null);

                            $db->where('client_id', $sponsorNameSq1, "IN");
                            break;

                        case 'placementSponsorID':
                            $placementSpsrIDSq1 = $db->subQuery();
                            $placementSpsrIDSq2 = $placementSpsrIDSq1->subQuery();
                            $placementSpsrIDSq2->where('member_id', $dataValue);
                            $placementSpsrIDSq2->getValue('client', 'id', null);
                            $placementSpsrIDSq1->where('placement_id', $placementSpsrIDSq2, "IN");
                            $placementSpsrIDSq1->getValue('client', 'id', null);

                            $db->where('client_id', $placementSpsrIDSq1, "IN");
                            break;

                        case 'placementSponsorName':
                            $placementSpsrNameSq1 = $db->subQuery();
                            $placementSpsrNameSq2 = $placementSpsrNameSq1->subQuery();
                            $placementSpsrNameSq2->where('name', $dataValue);
                            $placementSpsrNameSq2->getValue('client', 'id', null);
                            $placementSpsrNameSq1->where('placement_id', $placementSpsrNameSq2, "IN");
                            $placementSpsrNameSq1->getValue('client', 'id', null);

                            $db->where('client_id', $placementSpsrNameSq1, "IN");
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            } 

            // $column = array(
            //     "a.id",
            //     "a.client_id",
            //     "a.bonus_date",
            //     "a.level",
            //     "a.new_recruit",
            //     "a.city_id",
            //     "a.state_id",
            //     "b.activated",
            //     "b.sponsor_id",
            //     // "b.active_downline_count",
            //     // "b.active_leg",
            //     // "b.own_sales",
            //     // "b.group_sales",
            //     // "b.sponsor_sales",
            //     // "b.pgp_sales",
            // );
            // if(!$bonusDate) $bonusDate = date('Y-m-t',strtotime('first day of Last Month'));
            // $db->where('a.bonus_date',$bonusDate);
            // $db->join('client_monthly_sales b',"b.client_id = a.client_id AND DATE(b.updated_at) = DATE(a.bonus_date)","LEFT");
            // $copyDb = $db->copy();
            // $db->orderBy('a.bonus_date',"DESC");
            // $reportRes = $db->get('client_monthly_detail a',$limit,$column);

            $sponsorTreeDateQuery = "(SELECT created_at FROM client WHERE client_id = id) AS created_at";
            $sponsorTreeSq = $db->subQuery();
            $sponsorTreeSq->where('LAST_DAY(created_at)', $bonusDate, "<=");
            $sponsorTreeSq->getValue('client', 'id', null);
            $db->where('client_id', $sponsorTreeSq, "IN");
            $copyDb = $db->copy();
            $db->orderBy('created_at', "DESC");
            $sponsorTreeRes = $db->get('tree_sponsor', $limit, 'client_id, level, '. $sponsorTreeDateQuery);

            if (empty($sponsorTreeRes)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language] /* No Results Found */, 'data' => "");
            }

            foreach ($sponsorTreeRes as $sponsorTreeRow) {
                $clientIDArr[$sponsorTreeRow['client_id']] = $sponsorTreeRow['client_id'];
            }

            if($clientIDArr){
                $db->where('id',$clientIDArr,'IN');
                $clientDataArr = $db->map('id')->get('client',null,'id,name,member_id,created_at, sponsor_id, placement_id, activated, disabled, suspended, freezed, `terminated`');
            }

            // foreach ($reportRes as &$reportRow) {
            //     $reportRow['sponsor_id'] = $clientDataArr[$reportRow['client_id']]['sponsor_id'];
            // }

            foreach ($clientDataArr as &$clientData) {
                // $clientIDArr[$clientData['sponsor_id']] = $clientData['sponsor_id'];

                /* Get client's status*/
                if ($clientData['activated']) {
                    $clientData['status'] = $translations["A00372"][$language]; /* Active */
                    if ($clientData['disabled'])
                        $clientData['status'] = $translations["A00104"][$language]; /* Disable */
                    if ($clientData['freezed'])
                        $clientData['status'] = $translations["A00176"][$language]; /* Freezed */
                    if ($clientData['suspended'])
                        $clientData['status'] = $translations["A00156"][$language]; /* Suspended */
                    if ($clientData['terminated'])
                        $clientData['status'] = $translations["A01131"][$language]; /* Terminated */
                } else {
                    $clientData['status'] = $translations["A00373"][$language]; /* Inactive */
                }

                /* Get client's active until date */
                $joinTS = strtotime($clientData['created_at']);
                $currentTS = strtotime($currentDate);
                $joinYear = date("Y", $joinTS);
                $currentYear = date("Y", $currentTS);
                $yearPassed = $currentYear - $joinYear - 1;
                unset($newJoinDateTS);
               if ($yearPassed > 0) {
                    $newJoinDateTS = strtotime("+".$yearPassed." year", $joinTS);
                    $remainTS = $newJoinDateTS - $currentTS;
                    if ($remainTS > 0) {
                        $newActiveUntilTS = strtotime("+".$yearPassed." year", $joinTS);
                    } else {
                        $year = $yearPassed +1;
                        $newActiveUntilTS = strtotime("+".$year." year", $joinTS);
                    }
                } else {
                    $newActiveUntilTS = strtotime("+1 year", $joinTS);
                }

                if ($clientData['terminated'] == "0") {
                    $clientData['activeUntil'] = date($dateFormat, strtotime("-1 day", $newActiveUntilTS));
                } else {
                    $clientData['activeUntil'] = "-";
                }
            }

            if($clientIDArr){
                // $tblDate = date("Ymd", strtotime($bonusDate));
                // $db->where('table_schema', Setting::$configArray['dB']);
                // $db->where('table_name', 'tree_sponsor_cache_'.$tblDate);
                // $isTableExists = $db->getValue('information_schema.tables', 'COUNT(*)');
                // if ($isTableExists > 0) {
                //     $db->where('client_id',$clientIDArr,"IN");
                //     $sponsorTreeArr = $db->map('client_id')->get("tree_sponsor_cache_".$tblDate, null, "client_id, trace_key");
                // }

                /*Get client's address*/
                $cityNameQuery = "(SELECT name FROM city WHERE city_id = id) AS city";
                $stateNameQuery = "(SELECT name FROM state WHERE state_id = id) AS state";
                $stateCodeQuery = "(SELECT translation_code FROM state WHERE state_id = id ) AS state_translate_code";
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('address_type', "billing");
                $addressRes = $db->map('client_id')->get('address', null, 'client_id, '.$cityNameQuery. ", ".$stateNameQuery. ", ". $stateCodeQuery);

                /*Get client's main leader data*/
                $mainLeaderNameQuery = "(SELECT name FROM client WHERE leader_id = id) AS leader_name";
                $mainLeaderIDQuery = "(SELECT member_id FROM client WHERE leader_id = id) AS leader_id";
                $db->where('client_id', $clientIDArr, "IN");
                $mainLeaderRes = $db->map('client_id')->get('mlm_leader', null, "client_id, ". $mainLeaderNameQuery. ", ".$mainLeaderIDQuery);

                /*Get client placement sponsor's data*/
                $placementNameQuery = "(SELECT name FROM client WHERE upline_id = id) AS placement_name";
                $placementIDQuery = "(SELECT member_id FROM client WHERE upline_id = id) AS placement_member_id";
                $db->where('client_id', $clientIDArr, "IN");
                $placementDataArr = $db->map('client_id')->get('tree_placement', null,'client_id, client_position, '.$placementNameQuery.", ".$placementIDQuery);

                foreach($clientDataArr as &$clientData){
                    /*Get client's position*/
                    if ($placementDataArr[$clientData['id']]['client_position'] == "1") {
                        $clientData['placement_structure'] = $translations["A00200"][$language]; /* Left */
                    }
                    if ($placementDataArr[$clientData['id']]['client_position'] == "2") {
                        $clientData['placement_structure'] = $translations["A00201"][$language]; /* Right */
                    }
                }

                /*Get client sponsor's data*/
                $sponsorMemberIDQuery = "(SELECT member_id FROM client a WHERE b.sponsor_id = a.id) AS sponsor_member_id";
                $sponsorNameQuery = "(SELECT name FROM client a WHERE b.sponsor_id = a.id) AS sponsor_name";
                $db->where('id', $clientIDArr, "IN");
                $sponsorDataArr = $db->map('id')->get('client b', null, 'id, sponsor_id, '.$sponsorMemberIDQuery. ", ". $sponsorNameQuery);

                /*Get client's pvp*/
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(created_at)', $bonusDate);
                $db->groupBy('client_id');
                $pvp = $db->map('client_id')->get('mlm_bonus_in', null,'client_id, SUM(bonus_value) AS bonus_value');

                /*Get client's remaining dvp data*/
                $coupleSq = $db->subQuery();
                $coupleSq->where('client_id', $clientIDArr, "IN");
                $coupleSq->where('LAST_DAY(bonus_date)', $bonusDate);
                $coupleSq->groupBy('client_id');
                $coupleSq->groupBy('LAST_DAY(bonus_date)');
                $coupleSq->get('mlm_bonus_couple', null,'MAX(id) as id');

                $db->where('id', $coupleSq, "IN");
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(bonus_date)', $bonusDate);
                $db->groupBy('client_id');
                $remainDVPDataArr = $db->map('client_id')->get('mlm_bonus_couple', null,'client_id, remaining_dvp_1, remaining_dvp_2');

                /*Get client couple data*/
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(bonus_date)', $bonusDate);
                $db->groupBy('client_id');
                $coupleDataArr = $db->map('client_id')->get('mlm_bonus_couple', null,'client_id, SUM(total_couple) as total_couple');

                /*Get client's no. of new recruit*/
                $db->where('sponsor_id', $clientIDArr, "IN");
                $db->where('`terminated`', "0");
                $db->where('LAST_DAY(created_at)', $bonusDate);
                $db->groupBy('sponsor_id');
                $newRecruitsDataArr = $db->map('sponsor_id')->get('client', null, 'sponsor_id, count(id) as new_recruits');
            }

            // $db->where('DATE(created_at)',$bonusDate);
            // $rankIDArr = $db->map('client_id')->get('client_rank_monthly',null,'client_id,rank_id');
            $db->where('LAST_DAY(created_at)', $bonusDate, "<=");
            $db->where('name', "rankDisplay");
            $db->groupBy('client_id');
            $rankMaxID = $db->get('client_rank', null,'MAX(id) as id');

            foreach($rankMaxID as $maxID) {
                $maxIDArr[] = $maxID['id'];
            }
            if ($maxIDArr) {
                $db->where('id', $maxIDArr, "IN");
                $rankIDArr = $db->map('client_id')->get('client_rank', null, 'client_id, rank_id');
            }

            // foreach ($reportRes as $reportRow) {
            //     $sponsorTreeTrace = explode("/", $sponsorTreeArr[$reportRow['client_id']]);
            //     krsort($sponsorTreeTrace);

            //     foreach ($sponsorTreeTrace as $uplineID) {
            //         if($rankIDArr[$uplineID] >= 4 && ($uplineID != $reportRow['client_id'])){
            //             $nearDirector[$reportRow['client_id']] = $uplineID;
            //             $clientIDArr[$uplineID] = $uplineID;
            //             break;
            //         }
            //     }
            // }

            // if($clientIDArr){
            //     $db->where('id',$clientIDArr,'IN');
            //     $clientDataArr = $db->map('id')->get('client',null,'id,name,member_id,created_at');
            // }

            $rankData = $db->map('id')->get('rank',null,'id,translation_code');

            foreach ($sponsorTreeRes as $sponsorTreeData) {
                $report['clientID'] = $sponsorTreeData['client_id'];
                $report['reportDate'] = $bonusDate;
                $report['monthYear'] = date("Y M", strtotime($bonusDate));
                $report['joinDate'] = date($dateTimeFormat, strtotime($clientDataArr[$sponsorTreeData['client_id']]['created_at']));
                $report['memberID'] = $clientDataArr[$sponsorTreeData['client_id']]['member_id']?:"-";
                $report['name']     = $clientDataArr[$sponsorTreeData['client_id']]['name']?:"-";
                $report['city']     = $addressRes[$sponsorTreeData['client_id']]['city']?:"-";
                $report['province'] = $addressRes[$sponsorTreeData['client_id']]['state_translate_code']?$translations[$addressRes[$sponsorTreeData['client_id']]['state_translate_code']][$language]:$addressRes[$sponsorTreeData['client_id']]['state']?:"-";
                $report['rank']     = $translations[$rankData[$rankIDArr[$sponsorTreeData['client_id']]]][$language]?:"-";
                $report['status']   = $clientDataArr[$sponsorTreeData['client_id']]['status'];
                $report['pvp']      = Setting::setDecimal($pvp[$sponsorTreeData['client_id']])?:Setting::setDecimal(0);
                $report['couple']   = $coupleDataArr[$sponsorTreeData['client_id']]?:0;
                $report['dvpLeft']  = Setting::setDecimal($remainDVPDataArr[$sponsorTreeData['client_id']]['remaining_dvp_1'])?:Setting::setDecimal(0);
                $report['dvpRight'] = Setting::setDecimal($remainDVPDataArr[$sponsorTreeData['client_id']]['remaining_dvp_2'])?:Setting::setDecimal(0);
                $report['newRecruit'] = $newRecruitsDataArr[$sponsorTreeData['client_id']]?:0;
                $report['activeUntil'] = $clientDataArr[$sponsorTreeData['client_id']]['activeUntil'];
                $report['sponsorMemberID'] = $sponsorDataArr[$sponsorTreeData['client_id']]['sponsor_member_id']?:"-";
                $report['sponsorName'] = $sponsorDataArr[$sponsorTreeData['client_id']]['sponsor_name']?:"-";
                if ($sponsorTreeData['level'] != 0) {
                    $report['level'] = $sponsorTreeData['level']?:"-";
                } else {
                    $report['level'] = $sponsorTreeData['level'];
                }
                $report['placementStructure'] = $clientDataArr[$sponsorTreeData['client_id']]['placement_structure']?:"-";
                $report['placementSponsorID'] = $placementDataArr[$sponsorTreeData['client_id']]['placement_member_id']?:"-";
                $report['placementSponsorName'] = $placementDataArr[$sponsorTreeData['client_id']]['placement_name']?:"-";
                $report['mainLeaderID'] = $mainLeaderRes[$sponsorTreeData['client_id']]['leader_id']?:"-";
                $report['mainLeaderUsername'] = $mainLeaderRes[$sponsorTreeData['client_id']]['leader_name']?:"-";

                $reportList[] = $report;
            }

            $data['reportList']= $reportList;

            // $totalRecord = $copyDb->getValue('client_monthly_detail a','count(a.id)');
            $totalRecord = $copyDb->getValue('tree_sponsor', 'count(*)');

            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);
        }

        public function getMonthlyPerformanceDetail($params){
        
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlace = Setting::$systemSetting['systemDecimalFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateFormat = Setting::$systemSetting['systemDateFormat'];
            // $reportID       = $params['reportID'];
            $clientID = $params['clientID'];
            $bonusDate = $params['reportDate'];

            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            
            // $clientID = $db->userID;
            $site = $db->userType;

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            if($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            if(!$bonusDate) 
                $bonusDate = date('Y-m-t',strtotime('first day of Last Month'));

            /*Original Code*/
            // $db->where('id',$reportID);
            // $reportRes  = $db->getOne('client_monthly_detail','client_id,bonus_date');
            // $clientID   = $reportRes['client_id'];
            // $bonusDate  = $reportRes['bonus_date'];


            /*Original code: used to find tree sponsor cache table of a specific date*/
            // $tblDate = date("Ymd", strtotime($bonusDate));
            // $db->where('table_schema', Setting::$configArray['dB']);
            // $db->where('table_name', 'tree_sponsor_cache_'.$tblDate);
            // $isTableExists = $db->getValue('information_schema.tables', 'COUNT(*)');
            // if ($isTableExists > 0) {
            //     $db->where('trace_key',"%".$clientID."%","LIKE");
            //     $db->orderBy('level','ASC');
            //     $sponsorTreeArr = $db->map('client_id')->get("tree_sponsor_cache_".$tblDate, null, "client_id, trace_key");
            // }

            /*Get client's downline*/
            $sponsorTreeDateQuery = "(SELECT created_at FROM client WHERE client_id = id) AS created_at";
            $sponsorTreeSq = $db->subQuery();
            $sponsorTreeSq->where('LAST_DAY(created_at)', $bonusDate, "<=");
            $sponsorTreeSq->where('id', $clientID, "!=");
            $sponsorTreeSq->getValue('client', 'id', null);
            $db->where('client_id', $sponsorTreeSq, "IN");
            $db->where('trace_key', '%'.$clientID.'%', "LIKE");
            $copyDb = $db->copy();
            $db->orderBy('created_at', "DESC");
            $sponsorTreeRes = $db->get('tree_sponsor', $limit, "client_id, level, ". $sponsorTreeDateQuery);

            if(empty($sponsorTreeRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language] /* No Results Found */, 'data' => '');
            }

            /*Original Code*/
            // $column = array(
            //     "a.id",
            //     "a.client_id",
            //     "a.bonus_date",
            //     "a.level",
            //     "a.new_recruit",
            //     "a.city_id",
            //     "a.state_id",
            //     "b.activated",
            //     "b.sponsor_id",
            //     "b.active_downline_count",
            //     "b.active_leg",
            //     "b.own_sales",
            //     "b.group_sales",
            //     "b.sponsor_sales",
            //     "b.pgp_sales",
            // );

            /*Original Code*/
            // $db->where('a.client_id',array_keys($sponsorTreeArr),"IN");
            // $db->where('a.bonus_date',$bonusDate);
            // $db->join('client_monthly_sales b',"b.client_id = a.client_id AND DATE(b.updated_at) = DATE(a.bonus_date)","LEFT");
            // $copyDb = $db->copy();
            // $db->orderBy('a.level',"ASC");
            // $reportRes = $db->get('client_monthly_detail a',$limit,$column);

            // $clientIDArr = array_keys($sponsorTreeArr);

            foreach($sponsorTreeRes as $sponsorTreeRow) {
                $clientIDArr[$sponsorTreeRow['client_id']] = $sponsorTreeRow['client_id'];
            }

            // $db->where('client_id', $clientIDArr, "IN");
            // $db->where('LAST_DAY(created_at)', $bonusDate);
            // $db->groupBy('client_id');
            // $copyDb = $db->copy();
            // $db->orderBy('created_at');
            // $reportRes = $db->get('mlm_client_portfolio', $limit, 'LAST_DAY(created_at) as created_at, client_id');

            // if(empty($reportRes)) {
            //     return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => '');
            // }

            if ($clientIDArr) {
                /*Get downlines' data*/
                $db->where('id', $clientIDArr, "IN");
                $clientDataArr = $db->map('id')->get('client', null, 'id, name, member_id, created_at, sponsor_id, placement_id, activated, disabled, suspended, freezed, `terminated`');

                /*Get downlines' placement data*/
                $placementNameQuery = "(SELECT name FROM client WHERE upline_id = id) AS placement_name";
                $placementIDQuery = "(SELECT member_id FROM client WHERE upline_id = id) AS placement_member_id";
                $db->where('client_id', $clientIDArr, "IN");
                $placementDataArr = $db->map('client_id')->get('tree_placement', null, "client_id, client_position, ".$placementNameQuery.", ".$placementIDQuery);

                foreach ($clientDataArr as &$clientData) {
                    /* Get downlines' status */
                    $clientIDArr[$clientData['sponsor_id']] = $clientData['sponsor_id'];
                    if ($clientData['activated']) {
                        $clientData['status'] = $translations["A00372"][$language]; /* Active */
                        if ($clientData['disabled'])
                            $clientData['status'] = $translations["A00104"][$language]; /* Disable */
                        if ($clientData['freezed'])
                            $clientData['status'] = $translations["A00176"][$language]; /* Freezed */
                        if ($clientData['suspended'])
                            $clientData['status'] = $translations["A00156"][$language]; /* Suspended */
                        if ($clientData['terminated'])
                            $clientData['status'] = $translations["A01131"][$language]; /* Terminated */
                    } else {
                        $clientData['status'] = $translations["A00373"][$language]; /* Inactive */
                    }

                    /* Get downlines' placement structure*/
                    if ($placementDataArr[$clientData['id']]['client_position'] == "1") {
                        $clientData['placement_structure'] = $translations["A00200"][$language]; /* Left */
                    }
                    if ($placementDataArr[$clientData['id']]['client_position'] == "2") {
                        $clientData['placement_structure'] = $translations["A00201"][$language]; /* Right */
                    }

                    /* Get downlines' active until date */
                    $joinTS = strtotime($clientData['created_at']);
                    $currentTS = strtotime($currentDate);
                    $joinYear = date("Y", $joinTS);
                    $currentYear = date("Y", $currentTS);
                    $yearPassed = $currentYear - $joinYear - 1;
                    unset($newJoinDateTS);
                    if ($yearPassed > 0) {
                        $newJoinDateTS = strtotime("+".$yearPassed." year", $joinTS);
                        $remainTS = $newJoinDateTS - $currentTS;
                        if ($remainTS > 0) {
                            $newActiveUntilTS = strtotime("+".$yearPassed." year", $joinTS);
                        } else {
                            $year = $yearPassed +1;
                            $newActiveUntilTS = strtotime("+".$year." year", $joinTS);
                        }
                    } else {
                        $newActiveUntilTS = strtotime("+1 year", $joinTS);
                    }
                    if ($clientData['terminated'] == "0") {
                        $clientData['activeUntil'] = date($dateFormat, strtotime("-1 day",$newActiveUntilTS));
                    } else {
                        $clientData['activeUntil'] = "-";
                    }
                }

                /*Get downlines' address data*/
                $cityNameQuery = "(SELECT name FROM city WHERE city_id = id) AS city_name";
                $stateNameQuery = "(SELECT name FROM state WHERE state_id = id) AS state_name";
                $stateCodeQuery = "(SELECT translation_code FROM state WHERE state_id = id) AS state_translate_code";
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('address_type', "billing");
                $addressDataArr = $db->map('client_id')->get('address', null, "client_id, ".$cityNameQuery.", ".$stateNameQuery.", ". $stateCodeQuery);

                /*Get downlines' rank*/
                $clientMaxSq = $db->subQuery();
                $clientMaxSq->where('client_id', $clientIDArr, "IN");
                $clientMaxSq->where('LAST_DAY(created_at)', $bonusDate, "<=");
                $clientMaxSq->where('rank_type', "Bonus Tier");
                $clientMaxSq->where('name', "rankDisplay");
                $clientMaxSq->groupBy('client_id');
                $clientMaxSq->getValue('client_rank', 'MAX(id)', null);
                $db->where('id', $clientMaxSq, "IN");
                $clientRankDataArr = $db->map('client_id')->get('client_rank', null,'client_id, rank_id');

                /*Get downlines' main leader data*/
                $leaderNameQuery = "(SELECT name FROM client WHERE leader_id = id) AS leader_name";
                $leaderIDQuery = "(SELECT member_id FROM client WHERE leader_id = id) AS leader_member_id";
                $db->where('client_id', $clientIDArr, "IN");
                $mainLeaderDataArr = $db->map('client_id')->get('mlm_leader', null, 'client_id, '.$leaderNameQuery.", ".$leaderIDQuery);

                /*Get downlines' sponsor data*/
                $sponsorNameQuery = "(SELECT name FROM client WHERE upline_id = id) AS sponsor_name";
                $sponsorIDQuery = "(SELECT member_id FROM client WHERE upline_id = id) AS sponsor_member_id";
                $db->where('client_id', $clientIDArr, "IN");
                $sponsorDataArr = $db->map('client_id')->get('tree_sponsor', null, "client_id, ".$sponsorNameQuery.", ".$sponsorIDQuery);

                /*Get downlines' pvp*/
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(created_at)', $bonusDate);
                $db->groupBy('client_id');
                $pvpDataArr = $db->map('client_id')->get('mlm_bonus_in', null, "client_id, SUM(bonus_value) AS bonus_value");

                /*Get downlines' remaining dvp*/
                $coupleSq = $db->subQuery();
                $coupleSq->where('client_id', $clientIDArr, "IN");
                $coupleSq->where('LAST_DAY(bonus_date)', $bonusDate);
                $coupleSq->groupBy('client_id');
                $coupleSq->groupBy('LAST_DAY(bonus_date)');
                $coupleSq->get('mlm_bonus_couple', null,'MAX(id) as id');

                $db->where('id', $coupleSq, "IN");
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(bonus_date)', $bonusDate);
                $db->groupBy('client_id');
                $remainDVPDataArr = $db->map('client_id')->get('mlm_bonus_couple', null,'client_id, remaining_dvp_1, remaining_dvp_2');

                /*Get downlines' couple*/
                $db->where('client_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(bonus_date)', $bonusDate);
                $db->groupBy('client_id');
                $coupleDataArr = $db->map('client_id')->get('mlm_bonus_couple', null,'client_id, SUM(total_couple) as total_couple');

                /*Get downlines' no. of new recruits*/
                $db->where('sponsor_id', $clientIDArr, "IN");
                $db->where('LAST_DAY(created_at)', $bonusDate);
                $db->where('`terminated`', "0");
                $db->groupBy('sponsor_id');
                $newRecruitDataArr = $db->map('sponsor_id')->get('client', null, "sponsor_id, count(*)");
            }

            /*Original Code*/
            // $db->where('client_id',array_keys($sponsorTreeArr),"IN");
            // $db->where('DATE(created_at)',$bonusDate);   
            // $rankIDArr = $db->map('client_id')->get('client_rank_monthly',null,'client_id,rank_id');
            // foreach ($reportRes as $reportRow) {
            //     $clientIDArr[$reportRow['client_id']] = $reportRow['client_id'];
            //     $clientIDArr[$reportRow['sponsor_id']] = $reportRow['sponsor_id'];
            //     $cityIDArr[$reportRow['city_id']] = $reportRow['city_id'];
            //     $stateIDArr[$reportRow['state_id']] = $reportRow['state_id'];
            //     if($clientID == $reportRow['client_id']) $clientLvl = $reportRow['level'];

            //     $sponsorTreeTrace = explode("/", $sponsorTreeArr[$reportRow['client_id']]);
            //     krsort($sponsorTreeTrace);

            //     foreach ($sponsorTreeTrace as $uplineID) {
            //         if($rankIDArr[$uplineID] >= 4 && ($uplineID != $reportRow['client_id'])){
            //             $nearDirector[$reportRow['client_id']] = $uplineID;
            //             $clientIDArr[$uplineID] = $uplineID;
            //             break;
            //         }
            //     }
            //     if($rankIDArr[$reportRow['client_id']] >= 4 && ($reportRow['client_id'] != $clientID)){
            //         $count = 1;
            //         while (true) {
            //             if(!array_intersect($sponsorTreeTrace, $directGenArr[$count])){
            //                 $directGenArr[$count][$reportRow['client_id']] = $reportRow['client_id'];
            //                 $directGen[$reportRow['client_id']] = $count;
            //                 break;
            //             }
            //             $count++;
            //         }
            //     }
            // }
            // unset($directGenArr);

            /*Current client's sponsor tree level*/
            $db->where('client_id', $clientID);
            $clientLevel = $db->getValue('tree_sponsor', 'level');

            $rankData = $db->map('id')->get('rank',null,'id,translation_code');

            foreach($sponsorTreeRes as $sponsorTreeData) {
                $report['monthYear'] = date("Y M", strtotime($bonusDate));
                $report['joinDate'] = date($dateTimeFormat, strtotime($clientDataArr[$sponsorTreeData['client_id']]['created_at']));
                $report['memberID'] = $clientDataArr[$sponsorTreeData['client_id']]['member_id']?:"-";
                $report['name']     = $clientDataArr[$sponsorTreeData['client_id']]['name']?:"-";
                $report['city']     = $addressDataArr[$sponsorTreeData['client_id']]['city_name']?:"-";
                $report['province'] = $addressDataArr[$sponsorTreeData['client_id']]['state_translate_code']?$translations[$addressData[$sponsorTreeData['client_id']]['state_translate_code']][$language]:$addressDataArr[$sponsorTreeData['client_id']]['state_name']?:"-";
                $report['rank']     = $clientRankDataArr[$sponsorTreeData['client_id']]?$translations[$rankData[$clientRankDataArr[$sponsorTreeData['client_id']]]][$language]:"-";
                $report['level']    = ($sponsorTreeData['level'] - $clientLevel > 0)?($sponsorTreeData['level'] - $clientLevel):"-";
                $report['status']   = $clientDataArr[$sponsorTreeData['client_id']]['status']?:"-";
                $report['pvp']      = Setting::setDecimal($pvpDataArr[$sponsorTreeData['client_id']])?:Setting::setDecimal(0);
                $report['newRecruit'] = $newRecruitDataArr[$sponsorTreeData['client_id']]?:0;
                $report['sponsorMemberID'] = $sponsorDataArr[$sponsorTreeData['client_id']]['sponsor_member_id']?:"-";
                $report['sponsorName'] = $sponsorDataArr[$sponsorTreeData['client_id']]['sponsor_name']?:"-";
                $report['placementStructureLR'] = $clientDataArr[$sponsorTreeData['client_id']]['placement_structure']?:"-";
                $report['placementSponsorID'] = $placementDataArr[$sponsorTreeData['client_id']]['placement_member_id']?:"-";
                $report['placementSponsorName'] = $placementDataArr[$sponsorTreeData['client_id']]['placement_name']?:"-";
                $report['couple'] = $coupleDataArr[$sponsorTreeData['client_id']]?:0;
                $report['activeUntil'] = $clientDataArr[$sponsorTreeData['client_id']]['activeUntil'];
                $report['remainingLeftDVP'] = Setting::setDecimal($remainDVPDataArr[$sponsorTreeData['client_id']]['remaining_dvp_1'])?:Setting::setDecimal(0);
                $report['remainingRightDVP'] = Setting::setDecimal($remainDVPDataArr[$sponsorTreeData['client_id']]['remaining_dvp_2'])?:Setting::setDecimal(0);
                $report['mainLeaderID'] = $mainLeaderDataArr[$sponsorTreeData['client_id']]['leader_member_id']?:"-";
                $report['mainLeaderUsername'] = $mainLeaderDataArr[$sponsorTreeData['client_id']]['leader_name']?:"-";

                $reportList[] = $report;
            }

            $data['reportList']= $reportList;

            // $totalRecord = $copyDb->getValue('client_monthly_detail a','count(a.id)');
            $totalRecord = $copyDb->getValue('tree_sponsor', 'count(*)');
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);
        }

        public function getDownlinePerformanceReport($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlace = Setting::$systemSetting['systemDecimalFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $dateFormat = Setting::$systemSetting['systemDateFormat'];
            $currentMonth = date('m', strtotime(date('Y-m-d H:i:s')));

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];
            $reportDate     = date('Y-m-d', strtotime("first day of Last Month"));
            
            $clientID = $db->userID;
            $site = $db->userType;

            if (!$clientID) {
                $clientID = $params['clientID'];
            }

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            } 

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'reportMonth':
                            // $reportDate = date('Y-m-t',$dataValue);
                            // $currentMonth = date('m', strtotime($reportDate));
                            $reportMonth = date('Y-m-t', $dataValue);
                            break;
                        case 'date':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom)>0) {
                                if ($dateFrom < 0) {
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data' => "");
                                }
                                $reportDateFrom = date('Y-m-t', $dateFrom);
                            }
                            if (strlen($dateTo) > 0){
                                if($dateTo < 0) {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data', "");
                                }
                                $reportDateTo = date('Y-m-t', $dateTo);
                            }
                            break;
                    }
                }
            }

            if (!$reportMonth)
                $reportMonth = date('Y-m-t', strtotime($reportDate));

            $sponsorTreeDateQuery = "(SELECT created_at FROM client WHERE client_id = id) AS created_at"; 
            $sponsorTreeSq = $db->subQuery();
            $sponsorTreeSq->where('LAST_DAY(created_at)', $reportMonth, '<=');
            $sponsorTreeSq->where('client_id', $clientID, "!=");
            $sponsorTreeSq->getValue('client', 'id', null);
            $db->where('client_id', $sponsorTreeSq, "IN");
            $db->where('trace_key', '%'.$clientID.'%', 'LIKE');
            $copyDb = $db->copy();
            $db->orderBy('created_at', "DESC");
            // $sponsorTreeRes = $db->map('client_id')->get('tree_sponsor', null, 'client_id, upline_id, level, trace_key');
            $sponsorTreeRes = $db->get('tree_sponsor', $limit, 'client_id, level, '. $sponsorTreeDateQuery);

            if(empty($sponsorTreeRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language] /* No Results Found */, 'data' => '');
            }

            // $clientRankArr = Bonus::getClientRank("Bonus Tier", "", "", 'rankDisplay');

            // foreach ($sponsorTreeRes as $sponsorTreeRow) {
            //     $clientIDAry[$sponsorTreeRow['client_id']] = $sponsorTreeRow['client_id'];
            //     // $clientIDAry[$sponsorTreeRow['upline_id']] = $sponsorTreeRow['upline_id'];
            //     $uplineIDAry[$sponsorTreeRow['upline_id']] = $sponsorTreeRow['upline_id'];

            //     $traceKeyAry = explode('/', $sponsorTreeRow['trace_key']);

            //     foreach ($traceKeyAry as $traceKeyRow) {
            //         $rank = $clientRankArr[$traceKeyRow]['rank_id'];

            //         if($rank >= 4 && $traceKeyRow != $clientID){
            //             $nearestDirector[$sponsorTreeRow['client_id']] = $traceKeyRow;
            //             break;
            //         }
            //     }
            // }

            foreach ($sponsorTreeRes as $sponsorTreeRow) {
                $clientIDAry[$sponsorTreeRow['client_id']] = $sponsorTreeRow['client_id'];
            }

            /*Get current client data*/
            if ($clientID) {
                $db->where('id', $clientID);
                $currentClient = $db->getOne('client', 'id, member_id, name');
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry, 'IN');
                $clientRes = $db->map('id')->get('client', null, 'id, member_id, name, created_at, activated, disabled, suspended, freezed, `terminated`');

                $cityNameQuery = "(SELECT name FROM city WHERE city_id = id) AS city_name";
                $provinceNameQuery = "(SELECT name FROM state WHERE state_id = id) AS state_name";
                $provinceCodeQuery = "(SELECT translation_code FROM state WHERE state_id = id) AS state_translate_code";
                $db->where('client_id', $clientIDAry, 'IN');
                $db->where('address_type', 'billing');
                $addressRes = $db->map('client_id')->get('address', null, 'client_id, '.$cityNameQuery.", ".$provinceNameQuery. ", ". $provinceCodeQuery);

                /*Get downlines' rank data*/
                $clientRankSq = $db->subQuery();
                $clientRankSq->where('LAST_DAY(created_at)', $reportMonth, "<=");
                $clientRankSq->where('name', "rankDisplay");
                $clientRankSq->where('rank_type', "Bonus Tier");
                $clientRankSq->where('client_id', $clientIDAry, "IN");
                $clientRankSq->groupBy('client_id');
                $clientRankSq->getValue('client_rank', 'MAX(id)', null);

                $db->where('id', $clientRankSq, "IN");
                $clientRankArr = $db->map('client_id')->get('client_rank', null, 'client_id, rank_id');

                /*Get PVP*/
                $db->where('client_id', $clientIDAry, 'IN');
                $db->where('LAST_DAY(created_at)', $reportMonth);
                $db->groupBy('LAST_DAY(created_at)');
                $db->groupBy('client_id');
                // $clientSalesRes = $db->map('client_id')->get('client_sales', null, 'client_id, activated, active_downline_count, active_leg, own_sales, group_sales, sponsor_sales, pgp_sales');
                $pvpRes = $db->map('client_id')->get('mlm_bonus_in', null, 'client_id, SUM(bonus_value) as bonus_value');

                /*Get sponsor's data*/
                $sponsorNameQuery = "(SELECT name FROM client WHERE upline_id = id) AS sponsor_name";
                $sponsorIDQuery = "(SELECT member_id FROM client WHERE upline_id = id) AS sponsor_member_id";
                $db->where('client_id', $clientIDAry, "IN");
                $sponsorRes = $db->map('client_id')->get('tree_sponsor', null, "client_id, ". $sponsorNameQuery. ", ". $sponsorIDQuery);

                /*Get placement sponsor's data*/
                $placementNameQuery = "(SELECT name FROM client WHERE upline_id = id) AS placement_name";
                $placementIDQuery = "(SELECT member_id FROM client WHERE upline_id = id) AS placement_member_id";
                $db->where('client_id', $clientIDAry, "IN");
                $placementRes = $db->map('client_id')->get('tree_placement', null, "client_id, client_position, ".$placementNameQuery.", ".$placementIDQuery);

                foreach ($clientRes as &$clientRow) {
                    /*Get downlines' status*/
                    if ($clientRow['activated']) {
                        $clientRow['status'] = $translations["A00372"][$language]; /* Active */
                        if ($clientRow['disabled'])
                            $clientRow['status'] = $translations["A00104"][$language]; /* Disable */
                        if ($clientRow['freezed'])
                            $clientRow['status'] = $translations["A00176"][$language]; /* Freezed */
                        if ($clientRow['suspended'])
                            $clientRow['status'] = $translations["A00156"][$language]; /* Suspended */
                        if ($clientRow['terminated'])
                            $clientRow['status'] = $translations["A01131"][$language]; /* Terminated */
                    } else {
                        $clientRow['status'] = $translations["A00373"][$language]; /* Inactive */
                    }

                    /*Get downlines' placement position*/
                    if ($placementRes[$clientRow['id']]['client_position'] == "1") {
                        $clientRow['placement_structure'] = $translations["A00200"][$language]; /* Left */
                    }
                    if ($placementRes[$clientRow['id']]['client_position'] == "2") {
                        $clientRow['placement_structure'] = $translations["A00201"][$language]; /* Right */
                    }

                    /*Get downlines' active until date*/
                    $joinTS = strtotime($clientRow['created_at']);
                    $currentTS = strtotime($currentDate);
                    $joinYear = date("Y", $joinTS);
                    $currentYear = date("Y", $currentTS);
                    $yearPassed = $currentYear - $joinYear - 1;
                    unset($newJoinDateTS);
                    if ($yearPassed > 0) {
                        $newJoinDateTS = strtotime("+".$yearPassed." year", $joinTS);
                        $remainTS = $newJoinDateTS - $currentTS;
                        if ($remainTS > 0) {
                            $newActiveUntilTS = strtotime("+".$yearPassed." year", $joinTS);
                        } else {
                            $year = $yearPassed +1;
                            $newActiveUntilTS = strtotime("+".$year." year", $joinTS);
                        }
                    } else {
                        $newActiveUntilTS = strtotime("+1 year", $joinTS);
                    }
                    if ($clientRow['terminated'] == "0") {
                        $clientRow['activeUntil'] = date($dateFormat, strtotime("-1 day",$newActiveUntilTS));
                    } else {
                        $clientRow['activeUntil'] = "-";
                    }
                }

                /*Get client remaining_dvp data*/
                $coupleSq = $db->subQuery();
                $coupleSq->where('client_id', $clientIDAry, "IN");
                $coupleSq->where('LAST_DAY(bonus_date)', $reportMonth);
                $coupleSq->groupBy('client_id');
                $coupleSq->groupBy('LAST_DAY(bonus_date)');
                $coupleSq->get('mlm_bonus_couple', null,'MAX(id) as id');

                $db->where('id', $coupleSq, "IN");
                $db->where('client_id', $clientIDAry, "IN");
                $db->where('LAST_DAY(bonus_date)', $reportMonth);
                $db->groupBy('client_id');
                $remainDVPDataArr = $db->map('client_id')->get('mlm_bonus_couple', null,'client_id, remaining_dvp_1, remaining_dvp_2');

                /*Get client couple data*/
                $db->where('client_id', $clientIDAry, "IN");
                $db->where('LAST_DAY(bonus_date)', $reportMonth);
                $db->groupBy('client_id');
                $coupleDataArr = $db->map('client_id')->get('mlm_bonus_couple', null,'client_id, SUM(total_couple) as total_couple');

                /*Get client no. of new recruits*/
                $db->where('sponsor_id', $clientIDAry, "IN");
                $db->where('LAST_DAY(created_at)', $reportMonth);
                $db->where('`terminated`', "0");
                $db->groupBy('sponsor_id');
                $recruitRes = $db->map('sponsor_id')->get('client', null, "sponsor_id, count(*)");

            }

            foreach ($clientRankArr as $clientRankRow) {
                $rankIDAry[$clientRankRow] = $clientRankRow;
            }

            if($rankIDAry){
                $db->where('id', $rankIDAry, 'IN');
                $rankLang = $db->map('id')->get('rank', null, 'id, translation_code');
            }

            // $currentMemberLevel = $sponsorTreeRes[$clientID]['level'];
            $db->where('client_id', $clientID);
            $currentMemberLevel = $db->getOne('tree_sponsor', 'level')['level'];

            foreach($sponsorTreeRes as $sponsorTreeData) {
                $report['reportMonth'] = date('Y M', strtotime($reportMonth));
                $report['memberID']    = $clientRes[$sponsorTreeData['client_id']]['member_id'];
                $report['memberName']  = $clientRes[$sponsorTreeData['client_id']]['name'];
                $report['city']        = $addressRes[$sponsorTreeData['client_id']]['city_name'];
                $report['province']    = $addressRes[$sponsorTreeData['client_id']]['state_translate_code']?$translation[$addressRes[$sponsorTreeData['client_id']]['state_translate_code']][$language]:$addressRes[$sponsorTreeData['client_id']]['state_name']?:"-";
                $report['rank']        = $translations[$rankLang[$clientRankArr[$sponsorTreeData['client_id']]]][$language]?:"-";
                $report['status']      = $clientRes[$sponsorTreeData['client_id']]['status'];
                $report['newRecruit']  = $recruitRes[$sponsorTreeData['client_id']]?:0;
                $report['sponsorID']   = $sponsorRes[$sponsorTreeData['client_id']]['sponsor_member_id']?:"-";
                $report['sponsorName'] = $sponsorRes[$sponsorTreeData['client_id']]['sponsor_name']?:"-";
                $memberLevel           = $sponsorTreeData['level'];
                $report['memberLevel'] = $memberLevel - $currentMemberLevel;
                $report['placementStructureLR'] = $clientRes[$sponsorTreeData['client_id']]['placement_structure']?:"-";
                $report['placementSponsorID'] = $placementRes[$sponsorTreeData['client_id']]['placement_member_id']?:"-";
                $report['placementSponsorName'] = $placementRes[$sponsorTreeData['client_id']]['placement_name']?:"-";
                $report['pvp']         = Setting::setDecimal($pvpRes[$sponsorTreeData['client_id']])?:Setting::setDecimal(0);
                $report['numberCouple'] = $coupleDataArr[$sponsorTreeData['client_id']]?:0;
                $report['activeUntil'] = $clientRes[$sponsorTreeData['client_id']]['activeUntil'];
                $report['remainingLeftDVP'] = Setting::setDecimal($remainDVPDataArr[$sponsorTreeData['client_id']]['remaining_dvp_1'])?:Setting::setDecimal(0);
                $report['remainingRightDVP'] = Setting::setDecimal($remainDVPDataArr[$sponsorTreeData['client_id']]['remaining_dvp_2'])?:Setting::setDecimal(0);

                $reportList[] = $report;
            }

            $data['reportList'] = $reportList;

            $totalRecord = $copyDb->getValue('tree_sponsor', 'count(*)');
            // $totalRecord = count($copyDb->get('mlm_client_portfolio', null, 'id'));

            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00547"][$language], 'data' => $data);
        }

        public function getOwnMonthlyPerformanceReport($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlace = Setting::$systemSetting['systemDecimalFormat'];
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $clientID = $db->userID;

            if(empty($clientID))
                $clientID = $params['clientID'];

            /*Get Date Range*/
            $getDateRange = $db->map('created_at')->get("mlm_bonus_in", null, "DISTINCT LAST_DAY(created_at) AS created_at");

            /*Get Member Details*/
            $db->where('id',$clientID);
            $memberDetail = $db->getOne('client','id, member_id, name, created_at, placement_id');

            /*Get PVP of each month*/
            $db->where('client_id', $clientID);
            $db->groupBy('MONTH(created_at)');
            $pvpDataRes = $db->map('created_at')->get('mlm_bonus_in', null, 'LAST_DAY(created_at) as created_at, SUM(bonus_value) AS bonus_value');

            /*Get DVP LEFT and RIGHT of each month*/
            $db->where('client_id', $clientID);
            $memberLevel = $db->getOne('tree_placement', 'level')['level'];
            $downlineLeftSq = $db->subQuery();
            $downlineRightSq = $db->subQuery();
            if ($memberLevel == 0) {
                $db->where('trace_key', $clientID."<%", "LIKE");
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineLeft = $db->getValue('tree_placement', 'client_id', null);
                if ($downlineLeft) {
                    $downlineLeftSq->where('id', $downlineLeft, "IN");
                    $downlineLeftSq->where('`terminated`', "0");
                    $downlineLeftSq->getValue('client', 'id', null);
                    $db->where('client_id', $downlineLeftSq, "IN");
                    $db->groupBy('MONTH(created_at)');
                    $downlineLeftVP  = $db->map('created_at')->get('mlm_bonus_in', null,'LAST_DAY(created_at) as created_at, SUM(bonus_value) AS bonus_value');    
                }

                $db->where('trace_key', $clientID.">%", "LIKE");
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineRight = $db->getValue('tree_placement', 'client_id', null);  

                if ($downlineRight) {
                    $downlineRightSq->where('id', $downlineRight, "IN");
                    $downlineRightSq->where('`terminated`', "0");
                    $downlineRightSq->getValue('client', 'id', null);
                    $db->where('client_id', $downlineRightSq, "IN");
                    $db->groupBy('MONTH(created_at)');
                    $downlineRightVP  = $db->map('created_at')->get('mlm_bonus_in', null,'LAST_DAY(created_at) as created_at, SUM(bonus_value) AS bonus_value');     
                }
            } else {
                $db->where('trace_key','%'.$clientID.'-1<%','LIKE');
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineLeft = $db->getValue('tree_placement', 'client_id', null);
                if ($downlineLeft) {
                    $downlineLeftSq->where('id', $downlineLeft, "IN");
                    $downlineLeftSq->where('`terminated`', "0");
                    $downlineLeftSq->getValue('client', 'id', null);
                    $db->where('client_id', $downlineLeftSq, "IN");
                    $db->groupBy('MONTH(created_at)');
                    $downlineLeftVP  = $db->map('created_at')->get('mlm_bonus_in', null,'LAST_DAY(created_at) as created_at, SUM(bonus_value) AS bonus_value'); 
                }

                $db->where('trace_key','%'.$clientID.'-1>%','LIKE');
                $db->where('client_id',$clientID, '!=');
                $db->groupBy('client_id');
                $downlineRight = $db->getValue('tree_placement', 'client_id', null);

                if ($downlineRight) {
                    $downlineRightSq->where('id', $downlineRight, "IN");
                    $downlineRightSq->where('`terminated`', "0");
                    $downlineRightSq->getValue('client', 'id', null);
                    $db->where('client_id', $downlineRightSq, "IN");
                    $db->groupBy('MONTH(created_at)');
                    $downlineRightVP  = $db->map('created_at')->get('mlm_bonus_in', null,'LAST_DAY(created_at) as created_at, SUM(bonus_value) AS bonus_value');    
                }
            }

            $downlineLeftVP = $downlineLeftVP?:array();
            $downlineRightVP = $downlineRightVP?:array();

            /*Get total couple of each month*/
            $db->where('client_id', $clientID);
            $db->groupBy('MONTH(created_at)');
            $coupleDataRes = $db->map('created_at')->get('mlm_bonus_couple', null, 'LAST_DAY(created_at) as created_at, SUM(total_couple) AS total_couple');

            /*Get number of new recruits*/
            $newRecruitSq = $db->subQuery();
            $starterKitsSq = $newRecruitSq->subQuery();
            $starterKitsSq->where('is_starter_kit', "1");
            $starterKitsSq->getValue('mlm_product', 'id', null);
            $newRecruitSq->where('product_id', $starterKitsSq, "IN");
            $newRecruitSq->groupBy('client_id');
            $newRecruitSq->getValue('mlm_client_portfolio', 'client_id', null);
            $db->where('sponsor_id', $clientID);
            $db->where('id', $newRecruitSq, "IN");
            $db->where('`terminated`', "0");
            $db->groupBy('MONTH(created_at)');
            $newRecruitsRes = $db->map('created_at')->get('client', null, 'LAST_DAY(created_at) as created_at, count(id) as total_recruits');

            foreach($getDateRange as $dateData) {
                $monthlyPerformance[date('F', strtotime($dateData))]['pvp'] = $pvpDataRes[$dateData]?Setting::setDecimal($pvpDataRes[$dateData]):Setting::setDecimal(0);
                $monthlyPerformance[date('F', strtotime($dateData))]['dvpLeft'] = $downlineLeftVP[$dateData]?Setting::setDecimal($downlineLeftVP[$dateData]):Setting::setDecimal(0);
                $monthlyPerformance[date('F', strtotime($dateData))]['dvpRight'] = $downlineRightVP[$dateData]?Setting::setDecimal($downlineRightVP[$dateData]):Setting::setDecimal(0);
                $monthlyPerformance[date('F', strtotime($dateData))]['couples'] = $coupleDataRes[$dateData]?:0;
                $monthlyPerformance[date('F', strtotime($dateData))]['newRecruits'] = $newRecruitsRes[$dateData]?:0;
                
            }

            $data['monthlyPerformanceList'] = $monthlyPerformance;

            return array('status' => "ok", 'code' => "0", 'statusMsg' => $translations["E00547"][$language], 'data' => $data);
        }
    }
?>
