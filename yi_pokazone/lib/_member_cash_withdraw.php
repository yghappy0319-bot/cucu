<?php

require_once __DIR__ . '/_member_cash.php';
require_once __DIR__ . '/_member_settle.php';

if (!defined('MEMBER_CASH_WITHDRAW_MIN')) {
    define('MEMBER_CASH_WITHDRAW_MIN', 10000);
}
if (!defined('MEMBER_CASH_WITHDRAW_MAX')) {
    define('MEMBER_CASH_WITHDRAW_MAX', 99999999);
}
if (!defined('MEMBER_CASH_WITHDRAW_FEE_RATE')) {
    define('MEMBER_CASH_WITHDRAW_FEE_RATE', 0);
}

if (!defined('MEMBER_CASH_WITHDRAW_STATUS_PENDING')) {
    define('MEMBER_CASH_WITHDRAW_STATUS_PENDING', 0);
}
if (!defined('MEMBER_CASH_WITHDRAW_STATUS_DONE')) {
    define('MEMBER_CASH_WITHDRAW_STATUS_DONE', 1);
}
if (!defined('MEMBER_CASH_WITHDRAW_STATUS_CANCELLED')) {
    define('MEMBER_CASH_WITHDRAW_STATUS_CANCELLED', 2);
}

function member_cash_withdraw_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_cash_withdraw');

    return $ready;
}

function member_cash_withdraw_fee_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_cash_withdraw_fee');

    return $ready;
}

function member_cash_withdraw_amount_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!member_cash_withdraw_table_ready()) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_cash_withdraw LIKE 'cw_gross_amount'"));

    return $ready;
}

/** @return array<int, string> */
function member_cash_withdraw_status_labels(): array
{
    return [
        MEMBER_CASH_WITHDRAW_STATUS_PENDING   => '출금 신청',
        MEMBER_CASH_WITHDRAW_STATUS_DONE      => '출금 완료',
        MEMBER_CASH_WITHDRAW_STATUS_CANCELLED => '출금 취소',
    ];
}

function member_cash_withdraw_status_label(int $status): string
{
    $labels = member_cash_withdraw_status_labels();

    return $labels[$status] ?? '알 수 없음';
}

/**
 * 출금 금액 검증 (수수료 없음 — 신청 금액 = 입금액)
 *
 * @return array{ok: bool, error?: string, gross?: int, fee_rate?: int, fee?: int, net?: int}
 */
function member_cash_withdraw_calc_fee(int $gross, ?int $fee_rate = null): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($gross < 1) {
        return $fail('출금 금액을 입력해 주세요.');
    }

    return [
        'ok'       => true,
        'gross'    => $gross,
        'fee_rate' => 0,
        'fee'      => 0,
        'net'      => $gross,
    ];
}

/**
 * 거래 결제 건 연동 정보 (판매자 본인·구매확정 건만)
 *
 * @return array{ok: bool, error?: string, source_type?: string, pay_idx?: int, tr_idx?: int, product_label?: string}
 */
function member_cash_withdraw_resolve_trade_source(int $mb_idx, int $pay_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($pay_idx < 1) {
        return $fail('잘못된 거래 정보입니다.');
    }
    if (!db_table_exists('tb_trade_payment')) {
        return $fail('거래 결제 정보를 찾을 수 없습니다.');
    }

    $row = db_assoc(db_query("
        SELECT pay_idx, tr_idx, seller_mb_idx, product_label, pay_fulfill_status, pay_status,
               pay_seller_settle_amount, pay_amount
        FROM tb_trade_payment
        WHERE pay_idx = {$pay_idx}
        LIMIT 1
    "));
    if (!$row) {
        return $fail('거래 결제 정보를 찾을 수 없습니다.');
    }
    if ((int) ($row['seller_mb_idx'] ?? 0) !== $mb_idx) {
        return $fail('본인 판매 건만 연결할 수 있습니다.');
    }
    if ((int) ($row['pay_status'] ?? 0) !== 2) {
        return $fail('입금 확인된 거래만 출금 연결할 수 있습니다.');
    }

    $fulfill_col = db_assoc(db_query("SHOW COLUMNS FROM tb_trade_payment LIKE 'pay_fulfill_status'"));
    if ($fulfill_col) {
        $fulfill = (int) ($row['pay_fulfill_status'] ?? 0);
        if ($fulfill < 3) {
            return $fail('구매확정된 거래만 연결할 수 있습니다.');
        }
    }

    return [
        'ok'            => true,
        'source_type'   => 'trade',
        'pay_idx'       => $pay_idx,
        'tr_idx'        => (int) ($row['tr_idx'] ?? 0),
        'product_label' => trim((string) ($row['product_label'] ?? '')),
        'settle_amount' => (int) ($row['pay_seller_settle_amount'] ?? $row['pay_amount'] ?? 0),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function member_cash_withdraw_list_for_member(int $mb_idx, int $limit = 20): array
{
    if (!member_cash_withdraw_table_ready() || $mb_idx < 1) {
        return [];
    }
    $limit    = max(1, min(100, $limit));
    $has_amt  = member_cash_withdraw_amount_columns_ready();
    $has_fee  = member_cash_withdraw_fee_table_ready();
    $amt_sel  = $has_amt
        ? 'w.cw_gross_amount, w.cw_fee_rate, w.cw_fee_amount, w.cw_net_amount'
        : 'w.cw_amount AS cw_gross_amount, 0 AS cw_fee_rate, 0 AS cw_fee_amount, w.cw_amount AS cw_net_amount';
    $fee_join = $has_fee
        ? 'LEFT JOIN tb_cash_withdraw_fee f ON f.cw_idx = w.cw_idx'
        : '';
    $fee_sel  = $has_fee
        ? ', f.cwf_source_type, f.pay_idx, f.tr_idx, f.cwf_product_label'
        : ", 'general' AS cwf_source_type, NULL AS pay_idx, NULL AS tr_idx, '' AS cwf_product_label";

    $out = [];
    $rs  = db_query("
        SELECT w.cw_idx, w.cw_amount, w.cw_bank, w.cw_holder, w.cw_account, w.cw_status,
               w.cw_balance_after, w.cw_created_at, w.cw_processed_at,
               {$amt_sel}
               {$fee_sel}
        FROM tb_cash_withdraw w
        {$fee_join}
        WHERE w.mb_idx = {$mb_idx}
        ORDER BY w.cw_idx DESC
        LIMIT {$limit}
    ");
    while ($row = db_assoc($rs)) {
        if (!$has_amt) {
            $gross = (int) ($row['cw_amount'] ?? 0);
            $row['cw_gross_amount'] = $gross;
            $row['cw_fee_rate']     = 0;
            $row['cw_fee_amount']   = 0;
            $row['cw_net_amount']   = $gross;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * @param array{pay_idx?: int} $options
 * @return array{ok: bool, error?: string, cw_idx?: int, balance?: int, gross?: int, fee?: int, net?: int}
 */
function member_cash_withdraw_apply(int $mb_idx, int $gross_amount, array $options = []): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!member_cash_column_ready()) {
        return $fail('캐시 기능이 준비되지 않았습니다. sql/migrate_tb_member_cash.sql 을 적용해 주세요.');
    }
    if (!member_cash_log_table_ready()) {
        return $fail('캐시 내역 기능이 준비되지 않았습니다. sql/migrate_tb_cash_log.sql 을 적용해 주세요.');
    }
    if (!member_cash_withdraw_table_ready()) {
        return $fail('출금 기능이 준비되지 않았습니다. sql/migrate_tb_cash_withdraw.sql 을 적용해 주세요.');
    }
    if (!member_cash_withdraw_amount_columns_ready()) {
        return $fail('출금 수수료 기능 DB가 없습니다. sql/migrate_tb_cash_withdraw_amounts.sql 을 적용해 주세요.');
    }
    if (!member_cash_withdraw_fee_table_ready()) {
        return $fail('출금 수수료 내역 DB가 없습니다. sql/migrate_tb_cash_withdraw_fee.sql 을 적용해 주세요.');
    }
    if (!member_settle_account_column_ready()) {
        return $fail('정산계좌 기능이 준비되지 않았습니다. sql/migrate_tb_member_settle_account.sql 을 적용해 주세요.');
    }

    $settle = member_settle_account_get($mb_idx);
    if (!member_settle_account_is_registered($settle)) {
        return $fail('출금 신청 전에 정산계좌를 등록해 주세요.');
    }

    if ($gross_amount < MEMBER_CASH_WITHDRAW_MIN) {
        return $fail('최소 출금 금액은 ₩' . number_format(MEMBER_CASH_WITHDRAW_MIN) . '입니다.');
    }
    if ($gross_amount > MEMBER_CASH_WITHDRAW_MAX) {
        return $fail('1회 출금 한도를 초과했습니다.');
    }

    $fee_calc = member_cash_withdraw_calc_fee($gross_amount);
    if (!$fee_calc['ok']) {
        return $fee_calc;
    }

    $gross    = (int) $fee_calc['gross'];
    $fee_rate = (int) $fee_calc['fee_rate'];
    $fee_amt  = (int) $fee_calc['fee'];
    $net_amt  = (int) $fee_calc['net'];

    $source_type   = 'general';
    $pay_idx       = 0;
    $tr_idx        = 0;
    $product_label = '';

    $opt_pay = (int) ($options['pay_idx'] ?? 0);
    if ($opt_pay > 0) {
        $trade_src = member_cash_withdraw_resolve_trade_source($mb_idx, $opt_pay);
        if (!$trade_src['ok']) {
            return $trade_src;
        }
        $source_type   = (string) ($trade_src['source_type'] ?? 'trade');
        $pay_idx       = (int) ($trade_src['pay_idx'] ?? 0);
        $tr_idx        = (int) ($trade_src['tr_idx'] ?? 0);
        $product_label = (string) ($trade_src['product_label'] ?? '');
    }

    $bank    = trim((string) ($settle['mb_settle_bank'] ?? ''));
    $holder  = trim((string) ($settle['mb_settle_holder'] ?? ''));
    $account = trim((string) ($settle['mb_settle_account'] ?? ''));

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('출금 신청을 시작하지 못했습니다.');
    }

    $bal_row = db_assoc(db_query("SELECT mb_cash FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1 FOR UPDATE"));
    if (!$bal_row) {
        mysqli_rollback($conn);

        return $fail('회원 정보를 찾을 수 없습니다.');
    }
    $balance = (int) ($bal_row['mb_cash'] ?? 0);
    if ($gross > $balance) {
        mysqli_rollback($conn);

        return $fail('보유 캐시가 부족합니다. (현재 ₩' . number_format($balance) . ')');
    }

    $deduct_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash - {$gross}
        WHERE mb_idx = {$mb_idx} AND mb_cash >= {$gross}
        LIMIT 1
    ");
    if (!$deduct_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('캐시 차감에 실패했습니다.');
    }

    $balance_after = $balance - $gross;
    $esc_bank      = db_escape($bank);
    $esc_holder    = db_escape($holder);
    $esc_account   = db_escape($account);

    $insert_ok = db_query("
        INSERT INTO tb_cash_withdraw (
            mb_idx, cw_amount, cw_gross_amount, cw_fee_rate, cw_fee_amount, cw_net_amount,
            cw_bank, cw_holder, cw_account,
            cw_status, cw_balance_after, cw_created_at
        ) VALUES (
            {$mb_idx}, {$net_amt}, {$gross}, {$fee_rate}, {$fee_amt}, {$net_amt},
            '{$esc_bank}', '{$esc_holder}', '{$esc_account}',
            " . MEMBER_CASH_WITHDRAW_STATUS_PENDING . ", {$balance_after}, NOW()
        )
    ");
    if (!$insert_ok) {
        mysqli_rollback($conn);

        return $fail('출금 신청 저장에 실패했습니다.');
    }

    $cw_idx = (int) db_insert_id();
    $esc_source = db_escape($source_type);
    $esc_product = db_escape($product_label);
    $pay_sql = $pay_idx > 0 ? (string) $pay_idx : 'NULL';
    $tr_sql  = $tr_idx > 0 ? (string) $tr_idx : 'NULL';

    $fee_insert_ok = db_query("
        INSERT INTO tb_cash_withdraw_fee (
            cw_idx, mb_idx, cwf_source_type, pay_idx, tr_idx,
            cwf_product_label, cwf_gross_amount, cwf_fee_rate, cwf_fee_amount, cwf_net_amount,
            cwf_created_at
        ) VALUES (
            {$cw_idx}, {$mb_idx}, '{$esc_source}', {$pay_sql}, {$tr_sql},
            '{$esc_product}', {$gross}, {$fee_rate}, {$fee_amt}, {$net_amt},
            NOW()
        )
    ");
    if (!$fee_insert_ok) {
        mysqli_rollback($conn);

        return $fail('출금 수수료 내역 저장에 실패했습니다.');
    }

    $memo = '캐시 출금 신청 #' . $cw_idx . ' (₩' . number_format($gross) . ')';
    if ($product_label !== '') {
        $memo .= ' · ' . $product_label;
    }
    if (!member_cash_log_insert($mb_idx, -$gross, $balance_after, 'cash_withdraw', $memo)) {
        mysqli_rollback($conn);

        return $fail('캐시 내역 저장에 실패했습니다.');
    }

    mysqli_commit($conn);

    return [
        'ok'      => true,
        'cw_idx'  => $cw_idx,
        'balance' => $balance_after,
        'gross'   => $gross,
        'fee'     => $fee_amt,
        'net'     => $net_amt,
    ];
}

/**
 * 관리자 — 출금 신청 완료 처리 (송금 완료 표시)
 *
 * @return array{ok: bool, error?: string, message?: string}
 */
function member_cash_withdraw_complete(int $cw_idx, string $memo = ''): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($cw_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!member_cash_withdraw_table_ready()) {
        return $fail('출금 기능이 준비되지 않았습니다.');
    }

    $memo = trim($memo);
    if (mb_strlen($memo) > 200) {
        return $fail('메모는 200자 이내로 입력해 주세요.');
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('처리를 시작하지 못했습니다.');
    }

    $row = db_assoc(db_query("
        SELECT cw_idx, cw_status, cw_net_amount
        FROM tb_cash_withdraw
        WHERE cw_idx = {$cw_idx}
        LIMIT 1
        FOR UPDATE
    "));
    if (!$row) {
        mysqli_rollback($conn);

        return $fail('출금 신청을 찾을 수 없습니다.');
    }
    if ((int) ($row['cw_status'] ?? -1) !== MEMBER_CASH_WITHDRAW_STATUS_PENDING) {
        mysqli_rollback($conn);

        return $fail('출금 신청 상태인 건만 완료 처리할 수 있습니다.');
    }

    $esc_memo = db_escape($memo);
    $ok = db_query("
        UPDATE tb_cash_withdraw
        SET cw_status = " . MEMBER_CASH_WITHDRAW_STATUS_DONE . ",
            cw_memo = '{$esc_memo}',
            cw_processed_at = NOW()
        WHERE cw_idx = {$cw_idx}
          AND cw_status = " . MEMBER_CASH_WITHDRAW_STATUS_PENDING . "
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('출금 완료 처리에 실패했습니다.');
    }

    mysqli_commit($conn);

    return [
        'ok'      => true,
        'message' => '출금 완료로 처리했습니다. (입금액 ₩' . number_format((int) ($row['cw_net_amount'] ?? 0)) . ')',
    ];
}

/**
 * 출금 신청 취소 (차감 캐시 환불)
 * 출금 완료 전(신청 상태)만 가능. $require_mb_idx 를 주면 본인 신청만 취소.
 *
 * @return array{ok: bool, error?: string, message?: string}
 */
function member_cash_withdraw_cancel(int $cw_idx, string $memo = '', int $require_mb_idx = 0): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($cw_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!member_cash_withdraw_table_ready()) {
        return $fail('출금 기능이 준비되지 않았습니다.');
    }
    if (!member_cash_column_ready()) {
        return $fail('캐시 기능이 준비되지 않았습니다.');
    }

    $memo = trim($memo);
    if (mb_strlen($memo) > 200) {
        return $fail('메모는 200자 이내로 입력해 주세요.');
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('처리를 시작하지 못했습니다.');
    }

    $has_amt = member_cash_withdraw_amount_columns_ready();
    $amt_sel = $has_amt ? 'cw_gross_amount, cw_net_amount' : 'cw_amount AS cw_gross_amount, cw_amount AS cw_net_amount';
    $row = db_assoc(db_query("
        SELECT cw_idx, mb_idx, cw_status, cw_bank, cw_holder, cw_account, {$amt_sel}
        FROM tb_cash_withdraw
        WHERE cw_idx = {$cw_idx}
        LIMIT 1
        FOR UPDATE
    "));
    if (!$row) {
        mysqli_rollback($conn);

        return $fail('출금 신청을 찾을 수 없습니다.');
    }
    if ($require_mb_idx > 0 && (int) ($row['mb_idx'] ?? 0) !== $require_mb_idx) {
        mysqli_rollback($conn);

        return $fail('본인의 출금 신청만 취소할 수 있습니다.');
    }
    if ((int) ($row['cw_status'] ?? -1) !== MEMBER_CASH_WITHDRAW_STATUS_PENDING) {
        mysqli_rollback($conn);

        return $fail('출금 완료 전(신청 중)인 건만 취소할 수 있습니다.');
    }

    $mb_idx = (int) ($row['mb_idx'] ?? 0);
    $gross  = (int) ($row['cw_gross_amount'] ?? 0);
    $net    = (int) ($row['cw_net_amount'] ?? $gross);
    if ($mb_idx < 1 || $gross < 1) {
        mysqli_rollback($conn);

        return $fail('출금 신청 데이터가 올바르지 않습니다.');
    }

    $bal_row = db_assoc(db_query("SELECT mb_cash, mb_id, mb_nick FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1 FOR UPDATE"));
    if (!$bal_row) {
        mysqli_rollback($conn);

        return $fail('회원 정보를 찾을 수 없습니다.');
    }

    $refund_ok = db_query("
        UPDATE tb_member
        SET mb_cash = mb_cash + {$gross}
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    ");
    if (!$refund_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('캐시 환불에 실패했습니다.');
    }

    $balance_after = (int) ($bal_row['mb_cash'] ?? 0) + $gross;
    $esc_memo = db_escape($memo);
    $status_ok = db_query("
        UPDATE tb_cash_withdraw
        SET cw_status = " . MEMBER_CASH_WITHDRAW_STATUS_CANCELLED . ",
            cw_memo = '{$esc_memo}',
            cw_processed_at = NOW()
        WHERE cw_idx = {$cw_idx}
          AND cw_status = " . MEMBER_CASH_WITHDRAW_STATUS_PENDING . "
        LIMIT 1
    ");
    if (!$status_ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('출금 취소 처리에 실패했습니다.');
    }

    $log_memo = '출금 신청 취소 #' . $cw_idx . ' (₩' . number_format($gross) . ' 환불)';
    if ($memo !== '') {
        $log_memo .= ' · ' . $memo;
    }
    if (member_cash_log_table_ready()
        && !member_cash_log_insert($mb_idx, $gross, $balance_after, 'cash_withdraw_cancel', $log_memo)
    ) {
        mysqli_rollback($conn);

        return $fail('캐시 내역 저장에 실패했습니다.');
    }

    mysqli_commit($conn);

    $cancel_by = $require_mb_idx > 0 ? '회원 직접 취소' : '관리자 취소';
    $telegram_lib = __DIR__ . '/_telegram.php';
    if (is_file($telegram_lib)) {
        require_once $telegram_lib;
        if (function_exists('telegram_notify_cash_withdraw_cancel')) {
            telegram_notify_cash_withdraw_cancel([
                'cw_idx'  => $cw_idx,
                'mb_idx'  => $mb_idx,
                'mb_id'   => (string) ($bal_row['mb_id'] ?? ''),
                'mb_nick' => (string) ($bal_row['mb_nick'] ?? ''),
                'gross'   => $gross,
                'net'     => $net,
                'bank'    => (string) ($row['cw_bank'] ?? ''),
                'holder'  => (string) ($row['cw_holder'] ?? ''),
                'account' => (string) ($row['cw_account'] ?? ''),
                'by'      => $cancel_by,
                'memo'    => $memo,
            ]);
        }
    }

    return [
        'ok'      => true,
        'message' => '출금을 취소하고 ₩' . number_format($gross) . '을(를) 캐시로 환불했습니다.',
    ];
}
