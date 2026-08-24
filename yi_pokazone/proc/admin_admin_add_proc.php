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

$ad_id   = trim((string) ($_POST['ad_id'] ?? ''));
$ad_pw   = (string) ($_POST['ad_pw'] ?? '');
$ad_name = trim((string) ($_POST['ad_name'] ?? ''));

if ($ad_id === '' || strlen($ad_id) > 30 || $ad_pw === '' || $ad_name === '' || strlen($ad_name) > 50) {
    alert_goto('아이디·비밀번호·이름을 올바르게 입력해 주세요.', '/admin/admins.php');
}

$exists = db_assoc(db_query("SELECT ad_idx FROM tb_admin WHERE ad_id = '" . db_escape($ad_id) . "' LIMIT 1"));
if ($exists) {
    alert_goto('이미 사용 중인 아이디입니다.', '/admin/admins.php');
}

$hash = password_hash($ad_pw, PASSWORD_DEFAULT);
$esc_id   = db_escape($ad_id);
$esc_name = db_escape($ad_name);
$esc_hash = db_escape($hash);

$sql = "
    INSERT INTO tb_admin (ad_id, ad_pw, ad_name, ad_status, ad_level)
    VALUES ('{$esc_id}', '{$esc_hash}', '{$esc_name}', 1, 1)
";
if (!db_query($sql)) {
    alert_goto('등록 중 오류가 발생했습니다.', '/admin/admins.php');
}

alert_goto('관리자 계정을 추가했습니다.', '/admin/admins.php');
