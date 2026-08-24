<?php
/**
 * 회원 휴대폰 변경 SMS 인증 API (JSON)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_contact_history.php';
require_once __DIR__ . '/../lib/_member_phone_verify.php';

header('Content-Type: application/json; charset=UTF-8');

function member_phone_verify_api_exit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$me = login_member();
if (!$me) {
    member_phone_verify_api_exit(['ok' => false, 'error' => '로그인이 필요합니다.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    member_phone_verify_api_exit(['ok' => false, 'error' => 'POST 만 지원합니다.']);
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw !== false ? $raw : '', true);
if (!is_array($in)) {
    member_phone_verify_api_exit(['ok' => false, 'error' => 'JSON 본문이 필요합니다.']);
}

$action = trim((string) ($in['action'] ?? ''));
$mb_idx = (int) $me['mb_idx'];

$rs = db_query("SELECT mb_status FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1");
$member_row = db_assoc($rs);
if (!$member_row) {
    member_phone_verify_api_exit(['ok' => false, 'error' => '회원 정보를 찾을 수 없습니다.']);
}
if ((int) $member_row['mb_status'] !== 1) {
    member_phone_verify_api_exit(['ok' => false, 'error' => '로그인할 수 없는 계정입니다.']);
}

if ($action === 'status') {
    member_phone_verify_api_exit([
        'ok'        => true,
        'ready'     => directsend_is_ready() && member_phone_verify_log_table_ready(),
        'remaining' => member_phone_verify_remaining_today($mb_idx),
        'limit'     => MEMBER_PHONE_VERIFY_DAILY_LIMIT,
    ]);
}

$phone = trim((string) ($in['phone'] ?? ''));

if ($action === 'send') {
    $result = member_phone_verify_prepare_send($mb_idx, $phone);
    if (!$result['ok']) {
        member_phone_verify_api_exit([
            'ok'        => false,
            'error'     => $result['error'],
            'remaining' => $result['remaining'],
            'limit'     => MEMBER_PHONE_VERIFY_DAILY_LIMIT,
        ]);
    }
    member_phone_verify_api_exit([
        'ok'         => true,
        'phone'      => $result['phone'],
        'expires_in' => $result['expires_in'],
        'remaining'  => $result['remaining'],
        'limit'      => MEMBER_PHONE_VERIFY_DAILY_LIMIT,
        'message'    => '인증번호를 발송했습니다.',
    ]);
}

if ($action === 'confirm') {
    $code = trim((string) ($in['code'] ?? ''));
    $check = member_phone_verify_check_code($mb_idx, $phone, $code);
    if (!$check['ok']) {
        member_phone_verify_api_exit(['ok' => false, 'error' => $check['error']]);
    }

    $phone_norm = mb_phone_normalize_kr($phone);
    if ($phone_norm === null) {
        member_phone_verify_api_exit(['ok' => false, 'error' => '휴대폰 번호가 올바르지 않습니다.']);
    }

    $applied = member_phone_verify_apply_phone($mb_idx, $phone_norm);
    if (!$applied['ok']) {
        member_phone_verify_api_exit(['ok' => false, 'error' => $applied['error']]);
    }

    member_phone_verify_api_exit([
        'ok'      => true,
        'phone'   => $applied['phone'],
        'message' => '휴대폰 번호가 변경되었습니다.',
    ]);
}

member_phone_verify_api_exit(['ok' => false, 'error' => '지원하지 않는 action 입니다.']);
