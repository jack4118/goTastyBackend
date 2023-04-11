<?php  
    echo "Start\n";
    $currentPath = __DIR__;

    ini_set("memory_limit","-1");

    include_once($currentPath."/../include/classlib.php");
    include_once($currentPath."/../include/class.language.php");
    include_once($currentPath."/../include/class.scriptFunction.php");

    General::$currentLanguage = 'english';
    General::$translations    = $translations;

    $db = MysqliDb::getInstance();
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
            $tariffCode = $sheet->getCell("G".$row)->getValue();

            if(($countryName != "" && $stateName != "" && $cityName != "" && $districtName != "" && $subDistrictName != "" && $zipCode != "")){
                $zipCodeAry[$zipCode][$subDistrictName][$districtName][$cityName][$stateName] = array("subDistrictName" => $subDistrictName, "districtName" => $districtName, "cityName" => $cityName, "stateName" => $stateName, "tariffCodeName" => $tariffCode);
            }else{
                print_r("[row: $row] countryName: $countryName, stateName: $stateName, cityName: $cityName, districtName: $districtName, subDistrictName: $subDistrictName, zipCode: $zipCode\n\n");
            }
            // $tariffCodeAry[$tariffCode] = $tariffCode;
        }
    }

    // $tariffCodeRes = $db->map('tariff_code')->get('zip_code', null, 'tariff_code');
    // $diff = array_diff($tariffCodeAry, $tariffCodeRes);
    // print_r($diff);

    $x = 0;
    foreach($zipCodeAry as $zipCode => $zipCodeRow){
        foreach($zipCodeRow as $zipCodeRow2){
            foreach($zipCodeRow2 as $zipCodeRow3){
                foreach($zipCodeRow3 as $zipCodeRow4){
                    foreach($zipCodeRow4 as $zipCodeRow5){
                        $stateName = $zipCodeRow5["stateName"];
                        $cityName = $zipCodeRow5["cityName"];
                        $districtName = $zipCodeRow5["districtName"];
                        $subDistrictName = $zipCodeRow5["subDistrictName"];
                        $tariffCodeName = $zipCodeRow5["tariffCodeName"];

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

                        if(!$subCountyID || !$countyID || !$cityID || !$stateID || !$countryID || !$stateName || !$cityName || !$districtName || !$subDistrictName || !$zipCode || !$tariffCodeName){
                            print_r("subCountyID: $subCountyID, countyID: $countyID, cityID: $cityID, stateID: $stateID, countryID: $countryID, stateName: $stateName, cityName: $cityName, districtName: $districtName, subDistrictName: $subDistrictName, zipCode: $zipCode\n\n");
                            continue;
                        }

                        print_r("subDistrictName: $subDistrictName zipCode: $zipCode tariffCode: $tariffCodeName\n\n");

                        unset($insertData);
                        $insertData = array(
                            "tariff_code" => $tariffCodeName,
                        );
                        $db->where("country_id",$countryID);
                        $db->where("sub_county_id",$subCountyID);
                        $insertRes = $db->update("zip_code",$insertData);

                        if($insertRes){
                            $successZipCode++;
                        }else{ 
                            $failedZipCode++;
                        }

                        $total = $x++;

                        unset($insertRes);
                    }
                }
            }
        }
    }

    echo "total: ".$total."\n";
    echo "fail: ".$failedZipCode."\n";

    echo "\nEnd\n"; 
?>