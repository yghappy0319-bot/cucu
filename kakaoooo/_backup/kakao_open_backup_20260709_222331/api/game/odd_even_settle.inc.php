<?php
/**
 * 홀짝 도전 픽 정산 (웹·info2·mutual 공통)
 */
require_once __DIR__ . '/odd_even_guards.php';
require_once __DIR__ . '/odd_even_profit.inc.php';

if (!function_exists('홀짝_pending_초기화_set')) {
  function 홀짝_pending_초기화_set() {
    return 'pending_bet = 0, pending_answer = NULL, pending_win_mult = 0, pending_lose_mult = 0, pending_at = NULL';
  }
}

if (!function_exists('홀짝_픽_글자')) {
  /** @return string 홀|짝|무 */
  function 홀짝_픽_글자($픽) {
    $p = (int)$픽;
    if ($p === 1) return '홀';
    if ($p === 2) return '짝';
    if ($p === 3) return '무';
    return '?';
  }
}

if (!function_exists('홀짝_도전_픽정산')) {
  /**
   * @param array $판 tb_odd_even_state 행 (streak, streak_max_bet, pending_*)
   * @param int $유저픽 1=홀 2=짝
   */
  function 홀짝_도전_픽정산($판, $유저픽, array $opts) {
    $닉_esc = (string)($opts['닉_esc'] ?? '');
    $두자리닉넴 = (string)($opts['두자리닉넴'] ?? '');
    $단위 = (string)($opts['단위'] ?? '냥');
    $연승최대 = (int)($opts['연승최대'] ?? 홀짝_연승_최대());
    $single_reset = !empty($opts['single_room_reset_others']);
    $merge_bet = !empty($opts['merge_bet_deduct']);
    $state_row = isset($opts['state_row']) && is_array($opts['state_row']) ? $opts['state_row'] : null;
    $확률모드 = isset($opts['odds_mode'])
      ? 홀짝_모드_정규화($opts['odds_mode'])
      : 홀짝_모드_읽기($닉_esc, $state_row);

    $유저픽 = (int)$유저픽;
    if ($유저픽 !== 1 && $유저픽 !== 2) {
      return ['ok' => false, 'data' => '❌ `홀` 또는 `짝`만 선택할 수 있어요.'];
    }

    $시스템 = (int)($판['pending_answer'] ?? 0);
    $배팅 = (int)($판['pending_bet'] ?? 0);
    $db행 = $state_row !== null ? $state_row : 홀짝_판_조회($닉_esc);
    $판streak = (int)($판['streak'] ?? 0);
    $dbStreak = is_array($db행) ? (int)($db행['streak'] ?? 0) : $판streak;
    if (isset($opts['streak_before'])) {
      $streak전 = 홀짝_연승_정규화((int)$opts['streak_before']);
    } else {
      // 정산 직전 DB 연승을 우선 (bet_pick 가상판·채팅 동시 진행 시 어긋남 방지)
      $streak전 = 홀짝_연승_읽기_및_복구($닉_esc, max($판streak, $dbStreak));
    }
    $winM = (int)($판['pending_win_mult'] ?? 0);
    $loseM = (int)($판['pending_lose_mult'] ?? 0);

    $시스템글 = ($시스템 === 1) ? '홀' : (($시스템 === 2) ? '짝' : '무(3)');
    $유저글 = ($유저픽 === 1) ? '홀' : '짝';

    // 서버 무(3) → 무승부 환급
    if ($시스템 === 3) {
      $무결과 = 홀짝_취소무승부_환급처리($닉_esc, $두자리닉넴, $배팅, $확률모드, !$merge_bet);
      $무환급 = (int)$무결과['환급'];
      $무금고 = (int)$무결과['수수료'];
      $무분배 = ['금고' => (int)$무결과['금고'], '로또' => (int)$무결과['로또']];
      $point_delta = $merge_bet ? ($무환급 - $배팅) : $무환급;
      if ($point_delta !== 0) {
        db_query("UPDATE tb_member SET point = point + {$point_delta} WHERE name = '{$닉_esc}' LIMIT 1");
      }
      if (!홀짝_상태_정산반영($닉_esc, 0, 0)) {
        return ['ok' => false, 'data' => '❌ 연승 상태 저장에 실패했어요. 관리자에게 문의해 주세요.'];
      }
      홀짝_지급로그('홀짝도전-무승부', $두자리닉넴, $무금고, $무환급);
      @db_query("INSERT INTO tb_odd_even_log (nick, bet, streak_before, win_mult, lose_mult, system_pick, user_pick, result, delta_point, streak_after) VALUES (
        '{$닉_esc}', {$배팅}, {$streak전}, {$winM}, {$loseM}, 3, {$유저픽}, 'push', {$무환급}, 0)");
      if (function_exists('홀짝_순이익_반영')) {
        홀짝_순이익_반영($닉_esc, 'push', $무환급, $배팅);
      }
      $msg = "🤝 무승부! " . 홀짝_취소무승부_환급문구($무결과, $확률모드, $단위) . " · 연승 리셋";
      return [
        'ok' => true, 'result' => 'push', 'data' => $msg,
        'user_pick' => $유저글, 'system_pick' => $시스템글,
        'refund' => $무환급, 'gumgo' => $무금고, 'lotto' => $무분배['로또'], 'tax' => $무분배['금고'], 'streak_after' => 0,
      ];
    }

    if ($유저픽 === $시스템) {
      $streak달성 = $streak전 + 1;
      $당첨금 = $배팅 * $winM;
      $연승수수료 = 0;
      $연승수수료분배 = ['금고' => 0, '로또' => 0];
      $하드보너스배수 = 0;
      if ($확률모드 === 'easy') {
        if (!empty($opts['web_payback'])) {
          require_once __DIR__ . '/odd_even_payback.inc.php';
          $이지수수료 = 홀짝_이지_연승승리_수수료적용_웹($배팅, $닉_esc);
        } else {
          $이지수수료 = 홀짝_이지_연승승리_수수료적용($배팅);
        }
        $연승수수료 = (int)$이지수수료['총'];
        $연승수수료분배 = ['금고' => (int)$이지수수료['금고'], '로또' => (int)$이지수수료['로또']];
      } elseif ($확률모드 === 'hard') {
        $하드보너스배수 = 홀짝_하드_연승_보너스배수($streak전);
        if ($하드보너스배수 > 1) {
          $당첨금 *= $하드보너스배수;
        }
      }
      $실지급 = $당첨금 - $연승수수료;
      $자동종료 = ($streak달성 >= $연승최대);
      $표시연승 = 홀짝_연승_달성_표시($streak달성);
      if ($자동종료) {
        $streak후 = 0;
        $새맥스 = 0;
        $완주문구 = "\n🏆 {$연승최대}연승 완주! 다음 판부터 첫 도전(승×3·패×2)으로 초기화";
        $로그연승후 = 0;
      } else {
        $streak후 = 홀짝_연승_정규화($streak달성);
        $이전맥스 = (int)($판['streak_max_bet'] ?? 0);
        if (is_array($db행)) {
          $이전맥스 = max($이전맥스, (int)($db행['streak_max_bet'] ?? 0));
        }
        $새맥스 = max($이전맥스, $배팅);
        $완주문구 = '';
        $로그연승후 = $streak후;
      }
      if ($single_reset) {
        db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0 WHERE streak > 0 AND nick <> '{$닉_esc}'");
      }
      if (!홀짝_상태_정산반영($닉_esc, $streak후, $새맥스)) {
        return ['ok' => false, 'data' => '❌ 연승 상태 저장에 실패했어요. (고액 배팅 시 DB 마이그레이션 필요) 관리자에게 문의해 주세요.'];
      }
      $point_delta = $merge_bet ? ($실지급 - $배팅) : $실지급;
      db_query("UPDATE tb_member SET point = point + {$point_delta} WHERE name = '{$닉_esc}' LIMIT 1");
      홀짝_지급로그('홀짝도전-승', $두자리닉넴, $연승수수료, $실지급);
      @db_query("INSERT INTO tb_odd_even_log (nick, bet, streak_before, win_mult, lose_mult, system_pick, user_pick, result, delta_point, streak_after) VALUES (
        '{$닉_esc}', {$배팅}, {$streak전}, {$winM}, {$loseM}, {$시스템}, {$유저픽}, 'win', {$실지급}, {$로그연승후})");
      if (function_exists('홀짝_순이익_반영')) {
        홀짝_순이익_반영($닉_esc, 'win', $실지급, $배팅);
      }
      $msg = "🎉 승! {$유저글}·{$시스템글} 🔥 {$표시연승}연승 · 당첨 +" . number_format($실지급) . "{$단위}";
      if ($하드보너스배수 > 1) {
        $msg .= "\n🎊 하드모드 x{$하드보너스배수} 보너스!";
      }
      if ($연승수수료 > 0) {
        $msg .= "\n(이지모드 연승수수료 10% -" . number_format($연승수수료) . "{$단위} · " . 홀짝_수수료_분배문구($연승수수료분배, $단위) . ")";
      }
      $msg .= $완주문구;
      return [
        'ok' => true, 'result' => 'win', 'data' => $msg,
        'user_pick' => $유저글, 'system_pick' => $시스템글,
        'win_amount' => $실지급, 'streak_after' => $streak후, 'streak_won' => $표시연승,
        'streak_max_bet' => $새맥스, 'played_win_mult' => $winM, 'streak_completed' => $자동종료,
        'win_fee' => $연승수수료, 'odds_mode' => $확률모드,
        'hard_bonus_mult' => $하드보너스배수,
        'hard_x2' => $하드보너스배수 >= 2,
      ];
    }

    $추가패 = $배팅 * max(0, $loseM - 1);
    $총손실 = $배팅 * $loseM;
    if ($merge_bet) {
      if ($총손실 > 0) {
        db_query("UPDATE tb_member SET point = point - {$총손실} WHERE name = '{$닉_esc}' LIMIT 1");
      }
    } elseif ($추가패 > 0) {
      db_query("UPDATE tb_member SET point = point - {$추가패} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    if (!홀짝_상태_정산반영($닉_esc, 0, 0)) {
      return ['ok' => false, 'data' => '❌ 연승 상태 저장에 실패했어요. 관리자에게 문의해 주세요.'];
    }
    홀짝_지급로그('홀짝도전-패', $두자리닉넴, 0, $총손실);
    @db_query("INSERT INTO tb_odd_even_log (nick, bet, streak_before, win_mult, lose_mult, system_pick, user_pick, result, delta_point, streak_after) VALUES (
      '{$닉_esc}', {$배팅}, {$streak전}, {$winM}, {$loseM}, {$시스템}, {$유저픽}, 'lose', " . (-$총손실) . ", 0)");
    if (function_exists('홀짝_순이익_반영')) {
      홀짝_순이익_반영($닉_esc, 'lose', -$총손실, $배팅);
    }
    $msg = "😢 패 {$유저글}≠{$시스템글} · -" . number_format($총손실) . "{$단위} (×{$loseM}) · 연승 리셋";
    return [
      'ok' => true, 'result' => 'lose', 'data' => $msg,
      'user_pick' => $유저글, 'system_pick' => $시스템글,
      'loss' => $총손실, 'streak_after' => 0, 'played_lose_mult' => $loseM,
    ];
  }
}
