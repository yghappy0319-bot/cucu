<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/shop_products.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!db_table_exists('tb_shop_product')) {
    alert_goto('상품 테이블이 없습니다.', '/admin/shop_products.php');
}

$mode    = (string) ($_POST['mode'] ?? 'insert');
$idx     = (int) ($_POST['idx'] ?? 0);
$name    = trim((string) ($_POST['sp_name'] ?? ''));
$sub     = trim((string) ($_POST['sp_subtitle'] ?? ''));
$sum     = trim((string) ($_POST['sp_summary'] ?? ''));
$content = (string) ($_POST['sp_content'] ?? '');
$price   = (int) ($_POST['sp_price'] ?? -1);

$orig_raw = trim((string) ($_POST['sp_original_price'] ?? ''));
$img      = trim((string) ($_POST['sp_image_path'] ?? ''));
$link     = trim((string) ($_POST['sp_link'] ?? ''));
$ship     = isset($_POST['sp_shipping_free']) ? 1 : 0;
$featured = isset($_POST['sp_featured']) ? 1 : 0;
$sort     = isset($_POST['sp_sort']) ? (int) $_POST['sp_sort'] : 0;
$status   = (int) ($_POST['sp_status'] ?? 1);

$form_back_insert = '/admin/shop_product_form.php';
$form_back_edit   = '/admin/shop_product_form.php?idx=' . $idx;

if ($name === '' || mb_strlen($name) > 200) {
    alert_goto('상품명을 1~200자 이내로 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (mb_strlen($sub) > 300) {
    alert_goto('부제는 300자 이내로 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (mb_strlen($sum) > 500) {
    alert_goto('요약은 500자 이내로 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (mb_strlen($content) > 50000) {
    alert_goto('상세 본문은 50,000자 이내로 작성해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if ($price < 0 || $price > 4294967295) {
    alert_goto('판매가를 올바르게 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}

$orig_sql = 'NULL';
if ($orig_raw !== '') {
    $op = (int) $orig_raw;
    if ($op < 1 || $op > 4294967295) {
        alert_goto('정가를 올바르게 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
    }
    $orig_sql = (string) $op;
}

if (mb_strlen($img) > 255) {
    alert_goto('이미지 경로는 255자 이내입니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (mb_strlen($link) > 500) {
    alert_goto('외부 URL은 500자 이내입니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if ($link !== '' && !preg_match('#^https?://#i', $link)) {
    alert_goto('외부 구매 URL은 http(s)로 시작해야 합니다. 자체 상세만 쓰려면 비워 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}

if ($status !== 1 && $status !== 9) {
    alert_goto('노출 상태가 올바르지 않습니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}

$sql_sub     = $sub === '' ? 'NULL' : "'" . db_escape($sub) . "'";
$sql_sum     = $sum === '' ? 'NULL' : "'" . db_escape($sum) . "'";
$sql_content = trim($content) === '' ? 'NULL' : "'" . db_escape($content) . "'";
$sql_img     = $img === '' ? 'NULL' : "'" . db_escape($img) . "'";
$sql_link    = $link === '' ? 'NULL' : "'" . db_escape($link) . "'";
$esc_name    = db_escape($name);

if ($mode === 'edit' && $idx > 0) {
    $rs = db_query("SELECT sp_idx FROM tb_shop_product WHERE sp_idx = {$idx} LIMIT 1");
    if (!db_assoc($rs)) {
        alert_goto('존재하지 않는 상품입니다.', '/admin/shop_products.php');
    }

    $sql = "
        UPDATE tb_shop_product SET
            sp_name            = '{$esc_name}',
            sp_subtitle        = {$sql_sub},
            sp_summary         = {$sql_sum},
            sp_content         = {$sql_content},
            sp_price           = {$price},
            sp_original_price  = {$orig_sql},
            sp_image_path      = {$sql_img},
            sp_link            = {$sql_link},
            sp_shipping_free   = {$ship},
            sp_featured        = {$featured},
            sp_sort            = {$sort},
            sp_status          = {$status}
        WHERE sp_idx = {$idx}
        LIMIT 1
    ";
    if (!db_query($sql)) {
        alert_goto('저장 중 오류가 발생했습니다.', $form_back_edit);
    }
    alert_goto('상품이 수정되었습니다.', '/admin/shop_products.php');
}

if ($mode === 'edit') {
    alert_goto('잘못된 요청입니다.', '/admin/shop_products.php');
}

$sql = "
    INSERT INTO tb_shop_product (
        sp_name, sp_subtitle, sp_summary, sp_content, sp_price, sp_original_price,
        sp_image_path, sp_link, sp_shipping_free, sp_featured, sp_sort, sp_status
    ) VALUES (
        '{$esc_name}', {$sql_sub}, {$sql_sum}, {$sql_content}, {$price}, {$orig_sql},
        {$sql_img}, {$sql_link}, {$ship}, {$featured}, {$sort}, {$status}
    )
";
if (!db_query($sql)) {
    alert_goto('등록 중 오류가 발생했습니다.', $form_back_insert);
}
alert_goto('상품이 등록되었습니다.', '/admin/shop_products.php');
