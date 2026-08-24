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
$action = trim((string) ($_POST['action'] ?? ''));
if ($co_idx < 1 || $action !== 'delete') {
    alert_goto('잘못된 요청입니다.', '/admin/card_price_offers.php');
}

$chk = db_assoc(db_query("SELECT co_idx FROM tb_card_price_offer WHERE co_idx = {$co_idx} LIMIT 1"));
if (!$chk) {
    alert_goto('존재하지 않는 판매처 행입니다.', '/admin/card_price_offers.php');
}

if (!db_query("DELETE FROM tb_card_price_offer WHERE co_idx = {$co_idx} LIMIT 1")) {
    alert_goto('삭제 중 오류가 발생했습니다.', '/admin/card_price_offers.php');
}

alert_goto('삭제했습니다.', '/admin/card_price_offers.php');
