<?php
require_once __DIR__ . '/../lib/_function.php';

if (is_admin_login()) {
    header('Location: /admin/');
    exit;
}

$return_url = '/admin/';
$saved_id   = $_COOKIE['pz_admin_save_id'] ?? '';
$title      = '관리자 로그인';
$ad_topbar  = null;
include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page ad-page--login">
    <div class="ad-card ad-card--narrow">
        <h1 class="ad-title">로그인</h1>
        <p class="ad-lead">관리자 계정으로 로그인합니다. 회원 로그인과는 세션이 분리되어 있어, 로그인 후 새 창에서 일반 사용자로도 접속할 수 있습니다.</p>

        <form class="ad-form" action="/proc/admin_login_proc.php" method="post" autocomplete="on">
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="ad-field">
                <label for="ad_id">아이디</label>
                <input type="text" id="ad_id" name="ad_id" maxlength="30" required
                       value="<?php echo htmlspecialchars($saved_id, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="아이디" autocomplete="username">
            </div>

            <div class="ad-field">
                <label for="ad_pw">비밀번호</label>
                <input type="password" id="ad_pw" name="ad_pw" maxlength="100" required
                       placeholder="비밀번호" autocomplete="current-password">
            </div>

            <div class="ad-form__row">
                <label>
                    <input type="checkbox" name="save_id" value="1" <?php echo $saved_id ? 'checked' : ''; ?>>
                    아이디 저장
                </label>
            </div>

            <button type="submit" class="ad-btn ad-btn--primary">로그인</button>

            <p class="ad-links">
                <a href="/" target="_blank" rel="noopener">사이트 홈</a> · <a href="/login.php" target="_blank" rel="noopener">일반 로그인 (새 창)</a>
            </p>
        </form>
    </div>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
