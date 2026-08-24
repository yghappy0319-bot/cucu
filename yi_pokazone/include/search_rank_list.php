<?php
/**
 * 인기 검색어 목록 partial
 *
 * @var array  $search_rank_rows   search_rank_top() 결과
 * @var string $search_rank_variant 'header' | 'page' | 'hero' | 'home-mobile'
 */
if (empty($search_rank_rows) || !is_array($search_rank_rows)) {
    return;
}
$search_rank_variant = $search_rank_variant ?? 'page';
$list_id = $search_rank_list_id ?? ('search-rank-list-' . $search_rank_variant);
$list_attrs = $search_rank_list_attrs ?? '';
if (in_array($search_rank_variant, ['header', 'page', 'hero', 'home-mobile'], true)) {
    $list_attrs = trim($list_attrs . ' data-search-rank-list');
}
$list_class = 'search-rank-list search-rank-list--' . $search_rank_variant;
if ($search_rank_variant === 'hero' && count($search_rank_rows) > 5) {
    $list_class .= ' is-collapsed';
}
?>
<ol class="<?php echo htmlspecialchars($list_class); ?>"
    id="<?php echo htmlspecialchars($list_id); ?>"<?php echo $list_attrs !== '' ? ' ' . $list_attrs : ''; ?>>
    <?php foreach ($search_rank_rows as $sr_row):
        $rank   = (int) ($sr_row['rank'] ?? 0);
        $kw     = (string) ($sr_row['keyword'] ?? '');
        $trend  = (string) ($sr_row['trend'] ?? 'same');
        $tdelta = (int) ($sr_row['trend_delta'] ?? 0);
        $href   = search_rank_search_url($kw);
        $tlabel = search_rank_trend_display($trend, $tdelta);
        $taria  = search_rank_trend_aria($trend, $tdelta);
    ?>
        <li class="search-rank-item">
            <a href="<?php echo htmlspecialchars($href); ?>" class="search-rank-link">
                <span class="search-rank-num<?php echo $rank <= 3 ? ' is-top' : ''; ?>"
                      aria-hidden="true"><?php echo $rank; ?></span>
                <span class="search-rank-keyword"><?php echo htmlspecialchars($kw); ?></span>
                <?php if ($tlabel !== ''): ?>
                    <span class="search-rank-trend search-rank-trend--<?php echo htmlspecialchars($trend); ?>"
                          aria-label="<?php echo htmlspecialchars($taria); ?>"><?php echo htmlspecialchars($tlabel); ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ol>
