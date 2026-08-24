<?php
/**
 * 로또 금액 컬럼 DECIMAL 확장 · 안전 합산 · total_amount 백필
 * BIGINT(~922경) 오버플로로 101회차+ 총합/당첨금 0 되는 문제 대응
 */

if (!function_exists('로또_금액컬럼_보장')) {
  /** tb_game_lotto / tb_game_lotto_result 금액 컬럼 → DECIMAL(65,0) */
  function 로또_금액컬럼_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;

    $widen = function (string $table, string $col): void {
      $info = @db_select("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
      if (empty($info['Field'])) {
        return;
      }
      $type = strtolower((string)($info['Type'] ?? ''));
      if (strpos($type, 'decimal(65') !== false || strpos($type, 'decimal(40') !== false) {
        return;
      }
      @db_query("ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` DECIMAL(65,0) NOT NULL DEFAULT 0");
    };

    $widen('tb_game_lotto', 'amount');
    $widen('tb_game_lotto', 'winnings');
    $widen('tb_game_lotto_result', 'total_amount');
    $widen('tb_game_lotto_result', 'last_amount');
  }
}

if (!function_exists('로또누적_컬럼보장')) {
  /**
   * config.로또누적 — 강화/채굴/홀짝/수리/주식 등 수수료 누적 (해 단위 · DECIMAL(65,0))
   * tb_game_lotto INSERT 대신 이 컬럼만 UPDATE
   */
  function 로또누적_컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $info = @db_select("SHOW COLUMNS FROM `config` LIKE '로또누적'");
    if (empty($info['Field'])) {
      @db_query("
        ALTER TABLE `config`
        ADD COLUMN `로또누적` DECIMAL(65,0) NOT NULL DEFAULT 0
        COMMENT '로또 수수료 누적(강화·채굴·홀짝·수리·주식 등)'
      ");
      return;
    }
    $type = strtolower((string)($info['Type'] ?? ''));
    if (strpos($type, 'decimal(65') === false) {
      @db_query("ALTER TABLE `config` MODIFY COLUMN `로또누적` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '로또 수수료 누적(강화·채굴·홀짝·수리·주식 등)'");
    }
  }
}

if (!function_exists('로또누적_정규화')) {
  function 로또누적_정규화($v): string {
    if (function_exists('로또_금액문자열')) {
      return 로또_금액문자열($v);
    }
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ($s === '' || $s === null) ? '0' : (ltrim($s, '0') ?: '0');
  }
}

if (!function_exists('로또누적_조회')) {
  /** config.로또누적 현재값 (문자열) */
  function 로또누적_조회(): string {
    로또누적_컬럼보장();
    $row = @db_select("SELECT CAST(IFNULL(`로또누적`, 0) AS CHAR) AS v FROM config LIMIT 1");
    return 로또누적_정규화($row['v'] ?? 0);
  }
}

if (!function_exists('로또누적_가산')) {
  /** 수수료 적립 — config.로또누적 += 금액 (로그/INSERT 없음) */
  function 로또누적_가산($amount): string {
    $add = 로또누적_정규화($amount);
    if ($add === '0') {
      return 로또누적_조회();
    }
    로또누적_컬럼보장();
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($add) : preg_replace('/\D/', '', $add);
    if ($sql === '' || $sql === null) {
      $sql = '0';
    }
    // CAST로 해 단위 거액 가산 안전
    @db_query("
      UPDATE config
      SET `로또누적` = CAST(IFNULL(`로또누적`, 0) AS DECIMAL(65,0)) + CAST({$sql} AS DECIMAL(65,0))
      LIMIT 1
    ");
    return 로또누적_조회();
  }
}

if (!function_exists('로또누적_차감')) {
  /**
   * config.로또누적에서 금액 차감 (0 미만 방지)
   * @return string 실제 차감된 금액
   */
  function 로또누적_차감($amount): string {
    $want = 로또누적_정규화($amount);
    if ($want === '0') {
      return '0';
    }
    로또누적_컬럼보장();
    $have = 로또누적_조회();
    if ($have === '0') {
      return '0';
    }
    $take = $want;
    if (function_exists('bccomp') && bccomp($want, $have, 0) > 0) {
      $take = $have;
    } elseif (!function_exists('bccomp') && function_exists('냥_정수_미만') && 냥_정수_미만($have, $want)) {
      $take = $have;
    } elseif (!function_exists('bccomp') && strlen((string)$want) > strlen((string)$have)) {
      $take = $have;
    }
    if ($take === '0') {
      return '0';
    }
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($take) : preg_replace('/\D/', '', $take);
    if ($sql === '' || $sql === null) {
      return '0';
    }
    @db_query("
      UPDATE config
      SET `로또누적` = GREATEST(
        0,
        CAST(IFNULL(`로또누적`, 0) AS DECIMAL(65,0)) - CAST({$sql} AS DECIMAL(65,0))
      )
      LIMIT 1
    ");
    return $take;
  }
}

if (!function_exists('로또누적_초기화')) {
  /** 추첨/지급 시 회차 총액에 반영한 뒤 0으로 */
  function 로또누적_초기화(): void {
    로또누적_컬럼보장();
    @db_query("UPDATE config SET `로또누적` = 0 LIMIT 1");
  }
}

if (!function_exists('로또누적_합산')) {
  /** 표 합 + config.로또누적 */
  function 로또누적_합산(string $표합): string {
    $a = 로또누적_정규화($표합);
    $b = 로또누적_조회();
    if ($b === '0') {
      return $a;
    }
    if ($a === '0') {
      return $b;
    }
    if (function_exists('bcadd')) {
      return bcadd($a, $b, 0);
    }
    return 로또누적_정규화((string)(((float)$a) + (float)$b));
  }
}

if (!function_exists('로또누적_수수료행_이관')) {
  /**
   * 진행 회차에 INSERT로 쌓인 수수료 행 → config.로또누적 이관 후 삭제
   * 대상: amount>0 · 번호(0,0,0) · nick≠이월금 (구매 표·이월금 제외)
   *
   * @return array{ok:bool,drow:int,이관금액:string,삭제건수:int,msg:string}
   */
  function 로또누적_수수료행_이관(int $drow = 0): array {
    if ($drow <= 0) {
      if (function_exists('로또_진행회차')) {
        $drow = (int)로또_진행회차();
      } else {
        $진행행 = @db_select("SELECT IFNULL(MAX(drow), 0) AS max_drow FROM tb_game_lotto_result");
        $drow = (int)($진행행['max_drow'] ?? 0) + 1;
      }
    }
    $drow = (int)$drow;
    if ($drow < 1) {
      return ['ok' => false, 'drow' => 0, '이관금액' => '0', '삭제건수' => 0, 'msg' => '진행 회차 없음'];
    }

    $합행 = @db_select("
      SELECT
        CAST(IFNULL(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS total_amount,
        COUNT(*) AS cnt
      FROM tb_game_lotto
      WHERE drow = {$drow}
        AND status = 0
        AND amount > 0
        AND IFNULL(num1, 0) = 0
        AND IFNULL(num2, 0) = 0
        AND IFNULL(num3, 0) = 0
        AND nick <> '이월금'
    ");
    $이관금액 = 로또누적_정규화($합행['total_amount'] ?? 0);
    $삭제대상 = (int)($합행['cnt'] ?? 0);
    if ($이관금액 === '0' || $삭제대상 < 1) {
      return [
        'ok' => true,
        'drow' => $drow,
        '이관금액' => '0',
        '삭제건수' => 0,
        'msg' => "{$drow}회차 이관할 수수료 행 없음",
      ];
    }

    로또누적_가산($이관금액);
    global $conn;
    $삭제건수 = 0;
    for ($i = 0; $i < 500; $i++) {
      $ok = @db_query("
        DELETE FROM tb_game_lotto
        WHERE drow = {$drow}
          AND status = 0
          AND amount > 0
          AND IFNULL(num1, 0) = 0
          AND IFNULL(num2, 0) = 0
          AND IFNULL(num3, 0) = 0
          AND nick <> '이월금'
        LIMIT 50000
      ");
      if (!$ok) {
        break;
      }
      $aff = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
      $삭제건수 += $aff;
      if ($aff < 50000) {
        break;
      }
    }
    if (function_exists('로또_회차금액_진행중합_캐시무효')) {
      로또_회차금액_진행중합_캐시무효($drow);
    }
    // 표합(이월·구매)만 풀 재시드
    if (function_exists('로또_회차금액_금액행합') && function_exists('로또_회차풀_쓰기')) {
      $표합 = 로또_회차금액_금액행합($drow);
      로또_회차풀_쓰기($drow, $표합);
      if (function_exists('로또_회차금액_캐시쓰기')) {
        로또_회차금액_캐시쓰기($drow, $표합);
      }
    }

    return [
      'ok' => true,
      'drow' => $drow,
      '이관금액' => $이관금액,
      '삭제건수' => $삭제건수,
      'msg' => "{$drow}회차 수수료 {$이관금액} → config.로또누적 · 행 {$삭제건수}건 삭제",
    ];
  }
}

if (!function_exists('로또_회차금액_안전합')) {
  /**
   * 회차 티켓 amount 합 (DECIMAL 합산 · status 무관 — 추첨 후 status=1 포함)
   */
  function 로또_회차금액_안전합(int $drow): string {
    $drow = (int)$drow;
    if ($drow <= 0) {
      return '0';
    }
    로또_금액컬럼_보장();
    // amount>0 — 티켓(0원) 대량 행 스캔 제외 (합에는 영향 없음)
    $row = @db_select("
      SELECT CAST(IFNULL(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS total_amount
      FROM tb_game_lotto
      WHERE drow = {$drow} AND amount > 0
    ");
    if (function_exists('로또_금액문자열')) {
      return 로또_금액문자열($row['total_amount'] ?? 0);
    }
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($row['total_amount'] ?? 0);
    }
    $s = preg_replace('/[^\d]/', '', (string)($row['total_amount'] ?? '0'));
    return ($s === '' || $s === null) ? '0' : (ltrim($s, '0') ?: '0');
  }
}

if (!function_exists('로또_회차풀_테이블보장')) {
  /** 회차 누적금 1행 카운터 — 티켓(amount=0) 수십만 행 SUM 회피 */
  function 로또_회차풀_테이블보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_game_lotto_pool (
        drow INT NOT NULL,
        amount DECIMAL(65,0) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (drow)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists('로또_회차풀_정규화')) {
  function 로또_회차풀_정규화($v): string {
    if (function_exists('로또_금액문자열')) {
      return 로또_금액문자열($v);
    }
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ($s === '' || $s === null) ? '0' : (ltrim($s, '0') ?: '0');
  }
}

if (!function_exists('로또_회차풀_읽기')) {
  /** @return string|null 없으면 null */
  function 로또_회차풀_읽기(int $drow): ?string {
    $drow = (int)$drow;
    if ($drow < 1) {
      return null;
    }
    로또_회차풀_테이블보장();
    $row = @db_select("
      SELECT CAST(IFNULL(amount, 0) AS CHAR) AS amount
      FROM tb_game_lotto_pool
      WHERE drow = {$drow}
      LIMIT 1
    ");
    if (!is_array($row) || !array_key_exists('amount', $row)) {
      return null;
    }
    return 로또_회차풀_정규화($row['amount']);
  }
}

if (!function_exists('로또_회차풀_쓰기')) {
  function 로또_회차풀_쓰기(int $drow, string $sum): void {
    $drow = (int)$drow;
    if ($drow < 1) {
      return;
    }
    $sum = 로또_회차풀_정규화($sum);
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($sum) : preg_replace('/\D/', '', $sum);
    if ($sql === '' || $sql === null) {
      $sql = '0';
    }
    로또_회차풀_테이블보장();
    @db_query("
      INSERT INTO tb_game_lotto_pool (drow, amount, updated_at)
      VALUES ({$drow}, {$sql}, NOW())
      ON DUPLICATE KEY UPDATE amount = {$sql}, updated_at = NOW()
    ");
  }
}

if (!function_exists('로또_회차풀_가산')) {
  function 로또_회차풀_가산(int $drow, $amount): void {
    $drow = (int)$drow;
    if ($drow < 1) {
      return;
    }
    $add = 로또_회차풀_정규화($amount);
    if ($add === '0') {
      return;
    }
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($add) : preg_replace('/\D/', '', $add);
    로또_회차풀_테이블보장();
    @db_query("
      INSERT INTO tb_game_lotto_pool (drow, amount, updated_at)
      VALUES ({$drow}, {$sql}, NOW())
      ON DUPLICATE KEY UPDATE amount = amount + {$sql}, updated_at = NOW()
    ");
  }
}

if (!function_exists('로또_회차금액_표합만')) {
  /**
   * 회차 표(이월·냥구매) 합만 — config.로또누적 미포함
   * pool / SUM(amount>0)
   */
  function 로또_회차금액_표합만(int $drow): string {
    $drow = (int)$drow;
    if ($drow <= 0) {
      return '0';
    }

    static $mem = [];
    $now = time();
    $freshTtl = 120;
    $staleTtl = 3600;
    if (isset($mem[$drow]) && ($now - (int)$mem[$drow]['t']) < $freshTtl) {
      return (string)$mem[$drow]['v'];
    }

    $pool = 로또_회차풀_읽기($drow);
    if ($pool !== null) {
      $mem[$drow] = ['t' => $now, 'v' => $pool];
      if (function_exists('로또_회차금액_캐시쓰기')) {
        로또_회차금액_캐시쓰기($drow, $pool);
      }
      return $pool;
    }

    $staleVal = null;
    $staleAge = null;
    $cur = function_exists('로또_회차금액_캐시읽기') ? 로또_회차금액_캐시읽기($drow) : null;
    if ($cur !== null) {
      $age = $now - (int)$cur['t'];
      if ($age < $staleTtl) {
        $staleVal = $cur['v'];
        $staleAge = $age;
      }
    }

    $sum = function_exists('로또_회차금액_금액행합')
      ? 로또_회차금액_금액행합($drow)
      : '0';
    if ($sum === '0' && $staleVal !== null && $staleVal !== '0') {
      $mem[$drow] = ['t' => $now - (int)$staleAge, 'v' => $staleVal];
      return $staleVal;
    }

    $mem[$drow] = ['t' => $now, 'v' => $sum];
    로또_회차풀_쓰기($drow, $sum);
    if (function_exists('로또_회차금액_캐시쓰기')) {
      로또_회차금액_캐시쓰기($drow, $sum);
    }
    return $sum;
  }
}

if (!function_exists('로또_회차금액_진행중합')) {
  /**
   * 진행 중 회차 표시 총액 = 표합(이월·구매) + config.로또누적(수수료)
   * 진행 회차가 아닐 때는 표합만
   */
  function 로또_회차금액_진행중합(int $drow): string {
    $drow = (int)$drow;
    if ($drow <= 0) {
      return '0';
    }
    $표합 = 로또_회차금액_표합만($drow);
    $진행 = 0;
    if (function_exists('로또_진행회차')) {
      $진행 = (int)로또_진행회차();
    } else {
      $진행행 = @db_select("SELECT IFNULL(MAX(drow), 0) AS max_drow FROM tb_game_lotto_result");
      $진행 = (int)($진행행['max_drow'] ?? 0) + 1;
    }
    if ($drow === $진행) {
      return 로또누적_합산($표합);
    }
    return $표합;
  }
}

if (!function_exists('로또_회차금액_캐시경로')) {
  function 로또_회차금액_캐시경로(int $drow): string {
    return dirname(__DIR__, 2) . '/data/lotto_pool_' . (int)$drow . '.cache';
  }
}

if (!function_exists('로또_회차금액_캐시읽기')) {
  /** @return array{v:string,t:int}|null */
  function 로또_회차금액_캐시읽기(int $drow): ?array {
    $cacheFile = 로또_회차금액_캐시경로($drow);
    if (!is_file($cacheFile)) {
      return null;
    }
    $raw = @file_get_contents($cacheFile);
    if (!is_string($raw) || $raw === '') {
      return null;
    }
    $parts = explode("\n", $raw, 2);
    $ts = (int)($parts[0] ?? 0);
    $val = trim((string)($parts[1] ?? ''));
    if ($ts < 1 || $val === '' || !preg_match('/^\d+$/', $val)) {
      return null;
    }
    return ['v' => $val, 't' => $ts];
  }
}

if (!function_exists('로또_회차금액_캐시쓰기')) {
  function 로또_회차금액_캐시쓰기(int $drow, string $sum): void {
    $drow = (int)$drow;
    $sum = preg_replace('/\D/', '', $sum) ?: '0';
    $sum = ltrim($sum, '0') ?: '0';
    $dir = dirname(__DIR__, 2) . '/data';
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
    @file_put_contents(로또_회차금액_캐시경로($drow), time() . "\n" . $sum);
  }
}

if (!function_exists('로또_회차금액_금액행합')) {
  /** status=0 · amount>0 만 SUM (티켓 0원 행 제외) */
  function 로또_회차금액_금액행합(int $drow): string {
    $drow = (int)$drow;
    if ($drow < 1) {
      return '0';
    }
    $row = @db_select("
      SELECT CAST(IFNULL(SUM(amount), 0) AS CHAR) AS total_amount
      FROM tb_game_lotto
      WHERE drow = {$drow} AND status = 0 AND amount > 0
    ");
    return 로또_회차풀_정규화($row['total_amount'] ?? 0);
  }
}

if (!function_exists('로또_회차금액_캐시가산')) {
  /** 구매·수수료·이월 시 풀/캐시 가산 — .로또 가 대량 SUM에 안 걸리게 */
  function 로또_회차금액_캐시가산(int $drow, $amount): void {
    $drow = (int)$drow;
    if ($drow < 1) {
      return;
    }
    $add = 로또_회차풀_정규화($amount);
    if ($add === '0') {
      return;
    }
    $pool = 로또_회차풀_읽기($drow);
    if ($pool !== null) {
      로또_회차풀_가산($drow, $add);
      if (function_exists('bcadd')) {
        $sum = bcadd($pool, $add, 0);
      } else {
        $sum = ltrim(preg_replace('/\D/', '', (string)(((float)$pool) + (float)$add)), '0') ?: '0';
      }
      로또_회차금액_캐시쓰기($drow, $sum);
      return;
    }
    // 풀 없음: INSERT 직후이므로 amount>0 SUM 1회로 시드 (가산 중복 금지)
    $sum = 로또_회차금액_금액행합($drow);
    로또_회차풀_쓰기($drow, $sum);
    로또_회차금액_캐시쓰기($drow, $sum);
  }
}

if (!function_exists('로또_회차금액_조회용')) {
  /** 채팅 `.로또` — 풀/캐시 우선 (티켓 대량 행 SUM 회피) */
  function 로또_회차금액_조회용(int $drow): string {
    $drow = (int)$drow;
    if ($drow <= 0) {
      return '0';
    }
    return 로또_회차금액_진행중합($drow);
  }
}

if (!function_exists('로또_채팅조회_문구')) {
  /**
   * 본방·홍보방 `.로또` 공통 문구 (가벼운 조회 전용)
   * @param int $내티켓 -1 이면 티켓 줄 생략
   */
  function 로또_채팅조회_문구(int $내티켓 = -1): string {
    $진행행 = @db_select("SELECT IFNULL(MAX(drow), 0) AS max_drow FROM tb_game_lotto_result");
    $진행회차 = (int)($진행행['max_drow'] ?? 0) + 1;
    $총액 = ($진행회차 > 0) ? (string)로또_회차금액_진행중합($진행회차) : '0';
    if ($총액 === '' || $총액 === null) {
      $총액 = '0';
    }

    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $표시 = static function ($amt) use ($단위표) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위표);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위표);
      }
      if (function_exists('로또_금액표시')) {
        return 로또_금액표시($amt, $단위표);
      }
      $s = preg_replace('/\D/', '', (string)$amt);
      return ($s === '' ? '0' : $s) . $단위표;
    };
    $비율 = static function ($amt, $pct) {
      $a = preg_replace('/\D/', '', (string)$amt) ?: '0';
      if (function_exists('bcdiv') && function_exists('bcmul')) {
        return bcdiv(bcmul($a, (string)(int)$pct, 0), '100', 0);
      }
      return '0';
    };
    $일등풀 = $비율($총액, 90);
    $일등지급풀 = $비율($일등풀, 70);
    $이등풀 = $비율($총액, 7);
    $삼등풀 = $비율($총액, 3);

    $msg = "🎟️ {$진행회차}회차 로또 | " . $표시($총액) . "\n\n";
    $미지급행 = @db_select("
      SELECT drow, num1, num2, num3
      FROM tb_game_lotto_result
      WHERE status = 0
      ORDER BY drow ASC
      LIMIT 1
    ");
    if (!empty($미지급행['drow'])) {
      $md = (int)$미지급행['drow'];
      $표시1 = str_pad((string)((int)$미지급행['num1']), 2, '0', STR_PAD_LEFT);
      $표시2 = str_pad((string)((int)$미지급행['num2']), 2, '0', STR_PAD_LEFT);
      $표시3 = str_pad((string)((int)$미지급행['num3']), 2, '0', STR_PAD_LEFT);
      $msg .= "⏳ {$md}회차 추첨 완료 · 지급 대기 ({$표시1},{$표시2},{$표시3})\n";
      $msg .= "→ `.로또 지급` 후 이월금이 반영됩니다.\n\n";
    }
    $msg .= "【🥇 1등이 있을 때】\n";
    $msg .= "· 1등: 총액 90% — 지급 70%·소멸 30% 후 등분 (지급풀 " . $표시($일등지급풀) . ")\n";
    $msg .= "· 2등: 총액 7% — 2등끼리 등분 (풀 " . $표시($이등풀) . ")\n";
    $msg .= "· 3등: 총액 3% — 3등끼리 등분 (풀 " . $표시($삼등풀) . ")\n\n";
    $msg .= "【1등이 없을 때】\n";
    $msg .= "· 🥈 2등: 총액 7% — 2등끼리 등분 (풀 " . $표시($이등풀) . ")\n";
    $msg .= "· 🥉 3등: 총액 3% — 3등끼리 등분 (풀 " . $표시($삼등풀) . ")\n";
    $msg .= "· 나머지 90% 이월(다음 회차)";
    $msg .= "\n\n1등: 3개 · 2등: 2개 · 3등: 1개 번호 일치";
    $msg .= "\n매일 23:00 추첨";
    if ($내티켓 >= 0) {
      $msg .= "\n\n🎟️ 내 로또 티켓: {$내티켓}장";
    }
    return $msg;
  }
}

if (!function_exists('로또_회차금액_진행중합_캐시무효')) {
  function 로또_회차금액_진행중합_캐시무효(int $drow): void {
    $drow = (int)$drow;
    if ($drow <= 0) {
      return;
    }
    $cacheFile = 로또_회차금액_캐시경로($drow);
    if (is_file($cacheFile)) {
      @unlink($cacheFile);
    }
  }
}

if (!function_exists('로또_금액_양수인가')) {
  function 로또_금액_양수인가($v): bool {
    if (function_exists('로또_금액양수')) {
      return 로또_금액양수($v);
    }
    $s = function_exists('로또_금액문자열')
      ? 로또_금액문자열($v)
      : (function_exists('냥_정수문자열') ? 냥_정수문자열($v) : preg_replace('/\D/', '', (string)$v));
    return $s !== '' && $s !== '0';
  }
}

if (!function_exists('로또_이월금_복구시도')) {
  /**
   * 이월금 amount=0 이고 직전 회차 last_amount>0 이면 복구
   * @return bool 복구 여부
   */
  function 로또_이월금_복구시도(int $drow): bool {
    $drow = (int)$drow;
    if ($drow <= 1) {
      return false;
    }
    로또_금액컬럼_보장();
    $prev = $drow - 1;
    $이월 = @db_select("
      SELECT idx, CAST(IFNULL(amount, 0) AS CHAR) AS amount
      FROM tb_game_lotto
      WHERE drow = {$drow} AND nick = '이월금'
      ORDER BY idx ASC
      LIMIT 1
    ");
    if (empty($이월['idx'])) {
      return false;
    }
    $cur = function_exists('로또_금액문자열')
      ? 로또_금액문자열($이월['amount'] ?? 0)
      : (string)($이월['amount'] ?? '0');
    if (로또_금액_양수인가($cur)) {
      return false;
    }
    $prevRow = @db_select("
      SELECT CAST(IFNULL(last_amount, 0) AS CHAR) AS last_amount,
             CAST(IFNULL(total_amount, 0) AS CHAR) AS total_amount
      FROM tb_game_lotto_result
      WHERE drow = {$prev}
      LIMIT 1
    ");
    $last = function_exists('로또_금액문자열')
      ? 로또_금액문자열($prevRow['last_amount'] ?? 0)
      : (string)($prevRow['last_amount'] ?? '0');
    if (!로또_금액_양수인가($last)) {
      return false;
    }
    $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($last) : $last;
    $idx = (int)$이월['idx'];
    @db_query("UPDATE tb_game_lotto SET amount = {$sql} WHERE idx = {$idx} LIMIT 1");
    return true;
  }
}

if (!function_exists('로또_total_amount_백필')) {
  /**
   * total_amount=0 인 회차를 티켓 합으로 재계산·저장
   * @return array{fixed:int,details:list<string>}
   */
  function 로또_total_amount_백필(int $fromDrow = 101): array {
    로또_금액컬럼_보장();
    $fromDrow = max(1, (int)$fromDrow);
    $fixed = 0;
    $details = [];

    $rs = @db_query("
      SELECT drow,
             CAST(IFNULL(total_amount, 0) AS CHAR) AS total_amount
      FROM tb_game_lotto_result
      WHERE drow >= {$fromDrow}
      ORDER BY drow ASC
    ");
    while ($rs && ($row = db_fetch($rs))) {
      $drow = (int)($row['drow'] ?? 0);
      if ($drow < 1) {
        continue;
      }
      $stored = function_exists('로또_금액문자열')
        ? 로또_금액문자열($row['total_amount'] ?? 0)
        : preg_replace('/\D/', '', (string)($row['total_amount'] ?? '0'));
      if ($stored === '' || $stored === null) {
        $stored = '0';
      } else {
        $stored = ltrim($stored, '0') ?: '0';
      }

      로또_이월금_복구시도($drow);
      $sum = 로또_회차금액_안전합($drow);

      // 저장된 값과 티켓 합이 다르면 갱신 (0·BIGINT 잘림·오버플로 찌꺼기 포함)
      $need = false;
      if (로또_금액_양수인가($sum)) {
        if ($stored === '0') {
          $need = true;
        } elseif (function_exists('bccomp')) {
          $need = (bccomp($stored, $sum, 0) !== 0);
        } else {
          $need = ($stored !== $sum);
        }
      }

      if (!$need) {
        continue;
      }
      $sql = function_exists('냥_SQL정수') ? 냥_SQL정수($sum) : $sum;
      @db_query("UPDATE tb_game_lotto_result SET total_amount = {$sql} WHERE drow = {$drow} LIMIT 1");
      $fixed++;
      $details[] = "{$drow}회차: {$stored} → {$sum}";
    }

    return ['fixed' => $fixed, 'details' => $details];
  }
}

if (!function_exists('로또_금액_초기화')) {
  /**
   * 로또 명령 진입 시 1회: 스키마 보장 + (필요 시) 백필
   * ※ 요청마다 전체 회차 SUM 스캔하지 않음 — 완료 마커로 재실행 차단
   */
  function 로또_금액_초기화(): void {
    static $ran = false;
    if ($ran) {
      return;
    }
    $ran = true;
    로또_금액컬럼_보장();

    $flagDir = dirname(__DIR__, 2) . '/data';
    $flagFile = $flagDir . '/lotto_amount_backfill.done';
    if (is_file($flagFile)) {
      return;
    }

    $r = 로또_total_amount_백필(101);
    if (!is_dir($flagDir)) {
      @mkdir($flagDir, 0755, true);
    }
    @file_put_contents(
      $flagFile,
      date('c') . ' fixed=' . (int)($r['fixed'] ?? 0) . "\n"
    );
  }
}
