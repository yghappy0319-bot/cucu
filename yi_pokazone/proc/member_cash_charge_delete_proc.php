<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash_charge.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash_charge.php'));
}

$mb_idx = (int) $me['mb_idx'];
$cc_idx = (int) ($_POST['cc_idx'] ?? 0);
$return = '/page/mypage_cash_charge.php';

$result = member_cash_charge_delete_pending($mb_idx, $cc_idx);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '신청 삭제에 실패했습니다.', $return);
}

alert_goto('충전 신청이 삭제되었습니다.', $return);
