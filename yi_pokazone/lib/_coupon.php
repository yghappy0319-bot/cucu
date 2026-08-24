<?php

if (!defined('COUPON_DISCOUNT_FIXED')) {
    define('COUPON_DISCOUNT_FIXED', 1);
}
if (!defined('COUPON_DISCOUNT_PERCENT')) {
    define('COUPON_DISCOUNT_PERCENT', 2);
}
if (!defined('MEMBER_COUPON_STATUS_AVAILABLE')) {
    define('MEMBER_COUPON_STATUS_AVAILABLE', 0);
}
if (!defined('MEMBER_COUPON_STATUS_USED')) {
    define('MEMBER_COUPON_STATUS_USED', 1);
}
if (!defined('MEMBER_COUPON_STATUS_CANCELLED')) {
    define('MEMBER_COUPON_STATUS_CANCELLED', 9);
}
if (!defined('COUPON_ISSUE_ALL')) {
    define('COUPON_ISSUE_ALL', 1);
}
if (!defined('COUPON_ISSUE_MEMBER')) {
    define('COUPON_ISSUE_MEMBER', 2);
}

function coupon_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_coupon');

    return $ready;
}

function member_coupon_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_member_coupon');

    return $ready;
}

function trade_payment_coupon_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade_payment')) {
        $ready = false;

        return $ready;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_payment LIKE 'pay_member_coupon_idx'"));

    return $ready;
}

/** @return array<string, string> */
function coupon_discount_type_labels(): array
{
    return [
        (string) COUPON_DISCOUNT_FIXED   => '정액 할인 (원)',
        (string) COUPON_DISCOUNT_PERCENT => '정률 할인 (%)',
    ];
}

function coupon_discount_type_label(int $type): string
{
    $labels = coupon_discount_type_labels();

    return $labels[(string) $type] ?? '알 수 없음';
}

function coupon_format_discount(array $row): string
{
    $type  = (int) ($row['mc_discount_type'] ?? $row['cp_discount_type'] ?? 0);
    $value = (int) ($row['mc_discount_value'] ?? $row['cp_discount_value'] ?? 0);
    if ($type === COUPON_DISCOUNT_PERCENT) {
        return $value . '%';
    }

    return '₩' . number_format($value);
}

/**
 * @return array{discount: int, pay_amount: int}
 */
function coupon_calc_amounts(int $discount_type, int $discount_value, int $order_amount): array
{
    $order_amount = max(0, $order_amount);
    $discount     = 0;

    if ($discount_type === COUPON_DISCOUNT_PERCENT) {
        $rate     = max(0, min(100, $discount_value));
        $discount = (int) floor($order_amount * $rate / 100);
    } else {
        $discount = max(0, $discount_value);
    }

    if ($discount > $order_amount) {
        $discount = $order_amount;
    }
    if ($order_amount > 0 && $discount >= $order_amount) {
        $discount = max(0, $order_amount - 1);
    }

    return [
        'discount'    => $discount,
        'pay_amount'  => max(1, $order_amount - $discount),
    ];
}

/**
 * @return array{ok: bool, error?: string, row?: array<string, mixed>, discount?: int, pay_amount?: int}
 */
function coupon_validate_member_coupon(int $mc_idx, int $mb_idx, int $order_amount): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mc_idx < 1 || $mb_idx < 1) {
        return $fail('쿠폰을 선택해 주세요.');
    }
    if (!member_coupon_table_ready()) {
        return $fail('쿠폰 기능이 준비되지 않았습니다.');
    }
    if ($order_amount < 1) {
        return $fail('결제 금액이 올바르지 않습니다.');
    }

    $row = db_assoc(db_query("
        SELECT mc.*, cp.cp_status
        FROM tb_member_coupon mc
        LEFT JOIN tb_coupon cp ON cp.cp_idx = mc.cp_idx
        WHERE mc.mc_idx = {$mc_idx}
          AND mc.mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return $fail('사용할 수 없는 쿠폰입니다.');
    }
    if ((int) ($row['mc_status'] ?? -1) !== MEMBER_COUPON_STATUS_AVAILABLE) {
        return $fail('이미 사용했거나 만료된 쿠폰입니다.');
    }
    if ((int) ($row['cp_status'] ?? 0) === 9) {
        return $fail('비활성화된 쿠폰입니다.');
    }

    $today = date('Y-m-d');
    $from  = (string) ($row['mc_valid_from'] ?? '');
    $until = (string) ($row['mc_valid_until'] ?? '');
    if ($from !== '' && $today < $from) {
        return $fail('아직 사용 기간이 아닌 쿠폰입니다.');
    }
    if ($until !== '' && $today > $until) {
        return $fail('사용 기간이 지난 쿠폰입니다.');
    }

    $calc = coupon_calc_amounts(
        (int) ($row['mc_discount_type'] ?? 0),
        (int) ($row['mc_discount_value'] ?? 0),
        $order_amount
    );
    if ($calc['discount'] < 1) {
        return $fail('이 주문 금액에는 적용할 수 없는 쿠폰입니다.');
    }

    return [
        'ok'         => true,
        'row'        => $row,
        'discount'   => (int) $calc['discount'],
        'pay_amount' => (int) $calc['pay_amount'],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function coupon_list_available_for_member(int $mb_idx, int $order_amount): array
{
    if ($mb_idx < 1 || $order_amount < 1 || !member_coupon_table_ready()) {
        return [];
    }

    $today = db_escape(date('Y-m-d'));
    $rs    = db_query("
        SELECT mc.*, cp.cp_status
        FROM tb_member_coupon mc
        LEFT JOIN tb_coupon cp ON cp.cp_idx = mc.cp_idx
        WHERE mc.mb_idx = {$mb_idx}
          AND mc.mc_status = " . MEMBER_COUPON_STATUS_AVAILABLE . "
          AND mc.mc_valid_from <= '{$today}'
          AND mc.mc_valid_until >= '{$today}'
          AND IFNULL(cp.cp_status, 1) = 1
        ORDER BY mc.mc_valid_until ASC, mc.mc_idx DESC
    ");

    $out = [];
    while ($r = db_assoc($rs)) {
        $calc = coupon_calc_amounts(
            (int) ($r['mc_discount_type'] ?? 0),
            (int) ($r['mc_discount_value'] ?? 0),
            $order_amount
        );
        if ($calc['discount'] < 1) {
            continue;
        }
        $r['preview_discount']   = (int) $calc['discount'];
        $r['preview_pay_amount'] = (int) $calc['pay_amount'];
        $r['discount_label']     = coupon_format_discount($r);
        $out[]                   = $r;
    }

    return $out;
}

/** @return array<string, string> */
function coupon_member_status_labels(): array
{
    return [
        'available' => '사용 가능',
        'used'      => '사용 완료',
        'expired'   => '기간 만료',
        'waiting'   => '사용 대기',
        'cancelled' => '취소',
    ];
}

/**
 * @param array<string, mixed> $row
 */
function coupon_member_row_status(array $row): string
{
    $mc_st = (int) ($row['mc_status'] ?? 0);
    if ($mc_st === MEMBER_COUPON_STATUS_USED) {
        return 'used';
    }
    if ($mc_st === MEMBER_COUPON_STATUS_CANCELLED) {
        return 'cancelled';
    }

    $today = date('Y-m-d');
    $from  = (string) ($row['mc_valid_from'] ?? '');
    $until = (string) ($row['mc_valid_until'] ?? '');
    if ($from !== '' && $today < $from) {
        return 'waiting';
    }
    if ($until !== '' && $today > $until) {
        return 'expired';
    }
    if ((int) ($row['cp_status'] ?? 1) === 9) {
        return 'cancelled';
    }

    return 'available';
}

function coupon_member_status_label(string $status): string
{
    $labels = coupon_member_status_labels();

    return $labels[$status] ?? $status;
}

/**
 * @return array{rows: array<int, array<string, mixed>>, total: int}
 */
function coupon_list_for_member(int $mb_idx, string $filter = 'available', int $limit = 50, int $offset = 0): array
{
    if ($mb_idx < 1 || !member_coupon_table_ready()) {
        return ['rows' => [], 'total' => 0];
    }

    $filter = in_array($filter, ['available', 'used', 'expired', 'all'], true) ? $filter : 'available';
    $today  = db_escape(date('Y-m-d'));
    $where  = ["mc.mb_idx = {$mb_idx}"];

    switch ($filter) {
        case 'used':
            $where[] = 'mc.mc_status = ' . MEMBER_COUPON_STATUS_USED;
            break;
        case 'expired':
            $where[] = 'mc.mc_status = ' . MEMBER_COUPON_STATUS_AVAILABLE;
            $where[] = "mc.mc_valid_until < '{$today}'";
            break;
        case 'available':
            $where[] = 'mc.mc_status = ' . MEMBER_COUPON_STATUS_AVAILABLE;
            $where[] = "mc.mc_valid_from <= '{$today}'";
            $where[] = "mc.mc_valid_until >= '{$today}'";
            $where[] = 'IFNULL(cp.cp_status, 1) = 1';
            break;
    }

    $where_sql = implode(' AND ', $where);
    $total     = (int) db_result("
        SELECT COUNT(*)
        FROM tb_member_coupon mc
        LEFT JOIN tb_coupon cp ON cp.cp_idx = mc.cp_idx
        WHERE {$where_sql}
    ");

    $rs = db_query("
        SELECT mc.*, cp.cp_status
        FROM tb_member_coupon mc
        LEFT JOIN tb_coupon cp ON cp.cp_idx = mc.cp_idx
        WHERE {$where_sql}
        ORDER BY mc.mc_valid_until ASC, mc.mc_idx DESC
        LIMIT {$offset}, {$limit}
    ");

    $rows = [];
    while ($r = db_assoc($rs)) {
        $r['discount_label'] = coupon_format_discount($r);
        $r['status_key']     = coupon_member_row_status($r);
        $r['status_label']   = coupon_member_status_label($r['status_key']);
        $rows[]              = $r;
    }

    return ['rows' => $rows, 'total' => $total];
}

function coupon_count_available_for_member(int $mb_idx): int
{
    if ($mb_idx < 1 || !member_coupon_table_ready()) {
        return 0;
    }

    $today = db_escape(date('Y-m-d'));

    return (int) db_result("
        SELECT COUNT(*)
        FROM tb_member_coupon mc
        LEFT JOIN tb_coupon cp ON cp.cp_idx = mc.cp_idx
        WHERE mc.mb_idx = {$mb_idx}
          AND mc.mc_status = " . MEMBER_COUPON_STATUS_AVAILABLE . "
          AND mc.mc_valid_from <= '{$today}'
          AND mc.mc_valid_until >= '{$today}'
          AND IFNULL(cp.cp_status, 1) = 1
    ");
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error?: string, cp_idx?: int, issued?: int}
 */
function coupon_admin_issue(array $input): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if (!coupon_table_ready() || !member_coupon_table_ready()) {
        return $fail('쿠폰 DB가 없습니다. sql/migrate_tb_coupon.sql 을 적용해 주세요.');
    }

    $name   = trim((string) ($input['cp_name'] ?? ''));
    $type   = (int) ($input['cp_discount_type'] ?? 0);
    $value  = (int) ($input['cp_discount_value'] ?? 0);
    $from   = trim((string) ($input['cp_valid_from'] ?? ''));
    $until  = trim((string) ($input['cp_valid_until'] ?? ''));
    $target = (int) ($input['cp_issue_target'] ?? 0);
    $mb_idx = (int) ($input['mb_idx'] ?? 0);

    if ($name === '' || mb_strlen($name) > 100) {
        return $fail('쿠폰명을 1~100자 이내로 입력해 주세요.');
    }
    if (!in_array($type, [COUPON_DISCOUNT_FIXED, COUPON_DISCOUNT_PERCENT], true)) {
        return $fail('할인 방식을 선택해 주세요.');
    }
    if ($type === COUPON_DISCOUNT_PERCENT) {
        if ($value < 1 || $value > 100) {
            return $fail('정률 할인은 1~100% 사이로 입력해 주세요.');
        }
    } elseif ($value < 1) {
        return $fail('할인 금액을 입력해 주세요.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
        return $fail('사용 기간 날짜 형식이 올바르지 않습니다.');
    }
    if ($from > $until) {
        return $fail('시작일이 종료일보다 늦을 수 없습니다.');
    }
    if (!in_array($target, [COUPON_ISSUE_ALL, COUPON_ISSUE_MEMBER], true)) {
        return $fail('발행 대상을 선택해 주세요.');
    }

    $member_ids = [];
    if ($target === COUPON_ISSUE_ALL) {
        $rs = db_query("SELECT mb_idx FROM tb_member WHERE mb_status = 1 ORDER BY mb_idx ASC");
        while ($r = db_assoc($rs)) {
            $id = (int) ($r['mb_idx'] ?? 0);
            if ($id > 0) {
                $member_ids[] = $id;
            }
        }
        if ($member_ids === []) {
            return $fail('발행할 활성 회원이 없습니다.');
        }
    } else {
        if ($mb_idx < 1) {
            return $fail('발행할 회원을 선택해 주세요.');
        }
        $mbr = db_assoc(db_query("SELECT mb_idx FROM tb_member WHERE mb_idx = {$mb_idx} AND mb_status = 1 LIMIT 1"));
        if (!$mbr) {
            return $fail('활성 회원을 찾을 수 없습니다.');
        }
        $member_ids[] = $mb_idx;
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('발행 처리를 시작하지 못했습니다.');
    }

    $esc_name = db_escape($name);
    $ok       = db_query("
        INSERT INTO tb_coupon (
            cp_name, cp_discount_type, cp_discount_value,
            cp_valid_from, cp_valid_until, cp_issue_target,
            cp_status, cp_issued_count, cp_created_at
        ) VALUES (
            '{$esc_name}', {$type}, {$value},
            '" . db_escape($from) . "', '" . db_escape($until) . "', {$target},
            1, 0, NOW()
        )
    ");
    if (!$ok) {
        mysqli_rollback($conn);

        return $fail('쿠폰 저장에 실패했습니다.');
    }

    $cp_idx   = (int) db_insert_id();
    $issued   = 0;
    $esc_from = db_escape($from);
    $esc_until = db_escape($until);

    foreach ($member_ids as $mid) {
        $ins = db_query("
            INSERT INTO tb_member_coupon (
                cp_idx, mb_idx, mc_name, mc_discount_type, mc_discount_value,
                mc_valid_from, mc_valid_until, mc_status, mc_issued_at
            ) VALUES (
                {$cp_idx}, {$mid}, '{$esc_name}', {$type}, {$value},
                '{$esc_from}', '{$esc_until}', " . MEMBER_COUPON_STATUS_AVAILABLE . ", NOW()
            )
        ");
        if ($ins) {
            $issued++;
        }
    }

    if ($issued < 1) {
        mysqli_rollback($conn);

        return $fail('회원 쿠폰 발행에 실패했습니다.');
    }

    db_query("UPDATE tb_coupon SET cp_issued_count = {$issued} WHERE cp_idx = {$cp_idx} LIMIT 1");
    mysqli_commit($conn);

    return [
        'ok'     => true,
        'cp_idx' => $cp_idx,
        'issued' => $issued,
    ];
}

function coupon_attach_to_payment(int $pay_idx, int $mc_idx, int $original_amount, int $discount_amount, int $pay_amount): bool
{
    if ($pay_idx < 1 || !trade_payment_coupon_column_ready()) {
        return false;
    }

    $mc_sql = $mc_idx > 0 ? (string) $mc_idx : 'NULL';

    return (bool) db_query("
        UPDATE tb_trade_payment
        SET pay_amount = {$pay_amount},
            pay_original_amount = {$original_amount},
            pay_discount_amount = {$discount_amount},
            pay_member_coupon_idx = {$mc_sql}
        WHERE pay_idx = {$pay_idx}
        LIMIT 1
    ");
}

function coupon_mark_used(int $mc_idx, int $pay_idx): bool
{
    if ($mc_idx < 1 || $pay_idx < 1 || !member_coupon_table_ready()) {
        return false;
    }

    global $conn;
    $ok = db_query("
        UPDATE tb_member_coupon
        SET mc_status = " . MEMBER_COUPON_STATUS_USED . ",
            mc_pay_idx = {$pay_idx},
            mc_used_at = NOW()
        WHERE mc_idx = {$mc_idx}
          AND mc_status = " . MEMBER_COUPON_STATUS_AVAILABLE . "
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        return false;
    }

    if (coupon_table_ready()) {
        $cp_idx = (int) db_result("SELECT cp_idx FROM tb_member_coupon WHERE mc_idx = {$mc_idx} LIMIT 1");
        if ($cp_idx > 0) {
            db_query("UPDATE tb_coupon SET cp_used_count = cp_used_count + 1 WHERE cp_idx = {$cp_idx} LIMIT 1");
        }
    }

    return true;
}

/**
 * 결제 취소·판매취소 시 사용 쿠폰을 사용가능 상태로 되돌림.
 *
 * @return bool 쿠폰 없음·복원 성공 true, 복원 대상인데 실패 시 false
 */
function coupon_restore_for_payment(int $pay_idx): bool
{
    if ($pay_idx < 1 || !member_coupon_table_ready() || !trade_payment_coupon_column_ready()) {
        return true;
    }

    $row = db_assoc(db_query("
        SELECT pay_member_coupon_idx
        FROM tb_trade_payment
        WHERE pay_idx = {$pay_idx}
        LIMIT 1
    "));
    $mc_idx = (int) ($row['pay_member_coupon_idx'] ?? 0);
    if ($mc_idx < 1) {
        return true;
    }

    global $conn;
    $ok = db_query("
        UPDATE tb_member_coupon
        SET mc_status = " . MEMBER_COUPON_STATUS_AVAILABLE . ",
            mc_pay_idx = NULL,
            mc_used_at = NULL
        WHERE mc_idx = {$mc_idx}
          AND mc_status = " . MEMBER_COUPON_STATUS_USED . "
          AND mc_pay_idx = {$pay_idx}
        LIMIT 1
    ");
    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        return false;
    }

    if (coupon_table_ready()) {
        $cp_idx = (int) db_result("SELECT cp_idx FROM tb_member_coupon WHERE mc_idx = {$mc_idx} LIMIT 1");
        if ($cp_idx > 0) {
            db_query("
                UPDATE tb_coupon
                SET cp_used_count = GREATEST(0, cp_used_count - 1)
                WHERE cp_idx = {$cp_idx}
                  AND cp_used_count > 0
                LIMIT 1
            ");
        }
    }

    return true;
}
