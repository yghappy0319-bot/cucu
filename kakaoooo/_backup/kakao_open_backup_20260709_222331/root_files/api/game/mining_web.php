<?php
/**
 * 채굴 게임 웹
 *
 * URL:
 *   /api/game/mining_web.php?code=XXXX
 *   /page/mining.php?code=XXXX
 *
 * 누적은 서버 스냅샷 + lease(단일 활성 세션) — PC/모바일 공유, 중복 채굴 방지
 */

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_tool.inc.php';
require_once __DIR__ . '/mining_sync.inc.php';
require_once __DIR__ . '/mining_weapon.inc.php';
require_once __DIR__ . '/mining_ore.inc.php';

define('WALLET_LIB_ONLY', true);
require_once __DIR__ . '/wallet_web.php';

function mining_fmt_game($n) {
    if (function_exists('wallet_fmt_game')) {
        return wallet_fmt_game($n);
    }
    $n = (int)$n;
    if ($n >= 1000000000000) {
        return rtrim(rtrim(number_format($n / 1000000000000, 2, '.', ''), '0'), '.') . '조';
    }
    if ($n >= 100000000) {
        return rtrim(rtrim(number_format($n / 100000000, 2, '.', ''), '0'), '.') . '억';
    }
    if ($n >= 10000) {
        return rtrim(rtrim(number_format($n / 10000, 2, '.', ''), '0'), '.') . '만';
    }
    return number_format($n);
}

function mining_parse_amount($raw) {
    $s = trim((string)$raw);
    if ($s === '' || !preg_match('/^\d+(\.\d{1,10})?$/', $s)) {
        return null;
    }
    return (float)$s;
}

function mining_claim_execute($nick, $lease_token = '') {
    $result = mining_claim_to_newpoint($nick, (float)MINING_SAVE_MIN);
    if (empty($result['ok'])) {
        return ['ok' => false, 'data' => $result['data'] ?? '수령 실패'];
    }

    $sync_read = mining_sync_read($nick, $lease_token);

    return [
        'ok' => true,
        'data' => ($result['claimed_fmt'] ?? mining_fmt_pending($result['claimed'])) . '냥을 수령했어요.',
        'saved' => (float)$result['claimed'],
        'saved_fmt' => (string)$result['claimed_fmt'],
        'newpoint' => (float)$result['newpoint'],
        'newpoint_fmt' => wallet_fmt_new((float)$result['newpoint']),
        'sync' => $sync_read['sync'] ?? null,
    ];
}

$mining_actions = ['status', 'claim', 'upgrade', 'upgrade10', 'use_eunchong', 'sync', 'release', 'equip_weapon', 'unequip_weapon', 'touch_ore'];
$req_action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';

if (in_array($req_action, $mining_actions, true)) {
    ob_start();
}

$req_code = wallet_request_code();

if (in_array($req_action, $mining_actions, true)) {
    if ($req_code === '') {
        wallet_json(['ok' => false, 'data' => '접속 코드가 필요합니다.']);
    }

    $auth = wallet_member_refresh($req_code);
    if (!$auth) {
        wallet_json(['ok' => false, 'data' => '유효하지 않은 코드입니다.']);
    }
    if (!mining_access_allowed($auth['nick'] ?? '')) {
        wallet_json(['ok' => false, 'data' => mining_access_denied_msg()]);
    }

    $nick = $auth['nick'];
    $member = $auth['row'];

    $lease_token = isset($_REQUEST['lease_token']) ? trim((string)$_REQUEST['lease_token']) : '';

    if ($req_action === 'status') {
        $fresh = wallet_auth($req_code) ?: $member;
        $tool_level = mining_tool_level_for_nick($nick);
        $tool = mining_tool_payload_for_nick($nick);
        $sync_read = mining_sync_read($nick, $lease_token);
        wallet_json(array_merge([
            'ok' => true,
            'type' => 'status',
            'member' => [
                'nick' => $nick,
                'point' => (int)($fresh['point'] ?? 0),
                'point_fmt' => mining_fmt_game((int)($fresh['point'] ?? 0)),
                'newpoint' => (float)($fresh['newpoint'] ?? 0),
                'newpoint_fmt' => wallet_fmt_new($fresh['newpoint'] ?? 0),
            ],
            'tool' => $tool,
            'rate' => (float)$tool['rate'],
            'save_min' => (float)MINING_SAVE_MIN,
            'active_elsewhere' => !empty($sync_read['active_elsewhere']),
            'sync' => $sync_read['sync'] ?? null,
        ], mining_eunchong_payload($nick), [
            'weapon' => mining_weapon_payload($nick, $fresh),
        ], mining_ore_member_state($nick)));
    }

    if ($req_action === 'touch_ore') {
        $find_idx = isset($_REQUEST['find_idx']) ? (int)$_REQUEST['find_idx'] : 0;
        $result = mining_ore_touch_execute($nick, $find_idx, $lease_token);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '수령 실패']);
        }
        wallet_json(array_merge(['ok' => true, 'type' => 'touch_ore'], $result));
    }

    if ($req_action === 'equip_weapon') {
        $result = mining_weapon_equip_execute($nick);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '장착 실패']);
        }
        wallet_json(array_merge(['ok' => true, 'type' => 'equip_weapon'], $result));
    }

    if ($req_action === 'unequip_weapon') {
        $result = mining_weapon_unequip_execute($nick);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '해제 실패']);
        }
        wallet_json(array_merge(['ok' => true, 'type' => 'unequip_weapon'], $result));
    }

    if ($req_action === 'sync') {
        if ($lease_token !== '') {
            $sync_result = mining_sync_ping($nick, $lease_token);
        } else {
            $sync_result = mining_sync_acquire($nick);
        }
        wallet_json(array_merge($sync_result, mining_ore_member_state($nick)));
    }

    if ($req_action === 'release') {
        if ($lease_token === '') {
            wallet_json(['ok' => true, 'type' => 'release', 'sync' => null]);
        }
        wallet_json(mining_sync_release($nick, $lease_token));
    }

    if ($req_action === 'use_eunchong') {
        $result = mining_use_eunchong_execute($nick);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '은총 사용 실패']);
        }
        $sync_read = mining_sync_read($nick, $lease_token);
        wallet_json(array_merge([
            'ok' => true,
            'type' => 'use_eunchong',
        ], $result, [
            'sync' => $sync_read['sync'] ?? null,
            'active_elsewhere' => !empty($sync_read['active_elsewhere']),
        ]));
    }

    if ($req_action === 'upgrade' || $req_action === 'upgrade10') {
        mining_sync_commit_elapsed($nick);
        if ($req_action === 'upgrade10') {
            $times = 10;
        } else {
            $times = (int)($_REQUEST['times'] ?? 1);
            if (!in_array($times, [1, 10, 50, 100, (int)MINING_UPGRADE_BATCH_1000_TIMES], true)) {
                $times = 1;
            }
        }
        if ($times <= 1) {
            $result = mining_upgrade_execute($nick, ['skip_sync' => true]);
            $type = 'upgrade';
        } else {
            $result = mining_upgrade_execute_batch($nick, $times);
            $type = 'upgrade_batch';
        }
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '강화 실패']);
        }
        $sync_read = mining_sync_read($nick, $lease_token);
        $upgrade_payload = array_merge(['ok' => true, 'type' => $type, 'times' => $times], $result, mining_eunchong_payload($nick));
        if (!empty($sync_read['sync'])) {
            $upgrade_payload['sync'] = $sync_read['sync'];
        }
        if ($times <= 1) {
            $upgrade_payload = array_merge($upgrade_payload, mining_ore_member_state($nick));
        } else {
            $ctx = mining_ore_roll_context($nick);
            if (!empty($ctx['ok'])) {
                $tool_lv = (int)(($result['tool']['level'] ?? null) ?? mining_tool_level_for_nick($nick));
                $upgrade_payload['ore_discover_hint'] = mining_ore_discover_hint((int)$ctx['enhance'], $tool_lv);
            }
        }
        wallet_json($upgrade_payload);
    }

    if ($req_action === 'claim') {
        $result = mining_claim_execute($nick, $lease_token);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '수령 실패']);
        }
        wallet_json(array_merge(['ok' => true, 'type' => 'claim'], $result, mining_ore_member_state($nick)));
    }
}

// ----- 페이지 -----
$mining_api = '/api/game/mining_web.php';
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($script, '/page/mining.php') !== false) {
    $mining_api = '/page/mining.php';
}

$mining_code = wallet_코드_해석();
if ($mining_code === '' && isset($_REQUEST['code'])) {
    $mining_code = trim((string)$_REQUEST['code']);
}

$mining_need_code = true;
$mining_member = null;
$mining_access_denied = false;
$mining_access_msg = mining_access_denied_msg();

if (!empty($GLOBALS['wallet_preauth']['row']) && !empty($GLOBALS['wallet_preauth']['code'])) {
    $mining_code = (string)$GLOBALS['wallet_preauth']['code'];
    $row = wallet_member_row_enrich($GLOBALS['wallet_preauth']['row'], $mining_code);
    $nick_page = wallet_nick_from_row($row);
    if ($nick_page === '') {
        $nick_page = trim((string)($row['name'] ?? ''));
    }
    wallet_코드_쿠키_저장($mining_code);
    $mining_need_code = false;
    mining_data_ensure_table();
    mining_sync_commit_elapsed($nick_page);
    $mining_tool = mining_tool_payload_for_nick($nick_page);
    $mining_sync_initial = mining_sync_read($nick_page);
    $mining_eunchong = mining_eunchong_payload($nick_page);
    $mining_weapon = mining_weapon_payload($nick_page, $row);
    $mining_ore = mining_ore_member_state($nick_page);
    $mining_member = array_merge([
        'nick' => $nick_page,
        'point' => (int)($row['point'] ?? 0),
        'point_fmt' => mining_fmt_game((int)($row['point'] ?? 0)),
        'newpoint' => (float)($row['newpoint'] ?? 0),
        'newpoint_fmt' => wallet_fmt_new($row['newpoint'] ?? 0),
        'tool' => $mining_tool,
        'weapon' => $mining_weapon,
    ], $mining_eunchong, $mining_ore);
    if (!mining_access_allowed($nick_page)) {
        $mining_access_denied = true;
    }
} elseif ($mining_code !== '') {
    wallet_odd_even_includes();
    $row = wallet_game_auth_row($mining_code);
    if ($row) {
        $row = wallet_member_row_enrich($row, $mining_code);
        $nick_page = wallet_nick_from_row($row);
        if ($nick_page === '') {
            $nick_page = trim((string)($row['name'] ?? ''));
        }
        wallet_코드_쿠키_저장($mining_code);
        $mining_need_code = false;
        mining_data_ensure_table();
        mining_sync_commit_elapsed($nick_page);
        $mining_tool = mining_tool_payload_for_nick($nick_page);
        $mining_sync_initial = mining_sync_read($nick_page);
        $mining_eunchong = mining_eunchong_payload($nick_page);
        $mining_weapon = mining_weapon_payload($nick_page, $row);
        $mining_ore = mining_ore_member_state($nick_page);
        $mining_member = array_merge([
            'nick' => $nick_page,
            'point' => (int)($row['point'] ?? 0),
            'point_fmt' => mining_fmt_game((int)($row['point'] ?? 0)),
            'newpoint' => (float)($row['newpoint'] ?? 0),
            'newpoint_fmt' => wallet_fmt_new($row['newpoint'] ?? 0),
            'tool' => $mining_tool,
            'weapon' => $mining_weapon,
        ], $mining_eunchong, $mining_ore);
        if (!mining_access_allowed($nick_page)) {
            $mining_access_denied = true;
        }
    } elseif (wallet_코드_쿠키_읽기() === $mining_code) {
        wallet_코드_쿠키_삭제();
    }
}

$wallet_q = wallet_code_query($mining_code);
$mining_guide_q = $mining_code !== '' ? ('?code=' . rawurlencode($mining_code)) : '';
$mining_upgrade_btn_layout = defined('MINING_UPGRADE_BTN_LAYOUT') ? (string)MINING_UPGRADE_BTN_LAYOUT : 'fixed';
if (!in_array($mining_upgrade_btn_layout, ['fixed', 'random'], true)) {
    $mining_upgrade_btn_layout = 'fixed';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a1210">
    <title>숟가락 채굴 · 웹</title>
    <style>
        :root {
            --bg: #0a1210;
            --card: rgba(0,0,0,0.42);
            --line: rgba(110,231,183,0.22);
            --mint: #6ee7b7;
            --gold: #fcd34d;
            --text: #ecfdf5;
            --muted: rgba(236,253,245,0.62);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
            background:
                radial-gradient(ellipse at 15% 0%, rgba(110,231,183,0.14) 0%, transparent 42%),
                radial-gradient(ellipse at 85% 15%, rgba(252,211,77,0.08) 0%, transparent 38%),
                linear-gradient(165deg, #0a1210 0%, #101c18 45%, #070b0a 100%);
            color: var(--text);
            padding: 12px;
            padding-top: max(12px, env(safe-area-inset-top));
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }
        .balance-corner {
            position: absolute;
            top: 0;
            z-index: 5;
            pointer-events: none;
            padding: 4px 0 6px;
        }
        .balance-corner--left {
            left: 0;
            text-align: left;
            padding-right: 8px;
        }
        .balance-corner--right {
            right: 0;
            text-align: right;
            padding-left: 8px;
        }
        .balance-corner-label {
            display: block;
            font-size: 0.6rem;
            color: var(--muted);
            line-height: 1.15;
            letter-spacing: -0.01em;
        }
        .balance-corner-value {
            display: block;
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1.25;
            white-space: nowrap;
            max-width: 140px;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .balance-corner-value::-webkit-scrollbar {
            display: none;
        }
        .balance-corner--left .balance-corner-value { color: var(--mint); }
        .balance-corner--right .balance-corner-value { color: var(--gold); }
        .wrap { max-width: 420px; margin: 0 auto; }
        h1 { font-size: 1.15rem; color: var(--mint); margin-bottom: 8px; }
        .sub { font-size: 0.82rem; color: var(--muted); line-height: 1.55; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px 14px;
            margin-bottom: 12px;
        }
        .top-bar-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }
        .top-bar-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .top-nick { font-size: 0.74rem; color: var(--muted); }
        .balance-toggle {
            border: 1px solid var(--line);
            background: rgba(0,0,0,0.25);
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 4px 9px;
            border-radius: 999px;
            cursor: pointer;
            font-family: inherit;
            line-height: 1.2;
            white-space: nowrap;
        }
        .balance-toggle:hover { color: var(--text); }
        .balance-panel {
            overflow: hidden;
            max-height: 120px;
            opacity: 1;
            transition: max-height 0.22s ease, opacity 0.2s ease, margin 0.22s ease;
        }
        .balance-panel.collapsed {
            max-height: 0;
            opacity: 0;
            margin-top: 0;
            pointer-events: none;
        }
        .balance-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            min-width: 0;
        }
        .balance-box.game {
            border-color: rgba(252,211,77,0.28);
        }
        .balance-box.new {
            border-color: rgba(110,231,183,0.28);
        }
        .balance-box.new .balance-value { color: var(--mint); }
        .balance-box.game .balance-value { color: var(--gold); }
        .balance-label {
            display: block;
            font-size: 0.68rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .balance-value {
            display: block;
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--gold);
            line-height: 1.2;
            max-width: 100%;
        }
        .balance-box.game .balance-value {
            font-size: 0.82rem;
            font-weight: 800;
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding-bottom: 1px;
        }
        .balance-box.game .balance-value::-webkit-scrollbar {
            display: none;
        }
        .balance-box.new .balance-value {
            font-size: 1rem;
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .balance-box.new .balance-value::-webkit-scrollbar {
            display: none;
        }
        .spoon-visual {
            position: relative;
            text-align: center;
            padding: 8px 0;
            margin-bottom: 4px;
        }
        .mining-scene {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 100px;
            margin: 34px auto 14px;
            max-width: 300px;
            position: relative;
        }
        .mining-ground {
            position: absolute;
            left: 50%;
            bottom: 2px;
            transform: translateX(-50%);
            width: 78%;
            height: 10px;
            background: radial-gradient(ellipse at center, rgba(0,0,0,0.38) 0%, transparent 72%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .mining-rock-pile {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-bottom: 0;
            position: relative;
            z-index: 1;
        }
        .mining-rock-pile--left {
            align-items: flex-end;
            padding-right: 4px;
        }
        .mining-rock-pile--right {
            align-items: flex-start;
            padding-left: 4px;
        }
        .mining-rock {
            display: block;
            line-height: 1;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
            user-select: none;
        }
        .mining-rock-pile--left .rock-a { font-size: 1.4rem; animation: rockHitLight 2.4s ease-in-out infinite; }
        .mining-rock-pile--left .rock-b { font-size: 1rem; margin-right: 10px; margin-top: -4px; animation: rockHitLight 2.4s ease-in-out infinite 0.06s; }
        .mining-rock-pile--left .rock-c { font-size: 0.78rem; margin-right: 18px; margin-top: -2px; animation: rockHitLight 2.4s ease-in-out infinite 0.12s; }
        .mining-rock-pile--right .rock-a { font-size: 1.25rem; animation: rockHitStrong 2.4s ease-in-out infinite 0.03s; }
        .mining-rock-pile--right .rock-b { font-size: 0.95rem; margin-left: 8px; margin-top: -3px; animation: rockHitStrong 2.4s ease-in-out infinite 0.09s; }
        .mining-rock-pile--right .rock-c { font-size: 0.72rem; margin-left: 14px; margin-top: -1px; animation: rockHitStrong 2.4s ease-in-out infinite 0.15s; }
        .mining-strike-zone {
            position: relative;
            flex: 0 0 auto;
            width: 92px;
            height: 84px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        .mining-dust {
            position: absolute;
            left: 58%;
            bottom: 14px;
            width: 0;
            height: 0;
            pointer-events: none;
            z-index: 3;
        }
        .mining-dust-bit {
            position: absolute;
            border-radius: 50%;
            background: rgba(186, 168, 130, 0.9);
            box-shadow: 0 0 2px rgba(255, 255, 255, 0.25);
            opacity: 0;
            animation: dustPop 2.4s ease-out infinite;
        }
        .mining-dust-bit:nth-child(1) { width: 4px; height: 4px; --dx: 10px; --dy: -8px; }
        .mining-dust-bit:nth-child(2) { width: 3px; height: 3px; --dx: 16px; --dy: -4px; animation-delay: 0.04s; }
        .mining-dust-bit:nth-child(3) { width: 5px; height: 5px; --dx: 6px; --dy: -12px; animation-delay: 0.02s; }
        .mining-dust-bit:nth-child(4) { width: 3px; height: 3px; --dx: 18px; --dy: -10px; animation-delay: 0.07s; }
        .mining-dust-bit:nth-child(5) { width: 2px; height: 2px; --dx: 12px; --dy: -14px; animation-delay: 0.05s; }
        .mining-chip {
            position: absolute;
            font-size: 0.55rem;
            color: rgba(252, 211, 77, 0.85);
            opacity: 0;
            pointer-events: none;
            animation: chipFly 2.4s ease-out infinite;
        }
        .mining-chip:nth-child(6) { --dx: 14px; --dy: -16px; animation-delay: 0.03s; }
        .mining-chip:nth-child(7) { --dx: 20px; --dy: -8px; animation-delay: 0.08s; }
        @keyframes miningStrike {
            0%, 100% {
                transform: translate(0, 0) rotate(-14deg) scale(1);
                filter: drop-shadow(0 0 0 rgba(110,231,183,0));
            }
            22% {
                transform: translate(-6px, -14px) rotate(-32deg) scale(1.03);
                filter: drop-shadow(0 0 0 rgba(110,231,183,0));
            }
            46% {
                transform: translate(10px, 12px) rotate(24deg) scale(1.1);
                filter: drop-shadow(0 0 14px rgba(110,231,183,0.45));
            }
            54% {
                transform: translate(12px, 14px) rotate(28deg) scale(1.07);
                filter: drop-shadow(0 0 10px rgba(252,211,77,0.35));
            }
            68% {
                transform: translate(4px, 4px) rotate(6deg) scale(1);
                filter: drop-shadow(0 0 4px rgba(110,231,183,0.15));
            }
        }
        @keyframes rockHitStrong {
            0%, 76%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
            80% { transform: translate(3px, 2px) rotate(4deg) scale(0.96); }
            84% { transform: translate(-2px, 3px) rotate(-3deg) scale(0.98); }
            88% { transform: translate(1px, 1px) rotate(2deg) scale(0.99); }
            92% { transform: translate(0, 0) rotate(0deg) scale(1); }
        }
        @keyframes rockHitLight {
            0%, 80%, 100% { transform: translate(0, 0) rotate(0deg); }
            84% { transform: translate(-1px, 1px) rotate(-2deg); }
            88% { transform: translate(1px, 0) rotate(1deg); }
        }
        @keyframes dustPop {
            0%, 42%, 100% { opacity: 0; transform: translate(0, 0) scale(0); }
            48% { opacity: 0.95; transform: translate(var(--dx, 8px), var(--dy, -8px)) scale(1); }
            62% { opacity: 0; transform: translate(calc(var(--dx, 8px) * 1.6), calc(var(--dy, -8px) * 1.4 - 6px)) scale(0.2); }
        }
        @keyframes chipFly {
            0%, 44%, 100% { opacity: 0; transform: translate(0, 0) rotate(0deg) scale(0.6); }
            50% { opacity: 0.85; transform: translate(var(--dx, 10px), var(--dy, -10px)) rotate(25deg) scale(1); }
            66% { opacity: 0; transform: translate(calc(var(--dx, 10px) * 1.5), calc(var(--dy, -10px) * 1.3 - 8px)) rotate(60deg) scale(0.4); }
        }
        .tool-icon-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 4px;
        }
        .tool-halo {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 88px;
            height: 88px;
            margin: -44px 0 0 -44px;
            border-radius: 50%;
            pointer-events: none;
            opacity: 0;
        }
        .tool-halo-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(252, 211, 77, 0);
            box-shadow: 0 0 0 0 rgba(252, 211, 77, 0);
        }
        .tool-icon-wrap.upgrade-flash .tool-halo {
            opacity: 1;
        }
        .tool-icon-wrap.upgrade-flash .tool-halo-ring:nth-child(1) {
            animation: upgradeRing 0.9s ease-out;
        }
        .tool-icon-wrap.upgrade-flash .tool-halo-ring:nth-child(2) {
            animation: upgradeRing 0.9s ease-out 0.12s;
        }
        .tool-icon-wrap.upgrade-flash .tool-halo-ring:nth-child(3) {
            animation: upgradeRing 0.9s ease-out 0.24s;
        }
        .tool-icon-wrap.upgrade-flash .spoon-icon {
            animation: upgradeIconFlash 0.9s ease-out;
        }
        .tool-success-fx {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 0;
            height: 0;
            pointer-events: none;
            z-index: 2;
        }
        .success-ray {
            position: absolute;
            left: 0;
            top: 0;
            width: 3px;
            height: 64px;
            margin-left: -1.5px;
            margin-top: -32px;
            transform-origin: center center;
            transform: rotate(var(--a, 0deg)) translateY(0) scaleY(0);
            background: linear-gradient(to top, rgba(252, 211, 77, 0), rgba(252, 211, 77, 0.95) 55%, rgba(255, 255, 255, 1));
            border-radius: 999px;
            opacity: 0;
        }
        .success-spark {
            position: absolute;
            left: -3px;
            top: -3px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0 10px #fcd34d, 0 0 16px #6ee7b7;
            opacity: 0;
        }
        .success-burst {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 96px;
            height: 96px;
            margin: -48px 0 0 -48px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.85);
            opacity: 0;
            pointer-events: none;
        }
        .tool-icon-wrap.upgrade-success::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 130px;
            height: 130px;
            margin: -65px 0 0 -65px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(252, 211, 77, 0.7) 0%, rgba(110, 231, 183, 0.35) 38%, transparent 72%);
            animation: successBloom 1.25s ease-out;
            pointer-events: none;
            z-index: 0;
        }
        .tool-icon-wrap.upgrade-success::after {
            content: '✦';
            position: absolute;
            left: 50%;
            top: -8px;
            transform: translateX(-50%) scale(0);
            font-size: 1.35rem;
            color: #fff;
            text-shadow: 0 0 12px #fcd34d, 0 0 22px #6ee7b7;
            animation: successStar 1.1s ease-out 0.08s;
            pointer-events: none;
            z-index: 3;
        }
        .tool-icon-wrap.upgrade-success .tool-halo {
            opacity: 1;
        }
        .tool-icon-wrap.upgrade-success .tool-halo-ring:nth-child(1) {
            animation: successRing 1.15s ease-out;
        }
        .tool-icon-wrap.upgrade-success .tool-halo-ring:nth-child(2) {
            animation: successRing 1.15s ease-out 0.1s;
        }
        .tool-icon-wrap.upgrade-success .tool-halo-ring:nth-child(3) {
            animation: successRing 1.15s ease-out 0.2s;
        }
        .tool-icon-wrap.upgrade-success .success-burst {
            animation: successBurst 1.05s ease-out;
        }
        .tool-icon-wrap.upgrade-success .success-ray {
            animation: successRay 1.05s ease-out forwards;
        }
        .tool-icon-wrap.upgrade-success .success-spark {
            animation: successSpark 0.95s ease-out forwards;
        }
        .tool-icon-wrap.upgrade-success .spoon-icon {
            animation: upgradeSuccessIcon 1.15s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 2;
        }
        .spoon-visual.upgrade-success-glow {
            animation: visualSuccessGlow 1.25s ease-out;
        }
        @keyframes successBloom {
            0% { opacity: 0; transform: scale(0.3); }
            25% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(1.55); }
        }
        @keyframes successStar {
            0% { opacity: 0; transform: translateX(-50%) scale(0) rotate(-30deg); }
            30% { opacity: 1; transform: translateX(-50%) scale(1.35) rotate(0deg); }
            55% { opacity: 1; transform: translateX(-50%) scale(1) rotate(15deg); }
            100% { opacity: 0; transform: translateX(-50%) scale(0.6) rotate(40deg); }
        }
        @keyframes successRing {
            0% {
                opacity: 0;
                transform: scale(0.4);
                border-color: rgba(255, 255, 255, 0);
                box-shadow: 0 0 0 0 rgba(252, 211, 77, 0);
            }
            20% {
                opacity: 1;
                border-color: rgba(255, 255, 255, 1);
                box-shadow: 0 0 30px 14px rgba(252, 211, 77, 0.95), 0 0 50px 20px rgba(110, 231, 183, 0.55);
            }
            100% {
                opacity: 0;
                transform: scale(1.75);
                border-color: rgba(252, 211, 77, 0);
                box-shadow: 0 0 0 0 rgba(252, 211, 77, 0);
            }
        }
        @keyframes successBurst {
            0% { opacity: 0; transform: scale(0.5); border-color: rgba(255, 255, 255, 0); }
            18% { opacity: 1; transform: scale(1); border-color: rgba(255, 255, 255, 0.95); }
            100% { opacity: 0; transform: scale(1.6); border-color: rgba(252, 211, 77, 0); }
        }
        @keyframes successRay {
            0% { opacity: 0; transform: rotate(var(--a, 0deg)) translateY(8px) scaleY(0.2); }
            22% { opacity: 1; transform: rotate(var(--a, 0deg)) translateY(-28px) scaleY(1); }
            100% { opacity: 0; transform: rotate(var(--a, 0deg)) translateY(-88px) scaleY(0.15); }
        }
        @keyframes successSpark {
            0% { opacity: 0; transform: rotate(var(--a, 0deg)) translateX(6px) scale(0.15); }
            18% { opacity: 1; }
            100% { opacity: 0; transform: rotate(var(--a, 0deg)) translateX(var(--d, 48px)) scale(0); }
        }
        @keyframes upgradeSuccessIcon {
            0% { transform: scale(0.8) rotate(-18deg); filter: brightness(1); }
            22% { transform: scale(1.42) rotate(10deg); filter: drop-shadow(0 0 28px rgba(252, 211, 77, 1)) drop-shadow(0 0 44px rgba(255, 255, 255, 0.85)) brightness(1.45); }
            42% { transform: scale(1.12) rotate(-6deg); filter: drop-shadow(0 0 14px rgba(252, 211, 77, 0.7)); }
            62% { transform: scale(1.32) rotate(8deg); filter: drop-shadow(0 0 22px rgba(110, 231, 183, 0.85)); }
            100% { transform: scale(1) rotate(0deg); filter: drop-shadow(0 0 0 rgba(252, 211, 77, 0)); }
        }
        @keyframes visualSuccessGlow {
            0%, 100% { filter: none; }
            25% { filter: drop-shadow(0 0 28px rgba(252, 211, 77, 0.35)); }
            50% { filter: drop-shadow(0 0 18px rgba(110, 231, 183, 0.28)); }
        }
        @keyframes upgradeRing {
            0% {
                opacity: 0;
                transform: scale(0.55);
                border-color: rgba(252, 211, 77, 0);
                box-shadow: 0 0 0 0 rgba(252, 211, 77, 0);
            }
            18% {
                opacity: 1;
                border-color: rgba(252, 211, 77, 0.95);
                box-shadow: 0 0 22px 10px rgba(252, 211, 77, 0.75), 0 0 36px 14px rgba(110, 231, 183, 0.45);
            }
            38% {
                opacity: 0.35;
                border-color: rgba(252, 211, 77, 0.35);
                box-shadow: 0 0 8px 4px rgba(252, 211, 77, 0.25);
            }
            52% {
                opacity: 1;
                border-color: rgba(255, 255, 255, 0.85);
                box-shadow: 0 0 28px 12px rgba(252, 211, 77, 0.9), 0 0 44px 18px rgba(255, 255, 255, 0.35);
            }
            100% {
                opacity: 0;
                transform: scale(1.45);
                border-color: rgba(252, 211, 77, 0);
                box-shadow: 0 0 0 0 rgba(252, 211, 77, 0);
            }
        }
        @keyframes upgradeIconFlash {
            0%, 100% {
                transform: rotate(0deg) scale(1);
                filter: drop-shadow(0 0 0 rgba(252, 211, 77, 0));
            }
            15% {
                transform: rotate(-10deg) scale(1.14);
                filter: drop-shadow(0 0 14px rgba(252, 211, 77, 1)) drop-shadow(0 0 26px rgba(255, 255, 255, 0.75));
            }
            35% {
                transform: rotate(4deg) scale(1.05);
                filter: drop-shadow(0 0 6px rgba(252, 211, 77, 0.45));
            }
            50% {
                transform: rotate(8deg) scale(1.16);
                filter: drop-shadow(0 0 18px rgba(252, 211, 77, 1)) drop-shadow(0 0 30px rgba(110, 231, 183, 0.65));
            }
            70% {
                transform: rotate(-4deg) scale(1.08);
                filter: drop-shadow(0 0 10px rgba(252, 211, 77, 0.6));
            }
        }
        .spoon-icon {
            position: relative;
            z-index: 1;
            font-size: 3rem;
            line-height: 1;
            transform-origin: 72% 88%;
            animation: miningStrike 2.4s ease-in-out infinite;
        }
        .spoon-status {
            margin-top: 4px;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .ore-spawn-countdown {
            display: none;
            margin: 6px 0 4px;
            min-height: 1.35rem;
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--gold);
            text-align: center;
            line-height: 1.35rem;
        }
        .ore-spawn-countdown.active {
            display: block;
        }
        .ore-spawn-countdown.pending-live {
            color: var(--mint);
        }
        .rate-line {
            margin-top: 8px;
            font-size: 0.74rem;
            color: var(--muted);
        }
        .rate-line b { color: var(--mint); font-weight: 700; }
        .yield-lock-hint {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid rgba(245,197,66,0.35);
            background: rgba(245,197,66,0.08);
            color: var(--gold);
            font-size: 0.74rem;
            line-height: 1.45;
            text-align: center;
        }
        .rate-yield-slide {
            margin-top: 6px;
            height: 1.35rem;
            overflow: hidden;
            text-align: center;
        }
        .rate-yield-track {
            transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
        }
        .rate-yield-item {
            height: 1.35rem;
            line-height: 1.35rem;
            font-size: 0.74rem;
            color: var(--muted);
            box-sizing: border-box;
        }
        .rate-yield-item b { color: var(--gold); font-weight: 700; }
        .hint {
            font-size: 0.72rem;
            color: var(--muted);
            line-height: 1.55;
            text-align: center;
        }
        .balance-box {
            text-align: center;
            padding: 10px 8px;
            border-radius: 12px;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(110,231,183,0.28);
            min-width: 0;
            overflow: hidden;
        }
        .save-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 8px;
            margin-bottom: 2px;
        }
        .btn-save {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(110,231,183,0.35);
            background: rgba(110,231,183,0.18);
            color: var(--mint);
            font-weight: 800;
            font-size: 0.92rem;
            cursor: pointer;
        }
        .btn-save:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .upgrade-actions {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 8px;
            margin: 0;
            padding: 0;
        }
        .upgrade-actions .btn-eunchong {
            flex: 0 0 auto;
            align-self: center;
        }
        .upgrade-btn-field {
            flex: 1;
            position: relative;
            min-width: 0;
            min-height: 0;
        }
        .upgrade-btn-field.layout-fixed {
            display: flex;
            align-items: stretch;
            justify-content: center;
        }
        .upgrade-btn-field.layout-fixed .btn-upgrade {
            position: static;
            left: auto;
            top: auto;
            width: 100%;
            max-width: none;
            min-width: 0;
            min-height: 52px;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 1rem;
        }
        .upgrade-btn-field.layout-fixed.has-random-pos {
            display: block;
            min-height: 52px;
            overflow: hidden;
        }
        .upgrade-btn-field.layout-fixed.has-random-pos .btn-upgrade {
            position: absolute;
            left: 0;
            top: 0;
            width: 25%;
            max-width: 25%;
            min-width: 2.6rem;
            min-height: 1.65rem;
            padding: 6px 5px;
            border-radius: 4px;
            font-size: 0.58rem;
        }
        .upgrade-count-picker {
            display: flex;
            gap: 4px;
            padding: 4px;
            border-radius: 12px;
            background: rgba(0,0,0,0.32);
            border: 1px solid rgba(110,231,183,0.16);
        }
        .upgrade-count-opt {
            flex: 1;
            max-width: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            color: rgba(236,253,245,0.45);
            font-weight: 800;
            font-size: 0.72rem;
            cursor: pointer;
            user-select: none;
            touch-action: manipulation;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .upgrade-count-opt:active {
            transform: scale(0.97);
        }
        .upgrade-count-opt input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .upgrade-count-opt:has(input:checked) {
            border-color: rgba(110,231,183,0.35);
            background: rgba(110,231,183,0.18);
            color: var(--mint);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.06), 0 2px 8px rgba(0,0,0,0.22);
        }
        .btn-upgrade {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: none;
            min-width: 0;
            min-height: 52px;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid rgba(252,211,77,0.38);
            background: rgba(252,211,77,0.12);
            color: var(--gold);
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            font-family: inherit;
            touch-action: manipulation;
            white-space: nowrap;
            box-sizing: border-box;
        }
        .btn-upgrade:active:not(:disabled) {
            transform: scale(0.96);
        }
        .btn-upgrade:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .upgrade-status {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .upgrade-promo-notice {
            display: none;
            font-size: 0.74rem;
            color: #f87171;
            text-align: center;
            font-weight: 700;
            line-height: 1.4;
            margin: 0;
        }
        .upgrade-promo-notice.on {
            display: block;
        }
        .upgrade-hint {
            font-size: 0.72rem;
            color: var(--muted);
            text-align: center;
            line-height: 1.45;
            margin: 0;
        }
        .upgrade-msg {
            min-height: 1.2em;
            font-size: 0.74rem;
            text-align: center;
            color: var(--muted);
            margin: 0;
        }
        .upgrade-msg.ok { color: var(--mint); }
        .upgrade-msg.err { color: #f87171; }
        .upgrade-row {
            overflow-anchor: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 0;
        }
        .eunchong-buff {
            display: none;
            font-size: 0.62rem;
            color: var(--muted);
            line-height: 1.35;
            text-align: center;
            letter-spacing: -0.01em;
        }
        .eunchong-buff.active {
            display: block;
        }
        .eunchong-buff #eunchongBuffLeft {
            color: rgba(252, 211, 77, 0.82);
            font-weight: 600;
        }
        .btn-eunchong {
            flex: 0 0 auto;
            width: auto;
            min-width: 52px;
            max-width: 4.8rem;
            padding: 10px 8px;
            border-radius: 10px;
            border: 1px solid rgba(252,211,77,0.32);
            background: rgba(252,211,77,0.08);
            color: var(--gold);
            font-weight: 700;
            font-size: 0.64rem;
            line-height: 1.15;
            white-space: nowrap;
            cursor: pointer;
            font-family: inherit;
            touch-action: manipulation;
        }
        .btn-eunchong:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            width: 100%;
            max-width: 340px;
            background: #101c18;
            border: 1px solid rgba(252,211,77,0.28);
            border-radius: 16px;
            padding: 18px 16px 14px;
        }
        .modal-box h3 {
            margin: 0 0 10px;
            text-align: center;
            font-size: 1rem;
            color: var(--gold);
        }
        .modal-box p {
            margin: 0 0 14px;
            font-size: 0.78rem;
            color: var(--muted);
            text-align: center;
            line-height: 1.55;
        }
        .modal-actions {
            display: flex;
            gap: 8px;
        }
        .modal-actions button {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 11px 12px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.88rem;
        }
        .btn-modal-cancel {
            background: rgba(255,255,255,0.08);
            color: var(--text);
            border: 1px solid var(--line);
        }
        .btn-modal-confirm {
            background: linear-gradient(135deg, #fcd34d, #d97706);
            color: #422006;
        }
        .save-msg {
            min-height: 1.2em;
            font-size: 0.74rem;
            text-align: center;
            color: var(--muted);
        }
        .save-msg.ok { color: var(--mint); }
        .save-msg.err { color: #f87171; }
        .weapon-row-wrap {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }
        .mining-section {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid rgba(110,231,183,0.14);
        }
        .mining-section-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 0;
            margin: 0 0 10px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 0.68rem;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: 0.02em;
            text-align: left;
        }
        .mining-section-toggle:hover { color: var(--text); }
        .mining-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin: 0 0 10px;
        }
        .mining-section-header .mining-section-toggle {
            flex: 1;
            min-width: 0;
            margin-bottom: 0;
        }
        .mining-section-title {
            font-weight: 700;
            line-height: 1.3;
        }
        .mining-section-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .mining-section-icon {
            font-size: 0.72rem;
            color: var(--muted);
            line-height: 1;
        }
        .mining-section-body {
            overflow: hidden;
            max-height: 520px;
            opacity: 1;
            transition: max-height 0.22s ease, opacity 0.2s ease, margin 0.22s ease;
        }
        .mining-section-body.collapsed {
            max-height: 0;
            opacity: 0;
            margin-top: 0;
            pointer-events: none;
        }
        .weapon-row-label {
            font-size: 0.68rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }
        .weapon-table-link {
            color: var(--mint);
            text-decoration: none;
            font-weight: 700;
            white-space: nowrap;
        }
        .weapon-table-link:hover { text-decoration: underline; }
        .weapon-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(110,231,183,0.05);
            border: 1px solid rgba(110,231,183,0.18);
        }
        .weapon-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .weapon-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text);
            word-break: break-all;
            line-height: 1.35;
        }
        .weapon-equipped-tag {
            display: inline-block;
            align-self: flex-start;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            color: var(--gold);
            background: rgba(252,211,77,0.12);
            border: 1px solid rgba(252,211,77,0.28);
        }
        .weapon-equipped-tag.on {
            display: inline-block;
        }
        .btn-weapon-equip,
        .btn-weapon-unequip {
            flex: 0 0 auto;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 0.74rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-weapon-equip {
            border: 1px solid rgba(110,231,183,0.38);
            background: rgba(110,231,183,0.12);
            color: var(--mint);
        }
        .btn-weapon-unequip {
            border: 1px solid rgba(248,113,113,0.35);
            background: rgba(248,113,113,0.1);
            color: #fca5a5;
        }
        .btn-weapon-equip:disabled,
        .btn-weapon-unequip:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .weapon-row-hint {
            margin-top: 8px;
            font-size: 0.68rem;
            color: var(--muted);
            text-align: center;
            line-height: 1.45;
        }
        .weapon-msg {
            min-height: 1.1em;
            margin-top: 6px;
            font-size: 0.72rem;
            text-align: center;
            color: var(--muted);
        }
        .weapon-msg.ok { color: var(--mint); }
        .weapon-msg.err { color: #f87171; }
        .ore-shard-line {
            margin-top: 6px;
            font-size: 0.68rem;
            color: var(--gold);
            text-align: center;
        }
        .ore-float-stack {
            position: absolute;
            inset: 0;
            z-index: 5;
            display: block;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            pointer-events: none;
        }
        .ore-float-btn {
            pointer-events: auto;
            position: absolute;
            left: 0;
            top: 0;
            transform: translate(-50%, -50%);
            width: 54px;
            height: 54px;
            padding: 0;
            border-radius: 50%;
            border: 2px solid rgba(252,211,77,0.55);
            background: radial-gradient(circle at 35% 30%, rgba(60,50,20,0.95), rgba(12,18,16,0.98));
            box-shadow: 0 4px 16px rgba(0,0,0,0.35), 0 0 14px rgba(252,211,77,0.18);
            color: var(--gold);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            animation: oreFloatIn 0.35s ease-out, orePulse 2.2s ease-in-out infinite;
            touch-action: manipulation;
        }
        .ore-float-btn:active { transform: translate(-50%, -50%) scale(0.94); }
        .ore-float-btn.is-blocked,
        .ore-float-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            animation: none;
            pointer-events: none;
        }
        .ore-float-btn .ore-chip-icon {
            font-size: 1.55rem;
            line-height: 1;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.35));
        }
        .ore-float-btn .ore-chip-timer {
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            min-width: 42px;
            padding: 1px 5px;
            border-radius: 8px;
            background: rgba(10,18,16,0.92);
            border: 1px solid rgba(252,211,77,0.35);
            font-size: 0.5rem;
            font-weight: 700;
            color: rgba(252,211,77,0.9);
            white-space: nowrap;
            line-height: 1.35;
        }
        .ore-float-btn .ore-main,
        .ore-float-btn .ore-sub {
            display: none;
        }
        .ore-float-btn .ore-timer {
            display: none;
        }
        @keyframes oreFloatIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.55); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        @keyframes orePulse {
            0%, 100% { box-shadow: 0 8px 28px rgba(0,0,0,0.45), 0 0 12px rgba(252,211,77,0.1); }
            50% { box-shadow: 0 8px 28px rgba(0,0,0,0.45), 0 0 22px rgba(252,211,77,0.28); }
        }
        .tool-roadmap-wrap {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }
        .tool-roadmap-label {
            font-size: 0.68rem;
            color: var(--muted);
            text-align: center;
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }
        .tool-roadmap {
            display: flex;
            align-items: flex-start;
            gap: 0;
            overflow-x: auto;
            overflow-y: hidden;
            padding: 6px 4px 10px;
            scroll-snap-type: x proximity;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(110,231,183,0.35) transparent;
        }
        .tool-roadmap::-webkit-scrollbar {
            height: 4px;
        }
        .tool-roadmap::-webkit-scrollbar-thumb {
            background: rgba(110,231,183,0.35);
            border-radius: 999px;
        }
        .tool-node {
            flex: 0 0 auto;
            width: 68px;
            scroll-snap-align: center;
            text-align: center;
            position: relative;
        }
        .tool-node:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 17px;
            left: calc(50% + 16px);
            width: calc(100% - 32px);
            height: 2px;
            background: rgba(110,231,183,0.16);
            z-index: 0;
        }
        .tool-node.done:not(:last-child)::after {
            background: rgba(110,231,183,0.42);
        }
        .tool-node-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            margin: 0 auto 5px;
            border-radius: 999px;
            font-size: 1.15rem;
            line-height: 1;
            position: relative;
            z-index: 1;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(110,231,183,0.18);
        }
        .tool-node-name {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            font-size: 0.58rem;
            line-height: 1.25;
            color: var(--muted);
            word-break: keep-all;
            padding: 0 1px;
        }
        .tool-node-lv {
            flex-shrink: 0;
            font-size: 0.5rem;
            font-weight: 700;
            color: rgba(236,253,245,0.45);
            letter-spacing: -0.02em;
        }
        .tool-node-label {
            min-width: 0;
        }
        .tool-node.done .tool-node-icon {
            border-color: rgba(110,231,183,0.35);
            opacity: 0.85;
        }
        .tool-node.done .tool-node-name {
            color: rgba(110,231,183,0.72);
        }
        .tool-node.current .tool-node-icon {
            width: 40px;
            height: 40px;
            font-size: 1.35rem;
            border-color: rgba(110,231,183,0.65);
            background: rgba(110,231,183,0.16);
            box-shadow: 0 0 14px rgba(110,231,183,0.28);
            animation: toolPulse 2s ease-in-out infinite;
        }
        .tool-node.current .tool-node-name {
            color: var(--mint);
            font-weight: 700;
        }
        .tool-node.current .tool-node-lv {
            color: rgba(110,231,183,0.82);
        }
        .tool-node.locked .tool-node-icon {
            opacity: 0.38;
            filter: grayscale(0.85);
            border-color: rgba(255,255,255,0.08);
        }
        .tool-node.locked .tool-node-name {
            opacity: 0.45;
        }
        @keyframes toolPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }
        @media (prefers-reduced-motion: reduce) {
            .spoon-icon,
            .mining-rock,
            .mining-dust-bit,
            .mining-chip {
                animation-duration: 4.8s;
            }
        }
        .nav-row {
            display: flex;
            gap: 8px;
            margin-top: 4px;
        }
        .btn-link {
            flex: 1;
            display: block;
            text-align: center;
            padding: 11px 12px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            background: rgba(110,231,183,0.12);
            border: 1px solid rgba(110,231,183,0.32);
            color: var(--mint);
        }
        .lock-box {
            text-align: center;
            padding: 24px 16px;
            line-height: 1.65;
        }
        .lock-box strong {
            display: block;
            font-size: 1.05rem;
            margin-bottom: 8px;
            color: var(--text);
        }
        .lock-box a {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(110,231,183,0.12);
            border: 1px solid rgba(110,231,183,0.32);
            color: var(--mint);
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <?php if ($mining_need_code) { ?>
        <h1>🥄 숟가락 채굴</h1>
        <p class="sub">회원 code로 접속하세요.<br>예) <code>/page/mining.php?code=XXXX</code></p>
    <?php } elseif ($mining_access_denied) { ?>
        <h1>🥄 숟가락 채굴</h1>
        <div class="card lock-box">
            <strong><?php echo htmlspecialchars($mining_access_msg, ENT_QUOTES, 'UTF-8'); ?></strong>
            <div><?php echo htmlspecialchars($mining_member['nick'], ENT_QUOTES, 'UTF-8'); ?> · 보유 게임냥</div>
            <div class="balance-value" style="margin:14px 0;"><?php echo htmlspecialchars($mining_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div>오픈 전까지는 입장할 수 없어요.</div>
            <a href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">지갑으로 돌아가기</a>
        </div>
    <?php } else {
        $tool = $mining_member['tool'];
        $upgrade = $tool['upgrade'] ?? null;
        $tool_roadmap = mining_tool_roadmap_items((int)($tool['level'] ?? 0));
        $eunchong_left_sec = (int)($mining_member['eunchong_left_sec'] ?? 0);
        $tool_lv = (int)($tool['level'] ?? 0);
        $eunchong_active = !empty($mining_member['eunchong_active']) && $eunchong_left_sec > 0;
        $upgrade_pct_now = $eunchong_active
            ? mining_upgrade_success_hint_str(true, $tool_lv)
            : mining_upgrade_success_hint_str(false, $tool_lv);
        $eunchong_left_fmt = $eunchong_active
            ? sprintf('%d:%02d', intdiv($eunchong_left_sec, 60), $eunchong_left_sec % 60)
            : '';
        $eunchong_buff_from = mining_upgrade_success_hint_str(false, $tool_lv);
        $eunchong_buff_to = mining_upgrade_success_hint_str(true, $tool_lv);
        $eunchong_cost_from = mining_upgrade_cost_fmt(mining_upgrade_cost_for_level($tool_lv, false));
        $eunchong_cost_to = mining_upgrade_cost_fmt(mining_upgrade_cost_for_level($tool_lv, true));
        $upgrade_cost_now_fmt = $eunchong_active && !empty($upgrade['cost_eunchong_fmt'])
            ? ($upgrade['cost_base_fmt'] . ' → ' . $upgrade['cost_eunchong_fmt'])
            : ($upgrade['cost_fmt'] ?? '');
        $yield_hourly_fmt = (string)($tool['hourly_yield_fmt'] ?? '');
        $yield_daily_fmt = (string)($tool['daily_yield_fmt'] ?? '');
        if ($yield_hourly_fmt === '' && function_exists('mining_tool_hourly_yield_fmt')) {
            $yield_hourly_fmt = mining_tool_hourly_yield_fmt((int)($tool['level'] ?? 0));
        }
        if ($yield_daily_fmt === '' && function_exists('mining_tool_daily_yield_fmt')) {
            $yield_daily_fmt = mining_tool_daily_yield_fmt((int)($tool['level'] ?? 0));
        }
        $weapon = $mining_member['weapon'] ?? ['has_weapon' => false, 'label' => '없음', 'equipped' => false];
        $mining_initial_pending_fmt = '0';
        $tool_rate_fmt = (string)($tool['rate_fmt'] ?? '');
        if (!empty($mining_sync_initial['sync'])) {
            $sync_init = $mining_sync_initial['sync'];
            $mining_initial_pending_fmt = (string)($sync_init['pending_fmt'] ?? '');
            if ($mining_initial_pending_fmt === '' && isset($sync_init['pending'])) {
                $mining_initial_pending_fmt = function_exists('mining_fmt_pending')
                    ? mining_fmt_pending($sync_init['pending'])
                    : (string)$sync_init['pending'];
            }
            if (!empty($sync_init['rate_fmt'])) {
                $tool_rate_fmt = (string)$sync_init['rate_fmt'];
            }
            if (!empty($sync_init['hourly_yield_fmt'])) {
                $yield_hourly_fmt = (string)$sync_init['hourly_yield_fmt'];
            }
            if (!empty($sync_init['daily_yield_fmt'])) {
                $yield_daily_fmt = (string)$sync_init['daily_yield_fmt'];
            }
        }
        if ($mining_initial_pending_fmt === '') {
            $mining_initial_pending_fmt = '0';
        }
    ?>
        <div class="top-bar">
            <div class="top-bar-head">
                <h1 id="pageTitle"><?php echo htmlspecialchars($tool['icon'] . ' ' . $tool['label'] . ' 채굴', ENT_QUOTES, 'UTF-8'); ?></h1>
                <span class="top-nick"><?php echo htmlspecialchars($mining_member['nick'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="card">
            <div class="spoon-visual">
                <div class="balance-corner balance-corner--left" aria-live="polite">
                    <span class="balance-corner-label">본방냥</span>
                    <span class="balance-corner-value" id="wNewpoint"><?php echo htmlspecialchars($mining_member['newpoint_fmt'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="balance-corner balance-corner--right" id="gamePointCorner" aria-live="polite">
                    <span class="balance-corner-label">보유 게임냥</span>
                    <span class="balance-corner-value" id="wPoint"><?php echo htmlspecialchars($mining_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="mining-scene" aria-hidden="true">
                    <div class="mining-rock-pile mining-rock-pile--left">
                        <span class="mining-rock rock-a">🪨</span>
                        <span class="mining-rock rock-b">🪨</span>
                        <span class="mining-rock rock-c">🪨</span>
                    </div>
                    <div class="mining-strike-zone">
                        <div class="mining-dust">
                            <span class="mining-dust-bit"></span>
                            <span class="mining-dust-bit"></span>
                            <span class="mining-dust-bit"></span>
                            <span class="mining-dust-bit"></span>
                            <span class="mining-dust-bit"></span>
                            <span class="mining-chip">◆</span>
                            <span class="mining-chip">◇</span>
                        </div>
                        <div class="tool-icon-wrap" id="toolIconWrap">
                            <div class="tool-halo" aria-hidden="true">
                                <span class="tool-halo-ring"></span>
                                <span class="tool-halo-ring"></span>
                                <span class="tool-halo-ring"></span>
                            </div>
                            <div class="tool-success-fx" id="toolSuccessFx" aria-hidden="true"></div>
                            <div class="spoon-icon" id="toolIcon"><?php echo $tool['icon']; ?></div>
                        </div>
                    </div>
                    <div class="mining-rock-pile mining-rock-pile--right">
                        <span class="mining-rock rock-a">🪨</span>
                        <span class="mining-rock rock-b">🪨</span>
                        <span class="mining-rock rock-c">🪨</span>
                    </div>
                    <div class="ore-float-stack" id="oreFloatStack" aria-live="polite"></div>
                    <div class="mining-ground"></div>
                </div>
                <div class="spoon-status" id="toolStatus"><?php echo htmlspecialchars($tool['status'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ore-spawn-countdown pending-live active" id="oreSpawnCountdown" aria-live="polite"><?php echo htmlspecialchars($mining_initial_pending_fmt, ENT_QUOTES, 'UTF-8'); ?>냥</div>
                <div class="rate-line">초당 <b id="rateValue">+<?php echo htmlspecialchars($tool_rate_fmt, ENT_QUOTES, 'UTF-8'); ?></b> 냥</div>
                <?php if (empty($tool['yield_unlocked'])) { ?>
                <div class="yield-lock-hint" id="yieldLockHint"><?php echo htmlspecialchars((string)($tool['unlock_hint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } else { ?>
                <div class="yield-lock-hint" id="yieldLockHint" style="display:none;"></div>
                <?php } ?>
                <div class="rate-yield-slide" id="rateYieldSlide" aria-live="polite">
                    <div class="rate-yield-track" id="rateYieldTrack">
                        <div class="rate-yield-item">1시간 약 <b id="hourlyYieldValue"><?php echo htmlspecialchars($yield_hourly_fmt !== '' ? $yield_hourly_fmt : '0', ENT_QUOTES, 'UTF-8'); ?></b>냥 (오프라인 포함)</div>
                        <div class="rate-yield-item">24시간 약 <b id="dailyYieldValue"><?php echo htmlspecialchars($yield_daily_fmt !== '' ? $yield_daily_fmt : '0', ENT_QUOTES, 'UTF-8'); ?></b>냥 (오프라인 포함)</div>
                    </div>
                </div>
                <div class="save-row" id="saveRow" style="display:none;">
                    <button type="button" class="btn-save" id="btnClaim">수령하기</button>
                    <div class="save-msg" id="saveMsg"></div>
                </div>
            </div>

            <?php if (!empty($tool['can_upgrade']) && is_array($upgrade)) { ?>
            <div class="upgrade-row" id="upgradeRow">
                <div class="eunchong-buff<?php echo $eunchong_active ? ' active' : ''; ?>" id="eunchongBuff">
                    은총 버프 · 확률 <?php echo htmlspecialchars($eunchong_buff_from, ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars($eunchong_buff_to, ENT_QUOTES, 'UTF-8'); ?>
                    · 비용 <?php echo htmlspecialchars($eunchong_cost_from, ENT_QUOTES, 'UTF-8'); ?>냥 → <?php echo htmlspecialchars($eunchong_cost_to, ENT_QUOTES, 'UTF-8'); ?>냥
                    · 채굴량 x<?php echo (int)MINING_EUNCHONG_YIELD_MULT; ?>
                    · <span id="eunchongBuffLeft"><?php echo htmlspecialchars($eunchong_left_fmt, ENT_QUOTES, 'UTF-8'); ?></span> 남음
                </div>
                <div class="upgrade-status">
                    <div class="upgrade-promo-notice" id="upgradePromoNotice"></div>
                    <div class="upgrade-hint" id="upgradeHint">
                        <?php echo ($upgrade['to_icon'] ?? '') . htmlspecialchars($upgrade['to_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?> · 게임냥 <?php echo htmlspecialchars($upgrade_cost_now_fmt, ENT_QUOTES, 'UTF-8'); ?>냥<br>
                        성공 확률 <span id="upgradePct"><?php echo htmlspecialchars($upgrade_pct_now, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="upgrade-msg" id="upgradeMsg"></div>
                </div>
                <div class="upgrade-count-picker" id="upgradeCountPicker">
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="1" checked>1</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="10">10</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="50">50</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="100">100</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="1000">1000</label>
                </div>
                <div class="upgrade-actions">
                    <button type="button" class="btn-eunchong" id="btnUseEunchong"<?php if ((int)($mining_member['eunchong_cnt'] ?? 0) < 1) { ?> style="display:none;"<?php } ?>>은총<?php echo number_format((int)($mining_member['eunchong_cnt'] ?? 0)); ?>개</button>
                    <div class="upgrade-btn-field layout-<?php echo htmlspecialchars($mining_upgrade_btn_layout, ENT_QUOTES, 'UTF-8'); ?>" id="upgradeBtnField">
                        <button type="button" class="btn-upgrade" id="btnUpgrade">강화하기</button>
                    </div>
                </div>
            </div>
            <?php } ?>

            <div class="mining-section weapon-row-wrap" id="weaponSection">
                <div class="mining-section-header">
                    <button type="button" class="mining-section-toggle" id="btnWeaponToggle" aria-expanded="true" aria-controls="weaponSectionBody">
                        <span class="mining-section-title">내 무기 · 채굴 결합</span>
                        <span class="mining-section-icon" id="weaponToggleIcon">▾</span>
                    </button>
                    <a class="weapon-table-link" href="/page/mining_weapon_bonus.php<?php echo htmlspecialchars($mining_guide_q, ENT_QUOTES, 'UTF-8'); ?>">광물·획득 표 →</a>
                </div>
                <div class="mining-section-body" id="weaponSectionBody">
                <div class="weapon-row">
                    <div class="weapon-info">
                        <span class="weapon-name" id="weaponLabel"><?php echo htmlspecialchars($weapon['label'] ?? '없음', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="weapon-equipped-tag<?php echo !empty($weapon['equipped']) ? ' on' : ''; ?>" id="weaponEquippedTag"<?php if (empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>>장착 중</span>
                    </div>
                    <button type="button" class="btn-weapon-equip" id="btnWeaponEquip"<?php if (empty($weapon['has_weapon']) || !empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>>장착</button>
                    <button type="button" class="btn-weapon-unequip" id="btnWeaponUnequip"<?php if (empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>>해제</button>
                </div>
                <div class="weapon-row-hint" id="weaponRowHint"<?php if (empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>><?php
                    $ore_hint = trim((string)($mining_member['ore_discover_hint'] ?? ''));
                    echo htmlspecialchars(
                        $ore_hint !== '' ? '장착 중 · ' . $ore_hint . ' · 1시간 내 터치' : '장착 중 · 1~60분 랜덤 광물 발견 · 1시간 내 터치',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></div>
                <div class="ore-shard-line" id="oreShardLine">✨ 은총조각 <?php echo (int)($mining_member['eunchong_shard'] ?? 0); ?>/<?php echo (int)MINING_ORE_EUNCHONG_SHARDS; ?></div>
                <div class="weapon-msg" id="weaponMsg"></div>
                </div>
            </div>

            <div class="mining-section tool-roadmap-wrap" id="roadmapSection">
                <button type="button" class="mining-section-toggle" id="btnRoadmapToggle" aria-expanded="true" aria-controls="roadmapSectionBody">
                    <span class="mining-section-title">장비 로드맵 · 주방 → 채굴기</span>
                    <span class="mining-section-icon" id="roadmapToggleIcon">▾</span>
                </button>
                <div class="mining-section-body" id="roadmapSectionBody">
                <div class="tool-roadmap" id="toolRoadmap">
                    <?php foreach ($tool_roadmap as $node) { ?>
                    <div class="tool-node <?php echo htmlspecialchars($node['state'], ENT_QUOTES, 'UTF-8'); ?>" data-level="<?php echo (int)$node['level']; ?>">
                        <span class="tool-node-icon"><?php echo $node['icon']; ?></span>
                        <span class="tool-node-name">
                            <span class="tool-node-lv">Lv<?php echo (int)$node['level']; ?></span>
                            <span class="tool-node-label"><?php echo htmlspecialchars($node['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    </div>
                    <?php } ?>
                </div>
                </div>
            </div>

        </div>

        <div class="nav-row">
            <a class="btn-link" href="/page/mining_roadmap.php<?php echo htmlspecialchars($mining_guide_q, ENT_QUOTES, 'UTF-8'); ?>">장비별 24h 획득량</a>
            <a class="btn-link" href="/page/mining_weapon_bonus.php<?php echo htmlspecialchars($mining_guide_q, ENT_QUOTES, 'UTF-8'); ?>">무기 결합 광물표</a>
            <a class="btn-link" href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">지갑으로</a>
        </div>
    <?php } ?>
</div>

<?php if (!$mining_need_code && !$mining_access_denied) { ?>
<div class="modal-overlay" id="eunchongModal" onclick="if(event.target===this)window.miningCloseEunchongModal&&miningCloseEunchongModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <h3>✨ 은총 사용</h3>
        <p>은총을 사용하시겠습니까?<br>5분간 강화 성공 확률·비용·채굴량이 상승합니다.<br>현재 장비 기준 분모·비용 1/10 · 채굴량 x<?php echo (int)MINING_EUNCHONG_YIELD_MULT; ?></p>
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="btnEunchongCancel">취소</button>
            <button type="button" class="btn-modal-confirm" id="btnEunchongConfirm">사용</button>
        </div>
    </div>
</div>
<?php } ?>

<?php if (!$mining_need_code && !$mining_access_denied) {
    if (!isset($mining_sync_initial)) {
        $mining_sync_initial = ['sync' => null, 'active_elsewhere' => false];
    }
?>
<script>
(function() {
    var TOOL = <?php echo json_encode($mining_member['tool'], JSON_UNESCAPED_UNICODE); ?>;
    var WEAPON = <?php echo json_encode($weapon, JSON_UNESCAPED_UNICODE); ?>;
    var ORE_FINDS = <?php echo json_encode($mining_member['ore_finds'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
    var ORE_SHARD = <?php echo (int)($mining_member['eunchong_shard'] ?? 0); ?>;
    var ORE_SHARD_NEED = <?php echo (int)MINING_ORE_EUNCHONG_SHARDS; ?>;
    var ORE_DISCOVER_HINT = <?php echo json_encode($mining_member['ore_discover_hint'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
    var RATE = Number(TOOL.rate) || <?php echo json_encode((float)MINING_RATE_SPOON, JSON_UNESCAPED_UNICODE); ?>;
    var HOURLY_YIELD_FMT = (TOOL && TOOL.hourly_yield_fmt) ? String(TOOL.hourly_yield_fmt) : '0';
    var DAILY_YIELD_FMT = (TOOL && TOOL.daily_yield_fmt) ? String(TOOL.daily_yield_fmt) : '0';
    var SAVE_MIN = <?php echo json_encode((float)MINING_SAVE_MIN, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_COST = <?php echo json_encode((int)MINING_UPGRADE_COST, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_PCT = <?php echo json_encode(MINING_UPGRADE_SUCCESS_PCT, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_PCT_EUNCHONG = <?php echo json_encode(MINING_UPGRADE_EUNCHONG_PCT, JSON_UNESCAPED_UNICODE); ?>;
    var memberEunchong = <?php echo (int)($mining_member['eunchong_cnt'] ?? 0); ?>;
    var memberEunchongActive = <?php echo !empty($mining_member['eunchong_active']) ? 'true' : 'false'; ?>;
    var memberEunchongLeftSec = <?php echo (int)($mining_member['eunchong_left_sec'] ?? 0); ?>;
    var PING_SEC = <?php echo json_encode((int)MINING_SYNC_PING_SEC, JSON_UNESCAPED_UNICODE); ?>;
    var POLL_SEC = <?php echo json_encode((int)MINING_SYNC_POLL_SEC, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BATCH_1000_COST = <?php echo json_encode((int)MINING_UPGRADE_BATCH_1000_COST, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BTN_MOVE_EVERY = <?php echo json_encode((int)MINING_UPGRADE_BTN_MOVE_EVERY, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BTN_LAYOUT = <?php echo json_encode($mining_upgrade_btn_layout, JSON_UNESCAPED_UNICODE); ?>;
    var CODE = <?php echo json_encode($mining_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($mining_api, JSON_UNESCAPED_UNICODE); ?>;
    var INITIAL_SYNC = <?php echo json_encode($mining_sync_initial['sync'] ?? null, JSON_UNESCAPED_UNICODE); ?>;
    var TOKEN_KEY = 'mining_lease_v2_' + CODE;
    var ORE_COUNTDOWN_SEC = 10;
    var oreNextDeadlineMs = null;
    var oreSpawnCountdown = document.getElementById('oreSpawnCountdown');
    var btnClaim = document.getElementById('btnClaim');
    var saveRow = document.getElementById('saveRow');
    var btnUpgrade = document.getElementById('btnUpgrade');
    var upgradeBtnField = document.getElementById('upgradeBtnField');
    var saveMsg = document.getElementById('saveMsg');
    var upgradeMsg = document.getElementById('upgradeMsg');
    var upgradePromoNotice = document.getElementById('upgradePromoNotice');
    var wNewpoint = document.getElementById('wNewpoint');
    var wPoint = document.getElementById('wPoint');
    var toolIcon = document.getElementById('toolIcon');
    var toolIconWrap = document.getElementById('toolIconWrap');
    var toolSuccessFx = document.getElementById('toolSuccessFx');
    var spoonVisual = toolIconWrap ? toolIconWrap.closest('.spoon-visual') : null;
    var toolStatus = document.getElementById('toolStatus');
    var rateValue = document.getElementById('rateValue');
    var hourlyYieldValue = document.getElementById('hourlyYieldValue');
    var dailyYieldValue = document.getElementById('dailyYieldValue');
    var rateYieldTrack = document.getElementById('rateYieldTrack');
    var pageTitle = document.getElementById('pageTitle');
    var upgradeRow = document.getElementById('upgradeRow');
    var btnWeaponToggle = document.getElementById('btnWeaponToggle');
    var weaponSectionBody = document.getElementById('weaponSectionBody');
    var weaponToggleIcon = document.getElementById('weaponToggleIcon');
    var btnRoadmapToggle = document.getElementById('btnRoadmapToggle');
    var roadmapSectionBody = document.getElementById('roadmapSectionBody');
    var roadmapToggleIcon = document.getElementById('roadmapToggleIcon');
    var toolRoadmap = document.getElementById('toolRoadmap');
    var btnUseEunchong = document.getElementById('btnUseEunchong');
    var eunchongBuff = document.getElementById('eunchongBuff');
    var eunchongBuffLeft = document.getElementById('eunchongBuffLeft');
    var eunchongModal = document.getElementById('eunchongModal');
    var btnEunchongCancel = document.getElementById('btnEunchongCancel');
    var btnEunchongConfirm = document.getElementById('btnEunchongConfirm');
    var upgradePct = document.getElementById('upgradePct');
    var upgradeHint = document.getElementById('upgradeHint');
    var weaponLabel = document.getElementById('weaponLabel');
    var weaponEquippedTag = document.getElementById('weaponEquippedTag');
    var weaponRowHint = document.getElementById('weaponRowHint');
    var weaponMsg = document.getElementById('weaponMsg');
    var btnWeaponEquip = document.getElementById('btnWeaponEquip');
    var btnWeaponUnequip = document.getElementById('btnWeaponUnequip');
    var oreFloatStack = document.getElementById('oreFloatStack');
    var oreShardLine = document.getElementById('oreShardLine');
    var oreTouchBusy = {};
    var usingEunchong = false;
    var weaponBusy = false;
    var miningStatusBase = '';
    var miningStatusDots = 1;

    var tabId = (Math.random().toString(36).slice(2) + Date.now().toString(36));
    var bc = null;
    try {
        bc = new BroadcastChannel('mining_leader_v2_' + CODE);
    } catch (e) {}

    var leaseToken = '';
    var isServerLeader = false;
    var isLocalLeader = false;
    var remoteLeaderAlive = false;
    var lastRemoteHb = 0;
    var pendingBase = 0;
    var syncAtSec = Math.floor(Date.now() / 1000);
    var leaseActiveElsewhere = false;
    var claiming = false;
    var upgrading = false;
    var memberPoint = <?php echo (int)($mining_member['point'] ?? 0); ?>;
    var memberNewpoint = <?php echo (int)floor((float)($mining_member['newpoint'] ?? 0)); ?>;
    var tickTimer = null;
    var pingTimer = null;
    var pollTimer = null;
    var hbTimer = null;
    var leaderWaitTimer = null;

    function loadToken() {
        try {
            return sessionStorage.getItem(TOKEN_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    function saveToken(token) {
        try {
            if (token) sessionStorage.setItem(TOKEN_KEY, token);
            else sessionStorage.removeItem(TOKEN_KEY);
        } catch (e) {}
    }

    function fmtPending(n) {
        var s = Number(n).toFixed(10);
        s = s.replace(/\.?0+$/, '');
        return s === '' ? '0' : s;
    }

    function fmtRemain(sec) {
        sec = Math.max(0, Math.ceil(sec));
        if (sec <= 0) return '0초';
        var y = Math.floor(sec / 31536000);
        sec %= 31536000;
        var d = Math.floor(sec / 86400);
        sec %= 86400;
        var h = Math.floor(sec / 3600);
        sec %= 3600;
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        var parts = [];
        if (y > 0) parts.push(y + '년');
        if (d > 0) parts.push(d + '일');
        if (h > 0) parts.push(h + '시간');
        if (m > 0) parts.push(m + '분');
        if (s > 0 || parts.length === 0) parts.push(s + '초');
        return parts.join(' ');
    }

    function currentAmount() {
        var nowSec = Date.now() / 1000;
        return Math.max(0, pendingBase + Math.max(0, nowSec - syncAtSec) * RATE);
    }

    var yieldSlideIndex = 0;
    var yieldSlideTimer = null;

    function applyYieldValues(source) {
        if (!source) return;
        var hourly = source.hourly_yield_fmt ? String(source.hourly_yield_fmt) : '';
        var daily = source.daily_yield_fmt ? String(source.daily_yield_fmt) : '';
        if (!hourly && daily) {
            var d = parseFloat(daily);
            if (!isNaN(d)) hourly = String(d / 24);
        }
        if (!hourly && typeof source.rate === 'number' && source.rate > 0) {
            hourly = String(source.rate * 3600);
        }
        if (!daily && typeof source.rate === 'number' && source.rate > 0) {
            daily = String(source.rate * 86400);
        }
        if (hourly) HOURLY_YIELD_FMT = hourly;
        if (daily) DAILY_YIELD_FMT = daily;
        if (hourlyYieldValue) hourlyYieldValue.textContent = HOURLY_YIELD_FMT;
        if (dailyYieldValue) dailyYieldValue.textContent = DAILY_YIELD_FMT;
    }

    function yieldSlideLineHeight() {
        return rateYieldSlide ? rateYieldSlide.offsetHeight : 0;
    }

    function setYieldSlideIndex(idx) {
        yieldSlideIndex = idx ? 1 : 0;
        if (!rateYieldTrack) return;
        var h = yieldSlideLineHeight();
        rateYieldTrack.style.transform = h > 0
            ? ('translate3d(0,' + (-yieldSlideIndex * h) + 'px,0)')
            : 'translate3d(0,0,0)';
    }

    function startYieldSlide() {
        if (!rateYieldTrack) return;
        setYieldSlideIndex(0);
        if (yieldSlideTimer) clearInterval(yieldSlideTimer);
        yieldSlideTimer = setInterval(function() {
            setYieldSlideIndex(1 - yieldSlideIndex);
        }, 4000);
    }

    function applyYieldUnlock(source) {
        source = source || {};
        var hint = document.getElementById('yieldLockHint');
        if (!hint) return;
        if (source.yield_unlocked) {
            hint.style.display = 'none';
            hint.textContent = '';
            return;
        }
        hint.style.display = 'block';
        var req = source.upgrade_attempts_required || 100;
        var cur = source.upgrade_attempts || 0;
        hint.textContent = source.unlock_hint || ('강화 ' + req + '회 후 채굴냥 적립 (현재 ' + cur + '/' + req + ')');
    }

    function applyRateFromSync(sync) {
        if (!sync) return;
        if (typeof sync.rate === 'number') {
            RATE = Number(sync.rate);
        }
        if (rateValue) {
            rateValue.textContent = '+' + (sync.rate_fmt || String(RATE));
        }
        applyYieldValues(sync);
        applyYieldUnlock(sync);
    }

    function applySync(sync, activeElsewhere) {
        if (!sync) return;
        pendingBase = Number(sync.pending) || 0;
        // pending은 서버 display_amount(오프라인 포함 현재 총량) — 중복 가산 방지
        syncAtSec = Math.floor(Date.now() / 1000);
        applyRateFromSync(sync);
        leaseActiveElsewhere = !!activeElsewhere;
        if (sync.is_leader) {
            isServerLeader = true;
            leaseToken = sync.lease_token || leaseToken;
            saveToken(leaseToken);
        } else if (!sync.lease_active || activeElsewhere) {
            isServerLeader = false;
        }
    }

    function bindMiningSection(toggleBtn, bodyEl, iconEl, storageKey, onExpand) {
        if (!toggleBtn || !bodyEl) return;
        function setCollapsed(collapsed, save) {
            bodyEl.classList.toggle('collapsed', collapsed);
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (iconEl) iconEl.textContent = collapsed ? '▸' : '▾';
            if (save) {
                try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (e) {}
            }
            if (!collapsed && typeof onExpand === 'function') {
                onExpand();
            }
        }
        var initCollapsed = false;
        try {
            var stored = localStorage.getItem(storageKey);
            if (stored === '1') initCollapsed = true;
            else if (stored === '0') initCollapsed = false;
        } catch (e) {}
        setCollapsed(initCollapsed, false);
        toggleBtn.addEventListener('click', function() {
            setCollapsed(!bodyEl.classList.contains('collapsed'), true);
        });
    }

    function updateClaimUi() {
        var amount = currentAmount();
        var canSave = amount + 1e-12 >= SAVE_MIN;
        if (saveRow) {
            saveRow.style.display = canSave ? 'flex' : 'none';
        }
        if (!btnClaim) return;
        btnClaim.disabled = !canSave || claiming;
        btnClaim.textContent = '수령하기';
    }

    function renderTick() {
        updateOreSpawnCountdown();
        updateClaimUi();
    }

    function setSaveMsg(text, kind) {
        if (!saveMsg) return;
        saveMsg.textContent = text || '';
        saveMsg.className = 'save-msg' + (kind ? (' ' + kind) : '');
    }

    function playUpgradeSuccessFlash() {
        if (!toolIconWrap) return;
        toolIconWrap.classList.remove('upgrade-flash', 'upgrade-success');
        if (spoonVisual) spoonVisual.classList.remove('upgrade-success-glow');

        if (toolSuccessFx) {
            toolSuccessFx.innerHTML = '';
            var burst = document.createElement('span');
            burst.className = 'success-burst';
            toolSuccessFx.appendChild(burst);
            var i;
            for (i = 0; i < 10; i++) {
                var ray = document.createElement('span');
                ray.className = 'success-ray';
                ray.style.setProperty('--a', (i * 36) + 'deg');
                toolSuccessFx.appendChild(ray);
            }
            for (i = 0; i < 14; i++) {
                var spark = document.createElement('span');
                spark.className = 'success-spark';
                spark.style.setProperty('--a', (i * 26 + 7) + 'deg');
                spark.style.setProperty('--d', (36 + Math.floor(Math.random() * 42)) + 'px');
                toolSuccessFx.appendChild(spark);
            }
        }

        void toolIconWrap.offsetWidth;
        toolIconWrap.classList.add('upgrade-success');
        if (spoonVisual) spoonVisual.classList.add('upgrade-success-glow');

        if (toolIconWrap._successTimer) clearTimeout(toolIconWrap._successTimer);
        toolIconWrap._successTimer = setTimeout(function() {
            toolIconWrap.classList.remove('upgrade-success');
            if (spoonVisual) spoonVisual.classList.remove('upgrade-success-glow');
            if (toolSuccessFx) toolSuccessFx.innerHTML = '';
        }, 1300);
    }

    function normalizeMiningStatusBase(text) {
        return String(text || '').replace(/[.…]+$/g, '');
    }

    function renderMiningStatusDots() {
        if (!toolStatus || !miningStatusBase) return;
        var dots = '';
        for (var i = 0; i < miningStatusDots; i++) dots += '.';
        toolStatus.textContent = miningStatusBase + dots;
    }

    function setMiningStatusBase(text) {
        miningStatusBase = normalizeMiningStatusBase(text);
        miningStatusDots = 1;
        renderMiningStatusDots();
    }

    function tickMiningStatusDots() {
        if (!toolStatus || !miningStatusBase) return;
        miningStatusDots = miningStatusDots >= 5 ? 1 : miningStatusDots + 1;
        renderMiningStatusDots();
    }

    function upgradeBtnLabel(tool) {
        return '강화하기';
    }

    function playUpgradeFlash() {
        if (!toolIconWrap) return;
        toolIconWrap.classList.remove('upgrade-flash');
        void toolIconWrap.offsetWidth;
        toolIconWrap.classList.add('upgrade-flash');
        if (toolIconWrap._flashTimer) clearTimeout(toolIconWrap._flashTimer);
        toolIconWrap._flashTimer = setTimeout(function() {
            toolIconWrap.classList.remove('upgrade-flash');
        }, 950);
    }

    function setUpgradeMsg(text, kind) {
        if (!upgradeMsg) return;
        upgradeMsg.textContent = text || '';
        upgradeMsg.className = 'upgrade-msg' + (kind ? (' ' + kind) : '');
    }

    function scrollRoadmapToCurrent(smooth) {
        if (!toolRoadmap) return;
        var cur = toolRoadmap.querySelector('.tool-node.current');
        if (!cur) return;
        var behavior = smooth ? 'smooth' : 'auto';
        if (typeof cur.scrollIntoView === 'function') {
            try {
                cur.scrollIntoView({ inline: 'center', block: 'nearest', behavior: behavior });
                return;
            } catch (e) {}
        }
        var containerRect = toolRoadmap.getBoundingClientRect();
        var nodeRect = cur.getBoundingClientRect();
        var delta = (nodeRect.left + nodeRect.width / 2) - (containerRect.left + containerRect.width / 2);
        var target = toolRoadmap.scrollLeft + delta;
        var max = Math.max(0, toolRoadmap.scrollWidth - toolRoadmap.clientWidth);
        target = Math.max(0, Math.min(target, max));
        toolRoadmap.scrollTo({ left: target, behavior: behavior });
    }

    function scheduleRoadmapScroll(smooth) {
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                scrollRoadmapToCurrent(smooth);
            });
        });
    }

    function updateRoadmap(level, doScroll) {
        if (!toolRoadmap) return;
        level = Number(level) || 0;
        var nodes = toolRoadmap.querySelectorAll('.tool-node');
        nodes.forEach(function(node) {
            var lv = Number(node.getAttribute('data-level'));
            node.className = 'tool-node';
            if (lv < level) node.classList.add('done');
            else if (lv === level) node.classList.add('current');
            else node.classList.add('locked');
        });
        if (doScroll !== false) {
            scheduleRoadmapScroll(doScroll !== 'instant');
        }
    }

    function applyTool(tool) {
        if (!tool) return;
        var prevLevel = Number(TOOL.level) || 0;
        TOOL = tool;
        RATE = Number(tool.rate) || RATE;
        if (toolIcon) toolIcon.textContent = tool.icon || '🥄';
        setMiningStatusBase(tool.status || (tool.label ? tool.label + '으로 채굴 중' : ''));
        if (rateValue) rateValue.textContent = '+' + (tool.rate_fmt || String(RATE));
        applyYieldValues(tool);
        if (pageTitle) pageTitle.textContent = (tool.icon || '') + ' ' + (tool.label || '') + ' 채굴';
        if (!tool.can_upgrade && upgradeRow) upgradeRow.style.display = 'none';
        else if (tool.can_upgrade && upgradeRow) upgradeRow.style.display = '';
        if (btnUpgrade && tool.upgrade) {
            btnUpgrade.textContent = upgradeBtnLabel(tool);
        }
        if (upgradeHint && tool.upgrade) {
            upgradeHint.innerHTML = upgradeHintHtml(tool);
            upgradePct = document.getElementById('upgradePct');
        }
        updateRoadmap(tool.level, Number(tool.level) !== prevLevel);
        updateEunchongUi();
        updateUpgradeUi();
        applyYieldUnlock(tool);
    }

    function fmtEunchongLeft(sec) {
        sec = Math.max(0, parseInt(sec, 10) || 0);
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function setWeaponMsg(text, kind) {
        if (!weaponMsg) return;
        weaponMsg.textContent = text || '';
        weaponMsg.className = 'weapon-msg' + (kind ? (' ' + kind) : '');
    }

    function applyWeaponState(j) {
        if (j && j.weapon) WEAPON = j.weapon;
        updateWeaponUi();
    }

    function oreTouchHint(item) {
        if (!item) return '터치하여 수령';
        if (item.is_shard) return '터치 → 은총조각 +' + (item.qty || 1);
        var v = item.pending_value_fmt || String(item.pending_value || '');
        return '터치 → 채굴량 +' + v + '냥';
    }

    function updateOreSpawnCountdown() {
        if (!oreSpawnCountdown) return;
        var equipped = WEAPON && WEAPON.equipped;
        if (equipped && oreNextDeadlineMs !== null) {
            var left = Math.max(0, Math.ceil((oreNextDeadlineMs - Date.now()) / 1000));
            if (left > 0 && left <= ORE_COUNTDOWN_SEC) {
                oreSpawnCountdown.classList.add('active');
                oreSpawnCountdown.classList.remove('pending-live');
                oreSpawnCountdown.textContent = '광물..' + left;
                return;
            }
        }
        oreSpawnCountdown.classList.add('active');
        oreSpawnCountdown.classList.add('pending-live');
        oreSpawnCountdown.textContent = fmtPending(currentAmount()) + '냥';
    }

    function applyOreSpawnPreview(j) {
        if (!j || !WEAPON || !WEAPON.equipped) {
            oreNextDeadlineMs = null;
            updateOreSpawnCountdown();
            return;
        }
        if (typeof j.ore_next_in_sec === 'number' && j.ore_next_in_sec >= 0 && j.ore_has_schedule) {
            oreNextDeadlineMs = Date.now() + (j.ore_next_in_sec * 1000);
        } else {
            oreNextDeadlineMs = null;
        }
        updateOreSpawnCountdown();
    }

    function applyOreState(j) {
        if (!j) return;
        if (Array.isArray(j.ore_finds)) ORE_FINDS = j.ore_finds;
        if (typeof j.eunchong_shard === 'number') ORE_SHARD = j.eunchong_shard;
        if (typeof j.eunchong_shard_need === 'number') ORE_SHARD_NEED = j.eunchong_shard_need;
        if (typeof j.ore_discover_hint === 'string') {
            ORE_DISCOVER_HINT = j.ore_discover_hint;
        }
        applyOreSpawnPreview(j);
        renderOreFinds();
        updateOreShardLine();
        updateWeaponUi();
    }

    function updateOreShardLine() {
        if (!oreShardLine) return;
        oreShardLine.textContent = '✨ 은총조각 ' + ORE_SHARD + '/' + ORE_SHARD_NEED;
    }

    function renderOreFinds() {
        if (!oreFloatStack) return;
        oreFloatStack.innerHTML = '';
        if (!ORE_FINDS || !ORE_FINDS.length) return;
        ORE_FINDS.forEach(function(item) {
            if (!item || !item.idx) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ore-float-btn';
            btn.dataset.findIdx = String(item.idx);
            btn.title = (item.label || '광물') + ' · ' + oreTouchHint(item);
            btn.setAttribute('aria-label', (item.label || '광물') + ' 터치하여 수령');

            var posX = parseFloat(item.pos_x_pct);
            var posY = parseFloat(item.pos_y_pct);
            if (!isFinite(posX) || posX <= 0) posX = 12 + Math.random() * 76;
            if (!isFinite(posY) || posY <= 0) posY = 14 + Math.random() * 72;
            btn.style.left = posX + '%';
            btn.style.top = posY + '%';

            var icon = document.createElement('span');
            icon.className = 'ore-chip-icon';
            icon.textContent = item.icon || '⛏️';

            var chipTimer = document.createElement('span');
            chipTimer.className = 'ore-chip-timer';
            chipTimer.dataset.leftSec = String(item.left_sec || 0);
            chipTimer.textContent = fmtRemain(item.left_sec || 0);

            var main = document.createElement('span');
            main.className = 'ore-main';
            main.textContent = item.message || '';
            var sub = document.createElement('span');
            sub.className = 'ore-sub';
            sub.textContent = oreTouchHint(item);
            var timer = document.createElement('span');
            timer.className = 'ore-timer';
            timer.dataset.leftSec = String(item.left_sec || 0);

            btn.appendChild(icon);
            btn.appendChild(chipTimer);
            btn.appendChild(main);
            btn.appendChild(sub);
            btn.appendChild(timer);
            btn.addEventListener('click', function() { touchOreFind(item.idx); });
            oreFloatStack.appendChild(btn);
        });
    }

    function touchOreFind(findIdx) {
        findIdx = parseInt(findIdx, 10);
        if (!findIdx || oreTouchBusy[findIdx]) return;
        oreTouchBusy[findIdx] = true;
        ajaxPost({ action: 'touch_ore', find_idx: findIdx }).then(function(j) {
            if (!j || !j.ok) {
                alert((j && j.data) ? j.data : '수령에 실패했어요.');
                return;
            }
            if (j.sync) applySync(j.sync, false);
            applyOreState(j);
            renderTick();
            if (j.data) setWeaponMsg(j.data.replace(/\n/g, ' · '), 'ok');
        }).catch(function() {
            alert('네트워크 오류가 발생했어요.');
        }).finally(function() {
            delete oreTouchBusy[findIdx];
        });
    }

    function tickOreTimers() {
        if (!oreFloatStack) return;
        var timers = oreFloatStack.querySelectorAll('.ore-chip-timer');
        for (var i = 0; i < timers.length; i++) {
            var el = timers[i];
            var left = parseInt(el.dataset.leftSec || '0', 10);
            if (left > 0) left--;
            el.dataset.leftSec = String(left);
            el.textContent = fmtRemain(left);
        }
    }

    function updateWeaponUi() {
        if (!WEAPON) WEAPON = { has_weapon: false, label: '없음', equipped: false };
        if (weaponLabel) weaponLabel.textContent = WEAPON.label || '없음';
        if (weaponEquippedTag) {
            weaponEquippedTag.style.display = WEAPON.equipped ? '' : 'none';
            weaponEquippedTag.className = 'weapon-equipped-tag' + (WEAPON.equipped ? ' on' : '');
        }
        if (weaponRowHint) {
            weaponRowHint.style.display = WEAPON.equipped ? '' : 'none';
            if (WEAPON.equipped) {
                var hint = (ORE_DISCOVER_HINT && ORE_DISCOVER_HINT !== '')
                    ? ('장착 중 · ' + ORE_DISCOVER_HINT + ' · 1시간 내 터치')
                    : '장착 중 · 1~60분 랜덤 광물 발견 · 1시간 내 터치';
                weaponRowHint.textContent = hint;
            }
        }
        updateOreSpawnCountdown();
        if (btnWeaponEquip) {
            btnWeaponEquip.style.display = (WEAPON.has_weapon && !WEAPON.equipped) ? '' : 'none';
            btnWeaponEquip.disabled = weaponBusy || !WEAPON.has_weapon || !!WEAPON.equipped;
        }
        if (btnWeaponUnequip) {
            btnWeaponUnequip.style.display = WEAPON.equipped ? '' : 'none';
            btnWeaponUnequip.disabled = weaponBusy || !WEAPON.equipped;
        }
    }

    function doEquipWeapon() {
        if (weaponBusy || !WEAPON || !WEAPON.has_weapon || WEAPON.equipped) return;
        weaponBusy = true;
        updateWeaponUi();
        setWeaponMsg('장착 중…', '');
        ajaxPost({ action: 'equip_weapon' }).then(function(j) {
            if (!j || !j.ok) {
                setWeaponMsg((j && j.data) ? j.data : '장착에 실패했어요.', 'err');
                return;
            }
            applyWeaponState(j);
            setWeaponMsg((j.data || '장착 완료').replace(/\n/g, ' · '), 'ok');
        }).catch(function() {
            setWeaponMsg('네트워크 오류가 발생했어요.', 'err');
        }).finally(function() {
            weaponBusy = false;
            updateWeaponUi();
        });
    }

    function doUnequipWeapon() {
        if (weaponBusy || !WEAPON || !WEAPON.equipped) return;
        weaponBusy = true;
        updateWeaponUi();
        setWeaponMsg('해제 중…', '');
        ajaxPost({ action: 'unequip_weapon' }).then(function(j) {
            if (!j || !j.ok) {
                setWeaponMsg((j && j.data) ? j.data : '해제에 실패했어요.', 'err');
                return;
            }
            applyWeaponState(j);
            setWeaponMsg((j.data || '해제 완료').replace(/\n/g, ' · '), 'ok');
        }).catch(function() {
            setWeaponMsg('네트워크 오류가 발생했어요.', 'err');
        }).finally(function() {
            weaponBusy = false;
            updateWeaponUi();
        });
    }

    if (btnWeaponEquip) {
        btnWeaponEquip.addEventListener('click', doEquipWeapon);
    }
    if (btnWeaponUnequip) {
        btnWeaponUnequip.addEventListener('click', doUnequipWeapon);
    }
    updateWeaponUi();
    renderOreFinds();
    updateOreShardLine();

    function applyEunchongState(j) {
        if (!j) return;
        if (typeof j.eunchong_cnt === 'number') memberEunchong = j.eunchong_cnt;
        if (typeof j.eunchong_active !== 'undefined') memberEunchongActive = !!j.eunchong_active;
        if (typeof j.eunchong_left_sec === 'number') memberEunchongLeftSec = j.eunchong_left_sec;
        if (j.sync) {
            applySync(j.sync, !!j.active_elsewhere);
        }
        updateEunchongUi();
        renderTick();
    }

    function currentUpgradePct() {
        var up = TOOL && TOOL.upgrade ? TOOL.upgrade : null;
        if (up) {
            if (memberEunchongActive && up.success_hint_eunchong) return up.success_hint_eunchong;
            if (up.success_hint) return up.success_hint;
            if (memberEunchongActive && up.success_pct_eunchong) return up.success_pct_eunchong;
            if (up.success_pct) return up.success_pct;
        }
        return memberEunchongActive ? UPGRADE_PCT_EUNCHONG : UPGRADE_PCT;
    }

    function upgradeCostFmt(tool) {
        if (!tool || !tool.upgrade) return '';
        var up = tool.upgrade;
        if (memberEunchongActive && up.cost_eunchong_fmt) {
            return up.cost_base_fmt + ' → ' + up.cost_eunchong_fmt;
        }
        return up.cost_fmt || '';
    }

    function upgradeHintHtml(tool) {
        if (!tool || !tool.upgrade) return '';
        var pct = memberEunchongActive
            ? (tool.upgrade.success_hint_eunchong || tool.upgrade.success_pct_eunchong || UPGRADE_PCT_EUNCHONG)
            : (tool.upgrade.success_hint || tool.upgrade.success_pct || UPGRADE_PCT);
        return (tool.upgrade.to_icon || '') + (tool.upgrade.to_label || '') + ' · 게임냥 ' + upgradeCostFmt(tool) + '냥<br>'
            + '성공 확률 <span id="upgradePct">' + pct + '</span>';
    }

    function updateEunchongUi() {
        var showBuff = memberEunchongActive && memberEunchongLeftSec > 0;
        if (eunchongBuff) {
            eunchongBuff.className = 'eunchong-buff' + (showBuff ? ' active' : '');
        }
        if (eunchongBuffLeft) {
            eunchongBuffLeft.textContent = showBuff ? fmtEunchongLeft(memberEunchongLeftSec) : '';
        }
        if (upgradePct) {
            upgradePct.textContent = currentUpgradePct();
        }
        if (upgradeHint && TOOL && TOOL.upgrade) {
            upgradeHint.innerHTML = upgradeHintHtml(TOOL);
            upgradePct = document.getElementById('upgradePct');
        }
        if (btnUseEunchong) {
            var hasEunchong = memberEunchong >= 1;
            btnUseEunchong.style.display = hasEunchong ? '' : 'none';
            if (hasEunchong) {
                btnUseEunchong.disabled = usingEunchong || !(TOOL && TOOL.can_upgrade);
                btnUseEunchong.textContent = '은총' + memberEunchong.toLocaleString('ko-KR') + '개';
            }
        }
        if (btnEunchongConfirm) {
            btnEunchongConfirm.disabled = usingEunchong || memberEunchong < 1;
        }
        updateUpgradeUi();
    }

    function openEunchongModal() {
        if (eunchongModal) eunchongModal.classList.add('open');
    }

    function closeEunchongModal() {
        if (eunchongModal) eunchongModal.classList.remove('open');
    }
    window.miningCloseEunchongModal = closeEunchongModal;

    function doUseEunchong() {
        if (usingEunchong || memberEunchong < 1) return;
        usingEunchong = true;
        updateEunchongUi();
        closeEunchongModal();
        setUpgradeMsg('은총 적용 중…', '');
        ajaxPost({ action: 'use_eunchong' }).then(function(j) {
            if (!j || !j.ok) {
                setUpgradeMsg((j && j.data) ? j.data : '은총 사용에 실패했어요.', 'err');
                return;
            }
            applyEunchongState(j);
            setUpgradeMsg((j.data || '은총 적용!').replace(/\n/g, ' · '), 'ok');
        }).catch(function() {
            setUpgradeMsg('네트워크 오류가 발생했어요.', 'err');
        }).finally(function() {
            usingEunchong = false;
            updateEunchongUi();
            updateUpgradeUi();
        });
    }

    function upgradeCostNow() {
        if (!TOOL || !TOOL.upgrade) return UPGRADE_COST;
        var up = TOOL.upgrade;
        if (memberEunchongActive && typeof up.cost_eunchong === 'number') {
            return up.cost_eunchong;
        }
        if (typeof up.cost === 'number') return up.cost;
        return UPGRADE_COST;
    }

    function selectedUpgradeTimes() {
        var el = document.querySelector('input[name="upgradeTimes"]:checked');
        var n = el ? parseInt(el.value, 10) : 1;
        if (isNaN(n) || n < 1) return 1;
        if ([1, 10, 50, 100, 1000].indexOf(n) === -1) return 1;
        return n;
    }

    function upgradeChargeNow() {
        return upgradeCostNow();
    }

    var UPGRADE_TIMES_KEY = 'mining_upgrade_times_' + CODE;
    var UPGRADE_TIMES_ALLOWED = [1, 10, 50, 100, 1000];

    function restoreUpgradeTimes() {
        try {
            var stored = localStorage.getItem(UPGRADE_TIMES_KEY);
            if (!stored) return;
            var n = parseInt(stored, 10);
            if (UPGRADE_TIMES_ALLOWED.indexOf(n) === -1) return;
            var target = document.querySelector('input[name="upgradeTimes"][value="' + n + '"]');
            if (target) target.checked = true;
        } catch (e) {}
    }

    function saveUpgradeTimes(times) {
        try {
            localStorage.setItem(UPGRADE_TIMES_KEY, String(times));
        } catch (e) {}
    }

    function upgradeAffordNow() {
        if (selectedUpgradeTimes() === 1000) {
            return memberPoint >= upgradeCostNow()
                && memberNewpoint >= UPGRADE_BATCH_1000_COST;
        }
        return memberPoint >= upgradeCostNow();
    }

    function updateUpgradePromoNotice() {
        if (!upgradePromoNotice) return;
        if (selectedUpgradeTimes() !== 1000) {
            upgradePromoNotice.classList.remove('on');
            upgradePromoNotice.textContent = '';
            return;
        }
        upgradePromoNotice.textContent = '본방냥 ' + UPGRADE_BATCH_1000_COST + '냥이 추가로 차감됩니다.';
        upgradePromoNotice.classList.add('on');
    }

    function updateUpgradeUi() {
        var canTry = !!(TOOL && TOOL.can_upgrade) && !upgrading && upgradeAffordNow();
        if (btnUpgrade) btnUpgrade.disabled = !canTry;
        document.querySelectorAll('input[name="upgradeTimes"]').forEach(function(r) {
            r.disabled = !canTry;
        });
        if (upgradePromoNotice) {
            updateUpgradePromoNotice();
        }
    }

    function setPointDisplay(fmt, point) {
        if (typeof point === 'number') memberPoint = point;
        if (fmt && wPoint) wPoint.textContent = fmt;
    }

    function setNewpointDisplay(fmt, np) {
        if (typeof np === 'number') memberNewpoint = np;
        if (fmt && wNewpoint) wNewpoint.textContent = fmt;
    }

    function applyUpgradeResponse(j, batchMode) {
        if (!j || !j.ok) {
            setUpgradeMsg((j && j.data) ? j.data : '강화에 실패했어요.', 'err');
            return;
        }
        applyEunchongState(j);
        applyWeaponState(j);
        applyOreState(j);
        if (j.sync) applySync(j.sync, false);
        renderTick();
        setPointDisplay(j.point_fmt, j.point);
        if (j.newpoint_fmt) {
            setNewpointDisplay(j.newpoint_fmt, j.newpoint);
        }
        if (j.tool) applyTool(j.tool);
        else updateEunchongUi();
        if (j.success) {
            playUpgradeSuccessFlash();
        } else if (!batchMode) {
            playUpgradeFlash();
        } else if ((j.batch_success || 0) > 0) {
            playUpgradeSuccessFlash();
        }
        var msg = j.data || (j.success ? '강화 성공!' : '강화 실패');
        var kind = (j.batch && (j.batch_success || 0) === 0 && (j.batch_fail || 0) > 0)
            ? 'err'
            : (j.success || (j.batch_success || 0) > 0 ? 'ok' : 'err');
        setUpgradeMsg(msg.replace(/\n/g, ' · '), kind);
    }

    var UPGRADE_CLICK_KEY = 'mining_upgrade_clicks_' + CODE;

    function centerUpgradeBtnPos() {
        if (!upgradeBtnField || !btnUpgrade) return;
        upgradeBtnField.classList.remove('has-random-pos');
        btnUpgrade.style.left = '';
        btnUpgrade.style.top = '';
    }

    function moveUpgradeBtnRandom() {
        if (!upgradeBtnField || !btnUpgrade) return;
        upgradeBtnField.classList.add('has-random-pos');
        requestAnimationFrame(function() {
            var fw = upgradeBtnField.clientWidth;
            var fh = upgradeBtnField.clientHeight;
            if (fw < 1 || fh < 1) return;
            var bw = btnUpgrade.offsetWidth;
            var bh = btnUpgrade.offsetHeight;
            var maxX = Math.max(0, fw - bw);
            var maxY = Math.max(0, fh - bh);
            btnUpgrade.style.left = Math.round(Math.random() * maxX) + 'px';
            btnUpgrade.style.top = Math.round(Math.random() * maxY) + 'px';
        });
    }

    function layoutUpgradeBtnPos() {
        if (UPGRADE_BTN_LAYOUT === 'random') {
            moveUpgradeBtnRandom();
            return;
        }
        if (upgradeBtnField && upgradeBtnField.classList.contains('has-random-pos')) {
            moveUpgradeBtnRandom();
            return;
        }
        centerUpgradeBtnPos();
    }

    function bumpUpgradeClickCount() {
        if (UPGRADE_BTN_LAYOUT === 'random') {
            moveUpgradeBtnRandom();
            return;
        }
        if (UPGRADE_BTN_MOVE_EVERY < 1) return;
        var n = 0;
        try { n = parseInt(localStorage.getItem(UPGRADE_CLICK_KEY) || '0', 10) || 0; } catch (e) {}
        n += 1;
        try { localStorage.setItem(UPGRADE_CLICK_KEY, String(n)); } catch (e) {}
        if (n % UPGRADE_BTN_MOVE_EVERY === 0) {
            moveUpgradeBtnRandom();
        }
    }

    function canRunUpgradeNow() {
        if (eunchongModal && eunchongModal.classList.contains('open')) return false;
        if (!btnUpgrade || btnUpgrade.disabled || upgrading) return false;
        if (!TOOL || !TOOL.can_upgrade || !upgradeAffordNow()) return false;
        return true;
    }

    function runUpgrade() {
        if (upgrading || !TOOL.can_upgrade || !upgradeAffordNow()) return;
        var times = selectedUpgradeTimes();
        var batchMode = times > 1;
        if (btnUpgrade) btnUpgrade.blur();
        playUpgradeFlash();
        upgrading = true;
        updateUpgradeUi();
        setUpgradeMsg(batchMode ? (times + '회 강화 시도 중…') : '강화 시도 중…', '');
        ajaxPost({ action: 'upgrade', times: times }).then(function(j) {
            applyUpgradeResponse(j, batchMode);
        }).catch(function() {
            setUpgradeMsg('네트워크 오류가 발생했어요.', 'err');
        }).finally(function() {
            upgrading = false;
            updateUpgradeUi();
            bumpUpgradeClickCount();
        });
    }

    if (btnUseEunchong) {
        btnUseEunchong.addEventListener('click', function() {
            if (btnUseEunchong.disabled || memberEunchong < 1) return;
            openEunchongModal();
        });
    }
    if (btnEunchongCancel) {
        btnEunchongCancel.addEventListener('click', closeEunchongModal);
    }
    if (btnEunchongConfirm) {
        btnEunchongConfirm.addEventListener('click', doUseEunchong);
    }
    updateEunchongUi();

    function ajaxPost(params) {
        var body = new URLSearchParams(params);
        body.set('code', CODE);
        if (leaseToken && !body.has('lease_token')) {
            body.set('lease_token', leaseToken);
        }
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); });
    }

    function ajaxSync(useToken) {
        var params = { action: 'sync' };
        if (useToken && leaseToken) params.lease_token = leaseToken;
        return ajaxPost(params);
    }

    function ajaxStatus() {
        var params = { action: 'status' };
        if (leaseToken) params.lease_token = leaseToken;
        return ajaxPost(params);
    }

    function ajaxRelease() {
        if (!leaseToken) return Promise.resolve({ ok: true });
        var body = new URLSearchParams({ action: 'release', code: CODE, lease_token: leaseToken });
        var payload = body.toString();
        if (navigator.sendBeacon) {
            try {
                navigator.sendBeacon(API, new Blob([payload], { type: 'application/x-www-form-urlencoded' }));
                return Promise.resolve({ ok: true });
            } catch (e) {}
        }
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload,
            credentials: 'same-origin',
            keepalive: true
        }).then(function() { return { ok: true }; }).catch(function() { return { ok: true }; });
    }

    function handleSyncResponse(j, fromAcquire) {
        if (!j || !j.ok) return;
        applySync(j.sync, j.active_elsewhere);
        applyOreState(j);
        if (j.sync && j.sync.is_leader) {
            isServerLeader = true;
            leaseToken = j.sync.lease_token || leaseToken;
            saveToken(leaseToken);
        } else if (j.active_elsewhere) {
            isServerLeader = false;
        } else if (fromAcquire && isLocalLeader) {
            isServerLeader = true;
        }
        renderTick();
    }

    function startLeaderMode() {
        isLocalLeader = true;
        remoteLeaderAlive = false;
        var stored = loadToken();
        if (stored) leaseToken = stored;
        ajaxSync(!!leaseToken).then(function(j) {
            handleSyncResponse(j, true);
            if (!isServerLeader && j && j.active_elsewhere) {
                startFollowerMode();
            }
        }).catch(function() {});
    }

    function startFollowerMode() {
        isLocalLeader = false;
        isServerLeader = false;
        ajaxStatus().then(function(j) {
            if (!j || !j.ok) return;
            applySync(j.sync, j.active_elsewhere);
            applyEunchongState(j);
            applyWeaponState(j);
            applyOreState(j);
            updateUpgradeUi();
            renderTick();
        }).catch(function() {});
    }

    function tryPromoteLocalLeader() {
        if (isLocalLeader) return;
        if (remoteLeaderAlive && (Date.now() - lastRemoteHb) < 6000) return;
        startLeaderMode();
    }

    function postBc(msg) {
        if (!bc) return;
        try { bc.postMessage(msg); } catch (e) {}
    }

    if (bc) {
        bc.onmessage = function(ev) {
            var data = ev.data || {};
            if (data.tabId === tabId) return;
            if (data.type === 'hello') {
                postBc({ type: 'hello', tabId: tabId, leader: isLocalLeader });
            }
            if (data.type === 'hello' && data.leader) {
                remoteLeaderAlive = true;
                lastRemoteHb = Date.now();
            }
            if (data.type === 'leader') {
                remoteLeaderAlive = true;
                lastRemoteHb = Date.now();
                if (!isLocalLeader) startFollowerMode();
            }
            if (data.type === 'bye' && data.leader) {
                remoteLeaderAlive = false;
                lastRemoteHb = 0;
                setTimeout(tryPromoteLocalLeader, 300);
            }
        };
        postBc({ type: 'hello', tabId: tabId, leader: false });
        leaderWaitTimer = setTimeout(function() {
            if (!remoteLeaderAlive) {
                startLeaderMode();
                postBc({ type: 'leader', tabId: tabId });
            } else {
                startFollowerMode();
            }
        }, 220);
        hbTimer = setInterval(function() {
            if (isLocalLeader) postBc({ type: 'leader', tabId: tabId });
        }, 3000);
    } else {
        startLeaderMode();
    }

    if (INITIAL_SYNC) {
        applySync(INITIAL_SYNC, false);
    }
    applyOreSpawnPreview({
        ore_next_in_sec: <?php echo json_encode(isset($mining_member['ore_next_in_sec']) ? (int)$mining_member['ore_next_in_sec'] : null, JSON_UNESCAPED_UNICODE); ?>,
        ore_has_schedule: <?php echo !empty($mining_member['ore_has_schedule']) ? 'true' : 'false'; ?>
    });
    renderTick();
    requestAnimationFrame(function() {
        requestAnimationFrame(startYieldSlide);
    });

    updateRoadmap(TOOL.level, 'instant');
    setTimeout(function() { scheduleRoadmapScroll(false); }, 150);
    window.addEventListener('resize', function() {
        scheduleRoadmapScroll(false);
        setYieldSlideIndex(yieldSlideIndex);
    });
    bindMiningSection(btnWeaponToggle, weaponSectionBody, weaponToggleIcon, 'mining_weapon_collapsed_' + CODE);
    bindMiningSection(btnRoadmapToggle, roadmapSectionBody, roadmapToggleIcon, 'mining_roadmap_collapsed_' + CODE, function() {
        scheduleRoadmapScroll(false);
    });
    setMiningStatusBase(TOOL.status || '');

    tickTimer = setInterval(renderTick, 250);
    setInterval(tickMiningStatusDots, 400);

    pingTimer = setInterval(function() {
        if (!isLocalLeader || !isServerLeader) return;
        ajaxSync(true).then(function(j) {
            handleSyncResponse(j, false);
            if (j && j.active_elsewhere) {
                isServerLeader = false;
                leaseToken = '';
                saveToken('');
                startFollowerMode();
            }
        }).catch(function() {});
    }, PING_SEC * 1000);

    pollTimer = setInterval(function() {
        if (isLocalLeader && isServerLeader) return;
        var req = isLocalLeader ? ajaxSync(false) : ajaxStatus();
        req.then(function(j) {
            if (!j || !j.ok) return;
            handleSyncResponse(j, isLocalLeader);
            applyEunchongState(j);
            applyWeaponState(j);
            applyOreState(j);
            updateUpgradeUi();
            if (isLocalLeader && !j.active_elsewhere && j.sync && j.sync.is_leader) {
                isServerLeader = true;
            }
        }).catch(function() {});
    }, POLL_SEC * 1000);

    setInterval(function() {
        tickOreTimers();
        updateOreSpawnCountdown();
        if (oreFloatStack) {
            var needRefresh = false;
            var timers = oreFloatStack.querySelectorAll('.ore-chip-timer');
            for (var t = 0; t < timers.length; t++) {
                if (parseInt(timers[t].dataset.leftSec || '0', 10) <= 0) {
                    needRefresh = true;
                    break;
                }
            }
            if (needRefresh) {
                ajaxStatus().then(function(j) {
                    if (j && j.ok) applyOreState(j);
                }).catch(function() {});
            }
        }
        if (memberEunchongLeftSec > 0) {
            memberEunchongLeftSec -= 1;
            if (memberEunchongLeftSec <= 0) {
                memberEunchongLeftSec = 0;
                memberEunchongActive = false;
                updateEunchongUi();
                ajaxStatus().then(function(j) {
                    if (j && j.ok) {
                        applyEunchongState(j);
                        applyWeaponState(j);
                    }
                }).catch(function() {});
            } else {
                if (eunchongBuffLeft) {
                    eunchongBuffLeft.textContent = fmtEunchongLeft(memberEunchongLeftSec);
                }
            }
        }
    }, 1000);

    if (btnUpgrade) {
        restoreUpgradeTimes();
        btnUpgrade.addEventListener('click', runUpgrade);
        updateUpgradeUi();
        layoutUpgradeBtnPos();
        window.addEventListener('resize', function() {
            layoutUpgradeBtnPos();
        });
        document.querySelectorAll('input[name="upgradeTimes"]').forEach(function(r) {
            r.addEventListener('change', function() {
                saveUpgradeTimes(selectedUpgradeTimes());
                updateUpgradeUi();
                layoutUpgradeBtnPos();
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key !== '1' && e.code !== 'Digit1' && e.code !== 'Numpad1') return;
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            var el = e.target;
            if (el) {
                var tag = el.tagName ? el.tagName.toUpperCase() : '';
                if (tag === 'TEXTAREA' || tag === 'SELECT') return;
                if (tag === 'INPUT') {
                    var ty = (el.type || '').toLowerCase();
                    if (ty === 'text' || ty === 'password' || ty === 'search' || ty === 'number' || ty === 'email' || ty === 'tel') return;
                }
                if (el.isContentEditable) return;
            }
            if (!canRunUpgradeNow()) return;
            e.preventDefault();
            runUpgrade();
        });
    }

    if (btnClaim) {
        btnClaim.addEventListener('click', function() {
            var amount = currentAmount();
            if (claiming || amount + 1e-12 < SAVE_MIN) return;
            claiming = true;
            updateClaimUi();
            setSaveMsg('수령 중…', '');

            ajaxPost({ action: 'claim' }).then(function(j) {
                if (!j || !j.ok) {
                    setSaveMsg((j && j.data) ? j.data : '수령에 실패했어요.', 'err');
                    return;
                }
                if (j.sync) {
                    applySync(j.sync, false);
                } else {
                    pendingBase = 0;
                    syncAtSec = Math.floor(Date.now() / 1000);
                }
                applyOreState(j);
                renderTick();
                if (wNewpoint && j.newpoint_fmt) wNewpoint.textContent = j.newpoint_fmt;
                setSaveMsg(j.data || '수령 완료', 'ok');
            }).catch(function() {
                setSaveMsg('네트워크 오류가 발생했어요.', 'err');
            }).finally(function() {
                claiming = false;
                updateClaimUi();
            });
        });
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) return;
        if (isLocalLeader && isServerLeader) {
            ajaxSync(true).then(function(j) { handleSyncResponse(j, false); }).catch(function() {});
        } else {
            ajaxStatus().then(function(j) {
                if (j && j.ok) handleSyncResponse(j, false);
            }).catch(function() {});
        }
        renderTick();
    });

    window.addEventListener('pagehide', function() {
        if (isLocalLeader) postBc({ type: 'bye', tabId: tabId, leader: true });
        ajaxRelease();
        saveToken('');
        if (tickTimer) clearInterval(tickTimer);
        if (pingTimer) clearInterval(pingTimer);
        if (pollTimer) clearInterval(pollTimer);
        if (hbTimer) clearInterval(hbTimer);
        if (leaderWaitTimer) clearTimeout(leaderWaitTimer);
        if (bc) try { bc.close(); } catch (e) {}
    });
})();
</script>
<?php } ?>
</body>
</html>
