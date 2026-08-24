<?php
require_once __DIR__ . '/../../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$idx = (int)($_POST['idx'] ?? 0);
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/trade.php');
}

$rs  = db_query("SELECT mb_idx FROM tb_trade WHERE tr_idx = {$idx} AND tr_status = 1 LIMIT 1");
$row = db_assoc($rs);

if (!$row) {
    alert_goto('이미 삭제되었거나 존재하지 않는 거래글입니다.', '/trade/trade.php');
}

if ((int)$row['mb_idx'] !== (int)$me['mb_idx'] && (int)$me['mb_level'] < 9) {
    alert_goto('삭제 권한이 없습니다.', '/trade/trade_view.php?idx=' . $idx);
}

if (!db_query("UPDATE tb_trade SET tr_status = 9, tr_updated_at = NOW() WHERE tr_idx = {$idx}")) {
    alert_goto('삭제 처리 중 오류가 발생했습니다.', '/trade/trade_view.php?idx=' . $idx);
}

alert_goto('거래글이 삭제되었습니다.', '/trade/trade.php');
