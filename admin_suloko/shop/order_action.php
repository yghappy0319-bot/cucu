<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SHOP_ORDERS.php";

$VAL         = $_POST;
$banner_path = '/upload/SHOP/';
$save_dir    = $_SERVER['DOCUMENT_ROOT'].$banner_path;
$mode        = $VAL['mode'];

$result = SHOP_ORDER($VAL);

if ($result) {
    if ($mode == 'delete') {
        $msg = "상품을 삭제 하였습니다.";
    } else {
        $msg = "상품 정보를 저장하였습니다.";
    }
} else {
    $msg = "배너이미지 정보 실패!!";
}
alert_print($msg);
meta_go("/shop/order.html");
?>
