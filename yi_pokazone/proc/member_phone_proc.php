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

$rs = db_query("SELECT mb_idx, mb_status, mb_phone FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}
if ((int) $row['mb_status'] !== 1) {
    alert_goto('로그인할 수 없는 계정입니다. 고객센터로 문의해 주세요.', '/logout.php');
}

$mb_phone_raw = trim((string) ($_POST['mb_phone'] ?? ''));

// 번호 등록·변경은 SMS 인증 API로만 가능. 이 proc는 미등록(삭제)만 처리합니다.
if ($mb_phone_raw !== '') {
    alert_goto('휴대폰 번호 변경은 인증번호 확인 후 적용됩니다.', '/page/member_info.php');
}

$ok = db_query("
    UPDATE tb_member SET
        mb_phone = NULL,
        mb_updated_at = NOW()
    WHERE mb_idx = {$mb_idx} AND mb_status = 1
    LIMIT 1
");

if (!$ok) {
    alert_goto('저장 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');
}

$old_phone = isset($row['mb_phone']) ? (string) $row['mb_phone'] : null;
if (
    member_contact_history_table_ready()
    && !member_contact_history_log_phone(
        $mb_idx,
        $old_phone,
        null,
        member_contact_history_actor_member($mb_idx)
    )
) {
    error_log("member_contact_history: phone log failed mb_idx={$mb_idx}");
}

alert_goto('휴대폰 번호가 삭제되었습니다.', '/page/member_info.php');
