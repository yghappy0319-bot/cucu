<?php
/**
 * SMS 발송 리눅스용
 * ----------------------------
 * 리눅스용으로 별도록 동작
 * 경우에 따른 문자 메세지만 구분
 */

if ($argc < 2) {
  echo "arg error";
  exit;
}

$case = $argv[1];

$from_number = "";
$send_number = "";

switch($case) {
  case "0":
    $msg = "위즈페이(WIZZPAY) 결제 오류 발생 - {$argv[2]}건";
    break;
  default:
    $msg = "case 변수 오류";
}

syslog(LOG_DEBUG, "[MONITORING] {$msg}");

// echo $msg;
send_sms($msg);



function send_sms ($msg) {
  global $from_number, $send_number;

  /* 사용자 인증정보 */
  $sms_url = "http://biz.moashot.com/EXT/URLASP/mssendUTF.asp"; // 전송요청 URL
  $sms['uid'] = ""; // SMS 아이디
  $sms['pwd'] = "";

  /* 발송 내용 */
  $sms['contents']   = stripslashes($msg);
  $sms['toNumber']   = $send_number; // 수신번호
  $sms['fromNumber'] = $from_number; // 발신번호
  $sms['title']      = "";
  $sms['returnType'] = 3;
  $sms['sendType']   = "3";

  /* post request */
  $postdata = http_build_query($sms);
  $opts = array('http' =>
    array(
      'method' => 'POST',
      'header' => 'Content-type: application/x-www-form-urlencoded',
      'content' => $postdata
    )
  );
  $context = stream_context_create($opts);
  $result  = file_get_contents($sms_url, false, $context);
}
