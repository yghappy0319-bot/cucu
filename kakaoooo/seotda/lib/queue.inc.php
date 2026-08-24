<?php
/**
 * 전역 매칭 큐 — queue_join 시 다른 대기자와 방 생성·게스트 배정
 */

if (!function_exists('seotda_queue_join')) {
    /** @return array{ok:bool,data?:string,room?:array,matched?:bool} */
    function seotda_queue_join($nick, $bet_raw) {
        $nick_esc = addslashes($nick);
        $bet = $bet_raw !== null && $bet_raw !== '' ? seotda_parse_bet($bet_raw) : (int)SEOTDA_DEFAULT_BET;
        if ($bet < (int)SEOTDA_MIN_BET) {
            return ['ok' => false, 'data' => '❌ 최소 ' . seotda_fmt_game(SEOTDA_MIN_BET) . ' 이상'];
        }
        if (seotda_member_point($nick_esc) < $bet) {
            return ['ok' => false, 'data' => '❌ 게임냥이 부족해요.'];
        }

        @db_query("DELETE FROM tb_seotda_queue WHERE nick = '{$nick_esc}'");

        $other = @db_select("
            SELECT nick, bet_amount FROM tb_seotda_queue
            WHERE nick <> '{$nick_esc}'
            ORDER BY queued_at ASC
            LIMIT 1
        ");

        if (!$other || empty($other['nick'])) {
            @db_query("
                INSERT INTO tb_seotda_queue (nick, bet_amount, queued_at)
                VALUES ('{$nick_esc}', {$bet}, NOW())
                ON DUPLICATE KEY UPDATE bet_amount = {$bet}, queued_at = NOW()
            ");
            return [
                'ok' => true,
                'matched' => false,
                'data' => '⏳ 전역 대기열 등록 · 상대를 찾는 중…',
            ];
        }

        $host_nick = (string)$other['nick'];
        $guest_nick = $nick;
        $match_bet = max($bet, (int)($other['bet_amount'] ?? $bet));

        @db_query("DELETE FROM tb_seotda_queue WHERE nick IN ('{$nick_esc}', '" . addslashes($host_nick) . "')");

        $host_room = seotda_room_by_host($host_nick);
        if (!$host_room) {
            $host_room = seotda_room_get_or_create_host($host_nick);
        }
        if (!$host_room) {
            return ['ok' => false, 'data' => '❌ 방 생성에 실패했어요.'];
        }

        $id = (int)$host_room['idx'];
        db_query("UPDATE tb_seotda_room SET bet_amount = {$match_bet} WHERE idx = {$id} LIMIT 1");
        $host_room = seotda_room_by_token($host_room['room_token']);

        $join = seotda_room_join_guest($host_room['room_token'], $guest_nick);
        if (empty($join['ok'])) {
            return $join;
        }

        return [
            'ok' => true,
            'matched' => true,
            'room' => $join['room'] ?? null,
            'data' => '✅ 매칭 성공! · ' . ($join['data'] ?? '') . "\n방 코드: " . ($host_room['room_token'] ?? ''),
        ];
    }
}

if (!function_exists('seotda_queue_leave')) {
    function seotda_queue_leave($nick) {
        $nick_esc = addslashes($nick);
        @db_query("DELETE FROM tb_seotda_queue WHERE nick = '{$nick_esc}'");
        return ['ok' => true, 'data' => '✅ 대기열에서 나왔어요.'];
    }
}

if (!function_exists('seotda_queue_status')) {
    function seotda_queue_status($nick) {
        $nick_esc = addslashes($nick);
        $row = @db_select("SELECT bet_amount, queued_at FROM tb_seotda_queue WHERE nick = '{$nick_esc}' LIMIT 1");
        if (!$row) {
            return ['queued' => false];
        }
        return [
            'queued' => true,
            'bet_amount' => (int)($row['bet_amount'] ?? 0),
            'bet_fmt' => seotda_fmt_game((int)($row['bet_amount'] ?? 0)),
            'queued_at' => (string)($row['queued_at'] ?? ''),
        ];
    }
}
