<?php
/**
 * 친구 거주 지도 (대한민국 SVG 약도)
 * URL: /page/friend_map.php
 *   회원 코드(지갑)만 지도 / 코드 없으면 본방 오픈채팅
 *   ?code=XXXX  (본인 강조 · 입장 24시간 미만이면 대기)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (!function_exists('getTwoCharNick')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
include_once __DIR__ . '/_wallet_preauth.php';
include_once __DIR__ . '/_friend_map_lib.php';

$회원 = fm_auth();
if (!fm_내부열람허용($회원)) {
    fm_외부화면_출력();
}
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$q = fm_code_query($code);
$대기중 = $로그인 && fm_입장대기_미충족($회원['regdate'] ?? '');
$가능시각 = $대기중 ? fm_입장대기_가능시각($회원['regdate'] ?? '') : '';
$언락ts = $대기중 ? fm_입장대기_언락타임스탬프($회원['regdate'] ?? '') : 0;
// 회원 코드가 있는 사람만 여기까지 옴 · 로그인한 신규(24시간 미만)만 대기 화면
$이용가능 = !$대기중;
$지도 = $이용가능 ? fm_회원목록() : ['pins' => [], 'unknown' => [], 'total' => 0, 'mapped' => 0];
$지도json = json_encode($지도, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
if ($지도json === false) {
    $지도json = '{"pins":[],"unknown":[],"total":0,"mapped":0}';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>친구 지도</title>
<meta name="robots" content="noindex,nofollow,noarchive">
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --fm-ink: #1a2332;
  --fm-muted: #6b7785;
  --fm-line: rgba(26, 35, 50, 0.12);
  --fm-sheet: #f7f4ef;
  --fm-sea: #d4e4ef;
  --fm-land: #e8dcc8;
  --fm-land-stroke: #b09a7a;
  --fm-accent: #c45c26;
  --fm-pin: #2c5f7a;
  --fm-pin-multi: #c45c26;
  --fm-pin-me: #d1262e;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  background: var(--fm-sheet);
  color: var(--fm-ink);
  font-family: "Pretendard", "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
  min-height: 100dvh;
}
.fm-wrap {
  max-width: 520px;
  margin: 0 auto;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  padding: 12px 12px calc(16px + env(safe-area-inset-bottom));
}
.fm-top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}
.fm-back {
  color: var(--fm-muted);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
}
.fm-title {
  flex: 1;
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.fm-stats {
  font-size: 0.8rem;
  color: var(--fm-muted);
  font-weight: 600;
}
.fm-search {
  width: 100%;
  border: 1px solid var(--fm-line);
  background: #fff;
  border-radius: 12px;
  padding: 11px 14px;
  font-size: 0.95rem;
  margin-bottom: 10px;
  outline: none;
}
.fm-search:focus {
  border-color: var(--fm-accent);
}
.fm-map-box {
  position: relative;
  flex: 1 1 auto;
  width: 100%;
  min-height: min(68dvh, 620px);
  max-height: 78dvh;
  aspect-ratio: 5 / 7;
  background: linear-gradient(165deg, #c5d9e8 0%, #d8e6f0 42%, #c9dce9 100%);
  border-radius: 18px;
  border: 1px solid var(--fm-line);
  overflow: hidden;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}
.fm-map-box svg {
  display: block;
  width: 100%;
  height: 100%;
}
.fm-pin {
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.fm-pin .halo {
  fill: rgba(44, 95, 122, 0.14);
}
.fm-pin .dot {
  fill: var(--fm-pin);
  stroke: #fff;
  stroke-width: 2;
}
.fm-pin.is-multi .dot {
  fill: var(--fm-pin-multi);
}
.fm-pin.is-me .halo {
  fill: rgba(209, 38, 46, 0.22);
}
.fm-pin.is-me .dot {
  fill: var(--fm-pin-me);
  stroke: #fff;
  stroke-width: 2.5;
}
.fm-pin.is-me .label {
  fill: var(--fm-pin-me);
  opacity: 1;
}
.fm-pin.is-active .dot,
.fm-pin:focus .dot {
  fill: var(--fm-accent);
}
.fm-pin.is-me.is-active .dot,
.fm-pin.is-me:focus .dot {
  fill: #a81c22;
}
.fm-pin .count {
  fill: #fff;
  font-size: 11px;
  font-weight: 800;
  text-anchor: middle;
  dominant-baseline: central;
  pointer-events: none;
}
.fm-pin .label {
  fill: var(--fm-ink);
  font-size: 9px;
  font-weight: 700;
  text-anchor: middle;
  pointer-events: none;
  paint-order: stroke;
  stroke: rgba(210, 226, 238, 0.95);
  stroke-width: 3px;
  stroke-linejoin: round;
  opacity: 0;
}
.fm-pin.is-active .label,
.fm-pin:focus .label {
  opacity: 1;
}
.fm-pin .nicks {
  pointer-events: none;
}
.fm-pin .nick {
  fill: #2a3544;
  font-size: 7px;
  font-weight: 650;
  text-anchor: middle;
  paint-order: stroke;
  stroke: rgba(212, 228, 239, 0.95);
  stroke-width: 2.5px;
  stroke-linejoin: round;
}
.fm-pin .nick.is-me {
  fill: var(--fm-pin-me);
  font-weight: 800;
  font-size: 7.5px;
}
.fm-hint {
  margin: 8px 2px 0;
  font-size: 0.78rem;
  color: var(--fm-muted);
  font-weight: 600;
  text-align: center;
}
.fm-unknown {
  margin-top: 12px;
  background: #fff;
  border: 1px solid var(--fm-line);
  border-radius: 14px;
  padding: 12px 14px;
}
.fm-unknown h2 {
  margin: 0 0 8px;
  font-size: 0.85rem;
  color: var(--fm-muted);
  font-weight: 700;
}
.fm-unknown-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.fm-chip {
  border: 1px solid var(--fm-line);
  background: var(--fm-sheet);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 0.82rem;
  font-weight: 650;
  cursor: pointer;
  color: var(--fm-ink);
}
.fm-chip:hover { border-color: var(--fm-accent); color: var(--fm-accent); }
.fm-chip.is-hidden { display: none; }
.fm-empty {
  text-align: center;
  padding: 48px 20px;
  color: var(--fm-muted);
  font-weight: 600;
  line-height: 1.5;
}
.fm-gate {
  max-width: 380px;
  margin: 72px auto;
  padding: 32px 24px 28px;
  background: #fff;
  border-radius: 18px;
  border: 1px solid var(--fm-line);
  text-align: center;
  box-shadow: 0 10px 28px rgba(26, 35, 50, 0.06);
}
.fm-gate h1 {
  margin: 0 0 10px;
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.fm-gate p {
  color: var(--fm-muted);
  font-size: 0.92rem;
  line-height: 1.55;
  margin: 0 0 8px;
}
.fm-gate a {
  display: inline-block;
  margin-top: 16px;
  color: var(--fm-accent);
  font-weight: 700;
  text-decoration: none;
}
.fm-timer-wrap {
  margin: 20px 0 8px;
  padding: 18px 14px 16px;
  border-radius: 14px;
  background: linear-gradient(160deg, #fff8f2 0%, #f7f4ef 100%);
  border: 1px solid rgba(196, 92, 38, 0.18);
}
.fm-timer-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--fm-muted);
  margin-bottom: 8px;
  letter-spacing: 0.02em;
}
.fm-timer {
  font-variant-numeric: tabular-nums;
  font-feature-settings: "tnum";
  font-size: 2.35rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: var(--fm-accent);
  line-height: 1.1;
}
.fm-timer-sub {
  margin-top: 10px;
  font-size: 0.82rem;
  color: var(--fm-muted);
  font-weight: 600;
}
.fm-timer-sub strong {
  color: var(--fm-ink);
  font-weight: 750;
}

/* 시트 */
.fm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(20, 24, 32, 0.45);
  z-index: 40;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease;
}
.fm-overlay.open {
  opacity: 1;
  pointer-events: auto;
}
.fm-sheet {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 50;
  max-width: 520px;
  margin: 0 auto;
  background: #fff;
  border-radius: 18px 18px 0 0;
  padding: 10px 16px calc(20px + env(safe-area-inset-bottom));
  transform: translateY(110%);
  transition: transform 0.25s ease;
  max-height: 72dvh;
  overflow: auto;
  box-shadow: 0 -8px 28px rgba(0,0,0,0.12);
}
.fm-sheet.open { transform: translateY(0); }
.fm-sheet-handle {
  width: 40px;
  height: 4px;
  border-radius: 999px;
  background: #d0d5dc;
  margin: 4px auto 12px;
}
.fm-sheet h3 {
  margin: 0 0 6px;
  font-size: 1.05rem;
  font-weight: 800;
}
.fm-sheet .meta {
  font-size: 0.85rem;
  color: var(--fm-muted);
  margin-bottom: 12px;
  font-weight: 600;
}
.fm-pick-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 8px;
}
.fm-pick-btn {
  text-align: left;
  border: 1px solid var(--fm-line);
  background: var(--fm-sheet);
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  color: var(--fm-ink);
}
.fm-pick-btn:hover { border-color: var(--fm-accent); }
.fm-profile-body {
  white-space: pre-wrap;
  word-break: keep-all;
  font-size: 0.92rem;
  line-height: 1.55;
  background: var(--fm-sheet);
  border-radius: 12px;
  padding: 14px;
  border: 1px solid var(--fm-line);
}
.fm-credit {
  color: #b42318;
  font-weight: 700;
  line-height: 1.5;
}
.fm-close {
  margin-top: 14px;
  width: 100%;
  border: none;
  background: var(--fm-ink);
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  border-radius: 12px;
  padding: 13px;
  cursor: pointer;
}
@media (max-width: 480px) {
  .fm-wrap { padding: 10px 10px calc(14px + env(safe-area-inset-bottom)); }
  .fm-map-box {
    min-height: min(62dvh, 560px);
    max-height: 72dvh;
    border-radius: 16px;
  }
  .fm-sheet { max-height: 78dvh; }
  .fm-timer { font-size: 2.05rem; }
}
</style>
</head>
<body>
<?php if ($대기중) { ?>
<div class="fm-gate">
  <h1>친구 지도</h1>
  <p>방에 들어온 지 <?= (int)fm_대기시간_시간() ?>시간이 지나야<br>친구 지도를 열람할 수 있어요.</p>
  <div class="fm-timer-wrap">
    <div class="fm-timer-label">열람까지 남은 시간</div>
    <div class="fm-timer" id="fmCountdown" aria-live="polite">--:--:--</div>
    <?php if ($가능시각 !== '') { ?>
    <div class="fm-timer-sub"><strong><?= htmlspecialchars($가능시각, ENT_QUOTES, 'UTF-8') ?></strong> 부터 가능</div>
    <?php } ?>
  </div>
  <?php if ($로그인) { ?>
  <a href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 지갑으로</a>
  <?php } ?>
</div>
<script>
(function () {
  var unlockAt = <?= (int)$언락ts ?> * 1000;
  var el = document.getElementById('fmCountdown');
  if (!el || !unlockAt) return;

  function pad(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function tick() {
    var remain = Math.floor((unlockAt - Date.now()) / 1000);
    if (remain <= 0) {
      el.textContent = '00:00:00';
      setTimeout(function () { location.reload(); }, 600);
      return;
    }
    var h = Math.floor(remain / 3600);
    var m = Math.floor((remain % 3600) / 60);
    var s = remain % 60;
    el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
    setTimeout(tick, 250);
  }
  tick();
})();
</script>
<?php } else { ?>
<div class="fm-wrap">
  <div class="fm-top">
    <?php if ($로그인) { ?>
    <a class="fm-back" href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 지갑</a>
    <?php } else { ?>
    <span class="fm-back" style="visibility:hidden">←</span>
    <?php } ?>
    <h1 class="fm-title">친구 지도</h1>
    <div class="fm-stats"><span id="fmStatMapped"><?= (int)$지도['mapped'] ?></span>/<?= (int)$지도['total'] ?></div>
  </div>

  <input type="search" class="fm-search" id="fmSearch" placeholder="닉·지역 검색" autocomplete="off" enterkeyhint="search">

  <div class="fm-map-box" id="fmMapBox">
    <svg viewBox="0 0 400 560" xmlns="http://www.w3.org/2000/svg" aria-label="대한민국 약도" id="fmSvg" preserveAspectRatio="xMidYMid meet">
      <defs>
        <linearGradient id="fmSea" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#b9d0e2"/>
          <stop offset="45%" stop-color="#d2e4ef"/>
          <stop offset="100%" stop-color="#c0d6e6"/>
        </linearGradient>
        <linearGradient id="fmLand" x1="0.2" y1="0" x2="0.85" y2="1">
          <stop offset="0%" stop-color="#f0e6d4"/>
          <stop offset="55%" stop-color="#e4d5bc"/>
          <stop offset="100%" stop-color="#d9c7a8"/>
        </linearGradient>
        <filter id="fmLandShadow" x="-12%" y="-8%" width="124%" height="120%">
          <feDropShadow dx="0" dy="3" stdDeviation="4" flood-color="#3a5470" flood-opacity="0.22"/>
        </filter>
      </defs>
      <rect x="0" y="0" width="400" height="560" fill="url(#fmSea)"/>
      <!-- 대한민국(남한) 실루엣 + 제주 — 화면 가득 -->
      <g filter="url(#fmLandShadow)" fill="url(#fmLand)" stroke="#a89274" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round">
        <path d="M44.4 91.1L50.6 94.6L57.1 97.4L63.8 99.6L70.8 101.2L78.1 102.1L85.4 102.3L92.9 101.9L100.6 100.7L108.3 98.9L116.1 96.5L123.9 93.8L131.6 90.5L139.4 86.8L147.0 82.9L154.5 78.8L161.9 74.4L169.2 69.8L176.4 65.3L183.5 60.9L190.5 56.6L197.5 52.5L204.5 48.6L211.5 45.2L218.5 42.1L225.5 39.3L232.4 36.8L239.2 34.4L246.0 32.4L252.8 30.5L259.1 29.3L265.1 28.7L270.6 28.8L275.8 29.5L280.8 30.9L285.6 32.9L290.2 35.7L294.6 39.2L299.2 43.8L303.9 49.6L308.9 56.5L314.1 64.6L319.1 72.9L323.9 81.6L328.5 90.5L332.9 99.8L337.2 109.0L341.5 118.2L345.7 127.5L349.8 136.7L353.8 146.2L357.7 156.0L361.5 166.1L365.1 176.5L368.5 186.9L371.6 197.3L374.4 207.7L377.0 218.1L379.3 228.2L381.4 238.0L383.2 247.5L384.8 256.7L385.7 266.0L386.1 275.2L385.9 284.4L385.1 293.7L383.9 302.3L382.2 310.4L380.1 317.9L377.5 324.8L374.5 331.2L371.0 337.0L367.0 342.2L362.6 346.8L358.2 351.0L353.6 354.8L349.0 358.2L344.4 361.2L339.5 363.8L334.3 366.2L328.9 368.1L323.2 369.7L316.8 371.4L309.6 373.0L301.8 374.6L293.3 376.2L284.9 377.8L276.8 379.3L268.8 380.7L261.0 382.1L253.3 383.4L245.5 384.5L237.8 385.5L230.0 386.5L221.9 387.4L213.5 388.3L204.8 389.2L195.7 390.2L186.6 390.7L177.6 391.0L168.5 390.9L159.5 390.4L150.7 389.6L142.3 388.4L134.2 386.9L126.5 385.1L118.7 383.1L110.9 380.9L103.2 378.5L95.4 376.0L88.0 373.3L80.8 370.5L74.1 367.7L67.6 364.7L61.4 361.6L55.6 358.5L50.1 355.3L44.9 352.1L40.1 348.6L35.6 344.9L31.3 341.0L27.5 336.8L24.2 332.3L21.6 327.3L19.7 322.0L18.4 316.2L18.2 310.4L19.1 304.6L21.1 298.9L24.2 293.1L27.6 287.3L31.2 281.6L35.1 275.8L39.2 270.0L43.1 264.2L46.7 258.5L50.1 252.7L53.2 246.9L55.2 241.2L56.1 235.4L55.9 229.6L54.6 223.8L52.7 218.1L50.1 212.3L46.9 206.5L43.0 200.8L39.1 195.0L35.2 189.2L31.3 183.5L27.5 177.7L23.9 171.9L20.7 166.1L17.8 160.4L15.2 154.6L13.9 149.0L13.9 143.6L15.2 138.3L17.8 133.2L20.5 128.3L23.5 123.4L26.7 118.7L30.1 114.1L32.6 109.3L34.3 104.3L35.1 99.2L35.1 93.9L36.7 90.8L39.8 89.8L44.4 91.1Z"/>
        <ellipse cx="71.3" cy="507.9" rx="35.9" ry="15.4"/>
      </g>
      <g id="fmPins"></g>
    </svg>
  </div>
  <p class="fm-hint">빨간 점·★닉 = 나 · 작은 글씨는 친구 닉</p>

  <div class="fm-unknown" id="fmUnknownWrap" hidden>
    <h2>위치 미확인 (<span id="fmUnknownCount">0</span>)</h2>
    <div class="fm-unknown-list" id="fmUnknownList"></div>
  </div>
</div>

<div class="fm-overlay" id="fmOverlay"></div>
<div class="fm-sheet" id="fmSheet" role="dialog" aria-modal="true">
  <div class="fm-sheet-handle"></div>
  <div id="fmSheetBody"></div>
  <button type="button" class="fm-close" id="fmClose">닫기</button>
</div>

<script>
(function () {
  const DATA = <?= $지도json ?>;
  const MY_NICK = <?= json_encode($닉, JSON_UNESCAPED_UNICODE) ?>;
  const pinsEl = document.getElementById('fmPins');
  const searchEl = document.getElementById('fmSearch');
  const overlay = document.getElementById('fmOverlay');
  const sheet = document.getElementById('fmSheet');
  const sheetBody = document.getElementById('fmSheetBody');
  const unknownWrap = document.getElementById('fmUnknownWrap');
  const unknownList = document.getElementById('fmUnknownList');
  const unknownCount = document.getElementById('fmUnknownCount');

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  function genderMark(g) {
    if (g === 1) return '♂';
    if (g === 2) return '♀';
    return '';
  }

  function memberTitle(m) {
    const t = (m.title || '').trim();
    if (t) return t;
    return 'Lv' + (m.level || 0);
  }

  function openSheet(html) {
    sheetBody.innerHTML = html;
    overlay.classList.add('open');
    sheet.classList.add('open');
  }

  function closeSheet() {
    overlay.classList.remove('open');
    sheet.classList.remove('open');
    document.querySelectorAll('.fm-pin.is-active').forEach(function (el) {
      el.classList.remove('is-active');
    });
  }

  function showProfile(m) {
    let body;
    if (m.point_neg) {
      body = '<div class="fm-credit">신용이 불량한 관계로<br>정보를 볼 수 없습니다.<br>빠른 시일내 상환하여 주십시오.</div>';
    } else {
      const text = (m.content || '').trim() || '(프로필 없음)';
      body = '<div class="fm-profile-body">' + esc(text) + '</div>';
    }
    openSheet(
      '<h3>' + esc(m.name) + ' ' + genderMark(m.gender) + '</h3>' +
      '<div class="meta">' + esc(memberTitle(m)) +
        (m.region ? ' · 📍 ' + esc(m.region) : '') +
        (m.num ? ' · 색 ' + m.num : '') +
      '</div>' + body
    );
  }

  function showRegionList(pin, members) {
    if (members.length === 1) {
      showProfile(members[0]);
      return;
    }
    const buttons = members.map(function (m, i) {
      return '<button type="button" class="fm-pick-btn" data-i="' + i + '">' +
        '<strong>' + esc(m.name) + '</strong> ' + genderMark(m.gender) +
        (m.region ? '<span style="display:block;margin-top:2px;color:#6b7785;font-weight:600;font-size:0.82rem">' + esc(m.region) + '</span>' : '') +
        '</button>';
    }).join('');
    openSheet(
      '<h3>📍 ' + esc(pin.label) + '</h3>' +
      '<div class="meta">' + members.length + '명 · 친구를 선택하세요</div>' +
      '<div class="fm-pick-list">' + buttons + '</div>'
    );
    sheetBody.querySelectorAll('.fm-pick-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const i = parseInt(btn.getAttribute('data-i'), 10);
        showProfile(members[i]);
      });
    });
  }

  function memberMatches(m, q) {
    if (!q) return true;
    return m.name.indexOf(q) !== -1 || (m.region && m.region.indexOf(q) !== -1);
  }

  /** 아주 가까운 동일권만 묶고, 시·군 라벨은 유지 */
  function clusterNearby(items, dist) {
    const used = {};
    const out = [];
    for (let i = 0; i < items.length; i++) {
      if (used[i]) continue;
      const base = items[i];
      const group = [base];
      used[i] = true;
      for (let j = i + 1; j < items.length; j++) {
        if (used[j]) continue;
        const o = items[j];
        // 라벨이 다르면 합치지 않음 (수원·성남·고양 등 분리)
        if (base.label !== o.label) continue;
        const dx = base.x - o.x;
        const dy = base.y - o.y;
        if (dx * dx + dy * dy <= dist * dist) {
          group.push(o);
          used[j] = true;
        }
      }
      let sx = 0, sy = 0, members = [];
      group.forEach(function (g) {
        sx += g.x;
        sy += g.y;
        members = members.concat(g.members);
      });
      members.sort(function (a, b) {
        return String(a.name).localeCompare(String(b.name), 'ko');
      });
      out.push({
        x: sx / group.length,
        y: sy / group.length,
        label: base.label,
        members: members,
        regionCount: 1
      });
    }
    return out;
  }

  /** 겹치는 핀을 서로 밀어 간격 확보 */
  function spreadPins(pins, minDist) {
    const pts = pins.map(function (p) {
      return {
        x: p.x,
        y: p.y,
        label: p.label,
        members: p.members,
        regionCount: p.regionCount || 1,
        ox: p.x,
        oy: p.y
      };
    });
    const minX = 28, maxX = 372, minY = 36, maxY = 530;
    for (let iter = 0; iter < 40; iter++) {
      for (let i = 0; i < pts.length; i++) {
        for (let j = i + 1; j < pts.length; j++) {
          let dx = pts[j].x - pts[i].x;
          let dy = pts[j].y - pts[i].y;
          let d2 = dx * dx + dy * dy;
          const min2 = minDist * minDist;
          if (d2 >= min2 && d2 > 0.0001) continue;
          if (d2 < 0.0001) {
            const ang = (i * 2.4 + j) % (Math.PI * 2);
            dx = Math.cos(ang);
            dy = Math.sin(ang);
            d2 = 1;
          }
          const d = Math.sqrt(d2);
          const push = (minDist - d) * 0.52;
          const ux = dx / d;
          const uy = dy / d;
          pts[i].x -= ux * push;
          pts[i].y -= uy * push;
          pts[j].x += ux * push;
          pts[j].y += uy * push;
        }
      }
      for (let i = 0; i < pts.length; i++) {
        const p = pts[i];
        p.x += (p.ox - p.x) * 0.04;
        p.y += (p.oy - p.y) * 0.04;
        if (p.x < minX) p.x = minX;
        if (p.x > maxX) p.x = maxX;
        if (p.y < minY) p.y = minY;
        if (p.y > maxY) p.y = maxY;
      }
    }
    return pts;
  }

  function shortLabel(label) {
    const s = String(label || '');
    if (s === '광주(경기)') return '경기광주';
    if (s.length <= 4) return s;
    return s.slice(0, 4);
  }

  function pinHasMe(members) {
    if (!MY_NICK) return false;
    return members.some(function (m) { return m.name === MY_NICK; });
  }

  function sortedMembers(members) {
    return members.slice().sort(function (a, b) {
      if (MY_NICK) {
        if (a.name === MY_NICK) return -1;
        if (b.name === MY_NICK) return 1;
      }
      return String(a.name).localeCompare(String(b.name), 'ko');
    });
  }

  function shortNick(name) {
    const s = String(name || '');
    if (s.length <= 6) return s;
    return s.slice(0, 5) + '…';
  }

  function renderPins(q) {
    pinsEl.innerHTML = '';
    const qn = (q || '').trim();
    let visibleMapped = 0;

    const filtered = [];
    DATA.pins.forEach(function (pin) {
      const members = pin.members.filter(function (m) { return memberMatches(m, qn); });
      if (!members.length) return;
      visibleMapped += members.length;
      filtered.push({
        x: pin.x,
        y: pin.y,
        label: pin.label,
        members: members
      });
    });

    let clusters = qn ? filtered : clusterNearby(filtered, 12);
    // 닉 목록 높이만큼 더 벌림
    const maxN = clusters.reduce(function (m, p) { return Math.max(m, p.members.length); }, 1);
    clusters = spreadPins(clusters, Math.min(56, 34 + maxN * 2.5));
    clusters.sort(function (a, b) {
      return (pinHasMe(a.members) ? 1 : 0) - (pinHasMe(b.members) ? 1 : 0);
    });

    clusters.forEach(function (pin) {
      const members = sortedMembers(pin.members);
      const n = members.length;
      const isMe = pinHasMe(members);
      const r = isMe ? (n >= 5 ? 13 : 11) : (n >= 12 ? 12 : (n >= 5 ? 10 : 8));
      const lineH = 8.5;

      const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
      g.setAttribute('class', 'fm-pin' + (n > 1 ? ' is-multi' : '') + (isMe ? ' is-me' : ''));
      g.setAttribute('transform', 'translate(' + pin.x + ',' + pin.y + ')');
      g.setAttribute('tabindex', '0');
      g.setAttribute('role', 'button');
      g.setAttribute('aria-label', (isMe ? '내 위치 · ' : '') + pin.label + ' ' + n + '명');

      if (pin.ox != null && (Math.abs(pin.ox - pin.x) > 6 || Math.abs(pin.oy - pin.y) > 6)) {
        const stem = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        stem.setAttribute('x1', String(pin.ox - pin.x));
        stem.setAttribute('y1', String(pin.oy - pin.y));
        stem.setAttribute('x2', '0');
        stem.setAttribute('y2', '0');
        stem.setAttribute('stroke', isMe ? 'rgba(209,38,46,0.4)' : 'rgba(44,95,122,0.28)');
        stem.setAttribute('stroke-width', '1');
        stem.setAttribute('pointer-events', 'none');
        g.appendChild(stem);
        const anchor = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        anchor.setAttribute('cx', String(pin.ox - pin.x));
        anchor.setAttribute('cy', String(pin.oy - pin.y));
        anchor.setAttribute('r', '2');
        anchor.setAttribute('fill', isMe ? 'rgba(209,38,46,0.55)' : 'rgba(44,95,122,0.35)');
        anchor.setAttribute('pointer-events', 'none');
        g.appendChild(anchor);
      }

      const halo = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      halo.setAttribute('class', 'halo');
      halo.setAttribute('cx', '0');
      halo.setAttribute('cy', '0');
      halo.setAttribute('r', String(Math.max(r + (isMe ? 8 : 5), isMe ? 18 : 14)));
      g.appendChild(halo);

      const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      dot.setAttribute('class', 'dot');
      dot.setAttribute('cx', '0');
      dot.setAttribute('cy', '0');
      dot.setAttribute('r', String(r));
      g.appendChild(dot);

      const count = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      count.setAttribute('class', 'count');
      count.setAttribute('x', '0');
      count.setAttribute('y', '0');
      count.textContent = String(n);
      g.appendChild(count);

      const nicks = document.createElementNS('http://www.w3.org/2000/svg', 'g');
      nicks.setAttribute('class', 'nicks');
      members.forEach(function (m, i) {
        const t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        const mine = MY_NICK && m.name === MY_NICK;
        t.setAttribute('class', 'nick' + (mine ? ' is-me' : ''));
        t.setAttribute('x', '0');
        t.setAttribute('y', String(r + 9 + i * lineH));
        t.textContent = mine ? ('★' + shortNick(m.name)) : shortNick(m.name);
        nicks.appendChild(t);
      });
      g.appendChild(nicks);

      function activate() {
        document.querySelectorAll('.fm-pin.is-active').forEach(function (el) {
          el.classList.remove('is-active');
        });
        g.classList.add('is-active');
        showRegionList(pin, members);
      }

      g.addEventListener('click', function (e) {
        e.stopPropagation();
        activate();
      });
      g.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          activate();
        }
      });

      pinsEl.appendChild(g);
    });

    document.getElementById('fmStatMapped').textContent = String(visibleMapped);
  }

  function renderUnknown(q) {
    const qn = (q || '').trim();
    const list = DATA.unknown.filter(function (m) { return memberMatches(m, qn); });
    unknownList.innerHTML = '';
    if (!DATA.unknown.length) {
      unknownWrap.hidden = true;
      return;
    }
    unknownWrap.hidden = false;
    unknownCount.textContent = String(list.length);
    list.forEach(function (m) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fm-chip';
      btn.textContent = m.name + (m.region ? ' · ' + m.region : '');
      btn.addEventListener('click', function () { showProfile(m); });
      unknownList.appendChild(btn);
    });
  }

  function refresh() {
    const q = searchEl.value || '';
    renderPins(q);
    renderUnknown(q);
  }

  searchEl.addEventListener('input', refresh);
  overlay.addEventListener('click', closeSheet);
  document.getElementById('fmClose').addEventListener('click', closeSheet);

  refresh();
})();
</script>
<?php } ?>
</body>
</html>
