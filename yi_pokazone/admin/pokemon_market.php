<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_pokemon_market.php';

$title     = '전체 시세 · 포켓몬명 관리';
$ad_topbar = $ad;
$ad_menu   = 'pokemon_market';
include __DIR__ . '/include/admin_header.php';

$ready = pokemon_market_table_ready();
$st = trim((string) ($_GET['st'] ?? '1'));
if (!in_array($st, ['all', '1', '9'], true)) {
    $st = '1';
}
$q = trim((string) ($_GET['q'] ?? ''));
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 40;
$rows = [];
$total = 0;
$total_page = 1;

if ($ready) {
    $where = ['1=1'];
    if ($st === '1' || $st === '9') {
        $where[] = 'pm_status = ' . (int) $st;
    }
    if ($q !== '') {
        $e = db_escape($q);
        $where[] = "pm_name LIKE '%{$e}%'";
    }
    $where_sql = implode(' AND ', $where);
    $total = (int) db_result("SELECT COUNT(*) FROM tb_pokemon_market WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per_page));
    $page_no = min($page_no, $total_page);
    $offset = ($page_no - 1) * $per_page;

    $rs = db_query("
        SELECT *
        FROM tb_pokemon_market
        WHERE {$where_sql}
        ORDER BY pm_sort ASC, pm_idx ASC
        LIMIT {$offset}, {$per_page}
    ");
    while ($row = db_assoc($rs)) {
        $name = (string) ($row['pm_name'] ?? '');
        $stats = pokemon_market_aggregate_prices($name);
        $row['sold_count'] = (int) ($stats['count'] ?? 0);
        $row['sold_avg'] = (int) ($stats['avg'] ?? 0);
        $rows[] = $row;
    }
}

$build_qs = static function (array $overrides = []) use ($st, $q, $page_no): string {
    $params = ['st' => $st, 'q' => $q, 'p' => $page_no];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    if (($params['st'] ?? '') === '1') {
        unset($params['st']);
    }
    if (($params['q'] ?? '') === '') {
        unset($params['q']);
    }
    if ((int) ($params['p'] ?? 1) <= 1) {
        unset($params['p']);
    }
    return $params ? '?' . http_build_query($params) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            <code>tb_pokemon_market</code> 테이블이 없습니다. <code>sql/tb_pokemon_market.sql</code>을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:10rem;">포켓몬명 관리</h1>
                <a class="ad-btn" href="/page/market_price.php" target="_blank" rel="noopener">사용자 페이지</a>
            </div>
            <p class="ad-p" style="margin-top:0;">
                여기에 등록한 포켓몬명이 사용자 「전체 시세」에 노출됩니다.
                거래글의 <strong>제목 또는 카드명에 등록명이 포함</strong>되면 집계하며, 레어도·등급은 구분하지 않습니다.
            </p>

            <form class="ad-form" method="post" action="/proc/admin_pokemon_market_proc.php" style="margin-bottom:1.25rem;">
                <input type="hidden" name="action" value="insert">
                <div class="ad-toolbar" style="flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
                    <div class="ad-field ad-field--grow">
                        <label for="pm_name">포켓몬명</label>
                        <input type="text" id="pm_name" name="pm_name" maxlength="100" required
                               placeholder="예: 피카츄">
                    </div>
                    <div class="ad-field" style="width:7rem;">
                        <label for="pm_sort">정렬</label>
                        <input type="number" id="pm_sort" name="pm_sort" value="0">
                    </div>
                    <div class="ad-field">
                        <label>&nbsp;</label>
                        <button type="submit" class="ad-btn">등록</button>
                    </div>
                </div>
            </form>

            <form class="ad-toolbar ad-form" method="get" action="/admin/pokemon_market.php">
                <div class="ad-field">
                    <label for="st">상태</label>
                    <select id="st" name="st">
                        <option value="1"<?php echo $st === '1' ? ' selected' : ''; ?>>노출</option>
                        <option value="9"<?php echo $st === '9' ? ' selected' : ''; ?>>숨김</option>
                        <option value="all"<?php echo $st === 'all' ? ' selected' : ''; ?>>전체</option>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="q">검색</label>
                    <input type="text" id="q" name="q"
                           value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="포켓몬명">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건</p>

            <?php if (!$rows): ?>
                <div class="ad-alert">등록된 포켓몬이 없습니다. 위에서 이름을 추가해 주세요.</div>
            <?php else: ?>
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th style="width:4rem;">ID</th>
                            <th>포켓몬명</th>
                            <th style="width:5rem;">정렬</th>
                            <th style="width:5rem;">상태</th>
                            <th style="width:7rem;">판매완료</th>
                            <th style="width:8rem;">평균가</th>
                            <th style="width:12rem;">관리</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row):
                            $idx = (int) $row['pm_idx'];
                            $is_on = ((int) $row['pm_status'] === 1);
                            ?>
                            <tr>
                                <td><?php echo $idx; ?></td>
                                <td>
                                    <form method="post" action="/proc/admin_pokemon_market_proc.php" class="ad-form" style="display:flex;gap:0.5rem;align-items:center;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="pm_idx" value="<?php echo $idx; ?>">
                                        <input type="hidden" name="st" value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="p" value="<?php echo $page_no; ?>">
                                        <input type="text" name="pm_name" maxlength="100" required
                                               value="<?php echo htmlspecialchars((string) $row['pm_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                               style="min-width:8rem;flex:1;">
                                        <input type="number" name="pm_sort" value="<?php echo (int) $row['pm_sort']; ?>" style="width:4.5rem;">
                                        <button type="submit" class="ad-btn ad-btn--sm">저장</button>
                                    </form>
                                </td>
                                <td><?php echo (int) $row['pm_sort']; ?></td>
                                <td>
                                    <?php if ($is_on): ?>
                                        <span class="ad-badge">노출</span>
                                    <?php else: ?>
                                        <span class="ad-badge ad-badge--bad">숨김</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format((int) $row['sold_count']); ?>건</td>
                                <td>
                                    <?php if ((int) $row['sold_avg'] > 0): ?>
                                        ₩<?php echo number_format((int) $row['sold_avg']); ?>
                                    <?php else: ?>
                                        <span class="ad-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <a class="ad-btn ad-btn--sm" href="/page/market_price.php?idx=<?php echo $idx; ?>" target="_blank" rel="noopener">보기</a>
                                    <form method="post" action="/proc/admin_pokemon_market_proc.php" style="display:inline;"
                                          onsubmit="return confirm('상태를 변경할까요?');">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="pm_idx" value="<?php echo $idx; ?>">
                                        <input type="hidden" name="st" value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="p" value="<?php echo $page_no; ?>">
                                        <button type="submit" class="ad-btn ad-btn--sm"><?php echo $is_on ? '숨김' : '노출'; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_page > 1): ?>
                    <nav class="ad-pagination" aria-label="포켓몬명 페이지" style="margin-top:1rem;">
                        <?php for ($i = 1; $i <= $total_page; $i++): ?>
                            <a href="/admin/pokemon_market.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"
                               class="<?php echo $i === $page_no ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
