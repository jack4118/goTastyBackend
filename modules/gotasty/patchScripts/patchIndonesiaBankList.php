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

    $excelFile = "indonesiaBankList.xlsx";
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);

    } catch(Exception $e) {
        echo "\nPatch Bank List Process Failed... \n\n";
        echo "Filename\t: " . pathinfo($excelFile, PATHINFO_BASENAME) . "\n";
        echo "Error message\t: " . $e->getMessage() . "\n\n";
        exit();
    }

    $sheet         = $objPHPExcel->getSheet(0);
    $highestRow    = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    $success       = 0; 
    $failed        = 0;
    $duplicated    = 0;

    for ($row = 1; $row <= $highestRow; $row++){ 
        if($row > 1) {
            $bankName = $sheet->getCell('B'.$row)->getValue();
            if($bankName != ""){
                $bankNameArray[] = $bankName;
            }   
        }
    }

    if($bankNameArray){
        echo "\nSuccessfully Retrieved Bank List from $excelFile\n";
        Log::write("\n".date("Y-m-d H:i:s")." Successfully Retrieved Bank List from $excelFile\n");

        $countryName = "Indonesia";
        $db->where('name', $countryName);
        $countryID = $db->getValue('country', 'id');

        echo "\nSet existing $countryName bank to Inactive status. \n";
        Log::write("\n\n".date("Y-m-d H:i:s")." Set existing $countryName bank to Inactive status. \n");

        $db->where('country_id', $countryID);
        $db->update('mlm_bank', array("status" => "Inactive"));
    }else{
        echo "\nBank List Not Found, aborting bank list patching \n";
        Log::write(date("Y-m-d H:i:s")." Bank List Not Found, aborting bank list patching \n");
    }

    sleep(1);
    echo "\nAll set, start bank list patching\n";

    $db->where('disabled', '0');
    $langAry = $db->getValue('languages', 'language', NULL);

    unset($bankName);
    echo "\nProcessing -->";
    Log::write("\n".date("Y-m-d H:i:s")." Processing Bank Insertion");
    foreach ($bankNameArray as $bankName) {
        echo "\n$bankName";
        Log::write("\n".date("Y-m-d H:i:s")." $bankName");
        $bankTC = General::generateDynamicCode("F");
        
        /*Insert to Bank table*/
        $insertBankData = array(
            "country_id"       => $countryID,
            "name"             => $bankName,
            "translation_code" => $bankTC,
            "status"           => "Active"
        );
        $insertRes = $db->insert('mlm_bank', $insertBankData);

        if($insertRes) $success++;
        else $failed++;

        /*Insert langugae translation code*/
        foreach ($langAry as $lang) {
            $insertDisplay = array(
                "code"       => $bankTC,
                "module"     => "Bank Display",
                "language"   => $lang,
                "site"       => "System",
                "type"       => "Dynamic",
                "content"    => $bankName,
                "created_at" => $db->now()
            );
            $db->insert("language_translation", $insertDisplay);
            unset($insertDisplay);
        }
        unset($bankTC);
        unset($insertRes);
        unset($existingBank);
    }

    echo "\n\n\n";
    echo $success    . " \trows success\n";
    echo $failed     . " \trows failed\n";

    Log::write("\n".date("Y-m-d H:i:s")." Done patched bank list with $success success and $failed failed\n");
echo "\nEnd\n"; ?>