<?php
/**
 * 색표 선점 API (가방 코드 인증)
 * POST: color_num
 *       newbie_mode=1 → 색표 만석 시 신입색(#1) + 신입색순번 등록
 * 닉은 회원 코드 기준. 신입(색확정=0) + 신입담당 있으면 → 신입/담당 보상 지급 + 본방 알림 + 담당 자동 해제
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
include_once __DIR__ . '/_wallet_preauth.php';
include_once __DIR__ . '/_color_market_lib.php';

header('Content-Type: application/json; charset=utf-8');

function cl_json($arr) {
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  cl_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$회원 = function_exists('cm_auth') ? cm_auth() : null;
if (empty($회원['code']) || empty($회원['nick'])) {
  cl_json(['ok' => false, 'msg' => '가방에서 접속한 뒤 선점해 주세요.']);
}

$nick = trim((string)$회원['nick']);
$color_num = (int)($_POST['color_num'] ?? 0);
$newbie_mode = ((int)($_POST['newbie_mode'] ?? 0) === 1);
$NEWBIE_COLOR_NUM = 1;

if ($nick === '') {
  cl_json(['ok' => false, 'msg' => '닉네임이 없어요.']);
}
if ($color_num < 1 || $color_num > 45) {
  cl_json(['ok' => false, 'msg' => '색번호는 1~45만 가능해요.']);
}
if (!$newbie_mode && ($color_num === 1 || $color_num === 2)) {
  cl_json(['ok' => false, 'msg' => '이 색은 선점할 수 없어요.']);
}

if (function_exists('신입색순번_컬럼보장')) {
  신입색순번_컬럼보장();
}

$닉_esc = addslashes($nick);
$회원 = db_select("SELECT idx, name, num, IFNULL(`신입색순번`, 0) AS 신입색순번 FROM tb_member WHERE name = '{$닉_esc}' AND status != 1 LIMIT 1");
if (empty($회원['idx'])) {
  // 컬럼 없을 때 대비 재조회
  $회원 = db_select("SELECT idx, name, num FROM tb_member WHERE name = '{$닉_esc}' AND status != 1 LIMIT 1");
}
if (empty($회원['idx'])) {
  cl_json(['ok' => false, 'msg' => "[{$nick}] 회원이 없어요. 먼저 공질 등록해줘!"]);
}

// 이미 색표(1~45)에 등록된 닉은 추가 선점 불가
$기존색 = (int)($회원['num'] ?? 0);
if ($기존색 >= 1 && $기존색 <= 45) {
  cl_json(['ok' => false, 'msg' => "[{$nick}] 님은 이미 #{$기존색} 색표에 등록되어 있어요. 추가 선점은 안 돼요!"]);
}

$기존순번 = (int)($회원['신입색순번'] ?? 0);
if ($기존순번 > 0) {
  cl_json(['ok' => false, 'msg' => "[{$nick}] 님은 이미 신입색 순번 #{$기존순번} 으로 등록되어 있어요."]);
}

$신입색순번 = 0;

if ($newbie_mode) {
  if ($color_num !== $NEWBIE_COLOR_NUM) {
    cl_json(['ok' => false, 'msg' => "신입색은 #{$NEWBIE_COLOR_NUM} 만 선택할 수 있어요."]);
  }

  // 서버에서 만석 여부 재확인 (2~45, 1번 제외 44칸)
  $lockMap = [];
  $ownerMap = [];
  $lockRs = @db_query("SELECT color_num, owner_nick, trade_lock FROM tb_color_market");
  if ($lockRs) {
    while ($lockRow = db_fetch($lockRs)) {
      $ln = (int)($lockRow['color_num'] ?? 0);
      if ($ln < 1 || $ln > 45) continue;
      $lockMap[$ln] = ((int)($lockRow['trade_lock'] ?? 0) === 1);
      $ownerMap[$ln] = trim((string)($lockRow['owner_nick'] ?? ''));
    }
  }
  $memberCountByNum = [];
  $mcRs = @db_query("SELECT num, COUNT(*) AS cnt FROM tb_member WHERE status != 1 AND num BETWEEN 1 AND 45 GROUP BY num");
  if ($mcRs) {
    while ($mcRow = db_fetch($mcRs)) {
      $mn = (int)($mcRow['num'] ?? 0);
      if ($mn < 1 || $mn > 45) continue;
      $memberCountByNum[$mn] = (int)($mcRow['cnt'] ?? 0);
    }
  }
  $emptyOtherCount = 0;
  for ($n = 2; $n <= 45; $n++) {
    $hasMember = !empty($memberCountByNum[$n]);
    $hasOwner = trim((string)($ownerMap[$n] ?? '')) !== '';
    if ($hasMember || $hasOwner) {
      continue;
    }
    $emptyOtherCount++;
  }
  if ($emptyOtherCount > 0) {
    cl_json(['ok' => false, 'msg' => '아직 빈색이 있어요. 빈색을 선점해줘!']);
  }

  $신입색순번 = function_exists('신입색순번_다음번호') ? 신입색순번_다음번호() : 1;
} else {
  $잠김 = false;
  $lockRs = @db_select("SELECT trade_lock FROM tb_color_market WHERE color_num = {$color_num} LIMIT 1");
  if ($lockRs && (int)($lockRs['trade_lock'] ?? 0) === 1) {
    $잠김 = true;
  }
  if ($잠김) {
    cl_json(['ok' => false, 'msg' => '구매불가 색은 선점할 수 없어요.']);
  }

  $점유 = db_select("SELECT COUNT(*) AS cnt FROM tb_member WHERE num = {$color_num} AND status != 1 AND name != '{$닉_esc}'");
  if ((int)($점유['cnt'] ?? 0) > 0) {
    cl_json(['ok' => false, 'msg' => '이미 누군가 쓰고 있는 색이에요. 빈색을 골라줘!']);
  }
}

// 장터 소유자 맞추기 (지급 전에 색 점유 반영) — 신입색 공유는 소유자 뺏지 않음
if (!$newbie_mode && is_file(__DIR__ . '/_color_market_lib.php')) {
  include_once __DIR__ . '/_color_market_lib.php';
  if (function_exists('cm_테이블보장')) {
    cm_테이블보장();
  }
}

$지급결과 = null;
if (function_exists('신입색변_완료처리')) {
  $지급결과 = 신입색변_완료처리($nick, $color_num, [
    'alarm' => true,
    'require_damdang' => false,
  ]);
  if (empty($지급결과['ok'])) {
    cl_json(['ok' => false, 'msg' => $지급결과['error'] ?? '선점에 실패했어요.']);
  }
} else {
  db_query("UPDATE tb_member SET num = {$color_num}, couple = 0 WHERE name = '{$닉_esc}' AND status != 1 LIMIT 1");
}

if ($newbie_mode && $신입색순번 > 0) {
  if (function_exists('신입색순번_컬럼보장')) {
    신입색순번_컬럼보장();
  }
  @db_query("UPDATE tb_member SET `신입색순번` = {$신입색순번} WHERE name = '{$닉_esc}' AND status != 1 LIMIT 1");
}

if (!$newbie_mode) {
  @db_query("UPDATE tb_color_market
    SET owner_nick = '{$닉_esc}', price = 0, listed = 0, updated_at = NOW()
    WHERE color_num = {$color_num} AND owner_nick = '' AND listed = 0 AND trade_lock = 0
    LIMIT 1");
}

$paid = !empty($지급결과['paid']);
if ($newbie_mode) {
  $msg = $paid
    ? "신입색 #{$color_num} 에 [{$nick}] 등록 + 신입·담당 보상 지급! (공창 알림으로 안내돼요)\n공창에서 #{$color_num} 색으로 프로필 변경해줘!"
    : "신입색 #{$color_num} 에 [{$nick}] 등록했어요!\n공창에서 #{$color_num} 색으로 프로필 변경해줘!";
} else {
  $msg = $paid
    ? "#{$color_num} 색 선점 + 신입·담당 보상 지급! (공창 알림으로 안내돼요)"
    : "#{$color_num} 색을 선점했어요!";
}

cl_json([
  'ok'  => true,
  'msg' => $msg,
  'color_num' => $color_num,
  'nick' => $nick,
  'paid' => $paid,
  'newbie_mode' => $newbie_mode,
  '신입색순번' => $신입색순번,
]);
