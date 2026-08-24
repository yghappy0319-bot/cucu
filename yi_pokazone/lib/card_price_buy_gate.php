<?php
/**
 * 박스 시세 — 외부 구매 게이트(포인트 차감·URL 검증)
 */
require_once __DIR__ . '/card_price_feed.php';

const CARD_PRICE_BUY_POINT_COST = 100;

/** @see site_info.php — $card_price_buy_force_redirect */
function card_price_buy_force_redirect_external(): bool
{
    global $card_price_buy_force_redirect;

    return isset($card_price_buy_force_redirect) && $card_price_buy_force_redirect === true;
}

/** 토큰 유효 시간(초) */
function card_price_buy_token_ttl(): int
{
    return 1800;
}

function card_price_buy_hmac_secret(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    $cached = hash('sha256', 'pokazone_card_buy|v1|' . $root);

    return $cached;
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
 * 박스 시세 피드에서 URL과 일치하는 오퍼·상품 찾기
 *
 * @return array<string, mixed>|null
 */
function card_price_buy_offer_by_product_url(string $product_url): ?array
{
    $want = trim($product_url);
    if ($want === '' || !card_price_valid_http_url($want)) {
        return null;
    }

    $feed = card_price_feed_load();
    foreach (($feed['products'] ?? []) as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pname = isset($p['name']) ? (string) $p['name'] : '';
        foreach (($p['offers'] ?? []) as $o) {
            if (!is_array($o)) {
                continue;
            }
            $u = trim((string) ($o['product_url'] ?? ''));
            if ($u === $want) {
                $be = 1;
                if (array_key_exists('buy_enabled', $o)) {
                    $be = (int) $o['buy_enabled'] === 1 ? 1 : 0;
                }

                return [
                    'product_url' => $u,
                    'site_name' => (string) ($o['site_name'] ?? ''),
                    'site_id' => (string) ($o['site_id'] ?? ''),
                    'co_type' => trim((string) ($o['co_type'] ?? '')),
                    'product_name' => $pname,
                    'price_won' => (int) ($o['price_won'] ?? 0),
                    'stock_qty' => array_key_exists('stock_qty', $o) ? $o['stock_qty'] : null,
                    'buy_enabled' => $be,
                ];
            }
        }
    }

    return null;
}

/** co_buy_enabled(피드 buy_enabled)·1 일 때만 포인트 차감 진행 허용 */
function card_price_buy_offer_available(array $row): bool
{
    return (int) ($row['buy_enabled'] ?? 1) === 1;
}

/**
 * 포인트 차감형 구매 안내 전 — 보유 포인트가 이용 요금 이상인지 검사합니다.
 * tb_point_log 가 없으면 포인트 기능 미적용으로 보고 true 입니다.
 */
function card_price_buy_member_has_sufficient_points(int $mb_idx): bool
{
    if ($mb_idx < 1) {
        return false;
    }
    if (!db_table_exists('tb_point_log')) {
        return true;
    }

    return (int) db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1") >= CARD_PRICE_BUY_POINT_COST;
}

/** @return non-empty-string|null */
function card_price_buy_token_issue(string $product_url): ?string
{
    $u = trim($product_url);
    if (!card_price_valid_http_url($u)) {
        return null;
    }
    if (card_price_buy_offer_by_product_url($u) === null) {
        return null;
    }

    $payload = [
        'u' => $u,
        'iat' => time(),
    ];

    return card_price_buy_token_pack($payload);
}

/**
 * 토큰 검증 후 포인트 차감·외부 URL 이동 (proc · jump 즉시 연결 공용)
 *
 * @param bool $allow_when_offer_disabled «품절» 모달의 «그래도 확인해 볼래요» 선택 시만 true
 * @param string|null $balance_fail_redirect 포인트 부족 등 실패 후 이동 URL (보통 박스 시세 확인·jump 재진입 등)
 *
 * @return array{
 *     ok?: bool,
 *     dest?: string,
 *     need_login?: bool,
 *     alert?: array{msg: string, url: ?string},
 * }
 */
function card_price_buy_try_purchase(string $token, bool $allow_when_offer_disabled, ?string $balance_fail_redirect = null): array
{
    if (!function_exists('login_member')) {
        require_once __DIR__ . '/_function.php';
    }

    $t = trim($token);
    $tok = card_price_buy_token_verify($t);
    if ($tok === null) {
        return [
            'ok' => false,
            'alert' => [
                'msg' => '안내 시간이 만료되었거나 잘못된 요청입니다. 박스 시세에서 다시 시도해 주세요.',
                'url' => '/page/card_price.php',
            ],
        ];
    }

    $url_key = trim((string) ($tok['u'] ?? ''));
    $found   = card_price_buy_offer_by_product_url($url_key);

    if ($found === null) {
        return [
            'ok' => false,
            'alert' => [
                'msg' => '해당 링크를 더 이상 박스 시세에서 찾을 수 없습니다. 목록을 다시 확인해 주세요.',
                'url' => '/page/card_price.php',
            ],
        ];
    }

    $available = card_price_buy_offer_available($found);
    if (!$available && !$allow_when_offer_disabled) {
        return [
            'ok' => false,
            'alert' => [
                'msg' => '품절 상품입니다.',
                'url' => '',
            ],
        ];
    }

    $dest = trim((string) ($found['product_url'] ?? ''));
    if ($dest === '' || $dest !== $url_key || !card_price_valid_http_url($dest)) {
        return [
            'ok' => false,
            'alert' => [
                'msg' => '판매 페이지 주소가 올바르지 않습니다.',
                'url' => '/page/card_price.php',
            ],
        ];
    }

    if (!db_table_exists('tb_point_log')) {
        return [
            'ok' => false,
            'alert' => [
                'msg' => '포인트 시스템이 준비되지 않았습니다.',
                'url' => '/page/card_price.php',
            ],
        ];
    }

    $me = login_member();
    if (!$me) {
        return ['ok' => false, 'need_login' => true];
    }

    $mb_idx = (int) ($me['mb_idx'] ?? 0);
    if ($mb_idx < 1) {
        return [
            'ok' => false,
            'alert' => ['msg' => '회원 정보를 확인할 수 없습니다.', 'url' => '/login.php'],
        ];
    }

    $cost = (int) CARD_PRICE_BUY_POINT_COST;
    $name = isset($found['product_name']) ? trim((string) $found['product_name']) : '';
    $memo = '박스시세 외부구매 안내 이용';
    if ($name !== '') {
        $memo .= ' · ' . mb_substr($name, 0, 80, 'UTF-8');
    }
    if ($allow_when_offer_disabled && !$available) {
        $memo .= ' · 품절링크예외차감';
    }

    $fallback = $balance_fail_redirect;
    if ($fallback === null || $fallback === '') {
        $fallback = '/page/card_price_buy.php?t=' . rawurlencode($t);
    }

    if (!point_change_with_log($mb_idx, -$cost, 'card_buy', $memo)) {
        return [
            'ok' => false,
            'alert' => [
                'msg' => '포인트가 부족하거나 처리에 실패했습니다. 박스 시세 확인 화면에서 잔여 포인트를 확인한 뒤 다시 시도해 주세요.',
                'url' => $fallback,
            ],
        ];
    }

    return ['ok' => true, 'dest' => $dest];
}

/** @return non-empty-string|null */
function card_price_buy_token_pack(array $payload): ?string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || $json === '') {
        return null;
    }
    $sig = hash_hmac('sha256', $json, card_price_buy_hmac_secret(), false);

    return rtrim(strtr(base64_encode($json . '::' . $sig), '+/', '-_'), '=');
}

/**
 * @return array<string, mixed>|null 검증 성공 시 payload(u,iat)
 */
function card_price_buy_token_verify(string $token): ?array
{
    $t = trim($token);
    if ($t === '') {
        return null;
    }

    $raw = base64_decode(strtr($t, '-_', '+/'), true);
    if ($raw === false || $raw === '') {
        return null;
    }

    $pos = strpos($raw, '::');
    if ($pos === false) {
        return null;
    }

    $json = substr($raw, 0, $pos);
    $sig_expect = substr($raw, $pos + 2);
    if ($json === '' || $sig_expect === '' || strlen($sig_expect) !== 64) {
        return null;
    }

    $calc = hash_hmac('sha256', $json, card_price_buy_hmac_secret(), false);
    if (!hash_equals($calc, $sig_expect)) {
        return null;
    }

    $data = json_decode($json, true, 512);
    if (!is_array($data)) {
        return null;
    }

    $u = isset($data['u']) ? trim((string) $data['u']) : '';
    $iat = isset($data['iat']) ? (int) $data['iat'] : 0;
    if ($u === '' || !card_price_valid_http_url($u) || $iat < 1) {
        return null;
    }

    if (time() - $iat > card_price_buy_token_ttl()) {
        return null;
    }

    return ['u' => $u, 'iat' => $iat];
}
