<?php
/**
 * 마피아 API
 * POST action=status|reset|night_act|day_vote|mission_reroll&code=...
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
require_once dirname(__DIR__) . '/api/game/mafia.inc.php';

header('Content-Type: application/json; charset=utf-8');

$code = '';
if (isset($_POST['code'])) {
    $code = trim((string)$_POST['code']);
}
if ($code === '' && isset($_GET['code'])) {
    $code = trim((string)$_GET['code']);
}
if ($code === '' && isset($_COOKIE['wallet_code'])) {
    $code = trim((string)$_COOKIE['wallet_code']);
}

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? 'status'));
if ($code === '') {
    mafia_json(['ok' => false, 'msg' => '코드가 필요해요.']);
}

$esc = addslashes($code);
$row = db_select("SELECT name, status FROM tb_member WHERE code = '{$esc}' LIMIT 1");
if (empty($row['name']) || (int)($row['status'] ?? 0) === 1) {
    mafia_json(['ok' => false, 'msg' => '인증에 실패했어요.']);
}
$nick = trim((string)$row['name']);
if (function_exists('getTwoCharNick')) {
    $nick = getTwoCharNick($nick) ?: $nick;
}

if ($action === 'reset') {
    $r = mafia_초기화($nick);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '초기화했어요';
    mafia_json($st);
}

if ($action === 'force_phase') {
    $target = trim((string)($_POST['phase'] ?? $_POST['target'] ?? ''));
    $r = mafia_페이즈강제($nick, $target);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '페이즈를 바꿨어요';
    mafia_json($st);
}

if ($action === 'set_role') {
    $target = trim((string)($_POST['target'] ?? $_POST['nick'] ?? ''));
    $role = trim((string)($_POST['role'] ?? ''));
    $r = mafia_관리자역할변경($nick, $target, $role);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '역할을 변경했어요';
    mafia_json($st);
}

if ($action === 'rejoin_mafia') {
    $r = mafia_생존마피아재편입($nick);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '재편입했어요';
    mafia_json($st);
}

if ($action === 'night_act') {
    $target = trim((string)($_POST['targets'] ?? ($_POST['target'] ?? '')));
    $r = mafia_밤행동($nick, $target);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '등록했어요';
    mafia_json($st);
}

if ($action === 'day_vote') {
    $target = trim((string)($_POST['targets'] ?? ($_POST['target'] ?? '')));
    $r = mafia_낮투표($nick, $target);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '투표했어요';
    mafia_json($st);
}

if ($action === 'mission_reroll' || $action === 'mission_change') {
    $r = mafia_미션변경($nick);
    if (empty($r['ok'])) {
        mafia_json($r);
    }
    $st = mafia_상태($nick);
    $st['msg'] = $r['msg'] ?? '미션을 바꿨어요';
    mafia_json($st);
}

// status (default)
mafia_json(mafia_상태($nick));
