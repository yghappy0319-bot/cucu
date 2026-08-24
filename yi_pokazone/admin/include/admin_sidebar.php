<?php
/** @var string $ad_menu current menu key */
$__ad_menu = isset($ad_menu) ? (string) $ad_menu : '';
$__ad_nav  = [
    [
        'group' => 'trade',
        'label' => '거래관리',
        'children' => [
            ['key' => 'trades', 'label' => '거래글관리', 'href' => '/trade/admin/trades.php'],
            ['key' => 'trade_payments', 'label' => '결제내역', 'href' => '/admin/trade_payments.php'],
            ['key' => 'trade_fees', 'label' => '판매자 수수료', 'href' => '/admin/trade_fees.php'],
            ['key' => 'trade_fees_period', 'label' => '판매자 수수료 집계', 'href' => '/admin/trade_fees_period.php'],
        ],
    ],
    [
        'group' => 'auction',
        'label' => '경매관리',
        'children' => [
            ['key' => 'auction_listings', 'label' => '경매 등록내역', 'href' => '/auction/admin/auction_listings.php'],
            ['key' => 'auction_bids', 'label' => '입찰내역', 'href' => '/auction/admin/auction_bids.php'],
            ['key' => 'auction_wins', 'label' => '낙찰내역', 'href' => '/auction/admin/auction_wins.php'],
            ['key' => 'auction_settlements', 'label' => '경매 정산내역', 'href' => '/auction/admin/auction_settlements.php'],
        ],
    ],
    [
        'group' => 'card_price',
        'label' => '박스 시세',
        'children' => [
            ['key' => 'snkrdunk_boxes', 'label' => '한글명 관리', 'href' => '/admin/snkrdunk_boxes.php'],
            ['key' => 'card_price', 'label' => '기존 박스 상품', 'href' => '/admin/card_price_products.php'],
            ['key' => 'card_price_offers', 'label' => '전체 박스 관리', 'href' => '/admin/card_price_offers.php'],
        ],
    ],
    [
        'group' => 'market_price',
        'label' => '전체 시세',
        'children' => [
            ['key' => 'pokemon_market', 'label' => '포켓몬명 관리', 'href' => '/admin/pokemon_market.php'],
        ],
    ],
    ['key' => 'admins',     'label' => '관리자관리',    'href' => '/admin/admins.php'],
    ['key' => 'members',    'label' => '회원관리',      'href' => '/admin/members.php'],
    ['key' => 'posts',      'label' => '게시글관리',    'href' => '/admin/posts.php'],
    ['key' => 'trade_bank_deposits', 'label' => '무통장 입금 내역', 'href' => '/admin/trade_bank_deposits.php'],
    ['key' => 'cron_job_logs', 'label' => '크론 실행 로그', 'href' => '/admin/cron_job_logs.php'],
    ['key' => 'shop_products', 'label' => '쇼핑몰 상품', 'href' => '/admin/shop_products.php'],
    ['key' => 'coupons', 'label' => '쿠폰 발행', 'href' => '/admin/coupons.php'],
    ['key' => 'draw_products', 'label' => '뽑기 관리', 'href' => '/admin/draw_products.php'],
    ['key' => 'notices',    'label' => '공지사항',      'href' => '/admin/notices.php'],
    ['key' => 'popups',     'label' => '레이어 팝업',   'href' => '/admin/popups.php'],
    ['key' => 'push_broadcast', 'label' => '전체 푸시', 'href' => '/admin/push_broadcast.php'],
    ['key' => 'inquiries',  'label' => '1:1 문의',      'href' => '/admin/inquiries.php'],
    ['key' => 'points',     'label' => '포인트 지급내역', 'href' => '/admin/points.php'],
    ['key' => 'cash_logs',  'label' => '캐시 지급내역', 'href' => '/admin/cash_logs.php'],
    ['key' => 'cash_withdraws', 'label' => '출금 신청', 'href' => '/admin/cash_withdraws.php'],
    ['key' => 'settings',   'label' => '사이트 설정',   'href' => '/admin/settings.php'],
];
?>
<aside class="ad-sidebar" aria-label="관리 메뉴">
    <nav class="ad-nav">
        <ul class="ad-nav__list">
            <?php foreach ($__ad_nav as $item):
                if (!empty($item['children']) && is_array($item['children'])):
                    $group_id = trim((string) ($item['group'] ?? ''));
                    if ($group_id === '') {
                        $group_id = 'group_' . substr(md5((string) ($item['label'] ?? '')), 0, 8);
                    }
                    $has_active_child = false;
                    foreach ($item['children'] as $child) {
                        if ($__ad_menu === (string) ($child['key'] ?? '')) {
                            $has_active_child = true;
                            break;
                        }
                    }
                    $sub_id = 'ad-nav-sub-' . $group_id;
                    ?>
                    <li class="ad-nav__item ad-nav__item--group<?php echo $has_active_child ? '' : ' is-collapsed'; ?>"
                        data-ad-nav-group="<?php echo htmlspecialchars($group_id, ENT_QUOTES, 'UTF-8'); ?>"
                        data-ad-nav-active="<?php echo $has_active_child ? '1' : '0'; ?>">
                        <button type="button"
                                class="ad-nav__group-toggle"
                                aria-expanded="<?php echo $has_active_child ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo htmlspecialchars($sub_id, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="ad-nav__group-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="ad-nav__group-chevron" aria-hidden="true"></span>
                        </button>
                        <ul class="ad-nav__sublist" id="<?php echo htmlspecialchars($sub_id, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php foreach ($item['children'] as $child):
                                $ckey = (string) ($child['key'] ?? '');
                                $is_active = ($__ad_menu === $ckey);
                                ?>
                                <li class="ad-nav__subitem">
                                    <a class="ad-nav__sublink<?php echo $is_active ? ' is-active' : ''; ?>"
                                       href="<?php echo htmlspecialchars((string) ($child['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                                       <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                        <?php echo htmlspecialchars((string) ($child['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else:
                    $is_active = ($__ad_menu === (string) ($item['key'] ?? ''));
                    ?>
                    <li class="ad-nav__item">
                        <a class="ad-nav__link<?php echo $is_active ? ' is-active' : ''; ?>"
                           href="<?php echo htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                            <?php echo htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endif;
            endforeach; ?>
        </ul>
    </nav>
</aside>
