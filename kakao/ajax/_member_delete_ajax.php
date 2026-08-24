<?php
include_once('../common.php');

$sql = "delete from member where mb_no = {$mb_no} ";
$result = db_query($sql);
$sql2 = "delete from site where member_no = {$mb_no} ";
db_query($sql2);

echo $result;
