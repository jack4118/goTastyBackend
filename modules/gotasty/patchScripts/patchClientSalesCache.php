<?php
    $currentPath = __DIR__;

    include_once($currentPath.'/../include/classlib.php');
    include_once($currentPath.'/../include/class.lang_all.php');

    General::$currentLanguage = 'english';
    General::$translations    = $translations;
    Log::setupLogPath(__DIR__, __FILE__);

    echo "Start patch\n";
    $bonusDate = "2022-02-28";
    $currentTime = date('Y-m-d H:i:s');
    $insertDate = date('Y-m-d 23:59:59',strtotime($bonusDate));
    $monthFirstDate = date('Y-m-01',strtotime($bonusDate));
    Bonus::bonusPreset($bonusDate,$creatorID);

    echo date("Y-m-d H:i:s")." Start Recalculate Rank \n";
    $db->where('type','Client');
    $db->orderBy('id','DESC');
    $clientRes = $db->get('client',null,'id,username');
    foreach ($clientRes as $clientRow) {
        $clientID = $clientRow['id'];
        echo date('Y-m-d H:i:s')." Username : ".$clientRow['username']."\n";
        Custom::calculateClientRank($clientID,$insertDate);
    }
    echo date("Y-m-d H:i:s")." finish Recalculate Rank \n";

    echo date("Y-m-d H:i:s")." Start Duplicate Client Sales \n";
    Bonus::duplicateClientSales($bonusDate);
    echo date("Y-m-d H:i:s")." Finish Duplicate Client Sales \n";

    echo date("Y-m-d H:i:s")." Start Calculate Recruit Promo \n";
    $db->where('bonus_name','Recruit Promo');
    $db->where("bonus_date",$bonusDate);
    $db->where("completed","1");
    $db->update('mlm_bonus_calculation_batch',array("completed"=>0));

    Custom::calculateRecruitPromo($bonusDate);
    echo date("Y-m-d H:i:s")." Finish Calculate Recruit Promo \n";

    echo date("Y-m-d H:i:s")." Start Record Monthly Rank \n";
    $clientRankArr = Bonus::getClientRank("Bonus Tier", "", $insertDate, "goldmineBonus","System");

    //Get Award Bonus Setting
    $db->where('name',array('directorAward','unicornAward'),"IN");
    $awardBonusStgRes = $db->get('mlm_bonus_setting',null,'name,reference');
    foreach ($awardBonusStgRes as $awardBonusStgRow) {
        $awardReqArr = explode("#", $awardBonusStgRow['reference']);
        $awardBonusStgArr[$awardBonusStgRow['name']]['minRankPriority'] = $awardReqArr[0];
        $awardBonusStgArr[$awardBonusStgRow['name']]['minPGP'] = $awardReqArr[2];
    }

    //Get Client Sales
    $db->where('DATE(updated_at)',$monthFirstDate,">=");
    $db->where('DATE(updated_at)',$bonusDate,"<=");
    $clientSalesRes = $db->get('client_sales_cache',null,'client_id,pgp_sales,own_sales');
    foreach ($clientSalesRes as $clientSalesRow) {
        $clientPGPSales[$clientSalesRow['client_id']] = ($clientSalesRow['pgp_sales'] + $clientSalesRow['own_sales']);
    }

    // Get Rank Data
    $rankData = $db->map('id')->get('rank',null,'id,priority');

    foreach ($clientRankArr as $clientID => $clientRankRow) {
        unset($insertData,$updateData,$isEntitle);
        //Director Rank = type column
        if(($rankData[$clientRankRow['rank_id']] == $awardBonusStgArr['directorAward']['minRankPriority']) && ($clientPGPSales[$clientID]>=$awardBonusStgArr['directorAward']['minPGP'])){
            $updateData['type'] = $db->inc(1);
            $isEntitle = 1;

        }elseif($rankData[$clientRankRow['rank_id']] == $awardBonusStgArr['unicornAward'] && ($clientPGPSales[$clientID]>=$awardBonusStgArr['directorAward']['minPGP'])){
            $updateData['reference'] = $db->inc(1);
            $isEntitle = 1;
        }

        Log::write(date("Y-m-d H:i:s") . " Client : ".$clientID." Rank : ".$clientRankRow['rank_id']." PGP Sales + Own Sales : ".$clientPGPSales[$clientID]."\n");

        $db->where('client_id',$clientID);
        $db->where('rank_id',$clientRankRow['rank_id']);
        $db->where('created_at',$insertDate);
        $rankMonthlyID = $db->getValue('client_rank_monthly','id');
        if($rankMonthlyID){
            $db->where('id',$rankMonthlyID);
            $db->update('client_rank_monthly',array("entitle_award"=>$isEntitle));
        }else{
            echo " Client ID : ".$clientID." Got different.\n";
        }

        // Update Client Setting
        if($updateData){
            $db->where('client_id',$clientID);
            $db->where('name','awardCycleDate');
            $db->update('client_setting',$updateData);
        }
    }
    echo date("Y-m-d H:i:s")." Finish Record Monthly Rank \n";

    echo date("Y-m-d H:i:s")." Reset Rank...\n";
    foreach ($clientRes as $clientRow) {
        $clientID = $clientRow['id'];
        echo date('Y-m-d H:i:s')." Username : ".$clientRow['username']."\n";
        Custom::calculateClientRank($clientID,$currentTime);
    }

    echo "Done patch\n";
?>