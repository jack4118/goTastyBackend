<?php
    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../include/class.lang_all.php');

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    echo "Start patch\n";

    $countryParams = array("pagination" => "No");
    $resultCountryList = Country::getCountriesList($countryParams);
    if (!$resultCountryList) {
        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00281"][$language] /* No result found */, 'data' => "");
    }
    $countryList    = $resultCountryList['data']['countriesList'];
    $cityList       = Country::getCity();
    $countyList     = Country::getCounty();
    $subCountyList  = Country::getSubCounty();
    $postalCodeList = Country::getPostalCode();
    $resultStateList = Country::getState();

    $data['countriesList']     = $countryList;
    $data['stateList']         = $resultStateList;
    $data['cityList']          = $cityList;
    $data['countyList']        = $countyList;
    $data['subCountyList']     = $subCountyList;
    $data['postalCodeList']        = $postalCodeList; 

    $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $path = __DIR__."/../json/addressList.json";

    file_put_contents($path, $content);

    $cmd = "scp ".$path." root@".$config['frontendServerIP'].":".$config['frontendPath']."/member/json/";
    $result = exec($cmd);

    $cmd2 = "scp ".$path." root@".$config['frontendServerIP'].":".$config['frontendPath']."/admin/json/";
    $result2 = exec($cmd2);

    echo "Done patch\n";
?>