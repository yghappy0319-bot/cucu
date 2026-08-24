<?php
require_once __DIR__ . '/../../admin/include/admin_init.php';
require_once __DIR__ . '/../lib/_admin_auction.php';

$title     = '경매 등록내역';
$ad_topbar = $ad;
$ad_menu   = 'auction_listings';
include __DIR__ . '/../../admin/include/admin_header.php';

$q       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$ast     = isset($_GET['ast']) ? trim((string) $_GET['ast']) : '';
$st      = isset($_GET['st']) ? trim((string) $_GET['st']) : 'all';
$itype   = isset($_GET['itype']) ? trim((string) $_GET['itype']) : '';
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per     = 20;
$offset  = ($page_no - 1) * $per;

$ast_map   = admin_au_list_status_filter_options();
$st_map    = admin_au_post_status_filter_options();
$itype_map = admin_au_item_type_filter_options();

if ($ast !== '' && !isset($ast_map[$ast])) {
    $ast = '';
}
if (!isset($st_map[$st])) {
    $st = 'all';
}
if ($itype !== '' && !isset($itype_map[$itype])) {
    $itype = '';
}

$ready = db_table_exists('tb_auction');
$rows  = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($ast !== '' && in_array($ast, ['0', '1', '2', '3', '4', '9'], true)) {
        $where[] = 'a.au_auction_status = ' . (int) $ast;
    }
    if ($st === '1' || $st === '9') {
        $where[] = 'a.au_status = ' . (int) $st;
    }
    if ($itype === 'card' || $itype === 'box') {
        $where[] = "a.au_item_type = '" . db_escape($itype) . "'";
    }
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(a.au_idx = ' . (int) $q . ' OR a.mb_idx = ' . (int) $q
                . ' OR a.au_winner_mb_idx = ' . (int) $q . ')';
        } else {
            $e = db_escape($q);
            $where[] = "(a.au_title LIKE '%{$e}%' OR a.au_card_name LIKE '%{$e}%'
                OR m.mb_id LIKE '%{$e}%' OR m.mb_nick LIKE '%{$e}%')";
        }
    }
    $where_sql = implode(' AND ', $where);

    $total      = (int) db_result("
        SELECT COUNT(*)
        FROM tb_auction a
        LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
        WHERE {$where_sql}
    ");
    $total_page = max(1, (int) ceil($total / $per));

    $rs = db_query("
        SELECT a.au_idx, a.au_item_type, a.au_card_kind, a.au_title, a.au_card_name,
               a.au_start_price, a.au_current_price, a.au_buy_now_price, a.au_bid_count,
               a.au_auction_status, a.au_status, a.au_starts_at, a.au_ends_at,
               a.au_winner_mb_idx, a.au_views, a.au_created_at, a.mb_idx,
               m.mb_id, m.mb_nick,
               w.mb_nick AS winner_nick
        FROM tb_auction a
        LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
        LEFT JOIN tb_member w ON w.mb_idx = a.au_winner_mb_idx
        WHERE {$where_sql}
        ORDER BY a.au_idx DESC
        LIMIT {$offset}, {$per}
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

$build_qs = static function (array $o) use ($q, $ast, $st, $itype, $page_no) {
    $base = ['q' => $q, 'ast' => $ast, 'st' => $st, 'itype' => $itype, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['q'] ?? '') === '') {
        unset($base['q']);
    }
    if (($base['ast'] ?? '') === '') {
        unset($base['ast']);
    }
    if (($base['st'] ?? '') === '' || ($base['st'] ?? '') === 'all') {
        unset($base['st']);
    }
    if (($base['itype'] ?? '') === '') {
        unset($base['itype']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }
    return $base ? '?' . http_build_query($base) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_auction 테이블이 없습니다. <code>sql/tb_auction.sql</code> 을 확인해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">경매 등록 내역</h1>
            <form class="ad-toolbar ad-form" method="get" action="">
                <div class="ad-field">
                    <label>경매상태</label>
                    <select name="ast">
                        <?php foreach ($ast_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ast === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label>글상태</label>
                    <select name="st">
                        <?php foreach ($st_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $st === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field">
                    <label>상품</label>
                    <select name="itype">
                        <?php foreach ($itype_map as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $itype === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label>검색</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="경매# / 회원# / 제목·상품명 / 아이디·닉네임">
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
                        <th>경매#</th>
                        <th>제목 / 상품</th>
                        <th>유형</th>
                        <th>등록자</th>
                        <th class="ad-num">시작가</th>
                        <th class="ad-num">현재가</th>
                        <th class="ad-num">입찰</th>
                        <th>경매상태</th>
                        <th>낙찰자</th>
                        <th>글</th>
                        <th class="ad-nowrap">등록일</th>
                        <th class="ad-nowrap">마감일</th>
                        <th>관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="13" class="ad-muted">내역이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $post_ok = (int) ($r['au_status'] ?? 1) === 1;
                        $winner  = (int) ($r['au_winner_mb_idx'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo (int) $r['au_idx']; ?></td>
                            <td class="ad-title-cell">
                                <a href="/auction/auction_view.php?idx=<?php echo (int) $r['au_idx']; ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars((string) $r['au_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <div class="ad-muted" style="font-size:12px;"><?php echo htmlspecialchars((string) $r['au_card_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars(admin_au_item_type_label($r), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['mb_nick'] ?: $r['mb_id'] ?: ('#' . $r['mb_idx'])), ENT_QUOTES, 'UTF-8'); ?>
                                <div class="ad-muted" style="font-size:12px;">#<?php echo (int) $r['mb_idx']; ?></div>
                            </td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['au_start_price']); ?></td>
                            <td class="ad-num">₩<?php echo number_format((int) $r['au_current_price']); ?></td>
                            <td class="ad-num"><?php echo number_format((int) $r['au_bid_count']); ?>회</td>
                            <td><?php echo htmlspecialchars(admin_au_auction_status_label((int) $r['au_auction_status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ($winner > 0): ?>
                                    <?php echo htmlspecialchars((string) ($r['winner_nick'] ?: ('#' . $winner)), ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    <span class="ad-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ad-badge <?php echo $post_ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>">
                                    <?php echo $post_ok ? '노출' : '삭제'; ?>
                                </span>
                            </td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) $r['au_created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap"><?php echo htmlspecialchars((string) $r['au_ends_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-nowrap">
                                <form class="ad-form ad-form--inline" action="/auction/proc/admin_auction_proc.php" method="post"
                                      onsubmit="return confirm('<?php echo $post_ok
                                          ? '이 경매를 삭제(숨김) 처리할까요? 목록·상세에서 보이지 않으며, 첨부 이미지 파일은 삭제됩니다. 입찰·주문 기록은 유지됩니다.'
                                          : '이 경매를 다시 노출할까요? (첨부 이미지는 복구되지 않습니다)'; ?>');">
                                    <input type="hidden" name="au_idx" value="<?php echo (int) $r['au_idx']; ?>">
                                    <input type="hidden" name="action" value="<?php echo $post_ok ? 'hide' : 'show'; ?>">
                                    <button type="submit" class="ad-btn"><?php echo $post_ok ? '삭제' : '복구'; ?></button>
                                </form>
                                <?php if ($post_ok): ?>
                                    <form class="ad-form ad-form--inline" action="/auction/proc/admin_auction_proc.php" method="post"
                                          onsubmit="return confirm('이 경매를 DB에서 완전히 삭제할까요? 복구할 수 없으며, 입찰 기록도 함께 제거됩니다. 낙찰 주문이 있으면 삭제되지 않습니다.');"
                                          style="margin-left:6px;display:inline;">
                                        <input type="hidden" name="au_idx" value="<?php echo (int) $r['au_idx']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="ad-btn" style="background:#b91c1c;border-color:#b91c1c;">완전삭제</button>
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
                        <a href="/auction/admin/auction_listings.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/auction/admin/auction_listings.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/auction/admin/auction_listings.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../admin/include/admin_footer.php'; ?>
