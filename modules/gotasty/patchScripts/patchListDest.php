<?php  
    echo "Start\n";
    $currentPath = __DIR__;

    ini_set("memory_limit","-1");

    include_once($currentPath."/../include/classlib.php");
    include_once($currentPath."/../include/class.language.php");
    include_once($currentPath."/../include/class.scriptFunction.php");

    General::$currentLanguage = 'english';
    General::$translations    = $translations;

    $language = new Language();
    $function = new scriptFunction();

    $dateTime = date("Y-m-d H:i:s");

    $excelFile = "listDest.xlsx";

    try{
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);
    }catch(Exception $e){
        echo "\nPatch State List Process Failed... \n\n";
        echo "Filename\t: " . pathinfo($excelFile, PATHINFO_BASENAME) . "\n";
        echo "Error message\t: " . $e->getMessage() . "\n\n";
        exit();
    }

    $sheet         = $objPHPExcel->getSheet(0); 
    $highestRow    = $sheet->getHighestRow(); 
    $highestColumn = $sheet->getHighestColumn();

    unset($stateNameAry,$cityNameAry,$districtNameAry,$subDistrictNameAry,$zipCodeAry);
    for($row = 1; $row <= $highestRow; $row++){ 
        if($row > 1) {
            $countryName = $sheet->getCell("A".$row)->getValue();
            $stateName = $sheet->getCell("B".$row)->getValue();
            $cityName = $sheet->getCell("C".$row)->getValue();
            $districtName = $sheet->getCell("D".$row)->getValue();
            $subDistrictName = $sheet->getCell("E".$row)->getValue();
            $zipCode = $sheet->getCell("F".$row)->getValue();

            if(($countryName != "" && $stateName != "" && $cityName != "" && $districtName != "" && $subDistrictName != "" && $zipCode != "")){
                $stateNameAry[$stateName][$countryName] = $countryName;
                $cityNameAry[$cityName][$stateName] = $stateName;
                $districtNameAry[$districtName][$cityName][$stateName] = array("cityName" => $cityName, "stateName" => $stateName);
                $subDistrictNameAry[$subDistrictName][$districtName][$cityName][$stateName] = array("districtName" => $districtName, "cityName" => $cityName, "stateName" => $stateName);
                $zipCodeAry[$zipCode][$subDistrictName][$districtName][$cityName][$stateName] = array("subDistrictName" => $subDistrictName, "districtName" => $districtName, "cityName" => $cityName, "stateName" => $stateName);
            }else{
                print_r("[row: $row] countryName: $countryName, stateName: $stateName, cityName: $cityName, districtName: $districtName, subDistrictName: $subDistrictName, zipCode: $zipCode\n\n");
            }
        }
    }

    if($stateNameAry && $cityNameAry && $districtNameAry && $subDistrictNameAry && $zipCodeAry){
        echo "\nSuccessfully Retrieved Data from $excelFile\n";
    }else{
        echo "\nData Not Found, aborting data patching \n";
    }

    print_r($stateNameAry);
    print_r($cityNameAry);
    print_r($districtNameAry);
    print_r($subDistrictNameAry);
    print_r($zipCodeAry);

    sleep(1);
    echo "\nAll set, start state patching\n";

    $db->where("disabled",0);
    $langAry = $db->getValue("languages","language",null);

    unset($countryName,$stateName,$cityName,$districtName,$subDistrictName,$zipCode);

    $successState = 0; 
    $failedState = 0;

    foreach($stateNameAry as $stateName => $countryNameRow){
        foreach($countryNameRow as $countryName){
            $db->where("name",$countryName);
            $countryID = $db->getValue("country","id");

            if(!$countryID || !$countryName || !$stateName){
                print_r("countryID: $countryID, countryName: $countryName, stateName: $stateName\n\n");
                continue;
            }

            print_r("stateName: $stateName countryName: $countryName\n\n");

            unset($insertData);
            $insertData = array(
                "country_id" => $countryID,
                "name" => $stateName,
                "translation_code" => $translationCode,
                "disabled" => "0",
                "created_at" => $dateTime,
            );
            $insertRes = $db->insert("state",$insertData);

            if($insertRes) $successState++;
            else $failedState++;

            /*foreach($langAry as $lang){
                unset($insertDisplay);
                $insertDisplay = array(
                    "code" => $translationCode,
                    "module" => "State Display",
                    "language" => $lang,
                    "site" => "State",
                    "type" => "Dynamic",
                    "content" => $stateName,
                    "created_at" => $dateTime,
                );
                $db->insert("language_translation",$insertDisplay);
            }
            unset($translationCode);*/
            unset($insertRes);
        }
    }

    $successCity = 0; 
    $failedCity = 0;

    foreach($cityNameAry as $cityName => $stateNameRow){
        foreach($stateNameRow as $stateName){
            $db->where("name",$stateName);
            $stateRes = $db->getOne("state","id,country_id");
            $stateID = $stateRes["id"];
            $countryID = $stateRes["country_id"];

            if(!$stateID || !$countryID || !$stateName || !$cityName){
                print_r("stateID: $stateID, countryID: $countryID, stateName: $stateName, cityName: $cityName\n\n");
                continue;
            }

            print_r("cityName: $cityName stateName: $stateName\n\n");

            unset($insertData);
            $insertData = array(
                "state_id" => $stateID,
                "country_id" => $countryID,
                "name" => $cityName,
                "translation_code" => $translationCode,
                "disabled" => "0",
                "created_at" => $dateTime,
            );
            $insertRes = $db->insert("city",$insertData);

            if($insertRes) $successCity++;
            else $failedCity++;

            /*foreach($langAry as $lang){
                unset($insertDisplay);
                $insertDisplay = array(
                    "code" => $translationCode,
                    "module" => "City Display",
                    "language" => $lang,
                    "site" => "State",
                    "type" => "Dynamic",
                    "content" => $cityName,
                    "created_at" => $dateTime,
                );
                $db->insert("language_translation",$insertDisplay);
            }
            unset($translationCode);*/
            unset($insertRes);
        }
    }

    $successDistrict = 0; 
    $failedDistrict = 0;

    foreach($districtNameAry as $districtName => $cityNameRow){
        foreach($cityNameRow as $districtNameRow){
            foreach($districtNameRow as $districtNameRow2){
                $stateName = $districtNameRow2["stateName"];
                $cityName = $districtNameRow2["cityName"];

                $db->where("name",$stateName);
                $stateID = $db->getValue("state","id");

                $db->where("state_id",$stateID);
                $db->where("name",$cityName);
                $cityRes = $db->getOne("city","id,country_id");
                $cityID = $cityRes["id"];
                $countryID = $cityRes["country_id"];

                if(!$cityID || !$stateID || !$countryID || !$stateName || !$cityName || !$districtName){
                    print_r("cityID: $cityID, stateID: $stateID, countryID: $countryID, stateName: $stateName, cityName: $cityName, districtName: $districtName\n\n");
                    continue;
                }

                print_r("stateName: $stateName, cityName: $cityName districtName: $districtName\n\n");

                unset($insertData);
                $insertData = array(
                    "city_id" => $cityID,
                    "country_id" => $countryID,
                    "name" => $districtName,
                    "translation_code" => $translationCode,
                    "disabled" => "0",
                    "created_at" => $dateTime,
                );
                $insertRes = $db->insert("county",$insertData);

                if($insertRes) $successDistrict++;
                else $failedDistrict++;

                /*foreach($langAry as $lang){
                    unset($insertDisplay);
                    $insertDisplay = array(
                        "code" => $translationCode,
                        "module" => "County Display",
                        "language" => $lang,
                        "site" => "State",
                        "type" => "Dynamic",
                        "content" => $districtName,
                        "created_at" => $dateTime,
                    );
                    $db->insert("language_translation",$insertDisplay);
                }
                unset($translationCode);*/
                unset($insertRes);
            }   
        }
    }

    $successSubDistrict = 0; 
    $failedSubDistrict = 0;

    foreach($subDistrictNameAry as $subDistrictName => $districtNameRow){
        foreach($districtNameRow as $cityNameRow){
            foreach($cityNameRow as $stateNameRow){
                foreach($stateNameRow as $stateNameRow2){
                    $stateName = $stateNameRow2["stateName"];
                    $cityName = $stateNameRow2["cityName"];
                    $districtName = $stateNameRow2["districtName"];

                    $db->where("name",$stateName);
                    $stateID = $db->getValue("state","id");

                    $db->where("state_id",$stateID);
                    $db->where("name",$cityName);
                    $cityID = $db->getValue("city","id");

                    $db->where("city_id",$cityID);
                    $db->where("name",$districtName);
                    $countyRes = $db->getOne("county","id,country_id");
                    $countyID = $countyRes["id"];
                    $countryID = $countyRes["country_id"];

                    if(!$countyID || !$cityID || !$stateID || !$countryID || !$stateName || !$cityName || !$districtName || !$subDistrictName){
                        print_r("countyID: $countyID, cityID: $cityID, stateID: $stateID, countryID: $countryID, stateName: $stateName, cityName: $cityName, districtName: $districtName, subDistrictName: $subDistrictName\n\n");
                        continue;
                    }

                    print_r("districtName: $districtName subDistrictName: $subDistrictName\n\n");

                    unset($insertData);
                    $insertData = array(
                        "county_id" => $countyID,
                        "country_id" => $countryID,
                        "name" => $subDistrictName,
                        "translation_code" => $translationCode,
                        "disabled" => "0",
                        "created_at" => $dateTime,
                    );
                    $insertRes = $db->insert("sub_county",$insertData);

                    if($insertRes) $successSubDistrict++;
                    else $failedSubDistrict++;

                    /*foreach($langAry as $lang){
                        unset($insertDisplay);
                        $insertDisplay = array(
                            "code" => $translationCode,
                            "module" => "Sub County Display",
                            "language" => $lang,
                            "site" => "State",
                            "type" => "Dynamic",
                            "content" => $subDistrictName,
                            "created_at" => $dateTime,
                        );
                        $db->insert("language_translation",$insertDisplay);
                    }
                    unset($translationCode);*/
                    unset($insertRes);
                }
            }
        }
    }

    $successZipCode = 0; 
    $failedZipCode = 0;

    foreach($zipCodeAry as $zipCode => $zipCodeRow){
        foreach($zipCodeRow as $zipCodeRow2){
            foreach($zipCodeRow2 as $zipCodeRow3){
                foreach($zipCodeRow3 as $zipCodeRow4){
                    foreach($zipCodeRow4 as $zipCodeRow5){
                        $stateName = $zipCodeRow5["stateName"];
                        $cityName = $zipCodeRow5["cityName"];
                        $districtName = $zipCodeRow5["districtName"];
                        $subDistrictName = $zipCodeRow5["subDistrictName"];

                        $db->where("name",$stateName);
                        $stateID = $db->getValue("state","id");

                        $db->where("state_id",$stateID);
                        $db->where("name",$cityName);
                        $cityID = $db->getValue("city","id");

                        $db->where("city_id",$cityID);
                        $db->where("name",$districtName);
                        $countyID = $db->getValue("county","id");

                        $db->where("county_id",$countyID);
                        $db->where("name",$subDistrictName);
                        $subCountyRes = $db->getOne("sub_county","id,country_id");
                        $subCountyID = $subCountyRes["id"];
                        $countryID = $subCountyRes["country_id"];

                        if(!$subCountyID || !$countyID || !$cityID || !$stateID || !$countryID || !$stateName || !$cityName || !$districtName || !$subDistrictName || !$zipCode){
                            print_r("subCountyID: $subCountyID, countyID: $countyID, cityID: $cityID, stateID: $stateID, countryID: $countryID, stateName: $stateName, cityName: $cityName, districtName: $districtName, subDistrictName: $subDistrictName, zipCode: $zipCode\n\n");
                            continue;
                        }

                        print_r("subDistrictName: $subDistrictName zipCode: $zipCode\n\n");

                        unset($insertData);
                        $insertData = array(
                            "sub_county_id" => $subCountyID,
                            "country_id" => $countryID,
                            "name" => $zipCode,
                            "disabled" => "0",
                            "created_at" => $dateTime,
                        );
                        $insertRes = $db->insert("zip_code",$insertData);

                        if($insertRes) $successZipCode++;
                        else $failedZipCode++;

                        unset($insertRes);
                    }
                }
            }
        }
    }

    echo "\n\n\n";
    echo $successState          . " rows successState\n";
    echo $failedState           . " rows failedState\n";
    echo $successCity           . " rows successCity\n";
    echo $failedCity            . " rows failedCity\n";
    echo $successDistrict       . " rows successDistrict\n";
    echo $failedDistrict        . " rows failedDistrict\n";
    echo $successSubDistrict    . " rows successSubDistrict\n";
    echo $failedSubDistrict     . " rows failedSubDistrict\n";
    echo $successZipCode        . " rows successZipCode\n";
    echo $failedZipCode         . " rows failedZipCode\n";

    echo "\nEnd\n"; 
?>