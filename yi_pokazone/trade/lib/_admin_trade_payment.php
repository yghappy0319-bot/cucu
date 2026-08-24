<?php

require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/_trade_payment.php';

/** @return array<string, string> */
function admin_tp_pay_status_filter_options(): array
{
    return [
        ''          => '결제상태 전체',
        'pending'   => '결제대기',
        'submitted' => '입금확인중',
        'confirmed' => '입금확인완료',
        'cancelled' => '결제요청취소',
        'sale_cancel' => '판매취소(환불)',
    ];
}

/** @return array<string, string> */
function admin_tp_fulfill_filter_options(): array
{
    return [
        ''        => '진행상태 전체',
        'wait'    => '발송대기',
        'sent'    => '발송완료',
        'ship'    => '수령확인',
        'purchase' => '구매확정',
    ];
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_settle_state_label(array $row): string
{
    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_sale_cancelled_status($status)) {
        return '판매취소(환불)';
    }
    if (trade_payment_is_cancelled_status($status)) {
        return '결제요청취소';
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return '정산완료';
    }
    if (!trade_payment_is_confirmed_status($status)) {
        return trade_payment_status_label($status);
    }
    if ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING) {
        return '구매확정대기';
    }

    return trade_payment_fulfill_label($row);
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_settle_state_class(array $row): string
{
    $status = (int) ($row['pay_status'] ?? 0);
    if (trade_payment_is_sale_cancelled_status($status) || trade_payment_is_cancelled_status($status)) {
        return 'ad-badge--muted';
    }

    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE) {
        return 'ad-badge--ok';
    }
    if (trade_payment_is_confirmed_status($status)) {
        return 'ad-badge--warn';
    }

    return 'ad-badge--bad';
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_fee_amount(array $row): int
{
    if ((int) ($row['tpf_fee_amount'] ?? 0) > 0) {
        return (int) $row['tpf_fee_amount'];
    }

    return (int) ($row['pay_platform_fee'] ?? 0);
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_seller_settle_amount(array $row): int
{
    if ((int) ($row['tpf_seller_amount'] ?? 0) > 0) {
        return (int) $row['tpf_seller_amount'];
    }

    return (int) ($row['pay_seller_settle_amount'] ?? 0);
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_fee_rate(array $row): int
{
    if (isset($row['tpf_fee_rate']) && (int) $row['tpf_fee_rate'] > 0) {
        return (int) $row['tpf_fee_rate'];
    }

    return function_exists('platform_fee_trade_percent')
        ? platform_fee_trade_percent()
        : (int) TRADE_PLATFORM_FEE_PERCENT;
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_is_settled(array $row): bool
{
    return trade_payment_fulfill_status_from_row($row) >= TRADE_PAYMENT_FULFILL_PURCHASE;
}

/** @return array<string, string> */
function admin_tp_fee_filter_options(): array
{
    return [
        ''       => '확정방식 전체',
        'manual' => '구매자 구매확정',
        'auto'   => '자동 구매확정',
    ];
}

/**
 * @return array{select: string, group: string, order: string, label: string}
 */
function admin_tp_fee_period_group_sql(string $view): array
{
    if ($view === 'month') {
        return [
            'select' => "DATE_FORMAT(f.tpf_created_at, '%Y-%m') AS period_key",
            'group'  => "DATE_FORMAT(f.tpf_created_at, '%Y-%m')",
            'order'  => 'period_key DESC',
            'label'  => '월',
        ];
    }

    return [
        'select' => 'DATE(f.tpf_created_at) AS period_key',
        'group'  => 'DATE(f.tpf_created_at)',
        'order'  => 'period_key DESC',
        'label'  => '일',
    ];
}

/**
 * @return array{ok: bool, from?: string, to?: string, error?: string}
 */
function admin_tp_fee_parse_date_range(string $from_raw, string $to_raw): array
{
    $from = trim($from_raw);
    $to   = trim($to_raw);
    if ($from === '' && $to === '') {
        return ['ok' => true, 'from' => '', 'to' => ''];
    }
    if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        return ['ok' => false, 'error' => '시작일 형식이 올바르지 않습니다. (YYYY-MM-DD)'];
    }
    if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return ['ok' => false, 'error' => '종료일 형식이 올바르지 않습니다. (YYYY-MM-DD)'];
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        return ['ok' => false, 'error' => '시작일이 종료일보다 늦을 수 없습니다.'];
    }

    return ['ok' => true, 'from' => $from, 'to' => $to];
}

/**
 * @return string[]
 */
function admin_tp_fee_date_where_clauses(string $from, string $to, string $column = 'f.tpf_created_at'): array
{
    $where = [];
    if ($from !== '') {
        $where[] = $column . " >= '" . db_escape($from) . " 00:00:00'";
    }
    if ($to !== '') {
        $where[] = $column . " <= '" . db_escape($to) . " 23:59:59'";
    }

    return $where;
}

/**
 * @param array<string, mixed> $row
 */
function admin_tp_fee_period_detail_url(string $view, array $row): string
{
    $key = (string) ($row['period_key'] ?? '');
    if ($key === '') {
        return '/admin/trade_fees.php';
    }
    if ($view === 'month' && preg_match('/^\d{4}-\d{2}$/', $key)) {
        $from = $key . '-01';
        $ts   = strtotime($from);
        $to   = $ts !== false ? date('Y-m-t', $ts) : $from;

        return '/admin/trade_fees.php?from=' . rawurlencode($from) . '&to=' . rawurlencode($to);
    }
    if ($view === 'day') {
        return '/admin/trade_fees.php?from=' . rawurlencode($key) . '&to=' . rawurlencode($key);
    }

    return '/admin/trade_fees.php';
}

/**
 * 결제와 연결된 채팅 메시지 msg_idx 수집
 *
 * @param array<string, mixed> $row
 * @return int[]
 */
function admin_tp_collect_payment_message_ids(array $row): array
{
    $pay_idx  = (int) ($row['pay_idx'] ?? 0);
    $room_idx = (int) ($row['room_idx'] ?? 0);
    if ($pay_idx < 1 || $room_idx < 1 || !db_table_exists('tb_trade_room_msg')) {
        return [];
    }

    $ids = [];
    $add = static function (int $msg_idx) use (&$ids): void {
        if ($msg_idx > 0) {
            $ids[$msg_idx] = true;
        }
    };

    $add((int) ($row['request_msg_idx'] ?? 0));
    if (trade_payment_pay_column_exists('pay_addr_msg_idx')) {
        $add((int) ($row['pay_addr_msg_idx'] ?? 0));
    }

    if (trade_payment_column_exists('msg_pay_idx')) {
        $rs = db_query("
            SELECT msg_idx
            FROM tb_trade_room_msg
            WHERE room_idx = {$room_idx}
              AND msg_pay_idx = {$pay_idx}
        ");
        while ($r = db_assoc($rs)) {
            $add((int) ($r['msg_idx'] ?? 0));
        }
    }

    $patterns = [
        '[PZ_PAY_DONE:' . $pay_idx . ':%',
        '[PZ_PAY_TRACK:' . $pay_idx . ']%',
        '[PZ_PAY_DELIVERED:' . $pay_idx . ']%',
        '[PZ_PAY_BUYCONF:' . $pay_idx . ']%',
        '[PZ_PAY_SHIP:' . $pay_idx . ']%',
        '[PZ_PAY_ADDR:' . $pay_idx . ':%',
        '[PZ_PAY_SALECANCEL:' . $pay_idx . ':%',
        '[PZ_PAY:' . '%:' . $pay_idx . ']%',
    ];
    foreach ($patterns as $like) {
        $esc = db_escape($like);
        $rs  = db_query("
            SELECT msg_idx
            FROM tb_trade_room_msg
            WHERE room_idx = {$room_idx}
              AND msg_body LIKE '{$esc}'
        ");
        while ($r = db_assoc($rs)) {
            $add((int) ($r['msg_idx'] ?? 0));
        }
    }

    $req_msg = (int) ($row['request_msg_idx'] ?? 0);
    if ($req_msg > 0) {
        $esc = db_escape('[PZ_PAY_ACK:%:%:' . $req_msg . ']%');
        $rs  = db_query("
            SELECT msg_idx
            FROM tb_trade_room_msg
            WHERE room_idx = {$room_idx}
              AND msg_body LIKE '{$esc}'
        ");
        while ($r = db_assoc($rs)) {
            $add((int) ($r['msg_idx'] ?? 0));
        }
    }

    return array_map('intval', array_keys($ids));
}

/**
 * 관리자 — 결제 및 연관 데이터 삭제
 *
 * @return array{ok: bool, error?: string, pay_idx?: int, messages?: int, cash_reversed?: bool}
 */
function admin_tp_delete_payment(int $pay_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($pay_idx < 1 || !trade_payment_table_ready()) {
        return $fail('잘못된 요청입니다.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }

    require_once __DIR__ . '/../../lib/_member_cash.php';

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return $fail('삭제 처리를 시작하지 못했습니다.');
    }

    $label     = (string) ($row['product_label'] ?? '상품');
    $buyer_mb  = (int) ($row['buyer_mb_idx'] ?? 0);
    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $room_idx  = (int) ($row['room_idx'] ?? 0);
    $status    = (int) ($row['pay_status'] ?? 0);
    $fulfill   = trade_payment_fulfill_status_from_row($row);
    $cash_reversed = false;

    $reverse_cash = static function (int $mb_idx, int $amount, string $memo) use (&$cash_reversed): bool {
        if ($mb_idx < 1 || $amount < 1 || !member_cash_column_ready()) {
            return true;
        }
        global $conn;
        $ok = db_query("
            UPDATE tb_member
            SET mb_cash = mb_cash - {$amount}
            WHERE mb_idx = {$mb_idx}
              AND mb_cash >= {$amount}
            LIMIT 1
        ");
        if (!$ok || mysqli_affected_rows($conn) !== 1) {
            return false;
        }
        if (member_cash_log_table_ready()) {
            $bal = member_cash_balance($mb_idx);
            member_cash_log_insert($mb_idx, -$amount, $bal, 'admin_adjust', $memo);
        }
        $cash_reversed = true;

        return true;
    };

    if ($fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE && $seller_mb > 0) {
        $settle_amt = admin_tp_seller_settle_amount($row);
        if ($settle_amt < 1 && trade_payment_fee_table_ready()) {
            $fee_row = db_assoc(db_query("
                SELECT tpf_seller_amount
                FROM tb_trade_payment_fee
                WHERE pay_idx = {$pay_idx}
                LIMIT 1
            "));
            $settle_amt = (int) ($fee_row['tpf_seller_amount'] ?? 0);
        }
        if ($settle_amt > 0) {
            $memo = '관리자 결제 삭제 — 판매 정산 회수: ' . $label . ' (pay#' . $pay_idx . ')';
            if (!$reverse_cash($seller_mb, $settle_amt, $memo)) {
                mysqli_rollback($conn);

                return $fail('판매자 캐시 회수에 실패했습니다. 잔액이 부족할 수 있습니다.');
            }
        }
    } elseif (trade_payment_is_sale_cancelled_status($status) && $buyer_mb > 0) {
        $refund_amt = (int) ($row['pay_refund_amount'] ?? $row['pay_amount'] ?? 0);
        if ($refund_amt > 0) {
            $memo = '관리자 결제 삭제 — 판매취소 환불 회수: ' . $label . ' (pay#' . $pay_idx . ')';
            if (!$reverse_cash($buyer_mb, $refund_amt, $memo)) {
                mysqli_rollback($conn);

                return $fail('구매자 캐시 환불 회수에 실패했습니다. 잔액이 부족할 수 있습니다.');
            }
        }
    }

    if (trade_payment_fee_table_ready()) {
        db_query("DELETE FROM tb_trade_payment_fee WHERE pay_idx = {$pay_idx}");
    }

    if (db_table_exists('tb_cash_withdraw_fee')) {
        db_query("DELETE FROM tb_cash_withdraw_fee WHERE pay_idx = {$pay_idx}");
    }

    if (member_cash_log_table_ready()) {
        $like = db_escape('%pay#' . $pay_idx . '%');
        db_query("DELETE FROM tb_cash_log WHERE cl_memo LIKE '{$like}'");
    }

    $msg_ids = admin_tp_collect_payment_message_ids($row);
    $msg_cnt = count($msg_ids);
    if ($msg_ids !== []) {
        $id_sql = implode(',', array_map('intval', $msg_ids));
        db_query("DELETE FROM tb_trade_room_msg WHERE msg_idx IN ({$id_sql})");
    }

    if (!db_query("DELETE FROM tb_trade_payment WHERE pay_idx = {$pay_idx} LIMIT 1")) {
        mysqli_rollback($conn);

        return $fail('결제 삭제에 실패했습니다.');
    }

    if (mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return $fail('결제 삭제에 실패했습니다.');
    }

    if ($room_idx > 0) {
        db_query("UPDATE tb_trade_room SET room_updated_at = NOW() WHERE room_idx = {$room_idx} LIMIT 1");
    }

    mysqli_commit($conn);

    return [
        'ok'            => true,
        'pay_idx'       => $pay_idx,
        'messages'      => $msg_cnt,
        'cash_reversed' => $cash_reversed,
    ];
}
