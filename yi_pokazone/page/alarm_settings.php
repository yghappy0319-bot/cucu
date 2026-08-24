<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/webpush.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/alarm_settings.php'));
}

$page  = 'alarm_settings';
$title = '알림 설정';
$meta_description = 'Pokazone 거래 메시지·경매 입찰 추월 알림을 기기별로 설정합니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '알림 설정', 'url' => '/page/alarm_settings.php'],
];
include __DIR__ . '/../include/header.php';

$wp_ready    = webpush_is_ready();
$wp_https_ok = webpush_request_looks_https();
?>

<section class="alarm-settings">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">알림 설정</h1>
            </div>
            <div class="alarm-settings-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
            </div>
        </div>

        <div class="mypage-panel alarm-settings-panel" id="alarm-settings-webpush">
            <h3 class="mypage-panel-title">알림</h3>
            <?php if (!$wp_ready):
                $wp_ready_rows = webpush_readiness_rows();
                ?>
                <div class="trade-webpush-blocked alert-soft">
                    <p class="trade-webpush-blocked-lead">서버에서 아래 항목이 모두 준비되어야 알림을 켤 수 있습니다. <strong>실패</strong> 항목만 맞추면 됩니다.</p>
                    <ul class="trade-webpush-readiness">
                        <?php foreach ($wp_ready_rows as $rw): ?>
                            <li class="<?php echo $rw['ok'] ? 'is-ok' : 'is-fail'; ?>">
                                <span class="trade-webpush-readiness-mark"><?php echo $rw['ok'] ? '✓' : '✗'; ?></span>
                                <span class="trade-webpush-readiness-title"><?php echo htmlspecialchars($rw['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($rw['hint'] !== ''): ?>
                                    <span class="trade-webpush-readiness-hint"><?php echo htmlspecialchars($rw['hint'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (!$wp_https_ok): ?>
                <p class="trade-webpush-blocked alert-soft"><strong>HTTPS</strong>로 접속해야 알림을 켤 수 있습니다. (리버스 프록시 사용 시 <code>X-Forwarded-Proto: https</code> 설정을 확인하세요.)</p>
            <?php else: ?>
                <div data-pz-webpush class="alarm-settings-webpush-controls">
                    <p class="trade-webpush-hint pz-webpush-status" aria-live="polite"></p>
                    <div class="pz-webpush-switch-row alarm-settings-switch-row">
                        <span class="pz-webpush-switch-label" id="alarm-settings-switch-label">푸시 알림</span>
                        <label class="pz-webpush-switch">
                            <input type="checkbox"
                                   id="alarm-settings-toggle"
                                   class="pz-webpush-toggle"
                                   role="switch"
                                   aria-labelledby="alarm-settings-switch-label" />
                            <span class="pz-webpush-switch-track" aria-hidden="true"></span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
