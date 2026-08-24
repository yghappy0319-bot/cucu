<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_settle.php';
require_once __DIR__ . '/../lib/_member_phone_verify.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_info.php'));
}

$mb_idx = (int) $me['mb_idx'];
$rs     = db_query("
    SELECT mb_idx, mb_id, mb_name, mb_nick, mb_email, mb_phone, mb_level, mb_status,
           mb_point, mb_login_count, mb_last_login_at, mb_last_login_ip, mb_created_at
    FROM tb_member
    WHERE mb_idx = {$mb_idx}
    LIMIT 1
");
$member = db_assoc($rs);
if (!$member) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}

$level       = (int) $member['mb_level'];
$level_label = $level >= 9 ? '관리자' : ($level >= 5 ? 'VIP' : '일반');
$level_class = $level >= 9 ? 'is-admin' : ($level >= 5 ? 'is-vip' : '');

$st            = (int) $member['mb_status'];
$status_labels = [
    0 => '휴면',
    1 => '정상',
    2 => '정지',
    3 => '탈퇴',
];
$status_label = $status_labels[$st] ?? '알 수 없음';

$settle_ready      = member_settle_account_column_ready();
$settle_account    = $settle_ready ? member_settle_account_get($mb_idx) : null;
$settle_registered = member_settle_account_is_registered($settle_account);
$settle_banks      = member_settle_bank_presets();

$phone_verify_ready      = directsend_is_ready() && member_phone_verify_log_table_ready();
$phone_verify_remaining  = member_phone_verify_remaining_today($mb_idx);
$phone_verify_current    = (string) ($member['mb_phone'] ?? '');

$page  = 'member_info';
$title = '내 정보';
$meta_description = '회원 정보를 확인하고 이메일·휴대폰·비밀번호를 변경합니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '내 정보', 'url' => '/page/member_info.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage member-info">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">내 정보</h1>
                <p class="board-desc">가입 시 등록한 정보를 확인하고 이메일·휴대폰·비밀번호를 변경할 수 있습니다.</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <a href="/page/member_address.php" class="btn btn-outline btn-sm">배송지</a>
            </div>
        </div>

        <div class="mypage-hero">
            <div class="mypage-avatar" aria-hidden="true"><?php echo mb_substr(htmlspecialchars($member['mb_nick']), 0, 1); ?></div>
            <div class="mypage-hero-body">
                <div class="mypage-hero-top">
                    <h2 class="mypage-nick"><?php echo htmlspecialchars($member['mb_nick']); ?></h2>
                    <span class="mypage-level <?php echo $level_class; ?>"><?php echo htmlspecialchars($level_label); ?></span>
                </div>
                <p class="mypage-id"><?php echo htmlspecialchars($member['mb_id']); ?></p>
                <div class="mypage-hero-meta">
                    <span>포인트 <strong><?php echo number_format((int) $member['mb_point']); ?></strong> P</span>
                    <span>가입일 <?php echo date('Y-m-d', strtotime($member['mb_created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="mypage-panels">
            <div class="mypage-panel">
                <h3 class="mypage-panel-title">회원 정보</h3>
                <dl class="mypage-dl">
                    <div>
                        <dt>아이디</dt>
                        <dd><?php echo htmlspecialchars($member['mb_id']); ?></dd>
                    </div>
                    <div>
                        <dt>닉네임</dt>
                        <dd><?php echo htmlspecialchars($member['mb_nick']); ?></dd>
                    </div>
                    <div>
                        <dt>이름</dt>
                        <dd><?php echo htmlspecialchars($member['mb_name']); ?></dd>
                    </div>
                    <div class="member-info-editable-row">
                        <dt>이메일</dt>
                        <dd class="member-info-dd-row">
                            <form class="member-info-inline-form" action="/proc/member_email_proc.php" method="post" autocomplete="on">
                                <input type="email" id="mb_email" name="mb_email" required
                                       maxlength="100"
                                       value="<?php echo htmlspecialchars((string) ($member['mb_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="example@email.com" autocomplete="email">
                                <button type="submit" class="btn btn-primary btn-sm">변경</button>
                            </form>
                        </dd>
                    </div>
                    <div class="member-info-editable-row">
                        <dt>휴대폰</dt>
                        <dd class="member-info-dd-row">
                            <?php if ($phone_verify_ready): ?>
                            <div class="member-info-phone-verify" id="member-phone-verify">
                                <div class="member-info-inline-form member-info-phone-row">
                                    <input type="tel" id="mb_phone" name="mb_phone" maxlength="20"
                                           value="<?php echo htmlspecialchars($phone_verify_current, ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="010-1234-5678" autocomplete="tel">
                                    <button type="button" class="btn btn-outline btn-sm" id="member-phone-send-btn">인증번호 받기</button>
                                </div>
                                <div class="member-info-phone-code-row" id="member-phone-code-row" hidden>
                                    <input type="text" id="mb_phone_code" name="mb_phone_code" maxlength="6"
                                           inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                                           placeholder="6자리 인증번호" aria-label="6자리 인증번호">
                                    <button type="button" class="btn btn-primary btn-sm" id="member-phone-confirm-btn">인증 확인</button>
                                </div>
                                <p class="member-info-phone-status" id="member-phone-status" hidden role="status"></p>
                                <p class="member-info-inline-hint" id="member-phone-hint">
                                    변경할 번호 입력 후 인증번호를 받아 주세요.
                                    오늘 남은 발송 <?php echo (int) $phone_verify_remaining; ?>/<?php echo (int) MEMBER_PHONE_VERIFY_DAILY_LIMIT; ?>회
                                    · 인증번호 유효시간 3분
                                </p>
                                <?php if ($phone_verify_current !== ''): ?>
                                <form class="member-info-phone-clear-form" action="/proc/member_phone_proc.php" method="post"
                                      onsubmit="return confirm('등록된 휴대폰 번호를 삭제(미등록)하시겠습니까?');">
                                    <input type="hidden" name="mb_phone" value="">
                                    <button type="submit" class="btn btn-outline btn-sm member-info-phone-clear-btn">번호 삭제 (미등록)</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <p class="member-info-inline-hint member-info-phone-warn">
                                휴대폰 번호 변경 인증이 준비되지 않았습니다.
                                <?php if (!directsend_is_ready()): ?>
                                (DirectSend 설정: <code>config/directsend.php</code>)
                                <?php endif; ?>
                                <?php if (!member_phone_verify_log_table_ready()): ?>
                                (DB: <code>sql/tb_member_phone_verify_log.sql</code>)
                                <?php endif; ?>
                            </p>
                            <p class="member-info-dd-plain"><?php echo $phone_verify_current !== '' ? htmlspecialchars($phone_verify_current) : '—'; ?></p>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>계정 상태</dt>
                        <dd><?php echo htmlspecialchars($status_label); ?></dd>
                    </div>
                    <div>
                        <dt>로그인 횟수</dt>
                        <dd><?php echo number_format((int) $member['mb_login_count']); ?>회</dd>
                    </div>
                    <div>
                        <dt>최근 로그인</dt>
                        <dd>
                            <?php
                            if (!empty($member['mb_last_login_at'])) {
                                echo htmlspecialchars($member['mb_last_login_at']);
                                if (!empty($member['mb_last_login_ip'])) {
                                    echo ' <span class="mypage-muted">(' . htmlspecialchars($member['mb_last_login_ip']) . ')</span>';
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <?php include __DIR__ . '/../include/member_settle_panel.php'; ?>

            <div class="mypage-panel">
                <h3 class="mypage-panel-title">비밀번호 변경</h3>
                <p class="member-info-pw-lead">현재 비밀번호를 확인한 뒤 새 비밀번호를 입력해 주세요. (8자 이상 50자 이하)</p>
                <form class="auth-form member-info-pw-form" action="/proc/member_password_proc.php" method="post" autocomplete="off">
                    <div class="field">
                        <label for="mb_pw_current">현재 비밀번호</label>
                        <input type="password" id="mb_pw_current" name="mb_pw_current" required
                               minlength="1" maxlength="50" autocomplete="current-password"
                               placeholder="현재 비밀번호">
                    </div>
                    <div class="field">
                        <label for="mb_pw_new">새 비밀번호</label>
                        <input type="password" id="mb_pw_new" name="mb_pw_new" required
                               minlength="8" maxlength="50" autocomplete="new-password"
                               placeholder="8자 이상">
                    </div>
                    <div class="field">
                        <label for="mb_pw_confirm">새 비밀번호 확인</label>
                        <input type="password" id="mb_pw_confirm" name="mb_pw_confirm" required
                               minlength="8" maxlength="50" autocomplete="new-password"
                               placeholder="다시 입력">
                    </div>
                    <button type="submit" class="btn btn-primary">비밀번호 변경</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if ($phone_verify_ready): ?>
<script>
window.__MEMBER_PHONE_VERIFY__ = {
    apiUrl: '/page/member_phone_verify_api.php',
    currentPhone: <?php echo json_encode($phone_verify_current, JSON_UNESCAPED_UNICODE); ?>,
    remaining: <?php echo (int) $phone_verify_remaining; ?>,
    dailyLimit: <?php echo (int) MEMBER_PHONE_VERIFY_DAILY_LIMIT; ?>
};
</script>
<?php
$__phone_js = dirname(__DIR__) . '/assets/js/member_phone_verify.js';
$__phone_v  = is_readable($__phone_js) ? '?m=' . (string) filemtime($__phone_js) : '';
$page_footer_extra = '<script src="/assets/js/member_phone_verify.js' . htmlspecialchars($__phone_v, ENT_QUOTES, 'UTF-8') . '"></script>';
?>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>