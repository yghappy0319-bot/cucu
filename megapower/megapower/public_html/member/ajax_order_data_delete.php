<?php include_once "../lib/function.php";
include_once "../_chk.php";

$실행자 = $member['idx'];

$sql = "select * from lr_orders where idx = {$idx} ";
$order = db_select($sql);
if($order['midx']!=$실행자){
  die("다른 회원의 주문내역은 삭제 하실 수 없습니다!");
}

$sql = "update lr_orders set delete_data = 1 where idx = {$idx} and midx = {$실행자} ";
$result = db_query($sql);
echo $result;
