<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_snkrdunk_box.php';

$title     = '박스 시세 · 한글명 관리';
$ad_topbar = $ad;
$ad_menu   = 'snkrdunk_boxes';
include __DIR__ . '/include/admin_header.php';

$ready = snkrdunk_box_tables_ready();
$name_ko_ready = $ready && snkrdunk_box_name_ko_supported();
$st = trim((string) ($_GET['st'] ?? 'all'));
if (!in_array($st, ['all', 'done', 'empty'], true)) {
    $st = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));
$page_no = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 30;
$rows = [];
$total = 0;
$total_page = 1;

if ($name_ko_ready) {
    $where = ['sb_status = 1'];
    if ($st === 'done') {
        $where[] = "TRIM(sb_name_ko) <> ''";
    } elseif ($st === 'empty') {
        $where[] = "TRIM(sb_name_ko) = ''";
    }
    if ($q !== '') {
        $q_esc = db_escape($q);
        $where[] = "(
            sb_name_ko LIKE '%{$q_esc}%'
            OR sb_name_ja LIKE '%{$q_esc}%'
            OR sb_name_en LIKE '%{$q_esc}%'
            OR sb_product_number LIKE '%{$q_esc}%'
        )";
    }
    $where_sql = implode(' AND ', $where);
    $total = (int) db_result("SELECT COUNT(*) FROM tb_snkrdunk_box WHERE {$where_sql}");
    $total_page = max(1, (int) ceil($total / $per_page));
    $page_no = min($page_no, $total_page);
    $offset = ($page_no - 1) * $per_page;

    $rs = db_query("
        SELECT *
        FROM tb_snkrdunk_box
        WHERE {$where_sql}
        ORDER BY (TRIM(sb_name_ko) = '') DESC, sb_listing_count DESC, sb_product_id DESC
        LIMIT {$offset}, {$per_page}
    ");
    while ($row = db_assoc($rs)) {
        $rows[] = $row;
    }
}

$build_qs = static function (array $overrides = []) use ($st, $q, $page_no): string {
    $params = ['st' => $st, 'q' => $q, 'p' => $page_no];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    if (($params['st'] ?? '') === 'all') unset($params['st']);
    if (($params['q'] ?? '') === '') unset($params['q']);
    if ((int) ($params['p'] ?? 1) <= 1) unset($params['p']);
    return $params ? '?' . http_build_query($params) : '';
};
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            스니덩크 박스 테이블이 없습니다. 먼저 <code>sql/tb_snkrdunk_box.sql</code>을 적용해 주세요.
        </div>
    <?php elseif (!$name_ko_ready): ?>
        <div class="ad-alert ad-alert--error">
            한글명 컬럼이 없습니다. <code>sql/migrate_tb_snkrdunk_box_name_ko.sql</code>을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:10rem;">스니덩크 박스 한글명</h1>
                <a class="ad-btn" href="/page/card_price.php" target="_blank" rel="noopener">사용자 페이지</a>
            </div>
            <p class="ad-p" style="margin-top:0;">
                크롤링된 일본어·영문 상품을 확인하고 사용자 화면에 표시할 한글명을 입력합니다.
                한글명을 비우면 일본어명이 대신 표시되며, 크론 수집을 다시 실행해도 관리자 한글명은 유지됩니다.
            </p>

            <form class="ad-toolbar ad-form" method="get" action="/admin/snkrdunk_boxes.php">
                <div class="ad-field">
                    <label for="st">번역 상태</label>
                    <select id="st" name="st">
                        <option value="all"<?php echo $st === 'all' ? ' selected' : ''; ?>>전체</option>
                        <option value="empty"<?php echo $st === 'empty' ? ' selected' : ''; ?>>미입력</option>
                        <option value="done"<?php echo $st === 'done' ? ' selected' : ''; ?>>입력 완료</option>
                    </select>
                </div>
                <div class="ad-field ad-field--grow">
                    <label for="q">검색</label>
                    <input type="text" id="q" name="q"
                           value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="한글·일본어·영문 상품명 또는 세트 코드">
                </div>
                <div class="ad-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ad-btn">검색</button>
                </div>
            </form>

            <p class="ad-p" style="margin-top:0;">총 <?php echo number_format($total); ?>건</p>

            <?php if (!$rows): ?>
                <div class="ad-alert">조건에 맞는 상품이 없습니다.</div>
            <?php else: ?>
                <form method="post" action="/proc/admin_snkrdunk_box_proc.php" class="ad-form">
                    <input type="hidden" name="action" value="save_names">
                    <input type="hidden" name="st" value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="p" value="<?php echo $page_no; ?>">

                    <div class="ad-table-wrap">
                        <table class="ad-table">
                            <thead>
                            <tr>
                                <th style="width:5rem;">이미지</th>
                                <th style="min-width:17rem;">원문 상품명</th>
                                <th style="min-width:20rem;">한글명</th>
                                <th class="ad-num">엔화</th>
                                <th class="ad-num">매물</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row):
                                $id = (int) $row['sb_product_id'];
                                $thumb = trim((string) ($row['sb_thumbnail_url'] ?? ''));
                                $thumb_ok = filter_var($thumb, FILTER_VALIDATE_URL) !== false
                                    && parse_url($thumb, PHP_URL_SCHEME) === 'https';
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($thumb_ok): ?>
                                            <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="" loading="lazy" referrerpolicy="no-referrer"
                                                 style="width:56px;height:56px;object-fit:contain;border:1px solid var(--ad-border,#e5e7eb);border-radius:8px;background:#fff;">
                                        <?php else: ?>
                                            <span style="font-size:1.7rem;">📦</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string) $row['sb_name_ja'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div class="ad-muted" style="margin-top:0.3rem;font-size:12px;line-height:1.45;">
                                            <?php echo htmlspecialchars((string) $row['sb_name_en'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="ad-muted" style="margin-top:0.25rem;font-size:11px;">
                                            <?php echo htmlspecialchars((string) $row['sb_product_number'], ENT_QUOTES, 'UTF-8'); ?>
                                            · ID <?php echo $id; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="name_ko[<?php echo $id; ?>]"
                                               maxlength="255"
                                               value="<?php echo htmlspecialchars((string) $row['sb_name_ko'], ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="예) 포켓몬카드 151 미개봉 박스"
                                               style="width:100%;min-width:18rem;">
                                    </td>
                                    <td class="ad-num ad-nowrap">¥<?php echo number_format((int) $row['sb_min_price_jpy']); ?></td>
                                    <td class="ad-num"><?php echo number_format((int) $row['sb_listing_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn ad-btn--primary">이 페이지 한글명 저장</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($total_page > 1): ?>
                <div class="ad-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/admin/snkrdunk_boxes.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/admin/snkrdunk_boxes.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/admin/snkrdunk_boxes.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
