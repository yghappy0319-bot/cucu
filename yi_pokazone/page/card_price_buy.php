<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/card_price_buy_gate.php';

$t_raw = isset($_GET['t']) ? trim((string) $_GET['t']) : '';

$offer_ctx = null;
$token_bad = '';
$buy_sold_out = false;
if ($t_raw === '') {
    $token_bad = '잘못된 접근입니다.';
} elseif (!is_login()) {
    header('Location: /login.php?return=' . urlencode('/page/card_price_buy.php?t=' . rawurlencode($t_raw)));
    exit;
}

$tok = ($t_raw !== '' && $token_bad === '') ? card_price_buy_token_verify($t_raw) : null;
if ($tok === null && $token_bad === '') {
    $token_bad = '안내 유효 시간이 지났거나 링크가 온전하지 않습니다. 박스 시세에서 「구매하기」를 다시 눌러 주세요.';
} elseif ($tok !== null) {
    $url_key = trim((string) ($tok['u'] ?? ''));
    $offer_ctx = card_price_buy_offer_by_product_url($url_key);
    if ($offer_ctx === null) {
        $token_bad = '해당 외부 판매처 링크를 박스 시세에서 찾을 수 없습니다. 목록을 새로 고친 뒤 다시 선택해 주세요.';
    } elseif (!card_price_buy_offer_available($offer_ctx)) {
        $offer_ctx = null;
        $buy_sold_out = true;
    }
}

$me           = login_member();
$balance      = 0;
if (is_array($me) && (int) ($me['mb_idx'] ?? 0) > 0) {
    $mb_idx  = (int) $me['mb_idx'];
    $bal_row = db_assoc(db_query("SELECT mb_point FROM tb_member WHERE mb_idx = {$mb_idx} LIMIT 1"));
    $balance = $bal_row ? (int) $bal_row['mb_point'] : 0;
}

$cost_display = number_format(CARD_PRICE_BUY_POINT_COST);
$masked_site = $offer_ctx ? card_price_site_display_masked((string) ($offer_ctx['site_name'] ?? '')) : '';
$co_type_buy = $offer_ctx ? trim((string) ($offer_ctx['co_type'] ?? '')) : '';
$price_show   = '';
if ($offer_ctx !== null) {
    $pw = (int) ($offer_ctx['price_won'] ?? 0);
    if ($pw > 0) {
        $price_show = '<span class="cp-price-val"><small>₩</small>' . number_format($pw) . '</span>';
    } else {
        $price_show = '<span class="cp-price-inquiry">가격 확인</span>';
    }
}

$points_short_for_buy = $offer_ctx !== null
    && db_table_exists('tb_point_log')
    && (int) $balance < CARD_PRICE_BUY_POINT_COST;

$page = 'card_price';
$meta_description = '박스 시세에서 선택한 외부 판매처로 넘어가기 전에 포인트 차감과 안내 정보를 확인합니다.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '박스 시세', 'url' => '/page/card_price.php'],
];
if ($offer_ctx !== null) {
    $meta_breadcrumb[] = ['name' => '외부 구매 확인', 'url' => '/page/card_price_buy.php?t=' . rawurlencode($t_raw)];
}

$title = '외부 구매 확인 · 박스 시세';
include __DIR__ . '/../include/header.php';

$can_pay = ($offer_ctx !== null && ((int) $balance >= CARD_PRICE_BUY_POINT_COST));
?>

<section class="community card-buy-confirm-page">
    <div class="container">
        <header class="board-head shop-store-head">
            <div>
                <p class="card-price-kicker">박스 시세</p>
                <h1 class="board-title"><?php echo $buy_sold_out ? '구매 안내' : '외부 구매 전 확인'; ?></h1>
                <?php if (!$buy_sold_out && $token_bad === ''): ?>
                    <p class="board-desc card-buy-confirm-intro">
                        아래 정보를 확인하신 뒤 <strong>구매처로 이동</strong>을 누르면
                        회원에게 <strong><?php echo htmlspecialchars($cost_display); ?> 포인트</strong>가 차감된 뒤 판매 페이지로 넘어갑니다.
                    </p>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($buy_sold_out): ?>
            <div class="card-buy-confirm-alert" role="alert">품절 상품입니다</div>
            <p class="shop-prep-desc" style="opacity:0.85;font-size:14px;margin-top:-0.25rem;">
                포인트는 차감되지 않았습니다. 잠시 후 이전 페이지로 돌아갑니다.
            </p>
            <p class="shop-prep-desc">
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="if (history.length &gt; 1) history.back(); else location.href='/page/card_price.php';">
                    바로 돌아가기
                </button>
                <a class="btn btn-outline btn-sm" href="/page/card_price.php">박스 시세</a>
            </p>
            <script>(function(){ setTimeout(function () {
                if (window.history.length > 1) { window.history.back(); }
                else { window.location.href = '/page/card_price.php'; }
            }, 1200); })();</script>
        <?php elseif ($token_bad !== ''): ?>
            <div class="card-buy-confirm-alert" role="alert">
                <?php echo htmlspecialchars($token_bad); ?>
            </div>
            <p class="shop-prep-desc">
                <a class="btn btn-outline btn-sm" href="/page/card_price.php">박스 시세로 돌아가기</a>
            </p>
        <?php else: ?>
            <div class="card-buy-confirm-box">
                <?php if (!db_table_exists('tb_point_log')): ?>
                    <p class="card-buy-confirm-alert">포인트 시스템이 준비되지 않았습니다.</p>
                <?php elseif ($points_short_for_buy): ?>
                    <p class="card-buy-confirm-alert" role="alert">
                        현재 보유 포인트는 <?php echo number_format($balance); ?>P입니다. 외부 구매 안내를 이용하려면
                        <strong><?php echo htmlspecialchars($cost_display); ?>P 이상</strong>이 필요합니다.
                        <a href="/page/attendance.php">출석체크</a> 등으로 적립한 뒤 다시 진행해 주세요.
                    </p>
                <?php endif; ?>
                <dl class="card-buy-spec">
                    <dt>미개봉 박스</dt>
                    <dd><?php echo htmlspecialchars(trim((string) ($offer_ctx['product_name'] ?? ''))); ?></dd>
                    <?php if ($co_type_buy !== ''): ?>
                        <dt>플랫폼·업체</dt>
                        <dd><?php echo htmlspecialchars($co_type_buy); ?></dd>
                    <?php endif; ?>
                    <dt>판매처</dt>
                    <dd><?php echo htmlspecialchars($masked_site); ?></dd>
                    <dt>가격</dt>
                    <dd><?php echo $price_show !== '' ? $price_show : '&mdash;'; ?></dd>
                    <dt>이용 차감</dt>
                    <dd><strong><?php echo htmlspecialchars($cost_display); ?></strong> P</dd>
                    <dt>보유 포인트</dt>
                    <dd><?php echo number_format($balance); ?> P</dd>
                </dl>

                <?php if (db_table_exists('tb_point_log')): ?>
                    <form class="card-buy-confirm-form" method="post" action="/proc/card_price_buy_proc.php">
                        <input type="hidden" name="t" value="<?php echo htmlspecialchars($t_raw, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-primary"<?php echo $can_pay ? '' : ' disabled aria-disabled="true"'; ?>
                                id="cardBuyProceedBtn">
                            <?php echo htmlspecialchars($cost_display); ?>P 차감 후 외부 판매처로 이동
                        </button>
                    </form>
                <?php endif; ?>

                <p class="card-buy-confirm-foot">
                    실제 결제·배송은 각 외부 쇼핑몰 규칙을 따릅니다. 차감된 포인트는 환불되지 않습니다.
                    링크는 일정 시간이 지나면 자동으로 만료됩니다.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
