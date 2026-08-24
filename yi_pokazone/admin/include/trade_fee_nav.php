<?php
/** @var string $ad_fee_nav current tab: list|day|month */
$__ad_fee_nav = isset($ad_fee_nav) ? (string) $ad_fee_nav : 'list';
$__ad_fee_tabs = [
    'list'  => ['label' => '전체 내역', 'href' => '/admin/trade_fees.php'],
    'day'   => ['label' => '일별 집계', 'href' => '/admin/trade_fees_period.php?view=day'],
    'month' => ['label' => '월별 집계', 'href' => '/admin/trade_fees_period.php?view=month'],
];
?>
<nav class="ad-subnav" aria-label="판매자 수수료 보기">
    <?php foreach ($__ad_fee_tabs as $key => $tab): ?>
        <a class="ad-subnav__link<?php echo $__ad_fee_nav === $key ? ' is-active' : ''; ?>"
           href="<?php echo htmlspecialchars((string) ($tab['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
           <?php echo $__ad_fee_nav === $key ? 'aria-current="page"' : ''; ?>>
            <?php echo htmlspecialchars((string) ($tab['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endforeach; ?>
</nav>
