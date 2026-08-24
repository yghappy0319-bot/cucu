<?php
/**
 * 판매내역(내 거래글) · 끌올
 */

function trade_bump_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_bumped_at'"));

    return $ready;
}

function trade_bump_point_cost(): int
{
    return 50;
}

function trade_seller_listing_order_sql(string $alias = 't'): string
{
    $a = preg_replace('/[^a-z_]/', '', $alias) ?: 't';
    if (trade_bump_column_ready()) {
        return "COALESCE({$a}.tr_bumped_at, {$a}.tr_created_at) DESC, {$a}.tr_idx DESC";
    }

    return "{$a}.tr_idx DESC";
}

function trade_seller_listing_tab_normalize(string $tab): string
{
    if ($tab === 'done') {
        return 'done';
    }
    if ($tab === 'all') {
        return 'all';
    }

    return 'active';
}

function trade_seller_listing_deal_sql(string $tab, string $alias = ''): string
{
    $tab = trade_seller_listing_tab_normalize($tab);
    $a   = preg_replace('/[^a-z_]/', '', $alias);
    $col = ($a !== '' ? "{$a}." : '') . 'tr_deal_status';
    if ($tab === 'all') {
        return '';
    }
    if ($tab === 'done') {
        return " AND {$col} = 3";
    }

    return " AND {$col} <> 3";
}

function trade_seller_listing_count(int $mb_idx, string $tab = 'active'): int
{
    $mb_idx = max(0, $mb_idx);
    $tab    = trade_seller_listing_tab_normalize($tab);
    if ($mb_idx < 1 || !db_table_exists('tb_trade')) {
        return 0;
    }

    $deal_sql = trade_seller_listing_deal_sql($tab);

    return (int) db_result("SELECT COUNT(*) FROM tb_trade WHERE mb_idx = {$mb_idx} AND " . trade_status_public_sql('') . "{$deal_sql}");
}

/**
 * @return array<int, array<string, mixed>>
 */
function trade_seller_listing_rows(int $mb_idx, int $limit = 15, int $offset = 0, string $tab = 'active'): array
{
    $mb_idx = max(0, $mb_idx);
    $tab    = trade_seller_listing_tab_normalize($tab);
    $limit  = max(1, min(100, $limit));
    $offset = max(0, $offset);
    if ($mb_idx < 1 || !db_table_exists('tb_trade')) {
        return [];
    }

    $order    = trade_seller_listing_order_sql('t');
    $deal_sql = trade_seller_listing_deal_sql($tab, 't');
    $thumb = db_table_exists('tb_trade_image')
        ? ', (SELECT i.ti_path FROM tb_trade_image i WHERE i.tr_idx = t.tr_idx ORDER BY i.ti_order ASC, i.ti_idx ASC LIMIT 1) AS thumb_path'
        : ', NULL AS thumb_path';

    $rs = db_query("
        SELECT t.*{$thumb}
        FROM tb_trade t
        WHERE t.mb_idx = {$mb_idx} AND " . trade_status_public_sql('t') . "{$deal_sql}
        ORDER BY {$order}
        LIMIT {$offset}, {$limit}
    ");

    $out = [];
    while ($row = db_assoc($rs)) {
        $out[] = $row;
    }

    return $out;
}

function trade_post_was_bumped_today(array $row): bool
{
    $at = trim((string) ($row['tr_bumped_at'] ?? ''));
    if ($at === '') {
        return false;
    }
    $ts = strtotime($at);

    return $ts !== false && date('Y-m-d', $ts) === date('Y-m-d');
}

/**
 * @return array{ok: bool, error?: string, tr_idx?: int, point_balance?: int}
 */
function trade_post_bump(int $mb_idx, int $tr_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mb_idx < 1 || $tr_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!trade_bump_column_ready()) {
        return $fail('끌올 기능 DB가 없습니다. sql/migrate_tb_trade_bump.sql 을 적용해 주세요.');
    }
    if (!db_table_exists('tb_point_log')) {
        return $fail('포인트 내역 DB가 없습니다.');
    }

    $trade = db_assoc(db_query("
        SELECT tr_idx, mb_idx, tr_title, tr_deal_status, tr_status, tr_bumped_at
        FROM tb_trade
        WHERE tr_idx = {$tr_idx} AND mb_idx = {$mb_idx} AND tr_status = 1
        LIMIT 1
    "));
    if (!$trade) {
        return $fail('거래글을 찾을 수 없습니다.');
    }
    if ((int) ($trade['tr_deal_status'] ?? 0) === 3) {
        return $fail('거래완료된 글은 끌올할 수 없습니다.');
    }
    if (trade_post_was_bumped_today($trade)) {
        return $fail('이 글은 오늘 이미 끌올했습니다. 내일 다시 시도해 주세요.');
    }

    $cost = trade_bump_point_cost();
    $balance = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
    if ($balance < $cost) {
        return $fail('포인트가 부족합니다. (필요: ' . number_format($cost) . 'P, 보유: ' . number_format($balance) . 'P)');
    }

    $title = trim((string) ($trade['tr_title'] ?? ''));
    if ($title === '') {
        $title = '거래글';
    }
    $memo = '거래글 끌올 · ' . mb_substr($title, 0, 40, 'UTF-8') . ' (#' . $tr_idx . ')';

    global $conn;
    mysqli_begin_transaction($conn);

    try {
        $delta    = -$cost;
        $esc_type = db_escape('trade_bump');
        $esc_memo = db_escape($memo);

        if (!db_query("
            UPDATE tb_member
            SET mb_point = mb_point + ({$delta})
            WHERE mb_idx = {$mb_idx}
              AND mb_point >= {$cost}
            LIMIT 1
        ") || mysqli_affected_rows($conn) !== 1) {
            throw new RuntimeException('포인트 차감에 실패했습니다.');
        }

        $new_balance = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
        if (!db_query("
            INSERT INTO tb_point_log (mb_idx, pl_change, pl_balance, pl_type, pl_memo)
            VALUES ({$mb_idx}, {$delta}, {$new_balance}, '{$esc_type}', '{$esc_memo}')
        ")) {
            throw new RuntimeException('포인트 내역 저장에 실패했습니다.');
        }

        if (!db_query("UPDATE tb_trade SET tr_bumped_at = NOW(), tr_updated_at = NOW() WHERE tr_idx = {$tr_idx} AND mb_idx = {$mb_idx} LIMIT 1")) {
            throw new RuntimeException('끌올 처리에 실패했습니다.');
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);

        return $fail($e->getMessage());
    }

    return [
        'ok'            => true,
        'tr_idx'        => $tr_idx,
        'point_balance' => $new_balance,
    ];
}

/**
 * @return array{can_bump: bool, reason: string}
 */
function trade_post_bump_ui_state(array $row): array
{
    if (function_exists('trade_is_held') && trade_is_held($row)) {
        return ['can_bump' => false, 'reason' => '게시중지'];
    }
    if ((int) ($row['tr_deal_status'] ?? 0) === 3) {
        return ['can_bump' => false, 'reason' => '거래완료'];
    }
    if (!trade_bump_column_ready()) {
        return ['can_bump' => false, 'reason' => '준비중'];
    }
    if (trade_post_was_bumped_today($row)) {
        return ['can_bump' => false, 'reason' => '오늘 끌올 완료'];
    }

    return ['can_bump' => true, 'reason' => ''];
}