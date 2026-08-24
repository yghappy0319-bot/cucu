<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../trade/lib/_trade_bank_deposit_log.php';

$title     = '무통장 입금 내역';
$ad_topbar = $ad;
$ad_menu   = 'trade_bank_deposits';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 30;
$offset  = ($page_no - 1) * $per;

$st_map = ['' => '전체'] + trade_bank_deposit_log_status_labels();
if ($st !== '' && !isset($st_map[$st])) {
    $st = '';
}

$ready = trade_bank_deposit_log_table_ready();
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st !== '') {
        $where[] = "l.bdl_status = '" . db_escape($st) . "'";
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $n = (int) $q;
            $where[] = "(l.bdl_amount = {$n} OR l.pay_idx = {$n} OR l.bdl_idx = {$n})";
        } else {
            $e = db_escape($q);
            $where[] = "(l.bdl_depositor LIKE '%{$e}%' OR l.bdl_expected_depositor LIKE '%{$e}%'
                OR l.bdl_result_msg LIKE '%{$e}%' OR l.bdl_bank_msg LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total = (int) db_result("SELECT COUNT(*) FROM tb_trade_bank_deposit_log l WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $pay_ready = db_table_exists('tb_trade_payment');
    $join_pay  = $pay_ready
        ? 'LEFT JOIN tb_trade_payment p ON p.pay_idx = l.pay_idx'
        : '';
    $pay_cols  = $pay_ready
        ? ', p.product_label, p.pay_status, p.buyer_mb_idx, p.seller_mb_idx'
        : '';

    $rs = db_query("
        SELECT l.*{$pay_cols}
        FROM tb_trade_bank_deposit_log l
        {$join_pay}
        WHERE {$where_sql}
        ORDER BY l.bdl_idx DESC
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

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            <code>tb_trade_bank_deposit_log</code> 테이블이 없습니다.
            <code>sql/migrate_tb_trade_bank_deposit_log.sql</code> 을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">무통장 입금 내역</h1>
            <p class="ad-p" style="margin-top:0;">
                은행·자동화에서 <code>/trade/cron/trade_bank_deposit_confirm.php</code> 로 수신된 입금 알림 기록입니다.
                입금자명·금액이 신청과 다를 경우 <strong>미매칭</strong>으로 남습니다.
                엔드포인트 호출 이력은 <a href="/admin/cron_job_logs.php?job=trade_bank_deposit_confirm">크론 실행 로그</a>에서 확인할 수 있습니다.
            </p>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_st">처리 상태</label>
                    <select id="f_st" name="st">
                        <?php foreach ($st_map as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $st === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="입금자명 / 금액 / pay# / 메시지">
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
                        <th class="ad-nowrap">수신 일시</th>
                        <th>수신 입금자</th>
                        <th class="ad-num">수신 금액</th>
                        <th>신청 입금자</th>
                        <th>처리</th>
                        <th>결제</th>
                        <th>결과·메모</th>
                        <th>은행 원문</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="9" class="ad-muted">내역이 없습니다.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $status = (string) ($r['bdl_status'] ?? '');
                        $status_label = trade_bank_deposit_log_status_label($status);
                        $status_class = 'ad-muted';
                        if ($status === 'confirmed') {
                            $status_class = 'ad-badge ad-badge--ok';
                        } elseif ($status === 'unmatched') {
                            $status_class = 'ad-badge ad-badge--warn';
                        } elseif ($status === 'error') {
                            $status_class = 'ad-badge ad-badge--bad';
                        }
                        $pay_idx = (int) ($r['pay_idx'] ?? 0);
                        $result_msg = (string) ($r['bdl_result_msg'] ?? '');
                        if ($result_msg === '' && $status === 'confirmed') {
                            $result_msg = '입금이 확인되었습니다.';
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $r['bdl_idx']; ?></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) $r['bdl_created_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['bdl_depositor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['bdl_amount']); ?></td>
                            <td>
                                <?php
                                $expected = trim((string) ($r['bdl_expected_depositor'] ?? ''));
                                echo $expected !== ''
                                    ? htmlspecialchars($expected, ENT_QUOTES, 'UTF-8')
                                    : '<span class="ad-muted">—</span>';
                                ?>
                            </td>
                            <td><span class="<?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td>
                                <?php if ($pay_idx > 0): ?>
                                    <a href="/admin/trade_payments.php?q=<?php echo $pay_idx; ?>">#<?php echo $pay_idx; ?></a>
                                    <?php if (!empty($r['product_label'])): ?>
                                        <div class="ad-muted ad-title-cell" title="<?php echo htmlspecialchars((string) $r['product_label'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string) $r['product_label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="ad-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars($result_msg, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($result_msg, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars((string) $r['bdl_bank_msg'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php
                                $bank_msg = trim((string) ($r['bdl_bank_msg'] ?? ''));
                                echo $bank_msg !== ''
                                    ? htmlspecialchars(mb_strlen($bank_msg) > 40 ? mb_substr($bank_msg, 0, 40) . '…' : $bank_msg, ENT_QUOTES, 'UTF-8')
                                    : '<span class="ad-muted">—</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="ad-pager" aria-label="페이지">
                    <?php if ($page_no > 1): ?>
                        <a href="<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <span><?php echo $page_no; ?> / <?php echo $total_page; ?></span>
                    <?php if ($page_no < $total_page): ?>
                        <a href="<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
