<?php
/*************  문자전송 결과 상세보기 *****************/
/** SMS_CNT / LMS_CNT / MMS_CNT : 전송유형별 잔여건수 ***/
/******************** 인증정보 ********************/
$sms_url = "https://apis.aligo.in/sms_list/"; // 전송요청 URL
$sms['user_id'] = ""; // SMS 아이디
$sms['key'] = "";//인증키

$sms['mid'] = "" ; // 메세지ID
$sms['page'] = "0" ;//조회 시작번호1
$sms['page_size'] = "10" ;//출력 갯수

$host_info = explode("/", $sms_url);
$port = $host_info[0] == 'https:' ? 443 : 80;

$oCurl = curl_init();
curl_setopt($oCurl, CURLOPT_PORT, $port);
curl_setopt($oCurl, CURLOPT_URL, $sms_url);
curl_setopt($oCurl, CURLOPT_POST, 1);
curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($oCurl, CURLOPT_POSTFIELDS, $sms);
curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
$ret = curl_exec($oCurl);
curl_close($oCurl);
echo $ret;
$retArr = json_decode($ret); // 결과배열
// print_r($retArr);
