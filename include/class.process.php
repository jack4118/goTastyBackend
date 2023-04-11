<?php
    
    /**
     * Process Class:
     * Used for managing processes status/updates
     * Process User requires special privilege in the database to set binary logging to 0/1
     * Create a db user with the SUPER privilege and permissions on related database
     */
    
    class Process
    {
        
        function __construct($db, $setting, $log)
        {
            $this->db = $db;
            $this->setting = $db;
            $this->log = $log;
        }
        
        public function checkIn($processName)
        {
            $db = $this->db;
            $log = $this->log;
            
            // Skip writing to binary log to prevent it from getting too large
            $db->rawQuery("SET sql_log_bin = 0");
            
            $data = array(
                          "process_name" => $processName,
                          "updated_at" => date("Y-m-d H:i:s")
                          );
            $updateColumns = array("updated_at");
            $lastInsertId = "id";
            $db->onDuplicate($updateColumns, $lastInsertId);
            $db->insert('system_status', $data);
            
            // After updating status, set the binary log back to 1
            $db->rawQuery("SET sql_log_bin = 1");
            
            $log->write(date("Y-m-d H:i:s")." Process: $processName check in.\n");
            
        }
        
        public function getEmailSubject()
        {
            global $db;
            $db->where('name','email_subject');
            $email_subject 	= $db->getOne("system_settings");
            $email_subject 	= $email_subject['value'];
            echo date("Y-m-d H:i:s").' '. "eamil_subject: ". $email_subject .'\n';
            return $email_subject;
        }
        
        
    }
    
?>
