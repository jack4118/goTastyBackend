<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../language/lang_all.php');

    log::setupLogPath(__DIR__, __FILE__);

    $language = "english";

    General::$translations = $translations;
    General::$currentLanguage = $language;

    $bonusDate  = date("Y-m-d", strtotime("-1 day"));
    $currentDate = date("Y-m-d");

    if ($argv[1]) {
        // If a bonus date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $bonusDate = $argv[1];
            $currentDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
        }
    }

    // Bonus::calculateActiveProgramBonus($bonusDate);
    // Bonus::payActiveProgramBonus($bonusDate);

    $db->where('name','processBonusSwitch');
    $stgRes = $db->getOne('system_settings','id,value');
    $processBonusSwitch = $stgRes['value'];
    $stgID = $stgRes['id'];
    while ($processBonusSwitch) {
        Log::write(date('Y-m-d H:i:s')." Waiting for Bonus Process...\n");

        $db->where('id',$stgID);
        $processBonusSwitch = $db->getValue('system_settings','value');

        sleep(1);
    }

    $db->where('name','resetBonusSwitch');
    $stgRes = $db->getOne('system_settings','id,value');
    $resetBonusSwitch = $stgRes['value'];
    $stgID = $stgRes['id'];
    while ($resetBonusSwitch) {
        Log::write(date('Y-m-d H:i:s')." Waiting for Reset Bonus Process...\n");

        $db->where('id',$stgID);
        $resetBonusSwitch = $db->getValue('system_settings','value');

        sleep(1);
    }

    $db->where('name','processBonusSwitch');
    $db->update('system_settings',array("value" => 1));

    unset($isAdminSet);
    $queueID    = $argv[2];
    $creatorID  = $argv[3];
    $creatorAt  = $argv[4]." ".$argv[5];
    if($queueID && $creatorID && $creatorAt){
        $db->where('id',$queueID);
        $queueDataRes = $db->getValue('queue','data');
        $queueData = json_decode($queueDataRes,true);

        $isAdminSet = 1;
        $logBaseName = basename(__FILE__, '.php');
        Log::setupLogPath(__DIR__, $logBaseName."-Admin");
    }

    $bonusArr = $db->subQuery();
    $bonusArr->where('disabled',0);
    $bonusArr->get('mlm_bonus',null,'name');
    $db->where('bonus_name',$bonusArr,"IN");
    $db->where('completed',1);
    $db->groupBy('bonus_date');
    $db->orderBy('bonus_date','DESC');
    $latestBonusDate = $db->getValue('mlm_bonus_calculation_batch','bonus_date');
    if(!$latestBonusDate) $latestBonusDate = date('Y-m-d',strtotime($bonusDate." -1 days"));
    if(strtotime($bonusDate) <= strtotime($latestBonusDate)){
        Log::write(date("Y-m-d H:i:s")." Bonus already run, failed to Run Bonus. \n");
        $db->where('name','processBonusSwitch');
        $db->update('system_settings',array("value" => 0));
        if($isAdminSet){
            $db->where('id',$queueID);
            $db->where('processed',2);
            $db->update('queue',array('processed'=>1));
            return false;
        }else{
            exit();
        }
    }

    $tgtBonusDate = $bonusDate;
    $bonusDate = date('Y-m-d',strtotime($latestBonusDate." +1 days"));

    while (strtotime($tgtBonusDate) >= strtotime($bonusDate)) {
        $db->where('queue_type','calculateRank');
        $db->where('processed',1,"!=");
        $db->where('DATE(created_at)',$bonusDate,"<=");
        $checkQueue = $db->getOne('queue','id');
        while ($checkQueue) {
            if($checkQueue && (!$startedQueueFlag)){
                //Open Bonus Switch For Queue
                $db->where('name','waitQueueFlag');
                $db->update('system_settings',array("value" => 1,"reference"=>$bonusDate));
                $startedQueueFlag = 1;
            }

            Log::write(date('Y-m-d H:i:s')." Waiting for Queue Process...\n");

            $db->where('queue_type','calculateRank');
            $db->where('processed',1,"!=");
            $db->where('DATE(created_at)',$bonusDate,"<=");
            $checkQueue = $db->getOne('queue','id');

            if(!$checkQueue){
                //Closed back Bonus Switch
                $db->where('name','waitQueueFlag');
                $db->update('system_settings',array("value" => 0));
                unset($startedQueueFlag);
            }

            sleep(1);
        }


        Log::write(date("Y-m-d H:i:s")." Start to run Bonus - ".$bonusDate." isAdminRun : ".$isAdminSet."\n");
        unset($waitQueueFlag);

        Log::write(date("Y-m-d H:i:s")." Terminate New Account with no starterKit purchase in 2 days... \n");
        $checkingDate = date("Y-m-d", strtotime($bonusDate.' -1days'));

        $db->where('DATE(created_at)', $checkingDate);
        $newAccounts = $db->get('client',null,'id');
        $terminated = 0;
        if($newAccounts){
            foreach($newAccounts as $newAccountsID){
                $db->where('client_id',$newAccountsID['id']);
                $db->where('DATE(created_at)',$bonusDate,'<=');
                $check = $db->get('mlm_client_portfolio');
                if(!$check){
                    $db->where("id", $newAccountsID['id']);
                    $updateStatus = $db->update('client', array("terminated" => 1));
                    if($updateStatus) {
                        $terminated += 1;

                        $db->where('client_id',$newAccountsID['id']);
                        $db->where('name','terminatedAt');
                        $updateTerminated = $db->copy();
                        $recordChecking = $db->getOne('client_setting');
                        if($recordChecking){
                            $updateTerminated->update('client_setting',array("value" => $bonusDate));
                        }else{
                            $insertData = array(
                                "name"      => "terminatedAt",
                                "value"     => $bonusDate,
                                "client_id" => $newAccountsID['id'],

                            );
                            $db->insert('client_setting',$insertData);
                        }
                    }
                }
            }
        }
        Log::write(date("Y-m-d H:i:s")." Terminated ".$terminated." accounts... \n");

        //Daily check account status for yearly update status for member - reset leadership reward / terminate account
        Log::write(date("Y-m-d H:i:s")." Start Account Status Maintain... \n");
        Bonus::accountStatusMaintain($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Done Account Status Maintain... \n");

        //1014 Bonus::duplicateClientSales($bonusDate);

        $currentDate = date("Y-m-d", strtotime($bonusDate." +1 day"));
        General::insertDailyTable("acc_credit",null,$currentDate);

        Bonus::cacheDailyTable('tree_sponsor', $bonusDate, $queueData['isUpdateSponsorCache']);
        Bonus::cacheDailyTable('tree_placement', $bonusDate, $queueData['isUpdateSponsorCache']);

        // $firstDay = date('Y-m-01', strtotime($currentDate));
        // if($currentDate == $firstDay){
        //     // update member discount percentage
        //     Log::write(date("Y-m-d H:i:s")." Start Update Member Discount Percentage\n");
        //     Custom::updateMemberDiscountPerc($currentDate);
        //     Log::write(date("Y-m-d H:i:s")." Finish Update Member Discount Percentage\n");
        // }

        //take all bonus setting and details
        Bonus::bonusPreset($bonusDate,$creatorID);

        // // Monthly Calculate
        // Log::write(date("Y-m-d H:i:s")." Start Running Goldmine Bonus\n");
        // Bonus::calculateGoldmineBonus($bonusDate);
        // Log::write(date("Y-m-d H:i:s")." Finish Running Goldmine Bonus\n");

        // // Monthly Calculate
        // Log::write(date("Y-m-d H:i:s")." Start Running Team Bonus\n");
        // Bonus::calculateTeamBonus($bonusDate);
        // Log::write(date("Y-m-d H:i:s")." Finish Running Team Bonus\n");

        // // Monthly Calculate
        // Log::write(date("Y-m-d H:i:s")." Start Running Leadership Bonus\n");
        // Bonus::calculateLeadershipBonus($bonusDate);
        // Log::write(date("Y-m-d H:i:s")." Finish Running Leadership Bonus\n");

        // // Daily Check
        // Log::write(date("Y-m-d H:i:s")." Start Check Inactive Member\n");
        // Custom::expiredMemberActiveStatus($bonusDate);
        // Log::write(date("Y-m-d H:i:s")." Finish Check Inactive Member\n");

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Bonus Tier [1]\n");
        Bonus::calculateBonusTier('',$bonusDate, 'starterKit', 'Bonus Tier');
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Bonus Tier [1]\n");

        sleep(10); // Give it a 10 seconds delay

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Bonus Tier [2]\n");
        Bonus::calculateBonusTier('',$bonusDate, 'fizMemberUpgrade', 'Bonus Tier');
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Bonus Tier [2]\n");

        sleep(10); // Give it a 10 seconds delay

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Enrollment Bonus\n");
        Bonus::calculateEnrollmentBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Enrollment Bonus\n");
        Log::write(date("Y-m-d H:i:s")." Start Pay Enrollment Bonus\n");
        Bonus::payEnrollmentBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Pay Enrollment Bonus\n");

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Couple Bonus\n");
        Bonus::calculateCoupleBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Couple Bonus\n");
        Log::write(date("Y-m-d H:i:s")." Start Pay Couple Bonus\n");
        Bonus::payCoupleBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Pay Couple Bonus\n");

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Bonus Tier [3]\n");
        Bonus::calculateBonusTier('',$bonusDate, 'couple', 'Bonus Tier');
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Bonus Tier [3]\n");

        sleep(10); // Give it a 10 seconds delay

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Unilevel Bonus\n");
        Bonus::calculateUnilevelBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Unilevel Bonus\n");
        Log::write(date("Y-m-d H:i:s")." Start Pay Unilevel Bonus\n");
        Bonus::payUnilevelBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Pay Unilevel Bonus\n");

        //Daily Calculate
        Log::write(date("Y-m-d H:i:s")." Start Calculate Leadership Reward Bonus\n");
        Bonus::calculateLeadershipRewardBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Calculate Leadership Reward Bonus\n");
        Log::write(date("Y-m-d H:i:s")." Start Pay Leadership Reward Bonus\n");
        Bonus::payLeadershipRewardBonus($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Pay Leadership Reward Bonus\n");



        // //Recalculate Rank
        // if(date("Y-m-d",strtotime($bonusDate)) == date("Y-m-t",strtotime($bonusDate))){
        //     Bonus::insertMonthlyDetail($bonusDate);
        //     Bonus::insertRankMonthly($bonusDate);
        //     $waitQueueFlag = 1;
        // }

        // // // Monthly Calculate
        // Bonus::calculateAwardBonus($bonusDate);

        // Custom::calculateRecruitPromo($bonusDate);

      /*  //Insert Bonus Payout Summary
        Log::write(date("Y-m-d H:i:s")." Start Insert Bonus Payout Summary\n");
        Bonus::insertBonusPayoutSummary($bonusDate);
        Log::write(date("Y-m-d H:i:s")." Finish Insert Bonus Payout Summary\n");*/

        // Log::write(date("Y-m-d H:i:s")." Reset Award Cycle\n");
        // Custom::resetAwardCycle($bonusDate);
        // Log::write(date("Y-m-d H:i:s")." Finish Reset Award Cycle\n");

        $bonusDate = date('Y-m-d',strtotime($bonusDate." +1 days"));

        sleep(1);
    }

    if($isAdminSet){
        $db->where('id',$queueID);
        $db->where('processed',2);
        $db->update('queue',array('processed'=>1));
    }

    $db->where('name','processBonusSwitch');
    $db->update('system_settings',array("value" => 0));

    Log::write(date("Y-m-d H:i:s")." Done Process Bonus. \n");

    // include_once("processAuditReport.php");
?>
