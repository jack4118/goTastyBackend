<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 16/12/2020.
    **/

    class Game {

        function __construct() {
        }

        public function joinGameQueue($params,$insertType = normal){
        	$db = MysqliDb::getInstance();
        	$language       = General::$currentLanguage;
            $translations   = General::$translations;
        	$dateTime 		= $params['dateTime']?trim($params['dateTime']):date('Y-m-d H:i:s');
        	$portfolioId 	= trim($params['portfolioId']);
        	$clientID 		= trim($params['clientID']);
            $productID      = trim($params['productID']);

            //Get Product Category
            $db->where('id',$productID);
            $category = $db->getValue('mlm_product','category');

        	if(!$portfolioId){
        		return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00959'][$language], 'data' => "");
        	}else{
        		$db->where('portfolio_id',$portfolioId);
                $db->orderBy('created_at','DESC');
        		$gameID = $db->getValue('game_detail','game_id');

                $db->where('id',$gameID);
                $db->where("start_date", $dateTime, "<=");
                $db->where("end_date", $dateTime, ">=");
                $db->where("product_category", $category);
                $checkGameDetail = $db->getValue('game','id');
        		if($checkGameDetail){
        			return array('status' => "error", 'code' => 1, 'statusMsg' => "This Portfolio Already Join Game.", 'data' => "");
        		}
        	}

            $db->where('name','luckyDrawOffDay');
            $luckyDrawOffDay = $db->getValue('system_settings','value');
            $offDayArr = explode("#", $luckyDrawOffDay);
            $todayDay  = date('D',strtotime($dateTime));

            if(in_array($todayDay, $offDayArr)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Today - ".$todayDay." no Lucky Draw.", 'data' => "");
            }

        	if(!$clientID){
        		return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00105'][$language] /*Invalid User.*/, 'data' => "");
        	}

    		$db->where('client_id',$clientID);
    		$db->where('name','enabledAutoJoin');
    		$autoJoin = $db->getValue('client_setting','value');

    		switch ($insertType) {
    			case 'normal':
    				if($autoJoin != 1){
    					return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00960'][$language], 'data' => "");
    				}
    				break;

				case 'manual':
					if($autoJoin == 1){
    					return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00961'][$language], 'data' => "");
    				}
    				break;
    		}

            $db->where('type', 'Game Setting');
            $db->where('name','gameTimeType');
            $gameTimeType = $db->getValue('system_settings_admin','value');
            switch ($gameTimeType) {
                case 'normal':
                    //take current game time
                    $db->where('type', 'Game Time Setting');
                    $db->where('status','Active');
                    $db->orderBy("CAST(ref_id as integer)","ASC");
                    $gameTimeSettingRes = $db->get('system_settings_admin',null,'name,value,reference');
                    $gameTimeSet = $gameTimeSettingRes;
                    break;

                case 'interval':
                    //take current game time
                    $db->where('type', 'Game Interval Setting');
                    $db->where('status','Active');
                    $gameTimeSettingRes = $db->map('name')->get('system_settings_admin',null,'name,value,reference');
                    $intervalStart      = $gameTimeSettingRes['intervalStart']['value'];
                    $intervalStartType  = $gameTimeSettingRes['intervalStart']['reference'];

                    $intervalEnd        = $gameTimeSettingRes['intervalEnd']['value'];
                    $intervalEndType    = $gameTimeSettingRes['intervalEnd']['reference'];
                    $dayOpenTime        = date('Y-m-d 00:00:00');
                    $dayEndTime         = date('Y-m-d 00:00:00',strtotime($dateTime." +1 days"));
                    $count              = 1; 
                    while ($dayOpenTime < $dayEndTime) {
                        $gameStart  = date('H:i:s',strtotime($dayOpenTime." +".$intervalStart." ".$intervalStartType));
                        $gameEnd    = date('H:i:s',strtotime($gameStart." +".$intervalEnd." ".$intervalEndType));
                        $gameTimeSetting['name']        = "gameTime".$count;
                        $gameTimeSetting['value']       = $gameStart;
                        $gameTimeSetting['reference']   = $gameEnd;
                        $gameTimeSet[] = $gameTimeSetting;

                        $dayOpenTime = date('Y-m-d H:i:s',strtotime($dayOpenTime." +".$intervalStart." ".$intervalStartType));
                        $count++;
                    }
                    break;
            }

            foreach ($gameTimeSet as $gameSet) {
                $gameOpenTime = date('Y-m-d '.$gameSet['value']);
                $gameCloseTime = date('Y-m-d '.$gameSet['reference']);
                if(strtotime($dateTime) >= strtotime($gameOpenTime) && strtotime($dateTime) < strtotime($gameCloseTime)){

                    $jsonParams['portfolioID'] = $portfolioId;
                    $jsonData = json_encode($jsonParams);
                    $insertQueue = array(
                        "queue_type" => "joinGame",
                        "client_id"  => $clientID,
                        "product_id" => $productID,
                        "data"       => $jsonData,
                        "status"     => "Active",
                        "created_at" => $dateTime,
                    );
                    
                    $queueID = $db->insert('queue',$insertQueue);
                }
            }

            if(!$queueID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00962'][$language], 'data' => "");
            }

            return array('status' => "ok", 'code' => 1, 'statusMsg' => $translations['B00381'][$language], 'data' => "");
        }

        public function openGameRoom($dateTime,$startTime,$endTime,$category){
        	$db = MysqliDb::getInstance();
        	$language       = General::$currentLanguage;
            $translations   = General::$translations;
        	$robotID 	  	= Setting::$systemSetting['robotID'];
        	$dateTime 		= $dateTime?trim($dateTime):date('Y-m-d H:i:s');

            $db->where('name',array('maxRobotQty','maxWinner'),"IN");
            $gameStgRes = $db->map("name")->get('system_settings',null,'name,value');
            $maxRobotQty = $gameStgRes['maxRobotQty'];
            $maxWinner = $gameStgRes['maxWinner'];

        	/*Get Game Setting*/
        	$db->where('active_at',$startTime,"<=");
            $db->where('status','Active');
            $db->where('ref_id',$category);
            $db->orderBy('active_at','ASC');
            $db->orderBy('id','ASC');
            $settingRes = $db->get("system_settings_admin",NULL,"name,value,reference,active_at,ref_id");
            foreach ($settingRes as $settingRow) {
            	$gameSetting[$settingRow['name']] = $settingRow;
            }

            unset($settingRes);
    		unset($opRoomQty,$totalMember,$gameIDAry,$roomRobotQty);

            $opRoomType 		= $gameSetting['roomSetting']['reference'];
            $opRoomValue 		= $gameSetting['roomSetting']['value'];
            $winRobotPercent 	= $gameSetting['winRobot']['value'];
            $notWinRobotPercent = $gameSetting['notWinRobot']['value'];
            $randomRobotPercent = $gameSetting['randomRobot']['value'];            
            $participantQty     = $gameSetting['maxParticipateLimit']['value'];
            $gameRoomCode       = $gameSetting['gameRoomCode']['value'];

            /*Get today Last Game No*/
            $db->where('DATE(start_date)',date('Y-m-d',strtotime($startTime)));
            $db->where('product_category',$category);
            $db->orderBy('created_at','DESC');
            $db->orderBy('id','DESC');
            $lastGameNo = $db->getValue('game','game_no');
            $lastGameNoArr = explode($gameRoomCode, $lastGameNo);
            $lastGameCount = (int)$lastGameNoArr[1];

            /*Calculate Open Room Quantity*/
            switch ($opRoomType) {
            	case 'memberPercentage':
            		$db->where('type','Client');
            		$db->where('activated',array(1,2),"IN");
            		$db->where('`terminated`',0);
                    $db->where('created_at',$startTime,"<=");
            		$totalMember = $db->getValue('client','Count(id)');

            		$opRoomQty = ceil($totalMember * ($opRoomValue/100) / $participantQty);
            		break;

        		case 'roomQuantity':
            		$opRoomQty = $opRoomValue;
            		break;

        		case 'memberQuantity':
            		$totalMember = $opRoomValue;

            		$opRoomQty = ceil($totalMember / $participantQty);
            		break;
            }            

            /*Calculate Win Robot Quantity*/
            $winRobotQty = floor($opRoomQty * $maxWinner *($winRobotPercent/100));

            // maxRobotQty - 1 is reserve for Win Robot 
            $notWinRobotQty = floor($opRoomQty * ($maxRobotQty - $maxWinner) * ($notWinRobotPercent/100));
            $randomRobotQty = floor($opRoomQty * ($maxRobotQty - $maxWinner) * ($randomRobotPercent/100));

            $gameCount = 0;
            for ($i=0; $i < $opRoomQty; $i++) {
            	$batchID = $db->getNewID();

                if($lastGameCount){
                    $lastGameCount++;
                    $newGameCount = sprintf("%'.05d\n", $lastGameCount);
                }else{
                    $gameCount++;
                    $newGameCount = sprintf("%'.05d\n", $gameCount);
                }

                $gameNo = date('Ymd').$gameRoomCode.$newGameCount;

            	$insertRoom = array(
            		"product_category" => $category,
            		"game_no" 	 => $gameNo,
            		"status"	 => "await",
            		"created_at" => $dateTime,
            		"start_date" => $startTime,
            		"end_date"	 => $endTime,
            		"batch_id"   => $batchID,
            	);
            	$gameID = $db->insert('game',$insertRoom);
            	$gameIDAry[$gameID] = $gameID;
            }

            // Insert Win Robot to Game Room
            $insertedRobot = 0;
            while ($insertedRobot < $winRobotQty) {
                shuffle($gameIDAry);

                foreach ($gameIDAry as $gameID) {
                	if($insertedRobot >= $winRobotQty){
                		break;
                	}

                	$memberID = Subscribe::generateMemberID();

                    $gameSeat = Custom::generateGameSeat("game_detail",$gameID,1,$participantQty);

                	$insertPlayer = array(
                		"game_id" 		=> $gameID,
                		"client_id" 	=> $robotID,
                        "priority"      => $gameSeat,
                		"member_detail" => $memberID,
                		"winner"		=> 1,
                		"created_at"	=> $dateTime,
                	);
                	$db->insert('game_detail',$insertPlayer);

                	$insertedRobot++;
                	$roomRobotQty[$gameID] += 1;
                	$db->where('id',$gameID);
                	$db->update('game',array("participant" => $db->inc(1)));
                }
            }

            $insertedRobot = 0;
            /*Insert Not Win Robot to Game Room*/
            while ($insertedRobot < $notWinRobotQty) {
            	shuffle($gameIDAry);

            	foreach ($gameIDAry as $gameID) {
	            	if($insertedRobot >= $notWinRobotQty){
	            		break;
	            	}

	            	if($roomRobotQty[$gameID] >= $maxRobotQty){
	            		continue;
	            	}

	            	$memberID = Subscribe::generateMemberID();

                    $gameSeat = Custom::generateGameSeat("game_detail",$gameID,1,$participantQty);

	            	$insertPlayer = array(
	            		"game_id" 		=> $gameID,
	            		"client_id" 	=> $robotID,
                        "priority"      => $gameSeat,
	            		"member_detail" => $memberID,
	            		"winner"		=> 2,
	            		"created_at"	=> $dateTime,
	            	);
	            	$db->insert('game_detail',$insertPlayer);

	            	$insertedRobot++;
	            	$roomRobotQty[$gameID] += 1;
	            	$db->where('id',$gameID);
            		$db->update('game',array("participant" => $db->inc(1)));
	            }
            }

            $insertedRobot = 0;
            /*Insert Not Win Robot to Game Room*/
            while ($insertedRobot < $randomRobotQty) {
            	shuffle($gameIDAry);

            	foreach ($gameIDAry as $gameID) {
	            	if($insertedRobot >= $randomRobotQty){
	            		break;
	            	}

	            	if($roomRobotQty[$gameID] >= $maxRobotQty){
	            		continue;
	            	}
	            	$memberID = Subscribe::generateMemberID();

                    $gameSeat = Custom::generateGameSeat("game_detail",$gameID,1,$participantQty);

	            	$insertPlayer = array(
	            		"game_id" 		=> $gameID,
	            		"client_id" 	=> $robotID,
                        "priority"      => $gameSeat,
	            		"member_detail" => $memberID,
	            		"winner"		=> 0,
	            		"created_at"	=> $dateTime,
	            	);
	            	$db->insert('game_detail',$insertPlayer);

	            	$insertedRobot++;
	            	$roomRobotQty[$gameID] += 1;

	            	$db->where('id',$gameID);
            		$db->update('game',array("participant" => $db->inc(1)));
	            }
            }

            return true;
        }

        public function insertPlayer($dateTime,$startTime,$endTime,$category,$portfolioIDAry,$insertType = normal){
        	$db = MysqliDb::getInstance();
        	$language       = General::$currentLanguage;
            $translations   = General::$translations;
        	$dateTime 		= $dateTime?trim($dateTime):date('Y-m-d H:i:s');

        	/*Get Product Setitng*/
            $db->where('name',"maxParticipateLimit");
            $db->where('ref_id',$category);
            $db->where('active_at',$startTime,"<=");
            $db->orderBy('active_at','DESC');
            $db->orderBy('id','DESC');
        	$maxParticipant = $db->getValue('system_settings_admin','value');

            $db->where('username','director');
            $directorID = $db->getValue('client','id');

        	$db->where('name','enabledAutoJoin');
        	$db->where('value','1');
        	$autoJoinAry = $db->map('client_id')->get('client_setting',null,'client_id,value');

        	$db->where('start_date',$startTime);
            $db->where('product_category',$category);
        	$gameRes = $db->get('game',null,'id,product_category,participant');
        	foreach ($gameRes as $gameRow) {
        		$gameDetailAry[$gameRow['id']] = $gameRow['participant'];
        		$gameIDArr[$gameRow['id']] = $gameRow['id'];
        	}

        	if($gameIDArr){
        		$db->where('game_id',array_values($gameIDArr),'IN');
        		$db->where('portfolio_id',0,">");
        		$gameDetailRes = $db->get('game_detail',null,'game_id,portfolio_id,client_id, win_count');
        		foreach ($gameDetailRes as $gameDetailRow) {
        			$invalidPortfolio[$gameDetailRow['portfolio_id']] = $gameDetailRow['portfolio_id'];
        			$gameClientIDAry[$gameDetailRow['game_id']][$gameDetailRow['client_id']] = $gameDetailRow['client_id'];
                    if($gameDetailRow['win_count'] >= 2){
                        $gRWinCountAry[$gameDetailRow['game_id']] += 1;
                    }
        		}
        	}

            //Get Product ID
            $db->where('category',$category);
            $productIDArr = $db->map('id')->get('mlm_product',null,'id');
            if(!$productIDArr){
                Log::write(date("Y-m-d H:i:s") . " Invalid category. Failed to proceed.\n");
                return false;
            }

        	/*Get Game Setting*/
        	if($portfolioIDAry){
        		$db->where('id',$portfolioIDAry,"IN");
        		$db->where('created_at',$endTime,"<");
        	}else{
        		$db->where('created_at',$startTime,"<");
        	}
            $db->where('product_id',$productIDArr,"IN");
            $db->where('status','Active');
            $db->orderBy('CAST(reference_no AS Integer)','ASC');
            $portfolioRes = $db->get('mlm_client_portfolio',null,'id,client_id,product_id');
            foreach ($portfolioRes as $portfolioRow) {
            	$clientIDAry[$portfolioRow['client_id']] = $portfolioRow['client_id'];
                $portfolioData[$portfolioRow['id']] = $portfolioRow['product_id'];
            }

            if($clientIDAry){
            	$db->where('id',$clientIDAry,"IN");
                $clientDataArr = $db->map('id')->get('client',null,'id,member_id,sponsor_id');

                $db->where('client_id',$clientIDAry,"IN");
                $db->where('name',array('thisMonthWon','bonusCountDate'),"IN");
                $clientWinRes = $db->get('client_setting',null,'client_id,name,value,reference,description');
                foreach ($clientWinRes as $clientWinRow) {
                    switch ($clientWinRow['name']) {
                        case 'thisMonthWon':
                            $clientWinCountAry[$clientWinRow['client_id']][$clientWinRow['reference']] = $clientWinRow['value'];
                            break;
                        
                        case 'bonusCountDate':
                            $clientJoinGame[$clientWinRow['client_id']] = $clientWinRow['description'];
                            break;
                    }
                }
            }

            foreach ($portfolioRes as $portfolioRow) {
                unset($gameID);
            	$portfolioID = $portfolioRow['id'];
            	$clientID 	 = $portfolioRow['client_id'];
                $productID   = $portfolioData[$portfolioID];
            	$participant = $maxParticipant;
            	$gameAry 	 = $gameDetailAry;
            	$memberID 	 = $clientDataArr[$clientID]['member_id'];
            	$totalGameRoom = Count($gameAry);   
                $winCount = $clientWinCountAry[$clientID][$productID] ? $clientWinCountAry[$clientID][$productID] : 0;

            	if($invalidPortfolio[$portfolioID]){
            		Log::write(date("Y-m-d H:i:s") . " Portfolio ID - ".$portfolioID." Already Join Game.\n");
            		continue;
            	}

            	if(($autoJoinAry[$clientID] != 1) && ($insertType == "normal")){
            		Log::write(date("Y-m-d H:i:s") . " Client ID - ".$clientID." had disabled auto join game.\n");
            		continue;
            	}

            	unset($failGameID);
            	while (Count($failGameID) < $totalGameRoom) {
        			$gameID = MIN(array_keys($gameAry));
                    $isInvalidGame = 0;

                   /* if($gRWinCountAry[$gameID] >= 1 && $winCount >= 2){
                        $isInvalidGame = 1;
                    }*/

        			if(!$isInvalidGame && (!$gameClientIDAry[$gameID][$clientID]) && ($gameAry[$gameID] < $participant)){
        				break;
        			}

                    $failGameID[$gameID] = $gameID;
                    unset($gameAry[$gameID]);
                    unset($gameID);
            	}

            	if(!$gameID){
                    // Send portfolio join game status socket
                    $socketData = array(
                        'category' => "luckyDrawListing$clientID",
                        'dataType' => "joinGameStatus",
                        'portfolioID' => $portfolioID,
                        'status' => 'failed'
                    );

                    General::sendSocketData($socketData);

            		Log::write(date("Y-m-d H:i:s") . " Product ID - ".$productID." Game Room is Full.\n");
            		continue;
            	}

                $gameSeat = Custom::generateGameSeat("game_detail",$gameID,1,$participant);

            	$insertPlayer = array(
            		"game_id" 		=> $gameID,
            		"client_id" 	=> $clientID,
                    "priority"      => $gameSeat,
            		"member_detail"	=> $memberID,
            		"portfolio_id"  => $portfolioID,
            		"winner"		=> 0,
            		"created_at"	=> $dateTime,
                    "win_count"     => $winCount,
            	);
            	$gameDetailID = $db->insert('game_detail',$insertPlayer);

                if($winCount >= 2){
                    $gRWinCountAry[$gameID] += 1;
                }

				// Send join game member data to game room socket
				$socketData = array(
					'category' => "gameRoom$gameID",
					'dataType' => "memberJoinGame",
					'clientDetail' => substr_replace($memberID, "xxxx", '-4'),
					'fullClientDetail' => $memberID
				);

				General::sendSocketData($socketData);

				// Send portfolio join game status socket
				$socketData = array(
					'category' => "luckyDrawListing$clientID",
					'dataType' => "joinGameStatus",
					'portfolioID' => $portfolioID,
					'status' => 'joined',
					'gameID' => $gameID
				);

				General::sendSocketData($socketData);

            	if($gameDetailID){
            		$db->where('id',$gameID);
	            	$db->update('game',array("participant" => $db->inc(1)));
            	}

                //update member active date, will not update after won 3 times.
                if(!$clientJoinGame[$clientID]){
                    Custom::updateMemberActiveStatus($clientID, $startTime, "joinGame");
                }

                $latestParticipant = $gameDetailAry[$gameID];
            	$gameDetailAry[$gameID] = $latestParticipant + 1;

            	if($gameDetailAry[$gameID] >= $participant){
            		$db->where('id',$gameID);
	            	$db->update('game',array("status" => "closed"));
            		unset($gameDetailAry[$gameID]);
            	}
            }

            return true;
        }

        public function queueInsertGame($dateTime,$startTime,$endTime,$category){
        	$db = MysqliDb::getInstance();
        	$language       = General::$currentLanguage;
            $translations   = General::$translations;
        	$dateTime 		= $dateTime?trim($dateTime):date('Y-m-d H:i:s');

            $db->where('category',$category);
            $productIDArr = $db->map('id')->get('mlm_product',null,'id');
            if(!$productIDArr){
                Log::write(date("Y-m-d H:i:s")." Invalid Category for queue insert game. Failed to proceed.\n");
                return false;
            }

        	$db->where("queue_type", "joinGame");
	        $db->where("processed", "0");
	        $db->where("created_at", $endTime,"<");
            $db->where('product_id',$productIDArr,"IN");
	        $db->orderBy("created_at", "ASC");
	        $queueRes = $db->get("queue", null, "id, data, created_at");
	        foreach ($queueRes as $queueRow) {
	        	$portfolioData = json_decode($queueRow['data'],true);
	        	$portfolioIDAry[$portfolioData['portfolioID']]	= $portfolioData['portfolioID'];
	        	$queueIDAry[$queueRow['id']] = $queueRow['id'];
	        }

	        if($queueIDAry){
	        	$db->where('id',$queueIDAry,"IN");
	        	$db->update('queue',array("processed"=>2));
	        }

	        if($portfolioIDAry){
                Log::write(date("Y-m-d H:i:s")." Processing Queue Join Game.\n");
                $result = Game::insertPlayer($dateTime,$startTime,$endTime,$category,$portfolioIDAry,"queue");
                Log::write(date("Y-m-d H:i:s")." Finish Queue Join Game.\n");
	        }

	        if($result && $queueIDAry){
	        	$db->where('id',$queueIDAry,"IN");
	        	$db->update('queue',array("processed"=>1));
	        }

            return true;
        }

        public function processLuckyDraw($dateTime,$startTime,$endTime,$category){
        	$db = MysqliDb::getInstance();
        	$language       = General::$currentLanguage;
            $translations   = General::$translations;
        	$dateTime 		= $dateTime?trim($dateTime):date('Y-m-d H:i:s');
        	$robotID 		= Setting::$systemSetting['robotID'];

            $db->where('name','maxWinner');
            $maxWinner = $db->getValue('system_settings','value');

        	/*Get Product Setitng*/
        	$db->where('name',array('maxParticipateLimit','prioritizeNewbie','minWin'),"IN");
            $db->where('ref_id',$category);
            $db->where('active_at',$startTime,"<=");
            $db->where('status','Active');
            $db->orderBy('active_at','ASC');
            $db->orderBy('id','ASC');
            $productData = $db->map('name')->get("system_settings_admin",NULL,"name,value");
            $participantSet     = $productData['maxParticipateLimit'];
            $prioritizeNewbie   = $productData['prioritizeNewbie'];
            $minWin             = $productData['minWin'];

            $db->where('status','closed');
        	$db->where('end_date',$endTime,'<=');
            $db->where('product_category',$category);
        	$db->orderBy('created_at','ASC');
        	$gameRes = $db->get('game',null,'id,participant,end_date,batch_id');
            if(!$gameRes){
                return false;
            }

        	foreach ($gameRes as $gameRow) {
        		$gameIDAry[$gameRow['id']] = $gameRow['id'];
        	}

        	if($gameIDAry){
        		$db->where('game_id',$gameIDAry,"IN");
        		$gameDetailRes = $db->get('game_detail',null,'id,game_id,client_id,portfolio_id,winner,member_detail');
        		foreach ($gameDetailRes as $gameDetailRow) {

        			switch ($gameDetailRow['winner']) {
        				case '1':
        					// Get Pre-set Winner Game ID
        					$preWinGameAry[$gameDetailRow['game_id']][$gameDetailRow['id']] 	= $gameDetailRow['id'];
        					break;
        				
        				case '0':
        					$drawWinnerAry[$gameDetailRow['game_id']][$gameDetailRow['id']] = $gameDetailRow['id'];
        					break;
        			}

        			$gameDetailAry[$gameDetailRow['game_id']][$gameDetailRow['id']] = $gameDetailRow;
        			$portfolioIDAry[$gameDetailRow['portfolio_id']] = $gameDetailRow['portfolio_id'];
                    $clientIDAry[$gameDetailRow['client_id']] = $gameDetailRow['client_id'];
        		}
        	}

        	if($portfolioIDAry){
        		$db->where('id',$portfolioIDAry,'IN');
        		$portfolioBVAry = $db->map('id')->get('mlm_client_portfolio',null,'id,belong_id,bonus_value as bonusValue,product_id');
        	}
        	unset($portfolioIDAry);

            if($clientIDAry){
                //game in 25days
                $db->where('client_id',$clientIDAry,"IN");
                $db->where('name','thisMonthWon');
                $clientWinCountRes = $db->get('client_setting',null,'client_id,reference,value');
                foreach ($clientWinCountRes as $clientWinCountRow) {
                    $clientWinCountAry[$clientWinCountRow['client_id']][$clientWinCountRow['reference']] = $clientWinCountRow['value'];
                }
            }

            $db->where('name','roiPercentage');
            $db->where('ref_id',$category);
            $db->orderBy('CAST(value AS Integer)','DESC');
            $rewaradAmtData = $db->map('value')->get('system_settings_admin',null,'value,reference');

            Log::write(date("Y-m-d H:i:s")." Start Process Lucky Draw.\n");

        	foreach ($gameRes as $gameRow) {
                unset($goldmineClientIDAry);
                unset($goldmineBonusParams);

        		$gameID			= $gameRow['id'];
        		$participant 	= $gameRow['participant'];
        		$endDate  	    = $gameRow['end_date'];
        		$batchID 		= $gameRow['batch_id'];
        		$maxParticipate = $participantSet;
        		$drawWinner 	= $drawWinnerAry[$gameID];
        		$gameDetail 	= $gameDetailAry[$gameID];
                $rewaradAmtArr  = $rewaradAmtData;
                $unitPrice = General::getLatestUnitPrice();
                unset($winnerClientIDArr);

        		if($participant != $maxParticipate){
        			Log::write(date("Y-m-d H:i:s")." Game ID - ".$gameID." participant is not enough to start game.\n");
        			continue;
        		}

                $winnerIDArr = $preWinGameAry[$gameID];

                while (COUNT($winnerIDArr) < $maxWinner) {
                    unset($win2ClientArr);
                    while (Count($win2ClientArr) < Count($drawWinner)) {
                        $winnerID       = array_rand($drawWinner);
                        $winClientID    = $gameDetail[$winnerID]['client_id'];
                        $tempProductID  = $portfolioBVAry[$gameDetail[$winnerID]['portfolio_id']]['product_id'];
                        $nextWinCount   = $clientWinCountAry[$winClientID][$tempProductID] + 1;

                        //Check room if got nextWin not equal to minWin(3)
                        if((($nextWinCount != $minWin)) || (!$prioritizeNewbie)){
                            break;
                        }
                        $win2ClientArr[$winnerID] = $winnerID;
                    }
                    $winnerIDArr[$winnerID] = $winnerID;
                }

                unset($memberIDAry);
                unset($prizeDataArr);
        		foreach ($gameDetail as $gameDetailID => $gameDetailRow) {
        			$portfolioID   = $gameDetailRow['portfolio_id'];
        			$clientID 	   = $gameDetailRow['client_id'];
                    $memberID      = $gameDetailRow['member_detail'];
        			$bonusValue    = $portfolioBVAry[$portfolioID]['bonusValue'];
        			$belongID      = $portfolioBVAry[$portfolioID]['belong_id'];
                    $productID      = $portfolioBVAry[$portfolioID]['product_id'];
                    $winCount      = $clientWinCountAry[$clientID];
                    $payAmt        = 0;
                    $memberIDAry[$clientID] = $memberID;

        			if($clientID == $robotID){
                        if($winnerIDArr[$gameDetailID]){
                            $winnerMemberIDArr[$memberID] = $memberID;
                        }
    					continue;
    				}

        			if($winnerIDArr[$gameDetailID]){
                        $winnerClientIDArr[$clientID] = $clientID;
                        $winnerMemberIDArr[$memberID] = $memberID;
                        Game::insertClientProductWon($productID, $clientID,$endTime);
        			}else{
                        //Calculate Rebate Amount
                        $payPercentage = array_rand($rewaradAmtArr);
                        $rewaradAmtArr[$payPercentage] = $rewaradAmtArr[$payPercentage] - 1;
                        if($rewaradAmtArr[$payPercentage]<=0) unset($rewaradAmtArr[$payPercentage]);

                        $portion = Setting::setDecimal(($bonusValue * ($payPercentage /100)),"");

                        $roiAmount = Setting::setDecimal(($portion * $unitPrice), "");
                        $prizeData['memberID']    = $memberID;
                        $prizeData['prizeAmount'] = $roiAmount;
                        $prizeDataArr[] = $prizeData;

                        $db->where('id',$portfolioID);
                        $db->update('mlm_client_portfolio',array("reward_percentage"=>$payPercentage));
        			}
        		}

                $socketData = array(
                    'category' => "gameRoom$gameID",
                    'dataType' => "gameResult",
                    'winnerID' => $winnerMemberIDArr
                );
                $socketData['prizeData'] = $prizeDataArr;
                if($socketData){
                    $jsonData = json_encode($socketData);
                    Game::insertSocketQueue($productID,$jsonData,$endTime);
                }

        		// Update Winner Status
        		$db->where('id',$winnerIDArr,"IN");
        		$db->where('game_id',$gameID);
				$db->update('game_detail',array('winner'=>1));

        		$db->where('id',$winnerIDArr,'NOT IN');
				$db->where('game_id',$gameID);
				$db->update('game_detail',array('winner'=>2));

				$db->where('id',$gameID);
				$db->update('game',array('status'=>'completed'));
        	}
            Log::write(date("Y-m-d H:i:s")." End Process Lucky Draw.\n");
        	return true;
        }

        public function manualJoinGame($params){
        	$db = MysqliDb::getInstance();
        	$language       = General::$currentLanguage;
            $translations   = General::$translations;
        	$dateTime 		= date('Y-m-d H:i:s');
        	$portfolioId 	= trim($params['portfolioId']);
            $category       = "normal";

        	$site 			= $db->userType;
        	$clientID 		= $db->userID;

        	if($site == "Admin"){
        		$clientID 		= trim($params['clientID']);
        	}

        	if(!$clientID){
        		return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00105'][$language] /*Invalid User.*/, 'data' => "");
        	}else{
        		$db->where('id',$clientID);
        		$username = $db->getValue('client','username');
        		if(!$username){
        			return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00105'][$language] /*Invalid User.*/, 'data' => "");
        		}
        	}

        	if(!$portfolioId){
        		return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00959'][$language], 'data' => "");
        	}else{
        		$db->where('id',$portfolioId);
        		$db->where('client_id',$clientID);
        		$db->where('status','Active');
        		$portfolioRes = $db->getOne('mlm_client_portfolio','reference_no, product_id');
        		if(!$portfolioRes){
        			return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00959'][$language], 'data' => "");
        		}

                $productID = $portfolioRes["product_id"];
                $portfolioRef = $portfolioRes["reference_no"];
        	}

            $db->where('category',$category);
            $vailProductIDArr = $db->map('id')->get('mlm_product',null,'id');

            if(!$vailProductIDArr[$productID]){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00955'][$language] /*Invalid Product*/, 'data' => "");
            }

            $db->where('name','luckyDrawOffDay');
            $luckyDrawOffDay = $db->getValue('system_settings','value');
            $offDayArr = explode("#", $luckyDrawOffDay);
            $todayDay  = date('D',strtotime($dateTime));

            if(in_array($todayDay, $offDayArr)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Today - ".$todayDay." no Lucky Draw.", 'data' => "");
            }

            $db->where("status", "await");
            $db->where("start_date", $dateTime, "<=");
            $db->where("end_date", $dateTime, ">=");
            $db->where("product_category", $category);
            $gameCount = $db->getValue("game", "count(id)");
            if($gameCount <= 0){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00978'][$language], 'data' => "");
            }

        	$joinQueueParams['portfolioId'] = $portfolioId;
            $joinQueueParams['productID']   = $productID;
        	$joinQueueParams['dateTime'] 	= $dateTime;
        	$joinQueueParams['clientID'] 	= $clientID;
        	$result = Game::joinGameQueue($joinQueueParams,"manual");
        	if($result['status'] == 'error'){
        		return array('status' => "error", 'code' => 1, 'statusMsg' => $result['statusMsg'], 'data' => "");
        	}

        	$activityData =  array("username" => $username, "portfolioRef" => $portfolioRef, "dateTime" => $dateTime);
        	$activityRes = Activity::insertActivity('Manual Join Game', 'T00040', 'L00060', $activityData, $clientID);

        	return array('status' => "ok", 'code' => 1, 'statusMsg' => $translations['B00381'][$language], 'data' => "");
        }

        public function getGameJoinerData($params){
            $db           = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $gameRoomID   = $params['gameRoomID'];
            $currentTime  = time();
            $dateTime     = date('Y-m-d H:i:s');
            $clientID     = $db->userID;

            if(!$gameRoomID)
                return array("status" => "error", "code" => 1, "statusMsg" => "Game Room Not Found", "data" => "");

            $db->where('id', $gameRoomID);
            $gameData = $db->getOne('game', 'end_date,status, batch_id');

            if(!$gameData)
                return array("status" => "ok", "code" => 0, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => "");

            $getEndDate     = $gameData['end_date'];
            // $winnerID       = $gameData['winner_id'];
            $getGameStatus  = "await";
            if(strtotime($dateTime) >= strtotime($getEndDate)){
                $getGameStatus  = $gameData['status'];
            }
            $batchID        = $gameData['batch_id'];
            $unitPrice      = General::getLatestUnitPrice();

            $db->where('game_id', $gameRoomID);
            $participantData = $db->get('game_detail', NULL, 'id, client_id, member_detail,winner,portfolio_id,priority');
            if(!$participantData)
                return array("status" => "ok", "code" => 1, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => "");

            foreach ($participantData as $participantRow) {
                $portfolioIDAry[$participantRow['portfolio_id']] = $participantRow['portfolio_id'];
            }

            if($getGameStatus == 'completed'){
                $db->where('game_id', $gameRoomID);
                $gamerROI = $db->map('client_id')->get('mlm_bonus_rebate', NULL, 'client_id, payable_amount');

                if(!$gamerROI){
                    if($portfolioIDAry){
                        $db->where('id',$portfolioIDAry,'IN');
                        $portfolioRes = $db->map('client_id')->get('mlm_client_portfolio',null,'client_id,bonus_value,reward_percentage');

                        foreach ($portfolioRes as $portClientID => $portfolioRow) {

                            $portion = Setting::setDecimal(($portfolioRow['bonus_value'] * ($portfolioRow['reward_percentage'] /100)),"");
                            $roiAmount = Setting::setDecimal(($portion * $unitPrice), "");
                            $gamerROI[$portClientID] = $roiAmount;
                        }
                    }
                }
            }

            foreach ($participantData as $clientData) {
                //Initialize default data
                $value['isYou']      = 0;
                $value['isWinner']   = 0;
                $value['winType']   = '-';
                $value['prize']      = '-';

                $value['clientID']         = $clientData['client_id'];
                $value['clientDetail']     = substr_replace($clientData['member_detail'], "xxxx", '-4');
                $value['fullClientDetail'] = $clientData['member_detail'];
                $value['priority'] = $clientData['priority'];

                if($clientID == $clientData['client_id']){
                    $value['isYou'] = '1';
                }

                if($getGameStatus == 'completed'){
                    if($clientData['winner'] == 1){
                        $value['isWinner'] = '1';
                        $value['winType']  = 'product';
                        $value['prize']    = '-';
                    }else{
                        $value['winType']  = 'roi';
                        $value['prize']    = $gamerROI[$clientData['client_id']] ? $gamerROI[$clientData['client_id']] : 0;
                    }
                }

                $clientDetail[] = $value;
            }
            
            $db->where("client_id", $clientID);
            $db->where("batch_id", $batchID);
            $payableAmount = $db->getValue("mlm_bonus_bonanza", "payable_amount");
            if(strtotime($getEndDate) > $currentTime){
                $remainingTime = strtotime($getEndDate);
                $remainingTime = $remainingTime - $currentTime;
            }else{
                $remainingTime = '-';
            }

            $data['remainingEndTime'] = $remainingTime;
            $data['endTimestamp'] = $remainingTime > 0 ? strtotime($getEndDate) : 0 ;
            $data['clientDetail']     = $clientDetail;
            $data['gameStatus'] = $getGameStatus;
            $data['bonanzaPayableAmount'] = $payableAmount > 0 ? Setting::setDecimal($payableAmount) : 0;

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $data);
        }

        public function getPVTGameJoinerData($params){
            $db           = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $gameRoomID   = $params['gameRoomID'];
            $currentTime  = time();
            $dateTime     = date('Y-m-d H:i:s');
            $clientID     = $db->userID;

            if(!$gameRoomID)
                return array("status" => "error", "code" => 1, "statusMsg" => "Game Room Not Found", "data" => "");

            $db->where('id', $gameRoomID);
            $gameData = $db->getOne('private_game', 'start_time,status');

            if(!$gameData)
                return array("status" => "ok", "code" => 0, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => "");

            $getEndDate     = $gameData['start_time'];
            $getGameStatus  = $gameData['status'];
            $batchID        = $gameData['batch_id'];
            $unitPrice      = General::getLatestUnitPrice();

            $db->where('private_game_id', $gameRoomID);
            $db->orderBy('id','ASC');
            $participantData = $db->get('private_game_detail', NULL, 'id, client_id,winner,portfolio_id');
            if(!$participantData)
                return array("status" => "ok", "code" => 1, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => "");

            foreach ($participantData as $participantRow) {
                $clientIDArr[$participantRow['client_id']] = $participantRow['client_id'];
            }

            if($clientIDArr){
                $db->where('id',$clientIDArr,"IN");
                $memberIDArr = $db->map('id')->get('client',null,'id,member_id');
            }

            foreach ($participantData as $clientData) {
                //Initialize default data
                $value['isYou']      = 0;
                $value['isWinner']   = 0;
                $value['winType']   = '-';
                $value['prize']      = '-';

                $value['clientID']         = $clientData['client_id'];
                $value['clientDetail']     = $clientData['client_id']?substr_replace($memberIDArr[$clientData['client_id']], "xxxx", '-4'):"-";
                $value['fullClientDetail'] = $clientData['client_id']?$memberIDArr[$clientData['client_id']]:"-";

                if($clientID == $clientData['client_id']){
                    $value['isYou'] = '1';
                }

                if($getGameStatus == 'completed'){
                    if($clientData['winner'] == 1){
                        $value['isWinner'] = '1';
                        $value['winType']  = 'product';
                        $value['prize']    = '-';
                    }else{
                        $value['winType']  = 'voucher';
                        $value['prize']    = 1;
                    }
                }

                $clientDetail[] = $value;
            }
            
            if(strtotime($getEndDate) > $currentTime){
                $remainingTime = strtotime($getEndDate);
                $remainingTime = $remainingTime - $currentTime;
            }else{
                $remainingTime = '-';
            }

            $data['remainingEndTime'] = $remainingTime;
            $data['endTimestamp'] = $remainingTime > 0 ? strtotime($getEndDate) : 0 ;
            $data['clientDetail']     = $clientDetail;
            $data['gameStatus'] = $getGameStatus;

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $data);
        }

        public function getGameRoomListing($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $robotID        = Setting::$systemSetting['robotID'];

            $searchData = $params['searchData'];
            $userID = $db->userId;
            $site   = $sb->userType;
            $seeAll = trim($params['seeAll']);
            $pageNumber = $params['pageNumber'] ? trim($params['pageNumber']) : 1;

            if(!$seeAll) {
                $limit = General::getLimit($pageNumber);
            }

            if($params['type'] == 'export') {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            if(count($searchData) > 0){
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $sq = $db->subQuery();
                            $sq->where('client_id', $downlines, 'IN');
                            $sq->get('game_detail', null, 'game_id');

                            $db->where('id', $sq, 'IN');

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $v) {
                    $dataType  = trim($v['dataType']);
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch ($dataName) {
                        case 'roomID':
                            $db->where('game_no', $dataValue);
                            break;

                        case 'roomType':
                            $db->where('product_category', $dataValue);
                            break;

                        case 'participant':
                            $db->where('participant', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'initial_at':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo   = trim($v['tsTo']);

                            if(strlen($dateFrom)>0) {
                                if($datefrom < 0){
                                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00156'][$language] /* Invalid date */, 'data'=>"");
                                }
                                $db->where('date(start_date)', date('Y-m-d', $dateFrom), '>=');
                                
                            }
                            if(strlen($dateTo)>0) {
                                if($dateTo < 0) {
                                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Date from can not later than date to. */, 'data' =>"");
                                }
                                if($dateTo < $dateFrom) {
                                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00158"][$language]/* Date from  cannot be later than date to */, 'data' =>'');
                                }
                                $db->where('date(start_date)', date('Y-m-d', $dateTo), '<=');   
                            }
                            break;

                        case 'done_at':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo   = trim($v['tsTo']);

                            if(strlen($dateFrom)>0) {
                                if($dateFrom < 0){
                                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00156'][$language] /* Invalid date */, 'data'=>'');
                                }
                                $db->where('date(end_date)', date('Y-m-d', $dateFrom), '>=');
                                
                            }
                            if(strlen($dateTo)>0) {
                                if($dateTo < 0) {
                                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Date from can not later than date to. */, 'data' =>'');
                                }
                                if($dateTo < $dateFrom) {
                                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00158"][$language]/* Date from  cannot be later than date to */, 'data' =>'');
                                }
                                $db->where('date(end_date)', date('Y-m-d', $dateTo), '<=');   
                            }
                            break;

                        case 'username':
                            $filterAry[] = array('column' => 'username', 'filter' => $dataValue);
                            $clientID = Report::getFilterData('client', "id", $filterAry, 1);
                            unset($filterAry);

                            if(empty($clientID)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $filterAry[] = array('column'=>'client_id', 'filter'=>$clientID);
                            $gameID = Report::getFilterData('game_detail', "game_id", $filterAry);
                            unset($filterAry);

                            if($gameID){
                                $db->where('id', $gameID, 'IN');
                            }else{
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            }
                            break;

                        case 'leaderUsername':
                            break;
                    }
                }
                unset($dataName);
                unset($dataType);
                unset($dataValue);
            }

            $db->orderBy('id', 'DESC');
            $copyDB = $db->copy();
            $gameDetail = $db->get('game', $limit,'id,product_category, game_no as roomID, participant, status, start_date as initial_at, end_date as done_at, batch_id');

            if(empty($gameDetail)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00120"][$language] /* No Result Found. */, 'data'=> "");
            }

            foreach ($gameDetail as $detail) {
                $batchIDAry[$detail['batch_id']]  = $detail['batch_id'];
            }

            if($batchIDAry) {
                $db->where('batch_id', $batchIDAry, 'IN');
                //get Paid Reward (5%)
                $reward = $db->map('batch_id')->get('mlm_bonus_rebate',null,'batch_id,payable_amount');
            }

            foreach ($gameDetail as &$detail) {
                $displayStatus = General::getTranslationByName($detail['status']);
                $detail['statusDisplay'] = $displayStatus ? : "-";
                $detail['roomType']      = General::getTranslationByName($detail['product_category']);
                
                $paidReward = $reward[$detail['batch_id']];
                $detail['paidReward']    = Setting::setDecimal($paidReward) ? : Setting::setDecimal(0);

                $paidUni = 0;
                $detail['paidUni']       = Setting::setDecimal($paidUni) ? : Setting::setDecimal(0);;

                if($detail['status']=="completed")
                    $detail['canView'] = "1";
                else
                    $detail['canView'] = "0";
                
                unset($detail['product_id']);
                unset($detail['batch_id']);
                unset($detail['status']);
            }

            //get room type list
            $roomType = $db->map('id')->get('mlm_product',null,'id, translation_code');

            foreach ($roomType as $key => $value) {
                $roomTypeList[$key] = $translations[$value][$language];
            } 

            $countTotalRecord = $copyDB->getValue('game', 'COUNT(id)');

            $data['gameDetail'] = $gameDetail;
            $data['roomList']   = $roomTypeList;
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord']= $countTotalRecord;
            $data['totalPage']  = ceil($countTotalRecord/$limit[1]);
            $data['numRecord']  = $limit[1];
            
            if($seeAll == "1"){
                $data['totalPage']     = 1;
                $data['numRecord']     = $countTotalRecord;
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /*Successful Retrieved.*/, 'data' => $data);
        }

        public function getGameRoomDetail($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $robotID        = Setting::$systemSetting['robotID'];

            $userID = $db->userId;
            $site   = $sb->userType;
            $seeAll = trim($params['seeAll']);
            $pageNumber = $params['pageNumber'] ? trim($params['pageNumber']) : 1;
            $gameID = trim($params['game_id']);

            if(!$seeAll) {
                $limit = General::getLimit($pageNumber);
            }

            $db->where('id', $gameID);
            $clientInfo = $db->getOne('game','game_no, product_category, status, date(end_date) as done_at');

            $clientInfo['roomType'] = General::getTranslationByName($clientInfo['product_category']." room") ? : "-";
            $clientInfo['status']   = General::getTranslationByName($clientInfo['status']) ? : "-";

            $db->where('game_id', $gameID);
            $db->where('client_id', $robotID, '!=');
            $gameDetailRes = $db->get('game_detail', $limit, 'game_id, client_id, portfolio_id, winner');

            if(empty($gameDetailRes)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00120"][$language] /* No Result Found. */, 'data'=> "");
            }

            foreach ($gameDetailRes as $detail) {
                $clientIDAry[$detail['client_id']] = $detail['client_id'];
                $portfolioIDAry[$detail['portfolio_id']] = $detail['portfolio_id'];
            }

            if($clientIDAry){
                $db->where('id',$clientIDAry,'IN');
                $db->where('type', 'Client');
                $db->orderBy('created_at','DESC');
                $clientDetail = $db->map('id')->get('client', null, 'id , created_at as signUpDate, member_id, username, phone, email');
            }

            if($portfolioIDAry){
                $db->where('id', $portfolioIDAry, 'IN');
                $productValue = $db->map('id')->get('mlm_client_portfolio', null, 'id, bonus_value');
            }

            $db->where('game_id', $gameID);
            $payAmtArr = $db->map('client_id')->get('mlm_bonus_rebate', null, 'client_id, payable_amount');

            foreach ($gameDetailRes as $gameResult) {
                $detailResult['signUp']   = $clientDetail[$gameResult['client_id']]['signUpDate'] ? : "-";
                $detailResult['memberID'] = $clientDetail[$gameResult['client_id']]['member_id']? : "-";
                $detailResult['username'] = $clientDetail[$gameResult['client_id']]['username'] ? : "-";
                $detailResult['phone']    = $clientDetail[$gameResult['client_id']]['phone'] ? : "-";
                $detailResult['email']    = $clientDetail[$gameResult['client_id']]['email'] ? : "-";
                $detailResult['portfolio_id'] = $gameResult['portfolio_id'];
                $detailResult['productValue'] = Setting::setDecimal($productValue[$gameResult['portfolio_id']]) ? : Setting::setDecimal("0");

                $payAmt = $payAmtArr[$gameResult['client_id']];
                $detailResult['paidAmt']  = Setting::setDecimal($payAmt) ? : Setting::setDecimal("0");

                switch ($gameResult['winner']) {

                    case '1':
                        $detailResult['result'] = $translations['B00378'][$language];/* won */
                        break;

                    case '2':
                        $detailResult['result'] = $translations['B00379'][$language];/* reward */
                        break;

                    case '3':
                        $detailResult['result'] = $translations['B00380'][$language];/* refund */
                        break;

                    default:
                        $detailResult['result'] = "-";
                        break;
                }
                $clientDetails[] = $detailResult;
            }

            $data['clientInfo']   = $clientInfo;
            $data['clientDetail'] = $clientDetails;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>'', 'data' => $data);
        }

        public function insertSocketQueue($productID,$queueData,$dateTime){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $queueType      = "sendSocket";

            if(!$productID){
                return false;
            }

            if(!$queueData){
                return false;
            }

            if(!$dateTime){
                return false;
            }
            
            $insertQueue = array(
                "queue_type" => $queueType,
                "product_id" => $productID,
                "data"       => $queueData,
                "status"     => "Active",
                "created_at" => $dateTime,
            );
            $db->insert('queue',$insertQueue);

            return true;
        }
        
        public function insertClientProductWon($productID, $clientID,$dateTime) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(!$dateTime)$dateTime = date("Y-m-d H:i:s");

            if(!$productID){
                return false;
            }

            if(!$clientID){
                return false;
            }

            $db->where("name", "thisMonthWon");
            $db->where("reference", $productID);
            $db->where("client_id", $clientID);
            $thisProductWon = $db->getValue("client_setting", "id");

            if($thisProductWon) {
                $db->where("id", $thisProductWon);
                $db->update("client_setting", array("value" => $db->inc(1), "description" => $dateTime));
            } else {
                $insertData = array(
                    "name" => 'thisMonthWon',
                    "value" => 1,
                    "reference" => $productID,
                    "description" => $dateTime,
                    "client_id" => $clientID
                );

                $db->insert("client_setting", $insertData);
            }

            return true;
        }

        public function insertFirstJoinGame($startTime) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $directorID = Bonus::$directorID;
            $clientDataArr = Bonus::$clientDataAry;

            $db->where('start_date',$startTime);
            $gameDataArr = $db->map('id')->get('game',null,"id");
            if(!$gameDataArr){
                Log::write(date("Y-m-d H:i:s")." No Valid Game Room.\n");
                return false;
            }

            $minGameID = MIN(array_keys($gameDataArr));

            $db->where('game_id',$minGameID,">=");
            $db->where('client_id',$directorID,">");
            $db->orderBy('created_at',"ASC");
            $gameRes = $db->get('game_detail',null,'game_id,client_id,created_at');
            foreach ($gameRes as $gameRow) {
                $clientIDArr[$gameRow['client_id']] = $gameRow['client_id'];
            }

            foreach ($gameRes as $gameRow) {
                $clientID   = $gameRow['client_id'];
                $gameID     = $gameRow['game_id'];
                $createdAt  = $gameRow['created_at'];

                /*Insert Bonus Count Date for first purchase*/
                $db->where('client_id',$clientID);
                $db->where('name','bonusCountDate');
                $checkID = $db->getValue('client_setting','id');
                if(!$checkID){
                    unset($insertData);
                    $insertData = array(
                                            "client_id" => $clientID,
                                            "name" => "bonusCountDate",
                                            "type" => "Bonus Setting",
                                            "value" => date("Y-m-d", strtotime($createdAt)),
                                            "reference" => $createdAt,
                                        );
                    $db->insert("client_setting", $insertData);
                }
            }
            return true;
        }   

        public function setPoolSetting($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $poolType   = trim($params['poolType']);
            $poolAmount = trim($params['poolAmount']);
            $poolDate   = trim($params['poolDate']);
            $dateTime   = date('Y-m-d H:i:s');
            unset($settingName,$poolDisplay);

            $userID     = $db->userID;
            $site       = $db->userType;

            $validPoolType = array("sysPoolAmount","sysKingPoolAmount");

            if($site != 'Admin'){
                return array("status" => "error", "code" => 1, "statusMsg" => $translations['E00105'][$language] /* Invalid User. */, "data" => "");
            }else{
                $db->where('id', $userID);
                $username = $db->getValue('admin', 'username');
            }

            if(!in_array($poolType, $validPoolType)){
                $errorFieldArr[] = array(
                                            'id'    => 'poolTypeError',
                                            'msg'   => $translations['E00983'][$language] /* Invalid Pool Type */
                                        );
            }

            if($poolAmount <= 0 || !is_numeric($poolAmount)){
                $errorFieldArr[] = array(
                                            'id'    => 'poolAmountError',
                                            'msg'   => $translations['E00224'][$language] /* Invalid Pool Amount */
                                        );
            }

            if(!$poolDate){
                $errorFieldArr[] = array(
                                                'id'    => 'poolDateError',
                                                'msg'   => $translations['E00982'][$language] /* Invalid Pool Date */
                                            );
            }else{
                $poolDate = date('Y-m-01',strtotime($poolDate));

                switch ($poolType) {
                    case 'sysPoolAmount':
                        $poolDisplay = "Jackpot Pool";
                        $db->where('name','resetJackpotPool');
                        break;
                    case 'sysKingPoolAmount':
                        $poolDisplay = "King Pool";
                        $db->where('name','resetKingPool');
                        break;
                }

                $poolSetting = $db->getOne('system_settings','value,reference');
                $nextPoolDate = date('Y-m-d',strtotime("+".$poolSetting['reference']." ".$poolSetting['value']));

                if(strtotime($poolDate) < strtotime($nextPoolDate)){
                    $errorFieldArr[] = array(
                                                'id'    => 'poolDateError',
                                                'msg'   => $translations['E00982'][$language] /* Invalid Pool Date */
                                            );
                }
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);

            $insertData = array(
                "name"      => $poolType,
                "type"      => "Pool Setting",
                "value"     => $poolAmount,
                "created_at"=> $dateTime,
                "creator_id"=> $userID,
                "status"    => "Active",
                "active_at" => $poolDate,
            );
            $db->insert('system_settings_admin',$insertData);

            $activityData = array('admin'=>$username,'poolType'=>$poolDisplay,'poolAmount'=>$poolAmount,'poolDate'=>$poolDate);
            Activity::insertActivity('Set Pool','T00044','L00064',$activityData,$userID);

            return array('status'=>'ok','code'=>0,'statusMsg'=>$translations['B00373'][$language],'data'=>'');
        }

         public function getPoolData() {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime   = date('Y-m-d H:i:s');

            $userID     = $db->userID;
            $site       = $db->userType;

            if($site != 'Admin'){
                return array("status" => "error", "code" => 1, "statusMsg" => $translations['E00105'][$language] /* Invalid User. */, "data" => "");
            }

            $db->where('type','Pool Setting');
            $db->where("active_at",$dateTime,"<=");
            $db->groupBy('name');
            $poolRes = $db->map('name')->get('system_settings_admin',null,'name,value,active_at');

            foreach ($poolRes as $settingName => $poolRow) {
                switch ($settingName) {
                    case 'sysPoolAmount':
                        $poolAmount = Custom::getPoolBalance("jackpotPool");
                        $sysPoolAmount = Custom::getPoolBalance("sysJackpotPool");
                        $poolDisplay = $translations['B00396'][$language]?$translations['B00396'][$language]:"Jackpot Pool";
                        break;

                    case 'sysKingPoolAmount':
                        $poolAmount = Custom::getPoolBalance("kingPool");
                        $sysPoolAmount = Custom::getPoolBalance("sysKingPool");
                        $poolDisplay = $translations['B00397'][$language]?$translations['B00397'][$language]:"King Pool";;
                        break;
                }

                $poolData[$settingName]["poolDisplay"] = $poolDisplay;
                $poolData[$settingName]["settingAmount"] = $poolRow['value'];
                $poolData[$settingName]["poolBal"] = $poolAmount;
                $poolData[$settingName]["sysPoolBal"] = $sysPoolAmount;
            }

            $data['poolData'] = $poolData;

            return array('status'=>'ok','code'=>0,'statusMsg'=>$translations['E00547'][$language],'data'=>$data);
        }

        public function processCancelPortfolio($endTime,$productID) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(!$productID){
                Log::write(date("Y-m-d H:i:s") . " Invalid Product ID.\n");
                return false;
            }

            if(!$endTime) $endTime = date('Y-m-d H:i:s');


            $db->where('product_id',$productID);
            $db->where('status', 'Active');
            $db->where('created_at',$endTime,"<=");
            $portfolioIDArr = $db->map('belong_id')->get('mlm_client_portfolio',null,'belong_id,id');

            if(!$portfolioIDArr) {
                Log::write(date("Y-m-d H:i:s") . " Invalid Portfolio.\n");
                return false;
            }

            $db->where('belong_id', array_keys($portfolioIDArr),"IN");
            $invoiceDataArr = $db->map('id')->get('mlm_invoice', null, 'id,client_id,belong_id');

            if($invoiceDataArr){
                $db->where('invoice_id', array_keys($invoiceDataArr),"IN");
                $paymentRes = $db->get('mlm_invoice_item_payment', null, 'invoice_id,credit_type, amount');
                foreach ($paymentRes as $paymentRow) {
                    $paymentArr[$paymentRow['invoice_id']][$paymentRow['credit_type']] = $paymentRow['amount'];
                }
            }

            $db->where('name', 'creditRefund');
            $internalID = $db->getValue('client', 'id');
            $batchID = $db->getNewID();

            foreach($paymentArr as $invoiceID => $paymentData) {

                $clientID       = $invoiceDataArr[$invoiceID]['client_id'];
                $invBelongID    = $invoiceDataArr[$invoiceID]['belong_id'];
                $portfolioID    = $portfolioIDArr[$invBelongID];
                $belongID       = $db->getNewID();

                foreach ($paymentData as $creditType => $paymentAmount) {

                    Log::write(date("Y-m-d H:i:s") . " Cancel Portfolio ID - ".$portfolioID." Refund Amount : ".$paymentAmount.".\n");

                    if($paymentAmount > 0){
                        $insertTAccountResult = Cash::insertTAccount($internalID, $clientID, $creditType, $paymentAmount, 'Refund Portfolio', $belongID, '', $endTime, $batchID, $clientID, '',$portfolioID);
                        if(!$insertTAccountResult) {
                            Log::write(date("Y-m-d H:i:s") . " Failed to insert data.\n");
                        }
                    }
                }
                $db->where('id', $portfolioID);
                $db->update('mlm_client_portfolio', array('status' => 'Refund', 'refunded_at' => $endTime));
            }

            return true;
        }

        public function refundPortfolio($clientID,$productID,$dateTime,$isWinOut) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            unset($invalidSponsor);

            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');

            $db->where('introducer_id',$clientID);
            $downlineArr = $db->getValue('client','id',null);

            $db->where('name','minValidDownline');
            $minValidDownline = $db->getValue('system_settings','value');

            if($downlineArr){
                $db->where('client_id',$downlineArr,"IN");
                $db->where('name','bonusCountDate');
                $validDownline = $db->getValue('client_setting','Count(id)');
            }

            if($validDownline < $minValidDownline){
                Log::write(date("Y-m-d H:i:s") . " Min Valid Downline : ".$minValidDownline." Valid Downline : ".$validDownline.".\n");
                $invalidSponsor = 1;
            }

            if($isWinOut){
                $portfolioStatus = 'Pending';
                if(!$productID){
                    Log::write(date("Y-m-d H:i:s") . " Invalid Product ID.\n");
                    return false;
                }
            }else{
                $portfolioStatus = 'Holding';
                if($invalidSponsor){
                    return false;
                }
            }

            $db->where('client_id',$clientID);
            if($productID) $db->where('product_id',$productID);
            $db->where('status', $portfolioStatus);
            $db->where('created_at',$dateTime,"<=");
            $portfolioIDArr = $db->map('belong_id')->get('mlm_client_portfolio',null,'belong_id,id');
            if(!$portfolioIDArr) {
                Log::write(date("Y-m-d H:i:s") . " Invalid Portfolio.\n");
                return false;
            }

            if($invalidSponsor){
                $db->where('id',$portfolioIDArr,'IN');
                $db->update('mlm_client_portfolio',array("status"=>"Holding"));
                return false;
            }

            $db->where('belong_id', array_keys($portfolioIDArr),"IN");
            $invoiceDataArr = $db->map('id')->get('mlm_invoice', null, 'id,client_id,belong_id');

            if($invoiceDataArr){
                $db->where('invoice_id', array_keys($invoiceDataArr),"IN");
                $paymentRes = $db->get('mlm_invoice_item_payment', null, 'invoice_id,credit_type, amount');
                foreach ($paymentRes as $paymentRow) {
                    $paymentArr[$paymentRow['invoice_id']][$paymentRow['credit_type']] = $paymentRow['amount'];
                }
            }

            $db->where('name', 'creditRefund');
            $internalID = $db->getValue('client', 'id');
            $batchID = $db->getNewID();

            foreach($paymentArr as $invoiceID => $paymentData) {

                $clientID       = $invoiceDataArr[$invoiceID]['client_id'];
                $invBelongID    = $invoiceDataArr[$invoiceID]['belong_id'];
                $portfolioID    = $portfolioIDArr[$invBelongID];
                $belongID       = $db->getNewID();

                foreach ($paymentData as $creditType => $paymentAmount) {

                    Log::write(date("Y-m-d H:i:s") . " Cancel Portfolio ID - ".$portfolioID." Refund Amount : ".$paymentAmount.".\n");

                    if($paymentAmount > 0){
                        $insertTAccountResult = Cash::insertTAccount($internalID, $clientID, $creditType, $paymentAmount, 'Refund Portfolio', $belongID, '', $dateTime, $batchID, $clientID, '',$portfolioID);
                        if(!$insertTAccountResult) {
                            Log::write(date("Y-m-d H:i:s") . " Failed to insert data.\n");
                        }
                    }
                }
                $db->where('id', $portfolioID);
                $db->update('mlm_client_portfolio', array('status' => 'Refund', 'refunded_at' => $dateTime));
            }

            return true;
        }

        public function setPrioritizeSwitch($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $switchType   = trim($params['switchType']);
            $category    = trim($params['category']);
            $dateTime   = date('Y-m-d H:i:s');

            unset($switchValue);

            $userID     = $db->userID;
            $site       = $db->userType;

            $validSwitchType = array("on","off");

            if($site != 'Admin'){
                return array("status" => "error", "code" => 1, "statusMsg" => $translations['E00105'][$language] /* Invalid User. */, "data" => "");
            }else{
                $db->where('id', $userID);
                $username = $db->getValue('admin', 'username');
            }

            if(!in_array($switchType, $validSwitchType)){
                $errorFieldArr[] = array(
                                            'id'    => 'switchTypeError',
                                            'msg'   => $translations['E00981'][$language] /* Edit Phone Number is  Not allow */
                                        );
            }

            if(!$category){
                $errorFieldArr[] = array(
                                            'id'    => 'categoryError',
                                            'msg'   => $translations['E00955'][$language] /* Invalid Product */
                                        );
            }else{
                $db->where('ref_id',$category);
                $db->where('name','prioritizeNewbie');
                $settingID = $db->getValue('system_settings_admin','id');
                if(!$settingID){
                    $errorFieldArr[] = array(
                                            'id'    => 'categoryError',
                                            'msg'   => $translations['E00955'][$language] /* Invalid Product */
                                        );
                }
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);

            switch ($switchType) {
                case 'on':
                    $switchValue = 1;
                    $switchDisplay = "On";
                    break;

                case 'off':
                    $switchValue = 0;
                    $switchDisplay = "Off";
                    break;
            }

            $db->where('id',$settingID);
            $db->update('system_settings_admin',array("value"=>$switchValue));

            $activityData = array('admin'=>$username,'productCode'=>$category,'switchDisplay'=>$switchDisplay);
            Activity::insertActivity('Set Game Room Setting','T00045','L00065',$activityData,$userID);

            return array('status'=>'ok','code'=>0,'statusMsg'=>$translations['B00373'][$language],'data'=>'');
        }

        public function insertJackpotPool($category,$startTime,$endTime) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $directorID = Bonus::$directorID;
            $clientDataArr = Bonus::$clientDataAry;

            $db->where('status','completed');
            $db->where('start_date',$startTime);
            $db->where('product_category',$category);
            $gameIDArr = $db->map('id')->get('game',null,"id,batch_id");
            if(!$gameIDArr){
                Log::write(date("Y-m-d H:i:s")." No Valid Game Room.\n");
                return false;
            }

            $db->where('game_id',array_keys($gameIDArr),"IN");
            $db->where('winner','1');
            $winnerRes = $db->get('game_detail',null,'game_id,client_id,portfolio_id');
            foreach ($winnerRes as $winnerRow) {
                if($winnerRow['portfolio_id']>0){
                    $portfolioIDArr[$winnerRow['portfolio_id']] = $winnerRow['portfolio_id'];
                }
            }

            // Get Contribute Data
            $db->where('name','contribPercent');
            $db->where('ref_id',$category);
            $contribDataArr = $db->map('type')->get('system_settings_admin',null,'type,value,reference');
            if(!$contribDataArr){
                Log::write(date("Y-m-d H:i:s")." Invalid Contribute Setting. Failed to proceed.\n");
                return false;
            }

            if($portfolioIDArr){
                $db->where('id',$portfolioIDArr,"IN");
                $protfolioData = $db->map('id')->get('mlm_client_portfolio',null,'id,bonus_value');
            }

            foreach ($winnerRes as $winnerRow) {
                $clientID       = $winnerRow['client_id'];
                $gameID         = $winnerRow['game_id'];
                $portfolioID    = $winnerRow['portfolio_id'];
                $bonusValue     = $protfolioData[$portfolioID];
                $batchID        = $gameIDArr[$gameID];

                foreach ($contribDataArr as $poolType => $contribData) {
                    $contribPercent = $contribData['value'];
                    $defaultBV  = $contribData['reference'];
                    $prodBV = $bonusValue>0?$bonusValue:$defaultBV;

                    $contribAmt = Setting::setDecimal(($prodBV * ($contribPercent / 100)));
                    $subject = "Contribute from ".$category;

                    Log::write(date("Y-m-d H:i:s")." Game Room - ".$gameID." BV : ".$prodBV." x ".$contribPercent."% = Contribute Amount : ".$contribAmt." to ".$poolType.".\n");

                    Custom::insertPool($clientID,$poolType,$contribAmt,"In",$endTime,$subject,$batchID);
                }
            }

            return true;
        }

        public function setMemberCycleActive($clientID,$dateTime) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            //Get System Cycle Setting
            $db->where('name','Bonus Duration');
            $gameCycleRes = $db->getOne('system_settings','value,reference');

            //Get Member Join Game Date
            $db->where('client_id',$clientID);
            $db->where('name','bonusCountDate');
            $joinGameRes = $db->getOne('client_setting','id,value');
            $joinDate = $joinGameRes['value'];
            $joinDateStgID = $joinGameRes['id'];

            $activeDate = date('Y-m-d H:i:s',strtotime($joinDate." + ".$gameCycleRes['value']." ".$gameCycleRes['reference']));

            $db->where('id',$clientID);
            $db->update('client',array("active_date"=>$activeDate));

            //Record time for first won 3 time in this cycle
            $db->where('id',$joinDateStgID);
            $db->update('client_setting',array("description"=>$dateTime));

            return true;
        }

        public function insertMthPoolWinner($clientID,$batchID,$dateTime,$poolType) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            if(!$clientID) return false;

            if(!$poolType) return false;

            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');

            $db->where('client_id',$clientID);
            $db->where('paid',0);
            $checkID = $db->getValue('mlm_pool_bonus','id');
            if($checkID){
                Log::write(date("Y-m-d H:i:s")." Client : ".$clientID." Already insert pool record.\n");
                return false;
            }

            unset($insertData);
            $insertData = array(
                "client_id" => $clientID,
                "type" => $poolType,
                "batch_id" => $batchID,
                "created_at"=> $dateTime
            );
            $db->insert('mlm_pool_bonus',$insertData);

            return true;
        }
    }
?>
