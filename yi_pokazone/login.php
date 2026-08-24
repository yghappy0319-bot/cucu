<?php
require_once __DIR__ . '/lib/_function.php';

if (is_login()) {
    alert_goto('이미 로그인된 상태입니다.', '/');
}

$return_url = $_GET['return'] ?? '/';
$saved_id   = $_COOKIE['pz_save_id'] ?? '';
$login_block_notice = '';
if (!empty($_SESSION['pz_login_block_notice'])) {
    $login_block_notice = trim((string) $_SESSION['pz_login_block_notice']);
    unset($_SESSION['pz_login_block_notice']);
}

$page  = 'login';
$title = '로그인';
$meta_description = 'Pokazone에 로그인하고 포켓몬 카드 거래와 커뮤니티 활동을 시작해 보세요.';
$meta_noindex = true;
$admin_session_on = is_admin_login();
include __DIR__ . '/include/header.php';
?>

<section class="auth">
    <div class="auth-box auth-box-sm">
        <div class="auth-head">
            <h1>로그인</h1>
            <p>Pokazone 계정으로 로그인하고 거래를 시작하세요.</p>
            <?php if (!empty($admin_session_on)): ?>
                <p class="auth-session-split-hint">관리자 로그인과는 별도입니다. 여기에 회원으로 로그인해도 관리자 세션은 유지됩니다.</p>
            <?php endif; ?>
        </div>

        <?php if ($login_block_notice !== ''): ?>
            <div class="auth-login-block" role="alert">
                <strong>접속 제한 안내</strong>
                <p><?php echo htmlspecialchars($login_block_notice, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <form class="auth-form" action="/proc/login_proc.php" method="post" autocomplete="on">
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_url); ?>">

            <div class="field">
                <label for="mb_id">아이디</label>
                <input type="text" id="mb_id" name="mb_id" maxlength="20" required
                       value="<?php echo htmlspecialchars($saved_id); ?>"
                       placeholder="아이디 입력" autocomplete="username">
            </div>

            <div class="field">
                <label for="mb_pw">비밀번호</label>
                <input type="password" id="mb_pw" name="mb_pw" maxlength="50" required
                       placeholder="비밀번호 입력" autocomplete="current-password">
            </div>

            <div class="login-opt">
                <label class="agree">
                    <input type="checkbox" name="save_id" value="1" <?php echo $saved_id ? 'checked' : ''; ?>>
                    <span>아이디 저장</span>
                </label>
                <a href="/page/find.php" class="forgot">아이디 / 비밀번호 찾기</a>
            </div>

            <button type="submit" class="btn btn-primary btn-block">로그인</button>

            <p class="auth-foot">
                아직 회원이 아니신가요? <a href="/page/register.php">회원가입</a>
            </p>
        </form>
    </div>
</section>

<?php include __DIR__ . '/include/footer.php'; ?>
