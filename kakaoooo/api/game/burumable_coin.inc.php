<?php
/**
 * 부루마블 1등 주화 — tb_item / 가방 컬럼이 회차마다 늘지 않게 별도 테이블
 * 가상 sname: 제{회차}회부루마블1등
 */

if (!function_exists('부루마블주화인가')) {
  function 부루마블주화인가(string $sname): bool {
    return (bool)preg_match('/^제\d+회부루마블1등$/u', trim($sname));
  }
}

if (!function_exists('부루마블주화_회차')) {
  function 부루마블주화_회차(string $sname): int {
    if (preg_match('/^제(\d+)회부루마블1등$/u', trim($sname), $m)) {
      return (int)$m[1];
    }
    return 0;
  }
}

if (!function_exists('부루마블주화_sname')) {
  function 부루마블주화_sname(int $round): string {
    $round = max(1, $round);
    return '제' . $round . '회부루마블1등';
  }
}

if (!function_exists('부루마블주화_스키마보장')) {
  function 부루마블주화_스키마보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_burumable_coin (
        round INT UNSIGNED NOT NULL,
        midx INT UNSIGNED NOT NULL,
        nick VARCHAR(32) NOT NULL DEFAULT '',
        qty INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (round, midx),
        KEY k_midx (midx)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_burumable_coin_meta (
        k TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        last_round INT UNSIGNED NOT NULL DEFAULT 0
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    @db_query("INSERT IGNORE INTO tb_burumable_coin_meta (k, last_round) VALUES (1, 0)");
    부루마블주화_가방이관();
  }
}

if (!function_exists('부루마블주화_회차기록')) {
  /** 전량 매도 후에도 다음 회차가 되돌아가지 않게 최고 회차 보존 */
  function 부루마블주화_회차기록(int $round): void {
    $round = (int)$round;
    if ($round < 1) {
      return;
    }
    부루마블주화_스키마보장();
    @db_query("UPDATE tb_burumable_coin_meta SET last_round = GREATEST(last_round, {$round}) WHERE k = 1");
  }
}

if (!function_exists('부루마블주화_가방이관')) {
  /** 기존 가방 컬럼·tb_item 회차행 → 주화 테이블 (1회성) */
  function 부루마블주화_가방이관(): void {
    static $ran = false;
    if ($ran) {
      return;
    }
    $ran = true;
    $maxRound = 0;
    $cols = @db_query("SHOW COLUMNS FROM tb_member_item_bag");
    if ($cols) {
      while ($col = db_fetch($cols)) {
        $field = trim((string)($col['Field'] ?? ''));
        $round = 부루마블주화_회차($field);
        if ($round < 1) {
          continue;
        }
        $maxRound = max($maxRound, $round);
        $k = str_replace('`', '``', $field);
        $rs = @db_query("SELECT midx, nick, IFNULL(`{$k}`, 0) AS qty FROM tb_member_item_bag WHERE IFNULL(`{$k}`, 0) > 0");
        if ($rs) {
          while ($row = db_fetch($rs)) {
            $midx = (int)($row['midx'] ?? 0);
            $qty = (int)($row['qty'] ?? 0);
            $nick = trim((string)($row['nick'] ?? ''));
            if ($midx < 1 || $qty < 1) {
              continue;
            }
            $nick_esc = addslashes($nick !== '' ? $nick : (string)$midx);
            @db_query("
              INSERT INTO tb_burumable_coin (round, midx, nick, qty)
              VALUES ({$round}, {$midx}, '{$nick_esc}', {$qty})
              ON DUPLICATE KEY UPDATE
                qty = GREATEST(qty, VALUES(qty)),
                nick = '{$nick_esc}'
            ");
          }
        }
        @db_query("UPDATE tb_member_item_bag SET `{$k}` = 0 WHERE IFNULL(`{$k}`, 0) > 0");
      }
    }
    $rsItem = @db_query("SELECT sname FROM tb_item WHERE sname LIKE '제%회부루마블1등'");
    if ($rsItem) {
      while ($r = db_fetch($rsItem)) {
        $maxRound = max($maxRound, 부루마블주화_회차((string)($r['sname'] ?? '')));
      }
    }
    if ($maxRound > 0) {
      부루마블주화_회차기록($maxRound);
    }
    @db_query("DELETE FROM tb_item WHERE sname LIKE '제%회부루마블1등'");
    if (function_exists('item_bag_snames_flush')) {
      item_bag_snames_flush();
    }
  }
}

if (!function_exists('부루마블주화_다음회차')) {
  function 부루마블주화_다음회차(): int {
    부루마블주화_스키마보장();
    $max = 0;
    $meta = @db_select("SELECT last_round AS m FROM tb_burumable_coin_meta WHERE k = 1 LIMIT 1");
    $max = max($max, (int)($meta['m'] ?? 0));
    $row = @db_select("SELECT MAX(round) AS m FROM tb_burumable_coin");
    $max = max($max, (int)($row['m'] ?? 0));
    $cols = @db_query("SHOW COLUMNS FROM tb_member_item_bag");
    if ($cols) {
      while ($col = db_fetch($cols)) {
        $max = max($max, 부루마블주화_회차((string)($col['Field'] ?? '')));
      }
    }
    $rs = @db_query("SELECT sname FROM tb_item WHERE sname LIKE '제%회부루마블1등'");
    if ($rs) {
      while ($r = db_fetch($rs)) {
        $max = max($max, 부루마블주화_회차((string)($r['sname'] ?? '')));
      }
    }
    return $max + 1;
  }
}

if (!function_exists('부루마블주화_수량')) {
  function 부루마블주화_수량(int $midx, string $sname): int {
    $round = 부루마블주화_회차($sname);
    if ($midx < 1 || $round < 1) {
      return 0;
    }
    부루마블주화_스키마보장();
    $row = @db_select("SELECT qty FROM tb_burumable_coin WHERE round = {$round} AND midx = {$midx} LIMIT 1");
    return (int)($row['qty'] ?? 0);
  }
}

if (!function_exists('부루마블주화_지급')) {
  /** @return array{ok:bool,msg?:string,qty?:int} */
  function 부루마블주화_지급(int $midx, string $nick, string $sname, int $qty = 1): array {
    $round = 부루마블주화_회차($sname);
    $qty = (int)$qty;
    $nick = trim($nick);
    if ($midx < 1 || $round < 1 || $qty < 1) {
      return ['ok' => false, 'msg' => '지급 정보가 올바르지 않아요.'];
    }
    부루마블주화_스키마보장();
    $nick_esc = addslashes($nick !== '' ? $nick : (string)$midx);
    $ok = @db_query("
      INSERT INTO tb_burumable_coin (round, midx, nick, qty)
      VALUES ({$round}, {$midx}, '{$nick_esc}', {$qty})
      ON DUPLICATE KEY UPDATE
        qty = qty + {$qty},
        nick = '{$nick_esc}'
    ");
    if (!$ok) {
      return ['ok' => false, 'msg' => '부루마블 주화 지급 실패'];
    }
    부루마블주화_회차기록($round);
    return ['ok' => true, 'qty' => 부루마블주화_수량($midx, $sname)];
  }
}

if (!function_exists('부루마블주화_차감')) {
  /** @return array{ok:bool,msg?:string,qty?:int} */
  function 부루마블주화_차감(int $midx, string $nick, string $sname, int $qty = 1): array {
    $round = 부루마블주화_회차($sname);
    $qty = (int)$qty;
    if ($midx < 1 || $round < 1 || $qty < 1) {
      return ['ok' => false, 'msg' => '차감 정보가 올바르지 않아요.'];
    }
    부루마블주화_스키마보장();
    global $conn;
    $ok = @db_query("
      UPDATE tb_burumable_coin
      SET qty = qty - {$qty}
      WHERE round = {$round} AND midx = {$midx} AND qty >= {$qty}
      LIMIT 1
    ");
    $affected = (isset($conn) && ($conn instanceof mysqli)) ? (int)mysqli_affected_rows($conn) : 0;
    if (!$ok || $affected < 1) {
      $have = 부루마블주화_수량($midx, $sname);
      return ['ok' => false, 'msg' => "{$sname} 보유수량 {$have}개", 'qty' => $have];
    }
    @db_query("DELETE FROM tb_burumable_coin WHERE round = {$round} AND midx = {$midx} AND qty < 1 LIMIT 1");
    return ['ok' => true, 'qty' => 부루마블주화_수량($midx, $sname)];
  }
}

if (!function_exists('부루마블주화_보유목록')) {
  /**
   * @return list<array{name:string,label:string,count:int}>
   */
  function 부루마블주화_보유목록(int $midx): array {
    if ($midx < 1) {
      return [];
    }
    부루마블주화_스키마보장();
    $out = [];
    $rs = @db_query("SELECT round, qty FROM tb_burumable_coin WHERE midx = {$midx} AND qty > 0 ORDER BY round ASC");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $round = (int)($row['round'] ?? 0);
        $qty = (int)($row['qty'] ?? 0);
        if ($round < 1 || $qty < 1) {
          continue;
        }
        $name = 부루마블주화_sname($round);
        $표시 = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($name) : $name;
        $out[] = ['name' => $name, 'label' => $표시, 'count' => $qty];
      }
    }
    return $out;
  }
}

if (!function_exists('부루마블주화_유통량')) {
  function 부루마블주화_유통량(string $sname): int {
    $round = 부루마블주화_회차($sname);
    if ($round < 1) {
      return 0;
    }
    부루마블주화_스키마보장();
    $row = @db_select("SELECT IFNULL(SUM(qty), 0) AS c FROM tb_burumable_coin WHERE round = {$round}");
    return (int)($row['c'] ?? 0);
  }
}
