<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/card_price_feed.php';

$title     = '박스 시세 · 전체 박스 관리';
$ad_topbar = $ad;
$ad_menu   = 'card_price_offers';
include __DIR__ . '/include/admin_header.php';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 40;
$offset  = ($page_no - 1) * $per;

$ready = card_price_tables_ready();
$offer_buy_col = card_price_co_buy_enabled_supported();
$offer_co_type_col = card_price_co_type_supported();

$rows       = [];
$total      = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($q !== '') {
        $e = db_escape($q);
        $parts = [
            "o.co_site_name LIKE '%{$e}%'",
            "o.co_site_id LIKE '%{$e}%'",
            "o.co_product_url LIKE '%{$e}%'",
            "IFNULL(p.cp_name,'') LIKE '%{$e}%'",
        ];
        if ($offer_co_type_col) {
            $parts[] = "IFNULL(o.co_type,'') LIKE '%{$e}%'";
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    $where_sql = implode(' AND ', $where);

    $total = (int) db_result("
        SELECT COUNT(*) FROM tb_card_price_offer o
        LEFT JOIN tb_card_price_product p ON p.cp_idx = o.cp_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT o.*, p.cp_name, p.cp_set_label, p.cp_status
        FROM tb_card_price_offer o
        LEFT JOIN tb_card_price_product p ON p.cp_idx = o.cp_idx
        WHERE {$where_sql}
        ORDER BY o.co_idx DESC
        LIMIT {$offset}, {$per}
    ");
    if ($rs) {
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
    }
}

$build_qs = static function (array $o) use ($q, $page_no) {
    $base = ['q' => $q, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
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
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">전체 박스 관리</h1>
                <a class="ad-btn" href="/admin/card_price_products.php">미개봉 박스 목록</a>
            </div>
            <p class="ad-p" style="margin-top:0;">
                <code>tb_card_price_offer</code> 의 모든 외부 판매 행입니다. 상품 본문·이미지는
                <a href="/admin/card_price_products.php">미개봉 박스</a>에서 수정하세요.
                사용자 화면: <a href="/page/card_price.php" target="_blank" rel="noopener">박스 시세</a>
            </p>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="박스명, 판매처, URL, 플랫폼…">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건 (행 기준)</p>

            <div class="ad-table-wrap" style="overflow-x:auto;">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th class="ad-num">#</th>
                        <th>미개봉 박스</th>
                        <th class="ad-nowrap">박스 상태</th>
                        <th>판매처 ID</th>
                        <?php if ($offer_co_type_col): ?>
                            <th>플랫폼·업체</th>
                        <?php endif; ?>
                        <th>판매처명</th>
                        <th class="ad-num">가격</th>
                        <th class="ad-num">재고</th>
                        <?php if ($offer_buy_col): ?>
                            <th>목록 노출</th>
                        <?php endif; ?>
                        <th class="ad-num">정렬</th>
                        <th style="min-width:12rem;">URL</th>
                        <th class="ad-nowrap">수정일</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $co_idx = (int) $r['co_idx'];
                        $cp_idx = (int) ($r['cp_idx'] ?? 0);
                        $pname = trim((string) ($r['cp_name'] ?? ''));
                        $pst = isset($r['cp_status']) ? (int) $r['cp_status'] : null;
                        $url = trim((string) ($r['co_product_url'] ?? ''));
                        $url_short = $url;
                        if (mb_strlen($url_short, 'UTF-8') > 42) {
                            $url_short = mb_substr($url_short, 0, 40, 'UTF-8') . '…';
                        }
                        $sq = $r['co_stock_qty'] ?? null;
                        $stock_disp = '미확인';
                        if ($sq !== null && $sq !== '') {
                            $stock_disp = number_format((int) $sq);
                        }
                        $pr = (int) ($r['co_price_won'] ?? 0);
                        $buy_ok = !$offer_buy_col || (int) ($r['co_buy_enabled'] ?? 1) === 1;
                        ?>
                        <tr>
                            <td class="ad-num"><?php echo $co_idx; ?></td>
                            <td class="ad-title-cell">
                                <?php if ($pname !== ''): ?>
                                    <?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (trim((string) ($r['cp_set_label'] ?? '')) !== ''): ?>
                                        <span class="ad-muted">(<?php echo htmlspecialchars((string) $r['cp_set_label'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="ad-muted">상품 없음 · cp_idx <?php echo $cp_idx; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-nowrap">
                                <?php if ($pst === null): ?>
                                    <span class="ad-muted">—</span>
                                <?php else: ?>
                                    <span class="ad-badge <?php echo $pst === 1 ? 'ad-badge--ok' : 'ad-badge--bad'; ?>">
                                        <?php echo $pst === 1 ? '노출' : '비노출'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars((string) ($r['co_site_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($offer_co_type_col): ?>
                                <td><?php echo htmlspecialchars((string) ($r['co_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars((string) ($r['co_site_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo $pr > 0 ? number_format($pr) : '—'; ?></td>
                            <td class="ad-num"><?php echo htmlspecialchars($stock_disp, ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($offer_buy_col): ?>
                                <td class="ad-nowrap">
                                    <span class="ad-badge <?php echo $buy_ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>">
                                        <?php echo $buy_ok ? '예' : '아니오'; ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td class="ad-num"><?php echo (int) ($r['co_sort'] ?? 0); ?></td>
                            <td class="ad-muted" style="font-size:12px;max-width:14rem;word-break:break-all;">
                                <?php if ($url !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                                       target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($url_short, ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) ($r['co_updated_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn" href="/admin/card_price_offer_form.php?co_idx=<?php echo $co_idx; ?>">수정</a>
                                <?php if ($cp_idx > 0): ?>
                                    <a class="ad-btn" href="/admin/card_price_product_form.php?idx=<?php echo $cp_idx; ?>">박스</a>
                                <?php endif; ?>
                                <form class="ad-form ad-form--inline" action="/proc/admin_card_price_offer_proc.php" method="post"
                                      style="display:inline;" onsubmit="return confirm('이 판매처 행을 삭제할까요?');">
                                    <input type="hidden" name="co_idx" value="<?php echo $co_idx; ?>">
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
                        <a href="/admin/card_price_offers.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/card_price_offers.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/card_price_offers.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
