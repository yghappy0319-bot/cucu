<?php
/**
 * 홀짝 도전 공통 제한 (홍보방·매추얼·웹)
 */

if (!function_exists('홀짝_배팅_티어표')) {
  /**
   * 보유 게임냥 구간별 최소·퀵 배팅 (cap 미만 구간 적용, 마지막 행은 50경+)
   */
  function 홀짝_배팅_티어표() {
    return array(
      array('cap' => 100000000, 'min' => 100000, 'quick' => array(1000000, 10000000)),
      array('cap' => 1000000000000000, 'min' => 1000000, 'quick' => array(
        1000000, 10000000, 50000000, 100000000, 1000000000, 10000000000,
      )),
      array('cap' => 10000000000000000, 'min' => 100000000, 'quick' => array(
        100000000, 5000000000, 10000000000, 1000000000000, 1000000000000000,
      )),
      array('cap' => 500000000000000000, 'min' => 1000000000000, 'quick' => array(
        1000000000000, 10000000000000, 50000000000000, 100000000000000, 1000000000000000, 10000000000000000,
      )),
      array('cap' => PHP_INT_MAX, 'min' => 10000000000000, 'quick' => array(
        10000000000000, 50000000000000, 100000000000000, 1000000000000000, 10000000000000000, 50000000000000000,
      )),
    );
  }
}

if (!function_exists('홀짝_배팅_티어')) {
  /** @return array{min:int, quick:int[]} */
  function 홀짝_배팅_티어($가진냥) {
    $가진냥 = max(0, (int)$가진냥);
    foreach (홀짝_배팅_티어표() as $tier) {
      if ($가진냥 < (int)$tier['cap']) {
        return array(
          'min' => (int)$tier['min'],
          'quick' => array_map('intval', (array)$tier['quick']),
        );
      }
    }
    $last = end(홀짝_배팅_티어표());
    return array(
      'min' => (int)$last['min'],
      'quick' => array_map('intval', (array)$last['quick']),
    );
  }
}

if (!function_exists('홀짝_기본배팅')) {
  /**
   * 미입력 시 기본·최소 배팅 — 보유 게임냥 구간별
   * 1억 미만 10만 / 1억~1000조 100만 / 1000조~1경 1억 / 1경~50경 1조 / 50경+ 10조
   */
  function 홀짝_기본배팅($가진냥) {
    return (int)홀짝_배팅_티어($가진냥)['min'];
  }
}

if (!function_exists('홀짝_배팅_퀵_금액')) {
  /** @return int[] */
  function 홀짝_배팅_퀵_금액($가진냥) {
    $tier = 홀짝_배팅_티어($가진냥);
    return array_map('intval', (array)($tier['quick'] ?? array()));
  }
}

if (!function_exists('홀짝_최대배팅')) {
  /** 홀짝 최대 배팅 — 해당 구간 퀵 버튼 최댓값과 동일 */
  function 홀짝_최대배팅($가진냥) {
    $tier = 홀짝_배팅_티어($가진냥);
    $max = (int)$tier['min'];
    foreach (홀짝_배팅_퀵_금액($가진냥) as $amt) {
      $amt = (int)$amt;
      if ($amt > $max) {
        $max = $amt;
      }
    }
    return $max;
  }
}

if (!function_exists('홀짝_배팅_퀵_목록')) {
  /** @return array<int, array{fixed:int, label:string}> */
  function 홀짝_배팅_퀵_목록($가진냥) {
    $out = array();
    foreach (홀짝_배팅_퀵_금액($가진냥) as $amt) {
      $amt = (int)$amt;
      $label = function_exists('냥축약표시') ? number_format($amt) . "냥" : number_format($amt);
      $out[] = array('fixed' => $amt, 'label' => $label);
    }
    return $out;
  }
}

if (!function_exists('홀짝_미션_생타100_달성')) {
  /**
   * 홀짝 도전 참가: 당일 tb_mission(타수/100타) 완료자만 허용.
   */
  function 홀짝_미션_생타100_달성($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    $닉_esc = addslashes($닉);
    $row = @db_select("
      SELECT idx
      FROM tb_mission
      WHERE nick = '{$닉_esc}'
        AND types = '타수'
        AND status = '100타'
        AND regdate = CURDATE()
      LIMIT 1
    ");
    return is_array($row) && !empty($row['idx']);
  }
}

if (!function_exists('홀짝_신용회복_홀짝_금지문구')) {
  /**
   * credit_recovery_plus3_at: 신용 회복 시각 +3시간(잠금 종료 시각). 경과 분은 NULL 초기화.
   * @return string|null 금지 시 안내 문구, 허용이면 null
   */
  function 홀짝_신용회복_홀짝_금지문구($닉) {
    $닉_esc = addslashes($닉);
    db_query("UPDATE tb_member SET credit_recovery_plus3_at = NULL WHERE name = '{$닉_esc}' AND credit_recovery_plus3_at IS NOT NULL AND credit_recovery_plus3_at <= NOW() LIMIT 1");
    $row = @db_select("
      SELECT credit_recovery_plus3_at AS u,
             TIMESTAMPDIFF(MINUTE, NOW(), credit_recovery_plus3_at) AS remain_m
      FROM tb_member
      WHERE name = '{$닉_esc}' AND credit_recovery_plus3_at IS NOT NULL AND credit_recovery_plus3_at > NOW()
      LIMIT 1
    ");
    if (!$row || empty($row['u'])) {
      return null;
    }
    $remain = max(1, (int)($row['remain_m'] ?? 1));
    $until = (string)$row['u'];
    return "❌ 신용 회복 후 3시간 동안 홀짝 도전이 제한됩니다.\n남은 시간: 약 {$remain}분\n해제 시각: {$until}";
  }
}

if (!function_exists('게임제한_홀짝_금지문구')) {
  /** @return string|null */
  function 게임제한_홀짝_금지문구($닉) {
    return function_exists('게임제한_차단문구') ? 게임제한_차단문구($닉) : null;
  }
}

if (!function_exists('홀짝_지급로그')) {
  /** 홀짝 전용 경량 로그 (mypoint 동기화 생략 — 정산 직후 별도 point 조회) */
  function 홀짝_지급로그($상태, $닉, $수수료, $지급냥) {
    $닉 = addslashes(trim((string)$닉));
    $상태 = addslashes((string)$상태);
    $수수료 = (int)$수수료;
    $지급냥 = (int)$지급냥;
    if ($닉 === '') {
      return false;
    }
    return (bool)@db_query("
      INSERT INTO tb_point_log
      SET status = '{$상태}',
          nick = '{$닉}',
          receiver = '',
          tax = '{$수수료}',
          point = {$지급냥},
          regdate = NOW()
    ");
  }
}

if (!function_exists('홀짝_로또수수료_적립')) {
  function 홀짝_로또수수료_적립($로또금액, $적립닉 = '홀짝수수료') {
    static $cached_lotto_drow = null;
    $로또 = max(0, (int)$로또금액);
    if ($로또 <= 0) {
      return 0;
    }
    if ($cached_lotto_drow === null) {
      $완료회차행 = @db_select("
        SELECT IFNULL(MAX(drow), 0) AS max_drow
        FROM tb_game_lotto_result
        WHERE status = 1
      ");
      $cached_lotto_drow = (int)($완료회차행['max_drow'] ?? 0);
    }
    $진행회차 = $cached_lotto_drow + 1;
    $cached_lotto_drow = $진행회차;
    $닉_esc = addslashes(trim((string)$적립닉));
    if ($닉_esc === '') {
      $닉_esc = '홀짝수수료';
    }
    db_query("
      INSERT INTO tb_game_lotto
      SET nick = '{$닉_esc}',
          drow = {$진행회차},
          num1 = 0,
          num2 = 0,
          num3 = 0,
          amount = {$로또},
          status = 0,
          regdate = NOW()
    ");
    return $로또;
  }
}

if (!function_exists('홀짝_수수료_금고로또배분')) {
  /**
   * 홀짝 수수료 총액 → 금고 50% · 로또 50%
   * @return array{금고:int,로또:int}
   */
  function 홀짝_수수료_금고로또배분($총수수료) {
    $총 = max(0, (int)$총수수료);
    if ($총 <= 0) {
      return ['금고' => 0, '로또' => 0];
    }
    $금고 = (int)floor($총 / 2);
    $로또 = $총 - $금고;
    if ($금고 > 0) {
      db_query("UPDATE config SET tax = tax + {$금고}");
    }
    if ($로또 > 0) {
      홀짝_로또수수료_적립($로또);
    }
    return ['금고' => $금고, '로또' => $로또];
  }
}

if (!function_exists('홀짝_하드_연승_보너스확률')) {
  /**
   * 하드모드 연승 승리 시 x2~x4 보너스 확률(%)
   * 0→1연승 3% · 1→2 6% · 2→3 9% · 3→4 12% · 4→5 15%
   */
  function 홀짝_하드_연승_보너스확률($streak_before = 0) {
    $표 = [3, 6, 9, 12, 15];
    $s = max(0, min(count($표) - 1, (int)$streak_before));
    return (int)$표[$s];
  }
}

if (!function_exists('홀짝_하드_연승_보너스배수')) {
  /**
   * 하드모드 연승 승리 시 연승 단계별 확률로 당첨금 x2~x4 보너스
   * @param int $streak_before 승리 직전 연승 (0=첫판, 4=4연승→5연승)
   * @return int 0=미발동, 2~4=배수
   */
  function 홀짝_하드_연승_보너스배수($streak_before = 0) {
    $pct = 홀짝_하드_연승_보너스확률($streak_before);
    if ($pct < 1 || mt_rand(1, 100) > $pct) {
      return 0;
    }
    return mt_rand(2, 4);
  }
}

if (!function_exists('홀짝_하드_연승_x2_발동')) {
  /** @deprecated 홀짝_하드_연승_보너스배수() 사용 */
  function 홀짝_하드_연승_x2_발동($streak_before = 0) {
    return 홀짝_하드_연승_보너스배수($streak_before) >= 2;
  }
}

if (!function_exists('홀짝_이지_연승승리_수수료적용')) {
  /**
   * 이지모드 연승 승리 수수료: 배팅 10% (금고 5% · 로또 5%)
   * @return array{총:int,금고:int,로또:int}
   */
  function 홀짝_이지_연승승리_수수료적용($배팅) {
    $배팅 = max(0, (int)$배팅);
    $금고 = (int)floor($배팅 * 5 / 100);
    $로또 = (int)floor($배팅 * 5 / 100);
    if ($금고 > 0) {
      db_query("UPDATE config SET tax = tax + {$금고}");
    }
    if ($로또 > 0) {
      홀짝_로또수수료_적립($로또);
    }
    return ['총' => $금고 + $로또, '금고' => $금고, '로또' => $로또];
  }
}

if (!function_exists('홀짝_도전취소무승부_금고및로또')) {
  /** @deprecated alias — 홀짝_수수료_금고로또배분 과 동일 (50:50) */
  function 홀짝_도전취소무승부_금고및로또($총수수료) {
    return 홀짝_수수료_금고로또배분($총수수료);
  }
}

if (!function_exists('홀짝_모드_정규화')) {
  function 홀짝_모드_정규화($모드) {
    return (strtolower(trim((string)$모드)) === 'hard') ? 'hard' : 'easy';
  }
}

if (!function_exists('홀짝_하드강제_보유냥')) {
  /** 홀짝웹: 보유 게임냥 100경 이상이면 하드모드만 허용 */
  function 홀짝_하드강제_보유냥() {
    return 1000000000000000000; // 100경
  }
}

if (!function_exists('홀짝_웹_하드모드_강제')) {
  function 홀짝_웹_하드모드_강제($가진냥) {
    return max(0, (int)$가진냥) >= 홀짝_하드강제_보유냥();
  }
}

if (!function_exists('홀짝_모드_웹적용')) {
  /** @return string easy|hard */
  function 홀짝_모드_웹적용($모드, $가진냥) {
    if (홀짝_웹_하드모드_강제($가진냥)) {
      return 'hard';
    }
    return 홀짝_모드_정규화($모드);
  }
}

if (!function_exists('홀짝_모드_웹읽기')) {
  /** 보유냥 100경+ 시 하드 강제 · DB가 easy면 hard로 동기화 */
  function 홀짝_모드_웹읽기($닉_esc, $가진냥, $행 = null) {
    $stored = 홀짝_모드_읽기($닉_esc, $행);
    if (!홀짝_웹_하드모드_강제($가진냥)) {
      return $stored;
    }
    if ($stored !== 'hard' && trim((string)$닉_esc) !== '') {
      홀짝_모드_저장($닉_esc, 'hard');
    }
    return 'hard';
  }
}

if (!function_exists('홀짝도전_배수')) {
  /** @return array{win:int,lose:int} */
  function 홀짝도전_배수($streak, $모드 = null) {
    $win = array(3, 5, 7, 9, 11);
    $lose_easy = array(2, 4, 6, 8, 10);
    $lose_hard = array(3, 5, 7, 9, 11);
    $s = max(0, (int)$streak);
    if ($s > 4) {
      $s = 4;
    }
    $모드 = ($모드 !== null) ? 홀짝_모드_정규화($모드) : 'easy';
    $lose = ($모드 === 'hard') ? $lose_hard : $lose_easy;
    return array('win' => $win[$s], 'lose' => $lose[$s]);
  }
}

if (!function_exists('홀짝_모드_읽기')) {
  /** @param array|null $행 tb_odd_even_state 행(odds_mode 포함 시 DB 조회 생략) @return string easy|hard */
  function 홀짝_모드_읽기($닉_esc, $행 = null) {
    if (is_array($행) && array_key_exists('odds_mode', $행) && trim((string)$행['odds_mode']) !== '') {
      return 홀짝_모드_정규화($행['odds_mode']);
    }
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      global $홀짝_확률모드;
      return 홀짝_모드_정규화($홀짝_확률모드 ?? 'easy');
    }
    $row = @db_select("SELECT odds_mode FROM tb_odd_even_state WHERE nick = '{$nick}' LIMIT 1");
    if (is_array($row) && isset($row['odds_mode']) && trim((string)$row['odds_mode']) !== '') {
      return 홀짝_모드_정규화($row['odds_mode']);
    }
    global $홀짝_확률모드;
    return 홀짝_모드_정규화($홀짝_확률모드 ?? 'easy');
  }
}

if (!function_exists('홀짝_모드_저장')) {
  function 홀짝_모드_저장($닉_esc, $모드) {
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return false;
    }
    $m = 홀짝_모드_정규화($모드);
    return (bool)@db_query("
      INSERT INTO tb_odd_even_state (nick, odds_mode)
      VALUES ('{$nick}', '{$m}')
      ON DUPLICATE KEY UPDATE odds_mode = VALUES(odds_mode)
    ");
  }
}

if (!function_exists('홀짝_닉_정답_선정')) {
  function 홀짝_닉_정답_선정($닉_esc) {
    return 홀짝_정답_선정(홀짝_모드_읽기($닉_esc));
  }
}

if (!function_exists('홀짝_취소무승부_환급처리')) {
  /**
   * 대기 취소·무승부 환급 (이지 90%·10% 수수료 / 하드 100%·수수료 없음)
   * @return array{환급:int,수수료:int,금고:int,로또:int}
   */
  function 홀짝_취소무승부_환급처리($닉_esc, $두자리닉넴, $환불, $모드 = null, $포인트적용 = true) {
    $환불 = max(0, (int)$환불);
    $모드 = ($모드 !== null) ? 홀짝_모드_정규화($모드) : 홀짝_모드_읽기($닉_esc);
    if ($모드 === 'hard') {
      if ($포인트적용 && $환불 > 0) {
        db_query("UPDATE tb_member SET point = point + {$환불} WHERE name = '{$닉_esc}' LIMIT 1");
      }
      return ['환급' => $환불, '수수료' => 0, '금고' => 0, '로또' => 0];
    }
    $수수료 = (int)floor($환불 * 10 / 100);
    $환급 = $환불 - $수수료;
    if ($포인트적용 && $환급 > 0) {
      db_query("UPDATE tb_member SET point = point + {$환급} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    $분배 = 홀짝_수수료_금고로또배분($수수료);
    return ['환급' => $환급, '수수료' => $수수료, '금고' => $분배['금고'], '로또' => $분배['로또']];
  }
}

if (!function_exists('홀짝_타임아웃_환급처리')) {
  /**
   * @return array{환급:int,수수료:int,금고:int,로또:int}
   */
  function 홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $배팅원, $모드 = null) {
    $배팅원 = max(0, (int)$배팅원);
    $모드 = ($모드 !== null) ? 홀짝_모드_정규화($모드) : 홀짝_모드_읽기($닉_esc);
    if ($모드 === 'hard') {
      if ($배팅원 > 0) {
        db_query("UPDATE tb_member SET point = point + {$배팅원} WHERE name = '{$닉_esc}' LIMIT 1");
      }
      if (function_exists('지급로그')) {
        지급로그('홀짝도전-타임아웃환급', $두자리닉넴, '', 0, $배팅원);
      }
      return ['환급' => $배팅원, '수수료' => 0, '금고' => 0, '로또' => 0];
    }
    $수수료 = (int)floor($배팅원 * 50 / 100);
    $환급 = $배팅원 - $수수료;
    if ($환급 > 0) {
      db_query("UPDATE tb_member SET point = point + {$환급} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    $분배 = 홀짝_수수료_금고로또배분($수수료);
    if (function_exists('지급로그')) {
      지급로그('홀짝도전-타임아웃환급', $두자리닉넴, '', $수수료, $환급);
    }
    return ['환급' => $환급, '수수료' => $수수료, '금고' => $분배['금고'], '로또' => $분배['로또']];
  }
}

if (!function_exists('홀짝_연승포기_수수료처리')) {
  /**
   * @return array{수수료:int,유지분:int,금고:int,로또:int,모드:string}
   */
  function 홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $맥스배, $모드 = null) {
    $맥스배 = max(0, (int)$맥스배);
    $모드 = ($모드 !== null) ? 홀짝_모드_정규화($모드) : 홀짝_모드_읽기($닉_esc);
    if ($모드 === 'hard') {
      $수수료 = (int)floor($맥스배 * 1 / 100);
      $유지분 = $맥스배 - $수수료;
      $분배 = ['금고' => 0, '로또' => 0];
      if ($수수료 > 0) {
        db_query("UPDATE tb_member SET point = point - {$수수료} WHERE name = '{$닉_esc}' LIMIT 1");
        $분배['로또'] = 홀짝_로또수수료_적립($수수료);
        if (function_exists('지급로그')) {
          지급로그('홀짝도전-연승포기', $두자리닉넴, '', $수수료, $유지분);
        }
      }
      return ['수수료' => $수수료, '유지분' => $유지분, '금고' => 0, '로또' => $분배['로또'], '모드' => $모드];
    }
    $수수료 = (int)floor($맥스배 * 20 / 100);
    $유지분 = $맥스배 - $수수료;
    $분배 = ['금고' => 0, '로또' => 0];
    if ($수수료 > 0) {
      db_query("UPDATE tb_member SET point = point - {$수수료} WHERE name = '{$닉_esc}' LIMIT 1");
      $분배 = 홀짝_수수료_금고로또배분($수수료);
      if (function_exists('지급로그')) {
        지급로그('홀짝도전-연승포기', $두자리닉넴, '', $수수료, $유지분);
      }
    }
    return ['수수료' => $수수료, '유지분' => $유지분, '금고' => $분배['금고'], '로또' => $분배['로또'], '모드' => $모드];
  }
}

if (!function_exists('홀짝_수수료_분배문구')) {
  function 홀짝_수수료_분배문구(array $분배, $단위 = '냥') {
    $금고 = (int)($분배['금고'] ?? 0);
    $로또 = (int)($분배['로또'] ?? 0);
    if ($금고 <= 0 && $로또 > 0) {
      return '로또 ' . number_format($로또) . "{$단위}";
    }
    if ($로또 <= 0 && $금고 > 0) {
      return '금고 ' . number_format($금고) . "{$단위}";
    }
    return '수수료 금고 ' . number_format($금고) . "{$단위}·로또 " . number_format($로또) . "{$단위}";
  }
}

if (!function_exists('홀짝_취소무승부_환급문구')) {
  function 홀짝_취소무승부_환급문구(array $결과, $모드, $단위 = '냥') {
    $모드 = 홀짝_모드_정규화($모드);
    $환급 = (int)($결과['환급'] ?? 0);
    if ($모드 === 'hard') {
      return '+' . number_format($환급) . "{$단위} 전액 환급 (수수료 없음)";
    }
    $수수료 = (int)($결과['수수료'] ?? 0);
    $분배 = ['금고' => (int)($결과['금고'] ?? 0), '로또' => (int)($결과['로또'] ?? 0)];
    return '+' . number_format($환급) . "{$단위} 환급 (10% · " . 홀짝_수수료_분배문구($분배, $단위) . ')';
  }
}

if (!function_exists('홀짝_타임아웃_환급문구')) {
  function 홀짝_타임아웃_환급문구(array $결과, $모드, $단위 = '냥') {
    $모드 = 홀짝_모드_정규화($모드);
    $환급 = (int)($결과['환급'] ?? 0);
    if ($모드 === 'hard') {
      return '+' . number_format($환급) . "{$단위} 환급 (수수료 없음)";
    }
    $분배 = ['금고' => (int)($결과['금고'] ?? 0), '로또' => (int)($결과['로또'] ?? 0)];
    return '+' . number_format($환급) . "{$단위} 환급 (50% · " . 홀짝_수수료_분배문구($분배, $단위) . ')';
  }
}

if (!function_exists('홀짝_연승포기_수수료문구')) {
  function 홀짝_연승포기_수수료문구(array $포기, $단위 = '냥') {
    $수수료 = (int)($포기['수수료'] ?? 0);
    if ($수수료 <= 0) {
      return '';
    }
    $모드 = 홀짝_모드_정규화($포기['모드'] ?? 'easy');
    if ($모드 === 'hard') {
      return '최고배팅 1% 수수료 · ' . 홀짝_수수료_분배문구(['금고' => 0, '로또' => (int)($포기['로또'] ?? 0)], $단위);
    }
    return '최고배팅 20% · ' . 홀짝_수수료_분배문구(['금고' => (int)($포기['금고'] ?? 0), '로또' => (int)($포기['로또'] ?? 0)], $단위);
  }
}

if (!function_exists('홀짝_신용회복_pending이면_취소환급')) {
  /**
   * 신용 회복 직후 3시간 락 중에는 정산 불가 → 진행 중 배팅이 있으면 취소와 동일(90% 환급·10% 수수료: 금고·로또 각 50%) 처리.
   *
   * @return array{msg:string,refund:int,gumgo:int,lotto:int,tax:int}|null 락 중이면 메시지·환급액, 아니면 null
   */
  function 홀짝_신용회복_pending이면_취소환급($닉, $판, $단위 = '냥') {
    $head = 홀짝_신용회복_홀짝_금지문구($닉);
    if ($head === null) {
      return null;
    }
    $닉_esc = addslashes($닉);
    $환불 = (int)($판['pending_bet'] ?? 0);
    if ($환불 <= 0) {
      return null;
    }
    $모드 = 홀짝_모드_읽기($닉_esc);
    $환급결과 = 홀짝_취소무승부_환급처리($닉_esc, $닉, $환불, $모드);
    db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('홀짝도전-신용회복제한환급', $닉, '', (int)$환급결과['수수료'], (int)$환급결과['환급']);
    }
    $msg = $head . "\n\n⛔ 신용 회복 직후 제한으로 진행 중 배팅을 취소했습니다.\n✅ " . 홀짝_취소무승부_환급문구($환급결과, $모드, $단위);
    return ['msg' => $msg, 'refund' => (int)$환급결과['환급'], 'gumgo' => (int)$환급결과['금고'], 'lotto' => (int)$환급결과['로또'], 'tax' => (int)$환급결과['금고']];
  }
}

if (!function_exists('홀짝_도전_본인상태_정리')) {
  /**
   * 채팅 .도전 전 본인 pending·연승만 검사 (웹 multi 와 동일 — 타인 진행으로 막지 않음).
   *
   * @return array{block:?string, notice:string}
   */
  function 홀짝_도전_본인상태_정리($두자리닉넴, $단위 = '냥', $만료초 = 1800) {
    $닉_esc = addslashes($두자리닉넴);
    $notice = '';
    $block = null;
    $만료초 = (int)$만료초;
    if ($만료초 <= 0) {
      $만료초 = 1800;
    }

    $진행행 = @db_select("
      SELECT pending_at, pending_bet,
             TIMESTAMPDIFF(SECOND, pending_at, NOW()) AS elapsed_sec,
             DATE_FORMAT(DATE_ADD(pending_at, INTERVAL {$만료초} SECOND), '%H:%i') AS end_hm
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND pending_bet > 0
      LIMIT 1
    ");
    if ($진행행 && (int)($진행행['pending_bet'] ?? 0) > 0) {
      $경과초 = (int)($진행행['elapsed_sec'] ?? 0);
      $종료시각 = trim((string)($진행행['end_hm'] ?? ''));
      if ($경과초 < $만료초) {
        $모드 = 홀짝_모드_읽기($닉_esc);
        $취소안내 = ($모드 === 'hard')
          ? '배팅 전액 환급(수수료 없음)'
          : '배팅 90% 환급·10% 수수료 금고50%·로또50%';
        if ($종료시각 !== '') {
          $block = "⏳ {$종료시각} 종료 전 `홀`/`짝` 또는 `.도전 취소`/`ㅈㅈ 취소`({$취소안내})";
        } else {
          $block = "⏳ `홀`/`짝` 또는 `.도전 취소`/`ㅈㅈ 취소`({$취소안내})";
        }
        return ['block' => $block, 'notice' => ''];
      }
      $만료환 = (int)($진행행['pending_bet'] ?? 0);
      $만료모드 = 홀짝_모드_읽기($닉_esc);
      $만료환급 = 0;
      $만료분배 = ['금고' => 0, '로또' => 0];
      if ($만료환 > 0) {
        $타임 = 홀짝_타임아웃_환급처리($닉_esc, $두자리닉넴, $만료환, $만료모드);
        $만료환급 = (int)$타임['환급'];
        $만료분배 = ['금고' => (int)$타임['금고'], '로또' => (int)$타임['로또']];
      }
      db_query("UPDATE tb_odd_even_state SET pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL WHERE nick = '{$닉_esc}' LIMIT 1");
      $notice = "⏰ 본인 30분 무응답 자동 정산\n"
              . "  " . 홀짝_타임아웃_환급문구(['환급' => $만료환급, '금고' => $만료분배['금고'], '로또' => $만료분배['로또']], $만료모드, $단위) . "\n\n";
    }

    $연승행 = @db_select("
      SELECT streak, streak_max_bet,
             TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS elapsed_sec
      FROM tb_odd_even_state
      WHERE nick = '{$닉_esc}' AND streak > 0
      LIMIT 1
    ");
    if ($연승행 && (int)($연승행['streak'] ?? 0) > 0) {
      $연승경과초 = (int)($연승행['elapsed_sec'] ?? 0);
      if ($연승경과초 >= $만료초) {
        $이전연승 = (int)($연승행['streak'] ?? 0);
        $이전맥스배 = (int)($연승행['streak_max_bet'] ?? 0);
        $포기 = 홀짝_연승포기_수수료처리($닉_esc, $두자리닉넴, $이전맥스배, 홀짝_모드_읽기($닉_esc));
        db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
        $포기문구 = 홀짝_연승포기_수수료문구($포기, $단위);
        $notice .= "⏰ 본인 30분 무응답 → 연승({$이전연승}) 자동 포기\n"
                 . "  맥스배 " . number_format($이전맥스배) . "{$단위}"
                 . ($포기문구 !== '' ? " · {$포기문구}" : '') . "\n\n";
      }
    }

    return ['block' => $block, 'notice' => $notice];
  }
}

if (!function_exists('홀짝_연승_최대')) {
  /** 완주 목표 연승 수 (5연승 달성 시 자동 종료) */
  function 홀짝_연승_최대() {
    return 5;
  }
}

if (!function_exists('홀짝_연승_저장_상한')) {
  /**
   * tb_odd_even_state.streak 컬럼 정상 상한.
   * 4연승까지 달성 후 5번째 판 배팅 중일 때 streak=4 (다음 승이 5연승 완주).
   */
  function 홀짝_연승_저장_상한() {
    return max(0, 홀짝_연승_최대() - 1);
  }
}

if (!function_exists('홀짝_연승_정규화')) {
  /** @return int 0 .. 저장상한 */
  function 홀짝_연승_정규화($streak) {
    $s = (int)$streak;
    if ($s < 0) {
      return 0;
    }
    $cap = 홀짝_연승_저장_상한();
    if ($s > $cap) {
      return $cap;
    }
    return $s;
  }
}

if (!function_exists('홀짝_연승_읽기_및_복구')) {
  /**
   * 비정상 streak(5연승 완주 후 리셋 누락 등 ≥최대) 은 0으로 복구.
   * 저장상한 초과(5 이상)도 동일 처리.
   *
   * @return int 복구·정규화 후 사용할 streak
   */
  function 홀짝_연승_읽기_및_복구($닉_esc, $rawStreak) {
    $raw = (int)$rawStreak;
    if ($raw < 0) {
      db_query("UPDATE tb_odd_even_state SET streak = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
      return 0;
    }
    if ($raw > 홀짝_연승_저장_상한()) {
      db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE nick = '{$닉_esc}' LIMIT 1");
      return 0;
    }
    return $raw;
  }
}

if (!function_exists('홀짝_연승_달성_표시')) {
  /** 이번 판 승리 후 표시용 연승 수 (최대 5) */
  function 홀짝_연승_달성_표시($streak달성) {
    return min(max(1, (int)$streak달성), 홀짝_연승_최대());
  }
}

if (!function_exists('홀짝_판_조회')) {
  /** @return array|null tb_odd_even_state 행 */
  function 홀짝_판_조회($닉_esc) {
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return null;
    }
    $판 = @db_select("
      SELECT streak, streak_max_bet, pending_bet, pending_answer,
             pending_win_mult, pending_lose_mult, pending_at,
             odds_mode, updated_at
      FROM tb_odd_even_state
      WHERE nick = '{$nick}'
      LIMIT 1
    ");
    return is_array($판) ? $판 : null;
  }
}

if (!function_exists('홀짝_배팅_저장')) {
  /** @return bool */
  function 홀짝_배팅_저장($닉_esc, $streak, $누적맥스, $배팅, $정답, $win, $lose) {
    $nick = addslashes((string)$닉_esc);
    return (bool)db_query("
      INSERT INTO tb_odd_even_state
        (nick, streak, streak_max_bet, pending_bet, pending_answer, pending_win_mult, pending_lose_mult, pending_at)
      VALUES
        ('{$nick}', " . (int)$streak . ", " . (int)$누적맥스 . ", " . (int)$배팅 . ", " . (int)$정답 . ", " . (int)$win . ", " . (int)$lose . ", NOW())
      ON DUPLICATE KEY UPDATE
        streak = VALUES(streak),
        pending_bet = VALUES(pending_bet),
        pending_answer = VALUES(pending_answer),
        pending_win_mult = VALUES(pending_win_mult),
        pending_lose_mult = VALUES(pending_lose_mult),
        pending_at = VALUES(pending_at)
    ");
  }
}

if (!function_exists('홀짝_상태_정산반영')) {
  /**
   * 정산 후 연승·최고배팅·pending 초기화 (행 없으면 INSERT — bet_pick 즉시정산 대응).
   * @return bool
   */
  function 홀짝_상태_정산반영($닉_esc, $streak, $streak_max_bet) {
    $nick = addslashes((string)$닉_esc);
    if ($nick === '') {
      return false;
    }
    $streak = 홀짝_연승_정규화((int)$streak);
    $streak_max_bet = max(0, (int)$streak_max_bet);
    $clear = 'pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL';
    return (bool)db_query("
      INSERT INTO tb_odd_even_state
        (nick, streak, streak_max_bet, pending_bet, pending_answer, pending_win_mult, pending_lose_mult, pending_at)
      VALUES
        ('{$nick}', {$streak}, {$streak_max_bet}, 0, NULL, 0, 0, NULL)
      ON DUPLICATE KEY UPDATE
        streak = VALUES(streak),
        streak_max_bet = VALUES(streak_max_bet),
        {$clear}
    ");
  }
}

if (!function_exists('홀짝_방금배팅판_만들기')) {
  /** 같은 요청 내 즉시정산용 — DB 재조회 없이 정산 */
  function 홀짝_방금배팅판_만들기($streak, $누적맥스, $배팅, $정답, $win, $lose) {
    return [
      'streak' => (int)$streak,
      'streak_max_bet' => (int)$누적맥스,
      'pending_bet' => (int)$배팅,
      'pending_answer' => (int)$정답,
      'pending_win_mult' => (int)$win,
      'pending_lose_mult' => (int)$lose,
      'pending_at' => date('Y-m-d H:i:s'),
    ];
  }
}

require_once __DIR__ . '/odd_even_odds.php';
