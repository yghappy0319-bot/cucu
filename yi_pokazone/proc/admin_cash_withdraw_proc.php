<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/cash_withdraws.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

$cw_idx = (int) ($_POST['cw_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$memo   = trim((string) ($_POST['cw_memo'] ?? ''));

if ($cw_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/cash_withdraws.php');
}

if ($action === 'complete') {
    $result = member_cash_withdraw_complete($cw_idx, $memo);
    if (empty($result['ok'])) {
        alert_goto((string) ($result['error'] ?? '처리에 실패했습니다.'), '/admin/cash_withdraws.php');
    }
    alert_goto((string) ($result['message'] ?? '출금 완료 처리했습니다.'), '/admin/cash_withdraws.php');
}

if ($action === 'cancel') {
    $result = member_cash_withdraw_cancel($cw_idx, $memo);
    if (empty($result['ok'])) {
        alert_goto((string) ($result['error'] ?? '처리에 실패했습니다.'), '/admin/cash_withdraws.php');
    }
    alert_goto((string) ($result['message'] ?? '출금을 취소했습니다.'), '/admin/cash_withdraws.php');
}

alert_goto('잘못된 요청입니다.', '/admin/cash_withdraws.php');
