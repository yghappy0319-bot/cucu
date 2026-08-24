<?php
/**
 * 무기 강화 리뉴얼
 * - 최대 +100 (+0~+20 확률·비용 유지, +21~ 확장)
 * - 종류별 강화도(+1~+100) 1인 독점 · 점유 시 탈취(확률=해당단계, 비용=일반 강화비 50/50)
 * - 시전한도(1시간): 강화 × 2 (한도를 다 안 써도 1시간마다 초기화 · 한도 외 시전 없음)
 */

if (!defined('강화_최대레벨')) {
    define('강화_최대레벨', 100);
}
if (!defined('강화_독점시작')) {
    /** 이 강화도부터 무기 종류별 1명만 (+1~+100) */
    define('강화_독점시작', 1);
}
if (!defined('강화_탈취비용_1조')) {
    /** 하위호환(미사용) — 탈취비는 해당 단계 일반 강화비 */
    define('강화_탈취비용_1조', '1000000000000');
}
/** 홍보방(info2) — +10/+20… 구간 진입 알림 */
if (!defined('강화_구간진입_알림')) {
    define('강화_구간진입_알림', true);
}
/** 하위호환 별칭 */
if (!defined('강화_구간진입_본방알림')) {
    define('강화_구간진입_본방알림', 강화_구간진입_알림);
}
/** 홍보방(info2) — 강화 대성공(+1~3) 알림 */
if (!defined('강화_대성공_알림')) {
    define('강화_대성공_알림', true);
}
/** 홍보방(info2) — 탈취「자리 빼앗김」알림 */
if (!defined('강화_탈취자리_알림')) {
    define('강화_탈취자리_알림', true);
}
/** 하위호환 별칭 */
if (!defined('강화_탈취자리_본방알림')) {
    define('강화_탈취자리_본방알림', 강화_탈취자리_알림);
}

if (!function_exists('강화_최대')) {
    function 강화_최대(): int {
        return (int)강화_최대레벨;
    }
}

if (!function_exists('강화_대성공_확률퍼센트')) {
    /**
     * 일반 강화 성공 시 대성공 확률(%) — +0~+100 전 구간
     * - 기본 5%
     * - 목표 20 · 40 · 60 · 80 → 10%
     * 대성공 발동 시 +2 또는 +3만 (+1만 오르면 대성공 아님)
     * 예) +20 성공 → 일반 +21 / 대성공 +22 또는 +23
     */
    function 강화_대성공_확률퍼센트(int $현재강화): float {
        $현재강화 = max(0, $현재강화);
        $최대 = function_exists('강화_최대') ? 강화_최대() : 100;
        // 이미 최대면 대성공 없음 (+99→100 은 가능, +100 이상은 0)
        if ($현재강화 >= $최대) {
            return 0.0;
        }
        // +99 등은 +2/+3이 천장에 막혀 실질 +1만 될 수 있음 → 함수 쪽에서 대성공 취소
        $목표 = $현재강화 + 1;
        if (in_array($목표, [20, 40, 60, 80], true)) {
            return 10.0;
        }
        return 5.0;
    }
}

if (!function_exists('강화_성공다음강화')) {
    /**
     * 일반 강화 성공 후 도달 강화도
     * 대성공 발동 시: 현재+2 또는 현재+3 (목표+1 · 목표+2) — 목표(+1)만 오르면 대성공 아님
     * 미발동: 현재+1
     * @return array{enhance:int,gain:int,crit:bool,안내:string}
     */
    function 강화_성공다음강화(int $현재강화, $최대 = null): array {
        $현재강화 = max(0, $현재강화);
        $최대 = $최대 !== null ? (int)$최대 : 강화_최대();
        if ($현재강화 >= $최대) {
            return ['enhance' => $현재강화, 'gain' => 0, 'crit' => false, '안내' => ''];
        }
        $목표 = $현재강화 + 1;
        $pct = 강화_대성공_확률퍼센트($현재강화);
        $crit = false;
        $gain = 1;
        if ($pct > 0) {
            $threshold = (int)round($pct * 100); // 5% → 500 / 10000
            if ($threshold > 0 && mt_rand(1, 10000) <= $threshold) {
                // +2 / +3 만 — 예: +20 성공 시 대성공이면 +22 또는 +23
                $gain = mt_rand(2, 3);
                $crit = true;
            }
        }
        $다음 = min($최대, $현재강화 + $gain);
        $실제 = max(0, $다음 - $현재강화);
        // 천장에 막혀 +1만 올랐으면 대성공 취급 안 함
        if ($crit && $실제 < 2) {
            $crit = false;
            $gain = 1;
            $다음 = min($최대, $현재강화 + 1);
            $실제 = max(0, $다음 - $현재강화);
        }
        $안내 = '';
        if ($crit && $실제 >= 2) {
            $안내 = "\n✨ 대성공! +{$실제} 상승 (+{$다음})";
        }
        return [
            'enhance' => $다음,
            'gain' => $실제,
            'crit' => $crit,
            '안내' => $안내,
            '목표' => $목표,
        ];
    }
}

if (!function_exists('강화_독점좌석_상승선점')) {
    /**
     * 강화 상승(+1~N) 구간의 독점 좌석을 모두 비움
     * @return list<array{nick:string,from:int,to:int}>
     */
    function 강화_독점좌석_상승선점($무기아이템, int $이전강화, int $다음강화, $제외닉 = '', $탈취자닉 = ''): array {
        if (!function_exists('강화_독점좌석_선점')) {
            return [];
        }
        $이전강화 = max(0, $이전강화);
        $다음강화 = max($이전강화, $다음강화);
        $독점시작 = (int)강화_독점시작;
        $결과 = [];
        for ($lv = $이전강화 + 1; $lv <= $다음강화; $lv++) {
            if ($lv < $독점시작) {
                continue;
            }
            foreach (강화_독점좌석_선점($무기아이템, $lv, $제외닉, $탈취자닉) as $row) {
                $결과[] = $row;
            }
        }
        return $결과;
    }
}

if (!function_exists('강화_구간진입_여부')) {
    /** +10/+20/… 구간으로 처음 올라갈 때만 true (+0~9는 알림 없음) */
    function 강화_구간진입_여부(int $이전강화, int $다음강화): bool {
        if ($다음강화 < 10) {
            return false;
        }
        return (int)floor($다음강화 / 10) > (int)floor(max(0, $이전강화) / 10);
    }
}

if (!function_exists('강화_구간진입_라벨')) {
    /** 예: +22 → 20, +30 → 30 */
    function 강화_구간진입_라벨(int $다음강화): int {
        return (int)floor(max(0, $다음강화) / 10) * 10;
    }
}

if (!function_exists('강화_알림_등록')) {
    /** 강화 관련 알림 → 홍보방(tb_info2_alarm) */
    function 강화_알림_등록($msg, $item = 'system'): bool {
        $msg = trim((string)$msg);
        if ($msg === '') {
            return false;
        }
        if (function_exists('info2알림_등록')) {
            return (bool)info2알림_등록($msg, $item);
        }
        if (function_exists('info2알림_테이블_보장')) {
            info2알림_테이블_보장();
        } else {
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
        }
        $msg_esc = addslashes($msg);
        $item_esc = addslashes((string)$item);
        return (bool)@db_query("INSERT INTO tb_info2_alarm SET status = 0, msg = '{$msg_esc}', item = '{$item_esc}', regdate = NOW()");
    }
}

if (!function_exists('강화_구간진입_알림문구')) {
    /** @return string|null 알림이 필요하면 메시지, 아니면 null */
    function 강화_구간진입_알림문구($닉, $무기, int $이전강화, int $다음강화): ?string {
        if (!강화_구간진입_알림) {
            return null;
        }
        if (!강화_구간진입_여부($이전강화, $다음강화)) {
            return null;
        }
        $닉 = trim((string)$닉);
        $무기 = trim((string)$무기);
        $구간 = 강화_구간진입_라벨($다음강화);
        if ($닉 === '' || $무기 === '' || $구간 < 10) {
            return null;
        }
        return "⚔️ [ {$닉} ] {$무기} +{$구간}강 구간에 들어갔어요!\n(현재 +{$다음강화})";
    }
}

if (!function_exists('강화_구간진입_알림등록')) {
    /** 홍보방(tb_info2_alarm) 큐에 구간 진입 알림 적재 */
    function 강화_구간진입_알림등록($닉, $무기, $이전강화, $다음강화): bool {
        $msg = 강화_구간진입_알림문구($닉, $무기, (int)$이전강화, (int)$다음강화);
        if ($msg === null) {
            return false;
        }
        $item = trim((string)$닉);
        if ($item === '') {
            $item = 'system';
        }
        return 강화_알림_등록($msg, $item);
    }
}

if (!function_exists('강화_대성공_알림문구')) {
    /** @return string|null */
    function 강화_대성공_알림문구($닉, $무기, int $이전강화, int $다음강화, int $gain = 0): ?string {
        if (!강화_대성공_알림) {
            return null;
        }
        $닉 = trim((string)$닉);
        $무기 = trim((string)$무기);
        $이전강화 = max(0, $이전강화);
        $다음강화 = max($이전강화, $다음강화);
        $gain = $gain > 0 ? $gain : max(0, $다음강화 - $이전강화);
        if ($닉 === '' || $무기 === '' || $gain < 1 || $다음강화 <= $이전강화) {
            return null;
        }
        if ($gain >= 2) {
            return "✨ [ {$닉} ] {$무기} 대성공!\n+{$이전강화} → +{$다음강화} (+{$gain} 상승)";
        }
        return "✨ [ {$닉} ] {$무기} 대성공!\n+{$이전강화} → +{$다음강화}";
    }
}

if (!function_exists('강화_대성공_알림등록')) {
    /** 홍보방(tb_info2_alarm) 큐에 대성공 알림 적재 */
    function 강화_대성공_알림등록($닉, $무기, $이전강화, $다음강화, $gain = 0): bool {
        $msg = 강화_대성공_알림문구($닉, $무기, (int)$이전강화, (int)$다음강화, (int)$gain);
        if ($msg === null) {
            return false;
        }
        $item = trim((string)$닉);
        if ($item === '') {
            $item = 'system';
        }
        return 강화_알림_등록($msg, $item);
    }
}

if (!function_exists('강화_스키마_보장')) {
    /** enhance / 로그 컬럼을 +100 대응으로 확장 (프로세스당 1회) */
    function 강화_스키마_보장(): void {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;
        $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'enhance'");
        $type = strtolower((string)($col['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'tinyint') !== false) {
            @db_query("ALTER TABLE tb_member MODIFY COLUMN enhance SMALLINT UNSIGNED NOT NULL DEFAULT 0");
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_enhance_log'");
        if (!empty($tbl)) {
            foreach (['enhance_before', 'enhance_after'] as $c) {
                $lc = @db_select("SHOW COLUMNS FROM tb_enhance_log LIKE '{$c}'");
                $lt = strtolower((string)($lc['Type'] ?? ''));
                if ($lt !== '' && strpos($lt, 'tinyint') !== false) {
                    @db_query("ALTER TABLE tb_enhance_log MODIFY COLUMN `{$c}` SMALLINT UNSIGNED NOT NULL DEFAULT 0");
                }
            }
        }
        if (function_exists('무기_타입_스키마보장')) {
            무기_타입_스키마보장();
        }
    }
}

if (!function_exists('강화_성공분자')) {
    /** @param int $현재강화 시도 전 강화 (from) */
    function 강화_성공분자(int $현재강화): int {
        static $base = [
            0 => 990, 1 => 900, 2 => 800, 3 => 400, 4 => 600,
            5 => 500, 6 => 300, 7 => 200, 8 => 100, 9 => 50,
            10 => 30, 11 => 20, 12 => 10, 13 => 5, 14 => 1,
            15 => 5, 16 => 1, 17 => 5, 18 => 1, 19 => 1,
        ];
        if (isset($base[$현재강화])) {
            return (int)$base[$현재강화];
        }
        // +20→21 … +99→100: 분자 1 고정
        if ($현재강화 >= 20 && $현재강화 < 강화_최대()) {
            return 1;
        }
        return 0;
    }
}

if (!function_exists('강화_성공분모')) {
    function 강화_성공분모(int $현재강화): int {
        // +0~+19 기존 유지
        if ($현재강화 <= 14) {
            return 1000;
        }
        if ($현재강화 === 15 || $현재강화 === 16) {
            return 10000;
        }
        if ($현재강화 === 17 || $현재강화 === 18) {
            return 100000;
        }
        if ($현재강화 === 19) {
            return 1000000; // 0.0001%
        }
        // +20↑: 단계가 오를수록 분모 증가 (점점 어려움)
        if ($현재강화 >= 20 && $현재강화 < 강화_최대()) {
            $tier = $현재강화 - 19; // 20→1, 30→11, 99→80
            if ($현재강화 <= 24) {
                return 2000000 * $tier;           // 20:2M … 24:10M
            }
            if ($현재강화 <= 29) {
                return 10000000;                  // ~0.00001%
            }
            if ($현재강화 <= 39) {
                return 50000000;
            }
            if ($현재강화 <= 49) {
                return 100000000;
            }
            if ($현재강화 <= 59) {
                return 500000000;
            }
            if ($현재강화 <= 69) {
                return 1000000000;
            }
            if ($현재강화 <= 79) {
                return 5000000000;
            }
            if ($현재강화 <= 89) {
                return 10000000000;
            }
            return 50000000000; // 90~99
        }
        return 1000;
    }
}

if (!function_exists('강화_은총_의존로드')) {
    /** 은총 티어 정의(메가/테라) 공유 — 버프 타이머는 무기/채굴 분리 */
    function 강화_은총_의존로드(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach (['mining_config.inc.php', 'mining_storage.inc.php'] as $f) {
            $path = __DIR__ . '/game/' . $f;
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
}

if (!function_exists('강화_은총_tier_ensure_column')) {
    /** 무기 강화 전용 은총 티어 (채굴 mining_eunchong_tier 와 분리) */
    function 강화_은총_tier_ensure_column(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        $exists = @db_select("SHOW COLUMNS FROM tb_member LIKE 'eunchong_tier'");
        if (empty($exists)) {
            @db_query("
                ALTER TABLE tb_member
                ADD COLUMN `eunchong_tier` TINYINT UNSIGNED NOT NULL DEFAULT 1
                COMMENT '무기 활성 은총 등급 1=은총 2=메가 3=테라'
            ");
            // 기존 공유 버프 이관: 채굴 티어 → 무기 티어 (활성 은총만)
            if (defined('MINING_TABLE')) {
                $tbl = MINING_TABLE;
                @db_query("
                    UPDATE tb_member mem
                    INNER JOIN `{$tbl}` m ON m.nick = mem.name
                    SET mem.eunchong_tier = IFNULL(m.mining_eunchong_tier, 1)
                    WHERE mem.은총 IS NOT NULL
                      AND mem.은총 > NOW()
                      AND IFNULL(mem.eunchong_tier, 1) <= 1
                      AND IFNULL(m.mining_eunchong_tier, 1) >= 1
                ");
            }
        }
    }
}

if (!function_exists('강화_분모_제로제거')) {
    /** 분모에서 0을 $zeros개 제거 (= /10^zeros) */
    function 강화_분모_제로제거(int $분모, int $zeros): int {
        $분모 = max(1, $분모);
        $zeros = max(0, min(6, $zeros));
        for ($i = 0; $i < $zeros; $i++) {
            $분모 = (int)max(1, (int)floor($분모 / 10));
        }
        return $분모;
    }
}

if (!function_exists('강화_은총_효과')) {
    /**
     * 무기 강화 전용 은총 버프 (채굴과 타이머·티어 분리)
     * @return array{active:bool,zeros:int,cost_discount:bool,tier:int,label:string,cnt:int,end:string,left_sec:int}
     */
    function 강화_은총_효과($nick): array {
        $nick = trim((string)$nick);
        강화_은총_의존로드();
        강화_은총_tier_ensure_column();
        if (!function_exists('bag_은총_수량') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/item_bag_enhance.inc.php';
        }
        $active = false;
        $end = '';
        $cnt = 0;
        $tier = 1;
        if ($nick !== '' && function_exists('db_select')) {
            $esc = addslashes($nick);
            $row = @db_select("
                SELECT IFNULL(은총개수,0) AS cnt, 은총, IFNULL(eunchong_tier, 1) AS tier
                FROM tb_member
                WHERE name = '{$esc}'
                LIMIT 1
            ");
            $cnt = function_exists('bag_은총_수량')
                ? bag_은총_수량($nick)
                : (int)($row['cnt'] ?? 0);
            $end = trim((string)($row['은총'] ?? ''));
            $active = function_exists('강화_은총_활성')
                ? 강화_은총_활성($end)
                : ($end !== '' && strtotime($end) > time());
            $tier = max(1, min(3, (int)($row['tier'] ?? 1)));
        }
        if (!$active) {
            $tier = 1;
        }
        $zeros = 0;
        // 은총(tier1) 비용할인 의도값 — 실제 적용은 강화_은총_비용할인_활성 플래그
        $cost_discount = false;
        $label = '은총';
        if ($active) {
            if (function_exists('mining_eunchong_tier_def')) {
                $def = mining_eunchong_tier_def($tier);
                $zeros = (int)($def['zeros'] ?? 1);
                $cost_discount = !empty($def['cost_discount']);
                $label = (string)($def['label'] ?? '은총');
                $tier = (int)($def['tier'] ?? $tier);
            } else {
                $zeros = ($tier >= 2) ? 2 : 1;
                $cost_discount = ($tier === 1); // 예전: 은총만 50% 할인
                $label = ($tier >= 2) ? '메가은총' : '은총';
            }
        }
        // 숨김: 할인 로직·필드 유지, 플래그 OFF 시 미적용
        if (!defined('강화_은총_비용할인_활성') || !강화_은총_비용할인_활성) {
            $cost_discount = false;
        }
        return [
            'active' => $active,
            'zeros' => $zeros,
            'cost_discount' => $cost_discount,
            'tier' => $tier,
            'label' => $label,
            'cnt' => $cnt,
            'end' => $end,
            'left_sec' => ($active && $end !== '') ? max(0, strtotime($end) - time()) : 0,
        ];
    }
}

if (!function_exists('강화_은총_사용')) {
    /**
     * 무기/채팅 전용 은총·메가은총 사용 (채굴 버프와 분리)
     * @return array{ok:bool,data?:string,...}
     */
    function 강화_은총_사용($nick, int $tier = 1, int $현재강화 = 0, $무기 = ''): array {
        $nick = trim((string)$nick);
        $tier = max(1, min(3, $tier));
        if ($nick === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없어요.'];
        }
        강화_은총_의존로드();
        강화_은총_tier_ensure_column();

        if ($tier === 3) {
            return ['ok' => false, 'data' => '테라은총은 아직 사용할 수 없어요.'];
        }

        if (!function_exists('bag_은총_차감') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
            require_once __DIR__ . '/item_bag_enhance.inc.php';
        }
        $esc = addslashes($nick);
        $row = @db_select("SELECT idx, IFNULL(은총개수,0) AS cnt, 은총, item, enhance FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        if (empty($row['idx'])) {
            return ['ok' => false, 'data' => '회원 정보를 찾을 수 없어요.'];
        }
        $보유 = function_exists('bag_은총_수량')
            ? bag_은총_수량($nick)
            : (int)($row['cnt'] ?? 0);
        $무기 = trim((string)($무기 !== '' ? $무기 : ($row['item'] ?? '')));
        $현재강화 = $현재강화 > 0 ? $현재강화 : (int)($row['enhance'] ?? 0);
        if ($무기 === '' || $현재강화 < 14) {
            return ['ok' => false, 'data' => '은총은 +14강 이상 무기부터 사용 가능합니다.'];
        }

        if (function_exists('mining_eunchong_tier_for_use')) {
            $use = mining_eunchong_tier_for_use($보유, $tier);
            if ($use === null) {
                $need = (int)mining_eunchong_tier_def($tier)['cost'];
                $label = (string)mining_eunchong_tier_def($tier)['label'];
                if ($보유 < 1) {
                    return ['ok' => false, 'data' => '은총이 부족해요. (보유 0개)'];
                }
                return ['ok' => false, 'data' => "{$label} 사용에 은총이 부족해요. (필요 {$need}개 · 보유 {$보유}개)"];
            }
            $cost = max(1, (int)$use['cost']);
            $zeros = (int)$use['zeros'];
            $label = (string)$use['label'];
            $cost_discount = !empty($use['cost_discount']);
            $tier = (int)$use['tier'];
        } else {
            $cost = ($tier >= 2) ? 10 : 1;
            $zeros = ($tier >= 2) ? 2 : 1;
            $label = ($tier >= 2) ? '메가은총' : '은총';
            $cost_discount = ($tier === 1); // 예전: 은총만 50% 할인
            if ($보유 < $cost) {
                return ['ok' => false, 'data' => "{$label} 사용에 은총이 부족해요. (필요 {$cost}개 · 보유 {$보유}개)"];
            }
        }

        // 숨김: 할인 메시지·필드 유지, 플래그 OFF 시 미적용
        if (!defined('강화_은총_비용할인_활성') || !강화_은총_비용할인_활성) {
            $cost_discount = false;
        }

        $버프분 = ($tier >= 2) ? 10 : 5;
        if (function_exists('bag_은총_차감')) {
            $차감 = bag_은총_차감($nick, $cost);
            if (empty($차감['ok'])) {
                return ['ok' => false, 'data' => $label . ' 적용에 실패했어요. (은총 부족)'];
            }
            @db_query("
                UPDATE tb_member
                SET eunchong_tier = {$tier},
                    은총 = CASE
                        WHEN 은총 IS NOT NULL AND 은총 > NOW() THEN DATE_ADD(은총, INTERVAL {$버프분} MINUTE)
                        ELSE DATE_ADD(NOW(), INTERVAL {$버프분} MINUTE)
                    END
                WHERE name = '{$esc}'
                LIMIT 1
            ");
        } else {
            $rs = @db_query("
                UPDATE tb_member
                SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - {$cost}, 0),
                    eunchong_tier = {$tier},
                    은총 = CASE
                        WHEN 은총 IS NOT NULL AND 은총 > NOW() THEN DATE_ADD(은총, INTERVAL {$버프분} MINUTE)
                        ELSE DATE_ADD(NOW(), INTERVAL {$버프분} MINUTE)
                    END
                WHERE name = '{$esc}'
                  AND IFNULL(은총개수, 0) >= {$cost}
                LIMIT 1
            ");
            global $conn;
            $applied = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : (bool)$rs;
            if (!$applied) {
                return ['ok' => false, 'data' => $label . ' 적용에 실패했어요.'];
            }
        }

        $기본분모 = 강화_성공분모($현재강화);
        $버프분모 = 강화_분모_제로제거($기본분모, $zeros);
        $배수 = (int)max(1, (int)floor($기본분모 / max(1, $버프분모)));
        $효과 = 강화_은총_효과($nick);
        $남은 = (int)($효과['cnt'] ?? max(0, $보유 - $cost));
        $종료 = (string)($효과['end'] ?? '');
        $종료표시 = $종료 !== '' ? date('Y-m-d H:i:s', strtotime($종료)) : '-';
        $다음 = min(강화_최대(), $현재강화 + 1);

        $msg = "✨ {$label} 적용! (+{$현재강화}→+{$다음} 기준 · 무기 전용)\n";
        $msg .= "은총 {$cost}개 사용 · 강화확률 0 {$zeros}개 제거\n";
        $msg .= '성공분모 ' . number_format($기본분모) . ' → ' . number_format($버프분모) . " ({$배수}배 완화)\n";
        $msg .= "은총 종료시각: {$종료표시}\n";
        if ($cost_discount) {
            $msg .= "지금부터 버프: 강화확률 상승 + 강화비용 50% 할인 (남은 은총 {$남은}개)";
        } else {
            $msg .= "지금부터 버프: 강화확률 대폭 상승 · 강화비 할인 없음 (남은 은총 {$남은}개)";
        }

        return [
            'ok' => true,
            'data' => $msg,
            'label' => $label,
            'tier' => $tier,
            'zeros' => $zeros,
            'cost_discount' => $cost_discount ? 1 : 0,
            '은총개수' => $남은,
            '은총_end' => $종료,
            '은총_left_sec' => (int)($효과['left_sec'] ?? 0),
            '은총활성' => !empty($효과['active']) ? 1 : 0,
            'eunchong_label' => $label,
        ];
    }
}

if (!function_exists('강화_성공확률문구')) {
    /**
     * @param bool|int $은총_or_zeros true=제로1, int=제로 개수
     */
    function 강화_성공확률문구(int $현재강화, $은총_or_zeros = false): string {
        $분자 = 강화_성공분자($현재강화);
        $분모 = 강화_성공분모($현재강화);
        if ($분자 < 1 || $분모 < 1) {
            return '—';
        }
        if (is_bool($은총_or_zeros)) {
            $zeros = $은총_or_zeros ? 1 : 0;
        } else {
            $zeros = max(0, (int)$은총_or_zeros);
        }
        $분모 = 강화_분모_제로제거($분모, $zeros);
        if ($분자 > $분모) {
            $분자 = $분모;
        }
        return rtrim(rtrim(number_format(($분자 / $분모) * 100, 8, '.', ''), '0'), '.') . '%';
    }
}

if (!function_exists('강화비용_고단계_설계절대표')) {
    /**
     * +20→21 … +99→100 설계 절대액 (+19=100조 기준 ×1.5씩)
     * @return array<int,string>
     */
    function 강화비용_고단계_설계절대표(): array {
        static $cache = null;
        $max = function_exists('강화_최대') ? 강화_최대() : 100;
        if (is_array($cache) && isset($cache[$max - 1])) {
            return $cache;
        }
        $cache = [];
        $prev = '100000000000000'; // +19 설계(100조)
        for ($lv = 20; $lv < $max; $lv++) {
            if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
                $prev = bcdiv(bcmul($prev, '3', 0), '2', 0); // ×1.5
                if (bccomp($prev, '1', 0) < 0) {
                    $prev = '1';
                }
            } else {
                // bcmath 없을 때만 — float가 PHP_INT_MAX에서 멈추지 않도록 문자열 배수
                $prev = (string)max(1, (int)floor((float)$prev * 1.5));
            }
            $cache[$lv] = $prev;
        }
        return $cache;
    }
}

if (!function_exists('강화_단소item_SQL하위호환')) {
    /** 무기타입 미설정 시 단소 계열 item 매칭 (구간 표시명 포함) */
    function 강화_단소item_SQL하위호환(): string {
        return "("
            . "REPLACE(TRIM(COALESCE(item,'')), ' ', '') LIKE '%단소%'"
            . " OR item LIKE '%젓가락%'"
            . " OR item LIKE '%리코더%'"
            . " OR item LIKE '%피리%'"
            . " OR item LIKE '%대금%'"
            . " OR item LIKE '%플루트%'"
            . " OR item LIKE '%생황%'"
            . " OR item LIKE '%나발%'"
            . " OR item LIKE '%태평소%'"
            . " OR item LIKE '%엑스칼리버%'"
            . ")";
    }
}

if (!function_exists('강화_무기종류_SQL조건')) {
    /** tb_member 무기 종류 매칭 SQL (무기타입 우선, 없으면 item 하위호환) */
    function 강화_무기종류_SQL조건($무기아이템): string {
        if (function_exists('무기_타입값')) {
            $타입 = 무기_타입값($무기아이템);
            if ($타입 >= 1 && $타입 <= 3) {
                return "(IFNULL(무기타입,0) = {$타입} OR (IFNULL(무기타입,0) = 0 AND (" .
                    ($타입 === 무기_타입_마법
                        ? "TRIM(COALESCE(item,'')) IN ('🪄마법','🪄 마법')"
                        : ($타입 === 무기_타입_활
                            ? "TRIM(COALESCE(item,'')) IN ('🏹활','🏹 활')"
                            : 강화_단소item_SQL하위호환()
                        )
                    ) .
                ")))";
            }
        }
        $키 = function_exists('강화_무기종류키') ? 강화_무기종류키($무기아이템) : trim((string)$무기아이템);
        if ($키 === '') {
            return '0';
        }
        if ($키 === '🪄마법') {
            return "(IFNULL(무기타입,0) = 3 OR TRIM(COALESCE(item,'')) = '🪄마법' OR TRIM(COALESCE(item,'')) = '🪄 마법')";
        }
        if ($키 === '🪈단소' || $키 === '플루트단소' || (function_exists('무기_단소인가') && 무기_단소인가($키))) {
            return "(IFNULL(무기타입,0) = 2 OR " . 강화_단소item_SQL하위호환() . ")";
        }
        if ($키 === '🏹활') {
            return "(IFNULL(무기타입,0) = 1 OR TRIM(COALESCE(item,'')) IN ('🏹활','🏹 활'))";
        }
        $키_esc = addslashes($키);
        return "TRIM(COALESCE(item,'')) = '{$키_esc}'";
    }
}

if (!function_exists('강화_종류별레벨보유자목록')) {
    /**
     * 동일 무기 종류 · 특정 강화도 보유자 전원
     * @return list<array{name:string,item:string,enhance:int,강화성공시간?:string|null}>
     */
    function 강화_종류별레벨보유자목록($무기아이템, int $레벨, $제외닉 = ''): array {
        $레벨 = (int)$레벨;
        if ($레벨 < (int)강화_독점시작) {
            return [];
        }
        $무기조건 = 강화_무기종류_SQL조건($무기아이템);
        if ($무기조건 === '0') {
            return [];
        }
        $제외조건 = '';
        $제외닉 = trim((string)$제외닉);
        if ($제외닉 !== '') {
            $제외_esc = addslashes($제외닉);
            $제외조건 = " AND name != '{$제외_esc}'";
        }
        $rs = @db_query("
          SELECT name, item, enhance, 강화성공시간
          FROM tb_member
          WHERE {$무기조건} AND enhance = {$레벨}{$제외조건}
          ORDER BY IFNULL(강화성공시간, '1970-01-01 00:00:00') ASC, name ASC
        ");
        $목록 = [];
        if ($rs) {
            while ($row = db_fetch($rs)) {
                if (!empty($row['name'])) {
                    $목록[] = $row;
                }
            }
        }
        return $목록;
    }
}

if (!function_exists('강화_종류별레벨보유자')) {
    /**
     * 동일 무기 종류 · 특정 강화도 보유자 1명
     * @return array|null
     */
    function 강화_종류별레벨보유자($무기아이템, int $레벨, $제외닉 = '') {
        $목록 = 강화_종류별레벨보유자목록($무기아이템, $레벨, $제외닉);
        return $목록[0] ?? null;
    }
}

if (!function_exists('강화_독점좌석_선점')) {
    /**
     * 목표 강화 좌석의 기존 보유자를 전원 하향 (탈취·레이스·중복 정리 공통)
     * @return list<array{nick:string,from:int,to:int}>
     */
    function 강화_독점좌석_선점($무기아이템, int $목표강화, $제외닉 = '', $탈취자닉 = ''): array {
        $목표강화 = (int)$목표강화;
        if ($목표강화 < (int)강화_독점시작) {
            return [];
        }
        $결과 = [];
        $목록 = 강화_종류별레벨보유자목록($무기아이템, $목표강화, $제외닉);
        foreach ($목록 as $row) {
            $닉 = trim((string)($row['name'] ?? ''));
            if ($닉 === '') {
                continue;
            }
            $무기 = trim((string)($row['item'] ?? $무기아이템));
            $from = (int)($row['enhance'] ?? $목표강화);
            $to = 강화_탈취_보유자하향($닉, addslashes($닉), $무기 !== '' ? $무기 : $무기아이템, $from, $탈취자닉);
            $결과[] = ['nick' => $닉, 'from' => $from, 'to' => $to];
        }
        return $결과;
    }
}

if (!function_exists('강화_독점중복_정리')) {
    /**
     * 종류×강화 중복 보유를 정리. 최신 강화성공시간 1명만 유지, 기존(이전) 보유자는 하향.
     * @return array{fixed:int,details:list<string>}
     */
    function 강화_독점중복_정리(): array {
        if (!function_exists('db_query') || !function_exists('db_select')) {
            return ['fixed' => 0, 'details' => []];
        }
        $lock = @db_select("SELECT GET_LOCK('enhance_exclusive_dup_fix', 0) AS ok");
        if (empty($lock['ok'])) {
            return ['fixed' => 0, 'details' => []];
        }
        $fixed = 0;
        $details = [];
        try {
            $독점시작 = (int)강화_독점시작;
            $rs = @db_query("
              SELECT
                CASE
                  WHEN TRIM(COALESCE(item,'')) IN ('🪄마법', '🪄 마법') THEN '🪄마법'
                  ELSE TRIM(COALESCE(item,''))
                END AS wkey,
                CAST(IFNULL(enhance, 0) AS UNSIGNED) AS lv,
                COUNT(*) AS cnt
              FROM tb_member
              WHERE TRIM(COALESCE(item, '')) != ''
                AND CAST(IFNULL(enhance, 0) AS UNSIGNED) >= {$독점시작}
              GROUP BY wkey, lv
              HAVING cnt > 1
              ORDER BY lv DESC, wkey ASC
            ");
            $그룹 = [];
            if ($rs) {
                while ($row = db_fetch($rs)) {
                    $그룹[] = $row;
                }
            }
            foreach ($그룹 as $g) {
                $wkey = trim((string)($g['wkey'] ?? ''));
                $lv = (int)($g['lv'] ?? 0);
                if ($wkey === '' || $lv < $독점시작) {
                    continue;
                }
                $무기조건 = 강화_무기종류_SQL조건($wkey);
                $holders = @db_query("
                  SELECT name, item, enhance, 강화성공시간
                  FROM tb_member
                  WHERE {$무기조건} AND CAST(IFNULL(enhance, 0) AS UNSIGNED) = {$lv}
                  ORDER BY IFNULL(강화성공시간, '1970-01-01 00:00:00') DESC, name ASC
                ");
                if (!$holders) {
                    continue;
                }
                $keep = true;
                while ($row = db_fetch($holders)) {
                    $닉 = trim((string)($row['name'] ?? ''));
                    if ($닉 === '') {
                        continue;
                    }
                    if ($keep) {
                        $keep = false; // 최신 1명 유지
                        continue;
                    }
                    $무기 = trim((string)($row['item'] ?? $wkey));
                    $from = (int)($row['enhance'] ?? $lv);
                    $to = 강화_탈취_보유자하향($닉, addslashes($닉), $무기 !== '' ? $무기 : $wkey, $from, '');
                    $details[] = "· {$닉} {$무기} +{$from} → +{$to}";
                    $fixed++;
                }
            }
            if ($fixed > 0) {
                $msg = "🔧 강화 독점 좌석 중복 정리\n종류별 동일 강화는 1명만 유지해요.\n(기존/이전 달성자 하향)\n\n" . implode("\n", $details);
                if (function_exists('강화_알림_등록')) {
                    강화_알림_등록($msg, 'system');
                } elseif (function_exists('info2알림_등록')) {
                    info2알림_등록($msg, 'system');
                } else {
                    $msg_esc = addslashes($msg);
                    @db_query("INSERT INTO tb_info2_alarm SET status = 0, msg = '{$msg_esc}', item = 'system', regdate = NOW()");
                }
            }
            return ['fixed' => $fixed, 'details' => $details];
        } finally {
            @db_query("SELECT RELEASE_LOCK('enhance_exclusive_dup_fix')");
        }
    }
}

if (!function_exists('강화_종류최고보유자인가')) {
    /** 해당 무기 종류에서 본인 강화가 방 내 최고인가 (단소 구간 표시명 포함) */
    function 강화_종류최고보유자인가($무기아이템, $닉, $본인강화 = null): bool {
        $닉 = trim((string)$닉);
        if ($닉 === '') {
            return false;
        }
        $무기조건 = 강화_무기종류_SQL조건($무기아이템);
        if ($무기조건 === '' || $무기조건 === '0') {
            return false;
        }
        $row = @db_select("SELECT MAX(enhance) AS mx FROM tb_member WHERE {$무기조건}");
        $max = (int)($row['mx'] ?? 0);
        if ($max < 1) {
            return false;
        }
        if ($본인강화 === null) {
            $nick_esc = addslashes($닉);
            $me = @db_select("SELECT enhance FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
            $본인강화 = (int)($me['enhance'] ?? 0);
        }
        return (int)$본인강화 >= $max && (int)$본인강화 > 0;
    }
}

if (!function_exists('강화_도전모드_정보')) {
    /**
     * 다음 강화도가 종류별 독점 좌석에 점유된 경우 탈취 도전
     * 비용 = 해당 단계 일반 강화비 · 50% 보유자 / 50% 소멸 · 확률 = 해당 단계 일반 강화 확률
     * @return array|null
     */
    function 강화_도전모드_정보($현재무기, $현재강화, $도전자닉 = '') {
        $현재강화 = (int)$현재강화;
        $목표 = $현재강화 + 1;
        if ($목표 < (int)강화_독점시작 || $목표 > 강화_최대()) {
            return null;
        }
        $보유자 = 강화_종류별레벨보유자($현재무기, $목표, $도전자닉);
        if (!is_array($보유자) || empty($보유자['name'])) {
            return null;
        }
        // 탈취비 = 그 단계를 비어 있을 때 올리는 일반 강화비와 동일 (본냥 전체미션 할인 포함)
        if (function_exists('강화비용_산출')) {
            $비용 = (string)강화비용_산출($현재강화, null, false, false, false, true);
        } elseif (function_exists('강화비용_기본금액')) {
            $비용 = (string)강화비용_기본금액($현재강화);
            if (!function_exists('gv_본냥미션_비용할인')) {
                $missionInc = __DIR__ . '/game/vault_bon_mission.inc.php';
                if (!is_file($missionInc)) {
                    $missionInc = dirname(__DIR__) . '/api/game/vault_bon_mission.inc.php';
                }
                if (is_file($missionInc)) {
                    include_once $missionInc;
                }
            }
            if (function_exists('gv_본냥미션_비용할인')) {
                $비용 = (string)gv_본냥미션_비용할인($비용);
            }
        } else {
            $비용 = (string)강화_탈취비용_1조;
        }
        if ($비용 === '' || $비용 === '0') {
            $비용 = '1';
        }
        $절반 = function_exists('강화비용_반액') ? 강화비용_반액($비용) : (string)max(0, (int)floor(((float)$비용) / 2));
        $분자 = 강화_성공분자($현재강화);
        $분모 = max(1, 강화_성공분모($현재강화));
        return [
            '보유자닉'   => trim((string)$보유자['name']),
            '무기'       => trim((string)($보유자['item'] ?? $현재무기)),
            '목표강화'   => $목표,
            '현재강화'   => $현재강화,
            '비용'       => $비용,
            '보유자보상' => $절반,
            '소멸'       => $절반,
            '분자'       => $분자,
            '분모'       => $분모,
            '분모_은총'  => (int)max(1, (int)floor($분모 / 10)),
        ];
    }
}

if (!function_exists('강화_도전모드_성공분모')) {
    /** @param bool|int $은총_or_zeros */
    function 강화_도전모드_성공분모($은총_or_zeros, array $도전모드): int {
        $분모 = max(1, (int)($도전모드['분모'] ?? 1));
        if (is_bool($은총_or_zeros)) {
            $zeros = $은총_or_zeros ? 1 : 0;
        } else {
            $zeros = max(0, (int)$은총_or_zeros);
        }
        return 강화_분모_제로제거($분모, $zeros);
    }
}

if (!function_exists('강화_도전모드_성공확률문구')) {
    /** @param bool|int $은총_or_zeros */
    function 강화_도전모드_성공확률문구($은총_or_zeros, array $도전모드): string {
        $분자 = max(1, (int)($도전모드['분자'] ?? 1));
        $분모 = 강화_도전모드_성공분모($은총_or_zeros, $도전모드);
        if ($분자 > $분모) {
            $분자 = $분모;
        }
        return rtrim(rtrim(number_format(($분자 / $분모) * 100, 8, '.', ''), '0'), '.') . '%';
    }
}

if (!function_exists('강화_탈취_보유자하향')) {
    /**
     * 탈취 성공 시 보유자 강화 -1 (하위 독점 좌석이 차 있으면 연쇄 하향)
     * 도전자가 아직 하위 강화에 있더라도 그 좌석은 비는 것으로 본다(곧 +목표로 이동).
     * @param string $탈취자닉 자리를 뺏은 사람 (알림용·좌석 제외)
     */
    function 강화_탈취_보유자하향($보유자닉, $보유자_esc, $무기아이템, int $보유강화, $탈취자닉 = ''): int {
        $보유자닉 = trim((string)$보유자닉);
        $탈취자닉 = trim((string)$탈취자닉);
        $보유강화 = max((int)강화_독점시작, $보유강화);
        // 이미 해당 좌석에서 내려간 뒤 재호출되면(배치 상승선점+탈취하향 중복 등) 알림·하향 생략
        $curRow = @db_select("SELECT CAST(IFNULL(enhance, 0) AS UNSIGNED) AS enhance FROM tb_member WHERE name = '{$보유자_esc}' LIMIT 1");
        $현재강화 = (int)($curRow['enhance'] ?? 0);
        if ($현재강화 < $보유강화) {
            return $현재강화;
        }
        if ($현재강화 > $보유강화) {
            $보유강화 = $현재강화;
        }
        $to = $보유강화 - 1;
        while ($to >= (int)강화_독점시작) {
            $occ = 강화_종류별레벨보유자($무기아이템, $to, $보유자닉);
            if (!is_array($occ) || empty($occ['name'])) {
                break;
            }
            // 도전자가 아직 +to 에 있으면 곧 비는 자리 — 연쇄 하향 대상에서 제외
            if ($탈취자닉 !== '' && trim((string)$occ['name']) === $탈취자닉) {
                break;
            }
            $to--;
        }
        $to = max(0, $to);
        db_query("UPDATE tb_member SET enhance = {$to} WHERE name = '{$보유자_esc}' LIMIT 1");

        if (강화_탈취자리_알림) {
            $무기아이템 = trim((string)$무기아이템);
            if ($보유자닉 !== '' && $무기아이템 !== '' && $탈취자닉 !== '') {
                $조사 = '가';
                $끝 = mb_substr($탈취자닉, -1, 1, 'UTF-8');
                if ($끝 !== '' && function_exists('mb_convert_encoding')) {
                    $ucs = @unpack('N', mb_convert_encoding($끝, 'UCS-4BE', 'UTF-8'));
                    $code = (int)($ucs[1] ?? 0);
                    if ($code >= 0xAC00 && $code <= 0xD7A3 && (($code - 0xAC00) % 28) !== 0) {
                        $조사 = '이';
                    }
                }
                $로그 = "{$탈취자닉}{$조사} {$보유자닉}의 {$무기아이템} {$보유강화}강을 탈취하였습니다";
                강화_알림_등록($로그, $보유자닉);
            }
        }
        return $to;
    }
}

if (!function_exists('강화_탈취_성공시_도전자강화')) {
    /**
     * 탈취 주사위 성공 → 목표 강화 획득 (+ 일반과 동일 대성공 판정)
     * 대성공 시 현재+2 또는 +3 (최소 목표 강화 보장)
     * @return array{enhance:int,탈취성공:bool,crit:bool,gain:int,안내:string}
     */
    function 강화_탈취_성공시_도전자강화(int $목표강화, ?int $현재강화 = null, $최대 = null): array {
        $목표강화 = max(1, (int)$목표강화);
        $현재강화 = $현재강화 !== null ? max(0, (int)$현재강화) : max(0, $목표강화 - 1);
        if (function_exists('강화_성공다음강화')) {
            $상승 = 강화_성공다음강화($현재강화, $최대);
            $다음 = (int)($상승['enhance'] ?? $목표강화);
            // 탈취 최소: 도전한 목표 강화 이상
            if ($다음 < $목표강화) {
                $다음 = $목표강화;
                $상승['crit'] = false;
                $상승['안내'] = '';
            }
            $gain = max(0, $다음 - $현재강화);
            $crit = !empty($상승['crit']) && $gain >= 2;
            $안내 = $crit ? (string)($상승['안내'] ?? '') : '';
            if ($crit && $안내 === '') {
                $안내 = "\n✨ 대성공! +{$gain} 상승 (+{$다음})";
            }
            return [
                'enhance'  => $다음,
                '탈취성공' => true,
                'crit'     => $crit,
                'gain'     => $gain,
                '안내'     => $안내,
            ];
        }
        return [
            'enhance'  => $목표강화,
            '탈취성공' => true,
            'crit'     => false,
            'gain'     => max(0, $목표강화 - $현재강화),
            '안내'     => '',
        ];
    }
}

if (!function_exists('강화_종류별빈좌석')) {
    /** 종류별 독점 빈 자리. $시작레벨 이상 중 가장 낮은 빈 강화도 */
    function 강화_종류별빈좌석($무기아이템, int $시작레벨, $제외닉 = ''): int {
        $시작레벨 = max((int)강화_독점시작, $시작레벨);
        $max = 강화_최대();
        for ($lv = $시작레벨; $lv <= $max; $lv++) {
            $occ = 강화_종류별레벨보유자($무기아이템, $lv, $제외닉);
            if (!is_array($occ) || empty($occ['name'])) {
                return $lv;
            }
        }
        return $시작레벨;
    }
}

if (!function_exists('무기랭킹_문구')) {
    /**
     * `.무기랭킹` / `.무기랭킹 단소|활|마법` 응답 문구
     * @return array{ok:bool,msg:string}
     */
    function 무기랭킹_문구($필터입력 = ''): array {
        $필터입력 = trim((string)$필터입력);
        $필터키 = '';
        if ($필터입력 !== '') {
            if (mb_strpos($필터입력, '단소') !== false) {
                $필터키 = '단소';
            } elseif (mb_strpos($필터입력, '마법') !== false) {
                $필터키 = '마법';
            } elseif (mb_strpos($필터입력, '활') !== false) {
                $필터키 = '활';
            } else {
                return [
                    'ok' => false,
                    'msg' => "❌ 사용법: `.무기랭킹` · `.무기랭킹 단소` · `.무기랭킹 활` · `.무기랭킹 마법`",
                ];
            }
        }

        if (function_exists('무기_타입_스키마보장')) {
            무기_타입_스키마보장();
        }
        $where = "TRIM(COALESCE(item,'')) != ''";
        $제목 = '⚔️ 무기 랭킹 (전체 · 강화 높은 순)';
        if ($필터키 !== '') {
            $타입 = function_exists('무기_타입_키에서') ? 무기_타입_키에서($필터키) : 0;
            if ($타입 >= 1 && $타입 <= 3) {
                $where .= " AND IFNULL(무기타입,0) = {$타입}";
            } else {
                $키_esc = addslashes($필터키);
                $where .= " AND REPLACE(TRIM(COALESCE(item,'')), ' ', '') LIKE '%{$키_esc}'";
            }
            $제목 = "⚔️ 무기 랭킹 ({$필터키} · 강화 높은 순)";
        }

        $result = @db_query("
          SELECT title, style, name, item, enhance, IFNULL(무기타입,0) AS 무기타입
          FROM tb_member
          WHERE {$where}
          ORDER BY CAST(COALESCE(enhance,0) AS UNSIGNED) DESC, 강화성공시간 ASC
        ");
        $msg = $제목 . "\n";
        $rank = 1;
        if ($result) {
            while ($row = db_fetch($result)) {
                $타이틀 = trim((string)($row['title'] ?? ''));
                $스타일 = trim((string)($row['style'] ?? ''));
                $무기 = trim((string)($row['item'] ?? ''));
                $강화 = (int)($row['enhance'] ?? 0);
                if (function_exists('무기_타입값') && function_exists('무기_표시아이템')) {
                    $t = 무기_타입값($row);
                    if ($t >= 1 && $t <= 3) {
                        $무기 = 무기_표시아이템($t, $강화);
                    }
                }
                $prefix = $타이틀 !== '' ? "{$타이틀} " : '';
                $msg .= "{$rank}등 {$prefix}{$row['name']} +{$강화} {$스타일} {$무기}\n";
                $rank++;
            }
        }
        if ($rank === 1) {
            $msg .= $필터키 !== '' ? "({$필터키} 보유 회원 없음)" : '(보유 무기 있는 회원 없음)';
        }
        return ['ok' => true, 'msg' => $msg];
    }
}
