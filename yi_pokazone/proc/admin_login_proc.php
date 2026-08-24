<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/login.php');
}

$ad_id      = trim($_POST['ad_id'] ?? '');
$ad_pw      = (string)($_POST['ad_pw'] ?? '');
$save_id    = !empty($_POST['save_id']);
$return_url = $_POST['return'] ?? '/admin/';

if ($ad_id === '' || $ad_pw === '') {
    alert_goto('아이디와 비밀번호를 입력해 주세요.');
}

$ip = get_client_ip();
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

if (!db_table_exists('tb_admin') || !db_table_exists('tb_admin_login_log')) {
    alert_goto('관리자 테이블이 설치되지 않았습니다. sql/tb_admin.sql을 실행해 주세요.', '/admin/login.php');
}

function admin_write_login_log($ad_idx, $ad_id_try, $ip, $ua, $status, $msg = ''): void
{
    $sql = "
        INSERT INTO tb_admin_login_log
            (ad_idx, log_ad_id, log_ip, log_user_agent, log_status, log_message, log_created_at)
        VALUES
            (".($ad_idx ? (int) $ad_idx : 'NULL').",
             '".db_escape($ad_id_try)."',
             '".db_escape($ip)."',
             '".db_escape($ua)."',
             ".(int) $status.",
             ".($msg ? "'".db_escape($msg)."'" : 'NULL').",
             NOW())
    ";
    @db_query($sql);
}

$esc_id = db_escape($ad_id);
$rs     = db_query("SELECT * FROM tb_admin WHERE ad_id='{$esc_id}' LIMIT 1");
$row    = db_assoc($rs);

if (!$row) {
    admin_write_login_log(0, $ad_id, $ip, $ua, 0, '존재하지 않는 아이디');
    alert_goto('아이디 또는 비밀번호가 올바르지 않습니다.');
}

if ((int) $row['ad_status'] !== 1) {
    admin_write_login_log((int) $row['ad_idx'], $ad_id, $ip, $ua, 0, '비활성 계정');
    alert_goto('로그인할 수 없는 계정입니다. 관리자에게 문의하세요.');
}

if (!password_verify($ad_pw, $row['ad_pw'])) {
    admin_write_login_log((int) $row['ad_idx'], $ad_id, $ip, $ua, 0, '비밀번호 불일치');
    alert_goto('아이디 또는 비밀번호가 올바르지 않습니다.');
}

pz_admin_login_commit([
    'ad_idx'   => (int) $row['ad_idx'],
    'ad_id'    => $row['ad_id'],
    'ad_name'  => $row['ad_name'],
    'ad_level' => (int) $row['ad_level'],
]);

db_query("
    UPDATE tb_admin SET
        ad_login_count = ad_login_count + 1,
        ad_last_login_at = NOW(),
        ad_last_login_ip = '".db_escape($ip)."'
    WHERE ad_idx = ".(int) $row['ad_idx']."
    LIMIT 1
");

admin_write_login_log((int) $row['ad_idx'], $ad_id, $ip, $ua, 1);

$cookie_name = 'pz_admin_save_id';
if ($save_id) {
    setcookie($cookie_name, $ad_id, time() + 60 * 60 * 24 * 30, '/');
} else {
    setcookie($cookie_name, '', time() - 3600, '/');
}

if (!preg_match('#^/[A-Za-z0-9/_\-\.\?\=\&]*$#', $return_url)) {
    $return_url = '/admin/';
}

header('Location: ' . $return_url);
exit;
