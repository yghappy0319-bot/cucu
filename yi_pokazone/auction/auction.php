<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_auction.php';
require_once __DIR__ . '/../lib/_search_rank.php';

$page  = 'auction';
$title = '경매';
$meta_description = '포켓몬 카드·미개봉 박스 실시간 경매. 현재가와 마감 시간을 확인하고 입찰해 보세요.';
$meta_keywords    = '포켓몬카드 경매, 포켓몬 경매, TCG 경매, 미개봉박스 경매, Pokazone 경매';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '경매', 'url' => '/auction/auction.php'],
];

$status_tabs = [
    ''        => '전체',
    'live'    => '진행중',
    'ending'  => '마감임박',
    'ended'   => '종료',
];

$sort_options = [
    'latest'       => '최신 등록순',
    'ending_soon'  => '마감 임박순',
    'price_high'   => '현재가 높은순',
    'price_low'    => '현재가 낮은순',
    'bids'         => '입찰 많은순',
];

$cur_status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
if (!array_key_exists($cur_status, $status_tabs)) {
    $cur_status = '';
}

$cur_sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'latest';
if (!array_key_exists($cur_sort, $sort_options)) {
    $cur_sort = 'latest';
}

$keyword = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($keyword !== '') {
    $me_auction_search = login_member();
    search_log_keyword($keyword, $me_auction_search ? (int) $me_auction_search['mb_idx'] : null);
}
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 12;
$offset  = ($page_no - 1) * $per;

$auction_table_ok = auction_table_ok();
$rows               = [];
$total              = 0;
$total_page         = 1;

if ($auction_table_ok) {
    auction_finalize_expired(null, 50);

    $where = ['a.au_status = 1'];

    if ($cur_status === 'live') {
        $where[] = 'a.au_auction_status = 1';
        $where[] = 'a.au_ends_at > NOW()';
    } elseif ($cur_status === 'ending') {
        $where[] = 'a.au_auction_status = 1';
        $where[] = 'a.au_ends_at > NOW()';
        $where[] = 'a.au_ends_at <= DATE_ADD(NOW(), INTERVAL ' . (int) (AUCTION_ENDING_SOON_SEC / 3600) . ' HOUR)';
    } elseif ($cur_status === 'ended') {
        $where[] = '(a.au_auction_status >= 2 OR a.au_ends_at <= NOW())';
    }

    if ($keyword !== '') {
        $kw = db_escape($keyword);
        $where[] = "(a.au_title LIKE '%{$kw}%' OR a.au_card_name LIKE '%{$kw}%' OR a.au_content LIKE '%{$kw}%')";
    }

    $where_sql = implode(' AND ', $where);

    $order_sql = 'a.au_idx DESC';
    switch ($cur_sort) {
        case 'ending_soon':
            $order_sql = 'a.au_ends_at ASC';
            break;
        case 'price_high':
            $order_sql = 'a.au_current_price DESC, a.au_idx DESC';
            break;
        case 'price_low':
            $order_sql = 'a.au_current_price ASC, a.au_idx DESC';
            break;
        case 'bids':
            $order_sql = 'a.au_bid_count DESC, a.au_idx DESC';
            break;
        case 'latest':
        default:
            $order_sql = 'a.au_idx DESC';
            break;
    }

    $total = (int) db_result("SELECT COUNT(*) FROM tb_auction a WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $sql = "
        SELECT a.*, m.mb_nick,
               (SELECT ai_path FROM tb_auction_image
                 WHERE au_idx = a.au_idx
                 ORDER BY ai_order ASC, ai_idx ASC LIMIT 1) AS thumb_path
        FROM tb_auction a
        LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
        WHERE {$where_sql}
        ORDER BY {$order_sql}
        LIMIT {$offset}, {$per}
    ";
    $rs = db_query($sql);
    if ($rs) {
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
    }
}

$status_labels = auction_status_labels();

$qs = static function (array $overrides = []) use ($cur_status, $cur_sort, $keyword, $page_no): string {
    $arr = array_merge([
        'status' => $cur_status,
        'sort'   => $cur_sort !== 'latest' ? $cur_sort : '',
        'q'      => $keyword,
        'p'      => $page_no > 1 ? $page_no : '',
    ], $overrides);
    $pairs = [];
    foreach ($arr as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $pairs[] = $k . '=' . rawurlencode((string) $v);
    }

    return $pairs ? '?' . implode('&', $pairs) : '';
};

$me = login_member();

$__auction_cd_js = dirname(__DIR__) . '/assets/js/auction-countdown.js';
$page_footer_extra = '<script src="/auction/assets/js/auction-countdown.js?v='
    . (is_file($__auction_cd_js) ? filemtime($__auction_cd_js) : time())
    . '" defer></script>';

include __DIR__ . '/../include/header.php';
?>

<section class="community trade-board auction-board">
    <div class="container">
        <?php if (!$auction_table_ok): ?>
            <div class="auction-prep-banner" role="note">
                <p>경매 DB 테이블이 아직 적용되지 않았습니다. 서버에서 <code>sql/tb_auction.sql</code> 실행 후 새로고침해 주세요.</p>
            </div>
        <?php endif; ?>

        <header class="board-head">
            <div>
                <h1 class="board-title">경매</h1>
                <p class="board-desc">포켓몬 카드·미개봉 박스를 실시간 입찰로 만나 보세요. <strong>택배 거래</strong>만 가능합니다.</p>
            </div>
            <?php if ($me && $auction_table_ok): ?>
                <a href="/auction/auction_write.php" class="btn btn-primary">경매 등록</a>
            <?php elseif (!$me): ?>
                <a href="/login.php?return=<?php echo urlencode('/auction/auction_write.php'); ?>" class="btn btn-primary">로그인 후 등록</a>
            <?php else: ?>
                <span class="btn btn-primary is-disabled" aria-disabled="true">경매 등록</span>
            <?php endif; ?>
        </header>

        <nav class="board-tabs" aria-label="경매 상태">
            <?php foreach ($status_tabs as $key => $label): ?>
                <a href="<?php echo htmlspecialchars($qs(['status' => $key, 'p' => 1])); ?>"
                   class="board-tab <?php echo $cur_status === $key ? 'is-active' : ''; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form class="board-search auction-search" method="get" action="/auction/auction.php">
            <?php if ($cur_status !== ''): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($cur_status); ?>">
            <?php endif; ?>
            <label class="auction-sort-label" for="auctionSort">정렬</label>
            <select id="auctionSort" name="sort" class="auction-sort-select" onchange="this.form.submit()">
                <?php foreach ($sort_options as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"<?php echo $cur_sort === $key ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?php echo htmlspecialchars($keyword); ?>"
                   placeholder="상품명·세트명으로 검색" aria-label="경매 검색">
            <button type="submit" class="btn btn-outline btn-sm">검색</button>
        </form>

        <?php if ($keyword !== '' || $cur_status !== ''): ?>
            <div class="auction-toolbar">
                <span class="auction-toolbar-count"><?php echo number_format($total); ?>건</span>
                <?php if ($keyword !== ''): ?>
                    <span class="auction-toolbar-query">&ldquo;<?php echo htmlspecialchars($keyword); ?>&rdquo; 검색</span>
                <?php endif; ?>
                <a href="/auction/auction.php" class="link-more">필터 초기화</a>
            </div>
        <?php endif; ?>

        <?php if (!$auction_table_ok): ?>
            <div class="board-list">
                <div class="board-empty">
                    <div class="empty-emoji" aria-hidden="true">🔨</div>
                    <p>경매 기능을 사용하려면 DB 마이그레이션이 필요합니다.</p>
                </div>
            </div>
        <?php elseif (empty($rows)): ?>
            <div class="board-list">
                <div class="board-empty">
                    <div class="empty-emoji" aria-hidden="true">🔨</div>
                    <p>조건에 맞는 경매가 없습니다.<br><?php echo $me ? '첫 경매를 등록해 보세요!' : '로그인 후 경매를 등록할 수 있습니다.'; ?></p>
                    <?php if ($me): ?>
                        <p style="margin-top:12px"><a href="/auction/auction_write.php" class="btn btn-primary btn-sm">경매 등록</a></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="trade-grid auction-grid">
                <?php foreach ($rows as $row):
                    $display = auction_display_status($row);
                    $st      = $status_labels[$display] ?? $status_labels['live'];
                    $outcome_lbl = auction_outcome_label($row);
                    if ($outcome_lbl !== null) {
                        $st = [
                            'label' => $outcome_lbl,
                            'class' => $outcome_lbl === '낙찰' ? 'ds-won' : 'ds-failed',
                        ];
                    }
                    $ends_ts  = strtotime((string) $row['au_ends_at']);
                    $remain   = auction_format_remain($ends_ts);
                    $is_ended = $display === 'ended';
                    $is_ending = $display === 'ending';
                    $is_box = ($row['au_item_type'] ?? '') === 'box';
                    $img    = $row['thumb_path'] ?? '';
                    $ck_lbl = auction_category_label($row);
                    $cat    = $row['au_card_kind'] ?? ($is_box ? 'box' : 'card');
                    ?>
                    <a href="/auction/auction_view.php?idx=<?php echo (int) $row['au_idx']; ?>"
                       class="trade-item auction-item<?php echo $is_ended ? ' is-done' : ''; ?>">
                        <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
                            <div class="trade-thumb-tags">
                                <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                                    <?php echo $is_box ? '📦 상자' : '카드'; ?>
                                </span>
                                <?php if ($ck_lbl !== '카드'): ?>
                                    <span class="item-kind-chip item-<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($ck_lbl); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="deal-status <?php echo htmlspecialchars($st['class']); ?>">
                                <?php echo htmlspecialchars($st['label']); ?>
                            </span>
                            <?php if ($img): ?>
                                <img class="trade-image"
                                     src="<?php echo htmlspecialchars(public_url($img)); ?>"
                                     alt="<?php echo htmlspecialchars($row['au_card_name']); ?>"
                                     loading="lazy" decoding="async">
                            <?php elseif ($is_box): ?>
                                <span class="trade-emoji" aria-hidden="true">📦</span>
                            <?php else: ?>
                                <span class="trade-placeholder-tcg" aria-hidden="true">
                                    <span class="trade-placeholder-tcg-inner"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="trade-body">
                            <p class="trade-title"><?php echo htmlspecialchars($row['au_title']); ?></p>
                            <p class="trade-card"><?php echo htmlspecialchars($row['au_card_name']); ?></p>
                            <div class="auction-price-block">
                                <span class="auction-price-label">현재가</span>
                                <div class="trade-price auction-current-price">
                                    <small>₩</small><?php echo number_format((int) $row['au_current_price']); ?>
                                </div>
                                <span class="auction-start-price">시작가 ₩<?php echo number_format((int) $row['au_start_price']); ?></span>
                            </div>
                            <div class="auction-item-meta">
                                <span class="auction-meta-bids">입찰 <?php echo number_format((int) $row['au_bid_count']); ?>회</span>
                                <span class="auction-meta-time<?php echo $is_ending ? ' is-urgent' : ''; ?><?php echo $is_ended ? ' is-ended' : ''; ?>"
                                      <?php if (!$is_ended && $ends_ts > 0): ?>
                                          data-auction-ends="<?php echo (int) $ends_ts; ?>"
                                      <?php endif; ?>>
                                    <?php echo htmlspecialchars($remain); ?>
                                </span>
                            </div>
                            <div class="trade-foot">
                                <span><?php echo htmlspecialchars($row['mb_nick'] ?? '(탈퇴)'); ?></span>
                                <span>· 조회 <?php echo number_format((int) $row['au_views']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="pagination" aria-label="페이지">
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
