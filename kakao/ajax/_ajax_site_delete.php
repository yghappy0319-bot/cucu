<?
include_once "../common.php";
include_once "../_chk.php";

$sql = "delete from site where idx = {$idx} ";
$result = db_query($sql);
echo $result;
