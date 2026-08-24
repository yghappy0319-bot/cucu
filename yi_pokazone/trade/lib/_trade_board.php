<?php
/**
 * 거래게시판 목록 필터 · 조회
 */

function trade_board_types(): array
{
    return [
        ''         => '전체',
        'sell'     => '판매',
        'buy'      => '구매',
        'exchange' => '교환',
    ];
}

function trade_board_item_filters(): array
{
    return [
        ''       => '전체상품',
        'single' => '싱글',
        'graded' => '등급',
        'box'    => '📦 미개봉박스',
    ];
}

function trade_board_deal_status_labels(): array
{
    return [
        1 => ['label' => '판매중',   'class' => 'ds-active'],
        2 => ['label' => '거래중',   'class' => 'ds-reserved'],
        3 => ['label' => '거래완료', 'class' => 'ds-done'],
    ];
}

function trade_board_per_page(): int
{
    return 40;
}

/**
 * @return array{
 *   type: string,
 *   item: string,
 *   keyword: string,
 *   hide_done: bool,
 *   mine_only: bool
 * }
 */
function trade_board_parse_filters(array $src = null): array
{
    $src = $src ?? $_GET;
    $types = trade_board_types();
    $item_filters = trade_board_item_filters();

    $cur_type = isset($src['type']) ? trim((string) $src['type']) : '';
    if (!array_key_exists($cur_type, $types)) {
        $cur_type = '';
    }

    $cur_item = isset($src['item']) ? trim((string) $src['item']) : '';
    if ($cur_item === 'card') {
        $cur_item = '';
    }
    if (!array_key_exists($cur_item, $item_filters)) {
        $cur_item = '';
    }

    return [
        'type'      => $cur_type,
        'item'      => $cur_item,
        'keyword'   => isset($src['q']) ? trim((string) $src['q']) : '',
        'hide_done' => !empty($src['active']),
        'mine_only' => !empty($src['mine']),
    ];
}

/**
 * @param array{
 *   type: string,
 *   item: string,
 *   keyword: string,
 *   hide_done: bool,
 *   mine_only: bool
 * } $filters
 */
function trade_board_where_sql(array $filters, ?array $me): string
{
    $where = [trade_status_public_sql('t')];

    if ($filters['type'] !== '') {
        $where[] = "t.tr_type = '" . db_escape($filters['type']) . "'";
    }
    if ($filters['item'] === 'box') {
        $where[] = "t.tr_item_type = 'box'";
    } elseif ($filters['item'] === 'single') {
        $where[] = "t.tr_item_type = 'card' AND t.tr_card_kind = 'single'";
    } elseif ($filters['item'] === 'graded') {
        $where[] = "t.tr_item_type = 'card' AND t.tr_card_kind = 'graded'";
    }
    if (!empty($filters['hide_done'])) {
        $where[] = 't.tr_deal_status <> 3';
    }
    if ($filters['keyword'] !== '') {
        $kw = db_escape($filters['keyword']);
        $where[] = "(t.tr_title LIKE '%{$kw}%' OR t.tr_card_name LIKE '%{$kw}%' OR t.tr_content LIKE '%{$kw}%')";
    }
    if (!empty($filters['mine_only']) && $me) {
        $where[] = 't.mb_idx = ' . (int) $me['mb_idx'];
    }

    return implode(' AND ', $where);
}

/**
 * @param array{
 *   type: string,
 *   item: string,
 *   keyword: string,
 *   hide_done: bool,
 *   mine_only: bool
 * } $filters
 * @return array{
 *   rows: array<int, array<string, mixed>>,
 *   total: int,
 *   total_page: int,
 *   page_no: int,
 *   per: int,
 *   has_more: bool
 * }
 */
function trade_board_fetch_page(array $filters, int $page_no, ?array $me = null, ?int $per = null): array
{
    if (!function_exists('trade_comment_table_ready')) {
        require_once __DIR__ . '/_trade_comment.php';
    }
    if (!function_exists('trade_seller_listing_order_sql')) {
        require_once __DIR__ . '/_trade_listing.php';
    }

    $page_no = max(1, $page_no);
    $per     = $per ?? trade_board_per_page();
    $per     = max(1, min(100, $per));
    $offset  = ($page_no - 1) * $per;

    $where_sql = trade_board_where_sql($filters, $me);
    $total = (int) db_result("SELECT COUNT(*) FROM tb_trade t WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $inquiry_sel = trade_comment_table_ready()
        ? ', (SELECT COUNT(*) FROM tb_trade_comment c WHERE c.tr_idx = t.tr_idx AND c.tc_status = 1) AS inquiry_cnt'
        : ', 0 AS inquiry_cnt';

    $sql = "SELECT t.*, m.mb_nick,
           (SELECT ti_path FROM tb_trade_image
             WHERE tr_idx = t.tr_idx
             ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path
           {$inquiry_sel}
    FROM tb_trade t
    LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
    WHERE {$where_sql}
    ORDER BY " . trade_seller_listing_order_sql('t') . "
    LIMIT {$offset}, {$per}";

    $rs = db_query($sql);
    $rows = [];
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }

    return [
        'rows'       => $rows,
        'total'      => $total,
        'total_page' => $total_page,
        'page_no'    => $page_no,
        'per'        => $per,
        'has_more'   => $page_no < $total_page,
    ];
}

/**
 * @param array{
 *   type: string,
 *   item: string,
 *   keyword: string,
 *   hide_done: bool,
 *   mine_only: bool
 * } $filters
 */
function trade_board_query_string(array $filters, array $overrides = []): string
{
    $arr = array_merge([
        'type'   => $filters['type'],
        'item'   => $filters['item'],
        'q'      => $filters['keyword'],
        'active' => !empty($filters['hide_done']) ? 1 : '',
        'mine'   => !empty($filters['mine_only']) ? 1 : '',
    ], $overrides);
    $pairs = [];
    foreach ($arr as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $pairs[] = $k . '=' . urlencode((string) $v);
    }

    return $pairs ? ('?' . implode('&', $pairs)) : '';
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function trade_board_render_items_html(
    array $rows,
    bool $has_wish,
    ?array $me,
    array $user_wishes
): string {
    if ($rows === []) {
        return '';
    }

    $deal_status_labels = trade_board_deal_status_labels();
    ob_start();
    foreach ($rows as $row) {
        include __DIR__ . '/../include/trade_list_item.php';
    }

    return (string) ob_get_clean();
}
