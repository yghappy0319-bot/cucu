<?php
/**
 * 개인 지갑 웹 (홀짝웹과 동일 code 인증)
 *
 * URL:
 *   /api/game/wallet_web.php?code=XXXX
 *   /page/wallet.php?code=XXXX
 *
 * AJAX:
 *   action=status       — 잔액·오늘 요약
 *   action=swap_quote   — 스왑 견적 (dir=np2pt|pt2np, amount)
 *   action=swap_np      — 본방냥→게임냥 (amount 비우면 전액)
 *   action=swap_pt      — 게임냥→본방냥 (전액)
 *   action=transfer_np  — 본방냥 양도 (receiver, amount)
 *   action=transfer_pt  — 게임냥 양도 (receiver, amount)
 *   action=progress_extend — 지목/강일 연장
 *   action=progress_cancel  — 강일 취소
 *
 * 아이템 상점: /page/item_shop.php (api/game/item_shop_web.php)
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
    $row = db_select("SELECT idx, name, point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) {
        return null;
    }
    return $row;
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
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
    $extra = @db_select("SELECT newpoint, title, style, item, enhance FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (is_array($extra)) {
        $row = array_merge($row, $extra);
    }
    foreach (['tasu', 'stasu', 'protect', 'level'] as $col) {
        $col_row = @db_select("SELECT {$col} FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (is_array($col_row) && array_key_exists($col, $col_row)) {
            $row[$col] = $col_row[$col];
        }
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

/** 본방 채팅 알림 큐 (info1.php 등에서 status=0 건 전송) */
function wallet_본방알림_등록($msg, $nick) {
    $msg = trim((string)$msg);
    $nick = trim((string)$nick);
    if ($msg === '') {
        return false;
    }
    $msg_esc = addslashes($msg);
    $item_esc = addslashes('wallet_' . ($nick !== '' ? $nick : 'system'));
    return (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
}

function wallet_swap_quote_payload($direction, $amount, $member) {
    $보유_np = round((float)($member['newpoint'] ?? 0), 1);
    $보유_pt = (int)($member['point'] ?? 0);

    if ($direction === 'np2pt') {
        $전액 = ($amount === null || $amount === '');
        $금액 = $전액 ? $보유_np : (float)$amount;
        return 스왑_견적계산('np2pt', $금액, $전액);
    }
    if ($direction === 'pt2np') {
        return 스왑_견적계산('pt2np', (float)$보유_pt, true);
    }
    return ['ok' => false, 'msg' => '잘못된 스왑 방향입니다.'];
}

function wallet_swap_np_execute($nick, $amount_raw) {
    $닉_esc = addslashes($nick);
    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    $최소 = 스왑_본방_최소보유냥();
    if ($보유_np < $최소) {
        return ['ok' => false, 'data' => '본방냥 ' . wallet_fmt_new($최소) . '냥 이상일 때 스왑할 수 있어요.'];
    }

    $전액 = ($amount_raw === null || $amount_raw === '');
    $금액 = $전액 ? $보유_np : (float)$amount_raw;
    $견적 = 스왑_견적계산('np2pt', $금액, $전액);
    if (empty($견적['ok'])) {
        return ['ok' => false, 'data' => $견적['msg'] ?? '스왑 견적을 계산할 수 없습니다.'];
    }

    $차감_np = (float)$견적['차감_np'];
    $지급_pt = (int)$견적['지급_pt'];
    if ($보유_np < $차감_np) {
        return ['ok' => false, 'data' => '본방냥이 부족합니다.'];
    }

    db_query("UPDATE tb_member SET newpoint = newpoint - {$차감_np}, point = point + {$지급_pt} WHERE name = '{$닉_esc}' LIMIT 1");
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    return [
        'ok' => true,
        'data' => '본방냥 ' . wallet_fmt_new($차감_np) . '냥 → 게임냥 ' . number_format($지급_pt) . '냥 스왑 완료 (삭제 ' . wallet_fmt_new($삭제_np) . '냥)',
    ];
}

function wallet_swap_pt_execute($nick) {
    $닉_esc = addslashes($nick);
    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $보유_pt = (int)($회원['point'] ?? 0);
    if ($보유_pt < 1) {
        return ['ok' => false, 'data' => '스왑할 게임냥이 없습니다.'];
    }

    $견적 = 스왑_견적계산('pt2np', (float)$보유_pt, true);
    if (empty($견적['ok'])) {
        return ['ok' => false, 'data' => $견적['msg'] ?? '스왑 견적을 계산할 수 없습니다.'];
    }

    $차감_pt = (int)$견적['차감_pt'];
    $지급_np = (float)$견적['지급_np'];
    db_query("UPDATE tb_member SET point = point - {$차감_pt}, newpoint = newpoint + {$지급_np} WHERE name = '{$닉_esc}' LIMIT 1");
    $수수료_pt = (int)($견적['수수료_pt'] ?? 0);
    $교환_pt = (int)($견적['교환_pt'] ?? 0);
    wallet_본방알림_등록(
        "💱 내 지갑 스왑 (게임냥 → 본방냥)\n"
        . "{$nick}님\n"
        . '-' . number_format($차감_pt) . " 게임냥\n"
        . "  └ 수수료 " . number_format($수수료_pt) . " · 교환 " . number_format($교환_pt) . "\n"
        . '+' . wallet_fmt_new($지급_np) . ' 본방냥',
        $nick
    );
    return [
        'ok' => true,
        'data' => '게임냥 ' . number_format($차감_pt) . '냥 → 본방냥 ' . wallet_fmt_new($지급_np) . '냥 스왑 완료 (수수료 ' . number_format($수수료_pt) . '냥)',
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
        $지호차감문구 = 양도_지호_소비($nick);
    } else {
        $지호차감문구 = '';
    }
    지급로그('양도', $nick, $receiver, $수수료, $amount);
    지급로그('수수료', $nick, '', $수수료, $수수료);
    $결과문구 = "{$nick} → {$receiver} 본방냥 " . (function_exists('냥축약표시') ? 냥축약표시($수수료제외) : number_format($수수료제외) . '냥') . " (수수료 " . (function_exists('냥축약표시') ? 냥축약표시($수수료) : $수수료 . '냥') . ")";
    if ($지호차감문구 !== '') {
        $결과문구 .= "\n" . $지호차감문구;
    }

    return [
        'ok' => true,
        'data' => $결과문구,
    ];
}

function wallet_transfer_pt_execute($nick, $receiver, $amount_raw) {
    $receiver = wallet_resolve_receiver($receiver);
    $amount = function_exists('냥_금액_파싱') ? (int)냥_금액_파싱((string)$amount_raw) : (int)$amount_raw;
    if ($receiver === '' || $amount < 1) {
        return ['ok' => false, 'data' => '받는 사람과 금액을 확인해주세요.'];
    }
    if ($amount < 30000) {
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
    $정보 = db_select("SELECT point, level FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($정보)) {
        return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    $수수료 = 양도수수료계산((int)($정보['level'] ?? 0), $amount);

    $게임냥 = (int)($정보['point'] ?? 0);
    if ($게임냥 < $amount) {
        return ['ok' => false, 'data' => '게임냥이 부족합니다. (보유 ' . wallet_fmt_game($게임냥) . '냥)'];
    }

    $수수료제외 = $amount - $수수료;
    if ($수수료제외 < 1) {
        return ['ok' => false, 'data' => '양도 금액이 너무 적습니다.'];
    }
    $받는_esc = addslashes($receiver);
    db_query("UPDATE tb_member SET point = point - {$amount} WHERE name = '{$닉_esc}' LIMIT 1");
    db_query("UPDATE tb_member SET point = point + {$수수료제외} WHERE name = '{$받는_esc}' LIMIT 1");
    db_query("UPDATE config SET tax = tax + {$수수료}");
    if ($지호소비) {
        $지호차감문구 = 양도_지호_소비($nick);
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

function wallet_fmt_game($n) {
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시((int)$n, '');
    }
    return number_format((int)$n);
}

function wallet_fmt_new($n) {
    if (function_exists('newpoint표시')) {
        return newpoint표시($n);
    }
    return number_format((int)floor((float)$n));
}

function wallet_member_payload(array $row, $nick = '') {
    $nick = trim((string)$nick);
    if ($nick === '') {
        $nick = wallet_nick_from_row($row);
    }
    $point = (int)($row['point'] ?? 0);
    $계급 = wallet_point_rank($point);
    $무기 = trim((string)($row['item'] ?? ''));
    $강화 = (int)($row['enhance'] ?? 0);
    $무기표시 = $무기 !== '' ? ($무기 . ($강화 > 0 ? " +{$강화}" : '')) : '없음';

    return [
        'nick' => $nick,
        'title' => trim((string)($row['title'] ?? '')),
        'style' => trim((string)($row['style'] ?? '')),
        'newpoint' => (int)floor((float)($row['newpoint'] ?? 0)),
        'newpoint_fmt' => wallet_fmt_new($row['newpoint'] ?? 0),
        'point' => $point,
        'point_fmt' => wallet_fmt_game($point),
        'rank_name' => (string)($계급['name'] ?? ''),
        'rank_level' => (int)($계급['rank'] ?? 0),
        'weapon' => $무기표시,
        'protect' => (int)($row['protect'] ?? 0),
        'tasu' => (int)($row['tasu'] ?? 0),
        'stasu' => (int)($row['stasu'] ?? 0),
    ];
}

function wallet_seotda_nav_link($query, $point) {
    if (!defined('SEOTDA_MIN_ACCESS')) {
        require_once __DIR__ . '/../../seotda/lib/config.inc.php';
    }
    $ok = (int)$point >= (int)SEOTDA_MIN_ACCESS;
    return [
        'icon' => '🃏',
        'label' => '섯다',
        'href' => '/page/seotda.php' . $query,
        'disabled' => !$ok,
        'disabled_hint' => '게임냥 1조+',
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
    $target = '준호';
    if ($nick === $target) {
        return true;
    }
    if (function_exists('getTwoCharNick') && getTwoCharNick($nick) === $target) {
        return true;
    }
    return false;
}

function wallet_today_summary($nick) {
    $nick = addslashes(trim((string)$nick));
    $오늘시작 = date('Y-m-d 00:00:00');
    $내일시작 = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $in_row = db_select("
      SELECT COALESCE(SUM(point), 0) AS s
      FROM tb_point_log
      WHERE nick = '{$nick}' AND regdate >= '{$오늘시작}' AND regdate < '{$내일시작}' AND point > 0
    ");
    $out_row = db_select("
      SELECT COALESCE(SUM(ABS(point)), 0) AS s
      FROM tb_point_log
      WHERE nick = '{$nick}' AND regdate >= '{$오늘시작}' AND regdate < '{$내일시작}' AND point < 0
    ");
    $cnt_row = db_select("
      SELECT COUNT(*) AS c
      FROM tb_point_log
      WHERE (nick = '{$nick}' OR receiver = '{$nick}') AND regdate >= '{$오늘시작}' AND regdate < '{$내일시작}'
    ");
    return [
        'in' => (int)($in_row['s'] ?? 0),
        'in_fmt' => wallet_fmt_game($in_row['s'] ?? 0),
        'out' => (int)($out_row['s'] ?? 0),
        'out_fmt' => wallet_fmt_game($out_row['s'] ?? 0),
        'count' => (int)($cnt_row['c'] ?? 0),
    ];
}

function wallet_item_shop_data(array $member) {
    $midx = (int)($member['idx'] ?? 0);
    return [
        'buy' => function_exists('아이템_상점구매_목록') ? 아이템_상점구매_목록() : [],
        'sell' => function_exists('아이템_상점판매_시세목록') ? 아이템_상점판매_시세목록($member) : ['items' => [], 'fee_rate' => 30, 'jiho_fee_rate' => 15],
        'inventory' => function_exists('아이템_보유목록_판매표시') ? 아이템_보유목록_판매표시($member) : (function_exists('아이템_보유목록_집계') ? 아이템_보유목록_집계($midx) : []),
    ];
}

if (defined('WALLET_LIB_ONLY') && WALLET_LIB_ONLY) {
    return;
}

$wallet_actions = ['status', 'swap_quote', 'swap_np', 'swap_pt', 'transfer_np', 'transfer_pt', 'progress_extend', 'progress_cancel'];
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
        require_once __DIR__ . '/wallet_progress.inc.php';
        $fresh = wallet_auth($req_code);
        $swap_np_rate = function_exists('스왑_1보유냥당_게임냥') ? (int)스왑_1보유냥당_게임냥() : 0;
        wallet_json([
            'ok' => true,
            'type' => 'status',
            'member' => wallet_member_payload($fresh ?: $member, $nick),
            'today' => wallet_today_summary($nick),
            'progress' => wallet_progress_payload($nick),
            'swap' => [
                'np_min' => (int)스왑_본방_최소보유냥(),
                'np_delete_pct' => (int)스왑_본방_삭제비율(),
                'pt_fee_pct' => (int)스왑_게임방_수수료비율(),
                'np_per_pt' => $swap_np_rate,
            ],
        ]);
    }

    if ($req_action === 'swap_quote') {
        $dir = isset($_REQUEST['dir']) ? trim($_REQUEST['dir']) : '';
        $amount = isset($_REQUEST['amount']) && $_REQUEST['amount'] !== '' ? $_REQUEST['amount'] : null;
        $quote = wallet_swap_quote_payload($dir, $amount, $member);
        if (empty($quote['ok'])) {
            wallet_json(['ok' => false, 'data' => $quote['msg'] ?? '견적을 계산할 수 없습니다.']);
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
        $result = wallet_swap_pt_execute($nick);
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

    if ($req_action === 'progress_extend') {
        require_once __DIR__ . '/wallet_progress.inc.php';
        $progress_type = isset($_REQUEST['progress_type']) ? trim((string)$_REQUEST['progress_type']) : '';
        $result = wallet_progress_extend_execute($nick, $progress_type);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        wallet_json(array_merge($result, [
            'type' => 'progress_extend',
            'member' => wallet_member_payload(wallet_auth($req_code) ?: $member, $nick),
            'today' => wallet_today_summary($nick),
        ]));
    }

    if ($req_action === 'progress_cancel') {
        require_once __DIR__ . '/wallet_progress.inc.php';
        $result = wallet_progress_cancel_execute($nick);
        if (empty($result['ok'])) {
            wallet_json($result);
        }
        wallet_json(array_merge($result, [
            'type' => 'progress_cancel',
            'member' => wallet_member_payload(wallet_auth($req_code) ?: $member, $nick),
            'today' => wallet_today_summary($nick),
        ]));
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
$wallet_member = null;
$wallet_today = null;
$wallet_progress = null;
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
    require_once __DIR__ . '/wallet_progress.inc.php';
    $wallet_progress = wallet_progress_payload($nick);
    $wallet_swap_info = [
        'np_min' => 500,
        'np_delete_pct' => 10,
        'pt_fee_pct' => 20,
        'np_per_pt' => null,
    ];
    $q = wallet_code_query($wallet_code);
    $wallet_nav_links = [
        ['icon' => '🎲', 'label' => '홀짝', 'href' => '/page/game.php' . $q],
        wallet_seotda_nav_link($q, (int)($wallet_member['point'] ?? 0)),
        wallet_mining_nav_link($q, $nick),
        ['icon' => '⚔️', 'label' => '강화', 'href' => '/page/enchant.php' . $q],
        ['icon' => '🛍️', 'label' => '아이템상점', 'href' => '/page/item_shop.php' . $q],
        ['icon' => '🛒', 'label' => '마켓', 'href' => '/shop/' . $q],
    ];
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
        require_once __DIR__ . '/wallet_progress.inc.php';
        $wallet_progress = wallet_progress_payload($nick);
        $wallet_swap_info = [
            'np_min' => 500,
            'np_delete_pct' => 10,
            'pt_fee_pct' => 20,
            'np_per_pt' => null,
        ];
        $q = wallet_code_query($wallet_code);
        $wallet_nav_links = [
            ['icon' => '🎲', 'label' => '홀짝', 'href' => '/page/game.php' . $q],
            wallet_seotda_nav_link($q, (int)($wallet_member['point'] ?? 0)),
            wallet_mining_nav_link($q, $nick),
            ['icon' => '⚔️', 'label' => '강화', 'href' => '/page/enchant.php' . $q],
            ['icon' => '🛍️', 'label' => '아이템상점', 'href' => '/page/item_shop.php' . $q],
            ['icon' => '🛒', 'label' => '마켓', 'href' => '/shop/' . $q],
        ];
    } elseif (wallet_코드_쿠키_읽기() === $wallet_code) {
        wallet_코드_쿠키_삭제();
    }
}

$wallet_show_ore_revoke = false;
$wallet_ore_revoke_stats = null;
if (!$wallet_need_code && is_array($wallet_member) && wallet_ore_revoke_wallet_visible($wallet_member['nick'] ?? '')) {
    $wallet_show_ore_revoke = true;
    if (!defined('WALLET_LIB_ONLY')) {
        define('WALLET_LIB_ONLY', true);
    }
    require_once __DIR__ . '/mining_ore_admin.inc.php';
    if (function_exists('mining_ore_admin_scan')) {
        $wallet_ore_revoke_stats = mining_ore_admin_scan()['stats'] ?? null;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0c1222">
    <title>내 지갑</title>
    <!-- wallet-build:20260626b -->
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
        .stat-pill.in b { color: var(--green); }
        .stat-pill.out b { color: var(--red); }
        .lock-box {
            text-align: center;
            padding: 40px 16px;
            color: var(--muted);
            line-height: 1.6;
        }
        .lock-box strong { color: var(--text); font-size: 1rem; }
        .hint { font-size: 0.72rem; color: var(--muted); text-align: center; margin-top: 12px; }
        .loading { opacity: 0.55; pointer-events: none; }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 12px 8px;
            border-radius: 12px;
            background: var(--card2);
            border: 1px solid var(--line);
            color: var(--text);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .nav-link span:first-child { font-size: 1.35rem; }
        .nav-link.disabled {
            opacity: 0.42;
            cursor: not-allowed;
            pointer-events: none;
        }
        .nav-link.disabled small {
            display: block;
            margin-top: 2px;
            font-size: 0.62rem;
            font-weight: 500;
            color: var(--muted);
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
        .progress-card {
            padding: 12px 14px;
        }
        .progress-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        .progress-tab {
            text-align: center;
            padding: 10px 6px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(0,0,0,0.12);
            color: var(--muted);
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            font-family: inherit;
            position: relative;
            transition: border-color .15s, color .15s, background .15s;
        }
        .progress-tab.selected {
            border-color: rgba(96,165,250,0.45);
            background: rgba(96,165,250,0.12);
            color: var(--blue);
        }
        .progress-tab.on::after {
            content: '';
            position: absolute;
            top: 6px;
            right: 8px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
        }
        .progress-panel {
            display: none;
            margin-top: 10px;
        }
        .progress-panel.show {
            display: block;
        }
        .progress-meta {
            min-height: 1.2em;
            font-size: 0.76rem;
            color: var(--muted);
            text-align: center;
            line-height: 1.45;
        }
        .progress-meta strong {
            color: var(--text);
        }
        .btn-progress-extend {
            width: 100%;
            margin-top: 10px;
            padding: 11px 12px;
            border-radius: 10px;
            border: 1px solid rgba(96,165,250,0.35);
            background: rgba(96,165,250,0.12);
            color: var(--blue);
            font-weight: 800;
            font-size: 0.86rem;
            font-family: inherit;
            cursor: pointer;
        }
        .progress-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        .progress-actions .btn-progress-extend {
            margin-top: 0;
        }
        .btn-progress-cancel {
            width: 100%;
            padding: 11px 12px;
            border-radius: 10px;
            border: 1px solid rgba(248,113,113,0.35);
            background: rgba(248,113,113,0.12);
            color: #f87171;
            font-weight: 800;
            font-size: 0.86rem;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-progress-extend:disabled,
        .btn-progress-cancel:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="wrap" id="app">
    <h1>💰 내 지갑</h1>

    <?php if (!$wallet_need_code && is_array($wallet_progress)) { ?>
        <div class="card progress-card" id="progressCard">
            <div class="progress-tabs" id="progressTabs">
                <?php foreach (($wallet_progress['types'] ?? []) as $pt) { ?>
                <button type="button" class="progress-tab<?php echo !empty($pt['active']) ? ' on' : ''; ?>" data-type="<?php echo htmlspecialchars((string)($pt['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string)($pt['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <?php } ?>
            </div>
            <?php foreach (($wallet_progress['types'] ?? []) as $pt) {
                $ptype = (string)($pt['type'] ?? '');
            ?>
            <div class="progress-panel" data-type="<?php echo htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="progress-meta" data-meta="<?php echo htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <?php if ($ptype === '강일') { ?>
                <div class="progress-actions">
                    <button type="button" class="btn-progress-extend" data-extend="강일" disabled>12시간 연장하기</button>
                    <button type="button" class="btn-progress-cancel" data-cancel="강일" disabled>취소하기</button>
                </div>
                <?php } elseif ($ptype !== '일방') { ?>
                <button type="button" class="btn-progress-extend" data-extend="<?php echo htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    <?php echo (int)($pt['extend_hours'] ?? 0); ?>시간 연장하기
                </button>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($wallet_need_code) { ?>
        <div class="card lock-box">
            <p style="font-size:2rem;margin-bottom:10px;">🔒</p>
            <strong>코드를 확인해주세요</strong><br>
            링크에 포함된 코드가 올바른지 확인하거나, 상황실에서 <code>.지갑</code> 또는 <code>.지갑배정</code> 으로 다시 받아주세요.<br>
            <span style="opacity:0.65;font-size:0.8rem;">예) /page/wallet.php?code=XXXX</span>
        </div>
    <?php } else { ?>
        <div class="card" id="profileCard">
            <div class="profile">
                <div>
                    <div class="nick" id="wNick"><?php echo htmlspecialchars($wallet_member['nick'], ENT_QUOTES, 'UTF-8'); ?></div>
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
                <div class="stat-pill">오늘타수<b id="wTasu"><?php echo (int)$wallet_member['tasu']; ?></b></div>
                <div class="stat-pill in">오늘 +<b id="wTodayIn">…</b></div>
                <div class="stat-pill out">오늘 -<b id="wTodayOut">…</b></div>
            </div>
        </div>

        <div class="card">
            <h2>🔗 바로가기</h2>
            <div class="nav-grid">
                <?php foreach ($wallet_nav_links as $link) {
                    $disabled = !empty($link['disabled']);
                    $hint = trim((string)($link['disabled_hint'] ?? ''));
                    if ($disabled) { ?>
                <span class="nav-link disabled" title="<?php echo htmlspecialchars($hint !== '' ? $hint : '이용 불가', ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo $link['icon']; ?></span>
                    <span><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?><?php if ($hint !== '') { ?><small><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></small><?php } ?></span>
                </span>
                    <?php } else { ?>
                <a class="nav-link" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo $link['icon']; ?></span>
                    <span><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                    <?php }
                } ?>
            </div>
        </div>

        <?php if (!empty($wallet_show_ore_revoke)) {
            $ore_q = wallet_code_query($wallet_code);
            $ore_stats = is_array($wallet_ore_revoke_stats) ? $wallet_ore_revoke_stats : [];
        ?>
        <div class="card admin-ore-revoke">
            <h2>⛏️ 광물 비정상 회수</h2>
            <p class="admin-ore-hint">1시간 허용량을 초과한 광물을 회수합니다. 이미 수령한 건은 채굴량 → 본방냥 순으로 차감 후 되돌립니다.</p>
            <div class="admin-ore-stats">
                <div class="admin-ore-stat">
                    <strong><?php echo (int)($ore_stats['abnormal_total'] ?? 0); ?></strong>
                    <span>대기 비정상</span>
                </div>
                <div class="admin-ore-stat">
                    <strong><?php echo (int)($ore_stats['claimed_abnormal_total'] ?? 0); ?></strong>
                    <span>수령 비정상</span>
                </div>
            </div>
            <a class="admin-ore-link" href="/page/mining_ore_revoke.php<?php echo htmlspecialchars($ore_q, ENT_QUOTES, 'UTF-8'); ?>">회수 페이지 열기 →</a>
        </div>
        <?php } ?>

        <div class="card">
            <h2>💱 스왑 · 양도</h2>
            <div class="action-grid">
                <button type="button" class="action-btn mint" onclick="openWalletModal('swapNp')">
                    본방냥 스왑
                    <small>본방냥 → 게임냥 · 삭제 <?php echo (int)($wallet_swap_info['np_delete_pct'] ?? 10); ?>%</small>
                </button>
                <button type="button" class="action-btn gold" onclick="openWalletModal('swapPt')">
                    게임냥 스왑
                    <small>게임냥 → 본방냥 · 수수료 <?php echo (int)($wallet_swap_info['pt_fee_pct'] ?? 20); ?>%</small>
                </button>
                <button type="button" class="action-btn mint" onclick="openWalletModal('transferNp')">
                    본방냥 양도
                    <small>친구에게 본방냥 보내기</small>
                </button>
                <button type="button" class="action-btn gold" onclick="openWalletModal('transferPt')">
                    게임냥 양도
                    <small>친구에게 게임냥 보내기 · 3만냥+</small>
                </button>
            </div>
            <div class="swap-hint" id="swapRateLine">
                본방 스왑 최소 <?php echo (int)($wallet_swap_info['np_min'] ?? 500); ?>냥 · 게임 스왑은 전액만 가능<br>
                환율 불러오는 중…
            </div>
        </div>

        <div class="modal-overlay" id="modal-swapNp" onclick="closeWalletModalBg(event, 'swapNp')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>본방냥 → 게임냥 스왑</h3>
                <div class="form-field">
                    <label for="swapNpAmount">스왑할 본방냥 (비우면 전액)</label>
                    <input type="number" id="swapNpAmount" min="500" step="1" placeholder="예) 500 또는 전액">
                </div>
                <div class="quote-box" id="swapNpQuote">금액을 입력하면 예상 지급액을 보여드려요.</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeWalletModal('swapNp')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitSwapNp()">스왑하기</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-swapPt" onclick="closeWalletModalBg(event, 'swapPt')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>게임냥 → 본방냥 스왑</h3>
                <div class="quote-box" id="swapPtQuote">보유 게임냥 전액 기준 견적을 불러옵니다.</div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeWalletModal('swapPt')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitSwapPt()">전액 스왑</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-transferNp" onclick="closeWalletModalBg(event, 'transferNp')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>본방냥 양도</h3>
                <div class="form-field">
                    <label for="transferNpReceiver">받는 닉네임</label>
                    <input type="text" id="transferNpReceiver" maxlength="20" placeholder="예) 친구 닉네임 2글자">
                </div>
                <div class="form-field">
                    <label for="transferNpAmount">양도할 본방냥</label>
                    <input type="number" id="transferNpAmount" min="1" step="1" placeholder="정수 냥">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeWalletModal('transferNp')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitTransferNp()">양도하기</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-transferPt" onclick="closeWalletModalBg(event, 'transferPt')">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3>게임냥 양도</h3>
                <div class="form-field">
                    <label for="transferPtReceiver">받는 닉네임</label>
                    <input type="text" id="transferPtReceiver" maxlength="20" placeholder="예) 친구 닉네임 2글자">
                </div>
                <div class="form-field">
                    <label for="transferPtAmount">양도할 게임냥</label>
                    <input type="text" id="transferPtAmount" placeholder="예) 50000, 1억, 5조">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeWalletModal('transferPt')">취소</button>
                    <button type="button" class="btn-confirm" onclick="submitTransferPt()">양도하기</button>
                </div>
            </div>
        </div>

        <div class="toast" id="toast"></div>
    <?php } ?>
</div>

<?php if (!$wallet_need_code) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($wallet_code, JSON_UNESCAPED_UNICODE); ?>;
    var API = <?php echo json_encode($wallet_api_entry, JSON_UNESCAPED_UNICODE); ?>;
    var PROGRESS = <?php echo json_encode($wallet_progress ?: null, JSON_UNESCAPED_UNICODE); ?>;

    function fmtLeft(sec) {
        sec = Math.max(0, parseInt(sec, 10) || 0);
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        if (h > 0) return h + '시간 ' + m + '분 남음';
        if (m > 0) return m + '분 남음';
        return sec + '초 남음';
    }

    var PROGRESS = <?php echo json_encode($wallet_progress ?: null, JSON_UNESCAPED_UNICODE); ?>;
    var PROGRESS_TAB = '지목';

    function progressRowByType(p, type) {
        if (!p || !p.types) return null;
        for (var i = 0; i < p.types.length; i++) {
            if (p.types[i].type === type) return p.types[i];
        }
        return null;
    }

    function defaultProgressTab(p) {
        var order = ['지목', '강일', '일방'];
        for (var i = 0; i < order.length; i++) {
            var row = progressRowByType(p, order[i]);
            if (row && row.active) return order[i];
        }
        return '지목';
    }

    function renderProgressPanel(type) {
        if (!PROGRESS) return;
        var row = progressRowByType(PROGRESS, type);
        var panel = document.querySelector('.progress-panel[data-type="' + type + '"]');
        if (!panel || !row) return;

        var meta = panel.querySelector('.progress-meta');
        if (meta) {
            if (!row.active) {
                meta.textContent = '진행 중인 ' + row.label + ' 없음';
            } else if (type === '일방') {
                meta.innerHTML = (row.nick_display || '') + '<br>' + fmtLeft(row.left_sec) + ' · ~' + (row.enddate_fmt || '');
            } else {
                meta.innerHTML = (row.nick_display || '') + '<br>' + fmtLeft(row.left_sec) + ' · ~' + (row.enddate_fmt || '')
                    + (row.item_count > 0 ? (' · ' + row.label + ' ' + row.item_count + '개') : '');
            }
        }

        var btn = panel.querySelector('.btn-progress-extend');
        if (btn) {
            btn.textContent = row.extend_label || (row.extend_hours + '시간 연장하기');
            btn.disabled = !(row.active && row.can_extend);
        }

        var cancelBtn = panel.querySelector('.btn-progress-cancel');
        if (cancelBtn) {
            cancelBtn.disabled = !(row.active && row.can_cancel);
        }
    }

    function selectProgressTab(type) {
        PROGRESS_TAB = type;
        var tabs = document.querySelectorAll('.progress-tab');
        for (var i = 0; i < tabs.length; i++) {
            var t = tabs[i];
            if (t.getAttribute('data-type') === type) t.classList.add('selected');
            else t.classList.remove('selected');
        }
        var panels = document.querySelectorAll('.progress-panel');
        for (var j = 0; j < panels.length; j++) {
            var p = panels[j];
            if (p.getAttribute('data-type') === type) p.classList.add('show');
            else p.classList.remove('show');
        }
        renderProgressPanel(type);
    }

    function applyProgress(p) {
        if (!p || !p.types) return;
        PROGRESS = p;

        var tabs = document.querySelectorAll('.progress-tab');
        for (var i = 0; i < tabs.length; i++) {
            var t = tabs[i];
            var type = t.getAttribute('data-type');
            var row = progressRowByType(p, type);
            if (row && row.active) t.classList.add('on');
            else t.classList.remove('on');
        }

        if (!progressRowByType(p, PROGRESS_TAB)) {
            PROGRESS_TAB = defaultProgressTab(p);
        }

        selectProgressTab(PROGRESS_TAB);
        ['지목', '강일', '일방'].forEach(function(type) {
            renderProgressPanel(type);
        });
    }

    function tickProgressCountdown() {
        if (!PROGRESS || !PROGRESS.types) return;
        var changed = false;
        for (var i = 0; i < PROGRESS.types.length; i++) {
            if (PROGRESS.types[i].active && PROGRESS.types[i].left_sec > 0) {
                PROGRESS.types[i].left_sec--;
                changed = true;
            }
        }
        if (!changed) return;
        renderProgressPanel(PROGRESS_TAB);
    }

    function applyStatus(j) {
        if (!j.member) return;
        var m = j.member;
        document.getElementById('wNewpoint').textContent = m.newpoint_fmt;
        document.getElementById('wPoint').textContent = m.point_fmt;
        document.getElementById('wProtect').textContent = m.protect;
        document.getElementById('wTasu').textContent = m.tasu;
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
        if (typeof j.swap_np_rate === 'number' || (j.swap && typeof j.swap.np_per_pt === 'number')) {
            var rate = typeof j.swap_np_rate === 'number' ? j.swap_np_rate : j.swap.np_per_pt;
            var rateLine = document.getElementById('swapRateLine');
            if (rateLine) {
                rateLine.innerHTML = '본방 스왑 최소 500냥 · 게임 스왑은 전액만 가능<br>'
                    + '환율 ≈ 1 본방냥 : ' + Number(rate).toLocaleString() + ' 게임냥';
            }
        }
        if (j.progress) applyProgress(j.progress);
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

    function openWalletModal(name) {
        var el = document.getElementById('modal-' + name);
        if (el) el.classList.add('open');
        if (name === 'swapNp') updateSwapNpQuote();
        if (name === 'swapPt') updateSwapPtQuote();
    }

    function closeWalletModal(name) {
        var el = document.getElementById('modal-' + name);
        if (el) el.classList.remove('open');
    }

    function closeWalletModalBg(e, name) {
        if (e.target.id === 'modal-' + name) closeWalletModal(name);
    }

    function formatQuoteNp2pt(q) {
        if (!q || !q.ok) return q && q.msg ? q.msg : '견적 없음';
        return '-' + (q.차감_np || 0) + ' 본방냥\n삭제 ' + (q.삭제_np || 0) + ' · 교환 ' + (q.교환_np || 0) + '\n+' + Number(q.지급_pt || 0).toLocaleString() + ' 게임냥';
    }

    function formatQuotePt2np(q) {
        if (!q || !q.ok) return q && q.msg ? q.msg : '견적 없음';
        return '-' + Number(q.차감_pt || 0).toLocaleString() + ' 게임냥\n수수료 ' + Number(q.수수료_pt || 0).toLocaleString() + ' · 교환 ' + Number(q.교환_pt || 0).toLocaleString() + '\n+' + (q.지급_np || 0) + ' 본방냥';
    }

    function updateSwapNpQuote() {
        var amount = document.getElementById('swapNpAmount').value.trim();
        ajax('swap_quote', { dir: 'np2pt', amount: amount }, function(j) {
            document.getElementById('swapNpQuote').textContent = formatQuoteNp2pt(j.quote);
        });
    }

    function updateSwapPtQuote() {
        ajax('swap_quote', { dir: 'pt2np' }, function(j) {
            document.getElementById('swapPtQuote').textContent = formatQuotePt2np(j.quote);
        });
    }

    var swapNpInput = document.getElementById('swapNpAmount');
    if (swapNpInput) {
        var quoteTimer;
        swapNpInput.addEventListener('input', function() {
            clearTimeout(quoteTimer);
            quoteTimer = setTimeout(updateSwapNpQuote, 300);
        });
    }

    function afterMoneyAction(j) {
        showToast(j.data || '완료');
        if (j.member || j.today) {
            applyStatus({
                member: j.member || null,
                today: j.today || null
            });
        }
        closeWalletModal('swapNp');
        closeWalletModal('swapPt');
        closeWalletModal('transferNp');
        closeWalletModal('transferPt');
    }

    function submitSwapNp() {
        var amount = document.getElementById('swapNpAmount').value.trim();
        ajax('swap_np', { amount: amount }, afterMoneyAction);
    }

    function submitSwapPt() {
        ajax('swap_pt', {}, afterMoneyAction);
    }

    function submitTransferNp() {
        ajax('transfer_np', {
            receiver: document.getElementById('transferNpReceiver').value.trim(),
            amount: document.getElementById('transferNpAmount').value.trim()
        }, afterMoneyAction);
    }

    function submitTransferPt() {
        ajax('transfer_pt', {
            receiver: document.getElementById('transferPtReceiver').value.trim(),
            amount: document.getElementById('transferPtAmount').value.trim()
        }, afterMoneyAction);
    }

    window.openWalletModal = openWalletModal;
    window.closeWalletModal = closeWalletModal;
    window.closeWalletModalBg = closeWalletModalBg;
    window.submitSwapNp = submitSwapNp;
    window.submitSwapPt = submitSwapPt;
    window.submitTransferNp = submitTransferNp;
    window.submitTransferPt = submitTransferPt;

    var progressTabs = document.getElementById('progressTabs');
    if (progressTabs) {
        progressTabs.addEventListener('click', function(e) {
            var tab = e.target.closest('.progress-tab');
            if (!tab) return;
            var type = tab.getAttribute('data-type');
            if (!type) return;
            selectProgressTab(type);
        });
    }

    document.querySelectorAll('.btn-progress-extend').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.getAttribute('data-extend');
            if (!type || btn.disabled) return;
            ajax('progress_extend', { progress_type: type }, function(j) {
                showToast(j.data || '연장 완료');
                if (j.progress) applyProgress(j.progress);
            });
        });
    });

    document.querySelectorAll('.btn-progress-cancel').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (btn.disabled) return;
            if (!confirm('진행 중인 강일을 취소할까요?\n(취소 후 12시간 재강일 제한이 적용돼요)')) return;
            ajax('progress_cancel', null, function(j) {
                showToast(j.data || '취소 완료');
                if (j.progress) applyProgress(j.progress);
            });
        });
    });

    if (PROGRESS) {
        PROGRESS_TAB = defaultProgressTab(PROGRESS);
        applyProgress(PROGRESS);
    }
    setInterval(tickProgressCountdown, 1000);

    ajax('status', null, applyStatus, true);
})();
</script>
<?php } ?>
</body>
</html>
