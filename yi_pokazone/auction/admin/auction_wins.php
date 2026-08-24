<?php
require_once __DIR__ . '/../../admin/include/admin_init.php';
require_once __DIR__ . '/../lib/_admin_auction.php';

$title     = '낙찰내역';
$ad_topbar = $ad;
$ad_menu   = 'auction_wins';
include __DIR__ . '/../../admin/include/admin_header.php';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$ast     = isset($_GET['ast']) ? trim((string) $_GET['ast']) : '';
$pay     = isset($_GET['pay']) ? trim((string) $_GET['pay']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$ast_map = admin_au_win_filter_options();
$pay_map = [
    ''  => '결제 전체',
    '0' => '미결제',
    '1' => '결제완료',
];

if ($ast !== '' && !isset($ast_map[$ast])) {
    $ast = '';
}
if ($pay !== '' && !isset($pay_map[$pay])) {
    $pay = '';
}

$ready = db_table_exists('tb_auction');
$has_order = db_table_exists('tb_auction_order');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['a.au_winner_mb_idx IS NOT NULL', 'a.au_winner_mb_idx > 0'];
    if ($ast === '3' || $ast === '4' || $ast === '2') {
        $where[] = 'a.au_auction_status = ' . (int) $ast;
    }
    if ($pay === '0' || $pay === '1') {
        if ($has_order) {
            if ($pay === '1') {
                $where[] = 'o.ao_pay_status = 1';
            } else {
                $where[] = '(o.ao_idx IS NULL OR o.ao_pay_status = 0)';
            }
        } elseif ($pay === '1') {
            $where[] = '1=0';
        }
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(a.au_idx = ' . (int) $q . ' OR a.au_winner_mb_idx = ' . (int) $q . ' OR a.mb_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $where[] = "(a.au_title LIKE '%{$e}%' OR w.mb_id LIKE '%{$e}%' OR w.mb_nick LIKE '%{$e}%'
                OR s.mb_id LIKE '%{$e}%' OR s.mb_nick LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);
    $join_order = $has_order
        ? 'LEFT JOIN tb_auction_order o ON o.au_idx = a.au_idx'
        : '';

    $total      = (int) db_result("
        SELECT COUNT(*)
        FROM tb_auction a
        {$join_order}
        LEFT JOIN tb_member w ON w.mb_idx = a.au_winner_mb_idx
        LEFT JOIN tb_member s ON s.mb_idx = a.mb_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $order_cols = $has_order
        ? ', o.ao_idx, o.ao_pay_status, o.ao_order_status, o.ao_paid_at, o.ao_buyer_confirmed_at,
           o.ao_refund_requested_at, o.ao_refunded_at'
        : '';

    $rs = db_query("
        SELECT a.au_idx, a.au_title, a.au_current_price, a.au_auction_status, a.au_ends_at,
               a.au_winner_mb_idx, a.mb_idx AS seller_mb_idx,
               w.mb_id AS winner_id, w.mb_nick AS winner_nick,
               s.mb_id AS seller_id, s.mb_nick AS seller_nick
               {$order_cols}
        FROM tb_auction a
        {$join_order}
        LEFT JOIN tb_member w ON w.mb_idx = a.au_winner_mb_idx
        LEFT JOIN tb_member s ON s.mb_idx = a.mb_idx
        WHERE {$where_sql}
        ORDER BY a.au_ends_at DESC, a.au_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($q, $ast, $pay, $page_no) {
    $base = ['q' => $q, 'ast' => $ast, 'pay' => $pay, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if (($base['ast'] ?? '') === '') {
        unset($base['ast']);
    }
    if (($base['pay'] ?? '') === '') {
        unset($base['pay']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_auction 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">낙찰 내역</h1>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>경매상태</label>
                    <select name="ast">
                        <?php foreach ($ast_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ast === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($has_order): ?>
                <div class="ad-field">
                    <label>결제</label>
                    <select name="pay">
                        <?php foreach ($pay_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $pay === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="경매# / 회원# / 제목 / 아이디·닉네임">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>
            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건 (낙찰자 지정 경매)</p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>경매#</th>
                        <th>제목</th>
                        <th>판매자</th>
                        <th>낙찰자</th>
                        <th class="ad-num">낙찰가</th>
                        <th>경매상태</th>
                        <?php if ($has_order): ?>
                        <th>주문·정산</th>
                        <th class="ad-nowrap">마감일</th>
                        <?php else: ?>
                        <th class="ad-nowrap">마감일</th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?php echo $has_order ? 8 : 7; ?>" class="ad-muted">내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo (int) $r['au_idx']; ?></td>
                            <td class="ad-title-cell">
                                <a href="/auction/auction_view.php?idx=<?php echo (int) $r['au_idx']; ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars((string) $r['au_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['seller_nick'] ?: $r['seller_id'] ?: ('#' . $r['seller_mb_idx'])), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['winner_nick'] ?: $r['winner_id'] ?: ('#' . $r['au_winner_mb_idx'])), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['au_current_price']); ?></td>
                            <td><?php echo htmlspecialchars(admin_au_auction_status_label((int) $r['au_auction_status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($has_order): ?>
                            <td>
                                <?php if (!empty($r['ao_idx'])): ?>
                                    <a href="/auction/auction_order.php?au_idx=<?php echo (int) $r['au_idx']; ?>" target="_blank" rel="noopener">주문 #<?php echo (int) $r['ao_idx']; ?></a>
                                    <div>
                                        <span class="ad-badge <?php echo admin_au_settle_state_class($r); ?>">
                                            <?php echo htmlspecialchars(admin_au_settle_state_label($r), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="ad-muted">주문 없음</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) $r['au_ends_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/auction/admin/auction_wins.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/auction/admin/auction_wins.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/auction/admin/auction_wins.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../admin/include/admin_footer.php'; ?>
