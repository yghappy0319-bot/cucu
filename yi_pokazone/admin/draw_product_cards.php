<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/draw_cards.php';

$title     = '상자 카드 관리';
$ad_topbar = $ad;
$ad_menu   = 'draw_products';
include __DIR__ . '/include/admin_header.php';

$ready_box  = db_table_exists('tb_draw_product');
$ready_card = db_table_exists('tb_draw_product_card');
$dp_idx     = max(0, (int) ($_GET['dp_idx'] ?? 0));
$grade_list = draw_card_grade_list();
$has_grade_col = false;

if ($ready_card) {
    $col_rs = db_query("SHOW COLUMNS FROM tb_draw_product_card LIKE 'dpc_grade'");
    $has_grade_col = (bool) db_assoc($col_rs);
}

$box = null;
$cards = [];
if ($ready_box && $ready_card && $dp_idx > 0) {
    $box = db_assoc(db_query("SELECT dp_idx, dp_name, dp_code FROM tb_draw_product WHERE dp_idx = {$dp_idx} LIMIT 1"));
    if ($box) {
        $grade_sel = $has_grade_col ? ', dpc_grade' : '';
        $rs = db_query("
            SELECT dpc_idx, dpc_orig_name, dpc_image_path, dpc_sort, dpc_created_at{$grade_sel}
            FROM tb_draw_product_card
            WHERE dp_idx = {$dp_idx}
            ORDER BY dpc_orig_name ASC, dpc_idx ASC
        ");
        while ($r = db_assoc($rs)) {
            $cards[] = $r;
        }
    }
}
?>

<div class="ad-page">
    <?php if (!$ready_box || !$ready_card): ?>
        <div class="ad-alert ad-alert--error">
            <?php if (!$ready_box): ?>
                <div>tb_draw_product 테이블이 없습니다. <code>sql/tb_draw_product.sql</code> 파일을 적용해 주세요.</div>
            <?php endif; ?>
            <?php if (!$ready_card): ?>
                <div>tb_draw_product_card 테이블이 없습니다. <code>sql/tb_draw_product_card.sql</code> 파일을 적용해 주세요.</div>
            <?php endif; ?>
        </div>
    <?php elseif (!$box): ?>
        <div class="ad-alert ad-alert--error">존재하지 않는 상자입니다.</div>
        <div class="ad-actions">
            <a class="ad-btn" href="/admin/draw_products.php">목록으로</a>
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;flex:1;min-width:8rem;">상자 카드 관리</h1>
                <a class="ad-btn" href="/admin/draw_products.php">상자 목록</a>
                <a class="ad-btn" href="/admin/draw_product_form.php?idx=<?php echo (int) $box['dp_idx']; ?>">상자 수정</a>
            </div>

            <p class="ad-p" style="margin-top:0;">
                상자명: <strong><?php echo htmlspecialchars((string) ($box['dp_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                / 제품번호: <?php echo htmlspecialchars((string) ($box['dp_code'] ?? ('#' . (int) $box['dp_idx'])), ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <?php if (!$has_grade_col): ?>
                <div class="ad-alert ad-alert--error" style="margin-bottom:1rem;">
                    카드 등급 컬럼이 없습니다. <code>sql/tb_draw_product_card_grade.sql</code> 을 DB에 적용해 주세요.
                </div>
            <?php endif; ?>

            <form class="ad-form" method="post" action="/proc/admin_draw_product_card_write_proc.php" enctype="multipart/form-data">
                <input type="hidden" name="dp_idx" value="<?php echo (int) $box['dp_idx']; ?>">

                <div class="ad-field">
                    <label for="dpc_images">카드 이미지 추가</label>
                    <div id="dpcInputList" style="display:grid;gap:0.5rem;">
                        <input type="file" id="dpc_images" name="dpc_images[]" class="ad-input" multiple
                               accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
                    </div>
                    <button type="button" id="dpcAddInputBtn" class="ad-btn" style="margin-top:0.5rem;">파일칸 추가</button>
                    <span class="ad-muted" style="display:block;font-size:13px;margin-top:0.25rem;">여러 장을 한 번에 추가할 수 있습니다. 브라우저에서 다중 선택이 안 되면 파일칸 추가를 눌러 여러 번 선택해 주세요. jpg · png · gif · webp, 장당 최대 5MB.</span>
                </div>

                <div class="ad-field">
                    <label>등록된 카드</label>
                    <?php if ($has_grade_col && !empty($cards)): ?>
                        <span class="ad-muted" style="display:block;font-size:13px;margin:0 0 0.75rem;">등급은 카드마다 하나만 선택됩니다. 선택 시 바로 저장됩니다.</span>
                    <?php endif; ?>
                    <?php if (empty($cards)): ?>
                        <div class="ad-muted">아직 등록된 카드가 없습니다.</div>
                    <?php else: ?>
                        <div class="dpc-card-grid">
                            <?php foreach ($cards as $cr):
                                $dpc_idx = (int) ($cr['dpc_idx'] ?? 0);
                                $img = trim((string) ($cr['dpc_image_path'] ?? ''));
                                $orig_name = trim((string) ($cr['dpc_orig_name'] ?? ''));
                                $current_grade = $has_grade_col
                                    ? draw_card_grade_normalize($cr['dpc_grade'] ?? '')
                                    : '';
                                if ($img === '') {
                                    continue;
                                }
                                ?>
                                <div class="dpc-card-item" data-dpc-idx="<?php echo $dpc_idx; ?>">
                                    <?php $img_url = public_url($img); ?>
                                    <div class="dpc-thumb-box">
                                        <div class="dpc-thumb-wrap">
                                            <img src="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo htmlspecialchars($orig_name !== '' ? $orig_name : '카드 이미지', ENT_QUOTES, 'UTF-8'); ?>"
                                                 class="dpc-card-thumb"
                                                 loading="lazy">
                                        </div>
                                        <div class="dpc-thumb-preview" aria-hidden="true">
                                            <img src="<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt=""
                                                 loading="lazy">
                                        </div>
                                    </div>
                                    <div class="ad-muted dpc-card-name">
                                        <?php echo htmlspecialchars($orig_name !== '' ? $orig_name : basename($img), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>

                                    <?php if ($has_grade_col): ?>
                                        <div class="dpc-grade-row" role="group" aria-label="카드 등급">
                                            <?php foreach ($grade_list as $grade): ?>
                                                <label class="dpc-grade-chip">
                                                    <input type="checkbox"
                                                           class="dpc-grade-cb"
                                                           value="<?php echo htmlspecialchars($grade, ENT_QUOTES, 'UTF-8'); ?>"
                                                           <?php echo $current_grade === $grade ? 'checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($grade, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="dpc-grade-status ad-muted" aria-live="polite"></div>
                                    <?php endif; ?>

                                    <label class="dpc-remove-row">
                                        <input type="checkbox" name="dpc_remove_ids[]" value="<?php echo $dpc_idx; ?>">
                                        삭제
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ad-actions">
                    <button type="submit" class="ad-btn">저장</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
.dpc-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}
.dpc-card-item {
    border: 1px solid var(--ad-border, #e5e5e5);
    border-radius: 10px;
    padding: 0.5rem;
    background: #fff;
}
.dpc-thumb-box {
    position: relative;
}
.dpc-thumb-wrap {
    border-radius: 8px;
    overflow: hidden;
    background: #f0f4fa;
    cursor: zoom-in;
}
.dpc-card-thumb {
    width: 100%;
    height: 150px;
    object-fit: contain;
    display: block;
    transition: transform 0.2s ease;
}
.dpc-thumb-wrap:hover .dpc-card-thumb {
    transform: scale(1.06);
}
.dpc-thumb-preview {
    display: none;
    position: absolute;
    left: 50%;
    bottom: calc(100% + 10px);
    transform: translateX(-50%);
    z-index: 30;
    width: min(300px, 88vw);
    max-height: min(420px, 70vh);
    padding: 8px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #c4d4f1;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.28);
    pointer-events: none;
}
.dpc-thumb-preview img {
    width: 100%;
    height: auto;
    max-height: min(400px, 68vh);
    object-fit: contain;
    display: block;
    border-radius: 8px;
}
.dpc-thumb-box:hover .dpc-thumb-preview,
.dpc-thumb-box:focus-within .dpc-thumb-preview {
    display: block;
}
.dpc-thumb-box.is-preview-below .dpc-thumb-preview {
    bottom: auto;
    top: calc(100% + 10px);
}
.dpc-card-name {
    margin-top: 0.4rem;
    font-size: 12px;
    line-height: 1.3;
    word-break: break-all;
}
.dpc-grade-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.5rem;
}
.dpc-grade-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
}
.dpc-grade-chip input {
    margin: 0;
    accent-color: #2a4a85;
}
.dpc-grade-chip span {
    padding: 0.15rem 0.35rem;
    border-radius: 6px;
    border: 1px solid #d7e1f4;
    background: #f5f8ff;
}
.dpc-grade-chip input:checked + span {
    background: #2a4a85;
    border-color: #2a4a85;
    color: #fff;
}
.dpc-grade-status {
    min-height: 1rem;
    margin-top: 0.25rem;
    font-size: 11px;
}
.dpc-grade-status.is-ok { color: #0d7a3f; }
.dpc-grade-status.is-err { color: #b42318; }
.dpc-remove-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.45rem;
    font-size: 13px;
    cursor: pointer;
}
</style>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
<script>
(function () {
    var list = document.getElementById('dpcInputList');
    var addBtn = document.getElementById('dpcAddInputBtn');
    if (list && addBtn) {
        addBtn.addEventListener('click', function () {
            var input = document.createElement('input');
            input.type = 'file';
            input.name = 'dpc_images[]';
            input.className = 'ad-input';
            input.setAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp');
            list.appendChild(input);
        });
    }

    document.querySelectorAll('.dpc-thumb-box').forEach(function (box) {
        var placePreview = function () {
            var rect = box.getBoundingClientRect();
            box.classList.toggle('is-preview-below', rect.top < 220);
        };
        box.addEventListener('mouseenter', placePreview);
        box.addEventListener('focusin', placePreview);
    });

    var dpIdx = <?php echo (int) ($box['dp_idx'] ?? 0); ?>;
    var gradeUrl = '/proc/admin_draw_product_card_grade_proc.php';
    var hasGrade = <?php echo $has_grade_col ? 'true' : 'false'; ?>;
    if (!hasGrade || dpIdx < 1) return;

    document.querySelectorAll('.dpc-card-item').forEach(function (item) {
        var dpcIdx = parseInt(item.getAttribute('data-dpc-idx') || '0', 10);
        if (dpcIdx < 1) return;

        var statusEl = item.querySelector('.dpc-grade-status');
        var gradeCbs = item.querySelectorAll('.dpc-grade-cb');
        if (!gradeCbs.length) return;

        var setStatus = function (text, type) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.classList.remove('is-ok', 'is-err');
            if (type) statusEl.classList.add(type);
        };

        var saveGrade = function (grade) {
            setStatus('저장 중…', '');
            var body = new URLSearchParams();
            body.set('dp_idx', String(dpIdx));
            body.set('dpc_idx', String(dpcIdx));
            body.set('dpc_grade', grade);

            fetch(gradeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: body.toString()
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { res: res, data: data };
                    });
                })
                .then(function (payload) {
                    if (!payload.res.ok || !payload.data || !payload.data.ok) {
                        throw new Error((payload.data && payload.data.message) ? payload.data.message : '저장 실패');
                    }
                    setStatus('등급 저장됨', 'is-ok');
                    window.setTimeout(function () {
                        if (statusEl && statusEl.textContent === '등급 저장됨') {
                            setStatus('', '');
                        }
                    }, 1200);
                })
                .catch(function (err) {
                    setStatus(err.message || '저장 실패', 'is-err');
                });
        };

        gradeCbs.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.checked) {
                    gradeCbs.forEach(function (other) {
                        if (other !== cb) other.checked = false;
                    });
                    saveGrade(cb.value);
                    return;
                }

                var anyChecked = false;
                gradeCbs.forEach(function (other) {
                    if (other.checked) anyChecked = true;
                });
                if (!anyChecked) {
                    saveGrade('');
                }
            });
        });
    });
})();
</script>
