<?php
/**
 * 아이템 주식 거래소 (A방식)
 * URL: /page/item_exchange.php?code=XXXX
 * · 가방 바로가기 「아이템 구매」
 * · 시세 설정 영역은 민호만 (접기/펼치기)
 * · 매수/매도 = .주가/.매수/.매도 와 동일 공식
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
include_once __DIR__ . '/_item_stock_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_stock_market.inc.php';
if (!function_exists('item_bag_qty') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php';
}

$auth_code = '';
if (!empty($_REQUEST['wallet_code'])) {
    $auth_code = trim((string)$_REQUEST['wallet_code']);
} elseif (!empty($_REQUEST['code'])) {
    $auth_code = trim((string)$_REQUEST['code']);
}
$회원 = item_stock_auth($auth_code);
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_시세관리자인가($닉);
$단위 = '냥';

$json_out = static function (array $data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

$member_row = null;
$본방결제 = false;
if ($로그인) {
    if (function_exists('아이템주식_본방결제모드_설정')) {
        아이템주식_본방결제모드_설정($닉);
    }
    $본방결제 = function_exists('아이템주식_본방결제모드인가') && 아이템주식_본방결제모드인가();
    $midx = (int)($회원['midx'] ?? 0);
    $member_row = @db_select("
      SELECT idx, name, CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint
      FROM tb_member
      WHERE idx = {$midx}
      LIMIT 1
    ");
    if (empty($member_row['idx'])) {
        $member_row = @db_select("
          SELECT idx, name, CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint
          FROM tb_member
          WHERE name = '" . addslashes($닉) . "'
          LIMIT 1
        ");
    }
}

$point_fmt = static function ($pt) use ($단위) {
    return function_exists('아이템주식_표시')
        ? 아이템주식_표시($pt, $단위)
        : ((string)$pt . $단위);
};

/** 상단 잔액 표시 — 게임포기면 본방냥 */
$balance_fmt = static function ($row) use ($본방결제, $point_fmt) {
    if ($본방결제) {
        $np = (float)($row['newpoint'] ?? 0);
        if (function_exists('newpoint표시')) {
            return newpoint표시($np) . '본방냥';
        }
        return number_format($np, 1) . '본방냥';
    }
    return $point_fmt($row['point'] ?? 0);
};

$balance_payload = static function ($row) use ($본방결제, $point_fmt, $balance_fmt) {
    $pay = $본방결제 ? 'newpoint' : 'point';
    return [
        'point' => function_exists('아이템주식_냥') ? 아이템주식_냥($row['point'] ?? 0) : (string)($row['point'] ?? 0),
        'point_fmt' => $balance_fmt($row),
        'newpoint' => function_exists('아이템주식_냥') ? 아이템주식_냥($row['newpoint'] ?? 0) : (string)($row['newpoint'] ?? 0),
        'pay_unit' => $pay,
        'balance_unit' => $본방결제 ? '본방냥' : '냥',
    ];
};

// —— AJAX ——
$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
if ($action !== '') {
    if (!$로그인) {
        $json_out(['ok' => false, 'data' => '코드 인증이 필요합니다.']);
    }
    if (empty($member_row['idx'])) {
        $json_out(['ok' => false, 'data' => '회원 정보를 찾을 수 없어요.']);
    }

    if ($action === 'status') {
        if (function_exists('아이템주식_틱_스냅샷_전부')) {
            아이템주식_틱_스냅샷_전부(180);
        }
        $json_out(array_merge([
            'ok' => true,
            'type' => 'status',
            'nick' => $닉,
            'exchange' => 아이템주식_거래소_데이터($member_row, $단위),
        ], $balance_payload($member_row)));
    }

    // 가벼운 폴링: 시세·스파크·최근매수·투자내역
    if ($action === 'poll') {
        $json_out([
            'ok' => true,
            'type' => 'poll',
            'poll' => 아이템주식_거래소_폴링데이터($단위, $member_row),
        ]);
    }

    if ($action === 'chart') {
        $item = isset($_REQUEST['item_name']) ? trim((string)$_REQUEST['item_name']) : '';
        $hours = isset($_REQUEST['hours']) ? (int)$_REQUEST['hours'] : 72;
        if ($item === '') {
            $json_out(['ok' => false, 'data' => '아이템을 선택해 주세요.']);
        }
        $chart = 아이템주식_차트데이터($item, $hours, $단위);
        if (empty($chart['ok'])) {
            $json_out(['ok' => false, 'data' => $chart['msg'] ?? '차트 없음']);
        }
        $json_out([
            'ok' => true,
            'type' => 'chart',
            'chart' => $chart,
        ]);
    }

    if ($action === 'set_base') {
        if (!$준호) {
            $json_out(['ok' => false, 'data' => '시세 설정은 민호만 변경할 수 있어요.']);
        }
        $mode = isset($_REQUEST['mode']) ? trim((string)$_REQUEST['mode']) : 'snapshot';
        $custom = isset($_REQUEST['custom']) ? trim((string)$_REQUEST['custom']) : '0';
        // 숫자·콤마만 허용
        $custom = preg_replace('/[^\d]/', '', $custom) ?: '0';
        $supply_curve = !empty($_REQUEST['supply_curve']) && (string)$_REQUEST['supply_curve'] !== '0';
        $supply_ref = isset($_REQUEST['supply_ref']) ? (int)$_REQUEST['supply_ref'] : 100;
        $spread = isset($_REQUEST['spread']) ? (int)$_REQUEST['spread'] : 20;
        $price_mult = isset($_REQUEST['price_mult']) ? (float)$_REQUEST['price_mult'] : 1.0;
        $saved = 아이템주식_기준설정_저장($mode, $custom, $닉, [
            'supply_curve' => $supply_curve,
            'supply_ref' => $supply_ref,
            'spread' => $spread,
            'price_mult' => $price_mult,
        ]);
        if (empty($saved['ok'])) {
            $json_out(['ok' => false, 'data' => $saved['msg'] ?? '저장 실패']);
        }
        $json_out(array_merge([
            'ok' => true,
            'data' => $saved['msg'] . "\n모드: " . ($saved['cfg']['mode'] ?? $mode),
            'exchange' => 아이템주식_거래소_데이터($member_row, $단위),
        ], $balance_payload($member_row)));
    }

    if ($action === 'buy') {
        $item = isset($_REQUEST['item_name']) ? trim((string)$_REQUEST['item_name']) : '';
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        $결과 = 아이템주식_매수_실행($닉, $member_row, $item, $qty);
        if (empty($결과['ok'])) {
            $json_out(['ok' => false, 'data' => $결과['msg'] ?? '매수 실패']);
        }
        $fresh = @db_select("SELECT idx, name, CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE idx = " . (int)$member_row['idx'] . " LIMIT 1");
        $json_out(array_merge([
            'ok' => true,
            'data' => $결과['msg'],
            'exchange' => 아이템주식_거래소_데이터($fresh ?: $member_row, $단위),
        ], $balance_payload($fresh ?: $member_row)));
    }

    if ($action === 'sell') {
        $item = isset($_REQUEST['item_name']) ? trim((string)$_REQUEST['item_name']) : '';
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        $결과 = 아이템주식_매도_실행($닉, $member_row, $item, $qty);
        if (empty($결과['ok'])) {
            $json_out(['ok' => false, 'data' => $결과['msg'] ?? '매도 실패']);
        }
        $fresh = @db_select("SELECT idx, name, CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE idx = " . (int)$member_row['idx'] . " LIMIT 1");
        $json_out(array_merge([
            'ok' => true,
            'data' => $결과['msg'],
            'exchange' => 아이템주식_거래소_데이터($fresh ?: $member_row, $단위),
        ], $balance_payload($fresh ?: $member_row), [
            'pay_unit' => (string)($결과['pay_unit'] ?? ($본방결제 ? 'newpoint' : 'point')),
        ]));
    }

    $json_out(['ok' => false, 'data' => '알 수 없는 요청입니다.']);
}

$초기시세 = ($로그인 && $member_row) ? 아이템주식_거래소_데이터($member_row, $단위) : null;
$초기포인트 = ($로그인 && $member_row) ? $balance_fmt($member_row) : '';
$초기결제단위 = $본방결제 ? 'newpoint' : 'point';
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>아이템 구매 · 거래소</title>
<style>
:root {
  --ink: #e8e4dc;
  --muted: #9a9388;
  --ok: #6cbc6a;
  --err: #d16b6b;
  --buy: #5b9fd4;
  --sell: #d4a05b;
  --line: rgba(255,255,255,0.12);
  --accent: #c9a96e;
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #141820 0%, #1a1f28 50%, #12151a 100%);
  color: var(--ink);
  font-family: 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
  font-size: 15px;
}
.wrap { max-width: 720px; margin: 0 auto; padding: 18px 14px 100px; }
.page-title-row {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  margin: 0 0 6px;
}
.page-title-row h1 { margin: 0; flex: 1; min-width: 0; }
.btn-wallet {
  flex-shrink: 0;
  display: inline-flex; align-items: center; gap: 5px;
  border: 1px solid rgba(201,169,110,0.45);
  background: rgba(201,169,110,0.14);
  color: var(--accent);
  text-decoration: none;
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 0.82rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  -webkit-tap-highlight-color: transparent;
}
.btn-wallet:active { transform: scale(0.97); opacity: 0.92; }
h1 { margin: 0 0 6px; font-size: 1.4rem; color: var(--accent); letter-spacing: -0.02em; }
.sub { margin: 0 0 16px; color: var(--muted); font-size: 0.88rem; line-height: 1.45; }
.card {
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px;
  margin-bottom: 12px;
}
.top-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 10px; }
.nick { font-weight: 800; font-size: 1.05rem; }
.point { color: var(--accent); font-weight: 700; font-variant-numeric: tabular-nums; }
.meta-line { color: var(--muted); font-size: 0.82rem; margin-bottom: 12px; line-height: 1.4; }
.pf-box {
  margin: 0 0 14px;
  padding: 12px 12px 10px;
  border-radius: 12px;
  border: 1px solid rgba(201,169,110,0.22);
  background:
    radial-gradient(ellipse at 0% 0%, rgba(110,207,142,0.08) 0%, transparent 55%),
    radial-gradient(ellipse at 100% 0%, rgba(209,107,107,0.07) 0%, transparent 50%),
    rgba(255,255,255,0.03);
}
.pf-box.empty { opacity: 0.75; }
.pf-head {
  display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
  margin-bottom: 8px;
}
.pf-head .pf-title { font-size: 0.78rem; font-weight: 800; color: var(--muted); letter-spacing: 0.04em; }
.pf-head .pf-qty { font-size: 0.72rem; color: var(--muted); }
.pf-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.pf-cell {
  min-width: 0;
  padding: 8px 8px 7px;
  border-radius: 10px;
  background: rgba(0,0,0,0.22);
  border: 1px solid rgba(255,255,255,0.05);
}
.pf-cell .lab { display: block; font-size: 0.68rem; color: var(--muted); margin-bottom: 3px; }
.pf-cell .val {
  display: block; font-size: 0.86rem; font-weight: 800; letter-spacing: -0.02em;
  word-break: break-all; line-height: 1.25;
}
.pf-cell.pnl .val.up { color: #6ecf8e; }
.pf-cell.pnl .val.down { color: #d16b6b; }
.pf-cell.pnl .pct {
  display: block; margin-top: 2px; font-size: 0.72rem; font-weight: 700;
}
.pf-cell.pnl .pct.up { color: #6ecf8e; }
.pf-cell.pnl .pct.down { color: #d16b6b; }
.pf-list { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
.pf-row {
  display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
  font-size: 0.74rem; color: var(--muted); line-height: 1.35;
}
.pf-row .n { color: var(--ink); font-weight: 700; }
.pf-row .p.up { color: #6ecf8e; font-weight: 700; }
.pf-row .p.down { color: #d16b6b; font-weight: 700; }
.pf-hint { margin-top: 6px; font-size: 0.68rem; color: var(--muted); opacity: 0.85; }
.tabs { display: flex; gap: 6px; margin-bottom: 12px; }
.tabs button {
  flex: 1; border: 1px solid var(--line); background: rgba(255,255,255,0.04);
  color: var(--muted); border-radius: 10px; padding: 10px; font-weight: 700; cursor: pointer;
}
.tabs button.active { color: var(--ink); border-color: var(--accent); background: rgba(201,169,110,0.12); }
.panel { display: none; }
.panel.active { display: block; }
.hint { color: var(--muted); font-size: 0.8rem; margin-bottom: 10px; }
.item-row {
  display: flex; justify-content: space-between; gap: 10px; align-items: flex-start;
  padding: 12px 0; border-bottom: 1px solid var(--line);
}
.item-row:last-child { border-bottom: none; }
.item-row .item-main { flex: 1; min-width: 0; }
.item-row .name-row {
  display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 10px;
  margin-bottom: 6px;
}
.item-row .name { font-weight: 700; margin: 0; }
.item-row .meta { color: var(--muted); font-size: 0.78rem; margin-top: 3px; line-height: 1.35; }
.item-row .price { font-weight: 700; color: var(--accent); text-align: right; font-size: 0.9rem; white-space: nowrap; }
.item-row .spark {
  display: block; width: 100%; max-width: 240px; height: 44px;
  margin: 0 0 8px; border-radius: 10px;
  background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
  cursor: pointer;
}
.item-row .spark.up { border-color: rgba(110,207,142,0.25); }
.item-row .spark.down { border-color: rgba(209,107,107,0.25); }
.item-row .spark-empty {
  height: 44px; max-width: 240px; margin: 0 0 8px; border-radius: 10px;
  background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.08);
  color: var(--muted); font-size: 0.72rem; display: flex; align-items: center; justify-content: center;
}
.item-row .last-buy {
  font-size: 0.75rem; color: var(--muted); line-height: 1.2; font-weight: 500;
}
.item-row .last-buy b { color: #9fd0f5; font-weight: 700; }
.item-row .last-buy .qty { color: var(--accent); font-weight: 700; }
.item-row .last-buy .amt { color: #c9e6a8; font-weight: 700; }
.actions { margin-top: 6px; display: flex; gap: 6px; }
.item-btn {
  border: 0; border-radius: 8px; padding: 6px 12px; font-weight: 700; cursor: pointer; font-size: 0.82rem;
}
.item-btn.buy { background: rgba(91,159,212,0.25); color: #9fd0f5; }
.item-btn.sell { background: rgba(212,160,91,0.25); color: #f0c98a; }
.item-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.empty { color: var(--muted); text-align: center; padding: 24px 8px; }
.btn-row { margin-top: 12px; }
.btn {
  width: 100%; border: 1px solid var(--line); background: rgba(255,255,255,0.05);
  color: var(--ink); border-radius: 10px; padding: 12px; font-weight: 700; cursor: pointer;
}
.need, .deny {
  padding: 18px; border-radius: 14px; border: 1px solid var(--line);
  background: rgba(0,0,0,0.28); line-height: 1.5; color: var(--muted);
}
.deny { color: var(--err); border-color: rgba(209,107,107,0.35); }
.modal-overlay {
  display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55);
  align-items: flex-end; justify-content: center; z-index: 40; padding: 16px;
}
.modal-overlay.show { display: flex; }
.modal-box {
  width: 100%; max-width: 420px; background: #1c222c; border: 1px solid var(--line);
  border-radius: 16px; padding: 16px; margin-bottom: env(safe-area-inset-bottom, 0);
}
.modal-box h3 { margin: 0 0 12px; }
.form-field { margin-bottom: 10px; }
.form-field label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px; }
.form-field input {
  width: 100%; border: 1px solid var(--line); background: rgba(0,0,0,0.25);
  color: var(--ink); border-radius: 10px; padding: 10px 12px; font-size: 1rem;
}
.quote-box {
  background: rgba(255,255,255,0.04); border: 1px solid var(--line);
  border-radius: 10px; padding: 10px; font-size: 0.86rem; line-height: 1.45; margin-bottom: 12px;
  color: var(--muted); white-space: pre-wrap;
}
.modal-actions { display: flex; gap: 8px; }
.modal-actions button {
  flex: 1; border: 0; border-radius: 10px; padding: 12px; font-weight: 800; cursor: pointer;
}
.btn-cancel { background: rgba(255,255,255,0.08); color: var(--ink); }
.btn-confirm { background: var(--accent); color: #1a1408; }
.toast {
  position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%) translateY(20px);
  background: rgba(20,24,32,0.95); border: 1px solid var(--line); color: var(--ink);
  padding: 10px 14px; border-radius: 12px; opacity: 0; pointer-events: none;
  transition: .2s ease; z-index: 50; max-width: 90%; white-space: pre-wrap; font-size: 0.88rem;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.loading { opacity: 0.55; pointer-events: none; }
code { font-size: 0.85em; color: var(--accent); }
.test-card { padding-top: 10px; padding-bottom: 10px; }
.test-card-toggle {
  width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px;
  border: 0; background: transparent; color: inherit; padding: 4px 0; cursor: pointer; text-align: left;
}
.test-card-toggle h2 { margin: 0; font-size: 1rem; color: var(--accent); }
.test-card-toggle .chev {
  color: var(--muted); font-size: 0.85rem; transition: transform .2s ease; flex-shrink: 0;
}
.test-card.open .test-card-toggle .chev { transform: rotate(180deg); }
.test-card-body { display: none; margin-top: 10px; padding-top: 4px; border-top: 1px solid var(--line); }
.test-card.open .test-card-body { display: block; }
.test-card h2 { margin: 0 0 8px; font-size: 1rem; color: var(--accent); }
.test-modes { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
.test-modes label {
  display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px;
  border: 1px solid var(--line); border-radius: 10px; cursor: pointer; font-size: 0.86rem; line-height: 1.35;
}
.test-modes label.active { border-color: var(--accent); background: rgba(201,169,110,0.1); }
.test-modes input { margin-top: 3px; }
.compare { font-size: 0.8rem; color: var(--muted); line-height: 1.45; margin-bottom: 10px; }
.compare strong { color: var(--ink); font-weight: 700; }
.quick-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.quick-row button {
  border: 1px solid var(--line); background: rgba(255,255,255,0.05); color: var(--ink);
  border-radius: 8px; padding: 6px 10px; font-size: 0.78rem; font-weight: 700; cursor: pointer;
}
.btn-apply {
  width: 100%; margin-top: 10px; border: 0; border-radius: 10px; padding: 12px;
  background: var(--accent); color: #1a1408; font-weight: 800; cursor: pointer;
}
.warn-line { color: var(--err); font-size: 0.78rem; margin-top: 8px; line-height: 1.4; }
.chart-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
.chart-toolbar select, .chart-toolbar button {
  border: 1px solid var(--line); background: rgba(0,0,0,0.28); color: var(--ink);
  border-radius: 8px; padding: 8px 10px; font-size: 0.86rem;
}
.chart-toolbar button.active { border-color: var(--accent); color: var(--accent); }
.chart-meta { color: var(--muted); font-size: 0.8rem; line-height: 1.4; margin-bottom: 8px; }
.chart-wrap {
  position: relative; width: 100%; height: 220px;
  background: rgba(255,255,255,0.03); border: 1px solid var(--line); border-radius: 12px;
  overflow: hidden;
}
.chart-wrap canvas { width: 100%; height: 100%; display: block; }
.chart-empty { color: var(--muted); text-align: center; padding: 40px 12px; font-size: 0.88rem; }
</style>
</head>
<body>
<div class="wrap" id="app">
  <?php
    $wallet_q = ($code !== '') ? ('?code=' . rawurlencode($code)) : '';
    $wallet_href = '/page/wallet.php' . $wallet_q;
  ?>
  <div class="page-title-row">
    <h1>📈 아이템 구매</h1>
    <?php if ($로그인 && $code !== '') { ?>
    <a class="btn-wallet" href="<?= htmlspecialchars($wallet_href, ENT_QUOTES, 'UTF-8') ?>">🎒 지갑</a>
    <?php } ?>
  </div>
  <p class="sub">실제 주식형 · 매수 = 현재 시세 · 매도 수수료 20%(금고 10% · 로또 10%)<br>
  부루마블1등은 구매 불가 · 보유자만 본방냥 시총 10%로 판매<br>
  <?php if ($본방결제) { ?>
  <span style="color:var(--accent)">게임포기 · 시세·결제 = 본방냥</span><br>
  <?php } ?>
    은총은 권면가 × 2.5%(유통곡선) · 매도 한도: 생타 500→1개 · 1천→+2 · 1.5천→+3 (하루)<br>
    공커대실권은 개별기준가×활성쌍 · 공커(oneroom=2)만 구매 · 종류별 하루 10개(자정 초기화)<br>
    채팅 <code>.주가</code> · <code>.매수</code> · <code>.매도</code> 와 동일</p>

<?php if (!$로그인) { ?>
  <div class="need">코드로 접속해 주세요.<br><code>/page/item_exchange.php?code=XXXX</code><br>또는 가방 → 아이템 구매</div>
<?php } elseif (empty($member_row['idx'])) { ?>
  <div class="deny">회원 정보를 찾을 수 없어요.</div>
<?php } else {
  $base0 = $초기시세['base'] ?? 아이템주식_기준_메타($단위);
?>
  <?php if ($준호) { ?>
  <div class="card test-card" id="adminSettings">
    <button type="button" class="test-card-toggle" id="btnToggleSettings" aria-expanded="false" aria-controls="settingsBody">
      <h2>시세 설정 · 민호</h2>
      <span class="chev" aria-hidden="true">▼</span>
    </button>
    <div class="test-card-body" id="settingsBody">
    <p class="hint" style="margin-top:0">총게임냥 기준을 바꿔 아이템가가 바로 변하는지 확인합니다. 채팅 <code>.주가</code>에도 적용됩니다.</p>
    <div class="compare" id="baseCompare">
      스냅샷 <strong id="cmpSnap"><?= htmlspecialchars($base0['snapshot_fmt'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong><br>
      실시간 <strong id="cmpLive"><?= htmlspecialchars($base0['live_fmt'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong><br>
      적용중 <strong id="cmpActive"><?= htmlspecialchars($base0['active_fmt'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
      (<span id="cmpMode"><?= htmlspecialchars($base0['mode_label'] ?? '스냅샷(기존)', ENT_QUOTES, 'UTF-8') ?></span>)
    </div>
    <div class="test-modes" id="baseModes">
      <label class="<?= ($base0['mode'] ?? '') === 'snapshot' ? 'active' : '' ?>">
        <input type="radio" name="baseMode" value="snapshot" <?= ($base0['mode'] ?? '') === 'snapshot' ? 'checked' : '' ?>>
        <span><strong>스냅샷</strong><br>기존 시세기준_게임냥 (현재 기본)</span>
      </label>
      <label class="<?= ($base0['mode'] ?? '') === 'live' ? 'active' : '' ?>">
        <input type="radio" name="baseMode" value="live" <?= ($base0['mode'] ?? '') === 'live' ? 'checked' : '' ?>>
        <span><strong>실시간 SUM</strong><br>회원 point 합계 — 매수·매도 직후에도 분모가 움직임</span>
      </label>
      <label class="<?= ($base0['mode'] ?? '') === 'custom' ? 'active' : '' ?>">
        <input type="radio" name="baseMode" value="custom" <?= ($base0['mode'] ?? '') === 'custom' ? 'checked' : '' ?>>
        <span><strong>커스텀</strong><br>임의 총냥을 넣어 가격 변동 확인</span>
      </label>
    </div>
    <div class="form-field" id="customWrap" style="<?= ($base0['mode'] ?? '') === 'custom' ? '' : 'display:none' ?>">
      <label for="customTotal">커스텀 총게임냥 (숫자만)</label>
      <input type="text" id="customTotal" inputmode="numeric" autocomplete="off"
        value="<?= htmlspecialchars($base0['custom'] ?? '0', ENT_QUOTES, 'UTF-8') ?>"
        placeholder="예) 1000000000000">
      <div class="quick-row">
        <button type="button" id="btnHalf">×0.5</button>
        <button type="button" id="btnDouble">×2</button>
        <button type="button" id="btnUseSnap">=스냅샷</button>
        <button type="button" id="btnUseLive">=실시간</button>
      </div>
    </div>
    <button type="button" class="btn-apply" id="btnApplyBase">기준 적용 · 시세 다시 계산</button>
    <div class="warn-line" style="margin-top:14px;color:var(--muted)">—— 유통량 가격 변동 ——</div>
    <label class="test-modes" style="display:block;margin-top:8px">
      <span style="display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border:1px solid var(--line);border-radius:10px;cursor:pointer;font-size:0.86rem;line-height:1.35" id="supplyCurveLabel">
        <input type="checkbox" id="supplyCurve" <?= !empty($base0['supply_curve']) ? 'checked' : '' ?> style="margin-top:3px">
        <span><strong>유통량 곡선</strong><br>
          전 회원 보유 합이 많을수록 매수가↑ · 매도하면↓<br>
          배수 = 1 + (유통량 ÷ 기준개수)<br>
          <em style="color:#c9a96e">변경 후 반드시 「기준 적용」클릭 (자동저장 없음)</em></span>
      </span>
    </label>
    <div class="form-field" style="margin-top:8px">
      <label for="priceMult">전체 시세 배수 (1=그대로 · 2=두 배 · 0.5=반값)</label>
      <input type="number" id="priceMult" min="0.01" max="100" step="0.1"
        value="<?= htmlspecialchars((string)($base0['price_mult'] ?? 1), ENT_QUOTES, 'UTF-8') ?>">
      <div class="quick-row">
        <button type="button" id="btnPm1">1×</button>
        <button type="button" id="btnPm2">2×</button>
        <button type="button" id="btnPm3">3×</button>
        <button type="button" id="btnPm5">5×</button>
        <button type="button" id="btnPm10">10×</button>
      </div>
    </div>
    <div class="form-field" style="margin-top:8px">
      <label for="spreadPct">매도 수수료 % (기본 20 · 최대 50 · 0=수수료 없음)</label>
      <input type="number" id="spreadPct" min="0" max="50" step="1"
        value="<?= (int)($base0['spread'] ?? ($초기시세['spread_pct'] ?? 20)) ?>">
      <div class="hint" style="margin-top:4px">수수료의 절반→금고 · 절반→로또. 예) 20 → 매도 80% · 금고 10% · 로또 10%</div>
    </div>
    <div class="form-field" style="margin-top:8px">
      <label for="supplyRef">기준 개수 (이 개수면 가격 2배)</label>
      <input type="number" id="supplyRef" min="1" step="1"
        value="<?= (int)($base0['supply_ref'] ?? 100) ?>">
    </div>
    <div class="compare" id="supplyHint" style="margin-top:6px">
      <?= htmlspecialchars($base0['supply_label'] ?? '유통량곡선 OFF', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="warn-line">커스텀/실시간·유통량곡선 적용 중에는 실제 매수·매도 단가도 바뀝니다. 테스트 후 스냅샷·곡선 OFF로 되돌리세요.</div>
    </div>
  </div>
  <?php } ?>

  <div class="card">
    <div class="top-row">
      <div class="nick" id="exNick"><?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="point" id="exPoint"><?= htmlspecialchars($초기포인트, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="meta-line" id="exMeta">
      시총 <?= htmlspecialchars($초기시세['total_nyang_fmt'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
      · 매도수수료 <?= (int)($초기시세['spread_pct'] ?? 20) ?>%<?= ((int)($초기시세['spread_pct'] ?? 20) === 20) ? '(금고10·로또10)' : (((int)($초기시세['spread_pct'] ?? 0) === 0) ? '(없음)' : '') ?>
      · <?= htmlspecialchars($base0['mode_label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="pf-box empty" id="portfolioBox">
      <div class="pf-head">
        <span class="pf-title">📊 내 투자내역</span>
        <span class="pf-qty" id="pfQty">보유 없음</span>
      </div>
      <div class="pf-grid">
        <div class="pf-cell">
          <span class="lab">매입금액</span>
          <span class="val" id="pfCost">—</span>
        </div>
        <div class="pf-cell">
          <span class="lab">매도시수령</span>
          <span class="val" id="pfValue">—</span>
        </div>
        <div class="pf-cell pnl">
          <span class="lab">평가손익(차익)</span>
          <span class="val" id="pfPnl">—</span>
          <span class="pct" id="pfPct"></span>
        </div>
      </div>
      <div class="pf-list" id="pfList"></div>
      <div class="pf-hint">매수 시 평균매입가 기록 · 매도 시 시세의 80% 수령(수수료 20%→금고·로또) · 손익 = 실수령 − 매입</div>
    </div>
    <div class="tabs">
      <button type="button" class="active" id="tabBuy">구매</button>
      <button type="button" id="tabSell">팔기</button>
      <button type="button" id="tabChart">차트</button>
    </div>
    <div class="panel active" id="panelBuy">
      <div class="hint"><?= $본방결제
        ? '현재 시세(본방냥)에 매수 · 본방냥 차감 · 종류별 하루 최대 10개(자정 초기화)'
        : '현재 시세에 매수 · 종류별 하루 최대 10개(자정 초기화) · 매입가가 평균단가로 기록' ?></div>
      <div id="buyList"></div>
    </div>
    <div class="panel" id="panelSell">
      <div class="hint"><?= $본방결제
        ? '매도 = 시세 × 80% · 수수료 20%(금고·로또) · 수령은 본방냥<br>꼬벙·1주년·부루마블1등은 시총 10% 본방냥 그대로 수령 (보유자만)'
        : '매도 = 시세 × 80% · 수수료 20%(금고 10% · 로또 10%) · 실수령 − 매입 = 손익<br>꼬벙·1주년·부루마블1등은 본방냥 시총 10%로 매도 (보유자만)' ?></div>
      <div id="sellList"></div>
    </div>
    <div class="panel" id="panelChart">
      <div class="hint">유통량곡선 ON이면 매수·매도할수록 단가가 변합니다(억절사로 1%가 깎이던 문제 수정). 곡선 체크 후 반드시 <b>적용</b>을 눌러 주세요. 적용 뒤 1회 매수하면 선이 움직여야 합니다.</div>
      <div class="chart-toolbar">
        <select id="chartItem"></select>
        <button type="button" class="range-btn active" data-h="24">24h</button>
        <button type="button" class="range-btn" data-h="72">3일</button>
        <button type="button" class="range-btn" data-h="168">7일</button>
        <button type="button" id="btnChartLoad">불러오기</button>
      </div>
      <div class="chart-meta" id="chartMeta">아이템을 선택해 주세요.</div>
      <div class="chart-wrap"><canvas id="chartCanvas" width="680" height="220"></canvas></div>
      <div class="chart-empty" id="chartEmpty" style="display:none">아직 틱/거래 데이터가 없어요.<br>매수·매도하거나 새로고침하면 점이 쌓입니다.</div>
    </div>
    <div class="btn-row">
      <button type="button" class="btn" id="btnRefresh">새로고침</button>
    </div>
  </div>

  <div class="modal-overlay" id="modalBuy" onclick="closeModalBg(event,'Buy')">
    <div class="modal-box" onclick="event.stopPropagation()">
      <h3 id="buyTitle">구매</h3>
      <div class="form-field">
        <label for="buyQty">수량</label>
        <input type="number" id="buyQty" min="1" max="10" step="1" value="1">
      </div>
      <div class="quote-box" id="buyHint"></div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal('Buy')">취소</button>
        <button type="button" class="btn-confirm" onclick="submitBuy()">구매</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="modalSell" onclick="closeModalBg(event,'Sell')">
    <div class="modal-box" onclick="event.stopPropagation()">
      <h3 id="sellTitle">팔기</h3>
      <div class="form-field">
        <label for="sellQty">수량</label>
        <input type="number" id="sellQty" min="1" step="1" value="1">
      </div>
      <div class="quote-box" id="sellHint"></div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal('Sell')">취소</button>
        <button type="button" class="btn-confirm" onclick="submitSell()">팔기</button>
      </div>
    </div>
  </div>
  <div class="toast" id="toast"></div>
<?php } ?>
</div>

<?php if ($로그인 && !empty($member_row['idx'])) { ?>
<script>
(function() {
  var CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
  var API = <?= json_encode('/page/item_exchange.php' . $q, JSON_UNESCAPED_UNICODE) ?>;
  var IS_ADMIN = <?= $준호 ? 'true' : 'false' ?>;
  var PAY_UNIT = <?= json_encode($초기결제단위 ?? 'point', JSON_UNESCAPED_UNICODE) ?>;
  var DATA = <?= json_encode($초기시세 ?: ['quotes'=>[], 'inventory'=>[], 'spread_pct'=>0, 'total_nyang_fmt'=>'-', 'base'=>null, 'pay_unit'=>($초기결제단위 ?? 'point')], JSON_UNESCAPED_UNICODE) ?>;
  if (DATA && DATA.pay_unit) PAY_UNIT = DATA.pay_unit;
  var selectedBuy = null;
  var selectedSell = null;
  var chartHours = 24;
  var chartLoadedItem = '';
  var lastChart = null;
  var POLL_MS = 30000;
  var pollTimer = null;
  var pollInFlight = false;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }
  function digitsOnly(s) {
    return String(s || '').replace(/\D/g, '') || '0';
  }
  function toast(msg) {
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
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(j) {
      app.classList.remove('loading');
      if (!j || !j.ok) {
        toast((j && (j.data || j.msg)) || '오류');
        return;
      }
      if (cb) cb(j);
    }).catch(function() {
      app.classList.remove('loading');
      toast('통신 오류');
    });
  }

  /** 폴링용 — 로딩 오버레이/토스트 없음 */
  function ajaxQuiet(action, extra, cb) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('wallet_code', CODE);
    if (extra) Object.keys(extra).forEach(function(k) { body.set(k, extra[k]); });
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(j) {
      if (j && j.ok && cb) cb(j);
    }).catch(function() {}).then(function() {
      pollInFlight = false;
    });
  }

  function setTab(tab) {
    document.getElementById('tabBuy').classList.toggle('active', tab === 'buy');
    document.getElementById('tabSell').classList.toggle('active', tab === 'sell');
    document.getElementById('tabChart').classList.toggle('active', tab === 'chart');
    document.getElementById('panelBuy').classList.toggle('active', tab === 'buy');
    document.getElementById('panelSell').classList.toggle('active', tab === 'sell');
    document.getElementById('panelChart').classList.toggle('active', tab === 'chart');
    if (tab === 'chart') {
      fillChartItems();
      // display:none → block 직후 clientWidth=0 으로 그려지면 캔버스가 비어 보임
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          var sel = document.getElementById('chartItem');
          if (sel && sel.value) {
            loadChart();
          } else if (lastChart) {
            drawChart(lastChart);
          }
        });
      });
    }
  }

  function fillChartItems() {
    var sel = document.getElementById('chartItem');
    if (!sel || !DATA) return;
    var names = (DATA.chart_items && DATA.chart_items.length)
      ? DATA.chart_items
      : (DATA.quotes || []).filter(function(q) {
          return q && q.pricing !== 'gongkeo' && q.pricing !== 'coin';
        }).map(function(q) { return q.name; });
    var prev = sel.value;
    sel.innerHTML = '';
    names.forEach(function(n) {
      if (!n) return;
      var opt = document.createElement('option');
      opt.value = String(n);
      opt.textContent = String(n);
      sel.appendChild(opt);
    });
    if (prev) sel.value = prev;
    if (!sel.value && names.length) sel.value = names[0];
  }

  function ensureChartY(chart) {
    if (!chart || !chart.points || chart.points.length < 2) return chart;
    var pts = chart.points;
    var yMin = Infinity, yMax = -Infinity;
    for (var i = 0; i < pts.length; i++) {
      var yy = Number(pts[i].y);
      if (!isFinite(yy)) yy = 50;
      if (yy < yMin) yMin = yy;
      if (yy > yMax) yMax = yy;
    }
    if (yMax - yMin > 0.5) return chart;

    function applyLocal(vals, scaleName) {
      var mn = Infinity, mx = -Infinity;
      for (var i = 0; i < vals.length; i++) {
        if (vals[i] < mn) mn = vals[i];
        if (vals[i] > mx) mx = vals[i];
      }
      if (!(mx > mn)) return false;
      for (var j = 0; j < pts.length; j++) {
        pts[j].y = ((vals[j] - mn) / (mx - mn)) * 100;
      }
      chart.flat = false;
      chart.flat_reason = '';
      chart.scale = scaleName;
      return true;
    }

    var circs = pts.map(function(p) { return Number(p.circulating) || 0; });
    if (applyLocal(circs, 'circulating_js')) return chart;

    var seq = [];
    var n = 0;
    var anyTrade = false;
    for (var k = 0; k < pts.length; k++) {
      var r = String(pts[k].reason || '');
      if (r === 'buy_pre' || r === 'sell_pre' || r === 'buy' || r === 'sell' || r === 'trade_log') {
        seq.push(n++);
        anyTrade = true;
      } else {
        seq.push(Math.max(0, n - 1));
      }
    }
    if (anyTrade && n >= 2 && applyLocal(seq, 'trade_seq_js')) return chart;
    return chart;
  }

  function drawChart(chart) {
    var canvas = document.getElementById('chartCanvas');
    var empty = document.getElementById('chartEmpty');
    var meta = document.getElementById('chartMeta');
    if (!canvas) return;
    chart = ensureChartY(chart || lastChart || {});
    if (chart) lastChart = chart;
    chart = chart || lastChart || {};
    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var wrap = canvas.parentElement;
    var cssW = canvas.clientWidth || (wrap && wrap.clientWidth) || 0;
    var cssH = canvas.clientHeight || (wrap && wrap.clientHeight) || 0;
    if (cssW < 40) cssW = 680;
    if (cssH < 40) cssH = 220;
    canvas.width = Math.floor(cssW * dpr);
    canvas.height = Math.floor(cssH * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    var pts = (chart && chart.points) || [];
    if (meta) {
      var flatNote = (chart.flat && chart.flat_reason) ? (' · ⚠ ' + chart.flat_reason) : '';
      var scaleNote = chart.scale ? (' · scale ' + chart.scale) : '';
      meta.textContent = (chart.item || '-') + ' · 매수 ' + (chart.now_buy_fmt || '-')
        + ' · 매도 ' + (chart.now_sell_fmt || '-')
        + ' · 구간 최저 ' + (chart.min_fmt || '-') + ' / 최고 ' + (chart.max_fmt || '-')
        + ' · 점 ' + pts.length + '개 · 거래 ' + ((chart.trades || []).length) + '건'
        + scaleNote + flatNote;
    }
    if (pts.length < 1) {
      if (empty) empty.style.display = '';
      return;
    }
    if (empty) empty.style.display = 'none';

    var padL = 10, padR = 10, padT = 16, padB = 28;
    var w = cssW - padL - padR;
    var h = cssH - padT - padB;
    var minTs = pts[0].ts, maxTs = pts[pts.length - 1].ts;
    if (maxTs <= minTs) maxTs = minTs + 1;

    // grid
    ctx.strokeStyle = 'rgba(255,255,255,0.08)';
    ctx.lineWidth = 1;
    for (var g = 0; g <= 4; g++) {
      var gy = padT + (h * g / 4);
      ctx.beginPath();
      ctx.moveTo(padL, gy);
      ctx.lineTo(padL + w, gy);
      ctx.stroke();
    }

    // buy line
    ctx.beginPath();
    ctx.strokeStyle = '#5b9fd4';
    ctx.lineWidth = 2;
    if (pts.length === 1) {
      var y1 = padT + h - (Math.max(0, Math.min(100, pts[0].y || 50)) / 100) * h;
      ctx.moveTo(padL, y1);
      ctx.lineTo(padL + w, y1);
      ctx.stroke();
      ctx.lineTo(padL + w, padT + h);
      ctx.lineTo(padL, padT + h);
      ctx.closePath();
      ctx.fillStyle = 'rgba(91,159,212,0.12)';
      ctx.fill();
      ctx.beginPath();
      ctx.strokeStyle = '#5b9fd4';
      ctx.lineWidth = 2;
      ctx.moveTo(padL, y1);
      ctx.lineTo(padL + w, y1);
      ctx.stroke();
    } else {
      pts.forEach(function(p, i) {
        var x = padL + ((p.ts - minTs) / (maxTs - minTs)) * w;
        var y = padT + h - (Math.max(0, Math.min(100, p.y || 50)) / 100) * h;
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.stroke();

      // fill under
      ctx.lineTo(padL + w, padT + h);
      ctx.lineTo(padL, padT + h);
      ctx.closePath();
      ctx.fillStyle = 'rgba(91,159,212,0.12)';
      ctx.fill();

      // redraw stroke on top of fill
      ctx.beginPath();
      ctx.strokeStyle = '#5b9fd4';
      ctx.lineWidth = 2;
      pts.forEach(function(p, i) {
        var x = padL + ((p.ts - minTs) / (maxTs - minTs)) * w;
        var y = padT + h - (Math.max(0, Math.min(100, p.y || 50)) / 100) * h;
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.stroke();
    }
    // dots
    pts.forEach(function(p) {
      var x = padL + ((p.ts - minTs) / (maxTs - minTs)) * w;
      var y = padT + h - (Math.max(0, Math.min(100, p.y || 50)) / 100) * h;
      ctx.beginPath();
      ctx.fillStyle = (p.reason === 'buy' || p.reason === 'sell' || p.reason === 'trade' || p.reason === 'trade_log') ? '#c9a96e' : '#5b9fd4';
      ctx.arc(x, y, 2.5, 0, Math.PI * 2);
      ctx.fill();
    });

    // trade markers
    (chart.trades || []).forEach(function(tr) {
      if (!tr.ts) return;
      var x = padL + ((tr.ts - minTs) / (maxTs - minTs)) * w;
      ctx.beginPath();
      ctx.fillStyle = tr.side === 'sell' ? '#d4a05b' : '#6cbc6a';
      ctx.moveTo(x, padT + 4);
      ctx.lineTo(x - 4, padT + 12);
      ctx.lineTo(x + 4, padT + 12);
      ctx.closePath();
      ctx.fill();
    });

    // time labels
    ctx.fillStyle = '#9a9388';
    ctx.font = '11px sans-serif';
    function fmtT(ts) {
      var d = new Date(ts * 1000);
      var mm = ('0' + (d.getMonth() + 1)).slice(-2);
      var dd = ('0' + d.getDate()).slice(-2);
      var hh = ('0' + d.getHours()).slice(-2);
      var mi = ('0' + d.getMinutes()).slice(-2);
      return mm + '-' + dd + ' ' + hh + ':' + mi;
    }
    ctx.fillText(fmtT(minTs), padL, cssH - 8);
    var endLabel = fmtT(maxTs);
    var tw = ctx.measureText(endLabel).width;
    ctx.fillText(endLabel, padL + w - tw, cssH - 8);
  }

  function loadChart() {
    var sel = document.getElementById('chartItem');
    var item = sel ? sel.value : '';
    if (!item) {
      toast('아이템을 선택해 주세요');
      return;
    }
    ajax('chart', { item_name: item, hours: String(chartHours) }, function(j) {
      chartLoadedItem = item;
      drawChart(j.chart || {});
    });
  }

  function selectedMode() {
    var el = document.querySelector('input[name="baseMode"]:checked');
    return el ? el.value : 'snapshot';
  }
  function syncModeUi() {
    var mode = selectedMode();
    document.querySelectorAll('#baseModes label').forEach(function(lab) {
      var inp = lab.querySelector('input');
      lab.classList.toggle('active', !!(inp && inp.checked));
    });
    var wrap = document.getElementById('customWrap');
    if (wrap) wrap.style.display = mode === 'custom' ? '' : 'none';
  }
  function renderBase(base) {
    if (!base) return;
    var snap = document.getElementById('cmpSnap');
    var live = document.getElementById('cmpLive');
    var active = document.getElementById('cmpActive');
    var modeEl = document.getElementById('cmpMode');
    if (snap) snap.textContent = base.snapshot_fmt || '-';
    if (live) live.textContent = base.live_fmt || '-';
    if (active) active.textContent = base.active_fmt || '-';
    if (modeEl) modeEl.textContent = base.mode_label || base.mode || '-';
    if (base.mode) {
      var radio = document.querySelector('input[name="baseMode"][value="' + base.mode + '"]');
      if (radio) radio.checked = true;
    }
    if (base.custom != null && document.getElementById('customTotal')) {
      document.getElementById('customTotal').value = base.custom;
    }
    var sc = document.getElementById('supplyCurve');
    if (sc) sc.checked = !!base.supply_curve;
    var sr = document.getElementById('supplyRef');
    if (sr && base.supply_ref) sr.value = String(base.supply_ref);
    var sp = document.getElementById('spreadPct');
    if (sp && base.spread != null) sp.value = String(base.spread);
    var pm = document.getElementById('priceMult');
    if (pm && base.price_mult != null) pm.value = String(base.price_mult);
    var sh = document.getElementById('supplyHint');
    if (sh) sh.textContent = base.supply_label || '';
    syncModeUi();
  }

  function applyData(ex, pointFmt) {
    if (ex) DATA = ex;
    if (DATA && DATA.pay_unit) PAY_UNIT = DATA.pay_unit;
    if (pointFmt) document.getElementById('exPoint').textContent = pointFmt;
    var meta = document.getElementById('exMeta');
    if (meta && DATA) {
      var bl = (DATA.base && DATA.base.mode_label) ? (' · ' + DATA.base.mode_label) : '';
      if (DATA.base && DATA.base.supply_curve) bl += ' · 유통곡선';
      if (DATA.base && DATA.base.price_mult && Number(DATA.base.price_mult) !== 1) {
        bl += ' · ' + DATA.base.price_mult + '×';
      }
      if (PAY_UNIT === 'newpoint') bl += ' · 본방냥결제';
      var sp = parseInt(DATA.spread_pct, 10);
      if (isNaN(sp)) sp = 20;
      var spNote = (sp === 20) ? '(금고10·로또10)' : (sp === 0 ? '(없음)' : '');
      meta.textContent = '시총 ' + (DATA.total_nyang_fmt || '-') + ' · 매도수수료 ' + sp + '%' + spNote + bl;
    }
    if (DATA && DATA.base) renderBase(DATA.base);
    renderPortfolio();
    render();
    fillChartItems();
  }

  // portfolio 미제공(구버전 응답) 시 보유목록만으로 최소 표시
  function portfolioFallback() {
    var inv = (DATA && DATA.inventory) || [];
    if (!inv.length) return null;
    var qty = 0;
    var items = inv.map(function(it) {
      qty += Number(it.count) || 0;
      return {
        name: it.name,
        qty: Number(it.count) || 0,
        cost_known: false,
        cost_fmt: '원가미상',
        value_fmt: it.sell_all_fmt || it.sell_fmt || '—',
        pnl_fmt: '—',
        pnl_pct_fmt: '—',
        pnl_up: true
      };
    });
    return {
      has: true,
      items: items,
      qty_total: qty,
      cost_fmt: '원가미상',
      value_fmt: '—',
      pnl_fmt: '—',
      pnl_pct_fmt: '—',
      pnl_up: true
    };
  }

  function renderPortfolio() {
    var box = document.getElementById('portfolioBox');
    if (!box || !DATA) return;
    var pf = DATA.portfolio || {};
    if (!pf.has) {
      pf = portfolioFallback() || pf;
    }
    var has = !!pf.has;
    box.classList.toggle('empty', !has);
    var qtyEl = document.getElementById('pfQty');
    var costEl = document.getElementById('pfCost');
    var valueEl = document.getElementById('pfValue');
    var pnlEl = document.getElementById('pfPnl');
    var pctEl = document.getElementById('pfPct');
    var listEl = document.getElementById('pfList');
    if (!has) {
      if (qtyEl) qtyEl.textContent = '보유 없음';
      if (costEl) costEl.textContent = '—';
      if (valueEl) valueEl.textContent = '—';
      if (pnlEl) { pnlEl.textContent = '—'; pnlEl.className = 'val'; }
      if (pctEl) { pctEl.textContent = ''; pctEl.className = 'pct'; }
      if (listEl) listEl.innerHTML = '<div class="pf-row"><span>보유한 시세 아이템이 없어요. 구매하면 여기에 표시됩니다.</span></div>';
      return;
    }
    if (qtyEl) qtyEl.textContent = '보유 ' + (pf.qty_total || 0) + '개';
    if (costEl) costEl.textContent = pf.cost_fmt || '—';
    if (valueEl) valueEl.textContent = pf.value_fmt || '—';
    var up = pf.pnl_up !== false;
    if (pnlEl) {
      pnlEl.textContent = pf.pnl_fmt || '—';
      pnlEl.className = 'val ' + (up ? 'up' : 'down');
    }
    if (pctEl) {
      pctEl.textContent = pf.pnl_pct_fmt || '';
      pctEl.className = 'pct ' + (up ? 'up' : 'down');
    }
    if (listEl) {
      var rows = pf.items || [];
      listEl.innerHTML = rows.map(function(it) {
        var cls = it.pnl_up === false ? 'down' : 'up';
        var pnlTxt = it.cost_known === false
          ? (esc(it.value_fmt) + ' · 원가미상')
          : (esc(it.pnl_fmt) + (it.pnl_pct_fmt && it.pnl_pct_fmt !== '—' ? (' ' + esc(it.pnl_pct_fmt)) : ''));
        var avg = it.avg_fmt || '—';
        var mkt = it.market_fmt || it.sell_fmt || it.buy_fmt || '—';
        return '<div class="pf-row"><span><span class="n">' + esc(it.name) + '</span> '
          + it.qty + '개 · 매입가 ' + esc(avg)
          + ' → 매도단가 ' + esc(mkt)
          + ' · 수령 ' + esc(it.value_fmt)
          + '</span><span class="p ' + cls + '">' + pnlTxt + '</span></div>';
      }).join('');
    }
  }

  function quotesFingerprint(quotes) {
    return (quotes || []).map(function(q) {
      return [
        q.name || '',
        q.buy || '',
        q.sell || '',
        q.buy_fmt || '',
        q.sell_fmt || '',
        q.last_buy_nick || '',
        q.last_buy_qty || 0,
        q.last_buy_unit_fmt || '',
        q.last_buy_paid_fmt || '',
        q.spark_up ? 1 : 0,
        (q.spark || []).join(',')
      ].join('|');
    }).join(';');
  }

  function applyPoll(poll) {
    if (!poll || !DATA) return;
    var nextQuotes = poll.quotes || [];
    var pfChanged = poll.portfolio
      && JSON.stringify(DATA.portfolio || {}) !== JSON.stringify(poll.portfolio || {});
    var same =
      quotesFingerprint(DATA.quotes) === quotesFingerprint(nextQuotes)
      && String(DATA.total_nyang || '') === String(poll.total_nyang || '')
      && String(DATA.spread_pct || '') === String(poll.spread_pct || '')
      && !pfChanged;
    if (same) return;

    DATA.quotes = nextQuotes;
    if (poll.total_nyang != null) DATA.total_nyang = poll.total_nyang;
    if (poll.total_nyang_fmt != null) DATA.total_nyang_fmt = poll.total_nyang_fmt;
    if (poll.spread_pct != null) DATA.spread_pct = poll.spread_pct;
    if (poll.portfolio) DATA.portfolio = poll.portfolio;
    if (poll.pay_unit) {
      DATA.pay_unit = poll.pay_unit;
      PAY_UNIT = poll.pay_unit;
    }

    var meta = document.getElementById('exMeta');
    if (meta) {
      var bl = (DATA.base && DATA.base.mode_label) ? (' · ' + DATA.base.mode_label) : '';
      if (DATA.base && DATA.base.supply_curve) bl += ' · 유통곡선';
      if (DATA.base && DATA.base.price_mult && Number(DATA.base.price_mult) !== 1) {
        bl += ' · ' + DATA.base.price_mult + '×';
      }
      if (PAY_UNIT === 'newpoint') bl += ' · 본방냥결제';
      var sp = parseInt(DATA.spread_pct, 10);
      if (isNaN(sp)) sp = 20;
      var spNote = (sp === 20) ? '(금고10·로또10)' : (sp === 0 ? '(없음)' : '');
      meta.textContent = '시총 ' + (DATA.total_nyang_fmt || '-') + ' · 매도수수료 ' + sp + '%' + spNote + bl;
    }
    renderPortfolio();
    render();
  }

  function pollQuotes() {
    if (pollInFlight) return;
    if (document.hidden) return;
    // 모달 열려 있으면 리스트 깜빡임 방지 — 다음 주기에
    if (document.querySelector('.modal.show')) return;
    pollInFlight = true;
    ajaxQuiet('poll', null, function(j) {
      applyPoll(j.poll);
    });
  }
  function startPoll() {
    if (pollTimer) return;
    pollTimer = setInterval(pollQuotes, POLL_MS);
  }
  function stopPoll() {
    if (!pollTimer) return;
    clearInterval(pollTimer);
    pollTimer = null;
  }
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      stopPoll();
    } else {
      pollQuotes();
      startPoll();
    }
  });

  function lastBuyHtml(it) {
    var nick = (it.last_buy_nick || '').trim();
    var qty = parseInt(it.last_buy_qty, 10) || 0;
    if (!nick) {
      return '';
    }
    var parts = ['최근 <b>' + esc(nick) + '</b>'];
    if (qty > 0) {
      parts.push('<span class="qty">' + qty + '개</span>');
    }
    var paid = (it.last_buy_paid_fmt || '').trim();
    var unit = (it.last_buy_unit_fmt || '').trim();
    // 총액 우선, 없으면 단가
    var amt = paid || unit;
    if (amt && amt !== '0' && amt !== '0냥') {
      parts.push('<span class="amt">' + esc(amt) + '</span>');
    }
    return '<span class="last-buy">' + parts.join(' · ') + '</span>';
  }

  function sparkHtml(it) {
    var ys = it.spark || [];
    var name = it.name || '';
    if (!ys.length) {
      return '<div class="spark-empty" data-chart="' + esc(name) + '" title="차트 보기">차트 대기</div>';
    }
    if (ys.length === 1) {
      ys = [ys[0], ys[0]];
    }
    var w = 240, h = 44, padX = 6, padY = 6;
    var n = ys.length;
    var pts = [];
    for (var i = 0; i < n; i++) {
      var x = padX + (n === 1 ? 0 : (i * (w - padX * 2) / (n - 1)));
      var yv = Math.max(0, Math.min(100, Number(ys[i]) || 0));
      var y = padY + ((100 - yv) / 100) * (h - padY * 2);
      pts.push(x.toFixed(1) + ',' + y.toFixed(1));
    }
    var up = it.spark_up !== false;
    var stroke = up ? '#6ecf8e' : '#d16b6b';
    var fill = up ? 'rgba(110,207,142,0.14)' : 'rgba(209,107,107,0.14)';
    var area = pts[0] + ' ' + pts.join(' ') + ' ' + (w - padX).toFixed(1) + ',' + (h - padY)
      + ' ' + padX + ',' + (h - padY);
    return '<svg class="spark ' + (up ? 'up' : 'down') + '" viewBox="0 0 ' + w + ' ' + h
      + '" preserveAspectRatio="none" data-chart="' + esc(name) + '" title="차트 보기">'
      + '<polygon points="' + area + '" fill="' + fill + '" stroke="none"></polygon>'
      + '<polyline points="' + pts.join(' ') + '" fill="none" stroke="' + stroke
      + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>'
      + '</svg>';
  }

  function render() {
    var buyBox = document.getElementById('buyList');
    var sellBox = document.getElementById('sellList');
    if (!buyBox || !sellBox || !DATA) return;

    var quotes = (DATA.quotes || []).filter(function(it) {
      var n = String(it.name || '') + String(it.display_name || '');
      if (it.pricing === 'coin') return false;
      return !/꼬벙기념주화|1주년기념주화|꼬병기념주화/.test(n);
    });
    buyBox.innerHTML = quotes.length
      ? quotes.map(function(it) {
          var disabled = it.buyable === false;
          var isGk = it.pricing === 'gongkeo';
          var isCoin = it.pricing === 'coin';
          var label = it.display_name || it.name;
          var circQty = Number(it.circulating) || 0;
          var circ = '';
          if (!isGk && !isCoin && (circQty > 0 || (DATA.base && DATA.base.supply_curve))) {
            circ = ' · 유통 ' + circQty + (it.mult_fmt ? (' ' + it.mult_fmt) : '');
          }
          var pct = (isGk || isCoin)
            ? (isCoin ? String(it.percent_label || '시총10%') : '')
            : ((it.percent_label && String(it.percent_label))
              ? String(it.percent_label)
              : (String(it.percent) + '%'));
          var spread0 = (parseInt(DATA.spread_pct, 10) || 0) === 0;
          var priceMeta = isGk
            ? ('구매 ' + esc(it.buy_fmt) + ' · 매도불가 · 게임냥')
            : (isCoin
              ? (disabled
                ? ('구매불가 · 보유자만 판매 · 매도 ' + esc(it.sell_fmt) + ' · 본방냥')
                : ('매수 ' + esc(it.buy_fmt) + ' · 매도 ' + esc(it.sell_fmt) + ' · 본방냥'))
              : (spread0
                ? ('시세 ' + esc(it.buy_fmt))
                : ('매수 ' + esc(it.buy_fmt) + ' · 매도 ' + esc(it.sell_fmt))));
          if (isGk && it.note) priceMeta += ' · ' + esc(it.note);
          if (isGk && it.is_gongkeo === false) priceMeta += ' · 공커만 구매';
          var chartBtn = (isGk || isCoin)
            ? ''
            : ('<button type="button" class="item-btn" style="background:rgba(201,169,110,0.18);color:#e2c991" data-chart="' + esc(it.name) + '">차트</button>');
          var metaExtra = (pct !== '' ? (' · ' + esc(pct)) : '') + esc(circ);
          var buyBtn = disabled
            ? '<button type="button" class="item-btn buy" disabled>구매불가</button>'
            : ('<button type="button" class="item-btn buy" data-name="' + esc(it.name) + '" data-buy="' + esc(it.buy_fmt)
              + '" data-pay="' + esc(it.pay_unit || (isGk ? 'point' : (isCoin ? 'newpoint' : (PAY_UNIT || 'point')))) + '">구매</button>');
          return '<div class="item-row"><div class="item-main">'
            + '<div class="name-row"><span class="name">' + esc(label) + '</span>' + lastBuyHtml(it) + '</div>'
            + ((isGk || isCoin) ? '' : sparkHtml(it))
            + '<div class="meta">' + priceMeta + metaExtra + '</div>'
            + '<div class="actions">' + buyBtn
            + chartBtn
            + '</div></div>'
            + '<div class="price">' + esc(isCoin && disabled ? (it.sell_fmt || it.buy_fmt) : it.buy_fmt) + '</div></div>';
        }).join('')
      : '<div class="empty">시세 아이템이 없어요.<br>tb_item.percent 또는 은총을 확인해 주세요.</div>';

    var inv = DATA.inventory || [];
    sellBox.innerHTML = inv.length
      ? inv.map(function(it) {
          var isCoin = it.pricing === 'coin';
          var label = it.display_name || it.name;
          var meta = '보유 ' + it.count + '개 · 매도단가 1개 ' + (it.sell_fmt || it.buy_fmt || '-') ;
          if (it.count > 1 && it.sell_all_fmt) meta += ' · 전량 ' + it.sell_all_fmt;
          if (isCoin) meta += ' · 본방냥 · 수수료 없음';
          return '<div class="item-row"><div class="item-main"><div class="name">' + esc(label) + '</div>'
            + '<div class="meta">' + esc(meta) + '</div>'
            + '<div class="actions"><button type="button" class="item-btn sell" data-name="' + esc(it.name)
            + '" data-max="' + it.count + '" data-sell="' + esc(it.sell_fmt) + '" data-pay="' + esc(it.pay_unit || 'point')
            + '" data-pricing="' + esc(it.pricing || '')
            + '">팔기</button></div></div>'
            + '<div class="price">' + esc(it.sell_fmt || '-') + '</div></div>';
        }).join('')
      : '<div class="empty">팔 수 있는 보유 아이템이 없어요.</div>';
  }

  window.closeModal = function(which) {
    var el = document.getElementById('modal' + which);
    if (el) el.classList.remove('show');
  };
  window.closeModalBg = function(e, which) {
    if (e.target && e.target.id === 'modal' + which) closeModal(which);
  };

  function openBuy(name, buyFmt, payUnit) {
    selectedBuy = name;
    document.getElementById('buyTitle').textContent = name + ' 구매';
    document.getElementById('buyQty').value = '1';
    var unit = payUnit || PAY_UNIT || 'point';
    var payHint = (unit === 'newpoint') ? '본방냥이 차감됩니다.' : '게임냥이 차감됩니다.';
    document.getElementById('buyHint').textContent = '단가 ' + (buyFmt || '-') + '\n' + payHint;
    document.getElementById('modalBuy').classList.add('show');
  }
  function openSell(name, max, sellFmt, payUnit, pricing) {
    var unit = payUnit || PAY_UNIT || 'point';
    selectedSell = { name: name, max: max || 1, pay: unit, pricing: pricing || '' };
    document.getElementById('sellTitle').textContent = name + ' 팔기';
    document.getElementById('sellQty').value = '1';
    document.getElementById('sellQty').max = String(max || 1);
    var hint;
    if ((pricing || '') === 'coin') {
      hint = '매도단가 1개당 ' + (sellFmt || '-') + '\n보유 ' + max + '개 · 본방냥 시총 10% · 수수료 없음';
    } else if (unit === 'newpoint') {
      hint = '매도단가(수수료 반영) 1개당 ' + (sellFmt || '-') + '\n보유 ' + max + '개 · 수령은 본방냥';
    } else {
      hint = '매도단가(수수료 반영) 1개당 ' + (sellFmt || '-') + '\n보유 ' + max + '개 · 수수료 20%는 금고·로또';
    }
    document.getElementById('sellHint').textContent = hint;
    document.getElementById('modalSell').classList.add('show');
  }

  window.submitBuy = function() {
    if (!selectedBuy) return;
    var qty = parseInt(document.getElementById('buyQty').value, 10) || 1;
    if (qty < 1) qty = 1;
    if (qty > 50) {
      toast('한 번에 최대 10개까지 매수할 수 있어요. (종류별 하루 10개 · 자정 초기화)');
      return;
    }
    var bought = selectedBuy;
    ajax('buy', { item_name: selectedBuy, qty: qty }, function(j) {
      closeModal('Buy');
      toast(j.data || '매수 완료');
      applyData(j.exchange, j.point_fmt);
      fillChartItems();
      var sel = document.getElementById('chartItem');
      if (sel && bought) sel.value = bought;
      setTab('chart');
    });
  };
  window.submitSell = function() {
    if (!selectedSell) return;
    var qty = parseInt(document.getElementById('sellQty').value, 10) || 1;
    if (qty > selectedSell.max) qty = selectedSell.max;
    ajax('sell', { item_name: selectedSell.name, qty: qty }, function(j) {
      closeModal('Sell');
      toast(j.data || '판매 완료');
      applyData(j.exchange, j.point_fmt);
      if (document.getElementById('panelChart').classList.contains('active')) loadChart();
    });
  };

  function mulBig(str, factor) {
    str = digitsOnly(str);
    if (window.BigInt) {
      try {
        var n = BigInt(str);
        if (factor === 0.5) return String(n / 2n);
        return String(n * BigInt(factor));
      } catch (e) {}
    }
    var n2 = parseFloat(str) || 0;
    return String(Math.floor(n2 * factor));
  }

  document.getElementById('tabBuy').addEventListener('click', function() { setTab('buy'); });
  document.getElementById('tabSell').addEventListener('click', function() { setTab('sell'); });
  document.getElementById('tabChart').addEventListener('click', function() { setTab('chart'); });
  document.getElementById('btnRefresh').addEventListener('click', function() {
    ajax('status', null, function(j) {
      applyData(j.exchange, j.point_fmt);
      toast('새로고침 완료');
      if (document.getElementById('panelChart').classList.contains('active')) loadChart();
    });
  });
  document.getElementById('buyList').addEventListener('click', function(e) {
    var c = e.target.closest('[data-chart]');
    if (c) {
      fillChartItems();
      var sel = document.getElementById('chartItem');
      if (sel) sel.value = c.getAttribute('data-chart');
      setTab('chart');
      return;
    }
    var b = e.target.closest('[data-name].buy');
    if (!b || b.disabled) return;
    openBuy(b.getAttribute('data-name'), b.getAttribute('data-buy'), b.getAttribute('data-pay') || 'point');
  });
  document.getElementById('sellList').addEventListener('click', function(e) {
    var b = e.target.closest('[data-name].sell');
    if (!b) return;
    openSell(
      b.getAttribute('data-name'),
      parseInt(b.getAttribute('data-max'), 10) || 1,
      b.getAttribute('data-sell'),
      b.getAttribute('data-pay') || 'point',
      b.getAttribute('data-pricing') || ''
    );
  });
  document.getElementById('btnChartLoad').addEventListener('click', loadChart);
  window.addEventListener('resize', function() {
    if (!lastChart) return;
    if (!document.getElementById('panelChart') || !document.getElementById('panelChart').classList.contains('active')) return;
    requestAnimationFrame(function() { drawChart(lastChart); });
  });
  document.querySelectorAll('.range-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.range-btn').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      chartHours = parseInt(btn.getAttribute('data-h'), 10) || 72;
      loadChart();
    });
  });
  document.getElementById('chartItem').addEventListener('change', loadChart);

  if (IS_ADMIN) {
    var toggleBtn = document.getElementById('btnToggleSettings');
    var settingsCard = document.getElementById('adminSettings');
    if (toggleBtn && settingsCard) {
      toggleBtn.addEventListener('click', function() {
        var open = settingsCard.classList.toggle('open');
        toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    document.querySelectorAll('input[name="baseMode"]').forEach(function(inp) {
      inp.addEventListener('change', syncModeUi);
    });
    function applyBaseSettings(doneMsg) {
      ajax('set_base', {
        mode: selectedMode(),
        custom: digitsOnly(document.getElementById('customTotal').value),
        supply_curve: document.getElementById('supplyCurve').checked ? '1' : '0',
        supply_ref: document.getElementById('supplyRef').value || '100',
        spread: document.getElementById('spreadPct').value || '20',
        price_mult: document.getElementById('priceMult').value || '1'
      }, function(j) {
        toast(doneMsg || j.data || '적용됨');
        applyData(j.exchange, j.point_fmt);
        if (document.getElementById('panelChart') && document.getElementById('panelChart').classList.contains('active')) {
          loadChart();
        }
      });
    }
    var btnApply = document.getElementById('btnApplyBase');
    if (btnApply) {
      btnApply.addEventListener('click', function() { applyBaseSettings(); });
    }
    // 곡선 체크/기준개수는 자동저장하지 않음 — 실수로 배수·모드가 덮이면 전 종목 시세가 급락할 수 있음
    ['1','2','3','5','10'].forEach(function(v) {
      var btn = document.getElementById('btnPm' + v);
      if (btn) btn.addEventListener('click', function() {
        document.getElementById('priceMult').value = v;
      });
    });
    var btnHalf = document.getElementById('btnHalf');
    if (btnHalf) btnHalf.addEventListener('click', function() {
      var el = document.getElementById('customTotal');
      el.value = mulBig(el.value, 0.5);
    });
    var btnDouble = document.getElementById('btnDouble');
    if (btnDouble) btnDouble.addEventListener('click', function() {
      var el = document.getElementById('customTotal');
      el.value = mulBig(el.value, 2);
    });
    var btnUseSnap = document.getElementById('btnUseSnap');
    if (btnUseSnap) btnUseSnap.addEventListener('click', function() {
      if (DATA && DATA.base) document.getElementById('customTotal').value = DATA.base.snapshot || '0';
    });
    var btnUseLive = document.getElementById('btnUseLive');
    if (btnUseLive) btnUseLive.addEventListener('click', function() {
      if (DATA && DATA.base) document.getElementById('customTotal').value = DATA.base.live || '0';
    });
    if (DATA && DATA.base) renderBase(DATA.base);
    else syncModeUi();
  }

  renderPortfolio();
  render();
  fillChartItems();
  if (!document.hidden) startPoll();
})();
</script>
<?php } ?>
<?php
if ($로그인 && $code !== '') {
  require_once __DIR__ . '/../api/game/wallet_nav_fab.inc.php';
  wallet_nav_fab_render([
    'code' => $code,
    'show_back' => false, // 타이틀 옆 지갑 버튼 사용
    'show_bag' => true,
    'show_swap' => true,
    'bag_href' => '/page/wallet.php' . ($code !== '' ? ('?code=' . rawurlencode($code)) : ''),
  ]);
}
?>
</body>
</html>
