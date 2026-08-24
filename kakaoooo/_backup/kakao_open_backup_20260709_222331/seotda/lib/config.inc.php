<?php
/**
 * 섯다 웹 설정 (독립 모듈)
 */

if (!defined('SEOTDA_SYSTEM_NICK')) {
    define('SEOTDA_SYSTEM_NICK', 'SYSTEM');
}
if (!defined('SEOTDA_MIN_BET')) {
    define('SEOTDA_MIN_BET', 30000000); // 3천만
}
if (!defined('SEOTDA_MIN_ACCESS')) {
    define('SEOTDA_MIN_ACCESS', 1000000000000); // 1조 — 입장 최소 게임냥
}
if (!defined('SEOTDA_DEFAULT_BET')) {
    define('SEOTDA_DEFAULT_BET', 100000000); // 1억
}
if (!defined('SEOTDA_WAITING_SEC')) {
    define('SEOTDA_WAITING_SEC', 90);
}
if (!defined('SEOTDA_ROOM_TOKEN_LEN')) {
    define('SEOTDA_ROOM_TOKEN_LEN', 8);
}
if (!defined('SEOTDA_ONLINE_SEC')) {
    define('SEOTDA_ONLINE_SEC', 12); // status heartbeat 유효 시간 (빠른 목록 반영)
}
if (!defined('SEOTDA_POLL_BROWSE_MS')) {
    define('SEOTDA_POLL_BROWSE_MS', 800); // 상대 찾기 목록
}
if (!defined('SEOTDA_POLL_MATCH_MS')) {
    define('SEOTDA_POLL_MATCH_MS', 1000); // 대기·PvP·입장 협상
}
if (!defined('SEOTDA_POLL_SOLO_MS')) {
    define('SEOTDA_POLL_SOLO_MS', 900); // 솔로 플레이 중
}
if (!defined('SEOTDA_GUEST_ACTION_SEC')) {
    define('SEOTDA_GUEST_ACTION_SEC', 30);
}
if (!defined('SEOTDA_READY_RESPONSE_SEC')) {
    define('SEOTDA_READY_RESPONSE_SEC', 30);
}
if (!defined('SEOTDA_SOLO_PLAYER_WIN_PCT')) {
    define('SEOTDA_SOLO_PLAYER_WIN_PCT', 54); // peek 없을 때만 반영
}
if (!defined('SEOTDA_SOLO_BOTH_LOSE_PCT')) {
    define('SEOTDA_SOLO_BOTH_LOSE_PCT', 6); // 솔로 둘 다 패 판 비율 % (1승1패 외 살짝 지는 쪽)
}
if (!defined('SEOTDA_SOLO_PICK_HINT_NICK')) {
    define('SEOTDA_SOLO_PICK_HINT_NICK', '준호'); // 솔로 패 선택 시 승패 표시 (점검용, 빈 문자열=비활성)
}

if (!function_exists('seotda_solo_pick_hints_enabled')) {
    /** 솔로 선택 패 승률 힌트 표시 여부 (점검용) */
    function seotda_solo_pick_hints_enabled($host_nick) {
        $hint_nick = defined('SEOTDA_SOLO_PICK_HINT_NICK') ? trim((string)SEOTDA_SOLO_PICK_HINT_NICK) : '';
        if ($hint_nick === '') {
            return false;
        }
        $nick = function_exists('seotda_nick') ? seotda_nick($host_nick) : trim((string)$host_nick);
        return $nick === $hint_nick;
    }
}
