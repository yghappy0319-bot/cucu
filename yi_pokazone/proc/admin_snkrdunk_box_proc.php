<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_snkrdunk_box.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/snkrdunk_boxes.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!snkrdunk_box_tables_ready() || !snkrdunk_box_name_ko_supported()) {
    alert_goto('한글명 관리 테이블을 먼저 준비해 주세요.', '/admin/snkrdunk_boxes.php');
}

$action = trim((string) ($_POST['action'] ?? ''));
$names = $_POST['name_ko'] ?? [];
if ($action !== 'save_names' || !is_array($names) || count($names) > 100) {
    alert_goto('잘못된 요청입니다.', '/admin/snkrdunk_boxes.php');
}

$st = trim((string) ($_POST['st'] ?? 'all'));
if (!in_array($st, ['all', 'done', 'empty'], true)) {
    $st = 'all';
}
$q = trim((string) ($_POST['q'] ?? ''));
$page_no = max(1, (int) ($_POST['p'] ?? 1));
$return_params = [];
if ($st !== 'all') $return_params['st'] = $st;
if ($q !== '') $return_params['q'] = $q;
if ($page_no > 1) $return_params['p'] = $page_no;
$return_url = '/admin/snkrdunk_boxes.php'
    . ($return_params ? '?' . http_build_query($return_params) : '');

$updated = 0;
db_query('START TRANSACTION');

foreach ($names as $id_raw => $name_raw) {
    $product_id = (int) $id_raw;
    if ($product_id < 1 || is_array($name_raw)) {
        db_query('ROLLBACK');
        alert_goto('잘못된 상품 정보가 포함되어 있습니다.', $return_url);
    }

    $name_ko = trim((string) $name_raw);
    if (mb_strlen($name_ko, 'UTF-8') > 255) {
        $name_ko = mb_substr($name_ko, 0, 255, 'UTF-8');
    }
    $name_esc = db_escape($name_ko);

    $ok = db_query("
        UPDATE tb_snkrdunk_box
        SET sb_name_ko = '{$name_esc}'
        WHERE sb_product_id = {$product_id}
        LIMIT 1
    ");
    if (!$ok) {
        db_query('ROLLBACK');
        alert_goto('한글명 저장 중 오류가 발생했습니다.', $return_url);
    }
    $updated++;
}

db_query('COMMIT');
alert_goto(number_format($updated) . '개 상품의 한글명을 저장했습니다.', $return_url);
