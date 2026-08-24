<?php
/**
 * 홀짝 웹 페이백 — tb_odd_even_state.payback_pool (웹 전용)
 * · 패배(실패) 시에만 적립: 잃은 금액(총손실)의 10%
 * · 배팅·승리·무승부에는 적립하지 않음
 *
 * 대금액: (int)/BIGINT 금지 — DECIMAL(40,0) + 문자열 연산
 */

if (!function_exists('홀짝_웹_페이백_금액문자열')) {
  /** @return string 부호 없는 정수 문자열 */
  function 홀짝_웹_페이백_금액문자열($v) {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ltrim((string)$s, '0') ?: '0';
  }
}

if (!function_exists('홀짝_웹_페이백_컬럼_확보')) {
  function 홀짝_웹_페이백_컬럼_확보() {
    static $done = false;
    if ($done || !function_exists('db_select') || !function_exists('db_query')) {
      return;
    }
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'payback_pool'");
    if (empty($col)) {
      $after = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'odds_mode'");
      $afterClause = !empty($after) ? ' AFTER odds_mode' : '';
      @db_query("
        ALTER TABLE tb_odd_even_state
          ADD COLUMN payback_pool DECIMAL(40,0) NOT NULL DEFAULT 0
            COMMENT '웹 페이백 누적(게임냥)'{$afterClause}
      ");
    } else {
      // BIGINT → DECIMAL(40,0): 922경 초과·오버플로우 방지
      $type = strtolower((string)($col['Type'] ?? ''));
      $needWiden = (strpos($type, 'decimal') === false)
        || (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 40);
      if ($needWiden) {
        @db_query("ALTER TABLE tb_odd_even_state MODIFY COLUMN payback_pool DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '웹 페이백 누적(게임냥)'");
      }
    }
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
  /** @return int 퍼센트 — 패배 시 손실액 10% */
  function 홀짝_웹_페이백_배팅률($모드 = null) {
    unset($모드);
    return 10;
  }
}

if (!function_exists('홀짝_웹_페이백_적립')) {
  /**
   * @param string|int $금액
   * @return string 이번 적립액
   */
  function 홀짝_웹_페이백_적립($닉_esc, $금액) {
    $금액Str = 홀짝_웹_페이백_금액문자열($금액);
    if ($금액Str === '0' || !홀짝_웹_페이백_컬럼_있음()) {
      return '0';
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return '0';
    }
    $sqlAmt = function_exists('냥_SQL정수') ? 냥_SQL정수($금액Str) : $금액Str;
    db_query("
      INSERT INTO tb_odd_even_state (nick, payback_pool)
      VALUES ('{$nick}', {$sqlAmt})
      ON DUPLICATE KEY UPDATE payback_pool = payback_pool + {$sqlAmt}
    ");
    return $금액Str;
  }
}

if (!function_exists('홀짝_웹_페이백_배팅적립')) {
  /**
   * @param string|int $배팅
   * @return string 이번 적립액
   */
  function 홀짝_웹_페이백_배팅적립($닉_esc, $배팅, $모드) {
    $배팅Str = 홀짝_웹_페이백_금액문자열($배팅);
    if ($배팅Str === '0') {
      return '0';
    }
    $rate = 홀짝_웹_페이백_배팅률($모드);
    if (function_exists('냥_비율내림')) {
      $add = 홀짝_웹_페이백_금액문자열(냥_비율내림($배팅Str, $rate / 100.0));
    } elseif (function_exists('bcmul') && function_exists('bcdiv')) {
      $add = bcdiv(bcmul($배팅Str, (string)$rate, 0), '100', 0);
    } else {
      $add = 홀짝_웹_페이백_금액문자열((int)floor((float)$배팅Str * $rate / 100));
    }
    if ($add === '0') {
      return '0';
    }
    return 홀짝_웹_페이백_적립($닉_esc, $add);
  }
}

if (!function_exists('홀짝_웹_페이백_패배적립')) {
  /**
   * 패배 시에만 페이백 적립 (잃은 금액·총손실의 10%)
   * @param string|int $손실액 배팅×패배수 (하드 2배 포함)
   * @return string 이번 적립액
   */
  function 홀짝_웹_페이백_패배적립($닉_esc, $손실액, $모드 = null) {
    return 홀짝_웹_페이백_배팅적립($닉_esc, $손실액, $모드);
  }
}

if (!function_exists('홀짝_웹_페이백_조회')) {
  /** @return string 페이백 누적 (정수 문자열) */
  function 홀짝_웹_페이백_조회($닉_esc) {
    if (!홀짝_웹_페이백_컬럼_있음()) {
      return '0';
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return '0';
    }
    $row = @db_select("SELECT CAST(payback_pool AS CHAR) AS payback_pool FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    return 홀짝_웹_페이백_금액문자열($row['payback_pool'] ?? 0);
  }
}

if (!function_exists('홀짝_이지_연승승리_수수료적용_웹')) {
  /**
   * @deprecated 하드 고정 — 홀짝_하드_연승승리_수수료적용 과 동일
   * @return array{총:int,금고:int,로또:int,페이백:int}
   */
  function 홀짝_이지_연승승리_수수료적용_웹($배팅, $닉_esc = '') {
    unset($닉_esc);
    $결과 = 홀짝_하드_연승승리_수수료적용($배팅);
    return [
      '총' => $결과['총'] ?? '0',
      '금고' => $결과['금고'] ?? '0',
      '로또' => $결과['로또'] ?? '0',
      '페이백' => '0',
    ];
  }
}

if (!function_exists('홀짝_웹_페이백_수령')) {
  /**
   * @return array{ok:bool,data?:string,amount?:string,point?:string,payback_pool?:string}
   */
  function 홀짝_웹_페이백_수령($닉_esc, $두자리닉넴) {
    if (!홀짝_웹_페이백_컬럼_있음()) {
      return ['ok' => false, 'data' => '❌ 페이백 기능을 사용할 수 없어요. (DB 마이그레이션 필요)'];
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return ['ok' => false, 'data' => '❌ 닉네임을 확인할 수 없어요.'];
    }
    $row = @db_select("SELECT CAST(payback_pool AS CHAR) AS payback_pool, CAST(pending_bet AS CHAR) AS pending_bet FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    $pending = function_exists('홀짝_냥')
      ? 홀짝_냥($row['pending_bet'] ?? 0)
      : (ltrim(preg_replace('/\D/', '', (string)($row['pending_bet'] ?? '0')), '0') ?: '0');
    if ($pending !== '0') {
      return ['ok' => false, 'data' => '⏳ 진행 중인 판이 있을 때는 페이백을 받을 수 없어요.'];
    }
    $amount = 홀짝_웹_페이백_금액문자열($row['payback_pool'] ?? 0);
    if ($amount === '0') {
      return ['ok' => false, 'data' => '❌ 받을 페이백이 없어요.'];
    }

    // 잔액 컬럼이 BIGINT면 922경 초과 시 오버플로우로 잔액이 줄어 보임 → DECIMAL 보장
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }

    $amountSql = function_exists('냥_SQL정수') ? 냥_SQL정수($amount) : $amount;
    db_query("UPDATE tb_odd_even_state SET payback_pool = 0 WHERE nick = '{$nick}' LIMIT 1");
    db_query("UPDATE tb_member SET point = point + {$amountSql} WHERE name = '{$nick}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('홀짝웹-페이백', $두자리닉넴, '', 0, $amount);
    }
    $pt = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$nick}' LIMIT 1");
    $point = 홀짝_웹_페이백_금액문자열($pt['point'] ?? 0);
    if (function_exists('game_금액_축약')) {
      $표시 = game_금액_축약($amount);
    } elseif (function_exists('홀짝_금액표시')) {
      $표시 = 홀짝_금액표시($amount, '');
    } elseif (function_exists('냥_경조억_축약문구')) {
      $표시 = 냥_경조억_축약문구($amount, '', '');
    } else {
      $표시 = function_exists('냥_숫자콤마')
        ? 냥_숫자콤마($amount)
        : (function_exists('number_format') ? number_format((float)$amount) : $amount);
    }
    return [
      'ok' => true,
      'data' => '💰 페이백 +' . $표시 . '냥 지급!',
      'amount' => $amount,
      'point' => $point,
      'payback_pool' => '0',
    ];
  }
}
