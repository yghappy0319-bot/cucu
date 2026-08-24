<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/card_price_feed.php';
require_once __DIR__ . '/../lib/_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/card_price_products.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!card_price_tables_ready()) {
    alert_goto('박스 시세 테이블이 없습니다.', '/admin/card_price_products.php');
}

$mode = (string) ($_POST['mode'] ?? 'insert');
$idx  = (int) ($_POST['idx'] ?? 0);

$name      = trim((string) ($_POST['cp_name'] ?? ''));
$set_label = trim((string) ($_POST['cp_set_label'] ?? ''));
$emoji_in  = trim((string) ($_POST['cp_emoji'] ?? ''));
$sort      = isset($_POST['cp_sort']) ? (int) $_POST['cp_sort'] : 0;
$status    = (int) ($_POST['cp_status'] ?? 1);

$form_back_insert = '/admin/card_price_product_form.php';
$form_back_edit   = '/admin/card_price_product_form.php?idx=' . $idx;

if ($name === '' || mb_strlen($name) > 255) {
    alert_goto('상품명을 1~255자 이내로 입력해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if (mb_strlen($set_label) > 80) {
    alert_goto('세트 라벨은 80자 이내입니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
$emoji = $emoji_in !== '' ? $emoji_in : '📦';
if (mb_strlen($emoji) > 16) {
    alert_goto('이모지 필드는 16자 이내입니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
if ($status !== 1 && $status !== 9) {
    alert_goto('노출 상태가 올바르지 않습니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}

$prev_image = '';
if ($mode === 'edit' && $idx > 0) {
    $exist_row = db_assoc(db_query("SELECT cp_idx, cp_image_path FROM tb_card_price_product WHERE cp_idx = {$idx} LIMIT 1"));
    if (!$exist_row) {
        alert_goto('존재하지 않는 상품입니다.', '/admin/card_price_products.php');
    }
    $prev_image = trim((string) ($exist_row['cp_image_path'] ?? ''));
}

$ids    = isset($_POST['offer_site_id']) && is_array($_POST['offer_site_id']) ? $_POST['offer_site_id'] : [];
$names  = isset($_POST['offer_site_name']) && is_array($_POST['offer_site_name']) ? $_POST['offer_site_name'] : [];
$types  = isset($_POST['offer_co_type']) && is_array($_POST['offer_co_type']) ? $_POST['offer_co_type'] : [];
$urls   = isset($_POST['offer_product_url']) && is_array($_POST['offer_product_url']) ? $_POST['offer_product_url'] : [];
$prices = isset($_POST['offer_price_won']) && is_array($_POST['offer_price_won']) ? $_POST['offer_price_won'] : [];
$stocks = isset($_POST['offer_stock_qty']) && is_array($_POST['offer_stock_qty']) ? $_POST['offer_stock_qty'] : [];
$buy_es = isset($_POST['offer_buy_enabled']) && is_array($_POST['offer_buy_enabled']) ? $_POST['offer_buy_enabled'] : [];

$max = max(count($ids), count($names), count($types), count($urls), count($prices), count($stocks), count($buy_es));
$offer_buy_supported = card_price_co_buy_enabled_supported();
$offers_norm = [];
for ($i = 0; $i < $max; $i++) {
    $sn = isset($names[$i]) ? trim((string) $names[$i]) : '';
    $url = isset($urls[$i]) ? trim((string) $urls[$i]) : '';
    if ($sn === '' && $url === '') {
        continue;
    }
    $pref_id = isset($ids[$i]) ? trim((string) $ids[$i]) : '';
    $co_type_in = isset($types[$i]) ? trim((string) $types[$i]) : '';
    $raw = [
        'site_id' => $pref_id,
        'site_name' => $sn,
        'co_type' => $co_type_in,
        'product_url' => $url,
        'price_won' => isset($prices[$i]) && trim((string) $prices[$i]) !== '' ? (int) $prices[$i] : 0,
        'stock_qty' => null,
        'fetched_at' => '',
    ];
    $stock_raw = isset($stocks[$i]) ? trim((string) $stocks[$i]) : '';
    if ($stock_raw !== '') {
        if (!is_numeric($stock_raw)) {
            alert_goto('재고 수량은 숫자만 입력하거나 비워 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
        }
        $raw['stock_qty'] = (int) $stock_raw;
        if ($raw['stock_qty'] < 0 || $raw['stock_qty'] > 2147483647) {
            alert_goto('재고 수량 범위가 올바르지 않습니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
        }
    }
    if ($offer_buy_supported) {
        $be_in = isset($buy_es[$i]) ? trim((string) $buy_es[$i]) : '1';
        $raw['buy_enabled'] = ($be_in === '0') ? 0 : 1;
    }
    $norm = card_price_normalize_offer($raw);
    if ($norm === null) {
        alert_goto('판매처 행의 상품 URL이 너무 길거나 공백이 포함되어 있습니다.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
    }
    $offers_norm[] = $norm;
}

$cp_file = isset($_FILES['cp_image']) && is_array($_FILES['cp_image']) ? $_FILES['cp_image'] : null;
$up = card_price_upload_product_image($cp_file);
if (!$up['ok']) {
    alert_goto($up['error'], $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}
$uploaded_web_path = $up['path'];
$remove_image = isset($_POST['cp_image_remove']) && (string) $_POST['cp_image_remove'] === '1';

$img = $prev_image;
if ($uploaded_web_path !== '') {
    $img = $uploaded_web_path;
} elseif ($remove_image) {
    $img = '';
}
$img = trim($img);
if (mb_strlen($img) > 255) {
    if ($uploaded_web_path !== '') {
        card_price_remove_files([$uploaded_web_path]);
    }
    alert_goto('이미지 경로가 너무 깁니다. 관리자에게 문의해 주세요.', $mode === 'edit' && $idx > 0 ? $form_back_edit : $form_back_insert);
}

$sql_set_label = $set_label === '' ? 'NULL' : "'" . db_escape($set_label) . "'";
$sql_img       = $img === '' ? 'NULL' : "'" . db_escape($img) . "'";
$sql_emoji     = db_escape($emoji);
$sql_name      = db_escape($name);

global $conn;

if ($mode === 'edit' && $idx > 0) {
    mysqli_begin_transaction($conn);

    $sql_up = "
        UPDATE tb_card_price_product SET
            cp_name       = '{$sql_name}',
            cp_set_label  = {$sql_set_label},
            cp_emoji      = '{$sql_emoji}',
            cp_image_path = {$sql_img},
            cp_sort       = {$sort},
            cp_status     = {$status}
        WHERE cp_idx = {$idx}
        LIMIT 1
    ";
    if (!db_query($sql_up)) {
        mysqli_rollback($conn);
        if ($uploaded_web_path !== '') {
            card_price_remove_files([$uploaded_web_path]);
        }
        alert_goto('저장 중 오류가 발생했습니다.', $form_back_edit);
    }

    if (!db_query("DELETE FROM tb_card_price_offer WHERE cp_idx = {$idx}")) {
        mysqli_rollback($conn);
        if ($uploaded_web_path !== '') {
            card_price_remove_files([$uploaded_web_path]);
        }
        alert_goto('판매처 행 갱신 중 오류가 발생했습니다.', $form_back_edit);
    }

    $offer_has_buy_en = card_price_co_buy_enabled_supported();
    $offer_has_co_type = card_price_co_type_supported();
    $co_sort = count($offers_norm);
    foreach ($offers_norm as $o) {
        $ins = card_price_admin_offer_insert_sql($idx, $co_sort, $o, $offer_has_buy_en, $offer_has_co_type);
        if (!db_query($ins)) {
            mysqli_rollback($conn);
            if ($uploaded_web_path !== '') {
                card_price_remove_files([$uploaded_web_path]);
            }
            alert_goto('판매처 행 저장 중 오류가 발생했습니다.', $form_back_edit);
        }
        $co_sort--;
    }

    mysqli_commit($conn);

    if ($uploaded_web_path !== '' && $prev_image !== '' && $prev_image !== $uploaded_web_path) {
        card_price_remove_files([$prev_image]);
    } elseif ($remove_image && $uploaded_web_path === '' && $prev_image !== '') {
        card_price_remove_files([$prev_image]);
    }

    alert_goto('저장했습니다.', '/admin/card_price_products.php');
}

if ($mode === 'edit') {
    alert_goto('잘못된 요청입니다.', '/admin/card_price_products.php');
}

mysqli_begin_transaction($conn);

$sql_ins = "
    INSERT INTO tb_card_price_product
        (cp_name, cp_set_label, cp_emoji, cp_image_path, cp_sort, cp_status)
    VALUES
        ('{$sql_name}', {$sql_set_label}, '{$sql_emoji}', {$sql_img}, {$sort}, {$status})
";
if (!db_query($sql_ins)) {
    mysqli_rollback($conn);
    if ($uploaded_web_path !== '') {
        card_price_remove_files([$uploaded_web_path]);
    }
    alert_goto('등록 중 오류가 발생했습니다.', $form_back_insert);
}

$new_id = (int) mysqli_insert_id($conn);
if ($new_id < 1) {
    mysqli_rollback($conn);
    if ($uploaded_web_path !== '') {
        card_price_remove_files([$uploaded_web_path]);
    }
    alert_goto('등록 후 식별 오류가 발생했습니다.', $form_back_insert);
}

$offer_has_buy_en = card_price_co_buy_enabled_supported();
$offer_has_co_type = card_price_co_type_supported();
$co_sort = count($offers_norm);
foreach ($offers_norm as $o) {
    $ins = card_price_admin_offer_insert_sql($new_id, $co_sort, $o, $offer_has_buy_en, $offer_has_co_type);
    if (!db_query($ins)) {
        mysqli_rollback($conn);
        if ($uploaded_web_path !== '') {
            card_price_remove_files([$uploaded_web_path]);
        }
        alert_goto('판매처 행 저장 중 오류가 발생했습니다.', $form_back_insert);
    }
    $co_sort--;
}

mysqli_commit($conn);
alert_goto('등록했습니다.', '/admin/card_price_products.php');
