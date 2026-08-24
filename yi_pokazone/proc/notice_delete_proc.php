<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') alert_goto('잘못된 접근입니다.', '/page/notice.php');

$me = login_member();
if (!$me || (int)$me['mb_level'] < 9) alert_goto('관리자만 접근할 수 있습니다.', '/page/notice.php');

$idx = (int)($_POST['idx'] ?? 0);
if ($idx <= 0) alert_goto('잘못된 접근입니다.', '/page/notice.php');

$rs = db_query("SELECT no_idx FROM tb_notice WHERE no_idx = {$idx} AND no_status = 1 LIMIT 1");
if (!db_assoc($rs)) alert_goto('존재하지 않는 공지입니다.', '/page/notice.php');

if (!db_query("UPDATE tb_notice SET no_status = 9, no_updated_at = NOW() WHERE no_idx = {$idx}")) {
    alert_goto('삭제 중 오류가 발생했습니다.');
}
alert_goto('공지가 삭제되었습니다.', '/page/notice.php');
