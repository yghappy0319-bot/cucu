<?php
define('PZ_API_JSON', true);

require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/draw_cards.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    draw_json_response(['ok' => false, 'message' => '잘못된 접근입니다.'], 405);
}

$ad = login_admin();
if (!$ad) {
    draw_json_response(['ok' => false, 'message' => '로그인이 필요합니다.'], 401);
}

if (!db_table_exists('tb_draw_product_card')) {
    draw_json_response(['ok' => false, 'message' => '카드 테이블이 없습니다.'], 503);
}

$dp_idx = (int) ($_POST['dp_idx'] ?? 0);
$dpc_idx = (int) ($_POST['dpc_idx'] ?? 0);
$grade = draw_card_grade_normalize($_POST['dpc_grade'] ?? '');

if ($dp_idx < 1 || $dpc_idx < 1) {
    draw_json_response(['ok' => false, 'message' => '잘못된 요청입니다.'], 400);
}

$row = db_assoc(db_query("
    SELECT dpc_idx
    FROM tb_draw_product_card
    WHERE dpc_idx = {$dpc_idx} AND dp_idx = {$dp_idx}
    LIMIT 1
"));
if (!$row) {
    draw_json_response(['ok' => false, 'message' => '카드를 찾을 수 없습니다.'], 404);
}

$grade_sql = db_escape($grade);
if (!db_query("
    UPDATE tb_draw_product_card
    SET dpc_grade = '{$grade_sql}'
    WHERE dpc_idx = {$dpc_idx} AND dp_idx = {$dp_idx}
    LIMIT 1
")) {
    draw_json_response(['ok' => false, 'message' => '등급 저장에 실패했습니다.'], 500);
}

draw_json_response([
    'ok' => true,
    'dpc_idx' => $dpc_idx,
    'dpc_grade' => $grade,
]);
