<?php
/**
 * 색표 고정가 매매
 * URL: /page/color_market.php?code=XXXX
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (!function_exists('냥_정수문자열')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
include_once __DIR__ . '/_color_market_lib.php';

cm_테이블보장();
cm_초과판매_정리();

$회원 = cm_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$포인트 = $로그인 ? ($회원['newpoint'] ?? '0') : '0';
$내색번호 = $로그인 ? (int)$회원['num'] : 0;
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && cm_준호인가($닉);
$본방총합 = cm_본방냥총합();
$본방총합표시 = cm_냥표시($본방총합);

$목록 = cm_목록();
$최근 = cm_최근거래(10);

$판매중수 = 0;
$무주인수 = 0;
$내색수 = 0;
foreach ($목록 as $it) {
    $n = (int)($it['color_num'] ?? 0);
    $isReserved = !empty($it['reserved']) || $n === 1;
    if ((int)$it['listed'] === 1 && !$isReserved) {
        $판매중수++;
    }
    if ($isReserved) {
        continue;
    }
    $표시닉 = $it['display_nicks'] ?? [];
    if ($it['owner_nick'] === '' && empty($표시닉)) {
        $무주인수++;
    }
    if ($로그인 && cm_내색인가($닉, $it['owner_nick'], $it['wearers'] ?? [])) {
        $내색수++;
    }
}

$feePct = (int)round(COLOR_MARKET_FEE_RATE * 100);
$minPriceDisp = cm_냥표시(COLOR_MARKET_MIN_PRICE);

$색변보유 = $로그인 ? cm_색변_보유($닉) : 0;
$색변시세 = cm_색변_시세정보();
$게임냥 = $로그인 ? cm_냥($회원['point'] ?? 0) : '0';
$게임냥표시 = function_exists('구매가_축약표시')
    ? 구매가_축약표시($게임냥, '게임냥')
    : (number_format((float)$게임냥) . '게임냥');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>색표마켓</title>
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --cm-ink: #1c2430;
  --cm-muted: #6b7280;
  --cm-line: rgba(28, 36, 48, 0.12);
  --cm-sheet: #fffaf3;
  --cm-accent: #c45c26;
  --cm-ok: #1f7a4c;
  --cm-sale: #b45309;
  --cm-bg1: #f3e7d3;
  --cm-bg2: #e8f0ea;
}
* { box-sizing: border-box; }
html, body {
  margin: 0;
  min-height: 100%;
  background:
    radial-gradient(120% 80% at 10% -10%, #fff6e8 0%, transparent 55%),
    radial-gradient(90% 70% at 100% 0%, #e4f2ea 0%, transparent 50%),
    linear-gradient(180deg, var(--cm-bg1), var(--cm-bg2));
  color: var(--cm-ink);
  font-family: 'MyFont', 'Apple SD Gothic Neo', sans-serif;
  font-size: 17px;
  -webkit-tap-highlight-color: transparent;
}
.cm-wrap {
  max-width: 480px;
  margin: 0 auto;
  padding: 14px 12px 120px;
}
.cm-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.cm-title {
  margin: 0;
  font-size: 32px;
  letter-spacing: -0.03em;
  line-height: 1.15;
}
.cm-sub {
  margin: 4px 0 0;
  font-size: 16px;
  color: var(--cm-muted);
  line-height: 1.35;
}
.cm-user {
  text-align: right;
  flex-shrink: 0;
  min-width: 110px;
}
.cm-nick {
  font-size: 19px;
  font-weight: 700;
}
.cm-point {
  margin-top: 2px;
  font-size: 16px;
  color: var(--cm-accent);
}
.cm-hint {
  margin: 0 0 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(255,255,255,0.55);
  border: 1px solid var(--cm-line);
  font-size: 15px;
  color: var(--cm-muted);
  line-height: 1.45;
}
.cm-hint strong { color: var(--cm-ink); }
.cm-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.cm-stat {
  background: rgba(255,255,255,0.6);
  border: 1px solid var(--cm-line);
  border-radius: 12px;
  padding: 12px 8px;
  text-align: center;
}
.cm-stat b {
  display: block;
  font-size: 22px;
  line-height: 1.2;
}
.cm-stat span {
  font-size: 14px;
  color: var(--cm-muted);
}
.cm-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
  margin-bottom: 12px;
  -webkit-overflow-scrolling: touch;
}
.cm-tab {
  flex: 0 0 auto;
  border: 1px solid var(--cm-line);
  background: rgba(255,255,255,0.55);
  color: var(--cm-ink);
  border-radius: 999px;
  padding: 10px 16px;
  font-size: 16px;
  font-family: inherit;
  cursor: pointer;
}
.cm-tab.active {
  background: var(--cm-ink);
  color: #fff;
  border-color: var(--cm-ink);
}
.cm-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
}
.cm-cell {
  position: relative;
  aspect-ratio: 1;
  border-radius: 50%;
  border: 2px solid rgba(0,0,0,0.14);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 4px;
  box-shadow: 0 4px 10px rgba(28,36,48,0.08);
  transition: transform .12s ease;
}
.cm-cell:active { transform: scale(0.96); }
.cm-cell.mine { border-color: #111; border-width: 3px; }
.cm-cell.sale { box-shadow: 0 0 0 2px var(--cm-sale); }

/* 공커색: 눈에 덜 띄게 */
.cm-cell.couple {
  opacity: 0.42;
  filter: grayscale(0.55) saturate(0.45) brightness(0.96);
  box-shadow: none;
  border-color: rgba(0,0,0,0.08);
  transform: scale(0.94);
  z-index: 0;
}
/* 공커라도 판매중이면 판매 표시는 강조 */
.cm-cell.couple.sale {
  opacity: 0.88;
  filter: saturate(0.95) brightness(1);
  box-shadow: 0 0 0 3px var(--cm-sale), 0 4px 12px rgba(180, 83, 9, 0.35);
  border-color: var(--cm-sale);
  transform: scale(1.02);
  z-index: 2;
}
.cm-cell.couple .badge {
  opacity: 0.45;
  transform: scale(0.85);
}
.cm-cell.couple.sale .badge {
  opacity: 1;
  transform: scale(1.05);
  background: var(--cm-sale);
  font-size: 11px;
  font-weight: 800;
  padding: 3px 7px;
  box-shadow: 0 2px 8px rgba(180, 83, 9, 0.45);
}
.cm-cell.couple .owners span {
  font-size: 11px;
  opacity: 0.75;
  font-weight: 600;
}
.cm-cell.couple.sale .owners span {
  opacity: 0.95;
  font-weight: 700;
}
.cm-cell.couple .num {
  opacity: 0.45;
  font-size: 10px;
}
.cm-cell.couple.sale .num {
  opacity: 0.8;
}

/* 구매불가: 더 조용하게 */
.cm-cell.locked {
  opacity: 0.32;
  filter: grayscale(0.75) brightness(0.9);
  box-shadow: none !important;
  border: 2px solid rgba(0,0,0,0.08);
  transform: scale(0.92);
  z-index: 0;
  animation: none !important;
}
.cm-cell.locked.empty {
  /* 무주인이어도 잠기면 선점 강조 끄기 */
  border: 2px solid rgba(0,0,0,0.08);
  box-shadow: none !important;
  transform: scale(0.92);
  filter: grayscale(0.75) brightness(0.9);
}
.cm-cell.locked .badge,
.cm-cell.locked .badge-empty {
  display: none;
}
.cm-cell.locked .badge-lock {
  position: absolute;
  bottom: -3px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(55, 65, 81, 0.55);
  color: rgba(255,255,255,0.85);
  font-size: 8px;
  font-weight: 600;
  padding: 1px 5px;
  border-radius: 999px;
  line-height: 1.2;
  white-space: nowrap;
  z-index: 2;
  opacity: 0.7;
}
.cm-cell.locked .owners span,
.cm-cell.locked .num {
  opacity: 0.55;
}

/* 1번 예약색: 선점된 것처럼 보이되 거래 불가 */
.cm-cell.reserved {
  filter: saturate(0.95);
  border: 2px solid rgba(55, 65, 81, 0.28);
  cursor: pointer;
}
.cm-cell.reserved.empty,
.cm-cell.reserved.sale {
  animation: none !important;
  box-shadow: none !important;
  transform: none;
  border: 2px solid rgba(55, 65, 81, 0.28);
}
.cm-cell.reserved .badge-empty,
.cm-cell.reserved .badge,
.cm-cell.reserved .badge-lock {
  display: none;
}
.cm-cell.reserved .badge-reserved {
  position: absolute;
  top: -5px;
  left: 50%;
  transform: translateX(-50%);
  background: #4b5563;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 999px;
  line-height: 1.2;
  white-space: nowrap;
  z-index: 2;
  box-shadow: 0 2px 6px rgba(55, 65, 81, 0.28);
}
.cm-cell.reserved.newbie {
  border: 3px solid #2563eb;
  box-shadow:
    0 0 0 3px rgba(37, 99, 235, 0.22),
    0 6px 16px rgba(37, 99, 235, 0.28);
  transform: scale(1.04);
  z-index: 1;
  filter: saturate(1.1) brightness(1.04);
}
.cm-cell.reserved.newbie .badge-reserved {
  background: #2563eb;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
}
.cm-cell.reserved.newbie .owners span {
  color: #1e3a8a;
  font-weight: 800;
}

.cm-btn.lock {
  background: #374151;
  color: #fff;
}
.cm-btn.unlock {
  background: #6b7280;
  color: #fff;
}

/* 주인 있는 일반 색: 차분하게 (공커·잠금은 각자 스타일 유지) */
.cm-cell:not(.empty):not(.couple):not(.locked) {
  filter: saturate(0.92);
}
.cm-cell:not(.empty):not(.couple):not(.locked) .owners span {
  opacity: 0.95;
}

/* 무주인 색 */
.cm-cell.empty:not(.locked) {
  border: 3px dashed #e11d48;
  box-shadow:
    0 0 0 3px rgba(225, 29, 72, 0.22),
    0 6px 16px rgba(225, 29, 72, 0.28);
  transform: scale(1.04);
  z-index: 1;
  animation: cm-empty-pulse 1.6s ease-in-out infinite;
  filter: saturate(1.15) brightness(1.05);
}
.cm-cell.empty:not(.locked):active { transform: scale(0.98); }
.cm-cell.empty:not(.locked) .num {
  opacity: 1;
  font-size: 12px;
  color: #9f1239;
}
.cm-cell.empty:not(.locked) .owners span {
  font-size: 13px;
  font-weight: 800;
  color: #9f1239;
  text-shadow: 0 1px 0 rgba(255,255,255,0.7);
}
.cm-cell.empty:not(.locked) .badge-empty {
  position: absolute;
  top: -5px;
  left: 50%;
  transform: translateX(-50%);
  background: #e11d48;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 999px;
  line-height: 1.2;
  white-space: nowrap;
  box-shadow: 0 2px 6px rgba(225, 29, 72, 0.35);
}
@keyframes cm-empty-pulse {
  0%, 100% {
    box-shadow:
      0 0 0 3px rgba(225, 29, 72, 0.22),
      0 6px 16px rgba(225, 29, 72, 0.28);
  }
  50% {
    box-shadow:
      0 0 0 5px rgba(225, 29, 72, 0.35),
      0 8px 20px rgba(225, 29, 72, 0.4);
  }
}
@media (prefers-reduced-motion: reduce) {
  .cm-cell.empty:not(.locked) { animation: none; }
}
.cm-cell .num {
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  opacity: 0.75;
  margin-bottom: 1px;
  text-shadow: 0 1px 0 rgba(255,255,255,0.45);
}
.cm-cell .owners {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0;
  max-width: 92%;
  line-height: 1.15;
}
.cm-cell .owners span {
  font-size: 14px;
  font-weight: 700;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  letter-spacing: -0.02em;
  text-shadow:
    0 1px 0 rgba(255,255,255,0.55),
    0 0 6px rgba(255,255,255,0.35);
}
.cm-cell.couple .owners span { font-size: 11px; }
.cm-cell.dark .owners span {
  color: #fff;
  text-shadow:
    0 1px 2px rgba(0,0,0,0.55),
    0 0 6px rgba(0,0,0,0.35);
}
.cm-cell .badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: var(--cm-sale);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 5px;
  border-radius: 999px;
  line-height: 1.2;
}
.cm-cell.dark .num {
  color: #fff;
  opacity: 0.9;
  text-shadow: 0 1px 2px rgba(0,0,0,0.45);
}

.cm-log {
  margin-top: 18px;
}
.cm-log h2 {
  margin: 0 0 8px;
  font-size: 18px;
}
.cm-log-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--cm-line);
  font-size: 16px;
}
.cm-log-dot {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,0.15);
  flex-shrink: 0;
}
.cm-log-meta { color: var(--cm-muted); margin-top: 2px; font-size: 13px; }

.cm-sheet-bg {
  position: fixed;
  inset: 0;
  background: rgba(20, 24, 30, 0.45);
  opacity: 0;
  pointer-events: none;
  transition: opacity .2s ease;
  z-index: 40;
}
.cm-sheet-bg.open {
  opacity: 1;
  pointer-events: auto;
}
.cm-sheet {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 50;
  max-width: 480px;
  margin: 0 auto;
  background: var(--cm-sheet);
  border-radius: 20px 20px 0 0;
  padding: 10px 16px calc(18px + env(safe-area-inset-bottom));
  transform: translateY(110%);
  transition: transform .22s ease;
  box-shadow: 0 -10px 40px rgba(0,0,0,0.18);
}
.cm-sheet.open { transform: translateY(0); }
.cm-sheet-handle {
  width: 42px;
  height: 4px;
  border-radius: 999px;
  background: rgba(0,0,0,0.15);
  margin: 0 auto 12px;
}
.cm-sheet-head {
  display: flex;
  gap: 14px;
  align-items: center;
  margin-bottom: 14px;
}
.cm-sheet-swatch {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  border: 2px solid rgba(0,0,0,0.12);
  flex-shrink: 0;
}
.cm-sheet-info h3 {
  margin: 0;
  font-size: 26px;
  letter-spacing: -0.02em;
}
.cm-sheet-info p {
  margin: 6px 0 0;
  font-size: 17px;
  color: var(--cm-muted);
  line-height: 1.4;
}
.cm-price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 14px;
  border-radius: 12px;
  background: rgba(255,255,255,0.7);
  border: 1px solid var(--cm-line);
  margin-bottom: 12px;
}
.cm-price-row label { font-size: 16px; color: var(--cm-muted); }
.cm-price-row strong { font-size: 22px; color: var(--cm-accent); }
.cm-input {
  width: 100%;
  border: 1px solid var(--cm-line);
  border-radius: 12px;
  padding: 14px 14px;
  font-size: 18px;
  font-family: inherit;
  margin-bottom: 8px;
  background: #fff;
}
.cm-price-preview {
  margin: 0 0 10px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(196, 92, 38, 0.08);
  color: var(--cm-accent);
  font-size: 16px;
  font-weight: 700;
  word-break: break-all;
}
.cm-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}
.cm-chip {
  border: 1px solid var(--cm-line);
  background: #fff;
  color: var(--cm-ink);
  border-radius: 999px;
  padding: 8px 11px;
  font-size: 14px;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  line-height: 1.2;
}
.cm-chip:active { background: #f3f4f6; }
.cm-chip.clear {
  background: transparent;
  color: var(--cm-muted);
  font-weight: 600;
}
.cm-price-help {
  margin: 0 0 10px;
  font-size: 12px;
  color: var(--cm-muted);
  line-height: 1.4;
}
.cm-actions {
  display: grid;
  gap: 8px;
}
.cm-btn {
  border: 0;
  border-radius: 14px;
  padding: 16px 12px;
  font-size: 18px;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
}
.cm-btn.primary { background: var(--cm-ink); color: #fff; }
.cm-btn.accent { background: var(--cm-accent); color: #fff; }
.cm-btn.ok { background: var(--cm-ok); color: #fff; }
.cm-btn.ghost {
  background: transparent;
  color: var(--cm-muted);
  border: 1px solid var(--cm-line);
}
.cm-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.cm-list-btn {
  display: inline-block;
  margin-top: 10px;
  background: var(--cm-ink);
  color: #fff;
  text-decoration: none;
  font-weight: 800;
  font-size: 15px;
  border-radius: 999px;
  padding: 8px 14px;
}
.cm-links {
  margin-top: 16px;
  text-align: center;
  font-size: 14px;
}
.cm-links a { color: var(--cm-muted); margin: 0 6px; }
.hidden { display: none !important; }
</style>
</head>
<body>
<div class="cm-wrap">
  <div class="cm-top">
    <div>
      <h1 class="cm-title">색표마켓</h1>
      <p class="cm-sub">1~45 색을 본방냥 고정가로 사고팔아요</p>
      <a class="cm-list-btn" href="/page/color_list.php<?= $code !== '' ? ('?code=' . rawurlencode($code)) : '' ?>">🎨 색표 보기</a>
    </div>
    <div class="cm-user">
      <?php if ($로그인) { ?>
        <div class="cm-nick"><?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cm-point" id="my-point"><?= htmlspecialchars(cm_냥표시($포인트), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cm-point" id="my-game-point" style="opacity:.85;font-size:12px;"><?= htmlspecialchars($게임냥표시, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cm-point" id="my-saekbyeon" style="opacity:.85;font-size:12px;">색변 <?= (int)$색변보유 ?>개</div>
      <?php } else { ?>
        <div class="cm-nick">비로그인</div>
        <div class="cm-point">코드 필요</div>
      <?php } ?>
    </div>
  </div>

  <?php if (!$로그인) { ?>
    <p class="cm-hint">연구실에서 발급받은 <strong>코드 링크</strong>로 들어와야 구매·판매할 수 있어요. 구경은 가능해요.</p>
  <?php } else { ?>
    <p class="cm-hint">본방냥으로 색을 사고팔아요. 판매 수수료 <strong><?= $feePct ?>%</strong>는 금고로 가요. 최소 <strong><?= htmlspecialchars(cm_냥표시(COLOR_MARKET_MIN_PRICE), ENT_QUOTES, 'UTF-8') ?></strong> · 판매가 상한 <strong><?= htmlspecialchars($본방총합표시, ENT_QUOTES, 'UTF-8') ?></strong>. 무주인 색 선점은 <strong>색변 아이템 1개</strong>가 필요해요. 색변이 없으면 게임냥 시세로 <strong>자동 구매 후 선점</strong>돼요. 내가 선점한 색만 판매가를 올릴 수 있어요.</p>
  <?php } ?>

  <div class="cm-stats">
    <div class="cm-stat"><b id="stat-sale"><?= (int)$판매중수 ?></b><span>판매중</span></div>
    <div class="cm-stat"><b id="stat-empty"><?= (int)$무주인수 ?></b><span>무주인</span></div>
    <div class="cm-stat"><b id="stat-mine"><?= (int)$내색수 ?></b><span>내 색</span></div>
  </div>

  <div class="cm-tabs" id="tabs">
    <button type="button" class="cm-tab active" data-filter="all">전체</button>
    <button type="button" class="cm-tab" data-filter="sale">판매중</button>
    <button type="button" class="cm-tab" data-filter="mine">내 색</button>
    <button type="button" class="cm-tab" data-filter="empty">무주인</button>
  </div>

  <div class="cm-grid" id="grid">
    <?php foreach ($목록 as $it) {
      $n = (int)$it['color_num'];
      $hex = $it['hex'];
      $owner = $it['owner_nick'];
      $표시닉 = $it['display_nicks'] ?? [];
      if (empty($표시닉) && $owner !== '') {
        $표시닉 = [$owner];
      }
      $listed = (int)$it['listed'] === 1;
      $locked = (int)($it['trade_lock'] ?? 0) === 1;
      $isReserved = !empty($it['reserved']) || $n === 1;
      $reservedLabel = $isReserved ? (string)($it['reserved_label'] ?? '불가') : '';
      if ($isReserved) {
        if ($reservedLabel === '') {
          $reservedLabel = '불가';
        }
        $owner = $reservedLabel;
        $표시닉 = [$reservedLabel];
        $listed = false;
        $locked = false;
        $isMine = false;
      }
      $isMine = $isReserved ? false : ($로그인 && cm_내색인가($닉, $owner, $it['wearers'] ?? []));
      $isEmpty = $isReserved ? false : ($owner === '' && empty($표시닉));
      $isCouple = !$isReserved && count($표시닉) >= 2;
      $isDark = in_array($n, [45], true) || strtolower($hex) === '#000' || strtolower($hex) === '#000000';
      $cls = 'cm-cell';
      if ($isMine) $cls .= ' mine';
      if ($listed) $cls .= ' sale';
      if ($isEmpty) $cls .= ' empty';
      if ($locked) $cls .= ' locked';
      if ($isCouple) $cls .= ' couple';
      if ($isDark) $cls .= ' dark';
      if ($isReserved) {
        $cls .= ' reserved';
        if ($reservedLabel === '신입') {
          $cls .= ' newbie';
        }
      }
      $filter = 'all';
      if ($listed) $filter .= ' sale';
      if ($isMine) $filter .= ' mine';
      if ($isEmpty) $filter .= ' empty';
      $nicksAttr = htmlspecialchars(implode('|', $표시닉), ENT_QUOTES, 'UTF-8');
    ?>
    <button
      type="button"
      class="<?= $cls ?>"
      style="background:<?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>"
      data-num="<?= $n ?>"
      data-filter="<?= trim($filter) ?>"
      data-owner="<?= htmlspecialchars($owner, ENT_QUOTES, 'UTF-8') ?>"
      data-nicks="<?= $nicksAttr ?>"
      data-price="<?= htmlspecialchars($isReserved ? '0' : $it['price'], ENT_QUOTES, 'UTF-8') ?>"
      data-listed="<?= $listed ? '1' : '0' ?>"
      data-lock="<?= $locked ? '1' : '0' ?>"
      data-reserved="<?= $isReserved ? '1' : '0' ?>"
      data-reserved-label="<?= htmlspecialchars($reservedLabel, ENT_QUOTES, 'UTF-8') ?>"
      data-hex="<?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>"
      aria-label="<?= $n ?>번 색"
    >
      <?php if ($isReserved) { ?><span class="badge-reserved"><?= htmlspecialchars($reservedLabel, ENT_QUOTES, 'UTF-8') ?></span><?php } ?>
      <?php if ($listed) { ?><span class="badge">판매</span><?php } ?>
      <?php if ($isEmpty && !$listed) { ?><span class="badge-empty">선점</span><?php } ?>
      <?php if ($locked) { ?><span class="badge-lock">구매불가</span><?php } ?>
      <span class="num">#<?= $n ?></span>
      <span class="owners">
        <?php if ($isReserved) { ?>
          <span><?= htmlspecialchars($reservedLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?php } else if ($isEmpty) { ?>
          <span><?= $listed ? '판매중' : '비어있음' ?></span>
        <?php } else { foreach ($표시닉 as $dn) { ?>
          <span><?= htmlspecialchars($dn, ENT_QUOTES, 'UTF-8') ?></span>
        <?php } } ?>
      </span>
    </button>
    <?php } ?>
  </div>

  <?php if (!empty($최근)) { ?>
  <section class="cm-log">
    <h2>최근 거래</h2>
    <?php foreach ($최근 as $log) {
      $act = $log['action'];
      if ($act === 'buy') {
        $txt = htmlspecialchars($log['buyer_nick'], ENT_QUOTES, 'UTF-8') . ' ← #' . (int)$log['color_num']
          . ' · ' . htmlspecialchars($log['price_disp'], ENT_QUOTES, 'UTF-8');
      } elseif ($act === 'claim') {
        $txt = htmlspecialchars($log['buyer_nick'], ENT_QUOTES, 'UTF-8') . ' 선점 #' . (int)$log['color_num'];
      } elseif ($act === 'list') {
        $txt = htmlspecialchars($log['seller_nick'], ENT_QUOTES, 'UTF-8') . ' 판매등록 #' . (int)$log['color_num']
          . ' · ' . htmlspecialchars($log['price_disp'], ENT_QUOTES, 'UTF-8');
      } else {
        $txt = htmlspecialchars($log['seller_nick'], ENT_QUOTES, 'UTF-8') . ' 판매철회 #' . (int)$log['color_num'];
      }
    ?>
    <div class="cm-log-item">
      <div class="cm-log-dot" style="background:<?= htmlspecialchars($log['hex'], ENT_QUOTES, 'UTF-8') ?>"></div>
      <div>
        <div><?= $txt ?></div>
        <div class="cm-log-meta"><?= htmlspecialchars($log['regdate'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
    <?php } ?>
  </section>
  <?php } ?>

  <div class="cm-links">
    <a href="/page/color_list.php<?= $code !== '' ? ('?code=' . rawurlencode($code)) : '' ?>">색표 보기</a>
  </div>
</div>

<div class="cm-sheet-bg" id="sheet-bg"></div>
<div class="cm-sheet" id="sheet" role="dialog" aria-modal="true">
  <div class="cm-sheet-handle"></div>
  <div class="cm-sheet-head">
    <div class="cm-sheet-swatch" id="sheet-swatch"></div>
    <div class="cm-sheet-info">
      <h3 id="sheet-title">#0</h3>
      <p id="sheet-desc">-</p>
    </div>
  </div>

  <div class="cm-price-row" id="sheet-price-row">
    <label>판매가</label>
    <strong id="sheet-price">-</strong>
  </div>

  <div id="sheet-owner-tools" class="hidden">
    <input type="text" class="cm-input" id="list-price" inputmode="text" autocomplete="off" placeholder="예: 1만 / 5천 / 1000">
    <div class="cm-price-preview" id="list-price-preview">합계 0본방냥</div>
    <p class="cm-price-help">버튼을 누르면 금액이 계속 더해져요. 판매가는 본방냥 총합을 넘을 수 없어요.</p>
    <div class="cm-chip-row" id="price-chips">
      <button type="button" class="cm-chip" data-add="100">100</button>
      <button type="button" class="cm-chip" data-add="500">500</button>
      <button type="button" class="cm-chip" data-add="1000">1천</button>
      <button type="button" class="cm-chip" data-add="5000">5천</button>
      <button type="button" class="cm-chip" data-add="10000">1만</button>
      <button type="button" class="cm-chip" data-add="50000">5만</button>
      <button type="button" class="cm-chip" data-add="100000">10만</button>
      <button type="button" class="cm-chip" data-add="500000">50만</button>
      <button type="button" class="cm-chip" data-add="1000000">100만</button>
      <button type="button" class="cm-chip clear" data-clear="1">초기화</button>
    </div>
  </div>

  <div class="cm-actions" id="sheet-actions"></div>
</div>

<script>
(function () {
  var CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  var NICK = <?= json_encode($닉, JSON_UNESCAPED_UNICODE) ?>;
  var LOGGED = <?= $로그인 ? 'true' : 'false' ?>;
  var IS_ADMIN = <?= $준호 ? 'true' : 'false' ?>;
  var MIN_PRICE = <?= json_encode((string)COLOR_MARKET_MIN_PRICE) ?>;
  var MAX_PRICE = <?= json_encode((string)$본방총합) ?>;
  var FEE_PCT = <?= (int)$feePct ?>;
  var ITEM_NAME = <?= json_encode(COLOR_MARKET_CLAIM_ITEM, JSON_UNESCAPED_UNICODE) ?>;
  var ITEM_QTY = <?= (int)$색변보유 ?>;
  var ITEM_QUOTE = <?= json_encode($색변시세, JSON_UNESCAPED_UNICODE) ?>;
  var RESERVED_NUM = 1;
  var busy = false;
  var current = null;

  var grid = document.getElementById('grid');
  var sheet = document.getElementById('sheet');
  var sheetBg = document.getElementById('sheet-bg');
  var actions = document.getElementById('sheet-actions');
  var ownerTools = document.getElementById('sheet-owner-tools');

  var listPriceInput = document.getElementById('list-price');
  var listPricePreview = document.getElementById('list-price-preview');
  var priceDigits = '0';

  var UNIT_TABLE = [
    ['해', '100000000000000000000'],
    ['천경', '10000000000000000000'],
    ['경', '10000000000000000'],
    ['천조', '1000000000000000'],
    ['조', '1000000000000'],
    ['천억', '100000000000'],
    ['백억', '10000000000'],
    ['십억', '1000000000'],
    ['억', '100000000'],
    ['만', '10000']
  ];

  function onlyDigits(s) {
    return String(s || '').replace(/[^\d]/g, '') || '0';
  }

  function stripZeros(s) {
    s = onlyDigits(s);
    return s.replace(/^0+/, '') || '0';
  }

  function addDigits(a, b) {
    a = stripZeros(a);
    b = stripZeros(b);
    if (a === '0') return b;
    if (b === '0') return a;
    var aa = a.split('').reverse();
    var bb = b.split('').reverse();
    var len = Math.max(aa.length, bb.length);
    var carry = 0;
    var out = [];
    for (var i = 0; i < len; i++) {
      var sum = carry + (parseInt(aa[i] || '0', 10)) + (parseInt(bb[i] || '0', 10));
      out.push(String(sum % 10));
      carry = Math.floor(sum / 10);
    }
    if (carry) out.push(String(carry));
    return stripZeros(out.reverse().join(''));
  }

  function mulDigits(a, b) {
    a = stripZeros(a);
    b = stripZeros(b);
    if (a === '0' || b === '0') return '0';
    if (/^1(0+)$/.test(b)) return stripZeros(a + b.slice(1));
    if (/^1(0+)$/.test(a)) return stripZeros(b + a.slice(1));
    var aa = a.split('').map(Number).reverse();
    var bb = b.split('').map(Number).reverse();
    var res = new Array(aa.length + bb.length).fill(0);
    for (var i = 0; i < aa.length; i++) {
      for (var j = 0; j < bb.length; j++) {
        var t = res[i + j] + aa[i] * bb[j];
        res[i + j] = t % 10;
        res[i + j + 1] += Math.floor(t / 10);
      }
    }
    return stripZeros(res.reverse().join(''));
  }

  function cmpDigits(a, b) {
    a = stripZeros(a);
    b = stripZeros(b);
    if (a.length !== b.length) return a.length > b.length ? 1 : -1;
    if (a === b) return 0;
    return a > b ? 1 : -1;
  }

  function parseKoreanAmount(text) {
    var raw = String(text || '').trim().replace(/[, ]/g, '').replace(/냥|원/g, '');
    if (!raw) return '0';
    if (/^\d+$/.test(raw)) return stripZeros(raw);

    var total = '0';
    var i = 0;
    while (i < raw.length) {
      var num = '';
      while (i < raw.length && /\d/.test(raw[i])) {
        num += raw[i];
        i++;
      }
      if (!num) num = '1';

      var matched = null;
      for (var u = 0; u < UNIT_TABLE.length; u++) {
        var name = UNIT_TABLE[u][0];
        if (raw.slice(i, i + name.length) === name) {
          matched = UNIT_TABLE[u];
          break;
        }
      }
      if (matched) {
        total = addDigits(total, mulDigits(num, matched[1]));
        i += matched[0].length;
      } else if (/^\d+$/.test(num) && i >= raw.length) {
        total = addDigits(total, stripZeros(num));
      } else {
        // 알 수 없는 단위면 숫자만 추출 시도
        var digitsOnly = onlyDigits(raw);
        return digitsOnly === '0' ? '0' : stripZeros(digitsOnly);
      }
    }
    return total;
  }

  function fmtShort(n) {
    n = stripZeros(n);
    if (n === '0') return '0본방냥';
    var units = [
      { name: '억', val: '100000000' },
      { name: '만', val: '10000' }
    ];
    var parts = [];
    var rest = n;
    for (var i = 0; i < units.length; i++) {
      var u = units[i];
      if (cmpDigits(rest, u.val) < 0) continue;
      var q;
      if (/^1(0+)$/.test(u.val)) {
        var zeros = u.val.length - 1;
        if (rest.length <= zeros) continue;
        q = stripZeros(rest.slice(0, rest.length - zeros));
        rest = stripZeros(rest.slice(rest.length - zeros)) || '0';
      } else {
        break;
      }
      if (q !== '0') parts.push(q + u.name);
    }
    if (rest !== '0') {
      try {
        parts.push(Number(rest).toLocaleString('ko-KR'));
      } catch (e) {
        parts.push(rest);
      }
    }
    return (parts.join('') || '0') + '본방냥';
  }

  function fmt(n) {
    return fmtShort(n);
  }

  function syncPricePreview() {
    var typed = listPriceInput.value;
    priceDigits = parseKoreanAmount(typed);
    if (priceDigits === '0' && typed.trim() === '') {
      listPricePreview.textContent = '합계 0본방냥';
      return;
    }
    var msg = '합계 ' + fmtShort(priceDigits);
    if (MAX_PRICE && MAX_PRICE !== '0' && cmpDigits(priceDigits, MAX_PRICE) > 0) {
      msg += ' · 상한 초과!';
    }
    listPricePreview.textContent = msg;
  }

  function setPriceDigits(digits, rewriteInput) {
    priceDigits = stripZeros(digits);
    if (rewriteInput) {
      listPriceInput.value = priceDigits === '0' ? '' : priceDigits;
    }
    syncPricePreview();
  }

  function parseNicks(raw) {
    if (!raw) return [];
    return String(raw).split('|').map(function (s) { return s.trim(); }).filter(Boolean);
  }

  function isReservedNum(num) {
    return parseInt(num, 10) === RESERVED_NUM;
  }

  function other44Full() {
    var full = true;
    grid.querySelectorAll('.cm-cell').forEach(function (el) {
      var n = parseInt(el.getAttribute('data-num'), 10);
      if (isReservedNum(n)) return;
      var owner = el.getAttribute('data-owner') || '';
      var nicks = parseNicks(el.getAttribute('data-nicks'));
      if (!owner && nicks.length === 0) full = false;
    });
    return full;
  }

  function reservedLabel() {
    return other44Full() ? '신입' : '불가';
  }

  function ensureReservedBadge(el, label) {
    var badge = el.querySelector('.badge-reserved');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge-reserved';
      el.appendChild(badge);
    }
    badge.textContent = label;
    var emptyBadge = el.querySelector('.badge-empty');
    if (emptyBadge) emptyBadge.remove();
    var saleBadge = el.querySelector('.badge');
    if (saleBadge) saleBadge.remove();
    var lockBadge = el.querySelector('.badge-lock');
    if (lockBadge) lockBadge.remove();
  }

  function syncReservedCell() {
    var el = grid.querySelector('[data-num="' + RESERVED_NUM + '"]');
    if (!el) return;
    var label = reservedLabel();
    el.setAttribute('data-owner', label);
    el.setAttribute('data-nicks', label);
    el.setAttribute('data-listed', '0');
    el.setAttribute('data-lock', '0');
    el.setAttribute('data-price', '0');
    el.setAttribute('data-reserved', '1');
    el.setAttribute('data-reserved-label', label);
    el.setAttribute('data-filter', 'all');
    el.classList.remove('empty', 'sale', 'mine', 'couple', 'locked');
    el.classList.add('reserved');
    el.classList.toggle('newbie', label === '신입');
    ensureReservedBadge(el, label);
    renderOwners(el, [label], false, false);
  }

  function isMineNicks(nicks, owner) {
    if (!LOGGED) return false;
    if (owner && owner === NICK) return true;
    return nicks.indexOf(NICK) >= 0;
  }

  function closeSheet() {
    sheet.classList.remove('open');
    sheetBg.classList.remove('open');
    current = null;
  }

  function openSheet(item) {
    current = item;
    document.getElementById('sheet-swatch').style.background = item.hex;
    document.getElementById('sheet-title').textContent = '#' + item.num;
    var nicks = item.nicks || [];
    var mine = isMineNicks(nicks, item.owner);
    var reserved = isReservedNum(item.num);
    var empty = !reserved && !item.owner && nicks.length === 0;
    var locked = !reserved && item.lock === 1;
    var desc = '';
    if (reserved) {
      var rLabel = reservedLabel();
      desc = rLabel === '신입'
        ? '신입색 · 장터 거래·선점 불가'
        : '예약색 · 장터 거래·선점 불가';
    } else if (locked) {
      desc = '구매불가 · 사고팔기 불가';
      if (nicks.length) desc += ' · ' + nicks.join(' · ');
    } else if (empty) {
      if (item.listed === 1 && item.price && item.price !== '0') {
        desc = '무주인 · 판매중 (구매 시 소유)';
      } else if (ITEM_QTY > 0) {
        desc = '무주인 · 색변 ' + ITEM_QTY + '개 보유 · 선점 가능';
      } else {
        desc = '무주인 · 색변 없으면 게임냥으로 자동 구매 후 선점';
      }
    } else {
      var who = nicks.length ? nicks.join(' · ') : item.owner;
      if (nicks.length >= 2) {
        desc = '공커 ' + who;
      } else {
        desc = '주인 ' + who;
      }
      desc += item.listed === 1 ? ' · 판매중' : ' · 판매 안 함';
      if (mine) desc += ' (내 색)';
    }
    document.getElementById('sheet-desc').textContent = desc;

    var priceRow = document.getElementById('sheet-price-row');
    if (!locked && item.listed === 1 && item.price && item.price !== '0') {
      priceRow.classList.remove('hidden');
      document.getElementById('sheet-price').textContent = fmt(item.price);
    } else {
      priceRow.classList.add('hidden');
    }

    actions.innerHTML = '';
    ownerTools.classList.add('hidden');

    var html = '';
    var emptyListed = empty && item.listed === 1 && item.price && item.price !== '0';
    if (reserved) {
      html = '<button type="button" class="cm-btn ghost" disabled>#' + item.num + ' ' + reservedLabel() + ' · 사고팔기·선점 불가</button>';
    } else if (!LOGGED) {
      html = '<button type="button" class="cm-btn ghost" disabled>코드 링크로 접속하면 거래할 수 있어요</button>';
    } else if (locked) {
      if (mine) {
        html += '<button type="button" class="cm-btn primary" data-act="wear">프로필에 착용</button>';
      }
      html += '<button type="button" class="cm-btn ghost" disabled>구매불가 색 · 사고팔기 불가</button>';
    } else if (empty) {
      if (emptyListed) {
        html += '<button type="button" class="cm-btn accent" data-act="buy">구매하기 · ' + fmt(item.price) + '</button>';
      } else if (ITEM_QTY > 0) {
        html += '<div style="margin:0 0 8px;font-size:14px;color:var(--cm-muted);line-height:1.45;">색변 보유 <strong style="color:var(--cm-ok)">' + ITEM_QTY + '개</strong></div>';
        html += '<button type="button" class="cm-btn ok" data-act="claim">선점하기(색변 아이템1개 사용)</button>';
      } else {
        var qOk = ITEM_QUOTE && ITEM_QUOTE.ok;
        var qDisp = qOk ? (ITEM_QUOTE.total_disp || '-') : ((ITEM_QUOTE && ITEM_QUOTE.msg) || '시세 없음');
        html += '<div style="margin:0 0 8px;padding:10px 12px;border-radius:12px;background:rgba(196,92,38,.08);border:1px solid rgba(196,92,38,.18);font-size:14px;line-height:1.45;">';
        html += '<div style="font-weight:800;margin-bottom:4px;">색변 아이템 없음</div>';
        html += '<div>선점 시 게임냥 <strong style="color:var(--cm-accent)">' + qDisp + '</strong>으로 색변 1개를 자동 구매해요.</div>';
        html += '</div>';
        if (qOk) {
          html += '<button type="button" class="cm-btn ok" data-act="claim">선점하기 · ' + qDisp + '</button>';
        } else {
          html += '<button type="button" class="cm-btn ghost" disabled>지금은 구매할 수 없어요</button>';
        }
      }
      // 선점 가능(무주인) 상태에서는 금액 입력/선택 UI 숨김 — 내 색일 때만 표시
    } else if (mine) {
      ownerTools.classList.remove('hidden');
      if (item.listed === 1 && item.price && item.price !== '0') {
        setPriceDigits(item.price, true);
      } else {
        setPriceDigits('0', true);
      }
      html += '<button type="button" class="cm-btn accent" data-act="list">판매가 등록/수정</button>';
      if (item.listed === 1) {
        html += '<button type="button" class="cm-btn ghost" data-act="unlist">판매 철회</button>';
      }
      html += '<button type="button" class="cm-btn primary" data-act="wear">프로필에 착용</button>';
    } else if (item.listed === 1) {
      html = '<button type="button" class="cm-btn accent" data-act="buy">구매하기 · ' + fmt(item.price) + '</button>';
    } else {
      html = '<button type="button" class="cm-btn ghost" disabled>주인이 판매가를 올리지 않았어요</button>';
    }

    // 민호만: 구매불가 토글 (1번 예약색 제외)
    if (IS_ADMIN && !reserved) {
      if (locked) {
        html += '<button type="button" class="cm-btn unlock" data-act="unlock">구매불가 해제</button>';
      } else {
        html += '<button type="button" class="cm-btn lock" data-act="lock">구매불가 지정</button>';
      }
    }

    actions.innerHTML = html;
    if (!locked && !mine && item.listed === 1) {
      var buyBtn = actions.querySelector('[data-act="buy"]');
      if (buyBtn) {
        buyBtn.dataset.feeHint = emptyListed
          ? '\\n(무주인 색 · 대금 전액 금고 · 구매자 소유)'
          : (FEE_PCT > 0 ? '\\n(판매 수수료 ' + FEE_PCT + '%는 금고 · 공커면 반반)' : '');
      }
    }

    sheet.classList.add('open');
    sheetBg.classList.add('open');
  }

  function readCell(btn) {
    return {
      num: parseInt(btn.getAttribute('data-num'), 10),
      owner: btn.getAttribute('data-owner') || '',
      nicks: parseNicks(btn.getAttribute('data-nicks')),
      price: btn.getAttribute('data-price') || '0',
      listed: parseInt(btn.getAttribute('data-listed'), 10) || 0,
      lock: parseInt(btn.getAttribute('data-lock'), 10) || 0,
      reserved: btn.getAttribute('data-reserved') === '1',
      hex: btn.getAttribute('data-hex') || '#ccc',
      el: btn
    };
  }

  function renderOwners(el, nicks, isEmpty, listed) {
    var box = el.querySelector('.owners');
    if (!box) return;
    box.innerHTML = '';
    if (isEmpty) {
      var s = document.createElement('span');
      s.textContent = listed ? '판매중' : '비어있음';
      box.appendChild(s);
      return;
    }
    (nicks.length ? nicks : ['?']).forEach(function (n) {
      var s = document.createElement('span');
      s.textContent = n;
      box.appendChild(s);
    });
  }

  function applyItemToCell(item, el) {
    if (!el) {
      el = grid.querySelector('[data-num="' + item.color_num + '"]');
    }
    if (!el) return;
    var owner = item.owner_nick || '';
    var nicks = item.display_nicks || item.wearers || [];
    if (!nicks.length && owner) nicks = [owner];
    var listed = parseInt(item.listed, 10) === 1 ? 1 : 0;
    var locked = parseInt(item.trade_lock, 10) === 1 ? 1 : 0;
    var reserved = isReservedNum(item.color_num) || !!item.reserved;
    var price = String(item.price || '0');
    var isMine = !reserved && isMineNicks(nicks, owner);
    var isEmpty = !reserved && !owner && nicks.length === 0;
    if (reserved) {
      var label = item.reserved_label || reservedLabel();
      owner = label;
      nicks = [label];
      listed = 0;
      locked = 0;
      price = '0';
    }
    el.setAttribute('data-owner', owner);
    el.setAttribute('data-nicks', nicks.join('|'));
    el.setAttribute('data-price', price);
    el.setAttribute('data-listed', String(listed));
    el.setAttribute('data-lock', String(locked));
    el.setAttribute('data-reserved', reserved ? '1' : '0');
    el.classList.toggle('mine', isMine);
    el.classList.toggle('sale', listed === 1);
    el.classList.toggle('empty', isEmpty);
    el.classList.toggle('locked', locked === 1);
    el.classList.toggle('couple', !reserved && nicks.length >= 2);
    el.classList.toggle('reserved', reserved);
    el.classList.toggle('newbie', reserved && (item.reserved_label || reservedLabel()) === '신입');
    var filter = 'all';
    if (listed === 1) filter += ' sale';
    if (isMine) filter += ' mine';
    if (isEmpty) filter += ' empty';
    el.setAttribute('data-filter', filter.trim());

    var badge = el.querySelector('.badge');
    if (listed === 1) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge';
        badge.textContent = '판매';
        el.appendChild(badge);
      }
    } else if (badge) {
      badge.remove();
    }

    var emptyBadge = el.querySelector('.badge-empty');
    if (!reserved && isEmpty && listed !== 1) {
      if (!emptyBadge) {
        emptyBadge = document.createElement('span');
        emptyBadge.className = 'badge-empty';
        emptyBadge.textContent = '선점';
        el.appendChild(emptyBadge);
      }
    } else if (emptyBadge) {
      emptyBadge.remove();
    }

    var lockBadge = el.querySelector('.badge-lock');
    if (!reserved && locked === 1) {
      if (!lockBadge) {
        lockBadge = document.createElement('span');
        lockBadge.className = 'badge-lock';
        lockBadge.textContent = '구매불가';
        el.appendChild(lockBadge);
      }
    } else if (lockBadge) {
      lockBadge.remove();
    }

    if (reserved) {
      ensureReservedBadge(el, item.reserved_label || reservedLabel());
    }

    renderOwners(el, nicks, isEmpty, listed === 1);
  }

  function refreshStats() {
    var sale = 0, empty = 0, mine = 0;
    grid.querySelectorAll('.cm-cell').forEach(function (el) {
      if (el.getAttribute('data-reserved') === '1' || isReservedNum(el.getAttribute('data-num'))) return;
      var nicks = parseNicks(el.getAttribute('data-nicks'));
      var owner = el.getAttribute('data-owner') || '';
      if (el.getAttribute('data-listed') === '1') sale++;
      if (!owner && nicks.length === 0) empty++;
      if (isMineNicks(nicks, owner)) mine++;
    });
    document.getElementById('stat-sale').textContent = sale;
    document.getElementById('stat-empty').textContent = empty;
    document.getElementById('stat-mine').textContent = mine;
  }

  function post(action, extra) {
    if (busy) return;
    if (!LOGGED) {
      alert('코드 링크로 접속해주세요.');
      return;
    }
    busy = true;
    var body = new FormData();
    body.set('action', action);
    body.set('code', CODE);
    body.set('wallet_code', CODE);
    if (current) body.set('color_num', String(current.num));
    if (extra) {
      Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
    }
    fetch('/page/color_market_api.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        busy = false;
        if (!j || !j.ok) {
          alert((j && j.msg) || '처리 실패');
          if (j && j.quote) ITEM_QUOTE = j.quote;
          if (typeof j.item_qty === 'number') {
            ITEM_QTY = j.item_qty;
            var sq = document.getElementById('my-saekbyeon');
            if (sq) sq.textContent = '색변 ' + ITEM_QTY + '개';
          }
          return;
        }
        if (j.reload) {
          alert(j.msg || '완료');
          location.reload();
          return;
        }
        if (j.point_disp) {
          document.getElementById('my-point').textContent = j.point_disp;
        }
        if (typeof j.item_qty === 'number') {
          ITEM_QTY = j.item_qty;
          var sq2 = document.getElementById('my-saekbyeon');
          if (sq2) sq2.textContent = '색변 ' + ITEM_QTY + '개';
        }
        if (j.item) {
          applyItemToCell(j.item, current && current.el);
          syncReservedCell();
          refreshStats();
        }
        alert(j.msg || '완료');
        closeSheet();
      })
      .catch(function () {
        busy = false;
        alert('네트워크 오류');
      });
  }

  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('.cm-cell');
    if (!btn) return;
    openSheet(readCell(btn));
  });

  actions.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn || !current) return;
    var act = btn.getAttribute('data-act');
    if (act === 'buy') {
      if (!confirm('#' + current.num + ' 색을 ' + fmt(current.price) + '에 구매할까요?' + (btn.dataset.feeHint || ''))) return;
      post('buy');
    } else if (act === 'claim') {
      if (ITEM_QTY > 0) {
        if (!confirm('#' + current.num + ' 색을 선점할까요?\n색변 아이템 1개가 소진됩니다.')) return;
      } else {
        var qDisp = (ITEM_QUOTE && ITEM_QUOTE.ok) ? (ITEM_QUOTE.total_disp || '-') : '-';
        if (!confirm('#' + current.num + ' 색을 선점할까요?\n색변이 없어 게임냥 ' + qDisp + '으로 색변 1개를 산 뒤 바로 선점합니다.')) return;
      }
      post('claim');
    } else if (act === 'buy_item') {
      var qDisp = (ITEM_QUOTE && ITEM_QUOTE.ok) ? (ITEM_QUOTE.total_disp || '-') : '-';
      if (!confirm(ITEM_NAME + ' 1개를 ' + qDisp + '에 구매할까요?\n(게임냥 차감 · 구매 후 새로고침)')) return;
      post('buy_item');
    } else if (act === 'list') {
      syncPricePreview();
      var price = priceDigits;
      if (!price || price === '0' || cmpDigits(price, MIN_PRICE) < 0) {
        alert('최소 판매가는 ' + fmt(MIN_PRICE) + '이에요.\n예) 1만, 5천 또는 아래 버튼으로 추가');
        return;
      }
      if (MAX_PRICE && MAX_PRICE !== '0' && cmpDigits(price, MAX_PRICE) > 0) {
        alert('판매가는 본방냥 총합(' + fmt(MAX_PRICE) + ')을 넘을 수 없어요.');
        return;
      }
      if (!confirm('#' + current.num + ' 색을 ' + fmt(price) + '에 올릴까요?')) return;
      post('list', { price: price });
    } else if (act === 'unlist') {
      if (!confirm('판매를 철회할까요?')) return;
      post('unlist');
    } else if (act === 'wear') {
      post('wear');
    } else if (act === 'lock') {
      if (!confirm('#' + current.num + ' 색을 구매불가로 지정할까요?\n(사고팔기·선점 불가, 판매중이면 자동 내림)')) return;
      post('lock');
    } else if (act === 'unlock') {
      if (!confirm('#' + current.num + ' 색 구매불가를 해제할까요?')) return;
      post('unlock');
    }
  });

  sheetBg.addEventListener('click', closeSheet);

  listPriceInput.addEventListener('input', syncPricePreview);
  listPriceInput.addEventListener('change', syncPricePreview);

  document.getElementById('price-chips').addEventListener('click', function (e) {
    var btn = e.target.closest('.cm-chip');
    if (!btn) return;
    e.preventDefault();
    if (btn.getAttribute('data-clear')) {
      setPriceDigits('0', true);
      return;
    }
    var add = btn.getAttribute('data-add');
    if (!add) return;
    // 현재 입력값을 먼저 반영한 뒤 버튼 금액 추가
    syncPricePreview();
    var next = addDigits(priceDigits, add);
    setPriceDigits(next, true);
  });

  document.getElementById('tabs').addEventListener('click', function (e) {
    var tab = e.target.closest('.cm-tab');
    if (!tab) return;
    document.querySelectorAll('.cm-tab').forEach(function (t) { t.classList.remove('active'); });
    tab.classList.add('active');
    var f = tab.getAttribute('data-filter');
    grid.querySelectorAll('.cm-cell').forEach(function (el) {
      var filters = (el.getAttribute('data-filter') || '').split(/\s+/);
      var show = (f === 'all') || filters.indexOf(f) >= 0;
      el.classList.toggle('hidden', !show);
    });
  });
})();
</script>
<?php
if ($code !== '') {
  require_once __DIR__ . '/../api/game/wallet_nav_fab.inc.php';
  wallet_nav_fab_render(['code' => $code]);
}
?>
</body>
</html>
