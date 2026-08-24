<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/card_price_feed.php';

$title     = '박스 시세 · 판매처 행 수정';
$ad_topbar = $ad;
$ad_menu   = 'card_price_offers';
include __DIR__ . '/include/admin_header.php';

$ready             = card_price_tables_ready();
$offer_buy_col     = card_price_co_buy_enabled_supported();
$offer_co_type_col = card_price_co_type_supported();

$co_idx = max(0, (int) ($_GET['co_idx'] ?? 0));
$row    = null;
$products = [];

if ($ready && $co_idx > 0) {
    $rs = db_query("
        SELECT o.*, p.cp_name, p.cp_set_label
        FROM tb_card_price_offer o
        LEFT JOIN tb_card_price_product p ON p.cp_idx = o.cp_idx
        WHERE o.co_idx = {$co_idx}
        LIMIT 1
    ");
    $row = $rs ? db_assoc($rs) : null;
}

if ($ready) {
    $prs = db_query('SELECT cp_idx, cp_name, cp_set_label FROM tb_card_price_product ORDER BY cp_sort DESC, cp_idx DESC');
    if ($prs) {
        while ($p = db_assoc($prs)) {
            $products[] = $p;
        }
    }
    $cur_cp = (int) ($row['cp_idx'] ?? 0);
    if ($cur_cp > 0) {
        $found = false;
        foreach ($products as $p) {
            if ((int) ($p['cp_idx'] ?? 0) === $cur_cp) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($products, [
                'cp_idx' => $cur_cp,
                'cp_name' => '⚠ 연결된 상품 없음 (cp_idx ' . $cur_cp . ', 다른 박스로 옮기세요)',
                'cp_set_label' => '',
            ]);
        }
    }
}

$back = '/admin/card_price_offers.php';

$sq_val = '';
if ($row) {
    $sq = $row['co_stock_qty'] ?? null;
    $sq_val = ($sq === null || $sq === '') ? '' : (string) (int) $sq;
}
$buy_sel = '1';
if ($row && $offer_buy_col) {
    $buy_sel = ((int) ($row['co_buy_enabled'] ?? 1) === 1) ? '1' : '0';
}
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">박스 시세 테이블이 없습니다. <code>sql/tb_card_price.sql</code> 을 적용해 주세요.</div>
    <?php elseif ($co_idx < 1 || !$row): ?>
        <div class="ad-alert ad-alert--error">존재하지 않는 판매처 행입니다.</div>
        <p class="ad-p"><a class="ad-btn" href="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">목록</a></p>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title">판매처 행 수정 (#<?php echo $co_idx; ?>)</h1>
            <p class="ad-p" style="margin-top:0;">
                이 양식은 <strong>한 행</strong>만 바꿉니다. 같은 박스의 여러 판매처를 한 번에 다루려면
                <?php if ((int) ($row['cp_idx'] ?? 0) > 0): ?>
                    <a href="/admin/card_price_product_form.php?idx=<?php echo (int) $row['cp_idx']; ?>">미개봉 박스 수정</a>을 이용하세요.
                <?php else: ?>
                    미개봉 박스 수정 화면에서 한 번에 수정할 수 있습니다.
                <?php endif; ?>
            </p>

            <form class="ad-form" method="post" action="/proc/admin_card_price_offer_write_proc.php" autocomplete="off">
                <input type="hidden" name="co_idx" value="<?php echo $co_idx; ?>">

                <div class="ad-field">
                    <label for="cp_idx">소속 미개봉 박스</label>
                    <select id="cp_idx" name="cp_idx" required>
                        <?php foreach ($products as $p):
                            $pid = (int) $p['cp_idx'];
                            $plab = (string) $p['cp_name'];
                            $sl = trim((string) ($p['cp_set_label'] ?? ''));
                            if ($sl !== '') {
                                $plab .= ' (' . $sl . ')';
                            }
                            ?>
                            <option value="<?php echo $pid; ?>"<?php echo (int) ($row['cp_idx'] ?? 0) === $pid ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars('#' . $pid . ' · ' . $plab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ad-field">
                    <label for="co_site_id">판매처 ID</label>
                    <input type="text" id="co_site_id" name="co_site_id" maxlength="64"
                           value="<?php echo htmlspecialchars((string) ($row['co_site_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="비우면 판매처명에서 자동">
                </div>

                <?php if ($offer_co_type_col): ?>
                    <div class="ad-field">
                        <label for="co_type">플랫폼·업체</label>
                        <input type="text" id="co_type" name="co_type" maxlength="120"
                               value="<?php echo htmlspecialchars((string) ($row['co_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="예) 쿠팡, 신세계">
                    </div>
                <?php endif; ?>

                <div class="ad-field">
                    <label for="co_site_name">판매처명</label>
                    <input type="text" id="co_site_name" name="co_site_name" maxlength="120" required
                           value="<?php echo htmlspecialchars((string) ($row['co_site_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="ad-field">
                    <label for="co_product_url">상품 URL</label>
                    <input type="text" id="co_product_url" name="co_product_url" maxlength="500" required
                           value="<?php echo htmlspecialchars((string) ($row['co_product_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="https://">
                </div>

                <div class="ad-field">
                    <label for="co_price_won">가격(원)</label>
                    <input type="number" id="co_price_won" name="co_price_won" min="0" max="4294967295" step="1"
                           value="<?php echo (int) ($row['co_price_won'] ?? 0) > 0 ? (int) $row['co_price_won'] : ''; ?>"
                           placeholder="0 이면 가격 확인">
                </div>

                <div class="ad-field">
                    <label for="co_stock_qty">재고 수량</label>
                    <input type="number" id="co_stock_qty" name="co_stock_qty" min="0" max="2147483647" step="1"
                           value="<?php echo htmlspecialchars($sq_val, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="비우면 미확인">
                </div>

                <?php if ($offer_buy_col): ?>
                    <div class="ad-field">
                        <label for="co_buy_enabled">목록 노출</label>
                        <select id="co_buy_enabled" name="co_buy_enabled">
                            <option value="1"<?php echo $buy_sel === '1' ? ' selected' : ''; ?>>예</option>
                            <option value="0"<?php echo $buy_sel === '0' ? ' selected' : ''; ?>>아니오</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="ad-field">
                    <label for="co_sort">정렬값</label>
                    <input type="number" id="co_sort" name="co_sort" step="1"
                           value="<?php echo (int) ($row['co_sort'] ?? 0); ?>">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">같은 박스 안에서 큰 값이 먼저 표시됩니다.</span>
                </div>

                <div class="ad-actions">
                    <a class="ad-btn" href="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">목록</a>
                    <button type="submit" class="ad-btn">저장</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
