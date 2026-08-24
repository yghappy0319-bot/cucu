<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/../lib/_upload.php';
require_once __DIR__ . '/lib/_member_trade_sell_suspend.php';
require_once __DIR__ . '/lib/_trade_payment.php';
require_once __DIR__ . '/lib/_trade_write_restore.php';

$me = login_member();
$ad = login_admin();
$from_admin = (isset($_GET['from']) && (string) $_GET['from'] === 'admin')
    || (isset($_POST['from']) && (string) $_POST['from'] === 'admin');

if (isset($_GET['discard']) && (string) $_GET['discard'] === '1') {
    unset($_SESSION['pz_trade_write_old'], $_SESSION['pz_trade_write_err']);
    $next = (string) ($_GET['next'] ?? '/trade/trade.php');
    if ($next === '' || $next[0] !== '/' || strpos($next, '//') !== false
        || strpos($next, '\\') !== false || preg_match('/[\r\n]/', $next)) {
        $next = '/trade/trade.php';
    }
    header('Location: ' . $next, true, 302);
    exit;
}

$idx     = (int) ($_GET['idx'] ?? 0);
$is_edit = $idx > 0;
$is_admin_edit = false;
$row     = null;
$images  = [];

if (!$me && !($ad && $is_edit)) {
    if ($ad && !$is_edit) {
        alert_goto('관리자 신규 등록은 지원하지 않습니다. 목록에서 수정해 주세요.', '/trade/admin/trades.php');
    }
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($_SERVER['REQUEST_URI']));
}

$item_types = [
    'card' => '카드',
    'box'  => '📦 미개봉박스',
];
$types = [
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];
$grades = ['SAR', 'SR', 'RR', 'R', 'UR', '프로모', '일반'];
$conds = [
    'S' => 'S · 민트 (미개봉/완벽)',
    'A' => 'A · 상급 (거의새것)',
    'B' => 'B · 중급 (사용감있음)',
    'C' => 'C · 하급 (보관용)',
];
$languages = [
    'ko'    => '한글판',
    'ja'    => '일본판',
    'en'    => '영문판',
    'other' => '기타',
];
$grading_companies = [
    'PSA'  => 'PSA',
    'BGS'  => 'BGS',
    'BRG'  => 'BRG',
    'CGC'  => 'CGC',
    'SGC'  => 'SGC',
    'ACE'  => 'ACE',
    '기타' => '기타',
];
$grading_scores = ['10', '9.5', '9', '8.5', '8', '7.5', '7', '6', '5', '4', '3', '2', '1'];
$methods = [
    'both'     => '직거래 / 택배 둘다 가능',
    'direct'   => '직거래만',
    'delivery' => '택배만',
];

$trade_sell_suspend_ready = $me ? member_trade_sale_cancel_penalty_column_ready() : false;
$trade_sell_suspend_status  = ($me && $trade_sell_suspend_ready)
    ? member_trade_sale_cancel_penalty_status((int) $me['mb_idx'])
    : ['count' => 0, 'is_suspended' => false, 'suspended_until' => null, 'memo' => null];
$requested_type = isset($_GET['type']) ? trim((string) $_GET['type']) : 'sell';
if ($me && !$is_edit && $trade_sell_suspend_status['is_suspended'] && $requested_type === 'sell') {
    alert_goto(
        member_trade_sell_suspend_user_message($trade_sell_suspend_status['suspended_until']),
        '/trade/trade.php'
    );
}

if ($is_edit) {
    $status_sql = $ad ? '' : ' AND tr_status = 1';
    $rs  = db_query("SELECT * FROM tb_trade WHERE tr_idx = {$idx}{$status_sql} LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto(
            '존재하지 않거나 삭제된 거래글입니다.',
            $ad ? '/trade/admin/trades.php' : '/trade/trade.php'
        );
    }

    $is_owner = $me && (int) $row['mb_idx'] === (int) $me['mb_idx'];
    $is_staff_member = $me && (int) $me['mb_level'] >= 9;
    $is_admin_edit = (bool) $ad;
    if (!$is_owner && !$is_staff_member && !$is_admin_edit) {
        alert_goto('수정 권한이 없습니다.', '/trade/trade_view.php?idx=' . $idx);
    }
    if ($is_admin_edit && !$is_owner && !$is_staff_member) {
        $from_admin = true;
    }

    $rsi = db_query("SELECT * FROM tb_trade_image WHERE tr_idx = {$idx} ORDER BY ti_order ASC, ti_idx ASC");
    while ($img = db_assoc($rsi)) {
        $images[] = $img;
    }
}

// 등록/수정 실패·작성 중 이탈 시 직전 입력값 복원 (파일 첨부는 브라우저 정책상 복원 불가)
$old = null;
$restore_key = $is_edit ? ('edit:' . $idx) : 'insert';
if (!empty($_SESSION['pz_trade_write_old']) && is_array($_SESSION['pz_trade_write_old'])) {
    $sess_old = $_SESSION['pz_trade_write_old'];
    if (($sess_old['_key'] ?? '') === $restore_key && trade_write_old_is_useful($sess_old)) {
        $old = $sess_old;
    }
}
$trade_write_restored = $old !== null;

$def_item_type = $is_edit
    ? $row['tr_item_type']
    : (isset($_GET['item']) && array_key_exists($_GET['item'], $item_types) ? $_GET['item'] : 'card');
$def_type = $is_edit
    ? $row['tr_type']
    : (isset($_GET['type']) && array_key_exists($_GET['type'], $types) ? $_GET['type'] : 'sell');

if ($old !== null) {
    if (isset($old['tr_item_type']) && array_key_exists($old['tr_item_type'], $item_types)) {
        $def_item_type = $old['tr_item_type'];
    }
    if (isset($old['tr_type']) && array_key_exists($old['tr_type'], $types)) {
        $def_type = $old['tr_type'];
    }
}

$card_kind_labels = [
    'single' => '싱글 카드',
    'graded' => '등급(슬랩) 카드',
];
$def_card_kind = 'single';
if ($is_edit && ($row['tr_item_type'] ?? '') === 'card') {
    $k = $row['tr_card_kind'] ?? '';
    $def_card_kind = ($k === 'graded' || $k === 'single') ? $k : 'single';
}
if ($old !== null && isset($old['tr_card_kind'])) {
    $k = $old['tr_card_kind'];
    $def_card_kind = ($k === 'graded' || $k === 'single') ? $k : $def_card_kind;
}

// 값 우선순위: 직전 입력(old) > 수정 대상(row) > 기본값
$v = function($field, $default = '') use ($is_edit, $row, $old) {
    if ($old !== null && array_key_exists($field, $old)) {
        return $old[$field];
    }
    if (!$is_edit) return $default;
    return $row[$field] ?? $default;
};

$content_for_editor = '';
if ($is_edit) {
    $content_for_editor = community_html_apply_public_urls((string)($row['tr_content'] ?? ''));
}
if ($old !== null && isset($old['tr_content'])) {
    $content_for_editor = community_sanitize_html((string) $old['tr_content']);
}
$trade_write_restored = $old !== null;
$trade_write_err = '';
if (!empty($_SESSION['pz_trade_write_err'])) {
    $trade_write_err = (string) $_SESSION['pz_trade_write_err'];
    unset($_SESSION['pz_trade_write_err']);
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
  var $el = window.jQuery ? window.jQuery('#tr_content') : null;
  if ($el && $el.length) {
  $el.summernote({
    lang: 'ko-KR',
    placeholder: '상품 상태, 구매시기, 거래 가능 시간 등 자세히 적어주세요.',
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
    ],
    callbacks: {
      onChange: function () {
        if (typeof window.pzTradeWriteSaveDraft === 'function') {
          window.pzTradeWriteSaveDraft();
        }
      }
    }
  });
  }
  if (typeof window.pzTradeWriteRestore === 'function') {
    window.pzTradeWriteRestore();
  }
})();
</script>
PZFOOT;

$page  = 'trade';
$title = $is_edit ? '거래글 수정' : '거래글 등록';
$meta_description = '포켓몬 카드 거래글을 안전하게 등록하세요. 카드와 미개봉 박스 모두 판매/구매/교환 가능합니다.';
$meta_noindex = true; // 작성 페이지는 색인 제외
$admin_cancel_href = '/trade/admin/trades.php';
$user_cancel_href = $is_edit ? '/trade/trade_view.php?idx=' . (int) $row['tr_idx'] : '/trade/trade.php';
$cancel_target = ($from_admin || $is_admin_edit) ? $admin_cancel_href : $user_cancel_href;
$cancel_href = '/trade/trade_write.php?discard=1&next=' . rawurlencode($cancel_target);
include __DIR__ . '/../include/header.php';
?>

<section class="community trade-write">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_edit ? '거래글 수정' : '거래글 등록'; ?></h1>
                <p class="board-desc">정확한 상품 정보와 사진을 함께 올리면 거래가 훨씬 빨라져요.</p>
            </div>
        </div>

        <?php if ($from_admin || $is_admin_edit): ?>
            <div class="trade-write-notice" role="status" style="border-color:#93c5fd;background:#eff6ff;">
                <strong>관리자 수정 모드</strong>
                <p>
                    백오피스에서 거래글을 수정 중입니다.
                    작성자 계정 소유권은 바뀌지 않으며, 저장 후
                    <a href="/trade/admin/trades.php">거래글관리</a>로 돌아갈 수 있습니다.
                    <?php if ($row && (int) ($row['tr_status'] ?? 1) === 9): ?>
                        <br>현재 이 글은 <strong>숨김</strong> 상태입니다.
                    <?php elseif ($row && function_exists('trade_is_held') && trade_is_held($row)): ?>
                        <br>현재 이 글은 <strong>게시중지</strong> 상태입니다.
                        <?php if (trim((string) ($row['tr_hold_reason'] ?? '')) !== ''): ?>
                            사유: <?php echo htmlspecialchars((string) $row['tr_hold_reason'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="trade-write-notice" role="note">
            <strong>가품·가짜 카드 등록 금지</strong>
            <p>
                <strong>가품·가짜 포켓몬 카드</strong>는 거래글 등록이 <strong>금지</strong>됩니다.
                진위가 확실하지 않다면 먼저
                <a href="/page/community.php?cat=question">커뮤니티 「질문」</a>에
                남겨 확인을 받은 뒤 등록해 주세요.
            </p>
        </div>

        <div class="trade-write-notice trade-write-notice--restrict" role="note">
            <strong>포카존 내 거래만 허용 · 외부 소통·연락처 공유 금지</strong>
            <p>
                카카오톡 등 <strong>외부 메신저·플랫폼으로의 소통</strong>과
                전화번호·아이디 등 <strong>개인연락처 공유는 금지</strong>됩니다.
                거래는 <strong>포카존 안에서만</strong> 진행해야 합니다.
            </p>
            <p>
                이 내용은 <strong>거래제한(게시중지) 사유</strong>에 해당합니다.
                타 플랫폼으로 거래를 유도하다 적발되면
                <strong>30일간 포카존 서비스 이용이 제한</strong>됩니다.
            </p>
        </div>

        <div class="trade-write-notice trade-write-notice--restore" id="trade-write-restore-notice" role="status"<?php echo !empty($trade_write_restored) ? '' : ' hidden'; ?>>
            <strong>입력 내용 복원</strong>
            <p>이전에 작성하던 내용을 다시 채워 두었습니다. 사진은 보안상 다시 첨부해 주세요.</p>
            <?php if ($trade_write_err !== ''): ?>
                <p><?php echo htmlspecialchars($trade_write_err, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <form class="post-form" method="post" action="/trade/proc/trade_write_proc.php" autocomplete="off" enctype="multipart/form-data" id="trade-form">
            <?php if ($from_admin || $is_admin_edit): ?>
                <input type="hidden" name="from" value="admin">
            <?php endif; ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="idx" value="<?php echo (int)$row['tr_idx']; ?>">
                <input type="hidden" name="mode" value="edit">
            <?php else: ?>
                <input type="hidden" name="mode" value="insert">
            <?php endif; ?>

            <div class="field">
                <label>상품 종류</label>
                <div class="radio-card-group" data-item-type-group>
                    <?php foreach ($item_types as $k => $label): ?>
                        <label class="radio-card <?php echo $def_item_type === $k ? 'is-active' : ''; ?>">
                            <input type="radio" name="tr_item_type" value="<?php echo $k; ?>"
                                <?php echo $def_item_type === $k ? 'checked' : ''; ?> required>
                            <span><?php echo $label; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="tr_type">거래 유형</label>
                    <select id="tr_type" name="tr_type" required>
                        <?php foreach ($types as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $def_type === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="tr_method">거래 방식</label>
                    <select id="tr_method" name="tr_method" required>
                        <?php foreach ($methods as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $v('tr_method', 'both') === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field" id="tr_shipping_fee_wrap" hidden>
                <label for="tr_shipping_fee">택배비 (원)</label>
                <input type="number" id="tr_shipping_fee" name="tr_shipping_fee" min="0" max="999999999" step="100"
                       placeholder="0 입력 시 판매자 부담"
                       value="<?php echo (int)$v('tr_shipping_fee', 0); ?>">
                <p class="help">0원이면 판매자가 택배비를 부담합니다. 금액을 입력하면 바로구매 시 <strong>상품가 + 택배비</strong>가 함께 결제됩니다.</p>
            </div>

            <div class="field">
                <label for="tr_title">제목</label>
                <input type="text" id="tr_title" name="tr_title" maxlength="150" required
                       placeholder="예) [판매] 리자몽 ex SAR 민트급 판매합니다"
                       value="<?php echo htmlspecialchars($v('tr_title')); ?>">
            </div>

            <div data-type-card>
            <div class="field">
                <label>카드 형태</label>
                <div class="radio-card-group radio-card-group--kind" data-card-kind-group>
                    <?php foreach ($card_kind_labels as $k => $label): ?>
                        <label class="radio-card <?php echo $def_card_kind === $k ? 'is-active' : ''; ?>">
                            <input type="radio" name="tr_card_kind" value="<?php echo htmlspecialchars($k); ?>"
                                <?php echo $def_card_kind === $k ? 'checked' : ''; ?> required>
                            <span><?php echo htmlspecialchars($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="help">원시(싱글) 카드와 PSA 등 슬랩 등급 카드는 구분되어 검색됩니다.</p>
            </div>

            <div class="field">
                <label for="tr_card_name_card">상품명 (카드명)</label>
                <input type="text" id="tr_card_name_card" name="tr_card_name_card" maxlength="100"
                       placeholder="예) 리자몽 ex"
                       value="<?php
                         $card_name_val = (string) $v('tr_card_name_card', '');
                         if ($card_name_val === '') {
                             $card_name_val = (string) $v('tr_card_name', '');
                         }
                         echo htmlspecialchars($card_name_val);
                       ?>">
            </div>

            <div class="field-row field-row-3">
                <div class="field">
                    <label for="tr_set_name">세트명</label>
                    <input type="text" id="tr_set_name" name="tr_set_name" maxlength="80"
                           placeholder="예) 포켓몬151, 흑염의지배자"
                           value="<?php echo htmlspecialchars($v('tr_set_name')); ?>">
                    <p class="help">같은 카드라도 세트가 다르면 시세가 달라요.</p>
                </div>
                <div class="field">
                    <label for="tr_card_number">카드번호</label>
                    <input type="text" id="tr_card_number" name="tr_card_number" maxlength="20"
                           placeholder="예) 201/165"
                           value="<?php echo htmlspecialchars($v('tr_card_number')); ?>">
                </div>
                <div class="field">
                    <label for="tr_language">언어</label>
                    <select id="tr_language" name="tr_language">
                        <option value="">선택 안함</option>
                        <?php foreach ($languages as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $v('tr_language') === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field-row field-row-3">
                <div class="field">
                    <label for="tr_grade">레어도 <span class="optional">(선택)</span></label>
                    <select id="tr_grade" name="tr_grade">
                        <option value="">선택 안함</option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $v('tr_grade') === $g ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" data-single-only>
                    <label for="tr_condition">카드 상태</label>
                    <select id="tr_condition" name="tr_condition">
                        <?php foreach ($conds as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $v('tr_condition', 'A') === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" data-graded-only style="display:none">
                    <label for="tr_grading_company">감정 회사</label>
                    <select id="tr_grading_company" name="tr_grading_company">
                        <option value="">선택</option>
                        <?php foreach ($grading_companies as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $v('tr_grading_company') === $k ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" data-graded-only style="display:none">
                    <label for="tr_grading_score">슬랩 점수</label>
                    <select id="tr_grading_score" name="tr_grading_score">
                        <option value="">선택</option>
                        <?php foreach ($grading_scores as $sc): ?>
                            <option value="<?php echo htmlspecialchars($sc); ?>" <?php echo (string)$v('tr_grading_score') === (string)$sc ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            </div>

            <div class="field-row" data-type-box style="display:none">
                <div class="field">
                    <label for="tr_card_name_box">상자명 / 상품명</label>
                    <input type="text" id="tr_card_name_box" name="tr_card_name_box" maxlength="100"
                           placeholder="예) 포켓몬151 부스터박스 (30팩)"
                           value="<?php
                             $box_name_val = (string) $v('tr_card_name_box', '');
                             if ($box_name_val === '') {
                                 $box_name_val = ($def_item_type === 'box') ? (string) $v('tr_card_name', '') : '';
                             }
                             echo htmlspecialchars($box_name_val);
                           ?>">
                </div>
                <div class="field">
                    <label for="tr_box_qty">수량</label>
                    <input type="number" id="tr_box_qty" name="tr_box_qty" min="1" max="9999" value="<?php echo (int)$v('tr_box_qty', 1); ?>">
                    <p class="help">상자 단위의 수량을 입력해 주세요.</p>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="tr_price">희망가 (원)</label>
                    <input type="number" id="tr_price" name="tr_price" min="0" max="999999999" step="500"
                           placeholder="0 입력 시 '가격제안'으로 표시됩니다"
                           value="<?php echo (int)$v('tr_price', 0); ?>">
                    <div class="trade-price-suggest" id="tr_price_suggest" hidden aria-live="polite">
                        <div class="trade-price-suggest__head">
                            <strong>추천가 · 최근 판매완료</strong>
                            <span class="trade-price-suggest__match" id="tr_price_suggest_match"></span>
                        </div>
                        <p class="trade-price-suggest__summary" id="tr_price_suggest_summary"></p>
                        <ul class="trade-price-suggest__list" id="tr_price_suggest_list"></ul>
                        <p class="trade-price-suggest__empty" id="tr_price_suggest_empty" hidden></p>
                        <button type="button" class="trade-price-suggest__apply" id="tr_price_suggest_apply" hidden>
                            추천가 적용
                        </button>
                    </div>
                    <p class="help trade-settle-preview" id="tr_settle_preview" hidden>
                        예상 정산 금액은 <strong id="tr_settle_amount"></strong>원 이에요
                        (플랫폼 수수료 <?php echo (int) platform_fee_trade_percent(); ?>% 차감 후)
                    </p>
                </div>
                <div class="field">
                    <label for="tr_region">거래 지역 <span class="optional">(선택)</span></label>
                    <input type="text" id="tr_region" name="tr_region" maxlength="30"
                           placeholder="예) 서울 강남, 부산 전지역"
                           value="<?php echo htmlspecialchars($v('tr_region')); ?>">
                </div>
            </div>

            <!-- 이미지 업로드 + 드래그 순서 + 대표이미지 -->
            <div class="field">
                <label>
                    사진 첨부
                    <span class="optional">(필수 1장 이상 · 최대 8장, 장당 <?php echo (int) round(TR_UP_MAX_SIZE / 1048576); ?>MB 이하 / 드래그로 순서 변경 · 첫번째가 대표이미지)</span>
                </label>

                <div class="image-manager" data-image-manager>
                    <?php foreach ($images as $i => $img): ?>
                        <div class="image-thumb <?php echo $i === 0 ? 'is-cover' : ''; ?>"
                             draggable="true"
                             data-img-ref="existing:<?php echo (int)$img['ti_idx']; ?>">
                            <img src="<?php echo htmlspecialchars(public_url($img['ti_path'])); ?>" alt="">
                            <span class="image-cover-badge">대표</span>
                            <div class="image-thumb-actions">
                                <button type="button" class="image-btn image-set-cover" title="대표이미지로 지정">★</button>
                                <button type="button" class="image-btn image-remove-btn" title="삭제">×</button>
                            </div>
                            <input type="hidden" name="remove_img[]" value="<?php echo (int)$img['ti_idx']; ?>" disabled>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="image-uploader">
                    <label class="image-uploader-btn">
                        <input type="file" name="tr_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple id="tr_images">
                        <span>📷 사진 추가</span>
                    </label>
                    <span class="image-counter" data-image-counter>0 / 8</span>
                </div>
                <p class="help">사진은 1장 이상 필수입니다. 썸네일을 드래그해서 순서를 변경할 수 있어요. 첫 번째 사진이 자동으로 대표이미지가 됩니다.</p>
            </div>

            <div class="field">
                <label for="tr_content">상세 설명</label>
                <textarea id="tr_content" name="tr_content" rows="12" required
                          placeholder="상품 상태, 구매시기, 거래 가능 시간 등 자세히 적어주세요."><?php echo htmlspecialchars($content_for_editor, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="help">사진은 위 「사진 첨부」에서 올려 주세요. 카카오톡·외부 메신저 유도 및 개인연락처 기재는 금지되며, 적발 시 30일 이용제한 대상입니다.</p>
                <div class="trade-disclaimer" role="note">
                    <strong class="trade-disclaimer__title">거래 면책 안내</strong>
                    <p class="trade-disclaimer__text">
                        포카존은 단순 중개 플랫폼으로서 거래에 직접 관여하지 않으며, 거래 과정에서 발생하는 사기, 분쟁, 손해 등에 대해 <em>일체의 책임을 지지 않습니다.</em> 모든 거래 책임은 이용자 본인에게 있습니다.
                    </p>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?php echo htmlspecialchars($cancel_href, ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-outline" id="trade-write-cancel">취소</a>
                <button type="submit" class="btn btn-primary">
                    <?php echo $is_edit ? '수정하기' : '등록하기'; ?>
                </button>
            </div>
        </form>
    </div>
</section>

<script>
(function(){
    'use strict';

    // ───────── 상품 유형 토글 ─────────
    const radios = document.querySelectorAll('[data-item-type-group] input[type="radio"]');
    const cardFields = document.querySelector('[data-type-card]');
    const boxFields  = document.querySelector('[data-type-box]');
    const cardName   = document.getElementById('tr_card_name_card');
    const boxName    = document.getElementById('tr_card_name_box');
    const gradeSel   = document.getElementById('tr_grade');
    const condSel    = document.getElementById('tr_condition');
    const setNameEl  = document.getElementById('tr_set_name');
    const cardNumEl  = document.getElementById('tr_card_number');
    const langSel    = document.getElementById('tr_language');
    const gradeCoSel = document.getElementById('tr_grading_company');
    const gradeScSel = document.getElementById('tr_grading_score');
    const kindRadios = document.querySelectorAll('[data-card-kind-group] input[type="radio"]');
    const singleOnly = document.querySelectorAll('[data-single-only]');
    const gradedOnly = document.querySelectorAll('[data-graded-only]');

    function syncCardKindActive() {
        document.querySelectorAll('[data-card-kind-group] .radio-card').forEach(function (el) {
            el.classList.remove('is-active');
        });
        const kr = document.querySelector('[data-card-kind-group] input[type="radio"]:checked');
        if (kr) kr.closest('.radio-card')?.classList.add('is-active');
    }

    function applyCardKind(kind) {
        const isGraded = kind === 'graded';
        singleOnly.forEach(function (el) {
            el.style.display = isGraded ? 'none' : '';
        });
        gradedOnly.forEach(function (el) {
            el.style.display = isGraded ? '' : 'none';
        });
        if (condSel) {
            condSel.required = !isGraded;
        }
        if (gradeCoSel) {
            gradeCoSel.required = isGraded;
        }
        if (gradeScSel) {
            gradeScSel.required = isGraded;
        }
        syncCardKindActive();
    }

    function applyType(v) {
        if (v === 'box') {
            cardFields.style.display = 'none';
            boxFields.style.display  = '';
            if (cardName) { cardName.required = false; }
            if (boxName)  { boxName.required  = true; }
            if (condSel)  { condSel.required = false; }
            if (gradeCoSel) { gradeCoSel.required = false; }
            if (gradeScSel) { gradeScSel.required = false; }
            kindRadios.forEach(r => { r.required = false; });
        } else {
            cardFields.style.display = '';
            boxFields.style.display  = 'none';
            if (cardName) { cardName.required = true; }
            if (boxName)  { boxName.required  = false; }
            kindRadios.forEach(r => { r.required = true; });
            const kr = document.querySelector('[data-card-kind-group] input[type="radio"]:checked');
            applyCardKind(kr ? kr.value : 'single');
            if (!kr) {
                const defKind = document.querySelector('[data-card-kind-group] input[type="radio"][value="single"]');
                if (defKind) {
                    defKind.checked = true;
                    applyCardKind('single');
                }
            }
        }
        document.querySelectorAll('[data-item-type-group] .radio-card').forEach(l => l.classList.remove('is-active'));
        const checked = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
        if (checked) checked.closest('.radio-card')?.classList.add('is-active');
        syncCardKindActive();
    }

    const TRADE_WRITE_DRAFT_KEY = <?php echo json_encode($is_edit ? ('pz_trade_write_draft_edit_' . (int) $row['tr_idx']) : 'pz_trade_write_draft_insert'); ?>;
    const TRADE_WRITE_PHP_RESTORED = <?php echo !empty($trade_write_restored) ? 'true' : 'false'; ?>;
    const TRADE_WRITE_SERVER_OLD = <?php
        echo json_encode(
            ($old !== null && is_array($old)) ? $old : null,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?>;
    const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;
    let skipDraftSave = true;
    let didRestoreDraft = TRADE_WRITE_PHP_RESTORED;

    function draftStripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return (tmp.textContent || '').replace(/\u00a0/g, ' ').trim();
    }

    function draftIsUseful(data) {
        if (!data || typeof data !== 'object') return false;
        const textBits = [
            data.tr_title, data.tr_card_name_card, data.tr_card_name_box,
            data.tr_set_name, data.tr_card_number, data.tr_region
        ];
        if (textBits.some(function (v) { return String(v || '').trim() !== ''; })) return true;
        if (draftStripHtml(data.tr_content || '') !== '') return true;
        return Math.floor(Number(data.tr_price) || 0) > 0;
    }

    function readDraft() {
        try {
            const raw = sessionStorage.getItem(TRADE_WRITE_DRAFT_KEY);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            const savedAt = Number(parsed && parsed.savedAt) || 0;
            if (!savedAt || (Date.now() - savedAt) > DRAFT_TTL_MS) {
                sessionStorage.removeItem(TRADE_WRITE_DRAFT_KEY);
                return null;
            }
            return parsed.fields && typeof parsed.fields === 'object' ? parsed.fields : null;
        } catch (e) {
            return null;
        }
    }

    function collectDraft() {
        const formEl = document.getElementById('trade-form');
        const data = {};
        if (!formEl) return data;
        formEl.querySelectorAll('input, select, textarea').forEach(function (el) {
            const name = el.name;
            if (!name || name.slice(-2) === '[]' || name === 'tr_images' || el.type === 'file') return;
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (el.checked) data[name] = el.value;
                return;
            }
            data[name] = el.value;
        });
        if (window.jQuery && window.jQuery('#tr_content').data('summernote')) {
            data.tr_content = window.jQuery('#tr_content').summernote('code');
        }
        return data;
    }

    function saveDraft() {
        try {
            const fields = collectDraft();
            if (!draftIsUseful(fields)) return;
            sessionStorage.setItem(TRADE_WRITE_DRAFT_KEY, JSON.stringify({
                savedAt: Date.now(),
                fields: fields
            }));
        } catch (e) {}
    }

    function clearDraft() {
        try { sessionStorage.removeItem(TRADE_WRITE_DRAFT_KEY); } catch (e) {}
    }

    function applyDraft(data) {
        const formEl = document.getElementById('trade-form');
        if (!formEl || !data) return;
        Object.keys(data).forEach(function (name) {
            if (name === 'mode' || name === 'idx' || name === 'from' || name === '_key') return;
            const els = formEl.querySelectorAll('[name="' + name + '"]');
            if (!els.length) return;
            const val = String(data[name] == null ? '' : data[name]);
            els.forEach(function (el) {
                if (el.type === 'file') return;
                if (el.type === 'radio' || el.type === 'checkbox') {
                    if (val === '') return;
                    el.checked = (el.value === val);
                } else {
                    el.value = val;
                }
            });
        });
        if (data.tr_content != null && window.jQuery && window.jQuery('#tr_content').data('summernote')) {
            window.jQuery('#tr_content').summernote('code', String(data.tr_content));
        }
    }

    function restoreTradeWriteDraft() {
        let data = null;
        if (TRADE_WRITE_SERVER_OLD && draftIsUseful(TRADE_WRITE_SERVER_OLD)) {
            data = TRADE_WRITE_SERVER_OLD;
        }
        const stored = readDraft();
        if (stored && draftIsUseful(stored)) {
            data = stored;
        }
        if (!data) return false;
        applyDraft(data);
        const typeChecked = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
        applyType(typeChecked ? typeChecked.value : 'card');
        if (typeof syncShippingFeeField === 'function') syncShippingFeeField();
        if (typeof updateSettlePreview === 'function') updateSettlePreview();
        didRestoreDraft = true;
        const note = document.getElementById('trade-write-restore-notice');
        if (note) note.hidden = false;
        try { saveDraft(); } catch (e) {}
        return true;
    }

    window.pzTradeWriteRestore = restoreTradeWriteDraft;
    window.pzTradeWriteSaveDraft = function () {
        if (skipDraftSave) return;
        saveDraft();
    };

    kindRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            applyCardKind(r.value);
        });
    });
    document.querySelectorAll('[data-card-kind-group] .radio-card').forEach(function (label) {
        label.addEventListener('click', function () {
            const input = label.querySelector('input[type="radio"]');
            if (!input) return;
            input.checked = true;
            applyCardKind(input.value);
        });
    });
    radios.forEach(r => r.addEventListener('change', e => applyType(e.target.value)));
    const firstChecked = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
    applyType(firstChecked ? firstChecked.value : 'card');

    // ───────── 택배비 (택배/둘다 가능일 때만) ─────────
    const methodSelect = document.getElementById('tr_method');
    const shipWrap = document.getElementById('tr_shipping_fee_wrap');
    const shipInput = document.getElementById('tr_shipping_fee');

    function syncShippingFeeField() {
        if (!methodSelect || !shipWrap) return;
        const m = methodSelect.value;
        const show = (m === 'delivery' || m === 'both');
        shipWrap.hidden = !show;
        if (shipInput && !show) shipInput.value = '0';
    }
    methodSelect?.addEventListener('change', syncShippingFeeField);
    syncShippingFeeField();

    // ───────── 예상 정산 금액 (플랫폼 수수료 차감) ─────────
    const FEE_RATE = <?php echo (int) platform_fee_trade_percent(); ?>;
    const priceInput = document.getElementById('tr_price');
    const typeSelect = document.getElementById('tr_type');
    const settlePreview = document.getElementById('tr_settle_preview');
    const settleAmountEl = document.getElementById('tr_settle_amount');

    function calcSellerAmount(gross) {
        gross = Math.max(0, Math.floor(Number(gross) || 0));
        const fee = Math.floor(gross * FEE_RATE / 100);
        return gross - fee;
    }

    function updateSettlePreview() {
        if (!settlePreview || !settleAmountEl || !priceInput) return;
        const isSell = typeSelect && typeSelect.value === 'sell';
        const gross = Math.floor(Number(priceInput.value) || 0);
        if (!isSell || gross <= 0) {
            settlePreview.hidden = true;
            return;
        }
        settleAmountEl.textContent = calcSellerAmount(gross).toLocaleString('ko-KR');
        settlePreview.hidden = false;
    }

    priceInput?.addEventListener('input', updateSettlePreview);
    typeSelect?.addEventListener('change', updateSettlePreview);
    updateSettlePreview();

    // ───────── 희망가 추천 (판매완료 데이터) ─────────
    const suggestBox = document.getElementById('tr_price_suggest');
    const suggestMatch = document.getElementById('tr_price_suggest_match');
    const suggestSummary = document.getElementById('tr_price_suggest_summary');
    const suggestList = document.getElementById('tr_price_suggest_list');
    const suggestEmpty = document.getElementById('tr_price_suggest_empty');
    const suggestApply = document.getElementById('tr_price_suggest_apply');
    let suggestTimer = null;
    let suggestAbort = null;
    let lastSuggestKey = '';
    let lastSuggestPrice = 0;

    function checkedCardKind() {
        const kr = document.querySelector('[data-card-kind-group] input[type="radio"]:checked');
        return kr ? kr.value : 'single';
    }

    function isCardItemType() {
        const it = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
        return !it || it.value === 'card';
    }

    function collectSuggestQuery() {
        const kind = checkedCardKind();
        return {
            card_name: (cardName && cardName.value || '').trim(),
            set_name: (setNameEl && setNameEl.value || '').trim(),
            card_number: (cardNumEl && cardNumEl.value || '').trim(),
            language: (langSel && langSel.value || '').trim(),
            grade: (gradeSel && gradeSel.value || '').trim(),
            condition: (condSel && condSel.value || '').trim(),
            card_kind: kind,
            grading_company: (gradeCoSel && gradeCoSel.value || '').trim(),
            grading_score: (gradeScSel && gradeScSel.value || '').trim()
        };
    }

    function isSuggestReady(q) {
        if (!q.card_name || !q.set_name || !q.card_number || !q.language || !q.grade) return false;
        if (q.card_kind === 'graded') {
            return !!(q.grading_company && q.grading_score);
        }
        return !!q.condition;
    }

    function hideSuggest() {
        if (suggestBox) suggestBox.hidden = true;
        lastSuggestKey = '';
        lastSuggestPrice = 0;
        if (suggestApply) suggestApply.hidden = true;
    }

    function formatWon(n) {
        return Number(n || 0).toLocaleString('ko-KR') + '원';
    }

    function renderSuggest(data) {
        if (!suggestBox) return;
        suggestBox.hidden = false;

        if (suggestMatch) {
            suggestMatch.textContent = data.match_label ? data.match_label : '';
        }

        const summary = data.summary || {};
        const items = Array.isArray(data.items) ? data.items : [];

        if (suggestList) suggestList.innerHTML = '';
        if (suggestEmpty) {
            suggestEmpty.hidden = true;
            suggestEmpty.textContent = '';
        }
        if (suggestApply) suggestApply.hidden = true;
        lastSuggestPrice = 0;

        if (!data.ready) {
            if (suggestSummary) suggestSummary.textContent = data.message || '상품 정보를 먼저 입력해 주세요.';
            return;
        }

        if (!items.length) {
            if (suggestSummary) suggestSummary.textContent = '';
            if (suggestEmpty) {
                suggestEmpty.hidden = false;
                suggestEmpty.textContent = data.message || '아직 같은 조건의 판매완료 거래가 없어요.';
            }
            return;
        }

        if (suggestSummary) {
            const parts = [];
            if (summary.count) parts.push(summary.count + '건');
            if (summary.min && summary.max) {
                if (summary.min === summary.max) {
                    parts.push(formatWon(summary.min));
                } else {
                    parts.push(formatWon(summary.min) + ' ~ ' + formatWon(summary.max));
                }
            }
            if (summary.suggest) parts.push('추천 ' + formatWon(summary.suggest));
            suggestSummary.textContent = parts.join(' · ') + (data.message ? ' — ' + data.message : '');
        }

        items.forEach(function (it) {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = it.url || '#';
            a.target = '_blank';
            a.rel = 'noopener';
            a.innerHTML =
                '<span class="tps-price">' + formatWon(it.sold_price) + '</span>' +
                '<span class="tps-meta">' +
                    (it.sold_at ? it.sold_at + ' · ' : '') +
                    (it.source_label || '') +
                    (it.meta ? ' · ' + it.meta : '') +
                '</span>';
            li.appendChild(a);
            suggestList.appendChild(li);
        });

        if (summary.suggest > 0 && suggestApply) {
            lastSuggestPrice = summary.suggest;
            suggestApply.hidden = false;
            suggestApply.textContent = '추천가 ' + formatWon(summary.suggest) + ' 적용';
        }
    }

    function fetchSuggest(force) {
        if (!isCardItemType()) {
            hideSuggest();
            return;
        }
        const q = collectSuggestQuery();
        if (!isSuggestReady(q)) {
            if (suggestBox && !suggestBox.hidden) {
                renderSuggest({
                    ready: false,
                    message: '상품명·세트명·카드번호·언어·레어도·상태(또는 슬랩)를 먼저 입력해 주세요.',
                    items: [],
                    summary: {}
                });
            }
            return;
        }

        const key = JSON.stringify(q);
        if (!force && key === lastSuggestKey && suggestBox && !suggestBox.hidden) {
            return;
        }
        lastSuggestKey = key;

        if (suggestAbort) {
            try { suggestAbort.abort(); } catch (e) {}
        }
        suggestAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        if (suggestBox) {
            suggestBox.hidden = false;
            if (suggestSummary) suggestSummary.textContent = '판매완료 시세를 불러오는 중…';
            if (suggestList) suggestList.innerHTML = '';
            if (suggestEmpty) suggestEmpty.hidden = true;
            if (suggestApply) suggestApply.hidden = true;
            if (suggestMatch) suggestMatch.textContent = '';
        }

        const params = new URLSearchParams(q);
        const opts = {};
        if (suggestAbort) opts.signal = suggestAbort.signal;

        fetch('/trade/trade_price_suggest_api.php?' + params.toString(), opts)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || data.ok === false) {
                    renderSuggest({
                        ready: true,
                        message: (data && data.error) ? data.error : '시세를 불러오지 못했습니다.',
                        items: [],
                        summary: {}
                    });
                    return;
                }
                renderSuggest(data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                renderSuggest({
                    ready: true,
                    message: '시세를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.',
                    items: [],
                    summary: {}
                });
            });
    }

    function scheduleSuggest() {
        if (suggestTimer) clearTimeout(suggestTimer);
        suggestTimer = setTimeout(function () { fetchSuggest(true); }, 220);
    }

    priceInput?.addEventListener('focus', function () {
        if (!isCardItemType()) {
            hideSuggest();
            return;
        }
        if (suggestBox) suggestBox.hidden = false;
        fetchSuggest(false);
    });

    [
        cardName, setNameEl, cardNumEl, langSel, gradeSel, condSel,
        gradeCoSel, gradeScSel
    ].forEach(function (el) {
        if (!el) return;
        el.addEventListener('change', function () {
            lastSuggestKey = '';
            if (suggestBox && !suggestBox.hidden) scheduleSuggest();
        });
        el.addEventListener('input', function () {
            lastSuggestKey = '';
            if (suggestBox && !suggestBox.hidden) scheduleSuggest();
        });
    });
    kindRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            lastSuggestKey = '';
            if (suggestBox && !suggestBox.hidden) scheduleSuggest();
        });
    });
    radios.forEach(function (r) {
        r.addEventListener('change', function () {
            if (!isCardItemType()) hideSuggest();
        });
    });

    suggestApply?.addEventListener('click', function () {
        if (!priceInput || lastSuggestPrice <= 0) return;
        priceInput.value = String(lastSuggestPrice);
        updateSettlePreview();
        priceInput.focus();
    });

    // ───────── 이미지 매니저 ─────────
    const MAX_IMG  = 8;
    const MAX_IMG_BYTES = <?php echo (int) TR_UP_MAX_SIZE; ?>;
    const MAX_IMG_MB = <?php echo (int) max(1, (int) round(TR_UP_MAX_SIZE / 1048576)); ?>;
    const manager  = document.querySelector('[data-image-manager]');
    const fileInput = document.getElementById('tr_images');
    const counter   = document.querySelector('[data-image-counter]');
    const form      = document.getElementById('trade-form');

    // 새 파일 저장 (DataTransfer로 input 갱신)
    let newFiles = [];

    function updateCover() {
        const thumbs = manager.querySelectorAll('.image-thumb:not(.is-removing)');
        manager.querySelectorAll('.image-thumb').forEach(t => t.classList.remove('is-cover'));
        if (thumbs.length > 0) thumbs[0].classList.add('is-cover');
    }

    function updateCounter() {
        const count = manager.querySelectorAll('.image-thumb:not(.is-removing)').length;
        counter.textContent = count + ' / ' + MAX_IMG;
        counter.classList.toggle('is-max', count >= MAX_IMG);
    }

    function syncFileInput() {
        // newFiles 배열을 input.files 에 동기화 (DataTransfer API)
        try {
            const dt = new DataTransfer();
            newFiles.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        } catch (e) {
            // 미지원 브라우저 대응: 그냥 둠 (FormData 로 직접 처리)
        }
    }

    function attachThumbEvents(thumb) {
        // 드래그 이벤트
        thumb.addEventListener('dragstart', onDragStart);
        thumb.addEventListener('dragover',  onDragOver);
        thumb.addEventListener('drop',      onDrop);
        thumb.addEventListener('dragend',   onDragEnd);

        // 버튼 이벤트
        thumb.querySelector('.image-set-cover')?.addEventListener('click', (e) => {
            e.preventDefault();
            manager.insertBefore(thumb, manager.firstChild);
            updateCover();
        });
        thumb.querySelector('.image-remove-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            const ref = thumb.dataset.imgRef || '';
            if (ref.startsWith('existing:')) {
                // 기존 이미지: 삭제 표시만
                thumb.classList.toggle('is-removing');
                const hid = thumb.querySelector('input[name="remove_img[]"]');
                if (hid) hid.disabled = !thumb.classList.contains('is-removing');
            } else if (ref.startsWith('new:')) {
                const newIdx = parseInt(ref.split(':')[1], 10);
                newFiles.splice(newIdx, 1);
                thumb.remove();
                // 남은 new thumbs 의 인덱스 재정렬
                [...manager.querySelectorAll('[data-img-ref^="new:"]')].forEach((t, i) => {
                    t.dataset.imgRef = 'new:' + i;
                });
                syncFileInput();
            }
            updateCover();
            updateCounter();
        });
    }

    // 드래그 관련
    let dragEl = null;
    function onDragStart(e) {
        dragEl = this;
        this.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', this.dataset.imgRef || ''); } catch (_) {}
    }
    function onDragOver(e) {
        e.preventDefault();
        if (!dragEl || dragEl === this) return;
        const rect = this.getBoundingClientRect();
        const before = (e.clientX - rect.left) < rect.width / 2;
        manager.insertBefore(dragEl, before ? this : this.nextSibling);
    }
    function onDrop(e) {
        e.preventDefault();
        updateCover();
    }
    function onDragEnd() {
        this.classList.remove('is-dragging');
        dragEl = null;
    }

    // 기존 이미지에 이벤트 연결
    manager.querySelectorAll('.image-thumb').forEach(attachThumbEvents);
    updateCover();
    updateCounter();

    // 새 파일 선택
    fileInput?.addEventListener('change', () => {
        const picked = Array.from(fileInput.files || []);
        const remaining = MAX_IMG - manager.querySelectorAll('.image-thumb:not(.is-removing)').length;
        if (picked.length > remaining) {
            alert('최대 ' + MAX_IMG + '장까지만 업로드 가능합니다. (' + remaining + '장 더 추가 가능)');
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
            thumb.innerHTML = `
                <img alt="">
                <span class="image-cover-badge">대표</span>
                <span class="image-new-badge">NEW</span>
                <div class="image-thumb-actions">
                    <button type="button" class="image-btn image-set-cover" title="대표이미지로 지정">★</button>
                    <button type="button" class="image-btn image-remove-btn" title="삭제">×</button>
                </div>
            `;
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

    form?.addEventListener('submit', function () {
        saveDraft();
    }, true);
    // 제출 시 사진 필수 검증 + 순서 정보 수집
    form?.addEventListener('submit', (e) => {
        const itemChecked = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
        if (!itemChecked || itemChecked.value === 'card') {
            let kindChecked = document.querySelector('[data-card-kind-group] input[type="radio"]:checked');
            if (!kindChecked) {
                kindChecked = document.querySelector('[data-card-kind-group] input[type="radio"][value="single"]');
                if (kindChecked) {
                    kindChecked.checked = true;
                    applyCardKind('single');
                }
            }
        }
        form.querySelectorAll('[name]').forEach(function (el) {
            if (el.disabled) el.disabled = false;
        });
        saveDraft();
        const imgCount = manager.querySelectorAll('.image-thumb:not(.is-removing)').length;
        if (imgCount < 1) {
            e.preventDefault();
            const checked = document.querySelector('[data-item-type-group] input[type="radio"]:checked');
            applyType(checked ? checked.value : 'card');
            syncShippingFeeField();
            alert('사진을 1장 이상 첨부해 주세요.');
            return;
        }
        // 기존 image_order 제거
        form.querySelectorAll('input[name="image_order[]"]').forEach(el => el.remove());
        manager.querySelectorAll('.image-thumb').forEach(t => {
            if (t.classList.contains('is-removing')) return;
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'image_order[]';
            inp.value = t.dataset.imgRef;
            form.appendChild(inp);
        });
    });

    let draftTimer = null;
    form?.addEventListener('input', function () {
        if (skipDraftSave) return;
        if (draftTimer) clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 250);
    });
    form?.addEventListener('change', function () {
        if (skipDraftSave) return;
        if (draftTimer) clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 250);
    });
    document.getElementById('trade-write-cancel')?.addEventListener('click', clearDraft);
    window.addEventListener('pagehide', function () {
        if (!skipDraftSave) saveDraft();
    });
    skipDraftSave = false;
    restoreTradeWriteDraft();
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
