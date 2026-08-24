<?php
/**
 * 외부 쇼핑몰 미개봉 박스 재고·가격 피드
 * - 우선: DB(tb_card_price_product / tb_card_price_offer) — 관리자 화면에서 관리
 * - 없으면: data/card_price_offers.json
 * - 그래도 없으면: 내장 예시 데이터
 */

/** DB 카드시세 테이블 적용 여부 */
function card_price_tables_ready(): bool
{
    return db_table_exists('tb_card_price_product') && db_table_exists('tb_card_price_offer');
}

/** tb_card_price_offer.co_buy_enabled 적용 여부 (마이그레이션 전이면 false) */
function card_price_co_buy_enabled_supported(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!card_price_tables_ready()) {
        $cached = false;

        return false;
    }
    $chk = db_assoc(db_query("SHOW COLUMNS FROM tb_card_price_offer LIKE 'co_buy_enabled'"));
    $cached = (bool) $chk;

    return $cached;
}

/** tb_card_price_offer.co_type 적용 여부 (플랫폼·업체명, 마이그레이션 전이면 false) */
function card_price_co_type_supported(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!card_price_tables_ready()) {
        $cached = false;

        return false;
    }
    $chk = db_assoc(db_query("SHOW COLUMNS FROM tb_card_price_offer LIKE 'co_type'"));
    $cached = (bool) $chk;

    return $cached;
}

/**
 * 관리자 저장용 tb_card_price_offer INSERT 한 줄
 *
 * @param array<string, mixed> $o card_price_normalize_offer 결과
 */
function card_price_admin_offer_insert_sql(int $cp_idx, int $co_sort, array $o, bool $has_buy_en, bool $has_co_type): string
{
    $sid = db_escape((string) $o['site_id']);
    $sna = db_escape((string) $o['site_name']);
    $url = db_escape((string) $o['product_url']);
    $pr = (int) $o['price_won'];
    $stock_sql = 'NULL';
    if (array_key_exists('stock_qty', $o) && $o['stock_qty'] !== null) {
        $stock_sql = (string) (int) $o['stock_qty'];
    }
    $buy_en = (int) (($o['buy_enabled'] ?? 1) === 1 ? 1 : 0);
    $co_type_sql = "''";
    if ($has_co_type) {
        $ct = isset($o['co_type']) ? trim((string) $o['co_type']) : '';
        if (mb_strlen($ct, 'UTF-8') > 120) {
            $ct = mb_substr($ct, 0, 120, 'UTF-8');
        }
        $co_type_sql = "'" . db_escape($ct) . "'";
    }

    $cols = ['cp_idx', 'co_site_id', 'co_site_name', 'co_product_url', 'co_price_won', 'co_stock_qty'];
    $vals = [(string) $cp_idx, "'{$sid}'", "'{$sna}'", "'{$url}'", (string) $pr, $stock_sql];
    if ($has_buy_en) {
        $cols[] = 'co_buy_enabled';
        $vals[] = (string) $buy_en;
    }
    if ($has_co_type) {
        $cols[] = 'co_type';
        $vals[] = $co_type_sql;
    }
    $cols[] = 'co_sort';
    $vals[] = (string) $co_sort;

    return 'INSERT INTO tb_card_price_offer (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
}

/**
 * 관리자 입력용 site_id 슬러그 (비어 있으면 판매처명에서 생성)
 */
function card_price_make_site_id(string $site_name, string $preferred = ''): string
{
    $pref = trim($preferred);
    if ($pref !== '') {
        $pref = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $pref);
        $pref = strtolower(trim((string) $pref, '-'));
        if ($pref !== '') {
            return mb_substr($pref, 0, 64, 'UTF-8');
        }
    }
    $base = preg_replace('/[^a-zA-Z0-9_-]+/i', '-', mb_substr(trim($site_name), 0, 48, 'UTF-8'));
    $base = strtolower(trim((string) $base, '-'));

    return mb_substr($base !== '' ? $base : 'site', 0, 64, 'UTF-8');
}

/**
 * @return array{products: array<int, array<string, mixed>>, feed_updated_at: string}
 */
function card_price_feed_load_from_db(): array
{
    $products = [];
    $max_ts = null;

    $rs = db_query("
        SELECT cp_idx, cp_name, cp_set_label, cp_emoji, cp_image_path, cp_updated_at
        FROM tb_card_price_product
        WHERE cp_status = 1
        ORDER BY cp_sort DESC, cp_idx DESC
    ");
    if (!$rs) {
        return ['products' => [], 'feed_updated_at' => ''];
    }

    while ($p = db_assoc($rs)) {
        $cp_idx = (int) $p['cp_idx'];
        $pu = isset($p['cp_updated_at']) ? strtotime((string) $p['cp_updated_at']) : false;
        if ($pu !== false) {
            $max_ts = $max_ts === null ? $pu : max($max_ts, $pu);
        }

        $co_buy_sel = card_price_co_buy_enabled_supported() ? ', co_buy_enabled' : '';
        $co_type_sel = card_price_co_type_supported() ? ', co_type' : '';

        $ors = db_query("
            SELECT co_site_id, co_site_name, co_product_url, co_price_won, co_stock_qty, co_updated_at{$co_buy_sel}{$co_type_sel}
            FROM tb_card_price_offer
            WHERE cp_idx = {$cp_idx}
            ORDER BY co_sort DESC, co_idx ASC
        ");
        $offers = [];
        if ($ors) {
            while ($o = db_assoc($ors)) {
                $ou = isset($o['co_updated_at']) ? strtotime((string) $o['co_updated_at']) : false;
                if ($ou !== false) {
                    $max_ts = $max_ts === null ? $ou : max($max_ts, $ou);
                }
                $sn = trim((string) ($o['co_site_name'] ?? ''));
                $url = trim((string) ($o['co_product_url'] ?? ''));
                if ($sn === '' || !card_price_valid_http_url($url)) {
                    continue;
                }
                $sid = trim((string) ($o['co_site_id'] ?? ''));
                if ($sid === '') {
                    $sid = card_price_make_site_id($sn);
                }
                $stock_raw = $o['co_stock_qty'] ?? null;
                $stock = ($stock_raw === null || $stock_raw === '') ? null : (int) $stock_raw;

                $buy_ok = true;
                if (card_price_co_buy_enabled_supported()) {
                    $buy_ok = (int) ($o['co_buy_enabled'] ?? 0) === 1;
                    if (!$buy_ok) {
                        continue;
                    }
                }

                $co_type = '';
                if (card_price_co_type_supported()) {
                    $co_type = trim((string) ($o['co_type'] ?? ''));
                }

                $offers[] = [
                    'site_id' => $sid,
                    'site_name' => $sn,
                    'co_type' => $co_type,
                    'product_url' => $url,
                    'price_won' => (int) ($o['co_price_won'] ?? 0),
                    'stock_qty' => $stock,
                    'buy_enabled' => 1,
                    'fetched_at' => '',
                ];
            }
        }

        if (count($offers) === 0) {
            continue;
        }

        $emoji = trim((string) ($p['cp_emoji'] ?? ''));
        if ($emoji === '') {
            $emoji = '📦';
        }
        $img = trim((string) ($p['cp_image_path'] ?? ''));

        $products[] = [
            'id' => 'cp-' . $cp_idx,
            'name' => (string) $p['cp_name'],
            'set_label' => trim((string) ($p['cp_set_label'] ?? '')),
            'emoji' => $emoji,
            'image' => $img,
            'offers' => $offers,
        ];
    }

    $feed_updated_at = '';
    if ($max_ts !== null && $max_ts > 0) {
        $feed_updated_at = date('c', $max_ts);
    }

    return ['products' => $products, 'feed_updated_at' => $feed_updated_at];
}

/**
 * 거래 게시판 미개봉 박스명과 관리자 등록 상품명이 일치할 때 외부 시세 요약 (카드 시세 페이지 연동용)
 *
 * @return array{
 *   cp_idx: int,
 *   cp_name: string,
 *   cp_set_label: string,
 *   offer_cnt: int,
 *   min_price_won: ?int
 * }|null
 */
function card_price_lookup_box_market(string $box_name): ?array
{
    if (!card_price_tables_ready()) {
        return null;
    }
    $name = trim($box_name);
    if ($name === '') {
        return null;
    }
    $esc = db_escape($name);
    $buy_sql = '';
    if (card_price_co_buy_enabled_supported()) {
        $buy_sql = ' AND o.co_buy_enabled = 1';
    }
    $rs = db_query("
        SELECT p.cp_idx, p.cp_name, p.cp_set_label,
               MIN(NULLIF(o.co_price_won, 0)) AS min_price_won,
               COUNT(*) AS offer_cnt
        FROM tb_card_price_product p
        INNER JOIN tb_card_price_offer o ON o.cp_idx = p.cp_idx
        WHERE p.cp_status = 1
          AND p.cp_name = '{$esc}'
          AND TRIM(o.co_site_name) <> ''
          AND o.co_product_url LIKE 'http%'
          AND o.co_product_url NOT LIKE '% %'{$buy_sql}
        GROUP BY p.cp_idx, p.cp_name, p.cp_set_label
        LIMIT 1
    ");
    if (!$rs) {
        return null;
    }
    $row = db_assoc($rs);
    if (!$row || (int) ($row['offer_cnt'] ?? 0) < 1) {
        return null;
    }
    $min_raw = $row['min_price_won'] ?? null;
    $min_price = ($min_raw === null || $min_raw === '') ? null : (int) $min_raw;

    return [
        'cp_idx' => (int) $row['cp_idx'],
        'cp_name' => (string) $row['cp_name'],
        'cp_set_label' => trim((string) ($row['cp_set_label'] ?? '')),
        'offer_cnt' => (int) $row['offer_cnt'],
        'min_price_won' => $min_price !== null && $min_price > 0 ? $min_price : null,
    ];
}

/**
 * @return string Absolute path to offers JSON
 */
function card_price_offers_json_path(): string
{
    return dirname(__DIR__) . '/data/card_price_offers.json';
}

/**
 * 내장 예시 (연동 전·디자인 확인용)
 *
 * @return array<int, array<string, mixed>>
 */
function card_price_demo_products(): array
{
    return [
        [
            'id' => 'demo-sv8',
            'name' => '포켓몬 카드게임 확장팩 「초전설의 빛」',
            'set_label' => 'SV8',
            'emoji' => '📦',
            'image' => '',
            'offers' => [
                [
                    'site_id' => 'demo-a',
                    'site_name' => '예시몰 Alpha',
                    'product_url' => 'https://example.com/products/demo-alpha-sv8',
                    'price_won' => 59800,
                    'stock_qty' => 24,
                    'buy_enabled' => 1,
                    'fetched_at' => '',
                ],
                [
                    'site_id' => 'demo-b',
                    'site_name' => '예시몰 Beta',
                    'product_url' => 'https://example.com/products/demo-beta-sv8',
                    'price_won' => 57900,
                    'stock_qty' => 3,
                    'buy_enabled' => 1,
                    'fetched_at' => '',
                ],
            ],
        ],
        [
            'id' => 'demo-m151',
            'name' => '포켓몬 카드게임 「포켓몬 카드151」 부스터',
            'set_label' => 'SV2a',
            'emoji' => '📦',
            'image' => '',
            'offers' => [
                [
                    'site_id' => 'demo-a',
                    'site_name' => '예시몰 Alpha',
                    'product_url' => 'https://example.com/products/demo-alpha-151',
                    'price_won' => 0,
                    'stock_qty' => 0,
                    'buy_enabled' => 0,
                    'fetched_at' => '',
                ],
                [
                    'site_id' => 'demo-c',
                    'site_name' => '예시몰 Gamma',
                    'product_url' => 'https://example.com/products/demo-gamma-151',
                    'price_won' => 72000,
                    'stock_qty' => null,
                    'buy_enabled' => 1,
                    'fetched_at' => '',
                ],
            ],
        ],
        [
            'id' => 'demo-surging-sparks',
            'name' => '포켓몬 카드게임 「내러티브의 시작 레다의별」 부스터',
            'set_label' => 'SV11',
            'emoji' => '📦',
            'image' => '',
            'offers' => [
                [
                    'site_id' => 'demo-b',
                    'site_name' => '예시몰 Beta',
                    'product_url' => 'https://example.com/products/demo-beta-narrative',
                    'price_won' => 49500,
                    'stock_qty' => 156,
                    'buy_enabled' => 1,
                    'fetched_at' => '',
                ],
            ],
        ],
    ];
}

/**
 * @param mixed $v
 */
function card_price_int_or_null($v): ?int
{
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_numeric($v)) {
        return null;
    }
    return (int) $v;
}

/**
 * @param mixed $url
 */
function card_price_valid_http_url($url): bool
{
    if (!is_string($url) || $url === '') {
        return false;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    if (preg_match('#\s#', $url)) {
        return false;
    }
    return true;
}

/**
 * 판매처 표시명: 앞 3글자(문자 단위)를 마스킹
 */
function card_price_site_display_masked(string $site_name): string
{
    $name = trim($site_name);
    if ($name === '') {
        return '***';
    }
    $len = mb_strlen($name, 'UTF-8');
    if ($len <= 3) {
        return '***';
    }

    return '***' . mb_substr($name, 3, null, 'UTF-8');
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>|null
 */
function card_price_normalize_offer(array $raw): ?array
{
    $site_name = isset($raw['site_name']) ? trim((string) $raw['site_name']) : '';
    $product_url = isset($raw['product_url']) ? trim((string) $raw['product_url']) : '';
    $pref = isset($raw['site_id']) ? trim((string) $raw['site_id']) : '';
    if ($site_name === '' && $pref !== '') {
        $site_name = $pref;
    }
    if ($site_name === '') {
        $site_name = '미입력';
    }
    if (mb_strlen($site_name) > 120) {
        $site_name = mb_substr($site_name, 0, 120, 'UTF-8');
    }
    if ($product_url !== '' && preg_match('#\s#', $product_url)) {
        return null;
    }
    if (mb_strlen($product_url) > 500) {
        return null;
    }
    $site_id = card_price_make_site_id($site_name, $pref);
    $price = isset($raw['price_won']) ? (int) $raw['price_won'] : 0;
    if ($price < 0) {
        $price = 0;
    }
    $buy_enabled = 1;
    if (array_key_exists('buy_enabled', $raw)) {
        $buy_enabled = (int) ($raw['buy_enabled'] ?? 0) === 1 ? 1 : 0;
    }
    $co_type_raw = isset($raw['co_type']) ? trim((string) $raw['co_type']) : '';
    if (mb_strlen($co_type_raw, 'UTF-8') > 120) {
        $co_type_raw = mb_substr($co_type_raw, 0, 120, 'UTF-8');
    }

    return [
        'site_id' => $site_id,
        'site_name' => $site_name,
        'co_type' => $co_type_raw,
        'product_url' => $product_url,
        'price_won' => $price,
        'stock_qty' => array_key_exists('stock_qty', $raw) ? card_price_int_or_null($raw['stock_qty']) : null,
        'buy_enabled' => $buy_enabled,
        'fetched_at' => isset($raw['fetched_at']) ? trim((string) $raw['fetched_at']) : '',
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>|null
 */
function card_price_normalize_product(array $raw): ?array
{
    $id = isset($raw['id']) ? trim((string) $raw['id']) : '';
    $name = isset($raw['name']) ? trim((string) $raw['name']) : '';
    if ($id === '' || $name === '') {
        return null;
    }
    $offers_in = isset($raw['offers']) && is_array($raw['offers']) ? $raw['offers'] : [];
    $offers = [];
    foreach ($offers_in as $o) {
        if (!is_array($o)) {
            continue;
        }
        $norm = card_price_normalize_offer($o);
        if ($norm !== null) {
            $offers[] = $norm;
        }
    }
    if (count($offers) === 0) {
        return null;
    }
    $img = isset($raw['image']) ? trim((string) $raw['image']) : '';
    return [
        'id' => $id,
        'name' => $name,
        'set_label' => isset($raw['set_label']) ? trim((string) $raw['set_label']) : '',
        'emoji' => isset($raw['emoji']) ? trim((string) $raw['emoji']) : '📦',
        'image' => $img,
        'offers' => $offers,
    ];
}

/**
 * @param array<int, mixed> $list
 * @return array<int, array<string, mixed>>
 */
function card_price_normalize_products(array $list): array
{
    $out = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $p = card_price_normalize_product($item);
        if ($p !== null) {
            $out[] = $p;
        }
    }
    return $out;
}

/**
 * @return array{
 *   products: array<int, array<string, mixed>>,
 *   feed_updated_at: string,
 *   from_file: bool,
 *   demo_fallback: bool,
 *   from_database: bool
 * }
 */
function card_price_feed_load(): array
{
    if (card_price_tables_ready()) {
        $db = card_price_feed_load_from_db();

        return [
            'products' => $db['products'],
            'feed_updated_at' => $db['feed_updated_at'],
            'from_file' => false,
            'demo_fallback' => false,
            'from_database' => true,
        ];
    }

    $path = card_price_offers_json_path();
    $feed_updated_at = '';
    $from_file = false;
    $products = [];

    if (is_readable($path)) {
        $raw_json = @file_get_contents($path);
        if ($raw_json !== false && $raw_json !== '') {
            $decoded = json_decode($raw_json, true);
            if (is_array($decoded)) {
                $feed_updated_at = isset($decoded['updated_at']) ? trim((string) $decoded['updated_at']) : '';
                $plist = isset($decoded['products']) && is_array($decoded['products']) ? $decoded['products'] : [];
                $products = card_price_normalize_products($plist);
                $from_file = true;
            }
        }
    }

    $demo_fallback = false;
    if (count($products) === 0) {
        $products = card_price_normalize_products(card_price_demo_products());
        $demo_fallback = true;
    }

    return [
        'products' => $products,
        'feed_updated_at' => $feed_updated_at,
        'from_file' => $from_file,
        'demo_fallback' => $demo_fallback,
        'from_database' => false,
    ];
}

/**
 * @param array<int, array<string, mixed>> $products
 * @return array<string, array{site_id:string, site_name:string}>
 */
function card_price_collect_sources(array $products): array
{
    $map = [];
    foreach ($products as $p) {
        foreach ($p['offers'] ?? [] as $o) {
            $sid = (string) ($o['site_id'] ?? '');
            $sn = (string) ($o['site_name'] ?? '');
            if ($sid !== '' && $sn !== '') {
                $map[$sid] = ['site_id' => $sid, 'site_name' => $sn];
            }
        }
    }
    ksort($map);
    return $map;
}

/**
 * @param array<string, mixed> $product
 */
function card_price_product_matches_query(array $product, string $q): bool
{
    if ($q === '') {
        return true;
    }
    $hay = mb_strtolower(
        ($product['name'] ?? '') . ' ' . ($product['set_label'] ?? ''),
        'UTF-8'
    );
    return mb_strpos($hay, $q, 0, 'UTF-8') !== false;
}
