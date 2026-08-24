<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/admin/trades.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_trade')) {
    alert_goto('거래 테이블이 없습니다.', '/trade/admin/trades.php');
}

$idx    = (int) ($_POST['tr_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/admin/trades.php');
}

if (in_array($action, ['hide', 'show'], true)) {
    $st = $action === 'hide' ? 9 : 1;
    $hold_sql = '';
    if ($action === 'show' && function_exists('trade_hold_columns_ready') && trade_hold_columns_ready()) {
        $hold_sql = ', tr_hold_code = NULL, tr_hold_reason = NULL, tr_hold_at = NULL, tr_hold_ad_idx = NULL';
    }
    if (!db_query("UPDATE tb_trade SET tr_status = {$st}{$hold_sql}, tr_updated_at = NOW() WHERE tr_idx = {$idx} LIMIT 1")) {
        alert_goto('처리 중 오류가 발생했습니다.', '/trade/admin/trades.php');
    }
    alert_goto($action === 'hide' ? '거래글을 숨김 처리했습니다.' : '거래글을 다시 노출했습니다.', '/trade/admin/trades.php');
}

if ($action === 'deal_status') {
    $ds = (int) ($_POST['tr_deal_status'] ?? 0);
    if (!in_array($ds, [1, 2, 3], true)) {
        alert_goto('거래 상태가 올바르지 않습니다.', '/trade/admin/trades.php');
    }
    if (!db_query("UPDATE tb_trade SET tr_deal_status = {$ds}, tr_updated_at = NOW() WHERE tr_idx = {$idx} AND tr_status = 1 LIMIT 1")) {
        alert_goto('처리 중 오류가 발생했습니다.', '/trade/admin/trades.php');
    }
    alert_goto('거래 상태를 변경했습니다.', '/trade/admin/trades.php');
}

if ($action === 'delete') {
    $chk = db_assoc(db_query("SELECT tr_idx FROM tb_trade WHERE tr_idx = {$idx} LIMIT 1"));
    if (!$chk) {
        alert_goto('존재하지 않는 거래글입니다.', '/trade/admin/trades.php');
    }
    if (db_table_exists('tb_trade_image')) {
        $rs_img = db_query("SELECT ti_path FROM tb_trade_image WHERE tr_idx = {$idx}");
        $paths  = [];
        while ($r = db_assoc($rs_img)) {
            $paths[] = $r['ti_path'];
        }
        if (!empty($paths)) {
            trade_remove_files($paths);
        }
    }
    if (!db_query("DELETE FROM tb_trade WHERE tr_idx = {$idx} LIMIT 1")) {
        alert_goto('삭제 처리 중 오류가 발생했습니다.', '/trade/admin/trades.php');
    }
    alert_goto('거래글을 삭제했습니다.', '/trade/admin/trades.php');
}

alert_goto('잘못된 요청입니다.', '/trade/admin/trades.php');
