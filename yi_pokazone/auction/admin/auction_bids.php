<?php
require_once __DIR__ . '/../../admin/include/admin_init.php';
require_once __DIR__ . '/../lib/_admin_auction.php';

$title     = '입찰내역';
$ad_topbar = $ad;
$ad_menu   = 'auction_bids';
include __DIR__ . '/../../admin/include/admin_header.php';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$au_idx  = max(0, (int) ($_GET['au_idx'] ?? 0));
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 30;
$offset  = ($page_no - 1) * $per;

$ready = db_table_exists('tb_auction_bid') && db_table_exists('tb_auction');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($au_idx > 0) {
        $where[] = 'b.au_idx = ' . $au_idx;
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(b.ab_idx = ' . (int) $q . ' OR b.au_idx = ' . (int) $q . ' OR b.mb_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $where[] = "(a.au_title LIKE '%{$e}%' OR m.mb_id LIKE '%{$e}%' OR m.mb_nick LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("
        SELECT COUNT(*)
        FROM tb_auction_bid b
        INNER JOIN tb_auction a ON a.au_idx = b.au_idx
        LEFT JOIN tb_member m ON m.mb_idx = b.mb_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT b.ab_idx, b.au_idx, b.mb_idx, b.ab_amount, b.ab_ip, b.ab_created_at,
               a.au_title, a.au_auction_status, a.au_current_price,
               m.mb_id, m.mb_nick
        FROM tb_auction_bid b
        INNER JOIN tb_auction a ON a.au_idx = b.au_idx
        LEFT JOIN tb_member m ON m.mb_idx = b.mb_idx
        WHERE {$where_sql}
        ORDER BY b.ab_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($q, $au_idx, $page_no) {
    $base = ['q' => $q, 'au_idx' => $au_idx, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if ((int) ($base['au_idx'] ?? 0) < 1) {
        unset($base['au_idx']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_auction_bid 또는 tb_auction 테이블이 없습니다. <code>sql/tb_auction.sql</code> 을 확인해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">입찰 내역</h1>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_au">경매번호</label>
                    <input type="number" id="f_au" name="au_idx" min="0" value="<?php echo $au_idx > 0 ? $au_idx : ''; ?>" placeholder="au_idx">
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="입찰# / 경매# / 회원# / 아이디·닉네임 / 경매제목">
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
                        <th>입찰#</th>
                        <th>경매</th>
                        <th>입찰자</th>
                        <th class="ad-num">입찰가</th>
                        <th>경매상태</th>
                        <th class="ad-num">현재가</th>
                        <th>IP</th>
                        <th class="ad-nowrap">입찰일시</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="ad-muted">내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int) $r['ab_idx']; ?></td>
                            <td class="ad-title-cell">
                                <a href="/auction/auction_view.php?idx=<?php echo (int) $r['au_idx']; ?>" target="_blank" rel="noopener">
                                    #<?php echo (int) $r['au_idx']; ?> <?php echo htmlspecialchars((string) $r['au_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['mb_nick'] ?: $r['mb_id'] ?: ('#' . $r['mb_idx'])), ENT_QUOTES, 'UTF-8'); ?>
                                <div class="ad-muted" style="font-size:12px;">#<?php echo (int) $r['mb_idx']; ?></div>
                            </td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['ab_amount']); ?></td>
                            <td><?php echo htmlspecialchars(admin_au_auction_status_label((int) $r['au_auction_status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['au_current_price']); ?></td>
                            <td class="ad-muted"><?php echo htmlspecialchars((string) ($r['ab_ip'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) $r['ab_created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/auction/admin/auction_bids.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/auction/admin/auction_bids.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/auction/admin/auction_bids.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../admin/include/admin_footer.php'; ?>
