<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_pokemon_market.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/pokemon_market.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!pokemon_market_table_ready()) {
    alert_goto('포켓몬명 테이블을 먼저 준비해 주세요.', '/admin/pokemon_market.php');
}

$action = trim((string) ($_POST['action'] ?? ''));
$st = trim((string) ($_POST['st'] ?? '1'));
if (!in_array($st, ['all', '1', '9'], true)) {
    $st = '1';
}
$q = trim((string) ($_POST['q'] ?? ''));
$page_no = max(1, (int) ($_POST['p'] ?? 1));
$return_params = [];
if ($st !== '1') {
    $return_params['st'] = $st;
}
if ($q !== '') {
    $return_params['q'] = $q;
}
if ($page_no > 1) {
    $return_params['p'] = $page_no;
}
$return_url = '/admin/pokemon_market.php'
    . ($return_params ? '?' . http_build_query($return_params) : '');

$now = date('Y-m-d H:i:s');

if ($action === 'insert') {
    $name = pokemon_market_normalize_name((string) ($_POST['pm_name'] ?? ''));
    $sort = (int) ($_POST['pm_sort'] ?? 0);
    if ($name === '') {
        alert_goto('포켓몬명을 입력해 주세요.', $return_url);
    }
    $name_esc = db_escape($name);
    $dup = db_assoc(db_query("SELECT pm_idx FROM tb_pokemon_market WHERE pm_name = '{$name_esc}' LIMIT 1"));
    if ($dup) {
        alert_goto('이미 등록된 포켓몬명입니다.', $return_url);
    }
    $ok = db_query("
        INSERT INTO tb_pokemon_market (pm_name, pm_sort, pm_status, pm_created_at, pm_updated_at)
        VALUES ('{$name_esc}', {$sort}, 1, '{$now}', '{$now}')
    ");
    if (!$ok) {
        alert_goto('등록에 실패했습니다.', $return_url);
    }
    alert_goto('등록했습니다.', '/admin/pokemon_market.php');
}

if ($action === 'update') {
    $idx = (int) ($_POST['pm_idx'] ?? 0);
    $name = pokemon_market_normalize_name((string) ($_POST['pm_name'] ?? ''));
    $sort = (int) ($_POST['pm_sort'] ?? 0);
    if ($idx < 1 || $name === '') {
        alert_goto('잘못된 요청입니다.', $return_url);
    }
    $name_esc = db_escape($name);
    $dup = db_assoc(db_query("
        SELECT pm_idx FROM tb_pokemon_market
        WHERE pm_name = '{$name_esc}' AND pm_idx <> {$idx}
        LIMIT 1
    "));
    if ($dup) {
        alert_goto('이미 등록된 포켓몬명입니다.', $return_url);
    }
    $ok = db_query("
        UPDATE tb_pokemon_market
        SET pm_name = '{$name_esc}', pm_sort = {$sort}, pm_updated_at = '{$now}'
        WHERE pm_idx = {$idx}
        LIMIT 1
    ");
    if (!$ok) {
        alert_goto('저장에 실패했습니다.', $return_url);
    }
    alert_goto('저장했습니다.', $return_url);
}

if ($action === 'toggle') {
    $idx = (int) ($_POST['pm_idx'] ?? 0);
    if ($idx < 1) {
        alert_goto('잘못된 요청입니다.', $return_url);
    }
    $row = pokemon_market_find_by_idx($idx, false);
    if (!$row) {
        alert_goto('대상을 찾을 수 없습니다.', $return_url);
    }
    $next = ((int) $row['pm_status'] === 1) ? 9 : 1;
    $ok = db_query("
        UPDATE tb_pokemon_market
        SET pm_status = {$next}, pm_updated_at = '{$now}'
        WHERE pm_idx = {$idx}
        LIMIT 1
    ");
    if (!$ok) {
        alert_goto('상태 변경에 실패했습니다.', $return_url);
    }
    alert_goto($next === 1 ? '노출로 변경했습니다.' : '숨김 처리했습니다.', $return_url);
}

alert_goto('알 수 없는 요청입니다.', $return_url);
