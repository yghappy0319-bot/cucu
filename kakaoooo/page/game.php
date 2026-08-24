<?php
/**
 * 홀짝 도전 게임 웹 (호환 URL)
 *
 * 실제 진입점: api/game/odd_even_web.php 예) /api/game/odd_even_web.php
 */
require __DIR__ . '/_wallet_preauth.php';
require_once __DIR__ . '/_game_quit_guard.php';
$__gq = $GLOBALS['wallet_preauth'] ?? null;
if (is_array($__gq) && !empty($__gq['row']['name'])) {
    $__gqNick = (string)$__gq['row']['name'];
    if (function_exists('getTwoCharNick')) {
        $__p = getTwoCharNick($__gqNick);
        if ($__p !== '') {
            $__gqNick = $__p;
        }
    }
    게임포기_페이지가드($__gqNick, (string)($__gq['code'] ?? ''), '홀짝');
}
require __DIR__ . '/../api/game/odd_even_web.php';
