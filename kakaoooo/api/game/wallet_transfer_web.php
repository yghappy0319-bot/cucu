<?php
/**
 * 친구에게 냥 보내기 (본방냥·게임냥 양도)
 */
require_once __DIR__ . '/wallet_subpage.inc.php';

$boot = wallet_subpage_boot();
$wallet_code = $boot['code'];
$wallet_need_code = $boot['need_code'];
$wallet_off_work = !empty($boot['off_work']);
$wallet_member = $boot['member'];
$wallet_q = $boot['q'];
$wallet_api = $boot['api'];

$np_fmt = is_array($wallet_member) ? (string)($wallet_member['newpoint_fmt'] ?? '0') : '0';
$pt_fmt = is_array($wallet_member) ? (string)($wallet_member['point_fmt'] ?? '0') : '0';
$np_raw = is_array($wallet_member) ? (int)($wallet_member['newpoint'] ?? 0) : 0;
$pt_raw = is_array($wallet_member) ? (string)($wallet_member['point'] ?? '0') : '0';
$nick_disp = is_array($wallet_member) ? (string)($wallet_member['nick'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c1222">
    <title>친구에게 냥 보내기</title>
    <style>
        :root {
            --bg: #0c1222;
            --card: #141c2e;
            --card2: #1a2438;
            --text: #e8eef8;
            --muted: rgba(232,238,248,0.55);
            --line: rgba(148,163,184,0.16);
            --mint: #5eead4;
            --gold: #f5c542;
            --accent: #f87171;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            background:
                radial-gradient(ellipse at 20% 0%, rgba(94,234,212,0.1) 0%, transparent 45%),
                var(--bg);
            color: var(--text);
            padding: 16px 14px 28px;
        }
        .wrap { max-width: 480px; margin: 0 auto; }
        h1 { font-size: 1.15rem; margin: 0 0 6px; }
        .sub { font-size: 0.78rem; color: var(--muted); margin-bottom: 14px; line-height: 1.45; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 12px;
            margin-bottom: 12px;
        }
        .card h2 { font-size: 0.92rem; margin: 0 0 10px; }
        .bal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        .bal-item {
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
        }
        .bal-item span { display: block; font-size: 0.68rem; color: var(--muted); margin-bottom: 4px; }
        .bal-item strong { font-size: 0.9rem; font-variant-numeric: tabular-nums; }
        .bal-item.mint strong { color: var(--mint); }
        .bal-item.gold strong { color: var(--gold); }
        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 12px;
        }
        .tab {
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--muted);
            border-radius: 10px;
            padding: 10px 8px;
            font-size: 0.82rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }
        .tab.active {
            color: var(--text);
            border-color: rgba(94,234,212,0.35);
            background: rgba(94,234,212,0.1);
        }
        .panel { display: none; }
        .panel.active { display: block; }
        .form-field { margin-bottom: 10px; }
        .form-field label {
            display: block;
            font-size: 0.72rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .form-field input {
            width: 100%;
            padding: 12px 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--text);
            font-size: 0.95rem;
            font-family: inherit;
        }
        .pct-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin: 8px 0 12px;
        }
        .btn-pct {
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--text);
            border-radius: 10px;
            padding: 10px 6px;
            min-height: 40px;
            font-size: 0.82rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-pct:active {
            background: rgba(94,234,212,0.14);
            border-color: rgba(94,234,212,0.35);
        }
        .hint { font-size: 0.72rem; color: var(--muted); line-height: 1.45; margin: 0 0 12px; }
        .hist-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }
        .hist-head h2 { margin: 0; }
        .hist-refresh {
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--muted);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.72rem;
            font-family: inherit;
            cursor: pointer;
        }
        .hist-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 360px;
            overflow-y: auto;
        }
        .hist-list li {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
            font-size: 0.82rem;
            line-height: 1.4;
        }
        .hist-list li:last-child { border-bottom: 0; }
        .hist-main { min-width: 0; flex: 1; }
        .hist-dir {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            border-radius: 999px;
            padding: 2px 7px;
            margin-right: 6px;
            vertical-align: middle;
        }
        .hist-dir.out {
            color: #fecaca;
            background: rgba(248, 113, 113, 0.15);
        }
        .hist-dir.in {
            color: #99f6e4;
            background: rgba(94, 234, 212, 0.14);
        }
        .hist-peer { font-weight: 700; }
        .hist-meta {
            display: block;
            margin-top: 3px;
            font-size: 0.7rem;
            color: var(--muted);
        }
        .hist-amt {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .hist-amt.out { color: #fca5a5; }
        .hist-amt.in { color: var(--mint); }
        .hist-amt small {
            display: block;
            margin-top: 2px;
            font-size: 0.66rem;
            color: var(--muted);
            font-weight: 400;
        }
        .hist-empty {
            text-align: center;
            color: var(--muted);
            font-size: 0.8rem;
            padding: 18px 8px;
        }
        .btn-submit {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 14px;
            min-height: 48px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            color: #06201c;
            background: linear-gradient(145deg, #5eead4, #2dd4bf);
            cursor: pointer;
        }
        .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }
        .back {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.84rem;
        }
        .toast {
            position: fixed;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%) translateY(20px);
            background: rgba(15,23,42,0.95);
            color: #fff;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.84rem;
            max-width: 90%;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s, transform 0.2s;
            z-index: 50;
            white-space: pre-wrap;
            text-align: center;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .need-code {
            text-align: center;
            padding: 40px 16px;
            color: var(--muted);
            line-height: 1.6;
        }
        .loading { opacity: 0.55; pointer-events: none; }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <h1>🎁 친구에게 냥 보내기</h1>
    <p class="sub">본방냥·게임냥을 친구에게 양도합니다.</p>

    <?php if (!empty($wallet_off_work)) { ?>
        <div class="need-code">퇴근 중에는 이용할 수 없어요.<br>공창에서 .출근 후 다시 들어와 주세요.</div>
        <a class="back" href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">← 가방으로</a>
    <?php } elseif ($wallet_need_code) { ?>
        <div class="need-code">접속 코드가 필요합니다.<br>가방 링크로 다시 들어와 주세요.</div>
        <a class="back" href="/page/wallet.php">← 가방으로</a>
    <?php } else { ?>
        <div class="bal">
            <div class="bal-item mint">
                <span>본방냥</span>
                <strong id="wNewpoint"><?php echo htmlspecialchars($np_fmt, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="bal-item gold">
                <span>게임냥</span>
                <strong id="wPoint"><?php echo htmlspecialchars($pt_fmt, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>

        <div class="card">
            <div class="tabs">
                <button type="button" class="tab active" data-tab="np">본방냥 보내기</button>
                <button type="button" class="tab" data-tab="pt">게임냥 보내기</button>
            </div>

            <div class="panel active" id="panel-np">
                <p class="hint">받는 닉네임(2글자)과 본방냥 금액을 입력하세요.</p>
                <div class="form-field">
                    <label for="transferNpReceiver">받는 닉네임</label>
                    <input type="text" id="transferNpReceiver" maxlength="20" placeholder="예) 친구 닉네임 2글자" autocomplete="off">
                </div>
                <div class="form-field">
                    <label for="transferNpAmount">양도할 본방냥</label>
                    <input type="number" id="transferNpAmount" min="1" step="1" placeholder="정수 냥" inputmode="numeric">
                    <div class="pct-row" data-kind="np" data-target="transferNpAmount">
                        <button type="button" class="btn-pct" data-pct="10">보유 10%</button>
                        <button type="button" class="btn-pct" data-pct="50">보유 50%</button>
                        <button type="button" class="btn-pct" data-pct="100">보유 100%</button>
                    </div>
                </div>
                <button type="button" class="btn-submit" id="btnTransferNp">본방냥 보내기</button>
            </div>

            <div class="panel" id="panel-pt">
                <p class="hint">게임냥 양도는 30,000냥 이상만 가능합니다.</p>
                <div class="form-field">
                    <label for="transferPtReceiver">받는 닉네임</label>
                    <input type="text" id="transferPtReceiver" maxlength="20" placeholder="예) 친구 닉네임 2글자" autocomplete="off">
                </div>
                <div class="form-field">
                    <label for="transferPtAmount">양도할 게임냥</label>
                    <input type="number" id="transferPtAmount" min="30000" step="1" placeholder="3만냥 이상" inputmode="numeric">
                    <div class="pct-row" data-kind="pt" data-target="transferPtAmount">
                        <button type="button" class="btn-pct" data-pct="10">보유 10%</button>
                        <button type="button" class="btn-pct" data-pct="50">보유 50%</button>
                        <button type="button" class="btn-pct" data-pct="100">보유 100%</button>
                    </div>
                </div>
                <button type="button" class="btn-submit" id="btnTransferPt">게임냥 보내기</button>
            </div>
        </div>

        <div class="card">
            <div class="hist-head">
                <h2>주고받은 내역</h2>
                <button type="button" class="hist-refresh" id="btnHistRefresh">새로고침</button>
            </div>
            <p class="hint" style="margin-top:0">최근 양도·마피아 적발 보상 기록입니다. 보낸/받은 모두 표시됩니다.</p>
            <ul class="hist-list" id="histList">
                <li class="hist-empty" id="histEmpty">불러오는 중…</li>
            </ul>
        </div>

        <a class="back" href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">← 가방으로</a>
        <div class="toast" id="toast"></div>
    <?php } ?>
</div>

<?php if (!$wallet_need_code && empty($wallet_off_work)) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($wallet_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($wallet_api, JSON_UNESCAPED_UNICODE); ?>;
    var NP = <?php echo (int)$np_raw; ?>;
    var PT = <?php echo json_encode((string)$pt_raw, JSON_UNESCAPED_UNICODE); ?>;

    function showToast(msg) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(function() { t.classList.remove('show'); }, 3200);
    }

    function ajax(action, extra, cb) {
        var app = document.getElementById('app');
        app.classList.add('loading');
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('wallet_code', CODE);
        if (extra) Object.keys(extra).forEach(function(k) { body.set(k, extra[k]); });
        var url = API + (CODE ? ('?code=' + encodeURIComponent(CODE)) : '');
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            app.classList.remove('loading');
            if (!j || !j.ok) {
                showToast((j && (j.data || j.msg)) || '오류');
                return;
            }
            if (j.member) {
                if (j.member.newpoint_fmt) document.getElementById('wNewpoint').textContent = j.member.newpoint_fmt;
                if (j.member.point_fmt) document.getElementById('wPoint').textContent = j.member.point_fmt;
                if (j.member.newpoint != null) NP = parseInt(j.member.newpoint, 10) || 0;
                if (j.member.point != null) PT = String(j.member.point);
            }
            if (j.data) showToast(j.data);
            if (cb) cb(j);
        })
        .catch(function() {
            app.classList.remove('loading');
            showToast('네트워크 오류');
        });
    }

    function digitsOnly(s) {
        s = String(s == null ? '0' : s).replace(/[^\d]/g, '');
        return s.replace(/^0+/, '') || '0';
    }

    function halfFloorDigits(s) {
        var carry = 0, out = '';
        for (var i = 0; i < s.length; i++) {
            var n = carry * 10 + (s.charCodeAt(i) - 48);
            out += String(Math.floor(n / 2));
            carry = n % 2;
        }
        return out.replace(/^0+/, '') || '0';
    }

    function pctOfHold(kind, pct) {
        pct = parseInt(pct, 10) || 0;
        if (kind === 'np') {
            var n = Math.max(0, parseInt(NP, 10) || 0);
            return String(Math.floor(n * pct / 100));
        }
        var s = digitsOnly(PT);
        if (s === '0' || pct <= 0) return '0';
        if (pct === 100) return s;
        if (pct === 10) return s.length <= 1 ? '0' : (s.slice(0, -1).replace(/^0+/, '') || '0');
        if (pct === 50) return halfFloorDigits(s);
        return '0';
    }

    document.querySelectorAll('.pct-row .btn-pct').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('.pct-row');
            var target = document.getElementById(row.getAttribute('data-target'));
            if (!target) return;
            var amt = pctOfHold(row.getAttribute('data-kind'), btn.getAttribute('data-pct'));
            if (amt === '0') {
                showToast('보유량이 부족해요.');
                return;
            }
            target.value = amt;
        });
    });

    document.querySelectorAll('.tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('panel-' + btn.getAttribute('data-tab')).classList.add('active');
        });
    });

    document.getElementById('btnTransferNp').addEventListener('click', function() {
        ajax('transfer_np', {
            receiver: document.getElementById('transferNpReceiver').value.trim(),
            amount: document.getElementById('transferNpAmount').value.trim()
        }, function() { loadHistory(); });
    });
    document.getElementById('btnTransferPt').addEventListener('click', function() {
        ajax('transfer_pt', {
            receiver: document.getElementById('transferPtReceiver').value.trim(),
            amount: document.getElementById('transferPtAmount').value.trim()
        }, function() { loadHistory(); });
    });

    function loadHistory() {
        var list = document.getElementById('histList');
        if (!list) return;
        var body = new URLSearchParams();
        body.set('action', 'transfer_history');
        body.set('wallet_code', CODE);
        body.set('limit', '40');
        var url = API + (CODE ? ('?code=' + encodeURIComponent(CODE)) : '');
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            var items = (j && j.ok && j.items) ? j.items : [];
            if (!items.length) {
                list.innerHTML = '<li class="hist-empty">아직 주고받은 내역이 없어요.</li>';
                return;
            }
            var html = '';
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                var isMafia = it.kind === 'mafia_catch';
                var isOut = !isMafia && it.dir === 'out';
                var dirLabel = isMafia ? '보상' : (isOut ? '보냄' : '받음');
                var peer = it.peer || '—';
                var mainAmt = isOut
                    ? ('-' + (it.amount_fmt || '0'))
                    : ('+' + (it.received_fmt || it.amount_fmt || '0'));
                var sub;
                if (isMafia) {
                    sub = '보유 ' + (it.balance_before_fmt || '0') + '냥 → +'
                        + (it.amount_fmt || '0') + '냥 → '
                        + (it.balance_after_fmt || '0') + '냥';
                } else if (isOut) {
                    sub = '수수료 ' + (it.fee_fmt || '0') + ' · 상대 수령 ' + (it.received_fmt || '0');
                } else {
                    sub = '보낸 금액 ' + (it.amount_fmt || '0') + (it.fee && it.fee !== '0' ? (' · 수수료 ' + (it.fee_fmt || '0')) : '');
                }
                html += '<li>'
                    + '<div class="hist-main">'
                    + '<span class="hist-dir ' + (isOut ? 'out' : 'in') + '">' + dirLabel + '</span>'
                    + '<span class="hist-peer">' + escapeHtml(peer) + '</span>'
                    + '<span class="hist-meta">' + escapeHtml(it.time || '') + '</span>'
                    + '</div>'
                    + '<div class="hist-amt ' + (isOut ? 'out' : 'in') + '">' + escapeHtml(mainAmt)
                    + '<small>' + escapeHtml(sub) + '</small></div>'
                    + '</li>';
            }
            list.innerHTML = html;
        })
        .catch(function() {
            list.innerHTML = '<li class="hist-empty">내역을 불러오지 못했어요.</li>';
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var btnRefresh = document.getElementById('btnHistRefresh');
    if (btnRefresh) btnRefresh.addEventListener('click', loadHistory);
    loadHistory();
})();
</script>
<?php } ?>
</body>
</html>
