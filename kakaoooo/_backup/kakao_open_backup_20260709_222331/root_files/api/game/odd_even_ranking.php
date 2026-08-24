<?php
/** 타짜 랭킹 (.타짜) — tb_odd_even_profit 집계 테이블 사용 */
require_once __DIR__ . '/odd_even_profit.inc.php';

if (!function_exists('홀짝_타짜_랭킹_문구')) {
  /** @param int $limit 0 이하면 전원, 양수면 상위 N명 (기본 20) */
  function 홀짝_타짜_랭킹_문구($limit = 20) {
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $limit = (int)$limit;

    $rows = 홀짝_타짜_랭킹_행목록($limit);

    $msg = "🎴 타짜 · 홀짝 지갑 순이익\n";
    $msg .= "승·무: 당첨/환급 − 선배팅 · 패: 전액 손실 합산\n";
    $msg .= "━━━━━━\n";

    if (empty($rows)) {
      return $msg . "아직 집계할 기록이 없어요.";
    }

    $인원 = count($rows);
    $msg = "🎴 타짜 · 홀짝 지갑 순이익"
      . ($limit > 0 ? " · 상위 {$limit}명" : " · {$인원}명")
      . "\n승·무: 당첨/환급 − 선배팅 · 패: 전액 손실 합산\n━━━━━━\n";

    foreach ($rows as $i => $row) {
      $순번 = $i + 1;
      $이름 = trim((string)($row['name'] ?? ''));
      $net = (int)($row['total_nyang'] ?? 0);
      if ($net > 0) {
        $액표 = '+' . (function_exists('냥축약표시') ? 냥축약표시($net, $단위표) : number_format($net) . $단위표);
      } elseif ($net < 0) {
        $액표 = '-' . (function_exists('냥축약표시') ? 냥축약표시(-$net, $단위표) : number_format(-$net) . $단위표);
      } else {
        $액표 = function_exists('냥축약표시') ? 냥축약표시(0, $단위표) : (number_format(0) . $단위표);
      }
      $메달 = $순번 === 1 ? '🥇 ' : ($순번 === 2 ? '🥈 ' : ($순번 === 3 ? '🥉 ' : ''));
      $msg .= "{$순번}) {$메달}{$이름} · {$액표}\n";
    }

    return rtrim($msg);
  }
}
