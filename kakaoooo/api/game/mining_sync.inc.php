<?php
/**
 * 채굴 미저장 냥 동기화 + 단일 활성 세션(lease)
 * — tb_member_mining 기준, PC/모바일 공유
 */

require_once __DIR__ . '/mining_tool.inc.php';
require_once __DIR__ . '/mining_durability.inc.php';

if (!defined('MINING_LEASE_SEC')) {
    define('MINING_LEASE_SEC', 25);
}

if (!function_exists('mining_sync_select_sql')) {
    /** tb_member_mining SELECT 컬럼 공통 */
    function mining_sync_select_sql(): string {
        mining_durability_ensure_column();
        mining_upgrade_attempts_ensure_column();
        $dur_default = mining_durability_sql(mining_durability_max(0), 0);
        return "mining_tool, mining_pending, mining_sync_at,
                   mining_lease_token, mining_lease_until,
                   IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts,
                   IFNULL(mining_durability, {$dur_default}) AS mining_durability";
    }
}

if (!function_exists('mining_nick_is_퇴근')) {
    /** .퇴근/.장퇴 등록(tb_work 또는 tb_member.status=3) */
    function mining_nick_is_퇴근($nick): bool {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return false;
        }
        if (function_exists('wallet_nick_is_퇴근')) {
            return (bool)wallet_nick_is_퇴근($nick);
        }
        if (function_exists('getTwoCharNick')) {
            $parsed = getTwoCharNick($nick);
            if ($parsed !== '') {
                $nick = $parsed;
            }
        }
        $esc = addslashes($nick);
        $work = @db_select("SELECT idx FROM tb_work WHERE nick = '{$esc}' LIMIT 1");
        if (!empty($work['idx'])) {
            return true;
        }
        $mem = @db_select("SELECT status FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        return isset($mem['status']) && (int)$mem['status'] === 3;
    }
}

if (!function_exists('mining_sync_simulate_elapsed')) {
    /**
     * 경과 시간 → 적립·내구 소모 시뮬레이션 (DB 미반영)
     * @return array{pending:float,durability:float,effective_sec:int,rate:float,broken:bool,off_work:bool}
     */
    function mining_sync_simulate_elapsed(array $row, $nick = '', $elapsed = null): array {
        $pending0 = mining_pending_round($row['mining_pending'] ?? 0);
        $level0 = max(0, (int)($row['mining_tool'] ?? 0));
        $dur0 = mining_durability_from_row($row);
        $floor = function_exists('mining_durability_stop_floor')
            ? mining_durability_stop_floor($level0)
            : 0.0;
        $stopped0 = function_exists('mining_durability_is_stopped')
            ? mining_durability_is_stopped($dur0, $level0)
            : ($dur0 <= 1e-9);
        $nick = trim((string)$nick);
        $off_work = ($nick !== '' && mining_nick_is_퇴근($nick));
        if ($elapsed === null) {
            $sync_at = mining_sync_ts($row['mining_sync_at'] ?? '');
            $elapsed = max(0, mining_sync_now() - $sync_at);
        } else {
            $elapsed = max(0, (int)$elapsed);
        }

        $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
        $unlocked = mining_yield_unlocked_for_attempts($attempts);
        if (!$unlocked || $elapsed <= 0 || $off_work) {
            return [
                'pending' => $pending0,
                'durability' => $dur0,
                'effective_sec' => 0,
                'rate' => 0.0,
                'broken' => $stopped0,
                'off_work' => $off_work,
            ];
        }

        // STOP_PCT 이하: 채굴·시간 마모 정지 (보스 등으로 이미 더 낮을 수 있음)
        if ($stopped0) {
            return [
                'pending' => $pending0,
                'durability' => $dur0,
                'effective_sec' => 0,
                'rate' => 0.0,
                'broken' => true,
                'off_work' => false,
            ];
        }

        $level = $level0;
        $rate = (float)mining_tool_rate_for_level($level);
        if ($nick !== '') {
            $rate *= mining_sync_yield_mult_for_nick($nick);
        }
        $eunchong = ($nick !== '' && mining_eunchong_active_for_nick($nick));
        $wear = mining_durability_wear_per_sec($level, $eunchong);

        if ($wear <= 0) {
            $eff = $elapsed;
            $dur1 = $dur0;
        } else {
            $headroom = max(0.0, $dur0 - $floor);
            $sec_until_stop = $headroom / $wear;
            if ($elapsed >= $sec_until_stop) {
                $eff_f = $sec_until_stop;
                $dur1 = $floor;
            } else {
                $eff_f = (float)$elapsed;
                $dur1 = max($floor, $dur0 - $elapsed * $wear);
            }
            $pending1 = mining_pending_round($pending0 + ($eff_f * $rate));
            $stopped1 = function_exists('mining_durability_is_stopped')
                ? mining_durability_is_stopped($dur1, $level)
                : ($dur1 <= 1e-9);
            return [
                'pending' => $pending1,
                'durability' => $dur1,
                'effective_sec' => (int)floor($eff_f),
                'rate' => $rate,
                'broken' => $stopped1,
                'off_work' => false,
            ];
        }

        $stopped1 = function_exists('mining_durability_is_stopped')
            ? mining_durability_is_stopped($dur1, $level)
            : ($dur1 <= 1e-9);
        return [
            'pending' => mining_pending_round($pending0 + ($eff * $rate)),
            'durability' => $dur1,
            'effective_sec' => $eff,
            'rate' => $rate,
            'broken' => $stopped1,
            'off_work' => false,
        ];
    }
}

if (!function_exists('mining_sync_table')) {
    function mining_sync_table(): string {
        return MINING_TABLE;
    }
}

if (!function_exists('mining_sync_now')) {
    function mining_sync_now(): int {
        return time();
    }
}

if (!function_exists('mining_sync_ts')) {
    function mining_sync_ts($dt): int {
        if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
            return mining_sync_now();
        }
        $t = strtotime((string)$dt);
        return ($t !== false && $t > 0) ? $t : mining_sync_now();
    }
}

if (!function_exists('mining_sync_finalize_expired')) {
    /** lease 만료·비정상 종료 시 마지막 구간 pending 확정 */
    function mining_sync_finalize_expired($nick) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }

        $tbl = mining_sync_table();
        $row = db_select("
            SELECT " . mining_sync_select_sql() . "
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return null;
        }

        $token = trim((string)($row['mining_lease_token'] ?? ''));
        $lease_until = mining_sync_ts($row['mining_lease_until'] ?? '');
        if ($token === '' || $lease_until <= 0) {
            return $row;
        }
        if (mining_sync_lease_active($row)) {
            return $row;
        }

        $sync_at = mining_sync_ts($row['mining_sync_at'] ?? '');
        $elapsed = max(0, mining_sync_now() - $sync_at);
        $sim = mining_sync_simulate_elapsed($row, $nick, $elapsed);
        $pending_sql = mining_pending_sql($sim['pending']);
        $tool_lv = max(0, (int)($row['mining_tool'] ?? 0));
        $dur_sql = mining_durability_sql($sim['durability'], $tool_lv);

        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = {$pending_sql},
                mining_durability = {$dur_sql},
                mining_sync_at = NOW(),
                mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
              AND mining_lease_token = '" . addslashes($token) . "'
            LIMIT 1
        ");

        if ($elapsed > 0) {
            // 광물 spawn 은 _auto_mining_ore.php 크론 전담
        }

        return mining_sync_row($nick);
    }
}

if (!function_exists('mining_sync_row')) {
    function mining_sync_row($nick) {
        mining_data_ensure_table();
        mining_upgrade_attempts_ensure_column();
        mining_durability_ensure_column();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        if (!mining_data_ensure_row($nick)) {
            return null;
        }

        $tbl = mining_sync_table();
        $row = db_select("
            SELECT " . mining_sync_select_sql() . "
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return null;
        }
        if (!mining_sync_lease_active($row) && trim((string)($row['mining_lease_token'] ?? '')) !== '') {
            return mining_sync_finalize_expired($nick) ?: $row;
        }
        return $row;
    }
}

if (!function_exists('mining_sync_rate')) {
    function mining_sync_rate(array $row, $nick = ''): float {
        $nick = trim((string)$nick);
        if ($nick !== '' && mining_nick_is_퇴근($nick)) {
            return 0.0;
        }
        $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
        if (!mining_yield_unlocked_for_attempts($attempts)) {
            return 0.0;
        }
        $dur = mining_durability_from_row($row);
        if ($dur <= 1e-9) {
            return 0.0;
        }
        $level = max(0, (int)($row['mining_tool'] ?? 0));
        $rate = mining_tool_rate_for_level($level);
        if ($nick !== '') {
            $rate *= mining_sync_yield_mult_for_nick($nick);
        }
        return (float)$rate;
    }
}

if (!function_exists('mining_sync_yield_mult_for_nick')) {
    /** 채팅 생타 배율 × 은총 배율 × 무기결합(강화÷10) */
    function mining_sync_yield_mult_for_nick($nick): float {
        $parts = mining_sync_yield_mult_parts_for_nick($nick);
        return (float)$parts['total'];
    }
}

if (!function_exists('mining_sync_yield_mult_parts_for_nick')) {
    /**
     * @return array{chat:float,eunchong:float,weapon:float,without_weapon:float,total:float}
     */
    function mining_sync_yield_mult_parts_for_nick($nick): array {
        $nick = trim((string)$nick);
        $chat = ($nick !== '' && function_exists('mining_chat_yield_mult_for_nick'))
            ? (float)mining_chat_yield_mult_for_nick($nick)
            : 1.0;
        $eunchong = ($nick !== '' && function_exists('mining_eunchong_active_for_nick')
            && mining_eunchong_active_for_nick($nick))
            ? (function_exists('mining_eunchong_yield_mult') ? (float)mining_eunchong_yield_mult(true) : 1.0)
            : 1.0;
        $weapon = 1.0;
        if ($nick !== '') {
            if (!function_exists('mining_weapon_yield_mult_for_nick')) {
                $weaponInc = __DIR__ . '/mining_weapon.inc.php';
                if (is_file($weaponInc)) {
                    @require_once $weaponInc;
                }
            }
            if (function_exists('mining_weapon_yield_mult_for_nick')) {
                $weapon = (float)mining_weapon_yield_mult_for_nick($nick);
            }
        }
        $chat = max(0.0, $chat);
        $eunchong = max(1.0, $eunchong);
        $weapon = max(0.0, $weapon);
        $without = $chat * $eunchong;
        return [
            'chat' => $chat,
            'eunchong' => $eunchong,
            'weapon' => $weapon,
            'without_weapon' => $without,
            'total' => $without * $weapon,
        ];
    }
}

if (!function_exists('mining_sync_lease_active')) {
    function mining_sync_lease_active(array $row): bool {
        return mining_sync_ts($row['mining_lease_until'] ?? '') > mining_sync_now();
    }
}

if (!function_exists('mining_sync_lease_owner')) {
    function mining_sync_lease_owner(array $row, $token): bool {
        $token = trim((string)$token);
        if ($token === '' || !mining_sync_lease_active($row)) {
            return false;
        }
        return hash_equals(trim((string)($row['mining_lease_token'] ?? '')), $token);
    }
}

if (!function_exists('mining_sync_display_amount')) {
    /** offline 포함 — sync_at 이후 경과분 항상 반영 (내구 소진 시 적립 중단) */
    function mining_sync_display_amount(array $row, $nick = ''): float {
        $sim = mining_sync_simulate_elapsed($row, $nick, null);
        return mining_pending_round($sim['pending']);
    }
}

if (!function_exists('mining_sync_commit_elapsed')) {
    /** 경과분을 pending·내구에 확정 (offline 적립 · 저장/강화 전) */
    function mining_sync_commit_elapsed($nick) {
        mining_data_ensure_table();
        mining_durability_ensure_column();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return null;
        }
        if (!mining_data_ensure_row($nick)) {
            return null;
        }

        global $conn;
        $tbl = mining_sync_table();
        $use_txn = ($conn instanceof mysqli);

        if ($use_txn) {
            mysqli_begin_transaction($conn);
        }
        try {
            $row = db_select("
                SELECT " . mining_sync_select_sql() . "
                FROM `{$tbl}`
                WHERE nick = '{$nick_esc}'
                LIMIT 1
                FOR UPDATE
            ");
            if (!$row) {
                if ($use_txn) {
                    mysqli_rollback($conn);
                }
                return null;
            }

            $sync_at = mining_sync_ts($row['mining_sync_at'] ?? '');
            $elapsed_sec = max(0, mining_sync_now() - $sync_at);
            $sim = mining_sync_simulate_elapsed($row, $nick, $elapsed_sec);
            $pending_sql = mining_pending_sql($sim['pending']);
            $tool_lv = max(0, (int)($row['mining_tool'] ?? 0));
            $dur_sql = mining_durability_sql($sim['durability'], $tool_lv);
            db_query("
                UPDATE `{$tbl}`
                SET mining_pending = {$pending_sql},
                    mining_durability = {$dur_sql},
                    mining_sync_at = NOW()
                WHERE nick = '{$nick_esc}'
                LIMIT 1
            ");

            if ($use_txn) {
                mysqli_commit($conn);
            }
        } catch (Throwable $e) {
            if ($use_txn) {
                mysqli_rollback($conn);
            }
            throw $e;
        }

        return mining_sync_row($nick);
    }
}

if (!function_exists('mining_sync_new_token')) {
    function mining_sync_new_token(): string {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('mining_sync_payload')) {
    function mining_sync_payload(array $row, $is_leader, $token = '', $nick = '') {
        $nick = trim((string)$nick);
        $sim = mining_sync_simulate_elapsed($row, $nick, null);
        $amount = mining_pending_round($sim['pending']);
        $level = max(0, (int)($row['mining_tool'] ?? 0));
        $parts = mining_sync_yield_mult_parts_for_nick($nick);
        $yield_mult = (float)$parts['total'];
        $rate = mining_tool_rate_payload($level, $yield_mult);
        $base_rate = mining_tool_rate_payload($level, (float)$parts['without_weapon']);
        $weapon_mult = (float)$parts['weapon'];
        $weapon_fmt = function_exists('mining_weapon_yield_mult_fmt')
            ? mining_weapon_yield_mult_fmt($weapon_mult)
            : rtrim(rtrim(number_format($weapon_mult, 4, '.', ''), '0'), '.');
        $rate['rate_base'] = (float)$base_rate['rate'];
        $rate['rate_base_fmt'] = (string)$base_rate['rate_fmt'];
        $rate['weapon_yield_mult'] = $weapon_mult;
        $rate['weapon_yield_mult_fmt'] = $weapon_fmt !== '' ? $weapon_fmt : '1';
        $unlocked = mining_yield_unlocked_for_attempts((int)($row['mining_upgrade_attempts'] ?? 0));
        $broken = !empty($sim['broken']);
        $off_work = !empty($sim['off_work']) || ($nick !== '' && mining_nick_is_퇴근($nick));
        if (!$unlocked || $broken || $off_work) {
            $rate = [
                'rate' => 0.0,
                'rate_fmt' => '0',
                'rate_base' => 0.0,
                'rate_base_fmt' => '0',
                'weapon_yield_mult' => $weapon_mult,
                'weapon_yield_mult_fmt' => $rate['weapon_yield_mult_fmt'],
                'hourly_yield' => 0.0,
                'hourly_yield_fmt' => '0',
                'daily_yield' => 0.0,
                'daily_yield_fmt' => '0',
                'time_to_1pct' => 0.0,
                'time_to_1pct_fmt' => '',
            ];
        }
        $required = mining_upgrade_attempts_required();
        $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
        $dur_payload = mining_durability_payload($row, $nick, (float)$sim['durability']);
        $chat_payload = ($nick !== '' && function_exists('mining_chat_yield_payload'))
            ? mining_chat_yield_payload($nick)
            : [
                'chat_raw_tasu' => 0,
                'chat_yield_mult' => 1.0,
                'chat_yield_mult_fmt' => '1',
                'chat_yield_hint' => '',
            ];
        $hint = '';
        if ($off_work) {
            $hint = '퇴근 중 · 채굴 정지 (.출근 후 재개)';
        } elseif ($unlocked) {
            $hint = $broken
                ? ('내구도 ' . (function_exists('mining_durability_stop_pct') ? mining_durability_stop_pct() : 10) . '% 이하 · 수리 후 채굴이 다시 시작돼요')
                : '';
        } else {
            $hint = "강화 {$required}회 후 채굴냥 적립 (현재 {$attempts}/{$required})";
        }
        return array_merge([
            'pending' => $amount,
            'pending_fmt' => function_exists('mining_fmt_pending')
                ? mining_fmt_pending($amount)
                : (string)$amount,
            'sync_at' => (int)mining_sync_ts($row['mining_sync_at'] ?? ''),
            'is_leader' => (bool)$is_leader,
            'lease_token' => $is_leader ? trim((string)$token) : '',
            'lease_active' => mining_sync_lease_active($row),
            'lease_sec' => (int)MINING_LEASE_SEC,
            'mining_tool' => $level,
            'upgrade_attempts' => $attempts,
            'upgrade_attempts_required' => $required,
            'yield_unlocked' => $unlocked,
            'off_work' => $off_work,
            'unlock_hint' => $hint,
        ], $rate, $dur_payload, $chat_payload);
    }
}

if (!function_exists('mining_sync_init_sync_at')) {
    function mining_sync_init_sync_at($nick_esc) {
        $tbl = mining_sync_table();
        @db_query("
            UPDATE `{$tbl}`
            SET mining_sync_at = NOW()
            WHERE nick = '{$nick_esc}'
              AND (mining_sync_at IS NULL OR mining_sync_at = '0000-00-00 00:00:00')
        ");
    }
}

if (!function_exists('mining_sync_flush')) {
    /**
     * lease 보유 중 경과분을 pending에 반영 후 sync_at 갱신
     * @return array|null 갱신된 row
     */
    function mining_sync_flush($nick, $token) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        $token = trim((string)$token);
        if ($nick_esc === '' || $token === '') {
            return null;
        }

        global $conn;
        $tbl = mining_sync_table();
        $token_esc = addslashes($token);
        $lease_sec = (int)MINING_LEASE_SEC;
        $use_txn = ($conn instanceof mysqli);

        if ($use_txn) {
            mysqli_begin_transaction($conn);
        }
        try {
            $row = db_select("
                SELECT " . mining_sync_select_sql() . "
                FROM `{$tbl}`
                WHERE nick = '{$nick_esc}'
                LIMIT 1
                FOR UPDATE
            ");
            if (!$row) {
                if ($use_txn) {
                    mysqli_rollback($conn);
                }
                return null;
            }

            if (!mining_sync_lease_owner($row, $token)) {
                if ($use_txn) {
                    mysqli_rollback($conn);
                }
                return mining_sync_commit_elapsed($nick);
            }

            $pending = mining_sync_display_amount($row, $nick);
            $sim = mining_sync_simulate_elapsed($row, $nick, null);
            $pending_sql = mining_pending_sql($pending);
            $tool_lv = max(0, (int)($row['mining_tool'] ?? 0));
            $dur_sql = mining_durability_sql($sim['durability'], $tool_lv);

            db_query("
                UPDATE `{$tbl}`
                SET mining_pending = {$pending_sql},
                    mining_durability = {$dur_sql},
                    mining_sync_at = NOW(),
                    mining_lease_token = '{$token_esc}',
                    mining_lease_until = DATE_ADD(NOW(), INTERVAL {$lease_sec} SECOND)
                WHERE nick = '{$nick_esc}'
                  AND mining_lease_token = '{$token_esc}'
                LIMIT 1
            ");

            if ($use_txn) {
                mysqli_commit($conn);
            }
        } catch (Throwable $e) {
            if ($use_txn) {
                mysqli_rollback($conn);
            }
            throw $e;
        }

        return mining_sync_row($nick);
    }
}

if (!function_exists('mining_sync_acquire')) {
    function mining_sync_acquire($nick) {
        mining_data_ensure_table();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보가 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }

        mining_sync_init_sync_at($nick_esc);
        $row = mining_sync_row($nick);
        if (!$row) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }

        if (mining_sync_lease_active($row)) {
            return [
                'ok' => true,
                'type' => 'sync',
                'active_elsewhere' => true,
                'sync' => mining_sync_payload($row, false, '', $nick),
            ];
        }

        $token = mining_sync_new_token();
        $token_esc = addslashes($token);
        $lease_sec = (int)MINING_LEASE_SEC;
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_lease_token = '{$token_esc}',
                mining_lease_until = DATE_ADD(NOW(), INTERVAL {$lease_sec} SECOND)
            WHERE nick = '{$nick_esc}'
              AND (mining_lease_until IS NULL OR mining_lease_until <= NOW())
            LIMIT 1
        ");

        global $conn;
        $got = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        $row = mining_sync_row($nick);
        if (!$got || !$row || !mining_sync_lease_owner($row, $token)) {
            if ($row && mining_sync_lease_active($row)) {
                return [
                    'ok' => true,
                    'type' => 'sync',
                    'active_elsewhere' => true,
                    'sync' => mining_sync_payload($row, false, '', $nick),
                ];
            }
            return ['ok' => false, 'data' => '채굴 세션을 시작할 수 없습니다.'];
        }

        // 리스 획득 후 경과분 확정 (페이지 GET 에서는 생략하고 여기서 처리)
        mining_sync_commit_elapsed($nick);
        $row = mining_sync_row($nick);

        return [
            'ok' => true,
            'type' => 'sync',
            'active_elsewhere' => false,
            'sync' => mining_sync_payload($row ?: [], true, $token, $nick),
        ];
    }
}

if (!function_exists('mining_sync_ping')) {
    function mining_sync_ping($nick, $token) {
        mining_sync_commit_elapsed($nick);
        $row = mining_sync_row($nick);
        if (!$row) {
            return mining_sync_acquire($nick);
        }

        $token = trim((string)$token);
        if ($token !== '' && mining_sync_lease_owner($row, $token)) {
            $token_esc = addslashes($token);
            $lease_sec = (int)MINING_LEASE_SEC;
            $nick_esc = mining_data_nick_esc($nick);
            $tbl = mining_sync_table();
            db_query("
                UPDATE `{$tbl}`
                SET mining_lease_token = '{$token_esc}',
                    mining_lease_until = DATE_ADD(NOW(), INTERVAL {$lease_sec} SECOND)
                WHERE nick = '{$nick_esc}'
                  AND mining_lease_token = '{$token_esc}'
                LIMIT 1
            ");
            $row = mining_sync_row($nick);
            return [
                'ok' => true,
                'type' => 'sync',
                'active_elsewhere' => false,
                'sync' => mining_sync_payload($row, true, $token, $nick),
            ];
        }

        if (mining_sync_lease_active($row)) {
            return [
                'ok' => true,
                'type' => 'sync',
                'active_elsewhere' => true,
                'sync' => mining_sync_payload($row, false, '', $nick),
            ];
        }

        return mining_sync_acquire($nick);
    }
}

if (!function_exists('mining_sync_release')) {
    function mining_sync_release($nick, $token) {
        mining_sync_commit_elapsed($nick);

        $nick_esc = mining_data_nick_esc($nick);
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $row = mining_sync_row($nick);

        return [
            'ok' => true,
            'type' => 'release',
            'sync' => $row ? mining_sync_payload($row, false, '', $nick) : null,
        ];
    }
}

if (!function_exists('mining_sync_read')) {
    function mining_sync_read($nick, $token = '') {
        $row = mining_sync_row($nick);
        if (!$row) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        $is_leader = mining_sync_lease_owner($row, $token);
        return [
            'ok' => true,
            'type' => 'sync',
            'active_elsewhere' => mining_sync_lease_active($row) && !$is_leader,
            'sync' => mining_sync_payload($row, $is_leader, $is_leader ? $token : '', $nick),
        ];
    }
}

if (!function_exists('mining_sync_claim_amount')) {
    /** commit 후 pending 전량 (본방 저장용) */
    function mining_sync_claim_amount($nick, $token = '') {
        $row = mining_sync_commit_elapsed($nick);
        if (!$row) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        return ['ok' => true, 'amount' => mining_pending_round($row['mining_pending'] ?? 0)];
    }
}

if (!function_exists('mining_pending_live_amount')) {
    /** offline 포함 현재 채굴량 (commit 반영) */
    function mining_pending_live_amount($nick): float {
        $row = mining_sync_commit_elapsed($nick);
        if (!$row) {
            return 0.0;
        }
        return mining_pending_round($row['mining_pending'] ?? 0);
    }
}

if (!function_exists('mining_pending_live_fmt')) {
    function mining_pending_live_fmt($nick): string {
        return mining_fmt_pending(mining_pending_live_amount($nick));
    }
}

if (!function_exists('mining_claim_to_newpoint')) {
    /**
     * 채굴량(mining_pending) → tb_member.newpoint(본방냥)
     * FOR UPDATE + 트랜잭션으로 동시 수령(이중 지급) 방지
     *
     * @param float|null $min_amount null이면 MINING_CHAT_CLAIM_MIN
     * @param bool $round_pay_to_int true면 본방냥 지급액을 정수 반올림 (홍보방 `.수령`)
     * @return array<string,mixed>
     */
    function mining_claim_to_newpoint($nick, $min_amount = null, $round_pay_to_int = false) {
        mining_data_ensure_table();

        $nick = trim((string)$nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.', 'claimed' => 0];
        }

        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.', 'claimed' => 0];
        }

        if (!mining_yield_unlocked_for_nick($nick)) {
            $attempts = mining_upgrade_attempts_for_nick($nick);
            $required = mining_upgrade_attempts_required();
            return [
                'ok' => false,
                'data' => "채굴냥은 강화 {$required}회 후 수령할 수 있어요. (현재 {$attempts}/{$required})",
                'claimed' => 0,
            ];
        }

        $min = ($min_amount === null) ? (float)MINING_CHAT_CLAIM_MIN : (float)$min_amount;
        $zero_sql = mining_pending_sql(0.0);
        $tbl = mining_sync_table();
        $member_esc = addslashes($nick);

        global $conn;
        if (!($conn instanceof mysqli)) {
            return ['ok' => false, 'data' => 'DB 연결을 확인할 수 없습니다.', 'claimed' => 0];
        }

        $elapsed_sec = 0;
        $amount = 0.0;
        $pay_amount = 0.0;

        mysqli_begin_transaction($conn);
        try {
            $row = db_select("
                SELECT " . mining_sync_select_sql() . "
                FROM `{$tbl}`
                WHERE nick = '{$nick_esc}'
                LIMIT 1
                FOR UPDATE
            ");
            if (!$row) {
                throw new RuntimeException('회원을 찾을 수 없습니다.');
            }

            if (!mining_yield_unlocked_for_attempts((int)($row['mining_upgrade_attempts'] ?? 0))) {
                $attempts = (int)($row['mining_upgrade_attempts'] ?? 0);
                $required = mining_upgrade_attempts_required();
                throw new RuntimeException("채굴냥은 강화 {$required}회 후 수령할 수 있어요. (현재 {$attempts}/{$required})");
            }

            $sync_at_ts = mining_sync_ts($row['mining_sync_at'] ?? '');
            $elapsed_sec = max(0, mining_sync_now() - $sync_at_ts);
            $sim = mining_sync_simulate_elapsed($row, $nick, $elapsed_sec);
            $amount = mining_pending_round($sim['pending']);

            if ($amount <= 0) {
                throw new RuntimeException('수령할 채굴량이 없어요.');
            }
            if ($amount + 1e-12 < $min) {
                throw new RuntimeException(
                    mining_fmt_pending($min) . '냥 이상 모였을 때 수령할 수 있어요. (현재 ' . mining_fmt_pending($amount) . '냥)'
                );
            }

            $pay_amount = $round_pay_to_int ? (float)round($amount, 0) : $amount;
            if ($pay_amount <= 0) {
                throw new RuntimeException('수령할 채굴량이 없어요.');
            }

            $pay_sql = $round_pay_to_int
                ? (string)(int)$pay_amount
                : mining_pending_sql($pay_amount);
            $tool_lv = max(0, (int)($row['mining_tool'] ?? 0));
            $dur_sql = mining_durability_sql($sim['durability'], $tool_lv);

            db_query("
                UPDATE `{$tbl}`
                SET mining_pending = {$zero_sql},
                    mining_durability = {$dur_sql},
                    mining_sync_at = NOW()
                WHERE nick = '{$nick_esc}'
                LIMIT 1
            ");

            db_query("
                UPDATE tb_member
                SET newpoint = IFNULL(newpoint, 0) + {$pay_sql}
                WHERE name = '{$member_esc}'
                LIMIT 1
            ");
            $member_updated = ((int)mysqli_affected_rows($conn) > 0);
            if (!$member_updated) {
                throw new RuntimeException('회원 정보를 찾을 수 없어요.');
            }

            mysqli_commit($conn);
        } catch (RuntimeException $e) {
            mysqli_rollback($conn);
            $msg = $e->getMessage();
            $pending = ($amount > 0 && $amount + 1e-12 < $min) ? $amount : 0.0;
            $out = ['ok' => false, 'data' => $msg, 'claimed' => 0];
            if ($pending > 0) {
                $out['pending'] = $pending;
            }
            return $out;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            return ['ok' => false, 'data' => '수령 처리에 실패했어요.', 'claimed' => 0];
        }

        if ($elapsed_sec > 0) {
            // 광물 spawn 은 _auto_mining_ore.php 크론 전담
        }

        if (function_exists('지급로그')) {
            지급로그('채굴', $nick, '', 0, $pay_amount);
        }

        $fresh = db_select("SELECT newpoint FROM tb_member WHERE name = '{$member_esc}' LIMIT 1");
        $newpoint = (float)($fresh['newpoint'] ?? 0);
        $claimed_fmt = $round_pay_to_int
            ? number_format((int)$pay_amount)
            : mining_fmt_pending($pay_amount);

        return [
            'ok' => true,
            'claimed' => $pay_amount,
            'claimed_fmt' => $claimed_fmt,
            'newpoint' => $newpoint,
            'data' => '채굴냥 ' . $claimed_fmt . '냥을 본방냥으로 수령했어요.',
        ];
    }
}

if (!function_exists('mining_sync_reset_pending')) {
    function mining_sync_reset_pending($nick) {
        $nick_esc = mining_data_nick_esc($nick);
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = 0,
                mining_sync_at = NOW(),
                mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_sync_pending_totals')) {
    /** 미수령 채굴량 합계 · 인원 */
    function mining_sync_pending_totals(): array {
        mining_data_ensure_table();
        $tbl = mining_sync_table();
        $row = db_select("
            SELECT COUNT(*) AS cnt,
                   COALESCE(SUM(mining_pending), 0) AS total
            FROM `{$tbl}`
            WHERE mining_pending > 0
        ");
        $total = (float)($row['total'] ?? 0);
        return [
            'nicks' => (int)($row['cnt'] ?? 0),
            'total' => mining_pending_round($total),
            'total_fmt' => mining_fmt_pending($total),
        ];
    }
}

if (!function_exists('mining_sync_reset_pending_all')) {
    /**
     * 전 회원 미수령 채굴량(mining_pending) 0 + sync/lease 해제
     * @return array{ok:bool,nicks:int,total:float,total_fmt:string,data:string}
     */
    function mining_sync_reset_pending_all(): array {
        $before = mining_sync_pending_totals();
        $tbl = mining_sync_table();
        db_query("
            UPDATE `{$tbl}`
            SET mining_pending = 0,
                mining_sync_at = NOW(),
                mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE mining_pending <> 0
               OR mining_lease_token IS NOT NULL
        ");
        $nicks = (int)$before['nicks'];
        $fmt = (string)$before['total_fmt'];
        return [
            'ok' => true,
            'nicks' => $nicks,
            'total' => (float)$before['total'],
            'total_fmt' => $fmt,
            'data' => $nicks > 0
                ? "전 회원 채굴량 {$fmt}냥 ({$nicks}명)을 0으로 초기화했어요."
                : '초기화할 채굴량이 없어요.',
        ];
    }
}

if (!function_exists('mining_sync_clear_lease')) {
    function mining_sync_clear_lease($nick): void {
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return;
        }
        $tbl = mining_sync_table();
        @db_query("
            UPDATE `{$tbl}`
            SET mining_lease_token = NULL,
                mining_lease_until = NULL
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_ore_defer_for_퇴근')) {
    /** 퇴근 중 광물 스폰 밀림 (백로그 방지) */
    function mining_ore_defer_for_퇴근($nick): void {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return;
        }
        $ore_cron = __DIR__ . '/mining_ore_cron.inc.php';
        if (is_file($ore_cron)) {
            require_once $ore_cron;
        }
        if (!function_exists('mining_ore_schedule_ensure_columns')) {
            return;
        }
        mining_ore_schedule_ensure_columns();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return;
        }
        $defer_sec = function_exists('mining_ore_window_seconds')
            ? max(60, (int)mining_ore_window_seconds())
            : 3600;
        $next = date('Y-m-d H:i:s', time() + $defer_sec);
        $next_esc = addslashes($next);
        $tbl = defined('MINING_TABLE') ? MINING_TABLE : mining_sync_table();
        @db_query("
            UPDATE `{$tbl}`
            SET mining_ore_next_spawn_at = '{$next_esc}'
            WHERE nick = '{$nick_esc}'
              AND mining_weapon_equipped = 1
              AND mining_ore_next_spawn_at IS NOT NULL
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_pause_on_퇴근')) {
    /**
     * 퇴근 등록 직전 호출 — 현재까지 채굴 확정 후 세션·광물 스폰 정지
     * (tb_work insert / status=3 이전이어야 퇴근 직전분 적립됨)
     */
    function mining_pause_on_퇴근($nick): void {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return;
        }
        mining_data_ensure_table();
        if (!mining_nick_is_퇴근($nick)) {
            mining_sync_commit_elapsed($nick);
        } else {
            $nick_esc = mining_data_nick_esc($nick);
            if ($nick_esc !== '') {
                $tbl = mining_sync_table();
                @db_query("
                    UPDATE `{$tbl}`
                    SET mining_sync_at = NOW()
                    WHERE nick = '{$nick_esc}'
                    LIMIT 1
                ");
            }
        }
        mining_sync_clear_lease($nick);
        mining_ore_defer_for_퇴근($nick);
    }
}

if (!function_exists('mining_resume_on_출근')) {
    /**
     * 출근 직전 호출(tb_work 삭제 전) — 퇴근 기간 미적립 확정 후 sync 시각 갱신
     */
    function mining_resume_on_출근($nick): void {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return;
        }
        mining_data_ensure_table();
        // 아직 퇴근 상태면 simulate 가 0 적립 + sync_at 갱신
        mining_sync_commit_elapsed($nick);
        mining_sync_clear_lease($nick);
        mining_ore_defer_for_퇴근($nick);
    }
}
