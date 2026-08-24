<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
if (!$me || (int)$me['mb_level'] < 9) {
    alert_goto('관리자만 접근할 수 있습니다.', '/page/notice.php');
}

$categories = [
    'general'     => '일반',
    'update'      => '업데이트',
    'event'       => '이벤트',
    'maintenance' => '점검',
];

$idx     = (int)($_GET['idx'] ?? 0);
$is_edit = $idx > 0;
$row     = null;

if ($is_edit) {
    $rs  = db_query("SELECT * FROM tb_notice WHERE no_idx = {$idx} AND no_status = 1 LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) alert_goto('존재하지 않는 공지입니다.', '/page/notice.php');
}

$def_cat = $is_edit ? $row['no_category'] : 'general';
$v = function($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit) return $default;
    return $row[$field] ?? $default;
};

$page  = 'notice';
$title = $is_edit ? '공지 수정' : '공지 등록';
$meta_noindex = true;
include __DIR__ . '/../include/header.php';
?>

<section class="community notice-write">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_edit ? '공지 수정' : '공지 등록'; ?></h1>
                <p class="board-desc">회원에게 전달할 공지사항을 작성하세요.</p>
            </div>
        </div>

        <form class="post-form" method="post" action="/proc/notice_write_proc.php" autocomplete="off">
            <?php if ($is_edit): ?>
                <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <input type="hidden" name="mode" value="edit">
            <?php else: ?>
                <input type="hidden" name="mode" value="insert">
            <?php endif; ?>

            <div class="field-row">
                <div class="field">
                    <label for="no_category">카테고리</label>
                    <select id="no_category" name="no_category" required>
                        <?php foreach ($categories as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $def_cat === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>상단 고정</label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_is_pinned" value="1"
                            <?php echo (int)$v('no_is_pinned') === 1 ? 'checked' : ''; ?>>
                        <span>목록 최상단에 고정</span>
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="no_title">제목</label>
                <input type="text" id="no_title" name="no_title" maxlength="200" required
                       placeholder="공지 제목을 입력해 주세요"
                       value="<?php echo htmlspecialchars($v('no_title')); ?>">
            </div>

            <div class="field">
                <label for="no_content">내용</label>
                <textarea id="no_content" name="no_content" rows="15" required
                          placeholder="공지 내용을 입력해 주세요"><?php echo htmlspecialchars($v('no_content')); ?></textarea>
            </div>

            <div class="form-actions">
                <a href="<?php echo $is_edit ? '/page/notice_view.php?idx='.$idx : '/page/notice.php'; ?>" class="btn btn-outline">취소</a>
                <button type="submit" class="btn btn-primary"><?php echo $is_edit ? '수정하기' : '등록하기'; ?></button>
            </div>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
