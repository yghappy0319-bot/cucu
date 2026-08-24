<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../trade/lib/_admin_trade_payment.php';

$page_self = '/admin/trade_fees.php';
$title     = '판매자 수수료';
$ad_topbar = $ad;
$ad_menu   = 'trade_fees';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : '';
$from_raw = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$to_raw   = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;
$ad_fee_nav = 'list';

$range = admin_tp_fee_parse_date_range($from_raw, $to_raw);
$range_error = empty($range['ok']) ? (string) ($range['error'] ?? '') : '';
$from  = !empty($range['ok']) ? (string) ($range['from'] ?? '') : '';
$to    = !empty($range['ok']) ? (string) ($range['to'] ?? '') : '';

$st_map = admin_tp_fee_filter_options();
if ($st !== '' && !isset($st_map[$st])) {
    $st = '';
}

$fee_table_ready   = function_exists('trade_payment_fee_table_ready') && trade_payment_fee_table_ready();
$payment_ready     = db_table_exists('tb_trade_payment');
$trade_table_ready = db_table_exists('tb_trade');
$use_fee_table     = $fee_table_ready && $payment_ready;

$rows       = [];
$total      = 0;
$total_page = 1;
$sum_gross  = 0;
$sum_fee    = 0;
$sum_settle = 0;
$db_error   = '';

if ($use_fee_table && $range_error === '') {
    $where = ['1=1'];
    $where = array_merge($where, admin_tp_fee_date_where_clauses($from, $to));
    switch ($st) {
        case 'manual':
            $where[] = 'f.tpf_auto_confirmed = 0';
            break;
        case 'auto':
            $where[] = 'f.tpf_auto_confirmed = 1';
            break;
    }

    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(f.tpf_idx = ' . (int) $q
                . ' OR f.pay_idx = ' . (int) $q
                . ' OR f.tr_idx = ' . (int) $q
                . ' OR f.mb_idx = ' . (int) $q
                . ' OR p.buyer_mb_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $title_like = $trade_table_ready ? " OR t.tr_title LIKE '%{$e}%'" : '';
            $where[] = "(p.product_label LIKE '%{$e}%'" . $title_like . "
                OR seller.mb_id LIKE '%{$e}%' OR seller.mb_nick LIKE '%{$e}%'
                OR buyer.mb_id LIKE '%{$e}%' OR buyer.mb_nick LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $trade_join = $trade_table_ready ? 'LEFT JOIN tb_trade t ON t.tr_idx = f.tr_idx' : '';
    $trade_select = $trade_table_ready ? ', t.tr_title' : ", '' AS tr_title";

    $from_sql = "
        FROM tb_trade_payment_fee f
        INNER JOIN tb_trade_payment p ON p.pay_idx = f.pay_idx
        {$trade_join}
        LEFT JOIN tb_member seller ON seller.mb_idx = f.mb_idx
        LEFT JOIN tb_member buyer ON buyer.mb_idx = p.buyer_mb_idx
        WHERE {$where_sql}
    ";

    $total_rs = db_query('SELECT COUNT(*) ' . $from_sql);
    if ($total_rs === false) {
        $db_error = db_last_error();
    } else {
        $total      = (int) db_result('SELECT COUNT(*) ' . $from_sql);
        $total_page = max(1, (int) ceil($total / $per));

        $sum_row = db_assoc(db_query("
            SELECT
                COALESCE(SUM(f.tpf_gross_amount), 0) AS sum_gross,
                COALESCE(SUM(f.tpf_fee_amount), 0) AS sum_fee,
                COALESCE(SUM(f.tpf_seller_amount), 0) AS sum_settle
            {$from_sql}
        "));
        if ($sum_row) {
            $sum_gross  = (int) $sum_row['sum_gross'];
            $sum_fee    = (int) $sum_row['sum_fee'];
            $sum_settle = (int) $sum_row['sum_settle'];
        }

        $rs = db_query("
            SELECT f.*,
                   p.product_label, p.pay_amount, p.buyer_mb_idx, p.pay_buyer_confirmed_at,
                   seller.mb_id AS seller_id, seller.mb_nick AS seller_nick,
                   buyer.mb_id AS buyer_id, buyer.mb_nick AS buyer_nick
                   {$trade_select}
            {$from_sql}
            ORDER BY f.tpf_idx DESC
            LIMIT {$offset}, {$per}
        ");
        if ($rs === false) {
            $db_error = db_last_error();
        } else {
            while ($r = db_assoc($rs)) {
                $rows[] = $r;
            }
        }
    }
}

$build_qs = static function (array $o) use ($q, $st, $from, $to, $page_no) {
    $base = ['q' => $q, 'st' => $st, 'from' => $from, 'to' => $to, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if (($base['st'] ?? '') === '') {
        unset($base['st']);
    }
    if (($base['from'] ?? '') === '') {
        unset($base['from']);
    }
    if (($base['to'] ?? '') === '') {
        unset($base['to']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }

    return $base ? '?' . http_build_query($base) : '';
};

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$use_fee_table): ?>
        <div class="ad-alert ad-alert--error">
            판매자 수수료 테이블이 없습니다.
            <code>sql/migrate_tb_trade_payment_fee.sql</code>을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <?php include __DIR__ . '/include/trade_fee_nav.php'; ?>
            <h1 class="ad-title">판매자 수수료 내역</h1>
            <p class="ad-muted" style="margin-top:0;">
                구매확정 시 판매자 정산에서 차감된 플랫폼 수수료입니다. 출금 수수료가 아닙니다.
                (기본 수수료율 <?php echo (int) platform_fee_trade_percent(); ?>%)
            </p>
            <?php if ($range_error !== ''): ?>
                <div class="ad-alert ad-alert--error"><?php echo htmlspecialchars($range_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($db_error !== ''): ?>
                <div class="ad-alert ad-alert--error">DB 조회 오류: <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form class="ad-toolbar ad-form" method="get" action="<?php echo htmlspecialchars($page_self, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ad-field">
                    <label for="f_st">확정방식</label>
                    <select id="f_st" name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label for="f_from">시작일</label>
                    <input type="date" id="f_from" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ad-field">
                    <label for="f_to">종료일</label>
                    <input type="date" id="f_to" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="수수료# / 결제# / 거래# / 판매자# / 상품명 / 아이디·닉네임">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>
            <p class="ad-p" style="margin-top:0;">
                총 <?php echo number_format($total); ?>건
                · 결제합계 ₩<?php echo number_format($sum_gross); ?>
                · <strong>수수료합계 ₩<?php echo number_format($sum_fee); ?></strong>
                · 판매자정산합계 ₩<?php echo number_format($sum_settle); ?>
            </p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>수수료#</th>
                        <th>결제#</th>
                        <th>거래글</th>
                        <th>상품</th>
                        <th>판매자</th>
                        <th>구매자</th>
                        <th class="ad-num">결제액</th>
                        <th class="ad-num">수수료율</th>
                        <th class="ad-num">수수료</th>
                        <th class="ad-num">판매자정산</th>
                        <th>확정방식</th>
                        <th class="ad-nowrap">발생일</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="12" class="ad-muted">수수료 내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>#<?php echo (int) ($r['tpf_idx'] ?? 0); ?></td>
                            <td>
                                <a href="/trade/trade_payment_ship.php?pay_idx=<?php echo (int) ($r['pay_idx'] ?? 0); ?>"
                                   target="_blank" rel="noopener">#<?php echo (int) ($r['pay_idx'] ?? 0); ?></a>
                            </td>
                            <td class="ad-title-cell">
                                <?php if ((int) ($r['tr_idx'] ?? 0) > 0): ?>
                                    <a href="/trade/trade_view.php?idx=<?php echo (int) $r['tr_idx']; ?>"
                                       target="_blank" rel="noopener">
                                        #<?php echo (int) $r['tr_idx']; ?>
                                        <?php if (!empty($r['tr_title'])): ?>
                                            <?php echo htmlspecialchars((string) $r['tr_title'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($r['product_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="/admin/member_edit.php?idx=<?php echo (int) ($r['mb_idx'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string) (($r['seller_nick'] ?? '') ?: ($r['seller_id'] ?? '') ?: ('#' . ($r['mb_idx'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td>
                                <a href="/admin/member_edit.php?idx=<?php echo (int) ($r['buyer_mb_idx'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string) (($r['buyer_nick'] ?? '') ?: ($r['buyer_id'] ?? '') ?: ('#' . ($r['buyer_mb_idx'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td class="ad-num">₩<?php echo number_format((int) ($r['tpf_gross_amount'] ?? 0)); ?></td>
                            <td class="ad-num"><?php echo (int) ($r['tpf_fee_rate'] ?? 0); ?>%</td>
                            <td class="ad-num"><strong>₩<?php echo number_format((int) ($r['tpf_fee_amount'] ?? 0)); ?></strong></td>
                            <td class="ad-num">₩<?php echo number_format((int) ($r['tpf_seller_amount'] ?? 0)); ?></td>
                            <td>
                                <?php if (!empty($r['tpf_auto_confirmed'])): ?>
                                    <span class="ad-badge ad-badge--muted">자동</span>
                                <?php else: ?>
                                    <span class="ad-badge ad-badge--ok">수동</span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) ($r['tpf_created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="<?php echo htmlspecialchars($page_self . $build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($page_self . $build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="<?php echo htmlspecialchars($page_self . $build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
