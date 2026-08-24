<?php
/**
 * 홀짝 순이익 집계 (tb_odd_even_profit) — .타짜·타짜 칭호용
 * 로그 INSERT 시 증분 반영, 최초 1회만 로그에서 백필
 */

if (!function_exists('홀짝_로그_순이익')) {
  /** 승·무: delta−bet, 패: delta */
  function 홀짝_로그_순이익($result, $delta_point, $bet) {
    $delta = (int)$delta_point;
    $bet = (int)$bet;
    $result = (string)$result;
    if ($result === 'lose') {
      return $delta;
    }
    if ($result === 'win' || $result === 'push') {
      return $delta - $bet;
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
      total_nyang BIGINT NOT NULL DEFAULT 0,
      regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_total_nyang (total_nyang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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

    $delta = 홀짝_로그_순이익($result, $delta_point, $bet);
    홀짝_순이익_테이블_보장();

    @db_query("
      INSERT INTO tb_odd_even_profit (nick, total_nyang, regdate, updated_at)
      VALUES ('{$nick_esc}', {$delta}, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        total_nyang = total_nyang + {$delta},
        updated_at = NOW()
    ");
  }
}

if (!function_exists('홀짝_타짜_랭킹_행목록')) {
  /**
   * @param int $limit 0 이하면 전원
   * @return list<array{name:string,total_nyang:int}>
   */
  function 홀짝_타짜_랭킹_행목록($limit = 20) {
    홀짝_순이익_테이블_보장();
    홀짝_순이익_백필_필요시();

    $limit = (int)$limit;
    $limit_sql = ($limit > 0) ? "LIMIT {$limit}" : '';

    $sql = "
      SELECT
        COALESCE(m.name, p.nick) AS name,
        p.total_nyang
      FROM tb_odd_even_profit p
      LEFT JOIN tb_member m ON m.name = p.nick
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
