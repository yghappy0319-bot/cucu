<?php
include_once('../common.php');

header('Content-Type: application/json; charset=utf-8');

$mb_no = isset($mb_no) ? (int) $mb_no : 0;
$cash = isset($cash) ? (int) $cash : 0;

if ($mb_no <= 0) {
    echo json_encode(array('status' => '0', 'msg' => '회원 번호가 올바르지 않습니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cash < 0) {
    echo json_encode(array('status' => '0', 'msg' => '금액이 올바르지 않습니다.'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cash > 0) {
    $sql = "update member set mb_cash = COALESCE(mb_cash, 0) + {$cash} where mb_no = {$mb_no} ";
} else {
    $sql = "update member set mb_cash = 0 where mb_no = {$mb_no} ";
}

$result = db_query($sql);
if ($result) {
    $msg = $cash > 0 ? "캐시 " . number_format($cash) . "원 추가 완료" : '캐시를 0으로 초기화했습니다.';
    echo json_encode(array('status' => '1', 'msg' => $msg), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(array('status' => '0', 'msg' => '처리에 실패했습니다.'), JSON_UNESCAPED_UNICODE);
}
