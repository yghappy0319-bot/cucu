<?php
/**
 * 거래글 등록 — 판매완료 기반 희망가 추천
 */

if (!function_exists('trade_price_suggest_market_columns_ready')) {
    function trade_price_suggest_market_columns_ready(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade LIKE 'tr_set_name'"));
        return $ready;
    }
}

if (!function_exists('trade_price_suggest_payment_ready')) {
    function trade_price_suggest_payment_ready(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (!db_table_exists('tb_trade_payment')) {
            $ready = false;
            return $ready;
        }
        $has_fulfill = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_payment LIKE 'pay_fulfill_status'"));
        $ready = $has_fulfill;
        return $ready;
    }
}

if (!function_exists('trade_price_suggest_normalize_input')) {
    /**
     * @return array<string, mixed>
     */
    function trade_price_suggest_normalize_input(array $in): array
    {
        $card_kind = trim((string) ($in['card_kind'] ?? 'single'));
        if ($card_kind !== 'graded') {
            $card_kind = 'single';
        }

        return [
            'card_name'         => trim((string) ($in['card_name'] ?? '')),
            'set_name'          => trim((string) ($in['set_name'] ?? '')),
            'card_number'       => trim((string) ($in['card_number'] ?? '')),
            'language'          => trim((string) ($in['language'] ?? '')),
            'grade'             => trim((string) ($in['grade'] ?? '')),
            'condition'         => trim((string) ($in['condition'] ?? '')),
            'card_kind'         => $card_kind,
            'grading_company'   => trim((string) ($in['grading_company'] ?? '')),
            'grading_score'     => trim((string) ($in['grading_score'] ?? '')),
        ];
    }
}

if (!function_exists('trade_price_suggest_is_ready_input')) {
    /**
     * 추천가 패널을 띄울 최소 입력 조건
     */
    function trade_price_suggest_is_ready_input(array $q): bool
    {
        if ($q['card_name'] === '' || $q['set_name'] === '' || $q['card_number'] === '' || $q['language'] === '') {
            return false;
        }
        if ($q['grade'] === '') {
            return false;
        }
        if ($q['card_kind'] === 'graded') {
            return $q['grading_company'] !== '' && $q['grading_score'] !== '';
        }
        return $q['condition'] !== '' && in_array($q['condition'], ['S', 'A', 'B', 'C'], true);
    }
}

if (!function_exists('trade_price_suggest_identity_sql')) {
    /**
     * @return array{0: string, 1: string} where sql fragment + match_level label
     */
    function trade_price_suggest_identity_sql(array $q, string $level): array
    {
        $parts = [
            "t.tr_item_type = 'card'",
            "t.tr_status = 1",
            "t.tr_card_name = '" . db_escape($q['card_name']) . "'",
        ];

        if ($q['card_kind'] === 'graded') {
            $parts[] = "t.tr_card_kind = 'graded'";
        } else {
            $parts[] = "(t.tr_card_kind = 'single' OR t.tr_card_kind IS NULL OR t.tr_card_kind = '')";
        }

        if ($level === 'exact' || $level === 'identity') {
            $parts[] = "t.tr_set_name = '" . db_escape($q['set_name']) . "'";
            $parts[] = "t.tr_card_number = '" . db_escape($q['card_number']) . "'";
            $parts[] = "t.tr_language = '" . db_escape($q['language']) . "'";
        }

        if ($level === 'exact') {
            if ($q['card_kind'] === 'graded') {
                $parts[] = "t.tr_grading_company = '" . db_escape($q['grading_company']) . "'";
                $parts[] = "t.tr_grading_score = '" . db_escape($q['grading_score']) . "'";
            } else {
                $parts[] = "t.tr_condition = '" . db_escape($q['condition']) . "'";
            }
            if ($q['grade'] !== '') {
                $parts[] = "t.tr_grade = '" . db_escape($q['grade']) . "'";
            }
        } elseif ($level === 'identity' && $q['card_kind'] === 'graded') {
            $parts[] = "t.tr_grading_company = '" . db_escape($q['grading_company']) . "'";
            $parts[] = "t.tr_grading_score = '" . db_escape($q['grading_score']) . "'";
        }

        return [implode(' AND ', $parts), $level];
    }
}

if (!function_exists('trade_price_suggest_fetch_paid_rows')) {
    /**
     * 실제 결제·구매확정 완료 건
     * @return list<array<string, mixed>>
     */
    function trade_price_suggest_fetch_paid_rows(string $where_sql, int $limit = 12): array
    {
        if (!trade_price_suggest_payment_ready()) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $st = 2; // CONFIRMED
        $ff = 3; // PURCHASE

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
                p.pay_amount AS sold_price,
                COALESCE(p.pay_buyer_confirmed_at, p.pay_confirmed_at, p.pay_created_at) AS sold_at,
                'paid' AS source
            FROM tb_trade_payment p
            INNER JOIN tb_trade t ON t.tr_idx = p.tr_idx
            WHERE {$where_sql}
              AND p.pay_status = {$st}
              AND p.pay_fulfill_status = {$ff}
              AND p.pay_amount > 0
            ORDER BY sold_at DESC
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

if (!function_exists('trade_price_suggest_fetch_listed_done_rows')) {
    /**
     * 결제 없이 거래완료로만 표시된 글 (희망가 참고용)
     * @return list<array<string, mixed>>
     */
    function trade_price_suggest_fetch_listed_done_rows(string $where_sql, int $limit = 12): array
    {
        $limit = max(1, min(30, $limit));
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
                t.tr_price AS sold_price,
                COALESCE(t.tr_updated_at, t.tr_created_at) AS sold_at,
                'listed' AS source
            FROM tb_trade t
            WHERE {$where_sql}
              AND t.tr_deal_status = 3
              AND t.tr_type = 'sell'
              AND t.tr_price > 0
            ORDER BY sold_at DESC
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

if (!function_exists('trade_price_suggest_summarize')) {
    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    function trade_price_suggest_summarize(array $rows): array
    {
        $prices = [];
        foreach ($rows as $r) {
            $p = (int) ($r['sold_price'] ?? 0);
            if ($p > 0) {
                $prices[] = $p;
            }
        }
        if (empty($prices)) {
            return [
                'count'   => 0,
                'min'     => 0,
                'max'     => 0,
                'avg'     => 0,
                'median'  => 0,
                'suggest' => 0,
            ];
        }

        sort($prices, SORT_NUMERIC);
        $n = count($prices);
        $sum = array_sum($prices);
        $avg = (int) round($sum / $n);
        if ($n % 2 === 1) {
            $median = $prices[(int) floor($n / 2)];
        } else {
            $median = (int) round(($prices[$n / 2 - 1] + $prices[$n / 2]) / 2);
        }

        // 추천가: 중앙값 우선, 500원 단위 반올림
        $suggest = (int) (round($median / 500) * 500);
        if ($suggest < 0) {
            $suggest = 0;
        }

        return [
            'count'   => $n,
            'min'     => $prices[0],
            'max'     => $prices[$n - 1],
            'avg'     => $avg,
            'median'  => $median,
            'suggest' => $suggest,
        ];
    }
}

if (!function_exists('trade_price_suggest_format_row')) {
    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    function trade_price_suggest_format_row(array $r): array
    {
        $sold_at = (string) ($r['sold_at'] ?? '');
        $sold_at_label = '';
        if ($sold_at !== '' && $sold_at !== '0000-00-00 00:00:00') {
            $ts = strtotime($sold_at);
            if ($ts) {
                $sold_at_label = date('Y.m.d', $ts);
            }
        }

        $meta = [];
        if (!empty($r['tr_set_name'])) {
            $meta[] = (string) $r['tr_set_name'];
        }
        if (!empty($r['tr_card_number'])) {
            $meta[] = (string) $r['tr_card_number'];
        }
        if (($r['tr_card_kind'] ?? '') === 'graded') {
            $g = trim((string) ($r['tr_grading_company'] ?? '') . ' ' . (string) ($r['tr_grading_score'] ?? ''));
            if ($g !== '') {
                $meta[] = $g;
            }
        } elseif (!empty($r['tr_condition'])) {
            $meta[] = '상태 ' . (string) $r['tr_condition'];
        }
        if (!empty($r['tr_grade'])) {
            $meta[] = (string) $r['tr_grade'];
        }

        return [
            'tr_idx'      => (int) ($r['tr_idx'] ?? 0),
            'card_name'   => (string) ($r['tr_card_name'] ?? ''),
            'sold_price'  => (int) ($r['sold_price'] ?? 0),
            'sold_at'     => $sold_at_label,
            'source'      => (string) ($r['source'] ?? 'paid'),
            'source_label'=> (($r['source'] ?? '') === 'listed') ? '거래완료(희망가)' : '체결가',
            'meta'        => implode(' · ', $meta),
            'url'         => '/trade/trade_view.php?idx=' . (int) ($r['tr_idx'] ?? 0),
        ];
    }
}

if (!function_exists('trade_price_suggest_lookup')) {
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    function trade_price_suggest_lookup(array $input): array
    {
        $q = trade_price_suggest_normalize_input($input);

        if (!trade_price_suggest_market_columns_ready()) {
            return [
                'ok'      => false,
                'error'   => '시세용 상품 정보 컬럼이 아직 준비되지 않았습니다.',
                'ready'   => false,
                'items'   => [],
                'summary' => trade_price_suggest_summarize([]),
            ];
        }

        if (!trade_price_suggest_is_ready_input($q)) {
            return [
                'ok'      => true,
                'ready'   => false,
                'message' => '상품명·세트명·카드번호·언어·레어도·상태(또는 슬랩)를 먼저 입력해 주세요.',
                'match'   => null,
                'items'   => [],
                'summary' => trade_price_suggest_summarize([]),
            ];
        }

        $levels = ['exact', 'identity', 'name'];
        $used_level = null;
        $raw = [];
        $used_source = 'paid';

        foreach ($levels as $level) {
            [$where] = trade_price_suggest_identity_sql($q, $level);
            $paid = trade_price_suggest_fetch_paid_rows($where, 12);
            if (!empty($paid)) {
                $raw = $paid;
                $used_level = $level;
                $used_source = 'paid';
                break;
            }
        }

        // 결제 체결 데이터가 없으면 거래완료 희망가로 보조
        if (empty($raw)) {
            foreach ($levels as $level) {
                [$where] = trade_price_suggest_identity_sql($q, $level);
                $listed = trade_price_suggest_fetch_listed_done_rows($where, 12);
                if (!empty($listed)) {
                    $raw = $listed;
                    $used_level = $level;
                    $used_source = 'listed';
                    break;
                }
            }
        }

        $items = array_map('trade_price_suggest_format_row', $raw);
        $summary = trade_price_suggest_summarize($raw);

        $match_labels = [
            'exact'    => '동일 조건',
            'identity' => '같은 카드(상태 제외)',
            'name'     => '같은 카드명',
        ];

        $message = '';
        if (empty($items)) {
            $message = '아직 같은 조건의 판매완료 거래가 없어요. 데이터가 쌓이면 추천가가 표시됩니다.';
        } elseif ($used_source === 'listed') {
            $message = '결제 체결 데이터가 없어, 거래완료로 표시된 희망가를 참고로 보여드려요.';
        } elseif ($used_level === 'identity') {
            $message = '상태가 다른 같은 카드의 체결 내역을 참고로 보여드려요.';
        } elseif ($used_level === 'name') {
            $message = '세트·번호가 다른 같은 카드명의 체결 내역을 참고로 보여드려요.';
        }

        return [
            'ok'           => true,
            'ready'        => true,
            'match'        => $used_level,
            'match_label'  => $used_level ? ($match_labels[$used_level] ?? $used_level) : null,
            'source'       => empty($items) ? null : $used_source,
            'message'      => $message,
            'items'        => $items,
            'summary'      => $summary,
            'query'        => [
                'card_name'   => $q['card_name'],
                'set_name'    => $q['set_name'],
                'card_number' => $q['card_number'],
                'language'    => $q['language'],
                'grade'       => $q['grade'],
                'condition'   => $q['condition'],
                'card_kind'   => $q['card_kind'],
            ],
        ];
    }
}
