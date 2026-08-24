<?php

require_once __DIR__ . '/../../lib/_member_cash.php';
require_once __DIR__ . '/_member_auction_ban.php';

function auction_order_table_ready(): bool
{
    return db_table_exists('tb_auction_order');
}

function auction_order_message_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!auction_order_table_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_addr_message'"));

    return $ready;
}

const AUCTION_ORDER_COURIER_MAX  = 50;
const AUCTION_ORDER_TRACKING_MAX = 50;
/** 구매확정 후 환불 가능 기간(일) */
const AUCTION_REFUND_PERIOD_DAYS = 7;

/** 플랫폼 수수료(구매확정 정산 시 %) — 사이트 설정 연동 */
if (!defined('AUCTION_PLATFORM_FEE_PERCENT')) {
    define(
        'AUCTION_PLATFORM_FEE_PERCENT',
        function_exists('platform_fee_auction_percent') ? platform_fee_auction_percent() : 5
    );
}

function auction_order_tracking_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!auction_order_table_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_tracking_no'"));

    return $ready;
}

/**
 * @return list<string>
 */
function auction_courier_presets(): array
{
    return [
        'CJ대한통운',
        '한진택배',
        '롯데택배',
        '우체국택배',
        '로젠택배',
        '경동택배',
        '대신택배',
        '일양로지스',
    ];
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_has_tracking(array $order): bool
{
    return trim((string) ($order['ao_courier_name'] ?? '')) !== ''
        && trim((string) ($order['ao_tracking_no'] ?? '')) !== '';
}

/**
 * 주문 시 입력한 배송 메시지 (배송지 주소록과 별도)
 *
 * @return array{ok: bool, error?: string, message?: string}
 */
function auction_order_parse_message(array $input): array
{
    if (!auction_order_message_column_ready()) {
        return ['ok' => true, 'message' => ''];
    }

    require_once __DIR__ . '/../../lib/_member_address.php';

    $message = trim((string) ($input['ao_addr_message'] ?? ''));
    if (mb_strlen($message) > MEMBER_ADDRESS_MESSAGE_MAX) {
        return [
            'ok'    => false,
            'error' => '배송 메시지는 ' . MEMBER_ADDRESS_MESSAGE_MAX . '자 이내로 입력해 주세요.',
        ];
    }

    return ['ok' => true, 'message' => $message];
}

function auction_order_get_by_auction(int $au_idx): ?array
{
    if (!auction_order_table_ready() || $au_idx < 1) {
        return null;
    }

    $row = db_assoc(db_query("
        SELECT *
        FROM tb_auction_order
        WHERE au_idx = {$au_idx}
        LIMIT 1
    "));

    return $row ?: null;
}

function auction_order_is_paid(?array $order): bool
{
    return $order !== null && (int) ($order['ao_pay_status'] ?? 0) === 1;
}

/**
 * @param array<string, mixed>|null $order
 */
function auction_order_is_cancelled(?array $order): bool
{
    return $order !== null && (int) ($order['ao_order_status'] ?? 0) === 9;
}

function auction_order_confirm_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!auction_order_table_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_buyer_confirmed_at'"));

    return $ready;
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_is_confirmed(array $order): bool
{
    if (!auction_order_confirm_column_ready()) {
        return false;
    }

    return !empty($order['ao_buyer_confirmed_at']);
}

function auction_order_refund_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!auction_order_table_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_refunded_at'"));

    return $ready;
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_is_refunded(array $order): bool
{
    if (!auction_order_refund_column_ready()) {
        return false;
    }

    return !empty($order['ao_refunded_at']);
}

function auction_order_refund_request_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!auction_order_refund_column_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_refund_requested_at'"));

    return $ready;
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_is_refund_pending(array $order): bool
{
    if (!auction_order_refund_request_column_ready()) {
        return false;
    }

    return !empty($order['ao_refund_requested_at']) && empty($order['ao_refunded_at']);
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_refund_expires_at(array $order): ?int
{
    if (!auction_order_is_confirmed($order) || empty($order['ao_buyer_confirmed_at'])) {
        return null;
    }
    $ts = strtotime((string) $order['ao_buyer_confirmed_at']);
    if ($ts === false) {
        return null;
    }

    return $ts + (AUCTION_REFUND_PERIOD_DAYS * 86400);
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_can_buyer_refund(array $order): bool
{
    if (!auction_order_refund_column_ready() || !member_cash_column_ready()) {
        return false;
    }
    if (!auction_order_is_confirmed($order) || auction_order_is_refunded($order) || auction_order_is_cancelled($order)) {
        return false;
    }
    $expires = auction_order_refund_expires_at($order);

    return $expires !== null && time() <= $expires;
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_can_buyer_request_refund(array $order): bool
{
    return auction_order_can_buyer_refund($order) && !auction_order_is_refund_pending($order);
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_can_seller_accept_refund(array $order): bool
{
    if (!auction_order_refund_request_column_ready() || !member_cash_column_ready()) {
        return false;
    }
    if (!auction_order_is_refund_pending($order)) {
        return false;
    }
    $expires = auction_order_refund_expires_at($order);

    return $expires !== null && time() <= $expires;
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_refund_days_left(array $order): int
{
    $expires = auction_order_refund_expires_at($order);
    if ($expires === null) {
        return 0;
    }
    $left = (int) ceil(($expires - time()) / 86400);

    return max(0, $left);
}

/**
 * @param array<string, mixed> $order
 * @return array{fee: int, seller_amount: int}
 */
function auction_order_settlement_amounts(int $order_amount): array
{
    $amount = max(0, $order_amount);
    $rate   = function_exists('platform_fee_auction_percent')
        ? platform_fee_auction_percent()
        : max(0, min(100, (int) AUCTION_PLATFORM_FEE_PERCENT));
    $fee    = (int) floor($amount * $rate / 100);
    if ($fee < 0) {
        $fee = 0;
    }
    if ($fee > $amount) {
        $fee = $amount;
    }

    return [
        'fee'           => $fee,
        'seller_amount' => $amount - $fee,
    ];
}

/**
 * 낙찰자용 배송 단계 (배송준비 → 배송시작 → 배송중 → 배송완료)
 *
 * @param array<string, mixed> $order
 * @return list<array{key: string, label: string, state: string}>
 */
function auction_order_delivery_steps(array $order): array
{
    $paid         = auction_order_is_paid($order);
    $has_tracking = auction_order_has_tracking($order);
    $st           = (int) ($order['ao_order_status'] ?? 0);
    $confirmed    = auction_order_is_confirmed($order);

    $done_prep      = $paid;
    $done_start     = $has_tracking || $st >= 3;
    $done_shipping  = $st >= 3;
    $done_delivered = $st >= 4 || $confirmed;

    $steps = [
        ['key' => 'prep', 'label' => '배송준비', 'done' => $done_prep],
        ['key' => 'start', 'label' => '배송시작', 'done' => $done_start],
        ['key' => 'shipping', 'label' => '배송중', 'done' => $done_shipping],
        ['key' => 'delivered', 'label' => '배송완료', 'done' => $done_delivered],
    ];

    $current_set = false;
    $out         = [];
    foreach ($steps as $step) {
        $state = 'pending';
        if ($step['done']) {
            $state = 'done';
        } elseif (!$current_set) {
            $state       = 'current';
            $current_set = true;
        }
        $out[] = [
            'key'   => $step['key'],
            'label' => $step['label'],
            'state' => $state,
        ];
    }

    if (!$current_set && $done_delivered) {
        $out[count($out) - 1]['state'] = 'done';
    }

    return $out;
}

/**
 * @param array<string, mixed> $order
 */
function auction_order_can_buyer_confirm(array $order, bool $test_mode = true): bool
{
    if (!auction_order_is_paid($order) || auction_order_is_confirmed($order) || auction_order_is_refunded($order)) {
        return false;
    }
    if ($test_mode) {
        return (int) ($order['ao_order_status'] ?? 0) >= 3;
    }

    return (int) ($order['ao_order_status'] ?? 0) >= 4;
}

function auction_order_checkout_url(int $au_idx): string
{
    return '/auction/auction_order.php?au_idx=' . max(1, $au_idx);
}

/**
 * 낙찰 주문 페이지 접근 역할: winner | seller | admin | ''
 *
 * @param array<string, mixed> $auction
 * @param array<string, mixed>|null $me
 */
function auction_order_view_role(array $auction, ?array $me): string
{
    if (!$me || (int) ($auction['au_auction_status'] ?? 0) !== 3) {
        return '';
    }
    $mb_idx      = (int) ($me['mb_idx'] ?? 0);
    $winner_mb   = (int) ($auction['au_winner_mb_idx'] ?? 0);
    $seller_mb   = (int) ($auction['mb_idx'] ?? 0);

    if ($mb_idx > 0 && $mb_idx === $winner_mb) {
        return 'winner';
    }
    if ($mb_idx > 0 && $mb_idx === $seller_mb) {
        return 'seller';
    }
    if ((int) ($me['mb_level'] ?? 0) >= 9) {
        return 'admin';
    }

    return '';
}

/**
 * @param array<string, mixed> $auction
 * @param array<string, mixed>|null $me
 */
function auction_order_can_view(array $auction, ?array $me): bool
{
    return auction_order_view_role($auction, $me) !== '';
}

/**
 * @return array{ok: bool, error?: string, winner_mb_idx?: int}
 */
function auction_force_finalize_won(int $au_idx, bool $notify = true): array
{
    if (!auction_table_ok() || !auction_bid_table_ok() || $au_idx < 1) {
        return ['ok' => false, 'error' => '경매 DB가 준비되지 않았습니다.'];
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return ['ok' => false, 'error' => '처리를 시작하지 못했습니다.'];
    }

    $rs = db_query("
        SELECT au_idx, au_title, au_auction_status
        FROM tb_auction
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_auction_status = 1
        LIMIT 1
        FOR UPDATE
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '진행 중인 경매가 아니거나 이미 확정되었습니다.'];
    }

    $top = db_assoc(db_query("
        SELECT mb_idx, ab_amount
        FROM tb_auction_bid
        WHERE au_idx = {$au_idx}
        ORDER BY ab_amount DESC, ab_idx DESC
        LIMIT 1
    "));
    if (!$top || (int) ($top['mb_idx'] ?? 0) < 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '입찰 내역이 없어 낙찰할 수 없습니다.'];
    }

    $winner = (int) $top['mb_idx'];
    $amount = max(0, (int) ($top['ab_amount'] ?? 0));
    $ok     = db_query("
        UPDATE tb_auction SET
            au_current_price  = {$amount},
            au_auction_status = 3,
            au_winner_mb_idx  = {$winner},
            au_ends_at        = NOW(),
            au_updated_at     = NOW()
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_auction_status = 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '낙찰 처리에 실패했습니다.'];
    }

    mysqli_commit($conn);

    if ($notify) {
        auction_notify_winner($au_idx, $winner, (string) ($auction['au_title'] ?? ''), $amount);
    }

    return ['ok' => true, 'winner_mb_idx' => $winner];
}

/**
 * @return array{ok: bool, error?: string}
 */
function auction_force_finalize_failed(int $au_idx): array
{
    if (!auction_table_ok() || $au_idx < 1) {
        return ['ok' => false, 'error' => '경매 DB가 준비되지 않았습니다.'];
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return ['ok' => false, 'error' => '처리를 시작하지 못했습니다.'];
    }

    $rs = db_query("
        SELECT au_idx
        FROM tb_auction
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_auction_status = 1
        LIMIT 1
        FOR UPDATE
    ");
    if (!db_assoc($rs)) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '진행 중인 경매가 아니거나 이미 확정되었습니다.'];
    }

    $ok = db_query("
        UPDATE tb_auction SET
            au_auction_status = 4,
            au_winner_mb_idx  = NULL,
            au_ends_at        = NOW(),
            au_updated_at     = NOW()
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_auction_status = 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '유찰 처리에 실패했습니다.'];
    }

    mysqli_commit($conn);

    return ['ok' => true];
}

/**
 * @return array{ok: bool, error?: string, ao_idx?: int}
 */
function auction_order_submit(int $mb_idx, int $au_idx, array $input): array
{
    if (!auction_order_table_ready()) {
        return ['ok' => false, 'error' => '주문 테이블이 없습니다. sql/tb_auction_order.sql 을 적용해 주세요.'];
    }
    if (member_auction_ban_column_ready() && member_auction_is_banned($mb_idx)) {
        return ['ok' => false, 'error' => member_auction_ban_user_message()];
    }

    require_once __DIR__ . '/../../lib/_member_address.php';

    $rs = db_query("
        SELECT *
        FROM tb_auction
        WHERE au_idx = {$au_idx} AND au_status = 1
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }
    if ((int) ($auction['au_auction_status'] ?? 0) !== 3) {
        return ['ok' => false, 'error' => '낙찰된 경매만 주문할 수 있습니다.'];
    }
    if ((int) ($auction['au_winner_mb_idx'] ?? 0) !== $mb_idx) {
        return ['ok' => false, 'error' => '낙찰자만 주문할 수 있습니다.'];
    }

    $existing = auction_order_get_by_auction($au_idx);
    if ($existing && auction_order_is_paid($existing)) {
        return ['ok' => false, 'error' => '이미 결제가 완료된 주문입니다.'];
    }

    $addr_idx   = (int) ($input['addr_idx'] ?? 0);
    $pay_method = trim((string) ($input['pay_method'] ?? 'cash'));
    if (!in_array($pay_method, ['cash', 'card'], true)) {
        return ['ok' => false, 'error' => '결제 수단을 선택해 주세요.'];
    }

    $addr = member_address_get($mb_idx, $addr_idx);
    if (!$addr) {
        return ['ok' => false, 'error' => '배송지를 선택해 주세요.'];
    }

    $msg_parsed = auction_order_parse_message($input);
    if (!$msg_parsed['ok']) {
        return $msg_parsed;
    }
    $order_message = (string) ($msg_parsed['message'] ?? '');

    $amount = (int) ($auction['au_current_price'] ?? 0);
    if ($amount < 1) {
        return ['ok' => false, 'error' => '결제 금액이 올바르지 않습니다.'];
    }

    $card_last4 = null;
    if ($pay_method === 'card') {
        $card_no = preg_replace('/\D/', '', (string) ($input['card_no'] ?? ''));
        if (strlen($card_no) < 15 || strlen($card_no) > 16) {
            return ['ok' => false, 'error' => '카드 번호 15~16자리를 입력해 주세요. (테스트용)'];
        }
        $card_last4 = substr($card_no, -4);
    } elseif (!member_cash_column_ready()) {
        return ['ok' => false, 'error' => '캐시 결제를 사용하려면 sql/migrate_tb_member_cash.sql 을 적용해 주세요.'];
    } elseif (member_cash_balance($mb_idx) < $amount) {
        return ['ok' => false, 'error' => '캐시 잔액이 부족합니다. (보유 ₩' . number_format(member_cash_balance($mb_idx)) . ')'];
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return ['ok' => false, 'error' => '주문 처리를 시작하지 못했습니다.'];
    }

    if ($pay_method === 'cash') {
        $cash_ok = db_query("
            UPDATE tb_member
            SET mb_cash = mb_cash - {$amount}
            WHERE mb_idx = {$mb_idx}
              AND mb_cash >= {$amount}
            LIMIT 1
        ");
        if (!$cash_ok || mysqli_affected_rows($conn) !== 1) {
            mysqli_rollback($conn);

            return ['ok' => false, 'error' => '캐시 차감에 실패했습니다. 잔액을 확인해 주세요.'];
        }
    }

    $seller     = (int) ($auction['mb_idx'] ?? 0);
    $esc_label  = db_escape((string) $addr['addr_label']);
    $esc_name   = db_escape((string) $addr['addr_name']);
    $esc_phone  = db_escape((string) $addr['addr_phone']);
    $esc_zip    = db_escape((string) $addr['addr_zip']);
    $esc_road   = db_escape((string) $addr['addr_road']);
    $esc_jibun  = !empty($addr['addr_jibun']) ? "'" . db_escape((string) $addr['addr_jibun']) . "'" : 'NULL';
    $esc_extra  = db_escape((string) ($addr['addr_extra'] ?? ''));
    $esc_detail = db_escape((string) ($addr['addr_detail'] ?? ''));
    $esc_pay    = db_escape($pay_method);
    $esc_card   = $card_last4 !== null ? "'" . db_escape($card_last4) . "'" : 'NULL';
    $msg_sql = '';
    if (auction_order_message_column_ready()) {
        $esc_msg = db_escape($order_message);
        $msg_sql = "ao_addr_message = '{$esc_msg}',";
    }

    if ($existing) {
        $ao_idx = (int) $existing['ao_idx'];
        $ok     = db_query("
            UPDATE tb_auction_order SET
                addr_idx = {$addr_idx},
                ao_addr_label = '{$esc_label}',
                ao_addr_name = '{$esc_name}',
                ao_addr_phone = '{$esc_phone}',
                ao_addr_zip = '{$esc_zip}',
                ao_addr_road = '{$esc_road}',
                ao_addr_jibun = {$esc_jibun},
                ao_addr_extra = '{$esc_extra}',
                ao_addr_detail = '{$esc_detail}',
                {$msg_sql}
                ao_amount = {$amount},
                ao_pay_method = '{$esc_pay}',
                ao_card_last4 = {$esc_card},
                ao_pay_status = 1,
                ao_order_status = 2,
                ao_paid_at = NOW(),
                ao_updated_at = NOW()
            WHERE ao_idx = {$ao_idx} AND ao_pay_status = 0
        ");
    } else {
        $msg_cols = '';
        $msg_vals = '';
        if (auction_order_message_column_ready()) {
            $esc_msg  = db_escape($order_message);
            $msg_cols = ', ao_addr_message';
            $msg_vals = ", '{$esc_msg}'";
        }
        $ok = db_query("
            INSERT INTO tb_auction_order
                (au_idx, mb_idx, seller_mb_idx, addr_idx,
                 ao_addr_label, ao_addr_name, ao_addr_phone, ao_addr_zip, ao_addr_road, ao_addr_jibun,
                 ao_addr_extra, ao_addr_detail{$msg_cols}, ao_amount, ao_pay_method, ao_card_last4,
                 ao_pay_status, ao_order_status, ao_created_at, ao_paid_at, ao_updated_at)
            VALUES
                ({$au_idx}, {$mb_idx}, {$seller}, {$addr_idx},
                 '{$esc_label}', '{$esc_name}', '{$esc_phone}', '{$esc_zip}', '{$esc_road}', {$esc_jibun},
                 '{$esc_extra}', '{$esc_detail}'{$msg_vals}, {$amount}, '{$esc_pay}', {$esc_card},
                 1, 2, NOW(), NOW(), NOW())
        ");
        $ao_idx = $ok ? (int) db_result('SELECT LAST_INSERT_ID()') : 0;
    }

    if (!$ok || ($existing && mysqli_affected_rows($conn) !== 1) || (!$existing && $ao_idx < 1)) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '주문 저장에 실패했습니다.'];
    }

    if ($pay_method === 'cash' && member_cash_log_table_ready()) {
        $cash_bal = member_cash_balance($mb_idx);
        $pay_memo = '경매 낙찰 결제: ' . (string) ($auction['au_title'] ?? '');
        if (!member_cash_log_insert($mb_idx, -$amount, $cash_bal, 'auction_pay', $pay_memo, $au_idx, $ao_idx)) {
            mysqli_rollback($conn);

            return ['ok' => false, 'error' => '캐시 내역 저장에 실패했습니다.'];
        }
    }

    mysqli_commit($conn);

    $buyer_nick = (string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . $mb_idx . ' LIMIT 1');
    auction_notify_seller_paid(
        $au_idx,
        $seller,
        (string) ($auction['au_title'] ?? ''),
        $buyer_nick,
        $amount
    );

    return ['ok' => true, 'ao_idx' => $ao_idx];
}

/**
 * 판매자 푸시 — 낙찰자 결제 완료·발송 안내
 */
function auction_notify_seller_paid(
    int $au_idx,
    int $seller_mb_idx,
    string $title,
    string $buyer_nick,
    int $amount
): void {
    if ($seller_mb_idx < 1 || $au_idx < 1 || $amount < 1) {
        return;
    }
    require_once __DIR__ . '/../../lib/webpush.php';
    if (function_exists('webpush_notify_auction_order_paid')) {
        webpush_notify_auction_order_paid($seller_mb_idx, $title, $buyer_nick, $amount, $au_idx);
    }
}

/**
 * @return array{ok: bool, error?: string}
 */
function auction_order_save_tracking(int $mb_idx, int $au_idx, array $input): array
{
    if (!auction_order_tracking_column_ready()) {
        return ['ok' => false, 'error' => '운송장 기능 DB가 없습니다. sql/migrate_tb_auction_order_tracking.sql 을 적용해 주세요.'];
    }

    $rs = db_query("
        SELECT a.au_idx, a.au_title, a.mb_idx AS seller_mb_idx, a.au_winner_mb_idx
        FROM tb_auction a
        WHERE a.au_idx = {$au_idx} AND a.au_status = 1 AND a.au_auction_status = 3
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }

    $seller_mb = (int) ($auction['seller_mb_idx'] ?? 0);
    $is_admin  = false;
    $me        = login_member();
    if ($me && (int) ($me['mb_level'] ?? 0) >= 9) {
        $is_admin = true;
    }
    if ($mb_idx !== $seller_mb && !$is_admin) {
        return ['ok' => false, 'error' => '판매자만 운송장을 등록할 수 있습니다.'];
    }

    $order = auction_order_get_by_auction($au_idx);
    if (!$order || !auction_order_is_paid($order)) {
        return ['ok' => false, 'error' => '결제가 완료된 주문만 운송장을 등록할 수 있습니다.'];
    }

    $courier  = trim((string) ($input['ao_courier_name'] ?? ''));
    $tracking = preg_replace('/\s+/', '', (string) ($input['ao_tracking_no'] ?? ''));

    if ($courier === '') {
        return ['ok' => false, 'error' => '택배사를 입력해 주세요.'];
    }
    if (mb_strlen($courier) > AUCTION_ORDER_COURIER_MAX) {
        return ['ok' => false, 'error' => '택배사명은 ' . AUCTION_ORDER_COURIER_MAX . '자 이내로 입력해 주세요.'];
    }
    if ($tracking === '') {
        return ['ok' => false, 'error' => '운송장번호를 입력해 주세요.'];
    }
    if (mb_strlen($tracking) > AUCTION_ORDER_TRACKING_MAX) {
        return ['ok' => false, 'error' => '운송장번호는 ' . AUCTION_ORDER_TRACKING_MAX . '자 이내로 입력해 주세요.'];
    }
    if (!preg_match('/^[0-9A-Za-z\-]+$/', $tracking)) {
        return ['ok' => false, 'error' => '운송장번호는 숫자·영문·하이픈(-)만 입력해 주세요.'];
    }

    $had_before   = auction_order_has_tracking($order);
    $old_tracking = trim((string) ($order['ao_tracking_no'] ?? ''));

    $esc_courier  = db_escape($courier);
    $esc_tracking = db_escape($tracking);
    $ao_idx       = (int) $order['ao_idx'];

    $ok = db_query("
        UPDATE tb_auction_order SET
            ao_courier_name = '{$esc_courier}',
            ao_tracking_no = '{$esc_tracking}',
            ao_shipped_at = NOW(),
            ao_order_status = 3,
            ao_updated_at = NOW()
        WHERE ao_idx = {$ao_idx}
          AND ao_pay_status = 1
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '운송장 저장에 실패했습니다.'];
    }

    $should_notify = !$had_before || $old_tracking !== $tracking;
    if ($should_notify) {
        $buyer_mb = (int) ($auction['au_winner_mb_idx'] ?? 0);
        auction_notify_buyer_tracking(
            $au_idx,
            $buyer_mb,
            (string) ($auction['au_title'] ?? ''),
            $courier,
            $tracking
        );
    }

    return ['ok' => true, 'notified' => $should_notify];
}

/**
 * 판매자 — 배송완료 처리 (테스트)
 *
 * @return array{ok: bool, error?: string}
 */
function auction_order_mark_delivered(int $mb_idx, int $au_idx): array
{
    $rs = db_query("
        SELECT a.mb_idx AS seller_mb_idx
        FROM tb_auction a
        WHERE a.au_idx = {$au_idx} AND a.au_status = 1 AND a.au_auction_status = 3
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }

    $seller_mb = (int) ($auction['seller_mb_idx'] ?? 0);
    $me        = login_member();
    $is_admin  = $me && (int) ($me['mb_level'] ?? 0) >= 9;
    if ($mb_idx !== $seller_mb && !$is_admin) {
        return ['ok' => false, 'error' => '판매자만 배송완료 처리할 수 있습니다.'];
    }

    $order = auction_order_get_by_auction($au_idx);
    if (!$order || !auction_order_is_paid($order)) {
        return ['ok' => false, 'error' => '결제 완료된 주문만 배송완료 처리할 수 있습니다.'];
    }
    if (auction_order_is_confirmed($order)) {
        return ['ok' => false, 'error' => '이미 구매확정된 주문입니다.'];
    }
    if ((int) ($order['ao_order_status'] ?? 0) >= 4) {
        return ['ok' => true];
    }

    $ao_idx = (int) $order['ao_idx'];
    $ok     = db_query("
        UPDATE tb_auction_order SET
            ao_order_status = 4,
            ao_updated_at = NOW()
        WHERE ao_idx = {$ao_idx} AND ao_pay_status = 1
        " . (auction_order_confirm_column_ready() ? ' AND ao_buyer_confirmed_at IS NULL' : '') . "
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '배송완료 처리에 실패했습니다.'];
    }

    return ['ok' => true];
}

/**
 * 낙찰자 구매확정 — 판매자에게 정산 (수수료 5% 제외)
 *
 * @return array{ok: bool, error?: string, seller_amount?: int, fee?: int}
 */
function auction_order_confirm_purchase(int $buyer_mb_idx, int $au_idx, bool $test_mode = true): array
{
    if (!auction_order_confirm_column_ready()) {
        return ['ok' => false, 'error' => '구매확정 기능 DB가 없습니다. sql/migrate_tb_auction_order_confirm.sql 을 적용해 주세요.'];
    }
    if (!member_cash_column_ready()) {
        return ['ok' => false, 'error' => '판매자 정산을 위해 sql/migrate_tb_member_cash.sql 을 적용해 주세요.'];
    }

    $rs = db_query("
        SELECT a.au_idx, a.mb_idx AS seller_mb_idx, a.au_winner_mb_idx
        FROM tb_auction a
        WHERE a.au_idx = {$au_idx} AND a.au_status = 1 AND a.au_auction_status = 3
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }
    if ((int) ($auction['au_winner_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return ['ok' => false, 'error' => '낙찰자만 구매확정할 수 있습니다.'];
    }

    $order = auction_order_get_by_auction($au_idx);
    if (!$order || !auction_order_is_paid($order)) {
        return ['ok' => false, 'error' => '결제 완료된 주문만 구매확정할 수 있습니다.'];
    }
    if (auction_order_is_confirmed($order)) {
        return ['ok' => false, 'error' => '이미 구매확정된 주문입니다.'];
    }
    if (!auction_order_can_buyer_confirm($order, $test_mode)) {
        if ($test_mode) {
            return ['ok' => false, 'error' => '운송장 등록(배송중) 이후에 구매확정할 수 있습니다.'];
        }

        return ['ok' => false, 'error' => '배송완료 후에 구매확정할 수 있습니다.'];
    }

    $amount   = (int) ($order['ao_amount'] ?? 0);
    $settle   = auction_order_settlement_amounts($amount);
    $fee      = $settle['fee'];
    $seller_amt = $settle['seller_amount'];
    $seller_mb  = (int) ($order['seller_mb_idx'] ?? 0);
    if ($seller_mb < 1 || $seller_amt < 1) {
        return ['ok' => false, 'error' => '정산 금액이 올바르지 않습니다.'];
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return ['ok' => false, 'error' => '구매확정 처리를 시작하지 못했습니다.'];
    }

    $ao_idx = (int) $order['ao_idx'];
    $ok     = db_query("
        UPDATE tb_auction_order SET
            ao_buyer_confirmed_at = NOW(),
            ao_seller_settle_amount = {$seller_amt},
            ao_platform_fee = {$fee},
            ao_order_status = 5,
            ao_updated_at = NOW()
        WHERE ao_idx = {$ao_idx}
          AND ao_pay_status = 1
          AND ao_buyer_confirmed_at IS NULL
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '구매확정 저장에 실패했습니다.'];
    }

    $cash_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash + {$seller_amt}
        WHERE mb_idx = {$seller_mb}
        LIMIT 1
    ");
    if (!$cash_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '판매자 캐시 정산에 실패했습니다.'];
    }

    if (member_cash_log_table_ready()) {
        $seller_bal = member_cash_balance($seller_mb);
        $au_title   = (string) db_result("SELECT au_title FROM tb_auction WHERE au_idx = {$au_idx} LIMIT 1");
        $settle_memo = '경매 판매 정산 (플랫폼 수수료 ₩' . number_format($fee) . '): ' . $au_title;
        if (!member_cash_log_insert($seller_mb, $seller_amt, $seller_bal, 'auction_settle', $settle_memo, $au_idx, $ao_idx)) {
            mysqli_rollback($conn);

            return ['ok' => false, 'error' => '캐시 내역 저장에 실패했습니다.'];
        }
    }

    mysqli_commit($conn);

    return [
        'ok'            => true,
        'seller_amount' => $seller_amt,
        'fee'           => $fee,
    ];
}

/**
 * 낙찰자 환불 신청 — 판매자 수락 후 실제 환불
 *
 * @return array{ok: bool, error?: string}
 */
function auction_order_request_refund_by_buyer(int $buyer_mb_idx, int $au_idx): array
{
    if (!auction_order_refund_request_column_ready()) {
        return ['ok' => false, 'error' => '환불 신청 DB가 없습니다. sql/migrate_tb_auction_order_refund_request.sql 을 적용해 주세요.'];
    }

    $rs = db_query("
        SELECT a.au_idx, a.au_title, a.au_winner_mb_idx, a.mb_idx AS seller_mb_idx
        FROM tb_auction a
        WHERE a.au_idx = {$au_idx} AND a.au_status = 1
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }
    if ((int) ($auction['au_winner_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return ['ok' => false, 'error' => '낙찰자만 환불을 신청할 수 있습니다.'];
    }

    $order = auction_order_get_by_auction($au_idx);
    if (!$order || !auction_order_is_paid($order)) {
        return ['ok' => false, 'error' => '결제 완료된 주문만 환불 신청할 수 있습니다.'];
    }
    if (!auction_order_is_confirmed($order)) {
        return ['ok' => false, 'error' => '구매확정된 주문만 환불 신청할 수 있습니다.'];
    }
    if (auction_order_is_refunded($order)) {
        return ['ok' => false, 'error' => '이미 환불 처리된 주문입니다.'];
    }
    if (auction_order_is_refund_pending($order)) {
        return ['ok' => false, 'error' => '이미 환불 신청 중입니다. 판매자 수락을 기다려 주세요.'];
    }
    if (!auction_order_can_buyer_request_refund($order)) {
        return ['ok' => false, 'error' => '환불 가능 기간(' . AUCTION_REFUND_PERIOD_DAYS . '일)이 지났습니다. 고객센터로 문의해 주세요.'];
    }

    global $conn;

    $ao_idx = (int) $order['ao_idx'];
    $ok     = db_query("
        UPDATE tb_auction_order SET
            ao_refund_requested_at = NOW(),
            ao_updated_at = NOW()
        WHERE ao_idx = {$ao_idx}
          AND ao_pay_status = 1
          AND ao_buyer_confirmed_at IS NOT NULL
          AND ao_refunded_at IS NULL
          AND ao_refund_requested_at IS NULL
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        return ['ok' => false, 'error' => '환불 신청 저장에 실패했습니다.'];
    }

    return ['ok' => true];
}

/**
 * 환불 실행(판매자 수락 시) — 결제금액 전액 캐시 환불·판매자 정산 회수
 *
 * @param array<string, mixed> $order
 * @param array<string, mixed> $auction
 * @return array{ok: bool, error?: string, refund_amount?: int}
 */
function auction_order_execute_refund(array $order, array $auction, int $buyer_mb_idx): array
{
    $au_idx     = (int) ($order['au_idx'] ?? 0);
    $refund_amt = (int) ($order['ao_amount'] ?? 0);
    $seller_mb  = (int) ($order['seller_mb_idx'] ?? 0);
    $seller_amt = (int) ($order['ao_seller_settle_amount'] ?? 0);
    if ($refund_amt < 1) {
        return ['ok' => false, 'error' => '환불 금액이 올바르지 않습니다.'];
    }
    if ($seller_mb < 1) {
        return ['ok' => false, 'error' => '판매자 정보를 찾을 수 없습니다.'];
    }
    if ($seller_amt > 0 && member_cash_balance($seller_mb) < $seller_amt) {
        return ['ok' => false, 'error' => '보유 캐시가 부족하여 환불을 수락할 수 없습니다. (정산금 ₩' . number_format($seller_amt) . ' 회수 필요)'];
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return ['ok' => false, 'error' => '환불 처리를 시작하지 못했습니다.'];
    }

    $ao_idx = (int) $order['ao_idx'];
    $ok     = db_query("
        UPDATE tb_auction_order SET
            ao_refunded_at = NOW(),
            ao_refund_amount = {$refund_amt},
            ao_order_status = 6,
            ao_updated_at = NOW()
        WHERE ao_idx = {$ao_idx}
          AND ao_pay_status = 1
          AND ao_buyer_confirmed_at IS NOT NULL
          AND ao_refund_requested_at IS NOT NULL
          AND ao_refunded_at IS NULL
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '환불 상태 저장에 실패했습니다.'];
    }

    if ($seller_amt > 0) {
        $claw_ok = db_query("
            UPDATE tb_member
            SET mb_cash = mb_cash - {$seller_amt}
            WHERE mb_idx = {$seller_mb}
              AND mb_cash >= {$seller_amt}
            LIMIT 1
        ");
        if (!$claw_ok || mysqli_affected_rows($conn) !== 1) {
            mysqli_rollback($conn);

            return ['ok' => false, 'error' => '판매자 정산금 회수에 실패했습니다.'];
        }
        if (member_cash_log_table_ready()) {
            $seller_bal = member_cash_balance($seller_mb);
            $title      = (string) ($auction['au_title'] ?? '');
            $memo       = '경매 환불 정산 회수: ' . $title;
            if (!member_cash_log_insert($seller_mb, -$seller_amt, $seller_bal, 'auction_refund_clawback', $memo, $au_idx, $ao_idx)) {
                mysqli_rollback($conn);

                return ['ok' => false, 'error' => '캐시 내역 저장에 실패했습니다.'];
            }
        }
    }

    $buyer_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash + {$refund_amt}
        WHERE mb_idx = {$buyer_mb_idx}
        LIMIT 1
    ");
    if (!$buyer_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '구매자 캐시 환불에 실패했습니다.'];
    }

    if (member_cash_log_table_ready()) {
        $buyer_bal = member_cash_balance($buyer_mb_idx);
        $title     = (string) ($auction['au_title'] ?? '');
        $memo      = '경매 구매확정 환불: ' . $title;
        if (!member_cash_log_insert($buyer_mb_idx, $refund_amt, $buyer_bal, 'auction_refund', $memo, $au_idx, $ao_idx)) {
            mysqli_rollback($conn);

            return ['ok' => false, 'error' => '캐시 내역 저장에 실패했습니다.'];
        }
    }

    mysqli_commit($conn);

    return ['ok' => true, 'refund_amount' => $refund_amt];
}

/**
 * 판매자 환불 수락 — 신청 건 실제 환불 처리
 *
 * @return array{ok: bool, error?: string, refund_amount?: int}
 */
function auction_order_accept_refund_by_seller(int $seller_mb_idx, int $au_idx): array
{
    if (!auction_order_refund_request_column_ready()) {
        return ['ok' => false, 'error' => '환불 신청 DB가 없습니다. sql/migrate_tb_auction_order_refund_request.sql 을 적용해 주세요.'];
    }
    if (!member_cash_column_ready()) {
        return ['ok' => false, 'error' => '캐시 환불을 위해 sql/migrate_tb_member_cash.sql 을 적용해 주세요.'];
    }

    $rs = db_query("
        SELECT a.au_idx, a.au_title, a.au_winner_mb_idx, a.mb_idx AS seller_mb_idx
        FROM tb_auction a
        WHERE a.au_idx = {$au_idx} AND a.au_status = 1
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }
    if ((int) ($auction['seller_mb_idx'] ?? 0) !== $seller_mb_idx) {
        return ['ok' => false, 'error' => '판매자만 환불을 수락할 수 있습니다.'];
    }

    $order = auction_order_get_by_auction($au_idx);
    if (!$order || !auction_order_is_paid($order)) {
        return ['ok' => false, 'error' => '결제 완료된 주문만 환불 수락할 수 있습니다.'];
    }
    if (!auction_order_is_refund_pending($order)) {
        return ['ok' => false, 'error' => '환불 신청 중인 주문만 수락할 수 있습니다.'];
    }
    if (!auction_order_can_seller_accept_refund($order)) {
        return ['ok' => false, 'error' => '환불 가능 기간(' . AUCTION_REFUND_PERIOD_DAYS . '일)이 지났습니다.'];
    }

    $buyer_mb_idx = (int) ($order['mb_idx'] ?? 0);
    if ($buyer_mb_idx < 1) {
        return ['ok' => false, 'error' => '구매자 정보를 찾을 수 없습니다.'];
    }

    return auction_order_execute_refund($order, $auction, $buyer_mb_idx);
}

/**
 * 낙찰자 미결제 주문 취소 — 경매 이용 금지
 *
 * @return array{ok: bool, error?: string}
 */
function auction_order_cancel_by_winner(int $mb_idx, int $au_idx): array
{
    if (!auction_table_ok()) {
        return ['ok' => false, 'error' => '경매 DB가 준비되지 않았습니다.'];
    }
    if (member_auction_ban_column_ready() && member_auction_is_banned($mb_idx)) {
        return ['ok' => false, 'error' => member_auction_ban_user_message()];
    }

    $rs = db_query("
        SELECT au_idx, au_title, au_auction_status, au_winner_mb_idx, mb_idx AS seller_mb_idx
        FROM tb_auction
        WHERE au_idx = {$au_idx} AND au_status = 1
        LIMIT 1
    ");
    $auction = db_assoc($rs);
    if (!$auction) {
        return ['ok' => false, 'error' => '경매를 찾을 수 없습니다.'];
    }
    if ((int) ($auction['au_auction_status'] ?? 0) !== 3) {
        return ['ok' => false, 'error' => '낙찰된 경매만 취소할 수 있습니다.'];
    }
    if ((int) ($auction['au_winner_mb_idx'] ?? 0) !== $mb_idx) {
        return ['ok' => false, 'error' => '낙찰자만 주문을 취소할 수 있습니다.'];
    }

    $order = auction_order_table_ready() ? auction_order_get_by_auction($au_idx) : null;
    if ($order && auction_order_is_paid($order)) {
        return ['ok' => false, 'error' => '결제가 완료된 주문은 취소할 수 없습니다. 고객센터로 문의해 주세요.'];
    }
    if ($order && auction_order_is_confirmed($order)) {
        return ['ok' => false, 'error' => '구매확정된 주문은 취소할 수 없습니다.'];
    }
    if ($order && auction_order_is_cancelled($order)) {
        return ['ok' => false, 'error' => '이미 취소된 주문입니다.'];
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return ['ok' => false, 'error' => '취소 처리를 시작하지 못했습니다.'];
    }

    $ban_memo = '낙찰 주문 취소 (경매 #' . $au_idx . ')';
    $ban      = member_auction_ban_apply($mb_idx, $ban_memo);
    if (!$ban['ok']) {
        mysqli_rollback($conn);

        return $ban;
    }

    if ($order) {
        $ao_idx = (int) $order['ao_idx'];
        $ok     = db_query("
            UPDATE tb_auction_order SET
                ao_order_status = 9,
                ao_pay_status = 0,
                ao_updated_at = NOW()
            WHERE ao_idx = {$ao_idx}
              AND ao_pay_status = 0
            LIMIT 1
        ");
        if (!$ok || mysqli_affected_rows($conn) !== 1) {
            mysqli_rollback($conn);

            return ['ok' => false, 'error' => '주문 취소 저장에 실패했습니다.'];
        }
    }

    $ok = db_query("
        UPDATE tb_auction SET
            au_winner_mb_idx = NULL,
            au_auction_status = 4,
            au_updated_at = NOW()
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_winner_mb_idx = {$mb_idx}
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return ['ok' => false, 'error' => '경매 상태 변경에 실패했습니다.'];
    }

    mysqli_commit($conn);

    return ['ok' => true];
}

function auction_notify_buyer_tracking(
    int $au_idx,
    int $buyer_mb_idx,
    string $title,
    string $courier,
    string $tracking
): void {
    if ($buyer_mb_idx < 1 || $au_idx < 1 || $courier === '' || $tracking === '') {
        return;
    }
    require_once __DIR__ . '/../../lib/webpush.php';
    if (function_exists('webpush_notify_auction_tracking')) {
        webpush_notify_auction_tracking($buyer_mb_idx, $title, $courier, $tracking, $au_idx);
    }
}

function auction_order_pay_method_label(string $method): string
{
    return $method === 'card' ? '카드결제' : '캐시결제';
}

function auction_order_status_label(int $status): string
{
    $map = [
        1 => '주문접수',
        2 => '배송준비',
        3 => '배송중',
        4 => '배송완료',
        5 => '구매확정',
        6 => '환불완료',
        9 => '취소',
    ];

    return $map[$status] ?? '알 수 없음';
}
