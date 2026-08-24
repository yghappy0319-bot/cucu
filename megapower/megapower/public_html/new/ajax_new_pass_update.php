<?php include_once "../lib/function.php";
include_once "../_chk.php";

if(!$_SESSION['new_pass']){
  die("다시 로그인 해 주세요.");
}

$sql = "update lr_member set ";
$_password = encryptPassword($new_password);
$sql.= "password = '{$_password}', ";
$sql.= "new_pass = 0 ";
$sql.= "where idx = {$_SESSION['new_pass']} and id = '{$member['id']}' ";
$result = db_query($sql);

if($result){
  $_SESSION['new_pass'] = 0;
  echo $result;
}
