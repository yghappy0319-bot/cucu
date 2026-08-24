<?php
/**
 * 스마트택배 배송조회 API
 */

function sweettracker_api_key(): string
{
    global $sweettracker_api_key;

    return trim((string) ($sweettracker_api_key ?? ''));
}

function sweettracker_template_id(): int
{
    global $sweettracker_template_id;
    $id = (int) ($sweettracker_template_id ?? 5);

    return ($id >= 1 && $id <= 5) ? $id : 5;
}

function sweettracker_ready(): bool
{
    return sweettracker_api_key() !== '';
}

/** @return array<string, string> name => code */
function sweettracker_company_fallback_map(): array
{
    return [
        '우체국택배'   => '01',
        'CJ대한통운'   => '04',
        '한진택배'     => '05',
        '로젠택배'     => '06',
        '롯데택배'     => '08',
        '대신택배'     => '22',
        '경동택배'     => '23',
        '일양로지스'   => '11',
        '합동택배'     => '32',
        'CU편의점택배' => '46',
    ];
}

/**
 * @return array<int, array{code: string, name: string}>
 */
function sweettracker_company_list(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = [];
    $key    = sweettracker_api_key();
    if ($key === '') {
        foreach (sweettracker_company_fallback_map() as $name => $code) {
            $cached[] = ['code' => $code, 'name' => $name];
        }

        return $cached;
    }

    $cache_file = dirname(__DIR__) . '/config/cache/sweettracker_companies.json';
    $ttl        = 86400;
    if (is_readable($cache_file)) {
        $raw = @file_get_contents($cache_file);
        if ($raw !== false && $raw !== '') {
            $wrap = json_decode($raw, true);
            if (is_array($wrap) && !empty($wrap['items']) && is_array($wrap['items'])) {
                $at = (int) ($wrap['cached_at'] ?? 0);
                if ($at > 0 && (time() - $at) < $ttl) {
                    $cached = $wrap['items'];

                    return $cached;
                }
            }
        }
    }

    $url  = 'https://info.sweettracker.co.kr/api/v1/companylist?t_key=' . rawurlencode($key);
    $data = sweettracker_http_get_json($url);
    if (is_array($data)) {
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['Code'] ?? $row['code'] ?? ''));
            $name = trim((string) ($row['Name'] ?? $row['name'] ?? ''));
            if ($code !== '' && $name !== '') {
                $cached[] = ['code' => $code, 'name' => $name];
            }
        }
    }

    if ($cached === []) {
        foreach (sweettracker_company_fallback_map() as $name => $code) {
            $cached[] = ['code' => $code, 'name' => $name];
        }
    } else {
        @mkdir(dirname($cache_file), 0755, true);
        @file_put_contents($cache_file, json_encode([
            'cached_at' => time(),
            'items'     => $cached,
        ], JSON_UNESCAPED_UNICODE));
    }

    return $cached;
}

function sweettracker_courier_codes_match(string $a, string $b): bool
{
    $a = trim($a);
    $b = trim($b);
    if ($a === $b) {
        return true;
    }
    $na = ltrim($a, '0');
    $nb = ltrim($b, '0');

    return $na !== '' && $nb !== '' && $na === $nb;
}

function sweettracker_company_name_by_code(string $code): string
{
    $code = trim($code);
    if ($code === '') {
        return '';
    }
    foreach (sweettracker_company_fallback_map() as $name => $fb_code) {
        if (sweettracker_courier_codes_match((string) $fb_code, $code)) {
            return (string) $name;
        }
    }
    foreach (sweettracker_company_list() as $row) {
        $row_code = (string) ($row['code'] ?? '');
        if (sweettracker_courier_codes_match($row_code, $code)) {
            return (string) ($row['name'] ?? '');
        }
    }

    return '';
}

function sweettracker_resolve_company_code(string $courier_name): string
{
    $name = trim($courier_name);
    if ($name === '') {
        return '';
    }

    $norm = sweettracker_normalize_company_name($name);
    foreach (sweettracker_company_list() as $row) {
        $row_name = sweettracker_normalize_company_name((string) ($row['name'] ?? ''));
        if ($row_name === $norm || mb_strpos($row_name, $norm) !== false || mb_strpos($norm, $row_name) !== false) {
            return (string) ($row['code'] ?? '');
        }
    }

    $fallback = sweettracker_company_fallback_map();
    foreach ($fallback as $fb_name => $fb_code) {
        $fb_norm = sweettracker_normalize_company_name($fb_name);
        if ($fb_norm === $norm || mb_strpos($norm, $fb_norm) !== false) {
            return $fb_code;
        }
    }

    return '';
}

function sweettracker_normalize_company_name(string $name): string
{
    $name = mb_strtolower(trim($name));
    $name = str_replace([' ', '(주)', '주식회사'], '', $name);

    return $name;
}

/**
 * @return array<string, mixed>|null
 */
function sweettracker_tracking_info(string $company_code, string $invoice): ?array
{
    $key = sweettracker_api_key();
    $code = trim($company_code);
    $invoice = preg_replace('/\s+/', '', trim($invoice));
    if ($key === '' || $code === '' || $invoice === '') {
        return null;
    }

    $url = 'https://info.sweettracker.co.kr/api/v1/trackingInfo?' . http_build_query([
        't_key'     => $key,
        't_code'    => $code,
        't_invoice' => $invoice,
    ]);

    $data = sweettracker_http_get_json($url);
    if (!is_array($data)) {
        return null;
    }
    if (!empty($data['status']) && (bool) $data['status'] === false) {
        return null;
    }

    return $data;
}

/**
 * @return array<string, mixed>|null
 */
function sweettracker_tracking_info_cached(string $company_code, string $invoice, int $ttl = 600): ?array
{
    $code = trim($company_code);
    $invoice = preg_replace('/\s+/', '', trim($invoice));
    if ($code === '' || $invoice === '') {
        return null;
    }

    $cache_file = dirname(__DIR__) . '/config/cache/sweettracker_track_' . md5($code . '|' . $invoice) . '.json';
    if (is_readable($cache_file)) {
        $raw = @file_get_contents($cache_file);
        if ($raw !== false && $raw !== '') {
            $wrap = json_decode($raw, true);
            if (is_array($wrap) && !empty($wrap['info']) && is_array($wrap['info'])) {
                $at = (int) ($wrap['cached_at'] ?? 0);
                if ($at > 0 && (time() - $at) < $ttl) {
                    return $wrap['info'];
                }
            }
        }
    }

    $info = sweettracker_tracking_info($code, $invoice);
    if ($info) {
        @mkdir(dirname($cache_file), 0755, true);
        @file_put_contents($cache_file, json_encode([
            'cached_at' => time(),
            'info'      => $info,
        ], JSON_UNESCAPED_UNICODE));
    }

    return $info;
}

function sweettracker_tracking_is_complete(?array $info): bool
{
    if (!$info) {
        return false;
    }
    if (!empty($info['complete'])) {
        return true;
    }

    return sweettracker_tracking_status_text($info) === '배송완료';
}

/**
 * @return array<int, array{time: string, where: string, kind: string, level: int}>
 */
function sweettracker_tracking_timeline(array $info): array
{
    $out = [];
    $details = $info['trackingDetails'] ?? $info['tracking_details'] ?? [];
    if (!is_array($details)) {
        return $out;
    }
    foreach ($details as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'time'  => trim((string) ($row['timeString'] ?? $row['time'] ?? '')),
            'where' => trim((string) ($row['where'] ?? '')),
            'kind'  => trim((string) ($row['kind'] ?? '')),
            'level' => (int) ($row['level'] ?? 0),
        ];
    }

    return array_reverse($out);
}

function sweettracker_tracking_status_text(array $info): string
{
    if (!empty($info['complete'])) {
        return '배송완료';
    }
    $last = $info['lastStateDetail'] ?? null;
    if (is_array($last) && !empty($last['kind'])) {
        return (string) $last['kind'];
    }
    $timeline = sweettracker_tracking_timeline($info);
    if ($timeline) {
        return (string) ($timeline[0]['kind'] ?? '배송중');
    }

    return '배송 조회 중';
}

/**
 * @return array<string, mixed>|null
 */
function sweettracker_http_get_json(string $url): ?array
{
    $raw = sweettracker_http_get($url);
    if ($raw === null || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

function sweettracker_http_get(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            error_log('sweettracker curl: ' . $err);

            return null;
        }

        return (string) $raw;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header'  => "Accept: application/json\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }

    return (string) $raw;
}

/**
 * @param array<string, mixed> $row tb_trade_payment
 * @return array{code: string, info: array<string, mixed>|null, error: string}
 */
function sweettracker_tracking_for_payment_row(array $row): array
{
    $out = ['code' => '', 'info' => null, 'error' => ''];
    $courier = trim((string) ($row['pay_courier_name'] ?? ''));
    $invoice = preg_replace('/\s+/', '', trim((string) ($row['pay_tracking_no'] ?? '')));
    if ($courier === '' || $invoice === '') {
        return $out;
    }
    if (!sweettracker_ready()) {
        $out['error'] = 'api_key';

        return $out;
    }

    $code = trim((string) ($row['pay_courier_code'] ?? ''));
    if ($code === '') {
        $code = sweettracker_resolve_company_code($courier);
    }
    if ($code === '') {
        $out['error'] = 'unknown_courier';

        return $out;
    }

    $invoice = (string) ($row['pay_tracking_no'] ?? '');
    $info    = sweettracker_tracking_info_cached($code, $invoice);
    if (!$info) {
        $out['code']  = $code;
        $out['error'] = 'fetch_failed';

        return $out;
    }

    $out['code'] = $code;
    $out['info'] = $info;

    return $out;
}
