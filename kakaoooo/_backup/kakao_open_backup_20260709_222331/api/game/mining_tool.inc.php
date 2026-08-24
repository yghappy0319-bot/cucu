<?php
/**
 * 채굴 장비 (숟가락 → … → 황금 숟가락) — tb_member_mining
 */

require_once __DIR__ . '/mining_storage.inc.php';

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
    function mining_tool_upgrade_info($level, $use_eunchong = false) {
        $level = max(0, (int)$level);
        $max = mining_tool_max_level();
        if ($level >= $max) {
            return null;
        }
        $next = mining_tool_def($level + 1);
        $cur = mining_tool_def($level);
        $cost_base = mining_upgrade_cost_for_level($level, false);
        $cost_eunchong = mining_upgrade_cost_for_level($level, true);
        $cost = $use_eunchong ? $cost_eunchong : $cost_base;
        return [
            'from_level' => $level,
            'to_level' => $level + 1,
            'from_label' => $cur['label'],
            'to_label' => $next['label'],
            'to_icon' => $next['icon'],
            'cost_base' => $cost_base,
            'cost_base_fmt' => mining_upgrade_cost_fmt($cost_base),
            'cost_eunchong' => $cost_eunchong,
            'cost_eunchong_fmt' => mining_upgrade_cost_fmt($cost_eunchong),
            'cost' => $cost,
            'cost_fmt' => mining_upgrade_cost_fmt($cost),
            'success_pct' => mining_upgrade_success_pct_str(false, $level),
            'success_pct_eunchong' => mining_upgrade_success_pct_str(true, $level),
            'success_hint' => mining_upgrade_success_hint_str(false, $level),
            'success_hint_eunchong' => mining_upgrade_success_hint_str(true, $level),
        ];
    }
}

if (!function_exists('mining_tool_daily_yield_fmt')) {
    function mining_tool_daily_yield_fmt($level): string {
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
        $mult = max(1.0, (float)$yield_mult);
        $rate = (float)mining_tool_rate_for_level($level) * $mult;
        $hourly = (float)mining_tool_hourly_yield($level) * $mult;
        $daily = (float)mining_tool_daily_yield($level) * $mult;
        return [
            'rate' => $rate,
            'rate_fmt' => mining_rate_fmt($rate),
            'hourly_yield' => $hourly,
            'hourly_yield_fmt' => mining_yield_amount_fmt($hourly),
            'daily_yield' => $daily,
            'daily_yield_fmt' => mining_yield_amount_fmt($daily),
            'yield_mult' => $mult > 1.0 ? $mult : 1.0,
            'eunchong_yield' => $mult > 1.0,
        ];
    }
}

if (!function_exists('mining_tool_roadmap_yield_table')) {
    /** @return list<array{level:int,icon:string,label:string,rate:float,rate_fmt:string,daily_yield:float,daily_yield_fmt:string,upgrade_cost:?int,upgrade_cost_fmt:string,is_current:bool}> */
    function mining_tool_roadmap_yield_table($current_level = null) {
        $current_level = $current_level === null ? null : max(0, min(mining_tool_max_level(), (int)$current_level));
        $rows = [];
        foreach (mining_tool_catalog() as $level => $def) {
            $rate = mining_tool_rate_payload($level);
            $upgrade = mining_tool_upgrade_info($level);
            $rows[] = [
                'level' => (int)$level,
                'icon' => $def['icon'],
                'label' => $def['label'],
                'rate' => (float)$rate['rate'],
                'rate_fmt' => $rate['rate_fmt'],
                'daily_yield' => (float)$rate['daily_yield'],
                'daily_yield_fmt' => $rate['daily_yield_fmt'],
                'upgrade_cost' => $upgrade ? (int)$upgrade['cost'] : null,
                'upgrade_cost_fmt' => $upgrade ? (string)$upgrade['cost_fmt'] : '—',
                'upgrade_success_pct' => $upgrade ? (string)$upgrade['success_pct'] : '—',
                'is_current' => $current_level !== null && (int)$current_level === (int)$level,
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
        $payload = mining_tool_payload($level);
        if ($upgrade_attempts !== null) {
            $unlock = mining_yield_unlock_payload_from_attempts((int)$upgrade_attempts);
        } else {
            $unlock = mining_yield_unlock_payload($nick);
        }
        if (empty($unlock['yield_unlocked'])) {
            $payload['rate'] = 0.0;
            $payload['rate_fmt'] = '0';
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
        $nick_esc = addslashes(trim((string)$nick));
        $status_esc = addslashes((string)$status);
        $receiver_esc = addslashes((string)$receiver);
        $cost = (int)$cost;
        if ($nick_esc === '' || $cost < 0) {
            return false;
        }
        $mypoint_sql = ($mypoint !== null) ? ', mypoint = ' . (int)$mypoint : '';
        return (bool)@db_query("
            INSERT INTO tb_point_log
            SET status = '{$status_esc}',
                nick = '{$nick_esc}',
                receiver = '{$receiver_esc}',
                tax = 0,
                point = {$cost},
                regdate = NOW()
                {$mypoint_sql}
        ");
    }
}

if (!function_exists('mining_upgrade_point_fmt')) {
    function mining_upgrade_point_fmt($point) {
        return function_exists('mining_fmt_game')
            ? mining_fmt_game((int)$point)
            : number_format((int)$point);
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

        $member = db_select("SELECT point, IFNULL(newpoint, 0) AS newpoint, IFNULL(은총개수, 0) AS eunchong_cnt, 은총 FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($member)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.'];
        }

        $은총활성 = mining_eunchong_active($member['은총'] ?? '');

        $row = mining_data_select_raw($nick);
        $level = mining_tool_level_from_row($row ?: []);
        $upgrade = mining_tool_upgrade_info($level, $은총활성);
        if ($upgrade === null) {
            return ['ok' => false, 'data' => '이미 최고 단계 장비예요.'];
        }

        $cost = (int)$upgrade['cost'];
        $point = (int)($member['point'] ?? 0);
        if ($point < $cost) {
            return [
                'ok' => false,
                'data' => '게임냥이 부족해요. (필요 ' . number_format($cost) . '냥 · 보유 ' . number_format($point) . '냥)',
            ];
        }

        if (empty($opts['skip_sync']) && function_exists('mining_sync_commit_elapsed')) {
            mining_sync_commit_elapsed($nick);
        }
        $roll = mining_upgrade_success_roll_for_level($level, $은총활성);
        $분모 = (int)$roll['denom'];
        $분자 = (int)$roll['num'];
        if ($분자 < 1) {
            $분자 = 1;
        }

        $주사위 = random_int(1, $분모);
        $성공 = ($주사위 <= $분자);
        $next_level = (int)$upgrade['to_level'];
        $tbl = MINING_TABLE;
        $pct_str = mining_upgrade_success_pct_str($은총활성, $level);

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

        $point -= $cost;
        $attempts_before = mining_upgrade_attempts_for_nick($nick);
        mining_upgrade_attempts_add($nick, 1);
        $attempts_after = $attempts_before + 1;

        if ($성공) {
            db_query("
                UPDATE `{$tbl}`
                SET mining_tool = {$next_level}
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
            $log_label = $tool['label'] . ($은총활성 ? '(은총)' : '');
            mining_upgrade_point_log('채굴강화성공', $nick, $log_label, $cost, $point);
        }

        if ($성공) {
            $msg = $upgrade['from_label'] . ' → ' . $tool['icon'] . $tool['label'] . ' 강화 성공!';
            if ($은총활성) {
                $msg .= ' (은총 버프 · ' . $pct_str . ')';
            }
        } else {
            $msg = '강화 실패… ' . number_format($cost) . '냥이 소진됐어요.';
            if ($은총활성) {
                $msg .= ' (은총 버프 · ' . $pct_str . ')';
            } else {
                $msg .= ' (' . $pct_str . ' 확률)';
            }
        }

        $unlock = mining_yield_unlock_payload_from_attempts($attempts_after);
        if (mining_yield_just_unlocked($attempts_before, $attempts_after)) {
            $msg .= "\n🎉 채굴냥 적립 해제!";
        }

        return array_merge([
            'ok' => true,
            'success' => $성공,
            'data' => $msg,
            'point' => $point,
            'point_fmt' => mining_upgrade_point_fmt($point),
            'tool' => mining_tool_payload_for_nick($nick, $level),
        ], mining_eunchong_payload($nick), $unlock);
    }
}

if (!function_exists('mining_upgrade_execute_batch')) {
    /**
     * 채굴 장비 강화 연속 시도 (최대 100회 · sync 1회 · 배치 요약 로그 1건)
     * 호출 전 mining_sync_commit_elapsed() 1회 권장.
     *
     * @return array<string,mixed>
     */
    function mining_upgrade_execute_batch($nick, $max_times = 10) {
        $max_times = (int)$max_times;
        $promo_1000 = ($max_times === (int)MINING_UPGRADE_BATCH_1000_TIMES);
        if ($promo_1000) {
            $max_times = (int)MINING_UPGRADE_BATCH_1000_TIMES;
        } else {
            $max_times = max(1, min(100, $max_times));
        }
        $promo_newpoint_cost = (int)MINING_UPGRADE_BATCH_1000_COST;
        if ($promo_newpoint_cost < 1) {
            $promo_newpoint_cost = 3;
        }
        mining_data_ensure_table();
        wallet_odd_even_includes();

        $nick = trim((string)$nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }

        $member = db_select("SELECT point, IFNULL(newpoint, 0) AS newpoint, IFNULL(은총개수, 0) AS eunchong_cnt, 은총 FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($member)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.'];
        }

        $은총활성 = mining_eunchong_active($member['은총'] ?? '');

        $row = mining_data_select_raw($nick);
        $level = mining_tool_level_from_row($row ?: []);
        $start_level = $level;
        $start_point = (int)($member['point'] ?? 0);
        $start_newpoint = (int)floor((float)($member['newpoint'] ?? 0));
        $point = $start_point;
        $newpoint = $start_newpoint;
        $attempts_before = mining_upgrade_attempts_for_nick($nick);
        $tbl = MINING_TABLE;
        global $conn;

        if ($promo_1000 && $start_newpoint < $promo_newpoint_cost) {
            $np_fmt = function_exists('wallet_fmt_new') ? wallet_fmt_new($start_newpoint) : number_format($start_newpoint);
            $cost_fmt = function_exists('wallet_fmt_new') ? wallet_fmt_new($promo_newpoint_cost) : number_format($promo_newpoint_cost);
            return [
                'ok' => false,
                'data' => '본방냥이 부족해요. (1000회 강화 ' . $cost_fmt . '냥 · 보유 ' . $np_fmt . '냥)',
            ];
        }

        $tries = 0;
        $successes = 0;
        $fails = 0;
        $total_spent = 0;

        for ($i = 0; $i < $max_times; $i++) {
            $upgrade = mining_tool_upgrade_info($level, $은총활성);
            if ($upgrade === null) {
                if ($promo_1000) {
                    $tries++;
                    $fails++;
                    continue;
                }
                if ($tries === 0) {
                    return ['ok' => false, 'data' => '이미 최고 단계 장비예요.'];
                }
                break;
            }

            $cost = (int)$upgrade['cost'];
            if ($point < $cost) {
                if ($tries === 0) {
                    return [
                        'ok' => false,
                        'data' => '게임냥이 부족해요. (필요 ' . number_format($cost) . '냥 · 보유 ' . number_format($point) . '냥)',
                    ];
                }
                break;
            }

            $roll = mining_upgrade_success_roll_for_level($level, $은총활성);
            $분모 = (int)$roll['denom'];
            $분자 = (int)$roll['num'];
            if ($분자 < 1) {
                $분자 = 1;
            }
            if ($분모 < 1) {
                $분모 = 1;
            }

            $성공 = random_int(1, $분모) <= $분자;

            $point -= $cost;
            $total_spent += $cost;
            $tries++;

            if ($성공) {
                $level = (int)$upgrade['to_level'];
                $successes++;
            } else {
                $fails++;
            }

            if (!$promo_1000 && mining_tool_upgrade_info($level, $은총활성) === null) {
                break;
            }
        }

        if ($tries === 0) {
            return ['ok' => false, 'data' => '강화를 시도할 수 없습니다.'];
        }

        if ($promo_1000) {
            $newpoint = $start_newpoint - $promo_newpoint_cost;
        }

        if ($promo_1000 && ($total_spent > 0 || $promo_newpoint_cost > 0)) {
            $set_parts = [];
            $where_parts = ["name = '{$nick_esc}'"];
            if ($total_spent > 0) {
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
        } elseif ($total_spent > 0) {
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
        }

        if ($level !== $start_level) {
            db_query("
                UPDATE `{$tbl}`
                SET mining_tool = {$level}
                WHERE nick = '{$nick_esc}'
                  AND mining_tool = {$start_level}
                LIMIT 1
            ");
            $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if (!$applied) {
                if ($promo_1000 && ($total_spent > 0 || $promo_newpoint_cost > 0)) {
                    $refund_parts = [];
                    if ($total_spent > 0) {
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
                } elseif ($total_spent > 0) {
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

        if ($total_spent > 0 || ($promo_1000 && $promo_newpoint_cost > 0)) {
            $log_label = $tries . '회(성' . $successes . '·패' . $fails . ')'
                . ($successes > 0 ? '→' . $tool['label'] : '')
                . ($은총활성 ? '(은총)' : '');
            if ($promo_1000) {
                if ($total_spent > 0) {
                    mining_upgrade_point_log('채굴강화1000', $nick, $log_label . '(게임냥)', $total_spent, $point);
                }
                if ($promo_newpoint_cost > 0 && function_exists('지급로그')) {
                    지급로그('채굴강화1000', $nick, $log_label . '(본방냥)', 0, $promo_newpoint_cost);
                }
            } else {
                mining_upgrade_point_log('채굴강화배치', $nick, $log_label, $total_spent, $point);
            }
        }

        $msg = $tries . '회 강화 · 성공 ' . $successes . ' · 실패 ' . $fails;
        if ($promo_1000) {
            if ($total_spent > 0) {
                $game_fmt = function_exists('mining_fmt_game')
                    ? mining_fmt_game($total_spent)
                    : number_format($total_spent);
                $msg .= ' · 게임냥 ' . $game_fmt . '냥';
            }
            if ($promo_newpoint_cost > 0) {
                $cost_fmt = function_exists('wallet_fmt_new') ? wallet_fmt_new($promo_newpoint_cost) : number_format($promo_newpoint_cost);
                $msg .= ' · 본방냥 ' . $cost_fmt . '냥';
            }
        }
        if ($successes === 0 && $tries > 0) {
            $msg .= ' · 확률 ' . mining_upgrade_success_pct_str($은총활성, $start_level);
        }
        if ($successes > 0 && !empty($tool['label'])) {
            $msg .= "\n현재: " . ($tool['icon'] ?? '') . $tool['label'];
        }

        $unlock = mining_yield_unlock_payload_from_attempts($attempts_after);
        if (mining_yield_just_unlocked($attempts_before, $attempts_after)) {
            $msg .= "\n🎉 채굴냥 적립 해제!";
        }

        $result = [
            'ok' => true,
            'success' => $successes > 0,
            'batch' => true,
            'batch_tries' => $tries,
            'batch_success' => $successes,
            'batch_fail' => $fails,
            'data' => $msg,
            'point' => $point,
            'point_fmt' => mining_upgrade_point_fmt($point),
            'tool' => $tool,
        ];
        if ($promo_1000) {
            $result['newpoint'] = $newpoint;
            $result['newpoint_fmt'] = function_exists('wallet_fmt_new')
                ? wallet_fmt_new($newpoint)
                : number_format($newpoint);
        }

        return array_merge($result, mining_eunchong_payload($nick), $unlock);
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
