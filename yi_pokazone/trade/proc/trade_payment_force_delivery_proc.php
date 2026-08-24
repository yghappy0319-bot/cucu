<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_payment.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade_messages.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$pay_idx = (int) ($_POST['pay_idx'] ?? 0);
$return  = '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;

if ($pay_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/trade_messages.php');
}

$result = trade_payment_force_delivery_complete($pay_idx, (int) $me['mb_idx']);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '강제 배송완료 처리에 실패했습니다.'), $return);
}

alert_goto(
    '강제 배송완료 처리되었습니다. 구매자 계정으로 이 페이지를 열면 구매확정 버튼이 표시됩니다.',
    $return . '&delivery_forced=1'
);
