<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_coupon.php';

$title     = '쿠폰 발행 내역';
$ad_topbar = $ad;
$ad_menu   = 'coupons';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$ready      = coupon_table_ready() && member_coupon_table_ready();
$rows       = [];
$total      = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($q !== '') {
        $e = db_escape($q);
        if (ctype_digit($q)) {
            $where[] = 'cp.cp_idx = ' . (int) $q;
        } else {
            $where[] = "cp.cp_name LIKE '%{$e}%'";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result('SELECT COUNT(*) FROM tb_coupon cp WHERE ' . $where_sql);
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT cp.*
        FROM tb_coupon cp
        WHERE {$where_sql}
        ORDER BY cp.cp_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$issued = isset($_GET['issued']) && (string) $_GET['issued'] === '1';
$count  = max(0, (int) ($_GET['count'] ?? 0));

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

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            쿠폰 테이블이 없습니다. <code>sql/migrate_tb_coupon.sql</code>을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">쿠폰 발행 내역</h1>
                <a class="ad-btn" href="/admin/coupon_issue.php">쿠폰 발행</a>
            </div>
            <?php if ($issued): ?>
                <div class="ad-alert ad-alert--done" role="status">
                    쿠폰이 발행되었습니다.<?php echo $count > 0 ? ' (총 ' . number_format($count) . '명)' : ''; ?>
                </div>
            <?php endif; ?>
            <p class="ad-p" style="margin-top:0;">
                바로구매 결제 시 사용할 수 있는 할인 쿠폰을 발행합니다.
                정액(예: ₩1,000) 또는 정률(예: 1%) 할인을 지정할 수 있습니다.
            </p>
            <form class="ad-toolbar ad-form" method="get" action="/admin/coupons.php">
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="쿠폰# / 쿠폰명">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>쿠폰명</th>
                        <th>할인</th>
                        <th>사용기간</th>
                        <th>발행대상</th>
                        <th class="ad-num">발행</th>
                        <th class="ad-num">사용</th>
                        <th class="ad-nowrap">등록</th>
                        <th>상세</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="ad-muted">발행 내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>#<?php echo (int) ($r['cp_idx'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['cp_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(coupon_format_discount($r), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <?php echo htmlspecialchars((string) ($r['cp_valid_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                ~
                                <?php echo htmlspecialchars((string) ($r['cp_valid_until'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <?php echo (int) ($r['cp_issue_target'] ?? 0) === COUPON_ISSUE_ALL ? '전체 활성 회원' : '개별 회원'; ?>
                            </td>
                            <td class="ad-num"><?php echo number_format((int) ($r['cp_issued_count'] ?? 0)); ?></td>
                            <td class="ad-num"><?php echo number_format((int) ($r['cp_used_count'] ?? 0)); ?></td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) ($r['cp_created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="/admin/coupon_members.php?cp_idx=<?php echo (int) ($r['cp_idx'] ?? 0); ?>">회원별</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="<?php echo htmlspecialchars('/admin/coupons.php' . $build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars('/admin/coupons.php' . $build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="<?php echo htmlspecialchars('/admin/coupons.php' . $build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
