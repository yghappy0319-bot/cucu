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

$post = is_array($_POST) ? $_POST : [];
try {
    $result = trade_payment_save_tracking((int) $me['mb_idx'], $pay_idx, $post);
} catch (Throwable $e) {
    error_log('trade_payment_ship_proc: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    alert_goto('처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.', $return);
}
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '저장에 실패했습니다.'), $return);
}

$msg = !empty($result['notified'])
    ? '운송장이 등록되었습니다. 구매자에게 알림을 보냈습니다.'
    : '운송장 정보가 저장되었습니다.';

alert_goto($msg, $return . '&done=1');
