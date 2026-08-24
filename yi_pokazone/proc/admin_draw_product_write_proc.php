<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/draw_products.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_draw_product')) {
    alert_goto('뽑기 테이블이 없습니다.', '/admin/draw_products.php');
}

$mode   = (string) ($_POST['mode'] ?? 'insert');
$idx    = (int) ($_POST['idx'] ?? 0);
$name   = trim((string) ($_POST['dp_name'] ?? ''));
$code   = trim((string) ($_POST['dp_code'] ?? ''));
$pack_type = trim((string) ($_POST['dp_pack_type'] ?? 'expansion'));
$sort   = isset($_POST['dp_sort']) ? (int) $_POST['dp_sort'] : 0;
$status = (int) ($_POST['dp_status'] ?? 1);
$pack_count_map = [
    'expansion' => 30,
    'enhanced' => 20,
    'highclass' => 10,
];

$form_back_insert = '/admin/draw_product_form.php';
$form_back_edit   = '/admin/draw_product_form.php?idx=' . $idx;

if ($name === '' || mb_strlen($name) > 200) {
    alert_goto('상자명을 1~200자 이내로 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (mb_strlen($code) > 80) {
    alert_goto('제품번호는 80자 이내입니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (!isset($pack_count_map[$pack_type])) {
    alert_goto('팩 종류가 올바르지 않습니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if ($status !== 1 && $status !== 9) {
    alert_goto('노출 상태가 올바르지 않습니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
$pack_count = (int) $pack_count_map[$pack_type];

$prev_image = '';
if ($mode === 'edit' && $idx > 0) {
    $exist_row = db_assoc(db_query("SELECT dp_idx, dp_image_path FROM tb_draw_product WHERE dp_idx = {$idx} LIMIT 1"));
    if (!$exist_row) {
        alert_goto('존재하지 않는 항목입니다.', '/admin/draw_products.php');
    }
    $prev_image = trim((string) ($exist_row['dp_image_path'] ?? ''));
}

$dp_file = isset($_FILES['dp_image']) && is_array($_FILES['dp_image']) ? $_FILES['dp_image'] : null;
$up = draw_upload_product_image($dp_file);
if (!$up['ok']) {
    alert_goto($up['error'], $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
$uploaded_web_path = $up['path'];
$remove_image = isset($_POST['dp_image_remove']) && (string) $_POST['dp_image_remove'] === '1';

$img = $prev_image;
if ($uploaded_web_path !== '') {
    $img = $uploaded_web_path;
} elseif ($remove_image) {
    $img = '';
}
$img = trim($img);
if (mb_strlen($img) > 255) {
    if ($uploaded_web_path !== '') {
        draw_remove_files([$uploaded_web_path]);
    }
    alert_goto('이미지 경로가 너무 깁니다. 관리자에게 문의해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}

$sql_name = db_escape($name);
$sql_code = $code === '' ? 'NULL' : "'" . db_escape($code) . "'";
$sql_pack_type = db_escape($pack_type);
$sql_img  = $img === '' ? 'NULL' : "'" . db_escape($img) . "'";

if ($mode === 'edit' && $idx > 0) {
    $sql = "
        UPDATE tb_draw_product SET
            dp_name       = '{$sql_name}',
            dp_code       = {$sql_code},
            dp_pack_type  = '{$sql_pack_type}',
            dp_pack_count = {$pack_count},
            dp_image_path = {$sql_img},
            dp_sort       = {$sort},
            dp_status     = {$status}
        WHERE dp_idx = {$idx}
        LIMIT 1
    ";
    if (!db_query($sql)) {
        if ($uploaded_web_path !== '') {
            draw_remove_files([$uploaded_web_path]);
        }
        alert_goto('저장 중 오류가 발생했습니다.', $form_back_edit);
    }

    if ($uploaded_web_path !== '' && $prev_image !== '' && $prev_image !== $uploaded_web_path) {
        draw_remove_files([$prev_image]);
    } elseif ($remove_image && $uploaded_web_path === '' && $prev_image !== '') {
        draw_remove_files([$prev_image]);
    }

    alert_goto('저장했습니다.', '/admin/draw_products.php');
}

if ($mode === 'edit') {
    alert_goto('잘못된 요청입니다.', '/admin/draw_products.php');
}

$sql = "
    INSERT INTO tb_draw_product (dp_name, dp_code, dp_pack_type, dp_pack_count, dp_image_path, dp_sort, dp_status)
    VALUES ('{$sql_name}', {$sql_code}, '{$sql_pack_type}', {$pack_count}, {$sql_img}, {$sort}, {$status})
";
if (!db_query($sql)) {
    if ($uploaded_web_path !== '') {
        draw_remove_files([$uploaded_web_path]);
    }
    alert_goto('등록 중 오류가 발생했습니다.', $form_back_insert);
}

alert_goto('등록했습니다.', '/admin/draw_products.php');
