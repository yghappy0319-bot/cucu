<?
/**
 * 기프티콘 마켓 공통
 * 판매등록 겜냥 = 전체게임냥 × (기본 2.5% × 시세배수) (개인 market_tax 미적용)
 *   · 시세배수 기본 3 → 실효 7.5%
 * 본인 재구매 겜냥 = 판매등록가 × (판매자 market_tax / 기본 2.5)
 * market_tax = 기본 2.5% + 권면가누적 피크 기준 (삭제·감액으로 안 내려감)
 *   · 5% 미만: 30만원당 +0.1%p · 5% 이상: 60만원당 +0.1%p
 * 선매입 본방냥 = 판매등록(·수정) 시점 실시간 본방냥 × 판매자 market_tax% 환산가(price_newpoint) 고정
 * 관리자 매입 시 DB에 저장된 price_newpoint 그대로 지급 · 시세 변동과 무관
 */

if (!defined('SHOP_만원기준원화')) {
    define('SHOP_만원기준원화', 10000);
}
if (!defined('SHOP_전체판매_열람코드')) {
    define('SHOP_전체판매_열람코드', 'yl8eeO');
}
if (!defined('SHOP_최소권면가')) {
    define('SHOP_최소권면가', 1000);
}
if (!defined('SHOP_이미지_업로드_경로')) {
    define('SHOP_이미지_업로드_경로', '/home/kakao/public_html/shop/data/gifticon');
}
if (!defined('SHOP_CODE_COOKIE_NAME')) {
    define('SHOP_CODE_COOKIE_NAME', 'shop_code');
    define('SHOP_CODE_COOKIE_TTL', 7 * 86400);
}
if (!defined('SHOP_정산_수수료율')) {
    define('SHOP_정산_수수료율', 0.1);
}
/** 게임냥 구매 시 판매자 즉시 환급 비율 (나머지 소멸) */
if (!defined('SHOP_겜냥구매_판매자환급율')) {
    define('SHOP_겜냥구매_판매자환급율', 0.3);
}
/** 마켓 게임냥 구매액 → config.선매입적립 누적 비율 (0.1% = 1/1000) */
if (!defined('SHOP_선매입적립_분모')) {
    define('SHOP_선매입적립_분모', 1000);
}
if (!defined('SHOP_매입_기본_MARKET_TAX')) {
    define('SHOP_매입_기본_MARKET_TAX', 2.5);
}
/** 판매등록 겜냥 시세 배수 (기본요율 2.5% × 배수 = 실효 요율) */
if (!defined('SHOP_겜냥_시세배수')) {
    define('SHOP_겜냥_시세배수', 3); // 2.5 × 3 = 7.5%
}
/** market_tax 가산: 5% 미만 30만원당 +0.1%p */
if (!defined('SHOP_MARKET_TAX_가산_단위권면가')) {
    define('SHOP_MARKET_TAX_가산_단위권면가', 300000);
}
/** market_tax 가산: 5% 이상 60만원당 +0.1%p */
if (!defined('SHOP_MARKET_TAX_고율_단위권면가')) {
    define('SHOP_MARKET_TAX_고율_단위권면가', 600000);
}
if (!defined('SHOP_MARKET_TAX_가산_퍼센트')) {
    define('SHOP_MARKET_TAX_가산_퍼센트', 0.1);
}
/** market_tax 고율 구간 시작 (이 값부터 60만원당) */
if (!defined('SHOP_MARKET_TAX_고율_시작')) {
    define('SHOP_MARKET_TAX_고율_시작', 5.0);
}
/** 선매입 은총: 권면가(×장수) 1만원당 1개 — 관리자 선매입 시 판매자에게 지급 */
if (!defined('SHOP_등록_은총_단위권면가')) {
    define('SHOP_등록_은총_단위권면가', 10000);
}
/** 금고 마켓 할인: 금괴 1% · 백금괴 10% · 최대 50% (은괴 제외) */
if (!defined('SHOP_금괴할인_최대')) {
    define('SHOP_금괴할인_최대', 50);
}
/** 마켓 점검 모드 — true면 허용 닉만 접속 (해제 시 false) */
if (!defined('SHOP_점검모드')) {
    define('SHOP_점검모드', false);
}
/** 점검 중 접속 허용 닉 (2글자) */
if (!defined('SHOP_점검_허용닉')) {
    define('SHOP_점검_허용닉', '민호');
}

function shop_카테고리목록() {
    return [
        'cafe'     => ['label' => '카페', 'icon' => '☕'],
        'mart'     => ['label' => '편의점', 'icon' => '🏪'],
        'other'    => ['label' => '기타', 'icon' => '🎁'],
    ];
}

function shop_카테고리_이모지($category) {
    $목록 = shop_카테고리목록();
    return $목록[$category]['icon'] ?? '🎁';
}

function shop_스왑_함수_로드() {
    static $tried = false;
    if ($tried) {
        return;
    }
    $tried = true;
    $cands = [
        __DIR__ . '/../api/function.php',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/function.php',
    ];
    foreach ($cands as $fn) {
        if ($fn !== '' && is_file($fn)) {
            include_once $fn;
            break;
        }
    }
}

/** 마켓 구매 최소 무기 강화 (+20 이상) */
if (!defined('SHOP_구매_최소강화')) {
    define('SHOP_구매_최소강화', 20);
}

function shop_마켓구매_최소강화(): int {
    return (int)SHOP_구매_최소강화;
}

/** @return array{ok:bool,enhance:int,min:int,msg?:string} */
function shop_마켓구매_강화검사($회원_or_nick): array {
    $min = shop_마켓구매_최소강화();
    $enhance = 0;
    if (is_array($회원_or_nick)) {
        if (array_key_exists('enhance', $회원_or_nick)) {
            $enhance = (int)$회원_or_nick['enhance'];
        } elseif (!empty($회원_or_nick['nick'])) {
            $nick_esc = addslashes(trim((string)$회원_or_nick['nick']));
            $row = db_select("SELECT IFNULL(enhance, 0) AS enhance FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
            $enhance = (int)($row['enhance'] ?? 0);
        }
    } else {
        $nick = trim((string)$회원_or_nick);
        if ($nick !== '') {
            $nick_esc = addslashes($nick);
            $row = db_select("SELECT IFNULL(enhance, 0) AS enhance FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
            $enhance = (int)($row['enhance'] ?? 0);
        }
    }
    if ($enhance < $min) {
        return [
            'ok' => false,
            'enhance' => $enhance,
            'min' => $min,
            'msg' => "무기 +{$min} 이상만 마켓 구매가 가능해요. (현재 +" . max(0, $enhance) . ')',
        ];
    }
    return ['ok' => true, 'enhance' => $enhance, 'min' => $min];
}

function shop_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') {
        return null;
    }
    // 천경·해 단위: CAST 없이 읽으면 mysqli float/과학적표기로 잔액이 깨져 구매 비교가 실패함
    shop_스왑_함수_로드();
    if (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
    }
    $code_esc = addslashes($code);
    $row = db_select("SELECT name,
            CAST(IFNULL(point, 0) AS CHAR) AS point,
            CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint,
            IFNULL(enhance, 0) AS enhance,
            TRIM(IFNULL(item, '')) AS item
        FROM tb_member WHERE code = '{$code_esc}' LIMIT 1");
    if (empty($row['name'])) {
        return null;
    }
    return [
        'nick'      => trim($row['name']),
        'point'     => shop_냥_값($row['point'] ?? 0),
        'newpoint'  => (float)($row['newpoint'] ?? 0),
        'enhance'   => (int)($row['enhance'] ?? 0),
        'item'      => trim((string)($row['item'] ?? '')),
        'code'      => $code,
    ];
}

/** 마켓 환산용 status=0 회원 실시간 newpoint·게임냥 합계 (게임냥 마이너스·신불은 0 취급) */
function shop_실시간_총량조회($force = false) {
    static $cached = null;
    if ($force) {
        $cached = null;
    }
    if (is_array($cached)) {
        return $cached;
    }

    shop_스왑_함수_로드();

    $본방냥 = 0.0;
    $게임냥 = '0';

    // 공통 합계(이미 마이너스 제외) 우선
    if (function_exists('시세기준_실시간합계')) {
        $live = 시세기준_실시간합계();
        $본방냥 = (float)($live['본방냥'] ?? 0);
        $후보 = function_exists('냥_정수문자열')
          ? 냥_정수문자열($live['게임냥'] ?? 0)
          : shop_냥_값($live['게임냥'] ?? 0);
        if ($후보 !== '0') {
            $cached = [
                '본방냥' => $본방냥,
                '게임냥' => $후보,
            ];
            return $cached;
        }
    }

    // 여러 SQL 시도 — 환경별 DECIMAL/FORMAT/mysqli 이슈 회피
    // GREATEST(...,0): 신불자 마이너스 point 가 마켓 시세를 깎지 않도록
    // DECIMAL(65,0): 시세기준_실시간합계와 동일 — (40)은 대시총에서 잘릴 수 있음
    $sqls = [
        "SELECT COALESCE(SUM(newpoint), 0) AS total_np,
            CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
         FROM tb_member WHERE status = 0",
        "SELECT COALESCE(SUM(newpoint), 0) AS total_np,
            CONCAT('N', CAST(COALESCE(SUM(GREATEST(point, 0)), 0) AS CHAR)) AS total_pt
         FROM tb_member WHERE status = 0",
        "SELECT COALESCE(SUM(newpoint), 0) AS total_np,
            CONCAT('N', REPLACE(FORMAT(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0), 0), ',', '')) AS total_pt
         FROM tb_member WHERE status = 0",
    ];

    foreach ($sqls as $sql) {
        $row = @db_select($sql);
        if (!is_array($row)) {
            continue;
        }
        $본방냥 = (float)($row['total_np'] ?? 0);
        $ptRaw = (string)($row['total_pt'] ?? 'N0');
        // N접두·과학적표기 안전 정규화 (preg_replace 숫자만 추출 금지)
        $후보 = function_exists('냥_정수문자열')
          ? 냥_정수문자열($ptRaw)
          : shop_냥_값($ptRaw);
        if ($후보 !== '0') {
            $게임냥 = $후보;
            break;
        }
    }

    // 스냅샷 폴백
    if ($게임냥 === '0' && function_exists('시세기준_게임냥_문자열')) {
        $snap = shop_냥_값(시세기준_게임냥_문자열());
        if ($snap !== '0') {
            $게임냥 = $snap;
        }
    }
    if ($본방냥 <= 0 && function_exists('시세기준_본방냥')) {
        $본방냥 = (float)시세기준_본방냥();
    }

    $cached = [
        '본방냥' => $본방냥,
        '게임냥' => $게임냥,
    ];
    return $cached;
}

/** 선매입·price_newpoint용 — config 시세 스냅샷 본방냥 총량 */
function shop_스냅샷_본방냥_총량() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    shop_스왑_함수_로드();
    if (function_exists('시세기준_본방냥')) {
        $cached = (float)시세기준_본방냥();
        return $cached;
    }

    $총량 = shop_실시간_총량조회();
    $cached = (float)($총량['본방냥'] ?? 0);
    return $cached;
}

/** 판매등록용 요율 — 기본 2.5% × 시세배수 (개인 market_tax 미적용) */
function shop_판매등록_market_tax() {
    $배수 = (float)SHOP_겜냥_시세배수;
    if ($배수 <= 0) {
        $배수 = 1.0;
    }
    return (float)SHOP_매입_기본_MARKET_TAX * $배수;
}

/**
 * 지정 market_tax% 기준 1만원당 실시간 본방냥 (문자열·bcmath — PHP_INT_MAX 초과 안전)
 * @param float|null $tax_pct null이면 판매등록 기본 2.5%
 * @return string|int 0 또는 양의 정수(문자열 가능)
 */
function shop_매입_만원당_newpoint_요율($tax_pct = null) {
    if ($tax_pct === null) {
        $tax_pct = shop_판매등록_market_tax();
    }
    $비율 = shop_market_tax_비율($tax_pct);
    $총량 = shop_실시간_총량조회();
    $total_np = (float)($총량['본방냥'] ?? 0);
    if ($total_np <= 0) {
        return '0';
    }
    $npStr = number_format($total_np, 4, '.', '');
    $ratioStr = sprintf('%.12F', $비율);
    if (function_exists('bcmul') && function_exists('bccomp')) {
        $금액 = bcmul($npStr, $ratioStr, 0); // floor
        if (bccomp($금액, '1', 0) < 0) {
            return '1';
        }
        if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
            return (int)$금액;
        }
        return $금액;
    }
    $raw = $total_np * $비율;
    if (!is_finite($raw) || $raw < 1) {
        return 1;
    }
    if ($raw > (float)PHP_INT_MAX) {
        return function_exists('냥_정수문자열') ? 냥_정수문자열($raw) : sprintf('%.0F', floor($raw));
    }
    return max(1, (int)floor($raw));
}

/** 큰 정수 문자열 ÷ 작은 정수 (내림) — bcmath 없이도 동작 */
function shop_문자열_나누기_내림($숫자문자열, $나누는수) {
    $s = preg_replace('/[^\d]/', '', (string)$숫자문자열);
    $s = ltrim($s, '0') ?: '0';
    $d = (int)$나누는수;
    if ($s === '0' || $d < 1) {
        return '0';
    }
    if (function_exists('bcdiv')) {
        return bcdiv($s, (string)$d, 0);
    }
    if (strlen($s) < 15) {
        return (string)(int)floor(((float)$s) / $d);
    }
    $result = '';
    $remain = 0;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $remain = $remain * 10 + (int)$s[$i];
        $result .= (string)intdiv($remain, $d);
        $remain = $remain % $d;
    }
    return ltrim($result, '0') ?: '0';
}

/** 큰 정수 문자열 × 작은 정수 — bcmath 없이도 동작 */
function shop_문자열_곱하기($숫자문자열, $배수) {
    $s = preg_replace('/[^\d]/', '', (string)$숫자문자열);
    $s = ltrim($s, '0') ?: '0';
    $m = (int)$배수;
    if ($s === '0' || $m === 0) {
        return '0';
    }
    if ($m === 1) {
        return $s;
    }
    if (function_exists('bcmul')) {
        return bcmul($s, (string)$m, 0);
    }
    if (strlen($s) < 15 && $m < 1000000) {
        return (string)(int)round(((float)$s) * $m);
    }
    $carry = 0;
    $out = '';
    for ($i = strlen($s) - 1; $i >= 0; $i--) {
        $prod = ((int)$s[$i]) * $m + $carry;
        $out = (string)($prod % 10) . $out;
        $carry = intdiv($prod, 10);
    }
    if ($carry > 0) {
        $out = (string)$carry . $out;
    }
    return ltrim($out, '0') ?: '0';
}

/**
 * 지정 market_tax% 기준 1만원당 게임냥
 * 공식: 전체게임냥 × (tax/100)  — float/(int) 절대 사용 금지
 */
function shop_만원당_냥_요율($tax_pct = null) {
    if ($tax_pct === null) {
        $tax_pct = shop_판매등록_market_tax();
    }
    $비율 = shop_market_tax_비율($tax_pct);
    $총량 = shop_실시간_총량조회();
    $전체게임냥 = shop_냥_값($총량['게임냥'] ?? 0);
    if ($전체게임냥 === '0') {
        shop_스왑_함수_로드();
        if (function_exists('시세기준_게임냥_문자열')) {
            $전체게임냥 = shop_냥_값(시세기준_게임냥_문자열());
        }
    }
    if ($전체게임냥 === '0') {
        return '0';
    }

    if (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
        $raw = bcmul($전체게임냥, sprintf('%.12F', $비율), 12);
        $금액 = bcadd($raw, '0', 0);
        if (bccomp($raw, $금액, 12) > 0) {
            $금액 = bcadd($금액, '1', 0);
        }
        if (bccomp($금액, '1', 0) < 0) {
            $금액 = '1';
        }
        return $금액;
    }

    // bcmath 없음: amount × (tax×100) ÷ 10000 (ceil)
    $tax100 = (int)round(((float)$tax_pct) * 100);
    if ($tax100 < 1) {
        $tax100 = 250;
    }
    $분자 = shop_문자열_곱하기($전체게임냥, $tax100);
    $몫 = shop_문자열_나누기_내림($분자, 10000);
    $remain = 0;
    $len = strlen($분자);
    for ($i = 0; $i < $len; $i++) {
        $remain = $remain * 10 + (int)$분자[$i];
        $remain = $remain % 10000;
    }
    if ($remain > 0) {
        $carry = 1;
        $out = '';
        for ($i = strlen($몫) - 1; $i >= 0; $i--) {
            $sum = ((int)$몫[$i]) + $carry;
            $out = (string)($sum % 10) . $out;
            $carry = intdiv($sum, 10);
        }
        if ($carry > 0) {
            $out = (string)$carry . $out;
        }
        $몫 = ltrim($out, '0') ?: '0';
    }
    return ($몫 === '0') ? '1' : $몫;
}

/**
 * 지정 market_tax% 기준 권면가 → 게임냥
 * 전체게임냥 × tax% × (원화/만원) — float 경로 없음
 * @param float|null $tax_pct null이면 판매등록 기본 2.5%
 */
function shop_원화_냥환산_요율($원화, $tax_pct = null) {
    $원화 = (int)$원화;
    if ($원화 <= 0) {
        return '0';
    }
    if ($tax_pct === null) {
        $tax_pct = shop_판매등록_market_tax();
    }
    $만원기준 = (int)SHOP_만원기준원화;
    if ($만원기준 < 1) {
        $만원기준 = 10000;
    }

    $만원당 = shop_만원당_냥_요율($tax_pct);
    $만원당Str = shop_냥_값($만원당);
    if ($만원당Str === '0') {
        return '0';
    }

    // (만원당 × 원화) ÷ 만원기준 — 소수 ratio×bcmul 대신 정수 경로 (대금액 안전)
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
        $금액 = bcdiv(bcmul($만원당Str, (string)$원화, 0), (string)$만원기준, 0);
        if (bccomp($금액, '0', 0) > 0) {
            return $금액;
        }
        // 시총·요율은 있는데 권면가가 작아 내림 0 → 최소 1냥
        if (bccomp($만원당Str, '0', 0) > 0) {
            return '1';
        }
    }

    // bcmath 없음 — 문자열 곱·나눗셈 (float/(int) 금지)
    $분자 = shop_문자열_곱하기($만원당Str, $원화);
    $금액 = shop_문자열_나누기_내림($분자, $만원기준);
    if ($금액 !== '0') {
        return $금액;
    }
    if ($만원당Str !== '0') {
        return '1';
    }

    return '0';
}

/**
 * 판매등록가 × (판매자세율 / 기본 2.5) — 대금액에서도 안전
 * 등록가가 있으면 재계산 대신 비율 스케일 (5%면 ×2)
 * ※ 스케일 기준은 시세배수 적용 전 기본요율(2.5) — 등록가가 이미 배수 반영됨
 */
function shop_본인재구매가_스케일($판매등록가, $seller_tax_pct) {
    $base = shop_냥_값($판매등록가);
    if ($base === '0') {
        return '0';
    }
    $seller_tax = (float)$seller_tax_pct;
    $base_tax = (float)SHOP_매입_기본_MARKET_TAX;
    if ($seller_tax <= 0) {
        $seller_tax = $base_tax;
    }
    if ($base_tax <= 0) {
        $base_tax = 2.5;
    }
    if (abs($seller_tax - $base_tax) < 0.0001) {
        return $base;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        $factor = bcdiv(sprintf('%.12F', $seller_tax), sprintf('%.12F', $base_tax), 12);
        return bcmul($base, $factor, 0);
    }
    // bcmath 없음 — 정수 배율 (5/2.5=2, 7.5/2.5=3 …)
    $배 = (int)round($seller_tax / $base_tax);
    if ($배 >= 1) {
        return shop_문자열_곱하기($base, $배);
    }
    return $base;
}

/** 1만원 권면가 → 게임냥 판매가 (전체게임냥 × 판매등록 실효요율) */
function shop_만원당_냥($seller_nick = '') {
    $금액 = shop_만원당_냥_요율(shop_판매등록_market_tax());
    $금액Str = shop_냥_값($금액);
    if ($금액Str === '0') {
        return 0;
    }
    if (function_exists('bccomp') && bccomp($금액Str, (string)PHP_INT_MAX, 0) <= 0) {
        return max(1, (int)$금액Str);
    }
    if (!function_exists('bccomp') && strlen($금액Str) < strlen((string)PHP_INT_MAX)) {
        return max(1, (int)$금액Str);
    }
    return $금액Str;
}

/** 권면가(원) → 판매등록 게임냥 (기본요율 × 시세배수) */
function shop_원화_냥환산($원화, $seller_nick = '') {
    return shop_원화_냥환산_요율($원화, shop_판매등록_market_tax());
}

/**
 * 권면가(원) → 본인 재구매 게임냥 (판매자 market_tax%)
 * 가능하면 판매등록가에 세율 비율을 곱해 대금액 오버플로우를 피함
 */
function shop_본인재구매_원화_냥환산($원화, $seller_nick = '', $판매등록가 = null) {
    $seller_tax = shop_판매자_market_tax($seller_nick);
    $등록가 = ($판매등록가 !== null && $판매등록가 !== '')
      ? shop_냥_값($판매등록가)
      : '0';
    if ($등록가 === '0' && (int)$원화 > 0) {
        $등록가 = shop_냥_값(shop_원화_냥환산($원화, $seller_nick));
    }
    if ($등록가 !== '0') {
        $scaled = shop_본인재구매가_스케일($등록가, $seller_tax);
        if (shop_냥_비교($scaled, '0') > 0) {
            return $scaled;
        }
    }
    return shop_원화_냥환산_요율($원화, $seller_tax);
}

/** 마켓 화면용 환산 기준 문구 */
function shop_환산_기준_요약($seller_nick = '') {
    $보유 = shop_매입_만원당_newpoint($seller_nick);
    $스왑 = shop_스왑_1보유냥당_게임냥();
    $게임 = shop_만원당_냥($seller_nick);
    return [
        '보유냥_1만원' => $보유,
        '스왑_1보유당_게임' => $스왑,
        '게임냥_1만원' => $게임,
        '판매등록_tax' => shop_판매등록_market_tax(),
        '선매입_tax' => shop_판매자_market_tax($seller_nick),
    ];
}

/** @return array 판매자 market_tax 요청 캐시 (참조) */
function &shop_판매자_market_tax_cache_ref() {
    static $cache = [];
    return $cache;
}

function shop_판매자_market_tax_cache_clear($seller_nick = '') {
    $cache = &shop_판매자_market_tax_cache_ref();
    $seller_nick = trim((string)$seller_nick);
    if ($seller_nick === '') {
        $cache = [];
        return;
    }
    unset($cache[$seller_nick]);
}

/** tb_member.권면가총액 · market_tax 컬럼 보장 (최초 1회 전체 백필) */
function shop_회원_권면가총액_컬럼_보장() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $need_backfill = false;
    $face_col = @db_select("SHOW COLUMNS FROM tb_member LIKE '권면가총액'");
    if (empty($face_col['Field'])) {
        @db_query("
            ALTER TABLE tb_member
            ADD COLUMN `권면가총액` BIGINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT '마켓 권면가 누적 피크(삭제·감액으로 안 내려감)'
        ");
        $need_backfill = true;
    }
    $tax_col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'market_tax'");
    if (empty($tax_col['Field'])) {
        $기본 = (float)SHOP_매입_기본_MARKET_TAX;
        @db_query("
            ALTER TABLE tb_member
            ADD COLUMN `market_tax` DECIMAL(6,2) NOT NULL DEFAULT {$기본}
            COMMENT '마켓 요율%% (피크·5%%미만 30만/0.1 · 이상 60만/0.1)'
        ");
        $need_backfill = true;
    }
    if ($need_backfill) {
        shop_회원_권면가총액_전체백필();
    } else {
        // 컬럼은 있으나 아직 미동기화(전부 0)인 경우 1회 백필
        $mem_sum = @db_select("SELECT COALESCE(SUM(`권면가총액`), 0) AS s FROM tb_member");
        $gift_sum = @db_select("
            SELECT COALESCE(SUM(face_value), 0) AS s
            FROM tb_gifticon
            WHERE status IN ('sale', 'sold', 'reserved')
        ");
        if ((int)($mem_sum['s'] ?? 0) === 0 && (int)($gift_sum['s'] ?? 0) > 0) {
            shop_회원_권면가총액_전체백필();
        }
    }
}

/** 기본 요율(2.5%)에서 고율 시작(5%)까지 필요한 권면가 피크 */
function shop_market_tax_고율_시작_권면가() {
    $기본 = (float)SHOP_매입_기본_MARKET_TAX;
    $고율시작 = (float)SHOP_MARKET_TAX_고율_시작;
    $가산 = (float)SHOP_MARKET_TAX_가산_퍼센트;
    $단위 = max(1, (int)SHOP_MARKET_TAX_가산_단위권면가);
    if ($가산 <= 0 || $고율시작 <= $기본) {
        return 0;
    }
    $단계 = (int)round(($고율시작 - $기본) / $가산);
    return max(0, $단계) * $단위;
}

/**
 * 권면가 피크 → market_tax%
 * 5% 미만: 30만원당 +0.1%p · 5% 이상: 60만원당 +0.1%p
 */
function shop_market_tax_from_face_total($권면가총액) {
    $총액 = max(0, (int)$권면가총액);
    $가산 = (float)SHOP_MARKET_TAX_가산_퍼센트;
    $기본 = (float)SHOP_매입_기본_MARKET_TAX;
    $고율시작 = (float)SHOP_MARKET_TAX_고율_시작;
    $단위1 = max(1, (int)SHOP_MARKET_TAX_가산_단위권면가);
    $단위2 = max(1, (int)SHOP_MARKET_TAX_고율_단위권면가);
    $한도 = shop_market_tax_고율_시작_권면가();

    if ($총액 <= $한도) {
        $단계 = (int)floor($총액 / $단위1);
        return round($기본 + ($단계 * $가산), 2);
    }

    $초과 = $총액 - $한도;
    $단계2 = (int)floor($초과 / $단위2);
    return round($고율시작 + ($단계2 * $가산), 2);
}

/** 판매자 전원 권면가총액·market_tax 일괄 재산출 (피크만 상승, 삭제로 안 내려감) */
function shop_회원_권면가총액_전체백필() {
    $기본 = number_format((float)SHOP_매입_기본_MARKET_TAX, 2, '.', '');
    $고율시작 = number_format((float)SHOP_MARKET_TAX_고율_시작, 2, '.', '');
    $단위1 = max(1, (int)SHOP_MARKET_TAX_가산_단위권면가);
    $단위2 = max(1, (int)SHOP_MARKET_TAX_고율_단위권면가);
    $가산 = number_format((float)SHOP_MARKET_TAX_가산_퍼센트, 2, '.', '');
    $한도 = (int)shop_market_tax_고율_시작_권면가();
    @db_query("
        UPDATE tb_member m
        INNER JOIN (
            SELECT seller_nick,
                   COALESCE(SUM(face_value), 0) AS total_face
            FROM tb_gifticon
            WHERE status IN ('sale', 'sold', 'reserved')
              AND seller_nick IS NOT NULL
              AND seller_nick <> ''
            GROUP BY seller_nick
        ) t ON m.name = t.seller_nick
        SET m.권면가총액 = GREATEST(IFNULL(m.권면가총액, 0), t.total_face),
            m.market_tax = GREATEST(
                IFNULL(m.market_tax, {$기본}),
                CASE
                  WHEN GREATEST(IFNULL(m.권면가총액, 0), t.total_face) <= {$한도}
                    THEN {$기본} + FLOOR(GREATEST(IFNULL(m.권면가총액, 0), t.total_face) / {$단위1}) * {$가산}
                  ELSE {$고율시작}
                       + FLOOR((GREATEST(IFNULL(m.권면가총액, 0), t.total_face) - {$한도}) / {$단위2}) * {$가산}
                END
            )
    ");
    shop_판매자_market_tax_cache_clear();
}

/**
 * tb_gifticon 합계로 회원 권면가총액·market_tax 동기화
 * 피크만 반영 — 삭제·권면가 감액으로 내려가지 않음
 * @return array{권면가총액:int,market_tax:float}
 */
function shop_회원_권면가총액_동기화($nick) {
    shop_회원_권면가총액_컬럼_보장();
    $nick = trim((string)$nick);
    $기본 = (float)SHOP_매입_기본_MARKET_TAX;
    if ($nick === '') {
        return ['권면가총액' => 0, 'market_tax' => $기본];
    }
    $nick_esc = addslashes($nick);
    $합계행 = @db_select("
        SELECT COALESCE(SUM(face_value), 0) AS total_face
        FROM tb_gifticon
        WHERE seller_nick = '{$nick_esc}'
          AND status IN ('sale', 'sold', 'reserved')
    ");
    $현재합 = max(0, (int)($합계행['total_face'] ?? 0));
    $기존행 = @db_select("
        SELECT IFNULL(권면가총액, 0) AS face_total,
               IFNULL(market_tax, {$기본}) AS market_tax
        FROM tb_member
        WHERE name = '{$nick_esc}'
        LIMIT 1
    ");
    $기존피크 = max(0, (int)($기존행['face_total'] ?? 0));
    $기존세율 = isset($기존행['market_tax']) ? (float)$기존행['market_tax'] : $기본;
    if ($기존세율 <= 0) {
        $기존세율 = $기본;
    }

    $총액 = max($기존피크, $현재합);
    $tax = max($기존세율, shop_market_tax_from_face_total($총액));
    $tax_sql = number_format($tax, 2, '.', '');
    @db_query("
        UPDATE tb_member
        SET 권면가총액 = {$총액},
            market_tax = {$tax_sql}
        WHERE name = '{$nick_esc}'
        LIMIT 1
    ");
    shop_판매자_market_tax_cache_clear($nick);
    return ['권면가총액' => $총액, 'market_tax' => $tax];
}

function shop_판매자_market_tax($seller_nick) {
    $seller_nick = trim((string)$seller_nick);
    if ($seller_nick === '') {
        return (float)SHOP_매입_기본_MARKET_TAX;
    }

    $cache = &shop_판매자_market_tax_cache_ref();
    if (array_key_exists($seller_nick, $cache)) {
        return $cache[$seller_nick];
    }

    shop_회원_권면가총액_컬럼_보장();
    $seller_esc = addslashes($seller_nick);
    $row = db_select("SELECT market_tax, IFNULL(권면가총액, 0) AS face_total FROM tb_member WHERE name = '{$seller_esc}' LIMIT 1");
    if (empty($row)) {
        $cache[$seller_nick] = (float)SHOP_매입_기본_MARKET_TAX;
        return $cache[$seller_nick];
    }

    // 피크 권면가 기준 재산출 — 저장된 세율보다 낮아지지 않음
    $face_total = max(0, (int)($row['face_total'] ?? 0));
    $calc = shop_market_tax_from_face_total($face_total);
    $stored = (isset($row['market_tax']) && $row['market_tax'] !== '' && $row['market_tax'] !== null)
        ? (float)$row['market_tax']
        : 0.0;
    $tax = max($stored, $calc);
    if ($tax <= 0) {
        $tax = (float)SHOP_매입_기본_MARKET_TAX;
    }
    if (abs($stored - $tax) > 0.001) {
        $tax_sql = number_format($tax, 2, '.', '');
        @db_query("UPDATE tb_member SET market_tax = {$tax_sql} WHERE name = '{$seller_esc}' LIMIT 1");
    }

    $cache[$seller_nick] = $tax;
    return $tax;
}

function shop_market_tax_비율($market_tax_pct) {
    return max(0.0001, (float)$market_tax_pct / 100.0);
}

function shop_market_tax_표시($market_tax_pct) {
    $tax = (float)$market_tax_pct;
    if ($tax <= 0) {
        $tax = (float)SHOP_매입_기본_MARKET_TAX;
    }
    $text = rtrim(rtrim(number_format($tax, 2, '.', ''), '0'), '.');
    return $text . '%';
}

/** 판매등록 겜냥 환산용 — 1만원당 실시간 본방냥 × 판매등록 실효요율 (개인 market_tax 무시) */
function shop_매입_만원당_newpoint($seller_nick = '') {
    return shop_매입_만원당_newpoint_요율(shop_판매등록_market_tax());
}

/** 선매입 — 1만원당 본방냥 (실시간 본방냥 × 판매자 market_tax%) */
function shop_선매입_만원당_newpoint($seller_nick = '') {
    $비율 = shop_market_tax_비율(shop_판매자_market_tax($seller_nick));
    $총량 = shop_실시간_총량조회();
    $total_np = (float)($총량['본방냥'] ?? 0);
    if ($total_np <= 0) {
        return 0;
    }
    return max(1, (int)floor($total_np * $비율));
}

/**
 * 선매입·표시용 본방냥 — DB price_newpoint(등록/수정 시점 고정가) 우선
 * 미기록(0)인 구상품만 현재 실시간으로 폴백 환산
 */
function shop_상품_표시_본방냥($row, $seller_nick = '') {
    $stored = shop_냥_값($row['price_newpoint'] ?? 0);
    if ($stored !== '0' && shop_냥_비교($stored, '0') > 0) {
        if (function_exists('bccomp') && bccomp($stored, (string)PHP_INT_MAX, 0) > 0) {
            return $stored;
        }
        return (int)$stored;
    }
    $face_value = (int)($row['face_value'] ?? 0);
    $seller_nick = trim((string)($seller_nick !== '' ? $seller_nick : ($row['seller_nick'] ?? ($row['seller'] ?? ''))));
    return shop_선매입_권면가_newpoint($face_value, $seller_nick);
}

/**
 * 선매입 실지급액 — 등록 시점 price_newpoint 고정 (없으면 현재 실시간 환산 폴백)
 * @return string 냥 정수 문자열
 */
function shop_선매입_지급액_조회($상품행, $seller_nick = '') {
    $stored = shop_냥_값($상품행['price_newpoint'] ?? 0);
    if ($stored !== '0' && shop_냥_비교($stored, '0') > 0) {
        return $stored;
    }
    $face_value = (int)($상품행['face_value'] ?? 0);
    $seller_nick = trim((string)($seller_nick !== '' ? $seller_nick : ($상품행['seller_nick'] ?? ($상품행['seller'] ?? ''))));
    return shop_냥_값(shop_선매입_권면가_newpoint($face_value, $seller_nick));
}

/** 권면가(원) → 선매입 본방냥 환산 (등록·수정 시 price_newpoint 기록용 · 실시간 본방냥) */
function shop_선매입_권면가_newpoint($face_value, $seller_nick = '') {
    $face_value = (int)$face_value;
    if ($face_value <= 0) {
        return 0;
    }
    $만원당 = shop_선매입_만원당_newpoint($seller_nick);
    if ($만원당 < 1) {
        return 0;
    }
    return (int)floor($face_value / SHOP_만원기준원화 * $만원당);
}

/** @deprecated alias — 선매입 전용 */
function shop_매입_권면가_newpoint($face_value, $seller_nick = '') {
    return shop_선매입_권면가_newpoint($face_value, $seller_nick);
}

/** 선매입 은총 개수 — 총권면가(권면가×장수) ÷ 1만원 (내림) */
function shop_등록_은총_지급개수($face_value, $image_count = 1) {
    $face_value = (int)$face_value;
    $image_count = max(0, (int)$image_count);
    $단위 = (int)SHOP_등록_은총_단위권면가;
    if ($face_value < 1 || $image_count < 1 || $단위 < 1) {
        return 0;
    }
    return (int)floor(($face_value * $image_count) / $단위);
}

/** 은총 지급 (관리자 선매입 등). 성공 시 지급 개수, 실패 시 false */
function shop_등록_은총_지급($nick, $face_value, $image_count, $로그구분 = '마켓선매입은총') {
    $nick = trim((string)$nick);
    $개수 = shop_등록_은총_지급개수($face_value, $image_count);
    if ($nick === '' || $개수 < 1) {
        return 0;
    }

    $nick_esc = addslashes($nick);
    $존재 = db_select("SELECT idx, IFNULL(은총개수, 0) AS cnt FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
    if (empty($존재['idx'])) {
        return false;
    }

    if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/../api/item_bag_enhance.inc.php')) {
        require_once __DIR__ . '/../api/item_bag_enhance.inc.php';
    }
    $이전 = function_exists('bag_은총_수량')
        ? bag_은총_수량($nick)
        : (int)($존재['cnt'] ?? 0);
    if (function_exists('bag_은총_가산')) {
        $add = bag_은총_가산($nick, $개수);
        if (empty($add['ok'])) {
            return false;
        }
        $이후 = (int)($add['qty'] ?? ($이전 + $개수));
    } else {
        global $conn;
        $ok = db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$개수} WHERE name = '{$nick_esc}' LIMIT 1");
        if (!$ok) {
            return false;
        }
        $확인 = db_select("SELECT IFNULL(은총개수, 0) AS cnt FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        $이후 = (int)($확인['cnt'] ?? 0);
    }
    if ($이후 < $이전 + $개수) {
        return false;
    }

    shop_스왑_함수_로드();
    if (function_exists('지급로그')) {
        $로그구분 = trim((string)$로그구분);
        if ($로그구분 === '') {
            $로그구분 = '마켓선매입은총';
        }
        지급로그($로그구분, $nick, $nick, 0, $개수);
    }
    return $개수;
}

function shop_매입_지급_표시($amount) {
    shop_스왑_함수_로드();
    if (function_exists('newpoint표시')) {
        return newpoint표시($amount);
    }
    return number_format((int)$amount) . '냥';
}

/** 1 보유냥 → 게임냥 스왑 환율 (실시간 총량 비율) */
function shop_스왑_1보유냥당_게임냥() {
    $총량 = shop_실시간_총량조회();
    $total_np = (float)($총량['본방냥'] ?? 0);
    $total_pt = function_exists('냥_정수문자열')
      ? 냥_정수문자열($총량['게임냥'] ?? 0)
      : (string)($총량['게임냥'] ?? 0);
    if ($total_np <= 0 || $total_pt === '0') {
        return 0;
    }
    if (function_exists('bcdiv') && function_exists('bccomp')) {
        $np = number_format($total_np, 4, '.', '');
        $q = bcdiv($total_pt, $np, 0);
        if (bccomp($q, (string)PHP_INT_MAX, 0) > 0) {
            return $q;
        }
        return max(0, (int)$q);
    }
    return max(0, (int)floor((float)$total_pt / $total_np));
}

/** BIGINT/DECIMAL 냥 값 정규화 — PHP int 오버플로우·과학적 표기 방지 */
function shop_냥_값($raw) {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($raw);
    }
    if (is_int($raw)) {
        return $raw < 0 ? (string)(-$raw) : (string)$raw;
    }
    if (is_float($raw)) {
        if (!is_finite($raw) || $raw == 0.0) {
            return '0';
        }
        return ltrim(preg_replace('/[^\d]/', '', sprintf('%.0F', abs($raw))), '0') ?: '0';
    }
    $s = trim((string)$raw);
    if ($s === '' || $s === '-' || $s === '+') {
        return '0';
    }
    // 과학적 표기 (mysqli BIGINT→float 등)
    if (preg_match('/^([+-])?(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $s, $m)) {
        $digits = $m[2] . ($m[3] ?? '');
        $exp = (int)$m[4] - strlen($m[3] ?? '');
        if ($exp >= 0) {
            $digits .= str_repeat('0', $exp);
        } else {
            $cut = strlen($digits) + $exp;
            $digits = $cut > 0 ? substr($digits, 0, $cut) : '0';
        }
        return ltrim($digits, '0') ?: '0';
    }
    $digits = preg_replace('/[^\d]/', '', $s);
    return ltrim($digits, '0') ?: '0';
}

/** 냥 전체 숫자 콤마 표시 (툴팁·상세용) */
function shop_냥_전체표시($amount) {
    $digits = shop_냥_값($amount);
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits);
}

/** 냥 크기 비교 — bccomp 우선 */
function shop_냥_비교($a, $b) {
    $a = shop_냥_값($a);
    $b = shop_냥_값($b);
    if (function_exists('bccomp')) {
        return bccomp($a, $b);
    }
    if (strlen($a) !== strlen($b)) {
        return strlen($a) <=> strlen($b);
    }
    return $a <=> $b;
}

function shop_금괴할인_로드() {
    static $done = false;
    if ($done) {
        return;
    }
    $cands = [
        __DIR__ . '/../page/_gold_vault_lib.php',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/page/_gold_vault_lib.php',
    ];
    foreach ($cands as $lib) {
        if ($lib !== '' && is_file($lib)) {
            include_once $lib;
            break;
        }
    }
    // 금고 라이브러리 미로드 시에도 겜냥 전체미션만 단독 로드
    if (!function_exists('gv_겜냥미션_마켓할인율')) {
        $gemCands = [
            __DIR__ . '/../api/game/vault_gem_mission.inc.php',
            (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/game/vault_gem_mission.inc.php',
        ];
        foreach ($gemCands as $gem) {
            if ($gem !== '' && is_file($gem)) {
                include_once $gem;
                break;
            }
        }
    }
    if (function_exists('gv_테이블보장')) {
        gv_테이블보장();
    }
    $done = true;
}

/** 보관 중 마켓할인 대상 개수 (금괴·백금 · 은괴 제외) */
function shop_금괴보유개수($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return 0;
    }
    shop_금괴할인_로드();
    if (function_exists('gv_할인배분')) {
        $d = gv_할인배분($nick);
        return (int)($d['gold_bars'] ?? 0) + (int)($d['plat_bars'] ?? 0);
    }
    if (function_exists('gv_보유개수')) {
        return gv_보유개수($nick);
    }
    $nick_esc = addslashes($nick);
    $row = @db_select("SELECT COUNT(*) AS cnt FROM tb_gold_bar WHERE nick = '{$nick_esc}' AND status = 0
        AND IFNULL(bar_kind, 'gold') IN ('gold', 'plat')");
    return (int)($row['cnt'] ?? 0);
}

/**
 * 마켓 할인 상세
 * @return array{total_pct:int,bar_pct:int,gem_pct:int,gold_count:int,label:string}
 */
function shop_마켓할인_상세($nick = '') {
    shop_금괴할인_로드();
    $nick = trim((string)$nick);
    $bar = 0;
    $goldCount = 0;
    if ($nick !== '') {
        if (function_exists('gv_할인배분')) {
            $d = gv_할인배분($nick);
            $bar = (int)($d['discount_pct'] ?? 0);
            $goldCount = (int)($d['gold_bars'] ?? 0) + (int)($d['plat_bars'] ?? 0);
        }
    }
    $gem = 0;
    if (function_exists('gv_겜냥미션_마켓할인율')) {
        $gem = (int)gv_겜냥미션_마켓할인율();
    }
    $total = max(0, min(99, $bar + $gem));
    $parts = [];
    if ($bar > 0) {
        $parts[] = '금괴·백금 ' . $bar . '%';
    }
    if ($gem > 0) {
        $parts[] = '겜냥미션 ' . $gem . '%';
    }
    $label = $total > 0
        ? ('마켓 ' . $total . '% 할인' . ($parts ? (' (' . implode(' · ', $parts) . ')') : ''))
        : '';
    return [
        'total_pct' => $total,
        'bar_pct' => $bar,
        'gem_pct' => $gem,
        'gold_count' => $goldCount,
        'label' => $label,
    ];
}

/** 금고 기여 마켓 할인율(%) — 금괴·백금 + 겜냥 전체미션 합산 (닉 없어도 미션 할인 적용) */
function shop_금괴마켓할인율($nick = '') {
    return (int)(shop_마켓할인_상세($nick)['total_pct'] ?? 0);
}

/** 원가에 할인율 적용 (내림) — 대금액은 bcmath/문자열 연산만 사용 */
function shop_금괴할인적용($price, $pct) {
    $price = shop_냥_값($price);
    $pct = (int)$pct;
    if ($pct <= 0) {
        return $price;
    }
    if ($pct >= 100) {
        return '0';
    }
    $remain = 100 - $pct;
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        return bcdiv(bcmul($price, (string)$remain, 0), '100', 0);
    }
    // float/(int) 금지 — 경 단위에서 오버플로우·정밀도 깨짐
    $분자 = shop_문자열_곱하기($price, $remain);
    return shop_문자열_나누기_내림($분자, 100);
}

/** 목록·카드 등 표시용 냥 축약 — 억 미만도 0으로 깎지 않음(만·원 단위 유지) */
function shop_냥_표시($amount) {
    shop_스왑_함수_로드();
    $digits = shop_냥_값($amount);
    if ($digits === '0') {
        return '0';
    }
    // 랭킹 표시: 해/경/조/억/만 전 구간 안전 (냥_조억_축약표시의 억미만→0 회피)
    if (function_exists('랭킹_게임냥표시')) {
        $s = 랭킹_게임냥표시($digits, '');
        $s = trim((string)$s);
        if ($s !== '' && $s !== '0') {
            return $s;
        }
    }
    if (function_exists('bccomp')) {
        if (bccomp($digits, '10000000000000000', 0) >= 0 && function_exists('냥_경조_축약표시')) {
            return 냥_경조_축약표시($digits);
        }
        if (bccomp($digits, '1000000000000', 0) >= 0) {
            return 냥_숫자콤마(bcdiv($digits, '1000000000000', 0)) . '조';
        }
        if (bccomp($digits, '100000000', 0) >= 0) {
            return 냥_숫자콤마(bcdiv($digits, '100000000', 0)) . '억';
        }
        if (bccomp($digits, '10000', 0) >= 0) {
            return 냥_숫자콤마(bcdiv($digits, '10000', 0)) . '만';
        }
        return 냥_숫자콤마($digits);
    }
    $len = strlen($digits);
    if ($len > 16 && function_exists('냥_경조_축약표시')) {
        return 냥_경조_축약표시($digits);
    }
    if ($len > 12) {
        return 냥_숫자콤마(substr($digits, 0, $len - 12)) . '조';
    }
    if ($len > 8) {
        return 냥_숫자콤마(substr($digits, 0, $len - 8)) . '억';
    }
    if ($len > 4) {
        return 냥_숫자콤마(substr($digits, 0, $len - 4)) . '만';
    }
    return 냥_숫자콤마($digits);
}

/** JS에 넘길 냥 숫자 — (int) 캐스팅 금지(PHP_INT_MAX≈922경 초과 시 잘림) */
function shop_js_냥수($amount) {
    return json_encode(shop_냥_값($amount), JSON_UNESCAPED_UNICODE);
}

/** 등록·수정 미리보기용 JS 축약 함수 */
function shop_냥_표시_js() {
    return <<<'JS'
function shopNyangDigits(n) {
  return String(n ?? '0').replace(/[^\d]/g, '') || '0';
}

/** 권면가(원) × 1만원당냥 ÷ 10000 — BigInt (서버 bcdiv 내림과 동일) */
function shopFaceToNyang(faceValue, per10000) {
  const face = Math.max(0, parseInt(faceValue, 10) || 0);
  const per = shopNyangDigits(per10000);
  if (face <= 0 || per === '0') return '0';
  if (typeof BigInt !== 'undefined') {
    return String((BigInt(face) * BigInt(per)) / 10000n);
  }
  // BigInt 없음 — 안전정수 범위만 Number
  const p = Number(per);
  if (!Number.isFinite(p) || p > Number.MAX_SAFE_INTEGER) return '0';
  return String(Math.floor(face / 10000 * p));
}

function shopFormatNyangShort(n) {
  const raw = shopNyangDigits(n);

  if (typeof BigInt !== 'undefined') {
    let v = BigInt(raw);
    const Hae = 100000000000000000000n; // 1해 = 10^20 = 1만경
    const Gn = 10000000000000000n;      // 1경
    const Jn = 1000000000000n;          // 1조
    const En = 100000000n;
    const Mn = 10000n;
    const fmtB = (x) => x.toLocaleString('ko-KR');
    if (v >= Hae) {
      const h = v / Hae;
      const g = (v % Hae) / Gn;
      const j = (v % Gn) / Jn;
      let s = fmtB(h) + '해';
      if (g > 0n) s += fmtB(g) + '경';
      if (j > 0n) s += ' ' + fmtB(j) + '조';
      return s;
    }
    if (v >= Gn) {
      const g = v / Gn;
      const j = (v % Gn) / Jn;
      let s = fmtB(g) + '경';
      if (j > 0n) s += ' ' + fmtB(j) + '조';
      return s;
    }
    if (v >= Jn) return fmtB(v / Jn) + '조';
    if (v >= En) return fmtB(v / En) + '억';
    if (v >= Mn) return fmtB(v / Mn) + '만';
    return fmtB(v);
  }

  let num = Number(raw);
  if (!Number.isFinite(num)) num = 0;
  num = Math.floor(num);
  const Haei = 1e20;
  const Gi = 1e16;
  const Ji = 1e12;
  const Ei = 1e8;
  const Mi = 1e4;
  if (num >= Haei) {
    const h = Math.floor(num / Haei);
    const g = Math.floor((num % Haei) / Gi);
    const j = Math.floor((num % Gi) / Ji);
    let s = h.toLocaleString('ko-KR') + '해';
    if (g > 0) s += g.toLocaleString('ko-KR') + '경';
    if (j > 0) s += ' ' + j.toLocaleString('ko-KR') + '조';
    return s;
  }
  if (num >= Gi) {
    const g = Math.floor(num / Gi);
    const j = Math.floor((num % Gi) / Ji);
    let s = g.toLocaleString('ko-KR') + '경';
    if (j > 0) s += ' ' + j.toLocaleString('ko-KR') + '조';
    return s;
  }
  if (num >= Ji) return Math.floor(num / Ji).toLocaleString('ko-KR') + '조';
  if (num >= Ei) return Math.floor(num / Ei).toLocaleString('ko-KR') + '억';
  if (num >= Mi) return Math.floor(num / Mi).toLocaleString('ko-KR') + '만';
  return num.toLocaleString('ko-KR');
}

function shopNyCompare(a, b) {
  const da = shopNyangDigits(a);
  const db = shopNyangDigits(b);
  if (typeof BigInt !== 'undefined' && (da.length > 15 || db.length > 15)) {
    const av = BigInt(da);
    const bv = BigInt(db);
    if (av < bv) return -1;
    if (av > bv) return 1;
    return 0;
  }
  const na = Number(da);
  const nb = Number(db);
  if (na < nb) return -1;
  if (na > nb) return 1;
  return 0;
}
JS;
}

function shop_점검중() {
    return defined('SHOP_점검모드') && SHOP_점검모드;
}

function shop_점검_허용닉인가($nick) {
    $허용 = defined('SHOP_점검_허용닉') ? trim((string)SHOP_점검_허용닉) : '민호';
    $nick = trim((string)$nick);
    if ($nick === '') {
        return false;
    }
    if (function_exists('getTwoCharNick')) {
        $두글자 = getTwoCharNick($nick);
        if ($두글자 !== '') {
            $nick = $두글자;
        }
    }
    return $nick === $허용;
}

function shop_점검_메시지() {
    $허용 = defined('SHOP_점검_허용닉') ? trim((string)SHOP_점검_허용닉) : '민호';
    return "🔧 마켓 점검 중입니다.\n잠시 후 다시 이용해주세요.\n(점검 담당: {$허용})";
}

function shop_요청이_JSON인가() {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '_') === 0 && $script !== '_image.php') {
        return true;
    }
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }
    $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $xhr === 'xmlhttprequest';
}

function shop_점검_페이지_출력() {
    $msg = htmlspecialchars(shop_점검_메시지(), ENT_QUOTES, 'UTF-8');
    $msgHtml = nl2br($msg);
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>마켓 점검 중</title>';
    echo '<style>
      body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
        background:linear-gradient(160deg,#1a1520 0%,#2a2035 50%,#1e2430 100%);
        font-family:-apple-system,BlinkMacSystemFont,"Apple SD Gothic Neo",sans-serif;color:#f5f0ea;}
      .box{max-width:22rem;margin:1.5rem;padding:2rem 1.5rem;text-align:center;
        background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;}
      .emoji{font-size:2.5rem;margin-bottom:.75rem}
      h1{font-size:1.25rem;margin:0 0 .75rem;font-weight:700}
      p{margin:0;line-height:1.55;opacity:.88;font-size:.95rem;white-space:pre-line}
    </style></head><body>';
    echo '<div class="box"><div class="emoji">🔧</div><h1>마켓 점검 중</h1>';
    echo '<p>' . $msgHtml . '</p></div></body></html>';
}

/** 점검 모드: 민호만 통과 · 크론(SHOP_SKIP_MAINTENANCE_GATE) 제외 */
function shop_점검_게이트() {
    if (!shop_점검중()) {
        return;
    }
    if (defined('SHOP_SKIP_MAINTENANCE_GATE') && SHOP_SKIP_MAINTENANCE_GATE) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        return;
    }

    $code = '';
    if (isset($_POST['code'])) {
        $code = trim((string)$_POST['code']);
    }
    if ($code === '' && isset($_GET['code'])) {
        $code = trim((string)$_GET['code']);
    }
    if ($code === '' && function_exists('shop_코드_쿠키_읽기')) {
        $code = shop_코드_쿠키_읽기();
    }

    $회원 = ($code !== '') ? shop_auth($code) : null;
    if ($회원 && shop_점검_허용닉인가($회원['nick'] ?? '')) {
        return;
    }

    if (shop_요청이_JSON인가()) {
        if (function_exists('shop_json')) {
            shop_json(['ok' => false, 'msg' => shop_점검_메시지()]);
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(503);
        echo json_encode(['ok' => false, 'msg' => shop_점검_메시지()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    shop_점검_페이지_출력();
    exit;
}

function shop_코드없음_메시지() {
    return '연구실에서 코드를 발급받아, 코드가 포함된 링크로 1회 접속해주세요.';
}

function shop_코드_쿠키_읽기() {
    if (!isset($_COOKIE[SHOP_CODE_COOKIE_NAME])) {
        return '';
    }
    return trim((string)$_COOKIE[SHOP_CODE_COOKIE_NAME]);
}

function shop_코드_쿠키_저장($code) {
    $code = trim((string)$code);
    if ($code === '') {
        return false;
    }
    return setcookie(
        SHOP_CODE_COOKIE_NAME,
        $code,
        time() + SHOP_CODE_COOKIE_TTL,
        '/shop/',
        '',
        false,
        true
    );
}

function shop_코드_쿠키_삭제() {
    setcookie(SHOP_CODE_COOKIE_NAME, '', time() - 3600, '/shop/', '', false, true);
}

function shop_코드_리다이렉트($code) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/shop/';
    $path = parse_url($uri, PHP_URL_PATH);
    if ($path === '' || $path === false) {
        $path = '/shop/';
    }
    $query = $_GET;
    $query['code'] = $code;
    $qs = http_build_query($query);
    header('Location: ' . $path . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
}

/** GET code 우선 · 유효 code 접속 시 7일 쿠키 · 쿠키만 있으면 ?code= 로 리다이렉트 */
function shop_코드_해석() {
    $get_code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';

    if ($get_code !== '') {
        if (shop_auth($get_code)) {
            shop_코드_쿠키_저장($get_code);
        }
        return $get_code;
    }

    $cookie_code = shop_코드_쿠키_읽기();
    if ($cookie_code !== '') {
        if (shop_auth($cookie_code)) {
            shop_코드_리다이렉트($cookie_code);
        }
        shop_코드_쿠키_삭제();
    }

    return '';
}

function shop_코드_게이트_스타일() {
    return '';
}

function shop_stylesheet_tag() {
    return '<link rel="stylesheet" href="/shop/shop.css?v=18">';
}

function shop_코드_게이트_출력() {
    $msg = htmlspecialchars(shop_코드없음_메시지(), ENT_QUOTES, 'UTF-8');
    echo '<div class="shop-code-gate" role="dialog" aria-modal="true">';
    echo '<div class="shop-code-gate-box">';
    echo '<div class="emoji">🔐</div>';
    echo '<h2>접속 코드가 필요해요</h2>';
    echo '<p>' . $msg . '</p>';
    echo '</div></div>';
}

function shop_회원_인증($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') {
        shop_json(['ok' => false, 'msg' => shop_코드없음_메시지()]);
    }
    $회원 = shop_auth($code);
    if (!$회원) {
        shop_json(['ok' => false, 'msg' => '인증코드가 올바르지 않습니다.']);
    }
    if (shop_점검중() && !shop_점검_허용닉인가($회원['nick'] ?? '')) {
        shop_json(['ok' => false, 'msg' => shop_점검_메시지()]);
    }
    return $회원;
}

function shop_가격갱신시각_표시($dt) {
    $dt = trim((string)$dt);
    if ($dt === '' || $dt === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false || $ts <= 0) {
        return '';
    }
    return date('Y-m-d H:i', $ts);
}

/**
 * 유통기한(YYYY-MM-DD) → 남은 일수 문구
 * 예: "15일 남음" · "오늘까지" · "기한 만료"
 */
function shop_유통기한_남은일수_문구($expire_date) {
    $expire_date = trim((string)$expire_date);
    if ($expire_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $expire_date)) {
        return '';
    }
    $expire_ymd = substr($expire_date, 0, 10);
    $expire_ts = strtotime($expire_ymd . ' 00:00:00');
    $today_ts = strtotime(date('Y-m-d') . ' 00:00:00');
    if ($expire_ts === false || $today_ts === false) {
        return '';
    }
    $days = (int)floor(($expire_ts - $today_ts) / 86400);
    if ($days < 0) {
        return '기한 만료';
    }
    if ($days === 0) {
        return '오늘까지';
    }
    return $days . '일 남음';
}

/** 유효기간 + 남은 일수 (상품리스트용) */
function shop_유통기한_표시($expire_date) {
    $expire_date = trim((string)$expire_date);
    if ($expire_date === '') {
        return '';
    }
    $left = shop_유통기한_남은일수_문구($expire_date);
    if ($left === '') {
        return '유효 ~' . $expire_date;
    }
    return '유효 ~' . $expire_date . ' · ' . $left;
}

function shop_상품목록_조회() {
    shop_매입컬럼_보장();
    // 선매입 대기(민호용: sale · prebuy=0) → 맨 위(최신순)
    // 선매입 끝난 상품·예약·판매완료 → 기존처럼 status 후 RAND()
    $rs = @db_query("SELECT g.*, CAST(g.price_nyang AS CHAR) AS price_nyang
        FROM tb_gifticon g
        WHERE g.status IN ('sale','reserved','sold')
        ORDER BY
          CASE WHEN g.status = 'sale' AND IFNULL(g.prebuy, 0) = 0 THEN 0 ELSE 1 END ASC,
          CASE WHEN g.status = 'sale' AND IFNULL(g.prebuy, 0) = 0 THEN g.idx ELSE 0 END DESC,
          FIELD(g.status, 'sale', 'reserved', 'sold'),
          RAND()
        LIMIT 200");
    if (!$rs) {
        return [];
    }
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $cat = $row['category'] ?? 'other';
        $판매자 = trim((string)($row['seller_nick'] ?? ''));
        $market_tax = shop_판매자_market_tax($판매자);
        $face_value = (int)$row['face_value'];
        $price_updated_at = (string)($row['price_updated_at'] ?? '');
        $price = shop_냥_값($row['price_nyang'] ?? 0);
        $status = (string)($row['status'] ?? '');
        // DB가 0이거나 판매중·예약중이면 실시간 환산 (깨진 0 덮어쓰기 복구)
        if ($face_value > 0 && ($price === '0' || $status === 'sale' || $status === 'reserved')) {
            $live = shop_냥_값(shop_원화_냥환산($face_value, $판매자));
            if ($live !== '0') {
                $price = $live;
            }
        }
        // 재구매가 = 등록가 × (market_tax / 기본 2.5) — 5%면 ×2
        $price_self = ($price !== '0')
          ? shop_냥_값(shop_본인재구매가_스케일($price, $market_tax))
          : (($face_value > 0)
              ? shop_냥_값(shop_본인재구매_원화_냥환산($face_value, $판매자))
              : '0');
        $price_np = shop_상품_표시_본방냥($row, $판매자);
        if (shop_냥_값($price_np) === '0' && $face_value > 0) {
            $price_np = shop_매입_권면가_newpoint($face_value, $판매자);
        }
        $목록[] = [
            'id'         => (int)$row['idx'],
            'brand'      => $row['brand'],
            'name'       => $row['name'],
            'face_value' => $face_value,
            'price'      => $price,
            'price_self' => $price_self,
            'price_np'   => $price_np,
            'price_updated_at' => $price_updated_at,
            'price_updated_fmt' => shop_가격갱신시각_표시($price_updated_at),
            'seller'     => $판매자,
            'market_tax' => $market_tax,
            'prebuy_np_per_10000' => 0,
            'category'   => $cat,
            'emoji'      => $row['emoji'] ?: shop_카테고리_이모지($cat),
            'expire'     => $row['expire_date'],
            'status'     => $status,
            'prebuy'     => (int)($row['prebuy'] ?? 0),
        ];
    }
    return $목록;
}

/** status=sale 만 (채팅 .주문 등) */
function shop_판매중_조회($limit = 30) {
    $limit = max(1, min(100, (int)$limit));
    $rs = @db_query("SELECT idx, brand, name, face_value, price_nyang, seller_nick, category, emoji, expire_date
        FROM tb_gifticon WHERE status = 'sale' ORDER BY idx DESC LIMIT {$limit}");
    if (!$rs) {
        return [];
    }
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $cat = $row['category'] ?? 'other';
        $목록[] = [
            'id'         => (int)$row['idx'],
            'brand'      => $row['brand'] ?? '',
            'name'       => $row['name'] ?? '',
            'face_value' => (int)($row['face_value'] ?? 0),
            'price'      => shop_냥_값($row['price_nyang'] ?? 0),
            'seller'     => $row['seller_nick'] ?? '',
            'category'   => $cat,
            'emoji'      => ($row['emoji'] ?? '') ?: shop_카테고리_이모지($cat),
            'expire'     => $row['expire_date'] ?? '',
        ];
    }
    return $목록;
}

/** 본방 .주문 — 판매중 상품명 목록 */
function shop_채팅_주문목록_문구() {
    $목록 = shop_판매중_조회(30);
    $msg = '🛒 도하 마켓 — 판매중';
    if (empty($목록)) {
        $msg .= "\n\n지금 판매 중인 상품이 없어요.";
        $msg .= "\n" . shop_마켓_공개url();
        return $msg;
    }
    $cnt = count($목록);
    // .랭킹1 과 동일: 10개 미만이면 맨 위 공백 패딩(카톡 펼침용)
    
    $msg .= ' (' . $cnt . ")\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";

    $i = 0;
    foreach ($목록 as $상품) {
        $i++;
        $이름 = trim((string)$상품['name']);
        $브랜드 = trim((string)$상품['brand']);
        if ($브랜드 !== '') {
            $이름 = $브랜드 . ' ' . $이름;
        }
        $기한 = shop_유통기한_남은일수_문구($상품['expire'] ?? '');
        $기한문구 = ($기한 !== '') ? " · {$기한}" : '';
        $msg .= "{$i}) {$상품['emoji']} {$이름}{$기한문구}\n";
    }
    $msg .= "\n" . shop_마켓_공개url();
    return $msg;
}

/** 마켓 등록 권면가 합계 상위 (채팅 .마켓큰손) */
function shop_마켓큰손_조회($limit = 10) {
    $limit = max(1, min(30, (int)$limit));
    $rs = @db_query("
        SELECT seller_nick,
               COUNT(*) AS cnt,
               COALESCE(SUM(face_value), 0) AS total_face
        FROM tb_gifticon
        WHERE status IN ('sale', 'sold', 'reserved')
        GROUP BY seller_nick
        ORDER BY total_face DESC, cnt DESC, seller_nick ASC
        LIMIT {$limit}
    ");
    if (!$rs) {
        return [];
    }
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $nick = trim((string)($row['seller_nick'] ?? ''));
        if ($nick === '') {
            continue;
        }
        $total_face = (int)($row['total_face'] ?? 0);
        // 표시 세율은 회원 피크 기준 (현재 합계만 쓰면 삭제 후 내려보임)
        $tax = shop_판매자_market_tax($nick);
        $목록[] = [
            'nick'       => $nick,
            'cnt'        => (int)($row['cnt'] ?? 0),
            'total_face' => $total_face,
            'market_tax' => $tax,
            'market_tax_disp' => shop_market_tax_표시($tax),
        ];
    }
    return $목록;
}

/** 채팅 .마켓큰손 — 권면가 합계 상위 판매자 */
function shop_채팅_마켓큰손_문구($limit = 10) {
    $limit = max(1, min(30, (int)$limit));
    $목록 = shop_마켓큰손_조회($limit);
    $msg = '🏪 도하 마켓 — 큰손 랭킹';
    $msg .= "\n(누적 등록 권면가 합계 · 상위 {$limit}명)";
    if (empty($목록)) {
        $msg .= "\n\n아직 등록 내역이 없어요.";
        $msg .= "\n" . shop_마켓_공개url();
        return $msg;
    }
    $msg .= "\n\n";
    $rank = 1;
    foreach ($목록 as $행) {
        $tax = $행['market_tax_disp'] ?? shop_market_tax_표시($행['market_tax'] ?? SHOP_매입_기본_MARKET_TAX);
        $msg .= "{$rank}등 {$행['nick']}({$tax}) — " . number_format($행['total_face']) . "원 ({$행['cnt']}건)\n";
        $rank++;
    }
    $msg .= "\n" . shop_마켓_공개url();
    return $msg;
}

function shop_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 구매·시세조회용 — 권면가 기준 실시간 게임냥가
 * @param array $상품행 tb_gifticon 행 (face_value, price_nyang, seller_nick, …)
 * @param string $구매닉
 * @param bool $force_live_total 직전 결제 등 후 총량 캐시 무효화
 * @return array{ok:bool,msg?:string,list_price:string,pay_price:string,self_buy:bool,market_tax:float,gold_pct:int,gold_count:int,face_value:int}
 */
function shop_상품_실시간구매가($상품행, $구매닉, $force_live_total = false) {
    if ($force_live_total) {
        shop_실시간_총량조회(true);
    }
    $구매닉 = trim((string)$구매닉);
    $face_value = (int)($상품행['face_value'] ?? 0);
    if ($face_value < 1) {
        return ['ok' => false, 'msg' => '권면가 정보가 올바르지 않습니다.'];
    }
    $판매자 = trim((string)($상품행['seller_nick'] ?? $상품행['seller'] ?? ''));
    $본인재구매 = ($구매닉 !== '' && $판매자 !== '' && $판매자 === $구매닉);
    $판매자_tax = shop_판매자_market_tax($판매자);

    // 판매등록 실효요율 실시간가 (전체게임냥 변동 즉시 반영)
    $list_price = shop_냥_값(shop_원화_냥환산($face_value, $판매자));
    if ($list_price === '0') {
        $fallback = shop_냥_값($상품행['price_nyang'] ?? 0);
        if ($fallback !== '0') {
            $list_price = $fallback;
        }
    }
    if ($본인재구매) {
        $list_price = shop_냥_값(shop_본인재구매_원화_냥환산($face_value, $판매자, $list_price));
    }
    if (shop_냥_비교($list_price, '1') < 0) {
        return ['ok' => false, 'msg' => '상품 가격이 올바르지 않습니다.'];
    }

    $할인상세 = shop_마켓할인_상세($구매닉);
    $금괴개수 = (int)($할인상세['gold_count'] ?? 0);
    $금괴할인율 = (int)($할인상세['total_pct'] ?? 0);
    $pay_price = $list_price;
    if ($금괴할인율 > 0) {
        $pay_price = shop_금괴할인적용($list_price, $금괴할인율);
        if (shop_냥_비교($pay_price, '1') < 0) {
            $pay_price = '1';
        }
    }

    return [
        'ok' => true,
        'list_price' => $list_price,
        'pay_price' => $pay_price,
        'self_buy' => $본인재구매,
        'market_tax' => $판매자_tax,
        'gold_pct' => $금괴할인율,
        'gold_count' => $금괴개수,
        'bar_discount_pct' => (int)($할인상세['bar_pct'] ?? 0),
        'gem_discount_pct' => (int)($할인상세['gem_pct'] ?? 0),
        'discount_label' => (string)($할인상세['label'] ?? ''),
        'face_value' => $face_value,
        'seller' => $판매자,
    ];
}

/**
 * 판매중·예약중 상품의 실시간 겜냥가 맵 (+ DB 동기화)
 * @return array{items:array,updated:int}
 */
function shop_판매중_실시간시세_동기화($동기화 = true, $구매닉 = '') {
    shop_실시간_총량조회(true);
    shop_매입컬럼_보장();
    $할인상세 = shop_마켓할인_상세($구매닉);
    $금괴할인율 = (int)($할인상세['total_pct'] ?? 0);
    $rs = @db_query("SELECT idx, seller_nick, face_value, CAST(price_nyang AS CHAR) AS price_nyang, status
        FROM tb_gifticon WHERE status IN ('sale', 'reserved') ORDER BY idx DESC");
    $items = [];
    $updated = 0;
    if (!$rs) {
        return ['items' => $items, 'updated' => 0, 'discount' => $할인상세];
    }
    while ($row = db_fetch($rs)) {
        $id = (int)($row['idx'] ?? 0);
        $face = (int)($row['face_value'] ?? 0);
        $seller = trim((string)($row['seller_nick'] ?? ''));
        $tax = shop_판매자_market_tax($seller);
        $price = ($face > 0) ? shop_냥_값(shop_원화_냥환산($face, $seller)) : '0';
        $old = shop_냥_값($row['price_nyang'] ?? 0);
        if ($price === '0' && $old !== '0') {
            $price = $old;
        }
        $price_self = ($price !== '0')
            ? shop_냥_값(shop_본인재구매가_스케일($price, $tax))
            : '0';
        $price_pay = $price;
        $price_self_pay = $price_self;
        if ($금괴할인율 > 0) {
            $price_pay = shop_금괴할인적용($price, $금괴할인율);
            $price_self_pay = shop_금괴할인적용($price_self, $금괴할인율);
            if (shop_냥_비교($price_pay, '1') < 0) {
                $price_pay = '1';
            }
            if (shop_냥_비교($price_self_pay, '1') < 0) {
                $price_self_pay = '1';
            }
        }
        if ($동기화 && $id > 0 && $price !== '0' && $price !== $old) {
            if (@db_query("UPDATE tb_gifticon SET price_nyang = {$price}, price_updated_at = NOW()
                WHERE idx = {$id} AND status IN ('sale', 'reserved') LIMIT 1")) {
                $updated++;
            }
        } elseif ($동기화 && $id > 0 && $price !== '0') {
            @db_query("UPDATE tb_gifticon SET price_updated_at = NOW()
                WHERE idx = {$id} AND status IN ('sale', 'reserved') LIMIT 1");
        }
        $items[] = [
            'id' => $id,
            'price' => $price,
            'price_pay' => $price_pay,
            'price_self' => $price_self,
            'price_self_pay' => $price_self_pay,
            'price_disp' => shop_냥_표시($price_pay),
            'price_list_disp' => shop_냥_표시($price),
            'discount_pct' => $금괴할인율,
            'bar_discount_pct' => (int)($할인상세['bar_pct'] ?? 0),
            'gem_discount_pct' => (int)($할인상세['gem_pct'] ?? 0),
            'discount_label' => (string)($할인상세['label'] ?? ''),
            'market_tax' => $tax,
        ];
    }
    return ['items' => $items, 'updated' => $updated, 'discount' => $할인상세];
}

/** 게임방(info2) 알림 큐 — 등록·구매·선매입 */
function shop_채팅알림_등록($msg, $item = '도하마켓') {
    $msg = trim((string)$msg);
    if ($msg === '') {
        return false;
    }
    if (!function_exists('info2알림_등록')) {
        shop_스왑_함수_로드();
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

function shop_구매알림_등록($구매자, $상품행, $pay_type = 'point') {
    $상품명 = trim((string)($상품행['name'] ?? ''));
    $emoji = trim((string)($상품행['emoji'] ?? '🎫'));
    if ($emoji === '') {
        $emoji = '🎫';
    }
    if ($상품명 === '') {
        $상품명 = '기프티콘';
    }

    $결제라벨 = ($pay_type === 'newpoint') ? '본냥' : '겜냥';
    $msg = "{$emoji} 도하 마켓 — {$상품명} 구매 완료! ({$결제라벨})";

    $item = 'gifticon_' . (int)($상품행['idx'] ?? 0);
    return shop_채팅알림_등록($msg, $item);
}

function shop_등록알림_등록($판매자, $상품정보) {
    $판매자 = trim((string)$판매자);
    $상품명 = trim((string)($상품정보['name'] ?? ''));
    $가격 = shop_냥_값($상품정보['price_nyang'] ?? 0);
    $건수 = max(1, (int)($상품정보['count'] ?? 1));
    $emoji = trim((string)($상품정보['emoji'] ?? '🎫'));
    if ($emoji === '') {
        $emoji = '🎫';
    }

    $msg = "{$emoji} 도하 마켓 신규 등록!\n";
    $msg .= "판매자: {$판매자}\n";
    if ($건수 > 1) {
        $msg .= "상품: {$상품명} {$건수}건\n";
        $msg .= "가격: " . shop_냥_표시($가격) . "게임냥/건";
    } else {
        $msg .= "상품: {$상품명}\n";
        $msg .= "가격: " . shop_냥_표시($가격) . "게임냥";
    }

    $item = $건수 > 1
        ? ('gifticon_reg_batch_' . (int)($상품정보['idx'] ?? 0))
        : ('gifticon_reg_' . (int)($상품정보['idx'] ?? 0));
    return shop_채팅알림_등록($msg, $item);
}

function shop_이미지_물리경로($filename) {
    $filename = basename((string)$filename);
    if ($filename === '') {
        return '';
    }
    return rtrim(SHOP_이미지_업로드_경로, '/') . '/' . $filename;
}

function shop_다중이미지_정규화($files) {
    if (empty($files) || !is_array($files)) {
        return [];
    }

    if (isset($files['tmp_name']) && !is_array($files['tmp_name'])) {
        if ((int)($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        return [$files];
    }

    $result = [];
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        return [];
    }

    foreach ($names as $i => $name) {
        if ((int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $result[] = [
            'name'     => $files['name'][$i] ?? '',
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }

    return $result;
}

function shop_이미지_업로드($file) {
    if (empty($file) || !is_array($file)) {
        return ['ok' => false, 'msg' => '기프티콘 이미지를 첨부해주세요.'];
    }
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'msg' => '기프티콘 이미지를 첨부해주세요.'];
    }
    if ((int)($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => '이미지 업로드 중 오류가 발생했습니다.'];
    }

    $maxSize = 5 * 1024 * 1024;
    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize > $maxSize) {
        return ['ok' => false, 'msg' => '이미지 크기는 5MB 이하여야 합니다.'];
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'msg' => 'jpg, png, gif, webp만 업로드할 수 있습니다.'];
    }

    $dir = rtrim(SHOP_이미지_업로드_경로, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'msg' => '업로드 디렉토리를 생성할 수 없습니다.'];
    }
    if (!is_writable($dir)) {
        return ['ok' => false, 'msg' => '업로드 디렉토리에 쓰기 권한이 없습니다.'];
    }

    $randomFileName = (function_exists('업로드_파일명_랜덤') ? 업로드_파일명_랜덤() : bin2hex(random_bytes(8))) . '.' . $ext;
    $uploadFile = $dir . '/' . $randomFileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
        return ['ok' => false, 'msg' => '이미지 파일 저장에 실패했습니다.'];
    }
    chmod($uploadFile, 0644);

    return ['ok' => true, 'file' => $randomFileName];
}

function shop_구매목록_조회($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return [];
    }
    shop_사용완료컬럼_보장();
    shop_추출코드컬럼_보장();
    shop_매입컬럼_보장();
    $nick_esc = addslashes($nick);
    // price_nyang/paid_* CAST — DECIMAL(40) SELECT * 시 mysqli float 깨짐 방지
    $rs = @db_query("SELECT g.*,
            CAST(IFNULL(g.price_nyang, 0) AS CHAR) AS price_nyang,
            CAST(IFNULL(g.paid_nyang, 0) AS CHAR) AS paid_nyang,
            CAST(IFNULL(g.price_newpoint, 0) AS CHAR) AS price_newpoint,
            CAST(IFNULL(g.paid_newpoint, 0) AS CHAR) AS paid_newpoint
        FROM tb_gifticon g
        WHERE g.buyer_nick = '{$nick_esc}' AND g.status = 'sold'
        ORDER BY g.sold_at DESC, g.idx DESC
        LIMIT 100");
    if (!$rs) {
        return [];
    }
    return shop_목록_가공($rs);
}

function shop_구매통계($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['count' => 0, 'total_won' => 0, 'total_nyang' => '0'];
    }
    $nick_esc = addslashes($nick);
    $row = db_select("SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(face_value), 0) AS total_won,
        CAST(COALESCE(SUM(CAST(IFNULL(price_nyang, 0) AS DECIMAL(40,0))), 0) AS CHAR) AS total_nyang
        FROM tb_gifticon WHERE buyer_nick = '{$nick_esc}' AND status = 'sold'");
    return [
        'count'       => (int)($row['cnt'] ?? 0),
        'total_won'   => (int)($row['total_won'] ?? 0),
        'total_nyang' => shop_냥_값($row['total_nyang'] ?? 0),
    ];
}

function shop_판매목록_조회($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return [];
    }
    shop_매입컬럼_보장();
    $nick_esc = addslashes($nick);
    $rs = @db_query("SELECT g.*,
            CAST(IFNULL(g.price_nyang, 0) AS CHAR) AS price_nyang,
            CAST(IFNULL(g.paid_nyang, 0) AS CHAR) AS paid_nyang,
            CAST(IFNULL(g.price_newpoint, 0) AS CHAR) AS price_newpoint,
            CAST(IFNULL(g.paid_newpoint, 0) AS CHAR) AS paid_newpoint
        FROM tb_gifticon g
        WHERE g.seller_nick = '{$nick_esc}' AND g.status IN ('sale', 'reserved', 'sold')
        ORDER BY FIELD(g.status, 'sale', 'reserved', 'sold'),
                 IF(g.status = 'sold', g.sold_at, g.regdate) DESC,
                 g.idx DESC
        LIMIT 100");
    if (!$rs) {
        return [];
    }
    return shop_판매목록_가공($rs);
}

/** 특정 인증코드에서만 친구 전체 판매내역 탭 노출 */
function shop_전체판매_열람가능($code) {
    $code = trim((string)$code);
    return $code !== '' && $code === SHOP_전체판매_열람코드;
}

function shop_전체판매목록_조회($limit = 200) {
    shop_매입컬럼_보장();
    shop_추출코드컬럼_보장();
    $limit = max(1, min(500, (int)$limit));
    $rs = @db_query("SELECT g.*,
            CAST(IFNULL(g.price_nyang, 0) AS CHAR) AS price_nyang,
            CAST(IFNULL(g.paid_nyang, 0) AS CHAR) AS paid_nyang,
            CAST(IFNULL(g.price_newpoint, 0) AS CHAR) AS price_newpoint,
            CAST(IFNULL(g.paid_newpoint, 0) AS CHAR) AS paid_newpoint
        FROM tb_gifticon g
        WHERE g.status IN ('sale', 'reserved', 'sold')
        ORDER BY FIELD(g.status, 'sale', 'reserved', 'sold'),
                 IF(g.status = 'sold', g.sold_at, g.regdate) DESC,
                 g.idx DESC
        LIMIT {$limit}");
    if (!$rs) {
        return [];
    }
    return shop_판매목록_가공($rs);
}

function shop_전체판매통계() {
    shop_매입컬럼_보장();
    $row = db_select("SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(CASE WHEN status = 'sold' THEN face_value ELSE 0 END), 0) AS total_won,
        CAST(COALESCE(SUM(CASE WHEN status = 'sold' THEN CAST(IFNULL(price_nyang, 0) AS DECIMAL(40,0)) ELSE 0 END), 0) AS CHAR) AS total_nyang,
        COUNT(DISTINCT seller_nick) AS seller_cnt
        FROM tb_gifticon
        WHERE status IN ('sale', 'reserved', 'sold')");
    return [
        'count'       => (int)($row['cnt'] ?? 0),
        'total_won'   => (int)($row['total_won'] ?? 0),
        'total_nyang' => shop_냥_값($row['total_nyang'] ?? 0),
        'seller_cnt'  => (int)($row['seller_cnt'] ?? 0),
    ];
}

function shop_판매상품_단건($id, $nick, $관리자 = false) {
    $id = (int)$id;
    $nick = trim((string)$nick);
    if ($id < 1 || $nick === '') {
        return null;
    }
    if ($관리자) {
        $row = db_select("SELECT * FROM tb_gifticon WHERE idx = {$id} AND status = 'sale' LIMIT 1");
    } else {
        $nick_esc = addslashes($nick);
        $row = db_select("SELECT * FROM tb_gifticon WHERE idx = {$id} AND seller_nick = '{$nick_esc}' AND status = 'sale' LIMIT 1");
    }
    if (empty($row['idx'])) {
        return null;
    }
    $cat = $row['category'] ?? 'other';
    return [
        'id'         => (int)$row['idx'],
        'name'       => $row['name'],
        'face_value' => (int)$row['face_value'],
        'price'      => shop_냥_값($row['price_nyang']),
        'category'   => $cat,
        'emoji'      => $row['emoji'] ?: shop_카테고리_이모지($cat),
        'expire'     => $row['expire_date'],
        'has_image'  => !empty($row['image_file']),
        'image_file' => $row['image_file'] ?? '',
        'status'     => $row['status'],
        'regdate'    => $row['regdate'] ?? '',
        'prebuy'     => (int)($row['prebuy'] ?? 0),
        'seller'     => trim((string)($row['seller_nick'] ?? '')),
    ];
}

function shop_판매상품_삭제($id, $nick) {
    $id = (int)$id;
    $nick = trim((string)$nick);
    if ($id < 1 || $nick === '') {
        return ['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.'];
    }

    // 마켓 판매중 삭제는 민호만 가능
    if (!shop_삭제_허용($nick)) {
        return ['ok' => false, 'msg' => shop_수정삭제_문의문구()];
    }

    $상품 = shop_판매상품_단건($id, $nick, true);
    if (!$상품) {
        return ['ok' => false, 'msg' => '판매중인 상품을 찾을 수 없습니다.'];
    }

    $판매자 = trim((string)($상품['seller'] ?? ''));
    if ($판매자 === '') {
        $판매자 = $nick;
    }

    $ok = db_query("DELETE FROM tb_gifticon WHERE idx = {$id} AND status = 'sale' LIMIT 1");
    if (!$ok) {
        return ['ok' => false, 'msg' => '삭제에 실패했습니다.'];
    }

    if (!empty($상품['image_file'])) {
        $path = shop_이미지_물리경로($상품['image_file']);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    shop_회원_권면가총액_동기화($판매자);

    $name = trim((string)$상품['name']);
    return ['ok' => true, 'msg' => ($name !== '' ? "{$name} " : '') . '삭제되었습니다.'];
}

function shop_목록_가공($rs) {
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $cat = $row['category'] ?? 'other';
        $used_at = trim((string)($row['used_at'] ?? ''));
        $code_text = shop_추출코드_정규화($row['barcode_text'] ?? '');
        $목록[] = [
            'id'         => (int)$row['idx'],
            'name'       => $row['name'],
            'face_value' => (int)$row['face_value'],
            'price'      => shop_냥_값($row['price_nyang']),
            'paid_nyang' => shop_냥_비교(shop_냥_값($row['paid_nyang'] ?? 0), '1') >= 0
                ? shop_냥_값($row['paid_nyang'])
                : shop_냥_값($row['price_nyang']),
            'price_np'   => (int)($row['paid_newpoint'] ?? 0) > 0
                ? (int)$row['paid_newpoint']
                : shop_상품_표시_본방냥($row),
            'buy_currency' => shop_구매통화_정규화($row['buy_currency'] ?? '', (int)($row['paid_newpoint'] ?? 0)),
            'seller'     => $row['seller_nick'],
            'buyer'      => $row['buyer_nick'] ?? '',
            'emoji'      => $row['emoji'] ?: shop_카테고리_이모지($cat),
            'expire'     => $row['expire_date'],
            'has_image'  => !empty($row['image_file']),
            'sold_at'    => $row['sold_at'] ?? '',
            'used_at'    => $used_at,
            'used'       => ($used_at !== '' && $used_at !== '0000-00-00 00:00:00') ? 1 : 0,
            'code_text'  => $code_text,
        ];
    }
    return $목록;
}

function shop_판매목록_가공($rs) {
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $cat = $row['category'] ?? 'other';
        $status = $row['status'] ?? 'sold';
        $code_text = shop_추출코드_정규화($row['barcode_text'] ?? '');
        $목록[] = [
            'id'         => (int)$row['idx'],
            'name'       => $row['name'],
            'face_value' => (int)$row['face_value'],
            'price'      => shop_냥_값($row['price_nyang']),
            'paid_nyang' => shop_냥_비교(shop_냥_값($row['paid_nyang'] ?? 0), '1') >= 0
                ? shop_냥_값($row['paid_nyang'])
                : shop_냥_값($row['price_nyang']),
            'price_np'   => (int)($row['paid_newpoint'] ?? 0) > 0
                ? (int)$row['paid_newpoint']
                : shop_상품_표시_본방냥($row),
            'buy_currency' => shop_구매통화_정규화($row['buy_currency'] ?? '', (int)($row['paid_newpoint'] ?? 0)),
            'buyer'      => $row['buyer_nick'] ?? '',
            'emoji'      => $row['emoji'] ?: shop_카테고리_이모지($cat),
            'expire'     => $row['expire_date'],
            'category'   => $cat,
            'status'     => $status,
            'settled'    => (int)($row['settled'] ?? 0),
            'sold_at'    => $row['sold_at'] ?? '',
            'regdate'    => $row['regdate'] ?? '',
            'prebuy'     => (int)($row['prebuy'] ?? 0),
            'seller'     => trim((string)($row['seller_nick'] ?? '')),
            'has_image'  => !empty($row['image_file']),
            'code_text'  => $code_text,
        ];
    }
    return $목록;
}

function shop_구매통화_정규화($currency, $paid_newpoint = 0) {
    $currency = strtolower(trim((string)$currency));
    if ($currency === 'newpoint') {
        return 'newpoint';
    }
    if ($currency === '' && (int)$paid_newpoint > 0) {
        return 'newpoint';
    }
    return 'point';
}

function shop_정산대기_합계($nick, $currency = 'point') {
    shop_매입컬럼_보장();
    $nick = trim((string)$nick);
    if ($nick === '') {
        return '0';
    }
    $nick_esc = addslashes($nick);
    $currency = shop_구매통화_정규화($currency);
    if ($currency === 'newpoint') {
        $row = db_select("SELECT CAST(COALESCE(SUM(paid_newpoint), 0) AS CHAR) AS total
            FROM tb_gifticon
            WHERE seller_nick = '{$nick_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = 'newpoint'");
    } else {
        $row = db_select("SELECT CAST(COALESCE(SUM(CAST(IFNULL(price_nyang, 0) AS DECIMAL(40,0))), 0) AS CHAR) AS total
            FROM tb_gifticon
            WHERE seller_nick = '{$nick_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = 'point'");
    }
    return shop_냥_값($row['total'] ?? 0);
}

function shop_정산_실지급액($gross) {
    $gross = shop_냥_값($gross);
    if (shop_냥_비교($gross, '0') <= 0) {
        return '0';
    }
    // 실수령 = 총액 × (1 - 수수료율) 내림 — float/(int) 금지
    $pct = max(0, min(10000, (int)round((1.0 - (float)SHOP_정산_수수료율) * 10000)));
    if ($pct <= 0) {
        return '0';
    }
    if ($pct >= 10000) {
        return $gross;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        return bcdiv(bcmul($gross, (string)$pct, 0), '10000', 0);
    }
    return shop_문자열_나누기_내림(shop_문자열_곱하기($gross, $pct), 10000);
}

function shop_정산_소멸액($gross) {
    $gross = shop_냥_값($gross);
    $실 = shop_정산_실지급액($gross);
    if (function_exists('bcsub')) {
        $diff = bcsub($gross, $실, 0);
        return (function_exists('bccomp') && bccomp($diff, '0', 0) < 0) ? '0' : $diff;
    }
    // 폴백 (소액만)
    $g = (int)$gross;
    $n = (int)$실;
    return (string)max(0, $g - $n);
}

/** 게임냥 구매액 중 판매자 즉시 환급액 (기본 30%) */
function shop_겜냥구매_판매자환급액($gross) {
    $gross = shop_냥_값($gross);
    if (shop_냥_비교($gross, '0') <= 0) {
        return '0';
    }
    $pct = max(0, min(10000, (int)round((float)SHOP_겜냥구매_판매자환급율 * 10000)));
    if ($pct <= 0) {
        return '0';
    }
    if ($pct >= 10000) {
        return $gross;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        return bcdiv(bcmul($gross, (string)$pct, 0), '10000', 0);
    }
    return shop_문자열_나누기_내림(shop_문자열_곱하기($gross, $pct), 10000);
}

/** 게임냥 구매액 중 소멸액 (환급 제외분) */
function shop_겜냥구매_소멸액($gross) {
    $gross = shop_냥_값($gross);
    $환급 = shop_겜냥구매_판매자환급액($gross);
    if (function_exists('bcsub')) {
        $diff = bcsub($gross, $환급, 0);
        return (function_exists('bccomp') && bccomp($diff, '0', 0) < 0) ? '0' : $diff;
    }
    $g = (int)$gross;
    $n = (int)$환급;
    return (string)max(0, $g - $n);
}

function shop_정산대기_건수($nick, $currency = 'point') {
    shop_매입컬럼_보장();
    $nick = trim((string)$nick);
    if ($nick === '') {
        return 0;
    }
    $nick_esc = addslashes($nick);
    $currency = shop_구매통화_정규화($currency);
    $row = db_select("SELECT COUNT(*) AS cnt
        FROM tb_gifticon
        WHERE seller_nick = '{$nick_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = '{$currency}'");
    return (int)($row['cnt'] ?? 0);
}

function shop_판매통계($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['count' => 0, 'total_won' => 0, 'total_nyang' => '0', 'settled_nyang' => '0'];
    }
    $nick_esc = addslashes($nick);
    $row = db_select("SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(face_value), 0) AS total_won,
        CAST(COALESCE(SUM(CAST(IFNULL(price_nyang, 0) AS DECIMAL(40,0))), 0) AS CHAR) AS total_nyang,
        CAST(COALESCE(SUM(CASE WHEN settled = 1 THEN CAST(IFNULL(price_nyang, 0) AS DECIMAL(40,0)) ELSE 0 END), 0) AS CHAR) AS settled_nyang
        FROM tb_gifticon WHERE seller_nick = '{$nick_esc}' AND status = 'sold'");
    return [
        'count'         => (int)($row['cnt'] ?? 0),
        'total_won'     => (int)($row['total_won'] ?? 0),
        'total_nyang'   => shop_냥_값($row['total_nyang'] ?? 0),
        'settled_nyang' => shop_냥_값($row['settled_nyang'] ?? 0),
    ];
}

function shop_code_query($code) {
    return $code !== '' ? '?code=' . rawurlencode($code) : '';
}

function shop_마켓_공개url() {
    return 'http://49.247.160.164/shop';
}

function shop_register_url($code) {
    return '/shop/register.php' . shop_code_query($code);
}

function shop_edit_url($code, $id) {
    $id = (int)$id;
    $q = 'id=' . $id;
    if ($code !== '') {
        $q .= '&code=' . rawurlencode($code);
    }
    return '/shop/edit.php?' . $q;
}

function shop_nav_html($code, $active = 'market') {
    $code_q = $code !== '' ? 'code=' . rawurlencode($code) : '';
    $items = [
        'market' => ['href' => '/shop/' . ($code_q ? '?' . $code_q : ''), 'label' => '🏪 도하 마켓'],
        'buy'    => ['href' => '/shop/history.php?' . ($code_q ? $code_q . '&' : '') . 'tab=buy', 'label' => '🛍️ 구매내역'],
        'sell'   => ['href' => '/shop/history.php?' . ($code_q ? $code_q . '&' : '') . 'tab=sell', 'label' => '💰 판매내역'],
    ];
    $html = '<nav class="shop-nav">';
    foreach ($items as $key => $item) {
        $cls = ($active === $key) ? ' class="active"' : '';
        $html .= '<a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '"' . $cls . '>' . $item['label'] . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

function shop_이미지_열람가능($gifticon_id, $nick, $auth_code = '') {
    $gifticon_id = (int)$gifticon_id;
    $nick = trim((string)$nick);
    if ($gifticon_id < 1 || $nick === '') {
        return null;
    }
    $row = db_select("SELECT idx, image_file, buyer_nick, seller_nick, status FROM tb_gifticon WHERE idx = {$gifticon_id} LIMIT 1");
    if (empty($row['idx']) || empty($row['image_file'])) {
        return null;
    }
    $status = $row['status'] ?? '';
    if (shop_전체판매_열람가능($auth_code)) {
        if (in_array($status, ['sale', 'reserved', 'sold'], true)) {
            return $row;
        }
        return null;
    }
    if ($status === 'sold' && trim((string)($row['buyer_nick'] ?? '')) === $nick) {
        return $row;
    }
    if ($status === 'sale' && trim((string)($row['seller_nick'] ?? '')) === $nick) {
        return $row;
    }
    if ($status === 'sale' && shop_관리자_닉($nick)) {
        return $row;
    }
    return null;
}

function shop_관리자_닉후보($nick) {
    $nick = trim((string)$nick);
    $out = [];
    $push = static function ($v) use (&$out) {
        $v = trim((string)$v);
        if ($v !== '' && !in_array($v, $out, true)) {
            $out[] = $v;
        }
    };
    $push($nick);
    shop_스왑_함수_로드();
    if (function_exists('getTwoCharNick')) {
        $push(getTwoCharNick($nick));
    }
    return $out;
}

function shop_관리자_목록() {
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $set = [];
    $add = static function ($nick) use (&$set) {
        foreach (shop_관리자_닉후보($nick) as $cand) {
            $set[$cand] = true;
        }
    };

    $add('지호');

    $rs = @db_query("SELECT name FROM tb_member WHERE admin = 1 ORDER BY name");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $add($row['name'] ?? '');
        }
    }

    $cache = array_keys($set);
    return $cache;
}

function shop_관리자_닉($nick) {
    $cands = shop_관리자_닉후보($nick);
    if (empty($cands)) {
        return false;
    }
    foreach (shop_관리자_목록() as $allowed) {
        if (in_array($allowed, $cands, true)) {
            return true;
        }
    }
    return false;
}

function shop_사용완료컬럼_보장() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'used_at'");
    $row = $rs ? db_fetch($rs) : null;
    if (!empty($row['Field'])) {
        return;
    }

    @db_query("ALTER TABLE tb_gifticon ADD COLUMN used_at DATETIME NULL DEFAULT NULL AFTER sold_at");
}

function shop_추출코드_정규화($text) {
    return preg_replace('/\s+/u', '', trim((string)$text));
}

function shop_추출코드컬럼_보장() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    shop_사용완료컬럼_보장();

    $need_barcode = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'barcode_text'");
    $row = $rs ? db_fetch($rs) : null;
    if (!empty($row['Field'])) {
        $need_barcode = false;
    }
    if ($need_barcode) {
        @db_query("ALTER TABLE tb_gifticon ADD COLUMN barcode_text VARCHAR(255) NULL DEFAULT NULL AFTER used_at");
    }

    $need_extracted_at = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'barcode_extracted_at'");
    $row = $rs ? db_fetch($rs) : null;
    if (!empty($row['Field'])) {
        $need_extracted_at = false;
    }
    if ($need_extracted_at) {
        @db_query("ALTER TABLE tb_gifticon ADD COLUMN barcode_extracted_at DATETIME NULL DEFAULT NULL AFTER barcode_text");
    }
}

function shop_구매상품_사용완료($id, $nick) {
    $id = (int)$id;
    $nick = trim((string)$nick);
    if ($id < 1 || $nick === '') {
        return ['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.'];
    }

    shop_사용완료컬럼_보장();

    $nick_esc = addslashes($nick);
    $row = db_select("SELECT idx, name, status, buyer_nick, used_at FROM tb_gifticon WHERE idx = {$id} LIMIT 1");
    if (empty($row['idx']) || trim((string)($row['buyer_nick'] ?? '')) !== $nick) {
        return ['ok' => false, 'msg' => '구매한 본인 상품만 처리할 수 있습니다.'];
    }
    if (($row['status'] ?? '') !== 'sold') {
        return ['ok' => false, 'msg' => '구매 완료된 상품만 처리할 수 있습니다.'];
    }

    $used_at = trim((string)($row['used_at'] ?? ''));
    if ($used_at !== '' && $used_at !== '0000-00-00 00:00:00') {
        return ['ok' => false, 'msg' => '이미 사용완료 처리된 기프티콘입니다.'];
    }

    $ok = db_query("UPDATE tb_gifticon SET used_at = NOW() WHERE idx = {$id} AND buyer_nick = '{$nick_esc}' AND status = 'sold' AND (used_at IS NULL OR used_at = '0000-00-00 00:00:00') LIMIT 1");
    if (!$ok) {
        return ['ok' => false, 'msg' => '사용완료 처리에 실패했습니다.'];
    }

    $name = trim((string)($row['name'] ?? ''));
    return ['ok' => true, 'msg' => ($name !== '' ? "{$name} " : '') . '사용완료 처리되었습니다.'];
}

/** 구매자: 사용완료된 구매 내역만 삭제 */
function shop_구매상품_삭제($id, $nick) {
    $id = (int)$id;
    $nick = trim((string)$nick);
    if ($id < 1 || $nick === '') {
        return ['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.'];
    }

    shop_사용완료컬럼_보장();

    $nick_esc = addslashes($nick);
    $row = db_select("SELECT idx, name, status, buyer_nick, seller_nick, used_at, image_file
        FROM tb_gifticon WHERE idx = {$id} LIMIT 1");
    if (empty($row['idx']) || trim((string)($row['buyer_nick'] ?? '')) !== $nick) {
        return ['ok' => false, 'msg' => '구매한 본인 상품만 삭제할 수 있습니다.'];
    }
    if (($row['status'] ?? '') !== 'sold') {
        return ['ok' => false, 'msg' => '구매 완료된 상품만 삭제할 수 있습니다.'];
    }

    $used_at = trim((string)($row['used_at'] ?? ''));
    if ($used_at === '' || $used_at === '0000-00-00 00:00:00') {
        return ['ok' => false, 'msg' => '사용완료 처리된 상품만 삭제할 수 있습니다.'];
    }

    $ok = db_query("DELETE FROM tb_gifticon
        WHERE idx = {$id}
          AND buyer_nick = '{$nick_esc}'
          AND status = 'sold'
          AND used_at IS NOT NULL
          AND used_at <> '0000-00-00 00:00:00'
        LIMIT 1");
    if (!$ok) {
        return ['ok' => false, 'msg' => '삭제에 실패했습니다.'];
    }

    if (!empty($row['image_file'])) {
        $path = shop_이미지_물리경로($row['image_file']);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    $판매자 = trim((string)($row['seller_nick'] ?? ''));
    if ($판매자 !== '') {
        shop_회원_권면가총액_동기화($판매자);
    }

    $name = trim((string)($row['name'] ?? ''));
    return ['ok' => true, 'msg' => ($name !== '' ? "{$name} " : '') . '삭제되었습니다.'];
}

function shop_구매상품_추출코드_저장($id, $nick, $text, $auth_code = '') {
    $id = (int)$id;
    $nick = trim((string)$nick);
    $text = trim((string)$text);
    if ($id < 1 || $nick === '' || $text === '') {
        return ['ok' => false, 'msg' => '저장할 코드 정보가 올바르지 않습니다.'];
    }

    shop_추출코드컬럼_보장();

    $nick_esc = addslashes($nick);
    $row = db_select("SELECT idx, buyer_nick, status, barcode_text, image_file FROM tb_gifticon WHERE idx = {$id} LIMIT 1");
    if (empty($row['idx'])) {
        return ['ok' => false, 'msg' => '상품을 찾을 수 없습니다.'];
    }

    $전체열람 = shop_전체판매_열람가능($auth_code);
    $허용 = false;
    if ($전체열람) {
        $허용 = !empty($row['image_file'])
            && in_array($row['status'] ?? '', ['sale', 'reserved', 'sold'], true);
    } elseif (trim((string)($row['buyer_nick'] ?? '')) === $nick && ($row['status'] ?? '') === 'sold') {
        $허용 = true;
    }
    if (!$허용) {
        return ['ok' => false, 'msg' => '코드를 저장할 권한이 없습니다.'];
    }

    $normalized = shop_추출코드_정규화($text);
    if ($normalized === '') {
        return ['ok' => false, 'msg' => '저장할 코드가 비어 있습니다.'];
    }

    $current = shop_추출코드_정규화($row['barcode_text'] ?? '');
    if ($current !== '' && $current === $normalized) {
        return ['ok' => true, 'msg' => '이미 저장된 코드입니다.', 'code_text' => $current];
    }

    $text_esc = addslashes($normalized);
    if ($전체열람) {
        $ok = db_query("UPDATE tb_gifticon
            SET barcode_text = '{$text_esc}', barcode_extracted_at = NOW()
            WHERE idx = {$id} LIMIT 1");
    } else {
        $ok = db_query("UPDATE tb_gifticon
            SET barcode_text = '{$text_esc}', barcode_extracted_at = NOW()
            WHERE idx = {$id} AND buyer_nick = '{$nick_esc}' AND status = 'sold' LIMIT 1");
    }
    if (!$ok) {
        return ['ok' => false, 'msg' => '코드 저장에 실패했습니다.'];
    }

    return ['ok' => true, 'msg' => '코드가 저장되었습니다.', 'code_text' => $normalized];
}

function shop_관리자_인증($code) {
    $회원 = shop_auth($code);
    if (!$회원) {
        return null;
    }
    if (!shop_관리자_닉($회원['nick'])) {
        return null;
    }
    return $회원;
}

function shop_매입컬럼_보장() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    shop_회원_권면가총액_컬럼_보장();

    $cols = [
        'price_newpoint' => "ADD COLUMN `price_newpoint` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '선매입가(본방냥·등록/수정 시점 고정)' AFTER `price_nyang`",
        'price_updated_at' => "ADD COLUMN `price_updated_at` DATETIME NULL DEFAULT NULL COMMENT '판매가 최근 갱신 시각' AFTER `price_newpoint`",
        'prebuy'       => "ADD COLUMN `prebuy` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '관리자 선매입'",
        'prebuy_at'    => "ADD COLUMN `prebuy_at` DATETIME NULL DEFAULT NULL",
        'prebuy_admin' => "ADD COLUMN `prebuy_admin` VARCHAR(30) NULL DEFAULT NULL",
        'prebuy_paid'  => "ADD COLUMN `prebuy_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '선매입 지급액'",
        'paid_newpoint' => "ADD COLUMN `paid_newpoint` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '본냥 구매 결제액' AFTER `prebuy_paid`",
        'paid_nyang'    => "ADD COLUMN `paid_nyang` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '겜냥 구매 실결제액' AFTER `paid_newpoint`",
        'buy_currency'  => "ADD COLUMN `buy_currency` VARCHAR(10) NOT NULL DEFAULT 'point' COMMENT '구매 결제 통화 point|newpoint' AFTER `paid_nyang`",
    ];

    foreach ($cols as $col => $alter) {
        $rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE '{$col}'");
        $row = $rs ? db_fetch($rs) : null;
        if (!empty($row['Field'])) {
            continue;
        }
        @db_query("ALTER TABLE tb_gifticon {$alter}");
    }

    // buy_currency가 paid_newpoint 바로 뒤에 있으면 paid_nyang을 그 사이에 추가
    $paid_nyang_rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'paid_nyang'");
    $paid_nyang_row = $paid_nyang_rs ? db_fetch($paid_nyang_rs) : null;
    if (empty($paid_nyang_row['Field'])) {
        @db_query("ALTER TABLE tb_gifticon ADD COLUMN `paid_nyang` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '겜냥 구매 실결제액' AFTER `paid_newpoint`");
    } else {
        $pnType = strtolower((string)($paid_nyang_row['Type'] ?? ''));
        $needWiden = false;
        if (strpos($pnType, 'decimal') === false) {
            $needWiden = true;
        } elseif (preg_match('/decimal\((\d+)/', $pnType, $m) && (int)$m[1] < 40) {
            $needWiden = true;
        }
        if ($needWiden) {
            @db_query("ALTER TABLE tb_gifticon MODIFY COLUMN `paid_nyang` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '겜냥 구매 실결제액'");
        }
    }

    $paid_rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'prebuy_paid'");
    $paid_row = $paid_rs ? db_fetch($paid_rs) : null;
    if (!empty($paid_row['Field']) && stripos((string)($paid_row['Type'] ?? ''), 'bigint') === false) {
        @db_query("ALTER TABLE tb_gifticon MODIFY COLUMN `prebuy_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '선매입 지급액'");
    }

    // price_nyang: BIGINT(~922경) → DECIMAL(40,0) — 대금액 판매가 보존
    $nyang_rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'price_nyang'");
    $nyang_row = $nyang_rs ? db_fetch($nyang_rs) : null;
    if (!empty($nyang_row['Field'])) {
        $nyangType = strtolower((string)($nyang_row['Type'] ?? ''));
        $needWiden = false;
        if (strpos($nyangType, 'decimal') === false) {
            $needWiden = true;
        } elseif (preg_match('/decimal\((\d+)/', $nyangType, $m) && (int)$m[1] < 40) {
            $needWiden = true;
        }
        if ($needWiden) {
            @db_query("ALTER TABLE tb_gifticon MODIFY COLUMN `price_nyang` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '판매가(게임냥)'");
        }
    }

    @db_query("UPDATE tb_gifticon SET prebuy = 0 WHERE prebuy IS NULL");
}

function shop_매입_본인상품_허용($admin_nick) {
    return shop_선매입_허용($admin_nick);
}

/** 마켓 선매입(관리자 매입 버튼·실행) — 민호만 */
function shop_선매입_허용($admin_nick) {
    return trim((string)$admin_nick) === '민호';
}

function shop_선매입_수정_허용($admin_nick) {
    return shop_선매입_허용($admin_nick);
}

/** 마켓 판매중 상품 수정·삭제 — 민호만 (일반은 등록만) */
function shop_수정_허용($admin_nick) {
    return trim((string)$admin_nick) === '민호';
}

/** 마켓 판매중 상품 삭제 — 민호만 */
function shop_삭제_허용($admin_nick) {
    return shop_수정_허용($admin_nick);
}

function shop_수정삭제_문의문구() {
    return '수정·삭제를 원하시면 관리자에게 문의해 주세요.';
}

function shop_수정_가능($상품행, $actor_nick = '', $관리자타인수정 = false) {
    if (empty($상품행['idx']) && empty($상품행['id'])) {
        return false;
    }
    if (($상품행['status'] ?? '') !== 'sale') {
        return false;
    }
    return shop_수정_허용($actor_nick);
}

function shop_매입_가능($상품행, $admin_nick = '') {
    if (empty($상품행['idx'])) {
        return false;
    }
    if (($상품행['status'] ?? '') !== 'sale') {
        return false;
    }
    if ((int)($상품행['prebuy'] ?? 0) === 1) {
        return false;
    }
    $관리자 = trim((string)$admin_nick);
    if ($관리자 !== '' && !shop_선매입_허용($관리자)) {
        return false;
    }
    $판매자 = trim((string)($상품행['seller_nick'] ?? ($상품행['seller'] ?? '')));
    if ($관리자 !== '' && $판매자 !== '' && $판매자 === $관리자 && !shop_매입_본인상품_허용($관리자)) {
        return false;
    }
    return true;
}

/** config.선매입적립 — 마켓 게임냥 구매액의 0.1% 누적 */
function shop_선매입적립_컬럼_보장() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $rs = @db_query("SHOW COLUMNS FROM config LIKE '선매입적립'");
    $row = $rs ? db_fetch($rs) : null;
    if (!empty($row['Field'])) {
        $type = strtolower((string)($row['Type'] ?? ''));
        $needWiden = false;
        if (strpos($type, 'decimal') === false) {
            $needWiden = true;
        } elseif (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 40) {
            $needWiden = true;
        }
        if ($needWiden) {
            @db_query("ALTER TABLE config MODIFY COLUMN `선매입적립` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '마켓 게임냥 구매액의 0.1% 누적'");
        }
        return;
    }
    @db_query("ALTER TABLE config ADD COLUMN `선매입적립` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '마켓 게임냥 구매액의 0.1% 누적'");
}

/**
 * 마켓 게임냥 구매액의 0.1%를 config.선매입적립에 누적
 * @param string|int|float $구매액 게임냥
 * @return string 이번에 적립된 금액 (없으면 '0')
 */
function shop_선매입적립_반영($구매액) {
    shop_선매입적립_컬럼_보장();
    $구매 = shop_냥_값($구매액);
    if (shop_냥_비교($구매, '0') <= 0) {
        return '0';
    }
    $적립 = shop_문자열_나누기_내림($구매, SHOP_선매입적립_분모);
    if (shop_냥_비교($적립, '0') <= 0) {
        return '0';
    }
    $적립_sql = shop_냥_값($적립);
    @db_query("UPDATE config SET `선매입적립` = `선매입적립` + {$적립_sql} LIMIT 1");
    return $적립_sql;
}

/** config.선매입적립 누적액 (게임냥 문자열) */
function shop_선매입적립_조회() {
    shop_선매입적립_컬럼_보장();
    $행 = db_select("SELECT CAST(IFNULL(`선매입적립`, 0) AS CHAR) AS amt FROM config LIMIT 1");
    return shop_냥_값($행['amt'] ?? 0);
}

/** 선매입적립 비율 표시 (예: 0.1%) */
function shop_선매입적립_비율표시() {
    $분모 = (int)SHOP_선매입적립_분모;
    if ($분모 < 1) {
        $분모 = 1000;
    }
    $pct = 100 / $분모;
    $s = rtrim(rtrim(sprintf('%.6F', $pct), '0'), '.');
    return $s . '%';
}

/** 채팅 .마켓수수료 — 선매입적립 누적 조회 */
function shop_채팅_마켓수수료_문구() {
    $적립 = shop_선매입적립_조회();
    if (function_exists('게임냥_안전표시')) {
        $표시 = 게임냥_안전표시($적립, '냥');
    } elseif (function_exists('냥축약표시')) {
        $표시 = 냥축약표시($적립, '냥');
    } else {
        $표시 = shop_냥_전체표시($적립) . '냥';
    }
    $비율 = shop_선매입적립_비율표시();
    $msg = "🛒 마켓 수수료 (선매입적립)\n";
    $msg .= "누적 : {$표시}\n";
    $msg .= "(마켓 게임냥 구매액의 {$비율} 누적)";
    return $msg;
}

/** .마켓수령 — 도하만 허용 */
function shop_마켓수령_허용($nick) {
    $nick = trim((string)$nick);
    if ($nick === '도하') {
        return true;
    }
    foreach (shop_관리자_닉후보($nick) as $cand) {
        if ($cand === '도하') {
            return true;
        }
    }
    return false;
}

/**
 * 채팅 .마켓수령 — config.선매입적립 전액 → 도하 게임냥
 * @return array{ok:bool,msg:string,amount?:string}
 */
function shop_채팅_마켓수령_실행($nick) {
    $nick = trim((string)$nick);
    if (!shop_마켓수령_허용($nick)) {
        return ['ok' => false, 'msg' => '❌ `.마켓수령`은 도하만 사용할 수 있어요.'];
    }

    shop_선매입적립_컬럼_보장();
    shop_스왑_함수_로드();
    if (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
    }

    global $conn;
    $락 = db_select("SELECT GET_LOCK('shop_선매입적립_수령', 5) AS ok");
    if (empty($락['ok'])) {
        return ['ok' => false, 'msg' => '❌ 다른 수령 요청이 처리 중이에요. 잠시 후 다시 시도해 주세요.'];
    }

    try {
        $적립 = shop_선매입적립_조회();
        if (shop_냥_비교($적립, '1') < 0) {
            return ['ok' => false, 'msg' => '❌ 수령할 마켓 수수료가 없어요.'];
        }
        $적립_sql = shop_냥_값($적립);

        $ok = db_query("UPDATE config SET `선매입적립` = `선매입적립` - {$적립_sql} WHERE `선매입적립` >= {$적립_sql} LIMIT 1");
        $aff = ($ok && $conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
        if (!$ok || $aff < 1) {
            return ['ok' => false, 'msg' => '❌ 마켓 수수료 차감에 실패했어요. 잠시 후 다시 시도해 주세요.'];
        }

        $지급닉 = '도하';
        $지급닉_esc = addslashes($지급닉);
        $회원 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$지급닉_esc}' LIMIT 1");
        if (empty($회원['idx'])) {
            // 롤백
            db_query("UPDATE config SET `선매입적립` = `선매입적립` + {$적립_sql} LIMIT 1");
            return ['ok' => false, 'msg' => '❌ 도하 계정을 찾을 수 없어요. 적립액을 되돌렸어요.'];
        }

        $payOk = db_query("UPDATE tb_member SET point = point + {$적립_sql} WHERE idx = " . (int)$회원['idx'] . " LIMIT 1");
        $payAff = ($payOk && $conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
        if (!$payOk || $payAff < 1) {
            db_query("UPDATE config SET `선매입적립` = `선매입적립` + {$적립_sql} LIMIT 1");
            return ['ok' => false, 'msg' => '❌ 게임냥 지급에 실패했어요. 적립액을 되돌렸어요.'];
        }

        if (function_exists('지급로그')) {
            지급로그('마켓수수료수령', $지급닉, '', 0, $적립_sql);
        }

        if (function_exists('게임냥_안전표시')) {
            $표시 = 게임냥_안전표시($적립_sql, '냥');
        } elseif (function_exists('냥축약표시')) {
            $표시 = 냥축약표시($적립_sql, '냥');
        } else {
            $표시 = shop_냥_전체표시($적립_sql) . '냥';
        }

        $msg = "🛒 마켓 수수료 수령 완료!\n";
        $msg .= "{$지급닉} ← {$표시}";
        return ['ok' => true, 'msg' => $msg, 'amount' => $적립_sql];
    } finally {
        db_query("SELECT RELEASE_LOCK('shop_선매입적립_수령')");
    }
}

function shop_매입_실행($gifticon_id, $admin_nick) {
    shop_매입컬럼_보장();

    $gifticon_id = (int)$gifticon_id;
    $admin_nick = trim((string)$admin_nick);
    if ($gifticon_id < 1 || $admin_nick === '') {
        return ['ok' => false, 'msg' => '요청 정보가 올바르지 않습니다.'];
    }

    $상품 = db_select("SELECT * FROM tb_gifticon WHERE idx = {$gifticon_id} LIMIT 1");
    if (empty($상품['idx'])) {
        return ['ok' => false, 'msg' => '상품을 찾을 수 없습니다.'];
    }
    if (!shop_매입_가능($상품, $admin_nick)) {
        if ((int)($상품['prebuy'] ?? 0) === 1) {
            return ['ok' => false, 'msg' => '이미 매입된 상품입니다.'];
        }
        if (($상품['status'] ?? '') !== 'sale') {
            return ['ok' => false, 'msg' => '판매중인 상품만 매입할 수 있습니다.'];
        }
        if (trim((string)($상품['seller_nick'] ?? '')) === $admin_nick && !shop_매입_본인상품_허용($admin_nick)) {
            return ['ok' => false, 'msg' => '본인이 등록한 상품은 매입할 수 없습니다.'];
        }
        return ['ok' => false, 'msg' => '매입할 수 없는 상품입니다.'];
    }

    $판매자 = trim((string)($상품['seller_nick'] ?? ''));
    $권면가 = (int)($상품['face_value'] ?? 0);
    if ($권면가 < SHOP_최소권면가) {
        return ['ok' => false, 'msg' => '권면가가 올바르지 않습니다.'];
    }

    $market_tax = shop_판매자_market_tax($판매자);
    $실지급 = shop_선매입_지급액_조회($상품, $판매자);
    if (shop_냥_비교($실지급, '1') < 0) {
        return ['ok' => false, 'msg' => '매입 지급액을 계산할 수 없습니다. (등록 시 선매입가가 없습니다)'];
    }
    $실지급_sql = shop_냥_값($실지급);
    $판매자_esc = addslashes($판매자);
    $관리자_esc = addslashes($admin_nick);

    if (!shop_선매입_허용($admin_nick)) {
        return ['ok' => false, 'msg' => '민호만 선매입할 수 있습니다.'];
    }

    global $conn;

    $ok = db_query("UPDATE tb_gifticon SET
        prebuy = 1,
        prebuy_at = NOW(),
        prebuy_admin = '{$관리자_esc}',
        prebuy_paid = {$실지급_sql}
        WHERE idx = {$gifticon_id} AND status = 'sale' AND IFNULL(prebuy, 0) = 0 LIMIT 1");
    $affected = ($ok && $conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
    if (!$ok || $affected < 1) {
        $db_err = ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
        return ['ok' => false, 'msg' => '매입 처리에 실패했습니다.' . ($db_err !== '' ? "\n({$db_err})" : '')];
    }

    if (shop_냥_비교($실지급_sql, '0') > 0 && $판매자 !== '') {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$실지급_sql} WHERE name = '{$판매자_esc}' LIMIT 1");
        $paid_affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
        if ($paid_affected < 1) {
            db_query("UPDATE tb_gifticon SET
                prebuy = 0,
                prebuy_at = NULL,
                prebuy_admin = NULL,
                prebuy_paid = 0
                WHERE idx = {$gifticon_id} AND prebuy = 1 AND prebuy_admin = '{$관리자_esc}' LIMIT 1");
            return ['ok' => false, 'msg' => '판매자에게 선지급하지 못했습니다. 매입 상태를 되돌렸습니다.'];
        }
    }

    $은총지급수 = 0;
    if ($판매자 !== '' && $권면가 >= SHOP_최소권면가) {
        $은총결과 = shop_등록_은총_지급($판매자, $권면가, 1, '마켓선매입은총');
        if ($은총결과 === false) {
            // 본방냥 선지급은 유지 · 은총만 실패 안내
            $상품명 = trim((string)($상품['name'] ?? ''));
            $msg = ($상품명 !== '' ? "{$상품명} " : '') . '매입 완료! ' . $판매자 . '님에게 본방냥 ' . shop_매입_지급_표시($실지급_sql) . ' 선지급 · 은총 지급 실패(관리자 확인)';
            shop_매입알림_등록($admin_nick, $상품, $실지급_sql, $market_tax);
            return [
                'ok'         => true,
                'msg'        => $msg,
                'paid'       => $실지급_sql,
                'face_value' => $권면가,
                'eunchong'   => 0,
            ];
        }
        $은총지급수 = (int)$은총결과;
    }

    shop_매입알림_등록($admin_nick, $상품, $실지급_sql, $market_tax);

    $상품명 = trim((string)($상품['name'] ?? ''));
    $msg = ($상품명 !== '' ? "{$상품명} " : '') . '매입 완료! ' . $판매자 . '님에게 본방냥 ' . shop_매입_지급_표시($실지급_sql) . ' 선지급';
    if ($은총지급수 > 0) {
        $msg .= " · 은총 {$은총지급수}개 지급";
    }

    return [
        'ok'         => true,
        'msg'        => $msg,
        'paid'       => $실지급_sql,
        'face_value' => $권면가,
        'eunchong'   => $은총지급수,
    ];
}

function shop_매입알림_등록($관리자, $상품행, $실지급, $market_tax = null) {
    $관리자 = trim((string)$관리자);
    $상품명 = trim((string)($상품행['name'] ?? ''));
    if ($상품명 === '') {
        $상품명 = '기프티콘';
    }
    $권면가 = (int)($상품행['face_value'] ?? 0);

    // 같은 상품명 연속 선매입(미전송 큐)은 1건으로 합침
    $window = 180;
    $관리자_esc = addslashes($관리자);
    $상품명_esc = addslashes($상품명);
    $집계 = db_select("SELECT COUNT(*) AS cnt, CAST(IFNULL(SUM(prebuy_paid), 0) AS CHAR) AS paid_sum
        FROM tb_gifticon
        WHERE prebuy = 1
          AND prebuy_admin = '{$관리자_esc}'
          AND name = '{$상품명_esc}'
          AND prebuy_at >= DATE_SUB(NOW(), INTERVAL {$window} SECOND)");
    $건수 = max(1, (int)($집계['cnt'] ?? 1));
    $총지급 = shop_냥_값($집계['paid_sum'] ?? $실지급);
    if (shop_냥_비교($총지급, '1') < 0) {
        $총지급 = shop_냥_값($실지급);
    }

    $msg = "마켓의 {$상품명}을 선매입하였습니다";
    if ($건수 > 1) {
        $msg .= " ({$건수}건)";
    }
    $msg .= "\n권면가: " . number_format($권면가) . "원" . ($건수 > 1 ? '/건' : '');
    $msg .= "\n지급된 본방냥: " . shop_매입_지급_표시($총지급);

    $item = 'gifticon_prebuy_c_' . substr(md5(mb_strtolower($상품명, 'UTF-8') . '|' . $관리자), 0, 16);
    $item_esc = addslashes($item);
    if (!function_exists('info2알림_테이블_보장')) {
        shop_스왑_함수_로드();
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
    $기존 = db_select("SELECT idx FROM tb_info2_alarm
        WHERE status = 0
          AND item = '{$item_esc}'
          AND regdate >= DATE_SUB(NOW(), INTERVAL {$window} SECOND)
        ORDER BY idx DESC LIMIT 1");
    if (!empty($기존['idx'])) {
        $msg_esc = addslashes($msg);
        $idx = (int)$기존['idx'];
        return (bool)@db_query("UPDATE tb_info2_alarm
            SET msg = '{$msg_esc}', regdate = NOW()
            WHERE idx = {$idx} AND status = 0 LIMIT 1");
    }

    return shop_채팅알림_등록($msg, $item);
}

function shop_보유냥_원화_환산($원화, $seller_nick = '') {
    $원화 = (int)$원화;
    if ($원화 <= 0) {
        return 0;
    }
    return shop_선매입_권면가_newpoint($원화, $seller_nick);
}

function shop_마이그레이션_대상조회() {
    shop_매입컬럼_보장();
    $rs = @db_query("SELECT idx, seller_nick, name, face_value,
            CAST(price_nyang AS CHAR) AS price_nyang,
            price_newpoint, status, expire_date
        FROM tb_gifticon WHERE status IN ('sale', 'reserved') ORDER BY idx DESC");
    if (!$rs) {
        return [];
    }
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $face_value = (int)($row['face_value'] ?? 0);
        $seller = trim((string)($row['seller_nick'] ?? ''));
        $old_nyang = shop_냥_값($row['price_nyang'] ?? 0);
        $new_nyang = shop_냥_값(shop_원화_냥환산($face_value, $seller));
        $stored_newpoint = shop_냥_값($row['price_newpoint'] ?? 0);
        // 새 계산이 0이면 기존가 유지 (깨진 환산으로 0 덮어쓰기 방지)
        if ($new_nyang === '0' && $old_nyang !== '0') {
            $new_nyang = $old_nyang;
        }
        $changed_nyang = ($new_nyang !== $old_nyang);
        $목록[] = [
            'id'         => (int)$row['idx'],
            'seller'     => $seller,
            'name'       => $row['name'] ?? '',
            'face_value' => $face_value,
            'old_price'  => $old_nyang,
            'new_price'  => $new_nyang,
            'diff'       => (function_exists('bcsub') ? bcsub($new_nyang, $old_nyang, 0) : '0'),
            'old_price_nyang' => $old_nyang,
            'new_price_nyang' => $new_nyang,
            'price_newpoint' => $stored_newpoint,
            'old_price_newpoint' => $stored_newpoint,
            'new_price_newpoint' => $stored_newpoint,
            'changed_nyang' => $changed_nyang,
            'changed_newpoint' => false,
            'status'     => $row['status'] ?? '',
            'expire'     => $row['expire_date'] ?? '',
            'changed'    => $changed_nyang,
        ];
    }
    return $목록;
}

function shop_마이그레이션_결과_메타() {
    $live = shop_실시간_총량조회();
    return [
        'live_newpoint' => (float)($live['본방냥'] ?? 0),
        'live_point'  => (int)($live['게임냥'] ?? 0),
        'snapshot_newpoint' => shop_스냅샷_본방냥_총량(),
        'nyang_per_10000' => shop_만원당_냥(),
        'realtime_newpoint_per_10000' => shop_매입_만원당_newpoint(),
        'newpoint_per_10000' => shop_선매입_만원당_newpoint(),
        'swap_per_newpoint'  => shop_스왑_1보유냥당_게임냥(),
        'ran_at'          => date('Y-m-d H:i:s'),
    ];
}

function shop_마이그레이션_실행() {
    shop_매입컬럼_보장();

    $목록 = shop_마이그레이션_대상조회();
    $updated = 0;
    $updated_nyang = 0;
    $updated_newpoint = 0;
    $unchanged = 0;
    $changed_items = [];
    $changed_items_newpoint = [];
    foreach ($목록 as $item) {
        $id = (int)$item['id'];
        $new_nyang = shop_냥_값($item['new_price_nyang']);
        $old_nyang = shop_냥_값($item['old_price_nyang'] ?? 0);
        if ($new_nyang === '0' && (int)($item['face_value'] ?? 0) > 0) {
            $retry = shop_냥_값(shop_원화_냥환산((int)$item['face_value'], (string)($item['seller'] ?? '')));
            $new_nyang = ($retry !== '0') ? $retry : $old_nyang;
        }
        // 환산 실패(0)로 기존 정상가를 지우지 않음
        if ($new_nyang === '0' && $old_nyang !== '0') {
            $new_nyang = $old_nyang;
        }
        // 선매입가(price_newpoint)는 등록/수정 시점 고정 — 자동시세에서 갱신하지 않음
        $ok = db_query("UPDATE tb_gifticon SET
            price_nyang = {$new_nyang},
            price_updated_at = NOW()
            WHERE idx = {$id} AND status IN ('sale', 'reserved') LIMIT 1");
        if ($ok) {
            $updated++;
            if (empty($item['changed'])) {
                $unchanged++;
            }
            if (!empty($item['changed_nyang'])) {
                $updated_nyang++;
                $changed_items[] = [
                    'id'         => $id,
                    'name'       => $item['name'],
                    'seller'     => $item['seller'],
                    'face_value' => $item['face_value'],
                    'old_price'  => $item['old_price_nyang'],
                    'new_price'  => $new_nyang,
                ];
            }
        }
    }

    $meta = shop_마이그레이션_결과_메타();

    return array_merge([
        'total'           => count($목록),
        'updated'         => $updated,
        'updated_nyang'   => $updated_nyang,
        'updated_newpoint' => $updated_newpoint,
        'unchanged'       => $unchanged,
        'skipped'         => $unchanged,
        'total_nyang'     => (int)($meta['live_point'] ?? 0),
        'changed_items'   => $changed_items,
        'changed_items_newpoint' => $changed_items_newpoint,
    ], $meta);
}

function shop_본방냥_마이그레이션_대상조회() {
    return shop_마이그레이션_대상조회();
}

/** @deprecated shop_마이그레이션_실행() 사용 (겜냥·본방냥 동시 갱신) */
function shop_본방냥_마이그레이션_실행() {
    $result = shop_마이그레이션_실행();
    return array_merge($result, [
        'updated' => (int)($result['updated_newpoint'] ?? 0),
        'changed_items' => $result['changed_items_newpoint'] ?? [],
    ]);
}

// 페이지·API include 시 점검 게이트 (크론은 SHOP_SKIP_MAINTENANCE_GATE 로 우회)
if (!defined('SHOP_LIB_LOADED_GATE')) {
    define('SHOP_LIB_LOADED_GATE', true);
    shop_점검_게이트();
}
