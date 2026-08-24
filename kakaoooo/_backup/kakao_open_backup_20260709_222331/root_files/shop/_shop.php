<?
/**
 * 기프티콘 마켓 공통
 * 환산: 겜냥 = 실시간 본방냥 × market_tax% × 스왑환율 · 선매입 본방냥 = 시세 스냅샷 × market_tax%
 * 예) 본방 2.5% = 17,345냥 · 스왑 1:2,243,525,876 → 1만원권 ≈ 3.89조 게임냥
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
if (!defined('SHOP_매입_기본_MARKET_TAX')) {
    define('SHOP_매입_기본_MARKET_TAX', 2.5);
}
/** 판매등록 은총: 총권면가(권면가×이미지수) 3만원당 1개 */
if (!defined('SHOP_등록_은총_단위권면가')) {
    define('SHOP_등록_은총_단위권면가', 30000);
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
    if (!function_exists('시세기준_실시간합계')) {
        $fn = $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
        if (is_file($fn)) {
            include_once $fn;
        }
    }
    if (!function_exists('newpoint표시')) {
        $fn = $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
        if (is_file($fn)) {
            include_once $fn;
        }
    }
}

/** 마켓 환산용 status=0 회원 실시간 newpoint·게임냥 합계 */
function shop_실시간_총량조회() {
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    shop_스왑_함수_로드();
    if (function_exists('시세기준_실시간합계')) {
        $cached = 시세기준_실시간합계();
        return $cached;
    }

    $row = db_select("
        SELECT
            COALESCE(SUM(newpoint), 0) AS total_np,
            CAST(COALESCE(SUM(CAST(point AS DECIMAL(40,0))), 0) AS CHAR) AS total_pt
        FROM tb_member
        WHERE status = 0
    ");
    $cached = [
        '본방냥' => (float)($row['total_np'] ?? 0),
        '게임냥' => function_exists('냥_정수문자열')
          ? 냥_정수문자열($row['total_pt'] ?? 0)
          : (string)($row['total_pt'] ?? 0),
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

/** 1만원 권면가 → 게임냥 판매가 (실시간 본방냥 market_tax% × 스왑환율) */
function shop_만원당_냥($seller_nick = '') {
    $본방만원당 = shop_매입_만원당_newpoint($seller_nick);
    $스왑율 = shop_스왑_1보유냥당_게임냥();
    $스왑율Str = function_exists('냥_정수문자열') ? 냥_정수문자열($스왑율) : (string)(int)$스왑율;
    $스왑양수 = function_exists('bccomp')
      ? bccomp($스왑율Str, '0', 0) > 0
      : ((float)$스왑율Str > 0);
    if ($본방만원당 > 0 && $스왑양수) {
        if (function_exists('bcmul') && function_exists('bccomp')) {
            $금액 = bcmul((string)$본방만원당, $스왑율Str, 0);
            // round: already integer product
            if (bccomp($금액, '1', 0) < 0) {
                $금액 = '1';
            }
            if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
                return max(1, (int)$금액);
            }
            return $금액;
        }
        return max(1, (int)round($본방만원당 * (float)$스왑율Str));
    }

    $비율 = shop_market_tax_비율(shop_판매자_market_tax($seller_nick));
    $총량 = shop_실시간_총량조회();
    $전체게임냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($총량['게임냥'] ?? 0)
      : (string)($총량['게임냥'] ?? 0);
    if (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
        $ratioStr = sprintf('%.12F', $비율);
        $raw = bcmul($전체게임냥, $ratioStr, 12);
        $금액 = bcadd($raw, '0', 0);
        if (bccomp($raw, $금액, 12) > 0) {
            $금액 = bcadd($금액, '1', 0);
        }
        if (bccomp($금액, '1', 0) < 0) {
            $금액 = '1';
        }
        if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
            return max(1, (int)$금액);
        }
        return $금액;
    }
    return max(1, (int)ceil((float)$전체게임냥 * $비율));
}

function shop_원화_냥환산($원화, $seller_nick = '') {
    $원화 = (int)$원화;
    if ($원화 <= 0) {
        return '0';
    }
    $만원기준 = (int)SHOP_만원기준원화;
    if ($만원기준 < 1) {
        $만원기준 = 10000;
    }
    $본방만원당 = shop_매입_만원당_newpoint($seller_nick);
    $스왑율 = shop_스왑_1보유냥당_게임냥();
    $스왑율Str = function_exists('냥_정수문자열') ? 냥_정수문자열($스왑율) : (string)max(0, (int)$스왑율);
    $스왑양수 = function_exists('bccomp')
      ? bccomp($스왑율Str, '0', 0) > 0
      : ((float)$스왑율Str > 0);
    if ($본방만원당 > 0 && $스왑양수) {
        if (function_exists('bcmul') && function_exists('bcdiv')) {
            $ratio = bcdiv((string)$원화, (string)$만원기준, 20);
            $np_part = bcmul($ratio, (string)$본방만원당, 20);
            return bcmul($np_part, $스왑율Str, 0);
        }
        return (string)max(0, (int)round($원화 / $만원기준 * $본방만원당 * (float)$스왑율Str));
    }
    $만원당 = shop_만원당_냥($seller_nick);
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        $ratio = bcdiv((string)$원화, (string)$만원기준, 20);
        return bcmul($ratio, (string)$만원당, 0);
    }
    return (string)max(0, (int)round($원화 / $만원기준 * $만원당));
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
    ];
}
function shop_판매자_market_tax($seller_nick) {
    $seller_nick = trim((string)$seller_nick);
    if ($seller_nick === '') {
        return (float)SHOP_매입_기본_MARKET_TAX;
    }

    static $cache = [];
    if (array_key_exists($seller_nick, $cache)) {
        return $cache[$seller_nick];
    }

    $seller_esc = addslashes($seller_nick);
    $row = db_select("SELECT market_tax FROM tb_member WHERE name = '{$seller_esc}' LIMIT 1");
    $tax = (isset($row['market_tax']) && $row['market_tax'] !== '' && $row['market_tax'] !== null)
        ? (float)$row['market_tax']
        : (float)SHOP_매입_기본_MARKET_TAX;
    if ($tax <= 0) {
        $tax = (float)SHOP_매입_기본_MARKET_TAX;
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

/** 마켓 겜냥 환산용 — 1만원당 실시간 본방냥 × 판매자 market_tax% */
function shop_매입_만원당_newpoint($seller_nick = '') {
    $비율 = shop_market_tax_비율(shop_판매자_market_tax($seller_nick));
    $총량 = shop_실시간_총량조회();
    $total_np = (float)($총량['본방냥'] ?? 0);
    if ($total_np <= 0) {
        return 0;
    }
    return max(1, (int)floor($total_np * $비율));
}

/** 선매입 — 1만원당 본방냥 (시세 스냅샷 × 판매자 market_tax%) */
function shop_선매입_만원당_newpoint($seller_nick = '') {
    $비율 = shop_market_tax_비율(shop_판매자_market_tax($seller_nick));
    $total_np = shop_스냅샷_본방냥_총량();
    if ($total_np <= 0) {
        return 0;
    }
    return max(1, (int)floor($total_np * $비율));
}

/** 선매입·표시용 본방냥 — 시세 스냅샷 × 판매자 market_tax% 환산 */
function shop_상품_표시_본방냥($row, $seller_nick = '') {
    $face_value = (int)($row['face_value'] ?? 0);
    $seller_nick = trim((string)($seller_nick !== '' ? $seller_nick : ($row['seller_nick'] ?? ($row['seller'] ?? ''))));
    return shop_선매입_권면가_newpoint($face_value, $seller_nick);
}

/** 권면가(원) → 선매입 지급 보유냥(newpoint) — 시세 스냅샷 기준 */
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

/** @deprecated alias — 선매입 전용 (스냅샷) */
function shop_매입_권면가_newpoint($face_value, $seller_nick = '') {
    return shop_선매입_권면가_newpoint($face_value, $seller_nick);
}

/** 판매등록 은총 개수 — 총권면가(권면가×장수) ÷ 3만원 (내림) */
function shop_등록_은총_지급개수($face_value, $image_count = 1) {
    $face_value = (int)$face_value;
    $image_count = max(0, (int)$image_count);
    $단위 = (int)SHOP_등록_은총_단위권면가;
    if ($face_value < 1 || $image_count < 1 || $단위 < 1) {
        return 0;
    }
    return (int)floor(($face_value * $image_count) / $단위);
}

/** 판매등록 완료 시 은총 지급. 성공 시 지급 개수, 실패 시 false */
function shop_등록_은총_지급($nick, $face_value, $image_count) {
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

    global $conn;
    $ok = db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$개수} WHERE name = '{$nick_esc}' LIMIT 1");
    if (!$ok) {
        return false;
    }

    $확인 = db_select("SELECT IFNULL(은총개수, 0) AS cnt FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
    $이전 = (int)($존재['cnt'] ?? 0);
    $이후 = (int)($확인['cnt'] ?? 0);
    if ($이후 < $이전 + $개수) {
        return false;
    }

    shop_스왑_함수_로드();
    if (function_exists('지급로그')) {
        지급로그('마켓등록은총', $nick, $nick, 0, $개수);
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

/** BIGINT 냥 값 정규화 — PHP int 오버플로우 방지 */
function shop_냥_값($raw) {
    $digits = preg_replace('/[^\d]/', '', (string)$raw);
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
    return $a <=> $b;
}

/** 목록·카드 등 표시용 냥 축약 (구매·차감 금액에는 사용하지 않음) — 1경↑ 경+조, 1조↑ 조만, 1조↓ 억만 */
function shop_냥_표시($amount) {
    shop_스왑_함수_로드();
    $digits = shop_냥_값($amount);
    if ($digits === '0') {
        return '0';
    }
    if (function_exists('냥_경조_축약표시')) {
        if (!function_exists('bccomp') || bccomp($digits, '100000000') >= 0) {
            return 냥_경조_축약표시($digits);
        }
    }
    if (function_exists('bccomp')) {
        if (bccomp($digits, '10000') >= 0) {
            $만 = (int)bcdiv($digits, '10000', 0);
            return number_format($만) . '만';
        }
        return number_format((int)$digits);
    }
    $n = (float)$digits;
    if ($n >= 1e8 && function_exists('냥_경조_축약표시')) {
        return 냥_경조_축약표시($digits);
    }
    if ($n >= 10000) {
        return number_format((int)floor($n / 10000)) . '만';
    }
    return number_format((int)$n);
}

/** 등록·수정 미리보기용 JS 축약 함수 */
function shop_냥_표시_js() {
    return <<<'JS'
function shopFormatNyangShort(n) {
  const raw = String(n ?? '0').replace(/[^\d]/g, '') || '0';
  const G = '10000000000000000';
  const J = '1000000000000';
  const E = '100000000';
  const M = '10000';

  if (typeof BigInt !== 'undefined' && raw.length > 15) {
    let v = BigInt(raw);
    const Gn = 10000000000000000n;
    const Jn = 1000000000000n;
    const En = 100000000n;
    const Mn = 10000n;
    const fmt = (x) => x.toLocaleString('ko-KR');
    if (v >= Gn) {
      const g = v / Gn;
      const j = (v % Gn) / Jn;
      let s = fmt(g) + '경';
      if (j > 0n) s += ' ' + fmt(j) + '조';
      return s;
    }
    if (v >= Jn) return fmt(v / Jn) + '조';
    if (v >= En) return fmt(v / En) + '억';
    if (v >= Mn) return fmt(v / Mn) + '만';
    return fmt(v);
  }

  let num = Number(raw);
  if (!Number.isFinite(num)) num = 0;
  num = Math.floor(num);
  const Gi = 10000000000000000;
  const Ji = 1000000000000;
  const Ei = 100000000;
  const Mi = 10000;
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
  const da = String(a ?? '0').replace(/[^\d]/g, '') || '0';
  const db = String(b ?? '0').replace(/[^\d]/g, '') || '0';
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

function shop_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') {
        return null;
    }
    $code_esc = addslashes($code);
    $row = db_select("SELECT name, point, newpoint FROM tb_member WHERE code = '{$code_esc}' LIMIT 1");
    if (empty($row['name'])) {
        return null;
    }
    return [
        'nick'      => trim($row['name']),
        'point'     => (int)floor((float)($row['point'] ?? 0)),
        'newpoint'  => (float)($row['newpoint'] ?? 0),
        'code'      => $code,
    ];
}

function shop_코드없음_메시지() {
    return '상황실에서 코드를 발급받아, 코드가 포함된 링크로 1회 접속해주세요.';
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
    return '<link rel="stylesheet" href="/shop/shop.css?v=15">';
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

function shop_상품목록_조회() {
    shop_매입컬럼_보장();
    $rs = @db_query("SELECT * FROM tb_gifticon WHERE status IN ('sale','reserved','sold')
        ORDER BY FIELD(status, 'sale', 'reserved', 'sold'), idx DESC LIMIT 200");
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
        $price = shop_냥_값($row['price_nyang']);
        if ($price === '0' && $face_value > 0) {
            $price = shop_냥_값(shop_원화_냥환산($face_value, $판매자));
        }
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
            'price_np'   => $price_np,
            'price_updated_at' => $price_updated_at,
            'price_updated_fmt' => shop_가격갱신시각_표시($price_updated_at),
            'seller'     => $판매자,
            'market_tax' => $market_tax,
            'prebuy_np_per_10000' => shop_선매입_만원당_newpoint($판매자),
            'category'   => $cat,
            'emoji'      => $row['emoji'] ?: shop_카테고리_이모지($cat),
            'expire'     => $row['expire_date'],
            'status'     => $row['status'],
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
    $msg = '🛒 아영이네 마켓 — 판매중';
    if (empty($목록)) {
        $msg .= "\n\n지금 판매 중인 상품이 없어요.";
        $msg .= "\n" . shop_마켓_공개url();
        return $msg;
    }
    $msg .= ' (' . count($목록) . ")\n\n";
    $i = 0;
    foreach ($목록 as $상품) {
        $i++;
        $이름 = trim((string)$상품['name']);
        $브랜드 = trim((string)$상품['brand']);
        if ($브랜드 !== '') {
            $이름 = $브랜드 . ' ' . $이름;
        }
        $msg .= "{$i}) {$상품['emoji']} {$이름}\n";
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
        $목록[] = [
            'nick'       => $nick,
            'cnt'        => (int)($row['cnt'] ?? 0),
            'total_face' => (int)($row['total_face'] ?? 0),
        ];
    }
    return $목록;
}

/** 채팅 .마켓큰손 — 권면가 합계 상위 판매자 */
function shop_채팅_마켓큰손_문구($limit = 10) {
    $limit = max(1, min(30, (int)$limit));
    $목록 = shop_마켓큰손_조회($limit);
    $msg = '🏪 아영이네 마켓 — 큰손 랭킹';
    $msg .= "\n(누적 등록 권면가 합계 · 상위 {$limit}명)";
    if (empty($목록)) {
        $msg .= "\n\n아직 등록 내역이 없어요.";
        $msg .= "\n" . shop_마켓_공개url();
        return $msg;
    }
    $msg .= "\n\n";
    $rank = 1;
    foreach ($목록 as $행) {
        $msg .= "{$rank}등 {$행['nick']} — " . number_format($행['total_face']) . "원 ({$행['cnt']}건)\n";
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

/** 채팅방 공지 큐 (info1.php 등에서 status=0 건을 전송) */
function shop_채팅알림_등록($msg, $item = '아영이네마켓') {
    $msg = trim((string)$msg);
    if ($msg === '') {
        return false;
    }
    $msg_esc = addslashes($msg);
    $item_esc = addslashes((string)$item);
    return (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
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
    $msg = "{$emoji} 아영이네 마켓 — {$상품명} 구매 완료! ({$결제라벨})";

    $item = 'gifticon_' . (int)($상품행['idx'] ?? 0);
    return shop_채팅알림_등록($msg, $item);
}

function shop_등록알림_등록($판매자, $상품정보) {
    $판매자 = trim((string)$판매자);
    $상품명 = trim((string)($상품정보['name'] ?? ''));
    $가격 = shop_냥_값($상품정보['price_nyang'] ?? 0);
    $emoji = trim((string)($상품정보['emoji'] ?? '🎫'));
    if ($emoji === '') {
        $emoji = '🎫';
    }

    $msg = "{$emoji} 아영이네 마켓 신규 등록!\n";
    $msg .= "판매자: {$판매자}\n";
    $msg .= "상품: {$상품명}\n";
    $msg .= "가격: " . shop_냥_표시($가격) . "게임냥";

    $item = 'gifticon_reg_' . (int)($상품정보['idx'] ?? 0);
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
    $rs = @db_query("SELECT * FROM tb_gifticon WHERE buyer_nick = '{$nick_esc}' AND status = 'sold' ORDER BY sold_at DESC, idx DESC LIMIT 100");
    if (!$rs) {
        return [];
    }
    return shop_목록_가공($rs);
}

function shop_구매통계($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['count' => 0, 'total_won' => 0, 'total_nyang' => 0];
    }
    $nick_esc = addslashes($nick);
    $row = db_select("SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(face_value), 0) AS total_won,
        COALESCE(SUM(price_nyang), 0) AS total_nyang
        FROM tb_gifticon WHERE buyer_nick = '{$nick_esc}' AND status = 'sold'");
    return [
        'count'       => (int)($row['cnt'] ?? 0),
        'total_won'   => (int)($row['total_won'] ?? 0),
        'total_nyang' => (int)($row['total_nyang'] ?? 0),
    ];
}

function shop_판매목록_조회($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return [];
    }
    shop_매입컬럼_보장();
    $nick_esc = addslashes($nick);
    $rs = @db_query("SELECT * FROM tb_gifticon
        WHERE seller_nick = '{$nick_esc}' AND status IN ('sale', 'reserved', 'sold')
        ORDER BY FIELD(status, 'sale', 'reserved', 'sold'),
                 IF(status = 'sold', sold_at, regdate) DESC,
                 idx DESC
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
    $rs = @db_query("SELECT * FROM tb_gifticon
        WHERE status IN ('sale', 'reserved', 'sold')
        ORDER BY FIELD(status, 'sale', 'reserved', 'sold'),
                 IF(status = 'sold', sold_at, regdate) DESC,
                 idx DESC
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
        COALESCE(SUM(CASE WHEN status = 'sold' THEN price_nyang ELSE 0 END), 0) AS total_nyang,
        COUNT(DISTINCT seller_nick) AS seller_cnt
        FROM tb_gifticon
        WHERE status IN ('sale', 'reserved', 'sold')");
    return [
        'count'       => (int)($row['cnt'] ?? 0),
        'total_won'   => (int)($row['total_won'] ?? 0),
        'total_nyang' => (int)($row['total_nyang'] ?? 0),
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

    $상품 = shop_판매상품_단건($id, $nick);
    if (!$상품) {
        return ['ok' => false, 'msg' => '판매중인 본인 상품만 삭제할 수 있습니다.'];
    }

    $닉_esc = addslashes($nick);
    $ok = db_query("DELETE FROM tb_gifticon WHERE idx = {$id} AND seller_nick = '{$닉_esc}' AND status = 'sale' LIMIT 1");
    if (!$ok) {
        return ['ok' => false, 'msg' => '삭제에 실패했습니다.'];
    }

    if (!empty($상품['image_file'])) {
        $path = shop_이미지_물리경로($상품['image_file']);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

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
        return 0;
    }
    $nick_esc = addslashes($nick);
    $currency = shop_구매통화_정규화($currency);
    if ($currency === 'newpoint') {
        $row = db_select("SELECT COALESCE(SUM(paid_newpoint), 0) AS total
            FROM tb_gifticon
            WHERE seller_nick = '{$nick_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = 'newpoint'");
    } else {
        $row = db_select("SELECT COALESCE(SUM(price_nyang), 0) AS total
            FROM tb_gifticon
            WHERE seller_nick = '{$nick_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = 'point'");
    }
    return (int)($row['total'] ?? 0);
}

function shop_정산_실지급액($gross) {
    $gross = (int)$gross;
    if ($gross <= 0) {
        return 0;
    }
    return (int)floor($gross * (1 - SHOP_정산_수수료율));
}

function shop_정산_소멸액($gross) {
    $gross = (int)$gross;
    if ($gross <= 0) {
        return 0;
    }
    return $gross - shop_정산_실지급액($gross);
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
        return ['count' => 0, 'total_won' => 0, 'total_nyang' => 0, 'settled_nyang' => 0];
    }
    $nick_esc = addslashes($nick);
    $row = db_select("SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(face_value), 0) AS total_won,
        COALESCE(SUM(price_nyang), 0) AS total_nyang,
        COALESCE(SUM(CASE WHEN settled = 1 THEN price_nyang ELSE 0 END), 0) AS settled_nyang
        FROM tb_gifticon WHERE seller_nick = '{$nick_esc}' AND status = 'sold'");
    return [
        'count'         => (int)($row['cnt'] ?? 0),
        'total_won'     => (int)($row['total_won'] ?? 0),
        'total_nyang'   => (int)($row['total_nyang'] ?? 0),
        'settled_nyang' => (int)($row['settled_nyang'] ?? 0),
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
        'market' => ['href' => '/shop/' . ($code_q ? '?' . $code_q : ''), 'label' => '🏪 아영이네 마켓'],
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

    $cols = [
        'price_newpoint' => "ADD COLUMN `price_newpoint` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '판매가(본방냥·등록 시점 환산)' AFTER `price_nyang`",
        'price_updated_at' => "ADD COLUMN `price_updated_at` DATETIME NULL DEFAULT NULL COMMENT '판매가 최근 갱신 시각' AFTER `price_newpoint`",
        'prebuy'       => "ADD COLUMN `prebuy` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '관리자 선매입'",
        'prebuy_at'    => "ADD COLUMN `prebuy_at` DATETIME NULL DEFAULT NULL",
        'prebuy_admin' => "ADD COLUMN `prebuy_admin` VARCHAR(30) NULL DEFAULT NULL",
        'prebuy_paid'  => "ADD COLUMN `prebuy_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '선매입 지급액'",
        'paid_newpoint' => "ADD COLUMN `paid_newpoint` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '본냥 구매 결제액' AFTER `prebuy_paid`",
        'buy_currency'  => "ADD COLUMN `buy_currency` VARCHAR(10) NOT NULL DEFAULT 'point' COMMENT '구매 결제 통화 point|newpoint' AFTER `paid_newpoint`",
    ];

    foreach ($cols as $col => $alter) {
        $rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE '{$col}'");
        $row = $rs ? db_fetch($rs) : null;
        if (!empty($row['Field'])) {
            continue;
        }
        @db_query("ALTER TABLE tb_gifticon {$alter}");
    }

    $paid_rs = @db_query("SHOW COLUMNS FROM tb_gifticon LIKE 'prebuy_paid'");
    $paid_row = $paid_rs ? db_fetch($paid_rs) : null;
    if (!empty($paid_row['Field']) && stripos((string)($paid_row['Type'] ?? ''), 'bigint') === false) {
        @db_query("ALTER TABLE tb_gifticon MODIFY COLUMN `prebuy_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '선매입 지급액'");
    }

    @db_query("UPDATE tb_gifticon SET prebuy = 0 WHERE prebuy IS NULL");
}

function shop_매입_본인상품_허용($admin_nick) {
    return trim((string)$admin_nick) === '준호';
}

function shop_선매입_수정_허용($admin_nick) {
    return trim((string)$admin_nick) === '준호';
}

function shop_수정_가능($상품행, $actor_nick = '', $관리자타인수정 = false) {
    if (empty($상품행['idx']) && empty($상품행['id'])) {
        return false;
    }
    if (($상품행['status'] ?? '') !== 'sale') {
        return false;
    }
    if ((int)($상품행['prebuy'] ?? 0) === 1) {
        return shop_선매입_수정_허용($actor_nick) && $관리자타인수정;
    }
    return true;
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
    $판매자 = trim((string)($상품행['seller_nick'] ?? ($상품행['seller'] ?? '')));
    $관리자 = trim((string)$admin_nick);
    if ($관리자 !== '' && $판매자 !== '' && $판매자 === $관리자 && !shop_매입_본인상품_허용($관리자)) {
        return false;
    }
    return true;
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
    $실지급 = shop_선매입_권면가_newpoint($권면가, $판매자);
    if ($실지급 < 1) {
        return ['ok' => false, 'msg' => '매입 지급액을 계산할 수 없습니다.'];
    }
    $판매자_esc = addslashes($판매자);
    $관리자_esc = addslashes($admin_nick);

    if (!shop_관리자_닉($admin_nick)) {
        return ['ok' => false, 'msg' => '관리자만 매입할 수 있습니다.'];
    }

    global $conn;

    $ok = db_query("UPDATE tb_gifticon SET
        prebuy = 1,
        prebuy_at = NOW(),
        prebuy_admin = '{$관리자_esc}',
        prebuy_paid = {$실지급}
        WHERE idx = {$gifticon_id} AND status = 'sale' AND IFNULL(prebuy, 0) = 0 LIMIT 1");
    $affected = ($ok && $conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
    if (!$ok || $affected < 1) {
        $db_err = ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
        return ['ok' => false, 'msg' => '매입 처리에 실패했습니다.' . ($db_err !== '' ? "\n({$db_err})" : '')];
    }

    if ($실지급 > 0 && $판매자 !== '') {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$실지급} WHERE name = '{$판매자_esc}' LIMIT 1");
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

    shop_매입알림_등록($admin_nick, $상품, $실지급, $market_tax);

    $상품명 = trim((string)($상품['name'] ?? ''));
    $msg = ($상품명 !== '' ? "{$상품명} " : '') . '매입 완료! ' . $판매자 . '님에게 본방냥 ' . shop_매입_지급_표시($실지급) . ' 선지급';

    return [
        'ok'       => true,
        'msg'      => $msg,
        'paid'     => $실지급,
        'face_value' => $권면가,
    ];
}

function shop_매입알림_등록($관리자, $상품행, $실지급, $market_tax = null) {
    $상품명 = trim((string)($상품행['name'] ?? ''));
    if ($상품명 === '') {
        $상품명 = '기프티콘';
    }
    $권면가 = (int)($상품행['face_value'] ?? 0);

    $msg = "마켓의 {$상품명}을 선매입하였습니다";
    $msg .= "\n권면가: " . number_format($권면가) . "원";
    $msg .= "\n지급된 본방냥: " . shop_매입_지급_표시($실지급);

    $item = 'gifticon_prebuy_' . (int)($상품행['idx'] ?? 0);
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
    $rs = @db_query("SELECT idx, seller_nick, name, face_value, price_nyang, price_newpoint, status, expire_date
        FROM tb_gifticon WHERE status IN ('sale', 'reserved') ORDER BY idx DESC");
    if (!$rs) {
        return [];
    }
    $목록 = [];
    while ($row = db_fetch($rs)) {
        $face_value = (int)($row['face_value'] ?? 0);
        $seller = trim((string)($row['seller_nick'] ?? ''));
        $old_nyang = (int)($row['price_nyang'] ?? 0);
        $new_nyang = shop_냥_값(shop_원화_냥환산($face_value, $seller));
        $old_newpoint = (int)($row['price_newpoint'] ?? 0);
        $new_newpoint = shop_선매입_권면가_newpoint($face_value, $seller);
        $changed_nyang = ($new_nyang !== $old_nyang);
        $changed_newpoint = ($new_newpoint !== $old_newpoint);
        $목록[] = [
            'id'         => (int)$row['idx'],
            'seller'     => $seller,
            'name'       => $row['name'] ?? '',
            'face_value' => $face_value,
            'old_price'  => $old_nyang,
            'new_price'  => $new_nyang,
            'diff'       => $new_nyang - $old_nyang,
            'old_price_nyang' => $old_nyang,
            'new_price_nyang' => $new_nyang,
            'old_price_newpoint' => $old_newpoint,
            'new_price_newpoint' => $new_newpoint,
            'changed_nyang' => $changed_nyang,
            'changed_newpoint' => $changed_newpoint,
            'status'     => $row['status'] ?? '',
            'expire'     => $row['expire_date'] ?? '',
            'changed'    => ($changed_nyang || $changed_newpoint),
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
        $new_newpoint = shop_냥_값($item['new_price_newpoint']);
        if ($new_nyang === '0' && (int)($item['face_value'] ?? 0) > 0) {
            $new_nyang = shop_냥_값(shop_원화_냥환산((int)$item['face_value'], (string)($item['seller'] ?? '')));
        }
        if ($new_newpoint === '0' && (int)($item['face_value'] ?? 0) > 0) {
            $new_newpoint = shop_냥_값(shop_선매입_권면가_newpoint((int)$item['face_value'], (string)($item['seller'] ?? '')));
        }
        $ok = db_query("UPDATE tb_gifticon SET
            price_nyang = {$new_nyang},
            price_newpoint = {$new_newpoint},
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
            if (!empty($item['changed_newpoint'])) {
                $updated_newpoint++;
                $changed_items_newpoint[] = [
                    'id'         => $id,
                    'name'       => $item['name'],
                    'seller'     => $item['seller'],
                    'face_value' => $item['face_value'],
                    'old_price'  => $item['old_price_newpoint'],
                    'new_price'  => $new_newpoint,
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
