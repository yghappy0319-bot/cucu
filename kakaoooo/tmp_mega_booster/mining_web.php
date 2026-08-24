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
require_once __DIR__ . '/mining_durability.inc.php';
require_once __DIR__ . '/mining_weapon.inc.php';
require_once __DIR__ . '/mining_ore.inc.php';

define('WALLET_LIB_ONLY', true);
require_once __DIR__ . '/wallet_web.php';

function mining_fmt_game($n) {
    if (function_exists('wallet_fmt_game')) {
        return wallet_fmt_game($n);
    }
    if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($n, '');
    }
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '');
    }
    return function_exists('냥_숫자콤마') ? 냥_숫자콤마($n) : number_format((float)$n);
}

/** 게임냥 정수 문자열 (천경·해 · (int) 금지) */
function mining_point_str($v): string {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ltrim((string)$s, '0') ?: '0';
}

/** 회원 게임냥 CAST 재조회 */
function mining_member_point_refresh(string $nick): string {
    $nick_esc = addslashes(trim($nick));
    if ($nick_esc === '') {
        return '0';
    }
    $row = @db_select("SELECT CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
    return mining_point_str($row['point'] ?? '0');
}

function mining_point_payload($pointRaw): array {
    $point = mining_point_str($pointRaw);
    return [
        'point' => $point,
        'point_fmt' => mining_fmt_game($point),
    ];
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

$mining_actions = ['status', 'claim', 'upgrade', 'upgrade10', 'use_eunchong', 'sync', 'release', 'equip_weapon', 'unequip_weapon', 'touch_ore', 'repair'];
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
        if (function_exists('wallet_퇴근_차단_여부') && wallet_퇴근_차단_여부()) {
            wallet_json(['ok' => false, 'data' => wallet_퇴근_차단_메시지()]);
        }
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
            'member' => array_merge([
                'nick' => $nick,
                'newpoint' => (float)($fresh['newpoint'] ?? 0),
                'newpoint_fmt' => wallet_fmt_new($fresh['newpoint'] ?? 0),
            ], mining_point_payload($fresh['point'] ?? mining_member_point_refresh($nick))),
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
        // 결합 배율 반영 — 초당·24h 즉시 갱신
        $tool = mining_tool_payload_for_nick($nick);
        wallet_json(array_merge(['ok' => true, 'type' => 'equip_weapon', 'tool' => $tool], $result));
    }

    if ($req_action === 'unequip_weapon') {
        $result = mining_weapon_unequip_execute($nick);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '해제 실패']);
        }
        $tool = mining_tool_payload_for_nick($nick);
        wallet_json(array_merge(['ok' => true, 'type' => 'unequip_weapon', 'tool' => $tool], $result));
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

    if ($req_action === 'repair') {
        $amount = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : (int)MINING_DURABILITY_REPAIR_STEP;
        $result = mining_repair_execute($nick, $amount);
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '수리 실패']);
        }
        // mining_repair_execute가 DB 차감 후 newpoint를 돌려줌
        // (wallet_member_refresh는 ['row'=>…] 래퍼라 newpoint가 비어 0으로 표시되던 버그 방지)
        $np_after = $result['newpoint'] ?? ($member['newpoint'] ?? 0);
        $np_fmt = wallet_fmt_new($np_after);
        $result['newpoint'] = (float)$np_after;
        $result['newpoint_fmt'] = $np_fmt;
        wallet_json(array_merge([
            'ok' => true,
            'type' => 'repair',
            'member' => array_merge([
                'nick' => $nick,
                'newpoint' => (float)$np_after,
                'newpoint_fmt' => $np_fmt,
            ], mining_point_payload($member['point'] ?? mining_member_point_refresh($nick))),
        ], $result, mining_ore_member_state($nick)));
    }

    if ($req_action === 'use_eunchong') {
        $tier = (int)($_REQUEST['tier'] ?? 1);
        if ($tier < 1 || $tier > 3) {
            $tier = 1;
        }
        $result = mining_use_eunchong_execute($nick, $tier);
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
        // 강화와 채굴 정산 분리: 실패 강화는 채굴에 손대지 않음.
        // 성공 시에만 execute 내부에서 레벨/내구 변경 직전 1회 commit.
        $booster = function_exists('mega_booster_request_wanted')
            ? mega_booster_request_wanted()
            : (!empty($_REQUEST['booster']) && (string)$_REQUEST['booster'] !== '0');
        if ($req_action === 'upgrade10') {
            // 하위 호환: 예전 10회 액션 → 100회
            $times = 100;
        } else {
            $times = (int)($_REQUEST['times'] ?? 1);
            $allowed_times = function_exists('mining_upgrade_batch_times_allowed')
                ? mining_upgrade_batch_times_allowed()
                : [1, 100, 500, 1000, 3000, 5000];
            if (!in_array($times, $allowed_times, true)) {
                $times = 1;
            }
        }
        if ($times <= 1) {
            $result = mining_upgrade_execute($nick, ['skip_sync' => true, 'booster' => $booster]);
            $type = 'upgrade';
        } else {
            $result = mining_upgrade_execute_batch($nick, $times, $booster);
            $type = 'upgrade_batch';
        }
        if (empty($result['ok'])) {
            wallet_json(['ok' => false, 'data' => $result['data'] ?? '강화 실패']);
        }
        $upgrade_payload = array_merge(['ok' => true, 'type' => $type, 'times' => $times], $result, mining_eunchong_payload($nick));
        // sync 절대 미포함 — 강화 응답으로 채굴 UI 리셋 금지
        unset($upgrade_payload['sync']);
        if ($times <= 1) {
            $upgrade_payload = array_merge($upgrade_payload, mining_ore_member_state($nick));
            unset($upgrade_payload['sync']);
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

// 인증·광물 조회 전에 로딩 셸을 먼저 flush → 흰 화면 대기 체감 감소
if (!defined('MINING_EARLY_BOOT')) {
    define('MINING_EARLY_BOOT', true);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="ko"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">';
    echo '<meta name="theme-color" content="#0a1210">';
    echo '<title>채굴</title>';
    echo '<style>';
    echo 'html,body{margin:0;min-height:100%;background:#0a1210;color:#d8e6df}';
    echo '#miningBoot{position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0a1210;font:600 14px/1.4 system-ui,-apple-system,sans-serif}';
    echo '#miningBoot .spin{width:36px;height:36px;border:3px solid rgba(216,230,223,.18);border-top-color:#7dcea0;border-radius:50%;animation:miningBootSpin .7s linear infinite}';
    echo '#miningBoot p{margin:14px 0 0;opacity:.88}';
    echo '@keyframes miningBootSpin{to{transform:rotate(360deg)}}';
    echo '</style></head><body>';
    echo '<div id="miningBoot" aria-live="polite"><div class="spin" aria-hidden="true"></div><p>채굴 여는 중…</p></div>';
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
}

$mining_code = wallet_코드_해석();
if ($mining_code === '' && isset($_REQUEST['code'])) {
    $mining_code = trim((string)$_REQUEST['code']);
}

$mining_need_code = true;
$mining_off_work = false;
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
    if (function_exists('wallet_nick_is_퇴근') && wallet_nick_is_퇴근($nick_page)) {
        $mining_off_work = true;
        $mining_need_code = true;
        $mining_member = null;
    } else {
        wallet_코드_쿠키_저장($mining_code);
        $mining_need_code = false;
        mining_data_ensure_table();
        // 첫 HTML은 simulate 표시만 — sync commit 은 이후 AJAX 에서 처리 (진입 체감 개선)
        $mining_tool = mining_tool_payload_for_nick($nick_page);
        $mining_sync_initial = mining_sync_read($nick_page);
        $mining_eunchong = mining_eunchong_payload($nick_page);
        $mining_weapon = mining_weapon_payload($nick_page, $row);
        $mining_ore = mining_ore_member_state($nick_page);
        $mining_member = array_merge([
            'nick' => $nick_page,
            'newpoint' => (float)($row['newpoint'] ?? 0),
            'newpoint_fmt' => wallet_fmt_new($row['newpoint'] ?? 0),
            'tool' => $mining_tool,
            'weapon' => $mining_weapon,
        ], mining_point_payload($row['point'] ?? mining_member_point_refresh($nick_page)), $mining_eunchong, $mining_ore);
        if (!mining_access_allowed($nick_page)) {
            $mining_access_denied = true;
        }
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
        if (function_exists('wallet_nick_is_퇴근') && wallet_nick_is_퇴근($nick_page)) {
            $mining_off_work = true;
            $mining_need_code = true;
            $mining_member = null;
        } else {
            wallet_코드_쿠키_저장($mining_code);
            $mining_need_code = false;
            mining_data_ensure_table();
            // 첫 HTML은 simulate 표시만 — sync commit 은 이후 AJAX 에서 처리 (진입 체감 개선)
            $mining_tool = mining_tool_payload_for_nick($nick_page);
            $mining_sync_initial = mining_sync_read($nick_page);
            $mining_eunchong = mining_eunchong_payload($nick_page);
            $mining_weapon = mining_weapon_payload($nick_page, $row);
            $mining_ore = mining_ore_member_state($nick_page);
            $mining_member = array_merge([
                'nick' => $nick_page,
                'newpoint' => (float)($row['newpoint'] ?? 0),
                'newpoint_fmt' => wallet_fmt_new($row['newpoint'] ?? 0),
                'tool' => $mining_tool,
                'weapon' => $mining_weapon,
            ], mining_point_payload($row['point'] ?? mining_member_point_refresh($nick_page)), $mining_eunchong, $mining_ore);
            if (!mining_access_allowed($nick_page)) {
                $mining_access_denied = true;
            }
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
<?php
$mining_early = defined('MINING_EARLY_BOOT') && MINING_EARLY_BOOT;
if (!$mining_early) {
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a1210">
    <title>숟가락 채굴 · 웹</title>
    <style>
<?php } else { ?>
    <style>
<?php } ?>

        :root {
            --bg: #0a1210;
            --card: rgba(0,0,0,0.42);
            --line: rgba(110,231,183,0.22);
            --mint: #6ee7b7;
            --gold: #fcd34d;
            --text: #ecfdf5;
            --muted: rgba(236,253,245,0.62);
            --tip-bg: #0f241c;
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
        .durability-wrap {
            width: 100%;
            text-align: left;
        }
        .durability-wrap .mining-section-header {
            margin: 0 0 8px;
        }
        .durability-wrap .mining-section-toggle {
            font-size: 0.68rem;
        }
        .durability-head-right {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            margin-left: auto;
        }
        .durability-head-right strong {
            color: var(--text);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .btn-repair-emoji {
            flex: 0 0 auto;
            width: 1.85rem;
            height: 1.85rem;
            padding: 0;
            border: 1px solid rgba(252,211,77,0.45);
            border-radius: 999px;
            background: rgba(252,211,77,0.14);
            font-size: 0.95rem;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.12s ease, opacity 0.15s ease, background 0.15s ease;
        }
        .btn-repair-emoji:hover:not(:disabled) {
            transform: scale(1.08);
            background: rgba(252,211,77,0.28);
        }
        .btn-repair-emoji:disabled {
            opacity: 0.28;
            cursor: not-allowed;
            transform: none;
        }
        .btn-repair-emoji.repairing {
            opacity: 0.55;
            animation: repairPulse 0.9s ease-in-out infinite;
        }
        @keyframes repairPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .durability-bar {
            height: 10px;
            border-radius: 999px;
            background: rgba(0,0,0,0.45);
            border: 1px solid rgba(110,231,183,0.22);
            overflow: hidden;
        }
        .durability-fill {
            height: 100%;
            width: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #34d399, #6ee7b7);
            box-shadow: 0 0 10px rgba(110,231,183,0.35);
            transition: width 0.35s linear, background 0.25s ease;
        }
        .durability-wrap.warn .durability-fill {
            background: linear-gradient(90deg, #f59e0b, #fcd34d);
            box-shadow: 0 0 10px rgba(252,211,77,0.35);
        }
        .durability-wrap.danger .durability-fill,
        .durability-wrap.broken .durability-fill {
            background: linear-gradient(90deg, #ef4444, #f87171);
            box-shadow: 0 0 10px rgba(248,113,113,0.35);
        }
        .durability-section-body {
            margin-top: 6px;
        }
        .durability-meta {
            margin-top: 5px;
            font-size: 0.68rem;
            color: var(--muted);
            line-height: 1.4;
            min-height: 1.2em;
        }
        .repair-msg {
            margin-top: 5px;
            font-size: 0.72rem;
            min-height: 1.1em;
            color: var(--muted);
            line-height: 1.35;
            text-align: left;
        }
        .repair-msg.ok { color: var(--mint); }
        .repair-msg.fail { color: #f87171; }
        .spoon-visual.tool-broken .spoon-icon {
            animation: none;
            opacity: 0.55;
            filter: grayscale(0.35);
        }
        .mining-pending-row {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 6px 0 4px;
            width: 100%;
            min-height: 2rem;
            padding: 0 2.4rem; /* 우측 수령 버튼 공간 확보 → 숫자 중앙 유지 */
            box-sizing: border-box;
        }
        .mining-pending-amount {
            display: flex;
            justify-content: center;
            align-items: baseline;
            min-width: 0;
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--mint);
            line-height: 1.35rem;
        }
        .mining-pending-num {
            display: inline-block;
            box-sizing: border-box;
            min-width: 14ch;
            max-width: 100%;
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum" 1;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            letter-spacing: 0;
            white-space: nowrap;
        }
        .mining-pending-unit {
            flex: 0 0 auto;
            margin-left: 2px;
            font-weight: 800;
        }
        .btn-claim-emoji {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 2rem;
            height: 2rem;
            padding: 0;
            border: 1px solid rgba(110,231,183,0.4);
            border-radius: 999px;
            background: rgba(110,231,183,0.16);
            font-size: 1.05rem;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.15s ease, background 0.15s ease, transform 0.12s ease;
        }
        .btn-claim-emoji:hover:not(:disabled) {
            transform: translateY(-50%) scale(1.08);
            background: rgba(110,231,183,0.28);
        }
        .btn-claim-emoji:disabled {
            opacity: 0.28;
            cursor: not-allowed;
        }
        .btn-claim-emoji.claiming {
            opacity: 0.55;
            animation: claimPulse 0.9s ease-in-out infinite;
        }
        @keyframes claimPulse {
            0%, 100% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.1); }
        }
        .ore-spawn-countdown {
            display: none;
            margin: 2px 0 4px;
            min-height: 1.2rem;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--gold);
            text-align: center;
            line-height: 1.2rem;
        }
        .ore-spawn-countdown.active {
            display: block;
        }
        .rate-line {
            margin-top: 8px;
            font-size: 0.74rem;
            color: var(--muted);
        }
        .rate-line b { color: var(--mint); font-weight: 700; }
        .rate-weapon-mult {
            color: #fbbf24;
            font-weight: 800;
            margin-left: 2px;
            letter-spacing: 0.02em;
        }
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
            gap: 4px;
            margin-top: 4px;
            margin-bottom: 2px;
            min-height: 1.1em;
        }
        .save-msg {
            min-height: 1.2em;
            text-align: center;
            font-size: 0.72rem;
            color: var(--muted);
            line-height: 1.35;
        }
        .save-msg.ok { color: var(--mint); }
        .save-msg.err { color: #f87171; }
        .upgrade-actions {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 8px;
            margin: 0;
            padding: 0;
        }
        .upgrade-actions .eunchong-btns {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 4px;
            align-self: center;
            max-width: 5.8rem;
        }
        .upgrade-actions .btn-eunchong {
            flex: 0 0 auto;
            align-self: stretch;
            width: 100%;
            max-width: none;
            min-width: 0;
            padding: 8px 6px;
            font-size: 0.58rem;
        }
        .upgrade-actions .btn-eunchong.tier-mega {
            border-color: rgba(125, 211, 252, 0.4);
            background: rgba(56, 189, 248, 0.1);
            color: #7dd3fc;
        }
        .upgrade-actions .btn-eunchong.tier-terra {
            border-color: rgba(251, 146, 60, 0.45);
            background: rgba(251, 146, 60, 0.1);
            color: #fdba74;
        }
        .mega-booster-wrap {
            display: none;
            align-items: center;
            gap: 8px;
            margin: 0 0 8px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid rgba(125, 211, 252, 0.35);
            background: rgba(56, 189, 248, 0.08);
            color: #bae6fd;
            font-size: 0.78rem;
            line-height: 1.35;
            cursor: pointer;
            user-select: none;
        }
        .mega-booster-wrap.show { display: flex; }
        .mega-booster-wrap input {
            width: 16px;
            height: 16px;
            accent-color: #38bdf8;
            flex-shrink: 0;
        }
        .mega-booster-wrap small {
            color: rgba(186, 230, 253, 0.75);
            font-size: 0.72rem;
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
        .upgrade-count-opt:has(input:disabled) {
            opacity: 0.38;
            cursor: not-allowed;
        }
        .btn-upgrade {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: none;
            min-width: 0;
            min-height: 52px;
            padding: 10px 16px;
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
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            line-height: 1.15;
        }
        .btn-upgrade-label {
            font-weight: 800;
            font-size: 1rem;
        }
        .btn-upgrade-cost {
            font-size: 0.72rem;
            font-weight: 700;
            color: rgba(252, 211, 77, 0.88);
            letter-spacing: -0.02em;
            white-space: nowrap;
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
        .upgrade-row-wrap {
            margin-top: 2px;
        }
        .upgrade-row-wrap > .mining-section-header {
            margin-bottom: 0;
        }
        .upgrade-row-wrap > .mining-section-body:not(.collapsed) {
            margin-top: 8px;
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
            max-width: 5.6rem;
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
        /* 내 무기 / 채굴 장비 — 각각 별도 카드 영역 */
        .mining-area-card {
            margin-top: 12px;
        }
        .mining-area-card .mining-section {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }
        .mining-weapon-card {
            border-color: rgba(252, 211, 77, 0.22);
        }
        .mining-equip-card {
            border-color: rgba(110, 231, 183, 0.22);
        }
        /* 무기 결합 — 헤더는 타 섹션과 동일 정렬, 부스트 톤은 안쪽 카드만 */
        .weapon-row-wrap.mining-section {
            margin-top: 0;
            padding: 0;
            border-top: none;
            background: none;
            border-left: none;
            border-right: none;
            border-bottom: none;
            border-radius: 0;
            box-shadow: none;
            filter: none;
            animation: none;
            position: relative;
        }
        .weapon-row-wrap::before { display: none; }
        .weapon-row-wrap .mining-section-header {
            position: relative;
            z-index: 1;
            align-items: center;
            min-height: 28px;
            margin: 0 0 8px;
            gap: 8px;
        }
        .weapon-row-wrap .mining-section-toggle {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
            margin: 0;
            min-height: 28px;
            line-height: 1.2;
            width: auto;
            flex: 1;
            min-width: 0;
        }
        .weapon-row-wrap .mining-section-title {
            flex: 0 1 auto;
            line-height: 1.2;
        }
        .weapon-row-wrap .mining-section-icon {
            flex: 0 0 auto;
            margin-left: 2px;
            line-height: 1;
        }
        .weapon-row-wrap:not(.is-bound) .mining-section-title {
            color: rgba(148, 163, 184, 0.72);
            font-weight: 600;
        }
        .weapon-row-wrap.is-bound .mining-section-title {
            color: #fde68a;
            font-weight: 800;
            letter-spacing: 0.01em;
        }
        .weapon-row-wrap.is-bound .mining-section-icon {
            color: rgba(252, 211, 77, 0.85);
        }
        .weapon-bind-status {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 22px;
            padding: 0 9px;
            border-radius: 999px;
            font-size: 0.66rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            color: rgba(148, 163, 184, 0.75);
            background: rgba(71, 85, 105, 0.22);
            border: 1px solid rgba(100, 116, 139, 0.35);
            transition: color 0.25s ease, background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .weapon-bind-status.on {
            color: #422006;
            background: linear-gradient(135deg, #fde68a, #fbbf24);
            border-color: rgba(251, 191, 36, 0.65);
            box-shadow: 0 0 10px rgba(251, 191, 36, 0.28);
        }
        .weapon-row-wrap .mining-section-body {
            position: relative;
            z-index: 1;
        }
        .weapon-row {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(30, 36, 48, 0.45);
            border: 1px solid rgba(100, 116, 139, 0.28);
            filter: saturate(0.55) brightness(0.92);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease, filter 0.3s ease;
        }
        .weapon-row-wrap.is-bound .weapon-row {
            filter: none;
            background:
                linear-gradient(120deg, rgba(66, 42, 12, 0.5), rgba(20, 36, 32, 0.55));
            border-color: rgba(252, 211, 77, 0.42);
            box-shadow:
                0 0 0 1px rgba(252, 211, 77, 0.1) inset,
                0 6px 18px rgba(217, 119, 6, 0.12);
            animation: weaponBoostPulse 2.8s ease-in-out infinite;
        }
        @keyframes weaponBoostPulse {
            0%, 100% {
                box-shadow:
                    0 0 0 1px rgba(252, 211, 77, 0.08) inset,
                    0 6px 16px rgba(217, 119, 6, 0.1);
                border-color: rgba(252, 211, 77, 0.36);
            }
            50% {
                box-shadow:
                    0 0 0 1px rgba(252, 211, 77, 0.18) inset,
                    0 8px 22px rgba(251, 191, 36, 0.18);
                border-color: rgba(252, 211, 77, 0.52);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .weapon-row-wrap.is-bound .weapon-row { animation: none; }
        }
        .weapon-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 3px;
        }
        .weapon-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: rgba(203, 213, 225, 0.72);
            word-break: break-all;
            line-height: 1.25;
            transition: color 0.25s ease;
        }
        .weapon-row-wrap.is-bound .weapon-name {
            color: #fff8e7;
            text-shadow: 0 0 14px rgba(252, 211, 77, 0.3);
        }
        .weapon-equipped-tag {
            display: inline-block;
            align-self: flex-start;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 999px;
            line-height: 1.35;
            color: var(--gold);
            background: rgba(252,211,77,0.12);
            border: 1px solid rgba(252,211,77,0.28);
        }
        .weapon-equipped-tag.on {
            display: inline-block;
            animation: weaponTagGlow 1.8s ease-in-out infinite;
        }
        @keyframes weaponTagGlow {
            0%, 100% { box-shadow: 0 0 0 rgba(252, 211, 77, 0); }
            50% { box-shadow: 0 0 8px rgba(252, 211, 77, 0.4); }
        }
        @media (prefers-reduced-motion: reduce) {
            .weapon-equipped-tag.on { animation: none; }
        }
        .btn-weapon-equip,
        .btn-weapon-unequip {
            flex: 0 0 auto;
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
            line-height: 1;
            transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }
        .btn-weapon-equip {
            border: 1px solid rgba(148, 163, 184, 0.4);
            background: rgba(71, 85, 105, 0.35);
            color: rgba(226, 232, 240, 0.85);
        }
        .btn-weapon-equip:hover:not(:disabled) {
            border-color: rgba(252, 211, 77, 0.55);
            background: rgba(252, 211, 77, 0.16);
            color: #fde68a;
            box-shadow: 0 0 12px rgba(251, 191, 36, 0.18);
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
            margin-top: 6px;
            font-size: 0.66rem;
            color: rgba(148, 163, 184, 0.5);
            text-align: left;
            line-height: 1.55;
            padding: 8px 10px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.35);
            border: 1px solid rgba(100, 116, 139, 0.22);
            white-space: pre-line;
            transition: color 0.28s ease, background 0.28s ease, border-color 0.28s ease, box-shadow 0.28s ease, filter 0.28s ease, opacity 0.28s ease;
        }
        /* 미결합 — 불 꺼짐 */
        .weapon-row-hint.is-idle {
            color: rgba(100, 116, 139, 0.55);
            background: rgba(15, 23, 42, 0.28);
            border-color: rgba(71, 85, 105, 0.3);
            opacity: 0.72;
            filter: grayscale(0.35) brightness(0.85);
            box-shadow: none;
            font-style: normal;
            font-weight: 500;
        }
        /* 결합 — 불 켜짐 */
        .weapon-row-wrap.is-bound .weapon-row-hint,
        .weapon-row-hint:not(.is-idle) {
            color: #fde68a;
            background: rgba(120, 53, 15, 0.22);
            border-color: rgba(251, 191, 36, 0.42);
            opacity: 1;
            filter: none;
            font-style: normal;
            font-weight: 650;
            box-shadow: 0 0 12px rgba(251, 191, 36, 0.18), inset 0 0 0 1px rgba(253, 230, 138, 0.08);
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.25);
        }
        .weapon-msg {
            min-height: 0;
            margin-top: 4px;
            font-size: 0.7rem;
            text-align: left;
            color: var(--muted);
        }
        .weapon-msg:empty { display: none; }
        .weapon-msg.ok { color: var(--mint); }
        .weapon-msg.err { color: #f87171; }
        .ore-shard-line {
            margin: 0 0 2px;
            font-size: 0.72rem;
            color: var(--gold);
            text-align: center;
            padding: 0 2.4rem;
            transition: color 0.25s ease;
            font-weight: 650;
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
            width: 72px;
            scroll-snap-align: center;
            text-align: center;
            position: relative;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
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
            pointer-events: none;
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
            flex-wrap: wrap;
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
        .tool-node-tip-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid rgba(110,231,183,0.45);
            color: var(--mint);
            font-size: 0.52rem;
            font-weight: 800;
            flex-shrink: 0;
            line-height: 1;
            opacity: 0.9;
        }
        .tool-node[aria-expanded="true"] .tool-node-tip-mark,
        .tool-node.tip-open .tool-node-tip-mark {
            background: rgba(110,231,183,0.18);
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
        .tool-yield-tip {
            display: none;
            margin-top: 10px;
            padding: 10px 11px;
            border-radius: 12px;
            background: var(--tip-bg);
            border: 1px solid rgba(110,231,183,0.35);
            box-shadow: 0 10px 24px rgba(0,0,0,0.35);
        }
        .tool-yield-tip.open { display: block; }
        .tool-yield-tip-head {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--text);
        }
        .tool-yield-tip-head .tip-icon { font-size: 1rem; line-height: 1; }
        .tool-yield-tip-title {
            font-size: 0.68rem;
            color: var(--mint);
            font-weight: 800;
            margin: 8px 0 6px;
        }
        .tool-yield-tip-title:first-of-type { margin-top: 0; }
        .tool-yield-tip-note {
            font-size: 0.62rem;
            color: var(--muted);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .tool-yield-tip-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.7rem;
            line-height: 1.45;
            padding: 3px 0;
            border-top: 1px solid rgba(110,231,183,0.1);
        }
        .tool-yield-tip-row:first-of-type { border-top: 0; }
        .tool-yield-tip-row .t-label { color: var(--muted); white-space: nowrap; }
        .tool-yield-tip-row .t-mult {
            color: var(--text);
            font-variant-numeric: tabular-nums;
            min-width: 2.4em;
            text-align: right;
        }
        .tool-yield-tip-row .t-daily {
            color: var(--mint);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
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
<?php if (!$mining_early) { ?>
</head>
<body>
<div id="miningBoot" aria-live="polite"><div class="spin" aria-hidden="true"></div><p>채굴 여는 중…</p></div>
<style>
#miningBoot{position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0a1210;color:#d8e6df;font:600 14px/1.4 system-ui,-apple-system,sans-serif}
#miningBoot .spin{width:36px;height:36px;border:3px solid rgba(216,230,223,.18);border-top-color:#7dcea0;border-radius:50%;animation:miningBootSpin .7s linear infinite}
#miningBoot p{margin:14px 0 0;opacity:.88}
@keyframes miningBootSpin{to{transform:rotate(360deg)}}
</style>
<?php } ?>
<div class="wrap" id="app">
    <?php if (!empty($mining_off_work)) { ?>
        <h1>🥄 숟가락 채굴</h1>
        <div class="card lock-box">
            <strong><?php echo htmlspecialchars(function_exists('wallet_퇴근_차단_메시지') ? wallet_퇴근_차단_메시지() : '퇴근 중에는 이용할 수 없어요.', ENT_QUOTES, 'UTF-8'); ?></strong>
            <div style="margin-top:10px;">공창에서 <code>.출근 닉네임</code> 후 다시 들어와 주세요.</div>
        </div>
    <?php } elseif ($mining_need_code) { ?>
        <h1>🥄 숟가락 채굴</h1>
        <p class="sub">회원 code로 접속하세요.<br>예) <code>/page/mining.php?code=XXXX</code></p>
    <?php } elseif ($mining_access_denied) { ?>
        <h1>🥄 숟가락 채굴</h1>
        <div class="card lock-box">
            <strong><?php echo htmlspecialchars($mining_access_msg, ENT_QUOTES, 'UTF-8'); ?></strong>
            <div><?php echo htmlspecialchars($mining_member['nick'], ENT_QUOTES, 'UTF-8'); ?> · 보유 게임냥</div>
            <div class="balance-value" style="margin:14px 0;"><?php echo htmlspecialchars($mining_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div>오픈 전까지는 입장할 수 없어요.</div>
        </div>
    <?php } else {
        $tool = $mining_member['tool'];
        $upgrade = $tool['upgrade'] ?? null;
        $tool_roadmap = mining_tool_roadmap_items((int)($tool['level'] ?? 0));
        $tool_yield_tip_map = [];
        if (function_exists('mining_tool_roadmap_yield_table')) {
            foreach (mining_tool_roadmap_yield_table() as $yield_row) {
                $lv = (int)($yield_row['level'] ?? 0);
                $tool_yield_tip_map[$lv] = [
                    'level' => $lv,
                    'icon' => (string)($yield_row['icon'] ?? ''),
                    'label' => (string)($yield_row['label'] ?? ''),
                    'daily_yield_fmt' => (string)($yield_row['daily_yield_fmt'] ?? '0'),
                    'chat_tiers' => is_array($yield_row['chat_tiers'] ?? null) ? $yield_row['chat_tiers'] : [],
                    'cost_discount_tiers' => is_array($yield_row['cost_discount_tiers'] ?? null) ? $yield_row['cost_discount_tiers'] : [],
                ];
            }
        }
        $eunchong_left_sec = (int)($mining_member['eunchong_left_sec'] ?? 0);
        $tool_lv = (int)($tool['level'] ?? 0);
        $eunchong_active = !empty($mining_member['eunchong_active']) && $eunchong_left_sec > 0;
        $eunchong_zeros = $eunchong_active ? max(0, (int)($mining_member['eunchong_zeros'] ?? 1)) : 0;
        $eunchong_cost_discount = $eunchong_active && !empty($mining_member['eunchong_cost_discount']);
        $eunchong_label = (string)($mining_member['eunchong_label'] ?? '은총');
        $eunchong_cnt = (int)($mining_member['eunchong_cnt'] ?? 0);
        $eunchong_btn_label = mining_eunchong_button_label($eunchong_cnt);
        $eunchong_use = mining_eunchong_tier_def(1);
        $eunchong_use_zeros = (int)$eunchong_use['zeros'];
        $eunchong_use_label = (string)$eunchong_use['label'];
        $eunchong_use_cost = (int)$eunchong_use['cost'];
        $eunchong_use_cost_discount = !empty($eunchong_use['cost_discount']);
        $upgrade_pct_now = $eunchong_active
            ? mining_upgrade_success_hint_str($eunchong_zeros, $tool_lv)
            : mining_upgrade_success_hint_str(0, $tool_lv);
        $eunchong_left_fmt = $eunchong_active
            ? sprintf('%d:%02d', intdiv($eunchong_left_sec, 60), $eunchong_left_sec % 60)
            : '';
        $eunchong_buff_from = mining_upgrade_success_hint_str(0, $tool_lv);
        $eunchong_buff_to = mining_upgrade_success_hint_str(max(1, $eunchong_zeros ?: 1), $tool_lv);
        $eunchong_cost_from = !empty($upgrade['cost_base_fmt'])
            ? (string)$upgrade['cost_base_fmt']
            : mining_upgrade_cost_fmt(mining_upgrade_cost_for_level($tool_lv, false));
        $eunchong_cost_to = !empty($upgrade['cost_eunchong_fmt'])
            ? (string)$upgrade['cost_eunchong_fmt']
            : mining_upgrade_cost_fmt(mining_upgrade_cost_for_level($tool_lv, true));
        $chat_upgrade_disc_pct = (int)($upgrade['chat_upgrade_discount_pct'] ?? $tool['chat_upgrade_discount_pct'] ?? 0);
        if ($eunchong_active && $eunchong_cost_discount && !empty($upgrade['cost_eunchong_fmt'])) {
            $upgrade_cost_now_fmt = ($upgrade['cost_base_fmt'] ?? '') . ' → ' . $upgrade['cost_eunchong_fmt'];
        } elseif ($chat_upgrade_disc_pct > 0 && !empty($upgrade['cost_base_raw_fmt']) && !empty($upgrade['cost_base_fmt'])) {
            $upgrade_cost_now_fmt = $upgrade['cost_base_raw_fmt'] . ' → ' . $upgrade['cost_base_fmt'] . ' (−' . $chat_upgrade_disc_pct . '%)';
        } else {
            $upgrade_cost_now_fmt = (string)($upgrade['cost_fmt'] ?? $upgrade['cost_base_fmt'] ?? '');
        }
        if ($upgrade_cost_now_fmt === '' && function_exists('mining_upgrade_cost_fmt')) {
            $upgrade_cost_now_fmt = mining_upgrade_cost_fmt(
                $upgrade['cost'] ?? $upgrade['cost_base'] ?? mining_upgrade_cost_for_level($tool_lv, false)
            );
        }
        $eunchong_buff_cost_txt = $eunchong_cost_discount
            ? ('비용 ' . $eunchong_cost_from . '냥 → ' . $eunchong_cost_to . '냥')
            : '강화비 할인 없음';
        $chat_upgrade_hint = (string)($tool['chat_upgrade_discount_hint'] ?? '');
        if ($chat_upgrade_hint === '' && function_exists('mining_chat_upgrade_discount_payload')) {
            $chat_upgrade_hint = (string)(mining_chat_upgrade_discount_payload($mining_member['nick'] ?? '')['chat_upgrade_discount_hint'] ?? '');
        }
        $eunchong_modal_cost_txt = '분모 1/10 · 강화비 할인 없음';
        $yield_hourly_fmt = (string)($tool['hourly_yield_fmt'] ?? '');
        $yield_daily_fmt = (string)($tool['daily_yield_fmt'] ?? '');
        $yield_time_fmt = (string)($tool['time_to_1pct_fmt'] ?? '');
        if ($yield_hourly_fmt === '' && function_exists('mining_tool_hourly_yield_fmt')) {
            $yield_hourly_fmt = mining_tool_hourly_yield_fmt((int)($tool['level'] ?? 0));
        }
        if ($yield_daily_fmt === '' && function_exists('mining_tool_daily_yield_fmt')) {
            $yield_daily_fmt = mining_tool_daily_yield_fmt((int)($tool['level'] ?? 0));
        }
        if ($yield_time_fmt === '' && function_exists('mining_tool_time_to_base_pct_label')) {
            $yield_time_fmt = mining_tool_time_to_base_pct_label((int)($tool['level'] ?? 0));
        }
        $weapon = $mining_member['weapon'] ?? ['has_weapon' => false, 'label' => '없음', 'equipped' => false];
        $mining_initial_pending_fmt = '0';
        $tool_rate_fmt = (string)($tool['rate_fmt'] ?? '');
        $tool_rate_base_fmt = (string)($tool['rate_base_fmt'] ?? $tool_rate_fmt);
        $tool_weapon_mult = (float)($tool['weapon_yield_mult'] ?? ($weapon['yield_mult'] ?? 1));
        $tool_weapon_mult_fmt = (string)($tool['weapon_yield_mult_fmt'] ?? ($weapon['yield_mult_fmt'] ?? '1'));
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
            if (!empty($sync_init['rate_base_fmt'])) {
                $tool_rate_base_fmt = (string)$sync_init['rate_base_fmt'];
            }
            if (isset($sync_init['weapon_yield_mult'])) {
                $tool_weapon_mult = (float)$sync_init['weapon_yield_mult'];
            }
            if (!empty($sync_init['weapon_yield_mult_fmt'])) {
                $tool_weapon_mult_fmt = (string)$sync_init['weapon_yield_mult_fmt'];
            }
            // 내구 정지 시 초당 표기도 0 (sync.rate=0이면 rate_fmt가 '0')
            if (!empty($sync_init['durability_stopped']) || !empty($sync_init['durability_broken'])) {
                $tool_rate_fmt = '0';
                $tool_rate_base_fmt = '0';
            }
            if (!empty($sync_init['hourly_yield_fmt'])) {
                $yield_hourly_fmt = (string)$sync_init['hourly_yield_fmt'];
            }
            if (!empty($sync_init['daily_yield_fmt'])) {
                $yield_daily_fmt = (string)$sync_init['daily_yield_fmt'];
            }
            if (!empty($sync_init['time_to_1pct_fmt'])) {
                $yield_time_fmt = (string)$sync_init['time_to_1pct_fmt'];
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
                <?php
                $dur_init = is_array($mining_sync_initial['sync'] ?? null) ? $mining_sync_initial['sync'] : [];
                $dur_pct = isset($dur_init['durability_pct']) ? (float)$dur_init['durability_pct'] : 100.0;
                $dur_disp = isset($dur_init['durability_display']) ? (int)$dur_init['durability_display'] : (int)MINING_DURABILITY_MAX;
                $dur_max = isset($dur_init['durability_max']) ? (int)$dur_init['durability_max'] : (int)MINING_DURABILITY_MAX;
                $dur_broken = !empty($dur_init['durability_broken']);
                $dur_stopped = !empty($dur_init['durability_stopped']) || $dur_broken
                    || $dur_pct <= (float)(defined('MINING_DURABILITY_STOP_PCT') ? MINING_DURABILITY_STOP_PCT : 10);
                $dur_stop_pct = isset($dur_init['stop_pct']) ? (int)$dur_init['stop_pct'] : (int)MINING_DURABILITY_STOP_PCT;
                $dur_warn = isset($dur_init['warn_pct']) ? (int)$dur_init['warn_pct'] : (int)MINING_DURABILITY_WARN_PCT;
                $tool_status_text = $dur_stopped
                    ? ('내구도 ' . $dur_stop_pct . '% 이하 · 채굴 정지')
                    : (string)($tool['status'] ?? '');
                ?>
                <div class="spoon-status" id="toolStatus"><?php echo htmlspecialchars($tool_status_text, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php
                $dur_cls = 'mining-section durability-wrap';
                if ($dur_stopped) {
                    $dur_cls .= ' broken';
                } elseif ($dur_pct <= $dur_warn) {
                    $dur_cls .= ' danger';
                } elseif ($dur_pct <= 50) {
                    $dur_cls .= ' warn';
                }
                $dur_repair_fmt = (string)($dur_init['repair_step_cost_fmt'] ?? ($dur_init['repair_full_cost_fmt'] ?? ''));
                $dur_step = isset($dur_init['repair_step_amount']) ? (int)$dur_init['repair_step_amount'] : (int)MINING_DURABILITY_REPAIR_STEP;
                $dur_meta = $dur_stopped
                    ? ('내구도 ' . $dur_stop_pct . '% 이하 · 수리 후 채굴 재개')
                    : ('+' . $dur_step . ' 수리 ' . ($dur_repair_fmt !== '' ? $dur_repair_fmt . '본냥' : '—'));
?>
                <div class="mining-pending-row">
                    <div class="mining-pending-amount" id="miningPendingAmount" aria-live="polite"><span class="mining-pending-num" id="miningPendingNum"><?php echo htmlspecialchars($mining_initial_pending_fmt, ENT_QUOTES, 'UTF-8'); ?></span><span class="mining-pending-unit">냥</span></div>
                    <button type="button" class="btn-claim-emoji" id="btnClaim" title="수령하기" aria-label="수령하기" disabled>💰</button>
                </div>
                <div class="ore-shard-line" id="oreShardLine">✨ 은총조각 <?php echo (int)($mining_member['eunchong_shard'] ?? 0); ?>/<?php echo (int)MINING_ORE_EUNCHONG_SHARDS; ?></div>
                <div class="ore-spawn-countdown" id="oreSpawnCountdown" aria-live="polite"></div>
                <?php
                    $show_weapon_rate_mult = !empty($weapon['equipped'])
                        && $tool_rate_base_fmt !== '0'
                        && $tool_weapon_mult > 1.0001;
                    $rate_display_fmt = $show_weapon_rate_mult ? $tool_rate_base_fmt : $tool_rate_fmt;
                ?>
                <div class="rate-line" data-mining-sep="v8">초당 <b id="rateValue">+<?php echo htmlspecialchars($rate_display_fmt, ENT_QUOTES, 'UTF-8'); ?></b> 냥<span class="rate-weapon-mult" id="rateWeaponMult"<?php echo $show_weapon_rate_mult ? '' : ' hidden'; ?>> ×<?php echo htmlspecialchars($tool_weapon_mult_fmt, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="rate-line" id="chatYieldHint" style="opacity:0.85;font-size:0.82rem;"><?php
                    $chat_hint = (string)($tool['chat_yield_hint'] ?? '');
                    if ($chat_hint === '' && function_exists('mining_chat_yield_payload')) {
                        $chat_hint = (string)(mining_chat_yield_payload($mining_member['nick'] ?? '')['chat_yield_hint'] ?? '');
                    }
                    echo htmlspecialchars($chat_hint !== '' ? $chat_hint : '오늘 생타 기준 채굴 배율 적용', ENT_QUOTES, 'UTF-8');
                ?></div>
                <?php if (empty($tool['yield_unlocked'])) { ?>
                <div class="yield-lock-hint" id="yieldLockHint"><?php echo htmlspecialchars((string)($tool['unlock_hint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } else { ?>
                <div class="yield-lock-hint" id="yieldLockHint" style="display:none;"></div>
                <?php } ?>
                <div class="rate-yield-slide" id="rateYieldSlide" aria-live="polite">
                    <div class="rate-yield-track" id="rateYieldTrack">
                        <div class="rate-yield-item">1시간 약 <b id="hourlyYieldValue"><?php echo htmlspecialchars($yield_hourly_fmt !== '' ? $yield_hourly_fmt : '0', ENT_QUOTES, 'UTF-8'); ?></b>냥 (오프라인 포함)</div>
                        <div class="rate-yield-item">24시간 약 <b id="dailyYieldValue"><?php echo htmlspecialchars($yield_daily_fmt !== '' ? $yield_daily_fmt : '0', ENT_QUOTES, 'UTF-8'); ?></b>냥 (오프라인 포함)</div>
                        <div class="rate-yield-item">본방냥 0.3% 소요 <b id="timeTo1pctValue"><?php echo htmlspecialchars($yield_time_fmt !== '' ? $yield_time_fmt : '—', ENT_QUOTES, 'UTF-8'); ?></b></div>
                    </div>
                </div>
                <div class="save-row" id="saveRow">
                    <div class="save-msg" id="saveMsg"></div>
                </div>
            </div>

            <?php if (!empty($tool['can_upgrade']) && is_array($upgrade)) { ?>
            <div class="mining-section upgrade-row-wrap" id="upgradeRow">
                <div class="mining-section-header">
                    <button type="button" class="mining-section-toggle" id="btnUpgradeToggle" aria-expanded="true" aria-controls="upgradeSectionBody">
                        <span class="mining-section-title">장비 강화</span>
                        <span class="mining-section-icon" id="upgradeToggleIcon">▾</span>
                    </button>
                </div>
                <div class="mining-section-body" id="upgradeSectionBody">
                <div class="upgrade-row">
                <div class="eunchong-buff<?php echo $eunchong_active ? ' active' : ''; ?>" id="eunchongBuff">
                    <span id="eunchongBuffLabel"><?php echo htmlspecialchars($eunchong_label, ENT_QUOTES, 'UTF-8'); ?></span> 버프 · 확률 <?php echo htmlspecialchars($eunchong_buff_from, ENT_QUOTES, 'UTF-8'); ?> → <span id="eunchongBuffTo"><?php echo htmlspecialchars($eunchong_buff_to, ENT_QUOTES, 'UTF-8'); ?></span>
                    · <span id="eunchongBuffCost"><?php echo htmlspecialchars($eunchong_buff_cost_txt, ENT_QUOTES, 'UTF-8'); ?></span>
                    · 채굴량 x<?php echo (int)MINING_EUNCHONG_YIELD_MULT; ?>
                    · <span id="eunchongBuffLeft"><?php echo htmlspecialchars($eunchong_left_fmt, ENT_QUOTES, 'UTF-8'); ?></span> 남음
                </div>
                <div class="upgrade-status">
                    <div class="upgrade-promo-notice" id="upgradePromoNotice"></div>
                    <div class="upgrade-hint" id="upgradeHint">
                        <?php echo ($upgrade['to_icon'] ?? '') . htmlspecialchars($upgrade['to_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?> · 성공 확률 <span id="upgradePct"><?php echo htmlspecialchars($upgrade_pct_now, ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span style="opacity:0.8;font-size:0.78rem;">강화비: 시총 완만화(무기보다 약간 높음) · 50% 소멸 · 25% 금고 · 25% 로또</span>
                    </div>
                    <div class="upgrade-hint" id="chatUpgradeDiscountHint" style="opacity:0.85;font-size:0.78rem;margin-top:4px;"><?php echo htmlspecialchars($chat_upgrade_hint, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="upgrade-msg" id="upgradeMsg"></div>
                </div>
                <div class="upgrade-count-picker" id="upgradeCountPicker">
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="1" checked>1</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="100">100</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="500">500</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="1000">1000(10냥)</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="3000">3000(30냥)</label>
                    <label class="upgrade-count-opt"><input type="radio" name="upgradeTimes" value="5000">5000(50냥)</label>
                </div>
                <div class="upgrade-actions">
                    <div class="eunchong-btns" id="eunchongBtns">
                        <button type="button" class="btn-eunchong" id="btnUseEunchong" data-tier="1"<?php if ($eunchong_cnt < 1) { ?> style="display:none;"<?php } ?>><?php echo htmlspecialchars($eunchong_btn_label, ENT_QUOTES, 'UTF-8'); ?></button>
                        <button type="button" class="btn-eunchong tier-mega" id="btnUseMegaEunchong" data-tier="2"<?php if ($eunchong_cnt < (int)MINING_EUNCHONG_MEGA_COST) { ?> style="display:none;"<?php } ?>>메가은총</button>
                        <button type="button" class="btn-eunchong tier-terra" id="btnUseTerraEunchong" data-tier="3" style="display:none;" hidden aria-hidden="true">테라은총</button>
                    </div>
                    <div class="upgrade-btn-col" style="flex:1;min-width:0;display:flex;flex-direction:column;">
                        <label class="mega-booster-wrap<?php echo ($eunchong_active && (int)($mining_member['eunchong_tier'] ?? 0) === 2) ? ' show' : ''; ?>" id="megaBoosterWrap">
                            <input type="checkbox" id="megaBoosterChk">
                            <span>부스터 <small>강화비 ×3 추가 · 0 하나 더 (총 3개)</small></span>
                        </label>
                        <div class="upgrade-btn-field layout-<?php echo htmlspecialchars($mining_upgrade_btn_layout, ENT_QUOTES, 'UTF-8'); ?>" id="upgradeBtnField">
                            <button type="button" class="btn-upgrade" id="btnUpgrade">
                                <span class="btn-upgrade-label">강화하기</span>
                                <span class="btn-upgrade-cost" id="upgradeCostValue"><?php echo htmlspecialchars($upgrade_cost_now_fmt !== '' ? $upgrade_cost_now_fmt : '—', ENT_QUOTES, 'UTF-8'); ?>냥</span>
                            </button>
                        </div>
                    </div>
                </div>
                </div>
                </div>
            </div>
            <?php } ?>

            <div class="<?php echo htmlspecialchars($dur_cls, ENT_QUOTES, 'UTF-8'); ?>" id="durabilityWrap">
                <div class="mining-section-header">
                    <button type="button" class="mining-section-toggle" id="btnDurabilityToggle" aria-expanded="false" aria-controls="durabilitySectionBody">
                        <span class="mining-section-title">장비 내구도</span>
                        <span class="durability-head-right">
                            <strong id="durabilityText"><?php echo (int)$dur_disp; ?> / <?php echo (int)$dur_max; ?></strong>
                            <span class="mining-section-icon" id="durabilityToggleIcon">▸</span>
                        </span>
                    </button>
                    <button type="button" class="btn-repair-emoji" id="btnRepair" title="수리하기 (+<?php echo (int)MINING_DURABILITY_REPAIR_STEP; ?>)" aria-label="수리하기"<?php echo ($dur_disp >= $dur_max) ? ' disabled' : ''; ?>>🔧</button>
                </div>
                <div class="durability-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)round($dur_pct); ?>" id="durabilityBar">
                    <div class="durability-fill" id="durabilityFill" style="width:<?php echo htmlspecialchars((string)max(0, min(100, $dur_pct)), ENT_QUOTES, 'UTF-8'); ?>%;"></div>
                </div>
                <div class="repair-msg" id="repairMsg"></div>
                <div class="mining-section-body durability-section-body collapsed" id="durabilitySectionBody">
                    <div class="durability-meta" id="durabilityMeta"><?php echo htmlspecialchars($dur_meta, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

        </div>

        <div class="card mining-area-card mining-weapon-card">
            <div class="mining-section weapon-row-wrap<?php echo !empty($weapon['equipped']) ? ' is-bound' : ''; ?>" id="weaponSection">
                <div class="mining-section-header">
                    <button type="button" class="mining-section-toggle" id="btnWeaponToggle" aria-expanded="true" aria-controls="weaponSectionBody">
                        <span class="mining-section-title">내 무기</span>
                        <span class="mining-section-icon" id="weaponToggleIcon">▾</span>
                    </button>
                    <span class="weapon-bind-status<?php echo !empty($weapon['equipped']) ? ' on' : ''; ?>" id="weaponBindStatus"><?php echo !empty($weapon['equipped']) ? '결합' : '해제'; ?></span>
                </div>
                <div class="mining-section-body" id="weaponSectionBody">
                <div class="weapon-row">
                    <div class="weapon-info">
                        <span class="weapon-name" id="weaponLabel"><?php echo htmlspecialchars($weapon['label'] ?? '없음', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="weapon-equipped-tag<?php echo !empty($weapon['equipped']) ? ' on' : ''; ?>" id="weaponEquippedTag"<?php if (empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>>부스트 ON</span>
                    </div>
                    <button type="button" class="btn-weapon-equip" id="btnWeaponEquip"<?php if (empty($weapon['has_weapon']) || !empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>>결합</button>
                    <button type="button" class="btn-weapon-unequip" id="btnWeaponUnequip"<?php if (empty($weapon['equipped'])) { ?> style="display:none;"<?php } ?>>해제</button>
                </div>
                <div class="weapon-row-hint<?php echo empty($weapon['equipped']) ? ' is-idle' : ''; ?>" id="weaponRowHint"><?php
                    $tool_lv_hint = (int)($tool['level'] ?? 0);
                    $hint_lines = function_exists('mining_weapon_bind_perk_lines')
                        ? mining_weapon_bind_perk_lines($weapon, $tool_lv_hint)
                        : ['부스트 가동', '광물 출현', '시간당 추가광물', '고급 광물 확률↑'];
                    echo htmlspecialchars(implode("\n", $hint_lines), ENT_QUOTES, 'UTF-8');
                ?></div>
                <div class="weapon-msg" id="weaponMsg"></div>
                </div>
            </div>
        </div>

        <div class="card mining-area-card mining-equip-card">
            <div class="mining-section tool-roadmap-wrap" id="roadmapSection">
                <button type="button" class="mining-section-toggle" id="btnRoadmapToggle" aria-expanded="true" aria-controls="roadmapSectionBody">
                    <span class="mining-section-title">채굴 장비 탭</span>
                    <span class="mining-section-icon" id="roadmapToggleIcon">▾</span>
                </button>
                <div class="mining-section-body" id="roadmapSectionBody">
                <div class="tool-roadmap-label">주방 → 채굴기 · 생타별 하루 예상·강화비</div>
                <div class="tool-roadmap" id="toolRoadmap">
                    <?php foreach ($tool_roadmap as $node) {
                        $node_lv = (int)$node['level'];
                        $tip_id = 'toolYieldTip';
                    ?>
                    <button type="button"
                        class="tool-node <?php echo htmlspecialchars($node['state'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-level="<?php echo $node_lv; ?>"
                        aria-expanded="false"
                        aria-controls="<?php echo htmlspecialchars($tip_id, ENT_QUOTES, 'UTF-8'); ?>"
                        title="생타별 하루 예상·강화비">
                        <span class="tool-node-icon"><?php echo $node['icon']; ?></span>
                        <span class="tool-node-name">
                            <span class="tool-node-lv">Lv<?php echo $node_lv; ?></span>
                            <span class="tool-node-label"><?php echo htmlspecialchars($node['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="tool-node-tip-mark" aria-hidden="true">?</span>
                        </span>
                    </button>
                    <?php } ?>
                </div>
                <div class="tool-yield-tip" id="toolYieldTip" role="tooltip" hidden></div>
                </div>
            </div>
        </div>

        <div class="nav-row">
            <a class="btn-link" href="/page/mining_roadmap.php<?php echo htmlspecialchars($mining_guide_q, ENT_QUOTES, 'UTF-8'); ?>">장비별 24h 획득량</a>
            <a class="btn-link" href="/page/mining_weapon_bonus.php<?php echo htmlspecialchars($mining_guide_q, ENT_QUOTES, 'UTF-8'); ?>">무기 결합 광물표</a>
        </div>
    <?php } ?>
</div>

<?php if (!$mining_need_code && !$mining_access_denied) { ?>
<div class="modal-overlay" id="eunchongModal" onclick="if(event.target===this)window.miningCloseEunchongModal&&miningCloseEunchongModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <h3 id="eunchongModalTitle">✨ <?php echo htmlspecialchars($eunchong_use_label !== '' ? $eunchong_use_label : '은총', ENT_QUOTES, 'UTF-8'); ?> 사용</h3>
        <p id="eunchongModalBody"><?php
            $modal_label = $eunchong_use_label !== '' ? $eunchong_use_label : '은총';
            $modal_cost = max(1, $eunchong_use_cost);
            $modal_mins = ((int)($eunchong_use_tier ?? 1) >= 2) ? 10 : 5;
            echo htmlspecialchars($modal_label . '을(를) 사용하시겠습니까?', ENT_QUOTES, 'UTF-8');
        ?><br>은총 <?php echo (int)$modal_cost; ?>개 소모 · <?php echo (int)$modal_mins; ?>분간 강화 성공 확률·채굴량이 상승합니다.<br><?php echo htmlspecialchars($eunchong_modal_cost_txt, ENT_QUOTES, 'UTF-8'); ?> · 채굴량 x<?php echo (int)MINING_EUNCHONG_YIELD_MULT; ?></p>
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
    if (!isset($dur_init) || !is_array($dur_init)) {
        $dur_init = is_array($mining_sync_initial['sync'] ?? null) ? $mining_sync_initial['sync'] : [];
    }
?>
<script>
(function() {
    var TOOL = <?php echo json_encode($mining_member['tool'], JSON_UNESCAPED_UNICODE); ?>;
    var WEAPON = <?php echo json_encode($weapon, JSON_UNESCAPED_UNICODE); ?>;
    var TOOL_YIELD_TIPS = <?php echo json_encode(isset($tool_yield_tip_map) ? $tool_yield_tip_map : new stdClass(), JSON_UNESCAPED_UNICODE); ?>;
    var ORE_FINDS = <?php echo json_encode($mining_member['ore_finds'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
    var ORE_SHARD = <?php echo (int)($mining_member['eunchong_shard'] ?? 0); ?>;
    var ORE_SHARD_NEED = <?php echo (int)MINING_ORE_EUNCHONG_SHARDS; ?>;
    var ORE_DISCOVER_HINT = <?php echo json_encode($mining_member['ore_discover_hint'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
    var RATE = Number(TOOL.rate) || <?php echo json_encode((float)MINING_RATE_SPOON, JSON_UNESCAPED_UNICODE); ?>;
    // 페이지 세션 동안 초당 채굴 속도 고정 (강화/시세 sync로 수치가 튀지 않게)
    var FROZEN_RATE = RATE > 0 ? RATE : null;
    var FROZEN_RATE_FMT = (TOOL && TOOL.rate_fmt) ? String(TOOL.rate_fmt) : (FROZEN_RATE !== null ? String(FROZEN_RATE) : null);
    var FROZEN_RATE_BASE_FMT = (TOOL && TOOL.rate_base_fmt) ? String(TOOL.rate_base_fmt) : FROZEN_RATE_FMT;
    var FROZEN_WEAPON_MULT_FMT = (TOOL && TOOL.weapon_yield_mult_fmt)
        ? String(TOOL.weapon_yield_mult_fmt)
        : ((WEAPON && WEAPON.yield_mult_fmt) ? String(WEAPON.yield_mult_fmt) : '1');
    var HOURLY_YIELD_FMT = (TOOL && TOOL.hourly_yield_fmt) ? String(TOOL.hourly_yield_fmt) : '0';
    var DAILY_YIELD_FMT = (TOOL && TOOL.daily_yield_fmt) ? String(TOOL.daily_yield_fmt) : '0';
    var TIME_TO_1PCT_FMT = (TOOL && TOOL.time_to_1pct_fmt) ? String(TOOL.time_to_1pct_fmt) : '—';
    var SAVE_MIN = <?php echo json_encode((float)MINING_SAVE_MIN, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_COST = <?php
        $_uc = '0';
        if (!empty($upgrade['cost_base'])) {
            $_uc = (string)$upgrade['cost_base'];
        } elseif (function_exists('mining_upgrade_cost_for_level')) {
            $_uc = (string)mining_upgrade_cost_for_level((int)($tool['level'] ?? 0), false);
        }
        echo json_encode($_uc, JSON_UNESCAPED_UNICODE);
    ?>;
    var UPGRADE_PCT = <?php echo json_encode(MINING_UPGRADE_SUCCESS_PCT, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_PCT_EUNCHONG = <?php echo json_encode(MINING_UPGRADE_EUNCHONG_PCT, JSON_UNESCAPED_UNICODE); ?>;
    var memberEunchong = <?php echo (int)($mining_member['eunchong_cnt'] ?? 0); ?>;
    var memberEunchongActive = <?php echo !empty($mining_member['eunchong_active']) ? 'true' : 'false'; ?>;
    var memberEunchongLeftSec = <?php echo (int)($mining_member['eunchong_left_sec'] ?? 0); ?>;
    var memberEunchongZeros = <?php echo (int)($mining_member['eunchong_zeros'] ?? 0); ?>;
    var memberEunchongCostDiscount = <?php echo !empty($mining_member['eunchong_cost_discount']) ? 'true' : 'false'; ?>;
    var memberEunchongLabel = <?php echo json_encode((string)($mining_member['eunchong_label'] ?? '은총'), JSON_UNESCAPED_UNICODE); ?>;
    var memberEunchongTier = <?php echo (int)($mining_member['eunchong_tier'] ?? 1); ?>;
    var memberEunchongBtnLabel = <?php echo json_encode((string)($mining_member['eunchong_btn_label'] ?? mining_eunchong_button_label((int)($mining_member['eunchong_cnt'] ?? 0))), JSON_UNESCAPED_UNICODE); ?>;
    var memberEunchongUseTier = <?php echo (int)($mining_member['eunchong_use_tier'] ?? 0); ?>;
    var memberEunchongUseLabel = <?php echo json_encode((string)($mining_member['eunchong_use_label'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
    var memberEunchongUseCost = <?php echo (int)($mining_member['eunchong_use_cost'] ?? 0); ?>;
    var memberEunchongUseZeros = <?php echo (int)($mining_member['eunchong_use_zeros'] ?? 0); ?>;
    var memberEunchongUseCostDiscount = <?php echo !empty($mining_member['eunchong_use_cost_discount']) ? 'true' : 'false'; ?>;
    var EUNCHONG_YIELD_MULT = <?php echo (int)MINING_EUNCHONG_YIELD_MULT; ?>;
    var EUNCHONG_MEGA_COST = <?php echo (int)MINING_EUNCHONG_MEGA_COST; ?>;
    var EUNCHONG_TERRA_COST = <?php echo (int)MINING_EUNCHONG_TERRA_COST; ?>;
    var PING_SEC = <?php echo json_encode((int)MINING_SYNC_PING_SEC, JSON_UNESCAPED_UNICODE); ?>;
    var POLL_SEC = <?php echo json_encode((int)MINING_SYNC_POLL_SEC, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BATCH_1000_COST = <?php echo json_encode((int)MINING_UPGRADE_BATCH_1000_COST, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BATCH_3000_COST = <?php echo json_encode((int)MINING_UPGRADE_BATCH_3000_COST, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BATCH_5000_COST = <?php echo json_encode((int)MINING_UPGRADE_BATCH_5000_COST, JSON_UNESCAPED_UNICODE); ?>;
    function upgradeBatchNewpointCost(times) {
        times = parseInt(times, 10) || 0;
        if (times === 1000) return UPGRADE_BATCH_1000_COST;
        if (times === 3000) return UPGRADE_BATCH_3000_COST;
        if (times === 5000) return UPGRADE_BATCH_5000_COST;
        return 0;
    }
    var UPGRADE_BTN_MOVE_EVERY = <?php echo json_encode((int)MINING_UPGRADE_BTN_MOVE_EVERY, JSON_UNESCAPED_UNICODE); ?>;
    var UPGRADE_BTN_LAYOUT = <?php echo json_encode($mining_upgrade_btn_layout, JSON_UNESCAPED_UNICODE); ?>;
    var CODE = <?php echo json_encode($mining_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($mining_api, JSON_UNESCAPED_UNICODE); ?>;
    var INITIAL_SYNC = <?php echo json_encode($mining_sync_initial['sync'] ?? null, JSON_UNESCAPED_UNICODE); ?>;
    var TOKEN_KEY = 'mining_lease_v2_' + CODE;
    var ORE_COUNTDOWN_SEC = 10;
    var oreNextDeadlineMs = null;
    var miningPendingAmount = document.getElementById('miningPendingAmount');
    var miningPendingNum = document.getElementById('miningPendingNum');
    var oreSpawnCountdown = document.getElementById('oreSpawnCountdown');
    var btnClaim = document.getElementById('btnClaim');
    var saveRow = document.getElementById('saveRow');
    var btnUpgrade = document.getElementById('btnUpgrade');
    var btnRepair = document.getElementById('btnRepair');
    var repairMsg = document.getElementById('repairMsg');
    var durabilityWrap = document.getElementById('durabilityWrap');
    var btnDurabilityToggle = document.getElementById('btnDurabilityToggle');
    var durabilitySectionBody = document.getElementById('durabilitySectionBody');
    var durabilityToggleIcon = document.getElementById('durabilityToggleIcon');
    var durabilityFill = document.getElementById('durabilityFill');
    var durabilityText = document.getElementById('durabilityText');
    var durabilityMeta = document.getElementById('durabilityMeta');
    var durabilityBar = document.getElementById('durabilityBar');
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
    var rateWeaponMult = document.getElementById('rateWeaponMult');
    var hourlyYieldValue = document.getElementById('hourlyYieldValue');
    var dailyYieldValue = document.getElementById('dailyYieldValue');
    var timeTo1pctValue = document.getElementById('timeTo1pctValue');
    var rateYieldTrack = document.getElementById('rateYieldTrack');
    var rateYieldSlide = document.getElementById('rateYieldSlide');
    var YIELD_SLIDE_COUNT = 3;
    var pageTitle = document.getElementById('pageTitle');
    var DUR_MAX = <?php echo (int)($dur_init['durability_max'] ?? (function_exists('mining_durability_max') ? mining_durability_max((int)($tool['level'] ?? 0)) : 120)); ?>;
    var DUR_WARN = <?php echo (int)MINING_DURABILITY_WARN_PCT; ?>;
    var DUR_STOP = <?php echo (int)MINING_DURABILITY_STOP_PCT; ?>;
    var DUR_FLOOR = DUR_MAX * (DUR_STOP / 100);
    var durBase = <?php echo json_encode((float)($dur_init['durability'] ?? MINING_DURABILITY_MAX), JSON_UNESCAPED_UNICODE); ?>;
    var wearPerSec = <?php echo json_encode((float)($dur_init['wear_per_sec'] ?? 0), JSON_UNESCAPED_UNICODE); ?>;
    var repairStepCostFmt = <?php echo json_encode((string)($dur_init['repair_step_cost_fmt'] ?? ($dur_init['repair_full_cost_fmt'] ?? '')), JSON_UNESCAPED_UNICODE); ?>;
    var repairStep = <?php echo (int)MINING_DURABILITY_REPAIR_STEP; ?>;
    var repairing = false;
    var upgradeRow = document.getElementById('upgradeRow');
    var btnUpgradeToggle = document.getElementById('btnUpgradeToggle');
    var upgradeSectionBody = document.getElementById('upgradeSectionBody');
    var upgradeToggleIcon = document.getElementById('upgradeToggleIcon');
    var btnWeaponToggle = document.getElementById('btnWeaponToggle');
    var weaponSectionBody = document.getElementById('weaponSectionBody');
    var weaponToggleIcon = document.getElementById('weaponToggleIcon');
    var btnRoadmapToggle = document.getElementById('btnRoadmapToggle');
    var roadmapSectionBody = document.getElementById('roadmapSectionBody');
    var roadmapToggleIcon = document.getElementById('roadmapToggleIcon');
    var toolRoadmap = document.getElementById('toolRoadmap');
    var toolYieldTip = document.getElementById('toolYieldTip');
    var btnUseEunchong = document.getElementById('btnUseEunchong');
    var btnUseMegaEunchong = document.getElementById('btnUseMegaEunchong');
    var btnUseTerraEunchong = document.getElementById('btnUseTerraEunchong');
    var megaBoosterWrap = document.getElementById('megaBoosterWrap');
    var megaBoosterChk = document.getElementById('megaBoosterChk');
    var eunchongBuff = document.getElementById('eunchongBuff');
    var eunchongBuffLeft = document.getElementById('eunchongBuffLeft');
    var eunchongBuffLabel = document.getElementById('eunchongBuffLabel');
    var eunchongBuffTo = document.getElementById('eunchongBuffTo');
    var eunchongBuffCost = document.getElementById('eunchongBuffCost');
    var eunchongModal = document.getElementById('eunchongModal');
    var eunchongModalTitle = document.getElementById('eunchongModalTitle');
    var eunchongModalBody = document.getElementById('eunchongModalBody');
    var btnEunchongCancel = document.getElementById('btnEunchongCancel');
    var btnEunchongConfirm = document.getElementById('btnEunchongConfirm');
    var pendingEunchongTier = 1;
    var upgradePct = document.getElementById('upgradePct');
    var upgradeHint = document.getElementById('upgradeHint');
    var upgradeCostValue = document.getElementById('upgradeCostValue');
    var weaponLabel = document.getElementById('weaponLabel');
    var weaponEquippedTag = document.getElementById('weaponEquippedTag');
    var weaponBindStatus = document.getElementById('weaponBindStatus');
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
    var pendingBase = 0; // 서버 동기화용(내구·lease) — 화면 채굴량과 분리
    var syncAtSec = Math.floor(Date.now() / 1000);
    var pendingSyncedOnce = false;
    // 화면 채굴량 전용 시계 — 강화/sync/내구가 절대 리셋하지 않음 (수령·최초진입만 재설정)
    var displayAmount0 = 0;
    var displayClock0 = Date.now() / 1000;
    var displayRate = (FROZEN_RATE !== null ? FROZEN_RATE : (RATE > 0 ? RATE : 0));
    var displaySealed = false;
    var miningStoppedByDur = false;
    var leaseActiveElsewhere = false;
    var claiming = false;
    var upgrading = false;
    var memberPoint = <?php echo json_encode((string)($mining_member['point'] ?? '0'), JSON_UNESCAPED_UNICODE); ?>;
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

    function fmtEta(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        if (sec <= 0) return '';
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        if (h > 0) return h + '시간' + (m > 0 ? ' ' + m + '분' : '');
        if (m > 0) return m + '분';
        return sec + '초';
    }

    function currentDurability() {
        var elapsed = Math.max(0, (Date.now() / 1000) - syncAtSec);
        if (durBase <= DUR_FLOOR + 1e-9) return Math.max(0, durBase);
        if (wearPerSec <= 0) return Math.max(DUR_FLOOR, durBase);
        return Math.max(DUR_FLOOR, durBase - elapsed * wearPerSec);
    }

    function currentAmount() {
        // 화면 표시 전용: 순수 로컬 시계. 강화·sync·내구와 무관.
        if (displayRate <= 0) return Math.max(0, displayAmount0);
        var elapsed = Math.max(0, (Date.now() / 1000) - displayClock0);
        return Math.max(0, displayAmount0 + elapsed * displayRate);
    }

    function syncWeaponRateMeta(source) {
        source = source || {};
        if (source.rate_base_fmt) FROZEN_RATE_BASE_FMT = String(source.rate_base_fmt);
        if (source.rate_fmt) FROZEN_RATE_FMT = String(source.rate_fmt);
        if (source.weapon_yield_mult_fmt) {
            FROZEN_WEAPON_MULT_FMT = String(source.weapon_yield_mult_fmt);
        } else if (WEAPON && WEAPON.yield_mult_fmt) {
            FROZEN_WEAPON_MULT_FMT = String(WEAPON.yield_mult_fmt);
        }
        if (typeof source.weapon_yield_mult === 'number' && TOOL) {
            TOOL.weapon_yield_mult = Number(source.weapon_yield_mult);
        }
    }

    function paintRateLine(finalRate, finalFmt) {
        var stopped = !(Number(finalRate) > 0);
        var wMult = 1;
        if (TOOL && typeof TOOL.weapon_yield_mult === 'number') {
            wMult = Number(TOOL.weapon_yield_mult) || 1;
        } else if (WEAPON && typeof WEAPON.yield_mult === 'number') {
            wMult = Number(WEAPON.yield_mult) || 1;
        }
        var showMult = !stopped && WEAPON && WEAPON.equipped && wMult > 1.0001;
        var shownFmt = stopped
            ? '0'
            : (showMult
                ? (FROZEN_RATE_BASE_FMT || finalFmt || FROZEN_RATE_FMT || '0')
                : (finalFmt || FROZEN_RATE_FMT || String(finalRate || 0)));
        if (rateValue) rateValue.textContent = '+' + shownFmt;
        if (rateWeaponMult) {
            if (showMult) {
                rateWeaponMult.hidden = false;
                rateWeaponMult.textContent = ' ×' + (FROZEN_WEAPON_MULT_FMT || String(wMult));
            } else {
                rateWeaponMult.hidden = true;
            }
        }
    }

    function sealDisplayAmount(amount, rate, rateFmt) {
        displayAmount0 = Math.max(0, Number(amount) || 0);
        displayClock0 = Date.now() / 1000;
        if (typeof rate === 'number') {
            if (rate > 0) {
                displayRate = rate;
                FROZEN_RATE = rate;
                if (rateFmt) FROZEN_RATE_FMT = String(rateFmt);
                RATE = displayRate;
                paintRateLine(displayRate, FROZEN_RATE_FMT);
            } else {
                // 0이면 화면 적립만 정지 (FROZEN_RATE는 수리 후 재개용으로 유지)
                displayRate = 0;
                RATE = 0;
                paintRateLine(0, '0');
            }
        }
        displaySealed = true;
        updateMiningPendingDisplay();
    }

    function freezeMiningForDurability() {
        var amt = currentAmount();
        displayAmount0 = Math.max(0, amt);
        displayClock0 = Date.now() / 1000;
        displayRate = 0;
        RATE = 0;
        displaySealed = true;
        updateMiningPendingDisplay();
        paintRateLine(0, '0');
        setMiningStatusBase('내구도 ' + DUR_STOP + '% 이하 · 채굴 정지');
    }

    function resumeMiningAfterDurability() {
        var resume = (FROZEN_RATE !== null && Number(FROZEN_RATE) > 0) ? Number(FROZEN_RATE) : 0;
        if (resume > 0) {
            sealDisplayAmount(currentAmount(), resume, FROZEN_RATE_FMT);
        }
        var label = (TOOL && TOOL.status)
            ? String(TOOL.status)
            : ((TOOL && TOOL.label) ? (TOOL.label + '으로 채굴 중') : '채굴 중');
        setMiningStatusBase(label);
    }

    function setRepairMsg(text, kind) {
        if (!repairMsg) return;
        repairMsg.textContent = text || '';
        repairMsg.className = 'repair-msg' + (kind ? (' ' + kind) : '');
    }

    function applyDurabilityUi() {
        var dur = currentDurability();
        var max = DUR_MAX > 0 ? DUR_MAX : 100;
        var pct = Math.max(0, Math.min(100, (dur / max) * 100));
        var disp = dur <= 1e-9 ? 0 : Math.max(1, Math.floor(dur + 1e-9));
        if (dur > 0 && disp > max) disp = max;
        if (durabilityFill) durabilityFill.style.width = pct + '%';
        if (durabilityText) durabilityText.textContent = disp + ' / ' + max;
        if (durabilityBar) durabilityBar.setAttribute('aria-valuenow', String(Math.round(pct)));
        var stopped = pct <= DUR_STOP + 1e-9;
        if (durabilityWrap) {
            durabilityWrap.classList.remove('warn', 'danger', 'broken');
            if (stopped) durabilityWrap.classList.add('broken');
            else if (pct <= DUR_WARN) durabilityWrap.classList.add('danger');
            else if (pct <= 50) durabilityWrap.classList.add('warn');
        }
        if (spoonVisual) spoonVisual.classList.toggle('tool-broken', stopped);
        if (durabilityMeta) {
            if (stopped) {
                durabilityMeta.textContent = '내구도 ' + DUR_STOP + '% 이하 · 수리 후 채굴 재개';
            } else {
                var eta = wearPerSec > 0 ? fmtEta(Math.max(0, dur - DUR_FLOOR) / wearPerSec) : '';
                var stepAmt = Math.min(Math.max(0, max - disp), repairStep);
                var cost = repairStepCostFmt ? ('+' + stepAmt + ' 수리 ' + repairStepCostFmt + '본냥') : ('+' + stepAmt + ' 수리');
                durabilityMeta.textContent = [eta ? ('잔여 약 ' + eta) : '', cost].filter(Boolean).join(' · ');
            }
        }
        if (btnRepair) {
            btnRepair.disabled = repairing || disp >= max;
            btnRepair.classList.toggle('repairing', !!repairing);
            btnRepair.title = disp >= max ? '이미 최대 내구도' : ('수리하기 (+' + repairStep + ')');
            btnRepair.setAttribute('aria-label', disp >= max ? '이미 최대 내구도' : '수리하기');
        }
        if (stopped) {
            if (!miningStoppedByDur || displayRate > 0) {
                freezeMiningForDurability();
                miningStoppedByDur = true;
            }
        } else if (miningStoppedByDur) {
            miningStoppedByDur = false;
            resumeMiningAfterDurability();
        } else if (displayRate > 0) {
            paintRateLine(displayRate, FROZEN_RATE_FMT);
        }
    }

    var yieldSlideIndex = 0;
    var yieldSlideTimer = null;

    function applyYieldValues(source) {
        if (!source) return;
        var hourly = source.hourly_yield_fmt ? String(source.hourly_yield_fmt) : '';
        var daily = source.daily_yield_fmt ? String(source.daily_yield_fmt) : '';
        var time1 = source.time_to_1pct_fmt ? String(source.time_to_1pct_fmt) : '';
        if (!hourly && daily) {
            var d = parseFloat(daily.replace(/,/g, ''));
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
        if (time1) TIME_TO_1PCT_FMT = time1;
        if (hourlyYieldValue) hourlyYieldValue.textContent = HOURLY_YIELD_FMT;
        if (dailyYieldValue) dailyYieldValue.textContent = DAILY_YIELD_FMT;
        if (timeTo1pctValue) timeTo1pctValue.textContent = TIME_TO_1PCT_FMT;
    }

    function yieldSlideLineHeight() {
        return rateYieldSlide ? rateYieldSlide.offsetHeight : 0;
    }

    function setYieldSlideIndex(idx) {
        yieldSlideIndex = ((idx % YIELD_SLIDE_COUNT) + YIELD_SLIDE_COUNT) % YIELD_SLIDE_COUNT;
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
            setYieldSlideIndex(yieldSlideIndex + 1);
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

    function setFrozenRate(rate, fmt) {
        rate = Number(rate) || 0;
        if (rate <= 0) return;
        // 속도만 바꿀 때도 현재 표시량을 보존한 채 시계만 다시 맞춤
        sealDisplayAmount(currentAmount(), rate, fmt || String(rate));
    }

    function applyRateFromSync(sync, opts) {
        if (!sync) return;
        opts = opts || {};
        applyYieldUnlock(sync);
        syncWeaponRateMeta(sync);
        var chatHint = document.getElementById('chatYieldHint');
        if (chatHint && sync.chat_yield_hint) {
            chatHint.textContent = sync.chat_yield_hint;
        }
        // 은총·최초 seal 전용. 일반 sync는 화면 속도 불변. 내구 정지 중이면 재가동 금지.
        if (opts.refreshRate && typeof sync.rate === 'number' && Number(sync.rate) > 0
            && !syncSaysDurStopped(sync) && !miningStoppedByDur) {
            setFrozenRate(sync.rate, sync.rate_fmt);
            applyYieldValues(sync);
            paintRateLine(displayRate, FROZEN_RATE_FMT);
            return;
        }
        if (!displaySealed && typeof sync.rate === 'number' && Number(sync.rate) > 0
            && !syncSaysDurStopped(sync) && !miningStoppedByDur) {
            setFrozenRate(sync.rate, sync.rate_fmt);
            applyYieldValues(sync);
            paintRateLine(displayRate, FROZEN_RATE_FMT);
        } else {
            paintRateLine(displayRate, FROZEN_RATE_FMT);
        }
    }

    function syncSaysDurStopped(sync) {
        if (!sync) return false;
        if (sync.durability_stopped || sync.durability_broken) return true;
        if (typeof sync.durability_pct === 'number' && sync.durability_pct <= DUR_STOP + 1e-9) return true;
        return false;
    }

    function applySync(sync, activeElsewhere, opts) {
        if (!sync) return;
        opts = opts || {};
        if (upgrading && !opts.forcePending && !opts.refreshRate) {
            return;
        }
        var nowSec = Math.floor(Date.now() / 1000);
        // 서버 pending은 lease/내구용으로만 보관. 화면 채굴량은 forcePending일 때만 재설정.
        pendingBase = Number(sync.pending) || 0;
        syncAtSec = nowSec;
        pendingSyncedOnce = true;
        if (opts.forcePending || !displaySealed) {
            var amt = Number(sync.pending) || 0;
            var rate = null;
            if (syncSaysDurStopped(sync)) {
                rate = 0;
            } else if (typeof sync.rate === 'number' && Number(sync.rate) > 0) {
                rate = Number(sync.rate);
            } else if (displayRate > 0) {
                rate = displayRate;
            }
            sealDisplayAmount(amt, rate, sync.rate_fmt);
        }
        if (typeof sync.durability === 'number') {
            durBase = Number(sync.durability);
        } else if (typeof sync.durability_display === 'number') {
            durBase = Number(sync.durability_display);
        }
        if (typeof sync.wear_per_sec === 'number') {
            wearPerSec = Number(sync.wear_per_sec);
        }
        if (sync.repair_step_cost_fmt) {
            repairStepCostFmt = String(sync.repair_step_cost_fmt);
        } else if (sync.repair_full_cost_fmt) {
            repairStepCostFmt = String(sync.repair_full_cost_fmt);
        }
        if (typeof sync.repair_step === 'number' && sync.repair_step > 0) {
            repairStep = Number(sync.repair_step);
        }
        if (typeof sync.durability_max === 'number' && sync.durability_max > 0) {
            DUR_MAX = Number(sync.durability_max);
            DUR_FLOOR = DUR_MAX * (DUR_STOP / 100);
        }
        if (typeof sync.stop_pct === 'number') {
            DUR_STOP = Math.max(0, Math.min(100, Number(sync.stop_pct)));
            DUR_FLOOR = DUR_MAX * (DUR_STOP / 100);
        }
        applyRateFromSync(sync, opts);
        applyDurabilityUi();
        leaseActiveElsewhere = !!activeElsewhere;
        if (sync.is_leader) {
            isServerLeader = true;
            leaseToken = sync.lease_token || leaseToken;
            saveToken(leaseToken);
        } else if (!sync.lease_active || activeElsewhere) {
            isServerLeader = false;
        }
    }

    function bindMiningSection(toggleBtn, bodyEl, iconEl, storageKey, onExpand, defaultCollapsed) {
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
        var initCollapsed = !!defaultCollapsed;
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
        if (!btnClaim) return;
        btnClaim.disabled = !canSave || claiming;
        btnClaim.classList.toggle('claiming', !!claiming);
        btnClaim.setAttribute('aria-label', canSave ? '수령하기' : '수령 가능 금액 미달');
        btnClaim.title = canSave ? '수령하기' : '수령 가능 금액 미달';
    }

    function renderTick() {
        updateMiningPendingDisplay();
        updateOreSpawnCountdown();
        applyDurabilityUi();
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
        if (miningStoppedByDur) {
            toolStatus.textContent = miningStatusBase;
            return;
        }
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
            var tipOpen = node.classList.contains('tip-open');
            node.className = 'tool-node';
            if (lv < level) node.classList.add('done');
            else if (lv === level) node.classList.add('current');
            else node.classList.add('locked');
            if (tipOpen) node.classList.add('tip-open');
        });
        if (doScroll !== false) {
            scheduleRoadmapScroll(doScroll !== 'instant');
        }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function closeToolYieldTip() {
        if (!toolRoadmap) return;
        toolRoadmap.querySelectorAll('.tool-node.tip-open').forEach(function(node) {
            node.classList.remove('tip-open');
            node.setAttribute('aria-expanded', 'false');
        });
        if (toolYieldTip) {
            toolYieldTip.classList.remove('open');
            toolYieldTip.hidden = true;
            toolYieldTip.innerHTML = '';
        }
    }

    function renderToolYieldTipHtml(tip) {
        if (!tip) return '';
        var html = '<div class="tool-yield-tip-head"><span class="tip-icon">' + (tip.icon || '') + '</span>'
            + '<span>Lv' + escapeHtml(tip.level) + ' ' + escapeHtml(tip.label || '') + '</span></div>';
        html += '<div class="tool-yield-tip-title">오늘 생타별 · 하루 예상</div>';
        html += '<div class="tool-yield-tip-note">은총 미적용 · 내구 유지 가정</div>';
        var tiers = Array.isArray(tip.chat_tiers) ? tip.chat_tiers : [];
        if (!tiers.length) {
            html += '<div class="tool-yield-tip-row"><span class="t-label">기준(×1)</span>'
                + '<span class="t-daily">' + escapeHtml(tip.daily_yield_fmt || '0') + '냥</span></div>';
        } else {
            tiers.forEach(function(tier) {
                html += '<div class="tool-yield-tip-row">'
                    + '<span class="t-label">' + escapeHtml(tier.label || '') + '</span>'
                    + '<span class="t-mult">' + escapeHtml(tier.mult_fmt || '') + '</span>'
                    + '<span class="t-daily">' + escapeHtml(tier.daily_fmt || '') + '냥</span>'
                    + '</div>';
            });
        }
        var costTiers = Array.isArray(tip.cost_discount_tiers) ? tip.cost_discount_tiers : [];
        if (costTiers.length) {
            html += '<div class="tool-yield-tip-title">오늘 생타별 · 강화비</div>';
            html += '<div class="tool-yield-tip-note">기본 강화비 대비 할인 (은총 별도)</div>';
            costTiers.forEach(function(ct) {
                var pct = Number(ct.discount_pct || 0) || 0;
                var disc = pct > 0 ? ('−' + pct + '%') : '할인없음';
                html += '<div class="tool-yield-tip-row">'
                    + '<span class="t-label">' + escapeHtml(ct.label || '') + '</span>'
                    + '<span class="t-mult">' + escapeHtml(disc) + '</span>'
                    + '<span class="t-daily">' + escapeHtml(ct.cost_fmt || '') + '냥</span>'
                    + '</div>';
            });
        }
        return html;
    }

    function openToolYieldTip(level) {
        if (!toolYieldTip || !toolRoadmap) return;
        var tip = TOOL_YIELD_TIPS && TOOL_YIELD_TIPS[level] ? TOOL_YIELD_TIPS[level] : null;
        if (!tip && TOOL_YIELD_TIPS && TOOL_YIELD_TIPS[String(level)]) {
            tip = TOOL_YIELD_TIPS[String(level)];
        }
        if (!tip) {
            closeToolYieldTip();
            return;
        }
        toolRoadmap.querySelectorAll('.tool-node').forEach(function(node) {
            var lv = Number(node.getAttribute('data-level'));
            var on = lv === Number(level);
            node.classList.toggle('tip-open', on);
            node.setAttribute('aria-expanded', on ? 'true' : 'false');
        });
        toolYieldTip.innerHTML = renderToolYieldTipHtml(tip);
        toolYieldTip.hidden = false;
        toolYieldTip.classList.add('open');
    }

    function bindToolYieldTips() {
        if (!toolRoadmap) return;
        toolRoadmap.querySelectorAll('.tool-node').forEach(function(node) {
            node.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var lv = Number(node.getAttribute('data-level'));
                if (node.classList.contains('tip-open')) {
                    closeToolYieldTip();
                    return;
                }
                openToolYieldTip(lv);
            });
        });
        document.addEventListener('click', function(e) {
            if (!toolYieldTip || toolYieldTip.hidden) return;
            if (toolYieldTip.contains(e.target)) return;
            if (e.target.closest && e.target.closest('.tool-node')) return;
            closeToolYieldTip();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeToolYieldTip();
        });
    }

    function applyTool(tool, opts) {
        if (!tool) return;
        opts = opts || {};
        var prevLevel = Number(TOOL.level) || 0;
        TOOL = tool;
        syncWeaponRateMeta(tool);
        // 강화 UI는 채굴 표시 속도를 바꾸지 않음 · 무기결합/은총 등은 refreshRate로 갱신
        if (!opts.keepMiningRate && opts.refreshRate && typeof tool.rate === 'number') {
            if (Number(tool.rate) > 0 && !miningStoppedByDur) {
                setFrozenRate(tool.rate, tool.rate_fmt);
                applyYieldValues(tool);
            } else if (Number(tool.rate) <= 0) {
                paintRateLine(0, '0');
            }
            paintRateLine(displayRate, FROZEN_RATE_FMT);
        } else if (displayRate > 0) {
            RATE = displayRate;
            paintRateLine(displayRate, FROZEN_RATE_FMT);
        } else {
            paintRateLine(displayRate, FROZEN_RATE_FMT);
        }
        if (toolIcon) toolIcon.textContent = tool.icon || '🥄';
        if (miningStoppedByDur) {
            setMiningStatusBase('내구도 ' + DUR_STOP + '% 이하 · 채굴 정지');
        } else {
            setMiningStatusBase(tool.status || (tool.label ? tool.label + '으로 채굴 중' : ''));
        }
        if (pageTitle) pageTitle.textContent = (tool.icon || '') + ' ' + (tool.label || '') + ' 채굴';
        if (!tool.can_upgrade && upgradeRow) upgradeRow.style.display = 'none';
        else if (tool.can_upgrade && upgradeRow) upgradeRow.style.display = '';
        if (btnUpgrade && tool.upgrade) {
            var labelEl = btnUpgrade.querySelector('.btn-upgrade-label');
            if (labelEl) labelEl.textContent = upgradeBtnLabel(tool);
            else btnUpgrade.textContent = upgradeBtnLabel(tool);
        }
        if (upgradeHint && tool.upgrade) {
            upgradeHint.innerHTML = upgradeHintHtml(tool);
            upgradePct = document.getElementById('upgradePct');
        }
        applyChatUpgradeDiscountHint(tool);
        updateRoadmap(tool.level, Number(tool.level) !== prevLevel);
        updateEunchongUi();
        updateUpgradeUi();
        applyYieldUnlock(tool);
        updateWeaponUi();
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
        if (j && j.tool) {
            applyTool(j.tool, { refreshRate: true });
        }
        updateWeaponUi();
    }

    function oreTouchHint(item) {
        if (!item) return '터치하여 수령';
        if (item.is_shard) return '터치 → 은총조각 +' + (item.qty || 1);
        var v = item.pending_value_fmt || String(item.pending_value || '');
        return '터치 → 채굴량 +' + v + '냥';
    }

    function updateMiningPendingDisplay() {
        var txt = fmtPending(currentAmount());
        if (miningPendingNum) {
            miningPendingNum.textContent = txt;
            return;
        }
        if (miningPendingAmount) {
            miningPendingAmount.textContent = txt + '냥';
        }
    }

    function updateOreSpawnCountdown() {
        // 채굴량 DOM을 여기서 절대 건드리지 않음
        if (!oreSpawnCountdown) return;
        var equipped = WEAPON && WEAPON.equipped;
        if (equipped && oreNextDeadlineMs !== null) {
            var left = Math.max(0, Math.ceil((oreNextDeadlineMs - Date.now()) / 1000));
            if (left > 0 && left <= ORE_COUNTDOWN_SEC) {
                oreSpawnCountdown.classList.add('active');
                oreSpawnCountdown.textContent = '광물..' + left;
                return;
            }
        }
        oreSpawnCountdown.classList.remove('active');
        oreSpawnCountdown.textContent = '';
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

    function applyOreState(j, opts) {
        if (!j) return;
        opts = opts || {};
        if (Array.isArray(j.ore_finds)) ORE_FINDS = j.ore_finds;
        if (typeof j.eunchong_shard === 'number') ORE_SHARD = j.eunchong_shard;
        if (typeof j.eunchong_shard_need === 'number') ORE_SHARD_NEED = j.eunchong_shard_need;
        if (typeof j.ore_discover_hint === 'string') {
            ORE_DISCOVER_HINT = j.ore_discover_hint;
        }
        // 강화 응답에서는 광물 스케줄/카운트다운도 갱신하지 않음
        if (!opts.fromUpgrade) {
            applyOreSpawnPreview(j);
        }
        renderOreFinds();
        updateOreShardLine();
        if (!opts.fromUpgrade) {
            updateWeaponUi();
        }
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
            var added = Number(j.pending_added);
            if (!isFinite(added) || added < 0) added = 0;

            if (typeof j.pending_after === 'number' && isFinite(j.pending_after) && j.pending_after >= 0) {
                var rate = (displayRate > 0)
                    ? displayRate
                    : ((FROZEN_RATE !== null && Number(FROZEN_RATE) > 0) ? Number(FROZEN_RATE) : null);
                sealDisplayAmount(Number(j.pending_after), rate, FROZEN_RATE_FMT);
            } else if (added > 0) {
                var rate2 = (displayRate > 0)
                    ? displayRate
                    : ((FROZEN_RATE !== null && Number(FROZEN_RATE) > 0) ? Number(FROZEN_RATE) : null);
                sealDisplayAmount(currentAmount() + added, rate2, FROZEN_RATE_FMT);
            } else if (j.sync) {
                applySync(j.sync, false, { forcePending: true });
            }

            if (j.sync) {
                pendingBase = Number(j.sync.pending) || pendingBase;
                pendingSyncedOnce = true;
            }
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

    function fmtWeaponMult(enh) {
        enh = parseInt(enh, 10) || 0;
        if (enh < 1) return '—';
        var m = Math.round((enh / 10) * 10000) / 10000;
        var s = String(m);
        if (s.indexOf('.') >= 0) s = s.replace(/0+$/, '').replace(/\.$/, '');
        return s === '' ? '0' : s;
    }

    function oreHourlyExpected(enhance, toolLv) {
        enhance = parseInt(enhance, 10) || 0;
        toolLv = parseInt(toolLv, 10) || 0;
        if (enhance < 10) return 0;
        var maxE = Math.min(Math.max(1, enhance), 100);
        var decade = Math.ceil(maxE / 10);
        var dailyMap = { 1: 12, 2: 18, 3: 24, 4: 30, 5: 36, 6: 42, 7: 48, 8: 54, 9: 57, 10: 60 };
        var daily = dailyMap[decade] || 0;
        var hourly = daily > 0 ? (daily / 24) : 0;
        var bonus = 0;
        if (toolLv >= 13) bonus = 5;
        else if (toolLv >= 12) bonus = 4;
        else if (toolLv >= 9) bonus = 3;
        else if (toolLv >= 5) bonus = 2;
        else if (toolLv >= 2) bonus = 1;
        return hourly + bonus;
    }

    function fmtOreHourlyCount(v) {
        v = Number(v) || 0;
        if (v <= 0) return '0';
        if (Math.abs(v - Math.round(v)) < 1e-9) return String(Math.round(v));
        return String(Math.round(v * 100) / 100).replace(/\.?0+$/, '');
    }

    function buildWeaponPerkLines() {
        var w = WEAPON || {};
        var has = !!w.has_weapon;
        var enh = parseInt(w.enhance, 10) || 0;
        var toolLv = (TOOL && TOOL.level != null) ? (parseInt(TOOL.level, 10) || 0) : 0;
        var boost = '부스트 가동 ×' + (has ? fmtWeaponMult(enh) : '—');
        var spawn = (has && enh >= 10) ? '광물 출현' : '광물 출현 (+10↑)';
        var hourlyLine = '시간당 추가광물';
        var hourly = (has && enh >= 10) ? oreHourlyExpected(enh, toolLv) : 0;
        if (hourly > 0) {
            var bonus = 0;
            if (toolLv >= 13) bonus = 5;
            else if (toolLv >= 12) bonus = 4;
            else if (toolLv >= 9) bonus = 3;
            else if (toolLv >= 5) bonus = 2;
            else if (toolLv >= 2) bonus = 1;
            hourlyLine = bonus > 0
                ? ('시간당 추가광물 약 ' + fmtOreHourlyCount(hourly) + '개 (장비 +' + bonus + ')')
                : ('시간당 추가광물 약 ' + fmtOreHourlyCount(hourly) + '개');
        }
        return [boost, spawn, hourlyLine, '고급 광물 확률↑'];
    }

    function updateWeaponUi() {
        if (!WEAPON) WEAPON = { has_weapon: false, label: '없음', equipped: false };
        var weaponSection = document.getElementById('weaponSection');
        if (weaponSection) {
            weaponSection.classList.toggle('is-bound', !!WEAPON.equipped);
        }
        if (weaponLabel) weaponLabel.textContent = WEAPON.label || '없음';
        if (weaponBindStatus) {
            weaponBindStatus.textContent = WEAPON.equipped ? '결합' : '해제';
            weaponBindStatus.className = 'weapon-bind-status' + (WEAPON.equipped ? ' on' : '');
        }
        if (weaponEquippedTag) {
            weaponEquippedTag.style.display = WEAPON.equipped ? '' : 'none';
            weaponEquippedTag.className = 'weapon-equipped-tag' + (WEAPON.equipped ? ' on' : '');
            weaponEquippedTag.textContent = '부스트 ON';
        }
        if (weaponRowHint) {
            weaponRowHint.style.display = '';
            weaponRowHint.classList.toggle('is-idle', !WEAPON.equipped);
            weaponRowHint.textContent = buildWeaponPerkLines().join('\n');
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

    function applyEunchongFields(j) {
        if (!j) return;
        if (typeof j.eunchong_cnt === 'number') memberEunchong = j.eunchong_cnt;
        if (typeof j.eunchong_active !== 'undefined') memberEunchongActive = !!j.eunchong_active;
        if (typeof j.eunchong_left_sec === 'number') memberEunchongLeftSec = j.eunchong_left_sec;
        if (typeof j.eunchong_zeros === 'number') memberEunchongZeros = j.eunchong_zeros;
        if (typeof j.eunchong_cost_discount !== 'undefined') memberEunchongCostDiscount = !!j.eunchong_cost_discount;
        if (typeof j.eunchong_label === 'string') memberEunchongLabel = j.eunchong_label;
        if (typeof j.eunchong_tier === 'number') memberEunchongTier = j.eunchong_tier;
        if (typeof j.eunchong_btn_label === 'string') memberEunchongBtnLabel = j.eunchong_btn_label;
        if (typeof j.eunchong_use_tier === 'number') memberEunchongUseTier = j.eunchong_use_tier;
        if (typeof j.eunchong_use_label === 'string') memberEunchongUseLabel = j.eunchong_use_label;
        if (typeof j.eunchong_use_cost === 'number') memberEunchongUseCost = j.eunchong_use_cost;
        if (typeof j.eunchong_use_zeros === 'number') memberEunchongUseZeros = j.eunchong_use_zeros;
        if (typeof j.eunchong_use_cost_discount !== 'undefined') memberEunchongUseCostDiscount = !!j.eunchong_use_cost_discount;
    }

    function applyEunchongState(j) {
        if (!j) return;
        applyEunchongFields(j);
        if (j.sync) {
            // 은총 적용/만료 시에만 초당 속도 재고정 허용
            applySync(j.sync, !!j.active_elsewhere, { refreshRate: true, forcePending: true });
        }
        updateEunchongUi();
        renderTick();
    }

    function eunchongDefByTier(tier) {
        tier = Math.max(1, Math.min(3, tier | 0));
        if (tier === 3) {
            return { tier: 3, label: '테라은총', cost: EUNCHONG_TERRA_COST, zeros: 3, costDiscount: false };
        }
        if (tier === 2) {
            return { tier: 2, label: '메가은총', cost: EUNCHONG_MEGA_COST, zeros: 2, costDiscount: false };
        }
        return { tier: 1, label: '은총', cost: 1, zeros: 1, costDiscount: true };
    }

    function eunchongButtonLabel(cnt) {
        return '은총(' + Math.max(0, cnt | 0) + ')';
    }

    function setPendingEunchongTier(tier) {
        var def = eunchongDefByTier(tier);
        pendingEunchongTier = def.tier;
        memberEunchongUseTier = def.tier;
        memberEunchongUseLabel = def.label;
        memberEunchongUseCost = def.cost;
        memberEunchongUseZeros = def.zeros;
        memberEunchongUseCostDiscount = !!def.costDiscount;
        memberEunchongBtnLabel = eunchongButtonLabel(memberEunchong);
    }

    function activeSuccessHint(up) {
        if (!up) return memberEunchongActive ? UPGRADE_PCT_EUNCHONG : UPGRADE_PCT;
        if (!memberEunchongActive) {
            return up.success_hint || up.success_pct || UPGRADE_PCT;
        }
        if (memberEunchongZeros >= 3 && up.success_hint_terra) return up.success_hint_terra;
        if (memberEunchongZeros >= 2 && up.success_hint_mega) return up.success_hint_mega;
        if (up.success_hint_eunchong) return up.success_hint_eunchong;
        if (memberEunchongZeros >= 3 && up.success_pct_terra) return up.success_pct_terra;
        if (memberEunchongZeros >= 2 && up.success_pct_mega) return up.success_pct_mega;
        if (up.success_pct_eunchong) return up.success_pct_eunchong;
        return UPGRADE_PCT_EUNCHONG;
    }

    function currentUpgradePct() {
        var up = TOOL && TOOL.upgrade ? TOOL.upgrade : null;
        return activeSuccessHint(up);
    }

    function upgradeCostFmt(tool) {
        if (!tool || !tool.upgrade) return '';
        var up = tool.upgrade;
        var chatPct = Number(up.chat_upgrade_discount_pct || tool.chat_upgrade_discount_pct || 0) || 0;
        if (memberEunchongActive && memberEunchongCostDiscount && up.cost_eunchong_fmt) {
            return up.cost_base_fmt + ' → ' + up.cost_eunchong_fmt;
        }
        if (chatPct > 0 && up.cost_base_raw_fmt && up.cost_base_fmt) {
            return up.cost_base_raw_fmt + ' → ' + up.cost_base_fmt + ' (−' + chatPct + '%)';
        }
        return up.cost_fmt || up.cost_base_fmt || String(up.cost || up.cost_base || '') || '';
    }

    function fmtUpgradeCostNum(n) {
        n = String(n == null ? '' : n).replace(/[^\d]/g, '');
        if (!n) return '0';
        try {
            return Number(n).toLocaleString('ko-KR');
        } catch (e) {
            return n;
        }
    }

    function updateUpgradeCostBox() {
        if (!upgradeCostValue) return;
        var times = selectedUpgradeTimes();
        var unit = upgradeCostNow();
        var total = null;
        try {
            if (typeof BigInt !== 'undefined') {
                total = (BigInt(String(unit).replace(/[^\d]/g, '') || '0') * BigInt(times)).toString();
            } else {
                total = String(Math.floor(Number(unit) * times) || 0);
            }
        } catch (e) {
            total = String(Math.floor(Number(unit) * times) || 0);
        }
        upgradeCostValue.textContent = fmtUpgradeCostNum(times > 1 ? total : unit) + '냥';
        upgradeCostValue.style.display = (TOOL && TOOL.can_upgrade && TOOL.upgrade) ? '' : 'none';
    }

    function upgradeHintHtml(tool) {
        if (!tool || !tool.upgrade) return '';
        var pct = activeSuccessHint(tool.upgrade);
        return (tool.upgrade.to_icon || '') + (tool.upgrade.to_label || '')
            + ' · 성공 확률 <span id="upgradePct">' + pct + '</span><br>'
            + '<span style="opacity:0.8;font-size:0.78rem;">강화비: 시총 완만화(무기보다 약간 높음) · 50% 소멸 · 25% 금고 · 25% 로또</span>';
    }

    function applyChatUpgradeDiscountHint(tool) {
        var el = document.getElementById('chatUpgradeDiscountHint');
        if (!el) return;
        var hint = (tool && tool.chat_upgrade_discount_hint) ? String(tool.chat_upgrade_discount_hint) : '';
        if (!hint && tool && tool.upgrade && tool.upgrade.chat_upgrade_discount_pct > 0) {
            hint = '강화비 ' + tool.upgrade.chat_upgrade_discount_pct + '% 할인 적용';
        }
        el.textContent = hint;
    }

    function updateEunchongModal() {
        var def = eunchongDefByTier(pendingEunchongTier || 1);
        var label = def.label;
        var cost = Math.max(1, def.cost | 0);
        var zeros = Math.max(1, def.zeros | 0);
        var effect = def.costDiscount
            ? '분모 1/10 · 강화비 할인 없음'
            : ('강화확률 0 ' + zeros + '개 제거 · 강화비 할인 없음');
        if (eunchongModalTitle) {
            eunchongModalTitle.textContent = '✨ ' + label + ' 사용';
        }
        if (eunchongModalBody) {
            var mins = (pendingEunchongTier || 1) >= 2 ? 10 : 5;
            eunchongModalBody.innerHTML = label + '을(를) 사용하시겠습니까?<br>'
                + '은총 ' + cost + '개 소모 · ' + mins + '분간 강화 성공 확률·채굴량이 상승합니다.<br>'
                + effect + ' · 채굴량 x' + EUNCHONG_YIELD_MULT;
        }
    }

    function isMegaBoosterActivePeriod() {
        return !!(memberEunchongActive && memberEunchongLeftSec > 0 && parseInt(memberEunchongTier, 10) === 2);
    }
    function isMegaBoosterChecked() {
        return !!(megaBoosterChk && megaBoosterChk.checked && isMegaBoosterActivePeriod());
    }
    function updateMegaBoosterUi() {
        if (!megaBoosterWrap) return;
        var show = isMegaBoosterActivePeriod();
        if (show) {
            megaBoosterWrap.classList.add('show');
        } else {
            megaBoosterWrap.classList.remove('show');
            if (megaBoosterChk) megaBoosterChk.checked = false;
        }
    }

    function updateEunchongUi() {
        memberEunchongBtnLabel = eunchongButtonLabel(memberEunchong);
        var showBuff = memberEunchongActive && memberEunchongLeftSec > 0;
        if (eunchongBuff) {
            eunchongBuff.className = 'eunchong-buff' + (showBuff ? ' active' : '');
        }
        if (eunchongBuffLeft) {
            eunchongBuffLeft.textContent = showBuff ? fmtEunchongLeft(memberEunchongLeftSec) : '';
        }
        if (eunchongBuffLabel) {
            eunchongBuffLabel.textContent = memberEunchongLabel || '은총';
        }
        if (eunchongBuffTo && TOOL && TOOL.upgrade) {
            eunchongBuffTo.textContent = activeSuccessHint(TOOL.upgrade);
        }
        if (eunchongBuffCost && TOOL && TOOL.upgrade) {
            var up = TOOL.upgrade;
            if (memberEunchongActive && memberEunchongCostDiscount && up.cost_base_fmt && up.cost_eunchong_fmt) {
                eunchongBuffCost.textContent = '비용 ' + up.cost_base_fmt + '냥 → ' + up.cost_eunchong_fmt + '냥';
            } else if (memberEunchongActive) {
                eunchongBuffCost.textContent = '강화비 할인 없음';
            }
        }
        if (upgradePct) {
            upgradePct.textContent = currentUpgradePct();
        }
        if (upgradeHint && TOOL && TOOL.upgrade) {
            upgradeHint.innerHTML = upgradeHintHtml(TOOL);
            upgradePct = document.getElementById('upgradePct');
        }
        var canUpgrade = !!(TOOL && TOOL.can_upgrade);
        var busy = usingEunchong || !canUpgrade;
        if (btnUseEunchong) {
            var showNormal = memberEunchong >= 1;
            btnUseEunchong.style.display = showNormal ? '' : 'none';
            btnUseEunchong.disabled = busy || !showNormal;
            btnUseEunchong.textContent = eunchongButtonLabel(memberEunchong);
        }
        if (btnUseMegaEunchong) {
            var showMega = memberEunchong >= EUNCHONG_MEGA_COST;
            btnUseMegaEunchong.style.display = showMega ? '' : 'none';
            btnUseMegaEunchong.disabled = busy || !showMega;
        }
        updateMegaBoosterUi();
        if (btnUseTerraEunchong) {
            // 테라은총 일시 비표시
            btnUseTerraEunchong.style.display = 'none';
            btnUseTerraEunchong.disabled = true;
            btnUseTerraEunchong.hidden = true;
        }
        if (btnEunchongConfirm) {
            var need = eunchongDefByTier(pendingEunchongTier || 1).cost;
            btnEunchongConfirm.disabled = usingEunchong || memberEunchong < need;
        }
        updateUpgradeUi();
    }

    function openEunchongModal(tier) {
        setPendingEunchongTier(tier || 1);
        updateEunchongModal();
        if (eunchongModal) eunchongModal.classList.add('open');
    }

    function closeEunchongModal() {
        if (eunchongModal) eunchongModal.classList.remove('open');
    }
    window.miningCloseEunchongModal = closeEunchongModal;

    function doUseEunchong() {
        var def = eunchongDefByTier(pendingEunchongTier || 1);
        if (usingEunchong || memberEunchong < def.cost) return;
        usingEunchong = true;
        updateEunchongUi();
        closeEunchongModal();
        setUpgradeMsg(def.label + ' 적용 중…', '');
        ajaxPost({ action: 'use_eunchong', tier: def.tier }).then(function(j) {
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
        if (memberEunchongActive && memberEunchongCostDiscount && (typeof up.cost_eunchong === 'number' || typeof up.cost_eunchong === 'string')) {
            return up.cost_eunchong;
        }
        if (typeof up.cost_base === 'number' || typeof up.cost_base === 'string') return up.cost_base;
        if (typeof up.cost === 'number' || typeof up.cost === 'string') return up.cost;
        return UPGRADE_COST;
    }

    function selectedUpgradeTimes() {
        var el = document.querySelector('input[name="upgradeTimes"]:checked');
        var n = el ? parseInt(el.value, 10) : 1;
        if (isNaN(n) || n < 1) return 1;
        if ([1, 100, 500, 1000, 3000, 5000].indexOf(n) === -1) return 1;
        return n;
    }

    function upgradeChargeNow() {
        return upgradeCostNow();
    }

    var UPGRADE_TIMES_KEY = 'mining_upgrade_times_' + CODE;
    var UPGRADE_TIMES_ALLOWED = [1, 100, 500, 1000, 3000, 5000];

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
        var npCost = upgradeBatchNewpointCost(selectedUpgradeTimes());
        if (npCost > 0) {
            return pointGte(memberPoint, upgradeCostNow())
                && memberNewpoint >= npCost;
        }
        return pointGte(memberPoint, upgradeCostNow());
    }

    function updateUpgradePromoNotice() {
        if (!upgradePromoNotice) return;
        var npCost = upgradeBatchNewpointCost(selectedUpgradeTimes());
        if (npCost < 1) {
            upgradePromoNotice.classList.remove('on');
            upgradePromoNotice.textContent = '';
            return;
        }
        upgradePromoNotice.textContent = '본방냥 ' + npCost + '냥이 추가로 차감됩니다.';
        upgradePromoNotice.classList.add('on');
    }

    function ensureSelectableUpgradeTimes() {
        var cur = document.querySelector('input[name="upgradeTimes"]:checked');
        if (cur && !cur.disabled) return;
        var pick = null;
        // 가능한 횟수 중 큰 값 우선 (5000 불가 → 3000 → 1000 …)
        for (var i = UPGRADE_TIMES_ALLOWED.length - 1; i >= 0; i--) {
            var r = document.querySelector('input[name="upgradeTimes"][value="' + UPGRADE_TIMES_ALLOWED[i] + '"]');
            if (r && !r.disabled) {
                pick = r;
                break;
            }
        }
        if (pick) {
            pick.checked = true;
            saveUpgradeTimes(selectedUpgradeTimes());
        }
    }

    function updateUpgradeUi() {
        // 횟수 선택은 강화 가능 여부만으로 잠금 — 잔액 부족으로 전부 disabled 되면
        // 1000 선택 상태에서 다른 횟수로 못 바꾸는 버그가 생김
        var sectionOk = !!(TOOL && TOOL.can_upgrade) && !upgrading;
        document.querySelectorAll('input[name="upgradeTimes"]').forEach(function(r) {
            var t = parseInt(r.value, 10) || 1;
            var npCost = upgradeBatchNewpointCost(t);
            // 본방냥 배치 비용이 필요한 옵션만 잔액으로 비활성 (1/100/500은 선택 가능 유지)
            var npOk = npCost < 1 || memberNewpoint >= npCost;
            r.disabled = !sectionOk || !npOk;
        });
        ensureSelectableUpgradeTimes();
        var canTry = sectionOk && upgradeAffordNow();
        if (btnUpgrade) btnUpgrade.disabled = !canTry;
        if (upgradePromoNotice) {
            updateUpgradePromoNotice();
        }
        updateUpgradeCostBox();
    }

    function pointGte(a, b) {
        var as = String(a == null ? '0' : a).replace(/[^\d]/g, '') || '0';
        var bs = String(b == null ? '0' : b).replace(/[^\d]/g, '') || '0';
        as = as.replace(/^0+(?=\d)/, '');
        bs = bs.replace(/^0+(?=\d)/, '');
        if (as.length !== bs.length) return as.length > bs.length;
        return as >= bs;
    }

    function setPointDisplay(fmt, point) {
        if (point !== undefined && point !== null && point !== '') memberPoint = point;
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
        // 채굴량(display*/miningPendingAmount)은 이 함수에서 읽지도 쓰지도 않음
        applyEunchongFields(j);
        updateEunchongUi();
        if (j.weapon) WEAPON = j.weapon;
        if (weaponLabel && WEAPON) weaponLabel.textContent = WEAPON.label || '없음';
        applyOreState(j, { fromUpgrade: true });
        setPointDisplay(j.point_fmt, j.point);
        if (j.newpoint_fmt) {
            setNewpointDisplay(j.newpoint_fmt, j.newpoint);
        }
        if (j.tool) {
            // 라벨/비용만 — rate 반영 경로 차단
            var prevRate = displayRate;
            applyTool(j.tool, { keepMiningRate: true });
            displayRate = prevRate;
            RATE = prevRate;
            if (rateValue && prevRate > 0) {
                paintRateLine(prevRate, FROZEN_RATE_FMT);
            }
            applyDurabilityUi();
        } else {
            updateUpgradeUi();
        }
        // unlock hint 텍스트만
        if (j.yield_unlocked || (j.tool && j.tool.yield_unlocked)) {
            var hint = document.getElementById('yieldLockHint');
            if (hint) {
                hint.style.display = 'none';
                hint.textContent = '';
            }
            // 해금 직후에만 속도 0→양수 시동 (수량 시계는 리셋하지 않음)
            if (displayRate <= 0 && j.tool && Number(j.tool.rate) > 0) {
                displayRate = Number(j.tool.rate);
                FROZEN_RATE = displayRate;
                FROZEN_RATE_FMT = j.tool.rate_fmt ? String(j.tool.rate_fmt) : String(displayRate);
                syncWeaponRateMeta(j.tool);
                RATE = displayRate;
                displayClock0 = Date.now() / 1000;
                paintRateLine(displayRate, FROZEN_RATE_FMT);
            }
        } else {
            applyYieldUnlock(j);
        }
        if (j.success || (j.batch_success || 0) > 0) {
            durBase = DUR_MAX;
            syncAtSec = Date.now() / 1000;
            applyDurabilityUi();
        }
        updateUpgradeUi();
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
        ajaxPost({ action: 'upgrade', times: times, booster: isMegaBoosterChecked() ? 1 : 0 }).then(function(j) {
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
            openEunchongModal(1);
        });
    }
    if (btnUseMegaEunchong) {
        btnUseMegaEunchong.addEventListener('click', function() {
            if (btnUseMegaEunchong.disabled || memberEunchong < EUNCHONG_MEGA_COST) return;
            openEunchongModal(2);
        });
    }
    if (btnUseTerraEunchong) {
        btnUseTerraEunchong.addEventListener('click', function() {
            if (btnUseTerraEunchong.disabled || memberEunchong < EUNCHONG_TERRA_COST) return;
            openEunchongModal(3);
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
        // 첫 페인트 후 sync (진입 체감 개선) — INITIAL_SYNC 로 UI 먼저 그림
        var kick = function() {
            ajaxSync(!!leaseToken).then(function(j) {
                handleSyncResponse(j, true);
                if (!isServerLeader && j && j.active_elsewhere) {
                    startFollowerMode();
                }
            }).catch(function() {});
        };
        if (typeof requestIdleCallback === 'function') {
            requestIdleCallback(kick, { timeout: 700 });
        } else {
            setTimeout(kick, 280);
        }
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
        applySync(INITIAL_SYNC, false, { forcePending: true });
    } else if (!displaySealed) {
        sealDisplayAmount(0, displayRate > 0 ? displayRate : null, FROZEN_RATE_FMT);
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
    bindToolYieldTips();
    setTimeout(function() { scheduleRoadmapScroll(false); }, 150);
    window.addEventListener('resize', function() {
        scheduleRoadmapScroll(false);
        setYieldSlideIndex(yieldSlideIndex);
    });
    bindMiningSection(btnDurabilityToggle, durabilitySectionBody, durabilityToggleIcon, 'mining_durability_collapsed_' + CODE, null, true);
    bindMiningSection(btnUpgradeToggle, upgradeSectionBody, upgradeToggleIcon, 'mining_upgrade_collapsed_' + CODE, function() {
        layoutUpgradeBtnPos();
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
                memberEunchongZeros = 0;
                memberEunchongCostDiscount = false;
                memberEunchongTier = 1;
                memberEunchongLabel = '은총';
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
                    displaySealed = false; // 수령 후 서버 수량으로 시계 재설정 허용
                    applySync(j.sync, false, { forcePending: true });
                } else {
                    pendingBase = 0;
                    syncAtSec = Math.floor(Date.now() / 1000);
                    pendingSyncedOnce = true;
                    sealDisplayAmount(0, displayRate > 0 ? displayRate : null, FROZEN_RATE_FMT);
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

    if (btnRepair) {
        btnRepair.addEventListener('click', function() {
            if (repairing) return;
            var dur = currentDurability();
            if (dur >= DUR_MAX - 1e-9) {
                setRepairMsg('이미 최대 내구도예요.', 'fail');
                return;
            }
            repairing = true;
            applyDurabilityUi();
            setRepairMsg('수리 중…', '');
            ajaxPost({ action: 'repair', amount: repairStep }).then(function(j) {
                if (!j || !j.ok) {
                    setRepairMsg((j && j.data) ? j.data : '수리에 실패했어요.', 'fail');
                    return;
                }
                // 수령(claim)과 동일: 서버가 돌려준 차감 후 본방냥으로 코너 잔액 갱신
                if (j.newpoint_fmt) {
                    setNewpointDisplay(j.newpoint_fmt, j.newpoint);
                } else if (j.member && j.member.newpoint_fmt) {
                    setNewpointDisplay(j.member.newpoint_fmt, j.member.newpoint);
                }
                if (j.point_fmt && wPoint) wPoint.textContent = j.point_fmt;
                if (j.member && j.member.point_fmt && wPoint) wPoint.textContent = j.member.point_fmt;
                if (j.member && typeof j.member.point !== 'undefined') memberPoint = String(j.member.point);
                else if (typeof j.point !== 'undefined') memberPoint = String(j.point);
                if (j.sync) applySync(j.sync, false);
                else if (j.durability) applySync(Object.assign({}, INITIAL_SYNC || {}, j.durability, {
                    pending: pendingBase,
                    durability: j.durability.durability,
                    wear_per_sec: j.durability.wear_per_sec,
                    repair_step_cost_fmt: j.durability.repair_step_cost_fmt,
                    repair_full_cost_fmt: j.durability.repair_full_cost_fmt,
                    repair_step: j.durability.repair_step
                }), false);
                renderTick();
                setRepairMsg(j.data || '수리 완료', 'ok');
            }).catch(function() {
                setRepairMsg('네트워크 오류가 발생했어요.', 'fail');
            }).finally(function() {
                repairing = false;
                applyDurabilityUi();
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
(function hideMiningBoot() {
    var boot = document.getElementById('miningBoot');
    if (!boot) return;
    var drop = function() {
        boot.style.opacity = '0';
        boot.style.transition = 'opacity .18s ease';
        setTimeout(function() {
            if (boot && boot.parentNode) boot.parentNode.removeChild(boot);
        }, 200);
    };
    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(function() { requestAnimationFrame(drop); });
    } else {
        setTimeout(drop, 0);
    }
})();
</script>
<?php } else { ?>
<script>
(function() {
    var boot = document.getElementById('miningBoot');
    if (boot && boot.parentNode) boot.parentNode.removeChild(boot);
})();
</script>
<?php } ?>
<?php
if (!empty($mining_code)) {
  require_once __DIR__ . '/wallet_nav_fab.inc.php';
  wallet_nav_fab_render(['code' => $mining_code]);
}
?>
</body>
</html>
