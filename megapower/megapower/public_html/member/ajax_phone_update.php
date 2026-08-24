<?php include_once "../lib/function.php";
include_once "../_chk.php";

$sql = "update lr_member set ";
$sql.= "phone = '{$phone}' ";
$sql.= "where idx = {$member['idx']} ";
$result = db_query($sql);
if($result){
  echo "ok";
}else{
  echo "no";
}
