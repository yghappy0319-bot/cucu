<?php
/**
 * 오늘 타수 랭킹 — 평타 | 생타 탭
 * URL: /page/today_tasu.php?code=XXXX
 */
require __DIR__ . '/_wallet_preauth.php';
include_once __DIR__ . '/_today_tasu_lib.php';

$code = '';
$nick = '';
if (!empty($GLOBALS['wallet_preauth']['code'])) {
    $code = trim((string)$GLOBALS['wallet_preauth']['code']);
}
if ($code === '' && isset($_REQUEST['code'])) {
    $code = trim((string)$_REQUEST['code']);
}
if (!empty($GLOBALS['wallet_preauth']['row']['name'])) {
    $nick = trim((string)$GLOBALS['wallet_preauth']['row']['name']);
}
if ($nick === '' && $code !== '' && function_exists('db_select')) {
    $행 = db_select("SELECT name FROM tb_member WHERE code = '" . addslashes($code) . "' LIMIT 1");
    $nick = trim((string)($행['name'] ?? ''));
}
if ($nick !== '' && function_exists('getTwoCharNick')) {
    $nick = getTwoCharNick($nick) ?: $nick;
}

$날짜 = tt_오늘날짜();
$평타목록 = tt_평타목록($날짜);
$생타목록 = tt_생타목록($날짜);
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'pyeong';
if ($tab !== 'saeng') {
    $tab = 'pyeong';
}

$날짜표시 = $날짜;
try {
    $날짜표시 = (new DateTime($날짜, new DateTimeZone('Asia/Seoul')))->format('n월 j일');
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0a1210">
<title>오늘 타수</title>
<style>
:root {
  --bg: #0a1210;
  --card: #12201c;
  --line: rgba(94, 234, 212, 0.16);
  --text: #e8eef8;
  --muted: rgba(232, 238, 248, 0.58);
  --mint: #5eead4;
  --gold: #fcd34d;
  --me: #f87171;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100dvh;
  background: radial-gradient(120% 80% at 50% -10%, #16352c 0%, var(--bg) 55%);
  color: var(--text);
  font-family: "Pretendard", "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
}
.tt-wrap {
  max-width: 480px;
  margin: 0 auto;
  padding: 12px 12px calc(20px + env(safe-area-inset-bottom));
}
.tt-top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.tt-back {
  color: var(--muted);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 700;
}
.tt-title {
  flex: 1;
  margin: 0;
  font-size: 1.12rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.tt-date {
  font-size: 0.8rem;
  color: var(--muted);
  font-weight: 650;
}
.tt-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 5px;
  margin-bottom: 14px;
}
.tt-tab {
  appearance: none;
  border: 0;
  background: transparent;
  color: var(--muted);
  font: inherit;
  font-weight: 750;
  font-size: 0.95rem;
  border-radius: 10px;
  padding: 11px 8px;
  cursor: pointer;
}
.tt-tab.active {
  background: rgba(94, 234, 212, 0.16);
  color: var(--mint);
  box-shadow: inset 0 0 0 1px rgba(94, 234, 212, 0.28);
}
.tt-hint {
  margin: 0 0 10px;
  font-size: 0.78rem;
  color: var(--muted);
  font-weight: 600;
  line-height: 1.4;
}
.tt-list {
  display: none;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
}
.tt-list.active { display: block; }
.tt-row {
  display: grid;
  grid-template-columns: 44px 1fr auto;
  gap: 8px;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.tt-row:last-child { border-bottom: 0; }
.tt-row.is-me {
  background: rgba(248, 113, 113, 0.08);
}
.tt-rank {
  font-size: 0.82rem;
  font-weight: 800;
  color: var(--muted);
  text-align: center;
}
.tt-row:nth-child(-n+3) .tt-rank { color: var(--gold); }
.tt-nick {
  font-size: 0.98rem;
  font-weight: 750;
  letter-spacing: -0.01em;
}
.tt-row.is-me .tt-nick { color: var(--me); }
.tt-cnt {
  font-variant-numeric: tabular-nums;
  font-weight: 800;
  font-size: 0.95rem;
  color: var(--mint);
  text-align: right;
}
.tt-cnt small {
  display: block;
  font-size: 0.72rem;
  font-weight: 650;
  color: var(--muted);
  margin-top: 2px;
}
.tt-steal {
  display: block;
  margin-top: 3px;
  font-size: 0.68rem;
  font-weight: 650;
  color: var(--muted);
  letter-spacing: -0.02em;
  white-space: nowrap;
}
.tt-steal .cur { color: rgba(232, 238, 248, 0.72); }
.tt-steal .lost { color: #fca5a5; }
.tt-empty {
  padding: 36px 16px;
  text-align: center;
  color: var(--muted);
  font-weight: 650;
  line-height: 1.5;
}
.tt-summary {
  margin-top: 10px;
  text-align: center;
  font-size: 0.8rem;
  color: var(--muted);
  font-weight: 650;
}
</style>
</head>
<body>
<div class="tt-wrap">
  <div class="tt-top">
    <a class="tt-back" href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 가방</a>
    <h1 class="tt-title">오늘 타수</h1>
    <div class="tt-date"><?= htmlspecialchars($날짜표시, ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <div class="tt-tabs" role="tablist">
    <button type="button" class="tt-tab<?= $tab === 'pyeong' ? ' active' : '' ?>" data-tab="pyeong" role="tab" aria-selected="<?= $tab === 'pyeong' ? 'true' : 'false' ?>">평타</button>
    <button type="button" class="tt-tab<?= $tab === 'saeng' ? ' active' : '' ?>" data-tab="saeng" role="tab" aria-selected="<?= $tab === 'saeng' ? 'true' : 'false' ?>">생타</button>
  </div>

  <p class="tt-hint" id="ttHint">
    <?= $tab === 'saeng'
      ? '생타 = 채팅 로우 + 사진(+2) · 버프타 병기'
      : '위: 뺏기기 전 총 전체 · 아래: 현재 타수 | 뺏긴 타수' ?>
  </p>

  <div class="tt-list<?= $tab === 'pyeong' ? ' active' : '' ?>" id="panelPyeong" role="tabpanel">
    <?php if (empty($평타목록)) { ?>
      <div class="tt-empty">오늘 평타 기록이 없어요.</div>
    <?php } else { ?>
      <?php foreach ($평타목록 as $row) {
        $isMe = ($nick !== '' && $row['nick'] === $nick);
      ?>
      <div class="tt-row<?= $isMe ? ' is-me' : '' ?>">
        <div class="tt-rank"><?= (int)$row['rank'] ?></div>
        <div class="tt-nick"><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?><?= $isMe ? ' ★' : '' ?></div>
        <div class="tt-cnt">
          <?= number_format((int)$row['total']) ?><small>뺏기기 전</small>
          <span class="tt-steal"><span class="cur">현재 <?= number_format((int)$row['cnt']) ?></span> | <span class="lost">뺏긴 <?= number_format((int)$row['stolen']) ?></span></span>
        </div>
      </div>
      <?php } ?>
    <?php } ?>
  </div>

  <div class="tt-list<?= $tab === 'saeng' ? ' active' : '' ?>" id="panelSaeng" role="tabpanel">
    <?php if (empty($생타목록)) { ?>
      <div class="tt-empty">오늘 생타 기록이 없어요.</div>
    <?php } else { ?>
      <?php foreach ($생타목록 as $row) {
        $isMe = ($nick !== '' && $row['nick'] === $nick);
      ?>
      <div class="tt-row<?= $isMe ? ' is-me' : '' ?>">
        <div class="tt-rank"><?= (int)$row['rank'] ?></div>
        <div class="tt-nick"><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?><?= $isMe ? ' ★' : '' ?></div>
        <div class="tt-cnt"><?= number_format((int)$row['raw']) ?><small>버프 <?= number_format((int)$row['buff']) ?></small></div>
      </div>
      <?php } ?>
    <?php } ?>
  </div>

  <div class="tt-summary" id="ttSummary">
    <?php if ($tab === 'saeng') { ?>
      참여 <?= count($생타목록) ?>명
    <?php } else { ?>
      참여 <?= count($평타목록) ?>명
    <?php } ?>
  </div>
</div>
<script>
(function () {
  var tabs = document.querySelectorAll('.tt-tab');
  var panelP = document.getElementById('panelPyeong');
  var panelS = document.getElementById('panelSaeng');
  var hint = document.getElementById('ttHint');
  var summary = document.getElementById('ttSummary');
  var countP = <?= (int)count($평타목록) ?>;
  var countS = <?= (int)count($생타목록) ?>;

  function activate(name) {
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panelP.classList.toggle('active', name === 'pyeong');
    panelS.classList.toggle('active', name === 'saeng');
    if (hint) {
      hint.textContent = name === 'saeng'
        ? '생타 = 채팅 로우 + 사진(+2) · 버프타 병기'
        : '위: 뺏기기 전 총 전체 · 아래: 현재 타수 | 뺏긴 타수';
    }
    if (summary) {
      summary.textContent = '참여 ' + (name === 'saeng' ? countS : countP) + '명';
    }
    try {
      var url = new URL(location.href);
      url.searchParams.set('tab', name);
      history.replaceState(null, '', url.toString());
    } catch (e) {}
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      activate(t.getAttribute('data-tab') || 'pyeong');
    });
  });
})();
</script>
</body>
</html>
