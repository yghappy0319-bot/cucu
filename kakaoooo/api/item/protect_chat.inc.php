<?php
/**
 * `.보호` / `.보호 닉` / `.보호 닉 N` 채팅 명령 — 홍보방(info2)
 * 매칭되면 전송 후 exit. 미해당이면 false.
 */
if (!function_exists('보호_채팅명령_처리')) {
  function 보호_채팅명령_처리($status, $두자리닉넴, $정보) {
    $보호입력 = trim((string)$status);
    if (function_exists('status_정규화')) {
      $보호입력 = status_정규화($보호입력);
    }
    $보호입력 = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Cf}]+/u', '', (string)$보호입력);
    $보호입력 = trim((string)$보호입력);
    $보호입력 = preg_replace('/^．/u', '.', $보호입력);
    if (!preg_match('/^\.\s*보호(?:\s|$)/u', $보호입력)) {
      return false;
    }
    $보호입력 = preg_replace('/^\.\s*보호/u', '.보호', $보호입력);

    global $쿨타임, $단위;
    if (!isset($쿨타임) || (int)$쿨타임 <= 0) {
      $쿨타임 = 1;
    }

    $두자리닉넴 = trim((string)$두자리닉넴);
    if (!is_array($정보)) {
      $정보 = [];
    }
    if ($두자리닉넴 !== '' && function_exists('회원정보_조회')) {
      $최신 = 회원정보_조회($두자리닉넴);
      if (is_array($최신) && trim((string)($최신['name'] ?? '')) !== '') {
        $정보 = $최신;
      }
    }
    if (trim((string)($정보['name'] ?? '')) === '') {
      echo 전송('❌ 등록된 회원만 `.보호`를 사용할 수 있어요.');
      exit;
    }
    if ($두자리닉넴 === '') {
      $두자리닉넴 = trim((string)$정보['name']);
    }

    if (function_exists('무기_타입_스키마보장')) {
      무기_타입_스키마보장();
    }

    $채굴무기차단 = function_exists('채굴_무기장착_차단문구')
      ? 채굴_무기장착_차단문구($두자리닉넴)
      : '';
    if ($채굴무기차단 !== '') {
      echo 전송($채굴무기차단);
      exit;
    }

    $내무기 = trim((string)($정보['item'] ?? ''));
    $내강화 = (int)($정보['enhance'] ?? 0);

    $요청보호횟수 = 1;
    $대상닉 = '';
    if (preg_match('/^\.보호\s*$/u', $보호입력) || $보호입력 === '.보호') {
      $대상닉 = '';
    } elseif (preg_match('/^\.보호\s+(\S+)\s+(\d+)/u', $보호입력, $m)) {
      $대상닉 = trim($m[1]);
      $요청보호횟수 = (int)$m[2];
      if ($요청보호횟수 <= 0) {
        echo 전송("❌ 보호 횟수는 1 이상 숫자로 입력해주세요.\n예) .보호 진우 30");
        exit;
      }
    } elseif (preg_match('/^\.보호\s+(\S+)/u', $보호입력, $m)) {
      $대상닉 = trim($m[1]);
    } else {
      echo 전송("❌ 사용법: .보호 (랜덤) 또는 .보호 (닉네임) 또는 .보호 (닉네임) (한도횟수)\n본인 닉을 넣으면 자기 보호 가능\n한도 1회당 강화~2배 (크리 40% 시 ~3배)\n마법 1강 이상 · 시전+보호 한도 합산 공용 (1시간 · 강화×2의 2배)");
      exit;
    }

    try {
      include __DIR__ . '/protect.php';
    } catch (Throwable $e) {
      echo 전송('❌ `.보호` 처리 중 오류가 났어요. 잠시 후 다시 시도해주세요.');
    }
    exit;
  }
}
