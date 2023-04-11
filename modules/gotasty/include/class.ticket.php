<?php 

    class Ticket {
        
        function __construct() {
            // $this->db = $db;
            // $this->setting = $setting;
            // $this->general = $general;
            // $this->otp     = $otp;
        }

        public function generateTicketNo() {
            $db = MysqliDb::getInstance();

            // Get ticket no length setting
            $ticketNoLength = Setting::$systemSetting['TicketNoLength'] ? Setting::$systemSetting['TicketNoLength'] : 10;

            $min = "1"; $max = "9";
            for($i = 1; $i < $ticketNoLength; $i++)
                $max .= "9";

            $ticketsNo = $db->getValue('mlm_ticket', 'ticket_no', null);

            // Randomise a ticketNo
            if(count($usedTicketNo) > 0) {
                while (1) {
                    $count++;

                    $ticketNo = sprintf("%0".$ticketNoLength."s", mt_rand((int)$min, (int)$max));

                    // If randomed number is valid, we use it
                    if(!in_array($ticketNo, $usedTicketNo))
                        break;

                    //print_r("Infinity loop detected.\n");
                    if($count > 9999999999)
                        break;
                }
            }
            else {
                $ticketNo = sprintf("%0".$ticketNoLength."s", mt_rand((int)$min, (int)$max));
            }

            return $ticketNo;
        }

        public function addTicket($params, $site, $types) {
            
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $dateTime = date('Y-m-d H:i:s');
            $imgTypeAry = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff');
            $subject = $params['subject'];
            $message = $params['message'];
            $type = $params['type'];

            $imageFlag = '0';
            $userID = $db->userID;

            if($types == 'ticket' || $types == "fiatTicket"){

                $clientID = $params['clientID'];
                if($site == 'Member') $clientID = $userID;
                $receiverID = ($params['receiverID'] != "" ? $params['receiverID'] : 0);

                if(empty($clientID)){

                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter client ID", 'data' => "");
                }
            }

            if($types == 'publicTicket'){

                $email = $params['email'];
                $name = $params['name'];
                $phone = $params['phone'];
                $type = 'public';

                if(empty($email)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter email", 'data' => "");
                }

                if(empty($name)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter name", 'data' => "");
                }

                if(empty($phone)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Please enter phone", 'data' => "");
                }
            }

            foreach ($params['uploadData'] as $uploadData) {

                if ($uploadData['imageFlag'] == 1) {
                    
                    if (empty($uploadData['imageType']) || empty($uploadData['imageName']) || (!in_array($uploadData['imageType'], $imgTypeAry))) {

                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language] /* Uploaded file is not a valid image. */, 'data' => "");

                    }
            
                    $imageFlag = '1';
                
                }
            
            }

            $ticketNo = self::generateTicketNo();

            if($types == 'publicTicket'){

                $insert = array (
                    'ticket_no' => $ticketNo,
                    'subject' => $subject,
                    'status' => "Open",
                    'created_at' => $dateTime,
                    'updated_at' => $dateTime,
                    'creator_id' => $userID,
                    'creator_type' => "Public",
                    'member_unread' => 1,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'type' => $type
                );

            }else{

                $insert = array (
                    'ticket_no' => $ticketNo,
                    'subject' => $subject,
                    'status' => "Open",
                    'created_at' => $dateTime,
                    'updated_at' => $dateTime,
                    'creator_id' => $clientID,
                    'creator_type' => $site,
                    'member_unread' => 1,
                    'receiver_id' => $receiverID,
                    'type' => $type
                );
            }        

            $id = $db->insert('mlm_ticket', $insert); 

            if(empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00619"][$language] /* Failed to submit message. */, 'data' => "");

            if($imageFlag == '1') {

                if(strlen($message)) {

                    $insert = array (
                        'ticket_id' => $id,
                        'sender_id' => $clientID,
                        'sender_type' => $site,
                        'message' => $message,
                        'created_at' => $dateTime
                    );

                }

                else {

                    $insert = array (
                        'ticket_id' => $id,
                        'sender_id' => $clientID,
                        'sender_type' => $site,
                        'message' => "",
                        'created_at' => $dateTime
                    );

                }

            }

            else {

                if($types == 'publicTicket'){
                    $insert = array (
                        'ticket_id' => $id,
                        'sender_id' => $userID,
                        'sender_type' => "Public",
                        'message' => $message,
                        'created_at' => $dateTime
                    );

                }else{

                    $insert = array (
                        'ticket_id' => $id,
                        'sender_id' => $clientID,
                        'sender_type' => $site,
                        'message' => $message,
                        'created_at' => $dateTime
                    );
                }

            }

            $ticketsID = $id;
            $sendersID = $clientID;

            $id = $db->insert('mlm_ticket_details', $insert);

            if(empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00620"][$language] /* Failed to submit. */, 'data' => "");

            $count = 1;
            $updateImgData = array();

            $groupCode = General::generateUniqueChar("mlm_ticket_details","image_name");

            foreach ($params['uploadData'] as $uploadData) {
                if ($uploadData['imageFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['imageName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_ticket_details","image_name")."_".$groupCode.".".$fileType;

                    if ($count == 1) {
                        $updateImgData['image_name'] = $upload_name;
                    } else {
                        $updateImgData['image_name_'.$count] = $upload_name;
                    }
                }

                $count++;
            }

            if ($updateImgData) {

                $db->where('ticket_id', $ticketsID);
                $db->where('sender_id', $sendersID);
                $db->orderBy('id', 'DESC');
                $db->update('mlm_ticket_details', $updateImgData);

                $data['uploadData'] = $updateImgData;

                $data["doRegion"] = Setting::$configArray["doRegion"];
                $data["doEndpoint"] = Setting::$configArray["doEndpoint"];
                $data["doAccessKey"] = Setting::$configArray["doApiKey"];
                $data["doSecretKey"] = Setting::$configArray["doSecretKey"];
                $data["doBucketName"] = Setting::$configArray["doBucketName"];
                $data["doProjectName"] = Setting::$configArray["doProjectName"];
                $data["doFolderName"] = Setting::$configArray["doFolderName"];
            }

            // get ticket id
            $db->where('ticket_no', $ticketNo);
            $ticketID = $db->getValue('mlm_ticket', 'id');
            $data["ticketID"] = $ticketID;

            if($type == 'support'){
                // send sms notification
                $sendAdminNotificationData = array(
                    "notificationUserType" => "ticketAndWithdrawal",
                    "messageType" => "ticket",
                );
                // Otp::sendAdminNotification($sendAdminNotificationData);
            }


            if($types == 'publicTicket'){

                    // send sms notification
                    $sendAdminNotificationData = array(
                        "notificationUserType" => "ticketAndWithdrawal",
                        "messageType" => "ticket",
                    );
                    // Otp::sendAdminNotification($sendAdminNotificationData);
            }
            
            if($site=="Member"){
                General::insertNotification("ticket");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00369"][$language] /* Successfully update ticket. */, 'data' => $data, 'ticketID' => $ticketID);
        }

        public function getInboxListing($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];
            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00622"][$language] /* Failed to load inbox listing. */, 'data' => "");


            $db->where('creator_id', $clientID);
            $db->orWhere('receiver_id', $clientID);
            $db->orderBy('updated_at', 'Desc');
            $getUsername = "(SELECT username FROM client WHERE client.id=creator_id) AS username";
            $getMessage = "(SELECT message FROM mlm_ticket_details WHERE ticket_id=mlm_ticket.id ORDER BY created_at DESC LIMIT 1) AS message";
            $getMessage .= ",(SELECT sender_id FROM mlm_ticket_details WHERE ticket_id=mlm_ticket.id ORDER BY created_at DESC LIMIT 1) AS sender_id";
            // $getSenderUsername = "IF(((SELECT sender_type FROM mlm_ticket_details WHERE ticket_id=mlm_ticket.id ORDER BY created_at DESC LIMIT 1)='Admin'), 'Admin', (SELECT username FROM client WHERE client.id=sender_id)) AS sender_username";
            $getUnreadMessage = "(SELECT COUNT(*) FROM mlm_ticket_details WHERE ticket_id=mlm_ticket.id AND `read` = 0 AND sender_id != '".$clientID."') AS unreadMessage";
            
            $result = $db->get('mlm_ticket', null, $getUsername.', id, updated_at, subject,creator_type, '.$getMessage.', '.$getUnreadMessage);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00623"][$language] /* No inbox found. */, 'data' => "");

            foreach($result as $key => $value) {
                $now = new DateTime("now");
                $inboxDatetime = new DateTime($value['updated_at']);
                $interval = $now->diff($inboxDatetime);

                if($interval->y >= 1)
                    $timeDiff = $interval->y." ".$translations['M02742'][$language] /* Year ago */;
                elseif($interval->m >= 1)
                    $timeDiff = $interval->m." ".$translations['M02743'][$language] /* Month ago */;
                elseif($interval->d >= 1)
                    $timeDiff = $interval->d." ".$translations['M02744'][$language] /* Day ago */;
                elseif($interval->h >= 1)
                    $timeDiff = $interval->h." ".$translations['M02745'][$language] /* Hour ago */;
                elseif($interval->i >= 1 )
                    $timeDiff = $interval->i." ".$translations['M02746'][$language] /* Minute ago */;
                else
                    $timeDiff = $translations['M02747'][$language] /* Just now */;
                
                $db->where('ticket_id',$value['id']);
                  $db->orderBy('id', 'DESC');
                $lastMessage = $db->get('mlm_ticket_details','1','sender_id,sender_type');
                foreach ($lastMessage as  $lastMessageValue) {
                    if($lastMessageValue["sender_type"] == "Admin"){
                        $value['sender_username'] = "Admin";
                    }else{
                        $db->where('id',$lastMessageValue['sender_id']);
                        $value['sender_username'] = $db->getValue('client','username');
                    }
                }

                $inbox['id'] = $value['id'];
                $inbox['username'] = $value['username'];
                $inbox['time_different'] = $timeDiff;
                $inbox['subject'] = $value['subject'];
                $inbox['lastMessage'] = $value['message'];
                $inbox['lastSender'] = $value['sender_username'];
                $inbox['unreadMessage'] = $value['unreadMessage'];
                $inboxListing[] = $inbox;
            }

            $data['inboxListing'] = $inboxListing;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getInboxMessages($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $inboxID = $params['inboxID'];
            $clientID = $params['clientID'];

            if(empty($inboxID) || empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00624"][$language] /* Failed to load messages. */, 'data' => "");

            $db->where('ticket_id', $inboxID);
            $db->orderBy('created_at', 'asc');
            $getSubject = "(SElECT subject FROM mlm_ticket WHERE mlm_ticket.id=ticket_id LIMIT 1) AS subject";
            $result = $db->get('mlm_ticket_details', null, 'sender_id, sender_type, message, image_name, created_at, '.$getSubject);
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00624"][$language] /* Failed to load messages. */, 'data' => "");

            foreach($result as $value) {
                if($value['sender_type'] == 'SuperAdmin')
                    $superAdminIDs[] = $value['sender_id'];
                else if($value['sender_type'] == 'Admin')
                    $adminIDs[] = $value['sender_id'];
                else if ($value['sender_type'] == 'Member')
                    $clientIDs[] = $value['sender_id'];
            }
            if(!empty($superAdminIDs)) {
                $db->where('id', $superAdminIDs, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $key => $value) {
                    // $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                    $usernameList['SuperAdmin'][$value['id']] = "Admin";
                }
            }
            if(!empty($adminIDs)) {
                $db->where('id', $adminIDs, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $key => $value) {
                    $usernameList['Admin'][$value['id']] = "Admin";
                    // $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($clientIDs)) {
                $db->where('id', $clientIDs, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $key => $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            foreach($result as $value) {
                if($value['sender_id'] == $clientID) {

                    $message['message_type'] = 'text';

                    if($value['image_name'] != '') {

                        $message['message_type'] = 'image';

                        if($value['message'] != '') {

                            $message['message_type'] = 'image/text';

                        }

                        // $db->where('id', $value['image_id']);
                        // $imgResult = $db->getValue('uploads', "data");

                        $message['base64Image'] = $value['image_name'];

                    }
                    
                    if(!$personMemberGroup){
                        unset($personAdminGroup);
                        $personMemberGroup = "Member";
                        $message['personGroup'] = "Member";
                    }

                    $message['personType'] = "Member";
                    $message['message'] = $value['message'];
                    $message['person'] = $translations['M00425'][$language];//"member";
                    $message['datetime'] = date($dateTimeFormat, strtotime($value['created_at']));

                } else {

                    $message['message_type'] = 'text';

                    if($value['image_name'] != '') {

                        $message['message_type'] = 'image';

                        if($value['message'] != '') {

                            $message['message_type'] = 'image/text';

                        }

                        // $db->where('id', $value['image_id']);
                        // $imgResult = $db->getValue('uploads', "data");

                        $message['base64Image'] = $value['image_name'];

                    }

                    if(!$personAdminGroup){
                        unset($personMemberGroup);
                        $personAdminGroup = "Admin";
                        $message['personGroup'] = "Admin";
                    }

                    $message['personType'] = "Admin";
                    $message['message'] = $value['message'];
                    $message['name'] = $usernameList[$value["sender_type"]][$value["sender_id"]];
                    $message['person'] = $translations['A01162'][$language];//"admin";
                    $message['datetime'] = date($dateTimeFormat, strtotime($value['created_at']));
                    
                }
                $messageList[] = $message;
                unset($message);
                $lastMessageDatetime = date($dateTimeFormat, strtotime($value['created_at']));
            }

            $db->where('id', $inboxID);
            $ticket = $db->getOne('mlm_ticket', 'subject, member_unread, status');
            if(empty($ticket))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00624"][$language] /* Failed to load messages. */, 'data' => "");

            if($ticket['member_unread'] == 1) {
                $insert = array(
                    'member_unread' => 0,
                    'updated_at' => $db->now()
                );

                $db->where('id', $inboxID);
                $db->update('mlm_ticket', $insert);
            }

            // update mlm_ticket_details `read` column to 1
            $db->where("`ticket_id`", $inboxID);
            $db->where("`sender_id`", $clientID, "!=");
            $db->where("`read`", 0);
            $unread = $db->getValue("`mlm_ticket_details`", "`id`", null);
            if(!empty($unread)){
                $update = array("read" => '1');

                $db->where("`id`", $unread, 'IN');
                $db->update("`mlm_ticket_details`", $update);
            }

            $data["inboxStatus"] = $ticket["status"];
            $data['messages'] = $messageList;
            $data['inboxSubject'] = $ticket['subject'];
            $data['lastMessageDatetime'] = $lastMessageDatetime;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function addInboxMessages($params, $site) {

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $dateTime = date('Y-m-d H:i:s');
            $imgTypeAry = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff');

            $inboxID = $params['inboxID'];
            $clientID = $params['clientID'];
            $message = $params['message'];

            // if(empty($inboxID) || empty($clientID) || empty($message))
            if(empty($inboxID) || empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00624"][$language] /* Failed to load messages. */, 'data' => "");

            // check if ticket status is Closed
            $db->where("id", $inboxID);
            $ticketStatus = $db->getValue("mlm_ticket", "status");
            if($ticketStatus == "Closed"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00728"][$language] /* This conversation is closed. */, 'data' => "");
            }

            $imageFlag = '0';

            foreach ($params['uploadData'] as $uploadData) {

                if ($uploadData['imageFlag'] == 1) {
                    
                    if (empty($uploadData['imageType']) || empty($uploadData['imageName']) || (!in_array($uploadData['imageType'], $imgTypeAry)))
            
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language] /* Uploaded file is not a valid image. */, 'data' => "");

                    $imageFlag = '1';
                
                }
            
            }

            if($imageFlag == '1') {

                if(strlen($message)) {

                    $insert = array (
                        'ticket_id' => $inboxID,
                        'sender_id' => $clientID,
                        'sender_type' => $site,
                        'message' => $message,
                        'created_at' => $dateTime
                    );

                }

                else {

                    $insert = array (
                        'ticket_id' => $inboxID,
                        'sender_id' => $clientID,
                        'sender_type' => $site,
                        'message' => $message,
                        'created_at' => $dateTime
                    );

                }

            }

            else {

                $insert = array (
                    'ticket_id' => $inboxID,
                    'sender_id' => $clientID,
                    'sender_type' => $site,
                    'message' => $message,
                    'created_at' => $dateTime
                );

            }

            $ticketsID = $inboxID;
            $sendersID = $clientID;

            $id = $db->insert('mlm_ticket_details', $insert);

            if(empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00624"][$language] /* Failed to load messages. */, 'data' => "");

            $count = 1;
            $updateImgData = array();
            $groupCode = General::generateUniqueChar("mlm_ticket_details","image_name");

            foreach ($params['uploadData'] as $uploadData) {
                if ($uploadData['imageFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['imageName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_ticket_details","image_name")."_".$groupCode.".".$fileType;

                    if ($count == 1) {
                        $updateImgData['image_name'] = $upload_name;
                    } else {
                        $updateImgData['image_name_'.$count] = $upload_name;
                    }
                }

                $count++;
            }

            if ($updateImgData) {

                $db->where('ticket_id', $ticketsID);
                $db->where('sender_id', $sendersID);
                $db->orderBy('id', 'DESC');
                $lastRowID = $db->getValue('mlm_ticket_details', 'id');

                $db->where('id', $lastRowID);
                $db->update('mlm_ticket_details', $updateImgData);

                $data['uploadData'] = $updateImgData;

                $data["doRegion"] = Setting::$configArray["doRegion"];
                $data["doEndpoint"] = Setting::$configArray["doEndpoint"];
                $data["doAccessKey"] = Setting::$configArray["doApiKey"];
                $data["doSecretKey"] = Setting::$configArray["doSecretKey"];
                $data["doBucketName"] = Setting::$configArray["doBucketName"];
                $data["doProjectName"] = Setting::$configArray["doProjectName"];
                $data["doFolderName"] = Setting::$configArray["doFolderName"];
            }

            // update mlm_ticket updated_at column
            $update = array('updated_at' => $dateTime);
            $db->where('id', $inboxID);
            $db->update('mlm_ticket', $update);

            $data['inboxID'] = $inboxID;

            if($site=="Member"){
                General::insertNotification("ticket");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getTicketList($params, $userID, $site) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_ticket";
            $searchData     = $params['searchData'];
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $column         = array(
                "id",
                "ticket_no",
                "subject",
                "status",
                // "(SELECT username FROM client WHERE id = creator_id) AS username",
                "(SELECT name FROM client WHERE id = creator_id) AS name",
                "member_unread",
                "created_at",
                "updated_at",
                "(SELECT COUNT(*) FROM mlm_ticket_details WHERE ticket_id = mlm_ticket.id AND `read` = 0 AND sender_id != '".$userID."') AS unreadMessage",
                "(SELECT COUNT(*) FROM mlm_ticket_details WHERE ticket_id = mlm_ticket.id) AS totalMessage",
                "name",
                "phone",
                "email",
                "creator_type",
                "creator_id",
            );

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'ticketNo':
                            if($dataValue != ""){
                                $db->where('ticket_no', $dataValue);
                            }
                            break;

                        case 'username':
                            if($dataValue != ""){
                                // $clientUsernameID = $db->subQuery();
                                // $clientUsernameID->where('username', $dataValue);
                                // $clientUsernameID->getOne('client', "id");
                                // $db->where('creator_id', $clientUsernameID);
                                // $db->orWhere('receiver_id', $clientUsernameID);
                            }
                            break;
                            
                        case 'subject':
                            if($dataValue != ""){
                                $db->where('subject', $dataValue);
                            }
                            break;
                            
                        case 'status':
                            if($dataValue != ""){
                                $db->where('status', $dataValue);
                            }
                            break;

                        case 'created':
                            if($dataValue != ""){
                                $db->where('status', $dataValue);
                            }
                            break;

                        case 'createdAt':
                            $columnName = 'created_at';
                            $dateFrom   = trim($v['tsFrom']);
                            $dateTo     = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'createdBy':
                            $db->where('creator_type', $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'username':
                            if($dataValue != ""){
                                $db->where('type', 'support');

                                $clientUsernameID = $db->subQuery();
                                $clientUsernameID->where('username', $dataValue);
                                $clientUsernameID->getOne('client', "id");
                                $db->where('creator_id', $clientUsernameID);

                                $clientUsernameID = $db->subQuery();
                                $clientUsernameID->where('username', $dataValue);
                                $clientUsernameID->getOne('client', "id");
                                $db->orWhere('receiver_id', $clientUsernameID);
                            }
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // $db->where('type', 'support');
            // $db->orderBy('totalMessage', 'DESC');
            // $copyDb = MysqliDb::getInstance();;
            // $ticketList = $db->get($tableName, $limit, $column);
            // $totalRecord = $copyDb->getValue($tableName, "count(*)");

            $db->where('type', array('support','public'), "IN");

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            } 
            
            $db->orderBy('updated_at', 'DESC');
            $ticketList = $db->get($tableName, $limit, $column);


            if (empty($ticketList))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00139"][$language] /* No result found */, 'data'=>"");

            foreach ($ticketList as $ticket) {
                if($ticket['creator_type'] != "Member" && $ticket['creator_type'] != "Public"){
                    $table = "admin";
                }else{
                     $table = "client";
                }

                $db->where("id", $ticket["creator_id"]);
                $ticket['username'] = $db->getValue($table, "username");


                if (!empty($ticket['id']))
                    $ticketListing['id']              = $ticket['id'];
                else
                    $ticketListing['id']              = "-";

                if (!empty($ticket['ticket_no']))
                    $ticketListing['ticketNo']              = $ticket['ticket_no'];
                else
                    $ticketListing['ticketNo']              = "-";

                if (!empty($ticket['subject']))
                    $ticketListing['subject']               = $ticket['subject'];
                else
                    $ticketListing['subject']               = "-";

                if (!empty($ticket['status']))
                    $ticketListing['status']                = $ticket['status'];
                else
                    $ticketListing['status']                = "-";

                if (!empty($ticket['username']))
                    $ticketListing['username']                  = $ticket['username'];
                else
                    $ticketListing['username']                  = "-";

                if (!empty($ticket['member_unread']))
                    $ticketListing['memberUnread']          = $ticket['member_unread'];
                else
                    $ticketListing['memberUnread']          = "-";

                if (!empty($ticket['created_at']))
                    $ticketListing['createdAt']             = General::formatDateTimeString($offsetSecs, $ticket['created_at'], $format = "Y-m-d H:i:s A");
                else
                    $ticketListing['createdAt']             = "-";

                if (!empty($ticket['updated_at']))
                    $ticketListing['updatedAt']             = General::formatDateTimeString($offsetSecs, $ticket['updated_at'], $format = "Y-m-d H:i:s A");
                else
                    $ticketListing['updatedAt']             = "-";

                if (!empty($ticket['unreadMessage']))
                    $ticketListing['unreadMessage']         = $ticket['unreadMessage'];
                else
                    $ticketListing['unreadMessage']         = "0";

                if (!empty($ticket['totalMessage']))
                    $ticketListing['totalMessage']          = $ticket['totalMessage'];
                else
                    $ticketListing['totalMessage']          = "-";

                if (!empty($ticket['creator_type']))
                    $ticketListing['creatorType']          = $ticket['creator_type'];
                else
                    $ticketListing['creatorType']          = "-";

                if (!empty($ticket['name']))
                    $ticketListing['fullName']          = $ticket['name'];
                else
                    $ticketListing['fullName']          = "-";

                if (!empty($ticket['phone']))
                    $ticketListing['phone']          = $ticket['phone'];
                else
                    $ticketListing['phone']          = "-";

                if (!empty($ticket['email']))
                    $ticketListing['email']          = $ticket['email'];
                else
                    $ticketListing['email']          = "-";

                $ticketPageListing[] = $ticketListing;
            }


            $data['ticketPageListing']          = $ticketPageListing;
            $data['totalPage']                  = ceil($totalRecord/$limit[1]);
            $data['pageNumber']                 = $pageNumber;
            $data['totalRecord']                = $totalRecord;
            $data['numRecord']                  = $limit[1];

            $db->where('admin_id',$userID);
            $db->where('type',"ticket");
            $db->update('admin_notification',array("notification_count"=>0));

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00140"][$language] /* Successfully retrieved ticket list. */, 'data'=> $data);
        }

        public function getTicketDetail($params, $userID) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_ticket_details";
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $ticketId       = trim($params['ticketId']);

            if (empty($ticketId) || !is_numeric($ticketId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data'=>"");

            // update mlm_ticket_details read column to 1
            $db->where("`ticket_id`", $ticketId);
            $db->where("`sender_id`", $userID, "!=");
            $db->where("`read`", 0);
            $unread = $db->getValue("`mlm_ticket_details`", "`id`", null);
            if(!empty($unread)){
                $update = array("read" => '1');

                $db->where("`id`", $unread, 'IN');
                $db->update("`mlm_ticket_details`", $update);
            }

            // get ticket data
            $column         = array(
                "ticket_no AS ticketNo",
                "creator_id AS clientID",
                "(SELECT member_id FROM client WHERE id = creator_id) AS memberID",
                "name AS fullName",
                "phone",
                "email",
                // "(SELECT name FROM client WHERE id = creator_id) AS username",
                "status",
                "creator_type",
            );

            $db->where("id", $ticketId);
            $res = $db->getOne("mlm_ticket", $column);
            $changeData = $res;
            $changeData["clientID"] = $changeData["memberID"] ? : '-';
            unset($changeData["memberID"]);
            unset($res["memberID"]);
            $data["ticketData"] = $changeData;
            
            if($res["creator_type"] == "Admin"){
                $table = "admin";
            }else{
                $table = "client";
            }

            $db->where("id", $res["clientID"]);
            $data["ticketData"]["username"] = $db->getValue($table, "username");

            $column2         = array(
                "sender_id",
                "sender_type",
                "(SELECT subject FROM mlm_ticket WHERE id = ticket_id) AS subject",
                "message",
                "image_name",
                "created_at",
            );

            $db->where("ticket_id", $ticketId);
            $db->orderBy("created_at", "DESC");
            $data["ticketDetail"] = $db->get($tableName, null, $column2);

            if (empty($data))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00141"][$language] /* No result found */, 'data'=>"");

            foreach($data["ticketDetail"] as $key => $value){
                if($value["sender_type"] == "Admin"){
                    $table = "admin";
                }else{
                    $table = "client";
                }

                $db->where("id", $value["sender_id"]);
                $data["ticketDetail"][$key]["senderName"] = $db->getValue($table, "username");

                // $db->where("id", $value["image_id"]);
                // $data["ticketDetail"][$key]["imageBased64"] = $db->getValue('uploads', 'data');
            }

            $db->where("id", $ticketId);
            $receiverID = $db->getValue("mlm_ticket", "receiver_id");

            $db->where("id", $receiverID);
            $data["ticketData"]["receiverName"] = $db->getValue("client", "username");
            $data["ticketData"]["receiverName"] = $data["ticketData"]["receiverName"] ? $data["ticketData"]["receiverName"] : $db->getValue("admin", "username");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00142"][$language] /* Successfully retrieved ticket detail. */, 'data'=> $data);
        }

        public function replyTicket($params,$site) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_ticket_details";
            $senderId       = trim($params['senderId']);
            $ticketId       = trim($params['ticketId']);
            $message        = trim($params['message']);

            $dateTime = date('Y-m-d H:i:s');
            $imgTypeAry = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff');

            if (empty($senderId) || !is_numeric($senderId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data'=>"");

            if (empty($ticketId) || !is_numeric($ticketId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data'=>"");

            // if (empty($message))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data'=>"");

            $imageFlag = '0';

            foreach ($params['uploadData'] as $uploadData) {

                if ($uploadData['imageFlag'] == 1) {
                    
                    if (empty($uploadData['imageType']) || empty($uploadData['imageName']) || (!in_array($uploadData['imageType'], $imgTypeAry)))
            
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00899"][$language] /* Uploaded file is not a valid image. */, 'data' => "");

                    $imageFlag = '1';
                
                }
            
            }

            // check if ticket status is Closed
            $db->where("id", $ticketId);
            $ticketStatus = $db->getValue("mlm_ticket", "status");
            if($ticketStatus == "Closed"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00728"][$language] /* This convertsation is closed */, 'data' => "");
            }

            $insertData = array(

                "ticket_id"         => $ticketId,
                "sender_id"         => $senderId,
                "sender_type"       => $site,
                "message"           => $message,
                "created_at"        => $dateTime
            );

            $id = $db->insert($tableName, $insertData);

            if (empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00272"][$language] /* Failed to reply ticket */, 'data'=>"");

            $count = 1;
            $updateImgData = array();
            $groupCode = General::generateUniqueChar("mlm_ticket_details","image_name");

            foreach ($params['uploadData'] as $uploadData) {
                if ($uploadData['imageFlag'] == 1) {

                    $fileType = end(explode(".", $uploadData['imageName']));
                    $upload_name = time()."_".General::generateUniqueChar("mlm_ticket_details","image_name")."_".$groupCode.".".$fileType;

                    if ($count == 1) {
                        $updateImgData['image_name'] = $upload_name;
                    } else {
                        $updateImgData['image_name_'.$count] = $upload_name;
                    }
                }

                $count++;
            }

            if ($updateImgData) {

                $db->where('ticket_id', $ticketId);
                $db->where('sender_id', $senderId);
                $db->orderBy('id', 'DESC');
                $lastRowID = $db->getValue('mlm_ticket_details', 'id');

                $db->where('id', $lastRowID);
                $db->update('mlm_ticket_details', $updateImgData);

                $data['uploadData'] = $updateImgData;
            }

            // update mlm_ticket updated_at column
            $update = array('updated_at' => $dateTime);
            $db->where('id', $ticketId);
            $db->update('mlm_ticket', $update);  

            $db->where('id', $ticketId);
            $ticketRes = $db->getOne("mlm_ticket", NULL, "email, subject, type");  

            $recipient = $ticketRes['email'];//recipient is email destination
            $sendType = 'email';

            if($ticketRes['type'] == 'public'){
                // send email
                $subject = $ticketRes['subject'];
                $content = $message;
                $result=Message::createCustomizeMessageOut($recipient,$subject,$content,$sendType,'','','','',1);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00143"][$language] /* ticket replied */, 'data'=>$data);
        }

        public function updateTicketStatus($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_ticket";
            $status         = trim($params['status']);
            $ticketId       = $params['ticketId'];

            if(empty($ticketId)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid */, 'data'=>"");
            }

            $updateData = array(

                "status"    => $status
            );

            if(count($ticketId) > 0){
                $db->where("id", $ticketId, "IN");
            }
            
            if ($db->update($tableName, $updateData))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00369"][$language] /* Successfully update ticket */, 'data'=> "");
            else
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00274"][$language] /* Failed to update ticket */, 'data'=> "");
        }
    }
?>
