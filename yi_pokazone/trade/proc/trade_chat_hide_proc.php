<?php
require_once __DIR__ . '/../../lib/_function.php';

$return = '/trade/trade_messages.php?tab=closed';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    alert_goto('잘못된 접근입니다.', $return);
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($return));
}

$tr_idx   = (int) ($_POST['tr_idx'] ?? 0);
$room_idx = (int) ($_POST['room_idx'] ?? 0);
if ($tr_idx < 1 || $room_idx < 1) {
    alert_goto('삭제할 대화를 찾을 수 없습니다.', $return);
}

if (!function_exists('trade_chat_hide_closed_room')) {
    alert_goto('삭제 기능을 불러오지 못했습니다.', $return);
}

$result = trade_chat_hide_closed_room($room_idx, $tr_idx, (int) $me['mb_idx']);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '대화를 삭제하지 못했습니다.'), $return);
}

alert_goto((string) ($result['message'] ?? '목록에서 삭제했습니다.'), $return);
