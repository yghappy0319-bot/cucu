<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';

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
$action = trim((string) ($_POST['action'] ?? ''));
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/inquiries.php');
}

if ($action === 'status') {
    $st = (int) ($_POST['iq_status'] ?? 0);
    if (!in_array($st, [1, 2, 3, 9], true)) {
        alert_goto('상태 값이 올바르지 않습니다.', '/admin/inquiry_view.php?idx=' . $idx);
    }
    if (!db_query("UPDATE tb_inquiry SET iq_status = {$st}, iq_updated_at = NOW() WHERE iq_idx = {$idx} LIMIT 1")) {
        alert_goto('처리 중 오류가 발생했습니다.', '/admin/inquiry_view.php?idx=' . $idx);
    }
    header('Location: /admin/inquiry_view.php?idx=' . $idx);
    exit;
}

if ($action === 'hide') {
    if (!db_query("UPDATE tb_inquiry SET iq_status = 9, iq_updated_at = NOW() WHERE iq_idx = {$idx} LIMIT 1")) {
        alert_goto('처리 중 오류가 발생했습니다.', '/admin/inquiries.php');
    }
    alert_goto('문의를 숨김 처리했습니다.', '/admin/inquiries.php');
}

if ($action === 'delete') {
    $chk = db_assoc(db_query("SELECT iq_idx FROM tb_inquiry WHERE iq_idx = {$idx} LIMIT 1"));
    if (!$chk) {
        alert_goto('존재하지 않는 문의입니다.', '/admin/inquiries.php');
    }

    $file_paths = [];
    if (db_table_exists('tb_inquiry_file')) {
        $rs_f = db_query("SELECT if_path FROM tb_inquiry_file WHERE iq_idx = {$idx}");
        while ($f = db_assoc($rs_f)) {
            if (!empty($f['if_path'])) {
                $file_paths[] = (string) $f['if_path'];
            }
        }
        if (!db_query("DELETE FROM tb_inquiry_file WHERE iq_idx = {$idx}")) {
            alert_goto('첨부파일 삭제 중 오류가 발생했습니다.', '/admin/inquiry_view.php?idx=' . $idx);
        }
    }

    if (!db_query("DELETE FROM tb_inquiry WHERE iq_idx = {$idx} LIMIT 1")) {
        alert_goto('문의 삭제 중 오류가 발생했습니다.', '/admin/inquiry_view.php?idx=' . $idx);
    }

    if (!empty($file_paths)) {
        inquiry_remove_files($file_paths);
    }

    alert_goto('문의를 완전 삭제했습니다.', '/admin/inquiries.php');
}

alert_goto('잘못된 요청입니다.', '/admin/inquiries.php');
