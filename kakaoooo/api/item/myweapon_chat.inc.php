<?php
/**
 * `.내무기` 채팅 명령 — 홍보방(info2)
 * 장착/해제 토글 후 안내. 매칭되면 전송 후 exit. 미해당이면 false.
 */
if (!function_exists('내무기_채팅명령_처리')) {
  function 내무기_채팅명령_처리($status, $두자리닉넴, $정보) {
    if (!preg_match('/^\.\s*내무기\s*$/u', trim((string)$status))) {
      return false;
    }

    global $쿨타임;

    $내무기 = trim($정보['item'] ?? '');
    $내강화 = (int)($정보['enhance'] ?? 0);

    if ($내무기 === '') {
      echo 전송("⚔️ [ {$두자리닉넴} ] 보유 무기 없음\n.강화 로 구매할 수 있어요!");
      exit;
    }
    $시전자_esc = addslashes($두자리닉넴);
    $무기장착 = (int)($정보['mount'] ?? 1);
    $gameDir = dirname(__DIR__) . '/game';
    if (!function_exists('mining_weapon_sync_from_mount')) {
      require_once $gameDir . '/mining_weapon.inc.php';
    }
    if ($무기장착 === 0) {
      db_query("UPDATE tb_member SET mount = 1 WHERE name = '{$시전자_esc}' LIMIT 1");
      mining_weapon_sync_from_mount($두자리닉넴, 1);
      $msg = "⚔️ [ {$두자리닉넴} ] 무기 장착 완료\n{$내무기} +{$내강화}\n\n";
      if ($내강화 >= 10) {
        $msg .= "• 채굴 무기 결합 해제 (.시전 · .보호 가능)\n";
      }
    } else {
      db_query("UPDATE tb_member SET mount = 0 WHERE name = '{$시전자_esc}' LIMIT 1");
      $채굴결합 = mining_weapon_sync_from_mount($두자리닉넴, 0);
      if (!function_exists('mining_pending_live_fmt')) {
        require_once $gameDir . '/mining_config.inc.php';
        require_once $gameDir . '/mining_storage.inc.php';
        require_once $gameDir . '/mining_sync.inc.php';
      }
      $채굴량 = mining_pending_live_amount($두자리닉넴);
      $채굴량fmt = mining_fmt_pending($채굴량);
      $해제멘트 = "⚔️ [ {$두자리닉넴} ] 무기 장착 해제";
      if ($채굴량 + 1e-12 > 0) {
        $해제멘트 .= "\n• 채굴냥: {$채굴량fmt}냥";
      }
      if (defined('MINING_CHAT_CLAIM_MIN') && $채굴량 + 1e-12 >= (float)MINING_CHAT_CLAIM_MIN) {
        $해제멘트 .= "\n• `.수령`으로 본방냥 수령 가능";
      }
      if ($채굴결합 && $내강화 >= 10) {
        $해제멘트 .= "\n• 채굴 무기 결합 ON — 광물 발견 가능";
      }
      echo 전송($해제멘트);
      exit;
    }
    $쿨타임 = isset($쿨타임) ? (int)$쿨타임 : 1;
    $마법무기인가 = function_exists('무기_마법인가') ? 무기_마법인가($정보) : ($내무기 === '🪄마법' || $내무기 === '🪄 마법');
    if ($마법무기인가 && function_exists('마법_시전보호_공용한도_동기화')) {
      마법_시전보호_공용한도_동기화($두자리닉넴, $쿨타임);
    }
    $구간 = 무기_시전구간_적용($두자리닉넴, $쿨타임, false);
    $magic_used = (int)($구간['used'] ?? 0);
    $지금 = time();
    $다음리셋 = (int)($구간['reset_at'] ?? 0);
    $시전한도단독 = function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($내무기, (int)$내강화) : 0;
    $시전한도 = ($마법무기인가 && function_exists('마법_시전보호_공용_무료한도'))
      ? 마법_시전보호_공용_무료한도($내무기, (int)$내강화)
      : $시전한도단독;
    if ($시전한도 > 0) {
      if ($다음리셋 > 0 && $지금 < $다음리셋) {
        $남은분 = (int)ceil(($다음리셋 - $지금) / 60);
        $msg .= $마법무기인가
          ? "⏱ 시전·보호 공용 한도 {$남은분}분 후 초기화 (남은 횟수도 소멸)\n"
          : "⏱ 시전 한도 {$남은분}분 후 초기화 (남은 횟수도 소멸)\n";
      } else {
        $msg .= $마법무기인가
          ? "✅ 시전·보호 공용 한도 초기화됨 (강화×2 다시 사용 가능)\n"
          : "✅ 시전 한도 초기화됨 (강화×2 다시 사용 가능)\n";
      }
      if ($마법무기인가) {
        $msg .= "• 시전·보호 공용: {$magic_used}/{$시전한도} (1시간 · 각 {$시전한도단독} · 합산 {$시전한도})\n";
      } else {
        $msg .= "• 시전: {$magic_used}/{$시전한도} (1시간 · 강화×2)\n";
      }
    } else {
      $msg .= "• 시전: 1강 이상부터 가능\n";
    }
    echo 전송(trim($msg));
    exit;
  }
}
