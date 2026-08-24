<?php
include_once "../common.php";
header('Content-Type: application/json; charset=UTF-8');

// 쿼리 실행
$sql = "SELECT * FROM tb_pay_form_config WHERE code = '{$code}'";
$result = db_query($sql);

// 데이터 fetch
$row = db_fetch($result);

if ($row) {
    echo json_encode([
        'result' => 'success',
        'data' => $row
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'result' => 'fail',
        'message' => '데이터가 없습니다.'
    ], JSON_UNESCAPED_UNICODE);
}

exit;
