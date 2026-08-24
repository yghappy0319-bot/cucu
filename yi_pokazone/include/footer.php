</main>

<?php
if (!function_exists('nav_show_card_price')) {
    require_once __DIR__ . '/../lib/_function.php';
}
if (!isset($__nav_show_card_price)) {
    $__nav_show_card_price = nav_show_card_price();
}
if (!function_exists('nav_show_market_price')) {
    require_once __DIR__ . '/../lib/_function.php';
}
if (!isset($__nav_show_market_price)) {
    $__nav_show_market_price = nav_show_market_price();
}
$__f_brand = function_exists('site_setting_get') ? site_setting_get('site_name', 'Pokazone') : 'Pokazone';
$__f_desc  = function_exists('site_setting_get')
    ? site_setting_get('site_tagline', '포켓몬 카드 수집가와 트레이너를 위한 안전한 거래 플랫폼. 검증된 시세와 안심 거래로 즐거운 컬렉션을 완성하세요.')
    : '포켓몬 카드 수집가와 트레이너를 위한 안전한 거래 플랫폼. 검증된 시세와 안심 거래로 즐거운 컬렉션을 완성하세요.';
$__f_extra = function_exists('site_setting_get') ? site_setting_get('footer_notice', '') : '';
$__hide_footer_menu = !empty($hide_footer_menu);
?>
<?php if (!$__hide_footer_menu): ?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="/" class="logo">
                    <span class="logo-dot" aria-hidden="true"></span>
                    <span><?php echo htmlspecialchars($__f_brand); ?></span>
                </a>
                <p class="footer-brand-desc"><?php echo nl2br(htmlspecialchars($__f_desc, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php if ($__f_extra !== ''): ?>
                    <p class="footer-extra footer-brand-extra"><?php echo nl2br(htmlspecialchars($__f_extra, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>
            </div>

            <details class="footer-col footer-col--acc">
                <summary class="footer-accordion-summary">서비스</summary>
                <div class="footer-accordion-panel">
                    <ul>
                        <li><a href="/page/community.php">커뮤니티</a></li>
                        <li><a href="/trade/trade.php">거래게시판</a></li>
                        <li><a href="/auction/auction.php">경매</a></li>
                        <li><a href="/page/shop.php">쇼핑몰</a></li>
                        <?php if ($__nav_show_card_price): ?>
                        <li><a href="/page/card_price.php">박스 시세</a></li>
                        <?php endif; ?>
                        <?php if (!empty($__nav_show_market_price)): ?>
                        <li><a href="/page/market_price.php">전체 시세</a></li>
                        <?php endif; ?>
                        <li><a href="/page/attendance.php">출석체크</a></li>
                    </ul>
                </div>
            </details>

            <details class="footer-col footer-col--acc">
                <summary class="footer-accordion-summary">고객지원</summary>
                <div class="footer-accordion-panel">
                    <ul>
                        <li><a href="/page/notice.php">공지사항</a></li>
                        <li><a href="/page/faq.php">자주 묻는 질문</a></li>
                        <li><a href="/page/inquiry.php">1:1 문의</a></li>
                        <li><a href="/page/guide.php">거래 가이드</a></li>
                    </ul>
                </div>
            </details>

            <details class="footer-col footer-col--acc">
                <summary class="footer-accordion-summary">회사소개</summary>
                <div class="footer-accordion-panel">
                    <ul>
                        <li><a href="/page/about.php">Pokazone 소개</a></li>
                        <li><a href="/page/terms.php">이용약관</a></li>
                        <li><a href="/page/privacy.php">개인정보처리방침</a></li>
                        <li><a href="/page/partner.php">제휴문의</a></li>
                    </ul>
                </div>
            </details>
        </div>

        <div class="footer-bottom">
            <div class="footer-legal">
                <?php foreach (site_legal_business_lines() as $__footer_legal_line): ?>
                    <p class="footer-legal-line"><?php echo htmlspecialchars($__footer_legal_line, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($__f_brand); ?>. All rights reserved.</p>
            </div>
            <ul class="sns" aria-label="SNS">
                <li><a href="#" aria-label="Instagram">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg>
                </a></li>
                <li><a href="#" aria-label="YouTube">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path>
                        <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
                    </svg>
                </a></li>
                <li><a href="#" aria-label="X">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a></li>
            </ul>
        </div>
    </div>
</footer>
<?php endif; ?>

<?php
// 사이트 레이어 팝업 (관리자 등록)
if (empty($hide_site_popup)) {
    include __DIR__ . '/site_popup.php';
}
?>

<?php echo $page_footer_extra ?? ''; ?>
<?php
$__pz_main_js = dirname(__DIR__) . '/assets/js/main.js';
$__pz_main_v = '';
if (is_readable($__pz_main_js)) {
    $__pz_main_v = '?m=' . (string) filemtime($__pz_main_js);
}
?>
<script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.1/dist/js/glightbox.min.js" crossorigin></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" crossorigin></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js" crossorigin></script>
<script src="/assets/js/main.js<?php echo htmlspecialchars($__pz_main_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
$__pz_search_rank_js = dirname(__DIR__) . '/assets/js/search_rank.js';
$__pz_search_rank_v = '';
if (is_readable($__pz_search_rank_js)) {
    $__pz_search_rank_v = '?m=' . (string) filemtime($__pz_search_rank_js);
}
?>
<script src="/assets/js/search_rank.js<?php echo htmlspecialchars($__pz_search_rank_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
$__pz_trade_wish_js = dirname(__DIR__) . '/trade/assets/js/trade_wish.js';
$__pz_trade_wish_v = '';
if (is_readable($__pz_trade_wish_js)) {
    $__pz_trade_wish_v = '?m=' . (string) filemtime($__pz_trade_wish_js);
}
?>
<script src="/trade/assets/js/trade_wish.js<?php echo htmlspecialchars($__pz_trade_wish_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
$pz_footer_webpush = isset($__pz_webpush_browser) && $__pz_webpush_browser;
if ($pz_footer_webpush) {
    require_once __DIR__ . '/../lib/webpush.php';
    ?>
<script>
window.__WEB_PUSH_VAPID__ = <?php echo json_encode(webpush_public_key_for_js(), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/trade/assets/js/trade_webpush.js"></script>
    <?php
}
?>
</body>
</html>
