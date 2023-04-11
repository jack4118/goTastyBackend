<?php  echo "Start\n";

    // ini_set('display_errors', 1);
    // ini_set('display_startup_errors', 1);
    // error_reporting(E_ALL);

    // $db->rawQuery("TRUNCATE TABLE mlm_bonus_setting");
    // $db->delete($tableName);

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once ($currentPath.'/../language/lang_all.php');
    General::$currentLanguage = 'english';
    General::$translations    = $translations;

    $db->rawQuery("TRUNCATE TABLE type_mapping");
    
    $db->groupBy("subject");
    $subjects = $db->getValue("credit_transaction","subject", null);
    if($subjects){
        foreach ($subjects as $subject) {
            $translateSubject[$subject] = General::getTranslationByName($subject);
        }

        $db->where("language", "english");
        $db->where("content",$translateSubject,"IN");
        $res = $db->map("content")->get("language_translation",null,"content,code");

        foreach ($translateSubject as $key => &$value) {
            echo " Key : ".$key." Ori : ".$value."\n";
            $value = $res[$translateSubject[$key]];
            echo " Change to : ".$value."\n";
        }
    }

    /* Bonus Payout */
    $db->join('mlm_bonus_payment_method b', 'b.bonus_id = a.id', 'RIGHT');
    $bonusRes = $db->map('name')->get('mlm_bonus a', NULL, 'a.name, b.description, a.language_code');

    foreach ($bonusRes as $bonusKey => $bonusValue) {
        $translateSubject[$bonusValue['description']] = $bonusValue['language_code'];
    }
    
    $i = 1;
    foreach ($translateSubject as $key => $newValue) {
        if(!$newValue) continue;
        echo "$key\n";
        $insertData = array(
                                'name'=>$key,
                                'type'=>'Transaction Type',
                                'translation_code'=>$newValue,
                                'priority'=>$i,
                                'disabled'=>0
                            );
        $db->insert("type_mapping",$insertData);
        $i++;
    }
    
?>
    

