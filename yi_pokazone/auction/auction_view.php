<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/lib/_auction.php';
require_once __DIR__ . '/lib/_auction_order.php';

if (!auction_table_ok()) {
    alert_goto('경매 DB 테이블이 없습니다. sql/tb_auction.sql 을 실행해 주세요.', '/auction/auction.php');
}

$idx = (int) ($_GET['idx'] ?? 0);
if ($idx < 1) {
    alert_goto('잘못된 접근입니다.', '/auction/auction.php');
}

auction_finalize_expired($idx);

$conds = [
    'S' => '민트 (미개봉/완벽)',
    'A' => '상급 (거의새것)',
    'B' => '중급 (사용감있음)',
    'C' => '하급 (보관용)',
];

if (!isset($_SESSION['au_view']) || !is_array($_SESSION['au_view'])) {
    $_SESSION['au_view'] = [];
}
if (!in_array($idx, $_SESSION['au_view'], true)) {
    db_query("UPDATE tb_auction SET au_views = au_views + 1 WHERE au_idx = {$idx} AND au_status = 1");
    $_SESSION['au_view'][] = $idx;
}

$rs = db_query("
    SELECT a.*, m.mb_nick
    FROM tb_auction a
    LEFT JOIN tb_member m ON m.mb_idx = a.mb_idx
    WHERE a.au_idx = {$idx} AND a.au_status = 1
    LIMIT 1
");
$row = db_assoc($rs);
if (!$row) {
    alert_goto('존재하지 않거나 삭제된 경매입니다.', '/auction/auction.php');
}

$images = [];
$rs_img = db_query("SELECT * FROM tb_auction_image WHERE au_idx = {$idx} ORDER BY ai_order ASC, ai_idx ASC");
while ($im = db_assoc($rs_img)) {
    $images[] = $im;
}

$me       = login_member();
$is_owner = $me && (int) $me['mb_idx'] === (int) $row['mb_idx'];
$is_admin = $me && (int) $me['mb_level'] >= 9;
$can_edit = ($is_owner || $is_admin) && (int) ($row['au_bid_count'] ?? 0) === 0;

$display_status = auction_display_status($row);
$status_labels  = auction_status_labels();
$st             = $status_labels[$display_status] ?? $status_labels['live'];
$is_box         = ($row['au_item_type'] ?? '') === 'box';
$is_ended       = $display_status === 'ended';
$ends_ts        = strtotime((string) $row['au_ends_at']);
$remain         = auction_format_remain($ends_ts);
$method_lbl     = '택배';
$cond_lbl       = $conds[$row['au_condition'] ?? ''] ?? '';
$min_bid        = auction_min_bid_amount($row);
$bid_step       = max(1, (int) ($row['au_bid_step'] ?? 1000));
$buy_now_price  = (int) ($row['au_buy_now_price'] ?? 0);
$can_bid        = auction_can_bid($row, $me);
$bid_rows       = auction_fetch_recent_bids($idx, 30);
$last_ab_idx    = 0;
if ($bid_rows !== []) {
    $last_ab_idx = (int) ($bid_rows[0]['ab_idx'] ?? 0);
}
$winner_nick    = '';
$outcome        = auction_outcome($row);
if (!empty($row['au_winner_mb_idx'])) {
    $winner_nick = (string) db_result('SELECT mb_nick FROM tb_member WHERE mb_idx = ' . (int) $row['au_winner_mb_idx'] . ' LIMIT 1');
}

$is_winner      = $me && $outcome === 'won' && (int) ($row['au_winner_mb_idx'] ?? 0) === (int) $me['mb_idx'];
$is_seller_won  = $is_owner && $is_ended && $outcome === 'won';
$is_seller_fail = $is_owner && $is_ended && $outcome === 'failed';
$order_row      = auction_order_get_by_auction($idx);
$order_paid     = auction_order_is_paid($order_row);
$checkout_url   = auction_order_checkout_url($idx);

$page  = 'auction';
$title = (string) $row['au_title'];
$meta_description = mb_substr(strip_tags((string) $row['au_card_name']), 0, 120);
$meta_breadcrumb  = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '경매', 'url' => '/auction/auction.php'],
    ['name' => $row['au_title'], 'url' => '/auction/auction_view.php?idx=' . $idx],
];

$__auction_cd_js = dirname(__DIR__) . '/assets/js/auction-countdown.js';
$__auction_live_js = dirname(__DIR__) . '/assets/js/auction-live.js';
$page_footer_extra = '<script src="/auction/assets/js/auction-countdown.js?v='
    . (is_file($__auction_cd_js) ? filemtime($__auction_cd_js) : time())
    . '" defer></script>';
$page_footer_extra .= '<script src="/auction/assets/js/auction-live.js?v='
    . (is_file($__auction_live_js) ? filemtime($__auction_live_js) : time())
    . '" defer></script>';
$__auction_win_js = dirname(__DIR__) . '/assets/js/auction-win-modal.js';
if ($is_winner && !$order_paid) {
    $page_footer_extra .= '<script src="/auction/assets/js/auction-win-modal.js?v='
        . (is_file($__auction_win_js) ? filemtime($__auction_win_js) : time())
        . '" defer></script>';
}

include __DIR__ . '/../include/header.php';
?>

<section class="community auction-view-page">
    <div class="container">
        <nav class="auction-view-nav">
            <a href="/auction/auction.php" class="link-back">← 경매 목록</a>
        </nav>

        <?php if ($is_seller_won): ?>
            <div class="auction-seller-banner auction-seller-banner--won" role="status">
                <strong>낙찰 완료</strong>
                <span>
                    <?php echo htmlspecialchars($winner_nick !== '' ? $winner_nick : '낙찰자'); ?>님에게
                    ₩<?php echo number_format((int) $row['au_current_price']); ?>에 낙찰되었습니다.
                    <?php if ($order_paid): ?>
                        · 구매자 <em>결제 완료</em> (<?php echo htmlspecialchars(auction_order_status_label((int) ($order_row['ao_order_status'] ?? 1))); ?>)
                    <?php else: ?>
                        · 구매자 <em>결제 대기</em> 중
                    <?php endif; ?>
                </span>
            </div>
        <?php elseif ($is_seller_fail): ?>
            <div class="auction-seller-banner auction-seller-banner--failed" role="status">
                <strong>유찰</strong>
                <span>입찰자가 없어 경매가 종료되었습니다. 필요 시 다시 등록해 주세요.</span>
            </div>
        <?php endif; ?>

        <article class="auction-detail">
            <header class="auction-detail-head">
                <div class="auction-detail-badges">
                    <span class="deal-status <?php echo htmlspecialchars($st['class']); ?>"><?php echo htmlspecialchars($st['label']); ?></span>
                    <?php if ($is_ended && $outcome === 'won'): ?>
                        <span class="deal-status ds-won">낙찰완료</span>
                    <?php elseif ($is_ended && $outcome === 'failed'): ?>
                        <span class="deal-status ds-failed">유찰</span>
                    <?php endif; ?>
                    <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                        <?php echo $is_box ? '📦 상자' : '카드'; ?>
                    </span>
                    <?php $ck_lbl = auction_category_label($row); ?>
                    <?php if ($ck_lbl !== '카드'): ?>
                        <span class="item-kind-chip"><?php echo htmlspecialchars($ck_lbl); ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="auction-detail-title"><?php echo htmlspecialchars($row['au_title']); ?></h1>
                <p class="auction-detail-sub">
                    <?php echo htmlspecialchars($row['au_card_name']); ?>
                    <?php if ($is_box && (int) $row['au_box_qty'] > 1): ?>
                        <span class="trade-qty">×<?php echo (int) $row['au_box_qty']; ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['au_grade'])): ?>
                        <span class="trade-grade"><?php echo htmlspecialchars($row['au_grade']); ?></span>
                    <?php endif; ?>
                </p>
                <ul class="auction-detail-meta">
                    <li><?php echo htmlspecialchars($row['mb_nick'] ?? '(탈퇴)'); ?></li>
                    <li>조회 <?php echo number_format((int) $row['au_views']); ?></li>
                    <li>마감 <?php echo date('Y-m-d H:i', $ends_ts); ?></li>
                </ul>
            </header>

            <div class="auction-view-layout">
                <aside class="auction-bid-card"
                       data-auction-live
                       data-au-idx="<?php echo $idx; ?>"
                       data-last-ab-idx="<?php echo $last_ab_idx; ?>"
                       data-auction-api="<?php echo htmlspecialchars(public_url('/auction/auction_api.php'), ENT_QUOTES, 'UTF-8'); ?>"
                       <?php if ($is_ended): ?>data-auction-ended="1"<?php endif; ?>
                       aria-label="입찰 정보">
                    <div class="auction-bid-card__timer<?php echo $display_status === 'ending' ? ' is-urgent' : ''; ?><?php echo $is_ended ? ' is-ended' : ''; ?>">
                        <span class="auction-bid-card__timer-label"><?php echo $is_ended ? '경매 종료' : '남은 시간'; ?></span>
                        <span class="auction-meta-time auction-bid-card__timer-value"
                              data-auction-remain
                              <?php if (!$is_ended && $ends_ts > 0): ?>
                                  data-auction-ends="<?php echo (int) $ends_ts; ?>"
                              <?php endif; ?>>
                            <?php echo htmlspecialchars($remain); ?>
                        </span>
                    </div>

                    <div class="auction-bid-card__price">
                        <span class="auction-price-label"><?php echo $is_ended ? '최종가' : '현재가'; ?></span>
                        <p class="auction-bid-card__current">
                            <span class="auction-bid-card__currency">₩</span><span data-auction-current-price><?php echo number_format((int) $row['au_current_price']); ?></span>
                        </p>
                        <?php if ($buy_now_price > 0 && !$is_ended): ?>
                            <p class="auction-bid-card__buynow">
                                즉시구매 <strong>₩<?php echo number_format($buy_now_price); ?></strong>
                            </p>
                        <?php endif; ?>
                        <?php if ($is_ended && $outcome === 'won' && $winner_nick !== ''): ?>
                            <p class="auction-bid-card__winner">
                                낙찰 <strong><?php echo htmlspecialchars($winner_nick); ?></strong>
                            </p>
                        <?php elseif ($is_ended && $outcome === 'failed'): ?>
                            <p class="auction-bid-card__failed">
                                <strong>유찰</strong> · 입찰자 없음
                            </p>
                        <?php endif; ?>
                    </div>

                    <ul class="auction-bid-stats">
                        <li>
                            <span class="auction-bid-stats__label">시작가</span>
                            <span class="auction-bid-stats__value">₩<?php echo number_format((int) $row['au_start_price']); ?></span>
                        </li>
                        <li>
                            <span class="auction-bid-stats__label">입찰</span>
                            <span class="auction-bid-stats__value" data-auction-bid-count><?php echo number_format((int) $row['au_bid_count']); ?>회</span>
                        </li>
                        <li>
                            <span class="auction-bid-stats__label">단위</span>
                            <span class="auction-bid-stats__value">₩<?php echo number_format($bid_step); ?></span>
                        </li>
                    </ul>

                    <section class="auction-bid-history auction-bid-history--sidebar" aria-labelledby="auction-bid-history-title">
                        <div class="auction-bid-history__head">
                            <h2 id="auction-bid-history-title">입찰 내역</h2>
                            <span class="auction-bid-history__count" data-auction-bids-count><?php echo number_format((int) $row['au_bid_count']); ?>건</span>
                        </div>
                        <ol class="auction-bid-list" data-auction-bids-list>
                            <?php echo auction_render_bid_list_items($bid_rows, $me ? (int) $me['mb_idx'] : null); ?>
                        </ol>
                    </section>

                    <?php if ($can_bid['ok']): ?>
                        <form class="auction-bid-form" method="post" action="<?php echo htmlspecialchars(public_url('/auction/proc/auction_bid_proc.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="au_idx" value="<?php echo $idx; ?>">
                            <input type="hidden" name="action" value="bid">
                            <div class="auction-bid-field">
                                <label for="ab_amount">입찰가</label>
                                <div class="auction-bid-input-wrap">
                                    <span class="auction-bid-input-prefix" aria-hidden="true">₩</span>
                                    <input type="number" id="ab_amount" name="ab_amount"
                                           min="<?php echo $min_bid; ?>"
                                           max="<?php echo max($min_bid, (int) ($can_bid['cash_balance'] ?? 0)); ?>"
                                           step="<?php echo $bid_step; ?>"
                                           value="<?php echo $min_bid; ?>" required inputmode="numeric"
                                           aria-describedby="ab_amount_help">
                                </div>
                                <p id="ab_amount_help" class="auction-bid-field-help" data-auction-min-help>
                                    최소 입찰가 <strong>₩<?php echo number_format($min_bid); ?></strong>
                                </p>
                            </div>
                            <div class="auction-bid-quick" role="group" aria-label="빠른 입찰가">
                                <button type="button" class="auction-bid-chip" data-bid-quick="<?php echo $min_bid; ?>">최소</button>
                                <button type="button" class="auction-bid-chip" data-bid-quick="<?php echo $min_bid + $bid_step; ?>">+1</button>
                                <button type="button" class="auction-bid-chip" data-bid-quick="<?php echo $min_bid + $bid_step * 2; ?>">+2</button>
                                <button type="button" class="auction-bid-chip" data-bid-quick="<?php echo $min_bid + $bid_step * 5; ?>">+5</button>
                            </div>
                            <div class="auction-bid-actions">
                                <button type="submit" class="btn btn-primary btn-block auction-bid-submit">입찰하기</button>
                                <?php if ($buy_now_price > 0): ?>
                                    <button type="submit" class="btn btn-outline btn-block" name="action" value="buy_now" data-auction-buy-now>
                                        즉시구매 ₩<?php echo number_format($buy_now_price); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_winner && !$order_paid): ?>
                                <a href="<?php echo htmlspecialchars($checkout_url); ?>" class="btn btn-primary btn-block auction-win-checkout-btn">
                                    낙찰정보 입력하러 가기
                                </a>
                            <?php elseif ($is_winner && $order_paid): ?>
                                <a href="<?php echo htmlspecialchars($checkout_url); ?>" class="btn btn-outline btn-block">
                                    주문 내역 보기
                                </a>
                            <?php endif; ?>
                            <p class="auction-bid-field-help" style="margin-top:8px;">
                                보유 캐시 <strong>₩<?php echo number_format((int) ($can_bid['cash_balance'] ?? 0)); ?></strong>
                                · 입찰가 이하로만 입찰할 수 있습니다
                            </p>
                            <p class="auction-bid-note">
                                <span class="auction-bid-note__icon" aria-hidden="true">📦</span>
                                낙찰 후 택배 거래 · 입찰 취소 불가
                            </p>
                        </form>
                    <?php elseif ($can_bid['reason'] === 'login'): ?>
                        <div class="auction-bid-state auction-bid-state--guest">
                            <p class="auction-bid-state__lead">로그인 후 입찰할 수 있습니다.</p>
                            <div class="auction-bid-state__actions">
                                <a href="/login.php?return=<?php echo urlencode('/auction/auction_view.php?idx=' . $idx); ?>" class="btn btn-primary btn-block">로그인</a>
                                <a href="/page/register.php" class="btn btn-outline btn-block">회원가입</a>
                            </div>
                        </div>
                    <?php elseif (!empty($can_bid['need_cash'])): ?>
                        <div class="auction-bid-state auction-bid-state--muted">
                            <p class="auction-bid-state__lead"><?php echo htmlspecialchars((string) $can_bid['reason']); ?></p>
                            <p style="margin:8px 0 0;font-size:0.9em;">
                                현재 보유 캐시 ₩<?php echo number_format((int) ($can_bid['cash_balance'] ?? 0)); ?>
                            </p>
                            <div class="auction-bid-state__actions" style="margin-top:12px;">
                                <a href="/page/mypage_cash.php" class="btn btn-primary btn-block">캐시 내역 보기</a>
                            </div>
                        </div>
                    <?php elseif (!empty($can_bid['eligibility']) && empty($can_bid['eligibility']['ok'])): ?>
                        <?php $bid_elig = $can_bid['eligibility']; ?>
                        <div class="auction-bid-state auction-bid-state--muted">
                            <p class="auction-bid-state__lead">경매 참여 조건을 확인해 주세요.</p>
                            <ul style="margin:10px 0 0;padding-left:1.2em;text-align:left;">
                                <li>
                                    구매확정 완료 거래 <?php echo (int) ($bid_elig['required'] ?? 3); ?>건 이상
                                    — 현재 <?php echo (int) ($bid_elig['completed'] ?? 0); ?>건
                                    (<a href="/trade/trade.php">거래게시판</a>)
                                </li>
                            </ul>
                            <p style="margin:12px 0 0;font-size:0.9em;">
                                레벨 <?php echo (int) member_auction_eligibility_level_bypass(); ?> 이상은 조건 없이 참여할 수 있습니다.
                            </p>
                        </div>
                    <?php elseif ($is_seller_won): ?>
                        <div class="auction-bid-state auction-bid-state--seller auction-bid-state--seller-won">
                            <p class="auction-bid-state__lead"><strong>낙찰 완료</strong> · 등록하신 경매가 마감되었습니다.</p>
                            <dl class="auction-seller-summary">
                                <div>
                                    <dt>낙찰자</dt>
                                    <dd><?php echo htmlspecialchars($winner_nick !== '' ? $winner_nick : '—'); ?></dd>
                                </div>
                                <div>
                                    <dt>최종 낙찰가</dt>
                                    <dd>₩<?php echo number_format((int) $row['au_current_price']); ?></dd>
                                </div>
                                <div>
                                    <dt>결제</dt>
                                    <dd>
                                        <?php if ($order_paid): ?>
                                            결제 완료
                                            <?php if (!empty($order_row['ao_paid_at'])): ?>
                                                <span class="auction-seller-summary-muted"><?php echo htmlspecialchars((string) $order_row['ao_paid_at']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="auction-seller-summary-wait">결제 대기</span>
                                            <span class="auction-seller-summary-muted">구매자가 배송·결제 정보를 입력할 때까지 대기합니다.</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php if ($order_paid): ?>
                                    <div>
                                        <dt>주문 상태</dt>
                                        <dd><?php echo htmlspecialchars(auction_order_status_label((int) ($order_row['ao_order_status'] ?? 1))); ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                            <p class="auction-seller-summary-note">
                                <?php if ($order_paid): ?>
                                    구매자 결제가 확인되었습니다. 택배 발송을 진행해 주세요.
                                <?php else: ?>
                                    구매자 결제가 완료되면 택배 발송을 진행해 주세요.
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo htmlspecialchars($checkout_url); ?>" class="btn btn-primary btn-block auction-seller-order-btn">
                                낙찰 정보 확인하러 가기
                            </a>
                        </div>
                    <?php elseif ($is_seller_fail): ?>
                        <div class="auction-bid-state auction-bid-state--seller auction-bid-state--seller-failed">
                            <p class="auction-bid-state__lead"><strong>유찰</strong> · 입찰자가 없어 판매되지 않았습니다.</p>
                            <p class="auction-seller-summary-note">가격·기간을 조정해 다시 등록해 보실 수 있습니다.</p>
                        </div>
                    <?php elseif ($is_owner && $is_ended): ?>
                        <div class="auction-bid-state auction-bid-state--muted">
                            <p>경매가 종료되었습니다. 결과를 확인 중입니다.</p>
                        </div>
                    <?php elseif ($is_owner): ?>
                        <div class="auction-bid-state auction-bid-state--muted">
                            <p>본인이 등록한 경매에는 입찰할 수 없습니다.</p>
                        </div>
                    <?php elseif ($is_ended && $outcome === 'won'): ?>
                        <div class="auction-bid-state auction-bid-state--ended">
                            <?php if ($is_winner && !$order_paid): ?>
                                <p>축하합니다! <strong>낙찰</strong>되었습니다. 배송지와 결제 정보를 입력해 주세요.</p>
                                <a href="<?php echo htmlspecialchars($checkout_url); ?>" class="btn btn-primary btn-block">낙찰정보 입력하러 가기</a>
                            <?php elseif ($is_winner && $order_paid): ?>
                                <p>낙찰 주문·결제가 완료되었습니다.</p>
                                <a href="<?php echo htmlspecialchars($checkout_url); ?>" class="btn btn-outline btn-block">주문 내역 보기</a>
                            <?php else: ?>
                                <p>낙찰되었습니다. 판매자와 택배 거래를 진행해 주세요.</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($is_ended && $outcome === 'failed'): ?>
                        <div class="auction-bid-state auction-bid-state--ended">
                            <p>입찰자가 없어 <strong>유찰</strong>되었습니다.</p>
                        </div>
                    <?php elseif ($is_ended): ?>
                        <div class="auction-bid-state auction-bid-state--ended">
                            <p>이 경매는 종료되었습니다.</p>
                        </div>
                    <?php else: ?>
                        <div class="auction-bid-state auction-bid-state--muted">
                            <p><?php echo htmlspecialchars($can_bid['reason']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_admin && (int) ($row['au_auction_status'] ?? 0) === 1): ?>
                        <div class="auction-test-tools">
                            <p class="auction-test-tools__label">테스트 (관리자)</p>
                            <form method="post" action="/auction/proc/auction_test_finalize_proc.php" class="auction-test-tools__form"
                                  onsubmit="return confirm('테스트: 최고 입찰자에게 낙찰 처리합니다. 계속할까요?');">
                                <input type="hidden" name="au_idx" value="<?php echo $idx; ?>">
                                <input type="hidden" name="action" value="win">
                                <button type="submit" class="btn btn-sm btn-primary">테스트 낙찰</button>
                            </form>
                            <form method="post" action="/auction/proc/auction_test_finalize_proc.php" class="auction-test-tools__form"
                                  onsubmit="return confirm('테스트: 유찰 처리합니다. 계속할까요?');">
                                <input type="hidden" name="au_idx" value="<?php echo $idx; ?>">
                                <input type="hidden" name="action" value="fail">
                                <button type="submit" class="btn btn-sm btn-outline">테스트 유찰</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </aside>

                <div class="auction-view-main">
                    <?php if (!empty($images)): ?>
                        <?php
                        $alt_base  = ($is_box ? '미개봉 상자 ' : '포켓몬 카드 ') . $row['au_card_name'];
                        $glb_id    = 'auction-view-' . $idx;
                        $first_src = public_url($images[0]['ai_path']);
                        ?>
                        <figure class="gallery auction-view-gallery" data-gallery>
                            <div class="gallery-main" data-gallery-main>
                                <div class="gallery-main__viewport">
                                    <img src="<?php echo htmlspecialchars($first_src); ?>"
                                         alt="<?php echo htmlspecialchars($alt_base . ' 대표 이미지'); ?>"
                                         data-main-image
                                         fetchpriority="high"
                                         decoding="async">
                                    <div class="gallery-magnifier" hidden data-gallery-lens aria-hidden="true">
                                        <img src="" alt="" class="gallery-magnifier__img" draggable="false" decoding="async">
                                    </div>
                                    <a href="<?php echo htmlspecialchars($first_src); ?>"
                                       class="gallery-zoom-btn glightbox"
                                       data-gallery="<?php echo htmlspecialchars($glb_id, ENT_QUOTES, 'UTF-8'); ?>"
                                       data-gallery-zoom
                                       aria-label="원본 이미지 크게 보기">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="11" cy="11" r="7"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    </a>
                                </div>
                                <p class="gallery-hint">마우스를 올리면 확대 · 돋보기로 전체 화면</p>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="gallery-glightbox-set" hidden aria-hidden="true">
                                    <?php foreach ($images as $i => $img):
                                        if ($i === 0) {
                                            continue;
                                        }
                                        $src = public_url($img['ai_path']);
                                        ?>
                                        <a href="<?php echo htmlspecialchars($src); ?>"
                                           class="glightbox"
                                           data-gallery="<?php echo htmlspecialchars($glb_id, ENT_QUOTES, 'UTF-8'); ?>"></a>
                                    <?php endforeach; ?>
                                </div>
                                <div class="gallery-thumbs" role="list">
                                    <?php foreach ($images as $i => $img):
                                        $src = public_url($img['ai_path']);
                                        ?>
                                        <button type="button"
                                                class="gallery-thumb <?php echo $i === 0 ? 'is-active' : ''; ?>"
                                                data-src="<?php echo htmlspecialchars($src); ?>"
                                                aria-label="<?php echo htmlspecialchars($alt_base . ' 이미지 ' . ($i + 1)); ?>">
                                            <img src="<?php echo htmlspecialchars($src); ?>"
                                                 alt="<?php echo htmlspecialchars($alt_base . ' 이미지 ' . ($i + 1)); ?>"
                                                 loading="lazy" decoding="async">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>

                    <div class="auction-spec-card">
                        <h2 class="auction-spec-card__title">상품 정보</h2>
                        <dl class="auction-spec-list">
                            <?php if (!$is_box && $cond_lbl !== ''): ?>
                                <div><dt>상태</dt><dd><?php echo htmlspecialchars($cond_lbl); ?></dd></div>
                            <?php endif; ?>
                            <div><dt>거래 방식</dt><dd><?php echo htmlspecialchars($method_lbl); ?></dd></div>
                            <?php if (!empty($row['au_region'])): ?>
                                <div><dt>발송 지역</dt><dd><?php echo htmlspecialchars($row['au_region']); ?></dd></div>
                            <?php endif; ?>
                            <div><dt>경매 시작</dt><dd><?php echo date('Y-m-d H:i', strtotime((string) $row['au_starts_at'])); ?></dd></div>
                        </dl>
                    </div>

                    <?php if (trim(strip_tags((string) $row['au_content'])) !== ''): ?>
                        <div class="auction-detail-body post-content">
                            <h2 class="auction-spec-card__title">상세 설명</h2>
                            <?php echo community_render_post_body((string) $row['au_content']); ?>
                        </div>
                    <?php endif; ?>


                    <?php if ($can_edit): ?>
                    <div class="auction-detail-foot">
                        <a href="/auction/auction_write.php?idx=<?php echo $idx; ?>" class="btn btn-outline">수정</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </div>
</section>

<?php if ($is_winner && !$order_paid): ?>
<div id="auction-win-modal"
     class="auction-win-modal"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="auction-win-modal-title"
     data-au-idx="<?php echo $idx; ?>"
     data-checkout-url="<?php echo htmlspecialchars($checkout_url, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="auction-win-modal__backdrop" data-auction-win-dismiss></div>
    <div class="auction-win-modal__panel">
        <p class="auction-win-modal__badge" aria-hidden="true">🎉</p>
        <h2 id="auction-win-modal-title" class="auction-win-modal__title">낙찰되었습니다!</h2>
        <p class="auction-win-modal__desc">
            <strong><?php echo htmlspecialchars((string) $row['au_title']); ?></strong>에
            <strong>₩<?php echo number_format((int) $row['au_current_price']); ?></strong> 으로 낙찰되었습니다.
        </p>
        <p class="auction-win-modal__lead">배송지와 결제 정보를 입력해 주문을 완료해 주세요.</p>
        <div class="auction-win-modal__actions">
            <a href="<?php echo htmlspecialchars($checkout_url); ?>" class="btn btn-primary btn-block">낙찰정보 입력하러 가기</a>
            <button type="button" class="btn btn-outline btn-block" data-auction-win-dismiss>나중에</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($images)): ?>
<script>
(function(){
    const main = document.querySelector('[data-main-image]');
    const thumbs = document.querySelectorAll('.gallery-thumb');
    const zoomLink = document.querySelector('[data-gallery-zoom]');
    thumbs.forEach(btn => {
        btn.addEventListener('click', () => {
            if (!main) return;
            const src = btn.dataset.src;
            main.src = src;
            if (zoomLink) zoomLink.href = src;
            thumbs.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
        });
    });

    const mqLens = window.matchMedia('(hover: hover) and (pointer: fine)');
    const root = document.querySelector('[data-gallery-main]');
    const lens = root && root.querySelector('[data-gallery-lens]');
    const lensImg = lens && lens.querySelector('.gallery-magnifier__img');
    const viewport = root && root.querySelector('.gallery-main__viewport');
    const ZOOM = 2;
    let raf = 0;

    if (!main || !root || !lens || !lensImg || !viewport || !mqLens.matches) return;

    function visibleImageRect(img) {
        const r = img.getBoundingClientRect();
        const nw = img.naturalWidth;
        const nh = img.naturalHeight;
        if (!nw || !nh) return { left: r.left, top: r.top, width: r.width, height: r.height };
        const cw = r.width;
        const ch = r.height;
        const ir = nw / nh;
        const cr = cw / ch;
        if (ir > cr) {
            const h = cw / ir;
            return { left: r.left, top: r.top + (ch - h) / 2, width: cw, height: h };
        }
        const w = ch * ir;
        return { left: r.left + (cw - w) / 2, top: r.top, width: w, height: ch };
    }

    function hideLens() {
        lens.hidden = true;
        lensImg.removeAttribute('src');
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
    }

    function posLens(clientX, clientY, imgRect, px, py) {
        const L = lens.offsetWidth || 140;
        const Dw = imgRect.width * ZOOM;
        const Dh = imgRect.height * ZOOM;
        lens.style.left = (clientX - L / 2) + 'px';
        lens.style.top = (clientY - L / 2) + 'px';
        let imgLeft = L / 2 - px * ZOOM;
        let imgTop = L / 2 - py * ZOOM;
        imgLeft = Math.min(0, Math.max(L - Dw, imgLeft));
        imgTop = Math.min(0, Math.max(L - Dh, imgTop));
        lensImg.style.width = Dw + 'px';
        lensImg.style.height = Dh + 'px';
        lensImg.style.left = imgLeft + 'px';
        lensImg.style.top = imgTop + 'px';
    }

    function syncLensSrc() {
        const next = main.currentSrc || main.src;
        if (!next || !main.complete || main.naturalWidth === 0) return;
        if (lensImg.src !== next) lensImg.src = next;
    }

    viewport.addEventListener('pointerenter', (e) => {
        if (!mqLens.matches || e.pointerType === 'touch') return;
        syncLensSrc();
        if (main.naturalWidth) lens.hidden = false;
    });
    viewport.addEventListener('pointerleave', hideLens);
    viewport.addEventListener('pointermove', (e) => {
        if (!mqLens.matches || e.pointerType === 'touch') return;
        if (raf) cancelAnimationFrame(raf);
        const ev = e;
        raf = requestAnimationFrame(() => {
            raf = 0;
            if (!main.naturalWidth) return;
            const vis = visibleImageRect(main);
            const px = ev.clientX - vis.left;
            const py = ev.clientY - vis.top;
            if (px < 0 || py < 0 || px > vis.width || py > vis.height) {
                hideLens();
                return;
            }
            syncLensSrc();
            lens.hidden = false;
            posLens(ev.clientX, ev.clientY, vis, px, py);
        });
    });
    main.addEventListener('load', syncLensSrc);
    mqLens.addEventListener('change', (m) => { if (!m.matches) hideLens(); });
    window.addEventListener('scroll', hideLens, true);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>
