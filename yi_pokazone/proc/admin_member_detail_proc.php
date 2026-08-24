<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_contact_history.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/members.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_member')) {
    alert_goto('회원 테이블이 없습니다.', '/admin/members.php');
}

$mb_idx = (int) ($_POST['mb_idx'] ?? 0);
if ($mb_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/members.php');
}

$rs = db_query("SELECT mb_idx, mb_email, mb_phone FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$before = db_assoc($rs);
if (!$before) {
    alert_goto('존재하지 않는 회원입니다.', '/admin/members.php');
}

$mb_id    = trim((string) ($_POST['mb_id'] ?? ''));
$mb_name  = trim((string) ($_POST['mb_name'] ?? ''));
$mb_nick  = trim((string) ($_POST['mb_nick'] ?? ''));
$mb_email = trim((string) ($_POST['mb_email'] ?? ''));
$mb_phone_raw = trim((string) ($_POST['mb_phone'] ?? ''));
$mb_status = isset($_POST['mb_status']) ? (int) $_POST['mb_status'] : -1;
$mb_level  = isset($_POST['mb_level']) ? (int) $_POST['mb_level'] : -1;

$pw_new = (string) ($_POST['mb_pw_new'] ?? '');
$pw_cf  = (string) ($_POST['mb_pw_new_confirm'] ?? '');

if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $mb_id)) {
    alert_goto('아이디는 영문/숫자/밑줄 4~20자여야 합니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}
if ($mb_name === '' || mb_strlen($mb_name) > 20) {
    alert_goto('이름을 올바르게 입력해 주세요.', '/admin/member_edit.php?idx=' . $mb_idx);
}
if ($mb_nick === '' || mb_strlen($mb_nick) > 20) {
    alert_goto('닉네임을 올바르게 입력해 주세요.', '/admin/member_edit.php?idx=' . $mb_idx);
}
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert_goto('이메일 형식이 올바르지 않습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}
if (!in_array($mb_status, [0, 1, 2, 3], true) || $mb_level < 1 || $mb_level > 9) {
    alert_goto('상태 또는 등급 값이 올바르지 않습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}

$mb_phone_sql = 'NULL';
$mb_phone_new = null;
if ($mb_phone_raw !== '') {
    $mb_phone = mb_phone_normalize_kr($mb_phone_raw);
    if ($mb_phone === null) {
        alert_goto('휴대폰 번호를 올바르게 입력해 주세요.', '/admin/member_edit.php?idx=' . $mb_idx);
    }
    $mb_phone_digits = mb_phone_digits($mb_phone);
    $esc_digits      = db_escape($mb_phone_digits);
    $dup_phone       = (int) db_result(
        "SELECT COUNT(*) FROM tb_member
         WHERE mb_idx <> {$mb_idx}
           AND IFNULL(`mb_phone`, '') <> ''
           AND REPLACE(REPLACE(REPLACE(`mb_phone`, '-', ''), ' ', ''), '.', '') = '{$esc_digits}'"
    );
    if ($dup_phone > 0) {
        alert_goto('다른 회원이 이미 사용 중인 휴대폰 번호입니다.', '/admin/member_edit.php?idx=' . $mb_idx);
    }
    $mb_phone_new = $mb_phone;
    $mb_phone_sql = "'" . db_escape($mb_phone) . "'";
}

$esc_id    = db_escape($mb_id);
$esc_nick  = db_escape($mb_nick);
$esc_email = db_escape($mb_email);
$esc_name  = db_escape($mb_name);

if ((int) db_result("SELECT COUNT(*) FROM tb_member WHERE mb_id = '{$esc_id}' AND mb_idx <> {$mb_idx}") > 0) {
    alert_goto('이미 사용 중인 아이디입니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}
if ((int) db_result("SELECT COUNT(*) FROM tb_member WHERE mb_nick = '{$esc_nick}' AND mb_idx <> {$mb_idx}") > 0) {
    alert_goto('이미 사용 중인 닉네임입니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}
if ((int) db_result("SELECT COUNT(*) FROM tb_member WHERE mb_email = '{$esc_email}' AND mb_idx <> {$mb_idx}") > 0) {
    alert_goto('이미 사용 중인 이메일입니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}

$pw_fragment = '';
if ($pw_new !== '' || $pw_cf !== '') {
    if (strlen($pw_new) < 8 || strlen($pw_new) > 50) {
        alert_goto('비밀번호는 8~50자여야 합니다.', '/admin/member_edit.php?idx=' . $mb_idx);
    }
    if ($pw_new !== $pw_cf) {
        alert_goto('비밀번호 확인이 일치하지 않습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
    }
    $hash = password_hash($pw_new, PASSWORD_DEFAULT);
    if ($hash === false) {
        alert_goto('비밀번호 처리에 실패했습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
    }
    $pw_fragment = ", mb_pw = '" . db_escape($hash) . "'";
}

$sql = "
    UPDATE tb_member SET
        mb_id = '{$esc_id}',
        mb_name = '{$esc_name}',
        mb_nick = '{$esc_nick}',
        mb_email = '{$esc_email}',
        mb_phone = {$mb_phone_sql},
        mb_status = {$mb_status},
        mb_level = {$mb_level},
        mb_updated_at = NOW(){$pw_fragment}
    WHERE mb_idx = {$mb_idx}
    LIMIT 1
";

if (!db_query($sql)) {
    alert_goto('저장 중 오류가 발생했습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
}

$actor = member_contact_history_actor_admin((int) $ad['ad_idx']);
if (member_contact_history_table_ready()) {
    $old_email = (string) ($before['mb_email'] ?? '');
    if (!member_contact_history_log_email($mb_idx, $old_email, $mb_email, $actor)) {
        error_log("member_contact_history: admin email log failed mb_idx={$mb_idx}");
    }

    $old_phone = isset($before['mb_phone']) ? (string) $before['mb_phone'] : null;
    if (!member_contact_history_log_phone($mb_idx, $old_phone, $mb_phone_new, $actor)) {
        error_log("member_contact_history: admin phone log failed mb_idx={$mb_idx}");
    }
}

alert_goto('회원 정보를 저장했습니다.', '/admin/member_edit.php?idx=' . $mb_idx);
