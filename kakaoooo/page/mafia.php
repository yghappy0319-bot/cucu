<?php
/**
 * 마피아 — 단일 글로벌 · 낮/밤 10분 시계정각(12:00·10·20·30·40·50) · 주말(토·일) 1회 · 종료 후 다음 주말 오전 10시 시작
 * URL: /page/mafia.php?code=XXXX
 */
require __DIR__ . '/_wallet_preauth.php';
require_once __DIR__ . '/../api/game/wallet_subpage.inc.php';
require_once __DIR__ . '/../api/game/mafia.inc.php';

$boot = wallet_subpage_boot();
$code = (string)($boot['code'] ?? '');
$nick = (string)($boot['nick'] ?? '');
$q = (string)($boot['q'] ?? '');
$need_code = !empty($boot['need_code']);
$isAdmin = (!$need_code && mafia_준호인가($nick));

if (!$need_code && $nick !== '') {
    require_once __DIR__ . '/_game_quit_guard.php';
    게임포기_페이지가드($nick, $code, '마피아');
}
if ($need_code) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>마피아</title></head><body style="margin:0;min-height:100dvh;display:grid;place-items:center;background:#0b0d12;color:#9aa3b2;font-family:sans-serif;padding:24px;text-align:center">';
    echo '<div><p style="margin:0 0 12px;font-weight:700">연구실 코드 링크로 접속해주세요.</p>';
    echo '<a href="/page/wallet.php" style="color:#7dd3c0">← 가방</a></div></body></html>';
    exit;
}

$state = mafia_상태($nick);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0b0d12" id="mfThemeColor">
<title>마피아</title>
<style>
:root {
  --bg: #0b0d12;
  --card: #151922;
  --line: rgba(220, 38, 38, 0.28);
  --text: #f3f4f6;
  --muted: rgba(243, 244, 246, 0.55);
  --red: #f87171;
  --cream: #fde68a;
  --ok: #6ee7b7;
  --blue: #93c5fd;
  --chip-bg: rgba(0,0,0,0.25);
  --chip-line: rgba(255,255,255,0.14);
  --list-bg: rgba(0,0,0,0.22);
  --list-line: rgba(255,255,255,0.08);
  --log-text: rgba(243,244,246,0.82);
  --log-line: rgba(255,255,255,0.06);
  --btn-ghost-bg: rgba(255,255,255,0.06);
  --btn-ghost-line: rgba(255,255,255,0.12);
  --hero-grad-a: rgba(185, 28, 28, 0.28);
  --hero-grad-b: #12151c;
  --hero-grad-c: #07080c;
}
body.is-day {
  --bg: #c8e3f5;
  --card: rgba(255, 252, 245, 0.88);
  --line: rgba(180, 120, 40, 0.28);
  --text: #1c2430;
  --muted: rgba(28, 36, 48, 0.55);
  --red: #c2410c;
  --cream: #b45309;
  --ok: #047857;
  --blue: #0369a1;
  --chip-bg: rgba(255,255,255,0.72);
  --chip-line: rgba(28, 36, 48, 0.14);
  --list-bg: rgba(255,255,255,0.7);
  --list-line: rgba(28, 36, 48, 0.1);
  --log-text: rgba(28, 36, 48, 0.82);
  --log-line: rgba(28, 36, 48, 0.08);
  --btn-ghost-bg: rgba(255,255,255,0.55);
  --btn-ghost-line: rgba(28, 36, 48, 0.14);
  --hero-grad-a: rgba(251, 191, 36, 0.55);
  --hero-grad-b: #9ecae8;
  --hero-grad-c: #e8f4fc;
}
body.is-waiting {
  --bg: #1a1d28;
  --card: #1e2330;
  --line: rgba(253, 230, 138, 0.22);
  --hero-grad-a: rgba(253, 230, 138, 0.12);
  --hero-grad-b: #1a1d28;
  --hero-grad-c: #0e1016;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100dvh;
  color: var(--text);
  font-family: "Pretendard", "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
  background:
    radial-gradient(90% 48% at 50% -8%, var(--hero-grad-a), transparent 55%),
    linear-gradient(180deg, var(--hero-grad-b) 0%, var(--bg) 48%, var(--hero-grad-c) 100%);
  transition: background .45s ease, color .35s ease;
}
body.is-day::before {
  content: "";
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  background:
    radial-gradient(circle at 82% 12%, rgba(255, 220, 120, 0.55), transparent 28%),
    radial-gradient(ellipse 120% 40% at 50% 100%, rgba(255,255,255,0.35), transparent 50%);
}
body > .wrap,
body > .toast { position: relative; z-index: 1; }
.wrap {
  max-width: 480px;
  margin: 0 auto;
  padding: 12px 14px calc(28px + env(safe-area-inset-bottom));
}
.top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.back {
  color: var(--muted);
  text-decoration: none;
  font-weight: 700;
  font-size: 0.9rem;
}
.badge {
  margin-left: auto;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: var(--cream);
  border: 1px solid rgba(253, 230, 138, 0.35);
  border-radius: 999px;
  padding: 5px 10px;
  background: rgba(0,0,0,0.28);
}
body.is-day .badge {
  background: rgba(255,255,255,0.65);
  border-color: rgba(180, 83, 9, 0.3);
}
.hero { text-align: center; padding: 4px 0 14px; }
.hero .ico { font-size: 2.2rem; line-height: 1; }
.hero h1 {
  margin: 6px 0 0;
  font-size: 1.65rem;
  font-weight: 900;
  letter-spacing: -0.03em;
}
.hero p {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 0.86rem;
  font-weight: 600;
  line-height: 1.45;
}
.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 14px 14px 12px;
  margin-bottom: 10px;
  backdrop-filter: blur(8px);
  transition: background .35s ease, border-color .35s ease;
}
body.is-day .card {
  box-shadow: 0 8px 24px rgba(40, 70, 100, 0.08);
}
.card h2 {
  margin: 0 0 8px;
  font-size: 0.9rem;
  font-weight: 850;
  color: var(--red);
}
.phase-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}
.phase-name {
  font-size: 1.35rem;
  font-weight: 900;
  color: var(--cream);
}
.phase-timer {
  font-variant-numeric: tabular-nums;
  font-weight: 800;
  color: var(--ok);
  font-size: 1.05rem;
}
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
  margin-top: 10px;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 650;
}
.meta b { color: var(--text); }
.role-box {
  text-align: center;
  padding: 10px 8px;
}
.role-box .role {
  font-size: 1.4rem;
  font-weight: 900;
  color: var(--red);
}
.role-box .sub {
  margin-top: 4px;
  color: var(--muted);
  font-size: 0.82rem;
  font-weight: 650;
}
.mission-box {
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(248, 113, 113, 0.12);
  border: 1px solid rgba(248, 113, 113, 0.28);
  font-size: 0.82rem;
  line-height: 1.45;
  font-weight: 700;
  text-align: left;
}
.mission-box .mw {
  color: var(--cream);
  font-weight: 900;
}
.mission-box .pot {
  margin-top: 4px;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 650;
}
.mission-box .mission-reroll {
  margin-top: 8px;
}
.mission-box .mission-reroll .btn {
  width: 100%;
  padding: 9px 12px;
  font-size: 0.8rem;
}
.mission-box .reroll-used {
  margin-top: 6px;
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 650;
}
body.is-day .mission-box {
  background: rgba(180, 83, 9, 0.1);
  border-color: rgba(180, 83, 9, 0.25);
}
.dead { opacity: 0.55; }
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  border: 0;
  border-radius: 12px;
  padding: 12px 14px;
  font-weight: 800;
  font-size: 0.95rem;
  cursor: pointer;
  background: linear-gradient(180deg, #ef4444, #b91c1c);
  color: #fff;
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-ghost {
  background: var(--btn-ghost-bg);
  border: 1px solid var(--btn-ghost-line);
  color: var(--text);
}
body.is-day .btn {
  background: linear-gradient(180deg, #f59e0b, #c2410c);
}
.btn-sm {
  width: auto;
  padding: 8px 12px;
  font-size: 0.82rem;
  border-radius: 10px;
}
.targets {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}
.chip {
  border: 1px solid var(--chip-line);
  background: var(--chip-bg);
  color: var(--text);
  border-radius: 999px;
  padding: 7px 11px;
  font-size: 0.8rem;
  font-weight: 750;
  cursor: pointer;
}
.chip.on {
  border-color: rgba(248, 113, 113, 0.7);
  background: rgba(185, 28, 28, 0.35);
  color: #fecaca;
}
body.is-day .chip.on {
  border-color: rgba(194, 65, 12, 0.55);
  background: rgba(251, 146, 60, 0.35);
  color: #7c2d12;
}
.chip:disabled { opacity: 0.4; cursor: not-allowed; }
.vote-tally {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.vote-tally li {
  display: grid;
  grid-template-columns: 2.2rem 1fr auto;
  align-items: center;
  gap: 8px;
  background: var(--list-bg);
  border: 1px solid var(--list-line);
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 0.82rem;
  font-weight: 750;
}
.vote-tally li .rank {
  font-variant-numeric: tabular-nums;
  color: var(--muted);
  font-weight: 850;
}
.vote-tally li .votes {
  font-variant-numeric: tabular-nums;
  color: var(--cream);
  font-weight: 900;
}
.vote-tally li.danger {
  border-color: rgba(248, 113, 113, 0.55);
  background: rgba(185, 28, 28, 0.22);
}
body.is-day .vote-tally li.danger {
  border-color: rgba(194, 65, 12, 0.45);
  background: rgba(251, 146, 60, 0.28);
}
.vote-tally li.danger .rank {
  color: var(--red);
}
.vote-tally-empty {
  margin: 0;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 650;
}
.list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}
.list li {
  background: var(--list-bg);
  border: 1px solid var(--list-line);
  border-radius: 10px;
  padding: 8px 6px;
  text-align: center;
  font-size: 0.78rem;
  font-weight: 750;
}
.list li.out {
  opacity: 0.4;
  text-decoration: line-through;
}
.logs {
  margin: 0;
  padding: 0;
  list-style: none;
  max-height: 220px;
  overflow: auto;
}
.logs li {
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--log-text);
  line-height: 1.4;
  padding: 6px 0;
  border-bottom: 1px solid var(--log-line);
}
.logs li span {
  display: block;
  color: var(--muted);
  font-size: 0.7rem;
  margin-bottom: 2px;
}
.history {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.history-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
  text-align: left;
}
.history-card-head h2 {
  margin: 0;
  pointer-events: none;
}
.history-card-head .chev {
  flex: 0 0 auto;
  width: 1.4rem;
  height: 1.4rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.75rem;
  color: var(--muted);
  background: rgba(255,255,255,0.06);
  transition: transform 0.18s ease;
}
.history-card-head[aria-expanded="false"] .chev {
  transform: rotate(-90deg);
}
.history-body[hidden] {
  display: none !important;
}
.history-item {
  background: var(--list-bg);
  border: 1px solid var(--list-line);
  border-radius: 12px;
  padding: 10px 12px;
}
.history-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}
.history-item.is-open .history-head {
  margin-bottom: 8px;
}
.history-item:not(.is-open) .history-head {
  margin-bottom: 0;
}
.history-head .rnd {
  font-weight: 800;
  font-size: 0.9rem;
}
.history-head .win {
  font-size: 0.75rem;
  font-weight: 750;
  color: var(--cream);
}
.history-head .item-chev {
  margin-left: 6px;
  color: var(--muted);
  font-size: 0.7rem;
  transition: transform 0.15s ease;
  display: inline-block;
}
.history-item.is-open .item-chev {
  transform: rotate(90deg);
}
.history-detail[hidden] {
  display: none !important;
}
.history-role {
  font-size: 0.78rem;
  line-height: 1.45;
  margin: 0 0 4px;
  color: var(--log-text);
}
.history-role b {
  color: var(--text);
  font-weight: 800;
}
.history-role .dead {
  opacity: 0.45;
  text-decoration: line-through;
}
.history-empty {
  margin: 0;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 650;
}
.toast {
  position: fixed;
  left: 50%;
  bottom: 24px;
  transform: translateX(-50%) translateY(12px);
  background: rgba(20,20,28,0.95);
  border: 1px solid rgba(253,230,138,0.35);
  color: var(--cream);
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 750;
  opacity: 0;
  pointer-events: none;
  transition: .25s;
  z-index: 40;
  max-width: 90vw;
  text-align: center;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.admin-box { border-color: rgba(253,230,138,0.35); }
body.is-day .admin-box { border-color: rgba(180, 83, 9, 0.35); }
.hint {
  margin: 8px 0 0;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 650;
  line-height: 1.4;
}
.allies { color: var(--red); font-weight: 800; }
</style>
</head>
<?php
$phase0 = (string)($state['phase'] ?? '');
$bodyClass = ($phase0 === 'day') ? 'is-day' : (($phase0 === 'waiting') ? 'is-waiting' : '');
?>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
<div class="wrap">
  <div class="top">
    <a class="back" href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 가방</a>
    <span class="badge" id="mfBadge">라운드 #<?= (int)$state['round'] ?></span>
  </div>

  <header class="hero">
    <div class="ico" id="mfIco"><?= $phase0 === 'day' ? '☀️' : ($phase0 === 'waiting' ? '⏳' : '🌙') ?></div>
    <h1>마피아</h1>
    <p>방 없이 전원 참여 · 낮/밤 <b>10분 시계정각</b>(12:00·12:10·12:20·12:30…) 전환 · <b>주말(토·일) 1회</b> · 종료 후 <b>다음 주말 오전 <?= (int)($state['daily_start_hour'] ?? 10) ?>시</b>에 다음 라운드<br>마피아는 밤 2명 지목 · 낮 투표는 일반 2표 · <b>정치인·마피아 3표</b> · 득표 <b>1·2등 확정처형</b> · <b>3등 50%</b> · <b>낮 2회차부터 투표 0건이면 생존자 1명 강제탈락</b> · 마피아 처형 시 투표 시민 0.5% · 마피아 승리 시 생존 마피아 <b>5%</b> · 시민 승리 시 생존자 <b>각 1%</b><br>의사 보호 성공 시 피보호자가 본방냥 총합 1%를 의사에게 지급(본방 부족 시 게임냥 · 둘 다 없으면 사망)<br>마피아 공통미션: 밤마다 공창 단어 출제 · <b>밤마다 1회 변경</b>(팀 공용) · <b>기회 2회</b> · 2회 실패 시 다른 역할과 랜덤 교체 · 성공 시 본방냥 <b>0.3%</b> 누적 (마피아 승리 시 분배)</p>
  </header>

  <section class="card">
    <div class="phase-row">
      <div class="phase-name" id="mfPhase"><?= htmlspecialchars($state['phase_label'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="phase-timer" id="mfTimer">--:--</div>
    </div>
    <p class="hint" id="mfNextStart"<?= in_array($phase0, ['waiting', 'idle'], true) ? '' : ' hidden' ?>><?php
      if (in_array($phase0, ['waiting', 'idle'], true)) {
          echo '다음 시작 ' . htmlspecialchars((string)($state['next_daily_label'] ?? '오전 10시'), ENT_QUOTES, 'UTF-8') . '까지';
      }
    ?></p>
    <div class="meta">
      <span>생존 <b id="mfAlive"><?= (int)($state['counts']['alive'] ?? 0) ?></b></span>
      <span>마피아 <b id="mfMafiaCnt"><?= (int)($state['counts']['mafia'] ?? 0) ?></b></span>
      <span>시민 <b id="mfCitizenCnt"><?= (int)($state['counts']['citizen'] ?? 0) ?></b></span>
      <span>의사 <b id="mfDoctorCnt"><?= (int)($state['counts']['doctor'] ?? 0) ?></b></span>
      <span>경찰 <b id="mfPoliceCnt"><?= (int)($state['counts']['police'] ?? 0) ?></b></span>
      <span>정치인 <b id="mfPoliticianCnt"><?= (int)($state['counts']['politician'] ?? 0) ?></b></span>
    </div>
    <p class="hint" id="mfResult"><?= htmlspecialchars($state['last_result'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
  </section>

  <section class="card">
    <h2>내 역할</h2>
    <div class="role-box<?= empty($state['me']['alive']) ? ' dead' : '' ?>" id="mfMyBox">
      <div class="role" id="mfMyRole"><?= htmlspecialchars($state['me']['role_label'] ?? '미배정', ENT_QUOTES, 'UTF-8') ?></div>
      <div class="sub" id="mfMySub"><?= !empty($state['me']) ? ($state['me']['alive'] ? '생존' : '탈락') : '라운드 시작 전' ?></div>
      <div class="sub allies" id="mfAllies"></div>
      <div class="mission-box" id="mfMission" hidden></div>
    </div>
  </section>

  <section class="card" id="mfVoteTallyCard" hidden>
    <h2>실시간 투표 현황</h2>
    <ol class="vote-tally" id="mfVoteTally"></ol>
    <p class="vote-tally-empty" id="mfVoteTallyEmpty" hidden>아직 투표가 없어요</p>
    <p class="hint" id="mfVoteTallyMeta"></p>
  </section>

  <section class="card" id="mfActCard" hidden>
    <h2 id="mfActTitle">행동</h2>
    <div class="targets" id="mfTargets"></div>
    <button type="button" class="btn" id="mfActBtn" style="margin-top:10px;" disabled>등록</button>
    <p class="hint" id="mfActHint"></p>
  </section>

  <section class="card">
    <h2>생존자</h2>
    <ul class="list" id="mfList"></ul>
  </section>

  <section class="card" id="mfHistoryCard">
    <button type="button" class="history-card-head" id="mfHistoryToggle" aria-expanded="false" aria-controls="mfHistoryBody">
      <h2>이전 라운드</h2>
      <span class="chev" aria-hidden="true">▼</span>
    </button>
    <div class="history-body" id="mfHistoryBody" hidden>
      <div class="history" id="mfHistory" style="margin-top:10px;"></div>
    </div>
  </section>

  <section class="card">
    <h2>기록</h2>
    <ul class="logs" id="mfLogs"></ul>
  </section>

  <?php if ($isAdmin): ?>
  <section class="card admin-box">
    <h2>민호 · 관리</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
      <button type="button" class="btn btn-ghost" id="mfForceDay">☀️ 낮으로</button>
      <button type="button" class="btn btn-ghost" id="mfForceNight">🌙 밤으로</button>
    </div>
    <button type="button" class="btn btn-ghost" id="mfRejoinMafia" style="margin-bottom:8px;">생존 마피아 일괄 재편입</button>
    <button type="button" class="btn" id="mfReset">초기화 / 새 라운드</button>
    <p class="hint">생존 마피아 재편입: 생존 마피아 미션실패 초기화 + 전원 낮투표·밤지목 리셋 (기록 미남김).<br>낮/밤 강제: 현재 페이즈를 즉시 결산하고 다음으로 넘깁니다.<br>초기화: 역할 랜덤 재배정 후 밤부터 시작.</p>
  </section>
  <?php endif; ?>
</div>
<div class="toast" id="mfToast"></div>

<script>
(function () {
  var CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  var API = '/page/mafia_api.php';
  var NICK = <?= json_encode($nick, JSON_UNESCAPED_UNICODE) ?>;
  var IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  var state = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;
  var selected = [];
  var selectMax = 1;
  var actDraftKey = '';
  var remain = parseInt(state.remain_sec || 0, 10) || 0;
  var refreshTimer = null;
  var lastRefreshPhase = '';

  function actContextKey(data) {
    var me = (data && data.me) || {};
    return [
      (data && data.phase) || '',
      me.role || '',
      (data && data.can_vote_day) ? '1' : '0',
      (data && data.can_act_night) ? '1' : '0',
      String(parseInt((data && data.max_day_votes) || 0, 10) || 0),
      String(parseInt((data && data.max_mafia_targets) || 0, 10) || 0)
    ].join('|');
  }

  function toast(msg) {
    var el = document.getElementById('mfToast');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { el.classList.remove('show'); }, 2600);
  }

  function pad2(n) {
    n = Math.max(0, Math.floor(n));
    return (n < 10 ? '0' : '') + n;
  }

  function fmtRemain(sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (h > 0) return h + ':' + pad2(m) + ':' + pad2(s);
    return pad2(m) + ':' + pad2(s);
  }

  function post(action, extra) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('code', CODE);
    if (extra) {
      Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
    }
    return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function renderTimer() {
    setText('mfTimer', fmtRemain(remain));
  }

  function applyPhaseTheme(phase) {
    var body = document.body;
    body.classList.toggle('is-day', phase === 'day');
    body.classList.toggle('is-waiting', phase === 'waiting');
    var ico = document.getElementById('mfIco');
    if (ico) {
      ico.textContent = phase === 'day' ? '☀️' : (phase === 'waiting' ? '⏳' : '🌙');
    }
    var meta = document.getElementById('mfThemeColor');
    if (meta) {
      meta.setAttribute('content', phase === 'day' ? '#9ecae8' : (phase === 'waiting' ? '#1a1d28' : '#0b0d12'));
    }
  }

  function render(data) {
    if (!data || !data.ok) return;
    state = data;
    remain = parseInt(data.remain_sec || 0, 10) || 0;
    applyPhaseTheme(data.phase || '');
    setText('mfBadge', '라운드 #' + (data.round || 0));
    setText('mfPhase', (function () {
      var label = data.phase_label || data.phase || '-';
      var dayNo = parseInt(data.day_no, 10) || 0;
      if ((data.phase || '') === 'day' && dayNo > 0) {
        return label + ' #' + dayNo;
      }
      return label;
    })());
    setText('mfAlive', String((data.counts && data.counts.alive) || 0));
    setText('mfMafiaCnt', String((data.counts && data.counts.mafia) || 0));
    setText('mfCitizenCnt', String((data.counts && data.counts.citizen) || 0));
    setText('mfDoctorCnt', String((data.counts && data.counts.doctor) || 0));
    setText('mfPoliceCnt', String((data.counts && data.counts.police) || 0));
    setText('mfPoliticianCnt', String((data.counts && data.counts.politician) || 0));
    setText('mfResult', data.last_result || '');
    var nextEl = document.getElementById('mfNextStart');
    if (nextEl) {
      if (data.phase === 'waiting' || data.phase === 'idle') {
        nextEl.textContent = '다음 시작 ' + (data.next_daily_label || '오전 10시') + '까지';
        nextEl.hidden = false;
      } else {
        nextEl.textContent = '';
        nextEl.hidden = true;
      }
    }
    renderTimer();

    var me = data.me;
    var roleEl = document.getElementById('mfMyRole');
    var subEl = document.getElementById('mfMySub');
    var box = document.getElementById('mfMyBox');
    if (!roleEl || !subEl || !box) return;
    if (me) {
      roleEl.textContent = me.role_label || me.role || '-';
      subEl.textContent = me.alive ? '생존' : '탈락';
      box.classList.toggle('dead', !me.alive);
      if (me.role === 'police' && me.investigated && me.investigate_result) {
        subEl.textContent += ' · 조사결과: ' + (me.investigate_result === 'mafia' ? '마피아' : '시민측');
      }
    } else {
      roleEl.textContent = '미배정';
      subEl.textContent = data.phase === 'idle' ? '초기화를 기다려주세요' : '이번 라운드 미참여';
      box.classList.remove('dead');
    }

    var allies = document.getElementById('mfAllies');
    if (allies) {
      allies.textContent = '';
      allies.hidden = true;
    }

    var missionEl = document.getElementById('mfMission');
    if (missionEl) {
      var m = data.mission || {};
      var potLine = '누적 성공보수: ' + (m.pot_label || '0') + '냥';
      if (me && me.role === 'mafia') {
        missionEl.hidden = false;
        var label = m.my_label ? ('[' + m.my_label + '] ') : '';
        var chanceLine = '';
        if (typeof m.remain_chances !== 'undefined' && m.max_fails) {
          chanceLine = ' · 남은 기회 ' + m.remain_chances + '/' + m.max_fails + ' (2회 실패 시 역할 교체)';
        }
        var rerollHtml = '';
        if (m.can_reroll) {
          rerollHtml = '<div class="mission-reroll"><button type="button" class="btn btn-ghost btn-sm" id="mfMissionReroll">미션 1회 변경</button></div>';
        } else if (data.phase === 'night' && m.word && m.reroll_used) {
          rerollHtml = '<div class="reroll-used">이번 밤 미션 변경권 사용됨</div>';
        }
        if (m.word) {
          missionEl.innerHTML = label + '공통미션 · 공창에서 <span class="mw">「' + escapeHtml(m.word) + '」</span> 를 자연스럽게 말하세요'
            + '<div class="pot">' + potLine + chanceLine + ' · 성공 시 낮에 익명 공개</div>'
            + rerollHtml;
        } else {
          missionEl.innerHTML = label + (m.hint || '공통미션 대기')
            + '<div class="pot">' + potLine + chanceLine + '</div>'
            + rerollHtml;
        }
        var rerollBtn = document.getElementById('mfMissionReroll');
        if (rerollBtn) {
          rerollBtn.addEventListener('click', function () {
            if (!confirm('공통미션 단어를 바꿀까요?\n밤마다 팀 공용 1회만 가능합니다.')) return;
            rerollBtn.disabled = true;
            post('mission_reroll').then(function (res) {
              if (!res.ok) { toast(res.msg || '실패'); rerollBtn.disabled = false; return; }
              toast(res.msg || '미션을 바꿨어요');
              render(res);
            }).catch(function () {
              toast('네트워크 오류');
              rerollBtn.disabled = false;
            });
          });
        }
      } else if (m.hint || (m.pot && m.pot > 0)) {
        missionEl.hidden = false;
        missionEl.innerHTML = escapeHtml(m.hint || '마피아 공통미션')
          + '<div class="pot">' + potLine + '</div>';
      } else {
        missionEl.hidden = true;
        missionEl.innerHTML = '';
      }
    }

    var list = document.getElementById('mfList');
    if (list) {
      list.innerHTML = (data.players || []).map(function (p) {
        var label = p.nick;
        if (p.role_label) label += '<br><small>' + p.role_label + '</small>';
        return '<li class="' + (p.alive ? '' : 'out') + '">' + label + '</li>';
      }).join('') || '<li>없음</li>';
    }

    var logs = document.getElementById('mfLogs');
    if (logs) {
      logs.innerHTML = (data.logs || []).filter(function (l) {
        var m = String(l.msg || '');
        return m.indexOf('역할 교체') < 0 && m.indexOf('역할교체') < 0 && m.indexOf('랜덤교체') < 0;
      }).slice().reverse().map(function (l) {
        return '<li><span>' + (l.at || '') + '</span>' + (l.msg || '') + '</li>';
      }).join('') || '<li>기록 없음</li>';
    }

    if (IS_ADMIN) {
      renderAdminRoster(data.admin_roster || []);
    }

    renderHistory(data.history || []);
    renderVoteTally(data);
    renderAct(data);
    if (lastRefreshPhase !== (data.phase || '')) {
      scheduleRefresh();
    }
  }

  function renderVoteTally(data) {
    var card = document.getElementById('mfVoteTallyCard');
    var list = document.getElementById('mfVoteTally');
    var empty = document.getElementById('mfVoteTallyEmpty');
    var meta = document.getElementById('mfVoteTallyMeta');
    if (!card || !list) return;

    if ((data.phase || '') !== 'day') {
      card.hidden = true;
      list.innerHTML = '';
      if (empty) empty.hidden = true;
      if (meta) meta.textContent = '';
      return;
    }

    card.hidden = false;
    var tally = data.day_vote_tally || [];
    var execute = parseInt(data.day_execute, 10) || 3;
    var guaranteed = parseInt(data.day_execute_guaranteed, 10);
    if (isNaN(guaranteed) || guaranteed < 0) guaranteed = 2;
    var chance = parseFloat(data.day_execute_chance);
    if (isNaN(chance) || chance < 0) chance = 0.5;
    var chancePct = Math.round(chance * 100);
    var cast = parseInt(data.day_vote_cast, 10) || 0;
    var alive = parseInt(data.day_vote_alive, 10) || 0;

    if (!tally.length) {
      list.innerHTML = '';
      list.hidden = true;
      if (empty) empty.hidden = false;
    } else {
      list.hidden = false;
      if (empty) empty.hidden = true;
      list.innerHTML = tally.map(function (row, i) {
        var danger = i < execute ? ' danger' : '';
        var mark = '';
        if (i < guaranteed) mark = ' · 확정처형';
        else if (i < execute) mark = ' · ' + chancePct + '%';
        return '<li class="' + danger.trim() + '">'
          + '<span class="rank">' + (row.rank || (i + 1)) + '등</span>'
          + '<span class="nick">' + escapeHtml(row.nick || '') + mark + '</span>'
          + '<span class="votes">' + (row.votes || 0) + '표</span>'
          + '</li>';
      }).join('');
    }

    if (meta) {
      var dayNo = parseInt(data.day_no, 10) || 0;
      var forceFrom = parseInt(data.day_force_elim_from, 10) || 2;
      var forceHint = '';
      if (dayNo >= forceFrom) {
        forceHint = ' · ⚠️ 투표 0건이면 강제탈락';
      } else if (dayNo > 0) {
        forceHint = ' · 낮 ' + forceFrom + '회차부터 무투표 강제탈락';
      }
      meta.textContent = (dayNo > 0 ? ('낮 #' + dayNo + ' · ') : '')
        + '투표 참여 ' + cast + '/' + alive
        + ' · 1·2등 확정 · 3등 ' + chancePct + '%'
        + forceHint
        + ' · 약 5초마다 갱신';
    }
  }

  function renderAdminRoster(roster) {
    var sel = document.getElementById('mfRoleNick');
    if (!sel) return;
    var prev = sel.value || '';
    var opts = (roster || []).map(function (p) {
      var nick = p.nick || '';
      var label = nick + (p.alive ? '' : ' (사망)') + ' · ' + (p.role_label || p.role || '');
      return '<option value="' + escapeHtml(nick) + '"' + (nick === prev ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
    }).join('');
    sel.innerHTML = opts || '<option value="">참가자 없음</option>';
    if (prev) {
      sel.value = prev;
    }
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderHistory(items) {
    var box = document.getElementById('mfHistory');
    if (!box) return;
    if (!items || !items.length) {
      box.innerHTML = '<p class="history-empty">아직 공개된 이전 라운드가 없어요. 라운드가 끝나면 역할이 공개됩니다.</p>';
      return;
    }
    var roleOrder = [
      { key: 'mafia', label: '마피아' },
      { key: 'police', label: '경찰' },
      { key: 'doctor', label: '의사' },
      { key: 'politician', label: '정치인' },
      { key: 'citizen', label: '시민' }
    ];
    box.innerHTML = items.map(function (h, idx) {
      var aliveMap = h.alive || {};
      var roles = h.roles || {};
      var lines = roleOrder.map(function (r) {
        var nicks = roles[r.key] || [];
        if (!nicks.length) return '';
        var names = nicks.map(function (n) {
          var dead = aliveMap[n] === false;
          return '<span class="' + (dead ? 'dead' : '') + '">' + escapeHtml(n) + '</span>';
        }).join(', ');
        return '<p class="history-role"><b>' + r.label + '</b> · ' + names + '</p>';
      }).filter(Boolean).join('');
      var win = h.winner_label || (h.result ? String(h.result).split('·')[0].trim() : '결과 미확인');
      var openClass = idx === 0 ? ' is-open' : '';
      var detailHidden = idx === 0 ? '' : ' hidden';
      return '<div class="history-item' + openClass + '" data-hist-idx="' + idx + '">'
        + '<div class="history-head" role="button" tabindex="0" aria-expanded="' + (idx === 0 ? 'true' : 'false') + '">'
        + '<span class="rnd">라운드 #' + (h.round || 0) + ' <span class="item-chev" aria-hidden="true">›</span></span>'
        + '<span class="win">' + escapeHtml(win) + '</span></div>'
        + '<div class="history-detail"' + detailHidden + '>' + lines + '</div>'
        + '</div>';
    }).join('');
  }

  (function bindHistoryToggle() {
    var btn = document.getElementById('mfHistoryToggle');
    var body = document.getElementById('mfHistoryBody');
    if (!btn || !body) return;
    var KEY = 'mafia_history_open';
    try {
      if (localStorage.getItem(KEY) === '1') {
        body.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
      }
    } catch (e) {}
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      open = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      body.hidden = !open;
      try { localStorage.setItem(KEY, open ? '1' : '0'); } catch (e) {}
    });
    var box = document.getElementById('mfHistory');
    if (box) {
      box.addEventListener('click', function (ev) {
        var head = ev.target.closest('.history-head');
        if (!head || !box.contains(head)) return;
        var item = head.closest('.history-item');
        if (!item) return;
        var detail = item.querySelector('.history-detail');
        if (!detail) return;
        var open = item.classList.toggle('is-open');
        detail.hidden = !open;
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      box.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var head = ev.target.closest('.history-head');
        if (!head) return;
        ev.preventDefault();
        head.click();
      });
    }
  })();

  function renderAct(data) {
    var card = document.getElementById('mfActCard');
    var title = document.getElementById('mfActTitle');
    var box = document.getElementById('mfTargets');
    var btn = document.getElementById('mfActBtn');
    var hint = document.getElementById('mfActHint');
    if (!card || !btn) return;

    var me = data.me;
    var canNight = !!data.can_act_night;
    var canDay = !!data.can_vote_day;
    if (!canNight && !canDay) {
      selected = [];
      actDraftKey = '';
      card.hidden = true;
      return;
    }
    card.hidden = false;

    var key = actContextKey(data);
    var prevSelected = selected.slice();
    var keepDraft = (key === actDraftKey && prevSelected.length > 0);

    selectMax = 1;
    var targets = (data.alive_list || []).filter(function (n) { return n !== NICK; });
    var current = [];
    if (canNight && me && me.role === 'mafia') {
      selectMax = parseInt(data.max_mafia_targets, 10) || 2;
      current = me.night_action_nicks || (me.night_action_nick ? String(me.night_action_nick).split(',') : []);
      title.textContent = '밤 · 살해 지목 (최대 ' + selectMax + '명)';
      hint.textContent = current.length
        ? ('현재 지목: ' + current.join(', '))
        : ('생존자 ' + selectMax + '명까지 지목하세요');
      btn.textContent = '살해 지목 등록';
    } else if (canNight && me && me.role === 'police') {
      selectMax = 1;
      current = me.night_action_nicks || (me.night_action_nick ? [me.night_action_nick] : []);
      title.textContent = '밤 · 조사';
      hint.textContent = current.length ? ('현재 조사: ' + current.join(', ')) : '1명을 조사하세요';
      btn.textContent = '조사 등록';
    } else if (canNight && me && me.role === 'doctor') {
      selectMax = 1;
      targets = data.alive_list || [];
      current = me.night_action_nicks || (me.night_action_nick ? [me.night_action_nick] : []);
      title.textContent = '밤 · 보호';
      hint.textContent = current.length ? ('현재 보호: ' + current.join(', ')) : '1명을 보호하세요';
      btn.textContent = '보호 등록';
    } else if (canDay) {
      selectMax = parseInt(data.max_day_votes, 10) || 2;
      current = (me && me.day_vote_nicks) || (me && me.day_vote_nick ? String(me.day_vote_nick).split(',') : []);
      title.textContent = '낮 · 투표 (최대 ' + selectMax + '표)';
      var executeHint = parseInt(data.day_execute, 10) || 3;
      var guaranteed = parseInt(data.day_execute_guaranteed, 10);
      if (isNaN(guaranteed) || guaranteed < 0) guaranteed = 2;
      var chancePct = Math.round((parseFloat(data.day_execute_chance) || 0.5) * 100);
      var dayNo = parseInt(data.day_no, 10) || 0;
      var forceFrom = parseInt(data.day_force_elim_from, 10) || 2;
      var baseHint = current.length
        ? ('현재 투표: ' + current.join(', '))
        : ('최대 ' + selectMax + '표 · 1·2등 확정처형 · 3등 ' + chancePct + '% (상위 ' + executeHint + '명)');
      if (dayNo >= forceFrom && !current.length) {
        baseHint += ' · 투표 0건이면 강제탈락';
      }
      hint.textContent = baseHint;
      btn.textContent = '투표 등록';
    }

    current = (current || []).map(function (n) { return String(n || '').trim(); }).filter(Boolean);
    if (keepDraft) {
      // 자동 새로고침으로 선택 중인 칩이 날아가지 않게 유지
      var targetSet = {};
      targets.forEach(function (n) { targetSet[n] = true; });
      selected = prevSelected.filter(function (n) { return !!targetSet[n]; }).slice(0, selectMax);
      if (selected.length) {
        hint.textContent = '선택: ' + selected.join(', ') + ' (' + selected.length + '/' + selectMax + ')';
      }
    } else {
      selected = current.slice(0, selectMax);
    }
    actDraftKey = key;
    btn.disabled = selected.length < 1;

    box.innerHTML = targets.map(function (n) {
      var on = selected.indexOf(n) !== -1 ? ' on' : '';
      return '<button type="button" class="chip' + on + '" data-nick="' + n + '">' + n + '</button>';
    }).join('') || '<span class="hint">대상 없음</span>';
  }

  var targetsEl = document.getElementById('mfTargets');
  if (targetsEl) {
    targetsEl.addEventListener('click', function (e) {
      var chip = e.target.closest('.chip');
      if (!chip) return;
      var nick = chip.getAttribute('data-nick') || '';
      if (!nick) return;
      var idx = selected.indexOf(nick);
      if (idx !== -1) {
        selected.splice(idx, 1);
      } else {
        if (selectMax <= 1) {
          selected = [nick];
        } else if (selected.length >= selectMax) {
          toast('최대 ' + selectMax + '명까지 선택할 수 있어요');
          return;
        } else {
          selected.push(nick);
        }
      }
      Array.prototype.forEach.call(document.querySelectorAll('#mfTargets .chip'), function (el) {
        var n = el.getAttribute('data-nick') || '';
        el.classList.toggle('on', selected.indexOf(n) !== -1);
      });
      var actBtn = document.getElementById('mfActBtn');
      if (actBtn) actBtn.disabled = selected.length < 1;
      var hint = document.getElementById('mfActHint');
      if (hint) {
        hint.textContent = selected.length
          ? ('선택: ' + selected.join(', ') + ' (' + selected.length + '/' + selectMax + ')')
          : ('대상을 선택하세요 (최대 ' + selectMax + '명)');
      }
    });
  }

  var actBtn = document.getElementById('mfActBtn');
  if (actBtn) {
    actBtn.addEventListener('click', function () {
      if (!selected.length) return;
      var action = state.can_vote_day ? 'day_vote' : 'night_act';
      var payload = { targets: selected.join(','), target: selected[0] };
      post(action, payload).then(function (res) {
        if (!res.ok) { toast(res.msg || '실패'); return; }
        toast(res.msg || '등록했어요');
        render(res);
      }).catch(function () { toast('네트워크 오류'); });
    });
  }

  if (IS_ADMIN) {
    var resetBtn = document.getElementById('mfReset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (!confirm('마피아를 초기화할까요?\n활성 회원 전원에게 역할을 랜덤 배정하고 밤부터 시작합니다.')) return;
        post('reset').then(function (res) {
          if (!res.ok) { toast(res.msg || '실패'); return; }
          toast(res.msg || '초기화했어요');
          render(res);
        }).catch(function () { toast('네트워크 오류'); });
      });
    }
    function forcePhase(phase, label) {
      if (!confirm(label + '로 강제 전환할까요?\n현재 페이즈를 즉시 결산합니다.')) return;
      post('force_phase', { phase: phase }).then(function (res) {
        if (!res.ok) { toast(res.msg || '실패'); return; }
        toast(res.msg || '전환했어요');
        render(res);
      }).catch(function () { toast('네트워크 오류'); });
    }
    var dayBtn = document.getElementById('mfForceDay');
    var nightBtn = document.getElementById('mfForceNight');
    if (dayBtn) dayBtn.addEventListener('click', function () { forcePhase('day', '낮'); });
    if (nightBtn) nightBtn.addEventListener('click', function () { forcePhase('night', '밤'); });

    var setRoleBtn = document.getElementById('mfSetRole');
    if (setRoleBtn) {
      setRoleBtn.addEventListener('click', function () {
        var nickSel = document.getElementById('mfRoleNick');
        var roleSel = document.getElementById('mfRolePick');
        var target = nickSel ? String(nickSel.value || '').trim() : '';
        var role = roleSel ? String(roleSel.value || '').trim() : '';
        if (!target || !role) {
          toast('대상과 역할을 선택해주세요');
          return;
        }
        var roleLabel = roleSel && roleSel.options[roleSel.selectedIndex]
          ? roleSel.options[roleSel.selectedIndex].text
          : role;
        if (!confirm(target + ' → ' + roleLabel + ' 로 변경할까요?\n기록에는 남지 않습니다.')) return;
        post('set_role', { target: target, role: role }).then(function (res) {
          if (!res.ok) { toast(res.msg || '실패'); return; }
          toast(res.msg || '변경했어요');
          render(res);
        }).catch(function () { toast('네트워크 오류'); });
      });
    }

    var rejoinBtn = document.getElementById('mfRejoinMafia');
    if (rejoinBtn) {
      rejoinBtn.addEventListener('click', function () {
        var aliveMafia = (state.admin_roster || []).filter(function (p) {
          return p && p.alive && p.role === 'mafia';
        }).map(function (p) { return p.nick; });
        var label = aliveMafia.length
          ? (aliveMafia.join(', ') + ' (' + aliveMafia.length + '명)')
          : '현재 생존 마피아';
        if (!confirm('생존 마피아를 일괄 재편입할까요?\n' + label + '\n미션실패 초기화 + 전원 투표/지목 리셋 · 기록에는 남지 않습니다.')) return;
        post('rejoin_mafia').then(function (res) {
          if (!res.ok) { toast(res.msg || '실패'); return; }
          toast(res.msg || '재편입했어요');
          render(res);
        }).catch(function () { toast('네트워크 오류'); });
      });
    }
  }

  render(state);
  setInterval(function () {
    if (remain > 0) remain -= 1;
    renderTimer();
  }, 1000);

  function scheduleRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    var ms = (state && state.phase === 'day') ? 5000 : 15000;
    refreshTimer = setInterval(refresh, ms);
    lastRefreshPhase = (state && state.phase) || '';
  }
  function refresh() {
    post('status').then(function (res) {
      if (res && res.ok) render(res);
    }).catch(function () {});
  }
  scheduleRefresh();
  setTimeout(refresh, 1200);
})();
</script>
<?php
if ($code !== '') {
  $fab = __DIR__ . '/../api/game/wallet_nav_fab.inc.php';
  if (is_file($fab)) {
    require_once $fab;
    if (function_exists('wallet_nav_fab_render')) {
      wallet_nav_fab_render(['code' => $code]);
    }
  }
}
?>
</body>
</html>
