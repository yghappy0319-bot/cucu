<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';
require_once __DIR__ . '/../lib/_site_popup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/popups.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!site_popup_table_ready()) {
    alert_goto('팝업 테이블이 없습니다. sql/tb_popup.sql 을 적용해 주세요.', '/admin/popups.php');
}

$mode   = (string) ($_POST['mode'] ?? 'insert');
$idx    = (int) ($_POST['idx'] ?? 0);
$title  = trim((string) ($_POST['pu_title'] ?? ''));
$content_raw = (string) ($_POST['pu_content'] ?? '');
$content = site_popup_sanitize_content($content_raw);
$link   = trim((string) ($_POST['pu_link_url'] ?? ''));
$width  = (int) ($_POST['pu_width'] ?? 400);
$sort   = (int) ($_POST['pu_sort'] ?? 0);
$target = trim((string) ($_POST['pu_target'] ?? 'all'));
$hide_days = (int) ($_POST['pu_hide_days'] ?? 1);
$status = (int) ($_POST['pu_status'] ?? 1);
$start_raw = trim((string) ($_POST['pu_start_at'] ?? ''));
$end_raw   = trim((string) ($_POST['pu_end_at'] ?? ''));
$remove_image = isset($_POST['pu_image_remove']);

if ($title === '' || mb_strlen($title) > 100) {
    alert_goto('제목을 1~100자 이내로 입력해 주세요.', $mode === 'edit' && $idx > 0 ? '/admin/popup_form.php?idx=' . $idx : '/admin/popup_form.php');
}
if (!in_array($target, ['all', 'home'], true)) {
    alert_goto('노출 대상이 올바르지 않습니다.', '/admin/popups.php');
}
if ($status !== 1 && $status !== 9) {
    $status = 1;
}
if ($width < 240) {
    $width = 240;
}
if ($width > 900) {
    $width = 900;
}
if ($sort < 0) {
    $sort = 0;
}
if ($sort > 9999) {
    $sort = 9999;
}
if ($hide_days < 1) {
    $hide_days = 1;
}
if ($hide_days > 30) {
    $hide_days = 30;
}
if ($link !== '') {
    if (mb_strlen($link) > 500) {
        alert_goto('링크는 500자 이내로 입력해 주세요.', '/admin/popups.php');
    }
    if (!preg_match('#^https?://#i', $link) && !preg_match('#^/#', $link)) {
        alert_goto('링크는 http(s):// 또는 / 로 시작해 주세요.', '/admin/popups.php');
    }
}
if (mb_strlen($content) > 20000) {
    alert_goto('본문이 너무 깁니다.', '/admin/popups.php');
}

$start_sql = 'NULL';
$end_sql   = 'NULL';
if ($start_raw !== '') {
    $ts = strtotime(str_replace('T', ' ', $start_raw));
    if ($ts === false) {
        alert_goto('노출 시작 시각이 올바르지 않습니다.', '/admin/popups.php');
    }
    $start_sql = "'" . db_escape(date('Y-m-d H:i:s', $ts)) . "'";
}
if ($end_raw !== '') {
    $ts = strtotime(str_replace('T', ' ', $end_raw));
    if ($ts === false) {
        alert_goto('노출 종료 시각이 올바르지 않습니다.', '/admin/popups.php');
    }
    $end_sql = "'" . db_escape(date('Y-m-d H:i:s', $ts)) . "'";
}

$upload = popup_upload_image($_FILES['pu_image_file'] ?? null);
if (!$upload['ok']) {
    alert_goto($upload['error'] ?: '이미지 업로드에 실패했습니다.', $mode === 'edit' && $idx > 0 ? '/admin/popup_form.php?idx=' . $idx : '/admin/popup_form.php');
}
$new_image = (string) ($upload['path'] ?? '');

$esc_title   = db_escape($title);
$esc_content = db_escape($content);
$esc_link    = db_escape($link);
$esc_target  = db_escape($target);

if ($mode === 'edit' && $idx > 0) {
    $rs  = db_query("SELECT * FROM tb_popup WHERE pu_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 팝업입니다.', '/admin/popups.php');
    }

    $image_path = (string) ($row['pu_image'] ?? '');
    if ($remove_image) {
        $image_path = '';
    }
    if ($new_image !== '') {
        $image_path = $new_image;
    }

    if ($content === '' && $image_path === '') {
        alert_goto('본문 또는 이미지 중 하나 이상 필요합니다.', '/admin/popup_form.php?idx=' . $idx);
    }

    $sql_image = ($image_path === '' ? 'NULL' : "'" . db_escape($image_path) . "'");
    $sql_link  = ($link === '' ? 'NULL' : "'{$esc_link}'");
    $sql_content = ($content === '' ? 'NULL' : "'{$esc_content}'");

    $sql = "
        UPDATE tb_popup SET
            pu_title     = '{$esc_title}',
            pu_content   = {$sql_content},
            pu_image     = {$sql_image},
            pu_link_url  = {$sql_link},
            pu_width     = {$width},
            pu_target    = '{$esc_target}',
            pu_start_at  = {$start_sql},
            pu_end_at    = {$end_sql},
            pu_hide_days = {$hide_days},
            pu_sort      = {$sort},
            pu_status    = {$status},
            pu_updated_at = NOW()
        WHERE pu_idx = {$idx}
        LIMIT 1
    ";
    if (!db_query($sql)) {
        alert_goto('팝업 수정 중 오류가 발생했습니다.', '/admin/popup_form.php?idx=' . $idx);
    }
    alert_goto('팝업이 수정되었습니다.', '/admin/popups.php');
}

if ($mode === 'edit') {
    alert_goto('잘못된 요청입니다.', '/admin/popups.php');
}

if ($content === '' && $new_image === '') {
    alert_goto('본문 또는 이미지 중 하나 이상 필요합니다.', '/admin/popup_form.php');
}

$sql_image = ($new_image === '' ? 'NULL' : "'" . db_escape($new_image) . "'");
$sql_link  = ($link === '' ? 'NULL' : "'{$esc_link}'");
$sql_content = ($content === '' ? 'NULL' : "'{$esc_content}'");

$sql = "
    INSERT INTO tb_popup
        (pu_title, pu_content, pu_image, pu_link_url, pu_width, pu_target,
         pu_start_at, pu_end_at, pu_hide_days, pu_sort, pu_status, pu_created_at, pu_updated_at)
    VALUES
        ('{$esc_title}', {$sql_content}, {$sql_image}, {$sql_link}, {$width}, '{$esc_target}',
         {$start_sql}, {$end_sql}, {$hide_days}, {$sort}, {$status}, NOW(), NOW())
";
if (!db_query($sql)) {
    alert_goto('팝업 등록 중 오류가 발생했습니다.', '/admin/popup_form.php');
}
alert_goto('팝업이 등록되었습니다.', '/admin/popups.php?st=1');
