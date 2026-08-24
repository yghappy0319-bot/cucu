<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_auction.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/auction/mypage_auction.php'));
}

if (!auction_table_ok()) {
    alert_goto('경매 DB 테이블이 없습니다.', '/page/mypage.php');
}

$mb_idx   = (int) $me['mb_idx'];
$page_num = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 15;
$offset   = ($page_num - 1) * $per_page;

$where = "mb_idx = {$mb_idx} AND au_status = 1";
$total = (int) db_result("SELECT COUNT(*) FROM tb_auction WHERE {$where}");
$total_page = max(1, (int) ceil($total / $per_page));
if ($page_num > $total_page) {
    $page_num = $total_page;
    $offset   = ($page_num - 1) * $per_page;
}

$rows = [];
$rs   = db_query("
    SELECT au_idx, au_title, au_current_price, au_auction_status, au_status,
           au_starts_at, au_ends_at, au_created_at
    FROM tb_auction
    WHERE {$where}
    ORDER BY au_idx DESC
    LIMIT {$per_page} OFFSET {$offset}
");
while ($r = db_assoc($rs)) {
    $rows[] = $r;
}

$page  = 'mypage';
$title = '나의 경매 내역';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '나의 경매 내역', 'url' => '/auction/mypage_auction.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">나의 경매 내역</h1>
                <p class="board-desc">내가 등록한 경매 글입니다. 총 <?php echo number_format($total); ?>건</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <a href="/auction/auction_write.php" class="btn btn-primary btn-sm">경매 등록</a>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <p class="mypage-empty">등록한 경매가 없습니다. <a href="/auction/auction_write.php">경매 등록하기</a></p>
        <?php else: ?>
            <ul class="mypage-recent mypage-recent--full">
                <?php foreach ($rows as $row): ?>
                    <li>
                        <a href="/auction/auction_view.php?idx=<?php echo (int) $row['au_idx']; ?>">
                            <span class="mypage-recent-main">
                                <span class="mypage-recent-title"><?php echo htmlspecialchars($row['au_title']); ?></span>
                                <span class="mypage-recent-meta">
                                    ₩<?php echo number_format((int) $row['au_current_price']); ?>
                                    · <?php echo htmlspecialchars(auction_mypage_status_text($row)); ?>
                                </span>
                            </span>
                            <time class="mypage-recent-date" datetime="<?php echo date('c', strtotime($row['au_created_at'])); ?>">
                                <?php echo date('Y-m-d', strtotime($row['au_created_at'])); ?>
                            </time>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="나의 경매 내역 페이지">
                    <?php for ($p = 1; $p <= $total_page; $p++): ?>
                        <a href="?p=<?php echo $p; ?>" class="page-link <?php echo $p === $page_num ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
