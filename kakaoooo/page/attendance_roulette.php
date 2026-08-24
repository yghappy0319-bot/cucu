<?php
/**
 * 출석룰렛 — 생타 구간별 티켓 · 자정 초기화
 * URL: /page/attendance_roulette.php?code=XXXX
 */
require __DIR__ . '/_wallet_preauth.php';
require_once __DIR__ . '/../api/game/wallet_subpage.inc.php';
require_once __DIR__ . '/../api/game/attendance_roulette.inc.php';

if (!function_exists('홀짝_미션_생타100_달성') && is_file(__DIR__ . '/../api/game/odd_even_guards.php')) {
    require_once __DIR__ . '/../api/game/odd_even_guards.php';
}

$boot = wallet_subpage_boot();
$code = (string)($boot['code'] ?? '');
$nick = (string)($boot['nick'] ?? '');
$q = (string)($boot['q'] ?? '');
$need_code = !empty($boot['need_code']);
$off_work = !empty($boot['off_work']);

$req_action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
if (in_array($req_action, ['status', 'spin'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    if ($need_code || $nick === '') {
        echo json_encode(['ok' => false, 'data' => '로그인이 필요해요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($req_action === 'status') {
        echo json_encode([
            'ok' => true,
            'state' => attendance_roulette_state($nick),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = attendance_roulette_spin($nick);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = !$need_code && $nick !== '';
$state = $allowed ? attendance_roulette_state($nick) : null;
$rewards = attendance_roulette_rewards();
$seg_colors = ['#94a3b8', '#f59e0b', '#34d399', '#60a5fa', '#a78bfa', '#f472b6', '#fb7185'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a1025">
    <title>출석룰렛 · 가방</title>
    <style>
        :root {
            --bg0: #1a1025;
            --bg1: #2a1840;
            --card: rgba(255, 248, 240, 0.06);
            --line: rgba(251, 191, 36, 0.28);
            --gold: #fbbf24;
            --cream: #fff7ed;
            --muted: rgba(255, 247, 237, 0.62);
            --ok: #34d399;
            --bad: #fb7185;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100dvh;
            color: var(--cream);
            font-family: 'Apple SD Gothic Neo', 'Noto Sans KR', system-ui, sans-serif;
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(251, 191, 36, 0.22), transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 100%, rgba(167, 139, 250, 0.18), transparent 50%),
                linear-gradient(165deg, var(--bg0), var(--bg1) 55%, #120c1c);
        }
        .top {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            padding-top: max(12px, env(safe-area-inset-top));
            background: rgba(26, 16, 37, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .top a {
            color: var(--gold);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .top h1 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .wrap {
            max-width: 440px;
            margin: 0 auto;
            padding: 20px 16px 40px;
        }
        .hero {
            text-align: center;
            margin-bottom: 18px;
        }
        .hero .eyebrow {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--gold);
            opacity: 0.9;
            margin-bottom: 6px;
        }
        .hero p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.45;
        }
        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .stats.bag3 {
            grid-template-columns: 1fr 1fr 1fr;
            margin-bottom: 12px;
        }
        .stats.bag3 .stat b {
            font-size: 15px;
        }
        .stat {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
        }
        .stat span {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .stat b {
            font-size: 18px;
            font-weight: 800;
            color: var(--cream);
        }
        .stat b.ok { color: var(--ok); }
        .stat b.bad { color: var(--bad); }
        .tiers {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--card);
            border: 1px solid var(--line);
            font-size: 12px;
            color: var(--muted);
            line-height: 1.55;
        }
        .tiers strong { color: var(--gold); font-weight: 700; }
        .wheel-stage {
            position: relative;
            width: min(300px, 78vw);
            margin: 0 auto 22px;
            aspect-ratio: 1;
        }
        .pointer {
            position: absolute;
            top: -6px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-top: 22px solid var(--gold);
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.45));
        }
        .wheel-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 6px solid rgba(251, 191, 36, 0.85);
            box-shadow:
                0 0 0 4px rgba(26, 16, 37, 0.9),
                0 12px 32px rgba(0, 0, 0, 0.4),
                inset 0 0 24px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            background: #1f142e;
        }
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            transition: transform 4.2s cubic-bezier(0.12, 0.75, 0.08, 1);
            will-change: transform;
        }
        .hub {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(145deg, #fde68a, #f59e0b);
            color: #1a1025;
            font-size: 12px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(0,0,0,.35);
            border: 3px solid #fff7ed;
        }
        .seg-label {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 48%;
            margin-left: -24%;
            margin-top: -0.7em;
            text-align: center;
            font-size: 15px;
            font-weight: 900;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0,0,0,.65);
            transform-origin: 50% 0.7em;
            pointer-events: none;
            line-height: 1.2;
            z-index: 1;
            letter-spacing: -0.03em;
        }
        .prizes {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin: -8px 0 18px;
        }
        .prizes span {
            font-size: 14px;
            font-weight: 700;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.14);
            color: var(--cream);
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
        }
        .btn-spin {
            appearance: none;
            border: none;
            border-radius: 16px;
            padding: 16px 18px;
            font-size: 17px;
            font-weight: 800;
            color: #1a1025;
            background: linear-gradient(135deg, #fde68a, #f59e0b 55%, #ea580c);
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.35);
            cursor: pointer;
        }
        .btn-spin:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn-spin.spinning {
            opacity: 0.7;
            pointer-events: none;
        }
        .result {
            min-height: 52px;
            text-align: center;
            padding: 14px;
            border-radius: 14px;
            background: var(--card);
            border: 1px dashed var(--line);
            font-size: 14px;
            line-height: 1.5;
            color: var(--muted);
            white-space: pre-line;
        }
        .result.win {
            color: var(--cream);
            border-style: solid;
            border-color: rgba(52, 211, 153, 0.45);
            background: rgba(52, 211, 153, 0.1);
            font-weight: 700;
        }
        .note {
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            line-height: 1.5;
        }
        .history {
            margin-top: 22px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: var(--card);
            overflow: hidden;
        }
        .history h2 {
            font-size: 13px;
            font-weight: 800;
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            color: var(--gold);
        }
        .history ul {
            list-style: none;
            max-height: 420px;
            overflow-y: auto;
        }
        .history li {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 13px;
        }
        .history li:last-child { border-bottom: none; }
        .history .label { font-weight: 700; color: var(--cream); }
        .history .meta {
            text-align: right;
            font-size: 11px;
            color: var(--muted);
            line-height: 1.35;
            flex-shrink: 0;
        }
        .history .st-granted { color: var(--ok); }
        .history .st-revoked { color: var(--bad); }
        .history .st-failed,
        .history .st-skipped { color: var(--muted); }
        .history .empty {
            padding: 18px 14px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }
        .lock {
            text-align: center;
            padding: 48px 16px;
            color: var(--muted);
        }
        .lock a { color: var(--gold); }
    </style>
</head>
<body>
    <header class="top">
        <a href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 가방</a>
        <h1>출석룰렛</h1>
        <span style="width:48px"></span>
    </header>

    <main class="wrap">
<?php if ($need_code || $off_work || !$allowed): ?>
        <div class="lock">
            <p>가방 코드로 로그인해 주세요.</p>
            <p style="margin-top:10px"><a href="/page/wallet.php">가방 열기</a></p>
        </div>
<?php else:
    $tasu = $state['tasu'] ?? [];
    $tickets = $state['tickets'] ?? [];
    $bag = $state['bag'] ?? [];
    $left = (int)($tickets['left'] ?? 0);
    $earned = (int)($tickets['earned'] ?? 0);
    $used = (int)($tickets['used'] ?? 0);
    $can = !empty($state['can_spin']);
    $resultLabel = (string)(($state['result']['label'] ?? ''));
    $next = $tickets['next'] ?? null;
    $n = max(1, count($rewards));
    $slice = 360 / $n;
    $stops = [];
    foreach ($rewards as $i => $r) {
        $c = $seg_colors[$i % count($seg_colors)];
        $stops[] = $c . ' ' . ($i * $slice) . 'deg ' . (($i + 1) * $slice) . 'deg';
    }
    $wheelBg = 'conic-gradient(from -' . ($slice / 2) . 'deg, ' . implode(', ', $stops) . ')';
?>
        <div class="hero">
            <div class="eyebrow">Daily Tickets</div>
            <p>생타 구간별 티켓 · 자정에 초기화</p>
        </div>

        <div class="stats">
            <div class="stat">
                <span>오늘 생타</span>
                <b id="stTasu"><?= (int)($tasu['raw'] ?? 0) ?></b>
            </div>
            <div class="stat">
                <span>남은 티켓</span>
                <b id="stSpin" class="<?= $left > 0 ? 'ok' : 'bad' ?>">
                    <?= $left ?> / <?= $earned ?>
                </b>
            </div>
        </div>

        <div class="stats bag3" id="bagHoldings">
            <div class="stat">
                <span>본방냥</span>
                <b id="stNp"><?= htmlspecialchars((string)($bag['newpoint_fmt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></b>
            </div>
            <div class="stat">
                <span>은총</span>
                <b id="stEun"><?= (int)($bag['eunchong'] ?? 0) ?>개</b>
            </div>
            <div class="stat">
                <span>은총조각</span>
                <b id="stShard"><?= (int)($bag['shard'] ?? 0) ?>개</b>
            </div>
        </div>

        <div class="tiers" id="tierBox">
            <strong>티켓</strong>
            100→1 · 300→2 · 500→3 · 1000→5 · 1500~3000 각 +5<br>
            4000→+15 · 5000→+15 · 6000→+20 (최대 75장 · 생타 기준)<br>
            빚탕감 <code>.주사위 2</code> 시간당 무료 1회 이후 추가사용에도 소모됩니다.<br>
<?php if (is_array($next)):
    $nextMode = (string)($next['mode'] ?? 'max');
    $nextLabel = ($nextMode === 'add')
        ? ('+' . (int)$next['tickets'] . '장')
        : ((int)$next['tickets'] . '장');
?>
            다음: 생타 <?= (int)$next['tasu'] ?> → 티켓 <?= htmlspecialchars($nextLabel, ENT_QUOTES, 'UTF-8') ?>
            (<?= max(0, (int)$next['tasu'] - (int)($tasu['raw'] ?? 0)) ?>타 더)
<?php else: ?>
            오늘 최대 구간(6000타 · 최대 75장)이에요. 사용 <?= $used ?>회
<?php endif; ?>
        </div>

        <div class="wheel-stage">
            <div class="pointer" aria-hidden="true"></div>
            <div class="wheel-ring">
                <div class="wheel" id="wheel" style="background: <?= htmlspecialchars($wheelBg, ENT_QUOTES, 'UTF-8') ?>;">
<?php foreach ($rewards as $i => $r):
    if (($r['type'] ?? '') === 'miss') {
        $short = '꽝!';
    } elseif (($r['type'] ?? '') === 'eunchong') {
        $short = '은총 1개';
    } elseif (($r['type'] ?? '') === 'newpoint') {
        $qty = (int)($r['qty'] ?? 0);
        $short = '본방 ' . number_format($qty);
    } else {
        $short = '조각 ' . (int)$r['qty'] . '개';
    }
    $ang = $i * $slice;
?>
                    <div class="seg-label" style="transform: rotate(<?= (float)$ang ?>deg) translateY(-112px);"><?= htmlspecialchars($short, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>
                </div>
            </div>
            <div class="hub">GO</div>
        </div>

        <div class="prizes">
<?php foreach ($rewards as $r): ?>
            <span><?= htmlspecialchars((string)$r['label'], ENT_QUOTES, 'UTF-8') ?></span>
<?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="button" class="btn-spin" id="btnSpin" <?= $can ? '' : 'disabled' ?>>
                <?= $can ? '룰렛 돌리기 (티켓 1장)' : ($earned > 0 ? '티켓을 모두 썼어요' : '티켓이 없어요') ?>
            </button>
            <div class="result<?= $resultLabel !== '' ? ' win' : '' ?>" id="resultBox"><?php
                if ($resultLabel !== '') {
                    echo '최근 결과: ' . htmlspecialchars($resultLabel, ENT_QUOTES, 'UTF-8');
                    if (!empty($state['grant_enabled'])) {
                        echo "\n(아이템 지급 ON)";
                    } else {
                        echo "\n(지급 보류 · 테스트)";
                    }
                } else {
                    echo '포인터가 가리키는 칸이 보상이에요.';
                }
            ?></div>
        </div>

        <p class="note">
            티켓은 매일 자정(서울)에 초기화 · 당첨 시 아이템/본방냥 지급
        </p>

        <section class="history" id="grantHistory">
            <h2>오늘 지급내역</h2>
<?php
    $grants = $state['grants'] ?? [];
    if (empty($grants)):
?>
            <div class="empty" id="grantEmpty">아직 지급 내역이 없어요.</div>
            <ul id="grantList" hidden></ul>
<?php else: ?>
            <div class="empty" id="grantEmpty" hidden></div>
            <ul id="grantList">
<?php foreach ($grants as $g):
    $st = (string)($g['status'] ?? '');
    $stLabel = $st === 'granted' ? '지급' : ($st === 'revoked' ? '회수' : ($st === 'skipped' ? '보류' : '실패'));
    if ($st === 'granted' && (string)($g['type'] ?? '') === 'miss') {
        $stLabel = '꽝';
    }
    $at = (string)($g['at'] ?? '');
    $atShort = '';
    if ($at !== '') {
        $ts = strtotime($at);
        $atShort = $ts ? date('H:i:s', $ts) : $at;
    }
?>
                <li data-idx="<?= (int)($g['idx'] ?? 0) ?>">
                    <div>
                        <div class="label"><?= htmlspecialchars((string)($g['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="meta">
                        <span class="st-<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"><?= $stLabel ?></span>
                        · <?= htmlspecialchars($atShort, ENT_QUOTES, 'UTF-8') ?>
<?php if ($st === 'revoked' && (int)($g['revoke_qty'] ?? 0) > 0): ?>
                        <br>회수 <?= (int)$g['revoke_qty'] ?>
<?php endif; ?>
                    </div>
                </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </section>
<?php endif; ?>
    </main>

<?php if ($allowed): ?>
<script>
(function () {
    var rewards = <?= json_encode($rewards, JSON_UNESCAPED_UNICODE) ?>;
    var apiBase = <?= json_encode('/page/attendance_roulette.php' . $q, JSON_UNESCAPED_UNICODE) ?>;
    var wheel = document.getElementById('wheel');
    var btn = document.getElementById('btnSpin');
    var resultBox = document.getElementById('resultBox');
    var stSpin = document.getElementById('stSpin');
    var spinning = false;
    var currentRot = 0;
    var n = rewards.length || 6;
    var slice = 360 / n;

    function api(action) {
        var url = apiBase + (apiBase.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(action);
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    function landRotation(index) {
        var centerFromZero = index * slice;
        var targetMod = (360 - centerFromZero) % 360;
        var extra = 360 * (5 + Math.floor(Math.random() * 3));
        currentRot = currentRot + extra + ((targetMod - (currentRot % 360) + 360) % 360);
        return currentRot;
    }

    function statusLabel(st, type) {
        if (st === 'granted' && type === 'miss') return '꽝';
        if (st === 'granted') return '지급';
        if (st === 'revoked') return '회수';
        if (st === 'skipped') return '보류';
        return '실패';
    }

    function timeShort(at) {
        if (!at) return '';
        var d = new Date(at.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return at;
        var hh = String(d.getHours()).padStart(2, '0');
        var mm = String(d.getMinutes()).padStart(2, '0');
        var ss = String(d.getSeconds()).padStart(2, '0');
        return hh + ':' + mm + ':' + ss;
    }

    function renderGrants(grants) {
        var list = document.getElementById('grantList');
        var empty = document.getElementById('grantEmpty');
        if (!list || !empty) return;
        grants = grants || [];
        if (!grants.length) {
            empty.hidden = false;
            empty.textContent = '아직 지급 내역이 없어요.';
            list.hidden = true;
            list.innerHTML = '';
            return;
        }
        empty.hidden = true;
        list.hidden = false;
        list.innerHTML = grants.map(function (g) {
            var st = g.status || '';
            var extra = (st === 'revoked' && g.revoke_qty) ? ('<br>회수 ' + g.revoke_qty) : '';
            return '<li data-idx="' + (g.idx || 0) + '">'
                + '<div><div class="label">' + (g.label || '') + '</div></div>'
                + '<div class="meta"><span class="st-' + st + '">' + statusLabel(st, g.type || '') + '</span> · '
                + timeShort(g.at) + extra + '</div></li>';
        }).join('');
    }

    function applyState(state, lastLabel, grantMsg) {
        if (!state || !state.tickets) return;
        var left = parseInt(state.tickets.left || 0, 10);
        var earned = parseInt(state.tickets.earned || 0, 10);
        stSpin.textContent = left + ' / ' + earned;
        stSpin.className = left > 0 ? 'ok' : 'bad';
        if (state.bag) {
            var npEl = document.getElementById('stNp');
            var eunEl = document.getElementById('stEun');
            var shardEl = document.getElementById('stShard');
            if (npEl) npEl.textContent = state.bag.newpoint_fmt || '0';
            if (eunEl) eunEl.textContent = (state.bag.eunchong || 0) + '개';
            if (shardEl) shardEl.textContent = (state.bag.shard || 0) + '개';
        }
        if (left > 0) {
            btn.disabled = false;
            btn.textContent = '룰렛 돌리기 (티켓 1장)';
        } else {
            btn.disabled = true;
            btn.textContent = earned > 0 ? '티켓을 모두 썼어요' : '티켓이 없어요';
        }
        if (lastLabel) {
            resultBox.className = 'result win';
            var extra = grantMsg ? ('\n' + grantMsg) : (state.grant_enabled ? '\n(지급 완료)' : '\n(지급 보류)');
            resultBox.textContent = '최근 결과: ' + lastLabel + '\n남은 티켓 ' + left + '장' + extra;
        }
        if (state.grants) renderGrants(state.grants);
    }

    btn.addEventListener('click', function () {
        if (spinning || btn.disabled) return;
        spinning = true;
        btn.classList.add('spinning');
        btn.textContent = '돌아가는 중…';
        resultBox.className = 'result';
        resultBox.textContent = '두근두근…';

        api('spin').then(function (j) {
            if (!j || !j.ok || !j.pick) {
                spinning = false;
                btn.classList.remove('spinning');
                resultBox.textContent = (j && j.data) ? j.data : '스핀 실패';
                if (j && j.state) applyState(j.state, null, null);
                else {
                    btn.disabled = false;
                    btn.textContent = '룰렛 돌리기 (티켓 1장)';
                }
                return;
            }
            var idx = typeof j.pick.index === 'number' ? j.pick.index : 0;
            var rot = landRotation(idx);
            wheel.style.transform = 'rotate(' + rot + 'deg)';
            setTimeout(function () {
                spinning = false;
                btn.classList.remove('spinning');
                applyState(j.state, j.pick.label || '', j.grant_msg || j.data || '');
            }, 4300);
        }).catch(function () {
            spinning = false;
            btn.classList.remove('spinning');
            btn.disabled = false;
            btn.textContent = '룰렛 돌리기 (티켓 1장)';
            resultBox.textContent = '네트워크 오류';
        });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
