<?php
require_once __DIR__ . '/../../lib/_function.php';

function trade_like_wants_json(): bool
{
    if (!empty($_POST['ajax']) || !empty($_GET['ajax'])) {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }

    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
}

/** @param array<string, mixed> $data */
function trade_like_json_exit(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (trade_like_wants_json()) {
        trade_like_json_exit(['ok' => false, 'message' => '잘못된 접근입니다.'], 405);
    }
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$wants_json = trade_like_wants_json();

$me = login_member();
if (!$me) {
    if ($wants_json) {
        trade_like_json_exit(['ok' => false, 'message' => '로그인 후 이용해 주세요.', 'login' => true], 401);
    }
    alert_goto('로그인 후 이용해 주세요.', '/login.php');
}

if (!db_table_exists('tb_trade_like')) {
    if ($wants_json) {
        trade_like_json_exit(['ok' => false, 'message' => '찜 기능이 아직 설정되지 않았습니다.'], 503);
    }
    alert_goto('찜 기능이 아직 설정되지 않았습니다. 관리자에게 문의해 주세요.', '/trade/trade.php');
}

$tr_idx = (int) ($_POST['tr_idx'] ?? 0);
$return = trim((string) ($_POST['return'] ?? ''));
if ($tr_idx < 1) {
    if ($wants_json) {
        trade_like_json_exit(['ok' => false, 'message' => '잘못된 요청입니다.'], 400);
    }
    alert_goto('잘못된 요청입니다.', '/trade/trade.php');
}

$mb_idx = (int) $me['mb_idx'];

$rs = db_query("SELECT tr_idx, tr_status, tr_deal_status FROM tb_trade WHERE tr_idx = {$tr_idx} LIMIT 1");
$tr = db_assoc($rs);
if (!$tr || (int) $tr['tr_status'] !== 1) {
    if ($wants_json) {
        trade_like_json_exit(['ok' => false, 'message' => '거래글을 찾을 수 없습니다.'], 404);
    }
    alert_goto('거래글을 찾을 수 없습니다.', '/trade/trade.php');
}

$exists = db_assoc(db_query("
    SELECT 1 FROM tb_trade_like WHERE tr_idx = {$tr_idx} AND mb_idx = {$mb_idx} LIMIT 1
"));

if (!$exists && (int) $tr['tr_deal_status'] === 3) {
    if ($wants_json) {
        trade_like_json_exit(['ok' => false, 'message' => '거래완료된 상품은 찜할 수 없습니다.'], 403);
    }
    alert_goto('거래완료된 상품은 찜할 수 없습니다.', '/trade/trade_view.php?idx=' . $tr_idx);
}

global $conn;
mysqli_begin_transaction($conn);

if ($exists) {
    if (!db_query("DELETE FROM tb_trade_like WHERE tr_idx = {$tr_idx} AND mb_idx = {$mb_idx} LIMIT 1")) {
        mysqli_rollback($conn);
        if ($wants_json) {
            trade_like_json_exit(['ok' => false, 'message' => '처리 중 오류가 발생했습니다.'], 500);
        }
        alert_goto('처리 중 오류가 발생했습니다.', '/trade/trade_view.php?idx=' . $tr_idx);
    }
    $wished = false;
} else {
    if (!db_query("INSERT INTO tb_trade_like (tr_idx, mb_idx) VALUES ({$tr_idx}, {$mb_idx})")) {
        mysqli_rollback($conn);
        if ($wants_json) {
            trade_like_json_exit(['ok' => false, 'message' => '처리 중 오류가 발생했습니다.'], 500);
        }
        alert_goto('처리 중 오류가 발생했습니다.', '/trade/trade_view.php?idx=' . $tr_idx);
    }
    $wished = true;
}

mysqli_commit($conn);
trade_sync_likes_count($tr_idx);
$count = (int) db_result("SELECT tr_likes FROM tb_trade WHERE tr_idx = {$tr_idx} LIMIT 1");

if ($wants_json) {
    trade_like_json_exit([
        'ok'     => true,
        'wished' => $wished,
        'count'  => $count,
    ]);
}

if ($return !== '' && preg_match('#^/[a-zA-Z0-9_./?=&\-#%]*$#', $return)) {
    header('Location: ' . $return);
    exit;
}

header('Location: /trade/trade_view.php?idx=' . $tr_idx);
exit;
