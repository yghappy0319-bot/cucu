<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
$ret_inbox = '/trade/trade_messages.php';
$tr_idx_q = (int) ($_GET['tr_idx'] ?? 0);
$room_idx_q = (int) ($_GET['room_idx'] ?? 0);
$inbox_tab = isset($_GET['tab']) && $_GET['tab'] === 'closed' ? 'closed' : 'active';
if ($tr_idx_q > 0) {
    $ret_inbox = '/trade/trade_messages.php?tr_idx=' . $tr_idx_q . ($room_idx_q > 0 ? '&room_idx=' . $room_idx_q : '');
    if ($inbox_tab === 'closed') {
        $ret_inbox .= '&tab=closed';
    }
}
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($ret_inbox));
}

$trade_chat_ok = db_table_exists('tb_trade_room') && db_table_exists('tb_trade_room_msg');
$my_mb = (int) $me['mb_idx'];
$trade_inbox_thumb_sql = db_table_exists('tb_trade_image')
    ? ', (SELECT i.ti_path FROM tb_trade_image i WHERE i.tr_idx = t.tr_idx ORDER BY i.ti_order ASC, i.ti_idx ASC LIMIT 1) AS thumb_path'
    : ', NULL AS thumb_path';

$page = 'trade_messages';
$title = '거래 메시지함';
$meta_description = '거래글 문의를 메시지함에서 확인하고 답변하세요.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래 메시지함', 'url' => '/trade/trade_messages.php'],
];


$active_trade = null;
$trade_chat_initial_room = 0;
$chat_blocked_reason = '';
$panel_context_title = '';

require __DIR__ . '/include/trade_messages_chat_context.php';

$inbox = $trade_chat_ok ? trade_messages_inbox_list($my_mb, $trade_inbox_thumb_sql, $inbox_tab) : [];
$inbox_active_count = $trade_chat_ok ? count(trade_messages_inbox_list($my_mb, $trade_inbox_thumb_sql, 'active')) : 0;
$inbox_closed_count = $trade_chat_ok ? count(trade_messages_inbox_list($my_mb, $trade_inbox_thumb_sql, 'closed')) : 0;
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

if ($trade_chat_ok && trade_chat_room_has_read_columns() && $active_trade && $chat_blocked_reason === '' && $trade_chat_initial_room > 0) {
    $is_seller_side = $my_mb === (int) $active_trade['mb_idx'];
    trade_chat_mark_room_read($trade_chat_initial_room, $my_mb, $is_seller_side);
}

$trade_messages_mobile_deep_link = $trade_chat_ok && $tr_idx_q > 0 && $active_trade && $chat_blocked_reason === '' && $trade_chat_initial_room > 0;

include __DIR__ . '/../include/header.php';

if (!empty($trade_messages_mobile_deep_link)):
    $mz_room = '/trade/trade_messages_room.php?tr_idx=' . (int) $tr_idx_q;
    if ($trade_chat_initial_room > 0) {
        $mz_room .= '&room_idx=' . (int) $trade_chat_initial_room;
    }
    if ($inbox_tab === 'closed') {
        $mz_room .= '&tab=closed';
    }
    ?>
<script>
(function () {
    try {
        if (!window.matchMedia || !window.matchMedia('(max-width:768px)').matches) return;
        location.replace(<?php echo json_encode($mz_room, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    } catch (e) {}
})();
</script>
<?php endif; ?>

<section class="community trade-messages-page">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">거래 메시지함</h1>
            </div>
            <div class="board-head-actions">
                <a href="/trade/trade.php" class="btn btn-outline btn-sm">거래게시판</a>
            </div>
        </div>

        <?php if (!$trade_chat_ok): ?>
            <div class="trade-chat-wrap trade-chat-wrap--disabled trade-messages-disabled">
                <p class="trade-chat-guest-hint">채팅 기능 사용을 위해 DB에 <code>sql/tb_trade_chat.sql</code> 을 적용해 주세요.</p>
            </div>
        <?php else: ?>
            <?php
            $inbox_active_tr = ($active_trade && $chat_blocked_reason === '') ? (int) $active_trade['tr_idx'] : 0;
            $inbox_active_room = $trade_chat_initial_room;
            ?>
            <div class="trade-inbox-layout">
                <?php include __DIR__ . '/include/trade_chat_fraud_modal.php'; ?>
                <aside class="trade-inbox-sidebar" aria-label="대화 목록">
                    <nav class="trade-inbox-tabs" aria-label="대화 목록 구분">
                        <a href="/trade/trade_messages.php?tab=active"
                           class="trade-inbox-tab<?php echo $inbox_tab === 'active' ? ' is-active' : ''; ?>">
                            대화중인 채팅
                            <?php if ($inbox_active_count > 0): ?>
                                <span class="trade-inbox-tab-count"><?php echo number_format($inbox_active_count); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/trade/trade_messages.php?tab=closed"
                           class="trade-inbox-tab<?php echo $inbox_tab === 'closed' ? ' is-active' : ''; ?>">
                            대화종료 채팅
                            <?php if ($inbox_closed_count > 0): ?>
                                <span class="trade-inbox-tab-count"><?php echo number_format($inbox_closed_count); ?></span>
                            <?php endif; ?>
                        </a>
                    </nav>
                    <?php if ($tr_idx_q > 0 && $chat_blocked_reason !== ''): ?>
                        <p class="trade-inbox-mobile-alert alert-soft"><?php echo htmlspecialchars($chat_blocked_reason, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if (empty($inbox)): ?>
                        <p class="trade-inbox-empty">
                            <?php if ($inbox_tab === 'closed'): ?>
                                종료한 대화가 없습니다.<br>
                                진행 중인 대화는 <a href="/trade/trade_messages.php?tab=active">대화중인 채팅</a>에서 확인할 수 있습니다.
                            <?php else: ?>
                                아직 대화가 없습니다.<br>
                                <a href="/trade/trade.php">거래게시판</a>에서 관심 있는 글의 <strong>거래 문의</strong>를 눌러 시작해 보세요.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <ul class="trade-inbox-list">
                            <?php foreach ($inbox as $it):
                                $href_desktop = '/trade/trade_messages.php?tr_idx=' . (int) $it['tr_idx'];
                                $href_mobile  = '/trade/trade_messages_room.php?tr_idx=' . (int) $it['tr_idx'];
                                $href_desktop .= '&room_idx=' . (int) $it['room_idx'];
                                $href_mobile .= '&room_idx=' . (int) $it['room_idx'];
                                if ($inbox_tab === 'closed') {
                                    $href_desktop .= '&tab=closed';
                                    $href_mobile .= '&tab=closed';
                                }
                                $peer = htmlspecialchars($it['peer_nick'] !== '' ? $it['peer_nick'] : ($it['inbox_role'] === 'seller' ? '회원#' . $it['room_idx'] : '판매자'), ENT_QUOTES, 'UTF-8');
                                $tl = htmlspecialchars((string) $it['tr_title'], ENT_QUOTES, 'UTF-8');
                                $dt = date('m/d H:i', strtotime($it['updated_at']));
                                $is_active = $inbox_active_tr > 0
                                    && (int) $it['tr_idx'] === $inbox_active_tr
                                    && $inbox_active_room > 0
                                    && (int) $it['room_idx'] === $inbox_active_room;
                                $thumb_raw = trim((string) ($it['thumb_path'] ?? ''));
                                $thumb_url = $thumb_raw !== '' ? public_url($thumb_raw) : '';
                                $active_cls = $is_active ? ' is-active' : '';
                                ob_start();
                                ?>
                                        <span class="trade-inbox-item-thumb-wrap">
                                            <?php if ($thumb_url !== ''): ?>
                                                <img src="<?php echo htmlspecialchars($thumb_url, ENT_QUOTES, 'UTF-8'); ?>"
                                                     class="trade-inbox-item-thumb"
                                                     alt=""
                                                     width="48"
                                                     height="48"
                                                     loading="lazy"
                                                     decoding="async">
                                            <?php else: ?>
                                                <span class="trade-inbox-item-thumb trade-inbox-item-thumb--empty" aria-hidden="true"></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="trade-inbox-item-main">
                                            <span class="trade-inbox-item-title" title="<?php echo $tl; ?>"><?php echo $tl; ?></span>
                                            <span class="trade-inbox-item-peer"><?php echo $it['inbox_role'] === 'buyer' ? '판매자 · ' : '구매자 · '; ?><?php echo $peer; ?></span>
                                            <time class="trade-inbox-item-time" datetime="<?php echo htmlspecialchars($it['updated_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dt, ENT_QUOTES, 'UTF-8'); ?></time>
                                        </span>
                                <?php
                                $inbox_item_inner = ob_get_clean();
                                ?>
                                <li class="trade-inbox-duo">
                                    <div class="trade-inbox-duo-main">
                                        <a href="<?php echo htmlspecialchars($href_desktop, ENT_QUOTES, 'UTF-8'); ?>"
                                           class="trade-inbox-item trade-inbox-item--desktop<?php echo $active_cls; ?>">
                                            <?php echo $inbox_item_inner; ?>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($href_mobile, ENT_QUOTES, 'UTF-8'); ?>"
                                           class="trade-inbox-item trade-inbox-item--mobile<?php echo $active_cls; ?>">
                                            <?php echo $inbox_item_inner; ?>
                                        </a>
                                        <?php if ($inbox_tab === 'closed'): ?>
                                            <form class="trade-inbox-hide" action="/trade/proc/trade_chat_hide_proc.php" method="post"
                                                  onsubmit="return confirm('목록에서 삭제할까요?\n대화 기록은 보관되며, 상대방 화면에는 그대로 남습니다.');">
                                                <input type="hidden" name="tr_idx" value="<?php echo (int) $it['tr_idx']; ?>">
                                                <input type="hidden" name="room_idx" value="<?php echo (int) $it['room_idx']; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm">삭제</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </aside>

                <div class="trade-inbox-main">
                    <?php if ($chat_blocked_reason !== ''): ?>
                        <div class="trade-inbox-placeholder alert-soft"><?php echo htmlspecialchars($chat_blocked_reason, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif ($active_trade): ?>
                        <div class="trade-inbox-thread-head">
                            <h2 class="trade-inbox-thread-title"><?php echo $panel_context_title; ?></h2>
                            <a href="/trade/trade_view.php?idx=<?php echo (int) $active_trade['tr_idx']; ?>" class="btn btn-outline btn-sm">거래글 보기</a>
                        </div>
                        <?php
                        $trade_chat_trade = $active_trade;
                        $trade_chat_archived = !empty($trade_chat_room_archived);
                        include __DIR__ . '/include/trade_chat_panel.php';
                        ?>
                    <?php else: ?>
                        <div class="trade-inbox-placeholder">
                            <?php if (empty($inbox)): ?>
                                <p>왼쪽 목록이 비어 있을 때는 거래 상세 페이지에서 <strong>거래 문의</strong>로 들어오면 이곳에서 대화할 수 있습니다.</p>
                            <?php else: ?>
                                <p>왼쪽에서 대화를 선택하거나 거래글에서 <strong>거래 문의</strong>를 눌러 주세요.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
