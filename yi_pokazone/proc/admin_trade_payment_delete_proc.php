<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../trade/lib/_admin_trade_payment.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/trade_payments.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

$pay_idx = (int) ($_POST['pay_idx'] ?? 0);
$return  = '/admin/trade_payments.php';

if ($pay_idx < 1) {
    alert_goto('잘못된 요청입니다.', $return);
}

$result = admin_tp_delete_payment($pay_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '결제 삭제에 실패했습니다.'), $return);
}

$msg = '결제 #' . $pay_idx . ' 및 연관 데이터가 삭제되었습니다.';
if (!empty($result['messages'])) {
    $msg .= ' (채팅 ' . (int) $result['messages'] . '건';
    if (!empty($result['cash_reversed'])) {
        $msg .= ' · 캐시 정산 회수';
    }
    $msg .= ')';
} elseif (!empty($result['cash_reversed'])) {
    $msg .= ' (캐시 정산 회수)';
}

alert_goto($msg, $return . '?deleted=1');
