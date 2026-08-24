<?php
require_once __DIR__ . '/include/admin_init.php';

$idx = (int) ($_GET['idx'] ?? 0);
if ($idx < 1) {
    header('Location: /admin/inquiries.php');
    exit;
}

if (!db_table_exists('tb_inquiry')) {
    header('Location: /admin/inquiries.php');
    exit;
}

$rs  = db_query("
    SELECT iq.*, m.mb_nick, m.mb_id
    FROM tb_inquiry iq
    LEFT JOIN tb_member m ON m.mb_idx = iq.mb_idx
    WHERE iq.iq_idx = {$idx}
    LIMIT 1
");
$row = db_assoc($rs);
if (!$row) {
    header('Location: /admin/inquiries.php');
    exit;
}

$cat_map = [
    'account'   => '계정/회원',
    'trade'     => '거래',
    'community' => '커뮤니티',
    'payment'   => '결제/환불',
    'report'    => '신고/사기',
    'etc'       => '기타',
];

$files = [];
if (db_table_exists('tb_inquiry_file')) {
    $rs_f = db_query("SELECT * FROM tb_inquiry_file WHERE iq_idx = {$idx} ORDER BY if_order ASC, if_idx ASC");
    while ($f = db_assoc($rs_f)) {
        $files[] = $f;
    }
}

$title     = '문의 상세';
$ad_topbar = $ad;
$ad_menu   = 'inquiries';
include __DIR__ . '/include/admin_header.php';

$ist = (int) $row['iq_status'];
?>

<div class="ad-page">
    <div class="ad-card" style="margin-bottom:1.25rem;">
        <div class="ad-actions" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <a href="/admin/inquiries.php" class="ad-btn ad-btn--ghost">← 목록</a>
            <form action="/proc/admin_inquiry_proc.php" method="post" style="margin:0;"
                  onsubmit="return confirm('이 문의를 완전 삭제할까요?\n\n문의 내용·답변·첨부파일이 모두 삭제되며 복구할 수 없습니다.');">
                <input type="hidden" name="iq_idx" value="<?php echo (int) $row['iq_idx']; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="ad-btn ad-btn--danger">완전 삭제</button>
            </form>
        </div>
        <h1 class="ad-title"><?php echo htmlspecialchars($row['iq_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="ad-lead">
            #<?php echo (int) $row['iq_idx']; ?> ·
            <?php echo htmlspecialchars($cat_map[$row['iq_category']] ?? $row['iq_category'], ENT_QUOTES, 'UTF-8'); ?> ·
            상태 <?php echo $ist === 9 ? '숨김' : $ist; ?>
        </p>
        <?php if ($ist === 9): ?>
            <div class="ad-alert ad-alert--error">숨김 처리된 문의입니다. 답변·상태 변경은 할 수 없습니다.</div>
        <?php endif; ?>
        <p class="ad-p">
            <strong><?php echo htmlspecialchars($row['iq_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
            &lt;<?php echo htmlspecialchars($row['iq_email'], ENT_QUOTES, 'UTF-8'); ?>&gt;
            <?php if (!empty($row['mb_idx'])): ?>
                · 회원 #<?php echo (int) $row['mb_idx']; ?>
                <?php echo htmlspecialchars($row['mb_nick'] ?: $row['mb_id'] ?: '', ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </p>
        <p class="ad-p" style="white-space:pre-wrap;"><?php echo htmlspecialchars($row['iq_content'], ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if (!empty($files)): ?>
            <div class="ad-field" style="margin-top:1rem;">
                <label>첨부파일 (<?php echo count($files); ?>)</label>
                <ul class="ad-list" style="display:flex;flex-wrap:wrap;gap:12px;list-style:none;padding:0;margin:0;">
                    <?php foreach ($files as $file): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(public_url($file['if_path']), ENT_QUOTES, 'UTF-8'); ?>"
                               target="_blank" rel="noopener" style="display:block;max-width:140px;text-align:center;font-size:12px;">
                                <img src="<?php echo htmlspecialchars(public_url($file['if_path']), ENT_QUOTES, 'UTF-8'); ?>"
                                     alt=""
                                     style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
                                <span style="display:block;margin-top:6px;word-break:break-all;">
                                    <?php echo htmlspecialchars($file['if_orig_name'] ?: basename($file['if_path']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p class="ad-muted" style="font-size:13px;">접수 <?php echo htmlspecialchars($row['iq_created_at'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($row['iq_answered_at'])): ?>
                · 답변 <?php echo htmlspecialchars($row['iq_answered_at'], ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </p>

        <?php if ($ist !== 9): ?>
        <div class="ad-split ad-split--2" style="margin-top:1rem;">
            <form class="ad-form" action="/proc/admin_inquiry_proc.php" method="post">
                <input type="hidden" name="iq_idx" value="<?php echo (int) $row['iq_idx']; ?>">
                <input type="hidden" name="action" value="status">
                <div class="ad-field">
                    <label for="iq_status">진행 상태</label>
                    <select id="iq_status" name="iq_status">
                        <option value="1"<?php echo $ist === 1 ? ' selected' : ''; ?>>접수</option>
                        <option value="2"<?php echo $ist === 2 ? ' selected' : ''; ?>>처리중</option>
                        <option value="3"<?php echo $ist === 3 ? ' selected' : ''; ?>>답변완료</option>
                    </select>
                </div>
                <button type="submit" class="ad-btn">상태 저장</button>
            </form>

            <form class="ad-form" action="/proc/admin_inquiry_proc.php" method="post" onsubmit="return confirm('이 문의를 숨김 처리할까요?');">
                <input type="hidden" name="iq_idx" value="<?php echo (int) $row['iq_idx']; ?>">
                <input type="hidden" name="action" value="hide">
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">문의 숨김</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="ad-card">
        <h2 class="ad-title" style="font-size:1.1rem;">답변</h2>
        <?php if (!empty($row['iq_answer'])): ?>
            <p class="ad-p" style="white-space:pre-wrap;background:#f8fafc;padding:1rem;border-radius:8px;"><?php echo htmlspecialchars($row['iq_answer'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($ist !== 9): ?>
        <form class="ad-form" action="/proc/admin_inquiry_answer_proc.php" method="post">
            <input type="hidden" name="iq_idx" value="<?php echo (int) $row['iq_idx']; ?>">
            <div class="ad-field">
                <label for="iq_answer">답변 내용 (저장 시 답변완료로 변경)</label>
                <textarea id="iq_answer" name="iq_answer" required maxlength="65535" placeholder="회원에게 전달할 답변을 입력하세요."><?php echo htmlspecialchars((string) $row['iq_answer'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="ad-btn">답변 저장</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
