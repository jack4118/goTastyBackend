<?php

    class Validation {

    	function __construct() {
    		
        }

        public function validatePortfolio($portfolioId, $clientId, $status) {
            $db = MysqliDb::getInstance();

            $db->where("id", $portfolioId);
            $db->where("status", $status);
            $db->where("client_id", $clientId);
            $checkPortfolio = $db->getValue("mlm_client_portfolio", "id");

            if(empty($checkPortfolio)) {
                return array("status" => "error", "code" => 1, "statusMsg" => "Invalid Portfolio", "data" => "");
            }

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $exceptionalFields);
        }

        public function validateRequiredField($params, $fieldAry) {
        	foreach ($fieldAry as $field) {
        		if(gettype($field) == "array") {
        			$counter = 0;
    				foreach ($field as $breakDownField) {
    					if(!empty($params[$breakDownField])) {
    						$counter++;
                            continue;
    					}
    					$exceptionalFields[] = $breakDownField;
                    }

                    if($counter < 1) {
                        return array("status" => "error", "code" => 1, "statusMsg" => "Field Required", "data" => $breakDownField);
                    } elseif($counter > 1) {
                        return array("status" => "error", "code" => 1, "statusMsg" => "More Than One Field", "data" => $breakDownField);
                    }

        		} elseif(empty($params[$field])) {
        			return array("status" => "error", "code" => 1, "statusMsg" => "Field Required", "data" => $field);
        		}
        	}
        	return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => $exceptionalFields);
        }

        public function validatePayment($clientId, $creditPaymentAry, $packageId, $date='') {
        	$db = MysqliDb::getInstance();
        	// $cash = self::cash;
        	$language = General::$currentLanguage;
        	$translations = General::$translations;

        	if(empty($clientId) || empty($creditPaymentAry)) {
        		return array("status" => "error", "code" => 1, "statusMsg" => "No Client or Package Found", "data" => "");
        	}

        	$paymentSetting = Setting::getPaymentSettingByCredit();
        	$creditTranslationCode = Setting::getCreditTranslationCode();

        	if($packageId) {
        		$db->where("id", $packageId);
            	$packagePrice = $db->getValue("mlm_product", "price");
            	if(empty($packagePrice)) {
                    $returnData['field'][] = array('id' => 'productError', 'msg' => "No Product Found.");
	        		return array("status" => "error", "code" => 1, "statusMsg" => "Package Not Found", "data" => $returnData);
	        	}
        	}

            if($date){
                $dateBalance = $date;
            }

        	foreach ($creditPaymentAry as $credit) {
        		$creditBalance = 0;
        		$creditBalance = Cash::getBalance($clientId, $credit['creditType'], $dateBalance);
        		if($credit['paymentAmount'] > $creditBalance) {
                    $returnData['field'][] = array('id' => 'PaymentError', 'msg' => "Invalid Credit Amount Input");
        			return array("status" => "error", "code" => 1, "statusMsg" => "Invalid Credit Amount Input", "data" => $returnData);
        		}
        		$paidCreditAmount[$credit['creditType']] = $credit['paymentAmount'];
        	}

        	if($packagePrice) {

        		$packagePriceLeft = $packagePrice;

        		foreach ($paymentSetting as $creditType => $creditDetail) {

        			$minPaymentUsage = $packagePrice * ($paymentSetting[$creditType]["minPercentage"] / 100);
        			$maxPaymentUsage = $packagePrice * ($paymentSetting[$creditType]["maxPercentage"] / 100);

                    if($paidCreditAmount[$creditType] < $minPaymentUsage){
                        
                        $creditArray = $db->get('credit', NULL, 'type, translation_code');
                        
                        foreach($creditArray as $creditItem){
                            $creditLangCode[$creditItem['type']] = $creditItem['translation_code'];
                        }

                        $creditDisplay = $translations[$creditLangCode[$creditType]][$language];
                        
                        if (!($paidCreditAmount[$creditType])){
                            $returnData['field'][] = array('id' => 'PaymentError', 'msg' => str_replace(array("%%creditDisplay%%"), array($creditDisplay), "%%creditDisplay%% amount cannot be empty"));
                            return array("status" => "error", "code" => 1, "statusMsg" => str_replace(array("%%creditDisplay%%"), array($creditDisplay), "%%creditDisplay%% amount cannot be empty"), "data" => $returnData);        
                        }

                        $returnData['field'][] = array('id' => 'PaymentError', 'msg' => str_replace(array("%%creditDisplay%%", "%%amount%%"), array($creditDisplay, $minPaymentUsage), $translations["B00233"][$language]));
                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace(array("%%creditDisplay%%", "%%amount%%"), array($creditDisplay, $minPaymentUsage), $translations["B00233"][$language]), 'data' => $returnData);
                    }

                    if($paidCreditAmount[$creditType] > $maxPaymentUsage){
                        $creditArray = $db->get('credit', NULL, 'type, translation_code');
                        foreach($creditArray as $creditItem){
                            $creditLangCode[$creditItem['type']] = $creditItem['translation_code'];
                        }

                        $creditDisplay = $translations[$creditLangCode[$creditType]][$language];

                        $returnData['field'][] = array('id' => 'PaymentError', 'msg' => str_replace(array("%%creditDisplay%%", "%%amount%%"), array($creditDisplay, $maxPaymentUsage), "Max Withdrawal For %%creditDisplay%% is %%amount%%."));

                        return array('status' => "error", 'code' => 1, 'statusMsg' => str_replace(array("%%creditDisplay%%", "%%amount%%"), array($creditDisplay, $maxPaymentUsage), "Max Withdrawal For %%creditDisplay%% is %%amount%%."), 'data' => $returnData);
                    }

	        		$packagePriceLeft -= $paidCreditAmount[$creditType];

	        		if($packagePriceLeft < 0) {

                        $returnData['field'][] = array('id' => 'PaymentError', 'msg' => "Credit Input Not Enough");

        				return array("status" => "error", "code" => 1, "statusMsg" => "Credit Input Not Enough", "data" => $returnData);
        			}

        		}

        		if($packagePriceLeft != 0) {
                    $returnData['field'][] = array('id' => 'PaymentError', 'msg' => "Enter Credit Exactly Match to Amount Pay");
        			return array("status" => "error", "code" => 1, "statusMsg" => "Enter Credit Exactly Match to Amount Pay", "data" => $returnData);
        		}
    		}

        	return array("status" => "ok", "code" => 0, "statusMsg" => "Successfully Paid", "data" => "");
        }

        public function validatePackagePin($type, $productId, $packagePinCode) {
        	$db = MysqliDb::getInstance();
        	$language = General::$currentLanguage;
        	$translations = General::$translations;

        	if($type == "noPackage") {
        		return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => "");
        	}

        	$allPackagePinTypeAry = array("pin", "package", "free");
        	$packagePinMatchAry = array(
	        							"pin" => array(
	        								"language" => $translations[""][$language],
	        								"table" => "mlm_pin",
	        								"column" => "code",
	        								"condition" => "New"
	        								),
	        							"package" => array(
	        								"language" => $translations[""][$language],
	        								"table" => "mlm_product",
	        								"column" => "id",
	        								"condition" => "Active"
	        								) /* BV */
	        						   );
        	$packagePinMatchAry["free"] = $packagePinMatchAry["package"]; /* NBV */

        	$unifiedCode = $productId;
        	if($type == "pin") {
        		$unifiedCode = $packagePinCode;
        	}

        	if(!$packagePinMatchAry[$type] && !in_array($type, $allPackagePinTypeAry)) {
        		return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => "");
        	}

        	if(empty($type)){
        		return array("status" => "error", "code" => 1, "statusMsg" => "Invalid Registration Type", "data" => "");
        	} elseif(empty($packagePinCode) && $type == "pin") {
        		return array("status" => "error", "code" => 1, "statusMsg" => "Pin Is Required", "data" => 1);
        	}
        	
        	if($packagePinMatchAry[$type]) {
	        	$db->where($packagePinMatchAry[$type]['column'], $unifiedCode);
	            $data = $db->getOne($packagePinMatchAry[$type]['table']);
	            if(empty($data)) {
	            	return array("status" => "error", "code" => 1, "statusMsg" => "Package Not Found", "data" => "");
	            }
	        }

            if(!empty($data) && 
               ($data['status'] != $packagePinMatchAry[$type]['condition'])) {
               	return array("status" => "error", "code" => 1, "statusMsg" => "Package Is Inactive", "data" => "");
            }

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => "");
        }

        public function validateBasicField($params, $exceptionalFields) {
            $newParams = $params;
            if($exceptionalFields) {
        		$newParams = self::removeExceptionalField($params, $exceptionalFields);
        	}

            $emptyErrorAry = self::checkEmptyField($newParams);
            foreach ($emptyErrorAry as $error) {
            	$skipList[] = $error['name'];
            }

            foreach ($newParams as $newParamsName => $newParamsValue) {

            	if(in_array($newParamsName, $skipList)) {
            		continue;
            	}

            	switch ($newParamsName) {
				    case "username":
				    	$basicErrorAry[] = self::checkFieldExistence("client", $newParamsName, $newParamsValue, "exist", $newParamsName);
				    	$basicErrorAry[] = self::checkFieldLength("Username", $newParamsName, $newParamsValue);
				    	$basicErrorAry[] = self::checkFieldFormat($newParamsName, $newParamsValue, "alphanumeric");
				        break;
				    case "fullName":
				    	$basicErrorAry[] = self::checkFieldLength("Fullname", $newParamsName, $newParamsValue);
				        break;
				    case "password":
				    	$basicErrorAry[] = self::checkFieldLength("Password", $newParamsName, $newParamsValue);
				    	break;
				    case "confirmPassword":
				    	$basicErrorAry[] = self::checkFieldMatch($newParamsName, $newParams['password'], $newParamsValue);
				    	break;
				    case "transactionPassword":
				    	$basicErrorAry[] = self::checkFieldLength("TransactionPassword", $newParamsName, $newParamsValue);
				    	break;
				    case "confirmTransactionPassword":
				    	$basicErrorAry[] = self::checkFieldMatch($newParamsName, $newParams['transactionPassword'], $newParamsValue);
				    	break;
				    case "telNumber":
				    	$basicErrorAry[] = self::checkFieldFormat($newParamsName, $newParamsValue, "numeric");
				    	$basicErrorAry[] = self::checkFieldExistence("client", "concat(dial_code, phone)",  str_replace("+", "", $newParams['dialCode']) . $newParamsValue, "exist", $newParamsName);
				        break;
				    case "email":
				    	$basicErrorAry[] = self::checkFieldFormat($newParamsName, $newParamsValue, "email");
				    	break;
				    case "sponsorUsername":
				    	$basicErrorAry[] = self::checkFieldExistence("client", "username", $newParamsValue, "inexist", $newParamsName);
				    	break;
				    case "sponsorTelNumber":
				    	$basicErrorAry[] = self::checkFieldExistence("client", "username", $newParamsValue, "inexist", $newParamsName);
				    	break;
				}

            }

            $newBasicErrorAry = array_filter($basicErrorAry);

            $allBasicErrorAry = $newBasicErrorAry;
            if($emptyErrorAry) {
            	$allBasicErrorAry = array_merge($allBasicErrorAry, $emptyErrorAry);
            }

            $allBasicErrorAry = array_values($allBasicErrorAry);
            $errorStatus = self::convertErrorAryToText($allBasicErrorAry);

            if(!empty($allBasicErrorAry)) {
                $returnData['field'] = $allBasicErrorAry;
            }

            return array("data" => $returnData, "errorStatus" => $errorStatus);

        }

        public function checkEmptyField($params, $exceptionalFields) {
        	$newParams = $params;

        	if($exceptionalFields) {
        		$newParams = self::removeExceptionalField($params, $exceptionalFields);
        	}
        	
        	foreach ($newParams as $newParamsName => $newParamsValue) {
        		if(trim($newParamsValue) == '') {
        			$errorFieldAry[] = self::getErrorMessage($newParamsName, "empty");
        		}
        	}

        	return $errorFieldAry;
        }

        public function checkFieldExistence($tableName, $field, $value, $exist, $inputField) {
        	$db = MysqliDb::getInstance();

        	$db->where($field, $value);
        	$check = $db->getValue($tableName, $field);

        	if($exist == "inexist" && $check == '') {
        		$validateExistence = self::getErrorMessage($inputField, $exist);
        	} elseif($exist == "exist" && $check != '') {
        		$validateExistence = self::getErrorMessage($inputField, $exist);
        	}

        	return $validateExistence;
        }

        public function checkFieldLength($settingName, $name, $value) {
        	
        	$minLength = Setting::$systemSetting["min" . $settingName . "Length"];
			$maxLength = Setting::$systemSetting["max" . $settingName . "Length"];

			if(strlen($value) < $minLength || strlen($value) > $maxLength) {
                $errorParams = array("minLength" => $minLength, "maxLength" => $maxLength);
				$errorFieldAry = self::getErrorMessage($name, "length", $errorParams);
			}

        	return $errorFieldAry;
        }

        public function checkFieldMatch($name, $value1, $value2) {
        	if($value1 != $value2) {
        		$errorFieldAry = self::getErrorMessage($name, "match");
        	}

        	return $errorFieldAry;
        }

        public function checkFieldFormat($name, $value, $format) {
        	switch ($format) {
			    case "numeric":
			    	if(!ctype_digit($value)) {
			    		$errorFieldAry = self::getErrorMessage($name, "format");
			    	}
			    	break;
			    case "alphabetic":
			    	if(!ctype_alnum($value)) {
			    		$errorFieldAry = self::getErrorMessage($name, "format");
			    	}
			    	break;
			    case "alphanumeric":
			    	if(!ctype_alnum($value)) {
			    		$errorFieldAry = self::getErrorMessage($name, "format");
			    	}
			    	break;
			    case "email":
			    	if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
			    		$errorFieldAry = self::getErrorMessage($name, "format");
			    	}
			    	break;
			}

			return $errorFieldAry;
        }

        public function getErrorMessage($name, $type, $params) {
        	// used unique type: empty, exist, length, match, format, inexist
        	switch ($name) {
			    case "username":
			    	$errorMessage["empty"] = "Username Missing";
			    	$errorMessage["length"] = "Invalid Username Length - Must Between " . $params['minLength'] . " And " . $params['maxLength'] . " Long";
			    	$errorMessage["exist"] = "Username Already Exist";
                    $errorMessage["inexist"] = "Sponsor Not Exist";
			        break;
			    case "fullname":
			    	$errorMessage["empty"] = "Full Name Missing";
			    	$errorMessage["length"] = "Invalid Full Name Length - Must Between " . $params['minLength'] . " And " . $params['maxLength'] . " Long";
			    	$errorMessage["format"] = "Invalid Full Name Format";
			        break;
			    case "password":
			    	$errorMessage["empty"] = "Login Password Missing";
			    	$errorMessage["length"] = "Invalid Login Password Length - Must Between " . $params['minLength'] . " And " . $params['maxLength'] . " Long";
			    	$errorMessage["format"] = "Invalid Login Password Format - Must Be Alphanumeric";
			    	break;
			    case "confirmPassword":
			    	$errorMessage["empty"] = "Login Password Missing";
			    	$errorMessage["match"] = "Retype Login Password Not Match";
			    	break;	
			    case "transactionPassword":
			    	$errorMessage["empty"] = "Transaction Password Missing";
			    	$errorMessage["length"] = "Invalid Transaction Password Length - Must Between " . $params['minLength'] . " And " . $params['maxLength'] . " Long";
			    	$errorMessage["format"] = "Invalid Transaction Password Format - Must Be Alphanumeric";
			    	break;
			    case "confirmTransactionPassword":
			    	$errorMessage["empty"] = "Retype Transaction Password Missing";
			    	$errorMessage["match"] = "Transaction Password Not Match";
			    	break;
			    case "telNumber":
			        $errorMessage["empty"] = "Phone Number Missing";
			        $errorMessage["exist"] = "Phone Number Already Exist";
			        break;
			    case "email":
			    	$errorMessage["empty"] = "Email Missing";
			    	$errorMessage["format"] = "Invalid Email Format";
			    	$errorMessage["exist"] = "Email Already Exist";
			    	break;
			    case "countryCode":
			    	$errorMessage["empty"] = "Country Code Missing";
			    	break;
			    case "sponsorUsername":
			    	$errorMessage["inexist"] = "Introducer Not Exist";
			    	break;
			    case "sponsorTelNumber":
			    	$errorMessage["inexist"] = "Introducer Phone Number Not Exist";
			    	break;
                case "passport":
                    $errorMessage["empty"] = "Passport Missing";
                    break;
			}

			return array("id" => $name . "Error", "msg" => $errorMessage[$type], "name" => $name, "type" => $type);
        }

        public function removeExceptionalField($params, $exceptionalFields) {
        	$newParams = $params;

        	foreach ($exceptionalFields as $field) {
        		unset($newParams[$field]);
        	}

        	return $newParams;
        }

        public function convertErrorAryToText($errorAry) {
        	$errorText = '';

        	foreach ($errorAry as $error) {
        	   $errorText .= $error['msg'] . "<br/>";
        	}

        	return $errorText;
        }

        public function validateClient($params) {
            $db = MysqliDb::getInstance();
            $username = $params['username'];

            $db->where("username", $username);
            $clientID = $db->getValue("client", "id");

            if(empty($clientID)) {
                return array("status" => "error", "code" => 1, "statusMsg" => "Invalid Username", "data" => "");
            }

            return array("status" => "ok", "code" => 0, "statusMsg" => "", "data" => '');
        }

    }

?>
