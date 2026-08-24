<?php
require_once __DIR__ . '/include/admin_init.php';
require_once dirname(__DIR__) . '/lib/_site_popup.php';

$title     = '레이어 팝업';
$ad_topbar = $ad;
$ad_menu   = 'popups';
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
$target_labels = [
    'all'  => '전체 페이지',
    'home' => '메인만',
];

$ready = site_popup_table_ready();
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '9') {
        $where[] = 'pu_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(pu_title LIKE '%{$e}%' OR pu_content LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_popup WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT *
        FROM tb_popup
        WHERE {$where_sql}
        ORDER BY pu_sort ASC, pu_idx DESC
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
        <div class="ad-alert ad-alert--error">
            tb_popup 테이블이 없습니다. <code>sql/tb_popup.sql</code> 을 DB에 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">레이어 팝업</h1>
                <a class="ad-btn" href="/admin/popup_form.php">새 팝업</a>
            </div>
            <p class="ad-p" style="margin-top:0;">사이트에 레이어 팝업을 등록합니다. 노출 기간·대상 페이지·「다시 보지 않기」를 설정할 수 있습니다.</p>

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
                        <th>제목</th>
                        <th>대상</th>
                        <th class="ad-num">정렬</th>
                        <th>기간</th>
                        <th>상태</th>
                        <th class="ad-nowrap">등록일</th>
                        <th class="ad-nowrap">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="ad-muted">등록된 팝업이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $ok = (int) $r['pu_status'] === 1;
                        $period = '상시';
                        $s = (string) ($r['pu_start_at'] ?? '');
                        $e = (string) ($r['pu_end_at'] ?? '');
                        if ($s !== '' || $e !== '') {
                            $period = ($s !== '' ? substr($s, 0, 16) : '즉시')
                                . ' ~ '
                                . ($e !== '' ? substr($e, 0, 16) : '무기한');
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $r['pu_idx']; ?></td>
                            <td class="ad-title-cell">
                                <?php echo htmlspecialchars((string) $r['pu_title'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($r['pu_image'])): ?>
                                    <span class="ad-badge ad-badge--info">이미지</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($target_labels[$r['pu_target']] ?? (string) $r['pu_target'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo (int) $r['pu_sort']; ?></td>
                            <td class="ad-muted" style="font-size:12px;"><?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '숨김'; ?></span></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) $r['pu_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <a class="ad-btn" href="/admin/popup_form.php?idx=<?php echo (int) $r['pu_idx']; ?>">수정</a>
                                <?php if ($ok): ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_popup_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 팝업을 숨김 처리할까요?');">
                                        <input type="hidden" name="pu_idx" value="<?php echo (int) $r['pu_idx']; ?>">
                                        <input type="hidden" name="action" value="hide">
                                        <button type="submit" class="ad-btn">숨김</button>
                                    </form>
                                <?php else: ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_popup_proc.php" method="post" style="display:inline;"
                                          onsubmit="return confirm('이 팝업을 다시 노출할까요?');">
                                        <input type="hidden" name="pu_idx" value="<?php echo (int) $r['pu_idx']; ?>">
                                        <input type="hidden" name="action" value="show">
                                        <button type="submit" class="ad-btn">노출</button>
                                    </form>
                                <?php endif; ?>
                                <form class="ad-form ad-form--inline" action="/proc/admin_popup_proc.php" method="post" style="display:inline;"
                                      onsubmit="return confirm('이 팝업을 완전히 삭제할까요? 복구할 수 없습니다.');">
                                    <input type="hidden" name="pu_idx" value="<?php echo (int) $r['pu_idx']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="ad-btn">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pager" style="margin-top:1rem;">
                    <?php for ($i = 1; $i <= $total_page; $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <strong><?php echo $i; ?></strong>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
