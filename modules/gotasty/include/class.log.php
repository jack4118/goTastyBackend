<?php
    
class Log{
	
	private static $maxLogSize = 10000000; // The log file size limit in bytes.
	private static $backupLogs = 2; // The number of backup logs to keep.
	
	public static $logFilePath;
	public static $logFileName;
	public static $logFile;
	// $path_arg = full path.. "/var/www/html/"
	// $name_arg = file name.. "process-A"
	// function __construct($path_arg=NULL, $name_arg=NULL){
	// }
	
	function setupLogPath($path_arg=NULL, $name_arg=NULL){

		if (is_null($path_arg) || ($path_arg === '')) return;
		if (is_null($name_arg) || ($name_arg === '')) return;
		
		$name_arg = basename($name_arg, '.php');
		$path_arg = $path_arg . '/log/';

		$path = realpath($path_arg);
		// make sure the dir is writable
		if (!is_dir($path) || !is_writable($path)) {
			throw new Exception('Log constructor failed.  Path ' . $path . ' is not writable.');
		}
		
		self::$logFilePath = $path;
		self::$logFileName = $name_arg;
		
		// make sure there isn't already a non-writable file with our filename
		self::$logFile = self::getLogFileName();
	}
	
	function getLogFileName($logNumber=0) {
		if ($logNumber == 0) {
			return self::$logFilePath . DIRECTORY_SEPARATOR . self::$logFileName . '.log';
		} else {
			return self::$logFilePath . DIRECTORY_SEPARATOR . self::$logFileName . '.' . trim($logNumber) . '.log';
		}
	} // getLogFileName
	
	function write($msg){
		
		$msgLength = strlen($msg) + 1;
		
		// sanity check...don't want to go writing huge files
		if ($msgLength > self::$maxLogSize) {
			throw new Exception('Writing log failed.  A single message was written that exceeds the maximum log size of '.self::$maxLogSize);
			return FALSE;
		}
		
		// check if we will pass the max log size.  if so, rotate logs
		if (file_exists(self::$logFile)) {
			if ((filesize(self::$logFile) + strlen($msg) + 1) > self::$maxLogSize) {
				self::rotateLogs();
			}
		}
		
		// write the log
		$result = file_put_contents(self::$logFile, $msg, FILE_APPEND | LOCK_EX);
		// clear the cached file
		clearstatcache();
		
		if ($result === FAlSE) {
			return FALSE;
		} else {
			return TRUE;
		}
		
	} // write
	
	
	function rotateLogs(){
		
		// delete the highest log file if it exists
		$lastLog = self::getLogFileName(self::$backupLogs);
		if (file_exists($lastLog)) {
			unlink($lastLog);
		}
		
		// rotate the other logs
		for($i=self::$backupLogs; $i>0; $i--) {
			$newLog = self::getLogFileName($i);
			$oldLog = self::getLogFileName($i-1);
			if (file_exists($oldLog)) {
				if (!rename($oldLog, $newLog)) {
					throw new Exception('Rotate logs failed. Failure renaming '.$oldLog.' to '.$newLog.'.');
				}
			}
		}
	} // rotate_logs
    
    function getMemoryUsage(){

        $size = memory_get_usage(true);
        $unit = array('b','kb','mb','gb','tb','pb');
        return "Memory Usage: ".@round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
	
}

?>