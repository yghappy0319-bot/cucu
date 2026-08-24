<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_search_rank.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q !== '') {
    $me_shop_search = login_member();
    search_log_keyword($q, $me_shop_search ? (int) $me_shop_search['mb_idx'] : null);
}

$page  = 'shop';
$title = '쇼핑몰';
$meta_description = 'Pokazone 공식 쇼핑몰. 포켓몬 카드·부스터·관련 굿즈를 한곳에서 만나 보세요.';
$meta_keywords    = '포켓몬카드 쇼핑몰, 포켓몬 TCG 구매, 부스터박스, Pokazone 스토어';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '쇼핑몰', 'url' => '/page/shop.php'],
];

$shop_table_ok = db_table_exists('tb_shop_product');
$shop_rows     = [];

if ($shop_table_ok) {
    $where = 'sp_status = 1';
    if ($q !== '') {
        $kw = db_escape($q);
        $where .= " AND (
            sp_name LIKE '%{$kw}%'
            OR IFNULL(sp_summary, '') LIKE '%{$kw}%'
            OR IFNULL(sp_subtitle, '') LIKE '%{$kw}%'
        )";
    }
    $sql = "
        SELECT sp_idx, sp_name, sp_subtitle, sp_summary, sp_price, sp_original_price,
               sp_image_path, sp_shipping_free, sp_link
        FROM tb_shop_product
        WHERE {$where}
        ORDER BY sp_sort DESC, sp_idx DESC
    ";
    $rs = db_query($sql);
    if ($rs) {
        while ($r = db_assoc($rs)) {
            $shop_rows[] = $r;
        }
    }
}

$shop_empty = !$shop_table_ok || count($shop_rows) === 0;

include __DIR__ . '/../include/header.php';

/**
 * @param array<string, mixed> $row
 */
$shop_item_href = static function (array $row): string {
    $link = trim((string) ($row['sp_link'] ?? ''));
    if ($link !== '' && preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if ($link !== '' && isset($link[0]) && $link[0] === '/') {
        return $link;
    }
    return '/page/shop_view.php?idx=' . (int) ($row['sp_idx'] ?? 0);
};
?>

<section class="shop-store community">
    <div class="container">
        <header class="board-head shop-store-head">
            <div>
                <h1 class="board-title">Pokazone 쇼핑몰</h1>
                <p class="board-desc">공식 검수 상품 위주로 구성합니다. 배송·환불 정책은 상세 페이지 안내를 확인해 주세요.</p>
            </div>
            <div class="shop-store-head-links">
                <a href="/page/partner.php" class="btn btn-outline btn-sm">입점 문의</a>
            </div>
        </header>

        <form class="board-search shop-search" method="get" action="/page/shop.php" role="search">
            <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>"
                   placeholder="상품명·설명으로 검색" aria-label="쇼핑몰 상품 검색">
            <button type="submit" class="btn btn-outline btn-sm">검색</button>
        </form>

        <?php if ($shop_empty): ?>
            <div class="shop-prep-state">
                <div class="shop-prep-visual" aria-hidden="true">🛒</div>
                <p class="shop-prep-title">준비중</p>
                <p class="shop-prep-desc">
                    <?php if (!$shop_table_ok): ?>
                        쇼핑몰 DB 테이블이 아직 적용되지 않았습니다. 서버에서
                        <code>sql/tb_shop_product.sql</code> 실행 후 새로고침해 주세요.
                    <?php elseif ($q !== ''): ?>
                        검색 조건에 맞는 상품이 없습니다. 다른 키워드로 시도해 보시거나 전체 목록을 확인해 보세요?
                    <?php else: ?>
                        등록된 판매 상품이 아직 없습니다. 곧 오픈할 예정입니다. 트레이너 간 거래는
                        <a href="/trade/trade.php">거래게시판</a>을 이용해 주세요.
                    <?php endif; ?>
                </p>
                <?php if ($q !== ''): ?>
                    <p class="shop-prep-actions">
                        <a href="/page/shop.php" class="btn btn-primary btn-sm">전체 보기</a>
                        <a href="/page/search.php?q=<?php echo urlencode($q); ?>" class="btn btn-outline btn-sm">통합 검색</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="shop-store-toolbar">
                <span class="shop-store-count"><?php echo number_format(count($shop_rows)); ?>개 상품</span>
                <?php if ($q !== ''): ?>
                    <span class="shop-store-query">&ldquo;<?php echo htmlspecialchars($q); ?>&rdquo; 검색 결과</span>
                    <a href="/page/shop.php" class="link-more">목록 초기화</a>
                <?php endif; ?>
            </div>

            <div class="trade-grid shop-store-grid">
                <?php foreach ($shop_rows as $row):
                    $href = $shop_item_href($row);
                    $img  = trim((string) ($row['sp_image_path'] ?? ''));
                    $ext  = $href !== '' && preg_match('#^https?://#i', $href);
                    ?>
                    <a href="<?php echo htmlspecialchars($href); ?>"
                       class="trade-item shop-store-item <?php echo $ext ? 'is-external' : ''; ?>"
                       <?php echo $ext ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <div class="trade-thumb <?php echo $img !== '' ? 'has-image' : ''; ?>">
                            <div class="trade-thumb-tags shop-store-tags">
                                <span class="item-type-chip item-card">공식</span>
                                <?php if (!empty($row['sp_shipping_free'])): ?>
                                    <span class="item-kind-chip item-single shop-chip-ship">무료배송</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($img !== ''): ?>
                                <img class="trade-image"
                                     src="<?php echo htmlspecialchars(public_url($img)); ?>"
                                     alt="<?php echo htmlspecialchars((string) $row['sp_name']); ?>"
                                     loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="trade-emoji" aria-hidden="true">🛒</span>
                            <?php endif; ?>
                        </div>
                        <div class="trade-body">
                            <p class="trade-title"><?php echo htmlspecialchars((string) $row['sp_name']); ?></p>
                            <?php if (!empty($row['sp_subtitle'])): ?>
                                <p class="trade-card shop-store-sub"><?php echo htmlspecialchars((string) $row['sp_subtitle']); ?></p>
                            <?php elseif (!empty($row['sp_summary'])): ?>
                                <p class="trade-card"><?php echo htmlspecialchars((string) $row['sp_summary']); ?></p>
                            <?php endif; ?>
                            <div class="trade-price shop-store-price">
                                <?php if ((int) $row['sp_price'] > 0): ?>
                                    <?php
                                    $orig = isset($row['sp_original_price']) ? (int) $row['sp_original_price'] : 0;
                                    $pr   = (int) $row['sp_price'];
                                    ?>
                                    <?php if ($orig > $pr): ?>
                                        <span class="shop-price-original"><small>₩</small><?php echo number_format($orig); ?></span>
                                    <?php endif; ?>
                                    <small>₩</small><?php echo number_format($pr); ?>
                                <?php else: ?>
                                    <span class="trade-price-free">가격 문의</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="policy-grid policy-grid-3 shop-store-footnotes">
            <article class="policy-card">
                <div class="policy-icon">🛒</div>
                <h2>자체 스토어</h2>
                <p>정식 유통·검수 상품을 직접 등록해 판매합니다.</p>
            </article>
            <article class="policy-card">
                <div class="policy-icon">📦</div>
                <h2>배송 안내</h2>
                <p>상품별 출고일·택배사는 상세 페이지에서 안내합니다.</p>
            </article>
            <article class="policy-card">
                <div class="policy-icon">🤝</div>
                <h2>입점·제휴</h2>
                <p>브랜드 입점은 <a href="/page/partner.php">제휴문의</a>로 연락 주세요.</p>
            </article>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
