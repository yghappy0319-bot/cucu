<?php
/**
 * 방 보스 레이드 (지갑 웹)
 *
 * - 참가: 무기 +1 이상
 * - 클리어 기준: HP=무기유저 기대딜 총합×참여율 200%(1회 스냅샷 고정 ±15%)
 * - 무기 공격 횟수: +1~15→4 … +20→10 · +21~30→12 … +51↑→18
 * - 채굴 공격 횟수: 보유 채굴 장비 레벨과 동일 (일반 수리해도 횟수 회복 없음 · 횟수 0이면 은총조각으로 횟수+내구 초기화)
 * - 마법: 채굴 대신 파티 보호(도전자 전원 · 량·횟수는 채팅 .보호와 비슷(강화×2) · 보스 한 판마다 초기화 · 채팅 한도와 별개 · 기여 제외)
 * - 단소: 무기 횟수×2 · HP 50%↓ 딜×2 · 채굴 대신 필살(무기×3~6 · 한도는 무기와 동일 · 1시간 · 채팅 .시전과 별개 · 0이면 은총조각 초기화)
 * - 무기 공격: 55% 확률로 내구 차감(강화 구간별 % · +1~10:5~10 …) · 채굴 2회차부터 채굴 내구 10%
 * - 개인 반격(매 공격): 20% 회피 · 보호 있으면 이번 공격 데미지의 5% 차감 · 보호 0이면 성향별(냥/내구10%/본방스왑 · 능력 분리)
 * - 광역(전투 중 최대 5회): 본인 누적 데미지의 3%만큼 보호 차감 · 보호 0이면 성향 피해
 * - 처치 보상: (1인기준×참여자수) 풀을 기여(데미지) 비율로 분배 + MVP 5천냥 + 처치풀(1% 분배·실패 시 누적)
 * - 처치 부가: 기여 1등 은총조각5 · 2등4 · 3등3 · 나머지 랜덤아이템 1개
 */

require_once __DIR__ . '/boss_raid_kinds.inc.php';
if (!function_exists('무기_마법인가')) {
    $wt = __DIR__ . '/weapon_type.inc.php';
    if (is_file($wt)) {
        require_once $wt;
    }
}

if (!defined('BOSS_RAID_HP_MAX')) {
    /** 기본(방룡) HP · 종류별 HP는 boss_raid_종류목록 참고 */
    define('BOSS_RAID_HP_MAX', 62000);
}
if (!defined('BOSS_RAID_REWARD_PCT')) {
    /** 폴백 1인기준 비율 · 실제 출현 보스는 종류별 0.3%~0.5% */
    define('BOSS_RAID_REWARD_PCT', 0.004);
}
/** 타격 1등(MVP) 추가 본방냥 */
if (!defined('BOSS_RAID_MVP_BONUS')) {
    define('BOSS_RAID_MVP_BONUS', 5000);
}
if (!defined('BOSS_RAID_FIGHT_SEC')) {
    define('BOSS_RAID_FIGHT_SEC', 1800); // 30분
}
/** 새벽(00~08) 전투 제한 시간 */
if (!defined('BOSS_RAID_FIGHT_SEC_DAWN')) {
    define('BOSS_RAID_FIGHT_SEC_DAWN', 1800); // 30분
}
/** 보스 종료 후 다음 출현까지 대기 (초) · 3~5시간 랜덤 */
if (!defined('BOSS_RAID_RESPAWN_SEC_MIN')) {
    define('BOSS_RAID_RESPAWN_SEC_MIN', 3 * 3600);
}
if (!defined('BOSS_RAID_RESPAWN_SEC_MAX')) {
    define('BOSS_RAID_RESPAWN_SEC_MAX', 5 * 3600);
}
/** @deprecated 레거시 폴백용(최소값). 신규는 boss_raid_리스폰초() 사용 */
if (!defined('BOSS_RAID_RESPAWN_SEC')) {
    define('BOSS_RAID_RESPAWN_SEC', (int)BOSS_RAID_RESPAWN_SEC_MIN);
}
/** 진행 중 공창 알림 간격(초) */
if (!defined('BOSS_RAID_TICK_ALARM_SEC')) {
    define('BOSS_RAID_TICK_ALARM_SEC', 60);
}
/** HP 임계치(%) 이하에서 공격이 멈췄을 때 회복 간격(초) */
if (!defined('BOSS_RAID_REGEN_IDLE_SEC')) {
    define('BOSS_RAID_REGEN_IDLE_SEC', 3);
}
/** 회복 발동: 현재 HP가 최대 HP의 이 % 이하일 때 */
if (!defined('BOSS_RAID_REGEN_HP_THRESHOLD_PCT')) {
    define('BOSS_RAID_REGEN_HP_THRESHOLD_PCT', 30);
}
/** 회복량: 최대 HP 대비 1% */
if (!defined('BOSS_RAID_REGEN_MIN_PCT')) {
    define('BOSS_RAID_REGEN_MIN_PCT', 1);
}
if (!defined('BOSS_RAID_REGEN_MAX_PCT')) {
    define('BOSS_RAID_REGEN_MAX_PCT', 1);
}
if (!defined('BOSS_RAID_MIN_ENHANCE')) {
    define('BOSS_RAID_MIN_ENHANCE', 1);
}
/** 클리어 기준 인원 비율 (방 인원 대비) · HP 스냅샷에도 동일 적용 */
if (!defined('BOSS_RAID_CLEAR_RATIO')) {
    define('BOSS_RAID_CLEAR_RATIO', 0.80);
}
/** 클리어 기준 참고 인원 (44명×80%≈35) */
if (!defined('BOSS_RAID_CLEAR_REF_MEMBERS')) {
    define('BOSS_RAID_CLEAR_REF_MEMBERS', 35);
}
/** 클리어 기준 참고 강화 (+20 풀커밋≈1인분) */
if (!defined('BOSS_RAID_CLEAR_REF_ENHANCE')) {
    define('BOSS_RAID_CLEAR_REF_ENHANCE', 20);
}
/** +21↑ 유효강화 증가 계수: 20+(e-20)*계수 → +100≈40 */
if (!defined('BOSS_RAID_WEAPON_SOFT_SCALE')) {
    define('BOSS_RAID_WEAPON_SOFT_SCALE', 0.25);
}
/** @deprecated 기대값 기반 산정으로 대체 — 관리자 안내 문구 하위호환용 */
if (!defined('BOSS_RAID_HP_CRIT_FRAC')) {
    define('BOSS_RAID_HP_CRIT_FRAC', 0.95);
}
/** HP 산정 참여율: 보유자 기대딜 총합 × 이 비율 (실제 참여자 비율 가정) */
if (!defined('BOSS_RAID_PARTICIPATION_RATE')) {
    define('BOSS_RAID_PARTICIPATION_RATE', 2.00);
}
/** 고정 HP 범위 = 중앙값 × (1±이 비율) */
if (!defined('BOSS_RAID_HP_RANGE_FRAC')) {
    define('BOSS_RAID_HP_RANGE_FRAC', 0.15);
}
/** HP 산정식 변경 시 기존 고정 스냅샷을 한 번 재계산하기 위한 버전 */
if (!defined('BOSS_RAID_HP_FORMULA_VERSION')) {
    define('BOSS_RAID_HP_FORMULA_VERSION', 5);
}
/** 채굴 공격 시 장비 내구도 차감 비율(% · 최대 내구 기준) */
if (!defined('BOSS_RAID_MINING_DUR_PCT')) {
    define('BOSS_RAID_MINING_DUR_PCT', 10);
}
/** 무기 공격 시 무기 내구도 차감 — 55% 확률로 적용 · 강화 구간별 % 범위(최대 내구 기준)
 *  +1~10 → 5~10% · +11~20 → 10~15% · +21~30 → 15~20% … (10강마다 +5)
 */
if (!defined('BOSS_RAID_WEAPON_ATTACK_DUR_PCT')) {
    define('BOSS_RAID_WEAPON_ATTACK_DUR_PCT', 5);
}
/** 활 무기 공격 데미지 보너스(%) */
if (!defined('BOSS_RAID_BOW_DMG_BONUS_PCT')) {
    define('BOSS_RAID_BOW_DMG_BONUS_PCT', 10);
}
/** 단소: 보스 HP 이 % 이하이면 딜 배율 */
if (!defined('BOSS_RAID_DANSO_EXECUTE_HP_PCT')) {
    define('BOSS_RAID_DANSO_EXECUTE_HP_PCT', 50);
}
if (!defined('BOSS_RAID_DANSO_EXECUTE_MULT')) {
    define('BOSS_RAID_DANSO_EXECUTE_MULT', 2);
}
/** 단소 무기 공격 횟수 배율 */
if (!defined('BOSS_RAID_DANSO_WEAPON_COUNT_MULT')) {
    define('BOSS_RAID_DANSO_WEAPON_COUNT_MULT', 2);
}
/** 단소 필살: 무기 공격의 배율 범위 */
if (!defined('BOSS_RAID_DANSO_FINISH_MULT_MIN')) {
    define('BOSS_RAID_DANSO_FINISH_MULT_MIN', 3);
}
if (!defined('BOSS_RAID_DANSO_FINISH_MULT_MAX')) {
    define('BOSS_RAID_DANSO_FINISH_MULT_MAX', 6);
}
/** 보유 게임냥 × 이 비율 차감 (0.001 = 0.1%) */
if (!defined('BOSS_RAID_COUNTER_POINT_RATIO')) {
    define('BOSS_RAID_COUNTER_POINT_RATIO', '0.001');
}
/** 치명타 확률(%) — 낮 5% / 밤(00~08) 10% · 참여자 수 무관 */
if (!defined('BOSS_RAID_CRIT_PCT')) {
    define('BOSS_RAID_CRIT_PCT', 5);
}
if (!defined('BOSS_RAID_CRIT_PCT_DAWN')) {
    define('BOSS_RAID_CRIT_PCT_DAWN', 10);
}
/** 치명타 발동 시 대미지 배율(%) 랜덤 범위 — 110~120 = +10~20% (예: 300 → 330~360) */
if (!defined('BOSS_RAID_CRIT_MULT_MIN_PCT')) {
    define('BOSS_RAID_CRIT_MULT_MIN_PCT', 110);
}
if (!defined('BOSS_RAID_CRIT_MULT_MAX_PCT')) {
    define('BOSS_RAID_CRIT_MULT_MAX_PCT', 120);
}
/** 무기 치명타는 기본 타격치의 10%를 두 번 더해 고정 +20% */
if (!defined('BOSS_RAID_WEAPON_CRIT_MULT_PCT')) {
    define('BOSS_RAID_WEAPON_CRIT_MULT_PCT', 120);
}
/** 대박타(잭팟) — +20↑만 발동 · 강화 구간별 확률 · 발동 시 랜덤 ×3~5 */
if (!defined('BOSS_RAID_JACKPOT_MIN_ENHANCE')) {
    define('BOSS_RAID_JACKPOT_MIN_ENHANCE', 20); // 이 강화부터 대박타 확률 적용
}
if (!defined('BOSS_RAID_JACKPOT_MULT_MIN')) {
    define('BOSS_RAID_JACKPOT_MULT_MIN', 3);
}
if (!defined('BOSS_RAID_JACKPOT_MULT_MAX')) {
    define('BOSS_RAID_JACKPOT_MULT_MAX', 5);
}
/** 공격 시 은총조각 드랍 확률(%) */
if (!defined('BOSS_RAID_DROP_SHARD_PCT')) {
    define('BOSS_RAID_DROP_SHARD_PCT', 3); // 3%
}
/** 공격 시 은총 드랍 확률(%) — 0.01% = 1/10000 */
if (!defined('BOSS_RAID_DROP_EUNCHONG_PCT')) {
    define('BOSS_RAID_DROP_EUNCHONG_PCT', 0.01);
}
/** 보스 처치 시 처치풀(탈취 냥) 분배 확률(%) — 실패 시 풀 유지·누적 */
if (!defined('BOSS_RAID_LOOT_PAYOUT_PCT')) {
    define('BOSS_RAID_LOOT_PAYOUT_PCT', 1); // 1%
}
/** 전투 중 광역(보호) 최대 횟수 */
if (!defined('BOSS_RAID_AOE_PROTECT_MAX')) {
    define('BOSS_RAID_AOE_PROTECT_MAX', 5);
}
/** 광역 보호 차감 — 본인 누적 데미지 대비(%) */
if (!defined('BOSS_RAID_AOE_PROTECT_DMG_PCT')) {
    define('BOSS_RAID_AOE_PROTECT_DMG_PCT', 3);
}
/** @deprecated 합 기준 → 본인 데미지%로 대체 */
if (!defined('BOSS_RAID_AOE_PROTECT_MIN_PCT')) {
    define('BOSS_RAID_AOE_PROTECT_MIN_PCT', 3);
}
/** @deprecated */
if (!defined('BOSS_RAID_AOE_PROTECT_MAX_PCT')) {
    define('BOSS_RAID_AOE_PROTECT_MAX_PCT', 3);
}
/** 개인 반격 회피 확률(%) */
if (!defined('BOSS_RAID_COUNTER_DODGE_PCT')) {
    define('BOSS_RAID_COUNTER_DODGE_PCT', 20);
}
/** 개인 반격 — 보호 차감 = 이번 공격 데미지 × N% (보유 보호와 무관) */
if (!defined('BOSS_RAID_COUNTER_PROTECT_DMG_PCT')) {
    define('BOSS_RAID_COUNTER_PROTECT_DMG_PCT', 5);
}
/** @deprecated 보유 보호 % 차감 → 데미지%로 대체 */
if (!defined('BOSS_RAID_COUNTER_PROTECT_MIN_PCT')) {
    define('BOSS_RAID_COUNTER_PROTECT_MIN_PCT', 5);
}
/** @deprecated */
if (!defined('BOSS_RAID_COUNTER_PROTECT_MAX_PCT')) {
    define('BOSS_RAID_COUNTER_PROTECT_MAX_PCT', 5);
}
/** @deprecated 공격마다 내구 광역 — 광역 보호로 대체 */
if (!defined('BOSS_RAID_AOE_PCT')) {
    define('BOSS_RAID_AOE_PCT', 0);
}
if (!defined('BOSS_RAID_AOE_DUR_PCT')) {
    define('BOSS_RAID_AOE_DUR_PCT', 20);
}
/** 무기 내구 비율(%) 미만이면 보스 공격 불가 — 0이면 내구 0일 때만 불가 */
if (!defined('BOSS_RAID_ATTACK_MIN_DUR_PCT')) {
    define('BOSS_RAID_ATTACK_MIN_DUR_PCT', 0);
}
/** 보호 0일 때 성향 피해 — 무기 내구 차감 비율(% · 최대 내구 기준) */
if (!defined('BOSS_RAID_COUNTER_DUR_PCT')) {
    define('BOSS_RAID_COUNTER_DUR_PCT', 10);
}
/** 환전형 반격 — 본방냥 강제스왑 비율(% · 고정) */
if (!defined('BOSS_RAID_COUNTER_SWAP_PCT')) {
    define('BOSS_RAID_COUNTER_SWAP_PCT', 10);
}

if (!function_exists('boss_raid_종류목록')) {
    /**
     * 오늘 출현 후보 6종 (매일/ .보스변경 시 재생성)
     * @return array<string, array{key:string,name:string,emoji:string,hp:int,reward_pct:float,blurb:string,style:string,style_label:string,style_desc:string}>
     */
    function boss_raid_종류목록(): array {
        return boss_raid_오늘종류목록();
    }
}

if (!function_exists('boss_raid_종류')) {
    /** @return array{key:string,name:string,emoji:string,hp:int,reward_pct:float,blurb:string,style:string,style_label:string,style_desc:string} */
    function boss_raid_종류($key): array {
        $key = trim((string)$key);
        $list = boss_raid_종류목록();
        if ($key !== '' && isset($list[$key])) {
            return $list[$key];
        }
        // 진행 중·과거 키 (재생성 후에도 조회 가능)
        $found = boss_raid_종류_키로조회($key);
        if (is_array($found)) {
            return $found;
        }
        $first = reset($list);
        if (is_array($first)) {
            return $first;
        }
        return [
            'key' => 'bangryong',
            'name' => '방룡',
            'emoji' => '🐉',
            'hp' => (int)BOSS_RAID_HP_MAX,
            'reward_pct' => (float)BOSS_RAID_REWARD_PCT,
            'blurb' => '냥탈취형',
            'style' => 'steal',
            'style_label' => '냥탈취형',
            'style_desc' => '기본 성향 · 게임냥 탈취 위주',
        ];
    }
}

if (!function_exists('boss_raid_다음종류키')) {
    /** 직전 출현 보스 다음 순번 (없으면 첫 보스) */
    function boss_raid_다음종류키(): string {
        $keys = array_keys(boss_raid_종류목록());
        if ($keys === []) {
            return 'bangryong';
        }
        $last = db_select("SELECT boss_key FROM tb_boss_raid ORDER BY idx DESC LIMIT 1");
        $lastKey = trim((string)($last['boss_key'] ?? ''));
        $idx = array_search($lastKey, $keys, true);
        if ($idx === false) {
            return $keys[0];
        }
        return $keys[($idx + 1) % count($keys)];
    }
}

if (!function_exists('boss_raid_랜덤종류키')) {
    function boss_raid_랜덤종류키(): string {
        $keys = array_keys(boss_raid_종류목록());
        if ($keys === []) {
            return 'bangryong';
        }
        return $keys[random_int(0, count($keys) - 1)];
    }
}

if (!function_exists('boss_raid_전투초')) {
    /** 평시·새벽 동일 30분 */

    function boss_raid_전투초($ts = null): int {
        if (boss_raid_새벽인가($ts)) {
            return (int)BOSS_RAID_FIGHT_SEC_DAWN;
        }
        return (int)BOSS_RAID_FIGHT_SEC;
    }
}

if (!function_exists('boss_raid_제한분')) {
    function boss_raid_제한분($ts = null): int {
        return max(1, (int)ceil(boss_raid_전투초($ts) / 60));
    }
}

if (!function_exists('boss_raid_리스폰초')) {
    /** 다음 보스 출현까지 대기 초 · 3~5시간 랜덤 */
    function boss_raid_리스폰초(): int {
        $min = (int)BOSS_RAID_RESPAWN_SEC_MIN;
        $max = (int)BOSS_RAID_RESPAWN_SEC_MAX;
        if ($max < $min) {
            $max = $min;
        }
        return random_int($min, $max);
    }
}

if (!function_exists('boss_raid_종류안내문구')) {
    /** `.보스종류` 안내 */
    function boss_raid_종류안내문구(): string {
        $msg = "🐉 보스 종류 안내\n";
        $msg .= "· 제한 30분 · 종료 후~다음 출현까지 기여/광역 이력 유지 · 다음 보스 출현 시 전부 삭제\n";
        $msg .= "· 참가: 무기 +1↑ · 가방 → [보스]\n";
        $msg .= "· 공격 드랍: 은총조각 3% · 은총 0.01%\n";
        $msg .= "· 처치: (1인기준×참여자수) 풀을 기여(데미지) 비율로 분배 + MVP +5천냥\n";
        $msg .= "· 처치 부가: 1등 은총조각5 · 2등4 · 3등3 · 나머지 랜덤아이템 1개\n";
        $msg .= "· 은총조각: 무기/채굴 공격횟수 0일 때 1개로 해당 횟수+내구 100% 초기화 · 단소 필살 0이면 1개로 횟수 초기화(1시간 다시)\n";
        $msg .= "· 처치풀(공통): 반격 탈취 냥 누적 · 처치 시 1%로 분배(실패 시 풀 유지) · 평시 랜덤 5명×20% · 00~08시 랜덤 3명×40%\n";
        $msg .= "· 새벽(00~08) 제한 30분 · 크리티컬 10% (HP 감소 없음)\n";
        $msg .= "· 크리티컬: 낮 5% / 밤(00~08) 10% (무기 +20% · 채굴 +10~20%)\n";
        $msg .= "· 🎰 대박타: 무기 +20↑ · 강화 구간별 확률(+20~29:16% … +90~100:4%) · 발동 시 ×3~5\n\n";
        $msg .= "오늘의 보스 (" . date('Y-m-d') . ")\n";
        $n = 1;
        foreach (boss_raid_종류목록() as $b) {
            $pct = boss_raid_보상비율문구((float)($b['reward_pct'] ?? 0));
            $styleLabel = (string)($b['style_label'] ?? '');
            $msg .= "{$n}) {$b['emoji']} {$b['name']}";
            if ($styleLabel !== '') {
                $msg .= " · {$styleLabel}";
            }
            $msg .= "\n";
            $msg .= "   HP " . number_format((int)$b['hp']) . " · 보상 본방냥 {$pct}\n";
            if (!empty($b['style_desc'])) {
                $msg .= "   {$b['style_desc']}\n";
            } elseif (!empty($b['blurb'])) {
                $msg .= "   {$b['blurb']}\n";
            }
            $msg .= "\n";
            $n++;
        }
        $msg .= "개인 반격(공격마다)\n";
        $msg .= "· " . (int)BOSS_RAID_COUNTER_DODGE_PCT . "% 회피 · 보호 있으면 이번 공격 데미지의 " . (int)BOSS_RAID_COUNTER_PROTECT_DMG_PCT . "% 차감\n";
        $msg .= "· 보호 0이면 성향별: 냥탈취 / 내구 " . (int)BOSS_RAID_COUNTER_DUR_PCT . "% / 본방" . (int)BOSS_RAID_COUNTER_SWAP_PCT . "%스왑\n\n";
        $msg .= "광역(전투 중 최대 " . (int)BOSS_RAID_AOE_PROTECT_MAX . "회)\n";
        $msg .= "· 보호 있으면 본인 누적 데미지의 " . (int)BOSS_RAID_AOE_PROTECT_DMG_PCT . "% 차감 (각자 다름)\n";
        $msg .= "· 보호가 있으면 보호만 깎임 · 보호 0이면 성향 피해(개인 반격과 동일)\n";
        $msg .= "· 무기 공격: 55% 확률로 내구 차감(강화 구간별 % · +1~10:5~10 · +11~20:10~15 · …) · 내구 0이면 공격 불가(수리 필요)";
        return rtrim($msg);
    }
}

if (!function_exists('boss_raid_초시분초')) {
    /** 남은 초 → "H시간 M분 S초" / "M분 S초" */
    function boss_raid_초시분초($sec): string {
        $sec = max(0, (int)$sec);
        $h = (int)floor($sec / 3600);
        $m = (int)floor(($sec % 3600) / 60);
        $s = $sec % 60;
        if ($h > 0) {
            return "{$h}시간 {$m}분 {$s}초";
        }
        return "{$m}분 {$s}초";
    }
}

if (!function_exists('boss_raid_젠안내문구')) {
    /** `.보스젠` — 다음(또는 현재) 보스·출현 시각 */
    function boss_raid_젠안내문구(): string {
        $link = 'http://49.247.160.164/page/boss.php';
        $st = boss_raid_활성_상태();
        $phase = (string)($st['phase'] ?? '');

        if ($phase === 'fighting') {
            $boss = $st['boss'] ?? [];
            $def = boss_raid_종류((string)($boss['boss_key'] ?? ''));
            $hp_max = (int)($boss['hp_max'] ?? $def['hp']);
            $hp_now = max(0, (int)($boss['hp_now'] ?? 0));
            $ends = !empty($boss['fight_ends_at']) ? strtotime((string)$boss['fight_ends_at']) : false;
            $left = ($ends !== false) ? max(0, $ends - time()) : 0;
            $nextKey = boss_raid_다음종류키();
            // 진행 중이면 다음 로테이션은 현재 보스 다음
            $keys = array_keys(boss_raid_종류목록());
            $curKey = (string)($def['key'] ?? '');
            $idx = array_search($curKey, $keys, true);
            if ($idx !== false && $keys !== []) {
                $nextKey = $keys[($idx + 1) % count($keys)];
            }
            $nextDef = boss_raid_종류($nextKey);
            $msg = "🐉 보스 진행 중!\n";
            $msg .= "{$def['emoji']} {$def['name']}\n";
            $msg .= "HP " . number_format($hp_now) . " / " . number_format($hp_max) . "\n";
            $msg .= "⏱ 남은 시간 " . boss_raid_초시분초($left) . "\n\n";
            $msg .= "다음 보스(처치/타임오버 후 3~5시간)\n";
            $msg .= "{$nextDef['emoji']} {$nextDef['name']}";
            if (!empty($nextDef['blurb'])) {
                $msg .= "\n{$nextDef['blurb']}";
            }
            $msg .= "\n\n{$link}";
            return $msg;
        }

        $nextKey = boss_raid_다음종류키();
        $nextDef = boss_raid_종류($nextKey);
        $msg = "🐉 다음 보스 젠\n";
        $msg .= "{$nextDef['emoji']} {$nextDef['name']}\n";
        if (!empty($nextDef['blurb'])) {
            $msg .= "{$nextDef['blurb']}\n";
        }
        $hp = boss_raid_출현HP((int)$nextDef['hp']);
        $pctLabel = boss_raid_보상비율문구((float)($nextDef['reward_pct'] ?? 0));
        $msg .= "HP " . number_format($hp) . " · 보상 본방냥 {$pctLabel}\n";

        if (!empty($st['need_admin'])) {
            $msg .= "\n출현: 건의방 관리자 `.보스출현` 대기 중";
            $msg .= "\n\n{$link}";
            return $msg;
        }

        $in = (int)($st['next_spawn_in'] ?? 0);
        $at = trim((string)($st['next_spawn_at'] ?? ''));
        if ($in > 0) {
            $msg .= "\n출현까지 " . boss_raid_초시분초($in);
            if ($at !== '') {
                $msg .= "\n예정: {$at}";
            }
        } else {
            $msg .= "\n출현: 곧 (자동 로테이션)";
        }
        $msg .= "\n\n{$link}";
        return $msg;
    }
}

if (!function_exists('boss_raid_준호인가')) {
    function boss_raid_준호인가($nick): bool {
        $nick = trim((string)$nick);
        if ($nick === '민호') {
            return true;
        }
        if (function_exists('getTwoCharNick') && getTwoCharNick($nick) === '민호') {
            return true;
        }
        return false;
    }
}

if (!function_exists('boss_raid_접근가능')) {
    /** 지갑 메뉴/페이지·공격 — 전원 개방 */
    function boss_raid_접근가능($nick): bool {
        return trim((string)$nick) !== '';
    }
}

if (!function_exists('boss_raid_마법무기인가')) {
    /** @param array|string $weaponOrItem */
    function boss_raid_마법무기인가($weaponOrItem): bool {
        if (function_exists('무기_마법인가')) {
            return (bool)무기_마법인가($weaponOrItem);
        }
        $item = is_array($weaponOrItem)
            ? trim((string)($weaponOrItem['item'] ?? ''))
            : trim((string)$weaponOrItem);
        return $item !== '' && (mb_strpos($item, '마법') !== false || strpos($item, '🪄') !== false);
    }
}

if (!function_exists('boss_raid_단소무기인가')) {
    /** @param array|string $weaponOrItem */
    function boss_raid_단소무기인가($weaponOrItem): bool {
        if (function_exists('무기_단소인가')) {
            return (bool)무기_단소인가($weaponOrItem);
        }
        $item = is_array($weaponOrItem)
            ? trim((string)($weaponOrItem['item'] ?? ''))
            : trim((string)$weaponOrItem);
        if ($item === '') {
            return false;
        }
        $keys = ['단소', '젓가락', '리코더', '피리', '대금', '플루트', '생황', '나발', '태평소', '엑스칼리버'];
        foreach ($keys as $k) {
            if (mb_strpos($item, $k) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('boss_raid_단소필살_스키마보장')) {
    /** tb_member 단소 필살 1시간 창 (채팅 magic_used · 보스 한 판과 무관) */
    function boss_raid_단소필살_스키마보장(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!function_exists('db_query')) {
            return;
        }
        $flag = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/data/.boss_danso_finish_schema_ok_v1';
        if ($flag !== '/data/.boss_danso_finish_schema_ok_v1' && is_file($flag)) {
            return;
        }
        global $conn;
        @db_query("SELECT boss_danso_finish_used, boss_danso_finish_window FROM tb_member LIMIT 1");
        $ok = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
        if (!$ok) {
            @db_query("ALTER TABLE tb_member ADD COLUMN boss_danso_finish_used INT NOT NULL DEFAULT 0");
            @db_query("ALTER TABLE tb_member ADD COLUMN boss_danso_finish_window DATETIME NULL DEFAULT NULL");
            @db_query("SELECT boss_danso_finish_used, boss_danso_finish_window FROM tb_member LIMIT 1");
            $ok = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
        }
        if ($ok && $flag !== '/data/.boss_danso_finish_schema_ok_v1') {
            $dir = dirname($flag);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($flag, '1');
        }
    }
}

if (!function_exists('boss_raid_단소필살구간_적용')) {
    /**
     * 단소 필살 1시간 구간 (채팅 .시전 magic_used와 무관 · 보스 교체해도 유지)
     * @return array{used:int,window:?string,reset_at:int,active:bool}
     */
    function boss_raid_단소필살구간_적용($닉, bool $시전시작 = false): array {
        boss_raid_단소필살_스키마보장();
        $esc = addslashes(trim((string)$닉));
        $쿨초 = 3600;
        $행 = ($esc !== '')
            ? @db_select("SELECT boss_danso_finish_used, boss_danso_finish_window FROM tb_member WHERE name = '{$esc}' LIMIT 1")
            : null;
        $used = is_array($행) ? (int)($행['boss_danso_finish_used'] ?? 0) : 0;
        $window = is_array($행) ? ($행['boss_danso_finish_window'] ?? null) : null;
        $window = ($window !== null && $window !== '') ? (string)$window : null;
        $지금 = time();
        $시작ts = $window !== null ? (int)strtotime($window) : 0;
        $active = ($window !== null && $시작ts > 0 && $지금 < $시작ts + $쿨초);

        if (!$active) {
            $used = 0;
            if ($시전시작 && $esc !== '') {
                $window = date('Y-m-d H:i:s', $지금);
                $win_esc = addslashes($window);
                @db_query("UPDATE tb_member SET boss_danso_finish_used = 0, boss_danso_finish_window = '{$win_esc}' WHERE name = '{$esc}' LIMIT 1");
                return [
                    'used' => 0,
                    'window' => $window,
                    'reset_at' => $지금 + $쿨초,
                    'active' => true,
                ];
            }
            if ($esc !== '' && is_array($행) && (int)($행['boss_danso_finish_used'] ?? 0) !== 0) {
                @db_query("UPDATE tb_member SET boss_danso_finish_used = 0 WHERE name = '{$esc}' LIMIT 1");
            }
            return [
                'used' => 0,
                'window' => null,
                'reset_at' => 0,
                'active' => false,
            ];
        }

        return [
            'used' => $used,
            'window' => $window,
            'reset_at' => $시작ts + $쿨초,
            'active' => true,
        ];
    }
}

if (!function_exists('boss_raid_단소필살횟수')) {
    /** 보스 무기 공격 횟수와 같음(+1~15=4 … · 단소×2). 채팅 .시전과 별개. */
    function boss_raid_단소필살횟수($weapon, $enhance): int {
        return boss_raid_무기공격횟수($enhance, $weapon);
    }
}

if (!function_exists('boss_raid_마법보호횟수')) {
    /**
     * 보스 마법 보호 한도 — 채팅 .보호 시전한도와 같음(강화×2).
     * 채팅 magic_used와 별개 · 보스 한 판(mining_used)마다 초기화.
     */
    function boss_raid_마법보호횟수($weapon, $enhance): int {
        $e = (int)$enhance;
        if ($e < (int)BOSS_RAID_MIN_ENHANCE) {
            return 0;
        }
        $item = is_array($weapon)
            ? trim((string)($weapon['item'] ?? ''))
            : trim((string)$weapon);
        if (function_exists('무기_시전한도_3시간')) {
            $n = (int)무기_시전한도_3시간($item !== '' ? $item : $weapon, $e);
            if ($n > 0) {
                return $n;
            }
        }
        return $e * 2;
    }
}

if (!function_exists('boss_raid_테이블_보장')) {
    function boss_raid_테이블_보장(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // 핫패스: 필수 컬럼이 이미 있으면 information_schema/SHOW 생략
        $schemaFlag = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/data/.boss_raid_schema_ok_v1';
        if ($schemaFlag !== '/data/.boss_raid_schema_ok_v1' && is_file($schemaFlag)) {
            return;
        }
        global $conn;
        @db_query("SELECT idx, boss_key, stolen_point, aoe_protect_count, last_tick_alarm_at, last_attack_at, last_regen_at FROM tb_boss_raid LIMIT 1");
        $bossOk = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
        if ($bossOk) {
            @db_query("SELECT crit, weapon_fx FROM tb_boss_raid_hit LIMIT 1");
            $hitOk = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
            if ($hitOk) {
                @db_query("SELECT 1 FROM tb_boss_raid_player LIMIT 1");
                $pOk = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
                @db_query("SELECT 1 FROM tb_boss_raid_counter LIMIT 1");
                $cOk = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
                @db_query("SELECT 1 FROM tb_boss_raid_drop LIMIT 1");
                $dOk = !($conn instanceof mysqli) || mysqli_errno($conn) === 0;
                if ($pOk && $cOk && $dOk) {
                    // 스키마 OK — SHOW COLUMNS 마이그레이션 생략
                    if ($schemaFlag !== '/data/.boss_raid_schema_ok_v1') {
                        $dir = dirname($schemaFlag);
                        if (!is_dir($dir)) {
                            @mkdir($dir, 0755, true);
                        }
                        @file_put_contents($schemaFlag, '1');
                    }
                    return;
                }
            }
        }

        // 일반 페이지 요청에서 매번 CREATE/ALTER를 시도하지 않도록
        // 현재 테이블 목록을 한 번 조회하고 실제로 누락된 스키마만 보정한다.
        $tables = [];
        $tableRs = @db_query("
          SELECT TABLE_NAME AS table_name
          FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN (
              'tb_boss_raid', 'tb_boss_raid_hit', 'tb_boss_raid_player',
              'tb_boss_raid_counter', 'tb_boss_raid_drop'
            )
        ");
        if ($tableRs) {
            while ($tableRow = db_fetch($tableRs)) {
                $tableName = (string)($tableRow['table_name'] ?? $tableRow['TABLE_NAME'] ?? '');
                if ($tableName !== '') {
                    $tables[$tableName] = true;
                }
            }
        }

        $hpDef = (int)BOSS_RAID_HP_MAX;
        if (empty($tables['tb_boss_raid'])) {
            @db_query("CREATE TABLE IF NOT EXISTS tb_boss_raid (
              idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              status TINYINT NOT NULL DEFAULT 0 COMMENT '0=진행 1=처치 2=타임오버',
              hp_max INT UNSIGNED NOT NULL DEFAULT {$hpDef},
              hp_now INT NOT NULL DEFAULT {$hpDef},
              fight_ends_at DATETIME DEFAULT NULL,
              next_spawn_at DATETIME DEFAULT NULL,
              last_attack_at DATETIME DEFAULT NULL,
              last_regen_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              killed_at DATETIME DEFAULT NULL,
              KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $tables['tb_boss_raid'] = true;
        }

        $bossColumns = [];
        $bossColRs = @db_query("SHOW COLUMNS FROM tb_boss_raid");
        if ($bossColRs) {
            while ($colRow = db_fetch($bossColRs)) {
                $field = (string)($colRow['Field'] ?? $colRow['FIELD'] ?? '');
                if ($field !== '') {
                    $bossColumns[$field] = true;
                }
            }
        }

        $cols = [
            'fight_ends_at' => "ALTER TABLE tb_boss_raid ADD COLUMN fight_ends_at DATETIME DEFAULT NULL AFTER hp_now",
            'next_spawn_at' => "ALTER TABLE tb_boss_raid ADD COLUMN next_spawn_at DATETIME DEFAULT NULL AFTER fight_ends_at",
            'last_attack_at' => "ALTER TABLE tb_boss_raid ADD COLUMN last_attack_at DATETIME DEFAULT NULL AFTER next_spawn_at",
            'last_regen_at' => "ALTER TABLE tb_boss_raid ADD COLUMN last_regen_at DATETIME DEFAULT NULL AFTER last_attack_at",
            'boss_key' => "ALTER TABLE tb_boss_raid ADD COLUMN boss_key VARCHAR(32) NOT NULL DEFAULT 'bangryong' AFTER status",
            'last_tick_alarm_at' => "ALTER TABLE tb_boss_raid ADD COLUMN last_tick_alarm_at DATETIME DEFAULT NULL AFTER next_spawn_at",
            'stolen_point' => "ALTER TABLE tb_boss_raid ADD COLUMN stolen_point DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '반격으로 탈취한 게임냥 누적' AFTER hp_now",
            'aoe_protect_count' => "ALTER TABLE tb_boss_raid ADD COLUMN aoe_protect_count TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '광역(보호) 발동 횟수' AFTER stolen_point",
        ];
        $addedLastAttack = false;
        $addedLastRegen = false;
        foreach ($cols as $name => $sql) {
            if (empty($bossColumns[$name])) {
                @db_query($sql);
                $bossColumns[$name] = true;
                $addedLastAttack = $addedLastAttack || $name === 'last_attack_at';
                $addedLastRegen = $addedLastRegen || $name === 'last_regen_at';
            }
        }

        if (empty($tables['tb_boss_raid_hit'])) {
            @db_query("CREATE TABLE IF NOT EXISTS tb_boss_raid_hit (
              idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              boss_idx INT UNSIGNED NOT NULL,
              nick VARCHAR(50) NOT NULL,
              attack_type VARCHAR(16) NOT NULL COMMENT 'weapon|mining',
              damage INT UNSIGNED NOT NULL DEFAULT 0,
              enhance INT NOT NULL DEFAULT 0,
              mining_level INT NOT NULL DEFAULT 0,
              crit TINYINT UNSIGNED NOT NULL DEFAULT 0,
              weapon_fx VARCHAR(16) NOT NULL DEFAULT '',
              regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_boss_nick_type (boss_idx, nick, attack_type),
              KEY idx_boss (boss_idx),
              KEY idx_nick (nick)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $tables['tb_boss_raid_hit'] = true;
        }

        // 구 UNIQUE(보스·닉·타입) 제거 — 무기 복수 공격 허용
        $rs = @db_query("SHOW INDEX FROM tb_boss_raid_hit WHERE Key_name = 'uk_boss_nick_type'");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $key = (string)($row['Key_name'] ?? $row['KEY_NAME'] ?? '');
                if ($key === '' || $key === 'PRIMARY') {
                    continue;
                }
                if ((int)($row['Non_unique'] ?? $row['NON_UNIQUE'] ?? 1) !== 0) {
                    continue;
                }
                if ($key === 'uk_boss_nick_type') {
                    @db_query("ALTER TABLE tb_boss_raid_hit DROP INDEX `uk_boss_nick_type`");
                }
            }
        }

        // 타인 타격 FX 동기화용
        $hitColumns = [];
        $hitColRs = @db_query("SHOW COLUMNS FROM tb_boss_raid_hit");
        if ($hitColRs) {
            while ($colRow = db_fetch($hitColRs)) {
                $field = (string)($colRow['Field'] ?? $colRow['FIELD'] ?? '');
                if ($field !== '') {
                    $hitColumns[$field] = true;
                }
            }
        }
        if (empty($hitColumns['crit'])) {
            @db_query("ALTER TABLE tb_boss_raid_hit ADD COLUMN crit TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER mining_level");
        }
        if (empty($hitColumns['weapon_fx'])) {
            @db_query("ALTER TABLE tb_boss_raid_hit ADD COLUMN weapon_fx VARCHAR(16) NOT NULL DEFAULT '' AFTER crit");
        }

        // 회복 컬럼을 처음 추가한 배포 요청에서만 기존 진행 건을 보정한다.
        if ($addedLastAttack) {
            @db_query("
              UPDATE tb_boss_raid b
              SET last_attack_at = COALESCE(
                (SELECT MAX(h.regdate) FROM tb_boss_raid_hit h WHERE h.boss_idx = b.idx),
                b.created_at
              )
              WHERE b.status = 0 AND b.last_attack_at IS NULL
            ");
        }
        if ($addedLastRegen) {
            @db_query("
              UPDATE tb_boss_raid
              SET last_regen_at = COALESCE(last_attack_at, created_at, NOW())
              WHERE status = 0 AND last_regen_at IS NULL
            ");
        }

        if (empty($tables['tb_boss_raid_player'])) {
            @db_query("CREATE TABLE IF NOT EXISTS tb_boss_raid_player (
              boss_idx INT UNSIGNED NOT NULL,
              nick VARCHAR(50) NOT NULL,
              weapon_used INT UNSIGNED NOT NULL DEFAULT 0,
              mining_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (boss_idx, nick)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (empty($tables['tb_boss_raid_counter'])) {
            @db_query("CREATE TABLE IF NOT EXISTS tb_boss_raid_counter (
              idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              boss_idx INT UNSIGNED NOT NULL,
              nick VARCHAR(50) NOT NULL,
              effect_type VARCHAR(16) NOT NULL COMMENT 'item|point|durability|protect',
              amount VARCHAR(64) NOT NULL DEFAULT '' COMMENT '소멸량/개수',
              detail VARCHAR(200) NOT NULL DEFAULT '',
              regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_boss (boss_idx),
              KEY idx_boss_reg (boss_idx, regdate)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (empty($tables['tb_boss_raid_drop'])) {
            @db_query("CREATE TABLE IF NOT EXISTS tb_boss_raid_drop (
              idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              boss_idx INT UNSIGNED NOT NULL,
              nick VARCHAR(50) NOT NULL DEFAULT '',
              drop_type VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'shard|eunchong',
              qty INT UNSIGNED NOT NULL DEFAULT 1,
              detail VARCHAR(200) NOT NULL DEFAULT '',
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_boss (boss_idx),
              KEY idx_boss_created (boss_idx, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (function_exists('boss_raid_처치풀_컬럼_확보')) {
            boss_raid_처치풀_컬럼_확보();
        }
    }
}

if (!function_exists('boss_raid_회복_동기화')) {
    /**
     * HP가 30% 이하이고 3초간 공격이 없으면 최대 HP의 1%를 회복한다.
     * 조건부 UPDATE로 여러 사용자가 동시에 폴링해도 한 번만 적용된다.
     */
    function boss_raid_회복_동기화(array $boss): array {
        $mid = (int)($boss['idx'] ?? 0);
        if ($mid < 1 || (int)($boss['status'] ?? -1) !== 0) {
            return $boss;
        }

        $idleSec = max(1, (int)BOSS_RAID_REGEN_IDLE_SEC);
        $thresholdPct = max(1, min(100, (int)BOSS_RAID_REGEN_HP_THRESHOLD_PCT));
        $minPct = max(0, (int)BOSS_RAID_REGEN_MIN_PCT);
        $maxPct = max($minPct, (int)BOSS_RAID_REGEN_MAX_PCT);
        if ($maxPct < 1) {
            return $boss;
        }
        $pct = random_int($minPct, $maxPct);

        global $conn;
        @db_query("
          UPDATE tb_boss_raid
          SET hp_now = LEAST(hp_max, hp_now + CEIL(hp_max * {$pct} / 100)),
              last_regen_at = NOW()
          WHERE idx = {$mid}
            AND status = 0
            AND hp_now > 0
            AND hp_now * 100 <= hp_max * {$thresholdPct}
            AND COALESCE(last_attack_at, created_at) <= DATE_SUB(NOW(), INTERVAL {$idleSec} SECOND)
            AND COALESCE(last_regen_at, last_attack_at, created_at) <= DATE_SUB(NOW(), INTERVAL {$idleSec} SECOND)
          LIMIT 1
        ");
        $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$applied) {
            return $boss;
        }

        $fresh = @db_select("SELECT * FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        return is_array($fresh) && !empty($fresh['idx']) ? $fresh : $boss;
    }
}

if (!function_exists('boss_raid_본방냥총합')) {
    function boss_raid_본방냥총합(): float {
        if (function_exists('시세기준_본방냥')) {
            return (float)시세기준_본방냥();
        }
        if (function_exists('전체보유newpoint합계')) {
            return (float)전체보유newpoint합계();
        }
        $row = @db_select("SELECT COALESCE(SUM(newpoint), 0) AS s FROM tb_member WHERE status = 0");
        return (float)($row['s'] ?? 0);
    }
}

if (!function_exists('boss_raid_보상1인')) {
    /** @param float|null $pct null이면 기본 BOSS_RAID_REWARD_PCT · 만타격(무기 최대횟수 전부) 시 지급액 */
    function boss_raid_보상1인($pct = null): int {
        $total = boss_raid_본방냥총합();
        $rate = $pct !== null ? (float)$pct : (float)BOSS_RAID_REWARD_PCT;
        if ($rate <= 0) {
            $rate = (float)BOSS_RAID_REWARD_PCT;
        }
        return max(1, (int)floor($total * $rate));
    }
}

if (!function_exists('boss_raid_보상_기여분배')) {
    /**
     * 풀을 데미지 기여 비율로 분배 (내림 · 잔여는 1등부터 1냥씩)
     * @param list<array{nick:string,damage:int}> $rank
     * @return list<array{nick:string,damage:int,amount:int,share_pct:float,place:int}>
     */
    function boss_raid_보상_기여분배(int $pool, array $rank): array {
        $pool = max(0, (int)$pool);
        $rows = [];
        $totalDmg = 0;
        $place = 0;
        foreach ($rank as $row) {
            $nick = trim((string)($row['nick'] ?? ''));
            $dmg = max(0, (int)($row['damage'] ?? 0));
            if ($nick === '' || $dmg < 1) {
                continue;
            }
            $place++;
            $rows[] = [
                'nick' => $nick,
                'damage' => $dmg,
                'amount' => 0,
                'share_pct' => 0.0,
                'place' => $place,
            ];
            $totalDmg += $dmg;
        }
        if ($rows === [] || $pool < 1 || $totalDmg < 1) {
            return $rows;
        }

        $assigned = 0;
        foreach ($rows as $i => $r) {
            $share = ($r['damage'] / $totalDmg);
            $amt = (int)floor($pool * $share);
            $rows[$i]['amount'] = $amt;
            $rows[$i]['share_pct'] = round($share * 100, 2);
            $assigned += $amt;
        }
        $remain = $pool - $assigned;
        for ($i = 0; $i < count($rows) && $remain > 0; $i++) {
            $rows[$i]['amount']++;
            $remain--;
        }
        return $rows;
    }
}

if (!function_exists('boss_raid_보상_개인산출')) {
    /**
     * 무기 최대 공격 전부 → 전액 / 아니면 사용횟수÷최대횟수 비율 (풀 산출용)
     * @return array{amount:int,used:int,max:int,full:bool,ratio:float}
     */
    function boss_raid_보상_개인산출(int $boss_idx, string $nick, int $fullReward): array {
        $nick = trim($nick);
        $fullReward = max(0, (int)$fullReward);
        $empty = ['amount' => 0, 'used' => 0, 'max' => 0, 'full' => false, 'ratio' => 0.0];
        if ($nick === '' || $boss_idx < 1 || $fullReward < 1) {
            return $empty;
        }
        $pl = boss_raid_플레이어행($boss_idx, $nick);
        $used = max(0, (int)($pl['weapon_used'] ?? 0));
        $weapon = boss_raid_무기정보($nick);
        $max = boss_raid_무기공격횟수((int)($weapon['enhance'] ?? 0), $weapon);
        if ($max < 1) {
            // 무기 공격 자격 없으면 채굴만 참여한 경우 — 지급 없음
            return $empty;
        }
        if ($used >= $max) {
            return [
                'amount' => $fullReward,
                'used' => $used,
                'max' => $max,
                'full' => true,
                'ratio' => 1.0,
            ];
        }
        if ($used < 1) {
            return [
                'amount' => 0,
                'used' => 0,
                'max' => $max,
                'full' => false,
                'ratio' => 0.0,
            ];
        }
        $amount = (int)floor($fullReward * $used / $max);
        if ($amount < 1) {
            $amount = 1;
        }
        return [
            'amount' => $amount,
            'used' => $used,
            'max' => $max,
            'full' => false,
            'ratio' => $used / $max,
        ];
    }
}

if (!function_exists('boss_raid_보상비율문구')) {
    function boss_raid_보상비율문구($pct): string {
        $pct = (float)$pct;
        $v = round($pct * 1000) / 10; // 0.7, 1, 1.2 …
        if (abs($v - round($v)) < 0.05) {
            return ((int)round($v)) . '%';
        }
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') . '%';
    }
}

if (!function_exists('boss_raid_무기_유효강화')) {
    /**
     * 보스 무기 데미지용 유효 강화
     * +1~+20: 실제 강화 / +21↑: 20+(e-20)×0.25 (+100→40)
     */
    function boss_raid_무기_유효강화($enhance): float {
        $e = (int)$enhance;
        if ($e < 1) {
            return 0.0;
        }
        $max = function_exists('강화_최대') ? (int)강화_최대() : 100;
        $e = min($e, $max);
        $ref = (int)BOSS_RAID_CLEAR_REF_ENHANCE;
        if ($e <= $ref) {
            return (float)$e;
        }
        $scale = (float)BOSS_RAID_WEAPON_SOFT_SCALE;
        return (float)$ref + ($e - $ref) * $scale;
    }
}

if (!function_exists('boss_raid_채굴공격횟수')) {
    /** 채굴 장비 레벨만큼 공격 가능 (Lv0=0 · 중장비 Lv10=10회) · 수리로 횟수 회복 없음 */
    function boss_raid_채굴공격횟수($mining_level): int {
        return max(0, (int)$mining_level);
    }
}

if (!function_exists('boss_raid_대박확률표')) {
    /**
     * 강화 구간별 대박타 확률(%) — max_e 이상이면 해당 pct
     * (위에서부터 매칭 · 숫자만 바꿔서 조정)
     * @return list<array{max:int,pct:float}>
     */
    function boss_raid_대박확률표(): array {
        return [
            ['max' => 29, 'pct' => 16.0], // +20~29
            ['max' => 39, 'pct' => 14.0], // +30~39
            ['max' => 49, 'pct' => 12.0], // +40~49
            ['max' => 59, 'pct' => 10.0], // +50~59
            ['max' => 69, 'pct' => 8.0],  // +60~69
            ['max' => 79, 'pct' => 6.0],  // +70~79
            ['max' => 89, 'pct' => 5.0],  // +80~89
            ['max' => 100, 'pct' => 4.0], // +90~100
        ];
    }
}

if (!function_exists('boss_raid_대박확률')) {
    /**
     * 무기 강화수치 → 대박타 확률(%)
     * +1~19=0% · +20↑=구간별 (boss_raid_대박확률표)
     */
    function boss_raid_대박확률($enhance): float {
        $e = (int)$enhance;
        $min_e = defined('BOSS_RAID_JACKPOT_MIN_ENHANCE')
            ? (int)BOSS_RAID_JACKPOT_MIN_ENHANCE
            : 20;
        if ($e < $min_e) {
            return 0.0;
        }
        $max_e = function_exists('강화_최대') ? (int)강화_최대() : 100;
        $e = min($e, $max_e);
        foreach (boss_raid_대박확률표() as $band) {
            $bandMax = (int)($band['max'] ?? 0);
            if ($e <= $bandMax) {
                return max(0.0, (float)($band['pct'] ?? 0));
            }
        }
        // 표 상한 초과(예: 강화 최대 확장) → 마지막 구간
        $표 = boss_raid_대박확률표();
        $last = $표 !== [] ? end($표) : null;
        return max(0.0, (float)($last['pct'] ?? 0));
    }
}

if (!function_exists('boss_raid_무기공격횟수')) {
    /** +1~15=4 · +16=5 · +17=6 · +18=7 · +19=8 · +20=10 · +21~30=12 · +31~40=14 · +41~50=16 · +51↑=18 · 단소×2 */
    function boss_raid_무기공격횟수($enhance, $weapon = null): int {
        $e = (int)$enhance;
        if ($e < (int)BOSS_RAID_MIN_ENHANCE) {
            return 0;
        }
        if ($e <= 15) {
            $n = 4;
        } elseif ($e === 16) {
            $n = 5;
        } elseif ($e === 17) {
            $n = 6;
        } elseif ($e === 18) {
            $n = 7;
        } elseif ($e === 19) {
            $n = 8;
        } elseif ($e === 20) {
            $n = 10;
        } elseif ($e <= 30) {
            $n = 12;
        } elseif ($e <= 40) {
            $n = 14;
        } elseif ($e <= 50) {
            $n = 16;
        } else {
            $n = 18;
        }
        if ($weapon !== null && boss_raid_단소무기인가($weapon)) {
            $n *= max(1, (int)BOSS_RAID_DANSO_WEAPON_COUNT_MULT);
        }
        return $n;
    }
}

if (!function_exists('boss_raid_무기유저_전부크리총합')) {
    /**
     * tb_member 무기 보유자(+1↑)의 풀커밋 기대딜 총합
     * 1인 = 횟수 × 평균타격(실제강화×10+50) × 대박기대배수 × 크리기대배수
     *  - 평균타격: 랜덤 [강화×10 ~ +100]의 기대값 = 강화×10+50
     *  - 대박: 확률 p_j 로 ×3~5(평균 4) → 배수 1 + p_j×(4-1)
     *  - 크리: 확률 p_c(낮 기준)로 무기 +20% → 배수 1 + p_c×0.20
     * (활 +10%는 스냅샷에서 무기 종류를 구분하지 않아 안전 여유로 미반영)
     * @return array{
     *   sum:int,weapon_n:int,attacker_n:int,
     *   one_hit_raw:int,one_hit_expected:int
     * }
     */
    function boss_raid_무기유저_전부크리총합(): array {
        $sum = 0.0;
        $oneHitRaw = 0.0;
        $oneHitExpected = 0.0;
        $weapon_n = 0;
        $attacker_n = 0;
        if (!function_exists('db_query')) {
            return [
                'sum' => 0,
                'weapon_n' => 0,
                'attacker_n' => 0,
                'one_hit_raw' => 0,
                'one_hit_expected' => 0,
            ];
        }
        $jackpotMeanMult = ((int)BOSS_RAID_JACKPOT_MULT_MIN + (int)BOSS_RAID_JACKPOT_MULT_MAX) / 2;
        $critProb = max(0, min(100, (int)BOSS_RAID_CRIT_PCT)) / 100;
        $critBonus = max(0, (int)BOSS_RAID_WEAPON_CRIT_MULT_PCT - 100) / 100;
        $critFactor = 1 + $critProb * $critBonus;
        $rs = @db_query("
          SELECT IFNULL(enhance, 0) AS e
          FROM tb_member
          WHERE TRIM(COALESCE(item, '')) <> ''
            AND (
              (IFNULL(무기타입,0) = 2 OR (
                IFNULL(무기타입,0) = 0 AND (
                  REPLACE(TRIM(item), ' ', '') LIKE '%단소%'
                  OR item LIKE '%젓가락%'
                  OR item LIKE '%리코더%'
                  OR item LIKE '%피리%'
                  OR item LIKE '%대금%'
                  OR item LIKE '%플루트%'
                  OR item LIKE '%생황%'
                  OR item LIKE '%나발%'
                  OR item LIKE '%태평소%'
                  OR item LIKE '%엑스칼리버%'
                )
              ))
              OR IFNULL(무기타입,0) = 1
              OR TRIM(item) LIKE '%활%'
              OR IFNULL(무기타입,0) = 3
              OR TRIM(item) LIKE '%마법%'
            )
        ");
        if (!$rs) {
            return [
                'sum' => 0,
                'weapon_n' => 0,
                'attacker_n' => 0,
                'one_hit_raw' => 0,
                'one_hit_expected' => 0,
            ];
        }
        while ($row = mysqli_fetch_assoc($rs)) {
            $weapon_n++;
            $e = (int)($row['e'] ?? 0);
            $hits = boss_raid_무기공격횟수($e);
            if ($hits < 1) {
                continue;
            }
            $meanHit = max(0, $e * 10 + 50);
            $jackpotProb = boss_raid_대박확률($e) / 100;
            $jackpotFactor = 1 + $jackpotProb * ($jackpotMeanMult - 1);
            $expectedPerHit = $meanHit * $jackpotFactor * $critFactor;
            $oneHitRaw += $meanHit;
            $oneHitExpected += $expectedPerHit;
            $sum += $hits * $expectedPerHit;
            $attacker_n++;
        }
        return [
            'sum' => (int)$sum,
            'weapon_n' => $weapon_n,
            'attacker_n' => $attacker_n,
            'one_hit_raw' => (int)round($oneHitRaw),
            'one_hit_expected' => (int)round($oneHitExpected),
        ];
    }
}

if (!function_exists('boss_raid_HP스냅샷_스키마')) {
    function boss_raid_HP스냅샷_스키마(): void {
        static $done = false;
        if ($done || !function_exists('db_select')) {
            return;
        }
        $done = true;
        foreach ([
            '보스HP_MIN' => "INT UNSIGNED NOT NULL DEFAULT 0",
            '보스HP_MAX' => "INT UNSIGNED NOT NULL DEFAULT 0",
            '보스HP_CENTER' => "INT UNSIGNED NOT NULL DEFAULT 0",
            '보스HP_스냅샷합' => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            '보스HP_스냅샷무기' => "INT UNSIGNED NOT NULL DEFAULT 0",
            '보스HP_스냅샷시각' => "DATETIME NULL DEFAULT NULL",
            '보스HP_공식버전' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
        ] as $col => $def) {
            $exists = @db_select("SHOW COLUMNS FROM config LIKE '{$col}'");
            if (!$exists) {
                @db_query("ALTER TABLE config ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}

if (!function_exists('boss_raid_HP고정범위_보장')) {
    /**
     * 1회성 스냅샷: 기대딜 총합 × 참여율(BOSS_RAID_PARTICIPATION_RATE) → 중앙 · ±15% 고정 min/max
     * @return array{min:int,max:int,center:int,sum:int,weapon_n:int,attacker_n:int,fresh:bool}
     */
    function boss_raid_HP고정범위_보장(): array {
        boss_raid_HP스냅샷_스키마();
        $row = @db_select("
          SELECT
            IFNULL(`보스HP_MIN`, 0) AS mn,
            IFNULL(`보스HP_MAX`, 0) AS mx,
            IFNULL(`보스HP_CENTER`, 0) AS cen,
            IFNULL(`보스HP_스냅샷합`, 0) AS sm,
            IFNULL(`보스HP_스냅샷무기`, 0) AS wn,
            IFNULL(`보스HP_공식버전`, 0) AS formula_ver
          FROM config LIMIT 1
        ") ?: [];
        $min = (int)($row['mn'] ?? 0);
        $max = (int)($row['mx'] ?? 0);
        $formulaVersion = (int)($row['formula_ver'] ?? 0);
        if ($min > 0 && $max >= $min && $formulaVersion >= (int)BOSS_RAID_HP_FORMULA_VERSION) {
            return [
                'min' => $min,
                'max' => $max,
                'center' => (int)($row['cen'] ?? (int)round(($min + $max) / 2)),
                'sum' => (int)($row['sm'] ?? 0),
                'weapon_n' => (int)($row['wn'] ?? 0),
                'attacker_n' => 0,
                'fresh' => false,
            ];
        }

        $tot = boss_raid_무기유저_전부크리총합();
        $sum = (int)$tot['sum'];
        $center = (int)round($sum * (float)BOSS_RAID_PARTICIPATION_RATE);
        if ($center < 1000) {
            // 데이터 없거나 전원 +1 미만 → 기존 대역 폴백
            $min = 45000;
            $max = 92000;
            $center = (int)round(($min + $max) / 2);
        } else {
            $frac = (float)BOSS_RAID_HP_RANGE_FRAC;
            $min = max(1000, (int)round($center * (1.0 - $frac)));
            $max = max($min + 1000, (int)round($center * (1.0 + $frac)));
        }
        $sum_sql = (int)$sum;
        $wn = (int)$tot['weapon_n'];
        @db_query("
          UPDATE config SET
            `보스HP_MIN` = {$min},
            `보스HP_MAX` = {$max},
            `보스HP_CENTER` = {$center},
            `보스HP_스냅샷합` = {$sum_sql},
            `보스HP_스냅샷무기` = {$wn},
            `보스HP_스냅샷시각` = NOW(),
            `보스HP_공식버전` = " . (int)BOSS_RAID_HP_FORMULA_VERSION . "
          LIMIT 1
        ");
        return [
            'min' => $min,
            'max' => $max,
            'center' => $center,
            'sum' => $sum,
            'weapon_n' => $wn,
            'attacker_n' => (int)$tot['attacker_n'],
            'fresh' => true,
        ];
    }
}

if (!function_exists('boss_raid_HP랜덤')) {
    /** 고정 min~max 범위에서 보스 HP 1회 추첨 */
    function boss_raid_HP랜덤(): int {
        $r = boss_raid_HP고정범위_보장();
        $min = max(1000, (int)$r['min']);
        $max = max($min, (int)$r['max']);
        return random_int($min, $max);
    }
}

if (!function_exists('boss_raid_HP고정범위_재계산')) {
    /** config 스냅샷을 지우고 tb_member 기준으로 다시 고정 */
    function boss_raid_HP고정범위_재계산(): array {
        boss_raid_HP스냅샷_스키마();
        @db_query("
          UPDATE config SET
            `보스HP_MIN` = 0,
            `보스HP_MAX` = 0,
            `보스HP_CENTER` = 0,
            `보스HP_스냅샷합` = 0,
            `보스HP_스냅샷무기` = 0,
            `보스HP_스냅샷시각` = NULL
          LIMIT 1
        ");
        return boss_raid_HP고정범위_보장();
    }
}

if (!function_exists('boss_raid_무기정보')) {
    /** @return array{enhance:int,item:string,durability:int} */
    function boss_raid_무기정보($nick): array {
        $esc = addslashes(trim((string)$nick));
        $row = db_select("SELECT IFNULL(enhance, 0) AS enhance, IFNULL(item, '') AS item, IFNULL(durability, 0) AS durability FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        return [
            'enhance' => (int)($row['enhance'] ?? 0),
            'item' => trim((string)($row['item'] ?? '')),
            'durability' => (int)($row['durability'] ?? 0),
        ];
    }
}

if (!function_exists('boss_raid_무기fx키')) {
    /**
     * 웹 타격 이펙트 키
     * @return 'sword'|'bow'|'magic'
     */
    function boss_raid_무기fx키($item): string {
        $item = trim((string)$item);
        if ($item !== '' && (mb_strpos($item, '활') !== false || strpos($item, '🏹') !== false)) {
            return 'bow';
        }
        if ($item !== '' && (mb_strpos($item, '마법') !== false || strpos($item, '🪄') !== false)) {
            return 'magic';
        }
        return 'sword';
    }
}

/**
 * 보스용 무기 내구 상태
 * @return array{current:int,max:int,pct:float,blocked:bool}
 */
if (!function_exists('boss_raid_무기내구_상태')) {
    function boss_raid_무기내구_상태($nick): array {
        $w = boss_raid_무기정보($nick);
        $current = max(0, (int)$w['durability']);
        $enhance = (int)$w['enhance'];
        $item = (string)$w['item'];
        $max = function_exists('무기_최대내구도') ? (int)무기_최대내구도($item, $enhance) : 0;
        if ($max < 1) {
            return [
                'current' => $current,
                'max' => 0,
                'pct' => 100.0,
                'blocked' => false,
            ];
        }
        $pct = ($current / $max) * 100.0;
        $minPct = max(0, (float)BOSS_RAID_ATTACK_MIN_DUR_PCT);
        // minPct=0 → 내구 0만 차단 / 그 외 → 비율 미만 차단
        $blocked = $minPct <= 0 ? ($current < 1) : ($pct < $minPct);
        return [
            'current' => $current,
            'max' => $max,
            'pct' => round($pct, 2),
            'blocked' => $blocked,
        ];
    }
}

/**
 * 보스 반격 — 최대 내구의 BOSS_RAID_COUNTER_DUR_PCT% 차감
 * @return array{ok:bool,cut:int,before:int,after:int,max:int,detail:string,msg:string}
 */
if (!function_exists('boss_raid_반격_무기내구차감')) {
    function boss_raid_반격_무기내구차감($nick): array {
        $nick = trim((string)$nick);
        $st = boss_raid_무기내구_상태($nick);
        $max = (int)$st['max'];
        $before = (int)$st['current'];
        $pct = max(1, min(100, (int)BOSS_RAID_COUNTER_DUR_PCT));
        if ($nick === '' || $before < 1) {
            $detail = '무기 내구도 0 (이미 손상)';
            return [
                'ok' => true,
                'cut' => 0,
                'before' => $before,
                'after' => $before,
                'max' => $max,
                'detail' => $detail,
                'msg' => "🐉 보스 반격! {$detail}",
            ];
        }
        $base = $max > 0 ? $max : $before;
        $cut = (int)round($base * ($pct / 100.0));
        if ($cut < 1) {
            $cut = 1;
        }
        $cut = min($cut, $before);
        $after = $before - $cut;
        $esc = addslashes($nick);
        db_query("UPDATE tb_member SET durability = {$after} WHERE name = '{$esc}' LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('보스반격-내구', $nick, (string)$pct . '%', 0, $cut);
        }
        $detail = "무기 내구 -{$cut} ({$before}→{$after}" . ($after < 1 ? ' · 수리 필요' : '') . ')';
        return [
            'ok' => true,
            'cut' => $cut,
            'before' => $before,
            'after' => $after,
            'max' => $max,
            'detail' => $detail,
            'msg' => "🐉 보스 반격! {$detail}",
        ];
    }
}

/**
 * 무기 공격 내구 차감 % 구간 — 강화 10단계마다 min/max +5
 * @return array{0:int,1:int} [minPct, maxPct]
 */
if (!function_exists('boss_raid_무기공격_내구차감구간')) {
    function boss_raid_무기공격_내구차감구간(int $enhance): array {
        $e = max(0, $enhance);
        $tier = (int)floor(max(0, $e - 1) / 10); // +0~10→0, +11~20→1, …
        $minPct = 5 + ($tier * 5);
        $maxPct = 10 + ($tier * 5);
        if ($maxPct < $minPct) {
            $maxPct = $minPct;
        }
        return [$minPct, $maxPct];
    }
}

/**
 * 무기 공격 1회 — 55% 확률로 차감 · 강화 구간별 최대 내구 % 랜덤
 * @return array{ok:bool,cut:int,before:int,after:int,max:int,pct:int,msg:string}
 */
if (!function_exists('boss_raid_무기공격_내구차감')) {
    function boss_raid_무기공격_내구차감($nick): array {
        $nick = trim((string)$nick);
        $w = boss_raid_무기정보($nick);
        $st = boss_raid_무기내구_상태($nick);
        $max = (int)$st['max'];
        $before = (int)$st['current'];
        $enhance = (int)($w['enhance'] ?? 0);
        if ($nick === '' || $max < 1 || $before < 1) {
            return [
                'ok' => false,
                'cut' => 0,
                'before' => $before,
                'after' => $before,
                'max' => $max,
                'pct' => 0,
                'msg' => '',
            ];
        }
        // 55% 차감 · 45% 미차감
        if (random_int(1, 100) <= 45) {
            return [
                'ok' => true,
                'cut' => 0,
                'before' => $before,
                'after' => $before,
                'max' => $max,
                'pct' => 0,
                'msg' => "무기 내구 유지 ({$before} · 45% 미차감)",
            ];
        }
        list($minPct, $maxPct) = boss_raid_무기공격_내구차감구간($enhance);
        $pct = ($minPct === $maxPct) ? $minPct : random_int($minPct, $maxPct);
        if ($pct < 1) {
            return [
                'ok' => false,
                'cut' => 0,
                'before' => $before,
                'after' => $before,
                'max' => $max,
                'pct' => 0,
                'msg' => '',
            ];
        }
        $cut = (int)round($max * ($pct / 100.0));
        if ($cut < 1) {
            $cut = 1;
        }
        $cut = min($cut, $before);
        $after = $before - $cut;
        $esc = addslashes($nick);
        db_query("UPDATE tb_member SET durability = {$after} WHERE name = '{$esc}' LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('보스무기공격-내구', $nick, (string)$pct . '%(+'.$enhance.')', 0, $cut);
        }
        return [
            'ok' => true,
            'cut' => $cut,
            'before' => $before,
            'after' => $after,
            'max' => $max,
            'pct' => $pct,
            'msg' => "무기 내구 -{$cut} ({$before}→{$after} · +{$enhance} {$pct}%)",
        ];
    }
}

/**
 * 보스 광역 — 참여자 전원 무기 내구(최대 기준) AOE% 차감. 미발동 시 null.
 * @return array{type:string,amount:string,detail:string,msg:string,hits:list}|null
 */
if (!function_exists('boss_raid_광역_내구차감')) {
    function boss_raid_광역_내구차감(int $boss_idx, string $triggerNick): ?array {
        $mid = (int)$boss_idx;
        $triggerNick = trim($triggerNick);
        if ($mid < 1) {
            return null;
        }
        $chance = max(0, min(100, (int)BOSS_RAID_AOE_PCT));
        if ($chance < 1 || random_int(1, 100) > $chance) {
            return null;
        }

        $targets = boss_raid_참여자목록($mid);
        if ($triggerNick !== '' && !in_array($triggerNick, $targets, true)) {
            $targets[] = $triggerNick;
        }
        if ($targets === []) {
            return null;
        }

        $pct = max(1, min(100, (int)BOSS_RAID_AOE_DUR_PCT));
        $hits = [];
        foreach ($targets as $n) {
            $n = trim((string)$n);
            if ($n === '') {
                continue;
            }
            $st = boss_raid_무기내구_상태($n);
            $max = (int)$st['max'];
            $cur = (int)$st['current'];
            if ($max < 1 || $cur < 1) {
                continue;
            }
            $cut = (int)round($max * ($pct / 100.0));
            if ($cut < 1) {
                $cut = 1;
            }
            $cut = min($cut, $cur);
            $left = $cur - $cut;
            $esc = addslashes($n);
            db_query("UPDATE tb_member SET durability = {$left} WHERE name = '{$esc}' LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('보스광역-내구', $n, (string)$pct . '%', 0, $cut);
            }
            $hits[] = [
                'nick' => $n,
                'cut' => $cut,
                'left' => $left,
                'max' => $max,
            ];
        }
        if ($hits === []) {
            return null;
        }

        $parts = [];
        foreach (array_slice($hits, 0, 12) as $h) {
            $parts[] = $h['nick'] . '-' . $h['cut'];
        }
        $more = count($hits) > 12 ? (' 외 ' . (count($hits) - 12) . '명') : '';
        $detail = '광역 내구! ' . implode(', ', $parts) . $more;
        $msg = '🐉 보스 광역 공격! 참여자 무기 내구도 하락 (' . count($hits) . "명)\n" . implode(', ', $parts) . $more;
        return [
            'type' => 'aoe',
            'amount' => (string)count($hits),
            'detail' => mb_substr($detail, 0, 180),
            'msg' => $msg,
            'hits' => $hits,
        ];
    }
}

if (!function_exists('boss_raid_채굴레벨')) {
    function boss_raid_채굴레벨($nick): int {
        // 상태 조회 핫패스: mining_tool.inc.php(~2천줄) 강제 로드하지 않음
        $esc = addslashes(trim((string)$nick));
        $row = @db_select("SELECT IFNULL(mining_tool, 0) AS lv FROM tb_member_mining WHERE nick = '{$esc}' LIMIT 1");
        $level = max(0, (int)($row['lv'] ?? 0));
        $max = function_exists('mining_tool_max_level') ? (int)mining_tool_max_level() : 14;
        return min($level, max(0, $max));
    }
}

if (!function_exists('boss_raid_채굴라벨')) {
    /** mining_tool.inc 없이 표시용 라벨 */
    function boss_raid_채굴라벨(int $lv): string {
        static $labels = [
            0 => '🥄숟가락', 1 => '🍴포크', 2 => '🥢젓가락', 3 => '🔪식칼', 4 => '🫕냄비',
            5 => '🍳프라이팬', 6 => '⛏️곡괭이', 7 => '🔨망치', 8 => '🛠️공구세트', 9 => '⚒️채굴망치',
            10 => '🏗️중장비', 11 => '💎다이아 곡괭이', 12 => '⚡레이저 채굴기', 13 => '🛸UFO 흡입기', 14 => '👑황금 숟가락',
        ];
        return $labels[$lv] ?? ('Lv' . $lv);
    }
}

if (!function_exists('boss_raid_채굴내구_로드')) {
    /** @return void */
    function boss_raid_채굴내구_로드(): void {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;
        foreach (['mining_durability.inc.php', 'mining_sync.inc.php', 'mining_storage.inc.php'] as $f) {
            $path = __DIR__ . '/' . $f;
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
}

if (!function_exists('boss_raid_채굴내구_조회')) {
    /** 동기화 반영 후 현재 채굴 내구도 */
    function boss_raid_채굴내구_조회($nick): float {
        boss_raid_채굴내구_로드();
        $nick = trim((string)$nick);
        if ($nick === '') {
            return 0.0;
        }
        if (function_exists('mining_sync_commit_elapsed')) {
            mining_sync_commit_elapsed($nick);
        }
        if (function_exists('mining_durability_ensure_column')) {
            mining_durability_ensure_column();
        }
        $esc = function_exists('mining_data_nick_esc')
            ? mining_data_nick_esc($nick)
            : addslashes($nick);
        if ($esc === '') {
            return 0.0;
        }
        $tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
        $row = @db_select("
            SELECT mining_tool,
                   IFNULL(mining_durability, 120) AS mining_durability
            FROM `{$tbl}`
            WHERE nick = '{$esc}'
            LIMIT 1
        ");
        if (function_exists('mining_durability_from_row')) {
            return mining_durability_from_row(is_array($row) ? $row : []);
        }
        $level = max(0, (int)($row['mining_tool'] ?? 0));
        $max = function_exists('mining_durability_max') ? mining_durability_max($level) : 120.0;
        if (!is_array($row)) {
            return $max;
        }
        return max(0.0, min($max, (float)($row['mining_durability'] ?? $max)));
    }
}

if (!function_exists('boss_raid_채굴내구_차감')) {
    /**
     * 보스 채굴 공격 후 내구도 차감 (최대 내구의 BOSS_RAID_MINING_DUR_PCT%)
     * @return array{ok:bool,before:float,after:float,cut:float,max:float,msg:string}
     */
    function boss_raid_채굴내구_차감($nick): array {
        boss_raid_채굴내구_로드();
        $nick = trim((string)$nick);
        $level = 0;
        if ($nick !== '' && function_exists('mining_tool_level_for_nick')) {
            $level = max(0, (int)mining_tool_level_for_nick($nick));
        } elseif ($nick !== '') {
            $escLv = function_exists('mining_data_nick_esc') ? mining_data_nick_esc($nick) : addslashes($nick);
            $tblLv = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
            $lvRow = @db_select("SELECT IFNULL(mining_tool, 0) AS mining_tool FROM `{$tblLv}` WHERE nick = '{$escLv}' LIMIT 1");
            $level = max(0, (int)($lvRow['mining_tool'] ?? 0));
        }
        $max = function_exists('mining_durability_max') ? (float)mining_durability_max($level) : 120.0;
        $pct = max(0, (int)BOSS_RAID_MINING_DUR_PCT);
        $cut = $max * ($pct / 100.0);
        if ($nick === '' || $cut <= 0) {
            return [
                'ok' => false,
                'before' => 0.0,
                'after' => 0.0,
                'cut' => 0.0,
                'max' => $max,
                'msg' => '',
            ];
        }
        $before = boss_raid_채굴내구_조회($nick);
        $after = max(0.0, $before - $cut);
        $esc = function_exists('mining_data_nick_esc')
            ? mining_data_nick_esc($nick)
            : addslashes($nick);
        $tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
        $dur_sql = function_exists('mining_durability_sql')
            ? mining_durability_sql($after, $level)
            : number_format($after, 4, '.', '');
        @db_query("
            UPDATE `{$tbl}`
            SET mining_durability = {$dur_sql}
            WHERE nick = '{$esc}'
            LIMIT 1
        ");
        $beforeDisp = function_exists('mining_durability_display_int')
            ? mining_durability_display_int($before, $level)
            : (int)floor($before);
        $afterDisp = function_exists('mining_durability_display_int')
            ? mining_durability_display_int($after, $level)
            : (int)floor($after);
        $msg = "채굴 내구도 {$pct}% 차감 ({$beforeDisp}→{$afterDisp})";
        return [
            'ok' => true,
            'before' => $before,
            'after' => $after,
            'cut' => $cut,
            'max' => $max,
            'msg' => $msg,
        ];
    }
}

if (!function_exists('boss_raid_데미지_계산')) {
    /**
     * @param 'weapon'|'mining'|'finish' $type
     * @return array<string,mixed>
     */
    function boss_raid_데미지_계산($type, $nick, $boss_idx = 0): array {
        $weapon = boss_raid_무기정보($nick);
        $mining_lv = boss_raid_채굴레벨($nick);
        $enhance = (int)$weapon['enhance'];
        $isDanso = boss_raid_단소무기인가($weapon);
        $isFinish = ($type === 'finish');
        $isWeaponLike = ($type === 'weapon' || $isFinish);

        // 참여자 수: 이미 공격한 인원 + 이번 공격자(아직 미기록이면 +1)
        $parts = 0;
        $mid = (int)$boss_idx;
        $hpPct = 100.0;
        if ($mid > 0) {
            $list = boss_raid_참여자목록($mid);
            $parts = count($list);
            $nickTrim = trim((string)$nick);
            $already = false;
            foreach ($list as $p) {
                if ((string)$p === $nickTrim) {
                    $already = true;
                    break;
                }
            }
            if (!$already && $nickTrim !== '') {
                $parts++;
            }
            $hpRow = @db_select("SELECT hp_now, hp_max FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
            $hpMax = max(0, (int)($hpRow['hp_max'] ?? 0));
            $hpNow = max(0, (int)($hpRow['hp_now'] ?? 0));
            $hpPct = $hpMax > 0 ? (($hpNow / $hpMax) * 100.0) : 0.0;
        }
        $baseCrit = boss_raid_새벽인가()
            ? (int)BOSS_RAID_CRIT_PCT_DAWN
            : (int)BOSS_RAID_CRIT_PCT;
        $critPct = min(100, max(0, $baseCrit));
        $crit = (random_int(1, 100) <= $critPct);
        $effEnhance = boss_raid_무기_유효강화($enhance);
        $weaponFx = boss_raid_무기fx키($weapon['item'] ?? '');
        $bowBonusPct = 0;
        $baseDmgMin = 0;
        $baseDmgMax = 0;
        $finishMult = 1;
        if ($isWeaponLike) {
            // 실제 강화수치 기준: +33이면 330~430, +44이면 440~540
            $baseDmgMin = max(0, $enhance * 10);
            $baseDmgMax = $baseDmgMin + 100;
            $dmg = random_int($baseDmgMin, $baseDmgMax);
            $mult = 0;
            $effEnhance = (float)$enhance;
            if ($weaponFx === 'bow') {
                $bowBonusPct = max(0, (int)BOSS_RAID_BOW_DMG_BONUS_PCT);
                if ($bowBonusPct > 0) {
                    $dmg = max(0, (int)round($dmg * (100 + $bowBonusPct) / 100));
                }
            }
            if ($isFinish) {
                $finishMult = random_int(
                    max(1, (int)BOSS_RAID_DANSO_FINISH_MULT_MIN),
                    max(1, (int)BOSS_RAID_DANSO_FINISH_MULT_MAX)
                );
                $dmg = max(0, $dmg * $finishMult);
            }
        } else {
            $mult = random_int(30, 40);
            $dmg = max(0, $mining_lv * $mult);
            $effEnhance = (float)$mining_lv;
        }
        // 대박타(잭팟) — 일반 무기 공격만 · 필살은 이미 ×3~6
        $jackpot = false;
        $jackpotMul = 1;
        if ($type === 'weapon') {
            $jpPct = boss_raid_대박확률($enhance);
            if ($jpPct > 0 && random_int(1, 10000) <= (int)round($jpPct * 100)) {
                $jackpot = true;
                $jackpotMul = random_int((int)BOSS_RAID_JACKPOT_MULT_MIN, (int)BOSS_RAID_JACKPOT_MULT_MAX);
                $dmg = max(0, $dmg * $jackpotMul);
            }
        }
        $critMul = 100;
        if ($crit) {
            // 무기/필살: 고정 +20% / 채굴: 랜덤 +10~20%
            $critMul = $isWeaponLike
                ? (int)BOSS_RAID_WEAPON_CRIT_MULT_PCT
                : random_int((int)BOSS_RAID_CRIT_MULT_MIN_PCT, (int)BOSS_RAID_CRIT_MULT_MAX_PCT);
            $dmg = max(0, (int)round($dmg * $critMul / 100));
        }
        $execute = false;
        $executeMult = 1;
        if ($isDanso && $isWeaponLike && $hpPct <= (float)BOSS_RAID_DANSO_EXECUTE_HP_PCT) {
            $executeMult = max(1, (int)BOSS_RAID_DANSO_EXECUTE_MULT);
            $dmg = max(0, $dmg * $executeMult);
            $execute = $executeMult > 1;
        }
        return [
            'damage' => $dmg,
            'crit_mult_pct' => $critMul,
            'jackpot' => $jackpot,
            'jackpot_mult' => $jackpotMul,
            'base_damage_min' => $baseDmgMin,
            'base_damage_max' => $baseDmgMax,
            'enhance' => $enhance,
            'effective_enhance' => $effEnhance,
            'mining_level' => $isFinish ? $finishMult : $mining_lv,
            'mult' => $isFinish ? $finishMult : $mult,
            'crit' => $crit,
            'crit_pct' => $critPct,
            'participants' => $parts,
            'weapon_fx' => $weaponFx,
            'bow_bonus_pct' => $bowBonusPct,
            'finish_mult' => $finishMult,
            'execute' => $execute,
            'execute_mult' => $executeMult,
        ];
    }
}

if (!function_exists('boss_raid_마지막종료행')) {
    function boss_raid_마지막종료행(): array {
        $row = db_select("SELECT * FROM tb_boss_raid WHERE status IN (1, 2) ORDER BY idx DESC LIMIT 1");
        return is_array($row) ? $row : [];
    }
}

/**
 * 보스 기록 정리
 * - 기본: 진행 중(status=0) + 직전 종료 보스 1개의 타격/광역/드랍만 유지, 나머지 전부 삭제
 * - $wipePrevDetails=true (신규 소환 시): 직전 상세 기록도 삭제(쿨다운용 종료 행 1개만 남김)
 */
if (!function_exists('boss_raid_종료보스_기록정리')) {
    function boss_raid_종료보스_기록정리(bool $wipePrevDetails = false): void {
        $keep = [];

        $active = @db_select("SELECT idx FROM tb_boss_raid WHERE status = 0 ORDER BY idx DESC LIMIT 1");
        $activeId = (int)($active['idx'] ?? 0);
        if ($activeId > 0) {
            $keep[$activeId] = true;
        }

        $lastEnded = @db_select("SELECT idx FROM tb_boss_raid WHERE status IN (1, 2) ORDER BY idx DESC LIMIT 1");
        $endedId = (int)($lastEnded['idx'] ?? 0);
        if ($endedId > 0 && !$wipePrevDetails) {
            $keep[$endedId] = true;
        }

        // 상세 기록: keep 외 전부 삭제 (고아 행 포함)
        if ($keep === []) {
            db_query("DELETE FROM tb_boss_raid_hit");
            db_query("DELETE FROM tb_boss_raid_player");
            db_query("DELETE FROM tb_boss_raid_counter");
            db_query("DELETE FROM tb_boss_raid_drop");
        } else {
            $in = implode(',', array_map('intval', array_keys($keep)));
            db_query("DELETE FROM tb_boss_raid_hit WHERE boss_idx NOT IN ({$in})");
            db_query("DELETE FROM tb_boss_raid_player WHERE boss_idx NOT IN ({$in})");
            db_query("DELETE FROM tb_boss_raid_counter WHERE boss_idx NOT IN ({$in})");
            db_query("DELETE FROM tb_boss_raid_drop WHERE boss_idx NOT IN ({$in})");
        }

        // 종료 행: 최신 1개만 유지 (쿨다운·직전내역용)
        if ($endedId > 0) {
            db_query("DELETE FROM tb_boss_raid WHERE status IN (1, 2) AND idx <> {$endedId}");
            if ($wipePrevDetails) {
                db_query("DELETE FROM tb_boss_raid_hit WHERE boss_idx = {$endedId}");
                db_query("DELETE FROM tb_boss_raid_player WHERE boss_idx = {$endedId}");
                db_query("DELETE FROM tb_boss_raid_counter WHERE boss_idx = {$endedId}");
                db_query("DELETE FROM tb_boss_raid_drop WHERE boss_idx = {$endedId}");
            }
        } else {
            db_query("DELETE FROM tb_boss_raid WHERE status IN (1, 2)");
        }
    }
}

/**
 * 대기 중 직전 보스 결과 (기여·반격) — 다음 출현 전까지 유지
 * @return array{prev_result:bool,prev_result_label:string,boss_idx:int,rank:list,participants:int,counters:list,drops:list,drop_latest:?array}
 */
if (!function_exists('boss_raid_직전결과_페이로드')) {
    function boss_raid_직전결과_페이로드($nick = ''): array {
        $empty = [
            'prev_result' => false,
            'prev_result_label' => '',
            'boss_idx' => 0,
            'rank' => [],
            'participants' => 0,
            'counters' => [],
            'my_hits' => [],
            'drops' => [],
            'drop_latest' => null,
        ];
        $last = boss_raid_마지막종료행();
        $mid = (int)($last['idx'] ?? 0);
        if ($mid < 1) {
            return $empty;
        }
        $def = boss_raid_종류((string)($last['boss_key'] ?? ''));
        $st = (int)($last['status'] ?? 0);
        $결과 = ($st === 1) ? '처치' : (($st === 2) ? '타임오버' : '종료');
        $label = trim(($def['emoji'] ?? '') . ' ' . ($def['name'] ?? '보스')) . " ({$결과})";
        $drops = function_exists('boss_raid_드랍이력') ? boss_raid_드랍이력($mid, 12) : [];
        $myHits = function_exists('boss_raid_내타격이력')
            ? boss_raid_내타격이력($mid, $nick, 40)
            : [];
        return [
            'prev_result' => true,
            'prev_result_label' => $label,
            'boss_idx' => $mid,
            'rank' => boss_raid_기여순위_표시($mid, 20),
            'participants' => boss_raid_참여자수($mid),
            'counters' => boss_raid_반격이력($mid, 40),
            'my_hits' => $myHits,
            'drops' => $drops,
            'drop_latest' => $drops[0] ?? null,
        ];
    }
}

if (!function_exists('boss_raid_스폰가능시각')) {
    /** @return int unix ts · 0이면 즉시 가능 */
    function boss_raid_스폰가능시각(): int {
        $last = boss_raid_마지막종료행();
        if (empty($last['idx'])) {
            return 0;
        }
        if (!empty($last['next_spawn_at'])) {
            $t = strtotime((string)$last['next_spawn_at']);
            return $t !== false ? $t : 0;
        }
        $base = !empty($last['killed_at']) ? (string)$last['killed_at'] : (string)($last['created_at'] ?? '');
        $t = $base !== '' ? strtotime($base) : false;
        if ($t === false) {
            return 0;
        }
        return $t + (int)BOSS_RAID_RESPAWN_SEC_MIN;
    }
}

if (!function_exists('boss_raid_본방알림')) {
    function boss_raid_본방알림($msg, $item = 'boss_raid'): bool {
        $msg = trim((string)$msg);
        if ($msg === '') {
            return false;
        }
        if (function_exists('본방알림_등록')) {
            return (bool)본방알림_등록($msg, $item);
        }
        $msg_esc = addslashes($msg);
        $item_esc = addslashes((string)$item);
        return (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
    }
}

if (!function_exists('boss_raid_홍보알림')) {
    /** 홍보방(info2) 알림 — 처치 결과 등 */
    function boss_raid_홍보알림($msg, $item = 'boss_raid'): bool {
        $msg = trim((string)$msg);
        if ($msg === '') {
            return false;
        }
        if (function_exists('info2알림_등록')) {
            return (bool)info2알림_등록($msg, $item);
        }
        $msg_esc = addslashes($msg);
        $item_esc = addslashes((string)$item);
        @db_query("
          CREATE TABLE IF NOT EXISTS tb_info2_alarm (
            idx INT AUTO_INCREMENT PRIMARY KEY,
            status TINYINT NOT NULL DEFAULT 0,
            msg TEXT NOT NULL,
            item VARCHAR(64) NOT NULL DEFAULT '',
            regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY status_idx (status)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return (bool)@db_query("INSERT INTO tb_info2_alarm SET status = 0, msg = '{$msg_esc}', item = '{$item_esc}', regdate = NOW()");
    }
}

if (!function_exists('boss_raid_출현알림')) {
    function boss_raid_출현알림(array $boss): void {
        $def = boss_raid_종류((string)($boss['boss_key'] ?? ''));
        $hp = (int)($boss['hp_max'] ?? $def['hp']);
        $pctLabel = boss_raid_보상비율문구((float)($def['reward_pct'] ?? BOSS_RAID_REWARD_PCT));
        $분 = boss_raid_제한분();
        $item = 'boss_spawn_' . $def['key'];
        $link = 'http://49.247.160.164/page/boss.php';

        // 홍보방: 상세
        $msg = "{$def['emoji']} {$def['name']} 출현!\n"
            . "HP " . number_format($hp) . " · 제한 {$분}분 · 보상 본방냥 {$pctLabel}\n";
        $msg .= "참가: 무기 +1↑ · 가방 → [보스]";
        if (!empty($def['blurb'])) {
            $msg .= "\n" . $def['blurb'];
        }
        $msg .= "\n{$link}";
        boss_raid_홍보알림($msg, $item);

        // 본방: 간략
        boss_raid_본방알림(
            "{$def['emoji']} {$def['name']} 보스출현! · 제한 {$분}분 · 가방 → [보스]\n{$link}",
            $item
        );
    }
}

if (!function_exists('boss_raid_처치알림')) {
    /**
     * @param array{
     *   reward?:int,reward_pct?:float,participants?:list<string>,msg?:string,
     *   mvp_nick?:string,mvp_damage?:int,mvp_bonus?:int,
     *   shard_rewards?:list<array{nick:string,shards:int,place:int}>,
     *   item_rewards?:list<array{nick:string,item:string}>
     * } $kill_info
     */
    function boss_raid_처치알림($boss_idx, array $kill_info): void {
        $mid = (int)$boss_idx;
        $bossRow = db_select("SELECT boss_key, next_spawn_at FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        $def = boss_raid_종류((string)($bossRow['boss_key'] ?? ''));
        $pct = (float)($kill_info['reward_pct'] ?? $def['reward_pct'] ?? BOSS_RAID_REWARD_PCT);
        $pctLabel = boss_raid_보상비율문구($pct);
        $reward = (int)($kill_info['reward'] ?? 0);
        $participants = $kill_info['participants'] ?? [];
        if (!is_array($participants)) {
            $participants = [];
        }
        $cnt = count($participants);
        $mvpNick = trim((string)($kill_info['mvp_nick'] ?? ''));
        $mvpDmg = (int)($kill_info['mvp_damage'] ?? 0);
        $mvpBonus = (int)($kill_info['mvp_bonus'] ?? 0);
        $msg = "{$def['emoji']} {$def['name']} 처치!\n";
        if ($cnt > 0) {
            $pool = (int)($kill_info['reward_pool'] ?? $kill_info['total_paid_amount'] ?? 0);
            $msg .= "참여자 {$cnt}명 · 보상풀 " . number_format($pool) . "냥 (1인기준 " . number_format($reward) . " · 본방냥 {$pctLabel})\n";
            $msg .= "기여(데미지) 비율로 분배\n";
            if ($mvpNick !== '' && $mvpBonus > 0) {
                $msg .= "🥇 MVP {$mvpNick} (타격 " . number_format($mvpDmg) . ") · +" . number_format($mvpBonus) . "냥 추가!\n";
            }
            $msg .= "✨ 은총조각 · 1등5 · 2등4 · 3등3 · 나머지 랜덤아이템 1개\n";
            $shards = $kill_info['shard_rewards'] ?? [];
            if (is_array($shards)) {
                foreach ($shards as $sr) {
                    $place = (int)($sr['place'] ?? 0);
                    if ($place >= 1 && $place <= 3) {
                        $msg .= "  {$place}등 {$sr['nick']} · 조각 " . (int)$sr['shards'] . "\n";
                    }
                }
            }
            $items = $kill_info['item_rewards'] ?? [];
            if (is_array($items) && $items !== []) {
                $itemLines = [];
                foreach (array_slice($items, 0, 15) as $ir) {
                    $in = trim((string)($ir['item'] ?? ''));
                    $nn = trim((string)($ir['nick'] ?? ''));
                    if ($nn === '' || $in === '') {
                        continue;
                    }
                    $itemLines[] = "{$nn} · {$in}";
                }
                if ($itemLines !== []) {
                    $msg .= "🎁 랜덤아이템:\n" . implode("\n", $itemLines) . "\n";
                    if (count($items) > 15) {
                        $msg .= "외 " . (count($items) - 15) . "명\n";
                    }
                }
            }
            $pays = $kill_info['reward_pays'] ?? [];
            if (is_array($pays) && $pays !== []) {
                $payLines = [];
                foreach (array_slice($pays, 0, 15) as $p) {
                    $amt = (int)($p['amount'] ?? 0);
                    if ($amt < 1) {
                        continue;
                    }
                    $place = (int)($p['place'] ?? 0);
                    $share = (float)($p['share_pct'] ?? 0);
                    $tag = $place > 0
                        ? ($place . '등 ' . rtrim(rtrim(number_format($share, 1, '.', ''), '0'), '.') . '%')
                        : '기여';
                    $payLines[] = ($p['nick'] ?? '') . ' +' . number_format($amt) . "({$tag})";
                }
                if ($payLines !== []) {
                    $msg .= "보상:\n" . implode("\n", $payLines);
                    if (count($pays) > 15) {
                        $msg .= "\n외 " . (count($pays) - 15) . '명';
                    }
                }
            } else {
                $names = array_slice($participants, 0, 20);
                $msg .= "보상:\n" . implode("\n", $names);
                if ($cnt > 20) {
                    $msg .= "\n외 " . ($cnt - 20) . '명';
                }
            }
            $loot = $kill_info['loot_share'] ?? null;
            if (is_array($loot) && !empty($loot['msg'])) {
                $msg .= "\n" . $loot['msg'];
            }
        } else {
            $msg .= '참여자 없음';
            $loot = $kill_info['loot_share'] ?? null;
            if (is_array($loot) && !empty($loot['msg'])) {
                $msg .= "\n" . $loot['msg'];
            }
        }
        $spawnSec = 0;
        if (!empty($bossRow['next_spawn_at'])) {
            $spawnTs = strtotime((string)$bossRow['next_spawn_at']);
            if ($spawnTs !== false) {
                $spawnSec = max(0, $spawnTs - time());
            }
        }
        $msg .= $spawnSec > 0
            ? ("\n다음 출현: " . boss_raid_초시분초($spawnSec) . " 뒤")
            : "\n다음 출현: 3~5시간 뒤";
        boss_raid_홍보알림($msg, 'boss_kill_' . $def['key']);
    }
}

if (!function_exists('boss_raid_스폰락_획득')) {
    /** @return bool */
    function boss_raid_스폰락_획득(int $timeoutSec = 5): bool {
        $timeoutSec = max(1, min(15, $timeoutSec));
        $row = @db_select("SELECT GET_LOCK('boss_raid_spawn', {$timeoutSec}) AS got");
        return (int)($row['got'] ?? 0) === 1;
    }
}

if (!function_exists('boss_raid_스폰락_해제')) {
    function boss_raid_스폰락_해제(): void {
        @db_select("SELECT RELEASE_LOCK('boss_raid_spawn') AS r");
    }
}

if (!function_exists('boss_raid_진행중_전부종료')) {
    /**
     * 진행 중(status=0) 보스 즉시 종료 — 처치/타임오버/강제소환 직전 중복 정리
     * @param int $exceptIdx 유지할 idx (0이면 전부 종료)
     * @param bool $overwriteNext true면 next_spawn_at 강제 덮어쓰기
     */
    function boss_raid_진행중_전부종료(int $exceptIdx = 0, ?string $nextSpawnAt = null, bool $overwriteNext = false): void {
        $next = $nextSpawnAt !== null && trim($nextSpawnAt) !== ''
            ? trim($nextSpawnAt)
            : date('Y-m-d H:i:s', time() + (int)BOSS_RAID_RESPAWN_SEC_MIN);
        $next_esc = addslashes($next);
        $exceptIdx = (int)$exceptIdx;
        $nextSql = $overwriteNext
            ? "'{$next_esc}'"
            : "IFNULL(next_spawn_at, '{$next_esc}')";
        if ($exceptIdx > 0) {
            @db_query("
              UPDATE tb_boss_raid
              SET status = 2, hp_now = 0,
                  killed_at = IFNULL(killed_at, NOW()),
                  next_spawn_at = {$nextSql}
              WHERE status = 0 AND idx <> {$exceptIdx}
            ");
        } else {
            @db_query("
              UPDATE tb_boss_raid
              SET status = 2, hp_now = 0,
                  killed_at = IFNULL(killed_at, NOW()),
                  next_spawn_at = {$nextSql}
              WHERE status = 0
            ");
        }
    }
}

if (!function_exists('boss_raid_신규소환')) {
    /**
     * @param bool $notify 출현 알림
     * @param bool $forceAdmin true면 쿨다운 무시(관리자 `.보스출현`/리셋)
     */
    function boss_raid_신규소환($boss_key = null, $notify = true, bool $forceAdmin = false): array {
        $locked = boss_raid_스폰락_획득(5);
        try {
            // 이미 진행 중이면 추가 생성 금지
            $active = @db_select("SELECT * FROM tb_boss_raid WHERE status = 0 ORDER BY idx DESC LIMIT 1");
            if (!empty($active['idx'])) {
                return is_array($active) ? $active : [];
            }

            // 쿨다운(다음 타임) 전이면 소환 금지 — 관리자 강제만 예외
            if (!$forceAdmin) {
                $spawnAt = boss_raid_스폰가능시각();
                if ($spawnAt > time()) {
                    return [];
                }
            }

            // 새 보스 출현 → 직전 상세 기록 삭제 · 쿨다운용 종료 행만 유지
            if (function_exists('boss_raid_종료보스_기록정리')) {
                boss_raid_종료보스_기록정리(true);
            }
            $key = $boss_key !== null && trim((string)$boss_key) !== ''
                ? trim((string)$boss_key)
                : boss_raid_다음종류키();
            $def = boss_raid_종류($key);
            $key = (string)$def['key'];
            $hp = boss_raid_출현HP((int)$def['hp']);
            $key_esc = addslashes($key);
            $ends = date('Y-m-d H:i:s', time() + boss_raid_전투초());
            $now = date('Y-m-d H:i:s');

            global $conn;
            db_query("
              INSERT INTO tb_boss_raid (
                status, boss_key, hp_max, hp_now, fight_ends_at, next_spawn_at,
                last_attack_at, last_regen_at, last_tick_alarm_at
              )
              SELECT 0, '{$key_esc}', {$hp}, {$hp}, '{$ends}', NULL, '{$now}', '{$now}', '{$now}'
              WHERE NOT EXISTS (SELECT 1 FROM tb_boss_raid br WHERE br.status = 0 LIMIT 1)
            ");
            $inserted = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            $row = db_select("SELECT * FROM tb_boss_raid WHERE status = 0 ORDER BY idx DESC LIMIT 1");
            $row = is_array($row) ? $row : [];
            // 혹시 레이스로 여러 개면 최신만 남김
            if (!empty($row['idx'])) {
                boss_raid_진행중_전부종료((int)$row['idx'], date('Y-m-d H:i:s', time() + (int)BOSS_RAID_RESPAWN_SEC_MIN));
                $row = db_select("SELECT * FROM tb_boss_raid WHERE idx = " . (int)$row['idx'] . " LIMIT 1") ?: $row;
            }
            if ($notify && $inserted && !empty($row['idx'])) {
                boss_raid_출현알림($row);
            }
            return $inserted ? $row : [];
        } finally {
            if ($locked) {
                boss_raid_스폰락_해제();
            }
        }
    }
}

if (!function_exists('boss_raid_진행알림_틱')) {
    /**
     * 진행 중 보스: 1분마다 현황 알림 → tb_info2_alarm (홍보방)
     * info2 폴링·보스 페이지·크론에서 호출
     */
    function boss_raid_진행알림_틱(): void {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        try {
            boss_raid_테이블_보장();
        } catch (Throwable $e) {
            return;
        }

        $row = @db_select("SELECT * FROM tb_boss_raid WHERE status = 0 ORDER BY idx DESC LIMIT 1");
        if (empty($row['idx'])) {
            return;
        }
        $mid = (int)$row['idx'];
        $ends = !empty($row['fight_ends_at']) ? strtotime((string)$row['fight_ends_at']) : false;
        if ($ends !== false && $ends <= time()) {
            boss_raid_타임오버_처리($row);
            return;
        }
        $row = boss_raid_회복_동기화($row);

        // 광역 보호는 알림 쿨다운과 무관하게 매 틱 동기화
        $freshAoe = @db_select("SELECT * FROM tb_boss_raid WHERE idx = {$mid} AND status = 0 LIMIT 1");
        if (!empty($freshAoe['idx'])) {
            boss_raid_광역보호_동기화($freshAoe);
        }

        $sec = max(30, (int)BOSS_RAID_TICK_ALARM_SEC);
        global $conn;
        @db_query("
          UPDATE tb_boss_raid
          SET last_tick_alarm_at = NOW()
          WHERE idx = {$mid} AND status = 0
            AND (
              last_tick_alarm_at IS NULL
              OR last_tick_alarm_at <= DATE_SUB(NOW(), INTERVAL {$sec} SECOND)
            )
          LIMIT 1
        ");
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$ok) {
            return;
        }

        // 최신 HP 재조회
        $fresh = @db_select("SELECT * FROM tb_boss_raid WHERE idx = {$mid} AND status = 0 LIMIT 1");
        if (empty($fresh['idx'])) {
            return;
        }
        $def = boss_raid_종류((string)($fresh['boss_key'] ?? ''));
        $hp_max = max(1, (int)($fresh['hp_max'] ?? $def['hp']));
        $hp_now = max(0, (int)($fresh['hp_now'] ?? 0));
        $pct = round(($hp_now / $hp_max) * 100, 1);
        $left = ($ends !== false) ? max(0, $ends - time()) : 0;
        $leftM = (int)floor($left / 60);
        $leftS = $left % 60;
        $parts = count(boss_raid_참여자목록($mid));
        $aoeN = (int)($fresh['aoe_protect_count'] ?? 0);
        $aoeMax = (int)BOSS_RAID_AOE_PROTECT_MAX;
        $top = boss_raid_기여순위($mid, 1);
        $mvpLine = '';
        if (!empty($top[0]['nick']) && (int)($top[0]['damage'] ?? 0) > 0) {
            $mvpLine = "\n🥇 현재 1등 {$top[0]['nick']} (타격 " . number_format((int)$top[0]['damage']) . ')';
        }

        $msg = "⚔ {$def['emoji']} {$def['name']} 진행 중!\n"
            . "HP " . number_format($hp_now) . " / " . number_format($hp_max) . " ({$pct}%)\n"
            . "남은 시간 {$leftM}분 {$leftS}초 · 참여자 {$parts}명 · 광역 {$aoeN}/{$aoeMax}"
            . $mvpLine
            . "\n가방 → [보스]";
        if (function_exists('info2알림_등록')) {
            info2알림_등록($msg, 'boss_tick_' . $def['key']);
        } else {
            // fallback: function.php 미로드 시에도 큐 적재
            @db_query("
              CREATE TABLE IF NOT EXISTS tb_info2_alarm (
                idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
                status TINYINT NOT NULL DEFAULT 0,
                msg TEXT NOT NULL,
                item VARCHAR(64) NOT NULL DEFAULT 'system',
                regdate DATETIME NOT NULL,
                PRIMARY KEY (idx),
                KEY ix_status_reg (status, regdate, idx)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $msg_esc = addslashes($msg);
            $item_esc = addslashes('boss_tick_' . $def['key']);
            @db_query("INSERT INTO tb_info2_alarm SET status = 0, msg = '{$msg_esc}', item = '{$item_esc}', regdate = NOW()");
        }
    }
}

if (!function_exists('boss_raid_HP_동기화')) {
    /** 구버전(HP 10000·키 없음)만 종류 정의로 보정 */
    function boss_raid_HP_동기화(array $boss): array {
        $mid = (int)($boss['idx'] ?? 0);
        if ($mid < 1) {
            return $boss;
        }
        $key = trim((string)($boss['boss_key'] ?? ''));
        if ($key === '' || !isset(boss_raid_종류목록()[$key])) {
            $key = 'bangryong';
            $key_esc = addslashes($key);
            db_query("UPDATE tb_boss_raid SET boss_key = '{$key_esc}' WHERE idx = {$mid} LIMIT 1");
            $boss['boss_key'] = $key;
        }
        $oldMax = (int)($boss['hp_max'] ?? 0);
        if ($oldMax !== 10000) {
            return $boss;
        }
        $def = boss_raid_종류($key);
        $design = (int)$def['hp'];
        $oldNow = max(0, (int)($boss['hp_now'] ?? 0));
        $newNow = ($oldNow >= $oldMax)
            ? $design
            : max(0, (int)round($oldNow * ($design / max(1, $oldMax))));
        db_query("
          UPDATE tb_boss_raid
          SET hp_max = {$design}, hp_now = {$newNow}
          WHERE idx = {$mid} AND status = 0
          LIMIT 1
        ");
        $boss['hp_max'] = $design;
        $boss['hp_now'] = $newNow;
        return $boss;
    }
}

if (!function_exists('boss_raid_강제소환')) {
    /**
     * 관리자 `.보스출현` — 랜덤 보스 즉시 소환 · 공창 알림 · 제한시간 시작
     * @return array{ok:bool,data:string,boss?:array,state?:array}
     */
    function boss_raid_강제소환($nick = ''): array {
        boss_raid_테이블_보장();
        $now = date('Y-m-d H:i:s');
        // 진행 중 전부 종료 후 강제 소환 (쿨다운 무시)
        boss_raid_진행중_전부종료(0, $now, true);
        $key = boss_raid_랜덤종류키();
        $boss = boss_raid_신규소환($key, true, true);
        if (empty($boss['idx'])) {
            return [
                'ok' => false,
                'data' => '보스 소환에 실패했어요. 잠시 후 다시 시도해 주세요.',
            ];
        }
        $def = boss_raid_종류((string)($boss['boss_key'] ?? $key));
        $hp = (int)($boss['hp_max'] ?? $def['hp']);
        $분 = boss_raid_제한분();
        $msg = "{$def['emoji']} {$def['name']} 출현! (랜덤)\n"
            . "HP " . number_format($hp) . " · 제한 {$분}분\n";
        $msg .= ($def['blurb'] !== '' ? $def['blurb'] . "\n" : '')
            . "참가: 무기 +1↑ · 가방 → [보스]\n"
            . "공창 알림 등록 완료";
        $out = [
            'ok' => true,
            'data' => $msg,
            'boss' => $boss,
        ];
        $nick = trim((string)$nick);
        if ($nick !== '') {
            $out['state'] = boss_raid_상태_페이로드($nick);
        }
        return $out;
    }
}

if (!function_exists('boss_raid_초기화')) {
    /**
     * 진행 중 보스: 공격이력 삭제 · HP 풀회복 · 제한시간 리셋
     * 진행 중 없으면 신규 소환
     * @return array{ok:bool,data:string,boss?:array}
     */
    function boss_raid_초기화($nick = ''): array {
        boss_raid_테이블_보장();
        $st = boss_raid_활성_상태();
        $boss = $st['boss'] ?? [];
        if (($st['phase'] ?? '') !== 'fighting' || empty($boss['idx'])) {
            return boss_raid_강제소환($nick);
        }

        $mid = (int)$boss['idx'];
        $def = boss_raid_종류((string)($boss['boss_key'] ?? ''));
        $hp = boss_raid_출현HP((int)$def['hp']);
        $ends = date('Y-m-d H:i:s', time() + boss_raid_전투초());

        db_query("DELETE FROM tb_boss_raid_hit WHERE boss_idx = {$mid}");
        db_query("DELETE FROM tb_boss_raid_player WHERE boss_idx = {$mid}");
        db_query("DELETE FROM tb_boss_raid_counter WHERE boss_idx = {$mid}");
        db_query("DELETE FROM tb_boss_raid_drop WHERE boss_idx = {$mid}");
        db_query("
          UPDATE tb_boss_raid
          SET status = 0, hp_max = {$hp}, hp_now = {$hp}, fight_ends_at = '{$ends}', next_spawn_at = NULL, killed_at = NULL,
              aoe_protect_count = 0, last_attack_at = NOW(), last_regen_at = NOW(), last_tick_alarm_at = NULL
          WHERE idx = {$mid}
          LIMIT 1
        ");

        $fresh = db_select("SELECT * FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        $msg = "🔄 보스 초기화! {$def['emoji']} {$def['name']}\n"
            . "공격이력 삭제 · HP " . number_format($hp) . " 회복 · 제한 " . boss_raid_제한분() . "분 리셋";
        $out = [
            'ok' => true,
            'data' => $msg,
            'boss' => is_array($fresh) ? $fresh : $boss,
        ];
        $nick = trim((string)$nick);
        if ($nick !== '') {
            $out['state'] = boss_raid_상태_페이로드($nick);
        }
        return $out;
    }
}

if (!function_exists('boss_raid_타임오버_처리')) {
    /** 30분 실패 → 다음 출현까지 기여·반격 기록 유지 · 3~5시간 후 다음 보스(로테이션) */
    function boss_raid_타임오버_처리(array $boss): array {
        $mid = (int)($boss['idx'] ?? 0);
        if ($mid < 1 || (int)($boss['status'] ?? -1) !== 0) {
            return $boss;
        }
        $def = boss_raid_종류((string)($boss['boss_key'] ?? ''));
        // 기록은 지우지 않음 — 대기 중 UI에서 직전 결과로 표시 · 다음 보스 출현 시 정리
        $respawnSec = boss_raid_리스폰초();
        $next = date('Y-m-d H:i:s', time() + $respawnSec);
        db_query("
          UPDATE tb_boss_raid
          SET status = 2, hp_now = 0, killed_at = NOW(), next_spawn_at = '{$next}'
          WHERE idx = {$mid} AND status = 0
          LIMIT 1
        ");
        // 중복 진행 보스(레이스)도 같은 쿨다운으로 종료
        boss_raid_진행중_전부종료($mid, $next);
        $분 = boss_raid_제한분(
            !empty($boss['created_at']) ? strtotime((string)$boss['created_at']) : null
        );
        boss_raid_본방알림(
            "⏱ {$def['emoji']} {$def['name']} 타임오버!\n"
            . "{$분}분 안에 처치 실패\n"
            . "다음 보스: " . boss_raid_초시분초($respawnSec) . " 뒤 · 대기 중 공격 불가 · 출현 시까지 휴식",
            'boss_timeout_' . $def['key']
        );
        $boss['status'] = 2;
        $boss['next_spawn_at'] = $next;
        $boss['hp_now'] = 0;
        return $boss;
    }
}

if (!function_exists('boss_raid_활성_상태')) {
    /**
     * @return array{boss?:array,phase:string,next_spawn_at?:string,next_spawn_in?:int,need_admin?:bool}
     * phase: fighting|waiting|none
     */
    function boss_raid_활성_상태(): array {
        boss_raid_테이블_보장();
        // 구 종료 보스·고아 기록 정리 (직전 1개만 유지)
        static $pruned = false;
        if (!$pruned) {
            $pruned = true;
            try {
                boss_raid_종료보스_기록정리(false);
            } catch (Throwable $e) {
                // ignore
            }
            // 진행 중 보스 중복(레이스) 정리: 최신 1개만 유지
            try {
                $keep = @db_select("SELECT idx FROM tb_boss_raid WHERE status = 0 ORDER BY idx DESC LIMIT 1");
                $keepId = (int)($keep['idx'] ?? 0);
                if ($keepId > 0) {
                    $spawnFallback = date('Y-m-d H:i:s', time() + (int)BOSS_RAID_RESPAWN_SEC_MIN);
                    boss_raid_진행중_전부종료($keepId, $spawnFallback);
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
        $row = db_select("SELECT * FROM tb_boss_raid WHERE status = 0 ORDER BY idx DESC LIMIT 1");
        if (!empty($row['idx'])) {
            // 직전 처치/타임오버 쿨다운이 남아 있고, 그 이전에 생긴 진행 보스가 남아 있으면 종료
            $spawnGate = boss_raid_스폰가능시각();
            $lastEndedGate = boss_raid_마지막종료행();
            $lastGateId = (int)($lastEndedGate['idx'] ?? 0);
            if ($lastGateId > 0 && $lastGateId !== (int)$row['idx'] && $spawnGate > time()) {
                $ac = !empty($row['created_at']) ? strtotime((string)$row['created_at']) : false;
                $ek = !empty($lastEndedGate['killed_at'])
                    ? strtotime((string)$lastEndedGate['killed_at'])
                    : (!empty($lastEndedGate['created_at']) ? strtotime((string)$lastEndedGate['created_at']) : false);
                if ($ac !== false && $ek !== false && $ac <= $ek) {
                    boss_raid_진행중_전부종료(0, date('Y-m-d H:i:s', $spawnGate), true);
                    $row = [];
                }
            }
        }
        if (!empty($row['idx'])) {
            $mid = (int)$row['idx'];
            $row = boss_raid_HP_동기화($row);
            if (empty($row['fight_ends_at'])) {
                $endsStr = date('Y-m-d H:i:s', time() + boss_raid_전투초());
                db_query("UPDATE tb_boss_raid SET fight_ends_at = '{$endsStr}' WHERE idx = {$mid} AND status = 0 LIMIT 1");
                $row['fight_ends_at'] = $endsStr;
            }
            $ends = strtotime((string)$row['fight_ends_at']);
            if ($ends !== false && $ends <= time()) {
                boss_raid_타임오버_처리($row);
                // 타임오버 후 같은 요청에서 자동 소환하지 않음 — 아래 waiting 분기로
            } else {
                $row = boss_raid_회복_동기화($row);
                // 진행알림(채팅 큐)은 function.php / _auto_boss_raid 에서만 — 웹 폴링마다 돌리면 진입·갱신 랙
                return ['boss' => $row, 'phase' => 'fighting'];
            }
        }

        $last = boss_raid_마지막종료행();
        // 한 번도 끝난 보스 없음 → 관리자 `.보스출현` 대기 (자동 소환 안 함)
        if (empty($last['idx'])) {
            return [
                'phase' => 'waiting',
                'next_spawn_at' => '',
                'next_spawn_in' => 0,
                'need_admin' => true,
            ];
        }

        $spawnAt = boss_raid_스폰가능시각();
        if ($spawnAt > time()) {
            // 다음 타임 전 — 공격·소환 모두 불가
            return [
                'phase' => 'waiting',
                'next_spawn_at' => date('Y-m-d H:i:s', $spawnAt),
                'next_spawn_in' => $spawnAt - time(),
                'need_admin' => false,
            ];
        }

        // 리스폰 경과 → 로테이션 1마리만 자동 출현 (락·쿨다운 가드 포함)
        $boss = boss_raid_신규소환(null, true, false);
        if (!empty($boss['idx'])) {
            return ['boss' => $boss, 'phase' => 'fighting'];
        }
        // 소환 실패(동시 요청 등) → 대기 유지
        $spawnAt2 = boss_raid_스폰가능시각();
        $in = max(0, $spawnAt2 - time());
        return [
            'phase' => 'waiting',
            'next_spawn_at' => $spawnAt2 > 0 ? date('Y-m-d H:i:s', $spawnAt2) : '',
            'next_spawn_in' => $in,
            'need_admin' => ($in < 1),
        ];
    }
}

if (!function_exists('boss_raid_플레이어행')) {
    /** @return array{weapon_used:int,mining_used:int} */
    function boss_raid_플레이어행($boss_idx, $nick): array {
        $mid = (int)$boss_idx;
        $esc = addslashes(trim((string)$nick));
        $row = db_select("SELECT weapon_used, mining_used FROM tb_boss_raid_player WHERE boss_idx = {$mid} AND nick = '{$esc}' LIMIT 1");
        return [
            'weapon_used' => (int)($row['weapon_used'] ?? 0),
            'mining_used' => (int)($row['mining_used'] ?? 0),
        ];
    }
}

if (!function_exists('boss_raid_플레이어_증가')) {
    function boss_raid_플레이어_증가($boss_idx, $nick, $type): void {
        $mid = (int)$boss_idx;
        $esc = addslashes(trim((string)$nick));
        db_query("
          INSERT INTO tb_boss_raid_player (boss_idx, nick, weapon_used, mining_used)
          VALUES ({$mid}, '{$esc}', 0, 0)
          ON DUPLICATE KEY UPDATE nick = nick
        ");
        if ($type === 'weapon') {
            db_query("UPDATE tb_boss_raid_player SET weapon_used = weapon_used + 1 WHERE boss_idx = {$mid} AND nick = '{$esc}' LIMIT 1");
        } elseif ($type === 'mining' || $type === 'protect') {
            db_query("UPDATE tb_boss_raid_player SET mining_used = mining_used + 1 WHERE boss_idx = {$mid} AND nick = '{$esc}' LIMIT 1");
        }
    }
}

if (!function_exists('boss_raid_참여자목록')) {
    /** @return list<string> */
    function boss_raid_참여자목록($boss_idx): array {
        $mid = (int)$boss_idx;
        $nicks = [];
        $rs = @db_query("SELECT DISTINCT nick FROM tb_boss_raid_hit WHERE boss_idx = {$mid} AND attack_type IN ('weapon','mining','finish') ORDER BY nick ASC");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $n = trim((string)($row['nick'] ?? ''));
                if ($n !== '') {
                    $nicks[] = $n;
                }
            }
        }
        return $nicks;
    }
}

if (!function_exists('boss_raid_도전자닉목록')) {
    /**
     * 이미 무기/채굴로 때린 닉 (+시전자)
     * @return list<string>
     */
    function boss_raid_도전자닉목록($boss_idx, $extraNick = ''): array {
        $nicks = boss_raid_참여자목록($boss_idx);
        $extra = trim((string)$extraNick);
        if ($extra !== '' && !in_array($extra, $nicks, true)) {
            $nicks[] = $extra;
        }
        return $nicks;
    }
}

if (!function_exists('boss_raid_참여자수')) {
    function boss_raid_참여자수($boss_idx): int {
        $mid = (int)$boss_idx;
        if ($mid < 1) {
            return 0;
        }
        $row = @db_select("SELECT COUNT(DISTINCT nick) AS c FROM tb_boss_raid_hit WHERE boss_idx = {$mid} AND attack_type IN ('weapon','mining','finish')");
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('boss_raid_총데미지')) {
    function boss_raid_총데미지($boss_idx): int {
        $mid = (int)$boss_idx;
        if ($mid < 1) {
            return 0;
        }
        $row = @db_select("SELECT COALESCE(SUM(damage), 0) AS s FROM tb_boss_raid_hit WHERE boss_idx = {$mid} AND attack_type IN ('weapon','mining','finish')");
        return (int)($row['s'] ?? 0);
    }
}

if (!function_exists('boss_raid_기여순위')) {
    /** @return list<array{nick:string,damage:int}> */
    function boss_raid_기여순위($boss_idx, $limit = 20): array {
        $mid = (int)$boss_idx;
        $limit = max(1, (int)$limit);
        $rows = [];
        $rs = @db_query("
          SELECT nick, SUM(damage) AS dmg
          FROM tb_boss_raid_hit
          WHERE boss_idx = {$mid} AND attack_type IN ('weapon','mining','finish')
          GROUP BY nick
          ORDER BY dmg DESC, nick ASC
          LIMIT {$limit}
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $rows[] = [
                    'nick' => (string)($row['nick'] ?? ''),
                    'damage' => (int)($row['dmg'] ?? 0),
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('boss_raid_기여순위_표시')) {
    /**
     * 기여 순위 + 기여% + 지급(처치 시) / 예상(진행 중) 냥 + 현재 보호
     * @return list<array{nick:string,damage:int,share_pct:float,reward:int,reward_fmt:string,reward_label:string,protect:int,protect_fmt:string}>
     */
    function boss_raid_기여순위_표시($boss_idx, $limit = 20): array {
        $mid = (int)$boss_idx;
        $limit = max(1, (int)$limit);
        $rank = boss_raid_기여순위($mid, $limit);
        if ($rank === []) {
            return [];
        }

        $bossRow = @db_select("SELECT status, boss_key FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        $status = (int)($bossRow['status'] ?? 0);
        $def = boss_raid_종류((string)($bossRow['boss_key'] ?? ''));
        $pct = (float)($def['reward_pct'] ?? BOSS_RAID_REWARD_PCT);
        $rewardEach = boss_raid_보상1인($pct);
        $partCnt = boss_raid_참여자수($mid);
        $totalDmg = boss_raid_총데미지($mid);
        $pool = max(0, $rewardEach * max(1, $partCnt));

        $rewardLabel = '';
        $includePay = false;
        if ($status === 1) {
            $rewardLabel = '지급';
            $includePay = true;
        } elseif ($status === 0) {
            $rewardLabel = '예상';
            $includePay = true;
        }

        $mvpBonus = (int)BOSS_RAID_MVP_BONUS;
        $mvpNick = ($includePay && $mvpBonus > 0 && !empty($rank[0]['nick']) && (int)($rank[0]['damage'] ?? 0) > 0)
            ? (string)$rank[0]['nick']
            : '';

        $protectMap = [];
        $in = [];
        foreach ($rank as $r) {
            $n = trim((string)($r['nick'] ?? ''));
            if ($n === '') {
                continue;
            }
            $in[] = "'" . addslashes($n) . "'";
        }
        if ($in !== []) {
            $rsP = @db_query("SELECT name, IFNULL(protect, 0) AS protect FROM tb_member WHERE name IN (" . implode(',', $in) . ")");
            if ($rsP) {
                while ($prow = db_fetch($rsP)) {
                    $pn = trim((string)($prow['name'] ?? ''));
                    if ($pn !== '') {
                        $protectMap[$pn] = max(0, (int)($prow['protect'] ?? 0));
                    }
                }
            }
        }

        $out = [];
        foreach ($rank as $i => $r) {
            $nick = (string)($r['nick'] ?? '');
            $dmg = (int)($r['damage'] ?? 0);
            $share = ($totalDmg > 0 && $dmg > 0) ? round(($dmg / $totalDmg) * 100, 2) : 0.0;
            $pay = 0;
            if ($includePay && $totalDmg > 0 && $dmg > 0 && $pool > 0) {
                $pay = (int)floor($pool * $dmg / $totalDmg);
            }
            if ($includePay && $mvpNick !== '' && $nick === $mvpNick) {
                $pay += $mvpBonus;
            }
            $protect = (int)($protectMap[$nick] ?? 0);
            $out[] = [
                'nick' => $nick,
                'damage' => $dmg,
                'share_pct' => $share,
                'reward' => $pay,
                'reward_fmt' => number_format($pay),
                'reward_label' => $rewardLabel,
                'place' => $i + 1,
                'protect' => $protect,
                'protect_fmt' => number_format($protect),
            ];
        }
        return $out;
    }
}

if (!function_exists('boss_raid_반격이력')) {
    /** @return list<array{nick:string,type:string,amount:string,detail:string,time:string}> */
    function boss_raid_반격이력($boss_idx, $limit = 30): array {
        $mid = (int)$boss_idx;
        $limit = max(1, (int)$limit);
        $rows = [];
        $rs = @db_query("
          SELECT nick, effect_type, amount, detail, regdate
          FROM tb_boss_raid_counter
          WHERE boss_idx = {$mid}
          ORDER BY idx DESC
          LIMIT {$limit}
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $rows[] = [
                    'nick' => (string)($row['nick'] ?? ''),
                    'type' => (string)($row['effect_type'] ?? ''),
                    'amount' => (string)($row['amount'] ?? ''),
                    'detail' => (string)($row['detail'] ?? ''),
                    'time' => (string)($row['regdate'] ?? ''),
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('boss_raid_반격_기록')) {
    function boss_raid_반격_기록($boss_idx, $nick, array $counter): void {
        $mid = (int)$boss_idx;
        if ($mid < 1) {
            return;
        }
        $esc = addslashes(trim((string)$nick));
        $type = addslashes((string)($counter['type'] ?? ''));
        $amount = addslashes((string)($counter['amount'] ?? ''));
        $detail = addslashes(mb_substr((string)($counter['detail'] ?? $counter['msg'] ?? ''), 0, 180));
        if ($type === '') {
            return;
        }
        db_query("
          INSERT INTO tb_boss_raid_counter (boss_idx, nick, effect_type, amount, detail)
          VALUES ({$mid}, '{$esc}', '{$type}', '{$amount}', '{$detail}')
        ");
    }
}

if (!function_exists('boss_raid_은총조각_지급')) {
    /** mining_ore_add_shards 래퍼 */
    function boss_raid_은총조각_지급($nick, int $qty): array {
        $qty = max(0, $qty);
        if ($qty < 1 || trim((string)$nick) === '') {
            return ['ok' => false, 'shard' => 0, 'eunchong_granted' => 0];
        }
        if (!function_exists('mining_ore_add_shards')) {
            $path = __DIR__ . '/mining_ore.inc.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (!function_exists('mining_ore_add_shards')) {
            return ['ok' => false, 'shard' => 0, 'eunchong_granted' => 0];
        }
        $r = mining_ore_add_shards($nick, $qty);
        return [
            'ok' => true,
            'shard' => (int)($r['shard'] ?? 0),
            'eunchong_granted' => (int)($r['eunchong_granted'] ?? 0),
        ];
    }
}

if (!function_exists('boss_raid_처치_랜덤아이템_뽑기')) {
    /** tb_item.ramdum = 0 인 아이템 중 1개 */
    function boss_raid_처치_랜덤아이템_뽑기(): string {
        $row = @db_select("
            SELECT TRIM(sname) AS sname
            FROM tb_item
            WHERE TRIM(IFNULL(sname, '')) <> ''
              AND IFNULL(ramdum, 1) = 0
            ORDER BY RAND()
            LIMIT 1
        ");
        return trim((string)($row['sname'] ?? ''));
    }
}

if (!function_exists('boss_raid_처치_아이템_지급')) {
    /** @return array{ok:bool,item:string} */
    function boss_raid_처치_아이템_지급(string $nick, string $itemname = ''): array {
        $nick = trim($nick);
        $itemname = trim($itemname);
        if ($nick === '') {
            return ['ok' => false, 'item' => ''];
        }
        if ($itemname === '') {
            $itemname = boss_raid_처치_랜덤아이템_뽑기();
        }
        if ($itemname === '') {
            return ['ok' => false, 'item' => ''];
        }

        $bagPath = __DIR__ . '/../item_bag.inc.php';
        if (!function_exists('item_bag_add_nick') && is_file($bagPath)) {
            require_once $bagPath;
        }

        if (function_exists('item_bag_tracked') && item_bag_tracked($itemname) && function_exists('item_bag_add_nick')) {
            $r = item_bag_add_nick($nick, $itemname, 1);
            return ['ok' => !empty($r['ok']), 'item' => $itemname];
        }

        $닉_esc = addslashes($nick);
        $아이템_esc = addslashes($itemname);
        $mem = db_select("SELECT idx FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
        $midx = (int)($mem['idx'] ?? 0);
        db_query("
            INSERT INTO tb_member_item
            SET midx = {$midx}, nick = '{$닉_esc}', status = 0, itemname = '{$아이템_esc}', regdate = NOW()
        ");
        return ['ok' => true, 'item' => $itemname];
    }
}

if (!function_exists('boss_raid_은총조각_보유')) {
    function boss_raid_은총조각_보유($nick): int {
        $esc = addslashes(trim((string)$nick));
        if ($esc === '') {
            return 0;
        }
        // 상태 폴링에서는 광물 전체 스키마 보장을 실행하지 않고 보유량만 읽는다.
        // 실제 충전/차감 액션은 mining_ore_spend_shards()가 스키마를 보장한다.
        $row = @db_select("
          SELECT IFNULL(mining_eunchong_shard, 0) AS shard
          FROM tb_member_mining
          WHERE nick = '{$esc}'
          LIMIT 1
        ");
        return max(0, (int)($row['shard'] ?? 0));
    }
}

if (!function_exists('boss_raid_은총조각_횟수충전')) {
    /**
     * 은총조각 1개 → 무기/채굴 횟수+내구 초기화, 또는 단소 필살 횟수 초기화(1시간 다시)
     * @param 'weapon'|'mining'|'finish' $type
     * @return array{ok:bool,data:string,state?:array}
     */
    function boss_raid_은총조각_횟수충전($nick, string $type): array {
        $nick = trim((string)$nick);
        if ($type !== 'mining' && $type !== 'finish') {
            $type = 'weapon';
        }
        if ($nick === '') {
            return ['ok' => false, 'data' => '로그인이 필요해요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $st = boss_raid_활성_상태();
        $weapon = boss_raid_무기정보($nick);
        $enhance = (int)($weapon['enhance'] ?? 0);

        if ($type === 'finish') {
            if (!boss_raid_단소무기인가($weapon)) {
                return ['ok' => false, 'data' => '필살 초기화는 단소만 가능해요.', 'state' => boss_raid_상태_페이로드($nick)];
            }
            $finish_max = boss_raid_단소필살횟수($weapon, $enhance);
            if ($finish_max < 1) {
                return ['ok' => false, 'data' => '필살 횟수가 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
            }
            $구간 = boss_raid_단소필살구간_적용($nick, false);
            $used = (int)($구간['used'] ?? 0);
            $left = max(0, $finish_max - $used);
            if ($left > 0) {
                return [
                    'ok' => false,
                    'data' => "필살 횟수가 아직 남아 있어요. ({$left}/{$finish_max})",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            if (!function_exists('mining_ore_spend_shards')) {
                $path = __DIR__ . '/mining_ore.inc.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
            if (!function_exists('mining_ore_spend_shards')) {
                return ['ok' => false, 'data' => '은총조각 처리에 실패했어요.', 'state' => boss_raid_상태_페이로드($nick)];
            }
            $spend = mining_ore_spend_shards($nick, 1);
            if (empty($spend['ok'])) {
                return [
                    'ok' => false,
                    'data' => (string)($spend['data'] ?? '은총조각이 부족해요.'),
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            boss_raid_단소필살_스키마보장();
            $esc = addslashes($nick);
            $win_esc = addslashes(date('Y-m-d H:i:s'));
            @db_query("UPDATE tb_member SET boss_danso_finish_used = 0, boss_danso_finish_window = '{$win_esc}' WHERE name = '{$esc}' LIMIT 1");
            $shardLeft = (int)($spend['shard'] ?? boss_raid_은총조각_보유($nick));
            $msg = "✨ 은총조각 1개 사용!\n필살 {$finish_max}/{$finish_max} 충전 · 1시간 다시 시작\n남은 은총조각 {$shardLeft}개";
            if (function_exists('지급로그')) {
                지급로그('보스은총조각충전|필살횟수초기화', $nick, '', 0, 1);
            }
            return [
                'ok' => true,
                'data' => $msg,
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }

        if (($st['phase'] ?? '') !== 'fighting') {
            return [
                'ok' => false,
                'data' => '전투 중에만 공격횟수를 충전할 수 있어요.',
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }
        $mid = (int)(($st['boss']['idx'] ?? 0));
        if ($mid < 1) {
            return ['ok' => false, 'data' => '보스 정보가 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $weapon = boss_raid_무기정보($nick);
        $enhance = (int)($weapon['enhance'] ?? 0);
        if ($type === 'mining' && (boss_raid_마법무기인가($weapon) || boss_raid_단소무기인가($weapon))) {
            return [
                'ok' => false,
                'data' => boss_raid_단소무기인가($weapon)
                    ? '단소는 은총조각으로 채굴 횟수를 충전하지 않아요.'
                    : '마법은 은총조각으로 채굴 횟수를 충전하지 않아요.',
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }
        $mining_lv = boss_raid_채굴레벨($nick);
        $weapon_max = boss_raid_무기공격횟수($enhance, $weapon);
        $mining_max = boss_raid_채굴공격횟수($mining_lv);
        $pl = boss_raid_플레이어행($mid, $nick);
        $weapon_used = (int)($pl['weapon_used'] ?? 0);
        $mining_used = (int)($pl['mining_used'] ?? 0);
        $weapon_left = max(0, $weapon_max - $weapon_used);
        $mining_left = max(0, $mining_max - $mining_used);

        if ($type === 'weapon') {
            if ($weapon_max < 1) {
                return [
                    'ok' => false,
                    'data' => '무기 공격 횟수가 없어요.',
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            if ($weapon_left > 0) {
                return [
                    'ok' => false,
                    'data' => "무기 공격 횟수가 아직 남아 있어요. ({$weapon_left}/{$weapon_max})",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
        } else {
            if ($mining_max < 1) {
                return [
                    'ok' => false,
                    'data' => '채굴 공격 횟수가 없어요.',
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            if ($mining_left > 0) {
                return [
                    'ok' => false,
                    'data' => "채굴 공격 횟수가 아직 남아 있어요. ({$mining_left}/{$mining_max})",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
        }

        if (!function_exists('mining_ore_spend_shards')) {
            $path = __DIR__ . '/mining_ore.inc.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (!function_exists('mining_ore_spend_shards')) {
            return ['ok' => false, 'data' => '은총조각 처리에 실패했어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }
        $spend = mining_ore_spend_shards($nick, 1);
        if (empty($spend['ok'])) {
            return [
                'ok' => false,
                'data' => (string)($spend['data'] ?? '은총조각이 부족해요.'),
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }

        $esc = addslashes($nick);
        if ($type === 'weapon') {
            @db_query("
                UPDATE tb_boss_raid_player
                SET weapon_used = 0
                WHERE boss_idx = {$mid} AND nick = '{$esc}'
                LIMIT 1
            ");
            $durSt = boss_raid_무기내구_상태($nick);
            $durMax = max(0, (int)($durSt['max'] ?? 0));
            if ($durMax > 0) {
                @db_query("UPDATE tb_member SET durability = {$durMax} WHERE name = '{$esc}' LIMIT 1");
            }
            $label = '무기 공격횟수';
            $durLabel = "무기 내구도 {$durMax}/{$durMax}";
            $max = $weapon_max;
            $logTag = '무기횟수초기화';
        } else {
            @db_query("
                UPDATE tb_boss_raid_player
                SET mining_used = 0
                WHERE boss_idx = {$mid} AND nick = '{$esc}'
                LIMIT 1
            ");
            boss_raid_채굴내구_로드();
            // 지금까지의 오프라인 채굴량·마모를 먼저 확정한 뒤 내구도를 완전 회복
            if (function_exists('mining_sync_commit_elapsed')) {
                mining_sync_commit_elapsed($nick);
            }
            $durMax = function_exists('mining_durability_max')
                ? (int)mining_durability_max($mining_lv)
                : 0;
            if (function_exists('mining_durability_restore_full')) {
                mining_durability_restore_full($nick);
            }
            $label = '채굴 공격횟수';
            $durLabel = "채굴장비 내구도 {$durMax}/{$durMax}";
            $max = $mining_max;
            $logTag = '채굴횟수초기화';
        }

        $shardLeft = (int)($spend['shard'] ?? boss_raid_은총조각_보유($nick));
        $msg = "✨ 은총조각 1개 사용!\n{$label} {$max}/{$max} 충전\n{$durLabel} 회복\n남은 은총조각 {$shardLeft}개";
        if (function_exists('지급로그')) {
            지급로그('보스은총조각충전|' . $logTag, $nick, '', 0, 1);
        }
        return [
            'ok' => true,
            'data' => $msg,
            'state' => boss_raid_상태_페이로드($nick),
        ];
    }
}

/** @deprecated boss_raid_은총조각_횟수충전 사용 */
if (!function_exists('boss_raid_채굴은총수리')) {
    function boss_raid_채굴은총수리($nick): array {
        return [
            'ok' => false,
            'data' => '무기/채굴 공격횟수가 0일 때 각각의 은총조각 충전 버튼을 사용해주세요.',
            'state' => boss_raid_상태_페이로드($nick),
        ];
    }
}

if (!function_exists('boss_raid_은총_지급')) {
    /** bag/은총개수 +N (bag 소스, 컬럼 미러) */
    function boss_raid_은총_지급($nick, int $qty = 1): bool {
        $qty = max(0, $qty);
        $nick = trim((string)$nick);
        if ($qty < 1 || $nick === '') {
            return false;
        }
        if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/../item_bag_enhance.inc.php';
        }
        if (function_exists('bag_은총_가산')) {
            $r = bag_은총_가산($nick, $qty);
            return !empty($r['ok']);
        }
        $esc = addslashes($nick);
        db_query("
          UPDATE tb_member
          SET 은총개수 = IFNULL(은총개수, 0) + {$qty}
          WHERE name = '{$esc}'
          LIMIT 1
        ");
        return true;
    }
}

if (!function_exists('boss_raid_드랍_기록')) {
    function boss_raid_드랍_기록($boss_idx, $nick, $drop_type, $qty, $detail): void {
        $mid = (int)$boss_idx;
        if ($mid < 1) {
            return;
        }
        $nick_esc = addslashes(trim((string)$nick));
        $type_esc = addslashes(trim((string)$drop_type));
        $qty = max(1, (int)$qty);
        $detail_esc = addslashes(mb_substr((string)$detail, 0, 180));
        @db_query("
          INSERT INTO tb_boss_raid_drop (boss_idx, nick, drop_type, qty, detail)
          VALUES ({$mid}, '{$nick_esc}', '{$type_esc}', {$qty}, '{$detail_esc}')
        ");
    }
}

if (!function_exists('boss_raid_최신타격')) {
    /**
     * 웹 동기화용 최근 타격 (오래된→최신)
     * @return list<array{idx:int,nick:string,attack_type:string,damage:int,crit:bool,weapon_fx:string,time:string}>
     */
    function boss_raid_최신타격($boss_idx, $limit = 20): array {
        $mid = (int)$boss_idx;
        $limit = max(1, min(40, (int)$limit));
        if ($mid < 1) {
            return [];
        }
        $rows = [];
        $rs = @db_query("
          SELECT idx, nick, attack_type, damage,
                 IFNULL(crit, 0) AS crit,
                 IFNULL(weapon_fx, '') AS weapon_fx,
                 regdate
          FROM tb_boss_raid_hit
          WHERE boss_idx = {$mid}
          ORDER BY idx DESC
          LIMIT {$limit}
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $atype = (string)($row['attack_type'] ?? 'weapon');
                $fx = trim((string)($row['weapon_fx'] ?? ''));
                if ($fx === '') {
                    $fx = ($atype === 'mining') ? 'mining' : (($atype === 'protect') ? 'protect' : (($atype === 'finish') ? 'finish' : 'sword'));
                }
                $rows[] = [
                    'idx' => (int)($row['idx'] ?? 0),
                    'nick' => (string)($row['nick'] ?? ''),
                    'attack_type' => $atype,
                    'damage' => (int)($row['damage'] ?? 0),
                    'crit' => !empty($row['crit']),
                    'weapon_fx' => $fx,
                    'time' => (string)($row['regdate'] ?? ''),
                ];
            }
        }
        return array_reverse($rows);
    }
}

if (!function_exists('boss_raid_내타격이력')) {
    /**
     * 내 공격 기록 (최신순)
     * @return list<array{idx:int,attack_type:string,damage:int,enhance:int,mining_level:int,crit:bool,weapon_fx:string,time:string}>
     */
    function boss_raid_내타격이력($boss_idx, $nick, $limit = 40): array {
        $mid = (int)$boss_idx;
        $nick = trim((string)$nick);
        $limit = max(1, min(80, (int)$limit));
        if ($mid < 1 || $nick === '') {
            return [];
        }
        $esc = addslashes($nick);
        $rows = [];
        $rs = @db_query("
          SELECT idx, attack_type, damage, enhance, mining_level,
                 IFNULL(crit, 0) AS crit,
                 IFNULL(weapon_fx, '') AS weapon_fx,
                 regdate
          FROM tb_boss_raid_hit
          WHERE boss_idx = {$mid} AND nick = '{$esc}'
          ORDER BY idx DESC
          LIMIT {$limit}
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $atype = (string)($row['attack_type'] ?? 'weapon');
                $fx = trim((string)($row['weapon_fx'] ?? ''));
                if ($fx === '') {
                    $fx = ($atype === 'mining') ? 'mining' : (($atype === 'protect') ? 'protect' : (($atype === 'finish') ? 'finish' : 'sword'));
                }
                $rows[] = [
                    'idx' => (int)($row['idx'] ?? 0),
                    'attack_type' => $atype,
                    'damage' => (int)($row['damage'] ?? 0),
                    'enhance' => (int)($row['enhance'] ?? 0),
                    'mining_level' => (int)($row['mining_level'] ?? 0),
                    'crit' => !empty($row['crit']),
                    'weapon_fx' => $fx,
                    'time' => (string)($row['regdate'] ?? ''),
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('boss_raid_드랍이력')) {
    /** @return list<array{nick:string,type:string,qty:int,detail:string,time:string}> */
    function boss_raid_드랍이력($boss_idx, $limit = 12): array {
        $mid = (int)$boss_idx;
        $limit = max(1, min(30, (int)$limit));
        if ($mid < 1) {
            return [];
        }
        $rows = [];
        $rs = @db_query("
          SELECT nick, drop_type, qty, detail, created_at
          FROM tb_boss_raid_drop
          WHERE boss_idx = {$mid}
          ORDER BY idx DESC
          LIMIT {$limit}
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $rows[] = [
                    'nick' => (string)($row['nick'] ?? ''),
                    'type' => (string)($row['drop_type'] ?? ''),
                    'qty' => (int)($row['qty'] ?? 1),
                    'detail' => (string)($row['detail'] ?? ''),
                    'time' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('boss_raid_공격드랍')) {
    /**
     * 공격 1회당 은총조각 3% / 은총 0.01% (독립 판정)
     * @return list<array{type:string,qty:int,detail:string,msg:string}>
     */
    function boss_raid_공격드랍($nick, $boss_idx = 0): array {
        $nick = trim((string)$nick);
        $mid = (int)$boss_idx;
        if ($nick === '') {
            return [];
        }
        $out = [];

        // 은총조각 3%
        $shardPct = max(0.0, (float)BOSS_RAID_DROP_SHARD_PCT);
        if ($shardPct > 0) {
            $rollMax = max(1, (int)round(100 / $shardPct));
            if (random_int(1, $rollMax) === 1) {
                $r = boss_raid_은총조각_지급($nick, 1);
                if (!empty($r['ok'])) {
                    $detail = "{$nick} 님이 은총조각 +1 획득!";
                    $extra = '';
                    if ((int)($r['eunchong_granted'] ?? 0) > 0) {
                        $extra = ' (조각 모아 은총 자동 변환)';
                    }
                    boss_raid_드랍_기록($mid, $nick, 'shard', 1, $detail . $extra);
                    if (function_exists('지급로그')) {
                        지급로그('보스드랍-은총조각', $nick, '', 0, 1);
                    }
                    $out[] = [
                        'type' => 'shard',
                        'qty' => 1,
                        'detail' => '✨ ' . $detail . $extra,
                        'msg' => '✨ 은총조각 +1!' . $extra,
                    ];
                }
            }
        }

        // 은총 0.01% = 1/10000
        $eunPct = max(0.0, (float)BOSS_RAID_DROP_EUNCHONG_PCT);
        if ($eunPct > 0) {
            $rollMax = max(1, (int)round(100 / $eunPct));
            if (random_int(1, $rollMax) === 1) {
                if (boss_raid_은총_지급($nick, 1)) {
                    $detail = "{$nick} 님이 은총 +1 획득!";
                    boss_raid_드랍_기록($mid, $nick, 'eunchong', 1, $detail);
                    if (function_exists('지급로그')) {
                        지급로그('보스드랍-은총', $nick, '', 0, 1);
                    }
                    $out[] = [
                        'type' => 'eunchong',
                        'qty' => 1,
                        'detail' => '🌟 ' . $detail,
                        'msg' => '🌟 은총 +1!',
                    ];
                }
            }
        }

        return $out;
    }
}

if (!function_exists('boss_raid_새벽인가')) {
    /** 00:00 ~ 07:59 (한국 시간 가정 · 서버 로컬) */
    function boss_raid_새벽인가($ts = null): bool {
        $h = (int)date('G', $ts !== null ? (int)$ts : time());
        return $h >= 0 && $h < 8;
    }
}

if (!function_exists('boss_raid_출현HP')) {
    /** 시간대와 무관하게 종류에 저장된 HP를 그대로 사용 */
    function boss_raid_출현HP($baseHp, $ts = null): int {
        return max(1, (int)$baseHp);
    }
}

if (!function_exists('boss_raid_금액문자열')) {
    function boss_raid_금액문자열($v): string {
        if (function_exists('냥_정수문자열')) {
            return 냥_정수문자열($v);
        }
        $s = preg_replace('/[^\d]/', '', (string)$v);
        return ltrim((string)$s, '0') ?: '0';
    }
}

if (!function_exists('boss_raid_처치풀_컬럼_확보')) {
    /** config.보스처치풀 — 반격 탈취 게임냥 공통 누적 */
    function boss_raid_처치풀_컬럼_확보(): bool {
        static $done = false;
        static $ok = false;
        if ($done) {
            return $ok;
        }
        $done = true;
        if (!function_exists('db_select') || !function_exists('db_query')) {
            return false;
        }
        global $conn;
        // 핫패스: SELECT로 존재 확인 (SHOW COLUMNS 생략)
        @db_query("SELECT `보스처치풀` FROM config LIMIT 1");
        if ($conn instanceof mysqli && mysqli_errno($conn) === 0) {
            $ok = true;
            return true;
        }
        $col = @db_select("SHOW COLUMNS FROM config LIKE '보스처치풀'");
        $newColumn = false;
        if (empty($col)) {
            @db_query("
              ALTER TABLE config
                ADD COLUMN `보스처치풀` DECIMAL(40,0) NOT NULL DEFAULT 0
                  COMMENT '보스 반격 탈취 게임냥 공통 풀(처치 시 분배)'
            ");
            $col = @db_select("SHOW COLUMNS FROM config LIKE '보스처치풀'");
            $newColumn = !empty($col);
        }
        if (empty($col)) {
            $ok = false;
            return false;
        }
        // 컬럼을 처음 추가한 배포 요청에서만 기존 보스별 금액을 이관한다.
        // 이전에는 페이지 요청마다 전체 보스 합계를 다시 계산했다.
        if ($newColumn) {
            $spCol = @db_select("SHOW COLUMNS FROM tb_boss_raid LIKE 'stolen_point'");
            if (!empty($spCol)) {
                $sumRow = @db_select("SELECT CAST(IFNULL(SUM(stolen_point), 0) AS CHAR) AS s FROM tb_boss_raid");
                $sum = boss_raid_금액문자열(is_array($sumRow) ? ($sumRow['s'] ?? 0) : 0);
                $has = function_exists('bccomp') ? (bccomp($sum, '0', 0) > 0) : ((float)$sum > 0);
                if ($has) {
                    $sqlSum = function_exists('냥_SQL정수') ? 냥_SQL정수($sum) : $sum;
                    @db_query("UPDATE config SET `보스처치풀` = IFNULL(`보스처치풀`, 0) + {$sqlSum} LIMIT 1");
                    @db_query("UPDATE tb_boss_raid SET stolen_point = 0 WHERE IFNULL(stolen_point, 0) <> 0");
                }
            }
        }
        $ok = true;
        return true;
    }
}

if (!function_exists('boss_raid_처치풀_조회')) {
    /** @return string */
    function boss_raid_처치풀_조회(): string {
        if (!boss_raid_처치풀_컬럼_확보()) {
            return '0';
        }
        $row = @db_select("SELECT CAST(IFNULL(`보스처치풀`, 0) AS CHAR) AS amt FROM config LIMIT 1");
        return boss_raid_금액문자열(is_array($row) ? ($row['amt'] ?? 0) : 0);
    }
}

if (!function_exists('boss_raid_냥축약')) {
    /**
     * 보스 UI용 냥 축약 (function.php 불필요)
     * 1경↑ / 1조↑ / 1억↑ / 1만↑ / 미만 콤마
     */
    function boss_raid_냥축약($금액, string $단위 = '냥'): string {
        $digits = preg_replace('/[^\d]/', '', (string)$금액);
        $digits = ltrim((string)$digits, '0');
        if ($digits === '' || $digits === null) {
            $digits = '0';
        }
        $len = strlen($digits);
        $comma = static function (string $n): string {
            if ($n === '' || $n === '0') {
                return '0';
            }
            // 큰 수는 number_format 부동소수 오차 방지
            $out = '';
            $s = $n;
            while (strlen($s) > 3) {
                $out = ',' . substr($s, -3) . $out;
                $s = substr($s, 0, -3);
            }
            return $s . $out;
        };
        if ($len > 16) {
            $경 = substr($digits, 0, $len - 16);
            $rest = substr($digits, $len - 16);
            $조 = ltrim(substr($rest, 0, 4), '0');
            $out = $comma($경) . '경';
            if ($조 !== '') {
                $out .= $comma($조) . '조';
            }
            return $out . $단위;
        }
        if ($len > 12) {
            $조 = substr($digits, 0, $len - 12);
            return $comma($조) . '조' . $단위;
        }
        if ($len > 8) {
            $억 = substr($digits, 0, $len - 8);
            return $comma($억) . '억' . $단위;
        }
        if ($len > 4) {
            $만 = substr($digits, 0, $len - 4);
            return $comma($만) . '만' . $단위;
        }
        return $comma($digits) . $단위;
    }
}

if (!function_exists('boss_raid_처치풀_적립')) {
    /** @param string|int $금액 */
    function boss_raid_처치풀_적립($금액): bool {
        if (!boss_raid_처치풀_컬럼_확보()) {
            return false;
        }
        $amt = boss_raid_금액문자열($금액);
        if ($amt === '0') {
            return false;
        }
        $sqlAmt = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
        return (bool)@db_query("UPDATE config SET `보스처치풀` = IFNULL(`보스처치풀`, 0) + {$sqlAmt} LIMIT 1");
    }
}

if (!function_exists('boss_raid_처치풀_비우기')) {
    function boss_raid_처치풀_비우기(): bool {
        if (!boss_raid_처치풀_컬럼_확보()) {
            return false;
        }
        return (bool)@db_query("UPDATE config SET `보스처치풀` = 0 LIMIT 1");
    }
}

if (!function_exists('boss_raid_처치풀_초기화')) {
    /**
     * 관리자: 보스처치풀 전액 0
     * @return array{ok:bool,data:string,before:string}
     */
    function boss_raid_처치풀_초기화($관리자닉 = ''): array {
        $before = boss_raid_처치풀_조회();
        $beforeFmt = function_exists('랭킹_게임냥표시')
            ? 랭킹_게임냥표시($before, '냥')
            : ((function_exists('냥_숫자콤마') ? 냥_숫자콤마($before) : $before) . '냥');
        if ($before === '0' || $before === '') {
            return [
                'ok' => true,
                'data' => "💰 보스처치풀이 이미 0이에요.",
                'before' => '0',
            ];
        }
        if (!boss_raid_처치풀_비우기()) {
            return [
                'ok' => false,
                'data' => '❌ 보스처치풀 초기화에 실패했어요.',
                'before' => $before,
            ];
        }
        if (function_exists('지급로그')) {
            지급로그('보스처치풀초기화', (string)$관리자닉, '전체', 0, $before);
        }
        return [
            'ok' => true,
            'data' => "💰 보스처치풀 초기화 완료\n이전: {$beforeFmt} → 0냥",
            'before' => $before,
        ];
    }
}

if (!function_exists('boss_raid_탈취냥_분배')) {
    /**
     * 처치 시 공통 보스처치풀 분배 (BOSS_RAID_LOOT_PAYOUT_PCT% 확률)
     * · 확률 실패 시 풀 유지(다음 처치까지 계속 누적)
     * · 평시: 공격자 랜덤 최대 5명 × 각 20%
     * · 00~08시: 공격자 랜덤 최대 3명 × 각 40%
     * @param list<string> $participants
     * @return array{
     *   ok:bool,pool:string,night:bool,share_pct:int,payout_roll:bool,winners:list<array{nick:string,amount:string,amount_fmt:string}>,msg:string
     * }
     */
    function boss_raid_탈취냥_분배($boss_idx, array $participants): array {
        $mid = (int)$boss_idx;
        $night = boss_raid_새벽인가();
        $empty = [
            'ok' => false,
            'pool' => '0',
            'night' => $night,
            'share_pct' => $night ? 40 : 20,
            'payout_roll' => false,
            'winners' => [],
            'msg' => '',
        ];
        $pool = boss_raid_처치풀_조회();
        $hasPool = function_exists('bccomp') ? (bccomp($pool, '0', 0) > 0) : ((float)$pool > 0);
        if (!$hasPool) {
            return $empty;
        }

        $poolFmt = function_exists('냥축약표시') ? 냥축약표시($pool) : number_format((float)$pool);
        $payoutPct = max(0.0, (float)BOSS_RAID_LOOT_PAYOUT_PCT);
        // 처치풀 분배: 1% → 1/100
        $rolled = false;
        if ($payoutPct > 0) {
            $rollMax = max(1, (int)round(100 / $payoutPct));
            $rolled = (random_int(1, $rollMax) === 1);
        }
        if (!$rolled) {
            return array_merge($empty, [
                'pool' => $pool,
                'pool_fmt' => $poolFmt,
                'payout_roll' => false,
                'msg' => "💰 보스 처치풀 {$poolFmt} · 분배 확률 미발동(유지·누적)",
            ]);
        }

        $names = [];
        foreach ($participants as $n) {
            $n = trim((string)$n);
            if ($n !== '') {
                $names[$n] = true;
            }
        }
        $names = array_keys($names);
        if ($names === []) {
            // 참여자 없으면 풀 유지 (다음 처치까지 누적)
            return array_merge($empty, [
                'pool' => $pool,
                'pool_fmt' => $poolFmt,
                'payout_roll' => true,
                'msg' => '처치풀 있음 · 참여자 없어 분배 보류',
            ]);
        }

        $pickN = $night ? 3 : 5;
        $sharePct = $night ? 40 : 20;
        shuffle($names);
        $picked = array_slice($names, 0, min($pickN, count($names)));

        $winners = [];
        foreach ($picked as $nick) {
            if (function_exists('bcmul') && function_exists('bcdiv')) {
                $amt = bcdiv(bcmul($pool, (string)$sharePct, 0), '100', 0);
            } else {
                $amt = (string)(int)floor(((float)$pool) * $sharePct / 100);
            }
            $amt = boss_raid_금액문자열($amt);
            $hasAmt = function_exists('bccomp') ? (bccomp($amt, '0', 0) > 0) : ((float)$amt > 0);
            if (!$hasAmt) {
                continue;
            }
            $sqlAmt = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
            $esc = addslashes($nick);
            db_query("UPDATE tb_member SET point = point + {$sqlAmt} WHERE name = '{$esc}' LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('보스처치풀|' . ($night ? '새벽' : '평시'), $nick, '보스#' . $mid, 0, $amt);
            }
            $fmt = function_exists('냥축약표시') ? 냥축약표시($amt) : number_format((float)$amt);
            $winners[] = [
                'nick' => $nick,
                'amount' => $amt,
                'amount_fmt' => $fmt,
            ];
        }

        if ($winners !== []) {
            boss_raid_처치풀_비우기();
        }

        $label = $night ? '새벽(00~08) 랜덤 3명 ×40%' : '랜덤 5명 ×20%';
        $msg = "💰 보스 처치풀 {$poolFmt} 분배 성공! · {$label}";
        if ($winners !== []) {
            foreach ($winners as $w) {
                $msg .= "\n{$w['nick']} +{$w['amount_fmt']}";
            }
        }

        return [
            'ok' => $winners !== [],
            'pool' => $pool,
            'pool_fmt' => $poolFmt,
            'night' => $night,
            'share_pct' => $sharePct,
            'payout_roll' => true,
            'winners' => $winners,
            'msg' => $msg,
        ];
    }
}

if (!function_exists('boss_raid_처치_보상')) {
    /**
     * @return array{
     *   ok:bool,reward:int,paid:int,participants:list<string>,msg:string,reward_pct:float,
     *   mvp_nick:string,mvp_damage:int,mvp_bonus:int,
     *   shard_rewards:list<array{nick:string,shards:int,place:int}>,
     *   item_rewards:list<array{nick:string,item:string}>,
     *   loot_share:array
     * }
     */
    function boss_raid_처치_보상($boss_idx): array {
        $mid = (int)$boss_idx;
        $bossRow = db_select("SELECT boss_key FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        $def = boss_raid_종류((string)($bossRow['boss_key'] ?? ''));
        $pct = (float)($def['reward_pct'] ?? BOSS_RAID_REWARD_PCT);
        $participants = boss_raid_참여자목록($mid);
        $reward = boss_raid_보상1인($pct); // 만타격 시 1인 기준액 (풀 산출용)
        $paid = 0;
        $fullPaid = 0;
        $partialPaid = 0;
        $rewardPays = [];
        $totalPaidAmount = 0;

        // 1) 참여자별 1인기준액 합산 = 분배 풀 (무기횟수 비율은 풀에만 참고용으로 기록)
        $baseByNick = [];
        $pool = 0;
        foreach ($participants as $nick) {
            $calc = boss_raid_보상_개인산출($mid, $nick, $reward);
            // 참여자 1명분 = 1인기준액 전액 (기여 분배 대상 풀)
            $base = $reward;
            $baseByNick[$nick] = [
                'base' => $base,
                'used' => (int)($calc['used'] ?? 0),
                'max' => (int)($calc['max'] ?? 0),
                'full' => !empty($calc['full']),
            ];
            $pool += $base;
            if (!empty($calc['full'])) {
                $fullPaid++;
            } elseif ((int)($calc['amount'] ?? 0) > 0) {
                $partialPaid++;
            }
        }

        // 2) 기여(데미지) 비율로 재분배
        $rankAll = boss_raid_기여순위($mid, 200);
        $shares = boss_raid_보상_기여분배($pool, $rankAll);
        $payByNick = [];
        foreach ($shares as $sh) {
            $payByNick[(string)$sh['nick']] = $sh;
        }

        // 데미지 0인 참여자도 목록에 남김(지급 0)
        foreach ($participants as $nick) {
            if (!isset($payByNick[$nick])) {
                $payByNick[$nick] = [
                    'nick' => $nick,
                    'damage' => 0,
                    'amount' => 0,
                    'share_pct' => 0.0,
                    'place' => 0,
                ];
            }
        }

        // 기여도 순 정렬
        uasort($payByNick, static function ($a, $b) {
            $da = (int)($a['damage'] ?? 0);
            $db = (int)($b['damage'] ?? 0);
            if ($da !== $db) {
                return $db <=> $da;
            }
            return strcmp((string)($a['nick'] ?? ''), (string)($b['nick'] ?? ''));
        });

        foreach ($payByNick as $nick => $sh) {
            $pay = (int)($sh['amount'] ?? 0);
            $baseInfo = $baseByNick[$nick] ?? ['base' => 0, 'used' => 0, 'max' => 0, 'full' => false];
            $rewardPays[] = [
                'nick' => $nick,
                'amount' => $pay,
                'base_amount' => (int)($baseInfo['base'] ?? 0),
                'damage' => (int)($sh['damage'] ?? 0),
                'share_pct' => (float)($sh['share_pct'] ?? 0),
                'place' => (int)($sh['place'] ?? 0),
                'used' => (int)($baseInfo['used'] ?? 0),
                'max' => (int)($baseInfo['max'] ?? 0),
                'full' => !empty($baseInfo['full']),
            ];
            if ($pay < 1) {
                continue;
            }
            $esc = addslashes($nick);
            db_query("UPDATE tb_member SET newpoint = newpoint + {$pay} WHERE name = '{$esc}' LIMIT 1");
            if (function_exists('지급로그')) {
                $place = (int)($sh['place'] ?? 0);
                $share = (float)($sh['share_pct'] ?? 0);
                $로그비고 = ($place > 0 ? "기여{$place}등 {$share}%" : '기여0');
                지급로그('보스처치|' . $def['name'] . '|' . $로그비고, $nick, '', 0, $pay);
            }
            $paid++;
            $totalPaidAmount += $pay;
        }

        // 은총조각: 1등5 · 2등4 · 3등3 / 나머지: 랜덤아이템 1개
        $placeBonus = [1 => 5, 2 => 4, 3 => 3];
        $placeByNick = [];
        $rankTop3 = boss_raid_기여순위($mid, 3);
        foreach ($rankTop3 as $i => $row) {
            $place = $i + 1;
            $rn = trim((string)($row['nick'] ?? ''));
            if ($rn === '' || (int)($row['damage'] ?? 0) < 1) {
                continue;
            }
            if (isset($placeBonus[$place])) {
                $placeByNick[$rn] = $place;
            }
        }
        $shardRewards = [];
        $itemRewards = [];
        foreach ($participants as $nick) {
            $nick = trim((string)$nick);
            if ($nick === '') {
                continue;
            }
            $place = (int)($placeByNick[$nick] ?? 0);
            if ($place >= 1 && $place <= 3) {
                $qty = (int)$placeBonus[$place];
                if ($qty < 1) {
                    continue;
                }
                boss_raid_은총조각_지급($nick, $qty);
                if (function_exists('지급로그')) {
                    지급로그('보스은총조각|' . $def['name'], $nick, '', 0, $qty);
                }
                $shardRewards[] = [
                    'nick' => $nick,
                    'shards' => $qty,
                    'place' => $place,
                ];
                continue;
            }
            $지급 = boss_raid_처치_아이템_지급($nick);
            if (empty($지급['ok'])) {
                continue;
            }
            $item = (string)($지급['item'] ?? '');
            if ($item === '') {
                continue;
            }
            if (function_exists('지급로그')) {
                지급로그('보스랜덤아이템|' . $def['name'], $nick, $item, 0, 1);
            }
            $itemRewards[] = [
                'nick' => $nick,
                'item' => $item,
            ];
        }
        usort($shardRewards, static function ($a, $b) {
            return ((int)($a['place'] ?? 0)) <=> ((int)($b['place'] ?? 0));
        });
        usort($itemRewards, static function ($a, $b) {
            return strcmp((string)($a['nick'] ?? ''), (string)($b['nick'] ?? ''));
        });

        $mvpNick = '';
        $mvpDmg = 0;
        $mvpBonus = 0;
        $rank = boss_raid_기여순위($mid, 1);
        if (!empty($rank[0]['nick']) && (int)($rank[0]['damage'] ?? 0) > 0) {
            $mvpNick = trim((string)$rank[0]['nick']);
            $mvpDmg = (int)$rank[0]['damage'];
            $mvpBonus = (int)BOSS_RAID_MVP_BONUS;
            $mvp_esc = addslashes($mvpNick);
            db_query("UPDATE tb_member SET newpoint = newpoint + {$mvpBonus} WHERE name = '{$mvp_esc}' LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('보스MVP|' . $def['name'], $mvpNick, '', 0, $mvpBonus);
            }
        }

        $lootShare = boss_raid_탈취냥_분배($mid, $participants);

        $cnt = count($participants);
        $pctLabel = boss_raid_보상비율문구($pct);
        $msg = $cnt > 0
            ? "{$def['emoji']} {$def['name']} 처치! 참여자 {$cnt}명\n"
                . "풀 " . number_format($pool) . "냥 (1인기준 " . number_format($reward) . " · 본방냥 {$pctLabel})\n"
                . "기여도 비율 분배 · 지급 " . number_format($totalPaidAmount) . "냥"
            : "{$def['emoji']} {$def['name']} 처치! (참여자 없음)";
        if ($mvpNick !== '' && $mvpBonus > 0) {
            $msg .= "\n🥇 MVP {$mvpNick} (타격 " . number_format($mvpDmg) . ") · +" . number_format($mvpBonus) . "냥";
        }
        if ($shardRewards !== []) {
            $msg .= "\n✨ 은총조각: 1등5 · 2등4 · 3등3";
            foreach ($shardRewards as $sr) {
                if ((int)($sr['place'] ?? 0) >= 1 && (int)$sr['place'] <= 3) {
                    $msg .= "\n  {$sr['place']}등 {$sr['nick']} · 조각 {$sr['shards']}";
                }
            }
        }
        if ($itemRewards !== []) {
            $msg .= "\n🎁 랜덤아이템 " . count($itemRewards) . "명";
            foreach (array_slice($itemRewards, 0, 10) as $ir) {
                $msg .= "\n  {$ir['nick']} · {$ir['item']}";
            }
            if (count($itemRewards) > 10) {
                $msg .= "\n  외 " . (count($itemRewards) - 10) . "명";
            }
        }
        if (!empty($lootShare['msg'])) {
            $msg .= "\n" . $lootShare['msg'];
        }
        return [
            'ok' => true,
            'reward' => $reward,
            'reward_pct' => $pct,
            'reward_pool' => $pool,
            'paid' => $paid,
            'full_paid' => $fullPaid,
            'partial_paid' => $partialPaid,
            'total_paid_amount' => $totalPaidAmount,
            'reward_pays' => $rewardPays,
            'participants' => $participants,
            'mvp_nick' => $mvpNick,
            'mvp_damage' => $mvpDmg,
            'mvp_bonus' => $mvpBonus,
            'shard_rewards' => $shardRewards,
            'item_rewards' => $itemRewards,
            'loot_share' => $lootShare,
            'msg' => $msg,
        ];
    }
}

if (!function_exists('boss_raid_반격_무보호피해')) {
    /**
     * 보호 0인 참여자 — 보스 성향별 단일 능력(냥 / 내구10% / 본방스왑)
     * @return array{type:string,msg:string,detail:string,amount:string,items?:string[]}
     */
    function boss_raid_반격_무보호피해($nick, $boss_idx = 0): array {
        $nick = trim((string)$nick);
        $esc = addslashes($nick);
        $mid = (int)$boss_idx;

        $style = 'steal';
        if ($mid > 0) {
            $brow = db_select("SELECT boss_key FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
            $def = boss_raid_종류((string)($brow['boss_key'] ?? ''));
            $style = trim((string)($def['style'] ?? 'steal'));
            if ($style === '') {
                $style = 'steal';
            }
        }

        $options = function_exists('boss_raid_성향_가중뽑기순서')
            ? boss_raid_성향_가중뽑기순서($style)
            : ['point', 'durability'];

        foreach ($options as $pick) {
            // 구 가방 아이템 소멸 — 폐지, 게임냥 탈취로 대체
            if ($pick === 'item') {
                $pick = 'point';
            }

            if ($pick === 'point') {
                $row = db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
                $pt = function_exists('냥_정수문자열')
                    ? 냥_정수문자열($row['point'] ?? 0)
                    : preg_replace('/[^\d]/', '', (string)($row['point'] ?? '0'));
                $pt = ltrim((string)$pt, '0') ?: '0';
                $ratio = (string)BOSS_RAID_COUNTER_POINT_RATIO;
                $cut = '0';
                if (function_exists('bcmul') && function_exists('bccomp')) {
                    $cut = bcmul($pt, $ratio, 0);
                    if (bccomp($cut, '1', 0) < 0 && bccomp($pt, '0', 0) > 0) {
                        $cut = '1';
                    }
                } else {
                    $n = (int)floor((float)$pt * (float)$ratio);
                    if ($n < 1 && (float)$pt > 0) {
                        $n = 1;
                    }
                    $cut = (string)max(0, $n);
                }
                $cut = function_exists('냥_정수문자열') ? 냥_정수문자열($cut) : preg_replace('/[^\d]/', '', (string)$cut);
                $cut = ltrim((string)$cut, '0') ?: '0';
                $hasCut = function_exists('bccomp') ? (bccomp($cut, '0', 0) > 0) : ((float)$cut > 0);
                if ($hasCut && $mid > 0) {
                    $sqlCut = function_exists('냥_SQL정수') ? 냥_SQL정수($cut) : $cut;
                    $toPool = '0';
                    $burned = '0';
                    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcsub')) {
                        $toPool = bcdiv(bcmul($cut, '90', 0), '100', 0);
                        $burned = bcsub($cut, $toPool, 0);
                    } else {
                        $toPool = (string)(int)floor(((float)$cut) * 0.9);
                        $burned = (string)max(0, (int)((float)$cut - (float)$toPool));
                    }
                    $toPool = boss_raid_금액문자열($toPool);
                    $burned = boss_raid_금액문자열($burned);

                    // GREATEST(0, ...) 금지 — 마이너스 보유가 0으로 리셋돼 🆘신불자가 풀려버림
                    db_query("UPDATE tb_member SET point = CAST(point AS DECIMAL(65,0)) - {$sqlCut} WHERE name = '{$esc}' LIMIT 1");
                    $hasPoolShare = function_exists('bccomp') ? (bccomp($toPool, '0', 0) > 0) : ((float)$toPool > 0);
                    if ($hasPoolShare) {
                        boss_raid_처치풀_적립($toPool);
                    }
                    $cutDisp = function_exists('냥축약표시') ? 냥축약표시($cut) : number_format((float)$cut);
                    $poolDisp = function_exists('냥축약표시') ? 냥축약표시($toPool) : number_format((float)$toPool);
                    $burnDisp = function_exists('냥축약표시') ? 냥축약표시($burned) : number_format((float)$burned);
                    if (function_exists('지급로그')) {
                        지급로그('보스반격-처치풀', $nick, '90%', 0, $toPool);
                        if ($burned !== '0') {
                            지급로그('보스반격-소멸', $nick, '10%', 0, $burned);
                        }
                    }
                    $detail = "게임냥 {$cutDisp} 탈취 · 처치풀 {$poolDisp} · 소멸 {$burnDisp}";
                    return [
                        'type' => 'point',
                        'amount' => (string)$cut,
                        'detail' => $detail,
                        'msg' => "🐉 보스 반격! {$detail}",
                    ];
                }
                continue;
            }

            if ($pick === 'swap') {
                $swap = function_exists('boss_raid_반격_본방스왑')
                    ? boss_raid_반격_본방스왑($nick)
                    : null;
                if (is_array($swap) && !empty($swap['ok'])) {
                    $detail = (string)($swap['detail'] ?? ('본방냥 ' . (int)BOSS_RAID_COUNTER_SWAP_PCT . '% 강제스왑'));
                    return [
                        'type' => 'swap',
                        'amount' => (string)($swap['amount'] ?? ''),
                        'detail' => $detail,
                        'msg' => "🐉 보스 반격! {$detail}",
                    ];
                }
                continue;
            }

            // durability — 최대 내구의 N% 차감 (전량 0 고정 아님)
            $durHit = boss_raid_반격_무기내구차감($nick);
            return [
                'type' => 'durability',
                'amount' => (string)(int)($durHit['cut'] ?? 0),
                'detail' => (string)($durHit['detail'] ?? '무기 내구 차감'),
                'msg' => (string)($durHit['msg'] ?? '🐉 보스 반격! 무기 내구 차감'),
            ];
        }

        $durHit = boss_raid_반격_무기내구차감($nick);
        return [
            'type' => 'durability',
            'amount' => (string)(int)($durHit['cut'] ?? 0),
            'detail' => (string)($durHit['detail'] ?? '무기 내구 차감'),
            'msg' => (string)($durHit['msg'] ?? '🐉 보스 반격! 무기 내구 차감'),
        ];
    }
}

if (!function_exists('boss_raid_반격_실행')) {
    /**
     * 개인 반격(매 공격): 회피 → 보호 차감(이번 데미지×N%) → 보호 0이면 성향 피해
     * @return array{type:string,msg:string,detail:string,amount:string,items?:string[]}
     */
    function boss_raid_반격_실행($nick, $boss_idx = 0, $damage = 0): array {
        $nick = trim((string)$nick);
        $esc = addslashes($nick);
        $mid = (int)$boss_idx;
        $dmg = max(0, (int)$damage);

        // 0) 반격 회피
        $dodgePct = max(0, min(100, (int)BOSS_RAID_COUNTER_DODGE_PCT));
        if ($dodgePct > 0 && random_int(1, 100) <= $dodgePct) {
            $detail = '반격 회피! (피해 없음)';
            return [
                'type' => 'dodge',
                'amount' => '0',
                'detail' => $detail,
                'msg' => "✨ {$detail}",
            ];
        }

        // 1) 보호 있으면 이번 공격 데미지의 N% 차감 (보유량과 무관)
        $prow = db_select("SELECT IFNULL(protect, 0) AS protect FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        $curProtect = max(0, (int)($prow['protect'] ?? 0));
        if ($curProtect > 0) {
            $pct = max(1, min(100, (int)BOSS_RAID_COUNTER_PROTECT_DMG_PCT));
            $cut = (int)round($dmg * ($pct / 100.0));
            if ($cut < 1 && $dmg > 0) {
                $cut = 1;
            }
            $cut = min($cut, $curProtect);
            if ($cut < 1) {
                // 데미지 0 등으로 차감량 0이면 보호만 유지(성향 피해로 넘기지 않음)
                return [
                    'type' => 'protect',
                    'amount' => '0',
                    'detail' => "보호 유지 (잔여 {$curProtect})",
                    'msg' => "🐉 보스 반격! 보호 유지 (잔여 {$curProtect})",
                ];
            }
            $left = $curProtect - $cut;
            db_query("UPDATE tb_member SET protect = {$left} WHERE name = '{$esc}' LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('보스반격-보호', $nick, "딜{$dmg}의{$pct}%", 0, $cut);
            }
            $detail = "보호 -{$cut} (딜 {$dmg}의 {$pct}% · 잔여 {$left})";
            return [
                'type' => 'protect',
                'amount' => (string)$cut,
                'detail' => $detail,
                'msg' => "🐉 보스 반격! {$detail}",
            ];
        }

        // 2) 보호 0 → 성향 피해(냥/내구10%/스왑 · 성향별 단일)
        return boss_raid_반격_무보호피해($nick, $mid);
    }
}

if (!function_exists('boss_raid_광역보호_예정횟수')) {
    /**
     * 전투 경과 시간 기준 발동해야 할 광역 횟수 (0~MAX)
     * 5분전: 약 50초마다 (50/100/150/200/250초) — 종료 전에 최대 5회
     */
    function boss_raid_광역보호_예정횟수(array $boss): int {
        $max = max(1, (int)BOSS_RAID_AOE_PROTECT_MAX);
        $created = !empty($boss['created_at']) ? strtotime((string)$boss['created_at']) : false;
        $ends = !empty($boss['fight_ends_at']) ? strtotime((string)$boss['fight_ends_at']) : false;
        if ($created === false) {
            return 0;
        }
        $fightSec = ($ends !== false && $ends > $created)
            ? max(60, $ends - $created)
            : max(60, (int)boss_raid_제한분() * 60);
        $elapsed = max(0, time() - $created);
        if ($elapsed < 1) {
            return 0;
        }
        $due = 0;
        for ($i = 1; $i <= $max; $i++) {
            $at = (int)floor($fightSec * $i / ($max + 1));
            if ($at < 1) {
                $at = 1;
            }
            if ($elapsed >= $at) {
                $due = $i;
            }
        }
        return max(0, min($max, $due));
    }
}

if (!function_exists('boss_raid_광역보호_실행')) {
    /**
     * 광역 1회: 참여자별 본인 누적 데미지의 N%만큼 보호 차감.
     * 보호 0이면 성향 피해. 발동 시 count+1.
     * @return array<string,mixed>|null
     */
    function boss_raid_광역보호_실행(int $boss_idx): ?array {
        $mid = (int)$boss_idx;
        if ($mid < 1) {
            return null;
        }
        $max = max(1, (int)BOSS_RAID_AOE_PROTECT_MAX);
        $targets = boss_raid_참여자목록($mid);
        if ($targets === []) {
            return null;
        }

        global $conn;
        @db_query("
          UPDATE tb_boss_raid
          SET aoe_protect_count = aoe_protect_count + 1
          WHERE idx = {$mid} AND status = 0 AND aoe_protect_count < {$max}
          LIMIT 1
        ");
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if (!$ok) {
            return null;
        }
        $crow = @db_select("SELECT aoe_protect_count FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        $wave = max(1, (int)($crow['aoe_protect_count'] ?? 1));

        $dmgMap = [];
        $rsDmg = @db_query("
          SELECT nick, COALESCE(SUM(damage), 0) AS dmg
          FROM tb_boss_raid_hit
          WHERE boss_idx = {$mid}
          GROUP BY nick
        ");
        if ($rsDmg) {
            while ($row = db_fetch($rsDmg)) {
                $nn = trim((string)($row['nick'] ?? ''));
                if ($nn === '') {
                    continue;
                }
                $dmgMap[$nn] = max(0, (int)($row['dmg'] ?? 0));
            }
        }

        $protectMap = [];
        foreach ($targets as $n) {
            $n = trim((string)$n);
            if ($n === '') {
                continue;
            }
            $esc = addslashes($n);
            $prow = @db_select("SELECT IFNULL(protect, 0) AS protect FROM tb_member WHERE name = '{$esc}' LIMIT 1");
            $protectMap[$n] = max(0, (int)($prow['protect'] ?? 0));
        }
        if ($protectMap === []) {
            return null;
        }

        $pct = max(1, min(100, (int)BOSS_RAID_AOE_PROTECT_DMG_PCT));

        $hits = [];
        $protectHits = 0;
        $penaltyHits = 0;
        $totalCut = 0;
        foreach ($protectMap as $n => $curProtect) {
            $myDmg = max(0, (int)($dmgMap[$n] ?? 0));
            if ($curProtect > 0) {
                $cut = (int)round($myDmg * ($pct / 100.0));
                if ($cut < 1 && $myDmg > 0) {
                    $cut = 1;
                }
                $cut = min($cut, $curProtect);
                if ($cut < 1) {
                    $detail = "보호 유지 (잔여 {$curProtect} · 누적딜 {$myDmg})";
                    $hit = [
                        'nick' => $n,
                        'type' => 'protect',
                        'amount' => '0',
                        'detail' => $detail,
                        'msg' => "🐉 광역! {$detail}",
                    ];
                    $hits[] = $hit;
                    $protectHits++;
                    boss_raid_반격_기록($mid, $n, $hit);
                    continue;
                }
                $left = $curProtect - $cut;
                $esc = addslashes($n);
                db_query("UPDATE tb_member SET protect = {$left} WHERE name = '{$esc}' LIMIT 1");
                if (function_exists('지급로그')) {
                    지급로그('보스광역-보호', $n, "누적딜{$myDmg}의{$pct}%", 0, $cut);
                }
                $detail = "보호 -{$cut} (누적딜 {$myDmg}의 {$pct}% · 잔여 {$left})";
                $hit = [
                    'nick' => $n,
                    'type' => 'protect',
                    'amount' => (string)$cut,
                    'detail' => $detail,
                    'msg' => "🐉 광역! {$detail}",
                ];
                $hits[] = $hit;
                $protectHits++;
                $totalCut += $cut;
                boss_raid_반격_기록($mid, $n, $hit);
                continue;
            }

            // 보호 0 → 성향 피해
            $pen = boss_raid_반격_무보호피해($n, $mid);
            $pen['nick'] = $n;
            if (strpos((string)($pen['msg'] ?? ''), '광역') === false) {
                $pen['msg'] = '🐉 광역! ' . ltrim(str_replace('🐉 보스 반격! ', '', (string)($pen['msg'] ?? '성향 피해')));
            }
            $hits[] = $pen;
            $penaltyHits++;
            boss_raid_반격_기록($mid, $n, $pen);
        }

        $parts = [];
        foreach ($hits as $h) {
            $nick = trim((string)($h['nick'] ?? ''));
            $detail = trim((string)($h['detail'] ?? $h['type'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $parts[] = "· {$nick}: {$detail}";
        }
        $cutLabel = "각자 누적딜의 {$pct}% 보호 차감 (총 보호 -" . number_format($totalCut) . ')';
        $msg = "🐉 보스 광역 공격! ({$wave}/{$max})\n"
            . "{$cutLabel}\n"
            . "보호차감 {$protectHits}명 · 성향피해 {$penaltyHits}명 · 대상 " . count($hits) . "명\n\n"
            . implode("\n", $parts);

        if (function_exists('boss_raid_홍보알림')) {
            boss_raid_홍보알림($msg, 'boss_aoe_' . $mid . '_' . $wave);
        }

        return [
            'type' => 'aoe_protect',
            'wave' => $wave,
            'max' => $max,
            'pct' => $pct,
            'cut_each' => 0,
            'sum_protect' => 0,
            'total_cut' => $totalCut,
            'protect_hits' => $protectHits,
            'penalty_hits' => $penaltyHits,
            'amount' => (string)$totalCut,
            'detail' => mb_substr($cutLabel, 0, 180),
            'msg' => $msg,
            'hits' => $hits,
        ];
    }
}

if (!function_exists('boss_raid_광역보호_동기화')) {
    /**
     * 경과 시간 기준으로 밀린 광역 최대 1회 발동
     * @return array<string,mixed>|null
     */
    function boss_raid_광역보호_동기화(array $boss): ?array {
        $mid = (int)($boss['idx'] ?? 0);
        if ($mid < 1 || (int)($boss['status'] ?? -1) !== 0) {
            return null;
        }
        $max = max(1, (int)BOSS_RAID_AOE_PROTECT_MAX);
        $count = (int)($boss['aoe_protect_count'] ?? 0);
        if ($count >= $max) {
            return null;
        }
        $due = boss_raid_광역보호_예정횟수($boss);
        if ($count >= $due) {
            return null;
        }
        return boss_raid_광역보호_실행($mid);
    }
}

if (!function_exists('boss_raid_마법보호_페이로드')) {
    /**
     * 마법 유저: 채굴 숨김 + 파티 보호 한도 (이번 보스 mining_used · 채팅 magic_used와 무관)
     * @return array<string,mixed>
     */
    function boss_raid_마법보호_페이로드($nick, array $weapon, int $enhance, bool $fighting, int $left, int $hp_now, int $protectUsed = 0): array {
        unset($nick);
        $isMagic = boss_raid_마법무기인가($weapon);
        $max = boss_raid_마법보호횟수($weapon, $enhance);
        $used = $isMagic ? max(0, $protectUsed) : 0;
        $protectLeft = max(0, $max - $used);
        $durOk = ($enhance < 10) || ((int)($weapon['durability'] ?? 0) >= 1);
        $can = $fighting && $isMagic && $enhance >= (int)BOSS_RAID_MIN_ENHANCE
            && $protectLeft > 0 && $left > 0 && $hp_now > 0 && $durOk;
        $out = [
            'is_magic' => $isMagic,
            'protect_max' => $max,
            'protect_used' => $used,
            'protect_left' => $protectLeft,
            'protect_reset_in' => 0,
            'can_protect' => $can,
            'protect_hint' => '전원 강화~2배 · 크리 40% ~3배 · 이번 보스',
        ];
        if ($isMagic) {
            $out['can_mining'] = false;
            $out['show_shard_mining'] = false;
            $out['can_shard_mining'] = false;
            $out['mining_dmg_hint'] = '도전자 전원 보호 · 량만 채팅 .보호와 동일 · 한도는 이번 보스';
        }
        return $out;
    }
}

if (!function_exists('boss_raid_단소_페이로드')) {
    /**
     * 단소: 채굴 숨김 + 필살 한도 (1시간 · 채팅 .시전과 별개 · 보스 교체해도 유지)
     * @return array<string,mixed>
     */
    function boss_raid_단소_페이로드($nick, array $weapon, int $enhance, bool $fighting, int $left, int $hp_now, int $hp_max, bool $durOk = true): array {
        $isDanso = boss_raid_단소무기인가($weapon);
        $max = boss_raid_단소필살횟수($weapon, $enhance);
        $used = 0;
        $resetAt = 0;
        if ($isDanso) {
            $구간 = boss_raid_단소필살구간_적용($nick, false);
            $used = (int)($구간['used'] ?? 0);
            $resetAt = (int)($구간['reset_at'] ?? 0);
        }
        $finishLeft = max(0, $max - $used);
        $resetIn = ($resetAt > 0) ? max(0, $resetAt - time()) : 0;
        $hpPct = $hp_max > 0 ? (($hp_now / $hp_max) * 100.0) : 0.0;
        $executeOn = $isDanso && $hp_now > 0 && $hpPct <= (float)BOSS_RAID_DANSO_EXECUTE_HP_PCT;
        $can = $fighting && $isDanso && $enhance >= (int)BOSS_RAID_MIN_ENHANCE
            && $finishLeft > 0 && $left > 0 && $hp_now > 0 && $durOk;
        $showShard = $isDanso && $max > 0 && $finishLeft < 1;
        $shardHave = $showShard ? boss_raid_은총조각_보유($nick) : 0;
        $out = [
            'is_danso' => $isDanso,
            'finish_max' => $max,
            'finish_used' => $used,
            'finish_left' => $finishLeft,
            'finish_reset_in' => $resetIn,
            'can_finish' => $can,
            'show_shard_finish' => $showShard,
            'can_shard_finish' => $showShard && $shardHave >= 1,
            'danso_execute_on' => $executeOn,
            'danso_execute_hp_pct' => (int)BOSS_RAID_DANSO_EXECUTE_HP_PCT,
            'finish_hint' => '무기×3~6 · HP 50%↓ 단소×2 · 1시간',
        ];
        if ($isDanso) {
            $out['can_mining'] = false;
            $out['show_shard_mining'] = false;
            $out['can_shard_mining'] = false;
            $out['mining_dmg_hint'] = '필살 무기×3~6 · 한도 무기와 동일 · 1시간 · 0이면 은총조각 1개 초기화';
            $out['weapon_dmg_hint'] = '강화×10 ~ 강화×10+100 · 단소 횟수×2 · HP50%↓ ×2 · 크리 +20% · 🎰대박타 +20↑';
        }
        return $out;
    }
}

if (!function_exists('boss_raid_상태_페이로드')) {
    /**
     * @param array{lite?:bool} $opts lite=true 이면 폴링용(이력·로스터 생략)
     */
    function boss_raid_상태_페이로드($nick, array $opts = []): array {
        $lite = !empty($opts['lite']);
        $st = boss_raid_활성_상태();
        // 폴링만 해도 밀린 광역이 발동되도록 (공격/채팅틱에만 의존하지 않음)
        if (($st['phase'] ?? '') === 'fighting' && !empty($st['boss']['idx'])) {
            $aoe = boss_raid_광역보호_동기화($st['boss']);
            if (is_array($aoe) && !empty($aoe['type'])) {
                $freshBoss = @db_select("SELECT * FROM tb_boss_raid WHERE idx = " . (int)$st['boss']['idx'] . " LIMIT 1");
                if (is_array($freshBoss) && !empty($freshBoss['idx'])) {
                    $st['boss'] = $freshBoss;
                }
            }
        }
        $weapon = boss_raid_무기정보($nick);
        $enhance = (int)$weapon['enhance'];
        $mining_lv = boss_raid_채굴레벨($nick);
        $weapon_max = boss_raid_무기공격횟수($enhance, $weapon);
        $mining_max = boss_raid_채굴공격횟수($mining_lv);
        $mining_label = '';
        if (function_exists('mining_tool_def')) {
            $def = mining_tool_def($mining_lv);
            $mining_label = trim(($def['icon'] ?? '') . ($def['label'] ?? ''));
        }
        if ($mining_label === '' && function_exists('boss_raid_채굴라벨')) {
            $mining_label = boss_raid_채굴라벨($mining_lv);
        }
        $nextKey = boss_raid_다음종류키();
        $nextDef = boss_raid_종류($nextKey);
        $reward = boss_raid_보상1인((float)$nextDef['reward_pct']);
        $rewardPctLabel = boss_raid_보상비율문구((float)$nextDef['reward_pct']);

        $shardHave = boss_raid_은총조각_보유($nick);
        $stolenOnce = boss_raid_처치풀_조회();
        $stolenFmtOnce = boss_raid_냥축약($stolenOnce);
        $roster = $lite ? [] : array_values(boss_raid_종류목록());

        if (($st['phase'] ?? '') === 'waiting') {
            // 직전 기여/광역 이력은 /page/boss_history.php 에서만 로드 (첫 화면 왕복 절감)
            $hasPrev = empty($st['need_admin']);
            $lastEnded = boss_raid_마지막종료행();
            $lastEndStatus = (int)($lastEnded['status'] ?? 0); // 1=처치 2=타임오버
            $lastEndDef = boss_raid_종류((string)($lastEnded['boss_key'] ?? ''));
            $lastEndName = trim((string)($lastEndDef['name'] ?? ''));
            $lastEndEmoji = trim((string)($lastEndDef['emoji'] ?? ''));
            $waitPayload = [
                'phase' => 'waiting',
                'emoji' => $nextDef['emoji'],
                'name' => $nextDef['name'],
                'boss_key' => $nextDef['key'],
                'blurb' => $nextDef['blurb'],
                'style' => (string)($nextDef['style'] ?? 'steal'),
                'style_label' => (string)($nextDef['style_label'] ?? ''),
                'style_desc' => (string)($nextDef['style_desc'] ?? ''),
            'enhance' => $enhance,
            'weapon_item' => (string)$weapon['item'],
            'weapon_fx' => boss_raid_무기fx키($weapon['item'] ?? ''),
            'weapon_durability' => (int)$weapon['durability'],
            'mining_level' => $mining_lv,
            'mining_label' => $mining_label !== '' ? $mining_label : ('Lv' . $mining_lv),
            'weapon_max' => $weapon_max,
            'mining_max' => $mining_max,
            'min_enhance' => (int)BOSS_RAID_MIN_ENHANCE,
            'can_join' => $enhance >= (int)BOSS_RAID_MIN_ENHANCE,
            'weapon_dmg_hint' => '강화×10 ~ 강화×10+100 · 활 +10% · 크리 +20% · 🎰대박타 +20↑ 구간별 ×3~5',
            'mining_dmg_hint' => '레벨×30~40 · 크리티컬 +10~20%',
            'reward_each' => $reward,
            'reward_each_fmt' => number_format($reward),
            'reward_pct' => (float)$nextDef['reward_pct'],
            'reward_pct_label' => $rewardPctLabel,
            'hp_design_note' => '관리자 .보스출현(랜덤) · 제한 30분 · 직전이력은 내역 페이지 · 다음 출현 시 삭제 · 3~5시간 후 로테이션',
            'is_junho' => boss_raid_준호인가($nick),
            'boss_idx' => 0,
                'hp_max' => boss_raid_출현HP((int)$nextDef['hp']),
                'hp_now' => boss_raid_출현HP((int)$nextDef['hp']),
                'hp_pct' => 100,
                'night_hp_half' => false,
                'stolen_point' => $stolenOnce,
                'stolen_point_fmt' => $stolenFmtOnce,
                'fight_left_sec' => 0,
                'next_spawn_at' => (string)($st['next_spawn_at'] ?? ''),
                'next_spawn_in' => (int)($st['next_spawn_in'] ?? 0),
                'need_admin' => !empty($st['need_admin']),
                'fight_min' => boss_raid_제한분(),
                'weapon_used' => 0,
                'mining_used' => 0,
                'weapon_left' => 0,
                'mining_left' => 0,
                'can_weapon' => false,
                'can_mining' => false,
                'eunchong_shard' => $shardHave,
                'show_shard_weapon' => false,
                'show_shard_mining' => false,
                'can_shard_weapon' => false,
                'can_shard_mining' => false,
                'can_shard_repair' => false,
                'prev_result' => false,
                'prev_result_label' => '',
                'has_prev_result' => $hasPrev,
                'last_end_status' => $lastEndStatus,
                'last_end_name' => $lastEndName,
                'last_end_emoji' => $lastEndEmoji,
                'rank' => [],
                'participants' => 0,
                'counters' => [],
                'my_hits' => [],
                'drops' => [],
                'recent_hits' => [],
                'drop_latest' => null,
                'roster' => $roster,
                'lite' => $lite,
            ];
            return array_merge(
                $waitPayload,
                boss_raid_마법보호_페이로드($nick, $weapon, $enhance, false, 0, 0, 0),
                boss_raid_단소_페이로드($nick, $weapon, $enhance, false, 0, 0, 0, false)
            );
        }

        $boss = $st['boss'] ?? [];
        $mid = (int)($boss['idx'] ?? 0);
        $def = boss_raid_종류((string)($boss['boss_key'] ?? ''));
        $reward = boss_raid_보상1인((float)$def['reward_pct']);
        $rewardPctLabel = boss_raid_보상비율문구((float)$def['reward_pct']);
        $hp_max = (int)($boss['hp_max'] ?? $def['hp']);
        $hp_now = max(0, (int)($boss['hp_now'] ?? 0));
        $pct = $hp_max > 0 ? round(($hp_now / $hp_max) * 100, 1) : 0;
        $stolenRaw = $stolenOnce;
        $stolenFmt = $stolenFmtOnce;
        $ends = !empty($boss['fight_ends_at']) ? strtotime((string)$boss['fight_ends_at']) : false;
        $left = ($ends !== false) ? max(0, $ends - time()) : 0;
        $pl = boss_raid_플레이어행($mid, $nick);
        $weapon_used = (int)$pl['weapon_used'];
        $mining_used = (int)$pl['mining_used'];
        $weapon_left = max(0, $weapon_max - $weapon_used);
        $mining_left = max(0, $mining_max - $mining_used);
        $durSt = boss_raid_무기내구_상태($nick);
        $dur_ok = empty($durSt['blocked']);
        $mining_dur_ok = ($mining_used < 1) || (boss_raid_채굴내구_조회($nick) > 1e-9);

        $drops = $lite ? [] : boss_raid_드랍이력($mid, 12);
        $fightPayload = [
            'phase' => $st['phase'],
            'emoji' => $def['emoji'],
            'name' => $def['name'],
            'boss_key' => $def['key'],
            'blurb' => $def['blurb'],
            'style' => (string)($def['style'] ?? 'steal'),
            'style_label' => (string)($def['style_label'] ?? ''),
            'style_desc' => (string)($def['style_desc'] ?? ''),
            'enhance' => $enhance,
            'weapon_item' => (string)$weapon['item'],
            'weapon_fx' => boss_raid_무기fx키($weapon['item'] ?? ''),
            'weapon_durability' => (int)$weapon['durability'],
            'mining_level' => $mining_lv,
            'mining_label' => $mining_label !== '' ? $mining_label : ('Lv' . $mining_lv),
            'weapon_max' => $weapon_max,
            'mining_max' => $mining_max,
            'min_enhance' => (int)BOSS_RAID_MIN_ENHANCE,
            'can_join' => $enhance >= (int)BOSS_RAID_MIN_ENHANCE,
            'weapon_dmg_hint' => '강화×10 ~ 강화×10+100 · 활 +10% · 크리 +20% · 🎰대박타 +20↑ 구간별 ×3~5',
            'mining_dmg_hint' => '레벨×30~40 · 크리티컬 +10~20%',
            'reward_each' => $reward,
            'reward_each_fmt' => number_format($reward),
            'reward_pct' => (float)$def['reward_pct'],
            'reward_pct_label' => $rewardPctLabel,
            'hp_design_note' => '관리자 .보스출현(랜덤) · 제한 30분 · 실패 시 초기화 · 3~5시간 후 로테이션',
            'is_junho' => boss_raid_준호인가($nick),
            'boss_idx' => $mid,
            'hp_max' => $hp_max,
            'hp_now' => $hp_now,
            'hp_pct' => $pct,
            'night_hp_half' => false,
            'stolen_point' => $stolenRaw,
            'stolen_point_fmt' => $stolenFmt,
            'fight_ends_at' => (string)($boss['fight_ends_at'] ?? ''),
            'fight_left_sec' => $left,
            'aoe_protect_count' => (int)($boss['aoe_protect_count'] ?? 0),
            'aoe_protect_max' => (int)BOSS_RAID_AOE_PROTECT_MAX,
            'fight_min' => boss_raid_제한분(
                !empty($boss['created_at']) ? strtotime((string)$boss['created_at']) : null
            ),
            'next_spawn_at' => '',
            'next_spawn_in' => 0,
            'need_admin' => false,
            'weapon_used' => $weapon_used,
            'mining_used' => $mining_used,
            'weapon_left' => $weapon_left,
            'mining_left' => $mining_left,
            'can_weapon' => $dur_ok && $enhance >= (int)BOSS_RAID_MIN_ENHANCE && $weapon_left > 0 && $left > 0 && $hp_now > 0,
            // 채굴: 장비 레벨만큼 · 횟수 소진 시 은총조각으로 채굴횟수+내구 초기화 · 2회차부터 채굴내구 필요
            'can_mining' => $dur_ok && $enhance >= (int)BOSS_RAID_MIN_ENHANCE && $mining_lv > 0
                && $mining_left > 0 && $mining_dur_ok && $left > 0 && $hp_now > 0,
            'eunchong_shard' => $shardHave,
            'show_shard_weapon' => $weapon_max > 0 && $weapon_left < 1 && $left > 0 && $hp_now > 0,
            'show_shard_mining' => $mining_max > 0 && $mining_left < 1 && $left > 0 && $hp_now > 0,
            'can_shard_weapon' => $shardHave >= 1 && $weapon_max > 0 && $weapon_left < 1 && $left > 0 && $hp_now > 0,
            'can_shard_mining' => $shardHave >= 1 && $mining_max > 0 && $mining_left < 1 && $left > 0 && $hp_now > 0,
            'can_shard_repair' => false,
            'weapon_dur_current' => (int)$durSt['current'],
            'weapon_dur_max' => (int)$durSt['max'],
            'weapon_dur_blocked' => !$dur_ok,
            'prev_result' => false,
            'prev_result_label' => '',
            'has_prev_result' => false,
            'rank' => boss_raid_기여순위_표시($mid, $lite ? 10 : 20),
            'participants' => boss_raid_참여자수($mid),
            'counters' => $lite ? [] : boss_raid_반격이력($mid, 40),
            'my_hits' => $lite ? [] : boss_raid_내타격이력($mid, $nick, 20),
            'drops' => $drops,
            'recent_hits' => $lite ? boss_raid_최신타격($mid, 8) : boss_raid_최신타격($mid, 20),
            'drop_latest' => $drops[0] ?? null,
            'roster' => $roster,
            'lite' => $lite,
        ];
        return array_merge(
            $fightPayload,
            boss_raid_마법보호_페이로드($nick, $weapon, $enhance, true, $left, $hp_now, $mining_used),
            boss_raid_단소_페이로드($nick, $weapon, $enhance, true, $left, $hp_now, $hp_max, $dur_ok)
        );
    }
}

if (!function_exists('boss_raid_파티보호')) {
    /**
     * 마법: 채굴 대신 현재 도전자(+시전자) 전원 보호
     * @return array<string,mixed>
     */
    function boss_raid_파티보호($nick): array {
        $nick = trim((string)$nick);
        if ($nick === '' || !boss_raid_접근가능($nick)) {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없어요.'];
        }

        $st = boss_raid_활성_상태();
        if (($st['phase'] ?? '') === 'waiting') {
            $in = (int)($st['next_spawn_in'] ?? 0);
            if (!empty($st['need_admin']) && $in < 1) {
                return [
                    'ok' => false,
                    'data' => '보스가 없어요. 건의방 관리자 `.보스출현` 으로 소환해 주세요.',
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            if ($in > 0) {
                $m = (int)floor($in / 60);
                $s = $in % 60;
                return [
                    'ok' => false,
                    'data' => "보스가 쉬는 중이에요. 다음 타임까지 {$m}분 {$s}초 · 그동안 보호 불가",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            return [
                'ok' => false,
                'data' => '보스가 쉬는 중이에요. 다음 출현을 기다려 주세요.',
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }
        $boss = $st['boss'] ?? [];
        $mid = (int)($boss['idx'] ?? 0);
        if ($mid < 1 || (int)($boss['status'] ?? -1) !== 0) {
            return ['ok' => false, 'data' => '진행 중인 보스가 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $ends = !empty($boss['fight_ends_at']) ? strtotime((string)$boss['fight_ends_at']) : false;
        if ($ends !== false && $ends <= time()) {
            boss_raid_타임오버_처리($boss);
            return ['ok' => false, 'data' => '⏱ ' . boss_raid_제한분() . '분이 끝나 이번 타임은 종료됐어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }
        if ((int)($boss['hp_now'] ?? 0) < 1) {
            return ['ok' => false, 'data' => '이미 처치된 보스예요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $weapon = boss_raid_무기정보($nick);
        $enhance = (int)$weapon['enhance'];
        if (!boss_raid_마법무기인가($weapon)) {
            return ['ok' => false, 'data' => '파티 보호는 마법 무기만 사용할 수 있어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }
        if ($enhance < (int)BOSS_RAID_MIN_ENHANCE) {
            return ['ok' => false, 'data' => '무기 +' . (int)BOSS_RAID_MIN_ENHANCE . ' 이상부터 참여할 수 있어요. (현재 +' . $enhance . ')', 'state' => boss_raid_상태_페이로드($nick)];
        }
        if ($enhance >= 10 && (int)($weapon['durability'] ?? 0) < 1) {
            return ['ok' => false, 'data' => '❌ 내구도 0 사용불가.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $max = boss_raid_마법보호횟수($weapon, $enhance);
        if ($max < 1) {
            return ['ok' => false, 'data' => '보호 횟수가 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }
        $pl = boss_raid_플레이어행($mid, $nick);
        $used = (int)($pl['mining_used'] ?? 0);
        if ($used >= $max) {
            return [
                'ok' => false,
                'data' => "이번 보스 파티 보호를 모두 썼어요. (+{$enhance} → {$max}회 · 다음 보스에서 다시)",
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }

        $crit = function_exists('마법_보호_크리티컬인가')
            ? (bool)마법_보호_크리티컬인가()
            : (mt_rand(1, 100) <= 40);
        $amount = function_exists('마법_보호_지급량')
            ? (int)마법_보호_지급량($enhance, $crit)
            : mt_rand($enhance, $crit ? ($enhance * 3) : ($enhance * 2));
        $amount = max(0, $amount);
        if ($amount < 1) {
            return ['ok' => false, 'data' => '보호량이 0이라 적용되지 않아요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $targets = boss_raid_도전자닉목록($mid, $nick);
        if ($targets === []) {
            $targets = [$nick];
        }
        $in = [];
        foreach ($targets as $t) {
            $t = trim((string)$t);
            if ($t === '') {
                continue;
            }
            $in[] = "'" . addslashes($t) . "'";
        }
        $cnt = count($in);
        if ($cnt < 1) {
            return ['ok' => false, 'data' => '보호할 대상이 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }
        db_query("UPDATE tb_member SET protect = IFNULL(protect, 0) + {$amount} WHERE name IN (" . implode(',', $in) . ")");

        $esc = addslashes($nick);
        boss_raid_플레이어_증가($mid, $nick, 'protect');
        if ($enhance >= 10) {
            @db_query("UPDATE tb_member SET durability = GREATEST(IFNULL(durability, 0) - 1, 0) WHERE name = '{$esc}' LIMIT 1");
        }

        $critFlag = $crit ? 1 : 0;
        db_query("
          INSERT INTO tb_boss_raid_hit (boss_idx, nick, attack_type, damage, enhance, mining_level, crit, weapon_fx)
          VALUES ({$mid}, '{$esc}', 'protect', 0, {$enhance}, {$amount}, {$critFlag}, 'protect')
        ");

        $머리 = $crit ? '💥크리' : '🛡️';
        $msg = "{$머리} 파티 보호 +{$amount} · {$cnt}명 (도전자 전원)";
        if ($enhance >= 10) {
            $afterDur = max(0, (int)($weapon['durability'] ?? 0) - 1);
            $msg .= "\n무기 내구 -1 ({$afterDur})";
        }
        $left = max(0, $max - $used - 1);
        $msg .= "\n남은 {$left}/{$max} · 이번 보스";

        return [
            'ok' => true,
            'data' => $msg,
            'amount' => $amount,
            'crit' => $crit,
            'targets' => $cnt,
            'attack_type' => 'protect',
            'weapon_fx' => 'protect',
            'state' => boss_raid_상태_페이로드($nick),
        ];
    }
}

if (!function_exists('boss_raid_공격')) {
    /**
     * @param 'weapon'|'mining'|'finish' $type
     * @return array<string,mixed>
     */
    function boss_raid_공격($nick, $type): array {
        $nick = trim((string)$nick);
        if ($nick === '' || !boss_raid_접근가능($nick)) {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없어요.'];
        }
        if ($type !== 'weapon' && $type !== 'mining' && $type !== 'finish') {
            return ['ok' => false, 'data' => '공격 종류가 올바르지 않아요.'];
        }

        $st = boss_raid_활성_상태();
        if (($st['phase'] ?? '') === 'waiting') {
            $in = (int)($st['next_spawn_in'] ?? 0);
            if (!empty($st['need_admin']) && $in < 1) {
                return [
                    'ok' => false,
                    'data' => '보스가 없어요. 건의방 관리자 `.보스출현` 으로 소환해 주세요.',
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            if ($in > 0) {
                $m = (int)floor($in / 60);
                $s = $in % 60;
                return [
                    'ok' => false,
                    'data' => "보스가 쉬는 중이에요. 다음 타임까지 {$m}분 {$s}초 · 그동안 공격 불가",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            return [
                'ok' => false,
                'data' => '보스가 쉬는 중이에요. 다음 출현을 기다려 주세요.',
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }
        $boss = $st['boss'] ?? [];
        $mid = (int)($boss['idx'] ?? 0);
        if ($mid < 1 || (int)($boss['status'] ?? -1) !== 0) {
            return ['ok' => false, 'data' => '진행 중인 보스가 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        // 이중 방어: 직전 처치 쿨다운이 남아 있는데, 그보다 이전에 생성된 진행 보스가 남아 있으면 정리
        $spawnAt = boss_raid_스폰가능시각();
        $lastEnded = boss_raid_마지막종료행();
        $lastEndedId = (int)($lastEnded['idx'] ?? 0);
        if ($lastEndedId > 0 && $lastEndedId !== $mid && $spawnAt > time()) {
            $activeCreated = !empty($boss['created_at']) ? strtotime((string)$boss['created_at']) : false;
            $endedAt = !empty($lastEnded['killed_at'])
                ? strtotime((string)$lastEnded['killed_at'])
                : (!empty($lastEnded['created_at']) ? strtotime((string)$lastEnded['created_at']) : false);
            if ($activeCreated !== false && $endedAt !== false && $activeCreated <= $endedAt) {
                $next = date('Y-m-d H:i:s', $spawnAt);
                boss_raid_진행중_전부종료(0, $next, true);
                $in = $spawnAt - time();
                $m = (int)floor($in / 60);
                $s = $in % 60;
                return [
                    'ok' => false,
                    'data' => "보스가 쉬는 중이에요. 다음 타임까지 {$m}분 {$s}초 · 그동안 공격 불가",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
        }

        $ends = !empty($boss['fight_ends_at']) ? strtotime((string)$boss['fight_ends_at']) : false;
        if ($ends !== false && $ends <= time()) {
            boss_raid_타임오버_처리($boss);
            return ['ok' => false, 'data' => '⏱ ' . boss_raid_제한분() . '분이 끝나 이번 타임은 종료됐어요. 다음 타임까지 공격할 수 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $weapon = boss_raid_무기정보($nick);
        $enhance = (int)$weapon['enhance'];
        if ($type === 'mining' && (boss_raid_마법무기인가($weapon) || boss_raid_단소무기인가($weapon))) {
            return [
                'ok' => false,
                'data' => boss_raid_단소무기인가($weapon)
                    ? '단소는 보스에서 채굴 대신 필살을 사용해요.'
                    : '마법은 보스에서 채굴 대신 보호를 사용해요.',
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }
        if ($type === 'finish' && !boss_raid_단소무기인가($weapon)) {
            return ['ok' => false, 'data' => '필살은 단소만 사용할 수 있어요.', 'state' => boss_raid_상태_페이로드($nick)];
        }
        if ($enhance < (int)BOSS_RAID_MIN_ENHANCE) {
            return ['ok' => false, 'data' => '무기 +' . (int)BOSS_RAID_MIN_ENHANCE . ' 이상부터 참여할 수 있어요. (현재 +' . $enhance . ')', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $durSt = boss_raid_무기내구_상태($nick);
        if (!empty($durSt['blocked'])) {
            $cur = (int)$durSt['current'];
            $max = (int)$durSt['max'];
            return [
                'ok' => false,
                'data' => "❌ 무기 내구도가 0이라 공격할 수 없어요. 수리 후 다시 가능해요. (현재 {$cur}/{$max})",
                'state' => boss_raid_상태_페이로드($nick),
            ];
        }

        $pl = boss_raid_플레이어행($mid, $nick);
        $weapon_max = boss_raid_무기공격횟수($enhance, $weapon);
        if ($type === 'weapon') {
            if ((int)$pl['weapon_used'] >= $weapon_max) {
                return ['ok' => false, 'data' => "무기 공격을 모두 사용했어요. (+{$enhance} → {$weapon_max}회 · 은총조각으로 횟수+내구 100% 충전 가능)", 'state' => boss_raid_상태_페이로드($nick)];
            }
        } elseif ($type === 'finish') {
            $finish_max = boss_raid_단소필살횟수($weapon, $enhance);
            if ($finish_max < 1) {
                return ['ok' => false, 'data' => '필살 횟수가 없어요.', 'state' => boss_raid_상태_페이로드($nick)];
            }
            $구간 = boss_raid_단소필살구간_적용($nick, false);
            $used = (int)($구간['used'] ?? 0);
            if ($used >= $finish_max) {
                $resetAt = (int)($구간['reset_at'] ?? 0);
                $남은분 = $resetAt > time() ? (int)ceil(($resetAt - time()) / 60) : 0;
                $wait = $남은분 > 0 ? " {$남은분}분 후 초기화." : '';
                return [
                    'ok' => false,
                    'data' => "필살 한도를 모두 썼어요. (+{$enhance} → {$finish_max}회 · 1시간){$wait} 은총조각으로 초기화할 수 있어요.",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
        } else {
            $mining_lv = boss_raid_채굴레벨($nick);
            $mining_max = boss_raid_채굴공격횟수($mining_lv);
            if ($mining_lv < 1 || $mining_max < 1) {
                return ['ok' => false, 'data' => '채굴 장비가 Lv1 이상이어야 해요.', 'state' => boss_raid_상태_페이로드($nick)];
            }
            if ((int)$pl['mining_used'] >= $mining_max) {
                return [
                    'ok' => false,
                    'data' => "채굴 공격을 모두 사용했어요. (Lv{$mining_lv} → {$mining_max}회 · 은총조각으로 횟수+내구 100% 충전 가능)",
                    'state' => boss_raid_상태_페이로드($nick),
                ];
            }
            // 2회차부터 내구도 필요 (1회차는 차감 없이 가능) · 수리해도 남은 횟수 내에서만 가능
            if ((int)$pl['mining_used'] >= 1) {
                $miningDur = boss_raid_채굴내구_조회($nick);
                if ($miningDur <= 1e-9) {
                    return ['ok' => false, 'data' => '채굴 장비 내구도가 0이에요. 일반 수리는 남은 횟수 안에서만, 횟수 0이면 은총조각으로 횟수+내구 100% 충전이 가능해요.', 'state' => boss_raid_상태_페이로드($nick)];
                }
            }
        }

        $calc = boss_raid_데미지_계산($type, $nick, $mid);
        $dmg = (int)$calc['damage'];
        if ($dmg < 1) {
            return ['ok' => false, 'data' => '데미지가 0이라 공격이 적용되지 않아요.', 'state' => boss_raid_상태_페이로드($nick)];
        }

        $esc = addslashes($nick);
        $type_esc = addslashes($type);
        $enh = (int)$calc['enhance'];
        $mlv = (int)$calc['mining_level'];
        $critFlag = !empty($calc['crit']) ? 1 : 0;
        $weaponFx = $type === 'mining'
            ? 'mining'
            : ($type === 'finish' ? 'finish' : boss_raid_무기fx키($weapon['item'] ?? ''));
        $fx_esc = addslashes($weaponFx);
        db_query("
          INSERT INTO tb_boss_raid_hit (boss_idx, nick, attack_type, damage, enhance, mining_level, crit, weapon_fx)
          VALUES ({$mid}, '{$esc}', '{$type_esc}', {$dmg}, {$enh}, {$mlv}, {$critFlag}, '{$fx_esc}')
        ");
        boss_raid_플레이어_증가($mid, $nick, $type);
        if ($type === 'finish') {
            boss_raid_단소필살구간_적용($nick, true);
            @db_query("UPDATE tb_member SET boss_danso_finish_used = IFNULL(boss_danso_finish_used, 0) + 1 WHERE name = '{$esc}' LIMIT 1");
        }

        global $conn;
        db_query("
          UPDATE tb_boss_raid
          SET hp_now = GREATEST(0, hp_now - {$dmg}),
              last_attack_at = NOW(),
              last_regen_at = NOW()
          WHERE idx = {$mid} AND status = 0
          LIMIT 1
        ");

        $weaponDurInfo = null;
        $miningDurInfo = null;
        if ($type === 'weapon' || $type === 'finish') {
            $weaponDurInfo = boss_raid_무기공격_내구차감($nick);
        } elseif ($type === 'mining') {
            // 공격 전 사용 횟수 기준: 0→1회차는 무료, 1 이상이면 10% 차감
            if ((int)$pl['mining_used'] >= 1) {
                $miningDurInfo = boss_raid_채굴내구_차감($nick);
            } else {
                $miningDurInfo = [
                    'ok' => true,
                    'msg' => '1회차 채굴 공격 · 내구도 차감 없음',
                ];
            }
        }

        // 개인 반격(매 공격 · 보호=이번 데미지×N%) + 경과 시간 광역(최대 5회)
        $counter = boss_raid_반격_실행($nick, $mid, $dmg);
        boss_raid_반격_기록($mid, $nick, $counter);

        $fresh = db_select("SELECT * FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1") ?: $boss;
        $aoe = boss_raid_광역보호_동기화(is_array($fresh) ? $fresh : $boss);

        $drops = boss_raid_공격드랍($nick, $mid);

        $fresh = db_select("SELECT * FROM tb_boss_raid WHERE idx = {$mid} LIMIT 1");
        $hp_after = max(0, (int)($fresh['hp_now'] ?? 0));
        $killed = false;
        $kill_info = null;

        if ($hp_after <= 0 && (int)($fresh['status'] ?? 0) === 0) {
            $respawnSec = boss_raid_리스폰초();
            $next = date('Y-m-d H:i:s', time() + $respawnSec);
            db_query("
              UPDATE tb_boss_raid
              SET status = 1, hp_now = 0, killed_at = NOW(), next_spawn_at = '{$next}'
              WHERE idx = {$mid} AND status = 0
              LIMIT 1
            ");
            $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
            if ($applied) {
                // 처치 직후 다른 진행 보스(레이스 잔여)도 같은 쿨다운으로 종료 — 연달아 공격 방지
                boss_raid_진행중_전부종료($mid, $next);
                $killed = true;
                $kill_info = boss_raid_처치_보상($mid);
                if (is_array($kill_info)) {
                    boss_raid_처치알림($mid, $kill_info);
                }
            }
        }

        $label = $type === 'weapon' ? '무기' : ($type === 'finish' ? '필살' : '채굴');
        $crit = !empty($calc['crit']);
        $jackpot = !empty($calc['jackpot']);
        $jackpotMul = (int)($calc['jackpot_mult'] ?? 1);
        $critBonus = max(0, (int)($calc['crit_mult_pct'] ?? 100) - 100);
        $execNote = !empty($calc['execute']) ? (' · 단소 HP50%↓ ×' . (int)($calc['execute_mult'] ?? 2)) : '';
        if ($type === 'weapon') {
            $range = (int)($calc['base_damage_min'] ?? 0) . '~' . (int)($calc['base_damage_max'] ?? 0);
            $msg = $crit
                ? "💥 {$label} 치명타! -{$dmg} (기본 {$range} · +{$critBonus}%{$execNote})"
                : "{$label} 공격! -{$dmg} (기본 {$range}{$execNote})";
            if ($jackpot) {
                $msg = "🎰 대박타! ×{$jackpotMul}\n" . $msg;
            }
        } elseif ($type === 'finish') {
            $fm = (int)($calc['finish_mult'] ?? $calc['mult'] ?? 1);
            $msg = $crit
                ? "💥 필살 치명타! -{$dmg} (무기×{$fm} · +{$critBonus}%{$execNote})"
                : "필살! -{$dmg} (무기×{$fm}{$execNote})";
            $fMax = boss_raid_단소필살횟수($weapon, $enhance);
            $fUsedNow = (int)(boss_raid_단소필살구간_적용($nick, false)['used'] ?? 0);
            $msg .= "\n남은 " . max(0, $fMax - $fUsedNow) . "/{$fMax} · 1시간";
        } else {
            $msg = $crit
                ? "💥 {$label} 치명타! -{$dmg} (×{$calc['mult']} · +{$critBonus}%)"
                : "{$label} 공격! -{$dmg} (×{$calc['mult']})";
        }
        if (is_array($drops) && $drops !== []) {
            foreach ($drops as $d) {
                if (!empty($d['msg'])) {
                    $msg .= "\n" . $d['msg'];
                }
            }
        }
        if (is_array($weaponDurInfo) && !empty($weaponDurInfo['msg'])) {
            $msg .= "\n" . $weaponDurInfo['msg'];
        }
        if (is_array($miningDurInfo) && !empty($miningDurInfo['msg'])) {
            $msg .= "\n" . $miningDurInfo['msg'];
        }
        if (is_array($counter) && !empty($counter['msg'])) {
            $msg .= "\n" . $counter['msg'];
        }
        if (is_array($aoe) && !empty($aoe['msg'])) {
            $msg .= "\n" . $aoe['msg'];
        }
        if ($killed && is_array($kill_info)) {
            $msg .= "\n" . ($kill_info['msg'] ?? '보스 처치!');
            $msg .= "\n다음 출현: " . boss_raid_초시분초($respawnSec) . " 뒤";
        }

        $weaponFx = $type === 'mining'
            ? 'mining'
            : ($type === 'finish' ? 'finish' : boss_raid_무기fx키($weapon['item'] ?? ''));

        return [
            'ok' => true,
            'data' => $msg,
            'damage' => $dmg,
            'mult' => (int)$calc['mult'],
            'crit' => $crit,
            'attack_type' => $type,
            'weapon_fx' => $weaponFx,
            'execute' => !empty($calc['execute']),
            'execute_mult' => (int)($calc['execute_mult'] ?? 1),
            'finish_mult' => (int)($calc['finish_mult'] ?? 1),
            'counter' => $counter,
            'personal_counter' => $counter,
            'aoe' => $aoe,
            'drops' => $drops,
            'killed' => $killed,
            'kill' => $kill_info,
            'state' => boss_raid_상태_페이로드($nick),
        ];
    }
}

if (!function_exists('boss_raid_리셋')) {
    /** 민호 전용 — 대기 없이 즉시 새 보스 */
    function boss_raid_리셋($nick): array {
        if (!boss_raid_준호인가($nick)) {
            return ['ok' => false, 'data' => '리셋은 민호만 가능해요.'];
        }
        return boss_raid_강제소환($nick);
    }
}
