<?php
    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');

    log::setupLogPath(__DIR__, __FILE__);

    Log::write(date("Y-m-d H:i:s")." product listing is start processing\n");

    $db->where('name', 'processGetProduct');
    $processProduct = $db->getOne('system_settings', 'id, value');
    $processing = $processProduct['value'] ? $processProduct['value'] : 0;

    if($processing){
        $message = date("Y-m-d H:i:s")." No category or product need to update\n";
        Log::write($message."\n");
        return $message;
    }

    $arrAllLang = array("english", "chineseSimplified", "chineseTraditional", "indonesian");

    // $db->where('id', $processProduct['id']);
    // $db->update('system_settings', array('value' => 1));

    $db->where('disabled', '0');
    $db->where('type', 'package');
    $categoryRes = $db->get('inv_category', null, 'id, name, type');
    foreach($categoryRes as $categoryRow) {
        $categoryIDAry[$categoryRow['id']] = $categoryRow['id'];
        $categoryDisplay[$categoryRow['id']]['name'] = $categoryRow['name'];
        // $categoryData[$categoryRow['id']]['type'] = $categoryRow['type'];
    }

    if($categoryIDAry){
        $db->where('module', 'inv_category');
        $db->where('module_id', $categoryIDAry, 'IN');
        $db->where('type', 'name');
        $categoryDisplayRes = $db->get('inv_language', null, 'module_id, content, language');
	$arrModuleLang = array();
        foreach($categoryDisplayRes as $cDisplayRow) {
            $arrModuleLang[$cDisplayRow['module_id']][] = $cDisplayRow['language'];
            $shortL = getShortenLang($cDisplayRow['language']);
            $categoryDisplay[$cDisplayRow['module_id']][$shortL] = $cDisplayRow['content'];
        }
        foreach($arrModuleLang as $mkey => $mvalue) {
            $langDiff=array_diff($arrAllLang, $mvalue);

            if(in_array("english", $mvalue) && count($langDiff)>0) {
                foreach($langDiff as $dlang) {
            	    $shortL = getShortenLang($dlang);
		    $categoryDisplay[$mkey][$shortL] = $categoryDisplay[$mkey]['eng'];
                }
            }
        }
    }

    $data['catList'] = $categoryDisplay;

    $db->where('delivery_country', 1);
    $countryList = $db->map('id')->get('country', null, 'id, currency_code');

    $data['curList'] = $countryList;

    if($categoryIDAry){
        $sq = $db->subQuery();
        $sq->where('value', $categoryIDAry, 'IN');
        $sq->where('name', 'packageCategory');
        $sq->get('mlm_product_setting', null, 'product_id');
        $db->where('id', $sq, 'IN');
        $packageRes = $db->get('mlm_product', null, 'id, name, (total_balance - total_sold) AS quantity, is_unlimited');
        foreach ($packageRes as $packageRow) {
            $quantityLeft = $packageRow['quantity'];
            $isUnlimited = $packageRow['is_unlimited'];

            if($isUnlimited == 1){
                $packageIDAry[$packageRow['id']] = $packageRow['id'];
            }

            if($quantityLeft > 0){
                $packageIDAry[$packageRow['id']] = $packageRow['id'];
            }
        }
    }
    if($packageIDAry){
        $dateTime = date("Y-m-d H:i:s");
        $db->where('id', $packageIDAry, 'IN');
        $db->where('status', 'Active');
        $db->where('active_at', $dateTime, '<=');
        $db->orderBy('code', 'ASC');
        $productRes = $db->get('mlm_product', null, 'id, name, code, pv_price, is_starter_kit');

        foreach($productRes as $productRow) {
            $productIDAry[$productRow['id']] = $productRow['id'];
        }

        if($productIDAry) {
            $db->where('product_id', $productIDAry, 'IN');
            $pSettingRes = $db->get('mlm_product_setting', null, 'product_id, name, value, type');

            $psetFilterAry = array('Inactive Image', 'Inactive Video', 'bBasic', '');
            foreach($pSettingRes as $pSetRow) {
                // do checking if is Image / Video
                if(in_array($pSetRow['type'], $psetFilterAry)) continue;
                $productSetting[$pSetRow['product_id']][$pSetRow['name']][] = $pSetRow['value'];

                if($pSetRow['type'] == "packageCategory"){
                    $packageCategory[$pSetRow['product_id']][$pSetRow['name']][] = $pSetRow['value'];
                }                
            }

            $db->where('module', 'mlm_product');
            $db->where('module_id', $productIDAry, 'IN');
            // $db->where('language', $language);
            $db->where('type', array('name', 'desc'), 'IN');
            $productDisplayRes = $db->get('inv_language', null, 'module_id, type, content, language');

            $arrProductLang = array();
            foreach($productDisplayRes as $pDisplayRow) {
		if(!in_array($pDisplayRow['language'], $arrProductLang[$pDisplayRow['module_id']])){
                    $arrProductLang[$pDisplayRow['module_id']][] = $pDisplayRow['language'];
		}
                $shortL = getShortenLang($pDisplayRow['language']);
                $productDisplay[$pDisplayRow['module_id']][$shortL][$pDisplayRow['type']] = $pDisplayRow['content'];
            }

            foreach($arrProductLang as $pkey => $pvalue) {
                $langDiff=array_diff($arrAllLang, $pvalue);

                if(in_array("english", $pvalue) && count($langDiff)>0) {
                    foreach($langDiff as $dlang) {
                    $shortL = getShortenLang($dlang);
                    $productDisplay[$pkey][$shortL] = $productDisplay[$pkey]['eng'];
                }
            }
        }

            $db->where('disabled', 0);
            $db->where('product_id', $productIDAry, 'IN');
            $priceRes = $db->get('mlm_product_price', null, 'product_id, country_id, price, promo_price, m_price, ms_price');
            foreach ($priceRes as $priceRow) {
                $price['country'] = $priceRow['country_id'];
                $price['retail'] = Setting::setDecimal($priceRow['price']) ? : 0;
                $price['promo'] = Setting::setDecimal($priceRow['promo_price']) ? : 0;
                $price['mPrice'] = Setting::setDecimal($priceRow['m_price']) ? : 0;
                $price['msPrice'] = Setting::setDecimal($priceRow['ms_price']) ? : 0;
                $priceSetting[$priceRow['product_id']][$priceRow['country_id']] = $price;
            }
        }
    }

    foreach($productRes as $productRow) {
    	$productData = $productDisplay[$productRow['id']];
        $productData['price'] = $priceSetting[$productRow['id']];

        $productData['id'] = $productRow['id'];
        $productData['code'] = $productRow['code'];
        $productData['pv'] = Setting::setDecimal($productRow['pv_price']);
        $productData['img'] = $productSetting[$productRow['id']]['Image'];
        $productData['vid'] = $productSetting[$productRow['id']]['Video'];
        $productData['str'] = $productRow['is_starter_kit'];

        unset($tmp);
        unset($category);

        $tmp = $packageCategory[$productRow['id']]['packageCategory'];
        $productData['cat'] = $packageCategory[$productRow['id']]['packageCategory'];


        unset($productData['Image']);
        unset($productData['Video']);
        unset($productData['validCountry']);
        unset($productData['packageCategory']);
        $productList[] = $productData;
    }

    $data['productList'] = $productList;

    $content = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $path = __DIR__."/../json/productList.json";


    file_put_contents($path, $content);

    $cmd = "scp ".$path." root@".$config['frontendServerIP'].":".$config['frontendPath']."/member/json/";
    $result = exec($cmd);

    if(is_null($result)) {
        Log::write("havent copy.\n");
    }

    unset($data);
    foreach ($productDisplay as $value) {
    	foreach ($value as $key => $value2) {
    		$data[] = $value2;
    	}
    }

    $content = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $path = __DIR__."/../json/autocompleteProductList.json";


    file_put_contents($path, $content);

    $cmd = "scp ".$path." root@".$config['frontendServerIP'].":".$config['frontendPath']."/member/json/";
    $result = exec($cmd);

    if(is_null($result)) {
        Log::write("havent copy.\n");
    }

    $db->where('id', $processProduct['id']);
    $db->update('system_settings', array('value' => 1));

    Log::write("Process completed.\n");

    function getShortenLang($lang){
    	switch ($lang) {
    		case 'english':
    			$lang = 'eng';
    			break;
    		case 'chineseSimplified':
    			$lang = 'cSim';
    			break;
    		case 'chineseTraditional':
    			$lang = 'cTra';
    			break;
    		case 'indonesian':
    			$lang = 'indo';
    			break;
    	}
    	return $lang;
    }

?>
