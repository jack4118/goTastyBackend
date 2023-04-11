<?php
	
    /**
     * Script to auto manage processes from database processes table.
     * This script will be run under this path "/var/www/project/process/"
     * In table `processes`:
     * file_path eg. "processMessageOut.php"
     * output_path eg. "../log/processMessageOut.log"
     * arg1 - arg5 for arguments to be passed into the process file
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.general.php');
    include($currentPath.'/../include/class.provider.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.log.php');
	
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $general = new General();
    $setting = new Setting($db);
    $log = new Log($logPath, $logBaseName);
    $provider = new Provider($db);
    $message = new Message($db, $general, $provider);
    
    $processes = $db->get("processes");
    
    if (count($processes) == 0) {
        // No process exists
        $log->write(date("Y-m-d H:i:s")." No processes in the database.\n");
    }
	
	foreach ($processes as $process) {
		
		if (!$process['process_id'] || !checkPid($process['process_id'])) {
			
			if ($process['disabled'] == 1) {
				$log->write(date("Y-m-d H:i:s")." Process ".$process['name']." is disabled, do nothing.\n");
				continue;
			}
            
            if ($process['process_id']) {
                // If pid exist and process is dead, send notifications
                $content = "Process ".$process['name']." is dead.\n\n";
                $content .= "Path: $currentPath/".$process['file_path']."\n";
                $message->createMessageOut(10004, $content);
            }
			
			// If process is not running
			$log->write(date("Y-m-d H:i:s")." Process ".$process['name']." is enabled, attempting to start now.\n");
			
			$cmd = "nohup php ".$currentPath."/".$process['file_path'];
			if ($process['arg1']) $cmd .= " ".$process['arg1'];
			if ($process['arg2']) $cmd .= " ".$process['arg2'];
			if ($process['arg3']) $cmd .= " ".$process['arg3'];
			if ($process['arg4']) $cmd .= " ".$process['arg4'];
            if ($process['arg5']) $cmd .= " ".$process['arg5'];
			$cmd .= " >> ".$currentPath."/../log/".$process['output_path']." 2>&1 & echo $!;"; // echo $! to return pid
			
			$log->write(date("Y-m-d H:i:s")." Run ($cmd).\n");
			$pid = exec($cmd, $output, $result);
			//print_r($result);
			if ($result == 0) $log->write(date("Y-m-d H:i:s")." Success: $pid\n");
			else $log->write(date("Y-m-d H:i:s")." Failed to run process.\n");
			
            $db->where("id", $process['id']);
			$db->update("processes", array("process_id" => $pid));
			
			unset($pid);
			
		}
		else {
			// Check for disabled setting
			if ($process['disabled'] == 1) {
				// If process is running
				$log->write(date("Y-m-d H:i:s")." Process ".$process['name']." is disabled, attempting to kill it now.\n");
				
				$cmd = "kill -15 ".$process['process_id'];
				exec($cmd, $output, $result);
				
				// Update the pid to empty
                $db->where("id", $process['id']);
                $db->update("processes", array("process_id" => ""));
                
			}
			else {
				// If process is running
				$log->write(date("Y-m-d H:i:s")." Process ".$process['name']." is running, do nothing.\n");
			}
		}
	}
	
	function checkPid($pid)
	{
		// create our system command
		$cmd = "ps $pid";
		
		// run the system command and assign output to a variable ($output)
		exec($cmd, $output, $result);
		
		// check the number of lines that were returned
		if(count($output) >= 2){
			
			// the process is still alive
			return true;
		}
		
		// the process is dead
		return false;
	}
	
	?>
