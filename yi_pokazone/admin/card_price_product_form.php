<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/card_price_feed.php';

$title     = '박스 시세 · 미개봉 박스 등록·수정';
$ad_topbar = $ad;
$ad_menu   = 'card_price';
include __DIR__ . '/include/admin_header.php';

$ready   = card_price_tables_ready();
$offer_buy_col = card_price_co_buy_enabled_supported();
$offer_co_type_col = card_price_co_type_supported();
$idx     = max(0, (int) ($_GET['idx'] ?? 0));
$is_edit = $idx > 0;
$row     = null;
$offer_rows = [];

if ($ready && $is_edit) {
    $rs  = db_query("SELECT * FROM tb_card_price_product WHERE cp_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 상품입니다.', '/admin/card_price_products.php');
    }
    $co_buy_field = $offer_buy_col ? ', co_buy_enabled' : '';
    $co_type_field = $offer_co_type_col ? ', co_type' : '';
    $ors = db_query("
        SELECT co_site_id, co_site_name, co_product_url, co_price_won, co_stock_qty{$co_buy_field}{$co_type_field}
        FROM tb_card_price_offer
        WHERE cp_idx = {$idx}
        ORDER BY co_sort DESC, co_idx ASC
    ");
    if ($ors) {
        while ($o = db_assoc($ors)) {
            $sq = $o['co_stock_qty'];
            $buyv = '1';
            if ($offer_buy_col) {
                $buyv = ((int) ($o['co_buy_enabled'] ?? 1) === 1) ? '1' : '0';
            }
            $offer_rows[] = [
                'co_site_id' => (string) ($o['co_site_id'] ?? ''),
                'co_site_name' => (string) ($o['co_site_name'] ?? ''),
                'co_type' => $offer_co_type_col ? (string) ($o['co_type'] ?? '') : '',
                'co_product_url' => (string) ($o['co_product_url'] ?? ''),
                'co_price_won' => (int) ($o['co_price_won'] ?? 0),
                'co_stock_qty' => ($sq === null || $sq === '') ? '' : (string) (int) $sq,
                'co_buy_enabled' => $buyv,
            ];
        }
    }
}

$slot_target = max(5, count($offer_rows) + 3);
while (count($offer_rows) < $slot_target) {
    $offer_rows[] = [
        'co_site_id' => '',
        'co_site_name' => '',
        'co_type' => '',
        'co_product_url' => '',
        'co_price_won' => 0,
        'co_stock_qty' => '',
        'co_buy_enabled' => '1',
    ];
}

$v = function ($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit || !$row) {
        return $default;
    }
    return $row[$field] ?? $default;
};

$back_href = '/admin/card_price_products.php';
$cur_img   = $is_edit && $row ? trim((string) ($row['cp_image_path'] ?? '')) : '';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">박스 시세 테이블이 없습니다. <code>sql/tb_card_price.sql</code> 을 적용해 주세요.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title"><?php echo $is_edit ? '미개봉 박스 수정' : '미개봉 박스 등록'; ?></h1>
                <p class="ad-p" style="margin-top:0;">
                한 상품(박스)에 여러 쇼핑몰 행을 넣을 수 있습니다. 빈 행은 저장 시 무시됩니다.
                <?php if ($offer_buy_col): ?>
                    <strong>목록 노출</strong>이 «아니오»이면 사용자 박스 시세 목록에서 해당 판매처 행이 숨겨집니다(크론으로 DB 갱신 가능).
                <?php else: ?>
                    <code>sql/tb_card_price_offer_co_buy_enabled.sql</code> 로 <code>co_buy_enabled</code> 컬럼을 추가하면 행별 목록 노출 여부를 다룰 수 있습니다.
                <?php endif; ?>
                <?php if (!$offer_co_type_col): ?>
                    <code>sql/tb_card_price_offer_co_type.sql</code> 로 <code>co_type</code> 컬럼을 추가하면 쿠팡·신세계 등 플랫폼·업체명을 행마다 저장할 수 있습니다.
                <?php endif; ?>
            </p>

            <form class="ad-form" method="post" action="/proc/admin_card_price_product_write_proc.php" enctype="multipart/form-data" autocomplete="off">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <?php else: ?>
                    <input type="hidden" name="mode" value="insert">
                <?php endif; ?>

                <div class="ad-field">
                    <label for="cp_name">상품명</label>
                    <input type="text" id="cp_name" name="cp_name" maxlength="255" required
                           value="<?php echo htmlspecialchars((string) $v('cp_name'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="예) 확장팩 「초전설의 빛」 미개봉 부스터">
                </div>

                <div class="ad-field">
                    <label for="cp_set_label">세트·라벨 (선택)</label>
                    <input type="text" id="cp_set_label" name="cp_set_label" maxlength="80"
                           value="<?php echo htmlspecialchars((string) $v('cp_set_label'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="예) SV8">
                </div>

                <div class="ad-field">
                    <label for="cp_emoji">목록 썸네일 이모지</label>
                    <input type="text" id="cp_emoji" name="cp_emoji" maxlength="16"
                           value="<?php echo htmlspecialchars((string) $v('cp_emoji', '📦'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="📦">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">이미지 경로가 없을 때 표시됩니다.</span>
                </div>

                <div class="ad-field">
                    <label for="cp_image">대표 이미지 (선택)</label>
                    <?php if ($cur_img !== ''): ?>
                        <div style="margin-bottom:0.5rem;">
                            <img src="<?php echo htmlspecialchars(public_url($cur_img), ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" style="max-width:120px;max-height:120px;object-fit:contain;border-radius:8px;border:1px solid var(--ad-border, #e5e5e5);">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="cp_image" name="cp_image" class="ad-input"
                           accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">jpg · png · gif · webp, 최대 5MB. <?php echo $cur_img !== '' ? '새 파일을 고르면 교체됩니다.' : ''; ?></span>
                    <?php if ($cur_img !== ''): ?>
                        <label style="display:flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-size:14px;cursor:pointer;">
                            <input type="checkbox" name="cp_image_remove" value="1">
                            등록된 이미지 삭제
                        </label>
                    <?php endif; ?>
                </div>

                <div class="ad-field">
                    <label for="cp_sort">정렬값</label>
                    <input type="number" id="cp_sort" name="cp_sort" step="1"
                           value="<?php echo htmlspecialchars((string) ($is_edit && $row ? (int) $row['cp_sort'] : '0'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">큰 값이 사용자 목록에서 앞에 옵니다.</span>
                </div>

                <div class="ad-field">
                    <label for="cp_status">노출</label>
                    <select id="cp_status" name="cp_status">
                        <option value="1"<?php echo (int) $v('cp_status', 1) === 1 ? ' selected' : ''; ?>>노출</option>
                        <option value="9"<?php echo (int) $v('cp_status', 1) === 9 ? ' selected' : ''; ?>>비노출</option>
                    </select>
                </div>

                <h2 class="ad-subtitle" style="margin-top:1.5rem;font-size:1.05rem;">외부 판매처</h2>
                <p class="ad-p" style="margin-top:0;">
                    판매처 ID는 필터용 영문·숫자입니다. 비우면 판매처명으로 자동 생성됩니다.
                    <?php if ($offer_buy_col): ?>
                        사용자 박스 시세에는 판매처명·유효한 http(s) URL이 있고 <strong>목록 노출</strong>이 «예»(<code>co_buy_enabled = 1</code>)인 행만 노출됩니다.
                    <?php else: ?>
                        사용자 박스 시세에는 판매처명과 유효한 http(s) URL이 있는 행만 노출됩니다. <code>co_buy_enabled</code> 컬럼을 추가하면 «아니오» 행을 목록에서 뺄 수 있습니다.
                    <?php endif; ?>
                </p>

                <div class="ad-table-wrap" style="overflow-x:auto;">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th style="width:7rem;">판매처 ID</th>
                            <?php if ($offer_co_type_col): ?>
                                <th style="min-width:7rem;">플랫폼·업체</th>
                            <?php endif; ?>
                            <th style="min-width:9rem;">판매처명</th>
                            <th style="min-width:14rem;">상품 URL</th>
                            <th style="width:7rem;">가격(원)</th>
                            <th style="width:7rem;">재고 수량</th>
                            <?php if ($offer_buy_col): ?>
                                <th style="width:9rem;">목록 노출</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($offer_rows as $or): ?>
                            <tr>
                                <td>
                                    <input type="text" name="offer_site_id[]" maxlength="64" class="ad-input-table"
                                           value="<?php echo htmlspecialchars($or['co_site_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="자동">
                                </td>
                                <?php if ($offer_co_type_col): ?>
                                    <td>
                                        <input type="text" name="offer_co_type[]" maxlength="120" class="ad-input-table"
                                               value="<?php echo htmlspecialchars((string) ($or['co_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="예) 쿠팡">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <input type="text" name="offer_site_name[]" maxlength="120" class="ad-input-table"
                                           value="<?php echo htmlspecialchars($or['co_site_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="예) OO카드몰">
                                </td>
                                <td>
                                    <input type="text" name="offer_product_url[]" maxlength="500" class="ad-input-table"
                                           value="<?php echo htmlspecialchars($or['co_product_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="https://">
                                </td>
                                <td>
                                    <input type="number" name="offer_price_won[]" min="0" max="4294967295" step="1" class="ad-input-table"
                                           value="<?php echo $or['co_price_won'] > 0 ? (int) $or['co_price_won'] : ''; ?>"
                                           placeholder="0">
                                </td>
                                <td>
                                    <input type="number" name="offer_stock_qty[]" min="0" max="2147483647" step="1" class="ad-input-table"
                                           value="<?php echo htmlspecialchars($or['co_stock_qty'], ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="비우면 미확인">
                                </td>
                                <?php if ($offer_buy_col): ?>
                                    <td>
                                        <select name="offer_buy_enabled[]" class="ad-input-table">
                                            <option value="1"<?php echo (($or['co_buy_enabled'] ?? '1') === '1') ? ' selected' : ''; ?>>예</option>
                                            <option value="0"<?php echo (($or['co_buy_enabled'] ?? '1') === '0') ? ' selected' : ''; ?>>아니오</option>
                                        </select>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="ad-actions">
                    <a class="ad-btn" href="<?php echo htmlspecialchars($back_href, ENT_QUOTES, 'UTF-8'); ?>">목록</a>
                    <button type="submit" class="ad-btn"><?php echo $is_edit ? '저장' : '등록'; ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
