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

    $excelFile = "placementRemap202209.xlsx";
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excelFile);
        $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel   = $objReader->load($excelFile);

    } catch(Exception $e) {
        echo "\nPatch Placement Remap Process Failed... \n\n";
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

    unset($clientLeft, $clientRight);
    for ($row = 1; $row <= $highestRow; $row++){ 
        if($row > 1) {
            if($row == 2){
                $firstID = $db->where('member_id',"".$sheet->getCell('A'.$row)->getValue()."")->getValue('client','id');

                $insertData = array(
                   "client_id"          => $firstID,
                   "client_unit"        => 0,
                   "client_position"    => 0,
                   "upline_id"          => 0,
                   "upline_unit"        => 0,
                   "upline_position"    => 0,
                   "level"              => 0,
                   "trace_key"          => $firstID
               );
               $insertRes = $db->insert("tree_placement", $insertData);
               if($insertRes) $success++;
               else $failed++;
            }

            $uplineID = $db->where('member_id',"".$sheet->getCell('A'.$row)->getValue()."")->getValue('client','id');
            $clientLeft = $db->where('member_id',"".$sheet->getCell('C'.$row)->getValue()."")->getValue('client','id');
            $clientRight = $db->where('member_id',"".$sheet->getCell('E'.$row)->getValue()."")->getValue('client','id');

            if($clientLeft){
                if($clientLeft != "1"){
                    $insertRes = Tree::insertPlacementTree($clientLeft, $uplineID,'1');
                    if($insertRes) $success++;
                    else $failed++;
                }                
            }

            if($clientRight){
                if($clientRight != "1"){
                    $insertRes = Tree::insertPlacementTree($clientRight, $uplineID,'2');
                    if($insertRes) $success++;
                    else $failed++;
                }                
            }
        }
    }

    echo "\n\n\n";
    echo $success    . " \trows success\n";
    echo $failed     . " \trows failed\n";

    Log::write("\n".date("Y-m-d H:i:s")." Done patched placement remap with $success success and $failed failed\n");
echo "\nEnd\n"; ?>