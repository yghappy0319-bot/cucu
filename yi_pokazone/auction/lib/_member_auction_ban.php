<?php

function member_auction_ban_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_member')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_auction_banned'"));

    return $ready;
}

function member_auction_is_banned(int $mb_idx): bool
{
    if (!member_auction_ban_column_ready() || $mb_idx < 1) {
        return false;
    }

    return (int) db_result(
        "SELECT mb_auction_banned FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"
    ) === 1;
}

/**
 * @return array{banned: bool, banned_at: ?string, memo: string}
 */
function member_auction_ban_info(int $mb_idx): array
{
    $empty = ['banned' => false, 'banned_at' => null, 'memo' => ''];
    if (!member_auction_ban_column_ready() || $mb_idx < 1) {
        return $empty;
    }

    $row = db_assoc(db_query("
        SELECT mb_auction_banned, mb_auction_banned_at, mb_auction_ban_memo
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return $empty;
    }

    return [
        'banned'    => (int) ($row['mb_auction_banned'] ?? 0) === 1,
        'banned_at' => !empty($row['mb_auction_banned_at']) ? (string) $row['mb_auction_banned_at'] : null,
        'memo'      => (string) ($row['mb_auction_ban_memo'] ?? ''),
    ];
}

function member_auction_ban_user_message(): string
{
    return '경매 이용이 제한된 계정입니다. 낙찰 주문 취소 등으로 이용이 정지되었을 수 있습니다. 문의는 고객센터로 연락해 주세요.';
}

/**
 * @return array{ok: bool, error?: string}
 */
function member_auction_ban_apply(int $mb_idx, string $memo): array
{
    if (!member_auction_ban_column_ready()) {
        return ['ok' => false, 'error' => '경매 이용 제한 DB가 없습니다. sql/migrate_tb_member_auction_ban.sql 을 적용해 주세요.'];
    }
    if ($mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }

    $memo = trim($memo);
    if ($memo === '') {
        $memo = '경매 이용 제한';
    }
    if (mb_strlen($memo) > 200) {
        $memo = mb_substr($memo, 0, 200);
    }
    $esc_memo = db_escape($memo);

    $ok = db_query("
        UPDATE tb_member SET
            mb_auction_banned = 1,
            mb_auction_banned_at = NOW(),
            mb_auction_ban_memo = '{$esc_memo}',
            mb_updated_at = NOW()
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '경매 이용 제한 처리에 실패했습니다.'];
    }

    return ['ok' => true];
}

/**
 * @return array{ok: bool, error?: string}
 */
function member_auction_ban_lift(int $mb_idx): array
{
    if (!member_auction_ban_column_ready()) {
        return ['ok' => false, 'error' => '경매 이용 제한 DB가 없습니다. sql/migrate_tb_member_auction_ban.sql 을 적용해 주세요.'];
    }
    if ($mb_idx < 1) {
        return ['ok' => false, 'error' => '잘못된 요청입니다.'];
    }

    global $conn;
    $ok = db_query("
        UPDATE tb_member SET
            mb_auction_banned = 0,
            mb_auction_banned_at = NULL,
            mb_auction_ban_memo = NULL,
            mb_updated_at = NOW()
        WHERE mb_idx = {$mb_idx}
          AND mb_auction_banned = 1
        LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'error' => '경매 이용 제한 해제에 실패했습니다.'];
    }
    if (mysqli_affected_rows($conn) !== 1) {
        return ['ok' => false, 'error' => '경매 이용 제한 상태가 아닙니다.'];
    }

    return ['ok' => true];
}
