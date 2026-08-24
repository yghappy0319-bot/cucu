<?php
/**
 * 홀짝 웹 페이백 — tb_odd_even_state.payback_pool (웹 전용)
 * · 이지 배팅 1% / 하드 배팅 3% 누적
 * · 이지 연승 승리 10% 수수료 중 배팅 1% → 페이백 풀
 */

if (!function_exists('홀짝_웹_페이백_컬럼_확보')) {
  function 홀짝_웹_페이백_컬럼_확보() {
    static $done = false;
    if ($done || !function_exists('db_select') || !function_exists('db_query')) {
      return;
    }
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'payback_pool'");
    if (!empty($col)) {
      $done = true;
      return;
    }
    $after = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'odds_mode'");
    $afterClause = !empty($after) ? ' AFTER odds_mode' : '';
    @db_query("
      ALTER TABLE tb_odd_even_state
        ADD COLUMN payback_pool BIGINT UNSIGNED NOT NULL DEFAULT 0
          COMMENT '웹 페이백 누적(게임냥)'{$afterClause}
    ");
    $done = true;
  }
}

if (!function_exists('홀짝_웹_페이백_컬럼_있음')) {
  function 홀짝_웹_페이백_컬럼_있음() {
    static $has = null;
    if (!function_exists('db_select')) {
      return false;
    }
    if ($has !== null) {
      return $has;
    }
    홀짝_웹_페이백_컬럼_확보();
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'payback_pool'");
    $has = !empty($col);
    return $has;
  }
}

if (!function_exists('홀짝_웹_페이백_배팅률')) {
  /** @return int 퍼센트 (easy 1, hard 3) */
  function 홀짝_웹_페이백_배팅률($모드) {
    return (홀짝_모드_정규화($모드) === 'hard') ? 3 : 1;
  }
}

if (!function_exists('홀짝_웹_페이백_적립')) {
  /** @return int 이번 적립액 */
  function 홀짝_웹_페이백_적립($닉_esc, $금액) {
    $금액 = max(0, (int)$금액);
    if ($금액 <= 0 || !홀짝_웹_페이백_컬럼_있음()) {
      return 0;
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 0;
    }
    db_query("
      INSERT INTO tb_odd_even_state (nick, payback_pool)
      VALUES ('{$nick}', {$금액})
      ON DUPLICATE KEY UPDATE payback_pool = payback_pool + {$금액}
    ");
    return $금액;
  }
}

if (!function_exists('홀짝_웹_페이백_배팅적립')) {
  /** @return int 이번 적립액 */
  function 홀짝_웹_페이백_배팅적립($닉_esc, $배팅, $모드) {
    $배팅 = max(0, (int)$배팅);
    if ($배팅 <= 0) {
      return 0;
    }
    $rate = 홀짝_웹_페이백_배팅률($모드);
    $add = (int)floor($배팅 * $rate / 100);
    if ($add <= 0) {
      return 0;
    }
    return 홀짝_웹_페이백_적립($닉_esc, $add);
  }
}

if (!function_exists('홀짝_웹_페이백_조회')) {
  function 홀짝_웹_페이백_조회($닉_esc) {
    if (!홀짝_웹_페이백_컬럼_있음()) {
      return 0;
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return 0;
    }
    $row = @db_select("SELECT payback_pool FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    return max(0, (int)($row['payback_pool'] ?? 0));
  }
}

if (!function_exists('홀짝_이지_연승승리_수수료적용_웹')) {
  /**
   * 이지모드 연승 승리 수수료 (웹): 배팅 10% 중 1% 페이백 · 나머지 9% 금고·로또 50:50
   * @return array{총:int,금고:int,로또:int,페이백:int}
   */
  function 홀짝_이지_연승승리_수수료적용_웹($배팅, $닉_esc) {
    $배팅 = max(0, (int)$배팅);
    $총수수료 = (int)floor($배팅 * 10 / 100);
    $페이백 = (int)floor($배팅 * 1 / 100);
    if ($페이백 > $총수수료) {
      $페이백 = $총수수료;
    }
    $나머지 = max(0, $총수수료 - $페이백);
    $금고 = (int)floor($나머지 / 2);
    $로또 = $나머지 - $금고;
    if ($금고 > 0) {
      db_query("UPDATE config SET tax = tax + {$금고}");
    }
    if ($로또 > 0) {
      홀짝_로또수수료_적립($로또);
    }
    if ($페이백 > 0) {
      홀짝_웹_페이백_적립($닉_esc, $페이백);
    }
    return ['총' => $총수수료, '금고' => $금고, '로또' => $로또, '페이백' => $페이백];
  }
}

if (!function_exists('홀짝_웹_페이백_수령')) {
  /**
   * @return array{ok:bool,data?:string,amount?:int,point?:int,payback_pool?:int}
   */
  function 홀짝_웹_페이백_수령($닉_esc, $두자리닉넴) {
    if (!홀짝_웹_페이백_컬럼_있음()) {
      return ['ok' => false, 'data' => '❌ 페이백 기능을 사용할 수 없어요. (DB 마이그레이션 필요)'];
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return ['ok' => false, 'data' => '❌ 닉네임을 확인할 수 없어요.'];
    }
    $row = @db_select("SELECT payback_pool, pending_bet FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    if ((int)($row['pending_bet'] ?? 0) > 0) {
      return ['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 페이백을 받을 수 없어요.'];
    }
    $amount = max(0, (int)($row['payback_pool'] ?? 0));
    if ($amount <= 0) {
      return ['ok' => false, 'data' => '❌ 받을 페이백이 없어요.'];
    }
    db_query("UPDATE tb_odd_even_state SET payback_pool = 0 WHERE nick = '{$nick}' LIMIT 1");
    db_query("UPDATE tb_member SET point = point + {$amount} WHERE name = '{$nick}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('홀짝웹-페이백', $두자리닉넴, '', 0, $amount);
    }
    $pt = @db_select("SELECT point FROM tb_member WHERE name = '{$nick}' LIMIT 1");
    $point = is_array($pt) ? (int)($pt['point'] ?? 0) : 0;
    return [
      'ok' => true,
      'data' => '💰 페이백 +' . number_format($amount) . '냥 지급!',
      'amount' => $amount,
      'point' => $point,
      'payback_pool' => 0,
    ];
  }
}
