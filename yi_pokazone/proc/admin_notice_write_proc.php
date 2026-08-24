<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/notices.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_notice')) {
    alert_goto('공지 테이블이 없습니다.', '/admin/notices.php');
}

$allowed_cat = ['general', 'update', 'event', 'maintenance'];

$mode      = (string) ($_POST['mode'] ?? 'insert');
$idx       = (int) ($_POST['idx'] ?? 0);
$category  = trim((string) ($_POST['no_category'] ?? 'general'));
$no_title  = trim((string) ($_POST['no_title'] ?? ''));
$content   = (string) ($_POST['no_content'] ?? '');
$is_pinned = isset($_POST['no_is_pinned']) ? 1 : 0;

if (!in_array($category, $allowed_cat, true)) {
    alert_goto('카테고리가 올바르지 않습니다.', '/admin/notices.php');
}
if ($no_title === '' || mb_strlen($no_title) > 200) {
    alert_goto('제목을 1~200자 이내로 입력해 주세요.', '/admin/notices.php');
}
if (trim($content) === '') {
    alert_goto('내용을 입력해 주세요.', '/admin/notices.php');
}
if (mb_strlen($content) > 50000) {
    alert_goto('내용은 50,000자 이내로 작성해 주세요.', '/admin/notices.php');
}

$esc_cat     = db_escape($category);
$esc_title   = db_escape($no_title);
$esc_content = db_escape($content);

if ($mode === 'edit' && $idx > 0) {
    $rs = db_query("SELECT no_idx FROM tb_notice WHERE no_idx = {$idx} LIMIT 1");
    if (!db_assoc($rs)) {
        alert_goto('존재하지 않는 공지입니다.', '/admin/notices.php');
    }

    $sql = "
        UPDATE tb_notice SET
            no_category   = '{$esc_cat}',
            no_is_pinned  = {$is_pinned},
            no_title      = '{$esc_title}',
            no_content    = '{$esc_content}',
            no_updated_at = NOW()
        WHERE no_idx = {$idx}
    ";
    if (!db_query($sql)) {
        alert_goto('공지 수정 중 오류가 발생했습니다.', '/admin/notice_form.php?idx=' . $idx);
    }
    alert_goto('공지가 수정되었습니다.', '/admin/notices.php');
}

if ($mode === 'edit') {
    alert_goto('잘못된 요청입니다.', '/admin/notices.php');
}

$ad_idx = (int) $ad['ad_idx'];

if (notice_table_has_no_ad_idx()) {
    $sql = "
        INSERT INTO tb_notice (mb_idx, no_ad_idx, no_category, no_is_pinned, no_title, no_content, no_created_at, no_updated_at)
        VALUES (NULL, {$ad_idx}, '{$esc_cat}', {$is_pinned}, '{$esc_title}', '{$esc_content}', NOW(), NOW())
    ";
} else {
    $mb_idx = notice_author_mb_idx_for_admin();
    if ($mb_idx < 1) {
        alert_goto(
            '회원(FK)으로 공지를 달 수 없습니다. DB에 sql/migrate_tb_notice_admin_author.sql 을 적용하면 백오피스 계정만으로 등록할 수 있습니다.',
            '/admin/notice_form.php'
        );
    }
    $sql = "
        INSERT INTO tb_notice (mb_idx, no_category, no_is_pinned, no_title, no_content, no_created_at, no_updated_at)
        VALUES ({$mb_idx}, '{$esc_cat}', {$is_pinned}, '{$esc_title}', '{$esc_content}', NOW(), NOW())
    ";
}
if (!db_query($sql)) {
    alert_goto('공지 등록 중 오류가 발생했습니다.', '/admin/notice_form.php');
}
alert_goto('공지가 등록되었습니다.', '/admin/notices.php?p=1&st=1');
