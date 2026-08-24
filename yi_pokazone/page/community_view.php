<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/../lib/_community_meta.php';
require_once __DIR__ . '/../lib/_member_public.php';

$idx = (int)($_GET['idx'] ?? 0);
$cur_cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';

if ($idx < 1) {
    alert_goto('잘못된 접근입니다.', '/page/community.php');
}

$categories = community_categories(false);

// 조회수 증가 (중복방지: 세션)
if (!isset($_SESSION['co_view']) || !is_array($_SESSION['co_view'])) {
    $_SESSION['co_view'] = [];
}
$__ad_view = login_admin() !== null;

if (!in_array($idx, $_SESSION['co_view'], true)) {
    db_query("UPDATE tb_community SET co_views = co_views + 1 WHERE co_idx = {$idx} AND co_status = 1");
    $_SESSION['co_view'][] = $idx;
}

$status_where = $__ad_view ? '' : ' AND c.co_status = 1';
$rs = db_query("
    SELECT c.*, m.mb_nick, m.mb_level
    FROM tb_community c
    LEFT JOIN tb_member m ON m.mb_idx = c.mb_idx
    WHERE c.co_idx = {$idx}{$status_where}
    LIMIT 1
");
$row = db_assoc($rs);

if (!$row) {
    alert_goto('존재하지 않거나 삭제된 게시글입니다.', '/page/community.php');
}

// 이전 / 다음 글
$prev = db_assoc(db_query("
    SELECT co_idx, co_title FROM tb_community
    WHERE co_status = 1 AND co_idx < {$idx}
    ORDER BY co_idx DESC LIMIT 1
"));
$next = db_assoc(db_query("
    SELECT co_idx, co_title FROM tb_community
    WHERE co_status = 1 AND co_idx > {$idx}
    ORDER BY co_idx ASC LIMIT 1
"));

$me       = login_member();
$is_owner = $me && (int)$me['mb_idx'] === (int)$row['mb_idx'];
$is_admin = $me && (int)$me['mb_level'] >= 9;
$can_edit = ($is_owner || $is_admin) && !community_is_external_cafe_post($row);
$can_interact = (int) $row['co_status'] === 1;

$has_likes    = db_table_exists('tb_community_like');
$has_comments = db_table_exists('tb_community_comment');

$liked = false;
if ($has_likes && $me) {
    $liked = (bool) db_assoc(db_query("
        SELECT 1 AS o FROM tb_community_like
        WHERE co_idx = {$idx} AND mb_idx = " . (int) $me['mb_idx'] . " LIMIT 1
    "));
}

$comment_rows = [];
if ($has_comments) {
    $crs = db_query("
        SELECT c.*, m.mb_nick
        FROM tb_community_comment c
        LEFT JOIN tb_member m ON m.mb_idx = c.mb_idx
        WHERE c.co_idx = {$idx} AND c.cc_status = 1
        ORDER BY c.cc_created_at ASC, c.cc_idx ASC
    ");
    while ($c = db_assoc($crs)) {
        $comment_rows[] = $c;
    }
}

$comment_children = [];
foreach ($comment_rows as $c) {
    if ($c['cc_parent_idx'] !== null && (int) $c['cc_parent_idx'] > 0) {
        $pid = (int) $c['cc_parent_idx'];
        if (!isset($comment_children[$pid])) {
            $comment_children[$pid] = [];
        }
        $comment_children[$pid][] = $c;
    }
}

$return_path = '/page/community_view.php?idx=' . $idx . ($cur_cat !== '' ? '&cat=' . rawurlencode($cur_cat) : '');

$cat_label = $categories[$row['co_category']] ?? community_category_label((string) $row['co_category']);
$display_author = community_display_author($row);
$is_external_cafe = community_is_external_cafe_post($row);
$external_url = trim((string) ($row['co_external_url'] ?? ''));

$page  = 'community';
$title = $row['co_title'];
$meta_description = seo_clean_desc($row['co_content'], 160);
$meta_keywords    = '포켓몬 카드, 커뮤니티, ' . $cat_label . ', ' . htmlspecialchars($row['co_title']);
$meta_type        = 'article';
$meta_published_at = date('c', strtotime($row['co_created_at']));
$meta_modified_at  = date('c', strtotime($row['co_updated_at']));
$meta_author       = $display_author;
$share_url         = seo_abs_url('/page/community_view.php?idx=' . (int)$row['co_idx']);
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '커뮤니티', 'url' => '/page/community.php'],
    ['name' => $cat_label, 'url' => '/page/community.php?cat=' . urlencode($row['co_category'])],
    ['name' => $row['co_title'], 'url' => '/page/community_view.php?idx=' . (int)$row['co_idx']],
];
$meta_jsonld = [
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id'   => seo_abs_url('/page/community_view.php?idx=' . (int)$row['co_idx']),
    ],
    'headline'      => $row['co_title'],
    'description'   => $meta_description,
    'datePublished' => $meta_published_at,
    'dateModified'  => $meta_modified_at,
    'author'        => [
        '@type' => 'Person',
        'name'  => $meta_author,
    ],
    'publisher'     => [
        '@type' => 'Organization',
        'name'  => 'Pokazone',
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => seo_abs_url('/assets/img/logo.png'),
        ],
    ],
    'articleSection' => $cat_label,
    'inLanguage'     => 'ko-KR',
];
if ($__ad_view && (int) $row['co_status'] !== 1) {
    $meta_noindex = true;
}
include __DIR__ . '/../include/header.php';
?>

<section class="community">
    <div class="container">
        <?php if ($__ad_view && (int) $row['co_status'] !== 1): ?>
            <p style="margin:0 0 1rem;padding:0.75rem 1rem;border-radius:8px;border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;font-size:14px;">
                관리자 미리보기 · 사용자에게는 숨김(삭제) 처리된 글입니다.
            </p>
        <?php endif; ?>
        <article class="post">
            <header class="post-head">
                <div class="post-cat">
                    <span class="cat-badge cat-<?php echo htmlspecialchars($row['co_category']); ?>">
                        <?php echo htmlspecialchars($cat_label); ?>
                    </span>
                </div>
                <h1 class="post-title"><?php echo htmlspecialchars($row['co_title']); ?></h1>

                <div class="post-meta">
                    <div class="post-author">
                        <?php
                        $co_author_mb = (int) ($row['mb_idx'] ?? 0);
                        $co_author_label = $display_author !== '' ? $display_author : '(탈퇴회원)';
                        $co_author_url = (!$is_external_cafe && $co_author_mb > 0)
                            ? member_profile_url($co_author_mb)
                            : '';
                        ?>
                        <?php if ($co_author_url !== ''): ?>
                        <a href="<?php echo htmlspecialchars($co_author_url, ENT_QUOTES, 'UTF-8'); ?>" class="post-author-link">
                            <span class="user-avatar">
                                <?php echo mb_substr(htmlspecialchars($co_author_label), 0, 1); ?>
                            </span>
                            <strong><?php echo htmlspecialchars($co_author_label); ?></strong>
                        </a>
                        <?php else: ?>
                        <span class="user-avatar">
                            <?php echo mb_substr(htmlspecialchars($co_author_label), 0, 1); ?>
                        </span>
                        <strong><?php echo htmlspecialchars($co_author_label); ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="post-stats">
                        <span>작성 <?php echo date('Y-m-d H:i', strtotime($row['co_created_at'])); ?></span>
                        <?php if ($row['co_updated_at'] !== $row['co_created_at']): ?>
                            <span>· 수정 <?php echo date('Y-m-d H:i', strtotime($row['co_updated_at'])); ?></span>
                        <?php endif; ?>
                        <span>· 조회 <?php echo number_format((int)$row['co_views']); ?></span>
                        <?php if ($has_likes): ?>
                            <span>· 좋아요 <?php echo number_format((int) $row['co_likes']); ?></span>
                        <?php endif; ?>
                        <?php if ($has_comments): ?>
                            <span>· 댓글 <?php echo number_format((int) $row['co_comments']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($is_external_cafe && $external_url !== ''): ?>
                    <p class="post-external-link">
                        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($external_url); ?>" target="_blank" rel="noopener noreferrer">네이버 카페 원문 보기</a>
                    </p>
                <?php endif; ?>
            </header>

            <div class="post-body post-body--article">
                <?php echo community_render_post_body((string)$row['co_content']); ?>
            </div>

            <?php if ($has_likes && $can_interact): ?>
                <div class="co-like-bar">
                    <form action="/proc/community_like_proc.php" method="post" class="co-like-form">
                        <input type="hidden" name="co_idx" value="<?php echo (int) $row['co_idx']; ?>">
                        <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($me): ?>
                            <button type="submit" class="btn <?php echo $liked ? 'btn-primary' : 'btn-outline'; ?> btn-sm co-like-submit" aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>">
                                <span class="co-like-icon" aria-hidden="true"><?php echo $liked ? '♥' : '♡'; ?></span>
                                좋아요 <span class="co-like-count"><?php echo number_format((int) $row['co_likes']); ?></span>
                            </button>
                        <?php else: ?>
                            <a class="btn btn-outline btn-sm" href="/login.php?return=<?php echo rawurlencode($return_path); ?>">♡ 로그인 후 좋아요</a>
                            <span class="co-like-readonly"><?php echo number_format((int) $row['co_likes']); ?>명이 좋아합니다</span>
                        <?php endif; ?>
                    </form>
                </div>
            <?php elseif ($has_likes && !$can_interact): ?>
                <div class="co-like-bar co-like-bar--muted">
                    <span class="co-like-readonly">좋아요 <?php echo number_format((int) $row['co_likes']); ?></span>
                </div>
            <?php endif; ?>

            <div class="post-actions">
                <div class="post-actions-left">
                    <a href="/page/community.php<?php echo $cur_cat ? '?cat='.urlencode($cur_cat) : ''; ?>" class="btn btn-outline btn-sm">목록</a>
                    <button type="button" class="btn btn-outline btn-sm"
                            data-share-url="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>"
                            data-share-label="공유하기"
                            aria-label="글 링크 복사">공유하기</button>
                </div>
                <?php if ($can_edit): ?>
                    <div class="post-actions-right">
                        <a href="/page/community_write.php?idx=<?php echo (int)$row['co_idx']; ?>" class="btn btn-outline btn-sm">수정</a>
                        <form action="/proc/community_delete_proc.php" method="post" class="inline-form"
                              onsubmit="return confirm('정말 삭제하시겠습니까? 삭제된 글은 복구되지 않습니다.');">
                            <input type="hidden" name="idx" value="<?php echo (int)$row['co_idx']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">삭제</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <?php if ($has_comments): ?>
            <div class="co-comments" id="comments">
                <h2 class="co-comments-title">댓글 <span class="co-comments-count"><?php echo number_format((int) $row['co_comments']); ?></span></h2>

                <?php if ($can_interact && $me): ?>
                    <form class="co-comment-form post-form" action="/proc/community_comment_proc.php" method="post">
                        <input type="hidden" name="co_idx" value="<?php echo (int) $row['co_idx']; ?>">
                        <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="cc_parent_idx" value="0">
                        <div class="field">
                            <label for="cc_content_root">댓글 작성</label>
                            <textarea id="cc_content_root" name="cc_content" maxlength="2000" rows="3" required placeholder="댓글을 입력하세요."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">등록</button>
                    </form>
                <?php elseif ($can_interact && !$me): ?>
                    <p class="co-comments-login-hint"><a href="/login.php?return=<?php echo rawurlencode($return_path . '#comments'); ?>">로그인</a> 후 댓글을 작성할 수 있습니다.</p>
                <?php endif; ?>

                <ul class="co-comment-list">
                    <?php
                    foreach ($comment_rows as $c):
                        if ($c['cc_parent_idx'] !== null && (int) $c['cc_parent_idx'] > 0) {
                            continue;
                        }
                        $cid = (int) $c['cc_idx'];
                        $c_owner = $me && (int) $me['mb_idx'] === (int) $c['mb_idx'];
                        $c_mod   = $me && (int) $me['mb_level'] >= 9;
                        ?>
                        <li class="co-comment" id="cc-<?php echo $cid; ?>">
                            <div class="co-comment-inner">
                                <div class="co-comment-meta">
                                    <span class="co-comment-author"><?php echo member_nick_link_html((int) ($c['mb_idx'] ?? 0), $c['mb_nick'] ?? '(회원)', 'member-nick-link'); ?></span>
                                    <time datetime="<?php echo htmlspecialchars($c['cc_created_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('Y-m-d H:i', strtotime($c['cc_created_at'])); ?></time>
                                </div>
                                <div class="co-comment-body"><?php echo nl2br(htmlspecialchars($c['cc_content'], ENT_QUOTES, 'UTF-8')); ?></div>
                                <div class="co-comment-actions">
                                    <?php if ($can_interact && $me): ?>
                                        <button type="button" class="btn btn-ghost btn-sm js-reply-toggle" data-target="reply-<?php echo $cid; ?>">답글</button>
                                    <?php endif; ?>
                                    <?php if ($c_owner || $c_mod): ?>
                                        <form action="/proc/community_comment_delete_proc.php" method="post" class="inline-form" onsubmit="return confirm('이 댓글을 삭제할까요? 대댓글도 함께 삭제될 수 있습니다.');">
                                            <input type="hidden" name="cc_idx" value="<?php echo $cid; ?>">
                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">삭제</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php if ($can_interact && $me): ?>
                                    <div class="co-comment-reply-wrap" id="reply-<?php echo $cid; ?>" hidden>
                                        <form class="co-comment-form co-comment-form--reply" action="/proc/community_comment_proc.php" method="post">
                                            <input type="hidden" name="co_idx" value="<?php echo (int) $row['co_idx']; ?>">
                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="cc_parent_idx" value="<?php echo $cid; ?>">
                                            <textarea name="cc_content" maxlength="2000" rows="2" required placeholder="<?php echo htmlspecialchars($c['mb_nick'] ?? '', ENT_QUOTES, 'UTF-8'); ?>님에게 답글…"></textarea>
                                            <button type="submit" class="btn btn-primary btn-sm">답글 등록</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($comment_children[$cid])): ?>
                                <ul class="co-comment-replies">
                                    <?php foreach ($comment_children[$cid] as $ch):
                                        $chid = (int) $ch['cc_idx'];
                                        $ch_owner = $me && (int) $me['mb_idx'] === (int) $ch['mb_idx'];
                                        $ch_mod   = $me && (int) $me['mb_level'] >= 9;
                                        ?>
                                        <li class="co-comment co-comment--reply" id="cc-<?php echo $chid; ?>">
                                            <div class="co-comment-inner">
                                                <div class="co-comment-meta">
                                                    <span class="co-comment-author"><?php echo member_nick_link_html((int) ($ch['mb_idx'] ?? 0), $ch['mb_nick'] ?? '(회원)', 'member-nick-link'); ?></span>
                                                    <time datetime="<?php echo htmlspecialchars($ch['cc_created_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('Y-m-d H:i', strtotime($ch['cc_created_at'])); ?></time>
                                                </div>
                                                <div class="co-comment-body"><?php echo nl2br(htmlspecialchars($ch['cc_content'], ENT_QUOTES, 'UTF-8')); ?></div>
                                                <div class="co-comment-actions">
                                                    <?php if ($ch_owner || $ch_mod): ?>
                                                        <form action="/proc/community_comment_delete_proc.php" method="post" class="inline-form" onsubmit="return confirm('답글을 삭제할까요?');">
                                                            <input type="hidden" name="cc_idx" value="<?php echo $chid; ?>">
                                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button type="submit" class="btn btn-ghost btn-sm">삭제</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (empty($comment_rows)): ?>
                    <p class="co-comments-empty">첫 댓글을 남겨 보세요.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <nav class="post-nav" aria-label="이전 다음 글">
            <?php if ($prev): ?>
                <a class="post-nav-item" href="/page/community_view.php?idx=<?php echo (int)$prev['co_idx']; ?>">
                    <span class="post-nav-label">← 이전글</span>
                    <span class="post-nav-title"><?php echo htmlspecialchars($prev['co_title']); ?></span>
                </a>
            <?php else: ?>
                <span class="post-nav-item is-disabled">
                    <span class="post-nav-label">← 이전글</span>
                    <span class="post-nav-title">이전 게시글이 없습니다.</span>
                </span>
            <?php endif; ?>

            <?php if ($next): ?>
                <a class="post-nav-item" href="/page/community_view.php?idx=<?php echo (int)$next['co_idx']; ?>">
                    <span class="post-nav-label">다음글 →</span>
                    <span class="post-nav-title"><?php echo htmlspecialchars($next['co_title']); ?></span>
                </a>
            <?php else: ?>
                <span class="post-nav-item is-disabled">
                    <span class="post-nav-label">다음글 →</span>
                    <span class="post-nav-title">다음 게시글이 없습니다.</span>
                </span>
            <?php endif; ?>
        </nav>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
