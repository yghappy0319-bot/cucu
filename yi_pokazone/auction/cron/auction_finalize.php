<?php
/**
 * 마감된 경매 낙찰/유찰 확정 (cron용)
 *
 * 예) 1분마다:
 *   * * * * * php /path/to/pokazone/auction/cron/auction_finalize.php
 */
define('PZ_API_JSON', true);

require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_auction.php';

if (PHP_SAPI !== 'cli' && !defined('AUCTION_CRON_ALLOW_WEB')) {
    http_response_code(403);
    exit('CLI only');
}

if (!auction_table_ok()) {
    fwrite(STDERR, "tb_auction not found\n");
    exit(1);
}

$count = auction_finalize_expired(null, 200);
$msg   = date('Y-m-d H:i:s') . " finalized={$count}\n";

if (PHP_SAPI === 'cli') {
    echo $msg;
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $msg;
}
