<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_login_block.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('관리자 로그인이 필요합니다.', '/admin/login.php');
}

$mb_idx = (int) ($_POST['mb_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$back   = '/page/member_profile.php?mb_idx=' . $mb_idx;
$from   = trim((string) ($_POST['from'] ?? ''));
if ($from === 'admin' && $mb_idx > 0) {
    $back = '/admin/member_edit.php?idx=' . $mb_idx;
}
if ($mb_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/trade.php');
}

if ($action === 'apply') {
    $days   = (int) ($_POST['days'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $res    = member_login_block_apply($mb_idx, $days, $reason, (int) ($ad['ad_idx'] ?? 0));
    if (empty($res['ok'])) {
        alert_goto((string) ($res['error'] ?? '처리에 실패했습니다.'), $back);
    }
    $until = (string) ($res['until'] ?? '');
    $until_lbl = $until !== '' ? date('Y-m-d H:i', strtotime($until)) : '';
    alert_goto(
        '로그인 접속 제한을 적용했습니다.' . ($until_lbl !== '' ? ' (' . $until_lbl . '까지)' : ''),
        $back
    );
}

if ($action === 'lift') {
    $res = member_login_block_lift($mb_idx);
    if (empty($res['ok'])) {
        alert_goto((string) ($res['error'] ?? '처리에 실패했습니다.'), $back);
    }
    alert_goto('로그인 접속 제한을 해제했습니다.', $back);
}

alert_goto('잘못된 요청입니다.', $back);
