<?php
header('Content-Type: text/html; charset=UTF-8');
@session_start();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');


include $_SERVER['DOCUMENT_ROOT']."/lib/config.php";


/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$conn = mysqli_connect("13.209.88.88","lotto","lotto09*&","lotto");
mysqli_query($conn, "set names utf8mb4");

$관리자070연락처 = "070-8095-1224";

function db_query($sql){
    global $conn;

    $rs = mysqli_query($conn, $sql);
    return $rs;
}
function db_select($sql){
    $rs = db_query($sql);
    return @db_fetch($rs);
}
function db_fetch($rs){
    return @mysqli_fetch_array($rs);
}
function db_result($sql){
    $rs = db_query($sql);
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}

//인젝션 공격 방지
function anti_injection($string){
    if ($string){
        $str =  trim($string);
        $str =  str_replace("'","&#039;",$str);
        $str =  str_replace("<xmp","<x-xmp",$str);
        $str =  str_replace("javascript","x-javascript",$str);
        $str =  str_replace("script","x-script",$str);
        $str =  str_replace("iframe","x-iframe",$str);
        $str =  str_replace("document","x-document",$str);
        $str =  str_replace("vbscript","x-vbscript",$str);
        $str =  str_replace("applet","x-applet",$str);
        $str =  str_replace("embed","x-embed",$str);
        $str =  str_replace("object","x-object",$str);
        $str =  str_replace("frame","x-frame",$str);
        $str =  str_replace("frameset","x-frameset",$str);
        $str =  str_replace("layer","x-layer",$str);
        $str =  str_replace("bgsound","x-bgsound",$str);
        $str =  str_replace("alert","x-alert",$str);
        $str =  str_replace("onblur","x-onblur",$str);
        $str =  str_replace("onchange","x-onchange",$str);
        $str =  str_replace("onclick","x-onclick",$str);
        $str =  str_replace("ondblclick","x-ondblclick",$str);
        $str =  str_replace("onerror","x-onerror",$str);
        $str =  str_replace("onfocus","x-onfocus",$str);
        $str =  str_replace("onload","x-onload",$str);
        $str =  str_replace("onmouse","x-onmouse",$str);
        $str =  str_replace("onscroll","x-onscrol",$str);
        $str =  str_replace("onsubmit'","x-onsubmit",$str);
        $str =  str_replace("onunload","x-onunload",$str);
        return $str;

    }else{
        return $string;
    }
}

function 문자열공백제거($inputString) {
    // 정규식을 사용하여 모든 공백을 제거
    $outputString = preg_replace('/\s+/', '', $inputString);

    return $outputString;
}

function SMS_SEND($받는사람, $제목="안녕하세요",$내용){
  $_받는사람치환 = str_replace('-', '', $받는사람);
    //$data = 뿌리오($_받는사람치환, $제목, $내용);
    $data = 비즈모아샷($_받는사람치환, '07047591808', $제목, $내용);;
    return $data;
}

function 비즈모아샷($to, $from, $SUBJECT, $SENDMSG, $msg_type="SMS") {

  $from = str_replace("-", "", $from);
  $to   = str_replace("-", "", $to);

  $name = $to;
  $testmode_yn = "";

  /* 사용자 인증정보 */
  $sms_url = "http://biz.moashot.com/EXT/URLASP/mssendUTF.asp"; // 전송요청 URL
  $sms['uid'] = "mepawor888"; // SMS 아이디
  //$sms['pwd'] = "suloko7952@@";
  $sms['pwd'] = "APVKDNJF88!!";

  /* 발송 내용 */
  $sms['contents']   = stripslashes($SENDMSG);
  $sms['toNumber']   = $to; // 수신번호
  $sms['fromNumber'] = $from; // 발신번호
  $sms['title']      = $SUBJECT;
  $sms['returnType'] = 3;

  /* sendType
  · 3 : 단문문자(SMS)
  · 5 : 장문문자(LMS)
  · 6 : 이미지를 포함한 문자(MMS)
  */
  $len = strlen($sms['contents']);
  if ($len > 90 || $msg_type == 'LMS') {
    $sms['sendType']  =  "5";
  } else {
    $sms['sendType']  =  "3";
  }

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

  // return 값을 알리고 형태로 변환
  $retArr = json_decode('{"result_code":"'.($result == "SUCCESS:" ? "1" : "Err").'"}');

  return $retArr;
}

function MAIL_SEND($받는이, $아이디, $제목, $내용){

  $to = $받는이;
  $toName = $아이디."님";
  $subject = $제목;
  $htmlBody = $내용;

  $result = sendGmailSMTP($to, $toName, $subject, $htmlBody);

  if ($result === true) {
      $data = array("status" => 1, "msg" => "이메일로 전송 되었습니다.");
      return json_encode($data);
      exit;
  } else {
      return $result; // 에러 메시지 출력
  }

}

function getToken() {
    $token_url = 'https://message.ppurio.com/v1/token';
    $account = 'kks060';
    $access_key = '8c9ce2627be8ef9cd7999ce537b286a00b449ba7cadf4444e24c9de615e92e05';
    $headers = array(
        'Authorization: Basic ' . base64_encode($account . ':' . $access_key)
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    // API 요청 실행
    $response = curl_exec($ch);

    // 응답 출력
    $response = json_decode($response, true);

    // cURL 세션 종료
    curl_close($ch);

    return $response;
}

/**
 * 문자 발송 요청
 */
 function checkMessageType($text) {
    // 문자열의 바이트 수 계산 (멀티바이트 문자열 포함)
    $byteLength = mb_strlen($text, '8bit');

    if ($byteLength >= 90) {
        return "LMS";
    } else {
        return "SMS";
    }
}

function 뿌리오($받는사람, $제목="안녕하세요",$내용) {
  $result = getToken();
    $api_url = 'https://message.ppurio.com/v1/message';
    $token = $result['token'];

    $content = $내용;
    $target = array(
        array(
            'to' => $받는사람,
            'name' => 'Kildong Hong',
            'changeWord' => array(
                'var1' => '10,000',
            )
        )
    );

    $file = $_SERVER['DOCUMENT_ROOT'].'/test.jpg';
    $files = array(
        array(
            'name' => basename($file),
            'size' => filesize($file),
            'data' => base64_encode(file_get_contents($file))
        )
    );

    // JSON 형식의 파라미터 데이터 생성
    $send_data = array(
        'account' => 'kks060', // 뿌리오 계정
        'messageType' => checkMessageType($content), // SMS/LMS/MMS
        'from' => '01030653753', // 발신번호 (숫자만)
        'duplicateFlag' =>'N', // 수신번호 중복허용 여부 (Y:허용 / N:제거)
        'content' => $content, // 메시지 내용
        'targetCount' => count($target), // 수신자 목록 수
        'targets' => $target, // 수신자 및 치환문자 정보
        'refKey' => 'test', // 요청에 부여한 키
        'rejectType' => 'AD', // 광고 수신거부 설정 및 유형 선택, 비활성화할 경우 파라미터 제외 (AD: 광고성)
        'subject' => '메가파워월드', // 제목 (LMS/MMS 발송에서만 가능)
    );

    $jsonData = json_encode($send_data);

    // 커스텀 헤더 설정
    $headers = array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    );

    // cURL 초기화
    $ch = curl_init();

    // cURL 옵션 설정
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    // API 요청 실행
    $response = curl_exec($ch);

    // cURL 세션 종료
    curl_close($ch);

    // 응답 출력
    $response = json_decode($response, true);
    return $response;
}




function SMS_알리고($받는사람, $제목="안녕하세요",$내용){

    /**************** 문자전송하기 예제 필독항목 ******************/
    /* 동일내용의 문자내용을 다수에게 동시 전송하실 수 있습니다 */
    /* 대량전송시에는 반드시 컴마분기하여 1천건씩 설정 후 이용하시기 바랍니다. (1건씩 반복하여 전송하시면 초당 10~20건정도 발송되며 컨텍팅이 지연될 수 있습니다.)*/
    /* 전화번호별 내용이 각각 다른 문자를 다수에게 보내실 경우에는 send 가 아닌 send_mass(예제:curl_send_mass.html)를 이용하시기 바랍니다.*/
    /****************** 인증정보 시작 ******************/
    $sms_url = "https://apis.aligo.in/send/"; // 전송요청 URL
    $sms['user_id'] = "meloko77"; // SMS 아이디
    $sms['key'] = "pqp2uj8whjfqoidozfxshx5cm0ylzt9l";//인증키
    /****************** 인증정보 끝 ********************/

    /****************** 전송정보 설정시작 ****************/
    $_POST['msg'] = $내용; // 메세지 내용 : euc-kr로 치환이 가능한 문자열만 사용하실 수 있습니다. (이모지 사용불가능)
    $_POST['receiver'] = $받는사람; // 수신번호
    $_POST['destination'] = ''; // 수신인 %고객명% 치환
    $_POST['sender'] = "070-7537-0606"; // 발신번호
    $_POST['rdate'] = ""; // 예약일자 - 20161004 : 2016-10-04일기준
    $_POST['rtime'] = ""; // 예약시간 - 1930 : 오후 7시30분
    $_POST['testmode_yn'] = ''; // Y 인경우 실제문자 전송X , 자동취소(환불) 처리
    $_POST['subject'] = $제목; //  LMS, MMS 제목 (미입력시 본문중 44Byte 또는 엔터 구분자 첫라인)
    if($이미지){
      $_POST['image'] = $이미지; // MMS 이미지 파일 위치 (저장된 경로)
      $_POST['msg_type'] = 'MMS';
    }else{
      $_POST['msg_type'] = $발송타입; //  SMS, LMS, MMS등 메세지 타입을 지정
    }
// ※ msg_type 미지정시 글자수/그림유무가 판단되어 자동변환됩니다.
//단, 개행문자/특수문자등이 2Byte로 처리되어 SMS 가 LMS로 처리될 가능성이 존재하므로 반드시 msg_type을 지정하여 사용하시기 바랍니다.
    /****************** 전송정보 설정끝 ***************/
    $sms['msg'] = stripslashes($_POST['msg']);
    $sms['receiver'] = $_POST['receiver'];
    $sms['destination'] = $_POST['destination'];
    $sms['sender'] = $_POST['sender'];
    $sms['rdate'] = $_POST['rdate'];
    $sms['rtime'] = $_POST['rtime'];
    $sms['testmode_yn'] = empty($_POST['testmode_yn']) ? '' : $_POST['testmode_yn'];
    $sms['title'] = $_POST['subject'];
    $sms['msg_type'] = $_POST['msg_type'];
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

      $결과 = json_decode($ret); //php 배열로 변환시킴


    return $결과;

    //$retArr = json_decode($ret); // 결과배열
    //print_r($retArr); // Response 출력 (연동작업시 확인용)
    /**** Response 항목 안내 ****/
    // result_code : 전송성공유무 (성공:1 / 실패: -100 부터 -999)
    // message : success (성공시) / reserved (예약성공시) / 그외 (실패상세사유가 포함됩니다)
    // msg_id : 메세지 고유ID = 고유값을 반드시 기록해 놓으셔야 sms_list API를 통해 전화번호별 성공/실패 유무를 확인하실 수 있습니다
    // error_cnt : 에러갯수 = receiver 에 포함된 전화번호중 문자전송이 실패한 갯수
    // success_cnt : 성공갯수 = 이동통신사에 전송요청된 갯수
    // msg_type : 전송된 메세지 타입 = SMS / LMS / MMS (보내신 타입과 다른경우 로그로 기록하여 확인하셔야 합니다)
    /**** Response 예문 끝 ****/
}

//메세지 팝업 후 이동
function alert($msg, $url){
  if($url){
    $go_url = "top.location.href='{$url}';";
  }else{
    $go_url = "history.back();";
  }
  echo "<script>alert('".$msg."');{$go_url}</script>";
  exit;
}

function go_url($url){
  if($url){
    $go_url = "top.location.href='{$url}';";
  }else{
    $go_url = "history.back();";
  }
  echo "<script>{$go_url}</script>";
  exit;
}

function encryptPassword($password) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    return $hashedPassword;
}
function 랜덤문자숫자($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
function 랜덤문자열($length = 10) {
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function 로그_문자발송($midx,$실패여부,$결과,$여섯자리,$수신자번호,$내용,$아이피="",$페이지=""){
  $midx = $midx ? $midx:0;

  $sql = "insert into lr_sms_log set ";
  $sql.= "midx = {$midx}, ";
  if($결과 > 0){
    $sql.= "deposit_idx = {$결과}, ";
  }

  $mem = db_select("select id from lr_member where idx = {$midx} ");
  $sql.= "userid = '{$mem['id']}', ";
  $sql.= "status = {$실패여부}, ";
  $sql.= "send_status = '{$실패여부}', ";
  $sql.= "page = '{$페이지}', ";
  $sql.= "code = '{$여섯자리}', ";
  $sql.= "phone = '{$수신자번호}', ";
  $sql.= "msg = '{$내용}', ";
  $sql.= "ip = '{$아이피}', ";
  $sql.= "regdate = now() ";
  //echo $sql;
  $result = db_query($sql);
  return $result;
}

function 로그인정보($midx){
  if (!$midx) return array();
  $member_sql = "select * from lr_member where idx = {$midx} ";
  $member = db_select($member_sql);
  return $member;
}

function 카드충전리스트(){
  $point_sql = "select * from lr_card_config order by camount desc ";
  $result = db_query($point_sql);
  return $result;
}

function 포인트리스트(){
  $point_sql = "select * from lr_cash_config order by camount desc ";
  $result = db_query($point_sql);
  return $result;
}

function 회원($midx){
  $member = db_select("select * from lr_member where idx = {$midx} ");
  return $member;
}
function 파트너_포인트신청리스트($midx,$where=""){
  if($midx){
    $w_midx = " and midx = {$midx}  ";
  }
  $sql = " select a.* , b.ads
    from lr_deposit_log a left join lr_member b
    on a.midx = b.idx
    where 1=1 {$w_midx} {$where} ";
  //echo $sql;
  $data = db_query($sql);
  return $data;
}

function 포인트신청리스트($midx,$where){
  if($midx){
    $w_midx = " and midx = {$midx}  ";
  }
  $deposit_sql = "select * from lr_deposit_log where 1=1 {$w_midx} {$where} ";
  $result = db_query($deposit_sql);
  return $result;
}

function 입금상태($obj){
  if($obj==1){
    return "처리중";
  }else if($obj==2){
    return "충전완료";
  }else if($obj==5){
    return "취소";
  }
}

function 충전전환출금상태($status, $name, $obj){
  if($obj==1){
    if($status=="충전"){
      return "<span style='color:#194A9C;font-weight: 700;'>".$status." 중</span>";
    }else if($status=="지급"){
      return "<span style='color:#194A9C;font-weight: 700;'>".$status." </span>";
    }else if($status=="출금"){
      return "<span style='color:#666;font-weight: 700;'>출금 요청중</span>";
    }

  }else if($obj==2){
    if($status=="출금"){
      $color = "color: #194A9C;";
    }else{
      $color = "color: #999999;";
    }
    return "<span style='{$color}'>{$name} ".$status."</span>";
  }else if($obj==6){ // 당첨금 출금완료
    return "<span style='color:red;' ><b>".$status." 완료</b></span>";
  }else{
    return "<span>".$status." 취소</span>";
  }
}

function 충전전환출금표기($구분){
  if($구분=="당첨금"){
    return "원";
  }else if($구분=="럭키포인트"){
    return "P";
  }else{
    return "캐시";
  }
}

function 게임구매_럭키포인트_지급로그($data){
  //로그
  $sql = "insert into lr_point_log set ";
  $sql.= "midx = {$data['midx']},";
  $sql.= "grade = {$data['grade']}, ";
  $sql.= "buy_idx = {$data['buy_idx']}, ";
  $sql.= "gubun = '{$data['gubun']}', ";
  $sql.= "name = '{$data['name']}', ";
  $sql.= "etc = '{$data['etc']}', ";
  $sql.= "status = {$data['status']}, ";
  $sql.= "point = {$data['point']}, ";
  $sql.= "regdate = now() ";
  db_query($sql);

  //실제 지급
  db_query("update lr_member set lucky_point = lucky_point + {$data['point']} where idx = {$data['midx']}");
}


function 포인트로그($midx, $gubun, $name="포인트", $point, $etc=""){

  $sql = "insert into lr_point_log set ";
  $sql.= "midx = {$midx},";
  $sql.= "gubun = '{$gubun}', ";
  if($gubun=="지급"){ //당첨금
    $sql.= "name = '미로코포인트', ";
    $sql.= "status = 1, ";
    $sql.= "etc = '당첨금 전환',";
  }else if($gubun=="출금"){ //당첨금
    $sql.= "name = '{$name}', ";
    $sql.= "status = 1, ";
    $sql.= "etc = '계좌 출금',";
  }else if($gubun=="차감"){
    $sql.= "name = '{$name}', ";
    $sql.= "status = 2, ";
    $sql.= "etc = '{$etc}',";
  }

  $sql.= "point = {$point},";
  $sql.= "regdate = now() ";
  $result = db_query($sql);
  return $result;
}

function 당첨금차감_출금($data){
  //당첨금이 포인트로 전환됬으니 당첨금 차감처리
  $sql = "insert into lr_prize_log set ";
  $sql.= "orderidx = 0, ";
  $sql.= "midx = {$data['midx']}, ";
  $sql.= "user_id = '{$data['userid']}', ";
  $sql.= "gubun = '{$data['gubun']}', ";
  $sql.= "etc = '당첨금', ";
  $sql.= "prizemoney = {$data['point']}, ";
  $sql.= "status = {$data['status']}, ";
  $sql.= "usa_chk = 1, ";
  $sql.= "regdate = now() ";
  $result = db_query($sql);
  return $result;
}

function 게임명($game){
  if($game=="pb"){
    return "파워볼";
  }else if($game=="mm"){
    return "메가밀리언";
  }
}
function 사이트설정(){
  $config = db_select("select * from lr_config");
  return $config;
}

function 환율(){
  $환율 = db_select("select * from lr_exchange order by date desc limit 1");
  return intval($환율['amount']);
}

function 당첨금원화단위변경($number, $status="") {
  if($status!="서브"){
      $억원표시 = "억원";
  }
  if ($number >= 1000000000000) {
      // 1조 (조 억원)
      $trillions = floor($number / 1000000000000);
      $billions = floor(($number - ($trillions * 1000000000000)) / 100000000);
      return $trillions . "조" . ($billions > 0 ? number_format($billions) . "" : "") . $억원표시;
  } elseif ($number >= 100000000) {
      // 1억 (억원)
      $billions = floor($number / 100000000);
      return number_format($billions) . $억원표시;
  } else {
      return number_format($number) . "원";
  }
}

function 요일($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date("w", $timestamp);

    switch ($dayOfWeek) {
        case 0:
            return "일요일";
        case 1:
            return "월요일";
        case 2:
            return "화요일";
        case 3:
            return "수요일";
        case 4:
            return "목요일";
        case 5:
            return "금요일";
        case 6:
            return "토요일";
        default:
            return "알 수 없음";
    }
}

function 파워볼(){
  $power_ball  = db_select("select * from lr_result where game = 'pb' order by drawdate desc limit 1 ");
  return $power_ball;
}
function 메가밀리언(){
  $mega_ball = db_select("select * from lr_result where game = 'mm' order by drawdate desc limit 1 ");
  return $mega_ball;
}

function 당첨금높은순(){
  $파워볼 = 파워볼();
  $메가밀리언 = 메가밀리언();
  if($파워볼['prize']>$메가밀리언['prize']){ // power up
    $sort = 1;
  }else{ //mega up
    $sort = 2;
  }
  return $sort;
}

function 추첨일요일반환($dateTimeString) {
    // 입력된 날짜와 시간 문자열을 DateTime 객체로 변환 (Asia/Seoul 시간대 설정)
    $timezone = new DateTimeZone('Asia/Seoul');
    $date = new DateTime($dateTimeString, $timezone);

    // 날짜에 따른 한국어 요일 배열
    $koreanDays = array("일", "월", "화", "수", "목", "금", "토");

    // 요일을 숫자로 얻어옴 (0: 일요일, 1: 월요일, ...)
    $dayOfWeek = (int)$date->format('w');

    // 숫자로 된 요일을 한국어 요일로 변환하여 반환
    return $koreanDays[$dayOfWeek];
}



function 메가밀리언_결과일($dateString) {
    // 입력된 날짜 문자열을 DateTime 객체로 변환
    $date = new DateTime($dateString);
    // 요일을 숫자로 얻어옴 (0: 일요일, 1: 월요일, ...)
    $dayOfWeek = (int)$date->format('w');

    if($dayOfWeek=="3"){
      $추첨날 = date("Y-m-d",strtotime($dateString." +3 days"));
    }else if($dayOfWeek=="6"){
      $추첨날 = date("Y-m-d",strtotime($dateString." +4 days"));
    }

    // 숫자로 된 요일을 한국어 요일로 변환하여 반환
    return $추첨날;
}

function 파워볼_결과일($date) {
    // 입력받은 날짜를 DateTime 객체로 변환
    $inputDate = new DateTime($date);
    // 요일을 숫자로 얻어옴 (0: 일요일, 1: 월요일, ...)
    $dayOfWeek = (int)$inputDate->format('w');

		if($dayOfWeek==2){ //화요일
				$_data = date("Y-m-d", strtotime($date." +2 days "));
		}else if($dayOfWeek==4){ // 목요일
				$_data = date("Y-m-d", strtotime($date." +3 days "));
		}else if($dayOfWeek==0){ // 일요일
				$_data = date("Y-m-d", strtotime($date." +2 days "));
		}

    return $_data;
}

function 다음회차($endDateString) {

  // 현재 시간을 Asia/Seoul 시간대로 설정
  $timezone = new DateTimeZone('Asia/Seoul');
  //$_settime = '2023-09-03 02:30:00'; //test
  $currentDateTime = new DateTime('now', $timezone);

  // 종료 날짜의 오전 2시를 생성
  $endDateTime = new DateTime($endDateString, $timezone);
  $서머타임 = 미국서머타임();

  $endDateTime->setTime(intval($서머타임['주문마감시간']), 0, 0); // 종료 날짜의 오전 2시

  // 현재 시간과 종료 날짜의 오전 2시를 비교하여 판단
  return $currentDateTime < $endDateTime;
}

function 이름가운데별표처리($inputString) {
    $outputString = '';
    for ($i = 0; $i < strlen($inputString); $i++) {
        if (($i + 1) % 2 == 0) {
            $outputString .= '*';
        } else {
            $outputString .= $inputString[$i];
        }
    }
    return $outputString;
}

function 문자열2와3자리별표처리($string) {
    // 문자열의 길이가 2 이하인 경우 원래 문자열을 반환
    if (strlen($string) <= 2) {
        return $string;
    }

    // 첫 번째 문자는 그대로 유지하고, 두 번째와 세 번째 문자를 별표로 변경
    $maskedString = $string[0] . '**' . substr($string, 3);

    return $maskedString;
}

function 문자열앞3자리놔두고마스킹($str) {
  $length = mb_strlen($str, 'UTF-8'); // 문자열 길이 (UTF-8 고려)

  if ($length <= 3) {
      return $str; // 3자리 이하라면 그대로 반환
  }

  $visiblePart = mb_substr($str, 0, 3, 'UTF-8'); // 앞 3자리
  $maskedPart = str_repeat('*', $length - 3); // 나머지 * 처리

  return $visiblePart . $maskedPart;
}

function 게임_추첨결과_체크($game, $kr_date){
  $_파워볼_결과 = "select * from lr_result where game = '{$game}' and ko_date = '{$kr_date}' and prize != ''  ";
  $_data = db_select($_파워볼_결과);
  return $_data;
}

/**
  function 파워볼_추첨일체크($data="") {
    // 한국 타임존 설정
    date_default_timezone_set('Asia/Seoul');
    // 현재 요일 가져오기 (0: 일요일, 1: 월요일, 2: 화요일, ...)
    $currentDayOfWeek = (int)date('w');
    // 현재 시간 가져오기 (24시간 형식)
    $currentHour = (int)date('G');
    // 조건 확인: 화요일, 목요일, 일요일이며 오전 2시부터 오후 12시 사이
    if($data){
      return false;
    }else{
      // && ($currentHour >= 2 && $currentHour <= 15)
      if (($currentDayOfWeek === 2 || $currentDayOfWeek === 4 || $currentDayOfWeek === 0)) {
          // 현재 날짜를 "yyyy-mm-dd" 형식으로 반환
          return date('Y-m-d');
      } else {
          return false;
      }
    }
  }

  function 메가볼_추첨일체크($data="") {
      // 한국 타임존 설정
      date_default_timezone_set('Asia/Seoul');

      // 현재 요일 가져오기 (0: 일요일, 1: 월요일, 2: 화요일, ...)
      $currentDayOfWeek = (int)date('w');

      // 현재 시간 가져오기 (24시간 형식)
      $currentHour = (int)date('G');

      if($data){
        return false;
      }else{
        // 조건 확인: 화요일, 목요일, 일요일이며 오전 2시부터 오후 12시 사이
        if (($currentDayOfWeek === 3 || $currentDayOfWeek === 6) && ($currentHour >= 2 && $currentHour <= 12)) {
            // 현재 날짜를 "yyyy-mm-dd" 형식으로 반환
            return date('Y-m-d');
        } else {
            return false;
        }
      }
  }
**/

  function 메가밀리언_수요일_토요일($회차,$서머타임) {
      // 현재 날짜 및 시간 가져오기
      $현재날짜시간 = new DateTime();
      $현재날짜시간->setTimezone(new DateTimeZone('Asia/Seoul'));

      // 현재 날짜의 요일을 얻기
      $currentDayOfWeek = $현재날짜시간->format('N');

      // 현재 날짜의 새벽 3시 이후인지 확인
      if ($현재날짜시간->format('H') >= $서머타임) {
          //money1 필드에 값이 있는 경우 추첨데이터가 나온 것
          $오늘 = $현재날짜시간->format("Y-m-d");
          $sql = "select * from lr_result where game = 'mm' and money1 > 0 and ko_date = '{$오늘}' ";
          $메가추첨 = db_select($sql);

          // 수요일(3) 또는 토요일(6)이면서 3시 이후인지 확인
          if (($currentDayOfWeek == 3 || $currentDayOfWeek == 6) && !$메가추첨['idx']) {
              // 수요일 또는 토요일이면서 3시 이후
              return true;
          }
      }

      // 다른 요일이거나 3시 이전
      return false;
  }


  function 파워볼_화목일($회차,$서머타임) {
      // 현재 날짜 및 시간 가져오기
      $현재날짜시간 = new DateTime();
      $현재날짜시간->setTimezone(new DateTimeZone('Asia/Seoul'));

      // 현재 날짜의 요일을 얻기
      $currentDayOfWeek = $현재날짜시간->format('N');

      // 현재 날짜의 새벽 3시 이후인지 확인

      if ($현재날짜시간->format('H') >= $서머타임) {
          //money1 필드에 값이 있는 경우 추첨데이터가 나온 것
          $오늘 = $현재날짜시간->format("Y-m-d");
          $sql = "select * from lr_result where game = 'pb' and money1 > 0 and ko_date = '{$오늘}' ";

          $파워볼추첨 = db_select($sql);

          // 화요일(2), 목요일(4), 일요일(7)이고, 3시 이후인지 확인
          if (($currentDayOfWeek == 2 || $currentDayOfWeek == 4 || $currentDayOfWeek == 7) && !$파워볼추첨['idx']) {
              // 화요일, 목요일, 일요일이면서 3시 이후
              return true;
          }
      }
      // 다른 요일이거나 3시 이전
      return false;
  }

  function 쿠폰사용가능여부($status,$기한){
    $currentDate = new DateTime();
    $targetDate = new DateTime($기한);
    $날짜비교 = $currentDate > $targetDate;
      if($status){
        echo "<span style='color:#666;'>사용완료</span>";
      }else if($날짜비교){
          echo "<span style='color:#ccc;'>기간만료</span>";
      }else{
        echo "<span style='color:#CC1F3B;' >사용가능</span>";
      }
  }

  function isMobileDevice() {
      // 사용자 에이전트 문자열 가져오기
      $userAgent = $_SERVER['HTTP_USER_AGENT'];

      // 일반적으로 모바일 브라우저에 포함되는 문자열 검사
      $mobileKeywords = array('Android', 'iPhone', 'iPad', 'Windows Phone', 'BlackBerry', 'Mobile', 'Opera Mini', 'Kindle', 'Silk');

      // 사용자 에이전트 문자열에 모바일 키워드가 포함되었는지 확인
      foreach ($mobileKeywords as $keyword) {
          if (stripos($userAgent, $keyword) !== false) {
              return true; // 모바일 기기에서 접속 중
          }
      }

      return false; // PC 데스크톱에서 접속 중
  }

  function isW2nAndroid($userAgent) {
    // User-Agent 문자열에서 "w2n/android"을 검색합니다. 대소문자 구분 없이 검색하려면 stristr() 함수를 사용합니다.
    if (strpos($userAgent, 'w2n/android') !== false || stristr($userAgent, 'w2n/android') !== false) {
        return true;
    } else {
        return false;
    }
  }

  function 카카오가입자($inputString) {
      if (strpos($inputString, 'kakao_') === 0) {
          return true;
      } else {
          return false;
      }
  }

  function 일등당첨예상금액($number, $percentage){

    $increase = $number * ($percentage / 100);
    $result = $number + $increase;
    return $result;
  }

  function 미국서머타임() {
      $currentYear = date('Y'); // 현재 년도 가져오기
      $currentDate = date('Y-m-d'); // 현재 날짜 가져오기
      $marchSecondWeekStart = date($currentYear . '-03-08');
      $marchSecondWeekEnd = date($currentYear . '-03-14');
      $novemberFirstWeekStart = date($currentYear . '-11-01');
      $novemberFirstWeekEnd = date($currentYear . '-11-03');

      if ($currentDate >= $marchSecondWeekStart && $currentDate <= $novemberFirstWeekEnd) {
  			$_data = array("추첨시간" => 12, "주문마감시간" => "09"); //기존 02
          return $_data;
      } else {
  			$_data = array("추첨시간" => 13, "주문마감시간" => "10"); //기존 03
        // 10시 12시 1초 이후 주문건은 새벽4시 이후 1장당 5초, 업로드 7시에 매칭작업
        // 12시 2초부터 아침 10시까지 주문한것들은 한국시간으로 10시 10분 1장 5초 간격 11시 50분에 일괄 처리
        return $_data;
      }
  }

  function 나의등급($obj){
    if($obj==1){
      return "White";
    }else if($obj==2){
      return "Green";
    }else if($obj==3){
      return "Sliver";
    }else if($obj==4){
      return "Gold";
    }else if($obj==5){
      return "Balck";
    }
  }

  //럭키 등급
  function 구매금액_마일리지_지급퍼센트($obj){
    if($obj==1){
        return 0.005;
    }else if($obj==2){
        return 0.025;
    }else if($obj==3){
        return 0.05;
    }else if($obj==4){
        return 0.08;
    }else if($obj==5){
        return 0.09;
    }else{
        return 0.005;
    }
  }

  function 지급마일리지로그($midx, $bidx, $grade, $point, $memo){
    $sql = "insert into lr_luckypoint_log set ";
    $sql.= "midx = {$midx}, ";
    $sql.= "buy_idx = {$bidx}, ";
    $sql.= "grade = '{$grade}', ";
    $sql.= "point = {$point},";
    $sql.= "memo = '{$memo}', ";
    $sql.= "regdate = now() ";
    db_query($sql);
  }

  function 휴대폰번호_영문숫자패턴($input) {
      // 숫자만 추출
      $cleaned = preg_replace('/\D/', '', $input);

      // 휴대폰 번호 패턴 확인
      if (preg_match('/^(\d{3})(\d{4})(\d{4})$/', $cleaned, $matches)) {
          return $matches[1] . '-' . $matches[2] . '-' . $matches[3]; // 휴대폰 번호 패턴이면 형식에 맞게 반환
      }

      // 영문 + 숫자 조합 패턴 확인
      if (ctype_alnum($input)) {
          return $input; // 영문 + 숫자 조합이면 그대로 반환
      }

      return false; // 어떤 패턴에도 해당하지 않으면 false 반환
  }

  function 휴대폰번호_이메일_영문숫자패턴($input) {
    // 숫자만 추출
    $cleaned = preg_replace('/\D/', '', $input);

    // 휴대폰 번호 패턴 확인
    if (preg_match('/^(\d{3})(\d{4})(\d{4})$/', $cleaned, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3]; // 휴대폰 번호 패턴이면 형식에 맞게 반환
    }

    // 이메일 패턴 확인
    if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
        return $input; // 이메일 패턴이면 그대로 반환
    }

    // 영문 + 숫자 조합 패턴 확인
    if (ctype_alnum($input)) {
        return $input; // 영문 + 숫자 조합이면 그대로 반환
    }

    return false; // 어떤 패턴에도 해당하지 않으면 false 반환
  }

  function PC모바일체크() {
    return preg_match('/Mobile|iP(hone|od|ad)|Android|BlackBerry|IEMobile/', $_SERVER['HTTP_USER_AGENT']) ? "MOBILE" : "PC";
  }

  function 구매번호콤마제거1($inputString,$gubun){
      // 콤마 제거
      $resultString = str_replace(',', '', $inputString);

      // 두 자리씩 <span>으로 감싸기
      $resultArray = str_split($resultString, 2);
      $wrappedResult = '';

      foreach ($resultArray as $key => $value) {
          // 마지막 2자리에는 span에 class 추가
          $class = ($key == count($resultArray) - 1) ? 'lastTwoDigits_'.$gubun : '';
          $style = ($key == count($resultArray) - 1) ? 'background: #0051c7;'.$gubun : '';

          $wrappedResult .= "<span class='{$class}' style='{$style}' >{$value}</span>";
      }
      return $wrappedResult;
  }
  function 구매번호콤마제거2($inputString,$gubun){
      // 콤마 제거
      $resultString = str_replace(',', '', $inputString);

      // 두 자리씩 <span>으로 감싸기
      $resultArray = str_split($resultString, 2);
      $wrappedResult = '';

      foreach ($resultArray as $key => $value) {
          // 마지막 2자리에는 span에 class 추가
          $class = ($key == count($resultArray) - 1) ? 'lastTwoDigits_'.$gubun : '';
          $style = ($key == count($resultArray) - 1) ? 'background: #e13b2c;'.$gubun : '';

          $wrappedResult .= "<span class='{$class}' style='{$style}' >{$value}</span>";
      }
      return $wrappedResult;
  }


  function 스캔파일_다운로드($Path,$File,$Org=""){
      $Org=($Org ? $Org : $File);
      $DownFile =$Path."/".$File;

      $Org=iconv("UTF-8","EUC-KR",$Org);

      Header("Cache-Control: cache, must-revalidate, post-check=0, pre-check=0");
      Header("Content-type: application/x-msdownload");
      Header("Content-Length: ".(string)(filesize($DownFile)));
      Header("Content-Disposition: attachment; filename=".$Org."");
      Header("Content-Description: PHP5 Generated Data");
      Header("Content-Transfer-incoding: euc_kr");
      Header("Content-Transfer-Encoding: binary");
      Header("Pragma: no-cache");
      Header("Expires: 0");
      Header("Content-Description: File Transfer");

      if (is_file($DownFile)) {
          $fp = fopen($DownFile, "rb");

          if (!fpassthru($fp)) fclose($fp);
          clearstatcache();
      } else {
          ErrorMessage("해당파일이나 경로가 존재하지 않습니다.");
          exit();
      }
  }
  function 자동문자($상태=0,$phone,$구분,$제목,$내용){
    $내용2 = "[Web발신]\n";
    $내용2.= $내용;

    $sql = "insert into lr_sms_send_log set ";
    $sql.= "status = 0, ";
    $sql.= "phone = '{$phone}', ";
    $sql.= "gubun = '{$구분}', ";
    $sql.= "title = '{$제목}', ";
    $sql.= "content = '{$내용2}', ";
    $sql.= "regdate = now() ";
    echo $sql;
    $result = db_query($sql);
    return $result;
  }

  function 문자설정($구분, $idx){
    $result = db_select("select * from lr_sms_send_mng where gubun = '{$구분}' and idx = {$idx} ");
    return $result;
  }

  function 당첨내역($game, $win, $gubun){
    // 1,2등 파워3등은 자동삭제되니..
    if($win > 1 and $win <= 3 ){ // 메가 1~3 등?
      echo "<div class='flex' style='justify-content: space-between;'>";
      echo "  <div>".$game." &nbsp;</div>";
      echo "  <div class='flex'>";
      echo "    <div style='font-weight: bold;'>3등</div>";
      echo "  </div>";
      echo "</div>";
    }else if($win > 3 and $win <= 9 ){ // 메가 4..5등?
      echo "<div class='flex' style='justify-content: space-between;'>";
      echo "  <div>".$game." &nbsp;</div>";
      echo "  <div class='flex'>";
      echo "    <div style='font-weight: bold;'>".$win."등</div>";
      echo "  </div>";
      echo "</div>";
    }else{
      echo "";
    }
  }

  function 랜덤소문자한자리() {
      // 랜덤 소문자 생성
      $randomLetter = chr(rand(97, 122)); // ASCII 값 범위: 97(a) ~ 122(z)
      return $randomLetter;
  }


  function save_remote_image($url, $filename)
  {
    $file_url = $url;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);

    curl_exec ($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if($http_code == 200) {
        // 파일 다운로드
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-type: application/octet-stream");
        header("Content-Transfer-Encoding: binary");

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);

        $file = curl_exec ($ch);
        curl_close($ch);
    } else {
        die('파일이 존재하지 않습니다.');
    }
  }


  function 다이랙트샌드($수신번호,$제목,$내용){
    $ch = curl_init();

    $title = $제목;
    $message = $내용;             //필수입력
    $sender = "07080186919";                    //필수입력
    $username = "dudrhks0319";                //필수입력
    $key = "zjuT7SQ1FZTgMjn";           //필수입력

    //수신자 정보 추가 - 필수 입력(주소록 미사용시), 치환문자 미사용시 치환문자 데이터를 입력하지 않고 사용할수 있습니다.
    //치환문자 미사용시 "{"mobile":"01000000001"} 번호만 입력 해주시기 바랍니다.
    $receiver = '{"mobile":"'.$수신번호.'"}';

    $receiver = '['.$receiver.']';

    // 예약발송 정보 추가
    $sms_type = 'NORMAL'; // NORMAL - 즉시발송 / ONETIME - 1회예약 / WEEKLY - 매주정기예약 / MONTHLY - 매월정기예약
    $start_reserve_time = date('Y-m-d H:i:s'); //  발송하고자 하는 시간(시,분단위까지만 가능) (동일한 예약 시간으로는 200회 이상 API 호출을 할 수 없습니다.)
    $end_reserve_time = date('Y-m-d H:i:s'); //  발송이 끝나는 시간 1회 예약일 경우 $start_reserve_time = $end_reserve_time
    // WEEKLY | MONTHLY 일 경우에 시작 시간부터 끝나는 시간까지 발송되는 횟수 Ex) type = WEEKLY, start_reserve_time = '2017-05-17 13:00:00', end_reserve_time = '2017-05-24 13:00:00' 이면 remained_count = 2 로 되어야 합니다.
    $remained_count = 1;
    // 예약 수정/취소 API는 소스 하단을 참고 해주시기 바랍니다.

    // 실제 발송성공실패 여부를 받기 원하실 경우 아래 주석을 해제하신 후, 사이트에 등록한 URL 번호를 입력해 주시기 바랍니다.
    //$return_url_yn = TRUE;        //return_url 사용시 필수 입력
    //$return_url = 0;

    /* 여기까지 수정해주시기 바랍니다. */
    $message = str_replace(' ', ' ', $message);  //유니코드 공백문자 치환

    // 첨부파일이 있을 시 아래 주석을 해제하고 첨부하실 파일의 URL을 입력하여 주시기 바랍니다.
    // jpg파일당 300kb 제한 3개까지 가능합니다.
    //$file[] = array('attc' => 'https://directsend.co.kr/jpgimg1.jpg');
    //$file[] = array('attc' => 'https://directsend.co.kr/jpgimg2.jpg');
    //$file[] = array('attc' => 'https://directsend.co.kr/jpgimg3.jpg');
    //$attaches = json_encode($file);

    $postvars = '"title":"'.$title.'"';
    $postvars = $postvars.', "message":"'.$message.'"';
    $postvars = $postvars.', "sender":"'.$sender.'"';
    $postvars = $postvars.', "username":"'.$username.'"';
    $postvars = $postvars.', "receiver":'.$receiver.'';
    //$postvars = $postvars.', "address_books":"'.$address_books.'"';       //주소록 사용할 경우 주석 해제
    //$postvars = $postvars.', "duplicate_yn":"'.$duplicate_yn.'"';         //중복 발송을 허용할 경우 주석 해제
    //$postvars = $postvars.', "return_url_yn":"'.$return_url_yn.'"';       // return_url이 있는 경우 주석해제 바랍니다.
    //$postvars = $postvars.', "return_url":"'.$return_url.'"';             // return_url이 있는 경우 주석해제 바랍니다.
    //$postvars = $postvars.', "attaches":'.$attaches;                      //첨부파일이 있는 경우 주석해제 바랍니다.
    //$postvars = $postvars.', "sms_type":"'.$sms_type.'"';                        // 예약 관련 정보 사용할 경우 주석 해제
    //$postvars = $postvars.', "start_reserve_time":"'.$start_reserve_time.'"';    // 예약 관련 정보 사용할 경우 주석 해제
    //$postvars = $postvars.', "end_reserve_time":"'.$end_reserve_time.'"';        // 예약 관련 정보 사용할 경우 주석 해제
    //$postvars = $postvars.', "remained_count":"'.$remained_count.'"';            // 예약 관련 정보 사용할 경우 주석 해제
    $postvars = $postvars.', "key":"'.$key.'"';
    $postvars = '{'.$postvars.'}';      //JSON 데이터

    $url = "https://directsend.co.kr/index.php/api_v2/sms_change_word";         //URL

    //헤더정보
    $headers = array("cache-control: no-cache","content-type: application/json; charset=utf-8");

    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POST, true);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $postvars);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);

    if(curl_errno($ch)){
        echo 'Curl error: ' . curl_error($ch);
    }else{
        return $response->status;
    }

    curl_close ($ch);
  }


function 추천코드포인트($midx,$point,$etc){
  $sql = "insert into lr_point_log set ";
  $sql.= "midx = {$midx}, ";
  $sql.= "gubun = '지급', ";
  $sql.= "name = '{$etc}', ";
  $sql.= "etc = '{$etc}', ";
  $sql.= "status = 1, ";
  $sql.= "point = {$point}, ";
  $sql.= "regdate = now() ";
  db_query($sql);
  $result = db_query("update lr_member set point = point + {$point} where idx = {$midx} ");

  return $result;
}

function 게임수별_프린트_높이($game, $gcnt){
  if($game=="mm"){
    if($gcnt==1){
      $height = "725";
    }else if($gcnt==2){
      $height = "750";
    }else if($gcnt==3){
      $height = "780";
    }else if($gcnt==4){
      $height = "810";
    }else if($gcnt==5){
      $height = "845";
    }
  }else if($game=="pb"){
    if($gcnt==1){
      $height = "665";
    }else if($gcnt==2){
      $height = "700";
    }else if($gcnt==3){
      $height = "730";
    }else if($gcnt==4){
      $height = "755";
    }else if($gcnt==5){
      $height = "785";
    }
  }
  return $height;
}

function 스캔본_확인시간($time){
    // 주어진 시간을 DateTime 객체로 변환
    $currentTime = new DateTime($time);

    // 오전 10시 1초부터 00시 1초까지는 "07:00"
    $timeStart1 = new DateTime('10:01:00');   // 오전 10시 1초
    $timeEnd1 = new DateTime('23:59:59');      // 00시 1초 전에 종료

    // 00시 2초부터 오전 10시까지는 "11:30"
    $timeStart2 = new DateTime('00:02:00');    // 00시 2초
    $timeEnd2 = new DateTime('10:00:00');      // 오전 10시 까지

    // **"07:00"** 시간대 조건: 오전 10시 1초 ~ 00시 1초까지
    if ($currentTime >= $timeStart1 && $currentTime <= $timeEnd1) {
        return '<span>07:00</span> 이후';
    }

    // **"11:30"** 시간대 조건: 00시 2초 ~ 오전 10시 까지
    if ($currentTime >= $timeStart2 && $currentTime < $timeEnd2) {
        return '<span>11:30</span> 이후';
    }

    // 조건을 만족하지 않으면 Invalid Time
    return '<span>07:00</span> 이후';
}


function 당첨조건($rank, $gubun) {
  if($gubun=="pb"){
    $게임 = "파워볼";
  }else{
    $게임 = "메가볼";
  }
    // 당첨 조건 배열
    $prizeConditions = [
        1 => '5개 화이트볼 + 1개 '.$게임,
        2 => '5개 화이트볼',
        3 => '4개 화이트볼 + 1개 '.$게임,
        4 => '4개 화이트볼',
        5 => '3개 화이트볼 + 1개 '.$게임,
        6 => '3개 화이트볼',
        7 => '2개 화이트볼 + 1개 '.$게임,
        8 => '1개 화이트볼 + 1개 '.$게임,
        9 => '0개 화이트볼 + 1개 '.$게임
    ];

    // 주어진 등수에 맞는 당첨 조건 반환
    if (isset($prizeConditions[$rank])) {
        return $prizeConditions[$rank];
    } else {
        return 'Invalid rank';  // 유효하지 않은 등수 입력 시
    }
}

function 나의이용내역_지급사용색상($gubun, $point, $etc){
    if($gubun=="지급"){
      return "<font color='blue'>".$point.$etc."</font>";
    }else if($gubun=="지급완료"){
      return "<font color='blue'>".$point.$etc."</font>";
    }else if($gubun=="출금"){
      return "<font color='black' >".$point.$etc."</font>";
    }else if($gubun=="반환"){
      return "<font color='blue' >".$point.$etc."</font>";
    }else if($gubun=="사용"){
      return "<font  >".$point.$etc."</font>";
    }else if($gubun=="차감"){
      return "<font  >".$point.$etc."</font>";
    }else if($gubun=="대기"){
      return "<font color='black' >".$point.$etc."</font>";
    }else if($gubun=="입금대기"){
      return "<font color='black' >".$point.$etc."</font>";
    }else if($gubun=="입금취소"){
      return "<font color='black' >".$point.$etc."</font>";
    }
}

function 쿠폰만들기($쿠폰타입, $midx, $content, $discount, $사용가능일, $추천인idx){
  $_사용마감일 = date("Y-m-d H:i:s", strtotime($사용가능일));
  $쿠폰생성sql = "insert into lr_coupon_log set ";
  $쿠폰생성sql.= "status = 0, ";
  $쿠폰생성sql.= "gubun = '{$쿠폰타입}', ";
  $쿠폰생성sql.= "cidx = 1, ";
  $쿠폰생성sql.= "midx = {$midx},";
  $쿠폰생성sql.= "recommend_id = '{$추천인idx}',";
  $쿠폰생성sql.= "name = '{$content}', ";
  $쿠폰생성sql.= "discount = {$discount}, ";
  $쿠폰생성sql.= "usedate1 = '{$_사용마감일}', ";
  $쿠폰생성sql.= "regdate = now() ";
  $쿠폰결과 = db_query($쿠폰생성sql);

  $sql1 = "insert into lr_coupon_log_list set ";
  $sql1.= "status = 0,";
  $sql1.= "gubun = '{$쿠폰타입}', ";
  $sql1.= "cidx = 1,";
  $sql1.= "midx = {$midx},";
  $sql1.= "recommend_id = '{$추천인idx}',";
  $sql1.= "name = '{$content}',";
  $sql1.= "discount = {$discount},";
  $sql1.= "usedate1 = '{$_사용마감일}',";
  $sql1.= "regdate = now() ";
  db_query($sql1);
  return $쿠폰결과;
}

function 누적충전_구매등급_변경($midx, $amount){
    if($amount > 500000 and $amount < 1000000 ){
        $grade = 2;
    }else if($amount > 1000000 and $amount < 1500000){
        $grade = 3;
    }else if($amount > 1500000 and $amount < 3000000){
        $grade = 4;
    }else if($amount > 3000000){
        $grade = 5;
    }else{
        $grade = 1;
    }

    $변경된날짜 = date("Y-m-d");
    $sql = "update lr_member set grade = {$grade}, grade_date = '{$변경된날짜}' where idx = {$midx} ";

    db_query($sql);
}

function 카드결제_캐시지급($cash_config_idx, $누적캐시, $충전금,$고유아이디){
  if($cash_config_idx>0){
    $쿠폰_할인율_적용 = true;
  }
  if($쿠폰_할인율_적용){
    $sql = "select * from lr_deposit_coupon where coupon_idx = {$cash_config_idx} ";
    $result = db_query($sql);
    foreach($result as $val){
      $_쿠폰만료일10 = date("Y-m-d",strtotime("+{$val['maxday']}days"));
      db_query("insert into lr_coupon_log set status = 0, cidx = {$val['coupon_idx']}, midx = {$고유아이디}, name = '{$val['name']}', discount = {$val['discount']}, bdate = '{$_쿠폰만료일10} 23:59:59', regdate = now()  ");
    }
  } //쿠폰 지급이 있는경우 작동

  $_추가포인트 = db_select("select * from lr_card_config where idx = {$cash_config_idx} ");
  if($_추가포인트['add_point']>0){ //추가 럭키포인트
    db_query("update lr_member set lucky_point = lucky_point + {$_추가포인트['add_point']} where idx = {$고유아이디} ");
    포인트로그2($고유아이디, '지급', "미로코포인트", "캐시충전 보너스", 1, $_추가포인트['add_point']);
  }

  if($_추가포인트['scratch']>0){ //스크래치 지급
    for($i=0;$i<$_추가포인트['scratch'];$i++){
      $sql = "insert into event_scratch set status = 0, midx = {$고유아이디}, regdate = now() ";
      db_query($sql);
    }
  }

  //할인쿠폰지급($충전금, $고유아이디);
  $_실제지급포인트 = $충전금;
  $mem = db_select("select * from lr_member where idx = {$고유아이디} ");
  //포인트로그($고유아이디, '지급', "캐시", "계좌이체 캐시 충전", 1, $_실제지급포인트);
  $입금처리상태 = 입금처리($누적캐시, $_실제지급포인트, $고유아이디);

  $총누적금액 = $mem['cash'] + $누적캐시;
   누적충전_구매등급_변경($고유아이디, $총누적금액);
  return $입금처리상태;
}

function 입금처리($누적금액,$포인트,$midx){
  if($누적금액>0){
    $_누적금액 = " cash = cash + {$누적금액}, ";
  }
  $sql = "update lr_member set {$_누적금액} point = point + {$포인트}  where idx = {$midx} ";
  //echo $sql."\n";
  $result = db_query($sql);
  return $result;
}

function 포인트로그2($midx, $gubun, $name="포인트", $etc, $보유포인트, $지급할포인트){
  if($보유포인트>0){
    $sql2 = "select * from lr_member where idx = {$midx} ";
    //echo $sql2."\n";
    $mem = db_select($sql2);
    $_보유포인트 = $mem['point'];
    $_지급할포인트 = $지급할포인트;
  }else{
    $_보유포인트 = $보유포인트;
    $_지급할포인트 = $지급할포인트;
  }

  $sql = "insert into lr_point_log set ";
  $sql.= "midx = {$midx},";
  $sql.= "gubun = '{$gubun}', ";
  $sql.= "name = '{$name}', ";
  if($gubun=="지급"){
    $sql.= "status = 2, ";
    $sql.= "etc = '{$etc}', ";
  }else if($gubun=="출금"){
    $sql.= "status = 1, ";
    $sql.= "etc = '{$etc}', ";
  }else if($gubun=="차감"){
    $sql.= "status = 2, ";
    $sql.= "etc = '{$etc}',";
  }else{
    $sql.= "status = 1, ";
    $sql.= "etc = '{$etc}',";
  }
  $sql.= "point = {$_지급할포인트},"; //지급 포인트
  if($name!="럭키포인트"){
    $sql.= "before_point = {$_보유포인트},"; //지급전 가지고 잇던 포인트
    if($_보유포인트>0){
      $_총포인트 = $_보유포인트 + $_지급할포인트;
      $sql.= "after_point = {$_총포인트},"; // 지급 후 현재 포인트
    }
  }
  $sql.= "regdate = now() ";
  //echo $sql."\n";
  $result = db_query($sql);
  return $result;
}

function 첫구매체크($midx){
  $aaa = db_select("select count(*) as cnt from lr_orders where midx = {$midx} ");
  if($aaa['cnt']==1){
    $data = db_select("select * from lr_member where idx = {$midx} ");
    $sql = "insert into lr_first_order_sms set ";
    $sql.= "midx = {$midx}, ";
    $sql.= "status = '0', ";
    $sql.= "phone = '{$data['phone']}', ";
    $sql.= "ordertime = now(), ";
    $sql.= "regdate = now() ";
    db_query($sql);
  }
}

function 다음회차주문하기($game, $datetime = null) {
    if ($datetime === null) {
        $datetime = new DateTime();
    }

    $dayOfWeek = (int)$datetime->format('w'); // 0(일) ~ 6(토)

    $hour = (int)$datetime->format('G');      // 0 ~ 23

    // 시간 조건: 오전 9시 이상 ~ 낮 12시 미만
    if (!($hour >= 9 && $hour < 12)) {
        return false;
    }

    // 게임별 허용 요일 설정
    $validDays = [
        'mm' => [3, 6],       // 수, 토
        'pb' => [0, 2, 4],    // 일, 화, 목
    ];

    // 허용되지 않은 게임 이름이면 false
    if (!isset($validDays[$game])) {
        return false;
    }

    // 현재 요일이 해당 게임의 허용 요일에 포함되어 있는지 확인
    return in_array($dayOfWeek, $validDays[$game]);
}

function getUSADSTStatus($status, $timezone = 'America/New_York') {
    // 지정된 미국 시간대 설정 (기본은 뉴욕)
    $tz = new DateTimeZone($timezone);
    $now = new DateTime('now', $tz);

    // 현재 서머타임 적용 여부 확인 (1 = 적용 중, 0 = 해제됨)
    $isDST = (int) $now->format('I');

    // 결과 반환
    if($status=="main"){
      return $isDST ? "낮 12시" : "오후 1시";
    }else{
      return $isDST ? "12:00" : "13:00";
    }

}


function getBrowser($ua){
    // 🔥 IE 감지 (가장 먼저 체크)
    // IE 10 이하: MSIE
    if (strpos($ua, 'MSIE') !== false) {
        preg_match('/MSIE\s([0-9\.]+)/i', $ua, $match);
        return 'IE ' . ($match[1] ?? '');
    }

    // IE 11: Trident + rv:11.0
    if (strpos($ua, 'Trident') !== false && strpos($ua, 'rv:11.0') !== false) {
        return 'IE 11';
    }

    // Edge(크로미움 아닌 구버전)
    if (strpos($ua, 'Edge') !== false) return 'Edge Legacy';

    // 신형 Edge(크로미움)
    if (strpos($ua, 'Edg') !== false) return 'Edge';

    // 다른 브라우저들
    if (strpos($ua, 'Chrome') !== false) return 'Chrome';
    if (strpos($ua, 'Firefox') !== false) return 'Firefox';
    if (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) return 'Safari';
    if (strpos($ua, 'OPR') !== false || strpos($ua, 'Opera') !== false) return 'Opera';

    return 'Unknown';
}

function getOS($ua){
    if (preg_match('/Windows/i', $ua)) return 'Windows';
    if (preg_match('/Macintosh|Mac OS X/i', $ua)) return 'MacOS';
    if (preg_match('/Android/i', $ua)) return 'Android';
    if (preg_match('/iPhone|iPad/i', $ua)) return 'iOS';
    if (preg_match('/Linux/i', $ua)) return 'Linux';
    return 'Unknown';
}

function getTelegramChatIds() {
    return [8075600950, 6854091508, 7799852886];
}

function sendTelegram($chat_id, $message) {
    $token = "8670881342:AAHG69ISUhHLNsCKwj6rIo2t9NfRLun24c8";

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chat_id,
            'text' => $message
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

function sendTelegramToMany($message, $chat_ids = null) {
    if ($chat_ids === null) {
        $chat_ids = getTelegramChatIds();
    }
    foreach ((array)$chat_ids as $chat_id) {
        sendTelegram($chat_id, $message);
    }
}

function SEO키워드숨김(){
  global $SEO키워드노출;
  return empty($SEO키워드노출);
}

function SEO키워드HTML필터($html){
  if ($html === '' || $html === false) {
    return $html;
  }
  $html = str_replace(
    array('미국복권구매대행', '미국로또구매대행', '미국복권', '미국로또', '미국 복권'),
    '',
    $html
  );
  $html = preg_replace('/(<title>[^<]*?)\s+-\s*(<\/title>)/u', '$1$2', $html);
  $html = preg_replace('/\|{2,}/', '|', $html);
  return $html;
}

if (SEO키워드숨김()) {
  ob_start('SEO키워드HTML필터');
}
