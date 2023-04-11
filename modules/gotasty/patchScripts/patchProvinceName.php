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

    $excelFile = "provinceName.xlsx";
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);
    } catch(Exception $e) {
        echo "\nPatch Province Name Process Failed... \n\n";
        echo "Filename\t: " . pathinfo($excelFile, PATHINFO_BASENAME) . "\n";
        echo "Error message\t: " . $e->getMessage() . "\n\n";
        exit();
    }

    $provinceSuccess       = 0; 
    $provinceFailed        = 0;
    $provinceDuplicated    = 0;
    $citySuccess           = 0; 
    $cityFailed            = 0;
    $cityDuplicated        = 0;
    $districtSuccess       = 0; 
    $districtFailed        = 0;
    $districtDuplicated    = 0;
    $subDistrictSuccess    = 0; 
    $subDistrictFailed     = 0;
    $subDistrictDuplicated = 0;
    $zipCodeSuccess        = 0; 
    $zipCodeFailed         = 0;
    $zipCodeDuplicated     = 0; 

    // loop through the excel sheet
    foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
        // echo 'Worksheet number - ', $objPHPExcel->getIndex($worksheet), PHP_EOL;
        // $sheet         = $objPHPExcel->getSheet(0);
        unset($highestRow);
        unset($highestColumn);
        unset($sheetName);
        unset($dataArray);
        unset($dataArrayList);
        $highestRow    = $worksheet->getHighestRow(); // max row number
        $highestColumn = $worksheet->getHighestColumn(); // max column alphabet

        // loop through excel to get row of data
        for ($row = 1; $row <= $highestRow; $row++){ 
            if($row > 1) {
                $dataArray = $worksheet->getCell('A'.$row)->getValue();
                if($dataArray != ""){
                    $dataArrayList[] = $dataArray;
                }   
            }
        }

        if($dataArrayList){
            echo "\nSuccessfully Retrieved Data from $excelFile\n";
            Log::write("\n".date("Y-m-d H:i:s")." Successfully Retrieved Data from $excelFile\n");

            $countryName = "Indonesia";
            $db->where('name', $countryName);
            $countryID = $db->getValue('country', 'id');
        }else{
            echo "\nData Not Found, aborting data patching \n";
            Log::write(date("Y-m-d H:i:s")." Data Not Found, aborting data patching \n");
        }

        sleep(1);
        echo "\nAll set, start state patching\n";

        $db->where('disabled', '0');
        $langAry = $db->getValue('languages', 'language', NULL);

        //check for specific sheet
        $sheetName = $worksheet->getCell('A1')->getValue();
        echo "\n $sheetName\n";
        if($sheetName == "PROVINCE_NAME"){
            // process start here
            echo "\nProcessing Province Name -->";
            Log::write("\n".date("Y-m-d H:i:s")." Processing Province Name Insertion");
            foreach ($dataArrayList as $stateName) {
                echo "\n$stateName";
                Log::write("\n".date("Y-m-d H:i:s")." $stateName");
                $translationCode = General::generateDynamicCode("D");

                // Insert to state table
                $insertData = array(
                    "country_id"        => $countryID,
                    "name"              => trim($stateName),
                    "translation_code"  => $translationCode,
                    "disabled"          =>  "0",
                    "created_at"        => $db->now(),
                );
                $insertRes = $db->insert('state', $insertData);

                if($insertRes) $provinceSuccess++;
                else $provinceFailed++;

                // Insert to language_translation table
                foreach ($langAry as $lang) {
                    $insertDisplay = array(
                        "code" => $translationCode,
                        "module" => "State Display",
                        "language" => $lang,
                        "site" => "State",
                        "type" => "Dynamic",
                        "content" => trim($stateName),
                        "created_at" => $db->now(),
                    );
                    $db->insert("language_translation", $insertDisplay);
                    unset($insertDisplay);
                }
                unset($translationCode);
                unset($insertRes);
            }
        }else if($sheetName == "CITY_NAME"){
            // process start here
            echo "\nProcessing City Name -->";
            Log::write("\n".date("Y-m-d H:i:s")." Processing City Name Insertion");
            foreach ($dataArrayList as $cityName) {
                echo "\n$cityName";
                Log::write("\n".date("Y-m-d H:i:s")." $cityName");

                // Insert to city table
                $insertData = array(
                    "country_id"        => $countryID,
                    "name"              => trim($cityName),
                    "disabled"          =>  "0",
                    "created_at"        => $db->now(),
                );
                $insertRes = $db->insert('city', $insertData);

                if($insertRes) $citySuccess++;
                else $cityFailed++;
                unset($insertRes);
            }

        }else if($sheetName == "DISTRICT_NAME"){
            // process start here
            echo "\nProcessing District Name -->";
            Log::write("\n".date("Y-m-d H:i:s")." Processing District Name Insertion");
            foreach ($dataArrayList as $districtName) {
                echo "\n$districtName";
                Log::write("\n".date("Y-m-d H:i:s")." $districtName");

                // Insert to county table
                $insertData = array(
                    "country_id"        => $countryID,
                    "name"              => trim($districtName),
                    "disabled"          =>  "0",
                    "created_at"        => $db->now(),
                );
                $insertRes = $db->insert('county', $insertData);

                if($insertRes) $districtSuccess++;
                else $districtFailed++;
                unset($insertRes);
            }

        }else if($sheetName == "SUBDISTRICT_NAME"){
            // process start here
            echo "\nProcessing Sub District Name -->";
            Log::write("\n".date("Y-m-d H:i:s")." Processing Sub District Name Insertion");
            foreach ($dataArrayList as $subDistrictName) {
                echo "\n$subDistrictName";
                Log::write("\n".date("Y-m-d H:i:s")." $subDistrictName");

                // Insert to sub_county table
                $insertData = array(
                    "country_id"        => $countryID,
                    "name"              => trim($subDistrictName),
                    "disabled"          =>  "0",
                    "created_at"        => $db->now(),
                );
                $insertRes = $db->insert('sub_county', $insertData);

                if($insertRes) $subDistrictSuccess++;
                else $subDistrictFailed++;
                unset($insertRes);
            }

        }else if($sheetName == "ZIP_CODE"){
            // process start here
            echo "\nProcessing Zip Code -->";
            Log::write("\n".date("Y-m-d H:i:s")." Processing Zip Code Insertion");
            foreach ($dataArrayList as $zipCode) {
                echo "\n$zipCode";
                Log::write("\n".date("Y-m-d H:i:s")." $zipCode");

                // Insert to zip_code table
                $insertData = array(
                    "country_id"        => $countryID,
                    "name"              => trim($zipCode),
                    "disabled"          =>  "0",
                    "created_at"        => $db->now(),
                );
                $insertRes = $db->insert('zip_code', $insertData);

                if($insertRes) $zipCodeSuccess++;
                else $zipCodeFailed++;
                unset($insertRes);
            }

        }else{
            echo "\Sheet Name not found";
            Log::write("\n".date("Y-m-d H:i:s")." Sheet Name not found");
        }
    }

    echo "\n\n\n";
    echo $provinceSuccess    . " \tprovince rows success\n";
    echo $provinceFailed     . " \tprovince rows failed\n";
    echo $citySuccess    . " \tcity rows success\n";
    echo $cityFailed     . " \tcity rows failed\n";
    echo $districtSuccess    . " \tdistrict rows success\n";
    echo $districtFailed     . " \tdistrict rows failed\n";
    echo $subDistrictSuccess    . " \tsubDistrict rows success\n";
    echo $subDistrictFailed     . " \tsubDistrict rows failed\n";
    echo $zipCodeSuccess    . " \tzipCode rows success\n";
    echo $zipCodeFailed     . " \tzipCode rows failed\n";

    Log::write("\n".date("Y-m-d H:i:s")." Done patched province list with $success success and $failed failed\n");
echo "\nEnd\n"; ?>