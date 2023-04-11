<?php
    
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for Users.
 * Date  11/07/2017.
**/

class scriptFunction
{
    
    // function __construct(){
    //     Self::db = $db;
    //     Self::setting = $setting;
    // }
    
    function addNewWallet($insertCredit){
    	$db = MysqliDb::getInstance();

		$displayAry = $insertCredit['display'];
		$insertSettingAry = $insertCredit["setting"];
		$adminDisplayAry = $insertCredit['adminDisplay'];

		if(!$insertCredit['decimal']) $insertCredit['decimal'] = 2;
		$insertCredit['dcm'] = $insertCredit['decimal'];
		unset($insertCredit['decimal']);
		
		if(!$insertCredit['rate']) $insertCredit['rate'] = 1;
		unset($insertCredit['display'], $insertCredit["setting"], $insertCredit['adminDisplay']);


		$db->where("name", $insertCredit["name"]);
		$result = $db->getOne("credit", "ID");
		
        if($result){
        	echo date("Y-m-d H:i:s")." ".$insertCredit["name"]." (exist)\n";
            return false;
        }

        $db->orderBy('priority', 'DESC');
    	$result = $db->getOne("credit", "priority");

        if($insertCredit["priority"] > $result["priority"] || !$insertCredit["priority"]){
        	$insertCredit["priority"] = $result["priority"] + 1;
        }else{
			$db->rawQuery("UPDATE credit SET priority  = priority + 1 WHERE priority >= ".$insertCredit["priority"]);
        }

        $insertCredit["translation_code"] =  Self::generateDynamicCode("C");

        foreach($displayAry AS $language=>$msg){
        	$insertDisplay = array(
        		"code" => $insertCredit["translation_code"],
        		"module" => "Credit Display",
        		"language" => $language,
        		"site" => "System",
        		"type" => "Dynamic",
        		"content" => $msg,
        		"created_at" => $db->now()
        	);
        	$db->insert("language_translation", $insertDisplay);
        }

        if(empty($adminDisplayAry)){
        	$adminDisplayAry = $displayAry;
        }
        
    	$insertCredit["admin_translation_code"] =  Self::generateDynamicCode("C");
        foreach($adminDisplayAry AS $language=>$msg){
        	$insertDisplay = array(
        		"code" => $insertCredit["admin_translation_code"],
        		"module" => "Credit Display",
        		"language" => $language,
        		"site" => "System",
        		"type" => "Dynamic",
        		"content" => $msg,
        		"created_at" => $db->now()
        	);
        	$db->insert("language_translation", $insertDisplay);
        }

        $insertCredit["created_at"] =  $db->now();
        $creditID = $db->insert("credit", $insertCredit);

        $defaultSetting = array("isTransferable", "isWithdrawable", "isWallet", "isAdjustable", "isDisplayOnTransaction", "showTransactionHistory");
        $columnAry = array("value", "member", "admin","description","reference","type");

        foreach($defaultSetting AS $settingName){
        	unset($insertSetting);

        	$insertSetting["name"] = $settingName;
        	$insertSetting["credit_id"] = $creditID;
        	$insertSetting["value"] = $insertSetting["member"] = $insertSetting["admin"] = 0;
        	if($settingName == 'showTransactionHistory') $insertSetting["value"] = $insertSetting["member"] = $insertSetting["admin"] = 1;
            
        	foreach($columnAry AS $column){
        		if($insertSettingAry[$settingName][$column]){
        			$insertSetting[$column] = $insertSettingAry[$settingName][$column];
        		}
        	}

        	// print_r($insertSetting);
        	$db->insert("credit_setting", $insertSetting);
        }

        $isWallet = $insertSettingAry['isWallet']['value'];
        foreach($insertSettingAry AS $settingName=>$setting){
            unset($insertSetting);

            if(in_array($settingName, $defaultSetting)) continue;

            $insertSetting["name"] = $settingName;
            if(strpos($settingName, 'convertTo') !== false){
                $insertSetting["name"] = 'convertTo';
            }

            if(is_array($setting[0])){
                foreach ($setting as $loopSetting) {
                    
                    $insertSetting["credit_id"] = $creditID;
                    $insertSetting["value"] = $insertSetting["member"] = $insertSetting["admin"] = 0;

                    foreach($columnAry AS $column){
                        if($loopSetting[$column]){
                            $insertSetting[$column] = $loopSetting[$column];
                        }
                    }
                    $db->insert("credit_setting", $insertSetting);
                }
            }else{
                
                $insertSetting["credit_id"] = $creditID;
                $insertSetting["value"] = $insertSetting["member"] = $insertSetting["admin"] = 0;

                foreach($columnAry AS $column){
                    if($setting[$column]){
                        $insertSetting[$column] = $setting[$column];
                    }
                }
                $db->insert("credit_setting", $insertSetting);
            }
        }

        $db->where("name", "Accounts");
        $parentRow = $db->getOne("permissions", "id, level");

        $parentID = $parentRow["id"];
        $parentLevel = $parentRow["level"];
        $db->where("parent_id", $parentID);
        $db->orderBy("priority", "DESC");
        $return = $db->getOne("permissions", "priority");

        $insert = array(
        	"name" => $insertCredit["name"], 
        	"description" => "Credit Type Page", 
        	"type" => "Sub Menu", 
        	"parent_id" => $parentID, 
        	"file_path" => "memberDetailsList.php?type=".$insertCredit["name"], 
            "level" => $parentLevel+1,
        	"priority" => ($return["priority"]+1), 
        	"disabled" => ($isWallet ? "0" : "1"), 
        	"site" => "Admin", 
        	"translation_code" => $insertCredit["admin_translation_code"],
        	"created_at" => $db->now(), 
        	"reference_table" => "credit", 
        	"reference_id" => $creditID
        );

        $permissionsID = $db->insert("permissions", $insert);

        $subMenuAry = array(
        	'Withdrawal' => "memberWithdrawal.php", 
        	'Adjustment' => "memberAdjustment.php", 
        	'Transfer' => "memberTransfer.php"
        );

        foreach($subMenuAry AS $type=>$fileName){
        	$subPerms = array(
        		"name" => $insertCredit["name"]." ".$type,
				"description" => "Credit ".$type." Page",
				"type" => "Sub Menu",
				"parent_id" => $permissionsID,
				"file_path" => $fileName."?type=".$insertCredit["name"],
                "level" => $parentLevel+2,
				"priority" => 1,
				"site" => "Admin",
				"translation_code" => $insertCredit["admin_translation_code"],
				"created_at" => date("Y-m-d H:i:s"),
				"updated_at" => date("Y-m-d H:i:s"),
            );

        	$db->insert("permissions", $subPerms);
        	if($type != 'Adjustment'){
	        	$memberRights = array(
	        		"name" => $insertCredit["name"]." ".$type,
	                "description" => "Block Member to view this",
	                // "command" => "memberAddNewWithdrawal",
	                "credit_id" => $creditID,
	                "status" => "on"
	            );
	            // $db->insert("mlm_client_rights", $memberRights);
	        }

    	}
    	
        $insertRole = array(
        	"role_id" => "2",
			"permission_id" => $permissionsID,
			"disabled" => "0",
			"created_at" => $db->now()
		);

        $db->insert("roles_permission", $insertRole);

        $db->where("name", "Credit Transaction");
        $parentRow = $db->getOne("permissions", "id, level");
        $parentID = $parentRow["id"];
        $parentLevel = $parentRow["level"];

        $db->where("parent_id", $parentID);
        $db->orderBy("priority", "DESC");
        $return = $db->getOne("permissions", "priority");

        $insert = array(
        	"name" => $insertCredit["name"], 
        	"description" => $insertCredit["name"]." Transaction Listing Page", 
        	"type" => "Sub Menu", 
        	"parent_id" => $parentID, 
        	"file_path" => "creditTransactionList.php?type=".$insertCredit["name"], 
            "level" => $parentLevel + 1,
        	"priority" => ($return["priority"]+1), 
        	"disabled" => "0", 
        	"site" => "Admin", 
        	"translation_code" => $insertCredit["admin_translation_code"],
        	"created_at" => $db->now(), 
        	"reference_table" => "credit", 
        	"reference_id" => $creditID
        );

        $permissionsID = $db->insert("permissions", $insert);

        $insertRole = array(
        	"role_id" => "2",
			"permission_id" => $permissionsID,
			"disabled" => "0",
			"created_at" => $db->now()
		);

        $db->insert("roles_permission", $insertRole);


        return true;
    } // addNewWallet

    function generateDynamicCode($code){
    	$db = MysqliDb::getInstance();
    	
    	$db->where("code", $code."%", "LIKE");
    	$db->orderBy('code', 'DESC');
    	$codeData = $db->getOne("language_translation", "code");

        if (empty($codeData)) return $code."00001";

        $existCode = $codeData["code"];
        $existCode = str_replace($code, "", $existCode);
        $newCode = $code.str_pad($existCode+1, 5, "0", STR_PAD_LEFT);
		return $newCode;
    }

    function deleteDailytable($databaseName, $tablelike){

    	$db = MysqliDb::getInstance();

    	$results = $db->rawQuery("SELECT CONCAT('DROP TABLE ', table_name, ';') AS dropQuery FROM information_schema.tables WHERE table_schema = '".$databaseName."' AND table_name LIKE '".$tablelike."%'");
	    foreach($results as $dropStatements){
	        $db->rawQuery($dropStatements["dropQuery"]);
	    }
    }

    function removeDuplicateRecord($table, $duplicateColAry){
    	$db = MysqliDb::getInstance();

    	$first = 0;
    	while(true){

    		$result = $db->map('id')->rawQuery("SELECT MAX(id) as id, ".$duplicateColAry[0]." FROM `".$table."` GROUP BY ".implode(",", $duplicateColAry)." HAVING count(id) > 1");

    		// print_r("SELECT MAX(id) as id, ".$duplicateColAry[0]." FROM `".$table."` GROUP BY ".implode(",", $duplicateColAry)." HAVING count(id) > 1");
    		if(empty($result)) break;

    		if($first == 0){
    			echo "\n#".$table." duplicate record\n";
    			$first++;
    		}

    		foreach($result AS $id=>$firstData){
    			echo "id: ".$id." ".$firstData." (deleted)\n";
    		}

    		$db->where('id', array_keys($result), 'IN');
    		$db->delete($table);
    	}

    	if($first != 0){
    		echo "#".$table." (clean)\n";
    	}

    } // removeDuplicateRecord
    
    function regeneratePermission(){
    	$db = MysqliDb::getInstance();

    	$permID = $db->map('id')->get('permissions', null, 'id');
    	echo "Check Lost Permission (parent_id not found)\n";
    	while(true){
    		$db->where('parent_id', 0, '!=');
    		$db->Where('parent_id', $permID, 'NOT IN');
	    	$result = $db->map('id')->get('permissions', null, 'id, name, parent_id');
	    	if(empty($result)) break;

	    	foreach($result AS $id=>$rows){
	    		echo "id:".$id." parent:".$rows['parent_id']." ".$rows['name']."\n";
	    		$idAry[] = $id;
	    	}
	    	if($idAry){
	    		$db->where('id', $idAry, 'IN');
	    		$db->delete('permissions');
	    	}
    	}

    	if($idAry){
    		echo "Deleted ...\n";
    	}else{
    		echo "Clean ...\n";
    	}
    	echo "--------------------------\n";

    	$allPerms = $db->map('id')->get('permissions', null, 'id, parent_id');
    	while(!empty($allPerms)){
    		foreach($allPerms AS $id=>$parentID){
    			if($parentID == 0){
    				$levelAry[0][] = $id;
    				unset($allPerms[$id]);
    				continue;
    			}

    			foreach($levelAry AS $level=>$parentAry){
    				if(in_array($parentID, $parentAry)){
    					$levelAry[$level+1][] = $id;
    					unset($allPerms[$id]);
    					break;
    				}
    			}
    		}
    	}

    	foreach($levelAry AS $level=>$id){
    		$update = array('level' => $level);

    		$db->where('id', $id, 'IN');
    		$db->update('permissions', $update);	
		}

    	echo "Regenerate Permission\n";
    	// $db->orderBy('parent_id', 'ASC');
    	$db->orderBy('level', 'ASC');
    	$db->orderBy('disabled', 'ASC');
    	$db->orderBy('priority', 'ASC');
    	$db->orderBy('id', 'ASC');

    	$allPerms = $db->get('permissions', null, '*');

    	$total = count($allPerms);
    	echo "total Permission: ".$total."\n";
    	$db->rawQuery("TRUNCATE TABLE permissions");

    	foreach($allPerms AS $rows){
    		unset($id, $newID);
    		$id = $rows['id'];
    		if($rows['parent_id'] != 0){
    			$rows['parent_id'] = $newIDAry[$rows['parent_id']];
    		}

    		if(!$priority[$rows['parent_id']]){
    			$priority[$rows['parent_id']] = 1;
    		}else{
    			$priority[$rows['parent_id']]++;
    		}

    		$rows['priority'] = $priority[$rows['parent_id']];
    		unset($rows['id']);
    		$newID = $db->insert('permissions', $rows);
    		$newIDAry[$id] = $newID;
    	}

    	$count = $db->getValue('permissions', 'COUNT(id)');

    	echo "regenerated Permission :".$count." (".($total == $count? "Tally": "Not Tally").")\n";

    	echo "Permission - Healthy Check";
    	$permID = $db->map('id')->get('permissions', null, 'id');
		$db->where('parent_id', 0, '!=');
		$db->Where('parent_id', $permID, 'NOT IN');
    	$result = $db->map('id')->get('permissions', null, 'id, name');

    	if(empty($result)){
    		echo " (Healthy)\n";
    	}else{
    		echo " (Not Healthy)\n";
    	}
    	echo "--------------------------\n";

    }

    function regenerateRolePermission(){
    	$db = MysqliDb::getInstance();

    	echo "\nRegenerate roles_permission...\n";
    	$db->rawQuery("TRUNCATE TABLE roles_permission");

    	$db->where('name', 'Master Admin','!=');
	    $result = $db->get('roles', null, 'id, site');
	    foreach($result AS $row){
	    	$typeID[$row['site']] = $row['id'];
	    }

	    $result = $db->rawQuery("SELECT ID, site FROM permissions WHERE site IN ('SuperAdmin', 'Admin')");
	    foreach($result as $row){
	        $permissionID = $permissionData["ID"];
	        
	        $insert = array(
	        	"role_id" => $typeID[$row['site']], 
	        	"permission_id" => $row['ID'], 
	        	"created_at" => $db->now(), 
	        	"updated_at" => $db->now()
	        );

	        $db->insert("roles_permission", $insert);
	    }
	    echo "Done generate roles_permission ...\n";
    } // regenerateRolePermission

}?>