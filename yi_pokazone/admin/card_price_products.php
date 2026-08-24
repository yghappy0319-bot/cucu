<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/card_price_feed.php';

$title     = '박스 시세';
$ad_topbar = $ad;
$ad_menu   = 'card_price';
include __DIR__ . '/include/admin_header.php';

$st_map = [
    'all' => '전체',
    '1'   => '노출',
    '9'   => '비노출',
];

$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 30;
$offset  = ($page_no - 1) * $per;

$ready       = card_price_tables_ready();
$rows        = [];
$total       = 0;
$total_page  = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '9') {
        $where[] = 'p.cp_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(p.cp_name LIKE '%{$e}%' OR IFNULL(p.cp_set_label, '') LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_card_price_product p WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT p.*,
               (SELECT COUNT(*) FROM tb_card_price_offer o WHERE o.cp_idx = p.cp_idx) AS offer_cnt
        FROM tb_card_price_product p
        WHERE {$where_sql}
        ORDER BY p.cp_sort DESC, p.cp_idx DESC
        LIMIT {$offset}, {$per}
    ");
    if ($rs) {
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
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
        <div class="ad-alert ad-alert--error">
            박스 시세 테이블이 없습니다. 서버에서 <code>sql/tb_card_price.sql</code> 을 실행한 뒤 다시 열어 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">박스 시세 · 미개봉 박스</h1>
                <a class="ad-btn" href="/admin/card_price_offers.php">전체 박스 관리</a>
                <a class="ad-btn" href="/admin/card_price_product_form.php">박스 등록</a>
            </div>
            <p class="ad-p" style="margin-top:0;">
                사용자 화면 <a href="/page/card_price.php" target="_blank" rel="noopener">박스 시세</a>에 노출되는 미개봉 부스터입니다.
                비노출(<code>cp_status=9</code>)은 목록에서 숨깁니다. 각 상품마다 외부 쇼핑몰 판매처·재고·링크를 여러 행으로 넣을 수 있습니다.
            </p>

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
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="상품명·세트 라벨">
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
                        <th>세트</th>
                        <th class="ad-num">판매처 수</th>
                        <th class="ad-num">정렬</th>
                        <th>상태</th>
                        <th class="ad-nowrap">수정일</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $ok = (int) $r['cp_status'] === 1;
                        $oc = (int) ($r['offer_cnt'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo (int) $r['cp_idx']; ?></td>
                            <td class="ad-title-cell"><?php echo htmlspecialchars($r['cp_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-muted"><?php echo htmlspecialchars((string) ($r['cp_set_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo number_format($oc); ?></td>
                            <td class="ad-num"><?php echo (int) $r['cp_sort']; ?></td>
                            <td><span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '비노출'; ?></span></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) ($r['cp_updated_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn" href="/admin/card_price_product_form.php?idx=<?php echo (int) $r['cp_idx']; ?>">수정</a>
                                <?php if ($ok): ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_card_price_product_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 상품을 비노출 처리할까요?');">
                                        <input type="hidden" name="cp_idx" value="<?php echo (int) $r['cp_idx']; ?>">
                                        <input type="hidden" name="action" value="hide">
                                        <button type="submit" class="ad-btn">비노출</button>
                                    </form>
                                <?php else: ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_card_price_product_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 상품을 다시 노출할까요?');">
                                        <input type="hidden" name="cp_idx" value="<?php echo (int) $r['cp_idx']; ?>">
                                        <input type="hidden" name="action" value="show">
                                        <button type="submit" class="ad-btn">노출</button>
                                    </form>
                                <?php endif; ?>
                                <form class="ad-form ad-form--inline" action="/proc/admin_card_price_product_proc.php" method="post" style="display:inline;"
                                      onsubmit="return confirm('삭제하면 판매처 행도 모두 지워집니다. 계속할까요?');">
                                    <input type="hidden" name="cp_idx" value="<?php echo (int) $r['cp_idx']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="ad-btn">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/card_price_products.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/card_price_products.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/card_price_products.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
