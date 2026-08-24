<?php
/**
 * 전체 시세 — 관리자 등록 포켓몬명 × 거래게시판 판매완료 가격
 */

require_once __DIR__ . '/_function.php';
require_once __DIR__ . '/../trade/lib/_trade_price_suggest.php';

if (!function_exists('pokemon_market_table_ready')) {
    function pokemon_market_table_ready(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = db_table_exists('tb_pokemon_market');
        return $ready;
    }
}

if (!function_exists('pokemon_market_normalize_name')) {
    function pokemon_market_normalize_name(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '';
        }
        if (mb_strlen($name, 'UTF-8') > 100) {
            $name = mb_substr($name, 0, 100, 'UTF-8');
        }
        return $name;
    }
}

if (!function_exists('pokemon_market_fetch_active')) {
    /**
     * @return list<array<string, mixed>>
     */
    function pokemon_market_fetch_active(?string $q = null): array
    {
        if (!pokemon_market_table_ready()) {
            return [];
        }

        $where = ['pm_status = 1'];
        if ($q !== null && $q !== '') {
            $e = db_escape($q);
            $where[] = "pm_name LIKE '%{$e}%'";
        }
        $where_sql = implode(' AND ', $where);

        $rs = db_query("
            SELECT pm_idx, pm_name, pm_sort, pm_status, pm_created_at, pm_updated_at
            FROM tb_pokemon_market
            WHERE {$where_sql}
            ORDER BY pm_sort ASC, pm_idx ASC
        ");
        $rows = [];
        if (!$rs) {
            return $rows;
        }
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
        return $rows;
    }
}

if (!function_exists('pokemon_market_find_by_idx')) {
    /**
     * @return array<string, mixed>|null
     */
    function pokemon_market_find_by_idx(int $idx, bool $active_only = true): ?array
    {
        if (!pokemon_market_table_ready() || $idx < 1) {
            return null;
        }
        $st = $active_only ? ' AND pm_status = 1' : '';
        $rs = db_query("SELECT * FROM tb_pokemon_market WHERE pm_idx = {$idx}{$st} LIMIT 1");
        $row = db_assoc($rs);
        return $row ?: null;
    }
}

if (!function_exists('pokemon_market_card_where_sql')) {
    /**
     * 제목·카드명에 등록명 포함 · 등급 무관 · 카드 매물
     */
    function pokemon_market_card_where_sql(string $card_name): string
    {
        $e = db_escape($card_name);
        return "t.tr_status = 1
            AND t.tr_item_type = 'card'
            AND (t.tr_card_name LIKE '%{$e}%' OR t.tr_title LIKE '%{$e}%')";
    }
}

if (!function_exists('pokemon_market_has_paid_deals')) {
    function pokemon_market_has_paid_deals(string $where_sql): bool
    {
        if (!trade_price_suggest_payment_ready()) {
            return false;
        }
        $st = 2;
        $ff = 3;
        $n = (int) db_result("
            SELECT COUNT(*)
            FROM tb_trade_payment p
            INNER JOIN tb_trade t ON t.tr_idx = p.tr_idx
            WHERE {$where_sql}
              AND p.pay_status = {$st}
              AND p.pay_fulfill_status = {$ff}
              AND p.pay_amount > 0
        ");
        return $n > 0;
    }
}

if (!function_exists('pokemon_market_aggregate_prices')) {
    /**
     * 전체 판매완료 기준 집계 (체결가 우선, 없으면 거래완료 희망가)
     * @return array{count:int,min:int,max:int,avg:int,median:int,suggest:int,source:?string}
     */
    function pokemon_market_aggregate_prices(string $card_name): array
    {
        $empty = trade_price_suggest_summarize([]);
        $empty['source'] = null;

        $card_name = pokemon_market_normalize_name($card_name);
        if ($card_name === '') {
            return $empty;
        }

        $where = pokemon_market_card_where_sql($card_name);
        $prices = [];
        $source = null;

        if (pokemon_market_has_paid_deals($where)) {
            $source = 'paid';
            $st = 2;
            $ff = 3;
            $rs = db_query("
                SELECT p.pay_amount AS sold_price
                FROM tb_trade_payment p
                INNER JOIN tb_trade t ON t.tr_idx = p.tr_idx
                WHERE {$where}
                  AND p.pay_status = {$st}
                  AND p.pay_fulfill_status = {$ff}
                  AND p.pay_amount > 0
            ");
            if ($rs) {
                while ($r = db_assoc($rs)) {
                    $p = (int) ($r['sold_price'] ?? 0);
                    if ($p > 0) {
                        $prices[] = $p;
                    }
                }
            }
        } else {
            $source = 'listed';
            $rs = db_query("
                SELECT t.tr_price AS sold_price
                FROM tb_trade t
                WHERE {$where}
                  AND t.tr_deal_status = 3
                  AND t.tr_type = 'sell'
                  AND t.tr_price > 0
            ");
            if ($rs) {
                while ($r = db_assoc($rs)) {
                    $p = (int) ($r['sold_price'] ?? 0);
                    if ($p > 0) {
                        $prices[] = $p;
                    }
                }
            }
        }

        if ($prices === []) {
            return $empty;
        }

        // summarize 는 row 배열을 받으므로 가격만 맞춰 전달
        $rows = array_map(static fn ($p) => ['sold_price' => $p], $prices);
        $summary = trade_price_suggest_summarize($rows);
        $summary['source'] = $source;
        return $summary;
    }
}

if (!function_exists('pokemon_market_fetch_sold_rows')) {
    /**
     * 판매완료 목록(최근순) — 화면용
     * @return list<array<string, mixed>>
     */
    function pokemon_market_fetch_sold_rows(string $card_name, int $limit = 50): array
    {
        $card_name = pokemon_market_normalize_name($card_name);
        if ($card_name === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $where = pokemon_market_card_where_sql($card_name);

        if (pokemon_market_has_paid_deals($where)) {
            return trade_price_suggest_fetch_paid_rows($where, min(30, $limit));
        }

        return trade_price_suggest_fetch_listed_done_rows($where, min(30, $limit));
    }
}

if (!function_exists('pokemon_market_aggregate_asking')) {
    /**
     * 판매중 희망가 집계 (거래완료·예약 제외, 가격제안(0원) 제외)
     * @return array{count:int,min:int,max:int,avg:int,median:int,suggest:int}
     */
    function pokemon_market_aggregate_asking(string $card_name): array
    {
        $empty = trade_price_suggest_summarize([]);
        $card_name = pokemon_market_normalize_name($card_name);
        if ($card_name === '') {
            return $empty;
        }

        $where = pokemon_market_card_where_sql($card_name);
        $prices = [];
        $rs = db_query("
            SELECT t.tr_price AS sold_price
            FROM tb_trade t
            WHERE {$where}
              AND t.tr_type = 'sell'
              AND t.tr_deal_status IN (1, 2)
              AND t.tr_price > 0
        ");
        if ($rs) {
            while ($r = db_assoc($rs)) {
                $p = (int) ($r['sold_price'] ?? 0);
                if ($p > 0) {
                    $prices[] = $p;
                }
            }
        }

        if ($prices === []) {
            return $empty;
        }

        $rows = array_map(static fn ($p) => ['sold_price' => $p], $prices);
        return trade_price_suggest_summarize($rows);
    }
}

if (!function_exists('pokemon_market_fetch_asking_rows')) {
    /**
     * 판매중 매물 목록
     * @return list<array<string, mixed>>
     */
    function pokemon_market_fetch_asking_rows(string $card_name, int $limit = 50): array
    {
        $card_name = pokemon_market_normalize_name($card_name);
        if ($card_name === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $where = pokemon_market_card_where_sql($card_name);
        $sql = "
            SELECT
                t.tr_idx,
                t.tr_card_name,
                t.tr_set_name,
                t.tr_card_number,
                t.tr_language,
                t.tr_grade,
                t.tr_condition,
                t.tr_card_kind,
                t.tr_grading_company,
                t.tr_grading_score,
                t.tr_deal_status,
                t.tr_price AS sold_price,
                COALESCE(t.tr_bumped_at, t.tr_updated_at, t.tr_created_at) AS sold_at,
                'asking' AS source
            FROM tb_trade t
            WHERE {$where}
              AND t.tr_type = 'sell'
              AND t.tr_deal_status IN (1, 2)
              AND t.tr_price > 0
            ORDER BY t.tr_price ASC, sold_at DESC
            LIMIT {$limit}
        ";

        $rs = db_query($sql);
        $rows = [];
        if (!$rs) {
            return $rows;
        }
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
        return $rows;
    }
}

if (!function_exists('pokemon_market_format_asking_row')) {
    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    function pokemon_market_format_asking_row(array $r): array
    {
        $formatted = trade_price_suggest_format_row($r);
        $formatted['source'] = 'asking';
        $formatted['source_label'] = ((int) ($r['tr_deal_status'] ?? 1) === 2) ? '거래중' : '판매중';
        $formatted['thumb_path'] = trim((string) ($r['thumb_path'] ?? ''));
        return $formatted;
    }
}

if (!function_exists('pokemon_market_thumb_map')) {
    /**
     * 거래글 대표 이미지 경로 맵
     * @param list<int> $tr_idxs
     * @return array<int, string>
     */
    function pokemon_market_thumb_map(array $tr_idxs): array
    {
        $ids = [];
        foreach ($tr_idxs as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === [] || !db_table_exists('tb_trade_image')) {
            return [];
        }

        $in = implode(',', $ids);
        $rs = db_query("
            SELECT ti.tr_idx, ti.ti_path
            FROM tb_trade_image ti
            INNER JOIN (
                SELECT tr_idx, MIN(CONCAT(LPAD(ti_order, 6, '0'), '-', LPAD(ti_idx, 10, '0'))) AS ord_key
                FROM tb_trade_image
                WHERE tr_idx IN ({$in})
                GROUP BY tr_idx
            ) first_img
              ON first_img.tr_idx = ti.tr_idx
             AND CONCAT(LPAD(ti.ti_order, 6, '0'), '-', LPAD(ti.ti_idx, 10, '0')) = first_img.ord_key
        ");
        $map = [];
        if (!$rs) {
            return $map;
        }
        while ($r = db_assoc($rs)) {
            $path = trim((string) ($r['ti_path'] ?? ''));
            if ($path !== '') {
                $map[(int) $r['tr_idx']] = $path;
            }
        }
        return $map;
    }
}

if (!function_exists('pokemon_market_attach_thumbs')) {
    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    function pokemon_market_attach_thumbs(array $items): array
    {
        if ($items === []) {
            return $items;
        }
        $idxs = [];
        foreach ($items as $item) {
            $idxs[] = (int) ($item['tr_idx'] ?? 0);
        }
        $map = pokemon_market_thumb_map($idxs);
        foreach ($items as &$item) {
            $idx = (int) ($item['tr_idx'] ?? 0);
            $item['thumb_path'] = $map[$idx] ?? (string) ($item['thumb_path'] ?? '');
        }
        unset($item);
        return $items;
    }
}

if (!function_exists('pokemon_market_summary_for_name')) {
    /**
     * @return array<string, mixed>
     */
    function pokemon_market_summary_for_name(string $card_name, int $limit = 50): array
    {
        $summary = pokemon_market_aggregate_prices($card_name);
        $asking = pokemon_market_aggregate_asking($card_name);
        $raw = pokemon_market_fetch_sold_rows($card_name, $limit);
        $asking_raw = pokemon_market_fetch_asking_rows($card_name, $limit);

        $sold_items = pokemon_market_attach_thumbs(array_map('trade_price_suggest_format_row', $raw));
        $asking_items = pokemon_market_attach_thumbs(array_map('pokemon_market_format_asking_row', $asking_raw));

        return array_merge($summary, [
            'items'         => $sold_items,
            'asking_count'  => (int) ($asking['count'] ?? 0),
            'asking_min'    => (int) ($asking['min'] ?? 0),
            'asking_max'    => (int) ($asking['max'] ?? 0),
            'asking_avg'    => (int) ($asking['avg'] ?? 0),
            'asking_items'  => $asking_items,
        ]);
    }
}

if (!function_exists('pokemon_market_list_with_stats')) {
    /**
     * 등록 포켓몬 + 판매완료·판매중 시세 요약
     * @return list<array<string, mixed>>
     */
    function pokemon_market_list_with_stats(?string $q = null, string $sort = 'sort'): array
    {
        $rows = pokemon_market_fetch_active($q);
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['pm_name'] ?? '');
            $sold = pokemon_market_aggregate_prices($name);
            $asking = pokemon_market_aggregate_asking($name);
            $out[] = array_merge($row, [
                'sold_count'   => (int) ($sold['count'] ?? 0),
                'sold_min'     => (int) ($sold['min'] ?? 0),
                'sold_max'     => (int) ($sold['max'] ?? 0),
                'sold_avg'     => (int) ($sold['avg'] ?? 0),
                'sold_median'  => (int) ($sold['median'] ?? 0),
                'sold_source'  => $sold['source'] ?? null,
                'asking_count' => (int) ($asking['count'] ?? 0),
                'asking_min'   => (int) ($asking['min'] ?? 0),
                'asking_max'   => (int) ($asking['max'] ?? 0),
                'asking_avg'   => (int) ($asking['avg'] ?? 0),
            ]);
        }

        if ($sort === 'price_high') {
            usort($out, static function ($a, $b) {
                $aPrice = (int) $a['sold_avg'] > 0 ? (int) $a['sold_avg'] : (int) $a['asking_min'];
                $bPrice = (int) $b['sold_avg'] > 0 ? (int) $b['sold_avg'] : (int) $b['asking_min'];
                $cmp = $bPrice <=> $aPrice;
                return $cmp !== 0 ? $cmp : ((int) $a['pm_sort'] <=> (int) $b['pm_sort']);
            });
        } elseif ($sort === 'price_low') {
            usort($out, static function ($a, $b) {
                $aPrice = (int) $a['sold_avg'] > 0 ? (int) $a['sold_avg'] : (int) $a['asking_min'];
                $bPrice = (int) $b['sold_avg'] > 0 ? (int) $b['sold_avg'] : (int) $b['asking_min'];
                $aZero = $aPrice <= 0 ? 1 : 0;
                $bZero = $bPrice <= 0 ? 1 : 0;
                if ($aZero !== $bZero) {
                    return $aZero <=> $bZero;
                }
                $cmp = $aPrice <=> $bPrice;
                return $cmp !== 0 ? $cmp : ((int) $a['pm_sort'] <=> (int) $b['pm_sort']);
            });
        } elseif ($sort === 'deals') {
            usort($out, static function ($a, $b) {
                $cmp = ((int) $b['sold_count']) <=> ((int) $a['sold_count']);
                return $cmp !== 0 ? $cmp : ((int) $a['pm_sort'] <=> (int) $b['pm_sort']);
            });
        }

        return $out;
    }
}
