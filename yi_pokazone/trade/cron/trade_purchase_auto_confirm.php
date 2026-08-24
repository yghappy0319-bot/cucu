<?php
/**
 * 배송확인 후 구매확정 미진행 건 자동 구매확정 (cron)
 *
 * 배송확인(pay_fulfill_status=2) 후 TRADE_PURCHASE_AUTO_CONFIRM_DAYS(기본 3일) 경과 시
 * 구매확정 처리 및 판매자 정산(플랫폼 수수료 5% 차감)
 *
 * crontab 예) 매일 03:00:
 *   0 3 * * * php /path/to/public_html/trade/cron/trade_purchase_auto_confirm.php
 *
 * 웹 호출(테스트): define('TRADE_CRON_ALLOW_WEB', true) 후 GET/POST
 */
define('PZ_API_JSON', true);

require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_cron_job_log.php';
require_once __DIR__ . '/../lib/_trade_payment.php';

if (PHP_SAPI !== 'cli' && !defined('TRADE_CRON_ALLOW_WEB')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('CLI only');
}

$started_ms = (int) round(microtime(true) * 1000);
$trigger    = cron_job_log_trigger_detect();

$write_log = static function (bool $ok, string $message, array $extra = []) use ($started_ms, $trigger): void {
    cron_job_log_write(CRON_JOB_TRADE_PURCHASE_AUTO_CONFIRM, array_merge([
        'ok'          => $ok,
        'trigger'     => $trigger,
        'message'     => $message,
        'duration_ms' => max(0, (int) round(microtime(true) * 1000) - $started_ms),
    ], $extra));
};

if (!trade_payment_tables_ready()) {
    $err = 'tb_trade_payment not found';
    $write_log(false, $err);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $err . "\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit  = isset($_GET['limit']) ? (int) $_GET['limit'] : (isset($argv[1]) ? (int) $argv[1] : 50);
$result = trade_payment_auto_confirm_purchases($limit);

$payload = [
    'ok'        => !empty($result['ok']),
    'processed' => (int) ($result['processed'] ?? 0),
    'confirmed' => (int) ($result['confirmed'] ?? 0),
    'failed'    => (int) ($result['failed'] ?? 0),
    'days'      => (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS,
    'at'        => date('Y-m-d H:i:s'),
];

if (empty($result['ok'])) {
    $payload['error'] = (string) ($result['error'] ?? 'auto confirm failed');
}

$log_message = empty($result['ok'])
    ? (string) ($result['error'] ?? 'auto confirm failed')
    : sprintf(
        '처리 %d건 · 확정 %d건 · 실패 %d건',
        (int) ($result['processed'] ?? 0),
        (int) ($result['confirmed'] ?? 0),
        (int) ($result['failed'] ?? 0)
    );

$write_log(!empty($result['ok']), $log_message, [
    'count_processed' => (int) ($result['processed'] ?? 0),
    'count_ok'        => (int) ($result['confirmed'] ?? 0),
    'count_fail'      => (int) ($result['failed'] ?? 0),
    'detail'          => [
        'days'     => (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS,
        'limit'    => $limit,
        'pay_idxs' => array_values(array_map('intval', (array) ($result['pay_idxs'] ?? []))),
        'error'    => empty($result['ok']) ? (string) ($result['error'] ?? '') : '',
    ],
]);

if (PHP_SAPI === 'cli') {
    echo date('Y-m-d H:i:s')
        . ' processed=' . $payload['processed']
        . ' confirmed=' . $payload['confirmed']
        . ' failed=' . $payload['failed']
        . "\n";
    exit(empty($result['ok']) ? 1 : 0);
}

header('Content-Type: application/json; charset=UTF-8');
if (empty($result['ok'])) {
    http_response_code(500);
}
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
