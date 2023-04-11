<?php  echo "Start\n";
    $currentPath = __DIR__;

    // ini_set('display_errors', 1);
    // ini_set('display_startup_errors', 1);
    // error_reporting(E_ALL);

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../include/class.language.php');
    include_once($currentPath.'/../include/class.scriptFunction.php');

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    $language = new Language();
    $function = new scriptFunction();

    $excelFile = "august2022RankList.xlsx";
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);

    } catch(Exception $e) {
        echo "\nPatch rank Process Failed... \n\n";
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

    $db->where('type','Bonus Tier');
    $rankArr = $db->map('id')->get("rank",null,"id,translation_code");

    $db->where('module','rank');
    $db->where('language','english');
    $rankMap = $db->map('code')->get('language_translation',null,'code, content');

    for ($row = 1; $row <= $highestRow; $row++){ 
        if($row > 1) {
            if($sheet->getCell('C'.$row)->getValue() == "X") continue;

            $clientID = $db->where('member_id',"".$sheet->getCell('A'.$row)->getValue()."")->getValue('client','id');

            unset($rankID);
            foreach($rankArr as $rank_id => $code){
                if("".$sheet->getCell('C'.$row)->getValue()."" == $rankMap[$code]){
                    $rankID = $rank_id;
                }
            }

            if(!$rankID){
                $failed ++;
                continue;
            }

            unset($insertData);
            $insertData = array(
                'client_id'  => $clientID,
                'name'       => "rankDisplay",
                'rank_type'  => "Bonus Tier",
                'rank_id'    => $rankID,
                'created_at' => "2022-07-01 23:57:44",
                'updated_at' => "2022-07-01 23:57:44",
                'type'       => "System"
            );

            $isSet = $db->insert('client_rank',$insertData);
            if($isSet) $success++;
            else $failed++;

            unset($insertDiscount);
            $insertDiscount = array(
                'client_id'  => $clientID,
                'name'       => "discountPercentage",
                'value'      => "25",
                'rank_type'  => "Bonus Tier",
                'rank_id'    => $rankID,
                'created_at' => "2022-07-01 23:57:44",
                'updated_at' => "2022-07-01 23:57:44",
                'type'       => "System"
            );

            $isInsert = $db->insert('client_rank',$insertDiscount);
            if($isInsert) $success++;
            else $failed++;

        }
    }

    echo "\n\n\n";
    echo $success    . " \trows success\n";
    echo $failed     . " \trows failed\n";

    Log::write("\n".date("Y-m-d H:i:s")." Done patched rank with $success success and $failed failed\n");
echo "\nEnd\n"; ?>