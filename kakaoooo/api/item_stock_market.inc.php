<?php
/**
 * 아이템 주식 시세 (A방식) — 기존 .구매/.판매 와 별도
 *
 * · 매수가 = 총게임냥 × tb_item.percent%  (억 절사, 기존 percent 공식과 동일)
 * · 은총 = 권면가→겜냥 · 거래소만 기본 2.5% (마켓 판매등록 10%와 별도) · 경 절사
 * · 매수 = 현재 시세 · 매도 = 시세 × (100−수수료)% · 차익 = 실수령 − 매입가
 * · 기본 매도 수수료 20% → 시세의 10% 금고(tax) · 10% 로또 당첨금
 * · (옵션) 유통량 곡선: 배수 = 1 + 유통량/기준 → 매수↑ 매도↓ (은총 포함)
 * · 대상: percent > 0 이고 buystatus=0 인 아이템 + 은총 + 공커대실권(공커만, 즉시 기간연장)
 * · 공커대실권: 커플별 기준가(amount) × (활성쌍/기준1) · 활성커플만(자숙공커 제외) · edate +7일×수량
 * · 기념주화(꼬벙·1주년): 구매 탭 노출 · 매수 불가 · 보유자만 매도(본방냥 시총 10%)
 * · 부루마블1등: tb_burumable_coin · 구매 탭에 안 올림 · 보유자만 매도(본방냥 시총 10%)
 * · 명령: .주가 / .매수 아이템 [수량] / .매도 아이템 [수량]
 */

if (!defined('아이템주식_스프레드_퍼센트')) {
  /** 매도 수수료% · 매도가 = 시세 × (100−이값)/100 · 차액 절반 금고·절반 로또 */
  define('아이템주식_스프레드_퍼센트', 20);
}

/**
 * 공커대실권 가격 배수 기준 쌍 수
 * 단가 = 커플별 기준가(amount) × (활성쌍 ÷ 기준)
 * 기준 1 → 1쌍이면 기준가 그대로, 쌍이 줄면 가격↓
 */
if (!defined('아이템주식_공커대실권_기준쌍')) {
  define('아이템주식_공커대실권_기준쌍', 1);
}

/** 거래소 은총 전용 요율(%) — 마켓 판매등록(2.5×4=10)과 분리 */
if (!defined('아이템주식_은총_요율')) {
  define('아이템주식_은총_요율', 2.5);
}

if (!function_exists('아이템주식_제외목록')) {
  /** 주식 percent 시세에 올리지 않을 sname (공커대실권은 별도 특수 시세) */
  function 아이템주식_제외목록(): array {
    return ['공커대실권', '일방신청권', '일방연장권', '로또티켓', '랜덤박스'];
  }
}

/** 거래소 팔기 전용 기념주화 — 보유자만 · 수령은 본방냥(newpoint) */
if (!defined('아이템주식_기념주화_기본매도가')) {
  define('아이템주식_기념주화_기본매도가', 10000); // sell 미설정 시 본방냥 기본가
}

/**
 * 게임포기 회원: 시세 분모·결제 통화를 본방냥(newpoint)으로
 * · 분모 = 시세기준_본방냥 (실시간 본방 총합)
 * · 매수/매도 차감·지급 = newpoint
 */
if (!function_exists('아이템주식_본방결제모드인가')) {
  function 아이템주식_본방결제모드인가(): bool {
    return !empty($GLOBALS['_item_stock_bonbang_pay']);
  }
}

/** 본방(info1) 공커대실권: 게임냥 시세 → 스왑가로 본방냥 결제 */
if (!function_exists('아이템주식_공커대실권_스왑본방결제인가')) {
  function 아이템주식_공커대실권_스왑본방결제인가(): bool {
    return !empty($GLOBALS['_item_stock_gongkeo_swap_np_pay']);
  }
}

if (!function_exists('아이템주식_공커대실권_스왑본방결제_설정')) {
  function 아이템주식_공커대실권_스왑본방결제_설정(bool $on = true): void {
    if ($on) {
      $GLOBALS['_item_stock_gongkeo_swap_np_pay'] = true;
    } else {
      unset($GLOBALS['_item_stock_gongkeo_swap_np_pay']);
    }
  }
}

/**
 * 게임냥 금액 → 현재 스왑가(전체 게임냥÷본방냥)로 본방냥 환산
 */
if (!function_exists('아이템주식_게임냥_스왑본방환산')) {
  function 아이템주식_게임냥_스왑본방환산($게임냥금액): string {
    $pt = 아이템주식_냥($게임냥금액);
    if ($pt === '0') {
      return '0';
    }
    $swapFile = __DIR__ . '/game/swap.inc.php';
    if (!function_exists('스왑_pt2np_지급계산') && is_file($swapFile)) {
      require_once $swapFile;
    }
    if (!function_exists('스왑_pt2np_지급계산') || !function_exists('스왑_총량조회')) {
      return '0';
    }
    $총량 = 스왑_총량조회();
    $np = 스왑_pt2np_지급계산($pt, $총량['total_pt'] ?? 0, $총량['total_np'] ?? 0);
    return 아이템주식_본방금액정규화($np);
  }
}

if (!function_exists('아이템주식_본방결제모드_설정')) {
  /** @param string $nick 두자리닉 */
  function 아이템주식_본방결제모드_설정($nick): void {
    $nick = trim((string)$nick);
    $on = ($nick !== '' && function_exists('게임포기_바로가기숨김인가') && 게임포기_바로가기숨김인가($nick));
    $GLOBALS['_item_stock_bonbang_pay'] = $on;
    if (!$on) {
      unset($GLOBALS['_item_stock_total_override']);
      return;
    }
    if (function_exists('tb_member_게임포기_컬럼_보장')) {
      tb_member_게임포기_컬럼_보장();
    }
    $np = 0.0;
    if (function_exists('시세기준_본방냥')) {
      $np = (float)시세기준_본방냥();
    } else {
      $row = @db_select("SELECT COALESCE(SUM(newpoint), 0) AS s FROM tb_member WHERE status = 0");
      $np = (float)($row['s'] ?? 0);
    }
    // 시세 분모: 소수 유지 (정수 내림 시 소액 percent → 0원). 분모는 최소단위 보정 없이 4자리
    $GLOBALS['_item_stock_total_override'] = number_format(max(0.0, $np), 4, '.', '');
  }
}

if (!function_exists('아이템주식_결제단위라벨')) {
  function 아이템주식_결제단위라벨($기본 = '냥'): string {
    return 아이템주식_본방결제모드인가() ? '본방냥' : (string)$기본;
  }
}

if (!function_exists('아이템주식_본방금액정규화')) {
  /**
   * 본방냥 금액 문자열 (소수 1자리) — 정수 절사로 0이 되지 않게
   * 양수인데 0.1 미만이면 최소 0.1
   */
  function 아이템주식_본방금액정규화($v): string {
    $n = round((float)$v, 1);
    if ($n < 0) {
      $n = 0.0;
    }
    if ($n == 0.0) {
      return '0';
    }
    if ($n > 0 && $n < 0.1) {
      $n = 0.1;
    }
    return number_format($n, 1, '.', '');
  }
}

if (!function_exists('아이템주식_본방표시숫자')) {
  /** 본방냥 UI용 — 1 미만도 소수로 살림 (newpoint표시의 floor 회피) */
  function 아이템주식_본방표시숫자($금액): string {
    $n = round((float)$금액, 1);
    if ($n == 0.0) {
      return '0';
    }
    if (abs($n - round($n)) < 0.05) {
      $i = (int)round($n);
      return function_exists('냥_숫자콤마') ? 냥_숫자콤마((string)$i) : number_format($i);
    }
    return number_format($n, 1, '.', ',');
  }
}

if (!function_exists('아이템주식_본방잔액_조회')) {
  function 아이템주식_본방잔액_조회(int $midx): float {
    if ($midx < 1) {
      return 0.0;
    }
    $row = @db_select("SELECT CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint FROM tb_member WHERE idx = {$midx} LIMIT 1");
    return round((float)($row['newpoint'] ?? 0), 1);
  }
}

if (!function_exists('아이템주식_본방잔액부족인가')) {
  function 아이템주식_본방잔액부족인가(float $보유, $필요액): bool {
    // 아이템주식_냥()은 소수점을 벗겨 0.5→5 등으로 깨짐 — float 비교
    $need = round((float)$필요액, 1);
    return $보유 + 1e-9 < $need;
  }
}

if (!function_exists('아이템주식_본방_sql')) {
  /** newpoint UPDATE용 소수 1자리 리터럴 */
  function 아이템주식_본방_sql($v): string {
    return 아이템주식_본방금액정규화($v);
  }
}

if (!function_exists('아이템주식_본방곱')) {
  /** 본방냥 단가 × 수량 (소수 1자리) */
  function 아이템주식_본방곱($단가, int $수량): string {
    $수량 = max(1, $수량);
    $u = (float)$단가;
    if (function_exists('bcmul')) {
      return 아이템주식_본방금액정규화(bcmul(sprintf('%.4F', $u), (string)$수량, 4));
    }
    return 아이템주식_본방금액정규화($u * $수량);
  }
}

if (!function_exists('아이템주식_본방가산')) {
  function 아이템주식_본방가산($a, $b): string {
    if (function_exists('bcadd')) {
      return 아이템주식_본방금액정규화(bcadd(sprintf('%.4F', (float)$a), sprintf('%.4F', (float)$b), 4));
    }
    return 아이템주식_본방금액정규화((float)$a + (float)$b);
  }
}

if (!function_exists('아이템주식_본방차')) {
  function 아이템주식_본방차($a, $b): string {
    $n = round((float)$a, 1) - round((float)$b, 1);
    if ($n < 0) {
      $n = 0.0;
    }
    return 아이템주식_본방금액정규화($n);
  }
}

if (!function_exists('아이템주식_기념주화목록')) {
  /** @return list<string> */
  function 아이템주식_기념주화목록(): array {
    return ['꼬벙기념주화', '1주년기념주화'];
  }
}

if (!function_exists('아이템주식_기념주화_정규sname')) {
  function 아이템주식_기념주화_정규sname(string $sname): string {
    $n = trim($sname);
    if (
      $n === '꼬병기념주화'
      || $n === '🌶️꼬벙기념주화'
      || $n === '제1회🌶️꼬벙기념주화'
      || $n === '제1회꼬벙기념주화'
    ) {
      return '꼬벙기념주화';
    }
    return $n;
  }
}

if (!function_exists('아이템주식_기념주화인가')) {
  function 아이템주식_기념주화인가(string $sname): bool {
    $n = 아이템주식_기념주화_정규sname($sname);
    return in_array($n, 아이템주식_기념주화목록(), true);
  }
}

if (!function_exists('아이템주식_본방시총가인가')) {
  function 아이템주식_본방시총가인가(string $sname): bool {
    if (function_exists('아이템_본방시총가_대상인가')) {
      return 아이템_본방시총가_대상인가($sname);
    }
    return 아이템주식_기념주화인가($sname) || (bool)preg_match('/^제\d+회부루마블1등$/u', trim($sname));
  }
}

if (!function_exists('아이템주식_본방시총가_정규sname')) {
  function 아이템주식_본방시총가_정규sname(string $sname): string {
    $n = 아이템주식_기념주화_정규sname($sname);
    return $n;
  }
}

if (!function_exists('아이템주식_본방시총가_단가')) {
  function 아이템주식_본방시총가_단가(string $sname = ''): string {
    if (function_exists('아이템_본방시총가_단가')) {
      return 아이템_본방시총가_단가($sname);
    }
    return '0';
  }
}

if (!function_exists('아이템주식_본방시총가_표시')) {
  function 아이템주식_본방시총가_표시($금액): string {
    $n = 아이템주식_냥($금액);
    if (function_exists('newpoint표시')) {
      return newpoint표시($n) . '본방냥';
    }
    if (function_exists('아이템판매_금액표시')) {
      return 아이템판매_금액표시($n, 'newpoint') . '본방냥';
    }
    return $n . '본방냥';
  }
}

if (!function_exists('아이템주식_본방시총가_시세행')) {
  /** @return array<string,mixed> */
  function 아이템주식_본방시총가_시세행(string $sname): array {
    $sname = 아이템주식_본방시총가_정규sname($sname);
    $price = 아이템주식_본방시총가_단가($sname);
    $disp = (function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($sname) : $sname);
    $fmt = 아이템주식_본방시총가_표시($price);
    return [
      'name' => $sname,
      'display_name' => $disp,
      'percent' => 10.0,
      'percent_label' => '시총10%',
      'pricing' => 'coin',
      'pay_unit' => 'newpoint',
      'buy' => $price,
      'sell' => $price,
      'buy_fmt' => $fmt,
      'sell_fmt' => $fmt,
      'buyable' => false,
      'circulating' => function_exists('아이템주식_유통량') ? 아이템주식_유통량($sname) : 0,
      'note' => '보유자 판매만 · 본방냥 시총 10%',
    ];
  }
}

if (!function_exists('아이템주식_기념주화_행보장')) {
  /**
   * type=newpoint · status=1 · 가격은 실시간 본방냥 시총 10%(tb_item.sell 미사용)
   * @return array|null tb_item 행
   */
  function 아이템주식_기념주화_행보장(string $sname): ?array {
    $sname = 아이템주식_기념주화_정규sname($sname);
    if (!아이템주식_기념주화인가($sname)) {
      return null;
    }
    if ($sname === '꼬벙기념주화' && is_file(__DIR__ . '/item_kkobung_coin.inc.php')) {
      require_once __DIR__ . '/item_kkobung_coin.inc.php';
      if (function_exists('꼬벙기념주화_스키마보장')) {
        꼬벙기념주화_스키마보장();
      }
    }
    $esc = addslashes($sname);
    $row = @db_select("SELECT * FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
    if (empty($row['idx'])) {
      $sell = (int)아이템주식_기념주화_기본매도가;
      @db_query("
        INSERT INTO tb_item
        SET itemname = '{$esc}', sname = '{$esc}',
            buy = 0, sell = {$sell}, percent = 0,
            buystatus = 1, status = 1, randum = 0, sort = 920
      ");
      $typeCol = @db_select("SHOW COLUMNS FROM tb_item LIKE 'type'");
      if (!empty($typeCol['Field'])) {
        @db_query("UPDATE tb_item SET type = 'newpoint' WHERE sname = '{$esc}' LIMIT 1");
      }
      $row = @db_select("SELECT * FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
    }
    if (empty($row['idx'])) {
      return null;
    }
    $need = [];
    if ((int)($row['status'] ?? 0) === 0) {
      $need[] = 'status = 1';
    }
    $col = @db_select("SHOW COLUMNS FROM tb_item LIKE 'type'");
    if (!empty($col['Field'])) {
      $ty = strtolower(trim((string)($row['type'] ?? '')));
      if ($ty !== 'newpoint') {
        $need[] = "type = 'newpoint'";
      }
    }
    if ($need !== []) {
      @db_query('UPDATE tb_item SET ' . implode(', ', $need) . " WHERE idx = " . (int)$row['idx'] . ' LIMIT 1');
      $row = @db_select("SELECT * FROM tb_item WHERE idx = " . (int)$row['idx'] . ' LIMIT 1') ?: $row;
    }
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column($sname);
    }
    return $row;
  }
}

if (!function_exists('아이템주식_기념주화_매도가')) {
  /** 1개 매도 실수령(본방냥) — 시총 10% 앞자리 반올림, 수수료 없음 */
  function 아이템주식_기념주화_매도가(array $정보, string $sname, int $수량 = 1): array {
    $sname = 아이템주식_본방시총가_정규sname($sname);
    $수량 = max(1, $수량);
    $midx = (int)($정보['idx'] ?? 0);
    if ($midx < 1 || $sname === '') {
      return ['ok' => false, 'msg' => '❌ 상품 정보를 확인할 수 없어요.'];
    }
    if (아이템주식_기념주화인가($sname)) {
      아이템주식_기념주화_행보장($sname);
    }
    $보유 = function_exists('item_bag_qty') ? item_bag_qty($midx, $sname) : 0;
    if ($보유 < $수량) {
      return ['ok' => false, 'msg' => "{$sname} 보유수량 {$보유}개"];
    }
    $단가 = 아이템주식_본방시총가_단가($sname);
    if ($단가 === '0') {
      return ['ok' => false, 'msg' => '❌ 본방냥 시총을 읽지 못했어요.'];
    }
    $실수령 = function_exists('bcmul') ? bcmul($단가, (string)$수량, 0) : 아이템주식_곱($단가, (string)$수량);
    return [
      'ok' => true,
      '아이템명' => $sname,
      '수량' => $수량,
      '판매금액' => $실수령,
      '수수료' => '0',
      '수수료율' => 0,
      '실수령' => $실수령,
      '지급컬럼' => 'newpoint',
      '실수령_fmt' => 아이템주식_본방시총가_표시($실수령),
      '수수료_fmt' => 아이템주식_본방시총가_표시('0'),
    ];
  }
}

if (!function_exists('아이템주식_공커대실권_정규sname')) {
  /** 공커구매권 → 공커대실권 */
  function 아이템주식_공커대실권_정규sname(string $sname): string {
    $n = trim($sname);
    if ($n === '공커구매권' || $n === '공커대실권') {
      return '공커대실권';
    }
    return $n;
  }
}

if (!function_exists('아이템주식_공커대실권인가')) {
  function 아이템주식_공커대실권인가(string $sname): bool {
    return 아이템주식_공커대실권_정규sname($sname) === '공커대실권';
  }
}

if (!function_exists('아이템주식_회원정보_닉')) {
  /** @return array|null tb_member 행 */
  function 아이템주식_회원정보_닉(string $닉): ?array {
    $닉 = function_exists('getTwoCharNick') ? getTwoCharNick(trim($닉)) : trim($닉);
    if ($닉 === '') {
      return null;
    }
    $esc = addslashes($닉);
    $row = @db_select("SELECT * FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (empty($row['idx'])) {
      return null;
    }
    return $row;
  }
}

if (!function_exists('아이템주식_관리자여부')) {
  function 아이템주식_관리자여부(string $닉): bool {
    global $관리자, $관리자1;
    $닉 = function_exists('getTwoCharNick') ? getTwoCharNick(trim($닉)) : trim($닉);
    if ($닉 === '') {
      return false;
    }
    if (isset($관리자) && in_array($닉, (array)$관리자, true)) {
      return true;
    }
    if (isset($관리자1) && in_array($닉, (array)$관리자1, true)) {
      return true;
    }
    return false;
  }
}

if (!function_exists('아이템주식_공커대실권_활성수')) {
  /** 활성 공커(tb_couple status=0) 커플 수 — 대실권 가격 배수에 사용 */
  function 아이템주식_공커대실권_활성수(): int {
    static $cache = null;
    if ($cache !== null) {
      return $cache;
    }
    $row = @db_select('SELECT COUNT(*) AS c FROM tb_couple WHERE status = 0');
    $cache = max(0, (int)($row['c'] ?? 0));
    return $cache;
  }
}

if (!function_exists('아이템주식_공커대실권_천원올림')) {
  /** 천 냥 단위 올림 (거액 문자열 안전) */
  function 아이템주식_공커대실권_천원올림($금액): string {
    $a = 아이템주식_냥($금액);
    if ($a === '0') {
      return '0';
    }
    if (function_exists('bcmod') && function_exists('bcadd') && function_exists('bcsub')) {
      $mod = bcmod($a, '1000');
      if ($mod === '0') {
        return $a;
      }
      return bcadd(bcsub($a, $mod, 0), '1000', 0);
    }
    if (strlen($a) <= 15) {
      return (string)(int)(ceil(((float)$a) / 1000.0) * 1000);
    }
    // bcmath 없고 거액: 하위 3자리 버림 후 +1000 (대략 올림)
    $head = substr($a, 0, -3);
    $tail = substr($a, -3);
    if ($tail === '000') {
      return $a;
    }
    $head = ltrim($head, '0');
    if ($head === '') {
      return '1000';
    }
    if (function_exists('bcadd')) {
      return bcadd($head . '000', '1000', 0);
    }
    return (string)(((int)$head) + 1) . '000';
  }
}

if (!function_exists('아이템주식_공커대실권_배수')) {
  /**
   * 활성 공커 수 본딩 배수 — 항상 ON
   * 배수 = max(1, 활성쌍) ÷ 기준쌍  · 기본 기준 1 → 1쌍=1×(개별 기준가), 쌍↓면 배수↓
   */
  function 아이템주식_공커대실권_배수(?int $coupleCount = null): string {
    $ref = max(1, (int)아이템주식_공커대실권_기준쌍);
    $cnt = $coupleCount !== null ? max(0, $coupleCount) : 아이템주식_공커대실권_활성수();
    $cnt = max(1, $cnt); // 0쌍 표시 시에도 기준가 1×
    if (function_exists('bcdiv')) {
      return bcdiv((string)$cnt, (string)$ref, 8);
    }
    return sprintf('%.8F', $cnt / $ref);
  }
}

if (!function_exists('아이템주식_공커대실권_단가')) {
  /**
   * 개별 기준가(tb_couple.amount 천원올림) × 공커수 배수 → 천원올림
   * @param array|int|string $커플행_or_amount
   */
  function 아이템주식_공커대실권_단가($커플행_or_amount): string {
    if (is_array($커플행_or_amount)) {
      $raw = 아이템주식_냥($커플행_or_amount['amount'] ?? 0);
    } else {
      $raw = 아이템주식_냥($커플행_or_amount);
    }
    if ($raw === '0') {
      return '0';
    }
    $base = 아이템주식_공커대실권_천원올림($raw);
    $mult = 아이템주식_공커대실권_배수();
    if ($mult === '1' || $mult === '1.0' || $mult === '1.00000000') {
      return $base;
    }
    $priced = function_exists('아이템주식_소수배곱')
      ? 아이템주식_소수배곱($base, $mult)
      : $base;
    return 아이템주식_공커대실권_천원올림($priced);
  }
}

if (!function_exists('아이템주식_공커대실권_견적')) {
  /**
   * 전원에게 거래소 견적 표시 (비공커도 목록에 보임 · 구매는 활성 공커만)
   * · 가격 = 커플 개별 기준가(amount) × (활성쌍 ÷ 기준1)
   * · 구매 가능: tb_member.oneroom=2 + 활성 커플행
   * · tb_item 없거나 buystatus≠0 → null
   * @return array|null
   */
  function 아이템주식_공커대실권_견적(array $정보, $단위 = '냥'): ?array {
    // 게임포기 본방결제 모드여도 공커대실권 표시·결제는 항상 게임냥
    $item = @db_select("
      SELECT idx, sname, buy, percent, buystatus
      FROM tb_item
      WHERE sname = '공커대실권'
      LIMIT 1
    ");
    if (empty($item['idx']) || (int)($item['buystatus'] ?? 1) !== 0) {
      return null;
    }

    $커플수 = 아이템주식_공커대실권_활성수();
    $mult = 아이템주식_공커대실권_배수($커플수);
    $multFmt = rtrim(rtrim(sprintf('%.4F', (float)$mult), '0'), '.') . '×';

    $닉 = trim((string)($정보['name'] ?? ''));
    $커플 = null;
    if ($닉 !== '' && function_exists('공커_대실권_구매대상_조회')) {
      $커플 = 공커_대실권_구매대상_조회($닉);
    } elseif ($닉 !== '' && function_exists('공커_활성_조회')) {
      $커플 = 공커_활성_조회($닉);
    }
    $isGongkeo = !empty($커플['idx']);

    // 본방 스왑결제: 게임냥 시세 → 본방냥 표시. 그 외는 게임냥
    $스왑본방 = 아이템주식_공커대실권_스왑본방결제인가();
    $표시단위 = $스왑본방 ? '본방냥' : '게임냥';

    if ($isGongkeo) {
      $buyGame = 아이템주식_공커대실권_단가($커플);
      $buy = $스왑본방 ? 아이템주식_게임냥_스왑본방환산($buyGame) : $buyGame;
      $edate = substr(trim((string)($커플['edate'] ?? '')), 0, 10);
      $couple = trim((string)($커플['couple'] ?? ''));
      $buyFmt = ($buy !== '0')
        ? 아이템주식_주가표시($buy, $표시단위)
        : '가격미설정';
      $note = $스왑본방 ? '구매즉시 +7일 · 스왑가 본방냥' : '구매즉시 +7일';
      if ($edate !== '') {
        $note = "만료 {$edate} · " . $note;
      }
    } else {
      // 비공커: 최저 기본가 × 공커수 배수
      $ref = @db_select("
        SELECT CAST(MIN(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))) AS CHAR) AS m
        FROM tb_couple
        WHERE status = 0 AND CAST(IFNULL(amount, 0) AS DECIMAL(65,0)) > 0
      ");
      $refAmt = 아이템주식_냥($ref['m'] ?? 0);
      $buyGame = ($refAmt !== '0') ? 아이템주식_공커대실권_단가($refAmt) : '0';
      $buy = ($buyGame !== '0' && $스왑본방) ? 아이템주식_게임냥_스왑본방환산($buyGame) : $buyGame;
      $buyFmt = ($buy !== '0')
        ? 아이템주식_주가표시($buy, $표시단위)
        : '공커 전용';
      $edate = '';
      $couple = '';
      $note = $스왑본방 ? '공커만 구매 · 스왑가 본방냥' : '공커만 구매 · 게임냥';
    }

    return [
      'name' => '공커대실권',
      'percent' => 0.0,
      'percent_label' => $스왑본방
        ? '공커수연동 · +7일 · 스왑가본방냥'
        : '공커수연동 · +7일 · 게임냥',
      'pricing' => 'gongkeo',
      'pay_unit' => $스왑본방 ? 'newpoint' : 'point',
      'buy' => $buy,
      'sell' => '0',
      'buy_fmt' => $buyFmt,
      'sell_fmt' => '매도불가',
      // 비공커도 버튼 활성 → 누르면 서버에서 거절 메시지
      'buyable' => true,
      'sellable' => false,
      'is_gongkeo' => $isGongkeo,
      'circulating' => $커플수,
      'mult' => $mult,
      'mult_fmt' => $multFmt,
      'couple_edate' => $edate,
      'couple_name' => $couple,
      'note' => $note,
    ];
  }
}

if (!function_exists('아이템주식_시세목록_회원')) {
  /** 시세목록 + 공커대실권(전원 표시) */
  function 아이템주식_시세목록_회원(array $정보, $단위 = '냥'): array {
    $목록 = 아이템주식_시세목록($단위);
    $gk = 아이템주식_공커대실권_견적($정보, $단위);
    if ($gk !== null) {
      $목록[] = $gk;
      usort($목록, static function ($a, $b) {
        // 가격 0(참고/미설정)은 뒤로
        $ab = (string)($a['buy'] ?? '0');
        $bb = (string)($b['buy'] ?? '0');
        if ($ab === '0' && $bb !== '0') {
          return 1;
        }
        if ($bb === '0' && $ab !== '0') {
          return -1;
        }
        return 아이템주식_비교($bb, $ab);
      });
    }
    return $목록;
  }
}

if (!function_exists('아이템주식_은총시세인가')) {
  /** 은총(겜냥 권면가 시세) — 은총1(본방)은 제외 */
  function 아이템주식_은총시세인가(string $sname): bool {
    $n = trim($sname);
    return ($n === '은총' || $n === '은총2');
  }
}

if (!function_exists('아이템주식_은총_정규sname')) {
  /** 채팅 별칭 은총2 → bag/tb_item sname 은총 */
  function 아이템주식_은총_정규sname(string $sname): string {
    $n = trim($sname);
    return ($n === '은총2') ? '은총' : $n;
  }
}

if (!function_exists('아이템주식_은총매도_오늘생타')) {
  /** 오늘 생타(일반채팅 · 사진 제외) — .궁금 cnt2 / 채굴 생타와 동일 */
  function 아이템주식_은총매도_오늘생타(string $nick): int {
    $nick = trim($nick);
    if ($nick === '') {
      return 0;
    }
    if (function_exists('mining_chat_raw_tasu_for_nick')) {
      return max(0, (int)mining_chat_raw_tasu_for_nick($nick));
    }
    $esc = addslashes($nick);
    $raw_expr = function_exists('생타_SQL_select_expr')
      ? 생타_SQL_select_expr('msg')
      : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,''))='' OR TRIM(msg)='사진을 보냈습니다.' THEN 0 ELSE 1 END), 0)";
    $row = @db_select("
      SELECT {$raw_expr} AS cnt2
      FROM tb_msg
      WHERE nickname = '{$esc}'
        AND tasu != 0
        AND regdate >= CURDATE()
        AND regdate < CURDATE() + INTERVAL 1 DAY
    ");
    return max(0, (int)($row['cnt2'] ?? 0));
  }
}

if (!function_exists('아이템주식_은총매도_일한도')) {
  /**
   * 오늘 생타 기준 은총 매도 가능 개수
   * 500+ → 1 · 1000+ → +2(합3) · 1500+ → +3(합6)
   */
  function 아이템주식_은총매도_일한도(int $생타): int {
    $limit = 0;
    if ($생타 >= 500) {
      $limit += 1;
    }
    if ($생타 >= 1000) {
      $limit += 2;
    }
    if ($생타 >= 1500) {
      $limit += 3;
    }
    return $limit;
  }
}

if (!function_exists('아이템주식_은총매도_오늘판매수')) {
  /** 오늘 거래소에서 매도한 은총 개수 (tb_item_trade_log) */
  function 아이템주식_은총매도_오늘판매수(string $nick): int {
    $nick = trim($nick);
    if ($nick === '') {
      return 0;
    }
    if (!function_exists('item_trade_log_스키마보장') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }
    if (function_exists('item_trade_log_스키마보장')) {
      item_trade_log_스키마보장();
    }
    $esc = addslashes($nick);
    $row = @db_select("
      SELECT COALESCE(SUM(qty), 0) AS sold
      FROM tb_item_trade_log
      WHERE side = 'sell'
        AND nick = '{$esc}'
        AND item = '은총'
        AND channel = 'chat_stock'
        AND regdate >= CURDATE()
        AND regdate < CURDATE() + INTERVAL 1 DAY
    ");
    return max(0, (int)($row['sold'] ?? 0));
  }
}

if (!function_exists('아이템주식_은총매도_한도검사')) {
  /**
   * @return array{ok:bool,msg:string,생타?:int,한도?:int,판매?:int,남음?:int}
   */
  function 아이템주식_은총매도_한도검사(string $nick, int $수량): array {
    $수량 = max(1, (int)$수량);
    $생타 = 아이템주식_은총매도_오늘생타($nick);
    $한도 = 아이템주식_은총매도_일한도($생타);
    $판매 = 아이템주식_은총매도_오늘판매수($nick);
    $남음 = max(0, $한도 - $판매);
    if ($한도 < 1) {
      return [
        'ok' => false,
        'msg' => "❌ 은총 매도는 오늘 생타 500타 이상부터 가능해요.\n"
          . "현재 생타 {$생타}타 · 한도 0개"
          . "\n(500→1개 · 1천→+2 · 1.5천→+3)",
        '생타' => $생타,
        '한도' => $한도,
        '판매' => $판매,
        '남음' => 0,
      ];
    }
    if ($수량 > $남음) {
      return [
        'ok' => false,
        'msg' => "❌ 오늘 은총 매도 한도를 초과했어요.\n"
          . "생타 {$생타}타 · 한도 {$한도}개 · 오늘 판매 {$판매}개 · 남은 {$남음}개"
          . "\n(500→1개 · 1천→+2 · 1.5천→+3)",
        '생타' => $생타,
        '한도' => $한도,
        '판매' => $판매,
        '남음' => $남음,
      ];
    }
    return [
      'ok' => true,
      'msg' => '',
      '생타' => $생타,
      '한도' => $한도,
      '판매' => $판매,
      '남음' => $남음,
    ];
  }
}

if (!function_exists('아이템주식_은총단가_로드')) {
  /**
   * 거래소·주식 매수 전용 은총 단가
   * · 분모 = 아이템주식_총게임냥() (스냅샷/라이브/커스텀 — percent 아이템과 동일)
   * · 단가 = 총게임냥 × 2.5% × (권면가/10000) · 경↓ 적응 절사 (1경 미만이면 조·억)
   * · shop 실시간 SUM을 쓰면 스냅샷 종목과 어긋나 갑자기 싸/비싸질 수 있어 사용하지 않음
   * @return string 게임냥 단가 · 실패 시 '0'
   */
  function 아이템주식_은총단가_로드(): string {
    if (isset($GLOBALS['_item_stock_eun_price']) && is_string($GLOBALS['_item_stock_eun_price'])) {
      return $GLOBALS['_item_stock_eun_price'];
    }
    if (!function_exists('info3_은총_금액_자리맞춤') || !defined('EUNCHONG_BUY_FACE_GAME')) {
      $path = __DIR__ . '/eunchong_shop_buy.inc.php';
      if (is_file($path)) {
        require_once $path;
      }
    }

    $face = defined('EUNCHONG_BUY_FACE_GAME') ? (int)EUNCHONG_BUY_FACE_GAME : 10000;
    if ($face < 1) {
      $face = 10000;
    }
    $tax = (float)아이템주식_은총_요율;
    if ($tax <= 0) {
      $tax = 2.5;
    }

    $총 = 아이템주식_냥(아이템주식_총게임냥());
    if ($총 === '0') {
      $GLOBALS['_item_stock_eun_price'] = '0';
      return '0';
    }

    // 총 × tax% × (face/10000) = 총 × tax × face / 1_000_000
    $원가 = '0';
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      $원가 = bcdiv(
        bcmul(bcmul($총, sprintf('%.12F', $tax), 12), (string)$face, 12),
        '1000000',
        0
      );
    } else {
      // 문자열 경로 — (int)/float 금지 (해 단위가 922경으로 뭉개짐)
      $tax100 = (int)round($tax * 100); // 2.5 → 250
      if ($tax100 < 1) {
        $tax100 = 250;
      }
      // 총 × tax100 × face / 100_000_000  (tax% = tax100/10000, ×face/10000 → /1e8 * tax100? )
      // 총 × (tax/100) × (face/10000) = 총 × tax × face / 1_000_000
      // tax=2.5,face=10000 → 총 × 25000 / 1_000_000 = 총 × 25 / 1000 = 총 × 2.5 / 100
      $분자 = 아이템주식_곱(아이템주식_곱($총, (string)$tax100), (string)$face);
      // tax100은 tax×100 이므로 추가로 /100 → 분모 1e6 * 100 = 1e8
      $원가 = 아이템주식_나누기내림($분자, 100000000);
    }

    $원가 = 아이템주식_냥($원가);
    if ($원가 === '0') {
      $GLOBALS['_item_stock_eun_price'] = '0';
      return '0';
    }
    if (function_exists('info3_은총_금액_자리맞춤_적응')) {
      $원가 = 아이템주식_냥(info3_은총_금액_자리맞춤_적응($원가, '경'));
    } elseif (function_exists('info3_은총_금액_자리맞춤')) {
      $원가 = 아이템주식_냥(info3_은총_금액_자리맞춤($원가, '경'));
    }
    $GLOBALS['_item_stock_eun_price'] = $원가;
    return $원가;
  }
}

if (!function_exists('아이템주식_은총행_보장')) {
  /**
   * tb_item 은총 스텁 보장 후 행 반환
   * @return array|null
   */
  function 아이템주식_은총행_보장() {
    if (!function_exists('bag_enhance_tb_item_보장') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
      require_once __DIR__ . '/item_bag_enhance.inc.php';
    }
    if (function_exists('bag_enhance_tb_item_보장')) {
      bag_enhance_tb_item_보장('은총');
    }
    $row = @db_select("
      SELECT idx, sname, buy, sell, percent, buystatus, status
      FROM tb_item
      WHERE TRIM(sname) = '은총'
      LIMIT 1
    ");
    if (empty($row['idx'])) {
      return null;
    }
    $row['sname'] = '은총';
    return $row;
  }
}

if (!function_exists('아이템주식_스프레드율')) {
  function 아이템주식_스프레드율(): int {
    // DB 설정 우선 (민호 거래소에서 조절)
    if (function_exists('아이템주식_기준설정_로드')) {
      $cfg = 아이템주식_기준설정_로드();
      if (isset($cfg['spread'])) {
        $n = (int)$cfg['spread'];
        if ($n < 0) {
          $n = 0;
        }
        if ($n > 50) {
          $n = 50;
        }
        return $n;
      }
    }
    $n = (int)아이템주식_스프레드_퍼센트;
    if ($n < 0) {
      $n = 0;
    }
    if ($n > 50) {
      $n = 50;
    }
    return $n;
  }
}

if (!function_exists('아이템주식_아이템스프레드율')) {
  /**
   * 아이템별 매도 수수료% (기본 20)
   */
  function 아이템주식_아이템스프레드율(string $sname): int {
    return 아이템주식_스프레드율();
  }
}

if (!function_exists('아이템주식_수수료반액')) {
  /** 수수료 절반(내림) — 금고/로또 배분용 · (int)/float 금지 */
  function 아이템주식_수수료반액($금액): string {
    $n = 아이템주식_냥($금액);
    if ($n === '0') {
      return '0';
    }
    if (function_exists('bcdiv')) {
      return bcdiv($n, '2', 0);
    }
    if (function_exists('냥_금액_문자열몫')) {
      return 냥_금액_문자열몫($n, '2');
    }
    return 아이템주식_나누기내림($n, 2);
  }
}

if (!function_exists('아이템주식_로또수수료_적립')) {
  /** 매도 수수료 중 로또분 → config.로또누적 (INSERT 안 함) */
  function 아이템주식_로또수수료_적립($로또금액, $적립닉 = '주식매도'): void {
    $로또_sql = 아이템주식_냥_sql($로또금액);
    if ($로또_sql === '0') {
      return;
    }
    if (is_file(__DIR__ . '/game/lotto_amount.inc.php')) {
      require_once __DIR__ . '/game/lotto_amount.inc.php';
    }
    if (function_exists('로또누적_가산')) {
      로또누적_가산($로또_sql);
      return;
    }
    $guards = __DIR__ . '/game/odd_even_guards.php';
    if (is_file($guards)) {
      require_once $guards;
    }
    if (function_exists('홀짝_로또수수료_적립')) {
      홀짝_로또수수료_적립($로또금액, $적립닉);
    }
  }
}

if (!function_exists('아이템주식_수수료_금고로또배분')) {
  /**
   * 매도 수수료 총액 → 금고 50% · 로또 50%
   * (기본 수수료 20%면 시세의 10%씩)
   * @return array{금고:string,로또:string}
   */
  function 아이템주식_수수료_금고로또배분($수수료액): array {
    $총 = 아이템주식_냥($수수료액);
    if ($총 === '0') {
      return ['금고' => '0', '로또' => '0'];
    }
    $금고 = 아이템주식_수수료반액($총);
    $로또 = 아이템주식_차($총, $금고);
    if ($금고 !== '0') {
      $금고sql = 아이템주식_냥_sql($금고);
      db_query("UPDATE config SET tax = tax + {$금고sql}");
    }
    if ($로또 !== '0') {
      아이템주식_로또수수료_적립($로또, '주식매도');
    }
    return ['금고' => $금고, '로또' => $로또];
  }
}

if (!function_exists('아이템주식_냥')) {
  function 아이템주식_냥($v): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = trim((string)$v);
    // 과학적 표기(6.65E+21)를 숫자만 추출하면 자릿수가 깨짐 → 별도 처리
    if (preg_match('/^([+-])?(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $s, $m)) {
      $digits = $m[2] . ($m[3] ?? '');
      $exp = (int)$m[4] - strlen((string)($m[3] ?? ''));
      if ($exp >= 0) {
        $digits .= str_repeat('0', $exp);
      } else {
        $cut = strlen($digits) + $exp;
        $digits = $cut > 0 ? substr($digits, 0, $cut) : '0';
      }
      return ltrim($digits, '0') ?: '0';
    }
    return ltrim(preg_replace('/\D/', '', $s), '0') ?: '0';
  }
}

if (!function_exists('아이템주식_잔액_음수인가')) {
  /** CAST(point AS CHAR) 등 원본 문자열 기준 마이너스 잔액 */
  function 아이템주식_잔액_음수인가($pointRaw): bool {
    $s = trim((string)$pointRaw);
    if ($s === '' || $s === '0' || $s === '+0' || $s === '-0') {
      return false;
    }
    if (isset($s[0]) && $s[0] === '-') {
      // -0.0 등은 위에서 처리, 그 외 음수
      $abs = ltrim(substr($s, 1), '0');
      return $abs !== '' && $abs !== '0';
    }
    if (is_numeric($s)) {
      return ((float)$s) < 0;
    }
    return false;
  }
}

if (!function_exists('아이템주식_매수잔액부족인가')) {
  /**
   * 마이너스 보유냥이거나 필요액보다 적으면 true
   * (냥_정수문자열이 부호를 버려 -잔액이 구매되는 문제 방지)
   */
  function 아이템주식_매수잔액부족인가($pointRaw, $필요액): bool {
    if (아이템주식_잔액_음수인가($pointRaw)) {
      return true;
    }
    return 아이템주식_비교(아이템주식_냥($pointRaw), $필요액) < 0;
  }
}

if (!function_exists('아이템주식_잔액표시')) {
  /** 음수 잔액도 - 붙여 표시 */
  function 아이템주식_잔액표시($pointRaw, $단위 = '냥'): string {
    $neg = 아이템주식_잔액_음수인가($pointRaw);
    $fmt = 아이템주식_표시(아이템주식_냥($pointRaw), $단위);
    return $neg ? ('-' . $fmt) : $fmt;
  }
}

if (!function_exists('아이템주식_냥_sql')) {
  function 아이템주식_냥_sql($v): string {
    $s = 아이템주식_냥($v);
    return preg_match('/^\d+$/', $s) ? $s : '0';
  }
}

if (!function_exists('아이템주식_비교')) {
  /** @return int -1|0|1 */
  function 아이템주식_비교($a, $b): int {
    $a = 아이템주식_냥($a);
    $b = 아이템주식_냥($b);
    if (function_exists('bccomp')) {
      return (int)bccomp($a, $b, 0);
    }
    $la = strlen($a);
    $lb = strlen($b);
    if ($la !== $lb) {
      return $la < $lb ? -1 : 1;
    }
    if ($a === $b) {
      return 0;
    }
    return $a < $b ? -1 : 1;
  }
}

if (!function_exists('아이템주식_곱')) {
  function 아이템주식_곱($a, $b): string {
    $a = 아이템주식_냥($a);
    $b = 아이템주식_냥($b);
    if ($a === '0' || $b === '0') {
      return '0';
    }
    if (function_exists('bcmul')) {
      return bcmul($a, $b, 0);
    }
    if (function_exists('냥_금액_문자열곱')) {
      return 냥_금액_문자열곱($a, $b);
    }
    // (int) 곱셈 금지 — 1해·수십해는 PHP_INT_MAX로 잘림
    $n = (int)$b;
    if ($n < 0) {
      $n = 0;
    }
    if ($n === 0) {
      return '0';
    }
    if ($n === 1) {
      return $a;
    }
    $a = strrev($a);
    $carry = 0;
    $out = '';
    for ($i = 0, $len = strlen($a); $i < $len; $i++) {
      $prod = (ord($a[$i]) - 48) * $n + $carry;
      $out .= chr(($prod % 10) + 48);
      $carry = intdiv($prod, 10);
    }
    while ($carry > 0) {
      $out .= chr(($carry % 10) + 48);
      $carry = intdiv($carry, 10);
    }
    return ltrim(strrev($out), '0') ?: '0';
  }
}

if (!function_exists('아이템주식_차')) {
  function 아이템주식_차($a, $b): string {
    $a = 아이템주식_냥($a);
    $b = 아이템주식_냥($b);
    if (function_exists('bcsub') && function_exists('bccomp')) {
      return (bccomp($a, $b, 0) < 0) ? '0' : bcsub($a, $b, 0);
    }
    if (function_exists('냥_금액_문자열차감')) {
      return 냥_금액_문자열차감($a, $b);
    }
    // 문자열 차감 폴백 — (int) 금지
    if (아이템주식_비교($a, $b) < 0) {
      return '0';
    }
    if ($b === '0') {
      return $a;
    }
    $a = strrev($a);
    $b = strrev($b);
    $len = strlen($a);
    $borrow = 0;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
      $da = (ord($a[$i]) - 48) - $borrow;
      $db = ($i < strlen($b)) ? (ord($b[$i]) - 48) : 0;
      if ($da < $db) {
        $da += 10;
        $borrow = 1;
      } else {
        $borrow = 0;
      }
      $out .= chr(($da - $db) + 48);
    }
    return ltrim(strrev($out), '0') ?: '0';
  }
}

if (!function_exists('아이템주식_표시')) {
  /** 매수/매도 금액 표시 — 억·만 미만도 0으로 깎지 않음 */
  function 아이템주식_표시($금액, $단위 = '냥'): string {
    $단위 = (string)$단위;
    if ($단위 === '본방냥') {
      return 아이템주식_본방표시숫자($금액) . $단위;
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, $단위);
    }
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($금액, $단위);
    }
    if (function_exists('구매가_축약표시')) {
      return 구매가_축약표시($금액, $단위);
    }
    if (function_exists('냥축약표시')) {
      return 냥축약표시($금액, $단위);
    }
    return 아이템주식_냥($금액) . $단위;
  }
}

if (!function_exists('아이템주식_주가표시')) {
  /** .주가·거래소 목록용 — 조 미만도 억/만/원 단위로 표시 (삭감 후 0냥 방지) */
  function 아이템주식_주가표시($금액, $단위 = '냥'): string {
    $단위 = (string)$단위;
    if ($단위 === '본방냥') {
      return 아이템주식_본방표시숫자($금액) . $단위;
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, $단위);
    }
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($금액, $단위);
    }
    return 아이템주식_표시($금액, $단위);
  }
}

if (!function_exists('아이템주식_기준설정_경로')) {
  /** 민호 테스트용 시세 기준 모드 (스냅샷/실시간/커스텀) */
  function 아이템주식_기준설정_경로(): string {
    return __DIR__ . '/item_stock_base.json';
  }
}

if (!function_exists('아이템주식_기준설정_기본')) {
  function 아이템주식_기준설정_기본(): array {
    return [
      'mode' => 'snapshot', // snapshot | live | custom
      'custom' => '0',
      /** 유통량 본딩: 매수↑ 매도↓ — 배수 = 1 + 유통량/supply_ref */
      'supply_curve' => false,
      'supply_ref' => 100,
      /** 매도가 = 매수 × (100−spread)% · 차액 절반 금고·절반 로또 */
      'spread' => (int)아이템주식_스프레드_퍼센트,
      /** 전체 시세 배수 (1=그대로, 2=두 배) */
      'price_mult' => 1.0,
      'updated_at' => '',
      'updated_by' => '',
    ];
  }
}

if (!function_exists('아이템주식_설정테이블_보장')) {
  /** 설정 저장소 (파일 권한 없는 서버 대비 DB 우선) */
  function 아이템주식_설정테이블_보장(): bool {
    static $done = null;
    if ($done !== null) {
      return $done;
    }
    $rs = @db_query("
      CREATE TABLE IF NOT EXISTS `tb_item_stock_cfg` (
        `k` VARCHAR(40) NOT NULL,
        `v` TEXT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`k`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $done = ($rs !== false);
    return $done;
  }
}

if (!function_exists('아이템주식_기준설정_정규화')) {
  function 아이템주식_기준설정_정규화($j): array {
    $def = 아이템주식_기준설정_기본();
    if (!is_array($j)) {
      return $def;
    }
    $mode = trim((string)($j['mode'] ?? 'snapshot'));
    if (!in_array($mode, ['snapshot', 'live', 'custom'], true)) {
      $mode = 'snapshot';
    }
    $ref = (int)($j['supply_ref'] ?? $def['supply_ref']);
    if ($ref < 1) {
      $ref = 1;
    }
    if ($ref > 1000000) {
      $ref = 1000000;
    }
    $spread = isset($j['spread']) ? (int)$j['spread'] : (int)$def['spread'];
    if ($spread < 0) {
      $spread = 0;
    }
    if ($spread > 50) {
      $spread = 50;
    }
    $pm = isset($j['price_mult']) ? (float)$j['price_mult'] : (float)$def['price_mult'];
    if ($pm < 0.01) {
      $pm = 0.01;
    }
    if ($pm > 100) {
      $pm = 100;
    }
    // 소수 4자리로 정리
    $pm = round($pm, 4);
    return [
      'mode' => $mode,
      'custom' => 아이템주식_냥($j['custom'] ?? '0'),
      'supply_curve' => !empty($j['supply_curve']),
      'supply_ref' => $ref,
      'spread' => $spread,
      'price_mult' => $pm,
      'updated_at' => (string)($j['updated_at'] ?? ''),
      'updated_by' => (string)($j['updated_by'] ?? ''),
    ];
  }
}

if (!function_exists('아이템주식_기준설정_캐시초기화')) {
  function 아이템주식_기준설정_캐시초기화(): void {
    global $아이템주식_기준설정_캐시;
    $아이템주식_기준설정_캐시 = null;
  }
}

if (!function_exists('아이템주식_기준설정_로드')) {
  function 아이템주식_기준설정_로드(): array {
    global $아이템주식_기준설정_캐시;
    if (is_array($아이템주식_기준설정_캐시)) {
      return $아이템주식_기준설정_캐시;
    }

    $raw = '';
    if (아이템주식_설정테이블_보장()) {
      $row = @db_select("SELECT v FROM `tb_item_stock_cfg` WHERE k = 'base' LIMIT 1");
      $raw = (string)($row['v'] ?? '');
    }
    if ($raw === '') {
      $path = 아이템주식_기준설정_경로();
      if (is_file($path)) {
        $raw = (string)@file_get_contents($path);
      }
    }

    $아이템주식_기준설정_캐시 = ($raw === '')
      ? 아이템주식_기준설정_기본()
      : 아이템주식_기준설정_정규화(json_decode($raw, true));

    // 1회: 매도 수수료 20% (금고10%+로또10%)로 전환
    if (function_exists('아이템주식_스프레드_수수료20_적용')) {
      $아이템주식_기준설정_캐시 = 아이템주식_스프레드_수수료20_적용($아이템주식_기준설정_캐시);
    }
    return $아이템주식_기준설정_캐시;
  }
}

if (!function_exists('아이템주식_스프레드_수수료20_적용')) {
  /** 저장된 스프레드를 20으로 맞추고 플래그 기록 (요청당 1회) */
  function 아이템주식_스프레드_수수료20_적용(array $cfg): array {
    static $checked = false;
    if ($checked) {
      return $cfg;
    }
    $checked = true;

    $flagDone = false;
    if (아이템주식_설정테이블_보장()) {
      $row = @db_select("SELECT v FROM `tb_item_stock_cfg` WHERE k = 'migrate_sell_fee20_split_v1' LIMIT 1");
      $flagDone = trim((string)($row['v'] ?? '')) !== '';
    }
    if ($flagDone) {
      return $cfg;
    }

    $cfg['spread'] = 20;
    $cfg['updated_at'] = date('Y-m-d H:i:s');
    $cfg['updated_by'] = trim((string)($cfg['updated_by'] ?? '')) ?: 'system';
    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
    if ($json !== false && 아이템주식_설정테이블_보장()) {
      $esc = addslashes($json);
      @db_query("
        INSERT INTO `tb_item_stock_cfg` (`k`, `v`) VALUES ('base', '{$esc}')
        ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)
      ");
      @db_query("
        INSERT INTO `tb_item_stock_cfg` (`k`, `v`) VALUES ('migrate_sell_fee20_split_v1', '1')
        ON DUPLICATE KEY UPDATE `v` = '1'
      ");
    }
    $path = 아이템주식_기준설정_경로();
    if (is_file($path) || is_writable(dirname($path))) {
      @file_put_contents($path, $json !== false ? $json : '{"spread":20}');
    }
    return $cfg;
  }
}

if (!function_exists('아이템주식_기준설정_저장')) {
  /**
   * @param 'snapshot'|'live'|'custom' $mode
   * @param array{supply_curve?:bool,supply_ref?:int,spread?:int,price_mult?:float}|null $extra
   */
  function 아이템주식_기준설정_저장(string $mode, $custom = '0', string $by = '', $extra = null): array {
    $prev = 아이템주식_기준설정_로드();
    if (!in_array($mode, ['snapshot', 'live', 'custom'], true)) {
      $mode = 'snapshot';
    }
    $supply_curve = $prev['supply_curve'];
    $supply_ref = (int)$prev['supply_ref'];
    $spread = (int)($prev['spread'] ?? 아이템주식_스프레드_퍼센트);
    $price_mult = (float)($prev['price_mult'] ?? 1);
    if (is_array($extra)) {
      if (array_key_exists('supply_curve', $extra)) {
        $supply_curve = !empty($extra['supply_curve']);
      }
      if (array_key_exists('supply_ref', $extra)) {
        $supply_ref = (int)$extra['supply_ref'];
        if ($supply_ref < 1) {
          $supply_ref = 1;
        }
        if ($supply_ref > 1000000) {
          $supply_ref = 1000000;
        }
      }
      if (array_key_exists('spread', $extra)) {
        $spread = (int)$extra['spread'];
        if ($spread < 0) {
          $spread = 0;
        }
        if ($spread > 50) {
          $spread = 50;
        }
      }
      if (array_key_exists('price_mult', $extra)) {
        $price_mult = (float)$extra['price_mult'];
        if ($price_mult < 0.01) {
          $price_mult = 0.01;
        }
        if ($price_mult > 100) {
          $price_mult = 100;
        }
        $price_mult = round($price_mult, 4);
      }
    }
    $data = [
      'mode' => $mode,
      'custom' => 아이템주식_냥($custom),
      'supply_curve' => $supply_curve,
      'supply_ref' => $supply_ref,
      'spread' => $spread,
      'price_mult' => $price_mult,
      'updated_at' => date('Y-m-d H:i:s'),
      'updated_by' => $by,
    ];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $saved = false;
    if (아이템주식_설정테이블_보장()) {
      $esc = addslashes($json);
      $saved = (@db_query("REPLACE INTO `tb_item_stock_cfg` (`k`, `v`) VALUES ('base', '{$esc}')") !== false);
    }
    if (!$saved) {
      $path = 아이템주식_기준설정_경로();
      $saved = (@file_put_contents($path, $json) !== false);
    }
    if (!$saved) {
      return ['ok' => false, 'msg' => '기준 설정 저장 실패 (DB·파일 모두 쓰기 불가)', 'cfg' => $data];
    }
    아이템주식_기준설정_캐시초기화();
    $GLOBALS['_item_stock_eun_price'] = null; // 은총가 캐시 무효 (분모 변경 반영)
    $msg = '시세 기준이 변경되었습니다.';
    $msg .= " 전체배수 {$price_mult}×";
    $half = (int)floor($spread / 2);
    $msg .= " · 매도=" . (100 - $spread) . "% (수수료 {$spread}% → 금고{$half}%+로또" . ($spread - $half) . '%)';
    if ($supply_curve) {
      $msg .= " · 유통량곡선 ON (기준 {$supply_ref}개=2배)";
    }
    return ['ok' => true, 'msg' => $msg, 'cfg' => $data];
  }
}

if (!function_exists('아이템주식_스냅샷총냥')) {
  function 아이템주식_스냅샷총냥(): string {
    if (function_exists('아이템_총게임냥')) {
      return 아이템주식_냥(아이템_총게임냥());
    }
    if (function_exists('시세기준_게임냥_문자열')) {
      return 아이템주식_냥(시세기준_게임냥_문자열());
    }
    return '0';
  }
}

if (!function_exists('아이템주식_실시간총냥')) {
  /** 회원 point 실시간 SUM (요청마다 재조회 — 매수 직후 변동 확인용) */
  function 아이템주식_실시간총냥(): string {
    $row = @db_select("
      SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
      FROM tb_member
    ");
    $raw = (string)($row['total_pt'] ?? 'N0');
    if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
      $raw = substr($raw, 1);
    }
    $sum = 아이템주식_냥($raw);
    if ($sum !== '0') {
      return $sum;
    }
    // SUM 실패 시 폴백
    if (function_exists('계급_총게임냥')) {
      return 아이템주식_냥(계급_총게임냥());
    }
    return '0';
  }
}

if (!function_exists('아이템주식_총게임냥')) {
  /**
   * 주식 시세 분모.
   * · snapshot(기본): 기존 시세 스냅샷/아이템_총게임냥
   * · live: 실시간 SUM(point)
   * · custom: 민호 테스트용 임의 총냥
   */
  function 아이템주식_총게임냥() {
    // 게임포기 회원 거래소: 본방냥 총합을 시세 분모로
    if (!empty($GLOBALS['_item_stock_total_override'])) {
      return 아이템주식_냥($GLOBALS['_item_stock_total_override']);
    }
    $cfg = 아이템주식_기준설정_로드();
    $mode = $cfg['mode'] ?? 'snapshot';
    if ($mode === 'custom') {
      $c = 아이템주식_냥($cfg['custom'] ?? '0');
      return ($c === '0') ? '0' : $c;
    }
    if ($mode === 'live') {
      return 아이템주식_실시간총냥();
    }
    return 아이템주식_스냅샷총냥();
  }
}

if (!function_exists('아이템주식_기준_메타')) {
  /** UI/API용 기준 비교값 */
  function 아이템주식_기준_메타($단위 = '냥'): array {
    $cfg = 아이템주식_기준설정_로드();
    $snap = 아이템주식_스냅샷총냥();
    $live = 아이템주식_실시간총냥();
    $active = 아이템주식_냥(아이템주식_총게임냥());
    $labels = [
      'snapshot' => '스냅샷(기존)',
      'live' => '실시간 SUM',
      'custom' => '커스텀(테스트)',
    ];
    $mode = $cfg['mode'] ?? 'snapshot';
    $ref = (int)($cfg['supply_ref'] ?? 100);
    return [
      'mode' => $mode,
      'mode_label' => $labels[$mode] ?? $mode,
      'custom' => 아이템주식_냥($cfg['custom'] ?? '0'),
      'custom_fmt' => 아이템주식_표시($cfg['custom'] ?? '0', $단위),
      'snapshot' => $snap,
      'snapshot_fmt' => 아이템주식_표시($snap, $단위),
      'live' => $live,
      'live_fmt' => 아이템주식_표시($live, $단위),
      'active' => $active,
      'active_fmt' => 아이템주식_표시($active, $단위),
      'supply_curve' => !empty($cfg['supply_curve']),
      'supply_ref' => $ref,
      'supply_label' => !empty($cfg['supply_curve'])
        ? ("유통량곡선 ON · 기준 {$ref}개=2배")
        : '유통량곡선 OFF',
      'spread' => 아이템주식_스프레드율(),
      'price_mult' => (float)($cfg['price_mult'] ?? 1),
      'updated_at' => (string)($cfg['updated_at'] ?? ''),
      'updated_by' => (string)($cfg['updated_by'] ?? ''),
    ];
  }
}

if (!function_exists('아이템주식_유통량')) {
  /** 전 회원 가방 합계 (해당 sname) */
  function 아이템주식_유통량(string $sname): int {
    $sname = trim($sname);
    if ($sname === '') {
      return 0;
    }
    $coinInc = __DIR__ . '/game/burumable_coin.inc.php';
    if (!function_exists('부루마블주화인가') && is_file($coinInc)) {
      require_once $coinInc;
    }
    if (function_exists('부루마블주화인가') && 부루마블주화인가($sname)) {
      return function_exists('부루마블주화_유통량') ? max(0, 부루마블주화_유통량($sname)) : 0;
    }
    if (!function_exists('item_bag_ensure_schema') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (function_exists('item_bag_ensure_schema')) {
      item_bag_ensure_schema();
    }
    if (function_exists('item_bag_col_exists') && !item_bag_col_exists($sname)) {
      return 0;
    }
    $col = str_replace('`', '``', $sname);
    $row = @db_select("SELECT CAST(COALESCE(SUM(`{$col}`), 0) AS CHAR) AS c FROM tb_member_item_bag");
    return max(0, (int)($row['c'] ?? 0));
  }
}

if (!function_exists('아이템주식_공급배수')) {
  /**
   * 유통량 본딩 배수 문자열 (예: "1.500000")
   * OFF 또는 circ=0 → "1"
   */
  function 아이템주식_공급배수(int $circulating, $cfg = null): string {
    if ($cfg === null) {
      $cfg = 아이템주식_기준설정_로드();
    }
    if (empty($cfg['supply_curve'])) {
      return '1';
    }
    $ref = max(1, (int)($cfg['supply_ref'] ?? 100));
    $circ = max(0, (int)$circulating);
    if (function_exists('bcadd') && function_exists('bcdiv')) {
      return bcadd('1', bcdiv((string)$circ, (string)$ref, 8), 8);
    }
    return sprintf('%.8F', 1.0 + ($circ / $ref));
  }
}

if (!function_exists('아이템주식_기본원가')) {
  /** 총게임냥 × percent (억절사 전) — 0.000001% 등 극소 percent 허용 */
  function 아이템주식_기본원가(array $itemRow, $총게임냥 = null): string {
    $percent = (float)($itemRow['percent'] ?? 0);
    if ($percent <= 0) {
      return '0';
    }
    if ($총게임냥 === null) {
      $총게임냥 = 아이템주식_총게임냥();
    }
    $bonbang = 아이템주식_본방결제모드인가();
    // 본방결제: 소수 1자리 유지 (정수 절사 시 선물 등 소액 percent → 0본방냥)
    if ($bonbang) {
      $총f = (float)$총게임냥;
      if ($총f <= 0) {
        return '0';
      }
      if (function_exists('bcmul') && function_exists('bcdiv')) {
        $raw = bcdiv(bcmul(sprintf('%.6F', $총f), sprintf('%.18F', $percent), 18), '100', 4);
        return 아이템주식_본방금액정규화($raw);
      }
      return 아이템주식_본방금액정규화($총f * $percent / 100.0);
    }

    $총 = 아이템주식_냥($총게임냥);
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return bcdiv(bcmul($총, sprintf('%.18F', $percent), 18), '100', 0);
    }
    // float/(int) 금지 — percent×1e12 정수 경로 (소수 12자리까지)
    $pctScaled = (int)round($percent * 1000000000000);
    if ($pctScaled < 1) {
      return '0';
    }
    // 총 × pctScaled / 1e14  (= 총 × percent / 100)
    return 아이템주식_나누기내림(아이템주식_곱($총, (string)$pctScaled), 100000000000000);
  }
}

if (!function_exists('아이템주식_전체배수')) {
  /** 전체 시세 배수 문자열 (예: "2.0000") */
  function 아이템주식_전체배수($cfg = null): string {
    if ($cfg === null) {
      $cfg = 아이템주식_기준설정_로드();
    }
    $pm = (float)($cfg['price_mult'] ?? 1);
    if ($pm < 0.01) {
      $pm = 0.01;
    }
    if ($pm > 100) {
      $pm = 100;
    }
    return sprintf('%.4F', $pm);
  }
}

if (!function_exists('아이템주식_소수배곱')) {
  /**
   * 거액 × 소수 배수 (예: 1.25) — (int)/float 금지 (PHP_INT_MAX≈922경 잘림 방지)
   */
  function 아이템주식_소수배곱($금액, $배수): string {
    $a = 아이템주식_냥($금액);
    $m = trim((string)$배수);
    if ($a === '0' || $m === '' || $m === '0') {
      return '0';
    }
    if ($m === '1' || $m === '1.0' || $m === '1.00' || $m === '1.000' || $m === '1.0000' || $m === '1.00000000') {
      return $a;
    }
    if (function_exists('bcmul')) {
      return 아이템주식_냥(bcmul($a, $m, 0));
    }
    // bcmath 없음: 정수 배율만 허용 (소수면 버림 배수)
    $intMult = (int)floor((float)$m);
    if ($intMult < 1) {
      return $a;
    }
    return 아이템주식_곱($a, (string)$intMult);
  }
}

if (!function_exists('아이템주식_매수단가')) {
  /**
   * percent 시세 매수가 (억 절사) — 문자열
   * 유통량곡선 ON 시: 원가 × (1 + 유통량/기준)
   * 전체배수: 원가 × price_mult
   * @param int|null $circulating null이면 현재 유통량 조회
   * @return string '0' 이면 거래 불가(원가 0)
   */
  function 아이템주식_매수단가(array $itemRow, $총게임냥 = null, $circulating = null): string {
    $name = 아이템주식_은총_정규sname(trim((string)($itemRow['sname'] ?? '')));
    $cfg = 아이템주식_기준설정_로드();
    $pm = 아이템주식_전체배수($cfg);
    $curve = !empty($cfg['supply_curve']);
    if ($circulating === null && $curve && $name !== '') {
      $circulating = 아이템주식_유통량($name);
    }
    $circulating = max(0, (int)($circulating ?? 0));

    // 은총: 권면가×총냥×2.5%(경↓적응절사) → 유통곡선 → 전체배수(price_mult)
    // 본방결제(게임포기): 경 절사 생략 — 본방 스케일에서 0으로 깎이지 않게
    if (아이템주식_은총시세인가($name) || $name === '은총') {
      $원가 = 아이템주식_은총단가_로드();
      if ($원가 === '0') {
        return '0';
      }
      if ($curve) {
        $원가 = 아이템주식_소수배곱($원가, 아이템주식_공급배수($circulating, $cfg));
      }
      if ((float)$pm !== 1.0) {
        $원가 = 아이템주식_소수배곱($원가, $pm);
      }
      $bonbang = 아이템주식_본방결제모드인가();
      if (!$bonbang && !$curve && (float)$pm !== 1.0) {
        if (function_exists('info3_은총_금액_자리맞춤_적응')) {
          $원가 = 아이템주식_냥(info3_은총_금액_자리맞춤_적응($원가, '경'));
        } elseif (function_exists('info3_은총_금액_자리맞춤')) {
          $원가 = 아이템주식_냥(info3_은총_금액_자리맞춤($원가, '경'));
        }
      }
      // 은총은 본방 스케일에서도 거액 — 정수 냥 유지
      return 아이템주식_냥($원가);
    }

    $percent = (float)($itemRow['percent'] ?? 0);
    if ($percent <= 0) {
      return '0';
    }
    if ($총게임냥 === null) {
      $총게임냥 = 아이템주식_총게임냥();
    }
    $needScale = $curve || ((float)$pm !== 1.0);
    $bonbang = 아이템주식_본방결제모드인가();

    if (!$needScale && !$bonbang && function_exists('아이템_구매단가_계산')) {
      return 아이템주식_냥(아이템_구매단가_계산($itemRow, $총게임냥));
    }

    $원가 = 아이템주식_기본원가($itemRow, $총게임냥);

    if ($curve) {
      $mult = 아이템주식_공급배수($circulating, $cfg);
      if ($bonbang) {
        $원가 = 아이템주식_본방금액정규화(((float)$원가) * (float)$mult);
      } else {
        $원가 = 아이템주식_소수배곱($원가, $mult);
      }
    }

    if ((float)$pm !== 1.0) {
      if ($bonbang) {
        $원가 = 아이템주식_본방금액정규화(((float)$원가) * (float)$pm);
      } else {
        $원가 = 아이템주식_소수배곱($원가, $pm);
      }
    }

    // 본방결제: 소수 1자리 유지 (아이템주식_냥은 소수 제거 → 0본방냥)
    if ($bonbang) {
      return 아이템주식_본방금액정규화($원가);
    }

    // 곡선 ON: 억절사 생략
    if ($curve) {
      return 아이템주식_냥($원가);
    }

    return function_exists('아이템_구매단가_억절사')
      ? 아이템주식_냥(아이템_구매단가_억절사($원가))
      : $원가;
  }
}

if (!function_exists('아이템주식_매수총액')) {
  /**
   * 수량만큼 곡선을 따라가며 합산 (매수 시 유통량이 1개씩 늘어난다고 가정)
   * @return array{총액:string,단가_첫:string,단가_끝:string,유통:int}
   */
  function 아이템주식_매수총액(array $itemRow, int $수량, $총게임냥 = null): array {
    $수량 = max(1, $수량);
    $name = 아이템주식_은총_정규sname(trim((string)($itemRow['sname'] ?? '')));
    $itemRow['sname'] = $name;
    $cfg = 아이템주식_기준설정_로드();
    $useCurve = ($name !== '' && !empty($cfg['supply_curve']));
    // 곡선 OFF여도 실제 유통량을 틱/차트에 남김 (복원·스케일용)
    $S = ($name !== '') ? 아이템주식_유통량($name) : 0;
    if ($총게임냥 === null) {
      $총게임냥 = 아이템주식_총게임냥();
    }

    if (!$useCurve || $수량 === 1) {
      $단가 = 아이템주식_매수단가($itemRow, $총게임냥, $useCurve ? $S : null);
      if (아이템주식_본방결제모드인가()) {
        $총액 = 아이템주식_본방곱($단가, $수량);
      } else {
        $총액 = 아이템주식_곱($단가, (string)$수량);
      }
      return ['총액' => $총액, '단가_첫' => $단가, '단가_끝' => $단가, '유통' => $S];
    }

    $bonbang = 아이템주식_본방결제모드인가();
    $총액 = $bonbang ? '0' : '0';
    $첫 = '0';
    $끝 = '0';
    for ($i = 0; $i < $수량; $i++) {
      $단가 = 아이템주식_매수단가($itemRow, $총게임냥, $S + $i);
      if ($i === 0) {
        $첫 = $단가;
      }
      $끝 = $단가;
      if ($bonbang) {
        $총액 = 아이템주식_본방가산($총액, $단가);
      } else {
        $총액 = function_exists('bcadd') ? bcadd($총액, $단가, 0) : (string)(((float)$총액) + (float)$단가);
        $총액 = 아이템주식_냥($총액);
      }
    }
    return ['총액' => $총액, '단가_첫' => $첫, '단가_끝' => $끝, '유통' => $S];
  }
}

if (!function_exists('아이템주식_매도총액')) {
  /**
   * 매도 시 곡선을 내려가며 합산 (현재 유통 S → S-1 …)
   * @return array{실수령:string,매수가액:string,스프레드:string,단가_첫:string,단가_끝:string,유통:int}
   */
  function 아이템주식_매도총액(array $itemRow, int $수량, $총게임냥 = null): array {
    $수량 = max(1, $수량);
    $name = 아이템주식_은총_정규sname(trim((string)($itemRow['sname'] ?? '')));
    $itemRow['sname'] = $name;
    $cfg = 아이템주식_기준설정_로드();
    $spread = 아이템주식_아이템스프레드율($name);
    $useCurve = ($name !== '' && !empty($cfg['supply_curve']));
    // 곡선 OFF여도 실제 유통량을 틱/차트에 남김
    $S = ($name !== '') ? 아이템주식_유통량($name) : 0;
    if ($총게임냥 === null) {
      $총게임냥 = 아이템주식_총게임냥();
    }

    $bonbang = 아이템주식_본방결제모드인가();
    $매수가액 = '0';
    $실수령 = '0';
    $첫 = '0';
    $끝 = '0';
    for ($i = 0; $i < $수량; $i++) {
      // 매도 직전 유통이 S-i 일 때, 한 단위의 시세 위치는 max(0, S-i-1)
      $pos = $useCurve ? max(0, $S - $i - 1) : null;
      $매수 = 아이템주식_매수단가($itemRow, $총게임냥, $pos);
      $매도 = 아이템주식_매도단가($매수, $spread);
      if ($i === 0) {
        $첫 = $매도;
      }
      $끝 = $매도;
      if ($bonbang) {
        $매수가액 = 아이템주식_본방가산($매수가액, $매수);
        $실수령 = 아이템주식_본방가산($실수령, $매도);
      } elseif (function_exists('bcadd')) {
        $매수가액 = bcadd($매수가액, $매수, 0);
        $실수령 = bcadd($실수령, $매도, 0);
      } else {
        $매수가액 = (string)(((float)$매수가액) + (float)$매수);
        $실수령 = (string)(((float)$실수령) + (float)$매도);
      }
    }
    if ($bonbang) {
      $매수가액 = 아이템주식_본방금액정규화($매수가액);
      $실수령 = 아이템주식_본방금액정규화($실수령);
      $스프레드액 = 아이템주식_본방차($매수가액, $실수령);
    } else {
      $매수가액 = 아이템주식_냥($매수가액);
      $실수령 = 아이템주식_냥($실수령);
      $스프레드액 = 아이템주식_차($매수가액, $실수령);
    }
    return [
      '실수령' => $실수령,
      '매수가액' => $매수가액,
      '스프레드' => $스프레드액,
      '단가_첫' => $첫,
      '단가_끝' => $끝,
      '유통' => $S,
    ];
  }
}

if (!function_exists('아이템주식_매도단가')) {
  /** 매도가 = 매수가 × (100−스프레드)/100 (내림) */
  function 아이템주식_매도단가($매수단가, $스프레드 = null): string {
    $spread = ($스프레드 === null) ? 아이템주식_스프레드율() : max(0, min(50, (int)$스프레드));
    $keep = 100 - $spread;

    if (아이템주식_본방결제모드인가()) {
      $매수 = round((float)$매수단가, 1);
      if ($매수 <= 0) {
        return '0';
      }
      if ($keep <= 0) {
        return '0';
      }
      if ($keep >= 100) {
        return 아이템주식_본방금액정규화($매수);
      }
      return 아이템주식_본방금액정규화($매수 * $keep / 100.0);
    }

    $매수 = 아이템주식_냥($매수단가);
    if ($매수 === '0') {
      return '0';
    }
    if ($keep <= 0) {
      return '0';
    }
    if ($keep >= 100) {
      return $매수;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return bcdiv(bcmul($매수, (string)$keep, 0), '100', 0);
    }
    // float/(int) 금지 — 거액이 PHP_INT_MAX·정밀도 손실로 수백경으로 뭉개짐
    $분자 = 아이템주식_곱($매수, (string)$keep);
    if (function_exists('bcdiv')) {
      return bcdiv($분자, '100', 0);
    }
    if (strlen($분자) <= 2) {
      return '0';
    }
    return ltrim(substr($분자, 0, -2), '0') ?: '0';
  }
}

if (!function_exists('아이템주식_대상여부')) {
  function 아이템주식_대상여부(array $itemRow): bool {
    $name = trim((string)($itemRow['sname'] ?? ''));
    if ($name === '' || in_array($name, 아이템주식_제외목록(), true)) {
      return false;
    }
    if (function_exists('아이템주식_본방시총가인가') && 아이템주식_본방시총가인가($name)) {
      return false;
    }
    // 은총: percent/buystatus 스텁이어도 권면가 시세로 거래소 포함
    if ($name === '은총' || 아이템주식_은총시세인가($name)) {
      return true;
    }
    if ((int)($itemRow['buystatus'] ?? 1) !== 0) {
      return false;
    }
    return (float)($itemRow['percent'] ?? 0) > 0;
  }
}

if (!function_exists('아이템주식_행조회')) {
  /** @return array|null */
  function 아이템주식_행조회(string $sname) {
    $sname = 아이템주식_공커대실권_정규sname(아이템주식_은총_정규sname($sname));
    if ($sname === '은총') {
      $row = 아이템주식_은총행_보장();
      if ($row === null || !아이템주식_대상여부($row)) {
        return null;
      }
      return $row;
    }
    // 공커대실권: percent 시세 제외이나 거래소 특수 구매 대상
    if (아이템주식_공커대실권인가($sname)) {
      $row = @db_select("
        SELECT idx, sname, buy, sell, percent, buystatus, status
        FROM tb_item
        WHERE sname = '공커대실권'
        LIMIT 1
      ");
      if (empty($row['idx']) || (int)($row['buystatus'] ?? 1) !== 0) {
        return null;
      }
      return $row;
    }
    $esc = addslashes(trim($sname));
    if ($esc === '') {
      return null;
    }
    $row = @db_select("
      SELECT idx, sname, buy, sell, percent, buystatus, status
      FROM tb_item
      WHERE sname = '{$esc}'
      LIMIT 1
    ");
    if (empty($row['idx']) || !아이템주식_대상여부($row)) {
      return null;
    }
    return $row;
  }
}

if (!function_exists('아이템주식_시세목록')) {
  /**
   * @return list<array{name:string,percent:float,buy:string,sell:string,buy_fmt:string,sell_fmt:string,circulating?:int,mult?:string}>
   */
  function 아이템주식_시세목록($단위 = '냥'): array {
    $총 = 아이템주식_총게임냥();
    $cfg = 아이템주식_기준설정_로드();
    $curve = !empty($cfg['supply_curve']);
    $목록 = [];
    $seen = [];

    $push = static function (array $row) use (&$목록, &$seen, $총, $cfg, $curve, $단위): void {
      if (!아이템주식_대상여부($row)) {
        return;
      }
      $name = trim((string)$row['sname']);
      $name = 아이템주식_은총_정규sname($name);
      if ($name === '' || isset($seen[$name])) {
        return;
      }
      if (아이템주식_본방시총가인가($name)) {
        return;
      }
      $seen[$name] = true;
      $isEun = ($name === '은총');
      // 표시용 유통량 = bag SUM. 곡선 ON이면 은총 포함 배수 적용
      $circ = 아이템주식_유통량($name);
      $useCurve = (bool)$curve;
      $mult = $useCurve ? 아이템주식_공급배수($circ, $cfg) : '1';
      $buy = 아이템주식_매수단가($row, $총, $useCurve ? $circ : null);
      $sell = 아이템주식_매도단가($buy, 아이템주식_아이템스프레드율($name));
      $목록[] = [
        'name' => $name,
        'percent' => $isEun ? 0.0 : (float)$row['percent'],
        'percent_label' => $isEun ? '은총시세' : '',
        'pricing' => $isEun ? 'eunchong' : 'percent',
        'pay_unit' => 아이템주식_본방결제모드인가() ? 'newpoint' : 'point',
        'buy' => $buy,
        'sell' => $sell,
        'buy_fmt' => 아이템주식_주가표시($buy, $단위),
        'sell_fmt' => 아이템주식_주가표시($sell, $단위),
        'buyable' => ($buy !== '0'),
        'circulating' => $circ,
        'mult' => $mult,
        'mult_fmt' => $useCurve ? (rtrim(rtrim(sprintf('%.4F', (float)$mult), '0'), '.') . '×') : '',
      ];
    };

    $rs = @db_query("SELECT sname, buy, percent, buystatus FROM tb_item WHERE buystatus = 0 AND percent > 0");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $push($row);
      }
    }
    // 은총: buystatus/percent 스텁이라 위 쿼리에 안 잡힘 → 별도 추가
    $eun = 아이템주식_은총행_보장();
    if ($eun !== null) {
      $push($eun);
    }
    // 꼬벙기념주화·1주년기념주화는 구매 목록에 넣지 않음 (보유자 팔기만)

    usort($목록, static function ($a, $b) {
      return 아이템주식_비교($b['buy'], $a['buy']);
    });
    return $목록;
  }
}

if (!function_exists('아이템주식_퍼센트표시')) {
  /** float 과학적 표기(1.0E-7) 방지 */
  function 아이템주식_퍼센트표시($percent): string {
    $p = (float)$percent;
    if ($p <= 0) {
      return '0';
    }
    if ($p >= 0.01) {
      $s = rtrim(rtrim(sprintf('%.4F', $p), '0'), '.');
      return $s === '' ? '0' : $s;
    }
    // 아주 작은 값: 소수 고정 (E 표기 금지)
    $s = rtrim(rtrim(sprintf('%.10F', $p), '0'), '.');
    return $s === '' ? '0' : $s;
  }
}

if (!function_exists('아이템주식_시세문구')) {
  function 아이템주식_시세문구($단위 = '냥', $정보 = null): string {
    $spread = 아이템주식_스프레드율();
    $cfg = 아이템주식_기준설정_로드();
    $목록 = (is_array($정보) && $정보 !== [])
      ? 아이템주식_시세목록_회원($정보, $단위)
      : 아이템주식_시세목록($단위);
    $keep = 100 - $spread;

    $msg = "📈 아이템 주가\n";
    if ($spread <= 0) {
      $msg .= "매수 = 매도 = 현재 시세 (차익 = 시세 − 매입가)\n";
    } else {
      $half = (int)floor($spread / 2);
      $msg .= "매수 = 시세 · 매도 = 시세 × {$keep}% (수수료 {$spread}% → 금고 {$half}% · 로또 " . ($spread - $half) . "%)\n";
    }
    if (!empty($cfg['supply_curve'])) {
      $msg .= "※ 유통 많을수록 매수가↑\n";
    }
    $msg .= "\n";

    if ($목록 === []) {
      $msg .= "거래 가능한 시세 아이템이 없어요.";
      return $msg;
    }

    $curve = !empty($cfg['supply_curve']);
    foreach ($목록 as $it) {
      $name = (string)$it['name'];
      $disp = (string)($it['display_name'] ?? $name);
      if (empty($it['buyable'])) {
        if (($it['pricing'] ?? '') === 'coin') {
          $sell = preg_replace('/(본방냥|게임냥|냥)$/u', '', (string)$it['sell_fmt']);
          $msg .= "【{$disp}】\n";
          $msg .= "매수  불가 (보유자 판매만)\n";
          $msg .= "매도  {$sell}\n";
          $msg .= "본방냥 시총 10% · 앞자리 반올림\n\n";
          continue;
        }
        $msg .= "【{$disp}】 매수불가\n\n";
        continue;
      }
      // 줄마다 '냥' 반복 제거
      $buy = preg_replace('/(본방냥|게임냥|냥)$/u', '', (string)$it['buy_fmt']);
      $sell = preg_replace('/(본방냥|게임냥|냥)$/u', '', (string)$it['sell_fmt']);
      $disp = (string)($it['display_name'] ?? $name);
      $msg .= "【{$disp}】\n";
      $msg .= "매수  {$buy}\n";
      if (($it['pricing'] ?? '') === 'gongkeo') {
        $msg .= "매도  불가 (구매즉시 기간연장)\n";
        $note = trim((string)($it['note'] ?? ''));
        if ($note !== '') {
          $msg .= "{$note}\n";
        }
        if (empty($it['is_gongkeo'])) {
          $msg .= "※ 공커가 아니면 구매할 수 없어요\n";
        }
      } elseif (($it['pricing'] ?? '') === 'coin') {
        $msg .= "매도  {$sell}\n";
        $msg .= "본방냥 시총 10% · 앞자리 반올림\n";
      } else {
        $msg .= "매도  {$sell}\n";
        $circN = (int)($it['circulating'] ?? 0);
        // 곡선 ON이거나 유통>0이면 표시
        if ($curve || $circN > 0) {
          $msg .= '유통  ' . number_format($circN) . "개\n";
        }
      }
      $msg .= "\n";
    }

    $msg .= "────────\n";
    $msg .= ".구매 / .주가 (시세표)\n";
    $msg .= ".구매 강일 (단가 조회) · .구매 강일 5 (매수 · 종류별 하루 10)\n";
    $msg .= ".매수 강일 5 · .매도 강일 / .매도 강일 3\n";
    $msg .= "※ 은총 매도: 생타 500→1개 · 1천→+2 · 1.5천→+3 (하루)";
    return $msg;
  }
}

if (!function_exists('아이템주식_단품시세_문구')) {
  /**
   * .구매 강일 → 해당 종목 매수/매도 단가만
   */
  function 아이템주식_단품시세_문구(string $아이템명, $단위 = '냥', $정보 = null): string {
    $아이템명 = 아이템주식_본방시총가_정규sname(아이템주식_공커대실권_정규sname(아이템주식_은총_정규sname(trim($아이템명))));
    if ($아이템명 === '') {
      return "❌ 아이템명을 확인해 주세요.\n예) .구매 강일";
    }

    $목록 = (is_array($정보) && $정보 !== [])
      ? 아이템주식_시세목록_회원($정보, $단위)
      : 아이템주식_시세목록($단위);
    $hit = null;
    foreach ($목록 as $it) {
      $n = (string)($it['name'] ?? '');
      $d = (string)($it['display_name'] ?? '');
      if ($n === $아이템명 || $d === $아이템명) {
        $hit = $it;
        break;
      }
    }
    if ($hit === null && 아이템주식_본방시총가인가($아이템명)) {
      $hit = 아이템주식_본방시총가_시세행($아이템명);
    }
    if ($hit === null) {
      $row = 아이템주식_행조회($아이템명);
      if ($row === null || !아이템주식_대상여부($row)) {
        return "❌ [{$아이템명}] 주식 시세 대상이 아니에요.\n시세표: .구매";
      }
      return "❌ [{$아이템명}] 현재 매수 불가 시세예요.";
    }

    $spread = 아이템주식_스프레드율();
    $cfg = 아이템주식_기준설정_로드();
    $name = (string)$hit['name'];
    $msg = "📈 【{$name}】 시세\n";
    if (($hit['pricing'] ?? '') === 'gongkeo') {
      $payUnit = (string)($hit['pay_unit'] ?? 'point');
      $표시단위 = ($payUnit === 'newpoint') ? '본방냥' : $단위;
      $buy = preg_replace('/(냥|본방냥|게임냥)$/u', '', (string)($hit['buy_fmt'] ?? ''));
      $msg .= "매수  {$buy}{$표시단위}\n";
      $msg .= "매도  불가 (구매즉시 기간연장)\n";
      $note = trim((string)($hit['note'] ?? ''));
      if ($note !== '') {
        $msg .= "{$note}\n";
      }
      $msg .= "\n구매하려면: .구매 {$name} 1";
      return $msg;
    }
    if (($hit['pricing'] ?? '') === 'coin') {
      $disp = (string)($hit['display_name'] ?? $name);
      $msg = "📈 【{$disp}】 시세\n";
      $sell = preg_replace('/(본방냥|게임냥|냥)$/u', '', (string)($hit['sell_fmt'] ?? ''));
      $msg .= "매수  불가 (보유자 판매만)\n";
      $msg .= "매도  {$sell}본방냥 (수수료 없음)\n";
      $msg .= "실시간 본방냥 시총 10% · 앞자리 반올림\n";
      return $msg;
    }
    if (empty($hit['buyable'])) {
      $msg .= "매수불가\n";
      return $msg;
    }
    $buy = preg_replace('/(본방냥|게임냥|냥)$/u', '', (string)($hit['buy_fmt'] ?? ''));
    $sell = preg_replace('/(본방냥|게임냥|냥)$/u', '', (string)($hit['sell_fmt'] ?? ''));
    $msg .= "매수  {$buy}{$단위}\n";
    $msg .= "매도  {$sell}{$단위}";
    if ($spread > 0) {
      $msg .= " (수수료 {$spread}%)";
    }
    $msg .= "\n";
    $circN = (int)($hit['circulating'] ?? 0);
    if (!empty($cfg['supply_curve']) || $circN > 0) {
      $msg .= '유통  ' . number_format($circN) . "개\n";
      if (!empty($hit['mult_fmt'])) {
        $msg .= '배수  ' . (string)$hit['mult_fmt'] . "\n";
      }
    }
    $msg .= "\n구매하려면: .구매 {$name} 1";
    return $msg;
  }
}

if (!defined('ITEM_STOCK_BUY_MAX_QTY')) {
  define('ITEM_STOCK_BUY_MAX_QTY', 10); // 1회 최대 (= 종류별 하루 한도)
}
if (!defined('ITEM_STOCK_BUY_HOURLY_MAX')) {
  define('ITEM_STOCK_BUY_HOURLY_MAX', 0); // 0 = 시간당 한도 없음 (종류별 일일 한도로 대체)
}
if (!defined('ITEM_STOCK_BUY_HOURLY_SEC')) {
  define('ITEM_STOCK_BUY_HOURLY_SEC', 3600);
}
if (!defined('ITEM_STOCK_BUY_DAILY_MAX')) {
  define('ITEM_STOCK_BUY_DAILY_MAX', 0); // 0 = 전체 하루 한도 없음
}
if (!defined('ITEM_STOCK_BUY_DAILY_PER_ITEM_MAX')) {
  define('ITEM_STOCK_BUY_DAILY_PER_ITEM_MAX', 10); // 아이템(종류)별 하루 한도 · 자정 리셋
}

if (!function_exists('아이템주식_매수수량합')) {
  /**
   * chat_stock 매수 qty 합 (regdate >= $since)
   * @param string $item 비우면 전체 · 지정 시 해당 sname만
   */
  function 아이템주식_매수수량합(string $nick, string $since, string $item = ''): int {
    $nick = trim($nick);
    if ($nick === '') {
      return 0;
    }
    if (!function_exists('item_trade_log_스키마보장') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }
    if (function_exists('item_trade_log_스키마보장')) {
      item_trade_log_스키마보장();
    }
    $nick_esc = addslashes($nick);
    $since_esc = addslashes($since);
    $itemSql = '';
    $item = trim($item);
    if ($item !== '') {
      $item_esc = addslashes($item);
      $itemSql = " AND item = '{$item_esc}'";
    }
    $row = @db_select("
      SELECT IFNULL(SUM(qty), 0) AS cnt
      FROM tb_item_trade_log
      WHERE nick = '{$nick_esc}'
        AND side = 'buy'
        AND channel = 'chat_stock'
        AND regdate >= '{$since_esc}'
        {$itemSql}
    ");
    return max(0, (int)($row['cnt'] ?? 0));
  }
}

if (!function_exists('아이템주식_시간당매수수량')) {
  /** 최근 1시간 chat_stock 매수 qty 합 */
  function 아이템주식_시간당매수수량(string $nick): int {
    $since = date('Y-m-d H:i:s', time() - (int)ITEM_STOCK_BUY_HOURLY_SEC);
    return 아이템주식_매수수량합($nick, $since);
  }
}

if (!function_exists('아이템주식_일일매수수량')) {
  /** 오늘 0시부터 chat_stock 매수 qty 합 (전체 종류) */
  function 아이템주식_일일매수수량(string $nick): int {
    $since = date('Y-m-d 00:00:00');
    return 아이템주식_매수수량합($nick, $since);
  }
}

if (!function_exists('아이템주식_일일매수수량_아이템')) {
  /** 오늘 0시(자정)부터 해당 아이템(종류) chat_stock 매수 qty 합 */
  function 아이템주식_일일매수수량_아이템(string $nick, string $item): int {
    $since = date('Y-m-d 00:00:00');
    return 아이템주식_매수수량합($nick, $since, $item);
  }
}

if (!function_exists('아이템주식_종류별일일매수한도검사')) {
  /**
   * 종류별 하루 매수 한도 검사
   * @return array{ok:bool,msg:string,used?:int,remain?:int,max?:int}
   */
  function 아이템주식_종류별일일매수한도검사(string $nick, string $item, int $수량): array {
    $max = (int)ITEM_STOCK_BUY_DAILY_PER_ITEM_MAX;
    if ($max <= 0) {
      return ['ok' => true, 'msg' => ''];
    }
    $item = trim($item);
    $수량 = max(1, (int)$수량);
    $used = 아이템주식_일일매수수량_아이템($nick, $item);
    $remain = max(0, $max - $used);
    if ($remain < 1) {
      return [
        'ok' => false,
        'msg' => "❌ [{$item}] 오늘 매수 한도 {$max}개를 모두 썼어요.\n자정(00:00)에 초기화돼요.",
        'used' => $used,
        'remain' => 0,
        'max' => $max,
      ];
    }
    if ($수량 > $remain) {
      return [
        'ok' => false,
        'msg' => "❌ [{$item}] 하루 매수 한도 {$max}개예요.\n오늘 이미 {$used}개 · 남은 {$remain}개 (자정 초기화)",
        'used' => $used,
        'remain' => $remain,
        'max' => $max,
      ];
    }
    return ['ok' => true, 'msg' => '', 'used' => $used, 'remain' => $remain, 'max' => $max];
  }
}

if (!function_exists('아이템주식_공커대실권_매수_실행')) {
  /**
   * 공커만 구매 · 가방 미적립 · tb_couple.edate += 7×수량
   * @return array{ok:bool,msg:string,단가?:string,총액?:string,수량?:int,point?:string}
   */
  function 아이템주식_공커대실권_매수_실행($두자리닉넴, array $정보, int $수량 = 1): array {
    global $단위;
    // 기본: 게임냥. 본방(info1) 스왑본방결제 플래그 시 게임냥시세→스왑가 본방냥
    $스왑본방 = 아이템주식_공커대실권_스왑본방결제인가();
    $u = $스왑본방 ? '본방냥' : (isset($단위) ? (string)$단위 : '냥');
    if (!$스왑본방 && ($u === '' || $u === '본방냥')) {
      $u = '냥';
    }
    $본방결제 = $스왑본방; // newpoint 차감 경로 재사용
    $수량 = max(1, (int)$수량);
    $maxQty = (int)ITEM_STOCK_BUY_MAX_QTY;
    $perItemDayMax = (int)ITEM_STOCK_BUY_DAILY_PER_ITEM_MAX;
    if ($수량 > $maxQty) {
      return ['ok' => false, 'msg' => "❌ 한 번에 최대 {$maxQty}개까지 매수할 수 있어요. (종류별 하루 {$perItemDayMax}개)"];
    }

    $midx = (int)($정보['idx'] ?? 0);
    $닉 = trim((string)($정보['name'] ?? $두자리닉넴));
    if ($midx < 1 || $닉 === '') {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    if (아이템주식_행조회('공커대실권') === null) {
      return ['ok' => false, 'msg' => '❌ 공커대실권을 구매할 수 없어요.'];
    }
    if (!function_exists('공커_대실권_구매대상_조회') && !function_exists('공커_활성_조회')) {
      return ['ok' => false, 'msg' => '❌ 공커 정보를 확인할 수 없어요.'];
    }
    $커플 = function_exists('공커_대실권_구매대상_조회')
      ? 공커_대실권_구매대상_조회($닉)
      : 공커_활성_조회($닉);
    if (empty($커플['idx'])) {
      $활성 = function_exists('공커_활성_조회') ? 공커_활성_조회($닉) : null;
      if (!empty($활성['idx'])) {
        return ['ok' => false, 'msg' => "❌ [{$닉}] 공커 자숙(.추가 공커) 중이라 대실권을 구매할 수 없어요."];
      }
      $oneroom = function_exists('공커_oneroom_조회') ? 공커_oneroom_조회($닉) : -1;
      return [
        'ok' => false,
        'msg' => "❌ [{$닉}] 활성 공커 커플이 없어요.\n(oneroom={$oneroom} · tb_couple status=0 확인)",
      ];
    }
    $단가 = 아이템주식_공커대실권_단가($커플);
    if ($단가 === '0') {
      $amt = 아이템주식_냥($커플['amount'] ?? 0);
      return [
        'ok' => false,
        'msg' => "❌ 가격이 설정되지 않아 공커대실권을 구매할 수 없습니다.\n(커플 amount={$amt})",
      ];
    }

    $한도 = 아이템주식_종류별일일매수한도검사($닉, '공커대실권', $수량);
    if (empty($한도['ok'])) {
      return ['ok' => false, 'msg' => (string)($한도['msg'] ?? '❌ 하루 매수 한도를 초과했어요.')];
    }
    $hourlyMax = (int)ITEM_STOCK_BUY_HOURLY_MAX;
    if ($hourlyMax > 0) {
      $usedHour = 아이템주식_시간당매수수량($닉);
      $remainHour = max(0, $hourlyMax - $usedHour);
      if ($remainHour < 1) {
        return [
          'ok' => false,
          'msg' => "❌ 시간당 매수 한도 {$hourlyMax}개를 모두 썼어요.\n1시간 뒤에 다시 시도해 주세요.",
        ];
      }
      if ($수량 > $remainHour) {
        return [
          'ok' => false,
          'msg' => "❌ 시간당 매수 한도 {$hourlyMax}개예요.\n이번 시간 이미 {$usedHour}개 · 남은 {$remainHour}개",
        ];
      }
    }
    $dailyMax = (int)ITEM_STOCK_BUY_DAILY_MAX;
    if ($dailyMax > 0) {
      $usedDay = 아이템주식_일일매수수량($닉);
      $remainDay = max(0, $dailyMax - $usedDay);
      if ($remainDay < 1) {
        return [
          'ok' => false,
          'msg' => "❌ 하루 매수 한도 {$dailyMax}개를 모두 썼어요.\n내일 다시 시도해 주세요.",
        ];
      }
      if ($수량 > $remainDay) {
        return [
          'ok' => false,
          'msg' => "❌ 하루 매수 한도 {$dailyMax}개예요.\n오늘 이미 {$usedDay}개 · 남은 {$remainDay}개",
        ];
      }
    }

    $총액_게임 = 아이템주식_곱($단가, (string)$수량);
    $단가_게임 = $단가;
    $총액 = $총액_게임;
    if ($스왑본방) {
      $단가 = 아이템주식_게임냥_스왑본방환산($단가_게임);
      $총액 = 아이템주식_게임냥_스왑본방환산($총액_게임);
      if ($단가 === '0' || $총액 === '0') {
        return ['ok' => false, 'msg' => '❌ 스왑가를 계산할 수 없어 본방냥 결제를 할 수 없어요.'];
      }
    }
    global $conn;
    if ($본방결제) {
      $보유Np = 아이템주식_본방잔액_조회($midx);
      if (아이템주식_본방잔액부족인가($보유Np, $총액)) {
        $보유표시 = (function_exists('아이템주식_본방표시숫자') ? 아이템주식_본방표시숫자($보유Np) : number_format($보유Np, 1)) . '본방냥';
        $게임참고 = $스왑본방
          ? ("\n(게임냥 시세 " . 아이템주식_표시($총액_게임, '냥') . " → 스왑가 본방냥)")
          : '';
        return [
          'ok' => false,
          'msg' => "‼️보유 본방냥 부족!\n공커대실권 매수 " . 아이템주식_표시($총액, $u)
            . " (단가 " . 아이템주식_표시($단가, $u) . " × {$수량})\n현재 보유 : {$보유표시}{$게임참고}",
        ];
      }
    } else {
      $pt행 = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE idx = {$midx} LIMIT 1");
      $보유원본 = $pt행['point'] ?? '0';
      if (아이템주식_매수잔액부족인가($보유원본, $총액)) {
        $보유표시 = 아이템주식_잔액표시($보유원본, $u);
        $음수안내 = 아이템주식_잔액_음수인가($보유원본)
          ? "\n※ 마이너스 게임냥 상태에서는 구매할 수 없어요."
          : '';
        return [
          'ok' => false,
          'msg' => "‼️보유 {$u} 부족!\n공커대실권 매수 " . 아이템주식_표시($총액, $u)
            . " (단가 " . 아이템주식_표시($단가, $u) . " × {$수량})\n현재 보유 : {$보유표시}{$음수안내}",
        ];
      }
    }

    $edate = substr(trim((string)($커플['edate'] ?? '')), 0, 10);
    if ($edate === '' || $edate === '0000-00-00') {
      $edate = date('Y-m-d');
    }
    $연장기간 = 7 * $수량;
    $새만료 = date('Y-m-d', strtotime($edate . " +{$연장기간} days"));
    $cidx = (int)$커플['idx'];
    $총액sql = 아이템주식_냥_sql($총액);
    if ($본방결제) {
      $차감Np = 아이템주식_본방_sql($총액);
      db_query("UPDATE tb_member SET newpoint = newpoint - {$차감Np} WHERE idx = {$midx} AND newpoint >= {$차감Np} LIMIT 1");
    } else {
      db_query("UPDATE tb_member SET point = point - {$총액sql} WHERE idx = {$midx} AND point >= {$총액sql} LIMIT 1");
    }
    if (!($conn instanceof mysqli) || (int)mysqli_affected_rows($conn) < 1) {
      return [
        'ok' => false,
        'msg' => "‼️보유 {$u} 부족!\n공커대실권 매수 " . 아이템주식_표시($총액, $u)
          . "\n※ 잔액이 부족해요.",
      ];
    }
    db_query("UPDATE tb_couple SET edate = '{$새만료}' WHERE idx = {$cidx} LIMIT 1");

    if (!function_exists('item_trade_log_구매') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }
    $payLogUnit = $본방결제 ? 'newpoint' : 'point';
    if (function_exists('item_trade_log_구매')) {
      item_trade_log_구매($닉, '공커대실권', $수량, $단가, $총액, 0, $midx, 'chat_stock', '', $payLogUnit);
    }
    if (function_exists('지급로그')) {
      지급로그('주식매수', $두자리닉넴, '공커대실권', 0, $총액);
    }

    $잔여 = @db_select("SELECT CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE idx = {$midx} LIMIT 1");
    $수량표시 = ($수량 > 1) ? " {$수량}개" : '';
    $스왑안내 = '';
    if ($스왑본방) {
      $스왑안내 = "\n※ 게임냥 시세 " . 아이템주식_표시($총액_게임, '냥') . " → 스왑가 본방냥 결제";
    }
    return [
      'ok' => true,
      'msg' => "📈 [{$닉}] 공커대실권{$수량표시} 매수 " . 아이템주식_표시($총액, $u)
        . "\n(단가 " . 아이템주식_표시($단가, $u) . ")"
        . "\n공커 기간 {$edate} → {$새만료} (+{$연장기간}일)"
        . $스왑안내,
      '단가' => $단가,
      '총액' => $총액,
      '수량' => $수량,
      'pay_unit' => $본방결제 ? 'newpoint' : 'point',
      'point' => 아이템주식_냥($잔여['point'] ?? 0),
      'newpoint' => 아이템주식_냥($잔여['newpoint'] ?? 0),
    ];
  }
}

if (!function_exists('아이템주식_매수_실행')) {
  /**
   * @return array{ok:bool,msg:string,단가?:string,총액?:string,수량?:int,point?:string}
   */
  function 아이템주식_매수_실행($두자리닉넴, array $정보, string $아이템명, int $수량 = 1): array {
    global $단위;
    $GLOBALS['_item_stock_eun_price'] = null; // 거래 직전 은총가 재계산
    $닉모드 = trim((string)($정보['name'] ?? $두자리닉넴));
    if (function_exists('아이템주식_본방결제모드_설정')) {
      아이템주식_본방결제모드_설정($닉모드);
    }
    $u = 아이템주식_결제단위라벨(isset($단위) ? (string)$단위 : '냥');
    $본방결제 = 아이템주식_본방결제모드인가();
    $아이템명 = 아이템주식_공커대실권_정규sname(아이템주식_은총_정규sname(trim($아이템명)));
    $수량 = max(1, (int)$수량);
    $maxQty = (int)ITEM_STOCK_BUY_MAX_QTY;
    $perItemDayMax = (int)ITEM_STOCK_BUY_DAILY_PER_ITEM_MAX;
    if ($수량 > $maxQty) {
      return ['ok' => false, 'msg' => "❌ 한 번에 최대 {$maxQty}개까지 매수할 수 있어요. (종류별 하루 {$perItemDayMax}개)"];
    }
    if ($아이템명 === '') {
      return ['ok' => false, 'msg' => '❌ 아이템명을 확인해주세요. 예) .매수 강일 5'];
    }

    $아이템명 = 아이템주식_본방시총가_정규sname(아이템주식_공커대실권_정규sname(아이템주식_은총_정규sname($아이템명)));

    if (아이템주식_공커대실권인가($아이템명)) {
      return 아이템주식_공커대실권_매수_실행($두자리닉넴, $정보, $수량);
    }
    if (아이템주식_본방시총가인가($아이템명)) {
      $disp = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($아이템명) : $아이템명;
      return ['ok' => false, 'msg' => "❌ {$disp} 구매 불가 · 보유자만 팔 수 있어요."];
    }

    if (!function_exists('item_bag_add') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (!function_exists('item_trade_log_구매') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }

    $row = 아이템주식_행조회($아이템명);
    if ($row === null) {
      return ['ok' => false, 'msg' => "❌ [{$아이템명}] 주식 시세 대상이 아니에요.\n.주가 로 목록을 확인하세요."];
    }

    $midx = (int)($정보['idx'] ?? 0);
    $닉 = trim((string)($정보['name'] ?? $두자리닉넴));
    if ($midx < 1 || $닉 === '') {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $한도 = 아이템주식_종류별일일매수한도검사($닉, $아이템명, $수량);
    if (empty($한도['ok'])) {
      return ['ok' => false, 'msg' => (string)($한도['msg'] ?? '❌ 하루 매수 한도를 초과했어요.')];
    }
    $hourlyMax = (int)ITEM_STOCK_BUY_HOURLY_MAX;
    if ($hourlyMax > 0) {
      $usedHour = 아이템주식_시간당매수수량($닉);
      $remainHour = max(0, $hourlyMax - $usedHour);
      if ($remainHour < 1) {
        return [
          'ok' => false,
          'msg' => "❌ 시간당 매수 한도 {$hourlyMax}개를 모두 썼어요.\n1시간 뒤에 다시 시도해 주세요.",
        ];
      }
      if ($수량 > $remainHour) {
        return [
          'ok' => false,
          'msg' => "❌ 시간당 매수 한도 {$hourlyMax}개예요.\n이번 시간 이미 {$usedHour}개 · 남은 {$remainHour}개",
        ];
      }
    }

    $dailyMax = (int)ITEM_STOCK_BUY_DAILY_MAX;
    if ($dailyMax > 0) {
      $usedDay = 아이템주식_일일매수수량($닉);
      $remainDay = max(0, $dailyMax - $usedDay);
      if ($remainDay < 1) {
        return [
          'ok' => false,
          'msg' => "❌ 하루 매수 한도 {$dailyMax}개를 모두 썼어요.\n내일 다시 시도해 주세요.",
        ];
      }
      if ($수량 > $remainDay) {
        return [
          'ok' => false,
          'msg' => "❌ 하루 매수 한도 {$dailyMax}개예요.\n오늘 이미 {$usedDay}개 · 남은 {$remainDay}개",
        ];
      }
    }

    $견적 = 아이템주식_매수총액($row, $수량);
    $단가 = $견적['단가_첫'];
    $총액 = $견적['총액'];
    if ($단가 === '0' || $총액 === '0') {
      $사유 = ($아이템명 === '은총')
        ? '은총 시세를 계산할 수 없어요.'
        : "현재 총 게임냥 기준 시세가 없어 {$아이템명}을(를) 매수할 수 없어요.";
      if ($본방결제) {
        $사유 = ($아이템명 === '은총')
          ? '은총 시세(본방냥)를 계산할 수 없어요.'
          : "현재 총 본방냥 기준 시세가 없어 {$아이템명}을(를) 매수할 수 없어요.";
      }
      return ['ok' => false, 'msg' => "❌ {$사유}"];
    }

    if ($아이템명 === '지호') {
      $한도 = 100000;
      $보유 = function_exists('item_bag_qty') ? (int)item_bag_qty($midx, '지호') : 0;
      if ($보유 + $수량 > $한도) {
        $가능 = max(0, $한도 - $보유);
        return [
          'ok' => false,
          'msg' => "❌ 지호는 최대 " . number_format($한도) . "개까지 보유 가능합니다.\n현재 {$보유}개 · 추가 가능 {$가능}개",
        ];
      }
    }

    global $conn;
    if ($본방결제) {
      $보유Np = 아이템주식_본방잔액_조회($midx);
      if (아이템주식_본방잔액부족인가($보유Np, $총액)) {
        $보유표시 = (function_exists('아이템주식_본방표시숫자') ? 아이템주식_본방표시숫자($보유Np) : number_format($보유Np, 1)) . '본방냥';
        return [
          'ok' => false,
          'msg' => "‼️보유 본방냥 부족!\n{$아이템명} 매수 " . 아이템주식_표시($총액, $u)
            . " (단가 " . 아이템주식_표시($단가, $u) . " × {$수량})\n현재 보유 : {$보유표시}",
        ];
      }
    } else {
      $pt행 = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE idx = {$midx} LIMIT 1");
      $보유원본 = $pt행['point'] ?? '0';
      if (아이템주식_매수잔액부족인가($보유원본, $총액)) {
        $보유표시 = 아이템주식_잔액표시($보유원본, $u);
        $음수안내 = 아이템주식_잔액_음수인가($보유원본)
          ? "\n※ 마이너스 게임냥 상태에서는 구매할 수 없어요."
          : '';
        return [
          'ok' => false,
          'msg' => "‼️보유 {$u} 부족!\n{$아이템명} 매수 " . 아이템주식_표시($총액, $u)
            . " (단가 " . 아이템주식_표시($단가, $u) . " × {$수량})\n현재 보유 : {$보유표시}{$음수안내}",
        ];
      }
    }

    if (!function_exists('item_bag_add')) {
      return ['ok' => false, 'msg' => '❌ 가방 시스템을 사용할 수 없어요.'];
    }

    // 잔액 차감을 아이템 지급보다 먼저 (마이너스 잔액 구매 방지)
    $총액sql = 아이템주식_냥_sql($총액);
    if ($본방결제) {
      $차감Np = 아이템주식_본방_sql($총액);
      db_query("UPDATE tb_member SET newpoint = newpoint - {$차감Np} WHERE idx = {$midx} AND newpoint >= {$차감Np} LIMIT 1");
    } else {
      db_query("UPDATE tb_member SET point = point - {$총액sql} WHERE idx = {$midx} AND point >= {$총액sql} LIMIT 1");
    }
    if (!($conn instanceof mysqli) || (int)mysqli_affected_rows($conn) < 1) {
      return [
        'ok' => false,
        'msg' => "‼️보유 {$u} 부족!\n{$아이템명} 매수 " . 아이템주식_표시($총액, $u)
          . "\n※ 잔액이 부족해요.",
      ];
    }

    if ($아이템명 === '은총') {
      if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
        require_once __DIR__ . '/item_bag_enhance.inc.php';
      }
      if (function_exists('bag_enhance_스키마보장')) {
        bag_enhance_스키마보장();
      }
      if (function_exists('item_bag_ensure_column')) {
        item_bag_ensure_column('은총');
      }
      // bag + tb_member.은총개수 미러까지 맞춤 (item_bag_add만 하면 .가방에 복구됨)
      $bagAdd = function_exists('bag_은총_가산')
        ? bag_은총_가산($닉, $수량)
        : item_bag_add($midx, $닉, $아이템명, $수량);
    } else {
      $bagAdd = item_bag_add($midx, $닉, $아이템명, $수량);
    }
    if (empty($bagAdd['ok'])) {
      // 지급 실패 시 차감 복구
      if ($본방결제) {
        $차감Np = 아이템주식_본방_sql($총액);
        db_query("UPDATE tb_member SET newpoint = newpoint + {$차감Np} WHERE idx = {$midx} LIMIT 1");
      } else {
        db_query("UPDATE tb_member SET point = point + {$총액sql} WHERE idx = {$midx} LIMIT 1");
      }
      return ['ok' => false, 'msg' => '❌ 아이템 지급 실패: ' . ($bagAdd['msg'] ?? '가방 오류')];
    }

    $payLogUnit = $본방결제 ? 'newpoint' : 'point';
    if (function_exists('item_trade_log_구매')) {
      item_trade_log_구매($닉, $아이템명, $수량, $단가, $총액, 0, $midx, 'chat_stock', '', $payLogUnit);
    }
    // 차트용: 거래 직전·직후 시세를 둘 다 남겨 한 번의 매수로도 선이 움직이게
    if (function_exists('아이템주식_틱_기록_값')) {
      $circBefore = (int)($견적['유통'] ?? 아이템주식_유통량($아이템명));
      $buyBefore = 아이템주식_냥($견적['단가_첫'] ?? $단가);
      $sellBefore = 아이템주식_매도단가($buyBefore, 아이템주식_아이템스프레드율($아이템명));
      아이템주식_틱_기록_값($아이템명, $buyBefore, $sellBefore, $circBefore, 'buy_pre');
      $circAfter = $circBefore + $수량;
      $buyAfter = 아이템주식_매수단가($row, null, $circAfter);
      $sellAfter = 아이템주식_매도단가($buyAfter, 아이템주식_아이템스프레드율($아이템명));
      아이템주식_틱_기록_값($아이템명, $buyAfter, $sellAfter, $circAfter, 'buy');
    } elseif (function_exists('아이템주식_틱_기록_아이템')) {
      아이템주식_틱_기록_아이템($아이템명, 'buy');
    }
    if (function_exists('지급로그')) {
      지급로그('주식매수', $두자리닉넴, $아이템명, 0, $총액);
    }

    $잔여 = @db_select("SELECT CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE idx = {$midx} LIMIT 1");
    $수량표시 = ($수량 > 1) ? " {$수량}개" : '';
    $cfg = 아이템주식_기준설정_로드();
    $curveNote = '';
    if (!empty($cfg['supply_curve'])) {
      $after = (int)$견적['유통'] + $수량;
      $curveNote = "\n유통 {$견적['유통']}→{$after} · 다음배수 "
        . rtrim(rtrim(sprintf('%.4F', (float)아이템주식_공급배수($after, $cfg)), '0'), '.') . '×';
      if ($수량 > 1 && $견적['단가_첫'] !== $견적['단가_끝']) {
        $curveNote .= "\n(첫 " . 아이템주식_표시($견적['단가_첫'], $u)
          . " → 끝 " . 아이템주식_표시($견적['단가_끝'], $u) . ')';
      }
    }
    $시세라벨 = ($아이템명 === '은총') ? '은총시세' : '주가시세';
    return [
      'ok' => true,
      'msg' => "📈 [{$닉}] {$아이템명}{$수량표시} 매수 " . 아이템주식_표시($총액, $u)
        . "\n(단가 " . 아이템주식_표시($단가, $u) . " · {$시세라벨}){$curveNote}",
      '단가' => $단가,
      '총액' => $총액,
      '수량' => $수량,
      'pay_unit' => $본방결제 ? 'newpoint' : 'point',
      'point' => 아이템주식_냥($잔여['point'] ?? 0),
      'newpoint' => 아이템주식_냥($잔여['newpoint'] ?? 0),
    ];
  }
}

if (!function_exists('아이템주식_본방시총가_매수_실행')) {
  function 아이템주식_본방시총가_매수_실행($두자리닉넴, array $정보, string $아이템명, int $수량 = 1): array {
    $아이템명 = 아이템주식_본방시총가_정규sname($아이템명);
    $disp = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($아이템명) : $아이템명;
    return ['ok' => false, 'msg' => "❌ {$disp} 구매 불가 · 보유자만 팔 수 있어요."];
  }
}

if (!function_exists('아이템주식_본방시총가_매도_실행')) {
  function 아이템주식_본방시총가_매도_실행($두자리닉넴, array $정보, string $아이템명, int $수량 = 1): array {
    $아이템명 = 아이템주식_본방시총가_정규sname($아이템명);
    $수량 = max(1, (int)$수량);
    $midx = (int)($정보['idx'] ?? 0);
    $닉 = trim((string)($정보['name'] ?? $두자리닉넴));
    if ($midx < 1 || $닉 === '') {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    if (아이템주식_기념주화인가($아이템명)) {
      아이템주식_기념주화_행보장($아이템명);
    }
    $견적 = 아이템주식_기념주화_매도가($정보, $아이템명, $수량);
    if (empty($견적['ok'])) {
      return $견적;
    }
    if (!function_exists('item_bag_sub') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    $bagSub = item_bag_sub($midx, $닉, $아이템명, $수량);
    if (empty($bagSub['ok'])) {
      return ['ok' => false, 'msg' => $bagSub['msg'] ?? '❌ 판매 처리 중 오류 (보유 수량 불일치).'];
    }
    $실수령 = 아이템주식_냥($견적['실수령'] ?? 0);
    $단가 = 아이템주식_본방시총가_단가($아이템명);
    $지급Sql = function_exists('냥_SQL정수') ? 냥_SQL정수($실수령) : $실수령;
    if (!preg_match('/^\d+$/', (string)$지급Sql)) {
      item_bag_add($midx, $닉, $아이템명, $수량);
      return ['ok' => false, 'msg' => '❌ 지급 금액을 확인하지 못했어요.'];
    }
    $닉_esc = addslashes($닉);
    db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$지급Sql} WHERE name = '{$닉_esc}' LIMIT 1");
    if (function_exists('item_trade_log_판매')) {
      item_trade_log_판매($닉, $아이템명, $수량, $실수령, 0, $실수령, $midx, 'web', '', 'newpoint');
    }
    $수량표시 = ($수량 > 1) ? " {$수량}개" : '';
    $disp = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($아이템명) : $아이템명;
    $pt = @db_select("SELECT CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE idx = {$midx} LIMIT 1");
    return [
      'ok' => true,
      'msg' => "📉 [{$닉}] {$disp}{$수량표시} 매도 " . 아이템주식_본방시총가_표시($실수령)
        . "\n(본방냥 시총 10% · 앞자리 반올림)",
      '단가' => $단가,
      '총액' => $실수령,
      '수량' => $수량,
      'pay_unit' => 'newpoint',
      'point' => 아이템주식_냥($pt['point'] ?? 0),
      'newpoint' => 아이템주식_냥($pt['newpoint'] ?? 0),
    ];
  }
}

if (!function_exists('아이템주식_기념주화_매도_실행')) {
  /**
   * 기념주화 매도 — 가방 차감 · 본방냥(newpoint) 지급 (.판매와 동일 견적)
   * @return array{ok:bool,msg:string,단가?:string,총액?:string,수량?:int,point?:string,newpoint?:string}
   */
  function 아이템주식_기념주화_매도_실행($두자리닉넴, array $정보, string $아이템명, int $수량 = 1): array {
    return 아이템주식_본방시총가_매도_실행($두자리닉넴, $정보, $아이템명, $수량);
  }
}

if (!function_exists('아이템주식_매도_실행')) {
  /**
   * @return array{ok:bool,msg:string,단가?:string,총액?:string,스프레드?:string,수량?:int,point?:string}
   */
  function 아이템주식_매도_실행($두자리닉넴, array $정보, string $아이템명, int $수량 = 1): array {
    global $단위;
    $GLOBALS['_item_stock_eun_price'] = null; // 거래 직전 은총가 재계산
    $닉모드 = trim((string)($정보['name'] ?? $두자리닉넴));
    if (function_exists('아이템주식_본방결제모드_설정')) {
      아이템주식_본방결제모드_설정($닉모드);
    }
    $u = 아이템주식_결제단위라벨(isset($단위) ? (string)$단위 : '냥');
    $본방결제 = 아이템주식_본방결제모드인가();
    $아이템명 = 아이템주식_본방시총가_정규sname(아이템주식_기념주화_정규sname(아이템주식_은총_정규sname(trim($아이템명))));
    $수량 = max(1, (int)$수량);
    if ($아이템명 === '') {
      return ['ok' => false, 'msg' => '❌ 아이템명을 확인해주세요. 예) .매도 강일 3'];
    }
    if (아이템주식_공커대실권인가($아이템명)) {
      return ['ok' => false, 'msg' => '❌ 공커대실권은 매도할 수 없어요. (구매 즉시 공커 기간이 연장됩니다)'];
    }
    if (아이템주식_본방시총가인가($아이템명)) {
      return 아이템주식_본방시총가_매도_실행($두자리닉넴, $정보, $아이템명, $수량);
    }

    if (!function_exists('item_bag_sub') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (!function_exists('item_trade_log_판매') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }

    $row = 아이템주식_행조회($아이템명);
    if ($row === null) {
      return ['ok' => false, 'msg' => "❌ [{$아이템명}] 주식 시세 대상이 아니에요.\n.주가 로 목록을 확인하세요."];
    }

    $midx = (int)($정보['idx'] ?? 0);
    $닉 = trim((string)($정보['name'] ?? $두자리닉넴));
    if ($midx < 1 || $닉 === '') {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $은총매도 = ($아이템명 === '은총');
    if ($은총매도 && !function_exists('bag_은총_차감') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
      require_once __DIR__ . '/item_bag_enhance.inc.php';
    }
    // 은총: 컬럼(은총개수)↔bag hydrate 포함 수량 조회
    $보유 = ($은총매도 && function_exists('bag_은총_수량'))
      ? (int)bag_은총_수량($닉)
      : (function_exists('item_bag_qty') ? (int)item_bag_qty($midx, $아이템명) : 0);
    if ($보유 < $수량) {
      return ['ok' => false, 'msg' => "{$아이템명} 보유수량 {$보유}개"];
    }

    // 은총 매도: 오늘 생타 구간별 일일 판매 한도
    if ($은총매도) {
      $한도검사 = 아이템주식_은총매도_한도검사($닉, $수량);
      if (empty($한도검사['ok'])) {
        return ['ok' => false, 'msg' => (string)($한도검사['msg'] ?? '❌ 은총 매도 한도를 초과했어요.')];
      }
    }

    $견적 = 아이템주식_매도총액($row, $수량);
    $매도단가 = $견적['단가_첫'];
    $실수령 = $견적['실수령'];
    $매수가액 = $견적['매수가액'];
    $스프레드액 = $견적['스프레드'];
    $spread = 아이템주식_아이템스프레드율($아이템명);

    if ($매도단가 === '0' || $실수령 === '0') {
      return ['ok' => false, 'msg' => "❌ 현재 시세가 없어 {$아이템명}을(를) 매도할 수 없어요."];
    }
    if (!function_exists('item_bag_sub') && !$은총매도) {
      return ['ok' => false, 'msg' => '❌ 가방 시스템을 사용할 수 없어요.'];
    }
    // 은총: bag 차감 + 은총개수 미러. item_bag_sub만 하면 bag=0일 때 컬럼이 다시 hydrate되어 .가방에 복구됨
    if ($은총매도 && function_exists('bag_은총_차감')) {
      $bagSub = bag_은총_차감($닉, $수량);
    } else {
      if (!function_exists('item_bag_sub')) {
        return ['ok' => false, 'msg' => '❌ 가방 시스템을 사용할 수 없어요.'];
      }
      $bagSub = item_bag_sub($midx, $닉, $아이템명, $수량);
    }
    if (empty($bagSub['ok'])) {
      return ['ok' => false, 'msg' => $bagSub['msg'] ?? '❌ 매도 처리 중 오류 (보유 수량). 다시 시도해주세요.'];
    }

    $실수령sql = 아이템주식_냥_sql($실수령);
    $닉_esc = addslashes($닉);
    if ($본방결제) {
      $실수령Np = 아이템주식_본방_sql($실수령);
      db_query("UPDATE tb_member SET newpoint = newpoint + {$실수령Np} WHERE name = '{$닉_esc}' LIMIT 1");
    } else {
      db_query("UPDATE tb_member SET point = point + {$실수령sql} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    $배분 = ['금고' => '0', '로또' => '0'];
    if ($스프레드액 !== '0') {
      $배분 = 아이템주식_수수료_금고로또배분($스프레드액);
    }

    $payLogUnit = $본방결제 ? 'newpoint' : 'point';
    if (function_exists('item_trade_log_판매')) {
      item_trade_log_판매($닉, $아이템명, $수량, $매수가액, $스프레드액, $실수령, $midx, 'chat_stock', '', $payLogUnit);
    }
    if (function_exists('아이템주식_틱_기록_값')) {
      $circBefore = (int)($견적['유통'] ?? 아이템주식_유통량($아이템명));
      // 매도 견적 단가_첫 = 수수료 반영 매도가 → 차트는 매수가(시세) 기준
      $buyBefore = 아이템주식_냥($견적['매수가액'] ?? '0');
      if ($수량 > 0 && $buyBefore !== '0') {
        $buyBefore = 아이템주식_나누기내림($buyBefore, $수량);
      }
      if ($buyBefore === '0') {
        $buyBefore = 아이템주식_매수단가($row, null, max(0, $circBefore - 1));
      }
      $sellBefore = 아이템주식_냥($견적['단가_첫'] ?? $매도단가);
      아이템주식_틱_기록_값($아이템명, $buyBefore, $sellBefore, $circBefore, 'sell_pre');
      $circAfter = max(0, $circBefore - $수량);
      $buyAfter = 아이템주식_매수단가($row, null, $circAfter);
      $sellAfter = 아이템주식_매도단가($buyAfter, 아이템주식_아이템스프레드율($아이템명));
      아이템주식_틱_기록_값($아이템명, $buyAfter, $sellAfter, $circAfter, 'sell');
    } elseif (function_exists('아이템주식_틱_기록_아이템')) {
      아이템주식_틱_기록_아이템($아이템명, 'sell');
    }
    if (function_exists('지급로그')) {
      지급로그('주식매도', $두자리닉넴, $아이템명, $스프레드액, $실수령);
    }

    $잔여 = @db_select("SELECT CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $수량표시 = ($수량 > 1) ? " {$수량}개" : '';
    $cfg = 아이템주식_기준설정_로드();
    $curveNote = '';
    if (!empty($cfg['supply_curve'])) {
      $after = max(0, (int)$견적['유통'] - $수량);
      $curveNote = "\n유통 {$견적['유통']}→{$after} · 다음배수 "
        . rtrim(rtrim(sprintf('%.4F', (float)아이템주식_공급배수($after, $cfg)), '0'), '.') . '×';
    }
    $half = (int)floor($spread / 2);
    $spreadNote = ($spread <= 0 || $스프레드액 === '0')
      ? ' · 수수료 없음(매수=매도)'
      : (
        " · 수수료 {$spread}% " . 아이템주식_표시($스프레드액, $u)
        . " (금고 {$half}% " . 아이템주식_표시($배분['금고'], $u)
        . " · 로또 " . ($spread - $half) . '% ' . 아이템주식_표시($배분['로또'], $u) . ')'
      );
    return [
      'ok' => true,
      'msg' => "📉 [{$닉}] {$아이템명}{$수량표시} 매도 " . 아이템주식_표시($실수령, $u)
        . "\n(단가 " . 아이템주식_표시($매도단가, $u) . "{$spreadNote}){$curveNote}",
      '단가' => $매도단가,
      '총액' => $실수령,
      '스프레드' => $스프레드액,
      '금고' => $배분['금고'],
      '로또' => $배분['로또'],
      '수량' => $수량,
      'pay_unit' => $본방결제 ? 'newpoint' : 'point',
      'point' => 아이템주식_냥($잔여['point'] ?? 0),
      'newpoint' => 아이템주식_냥($잔여['newpoint'] ?? 0),
    ];
  }
}

if (!function_exists('아이템주식_보유목록')) {
  /**
   * 주식 매도 가능 보유분 (percent 시세 아이템만)
   * @return list<array{name:string,count:int,buy:string,sell:string,buy_fmt:string,sell_fmt:string,sell_all_fmt:string}>
   */
  function 아이템주식_보유목록(array $정보, $단위 = '냥'): array {
    $midx = (int)($정보['idx'] ?? 0);
    if ($midx < 1) {
      return [];
    }
    if (!function_exists('item_bag_list') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (!function_exists('item_bag_list')) {
      return [];
    }
    $닉 = trim((string)($정보['name'] ?? ''));
    // 은총: 컬럼에만 남은 수량을 bag로 이관(표시·매도 수량 일치)
    if ($닉 !== '') {
      if (!function_exists('bag_은총_수량') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
        require_once __DIR__ . '/item_bag_enhance.inc.php';
      }
      if (function_exists('bag_은총_수량')) {
        bag_은총_수량($닉);
      }
    }
    $총 = 아이템주식_총게임냥();
    $out = [];
    $seenCoin = [];
    foreach (item_bag_list($midx) as $it) {
      $name = trim((string)($it['name'] ?? ''));
      $cnt = (int)($it['count'] ?? 0);
      if ($name === '' || $cnt < 1) {
        continue;
      }
      // 본방시총가(기념주화·부루마블1등): 시세 대상 아니어도 보유 시 팔기 가능
      if (아이템주식_본방시총가인가($name)) {
        $name = 아이템주식_본방시총가_정규sname($name);
        if (isset($seenCoin[$name])) {
          continue;
        }
        $seenCoin[$name] = true;
        if (아이템주식_기념주화인가($name)) {
          아이템주식_기념주화_행보장($name);
        }
        $견적1 = 아이템주식_기념주화_매도가($정보, $name, 1);
        $견적All = ($cnt > 1) ? 아이템주식_기념주화_매도가($정보, $name, $cnt) : $견적1;
        if (empty($견적1['ok'])) {
          continue;
        }
        $sell = 아이템주식_냥($견적1['실수령'] ?? 0);
        $sellAll = 아이템주식_냥($견적All['실수령'] ?? $sell);
        $sellFmt = 아이템주식_본방시총가_표시($sell);
        $sellAllFmt = 아이템주식_본방시총가_표시($sellAll);
        $disp = (function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($name) : $name);
        $buy = 아이템주식_본방시총가_단가($name);
        $out[] = [
          'name' => $name,
          'display_name' => $disp,
          'count' => $cnt,
          'percent' => 10.0,
          'percent_label' => '시총10%',
          'pricing' => 'coin',
          'pay_unit' => 'newpoint',
          'buy' => $buy,
          'sell' => $sell,
          'buy_fmt' => 아이템주식_본방시총가_표시($buy),
          'sell_fmt' => $sellFmt,
          'sell_all' => $sellAll,
          'sell_all_fmt' => $sellAllFmt,
          'buyable' => ($buy !== '0'),
        ];
        continue;
      }
      $row = 아이템주식_행조회($name);
      if ($row === null) {
        continue;
      }
      $buy = 아이템주식_매수단가($row, $총);
      // 곡선 ON 시 수량별 단가가 달라지므로 전량 매도는 견적 함수 사용
      $견적 = 아이템주식_매도총액($row, $cnt, $총);
      $sell = 아이템주식_냥($견적['단가_첫'] ?? 아이템주식_매도단가($buy, 아이템주식_아이템스프레드율($name)));
      $sellAll = 아이템주식_냥($견적['실수령'] ?? 아이템주식_곱($sell, (string)$cnt));
      $out[] = [
        'name' => $name,
        'count' => $cnt,
        'percent' => (float)$row['percent'],
        'percent_label' => ($name === '은총') ? '은총시세' : '',
        'pricing' => ($name === '은총') ? 'eunchong' : 'percent',
        'pay_unit' => 아이템주식_본방결제모드인가() ? 'newpoint' : 'point',
        'buy' => $buy,
        'sell' => $sell,
        // 시세표(.주가)와 동일 축약 — 매수/매도/투자내역 숫자가 어긋져 보이지 않게
        'buy_fmt' => 아이템주식_주가표시($buy, $단위),
        'sell_fmt' => 아이템주식_주가표시($sell, $단위),
        'sell_all' => $sellAll,
        'sell_all_fmt' => 아이템주식_주가표시($sellAll, $단위),
        'buyable' => ($buy !== '0'),
      ];
    }
    usort($out, static function ($a, $b) {
      // 기념주화는 목록 상단 근처 · 본방냥 숫자는 게임냥과 스케일이 달라 이름순 보조
      $ac = (($a['pricing'] ?? '') === 'coin') ? 1 : 0;
      $bc = (($b['pricing'] ?? '') === 'coin') ? 1 : 0;
      if ($ac !== $bc) {
        return $bc - $ac;
      }
      return 아이템주식_비교($b['sell'], $a['sell']);
    });
    return $out;
  }
}

if (!function_exists('아이템주식_나누기내림')) {
  /** 거액 ÷ 작은 정수 (내림) — (int)/float 금지 */
  function 아이템주식_나누기내림($피제수, int $제수): string {
    $a = 아이템주식_냥($피제수);
    $제수 = max(1, $제수);
    if ($a === '0') {
      return '0';
    }
    if (function_exists('bcdiv')) {
      return bcdiv($a, (string)$제수, 0);
    }
    if (function_exists('shop_문자열_나누기_내림')) {
      return shop_문자열_나누기_내림($a, $제수);
    }
    // 학교식 나눗셈
    $out = '';
    $remain = 0;
    $len = strlen($a);
    for ($i = 0; $i < $len; $i++) {
      $remain = $remain * 10 + (ord($a[$i]) - 48);
      $digit = intdiv($remain, $제수);
      $remain = $remain % $제수;
      if ($out !== '' || $digit > 0) {
        $out .= (string)$digit;
      }
    }
    return $out === '' ? '0' : $out;
  }
}

if (!function_exists('아이템주식_손익표시')) {
  /** 부호 있는 손익 축약 표시 (+1.2경 / -3조) — PHP_INT_MAX(~922경) 캐스팅 금지 */
  function 아이템주식_손익표시($금액, $단위 = '냥'): string {
    $raw = trim((string)$금액);
    $neg = (isset($raw[0]) && $raw[0] === '-');
    if ($neg) {
      $raw = substr($raw, 1);
    }
    $abs = 아이템주식_냥($raw);
    if ($abs === '0') {
      return '0';
    }
    $fmt = 아이템주식_주가표시($abs, $단위);
    return ($neg ? '-' : '+') . $fmt;
  }
}

if (!function_exists('아이템주식_부호차')) {
  /**
   * value - cost (음수 허용 문자열)
   * (int) 금지 — 922경(PHP_INT_MAX) 초과 차익이 921경으로 뭉개지는 문제 방지
   */
  function 아이템주식_부호차($value, $cost): string {
    $a = 아이템주식_냥($value);
    $b = 아이템주식_냥($cost);
    if (function_exists('bcsub')) {
      return bcsub($a, $b, 0);
    }
    $cmp = 아이템주식_비교($a, $b);
    if ($cmp === 0) {
      return '0';
    }
    if ($cmp > 0) {
      return 아이템주식_차($a, $b);
    }
    $diff = 아이템주식_차($b, $a);
    return ($diff === '0') ? '0' : ('-' . $diff);
  }
}

/**
 * 내 주식 투자내역 (실제 주식형)
 * · 매입 = chat_stock 평균매수단가 × 보유
 * · 평가 = 매도 실수령(시세 − 수수료) × 보유
 * · 손익 = 평가 − 매입
 */
if (!function_exists('아이템주식_투자내역')) {
  function 아이템주식_투자내역(array $정보, $단위 = '냥'): array {
    $empty = [
      'has' => false,
      'items' => [],
      'qty_total' => 0,
      'cost' => '0',
      'cost_fmt' => 아이템주식_주가표시('0', $단위),
      'value' => '0',
      'value_fmt' => 아이템주식_주가표시('0', $단위),
      'pnl' => '0',
      'pnl_fmt' => '0',
      'pnl_pct' => null,
      'pnl_pct_fmt' => '—',
      'pnl_up' => true,
    ];
    $보유목록 = 아이템주식_보유목록($정보, $단위);
    if ($보유목록 === []) {
      return $empty;
    }

    $midx = (int)($정보['idx'] ?? 0);
    $닉 = trim((string)($정보['name'] ?? ''));
    $costMap = []; // item => ['buy_qty'=>int,'buy_paid'=>string]
    if ($닉 !== '' || $midx > 0) {
      if (!function_exists('item_trade_log_스키마보장') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
        require_once __DIR__ . '/item_trade_log.inc.php';
      }
      if (function_exists('item_trade_log_스키마보장')) {
        item_trade_log_스키마보장();
      }
      $where = ["side = 'buy'", "channel = 'chat_stock'"];
      if ($midx > 0) {
        $where[] = 'midx = ' . $midx;
      } else {
        $where[] = "nick = '" . addslashes($닉) . "'";
      }
      $rs = @db_query("
        SELECT item,
               SUM(qty) AS buy_qty,
               CAST(COALESCE(SUM(CAST(paid_total AS DECIMAL(65,0))), 0) AS CHAR) AS buy_paid
        FROM tb_item_trade_log
        WHERE " . implode(' AND ', $where) . "
        GROUP BY item
      ");
      if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
          $name = 아이템주식_은총_정규sname(trim((string)($row['item'] ?? '')));
          if ($name === '') {
            continue;
          }
          $costMap[$name] = [
            'buy_qty' => max(0, (int)($row['buy_qty'] ?? 0)),
            'buy_paid' => 아이템주식_냥($row['buy_paid'] ?? 0),
          ];
        }
      }
    }

    $items = [];
    $totalCost = '0';
    $totalValue = '0';
    $qtyTotal = 0;
    $anyKnown = false;

    foreach ($보유목록 as $it) {
      $name = trim((string)($it['name'] ?? ''));
      $qty = (int)($it['count'] ?? 0);
      if ($name === '' || $qty < 1) {
        continue;
      }
      // 기념주화(본방냥 매도)는 게임냥 투자내역에서 제외
      if (($it['pricing'] ?? '') === 'coin' || 아이템주식_기념주화인가($name)) {
        continue;
      }
      $qtyTotal += $qty;
      // 현재 시세(1주) · 전량 매도 시 수령액
      $market = 아이템주식_냥($it['sell'] ?? ($it['buy'] ?? '0'));
      if ($market === '0') {
        $market = 아이템주식_냥($it['buy'] ?? '0');
      }
      $value = 아이템주식_냥($it['sell_all'] ?? '0');
      if ($value === '0') {
        $value = 아이템주식_곱($market, (string)$qty);
      }
      $cm = $costMap[$name] ?? null;
      $buyQty = (int)($cm['buy_qty'] ?? 0);
      $buyPaid = 아이템주식_냥($cm['buy_paid'] ?? '0');
      $costKnown = ($buyQty > 0 && $buyPaid !== '0');
      $avg = '0';
      $cost = '0';
      if ($costKnown) {
        $anyKnown = true;
        $avg = 아이템주식_나누기내림($buyPaid, $buyQty);
        $cost = 아이템주식_곱($avg, (string)$qty);
      }
      // 차익 = 매도시수령 − 매입금액 (문자열 · INT_MAX 금지)
      $pnl = $costKnown ? 아이템주식_부호차($value, $cost) : '0';
      $pnlPct = null;
      $pnlPctFmt = '—';
      if ($costKnown && $cost !== '0' && function_exists('bcdiv') && function_exists('bcmul')) {
        // 비율만 float — 금액 자체는 bcmul/bcdiv 문자열
        $pnlPct = (float)bcdiv(bcmul($pnl, '100', 4), $cost, 2);
        $pnlPctFmt = sprintf('%+.2F%%', $pnlPct);
      } elseif ($costKnown && $cost !== '0') {
        // float 금액 차감 금지 — 자리수 비교로 대략 % (표시용)
        $pnlAbs = (isset($pnl[0]) && $pnl[0] === '-') ? substr($pnl, 1) : $pnl;
        $pnlAbs = 아이템주식_냥($pnlAbs);
        $sign = (isset($pnl[0]) && $pnl[0] === '-') ? -1.0 : 1.0;
        if (function_exists('bcdiv')) {
          $pnlPct = $sign * (float)bcdiv(bcmul($pnlAbs, '100', 4), $cost, 2);
        } else {
          // 상위 자리만으로 대략 비율
          $clen = strlen($cost);
          $plen = strlen($pnlAbs);
          if ($clen > 0) {
            $headC = (float)substr($cost, 0, min(12, $clen));
            $headP = (float)substr($pnlAbs, 0, min(12, $plen));
            $scale = $plen - $clen;
            $ratio = ($headC > 0) ? ($headP / $headC) : 0.0;
            if ($scale !== 0) {
              $ratio *= ($scale > 0) ? pow(10, min(8, $scale)) : (1.0 / pow(10, min(8, -$scale)));
            }
            $pnlPct = $sign * $ratio * 100.0;
          }
        }
        $pnlPctFmt = ($pnlPct === null) ? '—' : sprintf('%+.2F%%', $pnlPct);
      }

      if (function_exists('bcadd')) {
        $totalCost = bcadd($totalCost, $cost, 0);
        $totalValue = bcadd($totalValue, $value, 0);
      } elseif (function_exists('냥_금액_문자열합')) {
        $totalCost = 냥_금액_문자열합($totalCost, $cost);
        $totalValue = 냥_금액_문자열합($totalValue, $value);
      } else {
        $addStr = static function ($x, $y) {
          $x = strrev(아이템주식_냥($x));
          $y = strrev(아이템주식_냥($y));
          $max = max(strlen($x), strlen($y));
          $carry = 0;
          $out = '';
          for ($i = 0; $i < $max; $i++) {
            $s = ($i < strlen($x) ? ord($x[$i]) - 48 : 0)
              + ($i < strlen($y) ? ord($y[$i]) - 48 : 0)
              + $carry;
            $out .= chr(($s % 10) + 48);
            $carry = intdiv($s, 10);
          }
          if ($carry > 0) {
            $out .= chr($carry + 48);
          }
          return ltrim(strrev($out), '0') ?: '0';
        };
        $totalCost = $addStr($totalCost, $cost);
        $totalValue = $addStr($totalValue, $value);
      }

      $marketFmt = (string)($it['sell_fmt'] ?? $it['buy_fmt'] ?? 아이템주식_주가표시($market, $단위));
      $items[] = [
        'name' => $name,
        'qty' => $qty,
        'avg' => $avg,
        'avg_fmt' => $costKnown ? 아이템주식_주가표시($avg, $단위) : '—',
        'cost' => $cost,
        'cost_fmt' => $costKnown ? 아이템주식_주가표시($cost, $단위) : '원가미상',
        'cost_known' => $costKnown,
        'market' => $market,
        'market_fmt' => $marketFmt,
        'value' => $value,
        'value_fmt' => 아이템주식_주가표시($value, $단위),
        'sell_fmt' => (string)($it['sell_fmt'] ?? $marketFmt),
        'buy_fmt' => (string)($it['buy_fmt'] ?? $marketFmt),
        'pnl' => $pnl,
        'pnl_fmt' => $costKnown ? 아이템주식_손익표시($pnl, $단위) : '—',
        'pnl_pct' => $pnlPct,
        'pnl_pct_fmt' => $pnlPctFmt,
        'pnl_up' => !$costKnown || 아이템주식_비교($value, $cost) >= 0,
      ];
    }

    if ($items === []) {
      return $empty;
    }

    $totalPnl = $anyKnown ? 아이템주식_부호차($totalValue, $totalCost) : '0';
    $totalPct = null;
    $totalPctFmt = '—';
    if ($anyKnown && $totalCost !== '0' && function_exists('bcdiv') && function_exists('bcmul')) {
      $totalPct = (float)bcdiv(bcmul($totalPnl, '100', 4), $totalCost, 2);
      $totalPctFmt = sprintf('%+.2F%%', $totalPct);
    } elseif ($anyKnown && $totalCost !== '0') {
      $pnlAbs = (isset($totalPnl[0]) && $totalPnl[0] === '-') ? substr($totalPnl, 1) : $totalPnl;
      $pnlAbs = 아이템주식_냥($pnlAbs);
      $sign = (isset($totalPnl[0]) && $totalPnl[0] === '-') ? -1.0 : 1.0;
      $clen = strlen($totalCost);
      $plen = strlen($pnlAbs);
      if ($clen > 0) {
        $headC = (float)substr($totalCost, 0, min(12, $clen));
        $headP = (float)substr($pnlAbs, 0, min(12, $plen));
        $scale = $plen - $clen;
        $ratio = ($headC > 0) ? ($headP / $headC) : 0.0;
        if ($scale !== 0) {
          $ratio *= ($scale > 0) ? pow(10, min(8, $scale)) : (1.0 / pow(10, min(8, -$scale)));
        }
        $totalPct = $sign * $ratio * 100.0;
        $totalPctFmt = sprintf('%+.2F%%', $totalPct);
      }
    }

    return [
      'has' => true,
      'items' => $items,
      'qty_total' => $qtyTotal,
      'cost' => $totalCost,
      'cost_fmt' => $anyKnown ? 아이템주식_주가표시($totalCost, $단위) : '원가미상',
      'cost_known' => $anyKnown,
      'value' => $totalValue,
      'value_fmt' => 아이템주식_주가표시($totalValue, $단위),
      'pnl' => $totalPnl,
      'pnl_fmt' => $anyKnown ? 아이템주식_손익표시($totalPnl, $단위) : '—',
      'pnl_pct' => $totalPct,
      'pnl_pct_fmt' => $totalPctFmt,
      'pnl_up' => !$anyKnown || 아이템주식_비교($totalValue, $totalCost) >= 0,
    ];
  }
}

if (!function_exists('아이템주식_거래소_데이터')) {
  /** 웹 거래소용 시세·보유·메타 */
  function 아이템주식_거래소_데이터(array $정보, $단위 = '냥'): array {
    $닉 = trim((string)($정보['name'] ?? ''));
    if ($닉 !== '' && function_exists('아이템주식_본방결제모드_설정')) {
      아이템주식_본방결제모드_설정($닉);
    }
    $표시단위 = 아이템주식_결제단위라벨($단위);
    $quotes = 아이템주식_시세목록_회원($정보, $표시단위);
    $quotes = array_values(array_filter($quotes, static function ($q) {
      $name = trim((string)($q['name'] ?? ''));
      $disp = trim((string)($q['display_name'] ?? ''));
      if (function_exists('아이템주식_기념주화인가') && (아이템주식_기념주화인가($name) || 아이템주식_기념주화인가($disp))) {
        return false;
      }
      $blob = $name . $disp;
      if ((bool)preg_match('/꼬벙기념주화|1주년기념주화|꼬병기념주화/u', $blob)) {
        return false;
      }
      return true;
    }));
    $currentBuys = [];
    foreach ($quotes as $q) {
      $n = trim((string)($q['name'] ?? ''));
      if ($n !== '') {
        $currentBuys[$n] = (string)($q['buy'] ?? '0');
      }
    }
    $sparks = 아이템주식_스파크라인_맵(24, 40, $currentBuys);
    $lastBuys = 아이템주식_최근매수맵(array_keys($currentBuys), $표시단위);
    foreach ($quotes as &$q) {
      $n = trim((string)($q['name'] ?? ''));
      $sp = $sparks[$n] ?? ['ys' => [], 'up' => true];
      $q['spark'] = array_values($sp['ys'] ?? []);
      $q['spark_up'] = !empty($sp['up']);
      $lb = $lastBuys[$n] ?? null;
      $q['last_buy_nick'] = $lb['nick'] ?? '';
      $q['last_buy_qty'] = (int)($lb['qty'] ?? 0);
      $q['last_buy_unit_fmt'] = (string)($lb['unit_fmt'] ?? '');
      $q['last_buy_paid_fmt'] = (string)($lb['paid_fmt'] ?? '');
      if (아이템주식_본방결제모드인가()
        && (($q['pricing'] ?? '') !== 'coin')
        && (($q['pricing'] ?? '') !== 'gongkeo')) {
        $q['pay_unit'] = 'newpoint';
      }
    }
    unset($q);

    return [
      'quotes' => $quotes,
      'inventory' => 아이템주식_보유목록($정보, $표시단위),
      'portfolio' => 아이템주식_투자내역($정보, $표시단위),
      'spread_pct' => 아이템주식_스프레드율(),
      'total_nyang' => 아이템주식_냥(아이템주식_총게임냥()),
      'total_nyang_fmt' => 아이템주식_표시(아이템주식_총게임냥(), $표시단위),
      'pay_unit' => 아이템주식_본방결제모드인가() ? 'newpoint' : 'point',
      'balance_unit' => $표시단위,
      'base' => 아이템주식_기준_메타($표시단위),
      'chart_items' => array_values(array_filter(array_map(static function ($q) {
        $n = (string)($q['name'] ?? '');
        $p = (string)($q['pricing'] ?? '');
        return ($p === 'gongkeo' || $p === 'coin') ? null : $n;
      }, $quotes))),
    ];
  }
}

if (!function_exists('아이템주식_거래소_폴링데이터')) {
  /**
   * 가벼운 폴링용 — 시세·스파크·최근매수·시총 (+회원 있으면 투자내역)
   * @return array{quotes:list,spread_pct:int,total_nyang:string,total_nyang_fmt:string,portfolio?:array}
   */
  function 아이템주식_거래소_폴링데이터($단위 = '냥', $정보 = null): array {
    if (is_array($정보) && $정보 !== []) {
      $닉 = trim((string)($정보['name'] ?? ''));
      if ($닉 !== '' && function_exists('아이템주식_본방결제모드_설정')) {
        아이템주식_본방결제모드_설정($닉);
      }
    }
    $표시단위 = 아이템주식_결제단위라벨($단위);
    $quotes = (is_array($정보) && $정보 !== [])
      ? 아이템주식_시세목록_회원($정보, $표시단위)
      : 아이템주식_시세목록($표시단위);
    $currentBuys = [];
    foreach ($quotes as $q) {
      $n = trim((string)($q['name'] ?? ''));
      if ($n !== '') {
        $currentBuys[$n] = (string)($q['buy'] ?? '0');
      }
    }
    $sparks = 아이템주식_스파크라인_맵(24, 40, $currentBuys);
    $lastBuys = 아이템주식_최근매수맵(array_keys($currentBuys), $표시단위);
    foreach ($quotes as &$q) {
      $n = trim((string)($q['name'] ?? ''));
      $sp = $sparks[$n] ?? ['ys' => [], 'up' => true];
      $q['spark'] = array_values($sp['ys'] ?? []);
      $q['spark_up'] = !empty($sp['up']);
      $lb = $lastBuys[$n] ?? null;
      $q['last_buy_nick'] = $lb['nick'] ?? '';
      $q['last_buy_qty'] = (int)($lb['qty'] ?? 0);
      $q['last_buy_unit_fmt'] = (string)($lb['unit_fmt'] ?? '');
      $q['last_buy_paid_fmt'] = (string)($lb['paid_fmt'] ?? '');
      if (아이템주식_본방결제모드인가()
        && (($q['pricing'] ?? '') !== 'coin')
        && (($q['pricing'] ?? '') !== 'gongkeo')) {
        $q['pay_unit'] = 'newpoint';
      }
    }
    unset($q);
    $총 = 아이템주식_총게임냥();
    $out = [
      'quotes' => $quotes,
      'spread_pct' => 아이템주식_스프레드율(),
      'total_nyang' => 아이템주식_냥($총),
      'total_nyang_fmt' => 아이템주식_표시($총, $표시단위),
      'pay_unit' => 아이템주식_본방결제모드인가() ? 'newpoint' : 'point',
      'balance_unit' => $표시단위,
    ];
    if (is_array($정보) && (int)($정보['idx'] ?? 0) > 0) {
      $out['portfolio'] = 아이템주식_투자내역($정보, $표시단위);
    }
    return $out;
  }
}

/**
 * 아이템별 최근 주식 매수 1건
 * @param list<string> $items
 * @return array<string,array{nick:string,qty:int,unit_price:string,paid_total:string,unit_fmt:string,paid_fmt:string}>
 */
if (!function_exists('아이템주식_최근매수맵')) {
  function 아이템주식_최근매수맵(array $items, $단위 = '냥'): array {
    $out = [];
    $items = array_values(array_filter(array_map('trim', $items), static function ($v) {
      return $v !== '';
    }));
    if ($items === []) {
      return $out;
    }
    if (!function_exists('item_trade_log_스키마보장') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }
    if (function_exists('item_trade_log_스키마보장')) {
      item_trade_log_스키마보장();
    }
    $in = [];
    foreach ($items as $it) {
      $in[] = "'" . addslashes($it) . "'";
    }
    $inSql = implode(',', $in);
    $rs = @db_query("
      SELECT t.item, t.nick, t.qty,
             CAST(t.unit_price AS CHAR) AS unit_price,
             CAST(t.paid_total AS CHAR) AS paid_total
      FROM tb_item_trade_log t
      INNER JOIN (
        SELECT item, MAX(idx) AS max_idx
        FROM tb_item_trade_log
        WHERE side = 'buy'
          AND channel = 'chat_stock'
          AND item IN ({$inSql})
        GROUP BY item
      ) x ON t.idx = x.max_idx
    ");
    if ($rs) {
      while ($row = mysqli_fetch_assoc($rs)) {
        $name = trim((string)($row['item'] ?? ''));
        if ($name === '') {
          continue;
        }
        $unit = preg_replace('/\D+/', '', (string)($row['unit_price'] ?? '0')) ?: '0';
        $paid = preg_replace('/\D+/', '', (string)($row['paid_total'] ?? '0')) ?: '0';
        $out[$name] = [
          'nick' => trim((string)($row['nick'] ?? '')),
          'qty' => max(0, (int)($row['qty'] ?? 0)),
          'unit_price' => $unit,
          'paid_total' => $paid,
          'unit_fmt' => 아이템주식_주가표시($unit, $단위),
          'paid_fmt' => 아이템주식_주가표시($paid, $단위),
        ];
      }
    }
    return $out;
  }
}

/**
 * 리스트용 미니차트 — 최근 N시간 매수가 상대값(0~100)
 * @param array<string,string> $currentBuys item => buy 문자열
 * @return array<string,array{ys:list<float>,up:bool}>
 */
if (!function_exists('아이템주식_스파크라인_맵')) {
  function 아이템주식_스파크라인_맵(int $hours = 24, int $maxPts = 40, array $currentBuys = []): array {
    $hours = max(1, min(168, $hours));
    $maxPts = max(4, min(80, $maxPts));
    아이템주식_틱_스키마보장();

    $since = date('Y-m-d H:i:s', time() - $hours * 3600);
    $since_esc = addslashes($since);
    $byItem = [];
    $itemRowCache = [];
    $cfg = 아이템주식_기준설정_로드();
    $reprice = !empty($cfg['supply_curve']);
    $rs = @db_query("
      SELECT item,
             CAST(buy_price AS CHAR) AS buy_price,
             CAST(IFNULL(total_nyang, 0) AS CHAR) AS total_nyang,
             IFNULL(circulating, 0) AS circulating,
             reason,
             regdate
      FROM `tb_item_stock_tick`
      WHERE regdate >= '{$since_esc}'
        AND buy_price > 0
      ORDER BY item ASC, regdate ASC, idx ASC
      LIMIT 4000
    ");
    if ($rs) {
      while ($row = mysqli_fetch_assoc($rs)) {
        $name = 아이템주식_은총_정규sname(trim((string)($row['item'] ?? '')));
        if ($name === '') {
          continue;
        }
        $buy = 아이템주식_냥($row['buy_price'] ?? 0);
        $reason = (string)($row['reason'] ?? '');
        if ($reprice) {
          if (!isset($itemRowCache[$name])) {
            $itemRowCache[$name] = 아이템주식_행조회($name);
          }
          $ir = $itemRowCache[$name];
          if (!empty($ir)) {
            $restored = 아이템주식_틱가격_복원(
              $ir,
              $buy,
              $buy,
              (int)($row['circulating'] ?? 0),
              $row['total_nyang'] ?? '0',
              $reason
            );
            $buy = $restored['buy'];
          }
        }
        if ($buy === '0') {
          continue;
        }
        if (!isset($byItem[$name])) {
          $byItem[$name] = [];
        }
        $byItem[$name][] = [
          'buy' => $buy,
          'circulating' => (int)($row['circulating'] ?? 0),
          'total_nyang' => 아이템주식_냥($row['total_nyang'] ?? '0'),
        ];
      }
    }

    foreach ($currentBuys as $name => $buy) {
      $name = trim((string)$name);
      $buy = 아이템주식_냥($buy);
      if ($name === '' || $buy === '0') {
        continue;
      }
      if (!isset($byItem[$name])) {
        $byItem[$name] = [];
      }
      $last = $byItem[$name] !== [] ? $byItem[$name][count($byItem[$name]) - 1] : null;
      $lastBuy = is_array($last) ? (string)($last['buy'] ?? '0') : null;
      if ($lastBuy === null || 아이템주식_비교($lastBuy, $buy) !== 0) {
        $byItem[$name][] = [
          'buy' => $buy,
          'circulating' => 아이템주식_유통량($name),
          'total_nyang' => 아이템주식_냥(아이템주식_총게임냥()),
        ];
      }
    }

    $out = [];
    foreach ($byItem as $name => $rows) {
      $n = count($rows);
      if ($n < 1) {
        continue;
      }
      // 다운샘플
      if ($n > $maxPts) {
        $sampled = [];
        $lastIdx = $n - 1;
        for ($i = 0; $i < $maxPts; $i++) {
          $idx = (int)round($i * $lastIdx / ($maxPts - 1));
          $sampled[] = $rows[$idx];
        }
        $rows = $sampled;
        $n = count($rows);
      }

      $ir = $itemRowCache[$name] ?? 아이템주식_행조회($name);
      $scaled = 아이템주식_차트_yscale적용($rows, is_array($ir) ? $ir : null);
      $ys = [];
      foreach ($scaled['points'] as $p) {
        $ys[] = (float)($p['y'] ?? 50.0);
      }
      $prices = array_map(static function ($r) {
        return (string)($r['buy'] ?? '0');
      }, $rows);
      $up = true;
      if ($n >= 2) {
        $up = 아이템주식_비교($prices[$n - 1], $prices[0]) >= 0;
      }
      // 가격이 평평하고 유통량으로 스케일한 경우 상승 여부는 유통량 기준
      if (!empty($scaled['scale']) && $scaled['scale'] === 'circulating' && $n >= 2) {
        $up = ((int)($rows[$n - 1]['circulating'] ?? 0)) >= ((int)($rows[0]['circulating'] ?? 0));
      }
      $out[$name] = ['ys' => $ys, 'up' => $up];
    }
    return $out;
  }
}

/** 시세 틱(차트용) 테이블 */
if (!function_exists('아이템주식_틱_스키마보장')) {
  function 아이템주식_틱_스키마보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS `tb_item_stock_tick` (
        `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `item` VARCHAR(64) NOT NULL DEFAULT '',
        `buy_price` DECIMAL(65,0) NOT NULL DEFAULT 0,
        `sell_price` DECIMAL(65,0) NOT NULL DEFAULT 0,
        `circulating` INT UNSIGNED NOT NULL DEFAULT 0,
        `total_nyang` DECIMAL(65,0) NOT NULL DEFAULT 0,
        `reason` VARCHAR(16) NOT NULL DEFAULT 'snap',
        `regdate` DATETIME NOT NULL,
        PRIMARY KEY (`idx`),
        KEY `ix_item_reg` (`item`, `regdate`),
        KEY `ix_reg` (`regdate`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists('아이템주식_매수단가_원시')) {
  /**
   * 차트 y용 — 억절사 전 원가(유통곡선·배수 반영)
   * 실제 거래 단가와 다를 수 있음(표시·체결은 억절사 단가 유지)
   */
  function 아이템주식_매수단가_원시(array $itemRow, $총게임냥 = null, $circulating = null): string {
    $name = 아이템주식_은총_정규sname(trim((string)($itemRow['sname'] ?? '')));
    $cfg = 아이템주식_기준설정_로드();
    $pm = 아이템주식_전체배수($cfg);
    $curve = !empty($cfg['supply_curve']);
    if ($circulating === null && $curve && $name !== '') {
      $circulating = 아이템주식_유통량($name);
    }
    $circulating = max(0, (int)($circulating ?? 0));

    if (아이템주식_은총시세인가($name) || $name === '은총') {
      $원가 = 아이템주식_은총단가_로드();
      if ($원가 === '0') {
        return '0';
      }
      if ($curve) {
        $원가 = 아이템주식_소수배곱($원가, 아이템주식_공급배수($circulating, $cfg));
      }
      if ((float)$pm !== 1.0) {
        $원가 = 아이템주식_소수배곱($원가, $pm);
      }
      return 아이템주식_냥($원가);
    }

    if ($총게임냥 === null) {
      $총게임냥 = 아이템주식_총게임냥();
    }
    $원가 = 아이템주식_기본원가($itemRow, $총게임냥);
    if ($원가 === '0') {
      return '0';
    }
    if ($curve) {
      $원가 = 아이템주식_소수배곱($원가, 아이템주식_공급배수($circulating, $cfg));
    }
    if ((float)$pm !== 1.0) {
      $원가 = 아이템주식_소수배곱($원가, $pm);
    }
    return 아이템주식_냥($원가);
  }
}

if (!function_exists('아이템주식_틱가격_복원')) {
  /**
   * 유통량곡선 ON이면 틱의 circulating·total_nyang으로 시세 재계산
   * 거래 틱(buy/sell…)은 저장된 체결가를 우선 — circ=0 과거틱이 전부 같은 공식가로 덮이는 것 방지
   * @return array{buy:string,sell:string}
   */
  function 아이템주식_틱가격_복원(array $itemRow, $buyStored, $sellStored, $circ, $totalNyang = null, string $reason = ''): array {
    $buyStored = 아이템주식_냥($buyStored);
    $sellStored = 아이템주식_냥($sellStored);
    $cfg = 아이템주식_기준설정_로드();
    $name = 아이템주식_은총_정규sname(trim((string)($itemRow['sname'] ?? '')));
    // 곡선OFF: 저장된 틱 가격 그대로
    if (empty($cfg['supply_curve'])) {
      return ['buy' => $buyStored, 'sell' => $sellStored !== '0' ? $sellStored : $buyStored];
    }
    $reason = strtolower(trim($reason));
    $trustStored = in_array($reason, [
      'buy', 'buy_pre', 'sell', 'sell_pre', 'trade', 'trade_log',
    ], true);
    // 거래 틱은 당시 체결가 유지 (억절사 반영된 실제 시세)
    if ($trustStored && $buyStored !== '0') {
      return ['buy' => $buyStored, 'sell' => $sellStored !== '0' ? $sellStored : $buyStored];
    }
    $총 = 아이템주식_냥($totalNyang ?? '0');
    if ($총 === '0') {
      $총 = 아이템주식_냥(아이템주식_총게임냥());
    }
    $buy = 아이템주식_매수단가($itemRow, $총, max(0, (int)$circ));
    if ($buy === '0') {
      return ['buy' => $buyStored, 'sell' => $sellStored !== '0' ? $sellStored : $buyStored];
    }
    $sell = 아이템주식_매도단가($buy, 아이템주식_아이템스프레드율($name));
    return ['buy' => $buy, 'sell' => $sell];
  }
}

if (!function_exists('아이템주식_차트_y비율')) {
  /**
   * 차트용 0~100 비율 — bcmath 없어도 동작 (표시 전용, 금액 연산 아님)
   */
  function 아이템주식_차트_y비율(string $v, string $min, string $max): float {
    $v = 아이템주식_냥($v);
    $min = 아이템주식_냥($min);
    $max = 아이템주식_냥($max);
    if (아이템주식_비교($max, $min) <= 0) {
      return 50.0;
    }
    if (function_exists('bcsub') && function_exists('bcdiv') && function_exists('bcmul')) {
      $range = bcsub($max, $min, 0);
      if ($range === '0') {
        return 50.0;
      }
      $diff = bcsub($v, $min, 0);
      return (float)bcmul(bcdiv($diff, $range, 8), '100', 4);
    }
    // float 안전 구간만 사용, 아니면 자릿수·접두 근사
    if (strlen($max) <= 14 && strlen($min) <= 14 && strlen($v) <= 14) {
      $mn = (float)$min;
      $mx = (float)$max;
      $vv = (float)$v;
      if (is_finite($mn) && is_finite($mx) && is_finite($vv) && $mx > $mn) {
        return (($vv - $mn) / ($mx - $mn)) * 100.0;
      }
    }
    $la = strlen($min);
    $lb = strlen($max);
    $lv = strlen($v);
    if ($lb !== $la) {
      return (($lv - $la) / max(1, $lb - $la)) * 100.0;
    }
    // 동일 자릿수: 공통 접두 이후  Lexicographic
    $n = $la;
    $i = 0;
    while ($i < $n && $min[$i] === $max[$i]) {
      $i++;
    }
    if ($i >= $n) {
      return 50.0;
    }
    $dMin = (int)$min[$i];
    $dMax = (int)$max[$i];
    $dV = (int)$v[$i];
    if ($dMax === $dMin) {
      return 50.0;
    }
    return (($dV - $dMin) / ($dMax - $dMin)) * 100.0;
  }
}

if (!function_exists('아이템주식_차트_yscale적용')) {
  /**
   * points[]에 y(0~100) 부여. 매수가가 평평해도 유통량·원가·시총으로 선을 살림
   * @param list<array> $points
   * @return array{points:list,flat:bool,flat_reason:string,min:string,max:string,scale?:string}
   */
  function 아이템주식_차트_yscale적용(array $points, $itemRow = null): array {
    $cfg = 아이템주식_기준설정_로드();
    $curve = !empty($cfg['supply_curve']);

    $buyMinMax = static function (array $pts): array {
      $min = null;
      $max = '0';
      foreach ($pts as $p) {
        $b = 아이템주식_냥($p['buy'] ?? '0');
        if ($b === '0') {
          continue;
        }
        if ($min === null || 아이템주식_비교($b, $min) < 0) {
          $min = $b;
        }
        if (아이템주식_비교($b, $max) > 0) {
          $max = $b;
        }
      }
      return [$min ?? '0', $max];
    };

    $applySeries = static function (array $pts, array $series) {
      $min = null;
      $max = '0';
      foreach ($series as $v) {
        $v = 아이템주식_냥($v);
        if ($min === null || 아이템주식_비교($v, $min) < 0) {
          $min = $v;
        }
        if (아이템주식_비교($v, $max) > 0) {
          $max = $v;
        }
      }
      if ($min === null) {
        $min = '0';
      }
      $flat = (아이템주식_비교($max, $min) === 0);
      foreach ($pts as $i => &$p) {
        $p['y'] = $flat ? 50.0 : 아이템주식_차트_y비율((string)($series[$i] ?? '0'), $min, $max);
      }
      unset($p);
      return [$pts, $flat, $min, $max];
    };

    // 1) 유통량 — 곡선 ON/OFF 무관 (매수 전·후 circ가 다르면 선이 움직여야 함)
    $circSeries = [];
    $circMin = null;
    $circMax = null;
    foreach ($points as $p) {
      $c = (string)max(0, (int)($p['circulating'] ?? 0));
      $circSeries[] = $c;
      if ($circMin === null || 아이템주식_비교($c, $circMin) < 0) {
        $circMin = $c;
      }
      if ($circMax === null || 아이템주식_비교($c, $circMax) > 0) {
        $circMax = $c;
      }
    }
    if ($circMin !== null && $circMax !== null && 아이템주식_비교($circMax, $circMin) > 0) {
      [$points] = $applySeries($points, $circSeries);
      [$min, $max] = $buyMinMax($points);
      return [
        'points' => $points,
        'flat' => false,
        'flat_reason' => '',
        'min' => $min,
        'max' => $max,
        'scale' => 'circulating',
      ];
    }

    // 2) 곡선 ON: 억절사 전 원가
    if ($curve && is_array($itemRow) && $itemRow !== []) {
      $rawSeries = [];
      $any = false;
      foreach ($points as $p) {
        $총 = 아이템주식_냥($p['total_nyang'] ?? '0');
        if ($총 === '0') {
          $총 = 아이템주식_냥(아이템주식_총게임냥());
        }
        $raw = 아이템주식_매수단가_원시($itemRow, $총, (int)($p['circulating'] ?? 0));
        if ($raw !== '0') {
          $any = true;
        }
        $rawSeries[] = $raw;
      }
      if ($any) {
        [$points, $flatRaw, $minR, $maxR] = $applySeries($points, $rawSeries);
        if (!$flatRaw) {
          return [
            'points' => $points,
            'flat' => false,
            'flat_reason' => '',
            'min' => $minR,
            'max' => $maxR,
            'scale' => 'raw',
          ];
        }
      }
    }

    // 3) 표시 매수가
    $series = [];
    foreach ($points as $p) {
      $series[] = (string)($p['buy'] ?? '0');
    }
    [$points, $flat, $min, $max] = $applySeries($points, $series);
    if (!$flat) {
      return ['points' => $points, 'flat' => false, 'flat_reason' => '', 'min' => $min, 'max' => $max, 'scale' => 'buy'];
    }

    // 4) 시총
    $totSeries = [];
    $totVary = false;
    $t0 = null;
    foreach ($points as $p) {
      $t = 아이템주식_냥($p['total_nyang'] ?? '0');
      $totSeries[] = $t;
      if ($t0 === null) {
        $t0 = $t;
      } elseif ($t !== '0' && 아이템주식_비교($t, $t0) !== 0) {
        $totVary = true;
      }
    }
    if ($totVary) {
      [$points] = $applySeries($points, $totSeries);
      return [
        'points' => $points,
        'flat' => false,
        'flat_reason' => '',
        'min' => $min,
        'max' => $max,
        'scale' => 'total_nyang',
      ];
    }

    // 5) 매수/매도 틱 reason 순서 (circ·가격이  alike 찍혀도 전·후 점은 움직이게)
    $reasonSeries = [];
    $rVary = false;
    $ri = 0;
    foreach ($points as $p) {
      $reason = strtolower((string)($p['reason'] ?? ''));
      if (in_array($reason, ['buy_pre', 'sell_pre'], true)) {
        $reasonSeries[] = (string)$ri;
        $ri++;
        $rVary = true;
      } elseif (in_array($reason, ['buy', 'sell'], true)) {
        $reasonSeries[] = (string)$ri;
        $ri++;
        $rVary = true;
      } else {
        $reasonSeries[] = (string)max(0, $ri - 1);
      }
    }
    if ($rVary && $ri >= 2) {
      [$points] = $applySeries($points, $reasonSeries);
      return [
        'points' => $points,
        'flat' => false,
        'flat_reason' => '',
        'min' => $min,
        'max' => $max,
        'scale' => 'trade_seq',
      ];
    }

    $reason = $curve
      ? '구간 내 유통량·시세 변화가 없습니다. 매수/매도 후 차트에서 해당 종목을 불러오세요.'
      : '유통량곡선이 적용되지 않았거나(체크 후 적용 필요), 시세가 구간 내 동일합니다.';
    return [
      'points' => $points,
      'flat' => true,
      'flat_reason' => $reason,
      'min' => $min,
      'max' => $max,
    ];
  }
}

if (!function_exists('아이템주식_틱_기록_값')) {
  /** 계산된 시세를 그대로 틱 저장 (매수 전/후 단가 고정용) */
  function 아이템주식_틱_기록_값(string $item, $buy, $sell, int $circ, string $reason = 'trade', $totalNyang = null): void {
    $item = 아이템주식_은총_정규sname(trim($item));
    if ($item === '') {
      return;
    }
    아이템주식_틱_스키마보장();
    $buy = 아이템주식_냥($buy);
    $sell = 아이템주식_냥($sell);
    if ($buy === '0' && $sell === '0') {
      return;
    }
    $total = 아이템주식_냥($totalNyang ?? 아이템주식_총게임냥());
    $item_esc = addslashes($item);
    $buy_sql = preg_replace('/[^\d]/', '', (string)$buy) ?: '0';
    $sell_sql = preg_replace('/[^\d]/', '', (string)$sell) ?: '0';
    $total_sql = preg_replace('/[^\d]/', '', (string)$total) ?: '0';
    $circ = max(0, $circ);
    $reason_esc = addslashes(substr($reason, 0, 16));
    @db_query("
      INSERT INTO `tb_item_stock_tick`
        (`item`, `buy_price`, `sell_price`, `circulating`, `total_nyang`, `reason`, `regdate`)
      VALUES
        ('{$item_esc}', {$buy_sql}, {$sell_sql}, {$circ}, {$total_sql}, '{$reason_esc}', NOW())
    ");
  }
}

if (!function_exists('아이템주식_틱_기록_아이템')) {
  function 아이템주식_틱_기록_아이템(string $item, string $reason = 'trade'): void {
    $item = 아이템주식_은총_정규sname(trim($item));
    if ($item === '') {
      return;
    }
    $row = 아이템주식_행조회($item);
    if (empty($row) || !아이템주식_대상여부($row)) {
      return;
    }
    $circ = 아이템주식_유통량($item);
    $buy = 아이템주식_매수단가($row, null, $circ);
    $sell = 아이템주식_매도단가($buy, 아이템주식_아이템스프레드율($item));
    아이템주식_틱_기록_값($item, $buy, $sell, $circ, $reason);
  }
}

if (!function_exists('아이템주식_틱_스냅샷_전부')) {
  /** 전체 시세 아이템 틱 기록 — minSec 이내 중복 스냅샷 생략 */
  function 아이템주식_틱_스냅샷_전부(int $minSec = 180): int {
    아이템주식_틱_스키마보장();
    $목록 = 아이템주식_시세목록('냥');
    $n = 0;
    foreach ($목록 as $q) {
      $name = trim((string)($q['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $esc = addslashes($name);
      $최근 = @db_select("
        SELECT regdate FROM `tb_item_stock_tick`
        WHERE item = '{$esc}' AND reason = 'snap'
        ORDER BY regdate DESC LIMIT 1
      ");
      if (!empty($최근['regdate'])) {
        $ts = strtotime((string)$최근['regdate']);
        if ($ts !== false && (time() - $ts) < $minSec) {
          continue;
        }
      }
      아이템주식_틱_기록_아이템($name, 'snap');
      $n++;
    }
    return $n;
  }
}

/**
 * 아이템 차트 시계열
 * @return array{ok:bool,item:string,points:array,trades:array,msg?:string}
 */
if (!function_exists('아이템주식_차트데이터')) {
  function 아이템주식_차트데이터(string $item, int $hours = 72, $단위 = '냥'): array {
    $item = 아이템주식_은총_정규sname(trim($item));
    $hours = max(1, min(720, $hours));
    if ($item === '') {
      return ['ok' => false, 'item' => '', 'points' => [], 'trades' => [], 'msg' => '아이템 없음'];
    }
    아이템주식_틱_스키마보장();
    // 현재가 한 점 확보
    아이템주식_틱_기록_아이템($item, 'view');

    $esc = addslashes($item);
    $since = date('Y-m-d H:i:s', time() - $hours * 3600);
    $since_esc = addslashes($since);

    $itemRow = 아이템주식_행조회($item);
    $points = [];
    $rs = @db_query("
      SELECT
        CAST(buy_price AS CHAR) AS buy_price,
        CAST(sell_price AS CHAR) AS sell_price,
        CAST(IFNULL(total_nyang, 0) AS CHAR) AS total_nyang,
        circulating,
        reason,
        regdate
      FROM `tb_item_stock_tick`
      WHERE item = '{$esc}' AND regdate >= '{$since_esc}'
      ORDER BY regdate ASC, idx ASC
      LIMIT 800
    ");
    if ($rs) {
      while ($row = mysqli_fetch_assoc($rs)) {
        $buy = 아이템주식_냥($row['buy_price'] ?? 0);
        $sell = 아이템주식_냥($row['sell_price'] ?? 0);
        $circ = (int)($row['circulating'] ?? 0);
        $reason = (string)($row['reason'] ?? '');
        $totalNyang = 아이템주식_냥($row['total_nyang'] ?? '0');
        if (!empty($itemRow)) {
          $restored = 아이템주식_틱가격_복원(
            $itemRow,
            $buy,
            $sell,
            $circ,
            $totalNyang,
            $reason
          );
          $buy = $restored['buy'];
          $sell = $restored['sell'];
        }
        $points[] = [
          't' => (string)($row['regdate'] ?? ''),
          'ts' => strtotime((string)($row['regdate'] ?? '')) ?: 0,
          'buy' => $buy,
          'sell' => $sell,
          'buy_fmt' => 아이템주식_표시($buy, $단위),
          'sell_fmt' => 아이템주식_표시($sell, $단위),
          'circulating' => $circ,
          'total_nyang' => $totalNyang,
          'reason' => $reason,
        ];
      }
    }

    // 거래 로그 보강 (틱이 적을 때)
    if (!function_exists('item_trade_log_스키마보장') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
      require_once __DIR__ . '/item_trade_log.inc.php';
    }
    if (function_exists('item_trade_log_스키마보장')) {
      item_trade_log_스키마보장();
    }
    $trades = [];
    $trs = @db_query("
      SELECT side, qty,
             CAST(unit_price AS CHAR) AS unit_price,
             CAST(paid_total AS CHAR) AS paid_total,
             regdate
      FROM tb_item_trade_log
      WHERE item = '{$esc}'
        AND channel = 'chat_stock'
        AND regdate >= '{$since_esc}'
      ORDER BY regdate ASC
      LIMIT 400
    ");
    if ($trs) {
      while ($row = mysqli_fetch_assoc($trs)) {
        $unit = 아이템주식_냥($row['unit_price'] ?? 0);
        $trades[] = [
          't' => (string)($row['regdate'] ?? ''),
          'ts' => strtotime((string)($row['regdate'] ?? '')) ?: 0,
          'side' => (string)($row['side'] ?? ''),
          'qty' => (int)($row['qty'] ?? 0),
          'unit' => $unit,
          'unit_fmt' => 아이템주식_표시($unit, $단위),
        ];
        // 거래 단가는 항상 차트에 합류 (틱이 평평해도 매매 흔적·단가 변화 표시)
        if ($unit !== '0') {
          $points[] = [
            't' => (string)($row['regdate'] ?? ''),
            'ts' => strtotime((string)($row['regdate'] ?? '')) ?: 0,
            'buy' => $unit,
            'sell' => $unit,
            'buy_fmt' => 아이템주식_표시($unit, $단위),
            'sell_fmt' => 아이템주식_표시($unit, $단위),
            'circulating' => 0,
            'total_nyang' => '0',
            'reason' => 'trade_log',
          ];
        }
      }
    }

    usort($points, static function ($a, $b) {
      return ($a['ts'] <=> $b['ts']);
    });

    $nowRow = 아이템주식_행조회($item);
    $nowBuy = (!empty($nowRow) && 아이템주식_대상여부($nowRow))
      ? 아이템주식_매수단가($nowRow, null, 아이템주식_유통량($item))
      : '0';
    $nowSell = 아이템주식_매도단가($nowBuy, 아이템주식_아이템스프레드율($item));
    $nowCirc = 아이템주식_유통량($item);
    $nowTotal = 아이템주식_냥(아이템주식_총게임냥());

    // 틱이 비어 있거나 SELECT 레이스가 나도 현재가로 끝점 보장
    if ($nowBuy !== '0') {
      $needNow = ($points === []);
      if (!$needNow) {
        $last = $points[count($points) - 1];
        $lastBuy = (string)($last['buy'] ?? '0');
        $age = time() - (int)($last['ts'] ?? 0);
        if ($age > 60 || 아이템주식_비교($lastBuy, $nowBuy) !== 0) {
          $needNow = true;
        }
      }
      if ($needNow) {
        $points[] = [
          't' => date('Y-m-d H:i:s'),
          'ts' => time(),
          'buy' => $nowBuy,
          'sell' => $nowSell,
          'buy_fmt' => 아이템주식_표시($nowBuy, $단위),
          'sell_fmt' => 아이템주식_표시($nowSell, $단위),
          'circulating' => $nowCirc,
          'total_nyang' => $nowTotal,
          'reason' => 'now',
        ];
      }
    }

    $scaled = 아이템주식_차트_yscale적용($points, $itemRow ?: $nowRow);
    $points = $scaled['points'];
    $min = $scaled['min'] ?? '0';
    $max = $scaled['max'] ?? '0';

    return [
      'ok' => true,
      'item' => $item,
      'hours' => $hours,
      'points' => $points,
      'trades' => $trades,
      'flat' => !empty($scaled['flat']),
      'flat_reason' => (string)($scaled['flat_reason'] ?? ''),
      'scale' => (string)($scaled['scale'] ?? 'buy'),
      'min_fmt' => 아이템주식_표시($min, $단위),
      'max_fmt' => 아이템주식_표시($max, $단위),
      'now_buy_fmt' => 아이템주식_표시($nowBuy, $단위),
      'now_sell_fmt' => 아이템주식_표시($nowSell, $단위),
    ];
  }
}

if (!function_exists('아이템주식_본방요청인가')) {
  /** 본방(info1) 웹훅에서 호출 중인지 */
  function 아이템주식_본방요청인가(): bool {
    $script = (string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    return (bool)preg_match('/info1\.php$/i', str_replace('\\', '/', $script));
  }
}

if (!function_exists('아이템주식_거래내역_응답')) {
  /**
   * 매수·매도 결과 응답
   * 본방(info1)에서 성공 시 → 홍보방(info2) 알림 큐로만 내역 전송
   * 실패·시세·사용법 등은 현재 방에 그대로
   */
  function 아이템주식_거래내역_응답(string $msg, bool $ok): void {
    $msg = trim($msg);
    if ($msg === '') {
      $msg = $ok ? '✅ 거래 완료' : '❌ 거래 실패';
    }
    if ($ok && 아이템주식_본방요청인가() && function_exists('info2알림_등록')) {
      info2알림_등록($msg, 'item_stock');
      echo 전송("✅ 거래 완료 · 내역은 홍보방에 올렸어요.");
      return;
    }
    echo 전송($msg);
  }
}

if (!function_exists('아이템주식_채팅명령_시도')) {
  /**
   * .주가 / .주식 / .구매(시세·매수) / .매수 / .매도 처리.
   * 해당 명령이면 true (호출부에서 exit).
   * .구매 아이템이 주식 대상이 아니면 false → 은총1(본방) 등 기존 로직으로.
   */
  function 아이템주식_채팅명령_시도($status, $두자리닉넴, array $정보, $단위 = '냥'): bool {
    $st = trim((string)$status);
    if ($st === '') {
      return false;
    }

    // .주가 / .주식 / .구매 (시세표)
    if (
      preg_match('/^\.주가\s*$/u', $st)
      || preg_match('/^\.주식\s*$/u', $st)
      || preg_match('/^\.구매\s*$/u', $st)
    ) {
      echo 전송(아이템주식_시세문구($단위, $정보));
      return true;
    }

    // 관리자 대리: .매수 피치 공커구매권 1  → 피치 냥 차감 · 피치 커플 기간연장
    if (preg_match('/^\.매수\s+(\S+)\s+(\S+)(?:\s+(\d+))?$/u', $st, $mProxy)) {
      $토큰1 = trim($mProxy[1]);
      $토큰2 = trim($mProxy[2]);
      $수량Proxy = isset($mProxy[3]) ? max(1, (int)$mProxy[3]) : 1;
      $아이템후보 = 아이템주식_공커대실권_정규sname($토큰2);
      if (아이템주식_공커대실권인가($아이템후보)) {
        if (!아이템주식_관리자여부((string)$두자리닉넴)) {
          echo 전송('❌ 관리자만 다른 닉 공커대실권(공커구매권)을 대리매수할 수 있어요.');
          return true;
        }
        $대상닉 = function_exists('getTwoCharNick') ? getTwoCharNick($토큰1) : $토큰1;
        if ($대상닉 === '') {
          $대상닉 = $토큰1;
        }
        $대상정보 = 아이템주식_회원정보_닉($대상닉);
        if ($대상정보 === null) {
          echo 전송("❌ [ {$대상닉} ] 회원을 찾을 수 없어요.\n예) .매수 피치 공커구매권 1");
          return true;
        }
        $결과 = 아이템주식_매수_실행(
          trim((string)($대상정보['name'] ?? $대상닉)),
          $대상정보,
          '공커대실권',
          $수량Proxy
        );
        $msg = (string)($결과['msg'] ?? '❌ 매수 실패');
        if (!empty($결과['ok'])) {
          $msg .= "\n(관리자 {$두자리닉넴} 대리매수)";
        }
        아이템주식_거래내역_응답($msg, !empty($결과['ok']));
        return true;
      }
      // 토큰2가 수량이면 기존 ".매수 아이템 수량" 으로 통과
    }

    // .매수 아이템 [수량]
    if (preg_match('/^\.매수\s+([^\s]+)(?:\s+(\d+))?$/u', $st, $m)) {
      $결과 = 아이템주식_매수_실행($두자리닉넴, $정보, trim($m[1]), isset($m[2]) ? (int)$m[2] : 1);
      아이템주식_거래내역_응답((string)($결과['msg'] ?? '❌ 매수 실패'), !empty($결과['ok']));
      return true;
    }
    if (preg_match('/^\.매수\s*$/u', $st)) {
      echo 전송("❌ 사용법: .매수 아이템이름 [수량]\n관리자: .매수 닉 공커구매권 [수량]\n예) .매수 강일 5\n예) .매수 피치 공커구매권 1\n시세: .구매 / .주가");
      return true;
    }

    // .구매 아이템명 → 단가 조회 (수량 없으면 구매하지 않음)
    if (preg_match('/^\.구매\s+([^\s]+)\s*$/u', $st, $m)) {
      $아이템명 = trim($m[1]);
      if (아이템주식_행조회($아이템명) === null) {
        return false;
      }
      echo 전송(아이템주식_단품시세_문구($아이템명, $단위, $정보));
      return true;
    }

    // .구매 아이템명 수량 → 매수 (수량 필수)
    if (preg_match('/^\.구매\s+([^\s]+)\s+(\d+)\s*$/u', $st, $m)) {
      $아이템명 = trim($m[1]);
      if (아이템주식_행조회($아이템명) === null) {
        return false;
      }
      $수량 = max(1, (int)$m[2]);
      $결과 = 아이템주식_매수_실행($두자리닉넴, $정보, $아이템명, $수량);
      아이템주식_거래내역_응답((string)($결과['msg'] ?? '❌ 매수 실패'), !empty($결과['ok']));
      return true;
    }

    // .매도 아이템 [수량]
    if (preg_match('/^\.매도\s+([^\s]+)(?:\s+(\d+))?$/u', $st, $m)) {
      $결과 = 아이템주식_매도_실행($두자리닉넴, $정보, trim($m[1]), isset($m[2]) ? (int)$m[2] : 1);
      아이템주식_거래내역_응답((string)($결과['msg'] ?? '❌ 매도 실패'), !empty($결과['ok']));
      return true;
    }
    if (preg_match('/^\.매도\s*$/u', $st)) {
      echo 전송("❌ 사용법: .매도 아이템이름 [수량]\n예) .매도 강일\n예) .매도 강일 3\n시세: .구매 / .주가");
      return true;
    }

    return false;
  }
}
