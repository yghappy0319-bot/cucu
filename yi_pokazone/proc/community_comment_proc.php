<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/community.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인 후 댓글을 작성할 수 있습니다.', '/login.php');
}

if (!db_table_exists('tb_community_comment')) {
    alert_goto('댓글 기능이 아직 설정되지 않았습니다.', '/page/community.php');
}

$co_idx = (int) ($_POST['co_idx'] ?? 0);
$parent = (int) ($_POST['cc_parent_idx'] ?? 0);
$content = trim((string) ($_POST['cc_content'] ?? ''));
$return = trim((string) ($_POST['return'] ?? ''));

if ($co_idx < 1 || $content === '') {
    alert_goto('내용을 입력해 주세요.', '/page/community_view.php?idx=' . max(1, $co_idx));
}

if (mb_strlen($content) > 2000) {
    alert_goto('댓글은 2,000자 이내로 작성해 주세요.', '/page/community_view.php?idx=' . $co_idx);
}

$rs = db_query("SELECT co_idx, co_status FROM tb_community WHERE co_idx = {$co_idx} LIMIT 1");
$co = db_assoc($rs);
if (!$co || (int) $co['co_status'] !== 1) {
    alert_goto('글을 찾을 수 없습니다.', '/page/community.php');
}

$parent_sql = 'NULL';
if ($parent > 0) {
    $pr = db_assoc(db_query("
        SELECT cc_idx, co_idx, cc_parent_idx, cc_status
        FROM tb_community_comment
        WHERE cc_idx = {$parent} LIMIT 1
    "));
    if (!$pr || (int) $pr['co_idx'] !== $co_idx || (int) $pr['cc_status'] !== 1) {
        alert_goto('대댓글을 달 수 없습니다.', '/page/community_view.php?idx=' . $co_idx);
    }
    if ($pr['cc_parent_idx'] !== null && (int) $pr['cc_parent_idx'] > 0) {
        alert_goto('대댓글에는 또 답글을 달 수 없습니다.', '/page/community_view.php?idx=' . $co_idx);
    }
    $parent_sql = (string) (int) $pr['cc_idx'];
}

$mb_idx = (int) $me['mb_idx'];
$ip     = db_escape(get_client_ip());
$esc    = db_escape($content);

$sql = "
    INSERT INTO tb_community_comment (co_idx, mb_idx, cc_parent_idx, cc_content, cc_ip)
    VALUES ({$co_idx}, {$mb_idx}, {$parent_sql}, '{$esc}', '{$ip}')
";
if (!db_query($sql)) {
    alert_goto('등록 중 오류가 발생했습니다.', '/page/community_view.php?idx=' . $co_idx);
}

community_sync_counts($co_idx);

if ($return !== '' && preg_match('#^/[a-zA-Z0-9_./?=&\-#]*$#', $return)) {
    header('Location: ' . $return . '#comments');
    exit;
}

header('Location: /page/community_view.php?idx=' . $co_idx . '#comments');
exit;
