<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";

include_once $_SERVER['DOCUMENT_ROOT']."/lib/gmail_smtp_mail.php";

$sql = "select * from lr_member where email = '{$email_address}' and name = '{$user_name}' and phone = '{$sms_number}' ";
$data = db_select($sql);

if(!$data['idx']){
  $data = array("status" => 0, "msg" => "정보가 존재하지 않습니다.");
  echo json_encode($data);
  exit;
}

$오늘 = date("Y-m-d");
// 하루 5건만 가능
$send_cnt = db_select("select count(*) as cnt from lr_sms_log where phone = '{$data['email']}' and date_format(regdate,'%Y-%m-%d') = '{$오늘}' ");
if($send_cnt['cnt'] > 5){
  die('over');
}


$여섯자리 = 랜덤문자열(6);
//$내용 = '<b>'.$config['사이트명1'].' 아이디찾기 인증번호 : ['.$여섯자리.']</b>';

$내용 = "[메가파워월드]아이디 찾기 정보<br />";
$내용 .= "귀하의 아이디는 ".$data['id']." 입니다.<br />";
$내용 .= "비밀번호도 까먹으신 경우 비밀번호 찾기를 진행해 주시거나 이메일로 답변 바랍니다.";


로그_문자발송($data['idx'], 1, 1, $여섯자리, $data['email'], $내용, $아이피, $pages);
$result = MAIL_SEND($data['email'], $data['id'], "[메가파워월드]아이디 찾기 정보", $내용);
echo $result;


?>
