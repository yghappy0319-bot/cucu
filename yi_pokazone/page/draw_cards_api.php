<?php
define('PZ_API_JSON', true);

require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/draw_cards.php';

if (!login_member()) {
    draw_json_response(['ok' => false, 'message' => '로그인 후 이용 가능합니다.'], 401);
}

$dp_idx = max(0, (int) ($_GET['dp_idx'] ?? 0));
$count  = max(1, min(20, (int) ($_GET['count'] ?? 5)));
$ball_idx = (int) ($_GET['ball_idx'] ?? -1);

if ($dp_idx < 1) {
    draw_json_response(['ok' => false, 'message' => '잘못된 요청입니다.'], 400);
}

if (!db_table_exists('tb_draw_product')) {
    draw_json_response(['ok' => false, 'message' => '뽑기 테이블이 없습니다.'], 503);
}

$row = db_assoc(db_query("
    SELECT dp_idx, dp_pack_type, dp_pack_count
    FROM tb_draw_product
    WHERE dp_idx = {$dp_idx} AND dp_status = 1
    LIMIT 1
"));
if (!$row) {
    draw_json_response(['ok' => false, 'message' => '존재하지 않는 팩입니다.'], 404);
}

$pack_type = trim((string) ($row['dp_pack_type'] ?? 'expansion'));
$pack_count = max(1, (int) ($row['dp_pack_count'] ?? 30));

if (draw_pack_type_supports_box_plan($pack_type)) {
    draw_ensure_box_plan($dp_idx, $pack_type, $pack_count);
    if ($ball_idx < 0) {
        draw_json_response(['ok' => false, 'message' => '몬스터볼을 선택해 주세요.'], 400);
    }
    if ($ball_idx >= $pack_count) {
        draw_json_response(['ok' => false, 'message' => '잘못된 몬스터볼입니다.'], 400);
    }
}

$pick = draw_pick_random_cards($dp_idx, $count, $pack_type, $pack_count, $ball_idx);

if (!empty($pick['error'])) {
    draw_json_response(['ok' => false, 'message' => (string) $pick['error']], 409);
}

$out = [
    'ok' => true,
    'cardback' => $pick['cardback'],
    'slides' => $pick['slides'],
];
if (!empty($pick['box']) && is_array($pick['box'])) {
    $out['box'] = $pick['box'];
}

draw_json_response($out);
