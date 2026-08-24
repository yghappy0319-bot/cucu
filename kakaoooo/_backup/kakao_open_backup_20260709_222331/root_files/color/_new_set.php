<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$couple = $couple > 0 ? $couple:0;

if($couple==2){
  $높번 = db_select("select max(num) as nmax from tb_member1");
  $num = $높번['nmax'] + 1;
}

$sql = "update tb_member1 set ";
$sql.= "couple = '{$couple}', ";
$sql.= "name = '{$name}', ";
$sql.= "num = {$num}, ";
$sql.= "gender = '{$gender}', ";
$sql.= "content = '{$content}' ";
$sql.= "where idx = {$number} ";
$result = db_query($sql);
echo $result;
