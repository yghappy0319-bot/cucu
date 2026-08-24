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

$idx    = (int) ($_POST['sp_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
if ($idx < 1 || !in_array($action, ['hide', 'show'], true)) {
    alert_goto('잘못된 요청입니다.', '/admin/shop_products.php');
}

$st = $action === 'hide' ? 9 : 1;
if (!db_query("UPDATE tb_shop_product SET sp_status = {$st} WHERE sp_idx = {$idx} LIMIT 1")) {
    alert_goto('처리 중 오류가 발생했습니다.', '/admin/shop_products.php');
}

alert_goto($action === 'hide' ? '상품을 비노출 처리했습니다.' : '상품을 다시 노출했습니다.', '/admin/shop_products.php');
