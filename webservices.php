<?php
    
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        
        include_once('include/class.msgpack.php');
        
        include_once('include/config.php');
        include_once('include/class.database.php');
        include_once('include/class.cash.php');
        include_once('include/class.credit.php');
		include_once('include/class.webservice.php');
        include_once('include/class.upgrade.php');
        include_once('include/class.user.php');
        include_once('include/class.api.php');
        include_once('include/class.message.php');
        include_once('include/class.permission.php');
        include_once('include/class.setting.php');
        include_once('include/class.language.php');
        include_once('include/class.provider.php');
        include_once('include/class.journals.php');
        include_once('include/class.country.php');
        include_once('include/class.system.php');
        include_once('include/class.general.php');
        include_once('include/class.tree.php');
        include_once('include/class.activity.php');
        include_once('include/class.bonus.php');

        $db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
        $setting         = new Setting($db);
        $general         = new General($db, $setting);
        
   		//$client          = new client();
        $msgpack         = new msgpack();
        $credit          = new Credit($db, $general, $setting);
        $upgrade         = new Upgrade($db, $general);
        $user            = new User($db, $setting, $general);
        $api             = new Api($db, $general);
        $provider        = new Provider($db);
        $message         = new Message($db, $general, $provider);
        $webservice      = new Webservice($db, $general, $message);
        $permission      = new Permission($db, $general);
        
        // $cash            = new Cash($db, $setting, $message, $provider);
        $cash            = new Cash($db, $setting, $message, $provider, $log, $general, $client);
        $language        = new Language($db, $general, $setting);
        
        $journals        = new Journals($db, $general);
        $country         = new Country($db, $general);
        $system          = new System($db, $general, $setting);
        $bonus           = new Bonus($db, $general, $setting, $cash);
        $tree            = new Tree($db, $setting, $general);

        $activity        = new Activity($db, $general);

        $msgpackData = $msgpack->msgpack_unpack(file_get_contents('php://input'));
        $timeStart   = time();
        $tblDate     = date("Ymd");
        $createTime  = date("Y-m-d H:i:s");
        
        $command = $msgpackData['command'];
        $sessionID = $msgpackData['sessionID'];
        $userID = $msgpackData['userID'];
        $sessionTimeOut = $msgpackData['sessionTimeOut'];
        $site = $msgpackData['site'];
        $systemLanguage = trim($msgpackData['language'])? trim($msgpackData['language']) : "english"; // default to english

        // Set current language. Call $general->getCurrentLanguage() to retrieve the current language
        $general->setCurrentLanguage($systemLanguage);
        // Include the language file for mapping usage
        include_once('language/lang_all.php');
        // Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
        $general->setTranslations($translations);
        
        if($command != "getWebservices") {
            $webserviceID = $webservice->insertWebserviceData($msgpackData, $tblDate, $createTime);
        }
        
        
        if($command != "superAdminLogin") {
            
            if ($command == "testAPI") {
                // If it's test API, no need to validate session
                $userData = $user->getTestUserData($msgpackData['params']['userID'], $site);
                
                // Replace the command with the command that we are going to test
                $command = trim($msgpackData['params']['testCommand']);
                unset($msgpackData['params']['testCommand']);
                
                // Remove from params object, so that checkApiParams will not block it
                // Assign to another variable, just in case need to use it again
                $testApiUserID = trim($msgpackData['params']['userID']);
                unset($msgpackData['params']['userID']);
            }
            else {
                $userData = $user->checkSession($userID, $sessionID, $site);
                //print_r($userData);
            }
            
            if (!$userData || !$user->checkSessionTimeOut($sessionTimeOut, $site)) {
                // If sessionID is invalid, we return as session timeout
                $outputArray = array('status' => "error", 'code' => 3, 'statusMsg' => "Session expired.", 'data' => '');
                
                $webservice->updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());
                
                echo $msgpack->msgpack_pack($outputArray);
                exit;
            }
        }

        $db->userID      = $userID;
        $db->userType    = $site;

        $getApiResult = $api->getOneApi($command);
        // Temporary comment till all APIs are added into API table
        // if($getApiResult['code'] == 1) {
        //     $updateWebservice = $webservice->updateWebserviceData($webserviceID, $getApiResult, $getApiResult["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

        //     echo $msgpack->msgpack_pack($outputArray);
        //     exit;
        // }
        $apiSetting = $getApiResult['data'];
        
        $apiID = $apiSetting['id'];
        $apiDuplicate = $apiSetting['check_duplicate'];
        $duplicateInterval = $apiSetting['check_duplicate_interval'];
        $isSample = $apiSetting['sample'];
        
        // Check api parameters type
        if($checker['code'] == 1) {
            $updateWebservice = $webservice->updateWebserviceData($webserviceID, $checker, $checker["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            echo $msgpack->msgpack_pack($checker);
            exit;
        }

        // Check duplicate parameters of api
        if($apiDuplicate == 1) {
            $duplicate = $api->checkApiDuplicate($tblDate, $createTime, $userID, $sessionID, $site, $command, $duplicateInterval);
            
            if($duplicate['code'] == 1) {
                $updateWebservice = $webservice->updateWebserviceData($webserviceID, $duplicate, $duplicate["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

                echo $msgpack->msgpack_pack($duplicate);
                exit;
            }
        }
        
        // Check whether to use sample output for this api
        if($isSample == 1) {
            $outputArray = $api->getSampleOutput($apiID);
            
            $webservice->updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());
            
            echo $msgpack->msgpack_pack($outputArray);
            exit;
        }
        
        // Set creator id and type
        $cash->setCreator($userID, $site);
        $activity->setCreator($userID, $site);
        
        $db->queryNumber = 0;
        switch($command) {
            case "superAdminLogin":
                $outputArray = $user->superAdminLogin($msgpackData['params']);
                break;
                
            case "apiList":
                $outputArray = $api->apiList($msgpackData['params']);
                break;
                
            case "newApi" :
                $outputArray = $api->newApi($msgpackData['params'], $userID);
                break;
                
            case "deleteApi" :
                $outputArray = $api->deleteApi($msgpackData['params']);
                break;
                
            case "editApi" :
                $outputArray = $api->editApi($msgpackData['params'], $userID);
                break;

            case "getEditApiData" :
                $outputArray = $api->getEditApiData($msgpackData['params']);
                break;

            case "getApiSampleData" :
                $outputArray = $api->getApiSampleData($msgpackData['params']);
                break;

            case "editApiSampleData" :
                $outputArray = $api->editApiSampleData($msgpackData['params']);
                break;

            case "getNewUpgrades":
                $outputArray = $upgrade->getNewUpgrades();
                break;
                
            case "getUpgradesHistory":
                $outputArray = $upgrade->getUpgradesHistory($msgpackData['params']);
                break;
                
            case "updateAllUpgrades":
                $outputArray = $upgrade->updateAllUpgrades($msgpackData['params']);
                break;
                
            case "getUsers":
                $outputArray = $user->getUsers($msgpackData['params']);
                break;
                
            case "addUser":
                $outputArray = $user->addUser($msgpackData['params']);
                break;
                
            case "editUser":
                $outputArray = $user->editUser($msgpackData['params']);
                break;
                
            case "getUserDetails":
                $outputArray = $user->getUserDetails($msgpackData['params']);
                break;
                
            case "deleteUser":
                $outputArray = $user->deleteUser($msgpackData['params']);
                break;
                
            case "getRoles":
                $outputArray = $user->getRoles($msgpackData['params']);
                break;
                
            case "addRole":
                $outputArray = $user->addRole($msgpackData['params']);
                break;
                
            case "editRole":
                $outputArray = $user->editRole($msgpackData['params']);
                break;
                
            case "getRoleDetails":
                $outputArray = $user->getRoleDetails($msgpackData['params']);
                break;
                
            case "deleteRole":
                $outputArray = $user->deleteRole($msgpackData['params']);
                break;
                
            case "messageAssignedList":
                $outputArray = $message->messageAssignedList($msgpackData['params']);
                break;
                
            case "deleteMessageAssigned" :
                $outputArray = $message->deleteMessageAssigned($msgpackData['params']);
                break;
                
            case "getMessageCode":
                $outputArray = $message->getMessageCode();
                break;
                
            case "newMessageAssigned":
                $outputArray = $message->newMessageAssigned($msgpackData['params']);
                break;
                
            case "editMessageAssigned":
                $outputArray = $message->editMessageAssigned($msgpackData['params']);
                break;
                
            case "getEditMessageAssignedData":
                $outputArray = $message->getEditMessageAssignedData($msgpackData['params']);
                break;

            case "sendMessage":
                $outputArray = $message->sendMessage($msgpackData['params']);
                break;

            case "newApiParam":
                $outputArray = $api->newApiParam($msgpackData['params'], $userID);
                break;

            case "getApiParameterData":
                $outputArray = $api->getApiParameterData($msgpackData['params']);
                break;
                
            case "getApiName":
                $outputArray = $api->getApiName();
                break;
                
            case "getEditParamData":
                $outputArray = $api->getEditParamData($msgpackData['params']);
                break;
                
            case "editParam" :
                $outputArray = $api->editParam($msgpackData['params']);
                break;
                
            case "deleteApiParam" :
                $outputArray = $api->deleteApiParam($msgpackData['params']);
                break;
                
            case "getApiSearchData":
                $outputArray = $api->getApiSearchData($msgpackData['params']);
                break;
                
            case "getAPIParams":
                $outputArray = $api->getAPIParams($msgpackData['params']);
                break;
                
            case "getMessageSearchData":
                $outputArray = $message->getMessageSearchData($msgpackData['params']);
                break;
                
            case "searchParamHistory":
                $outputArray = $api->searchParamHistory($msgpackData['params']);
                break;

            case "getPermissions":
                $outputArray = $permission->getPermissions($msgpackData['params']);
                break;
                
            case "getPermissionsList":
                $outputArray = $permission->getPermissionsList($msgpackData['params']);
                break;
                
            case "deletePermissions":
                $outputArray = $permission->deletePermissions($msgpackData['params']);
                break;
                
            case "newPermission":
                $outputArray = $permission->newPermission($msgpackData['params']);
                break;
                
            case "getPermissionData":
                $outputArray = $permission->getPermissionData($msgpackData['params']);
                break;
                
            case "editPermissionData":
                $outputArray = $permission->editPermissionData($msgpackData['params']);
                break;
                
            case "getPermissionTree":
                $outputArray = $permission->getPermissionTree($msgpackData['params']);
                break;
                
            case "getWebservices":
                $outputArray = $webservice->getWebservices($msgpackData['params'], $site);
                break;
                
                // Using the $user->getRoles();
                // case "getRolePermissionList":
                //     $outputArray = $permission->getRolePermissionList($msgpackData['params']);
                //     break;
                
            case "newRolePermission":
                $outputArray = $permission->newRolePermission($msgpackData['params']);
                break;
                
            case "editRolePermission":
                $outputArray = $permission->editRolePermission($msgpackData['params']);
                break;
                
            case "getPermissionNames":
                $outputArray = $permission->getPermissionNames();
                break;
                
            case "getRoleNames":
                $outputArray = $permission->getRoleNames();
                break;
                
            case "deleteRolePermission":
                $outputArray = $permission->deleteRolePermission($msgpackData['params']);
                break;
                
            case "getRolePermissionData":
                $outputArray = $permission->getRolePermissionData($msgpackData['params']);
                break;
                
            case "newSetting":
                $outputArray = $setting->newSetting($msgpackData['params']);
                break;
                
            case "getSettingsList":
                $outputArray = $setting->getSettingsList($msgpackData['params']);
                break;
                
            case "deleteSettings":
                $outputArray = $setting->deleteSettings($msgpackData['params']);
                break;
                
            case "getSettingData":
                $outputArray = $setting->getSettingData($msgpackData['params']);
                break;
                
            case "editSettingData":
                $outputArray = $setting->editSettingData($msgpackData['params']);
                break;
                
            case "getMessageSentList":
                $outputArray = $message->getMessageSentList($msgpackData['params']);
                break;

            case "getMessageInList":
                $outputArray = $message->getMessageInList($msgpackData['params']);
                break;

            case "getMessageErrorList":
                $outputArray = $message->getMessageErrorList($msgpackData['params']);
                break;

            case "getErrorCode":
                $outputArray = $message->getErrorCode();
                break;

            //Languages getLanguageData
            case "editLanguageData":
                $outputArray = $language->editLanguageData($msgpackData['params']);
                break;
                
            case "getLanguageData":
                $outputArray = $language->getLanguageData($msgpackData['params']);
                break;
                
            case "getLanguageList":
                $outputArray = $language->getLanguageList($msgpackData['params']);
                break;
                
            case "deleteLanguage":
                $outputArray = $language->deleteLanguage($msgpackData['params']);
                break;
                
            case "newLanguage":
                $outputArray = $language->newLanguage($msgpackData['params']);
                break;
                
                //Languages Code
            case "editLanguageCodeData":
                $outputArray = $language->editLanguageCodeData($msgpackData['params']);
                break;
                
            case "getLanguageCodeData":
                $outputArray = $language->getLanguageCodeData($msgpackData['params']);
                break;
                
            case "getLanguageCodeList":
                $outputArray = $language->getLanguageCodeList($msgpackData['params']);
                break;
                
            case "deleteLanguageCode":
                $outputArray = $language->deleteLanguageCode($msgpackData['params']);
                break;
                
            case "newLanguageCode":
                $outputArray = $language->newLanguageCode($msgpackData['params']);
                break;
                
            case "getLanguageRows":
                $outputArray = $language->getLanguageRows($msgpackData['params']);
                break;

            case "uploadFile":
                $outputArray = $language->uploadFile($msgpackData['params'],$userID,$site);
                break;

            case "exportLanguageCodes":
                $outputArray = $language->exportLanguageCodes($msgpackData['params']);
                break;

            //Message Codes
            case "getMessageCodes":
                $outputArray = $message->getMessageCodes($msgpackData['params']);
                break;
                
            case "saveMessageCodeData" :
                $outputArray = $message->saveMessageCodeData($msgpackData['params']);
                break;
                
            case "deleteMessageCode" :
                $outputArray = $message->deleteMessageCode($msgpackData['params']);
                break;
                
            case "editMessageCode" :
                $outputArray = $message->editMessageCode($msgpackData['params']);
                break;
                
            case "getEditMessageCodeData" :
                $outputArray = $message->getEditMessageCodeData($msgpackData['params']);
                break;
                
            case "searchMessageCode" :
                $outputArray = $message->searchMessageCode($msgpackData['params']);
                break;
                
            case "newProvider" :
                $outputArray = $provider->newProvider($msgpackData['params']);
                break;

            case "getProviderData":
                $outputArray = $provider->getProviderData($msgpackData['params']);
                break;

            case "deleteProvider" :
                $outputArray = $provider->deleteProvider($msgpackData['params']);
                break;

            case "getEditProviderData":
                $outputArray = $provider->getEditProviderData($msgpackData['params']);
                break;

            case "editProvider" :
                $outputArray = $provider->editProvider($msgpackData['params']);
                break;

            case "getMessageType" :
                $outputArray = $provider->getMessageType($msgpackData['params']);
                break;

            case "getInternalAccountsList" :
                $outputArray = $user->getInternalAccountsList($msgpackData['params']);
                break;

            case "newInternalAccount":
                $outputArray = $user->newInternalAccount($msgpackData['params']);
                break;
                
            case "deleteInternalAccount":
                $outputArray = $user->deleteInternalAccount($msgpackData['params']);
                break;
                
            case "getInternalAccountData":
                $outputArray = $user->getInternalAccountData($msgpackData['params']);
                break;
                
            case "editInternalAccountData":
                $outputArray = $user->editInternalAccountData($msgpackData['params']);
                break;
                
            case "newJournalTable":
                $outputArray = $journals->newJournalTable($msgpackData['params']);
                break;
                
            case "getJournalTablesList":
                $outputArray = $journals->getJournalTablesList($msgpackData['params']);
                break;
                
            case "deleteJournalTables":
                $outputArray = $journals->deleteJournalTables($msgpackData['params']);
                break;
                
            case "getJournalTableData":
                $outputArray = $journals->getJournalTableData($msgpackData['params']);
                break;
                
            case "editJournalTableData":
                $outputArray = $journals->editJournalTableData($msgpackData['params']);
                break;
                
            case "getJournalTableNames":
                $msgpackData['params']['dbName'] = $config['dB'];
                $outputArray = $journals->getJournalTableNames($msgpackData['params']);
                break;
                
            case "getCountriesList" :
                $outputArray = $country->getCountriesList($msgpackData['params']);
                break;
                
            case "newCountry":
                $outputArray = $country->newCountry($msgpackData['params']);
                break;
                
            case "deleteCountry":
                $outputArray = $country->deleteCountry($msgpackData['params']);
                break;
                
            case "getCountryData":
                $outputArray = $country->getCountryData($msgpackData['params']);
                break;
                
            case "editCountryData":
                $outputArray = $country->editCountryData($msgpackData['params']);
                break;
                
            case "getCredits":
                $outputArray = $credit->getCredits($msgpackData['params']);
                break;
                
            case "addCredit":
                $outputArray = $credit->addCredit($msgpackData['params']);
                break;
                
            case "editCredit":
                $outputArray = $credit->editCredit($msgpackData['params']);
                break;
                
            case "getCreditDetails":
                $outputArray = $credit->getCreditDetails($msgpackData['params']);
                break;
                
            case "deleteCredit":
                $outputArray = $credit->deleteCredit($msgpackData['params']);
                break;
            
            case "getCreditSettingDetails":
                $outputArray = $credit->getCreditSettingDetails($msgpackData['params']);
                break;
                
            case "editCreditSetting":
                $outputArray = $credit->editCreditSetting($msgpackData['params']);
                break;
                
            case "getClients":
                $outputArray = $user->getClients($msgpackData['params']);
                break;
                
            case "getClientSettings":
                $outputArray = $user->getClientSettings($msgpackData['params']);
                break;
                
            case "getClientDetails":
                $outputArray = $user->getClientDetails($msgpackData['params']);
                break;

            case "getMessageQueueList":
                $outputArray = $message->getMessageQueueList($msgpackData['params']);
                break;

            case "getSystemList":
                $outputArray = $system->getSystemList($msgpackData['params']);
                break;

            case "getSystemData":
                $outputArray = $system->getSystemData($msgpackData['params']);
                break;
                
            case "getAdmins":    
                $outputArray = $user->getAdmins($msgpackData['params']);
                break;

            case "getAdminDetails":    
                $outputArray = $user->getAdminDetails($msgpackData['params']);
                break;

            case "addAdmin":   
                $outputArray = $user->addAdmin($msgpackData['params']);
                break;

            case "editAdmin":    
                $outputArray = $user->editAdmin($msgpackData['params']);
                break;

            case "deleteAdmin":    
                $outputArray = $user->deleteAdmin($msgpackData['params']);
                break;

            case "changeSponsor":    
                $outputArray = $tree->changeSponsor($msgpackData['params']);
                break;

            case "changePlacement":    
                $outputArray = $tree->changePlacement($msgpackData['params']);
                break;

            case "getSponsorTree":    
                $outputArray = $tree->getSponsorTree($msgpackData['params']);
                break;

            case "getPlacementTree":    
                $outputArray = $tree->getPlacementTree($msgpackData['params']);
                break;

            case "getActivity":
                $outputArray = $activity->getActivity($msgpackData['params']);
                break;

            case "getModuleSettingList":
                $outputArray = $setting->getModuleSettingList($msgpackData['params']);
                break;

            case "getModuleSettingData":
                $outputArray = $setting->getModuleSettingData($msgpackData['params']);
                break;

            case "editModuleSettingData":
                $outputArray = $setting->editModuleSettingData($msgpackData['params']);
                break;

            case "deleteModuleSetting":
                $outputArray = $setting->deleteModuleSetting($msgpackData['params']);
                break;

            case "addModuleSetting":
                $outputArray = $setting->addModuleSetting($msgpackData['params']);
                break;

            case "getBonusSettingAll":
                $outputArray = $bonus->getBonusSettingAll();
                break;

            case "getBonusSettingDetails":
                $outputArray = $bonus->getBonusSettingDetails($msgpackData['params']);
                break;

            case "editBonusSetting":
                $outputArray = $bonus->editBonusSetting($msgpackData['params']);
                break;

            case "addBonusSetting":
                $outputArray = $bonus->addBonusSetting($msgpackData['params']);
                break;

            case "getActiveLanguages":
                $outputArray = $setting->getActiveLanguages();
                break;

            case "getLanguageUploadFileList":
                $outputArray = $language->getLanguageUploadFileList();
                break;
                
            default:
                $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Command not found.", 'data' => '');
                $find = array("%%apiName%%");
                $replace = array($command);
                $message->createMessageOut('10003', NULL, NULL, $find, $replace); //Send notification if Invalid Command.
                break;
        }
        /***** For sending the Notifications. *****/
        $queries = $db->getQueryNumber(); // Need to add the Executed queries count.
        //For sending the Notification - API executes the no of queries.
        if($queries > $apiSetting['no_of_queries']) {
            $find = array("%%apiName%%", "%%apiAllowed%%", "%%apiCurrent%%");
            $replace = array($command, $apiSetting['no_of_queries'], $queries);
            $message->createMessageOut('10002', NULL, NULL, $find, $replace);
        }
        /***** For sending the Notification. *****/
        
        $completedTime = date("Y-m-d H:i:s");
        $processedTime = time() - $timeStart;

        $dataOut = $outputArray;
        $status = $dataOut['status'];

        //For sending the Notification - API takes longer time.
        if($processedTime > $apiSetting['duration']){
            $find = array("%%apiName%%", "%%apiTime%%", "%%seconds%%");
            $replace = array($command, $apiSetting['duration'], $processedTime);
            $message->createMessageOut('10001', NULL, NULL, $find, $replace);
        }

        if($command != "getWebservices") {
            $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, $queries);
        }

        echo $msgpack->msgpack_pack($outputArray);
    }
?>
