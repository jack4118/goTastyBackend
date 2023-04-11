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

    $excelFile = "BankList.xlsx";
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

    $countryIDArray = $db->map('name')->get('country',null,'name,id');

    $bankNameArray = $bankCountryArray = array();
    for ($row = 1; $row <= $highestRow; $row++){
        if($row > 1) {
            $countryName = $sheet->getCell('A'.$row)->getValue();
            $bankName = $sheet->getCell('B'.$row)->getValue();
            $newBankName = $sheet->getCell('C'.$row)->getValue();

            if ($newBankName) {
                $bankNameArray[$newBankName] = $newBankName;
                $bankCountryArray[$newBankName] = $countryName;

                $db->where('name',$bankName);
                $db->update('mlm_bank',array('name'=>$newBankName));
                
                $db->where('name',$newBankName);
                $translationCode = $db->getValue('mlm_bank','translation_code');

                $db->where('code',$translationCode);
                $db->update('language_translation',array('content'=>$newBankName));
            } else {
                $bankNameArray[$bankName] = $bankName;
                $bankCountryArray[$bankName] = $countryName;
            }
        }
    }

    // Set removed bank status as inactive
    $res = $db->get('mlm_bank',null,'id,country_id,name,translation_code,status');

    foreach ($res as $row) {
        if (!in_array($row['name'],$bankNameArray) && $row['status'] == 'Active') {
            $db->where('id',$row['id']);
            $db->update('mlm_bank',array('status'=>'Inactive'));
        }

        if (in_array($row['name'],$bankNameArray) && $row['status'] == 'Inactive') {
            $db->where('id',$row['id']);
            $db->update('mlm_bank',array('status'=>'Active'));
        }

        unset($bankNameArray[$row['name']]);
    }

    // Insert new bank
    foreach ($bankNameArray as $bankName) {
        $countryID = $countryIDArray[$bankCountryArray[$bankName]];

        $translationCode = $function->generateDynamicCode('F');

        foreach (array('english','chineseSimplified','chineseTraditional') as $translationLanguage) {
            $insertData = array(
                'code' => $translationCode,
                'module' => 'Bank Display',
                'language' => $translationLanguage,
                'site' => 'System',
                'type' => 'Dynamic',
                'content' => $bankName,
                'created_at' => date('Y-m-d H:i:s')
            );
            $db->insert('language_translation',$insertData);
        }

        $insertData = array(
            'country_id' => $countryID,
            'name' => $bankName,
            'translation_code' => $translationCode,
            'status' => 'Active',
        );
        $db->insert('mlm_bank',$insertData);
    }