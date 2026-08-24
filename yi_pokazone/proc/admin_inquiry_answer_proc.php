<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/inquiries.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_inquiry')) {
    alert_goto('문의 테이블이 없습니다.', '/admin/inquiries.php');
}

$idx    = (int) ($_POST['iq_idx'] ?? 0);
$answer = trim((string) ($_POST['iq_answer'] ?? ''));
if ($idx < 1 || $answer === '') {
    alert_goto('답변 내용을 입력해 주세요.', '/admin/inquiry_view.php?idx=' . $idx);
}

$chk = db_assoc(db_query("SELECT iq_status FROM tb_inquiry WHERE iq_idx = {$idx} LIMIT 1"));
if (!$chk || (int) $chk['iq_status'] === 9) {
    alert_goto('숨김 처리된 문의에는 답변할 수 없습니다.', '/admin/inquiries.php');
}

$esc = db_escape($answer);
if (!db_query("
    UPDATE tb_inquiry SET
        iq_answer = '{$esc}',
        iq_status = 3,
        iq_answered_at = NOW(),
        iq_updated_at = NOW()
    WHERE iq_idx = {$idx} AND iq_status <> 9
    LIMIT 1
")) {
    alert_goto('저장 중 오류가 발생했습니다.', '/admin/inquiry_view.php?idx=' . $idx);
}

header('Location: /admin/inquiry_view.php?idx=' . $idx);
exit;
