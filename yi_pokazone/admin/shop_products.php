<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '쇼핑몰 상품';
$ad_topbar = $ad;
$ad_menu   = 'shop_products';
include __DIR__ . '/include/admin_header.php';

$st_map = [
    'all' => '전체',
    '1'   => '노출',
    '9'   => '비노출',
];

$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$ready       = db_table_exists('tb_shop_product');
$rows        = [];
$total       = 0;
$total_page  = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '9') {
        $where[] = 'p.sp_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(p.sp_name LIKE '%{$e}%' OR IFNULL(p.sp_subtitle, '') LIKE '%{$e}%' OR IFNULL(p.sp_summary, '') LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_shop_product p WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT p.*
        FROM tb_shop_product p
        WHERE {$where_sql}
        ORDER BY p.sp_featured DESC, p.sp_sort DESC, p.sp_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($st, $q, $page_no) {
    $base = ['st' => $st, 'q' => $q, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['st'] ?? '') === '' || ($base['st'] ?? '') === 'all') {
        unset($base['st']);
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_shop_product 테이블이 없습니다. <code>sql/tb_shop_product.sql</code> 또는 마이그레이션을 적용해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">쇼핑몰 상품</h1>
                <a class="ad-btn" href="/admin/shop_product_form.php">상품 등록</a>
            </div>
            <p class="ad-p" style="margin-top:0;">사용자 화면 <a href="/page/shop.php" target="_blank" rel="noopener">쇼핑몰</a>에 노출되는 상품입니다. 비노출(<code>sp_status=9</code>)은 목록·상세에서 숨깁니다.</p>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>상태</label>
                    <select name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="상품명·부제·요약">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건</p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>상품명</th>
                        <th class="ad-num">판매가</th>
                        <th>추천</th>
                        <th class="ad-num">정렬</th>
                        <th>배송</th>
                        <th>상태</th>
                        <th class="ad-nowrap">수정일</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $ok     = (int) $r['sp_status'] === 1;
                        $feat   = (int) ($r['sp_featured'] ?? 0) === 1;
                        $ship   = !empty($r['sp_shipping_free']);
                        $price  = (int) $r['sp_price'];
                        ?>
                        <tr>
                            <td><?php echo (int) $r['sp_idx']; ?></td>
                            <td class="ad-title-cell">
                                <?php if ($ok): ?>
                                    <a href="/page/shop_view.php?idx=<?php echo (int) $r['sp_idx']; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['sp_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($r['sp_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <div class="ad-muted" style="font-size:12px;">비노출 — 미리보기는 등록 화면에서 확인</div>
                                <?php endif; ?>
                            </td>
                            <td class="ad-num"><?php echo number_format($price); ?>원</td>
                            <td><?php echo $feat ? '<span class="ad-badge ad-badge--ok">추천</span>' : '<span class="ad-muted">—</span>'; ?></td>
                            <td class="ad-num"><?php echo (int) $r['sp_sort']; ?></td>
                            <td><?php echo $ship ? '무료배송' : '조건문의'; ?></td>
                            <td><span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '비노출'; ?></span></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) ($r['sp_updated_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn" href="/admin/shop_product_form.php?idx=<?php echo (int) $r['sp_idx']; ?>">수정</a>
                                <?php if ($ok): ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_shop_product_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 상품을 비노출 처리할까요?');">
                                        <input type="hidden" name="sp_idx" value="<?php echo (int) $r['sp_idx']; ?>">
                                        <input type="hidden" name="action" value="hide">
                                        <button type="submit" class="ad-btn">비노출</button>
                                    </form>
                                <?php else: ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_shop_product_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 상품을 다시 노출할까요?');">
                                        <input type="hidden" name="sp_idx" value="<?php echo (int) $r['sp_idx']; ?>">
                                        <input type="hidden" name="action" value="show">
                                        <button type="submit" class="ad-btn">노출</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/shop_products.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/shop_products.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/shop_products.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
