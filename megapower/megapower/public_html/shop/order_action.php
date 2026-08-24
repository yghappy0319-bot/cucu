<?php
include_once "../lib/function.php";
include_once "../_chk.php";

$mem = db_select("SELECT * FROM lr_member WHERE id ='".$member['id']."' LIMIT 1");
if(!$member['idx']){
    $data = array("status" => "0", "msg" => "로그인 후 이용해주세요.");
    echo json_encode($data);
    exit;
}
if($total > $mem['cash']){
    $data = array("status" => "0", "msg" => "보유 캐시가 부족합니다. 캐시충전 후 구매해주세요.");
    echo json_encode($data);
    exit;
}

$sql = "insert into lr_product_orders SET ";
$sql.= "product_idx = {$pidx}, ";
$sql.= "midx = {$mem['idx']}, ";
$sql.= "view_status = 1, ";
$sql.= "quantity = {$quantity}, ";
$sql.= "product_name = '{$product_name}', ";
$sql.= "order_price = {$price},";
$sql.= "order_delivery = {$delivery}, ";
$sql.= "order_name = '{$order_name}', ";
$sql.= "order_phone = '{$order_phone}', ";
$sql.= "order_zipcode = '{$order_zipcode}', ";
$sql.= "order_address1 = '{$order_address1}', ";
$sql.= "order_address2 = '{$order_address2}', ";
$sql.= "order_memo = '{$order_memo}', ";
$sql.= "regdate = now() ";
$result = db_query($sql);

$sql = db_query("update lr_member SET cash = cash - {$total} where idx = {$mem['idx']} ");

if($result){
    $data = array("status" => "1", "msg" => "주문이 완료 되었습니다.");
    echo json_encode($data);
    exit;
}
