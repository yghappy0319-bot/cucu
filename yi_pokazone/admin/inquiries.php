<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '1:1문의관리';
$ad_topbar = $ad;
$ad_menu   = 'inquiries';
include __DIR__ . '/include/admin_header.php';

$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$status_map = [
    'all' => '전체',
    '1'   => '접수',
    '2'   => '처리중',
    '3'   => '답변완료',
    '9'   => '숨김',
];

$cat_map = [
    'account'   => '계정/회원',
    'trade'     => '거래',
    'community' => '커뮤니티',
    'payment'   => '결제/환불',
    'report'    => '신고/사기',
    'etc'       => '기타',
];

$ready = db_table_exists('tb_inquiry');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '2' || $st === '3' || $st === '9') {
        $where[] = 'iq.iq_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(iq.iq_title LIKE '%{$e}%' OR iq.iq_content LIKE '%{$e}%' OR iq.iq_email LIKE '%{$e}%' OR iq.iq_name LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_inquiry iq WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT iq.*, m.mb_nick, m.mb_id
        FROM tb_inquiry iq
        LEFT JOIN tb_member m ON m.mb_idx = iq.mb_idx
        WHERE {$where_sql}
        ORDER BY iq.iq_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($st, $q, $page_no) {
    $base = ['st' => $st, 'q' => $q, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['st'] ?? '') === '' || ($base['st'] ?? '') === 'all') {
        unset($base['st']);
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_inquiry 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">1:1 문의</h1>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>상태</label>
                    <select name="st">
                        <?php foreach ($status_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건</p>

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>분류</th>
                        <th>제목</th>
                        <th>문의자</th>
                        <th>상태</th>
                        <th class="ad-nowrap">접수일</th>
                        <th>관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $ist = (int) $r['iq_status'];
                        $st_label = $status_map[(string) $ist] ?? $ist;
                        $del_confirm = '문의 #' . (int) $r['iq_idx'] . '을(를) 완전 삭제할까요?'
                            . '\n\n문의 내용·답변·첨부파일이 모두 삭제되며 복구할 수 없습니다.';
                        ?>
                        <tr>
                            <td><?php echo (int) $r['iq_idx']; ?></td>
                            <td><?php echo htmlspecialchars($cat_map[$r['iq_category']] ?? $r['iq_category'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell"><?php echo htmlspecialchars($r['iq_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($r['iq_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <div class="ad-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['iq_email'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td>
                                <?php
                                $badge = 'ad-badge--warn';
                                if ($ist === 3) {
                                    $badge = 'ad-badge--ok';
                                }
                                if ($ist === 9) {
                                    $badge = 'ad-badge--bad';
                                }
                                ?>
                                <span class="ad-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($st_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr($r['iq_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn ad-btn--sm" href="/admin/inquiry_view.php?idx=<?php echo (int) $r['iq_idx']; ?>">상세</a>
                                <form class="ad-form ad-form--inline" action="/proc/admin_inquiry_proc.php" method="post" style="display:inline;"
                                      onsubmit="return confirm('<?php echo htmlspecialchars($del_confirm, ENT_QUOTES, 'UTF-8'); ?>');">
                                    <input type="hidden" name="iq_idx" value="<?php echo (int) $r['iq_idx']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="ad-btn ad-btn--sm ad-btn--danger">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/inquiries.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/inquiries.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/inquiries.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
