<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";

$sql = "insert into lr_inquiry set ";
if($member['idx']>0){
  $sql.= "midx = {$member['idx']},";
}else{
  $sql.= "midx = 0,";
}
$sql.= "userid = '{$member['id']}', ";
$sql.= "name = '{$member['name']}', ";
$sql.= "status = 0,";
$sql.= "gubun = '{$gubun}',";
$sql.= "phone = '{$phone}',";
$sql.= "title = '{$title}',";
$sql.= "content = '{$content}',";
$sql.= "regdate = now() ";
$result = db_query($sql);
// $문의 = "1:1문의 : ".$content."\n";
//$문의.= "https://167.179.116.81/admin/manage/contact.html";
// if($알리고문자발송){
//   SMS_SEND('01022934444', "문의접수", $문의);
// }else{
//   자동문자(0, '01022934444','문의', '럭키볼-문의',$문의);
// }
echo $result;

$회원여부 = $member['idx'] ? $member['id']:"비회원";
$msg = "{$gubun} | {$회원여부}\n";
$msg.= "제목 : {$title}\n";
$msg.= "내용 : {$content}";
sendTelegramToMany($msg);
