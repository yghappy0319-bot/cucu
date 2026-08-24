<?php
require_once __DIR__ . '/../lib/_function.php';

$page  = 'notice';
$title = '공지사항';

$categories = [
    'all'         => '전체',
    'general'     => '일반',
    'update'      => '업데이트',
    'event'       => '이벤트',
    'maintenance' => '점검',
];

$cat       = isset($_GET['cat']) && array_key_exists($_GET['cat'], $categories) ? $_GET['cat'] : 'all';
$keyword   = trim($_GET['q'] ?? '');
$page_num  = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;
$offset    = ($page_num - 1) * $per_page;

$where_parts = ["no_status = 1"];
if ($cat !== 'all') {
    $where_parts[] = "no_category = '" . db_escape($cat) . "'";
}
if ($keyword !== '') {
    $k = db_escape($keyword);
    $where_parts[] = "(no_title LIKE '%{$k}%' OR no_content LIKE '%{$k}%')";
}
$where = implode(' AND ', $where_parts);

$__n_ad    = notice_table_has_no_ad_idx();
$__ad_join = $__n_ad ? ' LEFT JOIN tb_admin a ON a.ad_idx = n.no_ad_idx' : '';
$__ad_sel  = $__n_ad ? ', a.ad_name, a.ad_id' : '';

$total = (int) db_result("SELECT COUNT(*) FROM tb_notice WHERE {$where}");
$total_pages = max(1, (int) ceil($total / $per_page));

// 상단 고정 공지 (전체 탭/검색 없을 때만)
$pinned_rows = [];
if ($cat === 'all' && $keyword === '' && $page_num === 1) {
    $rs = db_query("
        SELECT n.*, m.mb_nick{$__ad_sel}
        FROM tb_notice n
        LEFT JOIN tb_member m ON m.mb_idx = n.mb_idx
        {$__ad_join}
        WHERE n.no_status = 1 AND n.no_is_pinned = 1
        ORDER BY n.no_created_at DESC
        LIMIT 5
    ");
    while ($r = db_assoc($rs)) {
        $pinned_rows[] = $r;
    }
}

// 일반 목록
$rs = db_query("
    SELECT n.*, m.mb_nick{$__ad_sel}
    FROM tb_notice n
    LEFT JOIN tb_member m ON m.mb_idx = n.mb_idx
    {$__ad_join}
    WHERE {$where}
      " . (!empty($pinned_rows) ? "AND n.no_is_pinned = 0" : '') . "
    ORDER BY n.no_created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$rows = [];
while ($r = db_assoc($rs)) {
    $rows[] = $r;
}

$me = login_member();
$is_admin = $me && (int) $me['mb_level'] >= 9;

$qs = function ($overrides = []) use ($cat, $keyword, $page_num) {
    $params = array_filter([
        'cat'  => $cat !== 'all' ? $cat : null,
        'q'    => $keyword !== '' ? $keyword : null,
        'page' => $page_num > 1 ? $page_num : null,
    ], static fn($v) => $v !== null);
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '' || $v === 0 || $v === false) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return $params ? '?' . http_build_query($params) : '';
};

$meta_description = 'Pokazone 공지사항 - 서비스 업데이트, 이벤트, 점검 안내 등 최신 소식을 확인하세요.';
$meta_keywords    = 'Pokazone 공지사항, 서비스 업데이트, 이벤트, 점검 안내';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '공지사항', 'url' => '/page/notice.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="community notice-page">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">공지사항</h1>
                <p class="board-desc">Pokazone의 최신 소식과 업데이트를 확인하세요.</p>
            </div>
            <?php if ($is_admin): ?>
                <a href="/page/notice_write.php" class="btn btn-primary">공지 등록</a>
            <?php endif; ?>
        </div>

        <nav class="board-tabs" aria-label="공지 카테고리">
            <?php foreach ($categories as $k => $label): ?>
                <a href="<?php echo $qs(['cat' => $k === 'all' ? null : $k, 'page' => null]); ?>"
                   class="board-tab<?php echo $cat === $k ? ' is-active' : ''; ?>">
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form class="board-search board-search--notice" method="get" action="/page/notice.php">
            <?php if ($cat !== 'all'): ?>
                <input type="hidden" name="cat" value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <input type="search" name="q" placeholder="제목·내용 검색" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-outline btn-sm">검색</button>
            <?php if ($keyword !== ''): ?>
                <a href="/page/notice.php<?php echo $qs(['q' => null, 'page' => null]); ?>" class="btn btn-ghost btn-sm">초기화</a>
            <?php endif; ?>
        </form>

        <?php if ($total > 0): ?>
            <p class="notice-list-meta">총 <?php echo number_format($total); ?>건</p>
        <?php endif; ?>

        <div class="board-list notice-board">
            <div class="board-row is-head">
                <div class="col-num">번호</div>
                <div class="col-cat">분류</div>
                <div class="col-title">제목</div>
                <div class="col-author">작성자</div>
                <div class="col-date">등록일</div>
                <div class="col-views">조회</div>
            </div>

            <?php if (empty($pinned_rows) && empty($rows)): ?>
                <div class="board-empty">
                    <div class="empty-emoji" aria-hidden="true">📢</div>
                    <p>등록된 공지가 없습니다.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pinned_rows as $row):
                    $nc  = $row['no_category'] ?? 'general';
                    $ncl = $categories[$nc] ?? '일반';
                    $nts = strtotime($row['no_created_at'] ?? 'now');
                    $is_new = (time() - $nts) < 86400 * 3;
                    ?>
                    <a href="/page/notice_view.php?idx=<?php echo (int) $row['no_idx']; ?>"
                       class="board-row is-notice-pinned">
                        <div class="col-num"><?php echo (int) $row['no_idx']; ?></div>
                        <div class="col-cat">
                            <span class="cat-badge cat-notice-<?php echo htmlspecialchars($nc, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ncl, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="notice-pin-ico" title="고정" aria-hidden="true">📌</span>
                        </div>
                        <div class="col-title">
                            <span class="board-subject"><?php echo htmlspecialchars($row['no_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($is_new): ?>
                                <span class="board-new">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-author"><?php echo htmlspecialchars(notice_row_author($row), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-date"><?php echo date('Y-m-d', $nts); ?></div>
                        <div class="col-views"><?php echo number_format((int) $row['no_views']); ?></div>
                    </a>
                <?php endforeach; ?>

                <?php foreach ($rows as $row):
                    $nc  = $row['no_category'] ?? 'general';
                    $ncl = $categories[$nc] ?? '일반';
                    $nts = strtotime($row['no_created_at'] ?? 'now');
                    $is_new = (time() - $nts) < 86400 * 3;
                    ?>
                    <a href="/page/notice_view.php?idx=<?php echo (int) $row['no_idx']; ?>"
                       class="board-row">
                        <div class="col-num"><?php echo (int) $row['no_idx']; ?></div>
                        <div class="col-cat">
                            <span class="cat-badge cat-notice-<?php echo htmlspecialchars($nc, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ncl, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="col-title">
                            <span class="board-subject"><?php echo htmlspecialchars($row['no_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($is_new): ?>
                                <span class="board-new">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-author"><?php echo htmlspecialchars(notice_row_author($row), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-date"><?php echo date('Y-m-d', $nts); ?></div>
                        <div class="col-views"><?php echo number_format((int) $row['no_views']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="페이지 네비게이션">
                <?php
                $range = 2;
                $start = max(1, $page_num - $range);
                $end   = min($total_pages, $page_num + $range);
                ?>
                <?php if ($page_num > 1): ?>
                    <a href="/page/notice.php<?php echo $qs(['page' => null]); ?>" class="page-link" aria-label="첫 페이지">«</a>
                    <a href="/page/notice.php<?php echo $qs(['page' => $page_num - 1 > 1 ? $page_num - 1 : null]); ?>" class="page-link" aria-label="이전">‹</a>
                <?php endif; ?>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="/page/notice.php<?php echo $qs(['page' => $p > 1 ? $p : null]); ?>"
                       class="page-link<?php echo $p === $page_num ? ' is-active' : ''; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
                <?php if ($page_num < $total_pages): ?>
                    <a href="/page/notice.php<?php echo $qs(['page' => $page_num + 1]); ?>" class="page-link" aria-label="다음">›</a>
                    <a href="/page/notice.php<?php echo $qs(['page' => $total_pages]); ?>" class="page-link" aria-label="마지막 페이지">»</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
