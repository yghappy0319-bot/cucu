<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_hold.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('관리자 로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_trade')) {
    alert_goto('거래 테이블이 없습니다.', '/trade/admin/trades.php');
}

$idx    = (int) ($_POST['tr_idx'] ?? $_POST['idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$back   = '/trade/trade_view.php?idx=' . $idx;
if ($idx < 1) {
    alert_goto('잘못된 요청입니다.', '/trade/admin/trades.php');
}

$row = db_assoc(db_query("SELECT tr_idx, tr_status FROM tb_trade WHERE tr_idx = {$idx} LIMIT 1"));
if (!$row) {
    alert_goto('존재하지 않는 거래글입니다.', '/trade/admin/trades.php');
}

if (!trade_hold_columns_ready()) {
    alert_goto('게시중지 기능 DB가 없습니다. sql/migrate_tb_trade_hold.sql 을 적용해 주세요.', $back);
}

if ($action === 'hold') {
    if ((int) $row['tr_status'] === TRADE_STATUS_DELETED) {
        alert_goto('삭제(숨김)된 거래글은 게시중지할 수 없습니다.', $back);
    }
    $code   = trim((string) ($_POST['hold_code'] ?? ''));
    $custom = trim((string) ($_POST['hold_reason'] ?? ''));
    $presets = trade_hold_preset_reasons();
    if (!array_key_exists($code, $presets)) {
        alert_goto('게시중지 사유를 선택해 주세요.', $back);
    }
    if ($code === 'custom' && $custom === '') {
        alert_goto('직접 입력 사유를 작성해 주세요.', $back);
    }
    $reason = trade_hold_build_reason($code, $custom);
    if ($reason === '') {
        alert_goto('게시중지 사유를 입력해 주세요.', $back);
    }

    $esc_code   = db_escape($code);
    $esc_reason = db_escape($reason);
    $ad_idx     = (int) ($ad['ad_idx'] ?? 0);
    $hold_st    = TRADE_STATUS_HOLD;

    $ok = db_query("
        UPDATE tb_trade
        SET tr_status = {$hold_st},
            tr_hold_code = '{$esc_code}',
            tr_hold_reason = '{$esc_reason}',
            tr_hold_at = NOW(),
            tr_hold_ad_idx = " . ($ad_idx > 0 ? $ad_idx : 'NULL') . ",
            tr_updated_at = NOW()
        WHERE tr_idx = {$idx}
        LIMIT 1
    ");
    if (!$ok) {
        alert_goto('게시중지 처리 중 오류가 발생했습니다.', $back);
    }
    alert_goto('거래글을 게시중지했습니다.', $back);
}

if ($action === 'unhold') {
    if ((int) $row['tr_status'] !== TRADE_STATUS_HOLD) {
        alert_goto('게시중지 상태가 아닙니다.', $back);
    }
    $ok_st = TRADE_STATUS_OK;
    $ok = db_query("
        UPDATE tb_trade
        SET tr_status = {$ok_st},
            tr_hold_code = NULL,
            tr_hold_reason = NULL,
            tr_hold_at = NULL,
            tr_hold_ad_idx = NULL,
            tr_updated_at = NOW()
        WHERE tr_idx = {$idx}
        LIMIT 1
    ");
    if (!$ok) {
        alert_goto('게시중지 해제 중 오류가 발생했습니다.', $back);
    }
    alert_goto('게시중지를 해제했습니다.', $back);
}

alert_goto('잘못된 요청입니다.', $back);
