<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_listing.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade_sales.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/trade/trade_sales.php'));
}

$page_num = max(1, (int) ($_POST['p'] ?? 1));
$tab      = function_exists('trade_seller_listing_tab_normalize')
    ? trade_seller_listing_tab_normalize((string) ($_POST['tab'] ?? 'active'))
    : 'active';
$return   = '/trade/trade_sales.php?tab=' . urlencode($tab);
if ($page_num > 1) {
    $return .= '&p=' . $page_num;
}

$pay_idx = (int) ($_POST['pay_idx'] ?? 0);
$tr_idx  = (int) ($_POST['tr_idx'] ?? 0);
if ($pay_idx < 1) {
    alert_goto('취소할 입금 신청을 찾을 수 없습니다.', $return);
}

if (!trade_chat_payment_lib_load() || !function_exists('trade_payment_cancel_request')) {
    alert_goto('결제 모듈을 불러오지 못했습니다.', $return);
}

$seller_mb = (int) $me['mb_idx'];
$row = trade_payment_row($pay_idx);
if (!$row || (int) ($row['seller_mb_idx'] ?? 0) !== $seller_mb) {
    alert_goto('판매자만 입금 신청을 취소할 수 있습니다.', $return);
}
if ($tr_idx > 0 && (int) ($row['tr_idx'] ?? 0) !== $tr_idx) {
    alert_goto('거래글 정보가 일치하지 않습니다.', $return);
}

$status = (int) ($row['pay_status'] ?? 0);
if (trade_payment_is_confirmed_status($status)) {
    alert_goto('이미 입금이 확인되어 취소할 수 없습니다.', $return);
}
if (!trade_payment_seller_can_cancel($status)) {
    alert_goto('취소할 수 없는 입금 신청입니다.', $return);
}

$room_idx = (int) ($row['room_idx'] ?? 0);
$msg_idx  = (int) ($row['request_msg_idx'] ?? 0);
$result   = trade_payment_cancel_request($pay_idx, $seller_mb, $room_idx, $msg_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '입금 신청 취소에 실패했습니다.'), $return);
}

alert_goto('입금 신청을 취소했습니다.', $return);
