<?php
define('PZ_API_JSON', true);
require_once __DIR__ . '/include/admin_init.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$stats = site_presence_stats();
echo json_encode([
    'ok'    => true,
    'ready' => $stats['ready'],
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE);
