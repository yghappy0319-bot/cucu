<?php
/**
 * 거래 메시지함 — 결제 요청·무통장 입금
 * BUILD: 20260624w-php74
 */

/**
 * true — 판매자 결제 요청 시 tb_trade_payment INSERT
 * false — 결제 요청은 채팅 [PZ_PAY] 메시지만 (구매자 입금 신청 시 DB 저장은 항상 시도)
 */
if (!defined('TRADE_PAYMENT_SYNC_TO_DB')) {
    define('TRADE_PAYMENT_SYNC_TO_DB', false);
}

function trade_payment_sync_to_db(): bool
{
    return TRADE_PAYMENT_SYNC_TO_DB;
}

/** 입금 신청(구매자) tb_trade_payment 저장 여부 — 결제 테이블만 있으면 true */
function trade_payment_deposit_sync_ready(): bool
{
    return db_table_exists('tb_trade_payment');
}

/** @return array{bank_name: string, account_no: string, account_holder: string} */
function trade_payment_bank_snapshot(): array
{
    $bank = trade_payment_bank_info();

    return [
        'bank_name'      => (string) ($bank['bank_name'] ?? ''),
        'account_no'     => (string) ($bank['account_no'] ?? ''),
        'account_holder' => (string) ($bank['account_holder'] ?? ''),
    ];
}

/** @return string[] */
function trade_payment_deposit_bank_set_clauses(): array
{
    if (!trade_payment_pay_column_exists('pay_bank_name')) {
        return [];
    }
    $snap = trade_payment_bank_snapshot();

    return [
        "pay_bank_name = '" . db_escape($snap['bank_name']) . "'",
        "pay_account_no = '" . db_escape($snap['account_no']) . "'",
        "pay_account_holder = '" . db_escape($snap['account_holder']) . "'",
    ];
}

if (!defined('TRADE_PAYMENT_AMOUNT_MAX')) {
    define('TRADE_PAYMENT_AMOUNT_MAX', 99999999);
}
if (!defined('TRADE_PAYMENT_DEPOSITOR_MAX')) {
    define('TRADE_PAYMENT_DEPOSITOR_MAX', 50);
}
if (!defined('TRADE_PAYMENT_STATUS_PENDING')) {
    define('TRADE_PAYMENT_STATUS_PENDING', 0);
}
if (!defined('TRADE_PAYMENT_STATUS_SUBMITTED')) {
    define('TRADE_PAYMENT_STATUS_SUBMITTED', 1);
}
if (!defined('TRADE_PAYMENT_STATUS_CONFIRMED')) {
    define('TRADE_PAYMENT_STATUS_CONFIRMED', 2);
}
if (!defined('TRADE_PAYMENT_STATUS_CANCELLED')) {
    define('TRADE_PAYMENT_STATUS_CANCELLED', 3);
}
if (!defined('TRADE_PAYMENT_STATUS_SALE_CANCELLED')) {
    define('TRADE_PAYMENT_STATUS_SALE_CANCELLED', 4);
}
if (!defined('TRADE_PAYMENT_FULFILL_NONE')) {
    define('TRADE_PAYMENT_FULFILL_NONE', 0);
}
if (!defined('TRADE_PAYMENT_FULFILL_ADDR_SENT')) {
    define('TRADE_PAYMENT_FULFILL_ADDR_SENT', 1);
}
if (!defined('TRADE_PAYMENT_FULFILL_SHIPPING')) {
    define('TRADE_PAYMENT_FULFILL_SHIPPING', 2);
}
if (!defined('TRADE_PAYMENT_FULFILL_PURCHASE')) {
    define('TRADE_PAYMENT_FULFILL_PURCHASE', 3);
}
if (!defined('TRADE_PLATFORM_FEE_PERCENT')) {
    define(
        'TRADE_PLATFORM_FEE_PERCENT',
        function_exists('platform_fee_trade_percent') ? platform_fee_trade_percent() : 5
    );
}
if (!defined('TRADE_PURCHASE_AUTO_CONFIRM_DAYS')) {
    define('TRADE_PURCHASE_AUTO_CONFIRM_DAYS', 3);
}

/** pay_status: 0 결제대기, 1 입금확인중, 2 입금확인완료, 3 결제요청취소, 4 판매취소(환불) */

function trade_payment_fee_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_trade_payment_fee');

    return $ready;
}

function trade_payment_platform_fee_column_ready(): bool
{
    return trade_payment_pay_column_exists('pay_platform_fee');
}

/**
 * @return array{fee: int, fee_rate: int, seller_amount: int, gross: int}
 */
function trade_payment_settlement_amounts(int $pay_amount): array
{
    $amount = max(0, $pay_amount);
    $rate   = function_exists('platform_fee_trade_percent')
        ? platform_fee_trade_percent()
        : max(0, min(100, (int) TRADE_PLATFORM_FEE_PERCENT));
    $fee    = (int) floor($amount * $rate / 100);
    if ($fee < 0) {
        $fee = 0;
    }
    if ($fee > $amount) {
        $fee = $amount;
    }

    return [
        'gross'         => $amount,
        'fee_rate'      => $rate,
        'fee'           => $fee,
        'seller_amount' => $amount - $fee,
    ];
}

/**
 * 판매자 정산 기준 금액. 쿠폰 할인분은 플랫폼 부담 — 할인 전 금액 기준.
 */
function trade_payment_settlement_gross_from_row(array $row): int
{
    $pay_amount = max(0, (int) ($row['pay_amount'] ?? 0));
    if (trade_payment_pay_column_exists('pay_original_amount')) {
        $original = max(0, (int) ($row['pay_original_amount'] ?? 0));
        $discount = max(0, (int) ($row['pay_discount_amount'] ?? 0));
        if ($original > $pay_amount && $discount > 0) {
            return $original;
        }
    }

    return $pay_amount;
}

/**
 * @return array{fee: int, fee_rate: int, seller_amount: int, gross: int, coupon_subsidy: int}
 */
function trade_payment_settlement_preview_from_row(array $row): array
{
    $gross  = trade_payment_settlement_gross_from_row($row);
    $settle = trade_payment_settlement_amounts($gross);
    $settle['coupon_subsidy'] = max(0, $gross - max(0, (int) ($row['pay_amount'] ?? 0)));

    return $settle;
}

function trade_payment_is_confirmed_status(int $status): bool
{
    return $status === TRADE_PAYMENT_STATUS_CONFIRMED;
}

function trade_payment_is_cancelled_status(int $status): bool
{
    return $status === TRADE_PAYMENT_STATUS_CANCELLED;
}

function trade_payment_is_sale_cancelled_status(int $status): bool
{
    return $status === TRADE_PAYMENT_STATUS_SALE_CANCELLED;
}

function trade_payment_sale_cancel_column_ready(): bool
{
    return trade_payment_pay_column_exists('pay_refunded_at')
        && trade_payment_pay_column_exists('pay_refund_amount');
}

function trade_payment_fulfill_column_ready(): bool
{
    return trade_payment_pay_column_exists('pay_fulfill_status');
}

function trade_payment_fulfill_status_from_row(array $row): int
{
    if (!trade_payment_fulfill_column_ready()) {
        return TRADE_PAYMENT_FULFILL_NONE;
    }

    return max(0, (int) ($row['pay_fulfill_status'] ?? 0));
}

if (!defined('TRADE_PAYMENT_COURIER_MAX')) {
    define('TRADE_PAYMENT_COURIER_MAX', 30);
}
if (!defined('TRADE_PAYMENT_TRACKING_MAX')) {
    define('TRADE_PAYMENT_TRACKING_MAX', 50);
}

function trade_payment_ship_column_ready(): bool
{
    return trade_payment_pay_column_exists('pay_ship_name')
        && trade_payment_pay_column_exists('pay_courier_name')
        && trade_payment_pay_column_exists('pay_tracking_no');
}

/** @return string[] */
function trade_payment_courier_presets(): array
{
    if (function_exists('sweettracker_company_list')) {
        $list = sweettracker_company_list();
        $names = [];
        $prefer = ['CJ대한통운', '한진택배', '롯데택배', '우체국택배', '로젠택배', '경동택배', '대신택배'];
        foreach ($prefer as $p) {
            foreach ($list as $row) {
                if (($row['name'] ?? '') === $p) {
                    $names[] = $p;
                    break;
                }
            }
        }
        if ($names !== []) {
            return $names;
        }
    }

    return [
        'CJ대한통운',
        '한진택배',
        '롯데택배',
        '우체국택배',
        '로젠택배',
        '경동택배',
        '대신택배',
    ];
}

/**
 * @param array<string, mixed> $row
 */
function trade_payment_has_tracking(array $row): bool
{
    return trim((string) ($row['pay_courier_name'] ?? '')) !== ''
        && trim((string) ($row['pay_tracking_no'] ?? '')) !== '';
}

function trade_payment_courier_codes_match(string $a, string $b): bool
{
    if (function_exists('sweettracker_courier_codes_match')) {
        return sweettracker_courier_codes_match($a, $b);
    }
    $a = trim($a);
    $b = trim($b);
    if ($a === $b) {
        return true;
    }
    $na = ltrim($a, '0');
    $nb = ltrim($b, '0');

    return $na !== '' && $nb !== '' && $na === $nb;
}

/** @return array<string, string> name => code */
function trade_payment_courier_fallback_map(): array
{
    return [
        '우체국택배'   => '01',
        'CJ대한통운'   => '04',
        '한진택배'     => '05',
        '로젠택배'     => '06',
        '롯데택배'     => '08',
        '대신택배'     => '22',
        '경동택배'     => '23',
        '일양로지스'   => '11',
        '합동택배'     => '32',
        'CU편의점택배' => '46',
    ];
}

function trade_payment_courier_name_from_code(string $code): string
{
    $code = preg_replace('/\D+/', '', trim($code));
    if ($code === '') {
        return '';
    }
    foreach (trade_payment_courier_fallback_map() as $name => $fb_code) {
        if (trade_payment_courier_codes_match((string) $fb_code, $code)) {
            return (string) $name;
        }
    }
    $st = __DIR__ . '/../../lib/_sweettracker.php';
    if (is_readable($st)) {
        require_once $st;
        if (function_exists('sweettracker_company_name_by_code')) {
            $name = sweettracker_company_name_by_code($code);
            if ($name !== '') {
                return $name;
            }
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $row
 * @return array<string, string>
 */
function trade_payment_ship_address_from_row(array $row): array
{
    return [
        'label'   => trim((string) ($row['pay_ship_label'] ?? '')),
        'name'    => trim((string) ($row['pay_ship_name'] ?? '')),
        'phone'   => trim((string) ($row['pay_ship_phone'] ?? '')),
        'zip'     => trim((string) ($row['pay_ship_zip'] ?? '')),
        'address' => trim((string) ($row['pay_ship_address'] ?? '')),
        'message' => trim((string) ($row['pay_ship_message'] ?? '')),
    ];
}

function trade_payment_ship_address_has_data(array $addr): bool
{
    return trim((string) ($addr['name'] ?? '')) !== ''
        || trim((string) ($addr['address'] ?? '')) !== '';
}

/**
 * 입금 확인 시 구매자 기본 배송지를 결제 행에 저장
 *
 * @param array<string, mixed> $row
 */
function trade_payment_snapshot_buyer_ship_address(array $row): void
{
    if (!trade_payment_ship_column_ready()) {
        return;
    }

    $pay_idx = (int) ($row['pay_idx'] ?? 0);
    if ($pay_idx < 1) {
        return;
    }

    $existing = trade_payment_ship_address_from_row($row);
    if (trade_payment_ship_address_has_data($existing)) {
        return;
    }

    $buyer_mb = (int) ($row['buyer_mb_idx'] ?? 0);
    if ($buyer_mb < 1) {
        return;
    }

    $addr_lib = __DIR__ . '/../../lib/_member_address.php';
    if (!is_readable($addr_lib)) {
        return;
    }
    require_once $addr_lib;
    $addr = member_address_table_ready() ? member_address_get_default($buyer_mb) : null;
    if (!$addr) {
        return;
    }

    $lines = member_address_format_lines($addr);
    db_query('
        UPDATE tb_trade_payment SET
            pay_ship_label = \'' . db_escape((string) ($lines['label'] ?? '')) . '\',
            pay_ship_name = \'' . db_escape((string) ($lines['name'] ?? '')) . '\',
            pay_ship_phone = \'' . db_escape((string) ($lines['phone'] ?? '')) . '\',
            pay_ship_zip = \'' . db_escape((string) ($lines['zip'] ?? '')) . '\',
            pay_ship_address = \'' . db_escape((string) ($lines['address'] ?? '')) . '\',
            pay_ship_message = \'' . db_escape((string) ($lines['message'] ?? '')) . '\'
        WHERE pay_idx = ' . $pay_idx . '
        LIMIT 1
    ');
}

function trade_payment_ship_url(int $pay_idx): string
{
    if ($pay_idx < 1) {
        return '';
    }

    return '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;
}

/**
 * @return array{ok: bool, error?: string, notified?: bool}
 */
function trade_payment_save_tracking(int $seller_mb_idx, int $pay_idx, array $input): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_ship_column_ready()) {
        return $fail('택배 기능 DB가 없습니다. sql/migrate_tb_trade_payment_ship.sql 을 적용해 주세요.');
    }
    if ($pay_idx < 1 || $seller_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if ((int) ($row['seller_mb_idx'] ?? 0) !== $seller_mb_idx) {
        return $fail('판매자만 운송장을 등록할 수 있습니다.');
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('입금이 확인된 결제만 운송장을 등록할 수 있습니다.');
    }
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('판매 취소된 결제는 운송장을 등록할 수 없습니다.');
    }
    if (trade_payment_fulfill_status_from_row($row) >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return $fail('구매확정된 결제는 운송장을 수정할 수 없습니다.');
    }

    trade_payment_snapshot_buyer_ship_address($row);
    $row = trade_payment_row($pay_idx) ?: $row;

    $courier  = trim((string) ($input['pay_courier_name'] ?? ''));
    $courier_code = preg_replace('/\D+/', '', (string) ($input['pay_courier_code'] ?? ''));
    $tracking = preg_replace('/\s+/', '', (string) ($input['pay_tracking_no'] ?? ''));

    if ($courier === '' && $courier_code !== '') {
        $from_code = trade_payment_courier_name_from_code($courier_code);
        if ($from_code !== '') {
            $courier = $from_code;
        }
    }

    if ($courier === '') {
        return $fail('택배사를 선택해 주세요.');
    }
    if (mb_strlen($courier) > TRADE_PAYMENT_COURIER_MAX) {
        return $fail('택배사명은 ' . TRADE_PAYMENT_COURIER_MAX . '자 이내로 입력해 주세요.');
    }
    if ($tracking === '') {
        return $fail('운송장번호를 입력해 주세요.');
    }
    if (mb_strlen($tracking) > TRADE_PAYMENT_TRACKING_MAX) {
        return $fail('운송장번호는 ' . TRADE_PAYMENT_TRACKING_MAX . '자 이내로 입력해 주세요.');
    }
    if (!preg_match('/^[0-9A-Za-z\-]+$/', $tracking)) {
        return $fail('운송장번호는 숫자·영문·하이픈(-)만 입력해 주세요.');
    }

    $had_before   = trade_payment_has_tracking($row);
    $old_tracking = trim((string) ($row['pay_tracking_no'] ?? ''));

    $sets = [
        "pay_courier_name = '" . db_escape($courier) . "'",
        "pay_tracking_no = '" . db_escape($tracking) . "'",
    ];
    if (trade_payment_pay_column_exists('pay_tracking_at')) {
        $sets[] = 'pay_tracking_at = NOW()';
    }
    if ($courier_code !== '' && trade_payment_pay_column_exists('pay_courier_code')) {
        $sets[] = "pay_courier_code = '" . db_escape($courier_code) . "'";
    } else {
        foreach (trade_payment_courier_fallback_map() as $fb_name => $fb_code) {
            if ($courier === $fb_name && trade_payment_pay_column_exists('pay_courier_code')) {
                $sets[] = "pay_courier_code = '" . db_escape((string) $fb_code) . "'";
                break;
            }
        }
        if ($courier_code === '' && trade_payment_pay_column_exists('pay_courier_code')) {
            $st = __DIR__ . '/../../lib/_sweettracker.php';
            if (is_readable($st)) {
                require_once $st;
                if (function_exists('sweettracker_resolve_company_code')) {
                    $resolved = sweettracker_resolve_company_code($courier);
                    if ($resolved !== '') {
                        $sets[] = "pay_courier_code = '" . db_escape($resolved) . "'";
                    }
                }
            }
        }
    }
    if (trade_payment_fulfill_column_ready()) {
        $fulfill = trade_payment_fulfill_status_from_row($row);
        if ($fulfill < TRADE_PAYMENT_FULFILL_ADDR_SENT) {
            $sets[] = 'pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_ADDR_SENT;
        }
    }

    if (!db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx} LIMIT 1")) {
        return $fail('운송장 저장에 실패했습니다.');
    }

    $should_notify = !$had_before || $old_tracking !== $tracking;
    if ($should_notify) {
        $updated = trade_payment_row($pay_idx) ?: array_merge($row, [
            'pay_courier_name' => $courier,
            'pay_tracking_no'  => $tracking,
            'pay_courier_code' => $courier_code,
        ]);
        trade_payment_insert_tracking_chat_message($updated, $courier, $tracking);
    }

    return ['ok' => true, 'notified' => $should_notify];
}

/**
 * @param array<string, mixed> $row
 */
function trade_payment_can_seller_cancel_sale(array $row): bool
{
    $status = (int) ($row['pay_status'] ?? 0);
    if (!trade_payment_is_confirmed_status($status)) {
        return false;
    }
    if (trade_payment_fulfill_status_from_row($row) >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return false;
    }
    if (trade_payment_has_tracking($row)) {
        return false;
    }

    return true;
}

function trade_payment_build_sale_cancel_body(int $pay_idx, int $amount): string
{
    $line = '판매가 취소되어 ₩' . number_format(max(0, $amount)) . '이 구매자 캐시로 환불되었습니다.';

    return '[PZ_PAY_SALECANCEL:' . max(0, $pay_idx) . ':' . max(0, $amount) . "]\n* 판매 취소 *\n" . $line;
}

/**
 * 판매자 — 입금 확인 후 판매 취소 · 구매자 캐시 환불
 *
 * @return array{ok: bool, error?: string, refund_amount?: int, payment?: array<string, mixed>}
 */
function trade_payment_cancel_sale(int $seller_mb_idx, int $pay_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    require_once __DIR__ . '/../../lib/_member_cash.php';
    if (!member_cash_column_ready()) {
        return $fail('캐시 환불 기능 DB가 없습니다. sql/migrate_tb_member_cash.sql 을 적용해 주세요.');
    }
    if (!trade_payment_sale_cancel_column_ready()) {
        return $fail('판매 취소 DB가 없습니다. sql/migrate_tb_trade_payment_sale_cancel.sql 을 적용해 주세요.');
    }
    if ($pay_idx < 1 || $seller_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if ((int) ($row['seller_mb_idx'] ?? 0) !== $seller_mb_idx) {
        return $fail('판매자만 판매를 취소할 수 있습니다.');
    }

    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_sale_cancelled_status($status)) {
        return [
            'ok'            => true,
            'refund_amount' => (int) ($row['pay_refund_amount'] ?? $row['pay_amount'] ?? 0),
            'payment'       => trade_payment_public_payload($row, $seller_mb_idx),
        ];
    }
    if (!trade_payment_can_seller_cancel_sale($row)) {
        if (trade_payment_fulfill_status_from_row($row) >= TRADE_PAYMENT_FULFILL_PURCHASE) {
            return $fail('구매확정된 거래는 판매 취소할 수 없습니다.');
        }
        if (trade_payment_has_tracking($row)) {
            return $fail('운송장이 등록된 거래는 판매 취소할 수 없습니다.');
        }

        return $fail('입금 확인된 결제만 판매 취소할 수 있습니다.');
    }

    $buyer_mb  = (int) ($row['buyer_mb_idx'] ?? 0);
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $tr_idx    = (int) ($row['tr_idx'] ?? 0);
    $label     = (string) ($row['product_label'] ?? '상품');
    $refund_amt = (int) ($row['pay_amount'] ?? 0);
    if ($buyer_mb < 1 || $refund_amt < 1) {
        return $fail('환불 금액이 올바르지 않습니다.');
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('판매 취소 처리를 시작하지 못했습니다.');
    }

    $sets = [
        'pay_status = ' . TRADE_PAYMENT_STATUS_SALE_CANCELLED,
        'pay_refund_amount = ' . $refund_amt,
        'pay_refunded_at = NOW()',
    ];
    $ok = db_query('
        UPDATE tb_trade_payment
        SET ' . implode(', ', $sets) . "
        WHERE pay_idx = {$pay_idx}
          AND pay_status = " . TRADE_PAYMENT_STATUS_CONFIRMED . '
        LIMIT 1
    ');
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);
        $again = trade_payment_row($pay_idx);
        if ($again && trade_payment_is_sale_cancelled_status((int) ($again['pay_status'] ?? 0))) {
            return [
                'ok'            => true,
                'refund_amount' => (int) ($again['pay_refund_amount'] ?? $refund_amt),
                'payment'       => trade_payment_public_payload($again, $seller_mb_idx),
            ];
        }

        return $fail('판매 취소 저장에 실패했습니다.');
    }

    $cash_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash + {$refund_amt}
        WHERE mb_idx = {$buyer_mb}
        LIMIT 1
    ");
    if (!$cash_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('구매자 캐시 환불에 실패했습니다.');
    }

    $memo = '거래 판매취소 환불: ' . $label . ' (pay#' . $pay_idx . ')';
    if (member_cash_log_table_ready()) {
        $bal = member_cash_balance($buyer_mb);
        if (!member_cash_log_insert($buyer_mb, $refund_amt, $bal, 'trade_refund', $memo)) {
            mysqli_rollback($conn);

            return $fail('환불 내역 저장에 실패했습니다.');
        }
    }

    require_once __DIR__ . '/../../lib/_coupon.php';
    if (!coupon_restore_for_payment($pay_idx)) {
        mysqli_rollback($conn);

        return $fail('쿠폰 복원에 실패했습니다. 잠시 후 다시 시도해 주세요.');
    }

    require_once __DIR__ . '/_member_trade_sell_suspend.php';
    $penalty = member_trade_sale_cancel_penalty_apply($seller_mb_idx, $pay_idx);
    if (empty($penalty['ok'])) {
        mysqli_rollback($conn);

        return $fail((string) ($penalty['error'] ?? '판매 취소 패널티 처리에 실패했습니다.'));
    }

    mysqli_commit($conn);

    if ($room_idx > 0) {
        $body = trade_payment_build_sale_cancel_body($pay_idx, $refund_amt);
        $esc  = db_escape($body);
        db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$seller_mb_idx}, '{$esc}', NOW())
        ");
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    try {
        require_once __DIR__ . '/../../lib/webpush.php';
        webpush_notify_trade_message(
            $buyer_mb,
            '포카존',
            $label . ' 판매 취소 · ₩' . number_format($refund_amt) . ' 캐시 환불',
            $tr_idx,
            $room_idx
        );
    } catch (Throwable $e) {
        error_log('trade_payment_cancel_sale webpush: ' . $e->getMessage());
    }

    $updated = trade_payment_row($pay_idx);

    return [
        'ok'              => true,
        'refund_amount'   => $refund_amt,
        'payment'         => trade_payment_public_payload($updated ?: $row, $seller_mb_idx),
        'penalty_applied' => !empty($penalty['penalty_applied']),
        'cancel_count'    => (int) ($penalty['count'] ?? 0),
        'suspended_until' => $penalty['suspended_until'] ?? null,
    ];
}

/**
 * @return array{ok: bool, error?: string, row?: array<string, mixed>, is_seller?: bool, is_buyer?: bool, can_edit_tracking?: bool, can_cancel_sale?: bool, is_sale_cancelled?: bool}
 */
function trade_payment_ship_page_access(int $pay_idx, int $viewer_mb_idx): array
{
    if ($pay_idx < 1 || $viewer_mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }
    if (!trade_payment_tables_ready()) {
        return ['ok' => false, 'error' => '결제 DB가 설정되지 않았습니다.'];
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return ['ok' => false, 'error' => '결제 정보를 찾을 수 없습니다.'];
    }

    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $buyer_mb  = (int) ($row['buyer_mb_idx'] ?? 0);
    $is_seller = $viewer_mb_idx === $seller_mb;
    $is_buyer  = $viewer_mb_idx === $buyer_mb;

    if (!$is_seller && !$is_buyer) {
        return ['ok' => false, 'error' => '접근 권한이 없습니다.'];
    }

    $status = (int) ($row['pay_status'] ?? 0);
    $is_sale_cancelled = trade_payment_is_sale_cancelled_status($status);
    if (!trade_payment_is_confirmed_status($status) && !$is_sale_cancelled) {
        return ['ok' => false, 'error' => '입금이 확인된 결제만 배송 정보를 볼 수 있습니다.'];
    }

    if (!$is_sale_cancelled) {
        trade_payment_snapshot_buyer_ship_address($row);
        $row = trade_payment_row($pay_idx) ?: $row;
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);

    return [
        'ok'                => true,
        'row'               => $row,
        'is_seller'         => $is_seller,
        'is_buyer'          => $is_buyer,
        'is_sale_cancelled' => $is_sale_cancelled,
        'can_edit_tracking' => $is_seller && !$is_sale_cancelled && $fulfill < TRADE_PAYMENT_FULFILL_PURCHASE,
        'can_cancel_sale'   => $is_seller && trade_payment_can_seller_cancel_sale($row),
    ];
}

/** 포카존 무통장 입금 계좌 (안내·결제 폼 공통) */
function trade_payment_bank_info(): array
{
    return [
        'bank_name'    => '케이뱅크',
        'account_no'   => '100-124-200276',
        'account_holder' => '장영관(럭키루트)',
        'label'        => '케이뱅크 100-124-200276 장영관(럭키루트)',
    ];
}

function trade_payment_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_trade_payment');

    return $ready;
}

function trade_payment_tables_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade_payment') || !db_table_exists('tb_trade_room_msg')) {
        $ready = false;

        return false;
    }
    $ready = trade_chat_msg_has_payment_columns();

    return $ready;
}

function trade_payment_column_exists(string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column) || !db_table_exists('tb_trade_room_msg')) {
        return false;
    }

    return (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_room_msg LIKE '{$column}'"));
}

function trade_payment_msg_pay_status_ready(): bool
{
    return trade_payment_column_exists('msg_pay_status');
}

function trade_payment_set_msg_pay_status(int $msg_idx, int $status): bool
{
    if ($msg_idx < 1 || !trade_payment_msg_pay_status_ready()) {
        return false;
    }
    $status = max(0, min(3, $status));

    return (bool) db_query('UPDATE tb_trade_room_msg SET msg_pay_status = ' . $status . " WHERE msg_idx = {$msg_idx}");
}

/**
 * @param array<string, mixed> $rw
 */
function trade_payment_msg_pay_status_from_row(array $rw): ?int
{
    if (!trade_payment_msg_pay_status_ready() || !array_key_exists('msg_pay_status', $rw)) {
        return null;
    }
    $v = $rw['msg_pay_status'];
    if ($v === null || $v === '') {
        return null;
    }

    return max(0, min(3, (int) $v));
}

/**
 * 결제 요청 메시지의 입금 상태 (msg_pay_status · 연결된 pay 행 · ACK 기준)
 *
 * @param array<string, mixed> $msg_rw
 */
function trade_payment_request_msg_status(int $room_idx, array $msg_rw): int
{
    $msg_idx = (int) ($msg_rw['msg_idx'] ?? 0);
    if ($msg_idx < 1) {
        return TRADE_PAYMENT_STATUS_PENDING;
    }

    $body = (string) ($msg_rw['msg_body'] ?? '');
    if (trade_payment_parse_cancel_body($body) !== null) {
        return TRADE_PAYMENT_STATUS_CANCELLED;
    }

    $col = trade_payment_msg_pay_status_from_row($msg_rw);
    if ($col !== null) {
        return $col;
    }

    $row = trade_payment_row_for_request_msg($msg_rw);
    if ($row) {
        $linked_msg = (int) ($row['request_msg_idx'] ?? 0);
        if ($linked_msg < 1 || $linked_msg === $msg_idx) {
            return (int) ($row['pay_status'] ?? TRADE_PAYMENT_STATUS_PENDING);
        }
    }

    if ($room_idx > 0 && trade_payment_has_ack_for_request($room_idx, $msg_idx)) {
        return TRADE_PAYMENT_STATUS_SUBMITTED;
    }

    return TRADE_PAYMENT_STATUS_PENDING;
}

function trade_payment_init_request_msg_status(int $msg_idx): void
{
    if ($msg_idx < 1) {
        return;
    }
    trade_payment_set_msg_pay_status($msg_idx, TRADE_PAYMENT_STATUS_PENDING);
    if (function_exists('trade_chat_msg_has_payment_columns') && trade_chat_msg_has_payment_columns()) {
        db_query("UPDATE tb_trade_room_msg SET msg_type = 'payment_request' WHERE msg_idx = {$msg_idx}");
    }
}

function trade_payment_pay_column_exists(string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column) || !db_table_exists('tb_trade_payment')) {
        return false;
    }

    return (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_payment LIKE '{$column}'"));
}

function trade_payment_fail_db(string $user_message): array
{
    $detail = trim(db_last_error());
    if ($detail !== '') {
        error_log('trade_payment: ' . $user_message . ' — ' . $detail);
    }

    return ['ok' => false, 'error' => $user_message . trade_payment_db_error_suffix()];
}

function trade_payment_db_error_suffix(): string
{
    $detail = trim(db_last_error());
    if ($detail === '') {
        return '';
    }
    global $db_connection_debug;
    if (!empty($db_connection_debug)) {
        return ' (' . $detail . ')';
    }
    if (stripos($detail, 'Unknown column') !== false) {
        if (stripos($detail, 'pay_bank_') !== false || stripos($detail, 'pay_account_') !== false) {
            return ' (입금 계좌 컬럼이 없습니다. sql/migrate_tb_trade_payment_deposit_bank.sql 을 적용해 주세요.)';
        }
        if (stripos($detail, 'tb_trade_payment') !== false || stripos($detail, 'request_msg_idx') !== false) {
            return ' (tb_trade_payment 컬럼이 부족합니다. sql/migrate_tb_trade_payment.sql 을 다시 적용해 주세요.)';
        }

        return ' (DB 컬럼이 없습니다. sql/migrate_tb_trade_payment_columns_only.sql 을 적용해 주세요.)';
    }
    if (stripos($detail, 'foreign key constraint') !== false) {
        return ' (거래글·대화방 정보가 일치하지 않습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.)';
    }
    if (stripos($detail, "doesn't exist") !== false) {
        return ' (결제 테이블이 없습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.)';
    }

    return ' (서버 error_log에 DB 오류가 기록되었습니다. /trade/trade_payment_health.php 로 상태를 확인해 주세요.)';
}

/**
 * @return array{ok: true, msg_idx: int}|array{ok: false, error: string}
 */
function trade_payment_insert_request_message(
    int $room_idx,
    int $seller_mb_idx,
    string $body,
    int $pay_idx
): array {
    $esc_body = db_escape($body);
    $cols     = 'room_idx, mb_idx, msg_body';
    $vals     = "{$room_idx}, {$seller_mb_idx}, '{$esc_body}'";

    if (function_exists('trade_chat_msg_has_payment_columns') && trade_chat_msg_has_payment_columns()) {
        $cols .= ', msg_type';
        $vals .= ", 'payment_request'";
    }
    if (trade_payment_column_exists('msg_pay_idx') && $pay_idx > 0) {
        $cols .= ', msg_pay_idx';
        $vals .= ", {$pay_idx}";
    }
    if (trade_chat_msg_has_image_column()) {
        $cols .= ', msg_image';
        $vals .= ', NULL';
    }
    $cols .= ', msg_created_at';
    $vals .= ', NOW()';

    if (!db_query("INSERT INTO tb_trade_room_msg ({$cols}) VALUES ({$vals})")) {
        return trade_payment_fail_db('결제 요청 메시지를 보낼 수 없습니다.');
    }
    $msg_idx = (int) db_insert_id();
    if ($msg_idx < 1) {
        return trade_payment_fail_db('결제 요청 메시지를 보낼 수 없습니다.');
    }

    return ['ok' => true, 'msg_idx' => $msg_idx];
}

/**
 * @return array<string, mixed>|null
 */
function trade_payment_row(int $pay_idx): ?array
{
    if ($pay_idx < 1 || !trade_payment_table_ready()) {
        return null;
    }
    $rs = db_query("SELECT * FROM tb_trade_payment WHERE pay_idx = {$pay_idx} LIMIT 1");

    return db_assoc($rs) ?: null;
}

/**
 * @return array<string, mixed>|null
 */
function trade_payment_row_by_msg(int $msg_idx): ?array
{
    if ($msg_idx < 1 || !trade_payment_table_ready()) {
        return null;
    }
    if (!trade_payment_pay_column_exists('request_msg_idx')) {
        return null;
    }
    $rs = db_query("SELECT * FROM tb_trade_payment WHERE request_msg_idx = {$msg_idx} LIMIT 1");

    return db_assoc($rs) ?: null;
}

/**
 * 결제 요청 채팅 메시지에 연결된 tb_trade_payment 행 (request_msg_idx · msg_pay_idx · 본문 pay_idx)
 *
 * @param array<string, mixed> $rw
 * @return array<string, mixed>|null
 */
function trade_payment_row_for_request_msg(array $rw): ?array
{
    if (!trade_payment_table_ready()) {
        return null;
    }

    $msg_idx = (int) ($rw['msg_idx'] ?? 0);
    if ($msg_idx < 1) {
        return null;
    }

    $row = trade_payment_row_by_msg($msg_idx);
    if ($row) {
        return $row;
    }

    $parsed = trade_payment_parse_request_body((string) ($rw['msg_body'] ?? ''));
    if ($parsed && (int) ($parsed['pay_idx'] ?? 0) > 0) {
        $row = trade_payment_row((int) $parsed['pay_idx']);
        if ($row) {
            return $row;
        }
    }

    $pay_idx = (int) ($rw['msg_pay_idx'] ?? 0);
    if ($pay_idx > 0) {
        return trade_payment_row($pay_idx);
    }

    return null;
}

/**
 * @return array{ok: bool, error?: string, amount?: int}
 */
function trade_payment_parse_amount($raw): array
{
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '' || $digits === null) {
        return ['ok' => false, 'error' => '결제 금액을 입력해 주세요.'];
    }
    $amount = (int) $digits;
    if ($amount < 1) {
        return ['ok' => false, 'error' => '결제 금액은 1원 이상이어야 합니다.'];
    }
    if ($amount > TRADE_PAYMENT_AMOUNT_MAX) {
        return ['ok' => false, 'error' => '결제 금액이 너무 큽니다.'];
    }

    return ['ok' => true, 'amount' => $amount];
}

/**
 * @return array{ok: bool, error?: string, name?: string}
 */
function trade_payment_parse_depositor_name(string $raw): array
{
    $name = trim($raw);
    if ($name === '') {
        return ['ok' => false, 'error' => '입금자명을 입력해 주세요.'];
    }
    if (mb_strlen($name) > TRADE_PAYMENT_DEPOSITOR_MAX) {
        return ['ok' => false, 'error' => '입금자명은 ' . TRADE_PAYMENT_DEPOSITOR_MAX . '자 이내로 입력해 주세요.'];
    }

    return ['ok' => true, 'name' => $name];
}

function trade_payment_status_label(int $status): string
{
    if (trade_payment_is_sale_cancelled_status($status)) {
        return '판매취소(환불)';
    }
    if (trade_payment_is_cancelled_status($status)) {
        return '결제요청취소';
    }
    if (trade_payment_is_confirmed_status($status)) {
        return '입금확인완료';
    }
    if ($status >= TRADE_PAYMENT_STATUS_SUBMITTED) {
        return '입금확인중';
    }

    return '결제대기';
}

function trade_payment_seller_can_cancel(int $status): bool
{
    return $status === TRADE_PAYMENT_STATUS_PENDING || $status === TRADE_PAYMENT_STATUS_SUBMITTED;
}

/**
 * 결제 요청 채팅 메시지가 입금 확인 완료 상태인지
 *
 * @param array<string, mixed> $msg_rw msg_idx, msg_body, msg_pay_idx(optional)
 */
function trade_payment_request_is_confirmed(int $room_idx, array $msg_rw): bool
{
    return trade_payment_request_msg_status($room_idx, $msg_rw) === TRADE_PAYMENT_STATUS_CONFIRMED;
}

/**
 * @return array{pay_idx: int, amount: int, depositor: string, request_msg_idx?: int}|null
 */
function trade_payment_find_done_for_request(int $room_idx, int $request_msg_idx, int $pay_idx = 0): ?array
{
    if ($room_idx < 1 || $request_msg_idx < 1) {
        return null;
    }

    $q = db_query("
        SELECT msg_body
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '[PZ_PAY_DONE:%'
        ORDER BY msg_idx DESC
        LIMIT 50
    ");
    while ($rw = db_assoc($q)) {
        $done = trade_payment_parse_done_body((string) ($rw['msg_body'] ?? ''));
        if (!$done) {
            continue;
        }
        $done_req = (int) ($done['request_msg_idx'] ?? 0);
        if ($done_req === $request_msg_idx) {
            return $done;
        }
        $done_pay = (int) ($done['pay_idx'] ?? 0);
        if ($pay_idx > 0 && $done_pay === $pay_idx) {
            $prow = trade_payment_row($pay_idx);
            if ($prow && (int) ($prow['request_msg_idx'] ?? 0) === $request_msg_idx) {
                return $done;
            }
        }
    }

    return null;
}

function trade_payment_has_done_for_request(int $room_idx, int $request_msg_idx, int $pay_idx = 0): bool
{
    return trade_payment_find_done_for_request($room_idx, $request_msg_idx, $pay_idx) !== null;
}

/** @deprecated 금액만으로 매칭하지 않음 — request_msg_idx 기준 사용 */
function trade_payment_find_done_for_request_amount(int $room_idx, int $amount): ?array
{
    return null;
}

/** @deprecated */
function trade_payment_has_done_for_request_amount(int $room_idx, int $request_msg_idx, int $amount, int $pay_idx = 0): bool
{
    if ($room_idx < 1 || $request_msg_idx < 1) {
        return false;
    }

    return trade_payment_has_done_for_request($room_idx, $request_msg_idx, $pay_idx);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function trade_payment_ensure_row_confirmed(array $row): array
{
    $pay_idx = (int) ($row['pay_idx'] ?? 0);
    if ($pay_idx < 1) {
        $row['pay_status'] = TRADE_PAYMENT_STATUS_CONFIRMED;
        trade_payment_mark_listing_in_deal((int) ($row['tr_idx'] ?? 0));

        return $row;
    }
    if (trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))
        || trade_payment_is_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return $row;
    }

    $sets = ['pay_status = ' . TRADE_PAYMENT_STATUS_CONFIRMED];
    if (trade_payment_pay_column_exists('pay_confirmed_at')) {
        $sets[] = 'pay_confirmed_at = NOW()';
    }
    db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx}");

    $updated = trade_payment_row($pay_idx);
    $req_msg = (int) (($updated ?: $row)['request_msg_idx'] ?? 0);
    if ($req_msg > 0) {
        trade_payment_set_msg_pay_status($req_msg, TRADE_PAYMENT_STATUS_CONFIRMED);
    }

    $out = $updated ?: array_merge($row, ['pay_status' => TRADE_PAYMENT_STATUS_CONFIRMED]);
    trade_payment_mark_listing_in_deal((int) ($out['tr_idx'] ?? 0));

    return $out;
}

/**
 * tb_trade_payment 행을 채팅 입금완료 기록과 맞춰 pay_status=2 로 동기화 (새로고침·poll 시 DB 반영)
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function trade_payment_reconcile_row_from_chat(int $room_idx, array $row, int $request_msg_idx = 0): array
{
    $st = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_confirmed_status($st) || trade_payment_is_cancelled_status($st)) {
        return $row;
    }

    $pay_idx = (int) ($row['pay_idx'] ?? 0);
    $req_msg = $request_msg_idx > 0 ? $request_msg_idx : (int) ($row['request_msg_idx'] ?? 0);
    if ($pay_idx > 0 && $room_idx > 0 && $req_msg > 0
        && trade_payment_has_done_for_request($room_idx, $req_msg, $pay_idx)) {
        $confirmed = trade_payment_ensure_row_confirmed($row);
        trade_payment_set_msg_pay_status($req_msg, TRADE_PAYMENT_STATUS_CONFIRMED);

        return $confirmed;
    }

    if (trade_payment_pay_column_exists('pay_confirmed_at') && trim((string) ($row['pay_confirmed_at'] ?? '')) !== '') {
        if ($req_msg > 0) {
            trade_payment_set_msg_pay_status($req_msg, TRADE_PAYMENT_STATUS_CONFIRMED);
        }

        return trade_payment_ensure_row_confirmed($row);
    }

    return $row;
}

/** 대화방 결제 — tb_trade_payment · msg_pay_status 동기화 */
function trade_payment_sync_room_payments(int $room_idx): void
{
    if ($room_idx < 1 || !trade_payment_tables_ready()) {
        return;
    }

    $st_confirmed = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $q = db_query("
        SELECT *
        FROM tb_trade_payment
        WHERE room_idx = {$room_idx}
          AND pay_status < {$st_confirmed}
        ORDER BY pay_idx ASC
    ");
    while ($row = db_assoc($q)) {
        trade_payment_reconcile_row_from_chat(
            $room_idx,
            $row,
            (int) ($row['request_msg_idx'] ?? 0)
        );
    }

    if (trade_payment_msg_pay_status_ready()) {
        $pay_sel = trade_payment_column_exists('msg_pay_idx') ? ', msg_pay_idx' : '';
        $status_sel = ', msg_pay_status';
        $q_msg = db_query("
            SELECT msg_idx, msg_body{$pay_sel}{$status_sel}
            FROM tb_trade_room_msg
            WHERE room_idx = {$room_idx}
              AND msg_body LIKE '[PZ_PAY:%'
              AND msg_body NOT LIKE '[PZ_PAY_CANCEL:%'
              AND msg_body NOT LIKE '[PZ_PAY_ACK:%'
              AND msg_body NOT LIKE '[PZ_PAY_DONE:%'
        ");
        while ($rw = db_assoc($q_msg)) {
            if (!trade_payment_parse_request_body((string) ($rw['msg_body'] ?? ''))) {
                continue;
            }
            $msg_idx = (int) ($rw['msg_idx'] ?? 0);
            if ($msg_idx < 1) {
                continue;
            }
            $st = trade_payment_request_msg_status($room_idx, $rw);
            trade_payment_set_msg_pay_status($msg_idx, $st);
        }
    }
}

/**
 * 결제 요청 메시지에 대한 입금 확인 완료 결제 행 (없으면 null)
 *
 * @param array<string, mixed> $msg_rw
 * @return array<string, mixed>|null
 */
function trade_payment_resolve_confirmed_for_request(int $room_idx, array $msg_rw, int $seller_mb_idx = 0): ?array
{
    $msg_idx = (int) ($msg_rw['msg_idx'] ?? 0);
    if ($room_idx < 1 || $msg_idx < 1) {
        return null;
    }
    if (trade_payment_request_msg_status($room_idx, $msg_rw) === TRADE_PAYMENT_STATUS_CANCELLED) {
        return null;
    }

    $row = trade_payment_row_by_msg($msg_idx);
    if (!$row) {
        $row = trade_payment_row_for_request_msg($msg_rw);
    }
    if ($row) {
        $linked = (int) ($row['request_msg_idx'] ?? 0);
        if ($linked > 0 && $linked !== $msg_idx) {
            $row = null;
        } elseif ($seller_mb_idx > 0 && (int) ($row['seller_mb_idx'] ?? 0) !== $seller_mb_idx) {
            $row = null;
        }
    }

    $pay_idx      = $row ? (int) ($row['pay_idx'] ?? 0) : (int) ($msg_rw['msg_pay_idx'] ?? 0);
    $is_confirmed = false;

    if ($row && trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        $is_confirmed = true;
    } elseif (trade_payment_msg_pay_status_from_row($msg_rw) === TRADE_PAYMENT_STATUS_CONFIRMED) {
        $is_confirmed = true;
    } elseif (trade_payment_has_done_for_request($room_idx, $msg_idx, $pay_idx)) {
        $is_confirmed = true;
        if ($row) {
            $row = trade_payment_ensure_row_confirmed($row);
        }
    }

    if (!$is_confirmed) {
        return null;
    }

    trade_payment_set_msg_pay_status($msg_idx, TRADE_PAYMENT_STATUS_CONFIRMED);

    if ($row) {
        return $row;
    }

    $parsed = trade_payment_parse_request_body((string) ($msg_rw['msg_body'] ?? ''));
    $amount = $parsed ? (int) ($parsed['amount'] ?? 0) : 0;
    $done   = trade_payment_find_done_for_request($room_idx, $msg_idx, $pay_idx);

    return [
        'pay_idx'         => (int) ($done['pay_idx'] ?? $pay_idx),
        'request_msg_idx' => $msg_idx,
        'room_idx'        => $room_idx,
        'pay_amount'      => $amount,
        'pay_status'      => TRADE_PAYMENT_STATUS_CONFIRMED,
        'depositor_name'  => (string) ($done['depositor'] ?? ''),
        'seller_mb_idx'   => $seller_mb_idx,
        'buyer_mb_idx'    => 0,
        'product_label'   => '',
    ];
}

/**
 * @return array{ok: bool, already_confirmed?: bool, message?: string, request_msg_idx?: int, payment?: array<string, mixed>}
 */
function trade_payment_cancel_already_confirmed_response(int $request_msg_idx = 0, ?array $row = null, int $viewer_mb_idx = 0): array
{
    $out = [
        'ok'                => true,
        'already_confirmed' => true,
        'message'           => '입금이 확인된 결제 요청입니다.',
    ];
    if ($request_msg_idx > 0) {
        $out['request_msg_idx'] = $request_msg_idx;
    }
    if ($row && $viewer_mb_idx > 0) {
        $room_idx = (int) ($row['room_idx'] ?? 0);
        $req_msg  = $request_msg_idx > 0 ? $request_msg_idx : (int) ($row['request_msg_idx'] ?? 0);
        if ((int) ($row['pay_idx'] ?? 0) > 0 && $room_idx > 0) {
            $row = trade_payment_reconcile_row_from_chat($room_idx, $row, $req_msg);
        } elseif ((int) ($row['pay_status'] ?? 0) < TRADE_PAYMENT_STATUS_CONFIRMED) {
            $row['pay_status'] = TRADE_PAYMENT_STATUS_CONFIRMED;
        }
        $payload = trade_payment_public_payload($row, $viewer_mb_idx);
        $payload['status']       = TRADE_PAYMENT_STATUS_CONFIRMED;
        $payload['is_confirmed'] = true;
        $payload['status_label'] = trade_payment_status_label(TRADE_PAYMENT_STATUS_CONFIRMED);
        $payload['can_cancel']   = false;
        $payload['can_pay']      = false;
        $out['payment']          = $payload;
    }

    return $out;
}

function trade_payment_normalize_name_match(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/\s+/u', '', $name);

    return $name;
}

function trade_payment_depositor_names_match(string $stored, string $incoming): bool
{
    $a = trade_payment_normalize_name_match($stored);
    $b = trade_payment_normalize_name_match($incoming);
    if ($a === '' || $b === '') {
        return false;
    }
    if ($a === $b) {
        return true;
    }

    return mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false;
}

/**
 * 입금확인중(pay_status=1) 주문을 입금확인완료(2)로 처리
 *
 * 은행 알림·관리자 수동 확인 공통. 판매자 캐시는 아직 지급되지 않고,
 * 구매확정 전까지 플랫폼 예치(묶임) 상태로 둡니다.
 *
 * @return array{ok: bool, error?: string, pay_idx?: int, message?: string, already?: bool}
 */
function trade_payment_confirm_deposit(int $pay_idx, string $note = ''): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($pay_idx < 1) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if (!trade_payment_tables_ready()) {
        return $fail('결제 DB가 설정되지 않았습니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_sale_cancelled_status($status)) {
        return $fail('판매 취소된 결제입니다.');
    }
    if (trade_payment_is_cancelled_status($status)) {
        return $fail('취소된 결제 요청입니다.');
    }
    if (trade_payment_is_confirmed_status($status)) {
        trade_payment_insert_confirm_chat_message($row);
        trade_payment_after_deposit_confirmed($row);

        return [
            'ok'      => true,
            'pay_idx' => $pay_idx,
            'message' => '이미 입금이 확인된 주문입니다.',
            'already' => true,
        ];
    }
    if ($status !== TRADE_PAYMENT_STATUS_SUBMITTED) {
        return $fail('입금확인중 상태인 주문만 입금 확인할 수 있습니다.');
    }

    $note = trim($note);
    $sets = ['pay_status = ' . TRADE_PAYMENT_STATUS_CONFIRMED];
    if (trade_payment_pay_column_exists('pay_confirmed_at')) {
        $sets[] = 'pay_confirmed_at = NOW()';
    }
    if (trade_payment_pay_column_exists('bank_confirm_msg') && $note !== '') {
        $sets[] = "bank_confirm_msg = '" . db_escape(mb_substr($note, 0, 500)) . "'";
    }

    if (!db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets)
        . " WHERE pay_idx = {$pay_idx} AND pay_status = " . TRADE_PAYMENT_STATUS_SUBMITTED)) {
        return $fail('입금 확인 처리에 실패했습니다.' . trade_payment_db_error_suffix());
    }

    global $conn;
    if (mysqli_affected_rows($conn) < 1) {
        $again = trade_payment_row($pay_idx);
        if ($again && trade_payment_is_confirmed_status((int) ($again['pay_status'] ?? 0))) {
            trade_payment_insert_confirm_chat_message($again);
            trade_payment_after_deposit_confirmed($again);

            return [
                'ok'      => true,
                'pay_idx' => $pay_idx,
                'message' => '이미 입금이 확인된 주문입니다.',
                'already' => true,
            ];
        }

        return $fail('입금 확인 처리에 실패했습니다.');
    }

    $updated = trade_payment_row($pay_idx);
    if (!$updated) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $room_idx  = (int) ($updated['room_idx'] ?? 0);
    $tr_idx    = (int) ($updated['tr_idx'] ?? 0);
    $buyer_mb  = (int) ($updated['buyer_mb_idx'] ?? 0);
    $seller_mb = (int) ($updated['seller_mb_idx'] ?? 0);
    $label     = (string) ($updated['product_label'] ?? '상품');
    $dep_name  = (string) ($updated['depositor_name'] ?? '');
    $amount    = (int) ($updated['pay_amount'] ?? 0);

    if ($room_idx > 0) {
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    trade_payment_insert_confirm_chat_message($updated);
    trade_payment_after_deposit_confirmed($updated);

    try {
        require_once __DIR__ . '/../../lib/webpush.php';
        $preview = $label . ' 입금 확인 완료 ₩' . number_format($amount);
        if ($buyer_mb > 0) {
            webpush_notify_trade_message($buyer_mb, '포카존', $preview, $tr_idx, $room_idx);
        }
        if ($seller_mb > 0 && $seller_mb !== $buyer_mb) {
            $seller_preview = ($dep_name !== '' ? $dep_name . ' 님 ' : '') . '입금 확인 ₩' . number_format($amount);
            webpush_notify_trade_message($seller_mb, '포카존', $seller_preview, $tr_idx, $room_idx);
        }
    } catch (Throwable $e) {
        error_log('trade_payment_confirm_deposit webpush: ' . $e->getMessage());
    }

    return [
        'ok'      => true,
        'pay_idx' => $pay_idx,
        'message' => '입금이 확인되었습니다.',
    ];
}

/**
 * 은행 입금 알림(POST)과 tb_trade_payment 매칭 후 입금 확인 완료 처리
 *
 * @return array
 */
function trade_payment_confirm_from_bank_webhook($depositor_name, $amount_raw, $bank_msg = '')
{
    require_once __DIR__ . '/_trade_bank_deposit_log.php';

    $fail = static function (string $error, array $extra = []): array {
        return array_merge(['ok' => false, 'error' => $error], $extra);
    };

    $log_return = static function (array $result) use (&$depositor_name, &$amount, &$bank_msg, &$expected_depositor): array {
        trade_bank_deposit_log_from_webhook(
            (string) $depositor_name,
            (int) $amount,
            (string) $bank_msg,
            $result,
            (string) ($expected_depositor ?? '')
        );

        return $result;
    };

    $depositor_name = trim((string) $depositor_name);
    $bank_msg       = trim((string) $bank_msg);
    $amount         = 0;
    $expected_depositor = '';

    if (!trade_payment_tables_ready()) {
        return $log_return($fail('결제 DB가 설정되지 않았습니다.'));
    }

    $parsed = trade_payment_parse_amount($amount_raw);
    if (!$parsed['ok']) {
        return $log_return($fail((string) ($parsed['error'] ?? '입금 금액이 올바르지 않습니다.')));
    }
    $amount = (int) $parsed['amount'];

    $name = $depositor_name;
    if ($name === '') {
        return $log_return($fail('입금자명이 없습니다.'));
    }

    $st_submitted = (int) TRADE_PAYMENT_STATUS_SUBMITTED;
    $st_confirmed = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $q = db_query("
        SELECT *
        FROM tb_trade_payment
        WHERE pay_status IN ({$st_submitted}, {$st_confirmed})
          AND pay_amount = {$amount}
        ORDER BY
            CASE WHEN pay_status = {$st_submitted} THEN 0 ELSE 1 END,
            pay_submitted_at ASC,
            pay_idx ASC
        LIMIT 20
    ");

    $matched = null;
    $candidate_names = [];
    while ($row = db_assoc($q)) {
        $stored_name = isset($row['depositor_name']) ? (string) $row['depositor_name'] : '';
        if ($stored_name !== '' && $stored_name !== '캐시결제') {
            $candidate_names[$stored_name] = true;
        }
        if (trade_payment_depositor_names_match($stored_name, $name)) {
            $matched = $row;
            break;
        }
    }

    if (!$matched) {
        require_once __DIR__ . '/../../lib/_member_cash_charge.php';
        if (function_exists('member_cash_charge_confirm_from_bank')) {
            $cash_result = member_cash_charge_confirm_from_bank($name, $amount, $bank_msg);
            if (!empty($cash_result['ok'])) {
                return $log_return([
                    'ok'      => true,
                    'pay_idx' => 0,
                    'cc_idx'  => (int) ($cash_result['cc_idx'] ?? 0),
                    'type'    => 'cash_charge',
                    'message' => (string) ($cash_result['message'] ?? '캐시 충전이 완료되었습니다.'),
                    'already' => !empty($cash_result['already']),
                ]);
            }
        }

        $expected_depositor = implode(', ', array_keys($candidate_names));
        $msg = '일치하는 입금 신청을 찾을 수 없습니다.';
        if ($expected_depositor !== '') {
            $msg .= ' (동일 금액 신청 입금자: ' . $expected_depositor . ')';
        }

        return $log_return($fail($msg, ['log_status' => 'unmatched']));
    }

    $pay_idx = (int) ($matched['pay_idx'] ?? 0);
    if ($pay_idx < 1) {
        return $log_return($fail('결제 정보를 찾을 수 없습니다.'));
    }

    return $log_return(trade_payment_confirm_deposit($pay_idx, $bank_msg));
}

/**
 * 구매자 구매확정 가능 여부 (배송 완료 알림 후 포함)
 *
 * @param array<string, mixed> $row
 */
function trade_payment_can_buyer_confirm_purchase(array $row, int $buyer_mb_idx): bool
{
    if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return false;
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return false;
    }
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return false;
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return false;
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        return true;
    }

    if ($fulfill < TRADE_PAYMENT_FULFILL_ADDR_SENT || !trade_payment_has_tracking($row)) {
        return false;
    }

    $pay_idx  = (int) ($row['pay_idx'] ?? 0);
    $room_idx = (int) ($row['room_idx'] ?? 0);
    if ($room_idx > 0 && $pay_idx > 0 && trade_payment_has_delivered_message($room_idx, $pay_idx)) {
        return true;
    }

    return trade_payment_is_delivery_complete($row);
}

/**
 * 배송 완료 후 구매확정 전 단계로 올림
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function trade_payment_advance_to_shipping_if_delivered(array $row): array
{
    $pay_idx = (int) ($row['pay_idx'] ?? 0);
    if ($pay_idx < 1 || !trade_payment_fulfill_column_ready()) {
        return $row;
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        return $row;
    }
    if ($fulfill < TRADE_PAYMENT_FULFILL_ADDR_SENT) {
        return $row;
    }

    $room_idx = (int) ($row['room_idx'] ?? 0);
    $can_advance = trade_payment_is_delivery_complete($row)
        || ($room_idx > 0 && trade_payment_has_delivered_message($room_idx, $pay_idx));
    if (!$can_advance) {
        return $row;
    }

    $sets = ['pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_SHIPPING];
    if (trade_payment_pay_column_exists('pay_shipped_at')) {
        $sets[] = 'pay_shipped_at = NOW()';
    }
    db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx} LIMIT 1");

    return trade_payment_row($pay_idx) ?: $row;
}

function trade_payment_trade_deal_status(int $tr_idx): int
{
    if ($tr_idx < 1) {
        return 1;
    }
    $rw = db_assoc(db_query("
        SELECT tr_deal_status
        FROM tb_trade
        WHERE tr_idx = {$tr_idx}
          AND tr_status = 1
        LIMIT 1
    "));

    return $rw ? max(1, (int) ($rw['tr_deal_status'] ?? 1)) : 1;
}

/**
 * @return array<string, mixed>
 */
function trade_payment_public_payload(array $row, int $viewer_mb_idx): array
{
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $buyer_mb  = (int) ($row['buyer_mb_idx'] ?? 0);
    $status    = (int) ($row['pay_status'] ?? 0);
    $is_buyer  = $viewer_mb_idx === $buyer_mb;
    $bank      = trade_payment_bank_info();
    $fulfill   = trade_payment_fulfill_status_from_row($row);
    $confirmed = trade_payment_is_confirmed_status($status);
    $pay_idx   = (int) ($row['pay_idx'] ?? 0);
    $is_seller = $viewer_mb_idx === $seller_mb;
    $has_tracking = trade_payment_ship_column_ready() && trade_payment_has_tracking($row);
    $is_sale_cancelled = trade_payment_is_sale_cancelled_status($status);
    $tr_idx            = (int) ($row['tr_idx'] ?? 0);
    $deal_status       = $tr_idx > 0 ? trade_payment_trade_deal_status($tr_idx) : 1;
    $purchase_done     = $fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE;

    return [
        'pay_idx'        => $pay_idx,
        'tr_idx'         => $tr_idx,
        'amount'         => (int) ($row['pay_amount'] ?? 0),
        'product_label'  => (string) ($row['product_label'] ?? ''),
        'status'         => $status,
        'status_label'   => trade_payment_status_label($status),
        'is_confirmed'   => $confirmed,
        'is_cancelled'   => trade_payment_is_cancelled_status($status),
        'is_sale_cancelled' => $is_sale_cancelled,
        'refund_amount'  => $is_sale_cancelled ? (int) ($row['pay_refund_amount'] ?? $row['pay_amount'] ?? 0) : 0,
        'depositor_name' => (string) ($row['depositor_name'] ?? ''),
        'can_pay'        => $is_buyer && $status < TRADE_PAYMENT_STATUS_SUBMITTED,
        'can_cancel'     => $viewer_mb_idx === $seller_mb && trade_payment_seller_can_cancel($status),
        'can_cancel_sale'=> $is_seller && trade_payment_can_seller_cancel_sale($row),
        'bank'           => $bank,
        'fulfill_status' => $fulfill,
        'ship_url'       => ($confirmed || $is_sale_cancelled) && $pay_idx > 0 ? trade_payment_ship_url($pay_idx) : '',
        'has_tracking'   => $has_tracking,
        'can_manage_shipping' => $is_seller && $confirmed && !$is_sale_cancelled && $fulfill < TRADE_PAYMENT_FULFILL_PURCHASE,
        'can_confirm_shipping' => $is_buyer && $confirmed && !$is_sale_cancelled
            && $has_tracking
            && $fulfill === TRADE_PAYMENT_FULFILL_ADDR_SENT,
        'can_confirm_purchase' => trade_payment_can_buyer_confirm_purchase($row, $viewer_mb_idx),
        'is_purchase_confirmed' => $purchase_done,
        'tr_deal_status'        => $deal_status,
        'is_trade_sold'         => $deal_status === 3,
        'can_mark_trade_sold'   => $is_seller && $purchase_done && !$is_sale_cancelled && $deal_status !== 3,
        'seller_settle_amount'  => $purchase_done
            ? (int) ($row['pay_seller_settle_amount'] ?? trade_payment_settlement_amounts(trade_payment_settlement_gross_from_row($row))['seller_amount'])
            : 0,
        'platform_fee'          => $purchase_done && trade_payment_platform_fee_column_ready()
            ? (int) ($row['pay_platform_fee'] ?? 0) : 0,
    ];
}

/**
 * 판매자 — 결제 요청 메시지 발송
 *
 * @return array{ok: bool, error?: string, pay_idx?: int, msg_idx?: int, room_idx?: int}
 */
function trade_payment_create_request(
    int $tr_idx,
    int $room_idx,
    int $seller_mb_idx,
    int $amount,
    string $product_label,
    string $seller_nick = ''
): array {
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_tables_ready()) {
        return $fail('결제 기능을 사용하려면 sql/migrate_tb_trade_payment.sql 을 적용해 주세요.');
    }
    if ($tr_idx < 1 || $room_idx < 1 || $seller_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $room = db_assoc(db_query("
        SELECT room_idx, tr_idx, buyer_mb_idx
        FROM tb_trade_room
        WHERE room_idx = {$room_idx} AND tr_idx = {$tr_idx}
        LIMIT 1
    "));
    if (!$room) {
        return $fail('대화방을 찾을 수 없습니다.');
    }

    $trade = db_assoc(db_query("
        SELECT tr_idx, mb_idx, tr_status
        FROM tb_trade
        WHERE tr_idx = {$tr_idx}
        LIMIT 1
    "));
    if (!$trade || (int) $trade['tr_status'] !== 1) {
        return $fail('거래글을 찾을 수 없습니다.');
    }
    if ((int) $trade['mb_idx'] !== $seller_mb_idx) {
        return $fail('판매자만 결제 요청을 보낼 수 있습니다.');
    }

    $buyer_mb = (int) ($room['buyer_mb_idx'] ?? 0);
    if ($buyer_mb < 1) {
        return $fail('구매자 정보를 찾을 수 없습니다.');
    }

    $can_create = trade_payment_assert_can_create_request($room_idx);
    if (!$can_create['ok']) {
        return $fail((string) ($can_create['error'] ?? '결제 요청을 보낼 수 없습니다.'));
    }

    $label = trim($product_label);
    if ($label === '') {
        $label = '상품';
    }
    if (mb_strlen($label) > 200) {
        $label = mb_substr($label, 0, 200);
    }

    $body = $label . '의 결제를 요청하셨습니다.';
    $esc_label = db_escape($label);

    global $conn;
    mysqli_begin_transaction($conn);

    try {
        if (!db_query("
            INSERT INTO tb_trade_payment
                (room_idx, tr_idx, seller_mb_idx, buyer_mb_idx, pay_amount, product_label, pay_status, depositor_name, pay_created_at)
            VALUES
                ({$room_idx}, {$tr_idx}, {$seller_mb_idx}, {$buyer_mb}, {$amount}, '{$esc_label}', 0, '', NOW())
        ")) {
            throw new RuntimeException('결제 요청을 저장할 수 없습니다.' . trade_payment_db_error_suffix());
        }
        $pay_idx = (int) db_insert_id();
        if ($pay_idx < 1) {
            throw new RuntimeException('결제 요청을 저장할 수 없습니다.' . trade_payment_db_error_suffix());
        }

        $msg_ins = trade_payment_insert_request_message($room_idx, $seller_mb_idx, $body, $pay_idx);
        if (!$msg_ins['ok']) {
            throw new RuntimeException((string) ($msg_ins['error'] ?? '결제 요청 메시지를 보낼 수 없습니다.'));
        }
        $msg_idx = (int) $msg_ins['msg_idx'];

        if (!db_query("UPDATE tb_trade_payment SET request_msg_idx = {$msg_idx} WHERE pay_idx = {$pay_idx}")) {
            throw new RuntimeException('결제 요청 연결에 실패했습니다.' . trade_payment_db_error_suffix());
        }
        if (!db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}")) {
            throw new RuntimeException('대화방 갱신에 실패했습니다.' . trade_payment_db_error_suffix());
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        return $fail($e->getMessage());
    }

    if (trade_chat_room_has_read_columns()) {
        trade_chat_mark_room_read($room_idx, $seller_mb_idx, true);
    }

    try {
        $from_nick = trim($seller_nick);
        if ($from_nick === '') {
            $nick_row = db_assoc(db_query("SELECT mb_nick FROM tb_member WHERE mb_idx = {$seller_mb_idx} LIMIT 1"));
            $from_nick = trim((string) ($nick_row['mb_nick'] ?? ''));
        }
        if ($from_nick === '') {
            $from_nick = '판매자';
        }

        require_once __DIR__ . '/../../lib/webpush.php';
        $preview = $label . ' 결제 요청 ₩' . number_format($amount);
        webpush_notify_trade_message($buyer_mb, $from_nick, $preview, $tr_idx, $room_idx);
    } catch (Throwable $e) {
        error_log('trade_payment_create_request webpush: ' . $e->getMessage());
    }

    return [
        'ok'       => true,
        'pay_idx'  => $pay_idx,
        'msg_idx'  => $msg_idx,
        'room_idx' => $room_idx,
    ];
}

/**
 * 구매자 — 무통장 입금 신청
 *
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>}
 */
function trade_payment_submit_deposit(int $pay_idx, int $buyer_mb_idx, string $depositor_name): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_tables_ready()) {
        return $fail('결제 기능을 사용하려면 sql/migrate_tb_trade_payment.sql 을 적용해 주세요.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 요청을 찾을 수 없습니다.');
    }
    if ((int) $row['buyer_mb_idx'] !== $buyer_mb_idx) {
        return $fail('구매자만 입금 신청을 할 수 있습니다.');
    }
    if ((int) $row['pay_status'] >= 1) {
        return [
            'ok'       => true,
            'payment'  => trade_payment_public_payload($row, $buyer_mb_idx),
        ];
    }

    $parsed = trade_payment_parse_depositor_name($depositor_name);
    if (!$parsed['ok']) {
        return $fail((string) ($parsed['error'] ?? '입금자명이 올바르지 않습니다.'));
    }
    $name = (string) $parsed['name'];
    $esc_name = db_escape($name);

    $sets = [
        'pay_status = ' . TRADE_PAYMENT_STATUS_SUBMITTED,
        "depositor_name = '{$esc_name}'",
    ];
    if (trade_payment_pay_column_exists('pay_submitted_at')) {
        $sets[] = 'pay_submitted_at = NOW()';
    }
    $sets = array_merge($sets, trade_payment_deposit_bank_set_clauses());

    if (!db_query('
        UPDATE tb_trade_payment
        SET ' . implode(', ', $sets) . "
        WHERE pay_idx = {$pay_idx} AND pay_status = " . TRADE_PAYMENT_STATUS_PENDING . '
    ')) {
        return $fail('입금 신청을 저장할 수 없습니다.' . trade_payment_db_error_suffix());
    }

    $updated = trade_payment_row($pay_idx);
    if (!$updated) {
        return $fail('결제 요청을 찾을 수 없습니다.');
    }

    $req_msg = (int) ($updated['request_msg_idx'] ?? 0);
    if ($req_msg > 0) {
        trade_payment_set_msg_pay_status($req_msg, TRADE_PAYMENT_STATUS_SUBMITTED);
    }

    $room_idx  = (int) ($updated['room_idx'] ?? 0);
    $tr_idx    = (int) ($updated['tr_idx'] ?? 0);
    $seller_mb = (int) ($updated['seller_mb_idx'] ?? 0);
    $amount    = (int) ($updated['pay_amount'] ?? 0);

    db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");

    return [
        'ok'      => true,
        'payment' => trade_payment_public_payload($updated, $buyer_mb_idx),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function trade_payment_fetch_buyer_request_msg(int $request_msg_idx, int $buyer_mb_idx): ?array
{
    if ($request_msg_idx < 1) {
        return null;
    }

    $pay_sel = trade_payment_column_exists('msg_pay_idx') ? ', m.msg_pay_idx' : '';
    $req_msg = db_assoc(db_query("
        SELECT m.msg_idx, m.room_idx, m.mb_idx, m.msg_body{$pay_sel}, r.tr_idx, r.buyer_mb_idx
        FROM tb_trade_room_msg m
        INNER JOIN tb_trade_room r ON r.room_idx = m.room_idx
        WHERE m.msg_idx = {$request_msg_idx}
        LIMIT 1
    "));
    if (!$req_msg) {
        return null;
    }
    if ($buyer_mb_idx > 0 && (int) ($req_msg['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return null;
    }

    return $req_msg;
}

/**
 * 결제 요청 메시지에 연결된 tb_trade_payment 행
 *
 * @param array<string, mixed> $req_msg
 * @return array<string, mixed>|null
 */
function trade_payment_resolve_row_for_request_msg(array $req_msg, int $pay_idx_hint = 0): ?array
{
    if ($pay_idx_hint > 0) {
        $row = trade_payment_row($pay_idx_hint);
        if ($row) {
            return $row;
        }
    }

    $msg_idx = (int) ($req_msg['msg_idx'] ?? 0);
    if ($msg_idx > 0) {
        $row = trade_payment_row_for_request_msg($req_msg);
        if ($row) {
            return $row;
        }
        $row = trade_payment_row_by_msg($msg_idx);
        if ($row) {
            return $row;
        }
    }

    $parsed = trade_payment_parse_request_body((string) ($req_msg['msg_body'] ?? ''));
    if ($parsed && (int) ($parsed['pay_idx'] ?? 0) > 0) {
        return trade_payment_row((int) $parsed['pay_idx']);
    }

    return null;
}

function trade_payment_link_row_to_request_msg(int $pay_idx, int $request_msg_idx): void
{
    if ($pay_idx < 1 || $request_msg_idx < 1) {
        return;
    }
    if (trade_payment_pay_column_exists('request_msg_idx')) {
        db_query("
            UPDATE tb_trade_payment
            SET request_msg_idx = {$request_msg_idx}
            WHERE pay_idx = {$pay_idx}
              AND (request_msg_idx IS NULL OR request_msg_idx = 0)
            LIMIT 1
        ");
    }
    trade_payment_update_msg_meta($request_msg_idx, $pay_idx);
}

/**
 * @return array{ok: bool, error?: string, row?: array<string, mixed>}
 */
function trade_payment_insert_pending_from_request(int $buyer_mb_idx, int $request_msg_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_deposit_sync_ready()) {
        return $fail('결제 DB가 설정되지 않았습니다.');
    }

    $req_msg = trade_payment_fetch_buyer_request_msg($request_msg_idx, $buyer_mb_idx);
    if (!$req_msg) {
        return $fail('결제 요청 메시지를 찾을 수 없습니다.');
    }

    $existing = trade_payment_resolve_row_for_request_msg($req_msg);
    if ($existing && (int) ($existing['pay_idx'] ?? 0) > 0) {
        $pay_idx = (int) $existing['pay_idx'];
        trade_payment_link_row_to_request_msg($pay_idx, $request_msg_idx);
        $row = trade_payment_row($pay_idx) ?: $existing;

        return ['ok' => true, 'row' => $row];
    }

    $parsed = trade_payment_parse_request_body((string) ($req_msg['msg_body'] ?? ''));
    if (!$parsed) {
        return $fail('결제 요청 메시지가 아닙니다.');
    }

    $amount = (int) ($parsed['amount'] ?? 0);
    if ($amount < 1) {
        return $fail('결제 금액이 올바르지 않습니다.');
    }

    $trade = db_assoc(db_query('
        SELECT tr_idx, mb_idx, tr_card_name, tr_title
        FROM tb_trade
        WHERE tr_idx = ' . (int) $req_msg['tr_idx'] . '
        LIMIT 1
    '));
    $label = $trade ? trade_chat_inquiry_product_label($trade) : '상품';
    $seller_mb = $trade ? (int) ($trade['mb_idx'] ?? 0) : (int) ($req_msg['mb_idx'] ?? 0);
    if ($seller_mb < 1) {
        return $fail('판매자 정보를 찾을 수 없습니다.');
    }

    $esc_label = db_escape($label);
    $room_idx  = (int) $req_msg['room_idx'];
    $tr_idx    = (int) $req_msg['tr_idx'];

    $cols = 'room_idx, tr_idx, seller_mb_idx, buyer_mb_idx, pay_amount, product_label, pay_status, pay_created_at';
    $vals = "{$room_idx}, {$tr_idx}, {$seller_mb}, {$buyer_mb_idx}, {$amount}, '{$esc_label}', "
        . TRADE_PAYMENT_STATUS_PENDING . ', NOW()';

    if (trade_payment_pay_column_exists('depositor_name')) {
        $cols .= ', depositor_name';
        $vals .= ", ''";
    }

    if (trade_payment_pay_column_exists('request_msg_idx')) {
        $cols .= ', request_msg_idx';
        $vals .= ", {$request_msg_idx}";
    }

    if (!db_query("INSERT INTO tb_trade_payment ({$cols}) VALUES ({$vals})")) {
        $again = trade_payment_resolve_row_for_request_msg($req_msg);
        if ($again && (int) ($again['pay_idx'] ?? 0) > 0) {
            $pay_idx = (int) $again['pay_idx'];
            trade_payment_link_row_to_request_msg($pay_idx, $request_msg_idx);
            $row = trade_payment_row($pay_idx) ?: $again;

            return ['ok' => true, 'row' => $row];
        }

        return trade_payment_fail_db('결제 정보를 저장할 수 없습니다.');
    }

    $pay_idx = (int) db_insert_id();
    if ($pay_idx < 1) {
        $again = trade_payment_resolve_row_for_request_msg($req_msg);
        if ($again && (int) ($again['pay_idx'] ?? 0) > 0) {
            $pay_idx = (int) $again['pay_idx'];
        }
    }
    if ($pay_idx < 1) {
        return trade_payment_fail_db('결제 정보를 저장할 수 없습니다.');
    }

    trade_payment_link_row_to_request_msg($pay_idx, $request_msg_idx);

    $row = trade_payment_row($pay_idx);

    return $row ? ['ok' => true, 'row' => $row] : $fail('결제 정보를 찾을 수 없습니다.');
}

/**
 * 구매자 — 보유 캐시로 즉시 결제 (입금 확인 완료 처리)
 *
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>, cash_balance?: int}
 */
function trade_payment_pay_with_cash(int $buyer_mb_idx, int $pay_idx, int $request_msg_idx = 0): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    require_once __DIR__ . '/../../lib/_member_cash.php';
    if (!trade_payment_deposit_sync_ready()) {
        return $fail('결제 기능을 사용하려면 sql/migrate_tb_trade_payment.sql 을 적용해 주세요.');
    }
    if (!member_cash_column_ready()) {
        return $fail('캐시 결제를 사용하려면 sql/migrate_tb_member_cash.sql 을 적용해 주세요.');
    }
    if ($buyer_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if ($request_msg_idx < 1 && $pay_idx > 0) {
        $hint = trade_payment_row($pay_idx);
        if ($hint) {
            $request_msg_idx = (int) ($hint['request_msg_idx'] ?? 0);
        }
    }
    if ($request_msg_idx < 1) {
        return $fail('결제 요청 메시지를 찾을 수 없습니다.');
    }

    $req_msg = trade_payment_fetch_buyer_request_msg($request_msg_idx, $buyer_mb_idx);
    if (!$req_msg) {
        return $fail('결제 요청 메시지를 찾을 수 없습니다.');
    }

    $parsed = trade_payment_parse_request_body((string) ($req_msg['msg_body'] ?? ''));
    if (!$parsed || (int) ($parsed['amount'] ?? 0) < 1) {
        return $fail('결제 금액이 올바르지 않습니다.');
    }
    $amount = (int) $parsed['amount'];

    $trade = db_assoc(db_query('
        SELECT tr_idx, mb_idx, tr_card_name, tr_title
        FROM tb_trade
        WHERE tr_idx = ' . (int) $req_msg['tr_idx'] . '
        LIMIT 1
    '));
    $label     = $trade ? trade_chat_inquiry_product_label($trade) : '상품';
    $seller_mb = $trade ? (int) ($trade['mb_idx'] ?? 0) : (int) ($req_msg['mb_idx'] ?? 0);

    $row = trade_payment_resolve_row_for_request_msg($req_msg, $pay_idx);
    if ($row) {
        $pay_idx = (int) ($row['pay_idx'] ?? 0);
        if ($pay_idx > 0) {
            trade_payment_link_row_to_request_msg($pay_idx, $request_msg_idx);
            $row = trade_payment_row($pay_idx) ?: $row;
        }
    }

    if (!$row || $pay_idx < 1) {
        $created = trade_payment_insert_deposit_from_ack(
            $request_msg_idx,
            (int) $req_msg['room_idx'],
            (int) $req_msg['tr_idx'],
            $seller_mb,
            $buyer_mb_idx,
            $amount,
            '캐시결제',
            $label
        );
        if (empty($created['ok'])) {
            $row = trade_payment_resolve_row_for_request_msg($req_msg, $pay_idx);
            if ($row && (int) ($row['pay_idx'] ?? 0) > 0) {
                $pay_idx = (int) $row['pay_idx'];
            } else {
                return $fail((string) ($created['error'] ?? '결제 정보를 저장할 수 없습니다.'));
            }
        } else {
            $pay_idx = (int) ($created['pay_idx'] ?? 0);
            $row     = $pay_idx > 0 ? trade_payment_row($pay_idx) : null;
        }
    }

    if (!$row || $pay_idx < 1) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return $fail('구매자만 캐시 결제를 할 수 있습니다.');
    }

    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_sale_cancelled_status($status)) {
        return $fail('판매 취소된 결제입니다.');
    }
    if (trade_payment_is_cancelled_status($status)) {
        return $fail('취소된 결제 요청입니다.');
    }
    if (trade_payment_is_confirmed_status($status)) {
        return [
            'ok'           => true,
            'payment'      => trade_payment_public_payload($row, $buyer_mb_idx),
            'cash_balance' => member_cash_balance($buyer_mb_idx),
        ];
    }

    $depositor = trim((string) ($row['depositor_name'] ?? ''));
    if ($status >= TRADE_PAYMENT_STATUS_SUBMITTED && $depositor !== '' && $depositor !== '캐시결제') {
        return $fail('이미 무통장 입금 신청이 되어 있습니다. 입금 확인을 기다리거나 판매자에게 문의해 주세요.');
    }

    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $tr_idx    = (int) ($row['tr_idx'] ?? 0);
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $label     = (string) ($row['product_label'] ?? $label);
    $req_msg   = (int) ($row['request_msg_idx'] ?? $request_msg_idx);

    if ((int) ($row['pay_amount'] ?? 0) > 0) {
        $amount = (int) $row['pay_amount'];
    }

    $balance = member_cash_balance($buyer_mb_idx);
    if ($balance < $amount) {
        return $fail('캐시 잔액이 부족합니다. (보유 ₩' . number_format($balance) . ')');
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('결제 처리를 시작하지 못했습니다.');
    }

    $cash_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash - {$amount}
        WHERE mb_idx = {$buyer_mb_idx}
          AND mb_cash >= {$amount}
        LIMIT 1
    ");
    if (!$cash_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('캐시 차감에 실패했습니다. 잔액을 확인해 주세요.');
    }

    $esc_dep = db_escape('캐시결제');
    $sets    = [
        'pay_status = ' . TRADE_PAYMENT_STATUS_CONFIRMED,
        "depositor_name = '{$esc_dep}'",
        'pay_submitted_at = NOW()',
    ];
    if (trade_payment_pay_column_exists('pay_confirmed_at')) {
        $sets[] = 'pay_confirmed_at = NOW()';
    }
    $sets = array_merge($sets, trade_payment_deposit_bank_set_clauses());

    $st_confirmed = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $ok = db_query('
        UPDATE tb_trade_payment
        SET ' . implode(', ', $sets) . "
        WHERE pay_idx = {$pay_idx}
          AND pay_status < {$st_confirmed}
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);
        $again = trade_payment_row($pay_idx);
        if ($again && trade_payment_is_confirmed_status((int) ($again['pay_status'] ?? 0))) {
            return [
                'ok'           => true,
                'payment'      => trade_payment_public_payload($again, $buyer_mb_idx),
                'cash_balance' => member_cash_balance($buyer_mb_idx),
            ];
        }

        return $fail('결제 저장에 실패했습니다.');
    }

    $memo = '거래 캐시 결제: ' . $label . ' (pay#' . $pay_idx . ')';
    if (member_cash_log_table_ready()) {
        $bal_after = member_cash_balance($buyer_mb_idx);
        if (!member_cash_log_insert($buyer_mb_idx, -$amount, $bal_after, 'trade_pay', $memo)) {
            mysqli_rollback($conn);

            return $fail('캐시 내역 저장에 실패했습니다.');
        }
    }

    mysqli_commit($conn);

    $updated = trade_payment_row($pay_idx);
    if (!$updated) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    if ($req_msg > 0) {
        trade_payment_set_msg_pay_status($req_msg, TRADE_PAYMENT_STATUS_CONFIRMED);
    }

    trade_payment_insert_confirm_chat_message($updated);
    trade_payment_after_deposit_confirmed($updated);

    if ($room_idx > 0) {
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    try {
        require_once __DIR__ . '/../../lib/webpush.php';
        $preview = '캐시 결제 완료 ₩' . number_format($amount);
        if ($seller_mb > 0) {
            webpush_notify_trade_message($seller_mb, '포카존', $preview, $tr_idx, $room_idx);
        }
        if ($buyer_mb_idx > 0 && $buyer_mb_idx !== $seller_mb) {
            webpush_notify_trade_message($buyer_mb_idx, '포카존', $label . ' 캐시 결제 완료', $tr_idx, $room_idx);
        }
    } catch (Throwable $e) {
        error_log('trade_payment_pay_with_cash webpush: ' . $e->getMessage());
    }

    return [
        'ok'           => true,
        'payment'      => trade_payment_public_payload($updated, $buyer_mb_idx),
        'cash_balance' => member_cash_balance($buyer_mb_idx),
    ];
}

/**
 * 진행 중인 결제 요청 목록 (취소·입금완료 제외, 채팅 [PZ_PAY] 기준)
 *
 * @return array<int, array<string, mixed>>
 */
function trade_payment_list_active_requests(int $room_idx): array
{
    if ($room_idx < 1) {
        return [];
    }

    $out = [];
    $pay_sel = trade_payment_column_exists('msg_pay_idx') ? ', msg_pay_idx' : '';
    $status_sel = trade_payment_msg_pay_status_ready() ? ', msg_pay_status' : '';
    $q = db_query("
        SELECT msg_idx, mb_idx, msg_body, msg_created_at{$pay_sel}{$status_sel}
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '[PZ_PAY:%'
          AND msg_body NOT LIKE '[PZ_PAY_CANCEL:%'
          AND msg_body NOT LIKE '[PZ_PAY_ACK:%'
          AND msg_body NOT LIKE '[PZ_PAY_DONE:%'
        ORDER BY msg_idx ASC
    ");
    while ($rw = db_assoc($q)) {
        $body = (string) ($rw['msg_body'] ?? '');
        if (trade_payment_parse_cancel_body($body) !== null) {
            continue;
        }
        $parsed = trade_payment_parse_request_body($body);
        if (!$parsed) {
            continue;
        }
        $msg_idx = (int) ($rw['msg_idx'] ?? 0);
        if ($msg_idx < 1) {
            continue;
        }
        $msg_st = trade_payment_request_msg_status($room_idx, $rw);
        if ($msg_st >= TRADE_PAYMENT_STATUS_CONFIRMED || $msg_st === TRADE_PAYMENT_STATUS_CANCELLED) {
            continue;
        }

        $row = trade_payment_tables_ready() ? trade_payment_row_for_request_msg($rw) : null;
        if ($row && (trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))
            || trade_payment_is_cancelled_status((int) ($row['pay_status'] ?? 0)))) {
            continue;
        }

        $has_ack = $msg_st >= TRADE_PAYMENT_STATUS_SUBMITTED
            || trade_payment_has_ack_for_request($room_idx, $msg_idx);
        $status  = $row
            ? (int) ($row['pay_status'] ?? ($has_ack ? TRADE_PAYMENT_STATUS_SUBMITTED : TRADE_PAYMENT_STATUS_PENDING))
            : ($has_ack ? TRADE_PAYMENT_STATUS_SUBMITTED : TRADE_PAYMENT_STATUS_PENDING);

        $out[] = [
            'pay_idx'         => $row ? (int) ($row['pay_idx'] ?? 0) : 0,
            'request_msg_idx' => $msg_idx,
            'room_idx'        => $room_idx,
            'pay_amount'      => (int) ($parsed['amount'] ?? 0),
            'pay_status'      => $status,
            'can_cancel'      => trade_payment_seller_can_cancel($status),
        ];
    }

    return $out;
}

/**
 * 대화방에 진행 중인 결제 요청 (입금 확인 완료·취소 제외, 1건만)
 *
 * @return array<string, mixed>|null
 */
function trade_payment_room_open_request(int $room_idx): ?array
{
    $active = trade_payment_list_active_requests($room_idx);
    if ($active === []) {
        return null;
    }

    return $active[count($active) - 1];
}

function trade_payment_has_ack_for_request(int $room_idx, int $request_msg_idx): bool
{
    if ($room_idx < 1 || $request_msg_idx < 1) {
        return false;
    }
    $like = db_escape('%:' . $request_msg_idx . ']%');
    $rw   = db_assoc(db_query("
        SELECT msg_idx
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '[PZ_PAY_ACK:%'
          AND msg_body LIKE '{$like}'
        LIMIT 1
    "));

    return (bool) $rw;
}

function trade_payment_delete_acks_for_request(int $room_idx, int $request_msg_idx): void
{
    if ($room_idx < 1 || $request_msg_idx < 1) {
        return;
    }
    $like = db_escape('%:' . $request_msg_idx . ']%');
    db_query("
        DELETE FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '[PZ_PAY_ACK:%'
          AND msg_body LIKE '{$like}'
    ");
}

/**
 * @return array{pay_idx: int, amount: int, depositor: string}|null
 */
function trade_payment_parse_done_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_DONE:(\d+):(\d+):([^:\]]*)(?::(\d+))?\]/', $body, $m)) {
        return null;
    }

    return [
        'pay_idx'          => (int) $m[1],
        'amount'           => (int) $m[2],
        'depositor'        => (string) $m[3],
        'request_msg_idx'  => isset($m[4]) ? (int) $m[4] : 0,
    ];
}

function trade_payment_build_done_body(int $pay_idx, int $amount, string $depositor_name, int $request_msg_idx = 0): string
{
    $dep = trim($depositor_name);
    $dep = preg_replace('/[\[\]:]/u', '', $dep);
    if (mb_strlen($dep) > 50) {
        $dep = mb_substr($dep, 0, 50);
    }

    $line = number_format(max(0, $amount)) . '원 입금이 확인되었습니다.';
    if ($dep !== '') {
        $line .= ' (입금자: ' . $dep . ')';
    }

    $req_part = $request_msg_idx > 0 ? (':' . $request_msg_idx) : '';

    return '[PZ_PAY_DONE:' . $pay_idx . ':' . max(0, $amount) . ':' . $dep . $req_part . "]\n* 입금 확인 완료 *\n" . $line;
}

function trade_payment_has_done_message(int $room_idx, int $pay_idx): bool
{
    if ($room_idx < 1 || $pay_idx < 1) {
        return false;
    }
    $like = db_escape('[PZ_PAY_DONE:' . $pay_idx . ':%');
    $rw   = db_assoc(db_query("
        SELECT msg_idx
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '{$like}'
        LIMIT 1
    "));

    return (bool) $rw;
}

/** 입금 확인 완료(pay_status=2) 시 채팅 안내 메시지 — 중복 방지 */
function trade_payment_insert_confirm_chat_message(array $row): int
{
    $pay_idx   = (int) ($row['pay_idx'] ?? 0);
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    if ($pay_idx < 1 || $room_idx < 1 || $seller_mb < 1) {
        return 0;
    }
    if (trade_payment_has_done_message($room_idx, $pay_idx)) {
        return 0;
    }

    $amount = (int) ($row['pay_amount'] ?? 0);
    $dep    = (string) ($row['depositor_name'] ?? '');
    $req    = (int) ($row['request_msg_idx'] ?? 0);
    $body   = trade_payment_build_done_body($pay_idx, $amount, $dep, $req);
    $esc    = db_escape($body);

    $cols = 'room_idx, mb_idx, msg_body';
    $vals = "{$room_idx}, {$seller_mb}, '{$esc}'";
    if (trade_chat_msg_has_image_column()) {
        $cols .= ', msg_image';
        $vals .= ', NULL';
    }
    $cols .= ', msg_created_at';
    $vals .= ', NOW()';

    if (!db_query("INSERT INTO tb_trade_room_msg ({$cols}) VALUES ({$vals})")) {
        error_log('trade_payment_insert_confirm_chat_message: ' . db_last_error());

        return 0;
    }

    $msg_idx = (int) db_insert_id();

    if ($req > 0) {
        trade_payment_set_msg_pay_status($req, TRADE_PAYMENT_STATUS_CONFIRMED);
    }

    trade_chat_bump_room($room_idx, $seller_mb);

    return $msg_idx;
}

function trade_payment_parse_track_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_TRACK:(\d+)\]/', $body, $m)) {
        return null;
    }

    return ['pay_idx' => (int) $m[1]];
}

function trade_payment_build_track_body(int $pay_idx, string $courier, string $tracking): string
{
    $courier  = trim($courier);
    $tracking = trim($tracking);
    $line     = $courier !== '' && $tracking !== ''
        ? $courier . ' · ' . $tracking
        : ($courier !== '' ? $courier : $tracking);

    return '[PZ_PAY_TRACK:' . max(0, $pay_idx) . "]\n* 택배 발송 *\n" . $line;
}

/**
 * 운송장 등록 시 구매자 채팅 알림
 *
 * @param array<string, mixed> $row
 */
function trade_payment_insert_tracking_chat_message(array $row, string $courier, string $tracking): int
{
    $pay_idx   = (int) ($row['pay_idx'] ?? 0);
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    if ($pay_idx < 1 || $room_idx < 1 || $seller_mb < 1) {
        return 0;
    }
    if (!trade_payment_has_tracking($row)) {
        return 0;
    }

    $body = trade_payment_build_track_body($pay_idx, $courier, $tracking);
    $esc  = db_escape($body);
    $cols = 'room_idx, mb_idx, msg_body';
    $vals = "{$room_idx}, {$seller_mb}, '{$esc}'";
    if (trade_chat_msg_has_image_column()) {
        $cols .= ', msg_image';
        $vals .= ', NULL';
    }
    $cols .= ', msg_created_at';
    $vals .= ', NOW()';

    if (!db_query("INSERT INTO tb_trade_room_msg ({$cols}) VALUES ({$vals})")) {
        error_log('trade_payment_insert_tracking_chat_message: ' . db_last_error());

        return 0;
    }

    $msg_idx = (int) db_insert_id();
    if ($msg_idx < 1) {
        return 0;
    }

    trade_chat_bump_room($room_idx, $seller_mb);

    $buyer_mb = (int) ($row['buyer_mb_idx'] ?? 0);
    $tr_idx   = (int) ($row['tr_idx'] ?? 0);
    $label    = (string) ($row['product_label'] ?? '상품');
    try {
        require_once __DIR__ . '/../../lib/webpush.php';
        if ($buyer_mb > 0) {
            webpush_notify_trade_message(
                $buyer_mb,
                '포카존',
                $label . ' 택배 발송 · 배송 조회',
                $tr_idx,
                $room_idx
            );
        }
    } catch (Throwable $e) {
        error_log('trade_payment_insert_tracking_chat_message webpush: ' . $e->getMessage());
    }

    return $msg_idx;
}

function trade_payment_parse_delivered_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_DELIVERED:(\d+)\]/', $body, $m)) {
        return null;
    }

    return ['pay_idx' => (int) $m[1]];
}

function trade_payment_build_delivered_body(int $pay_idx): string
{
    return '[PZ_PAY_DELIVERED:' . max(0, $pay_idx) . "]\n* 배송 완료 *\n"
        . '상품이 배송 완료되었습니다. 수령을 확인하신 후 구매확정해 주세요.';
}

function trade_payment_has_delivered_message(int $room_idx, int $pay_idx): bool
{
    if ($room_idx < 1 || $pay_idx < 1) {
        return false;
    }
    $like = db_escape('[PZ_PAY_DELIVERED:' . $pay_idx . ']%');

    return (bool) db_assoc(db_query("
        SELECT msg_idx
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '{$like}'
        LIMIT 1
    "));
}

/**
 * @param array<string, mixed> $row
 */
function trade_payment_is_delivery_complete(array $row): bool
{
    if (!trade_payment_has_tracking($row)) {
        return false;
    }
    $st = __DIR__ . '/../../lib/_sweettracker.php';
    if (!is_readable($st)) {
        return false;
    }
    require_once $st;
    if (!sweettracker_ready()) {
        return false;
    }

    $st_row = sweettracker_tracking_for_payment_row($row);

    return sweettracker_tracking_is_complete(is_array($st_row['info'] ?? null) ? $st_row['info'] : null);
}

/**
 * 배송 완료 감지 시 구매확정 안내 채팅
 *
 * @param array<string, mixed> $row
 */
function trade_payment_insert_delivered_chat_message(array $row): int
{
    $pay_idx   = (int) ($row['pay_idx'] ?? 0);
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    if ($pay_idx < 1 || $room_idx < 1 || $seller_mb < 1) {
        return 0;
    }
    if (trade_payment_has_delivered_message($room_idx, $pay_idx)) {
        return 0;
    }

    $body = trade_payment_build_delivered_body($pay_idx);
    $esc  = db_escape($body);
    $cols = 'room_idx, mb_idx, msg_body';
    $vals = "{$room_idx}, {$seller_mb}, '{$esc}'";
    if (trade_chat_msg_has_image_column()) {
        $cols .= ', msg_image';
        $vals .= ', NULL';
    }
    $cols .= ', msg_created_at';
    $vals .= ', NOW()';

    if (!db_query("INSERT INTO tb_trade_room_msg ({$cols}) VALUES ({$vals})")) {
        error_log('trade_payment_insert_delivered_chat_message: ' . db_last_error());

        return 0;
    }

    $msg_idx = (int) db_insert_id();
    if ($msg_idx < 1) {
        return 0;
    }

    trade_chat_bump_room($room_idx, $seller_mb);

    $buyer_mb = (int) ($row['buyer_mb_idx'] ?? 0);
    $tr_idx   = (int) ($row['tr_idx'] ?? 0);
    $label    = (string) ($row['product_label'] ?? '상품');
    try {
        require_once __DIR__ . '/../../lib/webpush.php';
        if ($buyer_mb > 0) {
            webpush_notify_trade_message(
                $buyer_mb,
                '포카존',
                $label . ' 배송 완료 · 구매확정',
                $tr_idx,
                $room_idx
            );
        }
    } catch (Throwable $e) {
        error_log('trade_payment_insert_delivered_chat_message webpush: ' . $e->getMessage());
    }

    return $msg_idx;
}

/**
 * @param array<string, mixed> $row
 */
function trade_payment_sync_delivery_status(array $row): void
{
    if (!trade_payment_fulfill_column_ready()) {
        return;
    }
    $pay_idx = (int) ($row['pay_idx'] ?? 0);
    if ($pay_idx < 1) {
        return;
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return;
    }
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return;
    }
    if (!trade_payment_has_tracking($row)) {
        return;
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return;
    }

    if (!trade_payment_is_delivery_complete($row)) {
        return;
    }

    $room_idx = (int) ($row['room_idx'] ?? 0);
    if ($fulfill < TRADE_PAYMENT_FULFILL_SHIPPING) {
        $sets = ['pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_SHIPPING];
        if (trade_payment_pay_column_exists('pay_shipped_at')) {
            $sets[] = 'pay_shipped_at = NOW()';
        }
        db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx} LIMIT 1");
        $row = trade_payment_row($pay_idx) ?: $row;
    }

    if ($room_idx > 0) {
        trade_payment_insert_delivered_chat_message($row);
    }
}

/**
 * 판매자·관리자 — 운송장·배송조회 없이 구매확정 단계로 진행 (테스트·수동 처리용)
 *
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>}
 */
function trade_payment_force_delivery_complete(int $pay_idx, int $actor_mb_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_fulfill_column_ready()) {
        return $fail('배송 확인 기능 DB가 없습니다. sql/migrate_tb_trade_payment_fulfill.sql 을 적용해 주세요.');
    }
    if ($pay_idx < 1 || $actor_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $actor    = db_assoc(db_query("SELECT mb_level FROM tb_member WHERE mb_idx = {$actor_mb_idx} LIMIT 1"));
    $is_admin  = $actor && (int) ($actor['mb_level'] ?? 0) >= 9;
    if (!$is_admin) {
        return $fail('테스트 강제 배송완료는 관리자만 처리할 수 있습니다.');
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('입금이 확인된 결제만 처리할 수 있습니다.');
    }
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('판매 취소된 결제는 처리할 수 없습니다.');
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return $fail('이미 구매확정된 거래입니다.');
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        $room_idx = (int) ($row['room_idx'] ?? 0);
        if ($room_idx > 0 && !trade_payment_has_delivered_message($room_idx, $pay_idx)) {
            trade_payment_insert_delivered_chat_message($row);
        }

        return ['ok' => true, 'payment' => trade_payment_public_payload($row, $actor_mb_idx)];
    }

    $sets = ['pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_SHIPPING];
    if (trade_payment_pay_column_exists('pay_shipped_at')) {
        $sets[] = 'pay_shipped_at = NOW()';
    }
    if (!db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx}")) {
        return $fail('배송완료 처리에 실패했습니다.');
    }

    $updated = trade_payment_row($pay_idx);
    if (!$updated) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $room_idx = (int) ($updated['room_idx'] ?? 0);
    if ($room_idx > 0) {
        trade_payment_insert_delivered_chat_message($updated);
    }

    return ['ok' => true, 'payment' => trade_payment_public_payload($updated, $actor_mb_idx)];
}

function trade_payment_sync_room_delivery_status(int $room_idx): void
{
    if ($room_idx < 1 || !trade_payment_tables_ready()) {
        return;
    }

    $st_confirmed = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $q = db_query("
        SELECT *
        FROM tb_trade_payment
        WHERE room_idx = {$room_idx}
          AND pay_status = {$st_confirmed}
          AND pay_tracking_no <> ''
        ORDER BY pay_idx ASC
    ");
    while ($row = db_assoc($q)) {
        trade_payment_sync_delivery_status($row);
    }
}

function trade_payment_parse_buyconf_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_BUYCONF:(\d+)\]/', $body, $m)) {
        return null;
    }

    $amount = 0;
    if (preg_match('/₩([0-9,]+)/u', $body, $am)) {
        $amount = (int) str_replace(',', '', $am[1]);
    }

    return [
        'pay_idx'         => (int) $m[1],
        'settle_amount'   => $amount,
    ];
}

/**
 * 입금 확인 완료 후 — 구매자 배송지 스냅샷 저장
 *
 * @param array<string, mixed> $row
 */
function trade_payment_after_deposit_confirmed(array $row): void
{
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return;
    }
    trade_payment_snapshot_buyer_ship_address($row);
    trade_payment_mark_listing_in_deal((int) ($row['tr_idx'] ?? 0));
}

/**
 * 입금완료 시 거래글 판매중(1) → 거래중(2). 거래완료(3)는 유지.
 */
function trade_payment_mark_listing_in_deal(int $tr_idx): void
{
    $tr_idx = (int) $tr_idx;
    if ($tr_idx < 1 || !db_table_exists('tb_trade')) {
        return;
    }

    db_query("
        UPDATE tb_trade
        SET tr_deal_status = 2, tr_updated_at = NOW()
        WHERE tr_idx = {$tr_idx}
          AND tr_status = 1
          AND tr_deal_status < 3
    ");
}

function trade_payment_has_addr_message(int $room_idx, int $pay_idx): bool
{
    if ($room_idx < 1 || $pay_idx < 1) {
        return false;
    }
    $like = db_escape('[PZ_PAY_ADDR:' . $pay_idx . ':%');

    return (bool) db_assoc(db_query("
        SELECT msg_idx
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND msg_body LIKE '{$like}'
        LIMIT 1
    "));
}

/**
 * @return array{pay_idx: int, addr_idx: int, lines: array<string, string>}|null
 */
function trade_payment_parse_addr_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_ADDR:(\d+):(\d+)\]/', $body, $m)) {
        return null;
    }

    $text = preg_replace('/^\[PZ_PAY_ADDR:[^\n]+\]\s*/', '', $body);
    $lines = [
        'label'   => '',
        'name'    => '',
        'phone'   => '',
        'zip'     => '',
        'address' => '',
        'message' => '',
    ];
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line === '* 배송지 *') {
            continue;
        }
        if (preg_match('/^배송지명:\s*(.+)$/u', $line, $mm)) {
            $lines['label'] = trim((string) $mm[1]);
        } elseif (preg_match('/^받는분:\s*(.+)$/u', $line, $mm)) {
            $lines['name'] = trim((string) $mm[1]);
        } elseif (preg_match('/^연락처:\s*(.+)$/u', $line, $mm)) {
            $lines['phone'] = trim((string) $mm[1]);
        } elseif (preg_match('/^우편번호:\s*(.+)$/u', $line, $mm)) {
            $lines['zip'] = trim((string) $mm[1]);
        } elseif (preg_match('/^주소:\s*(.+)$/u', $line, $mm)) {
            $lines['address'] = trim((string) $mm[1]);
        } elseif (preg_match('/^배송 메시지:\s*(.+)$/u', $line, $mm)) {
            $lines['message'] = trim((string) $mm[1]);
        }
    }

    return [
        'pay_idx'  => (int) $m[1],
        'addr_idx' => (int) $m[2],
        'lines'    => $lines,
    ];
}

/**
 * @param array<string, string> $lines
 */
function trade_payment_build_addr_body(int $pay_idx, int $addr_idx, array $lines): string
{
    $body = '[PZ_PAY_ADDR:' . max(0, $pay_idx) . ':' . max(0, $addr_idx) . "]\n* 배송지 *\n";
    if (trim((string) ($lines['label'] ?? '')) !== '') {
        $body .= '배송지명: ' . trim((string) $lines['label']) . "\n";
    }
    if (trim((string) ($lines['name'] ?? '')) !== '') {
        $body .= '받는분: ' . trim((string) $lines['name']) . "\n";
    }
    if (trim((string) ($lines['phone'] ?? '')) !== '') {
        $body .= '연락처: ' . trim((string) $lines['phone']) . "\n";
    }
    if (trim((string) ($lines['zip'] ?? '')) !== '') {
        $body .= '우편번호: ' . trim((string) $lines['zip']) . "\n";
    }
    if (trim((string) ($lines['address'] ?? '')) !== '') {
        $body .= '주소: ' . trim((string) $lines['address']) . "\n";
    }
    if (trim((string) ($lines['message'] ?? '')) !== '') {
        $body .= '배송 메시지: ' . trim((string) $lines['message']) . "\n";
    }
    if ($body === '[PZ_PAY_ADDR:' . max(0, $pay_idx) . ':' . max(0, $addr_idx) . "]\n* 배송지 *\n") {
        $body .= "등록된 배송지가 없습니다.\n마이페이지 > 배송지 주소록에서 등록해 주세요.\n";
    }

    return rtrim($body);
}

/**
 * @param array<string, mixed> $row tb_trade_payment
 */
function trade_payment_send_buyer_address_chat(array $row): int
{
    $pay_idx   = (int) ($row['pay_idx'] ?? 0);
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $buyer_mb  = (int) ($row['buyer_mb_idx'] ?? 0);
    if ($pay_idx < 1 || $room_idx < 1 || $buyer_mb < 1) {
        return 0;
    }
    if (trade_payment_has_addr_message($room_idx, $pay_idx)) {
        return 0;
    }
    if (trade_payment_fulfill_column_ready()) {
        $fulfill = trade_payment_fulfill_status_from_row($row);
        if ($fulfill >= TRADE_PAYMENT_FULFILL_ADDR_SENT) {
            return 0;
        }
    }

    require_once __DIR__ . '/../../lib/_member_address.php';

    $addr = member_address_table_ready() ? member_address_get_default($buyer_mb) : null;
    $lines = $addr ? member_address_format_lines($addr) : [];
    $addr_idx = $addr ? (int) ($addr['addr_idx'] ?? 0) : 0;
    $body = trade_payment_build_addr_body($pay_idx, $addr_idx, $lines);
    $esc  = db_escape($body);

    $cols = 'room_idx, mb_idx, msg_body';
    $vals = "{$room_idx}, {$buyer_mb}, '{$esc}'";
    if (trade_payment_column_exists('msg_type')) {
        $cols .= ', msg_type';
        $vals .= ", 'payment_address'";
    }
    if (trade_payment_column_exists('msg_pay_idx')) {
        $cols .= ', msg_pay_idx';
        $vals .= ", {$pay_idx}";
    }
    if (trade_chat_msg_has_image_column()) {
        $cols .= ', msg_image';
        $vals .= ', NULL';
    }
    $cols .= ', msg_created_at';
    $vals .= ', NOW()';

    if (!db_query("INSERT INTO tb_trade_room_msg ({$cols}) VALUES ({$vals})")) {
        error_log('trade_payment_send_buyer_address_chat: ' . db_last_error());

        return 0;
    }

    $msg_idx = (int) db_insert_id();
    if ($msg_idx < 1) {
        return 0;
    }

    if (trade_payment_fulfill_column_ready()) {
        $sets = ['pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_ADDR_SENT];
        if (trade_payment_pay_column_exists('pay_addr_msg_idx')) {
            $sets[] = 'pay_addr_msg_idx = ' . $msg_idx;
        }
        db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx}");
    }

    db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");

    return $msg_idx;
}

/**
 * 구매자 — 배송 확인
 *
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>}
 */
function trade_payment_confirm_shipping(int $pay_idx, int $buyer_mb_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_fulfill_column_ready()) {
        return $fail('배송 확인 기능 DB가 없습니다. sql/migrate_tb_trade_payment_fulfill.sql 을 적용해 주세요.');
    }
    if ($pay_idx < 1 || $buyer_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return $fail('구매자만 배송 확인을 할 수 있습니다.');
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('입금이 확인된 결제만 배송 확인할 수 있습니다.');
    }
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('판매 취소된 결제는 배송 확인할 수 없습니다.');
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        return ['ok' => true, 'payment' => trade_payment_public_payload($row, $buyer_mb_idx)];
    }
    if ($fulfill < TRADE_PAYMENT_FULFILL_ADDR_SENT) {
        return $fail('판매자가 운송장을 등록한 후 배송 확인할 수 있습니다.');
    }

    $room_idx = (int) ($row['room_idx'] ?? 0);
    $sets     = ['pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_SHIPPING];
    if (trade_payment_pay_column_exists('pay_shipped_at')) {
        $sets[] = 'pay_shipped_at = NOW()';
    }
    if (!db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx}")) {
        return $fail('배송 확인 처리에 실패했습니다.');
    }

    $updated = trade_payment_row($pay_idx);
    if (!$updated) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    if ($room_idx > 0) {
        $body = '[PZ_PAY_SHIP:' . $pay_idx . "]\n* 배송 확인 *\n상품 수령을 확인했습니다.";
        $esc  = db_escape($body);
        db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$buyer_mb_idx}, '{$esc}', NOW())
        ");
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    return ['ok' => true, 'payment' => trade_payment_public_payload($updated, $buyer_mb_idx)];
}

/**
 * 구매확정 실행 (판매자 캐시 정산 · 플랫폼 수수료 차감)
 *
 * @param array{buyer_mb_idx?: int, auto?: bool} $options
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>, seller_amount?: int, fee?: int, auto?: bool}
 */
function trade_payment_apply_purchase_confirm(int $pay_idx, array $options = []): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    $auto         = !empty($options['auto']);
    $buyer_mb_idx = (int) ($options['buyer_mb_idx'] ?? 0);

    if (!trade_payment_fulfill_column_ready()) {
        return $fail('구매확정 기능 DB가 없습니다. sql/migrate_tb_trade_payment_fulfill.sql 을 적용해 주세요.');
    }
    if (!trade_payment_fee_table_ready()) {
        return $fail('거래 수수료 DB가 없습니다. sql/migrate_tb_trade_payment_fee.sql 을 적용해 주세요.');
    }
    require_once __DIR__ . '/../../lib/_member_cash.php';
    if (!member_cash_column_ready()) {
        return $fail('판매자 정산을 위해 sql/migrate_tb_member_cash.sql 을 적용해 주세요.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if (!$auto) {
        if ($buyer_mb_idx < 1) {
            return $fail('잘못된 요청입니다.');
        }
        if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
            return $fail('구매자만 구매확정할 수 있습니다.');
        }
    } else {
        $buyer_mb_idx = (int) ($row['buyer_mb_idx'] ?? 0);
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('입금이 확인된 결제만 구매확정할 수 있습니다.');
    }
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return $fail('판매 취소된 결제는 구매확정할 수 없습니다.');
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return [
            'ok'      => false,
            'error'   => '이미 구매가 확정된 건입니다.',
            'payment' => trade_payment_public_payload($row, $buyer_mb_idx),
        ];
    }

    $room_idx = (int) ($row['room_idx'] ?? 0);
    if (!$auto) {
        $row     = trade_payment_advance_to_shipping_if_delivered($row);
        $fulfill = trade_payment_fulfill_status_from_row($row);
    }
    if ($fulfill < TRADE_PAYMENT_FULFILL_SHIPPING) {
        return $fail('배송 확인 후 구매확정할 수 있습니다.');
    }

    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $gross     = trade_payment_settlement_gross_from_row($row);
    $tr_idx    = (int) ($row['tr_idx'] ?? 0);
    $label     = (string) ($row['product_label'] ?? '상품');
    if ($seller_mb < 1 || $gross < 1) {
        return $fail('정산 금액이 올바르지 않습니다.');
    }

    $settle     = trade_payment_settlement_amounts($gross);
    $fee_rate   = (int) $settle['fee_rate'];
    $fee_amt    = (int) $settle['fee'];
    $seller_amt = (int) $settle['seller_amount'];
    if ($seller_amt < 1) {
        return $fail('정산 금액이 너무 적습니다.');
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('구매확정 처리를 시작하지 못했습니다.');
    }

    $sets = [
        'pay_fulfill_status = ' . TRADE_PAYMENT_FULFILL_PURCHASE,
        'pay_seller_settle_amount = ' . $seller_amt,
    ];
    if (trade_payment_platform_fee_column_ready()) {
        $sets[] = 'pay_platform_fee = ' . $fee_amt;
    }
    if (trade_payment_pay_column_exists('pay_buyer_confirmed_at')) {
        $sets[] = 'pay_buyer_confirmed_at = NOW()';
    }
    $ok = db_query('
        UPDATE tb_trade_payment
        SET ' . implode(', ', $sets) . "
        WHERE pay_idx = {$pay_idx}
          AND pay_fulfill_status = " . TRADE_PAYMENT_FULFILL_SHIPPING . '
        LIMIT 1
    ');
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('구매확정 저장에 실패했습니다.');
    }

    $auto_flag = $auto ? 1 : 0;
    $fee_ok    = db_query("
        INSERT INTO tb_trade_payment_fee (
            pay_idx, mb_idx, tr_idx,
            tpf_gross_amount, tpf_fee_rate, tpf_fee_amount, tpf_seller_amount,
            tpf_auto_confirmed, tpf_created_at
        ) VALUES (
            {$pay_idx}, {$seller_mb}, " . ($tr_idx > 0 ? (string) $tr_idx : 'NULL') . ",
            {$gross}, {$fee_rate}, {$fee_amt}, {$seller_amt},
            {$auto_flag}, NOW()
        )
    ");
    if (!$fee_ok) {
        mysqli_rollback($conn);

        return $fail('수수료 내역 저장에 실패했습니다.');
    }

    $cash_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash + {$seller_amt}
        WHERE mb_idx = {$seller_mb}
        LIMIT 1
    ");
    if (!$cash_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('판매자 캐시 적립에 실패했습니다.');
    }

    $memo = '거래 구매확정: ' . $label . ' (pay#' . $pay_idx . ', 수수료 ₩' . number_format($fee_amt) . ')';
    $coupon_subsidy = max(0, $gross - max(0, (int) ($row['pay_amount'] ?? 0)));
    if ($coupon_subsidy > 0) {
        $memo .= ', 쿠폰지원 ₩' . number_format($coupon_subsidy);
    }
    if (member_cash_log_table_ready()) {
        $bal = member_cash_balance($seller_mb);
        if (!member_cash_log_insert($seller_mb, $seller_amt, $bal, 'trade_settle', $memo)) {
            mysqli_rollback($conn);

            return $fail('정산 내역 저장에 실패했습니다.');
        }
    }

    mysqli_commit($conn);

    if ($room_idx > 0 && $buyer_mb_idx > 0) {
        if ($auto) {
            $body = '[PZ_PAY_BUYCONF:' . $pay_idx . "]\n* 구매확정(자동) *\n배송확인 후 "
                . (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS . '일이 경과하여 구매가 자동 확정되었습니다. 판매자에게 ₩'
                . number_format($seller_amt) . '이 정산되었습니다.';
        } else {
            $body = '[PZ_PAY_BUYCONF:' . $pay_idx . "]\n* 구매확정 *\n구매가 확정되었습니다. 판매자에게 ₩"
                . number_format($seller_amt) . '이 정산되었습니다.';
        }
        $esc = db_escape($body);
        db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$buyer_mb_idx}, '{$esc}', NOW())
        ");
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    try {
        require_once __DIR__ . '/../../lib/webpush.php';
        if ($seller_mb > 0) {
            $seller_msg = $label . ' 구매확정 · ₩' . number_format($seller_amt) . ' 캐시 적립';
            if ($fee_amt > 0) {
                $seller_msg .= ' (수수료 ₩' . number_format($fee_amt) . ')';
            }
            webpush_notify_trade_message($seller_mb, '포카존', $seller_msg, $tr_idx, $room_idx);
        }
        if ($buyer_mb_idx > 0) {
            $buyer_msg = $auto
                ? $label . ' 구매가 자동 확정되었습니다.'
                : $label . ' 구매확정이 완료되었습니다.';
            webpush_notify_trade_message($buyer_mb_idx, '포카존', $buyer_msg, $tr_idx, $room_idx);
        }
    } catch (Throwable $e) {
        error_log('trade_payment_apply_purchase_confirm webpush: ' . $e->getMessage());
    }

    $updated = trade_payment_row($pay_idx);

    return [
        'ok'            => true,
        'seller_amount' => $seller_amt,
        'fee'           => $fee_amt,
        'auto'          => $auto,
        'payment'       => trade_payment_public_payload($updated ?: $row, $buyer_mb_idx),
    ];
}

/**
 * 구매자 — 구매확정 (판매자 캐시 정산)
 *
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>, seller_amount?: int, fee?: int}
 */
function trade_payment_confirm_purchase(int $pay_idx, int $buyer_mb_idx): array
{
    return trade_payment_apply_purchase_confirm($pay_idx, [
        'buyer_mb_idx' => $buyer_mb_idx,
        'auto'         => false,
    ]);
}

/**
 * 배송확인 후 N일 경과 건 자동 구매확정 (cron)
 *
 * @return array{ok: bool, error?: string, processed?: int, confirmed?: int, failed?: int, pay_idxs?: int[]}
 */
function trade_payment_auto_confirm_purchases(?int $limit = null): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_fulfill_column_ready()) {
        return $fail('구매확정 기능 DB가 없습니다.');
    }
    if (!trade_payment_pay_column_exists('pay_shipped_at')) {
        return $fail('배송확인 시각 컬럼이 없습니다. sql/migrate_tb_trade_payment_fulfill.sql 을 적용해 주세요.');
    }
    if (!trade_payment_fee_table_ready()) {
        return $fail('거래 수수료 DB가 없습니다. sql/migrate_tb_trade_payment_fee.sql 을 적용해 주세요.');
    }

    $limit = $limit !== null ? max(1, min(200, $limit)) : 50;
    $days  = max(1, (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS);
    $st_ok = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $st_ship = (int) TRADE_PAYMENT_FULFILL_SHIPPING;

    $pay_idxs = [];
    $rs       = db_query("
        SELECT pay_idx
        FROM tb_trade_payment
        WHERE pay_status = {$st_ok}
          AND pay_fulfill_status = {$st_ship}
          AND pay_shipped_at IS NOT NULL
          AND pay_shipped_at <= DATE_SUB(NOW(), INTERVAL {$days} DAY)
        ORDER BY pay_shipped_at ASC
        LIMIT {$limit}
    ");
    while ($row = db_assoc($rs)) {
        $pid = (int) ($row['pay_idx'] ?? 0);
        if ($pid > 0) {
            $pay_idxs[] = $pid;
        }
    }

    $confirmed = 0;
    $failed    = 0;
    foreach ($pay_idxs as $pay_idx) {
        $result = trade_payment_apply_purchase_confirm($pay_idx, ['auto' => true]);
        if (!empty($result['ok'])) {
            $confirmed++;
        } else {
            $failed++;
            error_log('trade_payment_auto_confirm pay#' . $pay_idx . ': ' . (string) ($result['error'] ?? 'unknown'));
        }
    }

    return [
        'ok'        => true,
        'processed' => count($pay_idxs),
        'confirmed' => $confirmed,
        'failed'    => $failed,
        'pay_idxs'  => $pay_idxs,
    ];
}

/**
 * 판매자 — 구매확정 후 거래글 판매완료(tr_deal_status=3)
 *
 * @return array{ok: bool, error?: string, message?: string, tr_deal_status?: int, payment?: array<string, mixed>}
 */
function trade_payment_mark_trade_sold(int $pay_idx, int $seller_mb_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($pay_idx < 1 || $seller_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if ((int) ($row['seller_mb_idx'] ?? 0) !== $seller_mb_idx) {
        return $fail('판매자만 판매완료 처리할 수 있습니다.');
    }
    if (trade_payment_fulfill_status_from_row($row) < TRADE_PAYMENT_FULFILL_PURCHASE) {
        return $fail('구매확정된 거래만 판매완료 처리할 수 있습니다.');
    }

    $tr_idx = (int) ($row['tr_idx'] ?? 0);
    if ($tr_idx < 1) {
        return $fail('거래글 정보를 찾을 수 없습니다.');
    }

    $trade = db_assoc(db_query("
        SELECT tr_idx, mb_idx, tr_deal_status
        FROM tb_trade
        WHERE tr_idx = {$tr_idx}
          AND tr_status = 1
        LIMIT 1
    "));
    if (!$trade || (int) ($trade['mb_idx'] ?? 0) !== $seller_mb_idx) {
        return $fail('거래글을 찾을 수 없거나 권한이 없습니다.');
    }

    $deal = (int) ($trade['tr_deal_status'] ?? 1);
    if ($deal === 3) {
        return [
            'ok'             => true,
            'message'        => '이미 판매완료 상태입니다.',
            'tr_deal_status' => 3,
            'payment'        => trade_payment_public_payload($row, $seller_mb_idx),
        ];
    }

    if (!db_query("UPDATE tb_trade SET tr_deal_status = 3, tr_updated_at = NOW() WHERE tr_idx = {$tr_idx} AND tr_status = 1 LIMIT 1")) {
        return $fail('판매완료 상태 변경에 실패했습니다.');
    }

    return [
        'ok'             => true,
        'message'        => '거래글이 판매완료로 변경되었습니다.',
        'tr_deal_status' => 3,
        'payment'        => trade_payment_public_payload($row, $seller_mb_idx),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function trade_payment_open_request_from_messages(int $room_idx): ?array
{
    return trade_payment_room_open_request($room_idx);
}

/**
 * @param array<string, mixed>|null $row
 * @return array<string, mixed>|null
 */
function trade_payment_active_request_api_payload(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    $status = (int) ($row['pay_status'] ?? 0);
    $msg_idx = (int) ($row['request_msg_idx'] ?? 0);

    return [
        'pay_idx'      => (int) ($row['pay_idx'] ?? 0),
        'msg_idx'      => $msg_idx,
        'amount'       => (int) ($row['pay_amount'] ?? 0),
        'status'       => $status,
        'status_label' => trade_payment_status_label($status),
        'can_cancel'   => !empty($row['can_cancel']),
    ];
}

/**
 * @return array{amount: int}|null
 */
function trade_payment_parse_cancel_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_CANCEL:(\d+)\]/', $body, $m)) {
        return null;
    }

    return ['amount' => (int) $m[1]];
}

function trade_payment_build_cancel_body(int $amount): string
{
    return '[PZ_PAY_CANCEL:' . max(0, $amount) . "]\n* 결제요청 취소 *\n결제요청을 취소하였습니다.";
}

/**
 * tb_trade_payment — 결제요청취소(pay_status=3) 로 표시 (행 삭제하지 않음)
 *
 * @return array<string, mixed>|null
 */
function trade_payment_mark_row_cancelled(int $pay_idx): ?array
{
    if ($pay_idx < 1 || !db_table_exists('tb_trade_payment')) {
        return null;
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return null;
    }

    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_cancelled_status($status)) {
        return $row;
    }
    if (trade_payment_is_confirmed_status($status)) {
        return null;
    }

    $sets = ['pay_status = ' . TRADE_PAYMENT_STATUS_CANCELLED];
    if (trade_payment_pay_column_exists('pay_cancelled_at')) {
        $sets[] = 'pay_cancelled_at = NOW()';
    }
    if (!db_query('UPDATE tb_trade_payment SET ' . implode(', ', $sets) . " WHERE pay_idx = {$pay_idx}")) {
        return null;
    }

    return trade_payment_row($pay_idx);
}

function trade_payment_mark_request_message_cancelled(int $msg_idx, int $room_idx, int $amount): bool
{
    if ($msg_idx < 1 || $room_idx < 1) {
        return false;
    }

    $body = trade_payment_build_cancel_body($amount);
    $esc = db_escape($body);
    if (!db_query("UPDATE tb_trade_room_msg SET msg_body = '{$esc}' WHERE msg_idx = {$msg_idx} AND room_idx = {$room_idx}")) {
        return false;
    }

    if (trade_payment_column_exists('msg_type')) {
        db_query("UPDATE tb_trade_room_msg SET msg_type = 'payment_cancelled' WHERE msg_idx = {$msg_idx}");
    }
    trade_payment_set_msg_pay_status($msg_idx, TRADE_PAYMENT_STATUS_CANCELLED);

    return true;
}

function trade_payment_load_request_msg_row(int $msg_idx, int $room_idx): ?array
{
    if ($msg_idx < 1 || $room_idx < 1) {
        return null;
    }

    $pay_sel = trade_payment_column_exists('msg_pay_idx') ? ', msg_pay_idx' : '';

    return db_assoc(db_query("
        SELECT msg_idx, room_idx, mb_idx, msg_body{$pay_sel}
        FROM tb_trade_room_msg
        WHERE msg_idx = {$msg_idx} AND room_idx = {$room_idx}
        LIMIT 1
    ")) ?: null;
}

/**
 * 판매자 — 결제 요청 취소 (채팅 메시지 취소 표시 + DB pay_status=3)
 *
 * @return array{ok: bool, error?: string, already_confirmed?: bool, already_cancelled?: bool, request_msg_idx?: int, payment?: array<string, mixed>}
 */
function trade_payment_cancel_request(
    int $pay_idx,
    int $seller_mb_idx,
    int $room_idx,
    int $msg_idx = 0
): array {
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($seller_mb_idx < 1 || $room_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    if ($pay_idx > 0 && db_table_exists('tb_trade_payment')) {
        $pay_row = trade_payment_row($pay_idx);
        if ($pay_row) {
            if ((int) ($pay_row['room_idx'] ?? 0) !== $room_idx) {
                return $fail('대화방 정보가 일치하지 않습니다.');
            }
            if ((int) ($pay_row['seller_mb_idx'] ?? 0) !== $seller_mb_idx) {
                return $fail('판매자만 결제 요청을 취소할 수 있습니다.');
            }
            if ($msg_idx < 1) {
                $msg_idx = (int) ($pay_row['request_msg_idx'] ?? 0);
            }
        }
    }

    if ($msg_idx < 1) {
        return $fail('취소할 결제 요청을 찾을 수 없습니다.');
    }

    $msg_rw = trade_payment_load_request_msg_row($msg_idx, $room_idx);
    if (!$msg_rw) {
        return $fail('결제 요청 메시지를 찾을 수 없습니다.');
    }
    if ((int) ($msg_rw['mb_idx'] ?? 0) !== $seller_mb_idx) {
        return $fail('판매자만 결제 요청을 취소할 수 있습니다.');
    }

    if (trade_payment_parse_cancel_body((string) ($msg_rw['msg_body'] ?? '')) !== null) {
        return ['ok' => true, 'already_cancelled' => true, 'request_msg_idx' => $msg_idx];
    }

    $row = null;
    if ($pay_idx > 0 && db_table_exists('tb_trade_payment')) {
        $row = trade_payment_row($pay_idx);
    }
    if (!$row && function_exists('trade_payment_row_for_request_msg')) {
        $row = trade_payment_row_for_request_msg($msg_rw);
        if ($row) {
            $pay_idx = (int) ($row['pay_idx'] ?? 0);
        }
    }

    if ($row && trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return trade_payment_cancel_already_confirmed_response($msg_idx, $row, $seller_mb_idx);
    }
    if ($row && trade_payment_is_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return ['ok' => true, 'already_cancelled' => true, 'request_msg_idx' => $msg_idx];
    }

    $parsed = trade_payment_parse_request_body((string) ($msg_rw['msg_body'] ?? ''));
    $amount = $parsed ? (int) ($parsed['amount'] ?? 0) : 0;
    if ($amount < 1 && $row) {
        $amount = (int) ($row['pay_amount'] ?? 0);
    }

    if ($pay_idx > 0) {
        trade_payment_mark_row_cancelled($pay_idx);
    }

    trade_payment_delete_acks_for_request($room_idx, $msg_idx);
    if (!trade_payment_mark_request_message_cancelled($msg_idx, $room_idx, $amount)) {
        return $fail('결제 요청을 취소할 수 없습니다.' . trade_payment_db_error_suffix());
    }

    db_query('UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = ' . $room_idx);

    $out = ['ok' => true, 'request_msg_idx' => $msg_idx];
    if ($pay_idx > 0 && function_exists('trade_payment_row') && function_exists('trade_payment_public_payload')) {
        $cancelled_row = trade_payment_row($pay_idx);
        if ($cancelled_row && trade_payment_is_cancelled_status((int) ($cancelled_row['pay_status'] ?? 0))) {
            $out['payment'] = trade_payment_public_payload($cancelled_row, $seller_mb_idx);
        }
    }

    return $out;
}

/**
 * @return array{ok: bool, error?: string}
 */
function trade_payment_assert_can_create_request(int $room_idx, int $exclude_msg_idx = 0): array
{
    $active = trade_payment_list_active_requests($room_idx);
    foreach ($active as $item) {
        $msg_idx = (int) ($item['request_msg_idx'] ?? 0);
        if ($exclude_msg_idx > 0 && $msg_idx === $exclude_msg_idx) {
            continue;
        }
        return [
            'ok'    => false,
            'error' => '진행 중인 결제 요청이 있습니다. 취소한 뒤 다시 보내 주세요.',
        ];
    }

    return ['ok' => true];
}

/**
 * 채팅 본문 [PZ_PAY:금액] 또는 [PZ_PAY:금액:pay_idx] 파싱
 *
 * @return array{amount: int, pay_idx: int}|null
 */
function trade_payment_parse_request_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY:(\d+)(?::(\d+))?\]/', $body, $m)) {
        return null;
    }

    return [
        'amount'  => (int) $m[1],
        'pay_idx' => isset($m[2]) ? (int) $m[2] : 0,
    ];
}

/**
 * 채팅 본문 [PZ_PAY_ACK:금액:입금자명:요청msg_idx] 파싱
 *
 * @return array{amount: int, depositor: string, request_msg_idx: int}|null
 */
function trade_payment_parse_ack_body(string $body): ?array
{
    if (!preg_match('/^\[PZ_PAY_ACK:(\d+):([^:\]]+):(\d+)\]/', $body, $m)) {
        return null;
    }

    return [
        'amount'          => (int) $m[1],
        'depositor'       => (string) $m[2],
        'request_msg_idx' => (int) $m[3],
    ];
}

function trade_payment_update_msg_meta(int $msg_idx, int $pay_idx): void
{
    if ($msg_idx < 1 || !trade_payment_column_exists('msg_type')) {
        return;
    }
    $sets = ["msg_type = 'payment_request'"];
    if (trade_payment_column_exists('msg_pay_idx') && $pay_idx > 0) {
        $sets[] = 'msg_pay_idx = ' . (int) $pay_idx;
    }
    db_query('UPDATE tb_trade_room_msg SET ' . implode(', ', $sets) . " WHERE msg_idx = {$msg_idx}");
}

/**
 * 결제요청 채팅 메시지 → tb_trade_payment 생성·연결
 *
 * @return array{ok: bool, error?: string, pay_idx?: int}
 */
function trade_payment_create_from_chat_request(
    int $msg_idx,
    int $room_idx,
    int $tr_idx,
    int $seller_mb_idx,
    int $amount,
    string $product_label
): array {
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_tables_ready()) {
        return ['ok' => true];
    }
    if ($msg_idx < 1 || $room_idx < 1 || $tr_idx < 1 || $seller_mb_idx < 1) {
        return $fail('잘못된 결제 요청입니다.');
    }

    $parsed_amount = trade_payment_parse_amount((string) $amount);
    if (!$parsed_amount['ok']) {
        return $fail((string) ($parsed_amount['error'] ?? '결제 금액이 올바르지 않습니다.'));
    }
    $amount = (int) $parsed_amount['amount'];

    $existing = trade_payment_row_by_msg($msg_idx);
    if ($existing) {
        trade_payment_update_msg_meta($msg_idx, (int) $existing['pay_idx']);

        return ['ok' => true, 'pay_idx' => (int) $existing['pay_idx']];
    }

    $can_create = trade_payment_assert_can_create_request($room_idx, $msg_idx);
    if (!$can_create['ok']) {
        return $fail((string) ($can_create['error'] ?? '결제 요청을 보낼 수 없습니다.'));
    }

    $room = db_assoc(db_query("
        SELECT room_idx, tr_idx, buyer_mb_idx
        FROM tb_trade_room
        WHERE room_idx = {$room_idx} AND tr_idx = {$tr_idx}
        LIMIT 1
    "));
    if (!$room) {
        return $fail('대화방을 찾을 수 없습니다.');
    }

    $buyer_mb = (int) ($room['buyer_mb_idx'] ?? 0);
    if ($buyer_mb < 1) {
        return $fail('구매자 정보를 찾을 수 없습니다.');
    }

    $label = trim($product_label);
    if ($label === '') {
        $label = '상품';
    }
    if (mb_strlen($label) > 200) {
        $label = mb_substr($label, 0, 200);
    }
    $esc_label = db_escape($label);

    if (!db_query("
        INSERT INTO tb_trade_payment
            (room_idx, tr_idx, seller_mb_idx, buyer_mb_idx, pay_amount, product_label, pay_status, depositor_name, pay_created_at)
        VALUES
            ({$room_idx}, {$tr_idx}, {$seller_mb_idx}, {$buyer_mb}, {$amount}, '{$esc_label}', 0, '', NOW())
    ")) {
        return trade_payment_fail_db('결제 요청을 저장할 수 없습니다.');
    }

    $pay_idx = (int) db_insert_id();
    if ($pay_idx < 1) {
        return trade_payment_fail_db('결제 요청을 저장할 수 없습니다.');
    }

    if (trade_payment_pay_column_exists('request_msg_idx')) {
        db_query("UPDATE tb_trade_payment SET request_msg_idx = {$msg_idx} WHERE pay_idx = {$pay_idx}");
    }
    trade_payment_update_msg_meta($msg_idx, $pay_idx);

    return ['ok' => true, 'pay_idx' => $pay_idx];
}

/**
 * 입금 신청 채팅 메시지 → tb_trade_payment 갱신
 *
 * @param array{amount: int, depositor: string, request_msg_idx: int} $ack
 * @return array{ok: bool, error?: string, pay_idx?: int}
 */
function trade_payment_deposit_from_chat_ack(int $buyer_mb_idx, array $ack): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_deposit_sync_ready()) {
        return $fail('결제 DB가 설정되지 않았습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.');
    }

    $request_msg_idx = (int) ($ack['request_msg_idx'] ?? 0);
    if ($request_msg_idx < 1) {
        return $fail('결제 요청 메시지를 찾을 수 없습니다.');
    }

    $row = trade_payment_row_by_msg($request_msg_idx);
    if (!$row && $request_msg_idx > 0) {
        $req_msg = trade_payment_fetch_buyer_request_msg($request_msg_idx, $buyer_mb_idx);
        if ($req_msg) {
            $row = trade_payment_resolve_row_for_request_msg($req_msg);
        }
    }
    if ($row) {
        $pay_idx = (int) ($row['pay_idx'] ?? 0);
        if ($pay_idx < 1) {
            return $fail('결제 요청을 찾을 수 없습니다.');
        }
        if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
            return $fail('구매자만 입금 신청을 할 수 있습니다.');
        }
        if ((int) ($row['pay_status'] ?? 0) >= TRADE_PAYMENT_STATUS_SUBMITTED) {
            return ['ok' => true, 'pay_idx' => $pay_idx];
        }

        $result = trade_payment_submit_deposit($pay_idx, $buyer_mb_idx, (string) ($ack['depositor'] ?? ''));
        if (!$result['ok']) {
            return $fail((string) ($result['error'] ?? '입금 신청을 저장할 수 없습니다.'));
        }

        return ['ok' => true, 'pay_idx' => $pay_idx];
    }

    $req_msg = db_assoc(db_query("
        SELECT m.msg_idx, m.room_idx, m.mb_idx, m.msg_body, r.tr_idx, r.buyer_mb_idx
        FROM tb_trade_room_msg m
        INNER JOIN tb_trade_room r ON r.room_idx = m.room_idx
        WHERE m.msg_idx = {$request_msg_idx}
        LIMIT 1
    "));
    if (!$req_msg) {
        return $fail('결제 요청 메시지를 찾을 수 없습니다.');
    }
    if ((int) ($req_msg['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return $fail('구매자만 입금 신청을 할 수 있습니다.');
    }

    $parsed = trade_payment_parse_request_body((string) ($req_msg['msg_body'] ?? ''));
    if (!$parsed) {
        return $fail('결제 요청 메시지가 아닙니다.');
    }

    $ack_amount = (int) ($ack['amount'] ?? 0);
    if ($ack_amount > 0 && $ack_amount !== (int) $parsed['amount']) {
        return $fail('입금 금액이 결제 요청과 일치하지 않습니다.');
    }

    $trade = db_assoc(db_query('
        SELECT tr_idx, mb_idx, tr_card_name, tr_title
        FROM tb_trade
        WHERE tr_idx = ' . (int) $req_msg['tr_idx'] . '
        LIMIT 1
    '));
    $label = $trade ? trade_chat_inquiry_product_label($trade) : '상품';
    $seller_mb = $trade ? (int) ($trade['mb_idx'] ?? 0) : (int) ($req_msg['mb_idx'] ?? 0);

    $created = trade_payment_insert_deposit_from_ack(
        $request_msg_idx,
        (int) $req_msg['room_idx'],
        (int) $req_msg['tr_idx'],
        $seller_mb,
        $buyer_mb_idx,
        (int) $parsed['amount'],
        (string) ($ack['depositor'] ?? ''),
        $label
    );
    if (!$created['ok']) {
        return $created;
    }

    return ['ok' => true, 'pay_idx' => (int) ($created['pay_idx'] ?? 0)];
}

/**
 * 채팅 결제 요청 없이 구매자 입금 신청만 tb_trade_payment 에 기록
 *
 * @return array{ok: bool, error?: string, pay_idx?: int}
 */
function trade_payment_insert_deposit_from_ack(
    int $request_msg_idx,
    int $room_idx,
    int $tr_idx,
    int $seller_mb_idx,
    int $buyer_mb_idx,
    int $amount,
    string $depositor_name,
    string $product_label
): array {
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!trade_payment_deposit_sync_ready()) {
        return $fail('결제 DB가 설정되지 않았습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.');
    }

    $req_msg = null;
    if ($request_msg_idx > 0) {
        $req_msg = trade_payment_fetch_buyer_request_msg($request_msg_idx, $buyer_mb_idx);
        if ($req_msg && $seller_mb_idx < 1) {
            $trade_hint = db_assoc(db_query('
                SELECT mb_idx FROM tb_trade
                WHERE tr_idx = ' . (int) ($req_msg['tr_idx'] ?? 0) . '
                LIMIT 1
            '));
            $seller_mb_idx = $trade_hint ? (int) ($trade_hint['mb_idx'] ?? 0) : (int) ($req_msg['mb_idx'] ?? 0);
        }
        if ($req_msg) {
            $existing = trade_payment_resolve_row_for_request_msg($req_msg);
            if ($existing && (int) ($existing['pay_idx'] ?? 0) > 0) {
                $pay_idx = (int) $existing['pay_idx'];
                trade_payment_link_row_to_request_msg($pay_idx, $request_msg_idx);

                return ['ok' => true, 'pay_idx' => $pay_idx];
            }
        }
    }

    $parsed_name = trade_payment_parse_depositor_name($depositor_name);
    if (!$parsed_name['ok']) {
        return $fail((string) ($parsed_name['error'] ?? '입금자명이 올바르지 않습니다.'));
    }
    $parsed_amount = trade_payment_parse_amount((string) $amount);
    if (!$parsed_amount['ok']) {
        return $fail((string) ($parsed_amount['error'] ?? '입금 금액이 올바르지 않습니다.'));
    }

    $amount   = (int) $parsed_amount['amount'];
    $name     = (string) $parsed_name['name'];
    $esc_name = db_escape($name);
    $label    = trim($product_label);
    if ($label === '') {
        $label = '상품';
    }
    if (mb_strlen($label) > 200) {
        $label = mb_substr($label, 0, 200);
    }
    $esc_label = db_escape($label);

    if ($seller_mb_idx < 1 || $buyer_mb_idx < 1) {
        return $fail('판매자·구매자 정보를 확인할 수 없습니다.');
    }

    $cols = 'room_idx, tr_idx, seller_mb_idx, buyer_mb_idx, pay_amount, product_label, pay_status, depositor_name, pay_created_at, pay_submitted_at';
    $vals = "{$room_idx}, {$tr_idx}, {$seller_mb_idx}, {$buyer_mb_idx}, {$amount}, '{$esc_label}', "
        . TRADE_PAYMENT_STATUS_SUBMITTED . ", '{$esc_name}', NOW(), NOW()";

    if (trade_payment_pay_column_exists('request_msg_idx')) {
        $cols .= ', request_msg_idx';
        $vals .= ", {$request_msg_idx}";
    }
    if (trade_payment_pay_column_exists('pay_bank_name')) {
        $snap = trade_payment_bank_snapshot();
        $cols .= ', pay_bank_name, pay_account_no, pay_account_holder';
        $vals .= ", '" . db_escape($snap['bank_name']) . "', '" . db_escape($snap['account_no']) . "', '"
            . db_escape($snap['account_holder']) . "'";
    }

    if (!db_query("INSERT INTO tb_trade_payment ({$cols}) VALUES ({$vals})")) {
        if ($req_msg) {
            $again = trade_payment_resolve_row_for_request_msg($req_msg);
            if ($again && (int) ($again['pay_idx'] ?? 0) > 0) {
                $pay_idx = (int) $again['pay_idx'];
                trade_payment_link_row_to_request_msg($pay_idx, $request_msg_idx);

                return ['ok' => true, 'pay_idx' => $pay_idx];
            }
        }

        return trade_payment_fail_db('입금 신청을 저장할 수 없습니다.');
    }

    $pay_idx = (int) db_insert_id();
    if ($pay_idx < 1 && $req_msg) {
        $again = trade_payment_resolve_row_for_request_msg($req_msg);
        if ($again && (int) ($again['pay_idx'] ?? 0) > 0) {
            $pay_idx = (int) $again['pay_idx'];
        }
    }
    if ($pay_idx < 1) {
        return trade_payment_fail_db('입금 신청을 저장할 수 없습니다.');
    }

    trade_payment_update_msg_meta($request_msg_idx, $pay_idx);

    return ['ok' => true, 'pay_idx' => $pay_idx];
}

function trade_payment_chat_push_preview(string $body): string
{
    if (trade_payment_parse_done_body($body) !== null) {
        $text = preg_replace('/^\[PZ_PAY_DONE:[^\n]+\]\s*/', '', $body);

        return trim((string) $text) !== '' ? trim((string) $text) : '입금 확인 완료';
    }
    if (trade_payment_parse_ack_body($body) !== null) {
        $text = preg_replace('/^\[PZ_PAY_ACK:[^\n]+\]\s*/', '', $body);

        return trim((string) $text);
    }
    if (trade_payment_parse_cancel_body($body) !== null) {
        return '입금요청을 취소하였습니다.';
    }
    if (trade_payment_parse_request_body($body) !== null) {
        $text = preg_replace('/^\[PZ_PAY:\d+(?::\d+)?\]\s*/', '', $body);

        return trim((string) $text) !== '' ? trim((string) $text) : '결제 요청';
    }
    if (trade_payment_parse_addr_body($body) !== null) {
        return '배송지';
    }
    if (preg_match('/^\[PZ_PAY_SHIP:/', $body)) {
        return '배송 확인';
    }
    if (trade_payment_parse_track_body($body) !== null) {
        return '택배 발송';
    }
    if (trade_payment_parse_delivered_body($body) !== null) {
        return '배송 완료';
    }
    if (trade_payment_parse_buyconf_body($body) !== null) {
        return '구매확정';
    }
    if (preg_match('/^\[PZ_PAY_SALECANCEL:/', $body)) {
        $text = preg_replace('/^\[PZ_PAY_SALECANCEL:[^\n]+\]\s*/', '', $body);

        return trim((string) $text) !== '' ? trim((string) $text) : '판매 취소';
    }

    return $body;
}

/**
 * 채팅 메시지 저장 직후 결제 테이블 연동
 *
 * @return array{error?: string, pay_idx?: int, skip_push?: bool}
 */
function trade_payment_after_chat_message_insert(
    int $msg_idx,
    int $room_idx,
    int $tr_idx,
    int $sender_mb_idx,
    bool $is_seller,
    string $body,
    int $seller_mb_idx,
    string $product_label = ''
): array {
    if ($body === '') {
        return [];
    }

    $req = trade_payment_parse_request_body($body);
    if ($req && $is_seller) {
        trade_payment_init_request_msg_status($msg_idx);

        if (!trade_payment_sync_to_db() || !trade_payment_tables_ready()) {
            return [];
        }

        $result = trade_payment_create_from_chat_request(
            $msg_idx,
            $room_idx,
            $tr_idx,
            $seller_mb_idx,
            (int) $req['amount'],
            $product_label
        );
        if (!$result['ok']) {
            return ['error' => (string) ($result['error'] ?? '결제 요청 저장에 실패했습니다.')];
        }

        return ['pay_idx' => (int) ($result['pay_idx'] ?? 0)];
    }

    $ack = trade_payment_parse_ack_body($body);
    if ($ack && !$is_seller) {
        if (!trade_payment_deposit_sync_ready()) {
            return ['error' => '결제 DB가 설정되지 않았습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.'];
        }

        $result = trade_payment_deposit_from_chat_ack($sender_mb_idx, $ack);
        if (!$result['ok']) {
            return ['error' => (string) ($result['error'] ?? '입금 신청 저장에 실패했습니다.')];
        }

        $req_msg = (int) ($ack['request_msg_idx'] ?? 0);
        if ($req_msg > 0) {
            trade_payment_set_msg_pay_status($req_msg, TRADE_PAYMENT_STATUS_SUBMITTED);
        }

        return [
            'pay_idx'   => (int) ($result['pay_idx'] ?? 0),
            'skip_push' => true,
        ];
    }

    return [];
}

function trade_payment_chat_only_payload(
    array $rw,
    int $viewer_mb_idx,
    int $room_idx,
    int $seller_mb_idx,
    string $product_label
): ?array {
    $msg_idx = (int) ($rw['msg_idx'] ?? 0);
    $parsed  = trade_payment_parse_request_body((string) ($rw['msg_body'] ?? ''));
    if (!$parsed || $msg_idx < 1) {
        return null;
    }

    if ($room_idx > 0) {
        $confirmed = trade_payment_resolve_confirmed_for_request($room_idx, $rw, $seller_mb_idx);
        if ($confirmed) {
            return trade_payment_public_payload($confirmed, $viewer_mb_idx);
        }
    }

    $status = trade_payment_request_msg_status($room_idx, $rw);
    if ($room_idx > 0 && trade_payment_has_ack_for_request($room_idx, $msg_idx)
        && $status < TRADE_PAYMENT_STATUS_SUBMITTED) {
        $status = TRADE_PAYMENT_STATUS_SUBMITTED;
    }
    $is_buyer = $seller_mb_idx > 0 && $viewer_mb_idx !== $seller_mb_idx;
    $is_seller = $seller_mb_idx > 0 && $viewer_mb_idx === $seller_mb_idx;
    $pay_idx = (int) ($rw['msg_pay_idx'] ?? 0);
    $confirmed = trade_payment_is_confirmed_status($status);

    return [
        'pay_idx'        => $pay_idx,
        'amount'         => (int) $parsed['amount'],
        'product_label'  => $product_label !== '' ? $product_label : '상품',
        'status'         => $status,
        'status_label'   => trade_payment_status_label($status),
        'is_confirmed'   => $confirmed,
        'is_cancelled'   => trade_payment_is_cancelled_status($status),
        'depositor_name' => '',
        'can_pay'        => $is_buyer && $status < TRADE_PAYMENT_STATUS_SUBMITTED,
        'can_cancel'     => $is_seller && trade_payment_seller_can_cancel($status),
        'bank'           => trade_payment_bank_info(),
        'fulfill_status' => TRADE_PAYMENT_FULFILL_NONE,
        'ship_url'       => $confirmed && $pay_idx > 0 ? trade_payment_ship_url($pay_idx) : '',
        'has_tracking'   => false,
        'can_manage_shipping' => $is_seller && $confirmed && $pay_idx > 0,
        'can_confirm_shipping' => false,
        'can_confirm_purchase' => false,
        'is_purchase_confirmed' => false,
    ];
}

/**
 * @param array<string, mixed> $rw
 * @return array<string, mixed>|null
 */
function trade_payment_message_api_payload(
    array $rw,
    int $viewer_mb_idx,
    int $room_idx = 0,
    int $seller_mb_idx = 0,
    string $product_label = '상품'
): ?array {
    $msg_idx = (int) ($rw['msg_idx'] ?? 0);
    if ($msg_idx < 1) {
        return null;
    }

    $body = (string) ($rw['msg_body'] ?? '');

    $parsed_done = trade_payment_parse_done_body($body);
    if ($parsed_done) {
        $pay_idx = (int) ($parsed_done['pay_idx'] ?? 0);
        if ($pay_idx > 0 && trade_payment_tables_ready()) {
            $row = trade_payment_row($pay_idx);
            if ($row) {
                return trade_payment_public_payload($row, $viewer_mb_idx);
            }
        }
        $is_seller = $seller_mb_idx > 0 && $viewer_mb_idx === $seller_mb_idx;

        return [
            'pay_idx'        => $pay_idx,
            'amount'         => (int) ($parsed_done['amount'] ?? 0),
            'product_label'  => $product_label !== '' ? $product_label : '상품',
            'status'         => TRADE_PAYMENT_STATUS_CONFIRMED,
            'status_label'   => trade_payment_status_label(TRADE_PAYMENT_STATUS_CONFIRMED),
            'is_confirmed'   => true,
            'is_cancelled'   => false,
            'depositor_name' => (string) ($parsed_done['depositor'] ?? ''),
            'can_pay'        => false,
            'can_cancel'     => false,
            'bank'           => trade_payment_bank_info(),
            'fulfill_status' => TRADE_PAYMENT_FULFILL_NONE,
            'ship_url'       => $pay_idx > 0 ? trade_payment_ship_url($pay_idx) : '',
            'has_tracking'   => false,
            'can_manage_shipping' => $is_seller && $pay_idx > 0,
            'can_confirm_shipping' => false,
            'can_confirm_purchase' => false,
            'is_purchase_confirmed' => false,
        ];
    }

    $parsed_cancel = trade_payment_parse_cancel_body($body);
    if ($parsed_cancel) {
        if (trade_payment_tables_ready()) {
            $row = trade_payment_row_by_msg($msg_idx);
            if (!$row) {
                $msg_pay_idx = (int) ($rw['msg_pay_idx'] ?? 0);
                if ($msg_pay_idx > 0) {
                    $row = trade_payment_row($msg_pay_idx);
                }
            }
            if ($row) {
                return trade_payment_public_payload($row, $viewer_mb_idx);
            }
        }

        $amount = (int) ($parsed_cancel['amount'] ?? 0);
        $is_buyer = $seller_mb_idx > 0 && $viewer_mb_idx !== $seller_mb_idx;

        return [
            'pay_idx'        => 0,
            'amount'         => $amount,
            'product_label'  => $product_label !== '' ? $product_label : '상품',
            'status'         => TRADE_PAYMENT_STATUS_CANCELLED,
            'status_label'   => trade_payment_status_label(TRADE_PAYMENT_STATUS_CANCELLED),
            'is_confirmed'   => false,
            'is_cancelled'   => true,
            'depositor_name' => '',
            'can_pay'        => false,
            'can_cancel'     => false,
            'bank'           => trade_payment_bank_info(),
        ];
    }

    $parsed_addr = trade_payment_parse_addr_body($body);
    if ($parsed_addr) {
        $pay_idx = (int) ($parsed_addr['pay_idx'] ?? 0);
        $row     = ($pay_idx > 0 && trade_payment_tables_ready()) ? trade_payment_row($pay_idx) : null;
        if ($row) {
            $payload = trade_payment_public_payload($row, $viewer_mb_idx);
        } else {
            $payload = [
                'pay_idx'        => $pay_idx,
                'amount'         => 0,
                'product_label'  => $product_label !== '' ? $product_label : '상품',
                'status'         => TRADE_PAYMENT_STATUS_CONFIRMED,
                'status_label'   => trade_payment_status_label(TRADE_PAYMENT_STATUS_CONFIRMED),
                'is_confirmed'   => true,
                'is_cancelled'   => false,
                'depositor_name' => '',
                'can_pay'        => false,
                'can_cancel'     => false,
                'bank'           => trade_payment_bank_info(),
                'fulfill_status' => TRADE_PAYMENT_FULFILL_ADDR_SENT,
                'can_confirm_shipping' => false,
                'can_confirm_purchase' => false,
                'is_purchase_confirmed' => false,
            ];
        }
        $payload['address'] = $parsed_addr['lines'];

        return $payload;
    }

    $parsed_track = trade_payment_parse_track_body($body);
    if ($parsed_track) {
        $pay_idx = (int) ($parsed_track['pay_idx'] ?? 0);
        $row     = ($pay_idx > 0 && trade_payment_tables_ready()) ? trade_payment_row($pay_idx) : null;
        if ($row) {
            $payload = trade_payment_public_payload($row, $viewer_mb_idx);
            $payload['tracking'] = [
                'courier'     => (string) ($row['pay_courier_name'] ?? ''),
                'tracking_no' => (string) ($row['pay_tracking_no'] ?? ''),
                'ship_url'    => trade_payment_ship_url($pay_idx),
                'track_url'   => '/trade/trade_tracking_view.php?pay_idx=' . $pay_idx,
            ];

            return $payload;
        }
    }

    $parsed_delivered = trade_payment_parse_delivered_body($body);
    if ($parsed_delivered) {
        $pay_idx = (int) ($parsed_delivered['pay_idx'] ?? 0);
        $row     = ($pay_idx > 0 && trade_payment_tables_ready()) ? trade_payment_row($pay_idx) : null;
        if ($row) {
            $row = trade_payment_advance_to_shipping_if_delivered($row);
            $payload = trade_payment_public_payload($row, $viewer_mb_idx);
            if (!empty($payload['is_purchase_confirmed'])) {
                $payload['can_confirm_purchase'] = false;
            } elseif ((int) ($row['buyer_mb_idx'] ?? 0) === $viewer_mb_idx) {
                $payload['can_confirm_purchase'] = true;
                $payload['can_confirm_shipping'] = false;
            }
            if (trade_payment_has_tracking($row)) {
                $payload['tracking'] = [
                    'courier'     => (string) ($row['pay_courier_name'] ?? ''),
                    'tracking_no' => (string) ($row['pay_tracking_no'] ?? ''),
                    'ship_url'    => trade_payment_ship_url($pay_idx),
                    'track_url'   => '/trade/trade_tracking_view.php?pay_idx=' . $pay_idx,
                ];
            }

            return $payload;
        }

        return [
            'pay_idx'               => $pay_idx,
            'amount'                => 0,
            'product_label'         => $product_label !== '' ? $product_label : '상품',
            'status'                => TRADE_PAYMENT_STATUS_CONFIRMED,
            'status_label'          => trade_payment_status_label(TRADE_PAYMENT_STATUS_CONFIRMED),
            'is_confirmed'          => true,
            'is_cancelled'          => false,
            'is_sale_cancelled'     => false,
            'can_confirm_purchase'  => $pay_idx > 0 && $viewer_mb_idx !== $seller_mb_idx,
            'can_confirm_shipping'  => false,
            'is_purchase_confirmed' => false,
            'ship_url'              => $pay_idx > 0 ? trade_payment_ship_url($pay_idx) : '',
            'tracking'              => $pay_idx > 0 ? [
                'courier'     => '',
                'tracking_no' => '',
                'ship_url'    => trade_payment_ship_url($pay_idx),
                'track_url'   => '/trade/trade_tracking_view.php?pay_idx=' . $pay_idx,
            ] : null,
        ];
    }

    $parsed_buyconf = trade_payment_parse_buyconf_body($body);
    if ($parsed_buyconf) {
        $pay_idx = (int) ($parsed_buyconf['pay_idx'] ?? 0);
        $row     = ($pay_idx > 0 && trade_payment_tables_ready()) ? trade_payment_row($pay_idx) : null;
        if ($row) {
            $payload = trade_payment_public_payload($row, $viewer_mb_idx);
            $payload['is_purchase_confirmed'] = true;
            $payload['can_confirm_purchase']  = false;
            $payload['can_confirm_shipping']  = false;
            $payload['seller_settle_amount']  = (int) ($row['pay_seller_settle_amount']
                ?? trade_payment_settlement_amounts(trade_payment_settlement_gross_from_row($row))['seller_amount']);

            return $payload;
        }

        $settle = (int) ($parsed_buyconf['settle_amount'] ?? 0);

        return [
            'pay_idx'                 => $pay_idx,
            'amount'                  => $settle,
            'seller_settle_amount'    => $settle,
            'product_label'           => $product_label !== '' ? $product_label : '상품',
            'status'                  => TRADE_PAYMENT_STATUS_CONFIRMED,
            'status_label'            => trade_payment_status_label(TRADE_PAYMENT_STATUS_CONFIRMED),
            'is_confirmed'            => true,
            'is_cancelled'            => false,
            'is_sale_cancelled'       => false,
            'is_purchase_confirmed'   => true,
            'can_confirm_purchase'    => false,
            'can_confirm_shipping'    => false,
            'fulfill_status'          => TRADE_PAYMENT_FULFILL_PURCHASE,
        ];
    }

    $parsed = trade_payment_parse_request_body($body);
    if (!$parsed) {
        return null;
    }

    $msg_pay_idx = (int) ($rw['msg_pay_idx'] ?? 0);

    if (trade_payment_tables_ready()) {
        $row = trade_payment_row_for_request_msg($rw);
        if ($row && trade_payment_is_cancelled_status((int) ($row['pay_status'] ?? 0))) {
            return trade_payment_public_payload($row, $viewer_mb_idx);
        }

        if ($room_idx > 0) {
            $confirmed = trade_payment_resolve_confirmed_for_request($room_idx, $rw, $seller_mb_idx);
            if ($confirmed) {
                $confirmed = trade_payment_reconcile_row_from_chat($room_idx, $confirmed, $msg_idx);

                return trade_payment_public_payload($confirmed, $viewer_mb_idx);
            }
        }

        if (!$row && $msg_pay_idx > 0) {
            $row = trade_payment_row($msg_pay_idx);
        }
        if ($row) {
            if ($room_idx > 0) {
                $row = trade_payment_reconcile_row_from_chat($room_idx, $row, $msg_idx);
            }

            return trade_payment_public_payload($row, $viewer_mb_idx);
        }
    }

    return trade_payment_chat_only_payload($rw, $viewer_mb_idx, $room_idx, $seller_mb_idx, $product_label);
}

function trade_payment_build_request_chat_body(int $amount): string
{
    return '[PZ_PAY:' . $amount . "]\n* 결제요청 *\n₩" . number_format($amount) . '을 결제해주세요';
}

function trade_checkout_needs_address(array $trade): bool
{
    $method = (string) ($trade['tr_method'] ?? 'both');

    return $method === 'delivery' || $method === 'both';
}

function trade_shipping_fee_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_shipping_fee'"));

    return $ready;
}

/**
 * 구매자 부담 택배비 (0이면 판매자 부담)
 */
function trade_checkout_shipping_fee(array $trade): int
{
    if (!trade_shipping_fee_column_ready()) {
        return 0;
    }
    $method = (string) ($trade['tr_method'] ?? '');
    if ($method !== 'delivery' && $method !== 'both') {
        return 0;
    }

    return max(0, (int) ($trade['tr_shipping_fee'] ?? 0));
}

/**
 * 바로구매 상품가(희망가)
 */
function trade_checkout_goods_amount(array $trade): int
{
    return max(0, (int) ($trade['tr_price'] ?? 0));
}

/**
 * 바로구매 결제 합계 = 상품가 + 택배비
 */
function trade_checkout_total_amount(array $trade): int
{
    return trade_checkout_goods_amount($trade) + trade_checkout_shipping_fee($trade);
}

/**
 * @param array<string, mixed> $trade
 * @return array{ok: bool, error?: string}
 */
function trade_checkout_validate_trade(array $trade, int $buyer_mb_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($buyer_mb_idx < 1) {
        return $fail('로그인이 필요합니다.');
    }
    if ((string) ($trade['tr_type'] ?? '') !== 'sell') {
        return $fail('판매 글만 바로구매할 수 있습니다.');
    }
    if ((int) ($trade['tr_price'] ?? 0) < 1) {
        return $fail('가격이 정해진 상품만 바로구매할 수 있습니다.');
    }
    if ((int) ($trade['tr_status'] ?? 0) !== 1) {
        return $fail('거래글을 찾을 수 없거나 종료된 글입니다.');
    }
    $deal = (int) ($trade['tr_deal_status'] ?? 0);
    if ($deal === 3) {
        return $fail('이미 거래가 완료된 상품입니다.');
    }
    if ($deal !== 1) {
        $tr_idx = (int) ($trade['tr_idx'] ?? 0);
        $has_open_order = false;
        if ($tr_idx > 0) {
            $state = trade_checkout_load_state($tr_idx, $buyer_mb_idx, 0);
            $pay_status = (int) (($state['payment']['status'] ?? -1));
            $has_open_order = (int) ($state['pay_idx'] ?? 0) > 0
                && $pay_status >= 0
                && $pay_status < TRADE_PAYMENT_STATUS_CANCELLED;
        }
        if (!$has_open_order) {
            return $fail('다른 구매자와 거래가 진행 중인 상품입니다.');
        }
    }
    if ($buyer_mb_idx === (int) ($trade['mb_idx'] ?? 0)) {
        return $fail('본인이 올린 거래글은 구매할 수 없습니다.');
    }
    if (!db_table_exists('tb_trade_room') || !db_table_exists('tb_trade_room_msg')) {
        return $fail('채팅 DB가 설치되지 않았습니다.');
    }
    if (!trade_chat_payment_status()['ready']) {
        return $fail('결제 기능을 사용할 수 없습니다. 관리자에게 문의해 주세요.');
    }

    return ['ok' => true];
}

/**
 * 바로구매 페이지 — 기존 문의방·결제 상태 조회 (생성하지 않음)
 *
 * @return array{room_idx: int, pay_idx: int, request_msg_idx: int, payment: ?array<string, mixed>}
 */
function trade_checkout_load_state(int $tr_idx, int $buyer_mb_idx, int $expected_amount = 0): array
{
    $out = [
        'room_idx'        => 0,
        'pay_idx'         => 0,
        'request_msg_idx' => 0,
        'payment'         => null,
    ];
    if ($tr_idx < 1 || $buyer_mb_idx < 1 || !db_table_exists('tb_trade_room')) {
        return $out;
    }

    $room_idx = function_exists('trade_chat_find_open_buyer_room')
        ? trade_chat_find_open_buyer_room($tr_idx, $buyer_mb_idx)
        : 0;

    if (trade_payment_table_ready()) {
        $pay_where = "tr_idx = {$tr_idx} AND buyer_mb_idx = {$buyer_mb_idx}";
        if ($room_idx > 0) {
            $pay_where .= " AND room_idx = {$room_idx}";
        }
        $row = db_assoc(db_query("
            SELECT *
            FROM tb_trade_payment
            WHERE {$pay_where}
            ORDER BY pay_idx DESC
            LIMIT 1
        "));
        if ($row) {
            $pay_room = (int) ($row['room_idx'] ?? 0);
            $out['room_idx']        = $pay_room > 0 ? $pay_room : $room_idx;
            $out['pay_idx']         = (int) ($row['pay_idx'] ?? 0);
            $out['request_msg_idx'] = (int) ($row['request_msg_idx'] ?? 0);
            $out['payment']         = trade_payment_public_payload($row, $buyer_mb_idx);

            return $out;
        }
    }

    if ($room_idx < 1) {
        return $out;
    }
    $out['room_idx'] = $room_idx;

    $active = trade_payment_list_active_requests($room_idx);
    if ($active === []) {
        return $out;
    }

    $open = $active[0];
    if ($expected_amount > 0 && (int) ($open['pay_amount'] ?? 0) !== $expected_amount) {
        return $out;
    }

    $out['request_msg_idx'] = (int) ($open['request_msg_idx'] ?? 0);
    $out['pay_idx']         = (int) ($open['pay_idx'] ?? 0);
    if ($out['pay_idx'] > 0) {
        $row = trade_payment_row($out['pay_idx']);
        if ($row) {
            $out['payment'] = trade_payment_public_payload($row, $buyer_mb_idx);
        }
    }

    return $out;
}

/**
 * 바로구매 페이지 — 판매자용 입금 대기·완료 조회 (생성하지 않음)
 *
 * @return array{room_idx: int, pay_idx: int, request_msg_idx: int, payment: ?array<string, mixed>}
 */
function trade_checkout_load_state_for_seller(int $tr_idx, int $seller_mb_idx, int $pay_idx_hint = 0): array
{
    $out = [
        'room_idx'        => 0,
        'pay_idx'         => 0,
        'request_msg_idx' => 0,
        'payment'         => null,
    ];
    if ($tr_idx < 1 || $seller_mb_idx < 1 || !trade_payment_table_ready()) {
        return $out;
    }

    $st_sub  = (int) TRADE_PAYMENT_STATUS_SUBMITTED;
    $st_conf = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $row     = null;

    if ($pay_idx_hint > 0) {
        $hint = trade_payment_row($pay_idx_hint);
        if ($hint
            && (int) ($hint['tr_idx'] ?? 0) === $tr_idx
            && (int) ($hint['seller_mb_idx'] ?? 0) === $seller_mb_idx
            && in_array((int) ($hint['pay_status'] ?? 0), [$st_sub, $st_conf], true)
        ) {
            $row = $hint;
        }
    }

    if (!$row) {
        $row = db_assoc(db_query("
            SELECT *
            FROM tb_trade_payment
            WHERE tr_idx = {$tr_idx}
              AND seller_mb_idx = {$seller_mb_idx}
              AND pay_status IN ({$st_sub}, {$st_conf})
            ORDER BY
                CASE WHEN pay_status = {$st_sub} THEN 0 ELSE 1 END,
                pay_idx DESC
            LIMIT 1
        "));
    }
    if (!$row) {
        return $out;
    }

    $out['pay_idx']         = (int) ($row['pay_idx'] ?? 0);
    $out['request_msg_idx'] = (int) ($row['request_msg_idx'] ?? 0);
    $out['room_idx']        = (int) ($row['room_idx'] ?? 0);
    $out['payment']         = trade_payment_public_payload($row, $seller_mb_idx);

    return $out;
}

/**
 * 판매내역(거래글) 목록 — 글별 입금신청·확인 결제 1건
 *
 * @param array<int> $tr_idxs
 * @return array<int, array<string, mixed>> tr_idx => payment row
 */
function trade_seller_listing_payments_by_tr(int $seller_mb_idx, array $tr_idxs): array
{
    $seller_mb_idx = max(0, $seller_mb_idx);
    $ids = [];
    foreach ($tr_idxs as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if ($seller_mb_idx < 1 || $ids === [] || !trade_payment_table_ready()) {
        return [];
    }

    $id_sql = implode(',', $ids);
    $st_sub  = (int) TRADE_PAYMENT_STATUS_SUBMITTED;
    $st_conf = (int) TRADE_PAYMENT_STATUS_CONFIRMED;
    $rs = db_query("
        SELECT *
        FROM tb_trade_payment
        WHERE seller_mb_idx = {$seller_mb_idx}
          AND tr_idx IN ({$id_sql})
          AND pay_status IN ({$st_sub}, {$st_conf})
        ORDER BY
            CASE WHEN pay_status = {$st_sub} THEN 0 ELSE 1 END,
            pay_idx DESC
    ");

    $out = [];
    while ($row = db_assoc($rs)) {
        $tr_idx = (int) ($row['tr_idx'] ?? 0);
        if ($tr_idx > 0 && !isset($out[$tr_idx])) {
            $out[$tr_idx] = $row;
        }
    }

    return $out;
}

/**
 * @return array{ok: bool, error?: string}
 */
function trade_payment_save_ship_from_address(int $pay_idx, int $buyer_mb_idx, int $addr_idx, string $order_message = ''): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($pay_idx < 1) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if (!trade_payment_ship_column_ready()) {
        return ['ok' => true];
    }

    require_once __DIR__ . '/../../lib/_member_address.php';
    if (!member_address_table_ready()) {
        return $fail('배송지 주소록을 사용할 수 없습니다.');
    }

    $addr = member_address_get($buyer_mb_idx, $addr_idx);
    if (!$addr) {
        return $fail('배송지를 선택해 주세요.');
    }

    $lines = member_address_format_lines($addr);
    if ($order_message !== '') {
        $lines['message'] = $order_message;
    }

    $ok = db_query('
        UPDATE tb_trade_payment SET
            pay_ship_label = \'' . db_escape((string) ($lines['label'] ?? '')) . '\',
            pay_ship_name = \'' . db_escape((string) ($lines['name'] ?? '')) . '\',
            pay_ship_phone = \'' . db_escape((string) ($lines['phone'] ?? '')) . '\',
            pay_ship_zip = \'' . db_escape((string) ($lines['zip'] ?? '')) . '\',
            pay_ship_address = \'' . db_escape((string) ($lines['address'] ?? '')) . '\',
            pay_ship_message = \'' . db_escape((string) ($lines['message'] ?? '')) . '\'
        WHERE pay_idx = ' . $pay_idx . '
          AND buyer_mb_idx = ' . $buyer_mb_idx . '
        LIMIT 1
    ');
    if (!$ok) {
        return $fail('배송지 저장에 실패했습니다.');
    }

    return ['ok' => true];
}

/**
 * 바로구매 — 결제 요청(판매자 결제창) 준비
 *
 * @param array<string, mixed> $trade
 * @return array{ok: bool, error?: string, room_idx?: int, pay_idx?: int, request_msg_idx?: int, payment?: array<string, mixed>}
 */
function trade_checkout_ensure_payment(int $tr_idx, int $buyer_mb_idx, array $trade, string $buyer_nick = '', int $pay_amount_override = 0): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    $valid = trade_checkout_validate_trade($trade, $buyer_mb_idx);
    if (!$valid['ok']) {
        return $fail((string) ($valid['error'] ?? '바로구매할 수 없습니다.'));
    }

    $seller_mb  = (int) ($trade['mb_idx'] ?? 0);
    $list_total = trade_checkout_total_amount($trade);
    $amount     = $pay_amount_override > 0 ? $pay_amount_override : $list_total;
    if ($amount < 1 || ($list_total > 0 && $amount > $list_total)) {
        return $fail('결제 금액이 올바르지 않습니다.');
    }
    $label     = trade_chat_inquiry_product_label($trade);

    $room_res = trade_chat_ensure_buyer_inquiry($tr_idx, $buyer_mb_idx, $trade, $buyer_nick);
    if (!$room_res['ok']) {
        return $fail((string) ($room_res['error'] ?? '문의방을 만들 수 없습니다.'));
    }
    $room_idx = (int) ($room_res['room_idx'] ?? 0);
    if ($room_idx < 1) {
        return $fail('문의방을 찾을 수 없습니다.');
    }

    $active = trade_payment_list_active_requests($room_idx);
    if ($active !== []) {
        $open = $active[0];
        if ((int) ($open['pay_amount'] ?? 0) !== $amount) {
            return $fail('이미 다른 금액의 결제가 진행 중입니다. 거래 메시지함에서 확인해 주세요.');
        }

        $request_msg_idx = (int) ($open['request_msg_idx'] ?? 0);
        $pay_idx         = (int) ($open['pay_idx'] ?? 0);
        if ($pay_idx < 1 && $request_msg_idx > 0) {
            $msg_rw = db_assoc(db_query("SELECT * FROM tb_trade_room_msg WHERE msg_idx = {$request_msg_idx} LIMIT 1"));
            if ($msg_rw) {
                $row = trade_payment_row_for_request_msg($msg_rw);
                if ($row) {
                    $pay_idx = (int) ($row['pay_idx'] ?? 0);
                }
            }
            if ($pay_idx < 1) {
                $pending = trade_payment_insert_pending_from_request($buyer_mb_idx, $request_msg_idx);
                if ($pending['ok']) {
                    $pay_idx = (int) (($pending['row']['pay_idx'] ?? 0));
                }
            }
        }

        $payment = null;
        if ($pay_idx > 0) {
            $row = trade_payment_row($pay_idx);
            if ($row) {
                $payment = trade_payment_public_payload($row, $buyer_mb_idx);
            }
        }

        return [
            'ok'              => true,
            'room_idx'        => $room_idx,
            'pay_idx'         => $pay_idx,
            'request_msg_idx' => $request_msg_idx,
            'payment'         => $payment,
        ];
    }

    $can_create = trade_payment_assert_can_create_request($room_idx);
    if (!$can_create['ok']) {
        return $fail((string) ($can_create['error'] ?? '결제 요청을 준비할 수 없습니다.'));
    }

    $body = trade_payment_build_request_chat_body($amount);

    $msg_ins = trade_payment_insert_request_message($room_idx, $seller_mb, $body, 0);
    if (!$msg_ins['ok']) {
        return $fail((string) ($msg_ins['error'] ?? '결제 요청 메시지를 보낼 수 없습니다.'));
    }
    $msg_idx = (int) ($msg_ins['msg_idx'] ?? 0);
    if ($msg_idx < 1) {
        return $fail('결제 요청 메시지를 보낼 수 없습니다.');
    }

    trade_payment_init_request_msg_status($msg_idx);

    if (!trade_payment_deposit_sync_ready()) {
        return $fail('결제 DB가 설정되지 않았습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.');
    }

    $pending = trade_payment_insert_pending_from_request($buyer_mb_idx, $msg_idx);
    if (!$pending['ok']) {
        return $fail((string) ($pending['error'] ?? '결제 정보를 저장할 수 없습니다.'));
    }
    $pay_idx = (int) (($pending['row']['pay_idx'] ?? 0));
    if ($pay_idx < 1) {
        return $fail('결제 정보를 저장할 수 없습니다.');
    }

    $buyer_body = '바로구매를 신청했습니다.';
    $esc_buyer  = db_escape($buyer_body);
    db_query("
        INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
        VALUES ({$room_idx}, {$buyer_mb_idx}, '{$esc_buyer}', NOW())
    ");

    db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");

    $payment = null;
    if ($pay_idx > 0) {
        $row = trade_payment_row($pay_idx);
        if ($row) {
            $payment = trade_payment_public_payload($row, $buyer_mb_idx);
        }
    }

    return [
        'ok'              => true,
        'room_idx'        => $room_idx,
        'pay_idx'         => $pay_idx,
        'request_msg_idx' => $msg_idx,
        'payment'         => $payment,
    ];
}

/**
 * 바로구매 결제 제출
 *
 * @param array<string, mixed> $input
 * @return array{ok: bool, error?: string, redirect?: string, message?: string, payment?: array<string, mixed>, bank?: array<string, mixed>}
 */
function trade_checkout_submit(int $buyer_mb_idx, int $tr_idx, array $input): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($tr_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $trade = db_assoc(db_query("
        SELECT t.*, m.mb_nick AS seller_nick
        FROM tb_trade t
        LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
        WHERE t.tr_idx = {$tr_idx}
        LIMIT 1
    "));
    if (!$trade) {
        return $fail('거래글을 찾을 수 없습니다.');
    }

    $valid = trade_checkout_validate_trade($trade, $buyer_mb_idx);
    if (!$valid['ok']) {
        return $fail((string) ($valid['error'] ?? '바로구매할 수 없습니다.'));
    }

    $buyer_nick = (string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . $buyer_mb_idx . ' LIMIT 1');
    $goods_amount    = trade_checkout_goods_amount($trade);
    $shipping_fee    = trade_checkout_shipping_fee($trade);
    $original_amount = trade_checkout_total_amount($trade);
    $mc_idx          = (int) ($input['member_coupon_idx'] ?? 0);
    $pay_amount      = $original_amount;
    $discount_amount = 0;

    if ($mc_idx > 0) {
        require_once __DIR__ . '/../../lib/_coupon.php';
        if (!member_coupon_table_ready()) {
            return $fail('쿠폰 기능이 준비되지 않았습니다. sql/migrate_tb_coupon.sql 을 적용해 주세요.');
        }
        // 쿠폰은 상품가에만 적용, 택배비는 그대로 합산
        $cval = coupon_validate_member_coupon($mc_idx, $buyer_mb_idx, $goods_amount);
        if (!$cval['ok']) {
            return $fail((string) ($cval['error'] ?? '쿠폰을 사용할 수 없습니다.'));
        }
        $discount_amount = (int) ($cval['discount'] ?? 0);
        $pay_amount      = (int) ($cval['pay_amount'] ?? $goods_amount) + $shipping_fee;
    }

    $prep       = trade_checkout_ensure_payment($tr_idx, $buyer_mb_idx, $trade, $buyer_nick, $pay_amount);
    if (!$prep['ok']) {
        return $fail((string) ($prep['error'] ?? '결제 준비에 실패했습니다.'));
    }

    $request_msg_idx = (int) ($prep['request_msg_idx'] ?? 0);
    $pay_idx         = (int) ($prep['pay_idx'] ?? 0);
    $room_idx        = (int) ($prep['room_idx'] ?? 0);
    $needs_addr      = trade_checkout_needs_address($trade);
    $addr_idx        = (int) ($input['addr_idx'] ?? 0);
    $order_message   = trim((string) ($input['addr_message'] ?? ''));
    $pay_method      = trim((string) ($input['pay_method'] ?? 'cash'));

    if ($needs_addr) {
        require_once __DIR__ . '/../../lib/_member_address.php';
        if (!member_address_table_ready()) {
            return $fail('배송지 주소록을 사용할 수 없습니다. sql/tb_member_address.sql 을 적용해 주세요.');
        }
        if ($addr_idx < 1 || !member_address_get($buyer_mb_idx, $addr_idx)) {
            return $fail('배송지를 선택해 주세요.');
        }
        if ($order_message !== '' && mb_strlen($order_message) > MEMBER_ADDRESS_MESSAGE_MAX) {
            return $fail('배송 메시지는 ' . MEMBER_ADDRESS_MESSAGE_MAX . '자 이내로 입력해 주세요.');
        }
    }

    if (!in_array($pay_method, ['cash', 'bank'], true)) {
        return $fail('결제 수단을 선택해 주세요.');
    }

    if ($request_msg_idx < 1) {
        return $fail('결제 요청을 찾을 수 없습니다.');
    }

    if ($pay_idx < 1) {
        $pending = trade_payment_insert_pending_from_request($buyer_mb_idx, $request_msg_idx);
        if (!$pending['ok']) {
            return $fail((string) ($pending['error'] ?? '결제 정보를 저장할 수 없습니다.'));
        }
        $pay_idx = (int) (($pending['row']['pay_idx'] ?? 0));
    }
    if ($pay_idx < 1) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if (trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        return [
            'ok'       => true,
            'message'  => '이미 결제가 완료되었습니다.',
            'redirect' => '/trade/trade_checkout.php?tr_idx=' . $tr_idx . '&done=1',
            'payment'  => trade_payment_public_payload($row, $buyer_mb_idx),
        ];
    }

    if ($needs_addr) {
        $addr_save = trade_payment_save_ship_from_address($pay_idx, $buyer_mb_idx, $addr_idx, $order_message);
        if (!$addr_save['ok']) {
            return $fail((string) ($addr_save['error'] ?? '배송지 저장에 실패했습니다.'));
        }
    }

    if ($mc_idx > 0 && $discount_amount > 0) {
        require_once __DIR__ . '/../../lib/_coupon.php';
        if (!coupon_attach_to_payment($pay_idx, $mc_idx, $original_amount, $discount_amount, $pay_amount)) {
            return $fail('쿠폰 적용 정보 저장에 실패했습니다.');
        }
        $row = trade_payment_row($pay_idx) ?: $row;
    }

    $return = '/trade/trade_checkout.php?tr_idx=' . $tr_idx;

    if ($pay_method === 'cash') {
        require_once __DIR__ . '/../../lib/_member_cash.php';
        if (!member_cash_column_ready()) {
            return $fail('캐시 결제를 사용하려면 sql/migrate_tb_member_cash.sql 을 적용해 주세요.');
        }

        $result = trade_payment_pay_with_cash($buyer_mb_idx, $pay_idx, $request_msg_idx);
        if (!$result['ok']) {
            return $fail((string) ($result['error'] ?? '캐시 결제에 실패했습니다.'));
        }

        if ($mc_idx > 0) {
            require_once __DIR__ . '/../../lib/_coupon.php';
            coupon_mark_used($mc_idx, $pay_idx);
        }

        return [
            'ok'       => true,
            'message'  => '결제가 완료되었습니다. 판매자가 곧 발송을 진행합니다.',
            'redirect' => $return . '&done=1',
            'payment'  => $result['payment'] ?? null,
        ];
    }

    $depositor = trim((string) ($input['depositor_name'] ?? ''));
    $parsed    = trade_payment_parse_depositor_name($depositor);
    if (!$parsed['ok']) {
        return $fail((string) ($parsed['error'] ?? '입금자명을 입력해 주세요.'));
    }

    $result = trade_payment_submit_deposit($pay_idx, $buyer_mb_idx, (string) $parsed['name']);
    if (!$result['ok']) {
        return $fail((string) ($result['error'] ?? '입금 신청에 실패했습니다.'));
    }

    if ($mc_idx > 0) {
        require_once __DIR__ . '/../../lib/_coupon.php';
        coupon_mark_used($mc_idx, $pay_idx);
    }

    return [
        'ok'       => true,
        'message'  => '입금 신청이 접수되었습니다. 안내 계좌로 입금해 주세요.',
        'redirect' => $return . '&submitted=1',
        'payment'  => $result['payment'] ?? null,
        'bank'     => trade_payment_bank_info(),
        'pay_idx'  => $pay_idx,
        'room_idx' => $room_idx,
    ];
}

/**
 * 바로구매 — 결제 상태 조회 (입금 확인 폴링, 구매자·판매자)
 *
 * @return array{ok: bool, error?: string, payment?: array<string, mixed>, bank?: array<string, mixed>, redirect?: string, pay_idx?: int}
 */
function trade_checkout_payment_status(int $viewer_mb_idx, int $tr_idx, int $pay_idx = 0): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($tr_idx < 1 || $viewer_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }

    $trade = db_assoc(db_query("SELECT * FROM tb_trade WHERE tr_idx = {$tr_idx} LIMIT 1"));
    $seller_mb = $trade ? (int) ($trade['mb_idx'] ?? 0) : 0;

    if ($pay_idx < 1) {
        if ($seller_mb > 0 && $viewer_mb_idx === $seller_mb) {
            $state = trade_checkout_load_state_for_seller($tr_idx, $viewer_mb_idx);
        } else {
            $amount = $trade ? trade_checkout_total_amount($trade) : 0;
            $state  = trade_checkout_load_state($tr_idx, $viewer_mb_idx, $amount);
        }
        $pay_idx = (int) ($state['pay_idx'] ?? 0);
    }
    if ($pay_idx < 1) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row || (int) ($row['tr_idx'] ?? 0) !== $tr_idx) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    $buyer_mb  = (int) ($row['buyer_mb_idx'] ?? 0);
    $pay_seller = (int) ($row['seller_mb_idx'] ?? $seller_mb);
    if ($viewer_mb_idx !== $buyer_mb && $viewer_mb_idx !== $pay_seller) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    $room_idx = (int) ($row['room_idx'] ?? 0);
    if ($room_idx > 0) {
        try {
            trade_payment_sync_room_payments($room_idx);
        } catch (Throwable $e) {
            error_log('trade_checkout_payment_status sync: ' . $e->getMessage());
        }
        $row = trade_payment_row($pay_idx) ?: $row;
    }

    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? 0))) {
        $req_msg = (int) ($row['request_msg_idx'] ?? 0);
        if ($room_idx > 0 && $req_msg > 0
            && trade_payment_has_done_for_request($room_idx, $req_msg, $pay_idx)) {
            $row = trade_payment_ensure_row_confirmed($row);
        }
    }

    return [
        'ok'       => true,
        'pay_idx'  => $pay_idx,
        'payment'  => trade_payment_public_payload($row, $viewer_mb_idx),
        'bank'     => trade_payment_bank_info(),
        'redirect' => '/trade/trade_checkout.php?tr_idx=' . $tr_idx . '&done=1',
    ];
}

/**
 * 바로구매 — 구매자 취소 가능 여부 (입금 확인 전 · 바로구매 주문만)
 */
function trade_checkout_can_cancel_buy_now(int $pay_idx, int $buyer_mb_idx): bool
{
    if ($pay_idx < 1 || $buyer_mb_idx < 1 || !trade_payment_table_ready()) {
        return false;
    }

    $row = trade_payment_row($pay_idx);
    if (!$row || (int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return false;
    }

    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_confirmed_status($status)
        || trade_payment_is_sale_cancelled_status($status)
        || trade_payment_is_cancelled_status($status)) {
        return false;
    }

    return trade_purchase_is_buynow($row);
}

/**
 * 바로구매 — 구매자 취소 (결제 행 삭제 + 채팅 결제요청 취소 표시)
 *
 * @return array{ok: bool, error?: string, message?: string, redirect?: string}
 */
function trade_checkout_cancel_buy_now(int $buyer_mb_idx, int $tr_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($tr_idx < 1 || $buyer_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!trade_payment_table_ready()) {
        return $fail('결제 DB가 설정되지 않았습니다.');
    }

    $trade = db_assoc(db_query("SELECT * FROM tb_trade WHERE tr_idx = {$tr_idx} LIMIT 1"));
    if (!$trade) {
        return $fail('거래글을 찾을 수 없습니다.');
    }

    $amount = trade_checkout_total_amount($trade);
    $state  = trade_checkout_load_state($tr_idx, $buyer_mb_idx, $amount);
    $pay_idx = (int) ($state['pay_idx'] ?? 0);
    $room_idx = (int) ($state['room_idx'] ?? 0);
    $request_msg_idx = (int) ($state['request_msg_idx'] ?? 0);

    if ($pay_idx < 1) {
        return $fail('취소할 바로구매 주문이 없습니다.');
    }
    if (!trade_checkout_can_cancel_buy_now($pay_idx, $buyer_mb_idx)) {
        return $fail('취소할 수 없는 주문입니다. 입금 확인이 완료되었거나 바로구매 주문이 아닙니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    if ($request_msg_idx < 1) {
        $request_msg_idx = (int) ($row['request_msg_idx'] ?? 0);
    }
    if ($room_idx < 1) {
        $room_idx = (int) ($row['room_idx'] ?? 0);
    }

    $pay_amount = (int) ($row['pay_amount'] ?? 0);
    $seller_mb  = (int) ($row['seller_mb_idx'] ?? 0);

    if (function_exists('coupon_restore_for_payment')) {
        require_once __DIR__ . '/../../lib/_coupon.php';
        if (!coupon_restore_for_payment($pay_idx)) {
            return $fail('쿠폰 복원에 실패했습니다. 잠시 후 다시 시도해 주세요.');
        }
    }

    if (!db_query("
        DELETE FROM tb_trade_payment
        WHERE pay_idx = {$pay_idx} AND buyer_mb_idx = {$buyer_mb_idx}
        LIMIT 1
    ")) {
        return trade_payment_fail_db('결제 내역을 삭제할 수 없습니다.');
    }
    if (trade_payment_row($pay_idx)) {
        return $fail('결제 내역을 삭제할 수 없습니다.');
    }

    if ($request_msg_idx > 0 && $room_idx > 0) {
        trade_payment_mark_request_message_cancelled($request_msg_idx, $room_idx, $pay_amount);
        if (trade_payment_column_exists('msg_pay_idx')) {
            db_query("
                UPDATE tb_trade_room_msg
                SET msg_pay_idx = NULL
                WHERE msg_idx = {$request_msg_idx} AND room_idx = {$room_idx}
            ");
        }
    }

    if ($room_idx > 0) {
        db_query("
            DELETE FROM tb_trade_room_msg
            WHERE room_idx = {$room_idx}
              AND mb_idx = {$buyer_mb_idx}
              AND msg_body LIKE '바로구매%'
        ");

        $cancel_body = '바로구매를 취소했습니다.';
        $esc_cancel  = db_escape($cancel_body);
        db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$buyer_mb_idx}, '{$esc_cancel}', NOW())
        ");

        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    try {
        if ($seller_mb > 0 && $room_idx > 0) {
            require_once __DIR__ . '/../../lib/webpush.php';
            $nick_row = db_assoc(db_query("SELECT mb_nick FROM tb_member WHERE mb_idx = {$buyer_mb_idx} LIMIT 1"));
            $nick     = trim((string) ($nick_row['mb_nick'] ?? ''));
            if ($nick === '') {
                $nick = '구매자';
            }
            webpush_notify_trade_message($seller_mb, $nick, $nick . '님 바로구매 취소', $tr_idx, $room_idx);
        }
    } catch (Throwable $e) {
        error_log('trade_checkout_cancel_buy_now webpush: ' . $e->getMessage());
    }

    return [
        'ok'       => true,
        'message'  => '바로구매가 취소되었습니다.',
        'redirect' => '/trade/trade_view.php?idx=' . $tr_idx,
    ];
}

/**
 * 구매내역 — 입금신청(입금확인중) 건을 구매자가 취소할 수 있는지
 */
function trade_purchase_can_buyer_cancel_deposit(array $row, int $buyer_mb_idx): bool
{
    $buyer_mb_idx = (int) $buyer_mb_idx;
    if ($buyer_mb_idx < 1) {
        return false;
    }
    if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return false;
    }

    return (int) ($row['pay_status'] ?? 0) === TRADE_PAYMENT_STATUS_SUBMITTED;
}

/**
 * 채팅구매 입금신청만 되돌림 (결제요청은 유지)
 */
function trade_purchase_reset_chat_deposit_application(array $row, int $buyer_mb_idx): array
{
    $fail = static function (string $message): array {
        return ['ok' => false, 'error' => $message, 'message' => $message];
    };

    $pay_idx = (int) ($row['pay_idx'] ?? 0);
    $tr_idx  = (int) ($row['tr_idx'] ?? 0);
    $buyer_mb_idx = (int) $buyer_mb_idx;
    if ($pay_idx < 1 || $tr_idx < 1 || $buyer_mb_idx < 1) {
        return $fail('결제 정보를 확인할 수 없습니다.');
    }

    $submitted = (int) TRADE_PAYMENT_STATUS_SUBMITTED;
    $pending   = (int) TRADE_PAYMENT_STATUS_PENDING;
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $request_msg_idx = (int) ($row['request_msg_idx'] ?? 0);

    require_once __DIR__ . '/../../lib/_coupon.php';
    if (function_exists('coupon_restore_for_payment') && !coupon_restore_for_payment($pay_idx)) {
        return $fail('쿠폰 복원에 실패했습니다. 잠시 후 다시 시도해 주세요.');
    }

    $set = [
        'pay_status = ' . $pending,
        "depositor_name = ''",
    ];
    if (trade_payment_pay_column_exists('pay_submitted_at')) {
        $set[] = 'pay_submitted_at = NULL';
    }
    if (trade_payment_pay_column_exists('pay_confirmed_at')) {
        $set[] = 'pay_confirmed_at = NULL';
    }
    if (function_exists('trade_payment_coupon_column_ready') && trade_payment_coupon_column_ready()) {
        $orig = max(0, (int) ($row['pay_original_amount'] ?? 0));
        if ($orig > 0) {
            $set[] = 'pay_amount = ' . $orig;
        }
        $set[] = 'pay_discount_amount = 0';
        $set[] = 'pay_member_coupon_idx = NULL';
    }

    if (!db_query("
        UPDATE tb_trade_payment
        SET " . implode(', ', $set) . "
        WHERE pay_idx = {$pay_idx}
          AND buyer_mb_idx = {$buyer_mb_idx}
          AND pay_status = {$submitted}
        LIMIT 1
    ")) {
        return trade_payment_fail_db('입금 신청을 취소할 수 없습니다.');
    }

    $fresh = trade_payment_row($pay_idx);
    if (!$fresh || (int) ($fresh['pay_status'] ?? 0) !== $pending) {
        return $fail('입금 신청을 취소할 수 없습니다.');
    }

    if ($request_msg_idx < 1) {
        $request_msg_idx = (int) ($fresh['request_msg_idx'] ?? 0);
    }
    if ($request_msg_idx > 0) {
        trade_payment_set_msg_pay_status($request_msg_idx, TRADE_PAYMENT_STATUS_PENDING);
        if ($room_idx > 0) {
            trade_payment_delete_acks_for_request($room_idx, $request_msg_idx);
        }
    }

    if ($room_idx > 0) {
        $body = '입금 신청을 취소했습니다.';
        $esc  = db_escape($body);
        db_query("
            INSERT INTO tb_trade_room_msg (room_idx, mb_idx, msg_body, msg_created_at)
            VALUES ({$room_idx}, {$buyer_mb_idx}, '{$esc}', NOW())
        ");
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx}");
    }

    try {
        if ($seller_mb > 0 && $room_idx > 0) {
            require_once __DIR__ . '/../../lib/webpush.php';
            $nick_row = db_assoc(db_query("SELECT mb_nick FROM tb_member WHERE mb_idx = {$buyer_mb_idx} LIMIT 1"));
            $nick     = trim((string) ($nick_row['mb_nick'] ?? ''));
            if ($nick === '') {
                $nick = '구매자';
            }
            webpush_notify_trade_message($seller_mb, $nick, $nick . '님 입금 신청 취소', $tr_idx, $room_idx);
        }
    } catch (Throwable $e) {
        error_log('trade_purchase_reset_chat_deposit_application webpush: ' . $e->getMessage());
    }

    return [
        'ok'       => true,
        'message'  => '입금 신청을 취소했습니다.',
        'redirect' => '/trade/trade_purchases.php',
    ];
}

/**
 * 구매내역 — 입금신청 취소 (바로구매: 결제 삭제 / 채팅구매: 입금신청만 되돌림)
 */
function trade_purchase_buyer_cancel_deposit(array $buyer, int $pay_idx): array
{
    $fail = static function (string $message): array {
        return ['ok' => false, 'error' => $message, 'message' => $message];
    };

    $buyer_mb_idx = (int) ($buyer['mb_idx'] ?? 0);
    $pay_idx      = (int) $pay_idx;
    if ($buyer_mb_idx < 1 || $pay_idx < 1) {
        return $fail('로그인 후 이용해 주세요.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 확인할 수 없습니다.');
    }
    if (!trade_purchase_can_buyer_cancel_deposit($row, $buyer_mb_idx)) {
        return $fail('입금 확인 중인 건만 취소할 수 있습니다. 이미 입금이 확인된 건은 취소할 수 없습니다.');
    }

    if (trade_purchase_is_buynow($row)) {
        $res = trade_checkout_cancel_buy_now($buyer_mb_idx, (int) $row['tr_idx']);
        if (!empty($res['ok'])) {
            $res['message']  = '입금 신청을 취소했습니다.';
            $res['redirect'] = '/trade/trade_purchases.php';
        }

        return $res;
    }

    return trade_purchase_reset_chat_deposit_application($row, $buyer_mb_idx);
}

/**
 * 구매내역 — 결제 테이블 사용 가능 여부
 */
function trade_purchase_tables_ready(): bool
{
    return trade_payment_tables_ready();
}

/**
 * 구매내역 — 구매자 기준 WHERE 절 (취소·판매취소 제외, 입금신청 이상)
 */
function trade_purchase_buyer_where_sql(int $buyer_mb_idx): string
{
    $buyer_mb_idx = max(0, $buyer_mb_idx);

    return 'p.buyer_mb_idx = ' . $buyer_mb_idx
        . ' AND p.pay_status >= ' . TRADE_PAYMENT_STATUS_SUBMITTED
        . ' AND p.pay_status NOT IN (' . TRADE_PAYMENT_STATUS_CANCELLED . ', ' . TRADE_PAYMENT_STATUS_SALE_CANCELLED . ')';
}

/**
 * 구매내역 — 구매 건수
 */
function trade_purchase_buyer_count(int $buyer_mb_idx): int
{
    if ($buyer_mb_idx < 1 || !trade_purchase_tables_ready()) {
        return 0;
    }

    return (int) db_result('SELECT COUNT(*) FROM tb_trade_payment p WHERE ' . trade_purchase_buyer_where_sql($buyer_mb_idx));
}

/**
 * 구매내역 — 바로구매 여부 (같은 문의방에 바로구매 안내 메시지)
 *
 * @param array<string, mixed> $row
 */
function trade_purchase_is_buynow(array $row): bool
{
    $room_idx = (int) ($row['room_idx'] ?? 0);
    $buyer_mb = (int) ($row['buyer_mb_idx'] ?? 0);
    if ($room_idx < 1 || $buyer_mb < 1 || !db_table_exists('tb_trade_room_msg')) {
        return false;
    }

    $cnt = (int) db_result("
        SELECT COUNT(*)
        FROM tb_trade_room_msg
        WHERE room_idx = {$room_idx}
          AND mb_idx = {$buyer_mb}
          AND msg_body LIKE '바로구매%'
        LIMIT 1
    ");

    return $cnt > 0;
}

/**
 * 구매내역 — 구매 경로 라벨
 *
 * @param array<string, mixed> $row
 */
function trade_purchase_source_label(array $row): string
{
    return trade_purchase_is_buynow($row) ? '바로구매' : '채팅구매';
}

/**
 * 구매내역 — 목록 조회
 *
 * @return array<int, array<string, mixed>>
 */
function trade_purchase_list_for_buyer(int $buyer_mb_idx, int $limit = 15, int $offset = 0): array
{
    if ($buyer_mb_idx < 1 || !trade_purchase_tables_ready()) {
        return [];
    }

    $limit  = max(1, min(50, $limit));
    $offset = max(0, $offset);
    $where  = trade_purchase_buyer_where_sql($buyer_mb_idx);

    $date_col = trade_payment_pay_column_exists('pay_confirmed_at')
        ? 'COALESCE(p.pay_confirmed_at, p.pay_submitted_at, p.pay_created_at)'
        : (trade_payment_pay_column_exists('pay_submitted_at')
            ? 'COALESCE(p.pay_submitted_at, p.pay_created_at)'
            : 'p.pay_created_at');

    $rows = [];
    $rs   = db_query("
        SELECT p.*, t.tr_title, t.tr_card_name, t.tr_method, t.tr_deal_status,
               (SELECT ti_path FROM tb_trade_image WHERE tr_idx = t.tr_idx ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path
        FROM tb_trade_payment p
        INNER JOIN tb_trade t ON t.tr_idx = p.tr_idx
        WHERE {$where}
        ORDER BY {$date_col} DESC, p.pay_idx DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }

    return $rows;
}

/**
 * 판매내역 — 판매자 기준 WHERE 절 (입금 확인 완료·판매취소 환불 건)
 */
function trade_sale_seller_where_sql(int $seller_mb_idx): string
{
    $seller_mb_idx = max(0, $seller_mb_idx);

    return 'p.seller_mb_idx = ' . $seller_mb_idx
        . ' AND p.pay_status IN (' . TRADE_PAYMENT_STATUS_CONFIRMED . ', ' . TRADE_PAYMENT_STATUS_SALE_CANCELLED . ')';
}

/**
 * 판매내역 — 판매 건수
 */
function trade_sale_seller_count(int $seller_mb_idx): int
{
    if ($seller_mb_idx < 1 || !trade_purchase_tables_ready()) {
        return 0;
    }

    return (int) db_result('SELECT COUNT(*) FROM tb_trade_payment p WHERE ' . trade_sale_seller_where_sql($seller_mb_idx));
}

/**
 * 판매내역 — 입금 확인 완료·운송장 미입력(발송대기) 건수
 */
function trade_sale_seller_pending_ship_count(int $seller_mb_idx): int
{
    if ($seller_mb_idx < 1 || !trade_purchase_tables_ready()) {
        return 0;
    }

    $conds = [
        'p.seller_mb_idx = ' . $seller_mb_idx,
        'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_CONFIRMED,
    ];

    if (trade_payment_fulfill_column_ready()) {
        $conds[] = 'p.pay_fulfill_status < ' . (int) TRADE_PAYMENT_FULFILL_ADDR_SENT;
    } elseif (trade_payment_ship_column_ready()) {
        $conds[] = "(TRIM(COALESCE(p.pay_tracking_no, '')) = '' OR TRIM(COALESCE(p.pay_courier_name, '')) = '')";
    } else {
        return 0;
    }

    return (int) db_result('SELECT COUNT(*) FROM tb_trade_payment p WHERE ' . implode(' AND ', $conds));
}

/**
 * 구매·판매내역 — 진행 상태 칩 색상 클래스
 *
 * @param array<string, mixed> $row
 */
function trade_order_fulfill_chip_class(array $row): string
{
    if (trade_payment_is_sale_cancelled_status((int) ($row['pay_status'] ?? 0))) {
        return 'is-cancelled';
    }
    if (!trade_payment_fulfill_column_ready()) {
        return 'is-wait';
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return 'is-done';
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        return 'is-received';
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_ADDR_SENT) {
        return 'is-sent';
    }

    return 'is-wait';
}

/**
 * pay_fulfill_status 진행 상태 라벨
 *
 * @param array<string, mixed> $row
 */
function trade_payment_fulfill_label(array $row): string
{
    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_sale_cancelled_status($status)) {
        return '판매취소(환불)';
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return '구매확정';
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        return '수령확인';
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_ADDR_SENT) {
        return '발송완료';
    }

    return '발송대기';
}

/**
 * 판매내역 — 진행 상태 라벨
 *
 * @param array<string, mixed> $row
 */
function trade_sale_fulfill_label(array $row): string
{
    return trade_payment_fulfill_label($row);
}

/**
 * 판매내역 — 목록 조회
 *
 * @return array<int, array<string, mixed>>
 */
function trade_sale_list_for_seller(int $seller_mb_idx, int $limit = 15, int $offset = 0): array
{
    if ($seller_mb_idx < 1 || !trade_purchase_tables_ready()) {
        return [];
    }

    $limit  = max(1, min(50, $limit));
    $offset = max(0, $offset);
    $where  = trade_sale_seller_where_sql($seller_mb_idx);

    $date_col = trade_payment_pay_column_exists('pay_confirmed_at')
        ? 'COALESCE(p.pay_confirmed_at, p.pay_submitted_at, p.pay_created_at)'
        : (trade_payment_pay_column_exists('pay_submitted_at')
            ? 'COALESCE(p.pay_submitted_at, p.pay_created_at)'
            : 'p.pay_created_at');

    $rows = [];
    $rs   = db_query("
        SELECT p.*, t.tr_title, t.tr_card_name, t.tr_method, t.tr_deal_status,
               m.mb_nick AS buyer_nick,
               (SELECT ti_path FROM tb_trade_image WHERE tr_idx = t.tr_idx ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path
        FROM tb_trade_payment p
        INNER JOIN tb_trade t ON t.tr_idx = p.tr_idx
        LEFT JOIN tb_member m ON m.mb_idx = p.buyer_mb_idx
        WHERE {$where}
        ORDER BY {$date_col} DESC, p.pay_idx DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }

    return $rows;
}

/**
 * POST action=request|submit_deposit|bank_info 처리. 해당 action 이 아니면 null.
 *
 * @param array<string, mixed> $me
 * @return array<string, mixed>|null
 */
function trade_payment_process_post(array $me): ?array
{
    $action = trim((string) ($_POST['action'] ?? ''));
    if (!in_array($action, ['request', 'submit_deposit', 'pay_cash', 'bank_info', 'confirm_shipping', 'confirm_purchase'], true)) {
        return null;
    }

    $my_mb = (int) ($me['mb_idx'] ?? 0);
    if ($my_mb < 1) {
        return ['ok' => false, 'error' => '로그인이 필요합니다.'];
    }

    if ($action === 'bank_info') {
        require_once __DIR__ . '/../../lib/_member_cash.php';
        $cash_ready = member_cash_column_ready();

        return [
            'ok'           => true,
            'bank'         => trade_payment_bank_info(),
            'cash_ready'   => $cash_ready,
            'cash_balance' => $cash_ready ? member_cash_balance($my_mb) : 0,
        ];
    }

    if (!trade_payment_tables_ready()) {
        return [
            'ok'    => false,
            'error' => '결제 DB 설정이 완료되지 않았습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.',
        ];
    }

    if ($action === 'request') {
        $tr_idx   = (int) ($_POST['tr_idx'] ?? 0);
        $room_idx = (int) ($_POST['room_idx'] ?? 0);
        if ($room_idx < 1) {
            return ['ok' => false, 'error' => '대화방이 선택되지 않았습니다. 왼쪽 목록에서 구매자 대화를 선택해 주세요.'];
        }
        $parsed = trade_payment_parse_amount($_POST['amount'] ?? '');
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => (string) ($parsed['error'] ?? '금액이 올바르지 않습니다.')];
        }

        $trade = db_assoc(db_query("
            SELECT tr_idx, mb_idx, tr_status, tr_card_name, tr_title
            FROM tb_trade
            WHERE tr_idx = {$tr_idx}
            LIMIT 1
        "));
        if (!$trade || (int) $trade['tr_status'] !== 1) {
            return ['ok' => false, 'error' => '거래글을 찾을 수 없습니다.'];
        }
        if ((int) $trade['mb_idx'] !== $my_mb) {
            return ['ok' => false, 'error' => '판매자만 결제 요청을 보낼 수 있습니다.'];
        }

        $result = trade_payment_create_request(
            $tr_idx,
            $room_idx,
            $my_mb,
            (int) $parsed['amount'],
            trade_chat_inquiry_product_label($trade),
            trim((string) ($me['mb_nick'] ?? ''))
        );
        if (!$result['ok']) {
            $err = trim((string) ($result['error'] ?? ''));
            return ['ok' => false, 'error' => $err !== '' ? $err : '결제 요청에 실패했습니다.'];
        }

        return [
            'ok'       => true,
            'pay_idx'  => (int) ($result['pay_idx'] ?? 0),
            'msg_idx'  => (int) ($result['msg_idx'] ?? 0),
            'room_idx' => (int) ($result['room_idx'] ?? 0),
        ];
    }

    $pay_idx = (int) ($_POST['pay_idx'] ?? 0);
    if ($action === 'confirm_shipping') {
        return trade_payment_confirm_shipping($pay_idx, $my_mb);
    }
    if ($action === 'confirm_purchase') {
        return trade_payment_confirm_purchase($pay_idx, $my_mb);
    }

    if ($action === 'pay_cash') {
        $request_msg_idx = (int) ($_POST['request_msg_idx'] ?? 0);
        $result = trade_payment_pay_with_cash($my_mb, $pay_idx, $request_msg_idx);
        if (!$result['ok']) {
            $err = trim((string) ($result['error'] ?? ''));
            return ['ok' => false, 'error' => $err !== '' ? $err : '캐시 결제에 실패했습니다.'];
        }

        return [
            'ok'           => true,
            'payment'      => $result['payment'] ?? null,
            'cash_balance' => (int) ($result['cash_balance'] ?? 0),
        ];
    }

    if ($action !== 'submit_deposit') {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }

    $name   = trim((string) ($_POST['depositor_name'] ?? ''));
    $result = trade_payment_submit_deposit($pay_idx, $my_mb, $name);
    if (!$result['ok']) {
        $err = trim((string) ($result['error'] ?? ''));
        return ['ok' => false, 'error' => $err !== '' ? $err : '입금 신청에 실패했습니다.'];
    }

    return [
        'ok'      => true,
        'payment' => $result['payment'] ?? null,
        'bank'    => trade_payment_bank_info(),
    ];
}
