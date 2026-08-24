<?php
/**
 * 보스처치풀 초기화 (민호)
 * URL: /page/boss_loot_pool_reset.php?code=XXXX
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/boss_raid.inc.php';
include_once __DIR__ . '/_item_stock_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$before = '0';
$beforeFmt = '—';
$결과 = '';
$결과종류 = '';

if ($준호) {
    $before = boss_raid_처치풀_조회();
    $beforeFmt = function_exists('랭킹_게임냥표시')
        ? 랭킹_게임냥표시($before, '냥')
        : ($before . '냥');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'reset') {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm !== '초기화') {
            $결과 = '확인란에 「초기화」를 입력하세요.';
            $결과종류 = 'err';
        } else {
            $r = boss_raid_처치풀_초기화($닉);
            $결과 = (string)($r['data'] ?? '완료');
            $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
            $before = boss_raid_처치풀_조회();
            $beforeFmt = function_exists('랭킹_게임냥표시')
                ? 랭킹_게임냥표시($before, '냥')
                : ($before . '냥');
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
<title>보스처치풀 초기화</title>
<style>
body { margin:0; font-family:sans-serif; background:#141210; color:#e8e4dc; }
.wrap { max-width:520px; margin:0 auto; padding:24px 16px; }
.card { background:#1c1916; border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:16px; }
.amt { font-size:1.4rem; margin:12px 0; word-break:break-all; }
.muted { color:#9a9388; font-size:.9rem; }
.msg.ok { color:#6cbc6a; } .msg.err { color:#d16b6b; }
input, button { padding:10px 12px; border-radius:8px; border:0; font-size:1rem; }
input { background:#0e0c0a; color:#e8e4dc; border:1px solid rgba(255,255,255,.12); width:140px; }
button { background:#c9a96e; color:#1a140c; font-weight:700; cursor:pointer; }
.need { text-align:center; padding:40px 16px; color:#9a9388; }
</style>
</head>
<body>
<div class="wrap">
  <h1 style="font-size:1.2rem;margin:0 0 8px;">보스처치풀 초기화</h1>
  <p class="muted">config.보스처치풀 → 0 · 민호 전용 · 채팅 <code>.보스처치풀초기화</code> 도 동일</p>

  <?php if (!$로그인) { ?>
    <div class="need">?code= 필요</div>
  <?php } elseif (!$준호) { ?>
    <div class="need">민호만 가능 (현재: <?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?>)</div>
  <?php } else { ?>
    <div class="card">
      <?php if ($결과 !== '') { ?>
        <p class="msg <?= $결과종류 === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($결과, ENT_QUOTES, 'UTF-8') ?></p>
      <?php } ?>
      <div class="muted">현재 처치풀</div>
      <div class="amt"><?= htmlspecialchars($beforeFmt, ENT_QUOTES, 'UTF-8') ?></div>
      <form method="post" action="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
            onsubmit="return confirm('보스처치풀을 0으로 초기화할까요?');">
        <input type="hidden" name="action" value="reset">
        <label class="muted">확인: 「초기화」입력</label><br><br>
        <input type="text" name="confirm" placeholder="초기화" autocomplete="off">
        <button type="submit">초기화 실행</button>
      </form>
    </div>
  <?php } ?>
</div>
</body>
</html>
