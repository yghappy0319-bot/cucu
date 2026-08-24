<?php
/**
 * 바로구매 — 무통장 입금 신청 (JSON, 채팅 API와 별도)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../../lib/_function.php';

header('Content-Type: application/json; charset=UTF-8');

function trade_checkout_bank_json(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    trade_checkout_bank_json(['ok' => false, 'error' => '잘못된 요청입니다.']);
}

if (!trade_chat_payment_lib_load() || !function_exists('trade_checkout_submit')) {
    trade_checkout_bank_json([
        'ok'    => false,
        'error' => '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 를 서버에 업로드해 주세요.',
    ]);
}

$me = login_member();
if (!$me) {
    trade_checkout_bank_json(['ok' => false, 'error' => '로그인이 필요합니다.']);
}

$tr_idx = (int) ($_POST['tr_idx'] ?? 0);
if ($tr_idx < 1) {
    trade_checkout_bank_json(['ok' => false, 'error' => '잘못된 요청입니다.']);
}

$input = $_POST;
$input['pay_method'] = 'bank';

try {
    $result = trade_checkout_submit((int) $me['mb_idx'], $tr_idx, $input);
} catch (Throwable $e) {
    error_log('trade_checkout_bank_proc: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $err = trim($e->getMessage());
    trade_checkout_bank_json(['ok' => false, 'error' => $err !== '' ? $err : '결제 처리 중 오류가 발생했습니다.']);
}

if (empty($result['ok'])) {
    trade_checkout_bank_json([
        'ok'    => false,
        'error' => (string) ($result['error'] ?? '입금 신청에 실패했습니다.'),
    ]);
}

trade_checkout_bank_json([
    'ok'       => true,
    'pay_idx'  => (int) ($result['pay_idx'] ?? ($result['payment']['pay_idx'] ?? 0)),
    'payment'  => $result['payment'] ?? null,
    'bank'     => $result['bank'] ?? (function_exists('trade_payment_bank_info') ? trade_payment_bank_info() : []),
    'redirect' => (string) ($result['redirect'] ?? ('/trade/trade_checkout.php?tr_idx=' . $tr_idx . '&done=1')),
]);
