<?php
/**
 * 바로구매 — 무통장 입금 확인 상태 (JSON 폴링, 채팅 API와 별도)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';

header('Content-Type: application/json; charset=UTF-8');

if (!trade_chat_payment_lib_load() || !function_exists('trade_checkout_payment_status')) {
    echo json_encode([
        'ok'    => false,
        'error' => '결제 모듈을 불러오지 못했습니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$me = login_member();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tr_idx  = (int) ($_GET['tr_idx'] ?? 0);
$pay_idx = (int) ($_GET['pay_idx'] ?? 0);

if ($tr_idx < 1) {
    echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = trade_checkout_payment_status((int) $me['mb_idx'], $tr_idx, $pay_idx);
} catch (Throwable $e) {
    error_log('trade_checkout_status: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => '상태 조회 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
