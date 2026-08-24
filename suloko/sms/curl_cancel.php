<?php
/**************** 예약취소 예제 필독항목 ******************/
/* 예약대기중인 문자를 취소하실 수 있습니다.
/* 최대 발송5분전까지만 취소가 가능합니다.
/****************** 인증정보 시작 ******************/
$sms_url = "https://apis.aligo.in/cancel/"; // 전송요청 URL
$sms['user_id'] = ""; // SMS 아이디
$sms['key'] = "";//인증키
/****************** 인증정보 끝 ********************/

/****************** 취소정보 설정시작 ****************/
$sms['mid'] = "" ; // 취소할 메세지ID (필수입력)
/****************** 취소정보 설정끝 ***************/

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
// print_r($retArr); - 결과값 출력

// print_r($retArr); // Response 출력 (연동작업시 확인용)

/**** Response 항목 안내 ****
// result_code : 전송성공유무 (성공:1 / 실패: -100 부터 -999)
// message : 실패시 상세사유 내용이 포함됩니다
// cancel_date : 취소일자
/**** Response 예문 끝 ****/

/*** 에러코드 확인 예제 ***/
if($retArr['result_code'] < 1) {
	echo 'Error Code : '.$retArr['result_code'].'<br />'."\n";
	echo 'Message : '.$retArr['message'].'<br />'."\n";
}
/*** 에러코드 ****
-801 : 메세지ID 미입력
-802 : 메세지ID 오류
-803 : 예약대기중인 문자 없음
-804 : 발송 5분전까지만 취소가능
-805 : 전송완료로 취소불가
-809 : 기타오류
/*****/