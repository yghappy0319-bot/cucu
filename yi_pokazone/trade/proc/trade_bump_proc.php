<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_listing.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade_sales.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/trade/trade_sales.php'));
}

$tr_idx   = (int) ($_POST['tr_idx'] ?? 0);
$page_num = max(1, (int) ($_POST['p'] ?? 1));
$tab      = trade_seller_listing_tab_normalize((string) ($_POST['tab'] ?? 'active'));
$return   = '/trade/trade_sales.php?tab=' . urlencode($tab);
if ($page_num > 1) {
    $return .= '&p=' . $page_num;
}

if ($tr_idx < 1) {
    alert_goto('잘못된 요청입니다.', $return);
}

$result = trade_post_bump((int) $me['mb_idx'], $tr_idx);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '끌올에 실패했습니다.'), $return);
}

alert_goto('끌올되었습니다. 거래게시판 상단에 노출됩니다. (' . number_format(trade_bump_point_cost()) . 'P 차감)', $return);
