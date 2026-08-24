<?php
/**
 * 이전 보스 내역 — /page/boss_history.php?code=XXXX
 * 기여 순위 · 광역/내 공격 이력 (보스 첫 화면에서 분리)
 */
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
if (!defined('WALLET_CODE_COOKIE_NAME')) {
    define('WALLET_CODE_COOKIE_NAME', 'wallet_code');
    define('WALLET_CODE_COOKIE_TTL', 7 * 86400);
}

function boss_hist_json(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function boss_hist_ensure_db(): void {
    if (function_exists('db_select')) {
        return;
    }
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($root !== '' && is_file($root . '/lib/_function.php')) {
        include_once $root . '/lib/_function.php';
    }
}

$action = trim((string)($_REQUEST['action'] ?? ''));
if (empty($GLOBALS['wallet_preauth']['code'])) {
    require_once __DIR__ . '/wallet_web.php';
}

$boot = [
    'code' => '',
    'need_code' => true,
    'nick' => '',
    'q' => '',
];

$code = '';
if (!empty($GLOBALS['wallet_preauth']['code'])) {
    $code = (string)$GLOBALS['wallet_preauth']['code'];
} elseif (function_exists('wallet_코드_해석')) {
    $code = wallet_코드_해석();
}

if ($code !== '') {
    boss_hist_ensure_db();
    $row = null;
    if (!empty($GLOBALS['wallet_preauth']['row'])) {
        $row = $GLOBALS['wallet_preauth']['row'];
    } elseif (function_exists('wallet_game_auth_row')) {
        $row = wallet_game_auth_row($code);
    } else {
        $esc = addslashes($code);
        $row = @db_select("SELECT idx, name, CAST(point AS CHAR) AS point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (empty($row['name'])) {
            $row = null;
        }
    }
    if ($row) {
        $nick = trim((string)($row['name'] ?? ''));
        if ($nick !== '' && function_exists('getTwoCharNick')) {
            $parsed = getTwoCharNick($nick);
            if ($parsed !== '') {
                $nick = $parsed;
            }
        } elseif ($nick !== '' && function_exists('wallet_nick_from_row')) {
            $nick = wallet_nick_from_row($row);
        }
        if (function_exists('wallet_코드_쿠키_저장')) {
            wallet_코드_쿠키_저장($code);
        } else {
            @setcookie(WALLET_CODE_COOKIE_NAME, $code, time() + WALLET_CODE_COOKIE_TTL, '/', '', false, true);
        }
        $boot['code'] = $code;
        $boot['need_code'] = false;
        $boot['nick'] = $nick;
        $boot['q'] = function_exists('wallet_code_query') ? wallet_code_query($code) : ('?code=' . rawurlencode($code));
    }
}

if ($action !== '') {
    require_once __DIR__ . '/boss_raid.inc.php';
    if (!empty($boot['need_code']) || $boot['nick'] === '') {
        boss_hist_json(['ok' => false, 'data' => '로그인이 필요해요.']);
    }
    $nick = $boot['nick'];
    if (!boss_raid_접근가능($nick)) {
        boss_hist_json(['ok' => false, 'data' => '접근할 수 없어요.']);
    }
    if ($action === 'history') {
        $prev = boss_raid_직전결과_페이로드($nick);
        boss_hist_json(['ok' => true, 'history' => $prev]);
    }
    boss_hist_json(['ok' => false, 'data' => '알 수 없는 요청이에요.']);
}

$allowed = !$boot['need_code'] && trim((string)$boot['nick']) !== '';
$walletQ = $boot['q'] !== '' ? $boot['q'] : '';
$codeJs = json_encode($boot['code'], JSON_UNESCAPED_UNICODE);
$nickJs = json_encode($boot['nick'], JSON_UNESCAPED_UNICODE);
$bossHref = '/page/boss.php' . $walletQ;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>이전 보스 내역</title>
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
.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 18px 16px;
  margin-bottom: 12px;
}
.meta { color: var(--muted); font-size: 12px; line-height: 1.5; }
.meta b { color: var(--ink); font-weight: 700; }
.label { font-size: 15px; font-weight: 700; margin: 0 0 8px; }
.empty { color: var(--muted); font-size: 13px; padding: 8px 0; }
.rank, .log { list-style: none; margin: 0; padding: 0; }
.rank li, .log li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid rgba(180, 210, 255, 0.08);
  font-size: 13px;
}
.rank li:last-child, .log li:last-child { border-bottom: 0; }
.rank .rank-right { text-align: right; }
.rank .rank-right b { display: block; }
.rank .rank-right small { color: var(--muted); font-size: 11px; }
.rank .rank-right small .pay { color: var(--ok); }
.log .who { color: var(--accent); }
.log .bad { color: var(--danger); }
.log .ok { color: var(--ok); }
.log .time, .log .hit-meta { color: var(--muted); white-space: nowrap; }
.log .dmg { color: var(--warn); }
.log-tabs { display: flex; gap: 6px; margin: 10px 0 8px; }
.log-tab {
  flex: 1;
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.04);
  color: var(--muted);
  border-radius: 999px;
  padding: 8px 6px;
  font-size: 12px;
}
.log-tab.active { color: var(--ink); border-color: rgba(110, 182, 255, 0.45); background: rgba(110, 182, 255, 0.12); }
.msg { margin: 8px 0 0; font-size: 13px; color: var(--muted); min-height: 1.2em; }
.msg.err { color: var(--danger); }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <a class="back" href="<?php echo htmlspecialchars($bossHref, ENT_QUOTES, 'UTF-8'); ?>">← 보스</a>
  </div>
  <h1>이전 보스 내역</h1>

  <?php if ($boot['need_code']): ?>
    <div class="card">가방 코드로 로그인해 주세요.</div>
  <?php elseif (!$allowed): ?>
    <div class="card">접근할 수 없어요.</div>
  <?php else: ?>
    <div class="card">
      <p class="label" id="histLabel">불러오는 중…</p>
      <p class="meta" id="histMeta">직전 보스 기여·광역 기록입니다. 다음 보스 출현 시 삭제됩니다.</p>
      <p class="msg" id="msg"></p>
    </div>

    <div class="card" id="rankCard" hidden>
      <div class="meta" style="margin-bottom:8px"><b>기여 순위</b> · 참여자 <span id="partCnt">0</span>명</div>
      <ul class="rank" id="rankList"></ul>
    </div>

    <div class="card" id="logCard" hidden>
      <div class="meta" style="margin-bottom:0"><b id="counterTitle">광역 이력</b></div>
      <div class="log-tabs" id="counterTabs" role="tablist">
        <button type="button" class="log-tab active" data-tab="all" role="tab" aria-selected="true">전체 광역</button>
        <button type="button" class="log-tab" data-tab="mine" role="tab" aria-selected="false">내 피해</button>
        <button type="button" class="log-tab" data-tab="hits" role="tab" aria-selected="false">내 공격</button>
      </div>
      <ul class="log" id="counterList"></ul>
    </div>
  <?php endif; ?>
</div>

<?php if ($allowed): ?>
<script>
(function () {
  var CODE = <?php echo $codeJs; ?>;
  var MY_NICK = <?php echo $nickJs; ?>;
  var hist = null;
  var counterTab = 'all';

  function $(id) { return document.getElementById(id); }
  function setMsg(text, kind) {
    var el = $('msg');
    if (!el) return;
    el.className = 'msg' + (kind ? (' ' + kind) : '');
    el.textContent = text || '';
  }
  function shortTime(t) {
    if (!t) return '';
    var s = String(t);
    return s.length >= 16 ? s.slice(5, 16) : s;
  }
  function counterTypeLabel(t) {
    if (t === 'protect' || t === 'aoe_protect') return '보호';
    if (t === 'point') return '냥';
    if (t === 'item') return '아이템';
    if (t === 'durability') return '내구';
    if (t === 'swap' || t === 'bonbang') return '본방스왑';
    if (t === 'aoe') return '광역';
    if (t === 'dodge') return '회피';
    return t || '피해';
  }
  function attackTypeLabel(t) {
    if (t === 'mining') return '채굴';
    if (t === 'protect') return '보호';
    if (t === 'finish') return '필살';
    return '무기';
  }

  function paintRank() {
    var list = $('rankList');
    var rank = (hist && hist.rank) || [];
    $('partCnt').textContent = String((hist && hist.participants) || 0);
    if (!rank.length) {
      list.innerHTML = '<li><span>공격 기록 없음</span><span>—</span></li>';
      return;
    }
    var html = '';
    for (var i = 0; i < rank.length; i++) {
      var r = rank[i];
      var dmg = Number(r.damage || 0).toLocaleString();
      var pct = (typeof r.share_pct !== 'undefined') ? Number(r.share_pct) : null;
      var pay = Number(r.reward || 0);
      var payLabel = r.reward_label || '';
      var sub = [];
      if (pct !== null && !isNaN(pct)) sub.push(pct.toFixed(1) + '%');
      if (payLabel && pay > 0) {
        sub.push('<span class="pay">' + payLabel + ' +' + Number(pay).toLocaleString() + '냥</span>');
      } else if (pay <= 0) {
        sub.push('지급 없음');
      }
      html += '<li><span>' + (i + 1) + ') ' + (r.nick || '')
        + '<small class="who-protect">보호 ' + Number(r.protect || 0).toLocaleString() + '</small></span>'
        + '<span class="rank-right"><b>' + dmg + '</b>'
        + (sub.length ? ('<small>' + sub.join(' · ') + '</small>') : '')
        + '</span></li>';
    }
    list.innerHTML = html;
  }

  function paintLog() {
    var list = $('counterList');
    var title = $('counterTitle');
    if (counterTab === 'hits') {
      title.textContent = '내 공격';
      var hits = (hist && hist.my_hits) || [];
      if (!hits.length) {
        list.innerHTML = '<li><span>내 공격 기록 없음</span><span>—</span></li>';
        return;
      }
      var hlog = '';
      for (var h = 0; h < hits.length; h++) {
        var hit = hits[h];
        var dmg = Number(hit.damage || 0);
        var kind = attackTypeLabel(hit.attack_type);
        var critMark = hit.crit ? ' · 💥치명' : '';
        var isProtect = hit.attack_type === 'protect';
        var isFinish = hit.attack_type === 'finish';
        var power = isProtect
          ? ('전원 +' + (hit.mining_level || 0))
          : (isFinish
            ? ('필살 ×' + (hit.mining_level || 0))
            : ((hit.attack_type === 'mining')
              ? ('채굴 Lv' + (hit.mining_level || 0))
              : ('무기 +' + (hit.enhance || 0))));
        var right = isProtect
          ? ('+' + Number(hit.mining_level || 0).toLocaleString())
          : ('-' + dmg.toLocaleString());
        hlog += '<li><span><span class="ok">' + kind + '</span> · ' + power + critMark +
          '</span><span class="hit-meta"><span class="dmg">' + right +
          '</span> · ' + shortTime(hit.time) + '</span></li>';
      }
      list.innerHTML = hlog;
      return;
    }

    var counters = (hist && hist.counters) || [];
    var rows = counters;
    if (counterTab === 'mine') {
      title.textContent = '내 피해';
      rows = counters.filter(function (row) { return (row.nick || '') === MY_NICK; });
    } else {
      title.textContent = '광역 이력';
    }
    if (!rows.length) {
      list.innerHTML = '<li><span>기록 없음</span><span>—</span></li>';
      return;
    }
    var clog = '';
    for (var c = 0; c < rows.length; c++) {
      var row = rows[c];
      if (counterTab === 'mine') {
        clog += '<li><span><span class="bad">' + counterTypeLabel(row.type) + '</span> · ' + (row.detail || '') +
          '</span><span class="time">' + shortTime(row.time) + '</span></li>';
      } else {
        clog += '<li><span><span class="who">' + (row.nick || '') + '</span> ← <span class="bad">' +
          counterTypeLabel(row.type) + '</span> · ' + (row.detail || '') +
          '</span><span class="time">' + shortTime(row.time) + '</span></li>';
      }
    }
    list.innerHTML = clog;
  }

  var tabs = $('counterTabs');
  if (tabs) {
    tabs.addEventListener('click', function (e) {
      var btn = e.target.closest('.log-tab');
      if (!btn) return;
      counterTab = btn.getAttribute('data-tab') || 'all';
      Array.prototype.forEach.call(tabs.querySelectorAll('.log-tab'), function (el) {
        var on = el === btn;
        el.classList.toggle('active', on);
        el.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      paintLog();
    });
  }

  var body = new URLSearchParams();
  body.set('code', CODE);
  body.set('action', 'history');
  fetch(location.pathname + location.search, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    body: body.toString(),
    credentials: 'same-origin'
  }).then(function (r) { return r.json(); }).then(function (j) {
    if (!(j && j.ok && j.history)) {
      setMsg((j && j.data) ? j.data : '내역을 불러오지 못했어요.', 'err');
      $('histLabel').textContent = '내역 없음';
      return;
    }
    hist = j.history;
    if (!hist.prev_result) {
      $('histLabel').textContent = '이전 보스 없음';
      setMsg('아직 종료된 보스 기록이 없어요.');
      return;
    }
    $('histLabel').textContent = hist.prev_result_label || '직전 보스';
    setMsg('');
    $('rankCard').hidden = false;
    $('logCard').hidden = false;
    paintRank();
    paintLog();
  }).catch(function () {
    setMsg('네트워크 오류 · 다시 시도해 주세요.', 'err');
    $('histLabel').textContent = '불러오기 실패';
  });
})();
</script>
<?php endif; ?>
<?php
if (!empty($code)) {
  require_once __DIR__ . '/wallet_nav_fab.inc.php';
  wallet_nav_fab_render(['code' => $code, 'newpoint_fmt' => ' ']);
}
?>
</body>
</html>
