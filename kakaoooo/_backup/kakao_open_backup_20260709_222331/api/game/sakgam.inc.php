<?php
/**
 * .삭감 — 대상 닉 게임냥(point) N% 삭감 (관리자, info1·info3)
 */

if (!function_exists('삭감_명령_처리')) {
  function 삭감_명령_처리($status, $두자리닉넴, $nick, array $관리자) {
    global $단위;

    $호출닉 = function_exists('getTwoCharNick') ? getTwoCharNick((string)$nick) : '';
    $관리자여부 = in_array($두자리닉넴, $관리자, true)
      || ($호출닉 !== '' && in_array($호출닉, $관리자, true));
    if (!$관리자여부) {
      echo 전송("🔒");
      exit;
    }

    if (!preg_match('/\.삭감\s+([가-힣A-Za-z0-9_]+)\s+(\d+)\s*$/u', trim((string)$status), $m)) {
      echo 전송("❌ 사용법: .삭감 닉네임 퍼센트 (예: .삭감 길동 80)");
      exit;
    }

    $대상닉 = trim($m[1]);
    $퍼센트 = (int)$m[2];
    if ($퍼센트 < 1 || $퍼센트 > 100) {
      echo 전송("❌ 퍼센트는 1~100 사이로 입력해주세요. (예: .삭감 우주 80)");
      exit;
    }

    $대상닉_esc = addslashes($대상닉);
    $대상 = db_select("SELECT idx, name, point FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
    if (empty($대상['idx'])) {
      echo 전송("❌ 존재하지 않는 사용자예요.");
      exit;
    }

    $보유냥 = (int)$대상['point'];
    $삭감액 = (int)floor($보유냥 * $퍼센트 / 100);
    $금액표시 = function_exists('냥축약표시')
      ? static function ($n) { return number_format($n) . "냥"; }
      : static function ($n) use ($단위) { return number_format((int)$n) . $단위; };

    if ($삭감액 <= 0) {
      echo 전송("✅ {$대상닉} 보유 냥이 없거나 삭감할 만큼 없어요. (보유: " . $금액표시($보유냥) . ")");
      exit;
    }

    db_query("UPDATE tb_member SET point = point - {$삭감액} WHERE name = '{$대상닉_esc}'");
    지급로그('삭감', $두자리닉넴, $대상닉, 0, $삭감액);
    echo 전송(
      "✅ {$대상닉} 냥 {$퍼센트}% 삭감 완료.\n"
      . "보유: " . $금액표시($보유냥) . " → " . $금액표시($보유냥 - $삭감액)
      . " (-" . $금액표시($삭감액) . ")"
    );
    exit;
  }
}
