<?php
define('PZ_API_JSON', true);

require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_auction.php';

if (!auction_table_ok()) {
    auction_json_response(['ok' => false, 'message' => '경매 DB가 없습니다.'], 503);
}

$au_idx = (int) ($_GET['au_idx'] ?? 0);
if ($au_idx < 1) {
    auction_json_response(['ok' => false, 'message' => '잘못된 요청입니다.'], 400);
}

auction_finalize_expired($au_idx);

$me       = login_member();
$snapshot = auction_view_snapshot($au_idx, $me);

if ($snapshot === null) {
    auction_json_response(['ok' => false, 'message' => '경매를 찾을 수 없습니다.'], 404);
}

auction_json_response([
    'ok'       => true,
    'snapshot' => $snapshot,
]);
