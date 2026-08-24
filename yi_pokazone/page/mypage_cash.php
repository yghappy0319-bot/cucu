<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash.php'));
}

$mb_idx = (int) $me['mb_idx'];

if (!member_cash_column_ready()) {
    alert_goto('캐시 기능이 준비되지 않았습니다. sql/migrate_tb_member_cash.sql 을 적용해 주세요.', '/page/mypage.php');
}
if (!member_cash_log_table_ready()) {
    alert_goto('캐시 내역 기능이 준비되지 않았습니다. sql/migrate_tb_cash_log.sql 을 적용해 주세요.', '/page/mypage.php');
}

$cash_bal     = member_cash_balance($mb_idx);
$type_labels  = member_cash_type_labels();
$page_num     = max(1, (int) ($_GET['p'] ?? 1));
$per_page     = 20;
$offset       = ($page_num - 1) * $per_page;
$log_total    = (int) db_result("SELECT COUNT(*) FROM tb_cash_log WHERE mb_idx = {$mb_idx}");
$total_page   = max(1, (int) ceil($log_total / $per_page));

$cash_rows = [];
$rs        = db_query("
    SELECT l.cl_change, l.cl_balance, l.cl_type, l.cl_memo, l.au_idx, l.ao_idx, l.cl_created_at,
           a.au_title
    FROM tb_cash_log l
    LEFT JOIN tb_auction a ON a.au_idx = l.au_idx
    WHERE l.mb_idx = {$mb_idx}
    ORDER BY l.cl_idx DESC
    LIMIT {$per_page} OFFSET {$offset}
");
while ($r = db_assoc($rs)) {
    $cash_rows[] = $r;
}

$page  = 'mypage_cash';
$title = '캐시 내역';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '캐시 내역', 'url' => '/page/mypage_cash.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage mypage-cash">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">캐시 내역</h1>
                <p class="board-desc">경매 결제·판매 정산 등 캐시 증감 내역을 확인할 수 있습니다.</p>
            </div>
            <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
        </div>

        <div class="attendance-summary mypage-cash-summary">
            <div class="attendance-stat-card">
                <span class="attendance-stat-label">보유 캐시</span>
                <strong class="attendance-stat-value mypage-cash-balance">₩<?php echo number_format($cash_bal); ?></strong>
            </div>
            <div class="mypage-cash-summary-actions">
                <a href="/page/mypage_cash_charge.php" class="btn btn-primary btn-sm">캐시 충전</a>
                <a href="/page/mypage_cash_withdraw.php" class="btn btn-outline btn-sm">판매금 출금</a>
            </div>
        </div>

        <h2 class="policy-h2 attendance-log-title">캐시 내역</h2>
        <?php if (empty($cash_rows)): ?>
            <div class="board-empty">
                <p>아직 캐시 내역이 없습니다. 경매에서 캐시로 결제하거나, 판매 정산을 받으면 이곳에 표시됩니다.</p>
            </div>
        <?php else: ?>
            <div class="attendance-log-table cash-log-table" role="table" aria-label="캐시 내역">
                <div class="attendance-log-row is-head" role="row">
                    <div role="columnheader">일시</div>
                    <div role="columnheader">유형</div>
                    <div role="columnheader">내용</div>
                    <div role="columnheader">증감</div>
                    <div role="columnheader">잔액</div>
                </div>
                <?php foreach ($cash_rows as $row):
                    $chg = (int) $row['cl_change'];
                    $typ = (string) $row['cl_type'];
                    $tl  = $type_labels[$typ] ?? $typ;
                    $au  = (int) ($row['au_idx'] ?? 0);
                    $settle_info = member_cash_log_settle_breakdown($row);
                ?>
                    <div class="attendance-log-row<?php echo $settle_info ? ' is-settle' : ''; ?>" role="row">
                        <div role="cell"><?php echo htmlspecialchars((string) $row['cl_created_at']); ?></div>
                        <div role="cell"><span class="point-type"><?php echo htmlspecialchars($tl); ?></span></div>
                        <div role="cell" class="attendance-log-memo">
                            <?php if ($settle_info): ?>
                                <span class="cash-log-settle-title"><?php echo htmlspecialchars($settle_info['title']); ?></span>
                                <span class="cash-log-settle-breakdown">
                                    <span class="cash-log-settle-item is-settle">
                                        정산 <strong>₩<?php echo number_format((int) $settle_info['settle']); ?></strong>
                                    </span>
                                    <span class="cash-log-settle-item is-fee">
                                        수수료 <strong>₩<?php echo number_format((int) $settle_info['fee']); ?></strong>
                                        <?php if ((int) $settle_info['fee_rate'] > 0): ?>
                                            <span class="cash-log-settle-rate">(<?php echo (int) $settle_info['fee_rate']; ?>%)</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ((int) $settle_info['gross'] > 0): ?>
                                        <span class="cash-log-settle-item is-gross">
                                            결제 ₩<?php echo number_format((int) $settle_info['gross']); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <?php echo htmlspecialchars((string) ($row['cl_memo'] ?? '—')); ?>
                            <?php endif; ?>
                            <?php if ($au > 0): ?>
                                <br><a href="/auction/auction_view.php?idx=<?php echo $au; ?>" class="mypage-inline-link">경매 보기</a>
                            <?php endif; ?>
                        </div>
                        <div role="cell" class="attendance-log-chg <?php echo $chg >= 0 ? 'is-plus' : 'is-minus'; ?>">
                            <?php echo $chg >= 0 ? '+' : ''; ?>₩<?php echo number_format(abs($chg)); ?>
                        </div>
                        <div role="cell">₩<?php echo number_format((int) $row['cl_balance']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="캐시 내역 페이지">
                    <?php for ($p = 1; $p <= $total_page; $p++): ?>
                        <a href="?p=<?php echo $p; ?>" class="page-link <?php echo $p === $page_num ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
