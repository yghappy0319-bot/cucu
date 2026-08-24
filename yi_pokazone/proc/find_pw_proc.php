<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/find.php?type=pw');
}

$mb_id    = trim($_POST['mb_id'] ?? '');
$mb_name  = trim($_POST['mb_name'] ?? '');
$mb_email = trim($_POST['mb_email'] ?? '');
$mb_pw    = (string) ($_POST['mb_pw'] ?? '');
$mb_pw2   = (string) ($_POST['mb_pw_confirm'] ?? '');

if ($mb_id === '' || !preg_match('/^[A-Za-z0-9_]{4,20}$/', $mb_id)) {
    alert_goto('아이디를 올바르게 입력해 주세요.');
}
if ($mb_name === '' || mb_strlen($mb_name) > 50) {
    alert_goto('이름을 올바르게 입력해 주세요.');
}
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert_goto('이메일 형식이 올바르지 않습니다.');
}
if (strlen($mb_pw) < 8 || strlen($mb_pw) > 50) {
    alert_goto('비밀번호는 8자 이상 50자 이하여야 합니다.');
}
if ($mb_pw !== $mb_pw2) {
    alert_goto('비밀번호가 일치하지 않습니다.');
}

$esc_id    = db_escape($mb_id);
$esc_name  = db_escape($mb_name);
$esc_email = db_escape($mb_email);

$rs = db_query(
    "SELECT mb_idx, mb_status FROM tb_member
     WHERE mb_id = '{$esc_id}' AND mb_name = '{$esc_name}' AND mb_email = '{$esc_email}'
     LIMIT 1"
);
$row = db_assoc($rs);

if (!$row) {
    alert_goto('일치하는 회원 정보가 없습니다. 아이디·이름·이메일을 확인해 주세요.');
}

if ((int) $row['mb_status'] !== 1) {
    alert_goto('로그인할 수 없는 계정입니다. 고객센터로 문의해 주세요.');
}

$pw_hash = password_hash($mb_pw, PASSWORD_DEFAULT);
if ($pw_hash === false) {
    alert_goto('비밀번호 처리에 실패했습니다. 다시 시도해 주세요.');
}

$mb_idx = (int) $row['mb_idx'];
$ok     = db_query(
    "UPDATE tb_member SET
        mb_pw = '" . db_escape($pw_hash) . "',
        mb_updated_at = NOW()
     WHERE mb_idx = {$mb_idx} AND mb_status = 1 LIMIT 1"
);

if (!$ok) {
    alert_goto('비밀번호 변경에 실패했습니다. 잠시 후 다시 시도해 주세요.');
}

alert_goto('비밀번호가 변경되었습니다. 새 비밀번호로 로그인해 주세요.', '/login.php');
