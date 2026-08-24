<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/../lib/_upload.php';
require_once __DIR__ . '/lib/_auction.php';

if (!auction_table_ok()) {
    alert_goto('경매 DB 테이블이 없습니다. sql/tb_auction.sql 을 실행한 뒤 다시 시도해 주세요.', '/auction/auction.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($_SERVER['REQUEST_URI']));
}

require_once __DIR__ . '/lib/_member_auction_ban.php';
require_once __DIR__ . '/lib/_member_auction_eligibility.php';
if (member_auction_ban_column_ready() && member_auction_is_banned((int) $me['mb_idx'])) {
    alert_goto(member_auction_ban_user_message(), '/page/mypage.php');
}

$idx     = (int) ($_GET['idx'] ?? 0);
$is_edit = $idx > 0;
$elig    = member_auction_eligibility_create($me);
$can_create_auction = $is_edit || $elig['ok'];

$item_types = [
    'card' => '카드',
    'box'  => '📦 미개봉박스',
];
$grades = ['SAR', 'SR', 'RR', 'R', 'UR', '프로모', '일반'];
$conds = [
    'S' => 'S · 민트 (미개봉/완벽)',
    'A' => 'A · 상급 (거의새것)',
    'B' => 'B · 중급 (사용감있음)',
    'C' => 'C · 하급 (보관용)',
];
$durations = [
    1  => '1일',
    3  => '3일',
    5  => '5일',
    7  => '7일',
    14 => '14일',
];
$bid_steps = [
    100   => '100원',
    500   => '500원',
    1000  => '1,000원',
    5000  => '5,000원',
    10000 => '10,000원',
];

$row    = null;
$images = [];

if ($is_edit) {
    $rs  = db_query("SELECT * FROM tb_auction WHERE au_idx = {$idx} AND au_status = 1 LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않거나 삭제된 경매입니다.', '/auction/auction.php');
    }
    if ((int) $row['mb_idx'] !== (int) $me['mb_idx'] && (int) $me['mb_level'] < 9) {
        alert_goto('수정 권한이 없습니다.', '/auction/auction_view.php?idx=' . $idx);
    }
    if ((int) ($row['au_bid_count'] ?? 0) > 0) {
        alert_goto('입찰이 시작된 경매는 수정할 수 없습니다.', '/auction/auction_view.php?idx=' . $idx);
    }

    $rsi = db_query("SELECT * FROM tb_auction_image WHERE au_idx = {$idx} ORDER BY ai_order ASC, ai_idx ASC");
    while ($img = db_assoc($rsi)) {
        $images[] = $img;
    }
}

$def_item_type = $is_edit
    ? ($row['au_item_type'] ?? 'card')
    : (isset($_GET['item']) && array_key_exists($_GET['item'], $item_types) ? $_GET['item'] : 'card');

$card_kind_labels = [
    'single' => '싱글 카드',
    'graded' => '등급(슬랩) 카드',
];
$def_card_kind = 'single';
if ($is_edit && ($row['au_item_type'] ?? '') === 'card') {
    $k = $row['au_card_kind'] ?? '';
    $def_card_kind = ($k === 'graded' || $k === 'single') ? $k : 'single';
}

$can_delete_auction = $is_edit
    && (int) ($row['au_bid_count'] ?? 0) === 0
    && ((int) $row['mb_idx'] === (int) $me['mb_idx'] || (int) $me['mb_level'] >= 9);
$delete_penalty = auction_delete_point_penalty();
$delete_penalty_mb_idx = $is_edit ? (int) $row['mb_idx'] : 0;
$delete_penalty_point = ($can_delete_auction && db_table_exists('tb_point_log') && $delete_penalty_mb_idx > 0)
    ? (int) db_result('SELECT mb_point FROM tb_member WHERE mb_idx = ' . $delete_penalty_mb_idx . ' LIMIT 1')
    : 0;

$def_duration = 3;
if ($is_edit && !empty($row['au_ends_at']) && !empty($row['au_starts_at'])) {
    $days = (int) round((strtotime($row['au_ends_at']) - strtotime($row['au_starts_at'])) / 86400);
    if (array_key_exists($days, $durations)) {
        $def_duration = $days;
    }
}

$v = static function ($field, $default = '') use ($is_edit, $row) {
    if (!$is_edit) {
        return $default;
    }

    return $row[$field] ?? $default;
};

$content_for_editor = '';
if ($is_edit) {
    $content_for_editor = community_html_apply_public_urls((string) ($row['au_content'] ?? ''));
}

$page_head_extra = <<<'PZHEAD'
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
PZHEAD;

$page_footer_extra = <<<'PZFOOT'
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-ko-KR.min.js"></script>
<script>
(function () {
  var $el = window.jQuery ? window.jQuery('#au_content') : null;
  if (!$el || !$el.length) return;
  $el.summernote({
    lang: 'ko-KR',
    placeholder: '상품 상태, 구성, 배송·직거래 가능 시간 등을 자세히 적어주세요.',
    tabsize: 2,
    height: 360,
    focus: false,
    toolbar: [
      ['style', ['style']],
      ['font', ['bold', 'underline', 'clear']],
      ['fontname', ['fontname']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['height', ['height']],
      ['table', ['table']],
      ['view', ['fullscreen', 'help']]
    ]
  });
})();
</script>
PZFOOT;

$page  = 'auction';
$title = $is_edit ? '경매 수정' : '경매 등록';
$meta_description = '포켓몬 카드·미개봉 박스 경매를 등록하세요.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '경매', 'url' => '/auction/auction.php'],
    ['name' => $is_edit ? '경매 수정' : '경매 등록', 'url' => '/auction/auction_write.php'],
];

include __DIR__ . '/../include/header.php';
?>

<section class="community trade-write auction-write">
    <div class="container">
        <header class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_edit ? '경매 수정' : '경매 등록'; ?></h1>
                <p class="board-desc">경매는 <strong>택배 거래</strong>만 가능합니다. <?php echo $is_edit ? '경매 기간은 등록 후 변경할 수 없습니다.' : '시작가·경매 기간·사진을 정확히 입력해 주세요.'; ?></p>
            </div>
        </header>

        <?php if (!$can_create_auction): ?>
            <div class="auction-prep-banner" role="status">
                <p><strong>경매 등록 조건</strong></p>
                <ul style="margin:10px 0 0;padding-left:1.2em;">
                    <li>
                        휴대폰 인증
                        <?php if ($elig['has_phone']): ?>
                            — 완료
                        <?php else: ?>
                            — 미완료
                            (<a href="/page/member_info.php">회원정보에서 인증</a>)
                        <?php endif; ?>
                    </li>
                    <li>
                        구매확정 완료 거래 <?php echo (int) $elig['required']; ?>건 이상
                        — 현재 <?php echo (int) $elig['completed']; ?>건
                        <?php if ($elig['missing_trades']): ?>
                            (<a href="/trade/trade.php">거래게시판</a>)
                        <?php endif; ?>
                    </li>
                </ul>
                <p style="margin:12px 0 0;">
                    레벨 <?php echo (int) member_auction_eligibility_level_bypass(); ?> 이상은
                    휴대폰 인증·거래 실적 없이 등록할 수 있습니다.
                </p>
            </div>
        <?php else: ?>

        <form class="post-form" method="post" action="/auction/proc/auction_write_proc.php" autocomplete="off" enctype="multipart/form-data" id="auction-form">
            <?php if ($is_edit): ?>
                <input type="hidden" name="idx" value="<?php echo (int) $row['au_idx']; ?>">
                <input type="hidden" name="mode" value="edit">
            <?php else: ?>
                <input type="hidden" name="mode" value="insert">
            <?php endif; ?>

            <div class="field">
                <label>상품 종류</label>
                <div class="radio-card-group" data-item-type-group>
                    <?php foreach ($item_types as $k => $label): ?>
                        <label class="radio-card <?php echo $def_item_type === $k ? 'is-active' : ''; ?>">
                            <input type="radio" name="au_item_type" value="<?php echo $k; ?>"
                                <?php echo $def_item_type === $k ? 'checked' : ''; ?> required>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field">
                <label for="au_title">제목</label>
                <input type="text" id="au_title" name="au_title" maxlength="150" required
                       placeholder="예) [경매] 리자몽 ex SAR · 3일 경매"
                       value="<?php echo htmlspecialchars($v('au_title')); ?>">
            </div>

            <div data-type-card>
                <div class="field">
                    <label>카드 형태</label>
                    <div class="radio-card-group radio-card-group--kind" data-card-kind-group>
                        <?php foreach ($card_kind_labels as $k => $label): ?>
                            <label class="radio-card <?php echo $def_card_kind === $k ? 'is-active' : ''; ?>">
                                <input type="radio" name="au_card_kind" value="<?php echo htmlspecialchars($k); ?>"
                                    <?php echo $def_card_kind === $k ? 'checked' : ''; ?> required>
                                <span><?php echo htmlspecialchars($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field-row field-row-3">
                    <div class="field">
                        <label for="au_card_name_card">상품명 (카드명)</label>
                        <input type="text" id="au_card_name_card" name="au_card_name_card" maxlength="100"
                               placeholder="예) 리자몽 ex"
                               value="<?php echo htmlspecialchars($def_item_type === 'card' ? $v('au_card_name') : ''); ?>">
                    </div>
                    <div class="field">
                        <label for="au_grade">등급 <span class="optional">(선택)</span></label>
                        <select id="au_grade" name="au_grade">
                            <option value="">선택 안함</option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $v('au_grade') === $g ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="au_condition">카드 상태</label>
                        <select id="au_condition" name="au_condition">
                            <?php foreach ($conds as $k => $label): ?>
                                <option value="<?php echo $k; ?>" <?php echo $v('au_condition', 'A') === $k ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="field-row" data-type-box style="display:none">
                <div class="field">
                    <label for="au_card_name_box">상자명 / 상품명</label>
                    <input type="text" id="au_card_name_box" name="au_card_name_box" maxlength="100"
                           placeholder="예) 포켓몬151 부스터박스 (30팩)"
                           value="<?php echo htmlspecialchars($def_item_type === 'box' ? $v('au_card_name') : ''); ?>">
                </div>
                <div class="field">
                    <label for="au_box_qty">수량</label>
                    <input type="number" id="au_box_qty" name="au_box_qty" min="1" max="9999" value="<?php echo (int) $v('au_box_qty', 1); ?>">
                </div>
            </div>

            <div class="field-row field-row-3">
                <div class="field">
                    <label for="au_start_price">시작가 (원)</label>
                    <input type="number" id="au_start_price" name="au_start_price" min="1000" max="999999999" step="1000" required
                           value="<?php echo (int) $v('au_start_price', 10000); ?>">
                </div>
                <div class="field">
                    <label for="au_bid_step">입찰 단위</label>
                    <select id="au_bid_step" name="au_bid_step" required>
                        <?php foreach ($bid_steps as $val => $label): ?>
                            <option value="<?php echo (int) $val; ?>" <?php echo (int) $v('au_bid_step', 1000) === (int) $val ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="au_buy_now_price">즉시구매가 <span class="optional">(선택)</span></label>
                    <input type="number" id="au_buy_now_price" name="au_buy_now_price" min="0" max="999999999" step="1000"
                           placeholder="비우면 미사용"
                           value="<?php echo (int) $v('au_buy_now_price', 0) ?: ''; ?>">
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <?php if ($is_edit): ?>
                    <span class="field-label">경매 기간</span>
                    <div class="auction-period-readonly" role="group" aria-label="경매 기간 (수정 불가)">
                        <p class="auction-period-readonly__range">
                            <strong><?php echo date('Y-m-d H:i', strtotime((string) $row['au_starts_at'])); ?></strong>
                            <span aria-hidden="true"> ~ </span>
                            <strong><?php echo date('Y-m-d H:i', strtotime((string) $row['au_ends_at'])); ?></strong>
                        </p>
                        <p class="help">등록 시 설정된 기간은 수정할 수 없습니다.</p>
                    </div>
                    <?php else: ?>
                    <label for="au_duration">경매 기간</label>
                    <select id="au_duration" name="au_duration" required>
                        <?php foreach ($durations as $days => $label): ?>
                            <option value="<?php echo (int) $days; ?>" <?php echo $def_duration === (int) $days ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?> (등록 시점부터)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help">등록 즉시 경매가 시작됩니다.</p>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label for="au_region">발송 지역 <span class="optional">(선택)</span></label>
                    <input type="text" id="au_region" name="au_region" maxlength="30"
                           placeholder="예) 서울 (택배 발송 기준)"
                           value="<?php echo htmlspecialchars($v('au_region')); ?>">
                    <p class="help">낙찰 후 택배 발송 시 참고할 지역입니다.</p>
                </div>
            </div>

            <div class="field">
                <label>
                    사진 첨부
                    <span class="optional">(최대 8장, 장당 <?php echo (int) max(1, (int) round(TR_UP_MAX_SIZE / 1048576)); ?>MB / 첫 번째가 대표)</span>
                </label>
                <div class="image-manager" data-image-manager>
                    <?php foreach ($images as $i => $img): ?>
                        <div class="image-thumb <?php echo $i === 0 ? 'is-cover' : ''; ?>"
                             draggable="true"
                             data-img-ref="existing:<?php echo (int) $img['ai_idx']; ?>">
                            <img src="<?php echo htmlspecialchars(public_url($img['ai_path'])); ?>" alt="">
                            <span class="image-cover-badge">대표</span>
                            <div class="image-thumb-actions">
                                <button type="button" class="image-btn image-set-cover" title="대표이미지">★</button>
                                <button type="button" class="image-btn image-remove-btn" title="삭제">×</button>
                            </div>
                            <input type="hidden" name="remove_img[]" value="<?php echo (int) $img['ai_idx']; ?>" disabled>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="image-uploader">
                    <label class="image-uploader-btn">
                        <input type="file" name="au_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple id="au_images">
                        <span>📷 사진 추가</span>
                    </label>
                    <span class="image-counter" data-image-counter>0 / 8</span>
                </div>
            </div>

            <div class="field">
                <label for="au_content">상세 설명</label>
                <textarea id="au_content" name="au_content" rows="12"><?php echo htmlspecialchars($content_for_editor, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="trade-disclaimer" role="note">
                    <strong class="trade-disclaimer__title">경매 면책 안내</strong>
                    <p class="trade-disclaimer__text">
                        경매는 <strong>택배 거래만</strong> 가능합니다. 포카존은 중개 플랫폼이며, 입찰·낙찰·결제·배송 과정에서 발생하는 분쟁에 대해 <em>법적 책임을 지지 않습니다.</em>
                    </p>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?php echo $is_edit ? '/auction/auction_view.php?idx=' . (int) $row['au_idx'] : '/auction/auction.php'; ?>"
                   class="btn btn-outline">취소</a>
                <button type="submit" class="btn btn-primary"><?php echo $is_edit ? '수정하기' : '경매 등록'; ?></button>
            </div>
        </form>

        <?php if ($can_delete_auction): ?>
            <aside class="auction-delete-panel" aria-labelledby="auction-delete-title">
                <h2 id="auction-delete-title" class="auction-delete-panel__title">경매 삭제</h2>
                <p class="auction-delete-panel__desc">
                    삭제 시 복구할 수 없으며, 등록자에게 <strong><?php echo number_format($delete_penalty); ?>P</strong> 패널티가 차감됩니다.
                    <?php if (db_table_exists('tb_point_log')): ?>
                        <?php if ((int) $row['mb_idx'] !== (int) $me['mb_idx']): ?>
                            등록자 보유 포인트: <strong><?php echo number_format($delete_penalty_point); ?>P</strong>
                        <?php else: ?>
                            현재 보유 포인트: <strong><?php echo number_format($delete_penalty_point); ?>P</strong>
                        <?php endif; ?>
                        <?php if ($delete_penalty_point < $delete_penalty): ?>
                            <span class="auction-delete-panel__warn">(포인트가 부족하면 삭제할 수 없습니다)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <form action="/auction/proc/auction_delete_proc.php" method="post" class="auction-delete-form"
                      onsubmit="return confirm('이 경매를 삭제하시겠습니까?\n\n· 삭제 후 복구할 수 없습니다.\n· 등록자에게 <?php echo number_format($delete_penalty); ?>P가 차감됩니다.');">
                    <input type="hidden" name="idx" value="<?php echo (int) $row['au_idx']; ?>">
                    <button type="submit" class="btn btn-danger">경매 삭제</button>
                </form>
            </aside>
        <?php endif; ?>

        <?php endif; /* $can_create_auction */ ?>
    </div>
</section>

<?php if ($can_create_auction): ?>
<script>
(function () {
    'use strict';
    const radios = document.querySelectorAll('[data-item-type-group] input[type="radio"]');
    const cardFields = document.querySelector('[data-type-card]');
    const boxFields = document.querySelector('[data-type-box]');
    const cardName = document.getElementById('au_card_name_card');
    const boxName = document.getElementById('au_card_name_box');
    const gradeSel = document.getElementById('au_grade');
    const condSel = document.getElementById('au_condition');
    const kindRadios = document.querySelectorAll('[data-card-kind-group] input[type="radio"]');

    function syncCardKindActive() {
        document.querySelectorAll('[data-card-kind-group] .radio-card').forEach(el => el.classList.remove('is-active'));
        const kr = document.querySelector('[data-card-kind-group] input[type="radio"]:checked');
        if (kr) kr.closest('.radio-card')?.classList.add('is-active');
    }

    function applyType(v) {
        if (v === 'box') {
            cardFields.style.display = 'none';
            boxFields.style.display = '';
            if (cardName) { cardName.required = false; cardName.disabled = true; }
            if (boxName) { boxName.required = true; boxName.disabled = false; }
            if (gradeSel) gradeSel.disabled = true;
            if (condSel) condSel.disabled = true;
            kindRadios.forEach(r => { r.required = false; });
        } else {
            cardFields.style.display = '';
            boxFields.style.display = 'none';
            if (cardName) { cardName.required = true; cardName.disabled = false; }
            if (boxName) { boxName.required = false; boxName.disabled = true; }
            if (gradeSel) gradeSel.disabled = false;
            if (condSel) condSel.disabled = false;
            kindRadios.forEach(r => { r.required = true; });
        }
        document.querySelectorAll('[data-item-type-group] .radio-card').forEach(l => l.classList.remove('is-active'));
        const checked = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
        if (checked) checked.closest('.radio-card')?.classList.add('is-active');
        syncCardKindActive();
    }

    kindRadios.forEach(r => r.addEventListener('change', syncCardKindActive));
    radios.forEach(r => r.addEventListener('change', e => applyType(e.target.value)));
    applyType(document.querySelector('[data-item-type-group] input[type="radio"]:checked')?.value || 'card');

    const MAX_IMG = 8;
    const MAX_IMG_BYTES = <?php echo (int) TR_UP_MAX_SIZE; ?>;
    const MAX_IMG_MB = <?php echo (int) max(1, (int) round(TR_UP_MAX_SIZE / 1048576)); ?>;
    const manager = document.querySelector('[data-image-manager]');
    const fileInput = document.getElementById('au_images');
    const counter = document.querySelector('[data-image-counter]');
    const form = document.getElementById('auction-form');
    let newFiles = [];

    function updateCover() {
        const thumbs = manager.querySelectorAll('.image-thumb:not(.is-removing)');
        manager.querySelectorAll('.image-thumb').forEach(t => t.classList.remove('is-cover'));
        if (thumbs.length) thumbs[0].classList.add('is-cover');
    }
    function updateCounter() {
        const count = manager.querySelectorAll('.image-thumb:not(.is-removing)').length;
        counter.textContent = count + ' / ' + MAX_IMG;
    }
    function syncFileInput() {
        try {
            const dt = new DataTransfer();
            newFiles.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        } catch (e) {}
    }
    function attachThumbEvents(thumb) {
        let dragEl = null;
        thumb.addEventListener('dragstart', function (e) {
            dragEl = this;
            this.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        thumb.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragEl || dragEl === this) return;
            const rect = this.getBoundingClientRect();
            manager.insertBefore(dragEl, (e.clientX - rect.left) < rect.width / 2 ? this : this.nextSibling);
        });
        thumb.addEventListener('drop', e => e.preventDefault());
        thumb.addEventListener('dragend', function () {
            this.classList.remove('is-dragging');
            dragEl = null;
            updateCover();
        });
        thumb.querySelector('.image-set-cover')?.addEventListener('click', e => {
            e.preventDefault();
            manager.insertBefore(thumb, manager.firstChild);
            updateCover();
        });
        thumb.querySelector('.image-remove-btn')?.addEventListener('click', e => {
            e.preventDefault();
            const ref = thumb.dataset.imgRef || '';
            if (ref.startsWith('existing:')) {
                thumb.classList.toggle('is-removing');
                const hid = thumb.querySelector('input[name="remove_img[]"]');
                if (hid) hid.disabled = !thumb.classList.contains('is-removing');
            } else if (ref.startsWith('new:')) {
                const newIdx = parseInt(ref.split(':')[1], 10);
                newFiles.splice(newIdx, 1);
                thumb.remove();
                [...manager.querySelectorAll('[data-img-ref^="new:"]')].forEach((t, i) => { t.dataset.imgRef = 'new:' + i; });
                syncFileInput();
            }
            updateCover();
            updateCounter();
        });
    }
    manager.querySelectorAll('.image-thumb').forEach(attachThumbEvents);
    updateCover();
    updateCounter();

    fileInput?.addEventListener('change', () => {
        const picked = Array.from(fileInput.files || []);
        const remaining = MAX_IMG - manager.querySelectorAll('.image-thumb:not(.is-removing)').length;
        if (picked.length > remaining) {
            alert('최대 ' + MAX_IMG + '장까지 업로드 가능합니다.');
            fileInput.value = '';
            return;
        }
        for (const f of picked) {
            if (f.size > MAX_IMG_BYTES) {
                alert('"' + f.name + '" 파일이 ' + MAX_IMG_MB + 'MB를 초과합니다.');
                fileInput.value = '';
                return;
            }
        }
        picked.forEach(f => {
            const newIdx = newFiles.length;
            newFiles.push(f);
            const thumb = document.createElement('div');
            thumb.className = 'image-thumb';
            thumb.draggable = true;
            thumb.dataset.imgRef = 'new:' + newIdx;
            thumb.innerHTML = '<img alt=""><span class="image-cover-badge">대표</span><span class="image-new-badge">NEW</span><div class="image-thumb-actions"><button type="button" class="image-btn image-set-cover">★</button><button type="button" class="image-btn image-remove-btn">×</button></div>';
            const reader = new FileReader();
            reader.onload = e => { thumb.querySelector('img').src = e.target.result; };
            reader.readAsDataURL(f);
            manager.appendChild(thumb);
            attachThumbEvents(thumb);
        });
        syncFileInput();
        updateCover();
        updateCounter();
    });

    form?.addEventListener('submit', function (e) {
        var $jq = window.jQuery;
        var $content = $jq ? $jq('#au_content') : null;
        if ($content && $content.length && typeof $content.summernote === 'function') {
            $content.val($content.summernote('code'));
            if ($content.summernote('isEmpty')) {
                e.preventDefault();
                alert('내용을 입력해주세요.');
                $content.summernote('focus');
                return;
            }
        }

        form.querySelectorAll('input[name="image_order[]"]').forEach(el => el.remove());
        manager.querySelectorAll('.image-thumb').forEach(t => {
            if (t.classList.contains('is-removing')) return;
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'image_order[]';
            inp.value = t.dataset.imgRef;
            form.appendChild(inp);
        });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>
