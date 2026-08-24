<?php
header('Content-Type: text/html; charset=UTF-8');
@session_start();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');


/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$conn = mysqli_connect("15.165.37.71","root","tnfhzh09*&^%","super");
mysqli_query($conn, "set names utf8mb4");
//DB 접속
/*
function db_connect($db_host, $db_user, $db_pass, $db_name){
    $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
    mysqli_select_db($db_name) or die(mysql_error());
    mysqli_query($conn, "set names utf8");
    return $result;
}
*/

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


/*  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 개발자 정의 함수                                                                                                                                       ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/

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
function move_msg_and_page($msg, $url=""){
    if($msg){
        echo "<script type='text/javascript'>alert('$msg');</script>";
    }

    $url="document.location.replace('$url')";
    echo "<script type='text/javascript'>$url;</script>";
    exit;
}
function 사이트설정(){
    $config = db_select("select * from lr_config");
    return $config;
}
function 매니저($midx){
    $member = db_select("select * from lr_staff where idx = {$midx} ");
    return $member;
}
function 회원($midx){
    $member = db_select("select * from lr_member where idx = {$midx} ");
    return $member;
}

function SMS_SEND($받는사람, $제목="안녕하세요",$내용){
    $data = 다이랙트샌드($받는사람, $제목, $내용);
    return print_r($data);
}

function SMS_SEND_알리고($받는사람, $제목="안녕하세요",$내용){
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

    $결과 = json_decode($ret);


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

function 알리고수신거부번호(){
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://apis.aligo.in/refuse_list?key=6732y15obrxyyktvpwjlrip5vsuijxmz&user_id=yghappy',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
  "notification" : {
          "title" : "TEST",
          "body" : "알림테스트"
      },
      "to" : "ceEwmseY_w0:APA91bFDZR2mFkqQp-D1aY1skaMXTDO_DTqm4tJ7Nssq9Xt0xn_g_p3EJaXMwXwbj-ZDKkqgUOrQZJW9M6EbyaB0RHzMa7XI_QHF4qHljfJRu-yUOkwyjNVW6piDEhssLoVAvweLBNyi"
  }',
        CURLOPT_HTTPHEADER => array(
            'Authorization: key=AAAAIK11WQU:APA91bEt6XdJdxl-PESTbi18IErIaE4nYDuOLizdRgiJ2q_nJRFR99QzSyJGJnXlF1ePKSUhkYnzcbJZBWgToaCr5kYqVcN3--_oj_XD3gGG0_Tf8uiYM0Zhvm3uNAGqMxUJzJKuYV66',
            'Content-Type: application/json',
            'p256dh: BNwmt4T00WYav8jAZp2eOk3j_B5tMBnqtWnsQtJfOMfiBZg111t_WJIqmtN7yfZnfnHPDe9IbgKvRe8YfXclXtY',
            'Auth: 3I4-O1efELE3JPz8dDXbew',
            ': '
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $결과 = json_decode($response);
    return $결과;
}

function encryptPassword($password) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    return $hashedPassword;
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

function 포인트신청리스트($midx,$where=""){
    if($midx){
        $w_midx = " and midx = {$midx}  ";
    }
    $sql = " select a.* , b.id, b.name as username, b.ads
            from lr_deposit_log a left join lr_member b
            on a.midx = b.idx
            where 1=1 and a.deposit_type = 'bank' {$w_midx} {$where} ";
    //echo $sql;
    $data = db_query($sql);
    return $data;
}

function 쿠폰리스트($order){
    $deposit_sql = "select * from lr_coupon where 1=1 {$order} ";
    $data = db_query($deposit_sql);
    return $data;
}

function 당첨금출금신청리스트($order){
    $withdraw_sql = "select * from lr_prize_log where 1=1 {$order} ";
    $data = db_query($withdraw_sql);
    return $data;
}

function 관리자정보($admin){
    $sql = "select * from lr_staff where idx = '{$admin}' ";
    $_admin = db_select($sql);
    return $_admin;
}
function 입금상태($obj){
    if($obj==1){
        return "처리중";
    }else if($obj==2){
        return "<font color='red'>충전완료</font>";
    }else if($obj==4){
        return "처리중(부족)";
    }else if($obj==5){
        return "<font color='blue'>취소</font>";
    }else if($obj==6){
        return "<font color='blue'>출금완료</font>";
    }
}
function 에러로그(){
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
}
function 게임명($game){
    if($game=="pb"){
        return "<font color='#CC1F3B'>파워볼</font>";
    }else if($game=="mm"){
        return "<font color='#194A9C'>메가밀리언</font>";
    }
}

function 환율(){
    $환율 = db_select("select * from lr_exchange order by date desc limit 1");
    return intval($환율['amount']);
}
function 로그인정보($midx){
    $member_sql = "select * from lr_member where idx = {$midx} ";
    $member = db_select($member_sql);
    return $member;
}


function 미국서머타임() {
    $currentYear = date('Y'); // 현재 년도 가져오기
    $currentDate = date('Y-m-d'); // 현재 날짜 가져오기
    $marchSecondWeekStart = date($currentYear . '-03-08');
    $marchSecondWeekEnd = date($currentYear . '-03-14');
    $novemberFirstWeekStart = date($currentYear . '-11-01');
    $novemberFirstWeekEnd = date($currentYear . '-11-04');

    if ($currentDate >= $marchSecondWeekStart && $currentDate <= $novemberFirstWeekEnd) {
        $_data = array("추첨시간" => 12, "주문마감시간" => "02");
        return $_data;
    } else {
        $_data = array("추첨시간" => 1, "주문마감시간" => "03");
        return $_data;
    }
}

function 매칭여부($macth){
    if($macth==1){
        return "<font color='red'>완료</font>";
    }else{
        return "<font color='blue'>대기</font>";
    }
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



//$_UPLOAD_DIR = $_SERVER['DOCUMENT_ROOT']."/data/file/hospital/";
//$파일네임1 = uploadFile($_FILES, "wr_19", $_POST[wr_19], $_UPLOAD_DIR,"");

function uploadFile($HTTP_POST_FILES, $el_name, $_P_DIR_FILE){

    if ($HTTP_POST_FILES[$el_name]['size']>0){
        ########## 등록한 파일이 업로드가 허용되지 않는 확장자를 갖는 파일인지를 검사한다. ##########

        $file_name = strtolower($HTTP_POST_FILES[$el_name]['name']);
        $full_filename = explode(".", "$file_name");
        $extension = $full_filename[sizeof($full_filename)-1];

        $file_name = time().".".$extension;

        if(!strcmp($extension,"html") || !strcmp($extension,"htm") ||
            !strcmp($extension,"php") || !strcmp($extension,"php3") ||
            !strcmp($extension,"php4") || !strcmp($extension, "inc")){
            exit;
        }
        ########## 지정디렉토리가 없을경우 생성  ##########
        if (!is_dir($_P_DIR_FILE)){
            $base_name = $_P_DIR_FILE;
            if (!is_dir($base_name)) mkdir($base_name);
        }

        ########## 등록하려는 파일을 현재 자료실의 지정디렉토리에 저장 ##########
        if(!copy($HTTP_POST_FILES[$el_name]['tmp_name'],$_P_DIR_FILE.$file_name)) {
            exit;
        }
        return $file_name;
    }
    return "";
}

function 통합_처리($cash_config_idx,$누적캐시, $충전금,$고유아이디){
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

    $_추가포인트 = db_select("select * from lr_cash_config where idx = {$cash_config_idx} ");
    if($_추가포인트['add_point']>0){ //추가 럭키포인트
        db_query("update lr_member set lucky_point = lucky_point + {$_추가포인트['add_point']} where idx = {$고유아이디} ");
        포인트로그($고유아이디, '지급', "미로코포인트", "캐시충전 보너스", 1, $_추가포인트['add_point']);
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

function 포인트로그($midx, $gubun, $name="포인트", $etc, $보유포인트, $지급할포인트){
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


function 로그_문자발송($midx,$실패여부,$결과,$여섯자리,$수신자번호,$내용,$아이피="",$페이지=""){
    $midx = $midx ? $midx:0;

    $sql = "insert into lr_sms_log set ";
    $sql.= "midx = {$midx}, ";
    $mem = db_select("select id from lr_member where idx = {$midx} ");
    $sql.= "userid = '{$mem['id']}', ";
    $sql.= "status = {$실패여부}, ";
    $sql.= "send_status = '{$결과}', ";
    $sql.= "page = '{$페이지}', ";
    $sql.= "code = '{$여섯자리}', ";
    $sql.= "phone = '{$수신자번호}', ";
    $sql.= "msg = '{$내용}', ";
    $sql.= "ip = '{$아이피}', ";
    $sql.= "regdate = now() ";
    $result = db_query($sql);
    return $result;
}

function 당첨금원화단위변경($number) {
    if ($number >= 1000000000000) {
        // 1조 (조 억원)
        $trillions = floor($number / 1000000000000);
        $billions = floor(($number - ($trillions * 1000000000000)) / 100000000);
        return $trillions . "조" . ($billions > 0 ? number_format($billions) . "" : "") . "억원";
    } elseif ($number >= 100000000) {
        // 1억 (억원)
        $billions = floor($number / 100000000);
        return number_format($billions) . "억원";
    } else {
        return number_format($number) . "원";
    }
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

function 추첨결과_전체문자($게임명, $게임, $회차){

    $_결과sql = "select * from lr_result where game = '{$게임}' and round = {$회차} and status = 0 ";
    $_결과 = db_select($_결과sql);

    $_당첨금 = $_결과['money1'] * 환율();
    $_다음당첨금 = $_결과['prize'] * 환율();

    $당첨금 = 당첨금원화단위변경($_당첨금);
    $다음당첨금 = 당첨금원화단위변경($_다음당첨금);
    if($_결과['prize']!=20000000){
        $_이월 = "[이월]";
    }else{
        $_이월 = "";
    }
    $kr_mega_result = $_결과['ko_date'];
    if($게임명=="메가밀리언"){
        $_다음추첨일 = 메가밀리언_결과일($kr_mega_result);
    }else{
        $_다음추첨일 = 파워볼_결과일($kr_mega_result);
    }

    $요일 = 요일($_다음추첨일);

    $제목 = $당첨금." {$게임명} 추첨결과 안내!";

    $내용 = "미국복권구매대행 럭키볼 입니다.\n";
    $내용.= $_이월.$_결과['round']."회차 {$게임명}\n";
    $내용.= "당첨금 : {$당첨금}\n";
    $내용.= "추첨번호\n{$_결과['ball1']} {$_결과['ball2']} {$_결과['ball3']} {$_결과['ball4']} {$_결과['ball5']} + {$_결과['ball6']}\n\n";

    $내용.= "★★주문가능★★\n";
    $내용.= "다음 추첨일\n{$_다음추첨일}/{$요일}\n";
    $내용.= "당첨금 {$다음당첨금}\n\n";

    $내용.= "-구매 초보를 위한 구매가이드\n";
    $내용.= "https://luckyballkr.com/buy/guide.html\n\n";

    $내용.= "-{$게임명} 구매하기\n";
    if($게임명=="메가밀리언"){
        $내용.= "https://luckyballkr.com/buy/megamillion.html\n\n";
    }else{
        $내용.= "https://luckyballkr.com/buy/power_ball.html\n\n";
    }

    $내용.= "-{$게임명} 당첨기준 안내\n";

    if($게임명=="메가밀리언"){
        $내용.= "https://luckyballkr.com/buy/mega_ball_service.html\n\n";
    }else{
        $내용.= "https://luckyballkr.com/buy/power_ball_service.html\n\n";
    }

    $내용.= "자세한 당첨현황은 럭키볼 웹사이트에 로그인하여 확인해주세요!\n";
    $내용.= "https://luckyballkr.com\n\n";
    $내용.= "감사합니다!\n";
    $수신거부번호 = 알리고수신거부번호();
    $내용.= "무료수신거부($수신거부번호->default_free_number)";

    //전체문자발송
    $sql = "select * from lr_member where phone != '' and cut_off = 0 ";
    $result = db_query($sql);
    $phoneArray = array(); // Initialize the array to store phone numbers
    $i = 0;
    while ($row = db_fetch($result)) {
        $phoneArray[$i][] = $row['phone'];

        // Check if 5 elements are stored in the current sub-array
        if (count($phoneArray[$i]) == 10) {
            $i++; // Move to the next sub-array
        }
    }
    $combinedNumbersArray = array();
    foreach ($phoneArray as $subArray) {
        $combinedNumbersArray[] = implode(',', $subArray);
    }
    // Print the result
    for($a=0;$a<count($combinedNumbersArray);$a++){
        $result = SMS_SEND($combinedNumbersArray[$a], $제목, $내용);
    }
    return $result->result_code;
}

function 무통장_입금상태($status){

    if($status==1){
        return "정상";
    }else if($status==2){
        return "";
    }else if($status==4){
        return "선입금-부족";
    }else if($status==5){
        return "선입금-많음";
    }else{
        return "대기";
    }
}

function 자동문자($상태=0,$phone,$구분,$제목,$내용){
    // $내용2 = "[Web발신]\n";
    $내용2.= $내용;

    $sql = "insert into lr_sms_send_log set ";
    $sql.= "status = 0, ";
    $sql.= "phone = '{$phone}', ";
    $sql.= "gubun = '{$구분}', ";
    $sql.= "title = '{$제목}', ";
    $sql.= "content = '{$내용2}', ";
    $sql.= "regdate = now() ";
    $result = db_query($sql);
    return $result;
}
function 나의이용내역_지급사용색상($gubun, $point, $etc){
    if($gubun=="지급"){
        return "<font color='blue'>".$point.$etc."</font>";
    }else if($gubun=="지급완료"){
        return "<font color='blue'>".$point.$etc."</font>";
    }else if($gubun=="출금"){
        return "<font color='black' >".$point.$etc."</font>";
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

function 서머타임체크($timezone = 'America/Los_Angeles')
{
    $date = new DateTime('now', new DateTimeZone($timezone));

    // 1 = DST, 0 = 표준시
    if ($date->format('I')) {
        return '0900';  // 서머타임 기간
    } else {
        return '1000';  // 표준시 기간
    }
}

function 열한시열한시오십구_새벽4시실행() {
    // 분: 1~59 사이에서 랜덤
    $minute = rand(1, 59);

    // 시: 항상 11시
    $hour = 11;

    // 시간 문자열 포맷 (예: 11:07)
    return sprintf('%02d:%02d', $hour, $minute);
}

function 십팔시십분_십팔시오십구분_11시실행($서머타임시간) {
    // 분: 1~59 사이에서 랜덤
    $minute = rand(10, 59);

    // 시: 항상 17시 / 오후5시
    //$hour = 17;
    if($서머타임시간=="0900"){
        $hour = 17;
    }else{
        $hour = 18;
    }

    // 시간 문자열 포맷 (예: 17:07)
    return sprintf('%02d:%02d', $hour, $minute);
}

