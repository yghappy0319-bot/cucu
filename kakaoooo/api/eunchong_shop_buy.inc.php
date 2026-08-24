<?php
/**
 * 은총1/은총2 상점 시세·구매 (info2 · info3 공용)
 * 은총1: 마켓 권면가 11000원 → 선매입 본방냥과 동일 환산 (본방총합×2.5% × 11000/10000)
 * 은총2: 마켓 권면가 10000원 → 판매등록 게임냥과 동일 환산 (게임총합×2.5% × 10000/10000)
 * 지급: bag 은총 (수량만큼)
 * ※ EUNCHONG_BUY_ENABLED 로 은총(겜냥) 구매 on/off
 */
if (!defined('EUNCHONG_BUY_ENABLED')) {
  define('EUNCHONG_BUY_ENABLED', true);
}
/** 은총1(본방냥) 권면가 */
if (!defined('EUNCHONG_BUY_FACE_BONNYANG')) {
  define('EUNCHONG_BUY_FACE_BONNYANG', 11000);
}
/** 은총2(게임냥) 권면가 — 여기만 바꾸면 채팅·거래소 전부 반영 */
if (!defined('EUNCHONG_BUY_FACE_GAME')) {
  define('EUNCHONG_BUY_FACE_GAME', 10000);
}
if (!defined('EUNCHONG_BUY_BONNYANG_ENABLED')) {
  define('EUNCHONG_BUY_BONNYANG_ENABLED', false);
}
if (!function_exists('info3_shop_로드')) {
  function info3_shop_로드(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $path = dirname(__DIR__) . '/shop/_shop.php';
    if (is_file($path)) {
      require_once $path;
    }
  }
}

if (!function_exists('info3_권면가_본방환산')) {
  /** 마켓 선매입/등록 프리뷰와 동일 — 권면가(원) → 본방냥 문자열 */
  function info3_권면가_본방환산(int $권면가): string {
    $권면가 = max(0, $권면가);
    if ($권면가 < 1) {
      return '0';
    }
    info3_shop_로드();
    $만원기준 = defined('SHOP_만원기준원화') ? (int)SHOP_만원기준원화 : 10000;
    if ($만원기준 < 1) {
      $만원기준 = 10000;
    }
    $tax = function_exists('shop_판매등록_market_tax') ? shop_판매등록_market_tax() : 2.5;
    if (function_exists('shop_매입_만원당_newpoint_요율')) {
      $만원당 = shop_매입_만원당_newpoint_요율($tax);
    } else {
      $전체본방 = function_exists('시세기준_본방냥') ? 시세기준_본방냥() : 0;
      $만원당 = function_exists('냥_비율내림')
        ? 냥_비율내림($전체본방, ((float)$tax) / 100)
        : (int)floor((float)$전체본방 * ((float)$tax) / 100);
    }
    $만원당Str = function_exists('shop_냥_값')
      ? shop_냥_값($만원당)
      : (function_exists('냥_정수문자열') ? 냥_정수문자열($만원당) : (string)max(0, (int)$만원당));
    if ($만원당Str === '0') {
      return '0';
    }
    // (만원당 × 권면가) ÷ 10000 — 마켓 register.php calcPrebuyNp / shop_선매입_권면가_newpoint 와 동일
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return bcdiv(bcmul($만원당Str, (string)$권면가, 0), (string)$만원기준, 0);
    }
    if (function_exists('shop_문자열_곱하기') && function_exists('shop_문자열_나누기_내림')) {
      return shop_문자열_나누기_내림(shop_문자열_곱하기($만원당Str, $권면가), $만원기준);
    }
    return (string)(int)floor(((float)$만원당Str) * $권면가 / $만원기준);
  }
}

if (!function_exists('info3_권면가_게임냥환산')) {
  /** 마켓 판매등록 게임냥과 동일 — 권면가(원) → 게임냥 문자열 (float/(int) 금지) */
  function info3_권면가_게임냥환산(int $권면가): string {
    $권면가 = max(0, $권면가);
    if ($권면가 < 1) {
      return '0';
    }
    info3_shop_로드();
    if (function_exists('shop_원화_냥환산')) {
      $v = shop_원화_냥환산($권면가);
      return function_exists('shop_냥_값') ? shop_냥_값($v) : (function_exists('냥_정수문자열') ? 냥_정수문자열($v) : preg_replace('/\D/', '', (string)$v));
    }
    // 폴백: (게임총합 × 25 ÷ 1000) × 권면가 ÷ 10000 — float 캐스팅 시 ~922경으로 잘림
    $전체게임 = function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : (function_exists('시세기준_게임냥') ? 시세기준_게임냥() : '0');
    $전체게임 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($전체게임)
      : (ltrim(preg_replace('/\D/', '', (string)$전체게임), '0') ?: '0');
    if ($전체게임 === '0') {
      return '0';
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      $만원당 = bcdiv(bcmul($전체게임, '25', 0), '1000', 0);
      if ($만원당 === '0') {
        return '0';
      }
      return bcdiv(bcmul($만원당, (string)$권면가, 0), '10000', 0);
    }
    if (function_exists('shop_문자열_곱하기') && function_exists('shop_문자열_나누기_내림')) {
      $만원당 = shop_문자열_나누기_내림(shop_문자열_곱하기($전체게임, 25), 1000);
      if ($만원당 === '0') {
        return '0';
      }
      return shop_문자열_나누기_내림(shop_문자열_곱하기($만원당, $권면가), 10000);
    }
    return '0';
  }
}

if (!function_exists('info3_은총_금액_자리맞춤')) {
  /**
   * 은총 금액 단위 절사
   * · 만(10^4) · 억(10^8) · 조(10^12) · 경(10^16)
   * · 금액이 해당 단위 미만이면 0 (호출측에서 하위 단위로 재시도)
   * @param string $단위 '만'|'억'|'조'|'경'
   */
  function info3_은총_금액_자리맞춤($금액, string $단위 = '만'): string {
    $s = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : preg_replace('/[^\d]/', '', (string)$금액);
    $s = ltrim((string)$s, '0') ?: '0';
    if ($s === '0') {
      return '0';
    }
    static $steps = [
      '만' => '10000',
      '억' => '100000000',
      '조' => '1000000000000',
      '경' => '10000000000000000',
    ];
    $단위 = isset($steps[$단위]) ? $단위 : '만';
    $step = $steps[$단위];
    if (function_exists('bcdiv') && function_exists('bcmul') && function_exists('bccomp')) {
      if (bccomp($s, $step, 0) < 0) {
        return '0';
      }
      return bcmul(bcdiv($s, $step, 0), $step, 0);
    }
    $stepLen = strlen($step) - 1; // 10^N → N trailing zeros
    if (strlen($s) <= $stepLen) {
      return '0';
    }
    return substr($s, 0, -$stepLen) . str_repeat('0', $stepLen);
  }
}

if (!function_exists('info3_은총_금액_자리맞춤_적응')) {
  /**
   * 선호 단위부터 내려가며 절사 — 1경 미만(삭감 후)도 조·억·만으로 0이 되지 않게
   * @param string $선호 '경'|'조'|'억'|'만'
   */
  function info3_은총_금액_자리맞춤_적응($금액, string $선호 = '경'): string {
    $s = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    if ($s === '0') {
      return '0';
    }
    $순서 = ['경', '조', '억', '만'];
    $시작 = array_search($선호, $순서, true);
    if ($시작 === false) {
      $시작 = 0;
    }
    for ($i = (int)$시작; $i < count($순서); $i++) {
      $rounded = info3_은총_금액_자리맞춤($s, $순서[$i]);
      if ($rounded !== '0') {
        return $rounded;
      }
    }
    // 만 미만이면 원가 유지 (구매 불가 0 방지)
    return $s;
  }
}

if (!function_exists('info3_은총1_구매단가')) {
  /** 본방냥 단가 — 마켓 권면가 환산 후 만↓ 적응 절사 */
  function info3_은총1_구매단가(): string {
    return info3_은총_금액_자리맞춤_적응(info3_권면가_본방환산((int)EUNCHONG_BUY_FACE_BONNYANG), '만');
  }
}

if (!function_exists('info3_은총2_구매단가')) {
  /** 게임냥 단가 — 마켓 권면가 환산 후 경↓ 적응 절사 (삭감 후 1경 미만 → 조·억) */
  function info3_은총2_구매단가(): string {
    return info3_은총_금액_자리맞춤_적응(info3_권면가_게임냥환산((int)EUNCHONG_BUY_FACE_GAME), '경');
  }
}

if (!function_exists('info3_은총구매_목록_문구')) {
  /** `.구매` 목록에 붙일 은총 행 (구매 비활성 시 빈 문자열) */
  function info3_은총구매_목록_문구($단위 = '냥'): string {
    if (!(defined('EUNCHONG_BUY_ENABLED') && EUNCHONG_BUY_ENABLED)) {
      return '';
    }
    $겜냥가 = info3_은총2_구매단가();
    $겜표시 = function_exists('구매가_축약표시')
      ? 구매가_축약표시($겜냥가, '')
      : number_format((float)$겜냥가);
    $행 = "은총 : {$겜표시}냥<br>";
    if (defined('EUNCHONG_BUY_BONNYANG_ENABLED') && EUNCHONG_BUY_BONNYANG_ENABLED) {
      $본냥가 = info3_은총1_구매단가();
      $본표시 = function_exists('newpoint표시')
        ? newpoint표시($본냥가)
        : (function_exists('구매가_축약표시') ? 구매가_축약표시($본냥가, '') : number_format((float)$본냥가));
      $행 = "은총1(본냥) : {$본표시}냥<br>" . $행;
    }
    return $행;
  }
}

if (!function_exists('info3_은총_문자열곱소')) {
  /** 큰 금액 × 작은 정수 — (int)/float 캐스팅 금지 */
  function info3_은총_문자열곱소(string $금액, int $배수): string {
    $금액 = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : (ltrim(preg_replace('/\D/', '', $금액), '0') ?: '0');
    $배수 = (int)$배수;
    if ($금액 === '0' || $배수 === 0) {
      return '0';
    }
    if ($배수 === 1) {
      return $금액;
    }
    if ($배수 < 0) {
      $배수 = abs($배수);
    }
    if (function_exists('bcmul')) {
      return bcmul($금액, (string)$배수, 0);
    }
    if (function_exists('냥_금액_문자열곱')) {
      return 냥_금액_문자열곱($금액, (string)$배수);
    }
    if (function_exists('shop_문자열_곱하기')) {
      return shop_문자열_곱하기($금액, $배수);
    }
    // 자리수 곱셈
    $carry = 0;
    $out = '';
    for ($i = strlen($금액) - 1; $i >= 0; $i--) {
      $prod = ((int)$금액[$i]) * $배수 + $carry;
      $out = (string)($prod % 10) . $out;
      $carry = intdiv($prod, 10);
    }
    if ($carry > 0) {
      $out = (string)$carry . $out;
    }
    return ltrim($out, '0') ?: '0';
  }
}

if (!function_exists('info3_은총_문자열합')) {
  function info3_은총_문자열합(string $a, string $b): string {
    if (function_exists('bcadd')) {
      return bcadd($a, $b, 0);
    }
    if (function_exists('냥_금액_문자열합')) {
      return 냥_금액_문자열합($a, $b);
    }
    // 학교식 덧셈
    $a = ltrim(preg_replace('/\D/', '', $a), '0') ?: '0';
    $b = ltrim(preg_replace('/\D/', '', $b), '0') ?: '0';
    $a = strrev($a);
    $b = strrev($b);
    $len = max(strlen($a), strlen($b));
    $carry = 0;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
      $sum = $carry + (int)($a[$i] ?? '0') + (int)($b[$i] ?? '0');
      $out .= (string)($sum % 10);
      $carry = intdiv($sum, 10);
    }
    if ($carry > 0) {
      $out .= (string)$carry;
    }
    return ltrim(strrev($out), '0') ?: '0';
  }
}

if (!function_exists('info3_은총_ceil1퍼센트')) {
  /** ceil(금액 × 1%) — (int)/float 금지 */
  function info3_은총_ceil1퍼센트(string $금액): string {
    $금액 = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : (ltrim(preg_replace('/\D/', '', $금액), '0') ?: '0');
    if ($금액 === '0') {
      return '0';
    }
    if (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
      $raw = bcmul($금액, '0.01', 12);
      $floor = bcadd($raw, '0', 0);
      if (bccomp($raw, $floor, 12) > 0) {
        return bcadd($floor, '1', 0);
      }
      return $floor;
    }
    // ceil(n/100) = floor((n+99)/100)
    $n = info3_은총_문자열합($금액, '99');
    if (strlen($n) <= 2) {
      return ($n === '0') ? '0' : '1';
    }
    return ltrim(substr($n, 0, -2), '0') ?: '0';
  }
}

if (!function_exists('info3_은총구매_총액계산')) {
  /**
   * @return array{ok:bool,단가:string,총액:string,소량추가금:string}
   */
  function info3_은총구매_총액계산(string $단가, int $수량): array {
    $단가 = function_exists('냥_정수문자열') ? 냥_정수문자열($단가) : (ltrim(preg_replace('/\D/', '', $단가), '0') ?: '0');
    if ($단가 === '' || $단가 === '0') {
      $단가 = '0';
    }
    $수량 = max(1, $수량);
    $총액 = info3_은총_문자열곱소($단가, $수량);
    $소량 = '0';
    if ($수량 < 10) {
      $소량 = info3_은총_ceil1퍼센트($총액);
      $총액 = info3_은총_문자열합($총액, $소량);
    }
    return ['ok' => true, '단가' => $단가, '총액' => $총액, '소량추가금' => $소량];
  }
}

if (!function_exists('info3_은총구매_실행')) {
  /**
   * `.구매 은총|은총1|은총2` (info2·info3)
   * 은총 = 겜냥 구매(구 은총2). 은총2도 동일하게 허용.
   * @return array{ok:bool,msg:string}|null null이면 일반 구매 로직으로
   */
  function info3_은총구매_실행(string $닉, array $정보, string $아이템명, int $수량): ?array {
    $아이템명 = trim($아이템명);
    if ($아이템명 !== '은총' && $아이템명 !== '은총1' && $아이템명 !== '은총2') {
      return null;
    }
    if (!(defined('EUNCHONG_BUY_ENABLED') && EUNCHONG_BUY_ENABLED)) {
      return ['ok' => false, 'msg' => '❌ 은총 구매는 잠시 닫혀 있어요.'];
    }
    // 본냥 은총1 — 비활성 시 차단
    if ($아이템명 === '은총1' && !(defined('EUNCHONG_BUY_BONNYANG_ENABLED') && EUNCHONG_BUY_BONNYANG_ENABLED)) {
      return ['ok' => false, 'msg' => '❌ 은총1(본냥) 구매는 잠시 닫혀 있어요.'];
    }
    $수량 = max(1, $수량);
    $본냥결제 = ($아이템명 === '은총1');
    // 표시·로그는 '은총'으로 통일 (은총2 입력도 동일)
    if (!$본냥결제) {
      $아이템명 = '은총';
    }
    $단가 = $본냥결제 ? info3_은총1_구매단가() : info3_은총2_구매단가();
    $견적 = info3_은총구매_총액계산($단가, $수량);
    // 삭감 후 1경 미만 등으로 고정 절사하면 총액이 0이 됨 → 적응 절사
    $총액 = info3_은총_금액_자리맞춤_적응($견적['총액'], $본냥결제 ? '만' : '경');
    $소량 = ($견적['소량추가금'] === '0' || $견적['소량추가금'] === 0)
      ? '0'
      : info3_은총_금액_자리맞춤_적응($견적['소량추가금'], $본냥결제 ? '만' : '경');
    $단위라벨 = $본냥결제 ? '본냥' : '겜냥';

    if ($총액 === '0' || (function_exists('bccomp') && bccomp($총액, '0', 0) <= 0)) {
      return ['ok' => false, 'msg' => "❌ 현재 시세로는 {$아이템명}을(를) 구매할 수 없어요."];
    }

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    // 은총1 → 본방냥(newpoint) 차감 / 은총2 → 게임냥(point) 차감
    $보유컬럼 = $본냥결제 ? 'newpoint' : 'point';
    $bal = db_select("
      SELECT CAST(IFNULL(point, 0) AS CHAR) AS point,
             IFNULL(newpoint, 0) AS newpoint
      FROM tb_member WHERE idx = {$내idx} LIMIT 1
    ");
    if (empty($bal)) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    $보유원시 = $본냥결제 ? ($bal['newpoint'] ?? 0) : ($bal['point'] ?? 0);
    $보유원본문자 = trim((string)$보유원시);
    $보유음수 = (isset($보유원본문자[0]) && $보유원본문자[0] === '-' && ltrim(substr($보유원본문자, 1), '0') !== '');
    if ($본냥결제) {
      $보유비교 = function_exists('냥_정수문자열')
        ? 냥_정수문자열($보유원시)
        : (ltrim(preg_replace('/\D/', '', (string)$보유원시), '0') ?: '0');
    } else {
      $보유비교 = function_exists('냥_정수문자열')
        ? 냥_정수문자열($보유원시)
        : (ltrim(preg_replace('/\D/', '', (string)$보유원시), '0') ?: '0');
    }
    if ($보유음수) {
      $부족 = true;
    } elseif (function_exists('bccomp')) {
      $부족 = bccomp($보유비교, $총액, 0) < 0;
    } else {
      // 문자열 자릿수/사전순 비교 (float 금지)
      $a = ltrim($보유비교, '0') ?: '0';
      $b = ltrim($총액, '0') ?: '0';
      $부족 = (strlen($a) !== strlen($b)) ? (strlen($a) < strlen($b)) : ($a < $b);
    }
    if ($부족) {
      $총표시 = $본냥결제
        ? ((function_exists('newpoint표시') ? newpoint표시($총액) : number_format((float)$총액)) . '본냥')
        : (function_exists('구매가_축약표시') ? 구매가_축약표시($총액, $단위라벨) : ($총액 . $단위라벨));
      $단가표시 = $본냥결제
        ? ((function_exists('newpoint표시') ? newpoint표시($단가) : number_format((float)$단가)) . '본냥')
        : (function_exists('구매가_축약표시') ? 구매가_축약표시($단가, $단위라벨) : ($단가 . $단위라벨));
      $보유표시 = ($보유음수 ? '-' : '') . ($본냥결제
        ? ((function_exists('newpoint표시') ? newpoint표시($보유비교) : number_format((float)$보유비교)) . '본냥')
        : (function_exists('구매가_축약표시') ? 구매가_축약표시($보유비교, $단위라벨) : ($보유비교 . $단위라벨)));
      $음수안내 = $보유음수 ? "\n※ 마이너스 잔액 상태에서는 구매할 수 없어요." : '';
      return [
        'ok' => false,
        'msg' => "‼️보유 {$단위라벨} 부족!\n{$아이템명} : {$총표시} (단가 {$단가표시} × {$수량})\n현재 보유 : {$보유표시}{$음수안내}",
      ];
    }

    if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
      require_once __DIR__ . '/item_bag_enhance.inc.php';
    }
    if (!function_exists('bag_은총_가산')) {
      return ['ok' => false, 'msg' => '❌ 은총 지급 기능을 불러올 수 없어요.'];
    }

    $총액_sql = preg_replace('/[^\d]/', '', $총액) ?: '0';
    // 게임냥은 DECIMAL(65,0)로 가감 — BIGINT/(int) 오버플로(≈922경) 방지
    if ($본냥결제) {
      $okPay = db_query("
        UPDATE tb_member
        SET `{$보유컬럼}` = `{$보유컬럼}` - {$총액_sql}
        WHERE idx = {$내idx} AND `{$보유컬럼}` >= {$총액_sql}
        LIMIT 1
      ");
    } else {
      $okPay = db_query("
        UPDATE tb_member
        SET point = CAST(
              CAST(IFNULL(point, 0) AS DECIMAL(65,0)) - CAST('{$총액_sql}' AS DECIMAL(65,0))
            AS CHAR)
        WHERE idx = {$내idx}
          AND CAST(IFNULL(point, 0) AS DECIMAL(65,0)) >= CAST('{$총액_sql}' AS DECIMAL(65,0))
        LIMIT 1
      ");
    }
    global $conn;
    $affected = (isset($conn) && ($conn instanceof mysqli)) ? (int)mysqli_affected_rows($conn) : 0;
    if (!$okPay || $affected < 1) {
      return ['ok' => false, 'msg' => "‼️보유 {$단위라벨} 차감에 실패했어요. 잔액을 확인해주세요."];
    }

    $지급 = bag_은총_가산($닉, $수량);
    if (empty($지급['ok'])) {
      // 실패 시 환불
      if ($본냥결제) {
        db_query("UPDATE tb_member SET `{$보유컬럼}` = `{$보유컬럼}` + {$총액_sql} WHERE idx = {$내idx} LIMIT 1");
      } else {
        db_query("
          UPDATE tb_member
          SET point = CAST(
                CAST(IFNULL(point, 0) AS DECIMAL(65,0)) + CAST('{$총액_sql}' AS DECIMAL(65,0))
              AS CHAR)
          WHERE idx = {$내idx}
          LIMIT 1
        ");
      }
      return ['ok' => false, 'msg' => '❌ 은총 지급 실패: ' . ($지급['msg'] ?? '가방 오류')];
    }

    // 구매 로그 (저가 회수·감사용)
    $단가_sql = preg_replace('/\D/', '', (string)$단가) ?: '0';
    $소량_sql = preg_replace('/\D/', '', (string)$소량) ?: '0';
    $닉_log = addslashes($닉);
    $아이템_log = addslashes($아이템명);
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_eunchong_buy_log (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nick VARCHAR(32) NOT NULL,
        item VARCHAR(16) NOT NULL DEFAULT '은총2',
        qty INT UNSIGNED NOT NULL DEFAULT 1,
        unit_price DECIMAL(65,0) NOT NULL DEFAULT 0,
        paid_total DECIMAL(65,0) NOT NULL DEFAULT 0,
        surcharge DECIMAL(65,0) NOT NULL DEFAULT 0,
        channel VARCHAR(16) NOT NULL DEFAULT 'chat',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        KEY ix_nick_reg (nick, regdate),
        KEY ix_paid (paid_total)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    @db_query("
      INSERT INTO tb_eunchong_buy_log
        (nick, item, qty, unit_price, paid_total, surcharge, channel, regdate)
      VALUES
        ('{$닉_log}', '{$아이템_log}', {$수량}, {$단가_sql}, {$총액_sql}, {$소량_sql}, 'chat', NOW())
    ");
    if (!function_exists('item_trade_log_구매') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }
    if (function_exists('item_trade_log_구매')) {
      item_trade_log_구매(
        $닉,
        $아이템명,
        $수량,
        $단가,
        $총액,
        $소량,
        $내idx,
        'chat',
        '',
        $본냥결제 ? 'newpoint' : 'point'
      );
    }
    if (function_exists('지급로그') && !$본냥결제) {
      지급로그($아이템명 . '구매', $닉, '', 0, '-' . $총액_sql);
    }

    $수량표시 = ($수량 > 1) ? " {$수량}개" : '';
    $총표시 = $본냥결제
      ? ((function_exists('newpoint표시') ? newpoint표시($총액) : number_format((float)$총액)) . '본냥')
      : (function_exists('구매가_축약표시') ? 구매가_축약표시($총액, $단위라벨) : ($총액 . $단위라벨));
    $추가금문구 = '';
    if ($소량 !== '0' && $소량 !== '' && !(function_exists('bccomp') && bccomp($소량, '0', 0) <= 0)) {
      $소량표시 = $본냥결제
        ? ((function_exists('newpoint표시') ? newpoint표시($소량) : number_format((float)$소량)) . '본냥')
        : (function_exists('구매가_축약표시') ? 구매가_축약표시($소량, $단위라벨) : ($소량 . $단위라벨));
      $추가금문구 = "\n(10개 미만 1% 추가금: {$소량표시})";
    }
    $보유은총 = (int)($지급['qty'] ?? 0);
    return [
      'ok' => true,
      'msg' => "►[{$닉}] {$아이템명}{$수량표시} 구매 {$총표시}{$추가금문구}\n✨ 은총 +{$수량} (보유 {$보유은총}개)",
    ];
  }
}
