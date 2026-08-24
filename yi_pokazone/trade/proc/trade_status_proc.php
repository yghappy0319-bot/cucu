<?php
require_once __DIR__ . '/../../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php');
}

$idx    = (int)($_POST['idx']    ?? 0);
$status = (int)($_POST['status'] ?? 0);

if ($idx < 1 || !in_array($status, [1, 2, 3], true)) {
    alert_goto('잘못된 요청입니다.', '/trade/trade.php');
}

$rs  = db_query("SELECT mb_idx FROM tb_trade WHERE tr_idx = {$idx} AND tr_status = 1 LIMIT 1");
$row = db_assoc($rs);

if (!$row) {
    alert_goto('존재하지 않거나 삭제된 거래글입니다.', '/trade/trade.php');
}

if ((int)$row['mb_idx'] !== (int)$me['mb_idx'] && (int)$me['mb_level'] < 9) {
    alert_goto('권한이 없습니다.', '/trade/trade_view.php?idx=' . $idx);
}

if (!db_query("UPDATE tb_trade SET tr_deal_status = {$status}, tr_updated_at = NOW() WHERE tr_idx = {$idx}")) {
    alert_goto('상태 변경 중 오류가 발생했습니다.', '/trade/trade_view.php?idx=' . $idx);
}

$msg = [
    1 => '판매중으로 변경되었습니다.',
    2 => '거래중으로 변경되었습니다.',
    3 => '거래완료 처리되었습니다.',
][$status];

alert_goto($msg, '/trade/trade_view.php?idx=' . $idx);
