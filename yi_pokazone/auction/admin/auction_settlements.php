<?php
require_once __DIR__ . '/../../admin/include/admin_init.php';
require_once __DIR__ . '/../lib/_admin_auction.php';
require_once __DIR__ . '/../lib/_auction_order.php';

$title     = '경매 정산내역';
$ad_topbar = $ad;
$ad_menu   = 'auction_settlements';
include __DIR__ . '/../../admin/include/admin_header.php';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$st_map = admin_au_settle_filter_options();
if ($st !== '' && !isset($st_map[$st])) {
    $st = '';
}

$ready = db_table_exists('tb_auction_order') && db_table_exists('tb_auction');
$rows  = [];
$total = 0;
$total_page = 1;
$sum_settle = 0;
$sum_fee    = 0;
$sum_refund = 0;

if ($ready) {
    $where = ['1=1'];
    switch ($st) {
        case 'unpaid':
            $where[] = 'o.ao_pay_status = 0';
            break;
        case 'paid':
            $where[] = 'o.ao_pay_status = 1';
            $where[] = 'o.ao_buyer_confirmed_at IS NULL';
            $where[] = 'o.ao_refunded_at IS NULL';
            if (auction_order_refund_request_column_ready()) {
                $where[] = 'o.ao_refund_requested_at IS NULL';
            }
            break;
        case 'confirmed':
            $where[] = 'o.ao_buyer_confirmed_at IS NOT NULL';
            $where[] = 'o.ao_refunded_at IS NULL';
            break;
        case 'refund_pending':
            if (auction_order_refund_request_column_ready()) {
                $where[] = 'o.ao_refund_requested_at IS NOT NULL';
                $where[] = 'o.ao_refunded_at IS NULL';
            } else {
                $where[] = '1=0';
            }
            break;
        case 'refunded':
            $where[] = 'o.ao_refunded_at IS NOT NULL';
            break;
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(o.ao_idx = ' . (int) $q . ' OR o.au_idx = ' . (int) $q
                . ' OR o.mb_idx = ' . (int) $q . ' OR o.seller_mb_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $where[] = "(a.au_title LIKE '%{$e}%' OR b.mb_id LIKE '%{$e}%' OR b.mb_nick LIKE '%{$e}%'
                OR s.mb_id LIKE '%{$e}%' OR s.mb_nick LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("
        SELECT COUNT(*)
        FROM tb_auction_order o
        INNER JOIN tb_auction a ON a.au_idx = o.au_idx
        LEFT JOIN tb_member b ON b.mb_idx = o.mb_idx
        LEFT JOIN tb_member s ON s.mb_idx = o.seller_mb_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $sum_row = db_assoc(db_query("
        SELECT
            COALESCE(SUM(CASE WHEN o.ao_buyer_confirmed_at IS NOT NULL AND o.ao_refunded_at IS NULL
                THEN o.ao_seller_settle_amount ELSE 0 END), 0) AS sum_settle,
            COALESCE(SUM(CASE WHEN o.ao_buyer_confirmed_at IS NOT NULL AND o.ao_refunded_at IS NULL
                THEN o.ao_platform_fee ELSE 0 END), 0) AS sum_fee,
            COALESCE(SUM(CASE WHEN o.ao_refunded_at IS NOT NULL THEN o.ao_refund_amount ELSE 0 END), 0) AS sum_refund
        FROM tb_auction_order o
        INNER JOIN tb_auction a ON a.au_idx = o.au_idx
        LEFT JOIN tb_member b ON b.mb_idx = o.mb_idx
        LEFT JOIN tb_member s ON s.mb_idx = o.seller_mb_idx
        WHERE {$where_sql}
    "));
    if ($sum_row) {
        $sum_settle = (int) $sum_row['sum_settle'];
        $sum_fee    = (int) $sum_row['sum_fee'];
        $sum_refund = (int) $sum_row['sum_refund'];
    }

    $rs = db_query("
        SELECT o.*, a.au_title,
               b.mb_id AS buyer_id, b.mb_nick AS buyer_nick,
               s.mb_id AS seller_id, s.mb_nick AS seller_nick
        FROM tb_auction_order o
        INNER JOIN tb_auction a ON a.au_idx = o.au_idx
        LEFT JOIN tb_member b ON b.mb_idx = o.mb_idx
        LEFT JOIN tb_member s ON s.mb_idx = o.seller_mb_idx
        WHERE {$where_sql}
        ORDER BY o.ao_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($q, $st, $page_no) {
    $base = ['q' => $q, 'st' => $st, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if (($base['st'] ?? '') === '') {
        unset($base['st']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_auction_order 또는 tb_auction 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">경매 정산 내역</h1>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>정산상태</label>
                    <select name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="주문# / 경매# / 회원# / 제목 / 아이디·닉네임">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>
            <p class="ad-p" style="margin-top:0;">
                총 <?php echo number_format($total); ?>건
                · 정산합계 ₩<?php echo number_format($sum_settle); ?>
                · 수수료합계 ₩<?php echo number_format($sum_fee); ?>
                · 환불합계 ₩<?php echo number_format($sum_refund); ?>
            </p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>주문#</th>
                        <th>경매</th>
                        <th>구매자</th>
                        <th>판매자</th>
                        <th class="ad-num">결제액</th>
                        <th>결제</th>
                        <th>상태</th>
                        <th class="ad-num">판매자정산</th>
                        <th class="ad-num">수수료</th>
                        <th class="ad-nowrap">구매확정</th>
                        <th class="ad-nowrap">환불</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="ad-muted">내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <a href="/auction/auction_order.php?au_idx=<?php echo (int) $r['au_idx']; ?>" target="_blank" rel="noopener">
                                    #<?php echo (int) $r['ao_idx']; ?>
                                </a>
                            </td>
                            <td class="ad-title-cell">
                                <a href="/auction/auction_view.php?idx=<?php echo (int) $r['au_idx']; ?>" target="_blank" rel="noopener">
                                    #<?php echo (int) $r['au_idx']; ?> <?php echo htmlspecialchars((string) $r['au_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($r['buyer_nick'] ?: $r['buyer_id'] ?: ('#' . $r['mb_idx'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['seller_nick'] ?: $r['seller_id'] ?: ('#' . $r['seller_mb_idx'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['ao_amount']); ?></td>
                            <td><?php echo htmlspecialchars(admin_au_pay_method_label((string) ($r['ao_pay_method'] ?? 'cash')), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="ad-badge <?php echo admin_au_settle_state_class($r); ?>">
                                    <?php echo htmlspecialchars(admin_au_settle_state_label($r), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <div class="ad-muted" style="font-size:12px;">
                                    <?php echo htmlspecialchars(admin_au_order_status_label((int) $r['ao_order_status']), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td class="ad-num">
                                <?php echo !empty($r['ao_buyer_confirmed_at']) && empty($r['ao_refunded_at'])
                                    ? '₩' . number_format((int) $r['ao_seller_settle_amount'])
                                    : '—'; ?>
                            </td>
                            <td class="ad-num">
                                <?php echo !empty($r['ao_buyer_confirmed_at']) && empty($r['ao_refunded_at'])
                                    ? '₩' . number_format((int) $r['ao_platform_fee'])
                                    : '—'; ?>
                            </td>
                            <td class="ad-nowrap"><?php echo !empty($r['ao_buyer_confirmed_at']) ? htmlspecialchars((string) $r['ao_buyer_confirmed_at'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td class="ad-nowrap">
                                <?php if (!empty($r['ao_refunded_at'])): ?>
                                    <?php echo htmlspecialchars((string) $r['ao_refunded_at'], ENT_QUOTES, 'UTF-8'); ?>
                                    <div class="ad-muted">₩<?php echo number_format((int) $r['ao_refund_amount']); ?></div>
                                <?php elseif (!empty($r['ao_refund_requested_at'])): ?>
                                    신청 <?php echo htmlspecialchars((string) $r['ao_refund_requested_at'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    —
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
                        <a href="/auction/admin/auction_settlements.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/auction/admin/auction_settlements.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/auction/admin/auction_settlements.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../admin/include/admin_footer.php'; ?>
