<?php
/**
 * `.수리` / `.수리 N` 채팅 명령 — 홍보방(info2)
 * 무기 내구도 수리. 매칭되면 전송 후 exit. 미해당이면 false.
 */
if (!function_exists('무기수리_채팅명령_처리')) {
  function 무기수리_채팅명령_처리($status, $두자리닉넴, $정보) {
    $입력문 = trim((string)$status);
    if (!preg_match('/^\.수리(?:\s+(\d+))?\s*$/u', $입력문, $m)) {
      return false;
    }

    global $단위;

    // 무기 내구도 수리: +20=0.0000005%, +10~19=0.0000001% (내구도 1당 전체 게임냥 비율)
    // 비용·보유냥은 문자열/bcmath — (int) 캐스팅 시 ~922경에서 잘려 한 번에 수십~수백만 수리되던 버그 방지
    $닉_esc = addslashes($두자리닉넴);
    $내무기 = trim($정보['item'] ?? '');
    $내강화 = (int)($정보['enhance'] ?? 0);

    if ($내무기 === '') {
      echo 전송("❌ 장착 중인 무기가 없어요.\n.강화 로 먼저 무기를 구매해주세요.");
      exit;
    }

    if ($내강화 < 10) {
      echo 전송("❌ +10 이상 무기부터 수리 가능합니다.\n현재 무기: {$내무기} +{$내강화}");
      exit;
    }

    $요청수리 = null;
    if (isset($m[1]) && $m[1] !== '') {
      $요청수리 = (int)$m[1];
      if ($요청수리 <= 0) {
        echo 전송("❌ 수리 횟수는 1 이상으로 입력해주세요.\n예) .수리 5");
        exit;
      }
    }

    $최대내구도 = 무기_최대내구도($내무기, $내강화);
    if ($최대내구도 <= 0) {
      echo 전송("❌ 이 무기는 수리 대상이 아닙니다.\n현재 무기: {$내무기} +{$내강화}");
      exit;
    }

    $멤버행 = db_select("
      SELECT durability,
             CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point
      FROM tb_member
      WHERE name = '{$닉_esc}'
      LIMIT 1
    ");
    if (!$멤버행) {
      echo 전송("❌ 회원 정보를 찾을 수 없습니다.");
      exit;
    }
    $durability_raw = $멤버행['durability'] ?? null;
    $현재내구도 = ($durability_raw !== null) ? (int)$durability_raw : 0;
    if ($현재내구도 > $최대내구도) {
      echo 전송(
        "❌ 현재 {$내무기} +{$내강화} 내구도 {$현재내구도}"
      );
      exit;
    }

    if ($현재내구도 >= $최대내구도) {
      echo 전송("🔧 {$내무기} +{$내강화} 수리\n이미 최대 내구도 ({$최대내구도}) 상태입니다.");
      exit;
    }

    $충전가능 = $최대내구도 - $현재내구도;
    $단가 = function_exists('수리_회당비용') ? 수리_회당비용($내강화) : '30000';
    $단가 = function_exists('냥_정수문자열') ? 냥_정수문자열($단가) : (ltrim(preg_replace('/[^\d]/', '', (string)$단가), '0') ?: '0');
    $보유냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($멤버행['point'] ?? '0')
      : (ltrim(preg_replace('/[^\d]/', '', (string)($멤버행['point'] ?? '0')), '0') ?: '0');

    $최대충전가능개수 = 0;
    if ($단가 !== '0' && $단가 !== '') {
      if (function_exists('bcdiv') && function_exists('bccomp') && bccomp($단가, '0', 0) > 0) {
        $최대충전가능개수 = (int)bcdiv($보유냥, $단가, 0);
      } elseif (function_exists('냥_나눗셈내림')) {
        $최대충전가능개수 = (int)냥_나눗셈내림($보유냥, $단가);
      } else {
        $최대충전가능개수 = (int)intdiv((int)min((float)$보유냥, (float)PHP_INT_MAX), max(1, (int)min((float)$단가, (float)PHP_INT_MAX)));
      }
    }
    if ($최대충전가능개수 <= 0) {
      echo 전송("❌ 수리에 필요한 냥이 부족해요.\n내구도 1당 " . 수리_냥문구($단가, $단위) . " 필요합니다.\n현재 보유: " . 수리_냥문구($보유냥, $단위));
      exit;
    }

    if ($요청수리 !== null) {
      if ($요청수리 > $충전가능) {
        echo 전송("❌ 요청한 수리 횟수가 너무 많아요.\n현재 최대 수리 가능: {$충전가능}회");
        exit;
      }
      if ($요청수리 > $최대충전가능개수) {
        $필요냥 = function_exists('냥_금액_문자열곱')
          ? 냥_금액_문자열곱((string)$요청수리, $단가)
          : (function_exists('bcmul') ? bcmul((string)$요청수리, $단가, 0) : (string)($요청수리 * (int)$단가));
        echo 전송(
          "❌ 수리에 필요한 냥이 부족해요.\n".
          "요청 수리: {$요청수리}회 (필요 " . 수리_냥문구($필요냥, $단위) . ")\n".
          "현재 보유: " . 수리_냥문구($보유냥, $단위)
        );
        exit;
      }
      $실제충전 = $요청수리;
    } else {
      $실제충전 = min($충전가능, $최대충전가능개수);
    }

    $총비용 = function_exists('냥_금액_문자열곱')
      ? 냥_금액_문자열곱((string)$실제충전, $단가)
      : (function_exists('bcmul') ? bcmul((string)$실제충전, $단가, 0) : (string)((int)$실제충전 * (int)$단가));

    if (function_exists('bcdiv') && function_exists('bcsub')) {
      $총수수료 = bcdiv(bcmul($총비용, '10', 0), '100', 0);
      $금고적립 = bcdiv(bcmul($총수수료, '70', 0), '100', 0);
      $로또적립 = bcsub($총수수료, $금고적립, 0);
    } else {
      $총수수료 = function_exists('냥_나눗셈내림')
        ? 냥_나눗셈내림(냥_금액_문자열곱($총비용, '10'), 100)
        : (string)intdiv((int)$총비용 * 10, 100);
      $금고적립 = function_exists('냥_나눗셈내림')
        ? 냥_나눗셈내림(냥_금액_문자열곱($총수수료, '70'), 100)
        : (string)intdiv((int)$총수수료 * 70, 100);
      $로또적립 = function_exists('냥_금액_문자열차감')
        ? 냥_금액_문자열차감($총수수료, $금고적립)
        : (string)max(0, (int)$총수수료 - (int)$금고적립);
    }

    $총비용_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($총비용) : preg_replace('/[^\d]/', '', $총비용);
    $금고_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($금고적립) : preg_replace('/[^\d]/', '', $금고적립);
    $로또_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($로또적립) : preg_replace('/[^\d]/', '', $로또적립);

    db_query("UPDATE tb_member SET durability = LEAST(IFNULL(durability,0) + {$실제충전}, {$최대내구도}), point = point - {$총비용_sql} WHERE name = '{$닉_esc}'");
    if ($금고_sql !== '' && $금고_sql !== '0' && (function_exists('bccomp') ? bccomp($금고적립, '0', 0) > 0 : (int)$금고적립 > 0)) {
      db_query("UPDATE config SET tax = tax + {$금고_sql}");
    }
    if ($로또_sql !== '' && $로또_sql !== '0' && (function_exists('bccomp') ? bccomp($로또적립, '0', 0) > 0 : (int)$로또적립 > 0)) {
      $gameDir = dirname(__DIR__) . '/game';
      if (is_file($gameDir . '/lotto_amount.inc.php')) {
        require_once $gameDir . '/lotto_amount.inc.php';
      }
      if (function_exists('로또누적_가산')) {
        로또누적_가산((string)$로또적립);
      } else {
        require_once $gameDir . '/odd_even_guards.php';
        if (function_exists('홀짝_로또수수료_적립')) {
          홀짝_로또수수료_적립($로또적립, '무기수리');
        }
      }
    }
    if (function_exists('지급로그')) {
      $수수료합 = function_exists('냥_금액_문자열합')
        ? 냥_금액_문자열합($금고적립, $로또적립)
        : (function_exists('bcadd') ? bcadd($금고적립, $로또적립, 0) : (string)((int)$금고적립 + (int)$로또적립));
      지급로그('무기수리', $두자리닉넴, '', $수수료합, $총비용);
    }

    $새내구도 = $현재내구도 + $실제충전;
    if ($새내구도 > $최대내구도) {
      $새내구도 = $최대내구도;
    }

    $완료문구 =
      "🔧 {$두자리닉넴} 수리 완료!\n".
      "🪄{$내무기} +{$내강화}\n".
      "내구도 {$현재내구도} → {$새내구도} / {$최대내구도}\n".
      수리_냥문구($총비용, $단위) . " 차감";
    if ($요청수리 === null && $새내구도 < $최대내구도) {
      $완료문구 .= "\n(냥 부족으로 일부만 수리 · 남은 " . ($최대내구도 - $새내구도) . ")";
    }
    echo 전송($완료문구);
    exit;
  }
}
