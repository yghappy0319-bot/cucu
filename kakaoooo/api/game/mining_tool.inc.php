<?php
/**
 * 채굴 장비 (숟가락 → … → 황금 숟가락) — tb_member_mining
 */

require_once __DIR__ . '/mining_storage.inc.php';
if (is_file(__DIR__ . '/mining_durability.inc.php')) {
    require_once __DIR__ . '/mining_durability.inc.php';
}

if (!function_exists('mining_tool_base_defs')) {
    /** @return list<array{key:string,icon:string,label:string}> */
    function mining_tool_base_defs() {
        return [
            ['key' => 'spoon', 'icon' => '🥄', 'label' => '숟가락'],
            ['key' => 'fork', 'icon' => '🍴', 'label' => '포크'],
            ['key' => 'chopsticks', 'icon' => '🥢', 'label' => '젓가락'],
            ['key' => 'knife', 'icon' => '🔪', 'label' => '식칼'],
            ['key' => 'pot', 'icon' => '🫕', 'label' => '냄비'],
            ['key' => 'pan', 'icon' => '🍳', 'label' => '프라이팬'],
            ['key' => 'pickaxe', 'icon' => '⛏️', 'label' => '곡괭이'],
            ['key' => 'hammer', 'icon' => '🔨', 'label' => '망치'],
            ['key' => 'wrench', 'icon' => '🛠️', 'label' => '공구세트'],
            ['key' => 'pick_hammer', 'icon' => '⚒️', 'label' => '채굴망치'],
            ['key' => 'bulldozer', 'icon' => '🏗️', 'label' => '중장비'],
            ['key' => 'diamond', 'icon' => '💎', 'label' => '다이아 곡괭이'],
            ['key' => 'laser', 'icon' => '⚡', 'label' => '레이저 채굴기'],
            ['key' => 'ufo', 'icon' => '🛸', 'label' => 'UFO 흡입기'],
            ['key' => 'golden_spoon', 'icon' => '👑', 'label' => '황금 숟가락'],
        ];
    }
}

if (!function_exists('mining_tool_max_level')) {
    function mining_tool_max_level() {
        return max(0, count(mining_tool_base_defs()) - 1);
    }
}

if (!function_exists('mining_rate_fmt')) {
    function mining_rate_fmt($rate): string {
        $rate = (float)$rate;
        if ($rate >= 0.01) {
            return rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.');
        }
        if ($rate >= 0.0001) {
            return rtrim(rtrim(sprintf('%.8f', $rate), '0'), '.');
        }
        return rtrim(rtrim(sprintf('%.10f', $rate), '0'), '.');
    }
}

if (!function_exists('mining_yield_amount_fmt')) {
    function mining_yield_amount_fmt($amount): string {
        $amount = (float)$amount;
        if ($amount >= 100) {
            return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
        }
        if ($amount >= 1) {
            return rtrim(rtrim(number_format($amount, 4, '.', ''), '0'), '.');
        }
        if ($amount >= 0.01) {
            return rtrim(rtrim(number_format($amount, 6, '.', ''), '0'), '.');
        }
        return rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.');
    }
}

if (!function_exists('mining_tool_rate_str')) {
    function mining_tool_rate_str($level): string {
        return mining_rate_fmt(mining_tool_rate_for_level($level));
    }
}

if (!function_exists('mining_tool_catalog')) {
    /** @return array<int, array{level:int,key:string,icon:string,label:string,status:string,rate:string}> */
    function mining_tool_catalog() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $catalog = [];
        foreach (mining_tool_base_defs() as $level => $def) {
            $catalog[$level] = [
                'level' => $level,
                'key' => $def['key'],
                'icon' => $def['icon'],
                'label' => $def['label'],
                'status' => $def['label'] . '으로 채굴 중',
                'rate' => mining_tool_rate_str($level),
            ];
        }
        $cache = $catalog;
        return $catalog;
    }
}

if (!function_exists('mining_tool_def')) {
    function mining_tool_def($level) {
        $catalog = mining_tool_catalog();
        $level = max(0, min(mining_tool_max_level(), (int)$level));
        return $catalog[$level];
    }
}

if (!function_exists('mining_tool_level_from_row')) {
    function mining_tool_level_from_row(array $row) {
        return max(0, min(mining_tool_max_level(), (int)($row['mining_tool'] ?? 0)));
    }
}

if (!function_exists('mining_tool_level_for_nick')) {
    function mining_tool_level_for_nick($nick) {
        $row = mining_data_select_raw($nick);
        return mining_tool_level_from_row($row ?: []);
    }
}

if (!function_exists('mining_tool_roadmap_state')) {
    function mining_tool_roadmap_state($item_level, $current_level) {
        $item_level = (int)$item_level;
        $current_level = max(0, min(mining_tool_max_level(), (int)$current_level));
        if ($item_level < $current_level) {
            return 'done';
        }
        if ($item_level === $current_level) {
            return 'current';
        }
        return 'locked';
    }
}

if (!function_exists('mining_tool_roadmap_items')) {
    function mining_tool_roadmap_items($current_level) {
        $current_level = max(0, min(mining_tool_max_level(), (int)$current_level));
        $items = [];
        foreach (mining_tool_catalog() as $level => $def) {
            $items[] = [
                'level' => $level,
                'icon' => $def['icon'],
                'label' => $def['label'],
                'state' => mining_tool_roadmap_state($level, $current_level),
            ];
        }
        return $items;
    }
}

if (!function_exists('mining_tool_upgrade_info')) {
    /**
     * @param bool $use_eunchong_cost 하위호환(은총 강화비 할인 폐기 · 항상 동일)
     * @param string $nick 있으면 오늘 생타 강화비 할인 적용
     */
    function mining_tool_upgrade_info($level, $use_eunchong_cost = false, $nick = '') {
        $level = max(0, (int)$level);
        $max = mining_tool_max_level();
        if ($level >= $max) {
            return null;
        }
        $next = mining_tool_def($level + 1);
        $cur = mining_tool_def($level);
        $cost_base_raw = mining_upgrade_cost_for_level($level, false);
        $cost_eunchong_raw = mining_upgrade_cost_for_level($level, true);

        $nick = trim((string)$nick);
        $chat_disc = 0.0;
        if ($nick !== '' && function_exists('mining_chat_upgrade_discount_for_nick')) {
            $chat_disc = (float)mining_chat_upgrade_discount_for_nick($nick);
        }
        $cost_base = $cost_base_raw;
        $cost_eunchong = $cost_eunchong_raw;
        if ($chat_disc > 0 && function_exists('mining_upgrade_cost_apply_chat_discount')) {
            $cost_base = mining_upgrade_cost_apply_chat_discount($cost_base_raw, $chat_disc);
            $cost_eunchong = mining_upgrade_cost_apply_chat_discount($cost_eunchong_raw, $chat_disc);
        }
        // 본냥 전체미션 강화비 할인 (채팅 할인 이후)
        if (!function_exists('gv_본냥미션_비용할인')) {
            $missionInc = dirname(__DIR__) . '/game/vault_bon_mission.inc.php';
            if (!is_file($missionInc)) {
                $missionInc = __DIR__ . '/vault_bon_mission.inc.php';
            }
            if (is_file($missionInc)) {
                include_once $missionInc;
            }
        }
        $bon_disc = 0;
        if (function_exists('gv_본냥미션_강화할인율')) {
            $bon_disc = (int)gv_본냥미션_강화할인율();
        }
        if ($bon_disc > 0 && function_exists('gv_본냥미션_비용할인')) {
            $cost_base = gv_본냥미션_비용할인($cost_base);
            $cost_eunchong = gv_본냥미션_비용할인($cost_eunchong);
        }
        $cost = $use_eunchong_cost ? $cost_eunchong : $cost_base;
        // JSON number 정밀도 깨짐 방지 — 비용은 항상 문자열
        $cost_base_raw = (string)$cost_base_raw;
        $cost_base = (string)$cost_base;
        $cost_eunchong_raw = (string)$cost_eunchong_raw;
        $cost_eunchong = (string)$cost_eunchong;
        $cost = (string)$cost;
        $chat_pct = (int)round($chat_disc * 100);
        return [
            'from_level' => $level,
            'to_level' => $level + 1,
            'from_label' => $cur['label'],
            'to_label' => $next['label'],
            'to_icon' => $next['icon'],
            'cost_base_raw' => $cost_base_raw,
            'cost_base_raw_fmt' => mining_upgrade_cost_fmt($cost_base_raw),
            'cost_base' => $cost_base,
            'cost_base_fmt' => mining_upgrade_cost_fmt($cost_base),
            'cost_eunchong_raw' => $cost_eunchong_raw,
            'cost_eunchong_raw_fmt' => mining_upgrade_cost_fmt($cost_eunchong_raw),
            'cost_eunchong' => $cost_eunchong,
            'cost_eunchong_fmt' => mining_upgrade_cost_fmt($cost_eunchong),
            'cost' => $cost,
            'cost_fmt' => mining_upgrade_cost_fmt($cost),
            'chat_upgrade_discount' => $chat_disc,
            'chat_upgrade_discount_pct' => $chat_pct,
            'bon_mission_discount_pct' => $bon_disc,
            'success_pct' => mining_upgrade_success_pct_str(0, $level),
            'success_pct_eunchong' => mining_upgrade_success_pct_str(1, $level),
            'success_pct_mega' => mining_upgrade_success_pct_str(2, $level),
            'success_pct_terra' => mining_upgrade_success_pct_str(3, $level),
            'success_hint' => mining_upgrade_success_hint_str(0, $level),
            'success_hint_eunchong' => mining_upgrade_success_hint_str(1, $level),
            'success_hint_mega' => mining_upgrade_success_hint_str(2, $level),
            'success_hint_terra' => mining_upgrade_success_hint_str(3, $level),
        ];
    }
}

if (!function_exists('mining_tool_daily_yield_fmt')) {
    function mining_tool_daily_yield_fmt($level): string {
        $level = (int)$level;
        // 전 장비: 본방냥 실시간 연동 획득량 숫자만 표시
        if (function_exists('mining_tool_daily_yield_amount_fmt')) {
            return mining_tool_daily_yield_amount_fmt($level);
        }
        $daily = mining_tool_daily_yield($level);
        if ($daily >= 100) {
            return rtrim(rtrim(number_format($daily, 2, '.', ''), '0'), '.');
        }
        if ($daily >= 1) {
            return rtrim(rtrim(number_format($daily, 4, '.', ''), '0'), '.');
        }
        return rtrim(rtrim(number_format($daily, 6, '.', ''), '0'), '.');
    }
}

if (!function_exists('mining_tool_hourly_yield_fmt')) {
    function mining_tool_hourly_yield_fmt($level): string {
        $hourly = mining_tool_hourly_yield($level);
        if (function_exists('냥_숫자콤마') && $hourly >= 1) {
            // 시간당도 본방냥 연동값 — 정수에 가깝면 콤마
            $s = mining_tool_daily_yield_str((int)$level);
            if (function_exists('냥_나눗셈내림')) {
                $h = 냥_나눗셈내림($s, 24);
                $h = is_string($h) ? $h : (string)(int)$h;
                if ($h !== '0') {
                    return 냥_숫자콤마($h);
                }
            }
        }
        if ($hourly >= 100) {
            return rtrim(rtrim(number_format($hourly, 2, '.', ''), '0'), '.');
        }
        if ($hourly >= 1) {
            return rtrim(rtrim(number_format($hourly, 4, '.', ''), '0'), '.');
        }
        if ($hourly >= 0.01) {
            return rtrim(rtrim(number_format($hourly, 6, '.', ''), '0'), '.');
        }
        return rtrim(rtrim(number_format($hourly, 8, '.', ''), '0'), '.');
    }
}

if (!function_exists('mining_tool_rate_payload')) {
    function mining_tool_rate_payload($level, $yield_mult = 1.0): array {
        $level = max(0, min(mining_tool_max_level(), (int)$level));
        $mult = max(0.0, (float)$yield_mult);
        if ($mult <= 0) {
            $mult = 0.0;
        }
        $rate = (float)mining_tool_rate_for_level($level) * $mult;
        $hourly = (float)mining_tool_hourly_yield($level) * $mult;
        $daily = (float)mining_tool_daily_yield($level) * $mult;
        $daily_fmt = function_exists('mining_tool_daily_yield_amount_fmt')
            ? mining_tool_daily_yield_amount_fmt($level, $mult)
            : mining_yield_amount_fmt($daily);
        $hourly_fmt = mining_yield_amount_fmt($hourly);
        $days = function_exists('mining_tool_days_to_base_pct')
            ? (float)mining_tool_days_to_base_pct($level)
            : 0.0;
        if ($mult > 0 && abs($mult - 1.0) > 1e-9) {
            $days = $days / $mult;
        }
        $time_fmt = '';
        if (function_exists('mining_tool_time_to_base_pct_label') && $days > 0) {
            // 배율 반영된 일수로 라벨 재계산
            if ($days >= 360) {
                $y = round($days / 365, 1);
                $s = rtrim(rtrim(number_format($y, 1, '.', ''), '0'), '.');
                $time_fmt = ($s === '1' ? '1년' : ($s . '년'));
            } elseif ($days >= 28) {
                $m = round($days / 30.4, 1);
                $s = rtrim(rtrim(number_format($m, 1, '.', ''), '0'), '.');
                $time_fmt = ($s === '1' ? '1개월' : ($s . '개월'));
            } elseif ($days >= 1) {
                $d = round($days, 1);
                $s = rtrim(rtrim(number_format($d, 1, '.', ''), '0'), '.');
                $time_fmt = ($s === '1' ? '1일' : ($s . '일'));
            } else {
                $h = max(1, (int)round($days * 24));
                $time_fmt = '약 ' . $h . '시간';
            }
        } elseif (function_exists('mining_tool_time_to_base_pct_label')) {
            $time_fmt = mining_tool_time_to_base_pct_label($level);
        }
        return [
            'rate' => $rate,
            'rate_fmt' => mining_rate_fmt($rate),
            'hourly_yield' => $hourly,
            'hourly_yield_fmt' => $hourly_fmt,
            'daily_yield' => $daily,
            'daily_yield_fmt' => $daily_fmt,
            'time_to_1pct' => $days,
            'time_to_1pct_fmt' => $time_fmt,
            'yield_mult' => $mult,
            'eunchong_yield' => $mult > 1.0,
        ];
    }
}

if (!function_exists('mining_tool_roadmap_yield_table')) {
    /** @return list<array{level:int,icon:string,label:string,rate:float,rate_fmt:string,daily_yield:float,daily_yield_fmt:string,time_to_1pct_fmt:string,upgrade_cost:int|string|null,upgrade_cost_fmt:string,is_current:bool,chat_tiers:list}> */
    function mining_tool_roadmap_yield_table($current_level = null) {
        $current_level = $current_level === null ? null : max(0, min(mining_tool_max_level(), (int)$current_level));
        $tiers = function_exists('mining_chat_yield_tiers') ? mining_chat_yield_tiers() : [];
        $rows = [];
        foreach (mining_tool_catalog() as $level => $def) {
            $rate = mining_tool_rate_payload($level);
            $upgrade = mining_tool_upgrade_info($level);
            $chat_tiers = [];
            foreach ($tiers as $tier) {
                $mult = (float)($tier['mult'] ?? 1.0);
                $chat_tiers[] = [
                    'min_raw' => (int)($tier['min_raw'] ?? 0),
                    'label' => (string)($tier['label'] ?? ''),
                    'mult' => $mult,
                    'mult_fmt' => function_exists('mining_chat_mult_label')
                        ? mining_chat_mult_label($mult)
                        : ('×' . $mult),
                    'daily_fmt' => function_exists('mining_chat_tier_daily_fmt')
                        ? mining_chat_tier_daily_fmt((int)$level, $mult)
                        : (string)$rate['daily_yield_fmt'],
                ];
            }
            $cost_disc_tiers = [];
            $cost_raw = $upgrade ? ($upgrade['cost_base_raw'] ?? $upgrade['cost_base'] ?? null) : null;
            if ($cost_raw !== null && function_exists('mining_chat_upgrade_discount_tiers')
                && function_exists('mining_upgrade_cost_apply_chat_discount')) {
                foreach (mining_chat_upgrade_discount_tiers() as $dt) {
                    $d = (float)($dt['discount'] ?? 0);
                    $c = mining_upgrade_cost_apply_chat_discount($cost_raw, $d);
                    $cost_disc_tiers[] = [
                        'min_raw' => (int)($dt['min_raw'] ?? 0),
                        'label' => (string)($dt['label'] ?? ''),
                        'discount_pct' => (int)($dt['discount_pct'] ?? 0),
                        'cost_fmt' => mining_upgrade_cost_fmt($c),
                    ];
                }
            }
            $rows[] = [
                'level' => (int)$level,
                'icon' => $def['icon'],
                'label' => $def['label'],
                'rate' => (float)$rate['rate'],
                'rate_fmt' => $rate['rate_fmt'],
                'daily_yield' => (float)$rate['daily_yield'],
                'daily_yield_fmt' => (string)$rate['daily_yield_fmt'],
                'time_to_1pct_fmt' => (string)($rate['time_to_1pct_fmt'] ?? ''),
                'upgrade_cost' => $upgrade ? $upgrade['cost'] : null,
                'upgrade_cost_fmt' => $upgrade ? (string)$upgrade['cost_fmt'] : '—',
                'upgrade_success_pct' => $upgrade ? (string)$upgrade['success_pct'] : '—',
                'is_current' => $current_level !== null && (int)$current_level === (int)$level,
                'chat_tiers' => $chat_tiers,
                'cost_discount_tiers' => $cost_disc_tiers,
            ];
        }
        return $rows;
    }
}

if (!function_exists('mining_tool_payload')) {
    function mining_tool_payload($level) {
        $level = max(0, min(mining_tool_max_level(), (int)$level));
        $def = mining_tool_def($level);
        $upgrade = mining_tool_upgrade_info($level);
        $rate = mining_tool_rate_payload($level);
        return array_merge([
            'level' => $level,
            'key' => $def['key'],
            'icon' => $def['icon'],
            'label' => $def['label'],
            'status' => $def['status'],
            'max_level' => mining_tool_max_level(),
            'can_upgrade' => $upgrade !== null,
            'upgrade' => $upgrade,
        ], $rate);
    }
}

if (!function_exists('mining_tool_payload_for_nick')) {
    function mining_tool_payload_for_nick($nick, $level = null, $upgrade_attempts = null) {
        if ($level === null) {
            $level = mining_tool_level_for_nick($nick);
        }
        $level = max(0, min(mining_tool_max_level(), (int)$level));
        $def = mining_tool_def($level);
        $upgrade = mining_tool_upgrade_info($level, false, $nick);
        $yield_mult = 1.0;
        $weapon_mult = 1.0;
        if (function_exists('mining_sync_yield_mult_parts_for_nick')) {
            $parts = mining_sync_yield_mult_parts_for_nick($nick);
            $yield_mult = (float)$parts['total'];
            $weapon_mult = (float)$parts['weapon'];
            $base_mult = (float)$parts['without_weapon'];
        } elseif (function_exists('mining_sync_yield_mult_for_nick')) {
            $yield_mult = (float)mining_sync_yield_mult_for_nick($nick);
            $base_mult = $yield_mult;
        } elseif (function_exists('mining_chat_yield_mult_for_nick')) {
            $yield_mult = (float)mining_chat_yield_mult_for_nick($nick);
            if (function_exists('mining_eunchong_active_for_nick') && mining_eunchong_active_for_nick($nick)) {
                $yield_mult *= function_exists('mining_eunchong_yield_mult')
                    ? (float)mining_eunchong_yield_mult(true)
                    : 1.0;
            }
            $base_mult = $yield_mult;
        } else {
            $base_mult = 1.0;
        }
        $payload = array_merge([
            'level' => $level,
            'key' => $def['key'],
            'icon' => $def['icon'],
            'label' => $def['label'],
            'status' => $def['status'],
            'max_level' => mining_tool_max_level(),
            'can_upgrade' => $upgrade !== null,
            'upgrade' => $upgrade,
        ], mining_tool_rate_payload($level, $yield_mult));
        $base_rate = mining_tool_rate_payload($level, $base_mult);
        $payload['rate_base'] = (float)$base_rate['rate'];
        $payload['rate_base_fmt'] = (string)$base_rate['rate_fmt'];
        $payload['weapon_yield_mult'] = $weapon_mult;
        $payload['weapon_yield_mult_fmt'] = function_exists('mining_weapon_yield_mult_fmt')
            ? mining_weapon_yield_mult_fmt($weapon_mult)
            : rtrim(rtrim(number_format($weapon_mult, 4, '.', ''), '0'), '.');
        if (($payload['weapon_yield_mult_fmt'] ?? '') === '') {
            $payload['weapon_yield_mult_fmt'] = '1';
        }
        if (function_exists('mining_chat_yield_payload')) {
            $payload = array_merge($payload, mining_chat_yield_payload($nick));
        }
        if (function_exists('mining_chat_upgrade_discount_payload')) {
            $payload = array_merge($payload, mining_chat_upgrade_discount_payload($nick));
        }
        if ($upgrade_attempts !== null) {
            $unlock = mining_yield_unlock_payload_from_attempts((int)$upgrade_attempts);
        } else {
            $unlock = mining_yield_unlock_payload($nick);
        }
        if (empty($unlock['yield_unlocked'])) {
            $payload['rate'] = 0.0;
            $payload['rate_fmt'] = '0';
            $payload['rate_base'] = 0.0;
            $payload['rate_base_fmt'] = '0';
            $payload['hourly_yield'] = 0.0;
            $payload['hourly_yield_fmt'] = '0';
            $payload['daily_yield'] = 0.0;
            $payload['daily_yield_fmt'] = '0';
        }
        return array_merge($payload, $unlock);
    }
}

if (!function_exists('mining_upgrade_point_log')) {
    /** 채굴 강화 경량 로그 — INSERT 1회 (mypoint 동기화 SELECT 생략) */
    function mining_upgrade_point_log($status, $nick, $receiver, $cost, $mypoint = null) {
        $status = (string)$status;
        // 연속강화 배치는 건수가 폭증해 point_log 비대화 → 기록 생략
        if ($status === '채굴강화배치') {
            return true;
        }
        $nick_esc = addslashes(trim((string)$nick));
        $status_esc = addslashes($status);
        $receiver_esc = addslashes((string)$receiver);
        $cost_sql = function_exists('지급로그_BIGINT안전')
            ? 지급로그_BIGINT안전($cost)
            : mining_upgrade_point_str($cost);
        if ($nick_esc === '' || $cost_sql === '' || (function_exists('bccomp') ? bccomp($cost_sql, '0', 0) < 0 : (int)$cost_sql < 0)) {
            return false;
        }
        $mypoint_sql = '';
        if ($mypoint !== null) {
            $mp = function_exists('지급로그_BIGINT안전')
                ? 지급로그_BIGINT안전($mypoint)
                : mining_upgrade_point_str($mypoint);
            $mypoint_sql = ', mypoint = ' . $mp;
        }
        return (bool)@db_query("
            INSERT INTO tb_point_log
            SET status = '{$status_esc}',
                nick = '{$nick_esc}',
                receiver = '{$receiver_esc}',
                tax = 0,
                point = {$cost_sql},
                regdate = NOW()
                {$mypoint_sql}
        ");
    }
}

if (!function_exists('mining_upgrade_point_str')) {
    function mining_upgrade_point_str($v): string {
        if (function_exists('mining_point_str')) {
            return mining_point_str($v);
        }
        if (function_exists('냥_정수문자열')) {
            return 냥_정수문자열($v);
        }
        $s = preg_replace('/[^\d]/', '', (string)$v);
        return ltrim((string)$s, '0') ?: '0';
    }
}

if (!function_exists('mining_upgrade_point_enough')) {
    function mining_upgrade_point_enough($point, $cost): bool {
        $p = mining_upgrade_point_str($point);
        $c = mining_upgrade_point_str($cost);
        if (function_exists('bccomp')) {
            return bccomp($p, $c, 0) >= 0;
        }
        if (strlen($p) !== strlen($c)) {
            return strlen($p) > strlen($c);
        }
        return $p >= $c;
    }
}

if (!function_exists('mining_upgrade_point_sub')) {
    function mining_upgrade_point_sub($point, $cost): string {
        $p = mining_upgrade_point_str($point);
        $c = mining_upgrade_point_str($cost);
        if (function_exists('bcsub')) {
            $r = bcsub($p, $c, 0);
            return ($r === '' || $r[0] === '-') ? '0' : $r;
        }
        // cost는 강화비(정수 범위)만 가정
        $pi = (float)$p;
        $ci = (float)$c;
        if ($pi > (float)PHP_INT_MAX || $ci > (float)PHP_INT_MAX) {
            return $p;
        }
        $r = (int)$p - (int)$c;
        return (string)max(0, $r);
    }
}

if (!function_exists('mining_upgrade_point_fmt')) {
    function mining_upgrade_point_fmt($point) {
        $p = mining_upgrade_point_str($point);
        if (function_exists('mining_fmt_game')) {
            return mining_fmt_game($p);
        }
        if (function_exists('게임냥_안전표시')) {
            return 게임냥_안전표시($p, '');
        }
        if (function_exists('랭킹_게임냥표시')) {
            return 랭킹_게임냥표시($p, '');
        }
        return function_exists('냥_숫자콤마') ? 냥_숫자콤마($p) : $p;
    }
}

if (!function_exists('mining_upgrade_point_refresh')) {
    function mining_upgrade_point_refresh(string $nick): string {
        if (function_exists('mining_member_point_refresh')) {
            return mining_member_point_refresh($nick);
        }
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return '0';
        }
        $row = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        return mining_upgrade_point_str($row['point'] ?? '0');
    }
}

if (!function_exists('mining_upgrade_execute')) {
    /**
     * @param array{skip_sync?:bool} $opts 호출 전 sync 완료 시 skip_sync=true
     * @return array<string,mixed>
     */
    function mining_upgrade_execute($nick, array $opts = []) {
        mining_data_ensure_table();
        wallet_odd_even_includes();

        $nick = trim((string)$nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }

        $member = db_select("SELECT CAST(point AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, IFNULL(은총개수, 0) AS eunchong_cnt, 은총 FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($member)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.'];
        }

        $은총효과 = function_exists('mining_eunchong_effect_for_nick')
            ? mining_eunchong_effect_for_nick($nick)
            : [
                'active' => mining_eunchong_active($member['은총'] ?? ''),
                'zeros' => mining_eunchong_active($member['은총'] ?? '') ? 1 : 0,
                // 예전: cost_discount => active — 플래그로 숨김
                'cost_discount' => false,
                'label' => '은총',
                'tier' => mining_eunchong_active($member['은총'] ?? '') ? 1 : 0,
            ];
        $은총활성 = !empty($은총효과['active']);
        $은총zeros = (int)($은총효과['zeros'] ?? 0);
        $은총비용할인 = !empty($은총효과['cost_discount']);
        $은총라벨 = (string)($은총효과['label'] ?? '은총');

        $row = mining_data_select_raw($nick);
        $level = mining_tool_level_from_row($row ?: []);

        $want_booster = !empty($opts['booster']);
        $boost = function_exists('mega_booster_apply_effect')
            ? mega_booster_apply_effect($은총효과, $want_booster, $level)
            : ['ok' => !$want_booster, 'zeros' => $은총zeros, 'extra_cost' => '0', 'applied' => false, 'error' => '부스터를 사용할 수 없어요.'];
        if (empty($boost['ok'])) {
            return ['ok' => false, 'data' => (string)($boost['error'] ?? '부스터를 사용할 수 없어요.')];
        }
        $은총zeros = (int)($boost['zeros'] ?? $은총zeros);
        $booster_applied = !empty($boost['applied']);
        $booster_extra = (string)($boost['extra_cost'] ?? '0');
        if ($booster_applied) {
            $은총라벨 .= '+부스터';
        }

        $upgrade = mining_tool_upgrade_info($level, $은총비용할인, $nick);
        if ($upgrade === null) {
            return ['ok' => false, 'data' => '이미 최고 단계 장비예요.'];
        }

        $cost = mining_upgrade_point_str($upgrade['cost']);
        if ($booster_applied && $booster_extra !== '0' && function_exists('mega_booster_add_cost')) {
            $cost = mega_booster_add_cost($cost, $booster_extra);
        }
        $point = mining_upgrade_point_str($member['point'] ?? 0);
        if (!mining_upgrade_point_enough($point, $cost)) {
            return [
                'ok' => false,
                'data' => '게임냥이 부족해요. (필요 ' . mining_upgrade_point_fmt($cost) . ' · 보유 ' . mining_upgrade_point_fmt($point) . ')',
            ];
        }

        if (empty($opts['skip_sync']) && function_exists('mining_sync_commit_elapsed')) {
            mining_sync_commit_elapsed($nick);
        }
        $roll = mining_upgrade_success_roll_for_level($level, $은총zeros);
        $분모 = (int)$roll['denom'];
        $분자 = (int)$roll['num'];
        if ($분자 < 1) {
            $분자 = 1;
        }

        $주사위 = random_int(1, $분모);
        $성공 = ($주사위 <= $분자);
        $next_level = (int)$upgrade['to_level'];
        $tbl = MINING_TABLE;
        $pct_str = mining_upgrade_success_pct_str($은총zeros, $level);

        db_query("
            UPDATE tb_member
            SET point = point - {$cost}
            WHERE name = '{$nick_esc}'
              AND point >= {$cost}
            LIMIT 1
        ");
        global $conn;
        $paid = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$paid) {
            return ['ok' => false, 'data' => '강화 처리에 실패했어요. 게임냥·장비 상태를 확인해주세요.'];
        }
        if (!function_exists('강화비용_소멸분배')) {
            $cfg = dirname(__DIR__) . '/config.php';
            if (is_file($cfg)) {
                require_once $cfg;
            }
        }
        if (function_exists('강화비용_소멸분배')) {
            강화비용_소멸분배($cost, $nick, '채굴강화소멸');
        }

        $point = mining_upgrade_point_refresh($nick);
        $attempts_before = mining_upgrade_attempts_for_nick($nick);
        mining_upgrade_attempts_add($nick, 1);
        $attempts_after = $attempts_before + 1;

        if ($성공) {
            // 레벨·내구 변경 직전에만 채굴 정산 (실패 강화는 채굴에 영향 없음)
            if (function_exists('mining_sync_commit_elapsed')) {
                mining_sync_commit_elapsed($nick);
            }
            $max_sql = function_exists('mining_durability_sql')
                ? mining_durability_sql(
                    function_exists('mining_durability_max') ? mining_durability_max($next_level) : 100,
                    $next_level
                )
                : '100';
            if (function_exists('mining_durability_ensure_column')) {
                mining_durability_ensure_column();
            }
            db_query("
                UPDATE `{$tbl}`
                SET mining_tool = {$next_level},
                    mining_durability = {$max_sql}
                WHERE nick = '{$nick_esc}'
                  AND mining_tool = {$level}
                LIMIT 1
            ");
            $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if (!$applied) {
                return ['ok' => false, 'data' => '강화 처리에 실패했어요. 게임냥·장비 상태를 확인해주세요.'];
            }
            $level = $next_level;
        }

        $tool = mining_tool_payload($level);

        if ($성공) {
            $log_label = $tool['label'] . ($은총활성 ? '(' . $은총라벨 . ')' : '');
            mining_upgrade_point_log('채굴강화성공', $nick, $log_label, $cost, $point);
        }

        if ($성공) {
            $msg = $upgrade['from_label'] . ' → ' . $tool['icon'] . $tool['label'] . ' 강화 성공!';
            if ($은총활성) {
                $msg .= ' (' . $은총라벨 . ' 버프 · ' . $pct_str . ')';
            }
        } else {
            $msg = '강화 실패… ' . mining_upgrade_point_fmt($cost) . '이 소진됐어요.';
            if ($은총활성) {
                $msg .= ' (' . $은총라벨 . ' 버프 · ' . $pct_str . ')';
            } else {
                $msg .= ' (' . $pct_str . ' 확률)';
            }
        }

        $unlock = mining_yield_unlock_payload_from_attempts($attempts_after);
        if (mining_yield_just_unlocked($attempts_before, $attempts_after)) {
            $msg .= "\n🎉 채굴냥 적립 해제!";
        }

        $자숙안내 = '';
        if (function_exists('자숙_채굴강화위반_적용')) {
            $자숙위반 = 자숙_채굴강화위반_적용($nick);
            if (!empty($자숙위반['applied']) && !empty($자숙위반['notice'])) {
                $자숙안내 = (string)$자숙위반['notice'];
                $msg = $자숙안내 . $msg;
            }
        }

        return array_merge([
            'ok' => true,
            'success' => $성공,
            'data' => $msg,
            'point' => $point,
            'point_fmt' => mining_upgrade_point_fmt($point),
            'tool' => mining_tool_payload_for_nick($nick, $level),
            'jasuk_extended' => ($자숙안내 !== ''),
        ], mining_eunchong_payload($nick), $unlock);
    }
}

if (!function_exists('mining_upgrade_execute_batch')) {
    /**
     * 채굴 장비 강화 연속 시도 (100·1000회 · sync 1회 · 배치 요약 로그 1건)
     * 레벨별 비용·확률은 루프 전 1회 캐시 (회당 생타/시세 조회 제거)
     * 호출 전 mining_sync_commit_elapsed() 1회 권장.
     *
     * @return array<string,mixed>
     */
    function mining_upgrade_execute_batch($nick, $max_times = 10, $booster = false) {
        $max_times = (int)$max_times;
        $allowed_times = function_exists('mining_upgrade_batch_times_allowed')
            ? mining_upgrade_batch_times_allowed()
            : [1, 100, 500, 1000, 3000, 5000];
        if (!in_array($max_times, $allowed_times, true)) {
            $max_times = 1;
        }
        $promo_newpoint_cost = function_exists('mining_upgrade_batch_newpoint_cost')
            ? (int)mining_upgrade_batch_newpoint_cost($max_times)
            : ($max_times === 1000 ? 10 : ($max_times === 3000 ? 30 : ($max_times === 5000 ? 50 : 0)));
        if ($max_times >= 1000) {
            @set_time_limit(max(180, (int)ceil($max_times * 0.12)));
        } elseif ($max_times >= 100) {
            @set_time_limit(180);
        }
        $is_promo = ($promo_newpoint_cost > 0);
        mining_data_ensure_table();
        wallet_odd_even_includes();

        $nick = trim((string)$nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }

        $member = db_select("SELECT CAST(point AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, IFNULL(은총개수, 0) AS eunchong_cnt, 은총 FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($member)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.'];
        }

        $은총효과 = function_exists('mining_eunchong_effect_for_nick')
            ? mining_eunchong_effect_for_nick($nick)
            : [
                'active' => mining_eunchong_active($member['은총'] ?? ''),
                'zeros' => mining_eunchong_active($member['은총'] ?? '') ? 1 : 0,
                'cost_discount' => false,
                'label' => '은총',
                'tier' => mining_eunchong_active($member['은총'] ?? '') ? 1 : 0,
            ];
        $은총활성 = !empty($은총효과['active']);
        $은총zeros = (int)($은총효과['zeros'] ?? 0);
        $은총비용할인 = !empty($은총효과['cost_discount']);
        $은총라벨 = (string)($은총효과['label'] ?? '은총');

        $row = mining_data_select_raw($nick);
        $level = mining_tool_level_from_row($row ?: []);

        $want_booster = !empty($booster);
        $boost = function_exists('mega_booster_apply_effect')
            ? mega_booster_apply_effect($은총효과, $want_booster, $level)
            : ['ok' => !$want_booster, 'zeros' => $은총zeros, 'extra_cost' => '0', 'applied' => false, 'error' => '부스터를 사용할 수 없어요.'];
        if (empty($boost['ok'])) {
            return ['ok' => false, 'data' => (string)($boost['error'] ?? '부스터를 사용할 수 없어요.')];
        }
        $은총zeros = (int)($boost['zeros'] ?? $은총zeros);
        $booster_applied = !empty($boost['applied']);
        if ($booster_applied) {
            $은총라벨 .= '+부스터';
        }

        $start_level = $level;
        $start_point = mining_upgrade_point_str($member['point'] ?? 0);
        $start_newpoint = (int)floor((float)($member['newpoint'] ?? 0));
        $point = $start_point;
        $newpoint = $start_newpoint;
        $attempts_before = mining_upgrade_attempts_for_nick($nick);
        $tbl = MINING_TABLE;
        global $conn;

        if ($is_promo && $start_newpoint < $promo_newpoint_cost) {
            $np_fmt = function_exists('wallet_fmt_new') ? wallet_fmt_new($start_newpoint) : number_format($start_newpoint);
            $cost_fmt = function_exists('wallet_fmt_new') ? wallet_fmt_new($promo_newpoint_cost) : number_format($promo_newpoint_cost);
            return [
                'ok' => false,
                'data' => '본방냥이 부족해요. (' . $max_times . '회 강화 ' . $cost_fmt . '냥 · 보유 ' . $np_fmt . '냥)',
            ];
        }

        $tries = 0;
        $successes = 0;
        $fails = 0;
        $total_spent = '0';
        $max_lv = function_exists('mining_tool_max_level') ? (int)mining_tool_max_level() : 14;

        // 회당 DB/포맷 호출 제거 — 레벨별 비용·확률만 1회 미리 계산
        $chat_disc = 0.0;
        if ($nick !== '' && function_exists('mining_chat_upgrade_discount_for_nick')) {
            $chat_disc = (float)mining_chat_upgrade_discount_for_nick($nick);
        }
        if (!function_exists('gv_본냥미션_비용할인')) {
            $missionInc = __DIR__ . '/vault_bon_mission.inc.php';
            if (is_file($missionInc)) {
                include_once $missionInc;
            }
        }
        $cost_by_level = [];
        $roll_by_level = [];
        for ($lv = 0; $lv < $max_lv; $lv++) {
            $cost_raw = function_exists('mining_upgrade_cost_for_level')
                ? mining_upgrade_cost_for_level($lv, $은총비용할인)
                : 1;
            if ($chat_disc > 0 && function_exists('mining_upgrade_cost_apply_chat_discount')) {
                $cost_raw = mining_upgrade_cost_apply_chat_discount($cost_raw, $chat_disc);
            }
            if (function_exists('gv_본냥미션_비용할인')) {
                $cost_raw = gv_본냥미션_비용할인($cost_raw);
            }
            $cost_str = mining_upgrade_point_str($cost_raw);
            if ($booster_applied && function_exists('mega_booster_cost_for_level') && function_exists('mega_booster_add_cost')) {
                $cost_str = mega_booster_add_cost($cost_str, mega_booster_cost_for_level($lv));
            }
            $cost_by_level[$lv] = $cost_str;
            $roll_by_level[$lv] = function_exists('mining_upgrade_success_roll_for_level')
                ? mining_upgrade_success_roll_for_level($lv, $은총zeros)
                : ['num' => 1, 'denom' => 100000];
        }

        $use_bc = function_exists('bcadd') && function_exists('bccomp');
        for ($i = 0; $i < $max_times; $i++) {
            if ($level >= $max_lv || !isset($cost_by_level[$level])) {
                if ($is_promo) {
                    $tries++;
                    $fails++;
                    continue;
                }
                if ($tries === 0) {
                    return ['ok' => false, 'data' => '이미 최고 단계 장비예요.'];
                }
                break;
            }

            $cost = $cost_by_level[$level];
            if (!mining_upgrade_point_enough($point, $cost)) {
                if ($tries === 0) {
                    return [
                        'ok' => false,
                        'data' => '게임냥이 부족해요. (필요 ' . mining_upgrade_point_fmt($cost) . ' · 보유 ' . mining_upgrade_point_fmt($point) . ')',
                    ];
                }
                break;
            }

            $roll = $roll_by_level[$level];
            $분모 = (int)($roll['denom'] ?? 1);
            $분자 = (int)($roll['num'] ?? 1);
            if ($분자 < 1) {
                $분자 = 1;
            }
            if ($분모 < 1) {
                $분모 = 1;
            }

            $성공 = random_int(1, $분모) <= $분자;

            $point = mining_upgrade_point_sub($point, $cost);
            $total_spent = $use_bc
                ? bcadd($total_spent, $cost, 0)
                : (string)((int)$total_spent + (int)$cost);
            $tries++;

            if ($성공) {
                $level++;
                $successes++;
            } else {
                $fails++;
            }

            if (!$is_promo && $level >= $max_lv) {
                break;
            }
        }

        if ($tries === 0) {
            return ['ok' => false, 'data' => '강화를 시도할 수 없습니다.'];
        }

        if ($is_promo) {
            $newpoint = $start_newpoint - $promo_newpoint_cost;
        }

        $spent_positive = function_exists('bccomp')
            ? (bccomp($total_spent, '0', 0) > 0)
            : ((int)$total_spent > 0);

        if ($is_promo && ($spent_positive || $promo_newpoint_cost > 0)) {
            $set_parts = [];
            $where_parts = ["name = '{$nick_esc}'"];
            if ($spent_positive) {
                $set_parts[] = "point = point - {$total_spent}";
                $where_parts[] = "point >= {$total_spent}";
            }
            if ($promo_newpoint_cost > 0) {
                $set_parts[] = "newpoint = newpoint - {$promo_newpoint_cost}";
                $where_parts[] = "newpoint >= {$promo_newpoint_cost}";
            }
            db_query("
                UPDATE tb_member
                SET " . implode(', ', $set_parts) . "
                WHERE " . implode(' AND ', $where_parts) . "
                LIMIT 1
            ");
            $paid = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if (!$paid) {
                return ['ok' => false, 'data' => '강화 처리에 실패했어요. 게임냥·본방냥·장비 상태를 확인해주세요.'];
            }
            if ($spent_positive) {
                if (!function_exists('강화비용_소멸분배')) {
                    $cfg = dirname(__DIR__) . '/config.php';
                    if (is_file($cfg)) {
                        require_once $cfg;
                    }
                }
                if (function_exists('강화비용_소멸분배')) {
                    강화비용_소멸분배($total_spent, $nick, '채굴강화소멸');
                }
            }
        } elseif ($spent_positive) {
            db_query("
                UPDATE tb_member
                SET point = point - {$total_spent}
                WHERE name = '{$nick_esc}'
                  AND point >= {$total_spent}
                LIMIT 1
            ");
            $paid = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if (!$paid) {
                return ['ok' => false, 'data' => '강화 처리에 실패했어요. 게임냥·장비 상태를 확인해주세요.'];
            }
            if (!function_exists('강화비용_소멸분배')) {
                $cfg = dirname(__DIR__) . '/config.php';
                if (is_file($cfg)) {
                    require_once $cfg;
                }
            }
            if (function_exists('강화비용_소멸분배')) {
                강화비용_소멸분배($total_spent, $nick, '채굴강화소멸');
            }
        }

        if ($level !== $start_level) {
            // 배치 성공으로 레벨이 오를 때만 채굴 정산 후 내구 풀회복
            if (function_exists('mining_sync_commit_elapsed')) {
                mining_sync_commit_elapsed($nick);
            }
            $max_sql = function_exists('mining_durability_sql')
                ? mining_durability_sql(
                    function_exists('mining_durability_max') ? mining_durability_max($level) : 100,
                    $level
                )
                : '100';
            if (function_exists('mining_durability_ensure_column')) {
                mining_durability_ensure_column();
            }
            db_query("
                UPDATE `{$tbl}`
                SET mining_tool = {$level},
                    mining_durability = {$max_sql}
                WHERE nick = '{$nick_esc}'
                  AND mining_tool = {$start_level}
                LIMIT 1
            ");
            $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if (!$applied) {
                if ($is_promo && ($spent_positive || $promo_newpoint_cost > 0)) {
                    $refund_parts = [];
                    if ($spent_positive) {
                        $refund_parts[] = "point = point + {$total_spent}";
                    }
                    if ($promo_newpoint_cost > 0) {
                        $refund_parts[] = "newpoint = newpoint + {$promo_newpoint_cost}";
                    }
                    db_query("
                        UPDATE tb_member
                        SET " . implode(', ', $refund_parts) . "
                        WHERE name = '{$nick_esc}'
                        LIMIT 1
                    ");
                } elseif ($spent_positive) {
                    db_query("
                        UPDATE tb_member
                        SET point = point + {$total_spent}
                        WHERE name = '{$nick_esc}'
                        LIMIT 1
                    ");
                }
                return ['ok' => false, 'data' => '강화 처리에 실패했어요. 게임냥·장비 상태를 확인해주세요.'];
            }
        }

        $attempts_after = $attempts_before + $tries;
        mining_upgrade_attempts_ensure_column();
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_upgrade_attempts = {$attempts_after}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");

        $tool = mining_tool_payload_for_nick($nick, $level, $attempts_after);

        if ($spent_positive || ($is_promo && $promo_newpoint_cost > 0)) {
            $log_label = $tries . '회(성' . $successes . '·패' . $fails . ')'
                . ($successes > 0 ? '→' . $tool['label'] : '')
                . ($은총활성 ? '(' . $은총라벨 . ')' : '');
            $log_tag = $is_promo ? ('채굴강화' . $max_times) : '채굴강화배치';
            if ($is_promo) {
                if ($spent_positive) {
                    mining_upgrade_point_log($log_tag, $nick, $log_label . '(게임냥)', $total_spent, $point);
                }
                if ($promo_newpoint_cost > 0 && function_exists('지급로그')) {
                    지급로그($log_tag, $nick, $log_label . '(본방냥)', 0, $promo_newpoint_cost);
                }
            } else {
                mining_upgrade_point_log('채굴강화배치', $nick, $log_label, $total_spent, $point);
            }
        }

        $msg = $tries . '회 강화 · 성공 ' . $successes . ' · 실패 ' . $fails;
        if ($is_promo) {
            if ($spent_positive) {
                $game_fmt = function_exists('mining_fmt_game')
                    ? mining_fmt_game($total_spent)
                    : (function_exists('mining_upgrade_point_fmt') ? mining_upgrade_point_fmt($total_spent) : $total_spent);
                $msg .= ' · 게임냥 ' . $game_fmt . '냥';
            }
            if ($promo_newpoint_cost > 0) {
                $cost_fmt = function_exists('wallet_fmt_new') ? wallet_fmt_new($promo_newpoint_cost) : number_format($promo_newpoint_cost);
                $msg .= ' · 본방냥 ' . $cost_fmt . '냥';
            }
        }
        if ($successes === 0 && $tries > 0) {
            $msg .= ' · 확률 ' . mining_upgrade_success_pct_str($은총zeros, $start_level);
        }
        if ($successes > 0 && !empty($tool['label'])) {
            $msg .= "\n현재: " . ($tool['icon'] ?? '') . $tool['label'];
        }

        $unlock = mining_yield_unlock_payload_from_attempts($attempts_after);
        if (mining_yield_just_unlocked($attempts_before, $attempts_after)) {
            $msg .= "\n🎉 채굴냥 적립 해제!";
        }

        $자숙안내 = '';
        if ($tries > 0 && function_exists('자숙_채굴강화위반_적용')) {
            $자숙위반 = 자숙_채굴강화위반_적용($nick);
            if (!empty($자숙위반['applied']) && !empty($자숙위반['notice'])) {
                $자숙안내 = (string)$자숙위반['notice'];
                $msg = $자숙안내 . $msg;
            }
        }

        $point_now = mining_upgrade_point_refresh($nick);
        $result = [
            'ok' => true,
            'success' => $successes > 0,
            'batch' => true,
            'batch_tries' => $tries,
            'batch_success' => $successes,
            'batch_fail' => $fails,
            'data' => $msg,
            'point' => $point_now,
            'point_fmt' => mining_upgrade_point_fmt($point_now),
            'tool' => $tool,
            'jasuk_extended' => ($자숙안내 !== ''),
        ];
        if ($is_promo) {
            $result['newpoint'] = $newpoint;
            $result['newpoint_fmt'] = function_exists('wallet_fmt_new')
                ? wallet_fmt_new($newpoint)
                : number_format($newpoint);
        }

        return array_merge($result, mining_eunchong_payload($nick), $unlock);
    }
}

if (!function_exists('mining_tool_level_by_name')) {
    /**
     * 장비 표시명/키 → 레벨 (예: 중장비, 황금숟가락, bulldozer)
     * @return int|null
     */
    function mining_tool_level_by_name($name) {
        $raw = trim((string)$name);
        if ($raw === '') {
            return null;
        }
        // 맨 앞 이모지 제거 후 매칭
        $raw = preg_replace('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]+\s*/u', '', $raw);
        $raw = trim((string)$raw);
        $norm = preg_replace('/\s+/u', '', mb_strtolower($raw, 'UTF-8'));
        if ($norm === '') {
            return null;
        }
        if (ctype_digit($norm)) {
            $lv = (int)$norm;
            if ($lv >= 0 && $lv <= mining_tool_max_level()) {
                return $lv;
            }
            return null;
        }
        foreach (mining_tool_base_defs() as $lv => $def) {
            $label = (string)($def['label'] ?? '');
            $key = (string)($def['key'] ?? '');
            $labelNorm = preg_replace('/\s+/u', '', mb_strtolower($label, 'UTF-8'));
            $keyNorm = preg_replace('/\s+/u', '', mb_strtolower($key, 'UTF-8'));
            if ($label === $raw || $labelNorm === $norm || $keyNorm === $norm) {
                return (int)$lv;
            }
        }
        // 부분 일치 (한 건만 매칭될 때)
        $hits = [];
        foreach (mining_tool_base_defs() as $lv => $def) {
            $labelNorm = preg_replace('/\s+/u', '', mb_strtolower((string)($def['label'] ?? ''), 'UTF-8'));
            if ($labelNorm !== '' && (mb_strpos($labelNorm, $norm, 0, 'UTF-8') !== false || mb_strpos($norm, $labelNorm, 0, 'UTF-8') !== false)) {
                $hits[] = (int)$lv;
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }
        return null;
    }
}

if (!function_exists('mining_tool_admin_set_for_nick')) {
    /**
     * 관리자용 채굴 장비 강제 변경 (내구도 풀로)
     * @return array{ok:bool,data:string,level?:int,label?:string,icon?:string}
     */
    function mining_tool_admin_set_for_nick($nick, $tool_name) {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return ['ok' => false, 'data' => '닉네임을 확인해 주세요.'];
        }
        $level = mining_tool_level_by_name($tool_name);
        if ($level === null) {
            $names = [];
            foreach (mining_tool_base_defs() as $def) {
                $names[] = ($def['icon'] ?? '') . ($def['label'] ?? '');
            }
            return [
                'ok' => false,
                'data' => "장비명을 확인해 주세요.\n예) 중장비 · 황금 숟가락 · 곡괭이\n목록: " . implode(' · ', $names),
            ];
        }
        if (!function_exists('mining_data_ensure_row')) {
            require_once __DIR__ . '/mining_storage.inc.php';
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => "[{$nick}] 회원·채굴 정보를 준비할 수 없어요."];
        }
        $nick_esc = function_exists('mining_data_nick_esc') ? mining_data_nick_esc($nick) : addslashes($nick);
        $tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
        $max_sql = function_exists('mining_durability_sql')
            ? mining_durability_sql(
                function_exists('mining_durability_max') ? mining_durability_max($level) : 100,
                $level
            )
            : '100';
        if (function_exists('mining_durability_ensure_column')) {
            mining_durability_ensure_column();
        }
        $before = mining_tool_level_for_nick($nick);
        db_query("
            UPDATE `{$tbl}`
            SET mining_tool = {$level},
                mining_durability = {$max_sql}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $def = mining_tool_def($level);
        $beforeDef = mining_tool_def($before);
        $label = ($def['icon'] ?? '') . ($def['label'] ?? '');
        $beforeLabel = ($beforeDef['icon'] ?? '') . ($beforeDef['label'] ?? '');
        return [
            'ok' => true,
            'data' => "✅ [{$nick}] 채굴 장비 변경\n{$beforeLabel} → {$label}",
            'level' => $level,
            'label' => (string)($def['label'] ?? ''),
            'icon' => (string)($def['icon'] ?? ''),
        ];
    }
}

if (!function_exists('mining_tool_ranking_message')) {
    function mining_tool_ranking_message(int $limit = 0, string $rankEmoji = ''): string {
        if (!function_exists('db_query')) {
            return '❌ 랭킹을 불러올 수 없습니다.';
        }
        $limit = max(0, (int)$limit);
        $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';
        $result = @db_query("
            SELECT m.name, m.level, m.title, m.point, IFNULL(mm.mining_tool, 0) AS mining_tool
            FROM tb_member m
            LEFT JOIN tb_member_mining mm ON mm.nick = m.name
            WHERE IFNULL(m.status, 0) = 0
            ORDER BY IFNULL(mm.mining_tool, 0) DESC, m.name ASC
            {$limitSql}
        ");
        if (!$result) {
            return '❌ 랭킹을 불러올 수 없습니다.';
        }
        if ($limit > 0 && $limit < 10) {
            $msg = "✅ 채굴 장비 랭킹\n";
        } else {
            $msg = "✅ 채굴 장비 랭킹\n\n";
        }
        $rank = 1;
        while ($row = db_fetch($result)) {
            $계급 = 계급($row['point']);
            $호칭 = $row['title'] ?: $계급['name'];
            $def = mining_tool_def((int)($row['mining_tool'] ?? 0));
            $msg .= $rank . "등{$rankEmoji} Lv {$row['level']} {$호칭} {$row['name']} {$def['icon']}{$def['label']}\n";
            $rank++;
        }
        if ($rank === 1) {
            $msg .= '(채굴 장비 보유 회원 없음)';
        }
        return $msg;
    }
}
