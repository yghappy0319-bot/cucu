<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/../lib/_community_meta.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/community.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$allowed_cat = array_keys(community_writable_categories());

$mode        = $_POST['mode']        ?? 'insert';
$idx         = (int)($_POST['idx']   ?? 0);
$co_category = trim($_POST['co_category'] ?? '');
$co_title    = trim($_POST['co_title']    ?? '');
$co_content  = (string)($_POST['co_content'] ?? '');
$co_content  = community_sanitize_html($co_content);

// 유효성 검사
if (!in_array($co_category, $allowed_cat, true)) {
    alert_goto('분류를 선택해 주세요.');
}
if ($co_title === '' || mb_strlen($co_title) > 150) {
    alert_goto('제목을 1~150자 이내로 입력해 주세요.');
}
if (community_editor_is_effectively_empty($co_content)) {
    alert_goto('내용을 입력해 주세요.');
}
if (mb_strlen($co_content) > 200000) {
    alert_goto('내용이 너무 깁니다. 이미지·서식을 줄여 주세요.');
}

$esc_cat     = db_escape($co_category);
$esc_title   = db_escape($co_title);
$esc_content = db_escape($co_content);
$ip          = db_escape(get_client_ip());
$mb_idx      = (int)$me['mb_idx'];

if ($mode === 'edit' && $idx > 0) {
    // 수정
    $rs  = db_query("SELECT mb_idx FROM tb_community WHERE co_idx = {$idx} AND co_status = 1 LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않거나 삭제된 게시글입니다.', '/page/community.php');
    }
    if ((int)$row['mb_idx'] !== $mb_idx && (int)$me['mb_level'] < 9) {
        alert_goto('수정 권한이 없습니다.', '/page/community_view.php?idx=' . $idx);
    }

    $sql = "
        UPDATE tb_community SET
            co_category = '{$esc_cat}',
            co_title    = '{$esc_title}',
            co_content  = '{$esc_content}',
            co_updated_at = NOW()
        WHERE co_idx = {$idx}
          AND co_status = 1
    ";
    $ok = db_query($sql);

    if (!$ok) {
        alert_goto('게시글 수정 중 오류가 발생했습니다.');
    }
    alert_goto('게시글이 수정되었습니다.', '/page/community_view.php?idx=' . $idx);
} else {
    // 등록
    $source_cols = '';
    $source_vals = '';
    if (community_has_external_columns()) {
        $esc_source = db_escape(COMMUNITY_SOURCE_POKAZONE);
        $source_cols = ', co_source';
        $source_vals = ", '{$esc_source}'";
    }
    $sql = "
        INSERT INTO tb_community
            (mb_idx, co_category, co_title, co_content, co_ip, co_created_at, co_updated_at{$source_cols})
        VALUES
            ({$mb_idx}, '{$esc_cat}', '{$esc_title}', '{$esc_content}', '{$ip}', NOW(), NOW(){$source_vals})
    ";
    $ok = db_query($sql);

    if (!$ok) {
        alert_goto('게시글 등록 중 오류가 발생했습니다.');
    }
    $new_idx = (int) db_insert_id();
    $rewarded = point_reward_try_community_new_post($mb_idx);
    $msg = '게시글이 등록되었습니다.';
    if ($rewarded) {
        $msg .= ' ' . number_format(point_reward_community_new_post()) . 'P가 지급되었습니다.';
    }
    alert_goto($msg, '/page/community_view.php?idx=' . $new_idx);
}
