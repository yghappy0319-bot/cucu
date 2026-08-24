<?php
/**
 * 거래게시판 목록 카드 1개
 *
 * @var array<string, mixed> $row
 * @var array<int, array{label: string, class: string}> $deal_status_labels
 * @var bool $has_wish
 * @var array|null $me
 * @var array<int, bool> $user_wishes
 */
$ds = $deal_status_labels[(int) $row['tr_deal_status']] ?? $deal_status_labels[1];
$ds['label'] = trade_deal_status_label((int) $row['tr_deal_status'], $row['tr_type'] ?? 'sell');
$ts = strtotime($row['tr_created_at']);
$is_box = ($row['tr_item_type'] ?? '') === 'box';
$img = $row['thumb_path'] ?? '';
$inquiry_n = (int) ($row['inquiry_cnt'] ?? $row['tr_comments'] ?? 0);
?>
<div class="trade-item <?php echo htmlspecialchars(trade_list_item_class($row), ENT_QUOTES, 'UTF-8'); ?>" data-tr-idx="<?php echo (int) $row['tr_idx']; ?>">
    <?php if ($has_wish && $me && (int) $row['tr_status'] === TRADE_STATUS_OK && (int) $row['tr_deal_status'] !== 3): ?>
        <?php $wished = !empty($user_wishes[(int) $row['tr_idx']]); ?>
        <form action="/trade/proc/trade_like_proc.php" method="post" class="trade-wish-form">
            <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
            <button type="submit"
                    class="trade-wish-btn <?php echo $wished ? 'is-wished' : ''; ?>"
                    aria-label="<?php echo $wished ? '찜 해제' : '찜하기'; ?>"
                    aria-pressed="<?php echo $wished ? 'true' : 'false'; ?>">
                <span aria-hidden="true"><?php echo $wished ? '♥' : '♡'; ?></span>
            </button>
        </form>
    <?php endif; ?>
    <a href="/trade/trade_view.php?idx=<?php echo (int) $row['tr_idx']; ?>" class="trade-item-link">
    <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
        <?php if (trade_is_held($row)): ?>
            <span class="deal-status ds-hold">게시중지</span>
        <?php else: ?>
            <span class="deal-status <?php echo $ds['class']; ?>"><?php echo $ds['label']; ?></span>
        <?php endif; ?>

        <?php if ($img): ?>
            <img class="trade-image"
                 src="<?php echo htmlspecialchars(public_url($img)); ?>"
                 alt="<?php echo htmlspecialchars(($is_box ? '미개봉 상자 ' : '포켓몬 카드 ') . $row['tr_card_name']); ?>"
                 loading="lazy" decoding="async">
            <?php if ((int) $row['tr_image_count'] > 1): ?>
                <span class="trade-image-count">+<?php echo (int) $row['tr_image_count'] - 1; ?></span>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($is_box): ?>
                <span class="trade-emoji" aria-hidden="true">📦</span>
            <?php else: ?>
                <span class="trade-placeholder-tcg" aria-hidden="true"><span class="trade-placeholder-tcg-inner"></span></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="trade-body">
        <p class="trade-title"><?php echo htmlspecialchars($row['tr_title']); ?></p>
        <p class="trade-card"><?php echo htmlspecialchars($row['tr_card_name']); ?>
            <?php if ($is_box && (int) $row['tr_box_qty'] > 1): ?>
                <span class="trade-qty">×<?php echo (int) $row['tr_box_qty']; ?></span>
            <?php endif; ?>
        </p>
        <div class="trade-price">
            <?php if ((int) $row['tr_price'] > 0): ?>
                <small>₩</small><?php echo number_format((int) $row['tr_price']); ?>
            <?php else: ?>
                <span class="trade-price-free">가격제안</span>
            <?php endif; ?>
        </div>
        <?php if ($inquiry_n > 0): ?>
            <p class="trade-inquiry-count"><span class="trade-inquiry-badge">문의 <?php echo number_format($inquiry_n); ?></span></p>
        <?php endif; ?>
    </div>
    </a>
    <div class="trade-foot trade-foot--meta">
        <?php echo member_nick_link_html((int) ($row['mb_idx'] ?? 0), $row['mb_nick'] ?? null); ?>
        <span>· <?php echo date(date('Y-m-d') === date('Y-m-d', $ts) ? 'H:i' : 'm/d', $ts); ?></span>
        <span>· 조회 <?php echo number_format((int) $row['tr_views']); ?></span>
        <?php if ($inquiry_n > 0): ?>
            <span class="trade-inquiry-count" title="문의">· 문의 <?php echo number_format($inquiry_n); ?></span>
        <?php endif; ?>
        <?php if ($has_wish): ?>
            <span class="trade-wish-count" title="찜"<?php echo (int) $row['tr_likes'] < 1 ? ' hidden' : ''; ?>>♥ <?php echo number_format((int) $row['tr_likes']); ?></span>
        <?php endif; ?>
    </div>
</div>
