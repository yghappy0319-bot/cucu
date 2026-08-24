<?php

function member_cash_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_member')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_cash'"));

    return $ready;
}

function member_cash_log_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_cash_log');

    return $ready;
}

function member_cash_balance(int $mb_idx): int
{
    if (!member_cash_column_ready() || $mb_idx < 1) {
        return 0;
    }

    return (int) db_result("SELECT mb_cash FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
}

/**
 * 캐시 내역 1건 기록 (호출 전 잔액 UPDATE 완료·동일 트랜잭션 권장)
 */
function member_cash_log_insert(
    int $mb_idx,
    int $change,
    int $balance,
    string $type,
    string $memo,
    ?int $au_idx = null,
    ?int $ao_idx = null
): bool {
    if (!member_cash_log_table_ready() || $mb_idx < 1 || $change === 0) {
        return false;
    }

    $esc_type = db_escape($type);
    $esc_memo = db_escape($memo);
    $au_sql   = $au_idx !== null && $au_idx > 0 ? (string) (int) $au_idx : 'NULL';
    $ao_sql   = $ao_idx !== null && $ao_idx > 0 ? (string) (int) $ao_idx : 'NULL';

    return (bool) db_query("
        INSERT INTO tb_cash_log (mb_idx, cl_change, cl_balance, cl_type, cl_memo, au_idx, ao_idx)
        VALUES ({$mb_idx}, {$change}, {$balance}, '{$esc_type}', '{$esc_memo}', {$au_sql}, {$ao_sql})
    ");
}

/**
 * 캐시 증감 + 내역 (단독 트랜잭션)
 */
function member_cash_change_with_log(
    int $mb_idx,
    int $delta,
    string $type,
    string $memo,
    ?int $au_idx = null,
    ?int $ao_idx = null
): bool {
    if (!member_cash_column_ready() || $mb_idx < 1 || $delta === 0) {
        return false;
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return false;
    }

    if ($delta > 0) {
        $ok = db_query("
            UPDATE tb_member SET mb_cash = mb_cash + {$delta}
            WHERE mb_idx = {$mb_idx} LIMIT 1
        ");
    } else {
        $amt = abs($delta);
        $ok  = db_query("
            UPDATE tb_member SET mb_cash = mb_cash - {$amt}
            WHERE mb_idx = {$mb_idx} AND mb_cash >= {$amt} LIMIT 1
        ");
    }
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return false;
    }

    $bal = member_cash_balance($mb_idx);
    if (member_cash_log_table_ready() && !member_cash_log_insert($mb_idx, $delta, $bal, $type, $memo, $au_idx, $ao_idx)) {
        mysqli_rollback($conn);

        return false;
    }

    mysqli_commit($conn);

    return true;
}

function member_cash_deduct(int $mb_idx, int $amount): bool
{
    return member_cash_change_with_log(
        $mb_idx,
        -$amount,
        'etc',
        '캐시 차감',
        null,
        null
    );
}

function member_cash_add(int $mb_idx, int $amount): bool
{
    return member_cash_change_with_log(
        $mb_idx,
        $amount,
        'etc',
        '캐시 적립',
        null,
        null
    );
}

/** @return array<string, string> */
function member_cash_type_labels(): array
{
    return [
        'auction_pay'             => '경매 결제',
        'auction_settle'          => '경매 판매 정산',
        'auction_refund'          => '경매 환불',
        'auction_refund_clawback' => '경매 환불 회수',
        'trade_pay'               => '거래 캐시 결제',
        'trade_settle'            => '거래 판매 정산',
        'trade_refund'            => '거래 판매취소 환불',
        'cash_withdraw'           => '캐시 출금',
        'cash_withdraw_cancel'    => '캐시 출금 취소',
        'cash_charge'             => '캐시 충전',
        'admin'                   => '관리',
        'etc'                     => '기타',
    ];
}

function member_cash_type_label(string $type): string
{
    $labels = member_cash_type_labels();

    return $labels[$type] ?? $type;
}

function member_cash_log_is_settle_type(string $type): bool
{
    return $type === 'trade_settle' || $type === 'auction_settle';
}

/**
 * 메모에서 수수료·결제번호 추출 (레거시 로그용)
 *
 * @return array{fee?: int, pay_idx?: int}
 */
function member_cash_log_parse_settle_memo(string $memo): array
{
    $out = [];
    if (preg_match('/pay#(\d+)/', $memo, $m)) {
        $out['pay_idx'] = (int) $m[1];
    }
    if (preg_match('/수수료\s*₩?\s*([\d,]+)/u', $memo, $m)) {
        $out['fee'] = (int) str_replace(',', '', $m[1]);
    }

    return $out;
}

/**
 * 판매 정산 로그의 결제액·수수료·정산액 표시용 정보
 *
 * @param array<string, mixed> $row cl_change, cl_type, cl_memo, ao_idx ...
 * @return array{ok: bool, settle: int, fee: int, gross: int, fee_rate: int, title: string}|null
 */
function member_cash_log_settle_breakdown(array $row): ?array
{
    $type = (string) ($row['cl_type'] ?? '');
    if (!member_cash_log_is_settle_type($type)) {
        return null;
    }

    $settle = max(0, (int) ($row['cl_change'] ?? 0));
    $memo   = (string) ($row['cl_memo'] ?? '');
    $parsed = member_cash_log_parse_settle_memo($memo);
    $fee    = max(0, (int) ($parsed['fee'] ?? 0));
    $gross  = 0;
    $rate   = 0;
    $title  = '';

    if ($type === 'trade_settle') {
        $pay_idx = (int) ($parsed['pay_idx'] ?? 0);
        if ($pay_idx > 0 && db_table_exists('tb_trade_payment_fee')) {
            $fee_row = db_assoc(db_query("
                SELECT tpf_gross_amount, tpf_fee_rate, tpf_fee_amount, tpf_seller_amount, tr_idx
                FROM tb_trade_payment_fee
                WHERE pay_idx = {$pay_idx}
                LIMIT 1
            "));
            if ($fee_row) {
                $gross  = (int) ($fee_row['tpf_gross_amount'] ?? 0);
                $fee    = (int) ($fee_row['tpf_fee_amount'] ?? 0);
                $rate   = (int) ($fee_row['tpf_fee_rate'] ?? 0);
                $settle = (int) ($fee_row['tpf_seller_amount'] ?? $settle);
                $tr_idx = (int) ($fee_row['tr_idx'] ?? 0);
                if ($tr_idx > 0 && db_table_exists('tb_trade')) {
                    $title = (string) db_result("SELECT tr_title FROM tb_trade WHERE tr_idx = {$tr_idx} LIMIT 1");
                }
            }
        }
        if ($title === '') {
            $title = preg_replace('/\s*\(pay#\d+.*$/u', '', $memo) ?? $memo;
            $title = preg_replace('/^거래 구매확정:\s*/u', '', $title) ?? $title;
            $title = trim($title);
        }
    } elseif ($type === 'auction_settle') {
        $ao_idx = (int) ($row['ao_idx'] ?? 0);
        if ($ao_idx > 0 && db_table_exists('tb_auction_order')) {
            $has_fee = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_platform_fee'"));
            if ($has_fee) {
                $ao = db_assoc(db_query("
                    SELECT ao_amount, ao_platform_fee, ao_seller_settle_amount
                    FROM tb_auction_order
                    WHERE ao_idx = {$ao_idx}
                    LIMIT 1
                "));
                if ($ao) {
                    $gross  = (int) ($ao['ao_amount'] ?? 0);
                    $fee    = (int) ($ao['ao_platform_fee'] ?? 0);
                    $settle = (int) ($ao['ao_seller_settle_amount'] ?? $settle);
                }
            }
        }
        if (function_exists('platform_fee_auction_percent')) {
            $rate = platform_fee_auction_percent();
        } elseif (defined('AUCTION_PLATFORM_FEE_PERCENT')) {
            $rate = (int) AUCTION_PLATFORM_FEE_PERCENT;
        } elseif ($gross > 0 && $fee > 0) {
            $rate = (int) round($fee * 100 / $gross);
        }
        $title = trim((string) ($row['au_title'] ?? ''));
        if ($title === '') {
            $title = preg_replace('/^경매 판매 정산\s*\([^)]*\):\s*/u', '', $memo) ?? $memo;
            $title = trim($title);
        }
    }

    if ($gross < 1 && $settle > 0) {
        $gross = $settle + $fee;
    }
    if ($rate < 1 && $gross > 0 && $fee > 0) {
        $rate = (int) round($fee * 100 / $gross);
    }
    if ($title === '') {
        $title = $memo !== '' ? $memo : '판매 정산';
    }

    return [
        'ok'       => true,
        'settle'   => $settle,
        'fee'      => $fee,
        'gross'    => $gross,
        'fee_rate' => $rate,
        'title'    => $title,
    ];
}
