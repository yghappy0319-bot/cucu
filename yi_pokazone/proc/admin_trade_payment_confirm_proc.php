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

$qs = [];
$q   = trim((string) ($_POST['q'] ?? ''));
$st  = trim((string) ($_POST['st'] ?? ''));
$ful = trim((string) ($_POST['ful'] ?? ''));
$p   = max(1, (int) ($_POST['p'] ?? 1));
if ($q !== '') {
    $qs['q'] = $q;
}
if ($st !== '') {
    $qs['st'] = $st;
}
if ($ful !== '') {
    $qs['ful'] = $ful;
}
if ($p > 1) {
    $qs['p'] = $p;
}
$return = '/admin/trade_payments.php' . ($qs ? ('?' . http_build_query($qs)) : '');

if ($pay_idx < 1) {
    alert_goto('잘못된 요청입니다.', $return);
}
if (!function_exists('trade_payment_confirm_deposit')) {
    alert_goto('결제 모듈을 불러오지 못했습니다.', $return);
}

$result = trade_payment_confirm_deposit($pay_idx, '관리자 입금확인');
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '입금 확인에 실패했습니다.'), $return);
}

$sep = strpos($return, '?') === false ? '?' : '&';
$msg = !empty($result['already'])
    ? '결제 #' . $pay_idx . ' 은(는) 이미 입금 확인된 주문입니다.'
    : '결제 #' . $pay_idx . ' 입금 확인 처리했습니다. 금액은 구매확정 전까지 예치됩니다.';
alert_goto($msg, $return . $sep . 'confirmed=1');
