<?php
/**
 * 스니덩크(SNKRDUNK) 포켓몬 미개봉 박스 최저가 수집
 *
 * 목록: GET /en/v1/trading-cards?brandId=pokemon&categoryId=14
 * 상세(엔화): GET /v1/apparels/{id}
 * 원화: 수집 시점 JPY→KRW 환율 × 엔화 최저가
 */

const SNKRDUNK_BOX_BRAND_ID    = 'pokemon';
const SNKRDUNK_BOX_CATEGORY_ID = 14; // Trading Cards (Box & Packs)
const SNKRDUNK_BOX_LIST_URL    = 'https://snkrdunk.com/en/v1/trading-cards';
const SNKRDUNK_BOX_DETAIL_URL  = 'https://snkrdunk.com/v1/apparels';
const SNKRDUNK_BOX_UA          = 'Mozilla/5.0 (compatible; PokazoneBot/1.0; +https://pokazone.kr)';
/** 환율 API 실패 시 최후 폴백 (대략치) */
const SNKRDUNK_BOX_FX_FALLBACK = 9.1;

function snkrdunk_box_tables_ready(): bool
{
    return db_table_exists('tb_snkrdunk_box');
}

function snkrdunk_box_name_ko_supported(): bool
{
    static $supported = null;
    if ($supported !== null) {
        return $supported;
    }
    if (!snkrdunk_box_tables_ready()) {
        return $supported = false;
    }

    $rs = db_query("SHOW COLUMNS FROM tb_snkrdunk_box LIKE 'sb_name_ko'");
    return $supported = (bool) ($rs && db_assoc($rs));
}

function snkrdunk_box_log_table_ready(): bool
{
    return db_table_exists('tb_snkrdunk_box_price_log');
}

/**
 * HTTP GET JSON
 *
 * @return array{ok: bool, status: int, data: mixed, error?: string, raw?: string}
 */
function snkrdunk_box_http_get_json(string $url, int $timeout = 25): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'curl_init failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Accept-Language: ja,en;q=0.8',
            'Referer: https://snkrdunk.com/en/brands/pokemon/trading-cards?categoryId=14',
            'Origin: https://snkrdunk.com',
        ],
        CURLOPT_USERAGENT      => SNKRDUNK_BOX_UA,
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $err !== '' ? $err : 'curl error'];
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        return [
            'ok'     => false,
            'status' => $status,
            'data'   => null,
            'error'  => 'invalid json',
            'raw'    => mb_substr((string) $body, 0, 200),
        ];
    }

    if ($status < 200 || $status >= 300) {
        return [
            'ok'     => false,
            'status' => $status,
            'data'   => $data,
            'error'  => 'http ' . $status,
        ];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data];
}

/**
 * JPY → KRW 환율 (1엔당 원화)
 *
 * @return array{ok: bool, rate: float, source: string, error?: string}
 */
function snkrdunk_box_fetch_jpy_krw_rate(): array
{
    // 1) Frankfurter (ECB, 키 불필요)
    $r = snkrdunk_box_http_get_json('https://api.frankfurter.app/latest?from=JPY&to=KRW', 15);
    if (!empty($r['ok']) && is_array($r['data'])) {
        $rate = (float) ($r['data']['rates']['KRW'] ?? 0);
        if ($rate > 0) {
            return ['ok' => true, 'rate' => $rate, 'source' => 'frankfurter'];
        }
    }

    // 2) open.er-api.com
    $r2 = snkrdunk_box_http_get_json('https://open.er-api.com/v6/latest/JPY', 15);
    if (!empty($r2['ok']) && is_array($r2['data'])) {
        $rate = (float) ($r2['data']['rates']['KRW'] ?? 0);
        if ($rate > 0) {
            return ['ok' => true, 'rate' => $rate, 'source' => 'open.er-api'];
        }
    }

    return [
        'ok'     => true,
        'rate'   => (float) SNKRDUNK_BOX_FX_FALLBACK,
        'source' => 'fallback',
        'error'  => 'fx api failed; used fallback ' . SNKRDUNK_BOX_FX_FALLBACK,
    ];
}

function snkrdunk_box_jpy_to_krw(int $jpy, float $rate): int
{
    if ($jpy <= 0 || $rate <= 0) {
        return 0;
    }

    return (int) max(0, (int) round($jpy * $rate));
}

/**
 * 영문 목록 1페이지
 *
 * @return array{ok: bool, items: list<array<string,mixed>>, error?: string}
 */
function snkrdunk_box_fetch_list_page(int $page = 1, int $per_page = 50, string $order = 'popular'): array
{
    $page     = max(1, $page);
    $per_page = max(1, min(50, $per_page));
    $qs       = http_build_query([
        'brandId'    => SNKRDUNK_BOX_BRAND_ID,
        'categoryId' => SNKRDUNK_BOX_CATEGORY_ID,
        'page'       => $page,
        'perPage'    => $per_page,
        'order'      => $order,
    ]);
    $url = SNKRDUNK_BOX_LIST_URL . '?' . $qs;
    $r   = snkrdunk_box_http_get_json($url);
    if (empty($r['ok']) || !is_array($r['data'])) {
        return [
            'ok'    => false,
            'items' => [],
            'error' => (string) ($r['error'] ?? 'list fetch failed'),
        ];
    }

    $cards = $r['data']['tradingCards'] ?? [];
    if (!is_array($cards)) {
        $cards = [];
    }

    $items = [];
    foreach ($cards as $c) {
        if (!is_array($c)) {
            continue;
        }
        $id = (int) ($c['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $items[] = [
            'id'            => $id,
            'productNumber' => (string) ($c['productNumber'] ?? ''),
            'name'          => (string) ($c['name'] ?? ''),
            'thumbnailUrl'  => (string) ($c['thumbnailUrl'] ?? ''),
            'releasedAt'    => (string) ($c['releasedAt'] ?? ''),
            // EN API는 원화 환산가를 내려줌(참고용). 저장 원가는 JP 상세 엔화×환율
            'minPriceKrwSite' => max(0, (int) ($c['minPrice'] ?? 0)),
        ];
    }

    return ['ok' => true, 'items' => $items];
}

/**
 * 일본 상품 상세 (엔화 최저가)
 *
 * @return array{ok: bool, item?: array<string,mixed>, error?: string}
 */
function snkrdunk_box_fetch_detail(int $product_id): array
{
    if ($product_id < 1) {
        return ['ok' => false, 'error' => 'invalid product id'];
    }

    $url = SNKRDUNK_BOX_DETAIL_URL . '/' . $product_id;
    $r   = snkrdunk_box_http_get_json($url);
    if (empty($r['ok']) || !is_array($r['data'])) {
        return ['ok' => false, 'error' => (string) ($r['error'] ?? 'detail fetch failed')];
    }

    $d = $r['data'];
    $released_raw = trim((string) ($d['releasedAt'] ?? ''));
    $released_at  = null;
    if ($released_raw !== '') {
        $ts = strtotime($released_raw);
        if ($ts !== false) {
            $released_at = date('Y-m-d H:i:s', $ts);
        }
    }

    $thumb = '';
    if (!empty($d['primaryMedia']['imageUrl'])) {
        $thumb = (string) $d['primaryMedia']['imageUrl'];
    }

    return [
        'ok'   => true,
        'item' => [
            'id'               => (int) ($d['id'] ?? $product_id),
            'productNumber'    => (string) ($d['productNumber'] ?? ''),
            'nameEn'           => (string) ($d['name'] ?? ''),
            'nameJa'           => (string) ($d['localizedName'] ?? ''),
            'colorName'        => (string) ($d['colorName'] ?? ''),
            'colorLocalized'   => (string) ($d['colorLocalizedName'] ?? ''),
            'thumbnailUrl'     => $thumb,
            'regularPriceJpy'  => max(0, (int) ($d['regularPrice'] ?? 0)),
            'minPriceJpy'      => max(0, (int) ($d['minPrice'] ?? 0)),
            'listingCount'     => max(0, (int) ($d['listingCount'] ?? 0)),
            'releasedAt'       => $released_at,
        ],
    ];
}

/**
 * 미개봉 박스 여부 (JP colorName / 영문명)
 */
function snkrdunk_box_is_unopened_box(string $color_name, string $color_localized, string $name_en = ''): bool
{
    $color = trim($color_name);
    if ($color !== '' && preg_match('/^Box\b/i', $color)) {
        return true;
    }
    if ($color_localized !== '' && mb_strpos($color_localized, 'ボックス') !== false) {
        return true;
    }
    // 상세 없이 목록만 볼 때 보조 힌트
    if ($name_en !== '' && preg_match('/\bBox\b/i', $name_en)) {
        return true;
    }

    return false;
}

/**
 * 목록 후보가 박스일 가능성 (상세 호출 전 1차 필터)
 */
function snkrdunk_box_list_name_looks_like_box(string $name_en): bool
{
    $n = trim($name_en);
    if ($n === '') {
        return false;
    }
    // "… Box", "Special Box", "Box Set" 등
    if (preg_match('/\bBox\b/i', $n)) {
        return true;
    }
    if (mb_strpos($n, 'ボックス') !== false) {
        return true;
    }

    return false;
}

/**
 * UPSERT + (가격 변동 시) 이력
 *
 * @param array<string,mixed> $row
 * @return array{ok: bool, changed: bool, error?: string}
 */
function snkrdunk_box_upsert(array $row, float $fx_rate): array
{
    if (!snkrdunk_box_tables_ready()) {
        return ['ok' => false, 'changed' => false, 'error' => 'table missing'];
    }

    $product_id = (int) ($row['product_id'] ?? 0);
    if ($product_id < 1) {
        return ['ok' => false, 'changed' => false, 'error' => 'invalid product_id'];
    }

    $min_jpy = max(0, (int) ($row['min_price_jpy'] ?? 0));
    $min_krw = snkrdunk_box_jpy_to_krw($min_jpy, $fx_rate);
    $listing = max(0, (int) ($row['listing_count'] ?? 0));
    $reg_jpy = max(0, (int) ($row['regular_price_jpy'] ?? 0));
    $cat_id  = (int) ($row['category_id'] ?? SNKRDUNK_BOX_CATEGORY_ID);
    $status  = !empty($row['status']) ? 1 : 0;

    $product_number = db_escape((string) ($row['product_number'] ?? ''));
    $name_en        = db_escape(mb_substr((string) ($row['name_en'] ?? ''), 0, 255));
    $name_ja        = db_escape(mb_substr((string) ($row['name_ja'] ?? ''), 0, 255));
    $color_name     = db_escape(mb_substr((string) ($row['color_name'] ?? ''), 0, 80));
    $thumb          = db_escape(mb_substr((string) ($row['thumbnail_url'] ?? ''), 0, 500));
    $product_url    = db_escape(mb_substr((string) ($row['product_url'] ?? ''), 0, 500));
    $brand_id       = db_escape((string) ($row['brand_id'] ?? SNKRDUNK_BOX_BRAND_ID));
    $fx_sql         = number_format($fx_rate, 6, '.', '');

    $released_sql = 'NULL';
    if (!empty($row['released_at'])) {
        $released_sql = "'" . db_escape((string) $row['released_at']) . "'";
    }

    $prev = db_assoc(db_query("
        SELECT sb_min_price_jpy, sb_listing_count
        FROM tb_snkrdunk_box
        WHERE sb_product_id = {$product_id}
        LIMIT 1
    "));

    $ok = db_query("
        INSERT INTO tb_snkrdunk_box (
            sb_product_id, sb_product_number, sb_name_en, sb_name_ja, sb_color_name,
            sb_thumbnail_url, sb_product_url,
            sb_regular_price_jpy, sb_min_price_jpy, sb_min_price_krw, sb_fx_rate,
            sb_listing_count, sb_released_at, sb_brand_id, sb_category_id,
            sb_status, sb_fetched_at
        ) VALUES (
            {$product_id}, '{$product_number}', '{$name_en}', '{$name_ja}', '{$color_name}',
            '{$thumb}', '{$product_url}',
            {$reg_jpy}, {$min_jpy}, {$min_krw}, {$fx_sql},
            {$listing}, {$released_sql}, '{$brand_id}', {$cat_id},
            {$status}, NOW()
        )
        ON DUPLICATE KEY UPDATE
            sb_product_number     = VALUES(sb_product_number),
            sb_name_en            = VALUES(sb_name_en),
            sb_name_ja            = VALUES(sb_name_ja),
            sb_color_name         = VALUES(sb_color_name),
            sb_thumbnail_url      = VALUES(sb_thumbnail_url),
            sb_product_url        = VALUES(sb_product_url),
            sb_regular_price_jpy  = VALUES(sb_regular_price_jpy),
            sb_min_price_jpy      = VALUES(sb_min_price_jpy),
            sb_min_price_krw      = VALUES(sb_min_price_krw),
            sb_fx_rate            = VALUES(sb_fx_rate),
            sb_listing_count      = VALUES(sb_listing_count),
            sb_released_at        = VALUES(sb_released_at),
            sb_brand_id           = VALUES(sb_brand_id),
            sb_category_id        = VALUES(sb_category_id),
            sb_status             = VALUES(sb_status),
            sb_fetched_at         = NOW()
    ");

    if (!$ok) {
        return ['ok' => false, 'changed' => false, 'error' => db_last_error() ?: 'upsert failed'];
    }

    $changed = true;
    if ($prev) {
        $changed = ((int) $prev['sb_min_price_jpy'] !== $min_jpy)
            || ((int) $prev['sb_listing_count'] !== $listing);
    }

    if ($changed && snkrdunk_box_log_table_ready()) {
        db_query("
            INSERT INTO tb_snkrdunk_box_price_log
                (sb_product_id, sbl_min_price_jpy, sbl_min_price_krw, sbl_fx_rate, sbl_listing_count)
            VALUES
                ({$product_id}, {$min_jpy}, {$min_krw}, {$fx_sql}, {$listing})
        ");
    }

    return ['ok' => true, 'changed' => $changed];
}

/**
 * 전체 수집 실행
 *
 * @param array{
 *   max_pages?: int,
 *   per_page?: int,
 *   sleep_ms?: int,
 *   name_prefilter?: bool,
 *   dry_run?: bool
 * } $opts
 * @return array<string,mixed>
 */
function snkrdunk_box_crawl_run(array $opts = []): array
{
    $max_pages      = max(1, (int) ($opts['max_pages'] ?? 40));
    $per_page       = max(1, min(50, (int) ($opts['per_page'] ?? 50)));
    $sleep_ms       = max(0, (int) ($opts['sleep_ms'] ?? 250));
    $name_prefilter = array_key_exists('name_prefilter', $opts) ? (bool) $opts['name_prefilter'] : true;
    $dry_run        = !empty($opts['dry_run']);

    $fx = snkrdunk_box_fetch_jpy_krw_rate();
    $fx_rate = (float) $fx['rate'];

    $stats = [
        'ok'              => true,
        'fx_rate'         => $fx_rate,
        'fx_source'       => (string) ($fx['source'] ?? ''),
        'fx_warning'      => (string) ($fx['error'] ?? ''),
        'pages'           => 0,
        'listed'          => 0,
        'candidates'      => 0,
        'detail_ok'       => 0,
        'detail_fail'     => 0,
        'boxes_saved'     => 0,
        'boxes_changed'   => 0,
        'skipped_not_box' => 0,
        'errors'          => [],
        'dry_run'         => $dry_run,
    ];

    if (!$dry_run && !snkrdunk_box_tables_ready()) {
        return [
            'ok'    => false,
            'error' => 'tb_snkrdunk_box missing — run sql/tb_snkrdunk_box.sql',
            'fx_rate' => $fx_rate,
        ];
    }

    for ($page = 1; $page <= $max_pages; $page++) {
        $list = snkrdunk_box_fetch_list_page($page, $per_page);
        if (empty($list['ok'])) {
            $stats['ok'] = false;
            $stats['errors'][] = 'list page ' . $page . ': ' . ($list['error'] ?? 'fail');
            break;
        }

        $items = $list['items'];
        $stats['pages']++;
        $stats['listed'] += count($items);

        if (count($items) === 0) {
            break;
        }

        foreach ($items as $it) {
            $name_en = (string) ($it['name'] ?? '');
            if ($name_prefilter && !snkrdunk_box_list_name_looks_like_box($name_en)) {
                continue;
            }
            $stats['candidates']++;

            $detail = snkrdunk_box_fetch_detail((int) $it['id']);
            if ($sleep_ms > 0) {
                usleep($sleep_ms * 1000);
            }

            if (empty($detail['ok']) || empty($detail['item'])) {
                $stats['detail_fail']++;
                if (count($stats['errors']) < 20) {
                    $stats['errors'][] = 'detail ' . (int) $it['id'] . ': ' . ($detail['error'] ?? 'fail');
                }
                continue;
            }

            $stats['detail_ok']++;
            $d = $detail['item'];
            $color = (string) ($d['colorName'] ?? '');
            $color_loc = (string) ($d['colorLocalized'] ?? '');
            $name = (string) ($d['nameEn'] ?? $name_en);

            if (!snkrdunk_box_is_unopened_box($color, $color_loc, $name)) {
                $stats['skipped_not_box']++;
                continue;
            }

            $product_id = (int) $d['id'];
            $row = [
                'product_id'         => $product_id,
                'product_number'     => (string) ($d['productNumber'] ?: ($it['productNumber'] ?? '')),
                'name_en'            => $name !== '' ? $name : $name_en,
                'name_ja'            => (string) ($d['nameJa'] ?? ''),
                'color_name'         => $color,
                'thumbnail_url'      => (string) ($d['thumbnailUrl'] ?: ($it['thumbnailUrl'] ?? '')),
                'product_url'        => 'https://snkrdunk.com/apparels/' . $product_id,
                'regular_price_jpy'  => (int) ($d['regularPriceJpy'] ?? 0),
                'min_price_jpy'      => (int) ($d['minPriceJpy'] ?? 0),
                'listing_count'      => (int) ($d['listingCount'] ?? 0),
                'released_at'        => $d['releasedAt'] ?? null,
                'brand_id'           => SNKRDUNK_BOX_BRAND_ID,
                'category_id'        => SNKRDUNK_BOX_CATEGORY_ID,
                'status'             => 1,
            ];

            if ($dry_run) {
                $stats['boxes_saved']++;
                continue;
            }

            $up = snkrdunk_box_upsert($row, $fx_rate);
            if (empty($up['ok'])) {
                $stats['detail_fail']++;
                if (count($stats['errors']) < 20) {
                    $stats['errors'][] = 'upsert ' . $product_id . ': ' . ($up['error'] ?? 'fail');
                }
                continue;
            }

            $stats['boxes_saved']++;
            if (!empty($up['changed'])) {
                $stats['boxes_changed']++;
            }
        }

        if (count($items) < $per_page) {
            break;
        }

        if ($sleep_ms > 0) {
            usleep($sleep_ms * 1000);
        }
    }

    return $stats;
}
