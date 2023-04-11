<?php  
    echo "Start\n";
    $currentPath = __DIR__;

    include_once($currentPath."/../include/classlib.php");
    include_once($currentPath."/../include/class.language.php");
    include_once($currentPath."/../include/class.scriptFunction.php");

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    $language = new Language();
    $function = new scriptFunction();

    $excelFile = "stateList.xlsx";
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);

    } catch(Exception $e) {
        echo "\nPatch State List Process Failed... \n\n";
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
            $stateName = $sheet->getCell('B'.$row)->getValue();
            $countryName = $sheet->getCell('A'.$row)->getValue();
            if($stateName != ""){
                if($countryName != ""){
                    $stateNameArray[] = array('country'=>$countryName, 'stateName'=>$stateName);
                }
            }
        }
    }

    if($stateNameArray){
        echo "\nSuccessfully Retrieved Data from $excelFile\n";
        Log::write("\n".date("Y-m-d H:i:s")." Successfully Retrieved Data from $excelFile\n");
    }else{
        echo "\nData Not Found, aborting data patching \n";
        Log::write(date("Y-m-d H:i:s")." Data Not Found, aborting data patching \n");
    }

    sleep(1);
    echo "\nAll set, start state patching\n";

    $db->where('disabled', '0');
    $langAry = $db->getValue('languages', 'language', NULL);

    unset($stateName);
    echo "\nProcessing -->";
    Log::write("\n".date("Y-m-d H:i:s")." Processing State Insertion");
    foreach ($stateNameArray as $stateName) {
        echo implode(" ",$stateName)."\n";
        Log::write("\n".date("Y-m-d H:i:s")." $stateName");
        $translationCode = General::generateDynamicCode("D");
    
        $db->where('name', $stateName['country']);
        $countryID = $db->getValue('country', 'id');

        /*Insert to state table*/
        $insertData = array(
            "country_id"        => $countryID,
            "name"              => $stateName["stateName"],
            "translation_code"  => $translationCode,
            "disabled"          =>  "0",
            "created_at"        => $db->now(),
        );
        $insertRes = $db->insert('state', $insertData);

        if($insertRes) $success++;
        else $failed++;

        /*Insert langugae translation code*/
        foreach ($langAry as $lang) {
            $insertDisplay = array(
                "code" => $translationCode,
                "module" => "State Display",
                "language" => $lang,
                "site" => "State",
                "type" => "Dynamic",
                "content" => $stateName['stateName'],
                "created_at" => $db->now(),
            );
            $db->insert("language_translation", $insertDisplay);
            unset($insertDisplay);
        }
        unset($translationCode);
        unset($insertRes);
    }

    echo "\n\n\n";
    echo $success    . " \trows success\n";
    echo $failed     . " \trows failed\n";

    Log::write("\n".date("Y-m-d H:i:s")." Done patched state list with $success success and $failed failed\n");
    echo "\nEnd\n"; 
?>