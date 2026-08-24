<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$sql = "insert into tb_danger_member set ";
$sql.= "name = '{$name}', ";
$sql.= "profile = '{$content}', ";
$sql.= "regdate = now() ";
$result = db_query($sql);
echo $result;
