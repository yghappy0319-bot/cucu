<?php
/**
 * 아이템 상점 웹 (지갑 code 인증 공유)
 *
 * URL:
 *   /page/item_shop.php?code=XXXX
 *   /api/game/item_shop_web.php?code=XXXX
 *
 * AJAX:
 *   action=item_shop       — 목록 새로고침
 *   action=item_buy        — 구매
 *   action=item_sell_quote — 판매 견적
 *   action=item_sell       — 판매
 */

define('WALLET_LIB_ONLY', true);
require_once __DIR__ . '/wallet_web.php';

$shop_actions = ['item_shop', 'item_buy', 'item_sell', 'item_sell_quote'];
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

    if ($req_action === 'item_buy') {
        $item_name = isset($_REQUEST['item_name']) ? trim($_REQUEST['item_name']) : '';
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        if (!function_exists('아이템_상점구매_실행')) {
            wallet_json(['ok' => false, 'data' => '상점 기능을 불러올 수 없습니다.']);
        }
        $result = 아이템_상점구매_실행($nick, $member, $item_name, $qty);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['msg'] ?? '구매 실패']);
        }
        wallet_본방알림_등록($result['msg'], $nick);
        $fresh = wallet_auth($req_code) ?: $member;
        wallet_json([
            'ok' => true,
            'data' => $result['msg'],
            'member' => wallet_member_payload($fresh, $nick),
            'shop' => wallet_item_shop_data($fresh),
        ]);
    }

    if ($req_action === 'item_sell_quote') {
        $item_name = isset($_REQUEST['item_name']) ? trim($_REQUEST['item_name']) : '';
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        if (!function_exists('아이템_상점판매_견적')) {
            wallet_json(['ok' => false, 'data' => '상점 기능을 불러올 수 없습니다.']);
        }
        $quote = 아이템_상점판매_견적($member, $item_name, $qty);
        if (empty($quote['ok'])) {
            wallet_json(['ok' => false, 'data' => $quote['msg'] ?? '견적 실패']);
        }
        wallet_json(['ok' => true, 'type' => 'item_sell_quote', 'quote' => $quote]);
    }

    if ($req_action === 'item_sell') {
        $item_name = isset($_REQUEST['item_name']) ? trim($_REQUEST['item_name']) : '';
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        if (!function_exists('아이템_상점판매_실행')) {
            wallet_json(['ok' => false, 'data' => '상점 기능을 불러올 수 없습니다.']);
        }
        $result = 아이템_상점판매_실행($nick, $member, $item_name, $qty);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['msg'] ?? '판매 실패']);
        }
        wallet_본방알림_등록($result['msg'], $nick);
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
$shop_member = null;
$shop_data = null;

if (!empty($GLOBALS['wallet_preauth']['row']) && !empty($GLOBALS['wallet_preauth']['code'])) {
    $shop_code = (string)$GLOBALS['wallet_preauth']['code'];
    $row = wallet_member_row_enrich($GLOBALS['wallet_preauth']['row'], $shop_code);
    $nick = wallet_nick_from_row($row);
    if ($nick === '') {
        $nick = trim((string)($row['name'] ?? ''));
    }
    wallet_코드_쿠키_저장($shop_code);
    wallet_api_load($nick);
    $shop_need_code = false;
    $shop_member = wallet_member_payload($row, $nick);
    $shop_data = wallet_item_shop_data($row);
} elseif ($shop_code !== '') {
    wallet_odd_even_includes();
    $row = wallet_game_auth_row($shop_code);
    if ($row) {
        $row = wallet_member_row_enrich($row, $shop_code);
        $nick = wallet_nick_from_row($row);
        if ($nick === '') {
            $nick = trim((string)($row['name'] ?? ''));
        }
        wallet_코드_쿠키_저장($shop_code);
        wallet_api_load($nick);
        $shop_need_code = false;
        $shop_member = wallet_member_payload($row, $nick);
        $shop_data = wallet_item_shop_data($row);
    }
}

$shop_wallet_href = '/page/wallet.php' . wallet_code_query($shop_code);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0c1222">
    <title>아이템 상점</title>
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
        .shop-tabs { display: flex; gap: 8px; margin-bottom: 10px; }
        .shop-tabs button {
            flex: 1;
            border: 1px solid var(--line);
            background: var(--card2);
            color: var(--muted);
            border-radius: 10px;
            padding: 10px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .shop-tabs button.active {
            color: var(--text);
            border-color: rgba(94,234,212,0.35);
            background: rgba(94,234,212,0.08);
        }
        .shop-panel { display: none; }
        .shop-panel.active { display: block; }
        .hint { font-size: 0.76rem; color: var(--muted); margin-bottom: 8px; line-height: 1.45; }
        .item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 10px;
            margin-bottom: 8px;
        }
        .item-row .name { font-weight: 700; font-size: 0.88rem; }
        .item-row .meta { font-size: 0.74rem; color: var(--muted); margin-top: 2px; }
        .item-row .price { color: var(--gold); font-weight: 800; font-size: 0.84rem; text-align: right; white-space: nowrap; }
        .item-row .actions { display: flex; gap: 6px; margin-top: 6px; }
        .item-btn {
            border: none;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.76rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }
        .item-btn.buy { background: rgba(94,234,212,0.18); color: var(--mint); }
        .item-btn.sell { background: rgba(245,197,66,0.15); color: var(--gold); }
        .item-btn:disabled { opacity: 0.45; cursor: not-allowed; }
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
    <h1>🛍️ 아이템 상점</h1>

    <?php if ($shop_need_code) { ?>
        <div class="card lock-box">
            <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
            <strong>코드를 확인해주세요</strong><br>
            상황실에서 <code>.지갑</code> 링크로 접속해 주세요.<br>
            <span style="opacity:0.65;font-size:0.8rem;">예) /page/item_shop.php?code=XXXX</span>
        </div>
    <?php } else { ?>
        <a class="back-link" href="<?php echo htmlspecialchars($shop_wallet_href, ENT_QUOTES, 'UTF-8'); ?>">← 내 지갑으로</a>

        <div class="card">
            <div class="top-row">
                <div class="nick" id="sNick"><?php echo htmlspecialchars($shop_member['nick'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="point" id="sPoint"><?php echo htmlspecialchars($shop_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</div>
            </div>
            <div class="shop-tabs">
                <button type="button" class="active" id="shopTabBuy">구매</button>
                <button type="button" id="shopTabSell">판매</button>
            </div>
            <div class="shop-panel active" id="shopPanelBuy">
                <div class="hint">게임냥으로 구매 · 10개 미만이면 1% 추가</div>
                <div id="shopBuyList"></div>
            </div>
            <div class="shop-panel" id="shopPanelSell">
                <div class="hint">
                    보유 아이템 판매 · 수수료 <?php echo (int)($shop_data['sell']['fee_rate'] ?? 30); ?>%
                    (지호 사용 중 <?php echo (int)($shop_data['sell']['jiho_fee_rate'] ?? 15); ?>%)
                </div>
                <div id="shopSellList"></div>
            </div>
            <div class="btn-row">
                <button type="button" class="btn" id="btnRefresh">새로고침</button>
            </div>
        </div>

        <div class="modal-overlay" id="modal-itemBuy" onclick="closeShopModalBg(event, 'itemBuy')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 id="itemBuyTitle">아이템 구매</h3>
                <div class="form-field">
                    <label for="itemBuyQty">수량</label>
                    <input type="number" id="itemBuyQty" min="1" step="1" value="1">
                </div>
                <div class="quote-box" id="itemBuyHint">게임냥이 차감됩니다.</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeShopModal('itemBuy')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitItemBuy()">구매하기</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-itemSell" onclick="closeShopModalBg(event, 'itemSell')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 id="itemSellTitle">아이템 판매</h3>
                <div class="form-field">
                    <label for="itemSellQty">수량</label>
                    <input type="number" id="itemSellQty" min="1" step="1" value="1">
                </div>
                <div class="quote-box" id="itemSellQuote">견적을 불러옵니다.</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeShopModal('itemSell')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitItemSell()">판매하기</button>
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
    var SHOP_DATA = <?php echo json_encode($shop_data ?: ['buy'=>[], 'sell'=>['items'=>[], 'fee_rate'=>30, 'jiho_fee_rate'=>15], 'inventory'=>[]], JSON_UNESCAPED_UNICODE); ?>;
    var selectedBuyItem = null;
    var selectedSellItem = null;

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

    function setShopTab(tab) {
        document.getElementById('shopTabBuy').classList.toggle('active', tab === 'buy');
        document.getElementById('shopTabSell').classList.toggle('active', tab === 'sell');
        document.getElementById('shopPanelBuy').classList.toggle('active', tab === 'buy');
        document.getElementById('shopPanelSell').classList.toggle('active', tab === 'sell');
    }

    function renderItemShop() {
        var buyBox = document.getElementById('shopBuyList');
        var sellBox = document.getElementById('shopSellList');
        if (!buyBox || !sellBox || !SHOP_DATA) return;

        var buy = SHOP_DATA.buy || [];
        buyBox.innerHTML = buy.length
            ? buy.map(function(it) {
                var disabled = it.buyable === false;
                return '<div class="item-row"><div><div class="name">' + esc(it.name) + '</div><div class="meta">단가 ' + esc(it.price_fmt) + '</div><div class="actions"><button type="button" class="item-btn buy"' + (disabled ? ' disabled' : '') + ' data-buy-name="' + esc(it.name) + '" data-buy-price="' + esc(it.price_fmt) + '">구매</button></div></div><div class="price">' + esc(it.price_fmt) + '</div></div>';
            }).join('')
            : '<div class="shop-empty">구매 가능한 아이템이 없어요.</div>';

        var inv = SHOP_DATA.inventory || [];
        sellBox.innerHTML = inv.length
            ? inv.map(function(it) {
                var meta = '보유 ' + it.count + '개';
                if (it.sell_price_fmt) {
                    meta += ' · 1개당 ' + it.sell_price_fmt;
                    if (it.count > 1 && it.sell_all_fmt) meta += ' · 전량 ' + it.sell_all_fmt;
                }
                var priceHtml = it.sell_price_fmt ? '<div class="price">' + esc(it.sell_price_fmt) + '</div>' : '';
                return '<div class="item-row"><div><div class="name">' + esc(it.name) + '</div><div class="meta">' + esc(meta) + '</div><div class="actions"><button type="button" class="item-btn sell" data-sell-name="' + esc(it.name) + '" data-sell-max="' + it.count + '">판매</button></div></div>' + priceHtml + '</div>';
            }).join('')
            : '<div class="shop-empty">판매할 보유 아이템이 없어요.</div>';
    }

    function bindItemShopEvents() {
        var buyBox = document.getElementById('shopBuyList');
        var sellBox = document.getElementById('shopSellList');
        if (buyBox && !buyBox._bound) {
            buyBox._bound = true;
            buyBox.addEventListener('click', function(e) {
                var btn = e.target.closest('button[data-buy-name]');
                if (!btn || btn.disabled) return;
                openItemBuy(btn.getAttribute('data-buy-name'), btn.getAttribute('data-buy-price'));
            });
        }
        if (sellBox && !sellBox._bound) {
            sellBox._bound = true;
            sellBox.addEventListener('click', function(e) {
                var btn = e.target.closest('button[data-sell-name]');
                if (!btn) return;
                openItemSell(btn.getAttribute('data-sell-name'), parseInt(btn.getAttribute('data-sell-max'), 10) || 1);
            });
        }
        document.getElementById('shopTabBuy').addEventListener('click', function() { setShopTab('buy'); });
        document.getElementById('shopTabSell').addEventListener('click', function() { setShopTab('sell'); });
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

    function openItemBuy(name, priceFmt) {
        selectedBuyItem = name;
        document.getElementById('itemBuyTitle').textContent = name + ' 구매';
        document.getElementById('itemBuyQty').value = '1';
        document.getElementById('itemBuyHint').textContent = '단가 ' + priceFmt + ' · 10개 미만이면 1% 추가';
        openShopModal('itemBuy');
    }

    function openItemSell(name, maxCount) {
        selectedSellItem = { name: name, max: maxCount };
        document.getElementById('itemSellTitle').textContent = name + ' 판매';
        document.getElementById('itemSellQty').value = '1';
        document.getElementById('itemSellQty').max = String(maxCount);
        updateItemSellQuote();
        openShopModal('itemSell');
    }

    function updateItemSellQuote() {
        if (!selectedSellItem) return;
        var qty = parseInt(document.getElementById('itemSellQty').value, 10) || 1;
        ajax('item_sell_quote', { item_name: selectedSellItem.name, qty: String(qty) }, function(j) {
            var q = j.quote;
            document.getElementById('itemSellQuote').textContent =
                '실수령 ' + q.실수령_fmt + ' · 수수료 ' + q.수수료_fmt + ' (' + q.수수료율 + '%)';
        });
    }

    function afterShopAction(j) {
        showToast(j.data || '완료');
        if (j.member) applyMember(j.member);
        if (j.shop) {
            SHOP_DATA = j.shop;
            renderItemShop();
        }
        closeShopModal('itemBuy');
        closeShopModal('itemSell');
    }

    function submitItemBuy() {
        if (!selectedBuyItem) return;
        ajax('item_buy', { item_name: selectedBuyItem, qty: document.getElementById('itemBuyQty').value.trim() || '1' }, afterShopAction);
    }

    function submitItemSell() {
        if (!selectedSellItem) return;
        ajax('item_sell', { item_name: selectedSellItem.name, qty: document.getElementById('itemSellQty').value.trim() || '1' }, afterShopAction);
    }

    document.getElementById('itemSellQty').addEventListener('input', function() {
        clearTimeout(updateItemSellQuote._t);
        updateItemSellQuote._t = setTimeout(updateItemSellQuote, 250);
    });

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
    window.submitItemBuy = submitItemBuy;
    window.submitItemSell = submitItemSell;
})();
</script>
<?php } ?>
</body>
</html>
