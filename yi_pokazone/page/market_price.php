<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_pokemon_market.php';

$q = trim((string) ($_GET['q'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'sort'));
$allowed_sorts = ['sort', 'deals', 'price_low', 'price_high'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'sort';
}

$idx = max(0, (int) ($_GET['idx'] ?? 0));
$detail = null;
$detail_stats = null;
if ($idx > 0) {
    $detail = pokemon_market_find_by_idx($idx, true);
    if ($detail) {
        $detail_stats = pokemon_market_summary_for_name((string) $detail['pm_name'], 50);
    }
}

$ready = pokemon_market_table_ready();
$list = ($ready && !$detail) ? pokemon_market_list_with_stats($q !== '' ? $q : null, $sort) : [];

$build_url = static function (array $overrides = []) use ($q, $sort): string {
    $params = ['q' => $q, 'sort' => $sort];
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (($params['q'] ?? '') === '') {
        unset($params['q']);
    }
    if (($params['sort'] ?? '') === 'sort') {
        unset($params['sort']);
    }
    return '/page/market_price.php' . ($params ? '?' . http_build_query($params) : '');
};

$page  = 'market_price';
$title = $detail
    ? ((string) $detail['pm_name'] . ' 시세')
    : '전체 시세';
$meta_description = '포카존 거래게시판 판매완료 가격으로 집계한 포켓몬별 중고 시세입니다. 등급·레어도 구분 없이 카드명 기준으로 확인할 수 있습니다.';
$meta_keywords = '포켓몬카드 시세, 중고 시세, 포켓몬 카드 거래, 전체 시세, Pokazone';
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '전체 시세', 'url' => '/page/market_price.php'],
];
if ($detail) {
    $meta_breadcrumb[] = [
        'name' => (string) $detail['pm_name'],
        'url'  => '/page/market_price.php?idx=' . (int) $detail['pm_idx'],
    ];
}
$meta_canonical = $detail
    ? '/page/market_price.php?idx=' . (int) $detail['pm_idx']
    : '/page/market_price.php';

include __DIR__ . '/../include/header.php';
?>

<section class="community card-price-page market-price-page">
    <div class="container">
        <header class="board-head shop-store-head card-price-head">
            <div class="card-price-head-copy">
                <p class="card-price-kicker">거래게시판 · 판매완료</p>
                <h1 class="board-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="board-desc">
                    관리자가 등록한 포켓몬 기준으로, 거래게시판 <strong>판매중</strong>·<strong>판매완료</strong> 가격을 모아 중고 시세를 만듭니다.
                    제목 또는 카드명에 등록명이 <strong>포함</strong>되면 집계하며, 레어도·등급은 구분하지 않습니다.
                </p>
            </div>
        </header>

        <aside class="snkrdunk-price-disclaimer" aria-label="시세 안내">
            <strong>시세 안내</strong>
            <p>
                <strong>판매중</strong>은 현재 올라온 희망가이고,
                <strong>판매완료</strong>는 결제·구매확정 체결가(없으면 거래완료 희망가)입니다.
                표시 가격은 참고용이며 실시간 매물·상태를 보장하지 않습니다.
            </p>
        </aside>

        <?php if ($detail && $detail_stats): ?>
            <p class="market-price-back">
                <a href="<?php echo htmlspecialchars($build_url(), ENT_QUOTES, 'UTF-8'); ?>" class="link-more">← 전체 시세</a>
                <a href="/trade/trade.php?q=<?php echo urlencode((string) $detail['pm_name']); ?>&amp;active=1" class="link-more">거래게시판에서 보기 →</a>
            </p>

            <div class="market-price-summary market-price-summary--dual" aria-label="<?php echo htmlspecialchars((string) $detail['pm_name'], ENT_QUOTES, 'UTF-8'); ?> 시세 요약">
                <div>
                    <span class="snkrdunk-price-label">판매중 최저</span>
                    <strong><?php echo (int) $detail_stats['asking_min'] > 0 ? '₩' . number_format((int) $detail_stats['asking_min']) : '—'; ?></strong>
                </div>
                <div>
                    <span class="snkrdunk-price-label">판매중 평균</span>
                    <strong><?php echo (int) $detail_stats['asking_avg'] > 0 ? '₩' . number_format((int) $detail_stats['asking_avg']) : '—'; ?></strong>
                </div>
                <div>
                    <span class="snkrdunk-price-label">판매중 매물</span>
                    <strong><?php echo number_format((int) $detail_stats['asking_count']); ?>건</strong>
                </div>
                <div>
                    <span class="snkrdunk-price-label">판매완료 평균</span>
                    <strong><?php echo (int) $detail_stats['avg'] > 0 ? '₩' . number_format((int) $detail_stats['avg']) : '—'; ?></strong>
                </div>
                <div>
                    <span class="snkrdunk-price-label">판매완료 최저</span>
                    <strong><?php echo (int) $detail_stats['min'] > 0 ? '₩' . number_format((int) $detail_stats['min']) : '—'; ?></strong>
                </div>
                <div>
                    <span class="snkrdunk-price-label">판매완료</span>
                    <strong><?php echo number_format((int) $detail_stats['count']); ?>건</strong>
                </div>
            </div>

            <?php if (($detail_stats['source'] ?? '') === 'listed' && (int) $detail_stats['count'] > 0): ?>
                <p class="market-price-source-note">결제 체결 데이터가 없어, 거래완료 희망가를 참고로 표시합니다.</p>
            <?php endif; ?>

            <?php
            $render_market_deal = static function (array $item): void {
                $thumb = trim((string) ($item['thumb_path'] ?? ''));
                $thumb_url = ($thumb !== '' && function_exists('public_url'))
                    ? public_url($thumb)
                    : $thumb;
                ?>
                <a class="market-price-deal" role="listitem"
                   href="<?php echo htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="market-price-deal-thumb<?php echo $thumb_url !== '' ? ' has-image' : ''; ?>" aria-hidden="true">
                        <?php if ($thumb_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($thumb_url, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" loading="lazy" decoding="async"
                                 onerror="this.parentElement.classList.remove('has-image'); this.remove();">
                        <?php endif; ?>
                    </div>
                    <div class="market-price-deal-body">
                        <div class="market-price-deal-main">
                            <strong>₩<?php echo number_format((int) $item['sold_price']); ?></strong>
                            <span class="market-price-deal-source"><?php echo htmlspecialchars((string) $item['source_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="market-price-deal-meta">
                            <?php if (!empty($item['meta'])): ?>
                                <span><?php echo htmlspecialchars((string) $item['meta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['sold_at'])): ?>
                                <span><?php echo htmlspecialchars((string) $item['sold_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php
            };
            ?>

            <h2 class="market-price-section-title">판매중 매물</h2>
            <?php if (empty($detail_stats['asking_items'])): ?>
                <div class="shop-prep-state card-price-empty market-price-empty-block">
                    <p class="shop-prep-title">판매중 매물이 없습니다</p>
                    <p class="shop-prep-desc">현재 올라온 판매글이 없으면 여기에 표시되지 않습니다.</p>
                </div>
            <?php else: ?>
                <div class="market-price-deals" role="list">
                    <?php foreach ($detail_stats['asking_items'] as $item) {
                        $render_market_deal($item);
                    } ?>
                </div>
            <?php endif; ?>

            <h2 class="market-price-section-title">판매완료</h2>
            <?php if (empty($detail_stats['items'])): ?>
                <div class="shop-prep-state card-price-empty market-price-empty-block">
                    <p class="shop-prep-title">아직 판매완료 거래가 없습니다</p>
                    <p class="shop-prep-desc">거래가 쌓이면 시세가 표시됩니다.</p>
                </div>
            <?php else: ?>
                <div class="market-price-deals" role="list">
                    <?php foreach ($detail_stats['items'] as $item) {
                        $render_market_deal($item);
                    } ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <form class="board-search shop-search card-price-search snkrdunk-price-search"
                  method="get" action="/page/market_price.php" role="search">
                <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="포켓몬명 검색" aria-label="전체 시세 검색">
                <select name="sort" class="card-price-site-select" aria-label="정렬">
                    <option value="sort"<?php echo $sort === 'sort' ? ' selected' : ''; ?>>등록 순</option>
                    <option value="deals"<?php echo $sort === 'deals' ? ' selected' : ''; ?>>거래 많은 순</option>
                    <option value="price_low"<?php echo $sort === 'price_low' ? ' selected' : ''; ?>>낮은 평균가 순</option>
                    <option value="price_high"<?php echo $sort === 'price_high' ? ' selected' : ''; ?>>높은 평균가 순</option>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">적용</button>
            </form>

            <?php if (!$ready): ?>
                <div class="shop-prep-state card-price-empty">
                    <p class="shop-prep-title">시세 데이터를 준비하고 있습니다</p>
                    <p class="shop-prep-desc">관리자에서 포켓몬명을 등록하면 전체 시세가 표시됩니다.</p>
                </div>
            <?php elseif (!$list): ?>
                <div class="shop-prep-state card-price-empty">
                    <p class="shop-prep-title">등록된 포켓몬이 없습니다</p>
                    <p class="shop-prep-desc">
                        <?php if ($q !== ''): ?>
                            다른 이름으로 검색해 보세요. <a href="/page/market_price.php">전체 보기</a>
                        <?php else: ?>
                            관리자가 포켓몬명을 등록하면 여기에 나타납니다.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="shop-store-toolbar card-price-toolbar">
                    <span class="shop-store-count"><?php echo number_format(count($list)); ?>종</span>
                    <?php if ($q !== ''): ?>
                        <span class="shop-store-query">&ldquo;<?php echo htmlspecialchars($q); ?>&rdquo; 검색 결과</span>
                        <a href="<?php echo htmlspecialchars($build_url(['q' => null]), ENT_QUOTES, 'UTF-8'); ?>"
                           class="link-more">검색 초기화</a>
                    <?php endif; ?>
                </div>

                <div class="snkrdunk-price-grid market-price-grid" role="list">
                    <?php foreach ($list as $row):
                        $href = '/page/market_price.php?idx=' . (int) $row['pm_idx'];
                        $name = (string) $row['pm_name'];
                        $avg = (int) $row['sold_avg'];
                        $ask_min = (int) $row['asking_min'];
                        $ask_cnt = (int) $row['asking_count'];
                        $cnt = (int) $row['sold_count'];
                        ?>
                        <a class="snkrdunk-price-card market-price-card" role="listitem"
                           href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="snkrdunk-price-card-icon" aria-hidden="true">
                                <span class="snkrdunk-price-card-fallback">◆</span>
                            </div>
                            <div class="snkrdunk-price-card-body">
                                <h2 class="snkrdunk-price-card-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <div class="snkrdunk-price-values">
                                    <div>
                                        <span class="snkrdunk-price-label">판매중 최저</span>
                                        <strong><?php echo $ask_min > 0 ? '₩' . number_format($ask_min) : '—'; ?></strong>
                                    </div>
                                    <div>
                                        <span class="snkrdunk-price-label">판매완료 평균</span>
                                        <strong><?php echo $avg > 0 ? '₩' . number_format($avg) : '—'; ?></strong>
                                    </div>
                                </div>
                                <div class="snkrdunk-price-meta">
                                    <span>판매중 <?php echo number_format($ask_cnt); ?>건</span>
                                    <span>판매완료 <?php echo number_format($cnt); ?>건</span>
                                    <?php if (($row['sold_source'] ?? '') === 'listed' && $cnt > 0): ?>
                                        <span>희망가 참고</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
