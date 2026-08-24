<?php
/**
 * 채굴 게임 설정 (wallet · mining_web 공용)
 */

if (!defined('MINING_BETA_NICK')) {
    /** @deprecated 베타 전용 해제 — 하위 호환용 상수만 유지 */
    define('MINING_BETA_NICK', '');
}
if (!defined('MINING_CHAT_CLAIM_MIN')) {
    /** 채팅 `.수령` — 채굴량 → 본방냥 최소 수령량 */
    define('MINING_CHAT_CLAIM_MIN', '10');
}
if (!defined('MINING_UNLOCK_UPGRADE_ATTEMPTS')) {
    /** 채굴냥 적립·수령 해제 — 강화 시도 누적 횟수 (성공·실패 포함) */
    define('MINING_UNLOCK_UPGRADE_ATTEMPTS', 100);
}
if (!defined('MINING_SAVE_MIN')) {
    /** 웹 수령 버튼 — 채팅 `.수령`과 동일 최소량 */
    define('MINING_SAVE_MIN', MINING_CHAT_CLAIM_MIN);
}
if (!defined('MINING_SECONDS_PER_DAY')) {
    define('MINING_SECONDS_PER_DAY', 86400);
}
/** Lv0 기준 24시간 240냥 (시간당 10냥) */
if (!defined('MINING_FIRST_SAVE_SEC')) {
    define('MINING_FIRST_SAVE_SEC', 86400);
}
/** Lv14(황금 숟가락) 하루(24h) 목표 수확량 — (15+1)×30×24 = 10,800 */
if (!defined('MINING_DAILY_TARGET_MAX')) {
    define('MINING_DAILY_TARGET_MAX', 10800);
}
/** 예전 Lv14 상한(3,000) — 하위 장비 일수확 비례 스케일 기준 */
if (!defined('MINING_DAILY_DESIGN_MAX')) {
    define('MINING_DAILY_DESIGN_MAX', 3000);
}
if (!defined('MINING_RATE_LEVEL0')) {
    define('MINING_RATE_LEVEL0', (string)(10 / 3600)); // Lv0: 시간당 10냥
}
/** @deprecated 구 기하곡선 밸런스 — mining_tool_hourly_yield_count() 사용 */
if (!defined('MINING_RATE_LEGACY_MULT')) {
    define('MINING_RATE_LEGACY_MULT', '30000');
}
/** @deprecated */
if (!defined('MINING_RATE_TOTAL_MULT')) {
    define('MINING_RATE_TOTAL_MULT', (string)((float)MINING_RATE_LEGACY_MULT * (float)MINING_DAILY_TARGET_MAX / (float)MINING_DAILY_DESIGN_MAX));
}
if (!defined('MINING_RATE_SPOON')) {
    define('MINING_RATE_SPOON', MINING_RATE_LEVEL0);
}
if (!defined('MINING_RATE_FORK')) {
    define('MINING_RATE_FORK', (string)(20 / 3600)); // Lv1: 시간당 20냥
}
if (!defined('MINING_RATE_PER_SEC')) {
    define('MINING_RATE_PER_SEC', MINING_RATE_SPOON);
}
/** 강화비: Lv0→1 = 5천, Lv1→2 = 1만, Lv2→3 = 10만 … Lv13→14 = 1경 (1만 × 10^Lv) */
if (!defined('MINING_UPGRADE_COST_FIRST')) {
    define('MINING_UPGRADE_COST_FIRST', 5000);
}
if (!defined('MINING_UPGRADE_COST_STEP')) {
    define('MINING_UPGRADE_COST_STEP', 10000);
}
/** @deprecated Lv13→14 = MINING_UPGRADE_COST_STEP × 10^12 */
if (!defined('MINING_UPGRADE_COST_FINAL')) {
    define('MINING_UPGRADE_COST_FINAL', '10000000000000000'); // 1경
}
/** @deprecated */
if (!defined('MINING_UPGRADE_COST_BASE')) {
    define('MINING_UPGRADE_COST_BASE', MINING_UPGRADE_COST_FIRST);
}
/** @deprecated MINING_UPGRADE_COST — mining_upgrade_cost_for_level() 사용 */
if (!defined('MINING_UPGRADE_COST')) {
    define('MINING_UPGRADE_COST', MINING_UPGRADE_COST_FIRST);
}
if (!defined('MINING_UPGRADE_SUCCESS_NUM')) {
    define('MINING_UPGRADE_SUCCESS_NUM', 1);
}
/** Lv0~2: 0.001% · Lv3~5: 0.0001% · Lv6~9: 0.00001% · Lv10~12: 0.0000001% · Lv13~14: 0.00000001% */
if (!defined('MINING_UPGRADE_SUCCESS_BASE_DENOM')) {
    define('MINING_UPGRADE_SUCCESS_BASE_DENOM', 100000);
}
if (!defined('MINING_UPGRADE_SUCCESS_DENOM')) {
    define('MINING_UPGRADE_SUCCESS_DENOM', MINING_UPGRADE_SUCCESS_BASE_DENOM);
}
if (!defined('MINING_UPGRADE_SUCCESS_PCT')) {
    define('MINING_UPGRADE_SUCCESS_PCT', '0.001%');
}
if (!defined('MINING_UPGRADE_EUNCHONG_DENOM_DIV')) {
    define('MINING_UPGRADE_EUNCHONG_DENOM_DIV', 10);
}
if (!defined('MINING_UPGRADE_EUNCHONG_COST_DIV')) {
    define('MINING_UPGRADE_EUNCHONG_COST_DIV', MINING_UPGRADE_EUNCHONG_DENOM_DIV);
}
if (!defined('MINING_EUNCHONG_YIELD_MULT')) {
    /** 은총 활성 시 채굴 적립 배율 (강화 분모/비용 ÷10 과 별도) */
    define('MINING_EUNCHONG_YIELD_MULT', 100);
}
if (!defined('MINING_UPGRADE_EUNCHONG_PCT')) {
    define('MINING_UPGRADE_EUNCHONG_PCT', '0.01%');
}

if (!function_exists('mining_upgrade_success_base_denom_for_level')) {
    /** 현재 Lv → Lv+1 강화 분모 (분자 1 고정) */
    function mining_upgrade_success_base_denom_for_level($level): int {
        $level = max(0, (int)$level);
        if (function_exists('mining_tool_max_level')) {
            $level = min($level, max(0, (int)mining_tool_max_level()));
        }
        if ($level <= 2) {
            return 100000;           // 0.001%
        }
        if ($level <= 5) {
            return 1000000;          // 0.0001%
        }
        if ($level <= 9) {
            return 10000000;         // 0.00001%
        }
        if ($level <= 12) {
            return 1000000000;       // 0.0000001%
        }
        return 10000000000;          // 0.00000001% (Lv13→14)
    }
}

if (!function_exists('mining_upgrade_success_roll_for_level')) {
    /**
     * 현재 Lv → Lv+1 강화 판정 (분자 1 고정)
     *
     * @return array{num:int,denom:int}
     */
    function mining_upgrade_success_roll_for_level($level, $use_eunchong = false): array {
        $num = max(1, (int)MINING_UPGRADE_SUCCESS_NUM);
        $denom = mining_upgrade_success_base_denom_for_level($level);
        if ($use_eunchong) {
            $div = max(1, (int)MINING_UPGRADE_EUNCHONG_DENOM_DIV);
            $denom = max(1, (int)floor($denom / $div));
        }
        return ['num' => $num, 'denom' => $denom];
    }
}

if (!function_exists('mining_upgrade_success_denom')) {
    function mining_upgrade_success_denom($use_eunchong = false, $level = 0): int {
        return mining_upgrade_success_roll_for_level($level, $use_eunchong)['denom'];
    }
}

if (!function_exists('mining_upgrade_success_pct_str')) {
    function mining_upgrade_success_pct_str($use_eunchong = false, $level = 0): string {
        $roll = mining_upgrade_success_roll_for_level($level, $use_eunchong);
        $denom = (int)$roll['denom'];
        $num = max(1, (int)$roll['num']);
        if ($denom <= 0) {
            return $use_eunchong ? MINING_UPGRADE_EUNCHONG_PCT : MINING_UPGRADE_SUCCESS_PCT;
        }
        $pct = ($num / $denom) * 100;
        if ($pct >= 1) {
            $s = rtrim(rtrim(number_format($pct, 4, '.', ''), '0'), '.');
            return $s . '%';
        }
        if ($pct >= 0.1) {
            $s = rtrim(rtrim(number_format($pct, 3, '.', ''), '0'), '.');
            return $s . '%';
        }
        if ($pct >= 0.01) {
            $s = rtrim(rtrim(number_format($pct, 4, '.', ''), '0'), '.');
            return ($s === '' ? '0' : $s) . '%';
        }
        $s = rtrim(rtrim(number_format($pct, 8, '.', ''), '0'), '.');
        return ($s === '' ? '0' : $s) . '%';
    }
}

if (!function_exists('mining_upgrade_success_hint_str')) {
    /** 화면용: "1/10,000 (0.01%)" */
    function mining_upgrade_success_hint_str($use_eunchong = false, $level = 0): string {
        $roll = mining_upgrade_success_roll_for_level($level, $use_eunchong);
        $pct = mining_upgrade_success_pct_str($use_eunchong, $level);
        return number_format((int)$roll['num']) . '/' . number_format((int)$roll['denom']) . ' (' . $pct . ')';
    }
}

if (!function_exists('mining_daily_cap_scale')) {
    /** Lv14 상한 변경 시 Lv1~ 하위 장비 일수확 비례 (Lv0=0.1 고정) */
    function mining_daily_cap_scale(): float {
        static $scale = null;
        if ($scale !== null) {
            return $scale;
        }
        $design = (float)MINING_DAILY_DESIGN_MAX;
        $max = (float)MINING_DAILY_TARGET_MAX;
        $scale = $design > 0 ? ($max / $design) : 1.0;
        return $scale;
    }
}

if (!function_exists('mining_rate_level_multiplier')) {
    /** Lv→Lv+1 구간 배율 (Lv0→1은 cap_scale만, Lv1+는 legacy step × scale) */
    function mining_rate_level_multiplier(): float {
        static $mult = null;
        if ($mult !== null) {
            return $mult;
        }
        $steps = 14;
        if (function_exists('mining_tool_max_level')) {
            $steps = max(1, (int)mining_tool_max_level());
        }
        $mult = pow((float)MINING_RATE_LEGACY_MULT, 1 / $steps);
        return $mult;
    }
}

if (!function_exists('mining_tool_yield_band_multiplier')) {
    /** Lv 구간 배율 — 시간당 (Lv+1)×배율 냥 */
    function mining_tool_yield_band_multiplier(int $level): int {
        if ($level <= 4) {
            return 10;
        }
        if ($level <= 9) {
            return 15;
        }
        if ($level <= 13) {
            return 20;
        }
        return 30;
    }
}

if (!function_exists('mining_tool_daily_yield_table')) {
    /** Lv → 24h 채굴량(냥) — Lv6~13 고정값, 그 외는 (Lv+1)×구간배율×24 */
    function mining_tool_daily_yield_table(): array {
        return [
            6 => 3480,  // 곡괭이
            7 => 3880,  // 망치
            8 => 4240,  // 공구세트
            9 => 5600,  // 채굴망치
            10 => 6240, // 중장비
            11 => 6720, // 다이아 곡괭이
            12 => 7520, // 레이저 채굴기
            13 => 8620, // UFO 흡입기
        ];
    }
}

if (!function_exists('mining_tool_daily_yield_count')) {
    function mining_tool_daily_yield_count(int $level): int {
        $max_lv = 14;
        if (function_exists('mining_tool_max_level')) {
            $max_lv = max(0, (int)mining_tool_max_level());
        }
        $level = max(0, min($max_lv, $level));
        $table = mining_tool_daily_yield_table();
        if (isset($table[$level])) {
            return (int)$table[$level];
        }
        return ($level + 1) * mining_tool_yield_band_multiplier($level) * 24;
    }
}

if (!function_exists('mining_tool_hourly_yield_count')) {
    /** Lv N 시간당 냥 채굴량(개) — 일일 획득량 ÷ 24 */
    function mining_tool_hourly_yield_count(int $level): int {
        $daily = mining_tool_daily_yield_count($level);
        return (int)round($daily / 24);
    }
}

if (!function_exists('mining_tool_rate_for_level')) {
    function mining_tool_rate_for_level($level): float {
        return mining_tool_daily_yield_count((int)$level) / (float)MINING_SECONDS_PER_DAY;
    }
}

if (!function_exists('mining_tool_daily_yield')) {
    function mining_tool_daily_yield($level): float {
        return (float)mining_tool_daily_yield_count((int)$level);
    }
}

if (!function_exists('mining_tool_hourly_yield')) {
    function mining_tool_hourly_yield($level): float {
        return mining_tool_daily_yield_count((int)$level) / 24.0;
    }
}

if (!function_exists('mining_upgrade_cost_fmt')) {
    function mining_upgrade_cost_fmt($cost) {
        $cost = (int)$cost;
        if ($cost >= 100000000 && function_exists('냥_경조억_축약문구')) {
            return 냥_경조억_축약문구($cost, '', ' ');
        }
        if (function_exists('mining_fmt_game')) {
            return mining_fmt_game($cost);
        }
        return number_format($cost);
    }
}

if (!function_exists('mining_upgrade_cost_for_level')) {
    /** @param int $level 현재 장비 Lv → Lv+1 강화 비용 (0→1=5천, 1→2=1만, 2→3=10만 …) */
    function mining_upgrade_cost_for_level($level, $use_eunchong = false): int {
        $level = max(0, (int)$level);
        $max = 13;
        if (function_exists('mining_tool_max_level')) {
            $max = max(0, (int)mining_tool_max_level());
        }
        $level = min($level, $max);
        if ($level === 0) {
            $base = (int)MINING_UPGRADE_COST_FIRST;
        } else {
            $base = (int)MINING_UPGRADE_COST_STEP * (int)pow(10, $level - 1);
        }
        if (!$use_eunchong) {
            return $base;
        }
        $div = max(1, (int)MINING_UPGRADE_EUNCHONG_COST_DIV);
        return max(1, (int)floor($base / $div));
    }
}

if (!function_exists('mining_upgrade_cost_table')) {
    /** @return list<array{from_level:int,to_level:int,from_label:string,to_label:string,cost:int,cost_fmt:string}> */
    function mining_upgrade_cost_table() {
        if (!function_exists('mining_tool_base_defs')) {
            return [];
        }
        $defs = mining_tool_base_defs();
        $rows = [];
        $max = count($defs) - 1;
        for ($lv = 0; $lv < $max; $lv++) {
            $cost = mining_upgrade_cost_for_level($lv);
            $rows[] = [
                'from_level' => $lv,
                'to_level' => $lv + 1,
                'from_label' => $defs[$lv]['label'] ?? '',
                'to_label' => $defs[$lv + 1]['label'] ?? '',
                'cost' => $cost,
                'cost_fmt' => mining_upgrade_cost_fmt($cost),
            ];
        }
        return $rows;
    }
}

if (!function_exists('mining_eunchong_yield_mult')) {
    function mining_eunchong_yield_mult($use_eunchong = false): float {
        return $use_eunchong ? (float)max(1, (int)MINING_EUNCHONG_YIELD_MULT) : 1.0;
    }
}

if (!function_exists('mining_eunchong_active_cache_ref')) {
    function &mining_eunchong_active_cache_ref(): array {
        static $cache = [];
        return $cache;
    }
}

if (!function_exists('mining_eunchong_reset_active_cache')) {
    function mining_eunchong_reset_active_cache($nick = ''): void {
        $cache = &mining_eunchong_active_cache_ref();
        $nick = trim((string)$nick);
        if ($nick === '') {
            $cache = [];
            return;
        }
        unset($cache[$nick]);
    }
}

if (!function_exists('mining_eunchong_active_for_nick')) {
    function mining_eunchong_active_for_nick($nick): bool {
        $cache = &mining_eunchong_active_cache_ref();
        $nick = trim((string)$nick);
        if ($nick === '') {
            return false;
        }
        if (array_key_exists($nick, $cache)) {
            return $cache[$nick];
        }
        $state = mining_eunchong_state_for_nick($nick);
        $cache[$nick] = !empty($state['active']);
        return $cache[$nick];
    }
}

if (!function_exists('mining_eunchong_active')) {
    function mining_eunchong_active($은총값): bool {
        if (function_exists('강화_은총_활성')) {
            return 강화_은총_활성($은총값);
        }
        return !empty($은총값) && (strtotime((string)$은총값) > time());
    }
}

if (!function_exists('mining_eunchong_state_from_values')) {
    /** @return array{cnt:int,active:bool,end:string,left_sec:int} */
    function mining_eunchong_state_from_values($cnt, $은총_end = ''): array {
        $cnt = max(0, (int)$cnt);
        $end = trim((string)$은총_end);
        $active = mining_eunchong_active($end);
        $left = ($active && $end !== '') ? max(0, strtotime($end) - time()) : 0;
        return [
            'cnt' => $cnt,
            'active' => $active,
            'end' => $end,
            'left_sec' => $left,
        ];
    }
}

if (!function_exists('mining_eunchong_state_for_nick')) {
    /** @return array{cnt:int,active:bool,end:string,left_sec:int} */
    function mining_eunchong_state_for_nick($nick): array {
        if (!function_exists('db_select')) {
            return mining_eunchong_state_from_values(0);
        }
        $nick_esc = addslashes(trim((string)$nick));
        if ($nick_esc === '') {
            return mining_eunchong_state_from_values(0);
        }
        $row = db_select("SELECT IFNULL(은총개수, 0) AS cnt, 은총 FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($row)) {
            return mining_eunchong_state_from_values(0);
        }
        return mining_eunchong_state_from_values($row['cnt'] ?? 0, $row['은총'] ?? '');
    }
}

if (!function_exists('mining_eunchong_payload')) {
    function mining_eunchong_payload($nick): array {
        $state = mining_eunchong_state_for_nick($nick);
        return [
            'eunchong_cnt' => $state['cnt'],
            'eunchong_active' => $state['active'] ? 1 : 0,
            'eunchong_end' => $state['end'],
            'eunchong_left_sec' => $state['left_sec'],
        ];
    }
}

if (!function_exists('mining_use_eunchong_execute')) {
    function mining_use_eunchong_execute($nick) {
        wallet_odd_even_includes();

        $nick = trim((string)$nick);
        $nick_esc = addslashes($nick);
        if ($nick_esc === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }

        $member = db_select("SELECT IFNULL(은총개수, 0) AS cnt, 은총 FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($member)) {
            return ['ok' => false, 'data' => '회원을 찾을 수 없습니다.'];
        }

        $보유 = (int)($member['cnt'] ?? 0);
        if ($보유 < 1) {
            return ['ok' => false, 'data' => '은총이 부족해요. (보유 0개)'];
        }

        $rs = db_query("
            UPDATE tb_member
            SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - 1, 0),
                은총 = CASE
                    WHEN 은총 IS NOT NULL AND 은총 > NOW() THEN DATE_ADD(은총, INTERVAL 5 MINUTE)
                    ELSE DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                END
            WHERE name = '{$nick_esc}'
              AND IFNULL(은총개수, 0) >= 1
            LIMIT 1
        ");
        global $conn;
        $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : (bool)$rs;
        if (!$applied) {
            return ['ok' => false, 'data' => '은총 적용에 실패했어요.'];
        }

        $state = mining_eunchong_state_for_nick($nick);
        mining_eunchong_reset_active_cache($nick);
        $tool_lv = 0;
        if (function_exists('mining_tool_level_for_nick')) {
            $tool_lv = max(0, (int)mining_tool_level_for_nick($nick));
        }
        $기본분모 = mining_upgrade_success_denom(false, $tool_lv);
        $은총분모 = mining_upgrade_success_denom(true, $tool_lv);
        $기본비용 = mining_upgrade_cost_for_level($tool_lv, false);
        $은총비용 = mining_upgrade_cost_for_level($tool_lv, true);
        $종료표시 = $state['end'] !== '' ? date('H:i:s', strtotime($state['end'])) : '-';
        $msg = '✨ 은총 적용! 5분간 강화·채굴 버프';
        $msg .= "\n성공분모 " . number_format($기본분모) . ' → ' . number_format($은총분모);
        $msg .= ' (' . mining_upgrade_success_pct_str(false, $tool_lv) . ' → ' . mining_upgrade_success_pct_str(true, $tool_lv) . ')';
        $msg .= "\n강화비용 " . mining_upgrade_cost_fmt($기본비용) . '냥 → ' . mining_upgrade_cost_fmt($은총비용) . '냥';
        $msg .= "\n채굴량 x" . (int)MINING_EUNCHONG_YIELD_MULT;
        $msg .= "\n종료: {$종료표시} · 남은 은총 {$state['cnt']}개";

        if (!function_exists('지급로그')) {
            if (!defined('WALLET_LIB_ONLY')) {
                define('WALLET_LIB_ONLY', true);
            }
            require_once __DIR__ . '/wallet_web.php';
        }
        if (function_exists('지급로그')) {
            $tool_label = 'Lv' . $tool_lv;
            if (function_exists('mining_tool_def')) {
                $tool_def = mining_tool_def($tool_lv);
                $tool_label = (string)($tool_def['label'] ?? $tool_label);
            }
            if (function_exists('wallet_api_load')) {
                wallet_api_load($nick, false);
            }
            $log_receiver = $tool_label
                . '·종료' . ($state['end'] !== '' ? date('Y-m-d H:i:s', strtotime($state['end'])) : '-')
                . '·남은' . (int)$state['cnt'] . '개';
            지급로그('채굴은총사용', $nick, $log_receiver, 0, 1);
        }

        return array_merge([
            'ok' => true,
            'data' => $msg,
        ], mining_eunchong_payload($nick));
    }
}

if (!function_exists('mining_eunchong_count_for_nick')) {
    function mining_eunchong_count_for_nick($nick): int {
        return mining_eunchong_state_for_nick($nick)['cnt'];
    }
}
if (!defined('MINING_LEASE_SEC')) {
    define('MINING_LEASE_SEC', 90);
}
if (!defined('MINING_SYNC_PING_SEC')) {
    define('MINING_SYNC_PING_SEC', 45);
}
if (!defined('MINING_SYNC_POLL_SEC')) {
    define('MINING_SYNC_POLL_SEC', 30);
}
if (!defined('MINING_UPGRADE_BTN_LAYOUT')) {
    /** 강화하기 버튼 배치: 'fixed'(가운데 고정) | 'random'(클릭마다 랜덤 — 매크로 방지) */
    define('MINING_UPGRADE_BTN_LAYOUT', 'fixed');
}
if (!defined('MINING_UPGRADE_BTN_MOVE_EVERY')) {
    /** fixed 모드 — 강화하기 N회 클릭마다 버튼 위치 랜덤 변경 (0=비활성) */
    define('MINING_UPGRADE_BTN_MOVE_EVERY', 0);
}
if (!defined('MINING_UPGRADE_BATCH_1000_TIMES')) {
    define('MINING_UPGRADE_BATCH_1000_TIMES', 1000);
}
if (!defined('MINING_UPGRADE_BATCH_1000_COST')) {
    /** 1000회 강화 버튼 — 본방냥 추가 차감량 (게임냥은 회당 강화비 × 시도 횟수) */
    define('MINING_UPGRADE_BATCH_1000_COST', 3);
}
if (!defined('MINING_PENDING_SCALE')) {
    define('MINING_PENDING_SCALE', 10);
}

if (!function_exists('mining_pending_round')) {
    function mining_pending_round($n): float {
        $scale = max(0, (int)MINING_PENDING_SCALE);
        $v = round(max(0, (float)$n), $scale);
        return $v < 0 ? 0.0 : $v;
    }
}

if (!function_exists('mining_pending_sql')) {
    function mining_pending_sql($n): string {
        return sprintf('%.' . max(0, (int)MINING_PENDING_SCALE) . 'f', mining_pending_round($n));
    }
}

if (!function_exists('mining_fmt_pending')) {
    function mining_fmt_pending($n) {
        $s = sprintf('%.10f', mining_pending_round($n));
        $s = rtrim(rtrim($s, '0'), '.');
        if ($s === '' || $s === '-0') {
            return '0';
        }
        return $s;
    }
}

if (!function_exists('mining_access_allowed')) {
    function mining_access_allowed($nick) {
        return trim((string)$nick) !== '';
    }
}

if (!function_exists('mining_access_denied_msg')) {
    function mining_access_denied_msg() {
        return '채굴을 이용할 수 없어요. 접속 코드를 확인해 주세요.';
    }
}
