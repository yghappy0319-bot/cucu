<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_seo.php';
require_once __DIR__ . '/../lib/_search_rank.php';
if (!isset($page)) { $page = ''; }
$__me = login_member();
// SEO 옵션 모음
$__seo = [
    'title'        => $title            ?? '',
    'description'  => $meta_description ?? '',
    'keywords'     => $meta_keywords    ?? '',
    'image'        => $meta_image       ?? '',
    'type'         => $meta_type        ?? 'website',
    'canonical'    => $meta_canonical   ?? '',
    'noindex'      => $meta_noindex     ?? false,
    'published_at' => $meta_published_at ?? '',
    'modified_at'  => $meta_modified_at  ?? '',
    'author'       => $meta_author       ?? '',
];
$__breadcrumb_items = $meta_breadcrumb ?? [];
$__jsonld_extra     = $meta_jsonld     ?? null;
$__brand      = function_exists('site_setting_get') ? site_setting_get('site_name', 'Pokazone') : 'Pokazone';
$__full_title = $__seo['title'] !== ''
    ? htmlspecialchars($__seo['title']) . ' | ' . htmlspecialchars($__brand)
    : htmlspecialchars($__brand) . ' | 포켓몬 카드 거래 플랫폼';
$__trade_unread = ($__me && trade_chat_room_has_read_columns())
    ? trade_chat_unread_count_for_member((int) $__me['mb_idx'])
    : 0;
$__nav_msg_aria = '거래 메시지함';
if ($__me && $__trade_unread > 0) {
    $__ur_lbl_nav = $__trade_unread > 99 ? '99개 이상' : ((int) $__trade_unread) . '개';
    $__nav_msg_aria = '거래 메시지함, 읽지 않은 메시지 ' . $__ur_lbl_nav;
}

$__pz_webpush_browser = false;
if ($__me) {
    require_once __DIR__ . '/../lib/webpush.php';
    $__pz_webpush_browser = webpush_can_use_in_browser();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $__full_title; ?></title>
    <?php seo_render_meta($__seo); ?>
    <meta name="naver-site-verification" content="">
    <meta name="google-site-verification" content="">

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/img/favicon-48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/img/favicon-192.png">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <meta name="naver-site-verification" content="1db286d47bce3bd9874528e567173e787c86759d" />

    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Pokazone">

    <link rel="stylesheet" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.3.1/dist/css/glightbox.min.css" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css?date=<?php echo date('His'); ?>">
    <link rel="stylesheet" href="/assets/css/search_rank.css?date=<?php echo date('His'); ?>">

    <?php
    $pz_pub_pre = isset($site_path_prefix) ? trim((string) $site_path_prefix, '/') : '';
    ?>
    <script>window.__PZ_PUBLIC_PREFIX__=<?php echo json_encode($pz_pub_pre, JSON_UNESCAPED_UNICODE); ?>;</script>

    <?php
        // 사이트 공통 JSON-LD
        seo_render_jsonld(seo_site_jsonld());

        // Breadcrumb 가 있으면 JSON-LD 출력
        if (!empty($__breadcrumb_items)) {
            seo_render_jsonld(seo_breadcrumb_jsonld($__breadcrumb_items));
        }

        // 페이지별 추가 JSON-LD
        if (!empty($__jsonld_extra)) {
            seo_render_jsonld($__jsonld_extra);
        }
    ?>
    <?php echo $page_head_extra ?? ''; ?>
</head>
<body>

<a class="skip-link" href="#main">본문 바로가기</a>

<header class="site-header" role="banner">
    <div class="container header-inner">
        <a href="/" class="logo" aria-label="<?php echo htmlspecialchars($__brand); ?> 홈">
            <span class="logo-dot" aria-hidden="true"></span>
            <span><?php echo htmlspecialchars($__brand); ?></span>
        </a>

        <nav class="nav" aria-label="주요 메뉴" id="mainNav">
            <?php /* 배포 확인: 페이지 소스에서 pz-build 검색 (header 수정 시 숫자 변경) */ ?>
            <!-- pz-build:header=<?php echo (int) @filemtime(__FILE__); ?> nav-card-price=<?php echo nav_show_card_price() ? '1' : '0'; ?> -->
            <a href="/page/community.php" class="<?php echo $page === 'community' ? 'is-active' : ''; ?>"<?php echo $page === 'community' ? ' aria-current="page"' : ''; ?>>커뮤니티</a>
            <a href="/trade/trade.php" class="<?php echo $page === 'trade' ? 'is-active' : ''; ?>"<?php echo $page === 'trade' ? ' aria-current="page"' : ''; ?>>거래게시판</a>
            <a href="/auction/auction.php" class="<?php echo $page === 'auction' ? 'is-active' : ''; ?>"<?php echo $page === 'auction' ? ' aria-current="page"' : ''; ?>>경매</a>
            <a href="/page/shop.php" class="<?php echo $page === 'shop' ? 'is-active' : ''; ?>"<?php echo $page === 'shop' ? ' aria-current="page"' : ''; ?>>쇼핑몰</a>
            <a href="/page/draw.php" class="<?php echo $page === 'draw' ? 'is-active' : ''; ?>"<?php echo $page === 'draw' ? ' aria-current="page"' : ''; ?>>뽑기</a>
            <?php if (nav_show_card_price()): ?>
            <a href="/page/card_price.php" class="<?php echo $page === 'card_price' ? 'is-active' : ''; ?>"<?php echo $page === 'card_price' ? ' aria-current="page"' : ''; ?>>박스 시세</a>
            <?php endif; ?>
            <?php if (nav_show_market_price()): ?>
            <a href="/page/market_price.php" class="<?php echo $page === 'market_price' ? 'is-active' : ''; ?>"<?php echo $page === 'market_price' ? ' aria-current="page"' : ''; ?>>전체 시세</a>
            <?php endif; ?>
            <?php if ($__me): ?>
                <a href="/trade/trade_messages.php"
                   class="nav-mobile-msg<?php echo $page === 'trade_messages' ? ' is-active' : ''; ?>"
                   <?php echo $page === 'trade_messages' ? ' aria-current="page"' : ''; ?>
                   aria-label="<?php echo htmlspecialchars($__nav_msg_aria, ENT_QUOTES, 'UTF-8'); ?>">
                    메시지함
                    <?php if ($__trade_unread > 0): ?>
                        <span class="nav-mobile-msg-badge" aria-hidden="true"><?php echo $__trade_unread > 99 ? '99+' : (string) (int) $__trade_unread; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <div class="nav-mobile-auth" role="group" aria-label="계정 메뉴">
                <?php if ($__me): ?>
                    <a href="/page/mypage.php" class="nav-mobile-auth-link">마이페이지</a>
                <?php else: ?>
                    <a href="/login.php" class="nav-mobile-auth-link">로그인</a>
                    <a href="/page/register.php" class="nav-mobile-auth-link nav-mobile-auth-link--muted">회원가입</a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="header-actions">
            <?php
            $__search_rank_rows = search_log_table_ready() ? search_rank_top(10) : [];
            ?>
            <div class="search-box-wrap" data-search-rank-root>
                <form class="search-box" role="search" action="/page/search.php" method="get" aria-label="상품 검색">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="search" placeholder="상품 검색" name="q" aria-label="상품 검색어" autocomplete="off">
                </form>
                <?php if (!empty($__search_rank_rows)): ?>
                <div class="search-rank-dropdown" data-search-rank-dropdown hidden>
                    <div class="search-rank-dropdown-head">
                        <strong>실시간 인기 검색어</strong>
                        <span class="search-rank-updated" data-search-rank-updated aria-live="polite"></span>
                    </div>
                    <?php
                    $search_rank_rows = $__search_rank_rows;
                    $search_rank_variant = 'header';
                    include __DIR__ . '/search_rank_list.php';
                    ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($__me): ?>
                <a href="/trade/trade_messages.php" class="btn btn-ghost btn-sm nav-msg" title="거래 메시지함" aria-label="<?php echo htmlspecialchars($__nav_msg_aria, ENT_QUOTES, 'UTF-8'); ?>">
                    메시지<?php if ($__trade_unread > 0): ?><span class="nav-msg-badge" aria-hidden="true"><?php echo $__trade_unread > 99 ? '99+' : (string) (int) $__trade_unread; ?></span><?php endif; ?>
                </a>
                <a href="/page/mypage.php" class="user-chip" title="마이페이지">
                    <span class="user-avatar" aria-hidden="true"><?php echo mb_substr(htmlspecialchars($__me['mb_nick']), 0, 1); ?></span>
                    <span class="user-nick"><?php echo htmlspecialchars($__me['mb_nick']); ?></span>
                </a>
            <?php else: ?>
                <a href="/login.php" class="btn btn-ghost btn-sm header-auth-login">로그인</a>
                <a href="/page/register.php" class="btn btn-primary btn-sm header-auth-register">회원가입</a>
            <?php endif; ?>
            <button id="menuToggle" class="menu-toggle" type="button" aria-label="메뉴" aria-expanded="false" aria-controls="mainNav">
                <span></span>
            </button>
        </div>
    </div>
</header>

<?php
if (!empty($__pz_webpush_browser) && !in_array(($page ?? ''), ['mypage', 'member_info', 'alarm_settings'], true)):
    ?>
    <div id="pz-webpush-banner"
         class="pz-webpush-banner"
         data-pz-webpush
         data-pz-webpush-banner
         hidden
         role="region"
         aria-label="거래 메시지 알림 안내">
        <div class="container pz-webpush-banner-inner">
            <div class="pz-webpush-banner-copy">
                <p class="pz-webpush-banner-lead">거래 메시지·경매 입찰 추월 알림을 이 기기에서 <strong>알림</strong>으로 받으시겠어요?</p>
                <p class="trade-webpush-hint pz-webpush-status" aria-live="polite"></p>
            </div>
            <div class="pz-webpush-banner-controls">
                <label class="pz-webpush-switch">
                    <input type="checkbox" class="pz-webpush-toggle" role="switch" aria-label="거래 메시지 알림 받기" />
                    <span class="pz-webpush-switch-track" aria-hidden="true"></span>
                </label>
                <button type="button" class="btn btn-ghost btn-sm pz-webpush-dismiss">나중에</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($__breadcrumb_items) && count($__breadcrumb_items) > 1): ?>
<nav class="breadcrumb container" aria-label="현재 위치">
    <ol>
        <?php foreach ($__breadcrumb_items as $i => $bc):
            $is_last = ($i === count($__breadcrumb_items) - 1);
        ?>
            <li>
                <?php if ($is_last): ?>
                    <span aria-current="page"><?php echo htmlspecialchars($bc['name']); ?></span>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($bc['url']); ?>"><?php echo htmlspecialchars($bc['name']); ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<main id="main" role="main">
