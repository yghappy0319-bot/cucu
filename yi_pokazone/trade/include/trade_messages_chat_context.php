<?php
/**
 * 거래 메시지함 · 대화 페이지 공통 — GET tr_idx / room_idx 로 채팅 컨텍스트 설정
 *
 * 사전 변수: $my_mb (int), $trade_chat_ok (bool), $tr_idx_q (int), $room_idx_q (int)
 * 설정됨: $active_trade, $trade_chat_initial_room, $chat_blocked_reason, $panel_context_title
 */
if (!isset($my_mb, $trade_chat_ok, $tr_idx_q, $room_idx_q)) {
    return;
}

$active_trade            = null;
$trade_chat_initial_room = 0;
$chat_blocked_reason     = '';
$panel_context_title     = '';

if (!$trade_chat_ok || $tr_idx_q < 1) {
    return;
}

$rs = db_query("
    SELECT t.*, m.mb_nick AS seller_nick
    FROM tb_trade t
    LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
    WHERE t.tr_idx = {$tr_idx_q}
    LIMIT 1
");
$active_trade = db_assoc($rs);
if (!$active_trade || (int) $active_trade['tr_status'] !== 1) {
    $chat_blocked_reason = '거래글을 찾을 수 없거나 종료된 글입니다.';
    $active_trade        = null;

    return;
}

$panel_context_title = htmlspecialchars((string) $active_trade['tr_title'], ENT_QUOTES, 'UTF-8');

$is_owner = $my_mb === (int) $active_trade['mb_idx'];

if ($is_owner) {
    if ($room_idx_q > 0) {
        $chk = "SELECT room_idx FROM tb_trade_room
                 WHERE room_idx = {$room_idx_q} AND tr_idx = {$tr_idx_q} LIMIT 1";
        $rw = db_assoc(db_query($chk));
        if ($rw && !(function_exists('trade_chat_room_is_hidden_for') && trade_chat_room_is_hidden_for($room_idx_q, $my_mb))) {
            $trade_chat_initial_room = $room_idx_q;
        }
    }
    if ($trade_chat_initial_room < 1) {
        $trade_chat_initial_room = function_exists('trade_chat_find_open_seller_room')
            ? trade_chat_find_open_seller_room($tr_idx_q)
            : 0;
    }
} else {
    if ($room_idx_q > 0 && function_exists('trade_chat_buyer_owns_room')
        && trade_chat_buyer_owns_room($room_idx_q, $tr_idx_q, $my_mb)
        && !(function_exists('trade_chat_room_is_hidden_for') && trade_chat_room_is_hidden_for($room_idx_q, $my_mb))) {
        $trade_chat_initial_room = $room_idx_q;
    } else {
        $buyer_nick = '';
        if (isset($me) && is_array($me)) {
            $buyer_nick = trim((string) ($me['mb_nick'] ?? ''));
        }
        $opened = trade_chat_ensure_buyer_inquiry($tr_idx_q, $my_mb, $active_trade, $buyer_nick);
        if ($opened['ok'] && $opened['room_idx'] > 0) {
            $trade_chat_initial_room = $opened['room_idx'];
        } else {
            $chat_blocked_reason = (string) ($opened['error'] ?? '문의방을 열 수 없습니다.');
        }
    }
}

if ($active_trade && $chat_blocked_reason === '' && $trade_chat_initial_room < 1 && $is_owner) {
    $chat_blocked_reason = '아직 받은 문의가 없습니다.';
}
