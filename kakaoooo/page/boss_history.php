<?php
/**
 * 이전 보스 내역 (호환 URL)
 * 실제: /api/game/boss_history_web.php?code=XXXX
 */
if (!isset($GLOBALS['wallet_preauth'])) {
    $GLOBALS['wallet_preauth'] = null;
}

$__wallet_code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($__wallet_code === '' && isset($_REQUEST['code'])) {
    $__wallet_code = trim((string)$_REQUEST['code']);
}
if ($__wallet_code === '' && isset($_POST['wallet_code'])) {
    $__wallet_code = trim((string)$_POST['wallet_code']);
}
if ($__wallet_code === '' && isset($_COOKIE['wallet_code'])) {
    $__wallet_code = trim((string)$_COOKIE['wallet_code']);
}

if ($__wallet_code !== '' && empty($GLOBALS['wallet_preauth'])) {
    if (!function_exists('db_select')) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    }
    $__esc = addslashes($__wallet_code);
    $__row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point FROM tb_member WHERE code = '{$__esc}' LIMIT 1");
    if (!empty($__row['name'])) {
        $GLOBALS['wallet_preauth'] = [
            'code' => $__wallet_code,
            'row' => $__row,
        ];
    }
}

require __DIR__ . '/../api/game/boss_history_web.php';
