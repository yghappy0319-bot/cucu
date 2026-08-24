<?php
/**
 * 방 보스 (호환 URL)
 * 실제: /api/game/boss_web.php?code=XXXX
 *
 * 진입 HTML은 가볍게 (function.php / boss_raid 미로드) · 상태는 AJAX
 * HTML은 DB/include 전에 문서 뼈대를 먼저 flush 해 체감 진입을 유지한다.
 */
$__boss_action = trim((string)($_REQUEST['action'] ?? ''));
$__boss_is_html = ($__boss_action === '');

if (!isset($GLOBALS['wallet_preauth'])) {
    $GLOBALS['wallet_preauth'] = null;
}

$__wallet_code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($__wallet_code === '' && isset($_REQUEST['code'])) {
    $__wallet_code = trim((string)$_REQUEST['code']);
}
if ($__wallet_code === '' && isset($_POST['wallet_code'])) {
    $__wallet_code = trim((string)$_POST['wallet_code']);
}
if ($__wallet_code === '' && isset($_COOKIE['wallet_code'])) {
    $__wallet_code = trim((string)$_COOKIE['wallet_code']);
}

if ($__wallet_code !== '' && empty($GLOBALS['wallet_preauth'])) {
    // DB만 — api/function.php(1만줄+)는 AJAX 때 로드
    if (!function_exists('db_select')) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    }
    $__esc = addslashes($__wallet_code);
    // 게임포기 컬럼이 없을 수 있어 기본 SELECT 후 폴백
    $__row = @db_select("SELECT idx, name, CAST(point AS CHAR) AS point, IFNULL(게임포기, 0) AS 게임포기 FROM tb_member WHERE code = '{$__esc}' LIMIT 1");
    if (!$__row) {
        $__row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point FROM tb_member WHERE code = '{$__esc}' LIMIT 1");
    }
    if (!empty($__row['name'])) {
        $GLOBALS['wallet_preauth'] = [
            'code' => $__wallet_code,
            'row' => $__row,
        ];
    }
}

// 게임포기 중이면 HTML flush 전에 차단
if ($__boss_is_html && !empty($GLOBALS['wallet_preauth']['row']['name'])
    && (int)($GLOBALS['wallet_preauth']['row']['게임포기'] ?? 0) === 1) {
    require_once __DIR__ . '/_game_quit_guard.php';
    $__gqNick = (string)$GLOBALS['wallet_preauth']['row']['name'];
    if (!function_exists('getTwoCharNick') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    }
    if (function_exists('getTwoCharNick')) {
        $__p = getTwoCharNick($__gqNick);
        if ($__p !== '') {
            $__gqNick = $__p;
        }
    }
    게임포기_페이지가드($__gqNick, (string)($GLOBALS['wallet_preauth']['code'] ?? ''), '보스');
}

if ($__boss_is_html) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
    }
    // 진단용 test가 아니라 실제 셸을 먼저 보냄 → DB 전에도 화면이 바로 열림
    echo "<!DOCTYPE html>\n<html lang=\"ko\"><head><meta charset=\"UTF-8\">";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">';
    echo '<title>보스</title>';
    echo '<style>html,body{margin:0;background:#070b14;color:#e8eef8}</style>';
    echo '<link href="/css/style.css" rel="stylesheet">';
    echo '<link href="/css/boss.css" rel="stylesheet">';
    echo '</head><body>';
    echo str_repeat(' ', 2048) . "\n";
    @flush();
    $GLOBALS['boss_early_html'] = true;
}

require __DIR__ . '/../api/game/boss_web.php';
