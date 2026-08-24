<?
include_once "../common.php";
include_once "../_chk.php";

$sql = "update site set api_key_v2 = '{$api_key_v2}' where idx = {$idx} ";
$result = db_query($sql);
echo $result;
