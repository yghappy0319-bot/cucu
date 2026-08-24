<?php
/**
 * 채팅 `.채굴란` 안내 문구
 */

if (!function_exists('mining_chat_help_message')) {
  function mining_chat_help_message(): string {
    if (!function_exists('mining_fmt_pending')) {
      require_once __DIR__ . '/mining_config.inc.php';
    }
    $min_fmt = mining_fmt_pending((float)MINING_CHAT_CLAIM_MIN);
    $msg = "⛏️ 채굴 안내\n\n";
    $msg .= "1️⃣ 채굴 방법\n";
    $msg .= "- 강화 " . (int)MINING_UNLOCK_UPGRADE_ATTEMPTS . "회 후부터 채굴냥이 쌓여요. (성공·실패 모두 카운트)\n";
    $msg .= "- 채굴 게임(숟가락 채굴)에서 오프라인 포함 냥이 쌓여요.\n";
    $msg .= "- +10 이상 무기는 `.내무기` 해제 시 채굴 결합·광물 발견이 가능해요.\n\n";
    $msg .= "2️⃣ 수령\n";
    $msg .= "- 채굴냥 {$min_fmt}냥 이상 `.수령` → 본방냥\n";
    $msg .= "- `.내냥` 으로 현재 채굴량 확인";
    return $msg;
  }
}
