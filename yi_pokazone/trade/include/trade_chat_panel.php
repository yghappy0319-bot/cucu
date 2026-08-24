<?php
/**
 * 거래 1:1 채팅 패널 (trade_messages.php · trade_messages_room.php · API: trade_chat_api.php)
 *
 * @var array $trade_chat_trade tb_trade 행 (tr_idx, mb_idx 필수)
 * @var array $me login_member 기준 행
 * @var int $trade_chat_initial_room 초기 room_idx (판매자: 메시지함 URL의 room_idx, 구매자: 기존 방)
 */
if (empty($trade_chat_trade) || empty($me)) {
    return;
}

$__tcp_tr = $trade_chat_trade;
$__tcp_is_seller = (int) $me['mb_idx'] === (int) $__tcp_tr['mb_idx'];
$__tcp_room = isset($trade_chat_initial_room) ? (int) $trade_chat_initial_room : 0;
$__tcp_archived = !empty($trade_chat_archived);
$__tcp_product = trade_chat_inquiry_product_label($__tcp_tr);
$__tcp_default_price = max(0, (int) ($__tcp_tr['tr_price'] ?? 0));
$__tcp_bank_label = trade_chat_bank_label();
$__pay_status = trade_chat_payment_status();
$__tcp_payment_db_ready = !empty($__pay_status['db_ready']);
$__tcp_payment_ready = !empty($__pay_status['ready']);
$__tcp_payment_chat_only = !empty($__pay_status['chat_only']);
$__tcp_payment_error = (string) ($__pay_status['error'] ?? '');
$__tcp_trade_payment_lib = __DIR__ . '/../lib/_trade_payment.php';
if (is_readable($__tcp_trade_payment_lib)) {
    require_once $__tcp_trade_payment_lib;
}
$__tcp_platform_fee_percent = function_exists('platform_fee_trade_percent')
    ? platform_fee_trade_percent()
    : (defined('TRADE_PLATFORM_FEE_PERCENT') ? (int) TRADE_PLATFORM_FEE_PERCENT : 5);
$__tcp_auto_confirm_days = defined('TRADE_PURCHASE_AUTO_CONFIRM_DAYS') ? (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS : 3;
$__tcp_chat_api = public_url('/trade/trade_chat_api.php');
$__tcp_chat_js = public_url('/trade/assets/js/trade_chat.js');
$__tcp_chat_js_v = '';
$__tcp_chat_js_path = dirname(__DIR__) . '/assets/js/trade_chat.js';
if (is_readable($__tcp_chat_js_path)) {
    $__tcp_chat_js_v = '?m=' . (string) filemtime($__tcp_chat_js_path);
}
$__cash_ready = false;
$__cash_balance = 0;
if (!$__tcp_is_seller) {
    $__member_cash_lib = __DIR__ . '/../../lib/_member_cash.php';
    if (is_readable($__member_cash_lib)) {
        require_once $__member_cash_lib;
        if (member_cash_column_ready()) {
            $__cash_ready = true;
            $__cash_balance = member_cash_balance((int) $me['mb_idx']);
        }
    }
}
?>
<div class="trade-chat-panel"
     id="trade-chat-root"
     data-api="<?php echo htmlspecialchars($__tcp_chat_api, ENT_QUOTES, 'UTF-8'); ?>"
     data-tr-idx="<?php echo (int) $__tcp_tr['tr_idx']; ?>"
     data-is-seller="<?php echo $__tcp_is_seller ? '1' : '0'; ?>"
     data-bank-label="<?php echo htmlspecialchars($__tcp_bank_label, ENT_QUOTES, 'UTF-8'); ?>"
     data-initial-room="<?php echo (int) $__tcp_room; ?>"
     data-product-label="<?php echo htmlspecialchars($__tcp_product, ENT_QUOTES, 'UTF-8'); ?>"
     data-default-price="<?php echo (int) $__tcp_default_price; ?>"
     data-payment-db-ready="<?php echo $__tcp_payment_db_ready ? '1' : '0'; ?>"
     data-payment-ready="<?php echo $__tcp_payment_ready ? '1' : '0'; ?>"
     data-payment-chat-only="<?php echo $__tcp_payment_chat_only ? '1' : '0'; ?>"
     data-payment-error="<?php echo htmlspecialchars($__tcp_payment_error, ENT_QUOTES, 'UTF-8'); ?>"
     data-cash-ready="<?php echo (!empty($__cash_ready) && !$__tcp_is_seller) ? '1' : '0'; ?>"
     data-cash-balance="<?php echo (!$__tcp_is_seller && !empty($__cash_ready)) ? (int) $__cash_balance : 0; ?>"
     data-platform-fee-percent="<?php echo (int) $__tcp_platform_fee_percent; ?>"
     data-auto-confirm-days="<?php echo (int) $__tcp_auto_confirm_days; ?>"
     data-room-archived="<?php echo $__tcp_archived ? '1' : '0'; ?>">
    <div class="trade-chat-main">
        <?php if ($__tcp_archived): ?>
            <div class="trade-chat-archived-notice alert-soft" role="status">
                종료된 대화입니다. 거래 진행은
                <?php if ($__tcp_is_seller): ?>
                    <a href="/trade/trade_sales.php">판매내역</a>
                <?php else: ?>
                    <a href="/trade/trade_purchases.php">구매내역</a>
                <?php endif; ?>
                에서 확인해 주세요.
                <?php if (!$__tcp_is_seller): ?>
                    같은 거래글에 다시 문의하거나
                    <a href="/trade/trade_messages.php?tr_idx=<?php echo (int) $__tcp_tr['tr_idx']; ?>">새 대화 시작하기</a>를 누르면 새 대화가 열립니다.
                <?php else: ?>
                    구매자가 다시 문의하면 새 대화가 시작됩니다.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="trade-chat-toolbar">
            <button type="button"
                    class="btn btn-outline btn-sm trade-chat-close-room-btn"
                    id="trade-chat-close-room"
                    <?php echo $__tcp_archived ? 'hidden' : ''; ?>
                    disabled>채팅 종료하기</button>
            <button type="button"
                    class="btn btn-outline btn-sm trade-chat-hide-room-btn"
                    id="trade-chat-hide-room"
                    <?php echo $__tcp_archived ? '' : 'hidden'; ?>>삭제하기</button>
        </div>
        <div class="trade-chat-messages" id="trade-chat-messages" tabindex="0"></div>
        <form class="trade-chat-form" id="trade-chat-form" action="/trade/trade_chat_api.php" method="post" enctype="multipart/form-data"<?php echo $__tcp_archived ? ' hidden' : ''; ?>>
            <input type="hidden" name="tr_idx" value="<?php echo (int) $__tcp_tr['tr_idx']; ?>">
            <input type="hidden" name="room_idx" id="trade-chat-room-input" value="<?php echo $__tcp_room > 0 ? (int) $__tcp_room : 0; ?>">
            <label class="sr-only" for="trade-chat-body">메시지</label>
            <textarea id="trade-chat-body" name="body" rows="2" maxlength="2000" placeholder="메시지를 입력하세요… (사진은 선택 즉시 전송, 텍스트 최대 2000자)"></textarea>
            <div class="trade-chat-form-actions">
                <?php if ($__tcp_is_seller): ?>
                    <button type="button" class="btn btn-outline btn-sm trade-chat-pay-request-btn" id="trade-chat-pay-request-open">결제창 보내기</button>
                <?php endif; ?>
                <label class="trade-chat-image-btn btn btn-outline btn-sm" title="사진 첨부">
                    <input type="file" name="msg_image" id="trade-chat-image" accept="image/*">
                    사진
                </label>
                <button type="submit" class="btn btn-primary btn-sm" id="trade-chat-send">보내기</button>
            </div>
        </form>
        <p class="trade-chat-error" id="trade-chat-error" hidden></p>
    </div>
    <div id="trade-chat-img-lightbox"
         class="trade-chat-img-lightbox"
         hidden
         role="dialog"
         aria-modal="true"
         aria-label="첨부 이미지">
        <div class="trade-chat-img-lightbox__backdrop" data-trade-chat-img-lightbox-close></div>
        <button type="button"
                class="trade-chat-img-lightbox__close"
                data-trade-chat-img-lightbox-close
                aria-label="닫기">&times;</button>
        <div class="trade-chat-img-lightbox__frame">
            <img src=""
                 alt="첨부 이미지 전체 보기"
                 class="trade-chat-img-lightbox__img"
                 id="trade-chat-img-lightbox-img"
                 decoding="async">
        </div>
    </div>
</div>
<?php
$__tcp_payment_modals = __DIR__ . '/trade_chat_payment_modals.php';
if (is_readable($__tcp_payment_modals)) {
    include $__tcp_payment_modals;
}
?>
<p class="trade-chat-poll-hint">새 메시지는 잠시 후 자동으로 불러옵니다.</p>
<script src="<?php echo htmlspecialchars($__tcp_chat_js . $__tcp_chat_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function () {
    function bootTradeChat() {
        if (typeof initTradeChat !== 'function') return;
        initTradeChat(document.getElementById('trade-chat-root'));
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTradeChat);
    } else {
        bootTradeChat();
    }
})();
</script>
