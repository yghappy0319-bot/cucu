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
/** Lv14(황금 숟가락) — 본방냥 총합 1.25%/24h (동적). 하위 호환용 상수만 유지 */
if (!defined('MINING_DAILY_TARGET_MAX')) {
    define('MINING_DAILY_TARGET_MAX', 9999);
}
if (!defined('MINING_BASE_PCT_RATIO')) {
    /** 소요시간 환산 기준 = 본방냥 0.3% (일일 비율과 별개) */
    define('MINING_BASE_PCT_RATIO', 0.003); // 0.3%
}
if (!defined('MINING_GOLDEN_DAILY_NP_RATIO')) {
    /** 황금 숟가락 24h 채굴량 = 본방냥(newpoint) 총합 × 1.25% */
    define('MINING_GOLDEN_DAILY_NP_RATIO', 0.0125); // 1.25% (기존 2.5%의 절반)
}
if (!defined('MINING_CHAT_YIELD_MULT_DEFAULT')) {
    /** 오늘 생타 100 미만 기본 채굴 배율 */
    define('MINING_CHAT_YIELD_MULT_DEFAULT', 0.1);
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
/**
 * 강화비 = ceil(채굴유효시총 × 비율)
 * 비율 = mining_upgrade_cost_ratio_parts_table() / MINING_UPGRADE_COST_RATIO_DEN
 * (채굴유효시총 = 무기 소프트캡 + 깎인분의 30% · 무기보다 약간 비쌈)
 */
if (!defined('MINING_UPGRADE_COST_RATIO_DEN')) {
    /** 비율 분모 — cost = ceil(시총 × parts / DEN) */
    define('MINING_UPGRADE_COST_RATIO_DEN', '100000000000000000000'); // 1e20
}
/** @deprecated 구 설계총량 — 비율표로 대체 · 시총 0 폴백만 (옛 9경 단위 금지) */
if (!defined('MINING_UPGRADE_COST_DESIGN_TOTAL')) {
    define('MINING_UPGRADE_COST_DESIGN_TOTAL', '413635915'); // ~4.1억 · 시총 폴백
}
/** @deprecated 고정 강화비 — mining_upgrade_cost_for_level() 동적 비율 사용 */
if (!defined('MINING_UPGRADE_COST_FIRST')) {
    define('MINING_UPGRADE_COST_FIRST', 5000);
}
if (!defined('MINING_UPGRADE_COST_STEP')) {
    define('MINING_UPGRADE_COST_STEP', 10000);
}
/** @deprecated */
if (!defined('MINING_UPGRADE_COST_FINAL')) {
    define('MINING_UPGRADE_COST_FINAL', '10000000000000000');
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
    /** 은총 강화비 할인 없음 (1=할인 없음 · 예전 ÷10 폐기) */
    define('MINING_UPGRADE_EUNCHONG_COST_DIV', 1);
}
if (!defined('MINING_EUNCHONG_YIELD_MULT')) {
    /** 은총 활성 시 채굴 적립 배율 (강화 분모 ÷10 과 별도 · 비용할인 없음) */
    define('MINING_EUNCHONG_YIELD_MULT', 30);
}
if (!defined('MINING_UPGRADE_EUNCHONG_PCT')) {
    define('MINING_UPGRADE_EUNCHONG_PCT', '0.01%');
}
if (!defined('MINING_EUNCHONG_MEGA_COST')) {
    /** 메가은총: 은총 10개 · 강화확률 0 2개 제거 · 강화비 할인 없음 */
    define('MINING_EUNCHONG_MEGA_COST', 10);
}
if (!defined('MINING_EUNCHONG_TERRA_COST')) {
    /** 테라은총: 은총 30개 · 강화확률 0 3개 제거 · 강화비 할인 없음 */
    define('MINING_EUNCHONG_TERRA_COST', 30);
}
if (!defined('MEGA_BOOSTER_COST')) {
    /** @deprecated 폴백 — 실제는 mega_booster_extra_from_cost() (강화비 ×3) */
    define('MEGA_BOOSTER_COST', '1');
}
if (!defined('MEGA_BOOSTER_COST_MULT')) {
    /** 메가부스터 추가비용 = 소진 강화비 × N (최종 = 강화비 + N배 = (N+1)배) */
    define('MEGA_BOOSTER_COST_MULT', 3);
}
if (!defined('MEGA_BOOSTER_COST_PCT')) {
    /** @deprecated 1% 방식 폐기 — MEGA_BOOSTER_COST_MULT 사용 */
    define('MEGA_BOOSTER_COST_PCT', 1);
}
if (!defined('MEGA_BOOSTER_EXTRA_ZEROS')) {
    /** 메가은총 부스터: 추가 제거 0 개수 (메가 2 + 부스터 1 = 3) */
    define('MEGA_BOOSTER_EXTRA_ZEROS', 1);
}

if (!function_exists('mega_booster_cost_ratio_parts_for_level')) {
    /**
     * @deprecated 부스터는 강화비 ×3 — 하위호환용 더미
     */
    function mega_booster_cost_ratio_parts_for_level($level): string {
        return '0';
    }
}

if (!function_exists('mining_시총비율_ceil_cost')) {
    /**
     * ceil(채굴유효시총 × parts / DEN), 최소 1
     * 무기보다 완만화 폭이 작음(비용 약간 더 높음) · 옛 9경 설계총량 폴백 금지
     */
    function mining_시총비율_ceil_cost(string $parts): string {
        $parts = ltrim(preg_replace('/[^\d]/', '', $parts) ?: '0', '0') ?: '0';
        if ($parts === '0') {
            return '1';
        }
        if (!function_exists('강화비용_시총비율금액_채굴')) {
            $cfg = __DIR__ . '/../config.php';
            if (is_file($cfg)) {
                @include_once $cfg;
            }
        }
        if (function_exists('강화비용_시총비율금액_채굴')) {
            return 강화비용_시총비율금액_채굴($parts);
        }
        if (function_exists('강화비용_시총비율금액')) {
            return 강화비용_시총비율금액($parts);
        }
        // 폴백: 실시간 시총 × parts / DEN
        $total = mining_upgrade_total_game_point_str();
        if (function_exists('냥_정수문자열')) {
            $total = 냥_정수문자열($total);
        }
        if ($total === '0') {
            $total = (string)MINING_UPGRADE_COST_DESIGN_TOTAL;
        }
        $den = defined('MINING_UPGRADE_COST_RATIO_DEN')
            ? (string)MINING_UPGRADE_COST_RATIO_DEN
            : '100000000000000000000';
        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcmod')
            && function_exists('bcadd') && function_exists('bccomp')) {
            $prod = bcmul($total, $parts, 0);
            $base = bcdiv($prod, $den, 0);
            if (bccomp(bcmod($prod, $den), '0', 0) > 0) {
                $base = bcadd($base, '1', 0);
            }
            return (bccomp($base, '1', 0) < 0) ? '1' : $base;
        }
        return '1';
    }
}

if (!function_exists('mega_booster_cost_for_level')) {
    /**
     * @deprecated 레벨 고정비 폐기 — mega_booster_extra_from_cost($강화비) 사용
     * 하위호환: 최소 1 반환
     */
    function mega_booster_cost_for_level($level): string {
        return '1';
    }
}

if (!function_exists('mega_booster_extra_from_cost')) {
    /**
     * 부스터 추가비용 = 소진 강화비 × MEGA_BOOSTER_COST_MULT (기본 3배)
     */
    function mega_booster_extra_from_cost($cost): string {
        $a = preg_replace('/[^\d]/', '', (string)$cost);
        $a = ltrim((string)$a, '0') ?: '0';
        if ($a === '0') {
            return '0';
        }
        $mult = max(1, (int)MEGA_BOOSTER_COST_MULT);
        if (function_exists('bcmul')) {
            return bcmul($a, (string)$mult, 0);
        }
        return (string)((int)$a * $mult);
    }
}

if (!function_exists('mega_booster_request_wanted')) {
    /** 요청에서 부스터 사용 여부 */
    function mega_booster_request_wanted($src = null): bool {
        if ($src === null) {
            $src = $_REQUEST;
        }
        if (!is_array($src)) {
            return false;
        }
        $v = $src['booster'] ?? $src['mega_booster'] ?? 0;
        return $v === 1 || $v === '1' || $v === true || $v === 'true' || $v === 'on';
    }
}

if (!function_exists('mega_booster_apply_effect')) {
    /**
     * 메가은총(tier=2) 활성 시에만 부스터 적용 — zeros+1 · 추가비용은 강화비 ×3
     * (실제 금액은 mega_booster_add_cost / mega_booster_extra_from_cost 에서 산정)
     *
     * @param array{active?:bool,zeros?:int,tier?:int} $effect
     * @param int $level 현재 무기강화/채굴장비 레벨 (미사용·하위호환)
     * @return array{ok:bool,zeros:int,extra_cost:string,applied:bool,error:string}
     */
    function mega_booster_apply_effect(array $effect, bool $want, $level = 0): array {
        $zeros = max(0, (int)($effect['zeros'] ?? 0));
        if (!$want) {
            return [
                'ok' => true,
                'zeros' => $zeros,
                'extra_cost' => '0',
                'applied' => false,
                'error' => '',
            ];
        }
        $active = !empty($effect['active']);
        $tier = (int)($effect['tier'] ?? 0);
        if (!$active || $tier !== 2) {
            return [
                'ok' => false,
                'zeros' => $zeros,
                'extra_cost' => '0',
                'applied' => false,
                'error' => '메가은총 활성화 중에만 부스터를 쓸 수 있어요.',
            ];
        }
        return [
            'ok' => true,
            'zeros' => min(6, $zeros + max(0, (int)MEGA_BOOSTER_EXTRA_ZEROS)),
            // 표시용 마커 — 실제 차감은 mega_booster_add_cost($강화비) 가 ×3 산정
            'extra_cost' => 'mult',
            'applied' => true,
            'error' => '',
        ];
    }
}

if (!function_exists('mega_booster_add_cost')) {
    /**
     * 강화비에 부스터 추가비용(강화비 × MEGA_BOOSTER_COST_MULT) 합산
     * $extra 가 '0' 이면 미적용. 그 외(mult/pct/구 절대액)여도 강화비 ×배수로 통일.
     */
    function mega_booster_add_cost($cost, $extra = 'pct'): string {
        $a = preg_replace('/[^\d]/', '', (string)$cost);
        $a = ltrim((string)$a, '0') ?: '0';
        $extraFlag = strtolower(trim((string)$extra));
        if ($extraFlag === '' || $extraFlag === '0') {
            return $a;
        }
        $b = function_exists('mega_booster_extra_from_cost')
            ? mega_booster_extra_from_cost($a)
            : '1';
        if ($b === '0') {
            return $a;
        }
        if (function_exists('냥_금액_문자열합')) {
            return 냥_금액_문자열합($a, $b);
        }
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 0);
        }
        return (string)((int)$a + (int)$b);
    }
}
if (!defined('MINING_DURABILITY_MAX')) {
    /**
     * 기본/폴백 최대 내구 (장비별 값은 mining_durability_max($level))
     * — 스키마 DEFAULT · IFNULL 용
     */
    define('MINING_DURABILITY_MAX', 120);
}
if (!defined('MINING_DURABILITY_SCALE')) {
    /** DB 소수 자릿수 */
    define('MINING_DURABILITY_SCALE', 6);
}
if (!defined('MINING_DURABILITY_EUNCHONG_WEAR_MULT')) {
    /** 은총 활성 시 내구 마모 배율 */
    define('MINING_DURABILITY_EUNCHONG_WEAR_MULT', 2.0);
}
if (!defined('MINING_DURABILITY_REPAIR_FLAT_NP')) {
    /** 내구 1당 고정 본방냥 (시총 비율 대신 사용) · 0이면 비율(PCT) 사용 */
    define('MINING_DURABILITY_REPAIR_FLAT_NP', 0.1);
}
if (!defined('MINING_DURABILITY_REPAIR_BONNYANG_PCT')) {
    /** 내구 1당 = 본방냥 총합 × 이 비율(%) · FLAT_NP > 0 이면 미사용 */
    define('MINING_DURABILITY_REPAIR_BONNYANG_PCT', 0.0000001);
}
if (!defined('MINING_DURABILITY_REPAIR_STEP')) {
    /** 기본 수리량 (웹 버튼 · `.채굴수리`) */
    define('MINING_DURABILITY_REPAIR_STEP', 100);
}
if (!defined('MINING_DURABILITY_REPAIR_MAX')) {
    /** 1회 최대 수리량 */
    define('MINING_DURABILITY_REPAIR_MAX', 1000);
}
if (!defined('MINING_DURABILITY_REPAIR_BONNYANG')) {
    /** @deprecated MINING_DURABILITY_REPAIR_BONNYANG_PCT 사용 */
    define('MINING_DURABILITY_REPAIR_BONNYANG', 0.0000001);
}
if (!defined('MINING_DURABILITY_REPAIR_PCT')) {
    /** @deprecated 채굴 수리는 본방냥 총합 × MINING_DURABILITY_REPAIR_BONNYANG_PCT */
    define('MINING_DURABILITY_REPAIR_PCT', 0.000001);
}
if (!defined('MINING_DURABILITY_WARN_PCT')) {
    /** UI 경고 구간 (잔여 % 이하) */
    define('MINING_DURABILITY_WARN_PCT', 20);
}
if (!defined('MINING_DURABILITY_STOP_PCT')) {
    /** 이 % 이하면 채굴 정지 (적립·시간 마모 중단) */
    define('MINING_DURABILITY_STOP_PCT', 10);
}

if (!function_exists('mining_eunchong_zeros_normalize')) {
    /**
     * bool(은총 여부) 또는 제거할 0 개수(int) → zeros
     * 은총=1, 메가=2, 테라=3
     */
    function mining_eunchong_zeros_normalize($use_eunchong_or_zeros): int {
        if (is_bool($use_eunchong_or_zeros)) {
            return $use_eunchong_or_zeros ? 1 : 0;
        }
        return max(0, min(6, (int)$use_eunchong_or_zeros));
    }
}

if (!function_exists('mining_eunchong_tier_defs')) {
    /**
     * @return array<int,array{tier:int,key:string,label:string,cost:int,zeros:int,cost_discount:bool}>
     */
    function mining_eunchong_tier_defs(): array {
        return [
            1 => [
                'tier' => 1,
                'key' => 'normal',
                'label' => '은총',
                'cost' => 1,
                'zeros' => 1,
                'cost_discount' => false,
            ],
            2 => [
                'tier' => 2,
                'key' => 'mega',
                'label' => '메가은총',
                'cost' => max(1, (int)MINING_EUNCHONG_MEGA_COST),
                'zeros' => 2,
                'cost_discount' => false,
            ],
            3 => [
                'tier' => 3,
                'key' => 'terra',
                'label' => '테라은총',
                'cost' => max(1, (int)MINING_EUNCHONG_TERRA_COST),
                'zeros' => 3,
                'cost_discount' => false,
            ],
        ];
    }
}

if (!function_exists('mining_eunchong_tier_def')) {
    /** @return array{tier:int,key:string,label:string,cost:int,zeros:int,cost_discount:bool} */
    function mining_eunchong_tier_def(int $tier): array {
        $defs = mining_eunchong_tier_defs();
        if (isset($defs[$tier])) {
            return $defs[$tier];
        }
        return $defs[1];
    }
}

if (!function_exists('mining_eunchong_use_tier_from_count')) {
    /**
     * 보유 수량으로 사용 가능한 최고 등급 (하위호환)
     * @return array{tier:int,key:string,label:string,cost:int,zeros:int,cost_discount:bool}|null
     */
    function mining_eunchong_use_tier_from_count($cnt): ?array {
        $cnt = max(0, (int)$cnt);
        if ($cnt < 1) {
            return null;
        }
        if ($cnt >= (int)MINING_EUNCHONG_TERRA_COST) {
            return mining_eunchong_tier_def(3);
        }
        if ($cnt >= (int)MINING_EUNCHONG_MEGA_COST) {
            return mining_eunchong_tier_def(2);
        }
        return mining_eunchong_tier_def(1);
    }
}

if (!function_exists('mining_eunchong_tier_for_use')) {
    /**
     * 선택한 등급 사용 가능 여부 (보유 부족 시 null)
     * @return array{tier:int,key:string,label:string,cost:int,zeros:int,cost_discount:bool}|null
     */
    function mining_eunchong_tier_for_use($cnt, $tier): ?array {
        $cnt = max(0, (int)$cnt);
        $tier = max(1, min(3, (int)$tier));
        $def = mining_eunchong_tier_def($tier);
        if ($cnt < (int)$def['cost']) {
            return null;
        }
        return $def;
    }
}

if (!function_exists('mining_eunchong_button_label')) {
    /** 기본 은총 버튼 문구 — 항상 은총(보유수) */
    function mining_eunchong_button_label($cnt): string {
        $cnt = max(0, (int)$cnt);
        return '은총(' . $cnt . ')';
    }
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
     * $use_eunchong_or_zeros: bool(은총=÷10) 또는 제거할 0 개수(1/2/3)
     *
     * @return array{num:int,denom:int}
     */
    function mining_upgrade_success_roll_for_level($level, $use_eunchong_or_zeros = false): array {
        $num = max(1, (int)MINING_UPGRADE_SUCCESS_NUM);
        $denom = mining_upgrade_success_base_denom_for_level($level);
        $zeros = mining_eunchong_zeros_normalize($use_eunchong_or_zeros);
        if ($zeros > 0) {
            $div = (int)pow(10, $zeros);
            if ($div < 1) {
                $div = max(1, (int)MINING_UPGRADE_EUNCHONG_DENOM_DIV);
            }
            $denom = max(1, (int)floor($denom / $div));
        }
        return ['num' => $num, 'denom' => $denom];
    }
}

if (!function_exists('mining_upgrade_success_denom')) {
    function mining_upgrade_success_denom($use_eunchong_or_zeros = false, $level = 0): int {
        return mining_upgrade_success_roll_for_level($level, $use_eunchong_or_zeros)['denom'];
    }
}

if (!function_exists('mining_upgrade_success_pct_str')) {
    function mining_upgrade_success_pct_str($use_eunchong_or_zeros = false, $level = 0): string {
        $zeros = mining_eunchong_zeros_normalize($use_eunchong_or_zeros);
        $roll = mining_upgrade_success_roll_for_level($level, $zeros);
        $denom = (int)$roll['denom'];
        $num = max(1, (int)$roll['num']);
        if ($denom <= 0) {
            return $zeros > 0 ? MINING_UPGRADE_EUNCHONG_PCT : MINING_UPGRADE_SUCCESS_PCT;
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
    /** 화면용: "1/10,000 (0.01%)" — bool 또는 zeros(int) */
    function mining_upgrade_success_hint_str($use_eunchong_or_zeros = false, $level = 0): string {
        $zeros = mining_eunchong_zeros_normalize($use_eunchong_or_zeros);
        $roll = mining_upgrade_success_roll_for_level($level, $zeros);
        $pct = mining_upgrade_success_pct_str($zeros, $level);
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
    /** @deprecated 구 고정수율 밴드 — mining_tool_days_to_base_pct() 사용 */
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

if (!function_exists('mining_tool_days_to_base_pct_table')) {
    /** @deprecated 전 장비 직접 % 방식 — mining_tool_daily_np_ratio_table() 사용 */
    function mining_tool_days_to_base_pct_table(): array {
        return [];
    }
}

if (!function_exists('mining_tool_uses_days_schedule')) {
    /** 전 장비 직접 % — 일수 스케줄 미사용 */
    function mining_tool_uses_days_schedule(int $level): bool {
        return false;
    }
}

if (!function_exists('mining_tool_days_to_base_pct')) {
    /**
     * 본방냥 0.3%를 캐는 데 걸리는 일수
     * = MINING_BASE_PCT_RATIO(0.3%) ÷ 장비 일일 비율
     */
    function mining_tool_days_to_base_pct(int $level): float {
        $max_lv = 14;
        if (function_exists('mining_tool_max_level')) {
            $max_lv = max(0, (int)mining_tool_max_level());
        }
        $level = max(0, min($max_lv, $level));
        $ratioTable = mining_tool_daily_np_ratio_table();
        $ratio = isset($ratioTable[$level]) ? (float)$ratioTable[$level] : 0.0;
        $base = defined('MINING_BASE_PCT_RATIO') ? (float)MINING_BASE_PCT_RATIO : 0.003;
        if ($ratio <= 0) {
            return 1.0;
        }
        return $base / $ratio;
    }
}

if (!function_exists('mining_tool_time_to_base_pct_label')) {
    /** 화면용: "10일" · "약 22시간" */
    function mining_tool_time_to_base_pct_label(int $level): string {
        $days = mining_tool_days_to_base_pct($level);
        if ($days >= 360) {
            $y = round($days / 365, 1);
            $s = rtrim(rtrim(number_format($y, 1, '.', ''), '0'), '.');
            return ($s === '1' ? '1년' : ($s . '년'));
        }
        if ($days >= 28) {
            $m = round($days / 30.4, 1);
            $s = rtrim(rtrim(number_format($m, 1, '.', ''), '0'), '.');
            return $s . '개월';
        }
        if ($days >= 1.05) {
            $d = round($days, 1);
            $s = rtrim(rtrim(number_format($d, 1, '.', ''), '0'), '.');
            return $s . '일';
        }
        $hours = max(0.1, round($days * 24, 1));
        $s = rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
        return '약 ' . $s . '시간';
    }
}

if (!function_exists('mining_tool_daily_np_ratio_table')) {
    /**
     * Lv → 24h 채굴량 = 본방냥 총합 × 비율
     * 숟가락 0.05% … 황금 1.25% (기존 대비 절반)
     */
    function mining_tool_daily_np_ratio_table(): array {
        return [
            0 => 0.0005,  // 숟가락 0.05%
            1 => 0.001,   // 포크 0.1%
            2 => 0.0015,  // 젓가락 0.15%
            3 => 0.0025,  // 식칼 0.25%
            4 => 0.0035,  // 냄비 0.35%
            5 => 0.0045,  // 프라이팬 0.45%
            6 => 0.005,   // 곡괭이 0.5%
            7 => 0.0055,  // 망치 0.55%
            8 => 0.006,   // 공구세트 0.6%
            9 => 0.0065,  // 채굴망치 0.65%
            10 => 0.0075, // 중장비 0.75%
            11 => 0.0085, // 다이아 곡괭이 0.85%
            12 => 0.0095, // 레이저 0.95%
            13 => 0.0105, // UFO 1.05%
            14 => 0.0125, // 황금 숟가락 1.25%
        ];
    }
}

if (!function_exists('mining_tool_daily_np_ratio_for_level')) {
    /** 해당 Lv 24h 본방냥 비율 */
    function mining_tool_daily_np_ratio_for_level(int $level): float {
        $max_lv = 14;
        if (function_exists('mining_tool_max_level')) {
            $max_lv = max(0, (int)mining_tool_max_level());
        }
        $level = max(0, min($max_lv, $level));
        $table = mining_tool_daily_np_ratio_table();
        if (isset($table[$level])) {
            return max(0.0, (float)$table[$level]);
        }
        $base = defined('MINING_BASE_PCT_RATIO') ? (float)MINING_BASE_PCT_RATIO : 0.003;
        return $base;
    }
}

if (!function_exists('mining_tool_daily_yield_table')) {
    /** @deprecated 구 고정 일수확 — mining_tool_daily_np_ratio_table() 사용 */
    function mining_tool_daily_yield_table(): array {
        return [];
    }
}

if (!function_exists('mining_tool_np_ratio_amount_str')) {
    /**
     * 본방냥 총합 × 비율 — 문자열 내림
     * 장비 비율은 0.05%~1.25%(0.0005~0.0125). 냥_비율내림은 정수%로 반올림해
     * Lv0~5→0 · Lv6~14→동일 1% 로 뭉개지므로 사용하지 않음.
     */
    function mining_tool_np_ratio_amount_str(float $ratio): string {
        $total = mining_tool_golden_np_total_str();
        if ($total === '0' || $ratio <= 0) {
            return '0';
        }
        if (function_exists('bcmul') && function_exists('bcadd')) {
            $raw = bcmul($total, sprintf('%.12F', $ratio), 12);
            return bcadd($raw, '0', 0);
        }
        // bcmath 없음: ×1e8 정수 스케일로 내림 (경 단위 문자열 유지)
        $scale = 100000000;
        $parts = (int)round($ratio * $scale);
        if ($parts <= 0) {
            return '0';
        }
        if (function_exists('냥_금액_문자열곱') && function_exists('냥_문자열나눗셈내림')) {
            return 냥_문자열나눗셈내림(냥_금액_문자열곱($total, (string)$parts), (string)$scale);
        }
        if (strlen($total) <= 15) {
            return (string)(int)floor((float)$total * $ratio);
        }
        return '0';
    }
}

if (!function_exists('mining_tool_base_pct_amount_str')) {
    /** 본방냥 총합 × MINING_BASE_PCT_RATIO (0.3%) — 문자열 내림 */
    function mining_tool_base_pct_amount_str(): string {
        $ratio = defined('MINING_BASE_PCT_RATIO')
            ? (float)MINING_BASE_PCT_RATIO
            : 0.01;
        return mining_tool_np_ratio_amount_str($ratio);
    }
}

if (!function_exists('mining_tool_daily_yield_str')) {
    /**
     * Lv N 24h 채굴량(문자열, 내림)
     * = 본방냥 총합 × 장비별 비율 (실시간 연동)
     */
    function mining_tool_daily_yield_str($level): string {
        $ratio = mining_tool_daily_np_ratio_for_level((int)$level);
        return mining_tool_np_ratio_amount_str($ratio);
    }
}

if (!function_exists('mining_tool_daily_yield_amount_fmt')) {
    /** 화면용 24h 획득량 숫자 (본방냥 실시간 연동, 콤마) */
    function mining_tool_daily_yield_amount_fmt($level, $yield_mult = 1.0): string {
        $amount = mining_tool_daily_yield_str((int)$level);
        $mult = max(0.0, (float)$yield_mult);
        if (abs($mult - 1.0) > 1e-9 && function_exists('bcmul')) {
            $amount = bcmul($amount, sprintf('%.12F', $mult), 0);
        } elseif (abs($mult - 1.0) > 1e-9) {
            $amount = (string)(int)floor((float)$amount * $mult);
        }
        if (function_exists('냥_숫자콤마')) {
            return 냥_숫자콤마($amount);
        }
        if (function_exists('mining_yield_amount_fmt')) {
            return mining_yield_amount_fmt($amount);
        }
        return number_format((float)$amount);
    }
}

if (!function_exists('mining_tool_is_golden_level')) {
    function mining_tool_is_golden_level(int $level): bool {
        $max_lv = 14;
        if (function_exists('mining_tool_max_level')) {
            $max_lv = max(0, (int)mining_tool_max_level());
        }
        return $level >= $max_lv;
    }
}

if (!function_exists('mining_tool_golden_np_total_str')) {
    /**
     * 본방냥(newpoint) 총합 — 문자열 (float 정밀도 손실 방지)
     * status=0 회원 SUM(newpoint)
     */
    function mining_tool_golden_np_total_str(): string {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $raw = '0';
        if (function_exists('db_select')) {
            // CONCAT('N', …) — mysqli가 큰 수를 float/과학적표기로 깨는 것 방지
            $row = @db_select("
                SELECT CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))), 0) AS CHAR)) AS total_np
                FROM tb_member
                WHERE status = 0
            ");
            $raw = (string)($row['total_np'] ?? 'N0');
        } elseif (function_exists('전체보유newpoint합계')) {
            $raw = (string)전체보유newpoint합계();
        } elseif (function_exists('시세기준_본방냥')) {
            $raw = (string)시세기준_본방냥();
        }
        if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
            $raw = substr($raw, 1);
        }
        if (function_exists('냥_정수문자열')) {
            // 소수부 버림 전 정수부만 — % 계산용 총량은 정수 냥 기준
            $digits = 냥_정수문자열($raw);
        } else {
            $raw = trim((string)$raw);
            if (strpos($raw, '.') !== false) {
                $raw = explode('.', $raw, 2)[0];
            }
            $digits = ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0';
        }
        $cache = $digits;
        return $cache;
    }
}

if (!function_exists('mining_tool_golden_np_total')) {
    /** @deprecated float — 큰 수는 mining_tool_golden_np_total_str() 사용 */
    function mining_tool_golden_np_total(): float {
        $s = mining_tool_golden_np_total_str();
        if (function_exists('bccomp') && bccomp($s, (string)PHP_INT_MAX, 0) > 0) {
            return (float)$s; // 근사
        }
        return (float)$s;
    }
}

if (!function_exists('mining_tool_golden_daily_yield_str')) {
    /** 황금 숟가락 24h = newpoint 총합 × 1.25% (문자열, 내림) */
    function mining_tool_golden_daily_yield_str(): string {
        return mining_tool_daily_yield_str(
            function_exists('mining_tool_max_level') ? (int)mining_tool_max_level() : 14
        );
    }
}

if (!function_exists('mining_tool_golden_daily_yield')) {
    /** 황금 숟가락 24h = 본방냥(newpoint) 총합 × 1.25% */
    function mining_tool_golden_daily_yield(): float {
        $s = mining_tool_golden_daily_yield_str();
        return (float)$s;
    }
}

if (!function_exists('mining_tool_base_pct_label')) {
    /** 화면용 비율 문구 — "1%" (내부/디버그용) */
    function mining_tool_base_pct_label(): string {
        $ratio = defined('MINING_BASE_PCT_RATIO')
            ? (float)MINING_BASE_PCT_RATIO
            : 0.01;
        $pct = $ratio * 100;
        $s = rtrim(rtrim(number_format($pct, 4, '.', ''), '0'), '.');
        return ($s === '' ? '0' : $s) . '%';
    }
}

if (!function_exists('mining_tool_np_ratio_pct_label')) {
    /** 비율 → "2.5%" 문구 */
    function mining_tool_np_ratio_pct_label(float $ratio): string {
        $pct = $ratio * 100;
        $s = rtrim(rtrim(number_format($pct, 4, '.', ''), '0'), '.');
        return ($s === '' ? '0' : $s) . '%';
    }
}

if (!function_exists('mining_tool_golden_daily_yield_label')) {
    /** 화면용: 실시간 24h 획득량 숫자만 (예: "87,853") — 본방냥 % 문구 없음 */
    function mining_tool_golden_daily_yield_label($daily = null): string {
        if ($daily === null) {
            return mining_tool_daily_yield_amount_fmt(
                function_exists('mining_tool_max_level') ? (int)mining_tool_max_level() : 14
            );
        }
        $amount = function_exists('냥_정수문자열')
            ? 냥_정수문자열($daily)
            : (string)(int)floor((float)$daily);
        if ($amount === '' || $amount === null) {
            $amount = '0';
        }
        if (function_exists('냥_숫자콤마')) {
            return 냥_숫자콤마($amount);
        }
        if (function_exists('mining_yield_amount_fmt')) {
            return mining_yield_amount_fmt($amount);
        }
        return number_format((float)$amount);
    }
}

if (!function_exists('mining_tool_daily_yield_count')) {
    function mining_tool_daily_yield_count(int $level): int {
        return (int)floor(mining_tool_daily_yield($level));
    }
}

if (!function_exists('mining_tool_hourly_yield_count')) {
    /** Lv N 시간당 냥 채굴량(개) — 일일 획득량 ÷ 24 */
    function mining_tool_hourly_yield_count(int $level): int {
        return (int)round(mining_tool_hourly_yield($level));
    }
}

if (!function_exists('mining_tool_rate_for_level')) {
    function mining_tool_rate_for_level($level): float {
        return mining_tool_daily_yield((int)$level) / (float)MINING_SECONDS_PER_DAY;
    }
}

if (!function_exists('mining_tool_daily_yield')) {
    /**
     * Lv N 24h 채굴량
     * = 본방냥 총합 × 장비별 비율 (실시간 연동)
     */
    function mining_tool_daily_yield($level): float {
        $max_lv = 14;
        if (function_exists('mining_tool_max_level')) {
            $max_lv = max(0, (int)mining_tool_max_level());
        }
        $level = max(0, min($max_lv, (int)$level));
        return (float)mining_tool_daily_yield_str($level);
    }
}

if (!function_exists('mining_tool_hourly_yield')) {
    function mining_tool_hourly_yield($level): float {
        return mining_tool_daily_yield((int)$level) / 24.0;
    }
}

if (!function_exists('mining_upgrade_total_game_point_str')) {
    /** 정상 회원 전체 게임냥(point) 합계 문자열 — 무기 강화(강화비용_전체게임냥)와 동일 소스 */
    function mining_upgrade_total_game_point_str(): string {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        // config.php 의 무기 강화 시총 (실시간 SUM · 설계총량 폴백은 ~3천만)
        if (!function_exists('강화비용_전체게임냥')) {
            $cfg = __DIR__ . '/../config.php';
            if (is_file($cfg)) {
                @include_once $cfg;
            }
        }
        if (function_exists('강화비용_전체게임냥')) {
            $cached = 강화비용_전체게임냥();
            if ($cached !== '' && $cached !== '0') {
                return $cached;
            }
        }
        $cached = '0';
        if (function_exists('시세기준_게임냥_문자열')) {
            $cached = 시세기준_게임냥_문자열();
        } elseif (function_exists('시세기준_게임냥') && function_exists('냥_정수문자열')) {
            $cached = 냥_정수문자열(시세기준_게임냥());
        }
        if (function_exists('냥_정수문자열')) {
            $cached = 냥_정수문자열($cached);
        } else {
            $cached = ltrim(preg_replace('/[^\d]/', '', (string)$cached) ?: '0', '0') ?: '0';
        }
        // 시세 함수 미로드·0이면 직접 SUM (구 설계총량 9경 폴백 금지)
        if ($cached === '0' && function_exists('db_select')) {
            $row = @db_select("
                SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
                FROM tb_member
                WHERE status = 0
            ");
            $raw = $row['total_pt'] ?? 'N0';
            if (function_exists('냥_금액원문_정규화')) {
                $cached = 냥_금액원문_정규화($raw);
            } elseif (function_exists('냥_정수문자열')) {
                $cached = 냥_정수문자열($raw);
            } else {
                $cached = ltrim(preg_replace('/[^\d]/', '', (string)$raw) ?: '0', '0') ?: '0';
            }
            if (isset($cached[0]) && $cached[0] === '-') {
                $cached = '0';
            }
        }
        if ($cached === '0') {
            if (defined('강화비용_설계총량')) {
                $cached = function_exists('냥_정수문자열')
                    ? 냥_정수문자열(강화비용_설계총량)
                    : (string)강화비용_설계총량;
            } else {
                $cached = (string)MINING_UPGRADE_COST_DESIGN_TOTAL;
            }
        }
        return $cached;
    }
}

if (!function_exists('mining_upgrade_cost_ratio_parts_table')) {
    /**
     * Lv → 강화비 비율 분자 (분모 = MINING_UPGRADE_COST_RATIO_DEN = 1e20)
     * cost = ceil(채굴유효시총 × parts / 1e20)
     *
     * 시총 ~4.1억 기준 · 연속 하향 후 추가 50% 인하(잔여 50%).
     *
     * @return array<int,string>
     */
    function mining_upgrade_cost_ratio_parts_table(): array {
        // 직전 대비 ×0.5
        return [
            0 => '3515625000',              // 숟가락→포크
            1 => '8203125000',              // 포크→젓가락
            2 => '16406250000',             // 젓가락→식칼
            3 => '41015625000',             // 식칼→냄비
            4 => '82031250000',             // 냄비→팬
            5 => '164062500000',            // 팬→곡괭이
            6 => '244140625000',            // 곡괭이→망치
            7 => '366210937500',            // 망치→공구세트
            8 => '488281250000',            // 공구세트→채굴망치
            9 => '683593750000',            // 채굴망치→중장비
            10 => '820312500000',           // 중장비→다이아
            11 => '1230468750000',          // 다이아→레이저
            12 => '1640625000000',          // 레이저→UFO
            13 => '2460937500000',          // UFO→황금
        ];
    }
}

if (!function_exists('mining_upgrade_cost_ratio_for_level')) {
    /** @return string 시총 대비 소수 비율 문자열 (예: 0.00000000000003) */
    function mining_upgrade_cost_ratio_for_level(int $level): string {
        $level = max(0, (int)$level);
        $parts = mining_upgrade_cost_ratio_parts_table();
        $num = (string)($parts[$level] ?? '1');
        $den = (string)MINING_UPGRADE_COST_RATIO_DEN;
        if (function_exists('bcdiv')) {
            return bcdiv($num, $den, 24);
        }
        return (string)((float)$num / (float)$den);
    }
}

if (!function_exists('mining_upgrade_cost_design_amount_table')) {
    /**
     * @deprecated mining_upgrade_cost_ratio_parts_table() 사용
     * 하위호환: 구 설계총량 기준 절대액 환산
     * @return array<int,string>
     */
    function mining_upgrade_cost_design_amount_table(): array {
        $out = [];
        $den = (string)MINING_UPGRADE_COST_RATIO_DEN;
        $legacyTotal = (string)MINING_UPGRADE_COST_DESIGN_TOTAL;
        foreach (mining_upgrade_cost_ratio_parts_table() as $lv => $parts) {
            if (function_exists('bcmul') && function_exists('bcdiv')) {
                // abs ≈ legacyTotal * parts / DEN
                $out[(int)$lv] = bcdiv(bcmul($legacyTotal, (string)$parts, 0), $den, 0);
            } else {
                $out[(int)$lv] = (string)(int)floor(((float)$legacyTotal * (float)$parts) / (float)$den);
            }
        }
        return $out;
    }
}

if (!function_exists('mining_upgrade_cost_fmt')) {
    /** 강화비 표시 — 억·만 미만도 0으로 깎지 않음 */
    function mining_upgrade_cost_fmt($cost) {
        $s = function_exists('냥_정수문자열')
            ? 냥_정수문자열($cost)
            : (preg_replace('/[^\d]/', '', (string)$cost) ?: '0');
        $s = ltrim((string)$s, '0') ?: '0';
        if (function_exists('랭킹_게임냥표시')) {
            return 랭킹_게임냥표시($s, '');
        }
        if (function_exists('게임냥_안전표시')) {
            return 게임냥_안전표시($s, '');
        }
        if (function_exists('mining_fmt_game')) {
            return mining_fmt_game($s);
        }
        return function_exists('냥_숫자콤마') ? 냥_숫자콤마($s) : $s;
    }
}

if (!function_exists('mining_upgrade_cost_for_level')) {
    /**
     * 현재 Lv → Lv+1 강화비 = ceil(채굴유효시총 × 비율).
     * 은총 강화비 할인 없음 (MINING_UPGRADE_EUNCHONG_COST_DIV=1).
     *
     * 항상 정수 문자열 반환 (float/(int) 캐스팅 금지 — 고레벨·대시총에서 1냥으로 깨지던 버그 방지).
     *
     * @return string
     */
    function mining_upgrade_cost_for_level($level, $use_eunchong = false) {
        $level = max(0, (int)$level);
        $parts_table = mining_upgrade_cost_ratio_parts_table();
        $max_from = 0;
        foreach (array_keys($parts_table) as $k) {
            $max_from = max($max_from, (int)$k);
        }
        // 최고장비(max_level=14)가 아니라 비율표 마지막 출발 Lv(13) 기준으로 clamp
        $level = min($level, $max_from);
        $parts = (string)($parts_table[$level] ?? $parts_table[$max_from] ?? '0');
        $parts = ltrim(preg_replace('/[^\d]/', '', $parts) ?: '0', '0') ?: '0';

        // 채굴 전용 유효시총 (무기 소프트캡보다 약간 높음)
        if (!function_exists('강화비용_시총비율금액_채굴')) {
            $cfg = __DIR__ . '/../config.php';
            if (is_file($cfg)) {
                @include_once $cfg;
            }
        }
        if (function_exists('강화비용_시총비율금액_채굴')) {
            $base = 강화비용_시총비율금액_채굴($parts);
        } else {
            $base = mining_시총비율_ceil_cost($parts);
        }

        if ($use_eunchong) {
            $div = max(1, (int)MINING_UPGRADE_EUNCHONG_COST_DIV);
            if (function_exists('bcdiv') && function_exists('bccomp')) {
                $base = bcdiv((string)$base, (string)$div, 0);
                if (bccomp($base, '1', 0) < 0) {
                    $base = '1';
                }
            } else {
                $base = (string)max(1, (int)floor(((float)$base) / $div));
            }
        }
        return (string)$base;
    }
}

if (!function_exists('mining_upgrade_cost_table')) {
    /** @return list<array{from_level:int,to_level:int,from_label:string,to_label:string,cost:int|string,cost_fmt:string,ratio:string}> */
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
                'ratio' => mining_upgrade_cost_ratio_for_level($lv),
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

/**
 * 오늘 생타(로우+사진보너스) — .궁금 cnt2 와 동일
 */
if (!function_exists('mining_chat_raw_tasu_for_nick')) {
    function mining_chat_raw_tasu_for_nick($nick): int {
        static $cache = [];
        $nick = trim((string)$nick);
        if ($nick === '') {
            return 0;
        }
        if (array_key_exists($nick, $cache)) {
            return $cache[$nick];
        }
        $esc = addslashes($nick);
        $raw_expr = function_exists('생타_SQL_select_expr')
            ? 생타_SQL_select_expr('msg')
            : "COUNT(*) + COALESCE(SUM(CASE WHEN msg = '사진을 보냈습니다.' THEN 2 ELSE 0 END), 0)";
        $row = @db_select("
            SELECT {$raw_expr} AS cnt2
            FROM tb_msg
            WHERE nickname = '{$esc}'
              AND tasu != 0
              AND regdate >= CURDATE()
              AND regdate < CURDATE() + INTERVAL 1 DAY
        ");
        $cache[$nick] = (int)($row['cnt2'] ?? 0);
        return $cache[$nick];
    }
}

/**
 * 생타 구간 → 채굴 배율 (본방냥 기반 장비 채굴량 × 배율)
 *  ~99: 0.1 / 100+: 0.3 / 300+: 0.5 / 500+: 1.0 / 800+: 1.3 / 1100+: 1.5
 */
if (!function_exists('mining_chat_yield_mult_for_raw')) {
    function mining_chat_yield_mult_for_raw(int $raw): float {
        if ($raw >= 1100) {
            return 1.5;
        }
        if ($raw >= 800) {
            return 1.3;
        }
        if ($raw >= 500) {
            return 1.0;
        }
        if ($raw >= 300) {
            return 0.5;
        }
        if ($raw >= 100) {
            return 0.3;
        }
        $def = defined('MINING_CHAT_YIELD_MULT_DEFAULT')
            ? (float)MINING_CHAT_YIELD_MULT_DEFAULT
            : 0.1;
        return max(0.0, $def);
    }
}

/** 로드맵·안내용 생타 구간 표 (min_raw 오름차순) */
if (!function_exists('mining_chat_yield_tiers')) {
    /** @return list<array{min_raw:int,label:string,mult:float}> */
    function mining_chat_yield_tiers(): array {
        return [
            ['min_raw' => 0, 'label' => '100타 미만', 'mult' => mining_chat_yield_mult_for_raw(0)],
            ['min_raw' => 100, 'label' => '100타', 'mult' => mining_chat_yield_mult_for_raw(100)],
            ['min_raw' => 300, 'label' => '300타', 'mult' => mining_chat_yield_mult_for_raw(300)],
            ['min_raw' => 500, 'label' => '500타', 'mult' => mining_chat_yield_mult_for_raw(500)],
            ['min_raw' => 800, 'label' => '800타', 'mult' => mining_chat_yield_mult_for_raw(800)],
            ['min_raw' => 1100, 'label' => '1100타', 'mult' => mining_chat_yield_mult_for_raw(1100)],
        ];
    }
}

/** 장비 Lv + 배율 → 24h 예상 획득량 표시 문자열 */
if (!function_exists('mining_chat_tier_daily_fmt')) {
    function mining_chat_tier_daily_fmt(int $level, float $mult): string {
        if (function_exists('mining_tool_daily_yield_amount_fmt')) {
            return (string)mining_tool_daily_yield_amount_fmt($level, $mult);
        }
        $base = function_exists('mining_tool_daily_yield')
            ? (float)mining_tool_daily_yield($level)
            : 0.0;
        $v = $base * max(0.0, $mult);
        if (function_exists('냥_숫자콤마')) {
            return 냥_숫자콤마((string)(int)floor($v));
        }
        return number_format((int)floor($v));
    }
}

/** 배율 숫자 → "×0.3" / "×1.5" */
if (!function_exists('mining_chat_mult_label')) {
    function mining_chat_mult_label(float $mult): string {
        $s = rtrim(rtrim(number_format($mult, 2, '.', ''), '0'), '.');
        if ($s === '') {
            $s = '0';
        }
        return '×' . $s;
    }
}

/**
 * 오늘 생타 → 강화비 할인율 (0~0.20)
 * 300미만 0 · 300+ 1% · 500+ 5% · 1000+ 10% · 1500+ 15% · 2000+ 20%
 */
if (!function_exists('mining_chat_upgrade_discount_for_raw')) {
    function mining_chat_upgrade_discount_for_raw(int $raw): float {
        if ($raw >= 2000) {
            return 0.20;
        }
        if ($raw >= 1500) {
            return 0.15;
        }
        if ($raw >= 1000) {
            return 0.10;
        }
        if ($raw >= 500) {
            return 0.05;
        }
        if ($raw >= 300) {
            return 0.01;
        }
        return 0.0;
    }
}

if (!function_exists('mining_chat_upgrade_discount_for_nick')) {
    function mining_chat_upgrade_discount_for_nick($nick): float {
        return mining_chat_upgrade_discount_for_raw(mining_chat_raw_tasu_for_nick($nick));
    }
}

/** 안내용 강화비 할인 구간 */
if (!function_exists('mining_chat_upgrade_discount_tiers')) {
    /** @return list<array{min_raw:int,label:string,discount:float,discount_pct:int}> */
    function mining_chat_upgrade_discount_tiers(): array {
        return [
            ['min_raw' => 0, 'label' => '300타 미만', 'discount' => 0.0, 'discount_pct' => 0],
            ['min_raw' => 300, 'label' => '300타', 'discount' => 0.01, 'discount_pct' => 1],
            ['min_raw' => 500, 'label' => '500타', 'discount' => 0.05, 'discount_pct' => 5],
            ['min_raw' => 1000, 'label' => '1000타', 'discount' => 0.10, 'discount_pct' => 10],
            ['min_raw' => 1500, 'label' => '1500타', 'discount' => 0.15, 'discount_pct' => 15],
            ['min_raw' => 2000, 'label' => '2000타', 'discount' => 0.20, 'discount_pct' => 20],
        ];
    }
}

/**
 * 강화비에 생타 할인 적용 (내림, 최소 1)
 * @param int|string $cost
 * @return int|string
 */
if (!function_exists('mining_upgrade_cost_apply_chat_discount')) {
    function mining_upgrade_cost_apply_chat_discount($cost, float $discount_rate) {
        $discount_rate = max(0.0, min(0.95, (float)$discount_rate));
        if ($discount_rate <= 0) {
            return is_string($cost) ? $cost : (string)$cost;
        }
        $keep = 1.0 - $discount_rate;
        $keep_s = sprintf('%.8F', $keep);

        $cost_s = function_exists('냥_정수문자열')
            ? 냥_정수문자열($cost)
            : (ltrim(preg_replace('/[^\d]/', '', (string)$cost) ?: '0', '0') ?: '0');
        if ($cost_s === '0') {
            return '1';
        }

        if (function_exists('bcmul') && function_exists('bccomp')) {
            // 내림: floor(cost * keep)
            $scaled = bcmul($cost_s, $keep_s, 8);
            $out = preg_replace('/\..*$/', '', $scaled);
            if ($out === '' || bccomp($out, '1', 0) < 0) {
                return '1';
            }
            return $out;
        }

        // bcmath 없음: keep이 0.8~1.0 범위라 자릿수 유지 + 앞자리만 축소
        $pct = (int)round($keep * 100);
        if ($pct >= 100) {
            return $cost_s;
        }
        if ($pct <= 0) {
            return '1';
        }
        // floor(cost * pct / 100)
        if (function_exists('냥_금액_문자열곱') && function_exists('냥_나눗셈내림')) {
            $out = 냥_나눗셈내림(냥_금액_문자열곱($cost_s, (string)$pct), 100);
            return ($out === '0' || $out === '') ? '1' : $out;
        }
        return $cost_s;
    }
}

if (!function_exists('mining_chat_upgrade_discount_payload')) {
    function mining_chat_upgrade_discount_payload($nick): array {
        $raw = mining_chat_raw_tasu_for_nick($nick);
        $disc = mining_chat_upgrade_discount_for_raw($raw);
        $pct = (int)round($disc * 100);
        return [
            'chat_upgrade_discount' => $disc,
            'chat_upgrade_discount_pct' => $pct,
            'chat_upgrade_discount_hint' => $pct > 0
                ? ('오늘 생타 ' . number_format($raw) . ' · 강화비 ' . $pct . '% 할인')
                : ('오늘 생타 ' . number_format($raw) . ' · 강화비 할인 없음 (300타부터)'),
        ];
    }
}

if (!function_exists('mining_chat_yield_mult_for_nick')) {
    function mining_chat_yield_mult_for_nick($nick): float {
        return mining_chat_yield_mult_for_raw(mining_chat_raw_tasu_for_nick($nick));
    }
}

if (!function_exists('mining_chat_yield_payload')) {
    function mining_chat_yield_payload($nick): array {
        $raw = mining_chat_raw_tasu_for_nick($nick);
        $mult = mining_chat_yield_mult_for_raw($raw);
        $mult_fmt = rtrim(rtrim(number_format($mult, 2, '.', ''), '0'), '.');
        if ($mult_fmt === '') {
            $mult_fmt = '0';
        }
        return [
            'chat_raw_tasu' => $raw,
            'chat_yield_mult' => $mult,
            'chat_yield_mult_fmt' => $mult_fmt,
            'chat_yield_hint' => '오늘 생타 ' . number_format($raw) . ' · 채굴 ×' . $mult_fmt,
        ];
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

if (!function_exists('mining_eunchong_tier_ensure_column')) {
    /**
     * 채굴 전용 은총 티어·종료시각 (무기 tb_member.은총 과 분리)
     */
    function mining_eunchong_tier_ensure_column(): void {
        static $done = false;
        if ($done || !function_exists('db_query') || !defined('MINING_TABLE')) {
            return;
        }
        $done = true;
        $tbl = MINING_TABLE;
        $exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_eunchong_tier'");
        if (empty($exists)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                ADD COLUMN `mining_eunchong_tier` TINYINT UNSIGNED NOT NULL DEFAULT 1
                COMMENT '채굴 활성 은총 등급 1=은총 2=메가 3=테라'
            ");
        }
        $end_exists = @db_select("SHOW COLUMNS FROM `{$tbl}` LIKE 'mining_eunchong_end'");
        if (empty($end_exists)) {
            @db_query("
                ALTER TABLE `{$tbl}`
                ADD COLUMN `mining_eunchong_end` DATETIME DEFAULT NULL
                COMMENT '채굴 은총 종료시각 (무기와 분리)'
            ");
            // 기존 공유 버프 이관: 활성 tb_member.은총 → 채굴 전용 종료시각
            @db_query("
                UPDATE `{$tbl}` m
                INNER JOIN tb_member mem ON mem.name = m.nick
                SET m.mining_eunchong_end = mem.은총
                WHERE mem.은총 IS NOT NULL
                  AND mem.은총 > NOW()
                  AND (m.mining_eunchong_end IS NULL OR m.mining_eunchong_end < NOW())
            ");
        }
    }
}

if (!function_exists('mining_eunchong_tier_for_nick')) {
    function mining_eunchong_tier_for_nick($nick): int {
        if (function_exists('mining_data_ensure_table')) {
            mining_data_ensure_table();
        }
        mining_eunchong_tier_ensure_column();
        $nick = trim((string)$nick);
        if ($nick === '' || !function_exists('db_select') || !defined('MINING_TABLE')) {
            return 1;
        }
        $nick_esc = function_exists('mining_data_nick_esc')
            ? mining_data_nick_esc($nick)
            : addslashes($nick);
        if ($nick_esc === '') {
            return 1;
        }
        if (function_exists('mining_data_ensure_row')) {
            mining_data_ensure_row($nick);
        }
        $row = @db_select("
            SELECT IFNULL(mining_eunchong_tier, 1) AS tier
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        $tier = (int)($row['tier'] ?? 1);
        return ($tier >= 1 && $tier <= 3) ? $tier : 1;
    }
}

if (!function_exists('mining_eunchong_tier_set_for_nick')) {
    function mining_eunchong_tier_set_for_nick($nick, int $tier): void {
        if (function_exists('mining_data_ensure_table')) {
            mining_data_ensure_table();
        }
        mining_eunchong_tier_ensure_column();
        $nick = trim((string)$nick);
        $tier = max(1, min(3, $tier));
        if ($nick === '' || !function_exists('db_query') || !defined('MINING_TABLE')) {
            return;
        }
        $nick_esc = function_exists('mining_data_nick_esc')
            ? mining_data_nick_esc($nick)
            : addslashes($nick);
        if ($nick_esc === '') {
            return;
        }
        if (function_exists('mining_data_ensure_row') && !mining_data_ensure_row($nick)) {
            return;
        }
        db_query("
            UPDATE `" . MINING_TABLE . "`
            SET mining_eunchong_tier = {$tier}
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
    }
}

if (!function_exists('mining_eunchong_state_from_values')) {
    /**
     * @return array{
     *   cnt:int,active:bool,end:string,left_sec:int,
     *   tier:int,key:string,label:string,zeros:int,cost_discount:bool,cost:int,btn_label:string
     * }
     */
    function mining_eunchong_state_from_values($cnt, $은총_end = '', $tier = 1): array {
        $cnt = max(0, (int)$cnt);
        $end = trim((string)$은총_end);
        $active = mining_eunchong_active($end);
        $left = ($active && $end !== '') ? max(0, strtotime($end) - time()) : 0;
        $tier = max(1, min(3, (int)$tier));
        if (!$active) {
            $tier = 1;
        }
        $def = mining_eunchong_tier_def($tier);
        $use = mining_eunchong_use_tier_from_count($cnt);
        return [
            'cnt' => $cnt,
            'active' => $active,
            'end' => $end,
            'left_sec' => $left,
            'tier' => (int)$def['tier'],
            'key' => (string)$def['key'],
            'label' => (string)$def['label'],
            'zeros' => $active ? (int)$def['zeros'] : 0,
            'cost_discount' => $active && !empty($def['cost_discount']),
            'cost' => (int)$def['cost'],
            'btn_label' => mining_eunchong_button_label($cnt),
            'show_normal' => $cnt >= 1 ? 1 : 0,
            'show_mega' => $cnt >= (int)MINING_EUNCHONG_MEGA_COST ? 1 : 0,
            'show_terra' => 0, // 테라은총 일시 비표시
            'use_tier' => $use ? (int)$use['tier'] : 0,
            'use_key' => $use ? (string)$use['key'] : '',
            'use_label' => $use ? (string)$use['label'] : '',
            'use_cost' => $use ? (int)$use['cost'] : 0,
            'use_zeros' => $use ? (int)$use['zeros'] : 0,
            'use_cost_discount' => $use ? !empty($use['cost_discount']) : false,
        ];
    }
}

if (!function_exists('mining_eunchong_end_for_nick')) {
    /** 채굴 전용 은총 종료시각 */
    function mining_eunchong_end_for_nick($nick): string {
        if (function_exists('mining_data_ensure_table')) {
            mining_data_ensure_table();
        }
        mining_eunchong_tier_ensure_column();
        $nick = trim((string)$nick);
        if ($nick === '' || !function_exists('db_select') || !defined('MINING_TABLE')) {
            return '';
        }
        $nick_esc = function_exists('mining_data_nick_esc')
            ? mining_data_nick_esc($nick)
            : addslashes($nick);
        if ($nick_esc === '') {
            return '';
        }
        if (function_exists('mining_data_ensure_row')) {
            mining_data_ensure_row($nick);
        }
        $row = @db_select("
            SELECT mining_eunchong_end AS end_at
            FROM `" . MINING_TABLE . "`
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");
        return trim((string)($row['end_at'] ?? ''));
    }
}

if (!function_exists('mining_eunchong_state_for_nick')) {
    /** @return array<string,mixed> */
    function mining_eunchong_state_for_nick($nick): array {
        if (!function_exists('db_select')) {
            return mining_eunchong_state_from_values(0);
        }
        $nick = trim((string)$nick);
        $nick_esc = addslashes($nick);
        if ($nick_esc === '') {
            return mining_eunchong_state_from_values(0);
        }
        $row = db_select("SELECT IFNULL(은총개수, 0) AS cnt FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($row)) {
            return mining_eunchong_state_from_values(0);
        }
        if (!function_exists('bag_은총_수량') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/../item_bag_enhance.inc.php';
        }
        $cnt = function_exists('bag_은총_수량')
            ? bag_은총_수량($nick)
            : ($row['cnt'] ?? 0);
        $tier = mining_eunchong_tier_for_nick($nick);
        $end = mining_eunchong_end_for_nick($nick);
        return mining_eunchong_state_from_values($cnt, $end, $tier);
    }
}

if (!function_exists('mining_eunchong_effect_for_nick')) {
    /**
     * 강화 판정·비용용 활성 버프
     * @return array{active:bool,zeros:int,cost_discount:bool,tier:int,label:string}
     */
    function mining_eunchong_effect_for_nick($nick): array {
        $state = mining_eunchong_state_for_nick($nick);
        return [
            'active' => !empty($state['active']),
            'zeros' => (int)($state['zeros'] ?? 0),
            'cost_discount' => !empty($state['cost_discount']),
            'tier' => (int)($state['tier'] ?? 1),
            'label' => (string)($state['label'] ?? '은총'),
        ];
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
            'eunchong_tier' => $state['tier'],
            'eunchong_key' => $state['key'],
            'eunchong_label' => $state['label'],
            'eunchong_zeros' => $state['zeros'],
            'eunchong_cost_discount' => !empty($state['cost_discount']) ? 1 : 0,
            'eunchong_btn_label' => $state['btn_label'],
            'eunchong_show_normal' => !empty($state['show_normal']) ? 1 : 0,
            'eunchong_show_mega' => !empty($state['show_mega']) ? 1 : 0,
            'eunchong_show_terra' => !empty($state['show_terra']) ? 1 : 0,
            'eunchong_use_tier' => $state['use_tier'],
            'eunchong_use_key' => $state['use_key'],
            'eunchong_use_label' => $state['use_label'],
            'eunchong_use_cost' => $state['use_cost'],
            'eunchong_use_zeros' => $state['use_zeros'],
            'eunchong_use_cost_discount' => !empty($state['use_cost_discount']) ? 1 : 0,
        ];
    }
}

if (!function_exists('mining_use_eunchong_execute')) {
    function mining_use_eunchong_execute($nick, $tier = 1) {
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

        if (!function_exists('bag_은총_차감') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/../item_bag_enhance.inc.php';
        }
        $보유 = function_exists('bag_은총_수량')
            ? bag_은총_수량($nick)
            : (int)($member['cnt'] ?? 0);
        $tier = max(1, min(3, (int)$tier));
        if ($tier === 3) {
            return ['ok' => false, 'data' => '테라은총은 아직 사용할 수 없어요.'];
        }
        $use = mining_eunchong_tier_for_use($보유, $tier);
        if ($use === null) {
            if ($보유 < 1) {
                return ['ok' => false, 'data' => '은총이 부족해요. (보유 0개)'];
            }
            $need = (int)mining_eunchong_tier_def($tier)['cost'];
            $label = (string)mining_eunchong_tier_def($tier)['label'];
            return ['ok' => false, 'data' => $label . ' 사용에 은총이 부족해요. (필요 ' . $need . '개 · 보유 ' . $보유 . '개)'];
        }

        $cost = max(1, (int)$use['cost']);
        $tier = (int)$use['tier'];
        $zeros = (int)$use['zeros'];
        $label = (string)$use['label'];
        $cost_discount = !empty($use['cost_discount']);

        $버프분 = ($tier >= 2) ? 10 : 5;
        if (function_exists('bag_은총_차감')) {
            $차감 = bag_은총_차감($nick, $cost);
            if (empty($차감['ok'])) {
                return ['ok' => false, 'data' => $label . ' 적용에 실패했어요. (필요 ' . $cost . '개)'];
            }
        } else {
            $rs = db_query("
                UPDATE tb_member
                SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - {$cost}, 0)
                WHERE name = '{$nick_esc}'
                  AND IFNULL(은총개수, 0) >= {$cost}
                LIMIT 1
            ");
            global $conn;
            $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : (bool)$rs;
            if (!$applied) {
                return ['ok' => false, 'data' => $label . ' 적용에 실패했어요. (필요 ' . $cost . '개)'];
            }
        }

        // 채굴 전용 버프만 연장 (무기 tb_member.은총 과 분리)
        if (function_exists('mining_data_ensure_table')) {
            mining_data_ensure_table();
        }
        mining_eunchong_tier_ensure_column();
        if (function_exists('mining_data_ensure_row') && !mining_data_ensure_row($nick)) {
            return ['ok' => false, 'data' => $label . ' 적용에 실패했어요. (채굴 정보)'];
        }
        $tbl = MINING_TABLE;
        db_query("
            UPDATE `{$tbl}`
            SET mining_eunchong_tier = {$tier},
                mining_eunchong_end = CASE
                    WHEN mining_eunchong_end IS NOT NULL AND mining_eunchong_end > NOW()
                        THEN DATE_ADD(mining_eunchong_end, INTERVAL {$버프분} MINUTE)
                    ELSE DATE_ADD(NOW(), INTERVAL {$버프분} MINUTE)
                END
            WHERE nick = '{$nick_esc}'
            LIMIT 1
        ");

        $state = mining_eunchong_state_for_nick($nick);
        mining_eunchong_reset_active_cache($nick);
        $tool_lv = 0;
        if (function_exists('mining_tool_level_for_nick')) {
            $tool_lv = max(0, (int)mining_tool_level_for_nick($nick));
        }
        $기본분모 = mining_upgrade_success_denom(0, $tool_lv);
        $버프분모 = mining_upgrade_success_denom($zeros, $tool_lv);
        $기본비용 = mining_upgrade_cost_for_level($tool_lv, false);
        $종료표시 = $state['end'] !== '' ? date('H:i:s', strtotime($state['end'])) : '-';
        $msg = '✨ ' . $label . " 적용! {$버프분}분간 채굴 버프 (무기는 별도)";
        $msg .= "\n은총 {$cost}개 사용 · 강화확률 0 {$zeros}개 제거";
        $msg .= "\n성공분모 " . number_format($기본분모) . ' → ' . number_format($버프분모);
        $msg .= ' (' . mining_upgrade_success_pct_str(0, $tool_lv) . ' → ' . mining_upgrade_success_pct_str($zeros, $tool_lv) . ')';
        if ($cost_discount) {
            $은총비용 = mining_upgrade_cost_for_level($tool_lv, true);
            $msg .= "\n강화비용 " . mining_upgrade_cost_fmt($기본비용) . '냥 → ' . mining_upgrade_cost_fmt($은총비용) . '냥';
        } else {
            $msg .= "\n강화비용 할인 없음 (" . mining_upgrade_cost_fmt($기본비용) . '냥)';
        }
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
            $log_receiver = $label
                . '·' . $tool_label
                . '·종료' . ($state['end'] !== '' ? date('Y-m-d H:i:s', strtotime($state['end'])) : '-')
                . '·남은' . (int)$state['cnt'] . '개';
            $log_type = ($tier === 3) ? '채굴테라은총사용' : (($tier === 2) ? '채굴메가은총사용' : '채굴은총사용');
            지급로그($log_type, $nick, $log_receiver, 0, $cost);
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
    /** 1000회 강화 — 본방냥 추가 차감 */
    define('MINING_UPGRADE_BATCH_1000_COST', 10);
}
if (!defined('MINING_UPGRADE_BATCH_3000_COST')) {
    /** 3000회 강화 — 본방냥 추가 차감 */
    define('MINING_UPGRADE_BATCH_3000_COST', 30);
}
if (!defined('MINING_UPGRADE_BATCH_5000_COST')) {
    /** 5000회 강화 — 본방냥 추가 차감 */
    define('MINING_UPGRADE_BATCH_5000_COST', 50);
}
/** @deprecated 하위 호환 — 100회는 본방냥 0 */
if (!defined('MINING_UPGRADE_BATCH_100_COST')) {
    define('MINING_UPGRADE_BATCH_100_COST', 0);
}
if (!function_exists('mining_upgrade_batch_times_allowed')) {
    /** 허용 연속 강화 횟수 */
    function mining_upgrade_batch_times_allowed(): array {
        return [1, 100, 500, 1000, 3000, 5000];
    }
}
if (!function_exists('mining_upgrade_batch_newpoint_cost')) {
    /** 배치 횟수별 본방냥 추가 차감: 1000→10 · 3000→30 · 5000→50 · 그 외 0 */
    function mining_upgrade_batch_newpoint_cost($times): int {
        $t = (int)$times;
        if ($t === 1000 || $t === (int)MINING_UPGRADE_BATCH_1000_TIMES) {
            return max(0, (int)MINING_UPGRADE_BATCH_1000_COST);
        }
        if ($t === 3000) {
            return max(0, (int)MINING_UPGRADE_BATCH_3000_COST);
        }
        if ($t === 5000) {
            return max(0, (int)MINING_UPGRADE_BATCH_5000_COST);
        }
        return 0;
    }
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
