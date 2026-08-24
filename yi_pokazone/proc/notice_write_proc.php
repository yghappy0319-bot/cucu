<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') alert_goto('잘못된 접근입니다.', '/page/notice.php');

$me = login_member();
if (!$me || (int)$me['mb_level'] < 9) alert_goto('관리자만 접근할 수 있습니다.', '/page/notice.php');

$allowed_cat = ['general', 'update', 'event', 'maintenance'];

$mode        = $_POST['mode']         ?? 'insert';
$idx         = (int)($_POST['idx']    ?? 0);
$category    = trim($_POST['no_category']  ?? 'general');
$title       = trim($_POST['no_title']     ?? '');
$content     = (string)($_POST['no_content'] ?? '');
$is_pinned   = isset($_POST['no_is_pinned']) ? 1 : 0;

if (!in_array($category, $allowed_cat, true))       alert_goto('카테고리가 올바르지 않습니다.');
if ($title === '' || mb_strlen($title) > 200)       alert_goto('제목을 1~200자 이내로 입력해 주세요.');
if (trim($content) === '')                          alert_goto('내용을 입력해 주세요.');
if (mb_strlen($content) > 50000)                    alert_goto('내용은 50,000자 이내로 작성해 주세요.');

$esc_cat     = db_escape($category);
$esc_title   = db_escape($title);
$esc_content = db_escape($content);
$mb_idx      = (int)$me['mb_idx'];

if ($mode === 'edit' && $idx > 0) {
    $rs = db_query("SELECT no_idx FROM tb_notice WHERE no_idx = {$idx} AND no_status = 1 LIMIT 1");
    if (!db_assoc($rs)) alert_goto('존재하지 않는 공지입니다.', '/page/notice.php');

    $sql = "
        UPDATE tb_notice SET
            no_category   = '{$esc_cat}',
            no_is_pinned  = {$is_pinned},
            no_title      = '{$esc_title}',
            no_content    = '{$esc_content}',
            no_updated_at = NOW()
        WHERE no_idx = {$idx} AND no_status = 1
    ";
    if (!db_query($sql)) alert_goto('공지 수정 중 오류가 발생했습니다.');
    alert_goto('공지가 수정되었습니다.', '/page/notice_view.php?idx=' . $idx);
} else {
    $sql = "
        INSERT INTO tb_notice (mb_idx, no_category, no_is_pinned, no_title, no_content, no_created_at, no_updated_at)
        VALUES ({$mb_idx}, '{$esc_cat}', {$is_pinned}, '{$esc_title}', '{$esc_content}', NOW(), NOW())
    ";
    if (!db_query($sql)) alert_goto('공지 등록 중 오류가 발생했습니다.');
    $new_idx = (int)db_insert_id();
    alert_goto('공지가 등록되었습니다.', '/page/notice_view.php?idx=' . $new_idx);
}
