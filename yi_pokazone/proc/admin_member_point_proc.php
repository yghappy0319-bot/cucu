<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/members.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

$mb_idx = (int) ($_POST['mb_idx'] ?? 0);
$delta  = (int) ($_POST['delta'] ?? 0);
if ($delta === 0) {
    $amount = abs((int) ($_POST['amount'] ?? 0));
    $op     = trim((string) ($_POST['op'] ?? 'grant'));
    if ($amount > 0) {
        $delta = ($op === 'deduct') ? -$amount : $amount;
    }
}
$memo   = trim((string) ($_POST['memo'] ?? ''));
$return = trim((string) ($_POST['return'] ?? '/admin/members.php'));
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
    $return = '/admin/members.php';
}

if ($mb_idx < 1 || $delta === 0) {
    alert_goto('회원번호와 변경 포인트를 확인해 주세요.', $return);
}

if ($memo === '') {
    $memo = $delta < 0 ? '관리자 차감' : '관리자 지급';
}
$admin_id = trim((string) ($ad['ad_id'] ?? ''));
if ($admin_id !== '') {
    $memo .= ' [' . $admin_id . ']';
}

if (!point_change_with_log($mb_idx, $delta, 'admin', $memo)) {
    alert_goto('포인트 처리에 실패했습니다. 잔액이 부족하거나 회원이 없을 수 있습니다.', $return);
}

alert_goto($delta < 0 ? '포인트를 차감했습니다.' : '포인트를 지급했습니다.', $return);
