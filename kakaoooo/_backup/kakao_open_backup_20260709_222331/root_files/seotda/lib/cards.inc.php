<?php
/**
 * 섯다 MVP — 1~10월 2장씩(20장) · 땡 > 끗 > 망통
 */

if (!function_exists('seotda_deck_new')) {
    function seotda_deck_new() {
        $deck = [];
        for ($m = 1; $m <= 10; $m++) {
            $deck[] = $m;
            $deck[] = $m;
        }
        shuffle($deck);
        return $deck;
    }
}

if (!function_exists('seotda_deal_pair')) {
    /** @return array{0:int,1:int} */
    function seotda_deal_pair(array &$deck) {
        if (count($deck) < 2) {
            $deck = seotda_deck_new();
        }
        $c1 = (int)array_shift($deck);
        $c2 = (int)array_shift($deck);
        return [$c1, $c2];
    }
}

if (!function_exists('seotda_deal_triple')) {
    /** @return array{0:int,1:int,2:int} */
    function seotda_deal_triple(array &$deck) {
        if (count($deck) < 3) {
            $deck = seotda_deck_new();
        }
        return [(int)array_shift($deck), (int)array_shift($deck), (int)array_shift($deck)];
    }
}

if (!function_exists('seotda_random_peek_slot')) {
    /** 패 3장 중 랜덤 1장 공개 슬롯 (1~3) */
    function seotda_random_peek_slot() {
        return random_int(1, 3);
    }
}

if (!function_exists('seotda_pick_slots')) {
    /**
     * 슬롯 1~3에서 2장 선택
     *
     * @return array{0:int,1:int}|null
     */
    function seotda_pick_slots($c1, $c2, $c3, $pick1, $pick2) {
        $pool = [1 => (int)$c1, 2 => (int)$c2, 3 => (int)$c3];
        $p1 = (int)$pick1;
        $p2 = (int)$pick2;
        if ($p1 === $p2 || $p1 < 1 || $p1 > 3 || $p2 < 1 || $p2 > 3) {
            return null;
        }
        return [$pool[$p1], $pool[$p2]];
    }
}

if (!function_exists('seotda_best_pair_from_triple')) {
    /**
     * 3장 중 족보 최고 2장 조합
     *
     * @return array{pick1:int,pick2:int,c1:int,c2:int,hand:array}
     */
    function seotda_best_pair_from_triple($c1, $c2, $c3) {
        $pairs = [[1, 2], [1, 3], [2, 3]];
        $best = null;
        foreach ($pairs as [$i, $j]) {
            $picked = seotda_pick_slots($c1, $c2, $c3, $i, $j);
            if (!$picked) {
                continue;
            }
            $hand = seotda_hand_eval($picked[0], $picked[1]);
            if ($best === null || seotda_compare_hands($hand, $best['hand']) > 0) {
                $best = [
                    'pick1' => $i,
                    'pick2' => $j,
                    'c1' => $picked[0],
                    'c2' => $picked[1],
                    'hand' => $hand,
                ];
            }
        }
        return $best;
    }
}

if (!function_exists('seotda_peek_user_win_prob')) {
    /**
     * 랜덤 공개 슬롯 기준 — 나머지 1장을 랜덤 선택 시 승리 확률 (0 / 0.5 / 1)
     * 1.0 = 두 선택 모두 시스템 최적 패보다 강함, 0.0 = 둘 다 약함
     */
    function seotda_peek_user_win_prob($c1, $c2, $c3, $peek_slot, array $sys_hand) {
        $peek = (int)$peek_slot;
        if ($peek < 1 || $peek > 3) {
            return 0.0;
        }
        $others = [];
        for ($s = 1; $s <= 3; $s++) {
            if ($s !== $peek) {
                $others[] = $s;
            }
        }
        $picked_a = seotda_pick_slots($c1, $c2, $c3, $peek, $others[0]);
        $picked_b = seotda_pick_slots($c1, $c2, $c3, $peek, $others[1]);
        if (!$picked_a || !$picked_b) {
            return 0.0;
        }
        $h1 = seotda_hand_eval($picked_a[0], $picked_a[1]);
        $h2 = seotda_hand_eval($picked_b[0], $picked_b[1]);
        $w1 = seotda_compare_hands($h1, $sys_hand) > 0;
        $w2 = seotda_compare_hands($h2, $sys_hand) > 0;
        if ($w1 && $w2) {
            return 1.0;
        }
        if (!$w1 && !$w2) {
            return 0.0;
        }
        return 0.5;
    }
}

if (!function_exists('seotda_user_best_hand_for_peek')) {
    /**
     * 랜덤 공개 슬롯 포함 시 유저가 고를 수 있는 최선의 족보
     * (공개 1장 + 나머지 2장 중 1장 선택)
     *
     * @return array{kind:string,power:int,label:string,kkeut:int}|null
     */
    function seotda_user_best_hand_for_peek($c1, $c2, $c3, $peek_slot) {
        $peek = (int)$peek_slot;
        if ($peek < 1 || $peek > 3) {
            return null;
        }
        $others = [];
        for ($s = 1; $s <= 3; $s++) {
            if ($s !== $peek) {
                $others[] = $s;
            }
        }
        $picked_a = seotda_pick_slots($c1, $c2, $c3, $peek, $others[0]);
        $picked_b = seotda_pick_slots($c1, $c2, $c3, $peek, $others[1]);
        if (!$picked_a || !$picked_b) {
            return null;
        }
        $h1 = seotda_hand_eval($picked_a[0], $picked_a[1]);
        $h2 = seotda_hand_eval($picked_b[0], $picked_b[1]);
        return seotda_compare_hands($h1, $h2) >= 0 ? $h1 : $h2;
    }
}

if (!function_exists('seotda_solo_deal_balanced')) {
    /**
     * 솔로 6장 딜 — 공개 1장 + 나머지 2장 중 1장 선택
     * $peek_slot 지정 시: 기본 1승1패, SEOTDA_SOLO_BOTH_LOSE_PCT 만큼 둘 다 패 판 혼입
     *
     * @param int|null $player_win_pct 유저(호스트) 승리 목표 % (peek 없을 때만 반영)
     * @param int|null $peek_slot 랜덤 공개 슬롯 1~3 (솔로)
     * @return array{host:array{0:int,1:int,2:int},sys:array{0:int,1:int,2:int}}
     */
    function seotda_solo_deal_balanced($player_win_pct = null, $peek_slot = null) {
        if ($player_win_pct === null) {
            $player_win_pct = defined('SEOTDA_SOLO_PLAYER_WIN_PCT')
                ? (int)SEOTDA_SOLO_PLAYER_WIN_PCT
                : 50;
        }
        $player_win_pct = max(0, min(100, (int)$player_win_pct));
        $desired_user_win = random_int(1, 100) <= $player_win_pct;
        $fallback_deck = seotda_deck_new();
        $fallback = [
            'host' => seotda_deal_triple($fallback_deck),
            'sys' => seotda_deal_triple($fallback_deck),
        ];

        if ($peek_slot === null) {
            for ($try = 0; $try < 1200; $try++) {
                $deck = seotda_deck_new();
                $host = seotda_deal_triple($deck);
                $sys = seotda_deal_triple($deck);
                $host_best = seotda_best_pair_from_triple($host[0], $host[1], $host[2]);
                $sys_best = seotda_best_pair_from_triple($sys[0], $sys[1], $sys[2]);
                if (!$host_best || !$sys_best) {
                    continue;
                }
                $cmp = seotda_compare_hands($host_best['hand'], $sys_best['hand']);
                if ($cmp === 0) {
                    continue;
                }
                $user_wins = $cmp > 0;
                if ($desired_user_win === $user_wins) {
                    return ['host' => $host, 'sys' => $sys];
                }
            }
            return $fallback;
        }

        $both_lose_pct = defined('SEOTDA_SOLO_BOTH_LOSE_PCT')
            ? max(0, min(30, (int)SEOTDA_SOLO_BOTH_LOSE_PCT))
            : 0;
        $want_both_lose = $both_lose_pct > 0 && random_int(1, 100) <= $both_lose_pct;

        for ($try = 0; $try < 5000; $try++) {
            $deck = seotda_deck_new();
            $host = seotda_deal_triple($deck);
            $sys = seotda_deal_triple($deck);
            $sys_best = seotda_best_pair_from_triple($sys[0], $sys[1], $sys[2]);
            if (!$sys_best) {
                continue;
            }
            $win_prob = seotda_peek_user_win_prob(
                $host[0], $host[1], $host[2],
                $peek_slot,
                $sys_best['hand']
            );
            if ($want_both_lose) {
                if ($win_prob <= 0.0) {
                    return ['host' => $host, 'sys' => $sys];
                }
                continue;
            }
            if ($win_prob === 0.5) {
                return ['host' => $host, 'sys' => $sys];
            }
        }

        return $fallback;
    }
}

if (!function_exists('seotda_parse_pick_params')) {
    /** @return array{0:int,1:int}|null */
    function seotda_parse_pick_params($pick1_raw, $pick2_raw) {
        $p1 = (int)$pick1_raw;
        $p2 = (int)$pick2_raw;
        if ($p1 < 1 || $p1 > 3 || $p2 < 1 || $p2 > 3 || $p1 === $p2) {
            return null;
        }
        return [$p1, $p2];
    }
}

if (!function_exists('seotda_hand_eval')) {
    /**
     * @return array{kind:string,power:int,label:string,kkeut:int}
     */
    function seotda_hand_eval($c1, $c2) {
        $c1 = (int)$c1;
        $c2 = (int)$c2;
        if ($c1 === $c2) {
            return [
                'kind' => 'ttang',
                'power' => 1000 + $c1,
                'label' => $c1 . '땡',
                'kkeut' => 0,
            ];
        }
        $kkeut = ($c1 + $c2) % 10;
        if ($kkeut === 0) {
            return [
                'kind' => 'mangtong',
                'power' => 0,
                'label' => '망통',
                'kkeut' => 0,
            ];
        }
        return [
            'kind' => 'kkeut',
            'power' => 100 + $kkeut,
            'label' => $kkeut . '끗',
            'kkeut' => $kkeut,
        ];
    }
}

if (!function_exists('seotda_compare_hands')) {
    /** @return int 1=첫번째 승, -1=두번째 승, 0=무 */
    function seotda_compare_hands(array $h1, array $h2) {
        if ($h1['power'] > $h2['power']) {
            return 1;
        }
        if ($h1['power'] < $h2['power']) {
            return -1;
        }
        return 0;
    }
}
