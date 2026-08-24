<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../trade/lib/_admin_trade_payment.php';

$page_self = '/admin/trade_payments.php';
$title     = '거래 결제내역';
$ad_topbar = $ad;
$ad_menu   = 'trade_payments';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : '';
$ful     = isset($_GET['ful']) ? trim((string) $_GET['ful']) : '';
$deleted   = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$confirmed = isset($_GET['confirmed']) && (string) $_GET['confirmed'] === '1';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$st_map  = admin_tp_pay_status_filter_options();
$ful_map = admin_tp_fulfill_filter_options();
if ($st !== '' && !isset($st_map[$st])) {
    $st = '';
}
if ($ful !== '' && !isset($ful_map[$ful])) {
    $ful = '';
}

$ready = db_table_exists('tb_trade_payment');
$rows  = [];
$total = 0;
$total_page = 1;
$sum_amount = 0;
$sum_fee    = 0;
$sum_settle = 0;
$sum_refund = 0;
$db_error   = '';
$fee_table_ready = function_exists('trade_payment_fee_table_ready') && trade_payment_fee_table_ready();
$fulfill_ready   = function_exists('trade_payment_fulfill_column_ready') && trade_payment_fulfill_column_ready();
$fee_col_ready   = function_exists('trade_payment_platform_fee_column_ready') && trade_payment_platform_fee_column_ready();
$settle_col_ready = function_exists('trade_payment_pay_column_exists') && trade_payment_pay_column_exists('pay_seller_settle_amount');
$refund_col_ready = function_exists('trade_payment_sale_cancel_column_ready') && trade_payment_sale_cancel_column_ready();
$trade_table_ready = db_table_exists('tb_trade');

if ($ready) {
    $where = ['1=1'];
    switch ($st) {
        case 'pending':
            $where[] = 'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_PENDING;
            break;
        case 'submitted':
            $where[] = 'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_SUBMITTED;
            break;
        case 'confirmed':
            $where[] = 'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_CONFIRMED;
            break;
        case 'cancelled':
            $where[] = 'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_CANCELLED;
            break;
        case 'sale_cancel':
            $where[] = 'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_SALE_CANCELLED;
            break;
    }
    if ($fulfill_ready) {
        switch ($ful) {
            case 'wait':
                $where[] = 'p.pay_status = ' . (int) TRADE_PAYMENT_STATUS_CONFIRMED
                    . ' AND p.pay_fulfill_status < ' . (int) TRADE_PAYMENT_FULFILL_ADDR_SENT;
                break;
            case 'sent':
                $where[] = 'p.pay_fulfill_status = ' . (int) TRADE_PAYMENT_FULFILL_ADDR_SENT;
                break;
            case 'ship':
                $where[] = 'p.pay_fulfill_status = ' . (int) TRADE_PAYMENT_FULFILL_SHIPPING;
                break;
            case 'purchase':
                $where[] = 'p.pay_fulfill_status >= ' . (int) TRADE_PAYMENT_FULFILL_PURCHASE;
                break;
        }
    } elseif ($ful !== '') {
        $where[] = '1=0';
    }

    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(p.pay_idx = ' . (int) $q
                . ' OR p.tr_idx = ' . (int) $q
                . ' OR p.room_idx = ' . (int) $q
                . ' OR p.buyer_mb_idx = ' . (int) $q
                . ' OR p.seller_mb_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $title_like = $trade_table_ready ? " OR t.tr_title LIKE '%{$e}%'" : '';
            $where[] = "(p.product_label LIKE '%{$e}%'" . $title_like . "
                OR b.mb_id LIKE '%{$e}%' OR b.mb_nick LIKE '%{$e}%'
                OR s.mb_id LIKE '%{$e}%' OR s.mb_nick LIKE '%{$e}%'
                OR p.depositor_name LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $fee_join = $fee_table_ready
        ? 'LEFT JOIN tb_trade_payment_fee f ON f.pay_idx = p.pay_idx'
        : '';
    $fee_select = $fee_table_ready
        ? ', f.tpf_fee_rate, f.tpf_fee_amount, f.tpf_seller_amount, f.tpf_gross_amount, f.tpf_auto_confirmed, f.tpf_created_at AS fee_created_at'
        : ', NULL AS tpf_fee_rate, NULL AS tpf_fee_amount, NULL AS tpf_seller_amount, NULL AS tpf_gross_amount, NULL AS tpf_auto_confirmed, NULL AS fee_created_at';

    $trade_join = $trade_table_ready ? 'LEFT JOIN tb_trade t ON t.tr_idx = p.tr_idx' : '';
    $trade_select = $trade_table_ready ? ', t.tr_title' : ", '' AS tr_title";

    $from_sql = "
        FROM tb_trade_payment p
        {$trade_join}
        LEFT JOIN tb_member b ON b.mb_idx = p.buyer_mb_idx
        LEFT JOIN tb_member s ON s.mb_idx = p.seller_mb_idx
        {$fee_join}
        WHERE {$where_sql}
    ";

    $fee_expr_parts = ['0'];
    if ($fee_table_ready) {
        $fee_expr_parts[] = 'NULLIF(f.tpf_fee_amount, 0)';
    }
    if ($fee_col_ready) {
        $fee_expr_parts[] = 'p.pay_platform_fee';
    }
    $fee_expr = 'COALESCE(' . implode(', ', $fee_expr_parts) . ', 0)';

    $settle_expr_parts = ['0'];
    if ($fee_table_ready) {
        $settle_expr_parts[] = 'NULLIF(f.tpf_seller_amount, 0)';
    }
    if ($settle_col_ready) {
        $settle_expr_parts[] = 'p.pay_seller_settle_amount';
    }
    $settle_expr = 'COALESCE(' . implode(', ', $settle_expr_parts) . ', 0)';

    $refund_expr = $refund_col_ready
        ? 'COALESCE(NULLIF(p.pay_refund_amount, 0), p.pay_amount, 0)'
        : 'COALESCE(p.pay_amount, 0)';

    $total_rs = db_query('SELECT COUNT(*) ' . $from_sql);
    if ($total_rs === false) {
        $db_error = db_last_error();
    } else {
        $total = (int) db_result('SELECT COUNT(*) ' . $from_sql);
        $total_page = max(1, (int) ceil($total / $per));

        if ($fulfill_ready) {
            $st_purchase = (int) TRADE_PAYMENT_FULFILL_PURCHASE;
            $st_sale_cancel = (int) TRADE_PAYMENT_STATUS_SALE_CANCELLED;
            $sum_row = db_assoc(db_query("
                SELECT
                    COALESCE(SUM(p.pay_amount), 0) AS sum_amount,
                    COALESCE(SUM(CASE WHEN p.pay_fulfill_status >= {$st_purchase} THEN {$fee_expr} ELSE 0 END), 0) AS sum_fee,
                    COALESCE(SUM(CASE WHEN p.pay_fulfill_status >= {$st_purchase} THEN {$settle_expr} ELSE 0 END), 0) AS sum_settle,
                    COALESCE(SUM(CASE WHEN p.pay_status = {$st_sale_cancel} THEN {$refund_expr} ELSE 0 END), 0) AS sum_refund
                {$from_sql}
            "));
        } else {
            $sum_row = db_assoc(db_query("
                SELECT COALESCE(SUM(p.pay_amount), 0) AS sum_amount, 0 AS sum_fee, 0 AS sum_settle, 0 AS sum_refund
                {$from_sql}
            "));
        }
        if ($sum_row) {
            $sum_amount = (int) $sum_row['sum_amount'];
            $sum_fee    = (int) $sum_row['sum_fee'];
            $sum_settle = (int) $sum_row['sum_settle'];
            $sum_refund = (int) $sum_row['sum_refund'];
        }

        $rs = db_query("
            SELECT p.*
                   {$trade_select},
                   b.mb_id AS buyer_id, b.mb_nick AS buyer_nick,
                   s.mb_id AS seller_id, s.mb_nick AS seller_nick
                   {$fee_select}
            {$from_sql}
            ORDER BY p.pay_idx DESC
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

$build_qs = static function (array $o) use ($q, $st, $ful, $page_no) {
    $base = ['q' => $q, 'st' => $st, 'ful' => $ful, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if (($base['st'] ?? '') === '') {
        unset($base['st']);
    }
    if (($base['ful'] ?? '') === '') {
        unset($base['ful']);
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
        <div class="ad-alert ad-alert--error">tb_trade_payment 테이블이 없습니다. <code>sql/migrate_tb_trade_payment.sql</code>을 적용해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">거래 결제 내역</h1>
            <?php if ($deleted): ?>
                <div class="ad-alert ad-alert--done" role="status">결제 내역이 삭제되었습니다.</div>
            <?php endif; ?>
            <?php if ($confirmed): ?>
                <div class="ad-alert ad-alert--done" role="status">입금 확인 처리되었습니다. 해당 금액은 구매확정 전까지 예치됩니다.</div>
            <?php endif; ?>
            <?php if ($db_error !== ''): ?>
                <div class="ad-alert ad-alert--error">DB 조회 오류: <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form class="ad-toolbar ad-form" method="get" action="<?php echo htmlspecialchars($page_self, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ad-field">
                    <label for="f_st">결제상태</label>
                    <select id="f_st" name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($fulfill_ready): ?>
                <div class="ad-field">
                    <label for="f_ful">진행상태</label>
                    <select id="f_ful" name="ful">
                        <?php foreach ($ful_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ful === $k ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="결제# / 거래# / 회원# / 상품명 / 아이디·닉네임 / 입금자명">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>
            <p class="ad-p" style="margin-top:0;">
                총 <?php echo number_format($total); ?>건
                · 결제합계 ₩<?php echo number_format($sum_amount); ?>
                <?php if ($fulfill_ready): ?>
                    · 정산합계 ₩<?php echo number_format($sum_settle); ?>
                    · 수수료합계 ₩<?php echo number_format($sum_fee); ?>
                    · 환불합계 ₩<?php echo number_format($sum_refund); ?>
                <?php endif; ?>
            </p>
            <?php if (!$fee_table_ready && !$fee_col_ready): ?>
                <p class="ad-muted">수수료 컬럼·테이블이 없습니다. <code>sql/migrate_tb_trade_payment_fee.sql</code>을 적용하면 수수료 내역이 표시됩니다.</p>
            <?php endif; ?>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>결제#</th>
                        <th>거래글</th>
                        <th>상품</th>
                        <th>구매자</th>
                        <th>판매자</th>
                        <th class="ad-num">결제액</th>
                        <th>결제상태</th>
                        <?php if ($fulfill_ready): ?>
                            <th>진행상태</th>
                        <?php endif; ?>
                        <th class="ad-num">수수료</th>
                        <th class="ad-num">판매자정산</th>
                        <th class="ad-nowrap">구매확정</th>
                        <th class="ad-nowrap">등록</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?php echo $fulfill_ready ? 13 : 12; ?>" class="ad-muted">내역이 없습니다.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $settled    = admin_tp_is_settled($r);
                        $fee_amt    = admin_tp_fee_amount($r);
                        $settle_amt = admin_tp_seller_settle_amount($r);
                        $fee_rate   = admin_tp_fee_rate($r);
                        ?>
                        <tr>
                            <td>
                                <?php if ((int) ($r['pay_status'] ?? 0) === TRADE_PAYMENT_STATUS_CONFIRMED && (int) ($r['pay_idx'] ?? 0) > 0): ?>
                                    <a href="/trade/trade_payment_ship.php?pay_idx=<?php echo (int) $r['pay_idx']; ?>"
                                       target="_blank" rel="noopener">#<?php echo (int) $r['pay_idx']; ?></a>
                                <?php else: ?>
                                    #<?php echo (int) $r['pay_idx']; ?>
                                <?php endif; ?>
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
                                <a href="/admin/member_edit.php?idx=<?php echo (int) ($r['buyer_mb_idx'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string) (($r['buyer_nick'] ?? '') ?: ($r['buyer_id'] ?? '') ?: ('#' . ($r['buyer_mb_idx'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td>
                                <a href="/admin/member_edit.php?idx=<?php echo (int) ($r['seller_mb_idx'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string) (($r['seller_nick'] ?? '') ?: ($r['seller_id'] ?? '') ?: ('#' . ($r['seller_mb_idx'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td class="ad-num">₩<?php echo number_format((int) ($r['pay_amount'] ?? 0)); ?></td>
                            <td>
                                <span class="ad-badge <?php echo admin_tp_settle_state_class($r); ?>">
                                    <?php echo htmlspecialchars(admin_tp_settle_state_label($r), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <div class="ad-muted" style="font-size:12px;">
                                    <?php echo htmlspecialchars(trade_payment_status_label((int) ($r['pay_status'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <?php if ($fulfill_ready): ?>
                                <td><?php echo htmlspecialchars(trade_payment_fulfill_label($r), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endif; ?>
                            <td class="ad-num">
                                <?php if ($settled && $fee_amt > 0): ?>
                                    ₩<?php echo number_format($fee_amt); ?>
                                    <div class="ad-muted" style="font-size:12px;"><?php echo $fee_rate; ?>%</div>
                                <?php elseif ($settled): ?>
                                    ₩0
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="ad-num">
                                <?php echo $settled && $settle_amt > 0
                                    ? '₩' . number_format($settle_amt)
                                    : ($settled ? '₩0' : '—'); ?>
                            </td>
                            <td class="ad-nowrap">
                                <?php if (!empty($r['pay_buyer_confirmed_at'])): ?>
                                    <?php echo htmlspecialchars((string) $r['pay_buyer_confirmed_at'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($r['tpf_auto_confirmed'])): ?>
                                        <div class="ad-muted" style="font-size:12px;">자동</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) ($r['pay_created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <?php
                                $pay_st = (int) ($r['pay_status'] ?? 0);
                                $can_confirm_deposit = $pay_st === TRADE_PAYMENT_STATUS_SUBMITTED;
                                $del_confirm = '결제 #' . (int) ($r['pay_idx'] ?? 0) . '을(를) 삭제하시겠습니까?'
                                    . '\n\n연관 채팅·수수료·캐시 내역도 함께 삭제됩니다.';
                                if ($settled) {
                                    $del_confirm .= '\n구매확정된 건은 판매자 캐시 정산액이 회수됩니다.';
                                } elseif (trade_payment_is_sale_cancelled_status($pay_st)) {
                                    $del_confirm .= '\n판매취소 환불 건은 구매자 캐시가 회수됩니다.';
                                }
                                $del_confirm .= '\n\n되돌릴 수 없습니다.';
                                $confirm_msg = '결제 #' . (int) ($r['pay_idx'] ?? 0)
                                    . ' (₩' . number_format((int) ($r['pay_amount'] ?? 0)) . ')을 입금확인 처리할까요?'
                                    . '\n\n주문이 입금확인완료로 바뀌고, 구매자·판매자에게 알림이 갑니다.'
                                    . '\n금액은 구매확정 전까지 포카존에 예치됩니다. 판매자 캐시로는 아직 지급되지 않습니다.';
                                ?>
                                <div class="ad-actions">
                                    <?php if ($can_confirm_deposit): ?>
                                        <form method="post"
                                              action="/proc/admin_trade_payment_confirm_proc.php"
                                              class="ad-inline-form"
                                              onsubmit="return confirm(<?php echo json_encode($confirm_msg, JSON_UNESCAPED_UNICODE); ?>);">
                                            <input type="hidden" name="pay_idx" value="<?php echo (int) ($r['pay_idx'] ?? 0); ?>">
                                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="st" value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="ful" value="<?php echo htmlspecialchars($ful, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="p" value="<?php echo (int) $page_no; ?>">
                                            <button type="submit" class="ad-btn ad-btn--primary ad-btn--sm">입금확인</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post"
                                          action="/proc/admin_trade_payment_delete_proc.php"
                                          class="ad-inline-form"
                                          onsubmit="return confirm(<?php echo json_encode($del_confirm, JSON_UNESCAPED_UNICODE); ?>);">
                                        <input type="hidden" name="pay_idx" value="<?php echo (int) ($r['pay_idx'] ?? 0); ?>">
                                        <button type="submit" class="ad-btn ad-btn--ghost ad-btn--danger">삭제</button>
                                    </form>
                                </div>
                            </td>
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
