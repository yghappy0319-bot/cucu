<?php
/**
 * 바로구매 — 무통장 입금·상태 폴링 API (JSON)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';

header('Content-Type: application/json; charset=UTF-8');

if (!trade_chat_payment_lib_load() || !function_exists('trade_checkout_submit')) {
    echo json_encode([
        'ok'    => false,
        'error' => '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 최신 파일을 서버에 업로드해 주세요.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$me = login_member();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mb_idx = (int) ($me['mb_idx'] ?? 0);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string) ($_REQUEST['action'] ?? ''));

if ($action === 'status') {
    $tr_idx  = (int) ($_GET['tr_idx'] ?? 0);
    $pay_idx = (int) ($_GET['pay_idx'] ?? 0);
    if ($tr_idx < 1) {
        echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(trade_checkout_payment_status($mb_idx, $tr_idx, $pay_idx), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['ok' => false, 'error' => '지원하지 않는 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'submit_bank') {
    $tr_idx = (int) ($_POST['tr_idx'] ?? 0);
    if ($tr_idx < 1) {
        echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_POST['pay_method'] = 'bank';
    $result = trade_checkout_submit($mb_idx, $tr_idx, $_POST);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE);
