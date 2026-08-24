<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/login.php');
}

$mb_id      = trim($_POST['mb_id'] ?? '');
$mb_pw      = (string)($_POST['mb_pw'] ?? '');
$save_id    = !empty($_POST['save_id']);
$return_url = $_POST['return'] ?? '/';

if ($mb_id === '' || $mb_pw === '') {
    alert_goto('아이디와 비밀번호를 입력해 주세요.');
}

$ip = get_client_ip();
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

function write_login_log($mb_idx, $mb_id, $ip, $ua, $status, $msg = '') {
    $sql = "
        INSERT INTO tb_member_login_log
            (mb_idx, log_mb_id, log_ip, log_user_agent, log_status, log_message, log_created_at)
        VALUES
            (".($mb_idx ? (int)$mb_idx : 'NULL').",
             '".db_escape($mb_id)."',
             '".db_escape($ip)."',
             '".db_escape($ua)."',
             ".(int)$status.",
             ".($msg ? "'".db_escape($msg)."'" : 'NULL').",
             NOW())
    ";
    @db_query($sql);
}

$esc_id = db_escape($mb_id);
$rs     = db_query("SELECT * FROM tb_member WHERE mb_id='{$esc_id}' LIMIT 1");
$row    = db_assoc($rs);

if (!$row) {
    write_login_log(0, $mb_id, $ip, $ua, 0, '존재하지 않는 아이디');
    alert_goto('아이디 또는 비밀번호가 올바르지 않습니다.');
}

if ((int)$row['mb_status'] !== 1) {
    $status_msg = [
        0 => '휴면 상태의 계정입니다.',
        2 => '정지된 계정입니다. 고객센터에 문의해 주세요.',
        3 => '탈퇴한 계정입니다.',
    ];
    write_login_log($row['mb_idx'], $mb_id, $ip, $ua, 0, '비정상 상태');
    alert_goto($status_msg[(int)$row['mb_status']] ?? '로그인할 수 없는 계정입니다.');
}

if (!password_verify($mb_pw, $row['mb_pw'])) {
    write_login_log($row['mb_idx'], $mb_id, $ip, $ua, 0, '비밀번호 불일치');
    alert_goto('아이디 또는 비밀번호가 올바르지 않습니다.');
}

if (function_exists('member_login_block_info')) {
    $block = member_login_block_info((int) $row['mb_idx']);
    if (!empty($block['blocked'])) {
        $block_msg = member_login_block_user_message($block);
        $_SESSION['pz_login_block_notice'] = $block_msg;
        write_login_log($row['mb_idx'], $mb_id, $ip, $ua, 0, '로그인 접속 제한');
        alert_goto($block_msg, '/login.php');
    }
}

session_regenerate_id(true);

$_SESSION['mb_idx']   = (int)$row['mb_idx'];
$_SESSION['mb_id']    = $row['mb_id'];
$_SESSION['mb_nick']  = $row['mb_nick'];
$_SESSION['mb_level'] = (int)$row['mb_level'];

db_query("
    UPDATE tb_member SET
        mb_login_count = mb_login_count + 1,
        mb_last_login_at = NOW(),
        mb_last_login_ip = '".db_escape($ip)."'
    WHERE mb_idx = ".(int)$row['mb_idx']
);

write_login_log($row['mb_idx'], $mb_id, $ip, $ua, 1);

if ($save_id) {
    setcookie('pz_save_id', $mb_id, time() + 60*60*24*30, '/');
} else {
    setcookie('pz_save_id', '', time() - 3600, '/');
}

if (!preg_match('#^/[A-Za-z0-9/_\-\.\?\=\&#]*$#', $return_url)) {
    $return_url = '/';
}

header('Location: ' . $return_url);
exit;
