<?php
/**
 * 가방 · 민호 전용 관리
 * URL: /page/wallet_admin.php?code=XXXX
 * - 광물 비정상 회수
 * - 채굴 수량 초기화
 */
require __DIR__ . '/_wallet_preauth.php';

if (!function_exists('db_select')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
}
if (!function_exists('getTwoCharNick')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}

$pre = $GLOBALS['wallet_preauth'] ?? null;
$code = is_array($pre) ? trim((string)($pre['code'] ?? '')) : '';
$row = is_array($pre) ? ($pre['row'] ?? null) : null;
$nick = '';
if (is_array($row) && !empty($row['name'])) {
    $nick = trim((string)$row['name']);
    if (function_exists('getTwoCharNick')) {
        $parsed = getTwoCharNick($nick);
        if ($parsed !== '') {
            $nick = $parsed;
        }
    }
}

$isAdmin = ($nick === '민호');
$walletQ = $code !== '' ? ('?code=' . rawurlencode($code)) : '';

// wallet_web 헬퍼만 로드 (HTML 출력 방지)
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/wallet_web.php';

$ore_stats = null;
$pending_stats = ['nicks' => 0, 'total_fmt' => '0'];
$bossWait = null;
$bagIpLogs = [];
if ($isAdmin) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/mining_ore_admin.inc.php';
    if (function_exists('mining_ore_admin_scan')) {
        $ore_stats = mining_ore_admin_scan()['stats'] ?? null;
    }
    if (function_exists('wallet_mining_pending_stats_payload')) {
        $pending_stats = wallet_mining_pending_stats_payload();
    }
    $bossInc = $_SERVER['DOCUMENT_ROOT'] . '/api/game/boss_raid.inc.php';
    if (is_file($bossInc)) {
        require_once $bossInc;
    }
    if (function_exists('boss_raid_활성_상태')) {
        $st = boss_raid_활성_상태();
        if (($st['phase'] ?? '') === 'waiting') {
            $nextKey = function_exists('boss_raid_다음종류키') ? boss_raid_다음종류키() : '';
            $def = ($nextKey !== '' && function_exists('boss_raid_종류')) ? boss_raid_종류($nextKey) : [];
            $waitIn = (int)($st['next_spawn_in'] ?? 0);
            $waitTxt = '';
            if (!empty($st['need_admin'])) {
                $waitTxt = '출현 대기 · `.보스출현` 대신 여기서 시작';
            } elseif ($waitIn > 0 && function_exists('boss_raid_초시분초')) {
                $waitTxt = '다음 출현까지 ' . boss_raid_초시분초($waitIn);
            } else {
                $waitTxt = '곧 출현 가능';
            }
            $bossWait = [
                'emoji' => (string)($def['emoji'] ?? '🐉'),
                'name' => (string)($def['name'] ?? '보스'),
                'hint' => $waitTxt,
            ];
        }
    }
    if (function_exists('wallet_bag_ip_목록')) {
        $bagIpLogs = wallet_bag_ip_목록(100);
    }
}

$apiUrl = '/api/game/wallet_web.php' . $walletQ;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>가방 · 관리</title>
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --wa-ink: #e8eef6;
  --wa-muted: #8a9bb0;
  --wa-line: rgba(255,255,255,0.12);
  --wa-accent: #f0b429;
  --wa-ok: #5eead4;
  --wa-danger: #f87171;
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #0f141c 0%, #1a2230 50%, #121820 100%);
  color: var(--wa-ink);
  font-family: 'MyFont', 'Apple SD Gothic Neo', sans-serif;
  font-size: 16px;
}
.wa-wrap { max-width: 560px; margin: 0 auto; padding: 16px 14px 80px; }
.wa-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
.wa-brand { margin: 0; font-size: 26px; letter-spacing: -0.03em; color: var(--wa-accent); }
.wa-sub { margin: 6px 0 0; color: var(--wa-muted); font-size: 13px; }
.wa-back {
  display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 10px;
  border: 1px solid var(--wa-line); background: rgba(255,255,255,0.04);
  color: var(--wa-ink); text-decoration: none; font-size: 13px; flex-shrink: 0;
}
.wa-card {
  margin-bottom: 14px; padding: 16px;
  border-radius: 16px; border: 1px solid var(--wa-line);
  background: rgba(0,0,0,0.28);
}
.wa-card h2 { margin: 0 0 8px; font-size: 18px; }
.wa-hint { margin: 0 0 14px; color: var(--wa-muted); font-size: 13px; line-height: 1.45; }
.wa-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.wa-stat {
  text-align: center; padding: 12px 8px; border-radius: 12px;
  background: rgba(255,255,255,0.04); border: 1px solid var(--wa-line);
}
.wa-stat strong { display: block; font-size: 22px; color: var(--wa-accent); margin-bottom: 4px; }
.wa-stat span { font-size: 12px; color: var(--wa-muted); }
.wa-ip-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wa-ip-table th, .wa-ip-table td {
  text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--wa-line);
  vertical-align: top; word-break: break-all;
}
.wa-ip-table th { color: var(--wa-muted); font-weight: 700; font-size: 12px; }
.wa-ip-table td.nick { color: var(--wa-ok); font-weight: 700; white-space: nowrap; width: 72px; }
.wa-ip-table td.time { color: var(--wa-muted); white-space: nowrap; font-size: 12px; width: 118px; }
.wa-ip-scroll { max-height: 360px; overflow: auto; -webkit-overflow-scrolling: touch; }
  display: block; width: 100%; text-align: center; padding: 14px 12px;
  border-radius: 12px; font-size: 15px; font-weight: 600; text-decoration: none;
  border: none; cursor: pointer;
}
.wa-link {
  background: linear-gradient(135deg, rgba(94,234,212,0.22), rgba(94,234,212,0.08));
  color: var(--wa-ok); border: 1px solid rgba(94,234,212,0.35);
}
.wa-btn {
  background: linear-gradient(135deg, rgba(248,113,113,0.28), rgba(248,113,113,0.1));
  color: #fecaca; border: 1px solid rgba(248,113,113,0.4);
}
.wa-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.wa-deny {
  padding: 28px 18px; text-align: center; border-radius: 16px;
  border: 1px solid var(--wa-line); background: rgba(0,0,0,0.28);
}
.wa-deny p { margin: 0 0 14px; color: var(--wa-muted); }
.wa-toast {
  position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%) translateY(20px);
  max-width: min(92vw, 420px); padding: 12px 16px; border-radius: 12px;
  background: rgba(15,20,28,0.95); border: 1px solid var(--wa-line);
  color: var(--wa-ink); font-size: 14px; opacity: 0; pointer-events: none;
  transition: opacity .2s, transform .2s; z-index: 50;
}
.wa-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>
<div class="wa-wrap">
  <div class="wa-top">
    <div>
      <h1 class="wa-brand">⚙️ 관리</h1>
      <p class="wa-sub">민호 전용 · 광물·채굴 관리</p>
    </div>
    <a class="wa-back" href="/page/wallet.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">← 가방</a>
  </div>

  <?php if (!$isAdmin) { ?>
  <div class="wa-deny">
    <p>이 페이지는 민호만 이용할 수 있어요.</p>
    <a class="wa-link" href="/page/wallet.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">가방으로 돌아가기</a>
  </div>
  <?php } else {
    $pending_nicks = (int)($pending_stats['nicks'] ?? 0);
    $pending_total_fmt = (string)($pending_stats['total_fmt'] ?? '0');
  ?>

  <div class="wa-card">
    <h2>⛏️ 광물 비정상 회수</h2>
    <p class="wa-hint">스케줄 창 키 기준 1시간 허용량 초과분만 회수합니다. 이미 수령한 건은 채굴량 → 본방냥 순으로 차감 후 되돌립니다.</p>
    <div class="wa-stats">
      <div class="wa-stat">
        <strong><?php echo (int)($ore_stats['abnormal_total'] ?? 0); ?></strong>
        <span>대기 비정상</span>
      </div>
      <div class="wa-stat">
        <strong><?php echo (int)($ore_stats['claimed_abnormal_total'] ?? 0); ?></strong>
        <span>수령 비정상</span>
      </div>
    </div>
    <a class="wa-link" href="/page/mining_ore_revoke.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">회수 페이지 열기 →</a>
  </div>

  <div class="wa-card">
    <h2>⌨️ 생타 3일권 소급</h2>
    <p class="wa-hint">최근 14일 생타 200 × 3일 연속인데 일방신청권·연장권이 안 나간 친구를 찾아 일괄 지급합니다.</p>
    <a class="wa-link" href="/page/raw_tasu_streak_catchup.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">일괄 지급 페이지 열기 →</a>
  </div>

  <div class="wa-card">
    <h2>🥄 채굴 수량 초기화</h2>
    <p class="wa-hint">전 회원의 미수령 채굴량(채굴냥)을 0으로 만듭니다. 장비·광물·본방냥은 건드리지 않습니다.</p>
    <div class="wa-stats">
      <div class="wa-stat">
        <strong id="wPendingNicks"><?php echo $pending_nicks; ?></strong>
        <span>보유 인원</span>
      </div>
      <div class="wa-stat">
        <strong id="wPendingTotal"><?php echo htmlspecialchars($pending_total_fmt, ENT_QUOTES, 'UTF-8'); ?></strong>
        <span>총 채굴냥</span>
      </div>
    </div>
    <button type="button" class="wa-btn" id="btnMiningPendingReset">채굴 수량 전부 0으로 만들기</button>
  </div>

  <?php if (is_array($bossWait)) { ?>
  <div class="wa-card">
    <h2>🐉 대기 보스 시작</h2>
    <p class="wa-hint"><?php
      echo htmlspecialchars(
        trim((string)($bossWait['emoji'] ?? '') . ' ' . (string)($bossWait['name'] ?? '보스'))
        . ' · ' . (string)($bossWait['hint'] ?? '대기 중'),
        ENT_QUOTES,
        'UTF-8'
      );
    ?></p>
    <button type="button" class="wa-link" id="btnBossStart" style="cursor:pointer">지금 시작</button>
  </div>
  <?php } else { ?>
  <div class="wa-card">
    <h2>🐉 대기 보스 시작</h2>
    <p class="wa-hint">지금은 대기 중인 보스가 없어요. (전투 중이거나 이미 출현한 상태)</p>
    <a class="wa-link" href="/page/boss.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">보스 화면 열기 →</a>
  </div>
  <?php } ?>

  <div class="wa-card">
    <h2>📡 가방 접속 IP</h2>
    <p class="wa-hint">가방에 들어온 닉네임과 접속 IP를 따로 모아 둡니다. 같은 닉·IP는 10분에 1번만 기록됩니다.</p>
    <?php if (empty($bagIpLogs)) { ?>
    <p class="wa-hint" style="margin:0">아직 기록이 없어요.</p>
    <?php } else { ?>
    <div class="wa-ip-scroll">
      <table class="wa-ip-table">
        <thead>
          <tr>
            <th>닉네임</th>
            <th>IP</th>
            <th>시각</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bagIpLogs as $log) {
            $t = (string)($log['created_at'] ?? '');
            $tFmt = $t !== '' ? date('m-d H:i', strtotime($t)) : '';
          ?>
          <tr>
            <td class="nick"><?php echo htmlspecialchars((string)($log['nick'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)($log['ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="time"><?php echo htmlspecialchars($tFmt, ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>

  <?php } ?>
</div>
<div class="wa-toast" id="toast" role="status"></div>

<?php if ($isAdmin) { ?>
<script>
(function() {
  var CODE = <?php echo json_encode($code, JSON_UNESCAPED_UNICODE); ?>;
  var API = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;
  var toastEl = document.getElementById('toast');
  var toastTimer = null;
  function showToast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg || '';
    toastEl.classList.add('show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { toastEl.classList.remove('show'); }, 2800);
  }
  function applyPendingStats(p) {
    if (!p) return;
    var nEl = document.getElementById('wPendingNicks');
    var tEl = document.getElementById('wPendingTotal');
    if (nEl) nEl.textContent = String(parseInt(p.nicks, 10) || 0);
    if (tEl) tEl.textContent = p.total_fmt || '0';
  }
  var btn = document.getElementById('btnMiningPendingReset');
  if (btn) {
  btn.addEventListener('click', function() {
    var nicks = (document.getElementById('wPendingNicks') || {}).textContent || '0';
    var total = (document.getElementById('wPendingTotal') || {}).textContent || '0';
    var msg = '전 회원 미수령 채굴량을 0으로 초기화합니다.\n\n'
      + '보유 인원: ' + nicks + '명\n'
      + '총 채굴냥: ' + total + '냥\n\n'
      + '장비·광물·본방냥은 유지됩니다. 계속할까요?';
    if (!window.confirm(msg)) return;
    if (!window.confirm('정말 전부 0으로 만들까요? 되돌릴 수 없습니다.')) return;
    btn.disabled = true;
    var body = new URLSearchParams();
    body.set('action', 'mining_pending_reset_all');
    body.set('wallet_code', CODE);
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        btn.disabled = false;
        if (!j || !j.ok) {
          showToast((j && (j.data || j.msg)) || '오류');
          return;
        }
        if (j.pending) applyPendingStats(j.pending);
        else applyPendingStats({ nicks: 0, total_fmt: '0' });
        showToast((j.data || '채굴량을 초기화했어요.').replace(/\n/g, ' · '));
      })
      .catch(function() {
        btn.disabled = false;
        showToast('통신 오류');
      });
  });
  }

  var bossBtn = document.getElementById('btnBossStart');
  if (bossBtn) {
    bossBtn.addEventListener('click', function() {
      if (!window.confirm('대기 중인 보스를 지금 시작할까요?\n대기 시간이 남아 있어도 바로 출현합니다.')) return;
      bossBtn.disabled = true;
      var body = new URLSearchParams();
      body.set('action', 'reset');
      body.set('code', CODE);
      fetch('/page/boss.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        credentials: 'same-origin'
      })
        .then(function(r) { return r.json(); })
        .then(function(j) {
          bossBtn.disabled = false;
          if (!j || !j.ok) {
            showToast((j && (j.data || j.msg)) || '시작 실패');
            return;
          }
          showToast((j.data || '보스를 시작했어요.').replace(/\n/g, ' · '));
          setTimeout(function() { location.reload(); }, 700);
        })
        .catch(function() {
          bossBtn.disabled = false;
          showToast('통신 오류');
        });
    });
  }
})();
</script>
<?php } ?>
</body>
</html>
