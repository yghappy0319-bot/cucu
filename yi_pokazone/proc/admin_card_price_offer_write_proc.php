<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/card_price_feed.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/card_price_offers.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!card_price_tables_ready()) {
    alert_goto('박스 시세 테이블이 없습니다.', '/admin/card_price_offers.php');
}

$co_idx = (int) ($_POST['co_idx'] ?? 0);
$cp_idx = (int) ($_POST['cp_idx'] ?? 0);

$form_back = $co_idx > 0 ? '/admin/card_price_offer_form.php?co_idx=' . $co_idx : '/admin/card_price_offers.php';

if ($co_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/admin/card_price_offers.php');
}

$exist = db_assoc(db_query("SELECT co_idx FROM tb_card_price_offer WHERE co_idx = {$co_idx} LIMIT 1"));
if (!$exist) {
    alert_goto('존재하지 않는 판매처 행입니다.', '/admin/card_price_offers.php');
}

if ($cp_idx < 1) {
    alert_goto('미개봉 박스를 선택해 주세요.', $form_back);
}

$pchk = db_assoc(db_query("SELECT cp_idx FROM tb_card_price_product WHERE cp_idx = {$cp_idx} LIMIT 1"));
if (!$pchk) {
    alert_goto('선택한 미개봉 박스가 없습니다.', $form_back);
}

$pref_id = trim((string) ($_POST['co_site_id'] ?? ''));
$sn      = trim((string) ($_POST['co_site_name'] ?? ''));
$url_in  = trim((string) ($_POST['co_product_url'] ?? ''));
$ctype_in = trim((string) ($_POST['co_type'] ?? ''));

$raw = [
    'site_id' => $pref_id,
    'site_name' => $sn,
    'co_type' => $ctype_in,
    'product_url' => $url_in,
    'price_won' => isset($_POST['co_price_won']) && trim((string) $_POST['co_price_won']) !== '' ? (int) $_POST['co_price_won'] : 0,
    'stock_qty' => null,
    'fetched_at' => '',
];

$stock_raw = isset($_POST['co_stock_qty']) ? trim((string) $_POST['co_stock_qty']) : '';
if ($stock_raw !== '') {
    if (!is_numeric($stock_raw)) {
        alert_goto('재고 수량은 숫자만 입력하거나 비워 주세요.', $form_back);
    }
    $raw['stock_qty'] = (int) $stock_raw;
    if ($raw['stock_qty'] < 0 || $raw['stock_qty'] > 2147483647) {
        alert_goto('재고 수량 범위가 올바르지 않습니다.', $form_back);
    }
}

if (card_price_co_buy_enabled_supported()) {
    $be_in = isset($_POST['co_buy_enabled']) ? trim((string) $_POST['co_buy_enabled']) : '1';
    $raw['buy_enabled'] = ($be_in === '0') ? 0 : 1;
}

$norm = card_price_normalize_offer($raw);
if ($norm === null) {
    alert_goto('상품 URL이 너무 길거나 공백이 포함되어 있습니다.', $form_back);
}
if (!card_price_valid_http_url((string) $norm['product_url'])) {
    alert_goto('상품 URL은 http(s)로 시작하고 공백 없이 입력해 주세요.', $form_back);
}

$co_sort = isset($_POST['co_sort']) ? (int) $_POST['co_sort'] : 0;

$sid = db_escape((string) $norm['site_id']);
$sna = db_escape((string) $norm['site_name']);
$url = db_escape((string) $norm['product_url']);
$pr  = (int) $norm['price_won'];
$stock_sql = 'NULL';
if (array_key_exists('stock_qty', $norm) && $norm['stock_qty'] !== null) {
    $stock_sql = (string) (int) $norm['stock_qty'];
}

$sets = [
    "cp_idx = {$cp_idx}",
    "co_site_id = '{$sid}'",
    "co_site_name = '{$sna}'",
    "co_product_url = '{$url}'",
    "co_price_won = {$pr}",
    "co_stock_qty = {$stock_sql}",
    "co_sort = {$co_sort}",
];

if (card_price_co_type_supported()) {
    $ct = isset($norm['co_type']) ? trim((string) $norm['co_type']) : '';
    if (mb_strlen($ct, 'UTF-8') > 120) {
        $ct = mb_substr($ct, 0, 120, 'UTF-8');
    }
    $sets[] = "co_type = '" . db_escape($ct) . "'";
}

if (card_price_co_buy_enabled_supported()) {
    $be = (int) (($norm['buy_enabled'] ?? 1) === 1 ? 1 : 0);
    $sets[] = "co_buy_enabled = {$be}";
}

$sql_up = 'UPDATE tb_card_price_offer SET ' . implode(', ', $sets) . " WHERE co_idx = {$co_idx} LIMIT 1";

if (!db_query($sql_up)) {
    alert_goto('저장 중 오류가 발생했습니다.', $form_back);
}

alert_goto('저장했습니다.', '/admin/card_price_offers.php');
