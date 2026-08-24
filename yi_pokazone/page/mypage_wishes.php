<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_wishes.php'));
}

if (!db_table_exists('tb_trade_like')) {
    alert_goto('찜 기능이 아직 설정되지 않았습니다.', '/page/mypage.php');
}

$mb_idx = (int) $me['mb_idx'];

$types = [
    ''         => '전체',
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];

$deal_status_labels = [
    1 => ['label' => '판매중',   'class' => 'ds-active'],
    2 => ['label' => '거래중',   'class' => 'ds-reserved'],
    3 => ['label' => '거래완료', 'class' => 'ds-done'],
];

$hide_done = !empty($_GET['active']);
$page_no   = max(1, (int) ($_GET['p'] ?? 1));
$per       = 12;
$offset    = ($page_no - 1) * $per;

$where = ["l.mb_idx = {$mb_idx}"];
if ($hide_done) {
    $where[] = 't.tr_deal_status <> 3';
}
$where_sql = implode(' AND ', $where);

$total = (int) db_result("
    SELECT COUNT(*)
    FROM tb_trade_like l
    INNER JOIN tb_trade t ON t.tr_idx = l.tr_idx
    WHERE {$where_sql}
");
$total_page = max(1, (int) ceil($total / $per));
if ($page_no > $total_page) {
    $page_no = $total_page;
    $offset  = ($page_no - 1) * $per;
}

$rs = db_query("
    SELECT t.*, m.mb_nick, l.liked_at,
           (SELECT ti_path FROM tb_trade_image
             WHERE tr_idx = t.tr_idx
             ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path
    FROM tb_trade_like l
    INNER JOIN tb_trade t ON t.tr_idx = l.tr_idx
    LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
    WHERE {$where_sql}
    ORDER BY l.liked_at DESC
    LIMIT {$offset}, {$per}
");

$rows = [];
while ($r = db_assoc($rs)) {
    $rows[] = $r;
}

$qs = static function (array $overrides = []) use ($hide_done, $page_no) {
    $arr = array_merge([
        'active' => $hide_done ? 1 : '',
        'p'      => $page_no,
    ], $overrides);
    $pairs = [];
    foreach ($arr as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $pairs[] = $k . '=' . urlencode((string) $v);
    }

    return $pairs ? '?' . implode('&', $pairs) : '';
};

$return_url = '/page/mypage_wishes.php' . $qs();

$page  = 'mypage_wishes';
$title = '찜 리스트';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '찜 리스트', 'url' => '/page/mypage_wishes.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage mypage-wishes community trade-board">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">찜 리스트</h1>
                <p class="board-desc">관심 있는 거래 상품을 모아두었습니다. 찜을 해제하면 목록에서 사라집니다.</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <a href="/trade/trade.php" class="btn btn-primary btn-sm">거래 둘러보기</a>
            </div>
        </div>

        <div class="mypage-wish-summary" data-mypage-wish-summary>
            <div class="mypage-wish-summary__inner">
                <div class="mypage-wish-summary__icon" aria-hidden="true">♥</div>
                <div>
                    <p class="mypage-wish-summary__label">찜한 상품</p>
                    <p class="mypage-wish-summary__count"><strong data-mypage-wish-total><?php echo number_format($total); ?></strong>개</p>
                </div>
            </div>
        </div>

        <form class="board-search mypage-wish-filter" method="get" action="/page/mypage_wishes.php">
            <label class="toggle-active">
                <input type="checkbox" name="active" value="1" <?php echo $hide_done ? 'checked' : ''; ?> onchange="this.form.submit()">
                <span>거래완료 숨김</span>
            </label>
        </form>

        <?php if (empty($rows)): ?>
            <div class="board-list" data-mypage-wish-empty>
                <div class="board-empty">
                    <div class="empty-emoji">♡</div>
                    <p>찜한 상품이 없습니다.<br><a href="/trade/trade.php">거래게시판</a>에서 마음에 드는 상품을 찜해 보세요.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="trade-grid mypage-wish-grid" data-mypage-wish-grid>
                <?php foreach ($rows as $row):
                    $ds = $deal_status_labels[(int) $row['tr_deal_status']] ?? $deal_status_labels[1];
                    $ds['label'] = trade_deal_status_label((int) $row['tr_deal_status'], $row['tr_type'] ?? 'sell');
                    $type_label = $types[$row['tr_type']] ?? '판매';
                    $ts = strtotime($row['liked_at']);
                    $is_box = $row['tr_item_type'] === 'box';
                    $img    = $row['thumb_path'] ?? '';
                    $ck     = !$is_box && !empty($row['tr_card_kind']) ? $row['tr_card_kind'] : '';
                    $ck_lbl = $ck === 'graded' ? '등급' : ($ck === 'single' ? '싱글' : '');
                    $is_hidden = trade_is_deleted_status($row);
                    $is_held_wish = trade_is_held($row);
                    ?>
                    <div class="trade-item <?php echo htmlspecialchars(trade_list_item_class($row, $is_hidden ? 'is-hidden-trade' : ''), ENT_QUOTES, 'UTF-8'); ?>" data-tr-idx="<?php echo (int) $row['tr_idx']; ?>">
                        <form action="/trade/proc/trade_like_proc.php" method="post" class="trade-wish-form" data-mypage-wish-form>
                            <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_url); ?>">
                            <button type="submit"
                                    class="trade-wish-btn is-wished"
                                    aria-label="찜 해제"
                                    aria-pressed="true">
                                <span aria-hidden="true">♥</span>
                            </button>
                        </form>
                        <a href="/trade/trade_view.php?idx=<?php echo (int) $row['tr_idx']; ?>" class="trade-item-link">
                            <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
                                <div class="trade-thumb-tags">
                                    <span class="trade-type type-<?php echo htmlspecialchars($row['tr_type']); ?>">
                                        <?php echo htmlspecialchars($type_label); ?>
                                    </span>
                                    <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                                        <?php echo $is_box ? '📦 상자' : '카드'; ?>
                                    </span>
                                    <?php if ($ck_lbl !== ''): ?>
                                        <span class="item-kind-chip item-<?php echo htmlspecialchars($ck); ?>"><?php echo htmlspecialchars($ck_lbl); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_held_wish): ?>
                                    <span class="deal-status ds-hold">게시중지</span>
                                <?php elseif ($is_hidden): ?>
                                    <span class="deal-status ds-hidden">비공개</span>
                                <?php else: ?>
                                <span class="deal-status <?php echo $ds['class']; ?>"><?php echo $ds['label']; ?></span>
                                <?php endif; ?>

                                <?php if ($img): ?>
                                    <img class="trade-image"
                                         src="<?php echo htmlspecialchars(public_url($img)); ?>"
                                         alt="<?php echo htmlspecialchars(($is_box ? '미개봉 상자 ' : '포켓몬 카드 ') . $row['tr_card_name']); ?>"
                                         loading="lazy" decoding="async">
                                    <?php if ((int) $row['tr_image_count'] > 1): ?>
                                        <span class="trade-image-count">+<?php echo (int) $row['tr_image_count'] - 1; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($is_box): ?>
                                        <span class="trade-emoji" aria-hidden="true">📦</span>
                                    <?php else: ?>
                                        <span class="trade-placeholder-tcg" aria-hidden="true"><span class="trade-placeholder-tcg-inner"></span></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="trade-body">
                                <p class="trade-title"><?php echo htmlspecialchars($row['tr_title']); ?></p>
                                <p class="trade-card"><?php echo htmlspecialchars($row['tr_card_name']); ?>
                                    <?php if ($is_box && (int) $row['tr_box_qty'] > 1): ?>
                                        <span class="trade-qty">×<?php echo (int) $row['tr_box_qty']; ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['tr_grade'])): ?>
                                        <span class="trade-grade"><?php echo htmlspecialchars($row['tr_grade']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <div class="trade-price">
                                    <?php if ((int) $row['tr_price'] > 0): ?>
                                        <small>₩</small><?php echo number_format((int) $row['tr_price']); ?>
                                    <?php else: ?>
                                        <span class="trade-price-free">가격제안</span>
                                    <?php endif; ?>
                                </div>
                                <div class="trade-foot">
                                    <span><?php echo htmlspecialchars($row['mb_nick'] ?? '(탈퇴)'); ?></span>
                                    <span>· 찜 <?php echo date('Y-m-d', $ts); ?></span>
                                    <?php
                                    $inquiry_n = (int) ($row['tr_comments'] ?? 0);
                                    if ($inquiry_n > 0):
                                    ?>
                                        <span class="trade-inquiry-count" title="문의">· 문의 <?php echo number_format($inquiry_n); ?></span>
                                    <?php endif; ?>
                                    <span class="trade-wish-count" title="찜"<?php echo (int) $row['tr_likes'] < 1 ? ' hidden' : ''; ?>>♥ <?php echo number_format((int) $row['tr_likes']); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="찜 리스트 페이지">
                    <?php
                    $range = 2;
                    $start = max(1, $page_no - $range);
                    $end   = min($total_page, $page_no + $range);
                    ?>
                    <?php if ($page_no > 1): ?>
                        <a href="<?php echo $qs(['p' => 1]); ?>" class="page-link">«</a>
                        <a href="<?php echo $qs(['p' => $page_no - 1]); ?>" class="page-link">‹</a>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?php echo $qs(['p' => $i]); ?>" class="page-link <?php echo $i === $page_no ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page_no < $total_page): ?>
                        <a href="<?php echo $qs(['p' => $page_no + 1]); ?>" class="page-link">›</a>
                        <a href="<?php echo $qs(['p' => $total_page]); ?>" class="page-link">»</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
