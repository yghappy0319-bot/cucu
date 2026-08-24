<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '뽑기 상품 등록·수정';
$ad_topbar = $ad;
$ad_menu   = 'draw_products';
include __DIR__ . '/include/admin_header.php';

$ready   = db_table_exists('tb_draw_product');
$idx     = max(0, (int) ($_GET['idx'] ?? 0));
$is_edit = $idx > 0;
$row     = null;

if ($ready && $is_edit) {
    $rs  = db_query("SELECT * FROM tb_draw_product WHERE dp_idx = {$idx} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않는 항목입니다.', '/admin/draw_products.php');
    }
}

$v = function ($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit || !$row) {
        return $default;
    }
    return $row[$field] ?? $default;
};
$pack_types = [
    'expansion' => ['label' => '확장팩', 'count' => 30],
    'enhanced' => ['label' => '강화확장팩', 'count' => 20],
    'highclass' => ['label' => '하이클래스팩', 'count' => 10],
];
$cur_pack_type = (string) $v('dp_pack_type', 'expansion');
if (!isset($pack_types[$cur_pack_type])) {
    $cur_pack_type = 'expansion';
}
$cur_pack_count = (int) $v('dp_pack_count', $pack_types[$cur_pack_type]['count']);
if ($cur_pack_count <= 0) {
    $cur_pack_count = (int) $pack_types[$cur_pack_type]['count'];
}

$back_href = '/admin/draw_products.php';
$cur_img   = $is_edit && $row ? trim((string) ($row['dp_image_path'] ?? '')) : '';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            <?php if (!$ready): ?>
                <div>tb_draw_product 테이블이 없습니다. <code>sql/tb_draw_product.sql</code> 파일을 적용해 주세요.</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ad-card">
            <h1 class="ad-title"><?php echo $is_edit ? '뽑기 상자 수정' : '뽑기 상자 등록'; ?></h1>
            <p class="ad-p" style="margin-top:0;">상자명, 제품번호(예: s4), 팩 종류(확장팩/강화확장팩/하이클래스팩), 대표 이미지를 등록하고 상자 안 카드 이미지를 추가합니다.</p>

            <form class="ad-form" method="post" action="/proc/admin_draw_product_write_proc.php" enctype="multipart/form-data" autocomplete="off">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="idx" value="<?php echo $idx; ?>">
                <?php else: ?>
                    <input type="hidden" name="mode" value="insert">
                <?php endif; ?>

                <div class="ad-field">
                    <label for="dp_name">상자명</label>
                    <input type="text" id="dp_name" name="dp_name" maxlength="200" required
                           value="<?php echo htmlspecialchars((string) $v('dp_name'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="예) sv8a 하이클래스팩">
                </div>

                <div class="ad-field">
                    <label for="dp_code">제품번호</label>
                    <input type="text" id="dp_code" name="dp_code" maxlength="80"
                           value="<?php echo htmlspecialchars((string) $v('dp_code'), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="예) s4">
                </div>

                <div class="ad-field">
                    <label for="dp_pack_type">팩 종류</label>
                    <select id="dp_pack_type" name="dp_pack_type">
                        <?php foreach ($pack_types as $type_key => $meta): ?>
                            <option value="<?php echo htmlspecialchars($type_key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $cur_pack_type === $type_key ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ad-field">
                    <label for="dp_pack_count">팩 수량</label>
                    <input type="number" id="dp_pack_count" name="dp_pack_count" min="1" step="1" readonly
                           value="<?php echo (int) $cur_pack_count; ?>">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">확장팩 30 / 강화확장팩 20 / 하이클래스팩 10으로 자동 설정됩니다.</span>
                </div>

                <div class="ad-field">
                    <label for="dp_image">상자 대표 이미지</label>
                    <?php if ($cur_img !== ''): ?>
                        <div style="margin-bottom:0.5rem;">
                            <img src="<?php echo htmlspecialchars(public_url($cur_img), ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" style="max-width:120px;max-height:120px;object-fit:contain;border-radius:8px;border:1px solid var(--ad-border, #e5e5e5);">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="dp_image" name="dp_image" class="ad-input"
                           accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">jpg · png · gif · webp, 최대 5MB. <?php echo $cur_img !== '' ? '새 파일을 고르면 교체됩니다.' : ''; ?></span>
                    <?php if ($cur_img !== ''): ?>
                        <label style="display:flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-size:14px;cursor:pointer;">
                            <input type="checkbox" name="dp_image_remove" value="1">
                            등록된 이미지 삭제
                        </label>
                    <?php endif; ?>
                </div>

                <div class="ad-field">
                    <label for="dp_sort">정렬값</label>
                    <input type="number" id="dp_sort" name="dp_sort" step="1"
                           value="<?php echo htmlspecialchars((string) ($is_edit && $row ? (int) $row['dp_sort'] : '0'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">큰 값이 목록에서 앞에 옵니다.</span>
                </div>

                <div class="ad-field">
                    <label for="dp_status">노출</label>
                    <select id="dp_status" name="dp_status">
                        <option value="1"<?php echo (int) $v('dp_status', 1) === 1 ? ' selected' : ''; ?>>노출</option>
                        <option value="9"<?php echo (int) $v('dp_status', 1) === 9 ? ' selected' : ''; ?>>비노출</option>
                    </select>
                </div>

                <div class="ad-actions">
                    <a class="ad-btn" href="<?php echo htmlspecialchars($back_href, ENT_QUOTES, 'UTF-8'); ?>">목록</a>
                    <?php if ($is_edit): ?>
                        <a class="ad-btn" href="/admin/draw_product_cards.php?dp_idx=<?php echo $idx; ?>">상자 카드 관리</a>
                    <?php endif; ?>
                    <button type="submit" class="ad-btn"><?php echo $is_edit ? '저장' : '등록'; ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
<script>
(function () {
    var typeEl = document.getElementById('dp_pack_type');
    var countEl = document.getElementById('dp_pack_count');
    if (!typeEl || !countEl) return;
    var map = {
        expansion: 30,
        enhanced: 20,
        highclass: 10
    };
    var sync = function () {
        var v = typeEl.value;
        countEl.value = map[v] || 30;
    };
    typeEl.addEventListener('change', sync);
    sync();
})();
</script>
