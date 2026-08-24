<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/trade/trade_purchases.php'));
}

if (!trade_chat_payment_lib_load() || !function_exists('trade_purchase_list_for_buyer')) {
    alert_goto(
        '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 최신 파일을 서버에 업로드해 주세요.',
        '/page/mypage.php'
    );
}

if (!trade_purchase_tables_ready()) {
    alert_goto('구매내역을 보려면 sql/migrate_tb_trade_payment.sql 을 적용해 주세요.', '/page/mypage.php');
}

$mb_idx   = (int) $me['mb_idx'];
$page_num = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 15;
$offset   = ($page_num - 1) * $per_page;

$total      = trade_purchase_buyer_count($mb_idx);
$total_page = max(1, (int) ceil($total / $per_page));
if ($page_num > $total_page) {
    $page_num = $total_page;
    $offset   = ($page_num - 1) * $per_page;
}

$rows = trade_purchase_list_for_buyer($mb_idx, $per_page, $offset);

$page  = 'mypage';
$title = '구매내역';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '구매내역', 'url' => '/trade/trade_purchases.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage trade-order-page">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">구매내역</h1>
                <p class="board-desc">채팅·바로구매로 결제한 거래 내역입니다. 총 <?php echo number_format($total); ?>건</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <a href="/trade/trade_messages.php" class="btn btn-outline btn-sm">거래 메시지함</a>
                <a href="/trade/trade.php" class="btn btn-outline btn-sm">거래 둘러보기</a>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <p class="mypage-empty">구매 내역이 없습니다. <a href="/trade/trade.php">거래게시판 보기</a></p>
        <?php else: ?>
            <ul class="trade-order-list">
                <?php foreach ($rows as $row):
                    $role = 'buyer';
                    $buyer_mb_idx = $mb_idx;
                    include __DIR__ . '/include/trade_order_list_row.php';
                endforeach; ?>
            </ul>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="구매내역 페이지">
                    <?php for ($p = 1; $p <= $total_page; $p++): ?>
                        <a href="?p=<?php echo $p; ?>" class="page-link <?php echo $p === $page_num ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
