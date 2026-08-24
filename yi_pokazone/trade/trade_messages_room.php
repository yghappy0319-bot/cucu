<?php
require_once __DIR__ . '/../lib/_function.php';

$tr_idx_q  = (int) ($_GET['tr_idx'] ?? 0);
$room_idx_q = (int) ($_GET['room_idx'] ?? 0);
$inbox_tab = isset($_GET['tab']) && $_GET['tab'] === 'closed' ? 'closed' : 'active';

$ret_room = '/trade/trade_messages.php?tab=' . $inbox_tab;
if ($tr_idx_q > 0) {
    $ret_room = '/trade/trade_messages_room.php?tr_idx=' . $tr_idx_q . '&tab=' . $inbox_tab;
    if ($room_idx_q > 0) {
        $ret_room .= '&room_idx=' . $room_idx_q;
    }
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($ret_room));
}

$trade_chat_ok = db_table_exists('tb_trade_room') && db_table_exists('tb_trade_room_msg');
$my_mb         = (int) $me['mb_idx'];

if (!$trade_chat_ok) {
    $page = 'trade_messages';
    $title = '거래 대화';
    $meta_description = '거래 메시지 대화를 이어갑니다.';
    $meta_noindex = true;
    $hide_footer_menu = true;
    $meta_breadcrumb = [
        ['name' => '홈', 'url' => '/'],
        ['name' => '거래 메시지함', 'url' => '/trade/trade_messages.php'],
        ['name' => '대화', 'url' => $ret_room],
    ];
    include __DIR__ . '/../include/header.php';
    ?>
<section class="community trade-messages-room-page">
    <div class="container">
        <div class="trade-chat-wrap trade-chat-wrap--disabled trade-messages-disabled">
            <p class="trade-chat-guest-hint">채팅 기능 사용을 위해 DB에 <code>sql/tb_trade_chat.sql</code> 을 적용해 주세요.</p>
            <p class="trade-room-back-wrap"><a href="/trade/trade_messages.php" class="btn btn-outline btn-sm">목록으로</a></p>
        </div>
    </div>
</section>
    <?php
    include __DIR__ . '/../include/footer.php';
    exit;
}

if ($tr_idx_q < 1) {
    alert_goto('대화를 선택해 주세요.', '/trade/trade_messages.php');
}

$page = 'trade_messages';
$title = '거래 대화';
$meta_description = '거래 메시지 대화를 이어갑니다.';
$meta_noindex = true;
$hide_footer_menu = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래 메시지함', 'url' => '/trade/trade_messages.php'],
    ['name' => '대화', 'url' => $ret_room],
];

$active_trade            = null;
$trade_chat_initial_room = 0;
$chat_blocked_reason     = '';
$panel_context_title     = '';

require __DIR__ . '/include/trade_messages_chat_context.php';

if (trade_chat_room_has_read_columns() && $active_trade && $chat_blocked_reason === '' && $trade_chat_initial_room > 0) {
    $is_seller_side = $my_mb === (int) $active_trade['mb_idx'];
    trade_chat_mark_room_read($trade_chat_initial_room, $my_mb, $is_seller_side);
}

$trade_chat_room_archived = false;
if ($trade_chat_ok && $trade_chat_initial_room > 0 && trade_chat_room_archive_columns_ready()) {
    $arch_rw = db_assoc(db_query("
        SELECT r.buyer_mb_idx, r.room_buyer_archived_at, r.room_seller_archived_at, t.mb_idx AS seller_mb_idx
        FROM tb_trade_room r
        INNER JOIN tb_trade t ON t.tr_idx = r.tr_idx
        WHERE r.room_idx = {$trade_chat_initial_room}
        LIMIT 1
    "));
    if ($arch_rw) {
        $trade_chat_room_archived = trade_chat_is_room_archived_for_member($arch_rw, $my_mb);
    }
}

$t_room_redirect_desktop = '/trade/trade_messages.php?tr_idx=' . (int) $tr_idx_q . '&tab=' . $inbox_tab;
if ($room_idx_q > 0) {
    $t_room_redirect_desktop .= '&room_idx=' . (int) $room_idx_q;
}

include __DIR__ . '/../include/header.php';
?>
<script>
(function () {
    try {
        if (!window.matchMedia || !window.matchMedia('(max-width:768px)').matches) {
            location.replace(<?php echo json_encode($t_room_redirect_desktop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    } catch (e) {}
})();
</script>

<section class="community trade-messages-room-page">
    <div class="container">
        <div class="board-head trade-room-board-head">
            <div class="trade-room-board-head-main">
                <p class="trade-room-back-wrap">
                    <a href="/trade/trade_messages.php?tab=<?php echo htmlspecialchars($inbox_tab, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-ghost btn-sm trade-room-back">← 목록</a>
                </p>
                <h1 class="board-title trade-room-title"><?php echo $panel_context_title !== '' ? $panel_context_title : '거래 대화'; ?></h1>
            </div>
            <div class="board-head-actions">
                <?php if ($active_trade && $chat_blocked_reason === ''): ?>
                    <a href="/trade/trade_view.php?idx=<?php echo (int) $active_trade['tr_idx']; ?>" class="btn btn-outline btn-sm">거래글 보기</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($chat_blocked_reason !== ''): ?>
            <div class="trade-inbox-placeholder alert-soft"><?php echo htmlspecialchars($chat_blocked_reason, ENT_QUOTES, 'UTF-8'); ?></div>
            <p class="trade-room-back-wrap trade-room-back-wrap--below"><a href="/trade/trade_messages.php" class="btn btn-outline btn-sm">목록으로</a></p>
        <?php elseif ($active_trade): ?>
            <?php include __DIR__ . '/include/trade_chat_fraud_modal.php'; ?>
            <?php
            $trade_chat_trade = $active_trade;
            $trade_chat_archived = !empty($trade_chat_room_archived);
            include __DIR__ . '/include/trade_chat_panel.php';
            ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
