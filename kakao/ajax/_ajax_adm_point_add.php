<?php
include_once('../common.php');

  if($point > 0){
    $sql = "update member set mb_point = mb_point + {$point} where mb_no = {$mb_no} ";
  }else{
    $sql = "update member set mb_point = 0 where mb_no = {$mb_no} ";
  }
  
  $result = db_query($sql);
  if($result){
    echo json_encode(array("status" => "1", "msg" => "포인트 {$point} 완료"));
  }
