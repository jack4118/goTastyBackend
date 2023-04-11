<?php

    /**
     * Script to process all the messages in message out table and send to recipient based on the type
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.phpmailer.php');
    include($currentPath.'/../include/class.smtp.php');
    include($currentPath.'/../include/class.pop3.php');
    include($currentPath.'/../include/class.notification.php');
    include($currentPath.'/../include/class.process.php');
    include($currentPath.'/../include/class.provider.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.log.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $pdb = new MysqliDb($config['dBHost'], $config['processUser'], $config['processPassword'], $config['dB']);
    $log = new Log($logPath, $logBaseName);
    $setting = new Setting($db);
    $mail = new PHPMailer();
    $notification = new Notification($mail);
    $provider = new Provider($db);
    $message = new Message($db, '', $provider);
    $process = new Process($pdb, $setting, $log);
    
    
    $processName = "";
    if(strlen($argv[1]) > 0)  
    $processName = $argv[1];
    $limit = (strlen($argv[2]) > 0) ? $argv[2] : 5; // Limit records
    $sleepTime = (strlen($argv[3]) > 0) ? $argv[3] : 2; // Sleep time

    while(1)
    {
            
        unset($processEnable);

        $processEnable = $setting->systemSetting['processOutGoingEnableFlag'];

        $log->write(date("Y-m-d H:i:s")." Enable Flag is: ".$processEnable.".\n");

        // 1. CHECK PROCESS ENABLE
        if($processEnable == 1)
        {
            
            //2. CHECK IN THE PROCESS - insert on duplicate key update to system_status
            $process->checkin('');
            
            // 3.GET PROVIDER DETAILS - for API calls 
            $providerArray = $provider->getProvider();

            // 4. check message_out, get asinged or assign  
            #getAssignedMessages       
            $results = $message->getAssignedMessages($processName, $limit);
                
            if(count($results) > 0)
            {
                // Send the notifications - to already assigned messages

                $log->write(date("Y-m-d H:i:s")." Retrieved ".count($results). " assigned messages.\n");
                    
                foreach($results as $result)
                {
                    unset($fileArray);
                    $error = $result['error_count'];
                    $to = $result['recipient'];
                    $subject = $result['subject'];
                    $text = $result['content'];
                    $data = "";
                    $errorData = "";
                    $sent = 0;
                    $sentID = $result['sent_history_id'];
                    $sentHistoryTable = $result['sent_history_table'];

                    switch ($result['is_attachment']) {
                        case '1':
                            $fileArray = array("Katalog.pdf");
                            break;
                        
                        case '2':

                            $db->where("isActive","1");
                            $pdfFile=$db->getOne('pdffile','pdfname');
                            $fileArray = array($pdfFile);
                            break;

                        case '3':
                            $fileArray = array("Katalog.pdf", "BusinessBasic.pdf");
                            break;
                    }

                    switch ($result['type'])
                    {
                        case 'email':
                            
                            // SMTP
                            // Send the email and check for errors
                            $response = $notification->sendEmailsUsingSMTP($to, $subject, $text, $providerArray[$result['type']], $fileArray);
                            
                            if ($response)
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'], 'processor' => $processName, 'error_code' => '', 'error_description' => $response);
                            }
                            
                            break;
                            
                        case 'mail':
                            
                            // IsMail
                            // Send the email and check for errors
                            $response = $notification->sendEmailsUsingSendmail($to, $subject, $text, $providerArray[$result['type']]);
                            
                            if ($response)
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'], 'processor' => $processName, 'error_code' => '', 'error_description' => "Mail sending failed.");
                            }
                            
                            break;
                            
                        case 'phone':
                            
                            // Send the sms and check for errors
                            $response = $notification->sendSMS($to, $text,$providerArray[$result['type']]);
                            $xml = simplexml_load_string($response);
                            $msgCode = (string)$xml->statusCode;
                            $msg = (string)$xml->statusMsg;
                            
                            //if success sent
                            if ($msgCode == '1606')
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'],'processor' => $processName, 'error_code' => $msgCode, 'error_description' => $msg);
                            }
                            
                            break;
                            
                        case 'xun':
                        case 'xun2':
                            
                            $xunNumber = array();
                            
                            $setNumber = '+'.$to;
                            
                            array_push($xunNumber, $setNumber);
                            $response = $notification->sendXun($xunNumber, $text, $subject, $providerArray[$result['type']]);
                            
                            $code = $response['code'];
                            $xunMsg = $response['message_d'];
                            
                            if ($code == 1)
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'],'processor' => $processName, 'error_code' => $code , 'error_description' => $xunMsg);
                            }
                            
                            break;
                    
                    
                    }

                    $log->write(date("Y-m-d H:i:s")." $processName ".$result['type']." ".($sent == 1 ? "successfully" : "failed to")." sent to ".$to." \n");

                    $message->updateMessages($sent, $result['id'], $data, $errorData, $error, $sentID, $sentHistoryTable);

                }


            }
            else
            {
                
                $log->write(date("Y-m-d H:i:s")." Attempting to assign up to $limit record(s).\n");
                
                //Assign messges to the current process, update the processor = $processName
                $assignedCount = $message->assignMessages($processName, $limit);
                
                $log->write(date("Y-m-d H:i:s")." Assigned $assignedCount messages.\n");
                
            }

            $log->write(date("Y-m-d H:i:s")." The process is going to sleep for: ". $sleepTime. "second(s)\n");
            
            sleep($sleepTime);
                
        }
        else
        {
            
            $log->write(date("Y-m-d H:i:s")." Process :".$processName ." has been disabled. Do nothing.\n");
            
            sleep($sleepTime);
        }


    }

?>
