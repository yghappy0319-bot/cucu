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
$action  = trim((string) ($_POST['action'] ?? ''));
$return  = '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;

if ($pay_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/trade_messages.php');
}

if (!in_array($action, ['confirm_shipping', 'confirm_purchase'], true)) {
    alert_goto('잘못된 요청입니다.', $return);
}

$mb_idx = (int) $me['mb_idx'];

if ($action === 'confirm_shipping') {
    $result = trade_payment_confirm_shipping($pay_idx, $mb_idx);
    if (empty($result['ok'])) {
        alert_goto((string) ($result['error'] ?? '수령 확인에 실패했습니다.'), $return);
    }
    alert_goto('수령 확인이 완료되었습니다. 물건에 이상이 없으면 구매확정을 진행해 주세요. '
        . (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS . '일 이내 구매확정이 없으면 자동으로 구매확정됩니다.', $return . '&shipping_confirmed=1');
}

$result = trade_payment_confirm_purchase($pay_idx, $mb_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '구매확정에 실패했습니다.'), $return);
}

$seller_amt = (int) ($result['seller_amount'] ?? 0);
$fee        = (int) ($result['fee'] ?? 0);
$msg = $seller_amt > 0
    ? '구매확정이 완료되었습니다. 판매자에게 ₩' . number_format($seller_amt) . '이 정산됩니다.'
        . ($fee > 0 ? ' (플랫폼 수수료 ₩' . number_format($fee) . ' 차감)' : '')
        . ' 아래에서 판매자 후기를 남길 수 있습니다.'
    : '구매확정이 완료되었습니다. 아래에서 판매자 후기를 남길 수 있습니다.';

alert_goto($msg, $return . '&purchase_confirmed=1');
