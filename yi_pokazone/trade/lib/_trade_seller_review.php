<?php
/**
 * 거래 구매확정 후 판매자 후기·별점
 */

require_once __DIR__ . '/_trade_payment.php';

if (!defined('TRADE_SELLER_REVIEW_BODY_MAX')) {
    define('TRADE_SELLER_REVIEW_BODY_MAX', 500);
}
if (!defined('TRADE_SELLER_REVIEW_POINT_REWARD')) {
    define('TRADE_SELLER_REVIEW_POINT_REWARD', 100);
}
if (!defined('TRADE_SELLER_REVIEW_STATUS_VISIBLE')) {
    define('TRADE_SELLER_REVIEW_STATUS_VISIBLE', 1);
}
if (!defined('TRADE_SELLER_REVIEW_STATUS_HIDDEN')) {
    define('TRADE_SELLER_REVIEW_STATUS_HIDDEN', 0);
}

function trade_seller_review_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_exists('tb_trade_seller_review');

    return $ready;
}

/**
 * @return array<string, mixed>|null
 */
function trade_seller_review_by_pay(int $pay_idx): ?array
{
    $pay_idx = max(0, $pay_idx);
    if ($pay_idx < 1 || !trade_seller_review_table_ready()) {
        return null;
    }
    $row = db_assoc(db_query("
        SELECT *
        FROM tb_trade_seller_review
        WHERE pay_idx = {$pay_idx}
        LIMIT 1
    "));

    return $row ?: null;
}

/**
 * @return array{avg: float, count: int}
 */
function trade_seller_review_stats(int $seller_mb_idx): array
{
    $seller_mb_idx = max(0, $seller_mb_idx);
    if ($seller_mb_idx < 1 || !trade_seller_review_table_ready()) {
        return ['avg' => 0.0, 'count' => 0];
    }
    $st = (int) TRADE_SELLER_REVIEW_STATUS_VISIBLE;
    $row = db_assoc(db_query("
        SELECT COUNT(*) AS cnt, AVG(tsr_rating) AS avg_rating
        FROM tb_trade_seller_review
        WHERE seller_mb_idx = {$seller_mb_idx}
          AND tsr_status = {$st}
    "));
    $count = (int) ($row['cnt'] ?? 0);
    $avg   = $count > 0 ? round((float) ($row['avg_rating'] ?? 0), 1) : 0.0;

    return ['avg' => $avg, 'count' => $count];
}

/**
 * @return list<array<string, mixed>>
 */
function trade_seller_review_list(int $seller_mb_idx, int $limit = 20, int $offset = 0): array
{
    $seller_mb_idx = max(0, $seller_mb_idx);
    $limit  = max(1, min(50, $limit));
    $offset = max(0, $offset);
    if ($seller_mb_idx < 1 || !trade_seller_review_table_ready()) {
        return [];
    }
    $st = (int) TRADE_SELLER_REVIEW_STATUS_VISIBLE;
    $rs = db_query("
        SELECT r.*, m.mb_nick AS buyer_nick
        FROM tb_trade_seller_review r
        LEFT JOIN tb_member m ON m.mb_idx = r.buyer_mb_idx
        WHERE r.seller_mb_idx = {$seller_mb_idx}
          AND r.tsr_status = {$st}
        ORDER BY r.tsr_idx DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $out = [];
    while ($row = db_assoc($rs)) {
        $out[] = $row;
    }

    return $out;
}

function trade_seller_review_count(int $seller_mb_idx): int
{
    $seller_mb_idx = max(0, $seller_mb_idx);
    if ($seller_mb_idx < 1 || !trade_seller_review_table_ready()) {
        return 0;
    }
    $st = (int) TRADE_SELLER_REVIEW_STATUS_VISIBLE;

    return (int) db_result("
        SELECT COUNT(*)
        FROM tb_trade_seller_review
        WHERE seller_mb_idx = {$seller_mb_idx}
          AND tsr_status = {$st}
    ");
}

/**
 * 별점 표시 HTML (접근성 텍스트 포함)
 */
function trade_seller_review_stars_html(int $rating, string $extra_class = ''): string
{
    $rating = max(0, min(5, $rating));
    $cls = 'trade-seller-review-stars';
    if ($extra_class !== '') {
        $cls .= ' ' . trim($extra_class);
    }
    $html = '<span class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" role="img" aria-label="'
        . htmlspecialchars($rating . '점 / 5점', ENT_QUOTES, 'UTF-8') . '">';
    for ($i = 1; $i <= 5; $i++) {
        $on = $i <= $rating ? ' is-on' : '';
        $html .= '<span class="trade-seller-review-star' . $on . '" aria-hidden="true">★</span>';
    }
    $html .= '</span>';

    return $html;
}

/**
 * 구매자 — 이 결제 건에 후기를 남길 수 있는지
 *
 * @return array{ok:bool,error?:string,row?:array<string,mixed>}
 */
function trade_seller_review_can_write(int $pay_idx, int $buyer_mb_idx): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($pay_idx < 1 || $buyer_mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!trade_seller_review_table_ready()) {
        return $fail('후기 기능을 사용하려면 sql/migrate_tb_trade_seller_review.sql 을 적용해 주세요.');
    }

    $row = trade_payment_row($pay_idx);
    if (!$row) {
        return $fail('결제 정보를 찾을 수 없습니다.');
    }
    if ((int) ($row['buyer_mb_idx'] ?? 0) !== $buyer_mb_idx) {
        return $fail('구매자만 후기를 작성할 수 있습니다.');
    }
    if (!trade_payment_is_confirmed_status((int) ($row['pay_status'] ?? -1))) {
        return $fail('입금이 확인된 거래만 후기를 남길 수 있습니다.');
    }
    if (trade_payment_is_cancelled_status((int) ($row['pay_status'] ?? -1))) {
        return $fail('취소된 거래에는 후기를 남길 수 없습니다.');
    }
    $fulfill = trade_payment_fulfill_status_from_row($row);
    if ($fulfill < TRADE_PAYMENT_FULFILL_PURCHASE) {
        return $fail('구매확정 후에만 후기를 남길 수 있습니다.');
    }
    if (trade_seller_review_by_pay($pay_idx)) {
        return $fail('이미 이 거래에 대한 후기를 작성했습니다.');
    }

    return ['ok' => true, 'row' => $row];
}

/**
 * @return array{ok:bool,error?:string,tsr_idx?:int,points_awarded?:int}
 */
function trade_seller_review_submit(int $pay_idx, int $buyer_mb_idx, int $rating, string $body_raw): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    $can = trade_seller_review_can_write($pay_idx, $buyer_mb_idx);
    if (empty($can['ok'])) {
        return $fail((string) ($can['error'] ?? '후기를 작성할 수 없습니다.'));
    }
    /** @var array<string, mixed> $row */
    $row = (array) ($can['row'] ?? []);

    $rating = (int) $rating;
    if ($rating < 1 || $rating > 5) {
        return $fail('별점은 1~5점 중에서 선택해 주세요.');
    }

    $body = trim(str_replace("\r\n", "\n", $body_raw));
    $body = preg_replace("/\n{3,}/u", "\n\n", $body) ?? $body;
    if ($body === '') {
        return $fail('후기 내용을 입력해 주세요.');
    }
    if (mb_strlen($body) > TRADE_SELLER_REVIEW_BODY_MAX) {
        return $fail('후기는 ' . TRADE_SELLER_REVIEW_BODY_MAX . '자 이내로 입력해 주세요.');
    }

    $seller_mb = (int) ($row['seller_mb_idx'] ?? 0);
    $tr_idx    = (int) ($row['tr_idx'] ?? 0);
    if ($seller_mb < 1) {
        return $fail('판매자 정보를 확인할 수 없습니다.');
    }

    $esc_body = db_escape($body);
    $st       = (int) TRADE_SELLER_REVIEW_STATUS_VISIBLE;
    $tr_sql   = $tr_idx > 0 ? (string) $tr_idx : 'NULL';

    $ok = db_query("
        INSERT INTO tb_trade_seller_review
            (pay_idx, tr_idx, seller_mb_idx, buyer_mb_idx, tsr_rating, tsr_body, tsr_status, tsr_created_at)
        VALUES
            ({$pay_idx}, {$tr_sql}, {$seller_mb}, {$buyer_mb_idx}, {$rating}, '{$esc_body}', {$st}, NOW())
    ");
    if (!$ok) {
        if (trade_seller_review_by_pay($pay_idx)) {
            return $fail('이미 이 거래에 대한 후기를 작성했습니다.');
        }

        return $fail('후기 저장 중 오류가 발생했습니다.');
    }

    $tsr_idx = (int) db_insert_id();
    $reward  = (int) TRADE_SELLER_REVIEW_POINT_REWARD;
    $points_awarded = 0;
    if ($reward > 0 && function_exists('point_change_with_log')) {
        $memo = '판매자 후기 작성 (pay#' . $pay_idx . ')';
        if (point_change_with_log($buyer_mb_idx, $reward, 'seller_review', $memo)) {
            $points_awarded = $reward;
        }
    }

    return [
        'ok'             => true,
        'tsr_idx'        => $tsr_idx,
        'points_awarded' => $points_awarded,
    ];
}
