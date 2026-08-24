<?php
/**
 * 무통장 입금 자동 확인 (은행·자동화 POST 수신)
 *
 * POST 필드: name (입금자명), amount (입금금액), msg (은행 알림 원문)
 *
 * 예) https://example.com/trade/cron/trade_bank_deposit_confirm.php
 */
define('PZ_API_JSON', true);

require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_cron_job_log.php';
require_once __DIR__ . '/../lib/_trade_payment.php';

header('Content-Type: application/json; charset=UTF-8');

$started_ms = (int) round(microtime(true) * 1000);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    cron_job_log_write(CRON_JOB_TRADE_BANK_DEPOSIT_CONFIRM, [
        'ok'          => false,
        'trigger'     => 'web',
        'message'     => 'POST 요청만 허용됩니다.',
        'duration_ms' => max(0, (int) round(microtime(true) * 1000) - $started_ms),
        'detail'      => [
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        ],
    ]);
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST 요청만 허용됩니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name   = trim((string) ($_POST['name'] ?? ''));
$amount = $_POST['amount'] ?? '';
$msg    = trim((string) ($_POST['msg'] ?? ''));

$result = trade_payment_confirm_from_bank_webhook($name, $amount, $msg);

$ok      = !empty($result['ok']);
$pay_idx = (int) ($result['pay_idx'] ?? 0);
$message = (string) ($result['message'] ?? $result['error'] ?? '');
if ($message === '') {
    $message = $ok ? '입금 처리 완료' : '입금 처리 실패';
}

cron_job_log_write(CRON_JOB_TRADE_BANK_DEPOSIT_CONFIRM, [
    'ok'          => $ok,
    'trigger'     => 'post',
    'message'     => $message,
    'count_processed' => 1,
    'count_ok'    => $ok ? 1 : 0,
    'count_fail'  => $ok ? 0 : 1,
    'ref_id'      => $pay_idx,
    'duration_ms' => max(0, (int) round(microtime(true) * 1000) - $started_ms),
    'detail'      => [
        'depositor' => $name,
        'amount'    => (int) preg_replace('/\D+/', '', (string) $amount),
        'already'   => !empty($result['already']),
        'bank_msg'  => mb_substr($msg, 0, 200),
    ],
]);

if (!$ok) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
