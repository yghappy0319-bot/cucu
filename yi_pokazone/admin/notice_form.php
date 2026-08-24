<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '공지 작성·수정';
$ad_topbar = $ad;
$ad_menu   = 'notices';
include __DIR__ . '/include/admin_header.php';

$categories = [
    'general'     => '일반',
    'update'      => '업데이트',
    'event'       => '이벤트',
    'maintenance' => '점검',
];

$ready = db_table_exists('tb_notice');
$idx   = max(0, (int) ($_GET['idx'] ?? 0));
$is_edit = $idx > 0;
$row   = null;

$author_ok = notice_table_has_no_ad_idx() || notice_author_mb_idx_for_admin() > 0;

if ($ready && $is_edit) {
    $rs  = db_query("SELECT * FROM tb_notice WHERE no_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 공지입니다.', '/admin/notices.php');
    }
}

$def_cat = $is_edit && $row ? (string) $row['no_category'] : 'general';
$v       = function ($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit || !$row) {
        return $default;
    }
    return $row[$field] ?? $default;
};

$back_href = '/admin/notices.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_notice 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title"><?php echo $is_edit ? '공지 수정' : '새 공지'; ?></h1>
            <p class="ad-p" style="margin-top:0;">
                <?php if (!$author_ok && !$is_edit): ?>
                    <span class="ad-badge ad-badge--bad">DB 마이그레이션 필요</span>
                <?php endif; ?>
                작성 내용은 <a href="/page/notice.php" target="_blank" rel="noopener">사용자 화면 공지사항</a>에 반영됩니다.
            </p>
            <?php if (!$author_ok && !$is_edit): ?>
                <div class="ad-alert ad-alert--error" style="margin:1rem 0;">
                    백오피스만으로 등록하려면 DB에 <code>sql/migrate_tb_notice_admin_author.sql</code>을 적용해 주세요.
                    적용 전에는 회원(관리자 id 동일 또는 등급 9)이 있어야 신규 등록이 됩니다.
                </div>
            <?php endif; ?>

            <form class="ad-form" method="post" action="/proc/admin_notice_write_proc.php" autocomplete="off">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <?php else: ?>
                    <input type="hidden" name="mode" value="insert">
                <?php endif; ?>

                <div class="ad-field">
                    <label for="no_category">카테고리</label>
                    <select id="no_category" name="no_category" required>
                        <?php foreach ($categories as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $def_cat === $k ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ad-field">
                    <label>
                        <input type="checkbox" name="no_is_pinned" value="1"<?php echo (int) $v('no_is_pinned') === 1 ? ' checked' : ''; ?>>
                        상단 고정(목록 최우선)
                    </label>
                </div>

                <div class="ad-field">
                    <label for="no_title">제목</label>
                    <input type="text" id="no_title" name="no_title" maxlength="200" required
                           value="<?php echo htmlspecialchars((string) $v('no_title'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="공지 제목">
                </div>

                <div class="ad-field">
                    <label for="no_content">내용</label>
                    <textarea id="no_content" name="no_content" rows="16" required
                              placeholder="공지 본문"><?php echo htmlspecialchars((string) $v('no_content'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="ad-actions">
                    <a class="ad-btn" href="<?php echo htmlspecialchars($back_href, ENT_QUOTES, 'UTF-8'); ?>">취소</a>
                    <button type="submit" class="ad-btn"<?php echo (!$author_ok && !$is_edit) ? ' disabled' : ''; ?>>
                        <?php echo $is_edit ? '저장' : '등록'; ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
