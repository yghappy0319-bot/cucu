<?php
/**
 * `.우리방` — 시세·보상 안내 (info2 등에서 include)
 */

if (strpos((string)$status, '.우리방') === false) {
  return;
}

if (!isset($단위)) {
  include_once __DIR__ . '/config.php';
}

$전체보유 = (float)전체보유newpoint합계();
$전체게임냥 = function_exists('시세기준_게임냥_문자열')
  ? 시세기준_게임냥_문자열()
  : (function_exists('시세기준_게임냥') ? 시세기준_게임냥() : '0');

$msg = "📊 시세 기준 (" . 시세기준_갱신시각_표시() . ")\n";
$msg .= "✅ 본방 {$단위} : " . newpoint표시($전체보유) . "\n";
$msg .= "✅ 게임 {$단위} : " . 랭킹_게임냥표시($전체게임냥, '') . "\n";
if (!function_exists('스왑_1보유냥당_게임냥')) {
  require_once __DIR__ . '/game/swap.inc.php';
}
$스왑1냥 = function_exists('스왑_1보유냥당_게임냥_문자열')
  ? 스왑_1보유냥당_게임냥_문자열()
  : 스왑_1보유냥당_게임냥();
$스왑표시가능 = function_exists('bccomp')
  ? bccomp((string)$스왑1냥, '0', 0) > 0
  : ((float)$스왑1냥 > 0);
if ($스왑표시가능) {
  $msg .= "💱 1 보유냥 스왑 시 게임냥 : " . 랭킹_게임냥표시($스왑1냥, '') . "\n";
}

$msg .= "\n* 커피쏘기 주의사항 *\n나에게 선물하기로 구매 후\n마켓에 업로드";

$만원당전체지급 = (int)floor($전체보유 * 0.025); // 전체 보유냥(newpoint) 2.5%
$msg .= "\n기프티콘 1만원 당 : " . newpoint표시($만원당전체지급) . "{$단위} 선매입!";

$신입지원금 = (int)floor($전체보유 * 0.02); // 전체의 2%
$신입담당보상 = (int)floor($전체보유 * 0.005); // 전체의 0.5%
$생일자보상 = (int)floor($전체보유 * 0.10); // 전체 본방냥 10%

$msg .= "\n\n📋 보상 (현재 기준)";
$msg .= "\n신입 지원금 2% : " . newpoint표시($신입지원금) . "{$단위}";
$msg .= "\n신입 담당 보상 0.5% : " . newpoint표시($신입담당보상) . "{$단위}";
$msg .= "\n생일자 : " . newpoint표시($생일자보상) . "{$단위}";

echo 전송($msg);
exit;
