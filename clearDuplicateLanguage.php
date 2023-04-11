<?php

	echo date("Y-m-d H:i:s")." Script start\n";
    include_once('include/config.php');
    include_once('include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $result = $db->rawQuery("SELECT max(id) as id, code, content, language FROM `language_translation` GROUP BY  code, language, module, type HAVING count(id) > 1");
    foreach($result AS $rows){

    	echo $rows['code']." ".$rows['language']." ".$rows['content']."\n";
    	$temp[] = $rows['id'];
    }

   	if($temp){
   		
    	echo date("Y-m-d H:i:s")." Delete Duplicated!\n";

		$db->where('id', $temp, 'IN');
   		$db->delete('language_translation');
	}

    echo date("Y-m-d H:i:s")." Script End!\n";
?>
