<?php include_once "../lib/function.php";
include_once "../_chk.php";

$_password = encryptPassword($password);
$birthday = $birth_yy."-".$birth_mm."-".$birth_dd;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "email_vali_error";
    exit;
}

$이메일체크 = db_select("select count(*) as cnt from lr_member where email = '{$email}' and idx != {$member['idx']} ");
if($이메일체크['cnt']>0){
  echo "email_chk";
  exit;
}


$phone_sql = " select count(*) as cnt from lr_member where phone != '{$my_phone_number}' and phone = '{$phone}' ";
$연락처체크 = db_select($phone_sql);
if($연락처체크['cnt']>0){
  echo "phone_chk";
  exit;
}

$send_sms = $send_sms ? $send_sms:0;

$sql = "update lr_member set ";
if($password){
  $sql.= "password = '{$_password}', ";
}
$sql.= "email = '{$email}', ";
$sql.= "name = '{$name}', ";
$sql.= "birthday = '{$birthday}',";
$sql.= "phone = '{$phone}', ";
$sql.= "acount = '{$acount}', ";
$sql.= "acount_name = '{$acount_name}', ";
$sql.= "acount_number = '{$acount_number}', ";
$sql.= "send_sms = {$send_sms} ";
$sql.= "where idx = {$member['idx']} ";
$result = db_query($sql);
if($result){
  echo "ok";
}else{
  echo "no";
}
