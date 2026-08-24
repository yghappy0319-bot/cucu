<?php
require_once __DIR__ . '/../../admin/include/admin_init.php';

$title     = '거래글관리';
$ad_topbar = $ad;
$ad_menu   = 'trades';
include __DIR__ . '/../../admin/include/admin_header.php';

$types = [
    ''         => '전체',
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];
$deal_map = [
    '' => '거래상태 전체',
    '1' => '판매중',
    '2' => '거래중',
    '3' => '거래완료',
];
$st_map = [
    'all' => '글상태 전체',
    '1'   => '노출',
    '2'   => '게시중지',
    '9'   => '숨김',
];

$cur_type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
$cur_deal = isset($_GET['deal']) ? trim((string) $_GET['deal']) : '';
$st       = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$q        = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_no  = max(1, (int) ($_GET['p'] ?? 1));
$per      = 15;
$offset   = ($page_no - 1) * $per;

if ($cur_type !== '' && !array_key_exists($cur_type, $types)) {
    $cur_type = '';
}
if ($cur_deal !== '' && !array_key_exists($cur_deal, $deal_map)) {
    $cur_deal = '';
}

$ready = db_table_exists('tb_trade');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($cur_type !== '') {
        $where[] = "t.tr_type = '" . db_escape($cur_type) . "'";
    }
    if ($cur_deal === '1' || $cur_deal === '2' || $cur_deal === '3') {
        $where[] = 't.tr_deal_status = ' . (int) $cur_deal;
    }
    if ($st === '1' || $st === '2' || $st === '9') {
        $where[] = 't.tr_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "(t.tr_title LIKE '%{$e}%' OR t.tr_card_name LIKE '%{$e}%' OR t.tr_content LIKE '%{$e}%')";
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("SELECT COUNT(*) FROM tb_trade t WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT t.*, m.mb_nick, m.mb_id
        FROM tb_trade t
        LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
        WHERE {$where_sql}
        ORDER BY t.tr_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($cur_type, $cur_deal, $st, $q, $page_no) {
    $base = ['type' => $cur_type, 'deal' => $cur_deal, 'st' => $st, 'q' => $q, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['type'] ?? '') === '') {
        unset($base['type']);
    }
    if (($base['deal'] ?? '') === '') {
        unset($base['deal']);
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
        <div class="ad-alert ad-alert--error">tb_trade 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">거래 게시글</h1>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>유형</label>
                    <select name="type">
                        <?php foreach ($types as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $cur_type === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label>거래진행</label>
                    <select name="deal">
                        <?php foreach ($deal_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $cur_deal === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label>글 노출</label>
                    <select name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="제목·상품명·본문">
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
                        <th>유형</th>
                        <th>제목 / 상품</th>
                        <th>작성자</th>
                        <th class="ad-num">가격</th>
                        <th>거래</th>
                        <th>글</th>
                        <th>처리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $st = (int) $r['tr_status'];
                        $ok = $st === TRADE_STATUS_OK;
                        $held = $st === TRADE_STATUS_HOLD;
                        $dl = (int) $r['tr_deal_status'];
                        $dl_label = $deal_map[(string) $dl] ?? $dl;
                        ?>
                        <tr>
                            <td><?php echo (int) $r['tr_idx']; ?></td>
                            <td><?php echo htmlspecialchars($types[$r['tr_type']] ?? $r['tr_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-title-cell">
                                <a href="/trade/trade_view.php?idx=<?php echo (int) $r['tr_idx']; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['tr_title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                <div class="ad-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['tr_card_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($r['mb_nick'] ?: $r['mb_id'] ?: ('#' . $r['mb_idx']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo (int) $r['tr_price'] > 0 ? number_format((int) $r['tr_price']) : '—'; ?></td>
                            <td>
                                <?php if ($ok): ?>
                                    <form class="ad-form ad-form--inline" action="/trade/proc/admin_trade_proc.php" method="post">
                                        <input type="hidden" name="tr_idx" value="<?php echo (int) $r['tr_idx']; ?>">
                                        <input type="hidden" name="action" value="deal_status">
                                        <select name="tr_deal_status" style="width:auto;height:36px;font-size:13px;">
                                            <option value="1"<?php echo $dl === 1 ? ' selected' : ''; ?>>판매중</option>
                                            <option value="2"<?php echo $dl === 2 ? ' selected' : ''; ?>>거래중</option>
                                            <option value="3"<?php echo $dl === 3 ? ' selected' : ''; ?>>거래완료</option>
                                        </select>
                                        <button type="submit" class="ad-btn">적용</button>
                                    </form>
                                <?php else: ?>
                                    <span class="ad-muted"><?php echo htmlspecialchars($dl_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($held): ?>
                                    <span class="ad-badge ad-badge--warn">게시중지</span>
                                    <?php if (!empty($r['tr_hold_reason'])): ?>
                                        <div class="ad-muted" style="font-size:12px;max-width:160px;"><?php echo htmlspecialchars((string) $r['tr_hold_reason'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>"><?php echo $ok ? '노출' : '숨김'; ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a class="ad-btn"
                                   href="/trade/trade_view.php?idx=<?php echo (int) $r['tr_idx']; ?>#trade-hold-admin" target="_blank" rel="noopener"><?php echo $held ? '중지관리' : '상세'; ?></a>
                                <a class="ad-btn"
                                   href="/trade/trade_write.php?idx=<?php echo (int) $r['tr_idx']; ?>&amp;from=admin">수정</a>
                                <form class="ad-form ad-form--inline" action="/trade/proc/admin_trade_proc.php" method="post" onsubmit="return confirm('<?php echo $ok || $held ? '이 거래글을 숨김 처리할까요?' : '이 글을 다시 노출할까요?'; ?>');" style="display:inline;margin-left:6px;">
                                    <input type="hidden" name="tr_idx" value="<?php echo (int) $r['tr_idx']; ?>">
                                    <input type="hidden" name="action" value="<?php echo ($ok || $held) ? 'hide' : 'show'; ?>">
                                    <button type="submit" class="ad-btn"><?php echo ($ok || $held) ? '숨김' : '복구'; ?></button>
                                </form>
                                <form class="ad-form ad-form--inline" action="/trade/proc/admin_trade_proc.php" method="post"
                                      onsubmit="return confirm('이 거래글을 DB에서 완전히 삭제할까요? 복구할 수 없으며, 연결된 문의 채팅·이미지 기록도 함께 제거됩니다.');"
                                      style="margin-left:6px;display:inline;">
                                    <input type="hidden" name="tr_idx" value="<?php echo (int) $r['tr_idx']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="ad-btn" style="background:#b91c1c;border-color:#b91c1c;">삭제</button>
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
                        <a href="/trade/admin/trades.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/trade/admin/trades.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/trade/admin/trades.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($_GET['wrote']) && (string) $_GET['wrote'] === '1'): ?>
<script>
try {
    Object.keys(sessionStorage).forEach(function (k) {
        if (k.indexOf('pz_trade_write_draft_') === 0) sessionStorage.removeItem(k);
    });
} catch (e) {}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../admin/include/admin_footer.php'; ?>
