<?php
/**
 * 개인금고(은괴·금괴·백금괴) 공통 — 커뮤니티 tax/.금고 털이와 별개
 * 신규 예치: 1은괴 = 1해 · 1금괴 = 10해 · 1백금괴 = 100해 (동일 이율 1%/일)
 * 기존 예치: amount 그대로 유지 (구 단가 100경 / 1천경 / 1해 · 이자도 원금 비율로 동일)
 * 은/금/백금 기간: 7/14일 (신규) · 기존 30일 예치는 만기까지 유지
 * 만기: 원금 + 7일5% / 14일10% · (구 30일 10% · 30일 연장 시 15%)
 * 전 회원 예치풀 상한: GOLD_VAULT_POOL_MAX (은/금/백금 status=0 합산)
 * 중도해지: 7일→4일 / 14일→7일 잠금 · (구 30일→14일)
 * 잠금 중 강제해지 환급: 맡긴 날 50% → 다음날 45% → 40% → 35% → 30%(이후 고정) · 잠금 후 적립이자×3 수수료
 * 은괴 수령보너스: 미수령 이자의 20% · 최대 20개분 (마켓할인 없음 대체)
 */

/** 신규 금괴 1개 = 10해 */
if (!defined('GOLD_BAR_AMOUNT')) {
    define('GOLD_BAR_AMOUNT', '1000000000000000000000'); // 10해 = 10^21
}
/** 신규 은괴 1개 = 1해 */
if (!defined('GOLD_SILVER_AMOUNT')) {
    define('GOLD_SILVER_AMOUNT', '100000000000000000000'); // 1해 = 10^20
}
/** 신규 백금괴 1개 = 100해 */
if (!defined('GOLD_PLAT_AMOUNT')) {
    define('GOLD_PLAT_AMOUNT', '10000000000000000000000'); // 100해 = 10^22
}
/** 금괴 1개 · 하루 이자 1천경 (원금 1% · 은/백금도 동일 이율) */
if (!defined('GOLD_BAR_DAILY_INTEREST')) {
    define('GOLD_BAR_DAILY_INTEREST', '10000000000000000000'); // 1천경 = 10^19
}
/** 은괴 1개 · 하루 이자 100경 */
if (!defined('GOLD_SILVER_DAILY_INTEREST')) {
    define('GOLD_SILVER_DAILY_INTEREST', '1000000000000000000'); // 100경 = 10^18
}
/** 구 단가(기존 예치 유지·BIGINT복구·종류추정용) — 변경 금지 */
if (!defined('GOLD_LEGACY_SILVER_AMOUNT')) {
    define('GOLD_LEGACY_SILVER_AMOUNT', '1000000000000000000'); // 100경
}
if (!defined('GOLD_LEGACY_GOLD_AMOUNT')) {
    define('GOLD_LEGACY_GOLD_AMOUNT', '10000000000000000000'); // 1천경
}
if (!defined('GOLD_LEGACY_PLAT_AMOUNT')) {
    define('GOLD_LEGACY_PLAT_AMOUNT', '100000000000000000000'); // 1해
}
if (!defined('GOLD_LEGACY_SILVER_DAILY')) {
    define('GOLD_LEGACY_SILVER_DAILY', '10000000000000000'); // 1경
}
if (!defined('GOLD_LEGACY_GOLD_DAILY')) {
    define('GOLD_LEGACY_GOLD_DAILY', '100000000000000000'); // 10경
}
if (!defined('GOLD_LEGACY_PLAT_DAILY')) {
    define('GOLD_LEGACY_PLAT_DAILY', '1000000000000000000'); // 100경
}
/** 은괴 전용 · 이자 수령 시 미수령 이자의 보너스 % (마켓할인 대체) */
if (!defined('GOLD_SILVER_CLAIM_BONUS_PCT')) {
    define('GOLD_SILVER_CLAIM_BONUS_PCT', 20);
}
/** 수령보너스 적용 은괴 개수 상한 (초과분은 이자만, 보너스 없음) */
if (!defined('GOLD_SILVER_CLAIM_BONUS_MAX_BARS')) {
    define('GOLD_SILVER_CLAIM_BONUS_MAX_BARS', 20);
}
/** 중도해지 수수료 = 받은 이자 × 배수 (잠금 해제 후) */
if (!defined('GOLD_BAR_EARLY_FEE_MULT')) {
    define('GOLD_BAR_EARLY_FEE_MULT', 3);
}
/** 잠금 기간 중 강제 중도해지 · 맡긴 당일 원금 환급 비율(%) — 나머지는 소멸 */
if (!defined('GOLD_BAR_FORCE_EARLY_KEEP_PCT')) {
    define('GOLD_BAR_FORCE_EARLY_KEEP_PCT', 50);
}
/** 강제해지 환급 하한(%) — 일자 경과해도 이 비율 미만으로 내려가지 않음 */
if (!defined('GOLD_BAR_FORCE_EARLY_KEEP_FLOOR_PCT')) {
    define('GOLD_BAR_FORCE_EARLY_KEEP_FLOOR_PCT', 30);
}
/** 강제해지 환급 · 하루마다 감소하는 %p */
if (!defined('GOLD_BAR_FORCE_EARLY_KEEP_STEP_PCT')) {
    define('GOLD_BAR_FORCE_EARLY_KEEP_STEP_PCT', 5);
}
/** @deprecated 기간별 잠금은 gv_중도잠금일수() 사용 (폴백 기본값) */
if (!defined('GOLD_BAR_EARLY_LOCK_DAYS')) {
    define('GOLD_BAR_EARLY_LOCK_DAYS', 4);
}
/** @deprecated 강제해지 환급%는 gv_강제중도환급비율() */
if (!defined('GOLD_BAR_EARLY_PCT')) {
    define('GOLD_BAR_EARLY_PCT', GOLD_BAR_FORCE_EARLY_KEEP_PCT);
}
if (!defined('GOLD_BAR_COOKIE')) {
    define('GOLD_BAR_COOKIE', 'wallet_code');
    define('GOLD_BAR_COOKIE_TTL', 7 * 86400);
}
if (!defined('GOLD_VAULT_ADMIN_NICK')) {
    define('GOLD_VAULT_ADMIN_NICK', '민호');
}
/** 전 회원 예치중(status=0) 합산 상한 — 은/금/백금 모두 포함 */
if (!defined('GOLD_VAULT_POOL_MAX')) {
    define('GOLD_VAULT_POOL_MAX', 6500);
}
/** 보유 게임냥 전액 맡기기 — 자유예치 기본 표시용(잠금 아님) */
if (!defined('GOLD_VAULT_CASH_TERM_DAYS')) {
    define('GOLD_VAULT_CASH_TERM_DAYS', 7);
}
/** 자유예치: 만 24시간마다 1% (24시간 미만 0) · 7일잠금: 맡긴 즉시 1일분 포함 */
if (!defined('GOLD_VAULT_CASH_DAILY_PCT')) {
    define('GOLD_VAULT_CASH_DAILY_PCT', 1);
}
/** 7일 잠금형 기간 */
if (!defined('GOLD_VAULT_LOCK7_DAYS')) {
    define('GOLD_VAULT_LOCK7_DAYS', 7);
}
/** 7일잠금 · 중도해지 잠금 일수 (이후에도 위약금% 차감 스케줄 적용) */
if (!defined('GOLD_VAULT_LOCK7_EARLY_LOCK_DAYS')) {
    define('GOLD_VAULT_LOCK7_EARLY_LOCK_DAYS', 2);
}
/** 예치 금괴 전액 회수(수수료 없음) 허용 닉 */
if (!defined('GOLD_VAULT_RECALL_NICKS')) {
    define('GOLD_VAULT_RECALL_NICKS', '다오,피치,민호');
}

// 본냥 전체미션 (강화비 할인)
$__gv_bon_mission = __DIR__ . '/../api/game/vault_bon_mission.inc.php';
if (!is_file($__gv_bon_mission)) {
    $__gv_bon_mission = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/api/game/vault_bon_mission.inc.php';
}
if (is_file($__gv_bon_mission)) {
    include_once $__gv_bon_mission;
}
unset($__gv_bon_mission);

// 겜냥 전체미션 (마켓 할인)
$__gv_gem_mission = __DIR__ . '/../api/game/vault_gem_mission.inc.php';
if (!is_file($__gv_gem_mission)) {
    $__gv_gem_mission = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/api/game/vault_gem_mission.inc.php';
}
if (is_file($__gv_gem_mission)) {
    include_once $__gv_gem_mission;
}
unset($__gv_gem_mission);

/** 예치 통화: point=게임냥 · newpoint=본방냥 */
if (!defined('GOLD_VAULT_CURRENCY_POINT')) {
    define('GOLD_VAULT_CURRENCY_POINT', 'point');
}
if (!defined('GOLD_VAULT_CURRENCY_NEWPOINT')) {
    define('GOLD_VAULT_CURRENCY_NEWPOINT', 'newpoint');
}

function gv_준호인가($nick) {
    return trim((string)$nick) === GOLD_VAULT_ADMIN_NICK;
}

/** @return 'point'|'newpoint' */
function gv_통화정상화($currency): string {
    $c = strtolower(trim((string)$currency));
    if (in_array($c, ['newpoint', '본냥', '본방냥', 'np', 'bon', 'bonyang'], true)) {
        return GOLD_VAULT_CURRENCY_NEWPOINT;
    }
    return GOLD_VAULT_CURRENCY_POINT;
}

/** tb_member 컬럼명 */
function gv_통화컬럼($currency): string {
    return gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT ? 'newpoint' : 'point';
}

function gv_통화라벨($currency): string {
    return gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT ? '본방냥' : '게임냥';
}

function gv_통화단축($currency): string {
    return gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT ? '본냥' : '겜냥';
}

if (!function_exists('gv_예치순위_상위')) {
    /**
     * 개인금고 예치액 상위 N명 (자유예치·7일잠금 · status=0)
     * share_pct = 해당 통화 전체 예치합 대비 비중
     * @return list<array{rank:int,nick:string,amount:string,amount_disp:string,share_pct:float,share_disp:string}>
     */
    function gv_예치순위_상위($currency = 'point', int $limit = 3): array {
        $currency = gv_통화정상화($currency);
        $limit = max(1, min(10, $limit));
        $bust = (int)($GLOBALS['_gv_gem_mission_bust'] ?? 0)
            + (int)($GLOBALS['_gv_bon_mission_bust'] ?? 0);
        static $cache = [];
        $key = $currency . ':' . $limit . ':' . $bust;
        $now = time();
        if (isset($cache[$key]) && ($now - (int)$cache[$key]['at']) < 5) {
            return $cache[$key]['rows'];
        }

        $cur_esc = addslashes($currency);
        $kindSql = "
              AND IFNULL(bar_kind, '') IN (
                'cash', 'vault', '전액', '전액예치', '자유',
                'lock7', 'lock', '잠금', '7일잠금'
              )";
        $tot행 = @db_select("
            SELECT CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(amount,0) AS DECIMAL(65,0))), 0) AS CHAR)) AS s
            FROM tb_gold_bar
            WHERE status = 0
              AND IFNULL(currency, 'point') = '{$cur_esc}'
              {$kindSql}
        ");
        $totRaw = (string)($tot행['s'] ?? 'N0');
        if (function_exists('냥_금액원문_정규화')) {
            $totRaw = 냥_금액원문_정규화($totRaw);
        } elseif (isset($totRaw[0]) && ($totRaw[0] === 'N' || $totRaw[0] === 'n')) {
            $totRaw = substr($totRaw, 1);
        }
        $total = function_exists('냥_정수문자열')
            ? 냥_정수문자열($totRaw)
            : (ltrim(preg_replace('/\D/', '', $totRaw), '0') ?: '0');

        $비중표시 = function (string $amt) use ($total): string {
            if ($amt === '0' || $total === '0') {
                return '0%';
            }
            if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
                if (bccomp($total, '0', 0) <= 0) {
                    return '0%';
                }
                $pct = bcdiv(bcmul($amt, '100', 4), $total, 2);
                if (bccomp($pct, '0', 2) === 0) {
                    return '<0.01%';
                }
                $s = rtrim(rtrim($pct, '0'), '.');
                return ($s === '' ? '0' : $s) . '%';
            }
            $p = ((float)$amt / (float)$total) * 100;
            if ($p > 0 && $p < 0.01) {
                return '<0.01%';
            }
            $s = rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.');
            return ($s === '' ? '0' : $s) . '%';
        };
        $비중숫자 = function (string $amt) use ($total): float {
            if ($amt === '0' || $total === '0') {
                return 0.0;
            }
            if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
                if (bccomp($total, '0', 0) <= 0) {
                    return 0.0;
                }
                return (float)bcdiv(bcmul($amt, '100', 4), $total, 2);
            }
            return round(((float)$amt / (float)$total) * 100, 2);
        };

        $rs = @db_query("
            SELECT nick,
                   CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(amount,0) AS DECIMAL(65,0))), 0) AS CHAR)) AS amt
            FROM tb_gold_bar
            WHERE status = 0
              AND IFNULL(currency, 'point') = '{$cur_esc}'
              {$kindSql}
              AND TRIM(IFNULL(nick, '')) <> ''
            GROUP BY nick
            HAVING CAST(SUM(CAST(IFNULL(amount,0) AS DECIMAL(65,0))) AS DECIMAL(65,0)) > 0
            ORDER BY CAST(SUM(CAST(IFNULL(amount,0) AS DECIMAL(65,0))) AS DECIMAL(65,0)) DESC
            LIMIT {$limit}
        ");
        $rows = [];
        $rank = 0;
        if ($rs) {
            while ($r = db_fetch($rs)) {
                $nick = trim((string)($r['nick'] ?? ''));
                if ($nick === '') {
                    continue;
                }
                $raw = (string)($r['amt'] ?? 'N0');
                if (function_exists('냥_금액원문_정규화')) {
                    $raw = 냥_금액원문_정규화($raw);
                } elseif (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
                    $raw = substr($raw, 1);
                }
                if (function_exists('냥_정수문자열')) {
                    $amt = 냥_정수문자열($raw);
                } else {
                    $amt = ltrim(preg_replace('/\D/', '', (string)$raw), '0') ?: '0';
                }
                if ($amt === '0') {
                    continue;
                }
                $rank++;
                $rows[] = [
                    'rank' => $rank,
                    'nick' => $nick,
                    'amount' => $amt,
                    'amount_disp' => gv_금액표시($amt, $currency),
                    'share_pct' => $비중숫자($amt),
                    'share_disp' => $비중표시($amt),
                ];
            }
        }
        $cache[$key] = ['at' => $now, 'rows' => $rows];
        return $rows;
    }
}

function gv_행통화(array $bar): string {
    return gv_통화정상화($bar['currency'] ?? GOLD_VAULT_CURRENCY_POINT);
}

function gv_보유잔액(string $nick, $currency = 'point'): string {
    $nick = trim($nick);
    if ($nick === '') {
        return '0';
    }
    $col = gv_통화컬럼($currency);
    $esc = addslashes($nick);
    // 본방냥은 소수 보유 가능 → FLOOR 후 읽기 (소수점이 자릿수에 붙는 것 방지)
    if ($col === 'newpoint') {
        $row = @db_select("SELECT CAST(FLOOR(CAST(IFNULL(`newpoint`,0) AS DECIMAL(65,4))) AS CHAR) AS bal
            FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    } else {
        $row = @db_select("SELECT CAST(IFNULL(`{$col}`,0) AS CHAR) AS bal FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    }
    return gv_냥($row['bal'] ?? 0);
}

function gv_잔액차감(string $nick, $currency, $amount): bool {
    $col = gv_통화컬럼($currency);
    $esc = addslashes(trim($nick));
    $amt = gv_냥($amount);
    if ($esc === '' || gv_비교($amt, '0') <= 0) {
        return false;
    }
    db_query("UPDATE tb_member SET `{$col}` = `{$col}` - {$amt}
        WHERE name = '{$esc}' AND `{$col}` >= {$amt} LIMIT 1");
    return gv_affected() >= 1;
}

function gv_잔액지급(string $nick, $currency, $amount): bool {
    $col = gv_통화컬럼($currency);
    $esc = addslashes(trim($nick));
    $amt = gv_냥($amount);
    if ($esc === '' || gv_비교($amt, '0') <= 0) {
        return true;
    }
    db_query("UPDATE tb_member SET `{$col}` = `{$col}` + {$amt} WHERE name = '{$esc}' LIMIT 1");
    return gv_affected() >= 1;
}

/**
 * 본냥/겜냥 분리용 스키마 (스키마준비완료 플래그와 무관하게 항상 확인)
 */
function gv_통화스키마보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $chk = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'currency'");
    if (empty($chk['Field'])) {
        @db_query("ALTER TABLE tb_gold_bar
            ADD COLUMN `currency` VARCHAR(16) NOT NULL DEFAULT 'point'
            COMMENT 'point=게임냥 · newpoint=본방냥' AFTER `bar_kind`");
        @db_query("UPDATE tb_gold_bar SET currency = 'point' WHERE IFNULL(currency,'') = ''");
    }
    $idx = @db_select("SHOW INDEX FROM tb_gold_bar WHERE Key_name = 'idx_nick_status_currency'");
    if (empty($idx['Key_name'])) {
        @db_query("ALTER TABLE tb_gold_bar ADD INDEX idx_nick_status_currency (nick, status, currency)");
    }

    gv_대기이자_스키마보장();
    $chkNp = @db_select("SHOW COLUMNS FROM tb_gold_vault_interest LIKE 'pending_newpoint'");
    if (empty($chkNp['Field'])) {
        @db_query("ALTER TABLE tb_gold_vault_interest
            ADD COLUMN `pending_newpoint` DECIMAL(65,0) NOT NULL DEFAULT 0 AFTER `pending`");
    }
}

/**
 * api/function.php 지연 로드 — 읽기 경로에서는 올리지 않음 (11k줄 파싱 비용 회피)
 * 지급로그 등 쓰기 시에만 호출
 */
function gv_api함수로드(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('지급로그') && function_exists('냥_정수문자열')) {
        return;
    }
    $path = $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    if (is_file($path)) {
        include_once $path;
    }
}

function gv_지급로그($상태, $닉, $받는이, $수수료, $지급냥) {
    gv_api함수로드();
    if (function_exists('지급로그')) {
        return 지급로그($상태, $닉, $받는이, $수수료, $지급냥);
    }
    return false;
}

/**
 * 전체 예치풀 (전 회원 status=0 합산)
 * @return array{max:int,used:int,remain:int,full:bool,expire_3d:int,used_disp:string,remain_disp:string,max_disp:string,expire_3d_disp:string}
 */
function gv_예치풀현황(): array {
    if (isset($GLOBALS['_gv_pool_cache']) && is_array($GLOBALS['_gv_pool_cache'])) {
        return $GLOBALS['_gv_pool_cache'];
    }
    // 첫 화면 list+status 병렬 요청 대비 앱 공유 캐시
    if (function_exists('apcu_fetch')) {
        $hit = @apcu_fetch('gv_pool_snap_v2');
        if (is_array($hit) && isset($hit['used'], $hit['expire_3d'])) {
            $GLOBALS['_gv_pool_cache'] = $hit;
            return $hit;
        }
    }
    gv_테이블보장();
    $max = max(0, (int)GOLD_VAULT_POOL_MAX);
    $row = db_select("
      SELECT
        COUNT(*) AS used,
        SUM(CASE WHEN unlock_at <= DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS expire_3d
      FROM tb_gold_bar
      WHERE status = 0
    ");
    $used = (int)($row['used'] ?? 0);
    if ($used < 0) {
        $used = 0;
    }
    $expire3d = (int)($row['expire_3d'] ?? 0);
    if ($expire3d < 0) {
        $expire3d = 0;
    }
    $remain = max(0, $max - $used);
    $out = [
        'max' => $max,
        'used' => $used,
        'remain' => $remain,
        'full' => ($remain < 1),
        'expire_3d' => $expire3d,
        'used_disp' => number_format($used),
        'remain_disp' => number_format($remain),
        'max_disp' => number_format($max),
        'expire_3d_disp' => number_format($expire3d),
    ];
    $GLOBALS['_gv_pool_cache'] = $out;
    if (function_exists('apcu_store')) {
        @apcu_store('gv_pool_snap_v2', $out, 3);
    }
    return $out;
}

/** 금괴 전부 회수 권한 (다오·피치·민호) */
function gv_회수권한인가($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return false;
    }
    $list = array_filter(array_map('trim', explode(',', (string)GOLD_VAULT_RECALL_NICKS)));
    return in_array($nick, $list, true);
}

/** @deprecated gv_회수권한인가 사용 */
function gv_다오인가($nick) {
    return gv_회수권한인가($nick);
}

function gv_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 허용 기간(일) — 신규 예치 (기존 30일 등 예치는 만기까지 유지) */
function gv_허용기간() {
    return [7, 14];
}

function gv_기간정상화($days) {
    $days = (int)$days;
    return in_array($days, gv_허용기간(), true) ? $days : 0;
}

/** @return array<string,array<string,mixed>> */
function gv_상품종류목록(): array {
    return [
        'silver' => [
            'key' => 'silver',
            'label' => '은괴',
            'amount' => GOLD_SILVER_AMOUNT,
            'amount_disp' => '1해',
            'terms' => [7, 14],
            'unit_daily' => GOLD_SILVER_DAILY_INTEREST,
        ],
        'gold' => [
            'key' => 'gold',
            'label' => '금괴',
            'amount' => GOLD_BAR_AMOUNT,
            'amount_disp' => '10해',
            'terms' => [7, 14],
            'unit_daily' => GOLD_BAR_DAILY_INTEREST,
        ],
        'plat' => [
            'key' => 'plat',
            'label' => '백금괴',
            'amount' => GOLD_PLAT_AMOUNT,
            'amount_disp' => '100해',
            'terms' => [7, 14],
            'unit_daily' => gv_일일이자(GOLD_PLAT_AMOUNT),
        ],
    ];
}

function gv_종류정상화($kind): string {
    $kind = trim((string)$kind);
    if ($kind === 'plat' || $kind === 'platinum' || $kind === '백금' || $kind === '백금괴') {
        return 'plat';
    }
    if ($kind === 'silver' || $kind === '은' || $kind === '은괴') {
        return 'silver';
    }
    if ($kind === 'cash' || $kind === 'vault' || $kind === '전액' || $kind === '전액예치' || $kind === '자유') {
        return 'cash';
    }
    if ($kind === 'lock7' || $kind === 'lock' || $kind === '잠금' || $kind === '7일잠금') {
        return 'lock7';
    }
    return 'gold';
}

function gv_종류정보($kind): array {
    $list = gv_상품종류목록();
    $key = gv_종류정상화($kind);
    return $list[$key] ?? $list['gold'];
}

function gv_종류단가($kind): string {
    return gv_냥(gv_종류정보($kind)['amount'] ?? GOLD_BAR_AMOUNT);
}

/** 종류별 허용 기간 */
function gv_종류허용기간($kind): array {
    $info = gv_종류정보($kind);
    $terms = $info['terms'] ?? gv_허용기간();
    return array_values(array_map('intval', $terms));
}

function gv_종류기간정상화($kind, $days): int {
    $days = (int)$days;
    $allowed = gv_종류허용기간($kind);
    return in_array($days, $allowed, true) ? $days : 0;
}

/** amount / bar_kind → 표시용 종류 (기존 예치는 bar_kind·구 단가로 유지) */
function gv_행종류(array $bar): string {
    $kind = trim((string)($bar['bar_kind'] ?? ''));
    if ($kind === 'cash' || $kind === 'vault' || $kind === '전액' || $kind === '전액예치' || $kind === '자유') {
        return 'cash';
    }
    if ($kind === 'lock7' || $kind === 'lock' || $kind === '잠금' || $kind === '7일잠금') {
        return 'lock7';
    }
    if ($kind === 'plat' || $kind === 'gold' || $kind === 'silver') {
        return $kind;
    }
    $amount = gv_냥($bar['amount'] ?? 0);
    // 신규 단가
    if (gv_비교($amount, GOLD_PLAT_AMOUNT) >= 0) {
        return 'plat';
    }
    if (gv_비교($amount, GOLD_BAR_AMOUNT) >= 0) {
        return 'gold';
    }
    // 구 단가(1해·1천경·100경) — bar_kind 없을 때만
    if (gv_비교($amount, GOLD_LEGACY_PLAT_AMOUNT) >= 0) {
        return 'plat';
    }
    if (gv_비교($amount, GOLD_LEGACY_GOLD_AMOUNT) >= 0) {
        return 'gold';
    }
    if (gv_비교($amount, '0') > 0) {
        return 'silver';
    }
    return 'gold';
}

function gv_종류라벨($kind): string {
    $k = gv_종류정상화($kind);
    if ($k === 'cash') {
        return '자유예치';
    }
    if ($k === 'lock7') {
        return '7일잠금';
    }
    return (string)(gv_종류정보($kind)['label'] ?? '금괴');
}

function gv_종류금액표시($kind): string {
    return (string)(gv_종류정보($kind)['amount_disp'] ?? '10해');
}

/** 은괴 수령 보너스 = 미수령 은괴 이자 × GOLD_SILVER_CLAIM_BONUS_PCT% */
function gv_은괴수령보너스($silver_claimable): string {
    $pct = max(0, (int)GOLD_SILVER_CLAIM_BONUS_PCT);
    $base = gv_냥($silver_claimable);
    if ($pct < 1 || gv_비교($base, '0') <= 0) {
        return '0';
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        return bcdiv(bcmul($base, (string)$pct, 0), '100', 0);
    }
    $scaled = gv_곱하기($base, $pct);
    if (strlen($scaled) <= 2) {
        return '0';
    }
    return ltrim(substr($scaled, 0, -2), '0') ?: '0';
}

/**
 * 수령보너스 산정용 은괴 미수령 이자 (최대 GOLD_SILVER_CLAIM_BONUS_MAX_BARS개분만)
 * @param list<array> $rows tb_gold_bar rows
 * @return array{claimable:string,bars:int}
 */
function gv_은괴수령보너스_대상(array $rows, $now = null): array {
    $now = $now ?? time();
    $maxBars = max(0, (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS);
    $sum = '0';
    $used = 0;
    if ($maxBars < 1) {
        return ['claimable' => '0', 'bars' => 0];
    }
    foreach ($rows as $row) {
        if ($used >= $maxBars) {
            break;
        }
        if (gv_행종류($row) !== 'silver') {
            continue;
        }
        $c = gv_금괴_미수령계산($row, $now);
        if (gv_비교($c['claimable'], '0') <= 0) {
            continue;
        }
        $sum = gv_더하기($sum, $c['claimable']);
        $used++;
    }
    return ['claimable' => $sum, 'bars' => $used];
}

/**
 * @deprecated 구 % 이율표 호환 — 새 규칙은 고정 10경/일
 * @return array<int,string>
 */
function gv_이율표() {
    return [
        7 => '10경',
        14 => '10경',
        30 => '10경', // 구 예치 호환
    ];
}

/** @deprecated */
function gv_일일이율($term_days) {
    return isset(gv_이율표()[(int)$term_days]) ? '10경' : '0';
}

/** @deprecated */
function gv_이율($term_days) {
    return gv_일일이율($term_days);
}

function gv_냥($v) {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($v);
    }
    // api/function.php 미로드 시에도 소수점을 자릿수에 붙이지 않음 (627432.5 → 6274325 방지)
    $s = trim((string)$v);
    if ($s === '' || $s === '-' || $s === '+') {
        return '0';
    }
    if (isset($s[0]) && ($s[0] === 'N' || $s[0] === 'n')) {
        $s = trim(substr($s, 1));
    }
    if (isset($s[0]) && ($s[0] === '-' || $s[0] === '+')) {
        $s = substr($s, 1);
    }
    // 과학적 표기 간단 처리
    if (preg_match('/^(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $s, $m)) {
        $digits = $m[1] . ($m[2] ?? '');
        $exp = (int)$m[3] - strlen($m[2] ?? '');
        if ($exp >= 0) {
            $digits .= str_repeat('0', $exp);
        } else {
            $cut = strlen($digits) + $exp;
            $digits = $cut > 0 ? substr($digits, 0, $cut) : '0';
        }
        return ltrim($digits, '0') ?: '0';
    }
    if (strpos($s, '.') !== false) {
        $s = explode('.', $s, 2)[0];
    }
    $s = preg_replace('/\D/', '', $s);
    return ltrim((string)$s, '0') ?: '0';
}

/**
 * 본방냥 표시 — 콤마만 (만/억/경 축약 금지 · 게임냥 표시와 분리)
 */
function gv_본방표시($v): string {
    $n = gv_냥($v);
    if (function_exists('newpoint표시')) {
        $s = newpoint표시($n);
        return (strpos($s, '냥') !== false) ? $s : ($s . '냥');
    }
    if ($n === '0') {
        return '0냥';
    }
    // 큰 수도 콤마 (bc 없이 3자리)
    $rev = strrev($n);
    $parts = str_split($rev, 3);
    return strrev(implode(',', $parts)) . '냥';
}

/**
 * 통화별 금액 표시 — 본방=콤마 · 게임냥=해/경 축약
 */
function gv_금액표시($v, $currency = 'point'): string {
    if (gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT) {
        return gv_본방표시($v);
    }
    return gv_경표시($v);
}

function gv_비교($a, $b) {
    $a = gv_냥($a);
    $b = gv_냥($b);
    if (function_exists('bccomp')) {
        return bccomp($a, $b, 0);
    }
    if (strlen($a) !== strlen($b)) {
        return strlen($a) <=> strlen($b);
    }
    return $a <=> $b;
}

function gv_더하기($a, $b) {
    $a = gv_냥($a);
    $b = gv_냥($b);
    if (function_exists('bcadd')) {
        return bcadd($a, $b, 0);
    }
    $a = strrev($a);
    $b = strrev($b);
    $max = max(strlen($a), strlen($b));
    $carry = 0;
    $out = '';
    for ($i = 0; $i < $max; $i++) {
        $da = $i < strlen($a) ? (ord($a[$i]) - 48) : 0;
        $db = $i < strlen($b) ? (ord($b[$i]) - 48) : 0;
        $sum = $da + $db + $carry;
        $out .= chr(($sum % 10) + 48);
        $carry = intdiv($sum, 10);
    }
    if ($carry > 0) {
        $out .= chr($carry + 48);
    }
    return ltrim(strrev($out), '0') ?: '0';
}

function gv_빼기($a, $b) {
    $a = gv_냥($a);
    $b = gv_냥($b);
    if (function_exists('bcsub') && function_exists('bccomp')) {
        if (bccomp($a, $b, 0) < 0) {
            return '0';
        }
        return bcsub($a, $b, 0);
    }
    if (gv_비교($a, $b) < 0) {
        return '0';
    }
    // (int) 캐스팅 금지 — 1천경·1해는 PHP_INT_MAX(≈922경) 초과로 중도환급이 0이 됨
    $a = strrev($a);
    $b = strrev($b);
    $max = max(strlen($a), strlen($b));
    $borrow = 0;
    $out = '';
    for ($i = 0; $i < $max; $i++) {
        $da = $i < strlen($a) ? (ord($a[$i]) - 48) : 0;
        $db = $i < strlen($b) ? (ord($b[$i]) - 48) : 0;
        $d = $da - $db - $borrow;
        if ($d < 0) {
            $d += 10;
            $borrow = 1;
        } else {
            $borrow = 0;
        }
        $out .= chr($d + 48);
    }
    return ltrim(strrev($out), '0') ?: '0';
}

function gv_곱하기($a, $b) {
    $a = gv_냥($a);
    $b = (int)$b;
    if ($b < 0) {
        $b = 0;
    }
    if ($a === '0' || $b === 0) {
        return '0';
    }
    if (function_exists('bcmul')) {
        return bcmul($a, (string)$b, 0);
    }
    $a = strrev($a);
    $carry = 0;
    $out = '';
    for ($i = 0, $len = strlen($a); $i < $len; $i++) {
        $prod = (ord($a[$i]) - 48) * $b + $carry;
        $out .= chr(($prod % 10) + 48);
        $carry = intdiv($prod, 10);
    }
    while ($carry > 0) {
        $out .= chr(($carry % 10) + 48);
        $carry = intdiv($carry, 10);
    }
    return ltrim(strrev($out), '0') ?: '0';
}

/** 정수 나눗셈 내림 (float/(int) 캐스팅 금지 — 1해 이상에서 PHP_INT_MAX 초과) */
function gv_나누기_내림($a, $b): string {
    $a = gv_냥($a);
    $b = gv_냥($b);
    if ($a === '0' || $b === '0' || gv_비교($b, '0') <= 0) {
        return '0';
    }
    if (function_exists('bcdiv')) {
        return bcdiv($a, $b, 0);
    }
    // 이율 계산용 빠른 경로: /100
    if ($b === '100') {
        if (strlen($a) <= 2) {
            return '0';
        }
        return ltrim(substr($a, 0, -2), '0') ?: '0';
    }
    if ($b === '10') {
        if (strlen($a) <= 1) {
            return '0';
        }
        return ltrim(substr($a, 0, -1), '0') ?: '0';
    }
    // 일반 나눗셈 (긴 정수)
    $divisor = $b;
    if (gv_비교($a, $divisor) < 0) {
        return '0';
    }
    $quotient = '';
    $remain = '';
    $len = strlen($a);
    for ($i = 0; $i < $len; $i++) {
        $remain .= $a[$i];
        $remain = ltrim($remain, '0') ?: '0';
        $qdigit = 0;
        while (gv_비교($remain, $divisor) >= 0) {
            $remain = gv_빼기($remain, $divisor);
            $qdigit++;
            if ($qdigit >= 10) {
                break;
            }
        }
        $quotient .= chr($qdigit + 48);
    }
    return ltrim($quotient, '0') ?: '0';
}

function gv_곱($a, $b) {
    return gv_곱하기($a, $b);
}

/** 원금 기준 하루 이자 (동일 이율 1%: 원금 × 금괴일일 / 금괴단가) · 구 예치도 원금 비율 유지 */
function gv_일일이자($amount = null) {
    $amount = gv_냥($amount !== null ? $amount : GOLD_BAR_AMOUNT);
    $base = GOLD_BAR_AMOUNT;
    $daily = GOLD_BAR_DAILY_INTEREST;
    if ($amount === '0') {
        return '0';
    }
    // 신규·구 단가 고정값 (비율 계산과 동일, 빠른 경로)
    if ($amount === $base || $amount === GOLD_LEGACY_GOLD_AMOUNT) {
        return ($amount === $base) ? $daily : GOLD_LEGACY_GOLD_DAILY;
    }
    if ($amount === GOLD_SILVER_AMOUNT) {
        return GOLD_SILVER_DAILY_INTEREST;
    }
    if ($amount === GOLD_LEGACY_SILVER_AMOUNT) {
        return GOLD_LEGACY_SILVER_DAILY;
    }
    if ($amount === GOLD_PLAT_AMOUNT) {
        return gv_곱하기($daily, 10); // 100해 → 1해/일
    }
    if ($amount === GOLD_LEGACY_PLAT_AMOUNT) {
        return GOLD_LEGACY_PLAT_DAILY;
    }
    if (function_exists('bcdiv') && function_exists('bcmul')) {
        // 구·신규 단가 외 금액도 동일 이율 — 정수 나눗셈 후 0→1 보정 금지
        return bcdiv(bcmul($amount, $daily, 0), $base, 0);
    }
    // bcmath 없음: float 금지 · 원금 1% 문자열 계산 (자유예치·임의금액)
    return gv_예치_일이자($amount, 1);
}

/** @deprecated 호환명 */
function gv_일일적립($amount, $term_days = 0) {
    return gv_일일이자($amount);
}

function gv_총이자($amount, $term_days) {
    $term = max(1, (int)$term_days);
    return gv_곱하기(gv_일일이자($amount), $term);
}

/** @deprecated 호환 — 기간 총 이자 */
function gv_만기이자($amount, $term_days) {
    return gv_총이자($amount, $term_days);
}

/** 30일 상품 연장 1회당 추가 일수 / 추가 보상 % */
if (!defined('GOLD_BAR_EXTEND_DAYS')) {
    define('GOLD_BAR_EXTEND_DAYS', 30);
}
if (!defined('GOLD_BAR_EXTEND_BONUS_PCT')) {
    define('GOLD_BAR_EXTEND_BONUS_PCT', 5);
}

/** 기본 만기 추가보상: 기간(일) => 원금 대비 % (신규 7/14 · 구 30 유지) */
function gv_만기추가보상표(): array {
    return [7 => 5, 14 => 10, 30 => 10];
}

/** 만기 추가보상 % (30일 연장 완료 시 10+5=15) */
function gv_만기추가보상율($term_days, $extend_count = 0): int {
    $term = (int)$term_days;
    $extend = max(0, (int)$extend_count);
    $map = gv_만기추가보상표();
    $base = max(0, (int)($map[$term] ?? 0));
    if ($term === 30 && $extend >= 1) {
        return $base + (int)GOLD_BAR_EXTEND_BONUS_PCT;
    }
    return $base;
}

/** 만기 추가보상액 = 원금 × % */
function gv_만기추가보상($amount, $term_days = 0, $extend_count = 0) {
    $pct = gv_만기추가보상율($term_days, $extend_count);
    if ($pct < 1) {
        return '0';
    }
    $amount = gv_냥($amount);
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        return bcdiv(bcmul($amount, (string)$pct, 0), '100', 0);
    }
    // float 금지(1해 정밀도 깨짐) — pct% = amount * pct / 100
    $scaled = gv_곱하기($amount, $pct);
    // /100 문자열 나눗셈
    if (strlen($scaled) <= 2) {
        return '0';
    }
    return ltrim(substr($scaled, 0, -2), '0') ?: '0';
}

/** 이자/만기 계산용 유효 기간 (30일 + 연장) — 기존 3·5일 예치도 원기간 유지 */
function gv_유효기간일수($term_days, $extend_count = 0): int {
    $term = (int)$term_days;
    if ($term < 1) {
        $term = gv_기간정상화($term_days);
    }
    if ($term < 1) {
        $term = 14;
    }
    $extend = max(0, min(1, (int)$extend_count));
    if ($term === 30 && $extend > 0) {
        return $term + ((int)GOLD_BAR_EXTEND_DAYS * $extend);
    }
    return $term;
}

/** 만기 환전액 = 원금 (+ 추가보상은 별도 합산) */
function gv_만기수령액($amount, $term_days = 0) {
    return gv_냥($amount);
}

/** 중도해지 수수료 = 받은 이자 × 3 (잠금 해제 후 일반 중도) */
function gv_중도수수료($interest_paid) {
    return gv_곱하기(gv_냥($interest_paid), (int)GOLD_BAR_EARLY_FEE_MULT);
}

/** 금액의 N% (정수 절사) */
function gv_비율금액($amount, $pct) {
    $amount = gv_냥($amount);
    $pct = max(0, min(100, (int)$pct));
    if ($pct <= 0 || gv_비교($amount, '0') <= 0) {
        return '0';
    }
    if ($pct >= 100) {
        return $amount;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        return bcdiv(bcmul($amount, (string)$pct, 0), '100', 0);
    }
    // 큰 수 폴백: 끝 두 자리로 대략 % (정확도는 bcmath 권장)
    $len = strlen($amount);
    if ($len <= 15) {
        return (string)(int)floor(((float)$amount) * $pct / 100);
    }
    $head = substr($amount, 0, $len - 2);
    $prod = gv_곱하기($head === '' ? '0' : $head, $pct);
    return gv_냥($prod);
}

/** 예치 기간별 중도해지 잠금 일수 — 7→4 · 14→7 · (구 30→14) */
function gv_중도잠금일수($term_days): int {
    $t = (int)$term_days;
    if ($t <= 0) {
        return (int)GOLD_BAR_EARLY_LOCK_DAYS;
    }
    if ($t <= 7) {
        return 4;
    }
    if ($t <= 14) {
        return 7;
    }
    return 14;
}

/**
 * 맡긴 뒤 기간별 N일간 중도해지 잠금
 * lock7(7일잠금형): 처음 2일 잠금 · 중도는 위약금 50→40→30→20% 차감 후 환급
 * @return array{locked:bool,remain_sec:int,unlock_at:string,remain_label:string,lock_days:int}
 */
function gv_중도잠금정보($deposited_at, $now = null, $term_days = 0, $bar_kind = ''): array {
    $now = $now ?? time();
    $kind = $bar_kind !== '' ? gv_종류정상화($bar_kind) : '';
    if ($kind === 'lock7') {
        $lockDays = max(1, (int)GOLD_VAULT_LOCK7_EARLY_LOCK_DAYS);
    } else {
        $lockDays = gv_중도잠금일수($term_days);
    }
    $empty = [
        'locked' => false,
        'remain_sec' => 0,
        'unlock_at' => '',
        'remain_label' => '',
        'lock_days' => $lockDays,
    ];
    if ($lockDays < 1) {
        return $empty;
    }
    $dep = gv_잠금7_예치타임스탬프($deposited_at);
    if ($dep <= 0) {
        return $empty;
    }
    $unlockTs = $dep + ($lockDays * 86400);
    $remain = max(0, $unlockTs - $now);
    return [
        'locked' => $remain > 0,
        'remain_sec' => $remain,
        'unlock_at' => date('Y-m-d H:i', $unlockTs),
        'remain_label' => $remain > 0 ? gv_남은시간문구($remain) : '',
        'lock_days' => $lockDays,
    ];
}

/**
 * 7일잠금 중도해지 위약금(%) — 원금에서 이만큼 차감하고 나머지를 환급
 * 맡긴 직후 50% 차감(환급 50%) → 24h마다 40 → 30 → 20%(이후 고정)
 */
function gv_잠금7_중도차감표(): array {
    return [50, 40, 30, 20];
}

/** @deprecated 환급% 표 — 차감표 기준 100−위약금 */
function gv_잠금7_중도환급표(): array {
    $out = [];
    foreach (gv_잠금7_중도차감표() as $pen) {
        $out[] = max(0, 100 - (int)$pen);
    }
    return $out;
}

function gv_잠금7_예치타임스탬프($deposited_at): int {
    if (is_array($deposited_at)) {
        return gv_예치타임스탬프($deposited_at);
    }
    return gv_예치타임스탬프(['deposited_at' => $deposited_at]);
}

function gv_잠금7_중도환급비율($deposited_at, $now = null): int {
    $table = gv_잠금7_중도차감표();
    $floor = (int)$table[count($table) - 1];
    if ($floor < 0) {
        $floor = 20;
    }
    $dep = gv_잠금7_예치타임스탬프($deposited_at);
    $days = ($dep > 0) ? gv_예치경과일수_만하루($dep, $now) : 0;
    if ($days < 0) {
        $days = 0;
    }
    if ($days >= count($table)) {
        $penalty = $floor;
    } else {
        $penalty = (int)$table[$days];
    }
    return max(0, min(100, 100 - $penalty));
}

/**
 * 잠금 중 강제해지 원금 환급 비율(%)
 * 구 금괴: 맡긴 당일 50% → 다음날 45% → 40% → 35% → 30%(이후 고정)
 * 7일잠금(bar_kind=lock7): 위약금 50→40→30→20 차감 · 환급 50→60→70→80
 */
function gv_강제중도환급비율($deposited_at, $now = null, $bar_kind = ''): int {
    $kind = $bar_kind;
    if ($kind === '' && is_array($deposited_at)) {
        $kind = (string)($deposited_at['bar_kind'] ?? '');
    }
    if ($kind !== '' && gv_종류정상화($kind) === 'lock7') {
        return gv_잠금7_중도환급비율($deposited_at, $now);
    }
    $start = (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT;
    $floor = (int)GOLD_BAR_FORCE_EARLY_KEEP_FLOOR_PCT;
    $step = (int)GOLD_BAR_FORCE_EARLY_KEEP_STEP_PCT;
    if ($start < $floor) {
        $start = $floor;
    }
    if ($step < 1) {
        return $start;
    }
    $now = $now ?? time();
    $dep = gv_잠금7_예치타임스탬프($deposited_at);
    if ($dep <= 0) {
        return $start;
    }
    // 달력 기준: 맡긴 날=0 · 그다음날=1 …
    $day0 = strtotime(date('Y-m-d', $dep));
    $dayNow = strtotime(date('Y-m-d', $now));
    if ($day0 === false || $dayNow === false) {
        return $start;
    }
    $elapsed = max(0, (int)(($dayNow - $day0) / 86400));
    return max($floor, $start - ($elapsed * $step));
}

function gv_강제중도_예치있음($deposited_at): bool {
    if ($deposited_at === null) {
        return false;
    }
    if (is_array($deposited_at)) {
        return gv_잠금7_예치타임스탬프($deposited_at) > 0
            || trim((string)($deposited_at['deposited_at'] ?? '')) !== '';
    }
    return (string)$deposited_at !== '';
}

/** 잠금 중 강제 중도해지 수수료 = 원금의 (100−환급%) */
function gv_강제중도수수료($amount, $deposited_at = null, $now = null, $bar_kind = '') {
    $keep = gv_강제중도_예치있음($deposited_at)
        ? gv_강제중도환급비율($deposited_at, $now, $bar_kind)
        : (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT;
    return gv_비율금액($amount, max(0, 100 - $keep));
}

/** 잠금 중 강제 중도해지 원금 환급 = 원금 × 환급% */
function gv_강제중도수령액($amount, $deposited_at = null, $now = null, $bar_kind = '') {
    $keep = gv_강제중도_예치있음($deposited_at)
        ? gv_강제중도환급비율($deposited_at, $now, $bar_kind)
        : (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT;
    return gv_비율금액($amount, $keep);
}

/** 중도해지 원금 환급 = 원금 − 이자×3 수수료 (잠금 해제 후) */
function gv_중도수령액($amount, $interest_paid = '0') {
    $fee = gv_중도수수료($interest_paid);
    return gv_빼기(gv_냥($amount), $fee);
}

function gv_경숫자콤마($경수) {
    if (function_exists('냥_숫자콤마')) {
        return 냥_숫자콤마($경수);
    }
    $d = ltrim(preg_replace('/[^\d]/', '', (string)$경수), '0') ?: '0';
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $d);
}

/** 문자열 나눗셈 몫 (양의 정수) — bcmath 없을 때 자릿수 절단 */
function gv_나눗셈몫(string $digits, string $divisor): string {
    $digits = ltrim(preg_replace('/\D/', '', $digits), '0') ?: '0';
    $divisor = ltrim(preg_replace('/\D/', '', $divisor), '0') ?: '0';
    if ($digits === '0' || $divisor === '0') {
        return '0';
    }
    if (function_exists('bcdiv')) {
        return bcdiv($digits, $divisor, 0);
    }
    // 10^k 배수(억/조/경) — 자릿수 같아도 몫이 2~9일 수 있음
    // (옛코드: 동일 자릿수면 무조건 1/0 → 5.14억이 1억으로 표시됨)
    if (preg_match('/^1(0+)$/', $divisor, $m)) {
        $zeros = strlen($m[1]);
        if (strlen($digits) <= $zeros) {
            return '0';
        }
        return ltrim(substr($digits, 0, -$zeros), '0') ?: '0';
    }
    $dLen = strlen($divisor);
    if (strlen($digits) < $dLen) {
        return '0';
    }
    if (strlen($digits) === $dLen) {
        return ($digits >= $divisor) ? '1' : '0';
    }
    // 일반 폴백
    if (function_exists('bccomp') && function_exists('bcsub')) {
        $q = '0';
        $r = $digits;
        while (bccomp($r, $divisor, 0) >= 0) {
            $r = bcsub($r, $divisor, 0);
            $q = function_exists('bcadd') ? bcadd($q, '1', 0) : (string)((int)$q + 1);
            if (strlen($q) > 40) {
                break;
            }
        }
        return $q;
    }
    return '0';
}

/** 문자열 나머지 */
function gv_나눗셈나머지(string $digits, string $divisor): string {
    $digits = ltrim(preg_replace('/\D/', '', $digits), '0') ?: '0';
    $divisor = ltrim(preg_replace('/\D/', '', $divisor), '0') ?: '0';
    if ($digits === '0' || $divisor === '0') {
        return '0';
    }
    if (function_exists('bcmod')) {
        return bcmod($digits, $divisor);
    }
    if (preg_match('/^1(0+)$/', $divisor, $m)) {
        $zeros = strlen($m[1]);
        if (strlen($digits) <= $zeros) {
            return $digits;
        }
        return ltrim(substr($digits, -$zeros), '0') ?: '0';
    }
    $q = gv_나눗셈몫($digits, $divisor);
    return gv_빼기($digits, gv_곱하기($divisor, (int)$q));
}

function gv_이상인가(string $a, string $b): bool {
    $a = ltrim(preg_replace('/\D/', '', $a), '0') ?: '0';
    $b = ltrim(preg_replace('/\D/', '', $b), '0') ?: '0';
    if (function_exists('bccomp')) {
        return bccomp($a, $b, 0) >= 0;
    }
    if (strlen($a) !== strlen($b)) {
        return strlen($a) > strlen($b);
    }
    return $a >= $b;
}

/**
 * 해·경 축약 (이자·금괴 단가용) — function.php / bcmath 없이도 동작
 * 예) 10경 · 1해 · 955해 8000경
 */
function gv_경표시($v) {
    $n = gv_냥($v);
    if ($n === '0') {
        return '0';
    }
    $경단위 = '10000000000000000'; // 1경 = 10^16
    if (!gv_이상인가($n, $경단위)) {
        return gv_냥표시($n);
    }

    $경정수 = gv_나눗셈몫($n, $경단위);
    // 1해 = 10000경
    if (gv_이상인가($경정수, '10000')) {
        $해 = gv_나눗셈몫($경정수, '10000');
        $경남 = gv_나눗셈나머지($경정수, '10000');
        $base = gv_경숫자콤마($해) . '해';
        if ($경남 !== '0') {
            $base .= ' ' . gv_경숫자콤마($경남) . '경';
        }
        return $base;
    }
    return gv_경숫자콤마($경정수) . '경';
}

/**
 * 보유냥 등 일반 축약 — 해/경/조/억/만/원 (지갑·랭킹과 동일 계열)
 * ※ 예전: 억 미만을 버려 "2억5719만…" → "2억냥"처럼 잘림
 */
function gv_냥표시($v) {
    $n = gv_냥($v);
    // 지갑과 동일: 만·원까지 살리는 표시 우선
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '냥');
    }
    if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($n, '냥');
    }
    if (function_exists('냥축약표시')) {
        return 냥축약표시($n, '냥');
    }
    if ($n === '0') {
        return '0냥';
    }
    $조단위 = '1000000000000';      // 10^12
    $경단위 = '10000000000000000'; // 10^16
    $억단위 = '100000000';         // 10^8
    $만단위 = '10000';             // 10^4

    $만원인접 = static function (string $rest) use ($만단위): string {
        $rest = ltrim(preg_replace('/\D/', '', $rest), '0') ?: '0';
        if ($rest === '0') {
            return '';
        }
        if (gv_이상인가($rest, $만단위)) {
            $만수 = gv_나눗셈몫($rest, $만단위);
            $원 = gv_나눗셈나머지($rest, $만단위);
            $out = gv_경숫자콤마($만수) . '만';
            if ($원 !== '0') {
                $out .= gv_경숫자콤마($원);
            }
            return $out;
        }
        return gv_경숫자콤마($rest);
    };

    // 1경 이상 → 해+경+조 (+억 이하)
    if (gv_이상인가($n, $경단위)) {
        $경수 = gv_나눗셈몫($n, $경단위);
        $경이하 = gv_나눗셈나머지($n, $경단위);
        $조수 = gv_나눗셈몫($경이하, $조단위);
        $조이하 = gv_나눗셈나머지($경이하, $조단위);
        if (gv_이상인가($경수, '10000')) {
            $해 = gv_나눗셈몫($경수, '10000');
            $경남 = gv_나눗셈나머지($경수, '10000');
            $out = gv_경숫자콤마($해) . '해';
            if ($경남 !== '0') {
                $out .= gv_경숫자콤마($경남) . '경';
            }
        } else {
            $out = gv_경숫자콤마($경수) . '경';
        }
        if ($조수 !== '0') {
            $out .= gv_경숫자콤마($조수) . '조';
        }
        if (gv_이상인가($조이하, $억단위)) {
            $억수 = gv_나눗셈몫($조이하, $억단위);
            $out .= gv_경숫자콤마($억수) . '억';
            $out .= $만원인접(gv_나눗셈나머지($조이하, $억단위));
        } else {
            $out .= $만원인접($조이하);
        }
        return $out . '냥';
    }
    // 1조 이상 → 조+억+만+원
    if (gv_이상인가($n, $조단위)) {
        $조수 = gv_나눗셈몫($n, $조단위);
        $조이하 = gv_나눗셈나머지($n, $조단위);
        $out = gv_경숫자콤마($조수) . '조';
        if (gv_이상인가($조이하, $억단위)) {
            $out .= gv_경숫자콤마(gv_나눗셈몫($조이하, $억단위)) . '억';
            $out .= $만원인접(gv_나눗셈나머지($조이하, $억단위));
        } else {
            $out .= $만원인접($조이하);
        }
        return $out . '냥';
    }
    // 1억 이상 → 억+만+원 (예: 2억5,719만5,420냥)
    if (gv_이상인가($n, $억단위)) {
        $억수 = gv_나눗셈몫($n, $억단위);
        $rest = gv_나눗셈나머지($n, $억단위);
        return gv_경숫자콤마($억수) . '억' . $만원인접($rest) . '냥';
    }
    $만원 = $만원인접($n);
    return ($만원 !== '' ? $만원 : '0') . '냥';
}

function gv_이율표시($rate_pct) {
    $r = trim((string)$rate_pct);
    if ($r === '' || $r === '0') {
        return '0';
    }
    if (strpos($r, '경') !== false || strpos($r, '%') !== false) {
        return $r;
    }
    return rtrim(rtrim($r, '0'), '.') . '%';
}

/** 스키마 준비 완료 플래그 — 있으면 SHOW COLUMNS 반복 생략 */
function gv_스키마준비완료인가(): bool {
    if (!empty($GLOBALS['_gv_schema_ready'])) {
        return true;
    }
    if (function_exists('apcu_fetch')) {
        $hit = @apcu_fetch('gv_schema_ready_v3');
        if ($hit) {
            $GLOBALS['_gv_schema_ready'] = true;
            return true;
        }
    }
    $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/gv_schema_ready_v3.flag';
    if (is_file($file) && (time() - (int)@filemtime($file)) < 86400) {
        $GLOBALS['_gv_schema_ready'] = true;
        return true;
    }
    $row = @db_select("SELECT key_name FROM tb_gold_vault_migrate WHERE key_name = 'schema_ready_v3' LIMIT 1");
    if (!empty($row['key_name'])) {
        $GLOBALS['_gv_schema_ready'] = true;
        if (function_exists('apcu_store')) {
            @apcu_store('gv_schema_ready_v3', 1, 86400);
        }
        @touch($file);
        return true;
    }
    return false;
}

function gv_스키마준비완료_표시(): void {
    @db_query("CREATE TABLE IF NOT EXISTS `tb_gold_vault_migrate` (
      `key_name` VARCHAR(64) NOT NULL,
      `done_at` DATETIME NOT NULL,
      PRIMARY KEY (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @db_query("INSERT IGNORE INTO tb_gold_vault_migrate (key_name, done_at)
        VALUES ('schema_ready_v3', NOW())");
    $GLOBALS['_gv_schema_ready'] = true;
    if (function_exists('apcu_store')) {
        @apcu_store('gv_schema_ready_v3', 1, 86400);
    }
    $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/gv_schema_ready_v3.flag';
    @touch($file);
}

function gv_컬럼보장() {
    static $done = false;
    if ($done) {
        return;
    }
    // 이미 마이그레이션 끝난 서버: SHOW COLUMNS/INDEX 왕복 전부 스킵
    if (gv_스키마준비완료인가()) {
        $done = true;
        return;
    }

    // 1해·1천경 저장 — BIGINT(~922경)면 예치 원금이 잘림
    gv_금괴금액컬럼보장();

    $chk = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'term_days'");
    if (empty($chk['Field'])) {
        @db_query("ALTER TABLE tb_gold_bar
            ADD COLUMN `term_days` SMALLINT NOT NULL DEFAULT 7 AFTER `amount`,
            ADD COLUMN `early` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    }
    $chkPaid = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'paid_days'");
    if (empty($chkPaid['Field'])) {
        @db_query("ALTER TABLE tb_gold_bar
            ADD COLUMN `paid_days` SMALLINT NOT NULL DEFAULT 0 AFTER `early`,
            ADD COLUMN `interest_paid` DECIMAL(40,0) NOT NULL DEFAULT 0 AFTER `paid_days`,
            ADD COLUMN `last_interest_at` DATETIME DEFAULT NULL AFTER `interest_paid`");
    }
    $chkExt = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'extend_count'");
    if (empty($chkExt['Field'])) {
        @db_query("ALTER TABLE tb_gold_bar
            ADD COLUMN `extend_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `term_days`");
    }
    $chkKind = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'bar_kind'");
    if (empty($chkKind['Field'])) {
        @db_query("ALTER TABLE tb_gold_bar
            ADD COLUMN `bar_kind` VARCHAR(16) NOT NULL DEFAULT 'gold' AFTER `amount`");
        // 기존 1해 이상 금액 → 백금괴 (구 단가 기준 · 신규 100해로 올리지 않음)
        @db_query("UPDATE tb_gold_bar
            SET bar_kind = 'plat'
            WHERE amount >= " . GOLD_LEGACY_PLAT_AMOUNT);
    }
    gv_만기원금누적_컬럼보장();
    gv_BIGINT잘림_원금복구();
    // 대량 보유자 목록/필터용 인덱스
    $idx = @db_select("SHOW INDEX FROM tb_gold_bar WHERE Key_name = 'idx_nick_status_unlock'");
    if (empty($idx['Key_name'])) {
        @db_query("ALTER TABLE tb_gold_bar ADD INDEX idx_nick_status_unlock (nick, status, unlock_at)");
    }
    $done = true;
}

/**
 * tb_gold_bar.amount → DECIMAL(40,0)
 * BIGINT/좁은 DECIMAL이면 1천경·1해가 922경…으로 잘림
 */
function gv_금괴금액컬럼보장() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $info = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'amount'");
    if (empty($info['Field'])) {
        return;
    }
    $type = strtolower((string)($info['Type'] ?? ''));
    if (strpos($type, 'decimal(40') !== false || strpos($type, 'decimal(65') !== false) {
        return;
    }
    @db_query("ALTER TABLE tb_gold_bar
        MODIFY COLUMN `amount` DECIMAL(40,0) NOT NULL DEFAULT " . GOLD_BAR_AMOUNT . "
        COMMENT '신규:1은괴=1해·1금괴=10해·1백금괴=100해 / 구예치 amount유지'");
}

/**
 * BIGINT 상한으로 잘린 보관 중 원금을 종류별 정상 단가로 복구 (1회)
 * 증상: 백금(1해)인데 이자 10경·환급 ≈892경3372조 (=PHP_INT_MAX−수수료)
 */
function gv_BIGINT잘림_원금복구() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    @db_query("CREATE TABLE IF NOT EXISTS `tb_gold_vault_migrate` (
      `key_name` VARCHAR(64) NOT NULL,
      `done_at` DATETIME NOT NULL,
      PRIMARY KEY (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $row = @db_select("SELECT key_name FROM tb_gold_vault_migrate WHERE key_name = 'fix_bigint_amount_clip_v1' LIMIT 1");
    if (!empty($row['key_name'])) {
        return;
    }

    // 컬럼이 DECIMAL이어야 1해 저장 가능
    gv_금괴금액컬럼보장();
    $info = @db_select("SHOW COLUMNS FROM tb_gold_bar LIKE 'amount'");
    $type = strtolower((string)($info['Type'] ?? ''));
    if (strpos($type, 'decimal') === false) {
        // ALTER 실패 시 잘린 값으로 다시 쓰지 않음 — 다음 요청에서 재시도
        return;
    }

    $bigintMax = '9223372036854775807';
    // 구 단가로만 복구 — 신규 단가(10해·100해)로 올리면 기존 보유가 깨짐
    $plat = GOLD_LEGACY_PLAT_AMOUNT;
    $gold = GOLD_LEGACY_GOLD_AMOUNT;

    // 백금괴: 단가 미만(잘림) → 구 1해 복구
    @db_query("UPDATE tb_gold_bar
        SET amount = {$plat}
        WHERE status = 0
          AND IFNULL(bar_kind, 'gold') = 'plat'
          AND amount < {$plat}");

    // 금괴: BIGINT 상한으로 잘림 → 구 1천경 복구
    @db_query("UPDATE tb_gold_bar
        SET amount = {$gold}
        WHERE status = 0
          AND IFNULL(bar_kind, 'gold') <> 'plat'
          AND amount = {$bigintMax}");

    @db_query("INSERT INTO tb_gold_vault_migrate (key_name, done_at)
        VALUES ('fix_bigint_amount_clip_v1', NOW())");
}

/**
 * config.개인금고_만기원금누적 — 정상 만기 원금 누적(스왑 게임냥 합계 제외용)
 * DECIMAL(65,0): 1천경 이상 반복 누적 보존
 */
function gv_만기원금누적_컬럼보장() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM config LIKE '개인금고_만기원금누적'");
    if (empty($col['Field'])) {
        @db_query("ALTER TABLE config
            ADD COLUMN `개인금고_만기원금누적` DECIMAL(65,0) NOT NULL DEFAULT 0
            COMMENT '개인금고 정상 만기 원금 누적(스왑 게임냥 제외용)'");
    }
}

/**
 * 정상 만기 원금만 원자적 누적. 이자·보너스·중도·회수는 호출하지 말 것.
 * @return bool 성공 여부
 */
function gv_만기원금누적_추가($원금): bool {
    gv_만기원금누적_컬럼보장();
    $원금 = gv_냥($원금);
    if (gv_비교($원금, '0') <= 0) {
        return true;
    }
    // SQL 주입 방지 · 숫자만
    $원금_sql = preg_replace('/\D/', '', (string)$원금) ?: '0';
    if ($원금_sql === '0') {
        return true;
    }
    $ok = @db_query("
        UPDATE config
        SET `개인금고_만기원금누적` = `개인금고_만기원금누적` + {$원금_sql}
        LIMIT 1
    ");
    if ($ok === false) {
        return false;
    }
    // config 1행이 있어야 함
    return gv_affected() >= 1;
}

/**
 * 정상 만기 원금 개인 잔액 — 마켓 소비 시 글로벌 누적 차감용
 * remaining: 아직 마켓에서 안 쓴 만기 원금
 */
function gv_만기원금잔액_스키마보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS `tb_gold_maturity_balance` (
        `nick` VARCHAR(50) NOT NULL COMMENT '만기해지 회원 2글자닉',
        `remaining` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '마켓 미소비 만기 원금 잔액',
        `total_matured` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '정상 만기 원금 누적 합',
        `market_spent` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '마켓 소비로 차감된 합',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`nick`),
        KEY `idx_remaining` (`remaining`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      COMMENT='금괴 정상만기 원금 잔액(마켓 소비 시 글로벌 누적 차감)'
    ");
    gv_만기원금잔액_과거백필();
}

/** 과거 정상만기(status=1, early=0) 원금을 개인 잔액에 1회 반영 */
function gv_만기원금잔액_과거백필(): void {
    @db_query("CREATE TABLE IF NOT EXISTS `tb_gold_vault_migrate` (
      `key_name` VARCHAR(64) NOT NULL,
      `done_at` DATETIME NOT NULL,
      PRIMARY KEY (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = @db_select("SELECT key_name FROM tb_gold_vault_migrate WHERE key_name = 'maturity_balance_backfill_v1' LIMIT 1");
    if (!empty($done['key_name'])) {
        return;
    }
    // 이미 잔액 테이블에 데이터가 있으면 백필 스킵(수동 운영 중일 수 있음)
    $cnt = @db_select("SELECT COUNT(*) AS c FROM tb_gold_maturity_balance");
    if ((int)($cnt['c'] ?? 0) > 0) {
        @db_query("INSERT INTO tb_gold_vault_migrate (key_name, done_at) VALUES ('maturity_balance_backfill_v1', NOW())");
        return;
    }
    @db_query("
      INSERT INTO tb_gold_maturity_balance (nick, remaining, total_matured, market_spent, updated_at)
      SELECT nick,
             CAST(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))) AS CHAR),
             CAST(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))) AS CHAR),
             0,
             NOW()
      FROM tb_gold_bar
      WHERE status = 1
        AND IFNULL(early, 0) = 0
        AND TRIM(IFNULL(nick, '')) <> ''
      GROUP BY nick
      HAVING SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))) > 0
    ");
    @db_query("INSERT INTO tb_gold_vault_migrate (key_name, done_at) VALUES ('maturity_balance_backfill_v1', NOW())");
}

/** 정상 만기 시 개인 잔액·누적 합 증가 */
function gv_만기원금잔액_추가(string $닉, $원금): bool {
    gv_만기원금잔액_스키마보장();
    $닉 = trim($닉);
    $원금 = gv_냥($원금);
    if ($닉 === '' || gv_비교($원금, '0') <= 0) {
        return true;
    }
    $닉_esc = addslashes($닉);
    $원금_sql = preg_replace('/\D/', '', (string)$원금) ?: '0';
    if ($원금_sql === '0') {
        return true;
    }
    $ok = @db_query("
      INSERT INTO tb_gold_maturity_balance (nick, remaining, total_matured, market_spent, updated_at)
      VALUES ('{$닉_esc}', {$원금_sql}, {$원금_sql}, 0, NOW())
      ON DUPLICATE KEY UPDATE
        remaining = remaining + {$원금_sql},
        total_matured = total_matured + {$원금_sql},
        updated_at = NOW()
    ");
    return $ok !== false;
}

/** 닉 변경 이관 — PK(nick) 라 변경닉 행이 이미 있으면 합산해서 넘긴다 */
function gv_만기원금잔액_닉변경(string $기존닉, string $변경닉): bool {
    gv_만기원금잔액_스키마보장();
    $기존닉 = trim($기존닉);
    $변경닉 = trim($변경닉);
    if ($기존닉 === '' || $변경닉 === '' || $기존닉 === $변경닉) {
        return true;
    }
    $기존_esc = addslashes($기존닉);
    $변경_esc = addslashes($변경닉);

    $row = @db_select("
      SELECT CAST(IFNULL(remaining, 0) AS CHAR) AS remaining,
             CAST(IFNULL(total_matured, 0) AS CHAR) AS total_matured,
             CAST(IFNULL(market_spent, 0) AS CHAR) AS market_spent
      FROM tb_gold_maturity_balance
      WHERE nick = '{$기존_esc}'
      LIMIT 1
    ");
    if (empty($row)) {
        return true;
    }

    $정수 = static function ($v) {
        $v = preg_replace('/\D/', '', (string)$v);
        return ($v === '' || $v === null) ? '0' : $v;
    };
    $remaining = $정수($row['remaining'] ?? 0);
    $total     = $정수($row['total_matured'] ?? 0);
    $spent     = $정수($row['market_spent'] ?? 0);

    $ok = @db_query("
      INSERT INTO tb_gold_maturity_balance (nick, remaining, total_matured, market_spent, updated_at)
      VALUES ('{$변경_esc}', {$remaining}, {$total}, {$spent}, NOW())
      ON DUPLICATE KEY UPDATE
        remaining = remaining + {$remaining},
        total_matured = total_matured + {$total},
        market_spent = market_spent + {$spent},
        updated_at = NOW()
    ");
    if ($ok === false) {
        return false;
    }
    return @db_query("DELETE FROM tb_gold_maturity_balance WHERE nick = '{$기존_esc}'") !== false;
}

function gv_만기원금잔액_조회(string $닉): string {
    gv_만기원금잔액_스키마보장();
    $닉 = trim($닉);
    if ($닉 === '') {
        return '0';
    }
    $닉_esc = addslashes($닉);
    $row = @db_select("
      SELECT CONCAT('N', CAST(IFNULL(remaining, 0) AS CHAR)) AS amt
      FROM tb_gold_maturity_balance
      WHERE nick = '{$닉_esc}'
      LIMIT 1
    ");
    $raw = (string)($row['amt'] ?? 'N0');
    if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
        $raw = substr($raw, 1);
    }
    if (strpos($raw, '.') !== false) {
        $raw = explode('.', $raw, 2)[0];
    }
    return gv_냥($raw);
}

/** 개인금고 원장(예치/해지 기록) */
function gv_원장스키마보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_gold_vault_ledger` (
      `idx` INT NOT NULL AUTO_INCREMENT,
      `nick` VARCHAR(50) NOT NULL,
      `action` VARCHAR(32) NOT NULL DEFAULT '',
      `amount` DECIMAL(65,0) NOT NULL DEFAULT 0,
      `currency` VARCHAR(16) NOT NULL DEFAULT 'point',
      `bar_idx` INT NOT NULL DEFAULT 0,
      `memo` VARCHAR(255) NOT NULL DEFAULT '',
      `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`idx`),
      KEY `idx_nick_date` (`nick`, `regdate`),
      KEY `idx_nick_bar` (`nick`, `bar_idx`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    COMMENT='개인금고 예치·해지 기록'");
    $chk = @db_select("SHOW COLUMNS FROM tb_gold_vault_ledger LIKE 'currency'");
    if (empty($chk['Field'])) {
        @db_query("ALTER TABLE tb_gold_vault_ledger
            ADD COLUMN `currency` VARCHAR(16) NOT NULL DEFAULT 'point'
            COMMENT 'point=게임냥 · newpoint=본방냥' AFTER `amount`");
        // 구기록: 메모에 본방/본냥이 있으면 본방냥으로 보정
        @db_query("UPDATE tb_gold_vault_ledger
            SET currency = 'newpoint'
            WHERE IFNULL(currency,'point') = 'point'
              AND (memo LIKE '%본방냥%' OR memo LIKE '%본냥%')");
    }
    $idxBar = @db_select("SHOW INDEX FROM tb_gold_vault_ledger WHERE Key_name = 'idx_nick_bar'");
    if (empty($idxBar['Key_name'])) {
        @db_query("ALTER TABLE tb_gold_vault_ledger ADD INDEX idx_nick_bar (nick, bar_idx)");
    }
}

/** 메모/컬럼으로 원장 통화 추론 */
function gv_원장통화추론(array $row): string {
    $cur = trim((string)($row['currency'] ?? ''));
    if ($cur !== '') {
        return gv_통화정상화($cur);
    }
    $memo = (string)($row['memo'] ?? '');
    if ($memo !== '' && (mb_strpos($memo, '본방냥') !== false || mb_strpos($memo, '본냥') !== false)) {
        return GOLD_VAULT_CURRENCY_NEWPOINT;
    }
    return GOLD_VAULT_CURRENCY_POINT;
}

/**
 * 예치는 됐는데 원장이 빠진 건 보정 (본방/겜냥 cash·lock7)
 */
function gv_원장_활성예치백필(string $nick): void {
    static $doneNicks = [];
    $nick = trim($nick);
    if ($nick === '' || isset($doneNicks[$nick])) {
        return;
    }
    $doneNicks[$nick] = true;
    gv_원장스키마보장();
    gv_테이블보장();
    $esc = addslashes($nick);
    $rs = @db_query("
        SELECT b.idx,
               CAST(b.amount AS CHAR) AS amount,
               IFNULL(b.bar_kind, 'gold') AS bar_kind,
               IFNULL(b.currency, 'point') AS currency,
               b.deposited_at
        FROM tb_gold_bar b
        WHERE b.nick = '{$esc}'
          AND b.status = 0
          AND IFNULL(b.bar_kind, '') IN ('cash', 'lock7')
          AND NOT EXISTS (
            SELECT 1 FROM tb_gold_vault_ledger l
            WHERE l.nick = '{$esc}'
              AND l.bar_idx = b.idx
              AND l.action IN ('deposit_all', 'deposit_lock7', 'deposit')
          )
        ORDER BY b.idx ASC
        LIMIT 80
    ");
    if (!$rs) {
        return;
    }
    while ($row = db_fetch($rs)) {
        $idx = (int)($row['idx'] ?? 0);
        if ($idx < 1) {
            continue;
        }
        $kind = trim((string)($row['bar_kind'] ?? 'cash'));
        $currency = gv_통화정상화($row['currency'] ?? 'point');
        $라벨 = gv_통화라벨($currency);
        $action = ($kind === 'lock7') ? 'deposit_lock7' : 'deposit_all';
        $label = ($kind === 'lock7') ? '7일잠금 맡기기' : '자유예치 맡기기';
        $dep = trim((string)($row['deposited_at'] ?? ''));
        $memo = "{$라벨} {$label} (내역 보정)";
        $amt = gv_냥($row['amount'] ?? 0);
        $cur_esc = addslashes($currency);
        $action_esc = addslashes($action);
        $memo_esc = addslashes(mb_substr($memo, 0, 255));
        $depSql = ($dep !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $dep))
            ? ("'" . addslashes($dep) . "'")
            : 'NOW()';
        @db_query("INSERT INTO tb_gold_vault_ledger
            SET nick = '{$esc}', action = '{$action_esc}', amount = {$amt},
                currency = '{$cur_esc}', bar_idx = {$idx}, memo = '{$memo_esc}',
                regdate = {$depSql}");
    }
}

/** 닉변 시 원장·대기이자 동기화 */
function gv_원장_닉변경(string $기존닉, string $변경닉): void {
    $기존닉 = trim($기존닉);
    $변경닉 = trim($변경닉);
    if ($기존닉 === '' || $변경닉 === '' || $기존닉 === $변경닉) {
        return;
    }
    gv_원장스키마보장();
    gv_대기이자_스키마보장();
    $a = addslashes($기존닉);
    $b = addslashes($변경닉);
    @db_query("UPDATE tb_gold_vault_ledger SET nick = '{$b}' WHERE nick = '{$a}'");
    // 대기이자: 변경닉 행이 있으면 합산 후 삭제, 없으면 rename
    $old = @db_select("SELECT CAST(IFNULL(pending,0) AS CHAR) AS pending,
            CAST(IFNULL(pending_newpoint,0) AS CHAR) AS pending_newpoint
        FROM tb_gold_vault_interest WHERE nick = '{$a}' LIMIT 1");
    if (!empty($old)) {
        $new = @db_select("SELECT nick FROM tb_gold_vault_interest WHERE nick = '{$b}' LIMIT 1");
        $p = gv_냥($old['pending'] ?? 0);
        $np = gv_냥($old['pending_newpoint'] ?? 0);
        if (!empty($new['nick'])) {
            @db_query("UPDATE tb_gold_vault_interest
                SET pending = pending + {$p},
                    pending_newpoint = pending_newpoint + {$np},
                    updated_at = NOW()
                WHERE nick = '{$b}' LIMIT 1");
            @db_query("DELETE FROM tb_gold_vault_interest WHERE nick = '{$a}' LIMIT 1");
        } else {
            @db_query("UPDATE tb_gold_vault_interest SET nick = '{$b}', updated_at = NOW() WHERE nick = '{$a}' LIMIT 1");
        }
    }
}

function gv_원장기록(string $nick, string $action, $amount, int $bar_idx = 0, string $memo = '', $currency = 'point'): void {
    gv_원장스키마보장();
    $nick = trim($nick);
    if ($nick === '') {
        return;
    }
    $amt = gv_냥($amount);
    $currency = gv_통화정상화($currency);
    // 메모에만 본방 표시가 있고 currency 미지정이면 보정
    if ($currency === GOLD_VAULT_CURRENCY_POINT) {
        if (mb_strpos($memo, '본방냥') !== false || mb_strpos($memo, '본냥') !== false) {
            $currency = GOLD_VAULT_CURRENCY_NEWPOINT;
        }
    }
    $nick_esc = addslashes($nick);
    $action_esc = addslashes(mb_substr(trim($action), 0, 32));
    $memo_esc = addslashes(mb_substr(trim($memo), 0, 255));
    $cur_esc = addslashes($currency);
    $bar_idx = max(0, $bar_idx);
    @db_query("INSERT INTO tb_gold_vault_ledger
        SET nick = '{$nick_esc}', action = '{$action_esc}', amount = {$amt},
            currency = '{$cur_esc}', bar_idx = {$bar_idx}, memo = '{$memo_esc}', regdate = NOW()");
}

/** @return list<array{action:string,action_label:string,amount:string,amount_disp:string,currency:string,currency_label:string,bar_idx:int,memo:string,regdate:string}> */
function gv_원장목록(string $nick, int $limit = 20): array {
    gv_원장스키마보장();
    $nick = trim($nick);
    if ($nick === '') {
        return [];
    }
    gv_원장_활성예치백필($nick);
    $limit = max(1, min(50, $limit));
    $nick_esc = addslashes($nick);
    $rs = @db_query("SELECT action, CAST(amount AS CHAR) AS amount,
            IFNULL(currency, 'point') AS currency, bar_idx, memo, regdate
        FROM tb_gold_vault_ledger
        WHERE nick = '{$nick_esc}'
        ORDER BY idx DESC
        LIMIT {$limit}");
    $labels = [
        'deposit_all' => '자유예치 맡기기',
        'deposit_lock7' => '7일잠금 맡기기',
        'withdraw_cash' => '자유예치 빼기',
        'deposit' => '예치',
        'claim' => '이자 수령',
        'mature' => '만기해지',
        'early' => '중도해지',
        'recall' => '회수',
        'interest_pending' => '이자 적립(대기)',
    ];
    $out = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $act = trim((string)($row['action'] ?? ''));
            $amt = gv_냥($row['amount'] ?? 0);
            $currency = gv_원장통화추론($row);
            $out[] = [
                'action' => $act,
                'action_label' => $labels[$act] ?? $act,
                'amount' => $amt,
                'amount_disp' => gv_금액표시($amt, $currency),
                'currency' => $currency,
                'currency_label' => gv_통화라벨($currency),
                'bar_idx' => (int)($row['bar_idx'] ?? 0),
                'memo' => (string)($row['memo'] ?? ''),
                'regdate' => (string)($row['regdate'] ?? ''),
            ];
        }
    }
    return $out;
}

/**
 * 만 24시간 단위 경과 일수 (floor) — 24시간 미만이면 0
 * (구 금괴 gv_경과일차의 +1일 카운트와 다름)
 */
function gv_예치경과일수_만하루(int $depTs, $now = null): int {
    $now = $now ?? time();
    if ($depTs <= 0 || $now < $depTs) {
        return 0;
    }
    return (int)floor(($now - $depTs) / 86400);
}

/**
 * 예치 시각 → unix timestamp (Asia/Seoul 고정 해석)
 * deposited_ts(UNIX_TIMESTAMP)가 있으면 우선 사용
 */
function gv_예치타임스탬프(array $bar): int {
    $ts = (int)($bar['deposited_ts'] ?? 0);
    if ($ts > 0) {
        return $ts;
    }
    $raw = trim((string)($bar['deposited_at'] ?? ''));
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return 0;
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone('Asia/Seoul'));
        return $dt->getTimestamp();
    } catch (Exception $e) {
        $fallback = strtotime($raw);
        return ($fallback !== false && $fallback > 0) ? $fallback : 0;
    }
}

/**
 * 원금 × 일수 × 1%/일 (만 24시간마다 1%)
 * 1일 미만이면 0 · float/(int) 캐스팅 금지 (대형 원금에서 이자 0 버그 방지)
 */
function gv_예치_일이자($amount, int $days): string {
    $amount = gv_냥($amount);
    $days = max(0, $days);
    if ($days < 1 || gv_비교($amount, '0') <= 0) {
        return '0';
    }
    $pct = max(1, (int)GOLD_VAULT_CASH_DAILY_PCT);
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        $num = bcmul(bcmul($amount, (string)$days, 0), (string)$pct, 0);
        return bcdiv($num, '100', 0);
    }
    $num = gv_곱하기(gv_곱하기($amount, $days), $pct);
    return gv_나누기_내림($num, '100');
}

/** @deprecated 호환 — 시간이자 대신 일이자 사용 */
function gv_자유예치_시간이자($amount, int $hours): string {
    $days = (int)floor(max(0, $hours) / 24);
    return gv_예치_일이자($amount, $days);
}

function gv_예치경과시간(int $depTs, $now = null): int {
    $now = $now ?? time();
    if ($depTs <= 0 || $now < $depTs) {
        return 0;
    }
    return (int)floor(($now - $depTs) / 3600);
}

/** 빼기 시 쌓아두는 수령대기 이자 */
function gv_대기이자_스키마보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_gold_vault_interest` (
      `nick` VARCHAR(50) NOT NULL,
      `pending` DECIMAL(65,0) NOT NULL DEFAULT 0,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`nick`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    COMMENT='예치 인출·만기 시 적립된 수령대기 이자'");
}

function gv_대기이자_조회(string $nick, $currency = 'point'): string {
    gv_대기이자_스키마보장();
    gv_통화스키마보장();
    $nick = trim($nick);
    if ($nick === '') {
        return '0';
    }
    $esc = addslashes($nick);
    $col = (gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT) ? 'pending_newpoint' : 'pending';
    $row = @db_select("SELECT CONCAT('N', CAST(IFNULL(`{$col}`,0) AS CHAR)) AS amt
        FROM tb_gold_vault_interest WHERE nick = '{$esc}' LIMIT 1");
    $raw = (string)($row['amt'] ?? 'N0');
    if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
        $raw = substr($raw, 1);
    }
    if (strpos($raw, '.') !== false) {
        $raw = explode('.', $raw, 2)[0];
    }
    return gv_냥($raw);
}

function gv_대기이자_추가(string $nick, $amount, $currency = 'point'): bool {
    gv_대기이자_스키마보장();
    gv_통화스키마보장();
    $nick = trim($nick);
    $amt = gv_냥($amount);
    if ($nick === '' || gv_비교($amt, '0') <= 0) {
        return true;
    }
    $esc = addslashes($nick);
    $col = (gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT) ? 'pending_newpoint' : 'pending';
    return @db_query("INSERT INTO tb_gold_vault_interest (nick, pending, pending_newpoint, updated_at)
        VALUES ('{$esc}', " . ($col === 'pending' ? $amt : '0') . ", " . ($col === 'pending_newpoint' ? $amt : '0') . ", NOW())
        ON DUPLICATE KEY UPDATE `{$col}` = `{$col}` + {$amt}, updated_at = NOW()") !== false;
}

/** @return string 실제 차감(수령)액 */
function gv_대기이자_수령차감(string $nick, $currency = 'point'): string {
    gv_대기이자_스키마보장();
    gv_통화스키마보장();
    $nick = trim($nick);
    if ($nick === '') {
        return '0';
    }
    $esc = addslashes($nick);
    $have = gv_대기이자_조회($nick, $currency);
    if (gv_비교($have, '0') <= 0) {
        return '0';
    }
    $col = (gv_통화정상화($currency) === GOLD_VAULT_CURRENCY_NEWPOINT) ? 'pending_newpoint' : 'pending';
    @db_query("UPDATE tb_gold_vault_interest SET `{$col}` = 0, updated_at = NOW() WHERE nick = '{$esc}' LIMIT 1");
    return $have;
}

/**
 * 예치 비율 정상화 (50 또는 100만 허용)
 */
function gv_예치비율정상화($pct): int {
    $pct = (int)$pct;
    return ($pct === 50) ? 50 : 100;
}

/**
 * 보유 게임냥 중 예치할 금액 (50% 또는 100%)
 */
function gv_예치금액_비율($보유, $pct): string {
    $보유 = gv_냥($보유);
    $pct = gv_예치비율정상화($pct);
    if ($pct >= 100) {
        return $보유;
    }
    return gv_비율금액($보유, $pct);
}

/**
 * 보유 잔액 → 자유예치 1건 (50%/100% · 언제든 인출 · 만 24시간마다 1% · 수령대기 적립)
 * @param 'point'|'newpoint'|string $currency
 * @return array{ok:bool,msg:string,amount?:string,amount_disp?:string,bar_idx?:int,term_days?:int}
 */
function gv_전액맡기기(string $nick, $pct = 100, $currency = 'point'): array {
    $nick = trim($nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '닉이 없어요.'];
    }
    $pct = gv_예치비율정상화($pct);
    $currency = gv_통화정상화($currency);
    $라벨 = gv_통화라벨($currency);
    gv_테이블보장();
    if (function_exists('gv_금괴금액컬럼보장')) {
        gv_금괴금액컬럼보장();
    }
    gv_원장스키마보장();

    $pool = gv_예치풀현황();
    if ((int)$pool['remain'] < 1) {
        return [
            'ok' => false,
            'msg' => '전체 예치풀이 가득 찼어요. (최대 ' . number_format((int)$pool['max']) . '개)',
        ];
    }

    $nick_esc = addslashes($nick);
    $보유 = gv_보유잔액($nick, $currency);
    if (gv_비교($보유, '0') <= 0) {
        return ['ok' => false, 'msg' => "맡길 {$라벨}이 없어요."];
    }
    $amount = gv_예치금액_비율($보유, $pct);
    if (gv_비교($amount, '0') <= 0) {
        return ['ok' => false, 'msg' => '맡길 금액이 너무 작아요.'];
    }

    $term = max(1, (int)GOLD_VAULT_CASH_TERM_DAYS);
    $일이자 = gv_일일이자($amount);
    $cur_esc = addslashes($currency);

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $poolNow = gv_예치풀현황();
    if ((int)$poolNow['remain'] < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '전체 예치풀이 가득 찼어요. 잠시 후 다시 시도해주세요.'];
    }

    if (!gv_잔액차감($nick, $currency, $amount)) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => "{$라벨} 차감에 실패했어요. 잔액을 확인해주세요."];
    }

    // unlock_at = NOW() · 자유인출 (만기 개념 없음)
    $okIns = db_query("INSERT INTO tb_gold_bar
        (nick, amount, bar_kind, currency, term_days, status, early, paid_days, interest_paid, last_interest_at, deposited_at, unlock_at)
        VALUES ('{$nick_esc}', {$amount}, 'cash', '{$cur_esc}', {$term}, 0, 0, 0, 0, NULL, NOW(), NOW())");
    if ($okIns === false) {
        gv_잔액지급($nick, $currency, $amount);
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '예치 기록 저장에 실패했어요. 다시 시도해주세요.'];
    }

    $barIdx = 0;
    if ($conn instanceof mysqli) {
        $barIdx = (int)mysqli_insert_id($conn);
        mysqli_commit($conn);
    } else {
        $last = db_select("SELECT idx FROM tb_gold_bar WHERE nick = '{$nick_esc}' AND bar_kind = 'cash' AND IFNULL(currency,'point') = '{$cur_esc}' AND status = 0 ORDER BY idx DESC LIMIT 1");
        $barIdx = (int)($last['idx'] ?? 0);
    }

    if ($barIdx > 0) {
        $chk = db_select("SELECT CAST(amount AS CHAR) AS amount FROM tb_gold_bar WHERE idx = {$barIdx} LIMIT 1");
        if (gv_비교(gv_냥($chk['amount'] ?? 0), $amount) !== 0) {
            @db_query("DELETE FROM tb_gold_bar WHERE idx = {$barIdx} LIMIT 1");
            gv_잔액지급($nick, $currency, $amount);
            return ['ok' => false, 'msg' => '원금 저장이 깨졌어요(컬럼 한도). 관리자에게 문의해주세요.'];
        }
    }

    $표시 = gv_금액표시($amount, $currency);
    $일표시 = gv_금액표시($일이자, $currency);
    gv_원장기록($nick, 'deposit_all', $amount, $barIdx, "{$라벨} 자유예치 {$pct}% · 24시간마다 1% · 하루 +{$일표시}", $currency);
    gv_지급로그('개인금고전액예치', $nick, "{$라벨} 자유예치 {$pct}% #{$barIdx}", 0, '-' . $amount);

    return [
        'ok' => true,
        'msg' => "{$라벨} 자유예치 {$표시}(보유 {$pct}%) 맡겼어요. 언제든 뺄 수 있어요 · 만 24시간마다 +{$일표시}(1%) · 이자는 수령 가능 이자에 쌓여요",
        'amount' => $amount,
        'amount_disp' => $표시,
        'bar_idx' => $barIdx,
        'term_days' => $term,
        'daily_disp' => $일표시,
        'pct' => $pct,
        'currency' => $currency,
    ];
}

/**
 * 보유 잔액 → 7일 잠금예치 (50%/100% · 만기 5% · 중도 원금% 환급)
 * @param 'point'|'newpoint'|string $currency
 * @return array{ok:bool,msg:string,amount?:string,amount_disp?:string,bar_idx?:int,term_days?:int}
 */
function gv_잠금7일맡기기(string $nick, $pct = 100, $currency = 'point'): array {
    $nick = trim($nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '닉이 없어요.'];
    }
    $pct = gv_예치비율정상화($pct);
    $currency = gv_통화정상화($currency);
    $라벨 = gv_통화라벨($currency);
    gv_테이블보장();
    if (function_exists('gv_금괴금액컬럼보장')) {
        gv_금괴금액컬럼보장();
    }
    gv_원장스키마보장();

    $pool = gv_예치풀현황();
    if ((int)$pool['remain'] < 1) {
        return [
            'ok' => false,
            'msg' => '전체 예치풀이 가득 찼어요. (최대 ' . number_format((int)$pool['max']) . '개)',
        ];
    }

    $nick_esc = addslashes($nick);
    $보유 = gv_보유잔액($nick, $currency);
    if (gv_비교($보유, '0') <= 0) {
        return ['ok' => false, 'msg' => "맡길 {$라벨}이 없어요."];
    }
    $amount = gv_예치금액_비율($보유, $pct);
    if (gv_비교($amount, '0') <= 0) {
        return ['ok' => false, 'msg' => '맡길 금액이 너무 작아요.'];
    }

    $term = max(1, (int)GOLD_VAULT_LOCK7_DAYS);
    $만기일수 = gv_만기까지일수($term);
    $일이자 = gv_일일이자($amount);
    $보너스율 = gv_만기추가보상율($term, 0);
    $cur_esc = addslashes($currency);

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $poolNow = gv_예치풀현황();
    if ((int)$poolNow['remain'] < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '전체 예치풀이 가득 찼어요. 잠시 후 다시 시도해주세요.'];
    }

    if (!gv_잔액차감($nick, $currency, $amount)) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => "{$라벨} 차감에 실패했어요. 잔액을 확인해주세요."];
    }

    $okIns = db_query("INSERT INTO tb_gold_bar
        (nick, amount, bar_kind, currency, term_days, status, early, paid_days, interest_paid, last_interest_at, deposited_at, unlock_at)
        VALUES ('{$nick_esc}', {$amount}, 'lock7', '{$cur_esc}', {$term}, 0, 0, 0, 0, NULL, NOW(), DATE_ADD(NOW(), INTERVAL {$만기일수} DAY))");
    if ($okIns === false) {
        gv_잔액지급($nick, $currency, $amount);
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '예치 기록 저장에 실패했어요. 다시 시도해주세요.'];
    }

    $barIdx = 0;
    if ($conn instanceof mysqli) {
        $barIdx = (int)mysqli_insert_id($conn);
        mysqli_commit($conn);
    } else {
        $last = db_select("SELECT idx FROM tb_gold_bar WHERE nick = '{$nick_esc}' AND bar_kind = 'lock7' AND IFNULL(currency,'point') = '{$cur_esc}' AND status = 0 ORDER BY idx DESC LIMIT 1");
        $barIdx = (int)($last['idx'] ?? 0);
    }

    if ($barIdx > 0) {
        $chk = db_select("SELECT CAST(amount AS CHAR) AS amount FROM tb_gold_bar WHERE idx = {$barIdx} LIMIT 1");
        if (gv_비교(gv_냥($chk['amount'] ?? 0), $amount) !== 0) {
            @db_query("DELETE FROM tb_gold_bar WHERE idx = {$barIdx} LIMIT 1");
            gv_잔액지급($nick, $currency, $amount);
            return ['ok' => false, 'msg' => '원금 저장이 깨졌어요(컬럼 한도). 관리자에게 문의해주세요.'];
        }
    }

    $표시 = gv_금액표시($amount, $currency);
    $일표시 = gv_금액표시($일이자, $currency);
    gv_원장기록($nick, 'deposit_lock7', $amount, $barIdx, "{$라벨} {$term}일 잠금 {$pct}% · 즉시 1일분 1% · 만기 +{$보너스율}% · 하루 +{$일표시}", $currency);
    gv_지급로그('개인금고잠금예치', $nick, "{$라벨} 7일잠금 {$pct}% #{$barIdx}", 0, '-' . $amount);

    return [
        'ok' => true,
        'msg' => "{$라벨} 7일 잠금예치 {$표시}(보유 {$pct}%) 맡겼어요 · 즉시 +{$일표시}(1일분) 수령 가능 · 이후 24시간마다 1% · 만기 시 원금 +{$보너스율}% · 처음 2일 잠금 · 중도 시 원금에서 50→40→30→20% 차감 후 환급",
        'amount' => $amount,
        'amount_disp' => $표시,
        'bar_idx' => $barIdx,
        'term_days' => $term,
        'daily_disp' => $일표시,
        'bonus_pct' => $보너스율,
        'pct' => $pct,
        'currency' => $currency,
    ];
}

/**
 * 자유예치(cash) 전부 인출 — 원금만 즉시 지급 · 미수령 이자는 수령대기 이자에 적립
 * @return array{ok:bool,msg:string,count?:int,payout?:string,payout_disp?:string}
 */
function gv_전액인출(string $nick, $currency = 'point'): array {
    $nick = trim($nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '닉이 없어요.'];
    }
    $currency = gv_통화정상화($currency);
    $라벨 = gv_통화라벨($currency);
    $cur_esc = addslashes($currency);
    gv_테이블보장();
    gv_원장스키마보장();
    gv_대기이자_스키마보장();
    $nick_esc = addslashes($nick);
    $now = time();

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, term_days, bar_kind,
            IFNULL(currency, 'point') AS currency,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at,
            UNIX_TIMESTAMP(deposited_at) AS deposited_ts
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0 AND bar_kind = 'cash'
          AND IFNULL(currency, 'point') = '{$cur_esc}'
        ORDER BY idx ASC FOR UPDATE");
    if (!$rs) {
        $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, term_days, bar_kind,
                IFNULL(currency, 'point') AS currency,
                IFNULL(extend_count, 0) AS extend_count, paid_days,
                CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at,
                UNIX_TIMESTAMP(deposited_at) AS deposited_ts
            FROM tb_gold_bar
            WHERE nick = '{$nick_esc}' AND status = 0 AND bar_kind = 'cash'
              AND IFNULL(currency, 'point') = '{$cur_esc}'
            ORDER BY idx ASC");
    }
    $rows = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rows[] = $row;
        }
    }
    if ($rows === []) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => "뺄 {$라벨} 자유예치가 없어요."];
    }

    $count = 0;
    $원금합 = '0';
    $이자합 = '0';
    foreach ($rows as $row) {
        $idx = (int)($row['idx'] ?? 0);
        if ($idx < 1) {
            continue;
        }
        $원금 = gv_냥($row['amount'] ?? 0);
        $c = gv_금괴_미수령계산($row, $now);
        $미수령 = gv_냥($c['claimable'] ?? 0);
        $newPaidDays = max(0, (int)($c['earned_days'] ?? 0));
        $newInterest = gv_더하기(gv_냥($row['interest_paid'] ?? 0), $미수령);
        $days = (int)($c['earned_days'] ?? 0);

        db_query("UPDATE tb_gold_bar
            SET status = 1,
                early = 0,
                paid_days = {$newPaidDays},
                interest_paid = {$newInterest},
                last_interest_at = NOW(),
                withdrawn_at = NOW()
            WHERE idx = {$idx} AND nick = '{$nick_esc}' AND status = 0
            LIMIT 1");
        if (gv_affected() < 1) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '인출에 실패했어요. 다시 시도해주세요.'];
        }
        $원금합 = gv_더하기($원금합, $원금);
        $이자합 = gv_더하기($이자합, $미수령);
        $count++;
        $memo = $days < 1
            ? '원금만 · 24시간 미만 이자 없음'
            : ('원금 인출 · 이자 ' . gv_금액표시($미수령, $currency) . ' → 수령대기');
        gv_원장기록($nick, 'withdraw_cash', $원금, $idx, $memo, $currency);
        if (gv_비교($미수령, '0') > 0) {
            gv_원장기록($nick, 'interest_pending', $미수령, $idx, "예치 {$days}일 이자", $currency);
        }
    }

    if (gv_비교($원금합, '0') > 0) {
        if (!gv_잔액지급($nick, $currency, $원금합)) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => "{$라벨} 지급에 실패했어요."];
        }
    }
    if (gv_비교($이자합, '0') > 0) {
        if (!gv_대기이자_추가($nick, $이자합, $currency)) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '이자 적립에 실패했어요. 다시 시도해주세요.'];
        }
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }

    gv_지급로그('개인금고인출', $nick, $count . '건', 0, $원금합);

    $msg = "자유예치 {$count}건 인출 · 원금 " . gv_금액표시($원금합, $currency);
    if (gv_비교($이자합, '0') > 0) {
        $msg .= ' · 이자 ' . gv_금액표시($이자합, $currency) . '은 수령 가능 이자에 쌓였어요';
    } else {
        $msg .= ' · 24시간 미만이라 이자 없음';
    }

    return [
        'ok' => true,
        'msg' => $msg,
        'count' => $count,
        'payout' => $원금합,
        'payout_disp' => gv_금액표시($원금합, $currency),
        'principal' => $원금합,
        'interest' => $이자합,
        'interest_disp' => gv_금액표시($이자합, $currency),
    ];
}

/**
 * 기존 예치분: 수령 기록을 초기화해 맡긴 1일차부터 적립분이 수령 가능으로 보이게 함 (1회)
 */
function gv_기존금괴_수령대기_보정() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    @db_query("CREATE TABLE IF NOT EXISTS `tb_gold_vault_migrate` (
      `key_name` VARCHAR(64) NOT NULL,
      `done_at` DATETIME NOT NULL,
      PRIMARY KEY (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $row = @db_select("SELECT key_name FROM tb_gold_vault_migrate WHERE key_name = 'reset_claim_from_day1_v1' LIMIT 1");
    if (!empty($row['key_name'])) {
        return;
    }

    // 진행 중 금괴의 수령 카운트만 리셋 → deposited_at 기준 1일차~현재 적립분이 수령 가능액에 반영
    @db_query("UPDATE tb_gold_bar
        SET paid_days = 0,
            interest_paid = 0,
            last_interest_at = NULL
        WHERE status = 0");

    @db_query("INSERT INTO tb_gold_vault_migrate (key_name, done_at)
        VALUES ('reset_claim_from_day1_v1', NOW())");
}

function gv_테이블보장() {
    static $done = false;
    gv_통화스키마보장();
    if ($done) {
        return;
    }
    // 스키마 준비 완료면 CREATE/SHOW/마이그레이션 전부 스킵
    if (gv_스키마준비완료인가()) {
        $done = true;
        return;
    }
    db_query("CREATE TABLE IF NOT EXISTS `tb_gold_bar` (
      `idx` INT NOT NULL AUTO_INCREMENT,
      `nick` VARCHAR(50) NOT NULL,
      `amount` DECIMAL(40,0) NOT NULL DEFAULT 1000000000000000000000,
      `bar_kind` VARCHAR(16) NOT NULL DEFAULT 'gold',
      `currency` VARCHAR(16) NOT NULL DEFAULT 'point',
      `term_days` SMALLINT NOT NULL DEFAULT 14,
      `extend_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
      `status` TINYINT(1) NOT NULL DEFAULT 0,
      `early` TINYINT(1) NOT NULL DEFAULT 0,
      `paid_days` SMALLINT NOT NULL DEFAULT 0,
      `interest_paid` DECIMAL(40,0) NOT NULL DEFAULT 0,
      `last_interest_at` DATETIME DEFAULT NULL,
      `deposited_at` DATETIME NOT NULL,
      `unlock_at` DATETIME NOT NULL,
      `withdrawn_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`idx`),
      KEY `idx_nick_status` (`nick`, `status`),
      KEY `idx_unlock` (`unlock_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    gv_컬럼보장();
    gv_기존금괴_수령대기_보정();
    gv_스키마준비완료_표시();
    $done = true;
}

function gv_코드후보($preferred = '') {
    $list = [];
    $push = function ($v) use (&$list) {
        $v = trim((string)$v);
        if ($v !== '' && !in_array($v, $list, true)) {
            $list[] = $v;
        }
    };
    $push($preferred);
    $push($_GET['code'] ?? '');
    $push($_POST['code'] ?? '');
    $push($_POST['wallet_code'] ?? '');
    $push($_COOKIE[GOLD_BAR_COOKIE] ?? '');
    $push($_COOKIE['wallet_code'] ?? '');
    return $list;
}

function gv_쿠키저장($code) {
    $code = trim((string)$code);
    if ($code === '') {
        return false;
    }
    return setcookie(GOLD_BAR_COOKIE, $code, time() + GOLD_BAR_COOKIE_TTL, '/', '', false, true);
}

function gv_auth($code = '') {
    foreach (gv_코드후보($code) as $c) {
        $esc = addslashes($c);
        $row = db_select("SELECT idx, name,
                CAST(IFNULL(point,0) AS CHAR) AS point,
                CAST(FLOOR(CAST(IFNULL(newpoint,0) AS DECIMAL(65,4))) AS CHAR) AS newpoint,
                status
            FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (empty($row['name'])) {
            continue;
        }
        if ((int)($row['status'] ?? 0) === 1) {
            continue;
        }
        gv_쿠키저장($c);
        return [
            'code'  => $c,
            'nick'  => trim((string)$row['name']),
            'point' => gv_냥($row['point'] ?? 0),
            'newpoint' => gv_냥($row['newpoint'] ?? 0),
            'idx'   => (int)($row['idx'] ?? 0),
        ];
    }
    return null;
}

function gv_회원필수($code = '') {
    $회원 = gv_auth($code);
    if (!$회원) {
        gv_json(['ok' => false, 'msg' => '연구실에서 발급받은 코드 링크로 접속해주세요.']);
    }
    return $회원;
}

function gv_affected() {
    global $conn;
    if ($conn instanceof mysqli) {
        return (int)mysqli_affected_rows($conn);
    }
    return 0;
}

function gv_만기까지일수($term_days) {
    $term = max(1, (int)$term_days);
    return max(0, $term - 1);
}

/** 자유예치 SQL — unlock_at 이 즉시라 만기 집계에서 제외 */
function gv_자유예치_sql(): string {
    return "IFNULL(bar_kind, 'gold') IN ('cash','vault','전액','전액예치','자유')";
}

/** unlock_at 이 지났으면 만기 (자유예치 제외 · MySQL NOW() 우선) */
function gv_만기시각도래했나(array $bar, $now = null): bool {
    $now = $now ?? time();
    if (gv_행종류($bar) === 'cash') {
        return false;
    }
    if (array_key_exists('unlock_ready', $bar)) {
        return (int)$bar['unlock_ready'] === 1;
    }
    $unlock = trim((string)($bar['unlock_at'] ?? ''));
    if ($unlock === '' || strpos($unlock, '0000-00-00') === 0) {
        return false;
    }
    $ts = strtotime($unlock);
    return $ts > 0 && $ts <= $now;
}

/** 맡긴 시점부터 경과 일차 (당일=1) */
function gv_경과일차($deposited_at, $now = null) {
    if (is_array($deposited_at)) {
        $start = gv_예치타임스탬프($deposited_at);
    } else {
        $start = gv_예치타임스탬프(['deposited_at' => $deposited_at]);
    }
    if ($start <= 0) {
        return 0;
    }
    $now = $now ?? time();
    return max(0, (int)floor(($now - $start) / 86400)) + 1;
}

/**
 * 금괴 1건 적립/미수령 계산 (지급 없음)
 * 자유예치: 만 24시간마다 원금 1% · 24시간 미만 0
 * 7일잠금: 맡긴 즉시 1일분(당일=1) · 이후 만 24시간마다 · 최대 7일
 * @return array{
 *   earned_days:int,paid_days:int,claim_days:int,claimable:string,
 *   earned:string,interest_paid:string,daily:string,matured:bool,next_ts:?int,
 *   is_cash?:bool,is_lock7?:bool
 * }
 */
function gv_금괴_미수령계산(array $bar, $now = null) {
    $now = $now ?? time();
    $kind = gv_행종류($bar);
    $amount = gv_냥($bar['amount'] ?? GOLD_BAR_AMOUNT);
    $interestPaid = gv_냥($bar['interest_paid'] ?? 0);
    $depTs = gv_예치타임스탬프($bar);

    // ── 자유예치: 만 24시간마다 1% (기간 상한 없음 · 24시간 미만 0) ──
    if ($kind === 'cash') {
        $daily = gv_일일이자($amount);
        $days = ($depTs > 0) ? gv_예치경과일수_만하루($depTs, $now) : 0;
        $earned = gv_예치_일이자($amount, $days);
        if (gv_비교($interestPaid, $earned) > 0) {
            $interestPaid = $earned;
        }
        $claimable = gv_빼기($earned, $interestPaid);
        $paidDays = max(0, (int)($bar['paid_days'] ?? 0));
        if ($paidDays > $days) {
            $paidDays = $days;
        }
        $claimDays = max(0, $days - $paidDays);
        if (gv_비교($claimable, '0') > 0 && $claimDays < 1 && $days >= 1) {
            $claimDays = 1;
        }
        if (gv_비교($claimable, '0') <= 0) {
            $claimDays = 0;
        }
        $next_ts = null;
        if ($depTs > 0) {
            $next_ts = $depTs + (($days + 1) * 86400);
            if ($next_ts <= $now) {
                $next_ts = $now + 1;
            }
        }
        return [
            'earned_days' => $days,
            'paid_days' => $paidDays,
            'claim_days' => $claimDays,
            'claimable' => $claimable,
            'earned' => $earned,
            'interest_paid' => $interestPaid,
            'daily' => $daily,
            'matured' => false,
            'next_ts' => $next_ts,
            'amount' => $amount,
            'term' => max(1, (int)($bar['term_days'] ?? GOLD_VAULT_CASH_TERM_DAYS)),
            'extend_count' => 0,
            'effective_term' => 0,
            'can_extend' => false,
            'bonus_pct' => 0,
            'is_cash' => true,
            'is_lock7' => false,
        ];
    }

    // ── 7일잠금: 맡긴 즉시 1일분 · 이후 24시간마다 · 최대 7일 ──
    if ($kind === 'lock7') {
        $term = max(1, (int)($bar['term_days'] ?? GOLD_VAULT_LOCK7_DAYS));
        $daily = gv_일일이자($amount);
        // 당일=1 (맡긴 즉시 이자) — 자유예치의 만24시간 floor 와 다름
        $days = ($depTs > 0) ? gv_경과일차($bar, $now) : 0;
        $earnedDays = max(0, min($term, $days));
        $paidDays = max(0, min($earnedDays, (int)($bar['paid_days'] ?? 0)));
        $claimDays = max(0, $earnedDays - $paidDays);
        $claimable = gv_예치_일이자($amount, $claimDays);
        $earned = gv_예치_일이자($amount, $earnedDays);
        $matured = ($earnedDays >= $term) || gv_만기시각도래했나($bar, $now);
        $next_ts = null;
        if (!$matured && $depTs > 0) {
            // 다음 일자 = 맡긴 시각 + earnedDays×24h (1일차 직후 → +24h)
            $next_ts = $depTs + ($earnedDays * 86400);
            if ($next_ts <= $now) {
                $next_ts = $now + 1;
            }
        }
        return [
            'earned_days' => $earnedDays,
            'paid_days' => $paidDays,
            'claim_days' => $claimDays,
            'claimable' => $claimable,
            'earned' => $earned,
            'interest_paid' => $interestPaid,
            'daily' => $daily,
            'matured' => $matured,
            'next_ts' => $next_ts,
            'amount' => $amount,
            'term' => $term,
            'extend_count' => 0,
            'effective_term' => $term,
            'can_extend' => false,
            'bonus_pct' => gv_만기추가보상율($term, 0),
            'is_cash' => false,
            'is_lock7' => true,
        ];
    }

    $rawTerm = max(1, (int)($bar['term_days'] ?? 14));
    $term = gv_기간정상화($rawTerm);
    if ($term < 1) {
        // 신규 허용 목록에 없어도 기존 예치(3·5일 등)는 원기간 유지
        $term = $rawTerm;
    }
    $extend = max(0, min(1, (int)($bar['extend_count'] ?? 0)));
    $effectiveTerm = gv_유효기간일수($term, $extend);
    $daily = gv_일일이자($amount);
    $paidDays = max(0, min($effectiveTerm, (int)($bar['paid_days'] ?? 0)));
    $elapsed = gv_경과일차($bar, $now);
    $earnedDays = max(0, min($effectiveTerm, $elapsed));
    if ($paidDays > $earnedDays) {
        $paidDays = $earnedDays;
    }
    $claimDays = max(0, $earnedDays - $paidDays);
    $claimable = gv_곱하기($daily, $claimDays);
    $earned = gv_곱하기($daily, $earnedDays);
    $matured = ($earnedDays >= $effectiveTerm) || gv_만기시각도래했나($bar, $now);
    // 30일 기본 만기(연장 전)일 때만 연장 가능
    $canExtend = ($term === 30 && $extend < 1 && $matured);

    $next_ts = null;
    if (!$matured && $depTs > 0) {
        // 다음 적립 시각 = 맡긴 시각 + earnedDays×24h
        $next_ts = $depTs + ($earnedDays * 86400);
        if ($next_ts <= $now) {
            $next_ts = $now + 1;
        }
    }

    return [
        'earned_days' => $earnedDays,
        'paid_days' => $paidDays,
        'claim_days' => $claimDays,
        'claimable' => $claimable,
        'earned' => $earned,
        'interest_paid' => $interestPaid,
        'daily' => $daily,
        'matured' => $matured,
        'next_ts' => $next_ts,
        'amount' => $amount,
        'term' => $term,
        'extend_count' => $extend,
        'effective_term' => $effectiveTerm,
        'can_extend' => $canExtend,
        'bonus_pct' => gv_만기추가보상율($term, $extend),
        'is_cash' => false,
        'is_lock7' => false,
    ];
}

/**
 * 30일 만기 금괴 1회 연장 (+30일 · 만기 보상 10%→15%)
 * @return array{ok:bool,msg:string}
 */
function gv_금괴연장($nick, $bar_idx) {
    $nick = trim((string)$nick);
    $bar_idx = (int)$bar_idx;
    if ($nick === '' || $bar_idx < 1) {
        return ['ok' => false, 'msg' => '금괴를 선택해주세요.'];
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $bar = db_select("SELECT idx, nick, CAST(amount AS CHAR) AS amount, term_days,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at, status
        FROM tb_gold_bar
        WHERE idx = {$bar_idx} AND nick = '{$nick_esc}' AND status = 0
        LIMIT 1 FOR UPDATE");
    if (empty($bar['idx'])) {
        $bar = db_select("SELECT idx, nick, CAST(amount AS CHAR) AS amount, term_days,
                IFNULL(extend_count, 0) AS extend_count, paid_days,
                CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at, status
            FROM tb_gold_bar
            WHERE idx = {$bar_idx} AND nick = '{$nick_esc}' AND status = 0
            LIMIT 1");
    }
    if (empty($bar['idx'])) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '보관 중인 금괴를 찾을 수 없어요.'];
    }

    $calc = gv_금괴_미수령계산($bar);
    if ((int)$calc['term'] !== 30) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '30일 상품만 연장할 수 있어요.'];
    }
    if ((int)$calc['extend_count'] >= 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '이미 한 번 연장했어요.'];
    }
    if (empty($calc['can_extend']) || empty($calc['matured'])) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '만기된 30일 금괴만 연장할 수 있어요.'];
    }

    $newEffective = gv_유효기간일수(30, 1);
    $unlockDays = gv_만기까지일수($newEffective);
    db_query("UPDATE tb_gold_bar
        SET extend_count = 1,
            unlock_at = DATE_ADD(deposited_at, INTERVAL {$unlockDays} DAY)
        WHERE idx = {$bar_idx} AND nick = '{$nick_esc}' AND status = 0
          AND IFNULL(extend_count, 0) = 0
        LIMIT 1");
    if (gv_affected() < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '연장에 실패했어요. 다시 시도해주세요.'];
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }
    gv_지급로그('개인금고연장', $nick, '30일→60일 · 만기보상15%', 0, 0);

    return [
        'ok' => true,
        'msg' => '30일 연장했어요. 한 달 더 적립 후 만기해지하면 추가보상 15%(10%+5%)를 받아요.',
    ];
}

if (!defined('GOLD_VAULT_PAGE_SIZE')) {
    define('GOLD_VAULT_PAGE_SIZE', 40);
}

/** 요청 단위 캐시 비우기 (예치/해지 후 같은 요청에서 재조회 시) */
function gv_요청캐시초기화() {
    $GLOBALS['_gv_claim_cache'] = [];
    $GLOBALS['_gv_list_meta_cache'] = [];
    $GLOBALS['_gv_pool_cache'] = null;
    $GLOBALS['_gv_discount_cache'] = [];
    if (function_exists('gv_본냥미션_캐시초기화')) {
        gv_본냥미션_캐시초기화();
    }
    if (function_exists('gv_겜냥미션_캐시초기화')) {
        gv_겜냥미션_캐시초기화();
    }
    if (function_exists('apcu_delete')) {
        @apcu_delete('gv_pool_snap_v2');
        @apcu_delete('gv_pool_snap_v1');
    }
}

/**
 * 전체 미수령 이자 요약 (표시용) — 요청당 1회만 전체 스캔
 * @return array{pending:string,pending_disp:string,can_claim:bool,daily:string,daily_disp:string,earned:string,earned_disp:string,claimed:string,claimed_disp:string}
 */
function gv_이자수령상태($nick, $currency = 'point') {
    $nick = trim((string)$nick);
    $currency = gv_통화정상화($currency);
    if ($nick === '') {
        return gv_이자수령상태_빈값();
    }
    if (!isset($GLOBALS['_gv_claim_cache']) || !is_array($GLOBALS['_gv_claim_cache'])) {
        $GLOBALS['_gv_claim_cache'] = [];
    }
    $cacheKey = $nick . '|' . $currency;
    if (isset($GLOBALS['_gv_claim_cache'][$cacheKey])) {
        return $GLOBALS['_gv_claim_cache'][$cacheKey];
    }

    $empty = gv_이자수령상태_빈값();
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $cur_esc = addslashes($currency);
    $pending = '0';
    $daily = '0';
    $earned = '0';
    $claimed = '0';
    $claimDays = 0;
    $barCount = 0;
    $ready = 0;
    $locked = 0;
    $matureInterest = '0';
    $minDays = null;
    $maxDays = 0;
    $now = time();
    $silverBonusMax = max(0, (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS);
    $silverPending = '0';
    $silverBonusBars = 0;
    $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, IFNULL(bar_kind, 'gold') AS bar_kind,
            IFNULL(currency, 'point') AS currency, term_days,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at,
            UNIX_TIMESTAMP(deposited_at) AS deposited_ts,
            (unlock_at <= NOW()) AS unlock_ready
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
          AND IFNULL(currency, 'point') = '{$cur_esc}'
        ORDER BY idx ASC");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $c = gv_금괴_미수령계산($row, $now);
            $pending = gv_더하기($pending, $c['claimable']);
            $daily = gv_더하기($daily, $c['daily']);
            $earned = gv_더하기($earned, $c['earned']);
            $claimed = gv_더하기($claimed, $c['interest_paid']);
            $claimDays += (int)$c['claim_days'];
            $barCount++;
            if (!empty($c['matured'])) {
                $ready++;
            } else {
                $locked++;
            }
            // 자유예치는 기간 상한 없음 → 현재까지 적립분을 기간이자 합에 반영
            if (!empty($c['is_cash'])) {
                $matureInterest = gv_더하기($matureInterest, $c['earned']);
            } else {
                $matureInterest = gv_더하기($matureInterest, gv_총이자($c['amount'], max(1, (int)$c['effective_term'])));
            }
            $d = (int)$c['earned_days'];
            if ($minDays === null || $d < $minDays) {
                $minDays = $d;
            }
            if ($d > $maxDays) {
                $maxDays = $d;
            }
            // 은괴 수령보너스: 재스캔 없이 한 번에 집계 (최대 N개)
            if ($silverBonusBars < $silverBonusMax
                && gv_행종류($row) === 'silver'
                && gv_비교($c['claimable'], '0') > 0
            ) {
                $silverPending = gv_더하기($silverPending, $c['claimable']);
                $silverBonusBars++;
            }
        }
    }
    if ($minDays === null) {
        $minDays = 0;
    }
    if ($barCount < 1) {
        $daysLabel = '맡긴 0일';
    } elseif ($minDays === $maxDays) {
        $daysLabel = '맡긴 ' . $maxDays . '일';
    } else {
        $daysLabel = '맡긴 ' . $minDays . '~' . $maxDays . '일';
    }
    $unitDisp = ($currency === GOLD_VAULT_CURRENCY_NEWPOINT)
        ? gv_금액표시(GOLD_BAR_DAILY_INTEREST, $currency)
        : gv_경표시(GOLD_BAR_DAILY_INTEREST);
    $unitCountLabel = '0';
    if (function_exists('bcdiv') && gv_비교($daily, '0') > 0) {
        $raw = bcdiv($daily, GOLD_BAR_DAILY_INTEREST, 1);
        $unitCountLabel = rtrim(rtrim($raw, '0'), '.');
        if ($unitCountLabel === '') {
            $unitCountLabel = '0';
        }
    } elseif (gv_비교($daily, '0') > 0) {
        $unitCount = 0;
        $tmp = '0';
        while ($unitCount < 100000) {
            $next = gv_더하기($tmp, GOLD_BAR_DAILY_INTEREST);
            if (gv_비교($next, $daily) > 0) {
                break;
            }
            $tmp = $next;
            $unitCount++;
        }
        $unitCountLabel = (string)$unitCount;
    }
    $dailyCalc = ($currency === GOLD_VAULT_CURRENCY_NEWPOINT)
        ? ('하루 +' . gv_금액표시($daily, $currency))
        : ('금괴환산 ' . $unitCountLabel . '개 × ' . $unitDisp . ' = 하루 +' . gv_경표시($daily));
    $silverBonus = gv_은괴수령보너스($silverPending);
    $대기이자 = gv_대기이자_조회($nick, $currency);
    $pending = gv_더하기($pending, $대기이자);
    $payoutTotal = gv_더하기($pending, $silverBonus);

    $out = [
        'pending' => $pending,
        'pending_disp' => gv_금액표시($pending, $currency),
        'pending_vault' => $대기이자,
        'pending_vault_disp' => gv_금액표시($대기이자, $currency),
        'can_claim' => gv_비교($payoutTotal, '0') > 0,
        'daily' => $daily,
        'daily_disp' => gv_금액표시($daily, $currency),
        'earned' => $earned,
        'earned_disp' => gv_금액표시($earned, $currency),
        'claimed' => $claimed,
        'claimed_disp' => gv_금액표시($claimed, $currency),
        'claim_days' => $claimDays,
        'bar_count' => $barCount,
        'ready' => $ready,
        'locked' => $locked,
        'mature_interest' => $matureInterest,
        'mature_interest_disp' => gv_금액표시($matureInterest, $currency),
        'min_days' => (int)$minDays,
        'max_days' => (int)$maxDays,
        'days_label' => $daysLabel,
        'daily_calc_label' => $dailyCalc,
        'unit_daily_disp' => $unitDisp,
        'silver_pending' => $silverPending,
        'silver_pending_disp' => gv_금액표시($silverPending, $currency),
        'silver_bonus' => $silverBonus,
        'silver_bonus_disp' => gv_금액표시($silverBonus, $currency),
        'silver_bonus_pct' => (int)GOLD_SILVER_CLAIM_BONUS_PCT,
        'silver_bonus_max_bars' => (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS,
        'silver_bonus_bars' => (int)$silverBonusBars,
        'payout_total' => $payoutTotal,
        'payout_total_disp' => gv_금액표시($payoutTotal, $currency),
    ];
    $out['currency'] = $currency;
    $out['currency_label'] = gv_통화라벨($currency);
    $GLOBALS['_gv_claim_cache'][$cacheKey] = $out;
    return $out;
}

function gv_이자수령상태_빈값(): array {
    return [
        'pending' => '0',
        'pending_disp' => '0',
        'pending_vault' => '0',
        'pending_vault_disp' => '0',
        'can_claim' => false,
        'daily' => '0',
        'daily_disp' => '0',
        'earned' => '0',
        'earned_disp' => '0',
        'claimed' => '0',
        'claimed_disp' => '0',
        'claim_days' => 0,
        'bar_count' => 0,
        'ready' => 0,
        'locked' => 0,
        'mature_interest' => '0',
        'mature_interest_disp' => '0',
        'min_days' => 0,
        'max_days' => 0,
        'days_label' => '맡긴 0일',
        'daily_calc_label' => '금괴환산 0개 × ' . gv_경표시(GOLD_BAR_DAILY_INTEREST) . ' = 하루 +0',
        'unit_daily_disp' => gv_경표시(GOLD_BAR_DAILY_INTEREST),
        'silver_pending' => '0',
        'silver_pending_disp' => '0',
        'silver_bonus' => '0',
        'silver_bonus_disp' => '0',
        'silver_bonus_pct' => (int)GOLD_SILVER_CLAIM_BONUS_PCT,
        'silver_bonus_max_bars' => (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS,
        'silver_bonus_bars' => 0,
        'payout_total' => '0',
        'payout_total_disp' => '0',
    ];
}

/**
 * 미수령 이자 전부 해당 통화로 수령
 * · 예치중 금괴/잠금 이자 + 자유예치 인출 시 쌓인 대기이자
 * · 은괴는 미수령 이자의 GOLD_SILVER_CLAIM_BONUS_PCT% 보너스
 * @param 'point'|'newpoint'|string $currency
 * @return array{ok:bool,msg:string,paid?:string,paid_disp?:string,bonus?:string,bonus_disp?:string}
 */
function gv_이자수령($nick, $currency = 'point') {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '회원 정보가 없어요.'];
    }
    $currency = gv_통화정상화($currency);
    $라벨 = gv_통화라벨($currency);
    $cur_esc = addslashes($currency);
    gv_테이블보장();
    gv_대기이자_스키마보장();
    $nick_esc = addslashes($nick);
    $now = time();
    $total = '0';
    $rows = [];
    $claimedRows = [];

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $rs = db_query("SELECT idx, nick, CAST(amount AS CHAR) AS amount, IFNULL(bar_kind, 'gold') AS bar_kind,
            IFNULL(currency, 'point') AS currency, term_days,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at,
            UNIX_TIMESTAMP(deposited_at) AS deposited_ts
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
          AND IFNULL(currency, 'point') = '{$cur_esc}'
        ORDER BY idx ASC FOR UPDATE");
    // FOR UPDATE may fail without InnoDB/tx — fallback without it
    if (!$rs) {
        $rs = db_query("SELECT idx, nick, CAST(amount AS CHAR) AS amount, IFNULL(bar_kind, 'gold') AS bar_kind,
                IFNULL(currency, 'point') AS currency, term_days,
                IFNULL(extend_count, 0) AS extend_count, paid_days,
                CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at,
                UNIX_TIMESTAMP(deposited_at) AS deposited_ts
            FROM tb_gold_bar
            WHERE nick = '{$nick_esc}' AND status = 0
              AND IFNULL(currency, 'point') = '{$cur_esc}'
            ORDER BY idx ASC");
    }
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rows[] = $row;
        }
    }

    foreach ($rows as $row) {
        $c = gv_금괴_미수령계산($row, $now);
        $isCash = !empty($c['is_cash']) || gv_행종류($row) === 'cash';
        if (gv_비교($c['claimable'], '0') <= 0) {
            continue;
        }
        if (!$isCash && (int)$c['claim_days'] < 1) {
            continue;
        }
        $idx = (int)$row['idx'];
        $newPaidDays = (int)$c['earned_days'];
        $newInterest = gv_더하기($c['interest_paid'], $c['claimable']);
        if ($isCash) {
            // 자유예치: unlock 유지 · paid_days = 경과 시간
            db_query("UPDATE tb_gold_bar
                SET paid_days = {$newPaidDays},
                    interest_paid = {$newInterest},
                    last_interest_at = NOW()
                WHERE idx = {$idx} AND nick = '{$nick_esc}' AND status = 0
                LIMIT 1");
        } else {
            $unlockSql = !empty($c['matured'])
                ? 'NOW()'
                : ("DATE_ADD(deposited_at, INTERVAL " . gv_만기까지일수($c['term']) . " DAY)");
            db_query("UPDATE tb_gold_bar
                SET paid_days = {$newPaidDays},
                    interest_paid = {$newInterest},
                    last_interest_at = NOW(),
                    unlock_at = {$unlockSql}
                WHERE idx = {$idx} AND nick = '{$nick_esc}' AND status = 0
                  AND paid_days = " . (int)$c['paid_days'] . "
                LIMIT 1");
        }
        if (gv_affected() < 1) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '수령에 실패했어요. 다시 시도해주세요.'];
        }
        $total = gv_더하기($total, $c['claimable']);
        if (!$isCash) {
            $claimedRows[] = $row;
        }
    }

    $대기수령 = gv_대기이자_조회($nick, $currency);
    $total = gv_더하기($total, $대기수령);

    if (gv_비교($total, '0') <= 0) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '수령할 이자가 없어요.'];
    }

    $bonusTarget = gv_은괴수령보너스_대상($claimedRows, $now);
    $bonus = gv_은괴수령보너스($bonusTarget['claimable']);
    $payout = gv_더하기($total, $bonus);

    if (!gv_잔액지급($nick, $currency, $payout)) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => "{$라벨} 지급에 실패했어요."];
    }
    if (gv_비교($대기수령, '0') > 0) {
        gv_대기이자_수령차감($nick, $currency);
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }
    gv_지급로그('개인금고이자', $nick, '', 0, $payout);
    if (gv_비교($대기수령, '0') > 0) {
        gv_원장기록($nick, 'claim', $대기수령, 0, '자유예치 대기이자 수령', $currency);
    }

    $msg = $라벨 . ' 이자 ' . gv_금액표시($total, $currency) . '을 수령했어요.';
    if (gv_비교($bonus, '0') > 0) {
        $msg .= ' · 은괴 수령보너스 ' . (int)GOLD_SILVER_CLAIM_BONUS_PCT . '% +' . gv_금액표시($bonus, $currency);
        $maxBars = (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS;
        if ((int)$bonusTarget['bars'] >= $maxBars) {
            $msg .= ' (최대 ' . $maxBars . '개분)';
        }
    }

    return [
        'ok' => true,
        'msg' => $msg,
        'paid' => $payout,
        'paid_disp' => gv_금액표시($payout, $currency),
        'interest' => $total,
        'interest_disp' => gv_금액표시($total, $currency),
        'bonus' => $bonus,
        'bonus_disp' => gv_금액표시($bonus, $currency),
        'bonus_pct' => (int)GOLD_SILVER_CLAIM_BONUS_PCT,
        'bonus_bars' => (int)$bonusTarget['bars'],
        'bonus_max_bars' => (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS,
    ];
}

/**
 * 다오·피치·민호: 예치 중 금괴 전부 회수
 * 3·7·14·30일 등 기간 무관 · 원금 전액 + 미수령 이자 · 중도 수수료 없음
 * @return array{ok:bool,msg:string,count?:int,payout?:string,payout_disp?:string}
 */
function gv_금괴전부회수($nick) {
    $nick = trim((string)$nick);
    if ($nick === '' || !gv_회수권한인가($nick)) {
        return ['ok' => false, 'msg' => '회수 권한이 없어요.'];
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $now = time();

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    // 기간(term_days) 조건 없음 — 예치중(status=0) 전부
    $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, term_days,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
        ORDER BY idx ASC FOR UPDATE");
    if (!$rs) {
        $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, term_days,
                IFNULL(extend_count, 0) AS extend_count, paid_days,
                CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at
            FROM tb_gold_bar
            WHERE nick = '{$nick_esc}' AND status = 0
            ORDER BY idx ASC");
    }
    $rows = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rows[] = $row;
        }
    }
    if ($rows === []) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '회수할 금괴가 없어요.'];
    }

    $count = 0;
    $원금합 = '0';
    $이자합 = '0';
    $termCounts = [];
    foreach ($rows as $row) {
        $idx = (int)($row['idx'] ?? 0);
        if ($idx < 1) {
            continue;
        }
        // 원금은 DB amount 그대로 (기간과 무관)
        $원금 = gv_냥($row['amount'] ?? GOLD_BAR_AMOUNT);
        $c = gv_금괴_미수령계산($row, $now);
        $미수령 = gv_냥($c['claimable'] ?? 0);
        $newPaidDays = max(0, (int)$c['earned_days']);
        $newInterest = gv_더하기(gv_냥($row['interest_paid'] ?? 0), $미수령);
        $termLabel = max(1, (int)($row['term_days'] ?? $c['term'] ?? 7));

        db_query("UPDATE tb_gold_bar
            SET status = 1,
                early = 0,
                paid_days = {$newPaidDays},
                interest_paid = {$newInterest},
                last_interest_at = NOW(),
                withdrawn_at = NOW()
            WHERE idx = {$idx} AND nick = '{$nick_esc}' AND status = 0
            LIMIT 1");
        if (gv_affected() < 1) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '회수에 실패했어요. 다시 시도해주세요.'];
        }
        $원금합 = gv_더하기($원금합, $원금);
        $이자합 = gv_더하기($이자합, $미수령);
        $count++;
        if (!isset($termCounts[$termLabel])) {
            $termCounts[$termLabel] = 0;
        }
        $termCounts[$termLabel]++;
    }

    if ($count < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '회수할 금괴가 없어요.'];
    }

    $총지급 = gv_더하기($원금합, $이자합);
    if (gv_비교($총지급, '0') > 0) {
        db_query("UPDATE tb_member SET point = point + {$총지급} WHERE name = '{$nick_esc}' LIMIT 1");
        if (gv_affected() < 1) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '게임냥 지급에 실패했어요.'];
        }
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }
    if (gv_비교($이자합, '0') > 0) {
        gv_지급로그('개인금고이자', $nick, '전부회수', 0, $이자합);
    }
    gv_지급로그('개인금고회수', $nick, $count . '개', 0, $원금합);

    ksort($termCounts, SORT_NUMERIC);
    $termBits = [];
    foreach ($termCounts as $t => $n) {
        $termBits[] = $t . '일 ' . $n . '개';
    }
    $msg = "금괴 {$count}개 전부 회수했어요";
    if ($termBits !== []) {
        $msg .= ' (' . implode(' · ', $termBits) . ')';
    }
    $msg .= '. 원금 ' . gv_경표시($원금합);
    if (gv_비교($이자합, '0') > 0) {
        $msg .= ' · 미수령 이자 ' . gv_경표시($이자합);
    }
    $msg .= ' 지급 · 수수료 없음';

    return [
        'ok' => true,
        'msg' => $msg,
        'count' => $count,
        'payout' => $총지급,
        'payout_disp' => gv_경표시($총지급),
    ];
}

/**
 * 단건 금괴 해지 처리 (이미 조회된 status=0 행)
 * @param 'early'|'withdraw' $mode
 * @return array{ok:bool,msg?:string,payout?:string,early?:bool,matured?:bool}
 */
function gv_금괴단건해지_행($nick, array $bar, $mode) {
    $nick = trim((string)$nick);
    $mode = ($mode === 'early') ? 'early' : 'withdraw';
    $idx = (int)($bar['idx'] ?? 0);
    if ($nick === '' || $idx < 1) {
        return ['ok' => false, 'msg' => '금괴를 선택해주세요.'];
    }

    $nick_esc = addslashes($nick);
    $calc = gv_금괴_미수령계산($bar);
    $금액원금 = gv_냥($bar['amount'] ?? $calc['amount']);
    $term = (int)$calc['term'];
    $extend = (int)($calc['extend_count'] ?? 0);
    $effTerm = (int)($calc['effective_term'] ?? $term);
    $earned = $calc['earned'];
    $matured = !empty($calc['matured']);
    $is_early = ($mode === 'early');
    $isCash = (gv_행종류($bar) === 'cash');
    $isLock7 = (gv_행종류($bar) === 'lock7');
    // 자유예치(cash) · 회수권한: 수수료/잠금 없이 인출
    $무료회수 = gv_회수권한인가($nick) || $isCash;

    if (!$무료회수) {
        if ($is_early && $matured) {
            return ['ok' => false, 'msg' => '이미 만기예요. 「일괄만기해지」를 이용해주세요.'];
        }
        if (!$is_early && !$matured) {
            $lock = gv_중도잠금정보($bar['deposited_at'] ?? '', time(), $term, $isLock7 ? 'lock7' : '');
            $lockDays = (int)($lock['lock_days'] ?? gv_중도잠금일수($term));
            $left = max(0, $effTerm - (int)$calc['earned_days']);
            if ($isLock7) {
                $keep = gv_잠금7_중도환급비율($bar);
                $pen = max(0, 100 - $keep);
                return ['ok' => false, 'msg' => "아직 {$left}일 남았어요. 중도해지 시 원금에서 {$pen}% 차감(환급 {$keep}%)돼요. (잠금 {$lockDays}일 · 만기 시 +5%)"];
            }
            if (!empty($lock['locked'])) {
                $keep = gv_강제중도환급비율($bar['deposited_at'] ?? '');
                return ['ok' => false, 'msg' => "아직 {$left}일 남았어요. 잠금 {$lockDays}일 중에는 강제해지 시 원금 {$keep}%만 환급돼요."];
            }
            return ['ok' => false, 'msg' => "아직 {$left}일 남았어요. 중도해지하면 적립 이자의 " . GOLD_BAR_EARLY_FEE_MULT . "배가 수수료로 빠져요."];
        }
    }

    $선수수령 = gv_냥($calc['claimable'] ?? 0);
    $실제중도 = $is_early && !$무료회수;
    $강제중도 = false;
    $강제환급율 = 0;
    if ($무료회수) {
        $수수료 = '0';
        $원금지급 = $금액원금;
        $is_early = false;
    } elseif ($실제중도) {
        $depAt = $bar['deposited_at'] ?? '';
        if ($isLock7) {
            // 7일잠금: 만기 전 중도는 원금에서 위약금 50→40→30→20% 차감 후 환급
            $강제중도 = true;
            $강제환급율 = gv_잠금7_중도환급비율($bar);
            $수수료 = gv_강제중도수수료($금액원금, $bar, null, 'lock7');
            $원금지급 = gv_강제중도수령액($금액원금, $bar, null, 'lock7');
        } else {
            $lock = gv_중도잠금정보($depAt, time(), $term, '');
            $강제중도 = !empty($lock['locked']);
            if ($강제중도) {
                $강제환급율 = gv_강제중도환급비율($depAt);
                $수수료 = gv_강제중도수수료($금액원금, $depAt);
                $원금지급 = gv_강제중도수령액($금액원금, $depAt);
            } else {
                $수수료 = gv_중도수수료($earned);
                $원금지급 = gv_중도수령액($금액원금, $earned);
            }
        }
    } else {
        $수수료 = '0';
        $원금지급 = gv_만기수령액($금액원금, $term);
    }
    // 만기해지: 7일잠금은 원금 +5% 추가이자(만기보상)
    $만기보너스 = (!$실제중도 && $matured && !$isCash) ? gv_만기추가보상($금액원금, $term, $extend) : '0';
    $만기보너스율 = (!$실제중도 && $matured && !$isCash) ? gv_만기추가보상율($term, $extend) : 0;
    // 자유예치·7일잠금: 이자는 수령대기 적립 · 원금(±만기보너스)만 즉시 지급
    $이자대기적립 = $isCash || $isLock7;
    if ($이자대기적립) {
        $총지급 = $isCash ? $원금지급 : gv_더하기($원금지급, $만기보너스);
    } else {
        $총지급 = gv_더하기(gv_더하기($원금지급, $선수수령), $만기보너스);
    }

    $newPaidDays = (int)$calc['earned_days'];
    $newInterest = gv_더하기($calc['interest_paid'], $선수수령);
    $earlyFlag = $실제중도 ? 1 : 0;
    db_query("UPDATE tb_gold_bar
        SET status = 1,
            early = {$earlyFlag},
            paid_days = {$newPaidDays},
            interest_paid = {$newInterest},
            last_interest_at = NOW(),
            withdrawn_at = NOW()
        WHERE idx = {$idx} AND nick = '{$nick_esc}' AND status = 0
        LIMIT 1");
    if (gv_affected() < 1) {
        return ['ok' => false, 'msg' => '환전에 실패했어요. 다시 시도해주세요.'];
    }

    if (!$무료회수 && !$실제중도 && $matured && gv_비교($원금지급, '0') > 0) {
        if (!gv_만기원금누적_추가($원금지급)) {
            return ['ok' => false, 'msg' => '만기 원금 누적에 실패했어요. 다시 시도해주세요.'];
        }
        if (!gv_만기원금잔액_추가($nick, $원금지급)) {
            return ['ok' => false, 'msg' => '만기 원금 잔액 반영에 실패했어요. 다시 시도해주세요.'];
        }
    }

    $currency = gv_행통화($bar);
    if (gv_비교($총지급, '0') > 0) {
        if (!gv_잔액지급($nick, $currency, $총지급)) {
            return ['ok' => false, 'msg' => gv_통화라벨($currency) . ' 지급에 실패했어요.'];
        }
    }
    if ($이자대기적립 && gv_비교($선수수령, '0') > 0) {
        gv_대기이자_추가($nick, $선수수령, $currency);
        gv_원장기록($nick, 'interest_pending', $선수수령, $idx, gv_통화라벨($currency) . ' ' . ($isCash ? '자유예치' : '7일잠금') . ' 이자 → 수령대기', $currency);
    }

    if (!$이자대기적립 && gv_비교($선수수령, '0') > 0) {
        gv_지급로그('개인금고이자', $nick, $무료회수 ? '권한회수' : '', 0, $선수수령);
    }
    if (gv_비교($만기보너스, '0') > 0) {
        gv_지급로그('개인금고만기보너스', $nick, $term . '일 ' . $만기보너스율 . '%', 0, $만기보너스);
    }
    if ($isCash) {
        gv_지급로그('개인금고인출', $nick, '#' . $idx, 0, $원금지급);
        gv_원장기록($nick, 'withdraw_cash', $원금지급, $idx, '원금만 · 이자는 수령대기', $currency);
    } else {
        gv_지급로그($무료회수 ? '개인금고회수' : ($실제중도 ? ($강제중도 ? '개인금고강제중도' : '개인금고중도') : '개인금고만기'), $nick, $강제중도 ? ('원금' . $강제환급율 . '%') : '', 0, $원금지급);
        if ($isLock7) {
            gv_원장기록($nick, $실제중도 ? 'early' : 'mature', $총지급, $idx, '원금(±보너스) · 이자는 수령대기', $currency);
        }
    }

    return [
        'ok' => true,
        'payout' => $총지급,
        'early' => $실제중도,
        'force_early' => $강제중도,
        'keep_pct' => $강제중도 ? $강제환급율 : 0,
        'matured' => $matured,
        'fee' => $수수료,
        'principal' => $원금지급,
        'bonus' => $만기보너스,
        'interest' => $선수수령,
        'free_cash' => $isCash,
    ];
}

/**
 * 일괄 만기해지 — 만기된 예치건만 (현재 통화 탭)
 * @return array{ok:bool,msg:string,count?:int,payout?:string,payout_disp?:string}
 */
function gv_금괴일괄만기해지($nick, $currency = 'point') {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '회원 정보가 없어요.'];
    }
    $currency = gv_통화정상화($currency);
    $라벨 = gv_통화라벨($currency);
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $cur_esc = addslashes($currency);
    $now = time();

    $rs = db_query("SELECT idx, nick, CAST(amount AS CHAR) AS amount, IFNULL(bar_kind, 'gold') AS bar_kind,
            IFNULL(currency, 'point') AS currency, term_days,
            IFNULL(extend_count, 0) AS extend_count, unlock_at, status,
            paid_days, CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid,
            last_interest_at, deposited_at,
            UNIX_TIMESTAMP(deposited_at) AS deposited_ts,
            (unlock_at <= NOW()) AS unlock_ready
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
          AND IFNULL(currency, 'point') = '{$cur_esc}'
        ORDER BY idx ASC");
    $targets = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            if (gv_행종류($row) === 'cash') {
                continue;
            }
            $c = gv_금괴_미수령계산($row, $now);
            if (!empty($c['matured'])) {
                $targets[] = $row;
            }
        }
    }
    if ($targets === []) {
        return ['ok' => false, 'msg' => '만기된 예치가 없어요.'];
    }

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $count = 0;
    $총지급 = '0';
    foreach ($targets as $row) {
        $r = gv_금괴단건해지_행($nick, $row, 'withdraw');
        if (empty($r['ok'])) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => $r['msg'] ?? '만기해지에 실패했어요.'];
        }
        $총지급 = gv_더하기($총지급, $r['payout'] ?? '0');
        $count++;
    }

    if ($count < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '만기된 예치가 없어요.'];
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }

    $지급표시 = gv_금액표시($총지급, $currency);
    return [
        'ok' => true,
        'msg' => "만기해지 {$count}건 완료 · 총 {$지급표시} {$라벨} 지급",
        'count' => $count,
        'payout' => $총지급,
        'payout_disp' => $지급표시,
        'currency' => $currency,
    ];
}

/**
 * 전체 중도해지 — 아직 만기 전인 예치건만 (만기건은 제외)
 * @return array{ok:bool,msg:string,count?:int,payout?:string,payout_disp?:string}
 */
function gv_금괴전체중도해지($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '회원 정보가 없어요.'];
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $now = time();
    $무료회수 = gv_회수권한인가($nick);

    $rs = db_query("SELECT idx, nick, CAST(amount AS CHAR) AS amount, IFNULL(bar_kind, 'gold') AS bar_kind, term_days,
            IFNULL(extend_count, 0) AS extend_count, unlock_at, status,
            paid_days, CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid,
            last_interest_at, deposited_at
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
        ORDER BY idx ASC");
    $targets = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $c = gv_금괴_미수령계산($row, $now);
            // 일반: 미만기만 / 회수권한: 예치중 전부
            if (!($무료회수 || empty($c['matured']))) {
                continue;
            }
            // 일반: 중도 잠금(기간별) 중인 건은 일괄에서 제외 — 단건 강제해지(일자별 환급%) 이용
            if (!$무료회수) {
                $lock = gv_중도잠금정보(
                    $row['deposited_at'] ?? '',
                    $now,
                    (int)($c['term'] ?? $row['term_days'] ?? 0),
                    (string)($row['bar_kind'] ?? '')
                );
                if (!empty($lock['locked'])) {
                    continue;
                }
            }
            $targets[] = $row;
        }
    }
    if ($targets === []) {
        $강제안내 = ' (잠금 기간 중은 단건 강제해지·원금'
            . (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT . '%→'
            . (int)GOLD_BAR_FORCE_EARLY_KEEP_FLOOR_PCT . '%)';
        return ['ok' => false, 'msg' => '중도해지할 건수가 없어요.' . (gv_회수권한인가($nick) ? '' : $강제안내)];
    }

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    $count = 0;
    $총지급 = '0';
    foreach ($targets as $row) {
        $r = gv_금괴단건해지_행($nick, $row, 'early');
        if (empty($r['ok'])) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => $r['msg'] ?? '중도해지에 실패했어요.'];
        }
        $총지급 = gv_더하기($총지급, $r['payout'] ?? '0');
        $count++;
    }

    if ($count < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '중도해지할 건수가 없어요.'];
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }

    $label = $무료회수 ? '회수' : '중도해지';
    return [
        'ok' => true,
        'msg' => "{$label} {$count}건 완료 · 총 " . gv_경표시($총지급) . ' 지급',
        'count' => $count,
        'payout' => $총지급,
        'payout_disp' => gv_경표시($총지급),
    ];
}

/** @deprecated 호환 — 자동지급 없음 */
function gv_금괴_이자정산($nick) {
    return '0';
}

function gv_다음이자문구_행(array $calc, $now = null) {
    $now = $now ?? time();
    $isCash = !empty($calc['is_cash']);
    $isLock7 = !empty($calc['is_lock7']);
    if ($isCash || $isLock7) {
        $days = (int)($calc['earned_days'] ?? 0);
        if ($isCash && $days < 1) {
            $next = $calc['next_ts'] ?? null;
            if ($next && (int)$next > $now) {
                return '24시간 미만 · 다음 이자 ' . date('m/d H:i', (int)$next) . ' · ' . gv_남은시간문구(max(0, (int)$next - $now)) . ' 후';
            }
            return '24시간 미만 · 이자 없음';
        }
        if (gv_비교($calc['claimable'] ?? '0', '0') > 0) {
            $prefix = ($isLock7 && $days <= 1) ? '맡긴 즉시 1일분 · ' : '';
            return $prefix . '수령 가능 +' . gv_경표시($calc['claimable']);
        }
        if (!empty($calc['matured'])) {
            return '적립 완료 · 일괄만기해지 가능';
        }
        $next = $calc['next_ts'] ?? null;
        if ($next && (int)$next > $now) {
            return '다음 일자 ' . date('m/d H:i', (int)$next) . ' · ' . gv_남은시간문구(max(0, (int)$next - $now)) . ' 후';
        }
        return $isLock7 ? '맡긴 즉시 1일분 · 이후 24시간마다 1%' : '24시간마다 1% 적립 중';
    }
    if (!empty($calc['matured'])) {
        if (!empty($calc['can_extend'])) {
            return '적립 완료 · 일괄만기해지 또는 30일 연장(+5%)';
        }
        return '적립 완료 · 일괄만기해지 가능';
    }
    $next = $calc['next_ts'] ?? null;
    if (!$next) {
        return '적립 대기';
    }
    if ((int)$next <= $now) {
        return '수령 가능';
    }
    $remain = max(0, (int)$next - $now);
    $when = date('m/d H:i', (int)$next);
    return '다음 적립 ' . $when . ' · ' . gv_남은시간문구($remain) . ' 후';
}

/**
 * 금괴 목록 (페이지네이션)
 * @param array{page?:int,page_size?:int,filter?:string,all?:bool} $opts
 *   filter: all|ready|active
 *   all=true 이면 전체(관리/특수용, 대량 시 느림)
 * @return list<array<string,mixed>>
 */
function gv_금괴목록($nick, array $opts = []) {
    $pack = gv_금괴목록페이지($nick, $opts);
    return $pack['bars'];
}

/**
 * @param array{page?:int,page_size?:int,filter?:string,all?:bool} $opts
 * @return array{bars:list,total:int,page:int,page_size:int,pages:int,has_more:bool,filter:string}
 */
function gv_금괴목록페이지($nick, array $opts = []): array {
    gv_테이블보장();
    $nick = trim((string)$nick);
    $filter = trim((string)($opts['filter'] ?? 'all'));
    if (!in_array($filter, ['all', 'ready', 'active'], true)) {
        $filter = 'all';
    }
    $currency = gv_통화정상화($opts['currency'] ?? 'point');
    $cur_esc = addslashes($currency);
    $all = !empty($opts['all']);
    $pageSize = max(1, min(100, (int)($opts['page_size'] ?? GOLD_VAULT_PAGE_SIZE)));
    $page = max(1, (int)($opts['page'] ?? 1));
    $empty = [
        'bars' => [],
        'total' => 0,
        'page' => $page,
        'page_size' => $pageSize,
        'pages' => 0,
        'has_more' => false,
        'filter' => $filter,
        'currency' => $currency,
    ];
    if ($nick === '') {
        return $empty;
    }

    $nick_esc = addslashes($nick);
    $cashSql = gv_자유예치_sql();
    $where = "nick = '{$nick_esc}' AND status = 0 AND IFNULL(currency, 'point') = '{$cur_esc}'";
    if ($filter === 'ready') {
        $where .= " AND unlock_at <= NOW() AND NOT ({$cashSql})";
    } elseif ($filter === 'active') {
        $where .= " AND (unlock_at > NOW() OR ({$cashSql}))";
    }

    $cntRow = db_select("SELECT COUNT(*) AS c FROM tb_gold_bar WHERE {$where}");
    $total = (int)($cntRow['c'] ?? 0);
    if ($total < 1) {
        return $empty;
    }

    $pages = $all ? 1 : (int)ceil($total / $pageSize);
    if (!$all && $page > $pages) {
        $page = $pages;
    }
    $limitSql = '';
    if (!$all) {
        $offset = ($page - 1) * $pageSize;
        $limitSql = " LIMIT {$pageSize} OFFSET {$offset}";
    }

    $rows = [];
    $now = time();
    // 만기 우선 · 최근 맡긴 순 (대량 보유 시 찾기 쉬움)
    $rs = db_query("SELECT idx, nick, CAST(amount AS CHAR) AS amount,
            IFNULL(bar_kind, 'gold') AS bar_kind, IFNULL(currency, 'point') AS currency, term_days,
            IFNULL(extend_count, 0) AS extend_count, status,
            paid_days, CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid,
            last_interest_at, deposited_at, unlock_at, withdrawn_at,
            UNIX_TIMESTAMP(deposited_at) AS deposited_ts,
            (unlock_at <= NOW()) AS unlock_ready
        FROM tb_gold_bar
        WHERE {$where}
        ORDER BY (unlock_at <= NOW()) DESC, deposited_at DESC, idx DESC
        {$limitSql}");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rows[] = gv_금괴목록_행포맷($row, $now);
        }
    }

    return [
        'bars' => $rows,
        'total' => $total,
        'page' => $page,
        'page_size' => $all ? $total : $pageSize,
        'pages' => $all ? 1 : $pages,
        'has_more' => !$all && ($page * $pageSize) < $total,
        'filter' => $filter,
        'currency' => $currency,
    ];
}

/** @return array<string,mixed> */
function gv_금괴목록_행포맷(array $row, $now = null): array {
    $now = $now ?? time();
    $c = gv_금괴_미수령계산($row, $now);
    $amount = $c['amount'];
    $kind = gv_행종류($row);
    $rowCurrency = gv_행통화($row);
    $disp = static function ($v) use ($rowCurrency) {
        return gv_금액표시($v, $rowCurrency);
    };
    $term = $c['term'];
    $extend = (int)$c['extend_count'];
    $effTerm = (int)$c['effective_term'];
    $earnedDays = (int)$c['earned_days'];
    $paidDays = (int)$c['paid_days'];
    $claimable = $c['claimable'];
    $earned = $c['earned'];
    $interestPaid = $c['interest_paid'];
    $daily = $c['daily'];
    $totalInterest = gv_총이자($amount, $effTerm);
    $matured = !empty($c['matured']);
    $canExtend = !empty($c['can_extend']);
    $matureBonus = gv_만기추가보상($amount, $term, $extend);
    $matureBonusPct = gv_만기추가보상율($term, $extend);
    $maturePayout = gv_더하기($amount, $matureBonus);
    $earlyLock = gv_중도잠금정보($row['deposited_at'] ?? '', $now, $term, $kind === 'lock7' ? 'lock7' : '');
    $forceEarly = !empty($earlyLock['locked']);
    $keepPct = gv_강제중도환급비율($row, $now, $kind === 'lock7' ? 'lock7' : '');
    $isCash = ($kind === 'cash');
    $days = (int)($c['earned_days'] ?? 0);
    if ($isCash) {
        $forceEarly = false;
        $earlyFee = '0';
        $earlyPayout = $amount; // 원금만 · 이자는 수령대기
        $totalInterest = $c['earned'];
        $matureBonus = '0';
        $matureBonusPct = 0;
        $maturePayout = $amount;
    } elseif ($kind === 'lock7') {
        // 7일잠금: 만기 전 중도는 항상 원금% 환급 · 이자는 수령대기
        $forceEarly = !$matured; // UI: 중도=원금% 환급 버튼
        $earlyFee = gv_강제중도수수료($amount, $row, $now, 'lock7');
        $earlyPayout = gv_강제중도수령액($amount, $row, $now, 'lock7');
    } elseif ($forceEarly) {
        $earlyFee = gv_강제중도수수료($amount, $row['deposited_at'] ?? '', $now);
        $earlyPayout = gv_강제중도수령액($amount, $row['deposited_at'] ?? '', $now);
    } else {
        $earlyFee = gv_중도수수료($earned);
        $earlyPayout = gv_중도수령액($amount, $earned);
    }
    $next_ts = $c['next_ts'];
    $next_label = gv_다음이자문구_행($c, $now);
    $rateDisp = $isCash
        ? ($disp($daily) . '/일 · 24시간 후부터')
        : (($kind === 'lock7')
            ? ($disp($daily) . '/일 · 맡긴 즉시')
            : ($disp($daily) . '/일'));

    if ($isCash) {
        $remain_label = '자유예치 · ' . $days . '일 · 수령대기 ' . $disp($claimable);
    } elseif ($kind === 'lock7' && !$matured) {
        $remain_label = "7일잠금 적립 {$earnedDays}/{$term}일 · 수령대기 " . $disp($claimable)
            . ' · 중도 시 원금에서 ' . max(0, 100 - $keepPct) . '% 차감(환급 ' . $keepPct . '%)';
        if (!empty($earlyLock['locked'])) {
            $lockDays = (int)($earlyLock['lock_days'] ?? GOLD_VAULT_LOCK7_EARLY_LOCK_DAYS);
            $remain_label .= ' · 잠금' . $lockDays . '일 '
                . ($earlyLock['remain_label'] !== '' ? $earlyLock['remain_label'] : '');
        }
    } elseif ($matured) {
        $remain_label = '만기 · 원금'
            . (gv_비교($matureBonus, '0') > 0 ? ('+' . $matureBonusPct . '% 추가이자') : '')
            . ' · 일괄만기해지'
            . ($canExtend ? ' · 30일 연장 시 15%' : '');
    } elseif ($extend > 0) {
        $remain_label = "연장 적립 {$earnedDays}/{$effTerm}일 · 수령대기 " . $disp($claimable);
    } else {
        $remain_label = "적립 {$earnedDays}/{$term}일 · 수령대기 " . $disp($claimable);
    }
    if ($kind !== 'lock7' && !$isCash && !$matured && $forceEarly) {
        $lockDays = (int)($earlyLock['lock_days'] ?? gv_중도잠금일수($term));
        $remain_label .= ' · 중도잠금' . $lockDays . '일 '
            . ($earlyLock['remain_label'] !== '' ? $earlyLock['remain_label'] : '')
            . ' · 강제해지 시 원금' . $keepPct . '%';
    }

    return [
        'idx'            => (int)$row['idx'],
        'amount'         => $amount,
        // 실제 맡긴 원금 표시 (구 단가 예치는 100경/1천경/1해 그대로)
        'amount_disp'    => $disp($amount),
        'bar_kind'       => $kind,
        'bar_label'      => gv_종류라벨($kind),
        'currency'       => gv_행통화($row),
        'currency_label' => gv_통화라벨(gv_행통화($row)),
        'term_days'      => $term,
        'extend_count'   => $extend,
        'effective_term' => $effTerm,
        'can_extend'     => $canExtend,
        'rate_pct'       => $rateDisp,
        'rate_disp'      => $rateDisp,
        'deposited_at'   => (string)$row['deposited_at'],
        'unlock_at'      => (string)$row['unlock_at'],
        'matured'        => $matured,
        'unlocked'       => $matured,
        'remain_sec'     => $next_ts ? max(0, (int)$next_ts - $now) : 0,
        'remain_label'   => $remain_label,
        'elapsed_days'   => $earnedDays,
        'accrued_days'   => $earnedDays,
        'paid_days'      => $paidDays,
        'claim_days'     => (int)$c['claim_days'],
        'claimable'      => $claimable,
        'claimable_disp' => $disp($claimable),
        'daily'          => $daily,
        'daily_disp'     => $disp($daily),
        'interest'       => $earned,
        'interest_disp'  => $disp($earned),
        'interest_paid'  => $interestPaid,
        'interest_paid_disp' => $disp($interestPaid),
        'remain_interest'=> gv_빼기($totalInterest, $earned),
        'remain_interest_disp' => $disp(gv_빼기($totalInterest, $earned)),
        'mature_interest'=> $totalInterest,
        'mature_interest_disp' => $disp($totalInterest),
        'mature_bonus'   => $matureBonus,
        'mature_bonus_disp' => $disp($matureBonus),
        'mature_bonus_pct' => $matureBonusPct,
        'mature_payout'  => $maturePayout,
        'mature_disp'    => $disp($maturePayout),
        'early_fee'      => $earlyFee,
        'early_fee_disp' => $disp($earlyFee),
        'early_payout'   => $earlyPayout,
        'early_disp'     => $disp($earlyPayout),
        'early_locked'   => $forceEarly,
        'early_force'    => $forceEarly,
        'early_lock_days'=> (int)($earlyLock['lock_days'] ?? gv_중도잠금일수($term)),
        'early_lock_sec' => (int)($earlyLock['remain_sec'] ?? 0),
        'early_lock_until' => (string)($earlyLock['unlock_at'] ?? ''),
        'early_lock_label' => (string)($earlyLock['remain_label'] ?? ''),
        'early_keep_pct' => $keepPct,
        'free_withdraw'  => $isCash,
        'next_interest_at' => $next_ts ? date('Y-m-d H:i:s', $next_ts) : null,
        'next_interest_ts' => $next_ts ? (int)$next_ts : 0,
        'next_interest_sec' => $next_ts ? max(0, $next_ts - $now) : 0,
        'next_interest_label' => $next_label,
        'last_interest_at' => (string)($row['last_interest_at'] ?? ''),
    ];
}

function gv_남은시간문구($sec) {
    $sec = max(0, (int)$sec);
    $d = intdiv($sec, 86400);
    $h = intdiv($sec % 86400, 3600);
    $m = intdiv($sec % 3600, 60);
    if ($d > 0) {
        return "D-{$d} · {$h}시간";
    }
    if ($h > 0) {
        return "{$h}시간 {$m}분";
    }
    return "{$m}분";
}

function gv_빠른통계($nick, $currency = 'point'): array {
    $nick = trim((string)$nick);
    $currency = gv_통화정상화($currency);
    $empty = [
        'total' => 0,
        'locked' => 0,
        'ready' => 0,
        'interest' => '0',
        'interest_disp' => '0',
        'pending' => '0',
        'pending_disp' => '0',
        'claimed_disp' => '0',
        'can_claim' => false,
        'daily' => '0',
        'daily_disp' => '0',
        'mature_interest' => '0',
        'mature_interest_disp' => '0',
        'currency' => $currency,
    ];
    if ($nick === '') {
        return $empty;
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $cur_esc = addslashes($currency);
    $cashSql = gv_자유예치_sql();
    $row = db_select("SELECT COUNT(*) AS total,
            SUM(CASE WHEN unlock_at <= NOW() AND NOT ({$cashSql}) THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN ({$cashSql}) OR unlock_at > NOW() THEN 1 ELSE 0 END) AS locked
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
          AND IFNULL(currency, 'point') = '{$cur_esc}'");
    return array_merge($empty, [
        'total' => (int)($row['total'] ?? 0),
        'ready' => (int)($row['ready'] ?? 0),
        'locked' => (int)($row['locked'] ?? 0),
        'currency' => $currency,
    ]);
}

function gv_통계($nick, $claim = null, $currency = 'point') {
    $currency = gv_통화정상화($currency);
    $claim = is_array($claim) ? $claim : gv_이자수령상태($nick, $currency);
    return [
        'total'  => (int)($claim['bar_count'] ?? 0),
        'locked' => (int)($claim['locked'] ?? 0),
        'ready'  => (int)($claim['ready'] ?? 0),
        'interest' => $claim['earned'] ?? '0',
        'interest_disp' => $claim['earned_disp'] ?? '0',
        'pending' => $claim['pending'] ?? '0',
        'pending_disp' => $claim['pending_disp'] ?? '0',
        'claimed' => $claim['claimed'] ?? '0',
        'claimed_disp' => $claim['claimed_disp'] ?? '0',
        'can_claim' => !empty($claim['can_claim']),
        'daily' => $claim['daily'] ?? '0',
        'daily_disp' => $claim['daily_disp'] ?? '0',
        'mature_interest' => $claim['mature_interest'] ?? '0',
        'mature_interest_disp' => $claim['mature_interest_disp'] ?? '0',
    ];
}

function gv_보유개수($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return 0;
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $row = db_select("SELECT COUNT(*) AS cnt FROM tb_gold_bar WHERE nick = '{$nick_esc}' AND status = 0");
    return (int)($row['cnt'] ?? 0);
}

/**
 * 개인 예치중 종류별 보유 수량 (status=0)
 * @return array{total:int,silver:int,gold:int,plat:int,silver_disp:string,gold_disp:string,plat_disp:string,total_disp:string,label:string}
 */
function gv_개인보유현황($nick): array {
    $empty = [
        'total' => 0,
        'silver' => 0,
        'gold' => 0,
        'plat' => 0,
        'silver_disp' => '0',
        'gold_disp' => '0',
        'plat_disp' => '0',
        'total_disp' => '0',
        'label' => '은괴 0 · 금괴 0 · 백금괴 0',
    ];
    $nick = trim((string)$nick);
    if ($nick === '') {
        return $empty;
    }
    // 할인배분과 동일 집계 재사용 (쿼리 1회)
    $d = gv_할인배분($nick);
    $silver = (int)($d['silver_bars'] ?? 0);
    $gold = (int)($d['gold_bars'] ?? 0);
    $plat = (int)($d['plat_bars'] ?? 0);
    $total = $silver + $gold + $plat;
    return [
        'total' => $total,
        'silver' => $silver,
        'gold' => $gold,
        'plat' => $plat,
        'silver_disp' => number_format($silver),
        'gold_disp' => number_format($gold),
        'plat_disp' => number_format($plat),
        'total_disp' => number_format($total),
        'label' => '은괴 ' . number_format($silver)
            . ' · 금괴 ' . number_format($gold)
            . ' · 백금괴 ' . number_format($plat),
    ];
}

/** 금괴 1개당 마켓 할인 % */
function gv_마켓할인_개당() {
    return 1;
}

/** 백금괴(1해) 1개당 마켓 할인 % */
function gv_마켓할인_백금개당() {
    return 10;
}

/** 은괴는 마켓 할인 없음 */
function gv_마켓할인_은괴십분율() {
    return 0;
}

function gv_마켓할인_최대() {
    return 50;
}

/**
 * 종류별 개당 할인 % (정수). 은괴는 할인 없음(0).
 */
function gv_마켓할인_종류개당($kind) {
    $kind = gv_종류정상화($kind);
    if ($kind === 'plat') {
        return max(1, (int)gv_마켓할인_백금개당());
    }
    if ($kind === 'silver') {
        return 0;
    }
    return max(1, (int)gv_마켓할인_개당());
}

/** 종류별 개당 할인 십분율(0.1% = 1) — 은괴 0 */
function gv_마켓할인_종류십분율($kind) {
    $kind = gv_종류정상화($kind);
    if ($kind === 'plat') {
        return max(1, (int)gv_마켓할인_백금개당()) * 10;
    }
    if ($kind === 'silver') {
        return 0;
    }
    return max(1, (int)gv_마켓할인_개당()) * 10;
}

/**
 * 금괴 1개 = 1% · 백금괴 1개 = 10% · 은괴 제외 · 최대 50%
 * @return array{discount_pct:int,discount_bars:int,excess_bars:int,total_bars:int,gold_bars:int,plat_bars:int,silver_bars:int,raw_pct:int}
 */
function gv_할인배분($nick) {
    $empty = [
        'discount_pct' => 0,
        'discount_bars' => 0,
        'excess_bars' => 0,
        'total_bars' => 0,
        'gold_bars' => 0,
        'plat_bars' => 0,
        'silver_bars' => 0,
        'raw_pct' => 0,
    ];
    $nick = trim((string)$nick);
    if ($nick === '') {
        return $empty;
    }
    if (!isset($GLOBALS['_gv_discount_cache']) || !is_array($GLOBALS['_gv_discount_cache'])) {
        $GLOBALS['_gv_discount_cache'] = [];
    }
    if (isset($GLOBALS['_gv_discount_cache'][$nick])) {
        return $GLOBALS['_gv_discount_cache'][$nick];
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $gold = 0;
    $plat = 0;
    $silver = 0;
    $rs = @db_query(
        "SELECT IFNULL(bar_kind, 'gold') AS bar_kind, COUNT(*) AS cnt
         FROM tb_gold_bar
         WHERE nick = '{$nick_esc}' AND status = 0
         GROUP BY IFNULL(bar_kind, 'gold')"
    );
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rawKind = trim((string)($row['bar_kind'] ?? 'gold'));
            if ($rawKind === 'cash' || $rawKind === 'vault' || $rawKind === '전액' || $rawKind === '전액예치'
                || $rawKind === 'lock7' || $rawKind === 'lock' || $rawKind === '잠금' || $rawKind === '7일잠금') {
                continue; // 자유/잠금 전액예치는 마켓할인 대상 아님
            }
            $k = gv_종류정상화($rawKind);
            $c = (int)($row['cnt'] ?? 0);
            if ($k === 'plat') {
                $plat += $c;
            } elseif ($k === 'silver') {
                $silver += $c;
            } elseif ($k === 'cash') {
                continue;
            } else {
                $gold += $c;
            }
        }
    } else {
        // bar_kind 컬럼 없는 구버전 폴백
        $totalFallback = gv_보유개수($nick);
        $gold = $totalFallback;
    }
    // 할인 대상: 금괴·백금만 (은괴는 이자 전용)
    $discountable = $gold + $plat;
    $total = $discountable + $silver;
    if ($discountable < 1) {
        $out = array_merge($empty, [
            'total_bars' => $total,
            'gold_bars' => $gold,
            'plat_bars' => $plat,
            'silver_bars' => $silver,
        ]);
        $GLOBALS['_gv_discount_cache'][$nick] = $out;
        return $out;
    }
    $perGoldT = gv_마켓할인_종류십분율('gold');
    $perPlatT = gv_마켓할인_종류십분율('plat');
    $max = max(1, (int)gv_마켓할인_최대());
    $rawTenths = ($gold * $perGoldT) + ($plat * $perPlatT);
    $rawPct = (int)floor($rawTenths / 10);
    $pct = min($max, $rawPct);

    // 할인에 실제로 쓰인 개수(대략: 백금 → 금괴)
    $remainT = $pct * 10;
    $usedPlat = min($plat, (int)floor($remainT / $perPlatT));
    $remainT -= $usedPlat * $perPlatT;
    $usedGold = ($perGoldT > 0) ? min($gold, (int)floor($remainT / $perGoldT)) : 0;
    $usedBars = $usedPlat + $usedGold;

    $out = [
        'discount_pct' => $pct,
        'discount_bars' => $usedBars,
        'excess_bars' => max(0, $discountable - $usedBars),
        'total_bars' => $total,
        'gold_bars' => $gold,
        'plat_bars' => $plat,
        'silver_bars' => $silver,
        'raw_pct' => $rawPct,
    ];
    $GLOBALS['_gv_discount_cache'][$nick] = $out;
    return $out;
}

/**
 * 마켓 할인율(%) = 금괴·백금괴 개인할인 + 겜냥 전체미션 할인
 * (금괴 개인할인은 최대 50% · 전체미션은 그 위에 합산 · 합계 상한 99%)
 */
function gv_마켓할인율($nick) {
    $bar = (int)(gv_할인배분($nick)['discount_pct'] ?? 0);
    $mission = 0;
    if (function_exists('gv_겜냥미션_마켓할인율')) {
        $mission = (int)gv_겜냥미션_마켓할인율();
    }
    return max(0, min(99, $bar + $mission));
}

function gv_상품안내($kind = 'gold') {
    $kind = gv_종류정상화($kind);
    $info = gv_종류정보($kind);
    $base = gv_냥($info['amount'] ?? GOLD_BAR_AMOUNT);
    $개당할인 = gv_마켓할인_종류개당($kind);
    $daily = gv_일일이자($base);
    $rateDisp = gv_경표시($daily) . '/일';
    $할인안내 = ($kind === 'silver')
        ? '수령보너스 ' . (int)GOLD_SILVER_CLAIM_BONUS_PCT . '%(최대 ' . (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS . '개) · 마켓할인 없음'
        : ('마켓할인 개당 ' . max(1, $개당할인) . '%');
    $items = [];
    foreach (gv_종류허용기간($kind) as $d) {
        $interest = gv_총이자($base, $d);
        $bonusPct = gv_만기추가보상율($d, 0);
        $bonus = gv_만기추가보상($base, $d, 0);
        $payout = gv_더하기($base, $bonus);
        $extendPct = ($d === 30) ? gv_만기추가보상율($d, 1) : 0;
        $extendBonus = ($d === 30) ? gv_만기추가보상($base, $d, 1) : '0';
        $items[] = [
            'kind' => $kind,
            'term_days' => (int)$d,
            'rate_pct' => $rateDisp,
            'rate_disp' => $rateDisp,
            'daily_disp' => gv_경표시($daily),
            'total_rate_disp' => gv_경표시($interest),
            'interest_disp' => gv_경표시($interest),
            'mature_bonus_pct' => $bonusPct,
            'mature_bonus_disp' => gv_경표시($bonus),
            'extend_bonus_pct' => $extendPct,
            'extend_bonus_disp' => gv_경표시($extendBonus),
            'payout_disp' => gv_경표시($payout),
            'market_discount_pct' => $개당할인,
            'market_discount_disp' => $할인안내,
        ];
    }
    return $items;
}

/** 종류 선택 UI용 요약 */
function gv_상품종류안내(): array {
    $out = [];
    foreach (gv_상품종류목록() as $key => $info) {
        $amount = gv_냥($info['amount']);
        $daily = gv_일일이자($amount);
        $terms = gv_종류허용기간($key);
        $out[] = [
            'key' => $key,
            'label' => (string)$info['label'],
            'amount' => $amount,
            'amount_disp' => (string)$info['amount_disp'],
            'terms' => $terms,
            'terms_label' => implode('/', $terms) . '일',
            'daily_disp' => gv_경표시($daily),
            'interest_30_disp' => gv_경표시(gv_총이자($amount, 30)),
            'market_discount_pct' => gv_마켓할인_종류개당($key),
        ];
    }
    return $out;
}

function gv_전체내역($filter = 'all', $limit = 500) {
    gv_테이블보장();
    $filter = trim((string)$filter);
    $where = '1=1';
    if ($filter === 'active') {
        $where = 'status = 0';
    } elseif ($filter === 'done') {
        $where = 'status = 1';
    }
    $limit = max(1, min(2000, (int)$limit));
    $rows = [];
    $now = time();
    $rs = db_query("SELECT idx, nick, CAST(amount AS CHAR) AS amount, term_days, status, early,
            paid_days, CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid,
            deposited_at, unlock_at, withdrawn_at
        FROM tb_gold_bar
        WHERE {$where}
        ORDER BY deposited_at DESC, idx DESC
        LIMIT {$limit}");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $term = (int)($row['term_days'] ?? 7);
            $status = (int)($row['status'] ?? 0);
            $early = (int)($row['early'] ?? 0);
            $paid = (int)($row['paid_days'] ?? 0);
            $matured = ($status === 0 && $paid >= $term);
            if ($status === 1) {
                $상태라벨 = $early ? '중도해지' : '만기환전';
            } elseif ($matured) {
                $상태라벨 = '만기대기';
            } else {
                $상태라벨 = '예치중';
            }
            $rows[] = [
                'idx' => (int)$row['idx'],
                'nick' => (string)$row['nick'],
                'term_days' => $term,
                'rate_disp' => '10경/일',
                'amount_disp' => '1천경',
                'paid_days' => $paid,
                'interest_paid_disp' => gv_경표시($row['interest_paid'] ?? 0),
                'status' => $status,
                'early' => $early,
                'status_label' => $상태라벨,
                'deposited_at' => (string)$row['deposited_at'],
                'unlock_at' => (string)$row['unlock_at'],
                'withdrawn_at' => (string)($row['withdrawn_at'] ?? ''),
                'matured' => $matured,
            ];
        }
    }
    return $rows;
}

function gv_닉별통계($filter = 'active') {
    gv_테이블보장();
    $filter = trim((string)$filter);
    if (!in_array($filter, ['all', 'active', 'done'], true)) {
        $filter = 'active';
    }

    $where = '1=1';
    if ($filter === 'active') {
        $where = 'status = 0';
    } elseif ($filter === 'done') {
        $where = 'status = 1';
    }

    $map = [];
    $ensure = function ($nick) use (&$map) {
        if (!isset($map[$nick])) {
            $map[$nick] = [
                'nick' => $nick,
                'active' => 0,
                'done' => 0,
                'early' => 0,
                'mature' => 0,
                'total' => 0,
                'by_term' => [],
                'interest_earned' => '0',
                'interest_paid_done' => '0',
                'interest_pending' => '0',
            ];
        }
    };

    $rs = db_query("SELECT nick, term_days, status, early, COUNT(*) AS c
        FROM tb_gold_bar
        WHERE {$where}
        GROUP BY nick, term_days, status, early");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = (string)($row['nick'] ?? '');
            if ($nick === '') {
                continue;
            }
            $ensure($nick);
            $c = (int)($row['c'] ?? 0);
            $term = (int)($row['term_days'] ?? 0);
            $status = (int)($row['status'] ?? 0);
            $early = (int)($row['early'] ?? 0);

            $map[$nick]['total'] += $c;
            if ($status === 0) {
                $map[$nick]['active'] += $c;
                if ($term > 0) {
                    if (!isset($map[$nick]['by_term'][$term])) {
                        $map[$nick]['by_term'][$term] = 0;
                    }
                    $map[$nick]['by_term'][$term] += $c;
                }
            } else {
                $map[$nick]['done'] += $c;
                if ($early === 1) {
                    $map[$nick]['early'] += $c;
                } else {
                    $map[$nick]['mature'] += $c;
                }
            }
        }
    }

    $now = time();
    $irs = db_query("SELECT nick, term_days, IFNULL(extend_count, 0) AS extend_count, status,
            paid_days, CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at,
            CAST(amount AS CHAR) AS amount
        FROM tb_gold_bar
        WHERE {$where}");
    if ($irs) {
        while ($row = db_fetch($irs)) {
            $nick = (string)($row['nick'] ?? '');
            if ($nick === '') {
                continue;
            }
            $ensure($nick);
            $status = (int)($row['status'] ?? 0);
            if ($status === 0) {
                $c = gv_금괴_미수령계산($row, $now);
                $map[$nick]['interest_earned'] = gv_더하기($map[$nick]['interest_earned'], $c['earned']);
                $map[$nick]['interest_pending'] = gv_더하기($map[$nick]['interest_pending'], $c['claimable']);
            } else {
                $map[$nick]['interest_paid_done'] = gv_더하기(
                    $map[$nick]['interest_paid_done'],
                    gv_냥($row['interest_paid'] ?? 0)
                );
            }
        }
    }

    $list = array_values($map);
    usort($list, function ($a, $b) use ($filter) {
        if ($filter === 'done') {
            $cmp = ($b['done'] ?? 0) - ($a['done'] ?? 0);
        } elseif ($filter === 'all') {
            $cmp = ($b['total'] ?? 0) - ($a['total'] ?? 0);
        } else {
            $cmp = ($b['active'] ?? 0) - ($a['active'] ?? 0);
        }
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['nick'], (string)$b['nick']);
    });

    foreach ($list as &$row) {
        if (!empty($row['by_term'])) {
            ksort($row['by_term'], SORT_NUMERIC);
        }
        $row['active_amount_disp'] = ((int)$row['active']) . '천경';
        if ($filter === 'done') {
            $totalInterest = $row['interest_paid_done'];
        } elseif ($filter === 'active') {
            $totalInterest = $row['interest_earned'];
        } else {
            $totalInterest = gv_더하기($row['interest_earned'], $row['interest_paid_done']);
        }
        $row['interest_total'] = $totalInterest;
        $row['interest_total_disp'] = gv_경표시($totalInterest);
        $row['interest_earned_disp'] = gv_경표시($row['interest_earned']);
        $row['interest_paid_disp'] = gv_경표시($row['interest_paid_done']);
        $row['interest_pending_disp'] = gv_경표시($row['interest_pending']);
    }
    unset($row);

    return $list;
}

function gv_전체통계() {
    gv_테이블보장();
    $active = (int)((db_select("SELECT COUNT(*) AS c FROM tb_gold_bar WHERE status = 0")['c'] ?? 0));
    $done = (int)((db_select("SELECT COUNT(*) AS c FROM tb_gold_bar WHERE status = 1")['c'] ?? 0));
    $early = (int)((db_select("SELECT COUNT(*) AS c FROM tb_gold_bar WHERE status = 1 AND early = 1")['c'] ?? 0));
    $nicks = (int)((db_select("SELECT COUNT(DISTINCT nick) AS c FROM tb_gold_bar WHERE status = 0")['c'] ?? 0));
    $byTerm = [];
    $rs = db_query("SELECT term_days, COUNT(*) AS c FROM tb_gold_bar WHERE status = 0 GROUP BY term_days ORDER BY term_days");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $byTerm[(int)$row['term_days']] = (int)$row['c'];
        }
    }
    return [
        'active' => $active,
        'done' => $done,
        'early' => $early,
        'active_nicks' => $nicks,
        'by_term' => $byTerm,
        'active_amount_disp' => $active . '천경',
    ];
}

/**
 * 닉 1명: 예치중 금괴 전부 회수 (권한 검사 없음)
 * 기간 무관 · 원금 전액 + 미수령 이자 · 중도 수수료(위약금) 없음
 * @return array{ok:bool,msg:string,count?:int,principal?:string,interest?:string,payout?:string,payout_disp?:string}
 */
function gv_금괴_수수료없이회수_닉($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '닉네임이 없어요.'];
    }
    gv_테이블보장();
    $nick_esc = addslashes($nick);
    $now = time();

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }

    // 기간(term_days) 조건 없음 — 예치중(status=0) 전부
    $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, term_days,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at
        FROM tb_gold_bar
        WHERE nick = '{$nick_esc}' AND status = 0
        ORDER BY idx ASC FOR UPDATE");
    if (!$rs) {
        $rs = db_query("SELECT idx, CAST(amount AS CHAR) AS amount, term_days,
                IFNULL(extend_count, 0) AS extend_count, paid_days,
                CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at
            FROM tb_gold_bar
            WHERE nick = '{$nick_esc}' AND status = 0
            ORDER BY idx ASC");
    }
    $rows = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rows[] = $row;
        }
    }
    if ($rows === []) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '회수할 금괴가 없어요.'];
    }

    $count = 0;
    $원금합 = '0';
    $이자합 = '0';
    $termCounts = [];
    foreach ($rows as $row) {
        $idx = (int)($row['idx'] ?? 0);
        if ($idx < 1) {
            continue;
        }
        // 원금은 DB amount 그대로 (기간과 무관)
        $원금 = gv_냥($row['amount'] ?? GOLD_BAR_AMOUNT);
        $c = gv_금괴_미수령계산($row, $now);
        $미수령 = gv_냥($c['claimable'] ?? 0);
        $newPaidDays = max(0, (int)$c['earned_days']);
        $newInterest = gv_더하기(gv_냥($row['interest_paid'] ?? 0), $미수령);
        $termLabel = max(1, (int)($row['term_days'] ?? $c['term'] ?? 7));

        db_query("UPDATE tb_gold_bar
            SET status = 1,
                early = 0,
                paid_days = {$newPaidDays},
                interest_paid = {$newInterest},
                last_interest_at = NOW(),
                withdrawn_at = NOW()
            WHERE idx = {$idx} AND nick = '{$nick_esc}' AND status = 0
            LIMIT 1");
        if (gv_affected() < 1) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '회수에 실패했어요. 다시 시도해주세요.'];
        }
        $원금합 = gv_더하기($원금합, $원금);
        $이자합 = gv_더하기($이자합, $미수령);
        $count++;
        if (!isset($termCounts[$termLabel])) {
            $termCounts[$termLabel] = 0;
        }
        $termCounts[$termLabel]++;
    }

    if ($count < 1) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '회수할 금괴가 없어요.'];
    }

    $총지급 = gv_더하기($원금합, $이자합);
    if (gv_비교($총지급, '0') > 0) {
        db_query("UPDATE tb_member SET point = point + {$총지급} WHERE name = '{$nick_esc}' LIMIT 1");
        if (gv_affected() < 1) {
            if ($conn instanceof mysqli) {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'msg' => '게임냥 지급에 실패했어요.'];
        }
    }

    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }
    if (gv_비교($이자합, '0') > 0) {
        gv_지급로그('개인금고이자', $nick, '전부회수', 0, $이자합);
    }
    gv_지급로그('개인금고회수', $nick, $count . '개', 0, $원금합);

    ksort($termCounts, SORT_NUMERIC);
    $termBits = [];
    foreach ($termCounts as $t => $n) {
        $termBits[] = $t . '일 ' . $n . '개';
    }
    $msg = "금괴 {$count}개 전부 회수했어요";
    if ($termBits !== []) {
        $msg .= ' (' . implode(' · ', $termBits) . ')';
    }
    $msg .= '. 원금 ' . gv_경표시($원금합);
    if (gv_비교($이자합, '0') > 0) {
        $msg .= ' · 미수령 이자 ' . gv_경표시($이자합);
    }
    $msg .= ' 지급 · 수수료 없음';

    return [
        'ok' => true,
        'msg' => $msg,
        'count' => $count,
        'principal' => $원금합,
        'interest' => $이자합,
        'payout' => $총지급,
        'payout_disp' => gv_경표시($총지급),
    ];
}

/**
 * 예치중 금괴 전체 일괄지급 미리보기 (원금 + 미수령 이자 · 위약금 없음)
 * @return array{ok:bool,msg:string,nick_count:int,bar_count:int,principal:string,interest:string,payout:string,rows:list}
 */
function gv_전체금괴일괄회수_미리보기() {
    gv_테이블보장();
    $now = time();
    $rs = db_query("SELECT nick, idx, CAST(amount AS CHAR) AS amount, term_days,
            IFNULL(extend_count, 0) AS extend_count, paid_days,
            CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid, deposited_at, unlock_at
        FROM tb_gold_bar
        WHERE status = 0
        ORDER BY nick ASC, idx ASC");
    $byNick = [];
    $barCount = 0;
    $원금합 = '0';
    $이자합 = '0';
    $byTerm = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $원금 = gv_냥($row['amount'] ?? GOLD_BAR_AMOUNT);
            $c = gv_금괴_미수령계산($row, $now);
            $미수령 = gv_냥($c['claimable'] ?? 0);
            $term = max(1, (int)($row['term_days'] ?? $c['term'] ?? 7));
            if (!isset($byNick[$nick])) {
                $byNick[$nick] = [
                    'nick' => $nick,
                    'bars' => 0,
                    'principal' => '0',
                    'interest' => '0',
                    'payout' => '0',
                ];
            }
            $byNick[$nick]['bars']++;
            $byNick[$nick]['principal'] = gv_더하기($byNick[$nick]['principal'], $원금);
            $byNick[$nick]['interest'] = gv_더하기($byNick[$nick]['interest'], $미수령);
            $byNick[$nick]['payout'] = gv_더하기($byNick[$nick]['payout'], gv_더하기($원금, $미수령));
            $원금합 = gv_더하기($원금합, $원금);
            $이자합 = gv_더하기($이자합, $미수령);
            $barCount++;
            if (!isset($byTerm[$term])) {
                $byTerm[$term] = 0;
            }
            $byTerm[$term]++;
        }
    }

    $rows = [];
    foreach ($byNick as $n => $info) {
        $rows[] = [
            'nick' => $n,
            'bars' => (int)$info['bars'],
            'principal' => $info['principal'],
            'interest' => $info['interest'],
            'payout' => $info['payout'],
            'principal_disp' => gv_경표시($info['principal']),
            'interest_disp' => gv_경표시($info['interest']),
            'payout_disp' => gv_경표시($info['payout']),
        ];
    }
    usort($rows, static function ($a, $b) {
        $cmp = gv_비교($b['payout'], $a['payout']);
        if ($cmp !== 0) {
            return $cmp > 0 ? 1 : -1;
        }
        return strcmp((string)$a['nick'], (string)$b['nick']);
    });

    ksort($byTerm, SORT_NUMERIC);
    $총지급 = gv_더하기($원금합, $이자합);
    $nickCount = count($rows);
    if ($barCount < 1) {
        return [
            'ok' => true,
            'msg' => '예치중인 금괴가 없어요.',
            'nick_count' => 0,
            'bar_count' => 0,
            'principal' => '0',
            'interest' => '0',
            'payout' => '0',
            'principal_disp' => gv_경표시(0),
            'interest_disp' => gv_경표시(0),
            'payout_disp' => gv_경표시(0),
            'by_term' => [],
            'rows' => [],
        ];
    }

    return [
        'ok' => true,
        'msg' => "예치중 {$nickCount}명 · 금괴 {$barCount}개 · 지급예정 " . gv_경표시($총지급),
        'nick_count' => $nickCount,
        'bar_count' => $barCount,
        'principal' => $원금합,
        'interest' => $이자합,
        'payout' => $총지급,
        'principal_disp' => gv_경표시($원금합),
        'interest_disp' => gv_경표시($이자합),
        'payout_disp' => gv_경표시($총지급),
        'by_term' => $byTerm,
        'rows' => $rows,
    ];
}

/**
 * 민호: 예치중 금괴 전원 일괄 회수·지급 (원금+미수령이자 · 위약금 없음)
 * 닉별로 트랜잭션 처리 — 한 명 실패해도 나머지는 계속
 * @return array{ok:bool,msg:string,nick_ok?:int,nick_fail?:int,bar_count?:int,payout?:string,errors?:list}
 */
function gv_전체금괴일괄회수($adminNick) {
    $adminNick = trim((string)$adminNick);
    if ($adminNick === '' || !gv_준호인가($adminNick)) {
        return ['ok' => false, 'msg' => '민호만 실행할 수 있어요.'];
    }
    gv_테이블보장();

    $rs = db_query("SELECT DISTINCT nick FROM tb_gold_bar WHERE status = 0 ORDER BY nick ASC");
    $nicks = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $n = trim((string)($row['nick'] ?? ''));
            if ($n !== '') {
                $nicks[] = $n;
            }
        }
    }
    if ($nicks === []) {
        return ['ok' => false, 'msg' => '예치중인 금괴가 없어요.'];
    }

    @set_time_limit(0);

    $okNicks = 0;
    $failNicks = 0;
    $barCount = 0;
    $원금합 = '0';
    $이자합 = '0';
    $총지급 = '0';
    $errors = [];

    foreach ($nicks as $nick) {
        $r = gv_금괴_수수료없이회수_닉($nick);
        if (empty($r['ok'])) {
            $failNicks++;
            $errors[] = $nick . ': ' . (string)($r['msg'] ?? '실패');
            continue;
        }
        $okNicks++;
        $barCount += (int)($r['count'] ?? 0);
        $원금합 = gv_더하기($원금합, gv_냥($r['principal'] ?? 0));
        $이자합 = gv_더하기($이자합, gv_냥($r['interest'] ?? 0));
        $총지급 = gv_더하기($총지급, gv_냥($r['payout'] ?? 0));
    }

    gv_지급로그(
        '개인금고일괄회수',
        $adminNick,
        '성공' . $okNicks . '명·실패' . $failNicks . '명·금괴' . $barCount . '개',
        0,
        $총지급
    );

    $msg = "일괄 지급 완료 · 성공 {$okNicks}명";
    if ($failNicks > 0) {
        $msg .= " · 실패 {$failNicks}명";
    }
    $msg .= " · 금괴 {$barCount}개 · 원금 " . gv_경표시($원금합);
    if (gv_비교($이자합, '0') > 0) {
        $msg .= ' · 이자 ' . gv_경표시($이자합);
    }
    $msg .= ' · 합계 ' . gv_경표시($총지급) . ' · 위약금 없음';

    return [
        'ok' => $okNicks > 0,
        'msg' => $msg,
        'nick_ok' => $okNicks,
        'nick_fail' => $failNicks,
        'bar_count' => $barCount,
        'principal' => $원금합,
        'interest' => $이자합,
        'payout' => $총지급,
        'payout_disp' => gv_경표시($총지급),
        'errors' => $errors,
    ];
}
