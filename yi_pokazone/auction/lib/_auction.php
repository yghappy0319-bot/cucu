<?php
/**
 * 경매 공통 헬퍼
 */

require_once __DIR__ . '/_member_auction_ban.php';
require_once __DIR__ . '/_member_auction_eligibility.php';
require_once __DIR__ . '/../../lib/_member_cash.php';

/** 마감 임박 기준(초) — 24시간 이내 */
const AUCTION_ENDING_SOON_SEC = 86400;

/** 경매 등록자가 글 삭제 시 차감 포인트 */
const AUCTION_DELETE_POINT_PENALTY = 100;

function auction_delete_point_penalty(): int
{
    return AUCTION_DELETE_POINT_PENALTY;
}

function auction_table_ok(): bool
{
    return function_exists('db_table_exists') && db_table_exists('tb_auction');
}

function auction_image_table_ok(): bool
{
    return function_exists('db_table_exists') && db_table_exists('tb_auction_image');
}

function auction_bid_table_ok(): bool
{
    return function_exists('db_table_exists') && db_table_exists('tb_auction_bid');
}

/**
 * 다음 입찰 가능 최소 금액
 *
 * @param array<string, mixed> $row
 */
function auction_min_bid_amount(array $row): int
{
    $current = (int) ($row['au_current_price'] ?? 0);
    $start   = (int) ($row['au_start_price'] ?? 0);
    $step    = max(1, (int) ($row['au_bid_step'] ?? 1000));
    $count   = (int) ($row['au_bid_count'] ?? 0);

    if ($count <= 0) {
        return max($start, $current);
    }

    return $current + $step;
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed>|null $me
 * @return array{ok: bool, reason: string}
 */
function auction_can_bid(array $row, ?array $me): array
{
    if (!auction_bid_table_ok()) {
        return ['ok' => false, 'reason' => '입찰 DB가 설정되지 않았습니다.'];
    }
    if (!$me) {
        return ['ok' => false, 'reason' => 'login'];
    }
    if (member_auction_ban_column_ready() && member_auction_is_banned((int) ($me['mb_idx'] ?? 0))) {
        return ['ok' => false, 'reason' => member_auction_ban_user_message()];
    }
    $elig = member_auction_eligibility_bid($me);
    if (!$elig['ok']) {
        return [
            'ok'          => false,
            'reason'      => member_auction_eligibility_user_message($elig),
            'eligibility' => $elig,
        ];
    }
    if ((int) ($row['mb_idx'] ?? 0) === (int) ($me['mb_idx'] ?? 0)) {
        return ['ok' => false, 'reason' => '본인이 등록한 경매에는 입찰할 수 없습니다.'];
    }
    if ((int) ($row['au_status'] ?? 0) !== 1) {
        return ['ok' => false, 'reason' => '종료되었거나 삭제된 경매입니다.'];
    }
    if ((int) ($row['au_auction_status'] ?? 0) !== 1) {
        return ['ok' => false, 'reason' => '입찰이 종료된 경매입니다.'];
    }
    $now = time();
    if (strtotime((string) ($row['au_starts_at'] ?? '')) > $now) {
        return ['ok' => false, 'reason' => '아직 경매가 시작되지 않았습니다.'];
    }
    if (strtotime((string) ($row['au_ends_at'] ?? '')) <= $now) {
        return ['ok' => false, 'reason' => '마감된 경매입니다.'];
    }
    if (!member_cash_column_ready()) {
        return ['ok' => false, 'reason' => '캐시 기능이 준비되지 않았습니다. 잠시 후 다시 시도해 주세요.'];
    }
    $cash_balance = member_cash_balance((int) ($me['mb_idx'] ?? 0));
    $min_bid      = auction_min_bid_amount($row);
    if ($cash_balance < 1 || $cash_balance < $min_bid) {
        return [
            'ok'           => false,
            'reason'       => $cash_balance < 1
                ? '보유 캐시가 있어야 입찰할 수 있습니다.'
                : ('캐시 잔액이 최소 입찰가보다 부족합니다. (보유 ₩' . number_format($cash_balance)
                    . ' / 최소 ₩' . number_format($min_bid) . ')'),
            'need_cash'    => true,
            'cash_balance' => $cash_balance,
            'min_bid'      => $min_bid,
        ];
    }

    return ['ok' => true, 'reason' => '', 'cash_balance' => $cash_balance];
}

/**
 * @return list<array<string, mixed>>
 */
function auction_fetch_recent_bids(int $au_idx, int $limit = 30): array
{
    if (!auction_bid_table_ok() || $au_idx < 1) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $rows  = [];
    $rs    = db_query("
        SELECT b.ab_idx, b.ab_amount, b.ab_created_at, b.mb_idx, m.mb_id
        FROM tb_auction_bid b
        LEFT JOIN tb_member m ON m.mb_idx = b.mb_idx
        WHERE b.au_idx = {$au_idx}
        ORDER BY b.ab_idx DESC
        LIMIT {$limit}
    ");
    if ($rs) {
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
    }

    return $rows;
}

/**
 * 직전 최고 입찰 1건 (신규 입찰 전 기준)
 *
 * @return array{mb_idx: int, ab_amount: int}|null
 */
function auction_previous_top_bid(int $au_idx): ?array
{
    if (!auction_bid_table_ok() || $au_idx < 1) {
        return null;
    }
    $rs = db_query("
        SELECT mb_idx, ab_amount
        FROM tb_auction_bid
        WHERE au_idx = {$au_idx}
        ORDER BY ab_amount DESC, ab_idx DESC
        LIMIT 1
    ");
    $row = db_assoc($rs);
    if (!$row) {
        return null;
    }

    return [
        'mb_idx'    => (int) ($row['mb_idx'] ?? 0),
        'ab_amount' => (int) ($row['ab_amount'] ?? 0),
    ];
}

/**
 * @param list<string> $order
 * @param list<array{path: string, orig_name: string, size: int, mime: string}> $new_images
 * @param list<int> $remove_ids
 */
function auction_apply_image_order(int $au_idx, array $order, array $new_images, array $remove_ids): void
{
    if (!auction_image_table_ok()) {
        return;
    }

    $order = array_values(array_filter($order, static function ($ref) use ($remove_ids) {
        if (!is_string($ref)) {
            return false;
        }
        if (strpos($ref, 'existing:') === 0) {
            $id = (int) substr($ref, 9);

            return !in_array($id, $remove_ids, true);
        }

        return strpos($ref, 'new:') === 0;
    }));

    $used_new = [];
    $ord      = 0;
    foreach ($order as $ref) {
        if (strpos($ref, 'existing:') === 0) {
            $id = (int) substr($ref, 9);
            if ($id > 0) {
                db_query("UPDATE tb_auction_image SET ai_order = {$ord} WHERE au_idx = {$au_idx} AND ai_idx = {$id}");
                $ord++;
            }
        } elseif (strpos($ref, 'new:') === 0) {
            $ni = (int) substr($ref, 4);
            if (isset($new_images[$ni]) && !isset($used_new[$ni])) {
                $img = $new_images[$ni];
                $used_new[$ni] = true;
                $sql = "
                    INSERT INTO tb_auction_image
                        (au_idx, ai_path, ai_orig_name, ai_size, ai_mime, ai_order, ai_created_at)
                    VALUES
                        ({$au_idx},
                         '" . db_escape($img['path']) . "',
                         '" . db_escape($img['orig_name']) . "',
                         " . (int) $img['size'] . ",
                         '" . db_escape($img['mime']) . "',
                         {$ord},
                         NOW())
                ";
                if (db_query($sql)) {
                    $ord++;
                } else {
                    auction_remove_files([$img['path']]);
                }
            }
        }
    }

    for ($i = 0, $c = count($new_images); $i < $c; $i++) {
        if (isset($used_new[$i])) {
            continue;
        }
        $img = $new_images[$i];
        $sql = "
            INSERT INTO tb_auction_image
                (au_idx, ai_path, ai_orig_name, ai_size, ai_mime, ai_order, ai_created_at)
            VALUES
                ({$au_idx},
                 '" . db_escape($img['path']) . "',
                 '" . db_escape($img['orig_name']) . "',
                 " . (int) $img['size'] . ",
                 '" . db_escape($img['mime']) . "',
                 {$ord},
                 NOW())
        ";
        if (db_query($sql)) {
            $ord++;
        } else {
            auction_remove_files([$img['path']]);
        }
    }
}

function auction_sync_image_count(int $au_idx): void
{
    if (!auction_image_table_ok() || $au_idx < 1) {
        return;
    }
    $cnt = (int) db_result("SELECT COUNT(*) FROM tb_auction_image WHERE au_idx = {$au_idx}");
    db_query("UPDATE tb_auction SET au_image_count = {$cnt} WHERE au_idx = {$au_idx}");
}

/**
 * 마감 시각이 지난 진행 중 경매 1건을 낙찰(3) 또는 유찰(4)로 확정
 */
function auction_finalize_one(int $au_idx): bool
{
    if (!auction_table_ok() || !auction_bid_table_ok() || $au_idx < 1) {
        return false;
    }

    global $conn;
    if (!mysqli_begin_transaction($conn)) {
        return false;
    }

    $rs = db_query("
        SELECT au_idx
        FROM tb_auction
        WHERE au_idx = {$au_idx}
          AND au_status = 1
          AND au_auction_status = 1
          AND au_ends_at <= NOW()
        LIMIT 1
        FOR UPDATE
    ");
    if (!db_assoc($rs)) {
        mysqli_rollback($conn);

        return false;
    }

    $top = db_assoc(db_query("
        SELECT mb_idx, ab_amount
        FROM tb_auction_bid
        WHERE au_idx = {$au_idx}
        ORDER BY ab_amount DESC, ab_idx DESC
        LIMIT 1
    "));

    if ($top && (int) ($top['mb_idx'] ?? 0) > 0) {
        $winner = (int) $top['mb_idx'];
        $amount = max(0, (int) ($top['ab_amount'] ?? 0));
        $ok     = db_query("
            UPDATE tb_auction SET
                au_current_price  = {$amount},
                au_auction_status = 3,
                au_winner_mb_idx  = {$winner},
                au_updated_at     = NOW()
            WHERE au_idx = {$au_idx}
              AND au_status = 1
              AND au_auction_status = 1
        ");
    } else {
        $ok = db_query("
            UPDATE tb_auction SET
                au_auction_status = 4,
                au_winner_mb_idx  = NULL,
                au_updated_at     = NOW()
            WHERE au_idx = {$au_idx}
              AND au_status = 1
              AND au_auction_status = 1
        ");
    }

    if (!$ok || mysqli_affected_rows($conn) !== 1) {
        mysqli_rollback($conn);

        return false;
    }

    mysqli_commit($conn);

    if ($top && (int) ($top['mb_idx'] ?? 0) > 0) {
        $winner = (int) $top['mb_idx'];
        $amount = max(0, (int) ($top['ab_amount'] ?? 0));
        $title  = (string) db_result("SELECT au_title FROM tb_auction WHERE au_idx = {$au_idx} LIMIT 1");
        auction_notify_winner($au_idx, $winner, $title, $amount);
    }

    return true;
}

/**
 * 낙찰자 푸시 알림
 */
function auction_notify_winner(int $au_idx, int $winner_mb_idx, string $title, int $amount): void
{
    if ($winner_mb_idx < 1 || $au_idx < 1 || $amount < 1) {
        return;
    }
    require_once __DIR__ . '/webpush.php';
    if (function_exists('webpush_notify_auction_won')) {
        webpush_notify_auction_won($winner_mb_idx, $title, $amount, $au_idx);
    }
}

/**
 * 마감 시각이 지난 경매를 일괄 확정 (낙찰 또는 유찰)
 *
 * @return int 확정 처리한 건수
 */
function auction_finalize_expired(?int $au_idx = null, int $limit = 100): int
{
    if (!auction_table_ok()) {
        return 0;
    }

    if ($au_idx !== null && $au_idx > 0) {
        return auction_finalize_one($au_idx) ? 1 : 0;
    }

    $limit = max(1, min(500, $limit));
    $count = 0;
    $rs    = db_query("
        SELECT au_idx
        FROM tb_auction
        WHERE au_status = 1
          AND au_auction_status = 1
          AND au_ends_at <= NOW()
        ORDER BY au_ends_at ASC
        LIMIT {$limit}
    ");
    while ($r = db_assoc($rs)) {
        if (auction_finalize_one((int) $r['au_idx'])) {
            $count++;
        }
    }

    return $count;
}

/**
 * 낙찰·유찰 결과: live | won | failed | cancelled | pending
 *
 * @param array<string, mixed> $row
 */
function auction_outcome(array $row): string
{
    $ast = (int) ($row['au_auction_status'] ?? 1);
    if ($ast === 3) {
        return 'won';
    }
    if ($ast === 4) {
        return 'failed';
    }
    if ($ast === 9) {
        return 'cancelled';
    }
    if (auction_display_status($row) === 'ended') {
        return 'pending';
    }

    return 'live';
}

/**
 * @param array<string, mixed> $row
 */
function auction_outcome_label(array $row): ?string
{
    $outcome = auction_outcome($row);
    if ($outcome === 'won') {
        return '낙찰';
    }
    if ($outcome === 'failed') {
        return '유찰';
    }

    return null;
}

/**
 * 마이페이지·내역 목록용 상태 문구
 *
 * @param array<string, mixed> $row
 */
function auction_mypage_status_text(array $row): string
{
    $outcome = auction_outcome_label($row);
    if ($outcome !== null) {
        return $outcome;
    }
    $ast = (int) ($row['au_auction_status'] ?? 1);
    if ($ast === 9) {
        return '취소';
    }
    if ($ast === 0) {
        return '예정';
    }
    $labels = auction_status_labels();
    $ds     = auction_display_status($row);

    return $labels[$ds]['label'] ?? '종료';
}

/**
 * 목록·카드용 표시 상태: live | ending | ended
 *
 * @param array<string, mixed> $row
 */
function auction_display_status(array $row): string
{
    $auction_status = (int) ($row['au_auction_status'] ?? 1);
    $ends_ts        = strtotime((string) ($row['au_ends_at'] ?? ''));
    $starts_ts      = strtotime((string) ($row['au_starts_at'] ?? ''));

    if ($auction_status >= 2 || ($ends_ts > 0 && $ends_ts <= time())) {
        return 'ended';
    }
    if ($starts_ts > time()) {
        return 'live';
    }
    if ($ends_ts > 0 && ($ends_ts - time()) <= AUCTION_ENDING_SOON_SEC) {
        return 'ending';
    }

    return 'live';
}

function auction_format_remain(int $ends_at): string
{
    $diff = $ends_at - time();
    if ($diff <= 0) {
        return '종료됨';
    }

    $d = (int) floor($diff / 86400);
    $h = (int) floor(($diff % 86400) / 3600);
    $m = (int) floor(($diff % 3600) / 60);
    $s = (int) ($diff % 60);

    $parts = [];
    if ($d > 0) {
        $parts[] = $d . '일';
    }
    if ($d > 0 || $h > 0) {
        $parts[] = $h . '시간';
    }
    $parts[] = $m . '분';
    $parts[] = $s . '초';

    return implode(' ', $parts) . ' 남음';
}

/**
 * @return array<string, array{label: string, class: string}>
 */
function auction_status_labels(): array
{
    return [
        'live'   => ['label' => '진행중',   'class' => 'ds-active'],
        'ending' => ['label' => '마감임박', 'class' => 'ds-ending'],
        'ended'  => ['label' => '종료',     'class' => 'ds-done'],
    ];
}

/**
 * @param array<string, mixed> $row
 */
function auction_category_label(array $row): string
{
    if (($row['au_item_type'] ?? '') === 'box') {
        return '미개봉박스';
    }
    $k = $row['au_card_kind'] ?? '';
    if ($k === 'graded') {
        return '등급';
    }
    if ($k === 'single') {
        return '싱글';
    }

    return '카드';
}

/**
 * 입찰 내역 표시용 회원 ID: 1~3번째 공개, 4~5번째만 마스킹
 */
function auction_mask_bidder_id(string $mb_id): string
{
    $id = trim($mb_id);
    if ($id === '') {
        return '(탈퇴)';
    }
    $len = mb_strlen($id, 'UTF-8');
    if ($len <= 3) {
        return $id;
    }
    $prefix     = mb_substr($id, 0, 3, 'UTF-8');
    $mask_count = min(2, $len - 3);
    $rest       = '';
    if (3 + $mask_count < $len) {
        $rest = mb_substr($id, 3 + $mask_count, null, 'UTF-8');
    }

    return $prefix . str_repeat('*', $mask_count) . $rest;
}

/**
 * 입찰 내역 <li> 목록 HTML (ol.auction-bid-list 내부)
 *
 * @param list<array<string, mixed>> $bid_rows
 */
function auction_render_bid_list_items(array $bid_rows, ?int $viewer_mb_idx = null): string
{
    if ($bid_rows === []) {
        return '<li class="auction-bid-list__empty">아직 입찰 내역이 없습니다.</li>';
    }

    $html = '';
    foreach ($bid_rows as $bi => $bid) {
        $is_me  = $viewer_mb_idx && (int) ($bid['mb_idx'] ?? 0) === $viewer_mb_idx;
        $is_top = $bi === 0;
        $cls    = 'auction-bid-list__item' . ($is_top ? ' is-top' : '') . ($is_me ? ' is-me' : '');
        $rank   = $is_top ? '1' : (string) ($bi + 1);
        $ts     = strtotime((string) ($bid['ab_created_at'] ?? ''));
        $html  .= '<li class="' . $cls . '" data-ab-idx="' . (int) ($bid['ab_idx'] ?? 0) . '">';
        $html  .= '<span class="auction-bid-list__rank" aria-hidden="true">' . htmlspecialchars($rank) . '</span>';
        $html  .= '<div class="auction-bid-list__body">';
        $html  .= '<span class="auction-bid-list__nick">';
        $html  .= htmlspecialchars(auction_mask_bidder_id((string) ($bid['mb_id'] ?? '')));
        if ($is_me) {
            $html .= '<em class="auction-bid-list__me">나</em>';
        }
        if ($is_top) {
            $html .= '<span class="auction-bid-list__badge">최고가</span>';
        }
        $html  .= '</span>';
        $html  .= '<time class="auction-bid-list__time" datetime="' . htmlspecialchars(date('c', $ts ?: time())) . '">';
        $html  .= date('m/d H:i:s', $ts ?: time());
        $html  .= '</time></div>';
        $html  .= '<span class="auction-bid-list__amount">₩' . number_format((int) ($bid['ab_amount'] ?? 0)) . '</span>';
        $html  .= '</li>';
    }

    return $html;
}

/**
 * 상세·폴링·AJAX 입찰 응답용 스냅샷
 *
 * @return array<string, mixed>|null
 */
function auction_view_snapshot(int $au_idx, ?array $me = null): ?array
{
    if (!auction_table_ok() || $au_idx < 1) {
        return null;
    }

    $rs  = db_query("
        SELECT a.*, m.mb_nick AS seller_nick
        FROM tb_auction a
        LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
        WHERE a.au_idx = {$au_idx} AND a.au_status = 1
        LIMIT 1
    ");
    $row = db_assoc($rs);
    if (!$row) {
        return null;
    }

    $viewer_mb_idx   = $me ? (int) $me['mb_idx'] : 0;
    $display_status  = auction_display_status($row);
    $is_ended        = $display_status === 'ended';
    $ends_ts         = (int) strtotime((string) $row['au_ends_at']);
    $bid_rows        = auction_fetch_recent_bids($au_idx, 30);
    $min_bid         = auction_min_bid_amount($row);
    $bid_step        = max(1, (int) ($row['au_bid_step'] ?? 1000));
    $can_bid         = auction_can_bid($row, $me);
    $last_ab_idx     = 0;
    if ($bid_rows !== []) {
        $last_ab_idx = (int) ($bid_rows[0]['ab_idx'] ?? 0);
    }

    $winner_nick = '';
    if (!empty($row['au_winner_mb_idx'])) {
        $winner_nick = (string) db_result(
            'SELECT mb_nick FROM tb_member WHERE mb_idx = ' . (int) $row['au_winner_mb_idx'] . ' LIMIT 1'
        );
    }

    $outcome = auction_outcome($row);

    return [
        'au_idx'          => $au_idx,
        'current_price'   => (int) $row['au_current_price'],
        'bid_count'       => (int) $row['au_bid_count'],
        'start_price'     => (int) $row['au_start_price'],
        'bid_step'        => $bid_step,
        'min_bid'         => $min_bid,
        'buy_now_price'   => (int) ($row['au_buy_now_price'] ?? 0),
        'display_status'  => $display_status,
        'is_ended'        => $is_ended,
        'ends_ts'         => $ends_ts,
        'remain_text'     => auction_format_remain($ends_ts),
        'can_bid'         => $can_bid['ok'],
        'can_bid_reason'  => $can_bid['ok'] ? '' : (string) ($can_bid['reason'] ?? ''),
        'winner_nick'     => $winner_nick,
        'outcome'         => $outcome,
        'outcome_label'   => auction_outcome_label($row),
        'auction_status'  => (int) ($row['au_auction_status'] ?? 1),
        'last_ab_idx'     => $last_ab_idx,
        'bids_html'       => auction_render_bid_list_items($bid_rows, $viewer_mb_idx ?: null),
        'bid_count_label' => number_format((int) $row['au_bid_count']) . '건',
    ];
}

function auction_json_response(array $payload, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($payload, $flags);
    exit;
}

function auction_request_is_ajax(): bool
{
    if (!empty($_POST['ajax']) && (string) $_POST['ajax'] === '1') {
        return true;
    }
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return strtolower((string) $xhr) === 'xmlhttprequest';
}

/**
 * 입찰 proc — AJAX면 JSON, 아니면 alert_goto
 */
function auction_bid_exit(
    string $message,
    string $return,
    bool $is_ajax,
    bool $ok = false,
    int $au_idx = 0,
    ?array $me = null,
    bool $reload = false,
    ?string $redirect = null
): void {
    if ($is_ajax) {
        $payload = ['ok' => $ok, 'message' => $message];
        if ($redirect !== null) {
            $payload['redirect'] = $redirect;
        }
        if ($ok && $au_idx > 0) {
            try {
                $payload['snapshot'] = auction_view_snapshot($au_idx, $me);
            } catch (Throwable $e) {
                error_log('auction_view_snapshot: ' . $e->getMessage());
                $payload['snapshot'] = null;
            }
            $payload['reload'] = $reload;
        }
        auction_json_response($payload, $ok ? 200 : 400);
    }
    if ($redirect !== null) {
        alert_goto($message, $redirect);
    }
    alert_goto($message, $return);
}
