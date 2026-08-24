<?php
/**
 * 브라우저 heartbeat — 같은 페이지에 머무는 접속자 갱신
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_site_presence.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!site_presence_table_ready()) {
    echo json_encode(['ok' => false, 'ready' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

site_presence_ping();

echo json_encode(['ok' => true, 'ready' => true], JSON_UNESCAPED_UNICODE);
