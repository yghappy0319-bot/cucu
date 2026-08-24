<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../trade/lib/_admin_trade_payment.php';

$view = isset($_GET['view']) ? trim((string) $_GET['view']) : 'day';
if (!in_array($view, ['day', 'month'], true)) {
    $view = 'day';
}

$page_self = '/admin/trade_fees_period.php?view=' . rawurlencode($view);
$title     = $view === 'month' ? '판매자 수수료 · 월별' : '판매자 수수료 · 일별';
$ad_topbar = $ad;
$ad_menu   = 'trade_fees_period';
$ad_fee_nav = $view;

$from_raw = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$to_raw   = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
$range    = admin_tp_fee_parse_date_range($from_raw, $to_raw);
$range_error = empty($range['ok']) ? (string) ($range['error'] ?? '') : '';
$from     = !empty($range['ok']) ? (string) ($range['from'] ?? '') : '';
$to       = !empty($range['ok']) ? (string) ($range['to'] ?? '') : '';

if ($from === '' && $to === '' && $range_error === '') {
    if ($view === 'day') {
        $from = date('Y-m-d', strtotime('-89 days'));
    } else {
        $from = date('Y-m-01', strtotime('-23 months'));
    }
}

$fee_table_ready = function_exists('trade_payment_fee_table_ready') && trade_payment_fee_table_ready();
$rows            = [];
$sum_gross       = 0;
$sum_fee         = 0;
$sum_settle      = 0;
$sum_count       = 0;
$db_error        = '';

if ($fee_table_ready && $range_error === '') {
    $where = admin_tp_fee_date_where_clauses($from, $to);
    $where_sql = $where !== [] ? implode(' AND ', $where) : '1=1';
    $period    = admin_tp_fee_period_group_sql($view);

    $sum_row = db_assoc(db_query("
        SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(f.tpf_gross_amount), 0) AS sum_gross,
            COALESCE(SUM(f.tpf_fee_amount), 0) AS sum_fee,
            COALESCE(SUM(f.tpf_seller_amount), 0) AS sum_settle
        FROM tb_trade_payment_fee f
        WHERE {$where_sql}
    "));
    if ($sum_row) {
        $sum_count  = (int) ($sum_row['cnt'] ?? 0);
        $sum_gross  = (int) ($sum_row['sum_gross'] ?? 0);
        $sum_fee    = (int) ($sum_row['sum_fee'] ?? 0);
        $sum_settle = (int) ($sum_row['sum_settle'] ?? 0);
    }

    $rs = db_query("
        SELECT
            {$period['select']},
            COUNT(*) AS fee_count,
            COALESCE(SUM(f.tpf_gross_amount), 0) AS sum_gross,
            COALESCE(SUM(f.tpf_fee_amount), 0) AS sum_fee,
            COALESCE(SUM(f.tpf_seller_amount), 0) AS sum_settle,
            COALESCE(SUM(CASE WHEN f.tpf_auto_confirmed = 1 THEN 1 ELSE 0 END), 0) AS auto_count,
            COALESCE(SUM(CASE WHEN f.tpf_auto_confirmed = 0 THEN 1 ELSE 0 END), 0) AS manual_count
        FROM tb_trade_payment_fee f
        WHERE {$where_sql}
        GROUP BY {$period['group']}
        ORDER BY {$period['order']}
    ");
    if ($rs === false) {
        $db_error = db_last_error();
    } else {
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
    }
}

$build_qs = static function (array $o) use ($view, $from, $to) {
    $base = ['view' => $view, 'from' => $from, 'to' => $to];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['from'] ?? '') === '') {
        unset($base['from']);
    }
    if (($base['to'] ?? '') === '') {
        unset($base['to']);
    }

    return '?' . http_build_query($base);
};

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$fee_table_ready): ?>
        <div class="ad-alert ad-alert--error">
            판매자 수수료 테이블이 없습니다.
            <code>sql/migrate_tb_trade_payment_fee.sql</code>을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <?php include __DIR__ . '/include/trade_fee_nav.php'; ?>
            <h1 class="ad-title"><?php echo $view === 'month' ? '판매자 수수료 · 월별 집계' : '판매자 수수료 · 일별 집계'; ?></h1>
            <p class="ad-muted" style="margin-top:0;">
                <?php if ($view === 'month'): ?>
                    구매확정 시 차감된 판매자 수수료를 월 단위로 합산합니다.
                <?php else: ?>
                    구매확정 시 차감된 판매자 수수료를 일 단위로 합산합니다.
                <?php endif; ?>
                기간을 비우면 <?php echo $view === 'month' ? '최근 24개월' : '최근 90일'; ?>이 기본입니다.
            </p>
            <?php if ($range_error !== ''): ?>
                <div class="ad-alert ad-alert--error"><?php echo htmlspecialchars($range_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($db_error !== ''): ?>
                <div class="ad-alert ad-alert--error">DB 조회 오류: <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form class="ad-toolbar ad-form" method="get" action="/admin/trade_fees_period.php">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ad-field">
                    <label for="f_from">시작<?php echo $view === 'month' ? '(월)' : '일'; ?></label>
                    <input type="date" id="f_from" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ad-field">
                    <label for="f_to">종료<?php echo $view === 'month' ? '(월)' : '일'; ?></label>
                    <input type="date" id="f_to" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">조회</button>
                </div>
            </form>
            <p class="ad-p" style="margin-top:0;">
                기간 내 <?php echo number_format($sum_count); ?>건
                · 결제합계 ₩<?php echo number_format($sum_gross); ?>
                · <strong>수수료합계 ₩<?php echo number_format($sum_fee); ?></strong>
                · 판매자정산합계 ₩<?php echo number_format($sum_settle); ?>
            </p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th><?php echo $view === 'month' ? '월' : '일자'; ?></th>
                        <th class="ad-num">건수</th>
                        <th class="ad-num">결제합계</th>
                        <th class="ad-num">수수료</th>
                        <th class="ad-num">판매자정산</th>
                        <th class="ad-num">수동</th>
                        <th class="ad-num">자동</th>
                        <th>상세</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="ad-muted">집계 내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $detail_url = admin_tp_fee_period_detail_url($view, $r); ?>
                        <tr>
                            <td>
                                <a href="<?php echo htmlspecialchars($detail_url, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) ($r['period_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td class="ad-num"><?php echo number_format((int) ($r['fee_count'] ?? 0)); ?></td>
                            <td class="ad-num">₩<?php echo number_format((int) ($r['sum_gross'] ?? 0)); ?></td>
                            <td class="ad-num"><strong>₩<?php echo number_format((int) ($r['sum_fee'] ?? 0)); ?></strong></td>
                            <td class="ad-num">₩<?php echo number_format((int) ($r['sum_settle'] ?? 0)); ?></td>
                            <td class="ad-num"><?php echo number_format((int) ($r['manual_count'] ?? 0)); ?></td>
                            <td class="ad-num"><?php echo number_format((int) ($r['auto_count'] ?? 0)); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($detail_url, ENT_QUOTES, 'UTF-8'); ?>">내역 보기</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
