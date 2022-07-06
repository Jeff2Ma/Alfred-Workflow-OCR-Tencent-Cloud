<?php
# 作者： https://github.com/Jeff2Ma/Alfred-Workflow-OCR-Tencent-Cloud
class TxOcr
    {
        # $Img 为 base64 格式时， $ImgFormat 应为 "ImageBase64"
        # $Img 为 URL(如：img.xxx.com/xxx.jpg)时， $ImgFormat 应为 "ImageUrl"
        public function GetTxOCRResult($Img, $ImgFormat = "ImageBase64")
        {
            // 配置参数
            $SecretId = getenv('SecretId');  // 腾讯云控制台中获取 SecretId
            $SecretKey = getenv('SecretKey'); // 腾讯云控制台中获取 SecretKey
            $api_url = "https://ocr.tencentcloudapi.com/";  // 接口请求域名
            $api_host = "ocr.tencentcloudapi.com";
            $api_service= "ocr";
            $api_action = "AdvertiseOCR";   // 公共参数:Action
            $api_region = "ap-guangzhou";  // 公共参数:Region
            $api_version = "2018-11-19";  // 公共参数:Version
            // 请求时间
            $request_time = time();
            $request_date = date("Y-m-d", $request_time);
            //图片参数
            $ImgFormat == "ImageBase64" ? "ImageBase64" : "ImageUrl";
            $params_data = [
                $ImgFormat => $Img,
            ];
            $params_data = json_encode($params_data);
            // 1.拼接规范请求串
            $httpRequestMethod = "POST";
            $canonicalUri = "/";
            $canonicalQueryString = "";
            $canonicalHeaders = "content-type:application/json; charset=utf-8\n"."host:".$api_host."\n";
            $signedHeaders = "content-type;host";
            // $payload = '{"Limit": 1, "Filters": [{"Values": ["\u672a\u547d\u540d"], "Name": "instance-name"}]}';
            // $hashedRequestPayload = hash("SHA256", $payload);
            $hashedRequestPayload = hash("SHA256", $params_data);
            $canonicalRequest = $httpRequestMethod."\n"
            .$canonicalUri."\n"
            .$canonicalQueryString."\n"
            .$canonicalHeaders."\n"
            .$signedHeaders."\n"
            .$hashedRequestPayload;
            // 2.拼接待签名字符串
            $algorithm = "TC3-HMAC-SHA256";
            $requestTimestamp = $request_time;
            $credentialScope = $request_date."/".$api_service."/tc3_request";
            $hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
            $stringToSign = $algorithm."\n"
            .$requestTimestamp."\n"
            .$credentialScope."\n"
            .$hashedCanonicalRequest;
            // 3.计算签名
            $secretDate = hash_hmac("SHA256", $request_date, "TC3".$SecretKey, true);
            $secretService = hash_hmac("SHA256", $api_service, $secretDate, true);
            $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
            $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);
            // 4.拼接 Authorization
            $Authorization = $algorithm."Credential=".$SecretId."/".$credentialScope.", SignedHeaders=content-type;host, Signature=".$signature;
            // 设置 header
            $curl_header = array(
                "Content-Type: application/json; charset=utf-8",
                "Authorization:".$Authorization,
                "Host:".$api_host,
                "X-TC-Action:".$api_action,
                "X-TC-Region:".$api_region,
                "X-TC-Version:".$api_version,
                "X-TC-Timestamp:".$request_time,
            );
            $ocrRes = $this -> httpRequest($api_url, "POST", $params_data, $curl_header);
           # print $ocrRes;
            $results = "";
            if ($ocrRes) {
                $infoDetail = json_decode($ocrRes, true)['Response']['TextDetections'];
                foreach ($infoDetail as $word) {
                    #echo $word["DetectedText"]."\n";
                    $results .= $word["DetectedText"]."\n";
                }
            }
            #var_dump($results);
            return $results;
        }
        // http 请求
        private function httpRequest($url, $method='POST', $data='', $curl_header)
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            #curl_setopt($curl, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            if ($method == 'POST') {
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_header);
            }
            curl_setopt($curl, CURLOPT_HEADER, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            $result = curl_exec($curl);
            if (curl_getinfo($curl, CURLINFO_HTTP_CODE ) == "200") {
                $resHeaderSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $result = substr($result, $resHeaderSize);
            }
            curl_close($curl);
            return $result;
        }
    }

    # 图片识别
    $ocr = new TxOcr;
    $img_path = "{query}";
    $image = file_get_contents($img_path);
    $base64_img = base64_encode($image);
    //  $fp = fopen($imgFile, "rb", 0); // 打开文件
    //  $binary = fread($fp, filesize($imgFile)); // 读取文件
    //  fclose($fp); // 关闭文件
    $resStr = $ocr -> GetTxOCRResult($base64_img); // 调用
    print $resStr;
?>
