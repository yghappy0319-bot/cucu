<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$sql = "delete from tb_member1 where idx = '{$code}' ";
$result = db_query($sql);
echo $result;