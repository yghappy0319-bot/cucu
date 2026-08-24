<?php
/**
 * 간단 부루마블 웹
 * URL: /page/burumable.php?code=XXXX
 *      /api/game/burumable_web.php?code=XXXX
 *
 * action=status|new|force_start|end|pause|resume|roll|skiproll|buy|skip|build|chance|travel|forcebuy|interest|giveup|give|sell|broke|paydebt|force_broke|force_skip|recruit|join|recruit_cancel|recruit_extend|recruit_remind
 */
define('WALLET_LIB_ONLY', true);
require_once __DIR__ . '/wallet_web.php';
require_once __DIR__ . '/burumable.inc.php';
require_once __DIR__ . '/wallet_nav_fab.inc.php';

function burumable_요청코드(): string {
  $code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
  if ($code === '' && isset($_REQUEST['code'])) {
    $code = trim((string)$_REQUEST['code']);
  }
  if ($code === '' && isset($_COOKIE['wallet_code'])) {
    $code = trim((string)$_COOKIE['wallet_code']);
  }
  return $code;
}

function burumable_닉($row): string {
  $nick = trim((string)($row['name'] ?? ''));
  if (function_exists('getTwoCharNick')) {
    $p = getTwoCharNick($nick);
    if ($p !== '') {
      return $p;
    }
  }
  return $nick;
}

$code = burumable_요청코드();
$auth = $code !== '' ? wallet_auth($code) : null;
if ($auth && $code !== '' && function_exists('wallet_코드_쿠키_저장')) {
  wallet_코드_쿠키_저장($code);
}
if (!$auth) {
  $action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
  if ($action !== '') {
    wallet_json(['ok' => false, 'data' => '연구실에서 공창닉으로 입장 후 .지갑을 입력해 주세요. 코드가 있어야 참가할 수 있어요.']);
  }
  if (function_exists('burumable_코드없음화면_출력')) {
    burumable_코드없음화면_출력();
  }
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>부루마블</title></head><body>';
  echo '<p>연구실에서 공창닉으로 입장 후 .지갑을 입력해 주세요.</p>';
  echo '<p><a href="https://open.kakao.com/o/gQIh7cIi">연구실 입장</a></p>';
  echo '</body></html>';
  exit;
}

$code = (string)($auth['code'] ?? $code);
$row = $auth['row'] ?? $auth;
$nick = burumable_닉($row);
$midx = (int)($row['idx'] ?? 0);
$q = wallet_code_query($code);
$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';

if ($action !== '') {
  if ($midx < 1 || $nick === '') {
    wallet_json(['ok' => false, 'data' => '회원 정보가 없어요.']);
  }
  $needLock = true;
  if ($needLock && !burumable_락()) {
    wallet_json(['ok' => false, 'data' => '다른 친구가 주사위 중이에요. 잠시 후 다시 눌러 주세요.']);
  }
  try {
    $state = burumable_보장로드($nick, $midx, $action);
    $playActs = ['roll', 'skiproll', 'buy', 'skip', 'build', 'chance', 'travel', 'forcebuy', 'interest', 'giveup', 'give', 'sell', 'broke', 'paydebt'];
    if (in_array($action, $playActs, true) && !empty($state['recruiting'])) {
      wallet_json(['ok' => false, 'data' => '지금은 참가 모집 중이에요.']);
    }
    if (in_array($action, $playActs, true) && !empty($state['paused'])) {
      wallet_json(['ok' => false, 'data' => '지금은 일시멈춤이에요.']);
    }
    if ($action === 'status') {
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick)]);
    }
    if ($action === 'new' || $action === 'force_start') {
      if (!burumable_민호인가($nick)) {
        wallet_json(['ok' => false, 'data' => $action === 'force_start' ? '강제시작은 민호만 할 수 있어요.' : '새 판은 민호만 시작할 수 있어요.']);
      }
      $force = ($action === 'force_start');
      $chk = function_exists('burumable_판시작가능') ? burumable_판시작가능($state, $force) : ['ok' => false, 'msg' => '먼저 모집을 시작해 주세요.'];
      if (empty($chk['ok'])) {
        wallet_json(['ok' => false, 'data' => $chk['msg'] ?? '실패']);
      }
      $state = burumable_새판시작($nick, $midx, $state);
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $force ? '강제 시작했어요.' : '']);
    }
    if ($action === 'recruit') {
      if (!function_exists('burumable_모집시작')) {
        wallet_json(['ok' => false, 'data' => '모집 기능을 불러올 수 없어요.']);
      }
      $r = burumable_모집시작($state, $nick);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'join') {
      if (!function_exists('burumable_참가신청')) {
        wallet_json(['ok' => false, 'data' => '참가 기능을 불러올 수 없어요.']);
      }
      $r = burumable_참가신청($state, $nick, $midx);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'recruit_cancel') {
      if (!function_exists('burumable_모집취소')) {
        wallet_json(['ok' => false, 'data' => '모집 취소 기능을 불러올 수 없어요.']);
      }
      $r = burumable_모집취소($state, $nick);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'recruit_extend') {
      if (!function_exists('burumable_모집연장')) {
        wallet_json(['ok' => false, 'data' => '모집 연장 기능을 불러올 수 없어요.']);
      }
      $r = burumable_모집연장($state, $nick);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'recruit_remind') {
      if (!function_exists('burumable_모집알람재전송')) {
        wallet_json(['ok' => false, 'data' => '모집 알람 기능을 불러올 수 없어요.']);
      }
      $r = burumable_모집알람재전송($state, $nick);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'end') {
      if (!burumable_민호인가($nick)) {
        wallet_json(['ok' => false, 'data' => '종료는 민호만 할 수 있어요.']);
      }
      $r = burumable_정산종료($state);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick)]);
    }
    if ($action === 'pause') {
      if (!burumable_민호인가($nick)) {
        wallet_json(['ok' => false, 'data' => '일시멈춤은 민호만 할 수 있어요.']);
      }
      $r = burumable_일시정지($state);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick)]);
    }
    if ($action === 'resume') {
      if (!burumable_민호인가($nick)) {
        wallet_json(['ok' => false, 'data' => '이어하기는 민호만 할 수 있어요.']);
      }
      $r = burumable_이어하기($state);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick)]);
    }
    if ($action === 'replay') {
      if (!burumable_민호인가($nick)) {
        wallet_json(['ok' => false, 'data' => '재진행은 민호만 할 수 있어요.']);
      }
      $who = trim((string)($_REQUEST['who'] ?? ''));
      $r = function_exists('burumable_재진행')
        ? burumable_재진행($state, $nick, $who)
        : ['ok' => false, 'msg' => '재진행 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'roll') {
      $r = burumable_주사위실행($state, $nick);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'skiproll') {
      $r = function_exists('burumable_주사위포기')
        ? burumable_주사위포기($state, $nick)
        : ['ok' => false, 'msg' => '굴림 포기 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'buy' || $action === 'skip') {
      $r = burumable_구매실행($state, $nick, $action === 'buy');
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'build') {
      $tid = (int)($_REQUEST['tile'] ?? 0);
      $r = burumable_짓기($state, $nick, $tid);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick)]);
    }
    if ($action === 'chance') {
      $cardId = trim((string)($_REQUEST['card'] ?? ''));
      $r = burumable_찬스고르기($state, $nick, $cardId);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'travel') {
      $tid = (int)($_REQUEST['tile'] ?? -1);
      $r = function_exists('burumable_세계여행고르기')
        ? burumable_세계여행고르기($state, $nick, $tid)
        : ['ok' => false, 'msg' => '세계여행 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'forcebuy') {
      $tid = (int)($_REQUEST['tile'] ?? -1);
      $r = function_exists('burumable_강제구매고르기')
        ? burumable_강제구매고르기($state, $nick, $tid)
        : ['ok' => false, 'msg' => '강제구매 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'interest') {
      $pay = trim((string)($_REQUEST['pay'] ?? 'money'));
      $r = burumable_땅이자수령($state, $nick, $pay);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'give') {
      wallet_json(['ok' => false, 'data' => '땅 양도는 지금은 할 수 없어요.']);
    }
    if ($action === 'sell') {
      $tid = (int)($_REQUEST['tile'] ?? 0);
      $r = burumable_땅매각($state, $nick, $tid);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'broke') {
      $r = function_exists('burumable_파산실행')
        ? burumable_파산실행($state, $nick)
        : ['ok' => false, 'msg' => '파산 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'paydebt') {
      $r = function_exists('burumable_빚납부')
        ? burumable_빚납부($state, $nick)
        : ['ok' => false, 'msg' => '납부 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'giveup') {
      $r = burumable_기권($state, $nick);
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick)]);
    }
    if ($action === 'force_broke') {
      $who = trim((string)($_REQUEST['who'] ?? ''));
      $r = function_exists('burumable_강제파산')
        ? burumable_강제파산($state, $nick, $who)
        : ['ok' => false, 'msg' => '강제 파산 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    if ($action === 'force_skip') {
      $who = trim((string)($_REQUEST['who'] ?? ''));
      $r = function_exists('burumable_강제턴넘김')
        ? burumable_강제턴넘김($state, $nick, $who)
        : ['ok' => false, 'msg' => '턴 넘김 기능을 불러올 수 없어요.'];
      if (empty($r['ok'])) {
        wallet_json(['ok' => false, 'data' => $r['msg'] ?? '실패']);
      }
      burumable_저장($state);
      wallet_json(['ok' => true, 'game' => burumable_공개($state, $nick), 'data' => $r['msg'] ?? '']);
    }
    wallet_json(['ok' => false, 'data' => '알 수 없는 요청이에요.']);
  } finally {
    if ($needLock) {
      burumable_락해제();
    }
  }
}

$bootLocked = burumable_락();
try {
  $bootState = burumable_보장로드($nick, $midx);
} finally {
  if ($bootLocked) {
    burumable_락해제();
  }
}
$bootGame = burumable_공개($bootState, $nick);
$gridN = burumable_격자();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<?php $pageTitle = defined('부루마블_이름') ? 부루마블_이름 : '부루마블'; ?>
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="application-name" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
<script>document.title=<?php echo json_encode($pageTitle, JSON_UNESCAPED_UNICODE); ?>;</script>
<style>
:root {
  --bg: #0b0d12;
  --card: #161c26;
  --card2: #1c2430;
  --line: rgba(255,255,255,0.12);
  --text: #e8eef6;
  --muted: #9aa3b2;
  --mint: #6ee7b7;
}
* { box-sizing: border-box; }
html, body {
  margin: 0;
  min-height: 100%;
  min-height: 100dvh;
  background: var(--bg);
  color: var(--text);
  font-family: "Pretendard", "Apple SD Gothic Neo", sans-serif;
  overflow-x: hidden;
  -webkit-text-size-adjust: 100%;
  text-size-adjust: 100%;
}
.wrap {
  max-width: 860px;
  margin: 0 auto;
  padding: max(46px, calc(env(safe-area-inset-top) + 36px)) max(8px, env(safe-area-inset-right)) 96px max(8px, env(safe-area-inset-left));
}
body.roll-dock-on .wrap { padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px)); }
body.roll-dock-on .wn-fab {
  bottom: calc(72px + env(safe-area-inset-bottom, 0px));
}
body:has(.buypop:not([hidden])) .wn-fab,
body:has(.tsheet:not([hidden])) .wn-fab,
body.recruit-on .wn-fab {
  visibility: hidden;
  pointer-events: none;
}
h1 { margin: 8px 0 4px; font-size: 1.15rem; }
.sub { color: var(--muted); font-size: 0.78rem; margin: 0 0 10px; line-height: 1.45; }
.sub[hidden] { display: none !important; }
.board-tools {
  display: flex;
  justify-content: flex-end;
  margin: 0 0 6px;
}
.board-text-btn {
  appearance: none;
  border: 1px solid var(--line);
  background: var(--card2);
  color: var(--muted);
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 5px 12px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.board-text-btn.on {
  color: var(--mint);
  border-color: rgba(110,231,183,0.4);
}
.board-wrap { position: relative; overflow: visible; width: 100%; }
.board {
  display: grid;
  width: 100%;
  max-width: min(100%, 68dvh);
  max-height: 68dvh;
  margin: 0 auto;
  grid-template-columns: minmax(36px, 1.45fr) repeat(<?php echo max(1, (int)$gridN - 2); ?>, minmax(0, 1fr)) minmax(36px, 1.45fr);
  grid-template-rows: minmax(32px, 1.3fr) repeat(<?php echo max(1, (int)$gridN - 2); ?>, minmax(0, 1fr)) minmax(32px, 1.3fr);
  gap: 1px;
  aspect-ratio: 1;
  background: #0e131a;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 2px;
}
.turn-cursor {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 8;
  pointer-events: none;
  will-change: transform;
}
.turn-cursor[hidden] { display: none; }
.turn-cursor-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  transform: translate(-50%, calc(-100% - 3px));
  filter: drop-shadow(0 2px 5px rgba(0,0,0,0.55));
}
.turn-cursor-lab {
  font-size: 0.62rem;
  font-weight: 900;
  letter-spacing: -0.04em;
  white-space: nowrap;
  color: #052e1c;
  background: var(--mint);
  border: 1px solid rgba(255,255,255,0.55);
  border-radius: 999px;
  padding: 2px 7px 1px;
  line-height: 1.2;
}
.turn-cursor-pin {
  width: 0;
  height: 0;
  border-left: 7px solid transparent;
  border-right: 7px solid transparent;
  border-top: 10px solid var(--mint);
  margin-top: -1px;
}
@keyframes cursorpop {
  0% { transform: translate(-50%, calc(-100% - 14px)) scale(0.72); }
  70% { transform: translate(-50%, calc(-100% - 1px)) scale(1.08); }
  100% { transform: translate(-50%, calc(-100% - 3px)) scale(1); }
}
.tok.tok-turn {
  width: 12px;
  height: 12px;
  outline: 2px solid #fff;
  box-shadow: 0 0 0 3px rgba(110,231,183,0.5);
  animation: tokpulse 1.1s ease-in-out infinite;
  z-index: 1;
}
@keyframes tokpulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.28); }
}
.cell {
  position: relative;
  border-radius: 6px;
  background: var(--card2);
  border: 1px solid var(--line);
  padding: 2px 2px 10px;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  cursor: pointer;
  -webkit-tap-highlight-color: rgba(110,231,183,0.25);
  touch-action: manipulation;
  user-select: none;
}
.cell.open {
  outline: 2px solid var(--mint);
  z-index: 2;
}
.cell.mine {
  box-shadow: inset 0 0 0 1px rgba(110,231,183,0.55);
}
.cell.free {
  box-shadow: inset 0 0 0 1px rgba(251,191,36,0.45);
}
.cell.free .bar { opacity: 0.38; }
.cell .vacant {
  font-size: 0.52rem;
  font-weight: 800;
  color: #fbbf24;
  letter-spacing: -0.04em;
  margin-top: 1px;
}
.cell .bar { height: 4px; border-radius: 2px; margin-bottom: 2px; flex-shrink: 0; }
.cell .nm {
  font-size: 0.52rem;
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -0.06em;
  overflow: hidden;
  word-break: keep-all;
  overflow-wrap: anywhere;
  flex-shrink: 0;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.cell.side-left,
.cell.side-right {
  padding: 3px 3px 10px;
}
.cell.side-top,
.cell.side-bot {
  padding: 3px 2px 10px;
}
.cell.side-left .pr,
.cell.side-right .pr,
.cell.side-top .pr,
.cell.side-bot .pr { display: none; }
.cell.side-left .vacant,
.cell.side-right .vacant,
.cell.side-top .vacant,
.cell.side-bot .vacant { display: none; }
.cell.side-corner {
  padding: 2px 2px 8px;
}
.cell.side-corner .bar { height: 3px; margin-bottom: 1px; }
.cell.side-corner .nm {
  font-size: 0.58rem;
  -webkit-line-clamp: 2;
}
.cell.side-corner .pr { font-size: 0.5rem; }
.cell .nm .fg,
img.fg {
  width: 0.95em;
  height: 0.95em;
  object-fit: cover;
  vertical-align: -0.12em;
  margin-right: 2px;
  border-radius: 1px;
  display: inline-block;
}
.tsheet-top h2 img.fg,
.buypop-card h3 img.fg {
  width: 1.2em;
  height: 1.2em;
  vertical-align: -0.18em;
}
.cell .pr {
  font-size: 0.46rem;
  font-weight: 700;
  color: #c5d0de;
  line-height: 1.15;
  letter-spacing: -0.04em;
  white-space: normal;
  overflow: hidden;
  word-break: keep-all;
  overflow-wrap: anywhere;
  margin-top: 1px;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}
.tokens {
  position: absolute;
  left: 3px;
  bottom: 2px;
  display: flex;
  gap: 2px;
  flex-wrap: wrap;
  max-width: calc(100% - 6px);
}
.tok {
  width: 8px; height: 8px; border-radius: 50%;
  border: 1px solid rgba(0,0,0,0.35);
}
@media (min-width: 560px) {
  .board {
    aspect-ratio: 1;
    max-width: min(100%, 72dvh);
    max-height: 72dvh;
    grid-template-columns: minmax(78px, 1.7fr) repeat(<?php echo max(1, (int)$gridN - 2); ?>, minmax(26px, 1.05fr)) minmax(78px, 1.7fr);
    grid-template-rows: minmax(58px, 1.25fr) repeat(<?php echo max(1, (int)$gridN - 2); ?>, minmax(22px, 1fr)) minmax(58px, 1.25fr);
  }
  .cell { padding: 3px 3px 12px; }
  .cell .nm { font-size: 0.82rem; -webkit-line-clamp: 3; }
  .cell .pr { font-size: 0.66rem; -webkit-line-clamp: 2; }
  .cell.side-corner .nm { font-size: 0.7rem; }
  .cell.side-left .pr,
  .cell.side-right .pr,
  .cell.side-top .pr,
  .cell.side-bot .pr { display: -webkit-box; }
  .cell.side-left .vacant,
  .cell.side-right .vacant,
  .cell.side-top .vacant,
  .cell.side-bot .vacant { display: block; }
  .buypop { align-items: center; padding: 16px; }
  .buypop-card {
    border-radius: 20px;
    padding: 22px 18px 16px;
    max-height: min(86dvh, 720px);
  }
  .mine-dock { display: none !important; }
  .center-mine { display: block; }
}
.center {
  grid-column: 2 / <?php echo (int)$gridN; ?>;
  grid-row: 2 / <?php echo (int)$gridN; ?>;
  border-radius: 12px;
  background: linear-gradient(160deg, #1a2432, #121820);
  border: 1px solid var(--line);
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: stretch;
  gap: 6px;
  padding: 6px;
  overflow: hidden;
  min-width: 0;
}
.center-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  gap: 6px;
  align-items: stretch;
}
.center-dice {
  flex: 0 0 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
}
.center-dice .meta {
  font-size: 0.68rem;
  color: #9aa3b2;
  text-align: center;
  line-height: 1.35;
}
.center-mine {
  flex: 1;
  min-width: 0;
  overflow: auto;
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 6px 7px;
  text-align: left;
}
.mine-h {
  font-weight: 800;
  font-size: 0.68rem;
  color: var(--mint);
  margin: 0 0 2px;
}
.mine-cash {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin: 0 0 6px;
  padding: 4px 6px;
  background: rgba(110, 231, 183, 0.12);
  border: 1px solid rgba(110, 231, 183, 0.28);
  border-radius: 8px;
}
.mine-cash span {
  font-size: 0.58rem;
  color: var(--muted);
  font-weight: 700;
  white-space: nowrap;
}
.mine-cash b {
  font-size: 0.82rem;
  font-weight: 900;
  color: var(--mint);
  line-height: 1.1;
  text-align: right;
}
.mine-here {
  color: var(--muted);
  font-size: 0.58rem;
  margin-bottom: 4px;
}
.mine-empty {
  color: var(--muted);
  font-size: 0.6rem;
  line-height: 1.35;
}
.mine-row {
  appearance: none;
  display: flex;
  align-items: center;
  gap: 5px;
  width: 100%;
  margin: 0;
  padding: 3px 0;
  border: 0;
  border-bottom: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  font-size: 0.6rem;
  line-height: 1.25;
  text-align: left;
  cursor: pointer;
}
.mine-row:last-child { border-bottom: 0; }
.mine-row i {
  width: 7px;
  height: 7px;
  border-radius: 2px;
  flex-shrink: 0;
}
.mine-row b { font-weight: 700; }
.mine-row span { margin-left: auto; color: var(--muted); white-space: nowrap; }
.mine-int {
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid var(--line);
}
.mine-int .lab {
  font-size: 0.58rem;
  color: var(--muted);
  font-weight: 700;
}
.mine-int .amt {
  font-size: 0.78rem;
  font-weight: 900;
  color: var(--mint);
  margin: 2px 0 6px;
}
.mine-int button {
  width: 100%;
  padding: 7px 8px;
  font-size: 0.72rem;
}
.mine-int-btns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px;
}
.mine-int-btns button {
  padding: 7px 4px;
  font-size: 0.66rem;
}
.mine-found {
  margin-top: 6px;
  font-size: 0.58rem;
  color: var(--muted);
  line-height: 1.4;
}
.mine-found b {
  color: #fbbf24;
  font-weight: 800;
}
.freelist {
  margin: 8px 0 0;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 8px 10px;
  max-height: 180px;
  overflow: auto;
}
.freelist.folded {
  max-height: none;
  overflow: hidden;
}
.freelist[hidden] { display: none; }
.free-h {
  appearance: none;
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  font-weight: 800;
  font-size: 0.72rem;
  color: #fbbf24;
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}
.freelist:not(.folded) .free-h { margin: 0 0 4px; }
.free-h .chev {
  margin-left: auto;
  font-weight: 700;
  font-size: 0.68rem;
  color: var(--muted);
}
.freelist.folded .free-row { display: none; }
.free-row {
  appearance: none;
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  margin: 0;
  padding: 4px 0;
  border: 0;
  border-bottom: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  font-size: 0.72rem;
  text-align: left;
  cursor: pointer;
}
.free-row:last-child { border-bottom: 0; }
.free-row i {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  flex-shrink: 0;
}
.free-row b { font-weight: 700; }
.free-row span { margin-left: auto; color: var(--muted); white-space: nowrap; }
.prow .int {
  font-size: 0.72rem;
  color: var(--mint);
  font-weight: 700;
  white-space: nowrap;
}
.dice {
  display: flex; gap: 6px; font-size: 1.35rem; font-weight: 800;
}
.dice b {
  width: 36px; height: 36px; border-radius: 9px;
  background: #fff; color: #111; display: grid; place-items: center;
}
.btns { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-start; align-items: center; }
.btns .giveup { margin-left: auto; }
button, .btn {
  appearance: none; border: 0; border-radius: 10px;
  padding: 9px 12px; font-weight: 700; font-size: 0.82rem;
  background: var(--mint); color: #052e1c; cursor: pointer;
}
button.ghost { background: var(--card2); color: var(--text); border: 1px solid var(--line); }
button:disabled { opacity: 0.45; cursor: not-allowed; }
body.recruit-on { overflow: hidden; }
.recruit {
  position: fixed;
  inset: 0;
  z-index: 260;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
}
.recruit[hidden] { display: none !important; }
.recruit-bg {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.72);
}
.recruit-card {
  position: relative;
  width: min(420px, 100%);
  max-height: min(90dvh, 720px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #243044, #161c26);
  border: 1px solid rgba(110,231,183,0.4);
  border-radius: 20px 20px 0 0;
  padding: 16px 14px calc(12px + env(safe-area-inset-bottom, 0px));
  box-shadow: 0 22px 60px rgba(0,0,0,0.55), 0 0 0 4px rgba(110,231,183,0.12);
}
.recruit-card h2 {
  margin: 0 0 6px;
  font-size: 1.18rem;
  text-align: center;
}
.recruit .time {
  text-align: center;
  font-weight: 900;
  color: var(--mint);
  font-variant-numeric: tabular-nums;
  font-size: 1.05rem;
}
.recruit .fee {
  color: #fbbf24;
  font-weight: 800;
}
.recruit .note {
  color: var(--muted);
  font-size: 0.78rem;
  line-height: 1.45;
  margin: 6px 0 0;
  text-align: center;
}
.recruit-count {
  margin: 10px 0 6px;
  font-size: 0.92rem;
  font-weight: 800;
  text-align: center;
}
.recruit-count b { color: var(--mint); }
.recruit-list {
  display: grid;
  gap: 6px;
  margin-top: 4px;
  overflow: auto;
  flex: 1 1 auto;
  min-height: 120px;
  max-height: 42dvh;
  padding-right: 2px;
}
.recruit-list .empty {
  color: var(--muted);
  font-size: 0.78rem;
  text-align: center;
  padding: 18px 8px;
}
.recruit-list .prow {
  background: var(--card2);
}
.recruit-list .prow .who { font-weight: 800; }
.recruit-list .prow .when {
  margin-left: auto;
  color: var(--muted);
  font-size: 0.72rem;
  font-variant-numeric: tabular-nums;
}
.recruit-list .prow.live {
  animation: recruitPop 0.7s ease;
}
@keyframes recruitPop {
  from { background: rgba(110,231,183,0.32); }
  to { background: var(--card2); }
}
.recruit-live {
  color: var(--muted);
  font-size: 0.72rem;
  text-align: center;
  margin-top: 4px;
}
.recruit-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
  margin-top: 12px;
}
.recruit-btns button.force {
  background: #f59e0b;
  color: #3a2200;
}
.recruit-prize {
  margin: 10px 0 4px;
  background: rgba(253, 230, 138, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.38);
  border-radius: 12px;
  padding: 10px 12px 8px;
}
.recruit-prize[hidden] { display: none !important; }
.recruit-prize .pt-title {
  text-align: center;
  font-size: 0.78rem;
  font-weight: 800;
  color: #fde68a;
  margin-bottom: 6px;
}
.recruit-prize .pt-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.82rem;
  padding: 2px 2px;
  color: var(--text);
}
.recruit-prize .pt-row b { font-weight: 800; }
.recruit-prize .pt-row .pt-shard { color: #fbbf24; font-weight: 800; font-variant-numeric: tabular-nums; }
.recruit-prize.pop {
  animation: recruitPrizeIn 0.55s ease;
}
@keyframes recruitPrizeIn {
  from { transform: scale(0.96); background: rgba(251, 191, 36, 0.28); }
  to { transform: scale(1); background: rgba(253, 230, 138, 0.08); }
}
.plist-wrap { position: relative; overflow: visible; margin: 10px 0; }
.plist { display: grid; gap: 6px; max-height: 240px; overflow: auto; }
.prow {
  display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px;
  background: var(--card); border: 1px solid var(--line);
  border-radius: 10px; padding: 8px 10px; font-size: 0.82rem;
}
.prow .who {
  flex: 1 1 92px;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.prow.on { border-color: var(--mint); box-shadow: 0 0 0 2px rgba(110,231,183,0.28); }
.turn-cursor-list .turn-cursor-inner {
  flex-direction: row;
  transform: translate(2px, -50%);
}
.turn-cursor-list .turn-cursor-pin {
  border-left: 10px solid var(--mint);
  border-top: 7px solid transparent;
  border-right: 0;
  border-bottom: 7px solid transparent;
  margin-top: 0;
  margin-left: -1px;
}
@keyframes cursorpop-list {
  0% { transform: translate(-8px, -50%) scale(0.72); }
  70% { transform: translate(4px, -50%) scale(1.08); }
  100% { transform: translate(2px, -50%) scale(1); }
}
.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.prow .cash { margin-left: auto; font-weight: 700; flex-shrink: 0; white-space: nowrap; }
.prow .miss {
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 700;
  white-space: nowrap;
}
.prow .miss.on { color: #f87171; font-weight: 800; }
.prow.admin { cursor: pointer; }
.prow.admin:hover { border-color: #fbbf24; }
.out { opacity: 0.45; text-decoration: line-through; }
.log {
  background: transparent;
  border: 0;
  border-radius: 0;
  padding: 0;
  font-size: 0.78rem;
  line-height: 1.45;
  max-height: 180px;
  overflow: auto;
  color: #c9d4e3;
}
.logbox {
  margin-top: 8px;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 8px 10px;
}
.logbox.folded .log { display: none; }
.logbox:not(.folded) .log-h { margin-bottom: 6px; }
.log-h,
.rent-h {
  appearance: none;
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  font-weight: 800;
  font-size: 0.72rem;
  color: #93c5fd;
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}
.log-h .n,
.rent-h .n {
  font-weight: 700;
  font-size: 0.68rem;
  color: var(--muted);
}
.log-h .chev,
.rent-h .chev {
  margin-left: auto;
  font-weight: 700;
  font-size: 0.68rem;
  color: var(--muted);
}
.rentbox {
  margin-top: 8px;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 8px 10px;
}
.rentbox[hidden] { display: none; }
.rent-h { color: #6ee7b7; cursor: default; }
.rent-empty {
  margin: 6px 0 0;
  font-size: 0.75rem;
  color: var(--muted);
}
.rent-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 0;
  border-top: 1px solid rgba(255,255,255,0.06);
  font-size: 0.78rem;
  color: #c9d4e3;
}
.rent-row b { color: #e8eef6; font-weight: 800; }
.rent-row span { margin-left: auto; color: #6ee7b7; font-weight: 700; white-space: nowrap; }
.rent-row .sub { margin-left: 0; color: var(--muted); font-weight: 650; font-size: 0.7rem; font-style: normal; flex: 1; }
.pend {
  margin: 8px 0; padding: 10px; border-radius: 12px;
  background: rgba(110,231,183,0.12); border: 1px solid rgba(110,231,183,0.35);
  font-size: 0.85rem;
  overflow-wrap: anywhere;
  word-break: keep-all;
}
.over {
  text-align: center; font-size: 1.05rem; font-weight: 800; color: var(--mint);
  margin: 8px 0 12px;
  padding: 14px 14px 12px;
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(110,231,183,0.16), rgba(18,24,32,0.96));
  border: 1px solid rgba(110,231,183,0.45);
}
.over .prize-item { font-size: 1.12rem; margin-top: 6px; color: #fde68a; }
.over .prize-sum { margin-top: 6px; color: #e8eef6; font-size: 0.95rem; }
.over .prize-cities {
  text-align: left;
  margin: 10px auto 0;
  max-width: 380px;
  font-size: 0.86rem;
  font-weight: 650;
  color: #c9d4e0;
}
.over .prize-cities div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 4px 0;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.over .prize-ranks {
  text-align: left;
  margin: 12px auto 0;
  max-width: 380px;
  padding-top: 10px;
  border-top: 1px solid rgba(255,255,255,0.14);
  font-size: 0.9rem;
  font-weight: 700;
  color: #dbe7f3;
}
.over .prize-ranks-title {
  text-align: center;
  color: #fde68a;
  font-size: 1.02rem;
  margin-bottom: 6px;
}
.over .prize-rank {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 5px 0;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.over .prize-rank b { color: #fff; font-weight: 800; }
.turnclock {
  margin: 10px 0 8px;
  padding: 16px 14px 14px;
  border-radius: 16px;
  text-align: center;
  background: linear-gradient(180deg, rgba(110,231,183,0.22), rgba(18,24,32,0.98));
  border: 2px solid var(--mint);
  box-shadow: 0 0 0 4px rgba(110,231,183,0.12);
}
.center .turnclock {
  margin: 0;
  padding: 8px 10px;
  border-radius: 10px;
  box-shadow: none;
  flex: 0 0 auto;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-align: center;
}
.center .turnclock .clock-main {
  width: 100%;
  text-align: center;
}
.turnclock[hidden] { display: none; }
.turnclock.warn {
  border-color: #fbbf24;
  background: linear-gradient(180deg, rgba(251,191,36,0.28), rgba(18,24,32,0.98));
  box-shadow: 0 0 0 4px rgba(251,191,36,0.14);
}
.center .turnclock.warn,
.center .turnclock.danger { box-shadow: none; }
.turnclock.danger {
  border-color: #f87171;
  background: linear-gradient(180deg, rgba(248,113,113,0.32), rgba(18,24,32,0.98));
  box-shadow: 0 0 0 4px rgba(248,113,113,0.18);
  animation: clockpulse 0.65s ease-in-out infinite;
}
.turnclock .who {
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  margin-bottom: 2px;
}
.center .turnclock .who { font-size: 0.82rem; }
.turnclock .sec {
  font-size: 2.8rem;
  font-weight: 900;
  letter-spacing: -0.05em;
  font-variant-numeric: tabular-nums;
  line-height: 1.05;
  color: var(--mint);
  text-shadow: 0 0 18px rgba(110,231,183,0.35);
}
.center .turnclock .sec { font-size: 1.4rem; }
.turnclock.warn .sec { color: #fbbf24; text-shadow: 0 0 18px rgba(251,191,36,0.35); }
.turnclock.danger .sec { color: #f87171; text-shadow: 0 0 18px rgba(248,113,113,0.4); }
.turnclock .hint {
  margin-top: 4px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #d5deea;
}
.center .turnclock .hint { font-size: 0.68rem; margin-top: 1px; }
.center .turnclock .clock-roll {
  display: none;
}
.center .turnclock .clock-skip {
  display: none;
}
.roll-dock {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 85;
  display: flex;
  flex-direction: row;
  align-items: stretch;
  gap: 8px;
  padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px));
  background: linear-gradient(180deg, rgba(11,13,18,0), rgba(11,13,18,0.92) 36%, #0b0d12);
}
.roll-dock[hidden] { display: none !important; }
.roll-dock button {
  display: block;
  flex: 1 1 58%;
  width: auto;
  max-width: none;
  margin: 0;
  height: 48px;
  border: 0;
  border-radius: 14px;
  padding: 0 12px;
  font-size: 1.02rem;
  font-weight: 900;
  letter-spacing: -0.03em;
  background: var(--mint);
  color: #052e1c;
  box-shadow: 0 8px 28px rgba(110,231,183,0.42);
  -webkit-tap-highlight-color: transparent;
  order: 2;
}
.roll-dock button.skiproll {
  flex: 0 1 38%;
  height: 48px;
  font-size: 0.88rem;
  background: #1c2430;
  color: #e8eef6;
  box-shadow: none;
  border: 1px solid var(--line);
  order: 1;
}
.roll-dock button:disabled { opacity: 0.55; }
@keyframes clockpulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.025); }
}
.tsheet {
  position: fixed;
  inset: 0;
  z-index: 240;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.tsheet[hidden] { display: none; }
.tsheet-bg {
  position: absolute;
  inset: 0;
  border: 0;
  padding: 0;
  background: rgba(0,0,0,0.55);
  cursor: pointer;
}
.tsheet-card {
  position: relative;
  width: min(520px, 100%);
  max-height: min(88dvh, 720px);
  overflow: auto;
  background: #1a2230;
  border: 1px solid var(--line);
  border-radius: 18px 18px 0 0;
  padding: 14px 16px calc(18px + env(safe-area-inset-bottom));
  box-shadow: 0 -12px 40px rgba(0,0,0,0.45);
  -webkit-overflow-scrolling: touch;
}
.tsheet-bar {
  height: 6px;
  border-radius: 999px;
  margin: 0 0 12px;
}
.tsheet-top {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}
.tsheet-top h2 {
  margin: 0;
  font-size: 1.2rem;
  letter-spacing: -0.03em;
}
.tsheet-sub { color: var(--muted); font-size: 0.8rem; margin-top: 3px; }
.tsheet-x {
  margin-left: auto;
  appearance: none;
  border: 0;
  background: var(--card2);
  color: var(--text);
  width: 34px;
  height: 34px;
  border-radius: 10px;
  font-size: 1.1rem;
  cursor: pointer;
  flex-shrink: 0;
}
.tsheet-rows { display: grid; gap: 0; }
.tsheet-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  border-top: 1px solid var(--line);
  font-size: 0.88rem;
}
.tsheet-row span { color: var(--muted); }
.tsheet-row b { font-weight: 700; text-align: right; }
.tsheet-row.now b { color: var(--mint); }
.tsheet-note {
  margin-top: 10px;
  font-size: 0.78rem;
  color: var(--muted);
  line-height: 1.45;
}
.tsheet-xfer {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid var(--line);
}
.tsheet-xfer .lab {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--mint);
  margin-bottom: 6px;
}
.tsheet-xfer-row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}
.tsheet-xfer select {
  flex: 1;
  min-width: 0;
  height: 40px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: var(--card2);
  color: var(--text);
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0 10px;
}
.tsheet-xfer button {
  flex-shrink: 0;
  height: 40px;
}
.buypop {
  position: fixed;
  inset: 0;
  z-index: 280;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
}
.buypop[hidden] { display: none; }
.buypop-bg {
  position: absolute;
  inset: 0;
  border: 0;
  padding: 0;
  background: rgba(0,0,0,0.62);
}
.buypop-card {
  position: relative;
  width: min(400px, 100%);
  max-height: min(88dvh, 720px);
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  background: linear-gradient(180deg, #243044, #161c26);
  border: 1px solid rgba(110,231,183,0.4);
  border-radius: 20px 20px 0 0;
  padding: 18px 14px calc(14px + env(safe-area-inset-bottom, 0px));
  text-align: center;
  box-shadow: 0 22px 60px rgba(0,0,0,0.55), 0 0 0 4px rgba(110,231,183,0.12);
}
.buypop-card h3 {
  margin: 8px 0 4px;
  font-size: 1.35rem;
  letter-spacing: -0.03em;
}
.buypop-ask {
  margin: 12px 0 6px;
  font-size: 1.12rem;
  font-weight: 900;
  color: var(--mint);
}
.buypop-meta {
  color: var(--muted);
  font-size: 0.88rem;
  line-height: 1.45;
}
.buypop-sec {
  margin-top: 8px;
  font-size: 1.35rem;
  font-weight: 900;
  color: #fbbf24;
  font-variant-numeric: tabular-nums;
}
.buypop-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
  position: sticky;
  bottom: 0;
  padding-top: 8px;
  background: linear-gradient(180deg, rgba(22,28,38,0), #161c26 28%);
}
.buypop-btns button {
  flex: 1 1 42%;
  min-width: 0;
  height: 48px;
  font-size: 1.02rem;
  font-weight: 900;
}
.buypop-card.distress {
  width: min(440px, 100%);
  text-align: left;
}
.buypop-sells {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
  max-height: 38vh;
  overflow: auto;
}
.buypop-sells button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  min-height: 52px;
  padding: 10px 12px;
  text-align: left;
  font-weight: 800;
  background: #1c2430;
  color: #e8eef6;
  border: 1px solid var(--line);
  box-shadow: none;
}
.buypop-sells button .nm {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-width: 0;
  font-weight: 900;
}
.buypop-sells button .nm i {
  font-style: normal;
  font-weight: 700;
  font-size: 0.76rem;
  color: var(--muted);
}
.buypop-sells button .net {
  color: var(--mint);
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
  white-space: nowrap;
}
.boardpop-card .buypop-btns {
  flex-direction: column;
}
.boardpop-card .buypop-btns button {
  width: 100%;
}
.buypop-card.chance {
  width: min(420px, 100%);
  text-align: left;
}
.buypop-card.travel {
  width: min(460px, 100%);
  text-align: left;
}
.buypop-cards {
  display: grid;
  gap: 8px;
  margin-top: 12px;
}
.buypop-cards button {
  height: auto;
  min-height: 64px;
  padding: 14px 12px;
  text-align: center;
  border: 1px solid var(--line);
  background: var(--card2);
  color: var(--text);
  border-radius: 12px;
  font-weight: 800;
  cursor: pointer;
}
.buypop-cards button .t { font-size: 1.08rem; font-weight: 900; }
.buypop-cities {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  margin-top: 12px;
  max-height: 46vh;
  overflow: auto;
  padding-right: 2px;
}
.buypop-cities button {
  height: auto;
  min-height: 46px;
  padding: 8px 10px;
  text-align: left;
  border: 1px solid var(--line);
  background: var(--card2);
  color: var(--text);
  border-radius: 10px;
  font-weight: 800;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}
.buypop-cities button.mine { border-color: var(--mint); }
.buypop-cities button.off { opacity: 0.42; cursor: not-allowed; }
.buypop-cities button .t { font-size: 0.92rem; font-weight: 900; line-height: 1.2; }
.buypop-cities button .s { font-size: 0.72rem; color: var(--muted); font-weight: 700; }
.mine-dock {
  margin: 8px 0 0;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 8px 10px;
  max-height: 42dvh;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
}
.mine-dock[hidden] { display: none; }
.mine-dock .center-mine {
  background: transparent;
  border: 0;
  padding: 0;
  overflow: visible;
}
.mine-dock .mine-row {
  flex-wrap: wrap;
}
.mine-dock .mine-row span {
  white-space: normal;
  flex: 1 1 100%;
  margin-left: 12px;
}
@media (max-width: 559px) {
  h1 { margin: 0 0 4px; font-size: 1.02rem; padding-right: 8px; }
  .sub { font-size: 0.72rem; margin: 0 0 6px; }
  .center { padding: 4px; gap: 4px; }
  .center-body { flex-direction: column; }
  .center-dice { flex: 0 0 auto; }
  .dice { font-size: 1.1rem; gap: 4px; }
  .dice b { width: 28px; height: 28px; border-radius: 8px; }
  .center .turnclock { padding: 6px 8px; }
  .center .turnclock .sec { font-size: 1.15rem; }
  .btns button, .btns .btn { min-height: 40px; }
  .plist { max-height: 200px; }
  .log { max-height: 140px; }
  .buypop-cities { grid-template-columns: 1fr; max-height: 36dvh; }
  .recruit-btns button { min-height: 44px; flex: 1 1 42%; }
}
@media (min-width: 560px) {
  .recruit { align-items: center; padding: 16px; }
  .recruit-card { border-radius: 20px; padding: 20px 16px 14px; }
  .roll-dock {
    flex-direction: column;
    padding: 10px 14px calc(12px + env(safe-area-inset-bottom, 0px));
  }
  .roll-dock button {
    width: 100%;
    max-width: 520px;
    margin: 0 auto;
    height: 56px;
    font-size: 1.18rem;
    flex: none;
    order: 0;
  }
  .roll-dock button.skiproll { height: 44px; font-size: 0.95rem; order: 0; }
  body.roll-dock-on .wrap { padding-bottom: 210px; }
  body.roll-dock-on .wn-fab { bottom: calc(128px + env(safe-area-inset-bottom, 0px)); }
}
</style>
</head>
<body>
<div class="wrap">
  <h1>🎲 <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
  <p class="sub" id="snapSub">새 판마다 전원 스냅샷의 <b><?php echo htmlspecialchars((string)($bootGame['grant_pct_fmt'] ?? '10%'), ENT_QUOTES, 'UTF-8'); ?></b>씩 <b>부루마블 머니</b> 동일 지급 (이번 판 <?php echo htmlspecialchars((string)($bootGame['grant_fmt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>) · <b>땅값·월급도 같은 10% 기준</b> · 월급 <?php echo htmlspecialchars((string)($bootGame['salary_fmt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>늦게 합류해도 같은 시작금 · 한 바퀴 통행료 <b><?php echo htmlspecialchars((string)($bootGame['grace_pct'] ?? '50'), ENT_QUOTES, 'UTF-8'); ?>%</b> · 남의 땅 도착 시 시세 <b><?php echo htmlspecialchars((string)($bootGame['takeover_pct'] ?? '150'), ENT_QUOTES, 'UTF-8'); ?>%</b> 인수 제안 · 거절 1회 · 2회째 강제매수 · 랜드마크는 매수 불가<br>한 바퀴마다 땅값 이자 <b><?php echo htmlspecialchars((string)($bootGame['interest_fmt'] ?? '0.5% · 펜션 +0.1% · 호텔 +0.4% · 랜드마크 +1.0%'), ENT_QUOTES, 'UTF-8'); ?></b> 가 쌓이고, 수령하면 <b>부루마블 머니 또는 게임냥</b>으로 받아요 · 사고팔기·통행료·세금은 <b>부루마블 머니</b> · 못 내면 땅 매도, 안 되면 파산<br>주사위 미돌림 1회 머니 <b>10%</b> · 2회 <b>30%</b> · 3회·4회 <b>50%</b> · 5회 강제 파산<br>민호 모집시작 후 30분 참가 · 참가비 스냅샷 시총 <b>1%</b> 게임냥 차감 · 본인 취소 불가 · 민호 모집취소 시 전액 환불</p>
  <div class="board-tools">
    <button type="button" class="board-text-btn on" id="boardTextBtn" aria-pressed="true">텍스트 접기</button>
  </div>
  <div class="board-wrap" id="boardWrap">
    <div class="board" id="board"></div>
    <div class="turn-cursor" id="turnCursor" hidden>
      <div class="turn-cursor-inner">
        <span class="turn-cursor-lab"></span>
        <span class="turn-cursor-pin"></span>
      </div>
    </div>
  </div>
  <div class="mine-dock" id="mineDock" hidden></div>
  <div class="turnclock" id="turnclock" hidden></div>
  <div class="pend" id="pend" hidden></div>
  <div class="over" id="over" hidden></div>
  <div class="btns" id="btns"></div>
  <div class="freelist" id="freelist" hidden></div>
  <div class="plist-wrap" id="plistWrap">
    <div class="plist" id="plist"></div>
    <div class="turn-cursor turn-cursor-list" id="listCursor" hidden>
      <div class="turn-cursor-inner">
        <span class="turn-cursor-lab"></span>
        <span class="turn-cursor-pin"></span>
      </div>
    </div>
  </div>
  <div class="logbox" id="logbox">
    <button type="button" class="log-h" id="logHead">로그 <span class="n" id="logCount"></span><span class="chev"></span></button>
    <div class="log" id="log"></div>
  </div>
  <div class="rentbox" id="rentbox" hidden>
    <div class="rent-h">나에게 통행료를 낸 사람 <span class="n" id="rentCount"></span></div>
    <div id="rentList"></div>
  </div>
</div>
<div class="roll-dock" id="rollDock" hidden>
  <button type="button" id="rollDockBtn">주사위 굴리기</button>
  <button type="button" class="skiproll" id="skipRollDockBtn">굴림 포기</button>
</div>
<div class="recruit" id="recruit" hidden>
  <div class="recruit-bg"></div>
  <div class="recruit-card" id="recruitCard" role="dialog" aria-modal="true" aria-labelledby="recruitTitle"></div>
</div>
<div class="tsheet" id="tsheet" hidden>
  <button type="button" class="tsheet-bg" id="tsheetBg" aria-label="닫기"></button>
  <div class="tsheet-card" id="tsheetCard" role="dialog" aria-modal="true"></div>
</div>
<div class="buypop" id="buypop" hidden>
  <div class="buypop-bg"></div>
  <div class="buypop-card" id="buypopCard" role="dialog" aria-modal="true"></div>
</div>
<div class="buypop" id="boardpop" hidden>
  <div class="buypop-bg" id="boardpopBg"></div>
  <div class="buypop-card boardpop-card" id="boardpopCard" role="dialog" aria-modal="true"></div>
</div>
<script type="application/json" id="boot"><?php echo json_encode(['q' => $q, 'nick' => $nick, 'game' => $bootGame], JSON_UNESCAPED_UNICODE); ?></script>
<script>
(function () {
  const boot = JSON.parse(document.getElementById('boot').textContent || '{}');
  const q = boot.q || location.search || '';
  const api = (location.pathname || '/page/burumable.php') + q;
  const pageTitle = (boot.game && boot.game.title) ? String(boot.game.title) : '부루마블';
  document.title = pageTitle;

  var isNarrow = false;
  try { isNarrow = window.matchMedia('(max-width: 559px)').matches; } catch (e2) {}
  var boardTextOn = !isNarrow;
  try {
    var savedText = localStorage.getItem('burumable_board_text');
    if (savedText === '0' || savedText === 'off') boardTextOn = false;
    else if (savedText === '1' || savedText === 'on') boardTextOn = true;
  } catch (e) {}
  var freeListOpen = !isNarrow;
  try {
    var savedFree = localStorage.getItem('burumable_freelist');
    if (savedFree === '0' || savedFree === 'off') freeListOpen = false;
    else if (savedFree === '1' || savedFree === 'on') freeListOpen = true;
  } catch (e) {}
  var logOpen = !isNarrow;
  try {
    var savedLog = localStorage.getItem('burumable_log');
    if (savedLog === '0' || savedLog === 'off') logOpen = false;
    else if (savedLog === '1' || savedLog === 'on') logOpen = true;
  } catch (e) {}

  function applyBoardText() {
    var sub = document.getElementById('snapSub');
    var btn = document.getElementById('boardTextBtn');
    if (sub) sub.hidden = !boardTextOn;
    if (btn) {
      btn.textContent = boardTextOn ? '텍스트 접기' : '텍스트 펴기';
      btn.setAttribute('aria-pressed', boardTextOn ? 'true' : 'false');
      btn.classList.toggle('on', boardTextOn);
    }
  }
  function applyFreeList() {
    var box = document.getElementById('freelist');
    if (!box) return;
    box.classList.toggle('folded', !freeListOpen);
    var h = box.querySelector('.free-h');
    if (h) {
      h.setAttribute('aria-expanded', freeListOpen ? 'true' : 'false');
      var chev = h.querySelector('.chev');
      if (chev) chev.textContent = freeListOpen ? '접기' : '펴기';
    }
  }
  function applyLogFold() {
    var box = document.getElementById('logbox');
    var head = document.getElementById('logHead');
    if (box) box.classList.toggle('folded', !logOpen);
    if (head) {
      head.setAttribute('aria-expanded', logOpen ? 'true' : 'false');
      var chev = head.querySelector('.chev');
      if (chev) chev.textContent = logOpen ? '접기' : '펴기';
    }
  }
  function skipRollConfirm(g) {
    var miss = parseInt(g && g.my_skip_roll, 10) || 0;
    if (!miss && g && g.players) {
      (g.players || []).forEach(function (p) {
        if (p && p.me) miss = parseInt(p.skip_roll, 10) || 0;
      });
    }
    var next = miss + 1;
    var brokeAt = parseInt(g && g.skip_broke_at, 10) || 5;
    var pen;
    if (next >= brokeAt) {
      pen = next + '회 · 강제 파산';
    } else if (next === 1) {
      pen = '1회 · 머니 10% 차감';
    } else if (next === 2) {
      pen = '2회 · 머니 30% 차감';
    } else {
      pen = next + '회 · 머니 50% 차감';
    }
    return window.confirm('주사위 굴림을 포기할까요?\n미굴림 ' + pen + '\n다음 턴 제한 시간도 10초 줄어듭니다.');
  }

  function syncRollDock(g) {
    var dock = document.getElementById('rollDock');
    var btn = document.getElementById('rollDockBtn');
    var skip = document.getElementById('skipRollDockBtn');
    if (!dock || !btn) return;
    var show = !!(g && g.has && !g.over && !g.paused && !g.recruiting && g.my_turn && !g.pending);
    dock.hidden = !show;
    document.body.classList.toggle('roll-dock-on', show);
    if (show) {
      btn.disabled = false;
      btn.textContent = g.again ? '한 번 더 굴리기' : '주사위 굴리기';
      if (skip) skip.disabled = false;
    }
  }
  applyBoardText();
  applyLogFold();
  var boardTextBtn = document.getElementById('boardTextBtn');
  if (boardTextBtn) {
    boardTextBtn.addEventListener('click', function () {
      boardTextOn = !boardTextOn;
      try { localStorage.setItem('burumable_board_text', boardTextOn ? '1' : '0'); } catch (e) {}
      applyBoardText();
    });
  }
  var logHead = document.getElementById('logHead');
  if (logHead) {
    logHead.addEventListener('click', function () {
      logOpen = !logOpen;
      try { localStorage.setItem('burumable_log', logOpen ? '1' : '0'); } catch (e) {}
      applyLogFold();
    });
  }
  var rollDockBtn = document.getElementById('rollDockBtn');
  if (rollDockBtn) {
    rollDockBtn.addEventListener('click', function (ev) {
      ev.preventDefault();
      if (rollDockBtn.disabled) return;
      rollDockBtn.disabled = true;
      act('roll');
    });
  }
  var skipRollDockBtn = document.getElementById('skipRollDockBtn');
  if (skipRollDockBtn) {
    skipRollDockBtn.addEventListener('click', function (ev) {
      ev.preventDefault();
      if (skipRollDockBtn.disabled) return;
      if (!skipRollConfirm(lastGame)) return;
      skipRollDockBtn.disabled = true;
      act('skiproll');
    });
  }

  function post(action, extra) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('code', (q.split('code=')[1] || '').split('&')[0]);
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        body.set(k, extra[k]);
      });
    }
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(function (r) {
      return r.text().then(function (t) {
        try {
          return JSON.parse(t);
        } catch (e) {
          return { ok: false, data: '응답을 읽지 못했어요. 새로고침 해 주세요.' };
        }
      });
    });
  }

  function fmtTurnLeft(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function fmtTurnLimit(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    if (sec >= 60 && sec % 60 === 0) return (sec / 60) + '분';
    if (sec >= 60) return Math.floor(sec / 60) + '분 ' + (sec % 60) + '초';
    return sec + '초';
  }

  let clockTimer = null;
  let clock = null;
  let clockAsked = false;

  function clockRemain() {
    if (!clock) return 0;
    if (clock.paused) return Math.max(0, parseInt(clock.left, 10) || 0);
    var elapsed = Math.floor((Date.now() - clock.at) / 1000);
    return Math.max(0, (parseInt(clock.left, 10) || 0) - elapsed);
  }

  function isWaitPend(kind) {
    return kind === 'buy' || kind === 'build' || kind === 'chance' || kind === 'travel' || kind === 'forcebuy' || kind === 'takeover' || kind === 'takeover_offer' || kind === 'distress';
  }

  function paintClock() {
    var box = document.getElementById('turnclock');
    if (!box) return;
    if (!clock) {
      box.hidden = true;
      box.innerHTML = '';
      box.dataset.paint = '';
      return;
    }
    var sec = clockRemain();
    box.hidden = false;
    box.className = 'turnclock' + (sec <= 10 ? ' danger' : (sec <= 20 ? ' warn' : ''));
    var who = esc(clock.waitFor || '다른 친구');
    var paintKey = ['bar', clock.kind || 'turn', clock.mine ? '1' : '0', clock.again ? '1' : '0', clock.waitFor || '', clock.sinbul ? '1' : '0', clock.paused ? 'p' : ''].join('|');
    if (box.dataset.paint === paintKey) {
      var secEl = box.querySelector('.sec');
      if (secEl) secEl.textContent = fmtTurnLeft(sec);
    } else {
      box.dataset.paint = paintKey;
      function clockMain(inner) {
        return '<div class="clock-main">' + inner + '</div>';
      }
      var html = '';
      if (clock.paused || clock.kind === 'pause') {
        html = clockMain('<div class="who">일시멈춤</div>'
          + '<div class="sec">' + fmtTurnLeft(sec) + '</div>'
          + '<div class="hint">지금 상태로 멈춰 있어요 · 민호가 이어하기 · 종료 · 다시 시작</div>');
      } else if (isWaitPend(clock.kind)) {
        var picking = clock.kind === 'build';
        var chance = clock.kind === 'chance';
        var travel = clock.kind === 'travel';
        var forcebuy = clock.kind === 'forcebuy';
        var take = clock.kind === 'takeover';
        var takeOffer = clock.kind === 'takeover_offer';
        var distress = clock.kind === 'distress';
        var whoMine = takeOffer
          ? ' 님 · 인수 응답'
          : (distress ? ' 님 · 빚 정리'
            : (take ? ' 님 · 인수' : (forcebuy ? ' 님 · 강제구매' : (travel ? ' 님 · 세계여행' : (chance ? ' 님 · 찬스 카드' : (picking ? ' 님 · 내 땅 건설' : ' 님 · 내 땅 고르기'))))));
        var whoWait = takeOffer
          ? ' 님이 인수를 고르는 중'
          : (distress ? ' 님이 땅을 팔아 빚을 갚는 중'
            : (take ? ' 님이 인수를 고르는 중' : (forcebuy ? ' 님이 강제구매 도시를 고르는 중' : (travel ? ' 님이 세계여행 도시를 고르는 중' : (chance ? ' 님이 찬스 카드를 고르는 중' : (picking ? ' 님이 건물을 고르는 중' : ' 님이 땅을 고르는 중'))))));
        var hintAct = takeOffer
          ? '받거나 거절'
          : (distress ? '땅 매도 또는 내기'
            : (take ? '인수 또는 통행료' : (forcebuy ? '도시 고르기' : (travel ? '도시 고르기' : (chance ? '카드 고르기' : (picking ? '짓거나 패스' : '사거나 패스'))))));
        var hintZero = takeOffer
          ? '거절·통행료'
          : (distress ? '전체 파산'
            : (take ? '통행료' : (forcebuy ? '무작위 또는 패스' : (travel ? '무작위 도시' : (chance ? '무작위' : '자동 패스')))));
        html = clockMain('<div class="who">' + who + (clock.mine ? whoMine : whoWait) + '</div>'
          + '<div class="sec">' + fmtTurnLeft(sec) + '</div>'
          + '<div class="hint">' + esc(fmtTurnLimit(clock.limit || (distress || chance || travel || picking ? 30 : 20))) + ' 안에 ' + hintAct + ' · 0이면 ' + hintZero + '</div>');
      } else if (clock.mine) {
        html = clockMain('<div class="who">' + (clock.again ? '더블! 한 번 더' : '내 차례예요') + '</div>'
          + '<div class="sec">' + fmtTurnLeft(sec) + '</div>'
          + '<div class="hint">' + esc(fmtTurnLimit(clock.limit || 60)) + ' 안에 주사위를 돌려 주세요</div>')
          + '<button type="button" class="clock-roll" id="clockRoll">' + (clock.again ? '한 번 더 굴리기' : '주사위 굴리기') + '</button>'
          + '<button type="button" class="clock-skip" id="clockSkip">굴림 포기</button>';
      } else {
        html = clockMain('<div class="who">지금은 ' + who + ' 님 턴이에요</div>'
          + '<div class="sec">' + fmtTurnLeft(sec) + '</div>'
          + '<div class="hint">0이 되면 다음 턴으로 넘어가요</div>');
      }
      box.innerHTML = html;
      var rollBtn = document.getElementById('clockRoll');
      if (rollBtn) {
        rollBtn.onclick = function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          act('roll');
        };
      }
      var skipBtn = document.getElementById('clockSkip');
      if (skipBtn) {
        skipBtn.onclick = function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          if (!skipRollConfirm(lastGame)) return;
          act('skiproll');
        };
      }
    }
    var buySec = document.getElementById('buypopSec');
    if (buySec && clock && isWaitPend(clock.kind)) {
      buySec.textContent = fmtTurnLeft(sec);
    }
    if (sec <= 0 && !clockAsked && !(clock && clock.paused)) {
      clockAsked = true;
      post('status').then(function (res) {
        if (res && res.ok) render(res.game || {});
      }).catch(function () {}).then(function () {
        clockAsked = false;
      });
    }
  }

  function armClock(g) {
    if (clockTimer) {
      clearInterval(clockTimer);
      clockTimer = null;
    }
    if (!g || !g.has || g.over) {
      clock = null;
      paintClock();
      return;
    }
    if (g.paused) {
      var pkind = g.pending ? (g.pending.kind || 'buy') : 'turn';
      var pleft = isWaitPend(pkind) ? (parseInt(g.buy_left, 10) || 0) : (parseInt(g.turn_left, 10) || 0);
      clock = {
        key: 'pause|' + (g.wait_for || '') + '|' + pleft,
        kind: 'pause',
        paused: true,
        left: pleft,
        limit: pleft,
        at: Date.now(),
        waitFor: g.wait_for || '',
        mine: false,
        sinbul: false,
        again: false
      };
      paintClock();
      return;
    }
    var kind = g.pending ? (g.pending.kind || 'buy') : 'turn';
    var left = isWaitPend(kind) ? (parseInt(g.buy_left, 10) || 0) : (parseInt(g.turn_left, 10) || 0);
    var limit = isWaitPend(kind)
      ? (parseInt(g.buy_limit, 10) || (kind === 'buy' || kind === 'takeover' || kind === 'takeover_offer' ? 20 : 30))
      : (parseInt(g.turn_limit, 10) || 60);
    var key = kind + '|' + (g.wait_for || '') + '|' + ((g.pending && (g.pending.name || (g.pending.cards || []).map(function (c) { return c.id; }).join(','))) || '') + '|' + (g.turn || 0) + '|' + (g.again ? '1' : '0') + '|' + limit + '|' + (((g.dice || [])[0] || 0) + '-' + ((g.dice || [])[1] || 0));
    if (clock && clock.key === key) {
      var local = clockRemain();
      if (Math.abs(local - left) >= 2) {
        clock.left = left;
        clock.at = Date.now();
      }
      clock.limit = limit;
    } else {
      clock = {
        key: key,
        kind: kind,
        left: left,
        limit: limit,
        at: Date.now(),
        waitFor: g.wait_for || '',
        mine: isWaitPend(kind) ? !!(g.pending && g.pending.mine) : !!g.my_turn,
        sinbul: !!g.wait_sinbul,
        again: !!g.again
      };
    }
    paintClock();
    clockTimer = setInterval(paintClock, 250);
  }

  function el(tag, cls, html) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  function prizeHtml(g) {
    const p = g.prize || {};
    let html = '🏆 우승 · ' + esc(g.winner || '');
    html += '<div class="prize-item">1등 보상내역</div>';
    if (p.item) {
      html += '<div class="prize-item">' + esc(p.item) + ' 지급</div>';
    }
    const eun1 = parseInt(p.eunchong, 10) || 0;
    if (eun1 > 0) {
      html += '<div class="prize-sum">은총 +' + eun1 + '</div>';
    }
    const shard1 = parseInt(p.shard, 10) || 0;
    if (shard1 > 0) {
      html += '<div class="prize-sum">은총조각 +' + shard1 + '</div>';
    }
    const landPct = parseInt(p.land_pct, 10) || 100;
    const count = parseInt(p.count, 10) || (p.cities ? p.cities.length : 0);
    if (p.land_fmt && count > 0) {
      html += '<div class="prize-sum">도시 ' + count + '칸 ' + landPct + '% 매각 · 게임냥 ' + esc(p.land_fmt) + '</div>';
    } else if (p.land_fmt) {
      html += '<div class="prize-sum">도시 매각 게임냥 ' + esc(p.land_fmt) + '</div>';
    }
    const cities = p.cities || [];
    if (cities.length) {
      html += '<div class="prize-cities">';
      cities.forEach(function (c) {
        html += '<div><span>' + esc(c.name || '') + (c.build ? (' · ' + esc(c.build)) : '') + '</span><span>' + esc(c.value_fmt || '') + '</span></div>';
      });
      html += '</div>';
    }
    const prizeRanks = p.ranks || [];
    const ranks = (prizeRanks.length ? prizeRanks : (g.ranks || [])).filter(function (r) {
      return parseInt(r.place, 10) >= 2 && parseInt(r.place, 10) <= 5 && r.name;
    });
    if (ranks.length) {
      html += '<div class="prize-ranks">';
      html += '<div class="prize-ranks-title">2~5등</div>';
      ranks.sort(function (a, b) { return (parseInt(a.place, 10) || 0) - (parseInt(b.place, 10) || 0); });
      ranks.forEach(function (r) {
        const bits = [];
        const eg = parseInt(r.eunchong_got, 10) || parseInt(r.eunchong, 10) || 0;
        if (eg > 0) bits.push('은총 +' + eg);
        const sg = parseInt(r.shard_got, 10) || parseInt(r.shard, 10) || 0;
        if (sg > 0) bits.push('은총조각 +' + sg);
        const lp = parseInt(r.land_pct, 10) || 0;
        if (lp > 0 && r.land_fmt) bits.push('땅 ' + lp + '% ' + r.land_fmt);
        html += '<div class="prize-rank"><span><b>' + esc(String(r.place)) + '등</b> ' + esc(r.name) + '</span><span>' + esc(bits.join(' · ') || (r.how_label || r.how || '')) + '</span></div>';
      });
      html += '</div>';
    }
    return html;
  }

  function flagImg(code) {
    const cc = String(code || '').toLowerCase();
    if (!/^[a-z]{2}$/.test(cc)) return '';
    const a = (0x1f1e6 + (cc.charCodeAt(0) - 97)).toString(16);
    const b = (0x1f1e6 + (cc.charCodeAt(1) - 97)).toString(16);
    return '<img class="fg" alt="" src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/' + a + '-' + b + '.png">';
  }

  function flagName(c) {
    const name = esc((c && c.name) || '');
    const img = flagImg(c && c.flag);
    return img ? (img + name) : name;
  }

  let lastGame = null;
  let lastCursorWho = '';
  let openTileId = null;
  const tsheet = document.getElementById('tsheet');
  const tsheetCard = document.getElementById('tsheetCard');
  document.getElementById('tsheetBg').onclick = closeSheet;

  function closeSheet() {
    openTileId = null;
    tsheet.hidden = true;
    document.querySelectorAll('.cell.open').forEach(function (n) {
      n.classList.remove('open');
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const boardpop = document.getElementById('boardpop');
      if (boardpop && !boardpop.hidden) {
        closeBoardPop();
        return;
      }
      if (openTileId != null) {
        closeSheet();
      }
    }
  });

  function rowHtml(label, value, cls) {
    if (value == null || value === '') return '';
    return '<div class="tsheet-row' + (cls ? (' ' + cls) : '') + '"><span>' + esc(label) + '</span><b>' + esc(value) + '</b></div>';
  }

  function fillSheet(id) {
    const cells = (lastGame && lastGame.cells) ? lastGame.cells : [];
    let c = null;
    for (let i = 0; i < cells.length; i++) {
      if (String(cells[i].id) === String(id)) {
        c = cells[i];
        break;
      }
    }
    if (!c) {
      closeSheet();
      return;
    }
    const here = (c.tokens || []).map(function (t) { return t.name; }).join(', ');
    const mates = (c.group_names || []).join(' · ');
    let html = '<div class="tsheet-bar" style="background:' + esc(c.owner_color || c.color || '#6ee7b7') + '"></div>';
    html += '<div class="tsheet-top"><div><h2>' + flagName(c) + '</h2>';
    html += '<div class="tsheet-sub">' + esc(c.type_label || '') + (c.hint ? (' · ' + esc(c.hint)) : '') + '</div></div>';
    html += '<button type="button" class="tsheet-x" id="tsheetX">✕</button></div>';
    html += '<div class="tsheet-rows">';
    if (c.type === 'land') {
      html += rowHtml('주인', c.owner ? c.owner : '없음');
      html += rowHtml('건물', c.build_label || '빈땅');
      html += rowHtml('땅값', c.price_full || c.price_fmt, 'now');
      if (c.keep_pct && Number(c.keep_pct) < 100) {
        html += rowHtml('양도 회복', String(c.keep_pct) + '% · 한 바퀴마다 +' + String(c.give_heal_pct || 5) + '%' + (c.heal_left ? (' · ' + String(c.heal_left) + '턴 후 원상') : ''));
      }
      html += rowHtml('지금 통행료', c.rent_full || c.rent_fmt, 'now');
      if (c.group_done) html += rowHtml('색 완성', '같은 색 모두 보유 · 통행료 2배');
      html += rowHtml('빈땅 통행료', c.rent_base_fmt);
      html += rowHtml('색완성 통행료', c.rent_set_fmt);
      html += rowHtml('펜션 통행료', c.rent_pen_fmt);
      html += rowHtml('호텔 통행료', c.rent_hot_fmt);
      html += rowHtml('랜드마크 통행료', c.rent_lm_fmt);
      html += rowHtml('펜션 건설비', c.pension_fmt);
      html += rowHtml('호텔 건설비', c.hotel_fmt);
      html += rowHtml('랜드마크 건설비', c.landmark_fmt);
      if (c.no_takeover) html += rowHtml('매수', '불가 · 랜드마크');
      else if (c.owner) {
        const rn = Number(c.refuse_n || 0);
        const rm = Number(c.refuse_max || 1);
        html += rowHtml('인수 거절', c.force_takeover ? (rn + '/' + rm + ' · 강제매수') : (rn + '/' + rm));
        html += rowHtml('강제매수', '시세 ' + String((lastGame && lastGame.takeover_pct) || 150) + '% 웃돈');
      }
      if (mates) html += rowHtml('같은 색', mates);
    } else if (c.type === 'go') {
      html += rowHtml('월급', lastGame.salary_fmt || '');
    } else if (c.type === 'tax') {
      html += rowHtml('세금', c.rent_fmt || ('부동산 ' + String((lastGame && lastGame.tax_pct) || 10) + '%'));
      html += rowHtml('면제', '땅이 없으면 세금 없음');
    }
    html += rowHtml('지금 여기', here || '없음');
    html += '</div>';
    if (c.type === 'land') {
      html += '<p class="tsheet-note">내 땅에 다시 도착하면 펜션 → 호텔 → 랜드마크 순으로 지을 수 있어요. 한 바퀴 땅이자는 땅값의 0.5%이고, 펜션이면 +0.1%, 호텔이면 +0.4%, 랜드마크면 +1.0%예요. 같은 색을 모두 가지면 빈땅 통행료가 2배예요. 통행료는 빈땅 → 색완성 2배 → 펜션 5배 → 호텔 12배 → 랜드마크 25배예요. 남의 땅에 도착하면 시세 ' + esc(String((lastGame && lastGame.takeover_pct) || 150)) + '%로 인수를 제안할 수 있어요. 땅마다 인수 거절은 1회까지이고, 2번째 도착한 사람은 강제매수할 수 있어요. 랜드마크는 매수할 수 없어요.</p>';
    }
    const mineLand = !!(lastGame && lastGame.me && c.type === 'land' && c.owner === lastGame.me && !lastGame.over);
    const pendTile = lastGame && lastGame.pending ? lastGame.pending.tile : null;
    const pendBusy = pendTile != null && String(pendTile) === String(c.id);
    if (mineLand) {
      html += '<div class="tsheet-xfer"><div class="lab">이 땅 매각</div>';
      if (pendBusy) {
        html += '<div class="tsheet-note" style="margin:0">이 칸은 지금 고르는 중이라 팔 수 없어요.</div>';
      } else {
        html += '<div class="tsheet-xfer-row"><button type="button" id="sellBtn">매각하기' + (c.sell_net_fmt ? (' · 수령 ' + esc(c.sell_net_fmt)) : '') + '</button></div>';
        html += '<div class="tsheet-note" style="margin:6px 0 0">매각 수수료 땅값·건물 ' + esc(String(c.sell_pct || 10)) + '%' + (c.sell_fee_fmt ? (' ' + esc(c.sell_fee_fmt)) : '') + ' · 5%씩 소멸 · 수령 ' + esc(c.sell_net_fmt || '0') + '</div>';
      }
      html += '</div>';
    }
    tsheetCard.innerHTML = html;
    const x = document.getElementById('tsheetX');
    if (x) x.onclick = closeSheet;
    const sellBtn = document.getElementById('sellBtn');
    if (sellBtn) {
      sellBtn.onclick = function () {
        const bld = c.build_label && c.build_label !== '빈땅' ? (' · ' + c.build_label) : '';
        var msg = (c.name || '이 땅') + bld + '을(를) 매각할까요?';
        msg += '\n매각가 ' + (c.sell_gross_fmt || c.price_full || '') + ' · 수수료 ' + (c.sell_pct || 10) + '%' + (c.sell_fee_fmt ? (' ' + c.sell_fee_fmt) : '') + ' · 5%씩 소멸';
        msg += '\n수령 ' + (c.sell_net_fmt || '0') + ' · 땅은 빈땅이 되고 건물은 철거됩니다.';
        if (!confirm(msg)) return;
        act('sell', { tile: c.id });
        closeSheet();
      };
    }
    tsheet.hidden = false;
    document.querySelectorAll('.cell.open').forEach(function (n) {
      n.classList.remove('open');
    });
    const on = document.querySelector('.cell[data-tid="' + String(c.id) + '"]');
    if (on) on.classList.add('open');
  }

  function openTile(id) {
    if (openTileId != null && String(openTileId) === String(id)) {
      closeSheet();
      return;
    }
    openTileId = id;
    fillSheet(id);
  }

  function hideBuyPop() {
    const pop = document.getElementById('buypop');
    pop.hidden = true;
    pop.dataset.key = '';
    const card = document.getElementById('buypopCard');
    if (card) card.className = 'buypop-card';
  }

  function closeBoardPop() {
    const pop = document.getElementById('boardpop');
    if (pop) pop.hidden = true;
  }

  function openAdminPop(g, p) {
    const pop = document.getElementById('boardpop');
    const card = document.getElementById('boardpopCard');
    if (!pop || !card || !g || !g.can_reset || !p || !p.name) return;
    closeSheet();
    hideBuyPop();
    const miss = parseInt(p.skip_roll, 10) || 0;
    const waitFor = g.wait_for || '';
    const canSkip = !g.over && !g.paused && !p.out && waitFor === p.name;
    const canBroke = !g.over && !p.out;
    const canRevive = !!(g.can_reset && p.out && !g.recruiting && g.end_kind !== 'settle');
    card.innerHTML = '<h3>' + esc(p.name) + (p.broke ? ' · 파산' : (p.out ? ' · 포기' : '')) + '</h3>'
      + '<div class="buypop-ask">주사위 미굴림 <b style="color:#f87171">' + miss + '</b>회</div>'
      + '<div class="buypop-meta">미돌림 ' + esc(g.skip_fmt || '1회 머니 10% · 2회 30% · 3회·4회 50% · 5회 강제 파산') + '. 강제 파산은 땅·건물이 빈땅이 되고 이번 판 참가 불가예요. 부활은 파산·포기한 친구를 다시 참가시킵니다. 땅은 돌아가지 않고, 머니가 없으면 시작금을 줍니다.</div>'
      + '<div class="buypop-btns"></div>';
    const box = card.querySelector('.buypop-btns');
    function addBtn(label, cls, fn, disabled) {
      const b = el('button', cls || '', label);
      b.type = 'button';
      b.disabled = !!disabled;
      b.onclick = function () {
        if (b.disabled) return;
        closeBoardPop();
        if (fn) fn();
      };
      box.appendChild(b);
    }
    addBtn('부활', canRevive ? '' : 'ghost', function () {
      if (!window.confirm(p.name + ' 님을 부활시킬까요?\n땅은 빈땅 그대로고, 머니가 없으면 시작금을 줍니다.')) return;
      act('replay', { who: p.name });
    }, !canRevive);
    addBtn('강제파산', '', function () {
      if (!window.confirm(p.name + ' 님을 강제 파산할까요?\n땅은 빈땅이 되고 이번 판 참가 불가예요.')) return;
      act('force_broke', { who: p.name });
    }, !canBroke);
    addBtn('턴 넘김', canSkip ? '' : 'ghost', function () {
      if (!window.confirm(p.name + ' 님 턴을 넘길까요?')) return;
      act('force_skip', { who: p.name });
    }, !canSkip);
    addBtn('닫기', 'ghost', function () {});
    pop.hidden = false;
  }

  function giveupConfirm(g) {
    var msg = '이번 판을 포기할까요?\n땅·건물은 전부 매각되고(수수료 ' + (g.giveup_pct || 10) + '% 소멸) 이번 판에서는 빠집니다.\n다음 판부터 다시 참여할 수 있어요.';
    if (g && g.giveup_total && String(g.giveup_total) !== '0') {
      msg = '이번 판을 포기할까요?\n';
      if (g.giveup_lands) {
        msg += '땅 ' + g.giveup_lands + '칸 매각 ' + (g.giveup_land_fmt || '0');
        if (g.giveup_fee_fmt) msg += ' · 수수료 ' + g.giveup_fee_fmt + ' 소멸';
        msg += '\n';
      }
      if (g.giveup_interest_fmt && g.my_interest && String(g.my_interest) !== '0') {
        msg += '누적 이자 ' + g.giveup_interest_fmt + '\n';
      }
      msg += '합계 ' + (g.giveup_total_fmt || '') + ' 이 부루마블 머니로 들어옵니다.\n이번 판에서는 빠지고, 다음 판부터 다시 참여할 수 있어요.';
    }
    return window.confirm(msg);
  }

  function addGiveupBtn(btns, g) {
    if (!btns || !g || !g.can_giveup) return;
    const gg = el('button', 'ghost giveup', '이번판포기' + (g.giveup_total && String(g.giveup_total) !== '0' ? (' · 매각 ' + (g.giveup_total_fmt || '')) : ''));
    gg.onclick = function () {
      if (giveupConfirm(g)) act('giveup');
    };
    btns.appendChild(gg);
  }

  var recruitClock = null;
  var recruitTimer = null;
  function fmtRecruitLeft(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + '분 ' + (s < 10 ? '0' : '') + s + '초';
  }
  function recruitRemain() {
    if (!recruitClock) return 0;
    var elapsed = Math.floor((Date.now() - recruitClock.at) / 1000);
    return Math.max(0, (parseInt(recruitClock.left, 10) || 0) - elapsed);
  }
  function paintRecruitTime() {
    var t = document.getElementById('recruitTime');
    if (!t) return;
    if (!recruitClock) {
      t.textContent = '모집 종료';
      return;
    }
    var sec = recruitRemain();
    t.textContent = sec > 0 ? ('남은 시간 ' + fmtRecruitLeft(sec)) : '모집 종료 · 민호가 판을 시작할 수 있어요';
  }
  function armRecruit(g) {
    if (!g || !g.recruiting) {
      if (recruitTimer) {
        clearInterval(recruitTimer);
        recruitTimer = null;
      }
      recruitClock = null;
      return;
    }
    var left = parseInt(g.recruit_left, 10) || 0;
    if (recruitClock) {
      var shown = recruitRemain();
      if (Math.abs(shown - left) < 2) {
        paintRecruitTime();
        return;
      }
    }
    recruitClock = { left: left, at: Date.now() };
    paintRecruitTime();
    if (!recruitTimer) {
      recruitTimer = setInterval(paintRecruitTime, 250);
    }
  }
  function paintRecruitList(apps) {
    var list = document.getElementById('recruitList');
    var cnt = document.getElementById('recruitCount');
    if (cnt) cnt.textContent = String((apps || []).length) + '명';
    if (!list) return;
    apps = apps || [];
    var key = apps.map(function (p) { return String(p.name || ''); }).join('\n');
    if (list.dataset.key === key) return;
    var prev = list.dataset.key || '';
    var grew = key.length > prev.length;
    var scroll = list.scrollTop;
    list.dataset.key = key;
    if (!apps.length) {
      list.innerHTML = '<div class="empty">아직 참가자가 없어요. 참가하면 여기에 바로 나와요.</div>';
      return;
    }
    var html = '';
    apps.forEach(function (p, i) {
      var last = i === apps.length - 1;
      html += '<div class="prow' + (p.me ? ' on' : '') + (last ? ' live' : '') + '">';
      html += '<span class="who">' + (p.me ? '나 · ' : '') + esc(String(p.no || (i + 1))) + '. ' + esc(p.name || '') + '</span>';
      html += '<span class="when">' + esc(p.at_fmt || '') + '</span>';
      html += '</div>';
    });
    list.innerHTML = html;
    list.scrollTop = grew ? list.scrollHeight : scroll;
  }
  function paintRecruitPrize(g) {
    var box = document.getElementById('recruitPrize');
    if (!box) return;
    var t = (g && g.prize_table) ? g.prize_table : {};
    var rows = t.rows || [];
    var tier = parseInt(t.tier, 10) || 0;
    if (!tier || !rows.length) {
      box.hidden = true;
      box.innerHTML = '';
      delete box.dataset.tier;
      return;
    }
    var html = '<div class="pt-title">✨ 시상 · ' + esc(String(tier)) + '명 이상</div>';
    rows.forEach(function (r) {
      var label = String(r.label || '');
      if (!label) {
        var parts = [];
        var eun = parseInt(r.eunchong, 10) || 0;
        var shard = parseInt(r.shard, 10) || 0;
        if (eun > 0) parts.push('은총 ' + eun + '개');
        if (shard > 0) parts.push('조각 ' + shard + '개');
        label = parts.join(' + ');
      }
      html += '<div class="pt-row"><b>' + esc(String(r.place)) + '등</b><span class="pt-shard">' + esc(label) + '</span></div>';
    });
    var prev = box.dataset.tier || '';
    var next = String(tier);
    var key = next + '|' + rows.map(function (r) { return r.place + ':' + (r.eunchong || 0) + ':' + (r.shard || 0); }).join(',');
    if (box.dataset.key === key) {
      box.hidden = false;
      return;
    }
    box.innerHTML = html;
    box.dataset.key = key;
    box.hidden = false;
    if (prev !== next) {
      box.classList.remove('pop');
      void box.offsetWidth;
      box.classList.add('pop');
    }
    box.dataset.tier = next;
  }
  function fillRecruitBtns(g) {
    var btns = document.getElementById('recruitBtns');
    if (!btns || !g) return;
    var sig = [g.can_join ? 1 : 0, g.join_afford ? 1 : 0, g.applied ? 1 : 0, g.can_start ? 1 : 0, g.can_force_start ? 1 : 0, g.can_extend_recruit ? 1 : 0, g.can_remind_recruit ? 1 : 0, g.can_cancel_recruit ? 1 : 0, (g.applicants || []).length, g.recruit_fee_fmt || ''].join('|');
    if (btns.dataset.sig === sig) return;
    btns.dataset.sig = sig;
    btns.innerHTML = '';
    if (g.can_join) {
      const b = el('button', g.join_afford ? '' : 'ghost', '참가하기 · ' + esc(g.recruit_fee_fmt || ''));
      b.onclick = function () {
        if (!g.join_afford) {
          alert('게임냥이 부족해요.\n참가비 ' + (g.recruit_fee_fmt || '') + '\n보유 ' + (g.my_pt_fmt || '0'));
          return;
        }
        if (!window.confirm('참가비 게임냥 ' + (g.recruit_fee_fmt || '') + '가 바로 차감됩니다. 본인이 취소할 수는 없고, 민호가 모집을 취소하면 전액 환불됩니다.\n참가할까요?')) return;
        act('join');
      };
      btns.appendChild(b);
    } else if (g.applied) {
      const done = el('button', 'ghost', '참가 완료');
      done.disabled = true;
      btns.appendChild(done);
    }
    if (g.can_force_start && !g.can_start) {
      const n = (g.applicants || []).length;
      const fs = el('button', 'force', '강제시작 · ' + n + '명');
      fs.onclick = function () {
        if (!window.confirm('모집 시간이 남아 있어도 지금 강제 시작할까요?\n참가 ' + n + '명으로 바로 판이 시작됩니다.')) return;
        act('force_start');
      };
      btns.appendChild(fs);
    }
    if (g.can_start) {
      const st = el('button', '', '판 시작 · ' + String((g.applicants || []).length) + '명');
      st.onclick = function () {
        if (!window.confirm('참가 ' + String((g.applicants || []).length) + '명으로 판을 시작할까요?')) return;
        act('new');
      };
      btns.appendChild(st);
    }
    if (g.can_extend_recruit) {
      const ex = el('button', '', '모집연장 +10분');
      ex.onclick = function () {
        if (!window.confirm('모집 시간을 10분 연장할까요?\n시간이 끝났으면 다시 10분 동안 참가할 수 있어요.')) return;
        act('recruit_extend');
      };
      btns.appendChild(ex);
    }
    if (g.can_remind_recruit) {
      const rm = el('button', '', '모집알람 다시보내기');
      rm.onclick = function () {
        if (!window.confirm('게임방에 모집 알람을 다시 보낼까요?')) return;
        act('recruit_remind');
      };
      btns.appendChild(rm);
    }
    if (g.can_cancel_recruit) {
      const cc = el('button', 'ghost', '모집취소');
      cc.onclick = function () {
        var n = (g.applicants || []).length;
        var msg = '모집을 취소할까요?';
        if (n > 0) msg += '\n참가 ' + n + '명의 참가비 게임냥을 전액 환불합니다.';
        if (!window.confirm(msg)) return;
        act('recruit_cancel');
      };
      btns.appendChild(cc);
    }
  }
  function fillRecruit(g) {
    var box = document.getElementById('recruit');
    var card = document.getElementById('recruitCard');
    if (!box || !card) return;
    if (!g || !g.recruiting) {
      box.hidden = true;
      document.body.classList.remove('recruit-on');
      card.innerHTML = '';
      delete box.dataset.built;
      return;
    }
    document.body.classList.add('recruit-on');
    if (!box.dataset.built) {
      var html = '<h2 id="recruitTitle">📣 참가 모집</h2>';
      html += '<div class="time" id="recruitTime"></div>';
      html += '<div class="note">참가비 게임냥 <span class="fee" id="recruitFee"></span> (스냅샷 시총 1%) · 본인 참가 취소는 안 돼요 · 민호가 모집 취소하면 전액 환불</div>';
      html += '<div class="note">내 게임냥 <span id="recruitMyPt"></span></div>';
      html += '<div class="recruit-count">참가자 <b id="recruitCount">0명</b></div>';
      html += '<div class="recruit-prize" id="recruitPrize" hidden></div>';
      html += '<div class="recruit-list" id="recruitList"></div>';
      html += '<div class="recruit-live">참가하면 바로 목록에 올라와요</div>';
      html += '<div class="recruit-btns" id="recruitBtns"></div>';
      card.innerHTML = html;
      box.dataset.built = '1';
    }
    var feeEl = document.getElementById('recruitFee');
    var ptEl = document.getElementById('recruitMyPt');
    if (feeEl) feeEl.textContent = g.recruit_fee_fmt || '';
    if (ptEl) ptEl.textContent = g.my_pt_fmt || '0';
    paintRecruitList(g.applicants || []);
    paintRecruitPrize(g);
    fillRecruitBtns(g);
    box.hidden = false;
    paintRecruitTime();
  }
  function addRecruitStartBtn(btns, g) {
    if (!btns || !g || !g.can_recruit) return;
    const b = el('button', '', '모집시작');
    b.onclick = function () {
      if (!window.confirm('30분 동안 참가 신청을 받을까요?\n참가비는 스냅샷 시총 1% 게임냥입니다.\n본인 참가 취소는 안 되고, 모집 취소하면 전액 환불됩니다.')) return;
      act('recruit');
    };
    btns.appendChild(b);
  }

  function openBoardPop(g) {
    const pop = document.getElementById('boardpop');
    const card = document.getElementById('boardpopCard');
    if (!pop || !card) return;
    closeSheet();
    hideBuyPop();
    const pct = (g && g.giveup_pct) || 50;
    const paused = !!(g && g.paused);
    const over = !!(g && g.over);
    let ask = '종료하시겠습니까?';
    let meta = '종료하면 땅·건물 ' + pct + '%와 누적 이자를 정산합니다. 일시멈춤은 지금 상태로 멈추고, 다시 시작하면 정산 후 순서를 섞어 새 판을 시작합니다.';
    if (over) {
      ask = '새 판을 시작할까요?';
      meta = '땅 가진 사람은 땅·건물 ' + pct + '%를 환급받고, 턴 순서를 섞어 시작합니다.';
    } else if (paused) {
      ask = '일시멈춤 중';
      meta = '이어하면 지금 상태 그대로 계속합니다. 종료하면 정산하고, 다시 시작하면 정산 후 새 판입니다.';
    }
    card.innerHTML = '<h3>🎲 ' + esc(pageTitle) + '</h3>'
      + '<div class="buypop-ask">' + esc(ask) + '</div>'
      + '<div class="buypop-meta">' + esc(meta) + '</div>'
      + '<div class="buypop-btns"></div>';
    const box = card.querySelector('.buypop-btns');
    function addBtn(label, cls, fn) {
      const b = el('button', cls || '', label);
      b.type = 'button';
      b.onclick = function () {
        closeBoardPop();
        if (fn) fn();
      };
      box.appendChild(b);
    }
    if (over) {
      if (g.can_replay) {
        addBtn('재진행', '', function () {
          if (!window.confirm('끝난 판을 이어서 진행할까요?\n강제 파산한 친구를 다시 참가시킵니다.\n이미 준 시상(은총조각·아이템·땅 매각)은 회수하지 않아요.')) return;
          act('replay');
        });
      }
      if (g.can_recruit) {
        addBtn('모집시작', '', function () {
          if (!window.confirm('30분 동안 참가 신청을 받을까요?\n참가비는 스냅샷 시총 1% 게임냥입니다.\n본인 참가 취소는 안 되고, 모집 취소하면 전액 환불됩니다.')) return;
          act('recruit');
        });
      }
      if (g.can_force_start && !g.can_start) {
        addBtn('강제시작', '', function () {
          var n = (g.applicants || []).length;
          if (!window.confirm('모집 시간이 남아 있어도 지금 강제 시작할까요?\n참가 ' + n + '명으로 바로 판이 시작됩니다.')) return;
          act('force_start');
        });
      }
      if (g.can_start) {
        addBtn('판 시작', '', function () {
          if (!window.confirm('참가 ' + ((g.applicants || []).length) + '명으로 판을 시작할까요?')) return;
          act('new');
        });
      }
      if (g.can_extend_recruit) {
        addBtn('모집연장 +10분', '', function () {
          if (!window.confirm('모집 시간을 10분 연장할까요?\n시간이 끝났으면 다시 10분 동안 참가할 수 있어요.')) return;
          act('recruit_extend');
        });
      }
      if (g.can_remind_recruit) {
        addBtn('모집알람 다시보내기', '', function () {
          if (!window.confirm('게임방에 모집 알람을 다시 보낼까요?')) return;
          act('recruit_remind');
        });
      }
      if (g.can_cancel_recruit) {
        addBtn('모집취소', 'ghost', function () {
          var n = (g.applicants || []).length;
          var msg = '모집을 취소할까요?';
          if (n > 0) msg += '\n참가 ' + n + '명의 참가비 게임냥을 전액 환불합니다.';
          if (!window.confirm(msg)) return;
          act('recruit_cancel');
        });
      }
    } else {
      addBtn('종료 · 정산', '', function () { act('end'); });
      if (paused) addBtn('이어하기', '', function () { act('resume'); });
      else addBtn('일시멈춤', '', function () { act('pause'); });
    }
    addBtn('닫기', 'ghost', function () {});
    pop.hidden = false;
  }

  const boardpopBg = document.getElementById('boardpopBg');
  if (boardpopBg) boardpopBg.onclick = closeBoardPop;

  function addBoardBtn(btns, g) {
    if (!g || !g.can_reset) return;
    const neu = el('button', 'ghost', g.paused ? '판 관리' : '새 판');
    neu.onclick = function () { openBoardPop(g); };
    btns.appendChild(neu);
  }

  function showBuyPop(g) {
    const pop = document.getElementById('buypop');
    const card = document.getElementById('buypopCard');
    const pend = g && g.pending;
    if (!pend || !pend.mine) {
      hideBuyPop();
      return;
    }
    closeSheet();
    const kind = pend.kind || 'buy';
    const cards = pend.cards || [];
    const cities = pend.cities || [];
    const key = kind + '|' + String(pend.tile) + '|' + String(pend.price_fmt || '') + '|' + String(pend.next || '') + '|' + String(pend.amt_fmt || '') + '|' + String(pend.cash_fmt || '') + '|' + (pend.assets || []).map(function (a) { return String(a.id) + ':' + String(a.net_fmt || ''); }).join(',') + '|' + cards.map(function (c) { return c.id; }).join(',') + '|' + cities.map(function (c) { return String(c.id); }).join(',');
    pop.hidden = false;
    if (pop.dataset.key === key && (card.querySelector('.buypop-btns') || card.querySelector('.buypop-cards') || card.querySelector('.buypop-cities'))) {
      const sec = document.getElementById('buypopSec');
      if (sec) sec.textContent = fmtTurnLeft(parseInt(g.buy_left, 10) || 0);
      return;
    }
    pop.dataset.key = key;
    if (kind === 'distress') {
      card.className = 'buypop-card distress';
      var why = pend.why_label || '빚';
      var toPay = pend.to_name ? (' → ' + esc(pend.to_name)) : '';
      var assets = pend.assets || [];
      var canPay = !!pend.can_pay;
      var canSettle = !!pend.can_settle;
      var meta = canPay
        ? (why + '를 낼 수 있어요.')
        : (assets.length
          ? (esc(fmtTurnLimit(parseInt(g.buy_limit, 10) || 30)) + ' 안에 땅·이자를 팔아 채워 주세요. 채워지면 자동으로 내고, 시간이 끝나면 파산입니다.')
          : ('팔 땅이 없어요. 남은 머니로 내거나 파산하세요. 시간이 끝나면 파산입니다.'));
      card.innerHTML = '<h3>🆘 ' + esc(why) + ' 부족</h3>'
        + '<div class="buypop-ask">내야 할 금액 <b style="color:#e8eef6">' + esc(pend.amt_fmt || '') + '</b>' + toPay
        + '<br>보유 ' + esc(pend.cash_fmt || '0') + (canPay ? '' : (' · 부족 <b style="color:#fbbf24">' + esc(pend.short_fmt || '') + '</b>')) + '</div>'
        + '<div class="buypop-sec" id="buypopSec">' + esc(fmtTurnLeft(parseInt(g.buy_left, 10) || 0)) + '</div>'
        + '<div class="buypop-meta">' + meta + '</div>'
        + '<div class="buypop-sells"></div>'
        + '<div class="buypop-btns"></div>';
      var sells = card.querySelector('.buypop-sells');
      assets.forEach(function (a) {
        var netTxt = a.net_fmt || '0';
        var lab;
        var sub = '';
        if (a.type === 'interest') {
          lab = '누적 이자 수령';
        } else {
          lab = flagName(a) + (a.build_label && a.build_label !== '빈땅' ? (' · ' + a.build_label) : '') + ' 매각';
          if (a.gross_fmt) sub = '시세 ' + a.gross_fmt;
        }
        var b = el('button', '', '<span class="nm">' + lab + (sub ? ('<i>' + esc(sub) + '</i>') : '') + '</span><span class="net">수령 ' + esc(netTxt) + '</span>');
        b.onclick = function () {
          if (a.type === 'interest') act('interest', { pay: 'money' });
          else act('sell', { tile: a.id });
        };
        sells.appendChild(b);
      });
      var brokeBtns = card.querySelector('.buypop-btns');
      addGiveupBtn(brokeBtns, g);
      if (canPay || canSettle) {
        var pay = el('button', '', canPay ? (why + ' 내기') : ('잔액으로 ' + why + ' 내기'));
        pay.onclick = function () {
          if (canSettle && !confirm('남은 머니로 ' + why + '를 내고 파산할까요? 땅은 빈땅이 되고 이번 판은 참가할 수 없어요.')) return;
          act('paydebt');
        };
        brokeBtns.appendChild(pay);
      }
      var broke = el('button', 'ghost', '파산하겠습니다');
      broke.onclick = function () {
        if (confirm('파산하면 땅은 빈땅이 되고 이번 판은 참가할 수 없어요. 파산할까요?')) {
          act('broke');
        }
      };
      brokeBtns.appendChild(broke);
      return;
    }
    if (kind === 'chance') {
      card.className = 'buypop-card chance';
      let html = '<h3>🎴 찬스</h3>'
        + '<div class="buypop-ask">뒤집힌 카드 중 하나를 고르세요</div>'
        + '<div class="buypop-sec" id="buypopSec">' + esc(fmtTurnLeft(parseInt(g.buy_left, 10) || 0)) + '</div>'
        + '<div class="buypop-meta">' + esc(fmtTurnLimit(parseInt(g.buy_limit, 10) || parseInt(g.chance_limit, 10) || 30)) + ' 안에 고르지 않으면 나온 카드 중 무작위로 적용됩니다 · 내용은 고른 뒤에 공개됩니다</div>'
        + '<div class="buypop-cards"></div>';
      card.innerHTML = html;
      const box = card.querySelector('.buypop-cards');
      cards.forEach(function (c, i) {
        const b = el('button', '', '<span class="t">' + esc(c.title || ('찬스' + (i + 1))) + '</span>');
        b.type = 'button';
        b.onclick = function () { act('chance', { card: c.id }); };
        box.appendChild(b);
      });
      return;
    }
    if (kind === 'travel') {
      card.className = 'buypop-card travel';
      let html = '<h3>✈️ 세계여행</h3>'
        + '<div class="buypop-ask">원하는 도시로 이동하세요</div>'
        + '<div class="buypop-sec" id="buypopSec">' + esc(fmtTurnLeft(parseInt(g.buy_left, 10) || 0)) + '</div>'
        + '<div class="buypop-meta">' + esc(fmtTurnLimit(parseInt(g.buy_limit, 10) || parseInt(g.travel_limit, 10) || 40)) + ' 안에 고르지 않으면 무작위 도시로 갑니다 · 도착하면 그 칸 효과(구매·통행료·건설)가 적용됩니다</div>'
        + '<div class="buypop-cities"></div>';
      card.innerHTML = html;
      const box = card.querySelector('.buypop-cities');
      cities.forEach(function (c) {
        var sub = c.mine ? '내 땅' : (c.owner ? esc(c.owner) : '빈땅');
        const b = el('button', c.mine ? 'mine' : '', '<span class="t">' + flagName(c) + '</span><span class="s">' + sub + (c.price_fmt ? (' · ' + esc(c.price_fmt)) : '') + '</span>');
        b.type = 'button';
        b.onclick = function () {
          if (!window.confirm((c.name || '이 도시') + '로 이동할까요?\n도착하면 그 칸 효과가 바로 적용됩니다.')) return;
          act('travel', { tile: c.id });
        };
        box.appendChild(b);
      });
      return;
    }
    if (kind === 'forcebuy') {
      card.className = 'buypop-card';
      card.innerHTML = '<h3>⚡ 도시 강제구매권</h3>'
        + '<div class="buypop-ask">이제 주사위로 도착한 남의 도시에서 씁니다</div>'
        + '<div class="buypop-meta">도착한 남의 도시에서 강제구매 · 인수 제안 · 통행료</div>'
        + '<div class="buypop-btns"></div>';
      const keep = el('button', '', '권으로 보관');
      keep.onclick = function () { act('skip'); };
      card.querySelector('.buypop-btns').appendChild(keep);
      return;
    }
    if (kind === 'takeover' || kind === 'takeover_offer') {
      var offer = kind === 'takeover_offer';
      var canForceTake = !offer && !!pend.can_force_takeover;
      var canForce = !offer && !!pend.can_force_buy;
      var canOffer = offer || (!canForceTake && pend.can_takeover !== false);
      card.className = 'buypop-card';
      var pct = String(pend.pct || 150);
      var refuseTxt = '';
      if (pend.refuse_max) {
        refuseTxt = ' · 거절 ' + String(pend.refuse_n || 0) + '/' + String(pend.refuse_max);
        if (canForceTake || pend.force_takeover) refuseTxt += ' · 강제매수';
        else if (pend.refuse_left != null) refuseTxt += ' · 남은 ' + String(pend.refuse_left) + '회';
      }
      var ask = offer
        ? (esc(pend.from || '상대') + ' 님이 이 땅을 인수하고 싶어 합니다')
        : (canForceTake ? '시세 ' + pct + '% 웃돈 강제매수 · 통행료' : (canForce ? '강제구매권 · 인수 제안 · 통행료' : '이 땅을 인수 제안할까요?'));
      var meta = offer
        ? ('받으면 <b style="color:#e8eef6">' + esc(pend.price_fmt || '') + '</b> · 건물 유지 · 거절하면 상대가 통행료 ' + esc(pend.rent_fmt || '') + ' 를 냅니다' + refuseTxt)
        : ('주인 ' + esc(pend.owner || '') + ' · 시세 ' + pct + '% <b style="color:#e8eef6">' + esc(pend.price_fmt || '') + '</b>'
          + (pend.build_label && pend.build_label !== '빈땅' ? (' · ' + esc(pend.build_label)) : '')
          + refuseTxt
          + (canForceTake
            ? '<br>거절 1회를 넘겨 강제매수할 수 있어요 · 주인이 거절할 수 없어요 · 아니면 통행료 ' + esc(pend.rent_fmt || '')
            : (canForce
              ? '<br>강제구매권은 주인이 거절할 수 없어요 · 인수 제안은 주인이 1회 거절할 수 있어요 · 아니면 통행료 ' + esc(pend.rent_fmt || '')
              : ('<br>주인이 받아야 땅이 넘어갑니다 · 거절 1회 · 아니면 통행료 ' + esc(pend.rent_fmt || ''))))
          + (pend.grace ? ' (후발 유예)' : ''));
      card.innerHTML = ''
        + '<h3>' + flagName(pend) + '</h3>'
        + '<div class="buypop-ask">' + ask + '</div>'
        + '<div class="buypop-meta">' + meta + '</div>'
        + '<div class="buypop-sec" id="buypopSec">' + esc(fmtTurnLeft(parseInt(g.buy_left, 10) || 0)) + '</div>'
        + '<div class="buypop-meta">' + esc(fmtTurnLimit(parseInt(g.buy_limit, 10) || 20)) + (offer ? ' 안에 답하지 않으면 거절·통행료' : ' 안에 고르지 않으면 통행료') + '</div>'
        + '<div class="buypop-btns"></div>';
      const tbtns = card.querySelector('.buypop-btns');
      if (canForceTake) {
        const forceTake = el('button', '', '강제매수');
        forceTake.onclick = function () {
          if (!window.confirm((pend.name || '이 도시') + '를 강제매수할까요?\n시세 ' + pct + '% 웃돈 ' + (pend.price_fmt || '') + '을 주인 ' + (pend.owner || '') + '에게 주고 바로 가져옵니다.\n거절 한도 · 주인은 거절할 수 없어요.')) return;
          act('buy');
        };
        tbtns.appendChild(forceTake);
      }
      if (canForce) {
        const force = el('button', 'ghost', '강제구매권');
        force.onclick = function () {
          if (!window.confirm((pend.name || '이 도시') + '를 강제구매권으로 살까요?\n주인 ' + (pend.owner || '') + '에게 ' + (pend.price_fmt || '') + '를 주고 바로 가져옵니다.\n주인은 거절할 수 없어요.')) return;
          act('forcebuy');
        };
        tbtns.appendChild(force);
      }
      if (canOffer) {
        const yes = el('button', (canForce || canForceTake) ? 'ghost' : '', offer ? '받기' : '인수 제안');
        yes.onclick = function () { act('buy'); };
        tbtns.appendChild(yes);
      }
      const no = el('button', 'ghost', offer ? '거절' : '통행료');
      no.onclick = function () { act('skip'); };
      tbtns.appendChild(no);
      return;
    }
    card.className = 'buypop-card';
    const isBuild = kind === 'build';
    const canBuy = pend.can_buy !== false;
    card.innerHTML = ''
      + '<h3>' + flagName(pend) + '</h3>'
      + '<div class="buypop-ask">' + (isBuild ? (esc(pend.next || '건물') + '을 지을까요?') : '구매하시겠습니까?') + '</div>'
      + '<div class="buypop-meta">' + (isBuild
        ? ('지금 ' + esc(pend.build_label || '빈땅') + ' · 건설비 <b style="color:#e8eef6">' + esc(pend.price_fmt || '') + '</b>'
          + (pend.rent_fmt ? ('<br>지으면 통행료 ' + esc(pend.rent_fmt)) : ''))
        : ('땅값 <b style="color:#e8eef6">' + esc(pend.price_fmt || '') + '</b>'
          + (pend.rent_fmt ? ('<br>통행료 ' + esc(pend.rent_fmt)) : ''))) + '</div>'
      + '<div class="buypop-sec" id="buypopSec">' + esc(fmtTurnLeft(parseInt(g.buy_left, 10) || 0)) + '</div>'
      + '<div class="buypop-meta">' + esc(fmtTurnLimit(parseInt(g.buy_limit, 10) || (isBuild ? 30 : 20))) + ' 안에 고르지 않으면 자동 패스</div>'
      + '<div class="buypop-btns"></div>';
    const btns = card.querySelector('.buypop-btns');
    if (isBuild) {
      const build = el('button', '', esc(pend.next || '짓기'));
      build.onclick = function () { act('build', { tile: pend.tile }); };
      const skip = el('button', 'ghost', '패스');
      skip.onclick = function () { act('skip'); };
      btns.appendChild(build);
      btns.appendChild(skip);
    } else if (!canBuy) {
      const skip = el('button', 'ghost', '패스');
      skip.onclick = function () { act('skip'); };
      btns.appendChild(skip);
    } else {
      const buy = el('button', '', '구매');
      buy.onclick = function () { act('buy'); };
      const skip = el('button', 'ghost', '패스');
      skip.onclick = function () { act('skip'); };
      btns.appendChild(buy);
      btns.appendChild(skip);
    }
  }

  function render(g) {
    try {
    lastGame = g || {};
    document.title = (g && g.title) ? String(g.title) : pageTitle;
    const snapSub = document.getElementById('snapSub');
    if (snapSub && g && g.start_fmt) {
      snapSub.innerHTML = '새 판마다 전원 스냅샷의 <b>' + esc(g.grant_pct_fmt || '10%') + '</b>씩 <b>부루마블 머니</b> 동일 지급 (이번 판 ' + esc(g.grant_fmt || '') + ') · <b>땅값·월급도 같은 10% 기준</b> · 월급 ' + esc(g.salary_fmt || '') + '<br>늦게 합류해도 같은 시작금 · 한 바퀴 통행료 <b>' + esc(String(g.grace_pct || 50)) + '%</b> · 남의 땅 도착 시 시세 <b>' + esc(String(g.takeover_pct || 150)) + '%</b> 인수 제안 · 거절 1회 · 2회째 강제매수 · 랜드마크는 매수 불가<br>한 바퀴마다 땅값 이자 <b>' + esc(g.interest_fmt || '0.5% · 펜션 +0.1% · 호텔 +0.4% · 랜드마크 +1.0%') + '</b> 가 쌓이고, 수령하면 <b>부루마블 머니 또는 게임냥</b>으로 받아요 · 사고팔기·통행료·세금은 <b>부루마블 머니</b> · 못 내면 땅 매도, 안 되면 파산<br>주사위 미돌림 1회 머니 <b>10%</b> · 2회 <b>30%</b> · 3회·4회 <b>50%</b> · 5회 강제 파산<br>민호 모집시작 후 30분 참가 · 참가비 스냅샷 시총 <b>1%</b> 게임냥 차감 · 본인 취소 불가 · 민호 모집취소 시 전액 환불';
    }
    const board = document.getElementById('board');
    const wrap = document.getElementById('boardWrap');
    const clockEl = document.getElementById('turnclock');
    if (clockEl && wrap) wrap.appendChild(clockEl);
    board.innerHTML = '';
    const cells = (g && g.cells) ? g.cells : [];
    cells.forEach(function (c) {
      const mine = g.me && c.owner === g.me;
      const vacant = c.type === 'land' && !c.owner;
      const box = el('div', 'cell' + (openTileId != null && String(openTileId) === String(c.id) ? ' open' : '') + (mine ? ' mine' : '') + (vacant ? ' free' : ''));
      box.style.gridColumn = (c.col + 1);
      box.style.gridRow = (c.row + 1);
      var lastG = <?php echo (int)$gridN; ?>;
      var side = 'mid';
      var isCorner = (c.col === 0 || c.col === lastG - 1) && (c.row === 0 || c.row === lastG - 1);
      if (isCorner) side = 'corner';
      else if (c.row === 0) side = 'top';
      else if (c.row === lastG - 1) side = 'bot';
      else if (c.col === 0) side = 'left';
      else if (c.col === lastG - 1) side = 'right';
      box.classList.add('side-' + side);
      box.setAttribute('data-tid', String(c.id));
      box.onclick = function () { openTile(c.id); };
      const bar = el('div', 'bar');
      bar.style.background = c.owner_color || c.color;
      box.appendChild(bar);
      box.appendChild(el('div', 'nm', flagName(c) + (c.build >= 3 ? ' 🏰' : (c.build >= 2 ? ' 🏨' : (c.build >= 1 ? ' 🏠' : '')))));
      if (vacant) box.appendChild(el('div', 'vacant', '빈땅'));
      if (c.price_fmt) box.appendChild(el('div', 'pr', c.price_fmt));
      else if (c.rent_fmt) box.appendChild(el('div', 'pr', c.rent_fmt));
      const toks = el('div', 'tokens');
      (c.tokens || []).forEach(function (t) {
        const onTurn = !!(g.wait_for && t.name === g.wait_for);
        const d = el('span', 'tok' + (onTurn ? ' tok-turn' : ''));
        d.style.background = t.color;
        d.title = t.name;
        d.setAttribute('data-nick', t.name || '');
        if (t.me && !onTurn) {
          d.style.width = '11px';
          d.style.height = '11px';
          d.style.outline = '1px solid #fff';
        }
        toks.appendChild(d);
      });
      box.appendChild(toks);
      board.appendChild(box);
    });
    const mid = el('div', 'center');
    const body = el('div', 'center-body');
    const d = (g && g.dice) ? g.dice : [0, 0];
    const diceCol = el('div', 'center-dice');
    diceCol.innerHTML = '<div class="dice"><b>' + (d[0] || '-') + '</b><b>' + (d[1] || '-') + '</b></div>';

    const mineCol = el('div', 'center-mine');
    const meP = (g.players || []).find(function (p) { return p.me; });
    const here = meP ? cells.find(function (c) { return String(c.id) === String(meP.pos); }) : null;
    const myLands = cells.filter(function (c) { return g.me && c.owner === g.me && c.type === 'land'; });
    mineCol.appendChild(el('div', 'mine-h', '내 땅 ' + myLands.length));
    const cashLine = el('div', 'mine-cash');
    cashLine.innerHTML = '<span>' + esc(g.money_name || '부루마블 머니') + '</span><b>' + esc(g.my_money_fmt || (meP && meP.cash_fmt) || '0') + '</b>';
    mineCol.appendChild(cashLine);
    if (here) {
      mineCol.appendChild(el('div', 'mine-here', '지금 위치 · ' + flagName(here)));
    }
    if (!myLands.length) {
      mineCol.appendChild(el('div', 'mine-empty', '아직 산 땅이 없어요'));
    } else {
      myLands.forEach(function (c) {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'mine-row';
        const sw = document.createElement('i');
        sw.style.background = c.owner_color || c.color || '#6ee7b7';
        row.appendChild(sw);
        row.appendChild(el('b', '', flagName(c)));
        row.appendChild(el('span', '', esc((c.build_label || '빈땅') + (c.sell_net_fmt ? (' · 매각 ' + c.sell_net_fmt) : (c.price_fmt ? (' · ' + c.price_fmt) : '')))));
        row.onclick = function (ev) {
          ev.stopPropagation();
          openTile(c.id);
        };
        mineCol.appendChild(row);
      });
    }
    const intBox = el('div', 'mine-int');
    intBox.appendChild(el('div', 'lab', '누적 이자'));
    intBox.appendChild(el('div', 'amt', esc(g.my_interest_fmt || '0')));
    const distressMe = !!(g.pending && g.pending.kind === 'distress' && g.pending.mine);
    if (distressMe) {
      const claimMini = el('button', g.can_claim_interest ? '' : 'ghost', '머니로 수령');
      claimMini.disabled = !g.can_claim_interest;
      claimMini.onclick = function (ev) {
        ev.stopPropagation();
        if (g.can_claim_interest) act('interest', { pay: 'money' });
      };
      intBox.appendChild(claimMini);
    } else {
      const claimRow = el('div', 'mine-int-btns');
      const moneyBtn = el('button', g.can_claim_interest ? '' : 'ghost', '머니로');
      moneyBtn.disabled = !g.can_claim_interest;
      moneyBtn.onclick = function (ev) {
        ev.stopPropagation();
        if (!g.can_claim_interest) return;
        if (!window.confirm('누적 이자 ' + (g.my_interest_fmt || '') + '를 부루마블 머니로 받을까요?')) return;
        act('interest', { pay: 'money' });
      };
      const ptBtn = el('button', g.can_claim_interest ? '' : 'ghost', '게임냥으로');
      ptBtn.disabled = !g.can_claim_interest;
      ptBtn.onclick = function (ev) {
        ev.stopPropagation();
        if (!g.can_claim_interest) return;
        if (!window.confirm('누적 이자 ' + (g.my_interest_fmt || '') + '를 게임냥으로 받을까요?\n부루마블 머니로는 안 들어와요.')) return;
        act('interest', { pay: 'pt' });
      };
      claimRow.appendChild(moneyBtn);
      claimRow.appendChild(ptBtn);
      intBox.appendChild(claimRow);
    }
    const foundLine = el('div', 'mine-found', '받은 은총조각 <b>' + esc(String(g.my_found_shard || 0)) + '</b> · 은총 <b>' + esc(String(g.my_found_eun || 0)) + '</b>');
    intBox.appendChild(foundLine);
    mineCol.appendChild(intBox);
    var mineDock = document.getElementById('mineDock');
    body.appendChild(diceCol);
    body.appendChild(mineCol);
    mid.appendChild(body);
    if (clockEl) mid.appendChild(clockEl);
    board.appendChild(mid);
    if (mineDock) {
      mineDock.hidden = true;
      mineDock.innerHTML = '';
    }

    const freelist = document.getElementById('freelist');
    if (freelist) {
      const freeLands = cells.filter(function (c) { return c.type === 'land' && !c.owner; });
      if (!g.has || !freeLands.length) {
        freelist.hidden = true;
        freelist.innerHTML = '';
      } else {
        freelist.hidden = false;
        freelist.innerHTML = '';
        const head = document.createElement('button');
        head.type = 'button';
        head.className = 'free-h';
        head.innerHTML = '주인 없는 땅 ' + freeLands.length + '<span class="chev"></span>';
        head.onclick = function () {
          freeListOpen = !freeListOpen;
          try { localStorage.setItem('burumable_freelist', freeListOpen ? '1' : '0'); } catch (e) {}
          applyFreeList();
        };
        freelist.appendChild(head);
        freeLands.forEach(function (c) {
          const row = document.createElement('button');
          row.type = 'button';
          row.className = 'free-row';
          const sw = document.createElement('i');
          sw.style.background = c.color || '#fbbf24';
          row.appendChild(sw);
          row.appendChild(el('b', '', flagName(c)));
          row.appendChild(el('span', '', esc(c.price_fmt || '')));
          row.onclick = function () { openTile(c.id); };
          freelist.appendChild(row);
        });
        applyFreeList();
      }
    }

    const plist = document.getElementById('plist');
    plist.innerHTML = '';
    if (g.recruiting) {
      (g.applicants || []).forEach(function (p) {
        const row = el('div', 'prow' + (p.me ? ' on' : ''));
        row.setAttribute('data-nick', p.name || '');
        row.appendChild(el('span', 'who', (p.me ? '나 · ' : '') + (p.no ? (p.no + '. ') : '') + p.name));
        row.appendChild(el('span', 'cash', '참가'));
        plist.appendChild(row);
      });
    } else {
    (g.players || []).forEach(function (p) {
      const row = el('div', 'prow' + (p.turn ? ' on' : '') + (p.out ? ' out' : '') + (g.can_reset ? ' admin' : ''));
      row.setAttribute('data-nick', p.name || '');
      const dot = el('span', 'dot');
      dot.style.background = p.color;
      row.appendChild(dot);
      row.appendChild(el('span', 'who', (p.me ? '나 · ' : '') + (p.order ? (p.order + '. ') : '') + p.name
        + (p.broke ? ' 💀파산' : (p.out ? ' 포기' : ''))
        + (p.grace_go ? ' · 후발' : '')
        + (p.rent_cut ? (' · 할인' + String(p.rent_cut)) : '')
        + (p.force_buy ? (' · 강제' + String(p.force_buy)) : '')
        + (' · 땅' + String(p.lands || 0))));
      const miss = parseInt(p.skip_roll, 10) || 0;
      row.appendChild(el('span', 'miss' + (miss > 0 ? ' on' : ''), '미굴림 ' + miss));
      row.appendChild(el('span', 'cash', p.cash_fmt));
      if (g.can_reset) {
        row.onclick = function (ev) {
          ev.stopPropagation();
          openAdminPop(g, p);
        };
      }
      plist.appendChild(row);
    });
    }

    const log = document.getElementById('log');
    const lines = g.log || [];
    log.innerHTML = lines.map(function (x) {
      return '<div>' + esc(x) + '</div>';
    }).join('');
    var logCount = document.getElementById('logCount');
    if (logCount) logCount.textContent = lines.length ? ('· ' + lines.length) : '';
    applyLogFold();

    const rentbox = document.getElementById('rentbox');
    const rentList = document.getElementById('rentList');
    const rentCount = document.getElementById('rentCount');
    const rents = g.my_rent_from || [];
    if (rentbox && rentList) {
      if (!g.has || g.recruiting) {
        rentbox.hidden = true;
        rentList.innerHTML = '';
      } else {
        rentbox.hidden = false;
        if (rentCount) rentCount.textContent = rents.length ? ('· ' + rents.length + '명') : '';
        if (!rents.length) {
          rentList.innerHTML = '<div class="rent-empty">아직 없어요</div>';
        } else {
          rentList.innerHTML = rents.map(function (r) {
            var sub = (r.times ? (r.times + '회') : '') + (r.last ? (' · ' + r.last) : '');
            return '<div class="rent-row"><b>' + esc(r.name || '') + '</b>'
              + (sub ? ('<i class="sub">' + esc(sub) + '</i>') : '')
              + '<span>' + esc(r.total_fmt || '') + '</span></div>';
          }).join('');
        }
      }
    }

    fillRecruit(g);
    armRecruit(g);
    const pend = document.getElementById('pend');
    const over = document.getElementById('over');
    const btns = document.getElementById('btns');
    btns.innerHTML = '';
    over.hidden = true;
    pend.hidden = true;

    if (g.recruiting) {
      hideBuyPop();
      if (g.can_reset) addBoardBtn(btns, g);
      return;
    }

    if (!g.has) {
      hideBuyPop();
      addRecruitStartBtn(btns, g);
      return;
    }
    if (g.over) {
      hideBuyPop();
      if (g.winner || (g.ranks && g.ranks.length)) {
        over.hidden = false;
        over.innerHTML = prizeHtml(g);
      }
      if (g.can_reset && g.can_replay) {
        const replay = el('button', '', '재진행');
        replay.onclick = function () {
          if (!window.confirm('끝난 판을 이어서 진행할까요?\n강제 파산한 친구를 다시 참가시킵니다.\n이미 준 시상(은총조각·아이템·땅 매각)은 회수하지 않아요.')) return;
          act('replay');
        };
        btns.appendChild(replay);
      }
      addRecruitStartBtn(btns, g);
      addBoardBtn(btns, g);
      return;
    }
    if (g.paused) {
      hideBuyPop();
      pend.hidden = false;
      pend.innerHTML = '⏸ 일시멈춤 · 지금 상태로 멈춰 있어요';
      addBoardBtn(btns, g);
      addGiveupBtn(btns, g);
      return;
    }
    if (g.pending) {
      pend.hidden = false;
      if ((g.pending.kind || '') === 'chance') {
        pend.innerHTML = esc(g.wait_for || '') + ' → 🎴 찬스 카드 고르기';
      } else if ((g.pending.kind || '') === 'travel') {
        pend.innerHTML = esc(g.wait_for || '') + ' → ✈️ 세계여행 · 도시 고르기';
      } else if ((g.pending.kind || '') === 'forcebuy') {
        pend.innerHTML = esc(g.wait_for || '') + ' → ⚡ 도시 강제구매';
      } else if ((g.pending.kind || '') === 'distress') {
        pend.innerHTML = esc(g.wait_for || '') + ' → 🆘 ' + esc(g.pending.why_label || '빚') + ' ' + esc(g.pending.amt_fmt || '') + ' 부족 · 땅 매도 후 내기';
      } else if ((g.pending.kind || '') === 'build') {
        pend.innerHTML = esc(g.wait_for || '') + ' → 내 땅 ' + flagName(g.pending) + ' · ' + esc(g.pending.next || '건설') + ' ' + esc(g.pending.price_fmt || '');
      } else if ((g.pending.kind || '') === 'takeover') {
        pend.innerHTML = esc(g.wait_for || '') + ' → ' + flagName(g.pending) + (g.pending.can_force_takeover ? ' 강제매수 ' : ' 인수 ') + esc(g.pending.price_fmt || '') + ' · 통행료 ' + esc(g.pending.rent_fmt || '');
      } else if ((g.pending.kind || '') === 'takeover_offer') {
        pend.innerHTML = esc(g.wait_for || '') + ' → ' + flagName(g.pending) + ' 인수 받기 ' + esc(g.pending.price_fmt || '');
      } else {
        pend.innerHTML = esc(g.wait_for || '') + ' → ' + flagName(g.pending) + ' ' + esc(g.pending.price_fmt) + ' · 통행료 ' + esc(g.pending.rent_fmt);
      }
      showBuyPop(g);
      if (g.pending.mine) {
        if ((g.pending.kind || '') === 'chance') {
          (g.pending.cards || []).forEach(function (c, i) {
            const b = el('button', '', esc(c.title || ('찬스' + (i + 1))));
            b.onclick = function () { act('chance', { card: c.id }); };
            btns.appendChild(b);
          });
        } else if ((g.pending.kind || '') === 'travel') {
          btns.appendChild(el('div', '', '원하는 도시를 골라 이동하세요'));
        } else if ((g.pending.kind || '') === 'forcebuy') {
          const keep = el('button', '', '권으로 보관');
          keep.onclick = function () { act('skip'); };
          btns.appendChild(keep);
        } else if ((g.pending.kind || '') === 'distress') {
          if (g.pending.can_pay || g.pending.can_settle) {
            const pay = el('button', '', g.pending.can_pay
              ? ((g.pending.why_label || '빚') + ' 내기')
              : ('잔액으로 ' + (g.pending.why_label || '빚') + ' 내기'));
            pay.onclick = function () {
              if (g.pending.can_settle && !confirm('남은 머니로 내고 파산할까요? 땅은 빈땅이 되고 이번 판은 참가할 수 없어요.')) return;
              act('paydebt');
            };
            btns.appendChild(pay);
          }
          const broke = el('button', 'ghost', '파산하겠습니다');
          broke.onclick = function () {
            if (confirm('파산하면 땅은 빈땅이 되고 이번 판은 참가할 수 없어요. 파산할까요?')) {
              act('broke');
            }
          };
          btns.appendChild(broke);
        } else if ((g.pending.kind || '') === 'build') {
          const build = el('button', '', g.pending.next || '짓기');
          build.onclick = function () { act('build', { tile: g.pending.tile }); };
          const skip = el('button', 'ghost', '패스');
          skip.onclick = function () { act('skip'); };
          btns.appendChild(build);
          btns.appendChild(skip);
        } else if ((g.pending.kind || '') === 'takeover' || (g.pending.kind || '') === 'takeover_offer') {
          var offerPend = (g.pending.kind || '') === 'takeover_offer';
          var forcePend = !offerPend && !!g.pending.can_force_takeover;
          if (!offerPend && g.pending.can_force_buy) {
            const force = el('button', 'ghost', '강제구매권');
            force.onclick = function () { act('forcebuy'); };
            btns.appendChild(force);
          }
          if (forcePend) {
            const forceTake = el('button', '', '강제매수');
            forceTake.onclick = function () { act('buy'); };
            btns.appendChild(forceTake);
          } else {
            const yes = el('button', '', offerPend ? '받기' : '인수 제안');
            yes.onclick = function () { act('buy'); };
            btns.appendChild(yes);
          }
          const no = el('button', 'ghost', offerPend ? '거절' : '통행료');
          no.onclick = function () { act('skip'); };
          btns.appendChild(no);
        } else if (g.pending.can_buy === false) {
          const skip = el('button', 'ghost', '패스');
          skip.onclick = function () { act('skip'); };
          btns.appendChild(skip);
        } else {
          const buy = el('button', '', '사기');
          buy.onclick = function () { act('buy'); };
          const skip = el('button', 'ghost', '패스');
          skip.onclick = function () { act('skip'); };
          btns.appendChild(buy);
          btns.appendChild(skip);
        }
      } else {
        var waitKind = g.pending.kind || '';
        var waitTxt = waitKind === 'chance' ? ' 님이 찬스 카드를 고르는 중…'
          : (waitKind === 'travel' ? ' 님이 세계여행 도시를 고르는 중…'
          : (waitKind === 'forcebuy' ? ' 님이 강제구매 도시를 고르는 중…'
          : (waitKind === 'distress' ? ' 님이 땅을 팔아 빚을 갚는 중…'
            : (waitKind === 'build' ? ' 님이 건물을 고르는 중…'
            : (waitKind === 'takeover_offer' ? ' 님이 인수를 고르는 중…'
              : (waitKind === 'takeover' ? ' 님이 인수를 고르는 중…' : ' 님이 땅을 고르는 중…'))))));
        btns.appendChild(el('div', '', (g.wait_for || '') + waitTxt));
      }
      addBoardBtn(btns, g);
      addGiveupBtn(btns, g);
      return;
    }
    hideBuyPop();
    if (g.my_turn) {
      (g.builds || []).forEach(function (b) {
        const bb = el('button', b.afford ? '' : 'ghost', b.name + ' ' + b.next + ' ' + b.cost_fmt + (b.toll_fmt ? (' → ' + b.toll_fmt) : ''));
        bb.disabled = !b.afford;
        bb.onclick = function () { act('build', { tile: b.id }); };
        btns.appendChild(bb);
      });
    }
    addBoardBtn(btns, g);
    addGiveupBtn(btns, g);
    } finally {
      if (openTileId != null) {
        fillSheet(openTileId);
      }
      armClock(g);
      armPoll(g);
      syncRollDock(g);
      requestAnimationFrame(function () { moveTurnCursor(g); });
    }
  }

  function findTurnTok(board, who) {
    if (!board || !who) return null;
    const toks = board.querySelectorAll('.tok');
    for (let i = 0; i < toks.length; i++) {
      if (toks[i].getAttribute('data-nick') === who) return toks[i];
    }
    return null;
  }

  function paintCursor(curEl, wrap, target, color, who, snap, whoChanged, side) {
    if (!curEl || !wrap) return false;
    if (!target) {
      curEl.hidden = true;
      return false;
    }
    const wr = wrap.getBoundingClientRect();
    const tr = target.getBoundingClientRect();
    const x = side === 'list'
      ? (tr.left - wr.left + 4)
      : (tr.left - wr.left + tr.width / 2);
    const y = tr.top - wr.top + tr.height / 2;
    const lab = curEl.querySelector('.turn-cursor-lab');
    const pin = curEl.querySelector('.turn-cursor-pin');
    const inner = curEl.querySelector('.turn-cursor-inner');
    if (lab) {
      lab.textContent = who;
      lab.style.background = color;
      lab.style.color = '#0b0d12';
    }
    if (pin) {
      if (side === 'list') pin.style.borderLeftColor = color;
      else pin.style.borderTopColor = color;
    }
    const first = !!snap || curEl.hidden || !lastCursorWho;
    const pos = 'translate(' + Math.round(x) + 'px,' + Math.round(y) + 'px)';
    curEl.style.transition = first
      ? 'none'
      : 'transform 0.6s cubic-bezier(0.22, 1, 0.36, 1)';
    if (first) {
      curEl.style.transform = pos;
      curEl.hidden = false;
    } else {
      curEl.hidden = false;
      curEl.style.transform = pos;
    }
    if (inner && whoChanged && !first) {
      inner.style.animation = 'none';
      void inner.offsetWidth;
      inner.style.animation = (side === 'list' ? 'cursorpop-list' : 'cursorpop') + ' 0.45s ease-out';
    }
    return true;
  }

  function moveTurnCursor(g, snap) {
    const who = (g && g.has && !g.over) ? String(g.wait_for || '') : '';
    const curP = (g.players || []).find(function (p) { return p.turn; });
    const color = (curP && curP.color) || '#6ee7b7';
    const whoChanged = lastCursorWho !== who;
    const boardCur = document.getElementById('turnCursor');
    const boardWrap = document.getElementById('boardWrap');
    const board = document.getElementById('board');
    const tok = (board && who) ? findTurnTok(board, who) : null;
    if (!who) {
      if (boardCur) boardCur.hidden = true;
      const listCur0 = document.getElementById('listCursor');
      if (listCur0) listCur0.hidden = true;
      lastCursorWho = '';
      return;
    }
    paintCursor(boardCur, boardWrap, tok, color, who, snap, whoChanged, 'board');

    const listCur = document.getElementById('listCursor');
    const listWrap = document.getElementById('plistWrap');
    const plist = document.getElementById('plist');
    let row = null;
    if (plist && who) {
      const rows = plist.querySelectorAll('.prow');
      for (let i = 0; i < rows.length; i++) {
        if (rows[i].getAttribute('data-nick') === who) {
          row = rows[i];
          break;
        }
      }
    }
    if (row && plist && whoChanged && plist.scrollHeight > plist.clientHeight) {
      const top = row.offsetTop - plist.clientHeight / 2 + row.offsetHeight / 2;
      plist.scrollTop = Math.max(0, top);
    }
    let listTarget = row;
    if (row && plist) {
      const box = plist.getBoundingClientRect();
      const rr = row.getBoundingClientRect();
      if (rr.bottom <= box.top || rr.top >= box.bottom) {
        listTarget = null;
      }
    }
    paintCursor(listCur, listWrap, listTarget, color, who, snap, whoChanged, 'list');
    lastCursorWho = who;
  }

  let pollTimer = null;
  let pollMs = 0;
  function armPoll(g) {
    var ms = (g && g.recruiting) ? 1000 : 2500;
    var need = !!(g && ((g.has && !g.over) || g.recruiting));
    if (!need) {
      if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
      pollMs = 0;
      return;
    }
    if (pollTimer && pollMs === ms) return;
    if (pollTimer) clearInterval(pollTimer);
    pollMs = ms;
    pollTimer = setInterval(function () {
      post('status').then(function (res) {
        if (!res || !res.ok) return;
        var ng = res.game || {};
        if (ng.recruiting && lastGame && lastGame.recruiting) {
          lastGame = ng;
          fillRecruit(ng);
          armRecruit(ng);
          return;
        }
        render(ng);
      }).catch(function () {});
    }, ms);
  }

  function act(action, extra) {
    post(action, extra).then(function (res) {
      if (!res || !res.ok) {
        var dockBtn = document.getElementById('rollDockBtn');
        if (dockBtn) dockBtn.disabled = false;
        var skipBtn = document.getElementById('skipRollDockBtn');
        if (skipBtn) skipBtn.disabled = false;
        alert((res && res.data) ? res.data : '처리에 실패했어요.');
        return;
      }
      render(res.game || {});
      if (res.data) alert(res.data);
    }).catch(function () {
      var dockBtn = document.getElementById('rollDockBtn');
      if (dockBtn) dockBtn.disabled = false;
      var skipBtn = document.getElementById('skipRollDockBtn');
      if (skipBtn) skipBtn.disabled = false;
      alert('네트워크 오류');
    });
  }

  window.addEventListener('resize', function () {
    if (lastGame) moveTurnCursor(lastGame, true);
  });
  const plistEl = document.getElementById('plist');
  if (plistEl) {
    plistEl.addEventListener('scroll', function () {
      if (lastGame) moveTurnCursor(lastGame, true);
    });
  }

  if (boot.game) {
    render(boot.game);
  }
  post('status').then(function (res) {
    if (res && res.ok && res.game) {
      render(res.game);
    } else if (!boot.game) {
      render({ has: false, cells: [] });
    }
  }).catch(function () {
    if (!boot.game) {
      render({ has: false, cells: [] });
    }
  });
})();
</script>
<?php wallet_nav_fab_render(['code' => $code]); ?>
</body>
</html>
