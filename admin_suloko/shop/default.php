<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SHOP.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SHOP_ORDERS.php";

F_admin_chk($S_login);

$VAL = $_POST;
$RES = array("error" => true, "msg" => "");

if (trim($VAL['mode']) ==  "search_product_info") {
    if ($VAL['no'] == '') {
        $RES["msg"] = "필수값이 누락되었습니다.";
        echo json_encode($RES);
        exit;
    }

    $result = F_PRODUCT(array(
        "mode"  => "read",
        "IDX"   => trim($VAL['no'])
    ));

    if ($result) {
        $RES["error"] = false;
        echo json_encode($result);
    } else {
        $RES["msg"] = "정보 로딩 실패!!";
        echo json_encode($RES);
    }
    exit;
}

if (trim($VAL['mode']) ==  "search_order_info") {
    if ($VAL['no'] == '') {
        $RES["msg"] = "필수값이 누락되었습니다.";
        echo json_encode($RES);
        exit;
    }

    $result = SHOP_ORDER(array(
        "mode"  => "read",
        "IDX"   => trim($VAL['no'])
    ));

    if ($result) {
        $RES["error"] = false;
        echo json_encode($result);
    } else {
        $RES["msg"] = "정보 로딩 실패!!";
        echo json_encode($RES);
    }
    exit;
}
?>
