<?php
require_once __DIR__ . '/../lib/_function.php';

if (!trade_chat_payment_lib_load() || !function_exists('trade_checkout_validate_trade')) {
    alert_goto(
        '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 최신 파일을 서버에 업로드해 주세요.',
        '/trade/trade.php'
    );
}

require_once __DIR__ . '/../lib/_member_address.php';
require_once __DIR__ . '/../lib/_member_cash.php';
require_once __DIR__ . '/../lib/_coupon.php';

$tr_idx = (int) ($_GET['tr_idx'] ?? 0);
if ($tr_idx < 1) {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$return_checkout = '/trade/trade_checkout.php?tr_idx=' . $tr_idx;

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($return_checkout));
}

$rs = db_query("
    SELECT t.*, m.mb_nick AS seller_nick
    FROM tb_trade t
    LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
    WHERE t.tr_idx = {$tr_idx}
    LIMIT 1
");
$trade = db_assoc($rs);
if (!$trade) {
    alert_goto('존재하지 않는 거래글입니다.', '/trade/trade.php');
}

$mb_idx = (int) $me['mb_idx'];
$is_seller_view = $mb_idx === (int) ($trade['mb_idx'] ?? 0);

if (!$is_seller_view) {
    $valid = trade_checkout_validate_trade($trade, $mb_idx);
    if (!$valid['ok']) {
        alert_goto((string) ($valid['error'] ?? '바로구매할 수 없습니다.'), '/trade/trade_view.php?idx=' . $tr_idx);
    }
}

$goods_amount   = trade_checkout_goods_amount($trade);
$shipping_fee   = trade_checkout_shipping_fee($trade);
$pay_amount     = trade_checkout_total_amount($trade);
$state      = $is_seller_view
    ? trade_checkout_load_state_for_seller($tr_idx, $mb_idx, (int) ($_GET['pay_idx'] ?? 0))
    : trade_checkout_load_state($tr_idx, $mb_idx, 0);
$pay_idx         = (int) ($state['pay_idx'] ?? 0);
$request_msg_idx = (int) ($state['request_msg_idx'] ?? 0);
$room_idx        = (int) ($state['room_idx'] ?? 0);
$payment         = is_array($state['payment'] ?? null) ? $state['payment'] : null;
if ($payment && (int) ($payment['amount'] ?? 0) > 0) {
    $pay_amount = (int) $payment['amount'];
}
$is_box       = ($trade['tr_item_type'] ?? '') === 'box';
$needs_addr   = trade_checkout_needs_address($trade);
$addresses    = $needs_addr && member_address_table_ready() ? member_address_list($mb_idx) : [];
$addr_table_ready    = member_address_table_ready();
$addr_message_ready  = member_address_message_column_ready();
$default_phone       = (string) ($me['mb_phone'] ?? '');
$member_name         = (string) ($me['mb_name'] ?? '');
$cash_ready   = member_cash_column_ready();
$cash_bal     = $cash_ready ? member_cash_balance($mb_idx) : 0;
$bank         = trade_payment_bank_info();
$done         = isset($_GET['done']) && (string) $_GET['done'] === '1';
$submitted    = isset($_GET['submitted']) && (string) $_GET['submitted'] === '1';

$order_paid      = $payment && !empty($payment['is_confirmed']);
$order_submitted = $payment && !$order_paid && (int) ($payment['status'] ?? 0) >= TRADE_PAYMENT_STATUS_SUBMITTED;
$can_pay_form    = !$is_seller_view && !$order_paid && !$order_submitted;
$can_cancel_buynow = !$is_seller_view && $pay_idx > 0
    && function_exists('trade_checkout_can_cancel_buy_now')
    && trade_checkout_can_cancel_buy_now($pay_idx, $mb_idx);

if ($is_seller_view && $order_paid && $pay_idx > 0) {
    $ship_go = function_exists('trade_payment_ship_url')
        ? trade_payment_ship_url($pay_idx)
        : '';
    if ($ship_go === '') {
        $ship_go = '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;
    }
    header('Location: ' . $ship_go, true, 302);
    exit;
}

$checkout_buyer_nick = '';
if ($is_seller_view && $pay_idx > 0) {
    $pay_row_buyer = trade_payment_row($pay_idx);
    $buyer_mb_view = $pay_row_buyer ? (int) ($pay_row_buyer['buyer_mb_idx'] ?? 0) : 0;
    if ($buyer_mb_view > 0) {
        $checkout_buyer_nick = trim((string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . $buyer_mb_view . ' LIMIT 1'));
    }
}

$thumb = '';
$rs_img = db_query("SELECT ti_path FROM tb_trade_image WHERE tr_idx = {$tr_idx} ORDER BY ti_order ASC, ti_idx ASC LIMIT 1");
$img_row = db_assoc($rs_img);
if ($img_row) {
    $thumb = (string) ($img_row['ti_path'] ?? '');
}

$methods = [
    'direct'   => '직거래',
    'delivery' => '택배',
    'both'     => '직거래 / 택배',
];
$product_label = trade_chat_inquiry_product_label($trade);
$checkout_done_url  = $return_checkout . '&done=1';
$checkout_bank_proc = '/trade/proc/trade_checkout_bank_proc.php';
$checkout_status_url = '/trade/trade_checkout_status.php';
$checkout_js_v       = '';
$__checkout_js_path  = __DIR__ . '/assets/js/trade_checkout.js';
if (is_file($__checkout_js_path)) {
    $checkout_js_v = '?v=' . rawurlencode((string) filemtime($__checkout_js_path));
}
$method_lbl = $methods[$trade['tr_method'] ?? ''] ?? '직거래 / 택배';
$coupon_ready   = member_coupon_table_ready() && coupon_table_ready();
$available_coupons = ($can_pay_form && $coupon_ready)
    ? coupon_list_available_for_member($mb_idx, $goods_amount)
    : [];

$page  = 'trade_checkout';
$title = ($is_seller_view ? '입금 상태 · ' : '바로구매 · ') . (string) $trade['tr_title'];
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래게시판', 'url' => '/trade/trade.php'],
    ['name' => $is_seller_view ? '입금 상태' : '바로구매', 'url' => $return_checkout],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage auction-order trade-checkout">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_seller_view ? '입금 상태' : '바로구매'; ?></h1>
                <p class="board-desc"><?php echo $is_seller_view
                    ? '입금대기인지, 입금확인인지 상태만 확인할 수 있습니다. 입금이 확인되면 주문 상세로 이동합니다.'
                    : '배송지와 결제 수단을 확인한 뒤 주문을 완료해 주세요.'; ?></p>
            </div>
            <div class="mypage-head-actions">
                <a href="/trade/trade_view.php?idx=<?php echo $tr_idx; ?>" class="btn btn-outline btn-sm">거래 상세</a>
            </div>
        </div>

        <?php if ($order_paid || $done): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                결제가 완료되었습니다.
                <?php if ($pay_idx > 0): ?>
                    주문번호 <strong>#<?php echo $pay_idx; ?></strong>
                <?php endif; ?>
            </div>
        <?php elseif ($is_seller_view && ($order_submitted || $submitted)): ?>
            <div class="auction-order-alert auction-order-alert--wait" role="status">
                현재 상태: <strong>입금대기</strong>
            </div>
        <?php elseif ($order_submitted || $submitted): ?>
            <div class="auction-order-alert auction-order-alert--wait" role="status">
                입금 신청이 접수되었습니다.
                입금하신 금액은 <strong>포카존에 예치</strong>되며, <strong>구매확정 시 판매자에게 지급</strong>됩니다.
                <strong>입금할 금액</strong>과 <strong>입금자명</strong>이 신청 내용과 <strong>정확히 일치</strong>해야 확인됩니다.
            </div>
        <?php elseif ($is_seller_view): ?>
            <div class="auction-order-alert">
                아직 이 글에 대한 입금 신청이 없습니다.
            </div>
        <?php else: ?>
            <div class="auction-order-alert">
                희망가 <strong>₩<?php echo number_format($pay_amount); ?></strong>으로 바로 주문합니다.
                결제 완료 후 판매자가 발송을 진행합니다.
            </div>
        <?php endif; ?>

        <div class="auction-order-layout">
            <div class="mypage-panel auction-order-product">
                <h2 class="mypage-panel-title">주문 상품</h2>
                <div class="auction-order-product-inner">
                    <?php if ($thumb !== ''): ?>
                        <img src="<?php echo htmlspecialchars(public_url($thumb)); ?>"
                             alt="<?php echo htmlspecialchars((string) $trade['tr_card_name']); ?>"
                             class="auction-order-product-thumb" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="auction-order-product-thumb auction-order-product-thumb--empty" aria-hidden="true">
                            <?php echo $is_box ? '📦' : '🃏'; ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="auction-order-product-title"><?php echo htmlspecialchars((string) $trade['tr_title']); ?></p>
                        <p class="auction-order-product-sub"><?php echo htmlspecialchars((string) $trade['tr_card_name']); ?></p>
                        <ul class="auction-order-product-meta">
                            <?php if ($is_seller_view): ?>
                                <li>구매자 <?php echo htmlspecialchars($checkout_buyer_nick !== '' ? $checkout_buyer_nick : '(구매자)'); ?></li>
                            <?php else: ?>
                                <li>판매자 <?php echo htmlspecialchars((string) ($trade['seller_nick'] ?? '(탈퇴)')); ?></li>
                            <?php endif; ?>
                            <li>거래 방식 <?php echo htmlspecialchars($method_lbl); ?></li>
                            <?php if (!$is_seller_view && $payment && trim((string) ($payment['depositor_name'] ?? '')) !== ''): ?>
                                <li>입금자명 <?php echo htmlspecialchars((string) $payment['depositor_name']); ?></li>
                            <?php endif; ?>
                        </ul>
                        <p class="auction-order-product-price">결제 금액 <strong>₩<?php echo number_format($pay_amount); ?></strong></p>
                    </div>
                </div>
            </div>

            <?php if ($order_paid || $done): ?>
                <div class="mypage-panel auction-order-done-panel">
                    <h2 class="mypage-panel-title">주문 정보</h2>
                    <dl class="mypage-dl">
                        <div>
                            <dt>결제 금액</dt>
                            <dd>₩<?php echo number_format($pay_amount); ?></dd>
                        </div>
                        <?php if ($payment && !empty($payment['depositor_name'])): ?>
                        <div>
                            <dt>결제 수단</dt>
                            <dd><?php echo htmlspecialchars((string) $payment['depositor_name']) === '캐시결제' ? '캐시 결제' : '무통장 입금'; ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <div class="trade-checkout-done-actions">
                        <?php if ($pay_idx > 0): ?>
                            <a href="/trade/trade_payment_ship.php?pay_idx=<?php echo $pay_idx; ?>" class="btn btn-primary btn-block">
                                <?php echo $is_seller_view ? '배송·발송 진행' : '주문·배송 확인'; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($room_idx > 0): ?>
                            <a href="/trade/trade_messages.php?room_idx=<?php echo $room_idx; ?>" class="btn btn-outline btn-block">거래 메시지함</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($is_seller_view && ($order_submitted || $submitted)): ?>
                <div class="mypage-panel auction-order-done-panel">
                    <h2 class="mypage-panel-title">입금 상태</h2>
                    <p class="trade-checkout-seller-status" role="status">
                        <strong>입금대기</strong>
                    </p>
                    <p class="auction-order-tracking-wait">
                        구매자 입금이 확인되면 <strong>입금확인</strong>으로 바뀌고, 주문 상세(배송) 페이지로 이동합니다.
                    </p>
                    <?php if ($room_idx > 0): ?>
                        <a href="/trade/trade_messages.php?room_idx=<?php echo $room_idx; ?>" class="btn btn-outline btn-block">거래 메시지함</a>
                    <?php endif; ?>
                </div>
            <?php elseif ($order_submitted || $submitted): ?>
                <div class="mypage-panel auction-order-done-panel">
                    <h2 class="mypage-panel-title">입금 대기</h2>
                    <p class="auction-order-tracking-wait">
                        입금 신청이 접수되었습니다. 입금 확인 창에서 계좌 정보를 확인해 주세요.
                        입금하신 금액은 <strong>포카존에 예치</strong>되며, <strong>구매확정 시 판매자에게 지급</strong>됩니다.
                        <strong>입금할 금액</strong>과 <strong>입금자명</strong>이 신청 내용과 <strong>정확히 일치</strong>해야 확인됩니다.
                    </p>
                    <button type="button" class="btn btn-primary btn-block" id="trade-checkout-reopen-bank">
                        입금 확인 창 열기
                    </button>
                    <?php if ($room_idx > 0): ?>
                        <a href="/trade/trade_messages.php?room_idx=<?php echo $room_idx; ?>" class="btn btn-outline btn-block" style="margin-top:8px;">거래 메시지함</a>
                    <?php endif; ?>
                </div>
                <?php include __DIR__ . '/include/trade_checkout_cancel_panel.php'; ?>
            <?php elseif ($is_seller_view): ?>
                <div class="mypage-panel auction-order-done-panel">
                    <h2 class="mypage-panel-title">입금 신청 없음</h2>
                    <p class="auction-order-tracking-wait">
                        아직 이 글에 바로구매 입금 신청이 없습니다.
                        구매자가 무통장 입금을 신청하면 이 페이지와 거래 메시지함에서 입금 확인 상태를 볼 수 있습니다.
                    </p>
                    <a href="/trade/trade_messages.php?tr_idx=<?php echo $tr_idx; ?>" class="btn btn-primary btn-block">거래 메시지함</a>
                </div>
            <?php else: ?>
                <form class="mypage-panel auction-order-form trade-checkout-form" method="post" action="/trade/proc/trade_checkout_proc.php" id="trade-checkout-form">
                    <input type="hidden" name="tr_idx" value="<?php echo $tr_idx; ?>">

                    <?php if ($needs_addr): ?>
                        <h2 class="mypage-panel-title">배송지</h2>
                        <?php if (!$addr_table_ready): ?>
                            <p class="auction-order-empty-addr">
                                배송지 기능을 사용할 수 없습니다. <code>sql/tb_member_address.sql</code> 을 적용해 주세요.
                            </p>
                        <?php else: ?>
                            <ul id="trade-checkout-addr-list" class="auction-order-addr-list" aria-live="polite">
                                <?php foreach ($addresses as $addr):
                                    $addr_idx = (int) $addr['addr_idx'];
                                    $full     = trim(
                                        '[' . $addr['addr_zip'] . '] ' . $addr['addr_road']
                                        . ($addr['addr_extra'] !== '' ? ' ' . $addr['addr_extra'] : '')
                                        . ($addr['addr_detail'] !== '' ? ', ' . $addr['addr_detail'] : '')
                                    );
                                    ?>
                                    <li class="auction-order-addr-row-wrap" data-addr-idx="<?php echo $addr_idx; ?>">
                                        <label class="auction-order-addr-item">
                                            <input type="radio" name="addr_idx" value="<?php echo $addr_idx; ?>"
                                                   data-addr-message="<?php echo htmlspecialchars((string) ($addr['addr_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                   <?php echo (int) ($addr['addr_is_default'] ?? 0) === 1 ? 'checked' : ''; ?> required>
                                            <span class="auction-order-addr-body">
                                                <span class="auction-order-addr-label">
                                                    <?php echo htmlspecialchars((string) $addr['addr_label']); ?>
                                                    <?php if ((int) ($addr['addr_is_default'] ?? 0) === 1): ?>
                                                        <em>기본</em>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="auction-order-addr-line"><?php echo htmlspecialchars($full); ?></span>
                                                <span class="auction-order-addr-contact">
                                                    <?php echo htmlspecialchars((string) $addr['addr_name']); ?>
                                                    · <?php echo htmlspecialchars((string) $addr['addr_phone']); ?>
                                                </span>
                                            </span>
                                        </label>
                                        <div class="auction-order-addr-actions">
                                            <button type="button" class="btn btn-outline btn-sm" data-member-address-edit="<?php echo $addr_idx; ?>">수정</button>
                                            <button type="button" class="btn btn-sm member-address-delete-btn" data-member-address-delete="<?php echo $addr_idx; ?>">삭제</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($addresses)): ?>
                                    <li class="auction-order-addr-empty-item">
                                        <p class="auction-order-empty-addr">등록된 배송지가 없습니다. 아래 <strong>배송지 추가</strong>를 눌러 등록해 주세요.</p>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <p class="auction-order-addr-manage">
                                <button type="button" class="btn btn-outline btn-sm" data-member-address-add>배송지 추가</button>
                            </p>
                            <div class="field auction-order-message-field">
                                <label for="addr_message">배송 메시지</label>
                                <textarea id="addr_message" name="addr_message" rows="3"
                                          maxlength="<?php echo (int) MEMBER_ADDRESS_MESSAGE_MAX; ?>"
                                          placeholder="부재 시 문 앞에 놓아주세요, 배송 전 연락 부탁드립니다 등"></textarea>
                                <p class="field-hint">선택 입력 · 이 주문에만 적용됩니다</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="auction-order-tracking-wait">이 상품은 직거래 방식입니다. 배송지 입력 없이 결제를 진행합니다.</p>
                    <?php endif; ?>

                    <h2 class="mypage-panel-title auction-order-pay-title">결제</h2>
                    <p class="auction-order-pay-amount">
                        상품 금액 <strong id="trade-checkout-goods-amount">₩<?php echo number_format($goods_amount); ?></strong>
                    </p>
                    <?php if ($shipping_fee > 0): ?>
                        <p class="auction-order-pay-amount">
                            택배비 <strong id="trade-checkout-shipping-amount">₩<?php echo number_format($shipping_fee); ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="auction-order-pay-amount auction-order-pay-amount--muted">
                            택배비 <strong>판매자 부담</strong>
                            <?php if (($trade['tr_method'] ?? '') === 'direct'): ?>
                                <small>(직거래)</small>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <p class="auction-order-pay-amount" hidden>
                        <strong id="trade-checkout-original-amount">₩<?php echo number_format($pay_amount); ?></strong>
                    </p>

                    <?php if ($coupon_ready): ?>
                        <div class="field trade-checkout-coupon-field">
                            <label for="member_coupon_idx">쿠폰</label>
                            <select id="member_coupon_idx" name="member_coupon_idx">
                                <option value="0" data-discount="0" data-pay="<?php echo (int) $goods_amount; ?>">쿠폰 사용 안 함</option>
                                <?php foreach ($available_coupons as $cp): ?>
                                    <option value="<?php echo (int) ($cp['mc_idx'] ?? 0); ?>"
                                            data-discount="<?php echo (int) ($cp['preview_discount'] ?? 0); ?>"
                                            data-pay="<?php echo (int) ($cp['preview_pay_amount'] ?? 0); ?>">
                                        <?php echo htmlspecialchars((string) ($cp['mc_name'] ?? '쿠폰'), ENT_QUOTES, 'UTF-8'); ?>
                                        (<?php echo htmlspecialchars((string) ($cp['discount_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        · ~<?php echo htmlspecialchars((string) ($cp['mc_valid_until'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($available_coupons)): ?>
                                <p class="field-hint">사용 가능한 쿠폰이 없습니다. <a href="/page/mypage_coupons.php">내 쿠폰</a>에서 확인할 수 있습니다.</p>
                            <?php else: ?>
                                <p class="field-hint">쿠폰은 상품 금액에만 적용됩니다. <a href="/page/mypage_coupons.php">내 쿠폰</a></p>
                            <?php endif; ?>
                        </div>
                        <p class="auction-order-pay-discount" id="trade-checkout-discount-row" hidden>
                            쿠폰 할인 <strong id="trade-checkout-discount-amount">-₩0</strong>
                        </p>
                    <?php endif; ?>

                    <p class="auction-order-pay-amount auction-order-pay-amount--final">
                        최종 결제 금액 <strong id="trade-checkout-final-amount">₩<?php echo number_format($pay_amount); ?></strong>
                    </p>

                    <div class="auction-order-pay-methods">
                        <label class="auction-order-pay-opt">
                            <input type="radio" name="pay_method" value="cash" checked data-pay-method="cash">
                            <span>
                                <strong>캐시 결제</strong>
                                <?php if ($cash_ready): ?>
                                    <small>보유 ₩<?php echo number_format($cash_bal); ?></small>
                                <?php else: ?>
                                    <small class="is-warn">mb_cash 컬럼 필요</small>
                                <?php endif; ?>
                            </span>
                        </label>
                        <label class="auction-order-pay-opt">
                            <input type="radio" name="pay_method" value="bank" data-pay-method="bank">
                            <span>
                                <strong>무통장 입금</strong>
                                <small><?php echo htmlspecialchars((string) ($bank['label'] ?? '')); ?></small>
                            </span>
                        </label>
                    </div>

                    <div id="trade-checkout-bank-notice" class="trade-pay-checkout-notice" hidden role="note">
                        <?php include __DIR__ . '/include/trade_bank_deposit_notice.php'; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="trade-checkout-submit-btn">
                        <span id="trade-checkout-submit-label">₩<?php echo number_format($pay_amount); ?> 결제하기</span>
                    </button>
                </form>
                <?php include __DIR__ . '/include/trade_checkout_cancel_panel.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($can_pay_form && $needs_addr && $addr_table_ready): ?>
    <?php include __DIR__ . '/../include/member_address_modal.php'; ?>
<?php
$__addr_js = dirname(__DIR__) . '/assets/js/member_address.js';
$__addr_v  = is_readable($__addr_js) ? '?m=' . (string) filemtime($__addr_js) : '';
?>
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
window.__MEMBER_ADDRESS__ = {
    apiUrl: '/page/member_address_api.php',
    mode: 'checkout',
    defaultPhone: <?php echo json_encode($default_phone, JSON_UNESCAPED_UNICODE); ?>,
    memberName: <?php echo json_encode($member_name, JSON_UNESCAPED_UNICODE); ?>,
    messageReady: <?php echo $addr_message_ready ? 'true' : 'false'; ?>,
    messageMax: <?php echo (int) MEMBER_ADDRESS_MESSAGE_MAX; ?>
};
</script>
<script src="/assets/js/member_address.js<?php echo htmlspecialchars($__addr_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<?php
$checkout_show_bank_ui = !$is_seller_view && ($can_pay_form || $order_submitted || $submitted);
if ($checkout_show_bank_ui):
    include __DIR__ . '/include/trade_checkout_bank_modal.php';
?>
<script>
window.__tradeCheckout = <?php echo json_encode([
    'tr_idx'         => $tr_idx,
    'pay_idx'        => $pay_idx,
    'amount'         => $pay_amount,
    'original_amount'=> $pay_amount,
    'goods_amount'   => $goods_amount,
    'shipping_fee'   => $shipping_fee,
    'product_label'  => $product_label,
    'bank'           => $bank,
    'bank_proc_url'  => $checkout_bank_proc,
    'status_url'     => $checkout_status_url,
    'checkout_url'   => $return_checkout,
    'done_url'       => $checkout_done_url,
    'auto_pending'   => ($order_submitted || $submitted) && !$order_paid,
    'is_seller'      => $is_seller_view,
    'payment'        => $payment,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/trade/assets/js/trade_checkout.js<?php echo htmlspecialchars($checkout_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<?php if ($is_seller_view && ($order_submitted || $submitted) && !$order_paid && $pay_idx > 0): ?>
<script>
(function () {
    var statusUrl = <?php echo json_encode($checkout_status_url, JSON_UNESCAPED_UNICODE); ?>;
    var trIdx = <?php echo (int) $tr_idx; ?>;
    var payIdx = <?php echo (int) $pay_idx; ?>;
    var shipUrl = <?php echo json_encode(trade_payment_ship_url($pay_idx), JSON_UNESCAPED_UNICODE); ?>;
    var going = false;
    function poll() {
        if (going || !statusUrl || !payIdx) return;
        fetch(statusUrl + '?tr_idx=' + encodeURIComponent(String(trIdx))
            + '&pay_idx=' + encodeURIComponent(String(payIdx))
            + '&_=' + String(Date.now()), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.ok || !j.payment) return;
                var st = parseInt(j.payment.status, 10) || 0;
                if (st >= 2 || j.payment.is_confirmed) {
                    going = true;
                    window.location.href = shipUrl || ('/trade/trade_payment_ship.php?pay_idx=' + payIdx);
                }
            })
            .catch(function () {});
    }
    poll();
    setInterval(poll, 3000);
})();
</script>
<?php endif; ?>

<?php if ($can_pay_form): ?>
<script>
(function () {
    var form = document.getElementById('trade-checkout-form');
    if (!form) return;

    var needsAddr = <?php echo $needs_addr ? 'true' : 'false'; ?>;
    var addrManageReady = <?php echo ($needs_addr && $addr_table_ready) ? 'true' : 'false'; ?>;

    function countAddresses() {
        return form.querySelectorAll('input[name="addr_idx"]').length;
    }

    form.addEventListener('submit', function (e) {
        if (!needsAddr) return;
        if (countAddresses() < 1) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (addrManageReady && window.confirm('배송지가 등록되어 있지 않습니다.\n\n지금 배송지를 등록할까요?')) {
                if (window.MemberAddressCheckout && typeof window.MemberAddressCheckout.openAddModal === 'function') {
                    window.MemberAddressCheckout.openAddModal();
                } else {
                    var addBtn = document.querySelector('[data-member-address-add]');
                    if (addBtn) addBtn.click();
                }
            }
            return;
        }
        if (!form.querySelector('input[name="addr_idx"]:checked')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            alert('배송지를 선택해 주세요.');
        }
    }, true);

    var msgInput = document.getElementById('addr_message');
    var bankNotice = document.getElementById('trade-checkout-bank-notice');
    var payMethods = form.querySelectorAll('input[name="pay_method"]');

    function syncMessageFromAddress() {
        if (!msgInput) return;
        var checked = form.querySelector('input[name="addr_idx"]:checked');
        if (!checked) return;
        msgInput.value = checked.getAttribute('data-addr-message') || '';
    }

    function bindAddrRadios() {
        syncMessageFromAddress();
    }

    function syncBankNotice() {
        if (!bankNotice) return;
        var checked = form.querySelector('input[name="pay_method"]:checked');
        bankNotice.hidden = !checked || checked.value !== 'bank';
    }

    form.addEventListener('change', function (e) {
        if (e.target && e.target.matches && e.target.matches('input[name="addr_idx"]')) {
            syncMessageFromAddress();
        }
    });

    document.addEventListener('member-address:list-updated', bindAddrRadios);

    bindAddrRadios();
    payMethods.forEach(function (el) {
        el.addEventListener('change', syncBankNotice);
    });
    syncMessageFromAddress();
    syncBankNotice();
})();

(function () {
    var form = document.getElementById('trade-checkout-form');
    var couponSelect = document.getElementById('member_coupon_idx');
    var discountRow = document.getElementById('trade-checkout-discount-row');
    var discountAmountEl = document.getElementById('trade-checkout-discount-amount');
    var finalAmountEl = document.getElementById('trade-checkout-final-amount');
    var submitLabel = document.getElementById('trade-checkout-submit-label');
    var cfg = window.__tradeCheckout || {};
    var goodsAmount = parseInt(cfg.goods_amount, 10) || 0;
    var shippingFee = parseInt(cfg.shipping_fee, 10) || 0;
    var originalAmount = parseInt(cfg.original_amount, 10) || (goodsAmount + shippingFee) || parseInt(cfg.amount, 10) || 0;

    function won(n) {
        return '₩' + (parseInt(n, 10) || 0).toLocaleString('ko-KR');
    }

    function syncCouponAmounts() {
        var discount = 0;
        var goodsPay = goodsAmount;
        if (couponSelect) {
            var opt = couponSelect.options[couponSelect.selectedIndex];
            if (opt) {
                discount = parseInt(opt.getAttribute('data-discount'), 10) || 0;
                goodsPay = parseInt(opt.getAttribute('data-pay'), 10);
                if (isNaN(goodsPay)) goodsPay = goodsAmount;
            }
        }
        var payAmount = goodsPay + shippingFee;
        if (discountRow) {
            discountRow.hidden = discount < 1;
        }
        if (discountAmountEl) {
            discountAmountEl.textContent = discount > 0 ? '-' + won(discount) : '-₩0';
        }
        if (finalAmountEl) {
            finalAmountEl.textContent = won(payAmount);
        }
        if (submitLabel) {
            submitLabel.textContent = won(payAmount) + ' 결제하기';
        }
        cfg.amount = payAmount;
        if (window.__tradeCheckout) {
            window.__tradeCheckout.amount = payAmount;
        }
    }

    if (couponSelect) {
        couponSelect.addEventListener('change', syncCouponAmounts);
        syncCouponAmounts();
    }
})();
</script>
<?php endif; ?>

<?php if ($order_submitted || $submitted): ?>
<script>
(function () {
    var btn = document.getElementById('trade-checkout-reopen-bank');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (typeof window.tradeCheckoutOpenBankLayer === 'function') {
            window.tradeCheckoutOpenBankLayer();
        }
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>
