<?php
require_once __DIR__ . '/../../lib/_function.php';

if (!trade_chat_payment_lib_load() || !function_exists('trade_checkout_submit')) {
    alert_goto(
        '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 최신 파일을 서버에 업로드해 주세요.',
        '/trade/trade.php'
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$tr_idx = (int) ($_POST['tr_idx'] ?? 0);
$return = '/trade/trade_checkout.php?tr_idx=' . $tr_idx;

if ($tr_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/trade.php');
}

$result = trade_checkout_submit((int) $me['mb_idx'], $tr_idx, $_POST);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '주문 처리에 실패했습니다.', $return);
}

$redirect = (string) ($result['redirect'] ?? $return);
$message  = (string) ($result['message'] ?? '주문이 완료되었습니다.');
alert_goto($message, $redirect);
