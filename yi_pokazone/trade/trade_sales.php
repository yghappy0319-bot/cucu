<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_trade_listing.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/trade/trade_sales.php'));
}

$mb_idx     = (int) $me['mb_idx'];
$sales_tab  = trade_seller_listing_tab_normalize((string) ($_GET['tab'] ?? 'active'));
$page_num   = max(1, (int) ($_GET['p'] ?? 1));
$per_page   = 15;
$offset     = ($page_num - 1) * $per_page;

$count_active = trade_seller_listing_count($mb_idx, 'active');
$count_done   = trade_seller_listing_count($mb_idx, 'done');
$total        = trade_seller_listing_count($mb_idx, $sales_tab);
$total_page   = max(1, (int) ceil($total / $per_page));
if ($page_num > $total_page) {
    $page_num = $total_page;
    $offset   = ($page_num - 1) * $per_page;
}

$rows = trade_seller_listing_rows($mb_idx, $per_page, $offset, $sales_tab);
$sale_payments = [];
if (trade_chat_payment_lib_load() && function_exists('trade_seller_listing_payments_by_tr')) {
    $tr_ids = [];
    foreach ($rows as $r) {
        $tr_ids[] = (int) ($r['tr_idx'] ?? 0);
    }
    $sale_payments = trade_seller_listing_payments_by_tr($mb_idx, $tr_ids);
}
$point_balance = (int) ($me['mb_point'] ?? db_result("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"));
$bump_ready = trade_bump_column_ready();

$type_labels = [
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];
$deal_labels = [
    1 => ['label' => '판매중', 'class' => 'is-wait'],
    2 => ['label' => '거래중', 'class' => 'is-sent'],
    3 => ['label' => '거래완료', 'class' => 'is-done'],
];

$pending_ship = 0;
if (trade_chat_payment_lib_load() && function_exists('trade_sale_seller_pending_ship_count') && trade_purchase_tables_ready()) {
    $pending_ship = trade_sale_seller_pending_ship_count($mb_idx);
}

$sales_qs = static function (array $overrides = []) use ($sales_tab, $page_num): string {
    $arr = array_merge([
        'tab' => $sales_tab,
        'p'   => $page_num,
    ], $overrides);
    $pairs = [];
    foreach ($arr as $k => $v) {
        if ($v === '' || $v === null || ($k === 'p' && (int) $v <= 1)) {
            continue;
        }
        $pairs[] = $k . '=' . urlencode((string) $v);
    }

    return $pairs ? ('?' . implode('&', $pairs)) : '';
};

$page  = 'mypage';
$title = '판매내역';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '판매내역', 'url' => '/trade/trade_sales.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage trade-order-page trade-sales-page">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">판매내역</h1>
                <p class="board-desc">
                    <?php echo $sales_tab === 'done' ? '판매완료' : '판매중'; ?>
                    <?php echo number_format($total); ?>건
                    · 보유 포인트 <strong><?php echo number_format($point_balance); ?>P</strong>
                    <?php if ($pending_ship > 0): ?>
                        · <a href="/trade/trade_messages.php">발송 대기 <?php echo number_format($pending_ship); ?>건</a>
                    <?php endif; ?>
                </p>
                <?php if ($bump_ready && $sales_tab === 'active'): ?>
                    <p class="board-desc board-desc--sub">
                        끌올 시 거래게시판 상단에 노출되며, <?php echo number_format(trade_bump_point_cost()); ?>P가 차감됩니다.
                        <strong>게시글마다</strong> 하루에 한 번 끌올할 수 있습니다.
                    </p>
                <?php elseif (!$bump_ready && $sales_tab === 'active'): ?>
                    <p class="board-desc board-desc--sub alert-soft">끌올 기능 DB가 없습니다. 관리자에게 sql/migrate_tb_trade_bump.sql 적용을 요청해 주세요.</p>
                <?php endif; ?>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <a href="/trade/trade_write.php" class="btn btn-primary btn-sm">거래글 등록</a>
                <a href="/trade/trade_messages.php" class="btn btn-outline btn-sm">거래 메시지함</a>
                <a href="/trade/trade.php" class="btn btn-outline btn-sm">거래 둘러보기</a>
            </div>
        </div>

        <nav class="board-tabs trade-sales-tabs" aria-label="판매 상태">
            <a href="<?php echo htmlspecialchars($sales_qs(['tab' => 'active', 'p' => 1]), ENT_QUOTES, 'UTF-8'); ?>"
               class="board-tab<?php echo $sales_tab === 'active' ? ' is-active' : ''; ?>">
                판매중
                <?php if ($count_active > 0): ?>
                    <span class="trade-sales-tab-count"><?php echo number_format($count_active); ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($sales_qs(['tab' => 'done', 'p' => 1]), ENT_QUOTES, 'UTF-8'); ?>"
               class="board-tab<?php echo $sales_tab === 'done' ? ' is-active' : ''; ?>">
                판매완료
                <?php if ($count_done > 0): ?>
                    <span class="trade-sales-tab-count"><?php echo number_format($count_done); ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <?php if (empty($rows)): ?>
            <p class="mypage-empty">
                <?php if ($sales_tab === 'done'): ?>
                    판매완료된 거래글이 없습니다.
                    <?php if ($count_active > 0): ?>
                        <a href="<?php echo htmlspecialchars($sales_qs(['tab' => 'active', 'p' => 1]), ENT_QUOTES, 'UTF-8'); ?>">판매중 목록 보기</a>
                    <?php endif; ?>
                <?php else: ?>
                    판매중인 거래글이 없습니다.
                    <?php if ($count_done > 0): ?>
                        <a href="<?php echo htmlspecialchars($sales_qs(['tab' => 'done', 'p' => 1]), ENT_QUOTES, 'UTF-8'); ?>">판매완료 목록 보기</a>
                    <?php else: ?>
                        <a href="/trade/trade_write.php">거래글 등록하기</a>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <ul class="trade-order-list trade-sales-list">
                <?php foreach ($rows as $row):
                    $sales_list_tab = $sales_tab;
                    $sale_pay = $sale_payments[(int) ($row['tr_idx'] ?? 0)] ?? null;
                    include __DIR__ . '/include/trade_sales_list_row.php';
                endforeach; ?>
            </ul>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="판매내역 페이지">
                    <?php for ($p = 1; $p <= $total_page; $p++): ?>
                        <a href="<?php echo htmlspecialchars($sales_qs(['p' => $p]), ENT_QUOTES, 'UTF-8'); ?>" class="page-link <?php echo $p === $page_num ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
