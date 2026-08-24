<?php
include_once "../lib/function.php";
include_once "../_chk.php";
function find_pass_password_valid($newpass) {
    // 비밀번호의 길이가 6자리 이상 20자 이하인지 확인
    if (strlen($newpass) >= 6 && strlen($newpass) <= 20) {
        return true;  // 유효한 비밀번호
    } else {
        return false; // 유효하지 않은 비밀번호
    }
}
if(!$newpass){
  die("비밀번호를 확인해주세요.");
}
if (!find_pass_password_valid($newpass)) {
    die("6자 이상 20자 이하의 비밀번호를 입력해주세요.");
}

$sql = "update lr_member set ";
$_password = encryptPassword($newpass);
$sql.= "password = '{$_password}' ";
$sql.= "where id = '{$id}' and phone = '{$phone}' ";
$result = db_query($sql);
echo $result;
