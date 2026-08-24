<?php
/**
 * 채굴 웹 (호환 URL)
 * 실제: /api/game/mining_web.php?code=XXXX
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
    게임포기_페이지가드($__gqNick, (string)($__gq['code'] ?? ''), '채굴');
}
require __DIR__ . '/../api/game/mining_web.php';
