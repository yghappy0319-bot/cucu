<?php
require_once __DIR__ . '/../lib/_function.php';

if (is_login()) {
    alert_goto('이미 로그인된 상태입니다.', '/');
}

$page  = 'register';
$title = '회원가입';
$meta_description = 'Pokazone 회원가입. 포켓몬 카드 거래와 커뮤니티에 참여하고 실시간 시세와 TOP 카드를 확인해 보세요.';
$meta_noindex = true;
include __DIR__ . '/../include/header.php';
?>

<section class="auth">
    <div class="auth-box">
        <div class="auth-head">
            <h1>회원가입</h1>
            <p>Pokazone에 오신 것을 환영합니다. 안전한 카드 거래를 지금 시작해 보세요.</p>
        </div>

        <form class="auth-form" action="/proc/register_proc.php<?php echo isset($_GET['debug']) ? '?debug=1' : ''; ?>" method="post" autocomplete="off" novalidate>
            <?php if (isset($_GET['debug'])): ?>
                <input type="hidden" name="debug" value="1">
            <?php endif; ?>
            <div class="field">
                <label for="mb_id">아이디</label>
                <input type="text" id="mb_id" name="mb_id" maxlength="20" required
                       placeholder="영문/숫자 4~20자" pattern="^[A-Za-z0-9_]{4,20}$">
                <p class="help">영문, 숫자, 밑줄(_) 포함 4~20자</p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="mb_pw">비밀번호</label>
                    <input type="password" id="mb_pw" name="mb_pw" required
                           placeholder="8자 이상" minlength="8" maxlength="50">
                </div>
                <div class="field">
                    <label for="mb_pw_confirm">비밀번호 확인</label>
                    <input type="password" id="mb_pw_confirm" name="mb_pw_confirm" required
                           placeholder="다시 입력" minlength="8" maxlength="50">
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="mb_name">이름</label>
                    <input type="text" id="mb_name" name="mb_name" maxlength="20" required placeholder="홍길동">
                </div>
                <div class="field">
                    <label for="mb_nick">닉네임</label>
                    <input type="text" id="mb_nick" name="mb_nick" maxlength="20" required placeholder="트레이너 이름">
                </div>
            </div>

            <div class="field">
                <label for="mb_email">이메일</label>
                <input type="email" id="mb_email" name="mb_email" maxlength="100" required placeholder="you@example.com">
            </div>

            <div class="field">
                <label for="mb_phone">휴대폰</label>
                <input type="tel" id="mb_phone" name="mb_phone" maxlength="13" required
                       placeholder="010-0000-0000" inputmode="numeric" autocomplete="tel"
                       title="휴대폰 번호(숫자)">
                <p class="help">010-1234-5678 형식 (숫자만 입력해도 하이픈이 붙습니다)</p>
            </div>

            <div class="agree-box">
                <label class="agree agree-all">
                    <input type="checkbox" id="agree_all">
                    <span><strong>전체 동의</strong></span>
                </label>
                <hr>
                <label class="agree">
                    <input type="checkbox" name="agree_terms" value="1" required>
                    <span>[필수] <a href="/page/terms.php" target="_blank">이용약관</a>에 동의합니다.</span>
                </label>
                <label class="agree">
                    <input type="checkbox" name="agree_privacy" value="1" required>
                    <span>[필수] <a href="/page/privacy.php" target="_blank">개인정보 수집·이용</a>에 동의합니다.</span>
                </label>
                <label class="agree">
                    <input type="checkbox" name="agree_marketing" value="1">
                    <span>[선택] 마케팅 정보 수신에 동의합니다.</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block">가입하기</button>

            <p class="auth-foot">
                이미 계정이 있으신가요? <a href="/login.php">로그인</a>
            </p>
        </form>
    </div>
</section>

<script>
(function(){
    const all = document.getElementById('agree_all');
    const boxes = document.querySelectorAll('.agree-box input[type="checkbox"]:not(#agree_all)');
    all?.addEventListener('change', () => boxes.forEach(b => b.checked = all.checked));
    boxes.forEach(b => b.addEventListener('change', () => {
        all.checked = Array.from(boxes).every(x => x.checked);
    }));

    const phone = document.getElementById('mb_phone');
    function formatKrMobile(s) {
        const d = String(s).replace(/\D/g, '').slice(0, 11);
        if (d.length <= 3) return d;
        if (d.slice(0, 3) === '010') {
            if (d.length <= 7) return d.slice(0, 3) + '-' + d.slice(3);
            return d.slice(0, 3) + '-' + d.slice(3, 7) + '-' + d.slice(7, 11);
        }
        const e = d.slice(0, 10);
        if (e.length <= 6) return e.slice(0, 3) + '-' + e.slice(3);
        return e.slice(0, 3) + '-' + e.slice(3, 6) + '-' + e.slice(6, 10);
    }
    phone?.addEventListener('input', () => { phone.value = formatKrMobile(phone.value); });

    const form = document.querySelector('.auth-form');
    form?.addEventListener('submit', (e) => {
        const pw  = document.getElementById('mb_pw').value;
        const pw2 = document.getElementById('mb_pw_confirm').value;
        if (pw !== pw2) { e.preventDefault(); alert('비밀번호가 일치하지 않습니다.'); return; }
        if (phone) {
            const d = phone.value.replace(/\D/g, '');
            if (!/^01[016789]\d{7,8}$/.test(d)) {
                e.preventDefault();
                alert('휴대폰 번호를 올바르게 입력해 주세요. (예: 010-1234-5678)');
            }
        }
    });
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
