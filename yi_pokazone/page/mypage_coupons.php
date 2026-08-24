<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_coupon.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_coupons.php'));
}

$mb_idx = (int) $me['mb_idx'];

if (!member_coupon_table_ready() || !coupon_table_ready()) {
    alert_goto('쿠폰 기능이 준비되지 않았습니다.', '/page/mypage.php');
}

$filter = isset($_GET['st']) ? trim((string) $_GET['st']) : 'available';
if (!in_array($filter, ['available', 'used', 'expired', 'all'], true)) {
    $filter = 'available';
}

$page_no    = max(1, (int) ($_GET['p'] ?? 1));
$per_page   = 20;
$offset     = ($page_no - 1) * $per_page;
$list       = coupon_list_for_member($mb_idx, $filter, $per_page, $offset);
$rows       = $list['rows'];
$total      = (int) ($list['total'] ?? 0);
$total_page = max(1, (int) ceil($total / $per_page));

$cnt_available = coupon_count_available_for_member($mb_idx);

$filter_labels = [
    'available' => '사용 가능',
    'used'      => '사용 완료',
    'expired'   => '기간 만료',
    'all'       => '전체',
];

$build_qs = static function (array $o) use ($filter, $page_no) {
    $base = ['st' => $filter, 'p' => $page_no];
    foreach ($o as $k => $v) {
        $base[$k] = $v;
    }
    if (($base['st'] ?? '') === 'available') {
        unset($base['st']);
    }
    if ((int) ($base['p'] ?? 1) <= 1) {
        unset($base['p']);
    }

    return $base ? '?' . http_build_query($base) : '';
};

$page  = 'mypage_coupons';
$title = '내 쿠폰';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '내 쿠폰', 'url' => '/page/mypage_coupons.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage mypage-coupons">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">내 쿠폰</h1>
                <p class="board-desc">발급받은 할인 쿠폰을 확인할 수 있습니다. 바로구매 결제 시 적용할 수 있습니다.</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
                <a href="/trade/trade.php" class="btn btn-primary btn-sm">거래 둘러보기</a>
            </div>
        </div>

        <div class="coupon-wallet-summary">
            <div class="coupon-wallet-summary__inner">
                <div class="coupon-wallet-summary__icon" aria-hidden="true">🎟️</div>
                <div>
                    <p class="coupon-wallet-summary__label">지금 쓸 수 있는 쿠폰</p>
                    <p class="coupon-wallet-summary__count">
                        <strong><?php echo number_format($cnt_available); ?></strong>장
                    </p>
                </div>
            </div>
            <p class="coupon-wallet-summary__hint">바로구매 결제할 때 쿠폰을 골라 할인받을 수 있어요.</p>
        </div>

        <nav class="coupon-filter-tabs" aria-label="쿠폰 상태">
            <?php foreach ($filter_labels as $key => $lab): ?>
                <a href="/page/mypage_coupons.php<?php echo $key === 'available' ? '' : '?st=' . rawurlencode($key); ?>"
                   class="coupon-filter-tab<?php echo $filter === $key ? ' is-active' : ''; ?>">
                    <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($rows)): ?>
            <div class="coupon-empty">
                <div class="coupon-empty__ticket" aria-hidden="true">
                    <span class="coupon-empty__amount">?</span>
                </div>
                <p class="coupon-empty__title">
                    <?php if ($filter === 'available'): ?>
                        아직 쓸 수 있는 쿠폰이 없어요
                    <?php elseif ($filter === 'used'): ?>
                        사용한 쿠폰이 없어요
                    <?php elseif ($filter === 'expired'): ?>
                        만료된 쿠폰이 없어요
                    <?php else: ?>
                        받은 쿠폰이 없어요
                    <?php endif; ?>
                </p>
                <p class="coupon-empty__desc">이벤트나 관리자 발행 쿠폰이 생기면 여기에 모여요.</p>
            </div>
        <?php else: ?>
            <ul class="coupon-ticket-list">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $status_key   = (string) ($row['status_key'] ?? 'available');
                    $is_usable    = $status_key === 'available';
                    $dtype        = (int) ($row['mc_discount_type'] ?? COUPON_DISCOUNT_FIXED);
                    $dval         = (int) ($row['mc_discount_value'] ?? 0);
                    $valid_from   = str_replace('-', '.', (string) ($row['mc_valid_from'] ?? ''));
                    $valid_until  = str_replace('-', '.', (string) ($row['mc_valid_until'] ?? ''));
                    $days_left    = null;
                    if ($is_usable && !empty($row['mc_valid_until'])) {
                        $days_left = (int) floor((strtotime((string) $row['mc_valid_until'] . ' 23:59:59') - time()) / 86400);
                    }
                    $stamp_text = '';
                    if ($status_key === 'used') {
                        $stamp_text = 'USED';
                    } elseif ($status_key === 'expired') {
                        $stamp_text = '만료';
                    } elseif ($status_key === 'waiting') {
                        $stamp_text = '대기';
                    }
                    ?>
                    <li class="coupon-ticket is-<?php echo htmlspecialchars($status_key, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="coupon-ticket__left">
                            <span class="coupon-ticket__brand">POKAZONE</span>
                            <?php if ($dtype === COUPON_DISCOUNT_PERCENT): ?>
                                <div class="coupon-ticket__amount">
                                    <span class="coupon-ticket__value"><?php echo number_format($dval); ?></span>
                                    <span class="coupon-ticket__unit">%</span>
                                </div>
                            <?php else: ?>
                                <div class="coupon-ticket__amount coupon-ticket__amount--won">
                                    <span class="coupon-ticket__won">₩</span>
                                    <span class="coupon-ticket__value"><?php echo number_format($dval); ?></span>
                                </div>
                            <?php endif; ?>
                            <span class="coupon-ticket__off">DISCOUNT</span>
                        </div>
                        <div class="coupon-ticket__divider" aria-hidden="true">
                            <span class="coupon-ticket__notch coupon-ticket__notch--top"></span>
                            <span class="coupon-ticket__dash"></span>
                            <span class="coupon-ticket__notch coupon-ticket__notch--bottom"></span>
                        </div>
                        <div class="coupon-ticket__right">
                            <div class="coupon-ticket__top">
                                <span class="coupon-ticket__status"><?php echo htmlspecialchars((string) ($row['status_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($days_left !== null && $days_left >= 0 && $days_left <= 7): ?>
                                    <span class="coupon-ticket__dday">D-<?php echo $days_left; ?></span>
                                <?php endif; ?>
                            </div>
                            <strong class="coupon-ticket__name"><?php echo htmlspecialchars((string) ($row['mc_name'] ?? '쿠폰'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p class="coupon-ticket__period">
                                <span class="coupon-ticket__period-label">사용기간</span>
                                <?php echo htmlspecialchars($valid_from, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="coupon-ticket__period-sep">~</span>
                                <?php echo htmlspecialchars($valid_until, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <?php if ($status_key === 'used' && (int) ($row['mc_pay_idx'] ?? 0) > 0): ?>
                                <p class="coupon-ticket__meta">
                                    주문 #<?php echo (int) $row['mc_pay_idx']; ?>
                                    <?php if (!empty($row['mc_used_at'])): ?>
                                        · <?php echo htmlspecialchars(substr((string) $row['mc_used_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </p>
                            <?php elseif ($is_usable): ?>
                                <p class="coupon-ticket__meta coupon-ticket__meta--use">바로구매 결제 시 적용 가능</p>
                            <?php endif; ?>
                            <?php if ($stamp_text !== ''): ?>
                                <span class="coupon-ticket__stamp" aria-hidden="true"><?php echo htmlspecialchars($stamp_text, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($total_page > 1): ?>
                <div class="board-pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="/page/mypage_coupons.php<?php echo htmlspecialchars($build_qs(['p' => $page_no - 1]), ENT_QUOTES, 'UTF-8'); ?>">이전</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page_no - 3); $i <= min($total_page, $page_no + 3); $i++): ?>
                        <?php if ($i === $page_no): ?>
                            <span class="is-current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="/page/mypage_coupons.php<?php echo htmlspecialchars($build_qs(['p' => $i]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page_no < $total_page): ?>
                        <a href="/page/mypage_coupons.php<?php echo htmlspecialchars($build_qs(['p' => $page_no + 1]), ENT_QUOTES, 'UTF-8'); ?>">다음</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
