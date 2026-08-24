<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_member_cash.php';

$title     = '캐시 지급내역';
$ad_topbar = $ad;
$ad_menu   = 'cash_logs';
include __DIR__ . '/include/admin_header.php';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$type_f  = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 30;
$offset  = ($page_no - 1) * $per;

$type_map = ['' => '전체 유형'] + member_cash_type_labels();
if ($type_f !== '' && !isset($type_map[$type_f])) {
    $type_f = '';
}

$ready = member_cash_log_table_ready() && db_table_exists('tb_member');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($type_f !== '') {
        $where[] = "l.cl_type = '" . db_escape($type_f) . "'";
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = 'l.mb_idx = ' . (int) $q;
        } else {
            $e = db_escape($q);
            $where[] = "(m.mb_id LIKE '%{$e}%' OR m.mb_nick LIKE '%{$e}%' OR l.cl_memo LIKE '%{$e}%' OR l.cl_type LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("
        SELECT COUNT(*) FROM tb_cash_log l
        LEFT JOIN tb_member m ON m.mb_idx = l.mb_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT l.*, m.mb_id, m.mb_nick
        FROM tb_cash_log l
        LEFT JOIN tb_member m ON m.mb_idx = l.mb_idx
        WHERE {$where_sql}
        ORDER BY l.cl_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($q, $type_f, $page_no) {
    $base = ['q' => $q, 'type' => $type_f, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    foreach (['q', 'type'] as $k) {
        if (($base[$k] ?? '') === '') {
            unset($base[$k]);
        }
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
            tb_cash_log 또는 tb_member 테이블이 없습니다.
            <code>sql/migrate_tb_cash_log.sql</code> 등을 확인해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">캐시 내역</h1>
            <p class="ad-p" style="margin-top:0;">
                회원 캐시 증감 기록입니다. 관리자 지급은 유형 <strong>관리</strong>로 남습니다.
                <a href="/admin/members.php">회원 목록에서 지급</a>
            </p>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_type">유형</label>
                    <select id="f_type" name="type">
                        <?php foreach ($type_map as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $type_f === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">회원번호 / 아이디·닉네임 / 메모·유형</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="검색">
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
                        <th>회원</th>
                        <th class="ad-num">변동</th>
                        <th class="ad-num">잔액</th>
                        <th>유형</th>
                        <th>메모</th>
                        <th class="ad-nowrap">일시</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="ad-muted">내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $chg = (int) $r['cl_change'];
                        $type_label = member_cash_type_label((string) ($r['cl_type'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo (int) $r['cl_idx']; ?></td>
                            <td>
                                <a href="/admin/members.php?q=<?php echo (int) $r['mb_idx']; ?>">#<?php echo (int) $r['mb_idx']; ?></a>
                                <?php if (!empty($r['mb_nick']) || !empty($r['mb_id'])): ?>
                                    <span class="ad-muted"><?php echo htmlspecialchars($r['mb_nick'] ?: $r['mb_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-num" style="color:<?php echo $chg >= 0 ? '#166534' : '#991b1b'; ?>">
                                <?php echo $chg >= 0 ? '+' : ''; ?><?php echo number_format($chg); ?>
                            </td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['cl_balance']); ?></td>
                            <td><?php echo htmlspecialchars($type_label, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars((string) ($r['cl_memo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) ($r['cl_memo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) $r['cl_created_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/cash_logs.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/cash_logs.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/cash_logs.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
