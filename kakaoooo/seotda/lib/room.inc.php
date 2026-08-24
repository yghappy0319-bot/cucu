<?php

if (!function_exists('seotda_room_token_new')) {
    function seotda_room_token_new() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = (int)SEOTDA_ROOM_TOKEN_LEN;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }
}

if (!function_exists('seotda_room_by_token')) {
    function seotda_room_by_token($token) {
        $token = addslashes(trim((string)$token));
        if ($token === '') {
            return null;
        }
        $row = @db_select("SELECT * FROM tb_seotda_room WHERE room_token = '{$token}' LIMIT 1");
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('seotda_room_by_host')) {
    function seotda_room_by_host($nick) {
        $nick = addslashes(trim((string)$nick));
        if ($nick === '') {
            return null;
        }
        $row = @db_select("SELECT * FROM tb_seotda_room WHERE host_nick = '{$nick}' ORDER BY idx DESC LIMIT 1");
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('seotda_resolve_api_room')) {
    /** API 요청 시 게스트 참여 방 우선, 없으면 호스트 방 */
    function seotda_resolve_api_room($nick) {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return null;
        }
        $guest_esc = addslashes($nick);
        $as_guest = @db_select("
            SELECT * FROM tb_seotda_room
            WHERE guest_nick = '{$guest_esc}'
              AND mode IN ('match_pending','waiting','pvp')
            ORDER BY idx DESC LIMIT 1
        ");
        if (is_array($as_guest) && !empty($as_guest['room_token'])) {
            return seotda_room_apply_timeouts($as_guest);
        }
        $host_room = seotda_room_by_host($nick);
        if (is_array($host_room)) {
            return seotda_room_apply_timeouts($host_room);
        }
        return seotda_room_get_or_create_host($nick);
    }
}

if (!function_exists('seotda_room_get_or_create_host')) {
    function seotda_room_get_or_create_host($host_nick) {
        $existing = seotda_room_by_host($host_nick);
        if (is_array($existing)) {
            return seotda_room_apply_timeouts($existing);
        }
        $host_esc = addslashes($host_nick);
        for ($try = 0; $try < 5; $try++) {
            $token = seotda_room_token_new();
            $token_esc = addslashes($token);
            $bet = (int)SEOTDA_DEFAULT_BET;
            $ok = @db_query("
                INSERT INTO tb_seotda_room (room_token, host_nick, guest_nick, mode, bet_amount)
                VALUES ('{$token_esc}', '{$host_esc}', NULL, 'solo', {$bet})
            ");
            if ($ok) {
                return seotda_room_by_token($token);
            }
        }
        return null;
    }
}

if (!function_exists('seotda_room_touch')) {
    /** 섯다 status 폴링 heartbeat — online_at만 갱신 (updated_at·게임과 무관) */
    function seotda_room_touch(array $room) {
        $id = (int)($room['idx'] ?? 0);
        if ($id <= 0) {
            return $room;
        }
        @db_query("UPDATE tb_seotda_room SET online_at = NOW() WHERE idx = {$id} LIMIT 1");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_expire_waiting')) {
    function seotda_room_expire_waiting(array $room) {
        if (($room['mode'] ?? '') !== 'waiting' || empty($room['waiting_since'])) {
            return $room;
        }
        $since = strtotime($room['waiting_since']);
        if ($since === false || (time() - $since) < (int)SEOTDA_WAITING_SEC) {
            return $room;
        }
        $id = (int)$room['idx'];
        db_query("
            UPDATE tb_seotda_room
            SET mode = 'solo',
                guest_nick = NULL,
                host_ready = 0,
                guest_ready = 0,
                waiting_since = NULL,
                guest_action_since = NULL,
                ready_wait_since = NULL,
                last_result = '대기 시간 초과 — 솔로로 복귀'
            WHERE idx = {$id} LIMIT 1
        ");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_guest_needs_action')) {
    function seotda_room_guest_needs_action(array $room) {
        $guest = trim((string)($room['guest_nick'] ?? ''));
        if ($guest === '' || $guest === SEOTDA_SYSTEM_NICK) {
            return false;
        }
        $mode = (string)($room['mode'] ?? 'solo');
        if ($mode === 'match_pending') {
            return false;
        }
        if ($mode === 'waiting' && (int)($room['guest_ready'] ?? 0) !== 1) {
            if ((int)($room['host_ready'] ?? 0) === 1) {
                return false;
            }
            return true;
        }
        if ($mode === 'pvp'
            && (string)($room['pvp_phase'] ?? '') === 'playing'
            && (int)($room['guest_picked'] ?? 0) !== 1) {
            return true;
        }
        return false;
    }
}

if (!function_exists('seotda_room_guest_action_start')) {
    function seotda_room_guest_action_start(array $room) {
        if (!function_exists('seotda_has_guest_action_since_column') || !seotda_has_guest_action_since_column()) {
            return $room;
        }
        if (!seotda_room_guest_needs_action($room)) {
            return $room;
        }
        $id = (int)($room['idx'] ?? 0);
        if ($id <= 0) {
            return $room;
        }
        if (!empty($room['guest_action_since'])) {
            return $room;
        }
        db_query("UPDATE tb_seotda_room SET guest_action_since = NOW() WHERE idx = {$id} LIMIT 1");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_guest_action_done')) {
    function seotda_room_guest_action_done(array $room) {
        if (!function_exists('seotda_has_guest_action_since_column') || !seotda_has_guest_action_since_column()) {
            return $room;
        }
        $id = (int)($room['idx'] ?? 0);
        if ($id <= 0 || empty($room['guest_action_since'])) {
            return $room;
        }
        db_query("UPDATE tb_seotda_room SET guest_action_since = NULL WHERE idx = {$id} LIMIT 1");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_kick_guest')) {
    function seotda_room_kick_guest(array $room, $reason_msg) {
        $id = (int)($room['idx'] ?? 0);
        if ($id <= 0 || empty($room['guest_nick'])) {
            return $room;
        }
        $reason_esc = addslashes((string)$reason_msg);
        db_query("
            UPDATE tb_seotda_room
            SET guest_nick = NULL, mode = 'solo', host_ready = 0, guest_ready = 0,
                waiting_since = NULL, guest_action_since = NULL, ready_wait_since = NULL,
                pvp_phase = 'idle', solo_phase = 'idle',
                pot = 0,
                host_picked = 0, guest_picked = 0,
                host_pick1 = NULL, host_pick2 = NULL,
                guest_pick1 = NULL, guest_pick2 = NULL,
                host_peek_slot = NULL, guest_peek_slot = NULL,
                host_card1 = NULL, host_card2 = NULL, host_card3 = NULL,
                guest_card1 = NULL, guest_card2 = NULL, guest_card3 = NULL,
                sys_card1 = NULL, sys_card2 = NULL, sys_card3 = NULL,
                last_result = '{$reason_esc}'
            WHERE idx = {$id} LIMIT 1
        ");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_expire_guest_inaction')) {
    function seotda_room_expire_guest_inaction(array $room) {
        if (!function_exists('seotda_has_guest_action_since_column') || !seotda_has_guest_action_since_column()) {
            return $room;
        }
        if (!seotda_room_guest_needs_action($room) || empty($room['guest_action_since'])) {
            return $room;
        }
        $since = strtotime($room['guest_action_since']);
        if ($since === false || (time() - $since) < (int)SEOTDA_GUEST_ACTION_SEC) {
            return $room;
        }
        $guest = (string)$room['guest_nick'];
        return seotda_room_kick_guest($room, $guest . '님 · 30초 내 선택 없음 — 자동 퇴장');
    }
}

if (!function_exists('seotda_room_waiting_one_ready')) {
    function seotda_room_waiting_one_ready(array $room) {
        if (($room['mode'] ?? '') !== 'waiting') {
            return false;
        }
        $host_ready = (int)($room['host_ready'] ?? 0) === 1;
        $guest_ready = (int)($room['guest_ready'] ?? 0) === 1;
        return ($host_ready xor $guest_ready);
    }
}

if (!function_exists('seotda_room_ready_wait_start')) {
    function seotda_room_ready_wait_start(array $room) {
        if (!function_exists('seotda_has_ready_wait_since_column') || !seotda_has_ready_wait_since_column()) {
            return $room;
        }
        if (!seotda_room_waiting_one_ready($room)) {
            return $room;
        }
        $id = (int)($room['idx'] ?? 0);
        if ($id <= 0 || !empty($room['ready_wait_since'])) {
            return $room;
        }
        db_query("UPDATE tb_seotda_room SET ready_wait_since = NOW() WHERE idx = {$id} LIMIT 1");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_expire_ready_wait')) {
    function seotda_room_expire_ready_wait(array $room) {
        if (!function_exists('seotda_has_ready_wait_since_column') || !seotda_has_ready_wait_since_column()) {
            return $room;
        }
        if (!seotda_room_waiting_one_ready($room) || empty($room['ready_wait_since'])) {
            return $room;
        }
        $since = strtotime($room['ready_wait_since']);
        if ($since === false || (time() - $since) < (int)SEOTDA_READY_RESPONSE_SEC) {
            return $room;
        }
        $guest = (string)($room['guest_nick'] ?? '');
        $host = (string)($room['host_nick'] ?? '');
        $guest_ready = (int)($room['guest_ready'] ?? 0) === 1;
        if (!$guest_ready) {
            return seotda_room_kick_guest($room, $guest . '님 · 30초 내 준비 없음 — 자동 퇴장');
        }
        return seotda_room_kick_guest($room, '호스트(' . $host . ') 30초 내 준비 없음 — 자동 퇴장');
    }
}

if (!function_exists('seotda_room_apply_timeouts')) {
    function seotda_room_apply_timeouts(array $room) {
        $room = seotda_room_expire_waiting($room);
        $room = seotda_room_expire_ready_wait($room);
        $room = seotda_room_expire_guest_inaction($room);
        if (function_exists('seotda_bet_pending_expire')) {
            $room = seotda_bet_pending_expire($room);
        }
        if (seotda_room_guest_needs_action($room) && empty($room['guest_action_since'])) {
            $room = seotda_room_guest_action_start($room);
        }
        if (seotda_room_waiting_one_ready($room) && empty($room['ready_wait_since'])) {
            $room = seotda_room_ready_wait_start($room);
        }
        return $room;
    }
}

if (!function_exists('seotda_room_join_guest')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_room_join_guest($room_token, $guest_nick) {
        $room = seotda_room_by_token($room_token);
        if (!$room) {
            return ['ok' => false, 'data' => '❌ 방을 찾을 수 없어요.'];
        }
        if ($room['host_nick'] === $guest_nick) {
            return ['ok' => false, 'data' => '❌ 내 방에는 게스트로 들어갈 수 없어요.'];
        }
        if (!empty($room['guest_nick']) && $room['guest_nick'] !== $guest_nick) {
            return ['ok' => false, 'data' => '❌ 이미 다른 상대가 있는 방이에요.'];
        }
        $id = (int)$room['idx'];
        $guest_esc = addslashes($guest_nick);
        $mode = (string)($room['mode'] ?? 'solo');
        $solo_phase = (string)($room['solo_phase'] ?? 'idle');

        if ($mode === 'pvp' || $solo_phase === 'playing') {
            $new_mode = 'match_pending';
        } elseif ($mode === 'solo' && $solo_phase === 'done') {
            $new_mode = 'waiting';
        } elseif ($mode === 'solo' && $solo_phase === 'idle') {
            $new_mode = 'waiting';
        } else {
            $new_mode = 'match_pending';
        }

        $waiting_sql = ($new_mode === 'waiting')
            ? ', waiting_since = NOW(), guest_action_since = NOW(), ready_wait_since = NULL'
            : ', guest_action_since = NULL, ready_wait_since = NULL';
        db_query("
            UPDATE tb_seotda_room
            SET guest_nick = '{$guest_esc}',
                mode = '{$new_mode}',
                host_ready = 0,
                guest_ready = 0
                {$waiting_sql}
            WHERE idx = {$id} LIMIT 1
        ");
        $room = seotda_room_by_token($room_token);
        return ['ok' => true, 'room' => $room, 'data' => '✅ 방 입장 · ' . ($new_mode === 'match_pending' ? '호스트 솔로 판 종료 후 1:1 대기' : '1:1 대기')];
    }
}

if (!function_exists('seotda_room_after_solo_done')) {
    function seotda_room_after_solo_done(array $room) {
        if (empty($room['guest_nick']) || $room['guest_nick'] === SEOTDA_SYSTEM_NICK) {
            return $room;
        }
        if (($room['mode'] ?? '') !== 'match_pending') {
            return $room;
        }
        $id = (int)$room['idx'];
        db_query("
            UPDATE tb_seotda_room
            SET mode = 'waiting',
                waiting_since = NOW(),
                guest_action_since = NOW(),
                ready_wait_since = NULL,
                host_ready = 0,
                guest_ready = 0,
                solo_phase = 'idle'
            WHERE idx = {$id} LIMIT 1
        ");
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_room_set_ready')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_room_set_ready(array $room, $nick) {
        $room = seotda_room_apply_timeouts($room);
        if (($room['mode'] ?? '') !== 'waiting') {
            return ['ok' => false, 'data' => '❌ 지금은 준비할 수 없어요. (1:1 대기 상태가 아님)'];
        }
        if (($room['solo_phase'] ?? '') === 'playing') {
            return ['ok' => false, 'data' => '❌ 호스트 솔로 판이 끝날 때까지 기다려주세요.'];
        }
        $id = (int)$room['idx'];
        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        if ($nick !== $host && $nick !== $guest) {
            return ['ok' => false, 'data' => '❌ 이 방 참가자가 아니에요.'];
        }
        $bet = (int)($room['bet_amount'] ?? SEOTDA_DEFAULT_BET);
        if (seotda_point_lt(seotda_member_point(addslashes($nick)), $bet)) {
            return ['ok' => false, 'data' => '❌ 게임냥이 부족해요. (필요 ' . seotda_fmt_game($bet) . ')'];
        }
        if ($nick === $host) {
            db_query("
                UPDATE tb_seotda_room
                SET host_ready = 1, guest_action_since = NULL, ready_wait_since = NOW()
                WHERE idx = {$id} LIMIT 1
            ");
        } else {
            db_query("
                UPDATE tb_seotda_room
                SET guest_ready = 1, guest_action_since = NULL, ready_wait_since = NOW()
                WHERE idx = {$id} LIMIT 1
            ");
        }
        $room = seotda_room_by_token($room['room_token']);
        if ((int)$room['host_ready'] === 1 && (int)$room['guest_ready'] === 1) {
            return seotda_pvp_start($room);
        }
        return ['ok' => true, 'room' => $room, 'data' => '✅ 준비 완료 · 상대 준비를 기다리는 중'];
    }
}

if (!function_exists('seotda_room_leave_guest')) {
    function seotda_room_leave_guest(array $room, $nick) {
        if ($nick === (string)$room['guest_nick']) {
            return seotda_room_kick_guest($room, '상대가 나갔습니다 · 솔로로 복귀');
        }
        if ($nick === (string)$room['host_nick']) {
            db_query("DELETE FROM tb_seotda_room WHERE idx = " . (int)$room['idx'] . " LIMIT 1");
            return null;
        }
        return seotda_room_by_token($room['room_token']);
    }
}

if (!function_exists('seotda_log_insert')) {
    function seotda_log_insert(array $fields) {
        $room_id = (int)($fields['room_id'] ?? 0);
        $token = addslashes((string)($fields['room_token'] ?? ''));
        $mode = addslashes((string)($fields['mode'] ?? ''));
        $host = addslashes((string)($fields['host_nick'] ?? ''));
        $guest = addslashes((string)($fields['guest_nick'] ?? ''));
        $bet = (int)($fields['bet_amount'] ?? 0);
        $winner = isset($fields['winner_nick']) ? ("'" . addslashes((string)$fields['winner_nick']) . "'") : 'NULL';
        $hl = addslashes((string)($fields['host_label'] ?? ''));
        $gl = addslashes((string)($fields['guest_label'] ?? ''));
        $hd = (int)($fields['host_delta'] ?? 0);
        $gd = (int)($fields['guest_delta'] ?? 0);
        @db_query("
            INSERT INTO tb_seotda_log
              (room_id, room_token, mode, host_nick, guest_nick, bet_amount, winner_nick,
               host_label, guest_label, host_delta, guest_delta)
            VALUES
              ({$room_id}, '{$token}', '{$mode}', '{$host}', '{$guest}', {$bet}, {$winner},
               '{$hl}', '{$gl}', {$hd}, {$gd})
        ");
    }
}

if (!function_exists('seotda_room_fill_peek')) {
    /** 패 선택 전 랜덤 1장 공개 정보 */
    function seotda_room_fill_peek(array &$out, array $room, $prefix) {
        if (!function_exists('seotda_has_peek_slot_columns') || !seotda_has_peek_slot_columns()) {
            return;
        }
        $peek = (int)($room[$prefix . '_peek_slot'] ?? 0);
        if ($peek < 1 || $peek > 3) {
            return;
        }
        $c1 = (int)($room[$prefix . '_card1'] ?? 0);
        $c2 = (int)($room[$prefix . '_card2'] ?? 0);
        $c3 = (int)($room[$prefix . '_card3'] ?? 0);
        if ($c1 < 1 || $c2 < 1 || $c3 < 1) {
            return;
        }
        $cards = [$c1, $c2, $c3];
        $out['peek_slot'] = $peek;
        $out['peek_card'] = seotda_card_label($cards[$peek - 1]);
    }
}

if (!function_exists('seotda_room_public_view')) {
    function seotda_room_public_view(array $room, $viewer_nick) {
        $mode = (string)($room['mode'] ?? 'solo');
        $host = (string)$room['host_nick'];
        $guest = (string)($room['guest_nick'] ?? '');
        $is_host = ($viewer_nick === $host);
        $is_guest = ($guest !== '' && $viewer_nick === $guest);

        $out = [
            'room_token' => (string)$room['room_token'],
            'mode' => $mode,
            'host_nick' => $host,
            'guest_nick' => $guest !== '' ? $guest : null,
            'bet_amount' => (int)($room['bet_amount'] ?? 0),
            'bet_fmt' => seotda_fmt_game((int)($room['bet_amount'] ?? 0)),
            'host_ready' => (int)($room['host_ready'] ?? 0) === 1,
            'guest_ready' => (int)($room['guest_ready'] ?? 0) === 1,
            'solo_phase' => (string)($room['solo_phase'] ?? 'idle'),
            'pvp_phase' => (string)($room['pvp_phase'] ?? 'idle'),
            'pot' => (int)($room['pot'] ?? 0),
            'pot_fmt' => seotda_fmt_game((int)($room['pot'] ?? 0)),
            'waiting_left' => 0,
            'last_result' => (string)($room['last_result'] ?? ''),
            'round_no' => (int)($room['round_no'] ?? 0),
            'my_role' => $is_host ? 'host' : ($is_guest ? 'guest' : 'viewer'),
        ];

        if ($mode === 'waiting' && !empty($room['waiting_since'])) {
            $elapsed = time() - (int)strtotime($room['waiting_since']);
            $out['waiting_left'] = max(0, (int)SEOTDA_WAITING_SEC - $elapsed);
        }

        $out['guest_action_left'] = 0;
        if ($is_guest && seotda_room_guest_needs_action($room) && !empty($room['guest_action_since'])) {
            $elapsed = time() - (int)strtotime($room['guest_action_since']);
            $out['guest_action_left'] = max(0, (int)SEOTDA_GUEST_ACTION_SEC - $elapsed);
        }

        $out['ready_wait_left'] = 0;
        $out['ready_wait_active'] = false;
        if ($mode === 'waiting' && seotda_room_waiting_one_ready($room) && !empty($room['ready_wait_since'])) {
            $elapsed = time() - (int)strtotime($room['ready_wait_since']);
            $out['ready_wait_left'] = max(0, (int)SEOTDA_READY_RESPONSE_SEC - $elapsed);
            $out['ready_wait_active'] = true;
        }

        $out['guest_action_active'] = false;
        if ($is_guest && seotda_room_guest_needs_action($room) && !empty($room['guest_action_since'])) {
            $out['guest_action_active'] = true;
        }

        $out['my_cards'] = null;
        $out['my_pool'] = null;
        $out['my_picks'] = null;
        $out['opponent_cards'] = null;
        $out['opponent_pool'] = null;
        $out['my_hand'] = null;
        $out['opponent_hand'] = null;
        $out['need_pick'] = false;
        $out['my_picked'] = false;
        $out['opponent_picked'] = false;
        $out['pool_count'] = 0;
        $out['cards_hidden'] = false;
        $out['cards_revealed'] = false;
        $out['peek_slot'] = 0;
        $out['peek_card'] = null;
        $out['in_pick_phase'] = false;
        $out['pick_hints'] = null;

        $solo_phase = (string)($room['solo_phase'] ?? 'idle');
        $pvp_phase = (string)($room['pvp_phase'] ?? 'idle');
        $host_picked = (int)($room['host_picked'] ?? 0) === 1;
        $guest_picked = (int)($room['guest_picked'] ?? 0) === 1;

        $fill_revealed_solo = function () use (&$out, $room, $is_host, $solo_phase) {
            if (!$is_host || $solo_phase !== 'done' || empty($room['host_pick1']) || empty($room['host_pick2'])) {
                return;
            }
            $picked = seotda_pick_slots(
                $room['host_card1'], $room['host_card2'], $room['host_card3'],
                $room['host_pick1'], $room['host_pick2']
            );
            if (!$picked) {
                return;
            }
            $out['cards_revealed'] = true;
            $out['cards_hidden'] = false;
            $out['pool_count'] = 3;
            $out['my_pool'] = [
                seotda_card_label($room['host_card1']),
                seotda_card_label($room['host_card2']),
                seotda_card_label($room['host_card3']),
            ];
            $out['my_picks'] = [(int)$room['host_pick1'], (int)$room['host_pick2']];
            $out['my_cards'] = [seotda_card_label($picked[0]), seotda_card_label($picked[1])];
            $out['my_hand'] = seotda_hand_eval($picked[0], $picked[1])['label'];
            $sys_best = seotda_best_pair_from_triple($room['sys_card1'], $room['sys_card2'], $room['sys_card3']);
            if ($sys_best) {
                $out['opponent_pool'] = [
                    seotda_card_label($room['sys_card1']),
                    seotda_card_label($room['sys_card2']),
                    seotda_card_label($room['sys_card3']),
                ];
                $out['opponent_cards'] = [
                    seotda_card_label($sys_best['c1']),
                    seotda_card_label($sys_best['c2']),
                ];
                $out['opponent_hand'] = $sys_best['hand']['label'];
            }
        };

        $fill_revealed_pvp = function ($as_host) use (&$out, $room, $is_host, $is_guest) {
            if ($as_host && !$is_host) {
                return;
            }
            if (!$as_host && !$is_guest) {
                return;
            }
            $prefix = $as_host ? 'host' : 'guest';
            $opp_prefix = $as_host ? 'guest' : 'host';
            if (empty($room[$prefix . '_pick1']) || empty($room[$prefix . '_pick2'])) {
                return;
            }
            $my_pair = seotda_pick_slots(
                $room[$prefix . '_card1'], $room[$prefix . '_card2'], $room[$prefix . '_card3'],
                $room[$prefix . '_pick1'], $room[$prefix . '_pick2']
            );
            $opp_pair = seotda_pick_slots(
                $room[$opp_prefix . '_card1'], $room[$opp_prefix . '_card2'], $room[$opp_prefix . '_card3'],
                $room[$opp_prefix . '_pick1'], $room[$opp_prefix . '_pick2']
            );
            if (!$my_pair || !$opp_pair) {
                return;
            }
            $out['cards_revealed'] = true;
            $out['cards_hidden'] = false;
            $out['pool_count'] = 3;
            $out['my_pool'] = [
                seotda_card_label($room[$prefix . '_card1']),
                seotda_card_label($room[$prefix . '_card2']),
                seotda_card_label($room[$prefix . '_card3']),
            ];
            $out['my_picks'] = [(int)$room[$prefix . '_pick1'], (int)$room[$prefix . '_pick2']];
            $out['opponent_pool'] = [
                seotda_card_label($room[$opp_prefix . '_card1']),
                seotda_card_label($room[$opp_prefix . '_card2']),
                seotda_card_label($room[$opp_prefix . '_card3']),
            ];
            $my_hand = seotda_hand_eval($my_pair[0], $my_pair[1]);
            $opp_hand = seotda_hand_eval($opp_pair[0], $opp_pair[1]);
            $out['my_hand'] = $my_hand['label'];
            $out['opponent_hand'] = $opp_hand['label'];
        };

        if ($mode === 'solo' || $mode === 'match_pending') {
            if ($is_host && in_array($solo_phase, ['playing', 'done'], true)) {
                if ($solo_phase === 'playing') {
                    $out['pool_count'] = 3;
                    $out['cards_hidden'] = true;
                    $out['cards_revealed'] = false;
                    $out['my_pool'] = null;
                    $out['need_pick'] = true;
                    $out['in_pick_phase'] = true;
                    seotda_room_fill_peek($out, $room, 'host');
                    if (function_exists('seotda_solo_pick_hints_enabled')
                        && seotda_solo_pick_hints_enabled($viewer_nick)
                        && function_exists('seotda_solo_pick_hints_for_room')) {
                        $out['pick_hints'] = seotda_solo_pick_hints_for_room($room);
                    }
                } else {
                    $fill_revealed_solo();
                }
            }
        }

        if ($mode === 'pvp') {
            if ($is_host) {
                $out['my_picked'] = $host_picked;
                $out['opponent_picked'] = $guest_picked;
                if ($pvp_phase === 'playing') {
                    $out['pool_count'] = 3;
                    $out['cards_hidden'] = true;
                    $out['cards_revealed'] = false;
                    $out['my_pool'] = null;
                    if (!$host_picked) {
                        $out['need_pick'] = true;
                        $out['in_pick_phase'] = true;
                    } elseif ($host_picked) {
                        $out['my_picks'] = [(int)$room['host_pick1'], (int)$room['host_pick2']];
                    }
                    seotda_room_fill_peek($out, $room, 'host');
                } elseif ($pvp_phase === 'done') {
                    $fill_revealed_pvp(true);
                }
            } elseif ($is_guest) {
                $out['my_picked'] = $guest_picked;
                $out['opponent_picked'] = $host_picked;
                if ($pvp_phase === 'playing') {
                    $out['pool_count'] = 3;
                    $out['cards_hidden'] = true;
                    $out['cards_revealed'] = false;
                    $out['my_pool'] = null;
                    if (!$guest_picked) {
                        $out['need_pick'] = true;
                        $out['in_pick_phase'] = true;
                    } elseif ($guest_picked) {
                        $out['my_picks'] = [(int)$room['guest_pick1'], (int)$room['guest_pick2']];
                    }
                    seotda_room_fill_peek($out, $room, 'guest');
                } elseif ($pvp_phase === 'done') {
                    $fill_revealed_pvp(false);
                }
            }
        }

        // PvP 판 종료 후 다음 판 대기 — 결과 카드 유지
        if ($mode === 'waiting' && $pvp_phase === 'done' && $guest !== '') {
            if ($is_host) {
                $fill_revealed_pvp(true);
            } elseif ($is_guest) {
                $fill_revealed_pvp(false);
            }
        }

        $out['pvp_result'] = seotda_pvp_result_summary($room);
        $out['solo_result'] = function_exists('seotda_solo_result_summary')
            ? seotda_solo_result_summary($room)
            : null;

        $out['emote_enabled'] = function_exists('seotda_emote_enabled_for_room')
            && seotda_emote_enabled_for_room($room, $viewer_nick);
        $out['emote_catalog'] = function_exists('seotda_emote_public_catalog')
            ? seotda_emote_public_catalog()
            : [];
        $out['opponent_emote'] = function_exists('seotda_emote_recent_for_viewer')
            ? seotda_emote_recent_for_viewer($room, $viewer_nick)
            : null;

        $out['bet_change_allowed'] = function_exists('seotda_bet_change_allowed')
            && seotda_bet_change_allowed($room);
        $out['bet_pending'] = function_exists('seotda_bet_pending_public')
            ? seotda_bet_pending_public($room, $viewer_nick)
            : null;

        return $out;
    }
}
