<?php
/**
 * 1:1 매칭 — 섯다 접속자 목록 · 선택 입장
 */

if (!function_exists('seotda_online_host_nicks')) {
    /**
     * 섯da status 폴링 중인 호스트 (본인 제외)
     * · online_at N초 이내 (status heartbeat 전용 — 게임/입장과 무관)
     *
     * @return string[]
     */
    function seotda_online_host_nicks($exclude_nick) {
        if (!function_exists('seotda_has_online_at_column') || !seotda_has_online_at_column()) {
            return [];
        }
        $exclude_esc = addslashes(trim((string)$exclude_nick));
        $sec = max(5, (int)SEOTDA_ONLINE_SEC);
        $rs = @db_query("
            SELECT host_nick
            FROM tb_seotda_room
            WHERE host_nick <> '{$exclude_esc}'
              AND online_at IS NOT NULL
              AND online_at >= DATE_SUB(NOW(), INTERVAL {$sec} SECOND)
            ORDER BY online_at DESC
            LIMIT 64
        ");
        if (!$rs) {
            return [];
        }
        $out = [];
        while ($row = @mysqli_fetch_assoc($rs)) {
            $n = trim((string)($row['host_nick'] ?? ''));
            if ($n !== '' && $n !== $exclude_nick) {
                $out[] = $n;
            }
        }
        return $out;
    }
}

if (!function_exists('seotda_active_player_nicks')) {
    /** @return string[] */
    function seotda_active_player_nicks($exclude_nick) {
        return seotda_online_host_nicks($exclude_nick);
    }
}

if (!function_exists('seotda_host_available_for_guest')) {
    function seotda_host_available_for_guest($host_nick) {
        $room = seotda_room_by_host($host_nick);
        if (!$room) {
            return false;
        }
        $guest = trim((string)($room['guest_nick'] ?? ''));
        $mode = (string)($room['mode'] ?? 'solo');
        if ($guest !== '' && in_array($mode, ['match_pending', 'waiting', 'pvp'], true)) {
            return false;
        }
        if ($mode === 'pvp') {
            return false;
        }
        return true;
    }
}

if (!function_exists('seotda_host_match_status')) {
    /** @return array{available:bool,label:string} */
    function seotda_host_match_status($host_nick, $room = null) {
        if ($room === null) {
            $room = seotda_room_by_host($host_nick);
        }
        if (!is_array($room)) {
            return ['available' => false, 'label' => '오프라인'];
        }
        if (!seotda_host_available_for_guest($host_nick)) {
            $mode = (string)($room['mode'] ?? 'solo');
            if ($mode === 'pvp') {
                return ['available' => false, 'label' => '1:1 진행 중'];
            }
            if ($mode === 'waiting') {
                return ['available' => false, 'label' => '대기 중'];
            }
            return ['available' => false, 'label' => '대결 중'];
        }
        $mode = (string)($room['mode'] ?? 'solo');
        $solo_phase = (string)($room['solo_phase'] ?? 'idle');
        if ($mode === 'solo' && $solo_phase === 'playing') {
            return ['available' => true, 'label' => '솔로 중'];
        }
        if ($mode === 'match_pending') {
            return ['available' => true, 'label' => '솔로 후 입장'];
        }
        return ['available' => true, 'label' => '접속 중'];
    }
}

if (!function_exists('seotda_online_host_rooms')) {
    /**
     * 접속 중 호스트 방 일괄 조회 (N+1 방지)
     *
     * @return array<int,array>
     */
    function seotda_online_host_rooms($exclude_nick) {
        if (!function_exists('seotda_has_online_at_column') || !seotda_has_online_at_column()) {
            return [];
        }
        $exclude_esc = addslashes(trim((string)$exclude_nick));
        $sec = max(5, (int)SEOTDA_ONLINE_SEC);
        $rs = @db_query("
            SELECT *
            FROM tb_seotda_room
            WHERE host_nick <> '{$exclude_esc}'
              AND online_at IS NOT NULL
              AND online_at >= DATE_SUB(NOW(), INTERVAL {$sec} SECOND)
            ORDER BY online_at DESC
            LIMIT 64
        ");
        if (!$rs) {
            return [];
        }
        $out = [];
        while ($row = @mysqli_fetch_assoc($rs)) {
            if (!is_array($row) || empty($row['host_nick'])) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('seotda_players_for_match')) {
    /**
     * @return array<int,array{nick:string,available:bool,status_label:string,bet_fmt:string}>
     */
    function seotda_players_for_match($viewer_nick) {
        $viewer_nick = trim((string)$viewer_nick);
        $list = [];
        foreach (seotda_online_host_rooms($viewer_nick) as $room) {
            $host_nick = trim((string)($room['host_nick'] ?? ''));
            if ($host_nick === '' || $host_nick === $viewer_nick) {
                continue;
            }
            $st = seotda_host_match_status($host_nick, $room);
            $bet = (int)($room['bet_amount'] ?? SEOTDA_DEFAULT_BET);
            $list[] = [
                'nick' => $host_nick,
                'available' => !empty($st['available']),
                'status_label' => (string)($st['label'] ?? ''),
                'bet_fmt' => seotda_fmt_game($bet),
            ];
        }
        usort($list, function ($a, $b) {
            if ($a['available'] !== $b['available']) {
                return $a['available'] ? -1 : 1;
            }
            return strcmp($a['nick'], $b['nick']);
        });
        return $list;
    }
}

if (!function_exists('seotda_guest_join_precheck')) {
    /** @return array{ok:bool,data?:string,bet?:int} */
    function seotda_guest_join_precheck($guest_nick, $bet_raw) {
        $guest_nick = trim((string)$guest_nick);
        if ($guest_nick === '') {
            return ['ok' => false, 'data' => '❌ 닉네임 오류'];
        }
        $bet = $bet_raw !== null && $bet_raw !== '' ? seotda_parse_bet($bet_raw) : (int)SEOTDA_DEFAULT_BET;
        if ($bet < (int)SEOTDA_MIN_BET) {
            return ['ok' => false, 'data' => '❌ 최소 ' . seotda_fmt_game(SEOTDA_MIN_BET) . ' 이상 설정해주세요.'];
        }
        $guest_esc = addslashes($guest_nick);
        if (seotda_member_point($guest_esc) < $bet) {
            return ['ok' => false, 'data' => '❌ 게임냥이 부족해요. (필요 ' . seotda_fmt_game($bet) . ')'];
        }
        $as_guest = @db_select("
            SELECT * FROM tb_seotda_room
            WHERE guest_nick = '{$guest_esc}'
              AND mode IN ('match_pending','waiting','pvp')
            ORDER BY idx DESC LIMIT 1
        ");
        if (is_array($as_guest) && !empty($as_guest['idx'])) {
            return ['ok' => false, 'data' => '❌ 이미 다른 방에 참여 중이에요. 먼저 「나가기」를 눌러주세요.'];
        }
        return ['ok' => true, 'bet' => $bet];
    }
}

if (!function_exists('seotda_join_host_room')) {
    /** @return array{ok:bool,data?:string,room?:array,matched_host?:string} */
    function seotda_join_host_room($guest_nick, $host_nick, $bet_raw) {
        $guest_nick = trim((string)$guest_nick);
        $host_nick = trim((string)$host_nick);
        if ($host_nick === '') {
            return ['ok' => false, 'data' => '❌ 상대를 선택해주세요.'];
        }
        if ($host_nick === $guest_nick) {
            return ['ok' => false, 'data' => '❌ 내 방에는 들어갈 수 없어요.'];
        }

        $pre = seotda_guest_join_precheck($guest_nick, $bet_raw);
        if (empty($pre['ok'])) {
            return $pre;
        }
        $bet = (int)$pre['bet'];

        $online = seotda_online_host_nicks($guest_nick);
        if (!in_array($host_nick, $online, true)) {
            return ['ok' => false, 'data' => '❌ ' . $host_nick . '님은 섯다 페이지 접속 중이 아니에요.'];
        }

        if (!seotda_host_available_for_guest($host_nick)) {
            return ['ok' => false, 'data' => '❌ ' . $host_nick . '님은 지금 입장할 수 없어요. (대결 중)'];
        }

        $host_room = seotda_room_by_host($host_nick);
        if (!$host_room) {
            return ['ok' => false, 'data' => '❌ 상대 방을 찾을 수 없어요.'];
        }

        $id = (int)$host_room['idx'];
        db_query("UPDATE tb_seotda_room SET bet_amount = {$bet} WHERE idx = {$id} LIMIT 1");
        $host_room = seotda_room_by_token($host_room['room_token']);

        $join = seotda_room_join_guest($host_room['room_token'], $guest_nick);
        if (empty($join['ok'])) {
            return $join;
        }

        $msg = '✅ ' . $host_nick . '님 방 입장 · 배팅 ' . seotda_fmt_game($bet) . "\n";
        $msg .= $join['data'] ?? '';

        return [
            'ok' => true,
            'room' => $join['room'] ?? $host_room,
            'data' => trim($msg),
            'matched_host' => $host_nick,
        ];
    }
}
