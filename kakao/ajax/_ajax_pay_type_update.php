<?php
include_once('../common.php');

$sql = "update member set auto_money = {$service_type} where mb_no = {$mb_no} ";
$result = db_query($sql);
if($result){
  echo json_encode(array("status" => "1", "msg" => "결제타입 설정 완료"));
}
