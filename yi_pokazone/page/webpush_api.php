<?php
/**
 * Web Push 구독 등록/해제 (JSON API)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/webpush.php';

header('Content-Type: application/json; charset=UTF-8');

function webpush_api_exit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$me = login_member();
if (!$me) {
    webpush_api_exit(['ok' => false, 'error' => '로그인이 필요합니다.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    webpush_api_exit(['ok' => false, 'error' => 'POST 만 지원합니다.']);
}

if (!webpush_is_ready()) {
    webpush_api_exit(['ok' => false, 'error' => '웹푸시가 설정되지 않았습니다. Composer·VAPID·DB(sql/tb_web_push_subscription.sql)를 확인하세요.']);
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw !== false ? $raw : '', true);
if (!is_array($in)) {
    webpush_api_exit(['ok' => false, 'error' => 'JSON 본문이 필요합니다.']);
}

$action = (string) ($in['action'] ?? '');
$mb_idx = (int) $me['mb_idx'];

if ($action === 'subscribe') {
    $sub = $in['subscription'] ?? null;
    if (!is_array($sub)) {
        webpush_api_exit(['ok' => false, 'error' => 'subscription 이 없습니다.']);
    }
    $endpoint = trim((string) ($sub['endpoint'] ?? ''));
    $keys     = $sub['keys'] ?? null;
    $p256dh   = is_array($keys) ? trim((string) ($keys['p256dh'] ?? '')) : '';
    $auth     = is_array($keys) ? trim((string) ($keys['auth'] ?? '')) : '';
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        webpush_api_exit(['ok' => false, 'error' => '구독 정보가 올바르지 않습니다.']);
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if (!webpush_save_subscription($mb_idx, $endpoint, $p256dh, $auth, is_string($ua) ? $ua : null)) {
        webpush_api_exit(['ok' => false, 'error' => '구독 저장에 실패했습니다.']);
    }
    webpush_api_exit(['ok' => true]);
}

if ($action === 'unsubscribe') {
    $endpoint = trim((string) ($in['endpoint'] ?? ''));
    if ($endpoint === '') {
        webpush_api_exit(['ok' => false, 'error' => 'endpoint 가 필요합니다.']);
    }
    webpush_remove_subscription($mb_idx, $endpoint);
    webpush_api_exit(['ok' => true]);
}

if ($action === 'test') {
    $n_rs = db_query('SELECT COUNT(*) AS c FROM tb_web_push_subscription WHERE mb_idx = ' . (int) $mb_idx);
    $n_rw = $n_rs ? db_assoc($n_rs) : null;
    $n    = (int) ($n_rw['c'] ?? 0);
    if ($n < 1) {
        webpush_api_exit([
            'ok'    => false,
            'error' => '저장된 푸시 구독이 없습니다. 알림 설정에서 스위치를 켜서 이 기기를 등록하세요.',
        ]);
    }
    $open = seo_abs_url('/trade/trade_messages.php');
    $payload = json_encode([
        'title' => 'Pokazone',
        'body'  => '테스트 알림입니다. 푸시 연결이 정상입니다.',
        'url'   => $open,
        'tag'   => 'pz-test-' . str_replace('.', '', uniqid('', true)),
    ], JSON_UNESCAPED_UNICODE);
    $delivery_diag = [];
    if (!webpush_deliver_payload($mb_idx, $payload, $delivery_diag)) {
        $hint = '';
        if (!empty($delivery_diag['failures']) && is_array($delivery_diag['failures'])) {
            $hint = mb_substr(implode(' · ', $delivery_diag['failures']), 0, 420);
        }
        $msg = '푸시 전송에 실패했습니다.';
        if ($hint !== '') {
            $msg .= ' (' . $hint . ')';
        }
        $msg .= ' 서버 방화벽(아웃바운드 HTTPS), VAPID 키 쌍 일치, 알림 설정에서 스위치로 구독 갱신을 확인해 주세요.';
        webpush_api_exit([
            'ok'    => false,
            'error' => $msg,
        ]);
    }
    webpush_api_exit(['ok' => true]);
}

webpush_api_exit(['ok' => false, 'error' => '알 수 없는 action 입니다.']);
