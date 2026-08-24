<?php include_once "../lib/function.php";
include_once "../_chk.php";

if(!$member['idx']){
  $_data = array("status" => "session_null");
  die(json_encode($_data));
}
$오늘 = date("Y-m-d");
$sql = "select * from lr_coupon where idx = {$cidx}";
$_coupon = db_select($sql);

if($_coupon['downdate1'] < $오늘){ //다운로드 가능 기간 체크
  $_data = array("status" => "다운로드 기간이 만료되었습니다");
  die(json_encode($_data));
}

if($_coupon['new_member_yn']==0){ // 진짜 신규회원만 가능
  $sql = "select count(*) as cnt from lr_coupon_log where midx = {$member['idx']} and new_member_yn = 0 "; //신규쿠폰 발행내역이 있으면..
  $chk = db_select($sql);
  if($chk['cnt']>0){ //신규로 받은 쿠폰 내역이 있는경우
    $_data = array("status" => "ok", "msg" => "신규회원이 아닙니다.");
    die(json_encode($_data));
  }
}


if($_coupon['gubun']=="할인쿠폰"){ //할인쿠폰 발급시
  $sql = "select count(*) as cnt from lr_coupon_log where midx = {$member['idx']} and cidx = {$_coupon['idx']} and gubun = '할인쿠폰' ";
  $_coupon_chk = db_select($sql);
  if($_coupon_chk['cnt']==0){

    $sql = "insert into lr_coupon_log set ";
    $sql.= "status = 0,";
    $sql.= "new_member_yn = {$_coupon['new_member_yn']},";
    $sql.= "gubun = '{$_coupon['gubun']}', ";
    $sql.= "cidx = {$_coupon['idx']},";
    $sql.= "midx = {$member['idx']},";
    $sql.= "name = '{$_coupon['name']}',";
    $sql.= "discount = {$_coupon['discount']},";
    $sql.= "usedate1 = '{$_coupon['usedate1']} 23:59:59',";
    $sql.= "regdate = now() ";
    $result = db_query($sql);

    //아래는 로그용
    $sql1 = "insert into lr_coupon_log_list set ";
    $sql1.= "status = 0,";
    $sql1.= "gubun = '{$_coupon['gubun']}', ";
    $sql1.= "cidx = {$_coupon['idx']},";
    $sql1.= "midx = {$member['idx']},";
    $sql1.= "name = '{$_coupon['name']}',";
    $sql1.= "discount = {$_coupon['discount']},";
    $sql1.= "usedate1 = '{$_coupon['usedate1']} 23:59:59',";
    $sql1.= "regdate = now() ";
    db_query($sql1);

    if($result){
      $_data = array("status" => "ok", "msg" => "쿠폰이 발급 되었습니다.");
      die(json_encode($_data));
    }
  }else{
    $_data = array("status" => "no");
    die(json_encode($_data));
  }
}else{
  $sql = "select count(*) as cnt from lr_coupon_log where midx = {$member['idx']} and cidx = {$_coupon['idx']} and gubun = '포인트' ";
  $_coupon_chk = db_select($sql);
  if($_coupon_chk['cnt']==0){

    $sql = "insert into lr_coupon_log set ";
    $sql.= "status = 1,";
    $sql.= "new_member_yn = {$_coupon['new_member_yn']}, ";
    $sql.= "gubun = '{$_coupon['gubun']}', ";
    $sql.= "cidx = {$_coupon['idx']},";
    $sql.= "midx = {$member['idx']},";
    $sql.= "name = '{$_coupon['name']}',";
    $sql.= "discount = {$_coupon['discount']},";
    $sql.= "usedate1 = '{$_coupon['usedate1']} 23:59:59',";
    $sql.= "regdate = now() ";
    $result = db_query($sql);

    //아래는 그냥 기록용입니다
    $sql1 = "insert into lr_coupon_log_list set ";
    $sql1.= "status = 1,";
    $sql1.= "gubun = '{$_coupon['gubun']}', ";
    $sql1.= "cidx = {$_coupon['idx']},";
    $sql1.= "midx = {$member['idx']},";
    $sql1.= "name = '{$_coupon['name']}',";
    $sql1.= "discount = {$_coupon['discount']},";
    $sql1.= "usedate1 = '{$_coupon['usedate1']} 23:59:59',";
    $sql1.= "regdate = now() ";
    db_query($sql1);

    if($result){
      $log = array(
        "midx" => $member['idx'],
        "grade" => $member['grade'],
        "buy_idx" => 0,
        "gubun" => '지급',
        "name" => '메가파워월드포인트',
        "etc" => "신규회원 쿠폰발급",
        "status" => 1,
        "point" => $_coupon['discount']
      );
      게임구매_럭키포인트_지급로그($log);

      $_data = array("status" => "ok", "msg" => "메가파워월드포인트가 지급 되었습니다.");
      die(json_encode($_data));

    }
  }else{
    $_data = array("status" => "no");
    die(json_encode($_data));
  }
}
