<?php

/** 입금 확인 후 판매 취소 3회 이상 시 1일 판매(등록) 정지 */
const MEMBER_TRADE_SALE_CANCEL_PENALTY_THRESHOLD = 3;
const MEMBER_TRADE_SELL_SUSPEND_HOURS = 24;

function member_trade_sale_cancel_penalty_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_member')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_trade_sale_cancel_count'"));

    return $ready;
}

/**
 * @return array{count: int, is_suspended: bool, suspended_until: string|null, memo: string|null}
 */
function member_trade_sale_cancel_penalty_status(int $mb_idx): array
{
    $empty = [
        'count'            => 0,
        'is_suspended'     => false,
        'suspended_until'  => null,
        'memo'             => null,
    ];
    if (!member_trade_sale_cancel_penalty_column_ready() || $mb_idx < 1) {
        return $empty;
    }

    $row = db_assoc(db_query("
        SELECT mb_trade_sale_cancel_count, mb_trade_sell_suspended_until, mb_trade_sell_suspend_memo
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return $empty;
    }

    $until = trim((string) ($row['mb_trade_sell_suspended_until'] ?? ''));
    $is_suspended = $until !== '' && strtotime($until) > time();

    return [
        'count'           => max(0, (int) ($row['mb_trade_sale_cancel_count'] ?? 0)),
        'is_suspended'    => $is_suspended,
        'suspended_until' => $is_suspended ? $until : null,
        'memo'            => trim((string) ($row['mb_trade_sell_suspend_memo'] ?? '')) ?: null,
    ];
}

function member_trade_sell_is_suspended(int $mb_idx): bool
{
    return member_trade_sale_cancel_penalty_status($mb_idx)['is_suspended'];
}

function member_trade_sell_suspend_user_message(?string $suspended_until = null): string
{
    $base = '입금 확인 후 판매 취소가 누적되어 거래 판매글 등록이 일시 정지되었습니다.';
    if ($suspended_until !== null && trim($suspended_until) !== '') {
        $ts = strtotime($suspended_until);
        if ($ts !== false) {
            return $base . ' (' . date('Y-m-d H:i', $ts) . ' 이후 등록 가능)';
        }
    }

    return $base . ' 문의는 고객센터로 연락해 주세요.';
}

/**
 * 판매 취소 성공 후 — 누적 횟수 증가 · 3회 이상 시 1일 판매 등록 정지
 *
 * @return array{ok: bool, error?: string, count?: int, penalty_applied?: bool, suspended_until?: string|null}
 */
function member_trade_sale_cancel_penalty_apply(int $seller_mb_idx, int $pay_idx): array
{
    if (!member_trade_sale_cancel_penalty_column_ready()) {
        return ['ok' => true, 'skipped' => true];
    }
    if ($seller_mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }

    $status = member_trade_sale_cancel_penalty_status($seller_mb_idx);
    $count  = $status['count'] + 1;
    $sets   = ['mb_trade_sale_cancel_count = ' . $count, 'mb_updated_at = NOW()'];
    $penalty_applied = false;
    $suspended_until = null;

    if ($count >= MEMBER_TRADE_SALE_CANCEL_PENALTY_THRESHOLD) {
        $penalty_applied = true;
        $memo = '입금 확인 후 판매 취소 ' . $count . '회 (pay#' . max(0, $pay_idx) . ')';
        if (mb_strlen($memo) > 200) {
            $memo = mb_substr($memo, 0, 200);
        }
        $esc_memo = db_escape($memo);
        $hours    = (int) MEMBER_TRADE_SELL_SUSPEND_HOURS;
        $sets[]   = "mb_trade_sell_suspended_until = GREATEST(COALESCE(mb_trade_sell_suspended_until, NOW()), DATE_ADD(NOW(), INTERVAL {$hours} HOUR))";
        $sets[]   = "mb_trade_sell_suspend_memo = '{$esc_memo}'";
    }

    $ok = db_query('
        UPDATE tb_member SET ' . implode(', ', $sets) . "
        WHERE mb_idx = {$seller_mb_idx}
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '판매 취소 패널티 처리에 실패했습니다.'];
    }

    if ($penalty_applied) {
        $after = member_trade_sale_cancel_penalty_status($seller_mb_idx);
        $suspended_until = $after['suspended_until'];
    }

    return [
        'ok'              => true,
        'count'           => $count,
        'penalty_applied' => $penalty_applied,
        'suspended_until' => $suspended_until,
    ];
}
