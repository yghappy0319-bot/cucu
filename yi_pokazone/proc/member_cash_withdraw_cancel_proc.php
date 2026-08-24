<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/mypage_cash_withdraw.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash_withdraw.php'));
}

$mb_idx = (int) $me['mb_idx'];
$cw_idx = (int) ($_POST['cw_idx'] ?? 0);
$return = '/page/mypage_cash_withdraw.php';

$result = member_cash_withdraw_cancel($cw_idx, '회원 직접 취소', $mb_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '출금 취소에 실패했습니다.'), $return);
}

alert_goto((string) ($result['message'] ?? '출금 신청을 취소했습니다.'), $return);
