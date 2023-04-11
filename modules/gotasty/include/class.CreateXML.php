<?php

	class CreateXML{
        
        function addNode($xml,$NodeName,$Value){
            $xml->startElement($NodeName);
            $xml->text($Value);
            $xml->endElement();
        }
        
        function generateXML($result, $fileName, $append = false){
            
            if(count($result) == 0){
             
                $path = __DIR__."/../coinRateData/".$fileName.".xml";

                file_put_contents($path, "");
                return;
            }
            
            //test with daily trading
            $xml = new XMLWriter();
            $xml->openMemory();
            $xml->setIndent(true);
            
            $xml->startElement($fileName);
            
            // print_r($result);
            foreach($result as $rate){
                $xml->startElement('Data');
                foreach($rate as $key => $value){
                    $this->addNode($xml,$key,$value);
                }
                $xml->endElement();
            }
            // while($row = mysql_fetch_assoc($result)){
            //     $xml->startElement('Data');
            //     foreach($row as $key => $value){
            //         $this->addNode($xml,$key,$value);
            //     }
            //     $xml->endElement();
            // }
            $xml->endElement();
            $xml->endDocument();

            $content = $xml->flush();
            
            $path = __DIR__."/../coinRateData/".$fileName.".xml";
            
            file_put_contents($path, $content);
            
//            $path =  __DIR__."/../graphData/".$fileName.".xml";
//            $fp = $append? fopen($path,"a+"):fopen($path, "w+");
//            fwrite($fp, $content);
//            fclose($fp);
//            header('Content-type: text/xml');
//            echo $content;

        }
        
        function readXML($fileName){
            
//            echo __DIR__."/../graphData/".$fileName.".xml";
            
            $xml=simplexml_load_file(__DIR__."/../coinRateData/".$fileName.".xml");

            switch($fileName){
                        case "coinRate":
                            foreach($xml->Data as $data){
                                $name = (string)strtok($data->name, " ");
                                $value = (float)$data->value;
                                $returnData[$name] = array("name"=>$name,
                                                      "value"=>$value,
                                                        ); 

                            }
                        
                        break;
                    }

            
            return $returnData;
//            $path =  __DIR__."/../graphData/".$fileName.".xml";
//            $reader = new XMLReader();
//            if (!$reader->open($path)){
//                //file not found
//                $returnData = array();
//            }
//            while($reader->read()){
//                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == $fileName) {
//                    
//                    $xml = simplexml_load_string($reader->readOuterXML());
//                    
//                    switch($fileName){
//                        case "oneMinute":
//                        case "fiveMinute":
//                        case "fifteenMinute":
//                        case "thirtyMinute":
//                        case "sixtyMinute":
//                        case "oneDay":
//                        case "oneWeek":
//                        case "oneMonth":
//                        case "dailyTrade":
//                        
//                            foreach($xml->Data as $data){
//                                $open = (string)$data->open;
//                                $close = (string)$data->close;
//                                $high = (string)$data->high;
//                                $low = (string)$data->low;
//                                $volume = (string)$data->volume;
//                                $change = (string)$data->change;
//                                $date = (string)$data->createdOn;
//
//                                $returnData[] = array("open"=>$open,
//                                                      "close"=>$close,
//                                                      "high"=>$high,
//                                                      "low"=>$low,
//                                                      "volume"=>$volume,
//                                                      "change"=>$change,
//                                                      "createdOn"=>$date); 
//
//                            }
//                        
//                        break;
//                        
//                        case "buyQueue":
//                        case "sellQueue":
//                        case "liveTrade":
//                        
//                            foreach($xml->Data as $data){
//                                $price = (string)$data->price;
//                                $quantity = (string)$data->quantity;
//                                $createdOn = (string)$data->createdOn;
//
//                                $returnData[] = array("price"=>$price,
//                                                      "quantity"=>$quantity,
//                                                      "createdOn"=>$createdOn); 
//
//                            }
//                        
//                        break;
//                    }
//                    
//                    
//
//                }
//            }
//            $reader->close();
//            
//            return $returnData;
        }

	}

?>
