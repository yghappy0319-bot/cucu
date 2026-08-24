<?php
/**
 * 거래 메시지함 — 결제 요청·무통장 입금 API (JSON)
 * (동일 로직은 trade_chat_api.php POST action= 으로도 호출 가능)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_trade_payment.php';

header('Content-Type: application/json; charset=UTF-8');

$me = login_member();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => '지원하지 않는 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = trade_payment_process_post($me);
if ($result === null) {
    echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
