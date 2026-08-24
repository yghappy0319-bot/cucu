<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/community.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인 후 이용해 주세요.', '/login.php');
}

if (!db_table_exists('tb_community_like')) {
    alert_goto('좋아요 기능이 아직 설정되지 않았습니다. 관리자에게 문의해 주세요.', '/page/community.php');
}

$co_idx = (int) ($_POST['co_idx'] ?? 0);
$return = trim((string) ($_POST['return'] ?? ''));
if ($co_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/page/community.php');
}

$mb_idx = (int) $me['mb_idx'];

$rs = db_query("SELECT co_idx, co_status FROM tb_community WHERE co_idx = {$co_idx} LIMIT 1");
$co = db_assoc($rs);
if (!$co || (int) $co['co_status'] !== 1) {
    alert_goto('글을 찾을 수 없습니다.', '/page/community.php');
}

$exists = db_assoc(db_query("
    SELECT 1 FROM tb_community_like WHERE co_idx = {$co_idx} AND mb_idx = {$mb_idx} LIMIT 1
"));

global $conn;
mysqli_begin_transaction($conn);

if ($exists) {
    if (!db_query("DELETE FROM tb_community_like WHERE co_idx = {$co_idx} AND mb_idx = {$mb_idx} LIMIT 1")) {
        mysqli_rollback($conn);
        alert_goto('처리 중 오류가 발생했습니다.', '/page/community_view.php?idx=' . $co_idx);
    }
} else {
    if (!db_query("INSERT INTO tb_community_like (co_idx, mb_idx) VALUES ({$co_idx}, {$mb_idx})")) {
        mysqli_rollback($conn);
        alert_goto('처리 중 오류가 발생했습니다.', '/page/community_view.php?idx=' . $co_idx);
    }
}

mysqli_commit($conn);
community_sync_counts($co_idx);

if ($return !== '' && preg_match('#^/[a-zA-Z0-9_./?=&\-#]*$#', $return)) {
    header('Location: ' . $return);
    exit;
}

header('Location: /page/community_view.php?idx=' . $co_idx);
exit;
