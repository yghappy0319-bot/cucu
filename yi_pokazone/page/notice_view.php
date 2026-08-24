<?php
require_once __DIR__ . '/../lib/_function.php';

$idx = (int) ($_GET['idx'] ?? 0);
if ($idx <= 0) {
    alert_goto('잘못된 접근입니다.', '/page/notice.php');
}

$__ad_view = login_admin() !== null;
$status_where = $__ad_view ? '' : ' AND n.no_status = 1';

$__n_ad    = notice_table_has_no_ad_idx();
$__ad_join = $__n_ad ? ' LEFT JOIN tb_admin a ON a.ad_idx = n.no_ad_idx' : '';
$__ad_sel  = $__n_ad ? ', a.ad_name, a.ad_id' : '';

$rs  = db_query("
    SELECT n.*, m.mb_nick{$__ad_sel}
    FROM tb_notice n
    LEFT JOIN tb_member m ON m.mb_idx = n.mb_idx
    {$__ad_join}
    WHERE n.no_idx = {$idx}{$status_where}
    LIMIT 1
");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('존재하지 않거나 삭제된 공지입니다.', '/page/notice.php');
}

// 조회수 (세션 중복방지, 노출 공지만 DB 반영)
$key = 'viewed_notice_' . $idx;
if (empty($_SESSION[$key])) {
    db_query("UPDATE tb_notice SET no_views = no_views + 1 WHERE no_idx = {$idx} AND no_status = 1");
    if ((int) $row['no_status'] === 1) {
        $row['no_views'] = (int) $row['no_views'] + 1;
    }
    $_SESSION[$key] = true;
}

$categories = [
    'general'     => '일반',
    'update'      => '업데이트',
    'event'       => '이벤트',
    'maintenance' => '점검',
];
$cat_key   = $row['no_category'] ?? 'general';
$cat_label = $categories[$cat_key] ?? '일반';

$prev = db_assoc(db_query("
    SELECT no_idx, no_title FROM tb_notice
    WHERE no_status = 1 AND no_idx < {$idx}
    ORDER BY no_idx DESC LIMIT 1
"));
$next = db_assoc(db_query("
    SELECT no_idx, no_title FROM tb_notice
    WHERE no_status = 1 AND no_idx > {$idx}
    ORDER BY no_idx ASC LIMIT 1
"));

$me = login_member();
$is_admin = $me && (int) $me['mb_level'] >= 9;

$page  = 'notice';
$title = $row['no_title'];
$meta_description = seo_clean_desc($row['no_content'], 160);
$meta_keywords    = 'Pokazone 공지사항, ' . $cat_label . ', ' . htmlspecialchars($row['no_title']);
$meta_type        = 'article';
$meta_published_at = date('c', strtotime($row['no_created_at']));
$meta_modified_at  = date('c', strtotime($row['no_updated_at']));
$meta_author       = notice_row_author($row);
$meta_breadcrumb   = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '공지사항', 'url' => '/page/notice.php'],
    ['name' => $cat_label, 'url' => '/page/notice.php?cat=' . urlencode($row['no_category'])],
    ['name' => $row['no_title'], 'url' => '/page/notice_view.php?idx=' . $idx],
];
if ($__ad_view && (int) $row['no_status'] !== 1) {
    $meta_noindex = true;
}
include __DIR__ . '/../include/header.php';
?>

<section class="community notice-view">
    <div class="container">
        <?php if ($__ad_view && (int) $row['no_status'] !== 1): ?>
            <p class="admin-preview-banner" role="status">
                관리자 미리보기 · 숨김 처리된 공지입니다.
            </p>
        <?php endif; ?>
        <article class="post">
            <header class="post-head">
                <div class="post-cat post-cat--notice">
                    <span class="cat-badge cat-notice-<?php echo htmlspecialchars($cat_key, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php if ((int) $row['no_is_pinned'] === 1): ?>
                        <span class="notice-pinned-label">고정</span>
                    <?php endif; ?>
                </div>
                <h1 class="post-title"><?php echo htmlspecialchars($row['no_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="post-meta">
                    <div class="post-author post-author--notice">
                        <span class="notice-author-name"><?php echo htmlspecialchars(notice_row_author($row), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="post-stats">
                        <span>등록 <?php echo date('Y-m-d H:i', strtotime($row['no_created_at'])); ?></span>
                        <?php if ($row['no_updated_at'] !== $row['no_created_at']): ?>
                            <span>· 수정 <?php echo date('Y-m-d H:i', strtotime($row['no_updated_at'])); ?></span>
                        <?php endif; ?>
                        <span>· 조회 <?php echo number_format((int) $row['no_views']); ?></span>
                    </div>
                </div>
            </header>

            <div class="post-body post-body--notice">
                <?php echo nl2br(htmlspecialchars($row['no_content'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
            </div>

            <?php if ($is_admin): ?>
                <div class="post-actions">
                    <div class="post-actions-left">
                        <a href="/page/notice_write.php?idx=<?php echo (int) $idx; ?>" class="btn btn-outline btn-sm">수정</a>
                        <form method="post" action="/proc/notice_delete_proc.php" class="inline-form"
                              onsubmit="return confirm('정말 삭제하시겠습니까?');">
                            <input type="hidden" name="idx" value="<?php echo (int) $idx; ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">삭제</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </article>

        <nav class="post-nav" aria-label="공지 이전·다음">
            <?php if ($prev): ?>
                <a href="/page/notice_view.php?idx=<?php echo (int) $prev['no_idx']; ?>" class="post-nav-item">
                    <span class="post-nav-label">← 이전</span>
                    <span class="post-nav-title"><?php echo htmlspecialchars($prev['no_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php else: ?>
                <span class="post-nav-item is-disabled">
                    <span class="post-nav-label">← 이전</span>
                    <span class="post-nav-title">이전 공지가 없습니다.</span>
                </span>
            <?php endif; ?>
            <?php if ($next): ?>
                <a href="/page/notice_view.php?idx=<?php echo (int) $next['no_idx']; ?>" class="post-nav-item is-next">
                    <span class="post-nav-label">다음 →</span>
                    <span class="post-nav-title"><?php echo htmlspecialchars($next['no_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php else: ?>
                <span class="post-nav-item is-disabled is-next">
                    <span class="post-nav-label">다음 →</span>
                    <span class="post-nav-title">다음 공지가 없습니다.</span>
                </span>
            <?php endif; ?>
        </nav>

        <div class="form-actions form-actions--center notice-view-back">
            <a href="/page/notice.php" class="btn btn-outline">목록</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
