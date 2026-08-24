<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_auction.php';
require_once __DIR__ . '/../lib/_auction_order.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/auction/auction.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$au_idx = (int) ($_POST['au_idx'] ?? 0);
$return = '/auction/auction_order.php?au_idx=' . $au_idx;

if ($au_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/auction/auction.php');
}

$result = auction_order_accept_refund_by_seller((int) $me['mb_idx'], $au_idx);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '환불 수락에 실패했습니다.', $return);
}

alert_goto(
    '환불을 수락했습니다. 구매자에게 ₩' . number_format((int) ($result['refund_amount'] ?? 0)) . '이 환불되었습니다.',
    $return
);
