<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/member_info.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_info.php'));
}

$mb_idx = (int) $me['mb_idx'];
$pw_cur = (string) ($_POST['mb_pw_current'] ?? '');
$pw_new = (string) ($_POST['mb_pw_new'] ?? '');
$pw_cf  = (string) ($_POST['mb_pw_confirm'] ?? '');

if ($pw_cur === '') {
    alert_goto('현재 비밀번호를 입력해 주세요.');
}
if (strlen($pw_new) < 8 || strlen($pw_new) > 50) {
    alert_goto('새 비밀번호는 8자 이상 50자 이하여야 합니다.');
}
if ($pw_new !== $pw_cf) {
    alert_goto('새 비밀번호가 서로 일치하지 않습니다.');
}
if ($pw_cur === $pw_new) {
    alert_goto('새 비밀번호는 현재 비밀번호와 달라야 합니다.');
}

$rs = db_query("SELECT mb_pw, mb_status FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}
if ((int) $row['mb_status'] !== 1) {
    alert_goto('로그인할 수 없는 계정입니다. 고객센터로 문의해 주세요.', '/logout.php');
}

if (!password_verify($pw_cur, $row['mb_pw'])) {
    alert_goto('현재 비밀번호가 올바르지 않습니다.');
}

$pw_hash = password_hash($pw_new, PASSWORD_DEFAULT);
if ($pw_hash === false) {
    alert_goto('비밀번호 처리에 실패했습니다. 다시 시도해 주세요.');
}

$ok = db_query("
    UPDATE tb_member SET
        mb_pw = '" . db_escape($pw_hash) . "',
        mb_updated_at = NOW()
    WHERE mb_idx = {$mb_idx} AND mb_status = 1
    LIMIT 1
");

if (!$ok) {
    alert_goto('비밀번호 변경에 실패했습니다. 잠시 후 다시 시도해 주세요.');
}

alert_goto('비밀번호가 변경되었습니다.', '/page/member_info.php');
