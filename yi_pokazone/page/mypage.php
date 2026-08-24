<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../auction/lib/_auction.php';
require_once __DIR__ . '/../lib/_member_cash.php';
require_once __DIR__ . '/../lib/_coupon.php';
trade_chat_payment_lib_load();

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage.php'));
}

$mb_idx = (int)$me['mb_idx'];
$rs     = db_query("
    SELECT mb_idx, mb_id, mb_name, mb_nick, mb_email, mb_phone, mb_level, mb_status,
           mb_point, mb_login_count, mb_last_login_at, mb_last_login_ip, mb_created_at
    FROM tb_member
    WHERE mb_idx = {$mb_idx}
    LIMIT 1
");
$member = db_assoc($rs);
if (!$member) {
    alert_goto('회원 정보를 찾을 수 없습니다.', '/logout.php');
}

$cnt_community = (int) db_result("SELECT COUNT(*) FROM tb_community WHERE mb_idx = {$mb_idx} AND co_status = 1");
$cnt_trade     = (int) db_result("SELECT COUNT(*) FROM tb_trade WHERE mb_idx = {$mb_idx} AND " . trade_status_public_sql(''));
$cnt_inquiry   = (int) db_result("SELECT COUNT(*) FROM tb_inquiry WHERE mb_idx = {$mb_idx} AND iq_status <> 9");

$cnt_purchase = 0;
$cnt_sale     = 0;
$cnt_sale_pending_ship = 0;
if (trade_chat_payment_lib_load() && function_exists('trade_purchase_buyer_count')) {
    $cnt_purchase = trade_purchase_buyer_count($mb_idx);
}
if (function_exists('trade_sale_seller_count')) {
    $cnt_sale = trade_sale_seller_count($mb_idx);
}
if (function_exists('trade_sale_seller_pending_ship_count')) {
    $cnt_sale_pending_ship = trade_sale_seller_pending_ship_count($mb_idx);
}

$recent_co = [];
$rs        = db_query("
    SELECT co_idx, co_title, co_created_at
    FROM tb_community
    WHERE mb_idx = {$mb_idx} AND co_status = 1
    ORDER BY co_idx DESC
    LIMIT 5
");
while ($r = db_assoc($rs)) {
    $recent_co[] = $r;
}

$recent_tr = [];
$rs        = db_query("
    SELECT tr_idx, tr_title, tr_created_at, tr_status
    FROM tb_trade
    WHERE mb_idx = {$mb_idx} AND " . trade_status_public_sql('') . "
    ORDER BY tr_idx DESC
    LIMIT 5
");
while ($r = db_assoc($rs)) {
    $recent_tr[] = $r;
}

$auction_ok       = auction_table_ok();
$cnt_auction      = 0;
$cnt_auction_win  = 0;
$recent_auction   = [];
$recent_au_win    = [];
if ($auction_ok) {
    $cnt_auction = (int) db_result("SELECT COUNT(*) FROM tb_auction WHERE mb_idx = {$mb_idx} AND au_status = 1");
    $cnt_auction_win = (int) db_result("SELECT COUNT(*) FROM tb_auction WHERE au_winner_mb_idx = {$mb_idx} AND au_status = 1");
    $rs = db_query("
        SELECT au_idx, au_title, au_current_price, au_auction_status, au_status,
               au_starts_at, au_ends_at, au_created_at
        FROM tb_auction
        WHERE mb_idx = {$mb_idx} AND au_status = 1
        ORDER BY au_idx DESC
        LIMIT 5
    ");
    while ($r = db_assoc($rs)) {
        $recent_auction[] = $r;
    }
    $rs = db_query("
        SELECT au_idx, au_title, au_current_price, au_auction_status, au_status,
               au_starts_at, au_ends_at, au_updated_at, au_created_at
        FROM tb_auction
        WHERE au_winner_mb_idx = {$mb_idx} AND au_status = 1
        ORDER BY au_idx DESC
        LIMIT 5
    ");
    while ($r = db_assoc($rs)) {
        $recent_au_win[] = $r;
    }
}

$level       = (int) $member['mb_level'];
$level_label = $level >= 9 ? '관리자' : ($level >= 5 ? 'VIP' : ($level >= 3 ? '인증' : '일반'));
$level_class = $level >= 9 ? 'is-admin' : ($level >= 5 ? 'is-vip' : '');

$st            = (int) $member['mb_status'];
$status_labels = [
    0 => '휴면',
    1 => '정상',
    2 => '정지',
    3 => '탈퇴',
];
$status_label = $status_labels[$st] ?? '알 수 없음';

$cash_ready     = member_cash_column_ready();
$cash_log_ready = member_cash_log_table_ready();
$cash_bal          = $cash_ready ? member_cash_balance($mb_idx) : 0;
$coupon_ready      = member_coupon_table_ready() && coupon_table_ready();
$cnt_coupon        = $coupon_ready ? coupon_count_available_for_member($mb_idx) : 0;
$wish_ready        = db_table_exists('tb_trade_like');
$cnt_wish          = $wish_ready ? (int) db_result("SELECT COUNT(*) FROM tb_trade_like WHERE mb_idx = {$mb_idx}") : 0;
$auction_ban_ready = member_auction_ban_column_ready();
$is_auction_banned = $auction_ban_ready && member_auction_is_banned($mb_idx);

/** 회원정보·바로가기 접기 상태 (쿠키 pz_mypage_fold=member:1,quick:0) */
$mypage_fold_state = ['member' => null, 'quick' => null];
$mypage_fold_raw   = trim((string) ($_COOKIE['pz_mypage_fold'] ?? ''));
if ($mypage_fold_raw !== '' && strpos($mypage_fold_raw, '%') !== false) {
    $mypage_fold_raw = rawurldecode($mypage_fold_raw);
}
if ($mypage_fold_raw !== '') {
    foreach (explode(',', $mypage_fold_raw) as $__fold_part) {
        $__fold_pair = explode(':', $__fold_part, 2);
        if (count($__fold_pair) !== 2) {
            continue;
        }
        $__fold_key = trim($__fold_pair[0]);
        if (!array_key_exists($__fold_key, $mypage_fold_state)) {
            continue;
        }
        $mypage_fold_state[$__fold_key] = trim($__fold_pair[1]) === '1';
    }
}
$mypage_fold_has_cookie = $mypage_fold_state['member'] !== null || $mypage_fold_state['quick'] !== null;
// 쿠키 없으면 PC 기본 펼침. 모바일 기본 접힘은 아래 스크립트에서 쿠키로 저장.
$mypage_fold_member_open = $mypage_fold_state['member'] !== null ? (bool) $mypage_fold_state['member'] : true;
$mypage_fold_quick_open  = $mypage_fold_state['quick'] !== null ? (bool) $mypage_fold_state['quick'] : true;

$page  = 'mypage';
$title = '마이페이지';
$meta_description = 'Pokazone 마이페이지에서 내 프로필과 활동 내역을 확인하세요.';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">마이페이지</h1>
                <p class="board-desc">내 계정 정보와 최근 활동을 한눈에 확인해 보세요.</p>
            </div>
            <div class="mypage-head-actions">
                <?php if ($cash_ready): ?>
                <a href="/page/mypage_cash_charge.php" class="btn btn-primary btn-sm">캐시 충전</a>
                <?php endif; ?>
                <a href="/page/member_info.php" class="btn btn-outline btn-sm">내 정보</a>
                <a href="/page/member_shop_settings.php" class="btn btn-outline btn-sm">상점 설정</a>
                <a href="/page/member_address.php" class="btn btn-outline btn-sm">배송지</a>
                <a href="/page/alarm_settings.php" class="btn btn-outline btn-sm">알림 설정</a>
                <!-- <a href="/page/community_write.php" class="btn btn-outline btn-sm">커뮤니티 글쓰기</a> -->
                <!-- <a href="/trade/trade_write.php" class="btn btn-primary btn-sm">거래글 등록</a> -->
                <a href="/logout.php" class="btn btn-sm mypage-logout-btn">로그아웃</a>
            </div>
        </div>

        <?php if ($is_auction_banned): ?>
        <div class="mypage-auction-ban-alert" role="alert">
            <strong>경매 이용 제한</strong>
            <p><?php echo htmlspecialchars(member_auction_ban_user_message()); ?></p>
        </div>
        <?php endif; ?>

        <div class="mypage-hero">
            <div class="mypage-avatar" aria-hidden="true"><?php echo mb_substr(htmlspecialchars($member['mb_nick']), 0, 1); ?></div>
            <div class="mypage-hero-body">
                <div class="mypage-hero-top">
                    <h2 class="mypage-nick"><?php echo htmlspecialchars($member['mb_nick']); ?></h2>
                    <span class="mypage-level <?php echo $level_class; ?>"><?php echo htmlspecialchars($level_label); ?></span>
                </div>
                <p class="mypage-id"><?php echo htmlspecialchars($member['mb_id']); ?></p>
                <div class="mypage-hero-meta">
                    <span>포인트 <strong><?php echo number_format((int) $member['mb_point']); ?></strong> P · <a href="/page/attendance.php" class="mypage-inline-link">출석/내역</a></span>
                    <?php if ($cash_ready): ?>
                    <span>보유 캐시 <strong class="mypage-cash">₩<?php echo number_format($cash_bal); ?></strong>
                        · <a href="/page/mypage_cash_charge.php" class="mypage-inline-link">캐시 충전</a>
                        <?php if ($cash_log_ready): ?>
                        · <a href="/page/mypage_cash.php" class="mypage-inline-link">캐시 내역</a>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($coupon_ready): ?>
                    <span>사용 가능 쿠폰 <strong><?php echo number_format($cnt_coupon); ?></strong>장
                        · <a href="/page/mypage_coupons.php" class="mypage-inline-link">내 쿠폰</a>
                    </span>
                    <?php endif; ?>
                    <span>가입일 <?php echo date('Y-m-d', strtotime($member['mb_created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="mypage-stats">
            <a href="/page/community.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_community); ?></span>
                <span class="mypage-stat-label">커뮤니티 글</span>
            </a>
            <a href="/trade/trade.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_trade); ?></span>
                <span class="mypage-stat-label">거래글</span>
            </a>
            <a href="/page/inquiry.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_inquiry); ?></span>
                <span class="mypage-stat-label">1:1 문의</span>
            </a>
            <?php if ($wish_ready): ?>
            <a href="/page/mypage_wishes.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_wish); ?></span>
                <span class="mypage-stat-label">찜 리스트</span>
            </a>
            <?php endif; ?>
            <?php if (function_exists('trade_purchase_tables_ready') && trade_purchase_tables_ready()): ?>
            <a href="/trade/trade_purchases.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_purchase); ?></span>
                <span class="mypage-stat-label">구매내역</span>
            </a>
            <a href="/trade/trade_sales.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_sale); ?></span>
                <span class="mypage-stat-label">판매내역</span>
            </a>
            <?php endif; ?>
            <?php if ($auction_ok): ?>
            <a href="/auction/mypage_auction.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_auction); ?></span>
                <span class="mypage-stat-label">나의 경매</span>
            </a>
            <a href="/auction/mypage_auction_wins.php" class="mypage-stat">
                <span class="mypage-stat-value"><?php echo number_format($cnt_auction_win); ?></span>
                <span class="mypage-stat-label">나의 낙찰</span>
            </a>
            <?php endif; ?>
        </div>

        <div class="mypage-panels">
            <details class="mypage-panel mypage-panel--fold" data-mypage-fold="member"<?php echo $mypage_fold_member_open ? ' open' : ''; ?>>
                <summary class="mypage-panel-summary">
                    <h3 class="mypage-panel-title">회원 정보</h3>
                </summary>
                <div class="mypage-panel-body">
                    <dl class="mypage-dl">
                        <div>
                            <dt>이름</dt>
                            <dd><?php echo htmlspecialchars($member['mb_name']); ?></dd>
                        </div>
                        <div>
                            <dt>이메일</dt>
                            <dd><?php echo htmlspecialchars($member['mb_email']); ?></dd>
                        </div>
                        <div>
                            <dt>휴대폰</dt>
                            <dd><?php echo $member['mb_phone'] !== null && $member['mb_phone'] !== '' ? htmlspecialchars($member['mb_phone']) : '—'; ?></dd>
                        </div>
                        <div>
                            <dt>계정 상태</dt>
                            <dd><?php echo htmlspecialchars($status_label); ?></dd>
                        </div>
                        <?php if ($cash_ready): ?>
                        <div>
                            <dt>보유 캐시</dt>
                            <dd>₩<?php echo number_format($cash_bal); ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt>로그인 횟수</dt>
                            <dd><?php echo number_format((int) $member['mb_login_count']); ?>회</dd>
                        </div>
                        <div>
                            <dt>최근 로그인</dt>
                            <dd>
                                <?php
                                if (!empty($member['mb_last_login_at'])) {
                                    echo htmlspecialchars($member['mb_last_login_at']);
                                    if (!empty($member['mb_last_login_ip'])) {
                                        echo ' <span class="mypage-muted">(' . htmlspecialchars($member['mb_last_login_ip']) . ')</span>';
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </details>

            <details class="mypage-panel mypage-panel--fold" data-mypage-fold="quick"<?php echo $mypage_fold_quick_open ? ' open' : ''; ?>>
                <summary class="mypage-panel-summary">
                    <h3 class="mypage-panel-title">바로가기</h3>
                </summary>
                <div class="mypage-panel-body">
                    <ul class="mypage-quick">
                        <li><a href="/page/member_info.php">내 정보 · 정산계좌</a></li>
                        <li><a href="/page/member_shop_settings.php">상점 설정</a></li>
                        <li><a href="/page/attendance.php">출석체크 · 포인트 내역</a></li>
                        <?php if ($cash_ready): ?>
                        <li><a href="/page/mypage_cash_charge.php">캐시 충전</a></li>
                        <?php endif; ?>
                        <?php if ($cash_log_ready): ?>
                        <li><a href="/page/mypage_cash.php">캐시 내역</a></li>
                        <li><a href="/page/mypage_cash_withdraw.php">판매금 출금</a></li>
                        <?php endif; ?>
                        <?php if ($coupon_ready): ?>
                        <li><a href="/page/mypage_coupons.php">내 쿠폰<?php if ($cnt_coupon > 0): ?> (<?php echo number_format($cnt_coupon); ?>)<?php endif; ?></a></li>
                        <?php endif; ?>
                        <?php if ($wish_ready): ?>
                        <li><a href="/page/mypage_wishes.php">찜 리스트<?php if ($cnt_wish > 0): ?> (<?php echo number_format($cnt_wish); ?>)<?php endif; ?></a></li>
                        <?php endif; ?>
                        <li><a href="/trade/trade_messages.php">거래 메시지함</a></li>
                        <li><a href="/trade/trade_sales.php">판매내역<?php if ($cnt_sale_pending_ship > 0): ?> (<?php echo number_format($cnt_sale_pending_ship); ?>)<?php endif; ?></a></li>
                        <?php if (function_exists('trade_purchase_tables_ready') && trade_purchase_tables_ready()): ?>
                        <li><a href="/trade/trade_purchases.php">구매내역</a></li>
                        <?php endif; ?>
                        <?php if ($auction_ok): ?>
                        <li><a href="/auction/mypage_auction.php">나의 경매 내역</a></li>
                        <li><a href="/auction/mypage_auction_wins.php">나의 낙찰 내역</a></li>
                        <li><a href="/auction/auction.php">경매 둘러보기</a></li>
                        <?php endif; ?>
                        <li><a href="/page/inquiry.php">1:1 문의 내역</a></li>
                        <li><a href="/page/community.php">커뮤니티</a></li>
                        <li><a href="/trade/trade.php">거래게시판</a></li>
                        <li><a href="/page/notice.php">공지사항</a></li>
                        <li><a href="/logout.php" class="mypage-quick-logout">로그아웃</a></li>
                    </ul>
                </div>
            </details>
        </div>

        <div class="mypage-panels mypage-panels-wide">
            <div class="mypage-panel">
                <div class="mypage-panel-head">
                    <h3 class="mypage-panel-title">최근 커뮤니티 글</h3>
                    <a href="/page/community.php" class="link-more">더보기 →</a>
                </div>
                <?php if (empty($recent_co)): ?>
                    <p class="mypage-empty">작성한 커뮤니티 글이 없습니다.</p>
                <?php else: ?>
                    <ul class="mypage-recent">
                        <?php foreach ($recent_co as $row): ?>
                            <li>
                                <a href="/page/community_view.php?idx=<?php echo (int) $row['co_idx']; ?>">
                                    <span class="mypage-recent-title"><?php echo htmlspecialchars($row['co_title']); ?></span>
                                    <time class="mypage-recent-date" datetime="<?php echo date('c', strtotime($row['co_created_at'])); ?>">
                                        <?php echo date('Y-m-d', strtotime($row['co_created_at'])); ?>
                                    </time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="mypage-panel">
                <div class="mypage-panel-head">
                    <h3 class="mypage-panel-title">최근 거래글</h3>
                    <a href="/trade/trade.php" class="link-more">더보기 →</a>
                </div>
                <?php if (empty($recent_tr)): ?>
                    <p class="mypage-empty">등록한 거래글이 없습니다.</p>
                <?php else: ?>
                    <ul class="mypage-recent">
                        <?php foreach ($recent_tr as $row): ?>
                            <li>
                                <a href="/trade/trade_view.php?idx=<?php echo (int) $row['tr_idx']; ?>">
                                    <span class="mypage-recent-title"><?php echo htmlspecialchars($row['tr_title']); ?></span>
                                    <?php if (trade_is_held($row)): ?>
                                        <span class="deal-status ds-hold">게시중지</span>
                                    <?php endif; ?>
                                    <time class="mypage-recent-date" datetime="<?php echo date('c', strtotime($row['tr_created_at'])); ?>">
                                        <?php echo date('Y-m-d', strtotime($row['tr_created_at'])); ?>
                                    </time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($auction_ok): ?>
            <div class="mypage-panel">
                <div class="mypage-panel-head">
                    <h3 class="mypage-panel-title">나의 경매 내역</h3>
                    <a href="/auction/mypage_auction.php" class="link-more">더보기 →</a>
                </div>
                <?php if (empty($recent_auction)): ?>
                    <p class="mypage-empty">등록한 경매가 없습니다. <a href="/auction/auction_write.php">경매 등록</a></p>
                <?php else: ?>
                    <ul class="mypage-recent">
                        <?php foreach ($recent_auction as $row): ?>
                            <li>
                                <a href="/auction/auction_view.php?idx=<?php echo (int) $row['au_idx']; ?>">
                                    <span class="mypage-recent-main">
                                        <span class="mypage-recent-title"><?php echo htmlspecialchars($row['au_title']); ?></span>
                                        <span class="mypage-recent-meta">
                                            ₩<?php echo number_format((int) $row['au_current_price']); ?>
                                            · <?php echo htmlspecialchars(auction_mypage_status_text($row)); ?>
                                        </span>
                                    </span>
                                    <time class="mypage-recent-date" datetime="<?php echo date('c', strtotime($row['au_created_at'])); ?>">
                                        <?php echo date('Y-m-d', strtotime($row['au_created_at'])); ?>
                                    </time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="mypage-panel">
                <div class="mypage-panel-head">
                    <h3 class="mypage-panel-title">나의 낙찰 내역</h3>
                    <a href="/auction/mypage_auction_wins.php" class="link-more">더보기 →</a>
                </div>
                <?php if (empty($recent_au_win)): ?>
                    <p class="mypage-empty">낙찰 내역이 없습니다. <a href="/auction/auction.php">경매 보기</a></p>
                <?php else: ?>
                    <ul class="mypage-recent">
                        <?php foreach ($recent_au_win as $row):
                            $win_date = !empty($row['au_updated_at']) ? $row['au_updated_at'] : $row['au_created_at'];
                            ?>
                            <li>
                                <a href="/auction/auction_view.php?idx=<?php echo (int) $row['au_idx']; ?>">
                                    <span class="mypage-recent-main">
                                        <span class="mypage-recent-title"><?php echo htmlspecialchars($row['au_title']); ?></span>
                                        <span class="mypage-recent-meta">
                                            낙찰가 ₩<?php echo number_format((int) $row['au_current_price']); ?>
                                        </span>
                                    </span>
                                    <time class="mypage-recent-date" datetime="<?php echo date('c', strtotime($win_date)); ?>">
                                        <?php echo date('Y-m-d', strtotime($win_date)); ?>
                                    </time>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    var COOKIE = 'pz_mypage_fold';
    var MAX_AGE = 60 * 60 * 24 * 365;
    var hasServerCookie = <?php echo $mypage_fold_has_cookie ? 'true' : 'false'; ?>;
    var applying = false;

    function getCookie(name) {
        var esc = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var m = document.cookie.match(new RegExp('(?:^|; )' + esc + '=([^;]*)'));
        if (!m) return '';
        try {
            return decodeURIComponent(m[1]);
        } catch (e) {
            return m[1] || '';
        }
    }

    function setCookie(name, value) {
        // 콜론/쉼표 그대로 저장해 PHP $_COOKIE 파싱과 맞춤 (인코딩하지 않음)
        document.cookie = name + '=' + value
            + '; path=/; max-age=' + MAX_AGE
            + '; SameSite=Lax';
    }

    function parseState(raw) {
        var out = { member: null, quick: null };
        if (!raw) return out;
        String(raw).split(',').forEach(function (part) {
            var pair = part.split(':');
            if (pair.length !== 2) return;
            var key = String(pair[0] || '').trim();
            if (key !== 'member' && key !== 'quick') return;
            out[key] = String(pair[1]).trim() === '1';
        });
        return out;
    }

    function currentState(panels) {
        var out = { member: true, quick: true };
        panels.forEach(function (el) {
            var key = el.getAttribute('data-mypage-fold') || '';
            if (key === 'member' || key === 'quick') {
                out[key] = !!el.open;
            }
        });
        return out;
    }

    function saveState(panels) {
        var st = currentState(panels);
        setCookie(COOKIE, 'member:' + (st.member ? '1' : '0') + ',quick:' + (st.quick ? '1' : '0'));
    }

    function syncQuickHeight() {
        var member = document.querySelector('[data-mypage-fold="member"]');
        var quick = document.querySelector('[data-mypage-fold="quick"]');
        if (!member || !quick) return;

        var body = quick.querySelector('.mypage-panel-body');
        var summary = quick.querySelector('.mypage-panel-summary');
        var isDesktop = window.matchMedia && window.matchMedia('(min-width: 769px)').matches;

        function clearSync() {
            quick.style.height = '';
            quick.classList.remove('is-height-synced');
            if (body) {
                body.style.maxHeight = '';
            }
        }

        if (!isDesktop || !quick.open || !body) {
            clearSync();
            return;
        }

        // 회원정보가 접혀 있으면 바로가기에 기본 스크롤 높이만 적용
        if (!member.open) {
            quick.style.height = '';
            quick.classList.remove('is-height-synced');
            body.style.maxHeight = '360px';
            return;
        }

        clearSync();
        var target = member.offsetHeight;
        if (target < 1) return;

        var summaryH = summary ? summary.offsetHeight : 0;
        var summaryStyle = summary ? window.getComputedStyle(summary) : null;
        var summaryMargin = summaryStyle
            ? (parseFloat(summaryStyle.marginBottom) || 0)
            : 0;
        var bodyMax = Math.floor(target - summaryH - summaryMargin);
        if (bodyMax < 80) bodyMax = 80;

        quick.style.height = target + 'px';
        quick.classList.add('is-height-synced');
        body.style.maxHeight = bodyMax + 'px';
    }

    try {
        var panels = Array.prototype.slice.call(document.querySelectorAll('[data-mypage-fold]'));
        if (!panels.length) return;

        // 쿠키 없을 때 모바일만 기본 접힘 + 쿠키 저장
        if (!hasServerCookie && window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
            applying = true;
            panels.forEach(function (el) { el.open = false; });
            applying = false;
            saveState(panels);
        }

        panels.forEach(function (el) {
            el.addEventListener('toggle', function () {
                if (applying) return;
                saveState(panels);
                window.requestAnimationFrame(syncQuickHeight);
            });
        });

        window.addEventListener('resize', function () {
            window.requestAnimationFrame(syncQuickHeight);
        });
        window.requestAnimationFrame(function () {
            syncQuickHeight();
            window.setTimeout(syncQuickHeight, 50);
        });
    } catch (e) {}
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
