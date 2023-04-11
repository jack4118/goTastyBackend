<?php
    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../include/class.language.php');
    include_once($currentPath.'/../include/class.scriptFunction.php');

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    $language = new Language();
    $function = new scriptFunction();

    $excelFile = "ONDELIVERY.xlsx";
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);
    } catch(Exception $e) {
        echo "\nPatch Dest ID Process Failed... \n\n";
        echo "Filename\t: " . pathinfo($excelFile, PATHINFO_BASENAME) . "\n";
        echo "Error message\t: " . $e->getMessage() . "\n\n";
        exit();
    }

    // loop through the excel sheet
    foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
        unset($highestRow);
        unset($highestColumn);
        unset($sheetName);
        unset($dataArray);
        unset($dataArrayList);
        $highestRow    = $worksheet->getHighestRow(); // max row number
        $highestColumn = $worksheet->getHighestColumn(); // max column alphabet

        // loop through excel to get row of data
        for ($row = 3; $row <= $highestRow; $row++){ 
            if($row > 3) {
                $destID = $worksheet->getCell('A'.$row)->getValue();
                $stateName = $worksheet->getCell("B".$row)->getValue();
                $cityName = $worksheet->getCell("C".$row)->getValue();
                $districtName = $worksheet->getCell("D".$row)->getValue();
                $subDistrictName = $worksheet->getCell("E".$row)->getValue();
                $zipCode = $worksheet->getCell("F".$row)->getValue();

                if($countryName != "" && $stateName != "" && $cityName != "" && $districtName != "" && $subDistrictName != "" && $zipCode != "" || $destID != ""){
                    $zipCodeAry[$zipCode][$subDistrictName][$districtName][$cityName][$stateName] = array("subDistrictName" => $subDistrictName, "districtName" => $districtName, "cityName" => $cityName, "stateName" => $stateName, "destID" => $destID);
                }
            }

            // $destIDAry[$destID] = $destID;
        }
    }

    // $destIDRes = $db->map('destination_id')->get('zip_code', null, 'destination_id');
    // $diff = array_diff($destIDAry, $destIDRes);
    // print_r($diff);

    unset($destID);

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
                        $destID = $zipCodeRow5["destID"];

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

                        print_r("subDistrictName: $subDistrictName zipCode: $zipCode destID: $destID\n\n");

                        unset($insertData);
                        $insertData = array(
                            "destination_id" => $destID,
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