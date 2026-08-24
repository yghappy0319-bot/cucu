<?php
/**
 * `.양도` — info2 게임냥(point) 양도
 * tb_point_log (status=양도, 오늘 날짜) 기준 하루 1회 + 지호 추가
 */

if (!function_exists('yangdo_db_준비')) {
  function yangdo_db_준비(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    if (function_exists('date_default_timezone_set')) {
      @date_default_timezone_set('Asia/Seoul');
    }
  }
}

if (!function_exists('yangdo_닉_정규화')) {
  function yangdo_닉_정규화(string $닉): string {
    yangdo_db_준비();
    static $cache = [];
    $key = trim($닉);
    if ($key === '') {
      return '';
    }
    if (isset($cache[$key])) {
      return $cache[$key];
    }

    $parsed = $key;
    if (function_exists('getTwoCharNick')) {
      $two = getTwoCharNick($key);
      if ($two !== '') {
        $parsed = $two;
      }
    }

    $parsed_esc = addslashes($parsed);
    $row = db_select("SELECT name FROM tb_member WHERE name = '{$parsed_esc}' LIMIT 1");
    if (empty($row['name'])) {
      $row = db_select("SELECT name FROM tb_member WHERE TRIM(name) = '{$parsed_esc}' LIMIT 1");
    }
    $result = !empty($row['name']) ? trim((string)$row['name']) : $parsed;
    $cache[$key] = $result;
    if ($parsed !== $key) {
      $cache[$parsed] = $result;
    }
    return $result;
  }
}

if (!function_exists('yangdo_받는이_조회')) {
  /** 받는이 정규화 + 회원 존재 확인 (1회 쿼리) */
  function yangdo_받는이_조회(string $receiver): ?array {
    yangdo_db_준비();
    static $cache = [];
    $key = trim($receiver);
    if ($key === '') {
      return null;
    }
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $parsed = $key;
    if (function_exists('getTwoCharNick')) {
      $two = getTwoCharNick($key);
      if ($two !== '') {
        $parsed = $two;
      }
    }
    $parsed_esc = addslashes($parsed);
    $row = db_select("SELECT idx, name FROM tb_member WHERE name = '{$parsed_esc}' LIMIT 1");
    if (empty($row['idx'])) {
      $row = db_select("SELECT idx, name FROM tb_member WHERE TRIM(name) = '{$parsed_esc}' LIMIT 1");
    }
    if (empty($row['idx'])) {
      $cache[$key] = null;
      return null;
    }

    $result = [
      'idx' => (int)$row['idx'],
      'name' => trim((string)$row['name']),
    ];
    $cache[$key] = $result;
    $cache[$parsed] = $result;
    $cache[$result['name']] = $result;
    return $result;
  }
}

if (!function_exists('yangdo_오늘완료수')) {
  /** tb_point_log — status=양도, MySQL 오늘(CURDATE) 기준 (인덱스 활용) */
  function yangdo_오늘완료수(string $닉): int {
    yangdo_db_준비();
    static $cache = [];
    $닉 = trim($닉);
    if ($닉 === '') {
      return 0;
    }
    if (isset($cache[$닉])) {
      return $cache[$닉];
    }

    $닉_esc = addslashes($닉);
    $row = db_select("
      SELECT COUNT(*) AS cnt
      FROM tb_point_log
      WHERE status = '양도'
        AND nick = '{$닉_esc}'
        AND regdate >= CURDATE()
        AND regdate < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");
    $cnt = (int)($row['cnt'] ?? 0);
    $cache[$닉] = $cnt;
    return $cnt;
  }
}

if (!function_exists('yangdo_지호_정보')) {
  /** 지호 1회 조회 — 적용 여부·남은 시간·차감용 idx */
  function yangdo_지호_정보(string $닉): array {
    yangdo_db_준비();
    static $cache = [];
    $닉 = trim($닉);
    if ($닉 === '') {
      return ['적용중' => false, '남은시간' => 0, 'idx' => 0, 'enddate_ts' => null];
    }
    if (isset($cache[$닉])) {
      return $cache[$닉];
    }

    $닉_esc = addslashes($닉);
    $row = db_select("
      SELECT idx, enddate
      FROM tb_item_use
      WHERE nickname = '{$닉_esc}' AND item = '지호' AND enddate > NOW()
      ORDER BY enddate DESC
      LIMIT 1
    ");
    if (empty($row['idx'])) {
      $info = ['적용중' => false, '남은시간' => 0, 'idx' => 0, 'enddate_ts' => null];
      $cache[$닉] = $info;
      return $info;
    }

    $ts = strtotime((string)$row['enddate']);
    if ($ts === false || $ts <= time()) {
      $info = ['적용중' => false, '남은시간' => 0, 'idx' => 0, 'enddate_ts' => null];
      $cache[$닉] = $info;
      return $info;
    }

    $info = [
      '적용중' => true,
      '남은시간' => (int)floor(($ts - time()) / 3600),
      'idx' => (int)$row['idx'],
      'enddate_ts' => $ts,
    ];
    $cache[$닉] = $info;
    return $info;
  }
}

if (!function_exists('yangdo_amount_sql')) {
  /**
   * tb_member UPDATE용 정수 문자열
   * 주의: "5.0E+19" 를 숫자만 남기면 "5019" 로 깨짐 → 냥_정수문자열 사용
   */
  function yangdo_amount_sql($n): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($n);
    }
    $s = trim((string)$n);
    if ($s === '') {
      return '0';
    }
    // 과학적 표기 문자열 직접 복원
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
    if (strpos($s, '.') !== false) {
      $s = explode('.', $s, 2)[0];
    }
    $s = preg_replace('/[^\d]/', '', $s);
    return ($s === '' || $s === null) ? '0' : (ltrim($s, '0') ?: '0');
  }
}

if (!function_exists('yangdo_amount_gte')) {
  function yangdo_amount_gte($a, $b): bool {
    $as = yangdo_amount_sql($a);
    $bs = yangdo_amount_sql($b);
    if ($as === '0' && $bs === '0') {
      return true;
    }
    if (function_exists('bccomp')) {
      return bccomp($as, $bs, 0) >= 0;
    }
    return (float)$as >= (float)$bs;
  }
}

if (!function_exists('yangdo_db_last_error')) {
  function yangdo_db_last_error(): string {
    global $conn;
    return ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
  }
}

if (!function_exists('yangdo_bigint_상한')) {
  /** MySQL SIGNED BIGINT 최대값 — 요청 중 ALTER 금지(tb_point_log 락/타임아웃 방지) */
  function yangdo_bigint_상한(): string {
    return '9223372036854775807';
  }
}

if (!function_exists('yangdo_로그금액_안전')) {
  /** 로그/금고 컬럼(BIGINT)용 — 초과분은 상한으로 저장 (실제 양도액은 member.point DECIMAL) */
  function yangdo_로그금액_안전($n): string {
    $s = yangdo_amount_sql($n);
    $max = yangdo_bigint_상한();
    if (function_exists('bccomp')) {
      return (bccomp($s, $max, 0) > 0) ? $max : $s;
    }
    if (strlen($s) > strlen($max) || (strlen($s) === strlen($max) && $s > $max)) {
      return $max;
    }
    return $s;
  }
}

if (!function_exists('yangdo_수수료_문자열')) {
  /** 보낸 금액의 1% (반올림) — 문자열/bcmath (천경·해 대응) */
  function yangdo_수수료_문자열($양도금액): string {
    $s = yangdo_amount_sql($양도금액);
    if ($s === '0' || !yangdo_amount_gte($s, '1')) {
      return '0';
    }
    if (function_exists('bcadd') && function_exists('bcdiv')) {
      // round(n * 0.01) ≈ floor((n + 50) / 100)
      return bcdiv(bcadd($s, '50', 0), '100', 0);
    }
    if (strlen($s) <= 15) {
      return (string)(int)round((float)$s * 0.01);
    }
    // bcmath 없을 때 큰 수: 끝 두 자리로 반올림
    $len = strlen($s);
    if ($len <= 2) {
      return ((int)$s >= 50) ? '1' : '0';
    }
    $head = substr($s, 0, $len - 2);
    $tail = (int)substr($s, -2);
    if ($tail < 50) {
      return ltrim($head, '0') ?: '0';
    }
    // head + 1 (문자열)
    $rev = strrev($head);
    $carry = 1;
    $out = '';
    for ($i = 0, $n = strlen($rev); $i < $n; $i++) {
      $sum = ((int)$rev[$i]) + $carry;
      $out .= (string)($sum % 10);
      $carry = intdiv($sum, 10);
    }
    if ($carry > 0) {
      $out .= (string)$carry;
    }
    return ltrim(strrev($out), '0') ?: '0';
  }
}

if (!function_exists('yangdo_수령액_문자열')) {
  function yangdo_수령액_문자열($보내는양): string {
    $s = yangdo_amount_sql($보내는양);
    $fee = yangdo_수수료_문자열($s);
    if (function_exists('bcsub') && function_exists('bccomp')) {
      $r = bcsub($s, $fee, 0);
      return (bccomp($r, '0', 0) < 0) ? '0' : $r;
    }
    if (strlen($s) <= 15 && strlen($fee) <= 15) {
      return yangdo_amount_sql(max(0, (int)$s - (int)$fee));
    }
    // 자리수 맞춘 학교식 뺄셈 (float 금지)
    $a = strrev($s);
    $b = strrev($fee);
    $len = max(strlen($a), strlen($b));
    $borrow = 0;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
      $da = (int)($a[$i] ?? '0') - $borrow;
      $db = (int)($b[$i] ?? '0');
      if ($da < $db) {
        $da += 10;
        $borrow = 1;
      } else {
        $borrow = 0;
      }
      $out .= (string)($da - $db);
    }
    if ($borrow > 0) {
      return '0';
    }
    return ltrim(strrev($out), '0') ?: '0';
  }
}

if (!function_exists('yangdo_양도_로그_기록')) {
  /** 경량 로그 — mypoint SELECT 생략 · BIGINT 한도 초과 시 상한 저장(일일횟수 카운트 유지) */
  function yangdo_양도_로그_기록(string $닉, string $receiver, $수수료, $보낸양): bool {
    $닉_esc = addslashes(trim($닉));
    $받는_esc = addslashes(trim($receiver));
    if ($닉_esc === '') {
      return false;
    }
    $보낸양_sql = yangdo_로그금액_안전($보낸양);
    $수수료_sql = yangdo_로그금액_안전($수수료);
    if ($보낸양_sql === '0') {
      return false;
    }

    $ok = (bool)db_query("
      INSERT INTO tb_point_log
      SET status = '양도',
          nick = '{$닉_esc}',
          receiver = '{$받는_esc}',
          tax = {$수수료_sql},
          point = {$보낸양_sql},
          regdate = NOW()
    ");
    if (!$ok) {
      return false;
    }
    db_query("
      INSERT INTO tb_point_log
      SET status = '수수료',
          nick = '{$닉_esc}',
          receiver = '',
          tax = {$수수료_sql},
          point = {$수수료_sql},
          regdate = NOW()
    ");
    return true;
  }
}

if (!function_exists('yangdo_지호_enddate_ts')) {
  function yangdo_지호_enddate_ts(string $닉): ?int {
    $info = yangdo_지호_정보($닉);
    return $info['enddate_ts'];
  }
}

if (!function_exists('yangdo_지호적용중')) {
  function yangdo_지호적용중(string $닉): bool {
    return yangdo_지호_정보($닉)['적용중'];
  }
}

if (!function_exists('yangdo_지호_남은시간_시간')) {
  function yangdo_지호_남은시간_시간(string $닉): int {
    return yangdo_지호_정보($닉)['남은시간'];
  }
}

if (!function_exists('yangdo_마법_정보')) {
  /** 마법 버프 1회 조회 — 적용 여부·남은 일수·차감용 idx */
  function yangdo_마법_정보(string $닉): array {
    yangdo_db_준비();
    static $cache = [];
    $닉 = trim($닉);
    if ($닉 === '') {
      return ['적용중' => false, '남은일수' => 0, 'idx' => 0, 'enddate_ts' => null];
    }
    if (isset($cache[$닉])) {
      return $cache[$닉];
    }

    $닉_esc = addslashes($닉);
    $row = db_select("
      SELECT idx, enddate
      FROM tb_item_use
      WHERE nickname = '{$닉_esc}' AND item = '마법' AND enddate > NOW()
      ORDER BY enddate DESC
      LIMIT 1
    ");
    if (empty($row['idx'])) {
      $info = ['적용중' => false, '남은일수' => 0, 'idx' => 0, 'enddate_ts' => null];
      $cache[$닉] = $info;
      return $info;
    }

    $ts = strtotime((string)$row['enddate']);
    if ($ts === false || $ts <= time()) {
      $info = ['적용중' => false, '남은일수' => 0, 'idx' => 0, 'enddate_ts' => null];
      $cache[$닉] = $info;
      return $info;
    }

    $info = [
      '적용중' => true,
      '남은일수' => (int)floor(($ts - time()) / 86400),
      'idx' => (int)$row['idx'],
      'enddate_ts' => $ts,
    ];
    $cache[$닉] = $info;
    return $info;
  }
}

if (!function_exists('yangdo_일일제한_검사')) {
  function yangdo_일일제한_검사(string $닉, ?int $완료수 = null, ?array $지호 = null, ?array $마법 = null): ?string {
    // function.php 공통 규칙 우선 (지호 1시간 · 없으면 마법 1일)
    if (function_exists('양도_일일제한_검사') && $완료수 === null && $지호 === null && $마법 === null) {
      return 양도_일일제한_검사($닉);
    }

    $닉 = trim($닉);
    $완료수 = $완료수 ?? yangdo_오늘완료수($닉);
    if ($완료수 < 1) {
      return null;
    }

    $지호 = $지호 ?? yangdo_지호_정보($닉);
    if ($지호['남은시간'] >= 1 && $지호['적용중']) {
      return null;
    }

    $마법 = $마법 ?? yangdo_마법_정보($닉);
    if ($마법['남은일수'] >= 1 && $마법['적용중']) {
      return null;
    }

    return "❌ [ {$닉} ] 오늘 `.양도` {$완료수}회 사용했어요.\n본방·게임방 합산 하루 1회 무료 — 추가 양도는 지호 1시간 이상 또는 마법 1일 이상 필요 (지호 {$지호['남은시간']}시간 · 마법 {$마법['남은일수']}일)";
  }
}

if (!function_exists('yangdo_지호_소비')) {
  function yangdo_지호_소비(string $닉, ?array $지호 = null): string {
    $닉 = trim($닉);
    if ($닉 === '') {
      return '';
    }
    $지호 = $지호 ?? yangdo_지호_정보($닉);
    if (empty($지호['idx']) || empty($지호['enddate_ts'])) {
      return '';
    }

    $idx = (int)$지호['idx'];
    $종료_ts = (int)$지호['enddate_ts'];
    if ($종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = {$idx}");
      return '(지호 1시간 차감 · 버프 종료)';
    }

    $새종료_ts = $종료_ts - 3600;
    if ($새종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = {$idx}");
      return '(지호 1시간 차감 · 버프 종료)';
    }

    $새종료 = date('Y-m-d H:i', $새종료_ts);
    $새종료_esc = addslashes($새종료);
    db_query("UPDATE tb_item_use SET enddate = '{$새종료_esc}' WHERE idx = {$idx}");
    return '(지호 1시간 차감 · ' . date('m-d H:i', $새종료_ts) . ' 까지)';
  }
}

if (!function_exists('yangdo_마법_소비')) {
  /** 추가 양도 시 마법 버프 종료시각 1일 차감 */
  function yangdo_마법_소비(string $닉, ?array $마법 = null): string {
    $닉 = trim($닉);
    if ($닉 === '') {
      return '';
    }
    $마법 = $마법 ?? yangdo_마법_정보($닉);
    if (empty($마법['idx']) || empty($마법['enddate_ts'])) {
      return '';
    }

    $idx = (int)$마법['idx'];
    $종료_ts = (int)$마법['enddate_ts'];
    if ($종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = {$idx}");
      return '(마법 1일 차감 · 버프 종료)';
    }

    $새종료_ts = $종료_ts - 86400;
    if ($새종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = {$idx}");
      return '(마법 1일 차감 · 버프 종료)';
    }

    $새종료 = date('Y-m-d H:i', $새종료_ts);
    $새종료_esc = addslashes($새종료);
    db_query("UPDATE tb_item_use SET enddate = '{$새종료_esc}' WHERE idx = {$idx}");
    return '(마법 1일 차감 · ' . date('m-d H:i', $새종료_ts) . ' 까지)';
  }
}

if (!function_exists('yangdo_추가양도_소비')) {
  /** 지호(1시간) 우선 · 없으면 마법(1일) */
  function yangdo_추가양도_소비(string $닉, ?array $지호 = null, ?array $마법 = null): string {
    if (function_exists('양도_추가양도_소비') && $지호 === null && $마법 === null) {
      return 양도_추가양도_소비($닉);
    }
    $지호 = $지호 ?? yangdo_지호_정보($닉);
    if (!empty($지호['적용중']) && (int)($지호['남은시간'] ?? 0) >= 1) {
      return yangdo_지호_소비($닉, $지호);
    }
    $마법 = $마법 ?? yangdo_마법_정보($닉);
    if (!empty($마법['적용중']) && (int)($마법['남은일수'] ?? 0) >= 1) {
      return yangdo_마법_소비($닉, $마법);
    }
    return '';
  }
}

if (!function_exists('yangdo_게임냥_표시')) {
  function yangdo_게임냥_표시($금액): string {
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($금액, '');
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, '');
    }
    if (function_exists('냥_숫자콤마')) {
      return 냥_숫자콤마($금액);
    }
    return yangdo_amount_sql($금액);
  }
}

if (!function_exists('yangdo_게임냥_실행')) {
  function yangdo_게임냥_실행(string $닉, string $receiver, $amount_raw, ?array $회원정보 = null): array {
    yangdo_db_준비();
    if (!function_exists('냥_금액_파싱_문자열') && !function_exists('냥_정수문자열')) {
      $fn = __DIR__ . '/function.php';
      if (is_file($fn)) {
        require_once $fn;
      }
    }
    $닉 = trim($닉);
    // 천경·해 등 PHP_INT_MAX 초과 — (int)냥_금액_파싱 금지, 문자열 파싱
    if (function_exists('냥_금액_파싱_문자열')) {
      $amount = 냥_금액_파싱_문자열((string)$amount_raw);
    } elseif (function_exists('냥_금액_파싱')) {
      $amount = yangdo_amount_sql(냥_금액_파싱((string)$amount_raw));
    } else {
      $amount = yangdo_amount_sql($amount_raw);
    }

    $받는 = yangdo_받는이_조회($receiver);
    if ($받는 === null) {
      return ['ok' => false, 'data' => "[ {$receiver} ] 회원을 찾을 수 없습니다."];
    }
    $receiver = $받는['name'];
    $받는_idx = (int)($받는['idx'] ?? 0);
    if ($받는_idx < 1) {
      return ['ok' => false, 'data' => "[ {$receiver} ] 회원을 찾을 수 없습니다."];
    }

    if ($닉 === '' || $receiver === '' || $amount === '0' || !yangdo_amount_gte($amount, '1')) {
      return ['ok' => false, 'data' => '받는 사람과 금액을 확인해주세요.'];
    }
    if (!yangdo_amount_gte($amount, '30000')) {
      return ['ok' => false, 'data' => '게임냥 양도는 30,000냥 이상만 가능합니다.'];
    }
    if ($receiver === $닉) {
      return ['ok' => false, 'data' => '본인에게는 양도할 수 없습니다.'];
    }

    $오늘완료 = yangdo_오늘완료수($닉);
    $추가양도 = ($오늘완료 >= 1);
    $지호 = $추가양도 ? yangdo_지호_정보($닉) : null;
    $마법 = $추가양도 ? yangdo_마법_정보($닉) : null;

    $양도제한 = yangdo_일일제한_검사($닉, $오늘완료, $지호, $마법);
    if ($양도제한 !== null) {
      return ['ok' => false, 'data' => $양도제한];
    }

    if ($추가양도) {
      $지호가능 = !empty($지호['적용중']) && (int)($지호['남은시간'] ?? 0) >= 1;
      $마법가능 = !empty($마법['적용중']) && (int)($마법['남은일수'] ?? 0) >= 1;
      if (!$지호가능 && !$마법가능) {
        return ['ok' => false, 'data' => yangdo_일일제한_검사($닉, $오늘완료, $지호, $마법) ?? '추가 양도는 지호 1시간 이상 또는 마법 1일 이상 필요합니다.'];
      }
    }

    // 보유냥은 항상 CAST AS CHAR 로 재조회 (큰 DECIMAL 이 float/과학적표기로 깨지는 것 방지)
    $닉_esc = addslashes($닉);
    $송금자 = db_select("
      SELECT idx, CAST(IFNULL(point, 0) AS CHAR) AS point_str, level
      FROM tb_member
      WHERE name = '{$닉_esc}'
      LIMIT 1
    ");
    if (empty($송금자['idx'])) {
      $송금자 = db_select("
        SELECT idx, CAST(IFNULL(point, 0) AS CHAR) AS point_str, level
        FROM tb_member
        WHERE TRIM(name) = '{$닉_esc}'
        LIMIT 1
      ");
    }
    if (empty($송금자['idx'])) {
      return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }
    $송금_idx = (int)$송금자['idx'];
    $게임냥 = yangdo_amount_sql($송금자['point_str'] ?? '0');
    $level = (int)($송금자['level'] ?? ($회원정보['level'] ?? 0));

    $수수료 = yangdo_수수료_문자열($amount);
    if (!yangdo_amount_gte($게임냥, $amount)) {
      return ['ok' => false, 'data' => '게임냥이 부족합니다. (보유 ' . yangdo_게임냥_표시($게임냥) . '냥)'];
    }

    $수수료제외 = yangdo_수령액_문자열($amount);
    if (!yangdo_amount_gte($수수료제외, '1')) {
      return ['ok' => false, 'data' => '양도 금액이 너무 적습니다.'];
    }

    // member.point 만 DECIMAL 보장 — tb_point_log ALTER 금지(대용량 락 → 응답 없음)
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }

    global $conn;
    if (!($conn instanceof mysqli)) {
      return ['ok' => false, 'data' => 'DB 연결을 확인할 수 없습니다.'];
    }

    $amount_sql = yangdo_amount_sql($amount);
    $수수료제외_sql = yangdo_amount_sql($수수료제외);
    $수수료_sql = yangdo_로그금액_안전($수수료); // 금고 BIGINT 한도

    if (!@mysqli_begin_transaction($conn)) {
      return ['ok' => false, 'data' => '양도 처리에 실패했습니다. 잠시 후 다시 시도해주세요.'];
    }
    try {
      $ok_send = db_query("
        UPDATE tb_member
        SET point = point - {$amount_sql}
        WHERE idx = {$송금_idx}
          AND point >= {$amount_sql}
        LIMIT 1
      ");
      if ($ok_send === false || (int)mysqli_affected_rows($conn) < 1) {
        $err = yangdo_db_last_error();
        if ($err !== '' && (stripos($err, 'Out of range') !== false || stripos($err, '1264') !== false)) {
          throw new RuntimeException('overflow');
        }
        throw new RuntimeException('insufficient');
      }

      $ok_recv = db_query("
        UPDATE tb_member
        SET point = point + {$수수료제외_sql}
        WHERE idx = {$받는_idx}
        LIMIT 1
      ");
      if ($ok_recv === false || (int)mysqli_affected_rows($conn) < 1) {
        $err = yangdo_db_last_error();
        if ($err !== '' && (stripos($err, 'Out of range') !== false || stripos($err, '1264') !== false)) {
          throw new RuntimeException('overflow');
        }
        throw new RuntimeException('recv');
      }

      $ok_tax = db_query("UPDATE config SET tax = tax + {$수수료_sql}");
      if ($ok_tax === false) {
        $err = yangdo_db_last_error();
        if ($err !== '' && (stripos($err, 'Out of range') !== false || stripos($err, '1264') !== false)) {
          // 금고 BIGINT 한도 — 수수료 적립만 상한 재시도
          $ok_tax = db_query("UPDATE config SET tax = " . yangdo_bigint_상한());
        }
        if ($ok_tax === false) {
          throw new RuntimeException('tax');
        }
      }

      if (!yangdo_양도_로그_기록($닉, $receiver, $수수료, $amount)) {
        throw new RuntimeException('log');
      }

      $버프차감문구 = '';
      if ($추가양도) {
        $버프차감문구 = yangdo_추가양도_소비($닉, $지호, $마법);
      }

      if (!@mysqli_commit($conn)) {
        throw new RuntimeException('commit');
      }
    } catch (Throwable $e) {
      @mysqli_rollback($conn);
      $msg = ($e instanceof RuntimeException) ? $e->getMessage() : '';
      if ($msg === 'insufficient') {
        return ['ok' => false, 'data' => '게임냥이 부족합니다. (보유 ' . yangdo_게임냥_표시($게임냥) . '냥)'];
      }
      if ($msg === 'recv') {
        return ['ok' => false, 'data' => "받는 분({$receiver}) 계정에 입금하지 못했습니다. 닉네임을 확인 후 다시 시도해주세요."];
      }
      if ($msg === 'overflow') {
        return ['ok' => false, 'data' => '양도 금액이 저장 한도를 초과했습니다. 잠시 후 다시 시도하거나 관리자에게 문의해주세요.'];
      }
      $db_err = yangdo_db_last_error();
      if ($db_err !== '' && (stripos($db_err, 'Out of range') !== false || stripos($db_err, '1264') !== false)) {
        return ['ok' => false, 'data' => '양도 금액이 저장 한도를 초과했습니다. 잠시 후 다시 시도하거나 관리자에게 문의해주세요.'];
      }
      return ['ok' => false, 'data' => '양도 기록 저장에 실패했습니다. 잠시 후 다시 시도해주세요.'];
    }

    $회차 = $오늘완료 + 1;
    if ($회차 <= 1) {
      $회차문구 = '오늘 1회째(무료)';
    } else {
      $회차문구 = "오늘 {$회차}회째(추가)";
    }

    $결과문구 = "{$닉} → {$receiver} 게임냥 " . yangdo_게임냥_표시($수수료제외) . "냥 (수수료 " . yangdo_게임냥_표시($수수료) . "냥)\n[{$회차문구}]";
    if ($버프차감문구 !== '') {
      $결과문구 .= "\n" . $버프차감문구;
    }

    return ['ok' => true, 'data' => $결과문구];
  }
}

if (!function_exists('양도_일일현황_문구')) {
  function 양도_일일현황_문구(string $닉): string {
    $닉 = trim($닉);
    $오늘양도 = yangdo_오늘완료수($닉);
    if ($오늘양도 < 1) {
      return '';
    }
    $지호 = yangdo_지호_정보($닉);
    $마법 = yangdo_마법_정보($닉);
    $msg = "\n\n[오늘 양도 {$오늘양도}회 (본방·게임방 합산)";
    if ($오늘양도 >= 1 && $지호['남은시간'] < 1 && $마법['남은일수'] < 1) {
      $msg .= ' · 무료 1회 사용 완료 · 추가 양도 불가(지호/마법 없음)';
    } else {
      if ($지호['적용중']) {
        $msg .= " · 지호 남은 {$지호['남은시간']}시간";
      }
      if ($마법['적용중']) {
        $msg .= " · 마법 남은 {$마법['남은일수']}일";
      }
    }
    $msg .= ']';
    return $msg;
  }
}

if (!function_exists('양도_게임냥_안내')) {
  function 양도_게임냥_안내(string $단위, int $level): string {
    $보낼양 = '30000';
    $수수료 = yangdo_수수료_문자열($보낼양);
    $수령액 = yangdo_수령액_문자열($보낼양);
    $fail = "✅ {$단위} 양도\n\n.양도 받을닉 양도할 {$단위}수량";
    $fail .= "\n양도금액: 게임냥(point)";
    $fail .= "\n양도금액 기준 수수료 (금고 적립)";
    if (function_exists('양도수수료_안내문')) {
      $fail .= 양도수수료_안내문();
    } else {
      $fail .= "\n보낸 금액의 1% 수수료 (금고) · 나머지만 상대 수령 · 레벨 무관";
    }
    $fail .= "\n\n-유의사항";
    $fail .= "\n본방냥·게임냥 합쳐 하루 1회 무료";
    $fail .= "\n본방에서 오늘 양도했으면 게임방 양도 불가 (지호/마법으로 추가 가능)";
    $fail .= "\n지호 적용 시 남은 시간(1시간)마다 추가 양도 가능 (추가 1회당 지호 -1시간)";
    $fail .= "\n지호 없으면 마법 버프 시간으로 추가 양도 가능 (추가 1회당 마법 -1일)";
    $fail .= "\n지호·마법 모두 없으면 추가 양도 불가";
    $fail .= "\n최소 30000{$단위} 이상만 양도 가능";
    $fail .= "\n금액: 숫자 또는 만·억·조·경·천경·해 (예: 5조, 100억, 5천경, 1해)";
    $fail .= "\n현재 예상 수수료 {$수수료}냥 (30000{$단위} 전송 시 수령 {$수령액}{$단위})";
    return $fail;
  }
}

if (!function_exists('양도_게임냥_명령_처리')) {
  function 양도_게임냥_명령_처리(string $status, string $두자리닉넴, string $단위, int $level = 0, ?array $회원정보 = null): bool {
    if (strpos($status, '.양도') === false) {
      return false;
    }

    try {
      if (!function_exists('냥_금액_파싱_문자열') && !function_exists('냥_금액_파싱')) {
        require_once __DIR__ . '/function.php';
      }
      if (!function_exists('양도수수료_안내문')) {
        require_once __DIR__ . '/config.php';
      }

      $닉 = (is_array($회원정보) && !empty($회원정보['name']))
        ? trim((string)$회원정보['name'])
        : yangdo_닉_정규화($두자리닉넴);

      if (!preg_match('/\.양도\s*(.+)/u', $status, $match)) {
        $fail = 양도_게임냥_안내($단위, $level) . 양도_일일현황_문구($닉);
        echo 전송($fail);
        exit;
      }

      $after = trim($match[1]);
      if (!preg_match('/^([가-힣A-Za-z]+)\s*(.+)$/u', $after, $parts)) {
        $fail = 양도_게임냥_안내($단위, $level) . 양도_일일현황_문구($닉);
        echo 전송($fail);
        exit;
      }

      $받는이 = $parts[1];
      $금액raw = trim($parts[2]);
      // 천경·해는 PHP_INT_MAX 초과 → 문자열 파싱 (int 캐스팅 금지)
      if (function_exists('냥_금액_파싱_문자열')) {
        $보내는양 = 냥_금액_파싱_문자열($금액raw);
      } elseif (function_exists('냥_금액_파싱')) {
        $보내는양 = yangdo_amount_sql(냥_금액_파싱($금액raw));
      } else {
        $보내는양 = yangdo_amount_sql($금액raw);
      }
      if ($보내는양 === '0' || !yangdo_amount_gte($보내는양, '1')) {
        echo 전송("❌ 금액 형식을 확인해주세요.\n예) .양도 미니 5천경 / .양도 미니 1해 / .양도 도하 5조");
        exit;
      }

      $result = yangdo_게임냥_실행($닉, $받는이, $금액raw, $회원정보);
      if (empty($result['ok'])) {
        echo 전송((string)($result['data'] ?? '❌ 양도에 실패했습니다.'));
        exit;
      }
      echo 전송('💰 ' . (string)$result['data']);
      exit;
    } catch (Throwable $e) {
      echo 전송('❌ 양도 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
      exit;
    }
  }
}
