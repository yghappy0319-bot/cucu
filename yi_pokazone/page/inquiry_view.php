<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
if (!$me) alert_goto('로그인이 필요합니다.', '/login.php');

$idx = (int)($_GET['idx'] ?? 0);
if ($idx <= 0) alert_goto('잘못된 접근입니다.', '/page/inquiry.php');

$rs  = db_query("
    SELECT iq.*, m.mb_nick
    FROM tb_inquiry iq
    LEFT JOIN tb_member m ON m.mb_idx = iq.mb_idx
    WHERE iq.iq_idx = {$idx} AND iq.iq_status <> 9
    LIMIT 1
");
$row = db_assoc($rs);
if (!$row) alert_goto('존재하지 않거나 삭제된 문의입니다.', '/page/inquiry.php');

$is_admin = (int)$me['mb_level'] >= 9;
$is_owner = (int)$row['mb_idx'] === (int)$me['mb_idx'];
if (!$is_admin && !$is_owner) alert_goto('본인의 문의만 열람할 수 있습니다.', '/page/inquiry.php');

$categories = [
    'account'   => '계정/회원',
    'trade'     => '거래',
    'community' => '커뮤니티',
    'payment'   => '결제/환불',
    'report'    => '신고/사기',
    'etc'       => '기타',
];
$status_map = [
    1 => ['class' => 'status-new',  'label' => '접수'],
    2 => ['class' => 'status-ing',  'label' => '처리중'],
    3 => ['class' => 'status-done', 'label' => '답변완료'],
];
$st = $status_map[(int)$row['iq_status']] ?? $status_map[1];
$cat_label = $categories[$row['iq_category']] ?? '기타';

$files = [];
if (db_table_exists('tb_inquiry_file')) {
    $rs_f = db_query("SELECT * FROM tb_inquiry_file WHERE iq_idx = {$idx} ORDER BY if_order ASC, if_idx ASC");
    while ($f = db_assoc($rs_f)) {
        $files[] = $f;
    }
}

$page  = 'inquiry';
$title = $row['iq_title'];
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '1:1 문의', 'url' => '/page/inquiry.php'],
    ['name' => $row['iq_title'], 'url' => '/page/inquiry_view.php?idx=' . $idx],
];
include __DIR__ . '/../include/header.php';
?>

<section class="community inquiry-view">
    <div class="container">
        <div class="inquiry-view-toolbar">
            <a href="/page/inquiry.php" class="inquiry-view-back">← 1:1 문의 목록</a>
            <span class="inquiry-view-ref" title="문의 번호">접수 <?php echo '#' . (int) $row['iq_idx']; ?></span>
        </div>

        <article class="post inquiry-post">
            <header class="post-head">
                <div class="inquiry-post-headline">
                    <div class="post-cat inquiry-post-cats">
                        <span class="badge-cat inquiry-cat-pill"><?php echo htmlspecialchars($cat_label); ?></span>
                        <span class="inquiry-status <?php echo $st['class']; ?>"><?php echo htmlspecialchars($st['label']); ?></span>
                    </div>
                    <h1 class="post-title"><?php echo htmlspecialchars($row['iq_title']); ?></h1>
                </div>
                <dl class="inquiry-meta-strip">
                    <div class="inquiry-meta-item">
                        <dt>작성자</dt>
                        <dd>
                            <?php echo htmlspecialchars($row['iq_name']); ?>
                            <?php if (!empty($row['mb_nick'])): ?>
                                <span class="inquiry-meta-account">· 회원 <?php echo htmlspecialchars($row['mb_nick']); ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="inquiry-meta-item">
                        <dt>답변 받을 이메일</dt>
                        <dd><a href="mailto:<?php echo htmlspecialchars($row['iq_email']); ?>"><?php echo htmlspecialchars($row['iq_email']); ?></a></dd>
                    </div>
                    <div class="inquiry-meta-item">
                        <dt>접수일시</dt>
                        <dd><time datetime="<?php echo htmlspecialchars($row['iq_created_at']); ?>"><?php echo date('Y.m.d H:i', strtotime($row['iq_created_at'])); ?></time></dd>
                    </div>
                </dl>
            </header>

            <div class="post-body inquiry-post-body">
                <?php echo nl2br(htmlspecialchars($row['iq_content'])); ?>
            </div>

            <?php if (!empty($files)): ?>
                <div class="inquiry-attachments">
                    <h2 class="inquiry-attachments-title">첨부파일 <span class="inquiry-attachments-count"><?php echo count($files); ?></span></h2>
                    <ul class="inquiry-attachment-list">
                        <?php foreach ($files as $file): ?>
                            <li>
                                <a class="inquiry-attachment-item" href="<?php echo htmlspecialchars(public_url($file['if_path'])); ?>"
                                   target="_blank" rel="noopener">
                                    <img src="<?php echo htmlspecialchars(public_url($file['if_path'])); ?>"
                                         alt="<?php echo htmlspecialchars($file['if_orig_name'] ?: '첨부 이미지'); ?>"
                                         loading="lazy">
                                    <span class="inquiry-attachment-name"><?php echo htmlspecialchars($file['if_orig_name'] ?: basename($file['if_path'])); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </article>

        <?php if (!empty($row['iq_answer'])): ?>
            <article class="post inquiry-answer-card">
                <header class="inquiry-answer-head">
                    <span class="inquiry-answer-icon" aria-hidden="true">✓</span>
                    <div>
                        <h2 class="inquiry-answer-title">관리자 답변</h2>
                        <?php if (!empty($row['iq_answered_at'])): ?>
                            <p class="inquiry-answer-when">
                                <time datetime="<?php echo htmlspecialchars($row['iq_answered_at']); ?>">
                                    <?php echo date('Y.m.d H:i', strtotime($row['iq_answered_at'])); ?>
                                </time>
                            </p>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="post-body inquiry-answer-body">
                    <?php echo nl2br(htmlspecialchars($row['iq_answer'])); ?>
                </div>
            </article>
        <?php elseif (!$is_admin): ?>
            <div class="inquiry-waiting inquiry-waiting-card">
                <span class="inquiry-waiting-icon" aria-hidden="true">⏳</span>
                <div class="inquiry-waiting-copy">
                    <p class="inquiry-waiting-lead"><strong>답변을 준비하고 있어요.</strong></p>
                    <p class="inquiry-waiting-note">영업일 기준 <strong>24시간 이내</strong>에 답변 드립니다. 긴급 신고는 카테고리 「신고/사기」로 접수해 주세요.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <form class="post-form inquiry-admin-form" method="post" action="/proc/inquiry_proc.php">
                <input type="hidden" name="mode" value="answer">
                <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <div class="inquiry-admin-form-head">
                    <h2 class="inquiry-admin-form-title">관리자 답변</h2>
                    <p class="inquiry-admin-form-hint">저장 시 회원에게 표시되며, 상태를 「답변완료」로 바꾸면 처리가 마무리된 것으로 보입니다.</p>
                </div>
                <div class="field-row inquiry-admin-field-row">
                    <div class="field">
                        <label for="iq_status">처리 상태</label>
                        <select id="iq_status" name="iq_status">
                            <option value="1" <?php echo (int)$row['iq_status'] === 1 ? 'selected' : ''; ?>>접수</option>
                            <option value="2" <?php echo (int)$row['iq_status'] === 2 ? 'selected' : ''; ?>>처리중</option>
                            <option value="3" <?php echo (int)$row['iq_status'] === 3 ? 'selected' : ''; ?>>답변완료</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="iq_answer">답변 내용</label>
                    <textarea id="iq_answer" name="iq_answer" rows="9" required class="inquiry-admin-textarea"
                              placeholder="회원에게 전달할 내용을 입력해 주세요."><?php echo htmlspecialchars($row['iq_answer'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions inquiry-admin-actions">
                    <button type="submit" class="btn btn-primary">답변 저장</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="inquiry-view-footer">
            <a href="/page/inquiry.php" class="btn btn-outline">목록으로</a>
            <?php if ($is_admin): ?>
                <a href="/admin/inquiry_view.php?idx=<?php echo (int) $idx; ?>" class="btn btn-ghost btn-sm">관리자 화면</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
