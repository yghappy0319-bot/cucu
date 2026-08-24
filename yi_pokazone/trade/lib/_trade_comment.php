<?php
/**
 * 거래글 공개 댓글
 */

function trade_comment_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_trade_comment');

    return $ready;
}

function trade_comment_has_count_column(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_trade')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_comments'"));

    return $ready;
}

/** 댓글 작성 시 차감 포인트 */
function trade_comment_point_cost(): int
{
    return 10;
}

function trade_comment_count(int $tr_idx): int
{
    $tr_idx = max(0, $tr_idx);
    if ($tr_idx < 1 || !trade_comment_table_ready()) {
        return 0;
    }

    return (int) db_result("
        SELECT COUNT(*) FROM tb_trade_comment
        WHERE tr_idx = {$tr_idx} AND tc_status = 1
    ");
}

function trade_comment_sync_count(int $tr_idx): void
{
    $tr_idx = max(0, $tr_idx);
    if ($tr_idx < 1 || !trade_comment_has_count_column()) {
        return;
    }
    $n = trade_comment_count($tr_idx);
    db_query("UPDATE tb_trade SET tr_comments = {$n} WHERE tr_idx = {$tr_idx} LIMIT 1");
}

/**
 * @return array<int, array<string, mixed>>
 */
function trade_comment_list(int $tr_idx): array
{
    $tr_idx = max(0, $tr_idx);
    if ($tr_idx < 1 || !trade_comment_table_ready()) {
        return [];
    }

    $rs = db_query("
        SELECT c.*, m.mb_nick
        FROM tb_trade_comment c
        LEFT JOIN tb_member m ON m.mb_idx = c.mb_idx
        WHERE c.tr_idx = {$tr_idx} AND c.tc_status = 1
        ORDER BY c.tc_created_at ASC, c.tc_idx ASC
    ");
    $out = [];
    while ($row = db_assoc($rs)) {
        $out[] = $row;
    }

    return $out;
}

/**
 * 댓글 등록 + 포인트 차감 (원자적)
 * 답글(tc_parent_idx > 0)은 거래글 작성자만 가능하며 포인트 차감 없음
 *
 * @return array{ok: bool, error?: string, tc_idx?: int, charged?: int, is_reply?: bool}
 */
function trade_comment_submit(int $tr_idx, int $mb_idx, string $content_raw, int $parent_idx = 0): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    $tr_idx = max(0, $tr_idx);
    $mb_idx = max(0, $mb_idx);
    $parent_idx = max(0, $parent_idx);
    $content = trim($content_raw);
    $is_reply = $parent_idx > 0;

    if ($tr_idx < 1 || $mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!trade_comment_table_ready()) {
        return $fail('댓글 기능을 사용하려면 sql/migrate_tb_trade_comment.sql 을 적용해 주세요.');
    }
    if ($content === '') {
        return $fail('내용을 입력해 주세요.');
    }
    if (mb_strlen($content) > 2000) {
        return $fail('댓글은 2,000자 이내로 작성해 주세요.');
    }

    $trade = db_assoc(db_query("
        SELECT tr_idx, tr_status, mb_idx, tr_title FROM tb_trade WHERE tr_idx = {$tr_idx} LIMIT 1
    "));
    if (!$trade || (int) $trade['tr_status'] !== 1) {
        return $fail('글을 찾을 수 없습니다.');
    }

    $seller_mb = (int) ($trade['mb_idx'] ?? 0);
    $parent_sql = 'NULL';
    $parent_commenter_mb = 0;
    $parent_tc_idx = 0;
    if ($is_reply) {
        if ($mb_idx !== $seller_mb) {
            return $fail('답글은 거래글 작성자만 작성할 수 있습니다.');
        }
        $pr = db_assoc(db_query("
            SELECT tc_idx, tr_idx, mb_idx, tc_parent_idx, tc_status
            FROM tb_trade_comment
            WHERE tc_idx = {$parent_idx}
            LIMIT 1
        "));
        if (!$pr || (int) $pr['tr_idx'] !== $tr_idx || (int) $pr['tc_status'] !== 1) {
            return $fail('대댓글을 달 수 없습니다.');
        }
        if ($pr['tc_parent_idx'] !== null && (int) $pr['tc_parent_idx'] > 0) {
            return $fail('대댓글에는 또 답글을 달 수 없습니다.');
        }
        $parent_sql = (string) (int) $pr['tc_idx'];
        $parent_commenter_mb = (int) ($pr['mb_idx'] ?? 0);
        $parent_tc_idx = (int) $pr['tc_idx'];
    }

    // 최상위 댓글만 포인트 차감, 답글은 무료
    $cost = $is_reply ? 0 : trade_comment_point_cost();
    if ($cost > 0 && !db_table_exists('tb_point_log')) {
        return $fail('포인트 기능을 사용할 수 없습니다.');
    }

    if ($cost > 0) {
        $bal = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
        if ($bal < $cost) {
            return $fail('포인트가 부족합니다. 댓글 작성에는 ' . number_format($cost) . 'P가 필요합니다. (보유 ' . number_format($bal) . 'P)');
        }
    }

    global $conn;
    mysqli_begin_transaction($conn);

    $esc = db_escape($content);
    $ip  = db_escape(get_client_ip());
    $ins = "
        INSERT INTO tb_trade_comment (tr_idx, mb_idx, tc_parent_idx, tc_content, tc_ip)
        VALUES ({$tr_idx}, {$mb_idx}, {$parent_sql}, '{$esc}', '{$ip}')
    ";
    if (!db_query($ins)) {
        mysqli_rollback($conn);

        return $fail('등록 중 오류가 발생했습니다.');
    }
    $tc_idx = (int) db_insert_id();

    if ($cost > 0) {
        $delta = -$cost;
        $up = "
            UPDATE tb_member
            SET mb_point = mb_point + ({$delta})
            WHERE mb_idx = {$mb_idx}
              AND mb_point >= {$cost}
            LIMIT 1
        ";
        if (!db_query($up) || mysqli_affected_rows($conn) !== 1) {
            mysqli_rollback($conn);

            return $fail('포인트가 부족합니다. 댓글 작성에는 ' . number_format($cost) . 'P가 필요합니다.');
        }

        $new_bal  = (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
        $esc_memo = db_escape('거래글 댓글 작성');
        $log = "
            INSERT INTO tb_point_log (mb_idx, pl_change, pl_balance, pl_type, pl_memo)
            VALUES ({$mb_idx}, {$delta}, {$new_bal}, 'trade_comment', '{$esc_memo}')
        ";
        if (!db_query($log)) {
            mysqli_rollback($conn);

            return $fail('포인트 차감 중 오류가 발생했습니다.');
        }
    }

    mysqli_commit($conn);
    trade_comment_sync_count($tr_idx);

    // 최상위 댓글만 판매자에게 푸시 (본인 글에 본인이 단 댓글·답글 제외)
    if (!$is_reply && $seller_mb > 0 && $mb_idx !== $seller_mb) {
        try {
            require_once dirname(__DIR__, 2) . '/lib/webpush.php';
            $nick_row = db_assoc(db_query("SELECT mb_nick FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"));
            $from_nick = trim((string) ($nick_row['mb_nick'] ?? ''));
            if ($from_nick === '') {
                $from_nick = '회원';
            }
            webpush_notify_trade_comment(
                $seller_mb,
                $from_nick,
                (string) ($trade['tr_title'] ?? ''),
                $content,
                $tr_idx
            );
        } catch (Throwable $e) {
            error_log('trade_comment_submit webpush: ' . $e->getMessage());
        }
    }

    // 판매자 답글 → 원댓글 작성자(문의자)에게 푸시
    if ($is_reply && $parent_commenter_mb > 0 && $parent_commenter_mb !== $mb_idx) {
        try {
            require_once dirname(__DIR__, 2) . '/lib/webpush.php';
            $nick_row = db_assoc(db_query("SELECT mb_nick FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"));
            $seller_nick = trim((string) ($nick_row['mb_nick'] ?? ''));
            if ($seller_nick === '') {
                $seller_nick = '판매자';
            }
            webpush_notify_trade_comment_reply(
                $parent_commenter_mb,
                $seller_nick,
                (string) ($trade['tr_title'] ?? ''),
                $content,
                $tr_idx,
                $parent_tc_idx
            );
        } catch (Throwable $e) {
            error_log('trade_comment_submit reply webpush: ' . $e->getMessage());
        }
    }

    return [
        'ok'       => true,
        'tc_idx'   => $tc_idx,
        'charged'  => $cost,
        'is_reply' => $is_reply,
    ];
}

/**
 * 댓글 소프트 삭제
 *
 * @return array{ok: bool, error?: string, tr_idx?: int}
 */
function trade_comment_delete(int $tc_idx, int $actor_mb_idx, bool $is_mod = false): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    $tc_idx = max(0, $tc_idx);
    if ($tc_idx < 1 || !trade_comment_table_ready()) {
        return $fail('잘못된 요청입니다.');
    }

    $row = db_assoc(db_query("
        SELECT c.tc_idx, c.tr_idx, c.mb_idx, c.tc_parent_idx, c.tc_status, t.tr_status
        FROM tb_trade_comment c
        INNER JOIN tb_trade t ON t.tr_idx = c.tr_idx
        WHERE c.tc_idx = {$tc_idx}
        LIMIT 1
    "));
    if (!$row || (int) $row['tc_status'] !== 1) {
        return $fail('이미 삭제되었거나 없는 댓글입니다.');
    }

    $is_owner = $actor_mb_idx > 0 && (int) $row['mb_idx'] === $actor_mb_idx;
    if (!$is_owner && !$is_mod) {
        return $fail('삭제 권한이 없습니다.');
    }

    $tr_idx = (int) $row['tr_idx'];

    if ($row['tc_parent_idx'] === null || (int) $row['tc_parent_idx'] === 0) {
        db_query("
            UPDATE tb_trade_comment
            SET tc_status = 9,
                tc_content = '삭제된 댓글입니다.',
                tc_updated_at = NOW()
            WHERE tr_idx = {$tr_idx}
              AND (tc_idx = {$tc_idx} OR tc_parent_idx = {$tc_idx})
              AND tc_status = 1
        ");
    } else {
        db_query("
            UPDATE tb_trade_comment
            SET tc_status = 9,
                tc_content = '삭제된 댓글입니다.',
                tc_updated_at = NOW()
            WHERE tc_idx = {$tc_idx} AND tr_idx = {$tr_idx}
            LIMIT 1
        ");
    }

    trade_comment_sync_count($tr_idx);

    return ['ok' => true, 'tr_idx' => $tr_idx];
}
