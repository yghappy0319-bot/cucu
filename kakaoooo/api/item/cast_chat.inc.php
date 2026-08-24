<?php
/**
 * `.시전` 채팅 명령 — 홍보방(info2)
 * 매칭되면 전송 후 exit. 미해당이면 false.
 */
if (!function_exists('시전_채팅명령_처리')) {
  function 시전_채팅명령_처리($status, $두자리닉넴, $정보) {
    $시전입력 = trim((string)$status);
    if (!(strpos($시전입력, '.시전') === 0 || strpos($시전입력, '시전!') === 0)) {
      return false;
    }

    global $오늘타수, $쿨타임, $단위;
    if (!isset($쿨타임) || (int)$쿨타임 <= 0) {
      $쿨타임 = 1;
    }

    if (function_exists('게임제한_차단')) {
      게임제한_차단($두자리닉넴);
    }

    $스타일 = trim($정보['style'] ?? '');
    $내무기 = trim($정보['item'] ?? '');
    $내강화 = (int)($정보['enhance'] ?? 0);
    $내타이틀 = trim((string)($정보['title'] ?? ''));
    $내신용 = (int)($정보['credit'] ?? 0);
    $내게임냥 = (int)($정보['point'] ?? 0);
    if ($내무기 === '') {
      echo 전송("⚔️ [ {$두자리닉넴} ] 보유 무기 없음\n.강화 로 구매할 수 있어요!");
      exit;
    }
    if (function_exists('무기_시전전_자동장착')) {
      무기_시전전_자동장착($두자리닉넴, $정보);
    }
    if (function_exists('무기_타입_스키마보장')) {
      무기_타입_스키마보장();
    }
    $시전무기 = function_exists('무기_시전가능인가') ? 무기_시전가능인가($정보) : false;
    $신불자 = ($내타이틀 === '🆘신불자' || (bool)preg_match('/신불자/u', $내타이틀) || ($내신용 === 1 && $내게임냥 < 0));
    if ($시전무기 && $신불자) {
      echo 전송("❌ 신불자는 단소·활·마법 시전이 불가해요.");
      exit;
    }

    $닉_esc = addslashes((string)$두자리닉넴);
    if (!isset($오늘타수) || !is_array($오늘타수)) {
      $오늘타수 = db_select("SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt FROM tb_msg WHERE nickname = '{$닉_esc}' AND DATE(regdate) = CURDATE()");
    }

    // 마법 시전은 타수 제한 없음 (단소/활 등: 오늘 버프 타수 SUM(tasu) 300 미만 시 시전 불가)
    if (!(function_exists('무기_마법인가') ? 무기_마법인가($정보) : ($내무기 === '🪄마법' || $내무기 === '🪄 마법'))) {
      $타수제한 = 300;
      $현재타수 = (int)($오늘타수['cnt'] ?? 0);
      if ($현재타수 < $타수제한) {
        echo 전송("버프 타수 {$타수제한}타 이상만 시전(공격) 가능해요.\n{$두자리닉넴}의 오늘 버프 타수 : {$현재타수}타");
        exit;
      }
    }

    // .시전 / .시전 30(랜덤·연속) / .시전 닉네임 / .시전 닉네임 30(지목·연속)
    // 단소·활 연속 시전 최소 10회 / 마법은 횟수 제한 없음
    $대상닉 = '';
    $시전횟수 = 1;
    $지목횟수지정 = false;
    $횟수지정 = false;
    if (preg_match('/^\.?시전!?\s+(\S+)\s+(\d+)\s*$/u', $시전입력, $m)) {
      $대상닉 = trim($m[1]);
      $시전횟수 = max(1, (int)$m[2]);
      $지목횟수지정 = true;
      $횟수지정 = true;
    } elseif (preg_match('/^\.?시전!?\s+(\d+)\s*$/u', $시전입력, $m)) {
      $시전횟수 = max(1, (int)$m[1]);
      $횟수지정 = true;
    } elseif (preg_match('/^\.?시전!?\s+(\S+)/u', $시전입력, $m)) {
      $대상닉 = trim($m[1]);
    }
    // 단소·활 지정 시전 잠시 중단 — false로 바꾸면 .시전 닉 / .시전 닉 10 복구
    $단소활_지정시전_중단 = true;
    $단소활무기 = function_exists('무기_단소활인가') ? 무기_단소활인가($정보) : false;
    if ($단소활_지정시전_중단 && $단소활무기 && $대상닉 !== '') {
      echo 전송("❌ 지금은 단소·활 지정 시전이 잠시 중단됐어요.\n`.시전` · `.시전 10` 처럼 랜덤 시전만 가능해요.");
      exit;
    }
    $연속시전최소적용 = function_exists('무기_단소활인가') ? 무기_단소활인가($정보) : false;
    if ($횟수지정 && $연속시전최소적용 && $시전횟수 < 10) {
      echo 전송("❌ 연속 시전은 최소 10회 이상 입력해주세요.\n예) .시전 10");
      exit;
    }
    if ($횟수지정 && (function_exists('무기_시전가능인가') ? 무기_시전가능인가($정보) : false)) {
      $배치검증 = 무기_시전_한도외배치_검증($내무기, $내강화, $시전횟수, $두자리닉넴, isset($쿨타임) ? (int)$쿨타임 : 1, '시전');
      if (empty($배치검증['ok'])) {
        echo 전송($배치검증['msg'] ?? '❌ 연속 시전 횟수를 확인해주세요.');
        exit;
      }
    }
    if ($대상닉 !== '') {
      $대상정보 = db_select("SELECT idx FROM tb_member WHERE name = '".addslashes($대상닉)."' LIMIT 1");
      if (empty($대상정보['idx'])) {
        echo 전송("❌ {$대상닉} 회원을 찾을 수 없어요.");
        exit;
      }
    }

    $itemDir = __DIR__;
    if (function_exists('무기_단소인가') ? 무기_단소인가($정보) : false) {
      include $itemDir . '/danso.php';
      exit;
    }

    if (function_exists('무기_활인가') ? 무기_활인가($정보) : ($내무기 === '🏹활' || $내무기 === '🏹 활')) {
      include $itemDir . '/bow.php';
      exit;
    }

    if (function_exists('무기_마법인가') ? 무기_마법인가($정보) : ($내무기 === '🪄마법' || $내무기 === '🪄 마법')) {
      include $itemDir . '/magic.php';
      exit;
    }
    echo 전송("⚔️ [ {$두자리닉넴} ] {$내무기} +{$내강화}\n(시전은 단소/활/마법만 가능해요)");
    exit;
  }
}
