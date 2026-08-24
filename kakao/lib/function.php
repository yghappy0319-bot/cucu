<?php
header('Content-Type: text/html; charset=UTF-8');
@session_start();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');

/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/
$conn = mysqli_connect("localhost","bank","Gkstlr59!","bank");
mysqli_query($conn, "set names utf8mb4");
//DB 접속

// function db_connect($db_host, $db_user, $db_pass, $db_name){
//     $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
//     mysqli_select_db($db_name) or die(mysql_error());
//     mysqli_query($conn, "set names utf8");
//     return $result;
// }


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


function 로그인정보($midx){
  $member_sql = "select * from member where mb_no = {$midx} ";
  $member = db_select($member_sql);
  return $member;
}

function 회원_휴대폰인증컬럼확보(){
  static $done = false;
  if ($done) {
    return;
  }
  $done = true;
  $col = db_select("SHOW COLUMNS FROM member LIKE 'mb_hp_cert'");
  if (!$col || empty($col['Field'])) {
    db_query("ALTER TABLE member ADD COLUMN mb_hp_cert TINYINT(1) NOT NULL DEFAULT 0 COMMENT '휴대폰인증여부' AFTER mb_hp");
  }
}

function 회원_휴대폰인증여부($member){
  if (!$member || empty($member['mb_no'])) {
    return false;
  }
  if (isset($member['mb_id']) && $member['mb_id'] === 'admin') {
    return true;
  }
  return (int)($member['mb_hp_cert'] ?? 0) === 1;
}

function 입금건기준_상태($obj){
    if($obj==1){
      return "<font color='blue'><b>성공</b></font>";
    }else if($obj==2){
      return "<font color='red' >실패</font>";
    }else if($obj==3){
        return "<font color='blueviolet'><b>수동</b></font>";
    }else if($obj==4){
      return "<font color='red' >삭제예정</font>";
    }else{
      return "<font>입금</font>";
    }
}
function 날짜지났는지체크($targetDate,$mb_no) {
    // 대상 날짜 문자열을 Unix 타임스탬프로 변환합니다.
    $targetTimestamp = strtotime($targetDate);

    // 현재의 Unix 타임스탬프를 얻습니다.
    $currentTimestamp = time();

    // 날짜가 이미 지났는지를 확인합니다.
    $hasPassed = $currentTimestamp > $targetTimestamp;

    // 결과를 출력합니다.
    return ($hasPassed ? $mb_no:'아직');
}

function 서비스종료기간($endDate) {
    // 종료 날짜를 설정합니다.
    $endDateTime = new DateTime($endDate);

    // 현재 날짜를 가져옵니다.
    $currentDateTime = new DateTime();

    // 종료 날짜가 현재 날짜 이전인지 확인합니다.
    if ($endDateTime < $currentDateTime) {
        return 9;
    }

    // 날짜 차이를 계산합니다.
    $interval = $currentDateTime->diff($endDateTime);

    // 남은 일수, 시간, 분, 초를 배열로 반환합니다.
    return [
        'days' => $interval->days,
        'hours' => $interval->h,
        'minutes' => $interval->i,
        'seconds' => $interval->s
    ];
}

function 문자_서비스_이용상태($obj,$idx){
  if($obj==1){
    return "<span class='btn btn-primary' >정상</span>";
  }else{
    return "<a href='javascript:go_status_update($idx)' class='btn btn-secondary' >중지</a>";
  }

}

function 회원사이트_서비스활성화($member_no) {
  $member_no = (int) $member_no;
  if ($member_no <= 0) {
    return false;
  }
  return db_query("update site set status = 1 where member_no = {$member_no}");
}

function 다이랙트샌드($수신번호,$제목,$내용){
    $ch = curl_init();

    $title = $제목;
    $message = $내용;             //필수입력
    $sender = "01022934444";                    //필수입력
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
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch,CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    $decoded = json_decode($response);
    if (is_object($decoded) && isset($decoded->status)) {
        return $decoded->status;
    }
    return $response;
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
  function 문자수신상태($obj){
      if($obj==1){
        return "<font color='blue'><b>성공</b></font>";
      }else if($obj==2){
        return "<font color='red' >실패</font>";
      }else if($obj==3){
          return "<font color='blueviolet'><b>수동</b></font>";
        }else if($obj==4){
          return "<font color='red' >삭제예정</font>";
      }else{
        return "입금 전";
      }
  }
  // 포인트 부여
  function insert_point($mb_id, $point, $content='', $rel_table='', $rel_id='', $rel_action='', $expire=0)
  {
      global $config;
      global $g5;
      global $is_admin;

      // 포인트 사용을 하지 않는다면 return
      if (!$config['cf_use_point']) { return 0; }

      // 포인트가 없다면 업데이트 할 필요 없음
      if ($point == 0) { return 0; }

      // 회원아이디가 없다면 업데이트 할 필요 없음
      if ($mb_id == '') { return 0; }
      $mb = db_select(" select mb_id from member where mb_id = '$mb_id' ");
      if (!$mb['mb_id']) { return 0; }

      // 회원포인트
      $mb_point = get_point_sum($mb_id);

      // 이미 등록된 내역이라면 건너뜀
      if ($rel_table || $rel_id || $rel_action)
      {
          $sql = " select count(*) as cnt from tb_point
                    where mb_id = '$mb_id'
                      and po_rel_table = '$rel_table'
                      and po_rel_id = '$rel_id'
                      and po_rel_action = '$rel_action' ";
          $row = db_select($sql);
          if ($row['cnt'])
              return -1;
      }

      // 포인트 건별 생성
      $po_expire_date = '9999-12-31';
      if($config['cf_point_term'] > 0) {
          if($expire > 0)
              $po_expire_date = date('Y-m-d', strtotime('+'.($expire - 1).' days', G5_SERVER_TIME));
          else
              $po_expire_date = date('Y-m-d', strtotime('+'.($config['cf_point_term'] - 1).' days', G5_SERVER_TIME));
      }

      $po_expired = 0;
      if($point < 0) {
          $po_expired = 1;
          $po_expire_date = G5_TIME_YMD;
      }
      $po_mb_point = $mb_point + $point;

      $sql = " insert into tb_point
                  set mb_id = '$mb_id',
                      po_datetime = '".G5_TIME_YMDHIS."',
                      po_content = '".addslashes($content)."',
                      po_point = '$point',
                      po_use_point = '0',
                      po_mb_point = '$po_mb_point',
                      po_expired = '$po_expired',
                      po_expire_date = '$po_expire_date',
                      po_rel_table = '$rel_table',
                      po_rel_id = '$rel_id',
                      po_rel_action = '$rel_action' ";
      db_query($sql);

      // 포인트를 사용한 경우 포인트 내역에 사용금액 기록
      if($point < 0) {
          insert_use_point($mb_id, $point);
      }

      // 포인트 UPDATE
      $sql = " update member set mb_point = '$po_mb_point' where mb_id = '$mb_id' ";
      db_query($sql);

      return 1;
  }

  // 사용포인트 입력
  function insert_use_point($mb_id, $point, $po_id='')
  {
      global $g5, $config;

      if($config['cf_point_term'])
          $sql_order = " order by po_expire_date asc, po_id asc ";
      else
          $sql_order = " order by po_id asc ";

      $point1 = abs($point);
      $sql = " select po_id, po_point, po_use_point
                  from tb_point
                  where mb_id = '$mb_id'
                    and po_id <> '$po_id'
                    and po_expired = '0'
                    and po_point > po_use_point
                  $sql_order ";
      $result = db_query($sql);
      for($i=0; $row=db_fetch($result); $i++) {
          $point2 = $row['po_point'];
          $point3 = $row['po_use_point'];

          if(($point2 - $point3) > $point1) {
              $sql = " update tb_point
                          set po_use_point = po_use_point + '$point1'
                          where po_id = '{$row['po_id']}' ";
              db_query($sql);
              break;
          } else {
              $point4 = $point2 - $point3;
              $sql = " update tb_point
                          set po_use_point = po_use_point + '$point4',
                              po_expired = '100'
                          where po_id = '{$row['po_id']}' ";
              db_query($sql);
              $point1 -= $point4;
          }
      }
  }


  function 결과($status,$msg, $etc=""){
    if($etc){
      $data = $etc;
    }else{
      $data = array("status" => $status, "msg" => $msg);
    }

    return json_encode($data);
  }

  function 무통장서비스_통보가능은행(){
    $result = db_query("select * from g5_sms_bank ");
    return $result;
  }

  function 날짜비교($targetDate) {
    if(!$targetDate){
      $targetDate = date("Y-m-d");
    }

    //서비스 날짜가 지났는지 확인하는 함수
      $targetDateTime = new DateTime($targetDate);
      $currentDateTime = new DateTime();
      if ($targetDateTime < $currentDateTime) {
          return 1;
      }
      return $targetDate;
  }

  function 주문로그($gubun,$midx,$제목,$가격=0,$status=0){
    $log_sql = "insert into tb_orderlist set ";
    $log_sql.= "gubun = '{$gubun}', ";
    $log_sql.= "midx = {$midx}, ";
    $log_sql.= "subject = '{$제목}', ";
    $log_sql.= "amount = {$가격}, ";
    $log_sql.= "status = {$status},";
    $log_sql.= "regdate = now() ";
    db_query($log_sql);
  }

  function 자동결제_pay_value($설정, $amt){
    if (!empty($설정['idx'])) {
      return $설정['gubun'].'/'.$설정['service_day'].'/'.$amt.'/'.$설정['service_sms'].'/'.$설정['idx'].'/card';
    }
    return '자동결제/30/'.$amt.'/card';
  }

  function 자동결제_서비스기간_memo($start_date = null, $days = 30){
    $start = $start_date ? date('Y-m-d', strtotime($start_date)) : date('Y-m-d');
    $end = date('Y-m-d', strtotime($start.' +'.((int) $days - 1).' days'));
    return $start.' ~ '.$end;
  }

  function 자동결제_pay_log($midx, $userid, $amt, $goodsname, $pay_value, $moid, $start_date = null){
    global $conn;
    $midx = (int) $midx;
    $amt = (float) $amt;
    $userid_esc = mysqli_real_escape_string($conn, (string) $userid);
    $goodsname_esc = mysqli_real_escape_string($conn, (string) $goodsname);
    $pay_value_esc = mysqli_real_escape_string($conn, (string) $pay_value);
    $moid_esc = mysqli_real_escape_string($conn, (string) $moid);
    $memo_esc = mysqli_real_escape_string($conn, 자동결제_서비스기간_memo($start_date));

    $dup = db_select("select idx from tb_pay_log where moid = '{$moid_esc}' limit 1");
    if ($dup['idx'] > 0) {
      return false;
    }

    $sql = "insert into tb_pay_log set ";
    $sql.= "pk_pay = 0, ";
    $sql.= "midx = {$midx}, ";
    $sql.= "userid = '{$userid_esc}', ";
    $sql.= "amt = {$amt}, ";
    $sql.= "goodsname = '{$goodsname_esc}', ";
    $sql.= "pay_value = '{$pay_value_esc}', ";
    $sql.= "memo = '{$memo_esc}', ";
    $sql.= "moid = '{$moid_esc}', ";
    $sql.= "status = 1, ";
    $sql.= "regdate = now() ";
    return db_query($sql);
  }

  function 자동결제_청구일_맞추기($mb_no, $서비스일){
    $mb_no = (int) $mb_no;
    $day_ts = strtotime((string) $서비스일);
    if ($mb_no < 1 || $day_ts === false) {
      return false;
    }

    $bill = db_select("select idx, enddate from tb_auto_bill where mb_no = {$mb_no} order by idx desc limit 1");
    if (!$bill || empty($bill['idx'])) {
      return false;
    }

    $time_part = '00:00:00';
    if (!empty($bill['enddate'])) {
      $old_ts = strtotime($bill['enddate']);
      if ($old_ts !== false) {
        $time_part = date('H:i:s', $old_ts);
      }
    }

    global $conn;
    $new_end_esc = mysqli_real_escape_string($conn, date('Y-m-d', $day_ts).' '.$time_part);
    return db_query("update tb_auto_bill set enddate = '{$new_end_esc}' where mb_no = {$mb_no}");
  }

  function 수동연장_기준일($start_date = null){
    if ($start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $start_date))) {
      return date('Y-m-d', strtotime(trim((string) $start_date)));
    }
    return date('Y-m-d');
  }

  function 수동연장_pay_log($midx, $userid, $days = 30, $amt = 0, $start_date = null){
    global $conn;
    $midx = (int) $midx;
    $days = (int) $days;
    $amt = (float) $amt;
    if ($days === 0) {
      return false;
    }

    $start = 수동연장_기준일($start_date);
    $sign_label = ($days > 0 ? '+' : '') . $days;
    if ($amt > 0) {
      $goods_label = '서비스 수동 연장 (' . $sign_label . '일)';
    } else if ($days > 0) {
      $goods_label = '관리자 무료 연장 (' . $sign_label . '일)';
    } else {
      $goods_label = '관리자 기간 조정 (' . $sign_label . '일)';
    }
    if ($days > 0) {
      $memo = 자동결제_서비스기간_memo($start, $days);
    } else {
      $memo = $start . ' → ' . date('Y-m-d', strtotime($start . ' ' . $days . ' days'));
    }

    $userid_esc = mysqli_real_escape_string($conn, (string) $userid);
    $goodsname_esc = mysqli_real_escape_string($conn, $goods_label);
    $pay_value_esc = mysqli_real_escape_string($conn, '수동연장/'.$days.'/'.$amt.'/0/0/manual');
    $memo_esc = mysqli_real_escape_string($conn, $memo);
    $moid_esc = mysqli_real_escape_string($conn, 'manual_'.$midx.'_'.date('YmdHis'));

    $sql = "insert into tb_pay_log set ";
    $sql.= "pk_pay = 0, ";
    $sql.= "midx = {$midx}, ";
    $sql.= "userid = '{$userid_esc}', ";
    $sql.= "amt = {$amt}, ";
    $sql.= "goodsname = '{$goodsname_esc}', ";
    $sql.= "pay_value = '{$pay_value_esc}', ";
    $sql.= "memo = '{$memo_esc}', ";
    $sql.= "moid = '{$moid_esc}', ";
    $sql.= "status = 1, ";
    $sql.= "regdate = now() ";
    return db_query($sql);
  }

  function 패널수동연장_pay_log($midx, $userid, $panel_idx, $panel_name_ko, $panel_name_en, $day_delta, $base_ts, $new_end_ts){
    global $conn;
    $midx = (int) $midx;
    $panel_idx = (int) $panel_idx;
    $day_delta = (int) $day_delta;
    if ($panel_idx <= 0 || $day_delta === 0) {
      return false;
    }

    $sign_label = ($day_delta >= 0 ? '+' : '') . $day_delta;
    $goods_label = '패널 서비스 수동 연장 (' . $sign_label . '일) (#' . $panel_idx . ' ' . $panel_name_ko . ' / ' . $panel_name_en . ')';
    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($goods_label, 'UTF-8') > 480) {
      $goods_label = mb_substr($goods_label, 0, 480, 'UTF-8');
    }

    if ($day_delta > 0) {
      $memo = 자동결제_서비스기간_memo(date('Y-m-d', $base_ts), $day_delta);
    } else {
      $memo = date('Y-m-d', $base_ts) . ' → ' . date('Y-m-d', $new_end_ts);
    }

    $userid_esc = mysqli_real_escape_string($conn, (string) $userid);
    $goodsname_esc = mysqli_real_escape_string($conn, $goods_label);
    $pay_value_esc = mysqli_real_escape_string($conn, '수동연장/panel/' . $panel_idx . '/' . $day_delta . '/0/0/manual');
    $memo_esc = mysqli_real_escape_string($conn, $memo);
    $moid_esc = mysqli_real_escape_string($conn, 'panel_manual_' . $panel_idx . '_' . $midx . '_' . date('YmdHis'));

    $sql = "insert into tb_pay_log set ";
    $sql.= "pk_pay = 0, ";
    $sql.= "midx = {$midx}, ";
    $sql.= "userid = '{$userid_esc}', ";
    $sql.= "amt = 0, ";
    $sql.= "goodsname = '{$goodsname_esc}', ";
    $sql.= "pay_value = '{$pay_value_esc}', ";
    $sql.= "memo = '{$memo_esc}', ";
    $sql.= "moid = '{$moid_esc}', ";
    $sql.= "status = 1, ";
    $sql.= "regdate = now() ";
    return db_query($sql);
  }

  function 접근권한($level){
    if($level<10){
      echo "접근권한이 없습니다.";
      exit;
    }
    return true;
  }

  function 날짜표기($오늘, $신청일자){
    $_신청일자 = date("Y-m-d",strtotime($신청일자));
    if($오늘==$_신청일자){
      $표기 = date("Y-m-d H:i",strtotime($신청일자));
    }else{
      $표기 = date("y-m-d",strtotime($신청일자));
    }
    return $표기;
  }

  function 랜덤코드_영문대소문자($length = 15) {
      $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $charactersLength = strlen($characters);
      $randomCode = '';

      for ($i = 0; $i < $length; $i++) {
          $randomCode .= $characters[rand(0, $charactersLength - 1)];
      }

      return $randomCode;
  }

  function 결제폼사용여부($obj){
    if($obj==1){
      return "<span class='btn btn-primary' >사용중</span>";
    }else{
      return "<span class='btn btn-secondary' >미사용</span>";
    }
  }

function 자동결제($data){
  $ch = curl_init();
  $url = 'https://api.innopay.co.kr/api/payAutoCardBill';

  $data2 = array(
      "amt" => $data['amt'],
      "arsConnType" => "02",
      "billKey" => $data['billKey'],
      "buyerHp" => $data['buyerHp'],
      "buyerName" => $data['buyerName'],
      "goodsName" => $data['goodsName'],
      "mid" => $data['mid'],
      "moid" => $data['moid'],
      "payExpDate" => $data['payExpDate'],
      "userId" => $data['userId'],
      "buyerEmail" => $data['buyerEmail'],
      "cardQuota" => $data['cardQuota']
  );

  // 데이터 배열을 JSON 형식으로 인코딩
  $jsonData = json_encode($data2);

  // cURL 옵션 설정
  curl_setopt($ch, CURLOPT_URL, $url); // 요청할 URL
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
  curl_setopt($ch, CURLOPT_POST, true); // POST 방식 요청
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // POST 데이터
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json', // Content-Type 헤더 설정
      'Content-Length: ' . strlen($jsonData) // JSON 데이터의 길이 설정
  ));
  // API 요청 실행 및 응답 받기
  $response = curl_exec($ch);
  return $response;
}
