<?php
/**
 * Telegram Bot 알림 (관리자)
 */
declare(strict_types=1);

/**
 * @return array{enabled:bool,bot_token:string,chat_ids:list<string>}|null
 */
function telegram_config(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    $path = dirname(__DIR__) . '/config/telegram.php';
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
    $enabled = !empty($c['enabled']);
    $token   = trim((string) ($c['bot_token'] ?? ''));
    $ids_raw = $c['chat_ids'] ?? ($c['chat_id'] ?? []);
    if (!is_array($ids_raw)) {
        $ids_raw = [$ids_raw];
    }
    $chat_ids = [];
    foreach ($ids_raw as $id) {
        $id = trim((string) $id);
        if ($id !== '') {
            $chat_ids[] = $id;
        }
    }
    $chat_ids = array_values(array_unique($chat_ids));
    if (!$enabled || $token === '' || $chat_ids === []) {
        $cache = false;

        return null;
    }
    $cache = [
        'enabled'   => true,
        'bot_token' => $token,
        'chat_ids'  => $chat_ids,
    ];

    return $cache;
}

function telegram_is_ready(): bool
{
    return telegram_config() !== null;
}

/**
 * @return array{ok:bool,error:?string,sent:int}
 */
function telegram_send_message(string $text, ?string $parse_mode = 'HTML'): array
{
    $cfg = telegram_config();
    if ($cfg === null) {
        return ['ok' => false, 'error' => '텔레그램 설정이 없습니다.', 'sent' => 0];
    }
    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'error' => '메시지 내용이 비어 있습니다.', 'sent' => 0];
    }
    if (mb_strlen($text) > 4000) {
        $text = mb_substr($text, 0, 3990) . '…';
    }

    $url  = 'https://api.telegram.org/bot' . $cfg['bot_token'] . '/sendMessage';
    $sent = 0;
    $last_error = null;

    foreach ($cfg['chat_ids'] as $chat_id) {
        $payload = [
            'chat_id' => $chat_id,
            'text'    => $text,
        ];
        if ($parse_mode !== null && $parse_mode !== '') {
            $payload['parse_mode'] = $parse_mode;
            $payload['disable_web_page_preview'] = true;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $last_error = '텔레그램 요청을 시작하지 못했습니다.';
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $raw = curl_exec($ch);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $last_error = '텔레그램 통신 오류: ' . $curl_err;
            error_log('telegram curl error: ' . $curl_err);
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            $desc = is_array($decoded) ? trim((string) ($decoded['description'] ?? '')) : '';
            $last_error = $desc !== '' ? $desc : '텔레그램 발송 실패';
            error_log('telegram api fail chat_id=' . $chat_id . ' raw=' . $raw);
            continue;
        }
        $sent++;
    }

    if ($sent < 1) {
        return ['ok' => false, 'error' => $last_error ?? '텔레그램 발송 실패', 'sent' => 0];
    }

    return ['ok' => true, 'error' => null, 'sent' => $sent];
}

function telegram_escape_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 1:1 문의 접수 알림
 *
 * @param array{
 *   iq_idx:int,
 *   category:string,
 *   category_label?:string,
 *   name:string,
 *   email:string,
 *   title:string,
 *   content:string,
 *   mb_idx?:int
 * } $inquiry
 */
function telegram_notify_inquiry(array $inquiry): void
{
    if (!telegram_is_ready()) {
        return;
    }

    $idx      = (int) ($inquiry['iq_idx'] ?? 0);
    $cat      = (string) ($inquiry['category_label'] ?? $inquiry['category'] ?? '');
    $name     = (string) ($inquiry['name'] ?? '');
    $email    = (string) ($inquiry['email'] ?? '');
    $title    = (string) ($inquiry['title'] ?? '');
    $content  = trim((string) ($inquiry['content'] ?? ''));
    $mb_idx   = (int) ($inquiry['mb_idx'] ?? 0);

    if (mb_strlen($content) > 500) {
        $content = mb_substr($content, 0, 500) . '…';
    }

    $admin_path = '/admin/inquiry_view.php?idx=' . $idx;
    $admin_url  = function_exists('seo_abs_url')
        ? seo_abs_url($admin_path)
        : $admin_path;

    $lines = [
        '<b>[Pokazone] 1:1 문의 접수</b>',
        '번호: #' . $idx,
        '유형: ' . telegram_escape_html($cat),
        '작성자: ' . telegram_escape_html($name) . ($mb_idx > 0 ? ' (mb#' . $mb_idx . ')' : ''),
        '이메일: ' . telegram_escape_html($email),
        '제목: ' . telegram_escape_html($title),
        '',
        telegram_escape_html($content),
        '',
        '<a href="' . telegram_escape_html($admin_url) . '">관리자에서 보기</a>',
    ];

    try {
        telegram_send_message(implode("\n", $lines), 'HTML');
    } catch (Throwable $e) {
        error_log('telegram_notify_inquiry: ' . $e->getMessage());
    }
}

/**
 * 판매금 출금 신청 알림
 *
 * @param array{
 *   cw_idx:int,
 *   mb_idx?:int,
 *   mb_id?:string,
 *   mb_nick?:string,
 *   amount?:int,
 *   gross?:int,
 *   net?:int,
 *   bank?:string,
 *   holder?:string,
 *   account?:string
 * } $withdraw
 */
function telegram_notify_cash_withdraw(array $withdraw): void
{
    if (!telegram_is_ready()) {
        return;
    }

    $cw_idx  = (int) ($withdraw['cw_idx'] ?? 0);
    $mb_idx  = (int) ($withdraw['mb_idx'] ?? 0);
    $mb_id   = trim((string) ($withdraw['mb_id'] ?? ''));
    $mb_nick = trim((string) ($withdraw['mb_nick'] ?? ''));
    $gross   = (int) ($withdraw['gross'] ?? $withdraw['amount'] ?? 0);
    $net     = (int) ($withdraw['net'] ?? $gross);
    $bank    = trim((string) ($withdraw['bank'] ?? ''));
    $holder  = trim((string) ($withdraw['holder'] ?? ''));
    $account = trim((string) ($withdraw['account'] ?? ''));

    $who = $mb_nick !== '' ? $mb_nick : ($mb_id !== '' ? $mb_id : '회원');
    if ($mb_id !== '' && $mb_nick !== '' && $mb_nick !== $mb_id) {
        $who .= ' (' . $mb_id . ')';
    }
    if ($mb_idx > 0) {
        $who .= ' · mb#' . $mb_idx;
    }

    $admin_path = '/admin/cash_withdraws.php' . ($cw_idx > 0 ? '?q=' . $cw_idx : '');
    $admin_url  = function_exists('seo_abs_url')
        ? seo_abs_url($admin_path)
        : $admin_path;

    $lines = [
        '<b>[Pokazone] 판매금 출금 신청</b>',
        '신청번호: #' . $cw_idx,
        '신청자: ' . telegram_escape_html($who),
        '출금액: ₩' . number_format($net),
        '',
        '<b>정산계좌</b>',
        '은행: ' . telegram_escape_html($bank !== '' ? $bank : '—'),
        '예금주: ' . telegram_escape_html($holder !== '' ? $holder : '—'),
        '계좌: ' . telegram_escape_html($account !== '' ? $account : '—'),
        '',
        '<a href="' . telegram_escape_html($admin_url) . '">출금 신청 관리</a>',
    ];

    try {
        telegram_send_message(implode("\n", $lines), 'HTML');
    } catch (Throwable $e) {
        error_log('telegram_notify_cash_withdraw: ' . $e->getMessage());
    }
}

/**
 * 판매금 출금 취소 알림
 *
 * @param array{
 *   cw_idx:int,
 *   mb_idx?:int,
 *   mb_id?:string,
 *   mb_nick?:string,
 *   amount?:int,
 *   gross?:int,
 *   net?:int,
 *   bank?:string,
 *   holder?:string,
 *   account?:string,
 *   by?:string,
 *   memo?:string
 * } $withdraw
 */
function telegram_notify_cash_withdraw_cancel(array $withdraw): void
{
    if (!telegram_is_ready()) {
        return;
    }

    $cw_idx  = (int) ($withdraw['cw_idx'] ?? 0);
    $mb_idx  = (int) ($withdraw['mb_idx'] ?? 0);
    $mb_id   = trim((string) ($withdraw['mb_id'] ?? ''));
    $mb_nick = trim((string) ($withdraw['mb_nick'] ?? ''));
    $gross   = (int) ($withdraw['gross'] ?? $withdraw['amount'] ?? 0);
    $net     = (int) ($withdraw['net'] ?? $gross);
    $bank    = trim((string) ($withdraw['bank'] ?? ''));
    $holder  = trim((string) ($withdraw['holder'] ?? ''));
    $account = trim((string) ($withdraw['account'] ?? ''));
    $by      = trim((string) ($withdraw['by'] ?? ''));
    $memo    = trim((string) ($withdraw['memo'] ?? ''));

    $who = $mb_nick !== '' ? $mb_nick : ($mb_id !== '' ? $mb_id : '회원');
    if ($mb_id !== '' && $mb_nick !== '' && $mb_nick !== $mb_id) {
        $who .= ' (' . $mb_id . ')';
    }
    if ($mb_idx > 0) {
        $who .= ' · mb#' . $mb_idx;
    }

    $admin_path = '/admin/cash_withdraws.php' . ($cw_idx > 0 ? '?q=' . $cw_idx : '');
    $admin_url  = function_exists('seo_abs_url')
        ? seo_abs_url($admin_path)
        : $admin_path;

    $lines = [
        '<b>[Pokazone] 판매금 출금 취소</b>',
        '신청번호: #' . $cw_idx,
        '신청자: ' . telegram_escape_html($who),
        '취소 금액: ₩' . number_format($net) . ' (캐시 환불)',
    ];
    if ($by !== '') {
        $lines[] = '취소: ' . telegram_escape_html($by);
    }
    if ($memo !== '') {
        $lines[] = '메모: ' . telegram_escape_html($memo);
    }
    $lines[] = '';
    $lines[] = '<b>정산계좌</b>';
    $lines[] = '은행: ' . telegram_escape_html($bank !== '' ? $bank : '—');
    $lines[] = '예금주: ' . telegram_escape_html($holder !== '' ? $holder : '—');
    $lines[] = '계좌: ' . telegram_escape_html($account !== '' ? $account : '—');
    $lines[] = '';
    $lines[] = '<a href="' . telegram_escape_html($admin_url) . '">출금 신청 관리</a>';

    try {
        telegram_send_message(implode("\n", $lines), 'HTML');
    } catch (Throwable $e) {
        error_log('telegram_notify_cash_withdraw_cancel: ' . $e->getMessage());
    }
}
