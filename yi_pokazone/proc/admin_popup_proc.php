<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_site_popup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/popups.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!site_popup_table_ready()) {
    alert_goto('팝업 테이블이 없습니다.', '/admin/popups.php');
}

$idx    = (int) ($_POST['pu_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));

if ($idx < 1 || !in_array($action, ['show', 'hide', 'delete'], true)) {
    alert_goto('잘못된 요청입니다.', '/admin/popups.php');
}

$rs  = db_query("SELECT * FROM tb_popup WHERE pu_idx = {$idx} LIMIT 1");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('존재하지 않는 팝업입니다.', '/admin/popups.php');
}

if ($action === 'delete') {
    $img = trim((string) ($row['pu_image'] ?? ''));
    if (!db_query("DELETE FROM tb_popup WHERE pu_idx = {$idx} LIMIT 1")) {
        alert_goto('삭제 중 오류가 발생했습니다.', '/admin/popups.php');
    }
    // 업로드 이미지 파일 정리 (로컬 경로만)
    if ($img !== '' && strpos($img, '/uploads/popup/') === 0) {
        $web_root = realpath(__DIR__ . '/..');
        if ($web_root !== false) {
            $abs = $web_root . $img;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }
    alert_goto('팝업을 삭제했습니다.', '/admin/popups.php');
}

$status = $action === 'show' ? 1 : 9;
if (!db_query("UPDATE tb_popup SET pu_status = {$status}, pu_updated_at = NOW() WHERE pu_idx = {$idx} LIMIT 1")) {
    alert_goto('처리 중 오류가 발생했습니다.', '/admin/popups.php');
}

alert_goto($action === 'show' ? '팝업을 노출했습니다.' : '팝업을 숨김 처리했습니다.', '/admin/popups.php');
