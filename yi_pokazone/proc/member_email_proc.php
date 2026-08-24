<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_contact_history.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/member_info.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_info.php'));
}

$mb_idx = (int) $me['mb_idx'];

$rs = db_query("SELECT mb_idx, mb_status, mb_email FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}
if ((int) $row['mb_status'] !== 1) {
    alert_goto('로그인할 수 없는 계정입니다. 고객센터로 문의해 주세요.', '/logout.php');
}

$mb_email = trim((string) ($_POST['mb_email'] ?? ''));

if ($mb_email === '') {
    alert_goto('이메일을 입력해 주세요.');
}
if (mb_strlen($mb_email) > 100) {
    alert_goto('이메일은 100자 이하여야 합니다.');
}
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert_goto('이메일 형식이 올바르지 않습니다.');
}

$esc_email = db_escape($mb_email);
if ((int) db_result("SELECT COUNT(*) FROM tb_member WHERE mb_email = '{$esc_email}' AND mb_idx <> {$mb_idx}") > 0) {
    alert_goto('이미 사용 중인 이메일입니다.');
}

$ok = db_query("
    UPDATE tb_member SET
        mb_email = '{$esc_email}',
        mb_updated_at = NOW()
    WHERE mb_idx = {$mb_idx} AND mb_status = 1
    LIMIT 1
");

if (!$ok) {
    alert_goto('저장 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');
}

$old_email = (string) ($row['mb_email'] ?? '');
if (
    member_contact_history_table_ready()
    && !member_contact_history_log_email(
        $mb_idx,
        $old_email,
        $mb_email,
        member_contact_history_actor_member($mb_idx)
    )
) {
    error_log("member_contact_history: email log failed mb_idx={$mb_idx}");
}

alert_goto('이메일이 변경되었습니다.', '/page/member_info.php');
