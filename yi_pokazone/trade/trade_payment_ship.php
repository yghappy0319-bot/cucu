<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_trade_payment.php';
require_once __DIR__ . '/lib/_trade_seller_review.php';
require_once __DIR__ . '/lib/_member_trade_sell_suspend.php';
require_once __DIR__ . '/../lib/_member_cash.php';
require_once __DIR__ . '/../lib/_sweettracker.php';
require_once __DIR__ . '/../lib/_member_public.php';

$me = login_member();
$pay_idx = (int) ($_GET['pay_idx'] ?? 0);
$return_ship = '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;

if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($return_ship));
}
if ($pay_idx < 1) {
    alert_goto('잘못된 접근입니다.', '/trade/trade_messages.php');
}

$mb_idx = (int) $me['mb_idx'];
$access = trade_payment_ship_page_access($pay_idx, $mb_idx);
if (empty($access['ok'])) {
    alert_goto((string) ($access['error'] ?? '접근할 수 없습니다.'), '/trade/trade_messages.php');
}

$row             = (array) ($access['row'] ?? []);
$is_seller       = !empty($access['is_seller']);
$is_buyer        = !empty($access['is_buyer']);
$can_edit_track  = !empty($access['can_edit_tracking']);
$can_cancel_sale = !empty($access['can_cancel_sale']);
$is_sale_cancelled = !empty($access['is_sale_cancelled']);
$ship_addr       = trade_payment_ship_address_from_row($row);
$has_addr        = trade_payment_ship_address_has_data($ship_addr);
$has_tracking    = trade_payment_has_tracking($row);
$courier_presets = trade_payment_courier_presets();
$courier_options = sweettracker_company_list();
$sweettracker_on = sweettracker_ready();
$current_courier_code = trim((string) ($row['pay_courier_code'] ?? ''));
if ($current_courier_code === '' && $has_tracking) {
    $current_courier_code = sweettracker_resolve_company_code((string) ($row['pay_courier_name'] ?? ''));
}
$st_tracking = ($has_tracking && $sweettracker_on)
    ? sweettracker_tracking_for_payment_row($row)
    : ['code' => '', 'info' => null, 'error' => ''];
$st_timeline = (!empty($st_tracking['info']) && is_array($st_tracking['info']))
    ? sweettracker_tracking_timeline($st_tracking['info'])
    : [];
$st_status_text = (!empty($st_tracking['info']) && is_array($st_tracking['info']))
    ? sweettracker_tracking_status_text($st_tracking['info'])
    : '';
$ship_ready      = trade_payment_ship_column_ready();
$fulfill         = trade_payment_fulfill_status_from_row($row);
if ($ship_ready && $has_tracking && $fulfill < TRADE_PAYMENT_FULFILL_PURCHASE) {
    trade_payment_sync_delivery_status($row);
    $row = trade_payment_row($pay_idx) ?: $row;
    $fulfill = trade_payment_fulfill_status_from_row($row);
}
$is_purchase_done = $fulfill >= TRADE_PAYMENT_FULFILL_PURCHASE;
$show_ship_detail = !$is_sale_cancelled && !$is_purchase_done;
$fulfill_label    = trade_payment_fulfill_label($row);
$pay_view         = trade_payment_public_payload($row, $mb_idx);
$can_confirm_shipping = $is_buyer && !empty($pay_view['can_confirm_shipping']);
$can_confirm_purchase = $is_buyer && !empty($pay_view['can_confirm_purchase']);
$is_admin_member      = (int) ($me['mb_level'] ?? 0) >= 9;
$can_force_delivery   = $is_admin_member && $show_ship_detail
    && $fulfill < TRADE_PAYMENT_FULFILL_SHIPPING;

$tr_idx   = (int) ($row['tr_idx'] ?? 0);
$room_idx = (int) ($row['room_idx'] ?? 0);
$back_url = '/trade/trade_messages.php';
if ($tr_idx > 0) {
    $back_url .= '?tr_idx=' . $tr_idx;
    if ($room_idx > 0) {
        $back_url .= '&room_idx=' . $room_idx;
    }
}

$buyer_mb = (int) ($row['buyer_mb_idx'] ?? 0);
$buyer_nick = '';
if ($buyer_mb > 0) {
    $buyer_nick = (string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . $buyer_mb . ' LIMIT 1');
}

$product_label = (string) ($row['product_label'] ?? '상품');
$pay_amount    = (int) ($row['pay_amount'] ?? 0);
$settle_preview = trade_payment_settlement_preview_from_row($row);
$auto_confirm_days = (int) TRADE_PURCHASE_AUTO_CONFIRM_DAYS;
$refund_amount = (int) ($row['pay_refund_amount'] ?? 0);
$done          = isset($_GET['done']) && (string) $_GET['done'] === '1';
$cancelled     = isset($_GET['cancelled']) && (string) $_GET['cancelled'] === '1';
$shipping_confirmed = isset($_GET['shipping_confirmed']) && (string) $_GET['shipping_confirmed'] === '1';
$purchase_confirmed = isset($_GET['purchase_confirmed']) && (string) $_GET['purchase_confirmed'] === '1';
$delivery_forced    = isset($_GET['delivery_forced']) && (string) $_GET['delivery_forced'] === '1';
$review_done        = isset($_GET['review_done']) && (string) $_GET['review_done'] === '1';

$seller_mb_idx = (int) ($row['seller_mb_idx'] ?? 0);
$seller_public = ($seller_mb_idx > 0) ? member_public_get($seller_mb_idx) : null;
$seller_nick   = $seller_public
    ? (string) ($seller_public['mb_nick'] ?? '')
    : ($seller_mb_idx > 0
        ? (string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . $seller_mb_idx . ' LIMIT 1')
        : '');
$seller_intro = $seller_public
    ? trim((string) ($seller_public['mb_shop_intro'] ?? ''))
    : '';
$seller_joined = '';
if ($seller_public && !empty($seller_public['mb_created_at'])) {
    $seller_joined = date('Y.m.d', strtotime((string) $seller_public['mb_created_at']));
}
$seller_shop_url = $seller_mb_idx > 0 ? member_profile_url($seller_mb_idx) : '';
$seller_review_stats = ($seller_mb_idx > 0 && trade_seller_review_table_ready())
    ? trade_seller_review_stats($seller_mb_idx)
    : ['avg' => 0.0, 'count' => 0];
$show_seller_shop_card = $is_buyer && $seller_shop_url !== '' && $seller_nick !== '';

$existing_review = ($is_purchase_done && trade_seller_review_table_ready())
    ? trade_seller_review_by_pay($pay_idx)
    : null;
$can_write_review = false;
$review_table_ready = trade_seller_review_table_ready();
if ($is_buyer && $is_purchase_done && $review_table_ready && !$existing_review) {
    $can_write_review = !empty(trade_seller_review_can_write($pay_idx, $mb_idx)['ok']);
}
$cash_ready    = function_exists('member_cash_column_ready') && member_cash_column_ready();
$sale_cancel_ready = trade_payment_sale_cancel_column_ready();
$sale_cancel_penalty_ready = member_trade_sale_cancel_penalty_column_ready();
$sale_cancel_penalty = ($is_seller && $sale_cancel_penalty_ready)
    ? member_trade_sale_cancel_penalty_status($mb_idx)
    : ['count' => 0, 'is_suspended' => false, 'suspended_until' => null, 'memo' => null];
$sale_cancel_count = (int) ($sale_cancel_penalty['count'] ?? 0);
$sale_cancel_threshold = (int) MEMBER_TRADE_SALE_CANCEL_PENALTY_THRESHOLD;
$sale_cancel_next_count = $sale_cancel_count + 1;
$sale_cancel_will_penalize = $sale_cancel_penalty_ready && $sale_cancel_next_count >= $sale_cancel_threshold;

$page = 'trade_payment_ship';
$title = ($is_seller ? '배송·택배 정보' : '배송 정보 확인') . ' · ' . $product_label;
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래 메시지함', 'url' => '/trade/trade_messages.php'],
    ['name' => $is_seller ? '배송·택배 정보' : '배송 정보 확인', 'url' => $return_ship],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage auction-order trade-payment-ship">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_seller ? '배송·택배 정보' : '배송 정보 확인'; ?></h1>
                <p class="board-desc">
                    <?php if ($is_sale_cancelled): ?>
                        취소된 거래입니다.
                    <?php elseif ($is_purchase_done): ?>
                        구매확정이 완료된 거래입니다.
                    <?php elseif ($is_seller): ?>
                        구매자 배송지를 확인하고 택배사·운송장번호를 등록해 주세요.
                    <?php else: ?>
                        등록된 배송지와 택배 발송 정보를 확인할 수 있습니다.
                    <?php endif; ?>
                </p>
            </div>
            <div class="mypage-head-actions">
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-outline btn-sm">채팅으로 돌아가기</a>
            </div>
        </div>

        <?php if ($cancelled): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                판매가 취소되었습니다. 구매자에게 캐시가 환불되었습니다.
            </div>
        <?php elseif ($done): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                운송장 정보가 저장되었습니다.
            </div>
        <?php elseif ($shipping_confirmed): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                수령 확인이 완료되었습니다. 물건에 이상이 없으면 아래에서 구매확정을 진행해 주세요.
            </div>
        <?php elseif ($purchase_confirmed): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                구매확정이 완료되었습니다.
                <?php if ($is_buyer && $can_write_review): ?>
                    아래에서 판매자 후기와 별점을 남겨 주세요.
                <?php endif; ?>
            </div>
        <?php elseif ($review_done): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                판매자 후기가 등록되었습니다.
                <?php if ((int) TRADE_SELLER_REVIEW_POINT_REWARD > 0): ?>
                    +<?php echo number_format((int) TRADE_SELLER_REVIEW_POINT_REWARD); ?>P가 적립되었습니다.
                <?php endif; ?>
            </div>
        <?php elseif ($delivery_forced): ?>
            <div class="auction-order-alert auction-order-alert--done" role="status">
                강제 배송완료 처리되었습니다. 구매자 계정에서 구매확정 버튼을 확인해 주세요.
            </div>
        <?php endif; ?>

        <?php if ($show_seller_shop_card): ?>
            <div class="mypage-panel trade-payment-seller-shop">
                <h2 class="mypage-panel-title">판매자 상점</h2>
                <div class="trade-seller-shop-card trade-seller-shop-card--ship">
                    <div class="trade-seller-shop-card__head">
                        <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($seller_nick, 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                        <div class="trade-seller-shop-card__meta">
                            <span class="trade-seller-shop-card__label">판매자</span>
                            <strong class="trade-seller-shop-card__nick"><?php echo htmlspecialchars($seller_nick, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p class="trade-seller-shop-card__stats">
                                <?php if ((int) ($seller_review_stats['count'] ?? 0) > 0): ?>
                                    <span>평점 <?php echo htmlspecialchars(number_format((float) $seller_review_stats['avg'], 1), ENT_QUOTES, 'UTF-8'); ?>
                                        (<?php echo number_format((int) $seller_review_stats['count']); ?>)</span>
                                <?php else: ?>
                                    <span>후기 없음</span>
                                <?php endif; ?>
                                <?php if ($seller_joined !== ''): ?>
                                    <span>가입 <?php echo htmlspecialchars($seller_joined, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($seller_intro !== ''): ?>
                        <p class="trade-seller-shop-card__intro"><?php echo nl2br(htmlspecialchars($seller_intro, ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>
                    <div class="trade-seller-shop-card__actions">
                        <a href="<?php echo htmlspecialchars($seller_shop_url, ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-outline btn-sm">상점 보기</a>
                        <a href="<?php echo htmlspecialchars($seller_shop_url . '#reviews', ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-outline btn-sm">후기 보기</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mypage-panel trade-payment-confirm-info">
            <h2 class="mypage-panel-title">구매확정 정보</h2>
            <?php if ($is_sale_cancelled): ?>
                <div class="auction-order-alert auction-order-alert--cancel" role="status">
                    <?php if ($is_seller): ?>
                        이 거래는 <strong>판매 취소</strong>되었습니다.
                        <?php if ($refund_amount > 0): ?>
                            · 구매자에게 ₩<?php echo number_format($refund_amount); ?> 캐시 환불
                        <?php endif; ?>
                    <?php else: ?>
                        판매자가 거래를 취소했습니다.
                        <?php if ($refund_amount > 0): ?>
                            · ₩<?php echo number_format($refund_amount); ?>이 <strong>캐시</strong>로 환불되었습니다.
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($row['pay_refunded_at'])): ?>
                        <span class="auction-order-tracking-muted">(<?php echo htmlspecialchars((string) $row['pay_refunded_at']); ?>)</span>
                    <?php endif; ?>
                </div>
            <?php elseif ($is_purchase_done): ?>
                <div class="auction-order-alert auction-order-alert--done" role="status">
                    <strong>구매확정 완료</strong>
                    · <?php echo htmlspecialchars($product_label); ?>
                    · ₩<?php echo number_format($pay_amount); ?>
                    <?php if (!empty($row['pay_buyer_confirmed_at'])): ?>
                        <span class="auction-order-tracking-muted">(<?php echo htmlspecialchars((string) $row['pay_buyer_confirmed_at']); ?>)</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="auction-order-alert auction-order-alert--done">
                    입금 확인 완료 · <strong><?php echo htmlspecialchars($product_label); ?></strong>
                    · ₩<?php echo number_format($pay_amount); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_purchase_done && ($is_buyer || $is_seller)): ?>
            <div class="mypage-panel trade-seller-review-panel">
                <h2 class="mypage-panel-title">판매자 후기</h2>
                <?php if (!$review_table_ready): ?>
                    <p class="auction-order-tracking-wait">
                        후기 기능을 사용하려면 <code>sql/migrate_tb_trade_seller_review.sql</code> 을 적용해 주세요.
                    </p>
                <?php elseif ($existing_review): ?>
                    <div class="trade-seller-review-done">
                        <div class="trade-seller-review-done-head">
                            <?php echo trade_seller_review_stars_html((int) ($existing_review['tsr_rating'] ?? 0)); ?>
                            <span class="trade-seller-review-done-score">
                                <?php echo (int) ($existing_review['tsr_rating'] ?? 0); ?> / 5
                            </span>
                        </div>
                        <p class="trade-seller-review-done-body">
                            <?php echo nl2br(htmlspecialchars((string) ($existing_review['tsr_body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
                        </p>
                        <?php if (!empty($existing_review['tsr_created_at'])): ?>
                            <p class="trade-seller-review-done-meta">
                                <?php echo htmlspecialchars((string) $existing_review['tsr_created_at'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($seller_shop_url !== ''): ?>
                                    · <a href="<?php echo htmlspecialchars($seller_shop_url, ENT_QUOTES, 'UTF-8'); ?>">판매자 상점</a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($is_buyer && $can_write_review): ?>
                    <p class="auction-order-tracking-form-desc">
                        <?php echo $seller_nick !== ''
                            ? htmlspecialchars($seller_nick, ENT_QUOTES, 'UTF-8') . ' 님과의 거래는 어떠셨나요?'
                            : '판매자와의 거래는 어떠셨나요?'; ?>
                        별점과 짧은 후기를 남겨 주시면 다른 구매자에게 도움이 됩니다.
                        <?php if ((int) TRADE_SELLER_REVIEW_POINT_REWARD > 0): ?>
                            후기 등록 시 <strong><?php echo number_format((int) TRADE_SELLER_REVIEW_POINT_REWARD); ?>P</strong>가 지급됩니다.
                        <?php endif; ?>
                    </p>
                    <form class="trade-seller-review-form"
                          method="post"
                          action="/trade/proc/trade_seller_review_proc.php"
                          onsubmit="return confirm('판매자 후기를 등록할까요?\n\n등록 후에는 수정할 수 없습니다.<?php echo (int) TRADE_SELLER_REVIEW_POINT_REWARD > 0 ? '\n+' . number_format((int) TRADE_SELLER_REVIEW_POINT_REWARD) . 'P가 적립됩니다.' : ''; ?>');">
                        <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                        <fieldset class="trade-seller-review-rating">
                            <legend>별점</legend>
                            <div class="trade-seller-review-rating-options" role="radiogroup" aria-label="별점">
                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                    <label class="trade-seller-review-rating-option">
                                        <input type="radio"
                                               name="tsr_rating"
                                               value="<?php echo $s; ?>"
                                               required
                                               <?php echo $s === 5 ? ' checked' : ''; ?>>
                                        <span class="trade-seller-review-rating-label">
                                            <?php echo trade_seller_review_stars_html($s); ?>
                                            <span class="trade-seller-review-rating-num"><?php echo $s; ?>점</span>
                                        </span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </fieldset>
                        <div class="trade-seller-review-body-field">
                            <label for="tsr_body">후기</label>
                            <textarea id="tsr_body"
                                      name="tsr_body"
                                      rows="4"
                                      maxlength="<?php echo (int) TRADE_SELLER_REVIEW_BODY_MAX; ?>"
                                      required
                                      placeholder="거래 후기를 입력해 주세요. (최대 <?php echo (int) TRADE_SELLER_REVIEW_BODY_MAX; ?>자)"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            후기 등록<?php if ((int) TRADE_SELLER_REVIEW_POINT_REWARD > 0): ?> (+<?php echo number_format((int) TRADE_SELLER_REVIEW_POINT_REWARD); ?>P)<?php endif; ?>
                        </button>
                    </form>
                <?php elseif ($is_buyer): ?>
                    <p class="auction-order-tracking-wait">이 거래에는 후기를 남길 수 없습니다.</p>
                <?php else: ?>
                    <p class="auction-order-tracking-wait">구매자가 아직 후기를 남기지 않았습니다.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_ship_detail): ?>
        <div class="auction-order-layout">
            <div class="mypage-panel">
                <h2 class="mypage-panel-title"><?php echo $is_seller ? '구매자 배송지' : '내 배송지'; ?></h2>
                <?php if ($has_addr): ?>
                    <dl class="mypage-dl">
                        <?php if ($is_seller && $buyer_nick !== ''): ?>
                            <div>
                                <dt>구매자</dt>
                                <dd><?php echo htmlspecialchars($buyer_nick); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($ship_addr['label'] !== ''): ?>
                            <div>
                                <dt>배송지명</dt>
                                <dd><?php echo htmlspecialchars($ship_addr['label']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt>받는분</dt>
                            <dd><?php echo htmlspecialchars($ship_addr['name'] !== '' ? $ship_addr['name'] : '-'); ?></dd>
                        </div>
                        <div>
                            <dt>연락처</dt>
                            <dd><?php echo htmlspecialchars($ship_addr['phone'] !== '' ? $ship_addr['phone'] : '-'); ?></dd>
                        </div>
                        <div class="auction-order-addr-row">
                            <dt>주소</dt>
                            <dd>
                                <?php if ($ship_addr['zip'] !== ''): ?>
                                    [<?php echo htmlspecialchars($ship_addr['zip']); ?>]
                                <?php endif; ?>
                                <?php echo htmlspecialchars($ship_addr['address'] !== '' ? $ship_addr['address'] : '-'); ?>
                            </dd>
                        </div>
                        <?php if ($ship_addr['message'] !== ''): ?>
                            <div>
                                <dt>배송 메시지</dt>
                                <dd><?php echo htmlspecialchars($ship_addr['message']); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                <?php else: ?>
                    <p class="auction-order-tracking-wait">
                        <?php if ($is_buyer): ?>
                            등록된 기본 배송지가 없습니다. 마이페이지 &gt; 배송지 주소록에서 등록해 주세요.
                        <?php else: ?>
                            구매자의 기본 배송지가 아직 등록되지 않았습니다.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mypage-panel">
                <h2 class="mypage-panel-title">택배 발송</h2>
                <?php if ($has_tracking): ?>
                    <div class="auction-order-tracking-row">
                        <p>
                            <?php echo htmlspecialchars((string) ($row['pay_courier_name'] ?? '')); ?>
                            · 운송장 <strong><?php echo htmlspecialchars((string) ($row['pay_tracking_no'] ?? '')); ?></strong>
                            <?php if (!empty($row['pay_tracking_at'])): ?>
                                <span class="auction-order-tracking-muted">(<?php echo htmlspecialchars((string) $row['pay_tracking_at']); ?> 등록)</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($sweettracker_on && $current_courier_code !== ''): ?>
                            <p class="sweettracker-actions">
                                <a class="btn btn-outline btn-sm"
                                   href="/trade/trade_tracking_view.php?pay_idx=<?php echo $pay_idx; ?>"
                                   target="_blank"
                                   rel="noopener">배송조회</a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($sweettracker_on): ?>
                        <div class="sweettracker-panel" id="sweettracker-panel">
                            <div class="sweettracker-panel__head">
                                <h3 class="sweettracker-panel__title">배송 현황</h3>
                                <?php if ($st_status_text !== ''): ?>
                                    <span class="sweettracker-panel__badge"><?php echo htmlspecialchars($st_status_text); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($st_timeline !== []): ?>
                                <ol class="sweettracker-timeline">
                                    <?php foreach ($st_timeline as $step): ?>
                                        <li class="sweettracker-timeline__item">
                                            <span class="sweettracker-timeline__time"><?php echo htmlspecialchars((string) ($step['time'] ?? '')); ?></span>
                                            <span class="sweettracker-timeline__kind"><?php echo htmlspecialchars((string) ($step['kind'] ?? '')); ?></span>
                                            <?php if (!empty($step['where'])): ?>
                                                <span class="sweettracker-timeline__where"><?php echo htmlspecialchars((string) $step['where']); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php elseif (!empty($st_tracking['error']) && $st_tracking['error'] === 'fetch_failed'): ?>
                                <p class="auction-order-tracking-wait">배송 정보를 불러오지 못했습니다. 잠시 후 다시 확인해 주세요.</p>
                            <?php elseif (!empty($st_tracking['error']) && $st_tracking['error'] === 'unknown_courier'): ?>
                                <p class="auction-order-tracking-wait">택배사를 목록에서 다시 선택해 주세요.</p>
                            <?php else: ?>
                                <p class="auction-order-tracking-wait">택배사 집하 후 배송 현황이 표시됩니다.</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$sweettracker_on): ?>
                        <p class="auction-order-tracking-wait sweettracker-setup-hint">
                            실시간 배송조회를 쓰려면 <code>config/site_info.php</code>에 배송조회 API 키를 설정해 주세요.
                        </p>
                    <?php endif; ?>
                <?php elseif (!$is_seller): ?>
                    <p class="auction-order-tracking-wait">판매자가 운송장을 등록하면 이곳에서 확인할 수 있습니다.</p>
                <?php else: ?>
                    <p class="auction-order-tracking-wait">아직 등록된 운송장이 없습니다.</p>
                <?php endif; ?>

                <?php if ($is_seller && $ship_ready && $can_edit_track): ?>
                    <form class="auction-order-tracking-form" method="post" action="/trade/proc/trade_payment_ship_proc.php">
                        <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                        <p class="auction-order-tracking-form-desc">
                            택배사와 운송장번호를 입력하면 구매자에게 알림이 발송됩니다.
                            <?php if ($sweettracker_on): ?>
                                <span class="sweettracker-form-note">택배사는 목록에서 선택합니다.</span>
                            <?php endif; ?>
                        </p>
                        <div class="form-row">
                            <label for="pay_courier_code">택배사 <span class="req">*</span></label>
                            <?php if ($courier_options !== []): ?>
                                <input type="hidden" name="pay_courier_name" id="pay_courier_name_field"
                                       value="<?php echo htmlspecialchars((string) ($row['pay_courier_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <select id="pay_courier_code" name="pay_courier_code" required>
                                    <option value="">택배사 선택</option>
                                    <?php foreach ($courier_options as $opt): ?>
                                        <?php
                                        $opt_code = (string) ($opt['code'] ?? '');
                                        $opt_name = (string) ($opt['name'] ?? '');
                                        if ($opt_code === '' || $opt_name === '') {
                                            continue;
                                        }
                                        $selected = ($current_courier_code !== '' && $current_courier_code === $opt_code)
                                            || ($current_courier_code === '' && (string) ($row['pay_courier_name'] ?? '') === $opt_name);
                                        ?>
                                        <option value="<?php echo htmlspecialchars($opt_code, ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo $selected ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($opt_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" id="pay_courier_name" name="pay_courier_name" list="trade-courier-list"
                                       maxlength="<?php echo TRADE_PAYMENT_COURIER_MAX; ?>"
                                       value="<?php echo htmlspecialchars((string) ($row['pay_courier_name'] ?? '')); ?>"
                                       required autocomplete="organization">
                                <datalist id="trade-courier-list">
                                    <?php foreach ($courier_presets as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            <?php endif; ?>
                        </div>
                        <div class="form-row">
                            <label for="pay_tracking_no">운송장번호 <span class="req">*</span></label>
                            <input type="text" id="pay_tracking_no" name="pay_tracking_no"
                                   maxlength="<?php echo TRADE_PAYMENT_TRACKING_MAX; ?>"
                                   pattern="[0-9A-Za-z\-]+"
                                   value="<?php echo htmlspecialchars((string) ($row['pay_tracking_no'] ?? '')); ?>"
                                   required autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $has_tracking ? '운송장 정보 수정' : '운송장 등록 및 구매자 알림'; ?>
                        </button>
                    </form>
                    <?php if ($courier_options !== []): ?>
                    <script>
                    (function () {
                        var sel = document.getElementById('pay_courier_code');
                        var hid = document.getElementById('pay_courier_name_field');
                        if (!sel || !hid) return;
                        function syncCourierName() {
                            var opt = sel.options[sel.selectedIndex];
                            hid.value = (opt && opt.value) ? opt.text.trim() : '';
                        }
                        sel.addEventListener('change', syncCourierName);
                        syncCourierName();
                    })();
                    </script>
                    <?php endif; ?>
                <?php elseif ($is_purchase_done): ?>
                    <p class="auction-order-tracking-wait">구매확정이 완료되어 운송장을 수정할 수 없습니다.</p>
                <?php endif; ?>

            </div>

            <?php if ($can_force_delivery): ?>
            <div class="mypage-panel trade-payment-force-delivery">
                <h2 class="mypage-panel-title">테스트 · 강제 배송완료</h2>
                <p class="auction-order-tracking-form-desc">
                    운송장 등록 없이 구매확정 단계로 넘깁니다.
                    처리 후 <strong>구매자 계정</strong>으로 이 페이지를 열면 「구매확정」 버튼이 표시되어 정산 테스트를 할 수 있습니다.
                </p>
                <form method="post"
                      action="/trade/proc/trade_payment_force_delivery_proc.php"
                      onsubmit="return confirm('강제 배송완료 처리하시겠습니까?\n\n운송장 없이 구매확정 단계로 넘어갑니다. 구매자 화면에서 구매확정 버튼이 표시됩니다.');">
                    <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                    <button type="submit" class="btn btn-outline">강제 배송완료</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($is_seller && !$is_sale_cancelled && $can_cancel_sale && $sale_cancel_ready && $cash_ready): ?>
            <div class="mypage-panel trade-payment-ship-cancel">
                <h2 class="mypage-panel-title">판매 취소</h2>
                <p class="auction-order-tracking-form-desc">
                    발송 전 거래를 취소하면 구매자에게 입금액 <strong>₩<?php echo number_format($pay_amount); ?></strong>이
                    포카존 캐시로 환불됩니다. 취소 후에는 되돌릴 수 없습니다.
                </p>
                <?php if ($sale_cancel_penalty_ready): ?>
                <p class="auction-order-tracking-form-desc trade-payment-ship-cancel-penalty<?php echo $sale_cancel_will_penalize ? ' trade-payment-ship-cancel-penalty--warn' : ''; ?>">
                    입금 확인 후 판매 취소 누적: <strong><?php echo number_format($sale_cancel_count); ?>회</strong>
                    <?php if ($sale_cancel_will_penalize): ?>
                        · 이번 취소 시 <strong>거래 판매글 등록이 1일간 정지</strong>됩니다.
                    <?php elseif ($sale_cancel_count > 0): ?>
                        · <?php echo number_format($sale_cancel_threshold); ?>회 이상 누적 시 거래 판매글 등록이 1일간 정지됩니다.
                    <?php else: ?>
                        · 구매자 입금 확인 후 판매 취소가 <?php echo number_format($sale_cancel_threshold); ?>회 이상 누적되면
                        거래 판매글 등록이 1일간 정지됩니다.
                    <?php endif; ?>
                </p>
                <?php if (!empty($sale_cancel_penalty['is_suspended'])): ?>
                <p class="auction-order-tracking-wait">
                    현재 판매글 등록 정지 중입니다.
                    <?php if (!empty($sale_cancel_penalty['suspended_until'])): ?>
                        (<?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $sale_cancel_penalty['suspended_until']))); ?> 이후 등록 가능)
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <?php endif; ?>
                <?php
                $cancel_confirm = '판매를 취소하고 구매자에게 ₩' . number_format($pay_amount) . '을 캐시로 환불하시겠습니까?';
                if ($sale_cancel_will_penalize) {
                    $cancel_confirm .= '\n\n입금 확인 후 판매 취소가 ' . number_format($sale_cancel_next_count)
                        . '회가 되어 거래 판매글 등록이 1일간 정지됩니다.';
                }
                ?>
                <form method="post"
                      action="/trade/proc/trade_payment_sale_cancel_proc.php"
                      onsubmit="return confirm(<?php echo json_encode($cancel_confirm, JSON_UNESCAPED_UNICODE); ?>);">
                    <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                    <button type="submit" class="btn btn-outline trade-payment-ship-cancel__btn">판매 취소</button>
                </form>
            </div>
            <?php elseif ($is_seller && !$is_sale_cancelled && $has_tracking): ?>
            <div class="mypage-panel trade-payment-ship-cancel">
                <p class="auction-order-tracking-wait">운송장이 등록되어 판매 취소할 수 없습니다.</p>
            </div>
            <?php elseif ($is_seller && !$is_sale_cancelled && $can_cancel_sale && (!$sale_cancel_ready || !$cash_ready)): ?>
            <div class="mypage-panel trade-payment-ship-cancel">
                <p class="auction-order-tracking-wait">
                    판매 취소 기능 DB가 필요합니다.
                    <?php if (!$sale_cancel_ready): ?>
                        sql/migrate_tb_trade_payment_sale_cancel.sql
                    <?php endif; ?>
                    <?php if (!$cash_ready): ?>
                        sql/migrate_tb_member_cash.sql
                    <?php endif; ?>
                    을 적용해 주세요.
                </p>
            </div>
            <?php endif; ?>

            <?php if ($is_buyer && $show_ship_detail): ?>
            <div class="mypage-panel trade-payment-buyer-fulfill">
                <h2 class="mypage-panel-title">구매자 확인</h2>
                <p class="trade-payment-fulfill-status">
                    현재 상태: <strong><?php echo htmlspecialchars($fulfill_label); ?></strong>
                </p>
                <?php if ($can_confirm_purchase): ?>
                    <p class="auction-order-tracking-form-desc">
                        상품을 수령·확인하셨다면 구매확정을 진행해 주세요.
                        구매확정 시 판매자에게 ₩<?php echo number_format((int) $settle_preview['seller_amount']); ?>이 정산됩니다
                        (플랫폼 수수료 <?php echo (int) platform_fee_trade_percent(); ?>% · ₩<?php echo number_format((int) $settle_preview['fee']); ?>).
                        <?php echo $auto_confirm_days; ?>일 이내 구매확정이 없으면 자동으로 구매확정됩니다.
                    </p>
                    <form method="post"
                          action="/trade/proc/trade_payment_fulfill_proc.php"
                          onsubmit="return confirm('구매확정하시겠습니까?\n\n확인 후에는 취소할 수 없으며, 판매자에게 ₩<?php echo number_format((int) $settle_preview['seller_amount']); ?>이 캐시로 정산됩니다.\n(플랫폼 수수료 ₩<?php echo number_format((int) $settle_preview['fee']); ?> 차감)');">
                        <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                        <input type="hidden" name="action" value="confirm_purchase">
                        <button type="submit" class="btn btn-primary btn-block">구매확정</button>
                    </form>
                <?php elseif ($can_confirm_shipping): ?>
                    <p class="auction-order-tracking-form-desc">
                        상품을 받으셨다면 수령 확인을 진행해 주세요.
                        확인 후 구매확정 단계로 넘어갑니다.
                    </p>
                    <form method="post"
                          action="/trade/proc/trade_payment_fulfill_proc.php"
                          onsubmit="return confirm('상품 수령을 확인하시겠습니까?');">
                        <input type="hidden" name="pay_idx" value="<?php echo $pay_idx; ?>">
                        <input type="hidden" name="action" value="confirm_shipping">
                        <button type="submit" class="btn btn-primary btn-block">수령 확인</button>
                    </form>
                <?php elseif ($fulfill >= TRADE_PAYMENT_FULFILL_SHIPPING): ?>
                    <p class="auction-order-tracking-wait">
                        수령 확인이 완료되었습니다. 구매확정을 진행해 주세요.
                        <?php echo $auto_confirm_days; ?>일 이내 구매확정이 없으면 자동으로 구매확정됩니다.
                    </p>
                <?php elseif (!$has_tracking): ?>
                    <p class="auction-order-tracking-wait">판매자가 운송장을 등록하면 수령 확인·구매확정을 진행할 수 있습니다.</p>
                <?php else: ?>
                    <p class="auction-order-tracking-wait">택배 수령 후 「수령 확인」 또는 배송 완료 후 「구매확정」을 진행해 주세요.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
