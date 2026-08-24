<?php
/**
 * 섯다 웹 (독립 모듈 — seotda/ 폴더)
 *
 * URL:
 *   /seotda/seotda_web.php?code=XXXX
 *   /page/seotda.php?code=XXXX
 *
 * action=status | solo_start | solo_reveal | solo_next
 *         join_host | ready | leave | pvp_reveal | send_emote
 *         bet_propose | bet_accept | bet_reject | bet_cancel
 */

require_once __DIR__ . '/lib/bootstrap.inc.php';

$SEOTDA_ACTIONS = [
    'status', 'solo_start', 'solo_reveal', 'solo_next',
    'join_host', 'ready', 'leave',
    'pvp_reveal', 'send_emote',
    'bet_propose', 'bet_accept', 'bet_reject', 'bet_cancel',
];

$req_action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
$req_code = isset($_REQUEST['code']) ? trim((string)$_REQUEST['code']) : '';

if (in_array($req_action, $SEOTDA_ACTIONS, true)) {
    if ($req_code === '') {
        seotda_json(['ok' => false, 'data' => '접속 code가 필요합니다.']);
    }
    $auth = seotda_auth($req_code);
    if (!$auth) {
        seotda_json(['ok' => false, 'data' => '유효하지 않은 code입니다.']);
    }
    if (!seotda_access_allowed((int)($auth['point'] ?? 0))) {
        seotda_json(['ok' => false, 'data' => seotda_access_denied_msg()]);
    }
    $nick = seotda_nick($auth['name']);
    if ($nick === '') {
        seotda_json(['ok' => false, 'data' => '닉네임을 확인할 수 없습니다.']);
    }

    $room = seotda_resolve_api_room($nick);
    if (!$room) {
        seotda_json(['ok' => false, 'data' => '방을 만들 수 없습니다. DB 마이그레이션(seotda/schema)을 실행했는지 확인해주세요.']);
    }

    $payload = function (array $room_row, $extra = [], array $opts = []) use ($nick) {
        if (empty($opts['skip_timeouts'])) {
            $room_row = seotda_room_apply_timeouts($room_row);
        }
        $pt = !empty($opts['skip_point']) ? null : seotda_member_point(addslashes($nick));
        $base = [
            'ok' => true,
            'nick' => $nick,
            'room' => seotda_room_public_view($room_row, $nick),
        ];
        if ($pt !== null) {
            $base['point'] = $pt;
            $base['point_fmt'] = seotda_fmt_game($pt);
        }
        if (empty($opts['skip_players'])) {
            $base['players'] = seotda_players_for_match($nick);
        }
        return array_merge($base, $extra);
    };

    $reveal_payload_opts = ['skip_players' => true];

    if ($req_action === 'status') {
        $host_room = seotda_room_by_host($nick);
        if (is_array($host_room)) {
            $host_room = seotda_room_touch($host_room);
        }
        if ($room['host_nick'] === $nick) {
            $room = $host_room ?: $room;
        }
        seotda_json($payload($room, ['type' => 'status']));
    }

    if ($req_action === 'solo_start') {
        $bet = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
        $res = seotda_solo_start($room, $nick, $bet);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'solo_start', 'data' => $res['data']]));
    }

    if ($req_action === 'solo_reveal') {
        $pick1 = isset($_REQUEST['pick1']) ? $_REQUEST['pick1'] : '';
        $pick2 = isset($_REQUEST['pick2']) ? $_REQUEST['pick2'] : '';
        $res = seotda_solo_reveal($room, $nick, $pick1, $pick2);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'solo_reveal', 'data' => $res['data']], $reveal_payload_opts));
    }

    if ($req_action === 'solo_next') {
        $room = seotda_solo_reset_idle($room, $nick);
        seotda_json($payload($room, ['type' => 'solo_next', 'data' => '다음 판 준비']));
    }

    if ($req_action === 'join_host') {
        $host_nick = isset($_REQUEST['host_nick']) ? trim((string)$_REQUEST['host_nick']) : '';
        $bet = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
        $res = seotda_join_host_room($nick, $host_nick, $bet);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '입장 실패']);
        }
        seotda_json($payload($res['room'], [
            'type' => 'join_host',
            'matched_host' => $res['matched_host'] ?? null,
            'data' => $res['data'],
        ]));
    }

    if ($req_action === 'ready') {
        $res = seotda_room_set_ready($room, $nick);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '준비 실패']);
        }
        seotda_json($payload($res['room'] ?? $room, ['type' => 'ready', 'data' => $res['data']]));
    }

    if ($req_action === 'leave') {
        $room = seotda_room_leave_guest($room, $nick);
        if ($room === null) {
            seotda_json(['ok' => true, 'type' => 'leave', 'data' => '방에서 나왔습니다.', 'room' => null]);
        }
        seotda_json($payload($room, ['type' => 'leave', 'data' => '✅ 나갔어요 · 솔로로 복귀']));
    }

    if ($req_action === 'pvp_reveal') {
        $pick1 = isset($_REQUEST['pick1']) ? $_REQUEST['pick1'] : '';
        $pick2 = isset($_REQUEST['pick2']) ? $_REQUEST['pick2'] : '';
        $res = seotda_pvp_reveal($room, $nick, $pick1, $pick2);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'pvp_reveal', 'data' => $res['data']], $reveal_payload_opts));
    }

    if ($req_action === 'send_emote') {
        $emote_key = isset($_REQUEST['emote']) ? trim((string)$_REQUEST['emote']) : '';
        $res = seotda_emote_send($room, $nick, $emote_key);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '전송 실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'send_emote', 'data' => $res['data']]));
    }

    if ($req_action === 'bet_propose') {
        $bet = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
        $res = seotda_bet_propose($room, $nick, $bet);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '요청 실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'bet_propose', 'data' => $res['data']]));
    }

    if ($req_action === 'bet_accept') {
        $res = seotda_bet_respond($room, $nick, true);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '수락 실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'bet_accept', 'data' => $res['data']]));
    }

    if ($req_action === 'bet_reject') {
        $res = seotda_bet_respond($room, $nick, false);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '거절 실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'bet_reject', 'data' => $res['data']]));
    }

    if ($req_action === 'bet_cancel') {
        $res = seotda_bet_cancel($room, $nick);
        if (empty($res['ok'])) {
            seotda_json(['ok' => false, 'data' => $res['data'] ?? '취소 실패']);
        }
        seotda_json($payload($res['room'], ['type' => 'bet_cancel', 'data' => $res['data']]));
    }
}

// ----- 페이지 -----
$seotda_api = '/seotda/seotda_web.php';
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($script, '/page/seotda.php') !== false) {
    $seotda_api = '/page/seotda.php';
}

$seotda_code = $req_code;
$seotda_need_code = ($seotda_code === '');
$seotda_member = null;
$seotda_room_view = null;
$seotda_access_denied = false;
$seotda_access_msg = seotda_access_denied_msg();

if (!$seotda_need_code) {
    $auth_page = seotda_auth($seotda_code);
    if ($auth_page) {
        $nick_page = seotda_nick($auth_page['name']);
        $seotda_member = [
            'nick' => $nick_page,
            'point' => (int)($auth_page['point'] ?? 0),
            'point_fmt' => seotda_fmt_game((int)($auth_page['point'] ?? 0)),
        ];
        if (!seotda_access_allowed($seotda_member['point'])) {
            $seotda_access_denied = true;
        } else {
            $room_page = seotda_resolve_api_room($nick_page);
            if ($room_page) {
                $seotda_room_view = seotda_room_public_view($room_page, $nick_page);
            }
        }
    } else {
        $seotda_need_code = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a0f0a">
    <title>섯다 · 웹</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; font-family: "Apple SD Gothic Neo", sans-serif;
            background: linear-gradient(165deg, #1a0f0a 0%, #2d1810 45%, #0f0a08 100%);
            color: #fde8d0; padding: 12px; padding-bottom: calc(12px + env(safe-area-inset-bottom));
        }
        .wrap { max-width: 420px; margin: 0 auto; }
        h1 { font-size: 1.15rem; margin: 0; color: #fcd34d; }
        .top-bar { margin-bottom: 10px; }
        .top-bar-head {
            display: flex; align-items: baseline; justify-content: space-between;
            gap: 8px; margin-bottom: 8px;
        }
        .top-nick { font-size: 0.74rem; opacity: 0.7; white-space: nowrap; }
        .top-balance {
            text-align: center; padding: 12px 14px;
            background: rgba(0,0,0,0.38);
            border: 1px solid rgba(252,211,77,0.32);
            border-radius: 12px;
        }
        .top-balance-label {
            display: block; font-size: 0.68rem; opacity: 0.62;
            margin-bottom: 4px; letter-spacing: 0.04em;
        }
        .top-balance-value {
            display: block; font-size: 1.75rem; font-weight: 900;
            color: #fcd34d; line-height: 1.1;
            letter-spacing: -0.02em; word-break: keep-all;
        }
        .card {
            background: rgba(0,0,0,0.35); border: 1px solid rgba(252,211,77,0.25);
            border-radius: 14px; padding: 14px; margin-bottom: 12px;
        }
        .card-info { padding: 8px 10px; margin-bottom: 10px; }
        .info-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 3px 8px;
        }
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.72rem; margin: 0; gap: 4px; min-width: 0;
        }
        .info-row.full { grid-column: 1 / -1; }
        .info-row > span:first-child { opacity: 0.62; flex-shrink: 0; }
        .info-row > span:last-child {
            text-align: right; font-weight: 600; min-width: 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .row { display: flex; justify-content: space-between; font-size: 0.9rem; margin: 6px 0; }
        .badge {
            display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 0.65rem;
            background: rgba(252,211,77,0.2); border: 1px solid rgba(252,211,77,0.35);
        }
        .cards { display: flex; gap: 12px; justify-content: center; margin: 8px 0 4px; flex-wrap: wrap; perspective: 800px; }
        .card-pip {
            width: 78px; height: 112px; border-radius: 7px;
            position: relative; overflow: hidden; flex-shrink: 0;
            box-shadow: 0 5px 14px rgba(0,0,0,0.45), 0 1px 0 rgba(255,255,255,0.12) inset;
            border: 2px solid transparent;
            transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s, opacity 0.15s;
            background: #1a120c;
        }
        .card-pip svg { width: 100%; height: 100%; display: block; }
        .card-pip .card-fallback {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
            background: linear-gradient(145deg, #fff7ed, #fed7aa);
            color: #7c2d12; font-weight: 800; font-size: 1.5rem;
        }
        .card-pip.placeholder { opacity: 0.55; filter: saturate(0.7); }
        .card-pip.hidden { border-color: rgba(69,10,10,0.6); }
        .card-pip.hidden.selected {
            border-color: #fbbf24;
            box-shadow: 0 0 0 2px rgba(251,191,36,0.4), 0 8px 18px rgba(0,0,0,0.45);
            transform: translateY(-4px);
        }
        .card-pip.pickable { cursor: pointer; }
        .card-pip.pickable:hover { transform: translateY(-3px); }
        .card-pip.pickable:active { transform: scale(0.96); }
        .card-pip.selected {
            border-color: #fbbf24;
            box-shadow: 0 0 0 2px rgba(251,191,36,0.4), 0 8px 18px rgba(0,0,0,0.45);
            transform: translateY(-4px);
        }
        .card-pip.dim { opacity: 0.42; filter: saturate(0.55) brightness(0.92); transform: scale(0.96); }
        .card-pip.peek-open {
            box-shadow: 0 0 0 2px rgba(96,165,250,0.45), 0 8px 18px rgba(0,0,0,0.45);
        }
        .card-pip.peek-locked {
            cursor: default;
        }
        .card-pip.has-pick-hint { position: relative; }
        .card-pick-hint {
            position: absolute;
            left: 50%;
            top: 50%;
            bottom: auto;
            transform: translate(-50%, -50%);
            z-index: 2;
            min-width: 78px;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1.3;
            text-align: center;
            pointer-events: none;
            box-shadow: 0 4px 14px rgba(0,0,0,0.45);
        }
        .card-pick-hint-sub {
            display: block;
            margin-top: 3px;
            font-size: 0.62rem;
            font-weight: 700;
            opacity: 0.92;
        }
        .card-pick-hint.win {
            background: rgba(22,163,74,0.92);
            color: #ecfdf5;
            border: 1px solid rgba(134,239,172,0.55);
        }
        .card-pick-hint.lose {
            background: rgba(220,38,38,0.92);
            color: #fef2f2;
            border: 1px solid rgba(252,165,165,0.55);
        }
        .card-pick-hint.draw {
            background: rgba(217,119,6,0.92);
            color: #fffbeb;
            border: 1px solid rgba(253,230,138,0.55);
        }
        .pick-hint { text-align: center; font-size: 0.78rem; opacity: 0.85; margin: 0 0 10px; }
        .reveal-actions { margin: 0 0 10px; }
        .reveal-actions .btn { margin-top: 0; padding: 14px; font-size: 1rem; }
        .card-play { padding-bottom: 12px; }
        .btn {
            display: block; width: 100%; padding: 12px; margin-top: 8px; border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
        }
        .btn-primary { background: linear-gradient(180deg, #fbbf24, #d97706); color: #1a0f0a; }
        .btn-secondary { background: rgba(255,255,255,0.08); color: #fde8d0; border: 1px solid rgba(255,255,255,0.15); }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        input {
            width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3); color: #fff; margin-top: 4px;
        }
        label { font-size: 0.78rem; opacity: 0.85; display: block; margin-bottom: 4px; }
        .result {
            white-space: pre-wrap; font-size: 0.82rem; line-height: 1.45;
            background: rgba(0,0,0,0.25); padding: 10px; border-radius: 8px; min-height: 48px;
        }
        .toast {
            position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%) translateY(80px);
            background: rgba(0,0,0,0.88); color: #fff; padding: 10px 16px; border-radius: 999px;
            font-size: 0.85rem; opacity: 0; transition: 0.25s; z-index: 99; max-width: 90%; text-align: center;
        }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .loading { pointer-events: none; opacity: 0.7; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .bet-quick {
            display: flex; gap: 6px; margin-top: 6px;
        }
        .btn-bet {
            flex: 1; min-width: 0; margin-top: 0; padding: 8px 4px; font-size: 0.78rem;
            background: rgba(255,255,255,0.08); color: #fde8d0;
            border: 1px solid rgba(255,255,255,0.15); border-radius: 8px;
            font-weight: 700; cursor: pointer; white-space: nowrap;
        }
        .btn-bet.active {
            background: linear-gradient(180deg, #fbbf24, #d97706); color: #1a0f0a;
            border-color: #fbbf24;
        }
        .bet-actions {
            display: none; flex-direction: column; gap: 8px; margin-top: 10px;
        }
        .bet-actions .btn { margin-top: 0; width: 100%; }
        .panel-title { margin: 0 0 8px; font-size: 0.95rem; color: #fcd34d; }
        .hint { font-size: 0.72rem; opacity: 0.72; margin: 6px 0 0; line-height: 1.4; }
        .player-list {
            max-height: 220px; overflow-y: auto; margin: 8px 0 0;
            border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
        }
        .player-row {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 10px; border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 0.82rem;
        }
        .player-row:last-child { border-bottom: none; }
        .player-info { flex: 1; min-width: 0; }
        .player-nick { font-weight: 700; color: #fde8d0; }
        .player-meta { font-size: 0.72rem; opacity: 0.72; margin-top: 2px; }
        .player-empty { padding: 16px 10px; text-align: center; font-size: 0.78rem; opacity: 0.75; }
        .btn-join {
            flex-shrink: 0; margin: 0; padding: 7px 10px; font-size: 0.75rem;
            border: none; border-radius: 8px; font-weight: 700; cursor: pointer;
            background: linear-gradient(180deg, #fbbf24, #d97706); color: #1a0f0a;
        }
        .btn-join:disabled { opacity: 0.4; cursor: not-allowed; }
        .layer-pop {
            position: fixed; inset: 0; z-index: 200;
            display: flex; align-items: center; justify-content: center;
            padding: 16px; opacity: 0; visibility: hidden; pointer-events: none;
            transition: opacity 0.2s, visibility 0.2s;
        }
        .layer-pop.show { opacity: 1; visibility: visible; pointer-events: auto; }
        .layer-pop .layer-box .btn-primary#readyLayerBtn {
            width: 100%; margin-top: 12px; font-size: 1.05rem; padding: 14px 12px;
        }
        .layer-pop .layer-box .btn-secondary#readyLayerClose {
            width: 100%; margin-top: 8px;
        }
        .layer-backdrop {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.72);
        }
        .layer-box {
            position: relative; width: 100%; max-width: 320px;
            background: linear-gradient(165deg, #2d1810, #1a0f0a);
            border: 1px solid rgba(252,211,77,0.45);
            border-radius: 16px; padding: 22px 18px 16px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.55);
            text-align: center;
            transform: translateY(12px) scale(0.96);
            transition: transform 0.22s;
        }
        .layer-pop.show .layer-box { transform: translateY(0) scale(1); }
        .layer-icon { font-size: 2.4rem; margin-bottom: 8px; }
        .layer-title {
            font-size: 0.82rem; font-weight: 700; color: #fbbf24;
            letter-spacing: 0.04em; margin-bottom: 8px;
        }
        .layer-msg {
            font-size: 1.05rem; font-weight: 800; color: #fde8d0;
            line-height: 1.45; margin-bottom: 8px;
        }
        .layer-sub {
            font-size: 0.78rem; opacity: 0.78; line-height: 1.45;
            margin-bottom: 14px; white-space: pre-wrap;
        }
        .layer-box .btn { margin-top: 0; }
        .layer-pop.result-layer { z-index: 210; }
        .card-play.revealing .cards { pointer-events: none; }
        .card-play.revealing .card-pip.selected,
        .card-play.revealing .card-pip.peek-open {
            animation: revealPulse 0.55s ease-in-out infinite;
        }
        @keyframes revealPulse {
            0%, 100% { box-shadow: 0 0 0 2px rgba(251,191,36,0.35), 0 8px 18px rgba(0,0,0,0.45); }
            50% { box-shadow: 0 0 0 3px rgba(251,191,36,0.65), 0 10px 22px rgba(251,191,36,0.25); }
        }
        .layer-pop.result-layer .layer-box {
            transition: transform 0.12s ease-out;
            max-width: 380px; padding: 28px 20px 20px;
        }
        .layer-pop.result-layer .layer-box.state-win {
            border-color: rgba(251,191,36,0.85);
            box-shadow: 0 0 48px rgba(251,191,36,0.22), 0 16px 48px rgba(0,0,0,0.55);
        }
        .layer-pop.result-layer .layer-box.state-lose {
            border-color: rgba(148,163,184,0.45);
            box-shadow: 0 0 32px rgba(100,116,139,0.18), 0 16px 48px rgba(0,0,0,0.55);
        }
        .layer-pop.result-layer .layer-box.state-draw {
            border-color: rgba(96,165,250,0.55);
            box-shadow: 0 0 32px rgba(96,165,250,0.18), 0 16px 48px rgba(0,0,0,0.55);
        }
        .result-big-icon { font-size: 3.6rem; line-height: 1; margin-bottom: 10px; }
        .result-big-title {
            font-size: 2rem; font-weight: 900; line-height: 1.15;
            margin-bottom: 16px; letter-spacing: -0.02em;
        }
        .result-big-title.win { color: #fcd34d; }
        .result-big-title.lose { color: #cbd5e1; }
        .result-big-title.draw { color: #93c5fd; }
        .result-vs {
            display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px;
            align-items: stretch; margin-bottom: 14px;
        }
        .result-side {
            padding: 12px 8px; border-radius: 12px;
            background: rgba(0,0,0,0.32);
            border: 2px solid transparent;
        }
        .result-side.me { border-color: rgba(252,211,77,0.45); }
        .result-side.winner { background: rgba(251,191,36,0.12); border-color: rgba(251,191,36,0.55); }
        .result-side-name { font-size: 0.82rem; font-weight: 700; margin-bottom: 4px; }
        .result-side-hand { font-size: 1.35rem; font-weight: 900; color: #fcd34d; margin: 4px 0; }
        .result-side-pool { font-size: 0.68rem; opacity: 0.72; line-height: 1.35; word-break: keep-all; }
        .result-vs-mid { font-size: 1.1rem; font-weight: 800; align-self: center; opacity: 0.65; }
        .result-payout {
            font-size: 1.05rem; font-weight: 800; line-height: 1.45;
            margin-bottom: 8px; color: #fde8d0;
        }
        .result-payout.positive { color: #fcd34d; }
        .result-payout.negative { color: #fca5a5; }
        .result-sub {
            font-size: 0.78rem; opacity: 0.75; line-height: 1.45;
            margin-bottom: 12px; white-space: pre-wrap;
        }
        .result-actions { margin-top: 4px; }
        .result-actions.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .result-actions.grid2 .btn { margin-top: 0; }
        .result-actions:not(.grid2) .btn { margin-top: 0; width: 100%; }
        .timer-bar {
            display: none; margin-bottom: 10px; padding: 14px 12px;
            border-radius: 12px; text-align: center;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(252,211,77,0.35);
        }
        .timer-bar.show { display: block; }
        .timer-bar.warn {
            border-color: rgba(248,113,113,0.55);
            background: rgba(127,29,29,0.25);
        }
        .timer-bar-label {
            font-size: 0.78rem; font-weight: 700; color: #fbbf24;
            margin-bottom: 6px;
        }
        .timer-bar.warn .timer-bar-label { color: #fca5a5; }
        .timer-bar-sec {
            font-size: 2.4rem; font-weight: 900; line-height: 1;
            color: #fcd34d; letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .timer-bar.warn .timer-bar-sec { color: #f87171; }
        .timer-bar-sub {
            margin-top: 6px; font-size: 0.72rem; opacity: 0.78; line-height: 1.4;
        }
        .timer-bar-track {
            height: 6px; border-radius: 999px; margin-top: 10px;
            background: rgba(255,255,255,0.12); overflow: hidden;
        }
        .timer-bar-fill {
            height: 100%; width: 100%; border-radius: 999px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            transition: width 0.35s linear;
        }
        .timer-bar.warn .timer-bar-fill {
            background: linear-gradient(90deg, #f87171, #ef4444);
        }
        .emote-bar {
            display: none; margin-top: 10px; padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .emote-bar.show { display: block; }
        .emote-bar-label {
            font-size: 0.72rem; opacity: 0.75; margin-bottom: 8px; text-align: center;
        }
        .emote-btns {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
        }
        .btn-emote {
            margin: 0; padding: 10px 0; font-size: 1.45rem; line-height: 1;
            border: 1px solid rgba(255,255,255,0.12); border-radius: 10px;
            background: rgba(255,255,255,0.06); cursor: pointer;
            transition: transform 0.12s, background 0.12s;
        }
        .btn-emote:active { transform: scale(0.92); background: rgba(251,191,36,0.18); }
        .btn-emote:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
        .emote-float {
            position: fixed; left: 50%; top: 18%;
            transform: translateX(-50%) translateY(12px) scale(0.85);
            z-index: 150; pointer-events: none; text-align: center;
            opacity: 0; visibility: hidden;
            transition: opacity 0.2s, transform 0.25s, visibility 0.2s;
        }
        .emote-float.show {
            opacity: 1; visibility: visible;
            transform: translateX(-50%) translateY(0) scale(1);
        }
        .emote-float-emoji {
            font-size: 3.2rem; line-height: 1;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.45));
            animation: emotePop 0.45s ease-out;
        }
        .emote-float-nick {
            margin-top: 6px; font-size: 0.82rem; font-weight: 700; color: #fcd34d;
            background: rgba(0,0,0,0.55); padding: 4px 10px; border-radius: 999px;
        }
        @keyframes emotePop {
            0% { transform: scale(0.4); opacity: 0; }
            60% { transform: scale(1.12); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .lock-box {
            text-align: center;
            padding: 36px 16px 28px;
            color: #c4a882;
            line-height: 1.65;
        }
        .lock-box strong { display: block; color: #fde8d0; font-size: 1.05rem; margin-bottom: 8px; }
        .lock-box .lock-point {
            margin: 14px 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: #fcd34d;
        }
        .lock-box a {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(252,211,77,0.15);
            border: 1px solid rgba(252,211,77,0.35);
            color: #fcd34d;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <?php if ($seotda_need_code) { ?>
        <h1>🃏 섯다 웹</h1>
        <p class="sub">회원 code로 접속하세요.<br>예) <code>/seotda/seotda_web.php?code=XXXX</code></p>
    <?php } elseif ($seotda_access_denied) { ?>
        <h1>🃏 섯다</h1>
        <div class="card lock-box">
            <strong><?php echo htmlspecialchars($seotda_access_msg, ENT_QUOTES, 'UTF-8'); ?></strong>
            <div><?php echo htmlspecialchars($seotda_member['nick'], ENT_QUOTES, 'UTF-8'); ?> · 보유 게임냥</div>
            <div class="lock-point"><?php echo htmlspecialchars($seotda_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div>게임냥 <?php echo htmlspecialchars(seotda_fmt_game((int)SEOTDA_MIN_ACCESS), ENT_QUOTES, 'UTF-8'); ?> 이상부터 입장할 수 있어요.</div>
            <a href="/page/wallet.php?code=<?php echo htmlspecialchars($seotda_code, ENT_QUOTES, 'UTF-8'); ?>">지갑으로 돌아가기</a>
        </div>
    <?php } else { ?>
        <div class="top-bar">
            <div class="top-bar-head">
                <h1>🃏 섯다</h1>
                <span class="top-nick" id="stNick"><?php echo htmlspecialchars($seotda_member['nick'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="top-balance">
                <span class="top-balance-label">보유 게임냥</span>
                <span class="top-balance-value" id="stPoint"><?php echo htmlspecialchars($seotda_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="card card-info">
            <div class="info-grid">
                <div class="info-row"><span>모드</span><span class="badge" id="stMode">—</span></div>
                <div class="info-row"><span>배팅</span><span id="stBet">—</span></div>
                <div class="info-row" id="rowRound" style="display:none"><span>판수</span><span id="stRound">—</span></div>
                <div class="info-row" id="rowReady" style="display:none"><span>준비</span><span id="stReady">—</span></div>
                <div class="info-row" id="rowWaitLeft" style="display:none"><span>대기</span><span id="stWaitLeft">—</span></div>
                <div class="info-row" id="rowGuestAction" style="display:none"><span>선택</span><span id="stGuestAction">—</span></div>
                <div class="info-row" id="rowReadyWait" style="display:none"><span>응답</span><span id="stReadyWait">—</span></div>
                <div class="info-row full"><span>상대</span><span id="stGuest">—</span></div>
            </div>
        </div>

        <div class="card card-play">
            <p class="pick-hint" id="pickHint">랜덤 1장 자동 선택 · 나머지 1장 고르면 바로 오픈</p>
            <div class="cards" id="cardArea"></div>
            <div class="reveal-actions">
                <button type="button" class="btn btn-primary" id="btnSoloStart" style="display:none">솔로 시작</button>
                <button type="button" class="btn btn-primary" id="btnSoloReveal" style="display:none" hidden aria-hidden="true">확정 오픈</button>
                <button type="button" class="btn btn-primary" id="btnPvpReveal" style="display:none" hidden aria-hidden="true">확정 오픈</button>
            </div>
            <div class="result" id="stResult"><?php echo htmlspecialchars($seotda_room_view['last_result'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="card" id="panelBet">
            <label id="betPanelLabel">배팅 금액 (솔로 · 방 찾기 공통)</label>
            <div class="bet-quick">
                <button type="button" class="btn-bet active" data-bet="50조">50조</button>
                <button type="button" class="btn-bet" data-bet="100조">100조</button>
                <button type="button" class="btn-bet" data-bet="1000조">1000조</button>
                <button type="button" class="btn-bet" data-bet="5000조">5000조</button>
            </div>
            <input type="hidden" id="betAmount" value="50조">
            <p class="hint" id="betHint"></p>
            <div class="bet-actions" id="betActions">
                <button type="button" class="btn btn-secondary" id="btnBetPropose">배팅 변경 요청</button>
                <button type="button" class="btn btn-secondary" id="btnBetCancel" style="display:none">요청 취소</button>
            </div>
        </div>

        <div class="card" id="panelMatch">
            <h3 class="panel-title">👥 1:1 상대 찾기</h3>
            <div class="timer-bar" id="timerBar">
                <div class="timer-bar-label" id="timerBarLabel">⏱ 준비 응답</div>
                <div class="timer-bar-sec" id="timerBarSec">30</div>
                <div class="timer-bar-sub" id="timerBarSub">—</div>
                <div class="timer-bar-track"><div class="timer-bar-fill" id="timerBarFill"></div></div>
            </div>
            <p class="hint" id="matchHint">섯다 접속 중인 유저 · 입장 버튼으로 대결</p>
            <div class="player-list" id="playerList">
                <div class="player-empty">목록 불러오는 중…</div>
            </div>
            <div class="grid2" style="margin-top:10px">
                <button type="button" class="btn btn-secondary" id="btnReady">✅ 1:1 준비</button>
                <button type="button" class="btn btn-secondary" id="btnLeave">나가기</button>
            </div>
            <div class="emote-bar" id="emoteBar">
                <div class="emote-bar-label">상대에게 보내기</div>
                <div class="emote-btns" id="emoteBtns"></div>
            </div>
        </div>
    <?php } ?>
</div>
<div class="emote-float" id="emoteFloat" aria-hidden="true">
    <div class="emote-float-emoji" id="emoteFloatEmoji"></div>
    <div class="emote-float-nick" id="emoteFloatNick"></div>
</div>
<div class="toast" id="toast"></div>
<div class="layer-pop" id="joinLayer" aria-hidden="true">
    <div class="layer-backdrop" id="joinLayerBackdrop"></div>
    <div class="layer-box">
        <div class="layer-icon">👋</div>
        <div class="layer-title" id="joinLayerTitle">입장 알림</div>
        <div class="layer-msg" id="joinLayerMsg">—</div>
        <div class="layer-sub" id="joinLayerSub"></div>
        <button type="button" class="btn btn-primary" id="joinLayerClose">확인</button>
    </div>
</div>
<div class="layer-pop" id="readyLayer" aria-hidden="true">
    <div class="layer-backdrop" id="readyLayerBackdrop"></div>
    <div class="layer-box">
        <div class="layer-icon">⚔️</div>
        <div class="layer-title" id="readyLayerTitle">1:1 대결</div>
        <div class="layer-msg" id="readyLayerMsg">—</div>
        <div class="layer-sub" id="readyLayerSub">아래 버튼을 눌러 준비해주세요.</div>
        <button type="button" class="btn btn-primary" id="readyLayerBtn">✅ 1:1 준비</button>
        <button type="button" class="btn btn-secondary" id="readyLayerClose">나중에</button>
    </div>
</div>
<div class="layer-pop" id="betPendingLayer" aria-hidden="true">
    <div class="layer-backdrop" id="betPendingBackdrop"></div>
    <div class="layer-box">
        <div class="layer-icon">💰</div>
        <div class="layer-title" id="betPendingTitle">배팅금 변경</div>
        <div class="layer-msg" id="betPendingMsg">—</div>
        <div class="layer-sub" id="betPendingSub">수락하시겠습니까?</div>
        <div class="grid2" style="margin-top:12px">
            <button type="button" class="btn btn-primary" id="betPendingAccept">수락</button>
            <button type="button" class="btn btn-secondary" id="betPendingReject">거절</button>
        </div>
    </div>
</div>
<div class="layer-pop result-layer" id="resultLayer" aria-hidden="true">
    <div class="layer-backdrop" id="resultLayerBackdrop"></div>
    <div class="layer-box" id="resultLayerBox">
        <div class="result-big-icon" id="resultLayerIcon">🏆</div>
        <div class="result-big-title" id="resultLayerTitle">승리!</div>
        <div class="result-vs" id="resultLayerVs"></div>
        <div class="result-payout" id="resultLayerPayout"></div>
        <div class="result-sub" id="resultLayerSub"></div>
        <div class="result-actions" id="resultLayerActions">
            <button type="button" class="btn btn-primary" id="resultLayerQuick" style="display:none">바로 시작</button>
            <button type="button" class="btn btn-secondary" id="resultLayerClose">확인</button>
        </div>
    </div>
</div>

<?php if (!$seotda_need_code && !$seotda_access_denied) {
    seotda_card_ui_script();
?>
<script>
(function() {
    var CODE = <?php echo json_encode($seotda_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($seotda_api, JSON_UNESCAPED_UNICODE); ?>;
    var INITIAL_POINT = <?php echo (int)($seotda_member['point'] ?? 0); ?>;
    var BET_1GYEONG = 10000000000000000;
    var BASE_BET_BUTTONS = ['50조', '100조', '1000조', '5000조'];
    var HIGH_BET_BUTTON = '1경';
    var POLL = {
        browse: <?php echo (int)SEOTDA_POLL_BROWSE_MS; ?>,
        match: <?php echo (int)SEOTDA_POLL_MATCH_MS; ?>,
        solo: <?php echo (int)SEOTDA_POLL_SOLO_MS; ?>
    };

    function betValue() {
        return (document.getElementById('betAmount').value || '50조').trim();
    }

    function setBetAmount(label) {
        document.getElementById('betAmount').value = label;
        document.querySelectorAll('.btn-bet').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-bet') === label);
        });
    }

    function renderBetButtons(point) {
        point = Math.max(0, parseInt(point, 10) || 0);
        var labels = BASE_BET_BUTTONS.slice();
        if (point >= BET_1GYEONG) {
            labels.push(HIGH_BET_BUTTON);
        }
        var wrap = document.querySelector('.bet-quick');
        if (!wrap) return;
        var key = labels.join('|');
        if (renderBetButtons._key === key) return;
        renderBetButtons._key = key;
        var cur = betValue();
        wrap.innerHTML = labels.map(function(label) {
            var active = label === cur ? ' active' : '';
            return '<button type="button" class="btn-bet' + active + '" data-bet="' + label + '">' + label + '</button>';
        }).join('');
        wrap.querySelectorAll('.btn-bet').forEach(function(btn) {
            btn.onclick = function() {
                setBetAmount(btn.getAttribute('data-bet'));
            };
        });
    }

    renderBetButtons(INITIAL_POINT);

    var TIMER = { type: null, left: 0, max: 30, syncedAt: 0 };
    var READY_RESPONSE_MAX = 30;
    var GUEST_ACTION_MAX = 30;
    var EMOTE_COOLDOWN_MS = 3000;
    var emoteSending = false;
    var emoteCooldownUntil = 0;
    var emoteFloatTimer = null;

    var DEFAULT_EMOTES = [
        { key: 'wave', emoji: '👋' },
        { key: 'laugh', emoji: '😂' },
        { key: 'angry', emoji: '😤' },
        { key: 'thumbs', emoji: '👍' },
        { key: 'pray', emoji: '🙏' },
        { key: 'fire', emoji: '🔥' },
        { key: 'skull', emoji: '💀' },
        { key: 'party', emoji: '🎉' }
    ];

    function renderEmoteButtons(catalog) {
        var list = catalog && catalog.length ? catalog : DEFAULT_EMOTES;
        var sig = list.map(function(item) { return item.key; }).join(',');
        if (renderEmoteButtons._sig === sig) {
            updateEmoteButtonsState();
            return;
        }
        renderEmoteButtons._sig = sig;
        var el = document.getElementById('emoteBtns');
        if (!el) return;
        el.innerHTML = list.map(function(item) {
            return '<button type="button" class="btn-emote" data-emote="' + item.key + '" title="보내기">' +
                item.emoji + '</button>';
        }).join('');
        el.querySelectorAll('.btn-emote').forEach(function(btn) {
            btn.onclick = function() {
                if (emoteSending || Date.now() < emoteCooldownUntil) return;
                var emoteKey = btn.getAttribute('data-emote');
                var emojiChar = (btn.textContent || '').trim();
                ajax('send_emote', { emote: emoteKey }, function(j) {
                    if (j && j.ok) {
                        emoteCooldownUntil = Date.now() + EMOTE_COOLDOWN_MS;
                        updateEmoteButtonsState();
                        showOpponentEmote({
                            emoji: emojiChar,
                            from_nick: j.nick || '나',
                            stamp: 'self:' + Date.now()
                        });
                    }
                });
            };
        });
        updateEmoteButtonsState();
    }

    function updateEmoteButtonsState() {
        var disabled = emoteSending || Date.now() < emoteCooldownUntil;
        document.querySelectorAll('.btn-emote').forEach(function(btn) {
            btn.disabled = disabled;
        });
    }

    function showOpponentEmote(oe) {
        if (!oe || !oe.emoji) return;
        var key = (oe.from_nick || '') + '|' + oe.emoji + '|' + (oe.stamp || oe.at || '');
        if (applyState._lastOpponentEmoteKey === key) return;
        applyState._lastOpponentEmoteKey = key;

        var box = document.getElementById('emoteFloat');
        document.getElementById('emoteFloatEmoji').textContent = oe.emoji;
        document.getElementById('emoteFloatNick').textContent = (oe.from_nick || '상대') + '님';
        box.classList.add('show');
        box.setAttribute('aria-hidden', 'false');
        clearTimeout(emoteFloatTimer);
        emoteFloatTimer = setTimeout(function() {
            box.classList.remove('show');
            box.setAttribute('aria-hidden', 'true');
        }, 4200);
    }

    function showBetPendingLayer() {
        var layer = document.getElementById('betPendingLayer');
        if (!layer) return;
        layer.classList.add('show');
        layer.setAttribute('aria-hidden', 'false');
    }

    function hideBetPendingLayer() {
        var layer = document.getElementById('betPendingLayer');
        if (!layer) return;
        layer.classList.remove('show');
        layer.setAttribute('aria-hidden', 'true');
    }

    function maybeShowBetPending(r) {
        var bp = r.bet_pending;
        if (!bp || !bp.need_response) {
            hideBetPendingLayer();
            applyState._betPendingShownKey = null;
            return;
        }
        var key = (bp.by_nick || '') + '|' + (bp.amount || 0) + '|' + (bp.stamp || 0);
        if (applyState._betPendingShownKey === key) return;
        applyState._betPendingShownKey = key;
        document.getElementById('betPendingTitle').textContent = '배팅금 변경 요청';
        document.getElementById('betPendingMsg').textContent =
            bp.by_nick + '님이 배팅금을 ' + bp.amount_fmt + '로 변경 요청했어요.';
        document.getElementById('betPendingSub').textContent = '수락하시겠습니까?';
        showBetPendingLayer();
    }

    function updateBetPanel(r) {
        var inPvpMatch = !!r.guest_nick && r.guest_nick !== 'SYSTEM';
        var canChange = !!r.bet_change_allowed;
        var betActions = document.getElementById('betActions');
        var betHint = document.getElementById('betHint');
        var btnPropose = document.getElementById('btnBetPropose');
        var btnCancel = document.getElementById('btnBetCancel');
        var betLabel = document.getElementById('betPanelLabel');

        if (betLabel) {
            betLabel.textContent = inPvpMatch
                ? '배팅 금액 (1:1 · 변경 시 상대 수락)'
                : '배팅 금액 (솔로 · 방 찾기 공통)';
        }
        if (betActions) {
            betActions.style.display = (inPvpMatch && canChange) ? 'flex' : 'none';
        }

        var bp = r.bet_pending;
        if (betHint) {
            if (bp && bp.by_me) {
                betHint.textContent = '⏳ ' + bp.amount_fmt + ' 변경 요청 · 상대 수락 대기';
            } else if (bp && bp.need_response) {
                betHint.textContent = '💰 상대 배팅 변경 요청 · 팝업에서 수락/거절';
            } else if (inPvpMatch && canChange) {
                betHint.textContent = '금액 선택 후 「배팅 변경 요청」 · 현재 ' + (r.bet_fmt || '—');
            } else if (inPvpMatch) {
                betHint.textContent = '대결 진행 중에는 배팅 변경 불가 · 현재 ' + (r.bet_fmt || '—');
            } else {
                betHint.textContent = '';
            }
        }
        if (btnPropose) {
            btnPropose.disabled = !!(bp && (bp.by_me || bp.need_response));
        }
        if (btnCancel) {
            btnCancel.style.display = (bp && bp.by_me) ? 'block' : 'none';
        }
    }

    function syncTimerFromRoom(r) {
        if (!r) {
            TIMER.type = null;
            return;
        }
        var oneReady = r.mode === 'waiting'
            && ((r.host_ready && !r.guest_ready) || (!r.host_ready && r.guest_ready));
        if (r.ready_wait_active && oneReady) {
            TIMER.type = 'ready_wait';
            TIMER.left = r.ready_wait_left || 0;
            TIMER.max = READY_RESPONSE_MAX;
            TIMER.syncedAt = Date.now();
            return;
        }
        if (r.my_role === 'guest' && r.guest_action_active) {
            TIMER.type = 'guest_action';
            TIMER.left = r.guest_action_left || 0;
            TIMER.max = GUEST_ACTION_MAX;
            TIMER.syncedAt = Date.now();
            return;
        }
        TIMER.type = null;
    }

    function getTimerLeftNow() {
        if (!TIMER.type) return 0;
        var elapsed = Math.floor((Date.now() - TIMER.syncedAt) / 1000);
        return Math.max(0, TIMER.left - elapsed);
    }

    function renderTimerBar(r) {
        var bar = document.getElementById('timerBar');
        if (!bar || !r) return;
        var left = getTimerLeftNow();
        if (!TIMER.type) {
            bar.classList.remove('show', 'warn');
            return;
        }
        bar.classList.add('show');
        bar.classList.toggle('warn', left <= 5);
        document.getElementById('timerBarSec').textContent = String(left);
        var pct = TIMER.max > 0 ? Math.max(0, Math.min(100, (left / TIMER.max) * 100)) : 0;
        document.getElementById('timerBarFill').style.width = pct + '%';

        if (TIMER.type === 'ready_wait') {
            var iReady = (r.my_role === 'host' && r.host_ready) || (r.my_role === 'guest' && r.guest_ready);
            document.getElementById('timerBarLabel').textContent = iReady ? '⏱ 상대 준비 대기' : '⏱ 1:1 준비';
            document.getElementById('timerBarSub').textContent = iReady
                ? ('상대 「1:1 준비」 응답 · ' + left + '초 후 자동 퇴장')
                : (left + '초 내 「1:1 준비」 · 미응답 시 자동 퇴장');
        } else if (TIMER.type === 'guest_action') {
            var actionLabel = r.mode === 'pvp' ? '패 선택' : '1:1 준비';
            document.getElementById('timerBarLabel').textContent = '⏱ 선택 제한';
            document.getElementById('timerBarSub').textContent =
                left + '초 내 「' + actionLabel + '」 · 미선택 시 자동 퇴장';
        }
    }

    function toast(msg) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(t._tm);
        t._tm = setTimeout(function() { t.classList.remove('show'); }, 2800);
    }

    function joinLayerSubText(mode) {
        if (mode === 'match_pending') {
            return '호스트 솔로 판이 끝나면\n1:1 대기로 전환됩니다.\n준비 팝업이 다시 뜹니다.';
        }
        if (mode === 'waiting') {
            return '양쪽 「1:1 준비」 후 대결이 시작됩니다.';
        }
        return '';
    }

    function needsReadyPrompt(r) {
        if (!r || r.mode !== 'waiting') return false;
        if (!r.guest_nick || r.guest_nick === 'SYSTEM') return false;
        if (r.my_role !== 'host' && r.my_role !== 'guest') return false;
        var myReady = (r.my_role === 'host' && r.host_ready) || (r.my_role === 'guest' && r.guest_ready);
        return !myReady;
    }

    function readyLayerKey(r) {
        if (!needsReadyPrompt(r)) return null;
        return (r.room_token || '') + ':ready:' + (r.round_no || 0) + ':' + (r.guest_nick || '');
    }

    function readyLayerTimerText(r) {
        var left = getTimerLeftNow();
        if (left <= 0 && r.guest_action_left) left = r.guest_action_left;
        if (left <= 0 && r.ready_wait_left) left = r.ready_wait_left;
        if (left <= 0) left = GUEST_ACTION_MAX;
        return '⏱ ' + left + '초 내 「1:1 준비」 · 미응답 시 자동 퇴장';
    }

    function updateReadyLayerUi(r) {
        var layer = document.getElementById('readyLayer');
        if (!layer || !layer.classList.contains('show') || !r) return;
        var btn = document.getElementById('readyLayerBtn');
        var sub = document.getElementById('readyLayerSub');
        var myReady = (r.my_role === 'host' && r.host_ready) || (r.my_role === 'guest' && r.guest_ready);
        if (btn) {
            btn.disabled = r.mode !== 'waiting' || myReady;
            if (myReady) {
                btn.textContent = '⏳ 준비 완료 · 상대 대기';
            } else if (r.pvp_phase === 'done') {
                btn.textContent = '✅ 다음 판 준비';
            } else {
                btn.textContent = '✅ 1:1 준비';
            }
        }
        if (sub) sub.textContent = readyLayerTimerText(r);
    }

    function showReadyLayer(r, title) {
        if (!needsReadyPrompt(r)) return;
        var opp = r.my_role === 'host' ? r.guest_nick : r.host_nick;
        document.getElementById('readyLayerTitle').textContent = title || '1:1 대결';
        document.getElementById('readyLayerMsg').textContent =
            (opp ? opp + '님' : '상대') + ' · 배팅 ' + (r.bet_fmt || '—');
        document.getElementById('readyLayerSub').textContent = readyLayerTimerText(r);
        document.getElementById('readyLayer').classList.add('show');
        document.getElementById('readyLayer').setAttribute('aria-hidden', 'false');
        applyState._readyLayerShownKey = readyLayerKey(r);
        updateReadyLayerUi(r);
    }

    function hideReadyLayer() {
        var layer = document.getElementById('readyLayer');
        if (!layer) return;
        layer.classList.remove('show');
        layer.setAttribute('aria-hidden', 'true');
    }

    function maybeShowReadyLayer(r) {
        var key = readyLayerKey(r);
        if (!key) {
            hideReadyLayer();
            return;
        }
        if (applyState._readyLayerDismissed === key) return;
        if (applyState._readyLayerShownKey === key && document.getElementById('readyLayer').classList.contains('show')) {
            updateReadyLayerUi(r);
            return;
        }
        var title = '1:1 준비';
        if (applyState._prevMode === 'match_pending' && r.mode === 'waiting') {
            title = '대기 시작';
        } else if (r.pvp_phase === 'done') {
            title = '다음 판 준비';
        } else if (applyState._lastAction === 'join_host' || r.my_role === 'guest') {
            title = '입장 완료';
        } else if (r.my_role === 'host') {
            title = '상대 입장';
        }
        showReadyLayer(r, title);
    }

    function showJoinLayer(title, msg, sub) {
        document.getElementById('joinLayerTitle').textContent = title;
        document.getElementById('joinLayerMsg').textContent = msg;
        document.getElementById('joinLayerSub').textContent = sub || '';
        document.getElementById('joinLayer').classList.add('show');
        document.getElementById('joinLayer').setAttribute('aria-hidden', 'false');
    }

    function hideJoinLayer() {
        document.getElementById('joinLayer').classList.remove('show');
        document.getElementById('joinLayer').setAttribute('aria-hidden', 'true');
    }

    document.getElementById('joinLayerClose').onclick = hideJoinLayer;
    document.getElementById('joinLayerBackdrop').onclick = hideJoinLayer;

    function notifyGuestJoined(r, guestNick) {
        if (!guestNick) return;
        var hostNick = r.host_nick || '';
        if (r.my_role === 'host') {
            if (needsReadyPrompt(r)) {
                showReadyLayer(r, '상대 입장');
            } else {
                showJoinLayer('상대 입장', guestNick + '님이 입장했습니다', joinLayerSubText(r.mode));
            }
            return;
        }
        if (r.my_role === 'guest') {
            if (needsReadyPrompt(r)) {
                showReadyLayer(r, '입장 완료');
            } else {
                showJoinLayer('입장 완료', hostNick + '님 방에 입장했습니다', joinLayerSubText(r.mode));
            }
        }
    }

    function notifyJoinHostResponse(j) {
        if (!j || !j.room) return;
        var r = j.room;
        if (needsReadyPrompt(r)) {
            showReadyLayer(r, '입장 완료');
            return;
        }
        var hostNick = j.matched_host || j.room.host_nick || '';
        var guestNick = j.nick || '';
        showJoinLayer(
            '입장 완료',
            guestNick + ' → ' + hostNick + '님 방 입장',
            joinLayerSubText(j.room.mode)
        );
    }

    function hideResultLayer() {
        document.getElementById('resultLayer').classList.remove('show');
        document.getElementById('resultLayer').setAttribute('aria-hidden', 'true');
    }

    var resultLayerQuickMode = null;

    function setResultLayerQuick(mode) {
        var quick = document.getElementById('resultLayerQuick');
        var actions = document.getElementById('resultLayerActions');
        resultLayerQuickMode = mode || null;
        if (mode === 'solo_restart') {
            quick.style.display = 'block';
            quick.textContent = '바로 시작';
            actions.classList.add('grid2');
        } else if (mode === 'pvp_ready') {
            quick.style.display = 'block';
            quick.textContent = '바로 대결';
            actions.classList.add('grid2');
        } else {
            quick.style.display = 'none';
            actions.classList.remove('grid2');
        }
    }

    function showGameResultLayer(opts) {
        var box = document.getElementById('resultLayerBox');
        box.className = 'layer-box state-' + opts.state;

        document.getElementById('resultLayerIcon').textContent = opts.icon;
        var titleEl = document.getElementById('resultLayerTitle');
        titleEl.className = 'result-big-title ' + opts.state;
        titleEl.textContent = opts.title;

        document.getElementById('resultLayerVs').innerHTML = opts.vsHtml;

        var payoutEl = document.getElementById('resultLayerPayout');
        payoutEl.className = 'result-payout' + (opts.payoutClass ? (' ' + opts.payoutClass) : '');
        payoutEl.textContent = opts.payoutText;

        document.getElementById('resultLayerSub').textContent = opts.subText || '';
        setResultLayerQuick(opts.quickMode || null);

        document.getElementById('resultLayer').classList.add('show');
        document.getElementById('resultLayer').setAttribute('aria-hidden', 'false');
    }

    function resultSideHtml(nick, label, pool, isMe, isWinner) {
        var cls = 'result-side' + (isMe ? ' me' : '') + (isWinner ? ' winner' : '');
        return '<div class="' + cls + '">' +
            '<div class="result-side-name">' + nick + (isMe ? ' (나)' : '') + '</div>' +
            '<div class="result-side-hand">' + label + '</div>' +
            '<div class="result-side-pool">' + pool + '</div>' +
            '</div>';
    }

    document.getElementById('resultLayerClose').onclick = hideResultLayer;
    document.getElementById('resultLayerBackdrop').onclick = hideResultLayer;

    document.getElementById('resultLayerQuick').onclick = function() {
        if (resultLayerQuickMode === 'solo_restart') {
            hideResultLayer();
            resetPickState();
            ajax('solo_start', { amount: betValue() });
            return;
        }
        if (resultLayerQuickMode === 'pvp_ready') {
            hideResultLayer();
            resetPickState();
            ajax('ready');
        }
    };

    function showPvpResultLayer(r, viewerNick) {
        var pr = r.pvp_result;
        if (!pr || !viewerNick) return;

        var isDraw = !!pr.is_draw;
        var isWin = !isDraw && pr.winner_nick === viewerNick;
        var state = isDraw ? 'draw' : (isWin ? 'win' : 'lose');

        var myNick = (r.my_role === 'host') ? pr.host_nick : pr.guest_nick;
        var oppNick = (r.my_role === 'host') ? pr.guest_nick : pr.host_nick;
        var myLabel = (r.my_role === 'host') ? pr.host_label : pr.guest_label;
        var oppLabel = (r.my_role === 'host') ? pr.guest_label : pr.host_label;
        var myPool = (r.my_role === 'host') ? pr.host_pool : pr.guest_pool;
        var oppPool = (r.my_role === 'host') ? pr.guest_pool : pr.host_pool;
        var myWin = pr.winner_nick === myNick;
        var oppWin = pr.winner_nick === oppNick;

        var payoutText;
        var payoutClass = '';
        if (isDraw) {
            var myRefund = (r.my_role === 'host')
                ? (pr.host_refund_fmt || pr.refund_fmt)
                : (pr.guest_refund_fmt || pr.refund_fmt);
            payoutText = '환급 ' + myRefund + ' · 금고 ' + (pr.vault_fmt || '') + ' · 로또 ' + (pr.lotto_fmt || '');
        } else if (isWin) {
            payoutClass = 'positive';
            payoutText = '+' + pr.bet_fmt + ' (총 ' + pr.pot_fmt + ' 수령)';
        } else {
            payoutClass = 'negative';
            payoutText = '-' + pr.bet_fmt;
        }

        var roundTxt = pr.round_no ? (pr.round_no + '판 · ') : '';
        showGameResultLayer({
            state: state,
            icon: isDraw ? '🤝' : (isWin ? '🏆' : '💔'),
            title: isDraw ? '무승부' : (isWin ? '승리!' : '패배'),
            vsHtml: resultSideHtml(myNick, myLabel, myPool, true, myWin) +
                '<div class="result-vs-mid">VS</div>' +
                resultSideHtml(oppNick, oppLabel, oppPool, false, oppWin),
            payoutText: payoutText,
            payoutClass: payoutClass,
            subText: roundTxt + '「바로 대결」 또는 「1:1 준비」 · 종료: 「나가기」',
            quickMode: (r.mode === 'waiting' && r.guest_nick) ? 'pvp_ready' : null
        });
    }

    function showSoloResultLayer(r, viewerNick) {
        var sr = r.solo_result;
        if (!sr || !viewerNick || r.my_role !== 'host') return;

        var isDraw = !!sr.is_draw;
        var isWin = !isDraw && sr.winner_nick === viewerNick;
        var state = isDraw ? 'draw' : (isWin ? 'win' : 'lose');

        var myWin = isWin;
        var sysWin = !isDraw && sr.winner_nick === 'SYSTEM';

        var payoutText;
        var payoutClass = '';
        if (isDraw) {
            payoutText = '환급 ' + sr.refund_fmt + ' · 금고 ' + sr.vault_fmt + ' · 로또 ' + (sr.lotto_fmt || '');
        } else if (isWin) {
            payoutClass = 'positive';
            payoutText = '+' + sr.bet_fmt + ' (총 ' + (sr.pot_fmt || sr.bet_fmt) + ' 수령)';
        } else {
            payoutClass = 'negative';
            payoutText = '-' + sr.bet_fmt;
        }

        var roundTxt = sr.round_no ? (sr.round_no + '판 · ') : '';
        var soloOn = r.mode === 'solo' || r.mode === 'match_pending';
        showGameResultLayer({
            state: state,
            icon: isDraw ? '🤝' : (isWin ? '🏆' : '💔'),
            title: isDraw ? '무승부' : (isWin ? '승리!' : '패배'),
            vsHtml: resultSideHtml(sr.host_nick, sr.host_label, sr.host_pool, true, myWin) +
                '<div class="result-vs-mid">VS</div>' +
                resultSideHtml(sr.sys_nick, sr.sys_label, sr.sys_pool, false, sysWin),
            payoutText: payoutText,
            payoutClass: payoutClass,
            subText: roundTxt + '「바로 시작」으로 연속 플레이',
            quickMode: soloOn && !r.guest_nick ? 'solo_restart' : null
        });
    }

    function maybeShowPvpResult(j) {
        if (!j || !j.room || !j.room.pvp_result || !j.nick) return;
        var rid = j.room.pvp_result.result_id;
        if (!rid) return;
        if (!applyState._initialized) {
            applyState._lastResultId = rid;
            return;
        }
        if (applyState._lastResultId === rid) return;
        applyState._lastResultId = rid;
        showPvpResultLayer(j.room, j.nick);
    }

    function maybeShowSoloResult(j) {
        if (!j || !j.room || !j.room.solo_result || !j.nick) return;
        if (j.room.my_role !== 'host') return;
        var rid = j.room.solo_result.result_id;
        if (!rid) return;
        if (!applyState._initialized) {
            applyState._lastSoloResultId = rid;
            return;
        }
        if (applyState._lastSoloResultId === rid) return;
        applyState._lastSoloResultId = rid;
        showSoloResultLayer(j.room, j.nick);
    }

    var selectedSlots = [];
    var pickLocked = false;
    var revealSubmitting = false;
    var lockedPeek = { slot: 0, card: '', session: '' };

    function isInPickPhase(r) {
        if (!r) return false;
        if (r.in_pick_phase) return true;
        if (r.solo_phase === 'playing') return true;
        if (r.mode === 'pvp' && r.pvp_phase === 'playing' && !r.my_picked) return true;
        return false;
    }

    function pickSessionKey(r) {
        if (!isInPickPhase(r)) return '';
        return (r.room_token || '') + ':' + (r.round_no || 0) + ':' +
            (r.solo_phase === 'playing' ? 'solo' : 'pvp');
    }

    function resetPickState() {
        selectedSlots = [];
        pickLocked = false;
        revealSubmitting = false;
        lockedPeek = { slot: 0, card: '', session: '' };
    }

    function lockPeekFromRoom(r, force) {
        var peek = peekSlotOf(r);
        if (!peek || !r.peek_card) return;
        var session = pickSessionKey(r);
        if (!session) return;
        if (force || lockedPeek.session !== session || !lockedPeek.slot) {
            lockedPeek = { slot: peek, card: r.peek_card, session: session };
        }
    }

    function activePeek(r) {
        var session = pickSessionKey(r);
        if (lockedPeek.slot && lockedPeek.session === session) {
            return { slot: lockedPeek.slot, card: lockedPeek.card };
        }
        lockPeekFromRoom(r, false);
        if (lockedPeek.slot && lockedPeek.session === session) {
            return { slot: lockedPeek.slot, card: lockedPeek.card };
        }
        var peek = peekSlotOf(r);
        if (peek && r.peek_card) {
            return { slot: peek, card: r.peek_card };
        }
        return { slot: 0, card: '' };
    }

    function peekSlotOf(r) {
        if (!r || !r.peek_slot) return 0;
        var peek = parseInt(r.peek_slot, 10);
        return (peek >= 1 && peek <= 3) ? peek : 0;
    }

    function syncPickSelection(r) {
        if (r.my_picks && r.my_picks.length === 2) {
            selectedSlots = r.my_picks.slice();
            return;
        }
        var peek = activePeek(r).slot;
        if (!peek) return;
        if (selectedSlots.length === 0) {
            selectedSlots = [peek];
            return;
        }
        if (selectedSlots.indexOf(peek) < 0) {
            var others = selectedSlots.filter(function(s) { return s !== peek; });
            if (others.length > 1) others = [others[others.length - 1]];
            selectedSlots = others.length ? [peek, others[0]] : [peek];
        }
    }

    function togglePickSlot(slot, peek) {
        if (pickLocked) return;
        peek = peek || 0;
        if (peek && slot === peek) return;

        if (peek) {
            var others = selectedSlots.filter(function(s) { return s !== peek; });
            if (others.indexOf(slot) >= 0) {
                selectedSlots = [peek];
            } else {
                selectedSlots = [peek, slot];
            }
            return;
        }

        var idx = selectedSlots.indexOf(slot);
        if (idx >= 0) {
            selectedSlots.splice(idx, 1);
            return;
        }
        if (selectedSlots.length >= 2) {
            selectedSlots.shift();
        }
        selectedSlots.push(slot);
    }

    function applyPickHints(el, r) {
        if (!el || !r || !r.pick_hints) return;
        el.querySelectorAll('.card-pip.pickable').forEach(function(node) {
            var slot = parseInt(node.getAttribute('data-slot'), 10);
            var hint = r.pick_hints[slot] || r.pick_hints[String(slot)];
            if (!hint) return;
            node.classList.add('has-pick-hint');
            var badge = document.createElement('span');
            var result = hint.result || (hint.win_pct >= 100 ? 'win' : (hint.win_pct <= 0 ? 'lose' : 'draw'));
            badge.className = 'card-pick-hint ' + result;
            var mainLabel = result === 'win' ? '이기는 패' : (result === 'lose' ? '지는 패' : '무승부');
            badge.textContent = mainLabel;
            if (hint.hand || hint.card) {
                var sub = document.createElement('span');
                sub.className = 'card-pick-hint-sub';
                sub.textContent = [hint.card, hint.hand].filter(Boolean).join(' · ');
                badge.appendChild(sub);
            }
            node.appendChild(badge);
        });
    }

    function renderPickPhaseCards(r, el, hint, SC) {
        pickLocked = false;
        syncPickSelection(r);
        var picks = selectedSlots;
        var peekInfo = activePeek(r);
        var peek = peekInfo.slot;
        var peekCard = peekInfo.card;

        el.innerHTML = '';
        for (var i = 0; i < 3; i++) {
            var slot = i + 1;
            var sel = picks.indexOf(slot) >= 0;
            var isPeek = peek === slot && !!peekCard;
            var extra = (sel ? ' selected' : '');
            if (isPeek) {
                extra += ' peek-open peek-locked';
                el.innerHTML += SC.cardNodeHtml(peekCard, extra, slot);
            } else {
                extra += ' pickable';
                el.innerHTML += SC.cardBackNodeHtml(extra, slot);
            }
        }

        applyPickHints(el, r);

        el.querySelectorAll('.card-pip.pickable').forEach(function(node) {
            node.onclick = function() {
                if (pickLocked || revealSubmitting) return;
                var slot = parseInt(node.getAttribute('data-slot'), 10);
                var room = applyState._lastRoom || r;
                var peekSlot = activePeek(room).slot;
                togglePickSlot(slot, peekSlot);
                renderCards(applyState._lastRoom || r);
                updatePickButtons(applyState._lastRoom || r);
                maybeAutoReveal(applyState._lastRoom || r);
            };
        });

        if (peekCard) {
            var extraCount = selectedSlots.filter(function(s) { return s !== peek; }).length;
            hint.textContent = '공개: ' + peekCard + ' (자동 선택) · 나머지 1장 선택 (' + extraCount + '/1)';
            if (extraCount >= 1) {
                hint.textContent += ' · 오픈 중…';
            } else if (r.pick_hints) {
                hint.textContent += ' · 초록=이기는 패 / 빨강=지는 패';
            }
        } else {
            hint.textContent = '3장 중 2장 선택 (' + selectedSlots.length + '/2) · 선택 즉시 오픈';
        }
    }

    function isRevealAction(action) {
        return action === 'solo_reveal' || action === 'pvp_reveal';
    }

    function cardPlayPanel() {
        return document.querySelector('.card-play');
    }

    function showRevealPending() {
        var panel = cardPlayPanel();
        if (panel) panel.classList.add('revealing');
        var hint = document.getElementById('pickHint');
        if (hint) hint.textContent = '패 오픈 중…';
    }

    function clearRevealPending() {
        var panel = cardPlayPanel();
        if (panel) panel.classList.remove('revealing');
    }

    function applyRevealFast(j) {
        if (!j || !j.room) return;
        if (j.point_fmt) {
            document.getElementById('stPoint').textContent = j.point_fmt;
        }
        if (j.data) {
            document.getElementById('stResult').textContent = j.data;
        }
        renderCards(j.room);
        clearRevealPending();
        if (j.room.solo_result && j.room.solo_phase === 'done') {
            maybeShowSoloResult(j);
        }
        if (j.room.pvp_result && j.room.pvp_phase === 'done') {
            maybeShowPvpResult(j);
        }
    }

    function maybeAutoReveal(r) {
        if (!r || revealSubmitting || pickLocked) return;
        if (selectedSlots.length !== 2) return;
        var p = pickParams();
        if (!p) return;

        var soloOn = (r.mode === 'solo' || r.mode === 'match_pending') && r.my_role !== 'guest';
        if (soloOn && r.solo_phase === 'playing') {
            revealSubmitting = true;
            pickLocked = true;
            showRevealPending();
            ajax('solo_reveal', p, function(j, ok) {
                revealSubmitting = false;
                if (!ok) {
                    pickLocked = false;
                    clearRevealPending();
                }
            });
            return;
        }
        if (r.mode === 'pvp' && r.pvp_phase === 'playing' && !r.my_picked) {
            revealSubmitting = true;
            pickLocked = true;
            showRevealPending();
            ajax('pvp_reveal', p, function(j, ok) {
                revealSubmitting = false;
                if (!ok) {
                    pickLocked = false;
                    clearRevealPending();
                }
            });
        }
    }

    function renderCards(r) {
        var el = document.getElementById('cardArea');
        var hint = document.getElementById('pickHint');
        var SC = window.SeotdaCards;
        if (!SC) return;

        var count = r.pool_count || (r.my_pool ? r.my_pool.length : 0);
        var inPick = isInPickPhase(r) && count >= 3;
        var needPick = !!r.need_pick && !r.my_picked;
        var hidden = !!r.cards_hidden && !r.cards_revealed;
        var picks = r.my_picks || selectedSlots;

        if (count < 3 && !r.cards_revealed) {
            if (r.my_cards && r.my_cards.length >= 2) {
                el.innerHTML = r.my_cards.map(function(c) {
                    return SC.cardNodeHtml(c, '');
                }).join('');
                hint.textContent = r.my_hand ? ('내 족보: ' + r.my_hand) : '—';
                return;
            }
            el.innerHTML =
                SC.cardBackNodeHtml('placeholder') +
                SC.cardBackNodeHtml('placeholder') +
                SC.cardBackNodeHtml('placeholder');
            hint.textContent = '솔로 시작 또는 상대 입장 후 패가 나옵니다';
            return;
        }

        // 패 선택 중에는 my_pool·cards_revealed 무시 — 공개 1장만 앞면 고정
        if (inPick) {
            renderPickPhaseCards(r, el, hint, SC);
            return;
        }

        if (hidden) {
            pickLocked = !needPick;
            if (r.my_picks && r.my_picks.length === 2) {
                selectedSlots = r.my_picks.slice();
                picks = selectedSlots;
            }
            var peekInfo = activePeek(r);
            var peek = peekInfo.slot;
            var peekCard = peekInfo.card;
            el.innerHTML = '';
            for (var i = 0; i < 3; i++) {
                var slot = i + 1;
                var sel = picks.indexOf(slot) >= 0;
                var isPeek = peek === slot && !!peekCard;
                var extra = (sel ? ' selected' : '');
                if (isPeek) {
                    extra += ' peek-open peek-locked';
                    el.innerHTML += SC.cardNodeHtml(peekCard, extra, slot);
                } else {
                    el.innerHTML += SC.cardBackNodeHtml(extra, slot);
                }
            }
            if (r.my_picked) {
                hint.textContent = r.opponent_picked
                    ? '양쪽 선택 완료 · 오픈 중…'
                    : '선택 완료 · 상대 선택 대기 중…';
            } else {
                hint.textContent = '패 선택 중…';
            }
            return;
        }

        pickLocked = true;
        var pool = r.my_pool || [];
        el.innerHTML = pool.map(function(c, i) {
            var slot = i + 1;
            var sel = picks.indexOf(slot) >= 0;
            var extra = sel ? ' selected' : ' dim';
            return SC.cardNodeHtml(c, extra, slot);
        }).join('');

        if (r.my_hand) {
            hint.textContent = '내 족보: ' + r.my_hand + (r.opponent_hand ? (' · 상대: ' + r.opponent_hand) : '');
        } else {
            hint.textContent = '오픈 완료';
        }
    }

    function renderPlaceholderCards() {
        if (!window.SeotdaCards) return;
        renderCards({ pool_count: 0, cards_revealed: false });
    }
    renderPlaceholderCards();

    function updatePickButtons(r) {
        var soloOn = (r.mode === 'solo' || r.mode === 'match_pending') && r.my_role !== 'guest';
        var showSoloStart = soloOn && r.solo_phase !== 'playing';

        document.getElementById('btnSoloStart').style.display = showSoloStart ? 'block' : 'none';
        document.getElementById('btnSoloStart').disabled = !showSoloStart;
    }

    function pickParams() {
        if (selectedSlots.length !== 2) return null;
        var sorted = selectedSlots.slice().sort(function(a, b) { return a - b; });
        return { pick1: sorted[0], pick2: sorted[1] };
    }

    function canBrowsePlayers(r) {
        return r.my_role !== 'guest'
            && r.mode !== 'pvp'
            && r.mode !== 'waiting'
            && r.mode !== 'match_pending';
    }

    function playersListKey(players) {
        if (!players || !players.length) return '';
        return players.map(function(p) {
            return p.nick + '|' + (p.available ? '1' : '0') + '|' + p.status_label + '|' + p.bet_fmt;
        }).join('\n');
    }

    function renderPlayerList(players, r) {
        var el = document.getElementById('playerList');
        var browse = canBrowsePlayers(r);
        if (!browse) {
            el.innerHTML = '<div class="player-empty">대결 참여 중에는 목록을 사용할 수 없어요.</div>';
            return;
        }
        if (!players || players.length === 0) {
            el.innerHTML = '<div class="player-empty">섯다 접속 중인 유저가 없어요.<br>솔로로 플레이해주세요.</div>';
            return;
        }
        el.innerHTML = players.map(function(p) {
            var disabled = !p.available;
            return '<div class="player-row">' +
                '<div class="player-info">' +
                '<div class="player-nick">' + p.nick + '</div>' +
                '<div class="player-meta">' + p.status_label + ' · ' + p.bet_fmt + '</div>' +
                '</div>' +
                '<button type="button" class="btn-join" data-host="' + p.nick + '"' +
                (disabled ? ' disabled' : '') + '>입장</button>' +
                '</div>';
        }).join('');
        el.querySelectorAll('.btn-join:not([disabled])').forEach(function(btn) {
            btn.onclick = function() {
                ajax('join_host', { host_nick: btn.getAttribute('data-host'), amount: betValue() });
            };
        });
    }

    function applyState(j) {
        if (!j || !j.room) return;
        var r = j.room;

        var prevPickSession = applyState._pickSession || '';
        var pickSession = pickSessionKey(r);
        if (pickSession && prevPickSession !== pickSession) {
            resetPickState();
            applyState._pickSession = pickSession;
            lockPeekFromRoom(r, true);
        } else if (!pickSession && prevPickSession) {
            resetPickState();
            applyState._pickSession = '';
        } else if (pickSession) {
            applyState._pickSession = pickSession;
            lockPeekFromRoom(r, false);
        }

        var prevPhase = applyState._phase || '';
        var phaseKey = r.mode + ':' + r.solo_phase + ':' + r.pvp_phase + ':' + (r.round_no || 0);
        if (phaseKey !== prevPhase) {
            applyState._phase = phaseKey;
            if (r.solo_phase === 'playing' || r.pvp_phase === 'playing') {
                document.getElementById('stResult').textContent = '—';
            }
        }

        document.getElementById('stNick').textContent = j.nick || '';
        document.getElementById('stPoint').textContent = j.point_fmt || '';
        renderBetButtons(j.point);
        document.getElementById('stMode').textContent = r.mode;
        var showRound = (r.round_no || 0) > 0 && !!r.guest_nick;
        document.getElementById('rowRound').style.display = showRound ? 'flex' : 'none';
        if (showRound) document.getElementById('stRound').textContent = r.round_no + '판';
        document.getElementById('stBet').textContent = r.bet_fmt;
        document.getElementById('stGuest').textContent = r.guest_nick || '솔로';
        var showReadyRow = !!r.guest_nick && (r.mode === 'waiting' || r.mode === 'pvp');
        document.getElementById('rowReady').style.display = showReadyRow ? 'flex' : 'none';
        if (showReadyRow) {
            document.getElementById('stReady').textContent =
                (r.host_ready ? '호✓' : '호·') + ' ' + (r.guest_ready ? '게✓' : '게·');
        }
        var showWaitRow = r.mode === 'waiting';
        document.getElementById('rowWaitLeft').style.display = showWaitRow ? 'flex' : 'none';
        if (showWaitRow) {
            document.getElementById('stWaitLeft').textContent = r.waiting_left + '초';
        }
        syncTimerFromRoom(r);
        var showGuestAction = r.my_role === 'guest' && r.guest_action_active;
        document.getElementById('rowGuestAction').style.display = showGuestAction ? 'flex' : 'none';
        if (showGuestAction) {
            document.getElementById('stGuestAction').textContent = getTimerLeftNow() + '초';
        }
        var oneReady = r.mode === 'waiting'
            && ((r.host_ready && !r.guest_ready) || (!r.host_ready && r.guest_ready));
        var showReadyWait = oneReady && r.ready_wait_active;
        document.getElementById('rowReadyWait').style.display = showReadyWait ? 'flex' : 'none';
        if (showReadyWait) {
            document.getElementById('stReadyWait').textContent = getTimerLeftNow() + '초';
        }
        if (r.last_result) document.getElementById('stResult').textContent = r.last_result;

        var prevGuest = applyState._guestNick;
        var newGuest = r.guest_nick || null;
        if (applyState._initialized && r.my_role === 'host' && !prevGuest && newGuest) {
            notifyGuestJoined(r, newGuest);
        }
        if (applyState._initialized && r.my_role === 'host' && prevGuest && !newGuest) {
            var autoKick = r.last_result && (
                r.last_result.indexOf('자동 퇴장') >= 0
                || r.last_result.indexOf('준비 없음') >= 0
            );
            toast(autoKick
                ? r.last_result.split('\n')[0]
                : (prevGuest + '님이 나갔습니다 · 솔로로 복귀'));
        }
        if (applyState._initialized && applyState._wasGuest && r.my_role !== 'guest'
            && applyState._lastAction !== 'leave') {
            toast('자동 퇴장되었습니다');
        }
        applyState._wasGuest = (r.my_role === 'guest');
        applyState._guestNick = newGuest;
        applyState._initialized = true;
        applyState._lastRoom = r;

        if (!needsReadyPrompt(r)) {
            applyState._readyLayerDismissed = null;
        }
        maybeShowReadyLayer(r);
        applyState._prevMode = r.mode;

        renderCards(r);
        if (j.players) {
            var playerKey = (canBrowsePlayers(r) ? '1' : '0') + '|' + playersListKey(j.players || []);
            if (playerKey !== applyState._playersKey) {
                applyState._playersKey = playerKey;
                renderPlayerList(j.players || [], r);
            }
        }

        var soloOn = r.mode === 'solo' || r.mode === 'match_pending';

        var inMatch = !!r.guest_nick || r.mode === 'pvp' || r.mode === 'waiting' || r.mode === 'match_pending';
        var matchTitle = document.querySelector('#panelMatch .panel-title');
        var matchHint = document.getElementById('matchHint');
        if (r.my_role === 'guest' && r.guest_action_active) {
            var actionLabel = r.mode === 'pvp' ? '패 선택' : '1:1 준비';
            matchHint.textContent = '⏱ ' + getTimerLeftNow() + '초 내 「' + actionLabel + '」 · 미선택 시 자동 퇴장';
        } else if (showReadyWait) {
            var iReady = (r.my_role === 'host' && r.host_ready) || (r.my_role === 'guest' && r.guest_ready);
            var tLeft = getTimerLeftNow();
            if (iReady) {
                matchHint.textContent = '⏱ 상대 「1:1 준비」 대기 · ' + tLeft + '초 후 자동 퇴장';
            } else {
                matchHint.textContent = '⏱ ' + tLeft + '초 내 「1:1 준비」 · 미응답 시 자동 퇴장';
            }
        } else if (r.mode === 'waiting' && r.pvp_phase === 'done') {
            matchTitle.textContent = '🔄 1:1 연속 대전';
            matchHint.textContent = '양쪽 「다음 판 준비」 후 계속 대결 · 그만두려면 「나가기」';
        } else if (inMatch) {
            matchTitle.textContent = '👥 1:1 대결';
            matchHint.textContent = '대결 중 · 종료하려면 「나가기」';
        } else {
            matchTitle.textContent = '👥 1:1 상대 찾기';
            matchHint.textContent = '섯다 접속 중인 유저 · 입장 버튼으로 대결';
        }

        var myReady = (r.my_role === 'host' && r.host_ready) || (r.my_role === 'guest' && r.guest_ready);
        var btnReady = document.getElementById('btnReady');
        btnReady.disabled = r.mode !== 'waiting' || myReady;
        if (r.mode === 'waiting') {
            var tLeft = getTimerLeftNow();
            if (myReady && TIMER.type === 'ready_wait') {
                btnReady.textContent = '⏳ 준비 완료 · ' + tLeft + '초';
            } else if (myReady) {
                btnReady.textContent = '⏳ 준비 완료 · 상대 대기';
            } else if (r.pvp_phase === 'done') {
                btnReady.textContent = TIMER.type === 'ready_wait'
                    ? ('✅ 다음 판 준비 (' + tLeft + '초)')
                    : '✅ 다음 판 준비';
            } else {
                btnReady.textContent = TIMER.type === 'ready_wait'
                    ? ('✅ 1:1 준비 (' + tLeft + '초)')
                    : '✅ 1:1 준비';
            }
        }
        updatePickButtons(r);
        applyState._lastRoom = r;
        renderTimerBar(r);
        maybeShowPvpResult(j);
        maybeShowSoloResult(j);

        if (r.mode === 'pvp' && r.pvp_phase === 'playing' && r.my_picked && !r.opponent_picked) {
            scheduleStatusPoll(280);
        }

        var emoteBar = document.getElementById('emoteBar');
        if (emoteBar) {
            emoteBar.classList.toggle('show', !!r.emote_enabled);
            if (r.emote_catalog && r.emote_catalog.length) {
                renderEmoteButtons(r.emote_catalog);
            }
        }
        if (r.opponent_emote) {
            showOpponentEmote(r.opponent_emote);
        }
        updateBetPanel(r);
        maybeShowBetPending(r);
    }

    function ajax(action, extra, cb, silent) {
        if (action === 'send_emote') {
            emoteSending = true;
            updateEmoteButtonsState();
        }
        if (!silent && action !== 'send_emote' && !isRevealAction(action)) {
            document.getElementById('app').classList.add('loading');
        }
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('code', CODE);
        if (extra) Object.keys(extra).forEach(function(k) { body.set(k, extra[k]); });
        var url = API + (CODE ? ('?code=' + encodeURIComponent(CODE)) : '');
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (action === 'send_emote') {
                    emoteSending = false;
                    updateEmoteButtonsState();
                }
                if (!silent && action !== 'send_emote' && !isRevealAction(action)) {
                    document.getElementById('app').classList.remove('loading');
                }
                if (!j.ok) {
                    if (isRevealAction(action)) clearRevealPending();
                    if (!silent) {
                        toast((j.data || '오류').split('\n')[0]);
                        if (j.data && j.data.indexOf('\n') >= 0) {
                            document.getElementById('stResult').textContent = j.data;
                        }
                    }
                    if (cb) cb(j, false);
                    return;
                }
                var isPvpSettle = action === 'pvp_reveal' && j.room && j.room.pvp_result
                    && j.room.mode === 'waiting' && j.room.pvp_phase === 'done';
                var isSoloSettle = action === 'solo_reveal' && j.room && j.room.solo_result
                    && j.room.solo_phase === 'done';
                if (j.data && !silent && action !== 'join_host' && action !== 'send_emote'
                    && action !== 'bet_propose' && action !== 'bet_accept' && action !== 'bet_reject' && action !== 'bet_cancel'
                    && !isPvpSettle && !isSoloSettle) {
                    toast(j.data.split('\n')[0]);
                }
                applyState._lastAction = action;
                if (isRevealAction(action)) {
                    applyRevealFast(j);
                }
                applyState(j);
                if (action === 'bet_accept' || action === 'bet_reject' || action === 'bet_cancel') {
                    hideBetPendingLayer();
                    applyState._betPendingShownKey = null;
                }
                if (action === 'bet_propose' || action === 'bet_accept' || action === 'bet_reject' || action === 'bet_cancel') {
                    if (j.data) toast(j.data.split('\n')[0]);
                }
                if (action === 'join_host') {
                    notifyJoinHostResponse(j);
                }
                if (action === 'ready' && j && j.ok) {
                    hideReadyLayer();
                }
                if (action === 'leave') {
                    hideReadyLayer();
                    applyState._readyLayerDismissed = null;
                    applyState._readyLayerShownKey = null;
                }
                if (j.data && (action === 'solo_reveal' || action === 'pvp_reveal')) {
                    document.getElementById('stResult').textContent = j.data;
                }
                if (isSoloSettle) {
                    maybeShowSoloResult(j);
                }
                if (isPvpSettle) {
                    maybeShowPvpResult(j);
                }
                if (cb) cb(j, true);
            })
            .catch(function() {
                if (action === 'send_emote') {
                    emoteSending = false;
                    updateEmoteButtonsState();
                }
                if (!silent && action !== 'send_emote' && !isRevealAction(action)) {
                    document.getElementById('app').classList.remove('loading');
                }
                if (isRevealAction(action)) clearRevealPending();
                if (!silent) toast('통신 오류');
                if (cb) cb(null, false);
            });
    }

    var pollTimer = null;
    var pollBusy = false;

    function getPollDelayMs() {
        var r = applyState._lastRoom;
        if (!r) return POLL.browse;
        if (canBrowsePlayers(r)) return POLL.browse;
        if (r.mode === 'waiting' || r.mode === 'pvp' || r.mode === 'match_pending') return POLL.match;
        if (r.mode === 'pvp' && r.pvp_phase === 'playing') {
            if (r.my_picked && !r.opponent_picked) return 280;
            return POLL.match;
        }
        if (r.mode === 'solo' && r.solo_phase === 'playing') return POLL.solo;
        return POLL.match;
    }

    function scheduleStatusPoll(delayMs) {
        if (pollTimer) clearTimeout(pollTimer);
        pollTimer = setTimeout(runStatusPoll, delayMs == null ? getPollDelayMs() : delayMs);
    }

    function runStatusPoll() {
        if (pollBusy) {
            scheduleStatusPoll(300);
            return;
        }
        pollBusy = true;
        ajax('status', null, function() {
            pollBusy = false;
            scheduleStatusPoll();
        }, true);
    }

    document.getElementById('btnSoloStart').onclick = function() {
        ajax('solo_start', { amount: betValue() });
    };
    document.getElementById('btnSoloReveal').onclick = function() {
        var p = pickParams();
        if (!p) { toast('패 2장을 선택하세요'); return; }
        ajax('solo_reveal', p);
    };
    document.getElementById('btnReady').onclick = function() { ajax('ready'); };
    document.getElementById('readyLayerBtn').onclick = function() {
        if (this.disabled) return;
        ajax('ready');
    };
    document.getElementById('readyLayerClose').onclick = function() {
        var r = applyState._lastRoom;
        var key = r ? readyLayerKey(r) : null;
        if (key) applyState._readyLayerDismissed = key;
        hideReadyLayer();
    };
    document.getElementById('readyLayerBackdrop').onclick = function() { /* 준비 유도 — 배경 닫기 없음 */ };
    document.getElementById('btnLeave').onclick = function() { ajax('leave'); };
    document.getElementById('btnPvpReveal').onclick = function() {
        var p = pickParams();
        if (!p) { toast('패 2장을 선택하세요'); return; }
        ajax('pvp_reveal', p);
    };
    document.getElementById('btnBetPropose').onclick = function() {
        ajax('bet_propose', { amount: betValue() });
    };
    document.getElementById('btnBetCancel').onclick = function() {
        ajax('bet_cancel');
    };
    document.getElementById('betPendingAccept').onclick = function() {
        ajax('bet_accept');
    };
    document.getElementById('betPendingReject').onclick = function() {
        ajax('bet_reject');
    };
    document.getElementById('betPendingBackdrop').onclick = function() { /* 수락/거절 필수 */ };

    renderEmoteButtons(DEFAULT_EMOTES);

    ajax('status', null, function() { scheduleStatusPoll(); });
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            ajax('status', null, function() { scheduleStatusPoll(POLL.browse); }, true);
        }
    });
    setInterval(function() {
        if (Date.now() >= emoteCooldownUntil) {
            updateEmoteButtonsState();
        }
        if (applyState._lastRoom && TIMER.type) {
            renderTimerBar(applyState._lastRoom);
            updateReadyLayerUi(applyState._lastRoom);
            var r = applyState._lastRoom;
            var oneReady = r.mode === 'waiting'
                && ((r.host_ready && !r.guest_ready) || (!r.host_ready && r.guest_ready));
            if (oneReady && r.ready_wait_active) {
                document.getElementById('stReadyWait').textContent = getTimerLeftNow() + '초';
            }
            if (r.my_role === 'guest' && r.guest_action_active) {
                document.getElementById('stGuestAction').textContent = getTimerLeftNow() + '초';
            }
            var btnReady = document.getElementById('btnReady');
            var myReady = (r.my_role === 'host' && r.host_ready) || (r.my_role === 'guest' && r.guest_ready);
            if (r.mode === 'waiting' && TIMER.type === 'ready_wait') {
                var tLeft = getTimerLeftNow();
                if (myReady) {
                    btnReady.textContent = '⏳ 준비 완료 · ' + tLeft + '초';
                } else if (r.pvp_phase === 'done') {
                    btnReady.textContent = '✅ 다음 판 준비 (' + tLeft + '초)';
                } else {
                    btnReady.textContent = '✅ 1:1 준비 (' + tLeft + '초)';
                }
            }
        }
    }, 1000);
})();
</script>
<?php } ?>
</body>
</html>
