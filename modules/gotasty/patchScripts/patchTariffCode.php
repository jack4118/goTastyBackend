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
            $districtName = $sheet->getCell("D".$row)->getValue();
            $tariffCode = $sheet->getCell("G".$row)->getValue();

            if(($districtName != "" && $tariffCode != "")){
                $districtTariffCode[$districtName] = $tariffCode;
            }else{
                print_r("[row: $row] districtName: $districtName, tariffCode: $tariffCode\n\n");
            }
        }
    }
    
    foreach ($districtTariffCode as $name => $code) {
        try {
            $query = 'UPDATE county SET tariff_code = "'.$code.'" WHERE name = "'.$name.'"';
            $db->rawQuery($query);
        } catch (Exception $e){
            print_r($e);
        }
    }


    echo "\nEnd\n"; 
?>