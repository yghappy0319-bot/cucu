<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_auction.php';
require_once __DIR__ . '/../lib/_auction_order.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/auction/auction.php');
}

$me = login_member();
if (!$me || (int) $me['mb_level'] < 9) {
    alert_goto('관리자만 사용할 수 있는 테스트 기능입니다.', '/auction/auction.php');
}

$au_idx = (int) ($_POST['au_idx'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$return = '/auction/auction_view.php?idx=' . $au_idx;

if ($au_idx < 1) {
    alert_goto('잘못된 요청입니다.', '/auction/auction.php');
}

if ($action === 'win') {
    $result = auction_force_finalize_won($au_idx, true);
    if (!$result['ok']) {
        alert_goto($result['error'] ?? '낙찰 처리에 실패했습니다.', $return);
    }
    alert_goto('테스트: 최고 입찰자에게 낙찰 처리되었습니다. (푸시 발송)', $return);
}

if ($action === 'fail') {
    $result = auction_force_finalize_failed($au_idx);
    if (!$result['ok']) {
        alert_goto($result['error'] ?? '유찰 처리에 실패했습니다.', $return);
    }
    alert_goto('테스트: 유찰 처리되었습니다.', $return);
}

alert_goto('알 수 없는 요청입니다.', $return);
