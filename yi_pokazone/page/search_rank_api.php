<?php
/**
 * 실시간 인기 검색어 API (JSON)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_search_rank.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$hours = isset($_GET['hours']) ? (int) $_GET['hours'] : SEARCH_RANK_DEFAULT_HOURS;

$rows = search_rank_top($limit, $hours);
$items = [];
foreach ($rows as $row) {
    $trend  = (string) ($row['trend'] ?? 'same');
    $tdelta = (int) ($row['trend_delta'] ?? 0);
    $items[] = [
        'rank'        => (int) $row['rank'],
        'keyword'     => $row['keyword'],
        'hits'        => (int) $row['hits'],
        'trend'       => $trend,
        'trend_delta' => $tdelta,
        'trend_label' => search_rank_trend_display($trend, $tdelta),
        'url'         => search_rank_search_url($row['keyword']),
    ];
}

echo json_encode([
    'ok'         => true,
    'ready'      => search_log_table_ready(),
    'hours'      => $hours,
    'trend_hours'=> SEARCH_RANK_TREND_HOURS,
    'updated_at' => date('c'),
    'items'      => $items,
], JSON_UNESCAPED_UNICODE);
