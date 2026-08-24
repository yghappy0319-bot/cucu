<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_payment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$result = trade_payment_cancel_sale((int) $me['mb_idx'], $pay_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '판매 취소에 실패했습니다.'), $return);
}

$refund = (int) ($result['refund_amount'] ?? 0);
$msg = $refund > 0
    ? '판매가 취소되었습니다. 구매자에게 ₩' . number_format($refund) . '이 캐시로 환불되었습니다.'
    : '판매가 취소되었습니다.';

if (!empty($result['penalty_applied'])) {
    $until = (string) ($result['suspended_until'] ?? '');
    $until_text = $until !== '' ? date('Y-m-d H:i', strtotime($until)) : '';
    $msg .= '\n\n입금 확인 후 판매 취소가 ' . (int) ($result['cancel_count'] ?? 0) . '회 누적되어'
        . ' 거래 판매글 등록이 1일간 정지되었습니다.';
    if ($until_text !== '') {
        $msg .= ' (' . $until_text . ' 이후 등록 가능)';
    }
}

alert_goto($msg, $return . '&cancelled=1');
