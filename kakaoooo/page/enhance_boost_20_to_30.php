<?php
/**
 * 일회성: 현재 +20 → +30 상향 (본방 알림 포함)
 * URL: /page/enhance_boost_20_to_30.php?code=XXXX  (민호)
 * 자동 실행은 본방 폴링(본방알림_큐_응답_시도)에서도 1회만 수행됨.
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
include_once __DIR__ . '/_item_stock_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$대상 = [];

$marker = defined('강화_일회보정_20to30_마커') ? 강화_일회보정_20to30_마커 : '#boost:20to30';
$marker_esc = addslashes($marker);
$완료 = db_select("SELECT idx, msg, regdate FROM tb_lotto_info WHERE item = '{$marker_esc}' LIMIT 1");

if ($준호) {
    $rs = db_query("
        SELECT name, item, enhance
        FROM tb_member
        WHERE CAST(IFNULL(enhance, 0) AS UNSIGNED) = 20
          AND TRIM(COALESCE(item, '')) != ''
        ORDER BY item ASC, name ASC
    ");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $대상[] = $row;
        }
    }
}

if ($준호 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
    if (!empty($완료['idx'])) {
        $결과메시지 = '이미 보정 완료된 상태입니다. (재실행 불가 · 이후 +20 달성자 자동상향 없음)';
        $결과종류 = 'err';
    } else {
        $r = 강화_일회보정_20to30_시도();
        if (!empty($r['ran'])) {
            $결과메시지 = (string)($r['msg'] ?? '완료') . "\n(상향 " . (int)($r['boosted'] ?? 0) . '명 · 본방 알림 큐 등록됨)';
            $결과종류 = 'ok';
            $완료 = db_select("SELECT idx, msg, regdate FROM tb_lotto_info WHERE item = '{$marker_esc}' LIMIT 1");
            $대상 = [];
        } else {
            $결과메시지 = '실행되지 않았어요. 이미 완료됐거나 다른 요청이 처리 중일 수 있습니다.';
            $결과종류 = 'err';
        }
    }
}

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>+20→+30 일회 보정</title>
<style>
body { font-family: sans-serif; background:#141820; color:#e8e4dc; margin:0; padding:20px; }
.wrap { max-width:640px; margin:0 auto; }
.card { background:rgba(0,0,0,.28); border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:14px; margin:12px 0; }
.ok { color:#6cbc6a; white-space:pre-wrap; }
.err { color:#d16b6b; white-space:pre-wrap; }
button { background:#c9a96e; border:0; padding:10px 16px; border-radius:8px; font-weight:700; cursor:pointer; }
li { margin:4px 0; }
.muted { color:#9a9388; font-size:.9rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>+20 → +30 일회 보정</h1>
  <p class="muted">당시 +20 보유자만 상향 · 완료 마커 후 재실행/자동상향 없음 · 본방 폴링에서도 1회 자동 실행</p>

  <? if (!$로그인) { ?>
    <div class="card err">인증 코드가 필요해요. (?code=)</div>
  <? } elseif (!$준호) { ?>
    <div class="card err">민호만 실행할 수 있어요. (현재: <?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?>)</div>
  <? } else { ?>

    <? if ($결과메시지 !== '') { ?>
      <div class="card <?= $결과종류 === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8') ?></div>
    <? } ?>

    <div class="card">
      <strong>상태:</strong>
      <? if (!empty($완료['idx'])) { ?>
        완료됨 · <?= htmlspecialchars((string)($완료['regdate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <div class="muted"><?= htmlspecialchars((string)($완료['msg'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      <? } else { ?>
        아직 미실행 (본방 봇 폴링 또는 아래 버튼으로 1회 실행)
      <? } ?>
    </div>

    <div class="card">
      <strong>현재 +20 대상 <?= count($대상) ?>명</strong>
      <? if (empty($대상)) { ?>
        <p class="muted">없음</p>
      <? } else { ?>
        <ul>
          <? foreach ($대상 as $r) { ?>
            <li><?= htmlspecialchars(trim($r['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              <?= htmlspecialchars(trim($r['item'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              +<?= (int)($r['enhance'] ?? 0) ?></li>
          <? } ?>
        </ul>
      <? } ?>
    </div>

    <? if (empty($완료['idx'])) { ?>
      <form method="post" action="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('+20 전원 +30 상향하고 본방 알림을 넣을까요?');">
        <input type="hidden" name="action" value="run">
        <button type="submit">지금 실행 (+30 상향 + 본방 알림)</button>
      </form>
    <? } ?>
  <? } ?>
</div>
</body>
</html>
