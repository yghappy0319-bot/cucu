<?
include_once "../lib/function.php";
include_once "../_chk.php";

$sql = "select * from lr_new_pass where code = '{$code}' ";
$data = db_select($sql);
if($data['status']==1){
  $data = array("status" => 0, "msg" => "이미 변경된 코드 입니다.");
  echo json_encode($data);
  exit;
}

$sql = "update lr_member set ";
$_password = encryptPassword($newpass);
$sql.= "password = '{$_password}' ";
$sql.= "where idx = {$data['midx']} ";
$result = db_query($sql);
if($result){
  db_query("update lr_new_pass set status = 1 where code = '{$code}' ");
  echo $result;
}
