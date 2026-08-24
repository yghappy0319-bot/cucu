<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_auction.php';
require_once __DIR__ . '/../lib/_auction_order.php';
require_once __DIR__ . '/../../lib/_member_address.php';

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

$result = auction_order_submit((int) $me['mb_idx'], $au_idx, $_POST);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '주문 처리에 실패했습니다.', $return);
}

alert_goto('주문 및 결제가 완료되었습니다. 판매자가 곧 발송을 진행합니다.', $return . '&done=1');
