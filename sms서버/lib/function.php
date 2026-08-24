<?php
header('Content-Type: text/html; charset=UTF-8');
@session_start();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');

/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$conn = mysqli_connect("localhost","sms","Gkstlr59!","sms");
mysqli_query($conn, "set names utf8mb4");
//DB 접속
function db_connect($db_host, $db_user, $db_pass, $db_name){
    $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
    mysqli_select_db($db_name) or die(mysql_error());
    mysqli_query($conn, "set names utf8");
    return $result;
}

//SQL 쿼리 실행 함수
function db_query($sql){
    global $conn;

    $rs = mysqli_query($conn, $sql);
    return $rs;
}

//개별데이터
function db_select($sql){
    $rs = db_query($sql);
    return @db_fetch($rs);
}

//데이터를 배열로 가져오기
function db_fetch($rs){
    return @mysqli_fetch_array($rs);
}

function db_result($sql){
    $rs = db_query($sql);
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}

function SMS_SEND($받는사람, $제목="안녕하세요",$내용){
    /**************** 문자전송하기 예제 필독항목 ******************/
    /* 동일내용의 문자내용을 다수에게 동시 전송하실 수 있습니다 */
    /* 대량전송시에는 반드시 컴마분기하여 1천건씩 설정 후 이용하시기 바랍니다. (1건씩 반복하여 전송하시면 초당 10~20건정도 발송되며 컨텍팅이 지연될 수 있습니다.)*/
    /* 전화번호별 내용이 각각 다른 문자를 다수에게 보내실 경우에는 send 가 아닌 send_mass(예제:curl_send_mass.html)를 이용하시기 바랍니다.*/
    /****************** 인증정보 시작 ******************/
    $sms_url = "https://apis.aligo.in/send/"; // 전송요청 URL
    $sms['user_id'] = "yghappy"; // SMS 아이디
    $sms['key'] = "6732y15obrxyyktvpwjlrip5vsuijxmz";//인증키
    /****************** 인증정보 끝 ********************/

    /****************** 전송정보 설정시작 ****************/
    $_POST['msg'] = $내용; // 메세지 내용 : euc-kr로 치환이 가능한 문자열만 사용하실 수 있습니다. (이모지 사용불가능)
    $_POST['receiver'] = $받는사람; // 수신번호
    $_POST['destination'] = ''; // 수신인 %고객명% 치환
    $_POST['sender'] = $발신번호; // 발신번호
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

      $결과 = json_decode($ret);


    return $결과;
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

function SITE_API_SEND($url, $data){
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>json_encode($data),
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'name: dasdfasdf',
      'Cookie: general_sessions=2u07p3frin7str3pivmmdssgqqh60n5s; token=d6e9707dd23e4c6323554f6c7ee896ed'
    ),
  ));
  $response = curl_exec($curl);
  curl_close($curl);
  $data = json_decode($response, true);
  return $data['status'];
}

function 콤마제거($inputString) {
    $outputString = str_replace(',', '', $inputString);
    return $outputString;
}

function 포함여부($inputString, $value) {
    $pattern = '/' . preg_quote($value, '/') . '/';
    $result = preg_match($pattern, $inputString);

    if ($result === false) {
        return false;
    }

    return $result === 1;
}

function 서비스일체크($date) {
    // 현재 날짜를 가져옵니다
    $currentDate = new DateTime();
    // 비교할 날짜를 설정합니다
    $targetDate = new DateTime($date);
    // 날짜를 비교합니다
    if ($currentDate > $targetDate) {
        return true; // 주어진 날짜가 지났습니다.
    } else {
        return false; // 주어진 날짜가 아직 오지 않았습니다.
    }
}

function luckypanel_bankdata_전송중단($luckypanel, $luckypanel_bankdata) {
    if ($luckypanel != 1 || $luckypanel_bankdata == '' || $luckypanel_bankdata === null) {
        return false;
    }
    return date('Y-m-d') > date('Y-m-d', strtotime($luckypanel_bankdata));
}
function 소괄호한개이후문자제거($string, $substring) {
    $position = strpos($string, $substring);
    if ($position !== false) {
        // 찾은 위치 이전까지의 문자열 반환
        return substr($string, 0, $position);
    }
    // 문자열에 지정된 부분이 없으면 원래 문자열 그대로 반환
    return $string;
}

// [Web발신]<새마을금고>900318**2 장영관 입금100 잔액100원 04/10 13:33
function 새마을금고_입금자명금액추출($sms) {
    $sms = trim($sms);
    if (preg_match('/(?:\[Web발신\])?\s*<새마을금고>\s*[\d\*]+\s+(.+?)\s+입금\s*([\d,]+)/u', $sms, $m)) {
        return array(
            'name' => trim($m[1]),
            'amount' => str_replace(',', '', $m[2]),
        );
    }
    return array('name' => null, 'amount' => null);
}

function site_bank_data_저장($data_idx, $member_no, $site, $acount, $name, $amount, $org_msg, $deposit_date = '') {
    global $conn;

    $site = mysqli_real_escape_string($conn, $site);
    $acount = mysqli_real_escape_string($conn, $acount);
    $name = mysqli_real_escape_string($conn, $name);
    $org_msg = mysqli_real_escape_string($conn, $org_msg);
    $amount = (int) $amount;
    $member_no = (int) $member_no;
    $data_idx = (int) $data_idx;

    if ($deposit_date === '') {
        $deposit_date = date('Y-m-d H:i:s');
    } else {
        $deposit_date = mysqli_real_escape_string($conn, $deposit_date);
    }

    $sql = "insert into site_bank_data set
      data_idx = {$data_idx},
      midx = {$member_no},
      site = '{$site}',
      acount = '{$acount}',
      name = '{$name}',
      amount = {$amount},
      org_msg = '{$org_msg}',
      deposit_date = '{$deposit_date}',
      regdate = now() ";
    db_query($sql);
}

