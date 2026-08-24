<?php
require_once __DIR__ . '/../lib/_function.php';

if (is_login()) {
    alert_goto('이미 로그인된 상태입니다.', '/');
}

$type = $_GET['type'] ?? 'id';
$type = ($type === 'pw') ? 'pw' : 'id';

$page  = 'find';
$title = '아이디 · 비밀번호 찾기';
$meta_description = 'Pokazone 아이디 찾기 및 비밀번호 재설정. 가입 시 입력한 이름과 이메일로 확인합니다.';
$meta_noindex = true;
include __DIR__ . '/../include/header.php';
?>

<section class="auth">
    <div class="auth-box">
        <div class="auth-head">
            <h1>아이디 · 비밀번호 찾기</h1>
            <p>가입 시 등록한 정보로 확인할 수 있습니다.</p>
        </div>

        <div class="find-tabs" role="tablist" aria-label="찾기 유형">
            <a href="/page/find.php?type=id" class="find-tab<?php echo $type === 'id' ? ' is-active' : ''; ?>"
               role="tab" aria-selected="<?php echo $type === 'id' ? 'true' : 'false'; ?>">아이디 찾기</a>
            <a href="/page/find.php?type=pw" class="find-tab<?php echo $type === 'pw' ? ' is-active' : ''; ?>"
               role="tab" aria-selected="<?php echo $type === 'pw' ? 'true' : 'false'; ?>">비밀번호 찾기</a>
        </div>

        <?php if ($type === 'id'): ?>
            <form class="auth-form" action="/proc/find_id_proc.php" method="post" autocomplete="off" novalidate>
                <div class="field">
                    <label for="find_name">이름</label>
                    <input type="text" id="find_name" name="mb_name" maxlength="50" required placeholder="가입 시 이름">
                </div>
                <div class="field">
                    <label for="find_email">이메일</label>
                    <input type="email" id="find_email" name="mb_email" maxlength="100" required
                           placeholder="가입 시 이메일" autocomplete="email">
                </div>
                <p class="help find-hint">일치하는 계정이 있으면 아이디 일부를 안내하고 로그인 페이지로 이동합니다.</p>
                <button type="submit" class="btn btn-primary btn-block">아이디 찾기</button>
            </form>
        <?php else: ?>
            <form class="auth-form" action="/proc/find_pw_proc.php" method="post" autocomplete="off" novalidate>
                <div class="field">
                    <label for="pw_mb_id">아이디</label>
                    <input type="text" id="pw_mb_id" name="mb_id" maxlength="20" required
                           placeholder="영문/숫자 4~20자" pattern="^[A-Za-z0-9_]{4,20}$" autocomplete="username">
                </div>
                <div class="field">
                    <label for="pw_mb_name">이름</label>
                    <input type="text" id="pw_mb_name" name="mb_name" maxlength="50" required placeholder="가입 시 이름">
                </div>
                <div class="field">
                    <label for="pw_mb_email">이메일</label>
                    <input type="email" id="pw_mb_email" name="mb_email" maxlength="100" required
                           placeholder="가입 시 이메일" autocomplete="email">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="pw_new">새 비밀번호</label>
                        <input type="password" id="pw_new" name="mb_pw" required
                               placeholder="8자 이상" minlength="8" maxlength="50" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="pw_new2">새 비밀번호 확인</label>
                        <input type="password" id="pw_new2" name="mb_pw_confirm" required
                               placeholder="다시 입력" minlength="8" maxlength="50" autocomplete="new-password">
                    </div>
                </div>
                <p class="help find-hint">아이디·이름·이메일이 모두 가입 정보와 일치할 때 비밀번호가 변경됩니다.</p>
                <button type="submit" class="btn btn-primary btn-block">비밀번호 변경</button>
            </form>
            <script>
            (function(){
                const form = document.querySelector('.auth-form');
                form?.addEventListener('submit', (e) => {
                    const a = document.getElementById('pw_new').value;
                    const b = document.getElementById('pw_new2').value;
                    if (a !== b) { e.preventDefault(); alert('비밀번호가 일치하지 않습니다.'); }
                });
            })();
            </script>
        <?php endif; ?>

        <p class="auth-foot">
            <a href="/login.php">로그인</a>
            ·
            <a href="/page/register.php">회원가입</a>
        </p>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
