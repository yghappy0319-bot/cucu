<?php
/**
 * 스니덩크 포켓몬 미개봉 박스 최저가 수집 (cron)
 *
 * 대상: https://snkrdunk.com/en/brands/pokemon/trading-cards?categoryId=14
 * - 목록(EN API) → 박스 후보 → 일본 상세(엔화 minPrice) → 환율로 원화 환산 저장
 *
 * 사전 작업:
 *   mysql ... < public_html/sql/tb_snkrdunk_box.sql
 *
 * crontab 예) 매일 04:30:
 *   30 4 * * * php /path/to/public_html/cron/snkrdunk_box_crawl.php >> /var/log/snkrdunk_box_crawl.log 2>&1
 *
 * CLI 옵션:
 *   php snkrdunk_box_crawl.php [--dry-run] [--max-pages=N] [--per-page=N] [--sleep-ms=N] [--no-name-filter]
 *
 * 웹 호출(테스트): define('SNKRDUNK_CRON_ALLOW_WEB', true) 후 GET
 *   ?max_pages=2&dry_run=1
 */
define('PZ_API_JSON', true);

require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_cron_job_log.php';
require_once __DIR__ . '/../lib/_snkrdunk_box.php';

if (PHP_SAPI !== 'cli' && !defined('SNKRDUNK_CRON_ALLOW_WEB')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('CLI only');
}

$started_ms = (int) round(microtime(true) * 1000);
$trigger    = cron_job_log_trigger_detect();

/** @param array<int,string> $argv_in */
function snkrdunk_box_cron_parse_argv(array $argv_in): array
{
    $out = [
        'dry_run'        => false,
        'max_pages'      => 40,
        'per_page'       => 50,
        'sleep_ms'       => 250,
        'name_prefilter' => true,
    ];
    foreach ($argv_in as $i => $arg) {
        if ($i === 0) {
            continue;
        }
        $arg = (string) $arg;
        if ($arg === '--dry-run') {
            $out['dry_run'] = true;
            continue;
        }
        if ($arg === '--no-name-filter') {
            $out['name_prefilter'] = false;
            continue;
        }
        if (preg_match('/^--max-pages=(\d+)$/', $arg, $m)) {
            $out['max_pages'] = max(1, (int) $m[1]);
            continue;
        }
        if (preg_match('/^--per-page=(\d+)$/', $arg, $m)) {
            $out['per_page'] = max(1, min(50, (int) $m[1]));
            continue;
        }
        if (preg_match('/^--sleep-ms=(\d+)$/', $arg, $m)) {
            $out['sleep_ms'] = max(0, (int) $m[1]);
        }
    }

    return $out;
}

$opts = PHP_SAPI === 'cli'
    ? snkrdunk_box_cron_parse_argv($argv ?? [])
    : [
        'dry_run'        => isset($_GET['dry_run']),
        'max_pages'      => max(1, (int) ($_GET['max_pages'] ?? 2)),
        'per_page'       => max(1, min(50, (int) ($_GET['per_page'] ?? 20))),
        'sleep_ms'       => max(0, (int) ($_GET['sleep_ms'] ?? 250)),
        'name_prefilter' => !isset($_GET['no_name_filter']),
    ];

$result = snkrdunk_box_crawl_run($opts);

$ok = !empty($result['ok']);
$message = $ok
    ? sprintf(
        '페이지 %d · 목록 %d · 후보 %d · 박스저장 %d · 가격변동 %d · 환율 %.4f(%s)',
        (int) ($result['pages'] ?? 0),
        (int) ($result['listed'] ?? 0),
        (int) ($result['candidates'] ?? 0),
        (int) ($result['boxes_saved'] ?? 0),
        (int) ($result['boxes_changed'] ?? 0),
        (float) ($result['fx_rate'] ?? 0),
        (string) ($result['fx_source'] ?? '')
    )
    : (string) ($result['error'] ?? 'crawl failed');

if (!empty($result['fx_warning'])) {
    $message .= ' · fx경고:' . $result['fx_warning'];
}

$duration_ms = max(0, (int) round(microtime(true) * 1000) - $started_ms);

cron_job_log_write(CRON_JOB_SNKRDUNK_BOX_CRAWL, [
    'ok'              => $ok,
    'trigger'         => $trigger,
    'message'         => $message,
    'duration_ms'     => $duration_ms,
    'count_processed' => (int) ($result['candidates'] ?? 0),
    'count_ok'        => (int) ($result['boxes_saved'] ?? 0),
    'count_fail'      => (int) ($result['detail_fail'] ?? 0),
    'detail'          => [
        'fx_rate'         => (float) ($result['fx_rate'] ?? 0),
        'fx_source'       => (string) ($result['fx_source'] ?? ''),
        'pages'           => (int) ($result['pages'] ?? 0),
        'listed'          => (int) ($result['listed'] ?? 0),
        'boxes_changed'   => (int) ($result['boxes_changed'] ?? 0),
        'skipped_not_box' => (int) ($result['skipped_not_box'] ?? 0),
        'dry_run'         => !empty($result['dry_run']),
        'errors'          => array_slice((array) ($result['errors'] ?? []), 0, 5),
    ],
]);

$payload = [
    'ok'      => $ok,
    'message' => $message,
    'result'  => $result,
    'at'      => date('Y-m-d H:i:s'),
    'ms'      => $duration_ms,
];

if (PHP_SAPI === 'cli') {
    echo date('Y-m-d H:i:s') . ' ' . $message . "\n";
    if (!empty($result['errors'])) {
        foreach (array_slice((array) $result['errors'], 0, 10) as $e) {
            fwrite(STDERR, "  - {$e}\n");
        }
    }
    exit($ok ? 0 : 1);
}

header('Content-Type: application/json; charset=UTF-8');
if (!$ok) {
    http_response_code(500);
}
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
