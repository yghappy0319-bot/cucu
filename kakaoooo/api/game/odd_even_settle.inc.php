<?php
/**
 * 홀짝 도전 픽 정산 (웹·info2·mutual 공통)
 *
 * 대금액: (int)/BIGINT 금지 — 배팅·당첨·손실은 정수 문자열 + bcmath 로 계산한다.
 * (int) 로 다루면 922경(PHP_INT_MAX)에서 잘리고, 곱하면 float 로 변질돼 지급액이 깨진다.
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

if (!function_exists('홀짝_보유냥_증감')) {
  /** 부호 있는 증감을 문자열 그대로 반영 (양수=지급, 음수=차감) */
  function 홀짝_보유냥_증감($닉_esc, $증가분, $감소분) {
    $증가 = 홀짝_냥($증가분);
    $감소 = 홀짝_냥($감소분);
    $비교 = 홀짝_냥_비교($증가, $감소);
    if ($비교 === 0) {
      return;
    }
    if ($비교 > 0) {
      $delta = 홀짝_냥_sql(홀짝_냥_차($증가, $감소));
      db_query("UPDATE tb_member SET point = point + {$delta} WHERE name = '{$닉_esc}' LIMIT 1");
      return;
    }
    $delta = 홀짝_냥_sql(홀짝_냥_차($감소, $증가));
    db_query("UPDATE tb_member SET point = point - {$delta} WHERE name = '{$닉_esc}' LIMIT 1");
  }
}

if (!function_exists('홀짝_로그_기록')) {
  /** @param string $delta_sql 부호 포함 정수 문자열 */
  function 홀짝_로그_기록($닉_esc, $배팅, $streak전, $winM, $loseM, $시스템, $유저픽, $result, $delta_sql, $streak후) {
    홀짝_배팅컬럼_확보();
    $bet = 홀짝_냥_sql($배팅);
    $result = addslashes((string)$result);
    @db_query("INSERT INTO tb_odd_even_log
      (nick, bet, streak_before, win_mult, lose_mult, system_pick, user_pick, result, delta_point, streak_after)
      VALUES ('{$닉_esc}', {$bet}, " . (int)$streak전 . ", " . (int)$winM . ", " . (int)$loseM . ", "
      . (int)$시스템 . ", " . (int)$유저픽 . ", '{$result}', {$delta_sql}, " . (int)$streak후 . ")");
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

    $배팅 = 홀짝_냥($판['pending_bet'] ?? 0);
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

    // 깎기 ON → 픽 때 재추첨(유저픽 깎기) · OFF → 사전 봉인(pending_answer)
    if (!function_exists('홀짝_정답_선정')) {
      require_once __DIR__ . '/odd_even_odds.php';
    }
    $깎기활성 = function_exists('홀짝_깎기_활성인가') && 홀짝_깎기_활성인가();
    if ($깎기활성) {
      $깎기p = function_exists('홀짝_깎기퍼센트_배팅액')
        ? 홀짝_깎기퍼센트_배팅액($배팅, $닉_esc, $merge_bet)
        : (function_exists('홀짝_깎기퍼센트_뽑기') ? 홀짝_깎기퍼센트_뽑기($닉_esc !== '' ? $닉_esc : $두자리닉넴) : 3);
      $시스템 = (int)홀짝_정답_선정($확률모드, $유저픽, $깎기p);
    } else {
      $봉인답 = (int)($판['pending_answer'] ?? 0);
      if (function_exists('홀짝_봉인_유효인가') && 홀짝_봉인_유효인가($봉인답)) {
        $시스템 = $봉인답;
      } else {
        $시스템 = (int)홀짝_정답_선정($확률모드, null, 0);
      }
    }

    $시스템글 = ($시스템 === 1) ? '홀' : (($시스템 === 2) ? '짝' : '무(3)');
    $유저글 = ($유저픽 === 1) ? '홀' : '짝';

    $봉인다음 = function () use ($닉_esc) {
      if (function_exists('홀짝_봉인_다음준비')) {
        홀짝_봉인_다음준비($닉_esc);
      }
    };

    // 서버 무(3) → 무승부 환급
    if ($시스템 === 3) {
      // merge_bet: 배팅을 아직 차감하지 않았으므로 환급도 하지 않는다(순변동 0).
      // 그 외: 배팅이 이미 빠져 있으므로 환급처리에서 한 번만 되돌려 준다.
      $무결과 = 홀짝_취소무승부_환급처리($닉_esc, $두자리닉넴, $배팅, $확률모드, !$merge_bet);
      $무환급 = 홀짝_냥($무결과['환급'] ?? 0);
      $무금고 = 홀짝_냥($무결과['수수료'] ?? 0);
      $무분배 = ['금고' => 홀짝_냥($무결과['금고'] ?? 0), '로또' => 홀짝_냥($무결과['로또'] ?? 0)];
      if (!홀짝_상태_정산반영($닉_esc, 0, 0)) {
        return ['ok' => false, 'data' => '❌ 연승 상태 저장에 실패했어요. 관리자에게 문의해 주세요.'];
      }
      홀짝_지급로그('홀짝도전-무승부', $두자리닉넴, $무금고, $무환급);
      홀짝_로그_기록($닉_esc, $배팅, $streak전, $winM, $loseM, 3, $유저픽, 'push', 홀짝_냥_sql($무환급), 0);
      if (function_exists('홀짝_순이익_반영')) {
        홀짝_순이익_반영($닉_esc, 'push', $무환급, $배팅);
      }
      $봉인다음();
      $msg = "🤝 무승부! " . 홀짝_취소무승부_환급문구($무결과, $확률모드, $단위) . " · 연승 리셋";
      return [
        'ok' => true, 'result' => 'push', 'data' => $msg,
        'user_pick' => $유저글, 'system_pick' => $시스템글,
        'refund' => $무환급, 'gumgo' => $무금고, 'lotto' => $무분배['로또'], 'tax' => $무분배['금고'], 'streak_after' => 0,
        'giveup_offer' => 0,
      ];
    }

    if ($유저픽 === $시스템) {
      $streak달성 = $streak전 + 1;
      $당첨금 = 홀짝_냥_곱($배팅, (string)max(0, $winM));
      $하드보너스배수 = 홀짝_하드_연승_보너스배수($streak전);
      if ($하드보너스배수 > 1) {
        $당첨금 = 홀짝_냥_곱($당첨금, (string)$하드보너스배수);
      }
      $자동종료 = ($streak달성 >= $연승최대);
      $표시연승 = 홀짝_연승_달성_표시($streak달성);
      // 연승 승리 수수료: 배팅 10%(금고5·로또5)
      $연승수수료결과 = 홀짝_하드_연승승리_수수료적용($배팅);
      $연승수수료율 = (int)($연승수수료결과['율'] ?? 10);
      $연승수수료 = 홀짝_냥($연승수수료결과['총'] ?? 0);
      $연승수수료분배 = [
        '금고' => 홀짝_냥($연승수수료결과['금고'] ?? 0),
        '로또' => 홀짝_냥($연승수수료결과['로또'] ?? 0),
      ];
      $실지급 = 홀짝_냥_차($당첨금, $연승수수료);
      if ($자동종료) {
        $streak후 = 0;
        $새맥스 = '0';
        $완주문구 = "\n🏆 {$연승최대}연승 완주! 다음 판부터 첫 도전(승×3·패×3)으로 초기화";
        $로그연승후 = 0;
      } else {
        $streak후 = 홀짝_연승_정규화($streak달성);
        $이전맥스 = 홀짝_냥($판['streak_max_bet'] ?? 0);
        if (is_array($db행)) {
          $이전맥스 = 홀짝_냥_최대($이전맥스, $db행['streak_max_bet'] ?? 0);
        }
        $새맥스 = 홀짝_냥_최대($이전맥스, $배팅);
        $완주문구 = '';
        $로그연승후 = $streak후;
      }
      if ($single_reset) {
        홀짝_연승포기_오퍼_컬럼확보();
        db_query("UPDATE tb_odd_even_state SET streak = 0, streak_max_bet = 0, giveup_offer = 0 WHERE streak > 0 AND nick <> '{$닉_esc}'");
      }
      if (!홀짝_상태_정산반영($닉_esc, $streak후, $새맥스)) {
        return ['ok' => false, 'data' => '❌ 연승 상태 저장에 실패했어요. (고액 배팅 시 DB 마이그레이션 필요) 관리자에게 문의해 주세요.'];
      }
      홀짝_보유냥_증감($닉_esc, $실지급, $merge_bet ? $배팅 : '0');
      홀짝_지급로그('홀짝도전-승', $두자리닉넴, $연승수수료, $실지급);
      홀짝_로그_기록($닉_esc, $배팅, $streak전, $winM, $loseM, $시스템, $유저픽, 'win', 홀짝_냥_sql($실지급), $로그연승후);
      if (function_exists('홀짝_순이익_반영')) {
        홀짝_순이익_반영($닉_esc, 'win', $실지급, $배팅);
      }
      $실지급표시 = 홀짝_금액표시($실지급, $단위);
      $msg = "🎉 승! {$유저글}·{$시스템글} 🔥 {$표시연승}연승 · 당첨 +{$실지급표시}";
      if ($하드보너스배수 > 1) {
        $msg .= "\n🎊 하드모드 x{$하드보너스배수} 보너스!";
      }
      if ($연승수수료 !== '0') {
        $수수료표시 = 홀짝_금액표시($연승수수료, $단위);
        $msg .= "\n(연승수수료 {$연승수수료율}% -{$수수료표시} · " . 홀짝_수수료_분배문구($연승수수료분배, $단위) . ")";
      }
      $msg .= $완주문구;
      // 2연승~(완주 전): 연승 포기 버튼 항상 ON
      $giveup_offer = 홀짝_연승포기_오퍼_승리후갱신($닉_esc, $streak후);
      $봉인다음();
      return [
        'ok' => true, 'result' => 'win', 'data' => $msg,
        'user_pick' => $유저글, 'system_pick' => $시스템글,
        'win_amount' => $실지급, 'streak_after' => $streak후, 'streak_won' => $표시연승,
        'streak_max_bet' => $새맥스, 'played_win_mult' => $winM, 'streak_completed' => $자동종료,
        'win_fee' => $연승수수료, 'win_fee_pct' => $연승수수료율, 'odds_mode' => $확률모드,
        'hard_bonus_mult' => $하드보너스배수,
        'hard_x2' => $하드보너스배수 >= 2,
        'giveup_offer' => $giveup_offer,
      ];
    }

    $총손실 = 홀짝_냥_곱($배팅, (string)max(0, $loseM));
    $하드패배2배 = false;
    if (홀짝_하드_연승_패배2배_발동($streak전)) {
      $하드패배2배 = true;
      $총손실 = 홀짝_냥_곱($총손실, '2');
    }
    // 채팅: 선배팅 차감분 제외한 추가 차감 / 웹 merge: 총손실 일괄 차감
    홀짝_보유냥_증감($닉_esc, '0', $merge_bet ? $총손실 : 홀짝_냥_차($총손실, $배팅));
    if (!홀짝_상태_정산반영($닉_esc, 0, 0)) {
      return ['ok' => false, 'data' => '❌ 연승 상태 저장에 실패했어요. 관리자에게 문의해 주세요.'];
    }
    홀짝_지급로그('홀짝도전-패', $두자리닉넴, 0, $총손실);
    $손실delta = ($총손실 === '0') ? '0' : ('-' . 홀짝_냥_sql($총손실));
    홀짝_로그_기록($닉_esc, $배팅, $streak전, $winM, $loseM, $시스템, $유저픽, 'lose', $손실delta, 0);
    if (function_exists('홀짝_순이익_반영')) {
      홀짝_순이익_반영($닉_esc, 'lose', $손실delta, $배팅);
    }
    $페이백적립 = '0';
    if (!empty($opts['web_payback'])) {
      if (!function_exists('홀짝_웹_페이백_패배적립')) {
        require_once __DIR__ . '/odd_even_payback.inc.php';
      }
      if (function_exists('홀짝_웹_페이백_패배적립')) {
        $페이백적립 = 홀짝_웹_페이백_패배적립($닉_esc, $총손실, $확률모드);
      }
    }
    $손실표시 = 홀짝_금액표시($총손실, $단위);
    $msg = "😢 패 {$유저글}≠{$시스템글} · -{$손실표시} (×{$loseM}) · 연승 리셋";
    if ($하드패배2배) {
      $msg .= "\n💀 하드모드 패배 2배 차감!";
    }
    $봉인다음();
    return [
      'ok' => true, 'result' => 'lose', 'data' => $msg,
      'user_pick' => $유저글, 'system_pick' => $시스템글,
      'loss' => $총손실, 'streak_after' => 0, 'played_lose_mult' => $loseM,
      'hard_lose_x2' => $하드패배2배,
      'odds_mode' => $확률모드,
      'payback_add' => $페이백적립,
      'giveup_offer' => 0,
    ];
  }
}
