<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_public.php';
require_once __DIR__ . '/../lib/_community_meta.php';
require_once __DIR__ . '/../trade/lib/_trade_listing.php';
require_once __DIR__ . '/../trade/lib/_trade_seller_review.php';

$mb_idx = (int) ($_GET['mb_idx'] ?? 0);

$member = member_public_get($mb_idx);
if (!$member) {
    alert_goto('존재하지 않거나 비공개된 판매자 상점입니다.', '/trade/trade.php');
}

$nick = (string) ($member['mb_nick'] ?? '');
if ($nick === '') {
    $nick = '회원';
}

$viewer = login_member();
$ad_view = login_admin();
$viewer_mb = $viewer ? (int) ($viewer['mb_idx'] ?? 0) : 0;
$is_own_shop = $viewer_mb > 0 && $viewer_mb === $mb_idx;
$report_return = '/page/inquiry.php?' . http_build_query([
    'cat'           => 'report',
    'report_mb_idx' => $mb_idx,
    'report_nick'   => $nick,
]);
$report_href = $viewer
    ? $report_return
    : ('/login.php?return=' . rawurlencode($report_return));

$per = 30;

$deal_labels = [
    1 => ['label' => '판매중',   'class' => 'ds-active'],
    2 => ['label' => '거래중',   'class' => 'ds-reserved'],
    3 => ['label' => '거래완료', 'class' => 'ds-done'],
];

$cnt_trade_active = trade_seller_listing_count($mb_idx, 'active');
$cnt_trade_done   = trade_seller_listing_count($mb_idx, 'done');
$cnt_trade        = trade_seller_listing_count($mb_idx, 'all');
$cnt_community    = 0;
if (db_table_exists('tb_community')) {
    $cnt_community = (int) db_result("
        SELECT COUNT(*) FROM tb_community
        WHERE mb_idx = {$mb_idx} AND co_status = 1
    ");
}
$review_stats = trade_seller_review_stats($mb_idx);
$cnt_reviews  = (int) ($review_stats['count'] ?? 0);
$review_avg   = (float) ($review_stats['avg'] ?? 0);

$community_rows = [];
if ($cnt_community > 0) {
    $ext_sel = community_has_external_columns()
        ? ', co_source, co_external_nick, co_external_url'
        : ", '' AS co_source, '' AS co_external_nick, '' AS co_external_url";
    $rs = db_query("
        SELECT co_idx, co_category, co_title, co_created_at, co_views, co_comments
               {$ext_sel}
        FROM tb_community
        WHERE mb_idx = {$mb_idx} AND co_status = 1
        ORDER BY co_idx DESC
        LIMIT {$per}
    ");
    while ($r = db_assoc($rs)) {
        $community_rows[] = $r;
    }
}

$trade_rows = $cnt_trade > 0
    ? trade_seller_listing_rows($mb_idx, $per, 0, 'all')
    : [];

$review_rows = $cnt_reviews > 0
    ? trade_seller_review_list($mb_idx, $per, 0)
    : [];

$page  = 'member_profile';
$title = $nick . ' 판매자 상점';
$meta_description = $nick . ' 님의 Pokazone 판매자 상점입니다. 거래글과 커뮤니티 게시글을 확인할 수 있습니다.';
$meta_noindex = false;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래게시판', 'url' => '/trade/trade.php'],
    ['name' => $nick . ' 판매자 상점', 'url' => member_profile_url($mb_idx)],
];
include __DIR__ . '/../include/header.php';

$joined = !empty($member['mb_created_at'])
    ? date('Y.m.d', strtotime($member['mb_created_at']))
    : '';
?>

<section class="member-profile">
    <div class="container">
        <div class="member-profile-hero">
            <div class="member-profile-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($nick, 0, 1), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="member-profile-hero-body">
                <p class="member-profile-eyebrow">판매자 상점</p>
                <div class="member-profile-title-row">
                    <h1 class="member-profile-nick"><?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if (!$is_own_shop): ?>
                    <a href="<?php echo htmlspecialchars($report_href, ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline btn-sm member-profile-report-btn"
                       title="허위·악의적 신고 시 신고자도 이용이 제한될 수 있습니다.">신고</a>
                    <?php endif; ?>
                </div>
                <?php if (!$is_own_shop): ?>
                <p class="member-profile-report-hint">
                    신고는 사실에 근거해 이용해 주세요. 허위이거나 악의적인 신고로 확인되면
                    <strong>신고자도 서비스 이용이 제한</strong>될 수 있습니다.
                </p>
                <?php endif; ?>
                <p class="member-profile-meta">
                    <?php if ($joined !== ''): ?>
                        <span>가입 <?php echo htmlspecialchars($joined, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($cnt_reviews > 0): ?>
                        <span class="member-profile-meta-rating">
                            평점 <?php echo htmlspecialchars(number_format($review_avg, 1), ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo number_format($cnt_reviews); ?>)
                        </span>
                    <?php else: ?>
                        <span>후기 없음</span>
                    <?php endif; ?>
                    <span>거래중 <?php echo number_format($cnt_trade_active); ?></span>
                    <span>거래완료 <?php echo number_format($cnt_trade_done); ?></span>
                    <span>커뮤니티 <?php echo number_format($cnt_community); ?></span>
                </p>
                <?php
                $shop_intro = trim((string) ($member['mb_shop_intro'] ?? ''));
                if ($shop_intro !== ''):
                ?>
                <p class="member-profile-intro"><?php echo nl2br(htmlspecialchars($shop_intro, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $login_block_ready = $ad_view && member_login_block_columns_ready();
        $login_block_info = $login_block_ready ? member_login_block_info($mb_idx) : null;
        $login_block_target = $ad_view
            ? db_assoc(db_query("SELECT mb_idx, mb_id, mb_nick, mb_level FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"))
            : null;
        $can_login_block = $login_block_ready && $login_block_target && (int) ($login_block_target['mb_level'] ?? 0) < 9;
        if ($ad_view && $login_block_ready):
            $lb_days_opts = member_login_block_day_options();
            $lb_blocked = !empty($login_block_info['blocked']);
        ?>
        <section class="member-login-block-admin" id="member-login-block" aria-label="로그인 접속 제한">
            <strong>관리자 · 로그인 접속 제한</strong>
            <?php if (!$can_login_block): ?>
                <p>관리자 계정에는 로그인 제한을 적용할 수 없습니다.</p>
            <?php elseif ($lb_blocked): ?>
                <p>
                    현재 <strong>접속 제한 중</strong>입니다.
                    <?php if (!empty($login_block_info['until'])): ?>
                        (<?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $login_block_info['until'])), ENT_QUOTES, 'UTF-8'); ?>까지
                        <?php if ((int) $login_block_info['days'] > 0): ?>
                            · <?php echo (int) $login_block_info['days']; ?>일
                        <?php endif; ?>)
                    <?php endif; ?>
                </p>
                <?php if ($login_block_info['reason'] !== ''): ?>
                    <p class="member-login-block-admin__reason">사유: <?php echo htmlspecialchars($login_block_info['reason'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <form action="/proc/admin_member_login_block_proc.php" method="post"
                      onsubmit="return confirm('이 회원의 로그인 접속 제한을 해제할까요?');">
                    <input type="hidden" name="mb_idx" value="<?php echo $mb_idx; ?>">
                    <input type="hidden" name="action" value="lift">
                    <button type="submit" class="btn btn-primary btn-sm">접속 제한 해제</button>
                </form>
            <?php else: ?>
                <p>거래글 작성자의 로그인을 기간 동안 막을 수 있습니다. 해당 회원에게는 아래 사유가 경고로 안내됩니다.</p>
                <form class="member-login-block-admin__form" action="/proc/admin_member_login_block_proc.php" method="post"
                      onsubmit="return confirm('이 회원에게 로그인 접속 제한을 적용할까요?');">
                    <input type="hidden" name="mb_idx" value="<?php echo $mb_idx; ?>">
                    <input type="hidden" name="action" value="apply">
                    <p class="member-login-block-admin__label">제한 기간</p>
                    <div class="member-login-block-admin__days" role="group" aria-label="제한 기간">
                        <?php foreach ($lb_days_opts as $i => $d): ?>
                            <label>
                                <input type="radio" name="days" value="<?php echo (int) $d; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>>
                                <span><?php echo (int) $d; ?>일</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="member-login-block-admin__label" for="login-block-reason">제한 사유</label>
                    <textarea id="login-block-reason" name="reason" rows="2" maxlength="400" required
                              placeholder="예: 타 플랫폼 거래 유도, 외부 연락처 공유 등"></textarea>
                    <button type="submit" class="btn btn-danger btn-sm">접속 제한 적용</button>
                </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="member-profile-section" id="community" aria-labelledby="member-profile-community-heading">
            <h2 id="member-profile-community-heading" class="member-profile-section-title">
                커뮤니티
                <span class="member-profile-section-count"><?php echo number_format($cnt_community); ?></span>
            </h2>
            <?php if (empty($community_rows)): ?>
                <div class="board-empty">
                    <p>작성한 커뮤니티 글이 없습니다.</p>
                </div>
            <?php else: ?>
                <ul class="member-profile-list">
                    <?php foreach ($community_rows as $row):
                        $is_ext = function_exists('community_is_external_cafe_post') && community_is_external_cafe_post($row);
                        $ext_url = trim((string) ($row['co_external_url'] ?? ''));
                        $href = ($is_ext && $ext_url !== '')
                            ? $ext_url
                            : ('/page/community_view.php?idx=' . (int) $row['co_idx']);
                        $cat = community_category_label((string) ($row['co_category'] ?? ''));
                        ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
                               class="member-profile-list-item"
                               <?php echo ($is_ext && $ext_url !== '') ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <span class="member-profile-list-cat"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="member-profile-list-title"><?php echo htmlspecialchars((string) $row['co_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="member-profile-list-meta">
                                    <?php echo date('Y-m-d', strtotime((string) $row['co_created_at'])); ?>
                                    · 조회 <?php echo number_format((int) ($row['co_views'] ?? 0)); ?>
                                    <?php if ((int) ($row['co_comments'] ?? 0) > 0): ?>
                                        · 댓글 <?php echo number_format((int) $row['co_comments']); ?>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="member-profile-section" id="trade" aria-labelledby="member-profile-trade-heading">
            <h2 id="member-profile-trade-heading" class="member-profile-section-title">
                거래게시판
                <span class="member-profile-section-count"><?php echo number_format($cnt_trade); ?></span>
            </h2>
            <?php if (empty($trade_rows)): ?>
                <div class="board-empty">
                    <p>등록한 거래글이 없습니다.</p>
                </div>
            <?php else: ?>
                <div class="trade-grid member-profile-trade-grid">
                    <?php foreach ($trade_rows as $row):
                        $ds = $deal_labels[(int) ($row['tr_deal_status'] ?? 1)] ?? $deal_labels[1];
                        $ds['label'] = trade_deal_status_label((int) ($row['tr_deal_status'] ?? 1), $row['tr_type'] ?? 'sell');
                        $ts = strtotime((string) ($row['tr_created_at'] ?? ''));
                        $is_box = ($row['tr_item_type'] ?? '') === 'box';
                        $img = $row['thumb_path'] ?? '';
                        ?>
                        <div class="trade-item <?php echo htmlspecialchars(trade_list_item_class($row), ENT_QUOTES, 'UTF-8'); ?>">
                            <a href="/trade/trade_view.php?idx=<?php echo (int) $row['tr_idx']; ?>" class="trade-item-link">
                                <div class="trade-thumb <?php echo $img ? 'has-image' : ''; ?>">
                                    <?php if (trade_is_held($row)): ?>
                                        <span class="deal-status ds-hold">게시중지</span>
                                    <?php else: ?>
                                    <span class="deal-status <?php echo $ds['class']; ?>"><?php echo $ds['label']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($img): ?>
                                        <img class="trade-image"
                                             src="<?php echo htmlspecialchars(public_url($img), ENT_QUOTES, 'UTF-8'); ?>"
                                             alt=""
                                             loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <?php if ($is_box): ?>
                                            <span class="trade-emoji" aria-hidden="true">📦</span>
                                        <?php else: ?>
                                            <span class="trade-placeholder-tcg" aria-hidden="true"><span class="trade-placeholder-tcg-inner"></span></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="trade-body">
                                    <p class="trade-title"><?php echo htmlspecialchars((string) $row['tr_title'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="trade-card"><?php echo htmlspecialchars((string) ($row['tr_card_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="trade-price">
                                        <?php if ((int) ($row['tr_price'] ?? 0) > 0): ?>
                                            <small>₩</small><?php echo number_format((int) $row['tr_price']); ?>
                                        <?php else: ?>
                                            <span class="trade-price-free">가격제안</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="trade-foot">
                                        <span><?php echo $ts ? date(date('Y-m-d') === date('Y-m-d', $ts) ? 'H:i' : 'm/d', $ts) : '—'; ?></span>
                                        <span>· 조회 <?php echo number_format((int) ($row['tr_views'] ?? 0)); ?></span>
                                        <?php
                                        $inquiry_n = (int) ($row['tr_comments'] ?? 0);
                                        if ($inquiry_n > 0):
                                        ?>
                                            <span class="trade-inquiry-count" title="문의">· 문의 <?php echo number_format($inquiry_n); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="member-profile-section" id="reviews" aria-labelledby="member-profile-reviews-heading">
            <h2 id="member-profile-reviews-heading" class="member-profile-section-title">
                구매후기
                <span class="member-profile-section-count"><?php echo number_format($cnt_reviews); ?></span>
            </h2>
            <?php if (empty($review_rows)): ?>
                <div class="board-empty">
                    <p>아직 등록된 거래 후기가 없습니다.</p>
                </div>
            <?php else: ?>
                <ul class="member-profile-reviews">
                    <?php foreach ($review_rows as $rv):
                        $buyer_label = trim((string) ($rv['buyer_nick'] ?? ''));
                        if ($buyer_label === '') {
                            $buyer_label = '구매자';
                        }
                        $rating = (int) ($rv['tsr_rating'] ?? 0);
                        ?>
                        <li class="member-profile-review-item">
                            <div class="member-profile-review-head">
                                <?php echo trade_seller_review_stars_html($rating); ?>
                                <span class="member-profile-review-buyer"><?php echo htmlspecialchars($buyer_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="member-profile-review-date">
                                    <?php echo !empty($rv['tsr_created_at'])
                                        ? date('Y-m-d', strtotime((string) $rv['tsr_created_at']))
                                        : ''; ?>
                                </span>
                            </div>
                            <p class="member-profile-review-body">
                                <?php echo nl2br(htmlspecialchars((string) ($rv['tsr_body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
