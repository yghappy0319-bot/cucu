<?php
/**
 * 개인 가방 웹 (홀짝웹과 동일 code 인증)
 *
 * URL:
 *   /api/game/wallet_web.php?code=XXXX
 *   /page/wallet.php?code=XXXX
 *
 * AJAX:
 *   action=status       — 잔액·오늘 요약
 *   action=swap_quote   — 스왑 견적 (dir=np2pt|pt2np, amount)
 *   action=swap_np      — 본방냥→게임냥 (amount 비우면 전액)
 *   action=swap_pt      — 게임냥→본방냥 (amount 비우면 전액 · 지정 시 최소 5000)
 *   action=transfer_np  — 본방냥 양도 (receiver, amount)
 *   action=transfer_pt  — 게임냥 양도 (receiver, amount)
 *   action=profile_status — 프변 보유·적용 기간
 *   action=profile_use    — 프변 사용 (amount=개수, 1개=3일)
 *   action=jiho_use       — 지호 사용 (amount=개수, 1개=1시간)
 *   action=emergency_alarm — 긴급알람(당근) 공창 전송
 *   action=mining_pending_reset_all — (민호) 전 회원 채굴 미수령량 0
 *
 * 교환: /page/item_shop.php · 가방 바로가기「교환」 · 아이템 구매는 거래소
 */

if (!defined('WALLET_CODE_COOKIE_NAME')) {
    define('WALLET_CODE_COOKIE_NAME', 'wallet_code');
    define('WALLET_CODE_COOKIE_TTL', 7 * 86400);
}

/** 배포·인증 진단 (?_ping=1&code=XXXX) */
if (isset($_GET['_ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ping_code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    $ping_esc = addslashes($ping_code);
    $ping_row = function_exists('db_select')
        ? db_select("SELECT idx, name, point FROM tb_member WHERE code = '{$ping_esc}' LIMIT 1")
        : null;
    global $conn;
    echo json_encode([
        'wallet_build' => '20260626b',
        'code' => $ping_code,
        'row' => $ping_row,
        'mysqli' => ($conn instanceof mysqli),
        'script' => $_SERVER['SCRIPT_NAME'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 홀짝웹 game_auth() 와 동일 — function.php 로드 전에도 사용 가능 */
function wallet_game_auth_row($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') {
        return null;
    }
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) {
        return null;
    }
    return $row;
}

/** .퇴근/.장퇴 등록( tb_work 또는 status=3 ) 여부 */
function wallet_nick_is_퇴근($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return false;
    }
    if (function_exists('getTwoCharNick')) {
        $parsed = getTwoCharNick($nick);
        if ($parsed !== '') {
            $nick = $parsed;
        }
    }
    $esc = addslashes($nick);
    $work = @db_select("SELECT idx FROM tb_work WHERE nick = '{$esc}' LIMIT 1");
    if (!empty($work['idx'])) {
        return true;
    }
    $mem = @db_select("SELECT status FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    return isset($mem['status']) && (int)$mem['status'] === 3;
}

function wallet_퇴근_차단_메시지() {
    return '퇴근 중에는 바로가기를 이용할 수 없어요. .출근 후 다시 이용해주세요.';
}

function wallet_퇴근_차단_여부_설정($on = true) {
    $GLOBALS['wallet_deny_퇴근'] = (bool)$on;
}

function wallet_퇴근_차단_여부() {
    return !empty($GLOBALS['wallet_deny_퇴근']);
}

/** 홀짝웹 페이지 로드와 동일 */
function wallet_odd_even_includes() {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}

function wallet_json($data) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_BIGINT_AS_STRING')) {
        $flags |= JSON_BIGINT_AS_STRING;
    }
    echo json_encode($data, $flags);
    exit;
}

function wallet_db_boot() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('db_query')) {
        return;
    }
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($root !== '' && is_file($root . '/lib/_function.php')) {
        include_once $root . '/lib/_function.php';
    }
    if (!function_exists('db_query')) {
        $candidates = [
            dirname(__DIR__, 2) . '/lib/_function.php',
            __DIR__ . '/../../lib/_function.php',
            '/home/kakao/public_html/lib/_function.php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                include_once $path;
                break;
            }
        }
    }
    if (!function_exists('db_query')) {
        require_once __DIR__ . '/../_bootstrap.php';
    }
}

function wallet_lib_include() {
    wallet_odd_even_includes();
    require_once __DIR__ . '/member_code_auth.inc.php';
}

function wallet_page_boot() {
    wallet_lib_include();
}

function wallet_functions_load() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    wallet_db_boot();
    if (!function_exists('getTwoCharNick')) {
        $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($root !== '' && is_file($root . '/api/function.php')) {
            include_once $root . '/api/function.php';
        } elseif (is_file(dirname(__DIR__) . '/function.php')) {
            include_once dirname(__DIR__) . '/function.php';
        } else {
            include_once __DIR__ . '/../function.php';
        }
    }
}

function wallet_member_row_enrich(array $row, $code) {
    $esc = addslashes(trim((string)$code));
    // 핵심 컬럼 먼저 (실패 시 본방냥 0 표시 방지)
    $extra = @db_select("SELECT newpoint, title, style, item, enhance FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (is_array($extra)) {
        $row = array_merge($row, $extra);
    }
    // 선택 컬럼은 별도 — 없는 컬럼이 있어도 newpoint·protect 유지
    // ※ stasu 미존재 DB에서 한 쿼리에 넣으면 전체 실패 → protect=0 버그 발생
    $opt = @db_select("SELECT tasu, protect, level FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (is_array($opt)) {
        foreach (['tasu', 'protect', 'level'] as $col) {
            if (array_key_exists($col, $opt)) {
                $row[$col] = $opt[$col];
            }
        }
    }
    $stasuOpt = @db_select("SELECT stasu FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (is_array($stasuOpt) && array_key_exists('stasu', $stasuOpt)) {
        $row['stasu'] = $stasuOpt['stasu'];
    }
    return $row;
}

/** @see wallet_game_auth_row() */
function wallet_auth($code) {
    wallet_odd_even_includes();
    $row = wallet_game_auth_row($code);
    if (!$row) {
        return null;
    }
    return wallet_member_row_enrich($row, $code);
}

function wallet_코드_쿠키_읽기() {
    if (!isset($_COOKIE[WALLET_CODE_COOKIE_NAME])) {
        return '';
    }
    return trim((string)$_COOKIE[WALLET_CODE_COOKIE_NAME]);
}

function wallet_코드_쿠키_저장($code) {
    $code = trim((string)$code);
    if ($code === '') {
        return false;
    }
    return setcookie(
        WALLET_CODE_COOKIE_NAME,
        $code,
        time() + WALLET_CODE_COOKIE_TTL,
        '/',
        '',
        false,
        true
    );
}

function wallet_코드_쿠키_삭제() {
    setcookie(WALLET_CODE_COOKIE_NAME, '', time() - 3600, '/', '', false, true);
}

function wallet_코드_리다이렉트($code) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/page/wallet.php';
    $path = parse_url($uri, PHP_URL_PATH);
    if ($path === '' || $path === false) {
        $path = '/page/wallet.php';
    }
    $query = $_GET;
    $query['code'] = $code;
    $qs = http_build_query($query);
    header('Location: ' . $path . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
}

/** GET·wallet_code·쿠키 순 후보 (WAF가 POST code 를 망가뜨리는 경우 대비) */
function wallet_code_candidates($preferred = '') {
    $list = [];
    $push = function ($v) use (&$list) {
        $v = trim((string)$v);
        if ($v !== '' && !in_array($v, $list, true)) {
            $list[] = $v;
        }
    };
    $push($preferred);
    if (isset($_GET['code'])) {
        $push($_GET['code']);
    }
    if (isset($_POST['wallet_code'])) {
        $push($_POST['wallet_code']);
    }
    if (isset($_POST['code'])) {
        $push($_POST['code']);
    }
    $push(wallet_코드_쿠키_읽기());
    if (isset($_REQUEST['code'])) {
        $push($_REQUEST['code']);
    }
    return $list;
}

/** GET code 우선 (홀짝웹 $game_code 와 동일) */
function wallet_코드_해석() {
    $c = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
    if ($c !== '') {
        return $c;
    }
    return wallet_코드_쿠키_읽기();
}

function wallet_nick_from_row(array $row) {
    $nick = trim((string)($row['name'] ?? ''));
    if ($nick === '') {
        return '';
    }
    if (function_exists('getTwoCharNick')) {
        $parsed = getTwoCharNick($nick);
        if ($parsed !== '') {
            return $parsed;
        }
    }
    return $nick;
}

/** 양도 받는 닉 — 2글자 닉·이모티 닉 파싱 */
function wallet_resolve_receiver($receiver) {
    $receiver = trim((string)$receiver);
    if ($receiver === '') {
        return '';
    }
    if (function_exists('getTwoCharNick')) {
        $parsed = getTwoCharNick($receiver);
        if ($parsed !== '') {
            return $parsed;
        }
    }
    return $receiver;
}

function wallet_request_code() {
    if (isset($_REQUEST['code'])) {
        $c = trim((string)$_REQUEST['code']);
        if ($c !== '') {
            return $c;
        }
    }
    if (isset($_POST['wallet_code'])) {
        $c = trim((string)$_POST['wallet_code']);
        if ($c !== '') {
            return $c;
        }
    }
    return wallet_코드_쿠키_읽기();
}

function wallet_append_fresh_payload(array &$result, $req_code, $nick) {
    $fresh = wallet_auth($req_code);
    if ($fresh) {
        $result['member'] = wallet_member_payload($fresh, $nick);
    }
    $result['today'] = wallet_today_summary($nick);
}

function wallet_code_query($code) {
    $code = trim((string)$code);
    return $code !== '' ? '?code=' . rawurlencode($code) : '';
}

function wallet_api_load($nick = '', $with_functions = true) {
    if ($with_functions) {
        wallet_odd_even_includes();
    } else {
        wallet_db_boot();
    }
    include_once __DIR__ . '/swap.inc.php';
    include_once __DIR__ . '/wallet_bootstrap.inc.php';
    global $두자리닉넴;
    $두자리닉넴 = trim((string)$nick);
}

function wallet_point_rank($point) {
    if (function_exists('계급')) {
        return 계급($point);
    }
    $point = (int)$point;
    if ($point < 1000000) return ['rank' => 5, 'name' => '🌾토끼'];
    if ($point < 2000000) return ['rank' => 12, 'name' => '🏪참새'];
    if ($point < 5000000) return ['rank' => 25, 'name' => '🔨다람'];
    if ($point < 8000000) return ['rank' => 50, 'name' => '📜개구'];
    if ($point < 10000000) return ['rank' => 75, 'name' => '🎓고슴'];
    if ($point < 15000000) return ['rank' => 100, 'name' => '📖여우'];
    if ($point < 20000000) return ['rank' => 125, 'name' => '✍️사슴'];
    if ($point < 40000000) return ['rank' => 162, 'name' => '📝수달'];
    if ($point < 60000000) return ['rank' => 200, 'name' => '🪶담비'];
    if ($point < 80000000) return ['rank' => 225, 'name' => '📋박쥐'];
    if ($point < 100000000) return ['rank' => 275, 'name' => '⚖️표범'];
    if ($point < 200000000) return ['rank' => 325, 'name' => '🏘️사자'];
    if ($point < 300000000) return ['rank' => 375, 'name' => '📜호랑'];
    if ($point < 400000000) return ['rank' => 425, 'name' => '🎖️불곰'];
    if ($point < 500000000) return ['rank' => 475, 'name' => '📯판다'];
    if ($point < 600000000) return ['rank' => 525, 'name' => '🏛️기린'];
    if ($point < 700000000) return ['rank' => 600, 'name' => '🏯코끼'];
    if ($point < 800000000) return ['rank' => 675, 'name' => '👔하마'];
    if ($point < 900000000) return ['rank' => 750, 'name' => '🔱코뿔'];
    if ($point < 1000000000) return ['rank' => 850, 'name' => '🦁악어'];
    if ($point < 3000000000) return ['rank' => 950, 'name' => '🌟고래'];
    if ($point < 5000000000) return ['rank' => 1050, 'name' => '⚔️상어'];
    if ($point < 10000000000) return ['rank' => 1175, 'name' => '🎋돌고'];
    if ($point < 30000000000) return ['rank' => 1300, 'name' => '🔯펭귄'];
    if ($point < 50000000000) return ['rank' => 1450, 'name' => '👸문어'];
    if ($point < 90000000000) return ['rank' => 1625, 'name' => '🐉오징'];
    if ($point < 200000000000) return ['rank' => 1825, 'name' => '👑낙지'];
    if ($point < 300000000000) return ['rank' => 2050, 'name' => '🌟참치'];
    if ($point < 500000000000) return ['rank' => 2300, 'name' => '🔱전복'];
    return ['rank' => 2500, 'name' => '👑해삼'];
}

function wallet_member_refresh($code) {
    wallet_퇴근_차단_여부_설정(false);
    if (!empty($GLOBALS['wallet_preauth']['row']) && !empty($GLOBALS['wallet_preauth']['code'])) {
        $pre = $GLOBALS['wallet_preauth'];
        $pre_code = (string)$pre['code'];
        if ($code === '' || $code === $pre_code) {
            $row = wallet_member_row_enrich($pre['row'], $pre_code);
            $nick = trim((string)($row['name'] ?? ''));
            if ($nick !== '' && function_exists('getTwoCharNick')) {
                $parsed = getTwoCharNick($nick);
                if ($parsed !== '') {
                    $nick = $parsed;
                }
            }
            if ($nick !== '') {
                // .퇴근: 가방 API는 허용 · 바로가기만 UI에서 숨김
                wallet_코드_쿠키_저장($pre_code);
                return ['row' => $row, 'nick' => $nick, 'code' => $pre_code];
            }
        }
    }

    wallet_odd_even_includes();

    foreach (wallet_code_candidates($code) as $candidate) {
        $row = wallet_game_auth_row($candidate);
        if (!$row) {
            continue;
        }
        $row = wallet_member_row_enrich($row, $candidate);
        $nick = trim((string)($row['name'] ?? ''));
        if ($nick !== '' && function_exists('getTwoCharNick')) {
            $parsed = getTwoCharNick($nick);
            if ($parsed !== '') {
                $nick = $parsed;
            }
        }
        if ($nick === '') {
            continue;
        }
        // .퇴근: 가방 API는 허용 · 바로가기만 UI에서 숨김
        wallet_코드_쿠키_저장($candidate);
        return ['row' => $row, 'nick' => $nick, 'code' => $candidate];
    }
    return null;
}

function wallet_buff_active($nick) {
    $nick_esc = addslashes(trim((string)$nick));
    $row = db_select("SELECT idx FROM tb_item_use WHERE nickname = '{$nick_esc}' AND (item = '지호' OR item = '마법') LIMIT 1");
    return !empty($row['idx']);
}

/** 프변 보유·프로필변경 적용 기간 */
function wallet_profile_change_payload($nick) {
    $nick = trim((string)$nick);
    $닉_esc = addslashes($nick);
    $owned = 0;
    $active = false;
    $enddate = null;
    $end_fmt = '';
    if ($닉_esc !== '') {
        $owned = function_exists('item_bag_qty_nick') ? item_bag_qty_nick($nick, '프변') : 0;
        $사용중 = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '프로필변경' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
        if (!empty($사용중['enddate'])) {
            $active = true;
            $enddate = (string)$사용중['enddate'];
            $end_fmt = date('m월 d일 H시', strtotime($enddate));
        }
    }
    return [
        'owned' => $owned,
        'active' => $active,
        'enddate' => $enddate,
        'end_fmt' => $end_fmt,
        'period_text' => $active ? ("적용 중 · {$end_fmt} 까지") : '미적용',
        'days_per_item' => 3,
    ];
}

/**
 * 프변 N개 사용 (1개=3일). .프변 채팅 명령과 동일 로직, 웹용 배열 반환.
 * 부족 시: 게임냥 자동구매 → 없으면 본방냥 20% 스왑 후 구매 → 사용.
 */
function wallet_profile_change_use($nick, $count = 1) {
    $nick = trim((string)$nick);
    $count = max(1, (int)$count);
    if ($nick === '') {
        return ['ok' => false, 'data' => '닉네임을 확인해주세요.'];
    }
    $닉_esc = addslashes($nick);

    $보유개수 = function_exists('item_bag_qty_nick') ? item_bag_qty_nick($nick, '프변') : 0;
    $자동문구 = '';
    if ($보유개수 < $count) {
        $부족 = $count - $보유개수;
        if (!function_exists('프변_부족분_자동구매')) {
            return ['ok' => false, 'data' => "프변 아이템 부족! (요청 {$count}개 · 보유 {$보유개수}개)"];
        }
        $자동 = 프변_부족분_자동구매($nick, $부족);
        if (empty($자동['ok'])) {
            return ['ok' => false, 'data' => (string)($자동['msg'] ?? "프변 아이템 부족! (요청 {$count}개 · 보유 {$보유개수}개)")];
        }
        $자동문구 = trim((string)($자동['msg'] ?? ''));
    }

    $차감 = item_bag_sub_nick($nick, '프변', $count);
    if (empty($차감['ok'])) {
        return ['ok' => false, 'data' => '프변 아이템 부족!'];
    }

    $연장일 = $count * 3;
    $사용중여부 = db_select("SELECT idx, enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '프로필변경' LIMIT 1");
    $연장중 = !empty($사용중여부['idx']);

    if ($연장중) {
        $유효시간 = date('Y-m-d H:i:s', strtotime($사용중여부['enddate'] . " +{$연장일} days"));
        db_query("UPDATE tb_item_use SET enddate = '{$유효시간}' WHERE idx = " . (int)$사용중여부['idx']);
    } else {
        $유효시간 = date('Y-m-d H:i:s', strtotime("+{$연장일} days"));
        db_query("INSERT INTO tb_item_use SET nickname = '{$닉_esc}', item = '프로필변경', enddate = '{$유효시간}', regdate = NOW()");
    }

    if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락('프변', $count);
    }

    $profile = wallet_profile_change_payload($nick);
    $만료표시 = $profile['end_fmt'] !== '' ? $profile['end_fmt'] : date('m월 d일 H시', strtotime($유효시간));
    if ($연장중) {
        $msg = $count > 1
            ? "✅ 프로필변경 {$연장일}일 연장! (프변 {$count}개)\n만료: {$만료표시}"
            : "✅ 프로필변경 3일 연장!\n만료: {$만료표시}";
    } else {
        $msg = $count > 1
            ? "✅ 프로필변경 적용! (프변 {$count}개 · {$연장일}일)\n만료: {$만료표시}"
            : "✅ 프로필변경 적용! (3일)\n만료: {$만료표시}";
    }
    if ($자동문구 !== '') {
        $msg = $자동문구 . "\n\n" . $msg;
    }

    return [
        'ok' => true,
        'data' => $msg,
        'profile' => $profile,
        'extended' => $연장중,
        'days' => $연장일,
    ];
}

/** 버프 항목 1개 상태 */
function wallet_buff_item_payload($nick, $item, $fmt = 'm-d H:i') {
    $닉_esc = addslashes(trim((string)$nick));
    $item_esc = addslashes($item);
    $active = false;
    $enddate = null;
    $end_fmt = '';
    $status_text = '미적용';
    if ($닉_esc !== '' && $item_esc !== '') {
        $row = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '{$item_esc}' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
        if (!empty($row['enddate'])) {
            $active = true;
            $enddate = (string)$row['enddate'];
            $end_fmt = date($fmt, strtotime($enddate));
            $status_text = "적용 중 · {$end_fmt} 까지";
        }
    }
    return [
        'item' => $item,
        'active' => $active,
        'enddate' => $enddate,
        'end_fmt' => $end_fmt,
        'status_text' => $status_text,
    ];
}

/** 내 버프 현황 (+ 지호 보유) */
function wallet_buff_payload($nick) {
    $nick = trim((string)$nick);
    $닉_esc = addslashes($nick);
    $profile = wallet_buff_item_payload($nick, '프로필변경', 'm월 d일 H시');
    $jiho = wallet_buff_item_payload($nick, '지호', 'd일 H:i');
    $magic = wallet_buff_item_payload($nick, '마법', 'd일 H:i');
    $jiho_owned = 0;
    $profile_owned = 0;
    if ($nick !== '') {
        $jiho_owned = function_exists('item_bag_qty_nick')
            ? item_bag_qty_nick($nick, '지호')
            : (int)((db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '지호' AND status = 0")['cnt'] ?? 0));
        $profile_owned = function_exists('item_bag_qty_nick')
            ? item_bag_qty_nick($nick, '프변')
            : 0;
    }
    $profile = array_merge($profile, ['owned' => $profile_owned, 'days_per_item' => 3]);
    $has_any = !empty($profile['active']) || !empty($jiho['active']) || !empty($magic['active']);
    return [
        'profile' => $profile,
        'jiho' => array_merge($jiho, ['owned' => $jiho_owned, 'hours_per_item' => 1]),
        'magic' => $magic,
        'has_any' => $has_any,
        'show_jiho_use' => !$has_any,
    ];
}

/**
 * 지호 N개 사용 (1개=1시간). .지호 채팅 명령과 동일 로직.
 */
function wallet_jiho_use($nick, $count = 1) {
    $nick = trim((string)$nick);
    $count = max(1, (int)$count);
    if ($nick === '') {
        return ['ok' => false, 'data' => '닉네임을 확인해주세요.'];
    }
    $닉_esc = addslashes($nick);

    $전체메시지행 = db_select('SELECT COUNT(*) AS cnt FROM tb_msg');
    $전체행수 = (int)($전체메시지행['cnt'] ?? 0);
    if ($전체행수 < 100) {
        return ['ok' => false, 'data' => "누적 타수 100 미만에서는 지호를 사용할 수 없습니다. (현재 {$전체행수}타)"];
    }

    $보유개수 = function_exists('item_bag_qty_nick')
        ? item_bag_qty_nick($nick, '지호')
        : 0;
    if ($보유개수 < $count) {
        return ['ok' => false, 'data' => "지호 아이템 부족! (요청 {$count}개 · 보유 {$보유개수}개)"];
    }

    if (!function_exists('item_bag_sub_nick')) {
        return ['ok' => false, 'data' => '아이템 가방 기능을 불러올 수 없어요.'];
    }
    $차감 = item_bag_sub_nick($nick, '지호', $count);
    if (empty($차감['ok'])) {
        return ['ok' => false, 'data' => '지호 아이템 없음!'];
    }
    if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락('지호', $count);
    }

    $추가시간 = $count;
    $사용중여부 = db_select("SELECT idx, enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '지호' LIMIT 1");
    $연장중 = !empty($사용중여부['idx']);
    if ($연장중) {
        $유효시간 = date('Y-m-d H:i', strtotime($사용중여부['enddate'] . " +{$추가시간} hours"));
        db_query("UPDATE tb_item_use SET enddate = '{$유효시간}' WHERE idx = " . (int)$사용중여부['idx']);
        $msg = "✅ 지호 {$count}개 적용\n" . date('m-d H:i', strtotime($유효시간)) . ' 까지';
    } else {
        $유효시간 = date('Y-m-d H:i', strtotime("+{$추가시간} hours"));
        db_query("INSERT INTO tb_item_use SET nickname = '{$닉_esc}', item = '지호', enddate = '{$유효시간}', regdate = NOW()");
        $msg = "✅ 지호 {$count}개 사용\n" . date('m-d H:i', strtotime($유효시간)) . ' 까지';
    }

    return [
        'ok' => true,
        'data' => $msg,
        'buff' => wallet_buff_payload($nick),
        'extended' => $연장중,
        'hours' => $추가시간,
    ];
}

/** 본방 채팅 알림 큐 (info1.php 등에서 status=0 건 전송) */
function wallet_본방알림_등록($msg, $nick) {
    $msg = trim((string)$msg);
    $nick = trim((string)$nick);
    if ($msg === '') {
        return false;
    }
    $msg_esc = addslashes($msg);
    $item_esc = addslashes('wallet_' . ($nick !== '' ? $nick : 'system'));
    $ok = (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
    if ($ok && function_exists('본방알림_대기_최신만')) {
        본방알림_대기_최신만();
    }
    return $ok;
}

/** 가방에서 출근 처리 (.출근 과 동일) + 본방 알림 */
function wallet_출근_실행($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'data' => '닉네임을 확인해주세요.'];
    }
    if (function_exists('getTwoCharNick')) {
        $parsed = getTwoCharNick($nick);
        if ($parsed !== '') {
            $nick = $parsed;
        }
    }
    $esc = addslashes($nick);
    $mem = @db_select("SELECT idx, name FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (empty($mem['idx'])) {
        return ['ok' => false, 'data' => '존재하지 않는 사용자입니다.'];
    }

    // status=3 복구
    @db_query("UPDATE tb_member SET status = 0 WHERE name = '{$esc}' LIMIT 1");

    $work = @db_select("SELECT * FROM tb_work WHERE nick = '{$esc}' LIMIT 1");
    $wasOff = !empty($work['idx']);
    $자숙연장문구 = '';
    if ($wasOff) {
        if (function_exists('회원_출근_채굴재개')) {
            회원_출근_채굴재개($nick);
        }
        if (function_exists('출근_자숙_퇴근기간연장') && is_array($work)) {
            $연장 = 출근_자숙_퇴근기간연장($nick, $work);
            if (!empty($연장['msg'])) {
                $자숙연장문구 = (string)$연장['msg'];
            }
        }
        @db_query("DELETE FROM tb_work WHERE nick = '{$esc}' LIMIT 1");
    }

    $알림 = "☀️ {$nick} 출근했어요! 어서와!!🎉";
    if ($자숙연장문구 !== '') {
        $알림 .= "\n" . $자숙연장문구;
    }
    if (function_exists('본방알림_등록')) {
        본방알림_등록($알림, 'wallet_출근');
    } else {
        wallet_본방알림_등록($알림, $nick);
    }

    $msg = $wasOff
        ? "{$nick} 출근 완료! 본방으로 이동합니다."
        : "{$nick} 이미 출근 상태예요. 본방으로 이동합니다.";

    $urls = wallet_room_urls();
    return [
        'ok' => true,
        'data' => $msg,
        'already' => !$wasOff,
        'bonbang_url' => (string)($urls['본방'] ?? 'https://open.kakao.com/o/pIe2XDJi'),
    ];
}

/** 긴급알람(당근) 쿨타임 초 */
if (!defined('WALLET_EMERGENCY_ALARM_COOLDOWN')) {
    define('WALLET_EMERGENCY_ALARM_COOLDOWN', 300);
}

/** 긴급알람 허용 이모티콘 (기본 🥕) */
function wallet_emergency_alarm_emojis() {
    return ['🥕', '🆘', '📢', '🚨', '🐱', '😭'];
}

/** 긴급알람 — 오치소/채팅불가 시 공창 호출 (days: 1·3·5·7·14·30·60) */
function wallet_emergency_alarm_execute($nick, $days = 3, $emoji = '🥕') {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['ok' => false, 'data' => '닉네임을 확인해주세요.'];
    }

    $allowed = [1, 3, 5, 7, 14, 30, 60];
    $days = (int)$days;
    if (!in_array($days, $allowed, true)) {
        return ['ok' => false, 'data' => '기간을 선택해주세요. (1·3·5·7·14·30·60일)'];
    }

    $emoji = trim((string)$emoji);
    $emojis = wallet_emergency_alarm_emojis();
    if ($emoji === '' || !in_array($emoji, $emojis, true)) {
        $emoji = '🥕';
    }

    $cooldown = (int)WALLET_EMERGENCY_ALARM_COOLDOWN;
    if ($cooldown < 60) {
        $cooldown = 60;
    }
    $item_esc = addslashes('wallet_emergency_' . $nick);
    $recent = @db_select(
        "SELECT regdate FROM tb_lotto_info
         WHERE item = '{$item_esc}'
         ORDER BY idx DESC LIMIT 1"
    );
    if (is_array($recent) && !empty($recent['regdate'])) {
        $elapsed = time() - strtotime((string)$recent['regdate']);
        if ($elapsed >= 0 && $elapsed < $cooldown) {
            $remain = $cooldown - $elapsed;
            $min = (int)ceil($remain / 60);
            return ['ok' => false, 'data' => "긴급알람은 {$min}분 후에 다시 보낼 수 있어요."];
        }
    }

    $pair = $emoji . $emoji;
    $line = $emoji . $emoji . $emoji . $emoji . $emoji;
    $msg = "{$pair}{$nick}{$pair}\n나 {$days}일 오치소감ㅜㅜ\n{$line}";
    $msg_esc = addslashes($msg);
    $ok = (bool)@db_query(
        "INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()"
    );
    if (!$ok) {
        return ['ok' => false, 'data' => '긴급알람 전송에 실패했어요. 잠시 후 다시 시도해주세요.'];
    }

    return [
        'ok' => true,
        'data' => "공창에 {$days}일 긴급알람을 보냈어요 {$emoji}",
        'msg' => $msg,
        'days' => $days,
        'emoji' => $emoji,
    ];
}

function wallet_swap_quote_payload($direction, $amount, $member) {
    $보유_np = round((float)($member['newpoint'] ?? 0), 1);
    $보유_pt = null;
    $idx = (int)($member['idx'] ?? 0);
    $nick = trim((string)($member['name'] ?? ''));
    // idx 우선 — CAST로 해 단위 게임냥 정밀도 보장 (.환율과 동일 총량 기준)
    if ($idx > 0) {
        $pt행 = @db_select("SELECT CAST(point AS CHAR) AS point, newpoint FROM tb_member WHERE idx = {$idx} LIMIT 1");
        if (is_array($pt행)) {
            $보유_np = round((float)($pt행['newpoint'] ?? $보유_np), 1);
            $보유_pt = function_exists('냥_정수문자열')
              ? 냥_정수문자열($pt행['point'] ?? 0)
              : (string)($pt행['point'] ?? 0);
        }
    }
    if ($보유_pt === null && $nick !== '') {
        $닉_esc = addslashes($nick);
        $pt행 = @db_select("SELECT CAST(point AS CHAR) AS point, newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        if (is_array($pt행)) {
            $보유_np = round((float)($pt행['newpoint'] ?? $보유_np), 1);
            $보유_pt = function_exists('냥_정수문자열')
              ? 냥_정수문자열($pt행['point'] ?? 0)
              : (string)($pt행['point'] ?? 0);
        }
    }
    if ($보유_pt === null) {
        $보유_pt = function_exists('냥_정수문자열')
          ? 냥_정수문자열($member['point'] ?? 0)
          : (string)($member['point'] ?? 0);
    }

    if ($direction === 'np2pt') {
        $전액 = ($amount === null || $amount === '');
        $금액 = $전액 ? $보유_np : (float)$amount;
        // 가방 본방→게임: 500냥 최소 제한 없음
        return 스왑_견적계산('np2pt', $금액, $전액, null, false);
    }
    if ($direction === 'pt2np') {
        $전액 = ($amount === null || $amount === '');
        if ($전액) {
            $견적 = 스왑_견적계산('pt2np', $보유_pt, true, null, false);
            $최대 = function_exists('스왑_게임냥으로_최대본방냥')
              ? 스왑_게임냥으로_최대본방냥($보유_pt)
              : (float)($견적['지급_np'] ?? 0);
            if (!empty($견적['ok'])) {
                $견적['최대_np'] = $최대;
                $견적['최대_np_fmt'] = wallet_fmt_new($최대);
                $견적['입력기준'] = '전액';
                return $견적;
            }
            // 견적 실패 시에도 최대 수령액은 안내
            return [
                'ok' => false,
                'msg' => ($견적['msg'] ?? '❌ 스왑 견적을 계산할 수 없어요.')
                  . ($최대 > 0 ? "\n(전액 기준 최대 약 " . wallet_fmt_new($최대) . '냥)' : ''),
                '최대_np' => $최대,
            ];
        }
        // 지갑 UI: 입력 = 받을 본방냥(실수령) → 게임냥 20% 수수료 포함 역산
        $원하는_np = round((float)$amount, 1);
        $최소_np = function_exists('스왑_게임방_최소본방냥') ? (float)스왑_게임방_최소본방냥() : 500.0;
        if ($원하는_np < $최소_np) {
            return [
                'ok' => false,
                'msg' => '❌ 받을 본방냥은 ' . (function_exists('newpoint표시') ? newpoint표시($최소_np) : (string)(int)$최소_np) . '냥 이상이어야 해요.',
            ];
        }
        if (!function_exists('스왑_원하는본방냥_필요게임냥')) {
            return ['ok' => false, 'msg' => '❌ 스왑 계산 기능을 불러올 수 없어요.'];
        }
        $필요 = 스왑_원하는본방냥_필요게임냥($원하는_np, $보유_pt);
        if (empty($필요['ok'])) {
            return $필요;
        }
        $금액 = $필요['차감_pt'];
        $견적 = 스왑_견적계산('pt2np', $금액, false, null, false);
        if (empty($견적['ok'])) {
            return ['ok' => false, 'msg' => $견적['msg'] ?? '❌ 스왑 견적을 계산할 수 없어요.'];
        }
        $견적['원하는_np'] = $원하는_np;
        $견적['원하는_np_fmt'] = wallet_fmt_new($원하는_np);
        $견적['입력기준'] = '본방냥';
        if (function_exists('스왑_게임냥으로_최대본방냥')) {
            $견적['최대_np'] = 스왑_게임냥으로_최대본방냥($보유_pt);
            $견적['최대_np_fmt'] = wallet_fmt_new($견적['최대_np']);
        }
        return $견적;
    }
    return ['ok' => false, 'msg' => '잘못된 스왑 방향입니다.'];
}

function wallet_swap_np_execute($nick, $amount_raw) {
    $닉_esc = addslashes($nick);
    $회원 = db_select("SELECT newpoint, CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np < 0.1) {
        return ['ok' => false, 'data' => '스왑할 본방냥이 없어요.'];
    }

    $전액 = ($amount_raw === null || $amount_raw === '');
    $금액 = $전액 ? $보유_np : (float)$amount_raw;
    // 가방 본방→게임: 500냥 최소 제한 없음
    $견적 = 스왑_견적계산('np2pt', $금액, $전액, null, false);
    if (empty($견적['ok'])) {
        return ['ok' => false, 'data' => $견적['msg'] ?? '스왑 견적을 계산할 수 없습니다.'];
    }

    $차감_np = (float)$견적['차감_np'];
    $지급_pt = function_exists('냥_정수문자열')
      ? 냥_정수문자열($견적['지급_pt'] ?? 0)
      : (string)($견적['지급_pt'] ?? 0);
    if ($보유_np < $차감_np) {
        return ['ok' => false, 'data' => '본방냥이 부족합니다.'];
    }

    if (function_exists('스왑_point_컬럼_보장')) {
        스왑_point_컬럼_보장();
    } elseif (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
    }
    db_query("UPDATE tb_member SET newpoint = newpoint - {$차감_np}, point = point + {$지급_pt} WHERE name = '{$닉_esc}' LIMIT 1");
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $스왑알림 = "💱 내 가방 스왑 (본방냥 → 게임냥)\n"
        . "{$nick}님\n"
        . '-' . wallet_fmt_new($차감_np) . " 본방냥\n"
        . "  └ 삭제 " . wallet_fmt_new($삭제_np) . " · 교환 " . wallet_fmt_new($교환_np) . "\n"
        . '+' . wallet_fmt_game($지급_pt) . ' 게임냥';
    if (function_exists('info2알림_등록')) {
        info2알림_등록($스왑알림, 'wallet_' . $nick);
    } else {
        wallet_본방알림_등록($스왑알림, $nick);
    }
    return [
        'ok' => true,
        'data' => '본방냥 ' . wallet_fmt_new($차감_np) . '냥 → 게임냥 ' . wallet_fmt_game($지급_pt) . '냥 스왑 완료 (삭제 ' . wallet_fmt_new($삭제_np) . '냥)',
    ];
}

function wallet_swap_pt_execute($nick, $amount_raw = null) {
    $닉_esc = addslashes($nick);
    $회원 = db_select("SELECT newpoint, CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $보유_pt = function_exists('냥_정수문자열')
      ? 냥_정수문자열($회원['point'] ?? 0)
      : (string)($회원['point'] ?? 0);
    $보유부족 = function_exists('bccomp')
      ? bccomp($보유_pt, '1', 0) < 0
      : ((int)$보유_pt < 1);
    if ($보유부족) {
        return ['ok' => false, 'data' => '스왑할 게임냥이 없습니다.'];
    }

    $전액 = ($amount_raw === null || $amount_raw === '');
    if ($전액) {
        $금액 = $보유_pt;
    } else {
        // 받을 본방냥(실수령) 기준 → 필요 게임냥 역산 (게임냥 20% 수수료 포함)
        $원하는_np = round((float)$amount_raw, 1);
        $최소_np = function_exists('스왑_게임방_최소본방냥') ? (float)스왑_게임방_최소본방냥() : 500.0;
        if ($원하는_np < $최소_np) {
            return ['ok' => false, 'data' => '받을 본방냥은 ' . wallet_fmt_new($최소_np) . '냥 이상 입력해 주세요.'];
        }
        if (!function_exists('스왑_원하는본방냥_필요게임냥')) {
            return ['ok' => false, 'data' => '스왑 계산 기능을 불러올 수 없어요.'];
        }
        $필요 = 스왑_원하는본방냥_필요게임냥($원하는_np, $보유_pt);
        if (empty($필요['ok'])) {
            return ['ok' => false, 'data' => $필요['msg'] ?? '스왑 금액을 계산할 수 없습니다.'];
        }
        $금액 = $필요['차감_pt'];
    }

    $견적 = 스왑_견적계산('pt2np', $금액, $전액, null, false);
    if (empty($견적['ok'])) {
        return ['ok' => false, 'data' => $견적['msg'] ?? '스왑 견적을 계산할 수 없습니다.'];
    }

    $차감_pt = function_exists('냥_정수문자열') ? 냥_정수문자열($견적['차감_pt'] ?? 0) : (string)($견적['차감_pt'] ?? 0);
    $지급_np = (float)$견적['지급_np'];
    $수수료_pt = function_exists('냥_정수문자열') ? 냥_정수문자열($견적['수수료_pt'] ?? 0) : (string)($견적['수수료_pt'] ?? 0);
    $교환_pt = function_exists('냥_정수문자열') ? 냥_정수문자열($견적['교환_pt'] ?? 0) : (string)($견적['교환_pt'] ?? 0);

    if (function_exists('스왑_point_컬럼_보장')) {
        스왑_point_컬럼_보장();
    } elseif (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
    }
    db_query("UPDATE tb_member SET point = point - {$차감_pt}, newpoint = newpoint + {$지급_np} WHERE name = '{$닉_esc}' AND point >= {$차감_pt} LIMIT 1");
    global $conn;
    if (($conn instanceof mysqli) && (int)mysqli_affected_rows($conn) <= 0) {
        return ['ok' => false, 'data' => '스왑 처리에 실패했어요. 보유 게임냥을 확인해 주세요.'];
    }
    $스왑알림 = "💱 내 가방 스왑 (게임냥 → 본방냥)\n"
        . "{$nick}님\n"
        . '-' . wallet_fmt_game($차감_pt) . " 게임냥\n"
        . "  └ 수수료 " . wallet_fmt_game($수수료_pt) . " · 교환 " . wallet_fmt_game($교환_pt) . "\n"
        . '+' . wallet_fmt_new($지급_np) . ' 본방냥';
    // 홍보방(info2) 알림 큐로 전송
    if (function_exists('info2알림_등록')) {
        info2알림_등록($스왑알림, 'wallet_' . $nick);
    } else {
        wallet_본방알림_등록($스왑알림, $nick);
    }
    return [
        'ok' => true,
        'data' => '게임냥 ' . wallet_fmt_game($차감_pt) . '냥 → 본방냥 ' . wallet_fmt_new($지급_np) . '냥 스왑 완료 (수수료 ' . wallet_fmt_game($수수료_pt) . '냥)',
    ];
}

function wallet_transfer_np_execute($nick, $receiver, $amount) {
    $receiver = wallet_resolve_receiver($receiver);
    $amount = (int)$amount;
    if ($receiver === '' || $amount < 1) {
        return ['ok' => false, 'data' => '받는 사람과 금액을 확인해주세요.'];
    }
    if ($receiver === $nick) {
        return ['ok' => false, 'data' => '본인에게는 양도할 수 없습니다.'];
    }

    $양도제한 = 양도_일일제한_검사($nick);
    if ($양도제한 !== null) {
        return ['ok' => false, 'data' => $양도제한];
    }

    $신입양도제한 = function_exists('신입_본방냥양도_검사') ? 신입_본방냥양도_검사($nick) : null;
    if ($신입양도제한 !== null) {
        return ['ok' => false, 'data' => $신입양도제한];
    }

    $지호소비 = 양도_지호추가양도_해당($nick);

    $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '" . addslashes($receiver) . "' LIMIT 1");
    if (empty($받는친구['idx'])) {
        return ['ok' => false, 'data' => "[ {$receiver} ] 회원을 찾을 수 없습니다."];
    }

    $닉_esc = addslashes($nick);
    $정보 = db_select("SELECT newpoint, level FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($정보)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $수수료 = 양도수수료계산((int)($정보['level'] ?? 0), $amount);
    $보유냥 = newpoint양도가능($정보['newpoint'] ?? 0);
    if ($보유냥 <= 0) {
        return ['ok' => false, 'data' => '양도 가능한 본방냥이 없습니다.'];
    }
    if ($보유냥 < $amount) {
        return ['ok' => false, 'data' => '본방냥이 부족합니다. (양도액 필요)'];
    }

    $수수료제외 = $amount - $수수료;
    if ($수수료제외 < 1) {
        return ['ok' => false, 'data' => '양도 금액이 너무 적습니다.'];
    }
    $받는_esc = addslashes($receiver);
    db_query("UPDATE tb_member SET newpoint = newpoint - {$amount} WHERE name = '{$닉_esc}' LIMIT 1");
    db_query("UPDATE tb_member SET newpoint = newpoint + {$수수료제외} WHERE name = '{$받는_esc}' LIMIT 1");
    db_query("UPDATE config SET tax = tax + {$수수료}");
    if ($지호소비) {
        $지호차감문구 = 양도_추가양도_소비($nick);
    } else {
        $지호차감문구 = '';
    }
    지급로그('양도', $nick, $receiver, $수수료, $amount);
    지급로그('수수료', $nick, '', $수수료, $수수료);
    $보낸표시 = function_exists('newpoint표시') ? newpoint표시($amount) : (function_exists('냥축약표시') ? 냥축약표시($amount) : number_format($amount) . '냥');
    $실수령표시 = function_exists('newpoint표시') ? newpoint표시($수수료제외) : (function_exists('냥축약표시') ? 냥축약표시($수수료제외) : number_format($수수료제외) . '냥');
    $수수료표시 = function_exists('newpoint표시') ? newpoint표시($수수료) : (function_exists('냥축약표시') ? 냥축약표시($수수료) : $수수료 . '냥');
    $결과문구 = "{$nick} → {$receiver} 본방냥 {$실수령표시} (수수료 {$수수료표시})";
    if ($지호차감문구 !== '') {
        $결과문구 .= "\n" . $지호차감문구;
    }

    $알림 = "💰💰💰💰양도 알림💰💰💰💰\n보낸이 : {$nick} {$보낸표시} (수수료 {$수수료표시})\n받는이 : {$receiver} {$실수령표시}";
    if ($지호차감문구 !== '') {
        $알림 .= "\n" . $지호차감문구;
    }
    wallet_본방알림_등록($알림, $nick);

    return [
        'ok' => true,
        'data' => $결과문구,
    ];
}

/**
 * 양도 수수료 1% (반올림) — 문자열/bcmath · (int) 금지
 */
function wallet_transfer_fee_string($amount): string {
    if (function_exists('yangdo_수수료_문자열')) {
        return yangdo_수수료_문자열($amount);
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($amount) : (ltrim(preg_replace('/[^\d]/', '', (string)$amount), '0') ?: '0');
    if ($s === '0') {
        return '0';
    }
    if (function_exists('bcadd') && function_exists('bcdiv')) {
        return bcdiv(bcadd($s, '50', 0), '100', 0);
    }
    if (strlen($s) <= 15) {
        return (string)(int)round((float)$s * 0.01);
    }
    $len = strlen($s);
    if ($len <= 2) {
        return ((int)$s >= 50) ? '1' : '0';
    }
    $head = substr($s, 0, $len - 2);
    $tail = (int)substr($s, -2);
    if ($tail < 50) {
        return ltrim($head, '0') ?: '0';
    }
    if (function_exists('bcadd')) {
        return bcadd(ltrim($head, '0') ?: '0', '1', 0);
    }
    return (string)((int)(ltrim($head, '0') ?: '0') + 1);
}

/** @return string 정수 문자열 */
function wallet_transfer_amount_string($raw): string {
    if (function_exists('냥_금액_파싱_문자열')) {
        return 냥_금액_파싱_문자열((string)$raw);
    }
    return function_exists('냥_정수문자열') ? 냥_정수문자열($raw) : (ltrim(preg_replace('/[^\d]/', '', (string)$raw), '0') ?: '0');
}

function wallet_transfer_pt_execute($nick, $receiver, $amount_raw) {
    $receiver = wallet_resolve_receiver($receiver);
    $amount = wallet_transfer_amount_string($amount_raw);
    if ($receiver === '' || $amount === '0') {
        return ['ok' => false, 'data' => '받는 사람과 금액을 확인해주세요.'];
    }
    $minOk = function_exists('bccomp')
        ? (bccomp($amount, '30000', 0) >= 0)
        : (strlen($amount) > 5 || (strlen($amount) === 5 && $amount >= '30000'));
    if (!$minOk) {
        return ['ok' => false, 'data' => '게임냥 양도는 30,000냥 이상만 가능합니다.'];
    }
    if ($receiver === $nick) {
        return ['ok' => false, 'data' => '본인에게는 양도할 수 없습니다.'];
    }

    $양도제한 = 양도_일일제한_검사($nick);
    if ($양도제한 !== null) {
        return ['ok' => false, 'data' => $양도제한];
    }

    $지호소비 = 양도_지호추가양도_해당($nick);

    $받는친구 = db_select("SELECT idx FROM tb_member WHERE name = '" . addslashes($receiver) . "' LIMIT 1");
    if (empty($받는친구['idx'])) {
        return ['ok' => false, 'data' => "[ {$receiver} ] 회원을 찾을 수 없습니다."];
    }

    $닉_esc = addslashes($nick);
    if (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
    }
    $정보 = db_select("
      SELECT CONCAT('N', CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR)) AS point, level
      FROM tb_member
      WHERE name = '{$닉_esc}'
      LIMIT 1
    ");
    if (empty($정보)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $게임냥 = function_exists('냥_금액원문_정규화')
        ? 냥_금액원문_정규화($정보['point'] ?? 'N0')
        : wallet_transfer_amount_string($정보['point'] ?? '0');
    $보유부족 = function_exists('bccomp')
        ? (bccomp($게임냥, $amount, 0) < 0)
        : (strlen($게임냥) < strlen($amount) || (strlen($게임냥) === strlen($amount) && $게임냥 < $amount));
    if ($보유부족) {
        return ['ok' => false, 'data' => '게임냥이 부족합니다. (보유 ' . wallet_fmt_game($게임냥) . '냥)'];
    }

    $수수료 = wallet_transfer_fee_string($amount);
    if (function_exists('bcsub') && function_exists('bccomp')) {
        $수수료제외 = bcsub($amount, $수수료, 0);
        if ($수수료제외 === null || $수수료제외 === '' || bccomp($수수료제외, '0', 0) < 0) {
            $수수료제외 = '0';
        }
    } else {
        // 문자열 뺄셈 (큰 금액 (int) 금지)
        $aRev = strrev($amount);
        $bRev = strrev($수수료);
        $len = max(strlen($aRev), strlen($bRev));
        $borrow = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $da = $i < strlen($aRev) ? (int)$aRev[$i] : 0;
            $db = $i < strlen($bRev) ? (int)$bRev[$i] : 0;
            $v = $da - $db - $borrow;
            if ($v < 0) {
                $v += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out .= (string)$v;
        }
        $수수료제외 = ($borrow > 0) ? '0' : (ltrim(strrev($out), '0') ?: '0');
    }
    $수령부족 = function_exists('bccomp')
        ? (bccomp($수수료제외, '1', 0) < 0)
        : ($수수료제외 === '0' || (strlen($수수료제외) === 1 && $수수료제외 < '1'));
    if ($수령부족) {
        return ['ok' => false, 'data' => '양도 금액이 너무 적습니다.'];
    }

    $amount_sql = preg_match('/^\d+$/', $amount) ? $amount : '0';
    $수수료제외_sql = preg_match('/^\d+$/', $수수료제외) ? $수수료제외 : '0';
    $수수료_sql = function_exists('지급로그_금액_SQL') ? 지급로그_금액_SQL($수수료) : (preg_match('/^\d+$/', $수수료) ? $수수료 : '0');
    $받는_esc = addslashes($receiver);
    $ok_send = db_query("
      UPDATE tb_member
      SET point = point - {$amount_sql}
      WHERE name = '{$닉_esc}' AND point >= {$amount_sql}
      LIMIT 1
    ");
    if ($ok_send === false) {
        return ['ok' => false, 'data' => '게임냥 차감에 실패했어요. 잠시 후 다시 시도해주세요.'];
    }
    db_query("UPDATE tb_member SET point = point + {$수수료제외_sql} WHERE name = '{$받는_esc}' LIMIT 1");
    db_query("UPDATE config SET tax = tax + {$수수료_sql}");
    if ($지호소비) {
        $지호차감문구 = 양도_추가양도_소비($nick);
    } else {
        $지호차감문구 = '';
    }
    지급로그('양도', $nick, $receiver, $수수료, $amount);
    지급로그('수수료', $nick, '', $수수료, $수수료);
    $결과문구 = "{$nick} → {$receiver} 게임냥 " . wallet_fmt_game($수수료제외) . "냥 (수수료 " . wallet_fmt_game($수수료) . "냥)";
    if ($지호차감문구 !== '') {
        $결과문구 .= "\n" . $지호차감문구;
    }

    return [
        'ok' => true,
        'data' => $결과문구,
    ];
}

/**
 * 냥 주고받은 내역 (양도 + 마피아적발 보상)
 * point=보낸/받은 총액 · tax=수수료(양도) 또는 지급전 본방냥(마피아적발)
 * 금액은 전부 문자열 — (int)/(float) 캐스팅 금지 (922경·해 단위 잘림 방지)
 * @return list<array<string,mixed>>
 */
function wallet_transfer_history($nick, $limit = 40): array {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return [];
    }
    if (function_exists('지급로그_컬럼_보장')) {
        지급로그_컬럼_보장();
    }
    $nick_esc = addslashes($nick);
    $limit = max(1, min(100, (int)$limit));
    $toStr = static function ($v): string {
        if (function_exists('냥_금액원문_정규화')) {
            return 냥_금액원문_정규화($v);
        }
        if (function_exists('냥_정수문자열')) {
            return 냥_정수문자열($v);
        }
        $s = trim((string)$v);
        if (isset($s[0]) && ($s[0] === 'N' || $s[0] === 'n')) {
            $s = substr($s, 1);
        }
        if (isset($s[0]) && $s[0] === '-') {
            $s = substr($s, 1);
        }
        return ltrim(preg_replace('/[^\d]/', '', $s), '0') ?: '0';
    };
    $fmt = static function ($v) use ($toStr): string {
        $s = $toStr($v);
        if (function_exists('wallet_fmt_game')) {
            return wallet_fmt_game($s);
        }
        if (function_exists('랭킹_게임냥표시')) {
            return 랭킹_게임냥표시($s, '');
        }
        if (function_exists('냥축약표시')) {
            return 냥축약표시($s, '');
        }
        if (function_exists('newpoint표시')) {
            return newpoint표시($s);
        }
        if (function_exists('냥_숫자콤마')) {
            return 냥_숫자콤마($s);
        }
        return $s;
    };
    $fmtNp = static function ($v) use ($toStr): string {
        $s = $toStr($v);
        if (function_exists('newpoint표시')) {
            return newpoint표시($s);
        }
        if (function_exists('냥_숫자콤마')) {
            return 냥_숫자콤마($s);
        }
        return $s;
    };
    $subStr = static function ($a, $b) use ($toStr): string {
        $as = $toStr($a);
        $bs = $toStr($b);
        if (function_exists('bcsub') && function_exists('bccomp')) {
            $r = bcsub($as, $bs, 0);
            return (bccomp($r, '0', 0) < 0) ? '0' : $r;
        }
        // 문자열 학교식 뺄셈 (큰 금액 (int) 금지)
        $aRev = strrev($as);
        $bRev = strrev($bs);
        $len = max(strlen($aRev), strlen($bRev));
        $borrow = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $da = $i < strlen($aRev) ? (int)$aRev[$i] : 0;
            $db = $i < strlen($bRev) ? (int)$bRev[$i] : 0;
            $v = $da - $db - $borrow;
            if ($v < 0) {
                $v += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out .= (string)$v;
        }
        if ($borrow > 0) {
            return '0';
        }
        return ltrim(strrev($out), '0') ?: '0';
    };
    $list = [];
    // CONCAT('N', …) — mysqli/float/과학적표기 캐스팅 방지
    $rs = @db_query("
      SELECT idx, status, nick, receiver,
             CONCAT('N', CAST(CAST(IFNULL(tax, 0) AS DECIMAL(40,0)) AS CHAR)) AS tax,
             CONCAT('N', CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR)) AS point,
             CONCAT('N', CAST(CAST(IFNULL(mypoint, 0) AS DECIMAL(40,0)) AS CHAR)) AS mypoint,
             regdate
      FROM tb_point_log
      WHERE (
            (status = '양도' AND (nick = '{$nick_esc}' OR receiver = '{$nick_esc}'))
            OR (status = '마피아적발' AND nick = '{$nick_esc}')
        )
      ORDER BY regdate DESC, idx DESC
      LIMIT {$limit}
    ");
    if (!$rs) {
        return [];
    }
    while ($row = db_fetch($rs)) {
        $status = trim((string)($row['status'] ?? ''));
        $sender = trim((string)($row['nick'] ?? ''));
        $receiver = trim((string)($row['receiver'] ?? ''));
        $sentNum = $toStr($row['point'] ?? 'N0');
        $feeNum = $toStr($row['tax'] ?? 'N0');
        $afterNum = $toStr($row['mypoint'] ?? 'N0');
        $ts = strtotime((string)($row['regdate'] ?? ''));
        $time = $ts ? date('Y-m-d H:i', $ts) : (string)($row['regdate'] ?? '');

        if ($status === '마피아적발') {
            $beforeNum = $feeNum;
            // 구로그(tax=0)면 mypoint−point 로 추정
            if ($beforeNum === '0' && $afterNum !== '0') {
                $beforeNum = $subStr($afterNum, $sentNum);
            }
            $list[] = [
                'idx' => (int)($row['idx'] ?? 0),
                'kind' => 'mafia_catch',
                'dir' => 'in',
                'peer' => $receiver !== '' ? $receiver : '마피아 적발 보상',
                'sender' => $sender,
                'receiver' => $receiver,
                'amount' => $sentNum,
                'amount_fmt' => $fmtNp($sentNum),
                'fee' => '0',
                'fee_fmt' => $fmtNp('0'),
                'received' => $sentNum,
                'received_fmt' => $fmtNp($sentNum),
                'balance_before' => $beforeNum,
                'balance_before_fmt' => $fmtNp($beforeNum),
                'balance_after' => $afterNum,
                'balance_after_fmt' => $fmtNp($afterNum),
                'time' => $time,
                'time_raw' => (string)($row['regdate'] ?? ''),
            ];
            continue;
        }

        $recvNum = $subStr($sentNum, $feeNum);
        $dir = ($sender === $nick) ? 'out' : 'in';
        $peer = ($dir === 'out') ? $receiver : $sender;
        $list[] = [
            'idx' => (int)($row['idx'] ?? 0),
            'kind' => 'transfer',
            'dir' => $dir,
            'peer' => $peer,
            'sender' => $sender,
            'receiver' => $receiver,
            'amount' => $sentNum,
            'amount_fmt' => $fmt($sentNum),
            'fee' => $feeNum,
            'fee_fmt' => $fmt($feeNum),
            'received' => $recvNum,
            'received_fmt' => $fmt($recvNum),
            'time' => $time,
            'time_raw' => (string)($row['regdate'] ?? ''),
        ];
    }
    return $list;
}

function wallet_fmt_game($n) {
    $raw = trim((string)$n);
    $disp = '';
    if (function_exists('게임냥_안전표시')) {
        $disp = 게임냥_안전표시($n, '');
    } elseif (function_exists('랭킹_게임냥표시')) {
        $disp = 랭킹_게임냥표시($n, '');
    } else {
        $disp = function_exists('냥_숫자콤마') ? 냥_숫자콤마($n) : number_format((float)$n);
    }
    return $disp;
}

/** 오늘+/- 등 좁은 칸용 — 한 줄(조/억/만 중 상위 단위만) */
function wallet_fmt_game_compact($n) {
    $digits = function_exists('냥_정수문자열')
        ? 냥_정수문자열($n)
        : (ltrim(preg_replace('/[^\d]/', '', (string)$n), '0') ?: '0');
    if ($digits === '0') {
        return '0';
    }
    $조 = '1000000000000';
    $억 = '100000000';
    $만 = '10000';
    if (function_exists('bccomp') && function_exists('bcdiv')) {
        if (bccomp($digits, $조, 0) >= 0) {
            return (function_exists('냥_숫자콤마') ? 냥_숫자콤마(bcdiv($digits, $조, 0)) : bcdiv($digits, $조, 0)) . '조';
        }
        if (bccomp($digits, $억, 0) >= 0) {
            return (function_exists('냥_숫자콤마') ? 냥_숫자콤마(bcdiv($digits, $억, 0)) : bcdiv($digits, $억, 0)) . '억';
        }
        if (bccomp($digits, $만, 0) >= 0) {
            return (function_exists('냥_숫자콤마') ? 냥_숫자콤마(bcdiv($digits, $만, 0)) : bcdiv($digits, $만, 0)) . '만';
        }
        return function_exists('냥_숫자콤마') ? 냥_숫자콤마($digits) : $digits;
    }
    $len = strlen($digits);
    if ($len > 12) {
        $v = substr($digits, 0, $len - 12);
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($v) : $v) . '조';
    }
    if ($len > 8 || ($len === 8 && $digits >= $억)) {
        $v = substr($digits, 0, max(1, $len - 8));
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($v) : $v) . '억';
    }
    if ($len > 4) {
        $v = substr($digits, 0, $len - 4);
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($v) : $v) . '만';
    }
    return function_exists('냥_숫자콤마') ? 냥_숫자콤마($digits) : $digits;
}

/** 지갑 게임냥 표시 — 신불자/마이너스면 앞에 - */
function wallet_fmt_game_signed($point, $title = '') {
    $disp = wallet_fmt_game($point);
    $title = trim((string)$title);
    $raw = trim((string)$point);
    $음수 = (bool)preg_match('/^N?-/i', $raw)
        || (is_numeric($raw) && (float)$raw < 0);
    $신불 = ($title === '🆘신불자') || (bool)preg_match('/신불자/u', $title);
    if (($신불 || $음수) && $disp !== '' && $disp !== '0' && substr($disp, 0, 1) !== '-') {
        $disp = '-' . $disp;
    }
    return $disp;
}

function wallet_fmt_new($n) {
    if (function_exists('newpoint표시')) {
        return newpoint표시($n);
    }
    return function_exists('냥_숫자콤마') ? 냥_숫자콤마((int)floor((float)$n)) : number_format((int)floor((float)$n));
}

/** .궁금 타수와 동일: 오늘 tb_msg 생타(cnt2) / 버프타(SUM(tasu)) */
function wallet_today_tasu($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return ['raw' => 0, 'buff' => 0, 'fmt' => '0/0'];
    }
    $esc = addslashes($nick);
    $raw_expr = function_exists('생타_SQL_select_expr')
        ? 생타_SQL_select_expr('msg')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,''))='' OR TRIM(msg) LIKE '%사진을 보냈습니다%' THEN 0 ELSE 1 END), 0)";
    $buff_expr = function_exists('버프타_SQL_select_expr')
        ? 버프타_SQL_select_expr('msg', 'tasu')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,'')) LIKE '%사진을 보냈습니다%' THEN 0 ELSE IFNULL(tasu, 0) END), 0)";
    $row = @db_select("
        SELECT
            {$raw_expr} AS cnt2,
            {$buff_expr} AS cnt
        FROM tb_msg
        WHERE nickname = '{$esc}'
          AND tasu != 0
          AND regdate >= CURDATE()
          AND regdate < CURDATE() + INTERVAL 1 DAY
    ");
    $raw = (int)($row['cnt2'] ?? 0);
    $buff = (int)($row['cnt'] ?? 0);
    return [
        'raw' => $raw,
        'buff' => $buff,
        'fmt' => $raw . '/' . $buff,
    ];
}

function wallet_member_payload(array $row, $nick = '') {
    $nick = trim((string)$nick);
    if ($nick === '') {
        $nick = wallet_nick_from_row($row);
    }
    $pointRaw = $row['point'] ?? 0;
    $pointStr = function_exists('냥_정수문자열') ? 냥_정수문자열($pointRaw) : (string)(int)$pointRaw;
    $pointForRank = (function_exists('bccomp') && bccomp($pointStr, (string)PHP_INT_MAX, 0) > 0)
      ? PHP_INT_MAX
      : (int)$pointStr;
    $계급 = wallet_point_rank($pointForRank);
    $무기 = trim((string)($row['item'] ?? ''));
    $강화 = (int)($row['enhance'] ?? 0);
    $무기표시 = $무기 !== '' ? ($무기 . ($강화 > 0 ? " +{$강화}" : '')) : '없음';
    $오늘타수 = wallet_today_tasu($nick);
    $title = trim((string)($row['title'] ?? ''));

    return [
        'nick' => $nick,
        'title' => $title,
        'style' => trim((string)($row['style'] ?? '')),
        'newpoint' => (int)floor((float)($row['newpoint'] ?? 0)),
        'newpoint_fmt' => wallet_fmt_new($row['newpoint'] ?? 0),
        'point' => (string)$pointRaw,
        'point_fmt' => wallet_fmt_game_signed($pointRaw, $title),
        'rank_name' => (string)($계급['name'] ?? ''),
        'rank_level' => (int)($계급['rank'] ?? 0),
        'weapon' => $무기표시,
        'protect' => (int)($row['protect'] ?? 0),
        'tasu' => (int)$오늘타수['buff'],
        'tasu_raw' => (int)$오늘타수['raw'],
        'tasu_fmt' => (string)$오늘타수['fmt'],
        'stasu' => (int)($row['stasu'] ?? 0),
    ];
}

function wallet_mining_nav_link($query, $nick) {
    return [
        'icon' => '🥄',
        'label' => '채굴',
        'href' => '/page/mining.php' . $query,
        'disabled' => false,
        'disabled_hint' => '',
    ];
}

function wallet_ore_revoke_wallet_visible($nick) {
    $nick = trim((string)$nick);
    $target = '민호';
    if ($nick === $target) {
        return true;
    }
    if (function_exists('getTwoCharNick') && getTwoCharNick($nick) === $target) {
        return true;
    }
    return false;
}

function wallet_mining_admin_boot(): void {
    require_once __DIR__ . '/mining_config.inc.php';
    require_once __DIR__ . '/mining_storage.inc.php';
    require_once __DIR__ . '/mining_sync.inc.php';
}

function wallet_mining_pending_stats_payload(): array {
    wallet_mining_admin_boot();
    if (!function_exists('mining_sync_pending_totals')) {
        return ['nicks' => 0, 'total' => 0, 'total_fmt' => '0'];
    }
    return mining_sync_pending_totals();
}

function wallet_boss_nav_link($query, $nick) {
    // boss_raid.inc 로드하지 않음 (가방 진입 지연 방지 · 접근은 닉 있으면 OK)
    if (trim((string)$nick) === '') {
        return null;
    }
    return [
        'icon' => '🐉',
        'label' => '보스',
        'href' => '/page/boss.php' . $query,
        'disabled' => false,
        'disabled_hint' => '',
    ];
}

function wallet_room_urls() {
    global $본방주소;
    include_once __DIR__ . '/../_bonbang.php';
    return [
        '본방' => (!empty($본방주소)) ? (string)$본방주소 : 'https://open.kakao.com/o/pIe2XDJi',
        '홍보방' => 'https://open.kakao.com/o/prP2JBJi',
    ];
}

/** 가방 우하단 본방·홍보방 플로팅 메뉴 마크업 */
function wallet_room_fab_markup(?array $urls = null): string {
    if ($urls === null) {
        $urls = wallet_room_urls();
    }
    $bon = htmlspecialchars((string)($urls['본방'] ?? ''), ENT_QUOTES, 'UTF-8');
    $promo = htmlspecialchars((string)($urls['홍보방'] ?? ''), ENT_QUOTES, 'UTF-8');
    return <<<HTML
<div class="wallet-room-fab" id="walletRoomFab">
  <div class="wallet-room-fab-panel" id="walletRoomFabPanel" hidden>
    <a class="wallet-room-fab-item" href="{$bon}" target="_blank" rel="noopener noreferrer" title="본방가기">
      <span class="wallet-room-fab-ico" aria-hidden="true">🏠</span>
      <span class="wallet-room-fab-txt">본방</span>
    </a>
    <button type="button" class="wallet-room-fab-item" id="btnPromoRoomFab" data-href="{$promo}" title="홍보방가기">
      <span class="wallet-room-fab-ico" aria-hidden="true">📢</span>
      <span class="wallet-room-fab-txt">홍보</span>
    </button>
  </div>
  <button type="button" class="wallet-room-fab-toggle" id="walletRoomFabToggle" aria-expanded="false" aria-controls="walletRoomFabPanel" title="오픈채팅">
    <span class="wallet-room-fab-toggle-ico" aria-hidden="true">💬</span>
  </button>
</div>
HTML;
}

/** 가방 바로가기용 마피아 라벨 — 예) 마피아(밤·시민) · 마피아(낮) */
function wallet_mafia_nav_label($nick): string {
    $base = '마피아';
    $nick = trim((string)$nick);
    static $mafiaLoaded = false;
    if (!$mafiaLoaded) {
        $mafiaLoaded = true;
        $inc = __DIR__ . '/mafia.inc.php';
        if (is_file($inc)) {
            require_once $inc;
        }
    }

    $parts = [];
    if (function_exists('mafia_게임') && function_exists('mafia_페이즈라벨')) {
        try {
            $game = mafia_게임();
            $phase = trim((string)($game['phase'] ?? ''));
            if ($phase === 'night') {
                $parts[] = '밤';
            } elseif ($phase === 'day') {
                $parts[] = '낮';
            } elseif ($phase === 'waiting') {
                $parts[] = '대기';
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    if ($nick !== '' && function_exists('mafia_내역할라벨')) {
        $role = trim((string)mafia_내역할라벨($nick));
        if ($role !== '') {
            $parts[] = $role;
        }
    }

    if ($parts === []) {
        return $base;
    }
    return $base . '(' . implode('·', $parts) . ')';
}

/** 가방 바로가기용 부루마블 라벨 — 예) 부루마블[민호] */
function wallet_burumable_nav_label(): string {
    $base = defined('부루마블_이름') ? 부루마블_이름 : '부루마블';
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $inc = __DIR__ . '/burumable.inc.php';
        if (is_file($inc)) {
            require_once $inc;
        }
    }
    if (!function_exists('burumable_로드') || !function_exists('burumable_현재닉')) {
        return $base;
    }
    try {
        $state = burumable_로드();
        if (!is_array($state) || !empty($state['over'])) {
            return $base;
        }
        $nm = trim((string)burumable_현재닉($state));
        if ($nm === '') {
            return $base;
        }
        return $base . '[' . $nm . ']';
    } catch (Throwable $e) {
        return $base;
    }
}

function wallet_nav_links_build($q, $nick, $point) {
    // 상단 한 줄(4칸): 생활·아이템
    $links = [
        ['icon' => '🎰', 'label' => '출석룰렛', 'href' => '/page/attendance_roulette.php' . $q, 'nav_group' => 'top'],
        ['icon' => '🗺️', 'label' => '친구지도', 'href' => '/page/friend_map.php' . $q, 'nav_group' => 'top'],
        ['icon' => '📈', 'label' => '아이템 구매', 'href' => '/page/item_exchange.php' . $q, 'nav_group' => 'top'],
        ['icon' => '♻️', 'label' => '교환', 'href' => '/page/item_shop.php' . $q, 'nav_group' => 'top'],
    ];
    // 본문 바로가기: 채굴 강화 보스 마피아 / 홀짝 냥카라 마켓 랭킹 / 개인금고 색표마켓
    $links[] = wallet_mining_nav_link($q, $nick);
    $links[] = ['icon' => '⚔️', 'label' => '강화', 'href' => '/page/enchant.php' . $q];
    $boss = wallet_boss_nav_link($q, $nick);
    if ($boss) {
        $links[] = $boss;
    }
    $links[] = [
        'icon' => '🕵️',
        'label' => wallet_mafia_nav_label($nick),
        'nav_key' => '마피아',
        'href' => '/page/mafia.php' . $q,
    ];
    $links[] = ['icon' => '🎲', 'label' => '홀짝', 'href' => '/page/game.php' . $q];
    $links[] = ['icon' => '🃏', 'label' => '냥카라', 'href' => '/page/nyangkara.php' . $q];
    $links[] = ['icon' => '🛒', 'label' => '마켓', 'href' => '/shop/' . $q];
    $links[] = [
        'icon' => '🏆',
        'label' => '랭킹',
        'href' => '/page/ranking.php' . $q,
    ];
    $links[] = ['icon' => '🥇', 'label' => '개인금고', 'href' => '/page/gold_vault.php' . $q];
    $links[] = ['icon' => '🎨', 'label' => '색표마켓', 'href' => '/page/color_market.php' . $q];
    $links[] = ['icon' => '♟️', 'label' => wallet_burumable_nav_label(), 'nav_key' => '부루마블', 'href' => '/page/burumable.php' . $q];
    $links[] = ['icon' => '🎬', 'label' => '조롱관', 'href' => '/gallery/', 'external' => true];
    // 민호 전용 관리 (광물 회수 · 채굴수량 초기화) · 조롱관 업로드
    if (wallet_ore_revoke_wallet_visible($nick)) {
        $links[] = [
            'icon' => '⚙️',
            'label' => '관리',
            'href' => '/page/wallet_admin.php' . $q,
        ];
        $links[] = [
            'icon' => '📤',
            'label' => '조롱관 업로드',
            'href' => '/gallery/upload.php',
            'external' => true,
        ];
    }

    // 게임포기 중이면 지정 바로가기 숨김
    if (function_exists('게임포기_바로가기숨김인가') && 게임포기_바로가기숨김인가($nick)) {
        $hide = function_exists('게임포기_숨김라벨목록')
            ? 게임포기_숨김라벨목록()
            : ['홀짝', '냥카라', '채굴', '강화', '보스', '마켓', '개인금고', '마피아', '부루마블'];
        $hideMap = array_fill_keys($hide, true);
        $links = array_values(array_filter($links, static function ($link) use ($hideMap) {
            $label = (string)($link['label'] ?? '');
            $key = (string)($link['nav_key'] ?? $label);
            return empty($hideMap[$key]) && empty($hideMap[$label]);
        }));
    }

    return $links;
}

function wallet_nav_link_html(array $link): string {
    $disabled = !empty($link['disabled']);
    $hint = trim((string)($link['disabled_hint'] ?? ''));
    $icon = (string)($link['icon'] ?? '');
    $label = htmlspecialchars((string)($link['label'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($disabled) {
        $title = htmlspecialchars($hint !== '' ? $hint : '이용 불가', ENT_QUOTES, 'UTF-8');
        $hintHtml = $hint !== '' ? '<small>' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</small>' : '';
        return '<span class="nav-link disabled" title="' . $title . '"><span>' . $icon . '</span><span>' . $label . $hintHtml . '</span></span>';
    }
    $href = (string)($link['href'] ?? '');
    $external = !empty($link['external']) || (bool)preg_match('#^https?://#i', $href);
    $lockAlert = trim((string)($link['lock_alert'] ?? ''));
    $cls = 'nav-link';
    if (strpos($href, '/page/mining.php') !== false) {
        $cls .= ' nav-mining';
    } elseif (strpos($href, '/page/boss.php') !== false) {
        $cls .= ' nav-boss';
    }
    if ($lockAlert !== '') {
        $cls .= ' nav-locked';
    }
    $extra = '';
    if ($lockAlert !== '') {
        $extra .= ' data-lock-alert="' . htmlspecialchars($lockAlert, ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($external) {
        $extra .= ' target="_blank" rel="noopener noreferrer"';
    }
    return '<a class="' . $cls . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $extra . '><span>' . $icon . '</span><span>' . $label . '</span></a>';
}

/** .퇴근 중 바로가기 — 냥카라 · 마피아 */
function wallet_nav_links_퇴근($q, $nick = '') {
    return [
        [
            'icon' => '🃏',
            'label' => '냥카라',
            'href' => '/page/nyangkara.php' . $q,
        ],
        [
            'icon' => '🕵️',
            'label' => wallet_mafia_nav_label($nick),
            'nav_key' => '마피아',
            'href' => '/page/mafia.php' . $q,
        ],
    ];
}

function wallet_today_summary($nick) {
    $nick = addslashes(trim((string)$nick));
    $오늘시작 = date('Y-m-d 00:00:00');
    $내일시작 = date('Y-m-d 00:00:00', strtotime('+1 day'));
    // nick+regdate 인덱스 사용 (CAST 불필요 — point는 이미 DECIMAL)
    $in_row = db_select("
      SELECT CAST(COALESCE(SUM(point), 0) AS CHAR) AS s
      FROM tb_point_log
      WHERE nick = '{$nick}' AND regdate >= '{$오늘시작}' AND regdate < '{$내일시작}' AND point > 0
    ");
    $out_row = db_select("
      SELECT CAST(COALESCE(SUM(ABS(point)), 0) AS CHAR) AS s
      FROM tb_point_log
      WHERE nick = '{$nick}' AND regdate >= '{$오늘시작}' AND regdate < '{$내일시작}' AND point < 0
    ");
    // ※ (nick OR receiver) COUNT는 receiver 인덱스 없어 700만건 풀스캔(~20초+) → 사용처 없어 제거
    //   UI는 in_fmt/out_fmt만 사용. count는 nick 기준(인덱스)만 유지
    $cnt_row = db_select("
      SELECT COUNT(*) AS c
      FROM tb_point_log
      WHERE nick = '{$nick}' AND regdate >= '{$오늘시작}' AND regdate < '{$내일시작}'
    ");
    $inStr = function_exists('냥_정수문자열') ? 냥_정수문자열($in_row['s'] ?? 0) : (string)(int)($in_row['s'] ?? 0);
    $outStr = function_exists('냥_정수문자열') ? 냥_정수문자열($out_row['s'] ?? 0) : (string)(int)($out_row['s'] ?? 0);
    return [
        'in' => $inStr,
        'in_fmt' => wallet_fmt_game_compact($inStr),
        'out' => $outStr,
        'out_fmt' => wallet_fmt_game_compact($outStr),
        'count' => (int)($cnt_row['c'] ?? 0),
    ];
}

function wallet_item_shop_data(array $member) {
    if (!function_exists('bag_은총_수량') && is_file(__DIR__ . '/../item_bag_enhance.inc.php')) {
        require_once __DIR__ . '/../item_bag_enhance.inc.php';
    }
    return [
        'exchange' => function_exists('아이템_교환_목록') ? 아이템_교환_목록($member) : [],
    ];
}

/** 가방 접속자 실제 IP (프록시·Cloudflare 우선) */
function wallet_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        $raw = trim((string)($_SERVER[$key] ?? ''));
        if ($raw === '') {
            continue;
        }
        foreach (preg_split('/\s*,\s*/', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $part, $m)) {
                $part = $m[1];
            }
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }
    }
    return '';
}

function wallet_bag_ip_테이블보장(): void {
    static $done = false;
    if ($done || !function_exists('db_query')) {
        return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_bag_ip` (
      `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `nick` VARCHAR(50) NOT NULL,
      `ip` VARCHAR(45) NOT NULL,
      `ua` VARCHAR(255) NOT NULL DEFAULT '',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`idx`),
      KEY `nick` (`nick`),
      KEY `ip` (`ip`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** 가방 진입 시 닉·IP를 각각 컬럼으로 기록. 같은 닉+IP는 10분에 1회 */
function wallet_bag_ip_수집(string $nick): void {
    $nick = trim($nick);
    $ip = wallet_client_ip();
    if ($nick === '' || $ip === '' || !function_exists('db_query')) {
        return;
    }
    wallet_bag_ip_테이블보장();
    $nick_esc = addslashes($nick);
    $ip_esc = addslashes($ip);
    $최근 = @db_select("
      SELECT idx FROM tb_bag_ip
      WHERE nick = '{$nick_esc}' AND ip = '{$ip_esc}'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
      ORDER BY idx DESC LIMIT 1
    ");
    if (!empty($최근['idx'])) {
        return;
    }
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (function_exists('mb_substr')) {
        $ua = mb_substr($ua, 0, 255, 'UTF-8');
    } else {
        $ua = substr($ua, 0, 255);
    }
    $ua_esc = addslashes($ua);
    @db_query("INSERT INTO tb_bag_ip SET nick = '{$nick_esc}', ip = '{$ip_esc}', ua = '{$ua_esc}', created_at = NOW()");
}

/** @return list<array{nick:string,ip:string,created_at:string}> */
function wallet_bag_ip_목록(int $limit = 80): array {
    wallet_bag_ip_테이블보장();
    $limit = max(1, min(300, $limit));
    $out = [];
    $rs = @db_query("SELECT nick, ip, created_at FROM tb_bag_ip ORDER BY idx DESC LIMIT {$limit}");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $out[] = [
                'nick' => (string)($row['nick'] ?? ''),
                'ip' => (string)($row['ip'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
    }
    return $out;
}

if (defined('WALLET_LIB_ONLY') && WALLET_LIB_ONLY) {
    return;
}

$wallet_actions = ['status', 'swap_quote', 'swap_np', 'swap_pt', 'transfer_np', 'transfer_pt', 'transfer_history', 'profile_status', 'profile_use', 'jiho_use', 'emergency_alarm', 'mining_pending_reset_all', 'game_quit', 'game_start', 'check_in'];
$req_action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
if (in_array($req_action, $wallet_actions, true)) {
    ob_start();
}
$req_code = wallet_request_code();

if (in_array($req_action, $wallet_actions, true)) {
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

    if ($req_action === 'status') {
        $fresh = wallet_auth($req_code);
        $swap_np_rate_raw = function_exists('스왑_1보유냥당_게임냥') ? 스왑_1보유냥당_게임냥() : 0;
        $swap_np_rate = is_string($swap_np_rate_raw)
          ? $swap_np_rate_raw
          : (string)(int)$swap_np_rate_raw;
        wallet_json([
            'ok' => true,
            'type' => 'status',
            'member' => wallet_member_payload($fresh ?: $member, $nick),
            'today' => wallet_today_summary($nick),
            'profile' => wallet_profile_change_payload($nick),
            'buff' => wallet_buff_payload($nick),
            'swap' => [
                'np_min' => 0,
                'np_delete_pct' => (int)스왑_본방_삭제비율(),
                'pt_fee_pct' => (int)스왑_게임방_수수료비율(),
                'pt_min' => function_exists('스왑_게임방_최소본방냥') ? (int)스왑_게임방_최소본방냥() : 500,
                'pt_np_min' => function_exists('스왑_게임방_최소본방냥') ? (int)스왑_게임방_최소본방냥() : 500,
                'np_per_pt' => $swap_np_rate,
                'np_per_pt_fmt' => wallet_fmt_game($swap_np_rate),
            ],
        ]);
    }

    if ($req_action === 'profile_status') {
        wallet_json([
            'ok' => true,
            'type' => 'profile_status',
            'profile' => wallet_profile_change_payload($nick),
            'buff' => wallet_buff_payload($nick),
        ]);
    }

    if ($req_action === 'profile_use') {
        $amount = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 1;
        if ($amount < 1) {
            $amount = 1;
        }
        if ($amount > 99) {
            wallet_json(['ok' => false, 'data' => '한 번에 최대 99개까지 사용할 수 있어요.']);
        }
        $result = wallet_profile_change_use($nick, $amount);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        $result['type'] = 'profile_use';
        $result['buff'] = wallet_buff_payload($nick);
        wallet_json($result);
    }

    if ($req_action === 'jiho_use') {
        $amount = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 1;
        if ($amount < 1) {
            $amount = 1;
        }
        if ($amount > 99) {
            wallet_json(['ok' => false, 'data' => '한 번에 최대 99개까지 사용할 수 있어요.']);
        }
        $result = wallet_jiho_use($nick, $amount);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        $result['type'] = 'jiho_use';
        $result['profile'] = wallet_profile_change_payload($nick);
        wallet_json($result);
    }

    if ($req_action === 'emergency_alarm') {
        $days = isset($_REQUEST['days']) ? (int)$_REQUEST['days'] : 3;
        $emoji = isset($_REQUEST['emoji']) ? (string)$_REQUEST['emoji'] : '🥕';
        $result = wallet_emergency_alarm_execute($nick, $days, $emoji);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        $result['type'] = 'emergency_alarm';
        wallet_json($result);
    }

    if ($req_action === 'swap_quote') {
        $dir = isset($_REQUEST['dir']) ? trim($_REQUEST['dir']) : '';
        $amount = isset($_REQUEST['amount']) && $_REQUEST['amount'] !== '' ? $_REQUEST['amount'] : null;
        $quote = wallet_swap_quote_payload($dir, $amount, $member);
        if (empty($quote['ok'])) {
            wallet_json(['ok' => false, 'data' => $quote['msg'] ?? '견적을 계산할 수 없습니다.']);
        }
        // 해/천경 견적 — JS Number() 정밀도 손실 방지용 표시 필드
        if (isset($quote['지급_pt'])) {
            $quote['지급_pt'] = function_exists('냥_정수문자열')
              ? 냥_정수문자열($quote['지급_pt'])
              : (string)$quote['지급_pt'];
            $quote['지급_pt_fmt'] = wallet_fmt_game($quote['지급_pt']);
        }
        foreach (['차감_pt', '수수료_pt', '교환_pt'] as $k) {
            if (isset($quote[$k])) {
                $quote[$k] = function_exists('냥_정수문자열')
                  ? 냥_정수문자열($quote[$k])
                  : (string)$quote[$k];
                $quote[$k . '_fmt'] = wallet_fmt_game($quote[$k]);
            }
        }
        if (isset($quote['지급_np'])) {
            $quote['지급_np_fmt'] = wallet_fmt_new($quote['지급_np']);
        }
        if (isset($quote['원하는_np'])) {
            $quote['원하는_np_fmt'] = wallet_fmt_new($quote['원하는_np']);
        }
        if (isset($quote['최대_np'])) {
            $quote['최대_np_fmt'] = wallet_fmt_new($quote['최대_np']);
        }
        if (isset($quote['차감_np'])) {
            $quote['차감_np_fmt'] = wallet_fmt_new($quote['차감_np']);
        }
        if (isset($quote['삭제_np'])) {
            $quote['삭제_np_fmt'] = wallet_fmt_new($quote['삭제_np']);
        }
        wallet_json(['ok' => true, 'type' => 'swap_quote', 'quote' => $quote, 'dir' => $dir]);
    }

    if ($req_action === 'swap_np') {
        $amount = isset($_REQUEST['amount']) && $_REQUEST['amount'] !== '' ? $_REQUEST['amount'] : null;
        $result = wallet_swap_np_execute($nick, $amount);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        wallet_append_fresh_payload($result, $req_code, $nick);
        wallet_json($result);
    }

    if ($req_action === 'swap_pt') {
        $amount = isset($_REQUEST['amount']) && $_REQUEST['amount'] !== '' ? $_REQUEST['amount'] : null;
        $result = wallet_swap_pt_execute($nick, $amount);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        wallet_append_fresh_payload($result, $req_code, $nick);
        wallet_json($result);
    }

    if ($req_action === 'transfer_np') {
        $receiver = isset($_REQUEST['receiver']) ? trim($_REQUEST['receiver']) : '';
        $amount = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;
        $result = wallet_transfer_np_execute($nick, $receiver, $amount);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        wallet_append_fresh_payload($result, $req_code, $nick);
        wallet_json($result);
    }

    if ($req_action === 'transfer_pt') {
        $receiver = isset($_REQUEST['receiver']) ? trim($_REQUEST['receiver']) : '';
        $amount = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
        $result = wallet_transfer_pt_execute($nick, $receiver, $amount);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        wallet_append_fresh_payload($result, $req_code, $nick);
        wallet_json($result);
    }

    if ($req_action === 'transfer_history') {
        $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 40;
        wallet_json([
            'ok' => true,
            'items' => wallet_transfer_history($nick, $limit),
        ]);
    }

    if ($req_action === 'mining_pending_reset_all') {
        if (!wallet_ore_revoke_wallet_visible($nick)) {
            wallet_json(['ok' => false, 'data' => '권한이 없습니다.']);
        }
        wallet_mining_admin_boot();
        if (!function_exists('mining_sync_reset_pending_all')) {
            wallet_json(['ok' => false, 'data' => '채굴 모듈을 불러올 수 없습니다.']);
        }
        $result = mining_sync_reset_pending_all();
        $result['type'] = 'mining_pending_reset_all';
        $result['pending'] = mining_sync_pending_totals();
        wallet_json($result);
    }

    if ($req_action === 'game_quit') {
        if (!function_exists('게임포기_실행')) {
            wallet_json(['ok' => false, 'data' => '게임포기 기능을 불러올 수 없어요.']);
        }
        $result = 게임포기_실행($nick);
        wallet_json([
            'ok' => !empty($result['ok']),
            'data' => (string)($result['msg'] ?? ''),
            'type' => 'game_quit',
            'game_quit' => $result['state'] ?? (function_exists('게임포기_상태') ? 게임포기_상태($nick) : null),
        ]);
    }

    if ($req_action === 'game_start') {
        if (!function_exists('게임포기_시작실행')) {
            wallet_json(['ok' => false, 'data' => '게임시작 기능을 불러올 수 없어요.']);
        }
        $result = 게임포기_시작실행($nick);
        wallet_json([
            'ok' => !empty($result['ok']),
            'data' => (string)($result['msg'] ?? ''),
            'type' => 'game_start',
            'game_quit' => $result['state'] ?? (function_exists('게임포기_상태') ? 게임포기_상태($nick) : null),
        ]);
    }

    if ($req_action === 'check_in') {
        $result = wallet_출근_실행($nick);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        $result['type'] = 'check_in';
        wallet_json($result);
    }

}

// ----- 페이지 -----
$wallet_api_entry = '/api/game/wallet_web.php';
$wallet_script = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($wallet_script, '/page/wallet.php') !== false) {
    $wallet_api_entry = '/page/wallet.php';
}

$wallet_code = wallet_코드_해석();
$wallet_need_code = true;
$wallet_off_work = false; // true면 바로가기는 냥카라·마피아
$wallet_member = null;
$wallet_today = null;
$wallet_swap_info = null;
$wallet_nav_links = [];
if (!empty($GLOBALS['wallet_preauth']['row']) && !empty($GLOBALS['wallet_preauth']['code'])) {
    $wallet_code = (string)$GLOBALS['wallet_preauth']['code'];
    $row = wallet_member_row_enrich($GLOBALS['wallet_preauth']['row'], $wallet_code);
    $nick = wallet_nick_from_row($row);
    if ($nick === '') {
        $nick = trim((string)($row['name'] ?? ''));
    }
    wallet_코드_쿠키_저장($wallet_code);
    $wallet_need_code = false;
    $wallet_member = wallet_member_payload($row, $nick);
    $wallet_swap_info = [
        'np_min' => 0,
        'np_delete_pct' => 10,
        'pt_fee_pct' => 20,
        'np_per_pt' => null,
    ];
    $q = wallet_code_query($wallet_code);
    if (wallet_nick_is_퇴근($nick)) {
        $wallet_off_work = true;
        $wallet_nav_links = wallet_nav_links_퇴근($q, $nick);
    } else {
        $wallet_nav_links = wallet_nav_links_build($q, $nick, $wallet_member['point'] ?? 0);
    }
} elseif ($wallet_code !== '') {
    wallet_odd_even_includes();
    $row = wallet_game_auth_row($wallet_code);
    if ($row) {
        $row = wallet_member_row_enrich($row, $wallet_code);
        $nick = wallet_nick_from_row($row);
        if ($nick === '') {
            $nick = trim((string)($row['name'] ?? ''));
        }
        wallet_코드_쿠키_저장($wallet_code);
        $wallet_need_code = false;
        $wallet_member = wallet_member_payload($row, $nick);
        $wallet_swap_info = [
            'np_min' => 0,
            'np_delete_pct' => 10,
            'pt_fee_pct' => 20,
            'np_per_pt' => null,
        ];
        $q = wallet_code_query($wallet_code);
        if (wallet_nick_is_퇴근($nick)) {
            $wallet_off_work = true;
            $wallet_nav_links = wallet_nav_links_퇴근($q, $nick);
        } else {
            $wallet_nav_links = wallet_nav_links_build($q, $nick, $wallet_member['point'] ?? 0);
        }
    } elseif (wallet_코드_쿠키_읽기() === $wallet_code) {
        wallet_코드_쿠키_삭제();
    }
}

if (!$wallet_need_code && is_array($wallet_member)) {
    $bagIpNick = trim((string)($wallet_member['nick'] ?? ''));
    if ($bagIpNick !== '') {
        wallet_bag_ip_수집($bagIpNick);
    }
}

$wallet_show_ore_revoke = false;
$wallet_ore_revoke_stats = null;
$wallet_mining_pending_stats = null;
$wallet_profile = ['owned' => 0, 'active' => false, 'enddate' => null, 'end_fmt' => '', 'period_text' => '미적용', 'days_per_item' => 3];
$wallet_buff = [
    'profile' => ['active' => false, 'status_text' => '미적용', 'owned' => 0, 'days_per_item' => 3],
    'jiho' => ['active' => false, 'status_text' => '미적용', 'owned' => 0, 'hours_per_item' => 1],
    'magic' => ['active' => false, 'status_text' => '미적용'],
    'has_any' => false,
    'show_jiho_use' => true,
];
$wallet_kkoma = [
    'rank' => 0,
    'done' => 0,
    'limit' => 5,
    'remain' => 5,
    'completed' => 0,
    'full' => 0,
];
if (!$wallet_need_code && is_array($wallet_member)) {
    $wallet_profile = wallet_profile_change_payload($wallet_member['nick'] ?? '');
    $wallet_buff = wallet_buff_payload($wallet_member['nick'] ?? '');
    if (function_exists('wallet_functions_load')) {
        wallet_functions_load();
    } elseif (function_exists('wallet_odd_even_includes')) {
        wallet_odd_even_includes();
    }
    if (function_exists('꼬맨_지갑_페이로드')) {
        $wallet_kkoma = 꼬맨_지갑_페이로드($wallet_member['nick'] ?? '');
    }
}
if (!$wallet_need_code && is_array($wallet_member) && wallet_ore_revoke_wallet_visible($wallet_member['nick'] ?? '')) {
    // 관리 버튼만 바로가기에 표시 · 상세는 /page/wallet_admin.php
    $wallet_show_ore_revoke = true;
}
$wallet_game_quit = [
    'quit' => false,
    'can_start' => true,
    'remain_sec' => 0,
    'is_admin' => false,
    'lock_days' => defined('게임포기_쿨다운일') ? (int)게임포기_쿨다운일 : 3,
    'msg' => '',
];
if (!$wallet_need_code && is_array($wallet_member) && function_exists('게임포기_상태')) {
    $wallet_game_quit = 게임포기_상태($wallet_member['nick'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c1222">
    <title>내 가방</title>
    <!-- wallet-build:20260626b -->
    <?php
    $wallet_boss_prefetch = '';
    foreach ($wallet_nav_links as $__nav) {
        if (($__nav['label'] ?? '') === '보스' && empty($__nav['disabled'])) {
            $wallet_boss_prefetch = (string)($__nav['href'] ?? '');
            break;
        }
    }
    if ($wallet_boss_prefetch !== '') {
        echo '    <link rel="prefetch" href="/css/boss.css" as="style">' . "\n";
        echo '    <link rel="prefetch" href="/js/boss.js" as="script">' . "\n";
    }
    unset($__nav, $wallet_boss_prefetch);
    ?>
    <style>
        :root {
            --bg: #0c1222;
            --card: #151d32;
            --card2: #1a2540;
            --gold: #f5c542;
            --mint: #5eead4;
            --blue: #60a5fa;
            --red: #f87171;
            --green: #4ade80;
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
                radial-gradient(ellipse at 20% 0%, rgba(94,234,212,0.12) 0%, transparent 45%),
                radial-gradient(ellipse at 90% 20%, rgba(245,197,66,0.1) 0%, transparent 40%);
            font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
            color: var(--text);
            padding: 12px;
            padding-top: max(12px, env(safe-area-inset-top));
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }
        .wrap { max-width: 480px; margin: 0 auto; }
        h1 {
            font-size: 1.85rem;
            font-weight: 900;
            text-align: center;
            margin: 4px 0 14px;
            background: linear-gradient(135deg, var(--gold), var(--mint));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        .card h2 {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }
        .profile {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .profile .nick {
            font-size: 1.35rem;
            font-weight: 900;
        }
        .nick-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .carrot-alarm-btn {
            appearance: none;
            background: rgba(249, 115, 22, 0.12);
            border: 1px solid rgba(249, 115, 22, 0.35);
            border-radius: 10px;
            width: 36px;
            height: 36px;
            font-size: 1.15rem;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform .15s ease, background .15s ease;
        }
        .carrot-alarm-btn:active {
            transform: scale(0.92);
            background: rgba(249, 115, 22, 0.22);
        }
        .emergency-desc {
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.55;
            margin: 0 0 12px;
            text-align: left;
        }
        .emergency-preview {
            background: rgba(0,0,0,0.28);
            border: 1px solid rgba(249, 115, 22, 0.28);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-line;
            text-align: center;
            margin-bottom: 4px;
            color: var(--text);
        }
        .emergency-days-label {
            display: block;
            font-size: 0.75rem;
            color: var(--muted);
            margin: 0 0 8px;
        }
        .emergency-days {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .emergency-day-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.05);
            color: var(--text);
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 0.8rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            line-height: 1;
        }
        .emergency-day-btn.active {
            background: rgba(249, 115, 22, 0.22);
            border-color: rgba(249, 115, 22, 0.55);
            color: #fdba74;
        }
        .emergency-emoji-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            width: 42px;
            height: 42px;
            font-size: 1.25rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .emergency-emoji-btn.active {
            background: rgba(249, 115, 22, 0.22);
            border-color: rgba(249, 115, 22, 0.55);
            box-shadow: 0 0 0 1px rgba(249, 115, 22, 0.25);
        }
        .btn-confirm.carrot {
            background: linear-gradient(135deg, #fb923c, #ea580c);
            color: #1c0a00;
        }
        .profile .rank {
            font-size: 0.85rem;
            color: var(--gold);
            font-weight: 700;
            white-space: nowrap;
        }
        .profile-sub {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 2px;
        }
        .balance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .balance-box {
            background: var(--card2);
            border-radius: 12px;
            padding: 12px;
            border: 1px solid var(--line);
        }
        .balance-box .label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .balance-box .amount {
            font-size: 1.05rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            word-break: break-all;
        }
        .balance-box.new .amount { color: var(--mint); }
        .balance-box.game .amount { color: var(--gold); }
        .stats-row {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .stat-pill {
            flex: 1;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 0.78rem;
            text-align: center;
        }
        .stat-pill b { display: block; font-size: 0.92rem; margin-top: 2px; }
        .stat-pill.in b,
        .stat-pill.out b {
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-pill.in b { color: var(--green); }
        .stat-pill.out b { color: var(--red); }
        a.stat-pill {
            color: inherit;
            text-decoration: none;
            transition: background 0.15s, box-shadow 0.15s;
        }
        a.stat-pill:active,
        a.stat-pill:hover {
            background: rgba(94, 234, 212, 0.12);
            box-shadow: inset 0 0 0 1px rgba(94, 234, 212, 0.22);
        }
        a.stat-pill b { color: var(--mint); }
        .lock-box {
            text-align: center;
            padding: 40px 16px;
            color: var(--muted);
            line-height: 1.6;
        }
        .lock-box strong { color: var(--text); font-size: 1rem; }
        .hint { font-size: 0.72rem; color: var(--muted); text-align: center; margin-top: 12px; }
        .loading { opacity: 0.55; pointer-events: none; }
        #navTransit {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            background: rgba(10, 18, 16, 0.88);
            color: var(--text);
            font-size: 0.92rem;
            font-weight: 700;
        }
        #navTransit.show { display: flex; }
        #navTransit .spin {
            width: 34px;
            height: 34px;
            border: 3px solid rgba(110, 231, 183, 0.2);
            border-top-color: var(--mint, #6ee7b7);
            border-radius: 50%;
            animation: walletNavSpin .7s linear infinite;
        }
        @keyframes walletNavSpin { to { transform: rotate(360deg); } }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }
        .nav-grid-sep {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.16);
            margin: 10px 2px;
        }
        .nav-title-row {
            justify-content: space-between;
            gap: 8px;
        }
        .nav-title-row h2 {
            flex-shrink: 0;
        }
        .nav-room-links {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .nav-room-link {
            appearance: none;
            background: none;
            border: none;
            padding: 0;
            margin: 0;
            color: var(--mint, #6ee7b7);
            font-family: inherit;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.2;
            white-space: nowrap;
        }
        .nav-room-link:hover {
            text-decoration: underline;
        }
        .nav-room-sep {
            color: var(--muted);
            font-size: 0.72rem;
            line-height: 1;
            opacity: 0.7;
        }
        .wallet-room-fab {
            position: fixed;
            right: max(14px, env(safe-area-inset-right));
            bottom: max(18px, env(safe-area-inset-bottom));
            z-index: 90;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            pointer-events: none;
        }
        .wallet-room-fab-panel {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            pointer-events: auto;
        }
        .wallet-room-fab-panel[hidden] {
            display: none !important;
        }
        .wallet-room-fab-item,
        .wallet-room-fab-toggle {
            pointer-events: auto;
            appearance: none;
            border: 1px solid rgba(255,255,255,0.16);
            background: linear-gradient(160deg, rgba(36, 48, 64, 0.96), rgba(22, 28, 38, 0.98));
            color: var(--ink, #e8e4dc);
            box-shadow: 0 10px 28px rgba(0,0,0,0.35);
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .wallet-room-fab-item:hover,
        .wallet-room-fab-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.42);
        }
        .wallet-room-fab-item {
            min-height: 42px;
            padding: 0 14px 0 10px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .wallet-room-fab-ico {
            font-size: 1.05rem;
            line-height: 1;
        }
        .wallet-room-fab-txt {
            letter-spacing: -0.02em;
        }
        .wallet-room-fab-toggle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: linear-gradient(160deg, rgba(110, 231, 183, 0.28), rgba(36, 48, 64, 0.96));
            border-color: rgba(110, 231, 183, 0.45);
        }
        .wallet-room-fab-toggle[aria-expanded="true"] {
            background: linear-gradient(160deg, rgba(110, 231, 183, 0.4), rgba(36, 48, 64, 0.98));
        }
        .wallet-room-fab-toggle-ico {
            font-size: 1.35rem;
            line-height: 1;
        }
        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            padding: 8px 4px;
            border-radius: 10px;
            background: var(--card2);
            border: 1px solid var(--line);
            color: var(--text);
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            width: 100%;
            box-sizing: border-box;
            line-height: 1.2;
        }
        .promo-room-body {
            font-size: 0.84rem;
            color: var(--text);
            line-height: 1.55;
            margin: 0 0 4px;
        }
        .promo-room-body p {
            margin: 0 0 10px;
        }
        .promo-room-body ul {
            margin: 0 0 4px;
            padding-left: 1.15em;
        }
        .promo-room-body li {
            margin-bottom: 6px;
        }
        .promo-room-body .emph {
            color: var(--mint, #6ee7b7);
            font-weight: 700;
        }
        .nav-link span:first-child { font-size: 1.05rem; }
        .nav-link span:last-child {
            text-align: center;
            word-break: keep-all;
            line-height: 1.25;
        }
        .nav-link.disabled {
            opacity: 0.42;
            cursor: not-allowed;
            pointer-events: none;
        }
        .nav-link.disabled small {
            display: block;
            margin-top: 2px;
            font-size: 0.55rem;
            font-weight: 500;
            color: var(--muted);
        }
        .kkoma-card {
            border: 1px solid rgba(167, 139, 250, 0.28);
            background: linear-gradient(160deg, rgba(167, 139, 250, 0.12) 0%, rgba(12, 18, 34, 0.55) 55%);
        }
        .kkoma-card .card-fold-toggle h2 {
            color: var(--text);
            font-size: 0.95rem;
        }
        .kkoma-play {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(167, 139, 250, 0.16);
            border: 1px solid rgba(167, 139, 250, 0.28);
            color: #e9d5ff;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
        }
        .kkoma-play:active { transform: scale(0.99); }
        .kkoma-play span:last-child {
            font-size: 0.72rem;
            color: #c4b5fd;
            white-space: nowrap;
        }
        .kkoma-metrics {
            display: flex;
            align-items: stretch;
            gap: 10px;
        }
        .kkoma-metric {
            flex: 1;
            min-width: 0;
            padding: 12px 10px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(255,255,255,0.06);
            text-align: center;
        }
        .kkoma-metric .label {
            display: block;
            font-size: 0.68rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .kkoma-metric .value {
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            color: #e9d5ff;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        .kkoma-metric .value.done {
            color: var(--mint, #5eead4);
        }
        .kkoma-metric .value.full {
            color: #fca5a5;
        }
        .kkoma-note {
            margin-top: 10px;
            font-size: 0.7rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .admin-ore-revoke {
            border-color: rgba(248, 113, 113, 0.28);
            background: linear-gradient(180deg, rgba(248, 113, 113, 0.08) 0%, var(--card) 100%);
        }
        .admin-ore-hint {
            margin: 0 0 10px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.5;
        }
        .admin-ore-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        .admin-ore-stat {
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        .admin-ore-stat strong {
            display: block;
            font-size: 1rem;
            color: #fca5a5;
        }
        .admin-ore-stat span {
            font-size: 0.66rem;
            color: var(--muted);
        }
        .admin-ore-link {
            display: block;
            text-align: center;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(248, 113, 113, 0.16);
            border: 1px solid rgba(248, 113, 113, 0.35);
            color: #fecaca;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 700;
        }
        .admin-pending-reset {
            border-color: rgba(245, 197, 66, 0.3);
            background: linear-gradient(180deg, rgba(245, 197, 66, 0.08) 0%, var(--card) 100%);
        }
        .admin-pending-reset .admin-ore-stat strong {
            color: var(--gold);
        }
        button.admin-pending-btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(245, 197, 66, 0.16);
            border: 1px solid rgba(245, 197, 66, 0.4);
            color: #fde68a;
            font-family: inherit;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
        }
        button.admin-pending-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        button.admin-pending-btn:not(:disabled):active {
            opacity: 0.88;
        }
        .game-quit-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .game-quit-bar button {
            flex: 1;
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 700;
            padding: 12px 10px;
            border-radius: 12px;
            cursor: pointer;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--text);
        }
        .game-quit-bar button#btnGameQuit {
            border-color: rgba(248, 113, 113, 0.4);
            background: rgba(248, 113, 113, 0.12);
            color: #fecaca;
        }
        .game-quit-bar button#btnGameStart {
            border-color: rgba(94, 234, 212, 0.4);
            background: rgba(94, 234, 212, 0.12);
            color: #99f6e4;
        }
        .game-quit-bar button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .game-quit-bar button.active-quit {
            box-shadow: inset 0 0 0 1px rgba(248, 113, 113, 0.55);
        }
        .game-quit-bar button.active-play {
            box-shadow: inset 0 0 0 1px rgba(94, 234, 212, 0.55);
        }
        .game-quit-note {
            margin: -4px 0 14px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.28);
            color: #fecaca;
            font-size: 0.74rem;
            line-height: 1.45;
        }
        .game-quit-note.ok {
            background: rgba(94, 234, 212, 0.08);
            border-color: rgba(94, 234, 212, 0.28);
            color: #99f6e4;
        }
        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .action-btn {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 10px;
            background: var(--card2);
            color: var(--text);
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            text-align: left;
        }
        .action-btn small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-weight: 500;
            font-size: 0.72rem;
            line-height: 1.35;
        }
        .action-btn.mint { border-color: rgba(94,234,212,0.25); }
        .action-btn.gold { border-color: rgba(245,197,66,0.25); }
        a.action-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }
        a.action-link:active { opacity: 0.88; }
        a.action-link-full {
            width: 100%;
            text-align: left;
        }
        .guide-footer-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 4px 0 8px;
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(160deg, rgba(79, 110, 247, 0.18) 0%, rgba(12, 18, 34, 0.55) 60%);
            border: 1px solid rgba(79, 110, 247, 0.32);
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
        }
        .guide-footer-btn:active { transform: scale(0.99); opacity: 0.92; }
        .guide-footer-btn small {
            display: block;
            margin-top: 3px;
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--muted);
        }
        .guide-footer-btn .arrow {
            flex-shrink: 0;
            font-size: 0.78rem;
            color: #93a4ff;
        }
        }
        .section-hint {
            margin: 0 0 10px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .profile-period-box {
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .profile-period-box .period-label {
            font-size: 0.72rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .profile-period-box .period-value {
            font-size: 1rem;
            font-weight: 800;
            color: var(--mint);
            line-height: 1.35;
        }
        .profile-period-box.inactive .period-value {
            color: var(--muted);
            font-weight: 700;
            font-size: 0.92rem;
        }
        .profile-owned {
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .profile-owned b {
            color: var(--gold);
            font-weight: 800;
        }
        .card-title-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .card-title-row h2 {
            margin-bottom: 0;
        }
        .card-fold {
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .card-fold-bar {
            margin-bottom: 0;
            width: 100%;
        }
        .card-fold-toggle {
            appearance: none;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            padding: 0;
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex: 1;
            min-width: 0;
            text-align: left;
        }
        .card-fold-toggle h2 {
            margin: 0;
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .card-fold-toggle .chev {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 0.7rem;
            transition: transform 0.2s ease;
        }
        .card-fold.open .card-fold-toggle .chev {
            transform: rotate(180deg);
        }
        .card-fold-body {
            display: none;
            margin-top: 10px;
        }
        .card-fold.open .card-fold-body {
            display: block;
        }
        .card-fold.open .card-fold-bar {
            margin-bottom: 0;
        }
        .help-tip {
            position: relative;
            flex-shrink: 0;
        }
        .help-tip-btn {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1px solid rgba(94,234,212,0.35);
            background: rgba(94,234,212,0.12);
            color: var(--mint);
            font-size: 0.72rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }
        .help-tip-panel {
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + 8px);
            z-index: 20;
            width: min(280px, calc(100vw - 48px));
            background: #1e2a45;
            border: 1px solid rgba(94,234,212,0.28);
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: 0 12px 28px rgba(0,0,0,0.45);
        }
        .help-tip.open .help-tip-panel { display: block; }
        .help-tip-panel::before {
            content: '';
            position: absolute;
            left: 8px;
            top: -6px;
            width: 10px;
            height: 10px;
            background: #1e2a45;
            border-left: 1px solid rgba(94,234,212,0.28);
            border-top: 1px solid rgba(94,234,212,0.28);
            transform: rotate(45deg);
        }
        .help-tip-panel strong {
            display: block;
            font-size: 0.82rem;
            color: var(--mint);
            margin-bottom: 6px;
        }
        .help-tip-panel p {
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
            margin: 0 0 8px;
        }
        .help-tip-panel ul {
            margin: 0;
            padding-left: 1.1em;
            font-size: 0.74rem;
            color: var(--text);
            line-height: 1.55;
        }
        .help-tip-panel li { margin-bottom: 2px; }
        .buff-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 10px;
        }
        .buff-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            background: var(--card2);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 12px;
        }
        .buff-row .buff-name {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text);
            white-space: nowrap;
        }
        .buff-row .buff-status {
            font-size: 0.78rem;
            color: var(--muted);
            text-align: right;
            line-height: 1.35;
            font-weight: 600;
        }
        .buff-row.active .buff-status {
            color: var(--mint);
        }
        .buff-row-main {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        .buff-row .buff-owned {
            font-size: 0.7rem;
            color: var(--muted);
            font-weight: 600;
        }
        .buff-use-btn {
            flex-shrink: 0;
            border: 1px solid rgba(94,234,212,0.35);
            background: rgba(94,234,212,0.12);
            color: var(--mint);
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 0.74rem;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }
        .buff-use-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .buff-empty-hint {
            font-size: 0.74rem;
            color: var(--muted);
            margin: 0 0 10px;
            line-height: 1.45;
        }
        .buff-request-hint {
            margin: 12px 0 0;
            padding-top: 10px;
            border-top: 1px solid var(--line);
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
        }
        #jihoUseWrap { margin-top: 2px; }
        #jihoUseWrap.hidden { display: none; }
        button.action-btn {
            width: 100%;
        }
        button.action-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .swap-hint {
            margin-top: 8px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
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
            max-width: 360px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
        }
        .modal-box h3 {
            margin: 0 0 12px;
            text-align: center;
            font-size: 1rem;
        }
        .form-field { margin-bottom: 10px; }
        .form-field label {
            display: block;
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .form-field input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            background: var(--card2);
            color: var(--text);
            font-family: inherit;
            font-size: 0.9rem;
        }
        .quote-box {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.5;
            margin-bottom: 10px;
            min-height: 42px;
        }
        .modal-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .modal-actions button {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 11px 12px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-cancel {
            background: rgba(255,255,255,0.08);
            color: var(--text);
            border: 1px solid var(--line);
        }
        .btn-confirm {
            background: linear-gradient(135deg, #2dd4bf, #0d9488);
            color: #042f2e;
        }
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

        .offwork-welcome {
            display: block;
            width: 100%;
            margin: 0 0 14px;
            padding: 22px 18px;
            border-radius: 18px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            font: inherit;
            -webkit-appearance: none;
            appearance: none;
            -webkit-tap-highlight-color: transparent;
            background:
                radial-gradient(120% 140% at 50% 0%, rgba(253, 224, 171, 0.28), transparent 55%),
                linear-gradient(160deg, rgba(45, 28, 18, 0.95), rgba(20, 28, 48, 0.98));
            border: 1px solid rgba(251, 191, 36, 0.45);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.04) inset,
                0 12px 28px rgba(0, 0, 0, 0.35);
            animation: offworkPulse 2.8s ease-in-out infinite;
        }
        button.offwork-welcome:active,
        a.offwork-welcome:active {
            transform: scale(0.985);
        }
        .offwork-welcome .ow-emoji {
            font-size: 1.8rem;
            line-height: 1;
            margin-bottom: 10px;
        }
        .offwork-welcome .ow-line1 {
            margin: 0;
            font-size: 1.22rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #fde68a;
            text-shadow: 0 1px 0 rgba(0,0,0,0.35);
        }
        .offwork-welcome .ow-line2 {
            margin: 10px 0 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: #fff7ed;
            letter-spacing: -0.01em;
        }
        .offwork-welcome .ow-go {
            margin: 14px 0 0;
            display: inline-block;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.92rem;
            font-weight: 800;
            color: #1a1208;
            background: linear-gradient(135deg, #fde68a, #f59e0b);
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
        }
        .offwork-welcome.is-busy {
            opacity: 0.72;
            pointer-events: none;
        }
        @keyframes offworkPulse {
            0%, 100% { box-shadow: 0 0 0 1px rgba(255,255,255,0.04) inset, 0 12px 28px rgba(0,0,0,0.35), 0 0 0 0 rgba(251,191,36,0); }
            50% { box-shadow: 0 0 0 1px rgba(255,255,255,0.06) inset, 0 14px 32px rgba(0,0,0,0.4), 0 0 24px 2px rgba(251,191,36,0.22); }
        }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <h1>🎒 내 가방</h1>

    <?php if ($wallet_need_code) { ?>
        <div class="card lock-box">
            <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
            <strong>코드를 확인해주세요</strong><br>
            링크에 포함된 코드가 올바른지 확인하거나, 연구실에서 <code>.가방배정</code> 으로 다시 받아주세요.<br>
            <span style="opacity:0.65;font-size:0.8rem;">예) /page/wallet.php?code=XXXX</span>
        </div>
    <?php } else { ?>
        <?php if (!empty($wallet_off_work)) {
            $ow_urls = wallet_room_urls();
            $ow_bonbang = (string)($ow_urls['본방'] ?? 'https://open.kakao.com/o/pIe2XDJi');
        ?>
        <button type="button" class="offwork-welcome" id="btnCheckInBonbang" data-href="<?php echo htmlspecialchars($ow_bonbang, ENT_QUOTES, 'UTF-8'); ?>" title="출근하고 본방 열기">
            <div class="ow-emoji" aria-hidden="true">🏠</div>
            <p class="ow-line1">가족의품으로돌아오십시오.</p>
            <p class="ow-line2">모두가 기다리고있습니다.</p>
            <span class="ow-go">출근하고 본방열기 →</span>
        </button>
        <?php } ?>
        <?php
        $gq = is_array($wallet_game_quit) ? $wallet_game_quit : [];
        $gqQuit = !empty($gq['quit']);
        $gqCanStart = !empty($gq['can_start']);
        $gqAdmin = !empty($gq['is_admin']);
        $gqDays = (int)($gq['lock_days'] ?? 3);
        $gqMsg = trim((string)($gq['msg'] ?? ''));
        ?>
        <div class="game-quit-bar" id="gameQuitBar">
            <button type="button" id="btnGameQuit" class="<?php echo $gqQuit ? 'active-quit' : ''; ?>"<?php echo $gqQuit ? ' disabled' : ''; ?>>게임포기</button>
            <button type="button" id="btnGameStart" class="<?php echo !$gqQuit ? 'active-play' : ''; ?>"<?php echo (!$gqQuit || (!$gqCanStart && !$gqAdmin)) ? ' disabled' : ''; ?>>게임시작</button>
        </div>
        <div class="game-quit-note<?php echo ($gqQuit && $gqCanStart) ? ' ok' : ''; ?>" id="gameQuitNote"<?php echo $gqQuit ? '' : ' hidden'; ?>>
            <?php
            if ($gqQuit) {
                echo htmlspecialchars($gqMsg !== '' ? $gqMsg : ('게임포기 중 · 홀짝·냥카라·채굴·강화·보스·마켓·금고·마피아 숨김'), ENT_QUOTES, 'UTF-8');
            }
            ?>
        </div>

        <div class="card" id="profileCard">
            <div class="profile">
                <div>
                    <div class="nick-row">
                        <div class="nick" id="wNick"><?php echo htmlspecialchars($wallet_member['nick'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <button type="button" class="carrot-alarm-btn" id="btnCarrotAlarm" title="긴급알람" aria-label="긴급알람">🥕</button>
                    </div>
                    <div class="profile-sub" id="wSub">
                        <?php
                        $sub = [];
                        if ($wallet_member['title'] !== '') $sub[] = $wallet_member['title'];
                        if ($wallet_member['style'] !== '') $sub[] = $wallet_member['style'];
                        if ($wallet_member['weapon'] !== '없음') $sub[] = $wallet_member['weapon'];
                        echo htmlspecialchars(implode(' · ', $sub) ?: '—', ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                </div>
                <div class="rank" id="wRank"><?php echo htmlspecialchars($wallet_member['rank_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="balance-grid">
                <div class="balance-box new">
                    <div class="label">본방냥</div>
                    <div class="amount" id="wNewpoint"><?php echo htmlspecialchars($wallet_member['newpoint_fmt'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="balance-box game">
                    <div class="label">게임냥</div>
                    <div class="amount" id="wPoint"><?php echo htmlspecialchars($wallet_member['point_fmt'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-pill">보호<b id="wProtect"><?php echo (int)$wallet_member['protect']; ?></b></div>
                <a class="stat-pill" href="/page/today_tasu.php<?php echo htmlspecialchars(isset($q) ? $q : wallet_code_query($wallet_code), ENT_QUOTES, 'UTF-8'); ?>" title="오늘 평타·생타 순위">오늘타수<b id="wTasu"><?php echo htmlspecialchars((string)($wallet_member['tasu_fmt'] ?? ((int)($wallet_member['tasu'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></b></a>
                <div class="stat-pill in">오늘 +<b id="wTodayIn">…</b></div>
                <div class="stat-pill out">오늘 -<b id="wTodayOut">…</b></div>
            </div>
        </div>

        <div class="card">
            <?php $wallet_room_urls = wallet_room_urls(); ?>
            <div class="card-title-row nav-title-row">
                <h2>🔗 바로가기</h2>
                <div class="nav-room-links">
                    <a class="nav-room-link" href="<?php echo htmlspecialchars($wallet_room_urls['본방'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">본방가기</a>
                    <span class="nav-room-sep" aria-hidden="true">·</span>
                    <button type="button" class="nav-room-link" id="btnPromoRoom" data-href="<?php echo htmlspecialchars($wallet_room_urls['홍보방'], ENT_QUOTES, 'UTF-8'); ?>">홍보방가기</button>
                </div>
            </div>
            <?php if (!empty($wallet_off_work)) { ?>
            <p class="section-hint" style="margin:0;padding:8px 2px 2px;opacity:0.85">🚌 퇴근 중 · 바로가기는 <b>마피아</b>만 이용할 수 있어요. 꼬맨틀·버프·프변·냥보내기·냥바꾸기도 숨겨져 있어요.</p>
            <?php } ?>
            <?php
            $wallet_nav_top = [];
            $wallet_nav_rest = [];
            foreach ($wallet_nav_links as $link) {
                if (($link['nav_group'] ?? '') === 'top') {
                    $wallet_nav_top[] = $link;
                } else {
                    $wallet_nav_rest[] = $link;
                }
            }
            if ($wallet_nav_top) { ?>
            <div class="nav-grid">
                <?php foreach ($wallet_nav_top as $link) {
                    echo wallet_nav_link_html($link);
                } ?>
            </div>
            <?php }
            if ($wallet_nav_top && $wallet_nav_rest) { ?>
            <hr class="nav-grid-sep">
            <?php }
            if ($wallet_nav_rest) { ?>
            <div class="nav-grid">
                <?php foreach ($wallet_nav_rest as $link) {
                    echo wallet_nav_link_html($link);
                } ?>
            </div>
            <?php } ?>
        </div>

        <?php if (empty($wallet_off_work)) {
        $kkoma_q = isset($q) ? $q : (isset($wallet_code) && $wallet_code !== '' ? wallet_code_query($wallet_code) : '');
        $kkoma_rank = (int)($wallet_kkoma['rank'] ?? 0);
        $kkoma_done = (int)($wallet_kkoma['done'] ?? 0);
        $kkoma_limit = (int)($wallet_kkoma['limit'] ?? 5);
        $kkoma_completed = !empty($wallet_kkoma['completed']);
        $kkoma_full = !empty($wallet_kkoma['full']);
        $kkoma_count_cls = $kkoma_full ? 'full' : 'done';
        $kkoma_note = $kkoma_completed
            ? '오늘 완료했어요. 내일 다시 도전할 수 있어요.'
            : ($kkoma_full
                ? '오늘 참여 인원이 마감됐어요. (하루 ' . $kkoma_limit . '명)'
                : '목표 순위 ' . number_format($kkoma_rank) . ' 필수 달성 후 연구실 `.꼬맨완료` · 하루 ' . $kkoma_limit . '명');
        ?>
        <div class="card card-fold kkoma-card" data-fold-key="wallet_kkoma">
            <div class="card-title-row card-fold-bar">
                <button type="button" class="card-fold-toggle" aria-expanded="false">
                    <h2>🧠 오늘의 꼬맨틀</h2>
                    <span class="chev" aria-hidden="true">▼</span>
                </button>
            </div>
            <div class="card-fold-body">
                <div class="kkoma-metrics">
                    <div class="kkoma-metric">
                        <span class="label">오늘 유사도 순위</span>
                        <span class="value" id="kkomaRank"><?php echo $kkoma_rank > 0 ? number_format($kkoma_rank) : '—'; ?></span>
                    </div>
                    <div class="kkoma-metric">
                        <span class="label">오늘 완료</span>
                        <span class="value <?php echo htmlspecialchars($kkoma_count_cls, ENT_QUOTES, 'UTF-8'); ?>" id="kkomaCount"><?php echo (int)$kkoma_done; ?>/<?php echo (int)$kkoma_limit; ?></span>
                    </div>
                </div>
                <p class="kkoma-note" id="kkomaNote"><?php echo htmlspecialchars($kkoma_note, ENT_QUOTES, 'UTF-8'); ?></p>
                <a class="kkoma-play" href="/page/kkomaentl.php<?php echo htmlspecialchars($kkoma_q, ENT_QUOTES, 'UTF-8'); ?>">
                    <span>꼬맨틀 플레이</span>
                    <span>열기 →</span>
                </a>
            </div>
        </div>

        <div class="card card-fold" data-fold-key="wallet_buff">
            <div class="card-title-row card-fold-bar">
                <button type="button" class="card-fold-toggle" aria-expanded="false">
                    <h2>✨ 버프 현황</h2>
                    <span class="chev" aria-hidden="true">▼</span>
                </button>
                <div class="help-tip" id="buffHelpTip">
                    <button type="button" class="help-tip-btn" id="btnBuffHelp" aria-label="버프 설명" aria-expanded="false">?</button>
                    <div class="help-tip-panel" role="tooltip" id="buffHelpPanel">
                        <strong>✨ 버프란?</strong>
                        <p>적용 중인 버프와 만료 시각을 보여줘요.</p>
                        <ul>
                            <li>프로필변경 · 프변 1개 = 3일 (이모티콘·텍스트·닉네임 뒤 장식 중 하나)</li>
                            <li>지호 · 1개 = 1시간 (타수 2배 · 양도 횟수 추가)</li>
                            <li>마법 · 타수 2배 · 양도 횟수 추가 (추가 시 1일 차감)</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-fold-body">
            <div class="buff-list" id="buffList">
                <div class="buff-row<?php echo !empty($wallet_buff['profile']['active']) ? ' active' : ''; ?>" id="buffRowProfile">
                    <div class="buff-row-main">
                        <span class="buff-name">프로필변경</span>
                        <span class="buff-status" id="buffStatusProfile"><?php echo htmlspecialchars($wallet_buff['profile']['status_text'] ?? '미적용', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="buff-owned">보유 프변 <b id="wBuffProfileOwned"><?php echo (int)($wallet_buff['profile']['owned'] ?? $wallet_profile['owned'] ?? 0); ?></b>개 · 1개=3일</span>
                    </div>
                    <button type="button" class="buff-use-btn" id="btnBuffProfileUse"<?php echo ((int)($wallet_buff['profile']['owned'] ?? $wallet_profile['owned'] ?? 0) < 1) ? ' disabled' : ''; ?>>프변 사용</button>
                </div>
                <div class="buff-row<?php echo !empty($wallet_buff['jiho']['active']) ? ' active' : ''; ?>" id="buffRowJiho">
                    <span class="buff-name">지호</span>
                    <span class="buff-status" id="buffStatusJiho"><?php echo htmlspecialchars($wallet_buff['jiho']['status_text'] ?? '미적용', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="buff-row<?php echo !empty($wallet_buff['magic']['active']) ? ' active' : ''; ?>" id="buffRowMagic">
                    <span class="buff-name">마법</span>
                    <span class="buff-status" id="buffStatusMagic"><?php echo htmlspecialchars($wallet_buff['magic']['status_text'] ?? '미적용', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div id="jihoUseWrap" class="<?php echo !empty($wallet_buff['show_jiho_use']) ? '' : 'hidden'; ?>">
                <p class="buff-empty-hint">적용 중인 버프가 없어요. 지호를 사용해볼까요? (보유 <b id="wJihoOwned"><?php echo (int)($wallet_buff['jiho']['owned'] ?? 0); ?></b>개)</p>
                <button type="button" class="action-btn gold" id="btnJihoUse"<?php echo ((int)($wallet_buff['jiho']['owned'] ?? 0) < 1) ? ' disabled' : ''; ?>>
                    지호 아이템 사용하기
                    <small>1개 = 1시간 · 타수 2배 · 양도 횟수 추가</small>
                </button>
            </div>
            <p class="buff-request-hint">지호/마법은 마법 무기를 가진 친구에게 요청하자</p>
            </div>
        </div>

        <div class="card card-fold" data-fold-key="wallet_transfer">
            <div class="card-title-row card-fold-bar">
                <button type="button" class="card-fold-toggle" aria-expanded="false">
                    <h2>🎁 친구에게 냥 보내기</h2>
                    <span class="chev" aria-hidden="true">▼</span>
                </button>
            </div>
            <div class="card-fold-body">
            <p class="section-hint">본방냥·게임냥을 친구에게 양도합니다.</p>
            <a class="action-btn mint action-link action-link-full" href="/page/wallet_transfer.php<?php echo htmlspecialchars(wallet_code_query($wallet_code), ENT_QUOTES, 'UTF-8'); ?>">
                친구에게 냥 보내기
                <small>받는 닉네임 · 금액 입력</small>
            </a>
            </div>
        </div>

        <div class="card card-fold" data-fold-key="wallet_swap">
            <div class="card-title-row card-fold-bar">
                <button type="button" class="card-fold-toggle" aria-expanded="false">
                    <h2>💱 본방냥을 게임냥으로 바꾸기</h2>
                    <span class="chev" aria-hidden="true">▼</span>
                </button>
            </div>
            <div class="card-fold-body">
            <p class="section-hint">본방냥 ↔ 게임냥 스왑 · 환율 적용</p>
            <a class="action-btn gold action-link action-link-full" href="/page/wallet_swap.php<?php echo htmlspecialchars(wallet_code_query($wallet_code), ENT_QUOTES, 'UTF-8'); ?>">
                본방냥을 게임냥으로 바꾸기
                <small>스왑 페이지로 이동</small>
            </a>
            </div>
        </div>
        <?php } ?>

        <?php /* 광물·채굴 관리는 민호 바로가기「관리」→ /page/wallet_admin.php */ ?>

        <a class="guide-footer-btn" href="/guide/">
            <span>
                📖 우리방 사용설명서
                <small>명령어 · 게임 · 보스전 · 아이템 안내</small>
            </span>
            <span class="arrow">열기 →</span>
        </a>

        <div class="toast" id="toast"></div>
        <div id="navTransit" aria-live="polite"><div class="spin" aria-hidden="true"></div><span id="navTransitMsg">이동 중…</span></div>

        <div class="modal-overlay" id="profileModal" onclick="if(event.target===this)window.walletCloseProfileModal&&walletCloseProfileModal()">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>🖍 프로필변경 사용</h3>
                <p class="section-hint" style="margin-bottom:12px;">프변 1개당 3일 적용·연장됩니다.</p>
                <div class="form-field">
                    <label for="profileAmount">사용할 개수</label>
                    <input type="number" id="profileAmount" min="1" max="99" value="1" inputmode="numeric">
                </div>
                <div class="quote-box" id="profileQuote">보유 0개 · 사용 시 +3일</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="btnProfileCancel">취소</button>
                    <button type="button" class="btn-confirm" id="btnProfileConfirm">사용</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="jihoModal" onclick="if(event.target===this)window.walletCloseJihoModal&&walletCloseJihoModal()">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>💦 지호 사용</h3>
                <p class="section-hint" style="margin-bottom:12px;">지호 1개당 1시간 적용·연장됩니다.</p>
                <div class="form-field">
                    <label for="jihoAmount">사용할 개수</label>
                    <input type="number" id="jihoAmount" min="1" max="99" value="1" inputmode="numeric">
                </div>
                <div class="quote-box" id="jihoQuote">보유 0개 · 사용 시 +1시간</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="btnJihoCancel">취소</button>
                    <button type="button" class="btn-confirm" id="btnJihoConfirm">사용</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="promoRoomModal" onclick="if(event.target===this)window.walletClosePromoRoomModal&&walletClosePromoRoomModal()">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>📢 홍보방 안내</h3>
                <div class="promo-room-body">
                    <p>홍보방에 들어오는 친구들을 <span class="emph">본방으로 유도</span>하는 게 목적이지만,<br>홍보방에서 우리방 <span class="emph">게임 관련 이야기</span>를 할 수 있는 방이에요.</p>
                    <ul>
                        <li><span class="emph">본방</span>에서는 게임 이야기 금지</li>
                        <li>홍보방 입장 시 <span class="emph">본방닉 2자리</span>로 입장할 것</li>
                        <li>그래야 게임 내역이 연동돼요</li>
                    </ul>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="btnPromoRoomCancel">닫기</button>
                    <button type="button" class="btn-confirm" id="btnPromoRoomGo">홍보방 가기</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="emergencyModal" onclick="if(event.target===this)window.walletCloseEmergencyModal&&walletCloseEmergencyModal()">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>🥕 긴급알람</h3>
                <p class="emergency-desc">오치소를 가거나 채팅이 불가한 상황에<br>당근을 흔들어 공창에 알람이 가도록 할 수 있는 기능입니다.</p>
                <span class="emergency-days-label">이모티콘</span>
                <div class="emergency-days" id="emergencyEmojis" role="group" aria-label="알람 이모티콘">
                    <?php foreach (wallet_emergency_alarm_emojis() as $em) { ?>
                    <button type="button" class="emergency-emoji-btn<?php echo $em === '🥕' ? ' active' : ''; ?>" data-emoji="<?php echo htmlspecialchars($em, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($em, ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php } ?>
                </div>
                <span class="emergency-days-label">기간 선택</span>
                <div class="emergency-days" id="emergencyDays" role="group" aria-label="오치소 기간">
                    <?php foreach ([1, 3, 5, 7, 14, 30, 60] as $d) { ?>
                    <button type="button" class="emergency-day-btn<?php echo $d === 3 ? ' active' : ''; ?>" data-days="<?php echo (int)$d; ?>"><?php echo (int)$d; ?>일</button>
                    <?php } ?>
                </div>
                <div class="emergency-preview" id="emergencyPreview"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="btnEmergencyCancel">닫기</button>
                    <button type="button" class="btn-confirm carrot" id="btnEmergencySend">긴급알람전송</button>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<?php if (!$wallet_need_code) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($wallet_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($wallet_api_entry, JSON_UNESCAPED_UNICODE); ?>;
    var profileOwned = <?php echo (int)($wallet_profile['owned'] ?? 0); ?>;
    var jihoOwned = <?php echo (int)($wallet_buff['jiho']['owned'] ?? 0); ?>;

    function setBuffRow(rowId, statusId, item) {
        var row = document.getElementById(rowId);
        var status = document.getElementById(statusId);
        if (!item) return;
        if (status) status.textContent = item.status_text || '미적용';
        if (row) {
            if (item.active) row.classList.add('active');
            else row.classList.remove('active');
        }
    }

    function applyBuff(b) {
        if (!b) return;
        setBuffRow('buffRowProfile', 'buffStatusProfile', b.profile);
        setBuffRow('buffRowJiho', 'buffStatusJiho', b.jiho);
        setBuffRow('buffRowMagic', 'buffStatusMagic', b.magic);
        if (b.profile && typeof b.profile.owned !== 'undefined') {
            profileOwned = parseInt(b.profile.owned, 10) || 0;
            var buffOwnedEl = document.getElementById('wBuffProfileOwned');
            if (buffOwnedEl) buffOwnedEl.textContent = profileOwned;
            var buffBtn = document.getElementById('btnBuffProfileUse');
            if (buffBtn) buffBtn.disabled = profileOwned < 1;
            var cardBtn = document.getElementById('btnProfileUse');
            if (cardBtn) cardBtn.disabled = profileOwned < 1;
            updateProfileQuote();
        }
        jihoOwned = (b.jiho && parseInt(b.jiho.owned, 10)) || 0;
        var ownedEl = document.getElementById('wJihoOwned');
        if (ownedEl) ownedEl.textContent = jihoOwned;
        var wrap = document.getElementById('jihoUseWrap');
        var btn = document.getElementById('btnJihoUse');
        if (wrap) {
            if (b.show_jiho_use) wrap.classList.remove('hidden');
            else wrap.classList.add('hidden');
        }
        if (btn) btn.disabled = jihoOwned < 1;
        updateJihoQuote();
    }

    function applyProfile(p) {
        if (!p) return;
        profileOwned = parseInt(p.owned, 10) || 0;
        var ownedEl = document.getElementById('wProfileOwned');
        var periodEl = document.getElementById('wProfilePeriod');
        var boxEl = document.getElementById('wProfilePeriodBox');
        var btn = document.getElementById('btnProfileUse');
        if (ownedEl) ownedEl.textContent = profileOwned;
        if (periodEl) periodEl.textContent = p.period_text || '미적용';
        if (boxEl) {
            if (p.active) boxEl.classList.remove('inactive');
            else boxEl.classList.add('inactive');
        }
        if (btn) btn.disabled = profileOwned < 1;
        var buffOwnedEl = document.getElementById('wBuffProfileOwned');
        if (buffOwnedEl) buffOwnedEl.textContent = profileOwned;
        var buffBtn = document.getElementById('btnBuffProfileUse');
        if (buffBtn) buffBtn.disabled = profileOwned < 1;
        updateProfileQuote();
    }

    function applyStatus(j) {
        if (!j.member) return;
        var m = j.member;
        document.getElementById('wNewpoint').textContent = m.newpoint_fmt;
        document.getElementById('wPoint').textContent = m.point_fmt;
        document.getElementById('wProtect').textContent = m.protect;
        document.getElementById('wTasu').textContent = m.tasu_fmt || m.tasu;
        document.getElementById('wRank').textContent = m.rank_name;
        var sub = [];
        if (m.title) sub.push(m.title);
        if (m.style) sub.push(m.style);
        if (m.weapon && m.weapon !== '없음') sub.push(m.weapon);
        document.getElementById('wSub').textContent = sub.length ? sub.join(' · ') : '—';
        if (j.today) {
            document.getElementById('wTodayIn').textContent = j.today.in_fmt;
            document.getElementById('wTodayOut').textContent = j.today.out_fmt;
        }
        if (j.profile) applyProfile(j.profile);
        if (j.buff) applyBuff(j.buff);
    }

    function ajax(action, extra, cb, silent) {
        var app = document.getElementById('app');
        if (!silent) {
            app.classList.add('loading');
        }
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('wallet_code', CODE);
        if (extra) Object.keys(extra).forEach(function(k) { body.set(k, extra[k]); });
        var url = API;
        if (CODE) {
            url += '?code=' + encodeURIComponent(CODE);
        }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (!silent) {
                    app.classList.remove('loading');
                }
                if (!j || !j.ok) {
                    if (!silent) showToast((j && (j.data || j.msg)) || '오류');
                    return;
                }
                if (cb) cb(j);
            })
            .catch(function() {
                if (!silent) {
                    app.classList.remove('loading');
                }
                if (!silent) showToast('통신 오류');
            });
    }

    function showToast(msg) {
        var t = document.getElementById('toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(t._timer);
        t._timer = setTimeout(function() { t.classList.remove('show'); }, 2800);
    }

    function updateProfileQuote() {
        var amountEl = document.getElementById('profileAmount');
        var quoteEl = document.getElementById('profileQuote');
        if (!amountEl || !quoteEl) return;
        var n = parseInt(amountEl.value, 10) || 1;
        if (n < 1) n = 1;
        var days = n * 3;
        quoteEl.textContent = '보유 ' + profileOwned + '개 · 사용 시 +' + days + '일';
    }

    function updateJihoQuote() {
        var amountEl = document.getElementById('jihoAmount');
        var quoteEl = document.getElementById('jihoQuote');
        if (!amountEl || !quoteEl) return;
        var n = parseInt(amountEl.value, 10) || 1;
        if (n < 1) n = 1;
        quoteEl.textContent = '보유 ' + jihoOwned + '개 · 사용 시 +' + n + '시간';
    }

    function openProfileModal() {
        if (profileOwned < 1) {
            showToast('보유한 프변이 없어요');
            return;
        }
        var amountEl = document.getElementById('profileAmount');
        if (amountEl) {
            amountEl.value = '1';
            amountEl.max = String(Math.min(99, profileOwned));
        }
        updateProfileQuote();
        var modal = document.getElementById('profileModal');
        if (modal) modal.classList.add('open');
    }

    function closeProfileModal() {
        var modal = document.getElementById('profileModal');
        if (modal) modal.classList.remove('open');
    }
    window.walletCloseProfileModal = closeProfileModal;

    function openPromoRoomModal() {
        var modal = document.getElementById('promoRoomModal');
        if (modal) modal.classList.add('open');
    }

    function closePromoRoomModal() {
        var modal = document.getElementById('promoRoomModal');
        if (modal) modal.classList.remove('open');
    }
    window.walletClosePromoRoomModal = closePromoRoomModal;

    function goPromoRoom() {
        var btn = document.getElementById('btnPromoRoom') || document.getElementById('btnPromoRoomFab');
        var href = btn ? (btn.getAttribute('data-href') || '') : '';
        if (!href) {
            showToast('홍보방 링크를 찾을 수 없어요');
            return;
        }
        var w = window.open(href, '_blank');
        if (w) w.opener = null;
        closePromoRoomModal();
    }

    function openJihoModal() {
        if (jihoOwned < 1) {
            showToast('보유한 지호가 없어요');
            return;
        }
        var amountEl = document.getElementById('jihoAmount');
        if (amountEl) {
            amountEl.value = '1';
            amountEl.max = String(Math.min(99, jihoOwned));
        }
        updateJihoQuote();
        var modal = document.getElementById('jihoModal');
        if (modal) modal.classList.add('open');
    }

    function closeJihoModal() {
        var modal = document.getElementById('jihoModal');
        if (modal) modal.classList.remove('open');
    }
    window.walletCloseJihoModal = closeJihoModal;

    var emergencyDays = 3;
    var emergencyEmoji = '🥕';
    var emergencyEmojiList = <?php echo json_encode(wallet_emergency_alarm_emojis(), JSON_UNESCAPED_UNICODE); ?>;

    function emergencyPreviewText() {
        var nickEl = document.getElementById('wNick');
        var nick = nickEl ? (nickEl.textContent || '').trim() : '';
        if (!nick) nick = '닉네임';
        var d = emergencyDays || 3;
        var em = emergencyEmoji || '🥕';
        return em + em + nick + em + em + '\n나 ' + d + '일 오치소감ㅜㅜ\n' + em + em + em + em + em;
    }

    function syncEmergencyDaysUi() {
        var wrap = document.getElementById('emergencyDays');
        if (wrap) {
            Array.prototype.forEach.call(wrap.querySelectorAll('.emergency-day-btn'), function(btn) {
                var d = parseInt(btn.getAttribute('data-days'), 10) || 0;
                if (d === emergencyDays) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }
        var emoWrap = document.getElementById('emergencyEmojis');
        if (emoWrap) {
            Array.prototype.forEach.call(emoWrap.querySelectorAll('.emergency-emoji-btn'), function(btn) {
                var em = btn.getAttribute('data-emoji') || '';
                if (em === emergencyEmoji) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }
        var preview = document.getElementById('emergencyPreview');
        if (preview) preview.textContent = emergencyPreviewText();
    }

    function openEmergencyModal() {
        if (!emergencyDays) emergencyDays = 3;
        if (!emergencyEmoji) emergencyEmoji = '🥕';
        syncEmergencyDaysUi();
        var modal = document.getElementById('emergencyModal');
        if (modal) modal.classList.add('open');
    }

    function closeEmergencyModal() {
        var modal = document.getElementById('emergencyModal');
        if (modal) modal.classList.remove('open');
    }
    window.walletCloseEmergencyModal = closeEmergencyModal;

    function doEmergencyAlarm() {
        var d = emergencyDays || 0;
        if ([1, 3, 5, 7, 14, 30, 60].indexOf(d) < 0) {
            showToast('기간을 선택해주세요');
            return;
        }
        var em = emergencyEmoji || '🥕';
        if (!emergencyEmojiList || emergencyEmojiList.indexOf(em) < 0) {
            em = '🥕';
        }
        closeEmergencyModal();
        ajax('emergency_alarm', { days: String(d), emoji: em }, function(j) {
            showToast(j.data || '긴급알람 전송 완료');
        });
    }

    function doProfileUse() {
        var amountEl = document.getElementById('profileAmount');
        var n = amountEl ? (parseInt(amountEl.value, 10) || 1) : 1;
        if (n < 1) n = 1;
        if (n > profileOwned) {
            showToast('프변 아이템이 부족해요');
            return;
        }
        closeProfileModal();
        ajax('profile_use', { amount: String(n) }, function(j) {
            if (j.profile) applyProfile(j.profile);
            if (j.buff) applyBuff(j.buff);
            var msg = (j.data || '프로필변경 적용!').replace(/\n/g, ' · ');
            showToast(msg);
        });
    }

    function doJihoUse() {
        var amountEl = document.getElementById('jihoAmount');
        var n = amountEl ? (parseInt(amountEl.value, 10) || 1) : 1;
        if (n < 1) n = 1;
        if (n > jihoOwned) {
            showToast('지호 아이템이 부족해요');
            return;
        }
        closeJihoModal();
        ajax('jiho_use', { amount: String(n) }, function(j) {
            if (j.buff) applyBuff(j.buff);
            if (j.profile) applyProfile(j.profile);
            var msg = (j.data || '지호 적용!').replace(/\n/g, ' · ');
            showToast(msg);
        });
    }

    function bindHelpTip(wrapId, btnId) {
        var helpWrap = document.getElementById(wrapId);
        var btnHelp = document.getElementById(btnId);
        if (!btnHelp || !helpWrap) return;
        btnHelp.addEventListener('click', function(e) {
            e.stopPropagation();
            var open = helpWrap.classList.toggle('open');
            btnHelp.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function(e) {
            if (!helpWrap.classList.contains('open')) return;
            if (helpWrap.contains(e.target)) return;
            helpWrap.classList.remove('open');
            btnHelp.setAttribute('aria-expanded', 'false');
        });
    }

    var btnBuffProfile = document.getElementById('btnBuffProfileUse');
    var btnCancel = document.getElementById('btnProfileCancel');
    var btnConfirm = document.getElementById('btnProfileConfirm');
    var amountEl = document.getElementById('profileAmount');
    var btnJiho = document.getElementById('btnJihoUse');
    var btnJihoCancel = document.getElementById('btnJihoCancel');
    var btnJihoConfirm = document.getElementById('btnJihoConfirm');
    var jihoAmountEl = document.getElementById('jihoAmount');
    if (btnBuffProfile) btnBuffProfile.addEventListener('click', openProfileModal);
    if (btnCancel) btnCancel.addEventListener('click', closeProfileModal);
    if (btnConfirm) btnConfirm.addEventListener('click', doProfileUse);
    if (amountEl) amountEl.addEventListener('input', updateProfileQuote);
    if (btnJiho) btnJiho.addEventListener('click', openJihoModal);
    if (btnJihoCancel) btnJihoCancel.addEventListener('click', closeJihoModal);
    if (btnJihoConfirm) btnJihoConfirm.addEventListener('click', doJihoUse);
    if (jihoAmountEl) jihoAmountEl.addEventListener('input', updateJihoQuote);

    var btnPromoRoom = document.getElementById('btnPromoRoom');
    var btnPromoRoomCancel = document.getElementById('btnPromoRoomCancel');
    var btnPromoRoomGo = document.getElementById('btnPromoRoomGo');
    if (btnPromoRoom) btnPromoRoom.addEventListener('click', openPromoRoomModal);
    if (btnPromoRoomCancel) btnPromoRoomCancel.addEventListener('click', closePromoRoomModal);
    if (btnPromoRoomGo) btnPromoRoomGo.addEventListener('click', goPromoRoom);

    var btnCarrot = document.getElementById('btnCarrotAlarm');
    var btnEmergencyCancel = document.getElementById('btnEmergencyCancel');
    var btnEmergencySend = document.getElementById('btnEmergencySend');
    var emergencyDaysWrap = document.getElementById('emergencyDays');
    var emergencyEmojiWrap = document.getElementById('emergencyEmojis');
    if (btnCarrot) btnCarrot.addEventListener('click', openEmergencyModal);
    if (btnEmergencyCancel) btnEmergencyCancel.addEventListener('click', closeEmergencyModal);
    if (btnEmergencySend) btnEmergencySend.addEventListener('click', doEmergencyAlarm);

    var btnCheckIn = document.getElementById('btnCheckInBonbang');
    if (btnCheckIn) {
        btnCheckIn.addEventListener('click', function() {
            if (btnCheckIn.classList.contains('is-busy')) return;
            btnCheckIn.classList.add('is-busy');
            var href = btnCheckIn.getAttribute('data-href') || '';
            ajax('check_in', {}, function(j) {
                showToast((j.data || '출근 완료').replace(/\n/g, ' · '));
                var url = (j.bonbang_url || href || '').trim();
                setTimeout(function() {
                    if (url) {
                        window.open(url, '_blank', 'noopener,noreferrer');
                    }
                    // 출근 후 바로가기 등 복구
                    location.reload();
                }, 450);
            });
            // ajax 실패 시 busy 해제 — fetch catch는 silent 아닐 때만 toast
            setTimeout(function() {
                if (btnCheckIn) btnCheckIn.classList.remove('is-busy');
            }, 4000);
        });
    }

    if (emergencyDaysWrap) {
        emergencyDaysWrap.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest ? e.target.closest('.emergency-day-btn') : null;
            if (!btn || !emergencyDaysWrap.contains(btn)) return;
            var d = parseInt(btn.getAttribute('data-days'), 10) || 0;
            if ([1, 3, 5, 7, 14, 30, 60].indexOf(d) < 0) return;
            emergencyDays = d;
            syncEmergencyDaysUi();
        });
    }
    if (emergencyEmojiWrap) {
        emergencyEmojiWrap.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest ? e.target.closest('.emergency-emoji-btn') : null;
            if (!btn || !emergencyEmojiWrap.contains(btn)) return;
            var em = btn.getAttribute('data-emoji') || '';
            if (!em || (emergencyEmojiList && emergencyEmojiList.indexOf(em) < 0)) return;
            emergencyEmoji = em;
            syncEmergencyDaysUi();
        });
    }
    bindHelpTip('buffHelpTip', 'btnBuffHelp');

    var GAME_QUIT_DAYS = <?php echo (int)($wallet_game_quit['lock_days'] ?? 3); ?>;
    var GAME_QUIT_ADMIN = <?php echo !empty($wallet_game_quit['is_admin']) ? 'true' : 'false'; ?>;

    function postGameQuitAction(action) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('wallet_code', CODE);
        var url = API;
        if (CODE) url += (url.indexOf('?') >= 0 ? '&' : '?') + 'code=' + encodeURIComponent(CODE);
        var app = document.getElementById('app');
        if (app) app.classList.add('loading');
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (app) app.classList.remove('loading');
                return j;
            })
            .catch(function() {
                if (app) app.classList.remove('loading');
                return null;
            });
    }

    var btnGameQuit = document.getElementById('btnGameQuit');
    var btnGameStart = document.getElementById('btnGameStart');
    if (btnGameQuit) {
        btnGameQuit.addEventListener('click', function() {
            var warn = GAME_QUIT_ADMIN
                ? '게임포기 할까요?\n\n홀짝·냥카라·채굴·강화·보스·마켓·금고·마피아 바로가기가 숨겨집니다.\n관리자는 제한 없이 언제든 게임시작할 수 있어요.'
                : ('게임포기 할까요?\n\n'
                    + '⚠ ' + GAME_QUIT_DAYS + '일간 게임시작을 할 수 없습니다.\n'
                    + '홀짝·냥카라·채굴·강화·보스·마켓·금고·마피아 바로가기가 숨겨집니다.');
            if (!window.confirm(warn)) return;
            if (!GAME_QUIT_ADMIN && !window.confirm('정말 게임포기 할까요?\n' + GAME_QUIT_DAYS + '일 동안 시작할 수 없어요.')) return;
            btnGameQuit.disabled = true;
            postGameQuitAction('game_quit').then(function(j) {
                if (!j || !j.ok) {
                    btnGameQuit.disabled = false;
                    showToast((j && (j.data || j.msg)) || '오류');
                    return;
                }
                showToast((j.data || '게임포기 했어요.').replace(/\n/g, ' · '));
                setTimeout(function() { location.reload(); }, 600);
            });
        });
    }
    if (btnGameStart) {
        btnGameStart.addEventListener('click', function() {
            if (!window.confirm('게임을 다시 시작할까요?\n숨겨진 바로가기가 다시 표시됩니다.')) return;
            btnGameStart.disabled = true;
            postGameQuitAction('game_start').then(function(j) {
                if (!j || !j.ok) {
                    btnGameStart.disabled = false;
                    showToast((j && (j.data || j.msg)) || '오류');
                    return;
                }
                showToast((j.data || '게임시작 했어요.').replace(/\n/g, ' · '));
                setTimeout(function() { location.reload(); }, 600);
            });
        });
    }

    document.querySelectorAll('.card-fold[data-fold-key]').forEach(function(fold) {
        var key = fold.getAttribute('data-fold-key') || '';
        var btn = fold.querySelector('.card-fold-toggle');
        if (!btn || !key) return;
        try {
            if (localStorage.getItem('wallet_fold_' + key) === '1') {
                fold.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        } catch (e) {}
        btn.addEventListener('click', function() {
            var open = fold.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            try { localStorage.setItem('wallet_fold_' + key, open ? '1' : '0'); } catch (e) {}
        });
    });

    var navTransit = document.getElementById('navTransit');
    var navTransitMsg = document.getElementById('navTransitMsg');
    function showNavTransit(msg) {
        if (navTransitMsg && msg) navTransitMsg.textContent = msg;
        if (navTransit) navTransit.classList.add('show');
    }
    document.querySelectorAll('a.nav-mining').forEach(function(a) {
        a.addEventListener('click', function() {
            showNavTransit('채굴 여는 중…');
        });
    });
    document.querySelectorAll('a.nav-locked[data-lock-alert]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var msg = a.getAttribute('data-lock-alert') || '';
            if (msg) {
                alert(msg);
            }
        });
    });
    // 보스는 HTML에 상태 동봉되어 진입이 빠름 · 여는 중 오버레이 생략
    // CSS/JS prefetch (캐시 bust)
    if (document.querySelector('a.nav-boss')) {
        ['/css/boss.css', '/js/boss.js?v=20260722b'].forEach(function(u) {
            try {
                var l = document.createElement('link');
                l.rel = 'prefetch';
                l.as = u.indexOf('.css') !== -1 ? 'style' : 'script';
                l.href = u;
                document.head.appendChild(l);
            } catch (e2) {}
        });
    }

    ajax('status', null, applyStatus, true);
})();
</script>
<?php
    if (!empty($wallet_member) && !empty($wallet_code)) {
      require_once __DIR__ . '/wallet_nav_fab.inc.php';
      wallet_nav_fab_render([
        'code' => $wallet_code,
        'show_back' => false,
        'show_bag' => false,
        'newpoint' => $wallet_member['newpoint'] ?? null,
        'newpoint_fmt' => (string)($wallet_member['newpoint_fmt'] ?? ''),
      ]);
    }
} ?>
</body>
</html>
