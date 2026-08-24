<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/admins.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_admin')) {
    alert_goto('관리자 테이블이 없습니다.', '/admin/admins.php');
}

$ad_idx = (int) ($_POST['ad_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));

if ($ad_idx < 1 || $ad_idx === (int) $ad['ad_idx']) {
    alert_goto('본인 계정은 여기서 비활성화할 수 없습니다.', '/admin/admins.php');
}

if ($action === 'toggle_status') {
    $row = db_assoc(db_query("SELECT ad_status FROM tb_admin WHERE ad_idx = {$ad_idx} LIMIT 1"));
    if (!$row) {
        alert_goto('계정을 찾을 수 없습니다.', '/admin/admins.php');
    }
    $next = (int) $row['ad_status'] === 1 ? 0 : 1;
    if (!db_query("UPDATE tb_admin SET ad_status = {$next}, ad_updated_at = NOW() WHERE ad_idx = {$ad_idx} LIMIT 1")) {
        alert_goto('처리 중 오류가 발생했습니다.', '/admin/admins.php');
    }
    alert_goto('계정 상태를 변경했습니다.', '/admin/admins.php');
}

alert_goto('잘못된 요청입니다.', '/admin/admins.php');
