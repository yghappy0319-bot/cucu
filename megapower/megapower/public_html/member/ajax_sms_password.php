<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";

$sql = "insert into cron_log set status = '{$_SERVER['REMOTE_ADDR']}', regdate = now() ";
db_query($sql);

$phonecnt1 = db_select("select count(*) as cnt from lr_member where id = '{$id}' ");
if(!$phonecnt1['cnt']){
  die('[아이디]가입된 정보가 없습니다.');
}

$phonecnt2 = db_select("select count(*) as cnt from lr_member where phone = '{$phone}' ");
if(!$phonecnt2['cnt']){
  die('[휴대폰번호]가입된 정보가 없습니다.');
}

$오늘 = date("Y-m-d");
// 하루 5건만 가능
$send_cnt = db_select("select count(*) as cnt from lr_sms_log where phone = '{$phone}' and date_format(regdate,'%Y-%m-%d') = '{$오늘}' ");
if($send_cnt['cnt'] > 5){
  die('over');
}

$query = "select * from lr_member where id = '{$id}' and phone = '{$phone}' ";
$info = db_select($query);

if($info['idx']){
  $여섯자리 = 랜덤문자열(6);
  $_password = encryptPassword($여섯자리);
  $_upquery = "update lr_member set password = '{$_password}', new_pass_date = now(), new_pass = 1 where idx = {$info['idx']} and id = '{$id}' ";
  $_status = db_query($_upquery);

  $내용 = $config['사이트명1']." 임시비밀번호 [".$여섯자리."]\n ".$config['웹사이트주소'];
  if($알리고문자발송){
    $result = SMS_SEND($phone, $제목=$config['사이트명1']." (".$config['웹사이트주소'].")",$내용);
  }else{
      $result = 자동문자(0,$phone, '인증번호', "{$config['사이트명1']} 임시비밀번호", $내용, '임시비밀번호발급');
  }
  $아이피 = $_SERVER['REMOTE_ADDR'];
  로그_문자발송(1, 1, $여섯자리,$phone, $내용, $아이피, '임시비밀번호발급');

  if($_status){
    echo $_status;
  }else{
    echo "error";
  }
}
