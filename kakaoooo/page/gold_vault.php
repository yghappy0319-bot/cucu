<?php
/**
 * 개인금고 (금괴 보관)
 * URL: /page/gold_vault.php?code=XXXX
 * 신규: 은괴(1해) / 금괴(10해) / 백금괴(100해) · 기존 예치 원금 유지 · 기간 7/14일 · 동일 이율
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
// api/function.php(11k줄)는 읽기 경로에서 로드하지 않음 — 쓰기 시 gv_지급로그가 지연 로드
include_once __DIR__ . '/_gold_vault_lib.php';

// 첫 화면은 가볍게: 목록/이자/예치풀은 JS API로 채움
$회원 = gv_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$포인트 = $로그인 ? gv_냥($회원['point'] ?? '0') : '0';
$본방냥 = $로그인 ? gv_보유잔액($닉, 'newpoint') : '0';
$code = $로그인 ? $회원['code'] : '';
$포인트표시 = $로그인 ? gv_금액표시($포인트, 'point') : '—';
$본방냥표시 = $로그인 ? gv_금액표시($본방냥, 'newpoint') : '—';

if ($로그인 && $닉 !== '') {
    require_once __DIR__ . '/_game_quit_guard.php';
    게임포기_페이지가드($닉, $code, '개인금고');
}
$maxByKind = ['silver' => 0, 'gold' => 0, 'plat' => 0];
$pool = [
    'max' => (int)GOLD_VAULT_POOL_MAX,
    'used' => 0,
    'remain' => (int)GOLD_VAULT_POOL_MAX,
    'full' => false,
    'expire_3d' => 0,
    'used_disp' => '…',
    'remain_disp' => '…',
    'max_disp' => number_format((int)GOLD_VAULT_POOL_MAX),
    'expire_3d_disp' => '…',
];
$가능개수 = 0;

$bars = [];
$stat = [
    'total' => 0,
    'locked' => 0,
    'ready' => 0,
    'interest' => '0',
    'interest_disp' => '0',
    'pending' => '0',
    'pending_disp' => '0',
    'claimed_disp' => '0',
    'can_claim' => false,
    'daily' => '0',
    'daily_disp' => '0',
    'mature_interest' => '0',
    'mature_interest_disp' => '0',
];
$claim = [
    'pending' => '0',
    'pending_disp' => '0',
    'can_claim' => false,
    'daily' => '0',
    'daily_disp' => '0',
    'earned' => '0',
    'earned_disp' => '0',
    'claimed' => '0',
    'claimed_disp' => '0',
    'claim_days' => 0,
    'bar_count' => 0,
    'min_days' => 0,
    'max_days' => 0,
    'days_label' => '불러오는 중…',
    'daily_calc_label' => '이자 요약 계산 중…',
    'silver_bonus' => '0',
    'silver_bonus_disp' => '0',
    'silver_bonus_pct' => (int)GOLD_SILVER_CLAIM_BONUS_PCT,
    'payout_total_disp' => '…',
];
$listMeta = [
    'page' => 1,
    'pages' => 0,
    'total' => 0,
    'has_more' => $로그인,
    'filter' => 'all',
    'page_size' => (int)GOLD_VAULT_PAGE_SIZE,
];
$kinds = gv_상품종류안내();
$terms = gv_상품안내('gold');
$termsByKind = [];
foreach (array_keys(gv_상품종류목록()) as $k) {
    $termsByKind[$k] = gv_상품안내($k);
}
$marketDisc = 0;
$준호 = $로그인 && gv_준호인가($닉);
$adminQ = $code !== '' ? ('?code=' . rawurlencode($code)) : '';

function gv_bar_card_html(array $b) {
    $idx = (int)$b['idx'];
    $term = (int)$b['term_days'];
    $label = htmlspecialchars($b['bar_label'] ?? '금괴', ENT_QUOTES, 'UTF-8');
    $amtDisp = htmlspecialchars($b['amount_disp'] ?? '10해', ENT_QUOTES, 'UTF-8');
    $rate = htmlspecialchars($b['rate_disp'] ?? '10경/일', ENT_QUOTES, 'UTF-8');
    $kind = (string)($b['bar_kind'] ?? 'gold');
    $matured = !empty($b['matured']);
    $cls = $matured ? 'ready' : '';
    if ($kind === 'plat' || $kind === 'silver') {
        $cls .= ' ' . $kind;
    }
    $sub = htmlspecialchars($b['remain_label'], ENT_QUOTES, 'UTF-8');
    $dep = htmlspecialchars(substr((string)$b['deposited_at'], 0, 16), ENT_QUOTES, 'UTF-8');
    $interest = htmlspecialchars($b['interest_disp'] ?? '', ENT_QUOTES, 'UTF-8');
    $claimable = htmlspecialchars($b['claimable_disp'] ?? '0', ENT_QUOTES, 'UTF-8');
    $mature = htmlspecialchars($b['mature_disp'] ?? '', ENT_QUOTES, 'UTF-8');
    $early = htmlspecialchars($b['early_disp'] ?? '', ENT_QUOTES, 'UTF-8');
    $fee = htmlspecialchars($b['early_fee_disp'] ?? '', ENT_QUOTES, 'UTF-8');
    $mi = htmlspecialchars($b['mature_interest_disp'] ?? '', ENT_QUOTES, 'UTF-8');
    $earnedDays = (int)($b['accrued_days'] ?? 0);
    $nextTs = (int)($b['next_interest_ts'] ?? 0);
    $nextWhen = $nextTs > 0 ? date('m/d H:i', $nextTs) : '';
    $bonusPct = (int)($b['mature_bonus_pct'] ?? 0);
    $bonusDisp = htmlspecialchars($b['mature_bonus_disp'] ?? '0', ENT_QUOTES, 'UTF-8');
    $canExtend = !empty($b['can_extend']);
    $effTerm = (int)($b['effective_term'] ?? $term);
    $extend = (int)($b['extend_count'] ?? 0);
    if ($matured) {
        $btns = '';
        if ($canExtend) {
            $btns .= '<button type="button" class="gv-btn gv-btn-warn gv-btn-sm gv-extend" data-idx="' . $idx
                . '">30일 연장(+5%)</button>';
        }
        $btns .= '<span class="gv-item-hint">일괄만기해지만 가능</span>';
        $extra = '만기 · 원금+보상 ' . $mature;
        if ($bonusPct > 0) {
            $extra .= ' (추가 ' . $bonusPct . '% +' . $bonusDisp . ')';
        }
        if ($canExtend) {
            $extra .= ' · 연장 시 총 15%';
        }
        $extra .= ' · 적립 이자 +' . $interest . ' / 총 ' . $mi;
        $nextHtml = '<p class="gv-item-next done">적립 완료 · 수령대기 +' . $claimable . '</p>';
    } else {
        $earlyLocked = !empty($b['early_locked']);
        $lockLabel = htmlspecialchars((string)($b['early_lock_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lockUntil = htmlspecialchars((string)($b['early_lock_until'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lockDays = (int)($b['early_lock_days'] ?? gv_중도잠금일수($term));
        $keepPct = (int)($b['early_keep_pct'] ?? GOLD_BAR_FORCE_EARLY_KEEP_PCT);
        $btns = '<button type="button" class="gv-btn gv-btn-warn gv-btn-sm gv-early" data-idx="' . $idx
            . '" data-label="' . $label . '" data-early="' . $early . '" data-fee="' . $fee . '" data-paid="' . $interest . '"'
            . ' data-force="' . ($earlyLocked ? '1' : '0') . '" data-keep="' . $keepPct . '" data-lock-days="' . $lockDays . '">'
            . ($earlyLocked ? ('강제해지(원금' . $keepPct . '%)') : '중도해지') . '</button>';
        $termLabel = ($extend > 0) ? ($earnedDays . '/' . $effTerm . '일(연장)') : ($earnedDays . '/' . $term . '일');
        $extra = $termLabel . ' 적립 +' . $interest . ' · 수령대기 +' . $claimable
            . ($earlyLocked
                ? (' · 잠금' . $lockDays . '일 · 강제해지 수수료(원금' . (100 - $keepPct) . '%) ' . $fee)
                : (' · 중도 수수료 ' . $fee));
        if ($earlyLocked) {
            $extra .= ' · 해지잠금' . ($lockUntil !== '' ? (' ~' . $lockUntil) : '') . ($lockLabel !== '' ? (' ' . $lockLabel) : '');
        }
        if ($nextTs > 0) {
            $nextHtml = '<p class="gv-item-next" data-next-ts="' . $nextTs . '">다음 적립 ' . htmlspecialchars($nextWhen, ENT_QUOTES, 'UTF-8')
                . ' · <span class="gv-cd">--:--:--</span> 후</p>';
        } else {
            $nextHtml = '<p class="gv-item-next done">수령 가능 +' . $claimable . '</p>';
        }
    }
    return '<div class="gv-item ' . trim($cls) . '" data-idx="' . $idx . '">'
        . '<div class="gv-item-bar' . (($kind === 'plat' || $kind === 'silver') ? ' ' . $kind : '') . '" aria-hidden="true"></div>'
        . '<div class="gv-item-body">'
        . '<p class="gv-item-title">' . $label . ' #' . $idx . ' · ' . $amtDisp . ' · ' . $term . '일 ' . $rate . '</p>'
        . '<p class="gv-item-sub">' . $sub . ' · ' . $dep . '</p>'
        . '<p class="gv-item-extra">' . $extra . '</p>'
        . $nextHtml
        . '</div><div class="gv-item-actions">' . $btns . '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>개인금고</title>
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --gv-ink: #f3e6c8;
  --gv-muted: #a89878;
  --gv-gold: #d4a017;
  --gv-gold2: #f0d78c;
  --gv-ok: #7dba7a;
  --gv-warn: #c47a4a;
  --gv-line: rgba(240, 215, 140, 0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0;
  min-height: 100%;
  background:
    radial-gradient(90% 60% at 50% -10%, rgba(212, 160, 23, 0.22) 0%, transparent 55%),
    radial-gradient(70% 50% at 100% 100%, rgba(80, 60, 20, 0.35) 0%, transparent 50%),
    linear-gradient(165deg, #12151a 0%, #1c2028 45%, #151820 100%);
  color: var(--gv-ink);
  font-family: 'MyFont', 'Apple SD Gothic Neo', sans-serif;
  font-size: 17px;
  -webkit-tap-highlight-color: transparent;
}
.gv-wrap { max-width: 480px; margin: 0 auto; padding: 16px 14px 110px; }
.gv-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 18px; }
.gv-brand { margin: 0; font-size: 34px; letter-spacing: -0.04em; line-height: 1.1; color: var(--gv-gold2); text-shadow: 0 1px 0 rgba(0,0,0,.45); }
.gv-sub { margin: 6px 0 0; font-size: 15px; color: var(--gv-muted); line-height: 1.35; }
.gv-back {
  flex-shrink: 0; display: inline-flex; align-items: center; padding: 8px 12px;
  border-radius: 10px; border: 1px solid var(--gv-line); background: rgba(255,255,255,0.04);
  color: var(--gv-ink); text-decoration: none; font-size: 14px;
}
.gv-vault {
  position: relative; border-radius: 22px; padding: 22px 18px 20px; margin-bottom: 16px;
  background: linear-gradient(145deg, #3a3f4a 0%, #23272f 40%, #1a1d24 100%);
  border: 1px solid rgba(240, 215, 140, 0.28);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), inset 0 -20px 40px rgba(0,0,0,0.35), 0 18px 40px rgba(0,0,0,0.45);
  overflow: hidden;
}
.gv-vault::before { content: ''; position: absolute; inset: 10px; border-radius: 16px; border: 1px solid rgba(212, 160, 23, 0.2); pointer-events: none; }
.gv-vault-hinge { position: absolute; left: 8px; top: 28%; width: 8px; height: 44%; border-radius: 4px; background: linear-gradient(180deg, #c9a227, #6e5510); opacity: 0.7; }
.gv-vault-dial {
  width: 72px; height: 72px; margin: 0 auto 14px; border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #f5e6b0 0%, #d4a017 35%, #7a5a10 75%, #3a2e0a 100%);
  border: 3px solid #f0d78c; box-shadow: inset 0 0 12px rgba(0,0,0,0.35), 0 0 0 4px rgba(0,0,0,0.25);
  position: relative; animation: gv-dial 8s ease-in-out infinite;
}
.gv-vault-dial::after { content: ''; position: absolute; left: 50%; top: 12%; width: 3px; height: 28%; background: #2a2208; transform: translateX(-50%); border-radius: 2px; }
@keyframes gv-dial { 0%, 100% { transform: rotate(-8deg); } 50% { transform: rotate(12deg); } }
.gv-vault-title { text-align: center; font-size: 15px; color: var(--gv-muted); margin: 0 0 10px; letter-spacing: 0.08em; }
.gv-count { text-align: center; font-size: 42px; font-weight: 700; color: var(--gv-gold2); line-height: 1; margin: 0; animation: gv-pulse 2.8s ease-in-out infinite; }
@keyframes gv-pulse { 0%, 100% { text-shadow: 0 0 0 transparent; } 50% { text-shadow: 0 0 18px rgba(240, 215, 140, 0.35); } }
.gv-count span { font-size: 18px; font-weight: 500; color: var(--gv-muted); margin-left: 4px; }
.gv-meta { display: flex; justify-content: center; gap: 14px; margin-top: 12px; font-size: 13px; color: var(--gv-muted); }
.gv-meta b { color: var(--gv-gold2); font-weight: 600; }
.gv-ticker {
  position: relative; z-index: 1; margin: 14px 2px 0; padding: 10px 12px 12px;
  border-radius: 12px; background: #0a0c10;
  border: 1px solid rgba(125, 220, 160, 0.28);
  box-shadow: inset 0 0 0 1px rgba(0,0,0,0.55), inset 0 0 24px rgba(40, 180, 100, 0.08), 0 0 18px rgba(80, 200, 120, 0.12);
  overflow: hidden;
}
.gv-ticker::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.18) 2px, rgba(0,0,0,0.18) 3px);
  opacity: 0.55;
}
.gv-ticker-label {
  position: relative; margin: 0 0 6px; font-size: 11px; letter-spacing: 0.14em;
  color: rgba(140, 230, 170, 0.7); text-transform: uppercase;
}
.gv-ticker-value {
  position: relative; margin: 0; font-size: 26px; font-weight: 800; line-height: 1.15;
  color: #8dffb0; letter-spacing: -0.02em;
  text-shadow: 0 0 10px rgba(100, 255, 160, 0.45), 0 0 2px rgba(180, 255, 200, 0.8);
  font-variant-numeric: tabular-nums; word-break: break-all;
  animation: gv-ticker-glow 2.4s ease-in-out infinite;
}
.gv-ticker-value .gv-ticker-plus { color: #b8ffd0; margin-right: 2px; }
.gv-ticker.empty .gv-ticker-value {
  font-size: 16px; font-weight: 600; color: rgba(140, 230, 170, 0.45); text-shadow: none; animation: none;
}
@keyframes gv-ticker-glow {
  0%, 100% { text-shadow: 0 0 8px rgba(100, 255, 160, 0.35), 0 0 1px rgba(180, 255, 200, 0.7); }
  50% { text-shadow: 0 0 16px rgba(100, 255, 160, 0.65), 0 0 4px rgba(180, 255, 200, 0.95); }
}
.gv-ticker-sub {
  position: relative; display: flex; flex-wrap: wrap; gap: 8px 14px; margin-top: 8px;
  font-size: 12px; color: rgba(140, 230, 170, 0.65);
}
.gv-ticker-sub b { color: #9dffbc; font-weight: 700; }
.gv-bars-stage { display: flex; justify-content: center; align-items: flex-end; gap: 6px; min-height: 54px; margin: 16px 0 4px; perspective: 400px; }
.gv-bar-visual {
  width: 28px; height: 42px; border-radius: 4px 4px 2px 2px;
  background: linear-gradient(90deg, #8a6a12 0%, #f0d78c 22%, #d4a017 50%, #f5e6b0 62%, #8a6a12 100%);
  box-shadow: 2px 4px 8px rgba(0,0,0,0.4); transform: rotateX(8deg); animation: gv-bar-in 0.55s ease both;
}
.gv-bar-visual:nth-child(odd) { height: 48px; }
.gv-bar-visual:nth-child(3n) { height: 38px; }
@keyframes gv-bar-in { from { opacity: 0; transform: translateY(16px) rotateX(8deg); } to { opacity: 1; transform: translateY(0) rotateX(8deg); } }
.gv-bal { margin: 14px 0 14px; padding: 12px 14px; border-radius: 14px; background: rgba(0,0,0,0.28); border: 1px solid var(--gv-line); }
.gv-bal-label { font-size: 13px; color: var(--gv-muted); margin: 0 0 4px; }
.gv-bal-val { margin: 0; font-size: 20px; color: var(--gv-gold2); word-break: break-all; }
.gv-bal-hint { margin: 6px 0 0; font-size: 13px; color: var(--gv-muted); }
.gv-bal-hint b { color: var(--gv-ink, #e8e4dc); font-variant-numeric: tabular-nums; }
.gv-ledger-toggle,
.gv-fold-toggle {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  width: 100%; margin: 0; padding: 0; border: 0; background: transparent;
  color: inherit; font: inherit; cursor: pointer; text-align: left;
}
.gv-ledger-toggle .gv-bal-label,
.gv-fold-toggle .gv-fold-label { margin: 0; font-weight: 700; color: var(--gv-ink, #e8e4dc); }
.gv-ledger-toggle .gv-ledger-chev,
.gv-fold-toggle .gv-fold-chev {
  flex-shrink: 0; font-size: 12px; color: var(--gv-muted);
  transition: transform .2s ease;
}
.gv-ledger-toggle[aria-expanded="true"] .gv-ledger-chev,
.gv-fold-toggle[aria-expanded="true"] .gv-fold-chev { transform: rotate(180deg); }
.gv-ledger-body { margin-top: 8px; font-size: 13px; line-height: 1.55; color: var(--gv-muted); }
.gv-rules-body { margin-top: 10px; }
.gv-pool {
  margin: 10px 0 0; padding: 10px 12px; border-radius: 12px;
  background: rgba(212, 160, 23, 0.10); border: 1px solid rgba(240, 215, 140, 0.28);
}
.gv-pool-main {
  margin: 0; font-size: 17px; font-weight: 700; color: var(--gv-gold2);
  font-variant-numeric: tabular-nums; line-height: 1.35;
}
.gv-pool-main b { color: #ffe7a0; font-size: 22px; font-weight: 800; }
.gv-pool-soon { margin-left: 0.35em; font-size: 15px; font-weight: 600; color: #f0c070; }
.gv-pool-soon b { font-size: 18px; color: #ffd78a; }
.gv-pool-sub { margin: 6px 0 0; font-size: 13px; color: var(--gv-muted); }
.gv-pool-sub b { color: var(--gv-ink, #e8e4dc); font-variant-numeric: tabular-nums; }
.gv-hold-sep { margin: 0 0.35em; opacity: 0.45; }
.gv-hold-total { margin-left: 0.35em; opacity: 0.85; }
.gv-terms { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px; }
.gv-term {
  appearance: none; border: 1px solid var(--gv-line); border-radius: 12px; padding: 10px 6px;
  background: rgba(0,0,0,0.28); color: var(--gv-muted); font-family: inherit; cursor: pointer; text-align: center;
}
.gv-term strong { display: block; color: var(--gv-gold2); font-size: 18px; margin-bottom: 4px; }
.gv-term small { display: block; font-size: 11px; line-height: 1.35; }
.gv-term.on { border-color: rgba(240, 215, 140, 0.55); background: rgba(212, 160, 23, 0.16); color: var(--gv-ink); }
.gv-actions { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-bottom: 18px; }
.gv-count-ctrl { display: flex; align-items: center; gap: 6px; background: rgba(0,0,0,0.28); border: 1px solid var(--gv-line); border-radius: 12px; padding: 4px; }
.gv-count-ctrl button { width: 40px; height: 40px; border: 0; border-radius: 10px; background: rgba(212, 160, 23, 0.18); color: var(--gv-gold2); font-size: 22px; cursor: pointer; }
.gv-count-ctrl input { width: 48px; text-align: center; border: 0; background: transparent; color: var(--gv-ink); font-size: 18px; font-family: inherit; }
.gv-btn {
  border: 0; border-radius: 12px; padding: 0 18px; min-height: 48px; font-size: 16px; font-family: inherit; font-weight: 600; cursor: pointer;
  color: #1a1408; background: linear-gradient(180deg, #f0d78c, #d4a017 55%, #b8860b); box-shadow: 0 4px 0 #6e5510;
}
.gv-btn:active { transform: translateY(2px); box-shadow: 0 2px 0 #6e5510; }
.gv-btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
.gv-btn-sm { min-height: 36px; padding: 0 10px; font-size: 13px; box-shadow: 0 2px 0 #6e5510; }
.gv-btn-warn {
  color: #f8e8d8; background: linear-gradient(180deg, #a86a45, #7a3f28); box-shadow: 0 2px 0 #4a2416; font-weight: 600;
}
.gv-sec-title { margin: 0 0 10px; font-size: 15px; color: var(--gv-muted); letter-spacing: 0.04em; }
.gv-list { display: flex; flex-direction: column; gap: 8px; }
.gv-item {
  display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 14px;
  background: rgba(0,0,0,0.28); border: 1px solid var(--gv-line);
}
.gv-sec-count { font-weight: 500; color: var(--gv-muted); font-size: 14px; margin-left: 6px; }
.gv-list-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 10px; }
.gv-filters { display: flex; gap: 6px; }
.gv-filter {
  border: 1px solid var(--gv-line); background: rgba(0,0,0,0.2); color: var(--gv-muted);
  border-radius: 999px; padding: 6px 12px; font-size: 12px; cursor: pointer;
}
.gv-filter.on { border-color: rgba(240, 215, 140, 0.5); color: var(--gv-gold2); background: rgba(212, 160, 23, 0.14); }
.gv-page-meta { margin: 0; font-size: 12px; color: var(--gv-muted); }
.gv-pager {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  margin: 12px 0 4px; color: var(--gv-muted); font-size: 13px;
}
.gv-item-hint {
  display: block;
  font-size: 11px;
  line-height: 1.35;
  color: #8a7340;
  text-align: center;
  padding: 4px 2px;
  max-width: 7.5em;
}
.gv-item.ready { border-color: rgba(125, 186, 122, 0.45); background: rgba(40, 70, 40, 0.25); }
.gv-item.plat { border-color: rgba(180, 200, 220, 0.35); }
.gv-item.silver { border-color: rgba(160, 170, 180, 0.35); }
.gv-item-bar { width: 22px; height: 34px; flex-shrink: 0; border-radius: 3px; background: linear-gradient(90deg, #8a6a12, #f0d78c 40%, #d4a017 70%, #8a6a12); }
.gv-item-bar.plat { background: linear-gradient(90deg, #6a7a8a, #e8eef5 40%, #b8c4d4 70%, #6a7a8a); }
.gv-item-bar.silver { background: linear-gradient(90deg, #5a6068, #c8ced6 40%, #9aa3ae 70%, #5a6068); }
.gv-kinds { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 0 0 12px; }
@media (min-width: 520px) {
  .gv-kinds { grid-template-columns: repeat(3, 1fr); }
}
.gv-kind {
  appearance: none; border: 1px solid var(--gv-line); background: rgba(0,0,0,0.28);
  color: var(--gv-muted); border-radius: 14px; padding: 12px 10px; cursor: pointer; text-align: left;
}
.gv-kind.on { border-color: rgba(240, 215, 140, 0.55); color: var(--gv-ink); background: rgba(240, 215, 140, 0.1); }
.gv-kind.plat.on { border-color: rgba(200, 220, 240, 0.55); background: rgba(180, 200, 220, 0.12); }
.gv-kind.silver.on { border-color: rgba(180, 190, 200, 0.5); background: rgba(140, 150, 160, 0.12); }
.gv-kind strong { display: block; font-size: 16px; color: var(--gv-gold2); margin-bottom: 4px; }
.gv-kind.plat strong { color: #d7e4f2; }
.gv-kind.silver strong { color: #c5ced8; }
.gv-kind small { display: block; font-size: 12px; line-height: 1.4; color: var(--gv-muted); }
.gv-term[hidden] { display: none !important; }
.gv-item-body { flex: 1; min-width: 0; }
.gv-item-title { margin: 0; font-size: 16px; color: var(--gv-gold2); }
.gv-item-sub { margin: 3px 0 0; font-size: 13px; color: var(--gv-muted); }
.gv-item-extra { margin: 4px 0 0; font-size: 12px; color: #cbb98a; }
.gv-item-next { margin: 4px 0 0; font-size: 12px; color: #8dffb0; }
.gv-item-next .gv-cd {
  display: inline-block; min-width: 7.2em; font-variant-numeric: tabular-nums;
  font-weight: 800; letter-spacing: 0.04em;
  color: #b8ffd0; text-shadow: 0 0 8px rgba(100, 255, 160, 0.45);
  animation: gv-cd-tick 1s steps(1, end) infinite;
}
.gv-item-next.done { color: rgba(140, 230, 170, 0.55); }
.gv-item-next.done .gv-cd { animation: none; text-shadow: none; color: inherit; font-weight: 600; }
@keyframes gv-cd-tick {
  0%, 80% { opacity: 1; }
  90% { opacity: 0.72; }
  100% { opacity: 1; }
}
.gv-item.ready .gv-item-sub { color: var(--gv-ok); }
.gv-empty { text-align: center; padding: 28px 12px; color: var(--gv-muted); font-size: 15px; border: 1px dashed var(--gv-line); border-radius: 14px; }
.gv-rules {
  margin-top: 20px; padding: 14px; border-radius: 14px; background: rgba(0,0,0,0.22);
  border: 1px solid var(--gv-line); font-size: 13px; color: var(--gv-muted); line-height: 1.55;
}
.gv-rules strong { color: var(--gv-gold2); font-weight: 600; }
.gv-batch {
  margin-top: 16px; display: grid; grid-template-columns: 1fr; gap: 8px;
}
.gv-batch[hidden] { display: none !important; }
.gv-batch .gv-btn { width: 100%; box-shadow: 0 3px 0 #6e5510; font-size: 15px; }
.gv-batch .gv-btn-warn { box-shadow: 0 3px 0 #4a2416; }
.gv-batch .gv-bal-hint { margin: 0; text-align: center; font-size: 12px; color: var(--gv-muted); }
.gv-toast {
  position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%) translateY(20px);
  max-width: min(420px, calc(100% - 28px)); padding: 12px 16px; border-radius: 12px;
  background: #2a2418; border: 1px solid rgba(240, 215, 140, 0.35); color: var(--gv-gold2);
  font-size: 14px; opacity: 0; pointer-events: none; transition: opacity .25s, transform .25s; z-index: 50; text-align: center;
}
.gv-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.gv-login { text-align: center; padding: 40px 16px; color: var(--gv-muted); }
.gv-login a { color: var(--gv-gold2); }
.gv-currency-tabs {
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
  margin: 0 0 14px;
}
.gv-currency-tabs button {
  appearance: none; border: 1px solid rgba(240, 215, 140, 0.28);
  background: rgba(30, 26, 20, 0.9); color: var(--gv-muted);
  border-radius: 12px; padding: 12px 10px; font-size: 15px; font-weight: 700;
  cursor: pointer;
}
.gv-currency-tabs button.on {
  color: #1a1510; background: linear-gradient(180deg, #f0d78c, #c9a24a);
  border-color: transparent;
}
.gv-currency-tabs button span {
  display: block; margin-top: 4px; font-size: 12px; font-weight: 500; opacity: 0.85;
}
.gv-mission {
  display: none;
  margin: 0 0 14px;
  padding: 14px 14px 12px;
  border-radius: 14px;
  border: 1px solid rgba(125, 186, 122, 0.4);
  background: linear-gradient(165deg, rgba(40, 52, 38, 0.95), rgba(22, 28, 20, 0.98));
}
.gv-mission.show { display: block; }
.gv-mission-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
  margin-bottom: 8px;
}
.gv-mission-title {
  margin: 0; font-size: 15px; font-weight: 800; color: #c8e6c0; letter-spacing: 0.02em;
}
.gv-mission-badge {
  flex-shrink: 0; font-size: 11px; font-weight: 700;
  padding: 4px 8px; border-radius: 999px;
  background: rgba(125, 186, 122, 0.2); color: #b7dfb0;
  border: 1px solid rgba(125, 186, 122, 0.35);
}
.gv-mission-badge.on {
  background: rgba(240, 215, 140, 0.2); color: var(--gv-gold2);
  border-color: rgba(240, 215, 140, 0.4);
}
.gv-mission-sub {
  margin: 0 0 10px; font-size: 12px; color: var(--gv-muted); line-height: 1.45;
}
.gv-mission-bar {
  height: 10px; border-radius: 999px; background: rgba(0,0,0,0.35);
  overflow: hidden; margin: 0 0 8px;
}
.gv-mission-bar > i {
  display: block; height: 100%; width: 0%;
  background: linear-gradient(90deg, #6aaf66, #f0d78c);
  border-radius: inherit; transition: width .35s ease;
}
.gv-mission-meta {
  display: flex; flex-wrap: wrap; gap: 8px 14px;
  margin: 0 0 10px; font-size: 12px; color: var(--gv-muted);
}
.gv-mission-meta b { color: var(--gv-ink); font-variant-numeric: tabular-nums; }
.gv-mission-tiers {
  margin: 0; padding: 0; list-style: none;
  display: grid; gap: 6px;
}
.gv-mission-tiers li {
  font-size: 12px; color: var(--gv-muted);
  padding: 6px 8px; border-radius: 8px;
  background: rgba(0,0,0,0.22);
}
.gv-mission-tiers li.done {
  color: #c8e6c0;
  border: 1px solid rgba(125, 186, 122, 0.35);
}
.gv-mission-tiers li .pct { color: var(--gv-gold2); font-weight: 700; }
.gv-mission-tabs {
  display: flex; gap: 6px; margin: 0 0 12px;
}
.gv-mission-tab {
  appearance: none; flex: 1; cursor: pointer;
  border: 1px solid rgba(125, 186, 122, 0.28);
  background: rgba(0,0,0,0.22); color: var(--gv-muted);
  border-radius: 10px; padding: 8px 10px; font-size: 13px; font-weight: 700;
}
.gv-mission-tab.on {
  color: #1a1510; background: linear-gradient(180deg, #d5e8d0, #8fbf86);
  border-color: transparent;
}
.gv-rank-list {
  margin: 0; padding: 0; list-style: none; display: grid; gap: 8px;
}
.gv-rank-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 10px;
  background: rgba(0,0,0,0.22);
}
.gv-rank-item.r1 { border: 1px solid rgba(240, 215, 140, 0.45); }
.gv-rank-item.r2 { border: 1px solid rgba(180, 196, 210, 0.28); }
.gv-rank-item.r3 { border: 1px solid rgba(196, 146, 90, 0.28); }
.gv-rank-num {
  flex-shrink: 0; width: 36px; font-size: 14px; font-weight: 800;
  color: var(--gv-gold2);
}
.gv-rank-nick {
  flex: 1; min-width: 0; font-size: 15px; font-weight: 700; color: var(--gv-ink);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.gv-rank-amt {
  flex-shrink: 0; font-size: 13px; font-weight: 700; color: #c8e6c0;
  font-variant-numeric: tabular-nums; text-align: right;
  display: flex; flex-direction: column; align-items: flex-end; gap: 2px;
}
.gv-rank-pct {
  font-size: 11px; font-weight: 700; color: var(--gv-gold2); opacity: 0.95;
}
.gv-rank-empty {
  margin: 0; padding: 12px; text-align: center;
  font-size: 13px; color: var(--gv-muted);
  background: rgba(0,0,0,0.18); border-radius: 10px;
}
</style>
</head>
<body>
<div class="gv-wrap">
  <div class="gv-top">
    <div>
      <h1 class="gv-brand">개인금고</h1>
      <p class="gv-sub">자유예치 · 7일 잠금 · 이자 수령</p>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0;">
      <?php if (!empty($준호)): ?>
      <a class="gv-back" href="/page/gold_vault_admin.php<?php echo htmlspecialchars($adminQ, ENT_QUOTES, 'UTF-8'); ?>">전체내역</a>
      <?php endif; ?>
    </div>
  </div>

<?php if (!$로그인): ?>
  <div class="gv-login">
    <p>연구실에서 발급받은 코드 링크로 접속해주세요.</p>
  </div>
<?php else: ?>

  <div class="gv-currency-tabs" id="gvCurrencyTabs" role="tablist" aria-label="예치 통화">
    <button type="button" class="on" data-currency="point" role="tab" aria-selected="true">겜냥<span id="gvTabPointBal"><?php echo htmlspecialchars($포인트표시, ENT_QUOTES, 'UTF-8'); ?></span></button>
    <button type="button" data-currency="newpoint" role="tab" aria-selected="false">본냥<span id="gvTabNewpointBal"><?php echo htmlspecialchars($본방냥표시, ENT_QUOTES, 'UTF-8'); ?></span></button>
  </div>

  <section class="gv-mission" id="gvCommunityMission" aria-label="전체미션">
    <div class="gv-mission-head">
      <p class="gv-mission-title" id="gvMissionTitle">전체미션</p>
      <span class="gv-mission-badge" id="gvMissionBadge">전체</span>
    </div>
    <div class="gv-mission-tabs" id="gvMissionInnerTabs" role="tablist" aria-label="전체미션 탭">
      <button type="button" class="gv-mission-tab on" data-mission-tab="mission" role="tab" aria-selected="true">전체미션</button>
      <button type="button" class="gv-mission-tab" data-mission-tab="rank" role="tab" aria-selected="false">예치순위</button>
    </div>
    <div id="gvMissionPanel">
      <p class="gv-mission-sub" id="gvMissionSub">불러오는 중…</p>
      <div class="gv-mission-bar" aria-hidden="true"><i id="gvMissionBarFill"></i></div>
      <div class="gv-mission-meta">
        <span>예치 <b id="gvMissionDep">—</b></span>
        <span>전체 <b id="gvMissionTotal">—</b></span>
        <span>비율 <b id="gvMissionRatio">—</b></span>
      </div>
      <p class="gv-mission-sub" id="gvMissionEffect" style="margin-bottom:8px;">불러오는 중…</p>
      <ul class="gv-mission-tiers" id="gvMissionTiers"></ul>
    </div>
    <div id="gvRankPanel" hidden>
      <p class="gv-mission-sub" id="gvRankSub">개인금고에 가장 많이 맡긴 순서 · 전체 예치 대비 비중</p>
      <ol class="gv-rank-list" id="gvRankList"></ol>
    </div>
  </section>

  <section class="gv-vault" aria-label="개인금고">
    <div class="gv-vault-hinge" aria-hidden="true"></div>
    <div class="gv-vault-dial" aria-hidden="true"></div>
    <p class="gv-vault-title">PERSONAL VAULT · <?php echo htmlspecialchars($닉, ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="gv-count" id="gvTotal"><?php echo (int)$stat['total']; ?><span>개</span></p>
    <div class="gv-meta">
      <span>예치중 <b id="gvLocked"><?php echo (int)$stat['locked']; ?></b></span>
      <span>만기 <b id="gvReady"><?php echo (int)$stat['ready']; ?></b></span>
    </div>
    <?php
      $이자있음 = false;
      $이자표시 = '이자 요약 계산 중…';
    ?>
    <div class="gv-ticker empty" id="gvTicker" aria-live="polite">
      <p class="gv-ticker-label">ACCRUED · 맡긴 시점부터 적립된 이자</p>
      <p class="gv-ticker-value" id="gvInterest"><?php echo htmlspecialchars($이자표시, ENT_QUOTES, 'UTF-8'); ?></p>
      <div class="gv-ticker-sub">
        <span id="gvDaysLabel"><?php echo htmlspecialchars($claim['days_label'] ?? '불러오는 중…', ENT_QUOTES, 'UTF-8'); ?></span>
        <span id="gvDailyCalc"><?php echo htmlspecialchars($claim['daily_calc_label'] ?? '이자 요약 계산 중…', ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <div class="gv-ticker-sub">
        <span>하루 이자 <b id="gvDailySum">+…</b></span>
        <span>기간 총 이자 <b id="gvMatureSum">+…</b></span>
      </div>
    </div>
    <div class="gv-bars-stage" id="gvStage" aria-hidden="true">
      <?php
        $show = min(8, max(0, (int)$stat['total']));
        for ($i = 0; $i < $show; $i++) {
            echo '<div class="gv-bar-visual" style="animation-delay:' . ($i * 0.05) . 's"></div>';
        }
      ?>
    </div>
  </section>

  <div class="gv-bal" id="gvClaimBox" style="border-color: rgba(125,186,122,0.35);">
    <p class="gv-bal-label">수령 가능 <span id="gvClaimUnitLabel">게임냥</span> 이자</p>
    <p class="gv-bal-val" id="gvClaimPending"><?php echo htmlspecialchars($claim['payout_total_disp'] ?? $claim['pending_disp'], ENT_QUOTES, 'UTF-8'); ?> <span style="font-size:16px;color:var(--gv-muted);" id="gvClaimUnitSuffix">게임냥</span></p>
    <p class="gv-bal-hint">
      적립 합계 <b id="gvClaimEarned"><?php echo htmlspecialchars($claim['earned_disp'], ENT_QUOTES, 'UTF-8'); ?></b>
      · 이미 수령 <b id="gvClaimed"><?php echo htmlspecialchars($claim['claimed_disp'], ENT_QUOTES, 'UTF-8'); ?></b>
      · 매일 +<b id="gvClaimDaily"><?php echo htmlspecialchars($claim['daily_disp'], ENT_QUOTES, 'UTF-8'); ?></b>
    </p>
    <p class="gv-bal-hint">선택한 통화의 자유예치·7일잠금 이자가 여기로 쌓여요 · <strong>수령하기</strong>로 같은 통화 지급</p>
    <p class="gv-bal-hint" id="gvSilverBonusHint"<?php echo (gv_비교($claim['silver_bonus'] ?? '0', '0') <= 0) ? ' hidden' : ''; ?>>
      은괴 수령보너스 <?php echo (int)($claim['silver_bonus_pct'] ?? GOLD_SILVER_CLAIM_BONUS_PCT); ?>%
      (최대 <?php echo (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS; ?>개분)
      · 이자 <b id="gvClaimInterestOnly"><?php echo htmlspecialchars($claim['pending_disp'], ENT_QUOTES, 'UTF-8'); ?></b>
      + 보너스 <b id="gvSilverBonus"><?php echo htmlspecialchars($claim['silver_bonus_disp'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></b>
    </p>
    <button type="button" class="gv-btn" id="gvClaimInterest" style="width:100%;margin-top:12px;"
      <?php echo empty($claim['can_claim']) ? 'disabled' : ''; ?>>수령하기</button>
  </div>

  <div class="gv-bal">
    <p class="gv-bal-label">보유 <span id="gvBalanceUnitLabel">게임냥</span></p>
    <p class="gv-bal-val" id="gvPoint"><?php echo htmlspecialchars($포인트표시, ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="gv-bal-hint">맡길 비율 · 본냥/겜냥 탭별로 따로 예치됩니다</p>
    <div class="gv-filters" id="gvDepositPct" role="tablist" aria-label="예치 비율" style="margin:8px 0 4px;">
      <button type="button" class="gv-filter" data-pct="50">50%</button>
      <button type="button" class="gv-filter on" data-pct="100">100%</button>
    </div>
    <p class="gv-bal-hint"><strong>① 자유예치</strong> · 선택한 비율 · 만 24시간 후부터 1%/일 · 24시간 미만 이자 없음 · 넣다 빼기 자유 · 이자는 수령 가능에 적립</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;">
      <button type="button" class="gv-btn" id="gvDepositAll"
        <?php echo (gv_비교($포인트, '0') <= 0) ? 'disabled' : ''; ?>>자유예치 맡기기</button>
      <button type="button" class="gv-btn gv-btn-warn" id="gvWithdrawCash" disabled>자유예치 빼기</button>
    </div>
    <p class="gv-bal-hint" style="margin-top:14px;"><strong>② 7일 잠금</strong> · 선택한 비율 · <strong>맡긴 즉시 1일분 이자</strong> · 이후 24시간마다 1% · 처음 2일 잠금 · 중도 시 원금에서 50→40→30→20% 차감 · 만기 시 원금 +5% · 이자는 수령 가능</p>
    <button type="button" class="gv-btn" id="gvDepositLock7" style="width:100%;margin-top:8px;"
      <?php echo (gv_비교($포인트, '0') <= 0) ? 'disabled' : ''; ?>>7일 잠금 맡기기</button>
  </div>

  <h2 class="gv-sec-title">내 금괴 <span class="gv-sec-count" id="gvListCount"><?php echo (int)$stat['total']; ?>개</span></h2>
  <div class="gv-list-tools" id="gvListTools" <?php echo $로그인 ? '' : 'hidden'; ?>>
    <div class="gv-filters" role="tablist" aria-label="목록 필터">
      <button type="button" class="gv-filter on" data-filter="all">전체</button>
      <button type="button" class="gv-filter" data-filter="ready">만기</button>
      <button type="button" class="gv-filter" data-filter="active">예치중</button>
    </div>
    <p class="gv-page-meta" id="gvPageMeta">목록 불러오는 중…</p>
  </div>
  <div class="gv-list" id="gvList">
    <div class="gv-empty" id="gvListEmpty"><?php echo $로그인
      ? '목록을 불러오는 중…'
      : '연구실 코드 링크로 접속해주세요.'; ?></div>
  </div>
  <div class="gv-pager" id="gvPager" hidden>
    <button type="button" class="gv-btn gv-btn-sm" id="gvPrevPage">이전</button>
    <span id="gvPageLabel">1 / 1</span>
    <button type="button" class="gv-btn gv-btn-sm" id="gvNextPage">다음</button>
  </div>

  <div class="gv-batch" id="gvMatureBox" hidden>
    <button type="button" class="gv-btn" id="gvMatureAll" disabled>일괄만기해지</button>
    <p class="gv-bal-hint" id="gvMatureHint">만기된 예치만 해지됩니다</p>
  </div>

  <div class="gv-bal" id="gvLedgerBox">
    <button type="button" class="gv-ledger-toggle" id="gvLedgerToggle" aria-expanded="false" aria-controls="gvLedgerBody">
      <span class="gv-bal-label">최근 기록</span>
      <span class="gv-ledger-chev" aria-hidden="true">▼</span>
    </button>
    <div class="gv-ledger-body" id="gvLedgerBody" hidden>
      <div id="gvLedger">기록 불러오는 중…</div>
    </div>
  </div>

  <div class="gv-rules" id="gvRulesBox">
    <button type="button" class="gv-fold-toggle" id="gvRulesToggle" aria-expanded="false" aria-controls="gvRulesBody">
      <span class="gv-fold-label">이용 안내</span>
      <span class="gv-fold-chev" aria-hidden="true">▼</span>
    </button>
    <div class="gv-rules-body" id="gvRulesBody" hidden>
    · <strong>개인금고</strong>예요. 채팅 <strong>.금고</strong>(금고털이)와는 별개예요<br>
    · <strong>① 자유예치</strong>: 선택한 통화(본냥/겜냥) 보유액의 <strong>50% 또는 100%</strong> · <strong>만 24시간 후부터 1%/일</strong> · <strong>24시간 미만 이자 없음</strong> · 넣다 빼기 자유 · 이자는 <strong>수령 가능 이자</strong>에 적립<br>
    · <strong>② 7일 잠금</strong>: 선택한 통화 보유액의 <strong>50% 또는 100%</strong> · <strong>맡긴 즉시 1일분 이자</strong> · 이후 24시간마다 1% · 이자는 <strong>수령 가능 이자</strong> · 만기해지 시 원금 <strong>+5% 추가이자</strong><br>
    · 본냥·겜냥은 <strong>탭별로 따로</strong> 예치·인출·이자 수령됩니다<br>
    · <strong>본냥 전체미션</strong>: 친구들 본방냥 예치 합이 전체의 <strong>10%/30%/60%</strong>면 전원 무기·채굴 강화비 <strong>5%/10%/15%</strong> 할인<br>
    · <strong>겜냥 전체미션</strong>: 친구들 게임냥 예치 합이 전체의 <strong>10%/30%/50%</strong>면 전원 마켓 <strong>5%/10%/15%</strong> 할인 (금괴·백금괴 할인과 합산)<br>
    · 7일잠금 중도: 처음 <strong>2일 잠금</strong> · 원금에서 위약금 <strong>50%→40%→30%→20%</strong> 차감(24시간마다, 이후 20% 고정) 후 환급 · 만기보상 없음<br>
    · 은/금/백금 개별 예치는 마감 · <strong>기존 예치</strong>도 이자 수령·해지 가능<br>
    · 이자는 <strong>수령하기</strong>로 예치한 통화(본냥/겜냥)로 수령<br>
    · <strong>은괴 수령보너스</strong>: 미수령 이자의 <strong><?php echo (int)GOLD_SILVER_CLAIM_BONUS_PCT; ?>%</strong> 추가 · <strong>최대 <?php echo (int)GOLD_SILVER_CLAIM_BONUS_MAX_BARS; ?>개분</strong>만 적용<br>
    · 만기해지: <strong>일괄만기해지만</strong> 가능 (1개씩 불가) · 원금 + 미수령 이자 · <strong>7일 5%</strong> · <strong>14일 10%</strong> 추가보상<br>
    · (구) 30일 만기 시 <strong>30일 연장 1회</strong> → 만기 시 <strong>총 15%</strong>(10%+5%)<br>
    · 중도해지 잠금(구 금괴): <strong>7일→4일</strong> · <strong>14일→7일</strong> · (구 30일→14일)<br>
    · 잠금 중 강제해지 환급(구 금괴): 맡긴 날 <strong><?php echo (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT; ?>%</strong> → 다음날 <strong>45%</strong> → <strong>40%</strong> → <strong>35%</strong> → <strong><?php echo (int)GOLD_BAR_FORCE_EARLY_KEEP_FLOOR_PCT; ?>%</strong>(이후 고정) · 나머지 소멸 · 만기보상 없음<br>
    · 잠금 후 중도해지: 적립 이자 × <strong><?php echo (int)GOLD_BAR_EARLY_FEE_MULT; ?></strong>을 수수료로 원금에서 차감 후 환급 (만기 추가보상 없음)<br>
    · 마켓 할인: 금괴 <strong>1개당 1%</strong> · 백금괴 <strong>1개당 10%</strong> (최대 <strong>50%</strong>) · 은괴는 할인 없음 · <strong>겜냥 전체미션</strong> 할인과 합산
    </div>
  </div>

<?php endif; ?>
</div>
<div class="gv-toast" id="gvToast" role="status"></div>

<?php if ($로그인): ?>
<script>
(function () {
  var CODE = <?php echo json_encode($code, JSON_UNESCAPED_UNICODE); ?>;
  var API = '/page/gold_vault_api.php';
  var EARLY_FEE_MULT = <?php echo (int)GOLD_BAR_EARLY_FEE_MULT; ?>;
  var FORCE_KEEP_PCT = <?php echo (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT; ?>;
  var FORCE_KEEP_FLOOR = <?php echo (int)GOLD_BAR_FORCE_EARLY_KEEP_FLOOR_PCT; ?>;
  var CASH_TERM = <?php echo (int)GOLD_VAULT_CASH_TERM_DAYS; ?>;
  var depositPct = 100;
  var currency = 'point'; // point=겜냥 · newpoint=본냥
  try {
    var savedCur = sessionStorage.getItem('gv_currency');
    if (savedCur === 'newpoint' || savedCur === 'point') {
      currency = savedCur;
    }
  } catch (e) {}
  function currencyLabel() {
    return currency === 'newpoint' ? '본방냥' : '게임냥';
  }
  function currencyShort() {
    return currency === 'newpoint' ? '본냥' : '겜냥';
  }
  function syncCurrencyTabs() {
    var curTabs = document.getElementById('gvCurrencyTabs');
    if (!curTabs) return;
    curTabs.querySelectorAll('[data-currency]').forEach(function (el) {
      var on = el.getAttribute('data-currency') === currency;
      el.classList.toggle('on', on);
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }
  syncCurrencyTabs();
  var lastBonMission = null;
  var lastGemMission = null;
  var lastOtherCurrencyCount = 0;
  var lastReadyCount = 0;
  var missionInnerTab = 'mission';

  function gvEsc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function syncMissionInnerTabs() {
    var tabs = document.getElementById('gvMissionInnerTabs');
    if (!tabs) return;
    tabs.querySelectorAll('[data-mission-tab]').forEach(function (el) {
      var on = el.getAttribute('data-mission-tab') === missionInnerTab;
      el.classList.toggle('on', on);
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var missionPanel = document.getElementById('gvMissionPanel');
    var rankPanel = document.getElementById('gvRankPanel');
    if (missionPanel) missionPanel.hidden = missionInnerTab !== 'mission';
    if (rankPanel) rankPanel.hidden = missionInnerTab !== 'rank';
  }

  function renderDepositRank(m) {
    var list = document.getElementById('gvRankList');
    var sub = document.getElementById('gvRankSub');
    if (sub) {
      sub.textContent = currencyShort() + ' 개인금고에 가장 많이 맡긴 순서 · 전체 예치 대비 비중';
    }
    if (!list) return;
    var rows = (m && m.rank_top) ? m.rank_top : [];
    if (!rows.length) {
      list.innerHTML = '<li class="gv-rank-empty">아직 예치한 친구가 없어요.</li>';
      return;
    }
    list.innerHTML = rows.map(function (r) {
      var n = parseInt(r.rank, 10) || 0;
      var pct = r.share_disp || '';
      return '<li class="gv-rank-item r' + n + '">'
        + '<span class="gv-rank-num">' + n + '위</span>'
        + '<span class="gv-rank-nick">' + gvEsc(r.nick) + '</span>'
        + '<span class="gv-rank-amt">' + gvEsc(r.amount_disp || '0냥')
        + (pct ? ('<span class="gv-rank-pct">' + gvEsc(pct) + '</span>') : '')
        + '</span>'
        + '</li>';
    }).join('');
  }

  function setCurrencyLabels() {
    var label = currencyLabel();
    var el1 = document.getElementById('gvBalanceUnitLabel');
    var el2 = document.getElementById('gvClaimUnitLabel');
    var el3 = document.getElementById('gvClaimUnitSuffix');
    if (el1) el1.textContent = label;
    if (el2) el2.textContent = label;
    if (el3) el3.textContent = label;
    renderActiveMission();
  }

  function renderActiveMission() {
    var m = currency === 'newpoint' ? lastBonMission : lastGemMission;
    renderCommunityMission(m);
  }

  function renderCommunityMission(m) {
    var box = document.getElementById('gvCommunityMission');
    if (!box) return;
    if (!m) {
      box.hidden = true;
      box.classList.remove('show');
      return;
    }
    box.hidden = false;
    box.classList.add('show');
    var title = document.getElementById('gvMissionTitle');
    var badge = document.getElementById('gvMissionBadge');
    var sub = document.getElementById('gvMissionSub');
    var fill = document.getElementById('gvMissionBarFill');
    var dep = document.getElementById('gvMissionDep');
    var tot = document.getElementById('gvMissionTotal');
    var ratio = document.getElementById('gvMissionRatio');
    var effect = document.getElementById('gvMissionEffect');
    var tiers = document.getElementById('gvMissionTiers');
    var reward = m.reward_label || (currency === 'newpoint' ? '강화비' : '마켓');
    if (title) title.textContent = m.title || '전체미션';
    if (badge) {
      badge.textContent = m.active ? ('할인 ' + (m.discount_pct || 0) + '%') : '전체미션';
      badge.classList.toggle('on', !!m.active);
    }
    if (sub) sub.textContent = m.subtitle || '';
    if (fill) fill.style.width = Math.max(0, Math.min(100, parseInt(m.bar_pct, 10) || 0)) + '%';
    if (dep) dep.textContent = m.deposited_disp || '0냥';
    if (tot) tot.textContent = m.total_disp || '0냥';
    if (ratio) ratio.textContent = m.ratio_disp || '0%';
    if (effect) {
      var msg = m.effect_label || '';
      if (m.next_need_pct && m.remain_to_next_disp) {
        msg += ' · 다음 ' + m.next_need_pct + '%까지 ' + m.remain_to_next_disp + ' 남음 → ' + reward + ' ' + (m.next_discount_pct || '') + '%';
      }
      effect.textContent = msg;
    }
    if (tiers) {
      var list = m.tiers || [];
      tiers.innerHTML = list.map(function (t) {
        var cls = t.reached ? ' done' : '';
        var mark = t.reached ? '✓ ' : '';
        var line = t.label
          ? String(t.label)
          : ('예치 ' + (t.need_pct || 0) + '% → ' + reward + ' ' + (t.discount_pct || 0) + '% 할인');
        return '<li class="' + cls.trim() + '">' + mark + line + '</li>';
      }).join('');
    }
    renderDepositRank(m);
    syncMissionInnerTabs();
  }
  function lockDaysForTerm(d) {
    d = parseInt(d, 10) || 0;
    if (d <= 7) return 4;
    if (d <= 14) return 7;
    return 14; // 구 30일 예치
  }
  function forceKeepHint() {
    return FORCE_KEEP_PCT + '%→' + FORCE_KEEP_FLOOR + '%';
  }
  var MAX_BY_KIND = <?php echo json_encode($maxByKind, JSON_UNESCAPED_UNICODE); ?>;
  var PAGE_SIZE = <?php echo (int)GOLD_VAULT_PAGE_SIZE; ?>;
  var busy = false;
  var cdTimer = null;
  var refreshSoon = false;
  var listPage = 1;
  var listFilter = 'all';
  var listPages = 1;

  function toast(msg) {
    var el = document.getElementById('gvToast');
    el.textContent = msg || '';
    el.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { el.classList.remove('show'); }, 2800);
  }

  function renderLedger(rows) {
    var box = document.getElementById('gvLedger');
    if (!box) return;
    if (!rows || !rows.length) {
      box.textContent = '아직 기록이 없어요.';
      return;
    }
    box.innerHTML = rows.map(function (r) {
      var when = String(r.regdate || '').slice(0, 16);
      var label = r.action_label || r.action || '';
      var cur = r.currency_label || '';
      var amt = r.amount_disp || '0';
      var memo = r.memo ? (' · ' + r.memo) : '';
      var bar = r.bar_idx ? (' #' + r.bar_idx) : '';
      var curTag = cur ? (' <span style="opacity:.8;">[' + cur + ']</span>') : '';
      return '<div style="padding:6px 0;border-bottom:1px solid rgba(0,0,0,.06);">'
        + '<b style="color:var(--gv-ink);">' + label + bar + '</b>' + curTag + ' · ' + amt
        + memo
        + '<div style="font-size:11px;opacity:.75;">' + when + '</div>'
        + '</div>';
    }).join('');
  }

  var ledgerToggle = document.getElementById('gvLedgerToggle');
  var ledgerBody = document.getElementById('gvLedgerBody');
  if (ledgerToggle && ledgerBody) {
    ledgerToggle.addEventListener('click', function () {
      var open = ledgerToggle.getAttribute('aria-expanded') === 'true';
      open = !open;
      ledgerToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      ledgerBody.hidden = !open;
    });
  }

  var rulesToggle = document.getElementById('gvRulesToggle');
  var rulesBody = document.getElementById('gvRulesBody');
  if (rulesToggle && rulesBody) {
    rulesToggle.addEventListener('click', function () {
      var open = rulesToggle.getAttribute('aria-expanded') === 'true';
      open = !open;
      rulesToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      rulesBody.hidden = !open;
    });
  }

  function pad2(n) {
    n = Math.max(0, Math.floor(n));
    return (n < 10 ? '0' : '') + n;
  }

  function formatCountdown(sec) {
    sec = Math.max(0, Math.floor(sec));
    var d = Math.floor(sec / 86400);
    var h = Math.floor((sec % 86400) / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (d > 0) return d + '일 ' + pad2(h) + ':' + pad2(m) + ':' + pad2(s);
    return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
  }

  function formatWhen(ts) {
    var d = new Date(ts * 1000);
    if (isNaN(d.getTime())) return '';
    return pad2(d.getMonth() + 1) + '/' + pad2(d.getDate()) + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
  }

  function tickCountdowns() {
    var nodes = document.querySelectorAll('.gv-item-next[data-next-ts]');
    var needRefresh = false;
    var now = Math.floor(Date.now() / 1000);
    nodes.forEach(function (el) {
      var ts = parseInt(el.getAttribute('data-next-ts'), 10) || 0;
      var cd = el.querySelector('.gv-cd');
      if (!ts || !cd) return;
      var left = ts - now;
      if (left <= 0) {
        cd.textContent = '00:00:00';
        needRefresh = true;
        return;
      }
      cd.textContent = formatCountdown(left);
    });
    if (needRefresh && !busy && !refreshSoon) {
      refreshSoon = true;
      setTimeout(function () {
        refreshSoon = false;
        if (busy) return;
        post('status', { with_list: '0' }).then(function (res) {
          if (res && res.ok) renderSummary(res);
        }).catch(function () {});
        loadList(listPage);
      }, 800);
    }
  }

  function startCountdown() {
    tickCountdowns();
    if (cdTimer) clearInterval(cdTimer);
    cdTimer = setInterval(tickCountdowns, 1000);
  }

  function post(action, extra) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('code', CODE);
    fd.append('currency', currency);
    if (extra) {
      Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
    }
    return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function barHtml(b) {
    var ready = !!b.matured;
    var rate = b.rate_disp || '10경/일';
    var label = b.bar_label || '금괴';
    var amt = b.amount_disp || '10해';
    var kind = b.bar_kind || 'gold';
    var earnedDays = b.accrued_days || 0;
    var term = b.term_days || '';
    var effTerm = b.effective_term || term;
    var extend = parseInt(b.extend_count, 10) || 0;
    var canExtend = !!b.can_extend;
    var claimable = b.claimable_disp || '0';
    var btns;
    if (kind === 'cash' || b.free_withdraw) {
      btns = '<button type="button" class="gv-btn gv-btn-sm gv-cash-one" data-idx="' + b.idx
        + '" data-label="' + label + '" data-payout="' + (b.early_disp || b.amount_disp || '') + '">빼기 (원금)</button>';
    } else if (ready) {
      btns = '';
      if (canExtend) {
        btns += '<button type="button" class="gv-btn gv-btn-warn gv-btn-sm gv-extend" data-idx="' + b.idx + '">30일 연장(+5%)</button>';
      }
      btns += '<span class="gv-item-hint">일괄만기해지만 가능</span>';
    } else if (b.early_locked || b.early_force) {
      var keepPct = parseInt(b.early_keep_pct, 10) || FORCE_KEEP_PCT;
      var lockDays = parseInt(b.early_lock_days, 10) || lockDaysForTerm(term);
      btns = '<button type="button" class="gv-btn gv-btn-warn gv-btn-sm gv-early" data-idx="' + b.idx
        + '" data-label="' + label + '" data-early="' + (b.early_disp || '') + '" data-fee="' + (b.early_fee_disp || '')
        + '" data-paid="' + (b.interest_disp || '') + '" data-force="1" data-keep="' + keepPct
        + '" data-lock-days="' + lockDays + '">강제해지(원금' + keepPct + '%)</button>';
    } else {
      btns = '<button type="button" class="gv-btn gv-btn-warn gv-btn-sm gv-early" data-idx="' + b.idx
        + '" data-label="' + label + '" data-early="' + (b.early_disp || '') + '" data-fee="' + (b.early_fee_disp || '')
        + '" data-paid="' + (b.interest_disp || '') + '" data-force="0" data-keep="' + FORCE_KEEP_PCT + '">중도해지</button>';
    }
    var bonusPct = parseInt(b.mature_bonus_pct, 10) || 0;
    var termLabel = extend > 0 ? (earnedDays + '/' + effTerm + '일(연장)') : (earnedDays + '/' + term + '일');
    var extra = ready
      ? ('만기 · 원금+보상 ' + (b.mature_disp || '')
          + (bonusPct > 0 ? (' (추가 ' + bonusPct + '% +' + (b.mature_bonus_disp || '0') + ')') : '')
          + (canExtend ? ' · 연장 시 총 15%' : '')
          + ' · 적립 이자 +' + (b.interest_disp || '') + ' / 총 ' + (b.mature_interest_disp || ''))
      : (termLabel + ' 적립 +' + (b.interest_disp || '') + ' · 수령대기 +' + claimable
          + (b.early_locked
            ? (' · 잠금' + (b.early_lock_days || '') + '일 · 강제해지 수수료 ' + (b.early_fee_disp || '')
                + (b.early_lock_until ? (' · ~' + b.early_lock_until) : ''))
            : (' · 중도 수수료 ' + (b.early_fee_disp || ''))));
    var nextTs = parseInt(b.next_interest_ts, 10) || 0;
    var next;
    if (ready) {
      next = '<p class="gv-item-next done">적립 완료 · 수령대기 +' + claimable + '</p>';
    } else if (nextTs > 0) {
      next = '<p class="gv-item-next" data-next-ts="' + nextTs + '">다음 적립 ' + formatWhen(nextTs)
        + ' · <span class="gv-cd">' + formatCountdown(nextTs - Math.floor(Date.now() / 1000)) + '</span> 후</p>';
    } else {
      next = '<p class="gv-item-next done">수령 가능 +' + claimable + '</p>';
    }
    var cls = (ready ? 'ready' : '') + (kind === 'plat' || kind === 'silver' ? ' ' + kind : '');
    return '<div class="gv-item ' + cls.trim() + '" data-idx="' + b.idx + '">' +
      '<div class="gv-item-bar' + (kind === 'plat' || kind === 'silver' ? ' ' + kind : '') + '" aria-hidden="true"></div>' +
      '<div class="gv-item-body">' +
        '<p class="gv-item-title">' + label + ' #' + b.idx + ' · ' + amt + ' · ' + term + '일 ' + rate + '</p>' +
        '<p class="gv-item-sub">' + (b.remain_label || '') + ' · ' + String(b.deposited_at || '').slice(0, 16) + '</p>' +
        '<p class="gv-item-extra">' + extra + '</p>' +
        next +
      '</div><div class="gv-item-actions">' + btns + '</div></div>';
  }

  function renderSummary(data) {
    if (!data || !data.ok) return;
    if (data.stat) {
      document.getElementById('gvTotal').innerHTML = (data.stat.total || 0) + '<span>개</span>';
      document.getElementById('gvLocked').textContent = data.stat.locked || 0;
      document.getElementById('gvReady').textContent = data.stat.ready || 0;
      var listCount = document.getElementById('gvListCount');
      if (listCount) listCount.textContent = (data.stat.total || 0) + '개';
      var tools = document.getElementById('gvListTools');
      if (tools) tools.hidden = !(data.stat.total > 0);
    }
    var balDisp = data.balance_disp || (currency === 'newpoint' ? data.newpoint_disp : data.point_disp);
    if (balDisp) {
      document.getElementById('gvPoint').textContent = balDisp;
    }
    if (data.point_disp) {
      var tp = document.getElementById('gvTabPointBal');
      if (tp) tp.textContent = data.point_disp;
    }
    if (data.newpoint_disp) {
      var tn = document.getElementById('gvTabNewpointBal');
      if (tn) tn.textContent = data.newpoint_disp;
    }
    setCurrencyLabels();
    if (data.bon_mission) {
      lastBonMission = data.bon_mission;
    }
    if (data.gem_mission) {
      lastGemMission = data.gem_mission;
    }
    renderActiveMission();
    var depAll = document.getElementById('gvDepositAll');
    if (depAll) {
      depAll.disabled = !(data.can_deposit_all || data.can_deposit);
    }
    var depLock = document.getElementById('gvDepositLock7');
    if (depLock) {
      depLock.disabled = !(data.can_deposit_lock7 || data.can_deposit_all || data.can_deposit);
    }
    var wCash = document.getElementById('gvWithdrawCash');
    if (wCash) {
      wCash.disabled = !data.can_withdraw_cash;
    }
    renderLedger(data.ledger || []);
    if (data.other_currency_count != null) {
      lastOtherCurrencyCount = parseInt(data.other_currency_count, 10) || 0;
    }

    var ticker = document.getElementById('gvTicker');
    var interestEl = document.getElementById('gvInterest');
    var dailyEl = document.getElementById('gvDailySum');
    var matureEl = document.getElementById('gvMatureSum');
    var totalBars = (data.stat && data.stat.total) ? data.stat.total : 0;
    var interestDisp = data.interest_earned_disp || (data.stat && data.stat.interest_disp) || '';
    var hasInterest = totalBars > 0 && interestDisp !== '' && interestDisp !== '0';
    if (ticker && interestEl && data.interest_earned_disp != null) {
      if (hasInterest) {
        ticker.classList.remove('empty');
        interestEl.innerHTML = '<span class="gv-ticker-plus">+</span>' + interestDisp;
      } else if (totalBars > 0) {
        ticker.classList.remove('empty');
        interestEl.innerHTML = '<span class="gv-ticker-plus">+</span>0';
      } else {
        ticker.classList.add('empty');
        interestEl.textContent = '금괴를 맡기면 이자가 표시돼요';
      }
    }
    if (dailyEl && data.interest_daily_disp != null) {
      dailyEl.textContent = '+' + (data.interest_daily_disp || ((data.stat && data.stat.daily_disp) ? data.stat.daily_disp : '0'));
    }
    if (matureEl && data.stat && data.stat.mature_interest_disp != null) {
      matureEl.textContent = '+' + (data.stat.mature_interest_disp || '0');
    }
    var daysLabel = document.getElementById('gvDaysLabel');
    if (daysLabel && data.interest_days_label) daysLabel.textContent = data.interest_days_label;
    var dailyCalc = document.getElementById('gvDailyCalc');
    if (dailyCalc && data.interest_daily_calc_label) dailyCalc.textContent = data.interest_daily_calc_label;

    var pending = document.getElementById('gvClaimPending');
    if (pending && (data.interest_payout_total_disp != null || data.interest_pending_disp != null)) {
      pending.innerHTML = (data.interest_payout_total_disp || data.interest_pending_disp || '0') + ' <span style="font-size:16px;color:var(--gv-muted);" id="gvClaimUnitSuffix">' + currencyLabel() + '</span>';
    }
    var earnedEl = document.getElementById('gvClaimEarned');
    if (earnedEl && data.interest_earned_disp != null) earnedEl.textContent = data.interest_earned_disp || '0';
    var claimedEl = document.getElementById('gvClaimed');
    if (claimedEl && data.interest_claimed_disp != null) claimedEl.textContent = data.interest_claimed_disp || '0';
    var claimDaily = document.getElementById('gvClaimDaily');
    if (claimDaily && data.interest_daily_disp != null) claimDaily.textContent = data.interest_daily_disp || '0';
    var claimBtn = document.getElementById('gvClaimInterest');
    if (claimBtn && data.interest_can_claim != null) claimBtn.disabled = !data.interest_can_claim;
    var bonusHint = document.getElementById('gvSilverBonusHint');
    var bonusEl = document.getElementById('gvSilverBonus');
    var interestOnly = document.getElementById('gvClaimInterestOnly');
    if (data.interest_silver_bonus_disp != null) {
      var bonusDisp = data.interest_silver_bonus_disp || '0';
      var hasBonus = bonusDisp !== '0' && bonusDisp !== '';
      if (bonusHint) bonusHint.hidden = !hasBonus;
      if (bonusEl) bonusEl.textContent = bonusDisp;
    }
    if (interestOnly && data.interest_pending_disp != null) interestOnly.textContent = data.interest_pending_disp || '0';

    var stage = document.getElementById('gvStage');
    if (stage && data.stat) {
      var show = Math.min(8, data.stat.total || 0);
      var html = '';
      for (var i = 0; i < show; i++) {
        html += '<div class="gv-bar-visual" style="animation-delay:' + (i * 0.05) + 's"></div>';
      }
      stage.innerHTML = html;
    }
    syncMatureAll(data);
  }

  function syncMatureAll(data) {
    var box = document.getElementById('gvMatureBox');
    var btn = document.getElementById('gvMatureAll');
    var hint = document.getElementById('gvMatureHint');
    if (!box || !btn) return;
    var n = lastReadyCount;
    if (data && data.stat && data.stat.ready != null) {
      n = parseInt(data.stat.ready, 10) || 0;
      lastReadyCount = n;
    } else if (data && data.can_mature_all) {
      n = Math.max(1, lastReadyCount);
      lastReadyCount = n;
    }
    var show = n > 0;
    box.hidden = !show;
    btn.disabled = !show;
    btn.textContent = show ? ('일괄만기해지 · ' + n + '건') : '일괄만기해지';
    if (hint) {
      hint.textContent = '만기된 ' + currencyShort() + ' 예치만 해지돼요 · 원금 + 만기 추가이자';
    }
  }

  function updatePager(data) {
    listPage = parseInt(data.bars_page, 10) || listPage;
    listPages = parseInt(data.bars_pages, 10) || 0;
    listFilter = data.bars_filter || listFilter;
    var pager = document.getElementById('gvPager');
    var meta = document.getElementById('gvPageMeta');
    var label = document.getElementById('gvPageLabel');
    var total = parseInt(data.bars_total, 10);
    if (isNaN(total) && data.stat) total = parseInt(data.stat.total, 10) || 0;
    if (meta) {
      if (total < 1) meta.textContent = '';
      else meta.textContent = '필터 ' + total + '개 · ' + listPage + '/' + Math.max(1, listPages) + '페이지';
    }
    if (pager) {
      pager.hidden = !(listPages > 1);
    }
    if (label) label.textContent = listPage + ' / ' + Math.max(1, listPages);
    var prev = document.getElementById('gvPrevPage');
    var next = document.getElementById('gvNextPage');
    if (prev) prev.disabled = listPage <= 1;
    if (next) next.disabled = listPage >= listPages;
    Array.prototype.forEach.call(document.querySelectorAll('.gv-filter'), function (el) {
      el.classList.toggle('on', el.getAttribute('data-filter') === listFilter);
    });
  }

  function renderList(data) {
    if (!data || !data.ok) return;
    var list = document.getElementById('gvList');
    if (!list) return;
    var bars = data.bars || [];
    updatePager(data);
    if (!bars.length) {
      var otherCnt = parseInt(data.other_currency_count, 10) || lastOtherCurrencyCount || 0;
      var otherLabel = data.other_currency_label || (currency === 'newpoint' ? '게임냥' : '본방냥');
      var emptyMsg = (parseInt(data.bars_total, 10) > 0)
        ? '이 필터에 해당하는 금괴가 없어요.'
        : '예치 중인 금괴가 없어요.';
      if (otherCnt > 0) {
        emptyMsg += '<br><span style="color:var(--gv-gold2);">※ ' + otherLabel + ' 탭에 예치 ' + otherCnt + '건이 있어요. 위 탭을 바꿔보세요.</span>';
      }
      list.innerHTML = '<div class="gv-empty">' + emptyMsg + '</div>';
      startCountdown();
      return;
    }
    list.innerHTML = bars.map(barHtml).join('');
    startCountdown();
  }

  function render(data) {
    renderSummary(data);
    if (data && data.bars) renderList(data);
  }

  function loadList(page) {
    listPage = page || 1;
    return post('list', { page: listPage, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
      if (res && res.ok) {
        // 목록 응답은 이자요약이 비어 있음(빠른통계) — 잔액/풀만 갱신, 이자는 status가 담당
        if (res.point_disp != null) {
          var pt = document.getElementById('gvPoint');
          if (pt) pt.textContent = res.point_disp;
        }
        if (res.pool) {
          var poolUsed = document.getElementById('gvPoolUsed');
          var poolMax = document.getElementById('gvPoolMax');
          var poolRemain = document.getElementById('gvPoolRemain');
          if (poolUsed && res.pool.used_disp != null) poolUsed.textContent = res.pool.used_disp;
          if (poolMax && res.pool.max_disp != null) poolMax.textContent = res.pool.max_disp;
          if (poolRemain && res.pool.remain_disp != null) poolRemain.textContent = res.pool.remain_disp;
        }
        var depAll = document.getElementById('gvDepositAll');
        if (depAll) depAll.disabled = !(res.can_deposit_all || res.can_deposit);
        var depLock = document.getElementById('gvDepositLock7');
        if (depLock) depLock.disabled = !(res.can_deposit_lock7 || res.can_deposit_all || res.can_deposit);
        var wCash = document.getElementById('gvWithdrawCash');
        if (wCash) wCash.disabled = !res.can_withdraw_cash;
        renderList(res);
        syncMatureAll(res);
      }
      return res;
    });
  }

  function refreshAll() {
    return post('status', {
      with_list: '1',
      page: listPage,
      filter: listFilter,
      page_size: PAGE_SIZE
    }).then(function (res) {
      if (res && res.ok) render(res);
      return res;
    });
  }


  var curTabs = document.getElementById('gvCurrencyTabs');
  if (curTabs) {
    curTabs.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-currency]');
      if (!btn || busy) return;
      var next = btn.getAttribute('data-currency') === 'newpoint' ? 'newpoint' : 'point';
      if (next === currency) return;
      currency = next;
      try { sessionStorage.setItem('gv_currency', currency); } catch (err) {}
      curTabs.querySelectorAll('[data-currency]').forEach(function (el) {
        var on = el.getAttribute('data-currency') === currency;
        el.classList.toggle('on', on);
        el.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      setCurrencyLabels();
      listPage = 1;
      lastReadyCount = 0;
      syncMatureAll({ stat: { ready: 0 } });
      refreshAll();
    });
  }
  var missionInnerTabs = document.getElementById('gvMissionInnerTabs');
  if (missionInnerTabs) {
    missionInnerTabs.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-mission-tab]');
      if (!btn) return;
      var next = btn.getAttribute('data-mission-tab') === 'rank' ? 'rank' : 'mission';
      if (next === missionInnerTab) return;
      missionInnerTab = next;
      syncMissionInnerTabs();
    });
  }
  syncMissionInnerTabs();
  setCurrencyLabels();

  document.getElementById('gvClaimInterest').addEventListener('click', function () {
    if (busy) return;
    if (!confirm('적립된 ' + currencyLabel() + ' 이자를 수령할까요?\n(은괴는 미수령 이자의 수령보너스가 함께 지급돼요)')) return;
    busy = true;
    post('claim_interest', { page: listPage, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
      busy = false;
      if (!res.ok) { toast(res.msg || '실패했어요'); if (res.stat) render(Object.assign({}, res, { ok: true })); return; }
      toast(res.msg || '수령했어요');
      render(res);
    }).catch(function () { busy = false; toast('네트워크 오류'); });
  });

  var pctBox = document.getElementById('gvDepositPct');
  if (pctBox) {
    pctBox.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-pct]');
      if (!btn) return;
      depositPct = parseInt(btn.getAttribute('data-pct'), 10) === 50 ? 50 : 100;
      pctBox.querySelectorAll('[data-pct]').forEach(function (el) {
        el.classList.toggle('on', parseInt(el.getAttribute('data-pct'), 10) === depositPct);
      });
    });
  }

  var depositAllBtn = document.getElementById('gvDepositAll');
  if (depositAllBtn) {
    depositAllBtn.addEventListener('click', function () {
      if (busy) return;
      var pt = (document.getElementById('gvPoint') || {}).textContent || '';
      if (!confirm('보유 ' + currencyLabel() + '의 ' + depositPct + '%를 「자유예치」로 맡길까요?\n\n현재 보유: ' + pt + '\n· 넣다 빼기 자유\n· 만 24시간마다 1%\n· 24시간 미만은 이자 없음\n· 이자는 수령 가능 이자에 쌓여요\n· ' + currencyShort() + ' 탭 전용')) return;
      busy = true;
      post('deposit_all', { page: 1, filter: listFilter, page_size: PAGE_SIZE, pct: depositPct }).then(function (res) {
        busy = false;
        if (!res.ok) { toast(res.msg || '실패했어요'); if (res.stat) render(Object.assign({}, res, { ok: true })); return; }
        toast(res.msg || '맡겼어요');
        listPage = 1;
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
    });
  }

  var depositLockBtn = document.getElementById('gvDepositLock7');
  if (depositLockBtn) {
    depositLockBtn.addEventListener('click', function () {
      if (busy) return;
      var pt = (document.getElementById('gvPoint') || {}).textContent || '';
      if (!confirm('보유 ' + currencyLabel() + '의 ' + depositPct + '%를 「7일 잠금」으로 맡길까요?\n\n현재 보유: ' + pt + '\n· 맡긴 즉시 1일분 이자(1%) 수령 가능\n· 이후 24시간마다 1%\n· 만기해지 시 원금 +5% 추가이자\n· 처음 2일 잠금\n· 중도해지: 원금에서 50%→40%→30%→20% 차감 후 환급\n· ' + currencyShort() + ' 탭 전용')) return;
      busy = true;
      post('deposit_lock7', { page: 1, filter: listFilter, page_size: PAGE_SIZE, pct: depositPct }).then(function (res) {
        busy = false;
        if (!res.ok) { toast(res.msg || '실패했어요'); if (res.stat) render(Object.assign({}, res, { ok: true })); return; }
        toast(res.msg || '맡겼어요');
        listPage = 1;
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
    });
  }

  var matureAllBtn = document.getElementById('gvMatureAll');
  if (matureAllBtn) {
    matureAllBtn.addEventListener('click', function () {
      if (busy) return;
      var n = lastReadyCount || parseInt((document.getElementById('gvReady') || {}).textContent, 10) || 0;
      if (n < 1) return;
      if (!confirm('만기된 ' + currencyLabel() + ' 예치 ' + n + '건만 해지할까요?\n\n· 원금 + 만기 추가이자 지급\n· 미수령 이자는 수령 가능 이자에 적립\n· 아직 만기 전인 건은 그대로 둡니다')) return;
      busy = true;
      post('withdraw_all_mature', { page: 1, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
        busy = false;
        if (!res.ok) {
          toast(res.msg || '실패했어요');
          if (res.stat) render(Object.assign({}, res, { ok: true }));
          return;
        }
        toast(res.msg || '만기해지했어요');
        listPage = 1;
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
    });
  }

  var withdrawCashBtn = document.getElementById('gvWithdrawCash');
  if (withdrawCashBtn) {
    withdrawCashBtn.addEventListener('click', function () {
      if (busy) return;
      if (!confirm(currencyLabel() + ' 자유예치를 모두 뺄까요?\n· 원금은 바로 같은 통화로 지급\n· 이자는 「수령 가능 이자」에 쌓여요\n· 24시간 미만이면 이자 없음')) return;
      busy = true;
      post('withdraw_cash', { page: 1, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
        busy = false;
        if (!res.ok) { toast(res.msg || '실패했어요'); if (res.stat) render(Object.assign({}, res, { ok: true })); return; }
        toast(res.msg || '뺐어요');
        listPage = 1;
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
    });
  }

  var filterBox = document.getElementById('gvListTools');
  if (filterBox) {
    filterBox.addEventListener('click', function (e) {
      var btn = e.target.closest('.gv-filter');
      if (!btn || busy) return;
      listFilter = btn.getAttribute('data-filter') || 'all';
      loadList(1);
    });
  }
  var prevBtn = document.getElementById('gvPrevPage');
  var nextBtn = document.getElementById('gvNextPage');
  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      if (busy || listPage <= 1) return;
      loadList(listPage - 1);
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      if (busy || listPage >= listPages) return;
      loadList(listPage + 1);
    });
  }

  document.getElementById('gvList').addEventListener('click', function (e) {
    var cashOne = e.target.closest('.gv-cash-one');
    var earlyBtn = e.target.closest('.gv-early');
    var extendBtn = e.target.closest('.gv-extend');
    if ((!cashOne && !earlyBtn && !extendBtn) || busy) return;

    if (cashOne) {
      var cidx = cashOne.getAttribute('data-idx');
      var clabel = cashOne.getAttribute('data-label') || '자유예치';
      var cpayout = cashOne.getAttribute('data-payout') || '';
      if (!confirm(clabel + ' #' + cidx + ' 를 뺄까요?\n원금 ' + cpayout + ' 즉시 지급 · 이자는 수령 가능 이자에 적립')) return;
      busy = true;
      post('early', { bar_idx: cidx, page: listPage, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
        busy = false;
        if (!res.ok) { toast(res.msg || '실패했어요'); return; }
        toast(res.msg || '뺐어요');
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
      return;
    }

    if (extendBtn) {
      var xidx = extendBtn.getAttribute('data-idx');
      if (!confirm('금괴 #' + xidx + '를 30일 연장할까요?\n한 달 더 적립 후 일괄만기해지 시 추가보상 15%(10%+5%)')) return;
      busy = true;
      post('extend', { bar_idx: xidx, page: listPage, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
        busy = false;
        if (!res.ok) { toast(res.msg || '실패했어요'); return; }
        toast(res.msg || '연장했어요');
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
      return;
    }

    if (earlyBtn) {
      var eidx = earlyBtn.getAttribute('data-idx');
      var elabel = earlyBtn.getAttribute('data-label') || '금괴';
      var ed = earlyBtn.getAttribute('data-early') || '';
      var fee = earlyBtn.getAttribute('data-fee') || '';
      var paid = earlyBtn.getAttribute('data-paid') || '';
      var isForce = earlyBtn.getAttribute('data-force') === '1';
      var keepPct = parseInt(earlyBtn.getAttribute('data-keep'), 10) || FORCE_KEEP_PCT;
      var lockDays = earlyBtn.getAttribute('data-lock-days') || '';
      if (isForce) {
        if (!confirm(elabel + ' #' + eidx + ' 중도해지할까요?\n원금에서 ' + (100 - keepPct) + '% 차감 · 환급 ' + keepPct + '% ' + ed + '\n위약금 ' + fee + '\n미수령 이자는 수령 가능 이자에 쌓여요')) return;
      } else if (!confirm(elabel + ' #' + eidx + ' 중도해지할까요?\n받은 이자 ' + paid + ' ×' + EARLY_FEE_MULT + ' = 수수료 ' + fee + '\n원금 환급 ' + ed)) {
        return;
      }
      busy = true;
      post('early', { bar_idx: eidx, page: listPage, filter: listFilter, page_size: PAGE_SIZE }).then(function (res) {
        busy = false;
        if (!res.ok) { toast(res.msg || '실패했어요'); return; }
        toast(res.msg || '중도해지했어요');
        render(res);
      }).catch(function () { busy = false; toast('네트워크 오류'); });
      return;
    }
  });

  // 첫 로딩: 목록(빠름) + 이자요약(대량 시 조금 걸림) 분리
  loadList(1).catch(function () {});
  post('status', { with_list: '0' }).then(function (res) {
    if (res && res.ok) renderSummary(res);
  }).catch(function () {});

  setInterval(function () {
    if (busy) return;
    post('status', { with_list: '0' }).then(function (res) {
      if (res && res.ok) renderSummary(res);
    }).catch(function () {});
  }, 60000);

  startCountdown();
})();
</script>
<?php endif; ?>
<?php
if ($code !== '') {
  require_once __DIR__ . '/../api/game/wallet_nav_fab.inc.php';
  wallet_nav_fab_render(['code' => $code]);
}
?>
</body>
</html>
