<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Languages code.
     * Date  11/07/2017.
    **/
    class Language {
        
        function __construct($db, $general, $setting, $writer) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
            $this->writer = $writer;
        }
        
        function generateLanguageFile(){

        	global $config;
            $db = $this->db;
            $setting = $this->setting;
            
            $results = $db->get("languages", null, "language");
            
            foreach($results as $row)
            {
                $languageArray[$row["language"]] = $row["language"];
            }
            
            $db->orderBy("site", "ASC");
            $db->orderBy("code", "ASC");
            $db->orderBy("module", "ASC");
            $translationResults = $db->get("language_translation");
            
            // Generate php file
            $content .= '<?php	'."\n";
            
            $languageVer =  $setting->systemSetting['languageVersion'];
			$languageVer += 0.1;

			$db->where('name', 'languageVersion');
			$db->update('system_settings', array('value' => $languageVer));
			$setting->systemSetting['languageVersion'] = $languageVer;

            $content .= "\t".'$langVersion = '.$languageVer.";\n";

            foreach ($translationResults as $row){
                $translationCode[$row["code"]] = $row["code"];
                $translationArray[$row["code"]][$row["language"]] = $row;
            }
            
            foreach ($translationCode as $code) {
                
                if ($tempCode != $code){
                    $tempCode = $code;
                    $content .= "\n";
                }
                
                foreach ($languageArray as $lang) {
                    
                    if ($translationArray[$code][$lang]){
                        
                        if ($comment != $translationArray[$code][$lang]["site"]." ".$translationArray[$code][$lang]["module"]){
                            
                            // Add comments
                            $comment = $translationArray[$code][$lang]["site"]." ".$translationArray[$code][$lang]["module"];
                            $content .= "\t".'// '.$comment.' section'."\n";
                            
                        }
                        
                        // Set the language
                        $content .= "\t".'$translations[\''.$code.'\'][\''.$lang.'\'] = "'.str_replace('"', '\"', $translationArray[$code][$lang]["content"]).'";'."\n";
                        
                    }else{
                        // If translation does not exist, set default to english
                        $content .= "\t".'$translations[\''.$code.'\'][\''.$lang.'\'] = "'.str_replace('"', '\"', $translationArray[$code]["english"]["content"]).'";'."\n";
                    }
                }
            }
            
            $content .= "\n?>";
            
            $languagePath = realpath(dirname(__FILE__))."/../language/";
            
            file_put_contents($languagePath.'lang_all.php', $content);
            $this->generateSeparateLanguagesFile();
            // Check whether frontend Member and Admin path is set
            // If it's set, we try to automate the file copy process based on the settings
            // ***** IMPORTANT TO CHANGE THE PATH FOR EVERY DIFFERENT PROJECT!!!!! *****

            if($config['frontendServerIP'] && $config['frontendServerIP'] != '127.0.0.1'){
            	// test/live
            	$cmd = "scp ".$languagePath."* root@".$config['frontendServerIP'].":".$config['memberLanguagePath'];
            	$cmd2 = "scp ".$languagePath."* root@".$config['frontendServerIP'].":".$config['adminLanguagePath'];

            }else{
            	 // local
            	$cmd = "cp ".$languagePath."* ".$config['memberLanguagePath'];
            	$cmd2 = "cp ".$languagePath."* ".$config['adminLanguagePath'];

            }

            $cmd3 = "cp ".$languagePath."* ".$config['backendLanguagePath'];
            
            exec($cmd, $output, $result);
            exec($cmd2, $output, $result);
            exec($cmd3, $output, $result);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }
        
        function getLanguageList(){ 
            $db = $this->db;
            $setting = $this->setting;
            $language = $this->general->getCurrentLanguage();    
            $translations        = $this->general->getTranslations();

            // $db->where('disabled', '0');
            $result = $db->get('languages', null, 'language, language_code, iso_code, disabled');
            foreach($result AS &$row){
            	if($row['disabled'] == 0) $row['disabled'] = 'Active';
            	if($row['disabled'] == 1) $row['disabled'] = 'Inactive';
            	// $row['display'] = $translations[$row['language_code']][$language];
            	// $row['optionDisplay'] = $translations[$row['language_code']][$row['language']];
            }

            $data['languageList'] = $result;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data); 
            
        } // getLanguageList
        
        public function newLanguageCode($languageCodeParams) {
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            //$test = $languageCodeParams['languageData'];
            $contentCode   = trim($languageCodeParams['contentCode']);
            $site           = trim($languageCodeParams['site']);
            $category       = trim($languageCodeParams['category']);
            $module         = trim($languageCodeParams['module']);
            $languageData   = $languageCodeParams['languageData'];

            $dataArray = array();

            foreach($languageData as $languageName => $content) {
                if($languageName == 'english' && empty($content)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "English content is compulsory", 'data'=>"");
                }

                if(empty($content)) continue;
                
                array_push($dataArray, Array($contentCode,$module,$languageName,$site,$category,$content));
            }

            // $myObj->data->data           = $languageCodeParams;
            // $myJson                     = json_encode($myObj);
            // return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $myJson);

            if(strlen($contentCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00604"][$language], 'data'=>"");

            if(strlen($site) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00605"][$language], 'data'=>"");

            if(strlen($category) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00606"][$language], 'data'=>"");

            $db->where("code",$contentCode);
            $check = $db->getValue("language_translation","code");
            if($check)return array('status' => "error", 'code' => 1, 'statusMsg' => "Code exist please use other code.", 'data'=>"");

            $fields = array("code", "module","language","site","type","content");

            $db->insertMulti('language_translation',$dataArray,$fields);

			$db->rawQuery("UPDATE system_settings SET value = 1 WHERE name = 'autoRunLangCron'");
			
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00599"][$language], 'data'=>($dataArray));
        }

        /**
         * Function for adding the Updating a Language.
         * @param $params
         * @author Aman.
        **/
        public function editLanguageData($params) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id             = trim($params['id']);
            $languageName   = trim($params['languageName']);
            //$languageCode   = trim($params['languageCode']);
            $isoCode        = trim($params['isoCode']);
            $status         = trim($params['status']);
            $updatedAt      = date("Y-m-d H:i:s");

            if(strlen($languageName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00596"][$language], 'data'=>"");
            // if(strlen($languageCode) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Language Code.", 'data'=>"");
            if(strlen($isoCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00597"][$language], 'data'=>"");
            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00598"][$language], 'data'=>"");

            $fields = array("language","iso_code","disabled","updated_at");
            $values = array($languageName, $isoCode,$status,$updatedAt);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("languages", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00600"][$language], 'data'=>''); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00601"][$language], 'data'=>"");
            }
        }

        /**
         * Function for deleting the Persmission.
         * @param $languageParams
         * @author Aman
        **/
        function deleteLanguage($languageParams) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id = trim($languageParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data'=> '');

            // $db->where('id', $id);
            $db->where('language', $id);
            $result = $db->get('languages', 1);

            if (!empty($result)) {
                // $db->where('id', $id);
                $db->where('language', $id);

                $result = $db->update('languages', array('disabled' => 1));
                if($result) {
                    return $this->getLanguageList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00601"][$language], 'data'=>"");
            }
        }

        /**
         * Function for getting the Language data in the Edit.
         * @param $params
         * @author Aman.
        **/
        public function getLanguageData($params) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            $id = trim($params['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data'=> '');
            
            // $db->where('id', $id);
            $db->where('language', $id);

            $result = $db->getOne("languages");

            if (!empty($result)) {
                $languageData['id']             = $result["id"];
                $languageData['languageName']   = $result["language"];
                //$languageData['languageCode']   = $result["language_code"];
                $languageData['isoCode']        = $result['iso_code'];
                $languageData['status']         = $result['disabled'];
                
                $data['languageData'] = $languageData;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00601"][$language], 'data'=>"");
            }
        }

        /** ########### Languagecode Starts (class.language.class) ########### **/

        /**
         * Function for getting the Languge List.
         * @param $languageCodeParams.
         * @author Aman.
        **/
        public function getLanguageCodeList($params,$site) {
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            $seeAll         = $params['seeAll'];

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];

            // $db->where("disabled","0");
            $res = $db->get("languages",NULL,"language,language_code");
            foreach($res AS $row){
                $langArr[] = $row['language'];
                $langDisplay[$row['language']] = $translations[$row['language_code']][$language];
            }

            // $select = ",MAX(CASE WHEN language = 'replace' THEN content ELSE '' END) AS replace";
            // foreach($langArr AS $lang){
            //     $str = str_replace('replace', $lang , $select);
            //     $sql .= " ".$str." ";  
            // }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {

                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        default:
                            $db->where($dataName, $dataValue);
                        break;

                    }

                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('type', 'dynamic');
            $column = "code ,site,module, type, language, content";
            // $copyDb = $db->copy();
            $result = $db->get("language_translation", null, $column);

            if (empty($result)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00593"][$language], 'data'=>"");
            
            foreach($result as $value) {

            	if(!in_array($value['language'],array_keys($langDisplay))) continue;
            	if(!$languageList[$value['code']]){
            		$value[$value['language']] = $value['content'];
            		$languageList[$value['code']] = $value;
            	}else{
            		$languageList[$value['code']][$value['language']] = $value['content'];
            	}
            }

            $headerAry = array('code', 'site', 'module', 'type');
            foreach($langDisplay AS $lang => $disply){
        		$headerAry[] = $lang;
        	}

            foreach($languageList AS $code=>&$value){
            	unset($value['content'], $value['language']);

            	if($params['type'] != 'export'){
	            	foreach($langDisplay AS $lang => $disply){
	            		if(!$value[$lang]) $value[$lang] = $value['english'];
	            	}
	            } // do not append empty languages

            	unset($temp);
            	foreach($headerAry AS $header){
            		$temp[$header] = $value[$header];
            	}
            	$languageList[$code] = $temp;
            }

            $db->where("name","lastLanguageUpdate");
            $lastTimeStamp = $db->getValue("system_settings","value");
            if(!$lastTimeStamp) {
                $lastTimeStamp = time();
            }
            
            $editable = array_keys($langDisplay);
            if($site == "SuperAdmin") {
                $canEdit = array('site','category');
                $editable = array_merge($editable,$canEdit);
            }

            $languageList = array_values($languageList);
            if($seeAll == 1){
            	$data['languageCodeList'] = $languageList;
            }else{
            	$data['languageCodeList'] = array_slice($languageList, $limit[0], $limit[1]);
            }

            $totalRecord = count($languageList);
            $data['langDisplay'] = $langDisplay;
            $data['editable'] = $editable;
            $data['lastTimeStamp'] = $lastTimeStamp;
            $data['lastEdit'] = date("d/m/Y h:i A", $lastTimeStamp);
            $data['headerArr'] = array_keys($languageList[0]);
            
            $data['totalPage']        = ceil($totalRecord/$limit[1]);
            $data['pageNumber']       = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        /**
         * Function for getting the Languge List.
         * @param $languageCodeParams.
         * @author Aman.
        **/
        public function getLanguageRows($languageCodeParams) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $db->where("disabled","0");
            $res = $db->get("languages",NULL,"language,language_code");
            foreach($res AS $row){
                $lang[$row['language']] = $translations[$row['language_code']][$language];
            }
            $db->groupBy("site");
            $res = $db->get("language_translation",NULL,"site");
            foreach($res AS $row){
                $site[] = $row['site'];
            }
            $db->groupBy("type");
            $res = $db->get("language_translation",NULL,"type");
            foreach($res AS $row){
                $type[] = $row['type'];
            }

            $lastCode[] = $this->generateDynamicCode('M');
            $lastCode[] = $this->generateDynamicCode('A');
            $lastCode[] = $this->generateDynamicCode('E');
            $lastCode[] = $this->generateDynamicCode('B');
            $lastCode[] = $this->generateDynamicCode('L');
            $lastCode[] = $this->generateDynamicCode('T');

            $data['lastCode'] = $lastCode;
            $data['language'] = $lang; 
            $data['site'] = $site; 
            $data['type'] = $type; 

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00593"][$language], 'data'=>$data);
            
        }

        /**
         * Function for adding the New Language.
         * @param $languageCodeParams
         * @author Aman
        **/
        public function newLanguage($languageCodeParams) {
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            //$test = $languageCodeParams['languageData'];
            $contentCode   = trim($languageCodeParams['contentCode']);
            $site           = trim($languageCodeParams['site']);
            $category       = trim($languageCodeParams['category']);
            $module         = trim($languageCodeParams['module']);
            $languageData   = $languageCodeParams['languageData'];

            $dataArray = array();

            foreach($languageData as $language => $content) {
                array_push($dataArray, Array($contentCode,$module,$language,$site,$category,$content));
            }

            // $myObj->data->data           = $languageCodeParams;
            // $myJson                     = json_encode($myObj);
            // return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $myJson);

            if(strlen($contentCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00604"][$language], 'data'=>"");

            if(strlen($site) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00605"][$language], 'data'=>"");

            if(strlen($category) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00606"][$language], 'data'=>"");

            $db->where("code",$contentCode);
            $check = $db->getValue("language_translation","code");
            if($check)return array('status' => "error", 'code' => 1, 'statusMsg' => "Code exist please use other code.", 'data'=>"");

            $fields = array("code", "module","language","site","type","content");

            $db->insertMulti('language_translation',$dataArray,$fields);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00599"][$language], 'data'=>($dataArray));
        }

        /**
         * Function for adding the Updating a Language.
         * @param $languageCodeParams
         * @author Aman.
        **/
        public function editLanguageCodeData($languageCodeParams) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $langCode                  = trim($languageCodeParams['code']);
            $language            = trim($languageCodeParams['language']);
            $content             = trim($languageCodeParams['content']);

            if(strlen($content) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00596"][$language], 'data'=>"");

            if(strlen($language) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00607"][$language], 'data'=>"");

            if(strlen($langCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "code cannot empty", 'data'=>"");

            // $db->where("disabled","0");
            $res = $db->get("languages",NULL,"language");
            foreach($res AS $row){
                $langArr[] = $row['language'];
            }

            // $res = $db->rawQuery("SELECT CASE WHEN EXISTS(SELECT 1 FROM language_import) THEN 0 ELSE 1 END AS IsEmpty");
            // $isEmpty = $res[0]['IsEmpty']; // 1  = table empty 

            // $db->orderBy("id","DESC");
            // $processChecking = $db->getValue("language_import","processed");
            // if($processChecking != 1 && $isEmpty != 1)return array('status' => "error", 'code' => 1, 'statusMsg' => "Process generate language file is running. Please try again. After 5 minutes still cannot edit please contact admin", 'data'=>"");

            // $db->where("id",$id);
            // $langCode = $db->getValue("language_translation","code");

            if(in_array($language, $langArr)){
                $db->where("language",$language);
                $db->where("code",$langCode);
                $realID = $db->getValue("language_translation","id");

                if($realID){
	                $fields = array("content");
	                $values = array($content);
	                $arrayData = array_combine($fields, $values);
	                $db->where('id', $realID);
	                $result =$db->update("language_translation", $arrayData);
                }else{
                	$db->where("code",$langCode);
                	$db->where("language","english");
                	$res = $db->getOne("language_translation","module,site,type");
                	$module = $res['module'];
                	$site = $res['site'];
                	$type = $res['type'];

                	$insertData = array(
                						"module" => $module,
                						"site" => $site,
                						"type" => $type,
                						"code" => $langCode,
                						"content" => $content,
                						"language" => $language,
                						"created_at" => date("Y-m-d H:i:s"),
                						"updated_at" => date("Y-m-d H:i:s")
                						);

                	$result = $db->insert("language_translation",$insertData);
                }

            }else{
                $fields = array($language);
                $values = array($content);
                $arrayData = array_combine($fields, $values);
                $db->where('code', $langCode);
                $result =$db->update("language_translation", $arrayData);

            }
            
            $lastEditTs = time();
            $updateData = array("value" => $lastEditTs);
            $db->where("name","lastLanguageUpdate");
            $db->update("system_settings",$updateData);

            $data['lastEdit'] = date("d/m/Y h:i A");
			$updateData = array("value" => "1");
            $db->where("name","autoRunLangCron");
            $db->update("system_settings",$updateData);
            
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Update Successfully", 'data' => $data); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00609"][$language], 'data'=>"");
            }
        }

        /**
         * Function for deleting the Persmission.
         * @param $languageCodeParams
         * @author Aman
        **/
        public function deleteLanguageCode($languageCodeParams) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id = trim($languageCodeParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00610"][$language], 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('language_translation', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('language_translation');
                if($result) {
                    return $this->getLanguageCodeList();
                } else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00611"][$language], 'data'=>"");
            }
        }

        /**
         * Function for getting the Language data in the Edit.
         * @param $languageCodeParams
         * @author Aman.
        **/
        public function getLanguageCodeData($languageCodeParams) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            $id = trim($languageCodeParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00612"][$language], 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("language_translation");

            if (!empty($result)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00613"][$language], 'data'=>"");
            }
        }

        /**
         * Upload the Language Codes Excel file.
         * @param NULL.
         * @author Rakesh.
        **/
        public function uploadFile($languageCodeParams,$userID,$site) {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            if(empty($languageCodeParams['data']) || empty($languageCodeParams['type']) || empty($languageCodeParams['fileName'])){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please upload at least a language file.", 'data'=>"");
            }
   //      	$split = explode('_', $languageCodeParams['fileName']);
			// $split2 = explode('.', $split[1]);
			// $timeStamp = $split2[0];

   //          $db->where("name","lastLanguageUpdate");
   //          $lastTimeStamp = $db->getValue("system_settings","value");
   //          if($timeStamp != $lastTimeStamp)return array('status' => "error", 'code' => 1, 'statusMsg' => "Please get latest language file.", 'data'=>"");

            $res = $db->rawQuery("SELECT CASE WHEN EXISTS(SELECT 1 FROM language_import) THEN 0 ELSE 1 END AS IsEmpty");
            $isEmpty = $res[0]['IsEmpty']; // 1  = table empty 

            $db->orderBy("id","DESC");
            $processChecking = $db->getValue("language_import","processed");
            if(in_array($processChecking, array(0,2)) && $isEmpty != 1)return array('status' => "error", 'code' => 1, 'statusMsg' => "Process genrating language file is running. Please try again. If after 5 minutes still cannot upload please contact admin", 'data'=>"");

            $creatorType = ($site == "Admin" ? "admin" : "users") ;

            $fields = array("data", "type", "created_at");
            $values = array($languageCodeParams['data'], $languageCodeParams['type'], date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $uploadId = $db->insert("uploads", $arrayData);

            $file        = $languageCodeParams['fileName'];
            $fileArray   = explode('.', $file);
            $fileName    = $fileArray[0];
            $fileExt     = $fileArray[1];
            $newFileName = $fileName.".".$fileExt;

            if ($uploadId) {
                $importFields = array("file_name", "processed", "upload_id", "created_by", "created_at", "creator_type");
                $importValues = array($newFileName, "0", $uploadId, $userID, date("Y-m-d H:i:s"), $creatorType);
                $importData = array_combine($importFields, $importValues);

                $languageImportId = $db->insert("language_import", $importData);
            }
            if((!$uploadId) || (!$languageImportId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00614"][$language], 'data'=>"");
            
            $updateData = array("value" => time());
            $db->where("name","lastLanguageUpdate");
            $db->update("system_settings",$updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00615"][$language], 'data' => '');
        }

        /**
         * Export the Language Codes.
         * @param NULL.
         * @author Rakesh.
        **/
        public function exportLanguageCodes() {
            $db = $this->db;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            
            $db->orderBy("code", "ASC");
            $result = $db->get("language_translation");
            $columnHeaders = $db->rawQuery("SELECT language from languages where disabled = 0 ");
             //languages list.
            $languages     =  array_column($columnHeaders, "language");

            if(empty($result)) {
                $langColumns = $db->rawQuery("SHOW COLUMNS FROM language_translation where Field NOT IN ('id', 'language', 'content', 'created_at', 'updated_at')  ");

                foreach ($langColumns as $langColumn) {
                    $headerCols[] = $langColumn["Field"];
                }
                $finalHeader = array_merge($headerCols, $languages);

                return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $finalHeader);
            }

            if (!empty($result)) {
                $prevCode = '';
                $currCode = '';

                $i = -1;
                foreach($result as $key => $value) {
                    $currCode = $value['code'];
                    if($prevCode != $currCode) {
                        $i++;
                        $exportArray[$i]['code'] = $value['code'];
                        $exportArray[$i]['site'] = $value['site'];
                        $exportArray[$i]['module'] = $value['module'];
                        $exportArray[$i]['type'] = $value['type'];
                    }

                    $language = $value['language'];
                    foreach ($languages as $lang) {
                        if($value['language'] == $lang) {
                            $language = $value['language'];
                        }
                    }
                    $exportArray[$i][$language] = $value['content'];

                    $prevCode = $value['code'];
                }
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $exportArray);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00595"][$language], 'data'=>"");
            }
        }

        /**
         * Import the Language Translations.
         * @param NULL.
         * @author Rakesh.
        **/
        public function importLanguageTranslations() {
        	global $config;
            $db = $this->db;

            $db->join("language_import li", "u.id=li.upload_id", "LEFT");
            $db->where("li.processed", 0);
            $importFile = $db->getOne ("uploads u",  "u.data, li.file_name, li.id, li.creator_type");

            $dbLanguages = $db->get("language_translation ", null, "code, site, module, type, language, content, created_at");
            foreach($dbLanguages AS $rows){
            	$dbTranslation[$rows['code']][$rows['language']] = $rows['content'];
            }

            if(empty($importFile)) {
                return "noFile";
            }

            if (!file_exists(realpath(dirname(__DIR__))."/temp/")) {
                mkdir(realpath(dirname(__DIR__))."/temp/", 0700, true);
            } // create directory if not exist

            //Create the Excel files in the temp Floder.
            $decodedData = base64_decode($importFile["data"]);
            file_put_contents(realpath(dirname(__DIR__))."/temp/".$importFile["file_name"], $decodedData);

            // update to processing 1st.
            unset($update);
            $update = array(
            	"processed" => 2,
            	"updated_at" => date("Y-m-d H:i:s")
        	);
            $db->where('id', $importFile["id"]);
            $db->update("language_import", $update);
            unset($update);

            //Get the All Excel file names.
            $files = glob(realpath(dirname(__DIR__))."/temp/*.xlsx");
            // $files = glob(realpath(dirname(__DIR__))."/temp/".$importFile["file_name"]);

            if (!file_exists(realpath(dirname(__DIR__))."/backup/")) {
                mkdir(realpath(dirname(__DIR__))."/backup/", 0700, true);
            } // create directory if not exist

            exec('mysqldump -u'.$config['dBUser'].' -p'.$config['dBPassword'].' '.$config['dB'].' language_translation > '.realpath(dirname(__DIR__)).'/backup/language_'.date("Ymd_Hi").'.sql');

            //Get all the Importing files Content.
            foreach ($files as $inputFileName) {
                //  Read your Excel workbook
                try {
                    $inputFileType  = PHPExcel_IOFactory::identify($inputFileName);
                    $objReader      = PHPExcel_IOFactory::createReader($inputFileType);
                    $objPHPExcel    = $objReader->load($inputFileName);
                } catch(Exception $e) {
                    die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
                }

                //  Get worksheet dimensions
                $sheet          = $objPHPExcel->getSheet(0); 
                $highestRow     = $sheet->getHighestRow(); 
                $highestColumn  = $sheet->getHighestColumn();

                $rowData = $sheet->rangeToArray('A1:'.$highestColumn.'1', NULL, FALSE, FALSE);
                $headerData = array_filter($rowData[0]);
                //  Loop through each row of the Excel file.
                for ($row = 2; $row <= $highestRow; $row++){ 
                    //  Read a row of data into an array
                    $rowData = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, NULL, FALSE, FALSE);
                    //Removes all empty values.

                    foreach ($rowData as $index => $datas) {
                        $excelAry[] =  $datas;
                    }
                }
            }

            echo date("Y-m-d H:i:s")." Start Looping Excel Record\n";
            $languagesType = $db->map('language')->get('languages', null, 'language, iso_code');

            foreach($headerData AS $header){
            	if(!in_array($header, array('code', 'contentCode', 'site', 'module', 'category', 'type'))){
            		if(in_array($header, array_keys($languagesType))) continue; //is possible language

            		unset($update);
            		$update = array(
		            	"processed" => 3,
		            	"updated_at" => date("Y-m-d H:i:s")
		        	);

		            $db->where('id', $importFile["id"]);
		            $db->update("language_import", $update);
		            // print_r($headerData);
		            unlink(realpath(dirname(__DIR__))."/temp/".$importFile["file_name"]);
            		die(date("Y-m-d H:i:s")." First rows is not header!\n");
            	}
            }

            foreach($excelAry AS $row){
            	unset($temp);
            	foreach($headerData AS $key=>$header){
            		if(in_array($header, array('code', 'contentCode', 'site', 'module', 'category', 'type'))){
            			if($header == 'contentCode') $header = 'code';
            			if($header == 'category') $header = 'module';
            			$temp[$header] = trim($row[$key]);
            		}
            	} // split foreach($headerData is to prevent inproper excel format

            	foreach($headerData AS $key=>$header){ 
            		if(in_array($header, array('id', 'code', 'contentCode', 'site', 'module', 'category', 'type'))) continue;
            		
        			unset($temp2);
            		$temp2 = $temp;
            		$temp2['language'] = trim($header);
            		$temp2['content'] = trim($row[$key]);
            		$finalImportArray[] = $temp2;
            	}
            } // convert format [0] => static to [type] => static & split multiple language


            // print_r($finalImportArray); exit();
            unset($newData);
            foreach($finalImportArray AS $row){
            	unset($oldContent);
            	if(in_array($row['type'], array('Dynamic', 'dynamic'))) continue; // will not update dynamic code
            	if(!Language::verifyCodeFormat($row['code'])) continue;

            	$oldContent = $dbTranslation[$row['code']][$row['language']];

            	if($oldContent == $row['content']) continue;  // code unchanged
            	if(!$row['content'] && $row['language'] == 'english') continue;// english cannot be empty

            	if($oldContent && !$row['content']){

            		$db->where('code', $row['code']);
            		$db->where('language', $row['language']);
            		$db->delete('language_translation');
            		print_r(" OldCode/OldLang | delete | ".$row['code']." | ".$languagesType[$row['language']]." | ".$oldContent." \n");
            		continue;
            	} // remove others language except english

            	if($row['content'][0] == "=") {
            		echo " Formula Error | ".$row['code']." | ".$languagesType[$row['language']]." |".$row['content']."\n";
            		continue;
				} // checking to escape formula content

				if($checkDuplicate[$row['code']][$row['language']]){
            		echo " dupliateCode/NewLang | ignore | ".$row['code']." | ".$languagesType[$row['language']]." | ".$row['content']." \n";
					continue;
				}

				if(!$dbTranslation[$row['code']]){
            		if($importFile['creator_type'] == 'users'){
            			echo " NewCode/NewLang | insert | ".$row['code']." | ".$languagesType[$row['language']]." | ".$row['content']." \n";
						$row['created_at'] = date("Y-m-d H:i:s");
	            		$newData[] = $row;
	            		$checkDuplicate[$row['code']][$row['language']] = $row['content'];
	            	}
            		continue;
				} // if code not exist insert new code

            	if(!$oldContent && $row['content']){
            		echo " OldCode/NewLang | insert | ".$row['code']." | ".$languagesType[$row['language']]." | ".$row['content']." \n";
            		$row['created_at'] = date("Y-m-d H:i:s");
            		$newData[] = $row;
            		$checkDuplicate[$row['code']][$row['language']] = $row['content'];
            		continue;
            	} // code exist but content empty will insert new record

            	unset($langUpdate);
            	$langUpdate = array(
            		'updated_at' => date("Y-m-d H:i:s"),
            		'content' => $row['content']
            	);

            	echo " OldCode/OldLang | update | ".$row['code']." | ".$languagesType[$row['language']]." | ".$oldContent." > ".$row['content']." \n";
            	$db->where('code', $row['code']);
            	$db->where('language', $row['language']);
            	$db->update('language_translation', $row);
            } // update existing language

            if(!empty($newData) && isset($newData)){
            	$ids = $db->insertMulti('language_translation', $newData);

            	if(!$ids){
            		return "insert failed ".  $db->getLastError();
            	}
            } // only superadmin allowed to insert NEW language

            Language::processFile($files);
            Language::generateLanguageFile();
            $message =  "Language Translations Successfully Imported, ".count($files)." Files Deleted Successfully.";
            return $message;

        }

        /**
         * Enable the Proceesed to 1 and delete the file.
         * @param NULL.
         * @author Rakesh.
        **/
        public function processFile($files) {
            $db = $this->db;
            foreach ($files as $iFile) {
                $fileName = basename($iFile);         // $file is set to "index.php"
                $fileName = basename($iFile, ".xlsx"); // $file is set to "index"

                $updateFileProcess['processed']     = '1';
                $updateFileProcess['updated_at']    = date("Y-m-d H:i:s");

                $db->where('file_name', $fileName.".xlsx");
                $result =$db->update("language_import", $updateFileProcess);

                if($result){
                    unlink(realpath(dirname(__DIR__))."/temp/".$fileName.".xlsx");
                }
            }
            return;
        }

        public function setLanguage($params) {

            $db = $this->db;

            $clientID = $params['clientID'];
            $language = $params['language'];

            $fields    = array("language");
            $values    = array($language);
            $arrayData = array_combine($fields, $values);

            $db->where('id', $clientID);
            $db->update("client", $arrayData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => '');

        }

        public function generateSeparateLanguagesFile(){
        	$db = $this->db;

			$db->where('disabled', 0);
			$return = $db->get('languages', null, 'language');
			foreach($return AS $data){
        		$languages[] = $data['language'];
        	}

        	$return = $db->get('language_translation', null, 'code, language, content');

			// foreach ($return as $row) {
			// 	if(!in_array($row['language'], $languages)) continue;

			// 	$array[$row['language']][$row['code']][$row['language']] = $row['content'];
			// }

			foreach ($return as $row){
				if(!in_array($row['language'], $languages)) continue;
					
	                $translationCodeAry[$row["code"]] = $row["code"];
	                $translationArray[$row["code"]][$row["language"]] = $row['content'];
            }
            
			foreach($translationCodeAry AS $code){
				foreach($languages AS $lang){
					if($translationArray[$code][$lang]){
						$array[$lang][$code][$lang] = $translationArray[$code][$lang];
					}else{
						$array[$lang][$code][$lang] = $translationArray[$code]['english'];
					}
				}
			}
			
			$languagePath = realpath(dirname(__FILE__))."/../language/";
			foreach ($array as $lang => $row) {
				file_put_contents($languagePath."lang_".$lang.".js", "var translations = ".json_encode($row));
			}
        }

        function getAllLanguage() {
            $db=$this->db;
            $db->where('type','Language Setting');
            $languageRes = $db->get("system_settings",null,'value');
            foreach ($languageRes as $key => $value) {
                $languages[] = $value["value"];
            }

            // while($db->dbFetchRow($languageRes)) {
            //     $languageRow = $db->dbRow["mlmSetting"];
            //     $languages[] = $languageRow["value"];
            // }
            
            $db->where('site','Member');
            $db->orWhere('site','Error');
            // $db->orWhere('site','Dynamic');
            $translationRes=$db->get('language_translation',null,'code, language, content');
            foreach ($translationRes as $key => $translationRow) {
                $languageCodes[$translationRow["code"]] = $translationRow["code"];
                $languageArray[$translationRow["code"]][$translationRow["language"]] = $translationRow["content"]; 
            }

            // $translationRes = $db->dbSql("SELECT code, type, msg FROM mlmLanguages WHERE site = 'Member' OR site = 'Error' OR site = 'Dynamic'");
            // while($translationRow = mysql_fetch_assoc($translationRes)) {
            //     $languageCodes[$translationRow["code"]] = $translationRow["code"];
            //     $languageArray[$translationRow["code"]][$translationRow["type"]] = $translationRow["msg"]; 
            // }

            foreach($languageCodes as $code) {
                if($languageCode != $code) {
                    $languageCode = $code;
                }
                
                foreach($languages as $lang) {
                    if($languageArray[$code][$lang]) {
                        $newLanguageArray[$code][$lang] = $languageArray[$code][$lang];
                    }
                    else {
                        $newLanguageArray[$code][$lang] = $languageArray[$code]["english"];
                    }
                }
                
            }
            
            // return $newLanguageArray;
            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $newLanguageArray);
        }

        function getLanguageVersion() {
            $setting = $this->setting;
            
            $data['languageVersion'] =  $setting->systemSetting['languageVersion']? $setting->systemSetting['languageVersion'] : 0;
            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $data);

        }

        public function verifyCodeFormat($code){

        	if($code == "") return false;

        	if(strlen($code) != 6){
        		return false;
        	} // code min 6 length, future 7.

        	if(!ctype_alpha($code[0])){
        		return false;
			} // if first is not character

			for($x=1; $x< strlen($code); $x++){
				if(!is_numeric($code[$x])){
					return false;
				}
			} // check others if is numeric

			return true;
        }

        public function getLanguageUploadFileList(){
            $db = $this->db;
            $general = $this->general;
            $language = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $copyDb = $db->copy();
            $db->orderBy('id', "DESC");//language_import
            $res = $db->get("language_import",$limit,"file_name AS fileName ,processed ,created_at ,updated_at");
            if(empty($res)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00595"][$language], 'data'=>"");
            $totalRecord = $copyDb->getValue ("language_import", "count(id)");
            foreach($res AS $key => $val){
                if($res[$key]['processed'] == 0) $res[$key]['status'] = 'Pending';
                if($res[$key]['processed'] == 2) $res[$key]['status'] = 'Processing';
                if($res[$key]['processed'] == 3) $res[$key]['status'] = 'Failed';
                if($res[$key]['processed'] == 1) $res[$key]['status'] = 'Success';
                $res[$key]['updated_at'] = ($res[$key]['updated_at'] == null ? '-' : $res[$key]['updated_at']);
                unset($res[$key]['processed']);
            }

            $data['importListing'] = $res;
            $data['totalPage']    = ceil($totalRecord/$limit[1]);
            $data['pageNumber']   = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

	    function generateDynamicCode($code){
	    	$db = $this->db;
	    	
	    	$db->where("code", $code."%", "LIKE");
	    	$db->orderBy('code', 'DESC');
	    	$codeData = $db->getOne("language_translation", "code");

	        if (empty($codeData)) return $code."00001";

	        $existCode = $codeData["code"];
	        $existCode = str_replace($code, "", $existCode);
	        $newCode = $code.str_pad($existCode+1, 5, "0", STR_PAD_LEFT);
			return $newCode;
	    }
        /** ########### Languagecode End. (class.language.class) ########### **/
    }

?>
