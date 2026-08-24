<?php
/**
 * 홀짝 순이익 집계 (tb_odd_even_profit) — .타짜·타짜 칭호용
 * 로그 INSERT 시 증분 반영, 최초 1회만 로그에서 백필
 * 천경·해 등 PHP_INT_MAX/BIGINT 초과 — 문자열·DECIMAL(40,0)
 */

if (!function_exists('홀짝_금액_정수문자열')) {
  /** 부호 유지 정수 문자열 (음수 순이익 허용) */
  function 홀짝_금액_정수문자열($v): string {
    if (is_int($v)) {
      return (string)$v;
    }
    $s = trim((string)$v);
    if ($s === '' || $s === '+' || $s === '-') {
      return '0';
    }
    $neg = false;
    if (isset($s[0]) && ($s[0] === '-' || $s[0] === '+')) {
      $neg = ($s[0] === '-');
      $s = substr($s, 1);
    }
    if (function_exists('냥_정수문자열')) {
      $digits = 냥_정수문자열($s);
    } else {
      $digits = ltrim(preg_replace('/[^\d]/', '', $s), '0') ?: '0';
    }
    if ($digits === '0') {
      return '0';
    }
    return $neg ? ('-' . $digits) : $digits;
  }
}

if (!function_exists('홀짝_금액_sql')) {
  function 홀짝_금액_sql($v): string {
    $s = 홀짝_금액_정수문자열($v);
    return preg_match('/^-?\d+$/', $s) ? $s : '0';
  }
}

if (!function_exists('홀짝_로그_순이익')) {
  /**
   * 승·무: delta−bet, 패: delta
   * @return string 순이익 (부호 포함 정수 문자열)
   */
  function 홀짝_로그_순이익($result, $delta_point, $bet) {
    $delta = 홀짝_금액_정수문자열($delta_point);
    $bet = 홀짝_금액_정수문자열($bet);
    // bet 은 양수 가정 — 부호 제거
    if (isset($bet[0]) && $bet[0] === '-') {
      $bet = substr($bet, 1);
    }
    $result = (string)$result;
    if ($result === 'lose') {
      return $delta;
    }
    if ($result === 'win' || $result === 'push') {
      if (function_exists('bcsub')) {
        return bcsub($delta, $bet, 0);
      }
      // bcmath 없을 때 — 작은 수만
      return (string)((int)$delta - (int)$bet);
    }
    return $delta;
  }
}

if (!function_exists('홀짝_순이익_테이블_보장')) {
  function 홀짝_순이익_테이블_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    if (function_exists('db_ensure_connection')) {
      db_ensure_connection();
    }
    @db_query("CREATE TABLE IF NOT EXISTS tb_odd_even_profit (
      nick VARCHAR(32) NOT NULL PRIMARY KEY,
      total_nyang DECIMAL(40,0) NOT NULL DEFAULT 0,
      regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_total_nyang (total_nyang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // DECIMAL 승격 여부는 프로세스당 1회만 점검 (요청마다 SHOW COLUMNS 방지용 플래그 파일)
    $colMarker = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR . 'kakao_odd_even_profit_decimal40.flag';
    if (is_file($colMarker)) {
      return;
    }

    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_profit LIKE 'total_nyang'");
    $type = strtolower((string)($col['Type'] ?? ''));
    if ($type !== '') {
      $need = false;
      if (strpos($type, 'decimal') === false) {
        $need = true;
      } elseif (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 40) {
        $need = true;
      }
      if ($need) {
        @db_query("ALTER TABLE tb_odd_even_profit MODIFY COLUMN total_nyang DECIMAL(40,0) NOT NULL DEFAULT 0");
      }
      @touch($colMarker);
    }
  }
}

if (!function_exists('홀짝_순이익_백필_필요시')) {
  /** 집계 테이블이 비었을 때 로그에서 1회 채움 */
  function 홀짝_순이익_백필_필요시() {
    static $tried = false;
    if ($tried) {
      return;
    }
    $tried = true;

    홀짝_순이익_테이블_보장();

    $cnt행 = db_select('SELECT COUNT(*) AS c FROM tb_odd_even_profit');
    if ((int)($cnt행['c'] ?? 0) > 0) {
      return;
    }

    $로그행 = db_select("SELECT COUNT(*) AS c FROM tb_odd_even_log WHERE result IN ('win','lose','push') LIMIT 1");
    if ((int)($로그행['c'] ?? 0) < 1) {
      return;
    }

    @db_query("
      INSERT INTO tb_odd_even_profit (nick, total_nyang, regdate, updated_at)
      SELECT
        l.nick,
        SUM(
          CASE l.result
            WHEN 'win' THEN COALESCE(l.delta_point, 0) - COALESCE(l.bet, 0)
            WHEN 'push' THEN COALESCE(l.delta_point, 0) - COALESCE(l.bet, 0)
            WHEN 'lose' THEN COALESCE(l.delta_point, 0)
            ELSE COALESCE(l.delta_point, 0)
          END
        ) AS total_nyang,
        MIN(l.regdate),
        NOW()
      FROM tb_odd_even_log l
      WHERE l.result IN ('win', 'lose', 'push')
      GROUP BY l.nick
      ON DUPLICATE KEY UPDATE
        total_nyang = VALUES(total_nyang),
        updated_at = NOW()
    ");
  }
}

if (!function_exists('홀짝_순이익_반영')) {
  /** tb_odd_even_log 1건과 동일한 순이익을 집계 테이블에 반영 */
  function 홀짝_순이익_반영($nick, $result, $delta_point, $bet) {
    $nick_esc = addslashes(trim((string)$nick));
    if ($nick_esc === '') {
      return;
    }
    $result = (string)$result;
    if (!in_array($result, ['win', 'lose', 'push'], true)) {
      return;
    }

    $delta = 홀짝_금액_sql(홀짝_로그_순이익($result, $delta_point, $bet));
    홀짝_순이익_테이블_보장();

    @db_query("
      INSERT INTO tb_odd_even_profit (nick, total_nyang, regdate, updated_at)
      VALUES ('{$nick_esc}', {$delta}, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        total_nyang = total_nyang + ({$delta}),
        updated_at = NOW()
    ");
  }
}

if (!function_exists('홀짝_비회원_데이터_정리')) {
  /**
   * tb_member 에 없는 닉의 홀짝 데이터 삭제
   * 로그 테이블 FULL SCAN급 DELETE 라 랭킹 조회 핫패스에서는 호출하지 말 것.
   * 기본은 6시간에 1회만 실행 (강제=$force).
   */
  function 홀짝_비회원_데이터_정리(bool $force = false): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;

    if (!$force) {
      $marker = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'kakao_odd_even_orphan_cleanup.flag';
      $ttl = 6 * 3600;
      if (is_file($marker) && (time() - (int)@filemtime($marker)) < $ttl) {
        return;
      }
    }

    // 집계·상태(소량) 먼저 — 로그(대량)는 배치 삭제
    @db_query("
      DELETE p FROM tb_odd_even_profit p
      LEFT JOIN tb_member m ON m.name = p.nick
      WHERE m.idx IS NULL
    ");
    @db_query("
      DELETE s FROM tb_odd_even_state s
      LEFT JOIN tb_member m ON m.name = s.nick
      WHERE m.idx IS NULL
    ");
    // multi-table DELETE 는 LIMIT 불가 → 서브쿼리 배치
    for ($i = 0; $i < 5; $i++) {
      $ok = @db_query("
        DELETE FROM tb_odd_even_log
        WHERE nick IN (
          SELECT nick FROM (
            SELECT l.nick
            FROM tb_odd_even_log l
            LEFT JOIN tb_member m ON m.name = l.nick
            WHERE m.idx IS NULL
            LIMIT 5000
          ) orphan_nicks
        )
      ");
      if (!$ok) {
        break;
      }
      global $conn;
      $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
      if ($affected < 1) {
        break;
      }
    }

    if (!$force) {
      $marker = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'kakao_odd_even_orphan_cleanup.flag';
      @touch($marker);
    }
  }
}

if (!function_exists('홀짝_타짜_랭킹_행목록')) {
  /**
   * @param int $limit 0 이하면 전원
   * @return list<array{name:string,total_nyang:string}>
   */
  function 홀짝_타짜_랭킹_행목록($limit = 20) {
    홀짝_순이익_테이블_보장();
    홀짝_순이익_백필_필요시();
    // 비회원 정리는 랭킹에서 제외 — INNER JOIN 으로 현재 회원만 표시

    $limit = (int)$limit;
    $limit_sql = ($limit > 0) ? "LIMIT {$limit}" : '';

    // tb_odd_even_profit = 홀짝 승·패·무 순이익 합계 (로그 증분 집계)
    // INNER JOIN tb_member — 현재 회원만 표시
    $sql = "
      SELECT
        m.name AS name,
        CAST(p.total_nyang AS CHAR) AS total_nyang
      FROM tb_odd_even_profit p
      INNER JOIN tb_member m ON m.name = p.nick
      ORDER BY p.total_nyang DESC, p.nick ASC
      {$limit_sql}
    ";

    $rows = [];
    $rs = @db_query($sql);
    if ($rs) {
      while ($row = db_fetch($rs)) {
        if ($row && isset($row['name'])) {
          $rows[] = $row;
        }
      }
    }
    return $rows;
  }
}
