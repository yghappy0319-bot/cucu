<?php include_once "../lib/function.php";
include_once "../_chk.php";

if(!$member['idx']){
  $_data = array("status" => "session_null");
  die(json_encode($_data));
}


$sql = "delete from lr_coupon_log where midx = {$member['idx']} AND (status = 1 || bdate < NOW())  ";
$result = db_query($sql);
if($result){
  $_data = array("status" => "ok");
  die(json_encode($_data));
}
