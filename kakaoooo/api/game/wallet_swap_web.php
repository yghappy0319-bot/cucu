<?php
/**
 * 본방냥 → 게임냥 스왑 (게임냥 → 본방냥 포함)
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

$np_min = 0; // 가방 본방→게임: 500냥 최소 제한 없음
$np_delete_pct = 10;
$pt_fee_pct = 20;
$pt_np_min = 500;
if (function_exists('스왑_본방_삭제비율')) {
    $np_delete_pct = (int)스왑_본방_삭제비율();
}
if (function_exists('스왑_게임방_수수료비율')) {
    $pt_fee_pct = (int)스왑_게임방_수수료비율();
}
if (function_exists('스왑_게임방_최소본방냥')) {
    $pt_np_min = (int)스왑_게임방_최소본방냥();
}
// swap.inc may need load for rates
if (!function_exists('스왑_1보유냥당_게임냥')) {
    $swap_inc = __DIR__ . '/swap.inc.php';
    if (is_file($swap_inc)) {
        include_once $swap_inc;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c1222">
    <title>냥 스왑</title>
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
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            background:
                radial-gradient(ellipse at 80% 0%, rgba(245,197,66,0.1) 0%, transparent 45%),
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
        .card h2 { font-size: 0.92rem; margin: 0 0 8px; }
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
            font-size: 0.8rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }
        .tab.active {
            color: var(--text);
            border-color: rgba(245,197,66,0.4);
            background: rgba(245,197,66,0.1);
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
        .hint, .quote-box {
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.5;
            margin: 0 0 12px;
        }
        .quote-box {
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            white-space: pre-wrap;
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
            color: #1a1208;
            background: linear-gradient(145deg, #f5c542, #d4a017);
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
    <h1>💱 냥 스왑</h1>
    <p class="sub">본방냥을 게임냥으로 바꾸거나, 게임냥을 본방냥으로 바꿉니다.</p>

    <?php if (!empty($wallet_off_work)) { ?>
        <div class="need-code">퇴근 중에는 이용할 수 없어요.<br>공창에서 .출근 후 다시 들어와 주세요.</div>
        <a class="back" href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">← 가방으로</a>
    <?php } elseif ($wallet_need_code) { ?>
        <div class="need-code">접속 코드가 필요합니다.<br>가방 링크로 다시 들어와 주세요.</div>
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
                <button type="button" class="tab active" data-tab="np2pt">본방 → 게임</button>
                <button type="button" class="tab" data-tab="pt2np">게임 → 본방</button>
            </div>

            <div class="panel active" id="panel-np2pt">
                <p class="hint">삭제 <?php echo (int)$np_delete_pct; ?>% · 금액 비우면 전액 · 최소 제한 없음</p>
                <div class="form-field">
                    <label for="swapNpAmount">스왑할 본방냥</label>
                    <input type="number" id="swapNpAmount" min="0" step="0.1" placeholder="예) 100 또는 비우면 전액" inputmode="decimal">
                </div>
                <div class="quote-box" id="swapNpQuote">금액을 입력하면 예상 지급액을 보여드려요.</div>
                <button type="button" class="btn-submit" id="btnSwapNp">본방냥 → 게임냥 스왑</button>
            </div>

            <div class="panel" id="panel-pt2np">
                <p class="hint">받을 본방냥 입력 · 최소 <?php echo (int)$pt_np_min; ?>냥 · 게임냥 수수료 <?php echo (int)$pt_fee_pct; ?>% · 비우면 전액</p>
                <div class="form-field">
                    <label for="swapPtAmount">받을 본방냥</label>
                    <input type="number" id="swapPtAmount" min="<?php echo (int)$pt_np_min; ?>" step="1" placeholder="예) 518000 또는 비우면 전액" inputmode="numeric" autocomplete="off">
                </div>
                <div class="quote-box" id="swapPtQuote">입력한 본방냥을 받기 위해 차감될 게임냥(수수료 <?php echo (int)$pt_fee_pct; ?>% 포함)을 계산해요.</div>
                <button type="button" class="btn-submit" id="btnSwapPt">게임냥 → 본방냥 스왑</button>
            </div>
        </div>

        <p class="hint" id="swapRateLine" style="text-align:center;">환율 불러오는 중…</p>
        <div class="toast" id="toast"></div>
    <?php } ?>
</div>

<?php if (!$wallet_need_code && empty($wallet_off_work)) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($wallet_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($wallet_api, JSON_UNESCAPED_UNICODE); ?>;
    var quoteTimer = null;

    function showToast(msg) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(function() { t.classList.remove('show'); }, 3200);
    }

    function ajax(action, extra, cb, silent) {
        var app = document.getElementById('app');
        if (!silent) app.classList.add('loading');
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
            if (!silent) app.classList.remove('loading');
            if (!j || !j.ok) {
                if (!silent) showToast((j && (j.data || j.msg)) || '오류');
                if (cb) cb(j || { ok: false });
                return;
            }
            if (j.member) {
                if (j.member.newpoint_fmt) document.getElementById('wNewpoint').textContent = j.member.newpoint_fmt;
                if (j.member.point_fmt) document.getElementById('wPoint').textContent = j.member.point_fmt;
            }
            if (cb) cb(j);
        })
        .catch(function() {
            if (!silent) {
                app.classList.remove('loading');
                showToast('네트워크 오류');
            }
            if (cb) cb({ ok: false, data: '네트워크 오류' });
        });
    }

    function formatQuoteNp2pt(q) {
        if (!q) return '견적을 불러올 수 없어요.';
        var give = q['지급_pt_fmt'] || q['지급_pt'] || '?';
        var cut = q['차감_np_fmt'] || q['차감_np'] || q['삭제_np_fmt'] || q['삭제_np'] || '';
        var del = q['삭제_np_fmt'] || q['삭제_np'] || '';
        return '차감 본방냥 ' + (q['차감_np_fmt'] || q['차감_np'] || '?') + '냥\n'
            + '삭제 ' + del + '냥\n'
            + '지급 게임냥 ' + give + '냥';
    }

    function formatQuotePt2np(q) {
        if (!q) return '견적을 불러올 수 없어요.';
        var cut = q['차감_pt_fmt'] || q['차감_pt'] || '?';
        var fee = q['수수료_pt_fmt'] || q['수수료_pt'] || '?';
        var exch = q['교환_pt_fmt'] || q['교환_pt'] || '?';
        var give = q['지급_np_fmt'] || q['지급_np'] || '?';
        var want = q['원하는_np_fmt'] || q['원하는_np'] || '';
        var max = q['최대_np_fmt'] || q['최대_np'] || '';
        var lines = '받을 본방냥: ' + give + '냥';
        if (want !== '' && want != null) {
            lines += ' (요청 ' + want + '냥)';
        }
        lines += '\n차감될 게임냥: ' + cut + '냥\n'
            + '· 수수료 20% ' + fee + '냥\n'
            + '· 교환 80% ' + exch + '냥';
        if (max !== '' && max != null) {
            lines += '\n전액 스왑 시 최대: ' + max + '냥';
        }
        return lines;
    }

    function updateSwapNpQuote() {
        var amount = document.getElementById('swapNpAmount').value.trim();
        var box = document.getElementById('swapNpQuote');
        ajax('swap_quote', { dir: 'np2pt', amount: amount }, function(j) {
            if (!j || !j.ok) {
                box.textContent = (j && (j.data || j.msg)) || '견적을 계산할 수 없어요.';
                return;
            }
            box.textContent = formatQuoteNp2pt(j.quote);
        }, true);
    }

    function updateSwapPtQuote() {
        var amount = document.getElementById('swapPtAmount').value.trim();
        var box = document.getElementById('swapPtQuote');
        if (amount === '') {
            box.textContent = '금액을 입력하면 차감될 게임냥을 비율로 계산해요. (비우면 전액)';
        }
        ajax('swap_quote', { dir: 'pt2np', amount: amount }, function(j) {
            if (!j || !j.ok) {
                box.textContent = (j && (j.data || j.msg)) || '견적을 계산할 수 없어요.';
                return;
            }
            box.textContent = formatQuotePt2np(j.quote);
        }, true);
    }

    document.querySelectorAll('.tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            var tab = btn.getAttribute('data-tab');
            document.getElementById('panel-' + tab).classList.add('active');
            if (tab === 'pt2np') updateSwapPtQuote();
            else updateSwapNpQuote();
        });
    });

    var swapNpInput = document.getElementById('swapNpAmount');
    if (swapNpInput) {
        swapNpInput.addEventListener('input', function() {
            clearTimeout(quoteTimer);
            quoteTimer = setTimeout(updateSwapNpQuote, 280);
        });
    }
    var swapPtInput = document.getElementById('swapPtAmount');
    if (swapPtInput) {
        swapPtInput.addEventListener('input', function() {
            clearTimeout(quoteTimer);
            quoteTimer = setTimeout(updateSwapPtQuote, 280);
        });
    }

    document.getElementById('btnSwapNp').addEventListener('click', function() {
        ajax('swap_np', { amount: document.getElementById('swapNpAmount').value.trim() }, function(j) {
            if (!j || !j.ok) return;
            if (j.data) showToast(j.data);
            updateSwapNpQuote();
            updateSwapPtQuote();
        });
    });
    document.getElementById('btnSwapPt').addEventListener('click', function() {
        ajax('swap_pt', { amount: document.getElementById('swapPtAmount').value.trim() }, function(j) {
            if (!j || !j.ok) return;
            if (j.data) showToast(j.data);
            updateSwapNpQuote();
            updateSwapPtQuote();
        });
    });

    ajax('status', null, function(j) {
        if (!j || !j.ok) return;
        if (j.swap && (j.swap.np_per_pt_fmt || j.swap.np_per_pt != null)) {
            var rateFmt = j.swap.np_per_pt_fmt || String(j.swap.np_per_pt);
            document.getElementById('swapRateLine').textContent = '환율 ≈ 1 본방냥 : ' + rateFmt + ' 게임냥';
        } else {
            document.getElementById('swapRateLine').textContent = '';
        }
        updateSwapNpQuote();
        updateSwapPtQuote();
    }, true);
})();
</script>
<?php } ?>
<?php
if (!empty($boot['code'])) {
  require_once __DIR__ . '/wallet_nav_fab.inc.php';
  wallet_nav_fab_render([
    'code' => $boot['code'],
    'show_swap' => false,
  ]);
}
?>
</body>
</html>
