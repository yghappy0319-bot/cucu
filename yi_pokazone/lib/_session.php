<?php
/**
 * 회원: 기본 PHP 세션(PHPSESSID)
 * 관리자: 별도 쿠키(PZADSESSID) + 파일 저장
 *
 * PHP는 한 요청에서 session_name()을 바꿔 세션을 두 번 열면
 * 같은 파일을 가리키거나 Set-Cookie가 덮여 관리자 로그인이 새로고침에 풀린다.
 */

if (!defined('PZ_MEMBER_SESSION_NAME')) {
    $__pz_member_sess = ini_get('session.name');
    define('PZ_MEMBER_SESSION_NAME', is_string($__pz_member_sess) && $__pz_member_sess !== '' ? $__pz_member_sess : 'PHPSESSID');
    unset($__pz_member_sess);
}
if (!defined('PZ_ADMIN_SESSION_NAME')) {
    define('PZ_ADMIN_SESSION_NAME', 'PZADSESSID');
}

$GLOBALS['_PZ_ADMIN'] = [
    'ad_idx'   => 0,
    'ad_id'    => '',
    'ad_name'  => '',
    'ad_level' => 1,
];

function pz_session_cookie_params(): array
{
    $ini = session_get_cookie_params();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $samesite = (string) ($ini['samesite'] ?? '');
    if ($samesite === '' || (strtolower($samesite) === 'none' && !$secure)) {
        $samesite = 'Lax';
    }

    return [
        'lifetime' => (int) ($ini['lifetime'] ?? 0),
        'path'     => '/',
        'domain'   => (string) ($ini['domain'] ?? ''),
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => $samesite,
    ];
}

function pz_session_apply_cookie_params(): void
{
    $p = pz_session_cookie_params();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($p);

        return;
    }
    session_set_cookie_params($p['lifetime'], $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

function pz_session_close_if_active(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function pz_valid_session_id(string $id): bool
{
    return $id !== '' && (bool) preg_match('/^[A-Za-z0-9,-]{16,256}$/', $id);
}

function pz_new_session_id(): string
{
    return bin2hex(random_bytes(16));
}

function pz_emit_session_cookie(string $name, string $id, int $expires = 0): void
{
    $p = pz_session_cookie_params();
    $opts = [
        'expires'  => $expires > 0 ? $expires : ($p['lifetime'] > 0 ? time() + $p['lifetime'] : 0),
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ];
    if ($p['domain'] !== '') {
        $opts['domain'] = $p['domain'];
    }
    setcookie($name, $id, $opts);
    if ($id === '') {
        unset($_COOKIE[$name]);
    } else {
        $_COOKIE[$name] = $id;
    }
}

/**
 * @return array{ad_idx:int,ad_id:string,ad_name:string,ad_level:int}
 */
function pz_admin_session_normalize(?array $src): array
{
    return [
        'ad_idx'   => (int) ($src['ad_idx'] ?? 0),
        'ad_id'    => (string) ($src['ad_id'] ?? ''),
        'ad_name'  => (string) ($src['ad_name'] ?? ''),
        'ad_level' => (int) ($src['ad_level'] ?? 1),
    ];
}

function pz_admin_store_dir(): string
{
    $candidates = [];
    $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'admin_sess';

    $save = (string) session_save_path();
    if (strpos($save, ';') !== false) {
        $parts = explode(';', $save);
        $save = (string) end($parts);
    }
    $save = trim($save);
    if ($save !== '') {
        $candidates[] = $save;
    }
    $candidates[] = sys_get_temp_dir();

    foreach ($candidates as $dir) {
        $dir = rtrim((string) $dir, '/\\');
        if ($dir === '') {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    return rtrim((string) sys_get_temp_dir(), '/\\');
}

function pz_admin_store_path(string $id): string
{
    return pz_admin_store_dir() . DIRECTORY_SEPARATOR . 'pzad_' . $id;
}

function pz_admin_max_lifetime(): int
{
    $ttl = (int) ini_get('session.gc_maxlifetime');

    return $ttl > 0 ? $ttl : 1440;
}

function pz_admin_store_read(string $id): ?array
{
    if (!pz_valid_session_id($id)) {
        return null;
    }
    $file = pz_admin_store_path($id);
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }
    $ttl = pz_admin_max_lifetime();
    if ($ttl > 0 && (time() - (int) filemtime($file)) > $ttl) {
        @unlink($file);

        return null;
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    @touch($file);

    return pz_admin_session_normalize($data);
}

function pz_admin_store_write(string $id, array $ad): bool
{
    if (!pz_valid_session_id($id)) {
        return false;
    }
    $dir = pz_admin_store_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }
    $payload = json_encode(pz_admin_session_normalize($ad), JSON_UNESCAPED_UNICODE);
    if (!is_string($payload)) {
        return false;
    }

    return @file_put_contents(pz_admin_store_path($id), $payload, LOCK_EX) !== false;
}

function pz_admin_store_delete(string $id): void
{
    if (!pz_valid_session_id($id)) {
        return;
    }
    $file = pz_admin_store_path($id);
    if (is_file($file)) {
        @unlink($file);
    }
}

function pz_admin_cookie_id(): string
{
    $id = isset($_COOKIE[PZ_ADMIN_SESSION_NAME]) ? (string) $_COOKIE[PZ_ADMIN_SESSION_NAME] : '';

    return pz_valid_session_id($id) ? $id : '';
}

function pz_member_session_resume(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    pz_session_apply_cookie_params();
    session_name(PZ_MEMBER_SESSION_NAME);
    @session_start();
}

/**
 * @return array{ad_idx:int,ad_id:string,ad_name:string,ad_level:int}
 */
function pz_admin_session_snapshot(): array
{
    return pz_admin_session_normalize($GLOBALS['_PZ_ADMIN'] ?? null);
}

function pz_sessions_boot(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    pz_session_close_if_active();
    pz_member_session_resume();

    $ad = pz_admin_session_normalize(null);
    $admin_id = pz_admin_cookie_id();
    if ($admin_id !== '') {
        $loaded = pz_admin_store_read($admin_id);
        if ($loaded !== null && $loaded['ad_idx'] > 0) {
            $ad = $loaded;
        }
    }

    $legacy = pz_admin_session_normalize($_SESSION);
    if ($ad['ad_idx'] < 1 && $legacy['ad_idx'] > 0) {
        unset($_SESSION['ad_idx'], $_SESSION['ad_id'], $_SESSION['ad_name'], $_SESSION['ad_level']);
        pz_admin_session_write($legacy);

        return;
    }

    unset($_SESSION['ad_idx'], $_SESSION['ad_id'], $_SESSION['ad_name'], $_SESSION['ad_level']);
    $GLOBALS['_PZ_ADMIN'] = $ad;
}

function pz_admin_login_commit(array $ad): void
{
    $old = pz_admin_cookie_id();
    if ($old !== '') {
        pz_admin_store_delete($old);
    }
    $ad = pz_admin_session_normalize($ad);
    $GLOBALS['_PZ_ADMIN'] = $ad;
    $id = pz_new_session_id();
    if (!pz_admin_store_write($id, $ad)) {
        error_log('Pokazone admin session write failed: ' . pz_admin_store_path($id));
    }
    pz_emit_session_cookie(PZ_ADMIN_SESSION_NAME, $id);
}

function pz_admin_session_write(array $ad): void
{
    $ad = pz_admin_session_normalize($ad);
    $GLOBALS['_PZ_ADMIN'] = $ad;

    $id = pz_admin_cookie_id();
    if ($id === '') {
        $id = pz_new_session_id();
    }
    if (!pz_admin_store_write($id, $ad)) {
        $id = pz_new_session_id();
        pz_admin_store_write($id, $ad);
    }
    pz_emit_session_cookie(PZ_ADMIN_SESSION_NAME, $id);
}

function pz_admin_session_regenerate(): void
{
    $old = pz_admin_cookie_id();
    $ad = $GLOBALS['_PZ_ADMIN'] ?? pz_admin_session_normalize(null);
    if ($old !== '') {
        $loaded = pz_admin_store_read($old);
        if ($loaded !== null) {
            $ad = $loaded;
        }
        pz_admin_store_delete($old);
    }
    $id = pz_new_session_id();
    if ((int) ($ad['ad_idx'] ?? 0) > 0) {
        pz_admin_store_write($id, $ad);
    }
    pz_emit_session_cookie(PZ_ADMIN_SESSION_NAME, $id);
}

function pz_admin_session_destroy(): void
{
    $GLOBALS['_PZ_ADMIN'] = pz_admin_session_normalize(null);
    $id = pz_admin_cookie_id();
    if ($id !== '') {
        pz_admin_store_delete($id);
    }
    pz_emit_session_cookie(PZ_ADMIN_SESSION_NAME, '', time() - 42000);
}

function pz_member_session_destroy(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        pz_member_session_resume();
    }
    $_SESSION = [];
    pz_emit_session_cookie(PZ_MEMBER_SESSION_NAME, '', time() - 42000);
    @session_destroy();
}
