<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for Reseller.
 * Date  19/05/2018.
 **/
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class aws {

    function __construct($db) {
        // $this->db      = $db;

    }

    public function awsUploadImage($params){

        include("config.php");

        $imgSrc = $params['imgSrc'] != "" ? $params['imgSrc'] : ""; 

        if($imgSrc){
            $s3Params=[
                'version' => 'latest',
                'region' => $config['doRegion'],
                'endpoint' => $config['doEndpoint'],
                'credentials' => [
                    'key'    => $config['doApiKey'],
                    'secret' => $config['doSecretKey'],
                ],
                'debug' => false,
            ];

            $s3 = new S3Client($s3Params);
            $imageSize = getimagesize($imgSrc);
            // echo $value;break;
            $image_parts = explode(";base64,", $imgSrc);
            $image_type_aux = explode("image/", $image_parts[0]);
            $imageType = $image_type_aux[1];
            $contentType = $image_type_aux[1];

            if(!$image_type_aux[1]){
                $image_type_aux = explode("application/", $image_parts[0]);
                $contentType = "application/".$image_type_aux[1];
                $imageType = $image_type_aux[1];
            }
            $image_base64 = base64_decode($image_parts[1]);

            $dateTime = new DateTime();
            $fileName = uniqid() . "." . $imageType;

            $putParams = [   
                 //'ContentLength'     => $imageSize,
                'ContentType'       => $contentType,
                'Bucket'            => $config['doBucketName'],
                'Key'               => $config['doFolderName'].$fileName, // this is the save as file in the space
                'Body'              => $image_base64, // and this is the file name on this server
                'ACL'               => 'public-read',
            ] ;

            try {
                $result = $s3->putObject($putParams);
                // $result->toArray();
            } catch (S3Exception $e) {
                $imageStatusMsg = $e;
            }

            return array('status' => "ok", 'code' => 0,  'statusMsg' => "Image uploaded successfully.", 'data' => '', 'imageUrl' => $result['ObjectURL']);

        }
        else{
            return array('status' => "error", 'code' => 1,  'statusMsg' => "No image was uploaded.", 'data' => '', 'response' => $result, 'imageStatusMsg' => json_encode($e));
        }
    }
}
?>