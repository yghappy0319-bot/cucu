<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';
require_once __DIR__ . '/../lib/draw_cards.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/draw_products.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_draw_product') || !db_table_exists('tb_draw_product_card')) {
    alert_goto('필수 테이블이 없습니다.', '/admin/draw_products.php');
}

$dp_idx = (int) ($_POST['dp_idx'] ?? 0);
if ($dp_idx < 1) {
    alert_goto('상자 정보가 올바르지 않습니다.', '/admin/draw_products.php');
}

$exists = db_assoc(db_query("SELECT dp_idx FROM tb_draw_product WHERE dp_idx = {$dp_idx} LIMIT 1"));
if (!$exists) {
    alert_goto('존재하지 않는 상자입니다.', '/admin/draw_products.php');
}

$back_url = '/admin/draw_product_cards.php?dp_idx=' . $dp_idx;

$has_grade_col = false;
if (db_table_exists('tb_draw_product_card')) {
    $col_rs = db_query("SHOW COLUMNS FROM tb_draw_product_card LIKE 'dpc_grade'");
    $has_grade_col = (bool) db_assoc($col_rs);
}

$grade_updated = 0;
if ($has_grade_col && isset($_POST['dpc_grade']) && is_array($_POST['dpc_grade'])) {
    foreach ($_POST['dpc_grade'] as $dpc_idx_raw => $grade_raw) {
        $dpc_idx = (int) $dpc_idx_raw;
        if ($dpc_idx < 1) {
            continue;
        }
        $grade = draw_card_grade_normalize($grade_raw);
        $grade_sql = db_escape($grade);
        $rs_grade = db_query("
            UPDATE tb_draw_product_card
            SET dpc_grade = '{$grade_sql}'
            WHERE dpc_idx = {$dpc_idx} AND dp_idx = {$dp_idx}
            LIMIT 1
        ");
        if ($rs_grade) {
            $grade_updated += 1;
        }
    }
}

$up = draw_upload_product_cards($_FILES['dpc_images'] ?? null);
if (!$up['ok']) {
    alert_goto($up['error'], $back_url);
}
$uploaded_items = is_array($up['items'] ?? null) ? $up['items'] : [];
$uploaded_paths = [];
foreach ($uploaded_items as $it) {
    $p = trim((string) ($it['path'] ?? ''));
    if ($p !== '') {
        $uploaded_paths[] = $p;
    }
}

$remove_ids_raw = isset($_POST['dpc_remove_ids']) && is_array($_POST['dpc_remove_ids']) ? $_POST['dpc_remove_ids'] : [];
$remove_ids = [];
foreach ($remove_ids_raw as $rid) {
    $rid = (int) $rid;
    if ($rid > 0) {
        $remove_ids[$rid] = $rid;
    }
}
$remove_ids = array_values($remove_ids);

if (!empty($remove_ids)) {
    $id_sql = implode(',', array_map('intval', $remove_ids));
    $del_paths = [];
    $rs_del = db_query("SELECT dpc_idx, dpc_image_path FROM tb_draw_product_card WHERE dp_idx = {$dp_idx} AND dpc_idx IN ({$id_sql})");
    while ($r = db_assoc($rs_del)) {
        $path = trim((string) ($r['dpc_image_path'] ?? ''));
        if ($path !== '') {
            $del_paths[] = $path;
        }
    }
    db_query("DELETE FROM tb_draw_product_card WHERE dp_idx = {$dp_idx} AND dpc_idx IN ({$id_sql})");
    if (!empty($del_paths)) {
        draw_remove_files($del_paths);
    }
}

if (!empty($uploaded_paths)) {
    foreach ($uploaded_items as $it) {
        $path = trim((string) ($it['path'] ?? ''));
        $orig_name = trim((string) ($it['orig_name'] ?? ''));
        if (mb_strlen($path) > 255) {
            continue;
        }
        $path_sql = db_escape($path);
        if ($orig_name === '') {
            $orig_name = basename($path);
        }
        if (mb_strlen($orig_name) > 255) {
            $orig_name = mb_substr($orig_name, 0, 255);
        }
        $orig_name_sql = db_escape($orig_name);
        db_query("
            INSERT INTO tb_draw_product_card (dp_idx, dpc_orig_name, dpc_image_path, dpc_sort)
            VALUES ({$dp_idx}, '{$orig_name_sql}', '{$path_sql}', 0)
        ");
    }
}

if (empty($remove_ids) && empty($uploaded_paths) && $grade_updated < 1) {
    alert_goto('변경된 내용이 없습니다.', $back_url);
}

alert_goto('상자 카드 정보를 저장했습니다.', $back_url);
