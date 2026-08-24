<?php
/**
 * 냥카라 — 최대 8명 실시간
 * URL: /page/baccarat.php?code=XXXX  (호환) · /page/nyangkara.php?code=XXXX
 */
require __DIR__ . '/_wallet_preauth.php';

$req_action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
if ($req_action === '') {
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
        게임포기_페이지가드($__gqNick, (string)($__gq['code'] ?? ''), '냥카라');
    }
}

require __DIR__ . '/../api/game/baccarat_web.php';
