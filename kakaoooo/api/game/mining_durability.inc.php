<?php
/**
 * 채굴 장비 내구도 — 장비(레벨)별 최대 · 시간 마모 · STOP_PCT 이하면 채굴 정지
 * 수리 기본 100/회(최대 1000)
 */

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_storage.inc.php';

if (!function_exists('mining_durability_max_map')) {
    /**
     * 장비 Lv별 최대 내구도
     * @return array<int,int>
     */
    function mining_durability_max_map(): array {
        return [
            0 => 120,   // 숟가락
            1 => 150,   // 포크
            2 => 180,   // 젓가락
            3 => 225,   // 식칼
            4 => 255,   // 냄비
            5 => 285,   // 프라이팬
            6 => 330,   // 곡괭이
            7 => 375,   // 망치
            8 => 420,   // 공구세트
            9 => 480,   // 채굴망치
            10 => 555,  // 중장비
            11 => 630,  // 다이아 곡괭이
            12 => 720,  // 레이저 채굴기
            13 => 840,  // UFO 흡입기
            14 => 960,  // 황금 숟가락
        ];
    }
}

if (!function_exists('mining_durability_max_ceiling')) {
    /** 전 장비 중 최대 천장 (SQL 클램프용) */
    function mining_durability_max_ceiling(): float {
        $map = mining_durability_max_map();
        $hi = max(1, (int)MINING_DURABILITY_MAX);
        foreach ($map as $v) {
            $hi = max($hi, (int)$v);
        }
        return (float)$hi;
    }
}

if (!function_exists('mining_durability_ensure_column')) {
    function mining_durability_ensure_column(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        mining_data_ensure_table();
        $tbl = MINING_TABLE;
        $max = (float)MINING_DURABILITY_MAX;
        $scale = max(0, (int)MINING_DURABILITY_SCALE);
        $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_durability'");
        if (empty($exists)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                ADD COLUMN `mining_durability` DECIMAL(12,{$scale}) NOT NULL DEFAULT {$max}
                COMMENT '채굴 장비 내구도 (장비별 최대)'
                AFTER `mining_upgrade_attempts`
            ");
        }

        // 구형 공통 max=100 → 장비별 최대 비율 환산
        // ver1: 1배 맵 · ver2: 3배 맵(현재)
        $verCol = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_dur_scale_ver'");
        if (empty($verCol)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                ADD COLUMN `mining_dur_scale_ver` TINYINT UNSIGNED NOT NULL DEFAULT 0
                COMMENT '내구도 스케일 버전 (2=장비별×3)'
                AFTER `mining_durability`
            ");
        }
        $cases = [];
        foreach (mining_durability_max_map() as $lv => $mx) {
            $cases[] = 'WHEN ' . (int)$lv . ' THEN ' . (int)$mx;
        }
        $caseSql = implode(' ', $cases);
        $fallback = (int)MINING_DURABILITY_MAX;
        $verExists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_dur_scale_ver'");
        if (!empty($verExists)) {
            // 아직 구 100 절대값 → 현재(3배) 맵으로 바로 환산
            @db_query("
                UPDATE `{$tbl}`
                SET mining_durability = LEAST(
                      CASE mining_tool {$caseSql} ELSE {$fallback} END,
                      GREATEST(0, mining_durability) * (CASE mining_tool {$caseSql} ELSE {$fallback} END) / 100
                    ),
                    mining_dur_scale_ver = 2
                WHERE IFNULL(mining_dur_scale_ver, 0) < 1
            ");
            // 이미 1배 장비별 맵(ver1) → ×3
            @db_query("
                UPDATE `{$tbl}`
                SET mining_durability = LEAST(
                      CASE mining_tool {$caseSql} ELSE {$fallback} END,
                      GREATEST(0, mining_durability) * 3
                    ),
                    mining_dur_scale_ver = 2
                WHERE IFNULL(mining_dur_scale_ver, 0) = 1
            ");
        }
    }
}

if (!function_exists('mining_durability_max')) {
    /**
     * @param int|null $level 장비 레벨. null이면 기본(MINING_DURABILITY_MAX)
     */
    function mining_durability_max(?int $level = null): float {
        if ($level === null) {
            return max(1.0, (float)MINING_DURABILITY_MAX);
        }
        $level = max(0, (int)$level);
        $map = mining_durability_max_map();
        if (isset($map[$level])) {
            return max(1.0, (float)$map[$level]);
        }
        $keys = array_keys($map);
        if ($keys === []) {
            return max(1.0, (float)MINING_DURABILITY_MAX);
        }
        $hi = max($keys);
        if ($level > $hi) {
            return max(1.0, (float)$map[$hi]);
        }
        return max(1.0, (float)MINING_DURABILITY_MAX);
    }
}

if (!function_exists('mining_durability_stop_pct')) {
    function mining_durability_stop_pct(): int {
        return max(0, min(100, (int)MINING_DURABILITY_STOP_PCT));
    }
}

if (!function_exists('mining_durability_stop_floor')) {
    /** 채굴 정지 하한 내구값 (이 값 이하면 적립·마모 중단) */
    function mining_durability_stop_floor(?int $level = null): float {
        $max = mining_durability_max($level);
        return $max * (mining_durability_stop_pct() / 100.0);
    }
}

if (!function_exists('mining_durability_is_stopped')) {
    function mining_durability_is_stopped($dur, ?int $level = null): bool {
        return (float)$dur <= mining_durability_stop_floor($level) + 1e-9;
    }
}

if (!function_exists('mining_durability_level_from_row')) {
    function mining_durability_level_from_row(array $row): int {
        return max(0, (int)($row['mining_tool'] ?? 0));
    }
}

if (!function_exists('mining_durability_from_row')) {
    function mining_durability_from_row(array $row): float {
        $level = mining_durability_level_from_row($row);
        $max = mining_durability_max($level);
        if (!array_key_exists('mining_durability', $row) || $row['mining_durability'] === null || $row['mining_durability'] === '') {
            return $max;
        }
        $v = (float)$row['mining_durability'];
        if ($v < 0) {
            return 0.0;
        }
        if ($v > $max) {
            return $max;
        }
        return $v;
    }
}

if (!function_exists('mining_durability_sql')) {
    /**
     * @param float|int|string $v
     * @param int|null $level 지정 시 해당 장비 최대로 클램프
     */
    function mining_durability_sql($v, ?int $level = null): string {
        $scale = max(0, (int)MINING_DURABILITY_SCALE);
        $n = (float)$v;
        if ($n < 0) {
            $n = 0.0;
        }
        $max = ($level !== null) ? mining_durability_max($level) : mining_durability_max_ceiling();
        if ($n > $max) {
            $n = $max;
        }
        return number_format($n, $scale, '.', '');
    }
}

if (!function_exists('mining_durability_wear_per_hour')) {
    /** Lv별 시간당 마모량 */
    function mining_durability_wear_per_hour(int $level): float {
        $level = max(0, $level);
        if ($level <= 2) {
            return 2.5;
        }
        if ($level <= 5) {
            return 4.7;
        }
        if ($level <= 9) {
            return 7.0;
        }
        if ($level <= 12) {
            return 9.5;
        }
        return 14.0;
    }
}

if (!function_exists('mining_durability_wear_per_sec')) {
    function mining_durability_wear_per_sec(int $level, bool $eunchong_active = false): float {
        $per_hour = mining_durability_wear_per_hour($level);
        $per_sec = $per_hour / 3600.0;
        if ($eunchong_active) {
            $per_sec *= max(1.0, (float)MINING_DURABILITY_EUNCHONG_WEAR_MULT);
        }
        return max(0.0, $per_sec);
    }
}

if (!function_exists('mining_durability_display_int')) {
    /** UI/채팅용 정수 내구 */
    function mining_durability_display_int($dur, ?int $level = null): int {
        $d = (float)$dur;
        if ($d <= 1e-9) {
            return 0;
        }
        $n = (int)floor($d + 1e-9);
        if ($n < 1 && $d > 0) {
            $n = 1;
        }
        $cap = ($level !== null) ? (int)mining_durability_max($level) : (int)mining_durability_max_ceiling();
        return max(0, min($cap, $n));
    }
}

if (!function_exists('mining_durability_repair_unit_cost')) {
    /**
     * 내구 1당 본방냥 (표시용 · 소수 가능)
     * @return string
     */
    function mining_durability_repair_unit_cost() {
        $raw = mining_durability_repair_raw_unit();
        if ($raw === '' || (function_exists('bccomp') ? bccomp($raw, '0', 8) <= 0 : (float)$raw <= 0)) {
            return '0.1';
        }
        if (function_exists('bcadd')) {
            return bcadd($raw, '0', 4);
        }
        return number_format((float)$raw, 4, '.', '');
    }
}

if (!function_exists('mining_durability_repair_raw_unit')) {
    /** 내구 1당 단가 (고정 FLAT 우선, 아니면 본방총합×PCT%) */
    function mining_durability_repair_raw_unit(): string {
        $flat = defined('MINING_DURABILITY_REPAIR_FLAT_NP')
            ? (float)MINING_DURABILITY_REPAIR_FLAT_NP
            : 0.0;
        if ($flat > 0) {
            return function_exists('bcadd')
                ? bcadd(sprintf('%.8F', $flat), '0', 8)
                : (string)$flat;
        }

        $pct = (float)MINING_DURABILITY_REPAIR_BONNYANG_PCT;
        if ($pct <= 0) {
            $pct = 0.01;
        }
        $total = '0';
        if (function_exists('mining_tool_golden_np_total_str')) {
            $total = mining_tool_golden_np_total_str();
        } elseif (function_exists('시세기준_본방냥')) {
            $total = (string)(int)floor((float)시세기준_본방냥());
        }
        if ($total === '' || $total === '0') {
            return '0';
        }
        if (function_exists('bcmul')) {
            return bcmul($total, sprintf('%.16F', $pct / 100.0), 16);
        }
        return (string)((float)$total * ($pct / 100.0));
    }
}

if (!function_exists('mining_durability_repair_cost_for_points')) {
    /**
     * 수리량 × 내구1당 0.1냥
     * @return string 본방냥 (예: 1→0.1, 100→10, 375→37.5)
     */
    function mining_durability_repair_cost_for_points(int $points) {
        $points = max(0, $points);
        if ($points <= 0) {
            return '0';
        }
        $raw_unit = mining_durability_repair_raw_unit();
        if ($raw_unit === '' || (function_exists('bccomp') ? bccomp($raw_unit, '0', 8) <= 0 : (float)$raw_unit <= 0)) {
            $raw_unit = '0.1';
        }
        if (function_exists('bcmul') && function_exists('bcadd')) {
            return bcadd(bcmul($raw_unit, (string)$points, 8), '0', 4);
        }
        return number_format((float)$raw_unit * $points, 4, '.', '');
    }
}

if (!function_exists('mining_durability_repair_fmt')) {
    /** 본냥 수리비 표시 (내구1당 0.1 등 소수 유지) */
    function mining_durability_repair_fmt($cost): string {
        $s = trim((string)$cost);
        if ($s === '') {
            return '0';
        }
        if (function_exists('bcadd')) {
            // 불필요한 Trailing 0 제거하되 소수 1자리까지는 살림 (0.1, 37.5)
            $n = bcadd($s, '0', 4);
            $n = rtrim(rtrim($n, '0'), '.');
            if ($n === '' || $n === '-') {
                $n = '0';
            }
            if (strpos($n, '.') === false && function_exists('냥_숫자콤마')) {
                return 냥_숫자콤마($n);
            }
            $parts = explode('.', $n, 2);
            $intPart = $parts[0];
            $neg = '';
            if (isset($intPart[0]) && $intPart[0] === '-') {
                $neg = '-';
                $intPart = substr($intPart, 1);
            }
            $intFmt = function_exists('냥_숫자콤마')
                ? 냥_숫자콤마($intPart)
                : number_format((float)$intPart, 0, '.', ',');
            if (count($parts) === 2) {
                return $neg . $intFmt . '.' . $parts[1];
            }
            return $neg . $intFmt;
        }
        $f = (float)$s;
        if (abs($f - round($f)) < 1e-9) {
            return number_format((int)round($f), 0, '.', ',');
        }
        return rtrim(rtrim(number_format($f, 4, '.', ','), '0'), '.');
    }
}

if (!function_exists('mining_durability_newpoint_enough')) {
    function mining_durability_newpoint_enough($have, $need): bool {
        if (function_exists('bccomp')) {
            return bccomp((string)$have, (string)$need, 4) >= 0;
        }
        return ((float)$have + 1e-9) >= (float)$need;
    }
}

if (!function_exists('mining_durability_restore_full')) {
    function mining_durability_restore_full($nick): bool {
        mining_durability_ensure_column();
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return false;
        }
        $tbl = MINING_TABLE;
        $row = @db_select("SELECT mining_tool FROM `{$tbl}` WHERE nick = '{$nick_esc}' LIMIT 1");
        $level = max(0, (int)($row['mining_tool'] ?? 0));
        $max_sql = mining_durability_sql(mining_durability_max($level), $level);
        db_query("
            UPDATE `{$tbl}`
            SET mining_durability = {$max_sql}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        return true;
    }
}

if (!function_exists('mining_durability_payload')) {
    /**
     * @return array<string,mixed>
     */
    function mining_durability_payload(array $row, $nick = '', ?float $dur_override = null): array {
        $nick = trim((string)$nick);
        $level = mining_durability_level_from_row($row);
        $max = mining_durability_max($level);
        $dur = $dur_override !== null ? max(0.0, min($max, $dur_override)) : mining_durability_from_row($row);
        $eunchong = ($nick !== '' && function_exists('mining_eunchong_active_for_nick'))
            ? mining_eunchong_active_for_nick($nick)
            : false;
        $unlocked = mining_yield_unlocked_for_attempts((int)($row['mining_upgrade_attempts'] ?? 0));
        $stopped = mining_durability_is_stopped($dur, $level);
        $floor = mining_durability_stop_floor($level);
        $wear = ($unlocked && !$stopped)
            ? mining_durability_wear_per_sec($level, $eunchong)
            : 0.0;
        $pct = $max > 0 ? max(0.0, min(100.0, ($dur / $max) * 100.0)) : 0.0;
        $disp = mining_durability_display_int($dur, $level);
        $max_i = (int)$max;
        $need = max(0, $max_i - $disp);
        $step = max(1, (int)MINING_DURABILITY_REPAIR_STEP);
        $step_cap = max($step, (int)MINING_DURABILITY_REPAIR_MAX);
        $step_amt = min($need, $step, $step_cap);
        $unit = mining_durability_repair_unit_cost();
        $full_cost = mining_durability_repair_cost_for_points($need);
        $step_cost = mining_durability_repair_cost_for_points($step_amt);
        $eta_sec = ($wear > 0 && $dur > $floor) ? (int)floor(($dur - $floor) / $wear) : 0;
        $stop_pct = mining_durability_stop_pct();
        $unit_fmt = mining_durability_repair_fmt($unit);
        $full_fmt = mining_durability_repair_fmt($full_cost);
        $step_fmt = mining_durability_repair_fmt($step_cost);

        return [
            'durability' => $dur,
            'durability_display' => $disp,
            'durability_max' => $max_i,
            'durability_pct' => round($pct, 2),
            'durability_broken' => ($dur <= 1e-9) ? 1 : 0,
            'durability_stopped' => $stopped ? 1 : 0,
            'stop_pct' => $stop_pct,
            'wear_per_sec' => $wear,
            'wear_per_hour' => mining_durability_wear_per_hour($level) * ($eunchong ? max(1.0, (float)MINING_DURABILITY_EUNCHONG_WEAR_MULT) : 1.0),
            'eta_sec' => $eta_sec,
            'repair_need' => $need,
            'repair_step' => $step,
            'repair_max' => $step_cap,
            'repair_step_amount' => $step_amt,
            'repair_currency' => 'newpoint',
            'repair_unit_cost' => $unit_fmt,
            'repair_unit_cost_fmt' => $unit_fmt,
            'repair_step_cost' => $step_fmt,
            'repair_step_cost_fmt' => $step_fmt,
            'repair_full_cost' => $full_fmt,
            'repair_full_cost_fmt' => $full_fmt,
            'warn_pct' => (int)MINING_DURABILITY_WARN_PCT,
        ];
    }
}

if (!function_exists('mining_repair_execute')) {
    /**
     * 채굴 장비 수리 (본방냥 · 내구1당 고정 0.1냥)
     * null → 기본 100 · 지정 시 최대 1000 · 잔여 need 이하
     * @param int|null $points null이면 기본 STEP, 숫자면 해당 내구만큼
     * @return array<string,mixed>
     */
    function mining_repair_execute($nick, $points = null) {
        mining_durability_ensure_column();
        if (function_exists('wallet_odd_even_includes')) {
            wallet_odd_even_includes();
        }

        $nick = trim((string)$nick);
        $nick_esc = mining_data_nick_esc($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }
        if (!mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => '채굴 정보를 준비할 수 없습니다.'];
        }

        if (function_exists('mining_sync_commit_elapsed')) {
            mining_sync_commit_elapsed($nick);
        }

        $tbl = MINING_TABLE;
        $row = db_select("
            SELECT mining_tool, mining_pending, mining_sync_at,
                   mining_lease_token, mining_lease_until,
                   IFNULL(mining_upgrade_attempts, 0) AS mining_upgrade_attempts,
                   mining_durability
            FROM `{$tbl}`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        if (!$row) {
            return ['ok' => false, 'data' => '채굴 정보를 찾을 수 없습니다.'];
        }

        $level = mining_durability_level_from_row($row);
        $max = mining_durability_max($level);
        $max_i = (int)$max;
        $cur = mining_durability_from_row($row);
        $cur_i = mining_durability_display_int($cur, $level);
        if ($cur_i >= $max_i) {
            return [
                'ok' => false,
                'data' => '이미 내구도가 최대예요. (' . $max_i . '/' . $max_i . ')',
                'durability' => mining_durability_payload($row, $nick, $cur),
            ];
        }

        $need = max(0, $max_i - $cur_i);
        $step = max(1, (int)MINING_DURABILITY_REPAIR_STEP);
        $cap = max($step, (int)MINING_DURABILITY_REPAIR_MAX);
        if ($points === null) {
            $charge = min($need, $step, $cap);
        } else {
            $req = (int)$points;
            if ($req < 1) {
                return ['ok' => false, 'data' => "수리량은 1 이상으로 입력해주세요. 예) .채굴수리 {$step}"];
            }
            if ($req > $cap) {
                return ['ok' => false, 'data' => "한 번에 최대 {$cap}까지 수리할 수 있어요."];
            }
            $charge = min($need, $req, $cap);
        }
        if ($charge < 1) {
            return ['ok' => false, 'data' => '수리할 내구도가 없어요.'];
        }

        $cost = mining_durability_repair_cost_for_points($charge);
        $cost_fmt = mining_durability_repair_fmt($cost);
        $member = db_select("SELECT CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (!$member) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }
        $have = (string)($member['newpoint'] ?? '0');
        if (!mining_durability_newpoint_enough($have, $cost)) {
            $have_fmt = mining_durability_repair_fmt($have);
            return [
                'ok' => false,
                'data' => '본냥이 부족해요. (필요 ' . $cost_fmt . ' · 보유 ' . $have_fmt . ')',
            ];
        }

        $cost_sql = function_exists('bcadd')
            ? bcadd($cost, '0', 4)
            : number_format((float)$cost, 4, '.', '');
        // 정수 본냥이어도 DECIMAL 차감 허용
        if (function_exists('bcadd') && strpos($cost_sql, '.') === false) {
            $cost_sql = bcadd($cost_sql, '0', 0);
        }
        db_query("
            UPDATE tb_member
            SET newpoint = newpoint - {$cost_sql}
            WHERE name = '{$nick_esc}'
              AND newpoint >= {$cost_sql}
            LIMIT 1
        ");
        global $conn;
        $paid = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$paid) {
            return ['ok' => false, 'data' => '수리 처리에 실패했어요. 본냥을 확인해주세요.'];
        }

        $new_dur = min($max, $cur + (float)$charge);
        if ($charge >= $need) {
            $new_dur = $max;
        }
        $dur_sql = mining_durability_sql($new_dur, $level);
        db_query("
            UPDATE `{$tbl}`
            SET mining_durability = {$dur_sql},
                mining_sync_at = NOW()
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");

        $after_row = db_select("SELECT CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        $newpoint_after = (string)($after_row['newpoint'] ?? '0');
        if (function_exists('지급로그')) {
            지급로그('채굴수리', $nick, '내구+' . $charge, 0, $cost_fmt);
        }

        $row['mining_durability'] = $new_dur;
        $new_i = mining_durability_display_int($new_dur, $level);
        $msg = "🔧 채굴 장비 수리 완료!\n내구도 {$cur_i} → {$new_i} / {$max_i}\n본냥 {$cost_fmt} 차감";

        $out = [
            'ok' => true,
            'data' => $msg,
            'repaired' => $charge,
            'newpoint' => $newpoint_after,
            'newpoint_fmt' => mining_durability_repair_fmt($newpoint_after),
            'durability' => mining_durability_payload($row, $nick, $new_dur),
        ];
        if (function_exists('mining_sync_read')) {
            $sync = mining_sync_read($nick);
            if (!empty($sync['sync'])) {
                $out['sync'] = $sync['sync'];
            }
        }
        return $out;
    }
}
