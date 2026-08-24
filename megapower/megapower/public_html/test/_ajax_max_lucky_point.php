<?php include_once "../lib/function.php";
include_once "../_chk.php";

$type = $point_type;

if(!$idx){
  $status = array("status" => "idx_null");
  echo json_encode($status);
}

//보유포인트가 더 많은경우
if($member[$type] > $total_amount){
  $남은L포인트 = $member[$type] - $total_amount;

  $status = array("status" => "max_point", "sell_point" => $total_amount, "point_type" => $type);
  echo json_encode($status);
  exit;
}else if($total_amount > $member[$type]){
  $남은L포인트 = $total_amount - $member[$type];
  // sell_point : 총사용할포인트
  $status = array("status" => "ok_point", "sell_point" => $member[$type], "total_amout" => $남은L포인트, "point_type" => $type);
  echo json_encode($status);
  exit;
}

if($member[$type]>0){
  $럭키포인트 = $member[$type];
  echo $럭키포인트;
}else{
  $status = array("status" => "lucky_point_null");
  echo json_encode($status);
}
