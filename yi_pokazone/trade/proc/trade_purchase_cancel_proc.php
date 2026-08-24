<?php
require_once __DIR__ . '/../../lib/_function.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade_purchases.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/trade/trade_purchases.php'));
}

$page_num = max(1, (int) ($_POST['p'] ?? 1));
$return   = '/trade/trade_purchases.php';
if ($page_num > 1) {
    $return .= '?p=' . $page_num;
}

$pay_idx = (int) ($_POST['pay_idx'] ?? 0);
if ($pay_idx < 1) {
    alert_goto('취소할 입금 신청을 찾을 수 없습니다.', $return);
}

if (!trade_chat_payment_lib_load() || !function_exists('trade_purchase_buyer_cancel_deposit')) {
    alert_goto('결제 모듈을 불러오지 못했습니다.', $return);
}

$result = trade_purchase_buyer_cancel_deposit($me, $pay_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? $result['message'] ?? '입금 신청 취소에 실패했습니다.'), $return);
}

alert_goto((string) ($result['message'] ?? '입금 신청을 취소했습니다.'), $return);
