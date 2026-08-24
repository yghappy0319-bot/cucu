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

$idx    = (int) ($_POST['cp_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
if ($idx < 1 || !in_array($action, ['hide', 'show', 'delete'], true)) {
    alert_goto('잘못된 요청입니다.', '/admin/card_price_products.php');
}

$chk = db_assoc(db_query("SELECT cp_idx, cp_image_path FROM tb_card_price_product WHERE cp_idx = {$idx} LIMIT 1"));
if (!$chk) {
    alert_goto('존재하지 않는 상품입니다.', '/admin/card_price_products.php');
}

global $conn;

if ($action === 'delete') {
    $del_img = trim((string) ($chk['cp_image_path'] ?? ''));
    mysqli_begin_transaction($conn);
    if (!db_query("DELETE FROM tb_card_price_offer WHERE cp_idx = {$idx}")) {
        mysqli_rollback($conn);
        alert_goto('삭제 중 오류가 발생했습니다.', '/admin/card_price_products.php');
    }
    if (!db_query("DELETE FROM tb_card_price_product WHERE cp_idx = {$idx} LIMIT 1")) {
        mysqli_rollback($conn);
        alert_goto('삭제 중 오류가 발생했습니다.', '/admin/card_price_products.php');
    }
    mysqli_commit($conn);
    if ($del_img !== '') {
        card_price_remove_files([$del_img]);
    }
    alert_goto('삭제했습니다.', '/admin/card_price_products.php');
}

$st = $action === 'hide' ? 9 : 1;
if (!db_query("UPDATE tb_card_price_product SET cp_status = {$st} WHERE cp_idx = {$idx} LIMIT 1")) {
    alert_goto('처리 중 오류가 발생했습니다.', '/admin/card_price_products.php');
}

alert_goto($action === 'hide' ? '비노출 처리했습니다.' : '다시 노출했습니다.', '/admin/card_price_products.php');
