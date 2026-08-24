<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";
$여섯자리 = 랜덤문자열(4);

if($pages=="비밀번호찾기"){
  $send_text = "비밀번호 찾기";
}else{
  $send_text = "";
}

$요청페이지 = "회원가입인증";
if($pages=="member"){
  $sql = "select count(*) as cnt from lr_member where phone = '{$phone}'   ";
  $폰번호중복검사 = db_select($sql);
  if($폰번호중복검사['cnt']>0){
    die("요청하신 번호는 이미 가입된 회원이므로 아이디 찾기 또는 비밀번호 찾기를 이용해주세요.");
  }
}

if($pages=="아이디찾기"){
  $요청페이지 = $pages;
  $sql = "select * from lr_member where name = '{$name}' and phone = '{$phone}' ";
  //echo $sql;
  $phonecnt1 = db_select($sql);
  if(!$phonecnt1['idx']){
    die('가입된 정보가 없습니다.');
  }

  if($아이디찾기=="바로찾기"){
    $내용 = "[".$config['사이트명1']."] 귀하의 아이디는 {$phonecnt1['id']} 입니다.";
  }else{
    $내용 = $config['사이트명1']." {$send_text} 인증번호 [".$여섯자리."]";
  }


}

if($pages=="비밀번호찾기"){
  $요청페이지 = $pages;
  $phonecnt12 = db_select("select count(*) as cnt from lr_member where id = '{$id}' ");
  if(!$phonecnt12['cnt']){
    die('[아이디]가입된 정보가 없습니다.');
  }
  $phonecnt2 = db_select("select count(*) as cnt from lr_member where phone = '{$phone}' ");
  if(!$phonecnt2['cnt']){
    die('[연락처]가입된 정보가 없습니다.');
  }

  $내용 = $config['사이트명1']." {$send_text} 인증번호 [".$여섯자리."]";
}

$오늘 = date("Y-m-d");
// 하루 5건만 가능
$send_cnt = db_select("select count(*) as cnt from lr_sms_log where phone = '{$phone}' and date_format(regdate,'%Y-%m-%d') = '{$오늘}' ");
if($send_cnt['cnt'] > 5){
  die('over');
}



if($알리고문자발송){
$result = SMS_SEND($phone, $config['사이트명'], $내용);
//echo $result->result_code;
  로그_문자발송(0, 1, 1, $여섯자리, $phone, $내용, $아이피, $요청페이지);
  if($result){
    echo 1;
  }else{
    echo "error";
  }

}else{
  $result = 자동문자(0,$phone, '인증번호', '메가파워월드 비밀번호 찾기 인증번호', $내용, $아이피, $요청페이지);
  로그_문자발송(1, 1, $여섯자리,$phone,$내용);
  if($result){
    echo $result;
  }else{
    echo "error";
  }
}



?>
