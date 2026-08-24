<?php
/**
 * 보스 공격 테스트 웹 — /page/boss_test.php?code=XXXX
 * 현재(또는 다음) 실보스 외형 · 무기별 FX(단소/활/마법) · 데미지1 · HP무한 · 반격/손해없음
 */
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
require_once __DIR__ . '/wallet_web.php';
require_once __DIR__ . '/boss_raid.inc.php';

if (!defined('BOSS_TEST_DAMAGE')) {
    define('BOSS_TEST_DAMAGE', 1);
}

function boss_test_web_json(array $payload): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return 'sword'|'bow'|'magic' */
function boss_test_무기fx키($item): string {
    $item = trim((string)$item);
    if ($item !== '' && (mb_strpos($item, '활') !== false || strpos($item, '🏹') !== false)) {
        return 'bow';
    }
    if ($item !== '' && (mb_strpos($item, '마법') !== false || strpos($item, '🪄') !== false)) {
        return 'magic';
    }
    return 'sword'; // 플루트단소·기타 → 칼
}

function boss_test_무기fx라벨(string $fx): string {
    if ($fx === 'bow') {
        return '🏹 활';
    }
    if ($fx === 'magic') {
        return '🪄 마법(얼음)';
    }
    if ($fx === 'mining') {
        return '🥄 채굴(낙석)';
    }
    return '플루트단소(칼)';
}

/** 실보스 페이지와 같은 외형 (진행 중이면 현재, 대기면 다음 보스) */
function boss_test_표시보스(): array {
    $st = function_exists('boss_raid_활성_상태') ? boss_raid_활성_상태() : ['phase' => 'none'];
    $phase = (string)($st['phase'] ?? 'none');
    if ($phase === 'fighting' && !empty($st['boss'])) {
        $boss = $st['boss'];
        $def = boss_raid_종류((string)($boss['boss_key'] ?? ''));
        $hpMax = max(1, (int)($boss['hp_max'] ?? ($def['hp'] ?? 10000)));
        return [
            'key' => (string)($def['key'] ?? ''),
            'name' => (string)($def['name'] ?? '보스'),
            'emoji' => (string)($def['emoji'] ?? '🐉'),
            'blurb' => (string)($def['blurb'] ?? ''),
            'style_label' => (string)($def['style_label'] ?? ''),
            'hp_max' => $hpMax,
            'source' => 'fighting',
        ];
    }
    $nextKey = function_exists('boss_raid_다음종류키') ? boss_raid_다음종류키() : '';
    $def = boss_raid_종류($nextKey);
    $hpMax = function_exists('boss_raid_출현HP')
        ? max(1, (int)boss_raid_출현HP((int)($def['hp'] ?? 10000)))
        : max(1, (int)($def['hp'] ?? 10000));
    return [
        'key' => (string)($def['key'] ?? ''),
        'name' => (string)($def['name'] ?? '보스'),
        'emoji' => (string)($def['emoji'] ?? '🐉'),
        'blurb' => (string)($def['blurb'] ?? ''),
        'style_label' => (string)($def['style_label'] ?? ''),
        'hp_max' => $hpMax,
        'source' => 'next',
    ];
}

function boss_test_상태_페이로드($nick = '', int $hits = 0): array {
    $b = boss_test_표시보스();
    $hpMax = max(1, (int)$b['hp_max']);
    $hint = trim(($b['style_label'] !== '' ? $b['style_label'] . ' · ' : '') . (string)$b['blurb']);
    if ($hint === '') {
        $hint = '테스트 · 데미지 1 · HP 무한 · 반격 없음';
    }
    $weapon = ['item' => '', 'enhance' => 0, 'durability' => 0];
    if (trim((string)$nick) !== '' && function_exists('boss_raid_무기정보')) {
        $weapon = boss_raid_무기정보($nick);
    }
    $fx = boss_test_무기fx키($weapon['item'] ?? '');
    return [
        'phase' => 'fighting',
        'test_mode' => true,
        'name' => $b['name'],
        'emoji' => $b['emoji'],
        'boss_key' => $b['key'],
        'blurb' => '🧪 테스트 · 무기별 FX · 데미지 1 · HP∞',
        'boss_hint' => $hint,
        'boss_source' => $b['source'],
        'hp_now' => $hpMax,
        'hp_max' => $hpMax,
        'hp_pct' => 100,
        'hp_infinite' => true,
        'hits' => max(0, $hits),
        'can_weapon' => true,
        'can_join' => true,
        'can_mining' => false,
        'fight_left_sec' => 999999,
        'damage' => (int)BOSS_TEST_DAMAGE,
        'weapon_item' => (string)($weapon['item'] ?? ''),
        'enhance' => (int)($weapon['enhance'] ?? 0),
        'weapon_fx' => $fx,
        'weapon_fx_label' => boss_test_무기fx라벨($fx),
    ];
}

function boss_test_공격($nick, $forceCrit = false, $forceFx = ''): array {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'data' => '로그인이 필요해요.'];
    }
    $state = boss_test_상태_페이로드($nick);
    $fx = trim((string)$forceFx);
    if (!in_array($fx, ['sword', 'bow', 'magic', 'mining'], true)) {
        $fx = (string)($state['weapon_fx'] ?? 'sword');
    }
    $dmg = (int)BOSS_TEST_DAMAGE;
    $crit = !empty($forceCrit);
    $label = boss_test_무기fx라벨($fx);
    $msg = $label . ' 공격! 피해 ' . $dmg . ($crit ? ' (크리 FX)' : '');
    $state['weapon_fx'] = $fx;
    $state['weapon_fx_label'] = $label;
    return [
        'ok' => true,
        'data' => $msg,
        'damage' => $dmg,
        'crit' => $crit,
        'weapon_fx' => $fx,
        'counter' => null,
        'killed' => false,
        'drops' => [],
        'state' => $state,
    ];
}

$boot = [
    'code' => '',
    'need_code' => true,
    'member' => null,
    'nick' => '',
    'q' => '',
];

$code = '';
if (!empty($GLOBALS['wallet_preauth']['code'])) {
    $code = (string)$GLOBALS['wallet_preauth']['code'];
} else {
    $code = function_exists('wallet_코드_해석') ? wallet_코드_해석() : '';
}

if ($code !== '') {
    if (function_exists('wallet_odd_even_includes')) {
        wallet_odd_even_includes();
    }
    $row = null;
    if (!empty($GLOBALS['wallet_preauth']['row'])) {
        $row = function_exists('wallet_member_row_enrich')
            ? wallet_member_row_enrich($GLOBALS['wallet_preauth']['row'], $code)
            : $GLOBALS['wallet_preauth']['row'];
    } elseif (function_exists('wallet_game_auth_row')) {
        $row = wallet_game_auth_row($code);
        if ($row && function_exists('wallet_member_row_enrich')) {
            $row = wallet_member_row_enrich($row, $code);
        }
    }
    if ($row) {
        $nick = function_exists('wallet_nick_from_row') ? wallet_nick_from_row($row) : '';
        if ($nick === '') {
            $nick = trim((string)($row['name'] ?? ''));
        }
        if (function_exists('wallet_코드_쿠키_저장')) {
            wallet_코드_쿠키_저장($code);
        }
        $boot['code'] = $code;
        $boot['need_code'] = false;
        $boot['nick'] = $nick;
        $boot['member'] = function_exists('wallet_member_payload')
            ? wallet_member_payload($row, $nick)
            : ['nick' => $nick, 'newpoint' => (float)($row['newpoint'] ?? 0)];
        $boot['q'] = function_exists('wallet_code_query') ? wallet_code_query($code) : ('?code=' . rawurlencode($code));
    }
}

$action = trim((string)($_REQUEST['action'] ?? ''));
if ($action !== '') {
    if (!empty($boot['need_code']) || $boot['nick'] === '') {
        boss_test_web_json(['ok' => false, 'data' => '로그인이 필요해요.']);
    }
    $nick = $boot['nick'];
    try {
        if ($action === 'state') {
            boss_test_web_json(['ok' => true, 'state' => boss_test_상태_페이로드($nick)]);
        }
        if ($action === 'attack') {
            $forceCrit = !empty($_REQUEST['crit']) || (string)($_REQUEST['crit'] ?? '') === '1';
            $forceFx = trim((string)($_REQUEST['fx'] ?? ''));
            boss_test_web_json(boss_test_공격($nick, $forceCrit, $forceFx));
        }
        boss_test_web_json(['ok' => false, 'data' => '알 수 없는 요청이에요.']);
    } catch (Throwable $e) {
        boss_test_web_json(['ok' => false, 'data' => '오류: ' . $e->getMessage()]);
    }
}

$allowed = !$boot['need_code'] && $boot['nick'] !== '';
$state = null;
if ($allowed) {
    try {
        $state = boss_test_상태_페이로드($boot['nick']);
    } catch (Throwable $e) {
        $state = [
            'phase' => 'fighting',
            'name' => '보스',
            'emoji' => '🐉',
            'blurb' => '🧪 테스트 · 데미지 1 · HP∞',
            'hp_now' => 10000,
            'hp_max' => 10000,
            'hp_pct' => 100,
            'hp_infinite' => true,
            'can_weapon' => true,
            'weapon_fx' => 'sword',
            'weapon_fx_label' => '플루트단소(칼)',
            'weapon_item' => '',
            'enhance' => 0,
        ];
    }
}
$walletQ = $boot['q'] !== '' ? $boot['q'] : '';
$codeJs = json_encode($boot['code'], JSON_UNESCAPED_UNICODE);
$stateJs = json_encode($state, JSON_UNESCAPED_UNICODE);
$titleBoss = '보스 테스트';
if (is_array($state) && !empty($state['name'])) {
    $emoji = trim((string)($state['emoji'] ?? ''));
    $titleBoss = '테스트 · ' . trim(($emoji !== '' ? $emoji . ' ' : '') . $state['name']);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?php echo htmlspecialchars($titleBoss, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --ink: #e8eef8;
  --muted: #8a9bb8;
  --line: rgba(180, 210, 255, 0.16);
  --accent: #6eb6ff;
  --danger: #ff6b6b;
  --ok: #7dba7a;
  --warn: #ffd28a;
  --card: rgba(12, 22, 40, 0.88);
}
* { box-sizing: border-box; }
html, body {
  margin: 0;
  min-height: 100%;
  color: var(--ink);
  font-family: "Pretendard", "Apple SD Gothic Neo", sans-serif;
  background:
    radial-gradient(1200px 600px at 50% -10%, rgba(80, 140, 255, 0.22), transparent 55%),
    radial-gradient(800px 500px at 80% 100%, rgba(255, 90, 90, 0.12), transparent 50%),
    #070b14;
}
.wrap { max-width: 440px; margin: 0 auto; padding: 16px 14px 40px; }
.top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.back { color: var(--muted); text-decoration: none; font-size: 14px; }
h1 { margin: 0 0 12px; font-size: 20px; letter-spacing: -0.02em; }
.badge {
  display: inline-block;
  font-size: 11px;
  color: #9be89a;
  border: 1px solid rgba(155, 232, 154, 0.35);
  border-radius: 999px;
  padding: 3px 8px;
}
.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 18px 16px;
  margin-bottom: 12px;
}
.lock { text-align: center; padding: 40px 16px; color: var(--muted); }
.dragon {
  text-align: center;
  font-size: 112px;
  line-height: 1;
  padding: 10px 0 4px;
  filter: drop-shadow(0 10px 28px rgba(255, 80, 80, 0.4));
  animation: floaty 2.6s ease-in-out infinite;
  transform-origin: 50% 60%;
  will-change: transform, filter;
}
@keyframes floaty {
  0%   { transform: translateY(0) rotate(-2deg) scale(0.94); }
  25%  { transform: translateY(-6px) rotate(0.5deg) scale(1.08); }
  50%  { transform: translateY(-12px) rotate(2deg) scale(0.96); }
  75%  { transform: translateY(-5px) rotate(-0.5deg) scale(1.1); }
  100% { transform: translateY(0) rotate(-2deg) scale(0.94); }
}
.boss-name { text-align: center; margin: 8px 0 4px; font-size: 18px; font-weight: 700; }
.boss-sub { text-align: center; color: var(--muted); font-size: 12px; margin: 0 0 10px; line-height: 1.5; }
.timer {
  text-align: center;
  font-size: 14px;
  font-weight: 700;
  color: var(--ok);
  margin: 0 0 12px;
}
.hp-bar {
  height: 16px;
  border-radius: 999px;
  background: rgba(255,255,255,0.08);
  overflow: hidden;
  border: 1px solid var(--line);
}
.hp-fill {
  height: 100%;
  width: 100%;
  background: linear-gradient(90deg, #ff5a5a, #ff9f43);
}
.hp-text {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
  font-size: 13px;
  color: var(--muted);
}
.hp-text b { color: var(--ink); font-weight: 600; }
.actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
.fx-preview { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; }
.btn {
  appearance: none;
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.04);
  color: var(--ink);
  border-radius: 14px;
  padding: 14px 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.btn:disabled { opacity: 0.4; cursor: not-allowed; }
.btn.weapon { border-color: rgba(255, 160, 80, 0.45); background: rgba(255, 160, 80, 0.08); }
.btn.crit { border-color: rgba(255, 107, 107, 0.45); }
.btn.fx-sword { border-color: rgba(220, 230, 255, 0.4); }
.btn.fx-bow { border-color: rgba(255, 190, 100, 0.45); }
.btn.fx-magic { border-color: rgba(140, 210, 255, 0.55); }
.btn.fx-mining { border-color: rgba(210, 170, 90, 0.55); }
.btn-hint { display: block; margin-top: 4px; font-size: 11px; font-weight: 400; color: var(--muted); }
.msg {
  min-height: 20px;
  margin: 10px 0 0;
  font-size: 13px;
  color: var(--accent);
  white-space: pre-line;
}
.msg.err { color: var(--danger); }
.msg.ok { color: var(--ok); }
.meta { font-size: 13px; color: var(--muted); line-height: 1.55; }
.meta b { color: var(--ink); }
.rules { font-size: 12px; color: var(--muted); line-height: 1.55; margin: 0; }
.boss-stage {
  position: relative;
  overflow: hidden;
  border-radius: 14px;
  margin-bottom: 4px;
  min-height: 148px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.fx-layer {
  pointer-events: none;
  position: absolute;
  inset: 0;
  z-index: 5;
  overflow: hidden;
}
.fx-dmg {
  position: absolute;
  left: 50%;
  top: 42%;
  transform: translate(-50%, 0);
  font-size: 28px;
  font-weight: 800;
  color: #ffd36a;
  text-shadow: 0 2px 0 #7a2a00, 0 0 18px rgba(255,140,0,0.7);
  animation: dmgPop 0.9s ease-out forwards;
  white-space: nowrap;
  z-index: 8;
}
.fx-slash {
  position: absolute;
  left: 18%;
  top: 28%;
  width: 64%;
  height: 6px;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent, #fff, #c8d4ff, transparent);
  box-shadow: 0 0 12px rgba(200, 220, 255, 0.7);
  transform: rotate(-28deg) scaleX(0.2);
  opacity: 0;
  animation: slashCut 0.45s ease-out forwards;
}
.fx-slash.second {
  top: 48%;
  left: 22%;
  width: 56%;
  transform: rotate(22deg) scaleX(0.2);
  animation: slashCut2 0.5s ease-out 0.08s forwards;
}
.fx-boom {
  position: absolute;
  left: 50%;
  top: 48%;
  width: 18px;
  height: 18px;
  margin: -9px 0 0 -9px;
  border-radius: 50%;
  background: radial-gradient(circle, #fff 0%, #ff8a3d 40%, transparent 70%);
  animation: boomPulse 0.55s ease-out forwards;
}
.fx-arrow {
  position: absolute;
  left: -8%;
  top: 62%;
  width: 54px;
  height: 10px;
  margin-top: -5px;
  background: linear-gradient(90deg, transparent 0%, #8b5a2b 18%, #e8c48a 55%, #fff 100%);
  clip-path: polygon(0 40%, 72% 28%, 72% 0, 100% 50%, 72% 100%, 72% 72%, 0 60%);
  transform: rotate(-18deg);
  opacity: 0;
  animation: arrowFly 0.48s ease-out forwards;
  filter: drop-shadow(0 0 6px rgba(255, 200, 120, 0.7));
}
.fx-arrow-trail {
  position: absolute;
  left: 8%;
  top: 58%;
  width: 42%;
  height: 3px;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent, rgba(255, 210, 140, 0.55));
  transform: rotate(-18deg);
  opacity: 0;
  animation: trailFade 0.55s ease-out forwards;
}
.fx-arrow-impact {
  position: absolute;
  left: 50%;
  top: 46%;
  width: 14px;
  height: 14px;
  margin: -7px 0 0 -7px;
  border-radius: 50%;
  border: 2px solid rgba(255, 210, 140, 0.9);
  opacity: 0;
  animation: ringPop 0.55s ease-out 0.28s forwards;
}
.fx-ice-burst {
  position: absolute;
  left: 50%;
  top: 48%;
  width: 20px;
  height: 20px;
  margin: -10px 0 0 -10px;
  border-radius: 50%;
  background: radial-gradient(circle, #fff 0%, #9adbff 35%, rgba(80, 160, 255, 0) 70%);
  animation: iceBoom 0.7s ease-out forwards;
  box-shadow: 0 0 24px rgba(140, 210, 255, 0.75);
}
.fx-ice-shard {
  position: absolute;
  left: 50%;
  top: 48%;
  width: 10px;
  height: 22px;
  margin: -11px 0 0 -5px;
  background: linear-gradient(180deg, #fff, #7ec8ff 55%, rgba(120, 190, 255, 0));
  clip-path: polygon(50% 0, 100% 100%, 50% 78%, 0 100%);
  opacity: 0;
  animation: iceShard 0.65s ease-out forwards;
  filter: drop-shadow(0 0 6px rgba(150, 220, 255, 0.8));
}
.fx-frost {
  position: absolute;
  inset: 8% 18%;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(180, 230, 255, 0.28), transparent 70%);
  opacity: 0;
  animation: frostFlash 0.7s ease-out forwards;
}
.fx-rock {
  position: absolute;
  top: -18%;
  width: var(--rw, 18px);
  height: var(--rh, 14px);
  border-radius: 40% 55% 45% 60%;
  background:
    radial-gradient(circle at 30% 30%, #d8c4a0 0%, #8a6a40 45%, #4a3520 100%);
  box-shadow: inset -2px -2px 0 rgba(0,0,0,0.25), 0 2px 4px rgba(0,0,0,0.35);
  opacity: 0;
  animation: rockFall 0.7s cubic-bezier(0.4, 0.05, 0.7, 1) forwards;
  z-index: 6;
}
.fx-rock.sm { --rw: 12px; --rh: 10px; }
.fx-rock.md { --rw: 18px; --rh: 14px; }
.fx-rock.lg { --rw: 24px; --rh: 18px; }
.fx-dust {
  position: absolute;
  left: 50%;
  top: 62%;
  width: 10px;
  height: 10px;
  margin: -5px 0 0 -5px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(210, 180, 120, 0.9), transparent 70%);
  opacity: 0;
  animation: dustPuff 0.55s ease-out forwards;
}
.dragon.hit {
  animation: bossHit 0.55s ease-out !important;
  filter: drop-shadow(0 0 18px rgba(255, 80, 40, 0.85)) brightness(1.35) saturate(1.4);
}
.dragon.hit-bow {
  animation: bossHit 0.55s ease-out !important;
  filter: drop-shadow(0 0 18px rgba(255, 170, 60, 0.9)) brightness(1.3);
}
.dragon.hit-magic {
  animation: bossHitIce 0.65s ease-out !important;
  filter: drop-shadow(0 0 22px rgba(120, 200, 255, 0.95)) brightness(1.25) saturate(1.2) hue-rotate(160deg);
}
.dragon.hit-mining {
  animation: bossHitRock 0.65s ease-out !important;
  filter: drop-shadow(0 0 16px rgba(210, 170, 90, 0.9)) brightness(1.2);
}
.card.flash-hit {
  box-shadow: 0 0 0 2px rgba(255,180,80,0.55), 0 0 28px rgba(255,120,40,0.35);
}
.card.flash-bow {
  box-shadow: 0 0 0 2px rgba(255,200,120,0.55), 0 0 28px rgba(255,160,60,0.35);
}
.card.flash-magic {
  box-shadow: 0 0 0 2px rgba(140,210,255,0.6), 0 0 32px rgba(100,180,255,0.45);
}
.card.flash-mining {
  box-shadow: 0 0 0 2px rgba(210,170,90,0.55), 0 0 28px rgba(180,140,60,0.35);
}
@keyframes dmgPop {
  0% { opacity: 0; transform: translate(-50%, 12px) scale(0.6); }
  25% { opacity: 1; transform: translate(-50%, -6px) scale(1.15); }
  100% { opacity: 0; transform: translate(-50%, -42px) scale(1); }
}
@keyframes slashCut {
  0% { opacity: 0; transform: rotate(-28deg) scaleX(0.15); }
  30% { opacity: 1; transform: rotate(-28deg) scaleX(1); }
  100% { opacity: 0; transform: rotate(-28deg) scaleX(1.05) translateX(10%); }
}
@keyframes slashCut2 {
  0% { opacity: 0; transform: rotate(22deg) scaleX(0.15); }
  30% { opacity: 1; transform: rotate(22deg) scaleX(1); }
  100% { opacity: 0; transform: rotate(22deg) scaleX(1.05) translateX(-8%); }
}
@keyframes boomPulse {
  0% { transform: scale(0.4); opacity: 1; }
  100% { transform: scale(6); opacity: 0; }
}
@keyframes arrowFly {
  0% { opacity: 0; left: -10%; top: 70%; transform: rotate(-18deg) scale(0.7); }
  15% { opacity: 1; }
  100% { opacity: 0; left: 48%; top: 44%; transform: rotate(-18deg) scale(1); }
}
@keyframes trailFade {
  0% { opacity: 0; width: 10%; }
  30% { opacity: 0.8; }
  100% { opacity: 0; width: 48%; }
}
@keyframes ringPop {
  0% { transform: scale(0.3); opacity: 1; }
  100% { transform: scale(5); opacity: 0; }
}
@keyframes iceBoom {
  0% { transform: scale(0.3); opacity: 1; }
  100% { transform: scale(7); opacity: 0; }
}
@keyframes iceShard {
  0% { opacity: 0; transform: rotate(var(--rot, 0deg)) translateY(0) scale(0.5); }
  25% { opacity: 1; }
  100% { opacity: 0; transform: rotate(var(--rot, 0deg)) translateY(-52px) scale(1.1); }
}
@keyframes frostFlash {
  0% { opacity: 0; transform: scale(0.6); }
  30% { opacity: 1; }
  100% { opacity: 0; transform: scale(1.25); }
}
@keyframes rockFall {
  0% { opacity: 0; transform: translateY(0) rotate(0deg) scale(0.7); }
  12% { opacity: 1; }
  70% { opacity: 1; transform: translateY(var(--fall, 118px)) rotate(var(--spin, 140deg)) scale(1); }
  85% { opacity: 1; transform: translateY(calc(var(--fall, 118px) - 6px)) rotate(calc(var(--spin, 140deg) + 10deg)) scale(1.05); }
  100% { opacity: 0; transform: translateY(calc(var(--fall, 118px) + 8px)) rotate(calc(var(--spin, 140deg) + 20deg)) scale(0.85); }
}
@keyframes dustPuff {
  0% { opacity: 0; transform: scale(0.4); }
  35% { opacity: 0.95; transform: scale(2.8); }
  100% { opacity: 0; transform: scale(5.5); }
}
@keyframes bossHit {
  0%, 100% { transform: translate(0,0) rotate(0); }
  20% { transform: translate(-10px, 4px) rotate(-6deg); }
  40% { transform: translate(12px, -4px) rotate(5deg); }
  60% { transform: translate(-6px, 2px) rotate(-3deg); }
  80% { transform: translate(4px, 0) rotate(2deg); }
}
@keyframes bossHitIce {
  0%, 100% { transform: scale(1); filter: drop-shadow(0 0 22px rgba(120, 200, 255, 0.95)) brightness(1.25) saturate(1.2) hue-rotate(160deg); }
  30% { transform: scale(1.08) rotate(-3deg); }
  55% { transform: scale(0.94) rotate(2deg); }
  80% { transform: scale(1.03); }
}
@keyframes bossHitRock {
  0%, 100% { transform: translateY(0) scale(1); }
  35% { transform: translateY(8px) scale(0.94); }
  55% { transform: translateY(-4px) scale(1.04); }
  75% { transform: translateY(3px) scale(0.98); }
}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <a class="back" href="/page/boss.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">← 실보스</a>
    <span class="badge">FX 테스트</span>
  </div>
  <h1>보스 테스트</h1>

  <?php if ($boot['need_code']): ?>
    <div class="card lock">가방 코드로 로그인해 주세요.</div>
  <?php elseif (!$allowed): ?>
    <div class="card lock">접근할 수 없어요.</div>
  <?php else: ?>
    <div class="card" id="bossCard">
      <div class="boss-stage" id="bossStage">
        <div class="fx-layer" id="fxLayer"></div>
        <div class="dragon" id="bossEmoji"><?php echo htmlspecialchars((string)($state['emoji'] ?? '🐉'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div class="boss-name" id="bossName"><?php echo htmlspecialchars((string)($state['name'] ?? '보스'), ENT_QUOTES, 'UTF-8'); ?></div>
      <p class="boss-sub" id="bossSub">🧪 테스트 · 무기별 FX · 데미지 1 · HP∞</p>
      <div class="timer" id="timer">✅ 항상 공격 가능 · HP 무한</div>
      <div class="hp-bar"><div class="hp-fill" id="hpFill" style="width:100%"></div></div>
      <div class="hp-text">
        <span>HP <b id="hpNow">∞</b> / <span id="hpMax">∞</span></span>
        <span id="hpPct">100%</span>
      </div>
      <div class="actions">
        <button type="button" class="btn weapon" id="btnWeapon">⚔️ 내 무기 공격<span class="btn-hint" id="weaponHint">자동 FX</span></button>
        <button type="button" class="btn crit" id="btnCrit">💥 크리 FX<span class="btn-hint">피해 1 · 크리</span></button>
      </div>
      <div class="fx-preview">
        <button type="button" class="btn fx-sword" id="btnSword" data-fx="sword">플루트단소<span class="btn-hint">칼</span></button>
        <button type="button" class="btn fx-bow" id="btnBow" data-fx="bow">🏹 활<span class="btn-hint">화살</span></button>
        <button type="button" class="btn fx-magic" id="btnMagic" data-fx="magic">🪄 마법<span class="btn-hint">얼음</span></button>
        <button type="button" class="btn fx-mining" id="btnMining" data-fx="mining">🥄 채굴<span class="btn-hint">낙석</span></button>
      </div>
      <p class="msg" id="msg"></p>
    </div>

    <div class="card">
      <div class="meta" id="myInfo">
        공격자: <b><?php echo htmlspecialchars($boot['nick'], ENT_QUOTES, 'UTF-8'); ?></b><br>
        장착: <b id="myWeapon">—</b> · FX <b id="myFx">—</b><br>
        타격 <b id="hitCnt">0</b>회 · 실보스 DB/반격/손해 없음<br>
        <span id="bossHint">—</span>
      </div>
    </div>

    <div class="card">
      <p class="rules">
        · 장착 무기 기준: 플루트단소→칼 · 활→화살 · 마법→얼음<br>
        · 채굴 미리보기: 보스 위로 돌이 떨어지는 낙석 FX<br>
        · 아래 버튼은 장착과 무관하게 FX만 바로 확인용<br>
        · 데미지 1 · HP 무한 · 반격/손해 없음
      </p>
    </div>
  <?php endif; ?>
</div>

<?php if ($allowed): ?>
<script>
(function () {
  var CODE = <?php echo $codeJs; ?>;
  var state = <?php echo $stateJs; ?> || {};
  var busy = false;
  var hits = 0;

  function $(id) { return document.getElementById(id); }

  function clearFx() {
    var layer = $('fxLayer');
    if (layer) layer.innerHTML = '';
  }

  function addDmg(layer, damage, crit, color) {
    var dmg = document.createElement('div');
    dmg.className = 'fx-dmg';
    dmg.textContent = (crit ? '💥 ' : '') + '-' + Number(damage || 0).toLocaleString();
    if (crit) dmg.style.color = '#ff6b6b';
    else if (color) dmg.style.color = color;
    layer.appendChild(dmg);
  }

  function playSwordFx(layer) {
    var slash = document.createElement('div');
    slash.className = 'fx-slash';
    layer.appendChild(slash);
    var slash2 = document.createElement('div');
    slash2.className = 'fx-slash second';
    layer.appendChild(slash2);
    var boom = document.createElement('div');
    boom.className = 'fx-boom';
    layer.appendChild(boom);
  }

  function playBowFx(layer) {
    var trail = document.createElement('div');
    trail.className = 'fx-arrow-trail';
    layer.appendChild(trail);
    var arrow = document.createElement('div');
    arrow.className = 'fx-arrow';
    layer.appendChild(arrow);
    var impact = document.createElement('div');
    impact.className = 'fx-arrow-impact';
    layer.appendChild(impact);
    var boom = document.createElement('div');
    boom.className = 'fx-boom';
    boom.style.animationDelay = '0.28s';
    boom.style.background = 'radial-gradient(circle, #fff 0%, #ffb45a 40%, transparent 70%)';
    layer.appendChild(boom);
  }

  function playMagicFx(layer) {
    var frost = document.createElement('div');
    frost.className = 'fx-frost';
    layer.appendChild(frost);
    var burst = document.createElement('div');
    burst.className = 'fx-ice-burst';
    layer.appendChild(burst);
    var angles = [-50, -20, 15, 45, 80, -80];
    for (var i = 0; i < angles.length; i++) {
      var shard = document.createElement('div');
      shard.className = 'fx-ice-shard';
      shard.style.setProperty('--rot', angles[i] + 'deg');
      shard.style.animationDelay = (i * 0.03) + 's';
      layer.appendChild(shard);
    }
  }

  function playMiningFx(layer) {
    var specs = [
      { left: '28%', size: 'lg', delay: '0s', fall: '112px', spin: '160deg' },
      { left: '46%', size: 'md', delay: '0.06s', fall: '120px', spin: '-120deg' },
      { left: '58%', size: 'sm', delay: '0.12s', fall: '108px', spin: '200deg' },
      { left: '38%', size: 'md', delay: '0.16s', fall: '126px', spin: '-90deg' },
      { left: '52%', size: 'lg', delay: '0.22s', fall: '116px', spin: '130deg' },
      { left: '64%', size: 'sm', delay: '0.28s', fall: '104px', spin: '-170deg' },
      { left: '34%', size: 'sm', delay: '0.34s', fall: '122px', spin: '80deg' }
    ];
    for (var i = 0; i < specs.length; i++) {
      var s = specs[i];
      var rock = document.createElement('div');
      rock.className = 'fx-rock ' + s.size;
      rock.style.left = s.left;
      rock.style.animationDelay = s.delay;
      rock.style.setProperty('--fall', s.fall);
      rock.style.setProperty('--spin', s.spin);
      layer.appendChild(rock);
    }
    var dustPositions = ['36%', '50%', '62%'];
    for (var d = 0; d < dustPositions.length; d++) {
      var dust = document.createElement('div');
      dust.className = 'fx-dust';
      dust.style.left = dustPositions[d];
      dust.style.animationDelay = (0.38 + d * 0.05) + 's';
      layer.appendChild(dust);
    }
    var boom = document.createElement('div');
    boom.className = 'fx-boom';
    boom.style.top = '60%';
    boom.style.animationDelay = '0.4s';
    boom.style.background = 'radial-gradient(circle, #fff 0%, #c9a06a 40%, transparent 70%)';
    layer.appendChild(boom);
  }

  function playHitFx(damage, crit, fx) {
    var emoji = $('bossEmoji');
    var card = $('bossCard');
    var layer = $('fxLayer');
    if (!emoji || !layer) return;
    clearFx();
    fx = fx || 'sword';
    emoji.classList.remove('waiting', 'hit', 'hit-bow', 'hit-magic', 'hit-mining', 'counter');
    void emoji.offsetWidth;
    if (card) {
      card.classList.remove('flash-hit', 'flash-bow', 'flash-magic', 'flash-mining', 'flash-counter');
      void card.offsetWidth;
    }
    if (fx === 'bow') {
      emoji.classList.add('hit-bow');
      if (card) card.classList.add('flash-bow');
      playBowFx(layer);
      addDmg(layer, damage, crit, '#ffd28a');
    } else if (fx === 'magic') {
      emoji.classList.add('hit-magic');
      if (card) card.classList.add('flash-magic');
      playMagicFx(layer);
      addDmg(layer, damage, crit, '#9adbff');
    } else if (fx === 'mining') {
      emoji.classList.add('hit-mining');
      if (card) card.classList.add('flash-mining');
      playMiningFx(layer);
      addDmg(layer, damage, crit, '#e8c48a');
    } else {
      emoji.classList.add('hit');
      if (card) card.classList.add('flash-hit');
      playSwordFx(layer);
      addDmg(layer, damage, crit, null);
    }
    setTimeout(function () {
      emoji.classList.remove('hit', 'hit-bow', 'hit-magic', 'hit-mining');
      if (card) card.classList.remove('flash-hit', 'flash-bow', 'flash-magic', 'flash-mining');
      clearFx();
    }, 950);
  }

  function applyHp() {
    $('hpNow').textContent = '∞';
    $('hpMax').textContent = '∞';
    $('hpPct').textContent = '100%';
    $('hpFill').style.width = '100%';
    if ($('hitCnt')) $('hitCnt').textContent = String(hits);
  }

  function render(s) {
    if (!s) return;
    state = s;
    $('bossEmoji').textContent = s.emoji || '🐉';
    if (!$('bossEmoji').classList.contains('hit')
      && !$('bossEmoji').classList.contains('hit-bow')
      && !$('bossEmoji').classList.contains('hit-magic')
      && !$('bossEmoji').classList.contains('hit-mining')) {
      $('bossEmoji').className = 'dragon';
    }
    $('bossName').textContent = s.name || '보스';
    $('bossSub').textContent = s.blurb || '🧪 테스트 · 무기별 FX · 데미지 1 · HP∞';
    if ($('bossHint')) {
      var src = s.boss_source === 'fighting' ? '현재 출현 보스' : '다음 보스(대기)';
      $('bossHint').textContent = src + (s.boss_hint ? ' · ' + s.boss_hint : '');
    }
    var item = s.weapon_item || '(미장착)';
    var enh = s.enhance != null ? (' +' + s.enhance) : '';
    if ($('myWeapon')) $('myWeapon').textContent = item + enh;
    if ($('myFx')) $('myFx').textContent = s.weapon_fx_label || '—';
    if ($('weaponHint')) {
      $('weaponHint').textContent = (s.weapon_fx_label || '자동') + ' · 피해 1';
    }
    applyHp();
    document.title = '테스트 · ' + ((s.emoji ? s.emoji + ' ' : '') + (s.name || '보스'));
  }

  function setMsg(text, kind) {
    var el = $('msg');
    el.className = 'msg' + (kind ? (' ' + kind) : '');
    el.textContent = text || '';
  }

  function post(action, extra) {
    var body = new URLSearchParams();
    body.set('code', CODE);
    body.set('action', action);
    if (extra) {
      Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
    }
    return fetch(location.pathname + location.search, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) {
      return r.text().then(function (t) {
        try {
          return JSON.parse(t);
        } catch (e) {
          throw new Error(t ? t.slice(0, 120) : 'empty');
        }
      });
    });
  }

  function attack(opts) {
    if (busy) return;
    opts = opts || {};
    busy = true;
    var fx = opts.fx || (state && state.weapon_fx) || 'sword';
    var crit = !!opts.crit;
    setMsg((opts.fx ? '미리보기 · ' : '') + '공격 중…', '');
    playHitFx(1, crit, fx);
    hits += 1;
    if ($('hitCnt')) $('hitCnt').textContent = String(hits);
    var extra = {};
    if (crit) extra.crit = '1';
    if (opts.fx) extra.fx = opts.fx;
    post('attack', extra).then(function (j) {
      if (j && j.ok) {
        if (j.state) render(j.state);
        setMsg(j.data || '공격 성공!', 'ok');
      } else {
        setMsg((j && j.data) ? j.data : '공격 실패', 'err');
      }
    }).catch(function (err) {
      setMsg('서버 응답 없음(로컬 FX만 재생됨)', 'err');
      console.warn(err);
    }).then(function () {
      busy = false;
    });
  }

  function refreshState() {
    post('state').then(function (j) {
      if (j && j.ok && j.state) render(j.state);
    }).catch(function () {});
  }

  $('btnWeapon').addEventListener('click', function () { attack({}); });
  $('btnCrit').addEventListener('click', function () { attack({ crit: true }); });
  ['btnSword', 'btnBow', 'btnMagic', 'btnMining'].forEach(function (id) {
    var el = $(id);
    if (!el) return;
    el.addEventListener('click', function () {
      attack({ fx: el.getAttribute('data-fx') });
    });
  });
  render(state);
  setInterval(refreshState, 8000);
})();
</script>
<?php endif; ?>
</body>
</html>
