<?php
/**************** 최근 전송 목록 ******************/
/* "result_code":결과코드,"message":결과문구, */
/** list : 전송된 목록 배열 ***/
/******************** 인증정보 ********************/
$sms_url = "https://apis.aligo.in/list/"; // 전송요청 URL
$sms['user_id'] = ""; // SMS 아이디
$sms['key'] = "";//인증키
/******************** 인증정보 ********************/

$sms['page'] = "1" ;				//조회 시작번호1
$sms['page_size'] = "10" ;	//출력 갯수
$sms['start_date'] = "" ;		//조회일 시작
$sms['limit_day'] = "7" ;		//조회일수

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
