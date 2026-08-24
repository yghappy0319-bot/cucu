<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/community.php');
}

$me = login_member();
$ad   = login_admin();

if (!db_table_exists('tb_community_comment')) {
    alert_goto('댓글 기능이 설정되지 않았습니다.', '/page/community.php');
}

$cc_idx = (int) ($_POST['cc_idx'] ?? 0);
$return = trim((string) ($_POST['return'] ?? ''));
if ($cc_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/page/community.php');
}

$row = db_assoc(db_query("
    SELECT c.cc_idx, c.co_idx, c.mb_idx, c.cc_parent_idx, c.cc_status, p.co_status AS post_status
    FROM tb_community_comment c
    INNER JOIN tb_community p ON p.co_idx = c.co_idx
    WHERE c.cc_idx = {$cc_idx}
    LIMIT 1
"));
if (!$row || (int) $row['cc_status'] !== 1) {
    alert_goto('이미 삭제되었거나 없는 댓글입니다.', '/page/community.php');
}

$is_owner = $me && (int) $me['mb_idx'] === (int) $row['mb_idx'];
$is_mod   = $me && (int) $me['mb_level'] >= 9;
if (!$is_owner && !$is_mod && !$ad) {
    alert_goto('삭제 권한이 없습니다.', '/page/community_view.php?idx=' . (int) $row['co_idx']);
}

$co_idx = (int) $row['co_idx'];

if ($row['cc_parent_idx'] === null || (int) $row['cc_parent_idx'] === 0) {
    db_query("
        UPDATE tb_community_comment
        SET cc_status = 9,
            cc_content = '삭제된 댓글입니다.',
            cc_updated_at = NOW()
        WHERE co_idx = {$co_idx}
          AND (cc_idx = {$cc_idx} OR cc_parent_idx = {$cc_idx})
          AND cc_status = 1
    ");
} else {
    db_query("
        UPDATE tb_community_comment
        SET cc_status = 9,
            cc_content = '삭제된 댓글입니다.',
            cc_updated_at = NOW()
        WHERE cc_idx = {$cc_idx} AND co_idx = {$co_idx} LIMIT 1
    ");
}

community_sync_counts($co_idx);

$redir = '/page/community_view.php?idx=' . $co_idx . '#comments';
if ($return !== '' && preg_match('#^/[a-zA-Z0-9_./?=&\-#]*$#', $return)) {
    $redir = $return . (strpos($return, '#') !== false ? '' : '#comments');
}

header('Location: ' . $redir);
exit;
