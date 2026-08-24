<?php
/**
 * 홀짝 웹 — 3연승 이상 후원
 * · 후원액(당첨금 10%) → config.후원모금함 누적
 * · 수령은 민호만 가능 (후원모금함 → 게임냥)
 */

if (!defined('홀짝_후원_퍼센트')) {
  define('홀짝_후원_퍼센트', 10);
}

if (!function_exists('홀짝_하드후원_수신닉')) {
  function 홀짝_하드후원_수신닉(): string {
    return '민호';
  }
}

if (!function_exists('홀짝_하드후원_금액문자열')) {
  /** @return string 부호 없는 정수 문자열 */
  function 홀짝_하드후원_금액문자열($v): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ltrim((string)$s, '0') ?: '0';
  }
}

if (!function_exists('홀짝_하드후원_표시금액')) {
  function 홀짝_하드후원_표시금액($금액): string {
    if (function_exists('냥_경조억_축약문구')) {
      return 냥_경조억_축약문구($금액, '냥');
    }
    if (function_exists('냥_숫자콤마')) {
      return 냥_숫자콤마($금액) . '냥';
    }
    return number_format((float)$금액) . '냥';
  }
}

if (!function_exists('홀짝_하드후원_컬럼_확보')) {
  function 홀짝_하드후원_컬럼_확보() {
    static $done = false;
    if ($done || !function_exists('db_select') || !function_exists('db_query')) {
      return;
    }
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'hard_donate_offer'");
    if (empty($col)) {
      $after = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'payback_pool'");
      $afterClause = !empty($after) ? ' AFTER payback_pool' : '';
      @db_query("
        ALTER TABLE tb_odd_even_state
          ADD COLUMN hard_donate_offer DECIMAL(40,0) NOT NULL DEFAULT 0
            COMMENT '이지/하드 3연승+ 후원 기준액(당첨금). 0=없음'{$afterClause}
      ");
    }
    $done = true;
  }
}

if (!function_exists('홀짝_하드후원_컬럼_있음')) {
  function 홀짝_하드후원_컬럼_있음(): bool {
    static $has = null;
    if (!function_exists('db_select')) {
      return false;
    }
    if ($has !== null) {
      return $has;
    }
    홀짝_하드후원_컬럼_확보();
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'hard_donate_offer'");
    $has = !empty($col);
    return $has;
  }
}

if (!function_exists('홀짝_하드후원_닉_esc')) {
  /** 이미 escape된 닉도 한 번만 escape */
  function 홀짝_하드후원_닉_esc($닉_or_esc): string {
    $raw = trim((string)$닉_or_esc);
    if ($raw === '') {
      return '';
    }
    // 호출부에서 이미 addslashes 한 경우 이중 escape 방지
    if (strpos($raw, '\\') !== false) {
      $raw = stripslashes($raw);
    }
    return addslashes($raw);
  }
}

if (!function_exists('홀짝_하드후원_오퍼_조회')) {
  /** @return string */
  function 홀짝_하드후원_오퍼_조회($닉_esc): string {
    if (!홀짝_하드후원_컬럼_있음()) {
      return '0';
    }
    $닉 = 홀짝_하드후원_닉_esc($닉_esc);
    if ($닉 === '') {
      return '0';
    }
    $row = @db_select("SELECT CAST(hard_donate_offer AS CHAR) AS hard_donate_offer FROM tb_odd_even_state WHERE nick = '{$닉}' LIMIT 1");
    return 홀짝_하드후원_금액문자열(is_array($row) ? ($row['hard_donate_offer'] ?? 0) : 0);
  }
}

if (!function_exists('홀짝_하드후원_오퍼_표시용')) {
  /**
   * 현재 연승이 3 미만이면 잔여 오퍼를 지우고 0 반환 (후원 버튼은 3연승+만)
   * @return string
   */
  function 홀짝_하드후원_오퍼_표시용($닉_esc, $streak): string {
    $streak = (int)$streak;
    $offer = 홀짝_하드후원_오퍼_조회($닉_esc);
    if ($offer === '0') {
      return '0';
    }
    if ($streak < 3) {
      홀짝_하드후원_오퍼_클리어($닉_esc);
      return '0';
    }
    return $offer;
  }
}

if (!function_exists('홀짝_하드후원_오퍼_저장')) {
  /** @param string|int $기준액 3연승 이상 당첨금 */
  function 홀짝_하드후원_오퍼_저장($닉_esc, $기준액): bool {
    if (!홀짝_하드후원_컬럼_있음()) {
      return false;
    }
    $닉 = 홀짝_하드후원_닉_esc($닉_esc);
    if ($닉 === '') {
      return false;
    }
    $amt = 홀짝_하드후원_금액문자열($기준액);
    if ($amt === '0') {
      return false;
    }
    $sqlAmt = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    return (bool)@db_query("
      INSERT INTO tb_odd_even_state (nick, hard_donate_offer)
      VALUES ('{$닉}', {$sqlAmt})
      ON DUPLICATE KEY UPDATE hard_donate_offer = VALUES(hard_donate_offer)
    ");
  }
}

if (!function_exists('홀짝_하드후원_오퍼_클리어')) {
  function 홀짝_하드후원_오퍼_클리어($닉_esc): bool {
    if (!홀짝_하드후원_컬럼_있음()) {
      return false;
    }
    $닉 = 홀짝_하드후원_닉_esc($닉_esc);
    if ($닉 === '') {
      return false;
    }
    return (bool)@db_query("UPDATE tb_odd_even_state SET hard_donate_offer = 0 WHERE nick = '{$닉}' LIMIT 1");
  }
}

if (!function_exists('홀짝_하드후원_1퍼센트')) {
  /** 당첨금의 N% (기본 10%) — 함수명 호환 유지 @return string */
  function 홀짝_하드후원_1퍼센트($기준액): string {
    $base = 홀짝_하드후원_금액문자열($기준액);
    if ($base === '0') {
      return '0';
    }
    $pct = (int)홀짝_후원_퍼센트;
    if ($pct < 1) {
      $pct = 10;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return 홀짝_하드후원_금액문자열(bcdiv(bcmul($base, (string)$pct, 0), '100', 0));
    }
    return 홀짝_하드후원_금액문자열((int)floor(((float)$base) * $pct / 100));
  }
}

if (!function_exists('홀짝_하드후원_모금함_컬럼_확보')) {
  function 홀짝_하드후원_모금함_컬럼_확보(): bool {
    static $done = false;
    if ($done) {
      return true;
    }
    if (!function_exists('db_select') || !function_exists('db_query')) {
      return false;
    }
    $col = @db_select("SHOW COLUMNS FROM config LIKE '후원모금함'");
    if (empty($col)) {
      @db_query("
        ALTER TABLE config
          ADD COLUMN `후원모금함` DECIMAL(40,0) NOT NULL DEFAULT 0
            COMMENT '홀짝 이지/하드 3연승+ 후원 누적(게임냥). 민호만 수령'
      ");
      $col = @db_select("SHOW COLUMNS FROM config LIKE '후원모금함'");
    }
    if (!empty($col)) {
      $done = true;
      return true;
    }
    return false;
  }
}

if (!function_exists('홀짝_하드후원_모금함_조회')) {
  /** @return string */
  function 홀짝_하드후원_모금함_조회(): string {
    if (!홀짝_하드후원_모금함_컬럼_확보()) {
      return '0';
    }
    $row = @db_select("SELECT CAST(`후원모금함` AS CHAR) AS amt FROM config LIMIT 1");
    return 홀짝_하드후원_금액문자열(is_array($row) ? ($row['amt'] ?? 0) : 0);
  }
}

if (!function_exists('홀짝_하드후원_모금함_적립')) {
  /** @param string|int $금액 */
  function 홀짝_하드후원_모금함_적립($금액): bool {
    if (!홀짝_하드후원_모금함_컬럼_확보()) {
      return false;
    }
    $amt = 홀짝_하드후원_금액문자열($금액);
    if ($amt === '0') {
      return false;
    }
    $sqlAmt = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;
    return (bool)@db_query("UPDATE config SET `후원모금함` = IFNULL(`후원모금함`, 0) + {$sqlAmt} LIMIT 1");
  }
}

if (!function_exists('홀짝_하드후원_수령가능')) {
  function 홀짝_하드후원_수령가능($닉): bool {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    $수신 = 홀짝_하드후원_수신닉();
    if ($닉 === $수신) {
      return true;
    }
    if (function_exists('getTwoCharNick') && getTwoCharNick($닉) === $수신) {
      return true;
    }
    return false;
  }
}

if (!function_exists('홀짝_하드후원_로그테이블_확보')) {
  function 홀짝_하드후원_로그테이블_확보(): bool {
    static $done = false;
    if ($done) {
      return true;
    }
    if (!function_exists('db_query')) {
      return false;
    }
    $ok = (bool)@db_query("
      CREATE TABLE IF NOT EXISTS `tb_odd_even_donate_log` (
        `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nick` VARCHAR(32) NOT NULL COMMENT '후원한 사람 (두자리 닉)',
        `receiver` VARCHAR(32) NOT NULL DEFAULT '민호' COMMENT '후원 받은 사람/모금함',
        `base_amount` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '3연승+ 당첨금(기준액)',
        `donate_amount` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '실제 후원액(10%)',
        `odds_mode` VARCHAR(8) NOT NULL DEFAULT 'hard' COMMENT '후원 시점 모드',
        `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idx`),
        KEY `idx_donate_nick_regdate` (`nick`, `regdate`),
        KEY `idx_donate_receiver_regdate` (`receiver`, `regdate`),
        KEY `idx_donate_regdate` (`regdate`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='홀짝 이지/하드 3연승+ 후원 내역'
    ");
    if ($ok) {
      $done = true;
    }
    return $ok;
  }
}

if (!function_exists('홀짝_하드후원_로그_저장')) {
  /**
   * @param string|int $기준액
   * @param string|int $후원액
   */
  function 홀짝_하드후원_로그_저장($닉, $수신, $기준액, $후원액, $모드 = 'hard'): bool {
    if (!홀짝_하드후원_로그테이블_확보()) {
      return false;
    }
    $닉_esc = addslashes(trim((string)$닉));
    $수신_esc = addslashes(trim((string)$수신));
    if ($닉_esc === '' || $수신_esc === '') {
      return false;
    }
    $baseSql = function_exists('냥_SQL정수')
      ? 냥_SQL정수(홀짝_하드후원_금액문자열($기준액))
      : 홀짝_하드후원_금액문자열($기준액);
    $amtSql = function_exists('냥_SQL정수')
      ? 냥_SQL정수(홀짝_하드후원_금액문자열($후원액))
      : 홀짝_하드후원_금액문자열($후원액);
    $모드_esc = addslashes(function_exists('홀짝_모드_정규화') ? 홀짝_모드_정규화($모드) : (string)$모드);
    return (bool)@db_query("
      INSERT INTO tb_odd_even_donate_log
        (nick, receiver, base_amount, donate_amount, odds_mode, regdate)
      VALUES
        ('{$닉_esc}', '{$수신_esc}', {$baseSql}, {$amtSql}, '{$모드_esc}', NOW())
    ");
  }
}

if (!function_exists('홀짝_하드후원_실행')) {
  /**
   * 이지/하드 3연승 이상 당첨금 10%를 config.후원모금함에 적립
   * @return array{ok:bool,data?:string,amount?:string,base?:string,point?:string,receiver?:string,donate_pool?:string}
   */
  function 홀짝_하드후원_실행($두자리닉넴, $닉_esc): array {
    $수신 = 홀짝_하드후원_수신닉();
    $streak = 0;
    if (function_exists('db_select')) {
      $닉 = function_exists('홀짝_하드후원_닉_esc') ? 홀짝_하드후원_닉_esc($닉_esc) : addslashes((string)$닉_esc);
      $st = @db_select("SELECT IFNULL(streak, 0) AS streak FROM tb_odd_even_state WHERE nick = '{$닉}' LIMIT 1");
      $streak = (int)($st['streak'] ?? 0);
    }
    $기준 = ($streak >= 3)
      ? 홀짝_하드후원_오퍼_조회($닉_esc)
      : (function_exists('홀짝_하드후원_오퍼_표시용')
          ? 홀짝_하드후원_오퍼_표시용($닉_esc, $streak)
          : '0');
    if ($기준 === '0') {
      return ['ok' => false, 'data' => '❌ 후원할 수 있는 3연승 기록이 없어요.'];
    }

    $pct = (int)홀짝_후원_퍼센트;
    $후원액 = 홀짝_하드후원_1퍼센트($기준);
    if ($후원액 === '0') {
      홀짝_하드후원_오퍼_클리어($닉_esc);
      return ['ok' => false, 'data' => "❌ 후원 금액이 너무 작아요. (당첨금 {$pct}% 기준)"];
    }

    if (!홀짝_하드후원_모금함_컬럼_확보()) {
      return ['ok' => false, 'data' => '❌ 후원모금함 준비에 실패했어요. 관리자에게 문의해 주세요.'];
    }

    $정보 = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '" . addslashes($닉_esc) . "' LIMIT 1");
    if (empty($정보)) {
      return ['ok' => false, 'data' => '❌ 회원 정보를 찾을 수 없습니다.'];
    }
    $보유 = 홀짝_하드후원_금액문자열($정보['point'] ?? 0);
    if (function_exists('bccomp')) {
      if (bccomp($보유, $후원액, 0) < 0) {
        return ['ok' => false, 'data' => '❌ 게임냥이 부족해요. (후원 ' . 홀짝_하드후원_표시금액($후원액) . ')'];
      }
    } elseif ((float)$보유 < (float)$후원액) {
      return ['ok' => false, 'data' => '❌ 게임냥이 부족해요.'];
    }

    $후원Sql = function_exists('냥_SQL정수') ? 냥_SQL정수($후원액) : $후원액;
    $닉Sql = addslashes((string)$닉_esc);
    db_query("UPDATE tb_member SET point = point - {$후원Sql} WHERE name = '{$닉Sql}' LIMIT 1");
    if (!홀짝_하드후원_모금함_적립($후원액)) {
      db_query("UPDATE tb_member SET point = point + {$후원Sql} WHERE name = '{$닉Sql}' LIMIT 1");
      return ['ok' => false, 'data' => '❌ 후원모금함 적립에 실패했어요.'];
    }
    홀짝_하드후원_오퍼_클리어($닉_esc);

    $모드 = 'hard';
    if (function_exists('홀짝_모드_읽기')) {
      $모드 = function_exists('홀짝_모드_정규화')
        ? 홀짝_모드_정규화(홀짝_모드_읽기($닉_esc))
        : (string)홀짝_모드_읽기($닉_esc);
    }

    if (function_exists('지급로그')) {
      지급로그('홀짝웹-후원', $두자리닉넴, '후원모금함', 0, $후원액);
    }
    홀짝_하드후원_로그_저장($두자리닉넴, $수신, $기준, $후원액, $모드);

    $닉표시 = trim((string)$두자리닉넴);
    $표시후원 = 홀짝_하드후원_표시금액($후원액);
    $공지 = "💝 홀짝 연승 후원 · {$닉표시} → {$수신} · {$표시후원}";
    $item = '홀짝_후원_' . $닉표시;
    if (function_exists('info2알림_등록')) {
      info2알림_등록($공지, $item);
    } else {
      $공지_esc = addslashes($공지);
      $item_esc = addslashes($item);
      @db_query("
        CREATE TABLE IF NOT EXISTS tb_info2_alarm (
          idx INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
          status TINYINT NOT NULL DEFAULT 0,
          msg TEXT NOT NULL,
          item VARCHAR(64) NOT NULL DEFAULT 'system',
          regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_status_reg (status, regdate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      ");
      @db_query("INSERT INTO tb_info2_alarm SET status = 0, msg = '{$공지_esc}', item = '{$item_esc}', regdate = NOW()");
    }

    $pt_row = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉Sql}' LIMIT 1");
    $point_now = 홀짝_하드후원_금액문자열(is_array($pt_row) ? ($pt_row['point'] ?? 0) : 0);

    return [
      'ok' => true,
      'data' => "💝 제작자 {$수신}에게 연승 하신금액의 {$pct}% ({$표시후원}) 후원되었습니다! 👍🏻\n(후원모금함 적립)",
      'amount' => $후원액,
      'amount_display' => $표시후원,
      'base' => $기준,
      'point' => $point_now,
      'receiver' => $수신,
      'hard_donate_offer' => '0',
      'donate_pool' => 홀짝_하드후원_모금함_조회(),
      'announce' => $공지,
    ];
  }
}

if (!function_exists('홀짝_하드후원_모금함_수령')) {
  /**
   * 민호만 후원모금함 전액 수령 → 게임냥
   * @return array{ok:bool,data?:string,amount?:string,point?:string,donate_pool?:string}
   */
  function 홀짝_하드후원_모금함_수령($두자리닉넴): array {
    if (!홀짝_하드후원_수령가능($두자리닉넴)) {
      return ['ok' => false, 'data' => '❌ 후원모금함 수령은 민호만 가능해요.'];
    }
    if (!홀짝_하드후원_모금함_컬럼_확보()) {
      return ['ok' => false, 'data' => '❌ 후원모금함 준비에 실패했어요.'];
    }

    $모금함 = 홀짝_하드후원_모금함_조회();
    if ($모금함 === '0') {
      return ['ok' => false, 'data' => '❌ 수령할 후원모금함이 없어요.', 'donate_pool' => '0'];
    }

    $수신 = 홀짝_하드후원_수신닉();
    $수신Sql = addslashes($수신);
    $받는친구 = @db_select("SELECT idx FROM tb_member WHERE name = '{$수신Sql}' LIMIT 1");
    if (empty($받는친구['idx'])) {
      return ['ok' => false, 'data' => "❌ [ {$수신} ] 회원을 찾을 수 없습니다."];
    }

    $sqlAmt = function_exists('냥_SQL정수') ? 냥_SQL정수($모금함) : $모금함;
    $before = 홀짝_하드후원_모금함_조회();
    if ($before !== $모금함) {
      return ['ok' => false, 'data' => '❌ 잠시 후 다시 시도해 주세요. (동시 수령)', 'donate_pool' => $before];
    }
    @db_query("UPDATE config SET `후원모금함` = GREATEST(IFNULL(`후원모금함`, 0) - {$sqlAmt}, 0) LIMIT 1");
    $after = 홀짝_하드후원_모금함_조회();
    if (function_exists('bccomp')) {
      $expected = bcsub($before, $모금함, 0);
      if (bccomp($after, 홀짝_하드후원_금액문자열($expected), 0) !== 0 && bccomp($after, $before, 0) >= 0) {
        return ['ok' => false, 'data' => '❌ 후원모금함 수령에 실패했어요. 다시 시도해 주세요.', 'donate_pool' => $after];
      }
    }

    db_query("UPDATE tb_member SET point = point + {$sqlAmt} WHERE name = '{$수신Sql}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('홀짝웹-후원모금수령', $수신, '후원모금함', 0, $모금함);
    }

    $pt_row = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$수신Sql}' LIMIT 1");
    $point_now = 홀짝_하드후원_금액문자열(is_array($pt_row) ? ($pt_row['point'] ?? 0) : 0);
    $표시 = 홀짝_하드후원_표시금액($모금함);

    return [
      'ok' => true,
      'data' => "💝 후원모금함 수령!\n+{$표시} 게임냥",
      'amount' => $모금함,
      'amount_display' => $표시,
      'point' => $point_now,
      'donate_pool' => 홀짝_하드후원_모금함_조회(),
    ];
  }
}
