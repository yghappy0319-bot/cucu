<?php
/**
 * 홀짝 도전 공통 제한 (홍보방·매추얼·웹)
 */

if (!function_exists('홀짝_배팅_티어표')) {
  /**
   * (하위호환) 구간 cap 표 — 최소 배팅은 홀짝_기본배팅(보유 1%) 사용
   */
  function 홀짝_배팅_티어표() {
    return array(
      array('cap' => 100000000, 'min' => 100000),
      array('cap' => 1000000000000000, 'min' => 1000000),
      array('cap' => 10000000000000000, 'min' => 100000000),
      array('cap' => 500000000000000000, 'min' => 1000000000000),
      array('cap' => PHP_INT_MAX, 'min' => 10000000000000),
    );
  }
}

if (!function_exists('홀짝_배팅_퀵_비율')) {
  /**
   * 깎기 매칭용 전체 후보 비율(%) — UI 표시는 홀짝_배팅_퀵_비율_무기강화()
   * @return float[]
   */
  function 홀짝_배팅_퀵_비율() {
    return array(1, 3, 5, 100);
  }
}

if (!function_exists('홀짝_배팅_고정퀵금액')) {
  /** @deprecated 10만 고정 퀵 폐지 — 빈 문자열 */
  function 홀짝_배팅_고정퀵금액(): string {
    return '';
  }
}

if (!function_exists('홀짝_고정퀵배팅인가')) {
  /** @deprecated 10만 고정 퀵 폐지 — 항상 false */
  function 홀짝_고정퀵배팅인가($배팅): bool {
    return false;
  }
}

if (!function_exists('홀짝_배팅_퀵_비율_무기강화')) {
  /**
   * 무기 강화 구간별 표시 % 버튼
   * 0~20 → 1% · 21~40 → 1·3% · 41+ → 1·3·5%
   * @return float[]
   */
  function 홀짝_배팅_퀵_비율_무기강화($enhance): array {
    $e = (int)$enhance;
    if ($e >= 41) {
      return array(1, 3, 5);
    }
    if ($e >= 21) {
      return array(1, 3);
    }
    return array(1);
  }
}

if (!function_exists('홀짝_무기타입_로드')) {
  /** weapon_type / enhance_renewal 로드 (홀짝 올인 종류판정용) */
  function 홀짝_무기타입_로드(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $wt = __DIR__ . '/weapon_type.inc.php';
    if (is_file($wt)) {
      require_once $wt;
    }
    $renewal = __DIR__ . '/../enhance_renewal.inc.php';
    if (is_file($renewal)) {
      require_once $renewal;
    }
  }
}

if (!function_exists('홀짝_무기종류매칭_SQL')) {
  /**
   * 종류별(활1·단소2·마법3) 회원 매칭 — item 표시명 완전일치 금지
   * 단소는 젓가락/리코더/단소/피리/대금/플루트… 전부 같은 종류
   */
  function 홀짝_무기종류매칭_SQL(int $타입): string {
    $타입 = (int)$타입;
    if ($타입 === 1) {
      return "(IFNULL(무기타입,0) = 1 OR TRIM(COALESCE(item,'')) IN ('🏹활','🏹 활'))";
    }
    if ($타입 === 3) {
      return "(IFNULL(무기타입,0) = 3 OR TRIM(COALESCE(item,'')) IN ('🪄마법','🪄 마법'))";
    }
    if ($타입 === 2) {
      홀짝_무기타입_로드();
      $단소item = function_exists('강화_단소item_SQL하위호환')
        ? 강화_단소item_SQL하위호환()
        : "("
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
          . " OR item LIKE '%용피리%'"
          . " OR item LIKE '%천상의 피리%'"
          . ")";
      // 무기타입=2 이거나, 타입이 비었/어긋나도 단소 계열 item 이면 포함
      return "(IFNULL(무기타입,0) = 2 OR {$단소item})";
    }
    return '0';
  }
}

if (!function_exists('홀짝_무기종류_최강강화')) {
  /**
   * 해당 무기 종류(활/단소/마법) 활성 회원(status=0) 최고 강화
   * ※ item 글자 완전일치로 비교하지 않음 (피리≠단소 버그 방지)
   * @return int
   */
  function 홀짝_무기종류_최강강화($item): int {
    $item = trim((string)$item);
    if ($item === '') {
      return 0;
    }
    홀짝_무기타입_로드();
    $타입 = function_exists('무기_타입값') ? (int)무기_타입값($item) : 0;
    if ($타입 < 1 || $타입 > 3) {
      return 0;
    }
    static $cache = [];
    if (array_key_exists($타입, $cache)) {
      return (int)$cache[$타입];
    }
    $무기조건 = 홀짝_무기종류매칭_SQL($타입);
    if ($무기조건 === '' || $무기조건 === '0') {
      $cache[$타입] = 0;
      return 0;
    }
    $row = @db_select("
      SELECT MAX(IFNULL(enhance, 0)) AS mx
      FROM tb_member
      WHERE status = 0
        AND ({$무기조건})
    ");
    $cache[$타입] = max(0, (int)($row['mx'] ?? 0));
    return (int)$cache[$타입];
  }
}

if (!function_exists('홀짝_무기종류_올인가능')) {
  /**
   * 내 무기가 그 종류(활/단소/마법) 최고 강화(동률 포함)일 때만 올인 버튼
   */
  function 홀짝_무기종류_올인가능($item, $enhance, $닉 = ''): bool {
    unset($닉);
    $item = trim((string)$item);
    $enhance = (int)$enhance;
    if ($item === '' || $enhance < 1) {
      return false;
    }
    $max = 홀짝_무기종류_최강강화($item);
    return $max > 0 && $enhance >= $max;
  }
}

if (!function_exists('홀짝_회원_무기퀵정보')) {
  /**
   * @return array{item:string,enhance:int,quick_pcts:float[],can_allin:bool,fixed_bet:string,weapon_type:int,weapon_max:int}
   */
  function 홀짝_회원_무기퀵정보($닉_or_code, $by_code = false): array {
    $key = trim((string)$닉_or_code);
    $item = '';
    $enhance = 0;
    $무기타입 = 0;
    if ($key !== '') {
      $esc = addslashes($key);
      if ($by_code) {
        $row = @db_select("SELECT name, TRIM(IFNULL(item,'')) AS item, IFNULL(enhance,0) AS enhance, IFNULL(무기타입,0) AS 무기타입 FROM tb_member WHERE code = '{$esc}' LIMIT 1");
      } else {
        $row = @db_select("SELECT name, TRIM(IFNULL(item,'')) AS item, IFNULL(enhance,0) AS enhance, IFNULL(무기타입,0) AS 무기타입 FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      }
      if (is_array($row)) {
        $item = trim((string)($row['item'] ?? ''));
        $enhance = (int)($row['enhance'] ?? 0);
        $무기타입 = (int)($row['무기타입'] ?? 0);
      }
    }
    홀짝_무기타입_로드();
    if ($무기타입 < 1 || $무기타입 > 3) {
      $무기타입 = function_exists('무기_타입값') ? (int)무기_타입값($item) : 0;
    }
    $pcts = 홀짝_배팅_퀵_비율_무기강화($enhance);
    $max = ($item !== '') ? 홀짝_무기종류_최강강화($item) : 0;
    $can = ($enhance >= 1 && $max > 0 && $enhance >= $max);
    if ($can) {
      $pcts[] = 100;
    }
    return [
      'item' => $item,
      'enhance' => $enhance,
      'quick_pcts' => array_values(array_map('floatval', $pcts)),
      'can_allin' => $can,
      'fixed_bet' => '',
      'weapon_type' => $무기타입,
      'weapon_max' => $max,
    ];
  }
}

if (!function_exists('홀짝_배팅_비율금액_문자열')) {
  /**
   * 보유 × 퍼센트(%) 내림 — 소수 1자리까지 (0.1% = /1000)
   * @param string|int $가진냥
   * @param float|int $pct
   * @return string
   */
  function 홀짝_배팅_비율금액_문자열($가진냥, $pct) {
    $가진Str = function_exists('냥_정수문자열')
      ? 냥_정수문자열($가진냥)
      : preg_replace('/\D/', '', (string)$가진냥);
    $가진Str = ltrim((string)$가진Str, '0');
    if ($가진Str === '' || $가진Str === '0') {
      return '0';
    }
    $pct = (float)$pct;
    if ($pct <= 0) {
      return '0';
    }
    // 소수 1자리: (보유 × round(pct×10)) / 1000
    $tenths = (int)round($pct * 10);
    if ($tenths <= 0) {
      return '0';
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return bcdiv(bcmul($가진Str, (string)$tenths, 0), '1000', 0);
    }
    if ($tenths === 10 && function_exists('냥_비율내림')) {
      // 1% 정수 경로 호환
      return 냥_정수문자열(냥_비율내림($가진Str, 0.01));
    }
    if (strlen($가진Str) <= 15) {
      return (string)(int)floor(((float)$가진Str * $tenths) / 1000.0);
    }
    return '0';
  }
}

if (!function_exists('홀짝_냥')) {
  /** 부호 없는 정수 문자열로 정규화 — (int) 캐스팅 금지(922경≈PHP_INT_MAX 잘림) */
  function 홀짝_냥($v): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ltrim((string)$s, '0') ?: '0';
  }
}

if (!function_exists('홀짝_냥_sql')) {
  /** SQL 리터럴로 안전한 숫자 문자열 */
  function 홀짝_냥_sql($v): string {
    $s = 홀짝_냥($v);
    return preg_match('/^\d+$/', $s) ? $s : '0';
  }
}

if (!function_exists('홀짝_냥_비교')) {
  /** @return int a<b:-1, a==b:0, a>b:1 */
  function 홀짝_냥_비교($a, $b): int {
    $a = 홀짝_냥($a);
    $b = 홀짝_냥($b);
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

if (!function_exists('홀짝_냥_합')) {
  function 홀짝_냥_합($a, $b): string {
    $a = 홀짝_냥($a);
    $b = 홀짝_냥($b);
    if (function_exists('bcadd')) {
      return bcadd($a, $b, 0);
    }
    return function_exists('냥_금액_문자열합') ? 냥_금액_문자열합($a, $b) : (string)((int)$a + (int)$b);
  }
}

if (!function_exists('홀짝_냥_차')) {
  /** a - b (음수면 '0') */
  function 홀짝_냥_차($a, $b): string {
    $a = 홀짝_냥($a);
    $b = 홀짝_냥($b);
    if (function_exists('bcsub') && function_exists('bccomp')) {
      return (bccomp($a, $b, 0) < 0) ? '0' : bcsub($a, $b, 0);
    }
    return function_exists('냥_금액_문자열차감') ? 냥_금액_문자열차감($a, $b) : (string)max(0, (int)$a - (int)$b);
  }
}

if (!function_exists('홀짝_냥_곱')) {
  function 홀짝_냥_곱($a, $b): string {
    $a = 홀짝_냥($a);
    $b = 홀짝_냥($b);
    if ($a === '0' || $b === '0') {
      return '0';
    }
    if (function_exists('bcmul')) {
      return bcmul($a, $b, 0);
    }
    return function_exists('냥_금액_문자열곱') ? 냥_금액_문자열곱($a, $b) : (string)((int)$a * (int)$b);
  }
}

if (!function_exists('홀짝_냥_최대')) {
  function 홀짝_냥_최대($a, $b): string {
    return 홀짝_냥_비교($a, $b) >= 0 ? 홀짝_냥($a) : 홀짝_냥($b);
  }
}

if (!function_exists('홀짝_냥_최소')) {
  function 홀짝_냥_최소($a, $b): string {
    return 홀짝_냥_비교($a, $b) <= 0 ? 홀짝_냥($a) : 홀짝_냥($b);
  }
}

if (!function_exists('홀짝_배팅컬럼_확보')) {
  /**
   * pending_bet·streak_max_bet·log 금액을 DECIMAL(40,0)으로 승격 (BIGINT는 최대 약 1844경).
   * SHOW COLUMNS 반복을 막기 위해 프로세스·임시파일 플래그로 1회만 점검.
   */
  function 홀짝_배팅컬럼_확보(): void {
    static $done = false;
    if ($done || !function_exists('db_select') || !function_exists('db_query')) {
      return;
    }
    $done = true;

    $marker = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR . 'kakao_odd_even_bet_decimal40.flag';
    if (is_file($marker)) {
      return;
    }

    $needWiden = function ($type) {
      $type = strtolower((string)$type);
      if ($type === '') {
        return false;
      }
      if (strpos($type, 'decimal') === false) {
        return true;
      }
      return preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 40;
    };

    $state = ['streak_max_bet', 'pending_bet'];
    foreach ($state as $col) {
      $info = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE '{$col}'");
      if (!empty($info) && $needWiden($info['Type'] ?? '')) {
        @db_query("ALTER TABLE tb_odd_even_state MODIFY COLUMN `{$col}` DECIMAL(40,0) NOT NULL DEFAULT 0");
      }
    }

    $betCol = @db_select("SHOW COLUMNS FROM tb_odd_even_log LIKE 'bet'");
    if (!empty($betCol) && $needWiden($betCol['Type'] ?? '')) {
      @db_query("ALTER TABLE tb_odd_even_log MODIFY COLUMN `bet` DECIMAL(40,0) NOT NULL DEFAULT 0");
    }
    $deltaCol = @db_select("SHOW COLUMNS FROM tb_odd_even_log LIKE 'delta_point'");
    if (!empty($deltaCol) && $needWiden($deltaCol['Type'] ?? '')) {
      @db_query("ALTER TABLE tb_odd_even_log MODIFY COLUMN `delta_point` DECIMAL(41,0) NOT NULL DEFAULT 0 COMMENT '실제 포인트 변동 (+승리지급 / -패배차감)'");
    }

    @touch($marker);
  }
}

/**
 * 사전 봉인 정답 (sealed_answer)
 * — 배팅 시 봉인 소진 → pending_answer, 픽 정산 후 다음 봉인 준비
 */
if (!function_exists('홀짝_봉인_컬럼확보')) {
  function 홀짝_봉인_컬럼확보(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'sealed_answer'");
    if (empty($col['Field']) && empty($col['field'])) {
      @db_query("
        ALTER TABLE tb_odd_even_state
          ADD COLUMN sealed_answer TINYINT NULL DEFAULT NULL
          COMMENT '다음 판 사전 봉인 정답(1홀2짝3무)'
      ");
    }
  }
}

if (!function_exists('홀짝_봉인_유효인가')) {
  function 홀짝_봉인_유효인가($답): bool {
    $a = (int)$답;
    return ($a === 1 || $a === 2 || $a === 3);
  }
}

if (!function_exists('홀짝_봉인_추첨')) {
  /** 유저픽 없이 홀/짝/무 추첨 (깎기 0) */
  function 홀짝_봉인_추첨(): int {
    if (!function_exists('홀짝_정답_선정')) {
      require_once __DIR__ . '/odd_even_odds.php';
    }
    $a = (int)홀짝_정답_선정(null, null, 0);
    return 홀짝_봉인_유효인가($a) ? $a : 1;
  }
}

if (!function_exists('홀짝_봉인_설정')) {
  function 홀짝_봉인_설정($닉_esc, $답): int {
    홀짝_봉인_컬럼확보();
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 0;
    }
    $a = 홀짝_봉인_유효인가($답) ? (int)$답 : 홀짝_봉인_추첨();
    @db_query("
      INSERT INTO tb_odd_even_state (nick, sealed_answer)
      VALUES ('{$nick}', {$a})
      ON DUPLICATE KEY UPDATE sealed_answer = VALUES(sealed_answer)
    ");
    return $a;
  }
}

if (!function_exists('홀짝_봉인_조회')) {
  function 홀짝_봉인_조회($닉_esc): int {
    홀짝_봉인_컬럼확보();
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 0;
    }
    $row = @db_select("SELECT sealed_answer FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    $a = (int)($row['sealed_answer'] ?? 0);
    return 홀짝_봉인_유효인가($a) ? $a : 0;
  }
}

if (!function_exists('홀짝_봉인_소진')) {
  /**
   * 이번 판에 쓸 봉인 정답 반환 후 sealed_answer 비움.
   * 봉인 없으면 즉시 추첨.
   */
  function 홀짝_봉인_소진($닉_esc): int {
    홀짝_봉인_컬럼확보();
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 홀짝_봉인_추첨();
    }
    $a = 홀짝_봉인_조회($닉_esc);
    if (!홀짝_봉인_유효인가($a)) {
      $a = 홀짝_봉인_추첨();
    }
    @db_query("
      INSERT INTO tb_odd_even_state (nick, sealed_answer)
      VALUES ('{$nick}', NULL)
      ON DUPLICATE KEY UPDATE sealed_answer = NULL
    ");
    return (int)$a;
  }
}

if (!function_exists('홀짝_봉인_다음준비')) {
  /** 정산 직후 다음 판 봉인 */
  function 홀짝_봉인_다음준비($닉_esc): int {
    return 홀짝_봉인_설정($닉_esc, 홀짝_봉인_추첨());
  }
}

if (!function_exists('홀짝_배팅취소_봉인복구')) {
  /** 배팅 취소 시 pending_answer를 다시 봉인으로 되돌림 */
  function 홀짝_배팅취소_봉인복구($닉_esc, $pending_answer): void {
    if (홀짝_봉인_유효인가($pending_answer)) {
      홀짝_봉인_설정($닉_esc, $pending_answer);
    }
  }
}

if (!function_exists('홀짝_배팅_금액_int')) {
  /**
   * 큰 금액 문자열 → int (PHP_INT_MAX 초과 시 클램프)
   * @deprecated 922경 초과에서 잘림 — 금액은 홀짝_냥() 문자열로 다룰 것
   */
  function 홀짝_배팅_금액_int($v) {
    $s = function_exists('냥_정수문자열')
      ? 냥_정수문자열($v)
      : preg_replace('/\D/', '', (string)$v);
    $s = ltrim((string)$s, '0');
    if ($s === '' || $s === '0') {
      return 0;
    }
    $max = (string)PHP_INT_MAX;
    if (function_exists('bccomp')) {
      if (bccomp($s, $max, 0) > 0) {
        return PHP_INT_MAX;
      }
      return (int)$s;
    }
    if (strlen($s) > strlen($max) || (strlen($s) === strlen($max) && $s > $max)) {
      return PHP_INT_MAX;
    }
    return (int)$s;
  }
}

if (!function_exists('홀짝_배팅_티어')) {
  /** @return array{min:int} @deprecated 최소는 홀짝_기본배팅(보유 1%) */
  function 홀짝_배팅_티어($가진냥) {
    return array('min' => 홀짝_기본배팅($가진냥));
  }
}

if (!function_exists('홀짝_기본배팅_문자열')) {
  /**
   * 최소·기본 배팅 = 보유 게임냥의 1% (내림)
   * 보유가 적어 1%가 0이면 1냥
   */
  function 홀짝_기본배팅_문자열($가진냥) {
    $raw = 홀짝_배팅_비율금액_문자열($가진냥, 1);
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($raw) : ltrim(preg_replace('/\D/', '', (string)$raw), '0');
    if ($s === '' || $s === '0') {
      $가진Str = function_exists('냥_정수문자열')
        ? 냥_정수문자열($가진냥)
        : preg_replace('/\D/', '', (string)$가진냥);
      $가진Str = ltrim((string)$가진Str, '0');
      return ($가진Str === '' || $가진Str === '0') ? '0' : '1';
    }
    return $s;
  }
}

if (!function_exists('홀짝_기본배팅')) {
  /**
   * 미입력 시 기본·최소 배팅 — 보유 게임냥의 1%
   */
  function 홀짝_기본배팅($가진냥) {
    return 홀짝_배팅_금액_int(홀짝_기본배팅_문자열($가진냥));
  }
}

if (!function_exists('홀짝_배팅_퀵_금액')) {
  /**
   * 보유 % 올인급 퀵 금액 (최소·중복 제거, 보유 상한 클램프)
   * @return string[] 정수 문자열
   */
  function 홀짝_배팅_퀵_금액($가진냥) {
    $min = 홀짝_기본배팅_문자열($가진냥);
    $가진Str = 홀짝_냥($가진냥);
    $올인 = 홀짝_냥_최대($가진Str, $min);
    $out = array();
    $seen = array();
    $push = function ($amt) use (&$out, &$seen, $min, $올인) {
      $amt = 홀짝_냥_최소(홀짝_냥_최대($amt, $min), $올인);
      if ($amt === '0' || isset($seen[$amt])) {
        return;
      }
      $seen[$amt] = true;
      $out[] = $amt;
    };
    $push($min);
    foreach (홀짝_배팅_퀵_비율() as $pct) {
      $pct = (float)$pct;
      if ($pct <= 0) {
        continue;
      }
      $push(홀짝_배팅_비율금액_문자열($가진Str, $pct));
    }
    return $out;
  }
}

if (!function_exists('홀짝_최대배팅')) {
  /**
   * 홀짝 최대 배팅 — 보유 전액 올인
   * @deprecated int 반환은 922경에서 잘림 — 홀짝_최대배팅_문자열() 사용
   */
  function 홀짝_최대배팅($가진냥) {
    return 홀짝_배팅_금액_int(홀짝_최대배팅_문자열($가진냥));
  }
}

if (!function_exists('홀짝_최대배팅_문자열')) {
  /** 올인 상한 문자열 — JS JSON 정밀도용 (보유 전액, 최소 이상) */
  function 홀짝_최대배팅_문자열($가진냥) {
    $min = 홀짝_기본배팅_문자열($가진냥);
    $가진Str = function_exists('냥_정수문자열')
      ? 냥_정수문자열($가진냥)
      : (string)max(0, (int)$가진냥);
    if ($가진Str === '' || $가진Str === '0') {
      return $min;
    }
    if (function_exists('bccomp')) {
      return bccomp($가진Str, $min, 0) >= 0 ? $가진Str : $min;
    }
    return (strlen($가진Str) > strlen($min) || (strlen($가진Str) === strlen($min) && $가진Str >= $min))
      ? $가진Str
      : $min;
  }
}

if (!function_exists('홀짝_연승바닥배팅')) {
  /**
   * 연승 중 최소 배팅 = max(보유 1%, 이번 연승 최고배팅).
   * 연승 최고가 현재 구간 최대보다 크면(보유 하락·숫자 깨짐 등) 하한을 무시해 배팅 불능을 막음.
   * @return string 정수 문자열
   */
  function 홀짝_연승바닥배팅($가진냥, $streak_max_bet) {
    $기본 = 홀짝_기본배팅_문자열($가진냥);
    $최대 = 홀짝_최대배팅_문자열($가진냥);
    $연승최고 = 홀짝_냥($streak_max_bet);
    if (홀짝_냥_비교($연승최고, $최대) > 0) {
      $연승최고 = '0';
    }
    return 홀짝_냥_최소(홀짝_냥_최대($기본, $연승최고), $최대);
  }
}

if (!function_exists('홀짝_연승최고배팅_정리')) {
  /**
   * 연승 최고배팅이 현재 최대보다 크면 DB에서 0으로 정리하고 0을 반환.
   * @return string 정리 후 streak_max_bet (정수 문자열)
   */
  function 홀짝_연승최고배팅_정리($닉_esc, $가진냥, $streak_max_bet) {
    $연승최고 = 홀짝_냥($streak_max_bet);
    if ($연승최고 === '0') {
      return '0';
    }
    if (홀짝_냥_비교($연승최고, 홀짝_최대배팅_문자열($가진냥)) <= 0) {
      return $연승최고;
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick !== '') {
      @db_query("UPDATE tb_odd_even_state SET streak_max_bet = 0 WHERE nick = '{$nick}' LIMIT 1");
    }
    return '0';
  }
}

if (!function_exists('홀짝_배팅_퀵_목록')) {
  /**
   * @param string|int $가진냥
   * @param float[]|null $pcts null이면 홀짝_배팅_퀵_비율()
   * @param bool $include_fixed 하위호환(무시) — 10만 고정 폐지
   * @return array<int, array{fixed:string, label:string, pct?:float}>
   */
  function 홀짝_배팅_퀵_목록($가진냥, $pcts = null, $include_fixed = false) {
    $min = 홀짝_기본배팅_문자열($가진냥);
    $가진Str = 홀짝_냥($가진냥);
    $올인 = 홀짝_최대배팅_문자열($가진냥);
    $out = array();
    $seen = array();
    $pctLabel = function ($pct) {
      $pct = (float)$pct;
      if ($pct >= 100) {
        return '올인';
      }
      if (abs($pct - round($pct)) < 0.0001) {
        return ((int)round($pct)) . '%';
      }
      $s = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');
      return $s . '%';
    };
    $push = function ($amt, $pct = null, $label = null, $clamp_up = true) use (&$out, &$seen, $min, $올인, $pctLabel) {
      $amt = 홀짝_냥($amt);
      if ($amt === '0') {
        return;
      }
      if (홀짝_냥_비교($amt, $올인) > 0) {
        $amt = $올인;
      }
      if ($clamp_up && 홀짝_냥_비교($amt, $min) < 0) {
        $amt = $min;
      }
      if ($amt === '0' || isset($seen[$amt])) {
        return;
      }
      $seen[$amt] = true;
      $row = array(
        'fixed' => $amt,
        'label' => $label !== null ? (string)$label : $pctLabel($pct === null ? 1 : $pct),
      );
      if ($pct !== null) {
        $row['pct'] = (float)$pct;
      }
      $out[] = $row;
    };
    $list = is_array($pcts) ? $pcts : 홀짝_배팅_퀵_비율();
    foreach ($list as $pct) {
      $pct = (float)$pct;
      if ($pct <= 0) {
        continue;
      }
      $push(홀짝_배팅_비율금액_문자열($가진Str, $pct), $pct, null, true);
    }
    return $out;
  }
}

if (!function_exists('홀짝_미션_생타100_달성')) {
  /**
   * 홀짝 도전 참가: 당일 tb_mission(타수/100타) 완료자만 허용.
   */
  function 홀짝_미션_생타100_달성($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    $닉_esc = addslashes($닉);
    $row = @db_select("
      SELECT idx
      FROM tb_mission
      WHERE nick = '{$닉_esc}'
        AND types = '타수'
        AND status = '100타'
        AND regdate = CURDATE()
      LIMIT 1
    ");
    return is_array($row) && !empty($row['idx']);
  }
}

if (!function_exists('홀짝_신용회복_홀짝_금지문구')) {
  /**
   * credit_recovery_plus3_at: 신용 회복 시각 +3시간(잠금 종료 시각). 경과 분은 NULL 초기화.
   * @return string|null 금지 시 안내 문구, 허용이면 null
   */
  function 홀짝_신용회복_홀짝_금지문구($닉) {
    $닉_esc = addslashes($닉);
    db_query("UPDATE tb_member SET credit_recovery_plus3_at = NULL WHERE name = '{$닉_esc}' AND credit_recovery_plus3_at IS NOT NULL AND credit_recovery_plus3_at <= NOW() LIMIT 1");
    $row = @db_select("
      SELECT credit_recovery_plus3_at AS u,
             TIMESTAMPDIFF(MINUTE, NOW(), credit_recovery_plus3_at) AS remain_m
      FROM tb_member
      WHERE name = '{$닉_esc}' AND credit_recovery_plus3_at IS NOT NULL AND credit_recovery_plus3_at > NOW()
      LIMIT 1
    ");
    if (!$row || empty($row['u'])) {
      return null;
    }
    $remain = max(1, (int)($row['remain_m'] ?? 1));
    $until = (string)$row['u'];
    return "❌ 신용 회복 후 3시간 동안 홀짝 도전이 제한됩니다.\n남은 시간: 약 {$remain}분\n해제 시각: {$until}";
  }
}

if (!function_exists('게임제한_홀짝_금지문구')) {
  /** @return string|null */
  function 게임제한_홀짝_금지문구($닉) {
    return function_exists('게임제한_차단문구') ? 게임제한_차단문구($닉) : null;
  }
}

if (!function_exists('홀짝_지급로그')) {
  /** 홀짝 전용 경량 로그 (mypoint 동기화 생략 — 정산 직후 별도 point 조회) */
  function 홀짝_지급로그($상태, $닉, $수수료, $지급냥) {
    $상태원본 = trim((string)$상태);
    // 승/패/무승부는 건수 폭증 → point_log 기록 생략 (배팅·환급 등은 유지)
    if ($상태원본 === '홀짝도전-승' || $상태원본 === '홀짝도전-패' || $상태원본 === '홀짝도전-무승부') {
      return true;
    }
    $닉 = addslashes(trim((string)$닉));
    $상태 = addslashes($상태원본);
    $수수료 = 홀짝_냥_sql($수수료);
    $지급냥 = 홀짝_냥_sql($지급냥);
    if ($닉 === '') {
      return false;
    }
    return (bool)@db_query("
      INSERT INTO tb_point_log
      SET status = '{$상태}',
          nick = '{$닉}',
          receiver = '',
          tax = '{$수수료}',
          point = {$지급냥},
          regdate = NOW()
    ");
  }
}

if (!function_exists('홀짝_로또수수료_적립')) {
  /**
   * 로또 수수료 공통 진입점 → 항상 config.로또누적 가산
   * (강화·채굴·홀짝·수리·주식·한도외·스톱워치 등 전부 이 함수 또는 로또누적_가산 사용)
   * $적립닉 하위호환용(미사용) · tb_game_lotto INSERT 금지
   */
  function 홀짝_로또수수료_적립($로또금액, $적립닉 = '홀짝수수료') {
    if (is_file(__DIR__ . '/lotto_amount.inc.php')) {
      require_once __DIR__ . '/lotto_amount.inc.php';
    }
    $로또_sql = function_exists('냥_SQL정수')
      ? 냥_SQL정수($로또금액)
      : (function_exists('로또누적_정규화') ? 로또누적_정규화($로또금액) : (ltrim(preg_replace('/\D/', '', (string)$로또금액), '0') ?: '0'));
    $로또_sql = ltrim((string)$로또_sql, '0') ?: '0';
    if ($로또_sql === '0') {
      return 0;
    }
    if (function_exists('로또누적_가산')) {
      로또누적_가산($로또_sql);
    } else {
      // lotto_amount 미로드 시에도 INSERT 금지 — config 직접 가산
      $sql = preg_replace('/\D/', '', $로또_sql) ?: '0';
      @db_query("
        UPDATE config
        SET `로또누적` = CAST(IFNULL(`로또누적`, 0) AS DECIMAL(65,0)) + CAST({$sql} AS DECIMAL(65,0))
        LIMIT 1
      ");
    }
    return (function_exists('bccomp') && bccomp($로또_sql, (string)PHP_INT_MAX, 0) > 0)
      ? PHP_INT_MAX
      : (int)$로또_sql;
  }
}

if (!function_exists('홀짝_수수료_금고로또배분')) {
  /**
   * 홀짝 수수료 총액 → 금고 50% · 로또 50%
   * @return array{금고:string,로또:string}
   */
  function 홀짝_수수료_금고로또배분($총수수료) {
    $총 = 홀짝_냥($총수수료);
    if ($총 === '0') {
      return ['금고' => '0', '로또' => '0'];
    }
    $금고 = 홀짝_배팅_비율금액_문자열($총, 50);
    $로또 = 홀짝_냥_차($총, $금고);
    if ($금고 !== '0') {
      $금고sql = 홀짝_냥_sql($금고);
      db_query("UPDATE config SET tax = tax + {$금고sql}");
    }
    if ($로또 !== '0') {
      홀짝_로또수수료_적립($로또);
    }
    return ['금고' => $금고, '로또' => $로또];
  }
}

if (!function_exists('홀짝_하드_연승_보너스확률')) {
  /**
   * 하드모드 연승 승리 시 x2~x4 보너스 확률(%)
   * 0→1연승 30% · 1→2 20% · 2→3 25% · 3→4 15% · 4→5 10%
   */
  function 홀짝_하드_연승_보너스확률($streak_before = 0) {
    $표 = [30, 20, 25, 15, 10];
    $s = max(0, min(count($표) - 1, (int)$streak_before));
    return (int)$표[$s];
  }
}

if (!function_exists('홀짝_하드_연승_보너스배수')) {
  /**
   * 하드모드 연승 승리 시 연승 단계별 확률로 당첨금 x2~x4 보너스
   * @param int $streak_before 승리 직전 연승 (0=첫판, 4=4연승→5연승)
   * @return int 0=미발동, 2~4=배수
   */
  function 홀짝_하드_연승_보너스배수($streak_before = 0) {
    $pct = 홀짝_하드_연승_보너스확률($streak_before);
    if ($pct < 1 || mt_rand(1, 100) > $pct) {
      return 0;
    }
    return mt_rand(2, 4);
  }
}

if (!function_exists('홀짝_하드_연승_패배패널티확률')) {
  /**
   * 하드모드 연승 패배 시 2배 차감 확률(%)
   * 첫판 5% · 2번째 10% · 3번째 15% · 4번째 20% · 5번째 30%
   */
  function 홀짝_하드_연승_패배패널티확률($streak_before = 0) {
    $표 = [5, 10, 15, 20, 30];
    $s = max(0, min(count($표) - 1, (int)$streak_before));
    return (int)$표[$s];
  }
}

if (!function_exists('홀짝_하드_연승_패배2배_발동')) {
  /**
   * 하드모드 연승 패배 시 단계별 확률로 손실 2배
   * @param int $streak_before 패배 직전 연승
   * @return bool
   */
  function 홀짝_하드_연승_패배2배_발동($streak_before = 0) {
    $pct = 홀짝_하드_연승_패배패널티확률($streak_before);
    if ($pct < 1) {
      return false;
    }
    return mt_rand(1, 100) <= $pct;
  }
}

if (!function_exists('홀짝_하드_연승_x2_발동')) {
  /** @deprecated 홀짝_하드_연승_보너스배수() 사용 */
  function 홀짝_하드_연승_x2_발동($streak_before = 0) {
    return 홀짝_하드_연승_보너스배수($streak_before) >= 2;
  }
}

if (!function_exists('홀짝_회원_보유냥_문자열')) {
  /** tb_member.point — CAST CHAR (과학적표기·(int) 금지) */
  function 홀짝_회원_보유냥_문자열($닉_esc): string {
    $닉_esc = addslashes(trim((string)$닉_esc));
    if ($닉_esc === '') {
      return '0';
    }
    $row = @db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    return 홀짝_냥($row['point'] ?? 0);
  }
}

if (!function_exists('홀짝_올인배팅인가')) {
  /**
   * 배팅 시점 보유 전액(또는 99% 이상)을 걸었는지
   * @param bool $merge_bet true=아직 차감 전(웹), false=이미 배팅 차감됨(채팅)
   */
  function 홀짝_올인배팅인가($배팅, $닉_esc, $merge_bet = false): bool {
    $배팅 = 홀짝_냥($배팅);
    if ($배팅 === '0') {
      return false;
    }
    $현재 = 홀짝_회원_보유냥_문자열($닉_esc);
    $시점보유 = $merge_bet ? $현재 : 홀짝_냥_합($현재, $배팅);
    if ($시점보유 === '0') {
      return false;
    }
    if (홀짝_냥_비교($배팅, $시점보유) >= 0) {
      return true;
    }
    // 반올림·퀵버튼 오차: 보유 99% 이상이면 올인으로 본다
    $거의전액 = 홀짝_배팅_비율금액_문자열($시점보유, 99);
    return ($거의전액 !== '0' && 홀짝_냥_비교($배팅, $거의전액) >= 0);
  }
}

if (!function_exists('홀짝_배팅시점_보유냥')) {
  /** @param bool $merge_bet true=아직 차감 전 */
  function 홀짝_배팅시점_보유냥($배팅, $닉_esc, $merge_bet = false): string {
    $배팅 = 홀짝_냥($배팅);
    $현재 = 홀짝_회원_보유냥_문자열($닉_esc);
    return $merge_bet ? $현재 : 홀짝_냥_합($현재, $배팅);
  }
}

if (!function_exists('홀짝_배팅_퀵비율_매칭')) {
  /**
   * 배팅액을 퀵버튼 비율(1·5·10·30·50·100)에 가장 가깝게 매칭
   * @return float
   */
  function 홀짝_배팅_퀵비율_매칭($배팅, $닉_esc, $merge_bet = false): float {
    if (홀짝_올인배팅인가($배팅, $닉_esc, $merge_bet)) {
      return 100.0;
    }
    $배팅 = 홀짝_냥($배팅);
    $시점보유 = 홀짝_배팅시점_보유냥($배팅, $닉_esc, $merge_bet);
    if ($시점보유 === '0' || $배팅 === '0') {
      return 1.0;
    }
    $bestPct = 1.0;
    $bestDiff = null;
    foreach (홀짝_배팅_퀵_비율() as $pct) {
      $pct = (float)$pct;
      if ($pct <= 0) {
        continue;
      }
      $amt = 홀짝_배팅_비율금액_문자열($시점보유, $pct);
      if (홀짝_냥_비교($배팅, $amt) === 0) {
        return $pct;
      }
      $diff = (홀짝_냥_비교($배팅, $amt) >= 0)
        ? 홀짝_냥_차($배팅, $amt)
        : 홀짝_냥_차($amt, $배팅);
      if ($bestDiff === null || 홀짝_냥_비교($diff, $bestDiff) < 0) {
        $bestDiff = $diff;
        $bestPct = $pct;
      }
    }
    return $bestPct;
  }
}

if (!function_exists('홀짝_깎기퍼센트_배팅비율')) {
  /**
   * 퀵버튼 비율 → 유저픽 깎기 %p
   * 깎기 OFF면 0 · ON이면 닉별 6시간 구간 고정(3~8) · 특별닉(새아) 1%
   */
  function 홀짝_깎기퍼센트_배팅비율($퀵비율, $닉 = null): int {
    unset($퀵비율);
    if (function_exists('홀짝_깎기퍼센트_뽑기')) {
      return 홀짝_깎기퍼센트_뽑기($닉);
    }
    if (function_exists('홀짝_깎기_활성인가') && !홀짝_깎기_활성인가()) {
      return 0;
    }
    return defined('홀짝_깎기_기본퍼센트') ? (int)홀짝_깎기_기본퍼센트 : 3;
  }
}

if (!function_exists('홀짝_깎기퍼센트_배팅액')) {
  /** 배팅액 무관 · 닉별 6시간 깎기 %p (OFF면 0 · ON이면 3~8 구간고정 · 새아 1%) */
  function 홀짝_깎기퍼센트_배팅액($배팅, $닉_esc, $merge_bet = false): int {
    unset($배팅, $merge_bet);
    if (function_exists('홀짝_깎기퍼센트_뽑기')) {
      return 홀짝_깎기퍼센트_뽑기($닉_esc);
    }
    if (function_exists('홀짝_깎기_활성인가') && !홀짝_깎기_활성인가()) {
      return 0;
    }
    return defined('홀짝_깎기_기본퍼센트') ? (int)홀짝_깎기_기본퍼센트 : 3;
  }
}

if (!function_exists('홀짝_이지_연승승리_수수료적용')) {
  /**
   * 연승 승리 수수료: 배팅 10% (금고 5% · 로또 5%)
   * @return array{총:string,금고:string,로또:string,율:int}
   */
  function 홀짝_이지_연승승리_수수료적용($배팅) {
    $총 = 홀짝_배팅_비율금액_문자열($배팅, 10);
    $분배 = 홀짝_수수료_금고로또배분($총);
    return [
      '총' => 홀짝_냥_합($분배['금고'], $분배['로또']),
      '금고' => $분배['금고'],
      '로또' => $분배['로또'],
      '율' => 10,
    ];
  }
}

if (!function_exists('홀짝_하드_연승승리_수수료적용')) {
  /**
   * 연승 승리 수수료: 배팅 10% (금고 5% · 로또 5%)
   * @return array{총:string,금고:string,로또:string,율:int}
   */
  function 홀짝_하드_연승승리_수수료적용($배팅) {
    return 홀짝_이지_연승승리_수수료적용($배팅);
  }
}

if (!function_exists('홀짝_도전취소무승부_금고및로또')) {
  /** @deprecated alias — 홀짝_수수료_금고로또배분 과 동일 (50:50) */
  function 홀짝_도전취소무승부_금고및로또($총수수료) {
    return 홀짝_수수료_금고로또배분($총수수료);
  }
}

if (!function_exists('홀짝_모드_정규화')) {
  /** 이지/하드 선택 폐지 — 항상 하드모드 */
  function 홀짝_모드_정규화($모드) {
    return 'hard';
  }
}

if (!function_exists('홀짝_하드강제_보유냥')) {
  /** @deprecated 하드모드 고정 — 하위호환용 상수만 유지 */
  function 홀짝_하드강제_보유냥() {
    return '5000000000000000000'; // 500경
  }
}

if (!function_exists('홀짝_웹_하드모드_강제')) {
  /** 하드모드 고정 */
  function 홀짝_웹_하드모드_강제($가진냥) {
    return true;
  }
}

if (!function_exists('홀짝_모드_웹적용')) {
  /** @return string hard */
  function 홀짝_모드_웹적용($모드, $가진냥) {
    return 'hard';
  }
}

if (!function_exists('홀짝_모드_웹읽기')) {
  /** 하드모드 고정 */
  function 홀짝_모드_웹읽기($닉_esc, $가진냥, $행 = null) {
    return 'hard';
  }
}

if (!function_exists('홀짝도전_배수')) {
  /** 하드 고정 — 승·패 ×3·5·7·9·11 @return array{win:int,lose:int} */
  function 홀짝도전_배수($streak, $모드 = null) {
    unset($모드);
    $표 = array(3, 5, 7, 9, 11);
    $s = max(0, (int)$streak);
    if ($s > 4) {
      $s = 4;
    }
    return array('win' => $표[$s], 'lose' => $표[$s]);
  }
}

if (!function_exists('홀짝_모드_읽기')) {
  /** @param array|null $행 tb_odd_even_state 행 @return string hard */
  function 홀짝_모드_읽기($닉_esc, $행 = null) {
    return 'hard';
  }
}

if (!function_exists('홀짝_모드_저장')) {
  function 홀짝_모드_저장($닉_esc, $모드) {
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return false;
    }
    $m = 'hard';
    return (bool)@db_query("
      INSERT INTO tb_odd_even_state (nick, odds_mode)
      VALUES ('{$nick}', '{$m}')
      ON DUPLICATE KEY UPDATE odds_mode = VALUES(odds_mode)
    ");
  }
}

if (!function_exists('홀짝_닉_정답_선정')) {
  /** @param int|null $유저픽 1=홀 2=짝 */
  function 홀짝_닉_정답_선정($닉_esc, $유저픽 = null) {
    $깎기p = function_exists('홀짝_깎기퍼센트_뽑기') ? 홀짝_깎기퍼센트_뽑기($닉_esc) : 3;
    return 홀짝_정답_선정(홀짝_모드_읽기($닉_esc), $유저픽, $깎기p);
  }
}

if (!function_exists('홀짝_취소무승부_환급처리')) {
  /**
   * 대기 취소·무승부 환급 — 하드 고정: 전액 환급 · 수수료 없음
   * @return array{환급:string,수수료:string,금고:string,로또:string}
   */
  function 홀짝_취소무승부_환급처리($닉_esc, $두자리닉넴, $환불, $모드 = null, $포인트적용 = true) {
    unset($모드);
    $환불 = 홀짝_냥($환불);
    if ($포인트적용 && $환불 !== '0') {
      $환불sql = 홀짝_냥_sql($환불);
      db_query("UPDATE tb_member SET point = point + {$환불sql} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    return ['환급' => $환불, '수수료' => '0', '금고' => '0', '로또' => '0'];
  }
}

if (!function_exists('홀짝_타임아웃_환급처리')) {
  /**
   * 타임아웃 환급 — 하드 고정: 전액 환급 · 수수료 없음
   * @return array{환급:string,수수료:string,금고:string,로또:string}
   */
  function 홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $배팅원, $모드 = null) {
    unset($모드);
    $배팅원 = 홀짝_냥($배팅원);
    if ($배팅원 !== '0') {
      $배팅sql = 홀짝_냥_sql($배팅원);
      db_query("UPDATE tb_member SET point = point + {$배팅sql} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    if (function_exists('지급로그')) {
      지급로그('홀짝도전-타임아웃환급', $두자리닉넴, '', 0, $배팅원);
    }
    return ['환급' => $배팅원, '수수료' => '0', '금고' => '0', '로또' => '0'];
  }
}

if (!function_exists('홀짝_연승포기_수수료처리')) {
  /**
   * 연승 포기: 최고배팅 10% 수수료 (금고 50% · 로또 50%)
   * @return array{수수료:string,유지분:string,금고:string,로또:string,모드:string}
   */
  function 홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $맥스배, $모드 = null) {
    $맥스배 = 홀짝_냥($맥스배);
    $모드 = ($모드 !== null) ? 홀짝_모드_정규화($모드) : 홀짝_모드_읽기($닉_esc);
    $수수료 = 홀짝_배팅_비율금액_문자열($맥스배, 10);
    $유지분 = 홀짝_냥_차($맥스배, $수수료);
    $분배 = ['금고' => '0', '로또' => '0'];
    if ($수수료 !== '0') {
      $수수료sql = 홀짝_냥_sql($수수료);
      db_query("UPDATE tb_member SET point = point - {$수수료sql} WHERE name = '{$닉_esc}' LIMIT 1");
      $분배 = 홀짝_수수료_금고로또배분($수수료);
      if (function_exists('지급로그')) {
        지급로그('홀짝도전-연승포기', $두자리닉넴, '', $수수료, $유지분);
      }
    }
    return ['수수료' => $수수료, '유지분' => $유지분, '금고' => $분배['금고'], '로또' => $분배['로또'], '모드' => $모드];
  }
}

if (!function_exists('홀짝_금액표시')) {
  /** 홀짝 채팅용 금액 축약 (경·조·억) */
  function 홀짝_금액표시($금액, $단위 = '냥') {
    if (function_exists('냥축약표시')) {
      return 냥축약표시($금액, $단위);
    }
    if (function_exists('냥_경조억_축약문구')) {
      return 냥_경조억_축약문구($금액, $단위, '');
    }
    return 홀짝_냥($금액) . $단위;
  }
}

if (!function_exists('홀짝_수수료_분배문구')) {
  function 홀짝_수수료_분배문구(array $분배, $단위 = '냥') {
    $금고 = 홀짝_냥($분배['금고'] ?? 0);
    $로또 = 홀짝_냥($분배['로또'] ?? 0);
    if ($금고 === '0' && $로또 !== '0') {
      return '로또 ' . 홀짝_금액표시($로또, $단위);
    }
    if ($로또 === '0' && $금고 !== '0') {
      return '금고 ' . 홀짝_금액표시($금고, $단위);
    }
    return '수수료 금고 ' . 홀짝_금액표시($금고, $단위) . '·로또 ' . 홀짝_금액표시($로또, $단위);
  }
}

if (!function_exists('홀짝_취소무승부_환급문구')) {
  function 홀짝_취소무승부_환급문구(array $결과, $모드 = null, $단위 = '냥') {
    unset($모드);
    $환급 = 홀짝_냥($결과['환급'] ?? 0);
    return '+' . 홀짝_금액표시($환급, $단위) . ' 전액 환급 (수수료 없음)';
  }
}

if (!function_exists('홀짝_타임아웃_환급문구')) {
  function 홀짝_타임아웃_환급문구(array $결과, $모드 = null, $단위 = '냥') {
    unset($모드);
    $환급 = 홀짝_냥($결과['환급'] ?? 0);
    return '+' . 홀짝_금액표시($환급, $단위) . ' 환급 (수수료 없음)';
  }
}

if (!function_exists('홀짝_연승포기_수수료문구')) {
  function 홀짝_연승포기_수수료문구(array $포기, $단위 = '냥') {
    if (홀짝_냥($포기['수수료'] ?? 0) === '0') {
      return '';
    }
    return '최고배팅 10% 수수료(90% 유지) · '
      . 홀짝_수수료_분배문구(['금고' => $포기['금고'] ?? 0, '로또' => $포기['로또'] ?? 0], $단위);
  }
}

/** 연승 포기 버튼 오퍼: 2연승~(5연승 완주 전) 항상 표시 */
if (!function_exists('홀짝_연승포기_오퍼_컬럼확보')) {
  function 홀짝_연승포기_오퍼_컬럼확보(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'giveup_offer'");
    if (!$col) {
      @db_query("
        ALTER TABLE tb_odd_even_state
          ADD COLUMN giveup_offer TINYINT UNSIGNED NOT NULL DEFAULT 0
          COMMENT '연승포기 버튼 오퍼(2연승부터 항상)'
      ");
    }
  }
}

if (!function_exists('홀짝_연승포기_오퍼_조회')) {
  function 홀짝_연승포기_오퍼_조회($닉_esc): int {
    홀짝_연승포기_오퍼_컬럼확보();
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 0;
    }
    $row = @db_select("SELECT giveup_offer FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    return ((int)($row['giveup_offer'] ?? 0) === 1) ? 1 : 0;
  }
}

if (!function_exists('홀짝_연승포기_오퍼_설정')) {
  function 홀짝_연승포기_오퍼_설정($닉_esc, $on): int {
    홀짝_연승포기_오퍼_컬럼확보();
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 0;
    }
    $v = ((int)$on === 1) ? 1 : 0;
    @db_query("
      INSERT INTO tb_odd_even_state (nick, giveup_offer)
      VALUES ('{$nick}', {$v})
      ON DUPLICATE KEY UPDATE giveup_offer = VALUES(giveup_offer)
    ");
    return $v;
  }
}

if (!function_exists('홀짝_연승포기_오퍼_클리어')) {
  function 홀짝_연승포기_오퍼_클리어($닉_esc): void {
    홀짝_연승포기_오퍼_설정($닉_esc, 0);
  }
}

/**
 * 승리 후 연승포기 오퍼.
 * streak_after >= 2 (2연승~) 이면 항상 ON. 미만이면 클리어.
 * @return int 0|1
 */
if (!function_exists('홀짝_연승포기_오퍼_승리후갱신')) {
  function 홀짝_연승포기_오퍼_승리후갱신($닉_esc, $streak후): int {
    $streak후 = (int)$streak후;
    if ($streak후 < 2) {
      홀짝_연승포기_오퍼_클리어($닉_esc);
      return 0;
    }
    return 홀짝_연승포기_오퍼_설정($닉_esc, 1);
  }
}

if (!function_exists('홀짝_연승포기_가능')) {
  /** 연승 포기 가능: 2연승 이상 (0→1·1→2 이후) */
  function 홀짝_연승포기_가능($연승, $오퍼 = 1): bool {
    return (int)$연승 >= 2;
  }
}

/** 현재 연승에 맞춰 giveup_offer 동기화 (2연승↑=1, 미만=0) */
if (!function_exists('홀짝_연승포기_오퍼_연승동기화')) {
  function 홀짝_연승포기_오퍼_연승동기화($닉_esc, $연승): int {
    $연승 = (int)$연승;
    if ($연승 >= 2) {
      return 홀짝_연승포기_오퍼_설정($닉_esc, 1);
    }
    홀짝_연승포기_오퍼_클리어($닉_esc);
    return 0;
  }
}

if (!function_exists('홀짝_신용회복_pending이면_취소환급')) {
  /**
   * 신용 회복 직후 3시간 락 중에는 정산 불가 → 진행 중 배팅이 있으면 취소와 동일(전액 환급·수수료 없음) 처리.
   *
   * @return array{msg:string,refund:int,gumgo:int,lotto:int,tax:int}|null 락 중이면 메시지·환급액, 아니면 null
   */
  function 홀짝_신용회복_pending이면_취소환급($닉, $판, $단위 = '냥') {
    $head = 홀짝_신용회복_홀짝_금지문구($닉);
    if ($head === null) {
      return null;
    }
    $닉_esc = addslashes($닉);
    $환불 = 홀짝_냥($판['pending_bet'] ?? 0);
    if ($환불 === '0') {
      return null;
    }
    $모드 = 홀짝_모드_읽기($닉_esc);
    $환급결과 = 홀짝_취소무승부_환급처리($닉_esc, $닉, $환불, $모드);
    if (function_exists('홀짝_배팅취소_봉인복구')) {
      홀짝_배팅취소_봉인복구($닉_esc, $판['pending_answer'] ?? 0);
    }
    db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('홀짝도전-신용회복제한환급', $닉, '', $환급결과['수수료'], $환급결과['환급']);
    }
    $msg = $head . "\n\n⛔ 신용 회복 직후 제한으로 진행 중 배팅을 취소했습니다.\n✅ " . 홀짝_취소무승부_환급문구($환급결과, $모드, $단위);
    return ['msg' => $msg, 'refund' => $환급결과['환급'], 'gumgo' => $환급결과['금고'], 'lotto' => $환급결과['로또'], 'tax' => $환급결과['금고']];
  }
}

if (!function_exists('홀짝_도전_본인상태_정리')) {
  /**
   * 채팅 .도전 전 본인 pending·연승만 검사 (웹 multi 와 동일 — 타인 진행으로 막지 않음).
   *
   * @return array{block:?string, notice:string}
   */
  function 홀짝_도전_본인상태_정리($두자리닉넴, $단위 = '냥', $만료초 = 1800) {
    $닉_esc = addslashes($두자리닉넴);
    $notice = '';
    $block = null;
    $만료초 = (int)$만료초;
    if ($만료초 <= 0) {
      $만료초 = 1800;
    }

    $진행행 = @db_select("
      SELECT pending_at, CAST(pending_bet AS CHAR) AS pending_bet, pending_answer,
             TIMESTAMPDIFF(SECOND, pending_at, NOW()) AS elapsed_sec,
             DATE_FORMAT(DATE_ADD(pending_at, INTERVAL {$만료초} SECOND), '%H:%i') AS end_hm
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND pending_bet > 0
      LIMIT 1
    ");
    if ($진행행 && 홀짝_냥($진행행['pending_bet'] ?? 0) !== '0') {
      $경과초 = (int)($진행행['elapsed_sec'] ?? 0);
      $종료시각 = trim((string)($진행행['end_hm'] ?? ''));
      if ($경과초 < $만료초) {
        $취소안내 = '배팅 전액 환급(수수료 없음)';
        if ($종료시각 !== '') {
          $block = "⏳ {$종료시각} 종료 전 `홀`/`짝` 또는 `.도전 취소`/`ㅈㅈ 취소`({$취소안내})";
        } else {
          $block = "⏳ `홀`/`짝` 또는 `.도전 취소`/`ㅈㅈ 취소`({$취소안내})";
        }
        return ['block' => $block, 'notice' => ''];
      }
      $만료환 = 홀짝_냥($진행행['pending_bet'] ?? 0);
      $만료모드 = 홀짝_모드_읽기($닉_esc);
      $만료환급 = '0';
      $만료분배 = ['금고' => '0', '로또' => '0'];
      if ($만료환 !== '0') {
        $타임 = 홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환, $만료모드);
        $만료환급 = $타임['환급'];
        $만료분배 = ['금고' => $타임['금고'], '로또' => $타임['로또']];
      }
      if (function_exists('홀짝_배팅취소_봉인복구')) {
        홀짝_배팅취소_봉인복구($닉_esc, $진행행['pending_answer'] ?? 0);
      }
      db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
      $notice = "⏰ 본인 30분 무응답 자동 정산\n"
              . "  " . 홀짝_타임아웃_환급문구(['환급' => $만료환급, '금고' => $만료분배['금고'], '로또' => $만료분배['로또']], $만료모드, $단위) . "\n\n";
    }

    $연승행 = @db_select("
      SELECT streak, CAST(streak_max_bet AS CHAR) AS streak_max_bet,
             TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND streak > 0
      LIMIT 1
    ");
    if ($연승행 && (int)($연승행['streak'] ?? 0) > 0) {
      $연승경과초 = (int)($연승행['elapsed_sec'] ?? 0);
      if ($연승경과초 >= $만료초) {
        $이전연승 = (int)($연승행['streak'] ?? 0);
        $이전맥스배 = 홀짝_냥($연승행['streak_max_bet'] ?? 0);
        $포기 = 홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $이전맥스배, 홀짝_모드_읽기($닉_esc));
        홀짝_연승포기_오퍼_컬럼확보();
        db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
        $포기문구 = 홀짝_연승포기_수수료문구($포기, $단위);
        $notice .= "⏰ 본인 30분 무응답 → 연승({$이전연승}) 자동 포기\n"
                 . "  맥스배 " . 홀짝_금액표시($이전맥스배, $단위)
                 . ($포기문구 !== '' ? " · {$포기문구}" : '') . "\n\n";
      }
    }

    return ['block' => $block, 'notice' => $notice];
  }
}

if (!function_exists('홀짝_연승_최대')) {
  /** 완주 목표 연승 수 (5연승 달성 시 자동 종료) */
  function 홀짝_연승_최대() {
    return 5;
  }
}

if (!function_exists('홀짝_연승_저장_상한')) {
  /**
   * tb_odd_even_state.streak 컬럼 정상 상한.
   * 4연승까지 달성 후 5번째 판 배팅 중일 때 streak=4 (다음 승이 5연승 완주).
   */
  function 홀짝_연승_저장_상한() {
    return max(0, 홀짝_연승_최대() - 1);
  }
}

if (!function_exists('홀짝_연승_정규화')) {
  /** @return int 0 .. 저장상한 */
  function 홀짝_연승_정규화($streak) {
    $s = (int)$streak;
    if ($s < 0) {
      return 0;
    }
    $cap = 홀짝_연승_저장_상한();
    if ($s > $cap) {
      return $cap;
    }
    return $s;
  }
}

if (!function_exists('홀짝_연승_읽기_및_복구')) {
  /**
   * 비정상 streak(5연승 완주 후 리셋 누락 등 ≥최대) 은 0으로 복구.
   * 저장상한 초과(5 이상)도 동일 처리.
   *
   * @return int 복구·정규화 후 사용할 streak
   */
  function 홀짝_연승_읽기_및_복구($닉_esc, $rawStreak) {
    $raw = (int)$rawStreak;
    if ($raw < 0) {
      db_query("UPDATE tb_odd_even_state SET streak = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
      return 0;
    }
    if ($raw > 홀짝_연승_저장_상한()) {
      홀짝_연승포기_오퍼_컬럼확보();
      db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
      return 0;
    }
    return $raw;
  }
}

if (!function_exists('홀짝_연승_달성_표시')) {
  /** 이번 판 승리 후 표시용 연승 수 (최대 5) */
  function 홀짝_연승_달성_표시($streak달성) {
    return min(max(1, (int)$streak달성), 홀짝_연승_최대());
  }
}

if (!function_exists('홀짝_판_조회')) {
  /** @return array|null tb_odd_even_state 행 */
  function 홀짝_판_조회($닉_esc) {
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return null;
    }
    홀짝_배팅컬럼_확보();
    // CAST AS CHAR: 922경 초과 금액이 PHP int/float 로 깨지지 않게 문자열로 받음
    홀짝_연승포기_오퍼_컬럼확보();
    $판 = @db_select("
      SELECT streak,
             CAST(streak_max_bet AS CHAR) AS streak_max_bet,
             CAST(pending_bet AS CHAR) AS pending_bet,
             pending_answer,
             pending_win_mult, pending_lose_mult, pending_at,
             odds_mode, updated_at,
             giveup_offer
      FROM tb_odd_even_state
      WHERE nick = '{$nick}'
      LIMIT 1
    ");
    return is_array($판) ? $판 : null;
  }
}

if (!function_exists('홀짝_배팅_저장')) {
  /** @return bool */
  function 홀짝_배팅_저장($닉_esc, $streak, $누적맥스, $배팅, $정답, $win, $lose) {
    $nick = addslashes((string)$닉_esc);
    홀짝_배팅컬럼_확보();
    return (bool)db_query("
      INSERT INTO tb_odd_even_state
        (nick, streak, streak_max_bet, pending_bet, pending_answer, pending_win_mult, pending_lose_mult, pending_at)
      VALUES
        ('{$nick}', " . (int)$streak . ", " . 홀짝_냥_sql($누적맥스) . ", " . 홀짝_냥_sql($배팅) . ", " . (int)$정답 . ", " . (int)$win . ", " . (int)$lose . ", NOW())
      ON DUPLICATE KEY UPDATE
        streak = VALUES(streak),
        pending_bet = VALUES(pending_bet),
        pending_answer = VALUES(pending_answer),
        pending_win_mult = VALUES(pending_win_mult),
        pending_lose_mult = VALUES(pending_lose_mult),
        pending_at = VALUES(pending_at)
    ");
  }
}

if (!function_exists('홀짝_상태_정산반영')) {
  /**
   * 정산 후 연승·최고배팅·pending 초기화 (행 없으면 INSERT — bet_pick 즉시정산 대응).
   * @return bool
   */
  function 홀짝_상태_정산반영($닉_esc, $streak, $streak_max_bet) {
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return false;
    }
    $streak = 홀짝_연승_정규화((int)$streak);
    $streak_max_bet = 홀짝_냥_sql($streak_max_bet);
    홀짝_배팅컬럼_확보();
    홀짝_연승포기_오퍼_컬럼확보();
    $clear = 'pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL';
    // 연승 리셋 시 포기 오퍼도 제거 (승리는 이후 주사위로 다시 설정)
    $giveupClear = ($streak < 2) ? ', giveup_offer = 0' : '';
    return (bool)db_query("
      INSERT INTO tb_odd_even_state
        (nick, streak, streak_max_bet, pending_bet, pending_answer, pending_win_mult, pending_lose_mult, pending_at, giveup_offer)
      VALUES
        ('{$nick}', {$streak}, {$streak_max_bet}, 0, NULL, 0, 0, NULL, 0)
      ON DUPLICATE KEY UPDATE
        streak = VALUES(streak),
        streak_max_bet = VALUES(streak_max_bet),
        {$clear}
        {$giveupClear}
    ");
  }
}

if (!function_exists('홀짝_방금배팅판_만들기')) {
  /** 같은 요청 내 즉시정산용 — DB 재조회 없이 정산 */
  function 홀짝_방금배팅판_만들기($streak, $누적맥스, $배팅, $정답, $win, $lose) {
    return [
      'streak' => (int)$streak,
      'streak_max_bet' => 홀짝_냥($누적맥스),
      'pending_bet' => 홀짝_냥($배팅),
      'pending_answer' => (int)$정답,
      'pending_win_mult' => (int)$win,
      'pending_lose_mult' => (int)$lose,
      'pending_at' => date('Y-m-d H:i:s'),
    ];
  }
}

require_once __DIR__ . '/odd_even_odds.php';
