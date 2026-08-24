<?php
/**
 * 냥카라 웹 — ?code= 인증 · 최대 8명 실시간
 * URL: /page/baccarat.php?code=XXXX
 * action=status | join | leave | start | bet
 */
require_once __DIR__ . '/wallet_subpage.inc.php';
require_once __DIR__ . '/baccarat.inc.php';

$boot = wallet_subpage_boot();
$wallet_code = (string)($boot['code'] ?? '');
$wallet_need_code = !empty($boot['need_code']);
$wallet_member = $boot['member'];
$wallet_q = (string)($boot['q'] ?? '');
$wallet_nick = (string)($boot['nick'] ?? '');
$wallet_api = '/page/baccarat.php';

$req_action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
$BACCARAT_ACTIONS = ['status', 'join', 'leave', 'start', 'bet', 'tip', 'react', 'x2force'];

if (in_array($req_action, $BACCARAT_ACTIONS, true)) {
    if ($wallet_need_code || $wallet_nick === '') {
        wallet_json(['ok' => false, 'data' => '접속 코드가 필요합니다.']);
    }
    if (!baccarat_lock(5)) {
        wallet_json(['ok' => false, 'data' => '잠시 후 다시 시도해 주세요.']);
    }
    try {
        if ($req_action === 'status') {
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'status']));
        }
        if ($req_action === 'join') {
            $res = baccarat_join($wallet_nick);
            if (empty($res['ok'])) {
                wallet_json(array_merge(baccarat_payload($wallet_nick, ['type' => 'join']), $res, ['ok' => false]));
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'join', 'data' => $res['data']]));
        }
        if ($req_action === 'leave') {
            $res = baccarat_leave($wallet_nick);
            if (empty($res['ok'])) {
                wallet_json(array_merge(baccarat_payload($wallet_nick, ['type' => 'leave']), $res, ['ok' => false]));
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'leave', 'data' => $res['data']]));
        }
        if ($req_action === 'start') {
            $res = baccarat_start_now($wallet_nick);
            if (empty($res['ok'])) {
                wallet_json(['ok' => false, 'data' => $res['data'] ?? '시작 실패']);
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'start', 'data' => $res['data']]));
        }
        if ($req_action === 'bet') {
            $side = isset($_REQUEST['side']) ? (string)$_REQUEST['side'] : '';
            $amount = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
            $res = baccarat_bet($wallet_nick, $side, $amount);
            if (empty($res['ok'])) {
                wallet_json(['ok' => false, 'data' => $res['data'] ?? '배팅 실패']);
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'bet', 'data' => $res['data']]));
        }
        if ($req_action === 'tip') {
            $pct = isset($_REQUEST['pct']) ? (string)$_REQUEST['pct'] : '5';
            $res = baccarat_tip($wallet_nick, $pct);
            if (empty($res['ok'])) {
                wallet_json(array_merge(baccarat_payload($wallet_nick, ['type' => 'tip']), $res, ['ok' => false]));
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'tip', 'data' => $res['data']]));
        }
        if ($req_action === 'react') {
            $emo = isset($_REQUEST['emo']) ? (string)$_REQUEST['emo'] : '';
            $res = baccarat_react($wallet_nick, $emo);
            if (empty($res['ok'])) {
                wallet_json(array_merge(baccarat_payload($wallet_nick, ['type' => 'react']), $res, ['ok' => false]));
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'react', 'data' => $res['data'] ?? '']));
        }
        if ($req_action === 'x2force') {
            $res = baccarat_x2_force($wallet_nick);
            if (empty($res['ok'])) {
                wallet_json(array_merge(baccarat_payload($wallet_nick, ['type' => 'x2force']), $res, ['ok' => false]));
            }
            wallet_json(baccarat_payload($wallet_nick, ['type' => 'x2force', 'data' => $res['data']]));
        }
        wallet_json(['ok' => false, 'data' => '알 수 없는 요청']);
    } finally {
        baccarat_unlock();
    }
}

$np_fmt = is_array($wallet_member) ? (string)($wallet_member['newpoint_fmt'] ?? '0') : '0';
$pt_fmt = is_array($wallet_member) ? (string)($wallet_member['point_fmt'] ?? '0') : '0';
$nick_disp = is_array($wallet_member) ? (string)($wallet_member['nick'] ?? $wallet_nick) : $wallet_nick;
$min_bet = defined('BACCARAT_MIN_BET') ? (int)BACCARAT_MIN_BET : 1000;
$presets = function_exists('baccarat_preset_bets') ? baccarat_preset_bets() : [
    ['amt' => '1000', 'label' => '1천'],
    ['amt' => '10000', 'label' => '1만'],
    ['amt' => '100000', 'label' => '10만'],
];
while (count($presets) < 3) {
    $presets[] = ['amt' => (string)$min_bet, 'label' => (string)$min_bet];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0b1a12">
    <title>냥카라</title>
    <style>
        :root {
            --bg: #0b1a12;
            --felt: #0f3d28;
            --felt2: #0a2c1d;
            --gold: #e8c872;
            --gold2: #f5e6b8;
            --text: #f4efe4;
            --muted: rgba(244,239,228,0.55);
            --line: rgba(232,200,114,0.22);
            --player: #7dd3fc;
            --banker: #fca5a5;
            --tie: #86efac;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(232,200,114,0.12) 0%, transparent 42%),
                radial-gradient(ellipse at 80% 80%, rgba(14,80,50,0.5) 0%, transparent 50%),
                var(--bg);
            color: var(--text);
            padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px));
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .wrap {
            max-width: 520px;
            width: 100%;
            margin: 0 auto;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        h1 { font-size: 1rem; margin: 0; letter-spacing: 0.04em; line-height: 1.2; }
        .sub { font-size: 0.74rem; color: var(--muted); margin: 0 0 12px; line-height: 1.45; }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            flex-shrink: 0;
        }
        .brand { min-width: 0; }
        .toplinks {
            display: flex;
            gap: 8px;
            margin-top: 2px;
        }
        .toplinks a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.68rem;
        }
        .bal {
            text-align: right;
            font-variant-numeric: tabular-nums;
            display: flex;
            align-items: baseline;
            gap: 6px;
            min-width: 0;
        }
        .bal .nick {
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--text);
            max-width: 7.5em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .bal strong { font-size: 0.92rem; color: var(--gold); }
        .bal .unit { font-size: 0.7rem; color: var(--gold2); font-weight: 800; }
        .felt {
            position: relative;
            background:
                radial-gradient(ellipse at 50% 40%, rgba(255,255,255,0.06) 0%, transparent 55%),
                linear-gradient(180deg, var(--felt) 0%, var(--felt2) 100%);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 8px 8px 8px;
            box-shadow: inset 0 0 0 3px rgba(12,40,28,0.45), 0 10px 28px rgba(0,0,0,0.35);
            flex: 1;
            min-height: 0;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
        }
        .phase-row {
            position: sticky;
            top: 0;
            z-index: 6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin: -8px -8px 6px;
            padding: 8px 8px 6px;
            background: linear-gradient(180deg, #0f3d28 72%, rgba(15,61,40,0.92) 100%);
        }
        .mvp-line {
            flex: 1;
            margin: 0;
            text-align: center;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--gold2);
            letter-spacing: 0.01em;
            line-height: 1.2;
            min-width: 0;
            min-height: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mvp-line[hidden] { display: none !important; }
        .phase-tag {
            font-size: 0.66rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--gold2);
            background: rgba(0,0,0,0.25);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 3px 8px;
            flex-shrink: 0;
        }
        .timer {
            flex-shrink: 0;
            font-size: 1.2rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--gold);
            min-width: 2.4em;
            text-align: center;
            line-height: 1.15;
            padding: 3px 8px;
            border-radius: 8px;
            background: rgba(0,0,0,0.45);
            border: 1px solid var(--line);
            z-index: 7;
        }
        .shoe-row {
            position: absolute;
            top: 48px;
            right: 10px;
            margin: 0;
            min-height: 0;
            z-index: 1;
            pointer-events: none;
        }
        .shoe {
            position: relative;
            width: 46px;
            height: 20px;
            transform: rotate(-12deg);
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.35));
            opacity: 0.72;
        }
        .shoe.dealing { opacity: 1; animation: shoeKick 0.42s ease; }
        .shoe span {
            position: absolute;
            inset: 0;
            border-radius: 3px 10px 4px 3px;
            background:
                linear-gradient(90deg, #c4a574 0%, #ead7b0 18%, #d4b896 100%);
            border: 1px solid rgba(80,50,20,0.45);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.35);
        }
        .shoe span:nth-child(1) { transform: translate(0, 0); }
        .shoe span:nth-child(2) { transform: translate(3px, -3px); opacity: 0.92; }
        .shoe span:nth-child(3) {
            transform: translate(6px, -6px);
            background: repeating-linear-gradient(135deg, #6b1220 0 5px, #8f1d2e 5px 10px);
            border-color: rgba(80,10,20,0.5);
        }
        @keyframes shoeKick {
            0% { transform: rotate(-12deg) translate(0, 0); }
            40% { transform: rotate(-18deg) translate(-6px, 2px); }
            100% { transform: rotate(-12deg) translate(0, 0); }
        }
        .hands {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 4px;
        }
        .hand {
            background: rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 6px 6px 6px;
            min-height: 0;
            transition: box-shadow 0.35s ease;
        }
        .hand.win { box-shadow: 0 0 0 2px var(--gold), 0 0 22px rgba(232,200,114,0.28); }
        .chip-spot {
            position: relative;
            min-height: 20px;
            margin-top: 4px;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            pointer-events: none;
        }
        .tie-row {
            display: flex;
            justify-content: center;
            margin: 0 0 4px;
        }
        .tie-spot {
            min-width: 72px;
            min-height: 28px;
            border-radius: 999px;
            border: 1px dashed rgba(134,239,172,0.4);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            padding: 4px 10px 6px;
            color: var(--tie);
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.06em;
        }
        .tie-spot .chip-spot { margin-top: 2px; min-height: 24px; }
        .chip {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-left: -11px;
            border: 2px dashed rgba(255,255,255,0.88);
            box-shadow:
                0 3px 7px rgba(0,0,0,0.42),
                inset 0 1px 0 rgba(255,255,255,0.45),
                inset 0 -2px 3px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.52rem;
            font-weight: 900;
            color: #fff;
            text-shadow: 0 1px 0 rgba(0,0,0,0.35);
            z-index: 1;
        }
        .chip:first-child { margin-left: 0; }
        .chip.P { background: radial-gradient(circle at 32% 28%, #bfdbfe, #1d4ed8 58%); }
        .chip.B { background: radial-gradient(circle at 32% 28%, #fecaca, #b91c1c 58%); }
        .chip.T { background: radial-gradient(circle at 32% 28%, #bbf7d0, #15803d 58%); }
        .chip.land { animation: chipLand 0.32s cubic-bezier(.15,1.5,.35,1) both; }
        @keyframes chipLand {
            0% { transform: scale(1.25) translateY(-10px); }
            100% { transform: scale(1) translateY(0); }
        }
        .chip-fly {
            position: fixed;
            left: 0;
            top: 0;
            margin: 0;
            z-index: 80;
            pointer-events: none;
            will-change: transform;
        }
        #chipFlyLayer {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 80;
            overflow: hidden;
        }
        #btnBet:active { transform: scale(0.97); }
        .hand h3 {
            margin: 0 0 4px;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 6px;
        }
        .hand h3 .odd-tag {
            font-size: 0.66rem;
            font-weight: 800;
            letter-spacing: 0;
            opacity: 0.85;
            margin-left: 4px;
        }
        .hand.player h3 { color: var(--player); }
        .hand.banker h3 { color: var(--banker); }
        .cards {
            display: flex;
            gap: 0;
            min-height: 68px;
            align-items: center;
            justify-content: center;
            perspective: 700px;
        }
        .card-slot {
            width: 44px;
            height: 64px;
            margin-left: -8px;
            perspective: 800px;
            transform-origin: 50% 80%;
        }
        .card-slot:first-child { margin-left: 0; }
        .card-flip {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.55s cubic-bezier(.15,.85,.25,1);
        }
        .card-flip.is-flip { transform: rotateY(180deg); }
        .card-face {
            position: absolute;
            inset: 0;
            border-radius: 8px;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            box-shadow: 0 6px 14px rgba(0,0,0,0.38);
        }
        .card-face.back {
            background:
                radial-gradient(circle at 50% 50%, rgba(232,200,114,0.22) 0 8px, transparent 9px),
                repeating-linear-gradient(135deg, #6b1220 0 6px, #8f1d2e 6px 12px);
            border: 1px solid #3f0d14;
        }
        .card-face.back::after {
            content: '';
            position: absolute;
            inset: 5px;
            border-radius: 5px;
            border: 1px solid rgba(232,200,114,0.35);
        }
        .card-face.front {
            transform: rotateY(180deg);
            background: linear-gradient(180deg, #fffaf0 0%, #f3ead6 100%);
            color: #1a1a1a;
            border: 1px solid #d7c9a8;
            display: grid;
            grid-template-rows: auto 1fr auto;
            padding: 4px 5px 5px;
        }
        .card-face.front.red { color: #c01414; }
        .c-tl, .c-br {
            display: flex;
            flex-direction: column;
            line-height: 1;
            font-weight: 800;
            font-size: 0.68rem;
        }
        .c-tl i, .c-br i { font-style: normal; font-size: 0.72rem; }
        .c-br { transform: rotate(180deg); align-items: flex-start; }
        .c-pip {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 1px 0 rgba(255,255,255,0.4);
        }
        .card-slot.deal {
            animation: dealFromShoe 0.46s cubic-bezier(.12,.82,.22,1) both;
        }
        @keyframes dealFromShoe {
            0% {
                opacity: 0;
                transform: translate(92px, -78px) rotate(-38deg) scale(0.55);
            }
            62% {
                opacity: 1;
                transform: translate(-2px, 4px) rotate(8deg) scale(1.04);
            }
            100% {
                opacity: 1;
                transform: translate(0, 0) rotate(0deg) scale(1);
            }
        }
        .result-banner {
            text-align: center;
            font-weight: 800;
            font-size: 0.82rem;
            color: var(--gold2);
            min-height: 0;
            margin: 0 0 4px;
        }
        .result-banner:empty { display: none; }
        .road {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            min-height: 0;
            margin-bottom: 6px;
        }
        .bead {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            font-size: 0.62rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111;
        }
        .bead.P { background: #7dd3fc; }
        .bead.B { background: #fca5a5; }
        .bead.T { background: #86efac; }
        .bead.PB { background: linear-gradient(90deg, #7dd3fc, #fca5a5); font-size: 0.5rem; }
        .bead.N { background: #64748b; color: #e2e8f0; }
        .seats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
        }
        .seat {
            background: rgba(0,0,0,0.22);
            border: 1px dashed rgba(255,255,255,0.12);
            border-radius: 8px;
            padding: 4px 6px;
            min-height: 0;
            font-size: 0.72rem;
        }
        .seat.filled { border-style: solid; border-color: var(--line); }
        .seat.me { box-shadow: 0 0 0 1px var(--gold); }
        .seat .who {
            font-weight: 800;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 6px;
            min-width: 0;
        }
        .seat .who .nm {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }
        .seat .hold {
            margin: 0;
            color: var(--gold2);
            font-size: 0.66rem;
            font-weight: 800;
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }
        .seat .meta { color: var(--muted); font-size: 0.64rem; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .seat.empty { color: var(--muted); display: flex; align-items: center; justify-content: center; min-height: 28px; font-size: 0.68rem; }
        .react-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 4px;
            margin-top: 6px;
        }
        .btn-react {
            font-family: inherit;
            cursor: pointer;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: rgba(0,0,0,0.22);
            min-height: 34px;
            font-size: 1.05rem;
            line-height: 1;
        }
        .btn-react:active { transform: scale(0.94); background: rgba(232,200,114,0.18); }
        #reactPopLayer {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 88;
            overflow: hidden;
        }
        .react-pop {
            position: absolute;
            left: 50%;
            top: 42%;
            transform: translate(-50%, -50%);
            text-align: center;
            animation: reactPop 1.7s ease forwards;
        }
        .react-pop .emo { font-size: 4.2rem; line-height: 1; filter: drop-shadow(0 8px 18px rgba(0,0,0,0.45)); }
        .react-pop .who {
            margin-top: 6px;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--gold2);
            text-shadow: 0 1px 4px rgba(0,0,0,0.7);
        }
        @keyframes reactPop {
            0% { opacity: 0; transform: translate(-50%, -40%) scale(0.4); }
            18% { opacity: 1; transform: translate(-50%, -50%) scale(1.12); }
            32% { transform: translate(-50%, -50%) scale(1); }
            78% { opacity: 1; transform: translate(-50%, -58%) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -72%) scale(0.88); }
        }
        .panel {
            margin-top: 6px;
            background: rgba(12, 24, 18, 0.92);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 8px;
            flex-shrink: 0;
            max-height: 48dvh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .sides {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 5px;
            margin-bottom: 6px;
        }
        .btn-side, .btn, .btn-pct, .btn-amt {
            font-family: inherit;
            cursor: pointer;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-weight: 800;
        }
        .btn-side { padding: 7px 4px 6px; font-size: 0.78rem; display: flex; flex-direction: column; align-items: center; gap: 1px; }
        .btn-side small { font-size: 0.64rem; font-weight: 800; opacity: 0.85; letter-spacing: 0.02em; }
        .btn-side.active.P { background: rgba(125,211,252,0.22); border-color: var(--player); color: var(--player); }
        .btn-side.active.B { background: rgba(252,165,165,0.22); border-color: var(--banker); color: var(--banker); }
        .btn-side.active.T { background: rgba(134,239,172,0.22); border-color: var(--tie); color: var(--tie); }
        .btn-side.placed { position: relative; }
        .btn-side.placed.P { box-shadow: inset 0 0 0 1px var(--player); }
        .btn-side.placed.B { box-shadow: inset 0 0 0 1px var(--banker); }
        .btn-side.placed.T { box-shadow: inset 0 0 0 1px var(--tie); }
        .btn-side .side-put { font-size: 0.58rem; font-weight: 800; line-height: 1.1; min-height: 0.7em; opacity: 0.92; }
        .amt-row, .pct-row {
            display: grid;
            gap: 5px;
            margin-bottom: 6px;
        }
        .amt-row { grid-template-columns: 1fr 1fr 1fr; }
        .pct-row { grid-template-columns: 1fr 1fr; }
        .btn-amt, .btn-pct { padding: 6px 4px; font-size: 0.72rem; min-height: 32px; }
        .btn-amt.active, .btn-pct.active {
            background: rgba(232,200,114,0.28);
            border-color: var(--gold);
            color: var(--gold2);
            box-shadow: 0 0 0 1px rgba(232,200,114,0.35);
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(0,0,0,0.28);
            color: var(--text);
            font-size: 0.9rem;
            font-family: inherit;
            margin-bottom: 6px;
        }
        .btn {
            width: 100%;
            padding: 10px;
            min-height: 40px;
            font-size: 0.88rem;
            background: linear-gradient(145deg, #e8c872, #c9a227);
            color: #1a1406;
            border: none;
        }
        .btn.ghost {
            display: block;
            text-align: center;
            text-decoration: none;
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--line);
            margin-top: 6px;
        }
        .row-2 {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }
        .row-2 .btn.ghost { margin-top: 0; flex: 1; width: auto; min-height: 36px; padding: 8px; }
        #btnX2Force { min-height: 34px; padding: 7px; font-size: 0.78rem; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        [hidden] { display: none !important; }
        .hint { font-size: 0.66rem; color: var(--muted); line-height: 1.35; margin: 0 0 6px; }
        .need-code {
            text-align: center;
            padding: 48px 16px;
            color: var(--muted);
            line-height: 1.6;
        }
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
            top: 14px;
            bottom: auto;
            transform: translateX(-50%) translateY(-12px);
            background: rgba(15,23,42,0.95);
            color: #fff;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.82rem;
            max-width: 90%;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s, transform 0.2s;
            z-index: 90;
            white-space: pre-wrap;
            text-align: center;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .result-splash {
            position: fixed;
            inset: 0;
            z-index: 75;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            background: rgba(4, 10, 8, 0.42);
            opacity: 0;
            visibility: hidden;
        }
        .result-splash.show {
            visibility: visible;
            animation: splashFade 2.8s ease forwards;
        }
        .x2-splash {
            position: fixed;
            inset: 0;
            top: 56px;
            z-index: 76;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 12%;
            pointer-events: none;
            background: transparent;
            opacity: 0;
            visibility: hidden;
        }
        .x2-splash.show {
            visibility: visible;
            animation: splashFade 2.8s ease forwards;
        }
        .x2-splash .box {
            min-width: 220px;
            max-width: 86%;
            padding: 22px 26px 20px;
            border-radius: 18px;
            text-align: center;
            background: linear-gradient(165deg, #3a2a08, #1a1406);
            border: 1px solid rgba(232,200,114,0.65);
            box-shadow: 0 18px 48px rgba(0,0,0,0.45);
        }
        .x2-mark {
            font-size: 2.4rem;
            font-weight: 900;
            color: var(--gold);
            letter-spacing: 0.08em;
            line-height: 1;
        }
        .x2-title {
            margin-top: 8px;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--gold2);
        }
        .x2-sub {
            margin-top: 6px;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .btn-side.x2-hot {
            box-shadow: 0 0 0 2px var(--gold);
            background: rgba(232,200,114,0.22);
        }
        .odd-tag.x2-hot, #oddT.x2-hot {
            color: var(--gold);
        }
        #btnX2Force.armed {
            color: var(--gold2);
            border-color: var(--gold);
        }
        @keyframes splashFade {
            0% { opacity: 0; }
            10% { opacity: 1; }
            82% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        .result-splash .box {
            min-width: 230px;
            max-width: 86%;
            padding: 22px 26px 20px;
            border-radius: 18px;
            text-align: center;
            transform: scale(0.86);
            animation: splashPop 2.8s cubic-bezier(.18,1.2,.32,1) forwards;
            box-shadow: 0 18px 48px rgba(0,0,0,0.45);
        }
        @keyframes splashPop {
            0% { transform: scale(0.72) translateY(12px); }
            14% { transform: scale(1.06) translateY(0); }
            22% { transform: scale(1) translateY(0); }
            100% { transform: scale(1) translateY(0); }
        }
        .result-splash.win .box {
            background: linear-gradient(165deg, #14331f, #0c1c12);
            border: 1px solid rgba(232,200,114,0.55);
        }
        .result-splash.lose .box {
            background: linear-gradient(165deg, #3a1216, #1a0a0c);
            border: 1px solid rgba(248,113,113,0.45);
        }
        .result-splash.push .box {
            background: linear-gradient(165deg, #123028, #0b1a16);
            border: 1px solid rgba(94,234,212,0.4);
        }
        .result-splash.info .box {
            background: linear-gradient(165deg, #16241c, #0d1812);
            border: 1px solid var(--line);
        }
        .splash-title {
            font-size: 1.55rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        .result-splash.win .splash-title { color: var(--gold); }
        .result-splash.lose .splash-title { color: #fca5a5; }
        .result-splash.push .splash-title { color: var(--tie); }
        .result-splash.info .splash-title { color: var(--gold2); }
        .splash-amt {
            font-size: 1.15rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            margin: 4px 0 8px;
        }
        .result-splash.win .splash-amt { color: #fde68a; }
        .result-splash.lose .splash-amt { color: #fecaca; }
        .result-splash.push .splash-amt { color: #bbf7d0; }
        .splash-sub {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.4;
        }
        .splash-mvp {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(232,200,114,0.22);
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--gold2);
            line-height: 1.45;
        }
        .splash-mvp small {
            display: block;
            margin-top: 2px;
            font-size: 0.76rem;
            color: #fde68a;
            font-weight: 700;
        }
        .splash-tip-pcts { pointer-events: auto; }
        .splash-tip {
            pointer-events: auto;
            margin-top: 12px;
            width: 100%;
            padding: 10px 12px;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            background: linear-gradient(145deg, #fbbf24, #d97706);
            color: #1a1406;
        }
        .btn.tip {
            background: linear-gradient(145deg, #fbbf24, #ea580c);
            color: #1a1406;
            margin-bottom: 6px;
        }
        #tipBox { margin-bottom: 6px; }
        .tip-pcts, .splash-tip-pcts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
            margin-bottom: 6px;
        }
        .btn-tip-pct {
            font-family: inherit;
            cursor: pointer;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            font-weight: 800;
            padding: 6px 4px;
            font-size: 0.72rem;
            min-height: 30px;
        }
        .btn-tip-pct.active {
            background: rgba(232,200,114,0.28);
            border-color: var(--gold);
            color: var(--gold2);
            box-shadow: 0 0 0 1px rgba(232,200,114,0.35);
        }
        .btn-tip-pct:disabled { opacity: 0.4; cursor: not-allowed; }
        .loading { opacity: 0.7; }
        .notice-gate {
            position: fixed;
            inset: 0;
            z-index: 200;
            background: rgba(4, 10, 8, 0.82);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px 16px;
        }
        .notice-gate[hidden] { display: none !important; }
        .notice-box {
            width: 100%;
            max-width: 360px;
            background: linear-gradient(165deg, #163524, #0c1c12);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px 20px 18px;
            box-shadow: 0 18px 48px rgba(0,0,0,0.5);
        }
        .notice-box h2 {
            margin: 0 0 12px;
            font-size: 1.05rem;
            color: var(--gold);
            letter-spacing: 0.04em;
        }
        .notice-box p {
            margin: 0 0 8px;
            font-size: 0.9rem;
            line-height: 1.55;
            color: var(--gold2);
        }
        .notice-box p.muted {
            color: var(--muted);
            font-size: 0.78rem;
            margin-top: 10px;
            margin-bottom: 16px;
        }
        .notice-actions {
            display: grid;
            gap: 8px;
        }
        .notice-actions .btn.ghost {
            margin-top: 0;
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--line);
        }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <div class="top">
        <div class="brand">
            <h1>🃏 냥카라</h1>
            <?php if (!$wallet_need_code) { ?>
            <div class="toplinks">
                <a href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">가방</a>
                <a href="/page/nyangkara_guide.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">방법</a>
            </div>
            <?php } ?>
        </div>
        <div class="bal">
            <span class="nick" id="wNick"><?php echo htmlspecialchars($nick_disp, ENT_QUOTES, 'UTF-8'); ?></span>
            <strong id="wPoint"><?php echo htmlspecialchars($pt_fmt, ENT_QUOTES, 'UTF-8'); ?></strong><span class="unit">냥</span>
        </div>
    </div>

    <?php if ($wallet_need_code) { ?>
        <div class="need-code">접속 코드가 필요합니다.<br>가방 링크로 다시 들어와 주세요.</div>
        <a class="back" href="/page/wallet.php">← 가방으로</a>
        <a class="back" href="/page/nyangkara_guide.php">냥카라 게임방법</a>
    <?php } else { ?>
        <div class="felt">
            <div class="phase-row">
                <span class="phase-tag" id="phaseTag">로비</span>
                <p class="mvp-line" id="mvpLine" hidden></p>
                <span class="timer" id="timer">–</span>
            </div>
            <div class="shoe-row"><div class="shoe" id="shoe" aria-hidden="true"><span></span><span></span><span></span></div></div>
            <p class="result-banner" id="resultBanner"></p>
            <div class="hands">
                <div class="hand player" id="handPlayer">
                    <h3><span>PLAYER <span class="odd-tag" id="oddP">1배</span></span> <span id="totP"></span></h3>
                    <div class="cards" id="cardsP"></div>
                    <div class="chip-spot" id="chipSpotP"></div>
                </div>
                <div class="hand banker" id="handBanker">
                    <h3><span>BANKER <span class="odd-tag" id="oddB">1배</span></span> <span id="totB"></span></h3>
                    <div class="cards" id="cardsB"></div>
                    <div class="chip-spot" id="chipSpotB"></div>
                </div>
            </div>
            <div class="tie-row">
                <div class="tie-spot">TIE <span id="oddT">8배</span><div class="chip-spot" id="chipSpotT"></div></div>
            </div>
            <div class="road" id="road"></div>
            <div class="seats" id="seats"></div>
            <div class="react-row" id="reactRow" hidden>
                <button type="button" class="btn-react" data-emo="smile" aria-label="웃는 얼굴">😊</button>
                <button type="button" class="btn-react" data-emo="sad" aria-label="슬픔">😢</button>
                <button type="button" class="btn-react" data-emo="smoke" aria-label="담배">🚬</button>
                <button type="button" class="btn-react" data-emo="thumb" aria-label="따봉">👍</button>
                <button type="button" class="btn-react" data-emo="angry" aria-label="화난 얼굴">😡</button>
                <button type="button" class="btn-react" data-emo="please" aria-label="주세요">🤲</button>
            </div>
        </div>

        <div class="panel" id="playPanel">
            <div id="joinBox">
                <button type="button" class="btn" id="btnJoin">자리 앉기</button>
            </div>
            <div id="betBox" hidden>
                <div id="tipBox" hidden>
                    <div class="tip-pcts" id="tipPcts">
                        <button type="button" class="btn-tip-pct active" data-pct="5">5%</button>
                        <button type="button" class="btn-tip-pct" data-pct="10">10%</button>
                    </div>
                    <button type="button" class="btn tip" id="btnTip">🎁 뽀찌주기</button>
                    <p class="hint" id="tipHint">딴 금액의 비율만큼 앉은 친구에게</p>
                </div>
                <div class="sides">
                    <button type="button" class="btn-side P" data-side="P">플레이어<small id="sideOddP">1배</small><small class="side-put" id="sidePutP"></small></button>
                    <button type="button" class="btn-side B" data-side="B">뱅커<small id="sideOddB">1배</small><small class="side-put" id="sidePutB"></small></button>
                    <button type="button" class="btn-side T" data-side="T">타이<small id="sideOddT">8배</small><small class="side-put" id="sidePutT"></small></button>
                </div>
                <input type="text" id="betAmount" inputmode="numeric" pattern="[0-9]*" autocomplete="off" enterkeyhint="done" placeholder="배팅 금액">
                <div class="amt-row">
                    <button type="button" class="btn-amt" data-amt="<?php echo htmlspecialchars($presets[0]['amt'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($presets[0]['label'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="btn-amt" data-amt="<?php echo htmlspecialchars($presets[1]['amt'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($presets[1]['label'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="btn-amt" data-amt="<?php echo htmlspecialchars($presets[2]['amt'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($presets[2]['label'], ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
                <div class="pct-row">
                    <button type="button" class="btn-pct" data-pct="10">보유 10%</button>
                    <button type="button" class="btn-pct" data-pct="30">보유 30%</button>
                    <button type="button" class="btn-pct" data-pct="50">보유 50%</button>
                    <button type="button" class="btn-pct" data-pct="100">보유 100%</button>
                </div>
                <button type="button" class="btn" id="btnBet">배팅하기</button>
                <div class="row-2">
                    <button type="button" class="btn ghost" id="btnStart">바로 시작</button>
                    <button type="button" class="btn ghost" id="btnLeave">일어나기</button>
                </div>
            </div>
            <button type="button" class="btn ghost" id="btnX2Force" hidden>다음 판 X2 발동</button>
        </div>
        <div id="chipFlyLayer" aria-hidden="true"></div>
        <div id="reactPopLayer" aria-hidden="true"></div>
        <div class="result-splash" id="resultSplash" aria-live="polite">
            <div class="box">
                <div class="splash-title" id="splashTitle"></div>
                <div class="splash-amt" id="splashAmt"></div>
                <div class="splash-sub" id="splashSub"></div>
                <div class="splash-mvp" id="splashMvp" hidden></div>
                <div class="splash-tip-pcts" id="splashTipPcts" hidden>
                    <button type="button" class="btn-tip-pct active" data-pct="5">5%</button>
                    <button type="button" class="btn-tip-pct" data-pct="10">10%</button>
                </div>
                <button type="button" class="splash-tip" id="splashTip" hidden>🎁 뽀찌주기</button>
            </div>
        </div>
        <div class="x2-splash" id="x2Splash" aria-live="polite">
            <div class="box">
                <div class="x2-mark">X2</div>
                <div class="x2-title" id="x2Title">플레이어 X2배</div>
                <div class="x2-sub" id="x2Sub">적중 시 2배 · 미적중 시 2배 손실</div>
            </div>
        </div>
        <div class="toast" id="toast"></div>
        <div class="notice-gate" id="noticeGate" role="dialog" aria-modal="true" aria-labelledby="noticeTitle">
            <div class="notice-box">
                <h2 id="noticeTitle">⚠️ 냥카라 주의안내</h2>
                <p>실제 현금으로 하는 게임이 아닙니다.</p>
                <p>채팅방 활동 포인트(게임냥)로 즐기는 놀이이며, 현금으로 전환되지 않습니다.</p>
                <p class="muted">내용을 확인한 뒤에만 입장할 수 있어요. 하루 한 번 안내합니다.</p>
                <div class="notice-actions">
                    <button type="button" class="btn" id="noticeOk">확인하였습니다</button>
                    <button type="button" class="btn ghost" id="noticeNo">모르겠습니다</button>
                </div>
            </div>
        </div>
        <script>
        (function() {
            try {
                var t = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Seoul' }).format(new Date());
                if (localStorage.getItem('nyangkara_notice_day') === t) {
                    var g = document.getElementById('noticeGate');
                    if (g) g.hidden = true;
                }
            } catch (e) {}
        })();
        </script>
    <?php } ?>
</div>
<?php
require_once __DIR__ . '/wallet_nav_fab.inc.php';
if (function_exists('wallet_nav_fab_render')) {
    wallet_nav_fab_render(['code' => $wallet_code]);
}
?>
<?php if (!$wallet_need_code) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($wallet_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($wallet_api, JSON_UNESCAPED_UNICODE); ?>;
    var MIN = <?php echo (int)$min_bet; ?>;
    var side = 'P';
    var lastKey = '';
    var busy = false;
    var pointDigits = '0';
    var dealTimers = [];
    var dealState = { round: 0, playing: false, done: false };
    var seenBets = {};
    var chipRound = 0;
    var holdFreeze = {};
    var myHoldFreeze = '';
    var mvpFreeze = null;

    var HOME = <?php echo json_encode('/page/wallet.php' . $wallet_q, JSON_UNESCAPED_UNICODE); ?>;
    var NOTICE_KEY = 'nyangkara_notice_day';
    var gameStarted = false;

    function todayKst() {
        try {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Seoul', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        } catch (e) {
            var d = new Date();
            var m = String(d.getMonth() + 1);
            var day = String(d.getDate());
            if (m.length < 2) m = '0' + m;
            if (day.length < 2) day = '0' + day;
            return d.getFullYear() + '-' + m + '-' + day;
        }
    }
    function noticeAcked() {
        try { return localStorage.getItem(NOTICE_KEY) === todayKst(); } catch (e) { return false; }
    }
    function ackNotice() {
        try { localStorage.setItem(NOTICE_KEY, todayKst()); } catch (e) {}
    }

    function chipTarget(side) {
        if (side === 'B') return document.getElementById('chipSpotB');
        if (side === 'T') return document.getElementById('chipSpotT');
        return document.getElementById('chipSpotP');
    }
    function sideAmt(bets, sd) {
        return digitsOnly((bets && bets[sd]) || '0');
    }
    function meBetsOf(t) {
        return (t && t.me && t.me.bets) ? t.me.bets : { P: '0', B: '0', T: '0' };
    }
    function seatBetSides(s) {
        var out = [];
        var bets = (s && s.bets) || {};
        ['P', 'B', 'T'].forEach(function(sd) {
            if (sideAmt(bets, sd) !== '0') out.push(sd);
        });
        if (!out.length && s && s.bet_side && ['P', 'B', 'T'].indexOf(s.bet_side) !== -1) {
            out.push(s.bet_side);
        }
        return out;
    }
    function syncSideButtons(t) {
        var bets = meBetsOf(t);
        var betting = t && t.phase === 'betting';
        document.querySelectorAll('.btn-side').forEach(function(b) {
            var sd = b.getAttribute('data-side');
            b.classList.toggle('active', sd === side);
            b.classList.toggle('placed', betting && sideAmt(bets, sd) !== '0');
            var put = document.getElementById('sidePut' + sd);
            if (put) {
                put.textContent = (betting && sideAmt(bets, sd) !== '0')
                    ? String(bets[sd + '_fmt'] || bets[sd] || '')
                    : '';
            }
        });
        var btn = document.getElementById('btnBet');
        if (!btn) return;
        btn.disabled = !betting;
        var labels = { P: '플레이어 배팅', B: '뱅커 배팅', T: '타이 배팅' };
        btn.textContent = betting ? (labels[side] || '배팅하기') : '배팅하기';
    }
    function clearChipSpots() {
        ['chipSpotP', 'chipSpotB', 'chipSpotT'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = '';
        });
    }
    function landChip(side) {
        var spot = chipTarget(side);
        if (!spot) return;
        if (spot.childNodes.length >= 6) return;
        var chip = document.createElement('div');
        chip.className = 'chip land ' + side;
        chip.textContent = '냥';
        spot.appendChild(chip);
    }
    function throwChips(side, fromEl, count) {
        var layer = document.getElementById('chipFlyLayer');
        var target = chipTarget(side);
        if (!layer || !target) return;
        var startEl = fromEl || document.getElementById('btnBet');
        if (!startEl) return;
        var a = startEl.getBoundingClientRect();
        var b = target.getBoundingClientRect();
        var sx = a.left + a.width / 2 - 14;
        var sy = a.top + a.height / 2 - 14;
        var ex = b.left + b.width / 2 - 14 + (Math.random() * 16 - 8);
        var ey = b.top + Math.max(0, b.height - 30) + (Math.random() * 6 - 3);
        var n = count || 4;
        for (var i = 0; i < n; i++) {
            (function(i) {
                var chip = document.createElement('div');
                chip.className = 'chip chip-fly ' + side;
                chip.textContent = '냥';
                chip.style.left = sx + 'px';
                chip.style.top = sy + 'px';
                layer.appendChild(chip);
                var jitterX = (Math.random() * 28 - 14);
                var jitterY = (Math.random() * 12 - 6);
                var dx = (ex - sx) + jitterX;
                var dy = (ey - sy) + jitterY;
                var dur = 520 + i * 70;
                var anim = chip.animate([
                    { transform: 'translate(0,0) rotate(0deg) scale(0.7)', offset: 0 },
                    { transform: 'translate(' + (dx * 0.42) + 'px,' + (dy * 0.18 - 92) + 'px) rotate(170deg) scale(1.12)', offset: 0.38 },
                    { transform: 'translate(' + dx + 'px,' + dy + 'px) rotate(400deg) scale(1)', offset: 1 }
                ], {
                    duration: dur,
                    easing: 'cubic-bezier(.18,.72,.22,1.05)',
                    fill: 'forwards',
                    delay: i * 42
                });
                anim.onfinish = function() {
                    landChip(side);
                    if (chip.parentNode) chip.parentNode.removeChild(chip);
                };
            })(i);
        }
    }
    function addDigitStrings(a, b) {
        a = digitsOnly(a);
        b = digitsOnly(b);
        var i = a.length, j = b.length, carry = 0, out = '';
        while (i > 0 || j > 0 || carry) {
            var x = (i > 0 ? a.charCodeAt(--i) - 48 : 0) + (j > 0 ? b.charCodeAt(--j) - 48 : 0) + carry;
            out = String(x % 10) + out;
            carry = x >= 10 ? 1 : 0;
        }
        return out.replace(/^0+/, '') || '0';
    }
    function markBetSeen(round, nick, sd, amt) {
        seenBets[String(round) + ':' + nick + ':' + (sd || '') + ':' + digitsOnly(amt)] = true;
    }
    function noticeNewBets(t) {
        if (!t || t.phase !== 'betting') return;
        if (chipRound !== t.round_no) {
            chipRound = t.round_no;
            clearChipSpots();
            seenBets = {};
        }
        (t.seats || []).forEach(function(s) {
            if (!s.has_bet || !s.nick) return;
            var from = s.me
                ? document.getElementById('btnBet')
                : document.querySelector('.seat[data-nick="' + String(s.nick).replace(/"/g, '') + '"]');
            var bets = s.bets || {};
            seatBetSides(s).forEach(function(sd) {
                var amt = sideAmt(bets, sd);
                var k = String(t.round_no) + ':' + s.nick + ':' + sd + ':' + amt;
                if (seenBets[k]) return;
                seenBets[k] = true;
                throwChips(sd, from, s.me ? 5 : 3);
            });
        });
    }

    var lastTable = null;

    function revealed() {
        return dealState.done;
    }
    function snapshotHolds(t) {
        holdFreeze = {};
        (t.seats || []).forEach(function(s) {
            if (!s || !s.nick) return;
            holdFreeze[s.nick] = s.point_compact || s.point_fmt || '0';
        });
        myHoldFreeze = (t.me && t.me.point_fmt) ? t.me.point_fmt : '';
        mvpFreeze = t.mvp || null;
        if (t.me && t.me.point != null) pointDigits = String(t.me.point);
    }
    function paintMvp(t) {
        var el = document.getElementById('mvpLine');
        if (!el) return;
        var mvp = t.mvp;
        if (t.phase === 'result' && !revealed()) {
            mvp = mvpFreeze;
        }
        if (!mvp || !mvp.nick) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        var amt = mvp.delta_compact || mvp.delta_fmt || '';
        el.textContent = '🏆 ' + mvp.nick + '  +' + amt + '냥';
        el.hidden = false;
    }
    var x2ShownRound = 0;
    function paintX2(t) {
        var x2 = (t && t.x2) || null;
        var side = x2 && x2.side ? x2.side : '';
        document.querySelectorAll('.btn-side').forEach(function(b) {
            b.classList.toggle('x2-hot', !!side && b.getAttribute('data-side') === side);
        });
        var oddP = document.getElementById('oddP');
        var oddB = document.getElementById('oddB');
        var oddT = document.getElementById('oddT');
        var sP = document.getElementById('sideOddP');
        var sB = document.getElementById('sideOddB');
        var sT = document.getElementById('sideOddT');
        if (oddP) { oddP.textContent = side === 'P' ? '2배' : '1배'; oddP.classList.toggle('x2-hot', side === 'P'); }
        if (oddB) { oddB.textContent = side === 'B' ? '2배' : '1배'; oddB.classList.toggle('x2-hot', side === 'B'); }
        if (oddT) { oddT.textContent = side === 'T' ? '16배' : '8배'; oddT.classList.toggle('x2-hot', side === 'T'); }
        if (sP) sP.textContent = side === 'P' ? '2배' : '1배';
        if (sB) sB.textContent = side === 'B' ? '2배' : '1배';
        if (sT) sT.textContent = side === 'T' ? '16배' : '8배';
        var forceBtn = document.getElementById('btnX2Force');
        if (forceBtn) {
            var isMinho = !!(t.me && t.me.is_minho);
            forceBtn.hidden = !isMinho;
            var queued = !!t.x2_force_queued;
            forceBtn.disabled = queued;
            forceBtn.classList.toggle('armed', queued);
            forceBtn.textContent = queued ? '다음 판 X2 예약됨' : '다음 판 X2 발동';
        }
        var betEl = document.getElementById('betAmount');
        if (betEl && betEl.value) {
            var cap = maxBetDigits();
            if (cmpDigits(betEl.value, cap) > 0) setBetAmount(betEl.value);
        }
        if (t.phase === 'betting' && side && x2ShownRound !== t.round_no) {
            x2ShownRound = t.round_no;
            var el = document.getElementById('x2Splash');
            var title = document.getElementById('x2Title');
            var sub = document.getElementById('x2Sub');
            if (title) title.textContent = (x2.text || (x2.label + ' X2배'));
            if (sub) sub.textContent = side === 'T'
                ? '적중 시 16배 · 미적중 시 2배 손실'
                : '적중 시 2배 · 미적중 시 2배 손실';
            if (el) {
                el.classList.remove('show');
                void el.offsetWidth;
                el.classList.add('show');
                clearTimeout(paintX2._timer);
                paintX2._timer = setTimeout(function() { el.classList.remove('show'); }, 2800);
            }
            try { if (navigator.vibrate) navigator.vibrate(180); } catch (e) {}
        }
        if (t.phase !== 'betting') {
            var splash = document.getElementById('x2Splash');
            if (splash) splash.classList.remove('show');
        }
    }
    function paintSeats(t) {
        var seatsEl = document.getElementById('seats');
        var html = '';
        var bySeat = {};
        (t.seats || []).forEach(function(s) { bySeat[s.seat] = s; });
        var showPay = t.phase === 'result' && revealed();
        for (var i = 1; i <= (t.max_seats || 8); i++) {
            var s = bySeat[i];
            if (!s) {
                html += '<div class="seat empty">' + i + '번 빈자리</div>';
                continue;
            }
            var bet = s.has_bet ? (s.bet_label || ((s.bet_side === 'P' ? '플' : s.bet_side === 'B' ? '뱅' : '타이') + ' ' + s.bet_amount_fmt)) : '대기';
            var extra = showPay && s.last_msg ? s.last_msg : bet;
            var liveHold = s.point_compact || s.point_fmt || '0';
            var hold = (t.phase === 'result' && !revealed() && holdFreeze[s.nick]) ? holdFreeze[s.nick] : liveHold;
            html += '<div class="seat filled' + (s.me ? ' me' : '') + '" data-nick="' + escapeHtml(s.nick) + '">'
                + '<div class="who"><span class="nm">' + i + ' · ' + escapeHtml(s.nick) + (s.me ? ' (나)' : '') + '</span>'
                + '<span class="hold">' + escapeHtml(hold) + '냥</span></div>'
                + '<div class="meta">' + escapeHtml(extra) + '</div></div>';
        }
        seatsEl.innerHTML = html;
    }

    function showToast(msg) {
        if (!msg) return;
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(function() { t.classList.remove('show'); }, String(msg).length > 24 ? 4500 : 2800);
    }

    function digitsOnly(s) {
        s = String(s == null ? '0' : s).replace(/[^\d]/g, '');
        return s.replace(/^0+/, '') || '0';
    }
    function cmpDigits(a, b) {
        a = digitsOnly(a);
        b = digitsOnly(b);
        if (a.length !== b.length) return a.length < b.length ? -1 : 1;
        if (a === b) return 0;
        return a < b ? -1 : 1;
    }
    function maxBetDigits() {
        return digitsOnly(pointDigits);
    }
    function clampToHold(s) {
        s = digitsOnly(s);
        if (s.length > 65) s = s.slice(0, 65);
        var hold = maxBetDigits();
        if (hold === '0') return '0';
        if (cmpDigits(s, hold) > 0) return hold;
        return s;
    }
    function setBetAmount(s) {
        var el = document.getElementById('betAmount');
        if (!el) return '0';
        var v = clampToHold(s);
        el.value = v === '0' ? '' : v;
        return v;
    }
    function halfFloor(s) {
        var carry = 0, out = '';
        for (var i = 0; i < s.length; i++) {
            var n = carry * 10 + (s.charCodeAt(i) - 48);
            out += String(Math.floor(n / 2));
            carry = n % 2;
        }
        return out.replace(/^0+/, '') || '0';
    }
    function pctOf(pct) {
        var s = digitsOnly(pointDigits);
        pct = parseInt(pct, 10) || 0;
        if (s === '0' || pct <= 0) return '0';
        if (pct >= 100) return s;
        if (pct === 10) return s.length <= 1 ? '0' : (s.slice(0, -1).replace(/^0+/, '') || '0');
        if (pct === 50) return halfFloor(s);
        // floor(보유 × pct / 100) — 경 단위 문자열 연산 (30% 등)
        var carry = 0, prod = '';
        for (var i = s.length - 1; i >= 0; i--) {
            var v = (s.charCodeAt(i) - 48) * pct + carry;
            prod = String(v % 10) + prod;
            carry = Math.floor(v / 10);
        }
        while (carry > 0) {
            prod = String(carry % 10) + prod;
            carry = Math.floor(carry / 10);
        }
        prod = prod.replace(/^0+/, '') || '0';
        if (prod.length <= 2) return '0';
        return prod.slice(0, -2).replace(/^0+/, '') || '0';
    }

    function ajax(action, extra, cb) {
        var light = (action === 'status' || action === 'react');
        if (busy && !light) return;
        if (!light) busy = true;
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('code', CODE);
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
            busy = false;
            if (!j || !j.ok) {
                if (action !== 'status' && action !== 'react') showToast((j && (j.data || j.msg)) || '오류');
                if (action === 'react' && j && (j.data || j.msg)) showToast(j.data || j.msg);
                if (j && j.table) render(j.table);
                if (cb) cb(j);
                return;
            }
            if (j.data && action !== 'status' && action !== 'react') showToast(j.data);
            if (j.table) render(j.table);
            if (cb) cb(j);
        })
        .catch(function() {
            busy = false;
            if (action !== 'status') showToast('네트워크 오류');
        });
    }

    function cardVal(c) {
        return c && c.val != null ? (parseInt(c.val, 10) || 0) : 0;
    }
    function handTotal(cards) {
        var s = 0;
        for (var i = 0; i < cards.length; i++) s += cardVal(cards[i]);
        return s % 10;
    }
    function later(ms, fn) {
        var id = setTimeout(fn, ms);
        dealTimers.push(id);
        return id;
    }
    function clearDeal() {
        for (var i = 0; i < dealTimers.length; i++) clearTimeout(dealTimers[i]);
        dealTimers = [];
        dealState.playing = false;
        var shoe = document.getElementById('shoe');
        if (shoe) shoe.classList.remove('dealing');
    }
    function kickShoe() {
        var shoe = document.getElementById('shoe');
        if (!shoe) return;
        shoe.classList.remove('dealing');
        void shoe.offsetWidth;
        shoe.classList.add('dealing');
    }
    function makeSlot(card) {
        var red = card && card.red ? ' red' : '';
        var rank = card && card.rank ? card.rank : '';
        var suit = card && card.suit ? card.suit : '';
        var slot = document.createElement('div');
        slot.className = 'card-slot';
        slot.innerHTML = '<div class="card-flip">'
            + '<div class="card-face back"></div>'
            + '<div class="card-face front' + red + '">'
            + '<span class="c-tl"><b>' + rank + '</b><i>' + suit + '</i></span>'
            + '<span class="c-pip">' + suit + '</span>'
            + '<span class="c-br"><b>' + rank + '</b><i>' + suit + '</i></span>'
            + '</div></div>';
        return slot;
    }
    function appendFlipped(el, card) {
        var slot = makeSlot(card);
        slot.querySelector('.card-flip').classList.add('is-flip');
        el.appendChild(slot);
    }
    var splashRound = 0;
    function buzzWin() {
        try {
            if (navigator.vibrate) navigator.vibrate(220);
        } catch (e) {}
    }
    function resultSideTitle(t) {
        var w = t && t.winner ? String(t.winner) : '';
        if (w === 'P') return '플레이어 승';
        if (w === 'B') return '뱅커 승';
        if (w === 'T') return '타이';
        if (w === 'PB') return '양쪽 적중';
        if (w === 'N') return '양쪽 미적중';
        var raw = String((t && t.last_result) || '');
        if (raw) return raw.replace(/\s+\d+\s*:\s*\d+.*$/, '').replace(/\s*·.*$/, '') || raw;
        return '결과';
    }
    function showResultSplash(t) {
        if (!t || splashRound === t.round_no) return;
        splashRound = t.round_no;
        var el = document.getElementById('resultSplash');
        if (!el) return;
        var me = t.me || {};
        var betThis = !!me.has_bet;
        var msg = betThis ? String(me.last_msg || '') : '';
        var delta = betThis ? String(me.last_delta || '0') : '0';
        var table = t.last_result || '';
        var kind = 'info';
        var title = resultSideTitle(t);
        var amt = '';
        if (betThis) {
            if (delta.charAt(0) === '-') {
                kind = 'lose';
                title = '잃었다';
                amt = msg || ('-' + (me.bet_amount || ''));
            } else if (msg.indexOf('적중') !== -1 || (delta !== '0' && delta !== '' && msg.indexOf('환급') === -1)) {
                kind = 'win';
                title = '따냈다!';
                amt = msg;
            } else if (msg.indexOf('환급') !== -1) {
                kind = 'push';
                title = '본전';
                amt = msg;
            }
        }
        document.getElementById('splashTitle').textContent = title;
        document.getElementById('splashAmt').textContent = amt;
        document.getElementById('splashSub').textContent = (kind === 'info') ? '' : table;
        var mvpEl = document.getElementById('splashMvp');
        var mvp = t.mvp;
        if (mvp && mvp.nick) {
            var meNick = me.nick || '';
            var line = mvp.nick === meNick
                ? '🎉 축하! ' + mvp.nick + ' 님이 이번 판 제일 많이 땄어요'
                : '🎉 축하! ' + mvp.nick + ' 님이 제일 많이 땄어요';
            mvpEl.innerHTML = line + '<small>+' + escapeHtml(mvp.delta_fmt || mvp.delta_compact || '') + '냥</small>';
            mvpEl.hidden = false;
        } else {
            mvpEl.innerHTML = '';
            mvpEl.hidden = true;
        }
        var splashTip = document.getElementById('splashTip');
        var splashPcts = document.getElementById('splashTipPcts');
        var canTip = betThis && kind === 'win' && t.tip && t.tip.can;
        if (splashPcts) splashPcts.hidden = !canTip;
        if (splashTip) {
            splashTip.hidden = !canTip;
            if (canTip) {
                var opt = tipOption(t.tip, tipPct);
                splashTip.textContent = '🎁 뽀찌주기 · 각 ' + ((opt && opt.per_fmt) || t.tip.per_fmt || '') + '냥';
            }
        }
        el.className = 'result-splash ' + kind;
        void el.offsetWidth;
        el.classList.add('show');
        if (kind === 'win') buzzWin();
        clearTimeout(showResultSplash._timer);
        showResultSplash._timer = setTimeout(function() {
            el.classList.remove('show');
        }, canTip ? 4500 : 2800);
    }
    function showWinner(t) {
        document.getElementById('resultBanner').textContent = t.last_result || '';
        document.getElementById('handPlayer').classList.toggle('win', t.winner === 'P' || t.winner === 'PB');
        document.getElementById('handBanker').classList.toggle('win', t.winner === 'B' || t.winner === 'PB');
        document.getElementById('totP').textContent = t.player_total != null ? t.player_total : '';
        document.getElementById('totB').textContent = t.banker_total != null ? t.banker_total : '';
        dealState.done = true;
        var view = lastTable && lastTable.round_no === t.round_no ? lastTable : t;
        if (view.me && view.me.point_fmt) document.getElementById('wPoint').textContent = view.me.point_fmt;
        if (view.me && view.me.point != null) pointDigits = String(view.me.point);
        var road = document.getElementById('road');
        if (road) {
            road.innerHTML = (view.road || []).map(function(w) {
                return '<span class="bead ' + w + '">' + w + '</span>';
            }).join('');
        }
        paintSeats(view);
        paintMvp(view);
        paintTip(view);
        showResultSplash(view);
    }
    function paintFinal(t) {
        var pEl = document.getElementById('cardsP');
        var bEl = document.getElementById('cardsB');
        pEl.innerHTML = '';
        bEl.innerHTML = '';
        (t.player_cards || []).forEach(function(c) { appendFlipped(pEl, c); });
        (t.banker_cards || []).forEach(function(c) { appendFlipped(bEl, c); });
        showWinner(t);
        dealState.playing = false;
        dealState.done = true;
        dealState.round = t.round_no;
    }
    function startDeal(t) {
        if (dealState.round === t.round_no && (dealState.playing || dealState.done)) return;
        clearDeal();
        dealState.round = t.round_no;
        dealState.playing = true;
        dealState.done = false;
        document.getElementById('handPlayer').classList.remove('win');
        document.getElementById('handBanker').classList.remove('win');
        document.getElementById('totP').textContent = '';
        document.getElementById('totB').textContent = '';
        document.getElementById('resultBanner').textContent = '카드를 나눕니다…';
        if ((t.remain || 0) <= 5 || !(t.player_cards || []).length) {
            paintFinal(t);
            return;
        }
        var pEl = document.getElementById('cardsP');
        var bEl = document.getElementById('cardsB');
        pEl.innerHTML = '';
        bEl.innerHTML = '';
        var P = t.player_cards || [];
        var B = t.banker_cards || [];
        var shownP = [];
        var shownB = [];
        function refreshTot() {
            document.getElementById('totP').textContent = shownP.length ? String(handTotal(shownP)) : '';
            document.getElementById('totB').textContent = shownB.length ? String(handTotal(shownB)) : '';
        }
        function dealTo(el, card, into, next) {
            kickShoe();
            var slot = makeSlot(card);
            el.appendChild(slot);
            void slot.offsetWidth;
            slot.classList.add('deal');
            later(480, function() {
                slot.querySelector('.card-flip').classList.add('is-flip');
                later(560, function() {
                    into.push(card);
                    refreshTot();
                    later(180, next);
                });
            });
        }
        var steps = [];
        if (P[0]) steps.push(function(n) { dealTo(pEl, P[0], shownP, n); });
        if (B[0]) steps.push(function(n) { dealTo(bEl, B[0], shownB, n); });
        if (P[1]) steps.push(function(n) { dealTo(pEl, P[1], shownP, n); });
        if (B[1]) steps.push(function(n) { dealTo(bEl, B[1], shownB, n); });
        if (P[2]) steps.push(function(n) { dealTo(pEl, P[2], shownP, n); });
        if (B[2]) steps.push(function(n) { dealTo(bEl, B[2], shownB, n); });
        function run(i) {
            if (i >= steps.length) {
                later(380, function() {
                    showWinner(t);
                    dealState.playing = false;
                    dealState.done = true;
                });
                return;
            }
            steps[i](function() { run(i + 1); });
        }
        later(180, function() { run(0); });
    }

    function phaseLabel(p, dealing) {
        if (p === 'betting') return '배팅 중';
        if (p === 'result') return dealing ? '오픈 중' : '결과';
        return '대기';
    }

    function render(t) {
        if (!t) return;
        lastTable = t;
        var dealing = t.phase === 'result' && dealState.playing && dealState.round === t.round_no;
        document.getElementById('phaseTag').textContent = phaseLabel(t.phase, dealing) + (t.round_no ? (' #' + t.round_no) : '');
        document.getElementById('timer').textContent = t.remain > 0 ? t.remain : '–';
        var wNick = document.getElementById('wNick');
        if (wNick && t.me && t.me.nick) wNick.textContent = t.me.nick;
        if (t.phase !== 'result') {
            snapshotHolds(t);
            if (t.me && t.me.point_fmt) document.getElementById('wPoint').textContent = t.me.point_fmt;
        } else if (revealed()) {
            if (t.me && t.me.point_fmt) document.getElementById('wPoint').textContent = t.me.point_fmt;
            if (t.me && t.me.point != null) pointDigits = String(t.me.point);
        } else if (myHoldFreeze) {
            document.getElementById('wPoint').textContent = myHoldFreeze;
        }
        applyPresets(t.presets);

        if (t.phase === 'result') {
            startDeal(t);
            if (revealed()) {
                document.getElementById('resultBanner').textContent = t.last_result || '';
            }
        } else {
            clearDeal();
            dealState.round = 0;
            dealState.done = false;
            document.getElementById('cardsP').innerHTML = '';
            document.getElementById('cardsB').innerHTML = '';
            document.getElementById('totP').textContent = '';
            document.getElementById('totB').textContent = '';
            document.getElementById('handPlayer').classList.remove('win');
            document.getElementById('handBanker').classList.remove('win');
            document.getElementById('resultBanner').textContent = t.phase === 'betting' ? '' : (t.last_result || '');
        }

        var road = document.getElementById('road');
        var beads = t.road || [];
        if (t.phase === 'result' && !revealed() && beads.length) {
            beads = beads.slice(0, -1);
        }
        road.innerHTML = beads.map(function(w) {
            return '<span class="bead ' + w + '">' + w + '</span>';
        }).join('');

        paintSeats(t);
        paintMvp(t);

        var seated = !!(t.me && t.me.seated);
        document.getElementById('joinBox').hidden = seated;
        document.getElementById('betBox').hidden = !seated;
        var reactRow = document.getElementById('reactRow');
        if (reactRow) reactRow.hidden = !seated;
        document.getElementById('btnBet').disabled = t.phase !== 'betting';
        document.getElementById('btnStart').hidden = t.phase !== 'lobby' || !seated;
        document.getElementById('btnLeave').disabled = t.phase !== 'lobby' && !!(t.me && t.me.has_bet);
        syncSideButtons(t);
        paintTip(t);
        noticeLastTip(t);
        noticeReacts(t);
        paintX2(t);
        if (t.phase === 'lobby') {
            clearChipSpots();
            seenBets = {};
            chipRound = 0;
        } else {
            noticeNewBets(t);
        }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    var lastTipKey = '';
    var tipPct = '5';
    function tipOption(tip, pct) {
        var list = (tip && tip.options) || [];
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].pct) === String(pct)) return list[i];
        }
        return null;
    }
    function setTipPct(pct) {
        tipPct = String(pct || '5');
        document.querySelectorAll('.btn-tip-pct').forEach(function(b) {
            b.classList.toggle('active', b.getAttribute('data-pct') === tipPct);
        });
        if (lastTable) paintTip(lastTable);
    }
    function paintTip(t) {
        var box = document.getElementById('tipBox');
        var btn = document.getElementById('btnTip');
        var hint = document.getElementById('tipHint');
        if (!box || !btn || !hint) return;
        var tip = (t && t.tip) || {};
        var opt = tipOption(tip, tipPct);
        document.querySelectorAll('.btn-tip-pct').forEach(function(b) {
            var o = tipOption(tip, b.getAttribute('data-pct'));
            b.disabled = !!(tip.can && o && !o.ok);
            b.classList.toggle('active', b.getAttribute('data-pct') === tipPct);
        });
        if (tip.can && revealed()) {
            box.hidden = false;
            var ok = !!(opt && opt.ok);
            btn.disabled = !ok;
            btn.textContent = '🎁 뽀찌주기';
            if (ok) {
                hint.textContent = '앉은 친구 ' + tip.count + '명에게 각 ' + (opt.per_fmt || '') + '냥 · 딴 금액의 ' + tipPct + '%';
            } else {
                hint.textContent = '이 비율은 딴 금액이 작아서 줄 수 없어요';
            }
        } else if (tip.done && t.phase === 'result' && revealed()) {
            box.hidden = false;
            btn.disabled = true;
            btn.textContent = '🎁 뽀찌 완료';
            hint.textContent = '이번 판 뽀찌를 뿌렸어요';
        } else {
            box.hidden = true;
            btn.disabled = false;
        }
        var splashTip = document.getElementById('splashTip');
        var splashPcts = document.getElementById('splashTipPcts');
        if (splashTip) {
            if (tip.can && revealed()) {
                splashTip.hidden = false;
                splashTip.disabled = !(opt && opt.ok);
                splashTip.textContent = '🎁 뽀찌주기 · 각 ' + ((opt && opt.per_fmt) || '') + '냥';
            } else if (!tip.can) {
                splashTip.hidden = true;
            }
        }
        if (splashPcts && !tip.can) splashPcts.hidden = true;
    }
    function sendTip() {
        ajax('tip', { pct: tipPct });
    }
    function noticeLastTip(t) {
        var flash = t && t.last_tip;
        if (!flash || !flash.text) return;
        var key = String(t.round_no || 0) + ':' + flash.text;
        if (key === lastTipKey) return;
        lastTipKey = key;
        var meNick = t.me && t.me.nick ? t.me.nick : '';
        if (flash.from && flash.from === meNick) return;
        showToast(flash.text);
    }

    var EMO = { smile: '😊', sad: '😢', smoke: '🚬', thumb: '👍', angry: '😡', please: '🤲' };
    var seenReact = {};
    var lastLocalReact = '';
    var reactCool = 0;
    var reactPrimed = false;
    function popReact(nick, emo) {
        var layer = document.getElementById('reactPopLayer');
        if (!layer) return;
        var face = EMO[emo] || emo || '😊';
        var el = document.createElement('div');
        el.className = 'react-pop';
        el.innerHTML = '<div class="emo">' + face + '</div><div class="who">' + escapeHtml(nick || '') + '</div>';
        var jitter = (Math.random() * 48) - 24;
        el.style.marginLeft = jitter + 'px';
        layer.appendChild(el);
        setTimeout(function() {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 1800);
    }
    function noticeReacts(t) {
        var list = (t && t.reacts) || [];
        if (!reactPrimed) {
            list.forEach(function(r) { if (r && r.id) seenReact[r.id] = true; });
            reactPrimed = true;
            return;
        }
        list.forEach(function(r) {
            if (!r || !r.id || seenReact[r.id]) return;
            seenReact[r.id] = true;
            var k = String(r.nick || '') + ':' + String(r.emo || '');
            if (k === lastLocalReact) {
                lastLocalReact = '';
                return;
            }
            popReact(r.nick, r.emo);
        });
        var keys = Object.keys(seenReact);
        if (keys.length > 40) {
            keys.slice(0, keys.length - 24).forEach(function(id) { delete seenReact[id]; });
        }
    }
    function sendReact(emo) {
        var now = Date.now();
        if (now < reactCool) return;
        reactCool = now + 700;
        var meNick = (lastTable && lastTable.me && lastTable.me.nick) ? lastTable.me.nick : '';
        lastLocalReact = meNick + ':' + emo;
        popReact(meNick, emo);
        ajax('react', { emo: emo });
    }

    document.querySelectorAll('.btn-side').forEach(function(b) {
        b.addEventListener('click', function() {
            side = b.getAttribute('data-side');
            document.querySelectorAll('.btn-side').forEach(function(x) { x.classList.remove('active'); });
            b.classList.add('active');
            var el = document.getElementById('betAmount');
            if (el && el.value) setBetAmount(el.value);
        });
    });
    var firstSide = document.querySelector('.btn-side.P');
    if (firstSide) firstSide.classList.add('active');

    function applyPresets(presets) {
        if (!presets || !presets.length) return;
        var btns = document.querySelectorAll('.btn-amt');
        btns.forEach(function(b, i) {
            if (!presets[i]) return;
            var amt = String(presets[i].amt || '');
            var label = String(presets[i].label || amt);
            var wasActive = b.classList.contains('active');
            b.setAttribute('data-amt', amt);
            b.textContent = label;
            b.title = label + '냥';
            if (wasActive) {
                setBetAmount(amt);
            }
        });
    }

    function selectAmtBtn(btn) {
        document.querySelectorAll('.btn-amt, .btn-pct').forEach(function(x) { x.classList.remove('active'); });
        if (btn) btn.classList.add('active');
    }

    document.querySelectorAll('.btn-amt').forEach(function(b) {
        b.addEventListener('click', function() {
            var v = setBetAmount(b.getAttribute('data-amt'));
            if (v === '0' || cmpDigits(v, String(MIN)) < 0) {
                showToast('보유 게임냥이 부족해요.');
                return;
            }
            if (cmpDigits(b.getAttribute('data-amt'), maxBetDigits()) > 0) {
                showToast('가진 게임냥까지만 걸 수 있어요.');
            }
            selectAmtBtn(b);
        });
    });
    document.querySelectorAll('.btn-pct').forEach(function(b) {
        b.addEventListener('click', function() {
            var amt = pctOf(parseInt(b.getAttribute('data-pct'), 10));
            if (amt === '0') { showToast('보유량이 부족해요.'); return; }
            setBetAmount(amt);
            selectAmtBtn(b);
        });
    });
    var betInput = document.getElementById('betAmount');
    if (betInput) {
        betInput.addEventListener('input', function() {
            var raw = digitsOnly(betInput.value);
            var cap = maxBetDigits();
            if (cmpDigits(raw, cap) > 0) {
                raw = cap;
            }
            betInput.value = raw === '0' ? '' : raw;
            selectAmtBtn(null);
        });
    }
    document.getElementById('btnJoin').addEventListener('click', function() { ajax('join'); });
    document.getElementById('btnLeave').addEventListener('click', function() { ajax('leave'); });
    document.getElementById('btnStart').addEventListener('click', function() { ajax('start'); });
    var btnX2Force = document.getElementById('btnX2Force');
    if (btnX2Force) {
        btnX2Force.addEventListener('click', function() { ajax('x2force'); });
    }
    var btnTip = document.getElementById('btnTip');
    if (btnTip) btnTip.addEventListener('click', sendTip);
    document.querySelectorAll('.btn-tip-pct').forEach(function(b) {
        b.addEventListener('click', function() {
            if (b.disabled) return;
            setTipPct(b.getAttribute('data-pct') || '5');
        });
    });
    var splashTipBtn = document.getElementById('splashTip');
    if (splashTipBtn) splashTipBtn.addEventListener('click', sendTip);
    document.querySelectorAll('.btn-react').forEach(function(b) {
        b.addEventListener('click', function() {
            sendReact(b.getAttribute('data-emo') || '');
        });
    });
    document.getElementById('btnBet').addEventListener('click', function() {
        var betSide = side;
        var amt = setBetAmount(document.getElementById('betAmount').value);
        if (amt === '0' || cmpDigits(amt, String(MIN)) < 0) {
            showToast('최소 배팅은 ' + MIN + '냥이에요.');
            return;
        }
        if (cmpDigits(amt, maxBetDigits()) > 0 || digitsOnly(pointDigits) === '0') {
            showToast('가진 게임냥보다 많이 걸 수 없어요.');
            return;
        }
        var nextAmt = amt;
        if (lastTable && lastTable.me && lastTable.me.nick) {
            nextAmt = addDigitStrings(sideAmt(meBetsOf(lastTable), betSide), amt);
            markBetSeen(lastTable.round_no, lastTable.me.nick, betSide, nextAmt);
        }
        throwChips(betSide, document.getElementById('btnBet'), 5);
        ajax('bet', { side: betSide, amount: amt }, function(j) {
            if (j && j.ok) return;
            var layer = document.getElementById('chipFlyLayer');
            if (layer) layer.innerHTML = '';
            if (lastTable && lastTable.me && lastTable.me.nick) {
                delete seenBets[String(lastTable.round_no) + ':' + lastTable.me.nick + ':' + betSide + ':' + digitsOnly(nextAmt)];
            }
            clearChipSpots();
            if (lastTable) {
                (lastTable.seats || []).forEach(function(s) {
                    if (!s.has_bet) return;
                    var n = s.me ? 4 : 3;
                    seatBetSides(s).forEach(function(sd) {
                        for (var i = 0; i < n; i++) landChip(sd);
                    });
                });
            }
        });
    });

    function poll() { ajax('status'); }
    function startGame() {
        if (gameStarted) return;
        gameStarted = true;
        poll();
        setInterval(poll, 1000);
    }

    var noticeEl = document.getElementById('noticeGate');
    var noticeOk = document.getElementById('noticeOk');
    var noticeNo = document.getElementById('noticeNo');
    if (noticeAcked()) {
        if (noticeEl) noticeEl.hidden = true;
        startGame();
    } else if (noticeEl) {
        noticeEl.hidden = false;
    }
    if (noticeOk) {
        noticeOk.addEventListener('click', function() {
            ackNotice();
            if (noticeEl) noticeEl.hidden = true;
            startGame();
        });
    }
    if (noticeNo) {
        noticeNo.addEventListener('click', function() {
            location.href = HOME;
        });
    }
})();
</script>
<?php } ?>
</body>
</html>
