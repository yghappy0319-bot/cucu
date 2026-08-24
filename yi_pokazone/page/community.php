<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_meta.php';

$page  = 'community';
$title = '커뮤니티';
$meta_description = '포켓몬 카드 트레이너들이 모이는 Pokazone 커뮤니티. 자랑, 질문, 정보, 꿀팁까지 자유롭게 나눠요.';
$meta_keywords    = '포켓몬 카드 커뮤니티, 포켓몬 자랑, 포켓몬 질문, 포켓몬 꿀팁, Pokazone 커뮤니티';
$meta_type        = 'website';
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '커뮤니티', 'url' => '/page/community.php'],
];

$categories = community_categories(true);
$writable_categories = community_writable_categories();

$cur_cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';
if (!array_key_exists($cur_cat, $categories)) $cur_cat = '';
$is_external_cafe_tab = community_is_external_category($cur_cat);

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$page_no = max(1, (int)($_GET['p'] ?? 1));
$per     = 15;
$offset  = ($page_no - 1) * $per;

// WHERE
$where = ["c.co_status = 1"];
if ($cur_cat !== '') {
    $where[] = "c.co_category = '" . db_escape($cur_cat) . "'";
}
if ($keyword !== '') {
    $esc_kw  = db_escape($keyword);
    $where[] = "(c.co_title LIKE '%{$esc_kw}%' OR c.co_content LIKE '%{$esc_kw}%')";
}
$where_sql = implode(' AND ', $where);

// 전체 개수
$total = (int) db_result("SELECT COUNT(*) FROM tb_community c WHERE {$where_sql}");
$total_page = max(1, (int)ceil($total / $per));

// 목록
$rs = db_query("
    SELECT c.*, m.mb_nick
    FROM tb_community c
    LEFT JOIN tb_member m ON m.mb_idx = c.mb_idx
    WHERE {$where_sql}
    ORDER BY c.co_idx DESC
    LIMIT {$offset}, {$per}
");

$rows = [];
while ($r = db_assoc($rs)) { $rows[] = $r; }

$community_has_likes = db_table_exists('tb_community_like');

include __DIR__ . '/../include/header.php';
?>

<section class="community">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">커뮤니티</h1>
                <p class="board-desc">트레이너들과 자유롭게 이야기를 나눠보세요.</p>
            </div>
            <?php if (!$is_external_cafe_tab): ?>
                <a href="/page/community_write.php<?php echo ($cur_cat && isset($writable_categories[$cur_cat])) ? '?cat='.urlencode($cur_cat) : ''; ?>" class="btn btn-primary">글쓰기</a>
            <?php endif; ?>
        </div>

        <div class="board-tabs">
            <?php foreach ($categories as $key => $label): ?>
                <a href="?cat=<?php echo urlencode($key); ?>"
                   class="board-tab <?php echo $cur_cat === $key ? 'is-active' : ''; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="board-search" method="get" action="/page/community.php">
            <?php if ($cur_cat !== ''): ?>
                <input type="hidden" name="cat" value="<?php echo htmlspecialchars($cur_cat); ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="제목·내용으로 검색">
            <button type="submit" class="btn btn-outline btn-sm">검색</button>
        </form>

        <div class="board-list">
            <div class="board-row is-head">
                <div class="col-num">번호</div>
                <div class="col-cat">분류</div>
                <div class="col-title">제목</div>
                <div class="col-author">작성자</div>
                <div class="col-date">작성일</div>
                <div class="col-views">조회</div>
            </div>

            <?php if (empty($rows)): ?>
                <div class="board-empty">
                    <div class="empty-emoji">📭</div>
                    <p>아직 등록된 게시글이 없어요.<br>첫 글의 주인공이 되어보세요!</p>
                </div>
            <?php else: ?>
                <?php foreach ($rows as $i => $row):
                    $num   = $total - $offset - $i;
                    $cat_k = $row['co_category'];
                    $cat_l = $categories[$cat_k] ?? community_category_label($cat_k);
                    $is_ext = community_is_external_cafe_post($row);
                    $ext_url = trim((string) ($row['co_external_url'] ?? ''));
                    $row_href = ($is_ext && $ext_url !== '')
                        ? $ext_url
                        : ('/page/community_view.php?idx=' . (int)$row['co_idx'] . ($cur_cat ? '&cat='.urlencode($cur_cat) : ''));
                    $row_attrs = ($is_ext && $ext_url !== '')
                        ? ' target="_blank" rel="noopener noreferrer"'
                        : '';
                ?>
                    <a href="<?php echo htmlspecialchars($row_href); ?>" class="board-row"<?php echo $row_attrs; ?>>
                        <div class="col-num"><?php echo $num; ?></div>
                        <div class="col-cat">
                            <span class="cat-badge cat-<?php echo htmlspecialchars($cat_k); ?>"><?php echo htmlspecialchars($cat_l); ?></span>
                        </div>
                        <div class="col-title">
                            <span class="board-subject"><?php echo htmlspecialchars($row['co_title']); ?></span>
                            <?php if ($is_ext): ?>
                                <span class="board-ext" title="네이버 카페 원문">↗</span>
                            <?php endif; ?>
                            <?php if ((int)$row['co_comments'] > 0): ?>
                                <span class="board-comments">[<?php echo (int)$row['co_comments']; ?>]</span>
                            <?php endif; ?>
                            <?php if ($community_has_likes && (int) $row['co_likes'] > 0): ?>
                                <span class="board-likes" title="좋아요">♥ <?php echo number_format((int) $row['co_likes']); ?></span>
                            <?php endif; ?>
                            <?php if (strtotime($row['co_created_at']) > time() - 60*60*24): ?>
                                <span class="board-new">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-author"><?php echo htmlspecialchars(community_display_author($row)); ?></div>
                        <div class="col-date">
                            <?php
                                $ts = strtotime($row['co_created_at']);
                                echo date(date('Y-m-d') === date('Y-m-d', $ts) ? 'H:i' : 'Y-m-d', $ts);
                            ?>
                        </div>
                        <div class="col-views"><?php echo number_format((int)$row['co_views']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_page > 1): ?>
            <nav class="pagination" aria-label="페이지">
                <?php
                $range = 2;
                $start = max(1, $page_no - $range);
                $end   = min($total_page, $page_no + $range);
                $qs = function($p) use ($cur_cat, $keyword) {
                    $arr = [];
                    if ($cur_cat !== '') $arr[] = 'cat=' . urlencode($cur_cat);
                    if ($keyword !== '') $arr[] = 'q=' . urlencode($keyword);
                    $arr[] = 'p=' . (int)$p;
                    return '?' . implode('&', $arr);
                };
                ?>
                <?php if ($page_no > 1): ?>
                    <a href="<?php echo $qs(1); ?>" class="page-link">«</a>
                    <a href="<?php echo $qs($page_no - 1); ?>" class="page-link">‹</a>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?php echo $qs($i); ?>" class="page-link <?php echo $i === $page_no ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page_no < $total_page): ?>
                    <a href="<?php echo $qs($page_no + 1); ?>" class="page-link">›</a>
                    <a href="<?php echo $qs($total_page); ?>" class="page-link">»</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
