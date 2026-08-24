<?php
/**
 * 거래소 은총 매도 유령복구 소급 정산 (CLI / 브라우저 1회용)
 *
 * CLI:
 *   php api/_eunchong_sell_reconcile.php
 *   php api/_eunchong_sell_reconcile.php apply
 *
 * Web (서버에서만, key 필요):
 *   /api/_eunchong_sell_reconcile.php?key=...
 *   /api/_eunchong_sell_reconcile.php?key=...&apply=1
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/item_bag_enhance.inc.php';

$isCli = (PHP_SAPI === 'cli');
$apply = false;
$days = 30;
$keyNeed = 'eunchong-sell-fix-' . date('Ymd');

if ($isCli) {
    $apply = in_array('apply', array_map('strtolower', array_slice($argv ?? [], 1)), true);
    foreach (($argv ?? []) as $a) {
        if (preg_match('/^days=(\d+)$/', (string)$a, $m)) {
            $days = (int)$m[1];
        }
    }
} else {
    $key = (string)($_GET['key'] ?? '');
    if ($key === '' || !hash_equals($keyNeed, $key)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(403);
        echo "forbidden\n";
        exit;
    }
    $apply = isset($_GET['apply']) && (string)$_GET['apply'] !== '0';
    if (isset($_GET['days'])) {
        $days = (int)$_GET['days'];
    }
}

$결과 = bag_은총_거래소매도_정산($apply, $days);
header('Content-Type: text/plain; charset=utf-8');
echo ($결과['msg'] ?? json_encode($결과, JSON_UNESCAPED_UNICODE)) . "\n";
if (!empty($결과['rows']) && $isCli) {
    echo "\n--- JSON ---\n";
    echo json_encode($결과, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
