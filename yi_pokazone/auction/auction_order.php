<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_auction.php';
require_once __DIR__ . '/lib/_auction_order.php';
require_once __DIR__ . '/../lib/_member_address.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/auction/auction_order.php?au_idx=' . (int) ($_GET['au_idx'] ?? 0)));
}

$au_idx = (int) ($_GET['au_idx'] ?? 0);
if ($au_idx < 1) {
    alert_goto('잘못된 접근입니다.', '/auction/auction.php');
}

auction_finalize_expired($au_idx);

$rs = db_query("
    SELECT a.*, m.mb_nick AS seller_nick
    FROM tb_auction a
    LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
    WHERE a.au_idx = {$au_idx} AND a.au_status = 1
    LIMIT 1
");
$auction = db_assoc($rs);
if (!$auction) {
    alert_goto('존재하지 않거나 삭제된 경매입니다.', '/auction/auction.php');
}

$mb_idx     = (int) $me['mb_idx'];
$view_role  = auction_order_view_role($auction, $me);

if ((int) ($auction['au_auction_status'] ?? 0) !== 3) {
    alert_goto('낙찰된 경매만 주문·확인할 수 있습니다.', '/auction/auction_view.php?idx=' . $au_idx);
}
if ($view_role === '') {
    alert_goto('낙찰자 또는 판매자만 확인할 수 있습니다.', '/auction/auction_view.php?idx=' . $au_idx);
}

$is_winner_view = $view_role === 'winner';
$is_seller_view = !$is_winner_view && in_array($view_role, ['seller', 'admin'], true);

$winner_mb_idx = (int) ($auction['au_winner_mb_idx'] ?? 0);
$buyer_nick    = '';
if ($winner_mb_idx > 0) {
    $buyer_nick = (string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . $winner_mb_idx . ' LIMIT 1');
}

$order            = auction_order_get_by_auction($au_idx);
$order_paid       = auction_order_is_paid($order);
$order_cancelled  = auction_order_is_cancelled($order);
$auction_ban_ready   = member_auction_ban_column_ready();
$is_auction_banned   = $auction_ban_ready && member_auction_is_banned($mb_idx);
$addresses   = ($is_winner_view && member_address_table_ready()) ? member_address_list($mb_idx) : [];
$cash_ready  = member_cash_column_ready();
$cash_bal    = $cash_ready ? member_cash_balance($mb_idx) : 0;
$pay_amount     = (int) ($auction['au_current_price'] ?? 0);
$is_box         = ($auction['au_item_type'] ?? '') === 'box';
$done           = isset($_GET['done']) && (string) $_GET['done'] === '1';
$message_ready   = auction_order_message_column_ready();
$tracking_ready  = auction_order_tracking_column_ready();
$confirm_ready   = auction_order_confirm_column_ready();
$refund_ready    = auction_order_refund_column_ready();
$has_tracking    = $order && auction_order_has_tracking($order);
$is_confirmed    = $order && auction_order_is_confirmed($order);
$is_refunded       = $order && auction_order_is_refunded($order);
$is_refund_pending = $order && auction_order_is_refund_pending($order);
$can_request_refund = $refund_ready && $is_winner_view && $order && auction_order_can_buyer_request_refund($order);
$can_seller_accept_refund = $refund_ready && $is_seller_view && $order && auction_order_can_seller_accept_refund($order);
$refund_days_left = ($order && $is_confirmed && !$is_refunded) ? auction_order_refund_days_left($order) : 0;
$refund_expires_at = ($order && $is_confirmed && !$is_refunded) ? auction_order_refund_expires_at($order) : null;
$delivery_steps  = ($order_paid && $order) ? auction_order_delivery_steps($order) : [];
$can_confirm     = $order_paid && $is_winner_view && $order && auction_order_can_buyer_confirm($order, true);
$settle_preview  = $order_paid && $order ? auction_order_settlement_amounts((int) ($order['ao_amount'] ?? 0)) : ['fee' => 0, 'seller_amount' => 0];
$courier_presets = auction_courier_presets();

$thumb = '';
if (auction_image_table_ok()) {
    $thumb = (string) db_result("
        SELECT ai_path FROM tb_auction_image
        WHERE au_idx = {$au_idx}
        ORDER BY ai_order ASC, ai_idx ASC
        LIMIT 1
    ");
}

$page  = 'auction_order';
$title = ($is_seller_view ? '낙찰 주문 확인' : '낙찰 주문') . ' · ' . (string) $auction['au_title'];
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '경매', 'url' => '/auction/auction.php'],
    ['name' => $is_seller_view ? '낙찰 주문 확인' : '낙찰 주문', 'url' => '/auction/auction_order.php?au_idx=' . $au_idx],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage auction-order">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_seller_view ? '낙찰 주문 확인' : '낙찰 주문'; ?></h1>
                <p class="board-desc">
                    <?php if ($is_seller_view): ?>
                        구매자가 입력한 낙찰·배송 정보를 확인하고 택배 발송을 진행해 주세요.
                    <?php else: ?>
                        낙찰된 상품의 배송지와 결제 정보를 입력해 주세요.
                    <?php endif; ?>
                </p>
            </div>
            <div class="mypage-head-actions">
                <a href="/auction/auction_view.php?idx=<?php echo $au_idx; ?>" class="btn btn-outline btn-sm">경매 상세</a>
            </div>
        </div>

        <?php if ($is_seller_view): ?>
            <?php if ($order_paid && $is_refunded): ?>
                <div class="auction-order-alert auction-order-alert--refunded" role="status">
                    <strong><?php echo htmlspecialchars($buyer_nick !== '' ? $buyer_nick : '구매자'); ?></strong>님 환불 완료
                    · ₩<?php echo number_format((int) ($order['ao_refund_amount'] ?? 0)); ?>
                </div>
            <?php elseif ($order_paid && $is_refund_pending): ?>
                <div class="auction-order-alert auction-order-alert--refund-pending" role="status">
                    <strong><?php echo htmlspecialchars($buyer_nick !== '' ? $buyer_nick : '구매자'); ?></strong>님 환불 신청
                    · ₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?>
                    <?php if (!empty($order['ao_refund_requested_at'])): ?>
                        · <?php echo htmlspecialchars((string) $order['ao_refund_requested_at']); ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($order_paid): ?>
                <div class="auction-order-alert auction-order-alert--done">
                    <strong><?php echo htmlspecialchars($buyer_nick !== '' ? $buyer_nick : '구매자'); ?></strong>님 결제 완료
                    · 주문번호 <strong>#<?php echo (int) ($order['ao_idx'] ?? 0); ?></strong>
                    · <?php echo htmlspecialchars(auction_order_status_label((int) ($order['ao_order_status'] ?? 1))); ?>
                </div>
            <?php else: ?>
                <div class="auction-order-alert auction-order-alert--wait">
                    구매자 <strong><?php echo htmlspecialchars($buyer_nick !== '' ? $buyer_nick : '(닉네임 없음)'); ?></strong>님이
                    아직 배송·결제 정보를 입력하지 않았습니다.
                </div>
            <?php endif; ?>
        <?php elseif ($order_cancelled): ?>
            <div class="auction-order-alert auction-order-alert--cancelled" role="status">
                이 낙찰 주문은 <strong>취소</strong>되었습니다.
            </div>
        <?php elseif ($is_auction_banned): ?>
            <div class="auction-order-alert auction-order-alert--ban" role="status">
                <?php echo htmlspecialchars(member_auction_ban_user_message()); ?>
            </div>
        <?php elseif (!$order_paid): ?>
            <div class="auction-order-alert">
                낙찰 축하합니다! <strong>24시간 이내</strong> 배송지와 결제를 완료해 주세요.
            </div>
        <?php elseif ($is_refunded): ?>
            <div class="auction-order-alert auction-order-alert--refunded" role="status">
                <strong>환불 완료</strong>
                · ₩<?php echo number_format((int) ($order['ao_refund_amount'] ?? $order['ao_amount'] ?? 0)); ?>
                <?php if (!empty($order['ao_refunded_at'])): ?>
                    · <?php echo htmlspecialchars((string) $order['ao_refunded_at']); ?>
                <?php endif; ?>
            </div>
        <?php elseif ($is_refund_pending): ?>
            <div class="auction-order-alert auction-order-alert--refund-pending" role="status">
                <strong>환불 신청 중</strong> · 판매자 수락 대기
                <?php if (!empty($order['ao_refund_requested_at'])): ?>
                    · <?php echo htmlspecialchars((string) $order['ao_refund_requested_at']); ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="auction-order-alert auction-order-alert--done">
                결제가 완료되었습니다. 주문번호 <strong>#<?php echo (int) ($order['ao_idx'] ?? 0); ?></strong>
                · <?php echo htmlspecialchars(auction_order_status_label((int) ($order['ao_order_status'] ?? 1))); ?>
            </div>
        <?php endif; ?>

        <div class="auction-order-layout">
            <div class="mypage-panel auction-order-product">
                <h2 class="mypage-panel-title">낙찰 상품</h2>
                <div class="auction-order-product-inner">
                    <?php if ($thumb !== ''): ?>
                        <img src="<?php echo htmlspecialchars(public_url($thumb)); ?>"
                             alt="<?php echo htmlspecialchars((string) $auction['au_card_name']); ?>"
                             class="auction-order-product-thumb" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="auction-order-product-thumb auction-order-product-thumb--empty" aria-hidden="true">
                            <?php echo $is_box ? '📦' : '🃏'; ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="auction-order-product-title"><?php echo htmlspecialchars((string) $auction['au_title']); ?></p>
                        <p class="auction-order-product-sub"><?php echo htmlspecialchars((string) $auction['au_card_name']); ?></p>
                        <ul class="auction-order-product-meta">
                            <li>판매자 <?php echo htmlspecialchars((string) ($auction['seller_nick'] ?? '(탈퇴)')); ?></li>
                            <li>거래 방식 택배</li>
                            <li>마감 <?php echo date('Y-m-d H:i', strtotime((string) $auction['au_ends_at'])); ?></li>
                        </ul>
                        <p class="auction-order-product-price">낙찰가 <strong>₩<?php echo number_format($pay_amount); ?></strong></p>
                    </div>
                </div>
            </div>

            <?php if ($is_seller_view && !$order_paid): ?>
                <div class="mypage-panel auction-order-seller-wait">
                    <h2 class="mypage-panel-title">낙찰 요약</h2>
                    <dl class="mypage-dl">
                        <div>
                            <dt>낙찰자</dt>
                            <dd><?php echo htmlspecialchars($buyer_nick !== '' ? $buyer_nick : '—'); ?></dd>
                        </div>
                        <div>
                            <dt>낙찰가</dt>
                            <dd>₩<?php echo number_format($pay_amount); ?></dd>
                        </div>
                        <div>
                            <dt>결제</dt>
                            <dd><span class="auction-seller-summary-wait">결제 대기</span></dd>
                        </div>
                    </dl>
                    <p class="auction-order-seller-wait-note">
                        구매자가 결제를 완료하면 배송지·연락처·배송 메시지를 이 페이지에서 확인할 수 있습니다.
                    </p>
                </div>
            <?php elseif ($order_paid): ?>
                <div class="mypage-panel auction-order-done-panel">
                    <h2 class="mypage-panel-title"><?php echo $is_seller_view ? '구매자 주문 정보' : '주문 정보'; ?></h2>
                    <dl class="mypage-dl">
                        <?php if ($is_seller_view): ?>
                        <div>
                            <dt>낙찰자</dt>
                            <dd><?php echo htmlspecialchars($buyer_nick !== '' ? $buyer_nick : '—'); ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt>결제 수단</dt>
                            <dd><?php echo htmlspecialchars(auction_order_pay_method_label((string) ($order['ao_pay_method'] ?? 'cash'))); ?>
                                <?php if (($order['ao_pay_method'] ?? '') === 'card' && !empty($order['ao_card_last4'])): ?>
                                    (**** <?php echo htmlspecialchars((string) $order['ao_card_last4']); ?>)
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>결제 금액</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?></dd>
                        </div>
                        <div>
                            <dt>결제 일시</dt>
                            <dd><?php echo htmlspecialchars((string) ($order['ao_paid_at'] ?? '')); ?></dd>
                        </div>
                        <div class="auction-order-addr-row">
                            <dt>배송지</dt>
                            <dd>
                                <?php
                                $addr_line = '[' . ($order['ao_addr_zip'] ?? '') . '] ' . ($order['ao_addr_road'] ?? '');
                                if (!empty($order['ao_addr_extra'])) {
                                    $addr_line .= ' ' . $order['ao_addr_extra'];
                                }
                                if (!empty($order['ao_addr_detail'])) {
                                    $addr_line .= ', ' . $order['ao_addr_detail'];
                                }
                                echo htmlspecialchars($addr_line);
                                ?><br>
                                <?php echo htmlspecialchars((string) ($order['ao_addr_name'] ?? '')); ?>
                                · <?php echo htmlspecialchars((string) ($order['ao_addr_phone'] ?? '')); ?>
                                <?php if (trim((string) ($order['ao_addr_message'] ?? '')) !== ''): ?>
                                    <br><span class="auction-order-addr-message">배송 메시지: <?php echo htmlspecialchars((string) $order['ao_addr_message']); ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if ($has_tracking): ?>
                        <div class="auction-order-tracking-row">
                            <dt>택배</dt>
                            <dd>
                                <?php echo htmlspecialchars((string) ($order['ao_courier_name'] ?? '')); ?>
                                · 운송장 <strong><?php echo htmlspecialchars((string) ($order['ao_tracking_no'] ?? '')); ?></strong>
                                <?php if (!empty($order['ao_shipped_at'])): ?>
                                    <span class="auction-order-tracking-muted">(<?php echo htmlspecialchars((string) $order['ao_shipped_at']); ?> 등록)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($is_seller_view): ?>
                        <p class="auction-order-seller-ship-note">위 배송지로 택배 발송을 진행해 주세요.</p>
                    <?php elseif (!$is_seller_view && !$has_tracking): ?>
                        <p class="auction-order-tracking-wait">판매자가 운송장을 등록하면 이곳에서 확인할 수 있습니다.</p>
                    <?php endif; ?>
                </div>

                <?php if (!$is_seller_view && $order_paid && !empty($delivery_steps)): ?>
                <div class="mypage-panel auction-order-delivery-panel">
                    <h2 class="mypage-panel-title">배송 현황</h2>
                    <ol class="auction-order-delivery-steps">
                        <?php foreach ($delivery_steps as $step): ?>
                            <li class="auction-order-delivery-step is-<?php echo htmlspecialchars($step['state']); ?>">
                                <span class="auction-order-delivery-step__dot" aria-hidden="true"></span>
                                <span class="auction-order-delivery-step__label"><?php echo htmlspecialchars($step['label']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if ($is_refunded): ?>
                        <p class="auction-order-refund-done">
                            환불이 완료되었습니다.
                            <?php if (!empty($order['ao_refunded_at'])): ?>
                                · <?php echo htmlspecialchars((string) $order['ao_refunded_at']); ?>
                            <?php endif; ?>
                        </p>
                    <?php elseif ($is_confirmed): ?>
                        <p class="auction-order-confirm-done">
                            구매확정 완료
                            <?php if (!empty($order['ao_buyer_confirmed_at'])): ?>
                                · <?php echo htmlspecialchars((string) $order['ao_buyer_confirmed_at']); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($is_refund_pending): ?>
                        <p class="auction-order-refund-pending">
                            환불 신청이 접수되었습니다. 판매자가 수락하면
                            <strong>₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?></strong>이 캐시로 환불됩니다.
                        </p>
                        <?php elseif ($can_request_refund): ?>
                        <div class="auction-order-refund-box">
                            <p class="auction-order-refund-hint">
                                상품 하자 등으로 환불이 필요하면 <strong><?php echo (int) AUCTION_REFUND_PERIOD_DAYS; ?>일 이내</strong>에 신청해 주세요.
                                (남은 기간 <?php echo $refund_days_left; ?>일
                                <?php if ($refund_expires_at): ?>
                                    · <?php echo date('Y-m-d H:i', $refund_expires_at); ?>까지
                                <?php endif; ?>)
                                <br>판매자가 수락하면 결제 금액 <strong>₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?></strong>이 보유 캐시로 환불됩니다.
                            </p>
                            <form method="post" action="/auction/proc/auction_order_refund_proc.php" class="auction-order-refund-form"
                                  onsubmit="return confirm('환불을 신청하시겠습니까?\n\n판매자가 수락하면 ₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?>이 캐시로 환불됩니다.');">
                                <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">
                                <button type="submit" class="btn btn-outline btn-block auction-order-refund-btn">환불 신청</button>
                            </form>
                        </div>
                        <?php elseif ($refund_ready && $refund_expires_at !== null && time() > $refund_expires_at): ?>
                        <p class="auction-order-refund-expired">환불 가능 기간이 지났습니다. 문의는 고객센터를 이용해 주세요.</p>
                        <?php endif; ?>
                    <?php elseif ($can_confirm && $confirm_ready): ?>
                        <p class="auction-order-confirm-hint">
                            상품을 받으셨다면 구매확정을 진행해 주세요.
                            판매자에게 ₩<?php echo number_format($settle_preview['seller_amount']); ?>이 정산됩니다.
                            (플랫폼 수수료 <?php echo (int) platform_fee_auction_percent(); ?>% · ₩<?php echo number_format($settle_preview['fee']); ?>)
                        </p>
                        <form method="post" action="/auction/proc/auction_order_confirm_proc.php" class="auction-order-confirm-form"
                              onsubmit="return confirm('구매확정하시겠습니까? 판매자에게 정산이 진행됩니다. 구매확정 후 <?php echo (int) AUCTION_REFUND_PERIOD_DAYS; ?>일 이내에 환불 신청이 가능하며, 판매자 수락 시 환불됩니다.');">
                            <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">
                            <button type="submit" class="btn btn-primary btn-block">구매확정 (테스트)</button>
                        </form>
                    <?php elseif (!$can_confirm && $confirm_ready): ?>
                        <p class="auction-order-confirm-hint">판매자가 운송장을 등록하면 구매확정(테스트) 버튼이 활성화됩니다.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($is_seller_view && $order_paid && !$is_refunded && !auction_order_is_confirmed($order ?? [])): ?>
                <form method="post" action="/auction/proc/auction_order_delivered_proc.php" class="mypage-panel auction-order-delivered-form"
                      onsubmit="return confirm('배송완료 상태로 변경할까요? (테스트)');">
                    <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">
                    <h2 class="mypage-panel-title">배송완료 (테스트)</h2>
                    <p class="auction-order-tracking-form-desc">구매자가 구매확정할 수 있도록 배송완료 단계로 변경합니다.</p>
                    <button type="submit" class="btn btn-outline btn-block">배송완료 처리</button>
                </form>
                <?php endif; ?>

                <?php if ($is_seller_view && $is_refund_pending && $can_seller_accept_refund): ?>
                <div class="mypage-panel auction-order-refund-accept-panel">
                    <h2 class="mypage-panel-title">환불 신청</h2>
                    <p class="auction-order-refund-accept-lead">
                        구매자가 환불을 신청했습니다.
                        <?php if (!empty($order['ao_refund_requested_at'])): ?>
                            (<?php echo htmlspecialchars((string) $order['ao_refund_requested_at']); ?>)
                        <?php endif; ?>
                    </p>
                    <dl class="mypage-dl">
                        <div>
                            <dt>환불 요청액</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?></dd>
                        </div>
                        <div>
                            <dt>회수 예정 정산금</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_seller_settle_amount'] ?? 0)); ?></dd>
                        </div>
                        <div>
                            <dt>보유 캐시</dt>
                            <dd>₩<?php echo $cash_ready ? number_format(member_cash_balance($mb_idx)) : '—'; ?></dd>
                        </div>
                    </dl>
                    <p class="auction-order-refund-accept-hint">
                        수락 시 구매자에게 결제 금액이 환불되고, 판매자에게 지급된 정산금이 회수됩니다.
                    </p>
                    <form method="post" action="/auction/proc/auction_order_refund_accept_proc.php"
                          onsubmit="return confirm('환불을 수락하시겠습니까?\n\n구매자에게 ₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?>이 환불되고, 정산금 ₩<?php echo number_format((int) ($order['ao_seller_settle_amount'] ?? 0)); ?>이 회수됩니다.');">
                        <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">
                        <button type="submit" class="btn btn-primary btn-block">환불 수락</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($is_seller_view && $is_confirmed && $confirm_ready): ?>
                <div class="mypage-panel auction-order-settle-panel">
                    <h2 class="mypage-panel-title"><?php echo $is_refunded ? '환불 완료' : ($is_refund_pending ? '환불 신청 중' : '정산 완료'); ?></h2>
                    <dl class="mypage-dl">
                        <div>
                            <dt>구매확정</dt>
                            <dd><?php echo htmlspecialchars((string) ($order['ao_buyer_confirmed_at'] ?? '')); ?></dd>
                        </div>
                        <?php if ($is_refunded): ?>
                        <div>
                            <dt>환불 일시</dt>
                            <dd><?php echo htmlspecialchars((string) ($order['ao_refunded_at'] ?? '')); ?></dd>
                        </div>
                        <div>
                            <dt>환불 금액</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_refund_amount'] ?? 0)); ?></dd>
                        </div>
                        <?php elseif ($is_refund_pending): ?>
                        <div>
                            <dt>환불 신청</dt>
                            <dd><?php echo htmlspecialchars((string) ($order['ao_refund_requested_at'] ?? '')); ?></dd>
                        </div>
                        <div>
                            <dt>환불 요청액</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_amount'] ?? 0)); ?></dd>
                        </div>
                        <?php else: ?>
                        <div>
                            <dt>판매자 정산액</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_seller_settle_amount'] ?? 0)); ?></dd>
                        </div>
                        <div>
                            <dt>플랫폼 수수료</dt>
                            <dd>₩<?php echo number_format((int) ($order['ao_platform_fee'] ?? 0)); ?> (<?php echo (int) platform_fee_auction_percent(); ?>%)</dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php endif; ?>

                <?php if ($is_seller_view && $tracking_ready && !$is_confirmed): ?>
                <form class="mypage-panel auction-order-tracking-form" method="post" action="/auction/proc/auction_order_tracking_proc.php">
                    <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">
                    <h2 class="mypage-panel-title">택배 발송 정보</h2>
                    <p class="auction-order-tracking-form-desc">택배사와 운송장번호를 입력하면 구매자에게 알림이 발송됩니다.</p>
                    <div class="field">
                        <label for="ao_courier_name">택배사 <span class="req">*</span></label>
                        <input type="text" id="ao_courier_name" name="ao_courier_name" list="auction-courier-list"
                               maxlength="<?php echo (int) AUCTION_ORDER_COURIER_MAX; ?>" required
                               value="<?php echo htmlspecialchars((string) ($order['ao_courier_name'] ?? '')); ?>"
                               placeholder="예: CJ대한통운">
                        <datalist id="auction-courier-list">
                            <?php foreach ($courier_presets as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="field">
                        <label for="ao_tracking_no">운송장번호 <span class="req">*</span></label>
                        <input type="text" id="ao_tracking_no" name="ao_tracking_no"
                               maxlength="<?php echo (int) AUCTION_ORDER_TRACKING_MAX; ?>" required
                               inputmode="numeric" autocomplete="off"
                               value="<?php echo htmlspecialchars((string) ($order['ao_tracking_no'] ?? '')); ?>"
                               placeholder="숫자·영문·하이픈(-)">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo $has_tracking ? '운송장 정보 수정' : '운송장 등록 및 구매자 알림'; ?>
                    </button>
                </form>
                <?php endif; ?>
            <?php elseif ($is_winner_view && !$order_cancelled && !$is_auction_banned): ?>
                <form class="mypage-panel auction-order-form" method="post" action="/auction/proc/auction_order_proc.php">
                    <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">

                    <h2 class="mypage-panel-title">배송지</h2>
                    <?php if (empty($addresses)): ?>
                        <p class="auction-order-empty-addr">
                            등록된 배송지가 없습니다.
                            <a href="/page/member_address.php">배송지 주소록</a>에서 먼저 추가해 주세요.
                        </p>
                    <?php else: ?>
                        <ul class="auction-order-addr-list">
                            <?php foreach ($addresses as $addr):
                                $addr_idx = (int) $addr['addr_idx'];
                                $full     = trim(
                                    '[' . $addr['addr_zip'] . '] ' . $addr['addr_road']
                                    . ($addr['addr_extra'] !== '' ? ' ' . $addr['addr_extra'] : '')
                                    . ($addr['addr_detail'] !== '' ? ', ' . $addr['addr_detail'] : '')
                                );
                                ?>
                                <li>
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
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="auction-order-addr-manage">
                            <a href="/page/member_address.php">배송지 관리</a>
                        </p>
                        <?php if ($message_ready): ?>
                            <div class="field auction-order-message-field">
                                <label for="ao_addr_message">배송 메시지</label>
                                <textarea id="ao_addr_message" name="ao_addr_message" rows="3"
                                          maxlength="<?php echo (int) MEMBER_ADDRESS_MESSAGE_MAX; ?>"
                                          placeholder="부재 시 문 앞에 놓아주세요, 배송 전 연락 부탁드립니다 등"></textarea>
                                <p class="field-hint">선택 입력 · 이 주문에만 적용됩니다 (배송지 주소록은 변경되지 않습니다)</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <h2 class="mypage-panel-title auction-order-pay-title">결제</h2>
                    <p class="auction-order-pay-amount">결제 금액 <strong>₩<?php echo number_format($pay_amount); ?></strong></p>

                    <div class="auction-order-pay-methods">
                        <label class="auction-order-pay-opt">
                            <input type="radio" name="pay_method" value="cash" checked>
                            <span>
                                <strong>캐시 결제</strong>
                                <?php if ($cash_ready): ?>
                                    <small>보유 ₩<?php echo number_format($cash_bal); ?></small>
                                <?php else: ?>
                                    <small class="is-warn">mb_cash 컬럼 필요 (sql/migrate_tb_member_cash.sql)</small>
                                <?php endif; ?>
                            </span>
                        </label>
                        <label class="auction-order-pay-opt">
                            <input type="radio" name="pay_method" value="card">
                            <span>
                                <strong>카드 결제</strong>
                                <small>테스트용 · 실제 PG 미연동</small>
                            </span>
                        </label>
                    </div>

                    <div class="auction-order-card-fields" id="auction-order-card-fields" hidden>
                        <div class="field">
                            <label for="ao_card_no">카드 번호 (테스트)</label>
                            <input type="text" id="ao_card_no" name="card_no" maxlength="19"
                                   inputmode="numeric" placeholder="0000 0000 0000 0000" autocomplete="off">
                        </div>
                        <p class="auction-order-card-hint">아무 유효한 15~16자리 번호 입력 시 결제 성공 처리됩니다.</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" <?php echo empty($addresses) ? 'disabled' : ''; ?>>
                        ₩<?php echo number_format($pay_amount); ?> 결제하고 주문 완료
                    </button>
                </form>

                <?php if ($auction_ban_ready): ?>
                <div class="mypage-panel auction-order-cancel-panel">
                    <h2 class="mypage-panel-title">낙찰 주문 취소</h2>
                    <p class="auction-order-cancel-warn">
                        결제 전 낙찰을 포기하면 <strong>경매 이용이 영구 제한</strong>됩니다.
                        (입찰·경매 등록·낙찰 주문 불가) 신중히 결정해 주세요.
                    </p>
                    <form method="post" action="/auction/proc/auction_order_cancel_proc.php"
                          onsubmit="return confirm('낙찰 주문을 취소하시겠습니까?\n\n취소 시 이 계정은 경매 이용이 제한되며, 되돌릴 수 없습니다.');">
                        <input type="hidden" name="au_idx" value="<?php echo $au_idx; ?>">
                        <button type="submit" class="btn btn-outline btn-block auction-order-cancel-btn">낙찰 주문 취소</button>
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($is_winner_view && !$order_paid): ?>
<script>
(function () {
    var form = document.querySelector('.auction-order-form');
    if (!form) return;
    var cardBox = document.getElementById('auction-order-card-fields');
    var cardInput = document.getElementById('ao_card_no');
    var msgInput = document.getElementById('ao_addr_message');
    var addrRadios = form.querySelectorAll('input[name="addr_idx"]');

    function syncMessageFromAddress() {
        if (!msgInput) return;
        var checked = form.querySelector('input[name="addr_idx"]:checked');
        if (!checked) return;
        msgInput.value = checked.getAttribute('data-addr-message') || '';
    }

    addrRadios.forEach(function (radio) {
        radio.addEventListener('change', syncMessageFromAddress);
    });
    syncMessageFromAddress();

    form.querySelectorAll('input[name="pay_method"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var isCard = form.querySelector('input[name="pay_method"]:checked').value === 'card';
            if (cardBox) cardBox.hidden = !isCard;
            if (cardInput) cardInput.required = isCard;
        });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>
