<?php
require_once __DIR__ . '/../lib/_function.php';

$idx = (int) ($_GET['idx'] ?? 0);
if ($idx < 1) {
    alert_goto('잘못된 접근입니다.', '/page/shop.php');
}

if (!db_table_exists('tb_shop_product')) {
    alert_goto('쇼핑몰을 준비 중입니다.', '/page/shop.php');
}

$rs = db_query("
    SELECT *
    FROM tb_shop_product
    WHERE sp_idx = {$idx} AND sp_status = 1
    LIMIT 1
");
$row = $rs ? db_assoc($rs) : null;

if (!$row) {
    alert_goto('판매 중인 상품이 아니거나 존재하지 않습니다.', '/page/shop.php');
}

$page = 'shop';
$title = $row['sp_name'];
$meta_description = seo_clean_desc(
    ($row['sp_summary'] ?? '') !== ''
        ? (string) $row['sp_summary']
        : (string) $row['sp_name'] . ' · Pokazone 쇼핑몰',
    160
);
$meta_type = 'product';
$meta_keywords = '포켓몬카드, 쇼핑몰, ' . $row['sp_name'];
$img_path = trim((string) ($row['sp_image_path'] ?? ''));
$meta_image = $img_path;
$meta_published_at = date('c', strtotime($row['sp_created_at']));
$meta_modified_at  = date('c', strtotime((string) ($row['sp_updated_at'] ?? $row['sp_created_at'])));
$meta_breadcrumb   = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '쇼핑몰', 'url' => '/page/shop.php'],
    ['name' => $row['sp_name'], 'url' => '/page/shop_view.php?idx=' . (int) $row['sp_idx']],
];

$ext_link = trim((string) ($row['sp_link'] ?? ''));
$is_external = $ext_link !== '' && preg_match('#^https?://#i', $ext_link);

include __DIR__ . '/../include/header.php';

$price    = (int) $row['sp_price'];
$orig     = isset($row['sp_original_price']) ? (int) $row['sp_original_price'] : 0;
$content  = (string) ($row['sp_content'] ?? '');
$ship_free = !empty($row['sp_shipping_free']);
?>

<section class="shop-detail community">
    <div class="container">
        <nav class="breadcrumb shop-detail-bc" aria-label="breadcrumb">
            <a href="/">홈</a>
            <span class="sep">/</span>
            <a href="/page/shop.php">쇼핑몰</a>
            <span class="sep">/</span>
            <span class="current"><?php echo htmlspecialchars((string) $row['sp_name']); ?></span>
        </nav>

        <div class="shop-detail-grid">
            <div class="shop-detail-media">
                <?php if ($img_path !== ''): ?>
                    <figure class="shop-detail-figure">
                        <img src="<?php echo htmlspecialchars(public_url($img_path)); ?>"
                             alt="<?php echo htmlspecialchars((string) $row['sp_name']); ?>"
                             width="640" height="640" loading="eager" decoding="async">
                    </figure>
                <?php else: ?>
                    <div class="shop-detail-placeholder" aria-hidden="true">
                        <span>🛒</span>
                        <p>이미지 준비중</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="shop-detail-panel">
                <header class="shop-detail-head">
                    <p class="shop-detail-eyebrow">Pokazone Store</p>
                    <h1 class="shop-detail-title"><?php echo htmlspecialchars((string) $row['sp_name']); ?></h1>
                    <?php if (!empty($row['sp_subtitle'])): ?>
                        <p class="shop-detail-sub"><?php echo htmlspecialchars((string) $row['sp_subtitle']); ?></p>
                    <?php endif; ?>
                </header>

                <div class="shop-detail-pricebox">
                    <?php if ($price > 0): ?>
                        <?php if ($orig > $price): ?>
                            <p class="shop-detail-original">
                                정가 <span class="num"><small>₩</small><?php echo number_format($orig); ?></span>
                            </p>
                        <?php endif; ?>
                        <p class="shop-detail-price">
                            <small>₩</small><?php echo number_format($price); ?>
                        </p>
                    <?php else: ?>
                        <p class="shop-detail-price shop-detail-price--inquiry">가격 문의</p>
                    <?php endif; ?>
                    <ul class="shop-detail-badges">
                        <li class="chip chip-official">공식 판매</li>
                        <li class="chip <?php echo $ship_free ? 'chip-ship' : 'chip-ship-muted'; ?>">
                            <?php echo $ship_free ? '무료배송' : '배송비 별도'; ?>
                        </li>
                    </ul>
                </div>

                <div class="shop-detail-actions">
                    <?php if ($is_external): ?>
                        <a href="<?php echo htmlspecialchars($ext_link); ?>"
                           class="btn btn-primary shop-detail-cta" target="_blank" rel="noopener noreferrer">외부에서 구매하기</a>
                        <p class="shop-detail-action-note">결제는 연결된 외부 페이지에서 진행됩니다.</p>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary shop-detail-cta" disabled>장바구니 (준비중)</button>
                        <a href="/page/inquiry.php" class="btn btn-outline shop-detail-cta-secondary">구매 문의</a>
                        <p class="shop-detail-action-note">자체 결제·장바구니 연동 전까지 1:1 문의로 안내합니다.</p>
                    <?php endif; ?>
                    <p class="shop-detail-meta-line">
                        상품번호 SP-<?php echo (int) $row['sp_idx']; ?>
                        · 수정 <?php echo date('Y-m-d', strtotime($row['sp_updated_at'] ?? $row['sp_created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($content !== ''): ?>
            <div class="shop-detail-body policy-card">
                <h2 class="shop-detail-h2">상품 상세</h2>
                <div class="shop-detail-content"><?php echo nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($row['sp_summary'])): ?>
            <p class="shop-detail-summary-muted"><?php echo htmlspecialchars((string) $row['sp_summary']); ?></p>
        <?php endif; ?>

        <p class="shop-detail-back">
            <a href="/page/shop.php" class="btn btn-outline btn-sm">← 쇼핑몰 목록</a>
        </p>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
