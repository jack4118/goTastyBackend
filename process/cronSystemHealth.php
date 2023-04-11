<?php
    
    /**
     * Script to update frontend and backend server health and notify when it reaches certain threshold
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.log.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $log = new Log($logPath, $logBaseName);

    // Get the list of servers
    $results = $db->get("server_status_summary");
    foreach ($results as $row)
    {
        $log->write(date("Y-m-d H:i:s")." Checking ".$row["server_name"]."(".$row["server_ip"].") now. ".$log->getMemoryUsage()."\n");

        $data['release'] = getServerOS($row["server_ip"], $row["is_local_machine"]);
        $data['totalCPU'] = getTotalCPU($row["server_ip"], $row["is_local_machine"]);

        $serverMemory = getServerMemoryUsage($row["server_ip"], $row["is_local_machine"]);
        $data['totalMemory'] = $serverMemory['totalMemory'];
        $data['totalSwap'] = $serverMemory['totalSwap'];
        $data['memoryUsed'] = $serverMemory['memoryUsed'];
        $data['swapUsed'] = $serverMemory['swapUsed'];
        // $data['memoryUsage'] = $serverMemory['memoryUsage'];
        // $data['swapUsage'] = $serverMemory['swapUsage'];

        $diskArray = getServerDiskUsage($row["server_ip"], $row["is_local_machine"]);
        $data['diskSize'] = $diskArray['diskSize'];
        $data['diskAvailable'] = $diskArray['diskAvailable'];
        $data['diskUsed'] = $diskArray['diskUsed'];
        // $data['diskUsage'] = $diskArray['diskUsage'];

        $avgCpu = getAvgCpu($row["server_ip"], $row["is_local_machine"]);
        $data['cpuLoadAverage'] = $avgCpu['cpuLoadAverage'];
        $data['cpuIdle'] = $avgCpu['cpuIdle'];

        $update = array (
            'release' => $data['release'],
            'total_cpu' => $data['totalCPU'],
            'total_memory' => $data['totalMemory'],
            'total_swap' => $data['totalSwap'],
            'disk_size' => $data['diskSize'],
            'disk_available' => $data['diskAvailable'],
            'updated_at' => $db->now()
        );

        $db->where('id', $row["id"]);
        $db->update('server_status_summary', $update);

        $insert = array (
            'server_id' => $row["id"],
            'cpu_load' => $data['cpuLoadAverage'],
            'cpu_idle' => $data['cpuIdle'],
            'memory_used' => $data['memoryUsed'],
            'swap_used' => $data['swapUsed'],
            'disk_used' => $data['diskUsed'],
            'created_at' => $db->now()
        );

        $db->insert('server_status_data', $insert);
        
        $log->write(date("Y-m-d H:i:s")." Finished checking ".$row["server_name"]."(".$row["server_ip"]."). ".$log->getMemoryUsage()."\n");
    }

    function getTotalCPU($serverIP, $isLocalMachine=0) {
        global $log;
        
        if($isLocalMachine) {
            $cmd = "grep processor /proc/cpuinfo | wc -l";
        } else {
            $cmd = "ssh root@$serverIP \"grep processor /proc/cpuinfo | wc -l\"";
        }
            
        $totalCPU = shell_exec($cmd);
        if(is_null($totalCPU)) {
            $log->write(date("Y-m-d H:i:s")." Connection Error ($cmd) ".$log->getMemoryUsage()."\n");
            return "N/A";
        }
        
        return $totalCPU;
    }

    function getAvgCpu($serverIP, $isLocalMachine=0) {
        global $log;
        
        if($isLocalMachine) {
            $cmd = "iostat";
        } else {
            $cmd = "ssh root@$serverIP \"iostat\"";
        }
            
        $result = shell_exec($cmd);
        if(is_null($result)) {
            $log->write(date("Y-m-d H:i:s")." Connection Error ($cmd) ".$log->getMemoryUsage()."\n");
            return "N/A";
        }

        $result = explode("\n", $result);
        $result = array_values(array_filter(explode(" ", $result[3])));

        $total = 0;
        foreach($result as $value) {
            $total += $value;
        }

        $cpuLoadAverage = 100 - ($result[5] * 100 / $total);
        $cpuLoadAverage = $cpuLoadAverage."%";

        $cpuIdle = $result[5]."%";

        $details = array (
            'cpuLoadAverage' => $cpuLoadAverage,
            'cpuIdle' => $cpuIdle
        );
        
        return $details;
    }
    
    function getServerOS($serverIP, $isLocalMachine=0)
    {
        global $log;
        
        if ($isLocalMachine)
        {
            $cmd = "cat /etc/redhat-release";
        }
        else
        {
            $cmd = "ssh root@$serverIP \"cat /etc/redhat-release\"";
        }
            
        $release = shell_exec($cmd);
        if(is_null($release)) {
            $log->write(date("Y-m-d H:i:s")." Connection Error ($cmd) ".$log->getMemoryUsage()."\n");
            return "N/A";
        }
        
        $release = str_replace("\n", "", $release);
        
        return $release;
    }
    
    function getServerDiskUsage($serverIP, $isLocalMachine=0)
    {
        global $log;
        
        if ($isLocalMachine)
        {
            $cmd = "df -h -T | awk '{print $1 \" \" $2 \" \" $3 \" \" $4 \" \" $5}'";
        }
        else
        {
            $cmd = "ssh root@$serverIP \"df -h -T | awk '{print $1 \" \" $2 \" \" $3 \" \" $4 \" \" $5}'\"";
        }
        
        $data['diskSize'] = "N/A";
        $data['diskUsed'] = "N/A";
        $data['diskAvailable'] = "N/A";
        $data['diskUsage'] = "N/A";
        
        $disk = shell_exec($cmd);
        if(is_null($disk)) {
            $log->write(date("Y-m-d H:i:s")." Connection Error ($cmd) ".$log->getMemoryUsage()."\n");
            return $data;
        }
        
        // Split the result into an array by lines (removing the final linefeed)
        $drives = preg_split("[\r|\n]", trim($disk));
        
        // Chuck away the unused first line
        array_shift($drives);
        
        $values = array_values(array_filter(explode(" ", $drives[0])));
        
        $data['diskSize'] = $values[2];
        $data['diskUsed'] = $values[3];
        $data['diskAvailable'] = $values[4];
        if ($values[5])
        {
            $data['diskUsage'] = preg_replace('/\D/', '', $values[5])."%";
        }
        else
        {
            $data['diskUsage'] = ((int)((float)$values[3] / (float)$values[2] * 100))."%";
        }
        
        return $data;
    }
    
    function getServerMemoryUsage($serverIP, $isLocalMachine=0)
    {
        global $log;
        
        if ($isLocalMachine)
        {
            $cmd = "free -m";
        }
        else
        {
            $cmd = "ssh root@$serverIP \"free -m\"";
        }
        
        $free = shell_exec($cmd);
        if(is_null($free)) {
            $log->write(date("Y-m-d H:i:s")." Connection Error ($cmd) ".$log->getMemoryUsage()."\n");
            return "N/A";
        }
        
        // $free = (string)trim($free);
        // $freeArray = explode("\n", $free);
        // $mem = explode(" ", $freeArray[1]);
        // $mem = array_filter($mem);
        // $mem = array_merge($mem);
        // $memoryUsage = $mem[2]/$mem[1]*100;
        
        // return number_format($memoryUsage, 2, ".", ",")."%";

        $free = explode("\n", $free);

        $memory = array_values(array_filter(explode(" ", $free[1])));
        $memoryUsage = $memory[2]/$memory[1]*100;
        $memoryUsed = $memory[2];
        $totalMemory = $memory[1];

        $swap = array_values(array_filter(explode(" ", $free[2])));
        $swapUsage = $swap[2]/$swap[1]*100;
        $swapUsed = $swap[2];
        $totalSwap = $swap[1];

        $details = array (
            'memoryUsage' => $memoryUsage,
            'totalMemory' => $totalMemory,
            'memoryUsed' => $memoryUsed,
            'swapUsage' => $swapUsage,
            'totalSwap' => $totalSwap,
            'swapUsed' => $swapUsed
        );

        return $details;
    }
    
    function getServerLoad($serverIP, $isLocalMachine=0)
    {
        global $log;
        
        if ($isLocalMachine)
        {
            $cmd = "cat /proc/stat";
        }
        else
        {
            $cmd = "ssh root@$serverIP \"cat /proc/stat\"";
        }
        
        $stats = shell_exec($cmd);
        if(is_null($stats)) {
            $log->write(date("Y-m-d H:i:s")." Connection Error ($cmd) ".$log->getMemoryUsage()."\n");
            return "N/A";
        }
        
        // Remove double spaces to make it easier to extract values with explode()
        $stats = preg_replace("/[[:blank:]]+/", " ", $stats);
        
        // Separate lines
        $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
        $stats = explode("\n", $stats);
        
        // Separate values and find line for main CPU load
        foreach ($stats as $statLine)
        {
            $statLineData = explode(" ", trim($statLine));
            
            // Found!
            if ((count($statLineData) >= 5) && ($statLineData[0] == "cpu"))
            {
                return array(
                             $statLineData[1],
                             $statLineData[2],
                             $statLineData[3],
                             $statLineData[4],
                             );
            }
        }

        return array(0, 0, 0, 0);
    }
    
    function compareServerLoad($serverIP, $isLocalMachine=0)
    {
        $load = "N/A";
        
        // Get first server load data
        $statData1 = getServerLoad($serverIP, $isLocalMachine);
        // Delay 5 second
        sleep(5);
        // Get second server load data
        $statData2 = getServerLoad($serverIP, $isLocalMachine);
        
        if ($statData1 == "N/A" && $statData2 == "N/A") {
            return $load;
        }
        
        if ((!is_null($statData1)) && (!is_null($statData2)))
        {
            // Get difference
            $statData2[0] -= $statData1[0]; // User
            $statData2[1] -= $statData1[1]; // Nice
            $statData2[2] -= $statData1[2]; // System
            $statData2[3] -= $statData1[3]; // Idle
            
            // Sum up the 4 values for User, Nice, System and Idle and calculate
            // the percentage of idle time (which is part of the 4 values!)
            $cpuTime = $statData2[0] + $statData2[1] + $statData2[2] + $statData2[3];
            
            // Invert percentage to get CPU time, not idle time
            $load = 100 - ($statData2[3] * 100 / $cpuTime);
        }
        
        return number_format($load, 2, ".", ",")."%";
    }

?>
