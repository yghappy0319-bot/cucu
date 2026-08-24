<?php
/** 홀짝 타짜(.타짜) 순이익 집계 — tb_odd_even_tyzza */

if (!function_exists('홀짝_타짜_로그순이익')) {
  function 홀짝_타짜_로그순이익($result, $bet, $delta_point) {
    $bet = (int)$bet;
    $delta = (int)$delta_point;
    $r = strtolower(trim((string)$result));
    if ($r === 'win' || $r === 'push') {
      return $delta - $bet;
    }
    return $delta;
  }
}

if (!function_exists('홀짝_타짜_순이익_반영')) {
  /** 판 정산 후 로그 INSERT 직후 호출 */
  function 홀짝_타짜_순이익_반영($nick, $result, $bet, $delta_point) {
    $nick = addslashes(trim((string)$nick));
    if ($nick === '') {
      return;
    }
    $net = (int)홀짝_타짜_로그순이익($result, $bet, $delta_point);
    @db_query("
      INSERT INTO tb_odd_even_tyzza (nick, total_nyang)
      VALUES ('{$nick}', {$net})
      ON DUPLICATE KEY UPDATE total_nyang = total_nyang + {$net}
    ");
  }
}

if (!function_exists('홀짝_타짜_집계_재구축')) {
  /** 로그 전체에서 집계 테이블 재생성 (최초 1회·불일치 시) */
  function 홀짝_타짜_집계_재구축() {
    @db_query('DELETE FROM tb_odd_even_tyzza');
    return (bool)@db_query("
      INSERT INTO tb_odd_even_tyzza (nick, total_nyang)
      SELECT
        l.nick,
        SUM(
          CASE
            WHEN l.result IN ('win', 'push') THEN COALESCE(l.delta_point, 0) - COALESCE(l.bet, 0)
            ELSE COALESCE(l.delta_point, 0)
          END
        ) AS total_nyang
      FROM tb_odd_even_log l
      WHERE l.result IN ('win', 'lose', 'push')
      GROUP BY l.nick
    ");
  }
}

if (!function_exists('홀짝_타짜_집계_행목록')) {
  /**
   * @param int $limit 0 이하면 전원
   * @return array<int, array{name:string,total_nyang:int}>
   */
  function 홀짝_타짜_집계_행목록($limit = 0) {
    $limit = (int)$limit;
    $limit_sql = ($limit > 0) ? "LIMIT {$limit}" : '';

    $rows = [];
    $rs = @db_query("
      SELECT nick AS name, total_nyang
      FROM tb_odd_even_tyzza
      ORDER BY total_nyang DESC, nick ASC
      {$limit_sql}
    ");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        if ($row && isset($row['name'])) {
          $rows[] = $row;
        }
      }
    }

    if (!empty($rows)) {
      return $rows;
    }

    $로그있음 = @db_select("SELECT 1 AS ok FROM tb_odd_even_log WHERE result IN ('win','lose','push') LIMIT 1");
    if (empty($로그있음['ok'])) {
      return [];
    }

    if (!홀짝_타짜_집계_재구축()) {
      return [];
    }

    $rs = @db_query("
      SELECT nick AS name, total_nyang
      FROM tb_odd_even_tyzza
      ORDER BY total_nyang DESC, nick ASC
      {$limit_sql}
    ");
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
