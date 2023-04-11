<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 27/03/2018.
    **/

    class Bulletin {
        
        function __construct() {
            
        }
        
        public function addAnnouncement($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $startDate = $params['startDate'];
            $endDate = $params['endDate'];
            $dateTime = date('Y-m-d H:i:s');
            $db->where("type", "Upload Setting");
            $validMediaRes  = $db->map('name')->get("system_settings",null,"name, value ,reference");

            $validImageType = explode("#", $validMediaRes['validImageType']['value']);
            $maxImageSize   = $validMediaRes['validImageType']['reference'];

            $validDocumentType = explode("#", $validMediaRes['validDocumentType']['value']);
            $maxDocumentSize   = $validMediaRes['validDocumentType']['reference'];

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => $translations["E00218"][$language]
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => $translations["E00218"][$language]
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'statusError',
                                            'msg' => $translations["E00552"][$language]
                                        );
            }

            if(empty($params['uploadData']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);

            foreach ($params['uploadData'] as $lang => $imageData) {

                if(!$imageData['imgFlag'] && !$imageData['attachmentFlag'])
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00993"][$language], 'data' => $data);

                if($imageData['imgFlag']==1){
                    if(!$imageData['languageType']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
                    }

                    if(!$imageData['imgName']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00556"][$language], 'data' => $data);
                    }

                    if(!in_array($imageData['imgType'], $validImageType)){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language], 'data' => $data);
                    }

                    if(!$imageData['imgSize']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Image)", 'data' => $data);

                    }
                    
                    if($imageData['imgSize']>$maxImageSize){
                        $sizeMB         = $maxImageSize / 1024 / 1024;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Image)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                    }
                }

                if($imageData['attachmentFlag']==1){
                    if(!$imageData['attachmentName']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00557"][$language], 'data' => $data);
                    }

                    if(!in_array($imageData['attachmentType'], $validDocumentType)){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00921"][$language], 'data' => $data);
                    }

                    if(!$imageData['attachmentSize']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Attachment)", 'data' => $data);

                    }
                    if($imageData['attachmentSize']>$maxDocumentSize){
                        $sizeMB         = $maxDocumentSize / 1024 / 1024;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Attachment)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                    }
                }

                // if(!$imageData['attachmentName']){
                //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00557"][$language], 'data' => $data);
                // }

                // if(!in_array($imageData['attachmentType'], $attachmentTypeAry)) {
                //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00921"][$language], 'data' => $data);
                // }

            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => $data);

            if($params['leaderUsernameAry']) {
                foreach($params['leaderUsernameAry'] AS $leaderUsername){
                    unset($leaderID);
                    $db->where('username', $leaderUsername);
                    $leaderID = $db->getValue('client', 'id');

                    if(empty($leaderID)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'leaderUsernameError',
                                                    'msg' => 'Username does not exist.'
                                                );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $leaderIDAry[] = $leaderID;
                }
            }

            if($params['excludeLeaderUsernameAry']) {
                foreach($params['excludeLeaderUsernameAry'] AS $excludeLeaderUsername){
                    unset($excludeLeaderID);
                    $db->where('username', $excludeLeaderUsername);
                    $excludeLeaderID = $db->getValue('client', 'id');

                    if(empty($excludeLeaderID)) {
                        $errorFieldArr[] = array(
                            'id'  => 'excludeLeaderUsernameError',
                            'msg' => 'Username does not exist.'
                        );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $excludeLeaderIDAry[] = $excludeLeaderID;
                }
            }

            $countryIDList = $db->get('country', null, 'id');
            foreach($countryIDList AS $countryData){
                $temp[] = $countryData['id'];
            }
            $countryIDList = $temp;

            foreach($params["countryIDAry"] AS $country_id){
                if(!in_array($country_id, $countryIDList)){

                    $errorFieldArr[] = array(
                        'id'  => 'countryIDError',
                        'msg' => 'country does not exist.'
                    );

                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                }
            }

            $insertData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'reference_id' => '',
                                    'created_at' => $dateTime,
                                    'updated_at' => $dateTime
                                );
            $announcementID = $db->insert('mlm_announcement', $insertData);

            if(empty($announcementID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            $updateData = array(
                                    "updateData" => array("value" => $dateTime),
                                    "name" => "announcementRead",
                                );
            Setting::updateClientSetting($updateData);

            $groupCode = General::generateUniqueChar("mlm_announcement_image_setting","upload_name");

            foreach ($params['uploadData'] as $uploadData) {
                unset($uploadID);
                unset($type);
                unset($uploadDataAry);

                if ($uploadData['imgFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['imgName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_announcement_image_setting","upload_name")."_".$groupCode."_".$uploadData["languageType"].".".$fileType;
                    $type = "image";
                    $defaultImageValue = $uploadData['defaultImage'];

                    $imageData['upload_name'] = $upload_name;
                    $imageData['type'] = $type;
                    $imageData['defaultImage'] = $defaultImageValue;

                    $uploadDataAry[] = $imageData;
                }

                if ($uploadData['attachmentFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['attachmentName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_announcement_image_setting","upload_name")."_".$groupCode."_".$uploadData["languageType"].".".$fileType;
                    $type = "attachement";
                    $defaultAttachmentValue = $uploadData['defaultAttachment'];

                    $attachmentData['uploadID'] = $uploadID;
                    $attachmentData['upload_name'] = $upload_name;
                    $attachmentData['type'] = $type;
                    $attachmentData['defaultAttachment'] = $defaultAttachmentValue;

                    $uploadDataAry[] = $attachmentData;
                }

                $language_type = $uploadData["languageType"];
                $defaultAttachementName = "defaultAttachementLanguage";
                $defaultImageName = "defaultImageLanguage";

                foreach ($uploadDataAry as $key => $value) {
                    if($value['defaultAttachment'] == 1){
                        $insertDefaultAttachment = array(   
                                                        'name' => $defaultAttachementName,
                                                        'value' => $language_type,
                                                        'announcement_id' => $announcementID,
                                                        );

                        $db->insert("mlm_announcement_setting", $insertDefaultAttachment);
                    }

                    if($value['defaultImage'] == 1){
                        $insertDefaultImage = array(   
                                                    'name' => $defaultImageName,
                                                    'value' => $language_type,
                                                    'announcement_id' => $announcementID,
                                                   );

                        $db->insert("mlm_announcement_setting", $insertDefaultImage);
                    }
                    $settingImageParams = array(
                                            'type' => $value['type'],
                                            'upload_id' => $value['uploadID'],
                                            'upload_name' => $value['upload_name'],
                                            'language_type' => $language_type,
                                            'announcement_id' => $announcementID,
                                            );     

                    $db->insert("mlm_announcement_image_setting", $settingImageParams);

                    if($value['type'] == 'image') {
                        $uploadDataNameAry[$language_type]['imgName'] = $value['upload_name'];
                    } else if($value['type'] == 'attachement') {
                        $uploadDataNameAry[$language_type]['attachmentName'] = $value['upload_name'];
                    }
                }

                $announcementSetting[$language_type."Description"] = $uploadData['description'];
                $announcementSetting[$language_type."Subject"] = $uploadData['subject'];
            }

            if($leaderIDAry){
                $announcementSetting['leaderUsernameAry'] = implode(", ", $leaderIDAry);
            }

            if($params['countryIDAry']){
                $announcementSetting['countryIDAry'] = implode(", ", $params['countryIDAry']);
            }else{
                $announcementSetting['countryIDAry'] = 0;
            }

            if($excludeLeaderIDAry){
                $announcementSetting['excludeLeaderUsernameAry'] = implode(", ", $excludeLeaderIDAry);
            }else{
                $announcementSetting['excludeLeaderUsernameAry'] = '';
            }

            if($params['excludeCountryIDAry']){
                $announcementSetting['excludeCountryIDAry'] = implode(", ", $params['excludeCountryIDAry']);
            }else{
                $announcementSetting['excludeCountryIDAry'] = '';
            }

            if($params['treeType']){
                $announcementSetting['treeType'] = $params['treeType'];
            }

            if($params['startDate']){
                $announcementSetting['startDate'] = date("d/m/Y", $params['startDate']);
            }

            if($params['endDate']){
                $announcementSetting['endDate'] = date("d/m/Y", $params['endDate']);
            }
       
            foreach ($announcementSetting as $key => $value) {
                $settingParams = array('name' => $key,
                                       'value' => $value,
                                       'announcement_id' => $announcementID,
                                      );     

                $db->insert("mlm_announcement_setting", $settingParams);

                unset($settingParams);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $uploadDataNameAry);
        }

        public function getAnnouncement($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $id = $params["id"];

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $db->where('disabled', 0);
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $db->where('id', $id);
            $result = $db->get('mlm_announcement', 1, 'id ,subject, description, status');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => "");

            $countryIDList = $db->get('country', null, 'id, name');
            foreach($countryIDList AS $countryData){
                $temp[$countryData['id']] = $countryData['name'];
            }
            $countryIDList = $temp;

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    if($k == 'id'){
                        
                        $db->where("announcement_id", $v);
                        $db->where("value", "0",">");

                        $result = $db->get("mlm_announcement_setting", NULL, "name, value");
                        if($result){
                            foreach ($result as $key => $value) {
                                $announcementSetting[$value['name']] = explode(", ", $value['value']);
                            }
                        }
                        if($announcementSetting['countryIDAry']){
                            foreach ($announcementSetting['countryIDAry'] as $name => $countryID) {
                                $permissions['country'][$countryID]['id'] = $countryID;
                                $permissions['country'][$countryID]['name'] = $countryIDList[$countryID];
                            }
                                
                        }
                        if($announcementSetting['leaderUsernameAry']){
                            foreach ($announcementSetting['leaderUsernameAry'] as $name => $leader_id) {
                                $db->where('id', $leader_id);
                                $clientUsername = $db->getValue('client', "username");
                                $permissions['leader'][$leader_id] = $clientUsername;//$data['leader_id'];
                            }
                        }
                        if($announcementSetting['excludeCountryIDAry']){
                            foreach ($announcementSetting['excludeCountryIDAry'] as $name => $excludeCountryID) {
                                $permissions['excludeCountry'][$excludeCountryID]['id'] = $excludeCountryID;
                                $permissions['excludeCountry'][$excludeCountryID]['name'] = $countryIDList[$excludeCountryID];
                            }   
                        }
                        if($announcementSetting['excludeLeaderUsernameAry']){
                            foreach ($announcementSetting['excludeLeaderUsernameAry'] as $name => $excludeLeader_id) {
                                $db->where('id', $excludeLeader_id);
                                $clientUsername = $db->getValue('client', "username");
                                $permissions['excludeLeader'][$excludeLeader_id] = $clientUsername;//$data['leader_id'];
                            }
                        }
                        if($announcementSetting['treeType']){
                            foreach ($announcementSetting['treeType'] as $name => $treeType) {
                                $permissions['treeType'] = $treeType;
                            }
                        }
                        if($announcementSetting['startDate']){
                            foreach ($announcementSetting['startDate'] as $name => $startDate) {
                                $permissions['startDate'] = $startDate;
                            }
                        }
                        if($announcementSetting['endDate']){
                            foreach ($announcementSetting['endDate'] as $name => $endDate) {
                                $permissions['endDate'] = $endDate;
                            }
                        }
                    }
                            $announcement[$k] = $v;
                }

            $db->where('announcement_id', $id);
            $systemAnnouncementImageData = $db->get('mlm_announcement_image_setting', NULL, 'language_type, upload_name, type as upload_type');
                foreach ($systemAnnouncementImageData as $key => $value) {
                    if($value['upload_type'] == "image"){
                        $announcementDetail[$value['language_type']]['image_name'] = $value['upload_name'];
                        $announcementDetail[$value['language_type']]['image_type'] = $value['upload_type'];
                    }
                    if($value['upload_type'] == "attachement"){
                        $announcementDetail[$value['language_type']]['attachement_name'] = $value['upload_name'];
                        $announcementDetail[$value['language_type']]['attachement_type'] = $value['upload_type'];

                    }
                    if($announcementSetting[$value['language_type']."Description"])$announcementDetail[$value['language_type']]['description'] = $announcementSetting[$value['language_type']."Description"][0];
                    if($announcementSetting[$value['language_type']."Subject"])$announcementDetail[$value['language_type']]['subject'] = $announcementSetting[$value['language_type']."Subject"][0];
                }

            $db->where('announcement_id', $id);
            $db->where('name',array('defaultImageLanguage','defaultAttachementLanguage'),'IN');
            $getAnnouncementDefault = $db->get('mlm_announcement_setting', NULL, 'name, value, announcement_id');
            $announcementDefault = $getAnnouncementDefault[0]['value'];

            }

            $announcementSortedDetail[$announcementDefault] = $announcementDetail[$announcementDefault];
            unset($announcementDetail[$announcementDefault]);

            foreach ($announcementDetail as $key => $value) {
                $announcementSortedDetail[$key] = $value;
            }

            // $countryListRes = Country::getCountriesList();

            $countryListRes = $db->get('country', null, 'id, name, iso_code2, iso_code3, country_code, currency_code, translation_code');

            foreach ($countryListRes as $value) {
                $country['id'] = $value['id'];
                $country['name'] = $value['name'];
                $country['isoCode2'] = $value['iso_code2'];
                $country['isoCode3'] = $value['iso_code3'];
                $country['countryCode'] = $value['country_code'];
                $country['currencyCode'] = $value['currency_code'];
                $country['display'] = $translations[$value['translation_code']][$language]?:$value['name'];

                $countryList[] = $country;
            } 

            $data['permissions'] = $permissions;
            $data['announcement'] = $announcement;
            $data['announcementDetail'] = $announcementSortedDetail;
            $data['announcementDefault'] = $announcementDefault;
            $data['countryList'] = $countryList;
            $data['systemLanguages'] = $systemLanguages;


            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editAnnouncement($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date('Y-m-d H:i:s');
            $db->where("type", "Upload Setting");
            $validMediaRes  = $db->map('name')->get("system_settings",null,"name, value ,reference");

            $validImageType = explode("#", $validMediaRes['validImageType']['value']);
            $maxImageSize   = $validMediaRes['validImageType']['reference'];

            $validDocumentType = explode("#", $validMediaRes['validDocumentType']['value']);
            $maxDocumentSize   = $validMediaRes['validDocumentType']['reference'];

            if(empty($params['id']) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => $translations["E00218"][$language]
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => $translations["E00218"][$language]
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'statusError',
                                            'msg' => $translations["E00552"][$language]
                                        );
            }

            $checkingValue = 0;
            foreach ($params['uploadData'] as $key => $uploadData) {

                if($uploadData["defaultImage"] == 1)
                    $checkingValue = 1;

                if(!$uploadData['imgName']){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00556"][$language], 'data' => "");
                }

                if(!$uploadData['languageType']){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => "");
                }


                if($uploadData['imgFlag'] == 1) {
                    if(!in_array($uploadData['imgType'], $validImageType)){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language], 'data' => $data);
                    }

                    if(!$uploadData['imgSize']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Image)", 'data' => $data);
                    }
                    
                    if($uploadData['imgSize']>$maxImageSize){
                        $sizeMB         = $maxImageSize / 1024 / 1024;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Image)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                    }    
                }

                // if(!$uploadData['attachmentName']){
                //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00557"][$language], 'data' => $data);
                // }

                if($uploadData['attachmentFlag'] == 1) {
                    if(!$uploadData['attachmentName']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00557"][$language], 'data' => $data);
                    }

                    if(!in_array($uploadData['attachmentType'], $validDocumentType)){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00921"][$language], 'data' => $data);
                    }

                    if(!$uploadData['attachmentSize']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Attachment)", 'data' => $data);

                    }
                    if($uploadData['attachmentSize']>$maxDocumentSize){
                        $sizeMB         = $maxDocumentSize / 1024 / 1024;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Attachment)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                    }
                }
            }            

            if($checkingValue == 0){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Default Image fields cannot be empty.", 'data' =>'');
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => $data);

            $groupCode = General::generateUniqueChar('mlm_announcement_image_setting','upload_name');

            foreach ($params['uploadData'] as $key => $uploadData) {

                if($uploadData['imgFlag'] == 1) {
                    $db->where('type', 'image');
                    $db->where('announcement_id',$params['id']);
                    $db->where('language_type',$uploadData['languageType']);
                    $preImageName = $db->getValue('mlm_announcement_image_setting','upload_name');
                    $availableLangAry[] = $key;

                    if($preImageName){
                        if($preImageName == $uploadData['imgName']){
                            continue;
                        }else{
                            $preImageNameAry[] = $preImageName;
                        }
                    }

                    $fileType = end(explode(".", $uploadData['imgName']));
                    $upload_name = time()."_".General::generateUniqueChar('mlm_memo_image_setting','upload_name')."_".$groupCode."_".$uploadData["languageType"].".".$fileType;

                    $attachment['image_name'] = $upload_name;
                    $attachment['type'] = 'image';

                    $db->where('type', $attachment['type']);
                    $db->where('language_type', $key);
                    $db->where('announcement_id', $params['id']);
                    $attachementSettingID = $db->getValue('mlm_announcement_image_setting','id');
                    if($attachementSettingID){
                        unset($updateSettingData);
                        $updateSettingData = array(
                                                   'upload_id' => $attachment['image_id'],
                                                   'upload_name' => $attachment['image_name'],
                                                  );

                        $db->where('id', $attachementSettingID);
                        $db->update('mlm_announcement_image_setting', $updateSettingData);

                    }else{
                        unset($insertData);
                        $insertData = array(
                                            'upload_id' => $attachment['image_id'],
                                            'upload_name' => $attachment['image_name'],
                                            'language_type' => $key,
                                            'announcement_id' => $params['id'],
                                            'type' => $attachment["type"],
                                           );

                        $db->insert('mlm_announcement_image_setting', $insertData);
                    } 
                    unset($attachementSettingID);

                    if($uploadData["defaultImage"] == 1){
                        $db->where('announcement_id', $params['id']);
                        $db->where('name','defaultImageLanguage');
                        $getDefaultLang = $db->get('mlm_announcement_setting', NULL, 'name, value, announcement_id');

                        if($getDefaultLang){
                            unset($updateDefaultLang);
                            $updateDefaultLang = array(
                                                        'value' => $key,
                                                      );
                            $db->where('announcement_id', $params['id']);
                            $db->where('name','defaultImageLanguage');
                            $db->update('mlm_announcement_setting', $updateDefaultLang);
                        }
                        else{
                            unset($insertDefaultLang);
                            $defaultImageName = "defaultImageLanguage";
                            $insertDefaultLang = array(
                                                        'name' => $defaultImageName,
                                                        'value' => $key,
                                                        'announcement_id' => $params['id'],
                                                      );

                            $db->insert('mlm_announcement_setting', $insertDefaultLang);

                        }
                    }

                    $uploadDataNameAry[$key]['imgName'] = $upload_name;

                } else {
                    $availableLangAry[] = $key;
                }

                if ($uploadData['attachmentFlag'] == 1) {
                    $db->where('type', 'attachement');
                    $db->where('announcement_id',$params['id']);
                    $db->where('language_type',$uploadData['languageType']);
                    $preAttachmentName = $db->getValue('mlm_announcement_image_setting','upload_name');

                    if($preAttachmentName){
                        if($preAttachmentName == $uploadData['attachmentName']){
                            continue;
                        }else{
                            $preAttachmentNameAry[] = $preAttachmentName;
                        }
                    }

                    $fileType = end(explode(".", $uploadData['attachmentName']));
                    $upload_name = time()."_".General::generateUniqueChar('mlm_announcement_image_setting','upload_name')."_".$groupCode."_".$uploadData["languageType"].".".$fileType;

                    $attachment['document_name'] = $upload_name;
                    $attachment['type'] = 'attachement';

                    $db->where('type', $attachment['type']);
                    $db->where('language_type', $key);
                    $db->where('announcement_id', $params['id']);
                    $attachementSettingID = $db->getValue('mlm_announcement_image_setting','id');

                    if($attachementSettingID){
                        unset($updateSettingData);
                        $updateSettingData = array(
                                                   'upload_id' => $attachment['document_id'],
                                                   'upload_name' => $attachment['document_name'],
                                                  );

                        $db->where('id', $attachementSettingID);
                        $db->update('mlm_announcement_image_setting', $updateSettingData);

                    }else{
                        unset($insertData);
                        $insertData = array(
                                            'upload_id' => $attachment['document_id'],
                                            'upload_name' => $attachment['document_name'],
                                            'language_type' => $key,
                                            'announcement_id' => $params['id'],
                                            'type' => $attachment["type"],
                                           );

                        $db->insert('mlm_announcement_image_setting', $insertData);
                    } 
                    unset($attachementSettingID);

                    if($uploadData["defaultAttachment"] == 1){
                        $db->where('announcement_id', $params['id']);
                        $db->where('name','defaultAttachementLanguage');
                        $getDefaultLang = $db->get('mlm_announcement_setting', NULL, 'name, value, announcement_id');

                        if($getDefaultLang){
                            $updateDefaultLang = array(
                                                        'value' => $key,
                                                      );
                            $db->where('announcement_id', $params['id']);
                            $db->where('name','defaultAttachementLanguage');
                                               
                            $db->update('mlm_announcement_setting', $updateDefaultLang);

                        }
                        else{
                            unset($insertDefaultLang);
                            $defaultAttachementName = "defaultAttachementLanguage";
                            $insertDefaultLang = array(
                                                        'name' => $defaultAttachementName,
                                                        'value' => $key,
                                                        'announcement_id' => $params['id'],
                                                      );

                            $db->insert('mlm_announcement_setting', $insertDefaultLang);
                        }
                    } 
                    $uploadDataNameAry[$key]['attachmentName'] = $upload_name;
                }
                $language_type = $uploadData["languageType"];
                $announcementSetting[$language_type."Description"] = $uploadData['description'];
                $announcementSetting[$language_type."Subject"] = $uploadData['subject'];
            }

            $uploadReturnData['uploadData'] = $uploadDataNameAry;

            $settingTypeAry = array('image', 'attachement'); 
            $db->where("announcement_id",$params['id']);
            $db->where('type', $settingTypeAry, 'IN');
            $db->where('language_type', $availableLangAry,"NOT IN");
            $copyDb = $db->copy();
            $preNameRes = $db->get('mlm_announcement_image_setting',null,'upload_name, type');            

            foreach ($preNameRes as $preNameRow) {
                if($preNameRow['type'] == 'attachement') {
                    $preAttachmentNameAry[] = $preNameRow['upload_name'];
                } else if($preNameRow['type'] == 'image') {
                    $preImageNameAry[] = $preNameRow['upload_name'];
                }
            }

            $uploadReturnData['preAttachmentName'] = $preAttachmentNameAry;
            $uploadReturnData['preImgName'] = $preImageNameAry;    

            $copyDb->delete("mlm_announcement_image_setting");

            if($params['leaderUsernameAry']) {
                foreach($params['leaderUsernameAry'] AS $leaderUsername){
                    unset($leaderID);
                    $db->where('username', $leaderUsername);
                    $leaderID = $db->getValue('client', 'id');

                    if(empty($leaderID)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'leaderUsernameError',
                                                    'msg' => 'Username does not exist.'
                                                );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $leaderIDAry[] = $leaderID;
                }
            }
            if($params['excludeLeaderUsernameAry']) {
                foreach($params['excludeLeaderUsernameAry'] AS $excludeLeaderUsername){
                    unset($excludeLeaderID);
                    $db->where('username', $excludeLeaderUsername);
                    $excludeLeaderID = $db->getValue('client', 'id');

                    if(empty($excludeLeaderID)) {
                        $errorFieldArr[] = array(
                            'id'  => 'excludeLeaderUsernameError',
                            'msg' => 'Username does not exist.'
                        );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $excludeLeaderIDAry[] = $excludeLeaderID;
                }
            }

            $countryIDList = $db->get('country', null, 'id');
            foreach($countryIDList AS $countryData){
                $temp[] = $countryData['id'];
            }
            $countryIDList = $temp;
    
            foreach($params["countryIDAry"] AS $country_id){
                if(!in_array($country_id, $countryIDList)){

                    $errorFieldArr[] = array(
                        'id'  => 'countryIDError',
                        'msg' => 'country does not exist.'
                    );

                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                }
            }

            $updateData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'creator_type' => $site,
                                    'updated_at' => $db->now()
                                );
            $db->where('id', $params['id']);
            $db->update('mlm_announcement', $updateData);

            if($leaderIDAry){
                $announcementSetting['leaderUsernameAry'] = implode(", ", $leaderIDAry);
            }else{
                $announcementSetting['leaderUsernameAry'] = '';
            }

            if($params['countryIDAry']){
                $announcementSetting['countryIDAry'] = implode(", ", $params['countryIDAry']);
            }else{
                $announcementSetting['countryIDAry'] = 0;
            }

            if($excludeLeaderIDAry){
                $announcementSetting['excludeLeaderUsernameAry'] = implode(", ", $excludeLeaderIDAry);
            }else{
                $announcementSetting['excludeLeaderUsernameAry'] = '';
            }

            if($params['excludeCountryIDAry']){
                $announcementSetting['excludeCountryIDAry'] = implode(", ", $params['excludeCountryIDAry']);
            }else{
                $announcementSetting['excludeCountryIDAry'] = '';
            }
            if($params['treeType']){
                $announcementSetting['treeType'] = $params['treeType'];
            }
            if($params['startDate']){
                $announcementSetting['startDate'] = date("d/m/Y", $params['startDate']);
            }
            if($params['endDate']){
                $announcementSetting['endDate'] = date("d/m/Y", $params['endDate']);
            }

            foreach ($announcementSetting as $key => $value) {
                $db->where('name', $key);
                $db->where('announcement_id', $params['id']);
                unset($announcementAry);
                $announcementAry = $db->get('mlm_announcement_setting', null, 'id, name, value, announcement_id');

                if(empty($announcementAry)){
                    unset($insertSettingParams);
                    $insertSettingParams = array (
                                                    'name' => $key,
                                                    'value' => $value,
                                                    'announcement_id' => $params['id'],
                                                 );

                    $db->insert('mlm_announcement_setting', $insertSettingParams);
                }

                if($announcementAry){
                    unset($updateSettingParams);
                    $updateSettingParams = array(
                                                 'value' => $value,
                                                );
                    $db->where('name', $key);
                    $db->where('announcement_id', $params['id']);

                    $db->update("mlm_announcement_setting", $updateSettingParams);
                }
            }

            if($uploadReturnData) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $uploadReturnData);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => '');
            }
        }

        public function removeAnnouncement($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $updateData = array('status' => "Deleted", 'updated_at' => $db->now());
            $db->where('id', $params['id']);
            $db->update('mlm_announcement', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getAnnouncementList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

            $searchData = $params['searchData'];

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'subject':
                            $db->where('subject', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'createdAt':
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
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] , 'data'=>$data);

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                    
                                $db->where('updated_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language], 'data'=>$data);

                                $db->where('updated_at', date('Y-m-d 23:59:59', $dateTo), '<=');
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
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            $result = $db->get('mlm_announcement', $limit, 'id, subject, description, status, creator_id, creator_type, created_at, updated_at');

            if(empty($result)){
                $db->where('disabled', 0);
                $systemLanguages = $db->get('languages', NULL, 'language, language_code');
                $data['systemLanguages'] = $systemLanguages;
                $data['countryList'] = $db->get('country', null, 'id, name');
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => $data);
            }

            foreach($result as $value) {
                if($value['creator_type'] == 'SuperAdmin')
                    $superAdminID[] = $value['creator_id'];
                else if($value['creator_type'] == 'Admin')
                    $adminID[] = $value['creator_id'];
                else if ($value['creator_type'] == 'Member')
                    $clientID[] = $value['creator_id'];
            }
            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    if($k == "creator_id") {

                    }
                    else if($k == "creator_type")
                        $announcement['creator_username'] = $usernameList[$v][$array['creator_id']];
                    else
                        $announcement[$k] = $v;
                }
                $announcementList[] = $announcement;
            }

            // Get system languages
            $db->where('disabled', 0);
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $totalRecords = $copyDb->getValue('mlm_announcement', 'count(id)');
            $data['announcementList'] = $announcementList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
            $data['countryList'] = $db->get('country', null, 'id, name');
            $data['systemLanguages'] = $systemLanguages;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function newsDisplay($params, $userID) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $db->orderBy('created_at','DESC');
            $db->where('status', "Active");
            $announcementData = $db->get('mlm_announcement', null, 'id, subject, description, created_at');

            if(empty($announcementData))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => "");

            $db->where('id', $userID);
            $country_id = $db->getValue('client', 'country_id');

            $db->where('client_id', $userID);
            $leaderSponsorRow = $db->getValue("tree_sponsor", "trace_key");
            $leader_id_sponsor_ary = explode('/', $leaderSponsorRow);
            $sponserLeaderAry = array_reverse($leader_id_sponsor_ary);

            $db->where('client_id', $userID);
            $leaderPlacementRow = $db->getValue("tree_placement", "trace_key");
            $leaderPlacementRow = str_replace(array('-1<', '-1>', '-1'), '/', $leaderPlacementRow);
            $leader_id_placement_ary = explode('/', $leaderPlacementRow);
            $placementLeaderAry = array_reverse($leader_id_placement_ary);

            $db->where("name","announcementRead");
            $db->where("client_id",$userID);
            $lastSeen = $db->getValue("client_setting","value");
            $strTime  = strtotime($lastSeen);

            $db->where('client_id', $userID);
            $db->where("name","announcement");
            $isReadArr = $db->map("value")->get("client_setting",NULL,"value");

            foreach($announcementData as $key => $value) {
                $news['subject'] = $value['subject'];
                $news['description'] = $value['description'];
                $news['created_at'] = $value['created_at'];
                $news['id'] = $value['id'];
                unset($isNew);
                if($strTime < strtotime($value['created_at'])){
                    $isNew = 1;
                    $insertData = array(
                                        "name"=>"announcement",
                                        "value"=>$value['id'],
                                        "client_id"=>$userID,
                                    );
                    $db->insert("client_setting",$insertData);
                }
                if($isReadArr[$value['id']]) $isNew = 1;
                $news['isNew'] = ($isNew ? 1 : 0);

                $includeLeaderAry = "";
                $db->where('announcement_id', $news['id']);
                $db->where('name', "leaderUsernameAry");
                $includeResult = $db->get("mlm_announcement_setting", NULL ,"value");
                foreach ($includeResult as $key => $includeValue) {
                    if($includeValue["value"] != "" && $includeValue["value"] !=0 )
                        $includeLeaderAry = explode(",", $includeValue["value"]);
                }

                $excludeLeaderAry = "";
                $db->where('announcement_id', $news['id']);
                $db->where('name', "excludeLeaderUsernameAry");
                $excludeResult = $db->get("mlm_announcement_setting", NULL ,"value");
                foreach ($excludeResult as $key => $excludeValue) {
                    if($excludeValue["value"] != "" && $excludeValue["value"] !=0 )
                        $excludeLeaderAry = explode(",", $excludeValue["value"]);
                }

                $announcementID = $news['id'];
                $db->where('announcement_id', $announcementID);
                $db->where('language_type', $language);
                $db->where('type', "image");
                // $db->where('upload_id', 0, ">");
                $announcementImageData = $db->get('mlm_announcement_image_setting', null, 'id, type as upload_type, upload_name');

                if($announcementImageData[0]){
                    $defaultImageData['upload_type'] = $announcementImageData[0]["upload_type"];
                    $defaultImageData['language_type'] = $announcementImageData[0]["language_type"];
                    $defaultImageData['upload_name'] = $announcementImageData[0]["upload_name"];
                }
                else if(!$announcementImageData[0]){
                    $db->where('announcement_id', $announcementID);
                    $db->where('name',"defaultImageLanguage");
                    $announcementImageData = $db->get('mlm_announcement_setting', null, 'id, name, value');
                    $defaultLanguage = $announcementImageData[0]["value"];

                    $db->where('announcement_id', $announcementID);
                    $db->where('language_type', $defaultLanguage);
                    $db->where('type', "image");
                    $announcementImageData = $db->get('mlm_announcement_image_setting', null, 'id, type as upload_type, upload_name');

                    $defaultImageData['upload_type'] = $announcementImageData[0]["upload_type"];
                    $defaultImageData['defaultLanguage'] = $announcementID;
                    $defaultImageData['upload_name'] = $announcementImageData[0]["upload_name"];
                }

                $isDownload = 0;

                $db->where('announcement_id', $announcementID);
                $db->where('language_type', $language);
                $db->where('type', "attachement");
                $announcementAttachementData = $db->get('mlm_announcement_image_setting', null, 'id, type as upload_type, upload_name');

                if($announcementAttachementData[0]){
                    $defaultAttachmentData['upload_type'] = $announcementAttachementData[0]["upload_type"];
                    $defaultAttachmentData['language_type'] = $announcementAttachementData[0]["language_type"];
                    $defaultAttachmentData['upload_name'] = $announcementAttachementData[0]["upload_name"];
                    $isDownload = 1;
                    
                }
                else if(!$announcementAttachementData[0]){
                    $db->where('announcement_id', $announcementID);
                    $db->where('name',"defaultAttachementLanguage");
                    $announcementAttachementData = $db->get('mlm_announcement_setting', null, 'id, name, value');
                    $defaultLanguage = $announcementAttachementData[0]["value"];

                    $db->where('announcement_id', $announcementID);
                    $db->where('language_type', $defaultLanguage);
                    $db->where('type', "attachement");
                    $announcementAttachementData = $db->get('mlm_announcement_image_setting', null, 'id, type as upload_type, upload_name');

                    $defaultAttachmentData['upload_type'] = $announcementAttachementData[0]["upload_type"];
                    $defaultAttachmentData['defaultLanguage'] = $announcementID;
                    $defaultAttachmentData['upload_name'] = $announcementAttachementData[0]["upload_name"];

                    if($announcementAttachementData[0]){
                        $isDownload = 1;
                    }
                }

                    $db->where('announcement_id', $value['id']);
                    $result = $db->get("mlm_announcement_setting", NULL, "value, name");

                    if(!$result) continue;
                    foreach ($result as $key1 => $announcementValue) {
                        $announcementSetting[$announcementValue['name']] = explode(', ', $announcementValue['value']);
                    }
                
                    if($announcementSetting[$language.'Description']) $news['description'] = $announcementSetting[$language.'Description'][0];
                    if($announcementSetting[$language.'Subject']) $news['subject'] = $announcementSetting[$language.'Subject'][0];

                    if($announcementSetting['treeType'][0] == 'sponsor'){
                        $leader_id_ary = $sponserLeaderAry;
                    }else if($announcementSetting['treeType'][0] == 'placement'){
                        $leader_id_ary = $placementLeaderAry;
                    }

                    $granted = 0;
                    $leaderSetting = 0;
                    $excludeLeaderSetting = 0;
                    $countrySetting = 0;
                    $excludeCountrySetting = 0;
                    $startDateSetting = 0;
                    $endDateSetting = 0;

                    $currentDate = date("Ymd");
                    // unset($leaderID);
                    // foreach ($announcementSetting['leaderUsernameAry'] as $key2 => $leaderID) {
                    //     if($leaderID > 0 && in_array($leaderID, $leader_id_ary)){
                    //         $leaderSetting = 1;
                    //         break;
                    //     }
                    // }
                    //     if(!$announcementSetting['leaderUsernameAry'] || $announcementSetting['leaderUsernameAry'][0] == ""){
                    //         $leaderSetting = 1;
                    //     }
                    // unset($excludeLeaderID);
                    // foreach ($announcementSetting['excludeLeaderUsernameAry'] as $key3 => $excludeLeaderID) {
                    //     if($excludeLeaderID > 0 && in_array($excludeLeaderID, $leader_id_ary)){
                    //         $excludeLeaderSetting = 1;
                    //         break;
                    //     }
                    // }

                    if($includeLeaderAry ){
                        $leaderSetting = 0;
                        $excludeLeaderSetting = 1;
                    }else{
                        $leaderSetting = 1;
                        $excludeLeaderSetting = 0;
                    }                    

                    if($includeLeaderAry || $excludeLeaderAry){

                        foreach($leader_id_ary as $key => $uplineID){

                            if (in_array($uplineID, $includeLeaderAry)){
                                $leaderSetting = 1;
                                $excludeLeaderSetting = 0;

                                break;
                            }else if (in_array($uplineID, $excludeLeaderAry)){
                                $leaderSetting = 0;
                                $excludeLeaderSetting = 1;

                                break; 
                            }
                        }
                    }

                    unset($countryID);
                    foreach ($announcementSetting['countryIDAry'] as $key4 => $countryID) {
                        if(($countryID > 0 && $countryID == $country_id) || $countryID == 0){
                            $countrySetting = 1;
                            break;
                        }
                    }
                    unset($excludeCountryID);
                    foreach ($announcementSetting['excludeCountryIDAry'] as $key5 => $excludeCountryID) {
                        if($excludeCountryID > 0 && $excludeCountryID == $country_id){
                            $excludeCountrySetting = 1;
                            break;
                        }
                    }
                    foreach ($announcementSetting['startDate'] as $key6 => $startDate) {
                        $startDateFormat = explode("/", $startDate);
                        $startDateFormation = $startDateFormat[2]."".$startDateFormat[1]."".$startDateFormat[0];
                        $finalStartDate = date($startDateFormation);

                        if($finalStartDate <= $currentDate){
                            $startDateSetting = 1;
                            break;
                        }
                    }
                        if(!$announcementSetting['startDate']){
                            $startDateSetting = 1;
                            break;
                        }
                    foreach ($announcementSetting['endDate'] as $key7 => $endDate) {
                        $endDateFormat = explode("/", $endDate);
                        $endDateFormation = $endDateFormat[2]."".$endDateFormat[1]."".$endDateFormat[0];
                        $finalEndDate = date($endDateFormation);

                        if($finalEndDate == 0){
                            $endDateSetting = 1;
                            break; 
                        }
                        if($finalEndDate >= $currentDate){
                            $endDateSetting = 1;
                            break;
                        }
                        if(!$finalEndDate){
                            $endDateSetting = 1;
                            break;
                        }
                    }

                    if($countrySetting == 1 && $leaderSetting == 1 && $excludeLeaderSetting != 1 && $excludeCountrySetting != 1 && $startDateSetting == 1 && $endDateSetting == 1){
                        $granted = 1;
                    }


                    if($granted == 0){
                        continue;
                    }

                    $newsList[] = $news;
                    $details['id'] = $value['id'];
                    $details['file_type'] =  $defaultImageData['upload_type'];
                    $details['base_64'] = $defaultImageData['upload_name'];
                    $details['subject'] = $announcementSetting[$language.'Subject'] ? $announcementSetting[$language.'Subject'][0] : $value['subject'];
                    $details['description'] = $announcementSetting[$language.'Description'] ? $announcementSetting[$language.'Description'][0] : $value['description'];
                    $details['attachment_file_type'] = $defaultAttachmentData['upload_type'];
                    $details['attachment_name'] = $defaultAttachmentData['upload_name'];
                    $details['created_at'] = $value['created_at'];
                    $details['id'] = $value['id'];
                    $details['isDownload'] = $isDownload;
                    $details['isNew'] = $news['isNew'];
                    $detailsList[] = $details;
            }
                    $updateData = array(
                                            "updateData" => array("value" => date("Y-m-d H:i:s")),
                                            "name" => "announcementRead",
                                            "clientID" => $userID
                                        );
                    Setting::updateClientSetting($updateData);
                    
                    if($newsList) $data['news'] = $newsList;
                    if($detailsList) $data['details'] = $detailsList;
                    if ($data){
                        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
                    }else{
                        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00279"][$language] /* No result found */, 'data' => "");
                    
                    }
                    // return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function newsDownload($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $announcementID = $params['announcementID'];

            if(empty($announcementID))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00560"][$language], 'data' => "");

            $db->where('status', "Active");
            $announcementData = $db->get('mlm_announcement', null, 'id, subject, description, created_at');

            if(empty($announcementData))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00555"][$language], 'data' => "");

            foreach($announcementData as $key => $value) {
                $db->where('announcement_id', $announcementID);
                $db->where('language_type', $language);
                $db->where('type', "attachement");
                $newsImageData = $db->get('mlm_announcement_image_setting', null, 'id, type as upload_type, upload_name');

                if($newsImageData[0]){
                    $defaultImageData['upload_type'] = $newsImageData[0]["upload_type"];
                    $defaultImageData['language_type'] = $newsImageData[0]["language_type"];
                    $defaultImageData['upload_name'] = $newsImageData[0]["upload_name"];
                }

                else if(!$newsImageData[0]){
                    $db->where('announcement_id', $announcementID);
                    $db->where('name',"defaultImageLanguage");
                    $newsImageData = $db->get('mlm_announcement_setting', null, 'id, name, value');
                    $defaultLanguage = $newsImageData[0]["value"];

                    $db->where('announcement_id', $announcementID);
                    $db->where('language_type', $defaultLanguage);
                    $db->where('type', "attachement");
                    $newsImageData = $db->get('mlm_announcement_image_setting', null, 'id, type as upload_type, upload_name');

                    $defaultImageData['upload_type'] = $newsImageData[1]["upload_type"];
                    $defaultImageData['defaultLanguage'] = $announcementID;
                    $defaultImageData['upload_name'] = $newsImageData[1]["upload_name"];

                }

                    $download['file_type'] =  $defaultImageData['upload_type'];
                    $download['attachment_name'] = $defaultImageData['upload_name'];
            }
            if($newsImageData)
                    $data['download'] = $download;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function dashboardNews($userID) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $db->where('status', "Active");
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=image_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=image_id) AS file_type";
            $announcement = $db->get('mlm_announcement', null, 'id, subject, description, created_at,'.$getBase64.','.$getFileType);

            if(empty($announcement))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00555"][$language], 'data' => "");

            foreach($announcement as $key => $value) {
                $news['file_type'] = $value['file_type'];
                $news['id'] = $value['id'];
                $news['base_64'] = $value['base_64'];
                $news['created_at'] = $value['created_at'];
                $news['subject'] = $value['subject'];
                $news['description'] = $value['description'];
                $newsList[] = $news;
            }

            if($newsList){
                $db->where('id', $userID);
                $country_id = $db->getValue('client', 'country_id');

                $db->where('client_id', $userID);
                $leaderRow = $db->getValue("tree_sponsor", "trace_key");
                $leader_id_ary = explode('/', $leaderRow);

                foreach($newsList AS $key=>$newData){
                    $db->where('announcement_id', $newData['id']);
                    $db->where('status', "Active");
                    $result = $db->get("mlm_announcement_permissions", NULL, "country_id, leader_id");

                    if(!$result) continue;
                    $granted = 0;
                    foreach($result AS $eachPerm){
                        if($eachPerm['leader_id'] > 0 && $eachPerm['country_id'] > 0 && $eachPerm['country_id'] == $country_id && in_array($eachPerm['leader_id'], $leader_id_ary)){
                                $granted = 1;
                                break;
                            
                        }else if(!$eachPerm['country_id'] && in_array($eachPerm['leader_id'], $leader_id_ary)){
                                $granted = 1;
                                break;

                        }else if(!$eachPerm['leader_id'] && $eachPerm['country_id'] == $country_id){
                                $granted = 1;
                                break;

                        }
                    } // foreach($result)

                    if($granted == 0){
                        unset($newsList[$key]);
                    }
                } // foreach($memo)
            } // if($memo)

            if($newsList) $data['news'] = $newsList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function addMemo($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
            }else{
                $db->where('id', $clientID);
                $getUsername = $db->getValue('admin', 'username');
                if(!$getUsername){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);  
                }
            }

            $startDate = $params['startDate'];
            $endDate = $params['endDate'];

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'statusError',
                                            'msg' => 'This field value is invalid.'
                                        );
            }
            if(empty($params['uploadData']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);

            foreach ($params['uploadData'] as $lang => $imageData) {

                if(!$imageData['languageType']){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
                }

                if(!$imageData['imgData']){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00556"][$language], 'data' => $data);
                }
            }

            $params['type'] = strtolower($params['type']);
            $acceptedType = array('member', 'public');

            if(!in_array($params['type'], $acceptedType)){
                $errorFieldArr[] = array(
                                            'id'  => 'typeError',
                                            'msg' => 'This field value is invalid.'
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be left blank.", 'data' => $data);

            // $leaderID = '';
            if($params['leaderUsernameAry']) {
                foreach($params['leaderUsernameAry'] AS $leaderUsername){
                    unset($leaderID);
                    unset($data);
                    $db->where('username', $leaderUsername);
                    $leaderID = $db->getValue('client', 'id');

                    if(empty($leaderID)) {
                        $errorFieldArr[] = array(
                                                    'id'  => 'leaderUsernameError',
                                                    'msg' => 'Username does not exist.'
                                                );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "Leader username does not exist.", 'data' => '');
                    }
                    $leaderIDAry[] = $leaderID;
                }
            }
            if($params['excludeLeaderUsernameAry']) {
                foreach($params['excludeLeaderUsernameAry'] AS $excludeLeaderUsername){
                    unset($excludeLeaderID);
                    $db->where('username', $excludeLeaderUsername);
                    $excludeLeaderID = $db->getValue('client', 'id');

                    if(empty($excludeLeaderID)) {
                        $errorFieldArr[] = array(
                            'id'  => 'leaderUsernameError',
                            'msg' => 'Username does not exist.'
                        );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $excludeLeaderIDAry[] = $excludeLeaderID;
                }
            }

            $countryIDList = $db->get('country', null, 'id');
                foreach($countryIDList AS $countryData){
                    $temp[] = $countryData['id'];
                }
                $countryIDList = $temp;

                foreach($params["countryIDAry"] AS $country_id){
                    if(!in_array($country_id, $countryIDList)){

                        $errorFieldArr[] = array(
                            'id'  => 'countryIDError',
                            'msg' => 'country does not exist.'
                        );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                }
            
            $insertData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'type' => $params['type'],
                                    'status' => $params['status'],
                                    'creator_id' => $clientID,
                                    'creator_type' => $site,
                                    'reference_id' => '',
                                    'created_at' => $db->now(),
                                    'updated_at' => $db->now()
                                );

            $memoID = $db->insert('mlm_memo', $insertData);

            if(empty($memoID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            $groupCode = General::generateUniqueChar("mlm_memo_image_setting","upload_name");

            foreach ($params['uploadData'] as $uploadData) {
                unset($uploadID);
                unset($type);
                unset($uploadDataAry);

                if ($uploadData['imgFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['imgName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_memo_image_setting","upload_name")."_".$groupCode."_".$uploadData["languageType"].".".$fileType;
                    $type = "image";
                    $defaultImageValue = $uploadData['defaultImage'];

                    $imageData['upload_name'] = $upload_name;
                    $imageData['type'] = $type;
                    $imageData['defaultImage'] = $defaultImageValue;
                    $imageData['imgFileID'] = $uploadData['imgFileID'];
                    $imageData['imgFileName'] = $uploadData['imgFileName'];
                    $imageData['upload_type'] = $uploadData['imgType'];

                    $uploadDataAry[] = $imageData;
                }

                $language_type = $uploadData["languageType"];
                $defaultImageName = "defaultImageLanguage";

                foreach ($uploadDataAry as $key => $value) {
                    if($value['defaultImage'] == 1){
                        $insertDefaultImage = array(   
                                                    'name' => $defaultImageName,
                                                    'value' => $language_type,
                                                    'memo_id' => $memoID,
                                                   );

                        $db->insert("mlm_memo_setting", $insertDefaultImage);
                    }

                    $settingImageParams = array(
                                            'type' => $value['type'],
                                            'upload_id' => $value['uploadID'],
                                            'upload_name' => $value['upload_name'],
                                            'language_type' => $language_type,
                                            'upload_type' => $value['upload_type'],
                                            'memo_id' => $memoID,
                                            );     

                     $db->insert("mlm_memo_image_setting", $settingImageParams);

                     $uploadDataNameAry[$language_type]['imgName'] = $value['upload_name'];
                     $uploadDataNameAry[$language_type]['imgFileID'] = $value['imgFileID'];
                     $uploadDataNameAry[$language_type]['imgFileName'] = $value['imgFileName'];
                }

            }

            if($leaderIDAry){
                $memoSetting['leaderUsernameAry'] = implode(", ", $leaderIDAry);
            }
            // else{
            //     $memoSetting['leaderUsernameAry'] = '';
            // }

            if($params['countryIDAry']){
                $memoSetting['countryIDAry'] = implode(", ", $params['countryIDAry']);
            }else{
                $memoSetting['countryIDAry'] = 0;
            }

            if($excludeLeaderIDAry){
                $memoSetting['excludeLeaderUsernameAry'] = implode(", ", $excludeLeaderIDAry);
            }else{
                $memoSetting['excludeLeaderUsernameAry'] = '';
            }

            if($params['excludeCountryIDAry']){
                $memoSetting['excludeCountryIDAry'] = implode(", ", $params['excludeCountryIDAry']);
            }else{
                $memoSetting['excludeCountryIDAry'] = '';
            }

            if($params['treeType']){
                $memoSetting['treeType'] = $params['treeType'];
            }

            if($params['startDate']){
                $memoSetting['startDate'] = date("d/m/Y", $params['startDate']);
            }

            if($params['endDate']){
                $memoSetting['endDate'] = date("d/m/Y", $params['endDate']);
            }

            foreach ($memoSetting as $key => $value) {
                $settingMemoParams = array('name' => $key,
                                           'value' => $value,
                                           'memo_id' => $memoID);   
                                            
                $db->insert("mlm_memo_setting", $settingMemoParams);
                unset($settingMemoParams);

            }

            // insert activity log 
            $titleCode    = 'T00066';
            $activityCode = 'L00090';
            $title = 'add Memo';
            $activityData = array('user' => $getUsername);
            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $uploadDataNameAry);
        }

        public function getMemo($params) {
            $db = MysqliDb::getInstance();

            $id = $params["id"];

            if(empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            // $db->where('disabled', 0);
            $newSearchArray = array('english', 'chineseSimplified', 'japanese', 'korean', 'vietnam', 'thailand');
            $db->where('language', $newSearchArray, "IN");
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $db->where('id', $id);
            $result = $db->get('mlm_memo', 1, 'id, subject, type, description, priority, status');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data' => "");

            $countryIDList = $db->get('country', null, 'id, name');
            foreach($countryIDList AS $countryData){
                $temp[$countryData['id']] = $countryData['name'];
            }
            $countryIDList = $temp;

            foreach($result as $array) {

                foreach ($array as $k => $v) {
                    if($k == 'id'){
                        $db->where("memo_id", $v);
                        $db->where("value", "0",">");
                        // $db->where("status", "Active");
                        $result = $db->get("mlm_memo_setting", NULL, "name, value");
                        if($result){
                            foreach ($result as $key => $value) {
                                $memoSetting[$value['name']] = explode(", ", $value['value']);
                            }
                        }

                        if($memoSetting['countryIDAry']){
                            foreach ($memoSetting['countryIDAry'] as $name => $countryID) {
                                $permissions['country'][$countryID]['id'] = $countryID;
                                $permissions['country'][$countryID]['name'] = $countryIDList[$countryID];
                            }
                                
                        }
                        if($memoSetting['leaderUsernameAry']){
                            foreach ($memoSetting['leaderUsernameAry'] as $name => $leader_id) {
                                $db->where('id', $leader_id);
                                $clientUsername = $db->getValue('client', "username");
                                $permissions['leader'][$leader_id] = $clientUsername;//$data['leader_id'];
                            }
                        }
                        if($memoSetting['excludeCountryIDAry']){
                            foreach ($memoSetting['excludeCountryIDAry'] as $name => $excludeCountryID) {
                                $permissions['excludeCountry'][$excludeCountryID]['id'] = $excludeCountryID;
                                $permissions['excludeCountry'][$excludeCountryID]['name'] = $countryIDList[$excludeCountryID];
                            }
                                
                        }
                        if($memoSetting['excludeLeaderUsernameAry']){
                            foreach ($memoSetting['excludeLeaderUsernameAry'] as $name => $excludeLeader_id) {
                                $db->where('id', $excludeLeader_id);
                                $clientUsername = $db->getValue('client', "username");
                                $permissions['excludeLeader'][$excludeLeader_id] = $clientUsername;//$data['leader_id'];
                            }
                        }
                        if($memoSetting['treeType']){
                            foreach ($memoSetting['treeType'] as $name => $treeType) {
                                $permissions['treeType'] = $treeType;
                            }
                        }
                        if($memoSetting['startDate']){
                            foreach ($memoSetting['startDate'] as $name => $startDate) {
                                $permissions['startDate'] = $startDate;
                            }
                        }
                        if($memoSetting['endDate']){
                            foreach ($memoSetting['endDate'] as $name => $endDate) {
                                $permissions['endDate'] = $endDate;
                            }
                        }
                    }

                    $memo[$k] = $v;
                }

            $db->where('memo_id', $id);
            $db->orderBy('upload_id', 'ASC');
            $systemMemoImageData = $db->get('mlm_memo_image_setting', NULL, 'upload_id, language_type, upload_name , type,(SELECT type FROM uploads WHERE id = upload_id) AS upload_type');
                foreach ($systemMemoImageData as $key => $value) {
                    if($value['type'] == "image"){
                        $memoDetail[$value['language_type']]['image_name'] = $value['upload_name'];
                        $memoDetail[$value['language_type']]['image_type'] = $value['type'];
                    }
                }

            $db->where('memo_id', $id);
            $db->where('name','defaultImageLanguage');
            $getMemoDefault = $db->get('mlm_memo_setting', NULL, 'name, value, memo_id');
            $memoDefault = $getMemoDefault[0]['value'];    

            }

            $memoSortedDetail[$memoDefault] = $memoDetail[$memoDefault];
            unset($memoDetail[$memoDefault]);

            foreach ($memoDetail as $key => $value) {
                $memoSortedDetail[$key] = $value;
            }

            $data['permissions'] = $permissions;
            $data['memo'] = $memo;
            $data['memoDetail'] = $memoSortedDetail;
            $data['memoDefault'] = $memoDefault;
            $countryListRes = Country::getCountriesList();
            $data['countryList'] = $countryListRes['data']['countriesList'];
            $data['systemLanguages'] = $systemLanguages;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editMemo($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
            }else{
                $db->where('id', $clientID);
                $getUsername = $db->getValue('admin', 'username');
                if(!$getUsername){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);  
                }
            }

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'statusError',
                                            'msg' => 'This field value is invalid.'
                                        );
            }

            $params['type'] = strtolower($params['type']);
            $acceptedType = array('member', 'public');

            if(!in_array($params['type'], $acceptedType)){
                $errorFieldArr[] = array(
                                            'id'  => 'typeError',
                                            'msg' => 'This field value is invalid.'
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => $data);
            
            $checkingValue = 0;
            foreach ($params['uploadData'] as $key => $uploadData) {

                if($uploadData["defaultImage"] == 1)
                    $checkingValue = 1;
            }

            if($checkingValue == 0){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Default Image fields cannot be empty.", 'data' =>'');
            }

            // $db->where('memo_id', $params['id']);
            // $clearMemoData = array(
            //                         'upload_id' => '',
            //                         'upload_name' => '',
            //                         'language_type' => '',
            //                         'memo_id' => $params['id'],
            //                         'type' => '',
            //                       );

            // $db->update('mlm_memo_image_setting', $clearMemoData);
            $groupCode = General::generateUniqueChar('mlm_memo_image_setting','upload_name');

            foreach ($params['uploadData'] as $key => $uploadData) {
                $availableLangAry[] = $key;
                if($uploadData['imgFlag'] == 1) {

                    $db->where('memo_id',$params['id']);
                    $db->where('language_type',$uploadData['languageType']);
                    $preImageName = $db->getValue('mlm_memo_image_setting','upload_name');

                    if($preImageName){
                        if($preImageName == $uploadData['imgName']){
                            continue;
                        }else{
                            $preImageNameAry[] = $preImageName;
                        }
                    }

                    $fileType = end(explode(".", $uploadData['imgName']));
                    $upload_name = time()."_".General::generateUniqueChar('mlm_memo_image_setting','upload_name')."_".$groupCode."_".$uploadData["languageType"].".".$fileType;


                    $memo['image_name'] = $upload_name;
                    $memo['type'] = 'image';

                    $db->where('type', $memo['type']);
                    $db->where('language_type', $key);
                    $db->where('memo_id', $params['id']);
                    $memoSettingID = $db->getValue('mlm_memo_image_setting','id');

                    if($memoSettingID){
                        unset($updateSettingData);
                        $updateSettingData = array(
                                                   'upload_id' => $memo['image_id'],
                                                   'upload_name' => $memo['image_name'],
                                                  );

                        $db->where('id', $memoSettingID);
                        $db->update('mlm_memo_image_setting', $updateSettingData);

                    }else{
                        unset($insertData);
                        $insertData = array(
                                            'upload_id' => $memo['image_id'],
                                            'upload_name' => $memo['image_name'],
                                            'language_type' => $key,
                                            'memo_id' => $params['id'],
                                            'type' => $memo["type"],
                                           );

                        $db->insert('mlm_memo_image_setting', $insertData);
                    } 
                    unset($memoSettingID);

                    if($uploadData["defaultImage"] == 1){
                        $db->where('memo_id', $params['id']);
                        $db->where('name','defaultImageLanguage');
                        $getDefaultLang = $db->get('mlm_memo_setting', NULL, 'name, value, memo_id');

                        if($getDefaultLang){
                            unset($updateDefaultLang);
                            $updateDefaultLang = array(
                                                        'value' => $key,
                                                      );
                            $db->where('memo_id', $params['id']);
                            $db->where('name','defaultImageLanguage');
                            $db->update('mlm_memo_setting', $updateDefaultLang);
                        }
                        else{
                            unset($insertDefaultLang);
                            $defaultImageName = "defaultImageLanguage";
                            $insertDefaultLang = array(
                                                        'name' => $defaultImageName,
                                                        'value' => $key,
                                                        'memo_id' => $params['id'],
                                                      );

                            $db->insert('mlm_memo_setting', $insertDefaultLang);
                        }
                    }

                    $uploadDataNameAry[$key]['imgName'] = $upload_name;
                    $uploadDataNameAry[$key]['imgFileID'] = $uploadData['imgFileID'];
                    $uploadDataNameAry[$key]['imgFileName'] = $uploadData['imgFileName'];
                }
            }
            $uploadReturnData['uploadData'] = $uploadDataNameAry;



            $db->where("memo_id",$params['id']);
            $db->where('type', "image");
            if($availableLangAry) $db->where('language_type', $availableLangAry,"NOT IN");
            $copyDb = $db->copy();
            $preImageNameRes = $db->getValue('mlm_memo_image_setting','upload_name',null);
            foreach ($preImageNameRes as $preImageNameRow) {
                $preImageNameAry[] = $preImageNameRow;
            }

            $uploadReturnData['preImgName'] = $preImageNameAry;

            $copyDb->delete("mlm_memo_image_setting");

            if($params['leaderUsernameAry']) {
                foreach($params['leaderUsernameAry'] AS $leaderUsername){
                    unset($leaderID);
                    $db->where('username', $leaderUsername);
                    $leaderID = $db->getValue('client', 'id');

                    if(empty($leaderID)) {
                        $errorFieldArr[] = array(
                            'id'  => 'leaderUsernameError',
                            'msg' => 'Username does not exist.'
                        );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $leaderIDAry[] = $leaderID;
                }
            }
            if($params['excludeLeaderUsernameAry']) {
                foreach($params['excludeLeaderUsernameAry'] AS $excludeLeaderUsername){
                    unset($excludeLeaderID);
                    $db->where('username', $excludeLeaderUsername);
                    $excludeLeaderID = $db->getValue('client', 'id');

                    if(empty($excludeLeaderID)) {
                        $errorFieldArr[] = array(
                            'id'  => 'leaderUsernameError',
                            'msg' => 'Username does not exist.'
                        );

                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                    }
                    $excludeLeaderIDAry[] = $excludeLeaderID;
                }
            }

            $countryIDList = $db->get('country', null, 'id');
            foreach($countryIDList AS $countryData){
                $temp[] = $countryData['id'];
            }
            $countryIDList = $temp;
            
            foreach($params["countryIDAry"] AS $country_id){
                if(!in_array($country_id, $countryIDList)){

                    $errorFieldArr[] = array(
                        'id'  => 'countryIDError',
                        'msg' => 'country does not exist.'
                    );

                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                }
            }

            $updateData = array (
                'subject' => $params['subject'],
                'description' => $params['description'],
                'status' => $params['status'],
                'creator_id' => $clientID,
                'creator_type' => $site,
                'type' => $params['type'],
                // 'priority' => $params['priority'],
                // 'group_leader_id' => $leaderID,
                'updated_at' => $db->now()
            );

            $db->where('id', $params['id']);
            $db->update('mlm_memo', $updateData);

            if($leaderIDAry){
                $memoSetting['leaderUsernameAry'] = implode(", ", $leaderIDAry);
            }else{
                $memoSetting['leaderUsernameAry'] = '';
            }

            if($params['countryIDAry']){
                $memoSetting['countryIDAry'] = implode(", ", $params['countryIDAry']);
            }else{
                $memoSetting['countryIDAry'] = 0;
            }

            if($excludeLeaderIDAry){
                $memoSetting['excludeLeaderUsernameAry'] = implode(", ", $excludeLeaderIDAry);
            }else{
                $memoSetting['excludeLeaderUsernameAry'] = '';
            }

            if($params['excludeCountryIDAry']){
                $memoSetting['excludeCountryIDAry'] = implode(", ", $params['excludeCountryIDAry']);
            }else{
                $memoSetting['excludeCountryIDAry'] = '';
            }

            if($params['treeType']){
                $memoSetting['treeType'] = $params['treeType'];
            }

            if($params['startDate']){
                $memoSetting['startDate'] = date("d/m/Y", $params['startDate']);
            }

            if($params['endDate']){
                $memoSetting['endDate'] = date("d/m/Y", $params['endDate']);
            }

            foreach ($memoSetting as $key => $value) {
                $db->where('name', $key);
                $db->where('memo_id', $params['id']);
                unset($memoAry);
                $memoAry = $db->get('mlm_memo_setting', null, 'id, name, value, memo_id');

                if(empty($memoAry)){
                    unset($insertSettingParams);
                    $insertSettingParams = array (
                                                    'name' => $key,
                                                    'value' => $value,
                                                    'memo_id' => $params['id'],
                                                 );

                    $db->insert('mlm_memo_setting', $insertSettingParams);
                }

                if($memoAry){
                    unset($updateSettingParams);
                    $updateSettingParams = array(
                                                  'value' => $value,
                                                );
                    $db->where('name', $key);
                    $db->where('memo_id', $params['id']);

                    $db->update("mlm_memo_setting", $updateSettingParams);
                }
            }   
            
            // insert activity log 
            $titleCode    = 'T00067';
            $activityCode = 'L00091';
            $title = 'edit Memo';
            $activityData = array('user' => $getUsername);
            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");


            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $uploadReturnData);
        }

        public function removeMemo($params,$site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
            }

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $updateData = array('status' => "Deleted", 'updated_at' => $db->now());
            $db->where('id', $params['id']);
            $db->update('mlm_memo', $updateData);

            $db->where('id', $clientID);
            $getUsername = $db->getValue('admin', 'username');

            // insert activity log 
            $titleCode    = 'T00068';
            $activityCode = 'L00092';
            $title = 'remove Memo';
            $activityData = array('user' => $getUsername);
            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");


            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getMemoList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

            $searchData = $params['searchData'];

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'subject':
                            $db->where('subject', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'type':
                            $db->where('type', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('updated_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

                                $db->where('updated_at', date('Y-m-d 23:59:59', $dateTo), '<=');
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
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            $result = $db->get('mlm_memo', $limit, 'id, subject, description, type, status, creator_id, creator_type, created_at, updated_at');

            if(empty($result)){
                $newSearchArray = array('english', 'chineseSimplified', 'japanese', 'korean', 'vietnam', 'thailand');
                $db->where('language', $newSearchArray, "IN");
                $systemLanguages = $db->get('languages', NULL, 'language, language_code');
                $data['systemLanguages'] = $systemLanguages;
                $data['countryList'] = $db->get('country', null, 'id, name');
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language] /* No Results Found. */, 'data' => $data);
            }

            foreach($result as $value) {
                if($value['creator_type'] == 'SuperAdmin')
                    $superAdminID[] = $value['creator_id'];
                else if($value['creator_type'] == 'Admin')
                    $adminID[] = $value['creator_id'];
                else if ($value['creator_type'] == 'Member')
                    $clientID[] = $value['creator_id'];
            }
            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    if($k == "creator_id") {

                    }
                    else if($k == "creator_type")
                        $memo['creator_username'] = $usernameList[$v][$array['creator_id']];
                    else
                        $memo[$k] = $v;
                }
                $memoList[] = $memo;
            }

            // Get system languages
            // $db->where('disabled', 0);
            $newSearchArray = array('english', 'chineseSimplified', 'japanese', 'korean', 'vietnam', 'thailand');
            $db->where('language', $newSearchArray, "IN");
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $totalRecords = $copyDb->getValue('mlm_memo', 'count(id)');
            $data['memoList'] = $memoList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['countryList'] = $db->get('country', null, 'id, name');
            $data['numRecord'] = $limit[1];
            $data['systemLanguages'] = $systemLanguages;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addStarterpackEmailAttachment($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $date = date("Y-m-d H:i:s");
            $site = $db->userType;
            $adminID = $db->userID;
            $subject = $params['subject'];
            
            $db->where("type", "Upload Setting");
            $validMediaRes  = $db->map('name')->get("system_settings",null,"name, value ,reference");

            $validDocumentType = explode("#", $validMediaRes['validDocumentType']['value']);
            $maxDocumentSize   = $validMediaRes['validDocumentType']['reference'];

            if(empty($subject)) {
                $errorFieldArr[] = array(
                    'id'  => 'subjectError',
                    'msg' => $translations["E00218"][$language]
                );
            }
            
            if(empty($params['uploadData']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);

            $checkingValue = 0;
            foreach ($params['uploadData'] as $lang => $attachmentData) {
                if($attachmentData["defaultAttachment"] == 1){
                    $checkingValue = 1;
                }
                if(!in_array($attachmentData['attachmentType'], $validDocumentType)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00921"][$language], 'data' => $data);
                }
                if(!$attachmentData['attachmentSize']){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Attachment)", 'data' => $data);

                }
                if($attachmentData['attachmentSize']>$maxDocumentSize){
                    $sizeMB         = $maxDocumentSize / 1024 / 1024;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Attachment)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                }

            }


            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => $data);

            $groupCode = General::generateUniqueChar("pdffile","pdfname");

            foreach ($params['uploadData'] as $uploadData) {
                unset($uploadID);
                unset($type);
                unset($uploadDataAry);


                    $fileType = end(explode(".", $uploadData['attachmentName']));
                    $upload_name = time()."_".General::generateUniqueChar("pdffile","pdfname")."_".$groupCode.".".$fileType;
                    $attachement['upload_name'] = $upload_name;
                    $attachement["isActive"] = 0;
                    $attachement['type'] = $uploadData['attachmentType'];

                  $updatePdfFile = array(
                        'pdfname' => $attachement['upload_name'],
                        'type' => $attachement['type'],
                        'isActive' => 0,
                        'created_at' => $date,
                    );     

                $insert = $db->insert("pdffile", $updatePdfFile);
                $uploadDataAry[] = $attachement;


                      // delete the old record in mlm_document_setting based on the id
                    
            }     

            $uploadDataNameAry["data"] = $uploadDataAry;

            $uploadDataNameAry["doRegion"] = Setting::$configArray["doRegion"];
            $uploadDataNameAry["doEndpoint"] = Setting::$configArray["doEndpoint"];
            $uploadDataNameAry["doAccessKey"] = Setting::$configArray["doApiKey"];
            $uploadDataNameAry["doSecretKey"] = Setting::$configArray["doSecretKey"];
            $uploadDataNameAry["doBucketName"] = Setting::$configArray["doBucketName"];
            $uploadDataNameAry["doProjectName"] = Setting::$configArray["doProjectName"];
            $uploadDataNameAry["doFolderName"] = Setting::$configArray["doFolderName"];
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00169"][$language], 'data' => $uploadDataNameAry );
        }

                public function addDocument($params, $type, $operation) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $date = date("Y-m-d H:i:s");
            $site = $db->userType;
            $adminID = $db->userID;

            if($type == "eCatalogue"){
                $documentType = "eCatalogue";
            }else{
                $documentType = "normal";
                if(empty($params['description'])) {
                    $errorFieldArr[] = array(
                        'id'  => 'descriptionError',
                        'msg' => $translations["E00218"][$language]
                    );
                }
            }

            if($operation == "edit"){
                $documentID = $params['documentID'];
                if(empty($documentID)) {
                   return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");
                }
            }

            $db->where("type", "Upload Setting");
            $validMediaRes  = $db->map('name')->get("system_settings",null,"name, value ,reference");

            $validDocumentType = explode("#", $validMediaRes['validDocumentType']['value']);
            $maxDocumentSize   = $validMediaRes['validDocumentType']['reference'];

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                    'id'  => 'subjectError',
                    'msg' => $translations["E00218"][$language]
                );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                    'id'  => 'status',
                    'msg' => $translations["E00628"][$language]
                );
            }

            if(empty($params['uploadData']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);

            $checkingValue = 0;
            foreach ($params['uploadData'] as $lang => $attachmentData) {
                if($attachmentData['attachmentFlag'] == 1) {
                    if($attachmentData["defaultAttachment"] == 1){
                        $checkingValue = 1;
                    }

                    if(!$attachmentData['languageType']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
                    }

                    // if(!$attachmentData['attachmentData']){
                    //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00557"][$language], 'data' => $data);
                    // }

                    if(!in_array($attachmentData['attachmentType'], $validDocumentType)){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00921"][$language], 'data' => $data);
                    }
                    if(!$attachmentData['attachmentSize']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00992"][$language]. " (Attachment)", 'data' => $data);

                    }
                    if($attachmentData['attachmentSize']>$maxDocumentSize){
                        $sizeMB         = $maxDocumentSize / 1024 / 1024;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) . " (Attachment)" /* Maximum upload file size is %%maxSize%% MB */, 'data' => $data);
                    }
                }

            }

            if($checkingValue == 0 && $operation == "add"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Default Attachment fields cannot be empty.", 'data' =>'');
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => $data);

            // if(empty($attachmentID))
                // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00630"][$language], 'data' => "");

            $insertData = array (
                'subject' => $params['subject'],
                'description' => $params['description'],
                'status' => $params['status'],
                'creator_id' => $adminID,
                'creator_type' => $site,
                'type' => $documentType,
                'reference_id' => '',
                'created_at' => $date,
                'updated_at' => $date,
            );

            if($operation == "add"){
                $documentID = $db->insert('mlm_document', $insertData);   
                if(empty($documentID)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");             
                }

                $updateData = array(
                    "updateData" => array("value" => date("Y-m-d H:i:s")),
                    "name" => "documentRead",
                );
                Setting::updateClientSetting($updateData);

            }else if($operation == "edit"){
                unset($insertData['created_at']);
                $db->where('id', $documentID);
                $db->update('mlm_document', $insertData);
            }

            // foreach ($params['uploadData'] as $language AS $data) {
            //  if(!$data['languageType'])return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
            // }

            $groupCode = General::generateUniqueChar("mlm_document_setting","upload_name");

            foreach ($params['uploadData'] as $uploadData) {
                unset($uploadID);
                unset($type);
                unset($uploadDataAry);

                if ($uploadData['attachmentFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['attachmentName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_document_setting","upload_name")."_".$groupCode."_".$uploadData["languageType"].".".$fileType;
                    $type = "attachement";
                    $defaultAttachmentValue = $uploadData['defaultAttachment'];
                    $attachmentData['upload_name'] = $upload_name;
                    $attachmentData['type'] = $type;
                    $attachmentData['defaultAttachment'] = $defaultAttachmentValue;
                    $attachmentData['fileName'] = $uploadData['attachmentName'];
                    $attachmentData['upload_type'] = $uploadData['attachmentType'];

                    $uploadDataAry[] = $attachmentData;

                      // delete the old record in mlm_document_setting based on the id
                    $db->where('document_id', $documentID);
                    $db->where('language_type', $uploadData["languageType"]);
                    $db->delete('mlm_document_setting');

                    $language_type = $uploadData["languageType"];
                    $defaultAttachementName = "defaultAttachementLanguage";
                    $defaultImageName = "defaultImageLanguage";

                    // if($documentType != "eCatalogue"){
                    //     if($uploadData["description"]){
                    //         $settingImageParams = array(
                    //             'type' => "description",
                    //             'upload_name' => $uploadData["description"],
                    //             'language_type' => $language_type,
                    //             'document_id' => $documentID,
                    //         );     

                    //     $db->insert("mlm_document_setting", $settingImageParams);
                    //     }
                    // }
                    // if($uploadData["subject"]){
                    //     $settingImageParams = array(
                    //         'type' => "subject",
                    //         'upload_name' => $uploadData["subject"],
                    //         'language_type' => $language_type,
                    //         'document_id' => $documentID,
                    //     );     

                    //     $db->insert("mlm_document_setting", $settingImageParams);
                    // }
                }

                if($documentType != "eCatalogue"){
                    if($uploadData["description"]){
                        $settingImageParams = array(
                            'type' => "description",
                            'upload_name' => $uploadData["description"],
                            'language_type' => $language_type,
                            'document_id' => $documentID,
                        );     

                    $db->insert("mlm_document_setting", $settingImageParams);
                    }
                }

                if($uploadData["subject"]){
                    if ($operation == "add") {
                        $settingImageParams = array(
                            'type' => "subject",
                            'upload_name' => $uploadData["subject"],
                            'language_type' => $language_type,
                            'document_id' => $documentID,
                        );    

                        $db->insert("mlm_document_setting", $settingImageParams);
                    } else if ($operation == "edit"){
                        $db->where('language_type', $uploadData["languageType"]);
                        $db->where('document_id', $documentID);
                        $db->where('type', 'subject');
                        $copyDes = $db->copy();
                        $checkDes = $db->getValue('mlm_document_setting', 'count(id)');
                        if (!$checkDes) {
                            $settingImageParams = array(
                                'type' => "subject",
                                'upload_name' => $uploadData["subject"],
                                'language_type' => $language_type,
                                'document_id' => $documentID
                            );
                            $db->insert("mlm_document_setting", $settingImageParams);
                        } else {
                            $copyDes->update('mlm_document_setting', array('upload_name' => $uploadData["subject"]));
                        }
                    }
                }

                foreach ($uploadDataAry as $key => $value) {
                    // if($value['defaultAttachment'] == 1){
                    //     $updateData = array(   
                    //                         'attachment_id' => $uploadID,
                    //                         'attachment_name' => $upload_name
                    //                         );
                    //     $db->where("id",$documentID);
                    //     $db->update("mlm_document", $updateData);
                    // }
                    $settingImageParams = array(
                            'type' => $value['type'],
                            'upload_id' => $value['uploadID'],
                            'upload_name' => $value['upload_name'],
                            'upload_type' => $value['upload_type'],
                            'language_type' => $language_type,
                            'document_id' => $documentID,
                        );     

                    $db->insert("mlm_document_setting", $settingImageParams);

                    // $uploadDataNameAry[$language_type][$value['type']]['uploadName'] = $value['upload_name'];
                    // $uploadDataNameAry[$language_type][$value['type']]['fileName'] = $value['fileName'];
                    $uploadDataNameAry[$language_type]['attachmentName'] = $value['upload_name'];
                }
            }

            $uploadDataNameAry["doRegion"] = Setting::$configArray["doRegion"];
            $uploadDataNameAry["doEndpoint"] = Setting::$configArray["doEndpoint"];
            $uploadDataNameAry["doAccessKey"] = Setting::$configArray["doApiKey"];
            $uploadDataNameAry["doSecretKey"] = Setting::$configArray["doSecretKey"];
            $uploadDataNameAry["doBucketName"] = Setting::$configArray["doBucketName"];
            $uploadDataNameAry["doProjectName"] = Setting::$configArray["doProjectName"];
            $uploadDataNameAry["doFolderName"] = Setting::$configArray["doFolderName"];

            if($operation == "add"){
                $statusMessage = $translations["B00169"][$language]; /* Upload Successful */
            }else if($operation == "edit"){
                $statusMessage = $translations["B00373"][$language]; // Update Successful
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $statusMessage, 'data' => $uploadDataNameAry);
        }

        public function getDocument($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if($params['id']){
                     // $getAttachmentData = "(SELECT data FROM uploads WHERE uploads.id = attachment_id) AS attachment_data";
                $getAttachmentData = 'id';
                // $getAttachmentType = "(SELECT type FROM uploads WHERE uploads.id = attachment_id) AS attachment_type";
                $db->where('id', $params['id']);
                $result = $db->get('mlm_document', 1, 'subject, description, status, '.$getAttachmentData);

                if(empty($result))
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00629"][$language], 'data' => "");

                foreach($result as $array) {
                    foreach ($array as $k => $v) {
                        $document[$k] = $v;
                    }
                }

                $db->where('document_id', $params['id']);
                $db->orderBy('upload_id', 'ASC');
                $systemAnnouncementImageData = $db->get('mlm_document_setting', NULL, 'upload_id, language_type, upload_name , type ,(SELECT data FROM uploads WHERE id = upload_id) AS upload_data, upload_type');
                foreach ($systemAnnouncementImageData as $key => $value) {
                    if($value['type'] == "attachement"){
                        // $announcementDetail[$value['language_type']]['attachement_data'] = $value['upload_data'];
                        $announcementDetail[$value['language_type']]['attachement_name'] = $value['upload_name'];
                        $announcementDetail[$value['language_type']]['attachement_type'] = $value['upload_type'];
                    }else{
                        $announcementDetail[$value['language_type']][$value['type']] = $value['upload_name'];
                    }
                }
            }

            // if(empty($params['id']))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $db->where('disabled', 0);
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');
            $data['document'] = $document;
            $data['documentDetail'] =  $announcementDetail;
            $data['systemLanguages'] = $systemLanguages;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language], 'data' => $data);
        }

        public function editDocument($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $documentID = $params['id'];
            $site = $db->userType;

            if(empty($documentID) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            if(!$params['uploadData']) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00630"][$language] /* Upload attachment error */, 'data' => "");
            }

            $checkingValue = 0;
            foreach ($params['uploadData'] as $lang => $attachmentData) {
                if ($attachmentData['attachmentFlag'] == 1) {
                    if($attachmentData["defaultAttachment"] == 1){
                        $checkingValue = 1;
                    }

                    if(!$attachmentData['languageType']){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language] /* Please Select Language */, 'data' => $data);
                    }

                    if($checkingValue == 0){
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "Default Attachment fields cannot be empty.", 'data' =>'');
                    }
                }
                // if(!$attachmentData['attachmentData']){
                //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00557"][$language], 'data' => $data);
                // }

            }

            // if($checkingValue == 0){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Default Attachment fields cannot be empty.", 'data' =>'');
            // }

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => $translations["E00218"][$language] /* This field cannot be empty */
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'status',
                                            'msg' => $translations["E00628"][$language] /* This field value is invalid. */
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => $data);

            $db->where('id', $documentID);
            $upload = $db->getOne('mlm_document', 'attachment_id, attachment_name');
            
            if(empty($upload))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00629"][$language] /* No Results Found */, 'data' => "");


            $updateData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    // 'attachment_id' => $upload['attachment_id'],
                                    // 'attachment_name' => $upload['attachment_name'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'updated_at' => $db->now()
                                );
            $db->where('id', $documentID);
            $db->update('mlm_document', $updateData);

            // $db->where("id",$documentID);
            // $docSettingaArr = $db->map('language_type')->get("mlm_document_setting",NULL,"language_type,id");
            // $db->where("document_id",$documentID);
            // $db->delete("mlm_document_setting");

            $groupCode = General::generateUniqueChar('mlm_document_setting','upload_name');
            $newAttachmentFlag = 0;

            foreach ($params['uploadData'] as $uploadData) {
                unset($uploadID);
                unset($type);
                unset($uploadDataAry);

                $availableLangAry[] = $uploadData['languageType'];

                if($uploadData["description"]){
                    $db->where('language_type',$uploadData["languageType"]);
                    $db->where('document_id',$documentID);
                    $db->where('type','description');
                    $copyDes = $db->copy();
                    $checkDes = $db->getValue('mlm_document_setting','count(id)');
                    if(!$checkDes){

                        $settingImageParams = array(
                                            'type' => "description",
                                            'upload_name' => $uploadData["description"],
                                            'language_type' => $uploadData["languageType"],
                                            'document_id' => $documentID,
                                            );     

                        $db->insert("mlm_document_setting", $settingImageParams);
                    }else{
                        $copyDes->update('mlm_document_setting',array('upload_name' => $uploadData["description"]));
                    }
                }

                if($uploadData["subject"]){
                    $db->where('language_type',$uploadData["languageType"]);
                    $db->where('document_id',$documentID);
                    $db->where('type','subject');
                    $copyDes = $db->copy();
                    $checkDes = $db->getValue('mlm_document_setting','count(id)');
                    if(!$checkDes){
                        $settingImageParams = array(
                                            'type' => "subject",
                                            'upload_name' => $uploadData["subject"],
                                            'language_type' => $uploadData["languageType"],
                                            'document_id' => $documentID,
                                            );     

                        $db->insert("mlm_document_setting", $settingImageParams);
                    }else{
                        $copyDes->update('mlm_document_setting',array('upload_name' => $uploadData["subject"]));
                    }
                }

                $uploadReturnData[$uploadData['languageType']]['attachmentName'] = $uploadData['attachmentName'];

                if ($uploadData['attachmentFlag'] == 1) {
                    $db->where('document_id',$documentID);
                    $db->where('language_type',$uploadData['languageType']);
                    $db->where('type','attachement');
                    $deleteCondition = $db->copy();
                    $preAttachmentName = $db->getValue('mlm_document_setting','upload_name');
                    // $availableLangAry[] = $uploadData['languageType'];

                    if($preAttachmentName){
                        if($preAttachmentName == $uploadData['attachmentName']){
                            continue;
                        }else{
                            $preAttachmentNameAry[] = $preAttachmentName;
                        }
                    }

                    $deleteCondition->delete("mlm_document_setting");

                    $fileType = end(explode(".", $uploadData['attachmentName']));
                    $upload_name = time()."_".General::generateUniqueChar('mlm_document_setting','upload_name')."_".$groupCode."_".$uploadData["languageType"].".".$fileType;
                    $type = "attachement";
                    $defaultAttachmentValue = $uploadData['defaultAttachment'];

                    $attachmentData['upload_name'] = $upload_name;
                    $attachmentData['type'] = $type;
                    $attachmentData['defaultAttachment'] = $defaultAttachmentValue;
                    $attachmentData['fileID'] = $uploadData['attachementFileID'];
                    $attachmentData['fileName'] = $uploadData['attachementFileName'];
                    $attachmentData['upload_type'] = $uploadData['attachmentType'];

                    $uploadDataAry[] = $attachmentData;
                    $newAttachmentFlag = 1;
                }

                $language_type = $uploadData["languageType"];
                $defaultAttachementName = "defaultAttachementLanguage";
                $defaultImageName = "defaultImageLanguage";

                foreach ($uploadDataAry as $key => $value) {
                    if($value['defaultAttachment'] == 1){
                        $updateData = array(   
                                            'attachment_id' => $uploadID,
                                            'attachment_name' => $upload_name
                                            );
                        $db->where("id",$documentID);
                        $db->update("mlm_document", $updateData);
                    }

                    // if($docSettingaArr[$language_type]){
                    //  $updateData = array(
                    //                      'upload_id'=>$uploadID,
                    //                      "upload_name" => $value['upload_name']
                    //                      );
                    //  $db->where("id",$docSettingaArr[$language_type]);
                    //  $db->update("mlm_document_setting", $updateData);

                    // }else{
                    $settingImageParams = array(
                                            'type' => $value['type'],
                                            'upload_id' => $value['uploadID'],
                                            'upload_name' => $value['upload_name'],
                                            'upload_type' => $value['upload_type'],
                                            'language_type' => $language_type,
                                            'document_id' => $documentID,
                                            );     

                    $db->insert("mlm_document_setting", $settingImageParams);
                    // }
                    // $uploadDataNameAry[$language_type][$value['type']]['uploadName'] = $value['upload_name'];
                    // $uploadDataNameAry[$language_type][$value['type']]['fileID'] = $value['fileID'];
                    // $uploadDataNameAry[$language_type][$value['type']]['fileName'] = $value['fileName'];
                    $uploadReturnData[$language_type]['attachmentName'] = $value['upload_name'];
                }   
            }

            $db->where("document_id",$documentID);
            $db->where('language_type', $availableLangAry,"NOT IN");
            $copyDb = $db->copy();
            $db->where('type', "attachement");
            $preAttachmentNameRes = $db->getValue('mlm_document_setting','upload_name',null);
            foreach ($preAttachmentNameRes as $preAttachmentNameRow) {
                $preAttachmentNameAry[] = $preAttachmentNameRow;
            }

            if ($preAttachmentNameAry) {
                $uploadReturnData['preAttachmentName'] = $preAttachmentNameAry;
            }

            $copyDb->delete("mlm_document_setting");

            $uploadReturnData["doRegion"] = Setting::$configArray["doRegion"];
            $uploadReturnData["doEndpoint"] = Setting::$configArray["doEndpoint"];
            $uploadReturnData["doAccessKey"] = Setting::$configArray["doApiKey"];
            $uploadReturnData["doSecretKey"] = Setting::$configArray["doSecretKey"];
            $uploadReturnData["doBucketName"] = Setting::$configArray["doBucketName"];
            $uploadReturnData["doProjectName"] = Setting::$configArray["doProjectName"];
            $uploadReturnData["doFolderName"] = Setting::$configArray["doFolderName"];

            if ($newAttachmentFlag == 0) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00400"][$language] /* Successful updated document */, 'data' => null);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00400"][$language] /* Successful updated document */, 'data' => $uploadReturnData);
            }
        }

        public function removeDocument($params, $type) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $date = date("Y-m-d H:i:s");

            if ($type == 'eCatalogue') {
                $documentID = $params['documentID'];    
            } else if ($type == 'normal') {
                $documentID = $params['id'];
            }

            if(empty($documentID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $updateData = array('status' => "Deleted", 'updated_at' => $date);
            $db->where('id', $documentID);
            $db->update('mlm_document', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00458"][$language], 'data' => "");
        }

        public function getDocumentList($params, $type) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $seeAll = $params['seeAll'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $limit = General::getLimit($pageNumber);
            $searchData = $params['searchData'];
            $site = $db->userType;

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'subject':
                            $db->where('subject', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                $db->where('Date(created_at)', date('Y-m-d 00:00:00', $dateFrom), '>=');
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

                                $db->where('Date(created_at)', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            break;

                        case 'updatedAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0){
                                    $db->resetState();
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language], 'data'=>"");
                                }
                                    
                                $db->where('Date(updated_at)', date('Y-m-d 00:00:00', $dateFrom), '>=');
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

                                $db->where('Date(updated_at)', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($site == "Member"){
                $db->where('status', 'Active');
            }

            if($type == "eCatalogue"){
                $db->where('type', 'eCatalogue');
            }else{
                $db->where('type', 'normal');
            }

            if($seeAll){
                $limit = NULL;
            }

            $statusList = array("Active","Inactive");
            $db->orderBy('id', 'Desc');
            $db->where('status', $statusList, "IN");
            $copyDb = $db->copy();
            $result = $db->get('mlm_document', $limit, 'id, subject, description, status, creator_id, creator_type, created_at, updated_at');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00629"][$language], 'data' => "");

            foreach($result as $value) {
                if($value['creator_type'] == 'SuperAdmin')
                    $superAdminID[] = $value['creator_id'];
                else if($value['creator_type'] == 'Admin')
                    $adminID[] = $value['creator_id'];
                else if ($value['creator_type'] == 'Member')
                    $clientID[] = $value['creator_id'];

                $documentIDAry[$value['id']] = $value['id'];
            }

            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            if(!empty($documentIDAry) && $type == "eCatalogue"){
                $db->where('document_id', $documentIDAry, 'IN');
                $db->where('language_type', $language);
                $db->where('type', 'subject');
                $docDisplay = $db->map('document_id')->get('mlm_document_setting', null, 'document_id, upload_name');
            }

            foreach($result as $array) {
                // change date format
                $array["created_at"] = date($dateTimeFormat, strtotime($array["created_at"])); 
                $array["updated_at"] = date($dateTimeFormat, strtotime($array["updated_at"])); 
                foreach ($array as $k => $v) {
                    if($k == "creator_id") {
                    }else if($k == "creator_type"){
                        $document['creator_username'] = $usernameList[$v][$array['creator_id']];
                    }else{
                        $document[$k] = $v;
                    }
                }
                if ($type == "eCatalogue") $document['display'] = $docDisplay[$array['id']]?:$document['subject'];
                $documentList[] = $document;
            }

            $totalRecord = $copyDb->getValue('mlm_document', 'count(id)');
            $data['documentList'] = $documentList;

            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00425"][$language], 'data' => $data);
        }

        public function documentDownloadList($params,$userID) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

            $searchData = $params['searchData'];

            $db->where('status', 'Active');
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            // $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=attachment_id) AS base_64";
            // $getFileType = "(SELECT type FROM uploads WHERE uploads.id=attachment_id) AS file_type";
            // $result = $db->get('mlm_document', $limit, 'id, subject, description, attachment_name, '.$getBase64.','.$getFileType);
            $result = $db->get('mlm_document', $limit, 'id, subject, description, created_at');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00629"][$language], 'data' => "");

            $db->where("language_type",$language);
            $res = $db->get("mlm_document_setting",NULL,"document_id,upload_name,type");
            foreach($res AS $row ){
                $langAtt[$row['document_id']][$row['type']] = $row['upload_name'];
            }

            foreach($result as $value) {
                unset($atttName);
                unset($description);
                unset($subject);
                if($langAtt[$value['id']]["attachement"]) $atttName = $langAtt[$value['id']]["attachement"];
                if($langAtt[$value['id']]["description"]) $description = $langAtt[$value['id']]["description"];
                if($langAtt[$value['id']]["subject"]) $subject = $langAtt[$value['id']]["subject"];
                $document['subject'] = $subject ? $subject : $value['subject'];
                $document['description'] = $description ? $description : $value['description'];
                $document['created_at'] = $value['created_at'];
                // $document['file'] = '<button type="button" class="btn btn-success btn-cons" id="'.$value["id"].'" onclick="createDownloadFile(this)"><i class="fa fa-download"></i></button>';
                $document['id'] = $value['id'];
                
                $documentList[] = $document;
            }

            $updateSettingData = array(
                                    "updateData" => array("value" => date("Y-m-d H:i:s")),
                                    "name" => "documentRead",
                                    "clientID" => $userID,
                                );
            Setting::updateClientSetting($updateSettingData);

            $totalRecords = $copyDb->getValue('mlm_document', 'count(id)');
            $data['documentList'] = $documentList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function documentDownload($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['documentID']))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00631"][$language], 'data' => "");

            $db->where('document_id', $params['documentID']);
            $db->where('language_type', $language);
            $db->where('type', "attachement");
            $langDocument = $db->getOne("mlm_document_setting","(SELECT data FROM uploads WHERE uploads.id=upload_id) AS fileData,(SELECT type FROM uploads WHERE uploads.id=upload_id) AS fileType,upload_name as fileName");

            $db->where('id', $params['documentID']);
            $db->where('status', 'Active');
            // $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=attachment_id) AS base_64";
            // $getFileType = "(SELECT type FROM uploads WHERE uploads.id=attachment_id) AS file_type";
            // $result = $db->get('mlm_document', 1, 'attachment_name, '.$getBase64.','.$getFileType);

            if(!$langDocument){
                $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=attachment_id) AS fileData";
                $getFileType = "(SELECT type FROM uploads WHERE uploads.id=attachment_id) AS fileType";
                $download = $db->getOne('mlm_document', 'attachment_name as fileName, '.$getBase64.','.$getFileType);
            }else{
                $download = $langDocument;
            }

            
            if(empty($download))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00631"][$language], 'data' => "");

            // foreach($result as $value) {
            //     $download = '<a id="thisDownload" download="'.$value["attachment_name"].'" href="data:'.$value["file_type"].';base64,'.$value["base_64"].'" style="display: none;"><span></span></a>';
            // }
            $base64 = explode(",", $download['fileData']);
            $download['fileData'] = $base64[1];

            $data['download'] = $download;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getPopUpMemo($userID, $turnOffPopUpMemo) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;

            if($turnOffPopUpMemo && $userID){
                $db->where('client_id', $userID);
                $db->where('name', "turnOffPopUpMemo");
                $db->where('value', "1");
                $result = $db->getOne('client_setting', 'reference, value');
                if($result){
                    $db->where('created_at', $result['reference'], '>');
                }
            }

            $db->where('status', "Active");
            if(!$userID) $db->where('type', 'public');
            $db->orderBy('ID', "DESC");
            $memoData = $db->get('mlm_memo', null, 'id, subject, description, created_at');

            if(empty($memoData))
                return "";

            if($memoData){  
                if($userID){
                    $db->where('id', $userID);
                    $country_id = $db->getValue('client', 'country_id');

                    $db->where('client_id', $userID);
                    $leaderSponsorRow = $db->getValue("tree_sponsor", "trace_key");
                    $leader_id_sponsor_ary = explode('/', $leaderSponsorRow);

                    $db->where('client_id', $userID);
                    $leaderPlacementRow = $db->getValue("tree_placement", "trace_key");
                    $leaderPlacementRow = str_replace(array('-1<', '-1>', '-1'), '/', $leaderPlacementRow);
                    $leader_id_placement_ary = explode('/', $leaderPlacementRow);
                }

                if ($language == "chineseSimplified" || $language == "chineseTraditional" ){
                    $language = "chineseSimplified";
                }

                // take off on 20 Jan 2020, client want separate it
                // if ($language == "vietnam" || $language == "thailand" ){
                //     $language = "english";
                // }

                foreach($memoData as $key => $value){

                       $memo['subject'] = $value['subject'];
                       $memo['description'] = $value['description'];
                       $memo['created_at'] = $value['created_at'];
                       $memo['id'] = $value['id'];

                       $memoID = $memo['id'];
                       $db->where('memo_id', $memoID);
                       $db->where('language_type', $language);
                       // $db->where('upload_id', 0, ">");
                       $memoImageData = $db->get('mlm_memo_image_setting', null, 'id, type, upload_id, upload_name, language_type, upload_type');

                        if($memoImageData[0]){
                            $defaultImageData['upload_type'] = $memoImageData[0]["upload_type"];
                            $defaultImageData['language_type'] = $memoImageData[0]["language_type"];
                            $defaultImageData['upload_name'] = $memoImageData[0]["upload_name"];
                        }
                        else if(!$memoImageData[0]){
                            $db->where('memo_id', $memoID);
                            $db->where('name',"defaultImageLanguage");
                            $memoImageData = $db->get('mlm_memo_setting', null, 'id, name, value');
                            $defaultLanguage = $memoImageData[0]["value"];

                            $db->where('memo_id', $memoID);
                            $db->where('language_type', $defaultLanguage);
                            $memoImageData = $db->get('mlm_memo_image_setting', null, 'id, type, upload_id, upload_name, language_type, upload_type');

                            $defaultImageData['upload_type'] = $memoImageData[0]["upload_type"];
                            $defaultImageData['defaultLanguage'] = $memoID;
                            $defaultImageData['upload_name'] = $memoImageData[0]["upload_name"];
                        }

                       $memoData[$key]['file_type'] = $defaultImageData['upload_type'];
                       $memoData[$key]['upload_name'] = $defaultImageData['upload_name'];

                    $db->where('memo_id', $memoID);
                    $result = $db->get("mlm_memo_setting", NULL, "value, name");

                    if(!$result) continue;
                    foreach ($result as $key1 => $value) {
                        $memoSetting[$value['name']] = explode(', ', $value['value']);
                    }

                    if($memoSetting['treeType'][0] == 'sponsor'){
                        $leader_id_ary = $leader_id_sponsor_ary;
                    }else if($memoSetting['treeType'][0] == 'placement'){
                        $leader_id_ary = $leader_id_placement_ary;
                    }

                    $granted = 0;
                    $leaderSetting = 0;
                    $excludeLeaderSetting = 0;
                    $countrySetting = 0;
                    $excludeCountrySetting = 0;
                    $startDateSetting = 0;
                    $endDateSetting = 0;

                    $currentDate = date("Ymd");
                    unset($leaderID);

                    foreach ($memoSetting['startDate'] as $key6 => $startDate) {
                        $startDateFormat = explode("/", $startDate);
                        $startDateFormation = $startDateFormat[2]."".$startDateFormat[1]."".$startDateFormat[0];
                        $finalStartDate = date($startDateFormation);
                        
                        if($finalStartDate <= $currentDate){
                            $startDateSetting = 1;
                            break;
                        }
                    }
                    if(!$memoSetting['startDate']){
                        $startDateSetting = 1;
                    }
                    foreach ($memoSetting['endDate'] as $key7 => $endDate) {
                        $endDateFormat = explode("/", $endDate);
                        $endDateFormation = $endDateFormat[2]."".$endDateFormat[1]."".$endDateFormat[0];
                        $finalEndDate = date($endDateFormation);

                        if($finalEndDate == 0){
                            $endDateSetting = 1;
                            break; 
                        }
                        if($finalEndDate >= $currentDate){
                            $endDateSetting = 1;
                            break;
                        }
                    }
                    if(!$memoSetting['endDate']){
                        $endDateSetting = 1;
                    }

                    if($userID){
                        foreach ($memoSetting['leaderUsernameAry'] as $key2 => $leaderID) {
                            if($leaderID > 0 && in_array($leaderID, $leader_id_ary)){
                                $leaderSetting = 1;
                                break;
                            }
                        }
                        if(!$memoSetting['leaderUsernameAry'] || $memoSetting['leaderUsernameAry'][0] == ""){
                            $leaderSetting = 1;
                        }
                        unset($excludeLeaderID);
                        foreach ($memoSetting['excludeLeaderUsernameAry'] as $key3 => $excludeLeaderID) {
                            if($excludeLeaderID > 0 && in_array($excludeLeaderID, $leader_id_ary)){
                                $excludeLeaderSetting = 1;
                                break;
                            }
                        }
                        unset($countryID);
                        foreach ($memoSetting['countryIDAry'] as $key4 => $countryID) {                        
                            if(($countryID > 0 && $countryID == $country_id) || $countryID == 0){
                                $countrySetting = 1;
                                break;
                            }
                        }
                        unset($excludeCountryID);
                        foreach ($memoSetting['excludeCountryIDAry'] as $key5 => $excludeCountryID) {                        
                            if($excludeCountryID > 0 && $excludeCountryID == $country_id){
                                $excludeCountrySetting = 1;
                                break;
                            }
                        }

                        if($countrySetting == 1 && $leaderSetting == 1 && $excludeLeaderSetting != 1 && $excludeCountrySetting != 1 && $startDateSetting == 1 && $endDateSetting == 1){
                            $granted = 1;
                        }  
                    }else{
                        if($startDateSetting == 1 && $endDateSetting == 1) {
                            $granted = 1;
                        }
                    }

                    if($granted == 0){
                        unset($memoData[$key]);
                    }
                } 
            } 

            return $memoData ? $memoData : "";
        }

        public function readAnnouncement($params,$userID){
            $db = MysqliDb::getInstance();
            $id = $params['id'];

            $db->where("name","announcement");
            $db->where("value",$id);
            $db->where("client_id",$userID);
            $db->delete("client_setting");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        function getDocumentAnnouncementUnreadMessage($params,$userID,$site){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            // $type = $params['type']; //announcementRead documentRead
            $typeArr = array("announcementRead","documentRead");

            // if(!$type) return array('status' => "error", 'code' => 1, 'statusMsg' =>  "Please Insert Type", 'data' => $data);
            // if(!in_array($type, $typeArr)) return array('status' => "error", 'code' => 1, 'statusMsg' =>  "Invalid Type", 'data' => $data);
            
            // get setting
        	$db->where("client_id",$userID);
        	$db->where("name",$typeArr,"IN");
        	$res = $db->map("name")->get("client_setting",NULL,"name,value");

        	foreach($typeArr AS $type){
	        	$table = ($type == "announcementRead" ? "mlm_announcement" : "mlm_document");
	        	// if no record insert new record
	        	$dateTime = $res[$type];
	        	if(!$res[$type]){
	        		$db->where("status","Active");
		        	$db->orderBy("created_at","ASC");
		        	$dateTime = $db->getValue($table,"created_at");
	        		if(!$dateTime) $dateTime = date("Y-m-d H:i:s");

	        		$insertData = array(
	        							"name"=>$type,
	        							"client_id"=>$userID,
	        							"value"=>$dateTime,
	        							);
	        		$db->insert("client_setting",$insertData);
	        	}
		        	        	// get unread message count
	        	$db->where("status","Active");
	        	$db->where("created_at",$dateTime,">=");
	        	$count = $db->getValue($table,"COUNT(id)");

	        	$data[$type]['unreadMessage'] = $count;
	        	$data[$type]['type'] = $type;
        	}


        	return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }




        public function addBanner($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $adminID = $db->userID;
            $validTypeAry = array("public", "member");

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            $startDate = $params['startDate'];
            $endDate = $params['endDate'];

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                    'id'  => 'subjectError',
                    'msg' => $translations["E01095"][$language] /* This field cannot be left blank. */
                );
            }

            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                    'id'  => 'descriptionError',
                    'msg' => $translations["E01095"][$language] /* This field cannot be left blank. */
                );
            }

            if(empty($params['type'])){
               $errorFieldArr[] = array(
                    'id'  => 'typeError',
                    'msg' => $translations["E01095"][$language] /* This field cannot be left blank. */
                );
            }

            if(!in_array($params['type'], $validTypeAry)){
               $errorFieldArr[] = array(
                    'id'  => 'typeError',
                    'msg' => $translations["E01037"][$language] /* This is not a valid type. */
                );
            }

            if(empty($params['page'])){
                $errorFieldArr[] = array(
                    'id'  => 'pageError',
                    'msg' => $translations["E01095"][$language] /* This field cannot be left blank. */
                );
            }

            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                    'id'  => 'statusError',
                    'msg' => $translations["E01097"][$language] /* This field value is invalid. */
                );
            }

            if(empty($params['uploadData'])) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00602"][$language], 'data' => $data);
            }

            foreach ($params['uploadData'] as $lang => $imageData) {
                $validImageSet  = $uploadSetting['validImageType'];
                $validImageType = explode("#", $validImageSet['value']);
                $validImageSize = $validImageSet['reference'];
                $sizeMB         = $validImageSize / 1024 / 1024;

                if($imageData["imgFlag"]) {
                    if(!in_array($imageData["imgType"], $validImageType)) {
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                        );
                    }

                    if(!$imageData['imgSize'] || $imageData['imgSize'] > $validImageSize){
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                        );
                    }
                }

                if(!$imageData['languageType']){
                    $errorFieldArr[] = array(
                        'id'  => "imgLanguageError",
                        'msg' => $translations["E00602"][$language] /* Please Select Language. */
                    );
                }
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */, 'data' => $data);
            }

            $insertData = array (
                'subject' => $params['subject'],
                'description' => $params['description'],
                'status' => $params['status'],
                'type' => $params['type'],
                'page' => $params['page'],
                'start_date' => date("Y-m-d", $params['startDate']),
                'end_date' => date("Y-m-d", $params['endDate']),
                'creator_id' => $params['clientID'],
                'creator_type' => $site,
                'reference_id' => '',
                'created_at' => $dateTime,
                'updated_at' => $dateTime
            );

            $bannerID = $db->insert('mlm_banner', $insertData);

            if(empty($bannerID)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01099"][$language] /* Failed to insert banner. */, 'data' => "");
            }

            $groupCode = General::generateUniqueChar("mlm_banner_image_setting","upload_name");

            foreach ($params['uploadData'] as $uploadData) {
                unset($uploadID);
                unset($type);
                unset($uploadDataAry);

                if($uploadData['imgFlag'] == 1) {
                    $fileType = end(explode(".", $uploadData['imgName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_banner_image_setting","upload_name")."_".$groupCode."_".$uploadData["languageType"].".".$fileType;
                    $type = "image";
                    $defaultImageValue = $uploadData['defaultImage'];

                    $imageData['upload_name'] = $upload_name;
                    $imageData['type'] = $type;
                    $imageData['defaultImage'] = $defaultImageValue;
                    $imageData['imgFileID'] = $uploadData['imgFileID'];
                    $imageData['imgFileName'] = $uploadData['imgFileName'];
                    $imageData['upload_type'] = $uploadData['imgType'];

                    $uploadDataAry[] = $imageData;
                }

                $language_type = $uploadData["languageType"];
                $defaultImageName = "defaultImageLanguage";

                foreach ($uploadDataAry as $key => $value) {
                    if($value['defaultImage'] == 1){
                        $insertDefaultImage = array(   
                            'name' => $defaultImageName,
                            'value' => $language_type,
                            'banner_id' => $bannerID,
                        );

                        $db->insert("mlm_banner_setting", $insertDefaultImage);
                    }

                    $settingImageParams = array(
                        'type' => $value['type'],
                        'upload_name' => $value['upload_name'],
                        'language_type' => $language_type,
                        'upload_type' => $value['upload_type'],
                        'banner_id' => $bannerID,
                    );     

                    $db->insert("mlm_banner_image_setting", $settingImageParams);

                    $uploadDataNameAry[$language_type]['imgName'] = $value['upload_name'];
                    $uploadDataNameAry[$language_type]['imgFileID'] = $value['imgFileID'];
                    $uploadDataNameAry[$language_type]['imgFileName'] = $value['imgFileName'];
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A01592"][$language] /* Successfully Added Banner. */, 'data' => $uploadDataNameAry);
        }

        public function getBanner($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateFormat = Setting::$systemSetting['systemDateFormat'];

            $id = $params["id"];

            if(empty($id)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01101"][$language] /* ID cannot be empty. */, 'data' => "");
            }

            $db->where('disabled', 0);
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $db->where('id', $id);
            $result = $db->get('mlm_banner', 1, 'id, subject, description, priority, status, page');

            if(empty($result)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    if($k == 'id'){
                        $db->where("id", $v);
                        $db->where("start_date", "0",">");
                        // $db->where("status", "Active");
                        $result = $db->get("mlm_banner", NULL, "start_date, end_date");
                        if($result){
                            foreach ($result as $key => $value) {
                                $permissions['startDate'] = date($dateFormat, strtotime($value['start_date']));
                                $permissions['endDate'] = date($dateFormat, strtotime($value['end_date']));
                            }
                        } else {
                            $permissions['startDate'] = null;
                            $permissions['endDate'] = null;
                        }
                    }

                    $banner[$k] = $v;
                }

                $db->where('banner_id', $id);
                $systemBannerImageData = $db->get('mlm_banner_image_setting', NULL, 'language_type, upload_name, type');

                foreach ($systemBannerImageData as $key => $value) {
                    if($value['type'] == "image"){
                        $bannerDetail[$value['language_type']]['image_name'] = $value['upload_name'];
                        $bannerDetail[$value['language_type']]['image_type'] = $value['type'];
                    }
                }

                $db->where('banner_id', $id);
                $db->where('name','defaultImageLanguage');
                $getBannerDefault = $db->get('mlm_banner_setting', NULL, 'name, value, banner_id');
                $bannerDefault = $getBannerDefault[0]['value'];    
            }

            $bannerSortedDetail[$bannerDefault] = $bannerDetail[$bannerDefault];
            unset($bannerDetail[$bannerDefault]);

            foreach ($bannerDefault as $key => $value) {
                $bannerSortedDetail[$key] = $value;
            }

            $data['permissions'] = $permissions;
            $data['banner'] = $banner;
            $data['bannerDetail'] = $bannerSortedDetail;
            $data['bannerDefault'] = $bannerDefault;
            // $countryListRes = Country::getCountriesList();
            // $data['countryList'] = $countryListRes['data']['countriesList'];
            $data['systemLanguages'] = $systemLanguages;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getBannerList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

            $searchData = $params['searchData'];

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'subject':
                            $db->where('subject', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                $db->where('created_at', date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('updated_at', date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                $db->where('updated_at', date('Y-m-d 23:59:59', $dateTo), '<=');
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
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            $result = $db->get('mlm_banner', $limit, 'id, subject, description, status, creator_id, creator_type, created_at, updated_at');

            if(empty($result)){
                $db->where('disabled', 0);
                $systemLanguages = $db->get('languages', NULL, 'language, language_code');
                $data['systemLanguages'] = $systemLanguages;
                // $data['countryList'] = $db->get('country', null, 'id, name');
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language] /* No Results Found. */, 'data' => $data);
            }

            foreach($result as $value) {
                if($value['creator_type'] == 'SuperAdmin')
                    $superAdminID[] = $value['creator_id'];
                else if($value['creator_type'] == 'Admin')
                    $adminID[] = $value['creator_id'];
                else if ($value['creator_type'] == 'Member')
                    $clientID[] = $value['creator_id'];
            }

            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                }
            }

            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }

            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    if($k == "creator_id") {

                    }
                    else if($k == "creator_type")
                        $banner['creator_username'] = $usernameList[$v][$array['creator_id']]?:"-";
                    else
                        $banner[$k] = $v;
                }
                $bannerList[] = $banner;      
            }

            // Get system languages
            $db->where('disabled', 0);
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $totalRecords = $copyDb->getValue('mlm_banner', 'count(id)');
            $data['bannerList'] = $bannerList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            // $data['countryList'] = $db->get('country', null, 'id, name');
            $data['numRecord'] = $limit[1];
            $data['systemLanguages'] = $systemLanguages;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editBanner($params, $site) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $adminID = $db->userID;
            $validTypeAry = array("public", "member");

            if(empty($params['id']) || empty($params['clientID'])) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01101"][$language] /* ID cannot be empty. */, 'data' => "");
            }

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                    'id'  => 'subjectError',
                    'msg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */
                );
            }

            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                    'id'  => 'descriptionError',
                    'msg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */
                );
            }

            if(empty($params['type'])){
               $errorFieldArr[] = array(
                    'id'  => 'typeError',
                    'msg' => $translations["E01095"][$language] /* This field cannot be left blank. */
                );
            }

            if(!in_array($params['type'], $validTypeAry)){
               $errorFieldArr[] = array(
                    'id'  => 'typeError',
                    'msg' => $translations["E01096"][$language] /* This is not a valid type. */
                );
            }

            if(empty($params['page'])){
                $errorFieldArr[] = array(
                    'id'  => 'pageError',
                    'msg' => $translations["E01095"][$language] /* This field cannot be left blank. */
                );
            }

            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                    'id'  => 'statusError',
                    'msg' => $translations["E01097"][$language] /* This field value is invalid. */
                );
            }

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            foreach ($params['uploadData'] as $key => $uploadData) {
                $validImageSet  = $uploadSetting['validImageType'];
                $validImageType = explode("#", $validImageSet['value']);
                $validImageSize = $validImageSet['reference'];
                $sizeMB         = $validImageSize / 1024 / 1024;

                if($uploadData["imgFlag"]) {
                    if(!in_array($uploadData["imgType"], $validImageType)) {
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                        );
                    }

                    if(!$uploadData['imgSize'] || $uploadData['imgSize'] > $validImageSize){
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                        );
                    }
                }
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /*Required fields cannot be empty.*/, 'data' => $data);
            }
            
            $checkingValue = 0;

            foreach ($params['uploadData'] as $key => $uploadData) {
                if($uploadData["defaultImage"] == 1) {
                    $checkingValue = 1;
                }
            }

            if($checkingValue == 0){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01095"][$language] /* This field cannot be left blank. */, 'data' =>'');
            }

            $groupCode = General::generateUniqueChar('mlm_banner_image_setting','upload_name');

            foreach ($params['uploadData'] as $key => $uploadData) {
                $availableLangAry[] = $key;
                if($uploadData['imgFlag'] == 1) {
                    $db->where('banner_id',$params['id']);
                    $db->where('language_type',$uploadData['languageType']);
                    $preImageName = $db->getValue('mlm_banner_image_setting','upload_name');
                    $availableLangAry[] = $key;

                    if($preImageName){
                        if($preImageName == $uploadData['imgName']){
                            continue;
                        }else{
                            $preImageNameAry[] = $preImageName;
                        }
                    }

                    $fileType = end(explode(".", $uploadData['imgName']));
                    $upload_name = time()."_".General::generateUniqueChar('mlm_banner_image_setting','upload_name')."_".$groupCode."_".$uploadData["languageType"].".".$fileType;

                    $banner['image_name'] = $upload_name;
                    $banner['type'] = 'image';

                    $db->where('type', $banner['type']);
                    $db->where('language_type', $key);
                    $db->where('banner_id', $params['id']);
                    $bannerSettingID = $db->getValue('mlm_banner_image_setting','id');

                    if($bannerSettingID){
                        unset($updateSettingData);
                        $updateSettingData = array(
                           'upload_name' => $banner['image_name'],
                        );

                        $db->where('id', $bannerSettingID);
                        $db->update('mlm_banner_image_setting', $updateSettingData);
                    }else{
                        unset($insertData);
                        $insertData = array(
                            'upload_name' => $banner['image_name'],
                            'language_type' => $key,
                            'banner_id' => $params['id'],
                            'type' => $banner["type"],
                        );

                        $db->insert('mlm_banner_image_setting', $insertData);
                    } 
                    unset($bannerSettingID);

                    if($uploadData["defaultImage"] == 1){
                        $db->where('banner_id', $params['id']);
                        $db->where('name','defaultImageLanguage');
                        $getDefaultLang = $db->get('mlm_banner_setting', NULL, 'name, value, banner_id');

                        if($getDefaultLang){
                            unset($updateDefaultLang);
                            $updateDefaultLang = array(
                                'value' => $key,
                            );
                            $db->where('banner_id', $params['id']);
                            $db->where('name','defaultImageLanguage');
                            $db->update('mlm_banner_setting', $updateDefaultLang);
                        }
                        else{
                            unset($insertDefaultLang);
                            $defaultImageName = "defaultImageLanguage";
                            $insertDefaultLang = array(
                                'name' => $defaultImageName,
                                'value' => $key,
                                'banner_id' => $params['id'],
                            );

                            $db->insert('mlm_banner_setting', $insertDefaultLang);
                        }
                    }

                    $uploadDataNameAry[$key]['imgName'] = $upload_name;
                    $uploadDataNameAry[$key]['imgFileID'] = $uploadData['imgFileID'];
                    $uploadDataNameAry[$key]['imgFileName'] = $uploadData['imgFileName'];
                }
            }
            if(!empty($uploadDataNameAry)){
            $uploadReturnData['uploadData'] = $uploadDataNameAry;
            }

            if($availableLangAry){
                $db->where("banner_id",$params['id']);
                $db->where('type', "image");
                $db->where('language_type', $availableLangAry,"NOT IN");
                $copyDb = $db->copy();
                $preImageNameRes = $db->getValue('mlm_banner_image_setting','upload_name',null);
                foreach ($preImageNameRes as $preImageNameRow) {
                    $preImageNameAry[] = $preImageNameRow;
                }
            
                $uploadReturnData['preImgName'] = $preImageNameAry;

                $copyDb->delete("mlm_banner_image_setting");
            }

            $updateData = array (
                'subject' => $params['subject'],
                'description' => $params['description'],
                'status' => $params['status'],
                'type' => $params['type'],
                'page' => $params['page'],
                'start_date' => date("Y-m-d", $params['startDate']),
                'end_date' => date("Y-m-d", $params['endDate']),
                'updated_at' => $dateTime
            );

            $db->where('id', $params['id']);
            $db->update('mlm_banner', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>  $translations["A01590"][$language] /* Successfully Updated Banner. */, 'data' => $uploadReturnData);
        }

        public function removeBanner($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['id'])) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */, 'data' => "");
            }

            $updateData = array('status' => "Deleted", 'updated_at' => $db->now());
            $db->where('id', $params['id']);
            $db->update('mlm_banner', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A01591"][$language] /* Successfully Deleted Banner. */, 'data' => "");
        }

        public function getDashboardBanner() {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $userID = $db->userID;
            if(!$userID){
                $db->where("type", "public");
            }

            $currentDate = date("Y-m-d");
            $limit = array(0,4);
            $db->where("status", "Active");

            $sq1 = $db->subQuery();
            $sq1->where("start_date", $currentDate, "<=");
            $sq1->orWhere("start_date", date("00-00-00"));
            $sq1->getValue('mlm_banner', 'id', null);
            $db->where("id", $sq1, "IN");

            $sq2 = $db->subQuery();
            $sq2->where("end_date", $currentDate, ">=");
            $sq2->orWhere("end_date", date("00-00-00"));
            $sq2->getValue('mlm_banner', 'id', null);
            $db->where("id", $sq2, "IN");

            $db->orderBy("id", "DESC");

            $bannerData = $db->get("mlm_banner", $limit, "id, subject, description, type, page,created_at, start_date, end_date");
            if(empty($bannerData)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language] /* No Results Found. */, 'data' => "");
            }

            if($bannerData) {  
                foreach($bannerData as $key => $value){
                    $banner['subject'] = $value['subject'];
                    $banner['description'] = $value['description'];
                    $banner['type'] = $value['type'];
                    $banner['page'] = $value['page'];
                    $banner['created_at'] = $value['created_at'];
                    $banner['id'] = $value['id'];
                    $banner['start_date'] = $value['start_date'];
                    $banner['end_date'] = $value['end_date'];


                    $bannerID = $banner['id'];
                    $bannerType = $banner['type'];
                    $db->where('banner_id', $bannerID);
                    $db->where('language_type', $language);
                    $bannerImageData = $db->get('mlm_banner_image_setting', null, 'id, type, upload_name, language_type, upload_type');

                    if($bannerImageData[0]){
                        $defaultImageData['upload_type'] = $bannerImageData[0]["upload_type"];
                        $defaultImageData['language_type'] = $bannerImageData[0]["language_type"];
                        $defaultImageData['upload_name'] = $bannerImageData[0]["upload_name"];
                    }
                    else{
                        $db->where('banner_id', $bannerID);
                        $db->where('name',"defaultImageLanguage");
                        $bannerImageData = $db->get('mlm_banner_setting', null, 'id, name, value');
                        $defaultLanguage = $bannerImageData[0]["value"];

                        $db->where('banner_id', $bannerID);
                        $db->where('language_type', $defaultLanguage);
                        $bannerImageData = $db->get('mlm_banner_image_setting', null, 'id, type, upload_name, language_type, upload_type');

                        $defaultImageData['upload_type'] = $bannerImageData[0]["upload_type"];
                        $defaultImageData['language_type'] = $bannerImageData[0]["language_type"];
                        $defaultImageData['upload_name'] = $bannerImageData[0]["upload_name"];
                    }

                    $bannerData[$key]['file_type'] = $defaultImageData['upload_type'];
                    $bannerData[$key]['upload_name'] = $defaultImageData['upload_name'];                   
                }
            } 

            $data['bannerData'] = $bannerData ? $bannerData : "";

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getProductBanner($userID) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;

            $db->where("status", "Active");
            $db->orderBy("id", "DESC");
            $bannerData = $db->get("mlm_banner", null, "id, subject, description, created_at");

            if(empty($bannerData)) {
                return "";
            }

            if($bannerData) {  
                $db->where('id', $userID);
                $country_id = $db->getValue('client', 'country_id');

                $db->where('client_id', $userID);
                $leaderSponsorRow = $db->getValue("tree_sponsor", "trace_key");
                $leader_id_sponsor_ary = explode('/', $leaderSponsorRow);

                $db->where('client_id', $userID);
                $leaderPlacementRow = $db->getValue("tree_placement", "trace_key");
                $leaderPlacementRow = str_replace(array('-1<', '-1>', '-1'), '/', $leaderPlacementRow);
                $leader_id_placement_ary = explode('/', $leaderPlacementRow);

                if ($language == "chineseSimplified" || $language == "chineseTraditional" ){
                    $language = "chineseSimplified";
                }

                foreach($bannerData as $key => $value){
                    $banner['subject'] = $value['subject'];
                    $banner['description'] = $value['description'];
                    $banner['created_at'] = $value['created_at'];
                    $banner['id'] = $value['id'];

                    $bannerID = $banner['id'];
                    $db->where('banner_id', $bannerID);
                    $db->where('language_type', $language);
                    $bannerImageData = $db->get('mlm_banner_image_setting', null, 'id, type, upload_name, language_type, upload_type');

                    if($bannerImageData[0]){
                        $defaultImageData['upload_type'] = $bannerImageData[0]["upload_type"];
                        $defaultImageData['language_type'] = $bannerImageData[0]["language_type"];
                        $defaultImageData['upload_name'] = $bannerImageData[0]["upload_name"];
                    }
                    else if(!$bannerImageData[0]){
                        $db->where('banner_id', $bannerID);
                        $db->where('name',"defaultImageLanguage");
                        $bannerImageData = $db->get('mlm_banner_setting', null, 'id, name, value');
                        $defaultLanguage = $bannerImageData[0]["value"];

                        $db->where('banner_id', $bannerID);
                        $db->where('language_type', $defaultLanguage);
                        $bannerImageData = $db->get('mlm_banner_image_setting', null, 'id, type, upload_name, language_type, upload_type');

                        $defaultImageData['upload_type'] = $bannerImageData[0]["upload_type"];
                        $defaultImageData['defaultLanguage'] = $bannerID;
                        $defaultImageData['upload_name'] = $bannerImageData[0]["upload_name"];
                    }

                   $bannerData[$key]['file_type'] = $defaultImageData['upload_type'];
                   $bannerData[$key]['upload_name'] = $defaultImageData['upload_name'];

                    $db->where('banner_id', $bannerID);
                    $result = $db->get("mlm_banner_setting", NULL, "value, name");

                    if(!$result) continue;
                    foreach ($result as $key1 => $value) {
                        $bannerSetting[$value['name']] = explode(', ', $value['value']);
                    }

                    if($bannerSetting['treeType'][0] == 'sponsor'){
                        $leader_id_ary = $leader_id_sponsor_ary;
                    }else if($bannerSetting['treeType'][0] == 'placement'){
                        $leader_id_ary = $leader_id_placement_ary;
                    }

                    $granted = 0;
                    $leaderSetting = 0;
                    $excludeLeaderSetting = 0;
                    $countrySetting = 0;
                    $excludeCountrySetting = 0;
                    $startDateSetting = 0;
                    $endDateSetting = 0;

                    $currentDate = date("Ymd");
                    unset($leaderID);
                    foreach ($bannerSetting['leaderUsernameAry'] as $key2 => $leaderID) {
                        if($leaderID > 0 && in_array($leaderID, $leader_id_ary)){
                            $leaderSetting = 1;
                            break;
                        }
                    }

                    if(!$bannerSetting['leaderUsernameAry'] || $bannerSetting['leaderUsernameAry'][0] == ""){
                        $leaderSetting = 1;
                    }

                    unset($excludeLeaderID);
                    foreach ($bannerSetting['excludeLeaderUsernameAry'] as $key3 => $excludeLeaderID) {
                        if($excludeLeaderID > 0 && in_array($excludeLeaderID, $leader_id_ary)){
                            $excludeLeaderSetting = 1;
                            break;
                        }
                    }

                    unset($countryID);
                    foreach ($bannerSetting['countryIDAry'] as $key4 => $countryID) {                        
                        if(($countryID > 0 && $countryID == $country_id) || $countryID == 0){
                            $countrySetting = 1;
                            break;
                        }
                    }

                    unset($excludeCountryID);
                    foreach ($bannerSetting['excludeCountryIDAry'] as $key5 => $excludeCountryID) {                        
                        if($excludeCountryID > 0 && $excludeCountryID == $country_id){
                            $excludeCountrySetting = 1;
                            break;
                        }
                    }

                    foreach ($bannerSetting['startDate'] as $key6 => $startDate) {
                        $startDateFormat = explode("/", $startDate);
                        $startDateFormation = $startDateFormat[2]."".$startDateFormat[1]."".$startDateFormat[0];
                        $finalStartDate = date($startDateFormation);
                        
                        if($finalStartDate <= $currentDate){
                            $startDateSetting = 1;
                            break;
                        }
                    }

                    if(!$bannerSetting['startDate']){
                        $startDateSetting = 1;
                    }

                    foreach ($bannerSetting['endDate'] as $key7 => $endDate) {
                        $endDateFormat = explode("/", $endDate);
                        $endDateFormation = $endDateFormat[2]."".$endDateFormat[1]."".$endDateFormat[0];
                        $finalEndDate = date($endDateFormation);

                        if($finalEndDate == 0){
                            $endDateSetting = 1;
                            break; 
                        }
                        if($finalEndDate >= $currentDate){
                            $endDateSetting = 1;
                            break;
                        }
                    }

                    if(!$bannerSetting['endDate']){
                        $endDateSetting = 1;
                    }

                    if($countrySetting == 1 && $leaderSetting == 1 && $excludeLeaderSetting != 1 && $excludeCountrySetting != 1 && $startDateSetting == 1 && $endDateSetting == 1){
                        $granted = 1;
                    }                    

                    if($granted == 0){
                        unset($bannerData[$key]);
                    }
                } 
            } 

            return $bannerData ? $bannerData : "";
        }

    }
?>