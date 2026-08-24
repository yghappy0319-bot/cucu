<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_community_meta.php';

$title     = '게시글관리';
$ad_topbar = $ad;
$ad_menu   = 'posts';
include __DIR__ . '/include/admin_header.php';

$cat = isset($_GET['cat']) ? trim((string) $_GET['cat']) : '';
$st  = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q   = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$categories = community_categories(true);

$status_filter = [
    'all' => '전체',
    '1'   => '노출',
    '9'   => '숨김',
];

$ready = db_table_exists('tb_community');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($cat !== '' && array_key_exists($cat, $categories)) {
        $where[] = "c.co_category = '" . db_escape($cat) . "'";
    }
    if ($st === '1' || $st === '9') {
        $where[] = 'c.co_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(c.co_title LIKE '%{$e}%' OR c.co_content LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_community c WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT c.*, m.mb_nick, m.mb_id
        FROM tb_community c
        LEFT JOIN tb_member m ON m.mb_idx = c.mb_idx
        WHERE {$where_sql}
        ORDER BY c.co_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($cat, $st, $q, $page_no) {
    $base = ['cat' => $cat, 'st' => $st, 'q' => $q, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['cat'] ?? '') === '') {
        unset($base['cat']);
    }
    if (($base['st'] ?? '') === '' || ($base['st'] ?? '') === 'all') {
        unset($base['st']);
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_community 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">커뮤니티 게시글</h1>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_cat">카테고리</label>
                    <select id="f_cat" name="cat">
                        <?php foreach ($categories as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $cat === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label for="f_st">상태</label>
                    <select id="f_st" name="st">
                        <?php foreach ($status_filter as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">제목·본문</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
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
                        <th>카테고리</th>
                        <th>제목</th>
                        <th>작성자</th>
                        <th class="ad-num">조회</th>
                        <th>상태</th>
                        <th class="ad-nowrap">작성일</th>
                        <th>처리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $ok = (int) $r['co_status'] === 1;
                        $cat_label = $categories[$r['co_category']] ?? $r['co_category'];
                        ?>
                        <tr>
                            <td><?php echo (int) $r['co_idx']; ?></td>
                            <td><?php echo htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell">
                                <a href="/page/community_view.php?idx=<?php echo (int) $r['co_idx']; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['co_title'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars(community_display_author($r), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo number_format((int) $r['co_views']); ?></td>
                            <td>
                                <span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '숨김'; ?></span>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr($r['co_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form class="ad-form ad-form--inline" action="/proc/admin_community_proc.php" method="post" onsubmit="return confirm('<?php echo $ok ? '이 글을 숨김 처리할까요?' : '이 글을 다시 노출할까요?'; ?>');">
                                    <input type="hidden" name="co_idx" value="<?php echo (int) $r['co_idx']; ?>">
                                    <input type="hidden" name="action" value="<?php echo $ok ? 'hide' : 'show'; ?>">
                                    <button type="submit" class="ad-btn"><?php echo $ok ? '숨김' : '복구'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/posts.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php
                    for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++):
                        if ($i === $page_no):
                            ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/posts.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif;
                    endfor;
                    ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/posts.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
