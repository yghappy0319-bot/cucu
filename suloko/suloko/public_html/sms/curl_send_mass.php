<?php
/**************** 문자전송하기(대량) 예제 필독항목 ******************/
/* 전화번호별 다른 문자내용을 다수(최대500명)에게 동시 전송하실 수 있습니다
/* 동일내용을 다수에게 전송하시는 경우에는 send API(별도예제:curl_send.html)에 전화번호를 컴마(,)분기하여 전송하시기 바랍니다.

/****************** 인증정보 시작 ******************/
$sms_url = "https://apis.aligo.in/send_mass/"; // 전송요청 URL
$sms['user_id'] = ""; // SMS 아이디
$sms['key'] = "";//인증키
/****************** 인증정보 끝 ********************/

/****************** 전송정보 설정시작 ****************/
$sms['sender'] =""; // 발신번호
$sms['rdate'] = ''; // 예약일자 - 20161004 : 2016-10-04일기준
$sms['rtime'] = ''; // 예약시간 - 1930 : 오후 7시30분
$sms['testmode_yn'] = 'Y'; // Y 인경우 실제문자 전송X , 자동취소(환불) 처리
$sms['msg_type'] = 'SMS'; // SMS(단문) , LMS(장문), MMS(그림문자)  = 필수항목
/****************** 전송정보 설정끝 ***************/

/****************** 아래는 500건 설정예제입니다.***************/
$msg = "(광고)알리고\n회원님 알리고를 추천드려요!!\n무료거부:080xxxxxxxxx"; // 메세지 내용
for($i=1; $i < 501; $i++) {
	$sms['rec_'.$i] = '01100000'.($i < 100 ? ($i < 10 ? '00'.$i : '0'.$i) : $i); // 수신번호_$i 번째  = 필수항목
	$sms['msg_'.$i] = stripslashes(str_replace('회원님','회원'.$i.'님',$msg)); // 내용_$i번째  = 필수항목
	$sms['sbj_'.$i] = stripslashes('회원'.$i.'님 개별 제목'); // 제목_$i번째  = LMS/MMS 에 제목을 개별로 설정하실 수 있습니다.
}
$sms['cnt'] = $i-1; // 전송건수(지정된 건수까지만  처리) cnt = 10 설정 시 rec_11 과 msg_11 에 데이터가 있어도 rec_11에게  미전송  = 필수항목
// $sms['title'] = '제목입력'; //  LMS, MMS 제목 (위 sbj_n 의 개별설정이 아닌 동일한 제목설정을 원하시는 경우 사용하실 수 있습니다.)
/*****/

/*** ※ 중요 - 기존 send 와 다른 부분  ***
 * msg_type 필수 : SMS 와 LMS 구분자 = 필수항목
 * receiver(수신번호) 와 msg(내용) 가 rec_1 ~ rec_500 과 msg_1 ~ msg_500 으로 설정가능 = 필수입력(최소 1개이상)
 * cnt 추가 : 위 rec_갯수 와 msg_갯수에 지정된 갯수정보 지정 = 필수항목 (최대 500개)
 * 이미지는 전화번호별 개별설정을 하실 수 없습니다. 개별이미지(QR코드등) 설정이 필요하신 경우 send API(별도예제:curl_send.html)로 1건씩 전송해 주세요
/******/

// $_POST['image'] = '/tmp/pic_57f358af08cf7_sms_.jpg'; // MMS 이미지 파일 위치
// 만일 $_FILES 로 직접 Request POST된 파일을 사용하시는 경우 move_uploaded_file 로 저장 후 저장된 경로를 사용하셔야 합니다.
if(!empty($_FILES['image']['tmp_name'])) {
	$tmp_filetype = mime_content_type($_FILES['image']['tmp_name']); 
	if($tmp_filetype != 'image/png' && $tmp_filetype != 'image/jpg' && $tmp_filetype != 'image/jpeg') $_POST['image'] = '';
	else {
		$_savePath = "./".uniqid(); // PHP의 권한이 허용된 디렉토리를 지정
		if(move_uploaded_file($_FILES['file']['tmp_name'], $_savePath)) {
			$_POST['image'] = $_savePath;
		}
	}
}
// 이미지 전송 설정
if(!empty($_POST['image'])) {
	if(file_exists($_POST['image'])) {
		$tmpFile = explode('/',$_POST['image']);
		$str_filename = $tmpFile[sizeof($tmpFile)-1];
		$tmp_filetype = mime_content_type($_POST['image']);
		if ((version_compare(PHP_VERSION, '5.5') >= 0)) { // PHP 5.5버전 이상부터 적용
			$sms['image'] = new CURLFile($_POST['image'], $tmp_filetype, $str_filename);
			curl_setopt($oCurl, CURLOPT_SAFE_UPLOAD, true);
		} else {
			$sms['image'] = '@'.$_POST['image'].';filename='.$str_filename. ';type='.$tmp_filetype;
		}
	}
}
/*****/

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
// print_r($retArr); // Response 출력 (연동작업시 확인용)

/**** Response 항목 안내 ****
// result_code : 전송성공유무 (성공:1 / 실패: -100 부터 -999)
// message : success (성공시) / reserved (예약성공시) / 그외 (실패상세사유가 포함됩니다)
// msg_id : 메세지 고유ID = 고유값을 반드시 기록해 놓으셔야 sms_list API를 통해 전화번호별 성공/실패 유무를 확인하실 수 있습니다
// error_cnt : 에러갯수 = receiver 에 포함된 전화번호중 문자전송이 실패한 갯수
// success_cnt : 성공갯수 = 이동통신사에 전송요청된 갯수
// msg_type : 전송된 메세지 타입 = SMS / LMS / MMS (보내신 타입과 다른경우 로그로 기록하여 확인하셔야 합니다)
/**** Response 예문 끝 ****/