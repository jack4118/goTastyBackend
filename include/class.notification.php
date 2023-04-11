<?php
    
    class Notification
    {
        
        function __construct($mail)
        {
            $this->mail = $mail;
        }
        
        function sendEmailsUsingSendmail($to, $subject, $content, $providerInfo)
        {
            
            $mail = $this->mail;
            
            $mail->isMail();
            $mail->Subject = $subject;
            $mail->addAddress($to);
            $mail->msgHTML($content);
            
            // Set sender information
            $mail->From = $providerInfo['username'];
            $mail->FromName = $providerInfo['company'];
            
            if ($this->emailReply) $mail->addReplyTo($this->emailReply, $this->emailReply);
            
            $mailsender = $mail->send();
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            
            return $mailsender;
            
        }
        
        function sendEmailsUsingSMTP($to, $subject, $content, $providerInfo, $fileAry)
        {
            $mail = $this->mail;
            
            $mail->isSMTP();
            $mail->Subject = $subject;
            $mail->addAddress($to);
            $mail->msgHTML($content);

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Authentication section
            $mail->Username = $providerInfo['username'];
            $mail->Password = $providerInfo['password'];
            
            // Set sender information
            $mail->From = $providerInfo['username'];
            $mail->FromName = $providerInfo['company'];
            
            if ($this->emailReply) $mail->addReplyTo($this->emailReply, $this->emailReply);

            if(!empty($fileAry)){
                foreach($fileAry as $row){

                    $db= MysqliDb::getInstance();
                    $db->where("pdfname",$row["pdfname"]);
                    $db->where("isActive","1");
                    $pdfPath=$db->get('pdffile',null,'path');
                    
                    foreach($pdfPath as $row){
                        $file= file_get_contents($row["path"]);
                        $mail->AddStringAttachment($file,"BusinessBasic.pdf");
                    }

                }
            }
            
            // $mail->Sender = "";
            $mailsender = $mail->send();
            $mail->clearAttachments();
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            
            return $mailsender? true : $mail->ErrorInfo;
            
        }
        
        
        function sendSMS($recipient, $text, $providerInfo)
        {   

            $post_data = array (
                                            'email' => $providerInfo['username'],
                                            'key' => $providerInfo['api_key'],
                                            'recipient' => $recipient,
                                            'message' => $text,
                                        );
            $URL = $providerInfo['url1']."email=".$providerInfo['username']."&key=".$providerInfo['api_key']."&recipient=".$recipient."&message=".urlencode($text);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return $response;
        }
        
        function sendXun($xunNumber, $message, $subject, $providerInfo)
        {
            global $db, $msgpack;
            
            $url = $providerInfo['url1'];
            $fields = array("api_key" => $providerInfo['api_key'],
                            "business_id" => $providerInfo['username'],
                            "message" => $message,
                            "tag" => $subject,
                            "mobile_list" => $xunNumber
                            );
            
            $dataString = json_encode($fields);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                                   'Content-Type: application/json',
                                                   'Content-Length: ' . strlen($dataString))
                       );
        
            $response = curl_exec($ch);
            curl_close($ch);
            
            return json_decode($response, true);
        }
        
    }
    
    ?>
