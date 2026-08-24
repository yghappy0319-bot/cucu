<?
include_once "/home/luckybank/public_html/lib/sms_server_function.php";
include_once "../lib/authentication_number.php";
include_once "../_chk.php";

if($bank=="신한은행"){
  $_신한 = " sms like '%{$bank}%' and sms like '%인증%' ";
  $sql = "select * from bankdata where {$_신한} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'],"code" => 신한_인증번호($data['sms']));
  echo json_encode($sms);
}
if($bank=="기업은행"){
  $_기업 = " sms like '%{$bank}%' and sms like '%인증%' ";
  $sql = "select * from bankdata where {$_기업} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'], "code" => 기업_인증번호($data['sms']));
  echo json_encode($sms);
}
if($bank=="농협은행"){
  $_기업 = " sms like '%농협알림%' and sms like '%인증번호%' ";
  $sql = "select * from bankdata where {$_기업} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'], "code" => 농협_인증번호($data['sms']));
  echo json_encode($sms);
}
if($bank=="SC제일은행"){
  $_SC제일 = " sms like '%{$bank}%' and sms like '%인증번호%' ";
  $sql = "select * from bankdata where {$_SC제일} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'],"code" => SC제일_인증번호($data['sms']));
  echo json_encode($sms);
}
if($bank=="우리은행"){
  $_우리 = " sms like '%{$bank}%' and sms like '%인증번호%' ";
  $sql = "select * from bankdata where {$_우리} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'],"code" => 우리_인증번호($data['sms']));
  echo json_encode($sms);
}
if($bank=="수협"){
  $_수헙 = " sms like '%{$bank}%' and sms like '%인증번호%' ";
  $sql = "select * from bankdata where {$_수헙} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'],"code" => 수협_인증번호($data['sms']));
  echo json_encode($sms);
}
if($bank=="케이뱅크"){
  $_케이뱅크 = " sms like '%{$bank}%' and sms like '%인증번호%' ";
  $sql = "select * from bankdata where {$_케이뱅크} and status = 0 order by regdate desc limit 1  ";
  $data = sms_db_select($sql);
  $sms = array("idx" => $data['id'],"code" => 케이뱅크_인증번호($data['sms']));
  echo json_encode($sms);
}
