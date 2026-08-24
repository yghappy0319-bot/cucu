<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash_charge.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash_charge.php'));
}

$mb_idx    = (int) $me['mb_idx'];
$method    = trim((string) ($_POST['cc_method'] ?? 'bank'));
$amount    = (int) preg_replace('/\D/', '', (string) ($_POST['cc_amount'] ?? ''));
$depositor = trim((string) ($_POST['cc_depositor'] ?? ''));
$return    = '/page/mypage_cash_charge.php';

if ($method === 'card') {
    alert_goto('신용카드 결제는 준비중 입니다.', $return);
}

if ($method !== 'bank') {
    alert_goto('결제 수단을 확인해 주세요.', $return);
}

$result = member_cash_charge_apply_bank($mb_idx, $amount, $depositor);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '충전 신청에 실패했습니다.', $return);
}

$msg = '캐시 충전 신청이 접수되었습니다.'
    . "\n충전 금액 ₩" . number_format($amount)
    . "\n입금자명: " . $depositor
    . "\n\n입금 금액과 입금자명이 일치해야 충전이 완료됩니다.";
alert_goto($msg, $return);
