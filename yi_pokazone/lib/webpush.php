<?php
/**
 * 거래 메시지 Web Push (minishlink/web-push)
 */
declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

function webpush_vendor_autoload(): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }
    require_once $autoload;
    $done = true;

    return true;
}

/** @return array{subject: string, public_key: string, private_key: string}|null */
function webpush_config(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    $path = dirname(__DIR__) . '/config/webpush.php';
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
    $pub  = trim((string) ($c['public_key'] ?? ''));
    $priv = trim((string) ($c['private_key'] ?? ''));
    $subj = trim((string) ($c['subject'] ?? 'mailto:admin@localhost'));
    if ($pub === '' || $priv === '') {
        $cache = false;

        return null;
    }
    $cache = [
        'subject'     => $subj,
        'public_key'  => $pub,
        'private_key' => $priv,
    ];

    return $cache;
}

function webpush_table_ready(): bool
{
    return db_table_exists('tb_web_push_subscription');
}

function webpush_is_ready(): bool
{
    return webpush_table_ready()
        && extension_loaded('gmp')
        && webpush_vendor_autoload()
        && webpush_config() !== null;
}

/**
 * 관리자 점검용 — webpush_is_ready() 각 조건 결과 (메시지함 등에 표시)
 *
 * @return list<array{ok: bool, title: string, hint: string}>
 */
function webpush_readiness_rows(): array
{
    $rows = [];

    $tableOk = webpush_table_ready();
    $rows[] = [
        'ok'    => $tableOk,
        'title' => 'DB 테이블 tb_web_push_subscription',
        'hint'  => $tableOk ? '' : 'MySQL에서 sql/tb_web_push_subscription.sql 을 실행했는지 확인하세요.',
    ];

    $gmpOk = extension_loaded('gmp');
    $rows[] = [
        'ok'    => $gmpOk,
        'title' => 'PHP 확장 gmp',
        'hint'  => $gmpOk ? '' : 'CLI가 아니라 웹(php-fpm·Apache)에 붙은 PHP에 gmp가 있어야 합니다. 설치 후 FPM/Apache를 재시작하세요.',
    ];

    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    $vendorOk     = is_file($autoloadPath) && webpush_vendor_autoload();
    $rows[] = [
        'ok'    => $vendorOk,
        'title' => 'Composer vendor (minishlink/web-push)',
        'hint'  => $vendorOk ? '' : (is_file($autoloadPath) ? 'vendor/autoload.php 는 있으나 로드에 실패했습니다.' : '사이트 루트에서 composer install 로 vendor/ 를 만드세요.'),
    ];

    $cfgOk   = false;
    $cfgHint = '';
    $cfgPath = dirname(__DIR__) . '/config/webpush.php';
    if (!is_file($cfgPath)) {
        $cfgHint = 'config/webpush.php 가 없습니다. config/webpush.example.php 를 복사해 만드세요.';
    } else {
        /** @var mixed $c */
        $c = include $cfgPath;
        if (!is_array($c)) {
            $cfgHint = 'config/webpush.php 가 올바른 설정 배열을 반환하지 않습니다.';
        } else {
            $pub  = trim((string) ($c['public_key'] ?? ''));
            $priv = trim((string) ($c['private_key'] ?? ''));
            if ($pub === '' || $priv === '') {
                $cfgHint = 'public_key 또는 private_key 가 비어 있습니다. php tools/gen_vapid_keys.php 로 키를 생성해 넣으세요.';
            } else {
                $cfgOk = true;
            }
        }
    }
    $rows[] = [
        'ok'    => $cfgOk,
        'title' => 'config/webpush.php (VAPID 공개키·비밀키)',
        'hint'  => $cfgOk ? '' : ($cfgHint !== '' ? $cfgHint : 'config/webpush.example.php 를 참고해 키를 채우세요.'),
    ];

    return $rows;
}

/** 현재 요청이 HTTPS(또는 로컬)로 보이는지 — 리버스 프록시는 X-Forwarded-Proto 권장 */
function webpush_request_looks_https(): bool
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && preg_match('/^(127\\.0\\.0\\.1|localhost)(:\\d+)?$/i', $host)) {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** 브라우저에서 Push 구독 가능한 환경(HTTPS 또는 localhost) */
function webpush_can_use_in_browser(): bool
{
    return webpush_is_ready() && webpush_request_looks_https();
}

/**
 * 인메모리 구독 행에 페이로드 전송. 하나라도 성공하면 true.
 *
 * @param list<array{wp_idx:int|string,endpoint:string,p256dh:string,auth:string}> $rows
 * @param-out array{attempted?: int, success?: int, failures?: list<string>}|null $diag
 */
function webpush_flush_subscription_rows(array $rows, string $payload, ?array &$diag = null): bool
{
    if ($rows === [] || !webpush_is_ready()) {
        return false;
    }
    $cfg = webpush_config();
    if ($cfg === null) {
        return false;
    }

    $auth = [
        'VAPID' => [
            'subject'    => $cfg['subject'],
            'publicKey'  => $cfg['public_key'],
            'privateKey' => $cfg['private_key'],
        ],
    ];

    try {
        if ($diag !== null) {
            $diag = ['attempted' => count($rows), 'success' => 0, 'failures' => []];
        }
        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $endpoint_to_wp = [];
        foreach ($rows as $row) {
            $endpoint = (string) $row['endpoint'];
            $endpoint_to_wp[$endpoint] = (int) $row['wp_idx'];
            $sub = Subscription::create([
                'endpoint' => $endpoint,
                'keys'     => [
                    'p256dh' => (string) $row['p256dh'],
                    'auth'   => (string) $row['auth'],
                ],
            ]);
            $webPush->queueNotification($sub, $payload);
        }

        $any = false;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $any = true;
                if ($diag !== null) {
                    $diag['success']++;
                }
                continue;
            }
            $reason = method_exists($report, 'getReason') ? trim((string) $report->getReason()) : '';
            $code   = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
            if ($reason === '' && $code > 0) {
                $reason = 'HTTP ' . (string) $code;
            }
            if ($diag !== null && $reason !== '') {
                $diag['failures'][] = $reason;
            }
            $epPrev = $report->getEndpoint();
            $epLog  = $epPrev !== '' ? (substr($epPrev, 0, 96) . (strlen($epPrev) > 96 ? '…' : '')) : '';
            error_log('webpush_flush_subscription_rows: 실패 reason=' . ($reason !== '' ? $reason : '(없음)') . ' endpoint=' . $epLog);
            $expired = method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired();
            $gone    = $expired || in_array($code, [400, 403, 404, 410], true);
            if ($gone) {
                $ep = $report->getEndpoint();
                if ($ep !== '' && isset($endpoint_to_wp[$ep])) {
                    webpush_delete_subscription($endpoint_to_wp[$ep]);
                }
            }
        }

        return $any;
    } catch (Throwable $e) {
        error_log('webpush_flush_subscription_rows: ' . $e->getMessage());
        if ($diag !== null) {
            if (!isset($diag['failures'])) {
                $diag['failures'] = [];
            }
            $diag['failures'][] = mb_substr($e->getMessage(), 0, 240);
        }

        return false;
    }
}

/**
 * 구독자에게 암호화 페이로드 전송. 하나라도 성공하면 true.
 *
 * @param-out array{attempted?: int, success?: int, failures?: list<string>}|null $diag
 */
function webpush_deliver_payload(int $mb_idx, string $payload, ?array &$diag = null): bool
{
    if (!webpush_is_ready() || $mb_idx < 1) {
        return false;
    }

    $mb   = (int) $mb_idx;
    $rs   = db_query("SELECT wp_idx, endpoint, p256dh, auth FROM tb_web_push_subscription WHERE mb_idx = {$mb}");
    $rows = [];
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
    if ($rows === []) {
        return false;
    }

    return webpush_flush_subscription_rows($rows, $payload, $diag);
}

/** 저장된 푸시 구독 건수 (기기·브라우저별) */
function webpush_subscription_total(): int
{
    if (!webpush_table_ready()) {
        return 0;
    }
    $rs = db_query('SELECT COUNT(*) AS c FROM tb_web_push_subscription');
    $r  = db_assoc($rs);

    return (int) ($r['c'] ?? 0);
}

/**
 * 모든 구독에 브로드캐스트. 배치로 나눠 전송한다.
 *
 * @param-out array{attempted?: int, success?: int, failed?: int, batches?: int}|null $stats
 */
function webpush_deliver_payload_broadcast(string $payload, ?array &$stats = null): bool
{
    if (!webpush_is_ready() || $payload === '') {
        if ($stats !== null) {
            $stats = ['attempted' => 0, 'success' => 0, 'failed' => 0, 'batches' => 0];
        }

        return false;
    }

    if ($stats !== null) {
        $stats = ['attempted' => 0, 'success' => 0, 'failed' => 0, 'batches' => 0];
    }

    $batchSize = 400;
    $lastId    = 0;
    $any       = false;

    while (true) {
        $rs = db_query(
            "SELECT wp_idx, endpoint, p256dh, auth FROM tb_web_push_subscription
             WHERE wp_idx > {$lastId} ORDER BY wp_idx ASC LIMIT {$batchSize}"
        );
        $rows = [];
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
            $lastId = max($lastId, (int) $r['wp_idx']);
        }
        if ($rows === []) {
            break;
        }

        $batchDiag = null;
        $ok        = webpush_flush_subscription_rows($rows, $payload, $batchDiag);
        if ($ok) {
            $any = true;
        }
        if ($stats !== null) {
            $stats['batches']++;
            $att = (int) ($batchDiag['attempted'] ?? count($rows));
            $suc = (int) ($batchDiag['success'] ?? 0);
            $stats['attempted'] += $att;
            $stats['success'] += $suc;
            $stats['failed'] += max(0, $att - $suc);
        }
    }

    return $any;
}

function webpush_public_key_for_js(): string
{
    $c = webpush_config();

    return $c ? $c['public_key'] : '';
}

function webpush_delete_subscription(int $wp_idx): void
{
    $wp_idx = (int) $wp_idx;
    if ($wp_idx < 1) {
        return;
    }
    db_query("DELETE FROM tb_web_push_subscription WHERE wp_idx = {$wp_idx}");
}

/**
 * 구독 저장 (동일 endpoint 는 덮어씀 — 다른 기기/계정 충돌 시 최근 로그인 기준)
 */
function webpush_save_subscription(int $mb_idx, string $endpoint, string $p256dh, string $auth, ?string $user_agent): bool
{
    if (!webpush_table_ready() || $mb_idx < 1 || $endpoint === '' || $p256dh === '' || $auth === '') {
        return false;
    }
    $hash = hash('sha256', $endpoint);
    $mb_idx        = (int) $mb_idx;
    $esc_endpoint  = db_escape($endpoint);
    $esc_p256      = db_escape($p256dh);
    $esc_auth      = db_escape($auth);
    $esc_ua        = db_escape((string) ($user_agent ?? ''));

    $sql = "
        INSERT INTO tb_web_push_subscription (mb_idx, endpoint, endpoint_hash, p256dh, auth, user_agent)
        VALUES ({$mb_idx}, '{$esc_endpoint}', '" . db_escape($hash) . "', '{$esc_p256}', '{$esc_auth}', " . ($user_agent !== null && $user_agent !== '' ? "'{$esc_ua}'" : 'NULL') . ")
        ON DUPLICATE KEY UPDATE
            mb_idx = VALUES(mb_idx),
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            user_agent = VALUES(user_agent)
    ";

    return (bool) db_query($sql);
}

function webpush_remove_subscription(int $mb_idx, string $endpoint): void
{
    if ($mb_idx < 1 || $endpoint === '' || !webpush_table_ready()) {
        return;
    }
    $hash = db_escape(hash('sha256', $endpoint));
    $mb   = (int) $mb_idx;
    db_query("DELETE FROM tb_web_push_subscription WHERE mb_idx = {$mb} AND endpoint_hash = '{$hash}'");
}

/**
 * 새 거래 메시지 수신자에게 푸시 (실패한 구독은 삭제)
 */
function webpush_notify_trade_message(int $recipient_mb_idx, string $from_nick, string $body_preview, int $tr_idx, int $room_idx): void
{
    if ($recipient_mb_idx < 1) {
        return;
    }

    $path = '/trade/trade_messages.php?tr_idx=' . max(1, $tr_idx);
    if ($room_idx > 0) {
        $path .= '&room_idx=' . (int) $room_idx;
    }
    $open_url = seo_abs_url($path);

    $title = '거래 메시지 · ' . $from_nick;
    $body  = seo_clean_desc($body_preview, 120);
    /* 알림 tag 가 매번 달라야 이전 알림을 덮어쓰지 않고 소리·헤드업이 납니다 */
    $notify_tag = 'pz-tm-' . max(1, $room_idx) . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $recipient_mb_idx, $payload);
}

/**
 * 거래글에 공개 댓글이 달렸을 때 판매자에게
 */
function webpush_notify_trade_comment(
    int $seller_mb_idx,
    string $commenter_nick,
    string $trade_title,
    string $comment_preview,
    int $tr_idx
): void {
    if ($seller_mb_idx < 1 || $tr_idx < 1) {
        return;
    }

    $path     = '/trade/trade_view.php?idx=' . $tr_idx . '#comments';
    $open_url = seo_abs_url($path);

    $nick = trim(strip_tags($commenter_nick));
    if ($nick === '') {
        $nick = '회원';
    }
    if (mb_strlen($nick) > 20) {
        $nick = mb_substr($nick, 0, 20) . '…';
    }

    $title_short = trim(strip_tags($trade_title));
    if ($title_short === '') {
        $title_short = '거래글';
    }
    if (mb_strlen($title_short) > 36) {
        $title_short = mb_substr($title_short, 0, 36) . '…';
    }

    $preview = seo_clean_desc($comment_preview, 80);
    if ($preview === '') {
        $preview = '새 댓글이 등록되었습니다.';
    }

    $title      = '거래 댓글 · ' . $nick;
    $body       = '"' . $title_short . '" — ' . $preview;
    $notify_tag = 'pz-tc-' . $tr_idx . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $seller_mb_idx, $payload);
}

/**
 * 거래글 댓글에 판매자 답글이 달렸을 때 원댓글 작성자에게
 */
function webpush_notify_trade_comment_reply(
    int $commenter_mb_idx,
    string $seller_nick,
    string $trade_title,
    string $reply_preview,
    int $tr_idx,
    int $parent_tc_idx = 0
): void {
    if ($commenter_mb_idx < 1 || $tr_idx < 1) {
        return;
    }

    $hash = $parent_tc_idx > 0 ? ('#tc-' . $parent_tc_idx) : '#comments';
    $path     = '/trade/trade_view.php?idx=' . $tr_idx . $hash;
    $open_url = seo_abs_url($path);

    $nick = trim(strip_tags($seller_nick));
    if ($nick === '') {
        $nick = '판매자';
    }
    if (mb_strlen($nick) > 20) {
        $nick = mb_substr($nick, 0, 20) . '…';
    }

    $title_short = trim(strip_tags($trade_title));
    if ($title_short === '') {
        $title_short = '거래글';
    }
    if (mb_strlen($title_short) > 36) {
        $title_short = mb_substr($title_short, 0, 36) . '…';
    }

    $preview = seo_clean_desc($reply_preview, 80);
    if ($preview === '') {
        $preview = '판매자 답글이 등록되었습니다.';
    }

    $title      = '거래 답글 · ' . $nick;
    $body       = '"' . $title_short . '" — ' . $preview;
    $notify_tag = 'pz-tcr-' . $tr_idx . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $commenter_mb_idx, $payload);
}

/**
 * 경매 — 내 최고 입찰이 더 높은 금액으로 추월되었을 때
 */
function webpush_notify_auction_outbid(
    int $recipient_mb_idx,
    string $auction_title,
    int $new_amount,
    int $au_idx
): void {
    if ($recipient_mb_idx < 1 || $au_idx < 1 || $new_amount < 1) {
        return;
    }

    $path = '/auction/auction_view.php?idx=' . $au_idx;
    $open_url = seo_abs_url($path);

    $title_short = trim(strip_tags($auction_title));
    if ($title_short === '') {
        $title_short = '경매';
    }
    if (mb_strlen($title_short) > 36) {
        $title_short = mb_substr($title_short, 0, 36) . '…';
    }

    $title = '경매 · 입찰 추월';
    $body  = '"' . $title_short . '"에 ₩' . number_format($new_amount) . ' 입찰이 들어왔습니다. 내 입찰이 추월되었어요.';
    $notify_tag = 'pz-au-' . $au_idx . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $recipient_mb_idx, $payload);
}

/**
 * 경매 — 낙찰 확정 시 낙찰자에게
 */
function webpush_notify_auction_won(
    int $recipient_mb_idx,
    string $auction_title,
    int $win_amount,
    int $au_idx
): void {
    if ($recipient_mb_idx < 1 || $au_idx < 1 || $win_amount < 1) {
        return;
    }

    $path     = '/auction/auction_order.php?au_idx=' . $au_idx;
    $open_url = seo_abs_url($path);

    $title_short = trim(strip_tags($auction_title));
    if ($title_short === '') {
        $title_short = '경매';
    }
    if (mb_strlen($title_short) > 36) {
        $title_short = mb_substr($title_short, 0, 36) . '…';
    }

    $title = '경매 · 낙찰 축하!';
    $body  = '"' . $title_short . '"에 ₩' . number_format($win_amount) . ' 으로 낙찰되었습니다. 배송·결제 정보를 입력해 주세요.';
    $notify_tag = 'pz-au-won-' . $au_idx . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $recipient_mb_idx, $payload);
}

/**
 * 경매 — 낙찰자 결제 완료 시 판매자에게 (발송 요청)
 */
function webpush_notify_auction_order_paid(
    int $seller_mb_idx,
    string $auction_title,
    string $buyer_nick,
    int $amount,
    int $au_idx
): void {
    if ($seller_mb_idx < 1 || $au_idx < 1 || $amount < 1) {
        return;
    }

    $path     = '/auction/auction_order.php?au_idx=' . $au_idx;
    $open_url = seo_abs_url($path);

    $title_short = trim(strip_tags($auction_title));
    if ($title_short === '') {
        $title_short = '경매';
    }
    if (mb_strlen($title_short) > 36) {
        $title_short = mb_substr($title_short, 0, 36) . '…';
    }

    $nick = trim(strip_tags($buyer_nick));
    if ($nick === '') {
        $nick = '구매자';
    }
    if (mb_strlen($nick) > 20) {
        $nick = mb_substr($nick, 0, 20) . '…';
    }

    $title      = '경매 · 결제 완료';
    $body       = $nick . '님이 "' . $title_short . '" 결제(₩' . number_format($amount) . ')를 완료했습니다. 택배 발송을 진행해 주세요.';
    $notify_tag = 'pz-au-paid-' . $au_idx . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $seller_mb_idx, $payload);
}

/**
 * 경매 — 판매자 운송장 등록 시 낙찰자(구매자)에게
 */
function webpush_notify_auction_tracking(
    int $buyer_mb_idx,
    string $auction_title,
    string $courier_name,
    string $tracking_no,
    int $au_idx
): void {
    if ($buyer_mb_idx < 1 || $au_idx < 1 || $courier_name === '' || $tracking_no === '') {
        return;
    }

    $path     = '/auction/auction_order.php?au_idx=' . $au_idx;
    $open_url = seo_abs_url($path);

    $title_short = trim(strip_tags($auction_title));
    if ($title_short === '') {
        $title_short = '경매';
    }
    if (mb_strlen($title_short) > 36) {
        $title_short = mb_substr($title_short, 0, 36) . '…';
    }

    $courier = trim(strip_tags($courier_name));
    if (mb_strlen($courier) > 24) {
        $courier = mb_substr($courier, 0, 24) . '…';
    }

    $title      = '경매 · 배송 시작';
    $body       = '"' . $title_short . '" 운송장이 등록되었습니다. ' . $courier . ' ' . $tracking_no;
    $notify_tag = 'pz-au-track-' . $au_idx . '-' . str_replace('.', '', uniqid('', true));

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $open_url,
        'tag'   => $notify_tag,
    ], JSON_UNESCAPED_UNICODE);

    webpush_deliver_payload((int) $buyer_mb_idx, $payload);
}
