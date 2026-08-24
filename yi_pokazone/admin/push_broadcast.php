<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/webpush.php';

$title     = '전체 푸시';
$ad_topbar = $ad;
$ad_menu   = 'push_broadcast';
include __DIR__ . '/include/admin_header.php';

$wp_ready = webpush_is_ready();
$sub_cnt  = webpush_subscription_total();
$readiness = webpush_readiness_rows();
?>

<div class="ad-page">
    <div class="ad-card">
        <h1 class="ad-title">전체 웹 푸시</h1>
        <p class="ad-p" style="margin-top:0;">
            알림을 켠 회원의 <strong>등록된 기기·브라우저</strong>로 동일한 내용을 보냅니다. 거래 메시지 알림과 같은 Web Push 채널을 사용합니다.
        </p>

        <?php if (!$wp_ready): ?>
            <div class="ad-alert ad-alert--error" style="margin-bottom:1rem;">
                웹 푸시가 준비되지 않았습니다. 아래 항목을 확인한 뒤 다시 시도하세요.
            </div>
            <ul class="ad-p" style="margin:0 0 1rem 1.25rem; padding:0;">
                <?php foreach ($readiness as $rw): ?>
                    <li>
                        <?php echo $rw['ok'] ? '✓' : '✗'; ?>
                        <?php echo htmlspecialchars($rw['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!$rw['ok'] && ($rw['hint'] ?? '') !== ''): ?>
                            — <span style="opacity:.9"><?php echo htmlspecialchars($rw['hint'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="ad-p">
                현재 저장된 구독: <strong><?php echo number_format($sub_cnt); ?></strong>건
                <?php if ($sub_cnt < 1): ?>
                    <span class="ad-badge ad-badge--bad">전송 대상 없음</span>
                <?php endif; ?>
            </p>

            <form class="ad-form"
                  method="post"
                  action="/proc/admin_push_broadcast_proc.php"
                  autocomplete="off"
                  onsubmit="return confirm('등록된 모든 구독(약 <?php echo (int) $sub_cnt; ?>건)으로 푸시를 보낼까요?');">
                <div class="ad-field">
                    <label for="pb_title">제목</label>
                    <input type="text" id="pb_title" name="pb_title" maxlength="120" required
                           placeholder="예: 공지 · 서비스 점검 안내">
                </div>
                <div class="ad-field">
                    <label for="pb_body">내용</label>
                    <textarea id="pb_body" name="pb_body" maxlength="500" required rows="4"
                              placeholder="알림에 표시할 짧은 문구"></textarea>
                </div>
                <div class="ad-field">
                    <label for="pb_url">클릭 시 이동 URL (선택)</label>
                    <input type="text" id="pb_url" name="pb_url" maxlength="500"
                           placeholder="비우면 메인(/)으로 열립니다. 예: /page/notice.php 또는 https://…">
                    <small style="display:block;margin-top:.35rem;opacity:.85;">상대 경로는 사이트 도메인 기준으로 변환됩니다.</small>
                </div>
                <div class="ad-actions">
                    <button type="submit" class="ad-btn" <?php echo $sub_cnt < 1 ? 'disabled' : ''; ?>>전체 전송</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
