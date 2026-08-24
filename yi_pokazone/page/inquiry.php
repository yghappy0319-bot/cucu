<?php
require_once __DIR__ . '/../lib/_function.php';

$me = login_member();
if (!$me) alert_goto('1:1 문의는 로그인 후 이용하실 수 있습니다.', '/login.php?return=' . urlencode('/page/inquiry.php'));

$is_admin = (int)$me['mb_level'] >= 9;

$categories = [
    'account'   => '계정/회원',
    'trade'     => '거래',
    'community' => '커뮤니티',
    'payment'   => '결제/환불',
    'report'    => '신고/사기',
    'etc'       => '기타',
];

$pref_cat = trim((string) ($_GET['cat'] ?? ''));
if (!isset($categories[$pref_cat])) {
    $pref_cat = '';
}
$report_mb_idx = (int) ($_GET['report_mb_idx'] ?? 0);
$report_nick   = trim((string) ($_GET['report_nick'] ?? ''));
if (mb_strlen($report_nick) > 30) {
    $report_nick = mb_substr($report_nick, 0, 30);
}
$pref_title   = '';
$pref_content = '';
if ($pref_cat === 'report' && ($report_mb_idx > 0 || $report_nick !== '')) {
    $who = $report_nick !== '' ? $report_nick : ('회원#' . $report_mb_idx);
    $pref_title = '판매자 상점 신고: ' . $who;
    $pref_content = "신고 대상\n"
        . '- 닉네임: ' . $who . "\n"
        . ($report_mb_idx > 0 ? '- 회원번호: #' . $report_mb_idx . "\n" : '')
        . ($report_mb_idx > 0 ? '- 상점: /page/member_profile.php?mb_idx=' . $report_mb_idx . "\n" : '')
        . "\n신고 사유\n"
        . "(여기에 사유를 구체적으로 적어 주세요. 거래 내역·대화·증빙이 있으면 함께 첨부해 주세요.)\n"
        . "\n※ 허위·악의적 신고로 확인될 경우 신고자도 서비스 이용이 제한될 수 있습니다.\n";
}

$status_map = [
    1 => ['class' => 'status-new',    'label' => '접수'],
    2 => ['class' => 'status-ing',    'label' => '처리중'],
    3 => ['class' => 'status-done',   'label' => '답변완료'],
];

// 내 문의 / 관리자일 경우 전체
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page_num - 1) * $per_page;

/* JOIN 시 tb_member 에도 mb_idx 가 있으므로 반드시 iq. 접두사 (미지정 시 모호 컬럼으로 쿼리 실패 가능) */
$where = $is_admin
    ? "iq.iq_status <> 9"
    : "iq.iq_status <> 9 AND iq.mb_idx = " . (int)$me['mb_idx'];

$total = (int) db_result("SELECT COUNT(*) FROM tb_inquiry iq WHERE {$where}");
$total_pages = max(1, (int)ceil($total / $per_page));

$rs = db_query("
    SELECT iq.*, m.mb_nick
    FROM tb_inquiry iq
    LEFT JOIN tb_member m ON m.mb_idx = iq.mb_idx
    WHERE {$where}
    ORDER BY iq.iq_created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$rows = [];
while ($r = db_assoc($rs)) $rows[] = $r;

$page  = 'inquiry';
$title = '1:1 문의';
$meta_description = '궁금한 점, 불편한 점, 신고·건의사항을 Pokazone에 직접 문의하세요. 영업일 기준 24시간 이내 답변드립니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '1:1 문의', 'url' => '/page/inquiry.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="community">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">1:1 문의</h1>
                <p class="board-desc">서비스 이용 중 궁금한 점을 남겨주세요. 빠르게 도와드리겠습니다.</p>
            </div>
        </div>

        <div class="inquiry-layout">
            <!-- 문의 작성 폼 -->
            <form class="post-form inquiry-form" method="post" action="/proc/inquiry_proc.php" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="mode" value="insert">
                <h2 class="policy-h2">새 문의 작성</h2>

                <div class="inquiry-report-notice" id="inquiry-report-notice" role="note"<?php echo $pref_cat === 'report' ? '' : ' hidden'; ?>>
                    <strong>신고 안내</strong>
                    <p>사실과 다른 내용이나 악의적인 목적의 신고로 확인될 경우, <strong>신고자도 서비스 이용이 제한</strong>될 수 있습니다. 사실에 근거해 신고해 주세요.</p>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="iq_category">문의 유형</label>
                        <select id="iq_category" name="iq_category" required>
                            <?php foreach ($categories as $k => $label): ?>
                                <option value="<?php echo $k; ?>"<?php echo $pref_cat === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="iq_email">답변 받을 이메일</label>
                        <input type="email" id="iq_email" name="iq_email" required maxlength="100"
                               value="<?php echo htmlspecialchars($me['mb_email'] ?? ''); ?>"
                               placeholder="답변을 받을 이메일 주소">
                    </div>
                </div>

                <div class="field">
                    <label for="iq_name">이름/닉네임</label>
                    <input type="text" id="iq_name" name="iq_name" required maxlength="30"
                           value="<?php echo htmlspecialchars($me['mb_nick'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label for="iq_title">제목</label>
                    <input type="text" id="iq_title" name="iq_title" required maxlength="200"
                           value="<?php echo htmlspecialchars($pref_title, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="문의 제목을 입력해 주세요">
                </div>

                <div class="field">
                    <label for="iq_content">내용</label>
                    <textarea id="iq_content" name="iq_content" rows="10" required
                              placeholder="문의 내용을 구체적으로 적어주세요. 관련 URL·스크린샷이 있다면 함께 설명해 주세요."><?php echo htmlspecialchars($pref_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="field">
                    <label for="iq_files">
                        첨부파일
                        <span class="optional">(선택, 최대 5개 · 장당 5MB · jpg/png/gif/webp)</span>
                    </label>
                    <div class="image-uploader">
                        <label class="image-uploader-btn">
                            <input type="file" name="iq_files[]" id="iq_files"
                                   accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                            <span>📎 파일 선택</span>
                        </label>
                        <span class="image-counter" id="iq_file_counter">0 / 5</span>
                    </div>
                    <ul class="inquiry-file-preview" id="iq_file_preview" hidden></ul>
                    <p class="help">스크린샷·증빙 자료가 있으면 함께 첨부해 주세요.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">문의 보내기</button>
                </div>
            </form>

            <!-- 안내 사이드 -->
            <aside class="inquiry-aside">
                <h3>답변 안내</h3>
                <ul class="policy-list">
                    <li>영업일 기준 24시간 이내 답변드립니다.</li>
                    <li>긴급한 사기 신고는 "신고/사기" 카테고리로 접수해 주세요.</li>
                    <li>허위·악의적 신고로 확인되면 신고자도 서비스 이용이 제한될 수 있습니다.</li>
                    <li>답변은 본 페이지와 등록하신 이메일로 발송됩니다.</li>
                </ul>
                <div class="inquiry-quick">
                    <a href="/page/faq.php" class="btn btn-outline btn-sm">FAQ 먼저 보기</a>
                    <a href="/page/guide.php" class="btn btn-ghost btn-sm">거래 가이드</a>
                </div>
            </aside>
        </div>

        <!-- 내 문의 내역 / 관리자는 전체 -->
        <h2 class="policy-h2" style="margin-top:48px">
            <?php echo $is_admin ? '전체 문의 내역 (관리자)' : '내 문의 내역'; ?>
        </h2>

        <?php if (empty($rows)): ?>
            <div class="board-empty">
                <p>접수된 문의가 없습니다.</p>
            </div>
        <?php else: ?>
            <ul class="post-list inquiry-list">
                <?php foreach ($rows as $row):
                    $st = $status_map[(int)$row['iq_status']] ?? $status_map[1];
                    $cat_label = $categories[$row['iq_category']] ?? '기타';
                ?>
                    <li class="post-item">
                        <span class="post-cat badge-cat"><?php echo htmlspecialchars($cat_label); ?></span>
                        <span class="inquiry-status <?php echo $st['class']; ?>"><?php echo $st['label']; ?></span>
                        <a class="post-title" href="/page/inquiry_view.php?idx=<?php echo (int)$row['iq_idx']; ?>">
                            <?php echo htmlspecialchars($row['iq_title']); ?>
                        </a>
                        <span class="post-meta">
                            <?php if ($is_admin): ?>
                                <span class="post-author"><?php echo htmlspecialchars($row['mb_nick'] ?? '-'); ?></span>
                            <?php endif; ?>
                            <time datetime="<?php echo htmlspecialchars($row['iq_created_at']); ?>">
                                <?php echo date('Y.m.d', strtotime($row['iq_created_at'])); ?>
                            </time>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="페이지 네비게이션">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?page=<?php echo $p; ?>" class="<?php echo $p === $page_num ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    'use strict';
    var input = document.getElementById('iq_files');
    var counter = document.getElementById('iq_file_counter');
    var preview = document.getElementById('iq_file_preview');
    if (!input || !counter || !preview) return;

    var maxFiles = 5;

    function updatePreview() {
        var files = input.files ? Array.prototype.slice.call(input.files) : [];
        preview.innerHTML = '';
        if (files.length === 0) {
            preview.hidden = true;
            counter.textContent = '0 / ' + maxFiles;
            return;
        }
        preview.hidden = false;
        counter.textContent = files.length + ' / ' + maxFiles;
        files.forEach(function (file) {
            var li = document.createElement('li');
            li.textContent = file.name;
            preview.appendChild(li);
        });
    }

    input.addEventListener('change', function () {
        if (input.files && input.files.length > maxFiles) {
            alert('첨부파일은 최대 ' + maxFiles + '개까지 선택할 수 있습니다.');
            input.value = '';
        }
        updatePreview();
    });
})();

(function () {
    var cat = document.getElementById('iq_category');
    var notice = document.getElementById('inquiry-report-notice');
    if (!cat || !notice) return;
    function sync() {
        if (cat.value === 'report') {
            notice.hidden = false;
        } else {
            notice.hidden = true;
        }
    }
    cat.addEventListener('change', sync);
    sync();
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
