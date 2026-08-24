<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_search_rank.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$q_blocked = ($q !== '' && search_keyword_is_blocked($q));

if ($q !== '' && !$q_blocked) {
    $me_search_log = login_member();
    search_log_keyword($q, $me_search_log ? (int) $me_search_log['mb_idx'] : null);
}

$search_rank_rows = search_log_table_ready() ? search_rank_top(10) : [];

$shop_limit  = 24;
$trade_limit = 24;

$types = [
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];
$deal_status_labels = [
    1 => ['label' => '판매중',   'class' => 'ds-active'],
    2 => ['label' => '거래중',   'class' => 'ds-reserved'],
    3 => ['label' => '거래완료', 'class' => 'ds-done'],
];

$shop_href = function (array $row): string {
    $link = trim((string) ($row['sp_link'] ?? ''));
    if ($link !== '' && preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if ($link !== '' && isset($link[0]) && $link[0] === '/') {
        return $link;
    }
    return '/page/shop_view.php?idx=' . (int) ($row['sp_idx'] ?? 0);
};

$shop_rows = [];
$trade_rows = [];

if ($q !== '' && !$q_blocked) {
    if (db_table_exists('tb_shop_product')) {
        $kw  = db_escape($q);
        $sql = "
            SELECT sp_idx, sp_name, sp_summary, sp_price, sp_image_path, sp_link
            FROM tb_shop_product
            WHERE sp_status = 1
              AND (
                    sp_name LIKE '%{$kw}%'
                 OR IFNULL(sp_summary, '') LIKE '%{$kw}%'
                 OR IFNULL(sp_subtitle, '') LIKE '%{$kw}%'
              )
            ORDER BY sp_idx DESC
            LIMIT {$shop_limit}
        ";
        $rs = db_query($sql);
        if ($rs) {
            while ($r = db_assoc($rs)) {
                $shop_rows[] = $r;
            }
        }
    }

    $kw = db_escape($q);
    $where = trade_status_public_sql('t') . " AND (
        t.tr_title LIKE '%{$kw}%' OR t.tr_card_name LIKE '%{$kw}%' OR t.tr_content LIKE '%{$kw}%'
    )";
    $sql = "
        SELECT t.*, m.mb_nick,
               (SELECT ti_path FROM tb_trade_image
                 WHERE tr_idx = t.tr_idx
                 ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path
        FROM tb_trade t
        LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
        WHERE {$where}
        ORDER BY t.tr_idx DESC
        LIMIT {$trade_limit}
    ";
    $rs = db_query($sql);
    if ($rs) {
        while ($r = db_assoc($rs)) {
            $trade_rows[] = $r;
        }
    }
}

$me_search = login_member();
$search_has_wish = db_table_exists('tb_trade_like');
$search_user_wishes = ($search_has_wish && $me_search)
    ? trade_user_wished_map(array_column($trade_rows, 'tr_idx'), (int) $me_search['mb_idx'])
    : [];

$page  = 'search';
$title = $q !== '' ? '상품 검색: ' . $q : '상품 검색';
$meta_description = '쇼핑몰 상품과 거래게시판 매물을 한 번에 검색합니다. 포켓몬 카드·미개봉 박스 키워드로 찾아보세요.';
$meta_keywords    = '포켓몬카드 검색, 포켓몬 TCG, 쇼핑몰, 거래게시판, Pokazone';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '상품 검색', 'url' => '/page/search.php'],
];

include __DIR__ . '/../include/header.php';
?>

<section class="search-unified community">
    <div class="container">
        <header class="search-page-head board-head">
            <div>
                <h1 class="board-title">상품 검색</h1>
                <p class="board-desc">쇼핑몰 등록 상품과 거래게시판 글을 함께 찾습니다.</p>
            </div>
        </header>

        <form class="board-search search-page-form" method="get" action="/page/search.php" role="search">
            <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>"
                   placeholder="상품명·세트명·카드명으로 검색" aria-label="상품 검색어">
            <button type="submit" class="btn btn-outline btn-sm">검색</button>
        </form>

        <?php if ($q === ''): ?>
            <?php if (!empty($search_rank_rows)): ?>
            <section class="search-page-rank" aria-labelledby="search-rank-title" data-search-rank-root data-search-rank-auto-refresh="1">
                <div class="search-rank-panel">
                    <div class="search-rank-panel-head">
                        <div>
                            <h2 id="search-rank-title">실시간 인기 검색어</h2>
                            <p>순위 24시간 · 변동 1시간 기준 · 클릭하면 바로 검색합니다</p>
                        </div>
                        <span class="search-rank-updated" data-search-rank-updated aria-live="polite"></span>
                    </div>
                    <?php
                    $search_rank_variant = 'page';
                    include __DIR__ . '/../include/search_rank_list.php';
                    ?>
                </div>
            </section>
            <?php endif; ?>
            <div class="search-empty-hint policy-card">
                <p class="policy-lead" style="margin:0">
                    검색어를 입력한 뒤 <strong>검색</strong>을 눌러 주세요. 쇼핑몰·거래게시판 결과가 한 페이지에 표시됩니다.
                </p>
            </div>
        <?php elseif ($q_blocked): ?>
            <div class="search-block-empty policy-card alert-soft">
                <p class="policy-lead" style="margin:0">검색할 수 없는 키워드입니다.</p>
            </div>
        <?php else: ?>

            <section class="search-section" aria-labelledby="search-trade-heading">
                <div class="search-section-head">
                    <h2 id="search-trade-heading">거래게시판</h2>
                    <span class="search-section-count"><?php echo count($trade_rows); ?>건</span>
                </div>
                <?php if (empty($trade_rows)): ?>
                    <div class="search-block-empty policy-card">
                        <p class="policy-lead" style="margin:0">
                            이 키워드에 맞는 거래글이 없습니다.
                            <a href="/trade/trade_write.php">거래글 등록</a> 또는
                            <a href="/trade/trade.php?q=<?php echo urlencode($q); ?>">거래게시판에서 필터 검색</a>을 이용해 보세요.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="trade-grid">
                        <?php foreach ($trade_rows as $row):
                            $ds         = $deal_status_labels[(int) $row['tr_deal_status']] ?? $deal_status_labels[1];
                            $ds['label'] = trade_deal_status_label((int) $row['tr_deal_status'], $row['tr_type'] ?? 'sell');
                            $type_label = $types[$row['tr_type']] ?? '판매';
                            $ts         = strtotime($row['tr_created_at']);
                            $is_box     = $row['tr_item_type'] === 'box';
                            $img        = $row['thumb_path'] ?? '';
                            $sck        = !$is_box && !empty($row['tr_card_kind']) ? $row['tr_card_kind'] : '';
                            $sck_lbl    = $sck === 'graded' ? '등급' : ($sck === 'single' ? '싱글' : '');
                        ?>
                            <div class="trade-item <?php echo htmlspecialchars(trade_list_item_class($row), ENT_QUOTES, 'UTF-8'); ?>" data-tr-idx="<?php echo (int) $row['tr_idx']; ?>">
                                <?php if ($search_has_wish && $me_search && (int) $row['tr_status'] === TRADE_STATUS_OK && (int) $row['tr_deal_status'] !== 3): ?>
                                    <?php $wished_search = !empty($search_user_wishes[(int) $row['tr_idx']]); ?>
                                    <form action="/trade/proc/trade_like_proc.php" method="post" class="trade-wish-form">
                                        <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
                                        <button type="submit"
                                                class="trade-wish-btn <?php echo $wished_search ? 'is-wished' : ''; ?>"
                                                aria-label="<?php echo $wished_search ? '찜 해제' : '찜하기'; ?>"
                                                aria-pressed="<?php echo $wished_search ? 'true' : 'false'; ?>">
                                            <span aria-hidden="true"><?php echo $wished_search ? '♥' : '♡'; ?></span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="/trade/trade_view.php?idx=<?php echo (int) $row['tr_idx']; ?>" class="trade-item-link">
                                <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
                                    <div class="trade-thumb-tags">
                                        <span class="trade-type type-<?php echo htmlspecialchars($row['tr_type']); ?>">
                                            <?php echo htmlspecialchars($type_label); ?>
                                        </span>
                                        <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                                            <?php echo $is_box ? '📦 상자' : '카드'; ?>
                                        </span>
                                        <?php if ($sck_lbl !== ''): ?>
                                            <span class="item-kind-chip item-<?php echo htmlspecialchars($sck); ?>"><?php echo htmlspecialchars($sck_lbl); ?></span>
                                        <?php endif; ?>
                                    </div>
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
                                        <?php if (!empty($row['tr_grade'])): ?>
                                            <span class="trade-grade"><?php echo htmlspecialchars($row['tr_grade']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="trade-price">
                                        <?php if ((int) $row['tr_price'] > 0): ?>
                                            <small>₩</small><?php echo number_format((int) $row['tr_price']); ?>
                                        <?php else: ?>
                                            <span class="trade-price-free">가격제안</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="trade-foot">
                                        <span><?php echo htmlspecialchars($row['mb_nick'] ?? '(탈퇴)'); ?></span>
                                        <span>· <?php echo date(date('Y-m-d') === date('Y-m-d', $ts) ? 'H:i' : 'm/d', $ts); ?></span>
                                        <span>· 조회 <?php echo number_format((int) $row['tr_views']); ?></span>
                                        <?php
                                        $inquiry_n = (int) ($row['tr_comments'] ?? 0);
                                        if ($inquiry_n > 0):
                                        ?>
                                            <span class="trade-inquiry-count" title="문의">· 문의 <?php echo number_format($inquiry_n); ?></span>
                                        <?php endif; ?>
                                        <?php if ($search_has_wish): ?>
                                            <span class="trade-wish-count" title="찜"<?php echo (int) $row['tr_likes'] < 1 ? ' hidden' : ''; ?>>♥ <?php echo number_format((int) $row['tr_likes']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($trade_rows) >= $trade_limit): ?>
                        <p class="search-more-link">
                            <a href="/trade/trade.php?q=<?php echo urlencode($q); ?>" class="btn btn-outline btn-sm">거래게시판에서 더 보기</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="search-section" aria-labelledby="search-shop-heading">
                <div class="search-section-head">
                    <h2 id="search-shop-heading">쇼핑몰</h2>
                    <span class="search-section-count"><?php echo count($shop_rows); ?>건</span>
                </div>
                <?php if (empty($shop_rows)): ?>
                    <div class="search-block-empty policy-card">
                        <p class="policy-lead" style="margin:0">
                            이 키워드에 맞는 쇼핑몰 상품이 없습니다.
                            <a href="/page/shop.php">쇼핑몰 안내</a>를 확인해 보세요.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="trade-grid">
                        <?php foreach ($shop_rows as $row):
                            $href = $shop_href($row);
                            $img  = $row['sp_image_path'] ?? '';
                        ?>
                            <a href="<?php echo htmlspecialchars($href); ?>" class="trade-item shop-result-item">
                                <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
                                    <span class="search-source-badge search-source-shop">쇼핑몰</span>
                                    <?php if ($img): ?>
                                        <img class="trade-image"
                                             src="<?php echo htmlspecialchars(public_url($img)); ?>"
                                             alt="<?php echo htmlspecialchars($row['sp_name']); ?>"
                                             loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="trade-emoji" aria-hidden="true">🛒</span>
                                    <?php endif; ?>
                                </div>
                                <div class="trade-body">
                                    <p class="trade-title"><?php echo htmlspecialchars($row['sp_name']); ?></p>
                                    <?php if (!empty($row['sp_summary'])): ?>
                                        <p class="trade-card"><?php echo htmlspecialchars($row['sp_summary']); ?></p>
                                    <?php endif; ?>
                                    <div class="trade-price">
                                        <?php if ((int) $row['sp_price'] > 0): ?>
                                            <small>₩</small><?php echo number_format((int) $row['sp_price']); ?>
                                        <?php else: ?>
                                            <span class="trade-price-free">가격 문의</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($shop_rows) >= $shop_limit): ?>
                        <p class="search-more-link">
                            <a href="/page/shop.php?q=<?php echo urlencode($q); ?>" class="btn btn-outline btn-sm">쇼핑몰에서 더 보기</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
