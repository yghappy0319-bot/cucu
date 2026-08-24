<?php
/**
 * 섯다 1:1 리액션 이모티콘 (고정 8종)
 */

if (!defined('SEOTDA_EMOTE_COOLDOWN_SEC')) {
    define('SEOTDA_EMOTE_COOLDOWN_SEC', 3);
}
if (!defined('SEOTDA_EMOTE_DISPLAY_SEC')) {
    define('SEOTDA_EMOTE_DISPLAY_SEC', 12);
}

if (!function_exists('seotda_emote_catalog')) {
    /** @return array<string,string> key => emoji */
    function seotda_emote_catalog() {
        return [
            'wave' => '👋',
            'laugh' => '😂',
            'angry' => '😤',
            'thumbs' => '👍',
            'pray' => '🙏',
            'fire' => '🔥',
            'skull' => '💀',
            'party' => '🎉',
        ];
    }
}

if (!function_exists('seotda_emote_keys_list')) {
    function seotda_emote_keys_list() {
        return array_keys(seotda_emote_catalog());
    }
}

if (!function_exists('seotda_emote_resolve')) {
    function seotda_emote_resolve($key) {
        $key = trim((string)$key);
        $cat = seotda_emote_catalog();
        return isset($cat[$key]) ? $cat[$key] : null;
    }
}

if (!function_exists('seotda_has_emote_columns')) {
    function seotda_has_emote_columns() {
        if (array_key_exists('seotda_has_emote_columns', $GLOBALS)) {
            return (bool)$GLOBALS['seotda_has_emote_columns'];
        }
        if (!function_exists('db_select')) {
            $GLOBALS['seotda_has_emote_columns'] = false;
            return false;
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            $GLOBALS['seotda_has_emote_columns'] = false;
            return false;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_seotda_room LIKE 'host_emote'");
        $GLOBALS['seotda_has_emote_columns'] = !empty($col['Field']);
        return $GLOBALS['seotda_has_emote_columns'];
    }
}

if (!function_exists('seotda_schema_ensure_emote_columns')) {
    function seotda_schema_ensure_emote_columns() {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            return;
        }
        if (seotda_has_emote_columns()) {
            return;
        }

        @db_query("
            ALTER TABLE tb_seotda_room
            ADD COLUMN host_emote VARCHAR(16) DEFAULT NULL COMMENT '호스트 최근 리액션' AFTER online_at,
            ADD COLUMN host_emote_at DATETIME DEFAULT NULL COMMENT '호스트 리액션 시각' AFTER host_emote,
            ADD COLUMN guest_emote VARCHAR(16) DEFAULT NULL COMMENT '게스트 최근 리액션' AFTER host_emote_at,
            ADD COLUMN guest_emote_at DATETIME DEFAULT NULL COMMENT '게스트 리액션 시각' AFTER guest_emote
        ");
        unset($GLOBALS['seotda_has_emote_columns']);
        seotda_has_emote_columns();
    }
}

if (!function_exists('seotda_room_has_pvp_opponent')) {
    function seotda_room_has_pvp_opponent(array $room) {
        $guest = trim((string)($room['guest_nick'] ?? ''));
        if ($guest === '' || $guest === SEOTDA_SYSTEM_NICK) {
            return false;
        }
        $mode = (string)($room['mode'] ?? '');
        return in_array($mode, ['match_pending', 'waiting', 'pvp'], true);
    }
}

if (!function_exists('seotda_emote_enabled_for_room')) {
    function seotda_emote_enabled_for_room(array $room, $viewer_nick) {
        if (!seotda_room_has_pvp_opponent($room)) {
            return false;
        }
        $host = (string)($room['host_nick'] ?? '');
        $guest = (string)($room['guest_nick'] ?? '');
        $viewer = trim((string)$viewer_nick);
        return $viewer === $host || $viewer === $guest;
    }
}

if (!function_exists('seotda_emote_send')) {
    /**
     * @return array{ok:bool,data?:string,room?:array}
     */
    function seotda_emote_send(array $room, $nick, $emote_key) {
        if (!seotda_has_emote_columns()) {
            return ['ok' => false, 'data' => '❌ 이모티콘 기능을 사용할 수 없어요. DB 마이그레이션을 확인해주세요.'];
        }
        if (!seotda_emote_enabled_for_room($room, $nick)) {
            return ['ok' => false, 'data' => '❌ 1:1 대결 중에만 이모티콘을 보낼 수 있어요.'];
        }

        $emoji = seotda_emote_resolve($emote_key);
        if ($emoji === null) {
            return ['ok' => false, 'data' => '❌ 잘못된 이모티콘입니다.'];
        }

        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        $is_host = ($nick === $host);
        $is_guest = ($nick === $guest);
        if (!$is_host && !$is_guest) {
            return ['ok' => false, 'data' => '❌ 참가자만 보낼 수 있어요.'];
        }

        $prefix = $is_host ? 'host' : 'guest';
        $last_at = !empty($room[$prefix . '_emote_at']) ? strtotime($room[$prefix . '_emote_at']) : 0;
        if ($last_at > 0 && (time() - $last_at) < (int)SEOTDA_EMOTE_COOLDOWN_SEC) {
            $wait = (int)SEOTDA_EMOTE_COOLDOWN_SEC - (time() - $last_at);
            return ['ok' => false, 'data' => '⏱ ' . max(1, $wait) . '초 후 다시 보낼 수 있어요.'];
        }

        $id = (int)($room['idx'] ?? 0);
        if ($id <= 0) {
            return ['ok' => false, 'data' => '❌ 방 정보를 찾을 수 없어요.'];
        }

        $key_esc = addslashes($emote_key);
        db_query("
            UPDATE tb_seotda_room
            SET {$prefix}_emote = '{$key_esc}',
                {$prefix}_emote_at = NOW()
            WHERE idx = {$id} LIMIT 1
        ");

        $fresh = db_select("SELECT * FROM tb_seotda_room WHERE idx = {$id} LIMIT 1");
        if (!is_array($fresh) || empty($fresh['room_token'])) {
            return ['ok' => false, 'data' => '❌ 전송에 실패했어요.'];
        }

        return [
            'ok' => true,
            'data' => $emoji . ' 전송!',
            'room' => $fresh,
        ];
    }
}

if (!function_exists('seotda_emote_stored_to_emoji')) {
    function seotda_emote_stored_to_emoji($stored) {
        $stored = trim((string)$stored);
        if ($stored === '') {
            return '';
        }
        $emoji = seotda_emote_resolve($stored);
        if ($emoji !== null) {
            return $emoji;
        }
        $cat = seotda_emote_catalog();
        if (in_array($stored, $cat, true)) {
            return $stored;
        }
        return $stored;
    }
}

if (!function_exists('seotda_emote_recent_for_viewer')) {
    /**
     * 상대가 보낸 최근 이모티콘 (표시용)
     *
     * @return array{emoji:string,from_nick:string,at:string,stamp:int}|null
     */
    function seotda_emote_recent_for_viewer(array $room, $viewer_nick) {
        if (!seotda_has_emote_columns() || !seotda_emote_enabled_for_room($room, $viewer_nick)) {
            return null;
        }

        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        $viewer = trim((string)$viewer_nick);

        if ($viewer === $host) {
            $stored = trim((string)($room['guest_emote'] ?? ''));
            $at_raw = $room['guest_emote_at'] ?? null;
            $from = $guest;
        } elseif ($viewer === $guest) {
            $stored = trim((string)($room['host_emote'] ?? ''));
            $at_raw = $room['host_emote_at'] ?? null;
            $from = $host;
        } else {
            return null;
        }

        $emoji = seotda_emote_stored_to_emoji($stored);
        if ($emoji === '' || empty($at_raw)) {
            return null;
        }

        $stamp = (int)strtotime($at_raw);
        if ($stamp <= 0 || (time() - $stamp) > (int)SEOTDA_EMOTE_DISPLAY_SEC) {
            return null;
        }

        return [
            'emoji' => $emoji,
            'from_nick' => $from,
            'at' => date('Y-m-d H:i:s', $stamp),
            'stamp' => $stamp,
            'key' => $stored,
        ];
    }
}

if (!function_exists('seotda_emote_public_catalog')) {
    /** @return array<int,array{key:string,emoji:string}> */
    function seotda_emote_public_catalog() {
        $out = [];
        foreach (seotda_emote_catalog() as $key => $emoji) {
            $out[] = ['key' => $key, 'emoji' => $emoji];
        }
        return $out;
    }
}
