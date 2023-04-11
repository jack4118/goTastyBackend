<?php
    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');

    ## FUNCTION VARIABLE DECLARATION ##
    $language = "english";
    $eventDescription = "";
    $dateHrs = date("H");
    
    if($dateHrs >= 21){
        
        $startDate = date("Y-m-d 00:00:00");
        $endDate = date("Y-m-d 21:00:00");
        $endDateDisplay = date("Y-m-d") . " 9PM";//" 21:00:00";
        $dailyBonusDate = date("Y-m-d",strtotime("-1 day"));
        $db->where('created_at', $startDate, '>=');
        $db->where('created_at', $endDate, '<=');
        $createdFilter = $db->copy();
        $bonusCreatedFilter = $db->copy();

    }else if($dateHrs >= 15){
        
        $startDate = date("Y-m-d 00:00:00");
        $endDate = date("Y-m-d 15:00:00");
        $endDateDisplay = date("Y-m-d") . " 3PM";//" 15:00:00";
        $dailyBonusDate = date("Y-m-d",strtotime("-1 day"));
        $db->where('created_at', $startDate, '>=');
        $db->where('created_at', $endDate, '<=');
        $createdFilter = $db->copy();
        $bonusCreatedFilter = $db->copy();

    }else{
        
        $startDate = date("Y-m-d 00:00:00", strtotime("- 1 day"));
        $endDate = date("Y-m-d 00:00:00");
        $endDateDisplay = date("Y-m-d") . " 12AM";//" 00:00:00";
        $dailyBonusDate = date("Y-m-d",strtotime("-1 day"));
        $db->where('created_at', $startDate, '>=');
        $db->where('created_at', $endDate, '<=');
        $createdFilter = $db->copy();
        
        $db->resetState();
        $db->where('created_at', $startDate, '>');
        $db->where('created_at', $endDate, '<=');
        $bonusCreatedFilter = $db->copy();

    }

    ## Default Credit Language ##
    //get payment credit
    $db->resetState();
    $result = $db->get('mlm_payment_method',null,'credit_type');
    foreach ($result as $row) {
        $mainPaymentCreditArray[$row['credit_type']] = $row['credit_type'];
    }
    
    $result = $cash->getPaymentCredit();
    foreach ($result as $key => $value) {
        if ($mainPaymentCreditArray[$key]) {
            foreach ($value as $subCredit) {
                $paymentCreditArray[] = $subCredit;
            }
        }
    }
    $paymentCreditArray = array_unique($paymentCreditArray);
    
    $tblDate = date('Ymd', strtotime($startDate));
    $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".$db->escape($tblDate)." LIKE acc_credit");
    $tblDate = date('Ymd', strtotime($endDate));
    $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS acc_credit_".$db->escape($tblDate)." LIKE acc_credit");
    
    // Get credit language
    $creditLangArray = array();
    $result = $db->get('credit',null,'name,admin_translation_code');
    foreach ($result as $row) {
        $creditLangArray[$row['name']] = $row['admin_translation_code'];
    }

    //get internal id ary
    $db->where('type', "Internal");
    $internalIDAry = $db->map("id")->get("client", null, "id");

    ## Default Display Function ##
    $decimal = 8;
    
    function tallyCheck($a, $b) {
        global $decimal;

        return "Result - " . (number_format($a, $decimal, ".", "") == number_format($b, $decimal, ".", "") ? "Tally" : "Not Tally (Diff:" . abs($a - $b) . ")") . "\n";
    }

    function tallyCheckString($a, $b) {
        global $decimal;

        return "Result - " . (bccomp($a, $b, $decimal) ? "Tally" : "Not Tally (diff:" . bcsub($a, $b, $decimal) . ")") . "\n";
    }

    function amountDisplay($amount) {
        global $decimal;
        
        if (!$amount) {
            $amount = 0;
        }

        return number_format($amount, $decimal, ".", "");
    }

    function setDecimal($amount, $decimal=2){
      
        $floor = pow(10, $decimal); // floor for extra decimal
        $convertedAmount = number_format( (floor(strval($amount*$floor))/$floor) , $decimal , '.', '');

        return $convertedAmount;
    }

    $mask0 = "%2.2s | %-25.25s\n";
    $mask1 = "%15.15s | %-25.25s\n";
    $mask2 = "%s%.8f | %-20.20s\n";

    function printfDisplay($amount, $display) {
        $display = str_replace('Amount','Amt',$display);
        $display = str_replace('Additional','Add.',$display);
        $display = str_replace('Purchase Voucher','P.Voucher',$display);
        $display = str_replace('Community','Comm',$display);
        $display = str_replace('Sponsor','Spon',$display);
        $display = str_replace('Internation Dividend','Int. Div',$display);
        $display = str_replace('Withholding','With.',$display);
        $display = str_replace('Income Multiplier','In.Mul',$display);
        $display = str_replace('Cash Credit -','CC',$display);
        $display = str_replace('Commucnity','Comm',$display);
        $display = str_replace('Rewards','RWD',$display);
        $display = str_replace('Daily','D.',$display);
        $display = str_replace(' - ','-',$display);
        $display = str_replace('Withdrawal','W/D',$display);
        $display = str_replace('Rejected','Reject',$display);
        $display = str_replace('Cancelled','Cancel',$display);
        $display = str_replace('Payout','P/O',$display);
        global $mask0, $mask1;
        ob_start();
        printf($mask1, amountDisplay($amount), $display);

        $result = ob_get_contents();
        ob_end_clean();

        return $result;

    }

    function printfTransaction($symbol, $amount, $display) {
        $display = str_replace('Amount','Amt',$display);
        $display = str_replace('Additional','Add.',$display);
        $display = str_replace('Management','MGT',$display);
        $display = str_replace('Community','Comm',$display);
        $display = str_replace('Sponsor','Spon',$display);
        $display = str_replace('Rewards','RWD',$display);
        $display = str_replace(' - ','-',$display);
        $display = str_replace('Withdrawal','W/D',$display);
        $display = str_replace('Rejected','Reject',$display);
        $display = str_replace('Cancelled','Cancel',$display);
        $display = str_replace('Payout','P/O',$display);

        global $mask2, $decimal;
        ob_start();

        printf($mask2, $symbol, amountDisplay($amount), $display);
        $result = ob_get_contents();

        ob_end_clean();
        return $result;
    }

    ## Sales ##
    $totalBV = 0;
    $totalSales = 0;

    $db = $createdFilter->copy();
    $db->where('product_price', '0', '>');
    $db->where('tier_value', '0');
    $portfolioRes = $db->get('mlm_client_portfolio',null,'id,bonus_value,belong_id,batch_id, product_price');
    $totalSalesBV = 0;
    foreach ($portfolioRes as $portfolioRow) {
        $totalSalesBV += $portfolioRow['bonus_value'];
        $portfolioBelongArray[] = $portfolioRow['batch_id'];
        $portfolioIDArray[] = $portfolioRow['id'];
        $totalPortfolioPrice += $portfolioRow['product_price'];
    }
    
    $eventDescription .= printfDisplay($totalSalesBV, "Sales BV");
    
    if (count($paymentCreditArray) > 0) {
        $totalSalesAmount = 0;
        if (count($portfolioBelongArray) > 0) {
            $db = $createdFilter->copy();
            $db->where('type',$paymentCreditArray,'IN');
            $db->where('from_id',$internalIDAry,'NOT IN');
            $db->where('batch_id',$portfolioBelongArray,'IN');
            $db->groupBy('type');
            $creditSalesAmountArray = $db->get('credit_transaction',null,'type,SUM(amount) AS amount');
            foreach ($salesAmountArray as $salesAmount) {
                $totalSalesAmount += $salesAmount['amount'];
            }
        }

         if (count($portfolioIDArray) > 0) {
            // $db = $createdFilter->copy();
            // $db->where('type',$paymentCreditArray,'IN');
            // $db->where('from_id',$internalID,'NOT IN');
            $db->where('portfolio_id',$portfolioIDArray,'IN');
            // $db->groupBy('type');
            $salesAmountArray = $db->get('mlm_invoice_item',null,'SUM(product_price) AS amount');

            foreach ($salesAmountArray as $salesAmount) {
                $totalSalesAmount += $salesAmount['amount'];
            }
        }

        $eventDescription .= printfDisplay($totalSalesAmount, "Sales Amount");

        // Get transaction amount for each payment credit
        $sumUsedAmount = 0;
        foreach ($creditSalesAmountArray as $creditSalesAmount) {
            $eventDescription .= printfDisplay($creditSalesAmount['amount'], "Used ".$translations[$creditLangArray[$creditSalesAmount['type']]][$language]);
        }

        // Sales tally check
        $eventDescription .= tallyCheck($totalSalesAmount,$totalPortfolioPrice);
    }
    ## Sales END ##
    $eventDescription .= "\n";

    ## Withdrawal ##'
    // Get total withdrawal amount
    $db = $createdFilter->copy();
    $totalWithdrawalAmount = $db->getValue('mlm_withdrawal','SUM(amount)');
    $eventDescription .= printfDisplay($totalWithdrawalAmount, "Withdrawal Amount");

    $sumWithdrawalAmount = 0;
    if (count($paymentCreditArray) > 0) {
        foreach ($paymentCreditArray as $paymentCredit) {
            $db = $createdFilter->copy();
            $db->where('type',$paymentCredit);
            $db->where('subject',array('Withdrawal','Admin Charge'),'IN');
            $withdrawalAmount = $db->getValue('credit_transaction','SUM(amount)');
            if ($withdrawalAmount > 0) $eventDescription .= printfDisplay($withdrawalAmount, "Deducted ".$translations[$creditLangArray[$paymentCredit]][$language]);

            $sumWithdrawalAmount += $withdrawalAmount;
        }
    }
    $eventDescription .= tallyCheck($totalWithdrawalAmount,$sumWithdrawalAmount);
    $eventDescription .= "\n";

    ## Withdrawal END ##

    ## Withdrawal Rejected / Cancel ##

    // Get rejected/cancelled withdrawal
    $db->where('approved_at', $startDate, '>=');
    $db->where('approved_at', $endDate, '<=');
    $db->where('status',array('Reject','Cancel'),'IN');
    $totalFailedWithdrawal = $db->getValue('mlm_withdrawal','SUM(amount)');
    $eventDescription .= printfDisplay($totalFailedWithdrawal, "Rejected/Cancelled Withdrawal Amount");

    // Get returned amount
    $db = $createdFilter->copy();
    $db->where('subject','Withdrawal Return');
    $totalReturnedWithdrawal = $db->getValue('credit_transaction','SUM(amount)');
    $eventDescription .= printfDisplay($totalReturnedWithdrawal, "Returned Withdrawal Amount");

    $eventDescription .= tallyCheck($totalFailedWithdrawal,$totalReturnedWithdrawal);

    ## Withdrawal Rejected / Cancel End ##

    $eventDescription .= "\n";

    ## Fund In ##

    $db->where('call_back_on', $startDate, '>=');
    $db->where('call_back_on', $endDate, '<=');
    $db->where('status',array('Success'),'IN');
    $fundInTotal = $db->getValue('mlm_fund_in','SUM(receivable_amount)');

    $db->where('created_at', $startDate, '>=');
    $db->where('created_at', $endDate, '<=');
    $db->where('subject','%Fund In', 'LIKE');
    $fundInRes = $db->get('credit_transaction',null,'type,amount');

    foreach ($fundInRes as $fKey => $fundInRow) {
        $fundInAmount[$fundInRow["type"]] += $fundInRow["amount"];
    }

    $eventDescription .= printfDisplay($fundInTotal, "Fund In Amount");

    foreach ($fundInAmount as $fundInCreditType => $crytoAmount) {

        if($fundInAmount[$fundInCreditType] <= 0) continue;

        // $spacing = spacing(number_format($fundInTotal, $decimal, ".", ""),number_format($fundInAmount[$fundInCreditType],$decimal,".",""));
        $eventDescription .= printfDisplay(number_format($fundInAmount[$fundInCreditType],$decimal,".",""),"Total ".$translations[$creditLangArray[$fundInCreditType]][$language]." In");

        $totalFundInPayment += number_format($fundInAmount[$fundInCreditType], $decimal, ".", "");
    }
    // Get Fund IN
    $eventDescription .= tallyCheck($fundInTotal, $totalFundInPayment)."\n";
    
    ## Fund In End ##

    ## Bonus Payout ##

    $db->where('table_name','','!=');
    $bonusArray = $db->get('mlm_bonus',null,'name,table_name,payment, language_code');
    $totalCalculatedBonus = 0;
    if (count($bonusArray) > 0) {
        foreach ($bonusArray as $bonus) {

            if($bonus["payment"] == "Daily"){
                $db->where('bonus_date',$dailyBonusDate);
                $db->where('paid',1);
                $calculatedBonus = $db->getValue($bonus['table_name'],'SUM(payable_amount)');
                $totalCalculatedBonus += $calculatedBonus;
            }else{
                if(date("d", strtotime($dailyBonusDate)) == "15"){
                    $db->where('bonus_date',date("Y-m-01", strtotime($dailyBonusDate)),">=");
                    $db->where('bonus_date',$dailyBonusDate,"<=");
                    $db->where('paid',1);
                    $calculatedBonus = $db->getValue($bonus['table_name'],'SUM(payable_amount)');
                    $totalCalculatedBonus += $calculatedBonus;
                }else if(date("Y-m-d",strtotime($dailyBonusDate)) == date("Y-m-t",strtotime($dailyBonusDate))){
                    $db->where('bonus_date',date("Y-m-16", strtotime($dailyBonusDate)),">=");
                    $db->where('bonus_date',$dailyBonusDate,"<=");
                    $db->where('paid',1);
                    $calculatedBonus = $db->getValue($bonus['table_name'],'SUM(payable_amount)');
                    $totalCalculatedBonus += $calculatedBonus;
                }
            }
        }
        $eventDescription .= printfDisplay($totalCalculatedBonus, "Bonus Calculated Amount");
    }

    $totalPaidBonus = 0;
    $bonusPayoutCreditArray = array();
    $bonusPayoutRes = $db->get('mlm_bonus_payment_method',null,'credit_type');
    foreach ($bonusPayoutRes as $bonusPayoutRow) {
        $bonusPayoutCreditArray[$bonusPayoutRow['credit_type']] = $bonusPayoutRow['credit_type'];
    }

    if (count($bonusPayoutCreditArray) > 0) {
        foreach ($bonusPayoutCreditArray as $bonusPayoutCredit) {
            $paidBonus = 0;
            
            $db = $bonusCreatedFilter->copy();
            $db->where('subject','%Bonus Payout','LIKE');
            $db->where('type',$bonusPayoutCredit);
            $paidBonus = $db->getValue('credit_transaction','SUM(amount)');
            
            if($paidBonus > 0){
                
                $lang = $translations[$creditLangArray[$bonusPayoutCredit]][$language];
                $eventDescription .= printfDisplay($paidBonus, $lang." In");
            }

            $totalPaidBonus += $paidBonus;
        }
    }

    $db->where("type","withholding");
    $withholdingCreditAry = $db->get("credit",null,"name");
    if($withholdingCreditAry){
        foreach ($withholdingCreditAry as $withholdingCredit) {
            $db = $bonusCreatedFilter->copy();
            $db->where('subject','%Bonus Payout','LIKE');
            $db->where('type',$withholdingCredit);
            $minusBonus = $db->getValue('credit_transaction','SUM(amount)');
            if ($minusBonus > 0){
                $lang = $translations[$creditLangArray[$bonusPayoutCredit]][$language];
                $eventDescription .= printfDisplay($minusBonus, $lang." In");
            }
            $totalMinusBonus += $minusBonus;
        }
    }

    $totalCalculatedBonus = setDecimal($totalCalculatedBonus,4);
    $totalPaidBonus = setDecimal($totalPaidBonus,4);

    $eventDescription .= tallyCheck($totalCalculatedBonus,$totalPaidBonus);
    $eventDescription .= "\n";

    ## Bonus Payout End ##

    ## Bonus Summary ##

    // Get total BV
    $totalBV = 0;
    $db->where("Date(created_at)",$dailyBonusDate);
    $totalBV = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
    $eventDescription .= printfDisplay($totalBV, "Total BV");

    $db->where("bonus_date",$dailyBonusDate);
    $db->groupBy("bonus_type");
    $bonusReportRes = $db->get("mlm_bonus_report",null, "bonus_date, sum(bonus_amount) as bonus_amount, bonus_type");
    foreach ($bonusReportRes as $bonusReportRow) {
        $bonusReportData[$bonusReportRow["bonus_date"]]["bonus_date"] = $bonusReportRow["bonus_date"];
        $bonusReportData[$bonusReportRow["bonus_date"]][$bonusReportRow["bonus_type"]] = $bonusReportRow["bonus_amount"];
    }

    $totalPayoutPercentage = 0;
    foreach($bonusReportData as $bonusItem) {
        unset($temp);
        $totalPayout = 0;
        if($bonusItem["bonus_date"] != "") {
            foreach ($bonusArray as $bonus) {
                $temp[$bonus["name"]] = (double)$bonusItem[$bonus["name"]];
                $totalPayout += (double)$bonusItem[$bonus["name"]];
                if ($totalBV > 0) {
                    $paidBonusPercentage = number_format(($bonusItem[$bonus["name"]]/$totalBV)*100,$decimal,'.','');
                } else {
                    $paidBonusPercentage = 0;
                }
                $bonusPayoutArray[] = printfDisplay($paidBonusPercentage, $translations[$bonus["language_code"]][$language]." %");
            }
            $totalPayoutPercentage = $totalBV > 0 ? number_format($totalPayout / (double) $totalBV * 100, $decimal, ".", ",") : 0;
            $grandTotalPayout += $totalPayout;
        }
    }
    
    $eventDescription .= printfDisplay($totalPayout, "Total Bonus Payout");
    $eventDescription .= printfDisplay($totalPayoutPercentage, "Total Bonus Payout %");

    if (count($bonusPayoutArray) > 0) {
        foreach ($bonusPayoutArray as $bonusPayout) {
            $eventDescription .= $bonusPayout;
        }
    }

    ## Bonus Summary End ##

    $eventDescription2 = '';

    ## Balance ##
    $stopDate = date("Ymd", strtotime($startDate));

    $result = $db->rawQuery('SHOW TABLES LIKE "acc_credit_%"');
    foreach ($result as $array) {
        foreach ($array as $accTable) {

            $val = explode('_', $accTable);
            $dateCredit = $val[2];

            $db->where('account_id', $internalIDAry, "NOT IN");
            $db->where('deleted', 0);
            $db->where('created_at', $endDate, "<=");
            $db->groupBy('account_id');
            $db->groupBy('type');
            $tableBalance = $db->get($accTable, null, 'SUM(credit) AS creditIn, sum(debit) AS creditOut, account_id, type');
            foreach ($tableBalance AS $row) {
                if ($row['creditIn'] > 0) $clientCreditAry[$row['type']][$row['account_id']] += number_format($row['creditIn'], $decimal, '.', '');
                if ($row['creditOut'] > 0) $clientDebitAry[$row['type']][$row['account_id']] += number_format($row['creditOut'], $decimal, '.', '');
                $clientIDAry[$row['account_id']] = $row['account_id'];
            }

            // TODAY BALANCE
            $db->where('account_id', $internalIDAry, "NOT IN");
            $db->where('deleted', 0);
            $db->where('created_at', $endDate, "<=");
            $db->groupBy('type');
            $tableBalance = $db->get($accTable, null, 'SUM(credit - debit) AS total, type');
            foreach ($tableBalance AS $row) {
                if ($dateCredit != $stopDate) $yestBalance[$row['type']] = number_format($yestBalance[$row['type']], $decimal, '.', '') + number_format($row['total'], $decimal, '.', '');
                $todayBalance[$row['type']] = number_format($todayBalance[$row['type']], $decimal, '.', '') + number_format($row['total'], $decimal, '.', '');
            }
        }
        if ($dateCredit == $stopDate) break;
    }

    ## GET TRANSACTION HISTORY
    // this is to reduce the space on sms
    $tableDate = date("Ymd", strtotime($startDate));
    $db->where('account_id', $internalIDAry, "NOT IN");
    $db->where('deleted', 0);
    $db->where('created_at', $endDate, '<=');
    $db->groupBy('subject');
    $db->groupBy('type');
    $tableBalance = $db->get("acc_credit_" . $tableDate, null, 'SUM(credit) AS creditIn, SUM(debit) AS creditOut, type, subject');
    foreach ($tableBalance AS $row) {
        $subject = $row['subject'];
        if ($row['creditIn'] > 0) $creditHistory[$row['type']][$subject] += $row['creditIn'];
        if ($row['creditOut'] > 0) $debitHistory[$row['type']][$subject] += $row['creditOut'];
    }

    # BALANCE TALLY CHECK
    $negativeCount = 0;
    $db->where('type', 'Client');
    $clientAry = $db->get("client", null, "username, id");
    foreach ($clientAry AS $clientData) $clientUsernameAry[$clientData['id']] = $clientData['username'];

    $db->orderBy('priority', "ASC");
    $eventDescription2 .= "Yesterday VS Today Balance\n";
    $creditAry = $db->get("credit", null, "name,type, translation_code");
  
    foreach ($creditAry AS $creditRow) {

        if($creditRow["type"] == "bonusValue" || $creditRow["type"] == "unitTier") continue;

        $type = $creditRow["name"];
        $todayCheck = 0;
       
        $displayed = 1;
        
        $eventDescription2 .= "\n" . $translations[$creditLangArray[$type]][$language] . " (" . amountDisplay($todayBalance[$type]) . ")\n";

        $yestLength = strlen(number_format($yestBalance[$type], $decimal, ".", ""));
        $eventDescription2 .= printfTransaction(" ", $yestBalance[$type], "Yest Balance");

        // CREDIT
        $sumYesterdayCredit = 0;
        foreach ($creditHistory[$type] AS $subject => $transaction) {
            $tranLength = $yestLength - strlen(number_format($transaction, $decimal, '.', ''));
            $finalAppend = str_repeat(" ", $tranLength * 2);

            $eventDescription2 .= printfTransaction("+" . $finalAppend, $transaction, $subject);
            $sumYesterdayCredit += number_format($transaction, $decimal, '.', '');
        }
        // DEBIT
        $sumYesterdayDebit = 0;
        foreach ($debitHistory[$type] AS $subject => $transaction) {

            $tranLength = $yestLength - strlen(number_format($transaction, $decimal, '.', ''));
            $finalAppend = str_repeat(" ", $tranLength * 2);

            $eventDescription2 .= printfTransaction("-" . $finalAppend, $transaction, $subject);
            $sumYesterdayDebit += number_format($transaction, $decimal, '.', '');
        }
        
        $todayCheck = bcadd((string)$yestBalance[$type], (string)$sumYesterdayCredit, 8);
        $todayCheck = bcsub((string)$todayCheck, (string)$sumYesterdayDebit, 8);

        $eventDescription2 .= "---------------------------------\n";
        $eventDescription2 .= printfTransaction(" ", $todayCheck, "Sum Balance");

        $todayBalance[$type] = setDecimal($todayBalance[$type],4);
        $todayCheck = setDecimal($todayCheck,4);

        // TRANSACTION TALLY CHECK
        $eventDescription2 .= tallyCheck($todayBalance[$type], $todayCheck);

        unset($todayCheck);

        ## CLIENT NEGATIVE BALANCE CHECK
        $clientCredit = $clientCreditAry[$type];
        $clientDebit = $clientDebitAry[$type];
        foreach ($clientIDAry AS $clientID) {
            $credit = $clientCredit[$clientID];
            $debit = $clientDebit[$clientID];

            if (amountDisplay($debit) > amountDisplay($credit)) {
                if ($displayed == 0) $eventDescription2 .= "\n" . $translations[$creditLangArray[$type]][$language] . "\n";
                $eventDescription2 .= $clientUsernameAry[$clientID] . " : " . amountDisplay($credit - $debit) . "\n";
                $displayed = 1;
                $negativeCount++;
            }
        } // clientIDAry
    } // creditAry

    // this is to show this report tally or not on report header
    $header = $endDateDisplay . " (Tally)\n";
    if (strpos($eventDescription, "Not Tally") !== false) {
        $header = $endDateDisplay . " (Not Tally)\n";
    } elseif (strpos($eventDescription2, "Not Tally") !== false) {
        $header = $endDateDisplay . " (Not Tally)\n";
    }

    $subHeader = "Page #1\n";
    $subHeader .= "-ve user: " . $negativeCount . "\n-----------------------------------\n\n";
    $eventDescription = $header . $subHeader . $eventDescription;

    echo $eventDescription;


    $header = $endDateDisplay . " (Tally)\n";
    if (strpos($eventDescription2, "Not Tally") !== false) {
        $header = $endDateDisplay . " (Not Tally)\n";
    } elseif (strpos($eventDescription, "Not Tally") !== false) {
        $header = $endDateDisplay . " (Not Tally)\n";
    }

    $subHeader = "Page #2\n";
    $subHeader .= "-ve user: " . $negativeCount . "\n-----------------------------------\n\n";
    $eventDescription2 = $header . $subHeader . $eventDescription2;

    echo $eventDescription2;
    

    Message::createMessageOut("90007", $eventDescription);
    Message::createMessageOut("90007", $eventDescription2);




