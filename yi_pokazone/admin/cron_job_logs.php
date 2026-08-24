<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_cron_job_log.php';

$title     = '크론·웹훅 실행 로그';
$ad_topbar = $ad;
$ad_menu   = 'cron_job_logs';

$job     = isset($_GET['job']) ? trim((string) $_GET['job']) : '';
$ok_f    = isset($_GET['ok']) ? trim((string) $_GET['ok']) : '';
$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 30;
$offset  = ($page_no - 1) * $per;

$job_map = ['' => '전체 작업'] + cron_job_log_job_labels();
if ($job !== '' && !isset($job_map[$job])) {
    $job = '';
}
if ($ok_f !== '' && !in_array($ok_f, ['1', '0'], true)) {
    $ok_f = '';
}

$ready = cron_job_log_table_ready();
$rows  = [];
$total = 0;
$total_page = 1;
$summaries = [];

if ($ready) {
    foreach (cron_job_log_job_labels() as $job_key => $job_label) {
        $summaries[$job_key] = cron_job_log_summary($job_key, 24);
        $summaries[$job_key]['label'] = $job_label;
    }

    $where = ['1=1'];
    if ($job !== '') {
        $where[] = "l.cjl_job = '" . db_escape($job) . "'";
    }
    if ($ok_f === '1') {
        $where[] = 'l.cjl_ok = 1';
    } elseif ($ok_f === '0') {
        $where[] = 'l.cjl_ok = 0';
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $n = (int) $q;
            $where[] = "(l.cjl_ref_id = {$n} OR l.cjl_idx = {$n})";
        } else {
            $e = db_escape($q);
            $where[] = "(l.cjl_message LIKE '%{$e}%' OR l.cjl_detail LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total = (int) db_result("SELECT COUNT(*) FROM tb_cron_job_log l WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT l.*
        FROM tb_cron_job_log l
        WHERE {$where_sql}
        ORDER BY l.cjl_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($job, $ok_f, $q, $page_no) {
    $base = ['job' => $job, 'ok' => $ok_f, 'q' => $q, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    foreach (['job', 'ok', 'q'] as $k) {
        if (($base[$k] ?? '') === '') {
            unset($base[$k]);
        }
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            <code>tb_cron_job_log</code> 테이블이 없습니다.
            <code>sql/migrate_tb_cron_job_log.sql</code> 을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">크론·웹훅 실행 로그</h1>
            <p class="ad-p" style="margin-top:0;">
                <code>trade_bank_deposit_confirm.php</code>(무통장 입금 POST)와
                <code>trade_purchase_auto_confirm.php</code>(구매확정 cron) 실행 기록입니다.
                입금 알림 상세는 <a href="/admin/trade_bank_deposits.php">무통장 입금 내역</a>에서 확인할 수 있습니다.
            </p>

            <div class="ad-table-wrap" style="margin-bottom:20px;">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>작업</th>
                        <th>최근 24시간</th>
                        <th>성공 / 실패</th>
                        <th>마지막 실행</th>
                        <th>마지막 성공</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($summaries as $job_key => $sum): ?>
                        <tr>
                            <td>
                                <a href="<?php echo htmlspecialchars($build_qs(['job' => $job_key, 'p' => 1]), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $sum['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td class="ad-num"><?php echo number_format((int) $sum['total']); ?>회</td>
                            <td class="ad-num">
                                <span class="ad-badge ad-badge--ok"><?php echo number_format((int) $sum['ok']); ?></span>
                                /
                                <span class="ad-badge ad-badge--bad"><?php echo number_format((int) $sum['fail']); ?></span>
                            </td>
                            <td class="ad-muted ad-nowrap">
                                <?php echo $sum['last_at']
                                    ? htmlspecialchars(substr((string) $sum['last_at'], 0, 19), ENT_QUOTES, 'UTF-8')
                                    : '<span class="ad-muted">—</span>'; ?>
                            </td>
                            <td class="ad-muted ad-nowrap">
                                <?php echo $sum['last_ok_at']
                                    ? htmlspecialchars(substr((string) $sum['last_ok_at'], 0, 19), ENT_QUOTES, 'UTF-8')
                                    : '<span class="ad-muted">—</span>'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label for="f_job">작업</label>
                    <select id="f_job" name="job">
                        <?php foreach ($job_map as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $job === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label for="f_ok">결과</label>
                    <select id="f_ok" name="ok">
                        <option value="" <?php echo $ok_f === '' ? 'selected' : ''; ?>>전체</option>
                        <option value="1" <?php echo $ok_f === '1' ? 'selected' : ''; ?>>성공</option>
                        <option value="0" <?php echo $ok_f === '0' ? 'selected' : ''; ?>>실패</option>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="f_q">검색</label>
                    <input type="text" id="f_q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="메시지 / pay# / 로그#">
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
                        <th class="ad-nowrap">실행 일시</th>
                        <th>작업</th>
                        <th>결과</th>
                        <th>호출</th>
                        <th class="ad-num">처리</th>
                        <th class="ad-num">성공</th>
                        <th class="ad-num">실패</th>
                        <th>참조</th>
                        <th>메시지</th>
                        <th class="ad-num">ms</th>
                        <th>상세</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="12" class="ad-muted">내역이 없습니다.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $is_ok = (int) ($r['cjl_ok'] ?? 0) === 1;
                        $job_key = (string) ($r['cjl_job'] ?? '');
                        $ref_id = (int) ($r['cjl_ref_id'] ?? 0);
                        $detail_raw = trim((string) ($r['cjl_detail'] ?? ''));
                        $detail_preview = $detail_raw;
                        if (mb_strlen($detail_preview) > 80) {
                            $detail_preview = mb_substr($detail_preview, 0, 80) . '…';
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $r['cjl_idx']; ?></td>
                            <td class="ad-muted ad-nowrap"><?php echo htmlspecialchars(substr((string) $r['cjl_created_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(cron_job_log_job_label($job_key), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="<?php echo $is_ok ? 'ad-badge ad-badge--ok' : 'ad-badge ad-badge--bad'; ?>">
                                    <?php echo $is_ok ? '성공' : '실패'; ?>
                                </span>
                            </td>
                            <td class="ad-muted"><?php echo htmlspecialchars((string) ($r['cjl_trigger'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo number_format((int) ($r['cjl_count_processed'] ?? 0)); ?></td>
                            <td class="ad-num"><?php echo number_format((int) ($r['cjl_count_ok'] ?? 0)); ?></td>
                            <td class="ad-num"><?php echo number_format((int) ($r['cjl_count_fail'] ?? 0)); ?></td>
                            <td>
                                <?php if ($ref_id > 0): ?>
                                    <a href="/admin/trade_payments.php?q=<?php echo $ref_id; ?>">pay#<?php echo $ref_id; ?></a>
                                <?php else: ?>
                                    <span class="ad-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars((string) ($r['cjl_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) ($r['cjl_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="ad-num ad-muted"><?php echo number_format((int) ($r['cjl_duration_ms'] ?? 0)); ?></td>
                            <td class="ad-title-cell" title="<?php echo htmlspecialchars($detail_raw, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $detail_preview !== ''
                                    ? htmlspecialchars($detail_preview, ENT_QUOTES, 'UTF-8')
                                    : '<span class="ad-muted">—</span>'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_page > 1): ?>
                <nav class="ad-pagination" aria-label="페이지">
                    <?php if ($page_no > 1): ?>
                        <a href="<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 2); $i <= min($total_page, $page_no + 2); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
