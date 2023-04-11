<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for System..
     * Date  11/07/2017.
    **/

    class System
    {

        function __construct($db, $general, $setting)
        {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }
        
        /**
         * Function for getting the System List.
         * @param $systemParams.
         * @author Rakesh.
        **/
        public function getSystemList($systemParams)
        {
            $db = $this->db;
            $general = $this->general;
            $setting = $this->setting;

            $decimalPlaces = $setting->getSystemDecimalPlaces();

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $summary = $db->get('server_status_summary');
            if(empty($summary))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");

            foreach($summary as $value) {
                $server[$value['id']]['server_name'] = $value['server_name'];
                $server[$value['id']]['type'] = $value['type'];
                $server[$value['id']]['server_ip'] = $value['server_ip'];
                $server[$value['id']]['total_cpu'] = $value['total_cpu'];
                $server[$value['id']]['total_memory'] = $value['total_memory'];
                $server[$value['id']]['total_swap'] = $value['total_swap'];
                $server[$value['id']]['disk_size'] = $value['disk_size'];
            }

            $datetime = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $db->where("created_at", $datetime, ">=");
            $db->groupBy("server_id");
            $result = $db->get("server_status_data", null, "server_id, AVG(cpu_load) AS cpu_load, AVG(cpu_idle) AS cpu_idle, AVG(memory_used) AS memory_used, AVG(swap_used) AS swap_used, AVG(disk_used) AS disk_used");
            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            
            foreach($result as $value) {
                $system['id']           = $value['server_id'];
                $system['server_name']  = $server[$value['server_id']]['server_name'];
                $system['server_ip']    = $server[$value['server_id']]['server_ip'];
                $system['type']         = $server[$value['server_id']]['type'];
                $system['total_cpu']    = $server[$value['server_id']]['total_cpu'];
                $system['cpu_load']     = number_format($value['cpu_load'], $decimalPlaces, ".", "")."%";
                $system['total_memory'] = $server[$value['server_id']]['total_memory']."M";

                $system['memory_usage'] = ($value['memory_used']/$server[$value['server_id']]['total_memory'])*100;
                $system['memory_usage'] = number_format($system['memory_usage'], $decimalPlaces, ".", "")."%";

                $system['total_swap'] = $server[$value['server_id']]['total_swap']."M";

                $system['swap_usage'] = ($value['swap_used']/$server[$value['server_id']]['total_swap'])*100;
                $system['swap_usage'] = number_format($system['swap_usage'], $decimalPlaces, ".", "")."%";

                $system['disk_size'] = $server[$value['server_id']]['disk_size'];
                $system['disk_usage'] = ($value['disk_used']/$server[$value['server_id']]['disk_size'])*100;
                $system['disk_usage'] = number_format($system['disk_usage'], $decimalPlaces, ".", "")."%";

                $systemList[] = $system;
            }

            $totalRecord = $db->getValue("server_status_summary", "COUNT(*)");
            $data['totalPage']    = ceil($totalRecord/$limit[1]);
            $data['pageNumber']   = $pageNumber;
            $data['totalRecord']  = $totalRecord;
            $data['numRecord']    = $limit[1];
            $data['apiData']      = $systemList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        /**
         * Function for getting the System data in the Edit.
         * @param $systemParams.
         * @author Rakesh.
        **/
        public function getSystemData($systemParams)
        {
            $db = $this->db;
            
            $id = trim($systemParams['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select System", 'data'=> '');

            $id = $db->escape($id);
            $db->where('id', $id);
            $result = $db->getOne("server_status_summary");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid System", 'data' => "");

            
            $result['total_memory'] = $result['total_memory']."M";
            $result['total_swap'] = $result['total_swap']."M";

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
        }
    }

?>
