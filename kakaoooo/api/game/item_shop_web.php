<?php
/**
 * 교환 웹 (지갑 code 인증 공유) — 구 아이템상점 URL 유지
 *
 * URL:
 *   /page/item_shop.php?code=XXXX
 *   /api/game/item_shop_web.php?code=XXXX
 *
 * AJAX:
 *   action=item_shop       — 교환 목록 새로고침
 *   action=item_exchange   — 교환 실행 (조각↔은총 · 일방권→은총)
 */

define('WALLET_LIB_ONLY', true);
require_once __DIR__ . '/wallet_web.php';

$shop_actions = ['item_shop', 'item_exchange'];
$req_action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

if (in_array($req_action, $shop_actions, true)) {
    ob_start();
}

$req_code = wallet_request_code();

if (in_array($req_action, $shop_actions, true)) {
    if ($req_code === '') {
        wallet_json(['ok' => false, 'data' => '접속 코드가 필요합니다.']);
    }

    $auth = wallet_member_refresh($req_code);
    if (!$auth) {
        if (function_exists('wallet_퇴근_차단_여부') && wallet_퇴근_차단_여부()) {
            wallet_json(['ok' => false, 'data' => wallet_퇴근_차단_메시지()]);
        }
        wallet_json(['ok' => false, 'data' => '유효하지 않은 코드입니다. 링크를 다시 받아주세요.']);
    }
    $req_code = $auth['code'] ?? $req_code;
    $member = $auth['row'];
    $nick = $auth['nick'];
    wallet_api_load($nick);

    if ($req_action === 'item_shop') {
        $fresh = wallet_auth($req_code) ?: $member;
        wallet_json([
            'ok' => true,
            'type' => 'item_shop',
            'member' => wallet_member_payload($fresh, $nick),
            'shop' => wallet_item_shop_data($fresh),
        ]);
    }

    if ($req_action === 'item_exchange') {
        $exchange_id = isset($_REQUEST['exchange_id']) ? trim($_REQUEST['exchange_id']) : 'ilbang_to_eunchong';
        $times = isset($_REQUEST['times']) ? (int)$_REQUEST['times'] : 1;

        if ($exchange_id === 'shard_to_eunchong') {
            if (!function_exists('mining_ore_exchange_to_eunchong')) {
                require_once __DIR__ . '/mining_config.inc.php';
                require_once __DIR__ . '/mining_storage.inc.php';
                require_once __DIR__ . '/mining_ore.inc.php';
            }
            if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
                require_once __DIR__ . '/../item_bag_enhance.inc.php';
            }
            if (!function_exists('mining_ore_exchange_to_eunchong')) {
                wallet_json(['ok' => false, 'data' => '교환 기능을 불러올 수 없습니다.']);
            }
            $result = mining_ore_exchange_to_eunchong($nick, 1);
            if (empty($result['ok'])) {
                wallet_json(['ok' => false, 'data' => $result['msg'] ?? '교환 실패']);
            }
            $fresh = wallet_auth($req_code) ?: $member;
            wallet_json([
                'ok' => true,
                'data' => $result['msg'],
                'member' => wallet_member_payload($fresh, $nick),
                'shop' => wallet_item_shop_data($fresh),
            ]);
        }

        if ($exchange_id === 'eunchong_to_shard') {
            if (!function_exists('mining_ore_exchange_eunchong_to_shards')) {
                require_once __DIR__ . '/mining_config.inc.php';
                require_once __DIR__ . '/mining_storage.inc.php';
                require_once __DIR__ . '/mining_ore.inc.php';
            }
            if (!function_exists('bag_은총_차감') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
                require_once __DIR__ . '/../item_bag_enhance.inc.php';
            }
            if (!function_exists('mining_ore_exchange_eunchong_to_shards')) {
                wallet_json(['ok' => false, 'data' => '교환 기능을 불러올 수 없습니다.']);
            }
            $result = mining_ore_exchange_eunchong_to_shards($nick);
            if (empty($result['ok'])) {
                wallet_json(['ok' => false, 'data' => $result['msg'] ?? '교환 실패']);
            }
            $fresh = wallet_auth($req_code) ?: $member;
            wallet_json([
                'ok' => true,
                'data' => $result['msg'],
                'member' => wallet_member_payload($fresh, $nick),
                'shop' => wallet_item_shop_data($fresh),
            ]);
        }

        if ($exchange_id !== 'ilbang_to_eunchong') {
            wallet_json(['ok' => false, 'data' => '알 수 없는 교환입니다.']);
        }
        if (!function_exists('아이템_교환_일방_은총_실행')) {
            wallet_json(['ok' => false, 'data' => '교환 기능을 불러올 수 없습니다.']);
        }
        $result = 아이템_교환_일방_은총_실행($nick, $member, $times);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['msg'] ?? '교환 실패']);
        }
        $fresh = wallet_auth($req_code) ?: $member;
        wallet_json([
            'ok' => true,
            'data' => $result['msg'],
            'member' => wallet_member_payload($fresh, $nick),
            'shop' => wallet_item_shop_data($fresh),
        ]);
    }
}

$shop_api_entry = '/api/game/item_shop_web.php';
$shop_script = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($shop_script, '/page/item_shop.php') !== false) {
    $shop_api_entry = '/page/item_shop.php';
}

$shop_code = wallet_코드_해석();
$shop_need_code = true;
$shop_off_work = false;
$shop_member = null;
$shop_data = null;

if (!empty($GLOBALS['wallet_preauth']['row']) && !empty($GLOBALS['wallet_preauth']['code'])) {
    $shop_code = (string)$GLOBALS['wallet_preauth']['code'];
    $row = wallet_member_row_enrich($GLOBALS['wallet_preauth']['row'], $shop_code);
    $nick = wallet_nick_from_row($row);
    if ($nick === '') {
        $nick = trim((string)($row['name'] ?? ''));
    }
    if (wallet_nick_is_퇴근($nick)) {
        $shop_off_work = true;
        $shop_need_code = true;
        $shop_member = null;
    } else {
        wallet_코드_쿠키_저장($shop_code);
        wallet_api_load($nick);
        $shop_need_code = false;
        $shop_member = wallet_member_payload($row, $nick);
        $shop_data = wallet_item_shop_data($row);
    }
} elseif ($shop_code !== '') {
    wallet_odd_even_includes();
    $row = wallet_game_auth_row($shop_code);
    if ($row) {
        $row = wallet_member_row_enrich($row, $shop_code);
        $nick = wallet_nick_from_row($row);
        if ($nick === '') {
            $nick = trim((string)($row['name'] ?? ''));
        }
        if (wallet_nick_is_퇴근($nick)) {
            $shop_off_work = true;
            $shop_need_code = true;
            $shop_member = null;
        } else {
            wallet_코드_쿠키_저장($shop_code);
            wallet_api_load($nick);
            $shop_need_code = false;
            $shop_member = wallet_member_payload($row, $nick);
            $shop_data = wallet_item_shop_data($row);
        }
    }
}

$shop_wallet_href = '/page/wallet.php' . wallet_code_query($shop_code);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c1222">
    <title>교환</title>
    <style>
        :root {
            --bg: #0c1222;
            --card: #151d32;
            --card2: #1a2540;
            --gold: #f5c542;
            --mint: #5eead4;
            --text: #e8edf8;
            --muted: #8b9bb8;
            --line: rgba(255,255,255,0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 20% 0%, rgba(245,197,66,0.1) 0%, transparent 45%),
                radial-gradient(ellipse at 90% 20%, rgba(94,234,212,0.1) 0%, transparent 40%);
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            color: var(--text);
            padding: 12px;
            padding-top: max(12px, env(safe-area-inset-top));
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }
        .wrap { max-width: 480px; margin: 0 auto; }
        h1 {
            font-size: 1.75rem;
            font-weight: 900;
            text-align: center;
            margin: 4px 0 14px;
            color: var(--gold);
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 10px;
        }
        .card h2 {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }
        .nick { font-size: 1.2rem; font-weight: 900; }
        .point { color: var(--gold); font-weight: 800; font-size: 1rem; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--mint);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
                .hint { font-size: 0.76rem; color: var(--muted); margin-bottom: 8px; line-height: 1.45; }
        .shop-empty { text-align: center; color: var(--muted); padding: 24px 8px; font-size: 0.86rem; }
        .btn-row { display: flex; gap: 8px; margin-top: 10px; }
        .btn {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 11px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            background: rgba(94,234,212,0.15);
            color: var(--mint);
        }

        /* ── 교환 카드 ── */
        .ex-card {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            margin-bottom: 12px;
            padding: 14px 14px 12px;
            border: 1px solid var(--line);
            background:
                linear-gradient(155deg, rgba(255,255,255,0.04) 0%, transparent 42%),
                var(--card2);
        }
        .ex-card::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 3px;
            background: var(--ex-accent, var(--mint));
        }
        .ex-card.is-shard {
            --ex-accent: #f5c542;
            border-color: rgba(245,197,66,0.28);
            background:
                radial-gradient(ellipse at 100% 0%, rgba(245,197,66,0.16) 0%, transparent 55%),
                linear-gradient(155deg, rgba(245,197,66,0.06) 0%, transparent 50%),
                var(--card2);
        }
        .ex-card.is-risky {
            --ex-accent: #fb7185;
            border-color: rgba(251,113,133,0.32);
            background:
                radial-gradient(ellipse at 100% 0%, rgba(251,113,133,0.18) 0%, transparent 55%),
                linear-gradient(155deg, rgba(251,113,133,0.07) 0%, transparent 50%),
                var(--card2);
        }
        .ex-card.is-ticket {
            --ex-accent: #5eead4;
            border-color: rgba(94,234,212,0.28);
            background:
                radial-gradient(ellipse at 100% 0%, rgba(94,234,212,0.14) 0%, transparent 55%),
                linear-gradient(155deg, rgba(94,234,212,0.06) 0%, transparent 50%),
                var(--card2);
        }
        .ex-card.is-disabled { opacity: 0.72; filter: saturate(0.75); }
        .ex-head {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .ex-icon {
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .ex-card.is-shard .ex-icon { background: rgba(245,197,66,0.14); }
        .ex-card.is-risky .ex-icon { background: rgba(251,113,133,0.16); }
        .ex-card.is-ticket .ex-icon { background: rgba(94,234,212,0.14); }
        .ex-titles { flex: 1; min-width: 0; }
        .ex-title {
            font-size: 0.98rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1.25;
            color: var(--text);
        }
        .ex-desc {
            margin-top: 3px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.4;
        }
        .ex-badge {
            display: inline-flex;
            align-items: center;
            margin-top: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .ex-badge.safe {
            color: #86efac;
            background: rgba(34,197,94,0.14);
            border: 1px solid rgba(34,197,94,0.28);
        }
        .ex-badge.risk {
            color: #fda4af;
            background: rgba(251,113,133,0.16);
            border: 1px solid rgba(251,113,133,0.35);
            animation: ex-pulse 1.8s ease-in-out infinite;
        }
        @keyframes ex-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(251,113,133,0.0); }
            50% { box-shadow: 0 0 0 4px rgba(251,113,133,0.12); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ex-badge.risk { animation: none; }
        }
        .ex-flow {
            display: flex;
            align-items: stretch;
            gap: 8px;
            margin: 10px 0 12px;
        }
        .ex-cost-stack {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .ex-chip {
            flex: 1;
            min-width: 0;
            border-radius: 10px;
            padding: 8px 10px;
            background: rgba(0,0,0,0.22);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .ex-chip.ok { border-color: rgba(94,234,212,0.28); }
        .ex-chip.lack {
            border-color: rgba(251,113,133,0.4);
            background: rgba(251,113,133,0.08);
        }
        .ex-chip .lab {
            font-size: 0.68rem;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 2px;
        }
        .ex-chip .val {
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1.25;
            word-break: keep-all;
        }
        .ex-chip .sub {
            margin-top: 2px;
            font-size: 0.7rem;
            color: var(--muted);
        }
        .ex-chip.lack .sub { color: #fda4af; font-weight: 700; }
        .ex-arrow {
            flex: 0 0 auto;
            align-self: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 0.85rem;
            font-weight: 900;
            color: var(--ex-accent, var(--mint));
            background: rgba(0,0,0,0.28);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .ex-bar-wrap { margin-bottom: 10px; }
        .ex-bar-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.68rem;
            color: var(--muted);
            margin-bottom: 4px;
            font-weight: 700;
        }
        .ex-bar {
            height: 7px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
        }
        .ex-bar > i {
            display: block;
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #fbbf24, #f5c542);
            transition: width .35s ease;
        }
        .ex-card.is-risky .ex-bar > i {
            background: linear-gradient(90deg, #fb7185, #fda4af);
        }
        .ex-actions { display: flex; }
        .ex-btn {
            flex: 1;
            border: none;
            border-radius: 11px;
            padding: 11px 12px;
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: -0.01em;
            transition: transform .12s ease, filter .12s ease;
        }
        .ex-btn:active:not(:disabled) { transform: scale(0.98); }
        .ex-btn.primary {
            color: #0c1222;
            background: linear-gradient(135deg, #f5c542 0%, #fbbf24 100%);
            box-shadow: 0 6px 16px rgba(245,197,66,0.22);
        }
        .ex-btn.danger {
            color: #fff;
            background: linear-gradient(135deg, #fb7185 0%, #e11d48 100%);
            box-shadow: 0 6px 16px rgba(225,29,72,0.28);
        }
        .ex-btn.mint {
            color: #0c1222;
            background: linear-gradient(135deg, #5eead4 0%, #2dd4bf 100%);
            box-shadow: 0 6px 16px rgba(94,234,212,0.22);
        }
        .ex-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            box-shadow: none;
            filter: grayscale(0.35);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 100;
            align-items: flex-end;
            justify-content: center;
            padding: 12px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            width: 100%;
            max-width: 480px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px 16px 12px 12px;
            padding: 18px 16px;
        }
        .modal-box h3 { margin-bottom: 12px; font-size: 1rem; }
        .form-field { margin-bottom: 10px; }
        .form-field label { display: block; font-size: 0.76rem; color: var(--muted); margin-bottom: 4px; }
        .form-field input {
            width: 100%;
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 1rem;
            font-family: inherit;
        }
        .quote-box {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.82rem;
            color: var(--muted);
            white-space: pre-line;
            margin-bottom: 12px;
        }
        .modal-actions { display: flex; gap: 8px; }
        .btn-cancel, .btn-confirm {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 11px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-cancel { background: var(--card2); color: var(--muted); }
        .btn-confirm { background: rgba(94,234,212,0.2); color: var(--mint); }
        .toast {
            position: fixed;
            left: 50%;
            bottom: max(20px, env(safe-area-inset-bottom));
            transform: translateX(-50%) translateY(120%);
            background: rgba(20,28,48,0.95);
            color: var(--text);
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.84rem;
            z-index: 200;
            transition: transform .25s ease;
            max-width: 90vw;
            text-align: center;
        }
        .toast.show { transform: translateX(-50%) translateY(0); }
        #app.loading { opacity: 0.72; pointer-events: none; }
        .lock-box { text-align: center; line-height: 1.6; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <a class="back-link" href="<?php echo htmlspecialchars($shop_wallet_href, ENT_QUOTES, 'UTF-8'); ?>">← 가방</a>
    <h1>♻️ 교환</h1>

    <?php if (!empty($shop_off_work)) { ?>
        <div class="card lock-box">
            <p style="font-size:2rem;margin-bottom:10px;">🚌</p>
            <strong>퇴근 중에는 이용할 수 없어요</strong><br>
            공창에서 <code>.출근 닉네임</code> 후 다시 들어와 주세요.
        </div>
    <?php } elseif ($shop_need_code) { ?>
        <div class="card lock-box">
            <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
            <strong>코드를 확인해주세요</strong><br>
            연구실에서 <code>.가방배정</code> 링크로 접속해 주세요.<br>
            <span style="opacity:0.65;font-size:0.8rem;">예) /page/item_shop.php?code=XXXX</span>
        </div>
    <?php } else { ?>
        <div class="card">
            <div class="top-row">
                <div class="nick" id="sNick"><?php echo htmlspecialchars($shop_member['nick'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="point" id="sPoint"><?php echo htmlspecialchars($shop_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</div>
            </div>
            <div class="hint">✨ 은총조각 · 🎟 일방권 · 🎲 확률 교환</div>
            <div id="shopExchangeList"></div>
            <div class="btn-row">
                <button type="button" class="btn" id="btnRefresh">새로고침</button>
            </div>
        </div>

        <div class="modal-overlay" id="modal-itemExchange" onclick="closeShopModalBg(event, 'itemExchange')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 id="itemExchangeTitle">은총 교환</h3>
                <div class="form-field" id="itemExchangeTimesWrap">
                    <label for="itemExchangeTimes">교환 횟수</label>
                    <input type="number" id="itemExchangeTimes" min="1" step="1" value="1">
                </div>
                <div class="quote-box" id="itemExchangeHint">일방신청권 5 + 일방연장권 5 → 은총 1</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeShopModal('itemExchange')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitItemExchange()">교환하기</button>
                </div>
            </div>
        </div>

        <div class="toast" id="toast"></div>
    <?php } ?>
</div>

<?php if (!$shop_need_code) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($shop_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($shop_api_entry, JSON_UNESCAPED_UNICODE); ?>;
    var SHOP_DATA = <?php echo json_encode($shop_data ?: ['exchange'=>[]], JSON_UNESCAPED_UNICODE); ?>;
    var selectedExchange = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function showToast(msg) {
        var t = document.getElementById('toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(t._timer);
        t._timer = setTimeout(function() { t.classList.remove('show'); }, 2800);
    }

    function ajax(action, extra, cb) {
        var app = document.getElementById('app');
        app.classList.add('loading');
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('wallet_code', CODE);
        if (extra) Object.keys(extra).forEach(function(k) { body.set(k, extra[k]); });
        var url = API + (CODE ? '?code=' + encodeURIComponent(CODE) : '');
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
                if (cb) cb(j);
            })
            .catch(function() {
                app.classList.remove('loading');
                showToast('통신 오류');
            });
    }

    function applyMember(m) {
        if (!m) return;
        document.getElementById('sPoint').textContent = m.point_fmt + '냥';
    }

    function renderItemShop() {
        var exBox = document.getElementById('shopExchangeList');
        if (!exBox || !SHOP_DATA) return;
        var ex = SHOP_DATA.exchange || [];
        exBox.innerHTML = ex.length
            ? ex.map(function(it) {
                var id = it.id || '';
                var theme = 'is-ticket';
                var icon = '♻️';
                var btnClass = 'mint';
                var badgeHtml = '<span class="ex-badge safe">확정 교환</span>';
                if (id === 'shard_to_eunchong') {
                    theme = 'is-shard';
                    icon = '✨';
                    btnClass = 'primary';
                    badgeHtml = '<span class="ex-badge safe">확정 · 10→1</span>';
                } else if (id === 'eunchong_to_shard') {
                    theme = 'is-risky';
                    icon = '🎲';
                    btnClass = 'danger';
                    badgeHtml = '<span class="ex-badge risk">성공 ' + (it.success_pct || 60) + '% · 실패 시 소멸</span>';
                } else if (id === 'ilbang_to_eunchong') {
                    theme = 'is-ticket';
                    icon = '🎟';
                    btnClass = 'mint';
                    badgeHtml = '<span class="ex-badge safe">확정 교환</span>';
                }

                var costChips = (it.cost || []).map(function(c) {
                    var ok = (c.have || 0) >= (c.qty || 0);
                    var haveTxt = (c.have || 0) + (c.max ? '/' + c.max : '');
                    return '<div class="ex-chip ' + (ok ? 'ok' : 'lack') + '">'
                        + '<div class="lab">재료 · ' + esc(c.name) + '</div>'
                        + '<div class="val">' + (c.qty || 0) + '개 필요</div>'
                        + '<div class="sub">' + (ok ? '보유 ' + haveTxt : '부족 · 보유 ' + haveTxt) + '</div>'
                        + '</div>';
                }).join('');
                var costBlock = (it.cost || []).length > 1
                    ? '<div class="ex-cost-stack">' + costChips + '</div>'
                    : costChips;

                var reward = it.reward || {};
                var rewardHave = (reward.have || 0) + (reward.max ? '/' + reward.max : '');
                var rewardChip = '<div class="ex-chip ok">'
                    + '<div class="lab">획득 · ' + esc(reward.name || '') + '</div>'
                    + '<div class="val">+' + (reward.qty || 1) + '개</div>'
                    + '<div class="sub">보유 ' + rewardHave + '</div>'
                    + '</div>';

                var barHtml = '';
                var shardCost = (it.cost || []).find(function(c) { return c.name === '은총조각'; });
                var shardReward = (reward.name === '은총조각') ? reward : null;
                var shardBar = shardCost || shardReward;
                if (shardBar && shardBar.max) {
                    var pct = Math.max(0, Math.min(100, Math.round(((shardBar.have || 0) / shardBar.max) * 100)));
                    barHtml = '<div class="ex-bar-wrap"><div class="ex-bar-meta"><span>은총조각</span><span>' + (shardBar.have || 0) + ' / ' + shardBar.max + '</span></div>'
                        + '<div class="ex-bar"><i style="width:' + pct + '%"></i></div></div>';
                }

                var disabled = !it.can_exchange;
                var btnLabel = it.fixed_times === 1 ? '1개 교환하기' : '교환하기';
                if (it.risky) btnLabel = '도전 교환하기';

                return '<div class="ex-card ' + theme + (disabled ? ' is-disabled' : '') + '">'
                    + '<div class="ex-head"><div class="ex-icon">' + icon + '</div><div class="ex-titles">'
                    + '<div class="ex-title">' + esc(it.title || '교환') + '</div>'
                    + '<div class="ex-desc">' + esc(it.desc || '') + '</div>'
                    + badgeHtml
                    + '</div></div>'
                    + '<div class="ex-flow">' + costBlock + '<div class="ex-arrow">→</div>' + rewardChip + '</div>'
                    + barHtml
                    + '<div class="ex-actions"><button type="button" class="ex-btn ' + btnClass + '"'
                    + (disabled ? ' disabled' : '')
                    + ' data-ex-id="' + esc(id) + '"'
                    + ' data-ex-max="' + (it.max_times || 0) + '"'
                    + ' data-ex-title="' + esc(it.title || '교환') + '"'
                    + ' data-ex-fixed="' + (it.fixed_times || 0) + '"'
                    + ' data-ex-risky="' + (it.risky ? '1' : '0') + '">'
                    + btnLabel + '</button></div>'
                    + '</div>';
            }).join('')
            : '<div class="shop-empty">교환 가능한 레시피가 없어요.</div>';
    }

    function bindItemShopEvents() {
        var exBox = document.getElementById('shopExchangeList');
        if (exBox && !exBox._bound) {
            exBox._bound = true;
            exBox.addEventListener('click', function(e) {
                var btn = e.target.closest('button[data-ex-id]');
                if (!btn || btn.disabled) return;
                openItemExchange(
                    btn.getAttribute('data-ex-id'),
                    parseInt(btn.getAttribute('data-ex-max'), 10) || 1,
                    btn.getAttribute('data-ex-title') || '교환',
                    parseInt(btn.getAttribute('data-ex-fixed'), 10) || 0,
                    btn.getAttribute('data-ex-risky') === '1'
                );
            });
        }
    }

    function openShopModal(name) {
        document.getElementById('modal-' + name).classList.add('open');
    }
    function closeShopModal(name) {
        document.getElementById('modal-' + name).classList.remove('open');
    }
    function closeShopModalBg(e, name) {
        if (e.target.id === 'modal-' + name) closeShopModal(name);
    }

    function openItemExchange(id, maxTimes, title, fixedTimes, risky) {
        var fixed = Math.max(0, parseInt(fixedTimes, 10) || 0);
        selectedExchange = {
            id: id,
            max: Math.max(1, maxTimes || 1),
            fixed: fixed,
            risky: !!risky,
            recipe: (SHOP_DATA.exchange || []).find(function(x) { return x.id === id; }) || null
        };
        document.getElementById('itemExchangeTitle').textContent = title || '은총 교환';
        document.getElementById('itemExchangeTimes').value = '1';
        document.getElementById('itemExchangeTimes').max = String(fixed === 1 ? 1 : selectedExchange.max);
        var timesWrap = document.getElementById('itemExchangeTimesWrap');
        if (timesWrap) {
            timesWrap.style.display = (fixed === 1) ? 'none' : '';
        }
        updateItemExchangeHint();
        openShopModal('itemExchange');
    }

    function updateItemExchangeHint() {
        if (!selectedExchange) return;
        var times = parseInt(document.getElementById('itemExchangeTimes').value, 10) || 1;
        if (selectedExchange.fixed === 1) times = 1;
        if (times > selectedExchange.max) times = selectedExchange.max;
        if (times < 1) times = 1;
        var recipe = selectedExchange.recipe;
        if (selectedExchange.id === 'shard_to_eunchong') {
            var rate = 10;
            if (recipe && recipe.cost && recipe.cost[0]) rate = recipe.cost[0].qty || 10;
            var have = (recipe && recipe.cost && recipe.cost[0]) ? (recipe.cost[0].have || 0) : 0;
            var maxHold = (recipe && recipe.cost && recipe.cost[0] && recipe.cost[0].max) ? recipe.cost[0].max : 100;
            document.getElementById('itemExchangeHint').textContent =
                '은총조각 ' + rate + '개 → 은총 1개\n보유 조각 ' + have + '/' + maxHold + ' · 1개씩 교환';
            return;
        }
        if (selectedExchange.id === 'eunchong_to_shard') {
            var rate2 = 10;
            var pct = 60;
            if (recipe && recipe.reward) rate2 = recipe.reward.qty || 10;
            if (recipe && recipe.success_pct) pct = recipe.success_pct;
            var haveE = (recipe && recipe.cost && recipe.cost[0]) ? (recipe.cost[0].have || 0) : 0;
            var haveS = (recipe && recipe.reward) ? (recipe.reward.have || 0) : 0;
            var maxS = (recipe && recipe.reward && recipe.reward.max) ? recipe.reward.max : 100;
            document.getElementById('itemExchangeHint').textContent =
                '은총 1개 → 은총조각 ' + rate2 + '개\n성공 ' + pct + '% · 실패 시 은총 소멸\n은총 ' + haveE + '개 · 조각 ' + haveS + '/' + maxS;
            return;
        }
        document.getElementById('itemExchangeHint').textContent =
            '일방신청권 ' + (5 * times) + '개 + 일방연장권 ' + (5 * times) + '개 → 은총 ' + times + '개\n최대 ' + selectedExchange.max + '회 가능';
    }

    function afterShopAction(j) {
        showToast(j.data || '완료');
        if (j.member) applyMember(j.member);
        if (j.shop) {
            SHOP_DATA = j.shop;
            renderItemShop();
        }
        closeShopModal('itemExchange');
    }

    function submitItemExchange() {
        if (!selectedExchange) return;
        if (selectedExchange.risky) {
            var pct = (selectedExchange.recipe && selectedExchange.recipe.success_pct)
                ? selectedExchange.recipe.success_pct
                : 60;
            if (!confirm('성공 ' + pct + '% · 실패 시 은총이 소멸합니다.\n정말 교환할까요?')) return;
        }
        var times = selectedExchange.fixed === 1
            ? '1'
            : (document.getElementById('itemExchangeTimes').value.trim() || '1');
        ajax('item_exchange', { exchange_id: selectedExchange.id, times: times }, afterShopAction);
    }

    document.getElementById('itemExchangeTimes').addEventListener('input', updateItemExchangeHint);
    document.getElementById('btnRefresh').addEventListener('click', function() {
        ajax('item_shop', null, function(j) {
            if (j.member) applyMember(j.member);
            if (j.shop) {
                SHOP_DATA = j.shop;
                renderItemShop();
            }
        });
    });

    renderItemShop();
    bindItemShopEvents();

    window.closeShopModal = closeShopModal;
    window.closeShopModalBg = closeShopModalBg;
    window.submitItemExchange = submitItemExchange;
})();
</script>
<?php } ?>
</body>
</html>
