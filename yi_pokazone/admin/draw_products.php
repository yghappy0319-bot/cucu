<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '뽑기 관리';
$ad_topbar = $ad;
$ad_menu   = 'draw_products';
include __DIR__ . '/include/admin_header.php';
$pack_labels = [
    'expansion' => '확장팩',
    'enhanced' => '강화확장팩',
    'highclass' => '하이클래스팩',
];

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

$ready      = db_table_exists('tb_draw_product');
$rows       = [];
$total      = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '9') {
        $where[] = 'd.dp_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(d.dp_name LIKE '%{$e}%' OR IFNULL(d.dp_code, '') LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_draw_product d WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT d.*
        FROM tb_draw_product d
        WHERE {$where_sql}
        ORDER BY d.dp_sort DESC, d.dp_idx DESC
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
        <div class="ad-alert ad-alert--error">tb_draw_product 테이블이 없습니다. <code>sql/tb_draw_product.sql</code> 파일을 적용해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">뽑기 관리</h1>
                <a class="ad-btn" href="/admin/draw_product_form.php">상품 등록</a>
            </div>
            <p class="ad-p" style="margin-top:0;">뽑기 상자의 상자명, 제품번호(예: s4), 팩 종류, 대표 이미지를 관리합니다.</p>

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
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="상자명·제품번호">
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
                        <th>이미지</th>
                        <th>상자명</th>
                        <th>제품번호</th>
                        <th>팩 종류</th>
                        <th class="ad-num">수량</th>
                        <th class="ad-num">정렬</th>
                        <th>상태</th>
                        <th class="ad-nowrap">수정일</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $ok = (int) ($r['dp_status'] ?? 1) === 1;
                        $img = trim((string) ($r['dp_image_path'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo (int) $r['dp_idx']; ?></td>
                            <td>
                                <?php if ($img !== ''): ?>
                                    <img src="<?php echo htmlspecialchars(public_url($img), ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="" style="width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                                <?php else: ?>
                                    <span class="ad-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) $r['dp_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['dp_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pack_labels[(string) ($r['dp_pack_type'] ?? '')] ?? '확장팩'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo (int) ($r['dp_pack_count'] ?? 0); ?></td>
                            <td class="ad-num"><?php echo (int) ($r['dp_sort'] ?? 0); ?></td>
                            <td><span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '비노출'; ?></span></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) ($r['dp_updated_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn" href="/admin/draw_product_form.php?idx=<?php echo (int) $r['dp_idx']; ?>">수정</a>
                                <a class="ad-btn" href="/admin/draw_product_cards.php?dp_idx=<?php echo (int) $r['dp_idx']; ?>">카드관리</a>
                                <?php if ($ok): ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_draw_product_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 항목을 비노출 처리할까요?');">
                                        <input type="hidden" name="dp_idx" value="<?php echo (int) $r['dp_idx']; ?>">
                                        <input type="hidden" name="action" value="hide">
                                        <button type="submit" class="ad-btn">비노출</button>
                                    </form>
                                <?php else: ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_draw_product_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 항목을 다시 노출할까요?');">
                                        <input type="hidden" name="dp_idx" value="<?php echo (int) $r['dp_idx']; ?>">
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
                        <a href="/admin/draw_products.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/draw_products.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/draw_products.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
