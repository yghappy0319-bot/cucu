<?php include_once "../lib/function.php";
include_once "../_chk.php";


$sql = "update lr_member set cut_off = 1 where idx = {$member['idx']} ";
$result = db_query($sql);
echo $result;
