<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '공지사항관리';
$ad_topbar = $ad;
$ad_menu   = 'notices';
include __DIR__ . '/include/admin_header.php';

$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$st_map = [
    'all' => '전체',
    '1'   => '노출',
    '9'   => '숨김',
];

$categories = [
    'general'     => '일반',
    'update'      => '업데이트',
    'event'       => '이벤트',
    'maintenance' => '점검',
];

$ready = db_table_exists('tb_notice');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '9') {
        $where[] = 'n.no_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(n.no_title LIKE '%{$e}%' OR n.no_content LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_notice n WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $ad_join   = notice_table_has_no_ad_idx() ? ' LEFT JOIN tb_admin ad ON ad.ad_idx = n.no_ad_idx' : '';
    $ad_select = notice_table_has_no_ad_idx() ? ', ad.ad_name, ad.ad_id' : '';

    $rs = db_query("
        SELECT n.*, m.mb_nick, m.mb_id{$ad_select}
        FROM tb_notice n
        LEFT JOIN tb_member m ON m.mb_idx = n.mb_idx
        {$ad_join}
        WHERE {$where_sql}
        ORDER BY n.no_is_pinned DESC, n.no_idx DESC
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
        <div class="ad-alert ad-alert--error">tb_notice 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">공지사항</h1>
                <a class="ad-btn" href="/admin/notice_form.php">새 공지</a>
            </div>
            <p class="ad-p" style="margin-top:0;">목록에서 수정·삭제(숨김)하거나, 사용자 화면 <a href="/page/notice_write.php" target="_blank" rel="noopener">회원 측 공지 작성</a>도 사용할 수 있습니다.</p>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>상태</label>
                    <select name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
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
                        <th>작성자</th>
                        <th class="ad-num">조회</th>
                        <th>상태</th>
                        <th class="ad-nowrap">작성일</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $ok = (int) $r['no_status'] === 1;
                        $pin = (int) $r['no_is_pinned'] === 1;
                        ?>
                        <tr>
                            <td><?php echo (int) $r['no_idx']; ?></td>
                            <td><?php echo htmlspecialchars($categories[$r['no_category']] ?? $r['no_category'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell">
                                <?php if ($pin): ?><span class="ad-badge ad-badge--info">고정</span> <?php endif; ?>
                                <a href="/page/notice_view.php?idx=<?php echo (int) $r['no_idx']; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['no_title'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars(notice_row_author($r), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo number_format((int) $r['no_views']); ?></td>
                            <td><span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '숨김'; ?></span></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr($r['no_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn" href="/admin/notice_form.php?idx=<?php echo (int) $r['no_idx']; ?>">수정</a>
                                <?php if ($ok): ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_notice_delete_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 공지를 삭제(숨김) 처리할까요?');">
                                        <input type="hidden" name="no_idx" value="<?php echo (int) $r['no_idx']; ?>">
                                        <button type="submit" class="ad-btn">삭제</button>
                                    </form>
                                <?php else: ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_notice_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 공지를 다시 노출할까요?');">
                                        <input type="hidden" name="no_idx" value="<?php echo (int) $r['no_idx']; ?>">
                                        <input type="hidden" name="action" value="show">
                                        <button type="submit" class="ad-btn">다시 노출</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/notices.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/notices.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/notices.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
