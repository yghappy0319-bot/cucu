<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '사이트 설정';
$ad_topbar = $ad;
$ad_menu   = 'settings';
include __DIR__ . '/include/admin_header.php';

$table_ok = site_setting_table_ready();
$name     = site_setting_get('site_name', 'Pokazone');
$tagline  = site_setting_get('site_tagline', '');
$email    = site_setting_get('contact_email', '');
$footer   = site_setting_get('footer_notice', '');
$trade_fee   = platform_fee_trade_percent();
$auction_fee = platform_fee_auction_percent();
?>

<div class="ad-page">
    <div class="ad-card">
        <h1 class="ad-title">사이트 설정</h1>
        <?php if (!$table_ok): ?>
            <div class="ad-alert ad-alert--error" style="margin-bottom:1rem;">
                설정 저장을 위해 <code>sql/tb_site_setting.sql</code>을 DB에 적용해 주세요. 적용 전까지는 아래 값은 미리보기용 기본값입니다.
            </div>
        <?php endif; ?>
        <p class="ad-p">로고에 표시되는 이름 등 일부 값은 저장 후 사용자 화면 헤더에 반영됩니다. DB 접속 정보는 보안상 이 화면에서 바꾸지 않습니다.</p>

        <form class="ad-form" action="/proc/admin_settings_proc.php" method="post">
            <div class="ad-field">
                <label for="site_name">사이트 이름</label>
                <input type="text" id="site_name" name="site_name" maxlength="60" required
                       value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="ad-field">
                <label for="site_tagline">짧은 소개 (선택)</label>
                <input type="text" id="site_tagline" name="site_tagline" maxlength="200"
                       value="<?php echo htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="예: 포켓몬 카드 거래·커뮤니티">
            </div>
            <div class="ad-field">
                <label for="contact_email">대표 문의 이메일 (선택)</label>
                <input type="text" id="contact_email" name="contact_email" maxlength="100"
                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="ad-field">
                <label for="footer_notice">푸터 안내 문구 (선택)</label>
                <textarea id="footer_notice" name="footer_notice" maxlength="500" placeholder="운영 시간, 사업자 정보 등"><?php echo htmlspecialchars($footer, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <hr style="border:0;border-top:1px solid #e5e7eb;margin:1.25rem 0;">
            <h2 class="ad-title" style="font-size:1.05rem;margin:0 0 0.75rem;">플랫폼 수수료</h2>
            <p class="ad-p" style="margin-top:0;">구매확정 시 판매자 정산에서 차감되는 비율입니다. 변경 즉시 신규 정산에 반영됩니다. (이미 확정된 건은 당시 수수료 유지)</p>
            <div class="ad-field" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div>
                    <label for="trade_platform_fee_percent">거래게시판 수수료 (%)</label>
                    <input type="number" id="trade_platform_fee_percent" name="trade_platform_fee_percent"
                           min="0" max="100" step="1" required
                           value="<?php echo (int) $trade_fee; ?>">
                </div>
                <div>
                    <label for="auction_platform_fee_percent">경매 수수료 (%)</label>
                    <input type="number" id="auction_platform_fee_percent" name="auction_platform_fee_percent"
                           min="0" max="100" step="1" required
                           value="<?php echo (int) $auction_fee; ?>">
                </div>
            </div>

            <div class="ad-actions">
                <button type="submit" class="ad-btn">저장</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
