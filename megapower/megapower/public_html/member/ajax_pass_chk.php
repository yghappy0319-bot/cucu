<?php include_once "../lib/function.php";
include_once "../_chk.php";

$sql = "select * from lr_member where idx = {$member['idx']} ";
$user = db_select($sql);

if(password_verify($password, $user['password'])){
  //로그인 성공
  $_SESSION['info_pass_chk'] = 1;
  echo 1;
}else{
  echo 0;
}
