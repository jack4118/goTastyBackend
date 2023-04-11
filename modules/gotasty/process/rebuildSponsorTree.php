<?php
    $currentPath = __DIR__;
    include_once($currentPath.'/../include/config.php');
    include_once($currentPath.'/../include/class.database.php');
    include_once($currentPath.'/../include/class.setting.php');
    include_once($currentPath.'/../include/class.general.php');
    include_once($currentPath.'/../include/class.tree.php');
    include_once($currentPath.'/../include/class.log.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $tree = new Tree($db, $setting, $general);

    $logBaseName = basename(__FILE__, '.php');
    $logPath     = $currentPath.'/log/';
    $log         = new Log($logPath, $logBaseName);

    $log->write(date("Y-m-d H:i:s")." Start rebuilding sponsor tree.\n");

    //Empty the table
    $db->rawQuery('TRUNCATE `tree_sponsor`');

    $db->where('type', 'Client');
    $db->orderBy('id', 'asc');
    $copy = $db->copy();
    $result = $db->get('client', null, 'id, sponsor_id');
    $totalClients = $copy->getValue("client", "count(id)");

    //Count the number of client successful insert to tree
    $count = 0;

    if(empty($result)) {
        $log->write("No Client Found.\n");
        exit();
    }

    $count = recursiveBuildSponsorTree($result, $count);
    $log->write("Total clients : ".$totalClients."\nTotal clients successful inserted : ".$count."\n".date("Y-m-d H:i:s")." Finish rebuilding sponsor tree.\n");

    function recursiveBuildSponsorTree($clients, $count) {
        global $db, $tree;

        foreach($clients as $value) {

            if($value['id'] == 1000000) {
                $form = array(
                    'client_id' => $value['id'],
                    'upline_id' => 0,
                    'level' => 0,
                    'trace_key' => $value['id']
                );
                $db->insert('tree_sponsor', $form);
                $count++;
                continue;
            }

            $flag = $tree->insertSponsorTree($value['id'], $value['sponsor_id']);

            if($flag)
                $count++;
            else
                $postpone[] = $value;
        }

        if(!empty($postpone)) {
            $log->write(date("Y-m-d H:i:s")." Rebuilding postpone clients.\n");

            recursiveBuildSponsorTree($postpone, $count);
        }
        else
            return $count;
    }
?>