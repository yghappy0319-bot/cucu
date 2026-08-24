<?php
/**
 * 1:1 대결 중 배팅금 변경 (상대 수락 필요)
 */

if (!defined('SEOTDA_BET_PENDING_SEC')) {
    define('SEOTDA_BET_PENDING_SEC', 120);
}

if (!function_exists('seotda_has_bet_pending_columns')) {
    function seotda_has_bet_pending_columns() {
        if (array_key_exists('seotda_has_bet_pending_columns', $GLOBALS)) {
            return (bool)$GLOBALS['seotda_has_bet_pending_columns'];
        }
        if (!function_exists('db_select')) {
            $GLOBALS['seotda_has_bet_pending_columns'] = false;
            return false;
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            $GLOBALS['seotda_has_bet_pending_columns'] = false;
            return false;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_seotda_room LIKE 'pending_bet_amount'");
        $GLOBALS['seotda_has_bet_pending_columns'] = !empty($col['Field']);
        return $GLOBALS['seotda_has_bet_pending_columns'];
    }
}

if (!function_exists('seotda_schema_ensure_bet_pending_columns')) {
    function seotda_schema_ensure_bet_pending_columns() {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            return;
        }
        if (seotda_has_bet_pending_columns()) {
            return;
        }

        @db_query("
            ALTER TABLE tb_seotda_room
            ADD COLUMN pending_bet_amount BIGINT UNSIGNED DEFAULT NULL COMMENT '배팅 변경 요청액' AFTER guest_emote_at,
            ADD COLUMN pending_bet_by VARCHAR(32) DEFAULT NULL COMMENT '배팅 변경 요청자' AFTER pending_bet_amount,
            ADD COLUMN pending_bet_at DATETIME DEFAULT NULL COMMENT '배팅 변경 요청 시각' AFTER pending_bet_by
        ");
        unset($GLOBALS['seotda_has_bet_pending_columns']);
        seotda_has_bet_pending_columns();
    }
}

if (!function_exists('seotda_bet_change_allowed')) {
    function seotda_bet_change_allowed(array $room) {
        $guest = trim((string)($room['guest_nick'] ?? ''));
        if ($guest === '' || $guest === SEOTDA_SYSTEM_NICK) {
            return false;
        }
        $mode = (string)($room['mode'] ?? '');
        if ($mode === 'pvp' || (string)($room['pvp_phase'] ?? '') === 'playing') {
            return false;
        }
        if ((string)($room['solo_phase'] ?? '') === 'playing') {
            return false;
        }
        return in_array($mode, ['waiting', 'match_pending'], true);
    }
}

if (!function_exists('seotda_bet_pending_clear')) {
    function seotda_bet_pending_clear($room_id) {
        $room_id = (int)$room_id;
        if ($room_id <= 0) {
            return;
        }
        @db_query("
            UPDATE tb_seotda_room
            SET pending_bet_amount = NULL,
                pending_bet_by = NULL,
                pending_bet_at = NULL
            WHERE idx = {$room_id} LIMIT 1
        ");
    }
}

if (!function_exists('seotda_bet_pending_expire')) {
    function seotda_bet_pending_expire(array $room) {
        if (!seotda_has_bet_pending_columns()) {
            return $room;
        }
        $pending_at = $room['pending_bet_at'] ?? null;
        if (empty($pending_at) || empty($room['pending_bet_amount'])) {
            return $room;
        }
        $since = strtotime($pending_at);
        if ($since === false || (time() - $since) < (int)SEOTDA_BET_PENDING_SEC) {
            return $room;
        }
        $id = (int)($room['idx'] ?? 0);
        seotda_bet_pending_clear($id);
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_bet_pending_public')) {
    /** @return array<string,mixed>|null */
    function seotda_bet_pending_public(array $room, $viewer_nick) {
        if (!seotda_has_bet_pending_columns()) {
            return null;
        }
        $amount = (int)($room['pending_bet_amount'] ?? 0);
        $by = trim((string)($room['pending_bet_by'] ?? ''));
        if ($amount <= 0 || $by === '') {
            return null;
        }
        $viewer = trim((string)$viewer_nick);
        return [
            'amount' => $amount,
            'amount_fmt' => seotda_fmt_game($amount),
            'by_nick' => $by,
            'by_me' => ($viewer === $by),
            'need_response' => ($viewer !== $by),
            'stamp' => !empty($room['pending_bet_at']) ? (int)strtotime($room['pending_bet_at']) : 0,
        ];
    }
}

if (!function_exists('seotda_bet_both_can_afford')) {
    function seotda_bet_both_can_afford(array $room, $bet) {
        $bet = (int)$bet;
        $host = addslashes((string)$room['host_nick']);
        $guest = addslashes((string)$room['guest_nick']);
        $host_pt = seotda_member_point($host);
        $guest_pt = seotda_member_point($guest);
        if ($host_pt < $bet) {
            return ['ok' => false, 'data' => '❌ 호스트 게임냥 부족 (필요 ' . seotda_fmt_game($bet) . ')'];
        }
        if ($guest_pt < $bet) {
            return ['ok' => false, 'data' => '❌ 게스트 게임냥 부족 (필요 ' . seotda_fmt_game($bet) . ')'];
        }
        return ['ok' => true];
    }
}

if (!function_exists('seotda_bet_propose')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_bet_propose(array $room, $nick, $bet_raw) {
        if (!seotda_has_bet_pending_columns()) {
            return ['ok' => false, 'data' => '❌ 배팅 변경 기능을 사용할 수 없어요. DB 마이그레이션을 확인해주세요.'];
        }
        $room = seotda_bet_pending_expire($room);
        if (!seotda_bet_change_allowed($room)) {
            return ['ok' => false, 'data' => '❌ 지금은 배팅금을 변경할 수 없어요. (1:1 대기 중에만 가능)'];
        }

        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        if ($nick !== $host && $nick !== $guest) {
            return ['ok' => false, 'data' => '❌ 이 방 참가자만 변경할 수 있어요.'];
        }

        $bet = $bet_raw !== null && $bet_raw !== '' ? seotda_parse_bet($bet_raw) : 0;
        if ($bet < (int)SEOTDA_MIN_BET) {
            return ['ok' => false, 'data' => '❌ 최소 ' . seotda_fmt_game(SEOTDA_MIN_BET) . ' 이상이에요.'];
        }

        $current = (int)($room['bet_amount'] ?? 0);
        if ($bet === $current && empty($room['pending_bet_amount'])) {
            return ['ok' => false, 'data' => '❌ 현재 배팅금과 같아요.'];
        }

        $pending_by = trim((string)($room['pending_bet_by'] ?? ''));
        if ($pending_by !== '' && $pending_by !== $nick) {
            return ['ok' => false, 'data' => '❌ 상대의 배팅 변경 요청을 먼저 처리해주세요.'];
        }

        $afford = seotda_bet_both_can_afford($room, $bet);
        if (empty($afford['ok'])) {
            return $afford;
        }

        $id = (int)($room['idx'] ?? 0);
        $nick_esc = addslashes($nick);
        db_query("
            UPDATE tb_seotda_room
            SET pending_bet_amount = {$bet},
                pending_bet_by = '{$nick_esc}',
                pending_bet_at = NOW(),
                host_ready = 0,
                guest_ready = 0,
                ready_wait_since = NULL
            WHERE idx = {$id} LIMIT 1
        ");

        $fresh = seotda_room_by_token($room['room_token']);
        return [
            'ok' => true,
            'data' => '📝 배팅 ' . seotda_fmt_game($bet) . ' 변경 요청 · 상대 수락 대기',
            'room' => $fresh,
        ];
    }
}

if (!function_exists('seotda_bet_respond')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_bet_respond(array $room, $nick, $accept) {
        if (!seotda_has_bet_pending_columns()) {
            return ['ok' => false, 'data' => '❌ 배팅 변경 기능을 사용할 수 없어요.'];
        }
        $room = seotda_bet_pending_expire($room);
        $amount = (int)($room['pending_bet_amount'] ?? 0);
        $by = trim((string)($room['pending_bet_by'] ?? ''));
        if ($amount <= 0 || $by === '') {
            return ['ok' => false, 'data' => '❌ 처리할 배팅 변경 요청이 없어요.'];
        }
        if ($nick === $by) {
            return ['ok' => false, 'data' => '❌ 본인 요청은 수락/거절할 수 없어요.'];
        }

        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        if ($nick !== $host && $nick !== $guest) {
            return ['ok' => false, 'data' => '❌ 이 방 참가자만 응답할 수 있어요.'];
        }

        $id = (int)($room['idx'] ?? 0);
        if (!$accept) {
            seotda_bet_pending_clear($id);
            $fresh = seotda_room_by_token($room['room_token']);
            return [
                'ok' => true,
                'data' => '❌ 배팅 변경을 거절했어요.',
                'room' => $fresh,
            ];
        }

        $afford = seotda_bet_both_can_afford($room, $amount);
        if (empty($afford['ok'])) {
            seotda_bet_pending_clear($id);
            return $afford;
        }

        $fmt = seotda_fmt_game($amount);
        $result_msg = '✅ 배팅금 ' . $fmt . ' 로 변경 · 양쪽 다시 준비';
        $result_esc = addslashes($result_msg);
        db_query("
            UPDATE tb_seotda_room
            SET bet_amount = {$amount},
                pending_bet_amount = NULL,
                pending_bet_by = NULL,
                pending_bet_at = NULL,
                host_ready = 0,
                guest_ready = 0,
                ready_wait_since = NULL,
                last_result = '{$result_esc}'
            WHERE idx = {$id} LIMIT 1
        ");
        $fresh = seotda_room_by_token($room['room_token']);
        return [
            'ok' => true,
            'data' => '✅ 배팅금 ' . $fmt . ' 로 변경되었어요.',
            'room' => $fresh,
        ];
    }
}

if (!function_exists('seotda_bet_cancel')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_bet_cancel(array $room, $nick) {
        if (!seotda_has_bet_pending_columns()) {
            return ['ok' => false, 'data' => '❌ 배팅 변경 요청이 없어요.'];
        }
        $by = trim((string)($room['pending_bet_by'] ?? ''));
        if ($by === '' || $by !== $nick) {
            return ['ok' => false, 'data' => '❌ 본인이 요청한 변경만 취소할 수 있어요.'];
        }
        seotda_bet_pending_clear((int)$room['idx']);
        $fresh = seotda_room_by_token($room['room_token']);
        return [
            'ok' => true,
            'data' => '배팅 변경 요청을 취소했어요.',
            'room' => $fresh,
        ];
    }
}
