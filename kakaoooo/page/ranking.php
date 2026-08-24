<?php
/**
 * 랭킹 — 버프타수 | 타수 | 본냥 | 겜냥 | 무기 | 장비
 * URL: /page/ranking.php?code=XXXX&tab=buff|tasu|bon|game|weapon|equip
 */
require __DIR__ . '/_wallet_preauth.php';
include_once __DIR__ . '/_ranking_lib.php';

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

$날짜 = rk_오늘날짜();
$버프목록 = rk_버프타수목록($날짜);
$타수목록 = rk_타수목록($날짜);
$본냥목록 = rk_본냥목록();
$겜냥목록 = rk_겜냥목록();
$무기목록 = rk_무기목록();
$장비목록 = rk_장비목록();

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'buff';
$허용탭 = ['buff', 'tasu', 'bon', 'game', 'weapon', 'equip'];
if (!in_array($tab, $허용탭, true)) {
    $tab = 'buff';
}

$날짜표시 = $날짜;
try {
    $날짜표시 = (new DateTime($날짜, new DateTimeZone('Asia/Seoul')))->format('n월 j일');
} catch (Throwable $e) {
}

$전체탭 = ['bon', 'game', 'weapon', 'equip'];
$힌트맵 = [
    'buff'   => '오늘 버프가 반영된 타수 순위',
    'tasu'   => '오늘 생타 · 채팅 로우 + 사진(+2)',
    'bon'    => '본냥(보유냥) 높은 순 · .랭킹1',
    'game'   => '겜냥(게임냥) 높은 순 · .랭킹2',
    'weapon' => '보유 무기 강화 높은 순',
    'equip'  => '채굴 장비 레벨 높은 순',
];

$메달 = [1 => '🥇', 2 => '🥈', 3 => '🥉'];

/** 큰 숫자 문자열 비율 (0~100) */
function rk_pct($val, $maxVal, $minPct = 2): int {
    $val = preg_replace('/[^\d]/', '', (string)$val) ?: '0';
    $maxVal = preg_replace('/[^\d]/', '', (string)$maxVal) ?: '0';
    if ($maxVal === '0') {
        return $minPct;
    }
    if (function_exists('bccomp') && function_exists('bcmul') && function_exists('bcdiv')) {
        if (bccomp($maxVal, '0', 0) <= 0) {
            return $minPct;
        }
        $pct = (int)bcdiv(bcmul($val, '100', 0), $maxVal, 0);
        return min(100, max($minPct, $pct));
    }
    $mf = (float)$maxVal;
    if ($mf <= 0) {
        return $minPct;
    }
    return min(100, max($minPct, (int)round(((float)$val / $mf) * 100)));
}

/**
 * @param list<array> $list
 * @param callable $valFn
 */
function rk_render_panel(string $id, string $activeTab, string $tabKey, array $list, string $nick, array $메달, callable $valFn, string $emptyMsg): void {
    $isActive = $activeTab === $tabKey;
    $top = array_slice($list, 0, 3);
    $rest = array_slice($list, 3);
    $maxVal = '1';
    if (!empty($list)) {
        $maxVal = (string)$valFn($list[0]);
        if ($maxVal === '' || $maxVal === '0') {
            $maxVal = '1';
        }
    }
    echo '<div class="rk-panel' . ($isActive ? ' active' : '') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" role="tabpanel" data-tab="' . htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') . '">';
    if (empty($list)) {
        echo '<div class="rk-empty"><span class="rk-empty-ico">∅</span>' . htmlspecialchars($emptyMsg, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
        return;
    }

    $podiumOrder = [];
    if (isset($top[1])) $podiumOrder[] = $top[1];
    if (isset($top[0])) $podiumOrder[] = $top[0];
    if (isset($top[2])) $podiumOrder[] = $top[2];
    $podCount = count($podiumOrder);

    if ($podCount >= 1) {
        echo '<div class="rk-podium rk-podium-' . $podCount . '">';
        foreach ($podiumOrder as $row) {
            $rank = (int)$row['rank'];
            $isMe = ($nick !== '' && $row['nick'] === $nick);
            $pct = rk_pct($valFn($row), $maxVal, 8);
            $meta = rk_row_meta($row, $tabKey);
            $isMoney = ($tabKey === 'bon' || $tabKey === 'game');
            echo '<div class="rk-pod rk-pod-' . $rank . ($isMe ? ' is-me' : '') . ($isMoney ? ' is-money' : '') . '" style="--delay:' . (($rank === 1) ? '0.08s' : ($rank === 2 ? '0.16s' : '0.24s')) . '">';
            echo '<div class="rk-pod-crown">' . ($메달[$rank] ?? '') . '</div>';
            echo '<div class="rk-pod-avatar">' . htmlspecialchars(mb_substr($row['nick'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="rk-pod-nick">' . htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') . ($isMe ? ' ·나' : '') . '</div>';
            if ($meta !== '') {
                echo '<div class="rk-pod-meta">' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '<div class="rk-pod-val">' . rk_format_val($row, $tabKey) . '</div>';
            echo '<div class="rk-pod-bar"><i style="height:' . $pct . '%"></i></div>';
            echo '</div>';
        }
        echo '</div>';
    }

    if (!empty($rest)) {
        echo '<div class="rk-list">';
        $isMoneyTab = ($tabKey === 'bon' || $tabKey === 'game');
        foreach ($rest as $i => $row) {
            $rank = (int)$row['rank'];
            $isMe = ($nick !== '' && $row['nick'] === $nick);
            $pct = rk_pct($valFn($row), $maxVal, 2);
            $meta = rk_row_meta($row, $tabKey);
            $valHtml = rk_format_val($row, $tabKey);
            echo '<div class="rk-row' . ($isMe ? ' is-me' : '') . ($isMoneyTab ? ' is-money-row' : '') . '" style="--i:' . (int)$i . '">';
            echo '<div class="rk-rank">' . $rank . '</div>';
            echo '<div class="rk-body">';
            echo '<div class="rk-nick-row"><span class="rk-nick">' . htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') . '</span>';
            if ($isMe) echo '<span class="rk-me-badge">ME</span>';
            echo '</div>';
            if ($meta !== '') {
                echo '<div class="rk-sub">' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '<div class="rk-track"><i style="width:' . $pct . '%"></i></div>';
            if ($isMoneyTab) {
                echo '<div class="rk-cnt is-money">' . $valHtml . '</div>';
            }
            echo '</div>';
            if (!$isMoneyTab) {
                echo '<div class="rk-cnt">' . $valHtml . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function rk_row_meta(array $row, string $tabKey): string {
    if ($tabKey === 'buff') {
        return '생타 ' . number_format((int)$row['raw']);
    }
    if ($tabKey === 'tasu') {
        return '버프 ' . number_format((int)$row['buff']);
    }
    if ($tabKey === 'bon' || $tabKey === 'game') {
        return 'Lv.' . (int)$row['level'] . ($row['title'] !== '' ? ' · ' . $row['title'] : '');
    }
    if ($tabKey === 'weapon') {
        return trim(($row['title'] !== '' ? $row['title'] . ' · ' : '') . $row['weapon'] . ($row['style'] !== '' ? ' · ' . $row['style'] : ''));
    }
    if ($tabKey === 'equip') {
        return trim(($row['title'] !== '' ? $row['title'] . ' · ' : '') . 'Lv.' . (int)$row['level']);
    }
    return '';
}

/** 억/만 등 단위 뒤에 공백을 넣어 줄바꿈이 단위 경계에서 일어나게 */
function rk_money_spaced(string $disp): string {
    $disp = trim($disp);
    if ($disp === '') {
        return $disp;
    }
    // 225억7,004만586 → 225억 7,004만 586
    $disp = preg_replace('/([해경조억만])(?=[\d])/u', '$1 ', $disp);
    return (string)$disp;
}

function rk_format_val(array $row, string $tabKey): string {
    if ($tabKey === 'buff') {
        return number_format((int)$row['buff']);
    }
    if ($tabKey === 'tasu') {
        return number_format((int)$row['raw']);
    }
    if ($tabKey === 'bon') {
        $amt = $row['amount'] ?? '0';
        $disp = function_exists('newpoint표시')
            ? (string)newpoint표시($amt)
            : number_format((float)$amt);
        return htmlspecialchars(rk_money_spaced($disp), ENT_QUOTES, 'UTF-8');
    }
    if ($tabKey === 'game') {
        $amt = $row['amount'] ?? '0';
        $disp = function_exists('랭킹_게임냥표시')
            ? (string)랭킹_게임냥표시($amt, '')
            : number_format((float)$amt);
        if (!empty($row['signed']) && $disp !== '' && $disp !== '0' && substr($disp, 0, 1) !== '-') {
            $disp = '-' . $disp;
        }
        return htmlspecialchars(rk_money_spaced($disp), ENT_QUOTES, 'UTF-8');
    }
    if ($tabKey === 'weapon') {
        return '+' . (int)$row['enhance'];
    }
    if ($tabKey === 'equip') {
        return htmlspecialchars(($row['tool_icon'] ?? '') . ' ' . ($row['tool_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#07110e">
<title>랭킹</title>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<style>
@import url('https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css');
:root {
  --bg: #07110e;
  --card: rgba(18, 36, 30, 0.72);
  --line: rgba(110, 231, 183, 0.14);
  --text: #eef6f1;
  --muted: rgba(238, 246, 241, 0.55);
  --mint: #6ee7b7;
  --mint-dim: rgba(110, 231, 183, 0.14);
  --gold: #f5c542;
  --silver: #c7d2de;
  --bronze: #d4a27c;
  --me: #fb7185;
  --accent: var(--mint);
}
* { box-sizing: border-box; }
html { -webkit-tap-highlight-color: transparent; }
body {
  margin: 0;
  min-height: 100dvh;
  color: var(--text);
  font-family: "Pretendard Variable", Pretendard, "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
  background:
    radial-gradient(90% 55% at 50% -8%, rgba(245, 197, 66, 0.16) 0%, transparent 52%),
    radial-gradient(70% 45% at 100% 10%, rgba(110, 231, 183, 0.12) 0%, transparent 50%),
    radial-gradient(60% 40% at 0% 30%, rgba(56, 189, 248, 0.08) 0%, transparent 55%),
    linear-gradient(180deg, #0c1a15 0%, var(--bg) 42%, #050a08 100%);
  background-attachment: fixed;
}
.rk-wrap {
  max-width: 480px;
  margin: 0 auto;
  padding: 10px 14px calc(28px + env(safe-area-inset-bottom));
  position: relative;
}
.rk-wrap::before {
  content: "";
  position: fixed;
  inset: 0;
  pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  opacity: 0.35;
  mix-blend-mode: soft-light;
  z-index: 0;
}
.rk-wrap > * { position: relative; z-index: 1; }

.rk-top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}
.rk-back {
  color: var(--muted);
  text-decoration: none;
  font-size: 0.86rem;
  font-weight: 700;
  padding: 8px 0;
  transition: color .2s;
}
.rk-back:active { color: var(--mint); }
.rk-date {
  margin-left: auto;
  font-size: 0.74rem;
  color: var(--muted);
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
}

.rk-hero {
  text-align: center;
  padding: 8px 0 18px;
  animation: rkFadeDown .5s ease both;
}
.rk-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 8px;
}
.rk-hero-badge span {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--gold);
  box-shadow: 0 0 12px rgba(245, 197, 66, 0.7);
  animation: rkPulse 2s ease-in-out infinite;
}
.rk-hero h1 {
  margin: 0;
  font-size: 1.85rem;
  font-weight: 900;
  letter-spacing: -0.04em;
  background: linear-gradient(135deg, #fff8e7 10%, var(--gold) 45%, var(--mint) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  line-height: 1.15;
}
.rk-hero p {
  margin: 8px 0 0;
  font-size: 0.82rem;
  color: var(--muted);
  font-weight: 600;
  min-height: 1.3em;
}

.rk-tabs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
  margin-bottom: 16px;
  animation: rkFadeDown .55s ease both;
  animation-delay: .05s;
}
.rk-tab {
  appearance: none;
  border: 1px solid transparent;
  background: rgba(0,0,0,0.28);
  color: var(--muted);
  font: inherit;
  font-weight: 800;
  font-size: 0.74rem;
  letter-spacing: -0.03em;
  border-radius: 14px;
  padding: 10px 4px 9px;
  cursor: pointer;
  line-height: 1.15;
  transition: transform .15s, background .2s, border-color .2s, color .2s, box-shadow .2s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.rk-tab .ico { font-size: 1.05rem; line-height: 1; filter: grayscale(.35); opacity: .75; transition: filter .2s, opacity .2s, transform .2s; }
.rk-tab.active {
  background: linear-gradient(180deg, rgba(110,231,183,0.18), rgba(110,231,183,0.06));
  color: var(--mint);
  border-color: rgba(110, 231, 183, 0.35);
  box-shadow: 0 8px 24px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.06);
}
.rk-tab.active .ico { filter: none; opacity: 1; transform: scale(1.08); }
.rk-tab:active { transform: scale(0.96); }

.rk-panel { display: none; }
.rk-panel.active {
  display: block;
  animation: rkPanelIn .38s cubic-bezier(.2,.8,.2,1) both;
}

.rk-podium {
  display: grid;
  grid-template-columns: 1fr 1.12fr 1fr;
  gap: 8px;
  align-items: end;
  margin-bottom: 14px;
  min-height: 196px;
}
.rk-podium-1 {
  grid-template-columns: minmax(140px, 220px);
  justify-content: center;
  min-height: 0;
}
.rk-podium-2 {
  grid-template-columns: 1fr 1fr;
  min-height: 0;
}
.rk-pod {
  position: relative;
  text-align: center;
  padding: 14px 8px 12px;
  border-radius: 18px;
  background: var(--card);
  border: 1px solid var(--line);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  overflow: hidden;
  animation: rkPodUp .55s cubic-bezier(.2,.85,.25,1) both;
  animation-delay: var(--delay, 0s);
}
.rk-pod::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--accent), transparent);
  opacity: .7;
}
.rk-pod-1 {
  --accent: var(--gold);
  border-color: rgba(245, 197, 66, 0.35);
  background:
    radial-gradient(80% 60% at 50% 0%, rgba(245,197,66,0.22), transparent 70%),
    var(--card);
  margin-bottom: 10px;
  box-shadow: 0 16px 36px rgba(0,0,0,0.28);
  padding-top: 18px;
  padding-bottom: 16px;
}
.rk-pod-2 { --accent: var(--silver); border-color: rgba(199,210,222,0.28); }
.rk-pod-3 { --accent: #d4a27c; border-color: rgba(212,162,124,0.3); }
.rk-pod.is-me {
  box-shadow: 0 0 0 1px rgba(251,113,133,0.45), 0 12px 28px rgba(0,0,0,0.25);
}
.rk-pod-crown {
  font-size: 1.2rem;
  line-height: 1;
  margin-bottom: 6px;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.35));
}
.rk-pod-1 .rk-pod-crown { font-size: 1.45rem; animation: rkFloat 2.4s ease-in-out infinite; }
.rk-pod-avatar {
  width: 42px; height: 42px;
  margin: 0 auto 8px;
  border-radius: 14px;
  display: grid; place-items: center;
  font-weight: 900;
  font-size: 1.05rem;
  color: #0a1210;
  background: linear-gradient(145deg, #fff7df, var(--accent));
  box-shadow: 0 6px 16px rgba(0,0,0,0.25);
}
.rk-pod-1 .rk-pod-avatar { width: 50px; height: 50px; font-size: 1.2rem; border-radius: 16px; }
.rk-pod-nick {
  font-size: 0.82rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rk-pod-1 .rk-pod-nick { font-size: 0.92rem; }
.rk-pod-meta {
  margin-top: 3px;
  font-size: 0.64rem;
  color: var(--muted);
  font-weight: 650;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rk-pod-val {
  margin-top: 8px;
  font-size: 0.82rem;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  color: var(--accent);
  letter-spacing: -0.02em;
  word-break: break-all;
  line-height: 1.25;
}
.rk-pod-1 .rk-pod-val { font-size: 0.92rem; color: var(--gold); }
.rk-pod-bar {
  margin: 10px auto 0;
  width: 10px;
  height: 48px;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  overflow: hidden;
  display: flex;
  align-items: flex-end;
}
.rk-pod-1 .rk-pod-bar { height: 64px; width: 12px; }
.rk-pod-bar i {
  display: block;
  width: 100%;
  border-radius: inherit;
  background: linear-gradient(180deg, #fff, var(--accent));
  box-shadow: 0 0 12px color-mix(in srgb, var(--accent) 55%, transparent);
  animation: rkBarGrow .7s cubic-bezier(.2,.85,.25,1) both;
  animation-delay: calc(var(--delay, 0s) + .15s);
}

.rk-list {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 18px;
  overflow: hidden;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}
.rk-row {
  display: grid;
  grid-template-columns: 40px 1fr auto;
  gap: 10px;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.045);
  animation: rkRowIn .4s ease both;
  animation-delay: calc(var(--i, 0) * 0.028s);
}
.rk-row.is-money-row {
  grid-template-columns: 40px 1fr;
  align-items: start;
}
.rk-row:last-child { border-bottom: 0; }
.rk-row.is-me {
  background: linear-gradient(90deg, rgba(251,113,133,0.14), rgba(251,113,133,0.03));
  box-shadow: inset 3px 0 0 var(--me);
}
.rk-rank {
  width: 32px; height: 32px;
  margin: 0 auto;
  border-radius: 10px;
  display: grid; place-items: center;
  font-size: 0.78rem;
  font-weight: 900;
  color: var(--muted);
  background: rgba(255,255,255,0.04);
}
.rk-body { min-width: 0; }
.rk-nick-row {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}
.rk-nick {
  font-size: 0.94rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rk-me-badge {
  flex-shrink: 0;
  font-size: 0.58rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  color: #fff;
  background: var(--me);
  border-radius: 5px;
  padding: 2px 5px;
}
.rk-sub {
  margin-top: 2px;
  font-size: 0.7rem;
  font-weight: 650;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rk-track {
  margin-top: 7px;
  height: 4px;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  overflow: hidden;
}
.rk-track i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, rgba(110,231,183,0.25), var(--mint));
}
.rk-cnt {
  font-variant-numeric: tabular-nums;
  font-weight: 900;
  font-size: 0.95rem;
  color: var(--mint);
  text-align: right;
  white-space: nowrap;
  letter-spacing: -0.02em;
}
.rk-cnt.is-money {
  display: block;
  margin-top: 8px;
  width: 100%;
  max-width: none;
  font-size: 0.88rem;
  font-weight: 850;
  text-align: left;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: keep-all;
  line-height: 1.35;
  letter-spacing: -0.01em;
}
.rk-pod.is-money .rk-pod-val {
  font-size: 0.72rem;
  white-space: normal;
  word-break: keep-all;
  overflow-wrap: anywhere;
}

.rk-empty {
  padding: 48px 16px;
  text-align: center;
  color: var(--muted);
  font-weight: 700;
  line-height: 1.5;
  background: var(--card);
  border: 1px dashed var(--line);
  border-radius: 18px;
}
.rk-empty-ico {
  display: block;
  font-size: 1.4rem;
  opacity: .45;
  margin-bottom: 8px;
}
.rk-summary {
  margin-top: 14px;
  text-align: center;
  font-size: 0.78rem;
  color: var(--muted);
  font-weight: 700;
  letter-spacing: 0.02em;
}
.rk-summary b { color: var(--mint); font-weight: 900; }

@keyframes rkFadeDown {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: none; }
}
@keyframes rkPanelIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: none; }
}
@keyframes rkPodUp {
  from { opacity: 0; transform: translateY(18px) scale(.96); }
  to { opacity: 1; transform: none; }
}
@keyframes rkBarGrow {
  from { transform: scaleY(0); transform-origin: bottom; }
  to { transform: scaleY(1); transform-origin: bottom; }
}
@keyframes rkRowIn {
  from { opacity: 0; transform: translateX(-6px); }
  to { opacity: 1; transform: none; }
}
@keyframes rkFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
@keyframes rkPulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: .55; transform: scale(.85); }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
</head>
<body>
<div class="rk-wrap">
  <div class="rk-top">
    <a class="rk-back" href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 가방</a>
    <div class="rk-date" id="rkDate"><?= in_array($tab, $전체탭, true) ? '전체' : htmlspecialchars($날짜표시, ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <header class="rk-hero">
    <div class="rk-hero-badge"><span></span> HALL OF FAME</div>
    <h1>랭킹</h1>
    <p id="rkHint"><?= htmlspecialchars($힌트맵[$tab], ENT_QUOTES, 'UTF-8') ?></p>
  </header>

  <div class="rk-tabs" role="tablist">
    <button type="button" class="rk-tab<?= $tab === 'buff' ? ' active' : '' ?>" data-tab="buff" role="tab" aria-selected="<?= $tab === 'buff' ? 'true' : 'false' ?>"><span class="ico">✨</span>버프타수</button>
    <button type="button" class="rk-tab<?= $tab === 'tasu' ? ' active' : '' ?>" data-tab="tasu" role="tab" aria-selected="<?= $tab === 'tasu' ? 'true' : 'false' ?>"><span class="ico">💬</span>타수</button>
    <button type="button" class="rk-tab<?= $tab === 'bon' ? ' active' : '' ?>" data-tab="bon" role="tab" aria-selected="<?= $tab === 'bon' ? 'true' : 'false' ?>"><span class="ico">💎</span>본냥</button>
    <button type="button" class="rk-tab<?= $tab === 'game' ? ' active' : '' ?>" data-tab="game" role="tab" aria-selected="<?= $tab === 'game' ? 'true' : 'false' ?>"><span class="ico">🪙</span>겜냥</button>
    <button type="button" class="rk-tab<?= $tab === 'weapon' ? ' active' : '' ?>" data-tab="weapon" role="tab" aria-selected="<?= $tab === 'weapon' ? 'true' : 'false' ?>"><span class="ico">⚔️</span>무기</button>
    <button type="button" class="rk-tab<?= $tab === 'equip' ? ' active' : '' ?>" data-tab="equip" role="tab" aria-selected="<?= $tab === 'equip' ? 'true' : 'false' ?>"><span class="ico">⛏️</span>장비</button>
  </div>

  <?php
    rk_render_panel('panelBuff', $tab, 'buff', $버프목록, $nick, $메달, static function ($r) { return (int)$r['buff']; }, '오늘 버프타수 기록이 없어요.');
    rk_render_panel('panelTasu', $tab, 'tasu', $타수목록, $nick, $메달, static function ($r) { return (int)$r['raw']; }, '오늘 타수 기록이 없어요.');
    rk_render_panel('panelBon', $tab, 'bon', $본냥목록, $nick, $메달, static function ($r) { return (string)($r['score'] ?? '0'); }, '본냥 정보가 없어요.');
    rk_render_panel('panelGame', $tab, 'game', $겜냥목록, $nick, $메달, static function ($r) { return (string)($r['score'] ?? '0'); }, '겜냥 정보가 없어요.');
    rk_render_panel('panelWeapon', $tab, 'weapon', $무기목록, $nick, $메달, static function ($r) { return (int)$r['enhance']; }, '보유 무기가 있는 회원이 없어요.');
    rk_render_panel('panelEquip', $tab, 'equip', $장비목록, $nick, $메달, static function ($r) { return (int)$r['tool_level']; }, '채굴 장비 정보가 없어요.');
  ?>

  <div class="rk-summary" id="rkSummary">
    <?php
      $카운트맵 = [
        'buff' => count($버프목록),
        'tasu' => count($타수목록),
        'bon' => count($본냥목록),
        'game' => count($겜냥목록),
        'weapon' => count($무기목록),
        'equip' => count($장비목록),
      ];
      echo '참여 <b>' . (int)$카운트맵[$tab] . '</b>명';
    ?>
  </div>
</div>
<script>
(function () {
  var tabs = document.querySelectorAll('.rk-tab');
  var panels = document.querySelectorAll('.rk-panel');
  var hint = document.getElementById('rkHint');
  var summary = document.getElementById('rkSummary');
  var dateEl = document.getElementById('rkDate');
  var hints = {
    buff: <?= json_encode($힌트맵['buff'], JSON_UNESCAPED_UNICODE) ?>,
    tasu: <?= json_encode($힌트맵['tasu'], JSON_UNESCAPED_UNICODE) ?>,
    bon: <?= json_encode($힌트맵['bon'], JSON_UNESCAPED_UNICODE) ?>,
    game: <?= json_encode($힌트맵['game'], JSON_UNESCAPED_UNICODE) ?>,
    weapon: <?= json_encode($힌트맵['weapon'], JSON_UNESCAPED_UNICODE) ?>,
    equip: <?= json_encode($힌트맵['equip'], JSON_UNESCAPED_UNICODE) ?>
  };
  var counts = {
    buff: <?= (int)count($버프목록) ?>,
    tasu: <?= (int)count($타수목록) ?>,
    bon: <?= (int)count($본냥목록) ?>,
    game: <?= (int)count($겜냥목록) ?>,
    weapon: <?= (int)count($무기목록) ?>,
    equip: <?= (int)count($장비목록) ?>
  };
  var allTime = { bon: 1, game: 1, weapon: 1, equip: 1 };
  var dateLabel = <?= json_encode($날짜표시, JSON_UNESCAPED_UNICODE) ?>;

  function activate(name) {
    if (!hints[name]) name = 'buff';
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) {
      var on = p.getAttribute('data-tab') === name;
      if (on) {
        p.classList.remove('active');
        void p.offsetWidth;
        p.classList.add('active');
      } else {
        p.classList.remove('active');
      }
    });
    if (hint) hint.textContent = hints[name] || '';
    if (summary) summary.innerHTML = '참여 <b>' + (counts[name] || 0) + '</b>명';
    if (dateEl) dateEl.textContent = allTime[name] ? '전체' : dateLabel;
    try {
      var url = new URL(location.href);
      url.searchParams.set('tab', name);
      history.replaceState(null, '', url.toString());
    } catch (e) {}
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      activate(t.getAttribute('data-tab') || 'buff');
    });
  });
})();
</script>
</body>
</html>
