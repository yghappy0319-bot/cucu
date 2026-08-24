<?php
/**
 * 구매·판매내역 공통 행
 *
 * @var array<string, mixed> $row
 * @var string $role 'buyer' | 'seller'
 */
if (!isset($row) || !is_array($row)) {
    return;
}
$role = ($role ?? 'buyer') === 'seller' ? 'seller' : 'buyer';

$pay_idx = (int) ($row['pay_idx'] ?? 0);
$tr_idx  = (int) ($row['tr_idx'] ?? 0);
$amount  = (int) ($row['pay_amount'] ?? 0);
$status  = (int) ($row['pay_status'] ?? 0);

$source    = trade_purchase_source_label($row);
$is_buynow = trade_purchase_is_buynow($row);
$status_lbl  = trade_payment_status_label($status);
$fulfill_lbl = trade_payment_fulfill_label($row);
$fulfill_cls = trade_order_fulfill_chip_class($row);

$title_txt = trim((string) ($row['product_label'] ?? ''));
if ($title_txt === '') {
    $title_txt = trim((string) ($row['tr_title'] ?? ''));
}
if ($title_txt === '') {
    $title_txt = '거래 상품';
}

$view_url = '/trade/trade_view.php?idx=' . $tr_idx;
if ($pay_idx > 0 && defined('TRADE_PAYMENT_STATUS_SUBMITTED')
    && $status === TRADE_PAYMENT_STATUS_SUBMITTED) {
    if ($is_buynow) {
        $view_url = '/trade/trade_checkout.php?tr_idx=' . $tr_idx . '&pay_idx=' . $pay_idx;
    } else {
        $room_idx = (int) ($row['room_idx'] ?? 0);
        $view_url = $room_idx > 0
            ? '/trade/trade_messages.php?room_idx=' . $room_idx
            : '/trade/trade_messages.php?tr_idx=' . $tr_idx;
    }
} elseif ($pay_idx > 0) {
    $view_url = trade_payment_ship_url($pay_idx);
}

$buyer_mb_idx = (int) ($buyer_mb_idx ?? 0);
$can_cancel_deposit = $role === 'buyer'
    && $pay_idx > 0
    && function_exists('trade_purchase_can_buyer_cancel_deposit')
    && trade_purchase_can_buyer_cancel_deposit($row, $buyer_mb_idx);
$page_num = max(1, (int) ($page_num ?? 1));
$depositor = trim((string) ($row['depositor_name'] ?? ''));

$date_src = (string) ($row['pay_confirmed_at'] ?? '');
if ($date_src === '') {
    $date_src = (string) ($row['pay_submitted_at'] ?? '');
}
if ($date_src === '') {
    $date_src = (string) ($row['pay_created_at'] ?? '');
}

$thumb = trim((string) ($row['thumb_path'] ?? ''));
if ($thumb !== '') {
    $thumb = public_url($thumb);
}

$buyer_nick = trim((string) ($row['buyer_nick'] ?? ''));
if ($buyer_nick === '') {
    $buyer_nick = '구매자';
}
?>
<li class="trade-order-item">
    <a href="<?php echo htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8'); ?>" class="trade-order-link">
        <?php if ($thumb !== ''): ?>
            <span class="trade-order-thumb">
                <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" width="56" height="56">
            </span>
        <?php else: ?>
            <span class="trade-order-thumb trade-order-thumb--empty" aria-hidden="true">🃏</span>
        <?php endif; ?>

        <span class="trade-order-body">
            <span class="trade-order-title"><?php echo htmlspecialchars($title_txt, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="trade-order-meta">
                <span class="trade-order-chip trade-order-chip--source <?php echo $is_buynow ? 'is-buynow' : 'is-chat'; ?>">
                    <?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if ($role === 'seller'): ?>
                    <span class="trade-order-chip trade-order-chip--buyer">
                        구매자 <?php echo htmlspecialchars($buyer_nick, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
                <span class="trade-order-chip trade-order-chip--amount">₩<?php echo number_format($amount); ?></span>
                <span class="trade-order-chip trade-order-chip--status"><?php echo htmlspecialchars($status_lbl, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="trade-order-chip trade-order-chip--fulfill <?php echo htmlspecialchars($fulfill_cls, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($fulfill_lbl, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </span>
        </span>

        <?php if ($date_src !== ''): ?>
            <time class="trade-order-date" datetime="<?php echo date('c', strtotime($date_src)); ?>">
                <?php echo date('Y-m-d', strtotime($date_src)); ?>
            </time>
        <?php endif; ?>
    </a>
    <?php if ($can_cancel_deposit): ?>
        <div class="trade-sales-paybar trade-order-paybar is-waiting">
            <a href="<?php echo htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8'); ?>" class="trade-sales-paybar__status">입금확인중</a>
            <?php if ($amount > 0 || ($depositor !== '' && $depositor !== '캐시결제')): ?>
                <span class="trade-sales-paybar__meta">
                    <?php if ($amount > 0): ?>
                        ₩<?php echo number_format($amount); ?>
                    <?php endif; ?>
                    <?php if ($depositor !== '' && $depositor !== '캐시결제'): ?>
                        · <?php echo htmlspecialchars($depositor, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <form class="trade-sales-paybar__cancel" action="/trade/proc/trade_purchase_cancel_proc.php" method="post"
                  onsubmit="return confirm('입금 신청을 취소할까요?<?php echo $is_buynow ? '\n바로구매 주문이 삭제됩니다.' : '\n판매자의 결제 요청은 유지됩니다.'; ?>');">
                <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                <input type="hidden" name="p" value="<?php echo $page_num; ?>">
                <button type="submit" class="btn btn-outline btn-sm">취소</button>
            </form>
        </div>
    <?php endif; ?>
</li>
