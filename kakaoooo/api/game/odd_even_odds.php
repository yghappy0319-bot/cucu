<?php
/**
 * 홀짝 정답 확률 (웹·info2·mutual 공통)
 * 하드모드 고정: 무 8%, 홀/짝 흔들림 ±30%p
 *
 * 유저픽이 있으면 고른 쪽 확률 깎기 (반대쪽으로 이동)
 * · 홀짝_깎기_활성 true  → 닉별 6시간 구간 고정 %p (기본 3~8 · 00/06/12/18시 갱신)
 * · 홀짝_깎기_활성 false → 깎기 0 (사전 봉인만 사용)
 * · 특별닉(새아)만 고정 1%p
 * · tb_member.`홀짝_깎기` + `홀짝_깎기날짜`(DATETIME) 에 저장
 * · `.깍기변경` 수동: 기존값 반전 (높음↔낮음)
 */

/** true=깎기 ON · false=깎기 OFF (코드에서 이 값만 바꿔서 껏다 켰다) */
if (!defined('홀짝_깎기_활성')) {
  define('홀짝_깎기_활성', true);
}

/** 닉별 깎기 랜덤 하한·상한 %p */
if (!defined('홀짝_깎기_최소퍼센트')) {
  define('홀짝_깎기_최소퍼센트', 3);
}
if (!defined('홀짝_깎기_최대퍼센트')) {
  define('홀짝_깎기_최대퍼센트', 8);
}

/** 자동 재배정 주기(초) · 기본 6시간 · 시계 정각 구간(0·6·12·18시) */
if (!defined('홀짝_깎기_갱신초')) {
  define('홀짝_깎기_갱신초', 6 * 3600);
}

/** 하위호환 — 멤버 미확인·폴백용 */
if (!defined('홀짝_깎기_기본퍼센트')) {
  define('홀짝_깎기_기본퍼센트', 3);
}

/** 특별 깎기 닉 (두자리 닉, 쉼표 구분) · 고정 %p */
if (!defined('홀짝_깎기_특별닉')) {
  define('홀짝_깎기_특별닉', '새아');
}
if (!defined('홀짝_깎기_특별퍼센트')) {
  define('홀짝_깎기_특별퍼센트', 1);
}

if (!isset($홀짝_확률모드)) {
  $홀짝_확률모드 = 'hard';
}

if (!function_exists('홀짝_깎기_활성인가')) {
  function 홀짝_깎기_활성인가(): bool {
    return defined('홀짝_깎기_활성') && (bool)홀짝_깎기_활성;
  }
}

if (!function_exists('홀짝_깎기_범위')) {
  /** @return array{0:int,1:int} [min,max] */
  function 홀짝_깎기_범위(): array {
    $min = defined('홀짝_깎기_최소퍼센트') ? (int)홀짝_깎기_최소퍼센트 : 3;
    $max = defined('홀짝_깎기_최대퍼센트') ? (int)홀짝_깎기_최대퍼센트 : 8;
    if ($min < 0) {
      $min = 0;
    }
    if ($max < $min) {
      $max = $min;
    }
    if ($max > 100) {
      $max = 100;
    }
    return [$min, $max];
  }
}

if (!function_exists('홀짝_깎기_갱신주기초')) {
  function 홀짝_깎기_갱신주기초(): int {
    $sec = defined('홀짝_깎기_갱신초') ? (int)홀짝_깎기_갱신초 : (6 * 3600);
    return max(60, $sec);
  }
}

if (!function_exists('홀짝_깎기_구간시작시각')) {
  /**
   * 현재 시각이 속한 깎기 구간 시작 timestamp (로컬 시계)
   * 예) 주기 6h → 00:00 / 06:00 / 12:00 / 18:00
   */
  function 홀짝_깎기_구간시작시각(?int $ts = null): int {
    $ts = $ts ?? time();
    $period = 홀짝_깎기_갱신주기초();
    $y = (int)date('Y', $ts);
    $m = (int)date('n', $ts);
    $d = (int)date('j', $ts);
    $midnight = mktime(0, 0, 0, $m, $d, $y);
    if ($midnight === false) {
      return $ts - ($ts % $period);
    }
    $elapsed = max(0, $ts - $midnight);
    $slot = (int)floor($elapsed / $period) * $period;
    return $midnight + $slot;
  }
}

if (!function_exists('홀짝_깎기_구간시작문자')) {
  function 홀짝_깎기_구간시작문자(?int $ts = null): string {
    return date('Y-m-d H:i:s', 홀짝_깎기_구간시작시각($ts));
  }
}

if (!function_exists('홀짝_깎기_닉정규화')) {
  function 홀짝_깎기_닉정규화($nick): string {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return '';
    }
    $nick = stripslashes($nick);
    if (function_exists('getTwoCharNick')) {
      $two = getTwoCharNick($nick);
      if (is_string($two) && $two !== '') {
        return $two;
      }
    }
    return $nick;
  }
}

if (!function_exists('홀짝_깎기_특별닉인가')) {
  function 홀짝_깎기_특별닉인가($nick): bool {
    $n = 홀짝_깎기_닉정규화($nick);
    if ($n === '') {
      return false;
    }
    $list = defined('홀짝_깎기_특별닉') ? (string)홀짝_깎기_특별닉 : '';
    foreach (preg_split('/\s*,\s*/u', $list) ?: [] as $one) {
      $one = 홀짝_깎기_닉정규화($one);
      if ($one !== '' && $one === $n) {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('홀짝_깎기_특별퍼센트값')) {
  function 홀짝_깎기_특별퍼센트값(): int {
    $v = defined('홀짝_깎기_특별퍼센트') ? (int)홀짝_깎기_특별퍼센트 : 1;
    if ($v < 0) {
      $v = 0;
    }
    if ($v > 100) {
      $v = 100;
    }
    return $v;
  }
}

if (!function_exists('홀짝_깎기_멤버컬럼보장')) {
  /**
   * tb_member 깎기 컬럼 보장 (최초 1회·실패 시에만)
   * · 핫패스에서는 호출하지 않음
   */
  function 홀짝_깎기_멤버컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    if (!function_exists('db_select') || !function_exists('db_query')) {
      return;
    }
    $c1 = @db_select("SHOW COLUMNS FROM tb_member LIKE '홀짝_깎기'");
    if (empty($c1['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `홀짝_깎기` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '홀짝 유저픽 깎기 %p (6시간)'");
    }
    $c2 = @db_select("SHOW COLUMNS FROM tb_member LIKE '홀짝_깎기날짜'");
    if (empty($c2['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `홀짝_깎기날짜` DATETIME NULL DEFAULT NULL COMMENT '홀짝_깎기 배정 시각(구간)'");
    } else {
      $type = strtolower((string)($c2['Type'] ?? ''));
      if ($type !== '' && strpos($type, 'datetime') === false && strpos($type, 'timestamp') === false) {
        @db_query("ALTER TABLE tb_member MODIFY COLUMN `홀짝_깎기날짜` DATETIME NULL DEFAULT NULL COMMENT '홀짝_깎기 배정 시각(구간)'");
      }
    }
  }
}

if (!function_exists('홀짝_깎기_랜덤값')) {
  function 홀짝_깎기_랜덤값(): int {
    [$min, $max] = 홀짝_깎기_범위();
    try {
      return (int)random_int($min, $max);
    } catch (Throwable $e) {
      return (int)$min;
    }
  }
}

if (!function_exists('홀짝_깎기_반전값')) {
  /**
   * 수동 `.깍기변경`용 · 높음↔낮음 대칭 반전
   * 예) 3~8: 3→8, 4→7, 5→6, 6→5, 7→4, 8→3
   * · 기존값이 범위 밖/없으면 랜덤
   */
  function 홀짝_깎기_반전값($이전): int {
    [$min, $max] = 홀짝_깎기_범위();
    $prev = (int)$이전;
    if ($prev < $min || $prev > $max) {
      return 홀짝_깎기_랜덤값();
    }
    return (int)($min + $max - $prev);
  }
}

if (!function_exists('홀짝_깎기_배정유효인가')) {
  /** 배정 시각이 현재 6시간 구간에 속하고 %가 범위 안이면 true */
  function 홀짝_깎기_배정유효인가($배정시각문자, int $pct): bool {
    [$min, $max] = 홀짝_깎기_범위();
    if ($pct < $min || $pct > $max) {
      return false;
    }
    $at = trim((string)$배정시각문자);
    if ($at === '') {
      return false;
    }
    $ts = strtotime($at);
    if ($ts === false) {
      return false;
    }
    return $ts >= 홀짝_깎기_구간시작시각();
  }
}

if (!function_exists('홀짝_깎기퍼센트_멤버일일')) {
  /**
   * 닉별 현재 6시간 구간 깎기 %p (없으면 3~8 뽑아 tb_member에 저장)
   * · 같은 구간(00/06/12/18)에는 고정 · 구간이 바뀌면 자동 재추첨
   */
  function 홀짝_깎기퍼센트_멤버일일($nick): int {
    static $cache = [];
    static $schemaTried = false;
    [$min, $max] = 홀짝_깎기_범위();
    $n = 홀짝_깎기_닉정규화($nick);
    if ($n === '') {
      return 홀짝_깎기_랜덤값();
    }
    if (isset($cache[$n])) {
      return (int)$cache[$n];
    }
    if (!function_exists('db_select') || !function_exists('db_query')) {
      $v = 홀짝_깎기_랜덤값();
      $cache[$n] = $v;
      return $v;
    }

    $esc = addslashes($n);
    $slotStart = 홀짝_깎기_구간시작문자();
    $sql = "
      SELECT IFNULL(`홀짝_깎기`, 0) AS cut_pct,
             DATE_FORMAT(`홀짝_깎기날짜`, '%Y-%m-%d %H:%i:%s') AS cut_at
      FROM tb_member
      WHERE name = '{$esc}'
      LIMIT 1
    ";
    $row = @db_select($sql);
    // 컬럼 미존재 시에만 스키마 보장 후 1회 재시도
    if (!is_array($row) && !$schemaTried) {
      $schemaTried = true;
      홀짝_깎기_멤버컬럼보장();
      $row = @db_select($sql);
    }
    if (!is_array($row)) {
      $v = 홀짝_깎기_랜덤값();
      $cache[$n] = $v;
      return $v;
    }

    $pct = (int)($row['cut_pct'] ?? 0);
    $at = trim((string)($row['cut_at'] ?? ''));
    if (홀짝_깎기_배정유효인가($at, $pct)) {
      $cache[$n] = $pct;
      return $pct;
    }

    $new = 홀짝_깎기_랜덤값();
    $slot_esc = addslashes($slotStart);
    // 동시 요청 시 구간당 1회만 갱신
    @db_query("
      UPDATE tb_member
      SET `홀짝_깎기` = {$new},
          `홀짝_깎기날짜` = '{$slot_esc}'
      WHERE name = '{$esc}'
        AND (
          `홀짝_깎기날짜` IS NULL
          OR `홀짝_깎기날짜` < '{$slot_esc}'
          OR IFNULL(`홀짝_깎기`, 0) < {$min}
          OR IFNULL(`홀짝_깎기`, 0) > {$max}
        )
      LIMIT 1
    ");

    $row2 = @db_select("
      SELECT IFNULL(`홀짝_깎기`, 0) AS cut_pct
      FROM tb_member
      WHERE name = '{$esc}'
      LIMIT 1
    ");
    $pct2 = (int)($row2['cut_pct'] ?? $new);
    if ($pct2 < $min || $pct2 > $max) {
      $pct2 = $new;
    }
    $cache[$n] = $pct2;
    return $pct2;
  }
}

if (!function_exists('홀짝_깎기_전체재배정')) {
  /**
   * 관리방 `.깍기변경` — 전원 깎기 즉시 재배정 (출퇴근/status 무관)
   * · 일반: 기존값 반전 (높음↔낮음) · 범위밖/없으면 3~8 랜덤
   * · 특별닉(새아): 고정 1%
   * · 배정시각 = 현재 6시간 구간 시작 (다음 구간까지 유지)
   * @return array{ok:bool,count:int,special:int,inverted:int,random:int,min:int,max:int,today:string,slot:string,msg?:string}
   */
  function 홀짝_깎기_전체재배정(): array {
    [$min, $max] = 홀짝_깎기_범위();
    $slotStart = 홀짝_깎기_구간시작문자();
    $out = [
      'ok' => false,
      'count' => 0,
      'special' => 0,
      'inverted' => 0,
      'random' => 0,
      'min' => $min,
      'max' => $max,
      'today' => $slotStart,
      'slot' => $slotStart,
    ];
    if (!function_exists('db_query') || !function_exists('db_fetch')) {
      $out['msg'] = 'DB 함수를 불러오지 못했어요.';
      return $out;
    }
    홀짝_깎기_멤버컬럼보장();
    $rs = @db_query("
      SELECT name, IFNULL(`홀짝_깎기`, 0) AS cut_pct
      FROM tb_member
      WHERE TRIM(IFNULL(name, '')) <> ''
    ");
    if (!$rs) {
      $out['msg'] = '회원 목록을 읽지 못했어요.';
      return $out;
    }
    $특별고정 = function_exists('홀짝_깎기_특별퍼센트값') ? 홀짝_깎기_특별퍼센트값() : 1;
    $slot_esc = addslashes($slotStart);
    while ($row = db_fetch($rs)) {
      $name = trim((string)($row['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $isSpecial = function_exists('홀짝_깎기_특별닉인가') && 홀짝_깎기_특별닉인가($name);
      $prev = (int)($row['cut_pct'] ?? 0);
      if ($isSpecial) {
        $pct = $특별고정;
        $out['special']++;
      } elseif ($prev >= $min && $prev <= $max) {
        $pct = 홀짝_깎기_반전값($prev);
        $out['inverted']++;
      } else {
        $pct = 홀짝_깎기_랜덤값();
        $out['random']++;
      }
      $esc = addslashes($name);
      @db_query("UPDATE tb_member
        SET `홀짝_깎기` = {$pct},
            `홀짝_깎기날짜` = '{$slot_esc}'
        WHERE name = '{$esc}'
        LIMIT 1");
      $out['count']++;
    }
    $out['ok'] = true;
    return $out;
  }
}

if (!function_exists('홀짝_깎기퍼센트_뽑기')) {
  /**
   * 깎기 ON이면 닉별 6시간 구간 고정 %p · OFF면 0
   * · 특별닉(새아)=고정 1% · 그외=tb_member 구간값(3~8) · 닉 없으면 즉시 랜덤 폴백
   * @param string|null $nick 두자리/원본 닉
   */
  function 홀짝_깎기퍼센트_뽑기($nick = null): int {
    if (!홀짝_깎기_활성인가()) {
      return 0;
    }
    if ($nick !== null && trim((string)$nick) !== '') {
      if (홀짝_깎기_특별닉인가($nick)) {
        return 홀짝_깎기_특별퍼센트값();
      }
      return 홀짝_깎기퍼센트_멤버일일($nick);
    }
    return 홀짝_깎기_랜덤값();
  }
}

if (!function_exists('홀짝_정답_선정')) {
  /**
   * @param string|null $모드 무시(하드 고정) · 하위호환용
   * @param int|null $유저픽 1=홀 2=짝 — 있으면 고른 쪽 확률 깎기
   * @param int|null $깎기퍼센트포인트 null=활성 시 랜덤/구간 · 비활성 시 0
   * @return int 1=홀 2=짝 3=무
   */
  function 홀짝_정답_선정($모드 = null, $유저픽 = null, $깎기퍼센트포인트 = null) {
    unset($모드);
    $무고정 = 80; // 8%
    $홀기본 = (int)((1000 - $무고정) / 2);
    $유동폭 = 300; // ±30%p

    $홀기준 = $홀기본 + random_int(-$유동폭, $유동폭);
    $짝기준 = 1000 - $무고정 - $홀기준;
    if ($홀기준 < 0) {
      $짝기준 += $홀기준;
      $홀기준 = 0;
    }
    if ($짝기준 < 0) {
      $홀기준 += $짝기준;
      $짝기준 = 0;
    }

    $유저픽 = (int)$유저픽;
    if (!홀짝_깎기_활성인가()) {
      $깎기 = 0;
    } elseif ($깎기퍼센트포인트 === null) {
      $깎기 = max(0, min(1000, 홀짝_깎기퍼센트_뽑기() * 10));
    } else {
      $깎기 = max(0, min(1000, (int)round(((float)$깎기퍼센트포인트) * 10)));
    }
    if ($유저픽 === 1 && $홀기준 > 0 && $깎기 > 0) {
      $옮김 = min($깎기, $홀기준);
      $홀기준 -= $옮김;
      $짝기준 += $옮김;
    } elseif ($유저픽 === 2 && $짝기준 > 0 && $깎기 > 0) {
      $옮김 = min($깎기, $짝기준);
      $짝기준 -= $옮김;
      $홀기준 += $옮김;
    }

    $정답주사위 = random_int(1, 1000);
    if ($정답주사위 <= $홀기준) {
      return 1;
    }
    if ($정답주사위 <= $홀기준 + $짝기준) {
      return 2;
    }
    return 3;
  }
}
