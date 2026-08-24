<?php
/**************** 전송가능 건수확인 ******************/
/* "result_code":결과코드,"message":결과문구, */
/** SMS_CNT / LMS_CNT / MMS_CNT : 전송유형별 잔여건수 ***/
/******************** 인증정보 ********************/
$sms_url = "https://apis.aligo.in/remain/"; // 전송요청 URL
$sms['user_id'] = ""; // SMS 아이디
$sms['key'] = "";//인증키
/******************** 인증정보 ********************/

$host_info = explode("/", $sms_url);
$port = $host_info[0] == 'https:' ? 443 : 80;

$oCurl = curl_init();
curl_setopt($oCurl, CURLOPT_PORT, $port);
curl_setopt($oCurl, CURLOPT_URL, $sms_url);
curl_setopt($oCurl, CURLOPT_POST, 1);
curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($oCurl, CURLOPT_POSTFIELDS, $sms);
curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);

// CURL 접속이 안되는 경우 CURL 옵션안내를 참조 하셔서 옵션을 출력하여 확인해 주세요.
// http://phpdoc.me/manual/kr/function.curl-setopt.php
$ret = curl_exec($oCurl);
curl_close($oCurl);
echo $ret;
$retArr = json_decode($ret); // 결과배열
// print_r($retArr);
