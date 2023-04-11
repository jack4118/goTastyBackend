<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for System JournalTables..
     * Date  31/07/2017.
    **/

    class Journals {
        
        function __construct($db, $general) {
            $this->db = $db;
            $this->general = $general;
        }
        
        /**
         * Function for getting the JournalTables List.
         * @param $journalTableParams.
         * @author Rakesh.
        **/
        public function getJournalTablesList($journalTableParams) {
            $db = $this->db;
            $general = $this->general;
            $pageNumber = $journalTableParams['pageNumber'] ? $journalTableParams['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $journalTableParams['searchData'];
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                        case 'name':
                            $db->where('table_name', $dataValue);
                            
                            break;
                            
                        case 'type':
                            $db->where('type', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("journal_tables", $limit);
            
            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("journal_tables", "count(id)");
                foreach($result as $value) {

                    $journals['id']         = $value['id'];
                    $journals['tableName']  = $value['table_name'];
                    $journals['type']       = $value['type'];

                    $journalsList[] = $journals;
                }

                $data['journalsList'] = $journalsList;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New JournalTables.
         * @param $journalTableParams.
         * @author Rakesh.
        **/
        function newJournalTable($journalTableParams) {
            $db = $this->db;

            $tableName = trim($journalTableParams['tableName']);
            $tableType = trim($journalTableParams['type']);
            $disabled  = trim($journalTableParams['disabled']);

            if(strlen($tableName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($tableType) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Disabled.", 'data'=>"");

            $fields     = array("table_name", "type", "disabled", "created_at");
            $values     = array($tableName, $tableType, $disabled, date("Y-m-d H:i:s"));
            $arrayData  = array_combine($fields, $values);

            $result = $db->insert("journal_tables", $arrayData);
            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "JournalTable Successfully Saved"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid JournalTable", 'data'=>"");
            }
        }

        /**
         * Function for adding the Updating the JournalTable.
         * @param $journalTableParams.
         * @author Rakesh.
        **/
        public function editJournalTableData($journalTableParams) {
            $db = $this->db;

            $id         = trim($journalTableParams['id']);
            $type       = trim($journalTableParams['type']);
            $disabled   = trim($journalTableParams['disabled']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please select a record.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Disabled.", 'data'=>"");

            $fields     = array("type", "disabled", "updated_at");
            $values     = array($type, $disabled, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("journal_tables", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "JournalTable Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid JournalTable", 'data'=>"");
            }
        }

        /**
         * Function for deleting the JournalTable.
         * @param $journalTableParams.
         * @author Rakesh.
        **/
        function deleteJournalTables($journalTableParams) {
            $db = $this->db;

            $id = trim($journalTableParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select JournalTable", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('journal_tables', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('journal_tables');
                if($result) {
                    return $this->getJournalTablesList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid JournalTable", 'data'=>"");
            }
        }

        /**
         * Function for getting the JournalTable data in the Edit.
         * @param $journalTableParams.
         * @author Rakesh.
        **/
        public function getJournalTableData($journalTableParams) {
            $db = $this->db;
            $id = trim($journalTableParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select JournalTable", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("journal_tables");
            
            if (!empty($result)) {
                $journals['id']            = $result["id"];
                $journals['table_name']    = $result["table_name"];
                $journals['type']          = $result["type"];
                $journals['disabled']      = $result["disabled"];
                
                $data['journalsTable'] = $journals;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid JournalTable", 'data'=>"");
            }
        }

        /**
         * Function for getting the JournalTables List.
         * @param NULL.
         * @author Rakesh.
        **/
        public function getJournalTableNames($tableParams) {
            $db = $this->db;

            $dbName = $tableParams['dbName'];
            if(strlen($dbName) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No results found.", 'data'=>"");

            $journalTables = $db->rawQuery("SELECT table_name FROM information_schema.tables WHERE table_type='BASE TABLE' AND table_schema='$dbName' AND table_name NOT LIKE '%_audit%';");

            if(empty($journalTables))
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No journal tables found.", 'data'=>"");

            // Perform checking to determine is it daily table
            $dailyTables = array();
            foreach($journalTables as $v) {
                if(strpos($v['table_name'], '_') === FALSE)
                    continue;

                $tableArr = explode('_', $v['table_name']);
                $tableLastWord = $tableArr[count($tableArr) - 1];
                if(ctype_digit($tableLastWord)) {
                    if(strtotime($tableLastWord) > 0) {
                        array_pop($tableArr);
                        $dailyTables[] = implode('_', $tableArr);
                    }
                }
            }

            $dailyTables = array_unique($dailyTables);
            // Filter out daily tables
            if(!empty($dailyTables)) {
                foreach($journalTables as $v) {
                    $isDailyTable = FALSE;
                    foreach($dailyTables as $val) {
                        if(strpos($v['table_name'], $val) !== FALSE) {
                            $isDailyTable = TRUE;
                            continue;
                        }
                    }
                    if(!$isDailyTable)
                        $journalTableList[] = $v['table_name'];
                }
            }
            else
                $journalTableList = $journalTables;

            // if($tableParams["action"] == "edit") {
            //     if (!empty($journalTableList)) {
            //         $journals['tableName'] = $journalTableList;

            //         $data['journalNames'] = $journals;
                    
            //         return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                    
            //     } else {
            //         return array('status' => "error", 'code' => '1', 'statusMsg' => "No results found.", 'data'=>"");
            //     }
            // } else if($tableParams["action"] == "add") {
                $savedTables = $db->get("journal_tables", null, 'table_name');
                if (!empty($savedTables)) {
                    $cmpTable = $journalTableList;

                    foreach($savedTables as $jTable){
                        $cmpTable2[] = $jTable['table_name'];
                    }

                    $uniqueTables = array_diff($cmpTable,$cmpTable2);
                    if (!empty($uniqueTables)) {
                        $journals['tableName'] = $uniqueTables;
                        
                        $data['journalNames'] = $journals;
                        
                        return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                    } else {
                        return array('status' => "error", 'code' => '1', 'statusMsg' => "No results found.", 'data'=>"");
                    }
                } else {
                    $journals['tableName'] = $journalTableList;
                        
                    $data['journalNames'] = $journals;
                        
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                }
            // }
        }
    }

?>
