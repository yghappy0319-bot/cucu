<?php
require_once __DIR__ . '/lib/_function.php';
require_once __DIR__ . '/auction/lib/_auction.php';
require_once __DIR__ . '/lib/_search_rank.php';
require_once __DIR__ . '/lib/_community_meta.php';
require_once __DIR__ . '/lib/_member_public.php';

$community_categories_home = community_categories(false);

$notice_categories_home = [
    'general'     => '일반',
    'update'      => '업데이트',
    'event'       => '이벤트',
    'maintenance' => '점검',
];

$notice_home_rows = [];
if (db_table_exists('tb_notice')) {
    $__n_ad    = notice_table_has_no_ad_idx();
    $__ad_join = $__n_ad ? ' LEFT JOIN tb_admin a ON a.ad_idx = n.no_ad_idx' : '';
    $__ad_sel  = $__n_ad ? ', a.ad_name, a.ad_id' : '';
    $notice_home_rs = db_query("
        SELECT n.*, m.mb_nick{$__ad_sel}
        FROM tb_notice n
        LEFT JOIN tb_member m ON m.mb_idx = n.mb_idx
        {$__ad_join}
        WHERE n.no_status = 1
        ORDER BY n.no_is_pinned DESC, n.no_created_at DESC
        LIMIT 6
    ");
    while ($r = db_assoc($notice_home_rs)) {
        $notice_home_rows[] = $r;
    }
}

$community_home_rs = db_query("
    SELECT c.*, m.mb_nick
    FROM tb_community c
    LEFT JOIN tb_member m ON m.mb_idx = c.mb_idx
    WHERE c.co_status = 1
    ORDER BY c.co_idx DESC
    LIMIT 10
");
$community_home_rows = [];
while ($r = db_assoc($community_home_rs)) {
    $community_home_rows[] = $r;
}

$types_trade = [
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];
$deal_status_labels_home = [
    1 => ['label' => '판매중',   'class' => 'ds-active'],
    2 => ['label' => '거래중',   'class' => 'ds-reserved'],
    3 => ['label' => '거래완료', 'class' => 'ds-done'],
];

$home_trade_item_filters = [
    ''       => '전체',
    'single' => '싱글',
    'graded' => '등급',
    'box'    => '📦 미개봉박스',
];
$cur_home_item = isset($_GET['item']) ? trim($_GET['item']) : '';
if ($cur_home_item === 'card') {
    $cur_home_item = '';
}
if (!array_key_exists($cur_home_item, $home_trade_item_filters)) {
    $cur_home_item = '';
}

$trade_where_home = [trade_status_public_sql('t')];
if ($cur_home_item === 'box') {
    $trade_where_home[] = "t.tr_item_type = 'box'";
} elseif ($cur_home_item === 'single') {
    $trade_where_home[] = "t.tr_item_type = 'card' AND t.tr_card_kind = 'single'";
} elseif ($cur_home_item === 'graded') {
    $trade_where_home[] = "t.tr_item_type = 'card' AND t.tr_card_kind = 'graded'";
}
$trade_where_home_sql = implode(' AND ', $trade_where_home);

$trade_home_rs = db_query("
    SELECT t.*, m.mb_nick,
           (SELECT ti_path FROM tb_trade_image
             WHERE tr_idx = t.tr_idx
             ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path
    FROM tb_trade t
    LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
    WHERE {$trade_where_home_sql}
    ORDER BY t.tr_idx DESC
    LIMIT 10
");
$trade_home_rows = [];
while ($r = db_assoc($trade_home_rs)) {
    $trade_home_rows[] = $r;
}

$me_home = login_member();
$trade_has_wish = db_table_exists('tb_trade_like');
$trade_user_wishes_home = ($trade_has_wish && $me_home)
    ? trade_user_wished_map(array_column($trade_home_rows, 'tr_idx'), (int) $me_home['mb_idx'])
    : [];

$auction_home_rows            = [];
$auction_table_ok_home        = auction_table_ok();
$auction_status_labels_home   = auction_status_labels();
if ($auction_table_ok_home) {
    $thumb_sql = auction_image_table_ok()
        ? ', (SELECT ai_path FROM tb_auction_image
              WHERE au_idx = a.au_idx
              ORDER BY ai_order ASC, ai_idx ASC LIMIT 1) AS thumb_path'
        : ', NULL AS thumb_path';
    $auction_home_rs = db_query("
        SELECT a.*, m.mb_nick{$thumb_sql}
        FROM tb_auction a
        LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
        WHERE a.au_status = 1
          AND a.au_auction_status = 1
          AND a.au_starts_at <= NOW()
          AND a.au_ends_at > NOW()
        ORDER BY a.au_idx DESC
        LIMIT 4
    ");
    if ($auction_home_rs) {
        while ($r = db_assoc($auction_home_rs)) {
            $auction_home_rows[] = $r;
        }
    }
}

$home_shop_featured_rows = [];
if (db_table_exists('tb_shop_product')) {
    $__hsf = db_query("
        SELECT sp_idx, sp_name, sp_subtitle, sp_summary, sp_price, sp_original_price,
               sp_image_path, sp_shipping_free, sp_link
        FROM tb_shop_product
        WHERE sp_status = 1 AND sp_featured = 1
        ORDER BY sp_sort DESC, sp_idx DESC
        LIMIT 12
    ");
    if ($__hsf) {
        while ($r = db_assoc($__hsf)) {
            $home_shop_featured_rows[] = $r;
        }
    }
}

/**
 * @param array<string, mixed> $row
 */
$home_featured_shop_href = static function (array $row): string {
    $link = trim((string) ($row['sp_link'] ?? ''));
    if ($link !== '' && preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if ($link !== '' && isset($link[0]) && $link[0] === '/') {
        return $link;
    }
    return '/page/shop_view.php?idx=' . (int) ($row['sp_idx'] ?? 0);
};

$page  = 'home';
$title = ''; // 홈은 사이트명만 노출
$meta_description = '포켓몬 카드 거래 플랫폼 Pokazone - 안전한 검수 시스템과 실시간 시세로 카드·미개봉 박스를 사고 팔고 교환하세요. 오늘의 추천 카드와 TOP 10 시세를 확인해 보세요.';
$meta_keywords    = '포켓몬카드, 포켓몬 TCG, 포켓몬카드 시세, 포켓몬카드 거래, 포켓몬카드 중고, 리자몽, 피카츄, 뮤츠, 부스터박스, 포켓몬151';
$meta_type        = 'website';
$page_head_extra  = '<link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(SEO_SITE_NAME . ' 공지사항', ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars(seo_abs_url(public_url('/rss.xml')), ENT_QUOTES, 'UTF-8') . '">' . "\n";
$page_footer_extra = '';
if (!empty($auction_home_rows)) {
    $__auction_cd_js = __DIR__ . '/auction/assets/js/auction-countdown.js';
    $page_footer_extra = '<script src="/auction/assets/js/auction-countdown.js?v='
        . (is_file($__auction_cd_js) ? filemtime($__auction_cd_js) : time())
        . '" defer></script>';
}
$home_search_rank_rows = search_log_table_ready() ? search_rank_top(10) : [];
include __DIR__ . '/include/header.php';
?>

<!-- Hero -->
<section class="hero">
    <div class="container hero-inner">
        <div>
            <h1 class="hero-title">
                나만의 <span class="accent">포켓몬카드</span><br>
                여기서 거래하세요
            </h1>
            <p class="hero-desc">
                안전한 검수 시스템과 실시간 시세로<br>
                수집가와 트레이너가 만나는 가장 간편한 거래 플랫폼.
            </p>

            <div data-search-rank-root data-search-rank-auto-refresh="1" data-search-rank-collapse="1">
            <form class="hero-search" action="/page/search.php" method="get" role="search">
                <input type="search" name="q" placeholder="상품명, 세트명으로 검색해 보세요" aria-label="상품 검색" autocomplete="off">
                <button type="submit" class="btn btn-primary">검색</button>
            </form>

            <?php if (!empty($home_search_rank_rows)): ?>
            <div class="hero-search-rank">
                <div class="search-rank-panel">
                    <div class="search-rank-panel-head">
                        <div>
                            <h2>실시간 인기 검색어</h2>
                            <p>순위 24시간 · 변동 1시간 기준</p>
                        </div>
                        <span class="search-rank-updated" data-search-rank-updated aria-live="polite"></span>
                    </div>
                    <?php
                    $search_rank_rows = $home_search_rank_rows;
                    $search_rank_variant = 'hero';
                    include __DIR__ . '/include/search_rank_list.php';
                    ?>
                    <button type="button" class="search-rank-more" data-search-rank-more<?php echo count($home_search_rank_rows) > 5 ? '' : ' hidden'; ?>>더보기</button>
                </div>
            </div>
            <?php endif; ?>
            </div>

            <ul class="hero-stats">
                <li>
                    <strong>12,480</strong>
                    <span>등록된 카드</span>
                </li>
                <li>
                    <strong>8,210</strong>
                    <span>누적 거래</span>
                </li>
                <li>
                    <strong>3,500+</strong>
                    <span>활성 트레이너</span>
                </li>
            </ul>
        </div>

        <div class="hero-visual" aria-hidden="true">
            <div class="floating-card floating-card-1">
                <div class="emoji">⚡</div>
                <div>피카츄</div>
                <div class="rare">★★★★★ Rare</div>
            </div>
            <div class="floating-card floating-card-2">
                <div class="emoji">🔥</div>
                <div>리자몽</div>
                <div class="rare">★★★★★ Ultra</div>
            </div>
            <div class="floating-card floating-card-3">
                <div class="emoji">💧</div>
                <div>거북왕</div>
                <div class="rare">★★★★ Super</div>
            </div>
        </div>
    </div>
</section>



<!-- Trade board latest -->
<section class="section home-trade-latest" id="home-trade-latest">
    <div class="container">
        <div class="section-head">
            <h2>거래 최신글</h2>
            <?php
            $home_trade_more = '/trade/trade.php';
            if ($cur_home_item !== '') {
                $home_trade_more .= '?item=' . urlencode($cur_home_item);
            }
            ?>
            <a href="<?php echo htmlspecialchars($home_trade_more); ?>" class="link-more">전체보기 →</a>
        </div>

        <nav class="board-tabs board-tabs-sub home-trade-latest-tabs" aria-label="거래 최신글 유형">
            <?php foreach ($home_trade_item_filters as $key => $label):
                $tab_href = $key === '' ? '/#home-trade-latest' : '/?item=' . urlencode($key) . '#home-trade-latest';
            ?>
                <a href="<?php echo htmlspecialchars($tab_href); ?>"
                   class="board-tab <?php echo $cur_home_item === $key ? 'is-active' : ''; ?>"
                   <?php echo $cur_home_item === $key ? ' aria-current="true"' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($trade_home_rows)): ?>
            <div class="board-list">
                <div class="board-empty">
                    <div class="empty-emoji">🪪</div>
                    <p>아직 등록된 거래글이 없어요.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="trade-grid">
                <?php foreach ($trade_home_rows as $row):
                    $ds = $deal_status_labels_home[(int)$row['tr_deal_status']] ?? $deal_status_labels_home[1];
                    $ds['label'] = trade_deal_status_label((int)$row['tr_deal_status'], $row['tr_type'] ?? 'sell');
                    $ts = strtotime($row['tr_created_at']);
                    $is_box = $row['tr_item_type'] === 'box';
                    $img    = $row['thumb_path'] ?? '';
                ?>
                    <div class="trade-item <?php echo htmlspecialchars(trade_list_item_class($row), ENT_QUOTES, 'UTF-8'); ?>" data-tr-idx="<?php echo (int) $row['tr_idx']; ?>">
                        <?php if ($trade_has_wish && $me_home && (int) $row['tr_status'] === TRADE_STATUS_OK && (int) $row['tr_deal_status'] !== 3): ?>
                            <?php $wished_home = !empty($trade_user_wishes_home[(int) $row['tr_idx']]); ?>
                            <form action="/trade/proc/trade_like_proc.php" method="post" class="trade-wish-form">
                                <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
                                <button type="submit"
                                        class="trade-wish-btn <?php echo $wished_home ? 'is-wished' : ''; ?>"
                                        aria-label="<?php echo $wished_home ? '찜 해제' : '찜하기'; ?>"
                                        aria-pressed="<?php echo $wished_home ? 'true' : 'false'; ?>">
                                    <span aria-hidden="true"><?php echo $wished_home ? '♥' : '♡'; ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="/trade/trade_view.php?idx=<?php echo (int)$row['tr_idx']; ?>" class="trade-item-link">
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
                                <?php if ((int)$row['tr_image_count'] > 1): ?>
                                    <span class="trade-image-count">+<?php echo (int)$row['tr_image_count'] - 1; ?></span>
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
                                <?php if ($is_box && (int)$row['tr_box_qty'] > 1): ?>
                                    <span class="trade-qty">×<?php echo (int)$row['tr_box_qty']; ?></span>
                                <?php endif; ?>
                            </p>
                            <div class="trade-price">
                                <?php if ((int)$row['tr_price'] > 0): ?>
                                    <small>₩</small><?php echo number_format((int)$row['tr_price']); ?>
                                <?php else: ?>
                                    <span class="trade-price-free">가격제안</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        </a>
                        <div class="trade-foot trade-foot--meta">
                            <?php echo member_nick_link_html((int) ($row['mb_idx'] ?? 0), $row['mb_nick'] ?? null); ?>
                            <span>· <?php echo date(date('Y-m-d') === date('Y-m-d', $ts) ? 'H:i' : 'm/d', $ts); ?></span>
                            <span>· 조회 <?php echo number_format((int)$row['tr_views']); ?></span>
                            <?php
                            $inquiry_n = (int) ($row['tr_comments'] ?? 0);
                            if ($inquiry_n > 0):
                            ?>
                                <span class="trade-inquiry-count" title="문의">· 문의 <?php echo number_format($inquiry_n); ?></span>
                            <?php endif; ?>
                            <?php if ($trade_has_wish): ?>
                                <span class="trade-wish-count" title="찜"<?php echo (int) $row['tr_likes'] < 1 ? ' hidden' : ''; ?>>♥ <?php echo number_format((int) $row['tr_likes']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Community latest -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>커뮤니티 최신글</h2>
            <a href="/page/community.php" class="link-more">전체보기 →</a>
        </div>

        <div class="board-list">
            <div class="board-row is-head">
                <div class="col-num">번호</div>
                <div class="col-cat">분류</div>
                <div class="col-title">제목</div>
                <div class="col-author">작성자</div>
                <div class="col-date">작성일</div>
                <div class="col-views">조회</div>
            </div>

            <?php if (empty($community_home_rows)): ?>
                <div class="board-empty">
                    <div class="empty-emoji">📭</div>
                    <p>아직 등록된 게시글이 없어요.</p>
                </div>
            <?php else: ?>
                <?php foreach ($community_home_rows as $row):
                    $cat_k = $row['co_category'];
                    $cat_l = $community_categories_home[$cat_k] ?? community_category_label((string) $cat_k);
                    $is_ext = community_is_external_cafe_post($row);
                    $ext_url = trim((string) ($row['co_external_url'] ?? ''));
                    $row_href = ($is_ext && $ext_url !== '')
                        ? $ext_url
                        : ('/page/community_view.php?idx=' . (int)$row['co_idx']);
                    $row_attrs = ($is_ext && $ext_url !== '')
                        ? ' target="_blank" rel="noopener noreferrer"'
                        : '';
                ?>
                    <a href="<?php echo htmlspecialchars($row_href); ?>" class="board-row"<?php echo $row_attrs; ?>>
                        <div class="col-num"><?php echo (int)$row['co_idx']; ?></div>
                        <div class="col-cat">
                            <span class="cat-badge cat-<?php echo htmlspecialchars($cat_k); ?>"><?php echo htmlspecialchars($cat_l); ?></span>
                        </div>
                        <div class="col-title">
                            <span class="board-subject"><?php echo htmlspecialchars($row['co_title']); ?></span>
                            <?php if ($is_ext): ?>
                                <span class="board-ext" title="네이버 카페 원문">↗</span>
                            <?php endif; ?>
                            <?php if ((int)$row['co_comments'] > 0): ?>
                                <span class="board-comments">[<?php echo (int)$row['co_comments']; ?>]</span>
                            <?php endif; ?>
                            <?php if (strtotime($row['co_created_at']) > time() - 60 * 60 * 24): ?>
                                <span class="board-new">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-author"><?php echo htmlspecialchars(community_display_author($row)); ?></div>
                        <div class="col-date">
                            <?php
                            $ts = strtotime($row['co_created_at']);
                            echo date(date('Y-m-d') === date('Y-m-d', $ts) ? 'H:i' : 'Y-m-d', $ts);
                            ?>
                        </div>
                        <div class="col-views"><?php echo number_format((int)$row['co_views']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($auction_table_ok_home): ?>
<!-- Auction live -->
<section class="section home-auction-latest">
    <div class="container">
        <div class="section-head">
            <h2>진행 중인 경매</h2>
            <a href="/auction/auction.php?status=live" class="link-more">전체보기 →</a>
        </div>

        <?php if (empty($auction_home_rows)): ?>
            <div class="board-list">
                <div class="board-empty">
                    <div class="empty-emoji" aria-hidden="true">🔨</div>
                    <p>지금 진행 중인 경매가 없어요.</p>
                    <p style="margin-top:12px"><a href="/auction/auction.php" class="btn btn-outline btn-sm">경매 둘러보기</a></p>
                </div>
            </div>
        <?php else: ?>
            <div class="trade-grid auction-grid">
                <?php foreach ($auction_home_rows as $row):
                    $display   = auction_display_status($row);
                    $st        = $auction_status_labels_home[$display] ?? $auction_status_labels_home['live'];
                    $ends_ts   = strtotime((string) $row['au_ends_at']);
                    $remain    = auction_format_remain($ends_ts);
                    $is_ended  = $display === 'ended';
                    $is_ending = $display === 'ending';
                    $is_box    = ($row['au_item_type'] ?? '') === 'box';
                    $img       = $row['thumb_path'] ?? '';
                    $ck_lbl    = auction_category_label($row);
                    $cat       = $row['au_card_kind'] ?? ($is_box ? 'box' : 'card');
                    ?>
                    <a href="/auction/auction_view.php?idx=<?php echo (int) $row['au_idx']; ?>"
                       class="trade-item auction-item<?php echo $is_ended ? ' is-done' : ''; ?>">
                        <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
                            <div class="trade-thumb-tags">
                                <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                                    <?php echo $is_box ? '📦 상자' : '카드'; ?>
                                </span>
                                <?php if ($ck_lbl !== '카드'): ?>
                                    <span class="item-kind-chip item-<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($ck_lbl); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="deal-status <?php echo htmlspecialchars($st['class']); ?>">
                                <?php echo htmlspecialchars($st['label']); ?>
                            </span>
                            <?php if ($img): ?>
                                <img class="trade-image"
                                     src="<?php echo htmlspecialchars(public_url($img)); ?>"
                                     alt="<?php echo htmlspecialchars($row['au_card_name']); ?>"
                                     loading="lazy" decoding="async">
                            <?php elseif ($is_box): ?>
                                <span class="trade-emoji" aria-hidden="true">📦</span>
                            <?php else: ?>
                                <span class="trade-placeholder-tcg" aria-hidden="true">
                                    <span class="trade-placeholder-tcg-inner"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="trade-body">
                            <p class="trade-title"><?php echo htmlspecialchars($row['au_title']); ?></p>
                            <p class="trade-card"><?php echo htmlspecialchars($row['au_card_name']); ?></p>
                            <div class="auction-price-block">
                                <span class="auction-price-label">현재가</span>
                                <div class="trade-price auction-current-price">
                                    <small>₩</small><?php echo number_format((int) $row['au_current_price']); ?>
                                </div>
                                <span class="auction-start-price">시작가 ₩<?php echo number_format((int) $row['au_start_price']); ?></span>
                            </div>
                            <div class="auction-item-meta">
                                <span class="auction-meta-bids">입찰 <?php echo number_format((int) $row['au_bid_count']); ?>회</span>
                                <span class="auction-meta-time<?php echo $is_ending ? ' is-urgent' : ''; ?><?php echo $is_ended ? ' is-ended' : ''; ?>"
                                      <?php if (!$is_ended && $ends_ts > 0): ?>
                                          data-auction-ends="<?php echo (int) $ends_ts; ?>"
                                      <?php endif; ?>>
                                    <?php echo htmlspecialchars($remain); ?>
                                </span>
                            </div>
                            <div class="trade-foot">
                                <span><?php echo htmlspecialchars($row['mb_nick'] ?? '(탈퇴)'); ?></span>
                                <span>· 조회 <?php echo number_format((int) $row['au_views']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>


<!-- Trending prices -->
<section class="section trending"  style="display:none;" >
    <div class="container">
        <div class="section-head">
            <h2>실시간 시세 TOP 10</h2>
            <a href="/page/market_price.php" class="link-more">시세 전체보기 →</a>
        </div>

        <div class="trend-list">
            <div class="trend-row is-head">
                <div class="rank">#</div>
                <div>카드</div>
                <div class="trend-price">평균가</div>
                <div class="trend-delta">변동</div>
                <div class="trend-volume">거래량</div>
            </div>

            <?php
            $trend = [
                ['rank'=>1,  'name'=>'리자몽 ex SAR',       'set'=>'포켓몬151',       'price'=>'320,000', 'delta'=>'+4.2%', 'up'=>true,  'vol'=>'48건'],
                ['rank'=>2,  'name'=>'뮤츠 ex SAR',          'set'=>'포켓몬151',       'price'=>'210,000', 'delta'=>'+2.1%', 'up'=>true,  'vol'=>'31건'],
                ['rank'=>3,  'name'=>'피카츄 ex SAR',       'set'=>'포켓몬151',       'price'=>'185,000', 'delta'=>'-1.8%', 'up'=>false, 'vol'=>'22건'],
                ['rank'=>4,  'name'=>'갸라도스 ex SAR',   'set'=>'포켓몬151',       'price'=>'142,000', 'delta'=>'+3.5%', 'up'=>true,  'vol'=>'26건'],
                ['rank'=>5,  'name'=>'이상해꽃 ex SR',       'set'=>'포켓몬151',       'price'=>'92,000',  'delta'=>'-0.5%', 'up'=>false, 'vol'=>'14건'],
                ['rank'=>6,  'name'=>'거북왕 ex SR',         'set'=>'포켓몬151',       'price'=>'88,500',  'delta'=>'+1.2%', 'up'=>true,  'vol'=>'19건'],
                ['rank'=>7,  'name'=>'이브이 RR',              'set'=>'야성의 힘',       'price'=>'46,000',  'delta'=>'+0.8%', 'up'=>true,  'vol'=>'12건'],
                ['rank'=>8,  'name'=>'나옹 프로모',             'set'=>'프로모',              'price'=>'24,000',  'delta'=>'-2.3%', 'up'=>false, 'vol'=>'8건'],
            ];
            foreach ($trend as $t) :
            ?>
            <div class="trend-row">
                <div class="rank <?php echo $t['rank'] <= 3 ? 'rank-top' : ''; ?>"><?php echo $t['rank']; ?></div>
                <div class="trend-name">
                    <strong><?php echo $t['name']; ?></strong>
                    <span><?php echo $t['set']; ?></span>
                </div>
                <div class="trend-price">₩<?php echo $t['price']; ?></div>
                <div class="trend-delta <?php echo $t['up'] ? 'up' : 'down'; ?>"><?php echo $t['delta']; ?></div>
                <div class="trend-volume"><?php echo $t['vol']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- 오늘의 추천 상품 (관리자 추천 지정 + 노출 상품) -->
<section class="section section-shop-prep home-featured-shop" aria-labelledby="home-featured-shop-title">
    <div class="container">
        <div class="section-head">
            <div class="home-featured-shop-intro">
                <p class="home-featured-kicker">PICK OF THE DAY</p>
                <h2 id="home-featured-shop-title">오늘의 추천 상품</h2>
                <!-- <p class="home-featured-lead">포카존에서 엄선한 쇼핑몰 상품입니다. 할인·배송 조건은 상세 페이지에서 확인해 주세요.</p> -->
            </div>
            <a href="/page/shop.php" class="link-more">쇼핑몰 →</a>
        </div>

        <?php if (empty($home_shop_featured_rows)): ?>
            <p class="home-featured-empty">
                아직 추천으로 표시할 상품이 없습니다.
                <a href="/page/shop.php">쇼핑몰 전체</a>에서 상품을 둘러보세요.
            </p>
        <?php else: ?>
        <div class="home-featured-marquee" aria-label="추천 상품 목록">
            <ul class="home-featured-cards">
                <?php
                $feat_grad = ['home-featured-thumb--a', 'home-featured-thumb--b', 'home-featured-thumb--c', 'home-featured-thumb--d'];
                foreach ($home_shop_featured_rows as $fi => $frow):
                    $fhref = $home_featured_shop_href($frow);
                    $fimg  = trim((string) ($frow['sp_image_path'] ?? ''));
                    $fext  = $fhref !== '' && (bool) preg_match('#^https?://#i', $fhref);
                    $fgrad = $feat_grad[$fi % 4];
                    $thumb_cls = 'home-featured-thumb ' . ($fimg !== '' ? 'is-photo ' : '') . $fgrad;
                    $fprice = (int) $frow['sp_price'];
                    $forig  = isset($frow['sp_original_price']) ? (int) $frow['sp_original_price'] : 0;
                    $fextra = trim((string) ($frow['sp_subtitle'] ?? ''));
                    if ($fextra === '') {
                        $fextra = !empty($frow['sp_shipping_free']) ? '무료배송' : '배송 조건 문의';
                    }
                    ?>
                <li>
                    <a href="<?php echo htmlspecialchars($fhref, ENT_QUOTES, 'UTF-8'); ?>"
                       class="home-featured-card"
                        <?php echo $fext ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <div class="<?php echo htmlspecialchars($thumb_cls, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($fi === 0): ?>
                                <span class="home-featured-badge">추천</span>
                            <?php endif; ?>
                            <span class="home-featured-src">스토어</span>
                            <?php if ($fimg !== ''): ?>
                                <img src="<?php echo htmlspecialchars(public_url($fimg), ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars((string) $frow['sp_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     loading="lazy" decoding="async">
                            <?php endif; ?>
                        </div>
                        <div class="home-featured-meta">
                            <h3 class="home-featured-name"><?php echo htmlspecialchars((string) $frow['sp_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if ($fprice > 0): ?>
                                <p class="home-featured-price">
                                    <?php if ($forig > $fprice): ?>
                                        <span class="home-featured-original"><small>₩</small><?php echo number_format($forig); ?></span>
                                    <?php endif; ?>
                                    <small>₩</small><?php echo number_format($fprice); ?>
                                </p>
                            <?php else: ?>
                                <p class="home-featured-price home-featured-price--inquiry">가격 문의</p>
                            <?php endif; ?>
                            <p class="home-featured-extra"><?php echo htmlspecialchars($fextra, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="container">
        <div class="cta-box">
            <div>
                <h2>나만 가지고 있던 그 카드,<br>Pokazone에서 거래해보세요</h2>
                <p>등록은 무료, 안전 거래 시스템으로 걱정 없이.</p>
            </div>
            <a href="/page/register.php" class="btn btn-primary">지금 시작하기</a>
        </div>
    </div>
</section>

<?php if (!empty($home_search_rank_rows)): ?>
<!-- 모바일: 실시간 인기 검색어 (페이지 하단) -->
<section class="section home-search-rank-mobile" aria-label="실시간 인기 검색어">
    <div class="container" data-search-rank-root data-search-rank-auto-refresh="1">
        <div class="search-rank-panel">
            <div class="search-rank-panel-head">
                <div>
                    <h2>실시간 인기 검색어</h2>
                    <p>순위 24시간 · 변동 1시간 기준</p>
                </div>
                <span class="search-rank-updated" data-search-rank-updated aria-live="polite"></span>
            </div>
            <?php
            $search_rank_rows = $home_search_rank_rows;
            $search_rank_variant = 'home-mobile';
            include __DIR__ . '/include/search_rank_list.php';
            ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/include/footer.php'; ?>
