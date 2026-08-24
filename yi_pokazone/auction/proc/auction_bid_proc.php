<?php
// FormData·XHR 입찰 — _function.php HTML 헤더보다 먼저 JSON 모드
$__pz_auction_ajax = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (
        (!empty($_POST['ajax']) && (string) $_POST['ajax'] === '1')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    );
if ($__pz_auction_ajax) {
    define('PZ_API_JSON', true);
}

require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_auction.php';

$is_ajax = auction_request_is_ajax();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auction_bid_exit('잘못된 접근입니다.', '/auction/auction.php', $is_ajax);
}

if (!auction_table_ok() || !auction_bid_table_ok()) {
    auction_bid_exit('입찰 기능 DB가 없습니다. sql/tb_auction.sql 을 실행해 주세요.', '/auction/auction.php', $is_ajax);
}

$me = login_member();
if (!$me) {
    $return_login = '/login.php?return=' . urlencode('/auction/auction_view.php?idx=' . (int) ($_POST['au_idx'] ?? 0));
    auction_bid_exit('로그인 후 입찰할 수 있습니다.', '/login.php', $is_ajax, false, 0, null, false, $return_login);
}

$au_idx = (int) ($_POST['au_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? 'bid'));
$return = '/auction/auction_view.php?idx=' . $au_idx;

if ($au_idx < 1) {
    auction_bid_exit('잘못된 요청입니다.', '/auction/auction.php', $is_ajax);
}

$mb_idx = (int) $me['mb_idx'];
$ip     = db_escape(get_client_ip());

global $conn;

if (!mysqli_begin_transaction($conn)) {
    auction_bid_exit('입찰 처리를 시작하지 못했습니다.', $return, $is_ajax);
}

$rs  = db_query("
    SELECT *
    FROM tb_auction
    WHERE au_idx = {$au_idx} AND au_status = 1
    LIMIT 1
    FOR UPDATE
");
$row = db_assoc($rs);

if (!$row) {
    mysqli_rollback($conn);
    auction_bid_exit('경매를 찾을 수 없습니다.', '/auction/auction.php', $is_ajax);
}

$can = auction_can_bid($row, $me);
if (!$can['ok']) {
    mysqli_rollback($conn);
    if ($can['reason'] === 'login') {
        $return_login = '/login.php?return=' . urlencode($return);
        auction_bid_exit('로그인 후 입찰할 수 있습니다.', '/login.php', $is_ajax, false, 0, null, false, $return_login);
    }
    auction_bid_exit((string) $can['reason'], $return, $is_ajax);
}

$buy_now = (int) ($row['au_buy_now_price'] ?? 0);
$min_bid = auction_min_bid_amount($row);

if ($action === 'buy_now') {
    if ($buy_now <= 0) {
        mysqli_rollback($conn);
        auction_bid_exit('즉시구매가가 설정되지 않은 경매입니다.', $return, $is_ajax);
    }
    $amount = $buy_now;
} else {
    $amount = (int) ($_POST['ab_amount'] ?? 0);
    if ($amount < $min_bid) {
        mysqli_rollback($conn);
        auction_bid_exit('입찰가는 최소 ' . number_format($min_bid) . '원 이상이어야 합니다.', $return, $is_ajax);
    }
    if ($amount > 999999999) {
        mysqli_rollback($conn);
        auction_bid_exit('입찰가는 9억 원 이하로 입력해 주세요.', $return, $is_ajax);
    }
}

if (!member_cash_column_ready()) {
    mysqli_rollback($conn);
    auction_bid_exit('캐시 기능이 준비되지 않았습니다. 잠시 후 다시 시도해 주세요.', $return, $is_ajax);
}
$cash_balance = member_cash_balance($mb_idx);
if ($cash_balance < 1) {
    mysqli_rollback($conn);
    auction_bid_exit('보유 캐시가 있어야 입찰할 수 있습니다.', $return, $is_ajax);
}
if ($cash_balance < $amount) {
    mysqli_rollback($conn);
    auction_bid_exit(
        '캐시 잔액이 부족합니다. (보유 ₩' . number_format($cash_balance)
        . ' / 입찰 ₩' . number_format($amount) . ')',
        $return,
        $is_ajax
    );
}

$finalize = ($buy_now > 0 && $amount >= $buy_now);

if (!$finalize && $buy_now > 0 && $amount > $buy_now) {
    mysqli_rollback($conn);
    auction_bid_exit(
        '즉시구매가(' . number_format($buy_now) . '원) 이하로 입찰해 주세요. 즉시구매는 별도 버튼을 이용해 주세요.',
        $return,
        $is_ajax
    );
}

$prev_top = auction_previous_top_bid($au_idx);

$sql_bid = "
    INSERT INTO tb_auction_bid (au_idx, mb_idx, ab_amount, ab_ip, ab_created_at)
    VALUES ({$au_idx}, {$mb_idx}, {$amount}, '{$ip}', NOW())
";
if (!db_query($sql_bid)) {
    mysqli_rollback($conn);
    $err = function_exists('db_last_error') ? db_last_error() : '';
    auction_bid_exit(
        '입찰 저장 중 오류가 발생했습니다.' . ($err !== '' ? "\n\n(DB: {$err})" : ''),
        $return,
        $is_ajax
    );
}

if ($finalize) {
    $sql_up = "
        UPDATE tb_auction SET
            au_current_price  = {$amount},
            au_bid_count      = au_bid_count + 1,
            au_auction_status = 3,
            au_winner_mb_idx  = {$mb_idx},
            au_ends_at        = NOW(),
            au_updated_at     = NOW()
        WHERE au_idx = {$au_idx}
          AND au_status = 1
    ";
} else {
    $sql_up = "
        UPDATE tb_auction SET
            au_current_price = {$amount},
            au_bid_count     = au_bid_count + 1,
            au_updated_at    = NOW()
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_auction_status = 1
          AND au_ends_at > NOW()
    ";
}

if (!db_query($sql_up) || mysqli_affected_rows($conn) !== 1) {
    mysqli_rollback($conn);
    auction_bid_exit('경매 정보 갱신에 실패했습니다. 마감되었거나 상태가 변경되었을 수 있습니다.', $return, $is_ajax);
}

mysqli_commit($conn);

if ($finalize) {
    auction_notify_winner(
        $au_idx,
        $mb_idx,
        (string) ($row['au_title'] ?? ''),
        $amount
    );
}

if (
    $prev_top !== null
    && (int) $prev_top['mb_idx'] > 0
    && (int) $prev_top['mb_idx'] !== $mb_idx
    && $amount > (int) $prev_top['ab_amount']
) {
    require_once __DIR__ . '/../../lib/webpush.php';
    webpush_notify_auction_outbid(
        (int) $prev_top['mb_idx'],
        (string) ($row['au_title'] ?? ''),
        $amount,
        $au_idx
    );
}

if ($finalize) {
    auction_bid_exit(
        '즉시구매가로 낙찰되었습니다. 판매자와 택배 거래를 진행해 주세요.',
        $return,
        $is_ajax,
        true,
        $au_idx,
        $me,
        true
    );
}

auction_bid_exit(
    number_format($amount) . '원에 입찰했습니다.',
    $return,
    $is_ajax,
    true,
    $au_idx,
    $me,
    false
);
