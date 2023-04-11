<?php
    
	class Log{
		
		// The log file size limit in bytes.
		private $maxLogSize = 10000000;
		
		// The number of backup logs to keep.
		private $backupLogs = 2;
		
		// Constructor
		// $path_arg = full path.. "/var/www/html/"
		// $name_arg = file name.. "process-A"
		function __construct($path_arg=NULL, $name_arg=NULL){
			
			if (is_null($path_arg) || ($path_arg === '')) {
				return;
			}
			if (is_null($name_arg) || ($name_arg === '')) {
				return;
			}
			
			$path = realpath($path_arg);
			// make sure the dir is writable
			if (!is_dir($path) || !is_writable($path)) {
				throw new Exception('Log constructor failed.  Path ' . $path . ' is not writable.');
			}
			
			$this->logFilePath = $path;
			$this->logFileName = $name_arg;
			
			// make sure there isn't already a non-writable file with our filename
			$this->logFile = $this->getLogFileName();
			
		}
		
		
		function getLogFileName($logNumber=0) {
			if ($logNumber == 0) {
				return $this->logFilePath . DIRECTORY_SEPARATOR . $this->logFileName . '.log';
			} else {
				return $this->logFilePath . DIRECTORY_SEPARATOR . $this->logFileName . '.' . trim($logNumber) . '.log';
			}
		} // getLogFileName()
		
		
		function write($msg){
			
			$msgLength = strlen($msg) + 1;
			
			// sanity check...don't want to go writing huge files
			if ($msgLength > $this->maxLogSize) {
				throw new Exception('Writing log failed.  A single message was written that exceeds the maximum log size of '.$this->maxLogSize);
				return FALSE;
			}
			
			// check if we will pass the max log size.  if so, rotate logs
			if (file_exists($this->logFile)) {
				if ((filesize($this->logFile) + strlen($msg) + 1) > $this->maxLogSize) {
					$this->rotateLogs();
				}
			}
			
			// write the log
			$result = file_put_contents($this->logFile, $msg, FILE_APPEND | LOCK_EX);
			// clear the cached file
			clearstatcache();
			
			if ($result === FAlSE) {
				return FALSE;
			} else {
				return TRUE;
			}
			
		} // write()
		
		
		function rotateLogs(){
			
			// delete the highest log file if it exists
			$lastLog = $this->getLogFileName($this->backupLogs);
			if (file_exists($lastLog)) {
				unlink($lastLog);
			}
			
			// rotate the other logs
			for($i=$this->backupLogs; $i>0; $i--) {
				$newLog = $this->getLogFileName($i);
				$oldLog = $this->getLogFileName($i-1);
				if (file_exists($oldLog)) {
					if (!rename($oldLog, $newLog)) {
						throw new Exception('Rotate logs failed. Failure renaming '.$oldLog.' to '.$newLog.'.');
					}
				}
			}
		} // rotate_logs()
        
        function getMemoryUsage()
        {
            $size = memory_get_usage(true);
            $unit = array('b','kb','mb','gb','tb','pb');
            return "Memory Usage: ".@round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
        }
		
	}
	
?>
