<?php

if (!function_exists('seotda_pvp_start')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_pvp_start(array $room) {
        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        $bet = (int)($room['bet_amount'] ?? SEOTDA_DEFAULT_BET);
        $host_esc = addslashes($host);
        $guest_esc = addslashes($guest);

        if (seotda_member_point($host_esc) < $bet || seotda_member_point($guest_esc) < $bet) {
            db_query("
                UPDATE tb_seotda_room
                SET mode = 'waiting', host_ready = 0, guest_ready = 0,
                    waiting_since = NOW(), guest_action_since = NOW(), ready_wait_since = NULL
                WHERE idx = " . (int)$room['idx'] . " LIMIT 1
            ");
            return ['ok' => false, 'data' => '❌ 한쪽 잔액 부족 — 다시 준비해주세요.'];
        }

        $deck = seotda_deck_new();
        [$hc1, $hc2, $hc3] = seotda_deal_triple($deck);
        [$gc1, $gc2, $gc3] = seotda_deal_triple($deck);
        $pot = $bet * 2;
        $round = (int)($room['round_no'] ?? 0) + 1;
        $id = (int)$room['idx'];
        $host_peek = seotda_random_peek_slot();
        $guest_peek = seotda_random_peek_slot();
        $peek_sql = '';
        if (function_exists('seotda_has_peek_slot_columns') && seotda_has_peek_slot_columns()) {
            $peek_sql = ", host_peek_slot = {$host_peek}, guest_peek_slot = {$guest_peek}";
        }

        db_query("UPDATE tb_member SET point = point - {$bet} WHERE name = '{$host_esc}' LIMIT 1");
        db_query("UPDATE tb_member SET point = point - {$bet} WHERE name = '{$guest_esc}' LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('섯다-pvp배팅', $host, $guest, 0, $bet);
            지급로그('섯다-pvp배팅', $guest, $host, 0, $bet);
        }

        db_query("
            UPDATE tb_seotda_room
            SET mode = 'pvp',
                pvp_phase = 'playing',
                solo_phase = 'idle',
                host_ready = 0,
                guest_ready = 0,
                waiting_since = NULL,
                ready_wait_since = NULL,
                round_no = {$round},
                pot = {$pot},
                host_card1 = {$hc1}, host_card2 = {$hc2}, host_card3 = {$hc3},
                guest_card1 = {$gc1}, guest_card2 = {$gc2}, guest_card3 = {$gc3},
                sys_card1 = NULL, sys_card2 = NULL, sys_card3 = NULL,
                host_picked = 0, guest_picked = 0,
                host_pick1 = NULL, host_pick2 = NULL,
                guest_pick1 = NULL, guest_pick2 = NULL,
                last_result = NULL{$peek_sql},
                guest_action_since = NOW()
            WHERE idx = {$id} LIMIT 1
        ");

        $room = seotda_room_by_token($room['room_token']);
        return ['ok' => true, 'room' => $room, 'data' => '🔥 1:1 섯다 · 랜덤 1장 공개 · 나머지 1장 고르면 바로 오픈 · 판돈 ' . seotda_fmt_game($pot)];
    }
}

if (!function_exists('seotda_pvp_result_summary')) {
    /** @return array<string,mixed>|null */
    function seotda_pvp_result_summary(array $room) {
        if ((string)($room['pvp_phase'] ?? 'idle') !== 'done') {
            return null;
        }
        if (empty($room['host_pick1']) || empty($room['guest_pick1'])) {
            return null;
        }
        $host = (string)($room['host_nick'] ?? '');
        $guest = (string)($room['guest_nick'] ?? '');
        if ($host === '' || $guest === '') {
            return null;
        }

        $host_pair = seotda_pick_slots(
            $room['host_card1'], $room['host_card2'], $room['host_card3'],
            $room['host_pick1'], $room['host_pick2']
        );
        $guest_pair = seotda_pick_slots(
            $room['guest_card1'], $room['guest_card2'], $room['guest_card3'],
            $room['guest_pick1'], $room['guest_pick2']
        );
        if (!$host_pair || !$guest_pair) {
            return null;
        }

        $h = seotda_hand_eval($host_pair[0], $host_pair[1]);
        $g = seotda_hand_eval($guest_pair[0], $guest_pair[1]);
        $cmp = seotda_compare_hands($h, $g);
        $bet = (int)($room['bet_amount'] ?? 0);
        $round = (int)($room['round_no'] ?? 0);
        $winner = null;
        if ($cmp > 0) {
            $winner = $host;
        } elseif ($cmp < 0) {
            $winner = $guest;
        }

        $pot = $bet * 2;
        $draw_split = function_exists('seotda_draw_split_pvp')
            ? seotda_draw_split_pvp($pot)
            : [
                'vault' => (int)floor($pot / 4),
                'lotto' => (int)floor($pot / 4),
                'host_refund' => (int)floor($pot / 4) + ($pot % 4),
                'guest_refund' => (int)floor($pot / 4),
            ];

        return [
            'result_id' => $round . '-' . (string)($room['room_token'] ?? ''),
            'round_no' => $round,
            'host_nick' => $host,
            'guest_nick' => $guest,
            'host_label' => $h['label'],
            'guest_label' => $g['label'],
            'host_pool' => implode('·', [
                seotda_card_label($room['host_card1']),
                seotda_card_label($room['host_card2']),
                seotda_card_label($room['host_card3']),
            ]),
            'guest_pool' => implode('·', [
                seotda_card_label($room['guest_card1']),
                seotda_card_label($room['guest_card2']),
                seotda_card_label($room['guest_card3']),
            ]),
            'winner_nick' => $winner,
            'is_draw' => ($cmp === 0),
            'bet_fmt' => seotda_fmt_game($bet),
            'pot_fmt' => seotda_fmt_game($pot),
            'refund_fmt' => seotda_fmt_game((int)$draw_split['host_refund']),
            'vault_fmt' => seotda_fmt_game((int)$draw_split['vault']),
            'lotto_fmt' => seotda_fmt_game((int)($draw_split['lotto'] ?? 0)),
            'host_refund_fmt' => seotda_fmt_game((int)$draw_split['host_refund']),
            'guest_refund_fmt' => seotda_fmt_game((int)$draw_split['guest_refund']),
        ];
    }
}

if (!function_exists('seotda_pvp_settle')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_pvp_settle(array $room) {
        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        $bet = (int)($room['bet_amount'] ?? 0);
        $pot = (int)($room['pot'] ?? $bet * 2);

        $host_pair = seotda_pick_slots(
            $room['host_card1'], $room['host_card2'], $room['host_card3'],
            $room['host_pick1'], $room['host_pick2']
        );
        $guest_pair = seotda_pick_slots(
            $room['guest_card1'], $room['guest_card2'], $room['guest_card3'],
            $room['guest_pick1'], $room['guest_pick2']
        );
        if (!$host_pair || !$guest_pair) {
            return ['ok' => false, 'data' => '❌ 패 선택 오류'];
        }

        $h = seotda_hand_eval($host_pair[0], $host_pair[1]);
        $g = seotda_hand_eval($guest_pair[0], $guest_pair[1]);
        $cmp = seotda_compare_hands($h, $g);

        $host_esc = addslashes($host);
        $guest_esc = addslashes($guest);
        $host_delta = -$bet;
        $guest_delta = -$bet;

        $host_pool = implode('·', [
            seotda_card_label($room['host_card1']),
            seotda_card_label($room['host_card2']),
            seotda_card_label($room['host_card3']),
        ]);
        $guest_pool = implode('·', [
            seotda_card_label($room['guest_card1']),
            seotda_card_label($room['guest_card2']),
            seotda_card_label($room['guest_card3']),
        ]);

        $msg = "🃏 {$host} {$h['label']} ({$host_pool})\n";
        $msg .= "🃏 {$guest} {$g['label']} ({$guest_pool})\n";
        $winner = null;

        if ($cmp > 0) {
            db_query("UPDATE tb_member SET point = point + {$pot} WHERE name = '{$host_esc}' LIMIT 1");
            $host_delta = $bet;
            $guest_delta = -$bet;
            $winner = $host;
            $msg .= "✅ {$host} 승리! +" . seotda_fmt_game($bet) . ' (총 ' . seotda_fmt_game($pot) . ' 수령)';
        } elseif ($cmp < 0) {
            db_query("UPDATE tb_member SET point = point + {$pot} WHERE name = '{$guest_esc}' LIMIT 1");
            $host_delta = -$bet;
            $guest_delta = $bet;
            $winner = $guest;
            $msg .= "✅ {$guest} 승리! +" . seotda_fmt_game($bet) . ' (총 ' . seotda_fmt_game($pot) . ' 수령)';
        } else {
            $draw = function_exists('seotda_draw_split_pvp')
                ? seotda_draw_split_pvp($pot)
                : [
                    'vault' => (int)floor($pot / 4),
                    'lotto' => (int)floor($pot / 4),
                    'host_refund' => (int)floor($pot / 4) + ($pot % 4),
                    'guest_refund' => (int)floor($pot / 4),
                ];
            $vault_amt = (int)$draw['vault'];
            $lotto_amt = (int)($draw['lotto'] ?? 0);
            $host_refund = (int)$draw['host_refund'];
            $guest_refund = (int)$draw['guest_refund'];
            if ($host_refund > 0) {
                db_query("UPDATE tb_member SET point = point + {$host_refund} WHERE name = '{$host_esc}' LIMIT 1");
            }
            if ($guest_refund > 0) {
                db_query("UPDATE tb_member SET point = point + {$guest_refund} WHERE name = '{$guest_esc}' LIMIT 1");
            }
            if ($vault_amt > 0) {
                seotda_vault_add($vault_amt);
            }
            if ($lotto_amt > 0) {
                seotda_lotto_add($lotto_amt);
            }
            $host_delta = -$bet + $host_refund;
            $guest_delta = -$bet + $guest_refund;
            $msg .= '🤝 무승부 · 각 환급 ' . seotda_fmt_game($host_refund)
                . ' · 금고 ' . seotda_fmt_game($vault_amt)
                . ' · 로또 ' . seotda_fmt_game($lotto_amt);
            if (function_exists('지급로그')) {
                $fee = $vault_amt + $lotto_amt;
                지급로그('섯다-pvp무', $host, $guest, $fee, $host_refund);
                지급로그('섯다-pvp무', $guest, $host, $fee, $guest_refund);
            }
        }

        if (function_exists('지급로그') && $winner !== null) {
            $lose = ($winner === $host) ? $guest : $host;
            $win_amt = $bet;
            지급로그('섯다-pvp승', $winner, $lose, 0, $win_amt);
        }

        $id = (int)$room['idx'];
        $msg_with_next = $msg . "\n\n🔄 다음 판: 양쪽 「1:1 준비」 · 종료는 「나가기」";
        $msg_next_esc = addslashes($msg_with_next);
        db_query("
            UPDATE tb_seotda_room
            SET mode = 'waiting',
                pvp_phase = 'done',
                solo_phase = 'idle',
                pot = 0,
                host_ready = 0,
                guest_ready = 0,
                waiting_since = NOW(),
                guest_action_since = NOW(),
                ready_wait_since = NULL,
                last_result = '{$msg_next_esc}'
            WHERE idx = {$id} LIMIT 1
        ");

        seotda_log_insert([
            'room_id' => $id,
            'room_token' => $room['room_token'],
            'mode' => 'pvp',
            'host_nick' => $host,
            'guest_nick' => $guest,
            'bet_amount' => $bet,
            'winner_nick' => $winner,
            'host_label' => $h['label'],
            'guest_label' => $g['label'],
            'host_delta' => $host_delta,
            'guest_delta' => $guest_delta,
        ]);

        $room = seotda_room_by_token($room['room_token']);
        return ['ok' => true, 'room' => $room, 'data' => $msg_with_next];
    }
}

if (!function_exists('seotda_pvp_reveal')) {
    /** @return array{ok:bool,data?:string,room?:array} */
    function seotda_pvp_reveal(array $room, $nick, $pick1_raw, $pick2_raw) {
        if (($room['mode'] ?? '') !== 'pvp' || ($room['pvp_phase'] ?? '') !== 'playing') {
            return ['ok' => false, 'data' => '❌ 진행 중인 PvP 판이 없어요.'];
        }
        $host = (string)$room['host_nick'];
        $guest = (string)$room['guest_nick'];
        if ($nick !== $host && $nick !== $guest) {
            return ['ok' => false, 'data' => '❌ 참가자만 선택할 수 있어요.'];
        }

        $picks = seotda_parse_pick_params($pick1_raw, $pick2_raw);
        if (!$picks) {
            return ['ok' => false, 'data' => '❌ 패 2장을 선택해주세요. (1~3번 중 서로 다른 2개)'];
        }
        [$pick1, $pick2] = $picks;

        if ($nick === $host) {
            if ((int)($room['host_picked'] ?? 0) === 1) {
                return ['ok' => false, 'data' => '❌ 이미 패를 선택했어요. 상대를 기다려주세요.'];
            }
            $pair = seotda_pick_slots($room['host_card1'], $room['host_card2'], $room['host_card3'], $pick1, $pick2);
            if (!$pair) {
                return ['ok' => false, 'data' => '❌ 패 선택이 올바르지 않아요.'];
            }
            $id = (int)$room['idx'];
            db_query("
                UPDATE tb_seotda_room
                SET host_picked = 1, host_pick1 = {$pick1}, host_pick2 = {$pick2}
                WHERE idx = {$id} LIMIT 1
            ");
        } else {
            if ((int)($room['guest_picked'] ?? 0) === 1) {
                return ['ok' => false, 'data' => '❌ 이미 패를 선택했어요. 상대를 기다려주세요.'];
            }
            $pair = seotda_pick_slots($room['guest_card1'], $room['guest_card2'], $room['guest_card3'], $pick1, $pick2);
            if (!$pair) {
                return ['ok' => false, 'data' => '❌ 패 선택이 올바르지 않아요.'];
            }
            $id = (int)$room['idx'];
            db_query("
                UPDATE tb_seotda_room
                SET guest_picked = 1, guest_pick1 = {$pick1}, guest_pick2 = {$pick2},
                    guest_action_since = NULL
                WHERE idx = {$id} LIMIT 1
            ");
        }

        $room = seotda_room_by_token($room['room_token']);
        $host_done = (int)($room['host_picked'] ?? 0) === 1;
        $guest_done = (int)($room['guest_picked'] ?? 0) === 1;

        if (!$host_done || !$guest_done) {
            return [
                'ok' => true,
                'room' => $room,
                'data' => '✅ 패 선택 완료 · 상대 선택 대기 중…',
            ];
        }

        return seotda_pvp_settle($room);
    }
}
