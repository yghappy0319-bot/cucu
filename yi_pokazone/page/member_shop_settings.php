<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_public.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_shop_settings.php'));
}

$mb_idx = (int) $me['mb_idx'];
$nick   = (string) ($me['mb_nick'] ?? '');
$intro  = '';
$shop_ready = member_shop_intro_column_ready();

if ($shop_ready) {
    $row = db_assoc(db_query("
        SELECT mb_shop_intro
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    $intro = trim((string) ($row['mb_shop_intro'] ?? ''));
}

$shop_url = member_profile_url($mb_idx);

$page  = 'member_shop_settings';
$title = '상점 설정';
$meta_description = '판매자 상점 소개를 설정합니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '상점 설정', 'url' => '/page/member_shop_settings.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage member-shop-settings">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">상점 설정</h1>
                <p class="board-desc">판매자 상점에 표시될 소개글을 작성할 수 있습니다.</p>
            </div>
            <div class="mypage-head-actions">
                <?php if ($shop_url !== ''): ?>
                <a href="<?php echo htmlspecialchars($shop_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-sm">내 상점 보기</a>
                <?php endif; ?>
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
            </div>
        </div>

        <?php if (!$shop_ready): ?>
            <div class="mypage-panel">
                <p class="member-settle-warn">
                    상점 소개 기능을 사용하려면 DB에
                    <code>sql/migrate_tb_member_shop_intro.sql</code> 을 적용해 주세요.
                </p>
            </div>
        <?php else: ?>
            <div class="mypage-panel">
                <h2 class="mypage-panel-title">상점 소개</h2>
                <p class="member-shop-settings-note">
                    닉네임을 클릭해 들어오는 판매자 상점 상단에 표시됩니다.
                    비우면 소개글은 표시되지 않습니다.
                </p>
                <form class="auth-form member-shop-settings-form" action="/proc/member_shop_settings_proc.php" method="post">
                    <div class="field">
                        <label for="mb_shop_intro">소개글</label>
                        <textarea id="mb_shop_intro" name="mb_shop_intro" rows="6"
                                  maxlength="<?php echo (int) MEMBER_SHOP_INTRO_MAX; ?>"
                                  placeholder="예: 포켓몬 카드 직거래·택배 모두 가능합니다. 문의 편하게 주세요."><?php echo htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="help">최대 <?php echo (int) MEMBER_SHOP_INTRO_MAX; ?>자 · 현재 <span id="shop-intro-count"><?php echo mb_strlen($intro); ?></span>자</p>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">저장하기</button>
                        <?php if ($shop_url !== ''): ?>
                        <a href="<?php echo htmlspecialchars($shop_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">내 상점 보기</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var el = document.getElementById('mb_shop_intro');
    var count = document.getElementById('shop-intro-count');
    if (!el || !count) return;
    function sync() {
        count.textContent = String((el.value || '').length);
    }
    el.addEventListener('input', sync);
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
