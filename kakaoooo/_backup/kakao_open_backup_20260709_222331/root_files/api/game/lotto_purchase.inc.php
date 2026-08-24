<?php
/**
 * 로또 수동·자동 구매 공통 처리
 * 포함 전제: config.php 로드됨, $두자리닉넴·$정보·$단위 사용 가능
 */

if (file_exists(__DIR__ . '/lotto_ticket.inc.php')) {
  require_once __DIR__ . '/lotto_ticket.inc.php';
}

if (!function_exists('로또_진행회차')) {
  function 로또_진행회차() {
    $완료회차행 = db_select("
      SELECT IFNULL(MAX(drow), 0) AS max_drow
      FROM tb_game_lotto_result
      WHERE status = 1
    ");
    return (int)($완료회차행['max_drow'] ?? 0) + 1;
  }
}

/**
 * `.로또 추첨` 전 선행 회차 정리 여부 확인
 * @return array{ok:bool, msg?:string, target_drow?:int}
 */
if (!function_exists('로또_추첨_선행검사')) {
  function 로또_추첨_선행검사() {
    $미지급행 = db_select("
      SELECT drow
      FROM tb_game_lotto_result
      WHERE status = 0
      ORDER BY drow ASC
      LIMIT 1
    ");
    if (!empty($미지급행['drow'])) {
      $d = (int)$미지급행['drow'];
      return [
        'ok'  => false,
        'msg' => "❌ {$d}회차 추첨은 완료됐지만 아직 지급이 안 됐어요.\n먼저 `.로또 지급`을 실행해주세요.",
      ];
    }

    $대상회차 = 로또_진행회차();

    $미추첨행 = db_select("
      SELECT MIN(l.drow) AS drow
      FROM tb_game_lotto l
      LEFT JOIN tb_game_lotto_result r ON r.drow = l.drow
      WHERE l.status = 0
        AND r.drow IS NULL
    ");
    $미추첨회차 = (int)($미추첨행['drow'] ?? 0);
    if ($미추첨회차 > 0 && $미추첨회차 < $대상회차) {
      return [
        'ok'  => false,
        'msg' => "❌ {$미추첨회차}회차 추첨이 아직 안 됐어요.\n{$대상회차}회차보다 이전 회차부터 처리해주세요.",
      ];
    }

    $이미추첨 = db_select("SELECT drow FROM tb_game_lotto_result WHERE drow = {$대상회차} LIMIT 1");
    if (!empty($이미추첨['drow'])) {
      return [
        'ok'  => false,
        'msg' => "❌ {$대상회차}회차는 이미 추첨됐어요.",
      ];
    }

    return ['ok' => true, 'target_drow' => $대상회차];
  }
}

if (!function_exists('로또_번호구매_실행')) {
  /**
   * @param array{요청개수:int,isAuto:bool,num1?:int,num2?:int,num3?:int,silent?:bool,useTicket?:bool} $opts
   */
  function 로또_번호구매_실행(array $opts) {
    global $두자리닉넴, $정보, $단위;

    $요청개수 = max(1, (int)($opts['요청개수'] ?? 1));
    $isAutoLotto = !empty($opts['isAuto']);
    $useTicket = !empty($opts['useTicket']);
    $silent = !empty($opts['silent']) || !empty($GLOBALS['LOTTO_PURCHASE_SILENT']);
    $수동_num1 = (int)($opts['num1'] ?? 0);
    $수동_num2 = (int)($opts['num2'] ?? 0);
    $수동_num3 = (int)($opts['num3'] ?? 0);

    $진행회차 = 로또_진행회차();
    $닉_esc = addslashes($두자리닉넴);
    $내idx  = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $내회차구매수행 = db_select("SELECT COUNT(*) AS cnt FROM tb_game_lotto WHERE drow = {$진행회차} AND nick = '{$닉_esc}' AND status = 0");
    $누적구매수    = (int)($내회차구매수행['cnt'] ?? 0);
    $보유냥        = (int)($정보['point'] ?? 0);
    $보유티켓      = function_exists('로또티켓_조회') ? 로또티켓_조회($내idx) : 0;

    $실구매목록 = [];
    $총차감     = 0;
    $총티켓차감 = 0;
    $부족중단   = false;
    $부족필요가 = 0;
    $부족필요티켓 = 1;
    $자숙패널티 = ['횟수' => 0, '게임냥' => 0, '보유냥' => 0, '마지막끝' => ''];

    for ($ii = 0; $ii < $요청개수; $ii++) {
      $이번순번 = $누적구매수 + 1;
      $추가단계 = (int)floor(($이번순번 - 1) / 20);
      $로또가격 = 500000 + ($추가단계 * 500000);

      if ($useTicket) {
        if ($보유티켓 < 1) {
          $부족중단     = true;
          $부족필요티켓 = 1;
          break;
        }
      } elseif ($보유냥 < $로또가격) {
        $부족중단   = true;
        $부족필요가 = $로또가격;
        break;
      }

      if (!$useTicket && function_exists('자숙_로또위반_적용')) {
        $자숙결과 = 자숙_로또위반_적용($두자리닉넴, $단위);
        if (!empty($자숙결과['applied'])) {
          $자숙패널티['횟수'] = (int)$자숙패널티['횟수'] + 1;
          $자숙패널티['게임냥'] = (int)$자숙패널티['게임냥'] + (int)($자숙결과['deduct'] ?? 0);
          if (!empty($자숙결과['new_end'])) {
            $자숙패널티['마지막끝'] = (string)$자숙결과['new_end'];
          }
          $보유행 = db_select("SELECT point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
          $보유냥 = (int)($보유행['point'] ?? 0);
        }
      }

      if ($useTicket) {
        if ($보유티켓 < 1) {
          $부족중단     = true;
          $부족필요티켓 = 1;
          break;
        }
      } elseif ($보유냥 < $로또가격) {
        $부족중단   = true;
        $부족필요가 = $로또가격;
        break;
      }

      if ($isAutoLotto) {
        $후보숫자 = range(1, 45);
        shuffle($후보숫자);
        $선택 = array_slice($후보숫자, 0, 3);
        sort($선택);
        $n1 = (int)$선택[0];
        $n2 = (int)$선택[1];
        $n3 = (int)$선택[2];
      } else {
        $n1 = $수동_num1;
        $n2 = $수동_num2;
        $n3 = $수동_num3;
      }

      db_query("
        INSERT INTO tb_game_lotto
        SET nick = '{$닉_esc}',
            drow = {$진행회차},
            num1 = {$n1},
            num2 = {$n2},
            num3 = {$n3},
            amount = " . ($useTicket ? 0 : $로또가격) . ",
            status = 0,
            regdate = NOW()
      ");

      if ($useTicket) {
        if (!로또티켓_차감($내idx, 1)) {
          $부족중단     = true;
          $부족필요티켓 = 1;
          break;
        }
        $보유티켓 -= 1;
        $총티켓차감 += 1;
      } else {
        db_query("UPDATE tb_member SET point = point - {$로또가격} WHERE idx = {$내idx}");
        $보유냥     -= $로또가격;
        $총차감     += $로또가격;
      }

      $누적구매수 += 1;

      $표시1 = str_pad((string)$n1, 2, '0', STR_PAD_LEFT);
      $표시2 = str_pad((string)$n2, 2, '0', STR_PAD_LEFT);
      $표시3 = str_pad((string)$n3, 2, '0', STR_PAD_LEFT);
      $실구매목록[] = [
        'num' => "{$표시1},{$표시2},{$표시3}",
        'amt' => $useTicket ? 0 : $로또가격,
      ];
    }

    $실제구매수 = count($실구매목록);

    if ($실제구매수 === 0) {
      if ($useTicket) {
        echo 전송("❌ 로또 티켓 부족! 1장 필요 (현재 {$보유티켓}장)");
      } else {
        echo 전송("❌ 보유 {$단위} 부족! 로또 구매는 " . number_format($부족필요가) . "{$단위} 필요 (현재 " . number_format($보유냥) . "{$단위})");
      }
      exit;
    }

    if ($silent) {
      exit;
    }

    $자숙안내 = function_exists('자숙_시전보호_패널티_요약문구')
      ? 자숙_시전보호_패널티_요약문구($자숙패널티, '로또', $단위)
      : '';

    if ($요청개수 === 1) {
      $한건   = $실구매목록[0];
      $분할 = explode(',', $한건['num']);
      $첫줄 = "✅ 로또 {$진행회차}회차 번호 등록 완료: {$분할[0]},{$분할[1]},{$분할[2]}";
      $패딩 = function_exists('채팅_첫줄_뒤_공백') ? 채팅_첫줄_뒤_공백() : "\n";
      if ($useTicket) {
        echo 전송($자숙안내 . $첫줄 . $패딩 . "(회차 누적 {$누적구매수}건째 / 티켓 1장 차감 · 잔여 {$보유티켓}장)");
      } else {
        echo 전송($자숙안내 . $첫줄 . $패딩 . "(회차 누적 {$누적구매수}건째 / 구매금액 " . number_format($한건['amt']) . "{$단위} 차감)");
      }
      exit;
    }

    $패딩 = function_exists('채팅_첫줄_뒤_공백') ? 채팅_첫줄_뒤_공백() : "\n";
    $msg = $자숙안내 . "✅ 로또 {$진행회차}회차 자동 {$실제구매수}개 구매 완료" . $패딩;
    $msg .= "─────────────\n";
    foreach ($실구매목록 as $i => $it) {
      $msg .= ($i + 1) . ") {$it['num']}\n";
    }
    $msg .= "─────────────\n";
    $msg .= "📊 합계\n";
    if ($useTicket) {
      $msg .= "🎟️ 티켓 차감: {$총티켓차감}장 (잔여 {$보유티켓}장)\n";
    } else {
      $msg .= "💸 차감: " . number_format($총차감) . "{$단위}\n";
    }
    $msg .= "📌 회차 누적: {$누적구매수}건";
    if ($부족중단) {
      $잔여요청 = $요청개수 - $실제구매수;
      if ($useTicket) {
        $msg .= "\n\n⚠️ 티켓 부족으로 {$잔여요청}개 구매 중단\n(다음 장 {$부족필요티켓}장 필요 · 현재 {$보유티켓}장)";
      } else {
        $msg .= "\n\n⚠️ 잔액 부족으로 {$잔여요청}개 구매 중단\n(다음 장 " . number_format($부족필요가) . "{$단위} 필요 · 현재 " . number_format($보유냥) . "{$단위})";
      }
    }
    echo 전송($msg);
    exit;
  }
}
