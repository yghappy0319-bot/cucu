<?php include_once "../lib/function.php";
include_once "../_chk.php";

$result  = db_query("delete from lr_my_number where idx = {$idx} ");
echo $result;
