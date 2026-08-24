<?php

if (!function_exists('seotda_solo_start')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_solo_start(array $room, $host_nick, $bet_raw) {
        if ($room['host_nick'] !== $host_nick) {
            return ['ok' => false, 'data' => '❌ 호스트만 솔로 판을 시작할 수 있어요.'];
        }
        $mode = (string)($room['mode'] ?? 'solo');
        if (!in_array($mode, ['solo', 'match_pending'], true)) {
            return ['ok' => false, 'data' => '❌ PvP 진행 중에는 솔로를 시작할 수 없어요.'];
        }
        if (($room['solo_phase'] ?? '') === 'playing') {
            return ['ok' => false, 'data' => '❌ 이미 솔로 판 진행 중이에요.'];
        }

        $bet = $bet_raw !== null && $bet_raw !== '' ? seotda_parse_bet($bet_raw) : (int)($room['bet_amount'] ?? SEOTDA_DEFAULT_BET);
        if ($bet < (int)SEOTDA_MIN_BET) {
            return ['ok' => false, 'data' => '❌ 최소 ' . seotda_fmt_game(SEOTDA_MIN_BET) . ' 이상이에요.'];
        }
        $host_esc = addslashes($host_nick);
        if (seotda_point_lt(seotda_member_point($host_esc), $bet)) {
            return ['ok' => false, 'data' => '❌ 게임냥이 부족해요.'];
        }

        $host_peek = seotda_random_peek_slot();
        if (defined('SEOTDA_SOLO_PLAYER_WIN_PCT')) {
            $deal = seotda_solo_deal_balanced((int)SEOTDA_SOLO_PLAYER_WIN_PCT, $host_peek);
            [$hc1, $hc2, $hc3] = $deal['host'];
            [$sc1, $sc2, $sc3] = $deal['sys'];
        } else {
            $deck = seotda_deck_new();
            [$hc1, $hc2, $hc3] = seotda_deal_triple($deck);
            [$sc1, $sc2, $sc3] = seotda_deal_triple($deck);
        }
        $id = (int)$room['idx'];
        $round = (int)($room['round_no'] ?? 0) + 1;
        $peek_sql = '';
        if (function_exists('seotda_has_peek_slot_columns') && seotda_has_peek_slot_columns()) {
            $peek_sql = ", host_peek_slot = {$host_peek}, guest_peek_slot = NULL";
        }

        db_query("UPDATE tb_member SET point = point - {$bet} WHERE name = '{$host_esc}' LIMIT 1");
        db_query("
            UPDATE tb_seotda_room
            SET bet_amount = {$bet},
                solo_phase = 'playing',
                pvp_phase = 'idle',
                round_no = {$round},
                pot = {$bet},
                host_card1 = {$hc1}, host_card2 = {$hc2}, host_card3 = {$hc3},
                sys_card1 = {$sc1}, sys_card2 = {$sc2}, sys_card3 = {$sc3},
                guest_card1 = NULL, guest_card2 = NULL, guest_card3 = NULL,
                host_picked = 0, guest_picked = 0,
                host_pick1 = NULL, host_pick2 = NULL,
                guest_pick1 = NULL, guest_pick2 = NULL,
                last_result = NULL{$peek_sql}
            WHERE idx = {$id} LIMIT 1
        ");
        if (function_exists('지급로그')) {
            지급로그('섯다-솔로배팅', $host_nick, SEOTDA_SYSTEM_NICK, 0, $bet);
        }

        $room = seotda_room_by_token($room['room_token']);
        return ['ok' => true, 'room' => $room, 'data' => '🃏 솔로 섯다 · 랜덤 1장 공개 · 나머지 1장 고르면 바로 오픈'];
    }
}

if (!function_exists('seotda_solo_pick_hints_for_room')) {
    /**
     * 공개 패 + 나머지 1장 선택 시 승패 힌트 (점검용)
     *
     * @return array<int,array{win_pct:int,hand:string,card:string,result:string}>|null
     */
    function seotda_solo_pick_hints_for_room(array $room) {
        $peek = (int)($room['host_peek_slot'] ?? 0);
        if ($peek < 1 || $peek > 3) {
            return null;
        }
        $c1 = (int)($room['host_card1'] ?? 0);
        $c2 = (int)($room['host_card2'] ?? 0);
        $c3 = (int)($room['host_card3'] ?? 0);
        if ($c1 < 1 || $c2 < 1 || $c3 < 1) {
            return null;
        }
        $sys_best = seotda_best_pair_from_triple(
            $room['sys_card1'], $room['sys_card2'], $room['sys_card3']
        );
        if (!$sys_best) {
            return null;
        }
        $pool = [1 => $c1, 2 => $c2, 3 => $c3];
        $hints = [];
        for ($slot = 1; $slot <= 3; $slot++) {
            if ($slot === $peek) {
                continue;
            }
            $picked = seotda_pick_slots($c1, $c2, $c3, $peek, $slot);
            if (!$picked) {
                continue;
            }
            $hand = seotda_hand_eval($picked[0], $picked[1]);
            $cmp = seotda_compare_hands($hand, $sys_best['hand']);
            $hints[$slot] = [
                'win_pct' => $cmp > 0 ? 100 : ($cmp < 0 ? 0 : 50),
                'hand' => $hand['label'],
                'card' => seotda_card_label($pool[$slot]),
                'result' => $cmp > 0 ? 'win' : ($cmp < 0 ? 'lose' : 'draw'),
            ];
        }
        return $hints ?: null;
    }
}

if (!function_exists('seotda_solo_reveal')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_solo_reveal(array $room, $host_nick, $pick1_raw, $pick2_raw) {
        if ($room['host_nick'] !== $host_nick) {
            return ['ok' => false, 'data' => '❌ 호스트만 오픈할 수 있어요.'];
        }
        if (($room['solo_phase'] ?? '') !== 'playing') {
            return ['ok' => false, 'data' => '❌ 진행 중인 솔로 판이 없어요.'];
        }

        $picks = seotda_parse_pick_params($pick1_raw, $pick2_raw);
        if (!$picks) {
            return ['ok' => false, 'data' => '❌ 패 2장을 선택해주세요. (1~3번 중 서로 다른 2개)'];
        }
        [$pick1, $pick2] = $picks;

        if (function_exists('seotda_has_peek_slot_columns') && seotda_has_peek_slot_columns()) {
            $peek = (int)($room['host_peek_slot'] ?? 0);
            if ($peek >= 1 && $peek <= 3 && $pick1 !== $peek && $pick2 !== $peek) {
                return ['ok' => false, 'data' => '❌ 공개된 패는 반드시 포함해야 해요.'];
            }
        }

        $host_pair = seotda_pick_slots(
            $room['host_card1'], $room['host_card2'], $room['host_card3'],
            $pick1, $pick2
        );
        if (!$host_pair) {
            return ['ok' => false, 'data' => '❌ 패 선택이 올바르지 않아요.'];
        }

        $sys_best = seotda_best_pair_from_triple($room['sys_card1'], $room['sys_card2'], $room['sys_card3']);
        if (!$sys_best) {
            return ['ok' => false, 'data' => '❌ 패 오류'];
        }

        $bet = (int)($room['bet_amount'] ?? 0);
        $host_esc = addslashes($host_nick);
        $h = seotda_hand_eval($host_pair[0], $host_pair[1]);
        $s = $sys_best['hand'];

        $host_pool = implode('·', [
            seotda_card_label($room['host_card1']),
            seotda_card_label($room['host_card2']),
            seotda_card_label($room['host_card3']),
        ]);
        $sys_pool = implode('·', [
            seotda_card_label($room['sys_card1']),
            seotda_card_label($room['sys_card2']),
            seotda_card_label($room['sys_card3']),
        ]);
        $cmp = seotda_compare_hands($h, $s);

        $msg = "🃏 내 {$h['label']} ({$host_pool})\n";
        $msg .= "🤖 시스템 {$s['label']} ({$sys_pool})\n";
        $host_delta = 0;

        if ($cmp > 0) {
            $win = $bet * 2;
            db_query("UPDATE tb_member SET point = point + {$win} WHERE name = '{$host_esc}' LIMIT 1");
            $host_delta = $bet;
            $msg .= '✅ 승리! +' . seotda_fmt_game($bet) . ' (총 ' . seotda_fmt_game($bet * 2) . ' 수령)';
            $winner = $host_nick;
        } elseif ($cmp < 0) {
            $host_delta = -$bet;
            $msg .= '❌ 패배 · -' . seotda_fmt_game($bet);
            $winner = SEOTDA_SYSTEM_NICK;
        } else {
            $draw = function_exists('seotda_draw_split') ? seotda_draw_split($bet) : [
                'vault' => (int)floor($bet / 4),
                'lotto' => (int)floor($bet / 4),
                'refund' => $bet - (int)floor($bet / 4) * 2,
            ];
            $vault_amt = (int)$draw['vault'];
            $lotto_amt = (int)($draw['lotto'] ?? 0);
            $refund = (int)$draw['refund'];
            if ($refund > 0) {
                db_query("UPDATE tb_member SET point = point + {$refund} WHERE name = '{$host_esc}' LIMIT 1");
            }
            if ($vault_amt > 0) {
                seotda_vault_add($vault_amt);
            }
            if ($lotto_amt > 0) {
                seotda_lotto_add($lotto_amt);
            }
            $host_delta = -$bet + $refund;
            $msg .= '🤝 무승부 · 환급 ' . seotda_fmt_game($refund)
                . ' · 금고 ' . seotda_fmt_game($vault_amt)
                . ' · 로또 ' . seotda_fmt_game($lotto_amt);
            $winner = null;
            if (function_exists('지급로그')) {
                지급로그('섯다-솔로무', $host_nick, SEOTDA_SYSTEM_NICK, $vault_amt + $lotto_amt, $refund);
            }
        }

        if (function_exists('지급로그') && $cmp > 0) {
            지급로그('섯다-솔로승', $host_nick, SEOTDA_SYSTEM_NICK, 0, $bet);
        }

        $id = (int)$room['idx'];
        $msg_esc = addslashes($msg);
        db_query("
            UPDATE tb_seotda_room
            SET solo_phase = 'done',
                pot = 0,
                host_picked = 1,
                host_pick1 = {$pick1},
                host_pick2 = {$pick2},
                last_result = '{$msg_esc}'
            WHERE idx = {$id} LIMIT 1
        ");

        seotda_log_insert([
            'room_id' => $id,
            'room_token' => $room['room_token'],
            'mode' => 'solo',
            'host_nick' => $host_nick,
            'guest_nick' => SEOTDA_SYSTEM_NICK,
            'bet_amount' => $bet,
            'winner_nick' => $winner,
            'host_label' => $h['label'],
            'guest_label' => $s['label'],
            'host_delta' => $host_delta,
            'guest_delta' => 0,
        ]);

        $room = seotda_room_by_token($room['room_token']);
        $room = seotda_room_after_solo_done($room);
        return ['ok' => true, 'room' => $room, 'data' => $msg];
    }
}

if (!function_exists('seotda_solo_result_summary')) {
    /** @return array<string,mixed>|null */
    function seotda_solo_result_summary(array $room) {
        if ((string)($room['solo_phase'] ?? '') !== 'done') {
            return null;
        }
        if (empty($room['host_pick1']) || empty($room['host_pick2'])) {
            return null;
        }
        $host = (string)($room['host_nick'] ?? '');
        if ($host === '') {
            return null;
        }

        $host_pair = seotda_pick_slots(
            $room['host_card1'], $room['host_card2'], $room['host_card3'],
            $room['host_pick1'], $room['host_pick2']
        );
        $sys_best = seotda_best_pair_from_triple($room['sys_card1'], $room['sys_card2'], $room['sys_card3']);
        if (!$host_pair || !$sys_best) {
            return null;
        }

        $h = seotda_hand_eval($host_pair[0], $host_pair[1]);
        $s = $sys_best['hand'];
        $cmp = seotda_compare_hands($h, $s);
        $bet = (int)($room['bet_amount'] ?? 0);
        $round = (int)($room['round_no'] ?? 0);
        $winner = null;
        if ($cmp > 0) {
            $winner = $host;
        } elseif ($cmp < 0) {
            $winner = SEOTDA_SYSTEM_NICK;
        }

        $draw_split = function_exists('seotda_draw_split') ? seotda_draw_split($bet) : [
            'vault' => (int)floor($bet / 4),
            'lotto' => (int)floor($bet / 4),
            'refund' => $bet - (int)floor($bet / 4) * 2,
        ];

        return [
            'result_id' => 'solo-' . $round . '-' . (string)($room['room_token'] ?? ''),
            'round_no' => $round,
            'host_nick' => $host,
            'sys_nick' => '🤖 시스템',
            'host_label' => $h['label'],
            'sys_label' => $s['label'],
            'host_pool' => implode('·', [
                seotda_card_label($room['host_card1']),
                seotda_card_label($room['host_card2']),
                seotda_card_label($room['host_card3']),
            ]),
            'sys_pool' => implode('·', [
                seotda_card_label($room['sys_card1']),
                seotda_card_label($room['sys_card2']),
                seotda_card_label($room['sys_card3']),
            ]),
            'winner_nick' => $winner,
            'is_draw' => ($cmp === 0),
            'bet_fmt' => seotda_fmt_game($bet),
            'pot_fmt' => seotda_fmt_game($bet * 2),
            'refund_fmt' => seotda_fmt_game((int)$draw_split['refund']),
            'vault_fmt' => seotda_fmt_game((int)$draw_split['vault']),
            'lotto_fmt' => seotda_fmt_game((int)($draw_split['lotto'] ?? 0)),
        ];
    }
}

if (!function_exists('seotda_solo_reset_idle')) {
    function seotda_solo_reset_idle(array $room, $host_nick) {
        if ($room['host_nick'] !== $host_nick) {
            return $room;
        }
        if (($room['solo_phase'] ?? '') !== 'done') {
            return $room;
        }
        if (in_array($room['mode'] ?? '', ['waiting', 'pvp'], true)) {
            return $room;
        }
        $id = (int)$room['idx'];
        db_query("UPDATE tb_seotda_room SET solo_phase = 'idle', last_result = NULL WHERE idx = {$id} LIMIT 1");
        return seotda_room_by_token($room['room_token']);
    }
}
