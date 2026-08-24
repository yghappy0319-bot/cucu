<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/community.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$idx = (int)($_POST['idx'] ?? 0);
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/page/community.php');
}

$rs  = db_query("SELECT mb_idx FROM tb_community WHERE co_idx = {$idx} AND co_status = 1 LIMIT 1");
$row = db_assoc($rs);

if (!$row) {
    alert_goto('이미 삭제되었거나 존재하지 않는 게시글입니다.', '/page/community.php');
}

if ((int)$row['mb_idx'] !== (int)$me['mb_idx'] && (int)$me['mb_level'] < 9) {
    alert_goto('삭제 권한이 없습니다.', '/page/community_view.php?idx=' . $idx);
}

// 소프트 삭제
$ok = db_query("UPDATE tb_community SET co_status = 9, co_updated_at = NOW() WHERE co_idx = {$idx}");

if (!$ok) {
    alert_goto('삭제 처리 중 오류가 발생했습니다.', '/page/community_view.php?idx=' . $idx);
}

alert_goto('게시글이 삭제되었습니다.', '/page/community.php');
