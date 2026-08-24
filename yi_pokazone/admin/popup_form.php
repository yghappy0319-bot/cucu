<?php
require_once __DIR__ . '/include/admin_init.php';
require_once dirname(__DIR__) . '/lib/_site_popup.php';

$title     = '레이어 팝업 작성·수정';
$ad_topbar = $ad;
$ad_menu   = 'popups';
include __DIR__ . '/include/admin_header.php';

$ready   = site_popup_table_ready();
$idx     = max(0, (int) ($_GET['idx'] ?? 0));
$is_edit = $idx > 0;
$row     = null;

if ($ready && $is_edit) {
    $rs  = db_query("SELECT * FROM tb_popup WHERE pu_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 팝업입니다.', '/admin/popups.php');
    }
}

$v = function ($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit || !$row) {
        return $default;
    }
    return $row[$field] ?? $default;
};

$def_target = (string) $v('pu_target', 'all');
$def_status = (string) (int) $v('pu_status', 1);
$content_edit = (string) $v('pu_content');
if ($content_edit !== '') {
    $content_edit = preg_replace('/<br\s*\/?>/i', "\n", $content_edit) ?? $content_edit;
    $content_edit = strip_tags($content_edit);
}
$start_val  = '';
$end_val    = '';
if ($is_edit && $row) {
    if (!empty($row['pu_start_at'])) {
        $start_val = date('Y-m-d\TH:i', strtotime((string) $row['pu_start_at']));
    }
    if (!empty($row['pu_end_at'])) {
        $end_val = date('Y-m-d\TH:i', strtotime((string) $row['pu_end_at']));
    }
}
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_popup 테이블이 없습니다. <code>sql/tb_popup.sql</code> 을 적용해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title"><?php echo $is_edit ? '팝업 수정' : '새 팝업'; ?></h1>
            <p class="ad-p" style="margin-top:0;">이미지 또는 텍스트로 레이어 팝업을 구성합니다. 둘 중 하나 이상 필요합니다.</p>

            <form class="ad-form" method="post" action="/proc/admin_popup_write_proc.php" enctype="multipart/form-data" autocomplete="off">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <?php else: ?>
                    <input type="hidden" name="mode" value="insert">
                <?php endif; ?>

                <div class="ad-field">
                    <label for="pu_title">제목</label>
                    <input type="text" id="pu_title" name="pu_title" maxlength="100" required
                           value="<?php echo htmlspecialchars((string) $v('pu_title'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="관리용 제목 (접근성에도 사용)">
                </div>

                <div class="ad-field">
                    <label for="pu_content">본문 <span class="ad-muted">(선택)</span></label>
                    <textarea id="pu_content" name="pu_content" rows="8"
                              placeholder="안내 문구"><?php echo htmlspecialchars($content_edit, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="ad-field">
                    <label for="pu_image_file">이미지 <span class="ad-muted">(선택, jpg/png/gif/webp · 5MB)</span></label>
                    <?php if ($is_edit && !empty($row['pu_image'])): ?>
                        <div style="margin-bottom:0.5rem;">
                            <img src="<?php echo htmlspecialchars(public_url((string) $row['pu_image']), ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" style="max-width:220px;border-radius:8px;border:1px solid #e5e7eb;">
                            <label style="display:block;margin-top:0.4rem;">
                                <input type="checkbox" name="pu_image_remove" value="1"> 이미지 삭제
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="pu_image_file" name="pu_image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                </div>

                <div class="ad-field">
                    <label for="pu_link_url">클릭 링크 <span class="ad-muted">(선택)</span></label>
                    <input type="url" id="pu_link_url" name="pu_link_url" maxlength="500"
                           value="<?php echo htmlspecialchars((string) $v('pu_link_url'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="https://...">
                </div>

                <div class="ad-field" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div>
                        <label for="pu_width">너비 (px)</label>
                        <input type="number" id="pu_width" name="pu_width" min="240" max="900" step="10"
                               value="<?php echo (int) $v('pu_width', 400); ?>">
                    </div>
                    <div>
                        <label for="pu_sort">정렬</label>
                        <input type="number" id="pu_sort" name="pu_sort" min="0" max="9999"
                               value="<?php echo (int) $v('pu_sort', 0); ?>">
                        <p class="ad-muted" style="margin:0.35rem 0 0;font-size:12px;">숫자가 작을수록 먼저 표시</p>
                    </div>
                </div>

                <div class="ad-field" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div>
                        <label for="pu_target">노출 대상</label>
                        <select id="pu_target" name="pu_target">
                            <option value="all"<?php echo $def_target === 'all' ? ' selected' : ''; ?>>전체 페이지</option>
                            <option value="home"<?php echo $def_target === 'home' ? ' selected' : ''; ?>>메인만</option>
                        </select>
                    </div>
                    <div>
                        <label for="pu_hide_days">다시 보지 않기 (일)</label>
                        <input type="number" id="pu_hide_days" name="pu_hide_days" min="1" max="30"
                               value="<?php echo max(1, (int) $v('pu_hide_days', 1)); ?>">
                    </div>
                </div>

                <div class="ad-field" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div>
                        <label for="pu_start_at">노출 시작 <span class="ad-muted">(비우면 즉시)</span></label>
                        <input type="datetime-local" id="pu_start_at" name="pu_start_at" value="<?php echo htmlspecialchars($start_val, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="pu_end_at">노출 종료 <span class="ad-muted">(비우면 무기한)</span></label>
                        <input type="datetime-local" id="pu_end_at" name="pu_end_at" value="<?php echo htmlspecialchars($end_val, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="ad-field">
                    <label for="pu_status">상태</label>
                    <select id="pu_status" name="pu_status">
                        <option value="1"<?php echo $def_status === '1' ? ' selected' : ''; ?>>노출</option>
                        <option value="9"<?php echo $def_status === '9' ? ' selected' : ''; ?>>숨김</option>
                    </select>
                </div>

                <div class="ad-actions">
                    <a class="ad-btn" href="/admin/popups.php">취소</a>
                    <button type="submit" class="ad-btn"><?php echo $is_edit ? '저장' : '등록'; ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
