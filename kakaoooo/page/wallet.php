<?php
/**
 * 개인 가방 웹 (호환 URL)
 */
if (isset($_GET['_ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ping_code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    $ping_esc = addslashes($ping_code);
    $ping_row = function_exists('db_select')
        ? db_select("SELECT idx, name, point FROM tb_member WHERE code = '{$ping_esc}' LIMIT 1")
        : null;
    echo json_encode([
        'wallet_page_build' => '20260626c',
        'code' => $ping_code,
        'row' => $ping_row,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/_wallet_preauth.php';
require __DIR__ . '/../api/game/wallet_web.php';
