<?php
/**
 * 채팅 `.채굴란` 안내 문구
 */

if (!function_exists('mining_chat_help_message')) {
  function mining_chat_help_message(): string {
    if (!function_exists('mining_fmt_pending')) {
      require_once __DIR__ . '/mining_config.inc.php';
    }
    if (!function_exists('강화비용_유효시총_채굴표시')) {
      $cfg = __DIR__ . '/../config.php';
      if (is_file($cfg)) {
        @include_once $cfg;
      }
    }
    $min_fmt = mining_fmt_pending((float)MINING_CHAT_CLAIM_MIN);
    $msg = "⛏️ 채굴 안내\n\n";
    $msg .= "1️⃣ 채굴 방법\n";
    $msg .= "- 강화 " . (int)MINING_UNLOCK_UPGRADE_ATTEMPTS . "회 후부터 채굴냥이 쌓여요. (성공·실패 모두 카운트)\n";
    $msg .= "- 채굴 게임(숟가락 채굴)에서 오프라인 포함 냥이 쌓여요.\n";
    $msg .= "- 오늘 생타에 따라 채굴 배율: 100미만 ×0.1 · 100+ ×0.3 · 300+ ×0.5 · 500+ ×1 · 800+ ×1.3 · 1100+ ×1.5\n";
    $msg .= "- 오늘 생타 강화비 할인: 300+ 1% · 500+ 5% · 1000+ 10% · 1500+ 15% · 2000+ 20%\n";
    $msg .= "- 채굴장비 강화비: 시총 30% 하드캡 적용(무기와 동일) · 50% 소멸 · 25% 금고 · 25% 로또\n";
    if (function_exists('강화비용_유효시총_채굴표시') && function_exists('강화비용_시총표시')) {
      $msg .= "  · 전체시총 " . 강화비용_시총표시() . " / 채굴유효시총 " . 강화비용_유효시총_채굴표시('냥') . "\n";
    }
    $msg .= "- +10 이상 무기는 `.내무기` 해제 시 채굴 결합·광물 발견이 가능해요.\n\n";
    $msg .= "2️⃣ 수령·수리\n";
    $msg .= "- 채굴냥 {$min_fmt}냥 이상 `.수령` → 본방냥\n";
    $msg .= "- `.내냥` 으로 현재 채굴량 확인\n";
    $msg .= "- 장비별 최대 내구 다름 · 10% 이하면 채굴 정지 · `.채굴수리` (+100 · 최대 1000 · 내구1당 본냥 0.1)\n";
    $msg .= "- `.퇴근` 중에는 채굴·광물 정지 · `.출근` 후 재개\n";
    $msg .= "- 강화 성공 시 내구도 풀 회복 · 은총 중 마모 2배\n";
    $msg .= "- 은총조각 10개당 `.은총교환` → 은총 1개 (홍보방·관리방)\n\n";
    $msg .= "3️⃣ 주의\n";
    $msg .= "- 자숙 중 채굴 장비 강화 시 자숙 +5시간 (1회 강화·연속 강화 모두 1회 적용)";
    return $msg;
  }
}
