<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for System Interal Accounts..
     * Date  1/08/2017.
    **/

    class Country {
        
        function __construct() {

        }

        public function getCountriesList($countryParams) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            if ($countryParams['pagination'] == "No") {
                // This is for getting all the countries without pagination
                $limit = null;
            }elseif($countryParams["deliveryCountry"] == "Yes"){
                $db->where("delivery_country","1");
            }
            else {
            
                $pageNumber = $countryParams['pageNumber'] ? $countryParams['pageNumber'] : 1;
                //Get the limit.
                $limit        = General::getLimit($pageNumber);
            }

            $searchData = $countryParams['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'iso_code2':
                            $db->where('iso_code2', $dataValue);
                            break;
                            
                        case 'iso_code3':
                            $db->where('iso_code3', $dataValue);
                            break;
                            
                        case 'country_code':
                            $db->where('country_code', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('status','Active');
            $db->orderBy("priority", "ASC");
            // $db->orderBy("name", "ASC");
            $copyDb = $db->copy();
            $result = $db->get("country", $limit);

            if (!empty($result)) {
                foreach($result as $value) {

                    $countries['id']            = $value['id'];
                    $countries['name']          = $value['name'];
                    $countries['isoCode2']      = $value['iso_code2'];
                    $countries['isoCode3']      = $value['iso_code3'];
                    $countries['countryCode']   = $value['country_code'];
                    $countries['currencyCode']  = $value['currency_code'];
                    $countries['display']       = $translations[$value['translation_code']][$language]?:$value['name'];

                    $countriesList[] = $countries;
                }
                
                $totalRecords = $copyDb->getValue ("country", "count(id)");
                if(!$limit){
                    $limit[1] = $totalRecords;
                }
                $data['countriesList'] = $countriesList;
                $data['totalPage']    = ceil($totalRecords/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord']   = $totalRecords;
                $data['numRecord']   = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00561"][$language], 'data'=>"");
            } 
        }

        /**
         * Function for adding the New Countries.
         * @param $countryParams.
         * @author Rakesh.
        **/
        function newCountry($countryParams) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $name           = trim($countryParams['name']);
            $isoCode2       = trim($countryParams['isoCode2']);
            $isoCode3       = trim($countryParams['isoCode3']);
            $countryCode    = trim($countryParams['countryCode']);
            $currencyCode   = trim($countryParams['currencyCode']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00562"][$language], 'data'=>"");

            if(strlen($isoCode2) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00563"][$language], 'data'=>"");

            if(strlen($isoCode3) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00564"][$language], 'data'=>"");

            if(strlen($countryCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00565"][$language], 'data'=>"");

            if(strlen($currencyCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00566"][$language], 'data'=>"");

            $fields = array("name",
                            "iso_code2",
                            "iso_code3",
                            "country_code",
                            "currency_code",
                            "created_at");
            $values = array($name, 
                            $isoCode2,
                            $isoCode3,
                            $countryCode,
                            $currencyCode,
                            date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $result = $db->insert("country", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00567"][$language]); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00568"][$language], 'data'=>"");
            }
        }

        /**
         * Function for adding the Updating the Country.
         * @param $countryParams.
         * @author Rakesh.
        **/
        public function editCountryData($countryParams) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $id             = trim($countryParams['id']);
            $name           = trim($countryParams['name']);
            $isoCode2       = trim($countryParams['isoCode2']);
            $isoCode3       = trim($countryParams['isoCode3']);
            $countryCode    = trim($countryParams['countryCode']);
            $currencyCode   = trim($countryParams['currencyCode']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00562"][$language], 'data'=>"");

            if(strlen($isoCode2) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00563"][$language], 'data'=>"");

            if(strlen($isoCode3) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00564"][$language], 'data'=>"");

            if(strlen($countryCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00565"][$language], 'data'=>"");

            if(strlen($currencyCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00566"][$language], 'data'=>"");

            $fields     = array("name",
                            "iso_code2",
                            "iso_code3",
                            "country_code",
                            "currency_code",
                            "updated_at");

            $values     = array($name, 
                            $isoCode2,
                            $isoCode3,
                            $countryCode,
                            $currencyCode,
                            date("Y-m-d H:i:s"));
            $arrayData  = array_combine($fields, $values);
            $db->where('id', $id);
            $result = $db->update("country", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=>  $translations["E00569"][$language]); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00568"][$language], 'data'=>"");
            }
        }

        /**
         * Function for deleting the Country.
         * @param $countryParams.
         * @author Rakesh.
        **/
        function deleteCountry($countryParams) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $id = trim($countryParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00303"][$language], 'data'=> '');

            $db->where('id', $id);
            $result = $db->get("country", 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete("country");
                if($result) {
                    return self::getCountriesList();
                } else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00568"][$language], 'data'=>"");
            }
        }

        /**
         * Function for getting the Country data in the Edit.
         * @param $countryParams.
         * @author Rakesh.
        **/
        public function getCountryData($countryParams) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $id = trim($countryParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00303"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("country");

            if (!empty($result)) {
                $countries['id']            = $result['id'];
                $countries['name']          = $result['name'];
                $countries['isoCode2']     = $result['iso_code2'];
                $countries['isoCode3']     = $result['iso_code3'];
                $countries['countryCode']  = $result['country_code'];
                $countries['currencyCode'] = $result['currency_code'];
                
                $data['countryData'] = $countries;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00568"][$language], 'data'=>"");
            }
        }

        public function getCounty(){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $tableName = "county";

            $sq = $db->subQuery();
            $sq->where("status","Active");
            $sq->where("delivery_country",1);
            $sq->get("country",null,"id");

            $db->orderBy("name", "ASC");
            $db->where("country_id",$sq,"IN");
            $result = $db->get($tableName,null,"id,city_id,country_id,name,translation_code");

            if (!empty($result)) {
                foreach($result as &$row){
                    $row["countyDisplay"] = $translations[$row["translation_code"]][$language]?:$row['name'];
                    unset($row["translation_code"]);
                }
                return $result;
            } else {
                return null;
            }
        }

        public function getSubCounty(){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $tableName = "sub_county";

            $sq = $db->subQuery();
            $sq->where("status","Active");
            $sq->where("delivery_country",1);
            $sq->get("country",null,"id");

            $db->orderBy("name", "ASC");
            $db->where("country_id",$sq,"IN");
            $result = $db->get($tableName,null,"id,county_id,country_id,name,translation_code");

            if (!empty($result)) {
                foreach($result as &$row){
                    $row["subCountyDisplay"] = $translations[$row["translation_code"]][$language]?:$row['name'];
                    unset($row["translation_code"]);
                }

                return $result;
            } else {
                return null;
            }
        }

        public function getPostalCode() {
            $db = MysqliDb::getInstance();
            $tableName = "zip_code";

            $sq = $db->subQuery();
            $sq->where("status","Active");
            $sq->where("delivery_country",1);
            $sq->get("country",null,"id");

            $db->orderBy("name", "ASC");
            $db->where("country_id",$sq,"IN");
            $result = $db->get($tableName, NULL, 'id, sub_county_id, country_id, name AS postalCode');

            if (!empty($result)) {
                return $result;
            } else {
                return null;
            }
        }

        public function getCity(){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $tableName = "city";

            $sq = $db->subQuery();
            $sq->where("status","Active");
            $sq->where("delivery_country",1);
            $sq->get("country",null,"id");

            $db->orderBy("name", "ASC");
            $db->where("country_id",$sq,"IN");
            $result = $db->get($tableName,null,"id,state_id,country_id,name,translation_code");

            if (!empty($result)) {
                foreach($result as &$row){
                    $row["cityDisplay"] = $translations[$row["translation_code"]][$language]?:$row['name'];
                    unset($row["translation_code"]);
                }
                return $result;
            } else {
                return null;
            }
        }

        public function getState(){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $tableName = "state";

            $sq = $db->subQuery();
            $sq->where("status","Active");
            $sq->where("delivery_country",1);
            $sq->get("country",null,"id");

            $db->orderBy("name", "ASC");
            $db->where("country_id",$sq,"IN");
            $result = $db->get($tableName,null,"id,country_id,name,translation_code");

            if (!empty($result)) {
                foreach($result as &$row){
                    $row["stateDisplay"] = $translations[$row["translation_code"]][$language]?:$row['name'];
                    unset($row["translation_code"]);
                }
                return $result;
            } else {
                return null;
            }
        }

        public function getBankListByCountryID($params) {
            $db = MysqliDb::getInstance();
            $tableName = "mlm_bank";
            $column    = array(
                "id",
                "name",
                "country_id"
            );
            $countryId = $params['countryId'];

            if (!empty($countryId))
                $db->where("country_id", $countryId);
            $data = $db->get($tableName, NULL, $column);

            if ($data)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getCustomCountryList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $pagination = $params['pagination'];
            $type = $params['type'];

            if ($pagination == 1) $limit = General::getLimit($pageNumber);

            switch ($type) {
                case 'delivery':
                    $countryList[] = array(
                        "id"    => "999",
                        "name"  => "Others",
                        "translation_code"  => "D003520",
                    );
                    $db->where("delivery_country", "1");

                    break;

                default:
                    break;
            }

            $db->where('status','Active');
            $db->orderBy("name", "ASC");
            $copyDb = $db->copy();
            $result = $db->get('country', $limit, 'id, name, country_code, currency_code, translation_code');

            foreach($result as $value) {

                $country['id']            = $value['id'];
                $country['name']          = $value['name'];
                $country['countryCode']   = $value['country_code'];
                $country['currencyCode']  = $value['currency_code'];
                $country['display']       = $translations[$value['translation_code']][$language];

                $countryList[] = $country;
            }

            // Sort Country List Based on Country ID
            $sortColumn = array_column($countryList, 'id');
            array_multisort($sortColumn, SORT_ASC, $countryList);

            $totalRecords = $copyDb->getValue("country", "count(id)");
            if(!$limit){
                $limit[1] = $totalRecords;
            }
            $data['countryList'] = $countryList;
            $data['totalPage']    = ceil($totalRecords/$limit[1]);
            $data['pageNumber']   = $pageNumber;
            $data['totalRecord']   = $totalRecords;
            $data['numRecord']   = $limit[1];
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
    }

?>
