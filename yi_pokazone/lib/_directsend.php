<?php
/**
 * DirectSend SMS (api_v2/sms_change_word)
 */
declare(strict_types=1);

/** @return array{username:string,key:string,sender:string,title:string,message:string}|null */
function directsend_config(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    $path = dirname(__DIR__) . '/config/directsend.php';
    if (!is_file($path)) {
        $cache = false;

        return null;
    }
    /** @var mixed $c */
    $c = include $path;
    if (!is_array($c)) {
        $cache = false;

        return null;
    }
    $username = trim((string) ($c['username'] ?? ''));
    $key      = trim((string) ($c['key'] ?? ''));
    $sender   = preg_replace('/\D/u', '', (string) ($c['sender'] ?? ''));
    $title    = trim((string) ($c['title'] ?? '휴대폰 인증'));
    $message  = trim((string) ($c['message'] ?? ''));
    if ($username === '' || $key === '' || $sender === '' || $message === '') {
        $cache = false;

        return null;
    }
    $cache = [
        'username' => $username,
        'key'      => $key,
        'sender'   => $sender,
        'title'    => $title,
        'message'  => $message,
    ];

    return $cache;
}

function directsend_is_ready(): bool
{
    return directsend_config() !== null;
}

/**
 * @return array{ok:bool,error:?string,status:?string,raw:?string}
 */
function directsend_send_auth_sms(string $phone_digits, string $code): array
{
    $cfg = directsend_config();
    if ($cfg === null) {
        return ['ok' => false, 'error' => 'SMS 설정이 완료되지 않았습니다.', 'status' => null, 'raw' => null];
    }
    if (!preg_match('/^01[016789]\d{7,8}$/', $phone_digits)) {
        return ['ok' => false, 'error' => '수신 번호가 올바르지 않습니다.', 'status' => null, 'raw' => null];
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => '인증번호 형식이 올바르지 않습니다.', 'status' => null, 'raw' => null];
    }

    $payload = [
        'title'    => $cfg['title'],
        'message'  => $cfg['message'],
        'sender'   => $cfg['sender'],
        'username' => $cfg['username'],
        'key'      => $cfg['key'],
        'receiver' => [
            [
                'mobile' => $phone_digits,
                'note1'  => $code,
            ],
        ],
        'type' => 'php',
    ];

    $ch = curl_init('https://directsend.co.kr/index.php/api_v2/sms_change_word');
    if ($ch === false) {
        return ['ok' => false, 'error' => 'SMS 요청을 시작하지 못했습니다.', 'status' => null, 'raw' => null];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=UTF-8'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log('directsend curl error: ' . $curl_err);

        return ['ok' => false, 'error' => 'SMS 발송 중 통신 오류가 발생했습니다.', 'status' => null, 'raw' => null];
    }

    $decoded = json_decode($raw, true);
    $status  = is_array($decoded) ? (string) ($decoded['status'] ?? '') : '';
    if ($status === '0') {
        return ['ok' => true, 'error' => null, 'status' => $status, 'raw' => $raw];
    }

    $msg = is_array($decoded) ? trim((string) ($decoded['msg'] ?? '')) : '';
    if ($msg === '') {
        $msg = 'SMS 발송에 실패했습니다.';
    }
    error_log('directsend api fail status=' . $status . ' raw=' . $raw);

    return ['ok' => false, 'error' => $msg, 'status' => $status !== '' ? $status : null, 'raw' => $raw];
}
