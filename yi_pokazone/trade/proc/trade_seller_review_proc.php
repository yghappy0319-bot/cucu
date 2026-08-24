<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_seller_review.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade_messages.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$pay_idx = (int) ($_POST['pay_idx'] ?? 0);
$rating  = (int) ($_POST['tsr_rating'] ?? 0);
$body    = (string) ($_POST['tsr_body'] ?? '');
$return  = '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;

if ($pay_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/trade_messages.php');
}

$result = trade_seller_review_submit($pay_idx, (int) $me['mb_idx'], $rating, $body);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '후기 등록에 실패했습니다.'), $return);
}

$points = (int) ($result['points_awarded'] ?? 0);
$msg = $points > 0
    ? '판매자 후기가 등록되었습니다. ' . number_format($points) . 'P가 적립되었습니다.'
    : '판매자 후기가 등록되었습니다. 감사합니다.';

alert_goto($msg, $return . '&review_done=1');
