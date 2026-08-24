<?php
/**
 * 판매내역(내 거래글) 행
 *
 * @var array<string, mixed> $row
 * @var array<string, string> $type_labels
 * @var array<int, array{label: string, class: string}> $deal_labels
 * @var int $point_balance
 * @var int $page_num
 * @var string $sales_list_tab
 */
if (!isset($row) || !is_array($row)) {
    return;
}

$tr_idx = (int) ($row['tr_idx'] ?? 0);
$type_lbl = $type_labels[$row['tr_type'] ?? ''] ?? '판매';
$deal = $deal_labels[(int) ($row['tr_deal_status'] ?? 1)] ?? $deal_labels[1];
$deal['label'] = trade_deal_status_label((int) ($row['tr_deal_status'] ?? 1), $row['tr_type'] ?? 'sell');
$bump_state = trade_post_bump_ui_state($row);
$can_bump = !empty($bump_state['can_bump']) && $point_balance >= trade_bump_point_cost();

$title_txt = trim((string) ($row['tr_title'] ?? ''));
if ($title_txt === '') {
    $title_txt = '거래글';
}

$thumb = trim((string) ($row['thumb_path'] ?? ''));
if ($thumb !== '') {
    $thumb = public_url($thumb);
}

$sort_at = (string) ($row['tr_bumped_at'] ?? '');
if ($sort_at === '') {
    $sort_at = (string) ($row['tr_created_at'] ?? '');
}
$created_at = (string) ($row['tr_created_at'] ?? '');
?>
<li class="trade-sales-item">
    <div class="trade-sales-row">
        <a href="/trade/trade_view.php?idx=<?php echo $tr_idx; ?>" class="trade-sales-link">
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
                    <span class="trade-order-chip trade-order-chip--source"><?php echo htmlspecialchars($type_lbl, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="trade-order-chip trade-order-chip--fulfill <?php echo htmlspecialchars($deal['class'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($deal['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php if (trade_is_held($row)): ?>
                        <span class="trade-order-chip trade-order-chip--hold">게시중지</span>
                    <?php endif; ?>
                    <?php if ((int) ($row['tr_price'] ?? 0) > 0): ?>
                        <span class="trade-order-chip trade-order-chip--amount">₩<?php echo number_format((int) $row['tr_price']); ?></span>
                    <?php else: ?>
                        <span class="trade-order-chip">가격제안</span>
                    <?php endif; ?>
                    <span class="trade-order-chip">조회 <?php echo number_format((int) ($row['tr_views'] ?? 0)); ?></span>
                    <?php if (!empty($row['tr_bumped_at'])): ?>
                        <span class="trade-order-chip trade-order-chip--bumped">끌올 <?php echo date('m/d H:i', strtotime((string) $row['tr_bumped_at'])); ?></span>
                    <?php endif; ?>
                </span>
            </span>

            <?php if ($sort_at !== ''): ?>
                <time class="trade-order-date" datetime="<?php echo date('c', strtotime($sort_at)); ?>">
                    <?php echo date('Y-m-d', strtotime($sort_at)); ?>
                </time>
            <?php endif; ?>
        </a>

        <div class="trade-sales-actions">
            <?php if ($sales_list_tab === 'active' && $can_bump): ?>
                <form action="/trade/proc/trade_bump_proc.php" method="post"
                      onsubmit="return confirm('<?php echo number_format(trade_bump_point_cost()); ?>P를 사용해 이 글을 끌올할까요?\n이 글은 하루에 한 번만 끌올할 수 있습니다.');">
                    <input type="hidden" name="tr_idx" value="<?php echo $tr_idx; ?>">
                    <input type="hidden" name="p" value="<?php echo (int) $page_num; ?>">
                    <input type="hidden" name="tab" value="active">
                    <button type="submit" class="btn btn-primary btn-sm trade-sales-bump-btn">끌올</button>
                </form>
                <span class="trade-sales-bump-cost"><?php echo number_format(trade_bump_point_cost()); ?>P</span>
            <?php elseif ($sales_list_tab === 'active'): ?>
                <button type="button" class="btn btn-outline btn-sm trade-sales-bump-btn" disabled title="<?php echo htmlspecialchars($bump_state['reason'], ENT_QUOTES, 'UTF-8'); ?>">
                    끌올
                </button>
                <span class="trade-sales-bump-hint">
                    <?php
                    if ($bump_state['reason'] !== '') {
                        echo htmlspecialchars($bump_state['reason'], ENT_QUOTES, 'UTF-8');
                    } elseif ($point_balance < trade_bump_point_cost()) {
                        echo '포인트 부족';
                    }
                    ?>
                </span>
            <?php endif; ?>
            <a href="/trade/trade_view.php?idx=<?php echo $tr_idx; ?>" class="btn btn-outline btn-sm">보기</a>
        </div>
    </div>
    <?php
    $sale_pay = is_array($sale_pay ?? null) ? $sale_pay : null;
    $sale_pay_status = $sale_pay ? (int) ($sale_pay['pay_status'] ?? 0) : -1;
    $sale_pay_idx = $sale_pay ? (int) ($sale_pay['pay_idx'] ?? 0) : 0;
    $sale_pay_submitted = defined('TRADE_PAYMENT_STATUS_SUBMITTED')
        && $sale_pay_status === TRADE_PAYMENT_STATUS_SUBMITTED;
    $sale_pay_confirmed = $sale_pay && function_exists('trade_payment_is_confirmed_status')
        && trade_payment_is_confirmed_status($sale_pay_status);
    if ($sale_pay && ($sale_pay_submitted || $sale_pay_confirmed)):
        $sale_pay_amount = (int) ($sale_pay['pay_amount'] ?? 0);
        $sale_pay_depositor = trim((string) ($sale_pay['depositor_name'] ?? ''));
        $sale_pay_checkout = '/trade/trade_checkout.php?tr_idx=' . $tr_idx;
        if ($sale_pay_idx > 0) {
            $sale_pay_checkout .= '&pay_idx=' . $sale_pay_idx;
        }
        ?>
        <div class="trade-sales-paybar<?php echo $sale_pay_confirmed ? ' is-confirmed' : ' is-waiting'; ?>">
            <a href="<?php echo htmlspecialchars($sale_pay_checkout, ENT_QUOTES, 'UTF-8'); ?>" class="trade-sales-paybar__status">
                <?php echo $sale_pay_confirmed ? '입금확인' : '입금확인중'; ?>
            </a>
            <?php if ($sale_pay_amount > 0 || $sale_pay_depositor !== ''): ?>
                <span class="trade-sales-paybar__meta">
                    <?php if ($sale_pay_amount > 0): ?>
                        ₩<?php echo number_format($sale_pay_amount); ?>
                    <?php endif; ?>
                    <?php if ($sale_pay_depositor !== '' && $sale_pay_depositor !== '캐시결제'): ?>
                        · <?php echo htmlspecialchars($sale_pay_depositor, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($sale_pay_submitted && $sale_pay_idx > 0): ?>
                <form class="trade-sales-paybar__cancel" action="/trade/proc/trade_sales_pay_cancel_proc.php" method="post"
                      onsubmit="return confirm('입금 신청을 취소할까요?\n구매자의 결제 요청이 취소됩니다.');">
                    <input type="hidden" name="pay_idx" value="<?php echo $sale_pay_idx; ?>">
                    <input type="hidden" name="tr_idx" value="<?php echo $tr_idx; ?>">
                    <input type="hidden" name="p" value="<?php echo (int) $page_num; ?>">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars((string) $sales_list_tab, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-outline btn-sm">취소</button>
                </form>
            <?php elseif ($sale_pay_confirmed && $sale_pay_idx > 0): ?>
                <?php
                $sale_pay_ship = function_exists('trade_payment_ship_url')
                    ? trade_payment_ship_url($sale_pay_idx)
                    : '';
                if ($sale_pay_ship === '') {
                    $sale_pay_ship = '/trade/trade_payment_ship.php?pay_idx=' . $sale_pay_idx;
                }
                ?>
                <a href="<?php echo htmlspecialchars($sale_pay_ship, ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-primary btn-sm trade-sales-paybar__prepare">준비하기</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($created_at !== '' && !empty($row['tr_bumped_at'])): ?>
        <p class="trade-sales-submeta">등록 <?php echo date('Y-m-d', strtotime($created_at)); ?></p>
    <?php endif; ?>
</li>
