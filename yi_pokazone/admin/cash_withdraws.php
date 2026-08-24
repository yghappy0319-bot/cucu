<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';

$title     = '출금 신청';
$ad_topbar = $ad;
$ad_menu   = 'cash_withdraws';
include __DIR__ . '/include/admin_header.php';

$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$status_map = [
    'all' => '전체',
    '0'   => '출금 신청',
    '1'   => '출금 완료',
    '2'   => '출금 취소',
];
if (!isset($status_map[$st])) {
    $st = 'all';
}

$ready = member_cash_withdraw_table_ready() && db_table_exists('tb_member');
$has_amt = $ready && member_cash_withdraw_amount_columns_ready();
$has_fee = $ready && member_cash_withdraw_fee_table_ready();
$rows  = [];
$total = 0;
$total_page = 1;
$pending_sum = 0;

if ($ready) {
    $where = ['1=1'];
    if ($st === '0' || $st === '1' || $st === '2') {
        $where[] = 'w.cw_status = ' . (int) $st;
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(w.mb_idx = ' . (int) $q . ' OR w.cw_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $where[] = "(m.mb_id LIKE '%{$e}%' OR m.mb_nick LIKE '%{$e}%'
                OR w.cw_bank LIKE '%{$e}%' OR w.cw_holder LIKE '%{$e}%' OR w.cw_account LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("
        SELECT COUNT(*)
        FROM tb_cash_withdraw w
        LEFT JOIN tb_member m ON m.mb_idx = w.mb_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $pending_sum = (int) db_result("
        SELECT COALESCE(SUM(" . ($has_amt ? 'w.cw_net_amount' : 'w.cw_amount') . "), 0)
        FROM tb_cash_withdraw w
        WHERE w.cw_status = " . MEMBER_CASH_WITHDRAW_STATUS_PENDING . "
    ");

    $amt_sel = $has_amt
        ? 'w.cw_gross_amount, w.cw_fee_amount, w.cw_net_amount'
        : 'w.cw_amount AS cw_gross_amount, 0 AS cw_fee_amount, w.cw_amount AS cw_net_amount';
    $fee_join = $has_fee
        ? 'LEFT JOIN tb_cash_withdraw_fee f ON f.cw_idx = w.cw_idx'
        : '';
    $fee_sel = $has_fee
        ? ', f.cwf_source_type, f.pay_idx, f.tr_idx, f.cwf_product_label'
        : ", 'general' AS cwf_source_type, NULL AS pay_idx, NULL AS tr_idx, '' AS cwf_product_label";

    $rs = db_query("
        SELECT w.cw_idx, w.mb_idx, w.cw_amount, w.cw_bank, w.cw_holder, w.cw_account,
               w.cw_status, w.cw_memo, w.cw_created_at, w.cw_processed_at,
               {$amt_sel}
               {$fee_sel},
               m.mb_id, m.mb_nick
        FROM tb_cash_withdraw w
        LEFT JOIN tb_member m ON m.mb_idx = w.mb_idx
        {$fee_join}
        WHERE {$where_sql}
        ORDER BY w.cw_idx DESC
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

$source_labels = [
    'general' => '일반',
    'trade'   => '거래',
    'auction' => '경매',
];
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            tb_cash_withdraw 테이블이 없습니다.
            <code>sql/migrate_tb_cash_withdraw.sql</code> 을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">출금 신청</h1>
            <p class="ad-p" style="margin-top:0;">
                회원이 신청한 캐시 출금 내역입니다. 신청 시 캐시가 차감되며,
                <strong>출금 완료</strong>는 송금 후 상태만 변경하고,
                <strong>취소</strong> 시 차감 캐시를 환불합니다.
            </p>
            <p class="ad-p" style="margin-top:0;">
                대기 중 출금액 합계: <strong>₩<?php echo number_format($pending_sum); ?></strong>
            </p>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_st">상태</label>
                    <select id="f_st" name="st">
                        <?php foreach ($status_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">신청번호 / 회원번호 / 아이디·닉네임 / 계좌</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="검색">
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
                        <th>회원</th>
                        <th class="ad-num">출금액</th>
                        <th>입금 계좌</th>
                        <th>구분</th>
                        <th>상태</th>
                        <th class="ad-nowrap">신청일</th>
                        <th class="ad-nowrap">처리일</th>
                        <th>관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" class="ad-muted">출금 신청 내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $ist = (int) ($r['cw_status'] ?? 0);
                        $st_label = member_cash_withdraw_status_label($ist);
                        $net = (int) ($r['cw_net_amount'] ?? $r['cw_amount'] ?? 0);
                        $src = (string) ($r['cwf_source_type'] ?? 'general');
                        $src_label = $source_labels[$src] ?? $src;
                        $product = trim((string) ($r['cwf_product_label'] ?? ''));
                        $badge = 'ad-badge--warn';
                        if ($ist === MEMBER_CASH_WITHDRAW_STATUS_DONE) {
                            $badge = 'ad-badge--ok';
                        } elseif ($ist === MEMBER_CASH_WITHDRAW_STATUS_CANCELLED) {
                            $badge = 'ad-badge--bad';
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $r['cw_idx']; ?></td>
                            <td>
                                <a href="/admin/members.php?q=<?php echo (int) $r['mb_idx']; ?>">#<?php echo (int) $r['mb_idx']; ?></a>
                                <?php if (!empty($r['mb_nick']) || !empty($r['mb_id'])): ?>
                                    <span class="ad-muted"><?php echo htmlspecialchars($r['mb_nick'] ?: $r['mb_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-num">₩<?php echo number_format($net); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars((string) ($r['cw_bank'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="ad-muted" style="font-size:12px;">
                                    <?php echo htmlspecialchars((string) ($r['cw_holder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    ·
                                    <?php echo htmlspecialchars((string) ($r['cw_account'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($src_label, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($product !== ''): ?>
                                    <div class="ad-muted" style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                         title="<?php echo htmlspecialchars($product, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($product, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php elseif (!empty($r['pay_idx'])): ?>
                                    <div class="ad-muted" style="font-size:12px;">pay#<?php echo (int) $r['pay_idx']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ad-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($st_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (trim((string) ($r['cw_memo'] ?? '')) !== ''): ?>
                                    <div class="ad-muted" style="font-size:12px;margin-top:4px;"
                                         title="<?php echo htmlspecialchars((string) $r['cw_memo'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string) $r['cw_memo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) ($r['cw_created_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-muted ad-nowrap">
                                <?php
                                echo !empty($r['cw_processed_at'])
                                    ? htmlspecialchars(substr((string) $r['cw_processed_at'], 0, 16), ENT_QUOTES, 'UTF-8')
                                    : '—';
                                ?>
                            </td>
                            <td class="ad-nowrap">
                                <?php if ($ist === MEMBER_CASH_WITHDRAW_STATUS_PENDING): ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_cash_withdraw_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('출금 #<?php echo (int) $r['cw_idx']; ?>을(를) 완료 처리할까요?\n\n계좌로 ₩<?php echo number_format($net); ?> 송금이 끝났는지 확인해 주세요.');">
                                        <input type="hidden" name="cw_idx" value="<?php echo (int) $r['cw_idx']; ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="ad-btn ad-btn--sm">출금 완료</button>
                                    </form>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_cash_withdraw_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('출금 #<?php echo (int) $r['cw_idx']; ?>을(를) 취소할까요?\n\n₩<?php echo number_format((int) ($r['cw_gross_amount'] ?? $net)); ?>이 회원 캐시로 환불됩니다.');">
                                        <input type="hidden" name="cw_idx" value="<?php echo (int) $r['cw_idx']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="ad-btn ad-btn--sm ad-btn--danger">취소</button>
                                    </form>
                                <?php else: ?>
                                    <span class="ad-muted">—</span>
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
                        <a href="/admin/cash_withdraws.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/cash_withdraws.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/cash_withdraws.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
