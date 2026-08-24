<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."' LIMIT 1");

if(!$M_login['midx']){
    $data = array("status" => "0", "msg" => "로그인 후 이용해주세요.");
    echo json_encode($data);
    exit;
}

if($total > $mem['CASH']){
    $data = array("status" => "0", "msg" => "보유 캐시가 부족합니다. 캐시충전 후 구매해주세요.");
    echo json_encode($data);
    exit;
}

$sql = "insert into PRODUCT_ORDERS SET ";
$sql.= "product_idx = {$pidx}, ";
$sql.= "midx = {$mem['MEMBER_NO']}, ";
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
$result = $db->query($sql);

$sql = $db->query("update MEMBER SET CASH = CASH - {$total} where MEMBER_NO = {$mem['MEMBER_NO']} ");


if($result){
    $data = array("status" => "1", "msg" => "주문이 완료 되었습니다.");
    echo json_encode($data);
    exit;
}
