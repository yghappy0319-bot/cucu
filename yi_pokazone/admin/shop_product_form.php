<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '쇼핑몰 상품 등록·수정';
$ad_topbar = $ad;
$ad_menu   = 'shop_products';
include __DIR__ . '/include/admin_header.php';

$ready   = db_table_exists('tb_shop_product');
$idx     = max(0, (int) ($_GET['idx'] ?? 0));
$is_edit = $idx > 0;
$row     = null;

if ($ready && $is_edit) {
    $rs  = db_query("SELECT * FROM tb_shop_product WHERE sp_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 상품입니다.', '/admin/shop_products.php');
    }
}

$v = function ($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit || !$row) {
        return $default;
    }
    return $row[$field] ?? $default;
};

$back_href = '/admin/shop_products.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_shop_product 테이블이 없습니다.</div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title"><?php echo $is_edit ? '상품 수정' : '상품 등록'; ?></h1>
            <p class="ad-p" style="margin-top:0;">사용자 <a href="/page/shop.php" target="_blank" rel="noopener">쇼핑몰</a>과 동일한 데이터입니다. 외부 구매 링크를 넣으면 상세 대신 해당 URL로 연결됩니다.</p>

            <form class="ad-form" method="post" action="/proc/admin_shop_product_write_proc.php" autocomplete="off">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <?php else: ?>
                    <input type="hidden" name="mode" value="insert">
                <?php endif; ?>

                <div class="ad-field">
                    <label for="sp_name">상품명</label>
                    <input type="text" id="sp_name" name="sp_name" maxlength="200" required
                           value="<?php echo htmlspecialchars((string) $v('sp_name'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="예) 부스터 박스">
                </div>

                <div class="ad-field">
                    <label for="sp_subtitle">부제(한 줄)</label>
                    <input type="text" id="sp_subtitle" name="sp_subtitle" maxlength="300"
                           value="<?php echo htmlspecialchars((string) $v('sp_subtitle'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="목록·상단에 보조 설명">
                </div>

                <div class="ad-field">
                    <label for="sp_summary">요약</label>
                    <input type="text" id="sp_summary" name="sp_summary" maxlength="500"
                           value="<?php echo htmlspecialchars((string) $v('sp_summary'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="목록 카드용 짧은 설명">
                </div>

                <div class="ad-field">
                    <label for="sp_content">상세 본문</label>
                    <textarea id="sp_content" name="sp_content" rows="14"
                              placeholder="줄바꿈 그대로 표시됩니다."><?php echo htmlspecialchars((string) $v('sp_content'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="ad-field">
                    <label for="sp_price">판매가(원)</label>
                    <input type="number" id="sp_price" name="sp_price" min="0" max="2147483647" step="1" required
                           value="<?php echo htmlspecialchars((string) ($is_edit && $row ? (int) $row['sp_price'] : '0'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="ad-field">
                    <label for="sp_original_price">정가(원, 선택)</label>
                    <input type="number" id="sp_original_price" name="sp_original_price" min="0" max="2147483647" step="1"
                           value="<?php
                           $op = $v('sp_original_price');
                           echo $op !== null && $op !== '' && (int) $op > 0
                               ? htmlspecialchars((string) (int) $op, ENT_QUOTES, 'UTF-8')
                               : '';
                           ?>"
                           placeholder="할인 전 가격(없으면 비움)">
                </div>

                <div class="ad-field">
                    <label for="sp_image_path">대표 이미지 경로</label>
                    <input type="text" id="sp_image_path" name="sp_image_path" maxlength="255"
                           value="<?php echo htmlspecialchars((string) $v('sp_image_path'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="예) /uploads/shop/xxx.jpg (웹에서 열리는 경로)">
                </div>

                <div class="ad-field">
                    <label for="sp_link">외부 구매 URL</label>
                    <input type="url" id="sp_link" name="sp_link" maxlength="500"
                           value="<?php echo htmlspecialchars((string) $v('sp_link'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="https://… (비우면 자체 상세 페이지)">
                </div>

                <div class="ad-field">
                    <label>
                        <input type="checkbox" name="sp_shipping_free" value="1"<?php echo (int) $v('sp_shipping_free', 1) === 1 ? ' checked' : ''; ?>>
                        무료배송
                    </label>
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">체크 해제 시 쇼핑몰에 «조건문의»로 표시됩니다.</span>
                </div>

                <div class="ad-field">
                    <label>
                        <input type="checkbox" name="sp_featured" value="1"<?php echo (int) $v('sp_featured', 0) === 1 ? ' checked' : ''; ?>>
                        오늘의 추천 (메인 홈 «오늘의 추천 상품»에 노출)
                    </label>
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">노출 상태일 때만 사용자 화면에 표시됩니다.</span>
                </div>

                <div class="ad-field">
                    <label for="sp_sort">정렬값</label>
                    <input type="number" id="sp_sort" name="sp_sort" step="1"
                           value="<?php echo htmlspecialchars((string) ($is_edit && $row ? (int) $row['sp_sort'] : '0'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">큰 값이 목록에서 앞에 옵니다.</span>
                </div>

                <div class="ad-field">
                    <label for="sp_status">노출</label>
                    <select id="sp_status" name="sp_status">
                        <option value="1"<?php echo (int) $v('sp_status', 1) === 1 ? ' selected' : ''; ?>>노출</option>
                        <option value="9"<?php echo (int) $v('sp_status', 1) === 9 ? ' selected' : ''; ?>>비노출</option>
                    </select>
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
