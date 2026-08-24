<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_snkrdunk_box.php';

$q = trim((string) ($_GET['q'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'popular'));
$allowed_sorts = ['popular', 'price_low', 'price_high', 'release'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'popular';
}

$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 24;
$ready = snkrdunk_box_tables_ready();
$name_ko_supported = $ready && snkrdunk_box_name_ko_supported();

$where = ['sb_status = 1', 'sb_min_price_jpy > 0'];
if ($q !== '') {
    $q_esc = db_escape($q);
    $name_ko_where = $name_ko_supported ? "sb_name_ko LIKE '%{$q_esc}%' OR " : '';
    $where[] = "(
        {$name_ko_where}sb_name_en LIKE '%{$q_esc}%'
        OR sb_name_ja LIKE '%{$q_esc}%'
        OR sb_product_number LIKE '%{$q_esc}%'
    )";
}
$where_sql = implode(' AND ', $where);

$order_sql = 'sb_listing_count DESC, sb_product_id DESC';
if ($sort === 'price_low') {
    $order_sql = 'sb_min_price_jpy ASC, sb_listing_count DESC';
} elseif ($sort === 'price_high') {
    $order_sql = 'sb_min_price_jpy DESC, sb_listing_count DESC';
} elseif ($sort === 'release') {
    $order_sql = 'sb_released_at DESC, sb_product_id DESC';
}

$total = 0;
$total_page = 1;
$rows = [];
$last_fetched_at = null;
$latest_fx_rate = 0.0;

if ($ready) {
    $total = (int) db_result("SELECT COUNT(*) FROM tb_snkrdunk_box WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per_page));
    if ($page_no > $total_page) {
        $page_no = $total_page;
    }
    $offset = ($page_no - 1) * $per_page;

    $rs = db_query("
        SELECT *
        FROM tb_snkrdunk_box
        WHERE {$where_sql}
        ORDER BY {$order_sql}
        LIMIT {$offset}, {$per_page}
    ");
    while ($row = db_assoc($rs)) {
        $rows[] = $row;
    }

    $latest = db_assoc(db_query("
        SELECT sb_fetched_at, sb_fx_rate
        FROM tb_snkrdunk_box
        WHERE sb_status = 1 AND sb_fetched_at IS NOT NULL
        ORDER BY sb_fetched_at DESC
        LIMIT 1
    "));
    if ($latest) {
        $last_fetched_at = $latest['sb_fetched_at'] ?? null;
        $latest_fx_rate = (float) ($latest['sb_fx_rate'] ?? 0);
    }
}

$build_url = static function (array $overrides = []) use ($q, $sort): string {
    $params = ['q' => $q, 'sort' => $sort];
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $params = array_filter($params, static fn($v): bool => $v !== '');
    return '/page/card_price.php' . ($params ? '?' . http_build_query($params) : '');
};

$page  = 'card_price';
$title = '박스 시세';
$meta_description = '일본판 포켓몬카드 미개봉 박스의 SNKRDUNK 참고 최저가를 엔화와 원화 환산 금액으로 확인합니다.';
$meta_keywords = '포켓몬카드 박스 시세, 일본판 포켓몬카드, 미개봉 박스, SNKRDUNK, 엔화 시세';
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '박스 시세', 'url' => '/page/card_price.php'],
];
$meta_canonical = '/page/card_price.php';

include __DIR__ . '/../include/header.php';
?>

<section class="community card-price-page snkrdunk-price-page">
    <div class="container">
        <header class="board-head shop-store-head card-price-head">
            <div class="card-price-head-copy">
                <p class="card-price-kicker">일본판 · 미개봉 박스</p>
                <h1 class="board-title">박스 시세</h1>
                <p class="board-desc">
                    일본 포켓몬카드 미개봉 박스의 외부 마켓 참고 최저가입니다.
                    엔화 가격과 수집 시점 환율을 적용한 원화 환산액을 함께 표시합니다.
                </p>
                <?php if ($last_fetched_at): ?>
                    <p class="snkrdunk-price-updated">
                        마지막 갱신 <?php echo htmlspecialchars(date('Y.m.d H:i', strtotime((string) $last_fetched_at))); ?>
                        <?php if ($latest_fx_rate > 0): ?>
                            · 적용 환율 ¥1 ≈ ₩<?php echo htmlspecialchars(number_format($latest_fx_rate, 4)); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </header>

        <aside class="snkrdunk-price-disclaimer" aria-label="가격 출처 및 면책 안내">
            <strong>가격 출처 및 안내</strong>
            <p>
                가격 출처는 <a href="https://snkrdunk.com/en/brands/pokemon/trading-cards?categoryId=14"
                    target="_blank" rel="nofollow noopener noreferrer">SNKRDUNK</a>이며,
                포카존은 SNKRDUNK와 제휴하거나 공식 관계를 맺고 있지 않습니다.
                표시 가격은 외부 마켓의 수집 시점 최저가를 참고용으로 제공한 것으로,
                실시간 가격·재고·상품 상태를 보장하지 않습니다.
            </p>
            <p>
                원화 금액은 환율에 따른 단순 환산액이며 배송비, 관세, 결제 수수료 및 기타 비용이 포함되지 않습니다.
                실제 구매 조건과 최종 결제 금액은 반드시 원문 상품 페이지에서 확인해 주세요.
            </p>
        </aside>

        <form class="board-search shop-search card-price-search snkrdunk-price-search"
              method="get" action="/page/card_price.php" role="search">
            <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="상품명·세트 코드 검색" aria-label="박스 시세 검색">
            <select name="sort" class="card-price-site-select" aria-label="정렬">
                <option value="popular"<?php echo $sort === 'popular' ? ' selected' : ''; ?>>매물 많은 순</option>
                <option value="release"<?php echo $sort === 'release' ? ' selected' : ''; ?>>최근 발매 순</option>
                <option value="price_low"<?php echo $sort === 'price_low' ? ' selected' : ''; ?>>낮은 가격 순</option>
                <option value="price_high"<?php echo $sort === 'price_high' ? ' selected' : ''; ?>>높은 가격 순</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">적용</button>
        </form>

        <?php if (!$ready): ?>
            <div class="shop-prep-state card-price-empty">
                <div class="shop-prep-visual" aria-hidden="true">📦</div>
                <p class="shop-prep-title">시세 데이터를 준비하고 있습니다</p>
                <p class="shop-prep-desc">데이터 수집이 완료되면 일본판 미개봉 박스 시세가 표시됩니다.</p>
            </div>
        <?php elseif (!$rows): ?>
            <div class="shop-prep-state card-price-empty">
                <div class="shop-prep-visual" aria-hidden="true">🔎</div>
                <p class="shop-prep-title">검색 결과가 없습니다</p>
                <p class="shop-prep-desc">
                    다른 상품명이나 세트 코드로 검색해 보세요.
                    <?php if ($q !== ''): ?><a href="/page/card_price.php">전체 보기</a><?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="shop-store-toolbar card-price-toolbar">
                <span class="shop-store-count"><?php echo number_format($total); ?>개 박스</span>
                <?php if ($q !== ''): ?>
                    <span class="shop-store-query">&ldquo;<?php echo htmlspecialchars($q); ?>&rdquo; 검색 결과</span>
                    <a href="<?php echo htmlspecialchars($build_url(['q' => null, 'p' => null]), ENT_QUOTES, 'UTF-8'); ?>"
                       class="link-more">검색 초기화</a>
                <?php endif; ?>
            </div>

            <div class="snkrdunk-price-grid" role="list">
                <?php foreach ($rows as $row):
                    $product_id = (int) $row['sb_product_id'];
                    $name_ko = $name_ko_supported ? trim((string) ($row['sb_name_ko'] ?? '')) : '';
                    $name_ja = trim((string) $row['sb_name_ja']);
                    $name_en = trim((string) $row['sb_name_en']);
                    $product_name = $name_ko !== ''
                        ? $name_ko
                        : ($name_ja !== '' ? $name_ja : $name_en);
                    $source_name = $name_ko !== ''
                        ? ($name_ja !== '' ? $name_ja : $name_en)
                        : ($name_ja !== '' ? $name_en : '');
                    $product_url = trim((string) $row['sb_product_url']);
                    $is_valid_url = filter_var($product_url, FILTER_VALIDATE_URL) !== false
                        && parse_url($product_url, PHP_URL_SCHEME) === 'https';
                    $thumbnail_url = trim((string) ($row['sb_thumbnail_url'] ?? ''));
                    $thumbnail_host = strtolower((string) parse_url($thumbnail_url, PHP_URL_HOST));
                    $is_valid_thumbnail = filter_var($thumbnail_url, FILTER_VALIDATE_URL) !== false
                        && parse_url($thumbnail_url, PHP_URL_SCHEME) === 'https'
                        && ($thumbnail_host === 'cdn.snkrdunk.com'
                            || str_ends_with($thumbnail_host, '.snkrdunk.com'));
                    ?>
                    <article class="snkrdunk-price-card" role="listitem">
                        <div class="snkrdunk-price-card-icon<?php echo $is_valid_thumbnail ? ' has-image' : ''; ?>">
                            <span class="snkrdunk-price-card-fallback" aria-hidden="true">📦</span>
                            <?php if ($is_valid_thumbnail): ?>
                                <img src="<?php echo htmlspecialchars($thumbnail_url, ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'); ?>"
                                     loading="lazy" decoding="async" referrerpolicy="no-referrer"
                                     onerror="this.hidden=true">
                            <?php endif; ?>
                        </div>
                        <div class="snkrdunk-price-card-body">
                            <div class="card-price-product-tags">
                                <span class="item-type-chip item-box">일본판</span>
                                <span class="item-kind-chip item-single">미개봉 박스</span>
                                <?php if (!empty($row['sb_product_number'])): ?>
                                    <span class="snkrdunk-product-code"><?php echo htmlspecialchars($row['sb_product_number']); ?></span>
                                <?php endif; ?>
                            </div>
                            <h2 class="snkrdunk-price-card-name"><?php echo htmlspecialchars($product_name); ?></h2>
                            <?php if ($source_name !== ''): ?>
                                <p class="snkrdunk-price-card-en"><?php echo htmlspecialchars($source_name); ?></p>
                            <?php endif; ?>

                            <div class="snkrdunk-price-values">
                                <div>
                                    <span class="snkrdunk-price-label">외부 최저가</span>
                                    <strong>¥<?php echo number_format((int) $row['sb_min_price_jpy']); ?></strong>
                                </div>
                                <div>
                                    <span class="snkrdunk-price-label">원화 환산</span>
                                    <strong>약 ₩<?php echo number_format((int) $row['sb_min_price_krw']); ?></strong>
                                </div>
                            </div>

                            <div class="snkrdunk-price-meta">
                                <span>매물 <?php echo number_format((int) $row['sb_listing_count']); ?>개</span>
                                <?php if (!empty($row['sb_fetched_at'])): ?>
                                    <span>확인 <?php echo htmlspecialchars(date('m.d H:i', strtotime((string) $row['sb_fetched_at']))); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($is_valid_url): ?>
                            <a href="<?php echo htmlspecialchars($product_url, ENT_QUOTES, 'UTF-8'); ?>"
                               class="btn btn-outline btn-sm snkrdunk-source-btn"
                               target="_blank" rel="nofollow noopener noreferrer"
                               aria-label="<?php echo htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'); ?> 원문 시세 확인">
                                원문 시세 확인
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="박스 시세 페이지">
                    <?php
                    $start = max(1, $page_no - 2);
                    $end = min($total_page, $page_no + 2);
                    ?>
                    <?php if ($page_no > 1): ?>
                        <a href="<?php echo htmlspecialchars($build_url(['p' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="page-link" aria-label="첫 페이지">«</a>
                        <a href="<?php echo htmlspecialchars($build_url(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>" class="page-link" aria-label="이전 페이지">‹</a>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?php echo htmlspecialchars($build_url(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"
                           class="page-link <?php echo $i === $page_no ? 'is-active' : ''; ?>"
                           <?php echo $i === $page_no ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page_no < $total_page): ?>
                        <a href="<?php echo htmlspecialchars($build_url(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>" class="page-link" aria-label="다음 페이지">›</a>
                        <a href="<?php echo htmlspecialchars($build_url(['p' => $total_page]), ENT_QUOTES, 'UTF-8'); ?>" class="page-link" aria-label="마지막 페이지">»</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
